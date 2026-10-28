<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Predicts, for every customer and product, how long their latest order will
 * last and when they will probably buy again, using only their order history.
 *
 *   A. Average daily consumption = packs consumed ÷ days observed
 *      Each order except the latest is assumed used up by the time of the next
 *      order: 12 packs on Oct 1, next order Oct 6 → 12 packs over 5 days.
 *      All intervals are pooled: (12 + 6) ÷ (5 + 4) = 2 packs/day. With
 *      "recent weighting" on, recent intervals count more.
 *   B. Estimated stock duration  = latest order quantity ÷ average daily consumption
 *   C. Predicted next purchase   = latest order date + estimated stock duration
 *      (whole days, rounded up).
 *
 * 1st order  = baseline only, no prediction.
 * 2nd order  = first estimate (one interval).
 * 3rd onward = refined estimate (several intervals); recalculated on every order.
 *
 * Several orders on the same day count as one order. Exact values are kept;
 * rounding happens only for display. This is a buying-pattern estimate: it
 * cannot know leftovers, extra stock or sharing, so it is never a stock count.
 */
class StockPredictor
{
    public function __construct(private CrmSettings $settings) {}

    /**
     * Predictions for every store/product pair with at least one order.
     *
     * @return Collection<int, array>
     */
    public function forStores($storeIds, ?CarbonImmutable $asOf = null): Collection
    {
        $storeIds = collect($storeIds)->values();
        if ($storeIds->isEmpty()) {
            return collect();
        }

        $settings = $this->settings->all();
        $asOf ??= CarbonImmutable::today();

        $orders = Order::query()
            ->whereIn('store_id', $storeIds)
            ->where('delivered_at', '<=', $asOf->toDateString())
            ->orderBy('delivered_at')
            ->get(['store_id', 'product_id', 'delivered_at', 'quantity'])
            ->groupBy(fn ($o) => $o->store_id.':'.$o->product_id);

        $products = Product::whereIn('id', $orders->keys()->map(fn ($k) => (int) explode(':', $k)[1])->unique())->pluck('name', 'id');

        return $orders->map(function (Collection $rows, string $k) use ($products, $settings, $asOf) {
            [$storeId, $productId] = array_map('intval', explode(':', $k));

            return [
                'store_id' => $storeId,
                'product_id' => $productId,
                'product_name' => $products[$productId] ?? 'Unknown product',
            ] + $this->estimate($rows, $settings, $asOf);
        })->values();
    }

    /**
     * Core calculation for one customer and product. Pure apart from its inputs.
     *
     * @param  Collection  $orders  orders with delivered_at and quantity, any order
     */
    public function estimate(Collection $orders, array $settings, CarbonImmutable $asOf): array
    {
        $settings += CrmSettings::DEFAULTS;

        // One entry per order day: date => packs.
        $byDay = [];
        foreach ($orders as $o) {
            $day = CarbonImmutable::parse($o->delivered_at)->toDateString();
            $byDay[$day] = ($byDay[$day] ?? 0) + (float) $o->quantity;
        }
        ksort($byDay);
        $dates = array_keys($byDay);
        $count = count($dates);

        $latestDate = CarbonImmutable::parse($dates[$count - 1]);
        $latestQty = $byDay[$dates[$count - 1]];
        $totalPacks = array_sum($byDay);

        // Intervals between consecutive orders: the earlier order's packs over the days until the next.
        $intervals = [];
        for ($i = 0; $i < $count - 1; $i++) {
            $intervals[] = [
                'packs' => $byDay[$dates[$i]],
                'days' => (int) CarbonImmutable::parse($dates[$i])->diffInDays(CarbonImmutable::parse($dates[$i + 1])),
                'end' => CarbonImmutable::parse($dates[$i + 1]),
            ];
        }

        $avgGap = $intervals ? array_sum(array_column($intervals, 'days')) / count($intervals) : null;
        $rate = $intervals ? $this->consumption($intervals, $settings, $asOf) : null;

        $duration = null;
        $nextDate = null;
        if ($rate !== null && $rate > 0) {
            $duration = $latestQty / $rate;
            $nextDate = $latestDate->addDays((int) ceil($duration));
        }

        $basis = match (true) {
            $count === 1 => 'baseline',
            $rate === null || $rate <= 0 => 'no_usage',
            $count === 2 => 'first_estimate',
            default => 'refined',
        };

        return [
            'total_orders' => $count,
            'total_packs' => $totalPacks,
            'first_order_date' => $dates[0],
            'avg_days_between_orders' => $avgGap,
            'avg_daily_consumption' => $rate,
            'weighted' => (bool) $settings['recent_weighting'] && count($intervals) > 1,
            'latest_order_date' => $latestDate->toDateString(),
            'latest_order_qty' => $latestQty,
            'estimated_stock_days' => $duration,
            'next_purchase_date' => $nextDate?->toDateString(),
            'days_until_next_purchase' => $nextDate ? (int) $asOf->diffInDays($nextDate, false) : null,
            'days_since_latest_order' => (int) $latestDate->diffInDays($asOf),
            'basis' => $basis,
            'buying_pattern' => $this->pattern($avgGap, $settings),
            'summary' => $this->summary($basis, $intervals, $rate, $latestQty, $latestDate, $duration, $nextDate, $avgGap),
            'is_estimate' => true,
        ];
    }

