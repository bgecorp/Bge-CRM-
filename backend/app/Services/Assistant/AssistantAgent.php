<?php

namespace App\Services\Assistant;

use App\Models\AssistantConversation;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Runs the tool-use loop for one conversation. Read-only tools run straight
 * away; when the model asks for a change, the loop stops and stores the pending
 * actions until the user approves or rejects them (resolve()).
 *
 * History is stored in the Claude API's own block format and only ever appended
 * to: every block Claude returns (thinking, text, tool calls) goes back unchanged,
 * which keeps its reasoning valid and the prompt cache warm between steps.
 */
class AssistantAgent
{
    public function __construct(private AssistantTools $tools, private SystemContext $context) {}

    /** @return string|null a notice for the user, if the run ended unusually */
    public function send(AssistantConversation $conversation, string $text): ?string
    {
        if ($conversation->pending) {
            throw new RuntimeException('Approve or reject the pending changes first.');
        }

        $messages = $conversation->messages ?? [];
        $messages[] = ['role' => 'user', 'content' => $text];

        if ($conversation->title === 'New conversation') {
            $conversation->title = Str::limit(preg_replace('/\s+/', ' ', $text), 60);
        }

        return $this->loop($conversation, $messages);
    }

    /**
     * Apply the user's decisions on pending actions and continue the run.
     *
     * @param  array<string, array{approve: bool, reason?: string}>  $decisions  keyed by tool call id
     */
    public function resolve(AssistantConversation $conversation, array $decisions): ?string
    {
        $pending = $conversation->pending;
        if (! $pending) {
            throw new RuntimeException('Nothing is waiting for approval.');
        }

        $results = $pending['results'];
        foreach ($pending['actions'] as $action) {
            $decision = $decisions[$action['id']] ?? null;
            if ($decision === null) {
                throw new RuntimeException('Every pending change needs a decision.');
            }
            if (! empty($decision['approve'])) {
                $results[$action['id']] = $this->tools->run($action['name'], $action['input'], $conversation->id);
            } else {
                $reason = trim((string) ($decision['reason'] ?? ''));
                $this->tools->logRejection($conversation->id, $action['name'], $action['input'], $reason);
                $results[$action['id']] = ['The user rejected this change.'.($reason !== '' ? " Their reason: {$reason}" : ''), true];
            }
        }

        $messages = $conversation->messages;
        $messages[] = ['role' => 'user', 'content' => $this->toolResultBlocks($pending['order'], $results)];
        $conversation->pending = null;

        return $this->loop($conversation, $messages);
    }

    private function loop(AssistantConversation $conversation, array $messages): ?string
    {
        $client = $this->client();
        $system = $this->context->text();
        $tools = $this->toolsForApi();

        try {
            for ($step = 0; $step < config('assistant.max_steps'); $step++) {
                $response = $client->send($system, $tools, $this->forApi($messages));

                $content = $response['content'];
                $messages[] = ['role' => 'assistant', 'content' => $content];
                $calls = array_values(array_filter($content, fn ($b) => ($b['type'] ?? '') === 'tool_use'));

                if ($response['stop_reason'] !== 'tool_use' || ! $calls) {
                    $this->save($conversation, $messages);

                    return match ($response['stop_reason']) {
                        'max_tokens' => 'The reply was cut off because it hit the length limit. Ask it to continue.',
                        'refusal' => 'Claude declined to answer this request.',
                        default => null,
                    };
                }

                $order = [];
                $results = [];
                $actions = [];
                foreach ($calls as $block) {
                    $order[] = $block['id'];
                    $input = is_array($block['input'] ?? null) ? $block['input'] : [];
                    if ($this->tools->requiresApproval($block['name'])) {
                        $actions[] = [
                            'id' => $block['id'],
                            'name' => $block['name'],
                            'input' => $input,
                            'preview' => $this->tools->preview($block['name'], $input),
                        ];
                    } else {
                        $results[$block['id']] = $this->tools->run($block['name'], $input, $conversation->id);
                    }
                }

                if ($actions) {
                    $conversation->pending = ['order' => $order, 'results' => $results, 'actions' => $actions];
                    $this->save($conversation, $messages);

                    return null;
                }

                // All results for one turn go back together in a single user message.
                $messages[] = ['role' => 'user', 'content' => $this->toolResultBlocks($order, $results)];
            }

            $this->save($conversation, $messages);

            return 'Paused after '.config('assistant.max_steps').' steps. Reply "continue" to let it keep going.';
        } catch (RuntimeException $e) {
            $this->save($conversation, $messages);
            throw $e;
        }
    }

    private function client(): object
    {
        // Tests bind a fake client here.
        if (app()->bound('assistant.client')) {
            return app('assistant.client');
        }

        $key = config('assistant.api_key');
        if (! $key) {
            throw new RuntimeException('The Claude API key is not set. Put ANTHROPIC_API_KEY=... in backend/.env, then restart "php artisan serve".');
        }

        return new ClaudeClient($key);
    }

    /** @param  array<string, array{0: string, 1: bool}>  $results */
    private function toolResultBlocks(array $order, array $results): array
    {
        return array_map(function (string $id) use ($results) {
            [$content, $isError] = $results[$id] ?? ['No result.', true];

            return ['type' => 'tool_result', 'tool_use_id' => $id, 'content' => $content, 'is_error' => $isError];
        }, $order);
    }

    private function toolsForApi(): array
    {
        return array_map(fn ($tool) => [
            'name' => $tool['name'],
            'description' => $tool['description'],
            // A tool with no inputs still needs "properties" to be a JSON object, not [].
            'inputSchema' => array_merge($tool['input_schema'], ['properties' => ($tool['input_schema']['properties'] ?? []) ?: new \stdClass]),
        ], $this->tools->definitions());
    }

    /**
     * Stored history as the API expects it. Blocks are sent exactly as stored; the only
     * fixes are JSON shapes PHP loses (an empty tool input decodes to [] instead of {}),
     * and skipping empty assistant turns left by older conversations.
     */
    private function forApi(array $messages): array
    {
        $out = [];
        foreach ($messages as $message) {
            $content = $message['content'];
            if (is_array($content)) {
                if ($content === []) {
                    continue;
                }
                $content = array_map(function (array $block) {
                    if (($block['type'] ?? '') === 'tool_use') {
                        unset($block['invalid_input']);
                        $block['input'] = ($block['input'] ?? []) ?: new \stdClass;
                    }

                    return $block;
                }, $content);
            }
            $out[] = ['role' => $message['role'], 'content' => $content];
        }

        return $out;
    }

    private function save(AssistantConversation $conversation, array $messages): void
    {
        $conversation->messages = $messages;
        $conversation->save();
    }
}
