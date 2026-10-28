<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;

class StockCountController extends Controller
{
    public function store(Request $request, Store $store)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'counted_at' => ['required', 'date', 'before_or_equal:today'],
            'quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $count = $store->stockCounts()->create($data);

        return response()->json($count->load('product:id,name'), 201);
    }
}
