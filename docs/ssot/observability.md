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
- Canonical span names are listed in [Baseline Canonical Events](#baseline-canonical-events-must).
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

| Metric name                              | Owner             | Type    | Labels                        |
|------------------------------------------|-------------------|---------|-------------------------------|
| http.request_total                       | `platform/http`   | counter | `method`, `status`, `outcome` |
| http.request_duration_ms                 | `platform/http`   | observe | `method`, `status`, `outcome` |
| foundation.reset_total                   | `core/foundation` | counter | `outcome`                     |
| foundation.reset_duration_ms             | `core/foundation` | observe | `outcome`                     |
| kernel.uow_total                         | `core/kernel`     | counter | `operation`, `outcome`        |
| kernel.uow_duration_ms                   | `core/kernel`     | observe | `operation`, `outcome`        |
| kernel.modules_resolve_total             | `core/kernel`     | counter | `operation`, `outcome`        |
| kernel.modules_resolve_duration_ms       | `core/kernel`     | observe | `operation`, `outcome`        |
| kernel.config_merge_total                | `core/kernel`     | counter | `outcome`                     |
| kernel.config_merge_duration_ms          | `core/kernel`     | observe | `outcome`                     |
| kernel.config_explain_total              | `core/kernel`     | counter | `outcome`                     |
| kernel.config_explain_duration_ms        | `core/kernel`     | observe | `outcome`                     |
| kernel.artifacts_write_total             | `core/kernel`     | counter | `outcome`                     |
| kernel.artifacts_write_duration_ms       | `core/kernel`     | observe | `outcome`                     |
| kernel.fingerprint_calculate_total       | `core/kernel`     | counter | `outcome`                     |
| kernel.fingerprint_calculate_duration_ms | `core/kernel`     | observe | `outcome`                     |
| kernel.container_compile_total           | `core/kernel`     | counter | `outcome`                     |
| kernel.container_compile_duration_ms     | `core/kernel`     | observe | `outcome`                     |
| kernel.cache_verify_total                | `core/kernel`     | counter | `outcome`                     |
| kernel.cache_verify_duration_ms          | `core/kernel`     | observe | `outcome`                     |
| worker.process_total                     | `platform/worker` | counter | `status`                      |
| worker.task_total                        | `platform/worker` | counter | `operation`, `outcome`        |
| worker.task_duration_ms                  | `platform/worker` | observe | `operation`, `outcome`        |

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

### Kernel config metrics label policy

Kernel config metrics are operation-specific by metric name.

The following metrics MUST use only the `outcome` label:

- `kernel.config_merge_total`
- `kernel.config_merge_duration_ms`
- `kernel.config_explain_total`
- `kernel.config_explain_duration_ms`

They MUST NOT use the `operation` label.

Rationale: the operation is already encoded in the metric name (`config_merge` or `config_explain`), so adding `operation` would duplicate the operation dimension and create avoidable label drift.

### Kernel artifact/fingerprint/container-compile/cache observability policy

Kernel artifact, fingerprint, container compile, and cache verification observability is operation-specific by metric and span name.

The following spans are canonical:

- `kernel.artifacts_write`
- `kernel.fingerprint_calculate`
- `kernel.container_compile`
- `kernel.cache_verify`

The corresponding metrics are registered in the canonical metrics catalog above.

These metric families MUST use only the `outcome` label:

- `kernel.artifacts_write_*`
- `kernel.fingerprint_calculate_*`
- `kernel.container_compile_*`
- `kernel.cache_verify_*`

They MUST NOT use the `operation` label.

For these Kernel metric families, allowed `outcome` values are:

- `success`
- `failure`

They MUST NOT introduce any of the following metric labels:

- `path`
- `artifact`
- `app`
- `env`
- `fingerprint`

Rationale: the operation is already encoded in the metric name (`artifacts_write`, `fingerprint_calculate`, `container_compile`, or `cache_verify`). Adding operation-like, artifact-like, app-like, env-like, path-like, or fingerprint-like labels would create unnecessary cardinality, privacy risk, and label drift.

Ownership is canonical:

- `ArtifactWriter` owns `kernel.artifacts_write`.
- `FingerprintCalculator` owns `kernel.fingerprint_calculate`.
- `ContainerCompiler` owns `kernel.container_compile`.
- `CacheVerifier` owns `kernel.cache_verify`.

Artifact/fingerprint/container-compile/cache services MUST depend on observability ports/interfaces only, plus Foundation `Stopwatch` where duration measurement is required.

Artifact/fingerprint/container-compile/cache services MUST NOT instantiate Noop observability implementations.

Artifact/fingerprint/container-compile/cache services MUST NOT know whether observability dependencies are real adapters or Noop/no-op adapters.

Real-vs-Noop/default binding is outside artifact/fingerprint/container-compile/cache service responsibility.

Observability failures MUST NOT change deterministic artifact, fingerprint, container compile, or cache verification behavior.

Lifecycle logs are emitted through `LoggerInterface`.

The logger implementation MAY be real or Noop, but artifact/fingerprint/container-compile/cache services MUST NOT know which one they received.

Logger calls MUST be failure-silent and MUST NOT change artifact/fingerprint/container-compile/cache behavior.

Artifact/fingerprint/cache logs MAY include normalized relative paths, artifact basenames, safe bucket names, safe reason tokens, counts, durations, and bounded outcome tokens.

Container compile logs MAY include only safe compile summary data:

- safe counts;
- duration milliseconds;
- outcome.

Artifact/fingerprint/container-compile/cache logs MUST NOT include secrets, raw payloads, raw config values, raw config dumps, raw env values, dotenv values, source file contents, closure dumps, source snippets, full fingerprints, absolute paths, temp paths, OS error messages, PHP warning text, stack traces, throwable messages, previous throwable messages, mtimes, permissions, owners, hostnames, user names, process ids, or random bytes.

### Worker observability policy

Worker observability is owned by `platform/worker`.

The following spans are canonical:

- `worker.process`
- `worker.task`

The corresponding worker metrics are registered in the canonical metrics catalog above.

Worker observability dependencies MUST be injected through public ports/interfaces plus Foundation `Stopwatch` where duration measurement is required.

Worker services MAY depend on:

- `Psr\Log\LoggerInterface`
- `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
- `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- `Coretsia\Foundation\Time\Stopwatch`

Worker services MAY use `Coretsia\Contracts\Observability\Tracing\SpanInterface` only as a runtime span handle returned by `TracerPortInterface`.

`SpanInterface` MUST NOT be injected as a worker service dependency.

Worker services MUST end spans they create, and span finalization failures MUST be failure-silent when they would otherwise alter worker control-flow semantics.

Worker services MUST NOT instantiate Noop logger, tracer, meter, or observability adapter implementations directly.

Worker services MUST NOT know whether observability dependencies are real adapters or Noop/no-op adapters.

Real-vs-Noop/default binding is outside worker service responsibility.

Worker service factories and providers MUST pass logger, tracer, meter, and stopwatch dependencies from DI/container wiring.

Worker services MUST NOT construct logger, tracer, meter, stopwatch, span, or adapter instances internally.

Worker observability failures MUST NOT change worker process, worker task, or worker control-flow semantics.

Logger, tracer, meter, span, and stopwatch calls in worker services MUST be failure-silent where observability failure would otherwise alter worker lifecycle behavior.

Worker process metrics MUST use only the metric-specific label already registered in this catalog:

```text
status
```

Worker task metrics MUST use only the metric-specific labels already registered in this catalog:

```text
operation
outcome
```

For worker process metrics, allowed `status` values are:

- `start_success`
- `start_failure`
- `stop_success`
- `stop_failure`
- `status_success`
- `status_failure`

For worker task metrics and worker task spans, allowed `operation` values are:

- `queue`
- `http`

For worker task metrics and worker task spans, allowed `outcome` values are:

- `success`
- `failure`

Worker metrics MUST NOT introduce any of the following metric labels:

- `worker_id`
- `pid`
- `path`
- `socket`
- `socket_path`
- `endpoint`
- `endpoint_hash`
- `payload`
- `headers`
- `token`
- `exception_class`
- `error_reason`
- `reason`

Worker process spans MAY include only safe lifecycle attributes:

- `pid`
- `outcome`

Worker task spans MAY include only safe task summary attributes:

- `operation`
- `outcome`

Worker id MUST NOT be emitted as a metric label.

Worker id MUST NOT be emitted as a span attribute unless a future SSoT update defines a bounded, non-sensitive worker id policy.

Pid MAY be emitted as a span attribute for worker process spans only.

Pid MUST NOT be emitted as a metric label.

Worker lifecycle logs are summary-only.

Worker lifecycle logs MAY include only:

- `status`
- `outcome`
- `duration_ms`
- `pid`
- `worker_count`
- `driver`
- `control_transport`
- `endpoint_hash`

Worker task logs, if introduced, MUST be summary-only and MAY include only:

- `operation`
- `outcome`
- `duration_ms`

Worker logs MUST NOT include payloads, raw socket paths, raw TCP endpoints, absolute paths, config dumps, environment values, headers, cookies, authorization values, tokens, command lines, stack traces, throwable messages, previous throwable messages, OS error messages, or PHP warning text.

Worker observability MUST NOT use raw endpoint identifiers.

When endpoint correlation is required, worker observability MAY use only the already-redacted lowercase hexadecimal SHA-256 `endpoint_hash`, and only in logs or safe state summaries.

`endpoint_hash` MUST NOT be used as a metric label.

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
- raw config dump
- raw env value
- closure dump
- source snippet
- raw artifact payload
- fingerprint
- OS error message
- throwable message
- previous throwable message
- stack trace

## Forbidden Label Keys (MUST)

The following label keys are forbidden in the baseline policy:

- `app`
- `artifact`
- `env`
- `field`
- `fingerprint`
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
- Worker `endpoint_hash` is a safe redacted representation for logs and state summaries only. It MUST NOT be emitted as a metric label.

## Baseline Canonical Events (MUST)

### Canonical Spans

- `http.request`
- `foundation.reset`
- `kernel.uow`
- `kernel.modules_resolve`
- `kernel.config_merge`
- `kernel.config_explain`
- `kernel.artifacts_write`
- `kernel.fingerprint_calculate`
- `kernel.container_compile`
- `kernel.cache_verify`
- `worker.process`
- `worker.task`

Canonical span names are validated by span naming policy and MUST NOT be registered in the canonical metrics catalog.

### Canonical Metrics

Canonical metric names and their metric-specific labels are registered in the canonical metrics catalog above.

The canonical metrics catalog is the authoritative registry for:

- metric name;
- owner;
- metric type;
- allowed metric-specific labels.

No additional baseline labels are allowed for canonical metrics beyond their catalog rows.

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
