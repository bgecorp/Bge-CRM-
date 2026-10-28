<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Sale;
use App\Models\Store;
use App\Services\StockPredictor;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sales performance per customer for a date range, with several ranking metrics
 * and each store's rank inside its own category (a grocery and a small sari-sari
 * store should not be judged on the same volume).
 */
class PerformanceController extends Controller
{
    /** Sort key => [row field, default direction]. Nulls always sort last. */
    private const SORTS = [
        'packs' => ['packs', 'desc'],
        'amount' => ['amount', 'desc'],
        'avg_daily' => ['avg_daily_sales', 'desc'],
        'stock_days' => ['estimated_stock_days', 'asc'],
        'growth' => ['growth_pct', 'desc'],
        'days_since_order' => ['days_since_last_order', 'desc'],
        'orders' => ['orders_in_range', 'desc'],
        'priority' => ['days_until_next_purchase', 'asc'],
    ];

    public function __construct(private StockPredictor $predictor) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'category' => ['nullable', Rule::in(Store::CATEGORIES)],
            'sales_rep_id' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            // up = selling more than the previous period of the same length, down = less.
            'trend' => ['nullable', Rule::in(['up', 'down'])],
        ]);

        $today = CarbonImmutable::today();
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to']) : $today;
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from']) : $to->subDays(29);
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        $length = (int) $from->diffInDays($to) + 1;
        $prevTo = $from->subDay();
        $prevFrom = $prevTo->subDays($length - 1);

        $stores = Store::query()
            ->with('salesRep:id,name')
            ->whereIn('status', ['active', 'prospect'])
            ->when($data['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($data['sales_rep_id'] ?? null, fn ($q, $r) => $q->where('sales_rep_id', $r))
            ->get();
        $ids = $stores->pluck('id');

        // Records ending after the range can still cover days inside it; dailySales() clips to the range.
        $sales = Sale::whereIn('store_id', $ids)
            ->whereBetween('sale_date', [$prevFrom->toDateString(), $to->addDays(366)->toDateString()])
            ->get(['store_id', 'sale_date', 'quantity', 'days_covered', 'amount'])
            ->groupBy('store_id');

        $orders = Order::whereIn('store_id', $ids)->where('delivered_at', '<=', $today->toDateString())
            ->orderBy('delivered_at')->get(['store_id', 'delivered_at', 'quantity'])->groupBy('store_id');

        $predictions = $this->predictor->forStores($ids)->groupBy('store_id');

        $rows = $stores->map(function (Store $store) use ($sales, $orders, $predictions, $from, $to, $prevFrom, $prevTo, $today) {
            $storeSales = $sales->get($store->id, collect());
            $current = $this->predictor->dailySales($storeSales, $from, $to);
            $previous = $this->predictor->dailySales($storeSales, $prevFrom, $prevTo);
            $packs = array_sum($current);
            $prevPacks = array_sum($previous);

            // Value of sales in range, apportioned like the packs.
            $amount = $storeSales->sum(function ($s) use ($from, $to) {
                $days = $this->predictor->dailySales(collect([$s]), $from, $to);

                return $s->quantity > 0 ? (float) $s->amount * array_sum($days) / (float) $s->quantity : 0;
            });

            $storeOrders = $orders->get($store->id, collect());
            $inRange = $storeOrders->filter(fn ($o) => $o->delivered_at->betweenIncluded($from, $to))->values();
            $lastOrder = $storeOrders->last()?->delivered_at;
            $gaps = [];
            for ($i = 1; $i < $inRange->count(); $i++) {
                $gaps[] = $inRange[$i - 1]->delivered_at->diffInDays($inRange[$i]->delivered_at);
            }

            $storePredictions = $predictions->get($store->id, collect());
            // The product they will need soonest.
            $urgent = $storePredictions->sortBy(fn ($p) => StockPredictor::nextPurchaseOrder($p))->first();

            return [
                'id' => $store->id,
                'name' => $store->name,
                'category' => $store->category,
                'sales_rep' => $store->salesRep?->name,
                'packs' => $packs,
                'amount' => round($amount, 2),
                'selling_days' => count($current),
                'avg_daily_sales' => count($current) ? $packs / count($current) : null,
                'previous_packs' => $prevPacks,
                'growth_pct' => $prevPacks > 0 ? ($packs - $prevPacks) / $prevPacks * 100 : null,
                'growth_note' => $prevPacks > 0 ? null : ($packs > 0 ? 'new' : null),
                'trend' => $packs > $prevPacks ? 'up' : ($packs < $prevPacks ? 'down' : 'flat'),
                'estimated_stock_days' => $storePredictions->min('estimated_stock_days'),
                'buying_pattern' => $urgent['buying_pattern'] ?? null,
                'basis' => $urgent['basis'] ?? null,
                'last_order_date' => $lastOrder?->toDateString(),
                'days_since_last_order' => $lastOrder ? (int) $lastOrder->diffInDays($today) : null,
                'orders_in_range' => $inRange->count(),
                'avg_days_between_orders' => $gaps ? array_sum($gaps) / count($gaps) : null,
                'next_purchase_date' => $urgent['next_purchase_date'] ?? null,
                'days_until_next_purchase' => $urgent['days_until_next_purchase'] ?? null,
            ];
        });

        if ($data['trend'] ?? null) {
            $rows = $rows->where('trend', $data['trend'])->values();
        }

        $sortKey = $data['sort'] ?? 'packs';
        [$field, $defaultDir] = self::SORTS[$sortKey];
        $desc = ($data['dir'] ?? $defaultDir) === 'desc';
        $sorted = $this->sortNullsLast($rows, $field, $desc);

        // Rank inside each category on the same metric.
        $ranks = [];
        $sorted = $sorted->map(function ($row) use (&$ranks, $sorted, $field) {
            $ranks[$row['category']] = ($ranks[$row['category']] ?? 0) + 1;
            $row['category_rank'] = $row[$field] === null ? null : $ranks[$row['category']];
            $row['category_size'] = $sorted->where('category', $row['category'])->count();

            return $row;
        });

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'previous_from' => $prevFrom->toDateString(),
            'previous_to' => $prevTo->toDateString(),
            'sort' => $sortKey,
            'dir' => $desc ? 'desc' : 'asc',
            'trend' => $data['trend'] ?? null,
            'rows' => $sorted->values(),
        ];
    }

    private function sortNullsLast($rows, string $field, bool $desc)
    {
        [$withValue, $without] = $rows->partition(fn ($r) => $r[$field] !== null);

        return $withValue->sortBy($field, SORT_REGULAR, $desc)->values()->concat($without->sortBy('name')->values());
    }
}
