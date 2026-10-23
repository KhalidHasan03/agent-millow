<?php

declare(strict_types=1);

namespace App\Providers\Llm\Contracts;

/**
 * A normalized request to generate a completion.
 */
final readonly class ChatRequest
{
    /**
     * @param  list<ChatMessage>  $messages
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $model,
        public array $messages,
        public ?float $temperature = null,
        public ?int $maxOutputTokens = null,
        public bool $stream = false,
        public array $options = [],
    ) {}
}
