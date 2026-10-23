<?php

declare(strict_types=1);

namespace App\Providers\Llm;

/**
 * Safe, client-facing readiness information for one provider adapter.
 *
 * Readiness only reflects server-side configuration. It never probes the
 * upstream provider and never exposes credentials.
 */
final readonly class ProviderStatus
{
    public function __construct(
        public string $name,
        public bool $enabled,
        public bool $ready,
        public bool $supportsStreaming,
    ) {}

    /**
     * @return array{name: string, enabled: bool, ready: bool, supports_streaming: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'enabled' => $this->enabled,
            'ready' => $this->ready,
            'supports_streaming' => $this->supportsStreaming,
        ];
    }
}
