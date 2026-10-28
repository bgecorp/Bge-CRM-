<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function store(Request $request, Store $store)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'sale_date' => ['required', 'date', 'before_or_equal:today'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'days_covered' => ['required', 'integer', 'min:1', 'max:365'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $data['unit_price'] ??= Product::find($data['product_id'])->price;
        $data['amount'] = round($data['quantity'] * $data['unit_price'], 2);

        $sale = $store->sales()->create($data);

        return response()->json($sale->load('product:id,name'), 201);
    }

    public function destroy(Sale $sale)
    {
        $sale->delete();

        return response()->noContent();
    }
}
