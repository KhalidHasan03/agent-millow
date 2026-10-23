# Agent Millow Architecture

Status: Phases 0-5 approved. Phase 6 is in progress: Workstream A (hardening) and
Workstream B (observability) are implemented and provisionally approved. Workstream C
(offline evaluation) is approved. Workstream D (deployment preparation) is implemented
and awaiting review. No public deployment has been performed, and production readiness
is not claimed.

## 1. Purpose

Agent Millow is a personal AI agent platform that can hold conversations, remember
user-approved context, and use tools through explicit approvals. The system is designed
around a strict boundary: the model proposes actions, while Laravel validates,
authorizes, approves, executes, limits, redacts, and audits them.

The architecture is a modular monolith. It favors clear boundaries and production
quality over premature distribution.

## 2. Non-goals for the current phase

- No public deployment, production connection, or deployment execution without
  explicit review and operator approval.
- No real-provider evaluation in the offline suite.
- No direct model access to databases, shell, filesystem, browser, or network.
- No model training on user data.
- No multi-tenant SaaS model in the initial phases.

## 3. Runtime topology

```text
Browser
  |
  | HTTPS / localhost
  v
React SPA (Vite dev server, later static build)
  |
  | /api/v1, /sanctum
  v
Laravel 12 API
  |-- Authentication and authorization (Sanctum, policies, gates)
  |-- Domain services and DTOs
  |-- Tool registry, risk policy, approvals, execution limits
  |-- Audit and redaction
  |
  |-- MySQL 8.4 (users, conversations, memory, tools, audit, queues, cache, sessions)
  |-- Queue workers (provider calls, tool execution, cleanup, indexing)
  |-- LLM/provider adapters (server-side credentials only)
```

In development, the React app runs on `http://localhost:5173` and proxies API requests
to Laravel on `http://localhost:8000`. MySQL 8.4 runs in an isolated local instance on
`127.0.0.1:3307`.

## 4. Component boundaries

### Laravel API

Laravel is the authority for identity, permissions, tool policy, approvals, execution,
and audit. Controllers stay thin and delegate to application services. Requests are
validated with form requests; responses use typed resources/DTOs.

### React console

The frontend is a typed React SPA. It displays conversations, memories, approvals, and
audit views as those features arrive. It never receives provider API keys and never
executes tools directly.

### Database

MySQL 8.4 stores application data and, for Phase 0, queue jobs, cache entries, and
sessions. All tables use `utf8mb4` and UTC timestamps. The local app user has
least-privilege grants on only the Agent Millow databases.

### Queue workers

Heavy or external work runs as queued jobs: provider requests, memory indexing, tool
execution, cleanup, and notifications. Jobs require explicit timeouts, bounded retries,
backoff, and idempotency where side effects are possible.

### Provider adapters

All LLM providers implement a common interface. Adapters normalize messages, streaming
events, tool-call proposals, token usage, errors, and rate-limit behavior. Provider
credentials remain server-side and are encrypted at rest.

### Tool registry and execution layer

Each tool has a versioned definition: name, description, JSON input schema, output
schema, risk level, required scopes, timeout, and approval policy. The registry is the
only path from a model-proposed tool call to an implementation.

## 5. Tool execution pipeline

Every tool call must pass through all stages:

1. **Proposal:** the model returns a tool name and structured arguments.
2. **Registry lookup:** reject unknown or disabled tools.
3. **Schema validation:** reject malformed or unexpected arguments.
4. **Authorization:** verify the tool is enabled for the user and the conversation.
5. **Risk policy:** classify the action and apply limits.
6. **Approval:** pause for explicit user approval when required.
7. **Execution:** run through a controlled adapter with timeout, resource limits, and
   idempotency.
8. **Redaction and audit:** remove secrets, record the decision and result, and expose a
   user-readable audit event.
9. **Result handling:** return a bounded structured result to the model.

The model never receives a database connection, shell, filesystem handle, or unrestricted
network client. Tools that mutate data, spend money, send messages, or access private
files require an approval path and audit trail.

## 6. Memory model

Memory is explicit, user-scoped, and inspectable. Planned capabilities:

- List, inspect, edit, and delete memories.
- Record the source, timestamp, confidence, and category of each memory.
- Separate user-authored facts from model-proposed memories.
- Require approval where a memory write could be surprising or sensitive.
- Audit memory reads and writes where appropriate.
- Never claim that a model permanently learned from memory; memory is retrieved context,
  not model training.

Embeddings or semantic search may be added later, but the canonical memory remains
inspectable relational data.

## 7. Planned data model

