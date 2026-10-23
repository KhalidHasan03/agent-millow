# Deployment preparation

Status: **Workstream D preparation only.** Nothing in this repository has been
deployed publicly, connected to a production server, or verified on Linux. This
document describes the supported production architecture and the operating
procedures that must be completed by an authorized operator. Local tests alone
do not establish production readiness.

## 1. Supported architecture

The baseline deployment is a single Linux application host with a separately
managed MySQL 8.4 service:

```text
Internet
   |
   v
Nginx 1.24+ (TLS termination, static SPA, reverse proxy to PHP-FPM)
   |                         |
   |                         +--> /var/www/agent-millow/current/frontend/dist
   |
   +--> PHP-FPM 8.3/8.4 (Laravel API, SSE)
             |
             +--> MySQL 8.4 (application, queue, cache, sessions, audit)
             +--> Supervisor workers (database queue)
             +--> cron (Laravel scheduler)
```

- **Operating system:** Linux x86_64. Ubuntu 24.04 LTS is the reference
  distribution; equivalent distributions are acceptable after review.
- **Web server:** Nginx with TLS.
- **PHP:** PHP-FPM 8.3 or 8.4. The Windows development environment uses PHP
  8.2.4 only for compatibility; it is not the production target.
- **Database:** MySQL 8.4, with least-privilege application credentials,
  encrypted backups, and TLS where the database is not local.
- **Background work:** Supervisor-managed `queue:work` processes and one
  Laravel scheduler (cron). Redis, Docker, and WSL are not part of the baseline.
- **Frontend:** The Vite build is served as static files from the same origin as
  the API. Same-origin hosting keeps Sanctum cookies and CSRF first-party.
- **Secrets:** Injected at deploy time from an approved secret manager or a
  root-owned environment file. Never in Git, Nginx, Supervisor, or cron files.

This baseline is a single application host. A load-balanced deployment requires
a separate review of session affinity, trusted proxies, shared storage, queue
workers, and the scheduler's single-runner guarantee.

## 2. Prerequisites

### Host and network

- A supported Linux release with security updates and a documented patching
  process.
- Inbound TCP 80 for ACME/redirect and TCP 443 for application traffic.
- MySQL accessible only from the application host or a private network.
- Outbound HTTPS for provider APIs, package installation, and approved
  monitoring/log shipping.
- DNS name and certificate plan approved before the first public exposure.
- Time synchronization enabled.
- A dedicated, unprivileged deploy account and `www-data` runtime account.

### Software

