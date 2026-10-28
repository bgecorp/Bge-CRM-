<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\Sale;
use App\Models\Store;
use App\Services\StockPredictor;

class DashboardController extends Controller
{
    private const PERIOD_DAYS = 30;

    /** Customers predicted to order within this many days are "ordering soon". */
    private const ORDER_SOON_DAYS = 3;

    public function __construct(private StockPredictor $predictor) {}

    public function __invoke()
    {
        $today = today();
        $todayStr = $today->toDateString();
        $since = $today->copy()->subDays(self::PERIOD_DAYS - 1)->toDateString();

        $activeStores = Store::where('status', 'active')->pluck('name', 'id');
        $predictions = $this->predictor->forStores($activeStores->keys())
            ->map(fn ($p) => $p + ['store_name' => $activeStores[$p['store_id']]]);

        $orderingSoon = $predictions
            ->filter(fn ($p) => $p['days_until_next_purchase'] !== null && $p['days_until_next_purchase'] <= self::ORDER_SOON_DAYS)
            ->sortBy('next_purchase_date')
            ->values();

        // Only one order so far: no prediction until they order again.
        $firstOrderOnly = $predictions->where('basis', 'baseline')->sortBy('store_name')->values();

        $pending = FollowUp::where('status', 'pending');
        $pipeline = [
            'overdue' => (clone $pending)->where('due_date', '<', $todayStr)->count(),
            'today' => (clone $pending)->where('due_date', $todayStr)->count(),
            'next_3_days' => (clone $pending)->whereBetween('due_date', [$today->copy()->addDay()->toDateString(), $today->copy()->addDays(3)->toDateString()])->count(),
            'next_7_days' => (clone $pending)->whereBetween('due_date', [$today->copy()->addDays(4)->toDateString(), $today->copy()->addDays(7)->toDateString()])->count(),
            'later' => (clone $pending)->where('due_date', '>', $today->copy()->addDays(7)->toDateString())->count(),
        ];

        $predicted = $predictions->whereNotNull('days_until_next_purchase');
        $nextPurchases = [
            'past_due' => $predicted->where('days_until_next_purchase', '<', 0)->count(),
            'today' => $predicted->where('days_until_next_purchase', 0)->count(),
            'next_3_days' => $predicted->whereBetween('days_until_next_purchase', [1, 3])->count(),
            'next_7_days' => $predicted->whereBetween('days_until_next_purchase', [4, 7])->count(),
            'later' => $predicted->where('days_until_next_purchase', '>', 7)->count(),
            'first_order_only' => $firstOrderOnly->count(),
        ];
        $patterns = $predictions->groupBy('buying_pattern')->map->count();

        $outcomes = FollowUp::where('status', 'completed')
            ->where('completed_at', '>=', $since)
            ->groupBy('outcome')
            ->selectRaw('outcome, COUNT(*) as n')
            ->pluck('n', 'outcome');

        $windowSales = Sale::where('sale_date', '>=', $since)->where('sale_date', '<=', $todayStr);

        $byDay = (clone $windowSales)
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->selectRaw('sale_date, SUM(quantity) as packs, SUM(amount) as amount')
            ->get()
            ->keyBy(fn ($r) => $r->sale_date->toDateString());

        // Every day in the window, including days with no sales, so the chart is honest.
        $days = [];
        for ($d = $today->copy()->subDays(self::PERIOD_DAYS - 1); $d->lte($today); $d->addDay()) {
            $key = $d->toDateString();
            $days[] = [
                'date' => $key,
                'packs' => (float) ($byDay[$key]->packs ?? 0),
                'amount' => (float) ($byDay[$key]->amount ?? 0),
            ];
        }
        $hasSales = $byDay->isNotEmpty();

        $topStores = (clone $windowSales)
            ->join('stores', 'stores.id', '=', 'sales.store_id')
            ->groupBy('stores.id', 'stores.name')
            ->orderByDesc('packs')
            ->limit(10)
            ->selectRaw('stores.id, stores.name, SUM(sales.quantity) as packs, SUM(sales.amount) as amount')
            ->get();

        $byRep = (clone $windowSales)
            ->join('stores', 'stores.id', '=', 'sales.store_id')
            ->leftJoin('sales_reps', 'sales_reps.id', '=', 'stores.sales_rep_id')
            ->groupBy('sales_reps.id', 'sales_reps.name')
            ->orderByDesc('packs')
            ->selectRaw("sales_reps.id, COALESCE(sales_reps.name, 'Unassigned') as name, COUNT(DISTINCT stores.id) as stores, SUM(sales.quantity) as packs, SUM(sales.amount) as amount")
            ->get();

        // This week (last 7 days) against the 7 days before it.
        $weekStart = $today->copy()->subDays(6)->toDateString();
        $prevStart = $today->copy()->subDays(13)->toDateString();
        $prevEnd = $today->copy()->subDays(7)->toDateString();
        $packsWeek = (float) Sale::whereBetween('sale_date', [$weekStart, $todayStr])->sum('quantity');
        $packsPrevWeek = (float) Sale::whereBetween('sale_date', [$prevStart, $prevEnd])->sum('quantity');
        $topWeek = Sale::whereBetween('sale_date', [$weekStart, $todayStr])
            ->join('stores', 'stores.id', '=', 'sales.store_id')
            ->groupBy('stores.id', 'stores.name')
            ->orderByDesc('packs')
            ->selectRaw('stores.id, stores.name, SUM(sales.quantity) as packs')
            ->first();

        // One row per store: the product they will need soonest.
        $byStore = $predictions->groupBy('store_id')->map(function ($rows) {
            $urgent = $rows->sortBy(fn ($p) => StockPredictor::nextPurchaseOrder($p))->first();

            return array_intersect_key($urgent, array_flip([
                'store_id', 'store_name', 'product_name', 'basis', 'avg_daily_consumption', 'estimated_stock_days',
                'latest_order_qty', 'next_purchase_date', 'days_until_next_purchase', 'buying_pattern',
            ]));
        });

        $stockPrediction = $byStore->whereNotNull('next_purchase_date')->sortBy('next_purchase_date')->take(5)->values();

        $upcoming = FollowUp::with('store:id,name')
            ->where('status', 'pending')
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->limit(5)
            ->get(['id', 'store_id', 'due_date', 'type', 'reason']);

        $packs30 = (clone $windowSales)->groupBy('store_id')->selectRaw('store_id, SUM(quantity) as packs')->pluck('packs', 'store_id');
        $lastSale = Sale::groupBy('store_id')->selectRaw('store_id, MAX(sale_date) as d')->pluck('d', 'store_id');
        $recent = Store::orderByDesc('updated_at')->get(['id', 'name', 'category', 'status', 'created_at', 'updated_at'])
            ->sortByDesc(fn ($s) => max((string) ($lastSale[$s->id] ?? ''), $s->updated_at->toDateString()))
            ->take(5)
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'category' => $s->category,
                'packs' => (float) ($packs30[$s->id] ?? 0),
            ] + array_intersect_key($byStore->get($s->id, []), array_flip(['basis', 'avg_daily_consumption', 'estimated_stock_days', 'next_purchase_date', 'days_until_next_purchase', 'buying_pattern'])))
            ->values();

        return [
            'as_of' => $todayStr,
            'totals' => [
                'stores' => Store::count(),
                'active_customers' => $activeStores->count(),
                'new_this_week' => Store::where('created_at', '>=', $today->copy()->subDays(6)->startOfDay())->count(),
            ],
            'week' => [
                'packs' => $packsWeek,
                'previous_packs' => $packsPrevWeek,
                'change_pct' => $packsPrevWeek > 0 ? ($packsWeek - $packsPrevWeek) / $packsPrevWeek * 100 : null,
                'top_performer' => $topWeek ? ['id' => $topWeek->id, 'name' => $topWeek->name, 'packs' => (float) $topWeek->packs] : null,
            ],
            'follow_ups' => [
                'due_today' => $pipeline['today'],
                'overdue' => $pipeline['overdue'],
                // Distinct customers with a follow-up overdue or due within 3 days.
                'customers_due_soon' => (clone $pending)->where('due_date', '<=', $today->copy()->addDays(3)->toDateString())->distinct('store_id')->count('store_id'),
            ],
            'stock_prediction' => $stockPrediction,
            'upcoming_follow_ups' => $upcoming,
            'recent_customers' => $recent,
            'ordering_soon' => $orderingSoon,
            'first_order_only' => $firstOrderOnly,
            'next_purchases' => $nextPurchases,
            'buying_patterns' => $patterns,
            'pipeline' => $pipeline,
            'outcomes' => $outcomes,
            'sales' => [
                'period_days' => self::PERIOD_DAYS,
                'total_packs' => (float) (clone $windowSales)->sum('quantity'),
                'total_amount' => (float) (clone $windowSales)->sum('amount'),
                'stores_with_sales' => (clone $windowSales)->distinct('store_id')->count('store_id'),
                'by_day' => $hasSales ? $days : [],
                'top_stores' => $topStores,
                'by_rep' => $byRep,
            ],
        ];
    }
}
