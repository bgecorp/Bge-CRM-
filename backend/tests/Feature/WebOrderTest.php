<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebOrderTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $overrides = []): array
    {
        return $overrides + [
            'customer_name' => 'Maria Santos', 'phone' => '0917 123 4567',
            'address' => 'Purok 3, Brgy. Kauswagan', 'city' => 'Cagayan de Oro',
            'items' => [['offer' => 'barley-powder', 'quantity' => 2], ['offer' => 'barley-capsules', 'quantity' => 1]],
        ];
    }

    public function test_public_can_see_offers_and_order_without_signing_in(): void
    {
        $this->getJson('/api/offers')->assertOk()->assertJsonPath('0.key', 'barley-powder')->assertJsonPath('0.price', 680)->assertJsonPath('0.packs', 10)->assertJsonPath('1.price', 1360);

        // A visitor cannot set their own price; it comes from the offer list.
        $this->postJson('/api/web-orders', $this->order(['total' => 1]))
            ->assertCreated()
            ->assertJsonPath('reference', 'BGE-00001')
            ->assertJsonPath('total', '1360.00')
            ->assertJsonPath('price_to_confirm', true);

        $order = WebOrder::sole();
        $this->assertSame('new', $order->status);
        $this->assertSame(1360, $order->items[0]['subtotal']);
        $this->assertNull($order->items[1]['subtotal']);
    }

    public function test_order_validation_and_spam_trap(): void
    {
        $this->postJson('/api/web-orders', $this->order(['phone' => 'call me']))->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->postJson('/api/web-orders', $this->order(['items' => [['offer' => 'free-car', 'quantity' => 1]]]))->assertStatus(422);
        $this->postJson('/api/web-orders', $this->order(['items' => []]))->assertStatus(422);

        // Bots that fill the hidden field get a normal-looking reply but no order.
        $this->postJson('/api/web-orders', $this->order(['website' => 'spam.example']))->assertCreated();
        $this->assertSame(0, WebOrder::count());
    }

    public function test_only_super_admin_sees_and_updates_web_orders(): void
    {
        $this->postJson('/api/web-orders', $this->order())->assertCreated();
        $id = WebOrder::sole()->id;

        $this->getJson('/api/web-orders')->assertStatus(401);
        $this->signIn(User::REPRESENTATIVE);
        $this->getJson('/api/web-orders')->assertForbidden();

        $admin = $this->signIn();
        $this->getJson('/api/web-orders')->assertOk()->assertJsonPath('counts.new', 1)->assertJsonMissingPath('orders.0.ip_address');
        $this->putJson("/api/web-orders/{$id}", ['status' => 'confirmed', 'admin_notes' => 'Called, ships Friday'])
            ->assertOk()->assertJsonPath('status', 'confirmed')->assertJsonPath('handler.id', $admin->id);
        $this->putJson("/api/web-orders/{$id}", ['status' => 'lost'])->assertStatus(422);
    }
}
