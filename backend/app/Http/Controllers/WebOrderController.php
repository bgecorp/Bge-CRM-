<?php

namespace App\Http\Controllers;

use App\Models\WebOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Orders from the public landing page. Anyone can see the offers and place an
 * order (cash on delivery); only the super admin can list and update them.
 * Prices always come from config/landing.php, never from the visitor.
 */
class WebOrderController extends Controller
{
    public function offers()
    {
        return collect(config('landing.offers'))
            ->map(fn ($offer, $key) => ['key' => $key] + $offer)
            ->values();
    }

    public function store(Request $request)
    {
        // Hidden "website" field: people leave it empty, spam bots fill it in.
        if ($request->filled('website')) {
            return response()->json(['message' => 'Order received.'], 201);
        }

        $offers = config('landing.offers');
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:'.count($offers)],
            'items.*.offer' => ['required', 'distinct', Rule::in(array_keys($offers))],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.config('landing.max_quantity')],
        ], [
            'phone.regex' => 'Enter a valid mobile number, e.g. 0917 123 4567.',
            'items.required' => 'Choose at least one product.',
        ]);

        $items = collect($data['items'])->map(function ($item) use ($offers) {
            $offer = $offers[$item['offer']];

            return [
                'offer' => $item['offer'],
                'name' => $offer['name'],
                'deal' => $offer['deal'],
                'packs' => $offer['packs'],
                'price' => $offer['price'],
                'quantity' => $item['quantity'],
                'subtotal' => $offer['price'] === null ? null : $offer['price'] * $item['quantity'],
            ];
        });

        $order = WebOrder::create([
            'customer_name' => $data['customer_name'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'city' => $data['city'],
            'notes' => $data['notes'] ?? null,
            'items' => $items->all(),
            'total' => $items->sum('subtotal'),
            'price_to_confirm' => $items->contains('price', null),
            'ip_address' => $request->ip(),
        ]);

        return response()->json($order->only(['reference', 'total', 'price_to_confirm']), 201);
    }

    public function index(Request $request)
    {
        $base = WebOrder::query();
        $orders = (clone $base)
            ->with('handler:id,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(500)
            ->get();

        return [
            'orders' => $orders,
            'counts' => (clone $base)->groupBy('status')->selectRaw('status, COUNT(*) as n')->pluck('n', 'status'),
        ];
    }

    public function update(Request $request, WebOrder $webOrder)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(WebOrder::STATUSES)],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $webOrder->update($data + ['handled_by' => $request->user()->id]);

        return $webOrder->load('handler:id,name');
    }

    public function destroy(WebOrder $webOrder)
    {
        $webOrder->delete();

        return response()->noContent();
    }
}
