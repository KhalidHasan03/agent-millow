<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Conversations;

final readonly class CreateConversationData
{
    public function __construct(
        public ?string $title = null,
        public ?string $systemPrompt = null,
        public ?string $provider = null,
        public ?string $model = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromRequest(array $validated): self
    {
        return new self(
            title: isset($validated['title']) ? (string) $validated['title'] : null,
            systemPrompt: isset($validated['system_prompt']) ? (string) $validated['system_prompt'] : null,
            provider: isset($validated['provider']) ? (string) $validated['provider'] : null,
            model: isset($validated['model']) ? (string) $validated['model'] : null,
        );
    }
}