| Area | Tables |
| --- | --- |
| Identity | `users`, `sessions`, `personal_access_tokens` |
| Conversations | `conversations`, `messages`, `message_attachments` |
| Providers | `provider_credentials`, `model_profiles`, `provider_usage` |
| Memory | `memories`, `memory_events` |
| Tools | `tools`, `tool_versions`, `tool_calls`, `tool_approvals` |
| Audit | `audit_events` |
| Operations | `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks` |

All user-owned records carry a `user_id` and are protected by policies. Foreign keys
and indexes are added deliberately; no polymorphic shortcut should bypass authorization.

## 8. Security model

- Sanctum SPA authentication with CSRF protection; API tokens only where appropriate.
- Session cookies are HTTP-only, SameSite, and secure in production.
- All API errors are JSON with a request ID and no internal secrets.
- Request IDs are accepted only when well-formed, otherwise generated.
- Security headers include `X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy`, `Permissions-Policy`, and `X-Permitted-Cross-Domain-Policies`.
- Rate limits apply to authentication, chat, and tool endpoints.
- Input validation and output encoding are mandatory.
- Provider credentials are encrypted at rest and never returned to the client.
- Logs redact secrets, tokens, and unnecessary personal data.
- Audit events are append-only in normal application flows.
- Database access uses a dedicated least-privilege account.
- Local and testing boots are guarded against using the wrong database.
- Production requires HTTPS, secure cookies, backups, and a reviewed CORS allowlist.

## 9. API conventions

- Base path: `/api/v1`.
- JSON requests and responses.
- Resource collections are paginated server-side (`page`, `per_page`, maximum 100).
- Mutating background operations accept an idempotency key.
- Errors use a consistent envelope containing `message`, optional `errors`, and the
  request ID.
- Long-running operations return a job/resource identifier rather than blocking.
- Streaming, when added, uses server-sent events or a compatible stream, with
  authorization checked before the stream starts.
- API versioning is explicit; breaking changes require a new version or a documented
  migration.

## 10. Background processing

Phase 0 uses the database queue driver. Later phases may move to Redis if throughput
requires it, but the job contracts should not depend on the driver.

Jobs must define:

- Timeout and retry/backoff policy.
- Idempotency behavior for side effects.
- Failure handling and user-visible status.
- Redacted logging.
- A path to dead-letter/failed-job inspection.

## 11. Frontend architecture

- Functional React components with explicit API types; no `any`.
- A typed API client owns request/response shapes and error handling.
- Tailwind CSS 4 through the Vite plugin.
- Auth uses Sanctum cookies, not tokens stored in `localStorage`.
- No provider credentials or privileged logic in the browser.
- Accessible states for loading, empty, error, approval pending, and execution results.
- Streaming and richer conversation UI arrive in later phases.

## 12. Testing strategy

- **Unit:** DTOs, policies, risk classification, redaction, provider normalization.
- **Feature:** HTTP endpoints, authentication, authorization, validation, approvals,
  audit behavior, database guard.
- **Integration:** MySQL 8.4 test database, queue job execution, provider fakes.
- **Frontend:** TypeScript, oxlint, build, and later component/end-to-end tests.
- **Static analysis:** Larastan level 6, Pint, oxlint, TypeScript.
- Tests never use the development database; PHPUnit points at `agent_millow_test`.

## 13. Deployment direction

Development is Windows with XAMPP PHP and isolated MySQL 8.4. The supported production
baseline is:

- Linux with Nginx and PHP-FPM 8.3/8.4.
- MySQL 8.4 with backups, least-privilege credentials, and TLS where required.
- Supervisor-managed database queue workers and one cron-based Laravel scheduler.
- A same-origin static SPA build with HTTPS, secure cookies, and opt-in HSTS.
- Immutable releases with shared storage, runtime-readable but non-world-readable
  configuration (secret manager or shared environment file), and a reviewed rollback
  path.
- Centralized logs/metrics with redaction.
- A reviewed CORS/Sanctum allowlist and rate limits.

Docker, WSL, and Redis are not part of the current local environment. Configuration
examples and operator runbooks are in [Deployment preparation](deployment.md). They have
not been executed on Linux and do not establish production readiness.

## 14. Phase roadmap

