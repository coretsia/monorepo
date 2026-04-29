<!--
  Coretsia Framework (Monorepo)

  Project: Coretsia Framework (Monorepo)
  Authors: Vladyslav Mudrichenko and contributors
  Copyright (c) 2026 Vladyslav Mudrichenko

  SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
  SPDX-License-Identifier: Apache-2.0

  For contributors list, see git history.
  See LICENSE and NOTICE in the project root for full license information.
-->

# Observability Naming and Labels Allowlist (SSoT)

This document is the canonical SSoT for observability naming, label allowlist, and redaction invariants across logs, metrics, and spans.

## Goal

A single SSoT defines observability naming and label allowlist to prevent metric, log, and span drift and to prevent PII leaks.

## Invariants (MUST)

- Observability names and labels **MUST** follow the single-choice rules defined in this document.
- Metrics, spans, and logs **MUST NOT** leak raw request or persistence data that can reveal secrets, PII, or unstable high-cardinality values.
- The label allowlist is explicit and closed; labels outside the allowlist are forbidden unless and until this SSoT is updated.
- Redaction policy is global for logs, metrics, and spans.
- Safe derivations such as hashes, lengths, and explicitly safe identifiers **MAY** be used when they preserve privacy and bounded cardinality.

## Naming Rules (MUST)

### Spans

Span names **MUST** use this shape:

```text
<domain>.<operation>
```

Example:

```text
http.request
```

Rules:

- `domain` **MUST** be stable and technology-neutral at the observability boundary.
- `operation` **MUST** describe the operation kind, not raw request data.
- Span names **MUST NOT** embed raw path fragments, identifiers, query strings, SQL, or user-controlled bytes.

### Metrics

Metric names **MUST** use this shape:

```text
<domain>.<metric>_{total|duration_ms|...}
```

Example:

```text
http.request_total
```

Rules:

- Metric names **MUST** be stable and low-cardinality.
- The suffix **MUST** encode the metric family or unit, for example `total` or `duration_ms`.
- Metric names **MUST NOT** encode request-specific values, identifiers, or raw payload fragments.

## Label Allowlist (MUST)

The reserved baseline allowlist is single-choice:

- `method`
- `status`
- `driver`
- `operation`
- `table`
- `outcome`

### Label Rules (MUST)

- Only allowlisted label keys may be emitted by default.
- Allowed labels **MUST** still carry safe, bounded-cardinality values.
- Allowlisted keys do not permit raw sensitive values.
- New canonical label keys require direct modification of this SSoT.

## Forbidden Data (MUST NOT LEAK)

The following data is forbidden in logs, metrics, span names, span attributes, and label values unless transformed into a safe representation:

- raw path
- raw query
- headers
- cookies
- body
- auth identifiers
- session identifiers
- tokens
- raw SQL

## Forbidden Label Keys (MUST)

The following label keys are forbidden in the baseline policy:

- `field`
- `path`
- `property`
- `request_id`
- `correlation_id`
- `tenant_id`
- `user_id`

Rationale:

- these keys commonly create high-cardinality, privacy-sensitive, or schema-drift-prone observability dimensions
- correlation and request identifiers may still exist in system-specific tracing or log correlation mechanisms, but they are not part of the canonical metrics label baseline defined here

## Allowed Safe Patterns (MUST)

The following patterns are allowed when a raw value would otherwise be unsafe:

- `hash(value)`
- `len(value)`
- safe ids

### Safe Pattern Rules (MUST)

- `hash(value)` **MUST** use a deterministic, non-reversible, policy-approved representation appropriate for observability.
- `len(value)` **MUST** expose only size, not content.
- Safe ids **MUST** be explicitly non-sensitive, bounded-cardinality identifiers whose semantics are safe to expose.
- Safe transformations **MUST NOT** be used to smuggle unstable or user-identifying data back into labels or names.

## Baseline Canonical Events (MUST)

### Canonical Span

- `http.request`

### Canonical Metrics

- `http.request_total`
- `http.request_duration_ms`

Allowed labels for these baseline HTTP metrics are single-choice:

- `method`
- `status`
- `outcome`

No additional baseline labels are allowed for these canonical HTTP metrics.

## Redaction Law (MUST)

- Redaction rules apply equally to logs, metrics, spans, span attributes, and metric labels.
- Producers **MUST** prefer omission over unsafe emission.
- When raw values are unsafe, producers **MUST** either drop them or convert them to an allowed safe pattern.
- Redaction **MUST NOT** be bypassed merely because a sink is internal.

## Non-goals / Clarifications (MUST)

- This SSoT defines naming, label allowlist, and redaction invariants; it does not define every future metric or span schema.
- Owner packages may introduce additional observability signals later, but they **MUST** conform to this naming and redaction law unless this SSoT is updated.
- This baseline intentionally optimizes for determinism, bounded cardinality, and privacy over ad hoc debugging convenience.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
