# ADR-0009: Production deployment target

- Status: Proposed for Phase 6 Workstream D, pending review
- Date: 2026-10-23

## Context

The application has completed hardening, observability, and offline evaluation, but it
has only run on Windows with an isolated local MySQL 8.4 instance. Deployment
preparation needs a concrete, reviewable production topology, configuration examples,
and operating procedures without deploying or creating external infrastructure.

The application uses database-backed sessions, cache, queue, and audit, so the
deployment model must preserve database durability and single-writer semantics for the
scheduler. The SPA uses Sanctum first-party cookies, so splitting the frontend and API
across unrelated origins would add CORS, cookie, and CSRF complexity.

## Decision

- Use a single Linux application host as the baseline: Nginx, PHP-FPM 8.3/8.4, a
  separately managed MySQL 8.4 database, Supervisor queue workers, and one cron-based
  Laravel scheduler.
- Serve the Vite-built SPA as static files from the same origin as `/api` and
  `/sanctum`.
- Use immutable releases with a `current` symlink, shared storage, a runtime-readable
  but non-world-readable production environment file (or secret-manager injection), and
  a documented rollback path.
- Keep database queue/cache/session drivers. Redis, Docker, and WSL remain out of scope.
- Provide reviewed Nginx, FastCGI, PHP-FPM, PHP/OPcache, Supervisor, and cron
  configuration examples. Do not treat them as deployed or production-verified.
- Treat TLS, HSTS, secure cookies, trusted proxies, backups, migrations, and pruning as
  operator-approved procedures. HSTS is opt-in and enabled only after HTTPS is verified.
- Use an API smoke test for readiness, auth, conversations, memory, tools, approval
  state, and audit. Add a positive approval check only when an existing high-risk tool
  is explicitly supplied.
- Prepare CI configuration for a clean Linux runner, but do not initialize Git, create
  commits, or require external credentials.

## Consequences

- The baseline is straightforward to operate but has a single application-host failure
  domain. High availability requires a separate architecture review.
- Same-origin hosting keeps Sanctum behavior simple; a split deployment must revisit
  CORS, cookies, and CSRF.
- PHP-FPM with OPcache timestamp validation disabled requires a reload on every release.
- The scheduler must run exactly once; adding hosts requires a single-runner mechanism.
- The configuration examples are not proof of production readiness. Linux validation,
  real MySQL, TLS, provider behavior, backups, monitoring, and restore drills remain
  required.
- The smoke test does not replace browser, SSE, queue-worker, or real-provider testing.
- The frontend authentication response-shape mismatch identified during this work was
  subsequently addressed by response normalization in the auth context; browser
  verification over TLS remains required before deployment.
