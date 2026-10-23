<?php

declare(strict_types=1);

namespace App\Providers\Llm\Contracts;

/**
 * A normalized streaming event emitted by a provider adapter.
 *
 * The application maps these onto server-sent events without knowing how any
 * particular provider frames its stream.
 */
final readonly class StreamEvent
{
    public const TYPE_DELTA = 'delta';

    public const TYPE_DONE = 'done';

    public const TYPE_ERROR = 'error';

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $type,
        public string $text = '',
        public ?TokenUsage $usage = null,
        public ?string $finishReason = null,
        public array $meta = [],
    ) {}

    public static function delta(string $text): self
    {
        return new self(self::TYPE_DELTA, $text);
    }

    public static function done(?TokenUsage $usage = null, ?string $finishReason = null): self
    {
        return new self(self::TYPE_DONE, '', $usage, $finishReason);
    }

    public static function error(string $message): self
    {
        return new self(self::TYPE_ERROR, $message);
    }
}
