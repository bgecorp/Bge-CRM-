<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Services\FirstOrderFollowUps;
use Illuminate\Http\Request;

/** Orders delivered to a store. The order history drives the stock prediction and next-purchase date. */
class OrderController extends Controller
{
    public function __construct(private FirstOrderFollowUps $firstOrders) {}

    public function store(Request $request, Store $store)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'delivered_at' => ['required', 'date', 'before_or_equal:today'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $data['unit_price'] ??= Product::find($data['product_id'])->price;
        $data['amount'] = round($data['quantity'] * $data['unit_price'], 2);

        $order = $store->orders()->create($data);
        $this->firstOrders->sync($store);

        return response()->json($order->load('product:id,name'), 201);
    }

    public function destroy(Order $order)
    {
        $store = $order->store;
        $order->delete();
        $this->firstOrders->sync($store);

        return response()->noContent();
    }
}
