<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Memories;

final readonly class CreateMemoryData
{
    public function __construct(
        public string $title,
        public string $content,
        public ?string $key = null,
        public ?string $category = null,
        public ?string $source = null,
        public ?int $confidence = null,
        public bool $sensitive = false,
        public bool $pinned = false,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromRequest(array $validated): self
    {
        return new self(
            title: (string) $validated['title'],
            content: (string) $validated['content'],
            key: isset($validated['key']) ? (string) $validated['key'] : null,
            category: isset($validated['category']) ? (string) $validated['category'] : null,
            source: isset($validated['source']) ? (string) $validated['source'] : null,
            confidence: isset($validated['confidence']) ? (int) $validated['confidence'] : null,
            sensitive: (bool) ($validated['sensitive'] ?? false),
            pinned: (bool) ($validated['pinned'] ?? false),
        );
    }
}
