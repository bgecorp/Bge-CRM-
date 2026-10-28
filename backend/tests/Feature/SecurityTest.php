<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\WebOrder;
use App\Services\Assistant\AssistantTools;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /** Classic SQL injection attempts; each must be treated as plain text. */
    private const PAYLOADS = [
        "' OR '1'='1",
        "' OR 1=1 -- ",
        "admin'--",
        "'; DROP TABLE users; --",
        "1; DELETE FROM stores",
        "' UNION SELECT email, password FROM users -- ",
        '" OR ""="',
    ];

    private function assertTablesIntact(): void
    {
        foreach (['users', 'stores', 'web_orders', 'orders'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} is missing");
        }
    }

    public function test_login_cannot_be_bypassed_with_sql_injection(): void
    {
        User::factory()->create(['email' => 'admin@example.com', 'role' => User::SUPER_ADMIN]);

        // Repeated guesses are cut off after 10 a minute.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertStatus(422);
        }
        $this->postJson('/api/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertStatus(429);

        // With the limit out of the way, every injection attempt is still just a wrong sign-in.
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach (self::PAYLOADS as $payload) {
            $this->postJson('/api/login', ['email' => $payload, 'password' => $payload])->assertStatus(422);
            $this->postJson('/api/login', ['email' => 'admin@example.com', 'password' => $payload])->assertStatus(422);
        }

        $this->assertSame(1, User::count());
        $this->assertTablesIntact();
    }

    public function test_order_form_stores_injection_text_as_plain_text(): void
    {
        foreach (array_slice(self::PAYLOADS, 0, 4) as $i => $payload) {
            $this->postJson('/api/web-orders', [
                'customer_name' => $payload, 'phone' => '0917 123 456'.$i,
                'address' => $payload, 'city' => $payload, 'notes' => $payload,
                'items' => [['offer' => 'barley-powder', 'quantity' => 1]],
            ])->assertCreated();
        }

        // A product key or quantity that is not on the list is refused, never run.
        $this->postJson('/api/web-orders', [
            'customer_name' => 'Test', 'phone' => '0917 123 4567', 'address' => 'A', 'city' => 'B',
            'items' => [['offer' => "barley-powder' OR '1'='1", 'quantity' => '1 OR 1=1']],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.0.offer', 'items.0.quantity']);

        $this->assertSame(4, WebOrder::count());
        $this->assertSame("'; DROP TABLE users; --", WebOrder::orderBy('id')->get()[3]->customer_name);
        $this->assertTablesIntact();
    }

    public function test_search_filters_and_sorting_ignore_injection(): void
    {
        $this->signIn();
        Store::create(['name' => 'Aling Nena Store', 'category' => 'small_sari_sari', 'status' => 'active']);

        foreach (self::PAYLOADS as $payload) {
            $this->getJson('/api/stores?search='.urlencode($payload))->assertOk()->assertJsonCount(0);
            $res = $this->getJson('/api/predictions?sort='.urlencode($payload).'&pattern='.urlencode($payload).'&product_id='.urlencode($payload));
            $this->assertContains($res->status(), [200, 422]);
            $this->getJson('/api/performance?sort='.urlencode($payload))->assertStatus(422);
            $this->getJson('/api/stores/'.urlencode($payload))->assertNotFound();
        }

        // A normal search still works.
        $this->getJson('/api/stores?search=Nena')->assertOk()->assertJsonCount(1);
        $this->assertSame(1, Store::count());
        $this->assertTablesIntact();
    }

    public function test_assistant_cannot_read_passwords_or_change_data(): void
    {
        $this->signIn();
        $tools = app(AssistantTools::class);

        foreach ([
            'SELECT email, password FROM users',
            'SELECT token FROM personal_access_tokens',
            'SELECT name FROM stores UNION SELECT password FROM users',
            'SELECT * FROM information_schema.tables',
            'SELECT name FROM stores; DROP TABLE stores',
            'DELETE FROM stores',
        ] as $sql) {
            [, $err] = $tools->run('query_database', ['sql' => $sql]);
            $this->assertTrue($err, "Should have been refused: {$sql}");
        }

        $this->assertTablesIntact();
    }

    public function test_pages_need_the_right_account(): void
    {
        $this->getJson('/api/stores')->assertUnauthorized();
        $this->getJson('/api/web-orders')->assertUnauthorized();
        $this->getJson('/api/users')->assertUnauthorized();

        $this->signIn(User::REPRESENTATIVE);
        $this->getJson('/api/web-orders')->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/login-logs')->assertForbidden();
        $this->postJson('/api/products', ['name' => 'X'])->assertForbidden();
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->getJson('/api/offers')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
