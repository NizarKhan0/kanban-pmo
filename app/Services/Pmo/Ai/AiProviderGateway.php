<?php

declare(strict_types=1);

namespace App\Services\Pmo\Ai;

use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client as AnthropicClient;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException as AnthropicAuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The only class that talks to a live AI provider. Reads everything from
 * config('pmo.ai') (i.e. .env) and never hard-codes credentials.
 *
 *  - claude  → official Anthropic PHP SDK (anthropic-ai/sdk), Messages API
 *  - openai  → OpenAI chat-completions endpoint (Laravel Http)
 *  - custom  → any OpenAI-compatible chat-completions endpoint (Laravel Http)
 */
class AiProviderGateway
{
    /** @var list<string> */
    private const EFFORTS = ['low', 'medium', 'high', 'xhigh', 'max'];

    public function provider(): string
    {
        $provider = (string) config('pmo.ai.provider', 'claude');

        return in_array($provider, ['claude', 'openai', 'custom'], true) ? $provider : 'claude';
    }

    /** automation_logs channel for the active provider. */
    public function channel(): string
    {
        return 'ai_'.$this->provider();
    }

    public function model(): string
    {
        return (string) config('pmo.ai.model', 'claude-sonnet-5-5');
    }

    /**
     * Live calls are possible only when credentials are present in .env.
     */
    public function isConfigured(): bool
    {
        return match ($this->provider()) {
            'custom' => filled(config('pmo.ai.custom_endpoint')),
            default => filled(config('pmo.ai.api_key')),
        };
    }

    /**
     * Run one prompt. $schema (JSON Schema) requests structured JSON output.
     *
     * @param  array<string, mixed>|null  $schema
     *
     * @throws AiProviderException
     */
    public function complete(string $system, string $prompt, ?array $schema = null): AiCompletion
    {
        $timeout = max(30, (int) config('pmo.ai.timeout', 120));
        @set_time_limit($timeout * 3 + 30); // SDK retries up to twice

        return match ($this->provider()) {
            'claude' => $this->claude($system, $prompt, $schema, $timeout),
            default => $this->openAiCompatible($system, $prompt, $schema !== null, $timeout),
        };
    }

    /**
     * @param  array<string, mixed>|null  $schema
     */
    private function claude(string $system, string $prompt, ?array $schema, int $timeout): AiCompletion
    {
        $client = new AnthropicClient(
            apiKey: (string) config('pmo.ai.api_key'),
            requestOptions: ['timeout' => (float) $timeout, 'maxRetries' => 2],
        );

        $effort = (string) config('pmo.ai.effort', 'medium');
        $outputConfig = array_filter([
            'effort' => in_array($effort, self::EFFORTS, true) ? $effort : null,
            'format' => $schema === null ? null : ['type' => 'json_schema', 'schema' => $schema],
        ]);

        try {
            $message = $client->beta->messages->create(
                maxTokens: max(1024, (int) config('pmo.ai.max_tokens', 16000)),
                messages: [['role' => 'user', 'content' => $prompt]],
                model: $this->model(),
                // On a policy decline, the API retries on the model's default fallback;
                // on models without fallback support the request simply runs as normal.
                fallbacks: 'default',
                outputConfig: $outputConfig === [] ? null : $outputConfig,
                system: $system,
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AnthropicAuthenticationException) {
            throw new AiProviderException('The Claude API rejected the API key. Check PMO_AI_API_KEY in your .env file.');
        } catch (RateLimitException) {
            throw new AiProviderException('The Claude API rate limit was reached. Please try again in a moment.');
        } catch (APIStatusException $e) {
            throw new AiProviderException('The Claude API returned an error: '.$e->getMessage());
        } catch (APIConnectionException) {
            throw new AiProviderException('Could not reach the Claude API. Check your internet connection and try again.');
        } catch (AnthropicException $e) {
            throw new AiProviderException('The Claude request failed: '.$e->getMessage());
        }

        if ($message->stopReason === 'refusal') {
            throw new AiProviderException('Claude declined this request'.($message->stopDetails?->explanation ? ': '.$message->stopDetails->explanation : '.'));
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block instanceof BetaTextBlock) {
                $text .= $block->text;
            }
        }

        if (trim($text) === '') {
            throw new AiProviderException('Claude returned an empty response'.($message->stopReason === 'max_tokens' ? ' (output limit reached).' : '.'));
        }

        return new AiCompletion(
            text: $text,
            tokens: $message->usage->inputTokens + $message->usage->outputTokens,
            model: $message->model,
        );
    }

    private function openAiCompatible(string $system, string $prompt, bool $json, int $timeout): AiCompletion
    {
        $custom = $this->provider() === 'custom';
        $url = $custom ? (string) config('pmo.ai.custom_endpoint') : 'https://api.openai.com/v1/chat/completions';
        $key = (string) config('pmo.ai.api_key');

        $payload = [
            'model' => $this->model(),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];
        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $request = Http::timeout($timeout)->acceptJson();
        if ($key !== '') {
            $request = $request->withToken($key);
        }

        try {
            $response = $request->post($url, $payload);
        } catch (ConnectionException) {
            throw new AiProviderException('Could not reach the AI endpoint. Check the provider URL and your connection.');
        }

        if ($response->failed()) {
            $detail = (string) ($response->json('error.message') ?? $response->reason());

            throw new AiProviderException("The AI provider returned HTTP {$response->status()}: {$detail}");
        }

        $text = (string) $response->json('choices.0.message.content', '');
        if (trim($text) === '') {
            throw new AiProviderException('The AI provider returned an empty response.');
        }

        return new AiCompletion(
            text: $text,
            tokens: (int) $response->json('usage.total_tokens', 0),
            model: (string) $response->json('model', $this->model()),
        );
    }
}
