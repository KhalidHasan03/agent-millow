# ADR-0007: Hardening and observability

- Status: Accepted for Phase 6 Workstreams A and B; provisionally approved
- Date: 2026-10-23

## Context

Phase 6 needs defensive controls and operational visibility before evaluation and
deployment. The system handles conversations, memory, tool approvals, and provider
credentials, so logs and health endpoints must not become a data leak or a false sense
of safety.

The first observability implementation placed `RequestContext` and
`RedactSensitiveData` in channel `processors`. Testing showed that Laravel's built-in
`single`, `daily`, `stack`, `slack`, `syslog`, and `errorlog` handlers ignore those
processor lists, so redaction was not applied. This was a confirmed defect, not only a
coverage gap.

## Decision

- Use custom Monolog formatters for every configured channel. `SafeLineFormatter` and
  `SafeJsonFormatter` apply request context and redaction at format time, independent of
  handler behavior. The `json` channel is the recommended production default.
- Log only safe identifiers: request ID, method, path, route name, optional user ID, and
  explicit operational fields. Never log query strings, headers, cookies, bodies,
  emails, or tokens.
- Redact by sensitive key, credential-shaped string patterns, and exception objects.
  Exception objects become redacted class/message/trace arrays. Redaction is defense in
  depth; call sites must still avoid secrets.
- Expose `/up` for liveness and `/api/v1/health` for readiness. Readiness checks database,
  cache, and queue access and returns `503` when any check fails. Dependency details are
  limited to local/testing.
- Emit structured operational events for failed logins, provider failures, tool
  approval/rejection/failure, and queue failures. Audit events remain the authoritative
  action record.
- Keep observability vendor-neutral. Do not add external monitoring or log shipping
  without explicit approval.
- Apply defense-in-depth limits: configurable rate limits, a request body cap, tool
  quotas, optional HSTS/trusted proxies, and retention commands that default to dry-run
  and require explicit approval to execute in production.

## Consequences

- Operators can forward JSON logs without changing application code.
- Redaction tests must cover the actual channel handlers, not only processors.
- Emergency framework logging bypasses configured channels and remains outside this
  guarantee; it must be documented and handled as an incident if it emits secrets.
- Custom formatters are part of the security boundary and must not be replaced with
  built-in formatters without equivalent redaction tests.
- Append-only audit remains an application-level invariant; direct query-builder writes
  and emergency deletion paths remain a known residual risk.