- Nginx, PHP-FPM 8.3/8.4 with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`,
  `xml`, `ctype`, `fileinfo`, `curl`, and `sqlite3`/`pdo_sqlite` for
  `eval:offline`.
- Composer 2.10+ on the build host; production should receive installed
  dependencies, not a global Composer requirement.
- MySQL 8.4 client tools for backups and health checks.
- Supervisor 4.x.
- Node 22 and npm 10 only on the build host, not required by the web runtime.
- An approved log collector, uptime monitor, and backup target.

### Decisions to approve before deployment

- Hosting provider, region, and data-residency requirements.
- Production domain and certificate authority.
- MySQL topology, backup retention, encryption, and restore target.
- Provider/model selection, spending limits, and provider data policy.
- Log retention and access controls.
- Who may approve destructive migration, restore, rollback, and pruning steps.

## 3. Environment and secrets

The template is [backend/.env.production.example](../backend/.env.production.example).
Copy it to the shared location `/var/www/agent-millow/shared/backend/.env` on the
production host, replace every placeholder, and link it into each release as described
in section 4.

Required values include:

| Group | Variables | Notes |
| --- | --- | --- |
| Application | `APP_NAME`, `APP_ENV=production`, `APP_KEY`, `APP_URL`, `APP_DEBUG=false` | Generate `APP_KEY` with `php artisan key:generate`; back it up securely. |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Use a dedicated least-privilege user; never the local XAMPP account. |
| Database TLS | `MYSQL_ATTR_SSL_CA` | Optional CA bundle when the database connection uses TLS. |
| Sessions | `SESSION_DRIVER`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE`, `SESSION_DOMAIN`, `SESSION_ENCRYPT` | Cookies must be Secure and HttpOnly; use `lax` for same-origin SPA. |
| Sanctum/CORS | `SANCTUM_STATEFUL_DOMAINS`, `CORS_ALLOWED_ORIGINS`, `SANCTUM_TOKEN_PREFIX` | Hostnames only, no wildcard production origins. |
| Queue/cache | `QUEUE_CONNECTION=database`, `QUEUE_FAILED_DRIVER`, `CACHE_STORE=database`, `DB_QUEUE_RETRY_AFTER` | `DB_QUEUE_RETRY_AFTER` must exceed the worker timeout. |
| Logging | `LOG_CHANNEL=json`, `LOG_LEVEL`, `LOG_DAILY_DAYS` | Ship the JSON stream to an approved, encrypted sink. |
| Providers | `AGENT_MILLOW_DEFAULT_PROVIDER`, `AGENT_MILLOW_ENABLED_PROVIDERS`, `AGENT_MILLOW_OPENAI_KEY`, `AGENT_MILLOW_OPENAI_MODEL`, `AGENT_MILLOW_OPENAI_ALLOWED_MODELS`, `AGENT_MILLOW_OPENAI_MAX_OUTPUT_TOKENS`, `AGENT_MILLOW_PROVIDER_TIMEOUT`, `AGENT_MILLOW_PROVIDER_CONNECT_TIMEOUT`, `AGENT_MILLOW_PROVIDER_STREAM_TIMEOUT`, retry count | Keep the provider key server-side and rotate it through the secret manager. The initial allowed/default model is `gpt-4o-mini`; change both model settings together. |
| Provider cost estimates | `AGENT_MILLOW_OPENAI_INPUT_COST_PER_MILLION`, `AGENT_MILLOW_OPENAI_OUTPUT_COST_PER_MILLION` | Leave blank until current prices are verified. Blank prices keep `provider_usage.estimated_cost` null. Estimates are not billing records. |
| Hardening | `AGENT_MILLOW_REQUEST_MAX_BYTES`, rate limits, tool quotas, retention, `AGENT_MILLOW_TRUSTED_PROXIES` | Keep defaults unless load testing justifies a change. |
| HSTS | `AGENT_MILLOW_HSTS_ENABLED`, `AGENT_MILLOW_HSTS_MAX_AGE` | Enable only after HTTPS and redirects are verified. |
| Provisioning | `AGENT_MILLOW_REGISTRATION_ENABLED=false`, `AGENT_MILLOW_SEED_ADMIN_*` | Seed one administrator, then clear the seed password. |

Secret handling rules:

- Keep the filled `.env` outside the release directory, owned by the deploy
  account or root, readable by `www-data` only where necessary, and mode `0640`.
- Prefer secret-manager injection at service start over a file where the
  platform supports it. If using a file, ensure it is not copied into releases
  or backups that are not encrypted.
- Do not put credentials in Nginx, PHP-FPM, Supervisor, cron, Docker, or CI
  configuration.
- Rotate `APP_KEY` only with a reviewed `APP_PREVIOUS_KEYS` migration plan;
  changing it invalidates encrypted sessions and stored encrypted values.
- Rotate provider, database, mail, and seed credentials after any suspected
  disclosure. Revoke exposed tokens immediately.
- Never print a filled environment file, token, password, or provider key in a
  ticket, chat, or log.

## 4. Release layout and permissions

Use immutable releases plus a `current` symlink:

```text
/var/www/agent-millow/
├── releases/
│   └── 20261023T120000Z/
│       ├── backend/
│       └── frontend/dist/
├── shared/
│   ├── backend/.env
│   ├── backend/storage/
│   └── backend/bootstrap/cache/
└── current -> releases/20261023T120000Z
```

Link the shared `.env`, `backend/storage`, and `backend/bootstrap/cache` into each
release instead of copying them. The application reads those paths, so the links must
exist before caches are warmed. This keeps secrets and mutable files out of immutable
release directories.

The following are host-mutating examples, not commands executed during this
preparation:

```bash
# OPERATOR APPROVAL REQUIRED. Create runtime directories.
sudo install -d -o deploy -g www-data -m 0750 /var/www/agent-millow/releases
sudo install -d -o deploy -g www-data -m 0750 /var/www/agent-millow/shared
sudo install -d -o deploy -g www-data -m 0750 /var/www/agent-millow/shared/backend
sudo install -d -o www-data -g www-data -m 0750 /var/www/agent-millow/shared/backend/storage
sudo install -d -o www-data -g www-data -m 0750 /var/www/agent-millow/shared/backend/bootstrap/cache
sudo install -d -o www-data -g www-data -m 0750 /var/log/agent-millow

# Runtime writable paths only.
sudo chown -R www-data:www-data /var/www/agent-millow/shared/backend/storage
sudo chown -R www-data:www-data /var/www/agent-millow/shared/backend/bootstrap/cache
```

