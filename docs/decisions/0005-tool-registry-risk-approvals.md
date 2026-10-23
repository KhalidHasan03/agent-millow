# ADR-0005: Tool registry, risk policy, approvals, and secure execution

- Status: Accepted
- Date: 2026-10-24
- Phase: 4

## Context

Phase 4 requires a tool registry, risk policy, approvals, secure tool execution, and
audit. The LLM must never bypass authorization or directly access the database, shell,
filesystem, or unrestricted network. All tool actions must be user-scoped, allowlisted,
validated, risk-classified, approval-gated when required, executed with limits, and
audited.

## Decision

1. **Allowlist via registry.** Tools are stored in `tools`/`tool_versions` with JSON
   input/output schemas, risk level, timeout, approval policy, and metadata. The
   `ToolRegistry` is the only path from a proposed call to an implementation; unknown
   or disabled tools are rejected.
2. **Full pipeline.** Every tool call passes through: proposal, registry lookup,
   schema validation, authorization, risk classification, approval (if required),
   controlled execution with timeout, redaction, and audit. The model never receives a
   DB connection, shell, filesystem handle, or unrestricted network client.
3. **Risk and approvals.** `ToolRiskPolicy` classifies risk and marks high/critical as
   approval-required by default. Pending calls create `tool_approvals` records;
   execution only proceeds after explicit user approval via dedicated endpoints.
4. **User-scoped and auditable.** Tool calls are owned by the user and protected by
   ownership checks. All phases (proposed/approved/rejected/succeeded/failed/timed_out)
   are recorded in `tool_calls` and `audit_events`. Redaction prevents logging secrets
   or sensitive content.
5. **Controlled executors.** Executors implement `ToolExecutor` and run through
   `ToolOrchestrator`. Timeouts are enforced and results are redacted. A safe
   non-network `echo` tool is provided for verification.
6. **Least privilege.** Tool execution is isolated, user-scoped, and rate-limited.
   Sensitive actions require an approval path and audit trail.

## Alternatives considered

- **Let the model call functions directly.** Rejected. Violates the requirement that
  the LLM must never bypass authorization or access system resources directly.
- **Global tools without per-user ownership.** Rejected. All tool actions must be
  user-scoped and auditable.
- **Implicit approvals.** Rejected. High-risk actions require explicit user approval.

## Consequences

Positive:

- Clear security boundary between model proposals and system actions.
- Full audit coverage with redaction.
- Extensible allowlist for future tools without changing the pipeline.

Negative and mitigations:

- Additional latency/complexity. Mitigation: deterministic flows, tests, and clear
  status reporting.
- PHPStan/Larastan style constraints on new code. Mitigation: iterative fixes while
  keeping behavior correct.

## Verification

- `php backend\artisan test` passes (96 tests).
- Tools can be listed; safe tool calls execute through the pipeline or return
  pending approval; unknown/invalid inputs rejected.
- Tool calls are user-scoped; cross-user access returns 403.
- Audit events are written for tool actions without logging full secrets.
- MariaDB on 3306 remains untouched.
