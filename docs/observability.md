# Observability

Agent Millow emits structured, redacted logs and exposes liveness and readiness
endpoints. Observability is intentionally vendor-neutral: local development writes JSON
to the application log, and production can forward the same stream to an approved
collector without changing application code.

## Logging

The default production-oriented channel is `json`:

```dotenv
LOG_CHANNEL=json
```

Other file, stderr, syslog, Slack, and Papertrail channels use `SafeLineFormatter`; the
`json` channel uses `SafeJsonFormatter`. Both formatters apply the same two processors
at format time:

- `App\Logging\RequestContext` adds safe request identifiers.
- `App\Logging\RedactSensitiveData` removes credentials from messages, context, extras,
  and nested exception context.

The formatter wrapper exists because Laravel's built-in `single`, `daily`, `stack`,
`slack`, `syslog`, and `errorlog` handlers do not run Monolog `processors` configured on
the channel. Do not move these processors back into `config/logging.php` without
verifying that every enabled driver still applies redaction.

### JSON record shape

```json
{
  "message": "provider.reply_failed",
  "context": {
    "provider": "openai",
    "model": "gpt-4.1-mini",
    "conversation_id": 42,
    "user_id": 7,
    "request_id": "8f14e45f-ceea-467f-a7a0-6d2c9b7f1c11",
    "method": "POST",
    "path": "/api/v1/conversations/42/messages",
    "exception": "App\\Providers\\Llm\\Exceptions\\ProviderException",
    "reason": "HTTP 401: [redacted]"
  },
  "level": 300,
  "level_name": "WARNING",
  "channel": "json",
  "datetime": "2026-10-23T12:00:00.000000+00:00",
  "extra": {}
}
```

`RequestContext` only adds:

- `request_id`: the validated `X-Request-Id` header or a generated UUID.
- `method`, `path`, and the route name when available.
- `user_id` when the request is authenticated.

It never logs query strings, headers, cookies, bodies, email addresses, or tokens.
Console and queue records have no request context unless an explicit `request_id` is
provided.

### Events

| Event | Level | Purpose | Safe fields |
| --- | --- | --- | --- |
| `auth.login_failed` | warning | Failed login | `reason`, `email_hash`, optional `user_id`, `ip` |
| `provider.reply_failed` | warning | Non-streaming provider failure | `provider`, `model`, `conversation_id`, `user_id`, exception class/reason |
| `provider.stream_failed` | warning | Streaming provider failure | `provider`, `model`, `conversation_id`, `user_id`, exception class/reason |
| `tool.approved` | info | User approved a pending tool call | `tool`, `tool_call_id`, `user_id`, `risk_level`, `tool_request_id` |
| `tool.rejected` | info | User rejected a pending tool call | Same as approval |
| `tool.failed` | error | Tool execution failed | `tool`, `tool_call_id`, `user_id`, `duration_ms`, exception class |
| `queue.job_failed` | error | Any queued job failure | `connection`, `queue`, `job`, exception class/reason |
| `queue.job_failed_permanently` | error | Assistant reply job exhausted retries | `job`, `queue`, `conversation_id`, `user_id`, `request_id`, `attempts`, exception class/reason |

Audit events remain in the `audit_events` table and are exposed through the read-only
audit API. Logs are for operational diagnosis, not for reconstructing user content.

## Health endpoints

| Endpoint | Purpose | Expected |
| --- | --- | --- |
| `GET /up` | Liveness | `200` when the PHP application can respond |
| `GET /api/v1/health` | Readiness | `200` when database, cache, queue, and default provider configuration pass; `503` when degraded |

Readiness checks:

- **Database:** a lightweight `SELECT 1`.
- **Cache:** writes, reads, and deletes a short-lived probe key.
- **Queue:** confirms the configured queue connection is usable.
- **Providers:** configuration-only readiness for enabled providers; the default provider must be ready. No network calls are made.

Outside `local` and `testing`, the response omits database names and versions. Use the
`request_id` response header to correlate a failing probe with logs.

## Alerting

External monitoring is not configured yet. The following rules are suitable for a
collector or an uptime monitor once one is approved:

- Readiness endpoint returns non-2xx for two consecutive minutes.
- `queue.job_failed_permanently` occurs at all.
- `queue.job_failed` rate exceeds the normal baseline for five minutes.
- Provider failure rate exceeds 5% of requests over fifteen minutes.
- `auth.login_failed` spikes for a single IP or `email_hash`.
- `tool.failed` occurs for a high/critical-risk tool.
- Disk usage or log growth threatens the host.
- HTTP 5xx rate increases; correlate with `request_id`.

Do not include secrets, full prompts, or message content in alert payloads.

## Dashboards

Minimum useful views:

- **Traffic and errors:** request count, 4xx/5xx rate, p95 latency by route.
- **Readiness:** database, cache, and queue probe status.
- **Providers:** non-streaming and streaming failures by provider/model.
- **Queue:** pending `jobs` count, failed jobs by queue, retry exhaustion.
- **Tools:** approvals, rejections, and failures by risk level.
- **Auth:** failed login rate and throttling events.

Useful database queries:

```sql
SELECT queue, COUNT(*) AS pending FROM jobs GROUP BY queue;

SELECT queue, COUNT(*) AS failed
FROM failed_jobs
WHERE failed_at >= NOW() - INTERVAL 1 DAY
GROUP BY queue;

SELECT event, COUNT(*) AS total
FROM audit_events
WHERE created_at >= NOW() - INTERVAL 1 DAY
GROUP BY event
ORDER BY total DESC;
```

Log correlation:

```bash
jq -c 'select(.context.request_id == "PASTE_REQUEST_ID")' storage/logs/laravel.json.log
```

## Troubleshooting

- **Logs are not JSON:** confirm `LOG_CHANNEL=json`, clear cached config with
  `php artisan config:clear`, and check that no custom channel overrides the formatter.
- **`request_id` is missing:** confirm `AssignRequestId` runs for the route. Queue and
  console records only include it when explicitly supplied.
- **A secret appears in a log:** treat it as an incident, rotate the credential, and add
  the key or message shape to `RedactSensitiveData` with a regression test. Emergency
  framework logging bypasses configured channels and is not covered by the formatters.
- **Readiness is degraded:** in local/testing the response names the failing check.
  In production, inspect logs and infrastructure using the returned `request_id`.
- **Queue failures:** inspect `failed_jobs` first; the payload and exception are stored
  there. Prefer `queue.job_failed_permanently` for alerting and avoid replaying jobs
  without confirming idempotency.
- **Audit and logs disagree:** audit events are the authoritative record of user-visible
  actions; logs may be sampled, rotated, or forwarded differently.

## Data handling

- Logs are retained by the configured daily rotation (14 days by default locally).
- Production should set a documented retention period and forward logs only to an
  approved, encrypted sink.
- Redaction is defense in depth, not permission to log secrets. Call sites must still
  avoid placing credentials or unnecessary personal data in log context.
- Tests cover key-based, pattern-based, nested, message, and exception-object redaction,
  plus the actual `single` and `json` channels.
