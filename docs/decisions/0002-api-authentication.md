# ADR-0002: API authentication and the protected boundary

- Status: Accepted
- Date: 2026-10-23
- Phase: 1

## Context

Phase 1 adds authentication and the protected API boundary. The platform targets two
client types:

- The first-party React console, which runs on the browser and should use cookie-based
  sessions with CSRF protection.
- Scripts, tests, and future integrations, which need bearer tokens.

Laravel 12 ships Sanctum 4, which supports both models against the same `User` model.
The existing architecture rules require that Laravel remain the authorization authority,
that API errors be JSON with a request ID, and that sensitive activity be auditable.

## Decision

1. **Use Sanctum for both session and token authentication.** The browser console uses
   the stateful SPA flow (`statefulApi()`, `/sanctum/csrf-cookie`, session cookie). Other
   clients send a personal access token in `Authorization: Bearer`. Every protected
   route uses `auth:sanctum`, so both paths resolve identically.
2. **Token issuance is explicit.** Login and registration return a plain-text token only
   when the request sets `issue_token: true`. Stateful requests instead establish a web
   guard session and return `token: null`, avoiding leaking a token into the SPA.
3. **Tokens are time-limited and capped per user.** Lifetime and per-user maximum come
   from `config/agent_millow.php`. When the cap is reached, the oldest tokens are pruned.
4. **Force JSON for `/api/*`.** A `ForceJsonForApi` middleware sets `Accept:
   application/json` for API requests so unauthenticated and error responses always use
   the JSON envelope rather than redirecting to a `login` route that does not exist in an
   API-only app.
5. **Centralize auth policy.** Registration enablement, a private-beta email allowlist,
   role assignment, and rate limits live in `config/agent_millow.php`.
6. **Audit authentication activity.** A redacting `AuditLogger` writes append-only
   records (`auth.registered`, `auth.login`, `auth.failed`, `auth.logout`,
   `auth.password_changed`, `auth.deleted`) to `audit_events`.
7. **Delete owned tokens on account deletion.** The `User` model removes its access
   tokens when the account is deleted, keeping token storage consistent.
8. **Authorize with policies.** `UserPolicy` is registered for the `User` model; profile
   actions authorize against the authenticated user, and cross-user access is denied.

## Alternatives considered

- **Session-only authentication.** Rejected. Non-browser clients need tokens, and
  tokens are also required for future tool execution and integrations.
- **Token-only authentication.** Rejected. The first-party console should use HTTP-only
  session cookies with CSRF rather than storing tokens in `localStorage`.
- **Issue a token on every login.** Rejected. It would leak a bearer token into the SPA
  and duplicate the session mechanism.
- **Use a custom guard.** Rejected. Sanctum already provides both flows and is the
  Laravel-standard choice; a custom guard adds risk without benefit.
- **Always return 401 as an HTML redirect.** Rejected. This is an API-only backend; all
  API errors must be JSON.

## Consequences

Positive:

- One identity model serves the SPA and future integrations.
- Protected routes are uniformly authenticated and authorized.
- Authentication activity is auditable and secrets are redacted.
- Errors are consistently JSON with a request ID.

Negative and mitigations:

- Two authentication paths increase test surface. Mitigation: feature tests cover both
  the stateful session flow and the bearer-token flow.
- Token storage must track expiry and caps. Mitigation: expiration and per-user pruning
  are enforced in `AccessTokenService` and covered by tests.

## Verification

- `php backend\artisan test` passes, including `TokenAndSessionTest`, `RegisterTest`,
  `LoginTest`, `ProtectedRoutesTest`, `ProfileTest`, `CrossUserIsolationTest`,
  `AuditLoggingTest`, and `RateLimitTest`.
- Unauthenticated requests to protected routes return `401` JSON.
- A token revoked by logout is rejected on reuse; other tokens remain valid.
- MariaDB on 3306 remains untouched.
