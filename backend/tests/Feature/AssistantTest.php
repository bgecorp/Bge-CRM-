<?php

namespace Tests\Feature;

use App\Models\AssistantChange;
use App\Models\Store;
use App\Services\Assistant\AssistantTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-token';

    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
        config(['assistant.token' => self::TOKEN, 'assistant.allow_code_edits' => true]);
    }

    private function headers(): array
    {
        return ['X-Assistant-Token' => self::TOKEN, 'Accept' => 'application/json'];
    }

    public function test_requires_access_code(): void
    {
        $this->getJson('/api/assistant/status')->assertStatus(401);
        $this->getJson('/api/assistant/status', ['X-Assistant-Token' => 'wrong'])->assertStatus(401);
        $this->getJson('/api/assistant/status', $this->headers())->assertOk()->assertJsonPath('code_edits', true);
    }

    public function test_open_without_access_code_when_none_is_set(): void
    {
        config(['assistant.token' => '']);
        $this->getJson('/api/assistant/status')->assertOk()->assertJsonPath('locked', false);
    }

    public function test_read_tools(): void
    {
        $tools = app(AssistantTools::class);
        Store::create(['name' => 'Tindahan ni Aling Nena', 'category' => 'small_sari_sari', 'status' => 'active']);

        [$out, $err] = $tools->run('api_get', ['path' => '/stores']);
        $this->assertFalse($err, $out);
        $this->assertStringContainsString('Aling Nena', $out);

        [$out, $err] = $tools->run('query_database', ['sql' => 'SELECT name FROM stores']);
        $this->assertFalse($err);
        $this->assertStringContainsString('Aling Nena', $out);

        [$out, $err] = $tools->run('read_file', ['path' => 'backend/app/Services/StockPredictor.php', 'start_line' => 1, 'end_line' => 5]);
        $this->assertFalse($err);
        $this->assertStringContainsString('namespace App\Services;', $out);

        [$out, $err] = $tools->run('search_code', ['pattern' => 'nextPurchaseOrder', 'path' => 'backend/app']);
        $this->assertFalse($err);
        $this->assertStringContainsString('StockPredictor.php', $out);
    }

    public function test_sql_must_be_read_only(): void
    {
        $tools = app(AssistantTools::class);
        foreach (['DELETE FROM stores', 'SELECT 1; DROP TABLE stores', 'UPDATE stores SET name = 1'] as $sql) {
            [, $err] = $tools->run('query_database', ['sql' => $sql]);
            $this->assertTrue($err, $sql);
        }
    }

    public function test_file_access_is_fenced(): void
    {
        $tools = app(AssistantTools::class);

        foreach (['backend/.env', 'backend/vendor/autoload.php', '../outside.txt', 'C:/Windows/win.ini', 'backend/app/../.env'] as $path) {
            [, $err] = $tools->run('read_file', ['path' => $path]);
            $this->assertTrue($err, "read {$path}");
        }

        $edit = fn ($path) => $tools->preview('edit_file', ['path' => $path, 'old_string' => 'x', 'new_string' => 'y', 'summary' => 's']);
        $this->assertSame('error', $edit('backend/app/Http/Middleware/EnsureAssistantToken.php')['kind']);
        $this->assertSame('error', $edit('backend/config/assistant.php')['kind']);
        $this->assertSame('error', $edit('backend/composer.json')['kind']);

        config(['assistant.allow_code_edits' => false]);
        $this->assertSame('error', $edit('backend/app/Models/Store.php')['kind']);
    }

    public function test_cannot_call_its_own_endpoints(): void
    {
        [, $err] = app(AssistantTools::class)->run('api_get', ['path' => '/assistant/notes']);
        $this->assertTrue($err);
    }

    public function test_loop_runs_reads_and_waits_for_approval_on_writes(): void
    {
        $fake = new FakeClaude([
            ['tool_calls', [['id' => 'tu_1', 'name' => 'api_get', 'input' => ['path' => '/stores']]]],
            ['tool_calls', [['id' => 'tu_2', 'name' => 'api_write', 'input' => [
                'method' => 'POST', 'path' => '/stores', 'summary' => 'Register Nena Store',
                'body' => ['name' => 'Nena Store', 'category' => 'small_sari_sari', 'status' => 'active'],
            ]]]],
            ['stop', 'Registered Nena Store.'],
        ]);
        $this->app->instance('assistant.client', $fake);

        $id = $this->postJson('/api/assistant/conversations', [], $this->headers())->json('id');

        $res = $this->postJson("/api/assistant/conversations/{$id}/messages", ['message' => 'Add Nena Store'], $this->headers())
            ->assertOk()
            ->assertJsonPath('pending.0.id', 'tu_2')
            ->assertJsonPath('pending.0.preview.title', 'Register Nena Store');
        $this->assertSame(0, Store::count(), 'nothing is written before approval');
        $this->assertSame('done', collect($res->json('items'))->firstWhere('id', 'tu_1')['status']);

        // The read result went back as a tool_result for tu_1, right after the call,
        // and the thinking block came back exactly as Claude sent it.
        $second = $fake->requests[1]['messages'];
        $this->assertNotSame('', $fake->requests[1]['system']);
        $this->assertSame('tu_1', end($second)['content'][0]['tool_use_id']);
        $call = prev($second)['content'];
        $this->assertSame(['type' => 'thinking', 'thinking' => '', 'signature' => 'sig-1'], $call[0]);
        $this->assertSame('tu_1', $call[1]['id']);
        $this->assertContains('api_get', array_column($fake->requests[0]['tools'], 'name'));

        $this->postJson("/api/assistant/conversations/{$id}/resolve", ['decisions' => ['tu_2' => ['approve' => true]]], $this->headers())
            ->assertOk()
            ->assertJsonPath('pending', [])
            ->assertJsonFragment(['text' => 'Registered Nena Store.']);

        $this->assertSame(1, Store::where('name', 'Nena Store')->count());
        $this->assertSame('applied', AssistantChange::first()->status);

        // History is append-only: each request starts with the previous one's messages unchanged.
        $this->assertSame($fake->requests[1]['messages'], array_slice($fake->requests[2]['messages'], 0, count($fake->requests[1]['messages'])));
    }

    public function test_rejected_change_is_not_applied_and_reason_reaches_the_model(): void
    {
        $fake = new FakeClaude([
            ['tool_calls', [['id' => 'tu_9', 'name' => 'api_write', 'input' => [
                'method' => 'DELETE', 'path' => '/stores/1', 'summary' => 'Delete store',
            ]]]],
            ['stop', 'Okay, I will leave it.'],
        ]);
        $this->app->instance('assistant.client', $fake);
        Store::create(['name' => 'Keep Me', 'category' => 'mini_grocery', 'status' => 'active']);

        $id = $this->postJson('/api/assistant/conversations', [], $this->headers())->json('id');
        $this->postJson("/api/assistant/conversations/{$id}/messages", ['message' => 'delete it'], $this->headers())->assertOk();
        $this->postJson("/api/assistant/conversations/{$id}/resolve", ['decisions' => ['tu_9' => ['approve' => false, 'reason' => 'still a customer']]], $this->headers())->assertOk();

        $this->assertSame(1, Store::count());
        $last = end($fake->requests[1]['messages'])['content'][0];
        $this->assertSame('tool_result', $last['type']);
        $this->assertTrue($last['is_error']);
        $this->assertStringContainsString('still a customer', $last['content']);
    }
}

/** Scripted stand-in for the Claude client: returns canned API-format responses and records requests. */
class FakeClaude
{
    public array $requests = [];

    public function __construct(private array $script) {}

    public function send(string $system, array $tools, array $messages): array
    {
        $this->requests[] = json_decode(json_encode(['system' => $system, 'tools' => $tools, 'messages' => $messages]), true);
        [$kind, $data] = array_shift($this->script);

        if ($kind === 'tool_calls') {
            // Real responses start with a thinking block that must go back unchanged.
            $content = [['type' => 'thinking', 'thinking' => '', 'signature' => 'sig-'.count($this->requests)]];
            foreach ($data as $t) {
                $content[] = ['type' => 'tool_use', 'id' => $t['id'], 'name' => $t['name'], 'input' => $t['input']];
            }

            return ['content' => $content, 'stop_reason' => 'tool_use'];
        }

        return ['content' => [['type' => 'text', 'text' => $data]], 'stop_reason' => 'end_turn'];
    }
}
