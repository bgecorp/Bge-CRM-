<?php

namespace App\Services\Assistant;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use RuntimeException;

/**
 * Thin wrapper over the Anthropic PHP SDK's Messages API. Requests and responses
 * use the API's own block format (snake_case arrays) so history can be stored as
 * JSON and sent back exactly as it was received.
 */
class ClaudeClient
{
    private Client $client;

    public function __construct(string $apiKey)
    {
        $this->client = new Client(apiKey: $apiKey);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages  stored history, API block format
     * @param  array<int, array<string, mixed>>  $tools
     * @return array{content: array<int, array<string, mixed>>, stop_reason: string|null}
     */
    public function send(string $system, array $tools, array $messages): array
    {
        try {
            $response = $this->client->beta->messages->create(
                model: config('assistant.model'),
                maxTokens: config('assistant.max_tokens'),
                system: [['type' => 'text', 'text' => $system]],
                tools: $tools,
                messages: $messages,
                outputConfig: ['effort' => config('assistant.effort')],
                // Cache the system prompt, tools and history between steps.
                cacheControl: ['type' => 'ephemeral'],
                // If the model declines for policy reasons, the API retries on its default fallback model.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException) {
            throw new RuntimeException('The Claude API key was refused. Check ANTHROPIC_API_KEY in backend/.env.');
        } catch (RateLimitException) {
            throw new RuntimeException('Claude is busy or your usage limit was reached. Wait a minute and try again.');
        } catch (APIStatusException $e) {
            throw new RuntimeException('Claude API error: '.$e->getMessage());
        } catch (APIConnectionException) {
            throw new RuntimeException('Could not reach the Claude API. Check the internet connection.');
        }

        return [
            // Every block (thinking, text, tool calls, fallback markers) is kept exactly as returned.
            'content' => json_decode(json_encode($response->content), true),
            'stop_reason' => $response->stopReason,
        ];
    }
}