| Phase | Scope | Status |
| --- | --- | --- |
| 0 | Foundation: repo, isolated DB, health API, guards, tooling, tests | Approved |
| 1 | Authentication, user profile, protected API boundary | Approved |
| 2 | Conversations, provider abstraction, streaming foundation | Approved |
| 3 | User-scoped memory with inspection/edit/delete and audit | Approved |
| 4 | Tool registry, risk policy, approvals, execution, audit | Approved |
| 5 | Frontend conversation/approval/audit experiences | Approved |
| 6A | Hardening: limits, retention, audit guards, HSTS, proxies | Implemented, provisionally approved |
| 6B | Observability: JSON logs, redaction, readiness, operational events | Implemented, provisionally approved |
| 6C | Offline evaluation: fake provider, seeded tools, baseline gate | Approved |
| 6D | Deployment preparation: templates, runbooks, smoke test | Implemented, awaiting review |

No deployment has been executed. Workstream D preparation must be reviewed before any
staging or production activation.

### Phase 1 implementation notes

- Sanctum provides two authentication paths: stateful SPA sessions (cookie + CSRF) and
  personal access tokens for non-browser clients. Both resolve to the same `User`.
- API routes are registered in `routes/api.php` under `/api/v1`, grouped by throttle.
  The `ForceJsonForApi` middleware forces JSON negotiation for `api/*` so authenticated
  failures always return the JSON error envelope instead of a redirect.
- All protected endpoints use `auth:sanctum`. Authorization beyond authentication uses
  `UserPolicy`, registered against the `User` model.
- Registration and login return the user resource and, for token requests
  (`issue_token: true`), a plain-text token. Stateful requests instead establish a web
  guard session and return `token: null`.
- Logout revokes only the current access token and invalidates the session when one
  exists. Password changes invalidate the user's other sessions while preserving the
  current session when the request has one; account deletion revokes tokens and removes
  the account.
- Auth events (`auth.registered`, `auth.login`, `auth.logout`, `auth.failed`,
  `auth.password_changed`, `auth.deleted`) are written to `audit_events` through
  `AuditLogger`, which redacts secrets.
- `config/agent_millow.php` centralizes registration enablement, a private-beta email
  allowlist, token lifetime and per-user limits, and per-route rate limits.

### Phase 2 implementation notes

- Every provider implements `App\Providers\Llm\Contracts\ProviderAdapter` and is resolved
  through `ProviderRegistry`. Messages, requests, results, and streaming events are
  normalized into provider-agnostic DTOs. Provider credentials are stored server-side and
  never returned to the client. `ProviderController` returns structured readiness
  (`enabled`, `ready`, `supports_streaming`) and never exposes keys or URLs. Provider
  selection is validated against enabled, ready providers with model allowlists;
  creating a conversation or starting a stream asserts readiness. Provider failures
  are mapped to safe HTTP statuses with `request_id`, and exception messages never
  include raw upstream bodies or secrets.
- The deterministic `FakeProvider` runs with no network calls and is the default in local
  and testing environments. An OpenAI-compatible adapter exists but remains inactive
  unless explicitly enabled and configured with credentials.
- Conversations are user-scoped, soft-deleted, and protected by `ConversationPolicy`.
  Messages belong to a conversation and are protected by `MessagePolicy`. Users can only
  access their own conversations and messages.
- `POST /api/v1/conversations/{id}/messages` appends a user message and, by default,
  generates an assistant reply through the configured provider, persisting usage metrics
  and audit events.
- `GET /api/v1/conversations/{id}/stream` uses server-sent events for streaming
  responses. Authorization is checked before the stream starts; provider events are
  normalized and never leak credentials or upstream stack details.
- `GenerateAssistantReply` is a queued job (`queue: provider`) with retries, backoff,
  timeout, and idempotency guards to prevent duplicate replies.

### Phase 3 implementation notes

- Memory is user-scoped, inspectable, editable, deletable, and auditable. Entries are
  stored as relational data (`memories`) and cannot be accessed by other users.
- Memories record `source` (`user` or `model`), `category`, confidence, and optional
  metadata. User-authored and model-proposed memories are distinguished; the model is
  never claimed to permanently learn from memory.
- Inspecting a memory updates `last_accessed_at` and records a read audit event.
- Memory operations write to `memory_events` (and the general audit log) without
  logging full memory content. Sensitive memories are marked but stored with the same
  access controls.

### Phase 4 implementation notes

- Tools are registered via `tools` and versioned in `tool_versions` with JSON schemas,
  risk level, timeout, and approval policy. The registry is the only path from a
  proposed tool call to an implementation.
- Every tool call passes through registry lookup, schema validation, authorization,
  risk classification, approval (when required), execution with timeout/idempotency
  tracking, redaction, and audit. The LLM never receives direct DB/shell/FS/network
  access.
- High/critical risk tools require approval by default; pending calls create a
  `tool_approvals` record and only proceed after explicit user approval via
  `POST /api/v1/tools/calls/{id}/approve` or reject.