For every new release, link the shared paths before warming caches:

```bash
# OPERATOR APPROVAL REQUIRED. Run inside the staged release.
rm -rf backend/storage backend/bootstrap/cache
ln -s /var/www/agent-millow/shared/backend/storage backend/storage
ln -s /var/www/agent-millow/shared/backend/bootstrap/cache backend/bootstrap/cache
ln -s /var/www/agent-millow/shared/backend/.env backend/.env
```

Do not replace the live `current` symlink until these links and permissions are
verified. The `rm` targets are only the staged release paths; verify
`$PWD` and the release ID before running it.

- Release directories should be owned by the deploy account, group `www-data`,
  and readable/traversable by `www-data` (for example `0750`); files should not be
  world-writable. Application code and `frontend/dist` are read-only to
  `www-data`.
- `backend/storage` and `backend/bootstrap/cache` must be writable by
  `www-data`; do not make the whole release writable.
- `backend/.env` must not be world-readable.
- Shared storage must survive release switches. Do not put `.env`, logs,
  sessions, or generated evaluation reports inside a release that will be
  deleted during rollback.
- Keep at least the current and previous release. Apply a documented retention
  policy to old releases and backups.

## 5. Reviewed configuration examples

| Example | Install target | Purpose |
| --- | --- | --- |
| [deploy/nginx/agent-millow.conf](../deploy/nginx/agent-millow.conf) | `/etc/nginx/sites-available/agent-millow.conf` | TLS server, SPA static hosting, API FastCGI, SSE timeout. |
| [deploy/nginx/fastcgi-agent-millow.conf](../deploy/nginx/fastcgi-agent-millow.conf) | `/etc/nginx/snippets/agent-millow-fastcgi.conf` | Shared FastCGI parameters and socket. |
| [deploy/php-fpm/agent-millow.conf](../deploy/php-fpm/agent-millow.conf) | `/etc/php/8.4/fpm/pool.d/agent-millow.conf` | Dedicated PHP-FPM pool. |
| [deploy/php/99-agent-millow.ini](../deploy/php/99-agent-millow.ini) | `/etc/php/8.4/fpm/conf.d/99-agent-millow.ini` | Production PHP/OPcache baseline. |
| [deploy/supervisor/agent-millow-worker.conf](../deploy/supervisor/agent-millow-worker.conf) | `/etc/supervisor/conf.d/agent-millow-worker.conf` | Queue workers. |
| [deploy/cron/agent-millow-scheduler](../deploy/cron/agent-millow-scheduler) | `/etc/cron.d/agent-millow` | Laravel scheduler. |

Review all placeholders before installing. In particular, replace
`app.example.com`, certificate paths, `/var/www/agent-millow`, PHP version
paths, worker counts, and log paths.

### Nginx, TLS, and static assets

- Serve the SPA from `current/frontend/dist` and fall back to `index.html` for
  client-side routes.
- Keep the API and SPA on the same origin. The Nginx example sends `/api/` and
  `/sanctum/` to PHP-FPM.
- The SSE location disables Nginx buffering and allows a longer read timeout.
  Normal API requests keep a shorter timeout.
- `client_max_body_size 1m` is an edge guard. Laravel independently enforces
  `AGENT_MILLOW_REQUEST_MAX_BYTES`; keep both aligned.
- Static assets use long expiry; `index.html` uses `expires -1` so releases are
  picked up.
- Static responses receive the Nginx security headers; Laravel also sets its
  own security headers on API responses. Keep duplicate values consistent and
  do not add conflicting header rules in locations. Nginx `add_header`
  inheritance is intentionally kept simple.
- Obtain a certificate through the approved ACME/client process. Keep the
  private key root-readable only and reload Nginx after renewal.
- HSTS remains commented in the example. Enable it only after confirming the
  certificate chain, HTTP-to-HTTPS redirect, SPA, API, and cookies over HTTPS.
  If Nginx owns HSTS, leave `AGENT_MILLOW_HSTS_ENABLED=false` to avoid duplicate
  headers. If Nginx does not set it, enable the application setting and verify
  secure requests only.
