<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\SalesRep;
use App\Models\Store;
use App\Services\FollowUpScheduler;
use Illuminate\Database\Seeder;

/**
 * Three clearly labelled sample stores reproducing the worked examples:
 *   12 packs over 5 days = 2.4/day, 8 on hand  -> ~3.33 days
 *    8 packs over 4 days = 2.0/day, stock unknown -> last sale + 7 days
 *    6 packs over 4 days = 1.5/day, 8 on hand  -> ~5.33 days -> follow up on day 5
 */
class SampleDataSeeder extends Seeder
{
    public function run(FollowUpScheduler $scheduler): void
    {
        $rep = SalesRep::create(['name' => 'Sample Rep']);
        $product = Product::create(['name' => 'Sample Product (pack)', 'sku' => 'SAMPLE-001', 'unit' => 'pack', 'price' => 10]);

        $examples = [
            ['Sample Store A', 'small_sari_sari', 12, 5, 8],
            ['Sample Store B', 'regular_sari_sari', 8, 4, null],
            ['Sample Store C', 'mini_grocery', 6, 4, 8],
        ];

        foreach ($examples as [$name, $category, $packs, $days, $stock]) {
            $store = Store::create([
                'name' => $name,
                'category' => $category,
                'status' => 'active',
                'sales_rep_id' => $rep->id,
                'notes' => 'Sample record — safe to delete.',
            ]);

            $store->sales()->create([
                'product_id' => $product->id,
                'sale_date' => today(),
                'quantity' => $packs,
                'days_covered' => $days,
                'unit_price' => $product->price,
                'amount' => $packs * $product->price,
            ]);

            if ($stock !== null) {
                $store->stockCounts()->create(['product_id' => $product->id, 'counted_at' => today(), 'quantity' => $stock]);
            }

            $scheduler->sync($store);
        }
    }
}
