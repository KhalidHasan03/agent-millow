# ADR-0001: Local development environment

- Status: Accepted
- Date: 2026-10-23
- Phase: 0

## Context

The development machine is Windows and already runs XAMPP:

- Apache 2.4.58 on ports 80/443.
- MariaDB 10.4.32 on `127.0.0.1:3306`, PID 19140, data in `C:\xampp\mysql\data`,
  around 50 pre-existing databases, `root` without a password.

That MariaDB instance is out of scope for Agent Millow and must not be modified.

The project needs a modern database for a Laravel 12 application, an isolated place to
test, and a local workflow that does not require Docker, WSL, or Redis-only features.
Machine resources: 16 GB RAM, 8 logical cores, roughly 64 GB free on `E:`.

Verified tooling:

- PHP 8.2.4 at `E:\xampp\php\php.exe` (extensions include `pdo_mysql`, `mysqli`,
  `mbstring`, `openssl`, `curl`, `fileinfo`, `intl`, `zip`, `gd`, `bcmath`, `dom`, `xml`;
  missing `redis`, `sodium`, `pcntl`, `posix`).
- Composer 2.10.2, updated to 2.10.3 during Phase 0.
- Node 22.14.0, npm 10.9.2.
- MySQL 8.4.11 binaries at `C:\Program Files\MySQL\MySQL Server 8.4` (initially
  unconfigured: no service, no data directory, no config).
- Docker, WSL, Redis, and Memurai are not installed.

## Decision

1. Use an isolated MySQL 8.4.11 instance for Agent Millow instead of the existing
   MariaDB. It runs on `127.0.0.1:3307`, binds to loopback only, disables the X plugin
   (`--mysqlx=0`), stores data in `.runtime/mysql/data`, and defaults to UTC.
2. Create only two databases: `agent_millow_dev` and `agent_millow_test`.
3. Use a dedicated least-privilege application user `agent_millow` granted only on those
   databases; never use `root` for application traffic.
4. Manage the instance with `scripts/dev-db.ps1` (`init`, `start`, `stop`, `status`).
   Initialization refuses to overwrite a non-empty data directory.
5. Keep generated credentials in `.runtime/mysql/app.cnf` and `root.cnf` (gitignored);
   `scripts/sync-env.ps1` writes them into `backend/.env`.
6. Add an application guard that refuses to boot in `local` or `testing` unless the
   connection is MySQL and the database name matches the Agent Millow pattern.
7. Use the database driver for queue, cache, and sessions. Do not introduce Redis,
   Docker, or WSL in this environment.
8. Pin Laravel to 12.x and use PHP 8.2.4 for Phase 0. PHPUnit 11.5 ships with the
   Laravel 12 skeleton; Pest 4 and Laravel 13 require PHP 8.3+.

## Alternatives considered

- **Use XAMPP MariaDB 10.4.32.** Rejected. It holds unrelated existing data, is a
  production-like local service for other projects, and using it risks accidental damage.
- **Install MySQL as a Windows service.** Rejected. A service changes machine-wide state
  and runs outside the project; a project-scoped instance is easier to isolate and remove.
- **Use Docker or WSL.** Rejected. Not installed, requires elevated setup, and adds
  resource overhead on a 16 GB machine.
- **Use SQLite for development.** Rejected. It would not exercise MySQL 8.4 behavior,
  JSON functions, or the production-like connection that the app targets.
- **Use Redis or Memurai for queue/cache.** Rejected for Phase 0. Database drivers are
  sufficient at this scale and avoid another service. Revisit if throughput requires it.
- **Upgrade PHP to 8.3/8.4 now.** Deferred. PHP 8.2.4 satisfies Laravel 12 and keeps the
  current XAMPP stack stable; production should use 8.3/8.4.

## Consequences

Positive:

- The existing MariaDB instance and its data are untouched.
- Development and tests are isolated from other projects.
- The test database can be reset safely; the development database cannot be reset
  accidentally because of explicit destructive-operation rules and the boot guard.
- No additional system services or elevated installs are required.

Negative and mitigations:

- PHP 8.2.4 is near end of security support. Mitigation: plan a PHP 8.3/8.4 upgrade
  before production; keep the code Laravel-12 and PHP-8.2 compatible meanwhile.
- The dev database instance must be started manually. Mitigation: `npm run db:start` is
  idempotent and `npm run db:status` reports state.
- No Redis means queue throughput is limited. Mitigation: acceptable at Phase 0 scale;
  revisit when job volume grows.
- The MySQL 8.4 binaries must remain installed. Mitigation: documented in the README and
  prerequisites.

## Verification

- `npm run db:status` reports MySQL 8.4.11 on port 3307 and the two databases.
- `php backend\artisan migrate` succeeds against `agent_millow_dev`.
- `php backend\artisan test` passes against `agent_millow_test`.
- MariaDB on 3306 remains running with the same database list as before Phase 0.
