<?php

declare(strict_types=1);

namespace App\Providers\Llm\Contracts;

use App\Models\ProviderCredential;
use Generator;

/**
 * Common interface implemented by every LLM provider integration.
 *
 * Implementations translate a normalized {@see ChatRequest} into the upstream
 * wire format and normalize the response back. Provider credentials are always
 * passed in from the server side; they must never be exposed to the client or
 * logged.
 */
interface ProviderAdapter
{
    /**
     * Identifier used in configuration, conversations, and usage records.
     */
    public function name(): string;

    /**
     * Whether this adapter can stream responses.
     */
    public function supportsStreaming(): bool;

    /**
     * Whether the server-side configuration required to call this adapter is
     * present. This does not make a network request.
     */
    public function isConfigured(): bool;

    /**
     * Produce a single completion.
     */
    public function complete(ChatRequest $request, ?ProviderCredential $credential = null): ChatResult;

    /**
     * Produce a streamed completion as a sequence of normalized events.
     *
     * @return Generator<int, StreamEvent>
     */
    public function stream(ChatRequest $request, ?ProviderCredential $credential = null): Generator;
}
