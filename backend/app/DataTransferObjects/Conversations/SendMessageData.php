<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Conversations;

final readonly class SendMessageData
{
    public function __construct(
        public string $content,
        public ?string $clientMessageId = null,
        public bool $generate = true,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromRequest(array $validated): self
    {
        return new self(
            content: (string) $validated['content'],
            clientMessageId: isset($validated['client_message_id']) ? (string) $validated['client_message_id'] : null,
            generate: (bool) ($validated['generate'] ?? true),
        );
    }
}