- Tool calls are user-scoped and auditable. Inputs/outputs are redacted before audit
  logging where applicable; secrets are never logged. A safe `echo` tool is seeded for
  local/testing use.
- Routes: `GET /api/v1/tools`, `POST /api/v1/tools/call`, `GET /api/v1/tools/calls/{id}`,
  `POST /api/v1/tools/calls/{id}/approve`, `POST /api/v1/tools/calls/{id}/reject`.

### Phase 5 implementation notes

- React + TypeScript SPA with Sanctum cookie auth, protected routes, and typed API
  client. UI covers conversations/messages with streaming, memory CRUD/inspect,
  tool listing/call/approval/rejection, and audit history.
- All mutations go through the authenticated API with proper loading, error, empty,
  and success states. Authorization remains enforced server-side; frontend checks are
  for UX only.
- Audit history is read via a user-scoped, read-only `GET /api/v1/audit` endpoint with
  server-side pagination and optional `event`, `subject_type`, `from`, and `to`
  filters. Audit events stay append-only; no write route is exposed.
- No secrets are stored in the browser; provider credentials remain server-side.

### Phase 6 implementation notes

- Workstream A adds configurable sensitive-route and tool rate limits, a 1 MiB request
  body cap, optional HSTS and trusted-proxy configuration, per-user hourly tool quotas
  and pending-approval caps, and configurable retention. `maintenance:prune` is dry-run
  by default, refuses `--force` outside `production`/`testing`, and is scheduled only in
  production. Audit events remain append-only; retention uses an explicit model method.
- Workstream B adds JSON-first structured logging with safe request context
  (`request_id`, method, path, route, optional user ID) and formatter-level redaction.
  Custom formatters are required because Laravel's built-in handlers ignore channel
  `processors`. Operational events cover authentication failures, provider failures,
  tool approval/rejection/failure, and queue failures. Readiness checks database, cache,
  and queue dependencies; `/up` remains dependency-free liveness.
- Logs never include query strings, headers, cookies, bodies, emails, or tokens.
  Exception objects in log context are converted to redacted class/message/trace data.
  See [Observability](observability.md).
- Workstream C adds a deterministic offline evaluation suite behind `eval:offline`.
  Scenarios cover provider normalization and streaming, tool selection and risk,
  approval enforcement, memory isolation, cross-user access, prompt-injection
  resistance, and secret redaction. The runner uses an in-memory SQLite database, the
  fake provider, seeded `echo`/`eval_guarded` tools, array cache, sync queue, and HTTP
  stray-request prevention; it restores configuration and rolls back scenario
  transactions afterwards.
- Reports are written under `storage/app/evaluations` and compared with the versioned
  `evaluation/baselines/offline-v1.json`. Missing checks, failed checks, threshold
  violations, or scenario failures exit nonzero. Baseline updates require a fully
  passing report that satisfies the existing contract. The evaluation runs as the final
  backend quality-check step. See [Offline evaluation](evaluation.md).
- Workstream D adds a non-secret production environment template, reviewed Nginx,
  FastCGI, PHP-FPM/OPcache, Supervisor, and cron examples, immutable-release and
  rollback/backup/restore/incident runbooks, a Linux-targeted API smoke test, and a
  credential-free CI configuration. Nothing was deployed. Nginx, PHP-FPM, Supervisor,
  TLS, production MySQL, backups, log shipping, and the smoke test remain unverified
  until exercised in a real staging/production environment. The frontend auth context
  normalizes the API response envelope, but browser login/logout over TLS remains
  unverified. See [Deployment preparation](deployment.md).

## 15. Decision records

- [ADR-0001: Local environment](decisions/0001-local-environment.md)
- [ADR-0002: API authentication and the protected boundary](decisions/0002-api-authentication.md)
- [ADR-0003: Conversations, provider abstraction, and streaming](decisions/0003-conversations-provider-abstraction.md)
- [ADR-0004: User-scoped memory with inspection/edit/delete and audit](decisions/0004-user-scoped-memory.md)
- [ADR-0005: Tool registry, risk policy, approvals, and secure execution](decisions/0005-tool-registry-risk-approvals.md)
- [ADR-0006: Frontend conversation, approval, and audit experiences](decisions/0006-frontend-experiences.md)
- [ADR-0007: Hardening and observability](decisions/0007-hardening-observability.md)
- [ADR-0008: Offline evaluation](decisions/0008-offline-evaluation.md)
- [ADR-0009: Production deployment target](decisions/0009-production-deployment.md)
