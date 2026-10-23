# Agent Millow: Agent Operating Rules

This file is the working agreement for coding agents and contributors. Read it before
changing code. The product is being built phase by phase; do not jump ahead.

## Current phase

Phases 0-5 are approved. Phase 6 (hardening, observability, evaluation, deployment) is in
progress: Workstream A (hardening) and Workstream B (observability) are implemented and
provisionally approved; Workstream C (offline evaluation) is approved; Workstream D
(deployment preparation) is implemented and awaiting review. No public deployment has
been performed. Do not execute deployment runbooks, connect to production, or start
staging/production activation until the user approves Workstream D. Do not jump ahead.

## Safety rules

1. **Never touch the existing MariaDB instance on port 3306.** It is XAMPP MariaDB
   10.4.32 with data in `C:\xampp\mysql\data`. Do not stop it, migrate it, reset it,
   delete it, or point this project at it.
2. Agent Millow uses isolated MySQL 8.4.11 on `127.0.0.1:3307`, data in
   `.runtime/mysql/data`, app user `agent_millow`, databases `agent_millow_dev` and
   `agent_millow_test`.
3. Do not run `migrate:fresh`, `db:wipe`, `DROP DATABASE`, or equivalent destructive
   operations against `agent_millow_dev` without explicit user approval. The test
   database is disposable, but only the test database.
4. Never commit `.env`, `.runtime`, credentials, API keys, tokens, private keys, or
   database dumps. Never print secrets in logs or user-facing errors.
5. The LLM must never receive direct database, shell, filesystem, or network access.
   Every tool call must pass through Laravel validation, authorization, risk policy,
   approval (when required), execution limits, redaction, and audit.
6. Sensitive actions require explicit user approval. Do not add a tool that can mutate
   data, spend money, send messages, or access private files without an approval path.
7. Ask before any critical decision or destructive operation. If a task conflicts with
   the approved architecture, stop and ask.
8. Keep the offline evaluation offline: no real provider credentials, external services,
   or production data. Never update a baseline from a failing report, and do not weaken
   checks or thresholds to make the gate pass.
9. Do not deploy publicly, connect to a production server, configure a real domain, or
   execute backup/restore/rollback/prune commands without explicit user approval.
10. Do not put secrets in deployment templates, Nginx, PHP-FPM, Supervisor, cron, CI, or
    smoke-test output. Production secrets must remain outside version control.

## Environment

- PHP: `E:\xampp\php\php.exe` (8.2.4 for Phase 0).
- Composer: 2.10.3+.
- Node: 22.14+, npm 10.9+.
- Laravel: 12.x only. Do not upgrade to Laravel 13 until PHP is upgraded and the user
  approves.
- MySQL: 8.4.11 at `C:\Program Files\MySQL\MySQL Server 8.4`.
- Frontend: React 19, TypeScript, Vite 8, Tailwind CSS 4.
- Queue, cache, and sessions use the database. No Redis, Docker, or WSL in the current
  environment.

## Commands

```powershell
npm run db:start
npm run db:stop
npm run db:status
npm run env:sync
npm run backend:serve
npm run backend:queue
npm run frontend:dev
npm run backend:check
npm run frontend:check
npm run eval:offline
npm run check
bash -n scripts/production-smoke-test.sh
```

Backend-only commands:

```powershell
composer --working-dir=backend run test
composer --working-dir=backend run lint
composer --working-dir=backend run analyse
composer --working-dir=backend run eval
composer --working-dir=backend run format
php backend\artisan test --filter=HealthTest
```

Run the relevant checks after every change. Fix errors you introduce. Do not mark work
complete if Pint, PHPStan, PHPUnit, oxlint, or TypeScript is failing.

## Architecture invariants

- Modular monolith, not microservices. Keep modules cohesive and boundaries explicit.
- Laravel is the authorization and execution authority. The LLM only proposes actions.
- Use form requests, policies/gates, DTOs, and service classes. Keep controllers thin.
- API routes live under `/api/v1`; API errors are JSON and carry a request ID.
- Heavy or external work runs on queued jobs with retries, timeouts, and idempotency.
- Provider integrations implement a common interface. Provider keys stay server-side.
- Memory is user-scoped, inspectable, editable, deletable, and auditable. Do not claim
  that a model permanently learns from memory.
- Audit events must be append-only in normal application flows and must not contain
  secrets or unnecessary personal data.
- Prefer server-side pagination, validation, rate limits, and least-privilege database
  access.
- Production is Linux with Nginx, PHP-FPM 8.3/8.4, MySQL 8.4, Supervisor queue workers,
  and one scheduler. Deployment examples and runbooks are preparation only until
  validated on a real host.

## Coding standards

- PHP: strict types in new files where practical, typed properties/returns, `final` for
  services and controllers, Laravel conventions, Pint formatting.
- PHPStan/Larastan level 6 must stay clean.
- TypeScript: no `any`, explicit API types, functional React components, hooks rules,
  oxlint and TypeScript must pass.
- Tailwind CSS 4 is configured through the Vite plugin.
- Do not add a dependency unless it is justified, maintained, and compatible with PHP
  8.2 / Laravel 12 / Node 22.
- Do not add comments that merely restate the code.
- Keep secrets out of source and out of committed configuration.

## Database rules

- Local app database: `agent_millow_dev`.
- Test database: `agent_millow_test` only.
- The application guard rejects local/testing boots when the database is not the isolated
  MySQL connection or the name does not match the expected pattern.
- `.runtime/mysql/app.cnf` is the source of generated app credentials. Use
  `npm run env:sync` to update `backend/.env`; do not copy passwords into git.
- The existing MariaDB on 3306 is out of scope for all Agent Millow work.

## Workflow

1. Read `README.md`, this file, and the relevant architecture/decision docs.
2. Work only on the approved phase.
3. Make the smallest coherent change that satisfies the phase.
4. Add or update tests for behavior you change.
5. Run formatting, static analysis, tests, lint, and build.
6. Update docs or an ADR when a decision changes.
7. Report what changed, commands run, results, and remaining risks.
8. Do not commit, amend, push, or open a PR unless the user explicitly asks.

## Documentation

- `README.md`: setup, commands, safety, production notes.
- `docs/architecture.md`: approved system design and phase roadmap.
- `docs/observability.md`: log schema, events, alerts, dashboards, troubleshooting.
- `docs/evaluation.md`: offline evaluation scenarios, baselines, and limitations.
- `docs/deployment.md`: production architecture, templates, runbooks, and limitations.
- `docs/decisions/`: architecture decision records.
