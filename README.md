# Agent Millow

A secure, production-oriented personal AI agent platform. The application is a modular
Laravel API with a React/TypeScript control plane. Agent capabilities run behind
explicit permissions, approvals, and audit; the model never receives direct system
access.

## Current status

**Phases 0-5 are approved. Phase 6 (hardening, observability, evaluation, deployment)
is in progress: Workstream A (hardening) and Workstream B (observability) are
implemented and provisionally approved; Workstream C (offline evaluation) is approved;
Workstream D (deployment preparation) is implemented and awaiting review. No application
has been deployed publicly, and production readiness is not claimed.** The repository
contains:

- Laravel 12.70.1 API backend with Sanctum installed.
- React 19 + TypeScript + Vite 8 frontend with Tailwind CSS 4.
- Isolated MySQL 8.4.11 development instance on port `3307`.
- Dedicated `agent_millow_dev` and `agent_millow_test` databases.
- Health endpoint, request IDs, security headers, JSON API errors, and a database
  name safety guard.
- Sanctum authentication: registration, login, logout, profile read/update, password
  change, and account deletion, all behind `auth:sanctum`.
- User-scoped conversations and messages with provider-agnostic streaming (SSE),
  normalized provider adapters, and a deterministic offline fake provider.
- Queued assistant generation with retries, backoff, timeout, and idempotency.
- User-scoped, inspectable, editable, and deletable memory with source/category
  tracking and audit events (no full-content logging).
- Tool registry with schema validation, risk classification, approval gating,
  controlled execution, redaction, and full audit. The LLM never receives direct
  system access.
- Append-only audit events for authentication, conversation, memory, and tool
  activity.
- React + TypeScript console with Sanctum cookie auth, conversations and streaming,
  memory management, tool calls with approvals, and audit history.
- Structured JSON logs with request correlation, redaction, and operational events.
- Liveness and readiness probes covering database, cache, and queue dependencies.
- Configurable rate limits, request limits, per-user tool quotas, and retention
  commands with dry-run defaults.
- Deterministic offline evaluation with fake-provider and seeded-tool scenarios,
  versioned baselines, and a quality-gate command.
- Production deployment preparation: environment template, reviewed Nginx,
  PHP-FPM/OPcache, Supervisor and scheduler examples, runbooks, and an API smoke test.
- PHPUnit, Larastan level 6, Pint, oxlint, and TypeScript checks.

Provider credentials remain server-side and are never exposed to the client. Tools run
only through the registry, risk policy, approval flow, and audit boundary; the model
never receives direct database, shell, filesystem, or network access.

## Stack

| Layer | Choice |
| --- | --- |
| Backend | PHP 8.2.4, Laravel 12.70.1, Sanctum 4 |
| Frontend | React 19, TypeScript 6, Vite 8, Tailwind CSS 4 |
| Database | MySQL 8.4.11 on `127.0.0.1:3307` |
| Queue/cache/session | Database drivers |
| Tests | PHPUnit 11.5 |
| Static analysis | Larastan/PHPStan level 6 |
| Formatting/linting | Pint, oxlint |

The Windows development environment does not use Docker, WSL, Redis, or Memurai.

## Repository layout

```text
Agent_Millow/
├── backend/                 Laravel 12 API
├── frontend/                React/TypeScript console
├── deploy/                  Reviewed production configuration examples
├── scripts/
│   ├── dev-db.ps1           Isolated MySQL 8.4 lifecycle
│   ├── sync-env.ps1         Sync generated DB credentials into backend/.env
│   └── production-smoke-test.sh
├── docs/
│   ├── architecture.md
│   ├── deployment.md
│   ├── observability.md
│   ├── evaluation.md
│   └── decisions/
├── .github/workflows/       CI configuration (not executed locally)
├── .runtime/                Gitignored MySQL data, logs, and credentials
├── AGENTS.md
└── README.md
```

## Local safety model

The machine already has XAMPP MariaDB 10.4.32 listening on `127.0.0.1:3306` with
existing data in `C:\xampp\mysql\data`. **Do not point this project at port 3306, do not
modify that data directory, and do not stop or reset that service.**

Agent Millow uses a separate MySQL 8.4.11 instance:

- Host: `127.0.0.1`
- Port: `3307`
- Data: `.runtime/mysql/data`
- Logs: `.runtime/mysql/logs`
- App user: `agent_millow`
- Databases: `agent_millow_dev`, `agent_millow_test`
- Credentials: `.runtime/mysql/app.cnf` and `.runtime/mysql/root.cnf` (gitignored)

