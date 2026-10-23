<?php

declare(strict_types=1);

namespace App\Providers\Llm\Adapters;

use App\Models\ProviderCredential;
use App\Providers\Llm\Contracts\ChatRequest;
use App\Providers\Llm\Contracts\ChatResult;
use App\Providers\Llm\Contracts\ProviderAdapter;
use App\Providers\Llm\Contracts\StreamEvent;
use App\Providers\Llm\Contracts\TokenUsage;
use App\Providers\Llm\Exceptions\ProviderAuthenticationException;
use App\Providers\Llm\Exceptions\ProviderException;
use App\Providers\Llm\Exceptions\ProviderRateLimitException;
use App\Providers\Llm\Exceptions\ProviderTimeoutException;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Adapter for OpenAI-compatible chat completion APIs.
 *
 * The endpoint and credentials are resolved server-side from configuration and
 * the user's stored (encrypted) credential. This adapter is only registered
 * when the provider is enabled; otherwise the fake provider is used.
 *
 * No credentials are ever logged or returned to the client.
 */
final class OpenAiCompatibleProvider implements ProviderAdapter
{
    public const NAME = 'openai';

    public function name(): string
    {
        return self::NAME;
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        $key = trim((string) config('agent_millow.providers.openai.key', ''));
        $baseUrl = trim((string) config('agent_millow.providers.openai.base_url', ''));
        $model = trim((string) config('agent_millow.providers.openai.default_model', ''));
        $allowedModels = config('agent_millow.providers.openai.allowed_models', []);

        return $key !== ''
            && filter_var($baseUrl, FILTER_VALIDATE_URL) !== false
            && $model !== ''
            && is_array($allowedModels)
            && in_array($model, $allowedModels, true);
    }

    public function complete(ChatRequest $request, ?ProviderCredential $credential = null): ChatResult
    {
        $started = microtime(true);

        try {
            $response = $this->client($credential)
                ->post('chat/completions', $this->payload($request, false));
        } catch (ConnectionException $exception) {
            throw $this->connectionFailure($exception);
        }

        $this->assertSuccessful($response->status());

        /** @var array<string, mixed> $body */
        $body = $response->json();

        $content = (string) data_get($body, 'choices.0.message.content', '');

        $usage = new TokenUsage(
            (int) data_get($body, 'usage.prompt_tokens', 0),
            (int) data_get($body, 'usage.completion_tokens', 0),
        );

        return new ChatResult(
            content: $content,
            usage: $usage,
            model: (string) data_get($body, 'model', $request->model),
            provider: self::NAME,
            finishReason: data_get($body, 'choices.0.finish_reason'),
            meta: ['latency_ms' => (int) round((microtime(true) - $started) * 1000)],
        );
    }

    public function stream(ChatRequest $request, ?ProviderCredential $credential = null): Generator
    {
        try {
            $response = $this->client($credential)
                ->withOptions(['stream' => true])
                ->post('chat/completions', $this->payload($request, true));
        } catch (ConnectionException $exception) {
            throw $this->connectionFailure($exception);
        }

        $this->assertSuccessful($response->status());

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $stream->eof()) {
            $buffer .= $stream->read(1024);

            while (($position = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $position));
                $buffer = substr($buffer, $position + 1);

                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $data = trim(substr($line, 5));

                if ($data === '[DONE]') {
                    yield StreamEvent::done();

                    return;
                }

                /** @var array<string, mixed>|null $decoded */
                $decoded = json_decode($data, true);

                if (is_array($decoded)) {
                    $text = (string) data_get($decoded, 'choices.0.delta.content', '');

                    if ($text !== '') {
                        yield StreamEvent::delta($text);
                    }
                }
            }
        }

        yield StreamEvent::done();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ChatRequest $request, bool $stream): array
    {
        $messages = [];

        foreach ($request->messages as $message) {
            $messages[] = $message->toArray();
        }

        $payload = [
            'model' => $request->model,
            'messages' => $messages,
            'stream' => $stream,
        ];

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        if ($request->maxOutputTokens !== null) {
            $payload['max_tokens'] = $request->maxOutputTokens;
        }

        return array_merge($payload, $request->options);
    }

    private function client(?ProviderCredential $credential): PendingRequest
    {
        $baseUrl = trim((string) config('agent_millow.providers.openai.base_url'));

        $key = trim($credential instanceof ProviderCredential
            ? (string) $credential->secret
            : (string) config('agent_millow.providers.openai.key'));

        if ($key === '') {
            throw new ProviderAuthenticationException('No provider credential is configured.');
        }

        return Http::baseUrl($baseUrl)
            ->withToken($key)
            ->acceptJson()
            ->timeout((int) config('agent_millow.providers.timeout_seconds', 30))
            ->retry((int) config('agent_millow.providers.retry_times', 2), 250);
    }

    private function assertSuccessful(int $status): void
    {
        if ($status === 401 || $status === 403) {
            throw new ProviderAuthenticationException('The provider rejected the credentials.');
        }

        if ($status === 429) {
            throw new ProviderRateLimitException('The provider rate limited the request.');
        }

        if ($status >= 400) {
            throw new ProviderException('The provider request failed.');
        }
    }

    private function connectionFailure(ConnectionException $exception): ProviderException
    {
        $message = $exception->getMessage();

        if (stripos($message, 'timed out') !== false || stripos($message, 'timeout') !== false) {
            return new ProviderTimeoutException('The provider request timed out.', 0, $exception);
        }

        return new ProviderException('The provider could not be reached.', 0, $exception);
    }
}
