<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Services\FirstOrderFollowUps;
use App\Services\StockPredictor;
use App\Services\XlsxWriter;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreController extends Controller
{
    /** Days of sales shown in the list's "packs sold" column. */
    private const SALES_PERIOD_DAYS = 30;

    public function __construct(private StockPredictor $predictor, private FirstOrderFollowUps $firstOrders) {}

    public function index(Request $request)
    {
        return $this->customerRows($request);
    }

    /** Excel file of the customers shown on the Customers page, with the same filters and order. */
    public function export(Request $request, XlsxWriter $xlsx)
    {
        $rows = $this->customerRows($request)->map(function (array $s) {
            $p = $s['prediction'];

            return [
                $s['name'],
                self::CATEGORY_LABELS[$s['category']] ?? $s['category'],
                $s['contact_person'],
                $s['phone'],
                $s['address'],
                $s['sales_rep']['name'] ?? 'Unassigned',
                self::STATUS_LABELS[$s['status']] ?? $s['status'],
                $s['sales_packs'],
                $s['sales_amount'],
                $p['product_name'] ?? null,
                $p['total_orders'] ?? null,
                $p['latest_order_date'] ?? null,
                $p['next_purchase_date'] ?? null,
                $p['days_until_next_purchase'] ?? null,
                self::orderStatus($p),
                isset($p['buying_pattern']) ? (self::PATTERN_LABELS[$p['buying_pattern']] ?? $p['buying_pattern']) : null,
                $s['facebook_url'],
                $s['notes'],
                empty($s['created_at']) ? null : Carbon::parse($s['created_at'])->timezone(config('app.timezone'))->toDateString(),
            ];
        });

        $path = $xlsx->write('Customers', [
            ['Store name', 28], ['Category', 18], ['Contact person', 22], ['Phone', 16], ['Address', 36],
            ['Representative', 20], ['Customer status', 16], ['Packs sold (30 days)', 20, 'number'], ['Sales (30 days)', 16, 'money'],
            ['Product needed soonest', 30], ['Total orders', 13, 'number'], ['Latest order', 15, 'date'],
            ['Predicted next purchase', 23, 'date'], ['Days until next purchase', 23, 'number'], ['Order status', 22],
            ['Buying pattern', 16], ['Facebook', 30], ['Notes', 40], ['Registered', 14, 'date'],
        ], $rows);

        return response()->download($path, 'customers-'.today()->toDateString().'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** Same wording as the "Order status" column on the Customers page. */
    private static function orderStatus(?array $p): string
    {
        if (! $p) {
            return 'No orders yet';
        }
        if ($p['basis'] === 'baseline') {
            return 'Waiting for 2nd order';
        }
        $days = $p['days_until_next_purchase'];

        return match (true) {
            $days === null => 'Cannot estimate',
            $days < 0 => 'Order expected already',
            $days === 0 => 'Ordering today',
            $days <= 3 => 'Ordering soon',
            default => 'Has stock',
        };
    }

    private const CATEGORY_LABELS = [
        'small_sari_sari' => 'Small Sari-Sari', 'regular_sari_sari' => 'Regular Sari-Sari',
        'mini_grocery' => 'Mini-Grocery', 'regular_grocery' => 'Regular Grocer',
    ];

    private const STATUS_LABELS = ['prospect' => 'Prospect', 'active' => 'Active', 'inactive' => 'Inactive', 'lost' => 'Lost'];

    private const PATTERN_LABELS = ['frequent' => 'Frequent', 'moderate' => 'Moderate', 'occasional' => 'Occasional', 'new' => 'New (1 order)'];

    /** Customers with their 30-day sales and soonest prediction, filtered and sorted like the list. */
    private function customerRows(Request $request)
    {
        $stores = Store::query()
            ->with('salesRep:id,name')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('contact_person', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('address', 'like', $term)
                    ->orWhere('facebook_url', 'like', $term)
                    ->orWhere('notes', 'like', $term)
                    ->orWhereHas('sales.product', fn ($p) => $p->where('name', 'like', $term))
                    ->orWhereHas('orders.product', fn ($p) => $p->where('name', 'like', $term)));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('sales_rep_id'), fn ($q) => $q->where('sales_rep_id', $request->sales_rep_id))
            ->get();

        $ids = $stores->pluck('id');
        $since = today()->subDays(self::SALES_PERIOD_DAYS - 1)->toDateString();
        $salesTotals = Sale::whereIn('store_id', $ids)
            ->where('sale_date', '>=', $since)
            ->groupBy('store_id')
            ->selectRaw('store_id, SUM(quantity) as packs, SUM(amount) as amount')
            ->get()
            ->keyBy('store_id');
        $predictions = $this->predictor->forStores($ids)->groupBy('store_id');

        $rows = $stores->map(function (Store $store) use ($salesTotals, $predictions) {
            $row = $store->toArray();
            $row['sales_packs'] = (float) ($salesTotals[$store->id]->packs ?? 0);
            $row['sales_amount'] = (float) ($salesTotals[$store->id]->amount ?? 0);
            // The product they will need soonest.
            $row['prediction'] = $predictions->get($store->id)?->sortBy(fn ($p) => StockPredictor::nextPurchaseOrder($p))->first();

            return $row;
        });

        $desc = $request->input('dir') === 'desc';
        $sorted = match ($request->input('sort', 'name')) {
            'sales' => $rows->sortBy('sales_packs', SORT_REGULAR, $desc),
            // Stores without any estimate go last regardless of direction.
            'urgency' => $rows->sortBy(fn ($r) => $r['prediction']['next_purchase_date'] ?? ($desc ? '0000' : '9999'), SORT_REGULAR, $desc),
            default => $rows->sortBy(fn ($r) => mb_strtolower($r['name']), SORT_REGULAR, $desc),
        };

        return $sorted->values();
    }

    /**
     * Register a store, optionally with its first order per product. That order
     * is the baseline of the order history; predictions start at the second order.
     */
    public function store(Request $request)
    {
        $storeData = $this->validated($request);
        $products = $request->validate([
            'products' => ['nullable', 'array', 'max:50'],
            'products.*.product_id' => ['required', 'distinct', 'exists:products,id'],
            'products.*.packs_ordered' => ['required', 'numeric', 'gt:0'],
            'products.*.order_date' => ['nullable', 'date', 'before_or_equal:today'],
        ])['products'] ?? [];

        $store = DB::transaction(function () use ($storeData, $products) {
            $store = Store::create($storeData);

            foreach ($products as $row) {
                $product = Product::find($row['product_id']);
                $store->orders()->create([
                    'product_id' => $product->id,
                    'delivered_at' => $row['order_date'] ?? today()->toDateString(),
                    'quantity' => $row['packs_ordered'],
                    'unit_price' => $product->price,
                    'amount' => round($row['packs_ordered'] * $product->price, 2),
                    'notes' => 'First order, entered when the store was registered',
                ]);
            }

            return $store;
        });
        $this->firstOrders->sync($store);

        return response()->json($store, 201);
    }
    public function show(Request $request, Store $store)
    {
        $store->load([
            'salesRep:id,name',
            'sales' => fn ($q) => $q->with('product:id,name')->orderByDesc('sale_date')->orderByDesc('id')->limit(100),
            'stockCounts' => fn ($q) => $q->with('product:id,name')->orderByDesc('counted_at')->limit(50),
            'orders' => fn ($q) => $q->with('product:id,name')->orderByDesc('delivered_at')->orderByDesc('id')->limit(100),
            'followUps' => fn ($q) => $q->orderByRaw("status = 'pending' desc")->orderByDesc('due_date')->limit(100),
        ]);

        $data = $store->toArray();
        $data['predictions'] = $this->predictor->forStores([$store->id])->sortBy(fn ($p) => StockPredictor::nextPurchaseOrder($p))->values();

        return $data;
    }

    public function update(Request $request, Store $store)
    {
        $store->update($this->validated($request));
        $store->followUps()->where('status', 'pending')->update(['sales_rep_id' => $store->sales_rep_id]);
        $this->firstOrders->sync($store);

        return $store->load('salesRep:id,name');
    }

    public function destroy(Store $store)
    {
        $store->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        // Accept links typed without "https://" and store them as full links.
        foreach (['facebook_url', 'messenger_url'] as $field) {
            $value = trim((string) $request->input($field));
            if ($value !== '' && ! preg_match('#^https?://#i', $value)) {
                $value = 'https://'.$value;
            }
            $request->merge([$field => $value !== '' ? $value : null]);
        }

        return $request->validate([
            'facebook_url' => ['nullable', 'url', 'max:255', 'regex:#^https?://([a-z0-9-]+\.)*(facebook\.com|fb\.com|fb\.me|m\.me)(/|$)#i'],
            // The business page inbox: business.facebook.com/latest/inbox/... (or facebook.com/latest/inbox/...).
            'messenger_url' => ['nullable', 'url', 'max:500', 'regex:#^https?://(business\.facebook\.com/|(www\.)?facebook\.com/latest/inbox)#i'],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::in(Store::CATEGORIES)],
            'status' => ['required', Rule::in(Store::STATUSES)],
            'sales_rep_id' => ['nullable', 'exists:sales_reps,id'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