The application refuses to boot in `local` or `testing` unless the database is the
isolated MySQL connection and the database name matches the expected `agent_millow_*`
pattern.

## Prerequisites

- PHP 8.2.4 at `E:\xampp\php\php.exe` (production should use PHP 8.3 or 8.4).
- Composer 2.10.3 or newer.
- Node.js 22.14+ and npm 10.9+.
- MySQL 8.4 binaries at `C:\Program Files\MySQL\MySQL Server 8.4`.
- Windows PowerShell 5.1+.

## Fresh setup

The current workspace is already initialized. These steps are for a fresh clone or a
new machine.

```powershell
composer install --working-dir=backend
Copy-Item backend\.env.example backend\.env
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\dev-db.ps1 init
powershell -NoProfile -ExecutionPolicy Bypass -File scripts\sync-env.ps1
php backend\artisan key:generate
php backend\artisan migrate
npm --prefix frontend install
```

`dev-db.ps1 init` refuses to overwrite an existing non-empty data directory. It creates
the databases and least-privilege user, and writes the generated credentials under
`.runtime/mysql/`.

## Daily development

Use separate terminals:

```powershell
npm run db:start
npm run backend:serve
npm run backend:queue
npm run frontend:dev
```

Open `http://localhost:5173`. The API runs at `http://localhost:8000`, and Vite proxies
`/api`, `/sanctum`, and `/storage` to it.

The queue worker is only needed when queued jobs exist. The health page works without
it.

Stop the local database when finished:

```powershell
npm run db:stop
```

## Verification

```powershell
npm run db:status
npm run backend:check
npm run frontend:check
npm run eval:offline
npm run check
bash -n scripts/production-smoke-test.sh
```

`backend:check` runs Pint, PHPStan/Larastan, PHPUnit, and the offline evaluation. It
requires the local MySQL instance to be running for the PHPUnit feature suite. The
evaluation itself uses in-memory SQLite, the fake provider, and no network.
`frontend:check` runs oxlint and TypeScript. See
[Offline evaluation](docs/evaluation.md) for baseline rules and limitations. The
deployment templates and smoke test are not production-verified on Windows; see
[Deployment preparation](docs/deployment.md) for the Linux validation and approval
requirements.

### API endpoints

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `GET` | `/api/v1/health` | Public | Readiness: database, cache, and queue checks |
| `GET` | `/up` | Public | Liveness: minimal framework check without dependencies |
| `GET` | `/sanctum/csrf-cookie` | Public | Sanctum SPA CSRF cookie |
| `POST` | `/api/v1/auth/register` | Public | Register a user (throttled) |
| `POST` | `/api/v1/auth/login` | Public | Log in; returns a token when `issue_token` is true |
| `POST` | `/api/v1/auth/logout` | Sanctum | Revoke the current token/session |
| `GET` | `/api/v1/auth/user` | Sanctum | Current user with active token count |
| `GET` | `/api/v1/me` | Sanctum | Current user profile |
| `PATCH` | `/api/v1/me` | Sanctum | Update name/email |
| `PUT` | `/api/v1/me/password` | Sanctum | Change password |
| `DELETE` | `/api/v1/me` | Sanctum | Delete the account |
| `GET` | `/api/v1/providers` | Sanctum | List providers with readiness metadata |
| `GET`/`POST` | `/api/v1/conversations` | Sanctum | List/create conversations |
| `GET`/`PATCH`/`DELETE` | `/api/v1/conversations/{id}` | Sanctum | Show/update/delete a conversation |
| `GET`/`POST` | `/api/v1/conversations/{id}/messages` | Sanctum | List messages / send a message (triggers generation by default) |
| `DELETE` | `/api/v1/conversations/{id}/messages/{message}` | Sanctum | Delete a message |
| `GET` | `/api/v1/conversations/{id}/stream` | Sanctum | Stream an assistant reply via SSE |
| `GET`/`POST` | `/api/v1/memories` | Sanctum | List/create memories |
| `GET`/`PATCH`/`DELETE` | `/api/v1/memories/{id}` | Sanctum | Inspect/update/delete a memory |
| `GET` | `/api/v1/tools` | Sanctum | List available tools |
| `POST` | `/api/v1/tools/call` | Sanctum | Propose and execute a tool call |
| `GET` | `/api/v1/tools/calls/{id}` | Sanctum | Inspect a tool call |
| `POST` | `/api/v1/tools/calls/{id}/approve` | Sanctum | Approve a pending tool call |
| `POST` | `/api/v1/tools/calls/{id}/reject` | Sanctum | Reject a pending tool call |
| `GET` | `/api/v1/audit` | Sanctum | User-scoped, paginated audit history (read-only) |