    /**
     * Packs sold per calendar day inside a range: each sale record's packs spread
     * evenly over the days it covers. Used for sales-performance figures.
     *
     * @return array<string, float> date => packs
     */
    public function dailySales(Collection $sales, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $daily = [];
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        foreach ($sales as $sale) {
            $end = CarbonImmutable::parse($sale->sale_date);
            $span = max(1, (int) $sale->days_covered);
            $perDay = (float) $sale->quantity / $span;
            for ($i = 0; $i < $span; $i++) {
                $day = $end->subDays($i)->toDateString();
                if ($day >= $fromStr && $day <= $toStr) {
                    $daily[$day] = ($daily[$day] ?? 0) + $perDay;
                }
            }
        }
        ksort($daily);

        return $daily;
    }

    /** Sort key: soonest predicted purchase first; customers without a prediction last. */
    public static function nextPurchaseOrder(array $prediction): string
    {
        return $prediction['next_purchase_date'] ?? '9999-12-31';
    }

    /** Pooled packs ÷ days, optionally weighting recent intervals more. */
    private function consumption(array $intervals, array $settings, CarbonImmutable $asOf): float
    {
        $halfLife = max(1, (int) $settings['weighting_half_life_days']);
        $packs = 0.0;
        $days = 0.0;
        foreach ($intervals as $interval) {
            $w = $settings['recent_weighting'] ? 0.5 ** ($interval['end']->diffInDays($asOf) / $halfLife) : 1.0;
            $packs += $w * $interval['packs'];
            $days += $w * $interval['days'];
        }

        return $days > 0 ? $packs / $days : 0.0;
    }

    private function pattern(?float $avgGap, array $settings): string
    {
        return match (true) {
            $avgGap === null => 'new',
            $avgGap <= (int) $settings['frequent_max_days'] => 'frequent',
            $avgGap <= (int) $settings['moderate_max_days'] => 'moderate',
            default => 'occasional',
        };
    }

    /** One plain-language sentence explaining the result. */
    private function summary(string $basis, array $intervals, ?float $rate, float $latestQty, CarbonImmutable $latestDate, ?float $duration, ?CarbonImmutable $next, ?float $avgGap): string
    {
        if ($basis === 'baseline') {
            return "First order recorded ({$this->fmt($latestQty)} packs on {$latestDate->format('M j')}). The estimate starts when they order again.";
        }
        if ($basis === 'no_usage') {
            return 'The earlier orders have no packs recorded, so usage cannot be estimated yet.';
        }

        $packs = array_column($intervals, 'packs');
        $days = array_column($intervals, 'days');
        $how = count($intervals) === 1
            ? "{$this->fmt($packs[0])} packs lasted {$days[0]} days"
            : 'orders of '.implode(' + ', array_map(fn ($p) => $this->fmt($p), $packs)).' packs over '.implode(' + ', $days).' days';

        return sprintf(
            'Uses about %s packs/day (%s). The latest %s packs may last about %s days, so the next order is expected around %s. Orders every %s days on average.',
            $this->fmt($rate), $how, $this->fmt($latestQty), $this->fmt($duration), $next->format('M j'), $this->fmt($avgGap)
        );
    }

    private function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
