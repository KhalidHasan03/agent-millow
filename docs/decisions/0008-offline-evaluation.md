# ADR-0008: Offline evaluation

- Status: Approved for Phase 6 Workstream C
- Date: 2026-10-23

## Context

Phase 6 needs a repeatable quality gate before deployment. The most important behaviors
are safety boundaries, not model quality: tool approval, cross-user isolation, risk
classification, prompt-injection resistance, and secret redaction. Those behaviors must
be testable without real provider credentials, external services, or production data.

The normal PHPUnit suite already covers individual endpoints and services, but it does
not produce a versioned, inspectable evaluation report or compare a whole safety suite
against an explicit baseline. Workstream C also must not write to the application
databases or weaken existing tests and static analysis.

## Decision

- Add a deterministic offline evaluation suite behind `eval:offline`.
- Run scenarios against an in-memory SQLite database with the fake provider and two
  seeded tools: the low-risk `echo` tool and the evaluation-only high-risk
  `eval_guarded` tool.
- Default the runner to the `array` cache, `sync` queue, and Laravel HTTP client stray
  request prevention. Restore the original configuration after every run.
- Run each scenario in a rolled-back transaction and clear the tool registry cache
  afterwards.
- Version the baseline in `backend/evaluation/baselines/offline-v1.json`. Record suite
  version, thresholds, critical/required scenarios, and expected checks.
- Write reports to `backend/storage/app/evaluations/`, not to source control.
- Compare reports with the baseline and exit nonzero on any failure. Include the
  comparison reasons in the report.
- Permit baseline updates only from a fully passing report that also satisfies the
  existing baseline contract. Refuse to update on any failure, missing check, or empty
  scenario.
- Run the evaluation as the final backend quality-check step in addition to Pint,
  PHPStan, and PHPUnit.
- Keep evaluation scenarios additive. They exercise application code; they do not
  bypass validation, authorization, approval, or audit.

## Consequences

- The safety suite is reproducible and can run without network access or credentials.
- A failing safety boundary now fails the repository quality gate.
- Baseline updates cannot silently mask a failing check. Renaming or removing checks
  requires a manual baseline review.
- In-memory SQLite means MySQL-specific behavior is still covered by PHPUnit against the
  isolated test database, not by the evaluation suite.
- The fake provider does not evaluate real model quality, latency, or cost.
- Stray-request prevention applies to Laravel's HTTP client. Any future SDK or raw
  network dependency must be audited separately.
- During implementation, the evaluation exposed and fixed a role-based approval defect:
  the risk policy compared a `UserRole` enum to string role names. The policy now
  compares the enum value.
