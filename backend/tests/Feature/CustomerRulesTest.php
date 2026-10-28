<?php

namespace Tests\Feature;

use App\Models\FollowUp;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    private function store(): Store
    {
        return Store::create(['name' => 'Test Store', 'category' => 'small_sari_sari', 'status' => 'active']);
    }

    public function test_orders_drive_the_prediction_and_no_follow_up_is_created(): void
    {
        $store = $this->store();
        $coffee = Product::create(['name' => 'Malunggay Corn Coffee', 'unit' => 'pack', 'price' => 10]);
        $order = fn (int $daysAgo, int $qty) => $this->postJson("/api/stores/{$store->id}/orders", [
            'product_id' => $coffee->id, 'delivered_at' => today()->subDays($daysAgo)->toDateString(), 'quantity' => $qty,
        ])->assertCreated();

        $order(9, 12);
        $this->assertSame('baseline', $this->getJson("/api/stores/{$store->id}")->json('predictions.0.basis'));

        // The first order gets a reminder 5 days later (already due here: 9 days ago + 5).
        $reminder = FollowUp::where('status', 'pending')->sole();
        $this->assertSame('first_order', $reminder->type);
        $this->assertSame(today()->subDays(4)->toDateString(), $reminder->due_date->toDateString());

        $order(4, 6);
        $order(0, 8);
        $p = $this->getJson("/api/stores/{$store->id}")->json('predictions.0');
        $this->assertSame('refined', $p['basis']);
        $this->assertEqualsWithDelta(2.0, $p['avg_daily_consumption'], 1e-9);
        $this->assertSame(today()->addDays(4)->toDateString(), $p['next_purchase_date']);

        // Sales and stock counts are recorded but do not change the prediction.
        $this->postJson("/api/stores/{$store->id}/sales", ['product_id' => $coffee->id, 'sale_date' => today()->toDateString(), 'quantity' => 100, 'days_covered' => 1])->assertCreated();
        $this->assertSame($p['next_purchase_date'], $this->getJson("/api/stores/{$store->id}")->json('predictions.0.next_purchase_date'));

        // The second order closed the reminder; nothing else is scheduled automatically.
        $this->assertSame(0, FollowUp::where('status', 'pending')->count());
        $this->assertSame('cancelled', $reminder->fresh()->status);
        $this->getJson('/api/predictions')->assertOk()->assertJsonPath('0.store_name', 'Test Store');
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('next_purchases.next_7_days', 1);
    }

    public function test_register_store_with_first_order(): void
    {
        $coffee = Product::create(['name' => 'Malunggay Corn Coffee', 'unit' => 'pack', 'price' => 10]);

        $id = $this->postJson('/api/stores', [
            'name' => 'Nena Store', 'category' => 'small_sari_sari', 'status' => 'active',
            'products' => [['product_id' => $coffee->id, 'packs_ordered' => 12, 'order_date' => today()->subDays(2)->toDateString()]],
        ])->assertCreated()->json('id');

        $p = $this->getJson("/api/stores/{$id}")->json('predictions.0');
        $this->assertSame('baseline', $p['basis']);
        $this->assertSame(today()->subDays(2)->toDateString(), $p['latest_order_date']);
        $this->assertSame(today()->addDays(3)->toDateString(), FollowUp::sole()->due_date->toDateString());

        // A product without packs ordered is rejected.
        $this->postJson('/api/stores', [
            'name' => 'Bad', 'category' => 'small_sari_sari', 'status' => 'active',
            'products' => [['product_id' => $coffee->id]],
        ])->assertStatus(422);
    }
    public function test_completed_first_order_reminder_is_not_recreated(): void
    {
        $store = $this->store();
        $coffee = Product::create(['name' => 'Coffee', 'unit' => 'pack', 'price' => 10]);
        $this->postJson("/api/stores/{$store->id}/orders", ['product_id' => $coffee->id, 'delivered_at' => today()->toDateString(), 'quantity' => 5])->assertCreated();

        $reminder = FollowUp::sole();
        $this->postJson("/api/follow-ups/{$reminder->id}/complete", ['outcome' => 'not_yet'])->assertOk();

        // Editing the store re-checks reminders; the handled one stays handled.
        $this->putJson("/api/stores/{$store->id}", ['name' => 'Renamed', 'category' => 'small_sari_sari', 'status' => 'active'])->assertOk();
        $this->assertSame(0, FollowUp::where('status', 'pending')->count());
    }

    public function test_complete_with_customer_response_and_next_order_date(): void
    {
        $store = $this->store();
        $followUp = $store->followUps()->create(['due_date' => today()->toDateString(), 'type' => 'manual', 'status' => 'pending']);

        $this->postJson("/api/follow-ups/{$followUp->id}/complete", [
            'outcome' => 'interested', 'next_action' => 'process_order', 'next_order_date' => today()->addDays(2)->toDateString(),
        ])->assertOk();

        $followUp->refresh();
        $this->assertSame('interested', $followUp->outcome);
        $this->assertSame(today()->addDays(2)->toDateString(), $followUp->next_order_date->toDateString());

        $this->postJson("/api/follow-ups/{$store->followUps()->create(['due_date' => today()->toDateString(), 'type' => 'manual', 'status' => 'pending'])->id}/complete", ['outcome' => 'sold'])
            ->assertStatus(422);
    }
    public function test_settings_validate_and_apply(): void
    {
        $this->putJson('/api/settings', ['frequent_max_days' => 999])->assertStatus(422);
        $this->putJson('/api/settings', ['frequent_max_days' => 5, 'recent_weighting' => true])
            ->assertOk()
            ->assertJsonPath('frequent_max_days', 5)
            ->assertJsonPath('recent_weighting', true);
    }

    public function test_performance_ranks_within_category(): void
    {
        $product = Product::create(['name' => 'Noodles', 'unit' => 'pack', 'price' => 10]);
        foreach ([['A', 'small_sari_sari', 10], ['B', 'small_sari_sari', 30], ['C', 'mini_grocery', 50]] as [$name, $cat, $qty]) {
            $s = Store::create(['name' => $name, 'category' => $cat, 'status' => 'active']);
            $s->sales()->create(['product_id' => $product->id, 'sale_date' => today(), 'quantity' => $qty, 'days_covered' => 5, 'unit_price' => 10, 'amount' => $qty * 10]);
        }

        $rows = collect($this->getJson('/api/performance?sort=packs')->assertOk()->json('rows'))->keyBy('name');
        $this->assertSame(1, $rows['B']['category_rank']);
        $this->assertSame(2, $rows['A']['category_rank']);
        $this->assertSame(1, $rows['C']['category_rank']);
        $this->assertEquals(6, $rows['B']['avg_daily_sales']);

        $this->getJson('/api/performance?category=mini_grocery')->assertJsonCount(1, 'rows');
    }
}