Readiness returns `200` only when every dependency check passes and `503` when
degraded. Database names and versions are included only in `local` and `testing`;
production exposes status, driver, and queue/cache connection names. See
[Observability](docs/observability.md) for log shape, events, alerts, and
troubleshooting.

Authentication accepts either a Sanctum SPA session cookie (for the browser console) or
a personal access token in the `Authorization: Bearer` header (for scripts and clients).
Registration enablement, a private-beta email allowlist, token lifetime, and rate limits
are configured in `backend/config/agent_millow.php` and their environment variables.

## Database commands

```powershell
npm run db:init      # One-time initialization; refuses to overwrite existing data
npm run db:start     # Idempotent start on port 3307
npm run db:stop      # Graceful shutdown
npm run db:status    # Version, port, data directory, visible databases
npm run env:sync     # Re-sync generated app credentials into backend/.env
```

If credentials are rotated or `.env` is recreated, run `npm run env:sync` and then
`php backend\artisan config:clear`.

The test database is disposable. `RefreshDatabase` may reset it. Never point the test
configuration at `agent_millow_dev`, another project database, or MariaDB on 3306. The
safety guard enforces this for local and testing environments.

## Testing and quality

```powershell
composer --working-dir=backend run test
composer --working-dir=backend run lint
composer --working-dir=backend run analyse
composer --working-dir=backend run format
npm --prefix frontend run check
npm --prefix frontend run build
```

Backend code is formatted with Pint and analyzed at PHPStan level 6. New PHP files
should use strict types where practical. Frontend code is typed and linted with oxlint.

## Deployment preparation

The supported production baseline is Linux with Nginx, PHP-FPM 8.3/8.4, MySQL 8.4,
Supervisor-managed queue workers, and one Laravel scheduler. Configuration examples and
the operator runbooks are in [Deployment preparation](docs/deployment.md). The reviewed
templates live under `deploy/`.

Nothing has been deployed. Before any public deployment, complete the checklist in the
deployment guide, including Linux validation, TLS/DNS, production MySQL and backups,
real-provider testing, secret management, monitoring, and a manual browser login check
over HTTPS. Local checks alone do not establish production readiness.

Key production rules:

- Use PHP 8.3 or 8.4; PHP 8.2 is near end of security support.
- Use HTTPS, secure cookies, an explicit CORS/Sanctum host, and HSTS only after
  verification.
- Use MySQL 8.4 with least-privilege credentials, backups, and TLS where required.
- Run queue workers under Supervisor and exactly one scheduler.
- Ship structured logs to an approved encrypted collector; keep `.env`, `.runtime`,
  provider keys, and database credentials out of version control and release artifacts.
- Review rate limits, request limits, tool quotas, retention, approval policy, and log
  redaction before exposing agent tools.
- Never run destructive restore, rollback, or pruning commands during a verification
  exercise.

## Documentation

- [Architecture](docs/architecture.md)
- [Deployment preparation](docs/deployment.md)
- [Observability](docs/observability.md)
- [Offline evaluation](docs/evaluation.md)
- [ADR-0001: Local environment](docs/decisions/0001-local-environment.md)
- [ADR-0008: Offline evaluation](docs/decisions/0008-offline-evaluation.md)
- [ADR-0009: Production deployment target](docs/decisions/0009-production-deployment.md)
- [Agent operating rules](AGENTS.md)

## Troubleshooting

- **Database connection refused:** run `npm run db:status`; start with `npm run db:start`.
- **MySQL will not start:** inspect `.runtime/mysql/logs/error.log`.
- **Wrong database or guard exception:** run `npm run env:sync` and
  `php backend\artisan config:clear`.
- **Port conflicts:** Agent Millow uses 3307, 8000, and 5173. The existing XAMPP
  services use 3306, 80, and 443.
- **PowerShell blocks scripts:** invoke with
  `-NoProfile -ExecutionPolicy Bypass -File ...`, as shown above.
- **Readiness is degraded:** call `GET /api/v1/health`; local/testing responses identify
  the failing dependency. Check `storage/logs/laravel.json.log` (or the configured
  channel) and use the `X-Request-Id` header to correlate records.
- **Logs are not structured:** ensure `LOG_CHANNEL=json` and run
  `php backend\artisan config:clear` after changing environment variables.
- **A secret may have reached a log:** rotate the affected credential and follow the
  incident guidance in [Observability](docs/observability.md).
