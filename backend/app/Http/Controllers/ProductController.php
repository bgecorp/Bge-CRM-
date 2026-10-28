<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockCount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /** Pictures are kept on the private local disk and served by image(). */
    private const IMAGE_DIR = 'products';

    public function index()
    {
        $since = today()->subDays(29)->toDateString();
        $packs = Sale::where('sale_date', '>=', $since)->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as packs, SUM(amount) as amount')->get()->keyBy('product_id');
        $customers = Sale::query()->select('product_id', 'store_id')
            ->union(StockCount::query()->select('product_id', 'store_id'))
            ->union(Order::query()->select('product_id', 'store_id'))
            ->get()->groupBy('product_id')->map->count();

        return Product::orderBy('name')->get()->map(fn (Product $p) => $p->toArray() + [
            'packs_30_days' => (float) ($packs[$p->id]->packs ?? 0),
            'sales_value_30_days' => (float) ($packs[$p->id]->amount ?? 0),
            'customers' => $customers[$p->id] ?? 0,
        ]);
    }

    public function store(Request $request)
    {
        $product = Product::create($this->validated($request));
        $this->saveImage($request, $product);

        return response()->json($product->fresh(), 201);
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request, $product));
        $this->saveImage($request, $product);

        return $product->fresh();
    }

    public function destroy(Product $product)
    {
        $inUse = Sale::where('product_id', $product->id)->exists()
            || StockCount::where('product_id', $product->id)->exists()
            || Order::where('product_id', $product->id)->exists();
        abort_if($inUse, 422, 'This product has recorded sales, stock counts or orders and cannot be removed.');

        if ($product->image_path) {
            Storage::disk('local')->delete($product->image_path);
        }
        $product->delete();

        return response()->noContent();
    }

    public function image(Product $product)
    {
        abort_unless($product->image_path && Storage::disk('local')->exists($product->image_path), 404);

        return response()->file(Storage::disk('local')->path($product->image_path), [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    public function destroyImage(Product $product)
    {
        if ($product->image_path) {
            Storage::disk('local')->delete($product->image_path);
            $product->image_path = null;
            $product->save();
        }

        return $product->fresh();
    }

    /** Store an uploaded picture (field "image"), replacing the old one. */
    private function saveImage(Request $request, Product $product): void
    {
        if (! $request->hasFile('image')) {
            return;
        }
        $old = $product->image_path;
        $product->image_path = $request->file('image')->store(self::IMAGE_DIR, 'local');
        $product->touch();
        if ($old) {
            Storage::disk('local')->delete($old);
        }
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('products', 'sku')->ignore($product)],
            'unit' => ['required', 'string', 'max:30'],
            'price' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);
        unset($data['image']);

        return $data;
    }
}
