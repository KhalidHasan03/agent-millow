<?php

declare(strict_types=1);

namespace App\Providers\Llm\Contracts;

/**
 * Normalized token accounting for a single provider response.
 */
final readonly class TokenUsage
{
    public function __construct(
        public int $promptTokens = 0,
        public int $completionTokens = 0,
    ) {}

    public function total(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->total(),
        ];
    }
}
