<?php

namespace App\Http\Controllers;

use App\Models\AssistantChange;
use App\Models\AssistantConversation;
use App\Models\AssistantNote;
use App\Services\Assistant\AssistantAgent;
use Illuminate\Http\Request;
use RuntimeException;

class AssistantController extends Controller
{
    public function __construct(private AssistantAgent $agent) {}

    public function status()
    {
        return [
            'configured' => (bool) config('assistant.api_key'),
            'code_edits' => (bool) config('assistant.allow_code_edits'),
            'model' => config('assistant.model'),
            'locked' => (string) config('assistant.token') !== '',
        ];
    }

    public function index()
    {
        return AssistantConversation::orderByDesc('updated_at')->limit(50)->get(['id', 'title', 'updated_at']);
    }

    public function store()
    {
        $conversation = AssistantConversation::create(['messages' => []]);

        return response()->json($this->present($conversation), 201);
    }

    public function show(AssistantConversation $conversation)
    {
        return $this->present($conversation);
    }

    public function destroy(AssistantConversation $conversation)
    {
        $conversation->delete();

        return response()->noContent();
    }

    public function message(Request $request, AssistantConversation $conversation)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:20000']]);

        return $this->run($conversation, fn () => $this->agent->send($conversation, $data['message']));
    }

    public function resolve(Request $request, AssistantConversation $conversation)
    {
        $data = $request->validate([
            'decisions' => ['required', 'array'],
            'decisions.*.approve' => ['required', 'boolean'],
            'decisions.*.reason' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->run($conversation, fn () => $this->agent->resolve($conversation, $data['decisions']));
    }

    public function notes()
    {
        return AssistantNote::orderByDesc('id')->get(['id', 'note', 'created_at']);
    }

    public function storeNote(Request $request)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);

        return response()->json(AssistantNote::create($data), 201);
    }

    public function destroyNote(AssistantNote $note)
    {
        $note->delete();

        return response()->noContent();
    }

    public function changes()
    {
        return AssistantChange::orderByDesc('id')->limit(200)->get();
    }

    private function run(AssistantConversation $conversation, callable $action)
    {
        // A single request can involve several model calls.
        set_time_limit(600);

        try {
            $notice = $action();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()] + $this->present($conversation->fresh()), 422);
        }

        return $this->present($conversation->fresh()) + ['notice' => $notice];
    }

    /** Conversation in a shape the chat UI can render directly. */
    private function present(AssistantConversation $conversation): array
    {
        $items = [];
        $toolIndex = [];

        foreach ($conversation->messages ?? [] as $message) {
            $content = $message['content'];

            if ($message['role'] === 'user') {
                if (is_string($content)) {
                    $items[] = ['type' => 'user', 'text' => $content];

                    continue;
                }
                foreach ($content as $block) {
                    if ($block['type'] === 'text') {
                        $items[] = ['type' => 'user', 'text' => $block['text']];
                    } elseif ($block['type'] === 'tool_result' && isset($toolIndex[$block['tool_use_id']])) {
                        $i = $toolIndex[$block['tool_use_id']];
                        $items[$i]['status'] = ! empty($block['is_error']) ? 'error' : 'done';
                        $items[$i]['result'] = mb_substr(is_string($block['content']) ? $block['content'] : json_encode($block['content']), 0, 4000);
                    }
                }

                continue;
            }

            foreach ($content as $block) {
                if ($block['type'] === 'text' && trim($block['text']) !== '') {
                    $items[] = ['type' => 'assistant', 'text' => $block['text']];
                } elseif ($block['type'] === 'tool_use') {
                    $toolIndex[$block['id']] = count($items);
                    $items[] = ['type' => 'tool', 'id' => $block['id'], 'name' => $block['name'], 'input' => $block['input'] ?? [], 'status' => 'pending'];
                }
            }
        }

        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'items' => $items,
            'pending' => array_map(
                fn ($a) => ['id' => $a['id'], 'name' => $a['name'], 'preview' => $a['preview']],
                $conversation->pending['actions'] ?? []
            ),
        ];
    }
}
