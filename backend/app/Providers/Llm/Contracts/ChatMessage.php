<?php

declare(strict_types=1);

namespace App\Providers\Llm\Contracts;

/**
 * A normalized chat message handed to a provider adapter.
 *
 * Provider adapters translate this into whatever wire format the upstream
 * service expects, so the rest of the application never depends on provider
 * specific payloads.
 */
final readonly class ChatMessage
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $role,
        public string $content,
        public array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
        ];
    }
}
