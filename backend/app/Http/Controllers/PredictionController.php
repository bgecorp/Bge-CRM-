<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\StockPredictor;
use Illuminate\Http\Request;

/** Customer-by-customer stock prediction summary, built from order history. */
class PredictionController extends Controller
{
    public function __construct(private StockPredictor $predictor) {}

    public function index(Request $request)
    {
        $stores = Store::query()
            ->whereIn('status', ['active', 'prospect'])
            ->when($request->filled('sales_rep_id'), fn ($q) => $q->where('sales_rep_id', $request->sales_rep_id))
            ->pluck('name', 'id');

        $rows = $this->predictor->forStores($stores->keys())
            ->map(fn ($p) => $p + ['store_name' => $stores[$p['store_id']]])
            ->when($request->filled('product_id'), fn ($c) => $c->where('product_id', (int) $request->product_id))
            ->when($request->filled('pattern'), fn ($c) => $c->where('buying_pattern', $request->pattern));

        $desc = $request->input('dir') === 'desc';
        $nullsLast = fn ($field) => fn ($r) => $r[$field] ?? ($desc ? -1 : PHP_INT_MAX);

        return match ($request->input('sort', 'next')) {
            'usage' => $rows->sortBy($nullsLast('avg_daily_consumption'), SORT_REGULAR, $desc)->values(),
            'orders' => $rows->sortBy('total_orders', SORT_REGULAR, $desc)->values(),
            'packs' => $rows->sortBy('total_packs', SORT_REGULAR, $desc)->values(),
            'store' => $rows->sortBy(fn ($r) => mb_strtolower($r['store_name']), SORT_REGULAR, $desc)->values(),
            default => $rows->sortBy(fn ($r) => StockPredictor::nextPurchaseOrder($r), SORT_REGULAR, $desc)->values(),
        };
    }
}