- Do not trust arbitrary `X-Forwarded-*` headers. Set
  `AGENT_MILLOW_TRUSTED_PROXIES` only to the actual load balancer/CDN ranges.
  With direct Nginx-to-PHP-FPM traffic, leave it empty.

Validate configuration on the host after installation and before reload. These
commands require operator approval because they inspect/restart production
services:

```bash
sudo nginx -t
sudo php-fpm8.4 -t
sudo supervisorctl reread
sudo supervisorctl update
```

### PHP-FPM and OPcache

- Use a dedicated socket and pool. Do not reuse a shared global pool.
- Tune `pm.max_children` to available RAM, allowing memory for MySQL, Nginx,
  workers, and the OS.
- `opcache.validate_timestamps=0` improves performance but requires a PHP-FPM
  reload on every release.
- `post_max_size` and `upload_max_filesize` are set above the edge limit so
  Laravel, not PHP, returns the validation error for an oversized JSON request.
- `clear_env=yes` keeps the pool isolated. Laravel reads `backend/.env`;
  secret-manager injection must be reviewed before changing this setting.

### Queue workers and scheduler

- Run workers as `www-data` from `current/backend`.
- The example listens to `provider,default`. Assistant generation jobs are
  dispatched to `provider`.
- Worker `--timeout=75` is below the database queue `retry_after=90`. Keep that
  relationship when tuning either value.
- `GenerateAssistantReply` has job-level retries and backoff. Do not add an
  unbounded retry loop.
- Keep `numprocs` conservative and monitor database queue depth. More workers
  increase provider concurrency and cost.
- Install exactly one scheduler. The cron example uses `flock` to prevent
  overlap. Do not also run `schedule:work` on the same host.
- `maintenance:prune --force` is scheduled only in `production` and is
  destructive. Review retention values before enabling the scheduler.

### Frontend build

Build the SPA on the build host:

```powershell
npm --prefix frontend ci
npm --prefix frontend run check
npm --prefix frontend run build
```

Copy `frontend/dist` into the release. Do not ship `node_modules`.
`frontend/src/lib/api.ts` uses same-origin `/api/v1`; keep the API on the same
hostname. No provider key or server secret may be exposed through a `VITE_*`
variable.

## 6. Release runbook

Every destructive step is marked **OPERATOR APPROVAL REQUIRED**. Do not run these
steps as part of local development or automated evaluation.

### Preflight

1. Confirm the Workstream D review is approved and a change window is recorded.
2. Confirm a verified database backup exists and a restore target is available.
3. Run the full quality gate on the release commit: `npm run check`,
   `npm run frontend:build`, and the offline evaluation.
4. Confirm the production environment file, provider keys, certificate, DNS,
   and monitoring are in place.
5. Confirm no release contains `.env`, `.runtime`, logs, evaluation reports,
   dumps, or credentials.
6. Confirm database migrations have been reviewed and tested on staging.

### Build and stage

1. Create a new immutable release directory.
2. Copy `backend` without `vendor`, `.env`, storage logs, or local artifacts.
3. Install production dependencies with Composer using the lock file:
   `composer install --working-dir=backend --no-dev --no-interaction --prefer-dist --optimize-autoloader`.
4. Copy the already-built `frontend/dist`.
5. Copy deploy configuration examples to the host's config directories and
   replace placeholders. Do not copy production secrets into the release.
6. Point `backend/storage` at the shared storage path.
7. Set ownership and permissions as described in section 4.

### Activate

Run commands that write `storage` or `bootstrap/cache` as `www-data` (or as a
deploy account with equivalent group permissions). With the ownership in section
4, use `sudo -u www-data /usr/bin/php` from the release directory for migration,
seeding, cache, and queue commands.

1. **OPERATOR APPROVAL REQUIRED:** put the application into a documented
   maintenance window if the migration is not backward compatible.
2. **OPERATOR APPROVAL REQUIRED:** verify the backup and run
   `php artisan migrate --force` from the new release.
3. **OPERATOR APPROVAL REQUIRED:** seed baseline tools if this is a new database:
   `php artisan db:seed --class=ToolSeeder --force`.
4. **OPERATOR APPROVAL REQUIRED:** seed the first administrator once with the seed
   variables set, using `php artisan db:seed --force`, then clear the seed password from
   `.env`. Run this before `config:cache`; the seeder reads the values directly.
