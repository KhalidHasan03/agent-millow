# ADR-0004: User-scoped memory with inspection/edit/delete and audit

- Status: Accepted
- Date: 2026-10-23
- Phase: 3

## Context

Phase 3 adds user-scoped memory with inspection/edit/delete and audit, as specified
in the architecture. Memory must remain explicit, inspectable, and user-owned. The
platform must not claim that a model permanently learns from memory, and memory
writes that could be surprising or sensitive must be handled carefully.

## Decision

1. **Memory is relational and user-scoped.** `memories` stores user-authored and
   model-proposed facts with `user_id`, category, source, confidence, and metadata.
   All queries are scoped to the authenticated user; a `MemoryPolicy` enforces
   ownership.
2. **Source and origin are explicit.** A memory records whether it came from `user`
   or `model`, along with confidence and optional key. This keeps user-authored facts
   distinct from model-proposed memories without relying on opaque embeddings alone.
3. **Inspection is auditable.** `GET /api/v1/memories/{memory}` marks the memory as
   inspected (`last_accessed_at`) and writes an audit event via `memory_events`. Reads
   and writes are audited.
4. **Content is never logged.** Audit/context payloads do not contain full memory
   content. Events record action, actor, request id, IP, and optional safe metadata.
5. **CRUD with soft deletion.** Users can list (paginated, filterable), inspect, edit,
   and delete their own memories. Deletion is soft-deleted and audited.
6. **Sensitive marking without bypassing access control.** Memories may be marked
   sensitive, but all access remains protected by authentication and ownership policy.
   Rate limiting applies to memory endpoints.
7. **Memory is retrieved context, not training.** The implementation treats memory as
   inspectable relational data; no automatic model fine-tuning or persistent learning
   claims are made.

## Alternatives considered

- **Store memory in the vector store only.** Rejected. The canonical memory must
  remain inspectable relational data per the architecture. Embeddings may be added
  later.
- **Allow cross-user memory sharing.** Rejected. Memory is explicitly user-scoped and
  not a multi-tenant knowledge base in these phases.
- **Log full memory content in audit events.** Rejected. It risks leaking sensitive
  facts into logs; only action metadata is recorded.
- **Implicit memory writes by the model.** Deferred. The phase requires inspectable
  memory with explicit CRUD and audit; implicit writes with approval will be
  addressed more fully when tool execution lands.

## Consequences

Positive:

- Users can inspect, edit, and delete their memories with full audit coverage.
- User and model sources are distinguished.
- Access is strictly scoped to the owner.

Negative and mitigations:

- Without embeddings, retrieval is limited to direct lookup/listing. Mitigation:
  acceptable for Phase 3; semantic search can be layered later without changing the
  audit model.

## Verification

- `php backend\artisan test` passes, including `MemoryTest`.
- A user can create, list, inspect, update, and delete their memory; another user
  receives 403.
- Inspection records `last_accessed_at` and a `memory_events` entry.
- Memory operations do not log full content in audit context.
- MariaDB on 3306 remains untouched.
