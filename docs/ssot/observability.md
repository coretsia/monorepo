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

# Observability Naming, Metrics Catalog, and Labels Allowlist (SSoT)

This document is the canonical SSoT for observability naming, the canonical metrics catalog, metric label allowlist, and redaction invariants across logs, metrics, and spans.

## Goal

A single SSoT defines observability naming, the canonical metrics catalog, metric-specific labels, and the global label allowlist to prevent metric, log, and span drift and to prevent PII leaks.

## Invariants (MUST)

- Observability names and labels MUST follow the single-choice rules defined in this document.
- Metrics, spans, and logs MUST NOT leak raw request or persistence data that can reveal secrets, PII, or unstable high-cardinality values.
- The label allowlist is explicit and closed; labels outside the allowlist are forbidden unless and until this SSoT is updated.
- Runtime metric names emitted through `MeterPortInterface` MUST exist in the canonical metrics catalog in this document.
- Metric labels MUST satisfy both the global label allowlist and the metric-specific catalog row.
- The global label allowlist is necessary but not sufficient for metrics; metric-specific catalog labels are authoritative for each registered metric.
- Runtime span names emitted through `TracerPortInterface::startSpan(...)` or `TracerPortInterface::inSpan(...)` MUST follow the canonical span naming policy in this document.
- Span names MUST NOT be registered in the canonical metrics catalog.
- Redaction policy is global for logs, metrics, and spans.
- Reset observability safety policy is defined in `docs/ssot/observability-and-errors.md`; this document owns only the canonical reset span name, reset metric names, metric-specific labels, and global redaction law.
- Safe derivations such as hashes, lengths, and explicitly safe identifiers MAY be used when they preserve privacy and bounded cardinality.

## Naming Rules (MUST)

### Spans

Span names MUST use this shape:

```text
<domain>.<singular_operation>
```

Example:

```text
http.request
```

Rules:

- `domain` MUST be stable and technology-neutral at the observability boundary.
- `singular_operation` MUST be singular and describe the operation kind, not raw request data.
- Span names emitted through `TracerPortInterface::startSpan(...)` or `TracerPortInterface::inSpan(...)` MUST follow this shape.
- Valid reset span name is `foundation.reset`.
- Reset span exception-recording safety is governed by `docs/ssot/observability-and-errors.md`.
- Plural span operation drift such as `foundation.resets` is forbidden.
- Span names MUST NOT be added to the canonical metrics catalog.
- Span names MUST NOT embed raw path fragments, identifiers, query strings, SQL, or user-controlled bytes.

### Metrics

Metric names MUST use this shape:

```text
<domain>.<singular_operation>_<measure>
```

Example:

```text
http.request_total
```

Rules:

- `domain` MUST be stable and technology-neutral at the observability boundary.
- `singular_operation` MUST be singular and describe the operation kind, not raw request data.
- `measure` MUST encode the metric family or unit, for example `total` or `duration_ms`.
- Metric names MUST be stable and low-cardinality.
- Metric names MUST NOT encode request-specific values, identifiers, or raw payload fragments.
- Runtime metric names emitted through `MeterPortInterface` MUST be registered in the canonical metrics catalog in this document.

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
- Allowed labels MUST still carry safe, bounded-cardinality values.
- Allowlisted keys do not permit raw sensitive values.
- New canonical label keys require direct modification of this SSoT.
- Metric label values are safe bounded scalar labels, not generic json-like payloads.
- Metric label values MUST be `string`, `int`, or `bool`.
- `null` MUST NOT be emitted as a metric label value; omit the label key instead.
- Metric label values MUST NOT be lists, maps, floats, objects, resources, closures, raw payloads, or transport/runtime objects.

## Canonical metrics catalog

Metric names MUST use:

```text
<domain>.<singular_operation>_<measure>
```

The operation segment MUST be singular. The measure suffix owns the metric kind or unit, for example `total` or `duration_ms`.

Runtime metric names emitted through `MeterPortInterface` MUST exist in this catalog.

Metric labels MUST satisfy both:

