# ADR-0003: Conversations, provider abstraction, and streaming

- Status: Accepted
- Date: 2026-10-23
- Phase: 2

## Context

Phase 2 adds conversations, a provider abstraction, and a streaming foundation.
The architecture requires that the model only propose actions while Laravel validates,
authorizes, and audits everything; provider credentials must remain server-side;
the LLM must never receive direct database, shell, filesystem, or network access; and
all API errors must be JSON with a request ID.

## Decision

1. **Normalize all provider interactions.** Every LLM provider implements
   `App\Providers\Llm\Contracts\ProviderAdapter` (`complete()`, `stream()`) and is
   resolved through `ProviderRegistry`. Normalized DTOs (`ChatMessage`, `ChatRequest`,
   `ChatResult`, `StreamEvent`, `TokenUsage`) decouple the application from upstream
   wire formats.
2. **Default to a deterministic offline provider.** `FakeProvider` has no network
   calls, is used by default in local and testing, and produces deterministic output
   derived from the input so the entire pipeline can be exercised without credentials.
   An OpenAI-compatible adapter is implemented but disabled by default.
3. **Conversations are user-scoped and authorized.** `Conversation` and `Message`
   are protected by policies; route model binding is combined with authorization and
   ownership checks. Cross-user access is rejected with `403`. Soft deletion is used for
   conversations.
4. **Streaming uses SSE with pre-authorization.** `GET
   .../conversations/{conversation}/stream` checks authorization before writing any
   stream bytes, sets `text/event-stream` headers, normalizes provider events into
   `start`/`delta`/`done`/`error` frames, persists the assistant message and usage on
   completion, and redacts provider errors.
5. **Background generation is queued and idempotent.** `GenerateAssistantReply` runs
   on the `provider` queue with retries and backoff, a timeout, `failed()` logging, and
   an idempotency guard (it will not create a duplicate reply if one already exists).
6. **Audit and usage tracking.** Creating conversations, sending messages, and
   generating replies record audit events. Provider calls write `provider_usage`
   records with token counts, latency, and status.
7. **Config-driven provider selection.** Enabled providers and defaults are configured
   in `config/agent_millow.php` and environment variables. The registry refuses
   unknown providers with a safe error.

## Alternatives considered

- **Call providers directly from controllers.** Rejected. It would bypass the
  normalization layer, make testing harder, leak provider details, and cannot enforce
  the future tool/execution pipeline.
- **Require a real provider for Phase 2.** Rejected. The project must remain usable
  offline and tests must be deterministic; keys should stay server-side and be added
  only when needed.
- **Stream via WebSockets.** Rejected for this phase. SSE is simpler for
  request-scoped authorization, fits the existing JSON/API patterns, and satisfies the
  "streaming foundation" requirement without adding a broadcast stack.
- **Generate replies synchronously for every message.** Rejected as the only path.
  The synchronous path exists for simplicity, but the queued job provides retries,
  timeouts, backoff, and idempotency for heavier workloads.

## Consequences

Positive:

- Provider-agnostic chat works end-to-end with a deterministic fake provider.
- Streaming is authorized before the stream starts and emits normalized events.
- Conversations/messages are isolated per user and audited.
- Background generation is resilient with retries, timeouts, and idempotency.

Negative and mitigations:

- SSE is one-directional and harder to cancel. Mitigation: acceptable for a streaming
  foundation; can evolve later.
- The OpenAI adapter is implemented but not exercised by default. Mitigation: kept
  disabled and never auto-registered; can be enabled explicitly with server-side keys.

## Verification

- `php backend\artisan test` passes, including `FakeProviderTest`, `ConversationTest`,
  `MessageTest`, `StreamTest`, `GenerateAssistantReplyJobTest`, and
  `ProviderRegistryTest`.
- A user can create a conversation, post a message, and receive a fake assistant
  reply; a second user cannot access the conversation.
- Streaming returns SSE events and persists the assistant message.
- The queued job is idempotent and uses retry/backoff/timeout.
- MariaDB on 3306 remains untouched.
