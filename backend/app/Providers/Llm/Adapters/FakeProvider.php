<?php

declare(strict_types=1);

namespace App\Providers\Llm\Adapters;

use App\Models\ProviderCredential;
use App\Providers\Llm\Contracts\ChatRequest;
use App\Providers\Llm\Contracts\ChatResult;
use App\Providers\Llm\Contracts\ProviderAdapter;
use App\Providers\Llm\Contracts\StreamEvent;
use App\Providers\Llm\Contracts\TokenUsage;
use App\Providers\Llm\Exceptions\ProviderException;
use Generator;

/**
 * Deterministic, offline provider used for local development and tests.
 *
 * It never performs network calls. Output is derived from the input so tests
 * can assert on exact content while exercising the entire pipeline.
 */
final class FakeProvider implements ProviderAdapter
{
    public const NAME = 'fake';

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
        return true;
    }

    public function complete(ChatRequest $request, ?ProviderCredential $credential = null): ChatResult
    {
        $this->guard($request);

        $content = $this->render($request);

        return new ChatResult(
            content: $content,
            usage: $this->usage($request, $content),
            model: $request->model,
            provider: self::NAME,
            finishReason: 'stop',
            meta: ['fake' => true],
        );
    }

    public function stream(ChatRequest $request, ?ProviderCredential $credential = null): Generator
    {
        $this->guard($request);

        $content = $this->render($request);
        $chunks = $this->chunks($content, 8);
        $emitted = '';

        foreach ($chunks as $chunk) {
            $emitted .= $chunk;

            yield StreamEvent::delta($chunk);
        }

        yield StreamEvent::done($this->usage($request, $emitted), 'stop');
    }

    private function guard(ChatRequest $request): void
    {
        if ($request->messages === []) {
            throw new ProviderException('The request contained no messages.');
        }
    }

    private function render(ChatRequest $request): string
    {
        $lastUser = '';

        foreach ($request->messages as $message) {
            if ($message->role === 'user') {
                $lastUser = $message->content;
            }
        }

        if ($lastUser === '') {
            $lastUser = $request->messages[array_key_last($request->messages)]->content;
        }

        return sprintf('[fake:%s] %s', $request->model, $lastUser);
    }

    private function usage(ChatRequest $request, string $content): TokenUsage
    {
        $prompt = 0;

        foreach ($request->messages as $message) {
            $prompt += $this->estimate($message->content);
        }

        return new TokenUsage($prompt, $this->estimate($content));
    }

    private function estimate(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / 4));
    }

    /**
     * @return list<string>
     */
    private function chunks(string $content, int $size): array
    {
        $chunks = [];

        $length = mb_strlen($content);

        for ($offset = 0; $offset < $length; $offset += $size) {
            $chunks[] = mb_substr($content, $offset, $size);
        }

        return $chunks === [] ? [''] : $chunks;
    }
}