1. the global label allowlist
2. the metric-specific catalog row

Catalog metric types are exactly:

- `counter`
- `observe`

The method/type mapping is canonical:

- `MeterPortInterface::increment(...)` MUST be used only with `counter` metrics.
- `MeterPortInterface::observe(...)` MUST be used only with `observe` metrics.

| Metric name                        | Owner             | Type    | Labels                        |
|------------------------------------|-------------------|---------|-------------------------------|
| http.request_total                 | `platform/http`   | counter | `method`, `status`, `outcome` |
| http.request_duration_ms           | `platform/http`   | observe | `method`, `status`, `outcome` |
| foundation.reset_total             | `core/foundation` | counter | `outcome`                     |
| foundation.reset_duration_ms       | `core/foundation` | observe | `outcome`                     |
| kernel.uow_total                   | `core/kernel`     | counter | `operation`, `outcome`        |
| kernel.uow_duration_ms             | `core/kernel`     | observe | `operation`, `outcome`        |
| kernel.modules_resolve_total       | `core/kernel`     | counter | `operation`, `outcome`        |
| kernel.modules_resolve_duration_ms | `core/kernel`     | observe | `operation`, `outcome`        |

Reset metric names and reset metric labels remain unchanged by the reset observability safety policy.

Reset metrics MAY use only the metric-specific label already registered in this catalog:

```text
outcome
```

Reset observability failure diagnostics, sanitized reset exception recording, and reset summary-only log/span policy are governed by:

```text
docs/ssot/observability-and-errors.md
```

This cross-reference MUST NOT introduce additional reset metric names, reset metric labels, reset span names, or reset logging payload fields.

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

- `hash(value)` MUST use a deterministic, non-reversible, policy-approved representation appropriate for observability.
- `len(value)` MUST expose only size, not content.
- Safe ids MUST be explicitly non-sensitive, bounded-cardinality identifiers whose semantics are safe to expose.
- Safe transformations MUST NOT be used to smuggle unstable or user-identifying data back into labels or names.

## Baseline Canonical Events (MUST)

### Canonical Spans

- `http.request`
- `foundation.reset`
- `kernel.uow`
- `kernel.modules_resolve`

Canonical span names are validated by span naming policy and MUST NOT be registered in the canonical metrics catalog.

### Canonical Metrics

Baseline HTTP metric names and their metric-specific labels are registered in the canonical metrics catalog above:

- `http.request_total`
- `http.request_duration_ms`
- `kernel.uow_total`
- `kernel.uow_duration_ms`
- `kernel.modules_resolve_total`
- `kernel.modules_resolve_duration_ms`

Reset metric names and their metric-specific labels are registered in the canonical metrics catalog above:

- `foundation.reset_total`
- `foundation.reset_duration_ms`

No additional baseline labels are allowed for these canonical metrics beyond their catalog rows.

Reset observability safety policy, including sanitized reset exception recording, summary-only logs/metrics/spans, and reset failure diagnostic limits, is defined in:

```text
docs/ssot/observability-and-errors.md
```

This document does not add reset logging payload fields beyond the reset summary policy owned by `docs/ssot/observability-and-errors.md`.

## Redaction Law (MUST)

- Redaction rules apply equally to logs, metrics, spans, span attributes, and metric labels.
- Producers MUST prefer omission over unsafe emission.
- When raw values are unsafe, producers MUST either drop them or convert them to an allowed safe pattern.
- Redaction MUST NOT be bypassed merely because a sink is internal.

## Non-goals / Clarifications (MUST)

- This SSoT defines naming, the canonical metrics catalog, the global label allowlist, metric-specific labels, and redaction invariants; it does not define every future span schema.
- Owner packages may introduce additional observability signals later, but they MUST conform to this naming and redaction law unless this SSoT is updated.
- This baseline intentionally optimizes for determinism, bounded cardinality, and privacy over ad hoc debugging convenience.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Observability and errors](./observability-and-errors.md) — reset observability safety policy, sanitized reset exception recording, and summary-only reset logs/metrics/spans.
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