5. Clear stale caches before warming:
   `php artisan optimize:clear`.
6. Warm safe caches: `php artisan config:cache` and `php artisan view:cache`.
   Do not add `route:cache` unless the closure route has been removed and the
   command has been verified on staging.
7. Switch the `current` symlink atomically, for example:
   `ln -s /var/www/agent-millow/releases/<release-id> /var/www/agent-millow/current.new`
   followed by `mv -Tf /var/www/agent-millow/current.new /var/www/agent-millow/current`.
   Confirm the target release ID before the move.
8. Reload PHP-FPM so OPcache loads the new release.
9. Restart workers with `php artisan queue:restart` or Supervisor; ensure the
   scheduler cron is still installed and points at `current`.
10. Exit maintenance mode, then run the production smoke test.
11. Confirm readiness, queue processing, audit events, provider behavior, TLS,
    cookies, and monitoring.

### Post-deploy

- Watch readiness and 5xx/4xx rates for at least one full provider/queue cycle.
- Confirm `jobs` is draining and `failed_jobs` is not growing.
- Confirm logs are arriving in the approved sink and redaction is working.
- Record release ID, migration IDs, smoke-test result, operator, and time.
- Keep the previous release and backup until the rollback window closes.

## 7. Rollback runbook

Prefer a forward fix. A rollback must be explicitly approved.

### Code-only rollback

1. **OPERATOR APPROVAL REQUIRED:** stop new deployments and record the reason.
2. Point `current` back to the previous release atomically.
3. Reload PHP-FPM.
4. Run `php artisan queue:restart`; verify workers start from the previous code.
5. Rebuild/clear config caches as needed for the previous release.
6. Run readiness and smoke checks.
7. Confirm queue jobs created by the newer code are compatible with older code.

Do not automatically reverse database migrations. A code rollback with a
forward-only migration may require a forward fix or a database restore.

### Database rollback

- `php artisan migrate:rollback --step=1 --force` is **OPERATOR APPROVAL
  REQUIRED** and only permitted when the migration is reviewed as reversible
  and a backup exists.
- Never use `migrate:fresh`, `db:wipe`, or `DROP DATABASE` in production.
- If a destructive migration has run, restore from the verified backup to a new
  database, validate it, and switch the application under an approved plan.
- Record exactly which migrations are reversed and which application version is
  compatible with the resulting schema.

## 8. Backup and restore runbook

### Backup policy

- Encrypt backups in transit and at rest.
- Store at least one copy off-host in a separate failure domain.
- Back up daily and before every migration/release.
- Test a restore into a disposable database at least quarterly.
- Define and record RPO/RTO targets; do not assume the schedule is sufficient.
- Back up the application `.env`/`APP_KEY` through the secret manager, not by
  putting it in the database dump.
- Audit logs, application logs, and release artifacts need their own retention
  and backup decisions; they are not covered by a database dump alone.

Example backup step, **OPERATOR APPROVAL REQUIRED**:

```bash
umask 077
mysqldump \
  --defaults-extra-file=/etc/agent-millow/backup.cnf \
  --single-transaction --quick --routines --triggers --events \
  --set-gtid-purged=OFF agent_millow_prod \
  | gzip > "/srv/backups/agent-millow-$(date -u +%Y%m%dT%H%M%SZ).sql.gz"
```

Encrypt the resulting file with the approved backup encryption tool before it
leaves the host, and restrict the backup directory to the backup account. The
defaults file must be root-owned, mode `0600`, and excluded from Git and release
artifacts.

### Restore policy

1. **OPERATOR APPROVAL REQUIRED:** declare an incident or approved recovery
   window.
2. Restore to a new database first. Never overwrite the live database during a
   test.
3. Validate schema, row counts, audit continuity, and a read-only application
   boot against the restored database.
4. Switch the application only after validation and a second approval.
5. Run migrations if the restored schema is older than the deployed code.
6. Run readiness and smoke checks, then monitor queue and audit behavior.

Restores are destructive to the target. Do not run a restore, rollback, or
prune merely to verify a deployment.

## 9. Database migration runbook

1. Review the migration and its rollback on staging.
2. Confirm a fresh backup and restore target.
3. Check `php artisan migrate:status` on production.
4. Announce the maintenance window and expected impact.
5. **OPERATOR APPROVAL REQUIRED:** run `php artisan migrate --force`.
6. Verify `migrate:status`, readiness, and critical read/write paths.
7. Restart workers if the code/schema contract changed.
8. Keep the backup until the rollback window closes.

