<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Memories;

final readonly class UpdateMemoryData
{
    public function __construct(
        public ?string $title = null,
        public ?string $content = null,
        public ?string $key = null,
        public ?string $category = null,
        public ?int $confidence = null,
        public ?bool $sensitive = null,
        public ?bool $pinned = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromRequest(array $validated): self
    {
        return new self(
            title: isset($validated['title']) ? (string) $validated['title'] : null,
            content: isset($validated['content']) ? (string) $validated['content'] : null,
            key: isset($validated['key']) ? (string) $validated['key'] : null,
            category: isset($validated['category']) ? (string) $validated['category'] : null,
            confidence: isset($validated['confidence']) ? (int) $validated['confidence'] : null,
            sensitive: array_key_exists('sensitive', $validated) ? (bool) $validated['sensitive'] : null,
            pinned: array_key_exists('pinned', $validated) ? (bool) $validated['pinned'] : null,
        );
    }
}
