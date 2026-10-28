<?php

namespace Tests\Feature;

use App\Models\LoginLog;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_everything_needs_sign_in_except_login_and_pictures(): void
    {
        $this->getJson('/api/stores')->assertStatus(401);
        $this->getJson('/api/products')->assertStatus(401);
        $this->getJson('/api/dashboard')->assertStatus(401);
        $this->getJson('/api/assistant/status')->assertStatus(401);
    }

    public function test_login_logout_and_the_log(): void
    {
        $user = User::factory()->create(['email' => 'rep@example.test', 'password' => 'secret-pass-1', 'role' => User::REPRESENTATIVE]);

        $this->postJson('/api/login', ['email' => 'rep@example.test', 'password' => 'wrong-pass'])->assertStatus(422);

        $res = $this->postJson('/api/login', ['email' => 'rep@example.test', 'password' => 'secret-pass-1'])
            ->assertOk()
            ->assertJsonPath('user.role', 'representative');
        $token = $res->json('token');
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->getJson('/api/me', ['Authorization' => "Bearer {$token}"])->assertOk()->assertJsonPath('email', 'rep@example.test');
        $this->postJson('/api/logout', [], ['Authorization' => "Bearer {$token}"])->assertNoContent();
        $this->assertSame(0, $user->tokens()->count());

        $this->assertSame(['failed', 'login', 'logout'], LoginLog::orderBy('id')->pluck('event')->all());
        $this->assertSame($user->id, LoginLog::where('event', 'login')->value('user_id'));
    }

    public function test_turned_off_account_cannot_sign_in(): void
    {
        User::factory()->create(['email' => 'off@example.test', 'password' => 'secret-pass-1', 'active' => false]);

        $this->postJson('/api/login', ['email' => 'off@example.test', 'password' => 'secret-pass-1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_representative_permissions(): void
    {
        $this->signIn(User::REPRESENTATIVE);
        $store = Store::create(['name' => 'Nena', 'category' => 'small_sari_sari', 'status' => 'active']);
        $product = Product::create(['name' => 'Coffee', 'unit' => 'pack', 'price' => 10]);

        // Allowed: view products, stock prediction, follow-ups, record orders.
        $this->getJson('/api/products')->assertOk();
        $this->getJson('/api/predictions')->assertOk();
        $this->getJson('/api/follow-ups')->assertOk();
        $this->getJson("/api/stores/{$store->id}")->assertOk();
        $this->postJson("/api/stores/{$store->id}/orders", ['product_id' => $product->id, 'delivered_at' => today()->toDateString(), 'quantity' => 5])->assertCreated();
        $this->postJson('/api/follow-ups', ['store_id' => $store->id, 'due_date' => today()->toDateString()])->assertCreated();

        // Not allowed: change products, customers, settings, accounts; dashboard; login log; assistant.
        $this->postJson('/api/products', ['name' => 'X', 'unit' => 'pack', 'price' => 1])->assertForbidden();
        $this->deleteJson("/api/products/{$product->id}")->assertForbidden();
        $this->postJson('/api/stores', ['name' => 'X', 'category' => 'small_sari_sari', 'status' => 'active'])->assertForbidden();
        $this->deleteJson("/api/stores/{$store->id}")->assertForbidden();
        $this->getJson('/api/dashboard')->assertForbidden();
        $this->putJson('/api/settings', ['frequent_max_days' => 3])->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/login-logs')->assertForbidden();
        $this->getJson('/api/assistant/status')->assertForbidden();
    }

    public function test_super_admin_manages_accounts(): void
    {
        $admin = $this->signIn();
        $rep = \App\Models\SalesRep::create(['name' => 'Juan']);

        $id = $this->postJson('/api/users', [
            'name' => 'Juan', 'email' => 'juan@example.test', 'password' => 'short', 'role' => 'representative',
        ])->assertStatus(422)->json('id');
        $this->assertNull($id);

        $id = $this->postJson('/api/users', [
            'name' => 'Juan', 'email' => 'juan@example.test', 'password' => 'long-enough-1', 'role' => 'representative',
        ])->assertCreated()->assertJsonPath('sales_rep.id', $rep->id)->assertJsonMissingPath('password')->json('id');

        $this->putJson("/api/users/{$id}", ['name' => 'Juan D', 'email' => 'juan@example.test', 'role' => 'representative', 'active' => false])
            ->assertOk()->assertJsonPath('active', false);
        $this->getJson('/api/users')->assertOk()->assertJsonCount(2);

        // A new seller name is added to the sales team automatically.
        $this->postJson('/api/users', ['name' => 'Maria', 'email' => 'maria@example.test', 'password' => 'long-enough-1', 'role' => 'representative'])
            ->assertCreated()->assertJsonPath('sales_rep.name', 'Maria');
        $this->getJson('/api/login-logs')->assertOk();

        // The super admin cannot lock themself out.
        $this->putJson("/api/users/{$admin->id}", ['name' => 'A', 'email' => $admin->email, 'role' => 'representative'])->assertStatus(422);
        $this->deleteJson("/api/users/{$admin->id}")->assertStatus(422);
        $this->deleteJson("/api/users/{$id}")->assertNoContent();
    }
}
