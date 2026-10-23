# ADR-0006: Frontend conversation, approval, and audit experiences

- Status: Accepted
- Date: 2026-10-24
- Phase: 5

## Context

Phase 5 delivers the React + TypeScript console for conversations, streaming,
memory management, tool-call status, approvals/rejections, and audit history. The
frontend must be professional and responsive, show loading/empty/error/success
states, and never be relied upon for authorization. Credentials and secrets stay
server-side.

## Decision

1. **SPA routing with protected routes.** React Router guards authenticated views;
   unauthenticated users are redirected to login. The backend still enforces
   authorization for every request.
2. **Sanctum cookie authentication.** The browser uses session cookies and CSRF
   bootstrapping (`/sanctum/csrf-cookie`), not tokens in `localStorage`.
3. **Typed API client.** A single typed client owns request/response shapes and error
   handling, including 403/422/5xx with request IDs.
4. **Feature coverage.** Conversations and messages (with SSE streaming), memory
   list/create/inspect/delete, tool listing/call/approve/reject, and audit history.
5. **State handling.** Every screen provides loading, empty, error, and success
   states. Mutations refresh server state rather than assuming client-only updates.
6. **No client-side secrets.** Provider credentials stay on the backend; the UI only
   sees tool definitions and redacted results.

## Alternatives considered

- **Server-rendered Blade UI.** Rejected; the architecture specifies a React/TS SPA.
- **Client-side authorization.** Rejected; authorization must be server-side.
- **Store PATs in localStorage.** Rejected; cookie-based Sanctum is safer for the SPA.

## Consequences

Positive:

- Complete, reviewable console for Phases 1-4 capabilities.
- Consistent error handling and accessibility-oriented states.

Negative and mitigations:

- Bundle/dependency growth (React Router). Mitigation: justified for routing and
  maintained.
- Streaming UI currently refreshes final messages rather than rendering incremental
  tokens. Mitigation: acceptable for Phase 5; incremental rendering can be added
  without API changes.

## Verification

- `npm --prefix frontend run check` passes (oxlint + TypeScript) with no warnings.
- `npm --prefix frontend run build` succeeds.
- `composer --working-dir=backend run check` passes (Pint, PHPStan level 6,
  PHPUnit: 99 tests).
- Manual flows: login, conversations/messages, memory CRUD, tool call + approval,
  audit view.
- MariaDB on 3306 remains untouched.

## Addendum: audit read endpoint

The UI needs a read model for audit history, so Phase 5 adds a read-only,
user-scoped `GET /api/v1/audit` endpoint (paginated, optional `event`,
`subject_type`, `from`, `to` filters). Audit events remain append-only; no write,
update, or delete route is exposed.