Never run `migrate:fresh`, `db:wipe`, `DROP DATABASE`, or equivalent commands
against the development or production database. The local MariaDB on port 3306
is outside this project and must never be used.

## 10. Monitoring and incident response

### Monitoring

- Uptime monitor: `/up` for liveness; `/api/v1/health` for readiness. Alert on
  two consecutive minutes of non-2xx readiness.
- Alert on any `queue.job_failed_permanently`, sustained `queue.job_failed`,
  provider failure spikes, 5xx spikes, disk pressure, TLS expiry, and backup
  failure.
- Dashboard: request/error rate, readiness, provider failures, queue depth,
  failed jobs, tool approvals/failures, login failures, database size.
- Ship `json` logs to an approved encrypted collector. Correlate with
  `X-Request-Id`; logs must not contain secrets or full user content.
- `docs/observability.md` defines the event and alert schema.
- External monitoring/APM is not configured in this repository.

### Incident response

1. **Detect and record:** timestamp, release ID, affected endpoint, request ID,
   user impact, and current database/queue state.
2. **Triage:** check `/up`, `/api/v1/health`, Nginx/PHP-FPM/MySQL status, recent
   deployments, `failed_jobs`, disk, certificate, and provider status.
3. **Contain:** stop traffic or remove the affected release only with operator
   approval. Do not delete logs, audit rows, or failed jobs to make an alert
   disappear.
4. **Protect secrets:** if a credential may have leaked, rotate it, revoke
   tokens, and preserve non-secret evidence. Never paste the secret into an
   incident ticket.
5. **Recover:** roll back code, restart workers, restore to a new database, or
   fail over according to the approved runbook.
6. **Verify:** readiness, smoke test, queue drain, audit continuity, and
   provider behavior.
7. **Post-incident:** document root cause, timeline, blast radius, corrective
   actions, and whether thresholds or redaction tests need changes.

Emergency framework logging may bypass configured application log channels.
Treat any unexpected log destination as a review item.

## 11. Post-deployment smoke test

The script is [scripts/production-smoke-test.sh](../scripts/production-smoke-test.sh).
It checks:

- liveness `/up`
- readiness database, cache, and queue checks
- bearer-token authentication and user identity
- conversation creation and message append with generation disabled
- memory create, inspect, and delete
- low-risk `echo` tool execution
- approval endpoint rejection for a completed call
- full propose/pending/reject approval flow when a high-risk tool is supplied
- user-scoped audit visibility for the tool execution

Usage on a trusted operator workstation:

```bash
export SMOKE_BASE_URL="https://app.example.com"
export SMOKE_EMAIL="smoke@example.com"
# Set SMOKE_PASSWORD through a secret manager or an ephemeral environment.
export SMOKE_PASSWORD="REPLACE_AT_RUNTIME"
export SMOKE_APPROVAL_TOOL="smoke_guarded"      # optional
export SMOKE_APPROVAL_INPUT='{"message":"smoke"}' # optional, must match schema
chmod +x scripts/production-smoke-test.sh
./scripts/production-smoke-test.sh
```

The script uses the smoke account's bearer token, does not print the token or
password, and stores the token in a mode-600 temporary curl configuration. It
creates and deletes only its own memory and conversation. The tool call and
audit records intentionally remain. It does not call a model provider because
message generation is disabled; enabling real generation would incur provider
cost and is outside this smoke test.

The positive approval path is **skipped** unless `SMOKE_APPROVAL_TOOL` is
provided. A completed low-risk call is always checked to ensure the approval
endpoint returns `409`; this verifies the endpoint and state guard, not a full
high-risk approval. Do not add a permanent high-risk production tool merely to
make this check run.

The API smoke test does not validate the SPA's rendered login state, browser
cookies, CSRF handling, or SSE UI. A manual browser check over the production
TLS origin remains required.

## 12. Local validation and checks that cannot be done here

Local verification covers application tests, static analysis, formatting,
offline evaluation, frontend lint/build, shell syntax, and secret/template
inspection. It cannot prove production behavior.

