<?php

declare(strict_types=1);

namespace App\Providers\Llm\Contracts;

/**
 * Normalized non-streaming completion result.
 */
final readonly class ChatResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $content,
        public TokenUsage $usage,
        public string $model,
        public string $provider,
        public ?string $finishReason = null,
        public array $meta = [],
    ) {}
}
