# Offline Evaluation

Agent Millow includes a deterministic offline evaluation suite for the safety-critical
boundaries: provider normalization, streaming, tool selection and risk, approval
enforcement, memory isolation, cross-user access, prompt-injection resistance, and
secret redaction.

The suite is a regression gate, not a benchmark of model quality. It uses the fake
provider and seeded evaluation tools, never real credentials, and never calls a network
service.

## Running

```powershell
composer --working-dir=backend run eval
```

or:

```powershell
npm run eval:offline
```

Both run:

```powershell
php backend\artisan eval:offline
```

The local PHP build includes `pdo_sqlite`, which is required for the in-memory
evaluation database. The default report is written to `backend/storage/app/evaluations/offline-latest.json`
and is compared with `backend/evaluation/baselines/offline-v1.json`. The command exits
nonzero when the report fails a scenario, check, threshold, or baseline contract.

Options:

| Option | Purpose |
| --- | --- |
| `--baseline=PATH` | Use another baseline JSON file |
| `--report=PATH` | Write the report to another path |
| `--update-baseline` | Rewrite the baseline from a fully passing report |
| `--json` | Emit the machine-readable payload instead of human-readable output |

The suite also runs as the final step of `composer --working-dir=backend run check`, so
`npm run check` runs backend lint, static analysis, PHPUnit, offline evaluation, and the
frontend checks.

## Offline boundary

`OfflineEvaluationRunner` temporarily:

- Switches the default database to the `evaluation` SQLite connection with
  `:memory:` storage.
- Switches cache to the `array` store and queue to `sync`.
- Runs migrations and seeds only the `echo` and evaluation-only `eval_guarded` tools.
- Enables Laravel HTTP client stray-request prevention.
- Runs every scenario in a transaction that is rolled back, then clears the tool
  registry cache.
- Restores the original database, cache, queue, and HTTP-client settings.

The application MySQL databases are not used by the runner. No evaluation tool has an
executor for mutating external state, and the provider adapter is `FakeProvider`.

## Scenarios

| Scenario | What it proves |
| --- | --- |
| `provider-normalization` | The fake adapter, messages, usage, metadata, and chat request are normalized deterministically. |
| `provider-streaming` | Streaming deltas are split, concatenated, completed, and carry usage and finish reason without errors. |
| `tool-selection-risk` | Known, high-risk, and unknown tools are classified correctly; schema validation and role-based approval limits hold. |
| `approval-enforcement` | High-risk calls stop before execution, create a pending approval, reject cross-user approval, and execute only after owner approval. |
| `memory-isolation` | Memory lists, inspection, and policies are scoped to the owner. |
| `cross-user-access` | Conversation, message, and tool-call access is denied across users and lists are scoped. |
| `prompt-injection-resistance` | Injected text produces only a normal assistant reply; unknown and high-risk tool suggestions do not bypass validation or approval. |
| `secret-redaction` | Log, tool, and audit redaction remove credential-shaped values while preserving safe fields. |

The current baseline contains 50 checks across these 8 scenarios. Critical scenarios
and required checks are listed in the baseline; missing or renamed checks fail the
contract.

## Baseline and thresholds

The versioned baseline records:

- `suite_version`.
- Thresholds: `minimum_pass_rate`, `maximum_failed_checks`, `minimum_checks`, and
  `maximum_critical_failures`.
- Critical and required scenario keys.
- Expected check names per scenario.

A report must match the suite version, include every required scenario, run every
expected check, pass those checks, and satisfy all thresholds. Failures include a
reason in the report.

### Updating a baseline

`--update-baseline` is intentionally conservative. It refuses to write when:

- Any scenario or check failed.
- The report does not satisfy the existing baseline contract.
- A scenario produced no checks.

This prevents using an update to silence a regression or remove a critical check.
Adding new checks to a fully passing suite can be committed with an update. Removing or
renaming existing checks, or changing the suite version, requires a deliberate manual
baseline review. Never update a baseline to make a failing report pass.

Production reports must not be committed. The baseline is source-controlled; the report
under `storage/app/evaluations` is generated output.

## Limitations

- The suite runs on in-memory SQLite, not MySQL. It validates application behavior, not
  MySQL-specific constraints, collation, or migration performance.
- It exercises services through the framework directly, not the browser UI, SSE
  transport, queue workers, or external provider APIs.
- `FakeProvider` is deterministic by design. It does not measure real model quality,
  latency, cost, or prompt robustness.
- Stray-request prevention covers Laravel's HTTP client. A future dependency that bypasses
  the client, such as raw cURL or a provider SDK, must be reviewed and blocked separately.
- Evaluation is not a substitute for authorization tests, static analysis, or incident
  response. It is one gate among several.