| Check | Local status | Required before production |
| --- | --- | --- |
| PHPUnit/PHPStan/Pint/oxlint/TypeScript | Run locally | Re-run in CI and on the release artifact |
| Offline evaluation | Run locally with SQLite/fake provider | Still not a real-provider or MySQL evaluation |
| Smoke script syntax | `bash -n` locally | Run against a real staging/production URL |
| Smoke script behavior | Not run | Requires a deployed account and environment |
| Nginx syntax/headers/TLS | Not available on Windows | `nginx -t`, TLS scan, header/cookie checks |
| PHP-FPM pool/OPcache | Not available on Windows | `php-fpm -t`, reload, opcache verification |
| Supervisor worker lifecycle | Not available on Windows | Start/stop/restart and queue-drain test |
| Scheduler/cron | Not available on Windows | One-run verification and overlap check |
| MySQL 8.4 production behavior | Local 8.4 on port 3307 only | Production schema, privileges, TLS, load |
| Backup/restore | Not executed | Restore into a disposable database |
| Queue failure/retry behavior | Unit/feature coverage only | Real worker failure and retry drill |
| HSTS/secure cookies/trusted proxies | Unit-level only | HTTPS browser and proxy-chain verification |
| Log shipping/alerting | Not configured | Collector ingestion and alert drill |
| CI workflow | Added as configuration only | Run in the approved CI system |

## 13. Remaining production blockers

These must be resolved or explicitly accepted before public deployment:

1. **Frontend authentication browser verification.** The auth context normalizes the
   API's `data` envelope, so the previously recorded response-shape mismatch is
   addressed in code. A manual login/logout/refresh check over the production HTTPS
   origin is still required; local build checks do not exercise cookies or CSRF.
2. **PHP version.** Local development uses PHP 8.2.4; production must use a
   supported PHP 8.3/8.4 build and be tested on that version.
3. **Real provider validation.** Offline evaluation uses a fake provider and
   does not prove model quality, latency, spend controls, or provider failure
   behavior.
4. **Production database.** MySQL 8.4 production credentials, TLS, least
   privilege, migrations, backups, and restore drills are not provisioned.
5. **TLS/DNS/domain.** No public domain or certificate has been configured.
6. **Secrets and key custody.** No production secret manager, `APP_KEY` backup,
   or rotation process has been selected.
7. **Configuration runtime validation.** Nginx, PHP-FPM, Supervisor, and cron
   examples have not been executed on Linux.
8. **Monitoring and log shipping.** External monitoring, alert delivery, and
   encrypted log retention are not configured.
9. **End-to-end browser/queue/SSE testing.** API smoke and offline evaluation do
   not replace a browser test, real worker test, or real provider integration.
10. **Operational ownership.** On-call, backup owner, incident commander,
    deployment approver, and maintenance window are not assigned.

## 14. Deployment checklist

Use this as the review checklist; it is not authorization to deploy.

- [ ] Workstream D reviewed and deployment explicitly approved.
- [ ] PHP 8.3/8.4 build tested; Composer lock install reproducible.
- [ ] DNS, TLS certificate, HTTP redirect, and HSTS decision approved.
- [ ] Production MySQL 8.4, least-privilege user, TLS, and backup target ready.
- [ ] `.env.production.example` copied and every placeholder replaced.
- [ ] `APP_KEY` generated and backed up; provider key stored server-side.
- [ ] Registration policy, admin seed, CORS, Sanctum domains, and trusted proxies reviewed.
- [ ] Release directory, shared storage, ownership, and modes verified.
- [ ] Nginx, FastCGI, PHP-FPM, OPcache, Supervisor, and cron configs installed.
- [ ] `nginx -t`, `php-fpm8.4 -t`, Supervisor config test, and service reloads passed.
- [ ] Database backup verified; migration plan and rollback owner approved.
- [ ] `npm run check`, frontend build, and release artifact scan passed.
- [ ] Migrations and baseline tools applied under approval.
- [ ] Config/view caches warmed; PHP-FPM reloaded; workers restarted.
- [ ] Scheduler is running once and only once.
- [ ] Readiness and manual browser login pass over HTTPS.
- [ ] Production smoke test passes, including the optional approval path.
- [ ] Queue drains; failed jobs, audit events, and logs are monitored.
- [ ] HSTS enabled only after verification, through one documented owner.
- [ ] Backup, log shipping, alert delivery, and restore drill completed.
- [ ] Frontend auth flow (login, refresh, logout) verified in a browser over HTTPS.
- [ ] Release record, operator, timestamps, and rollback window recorded.
