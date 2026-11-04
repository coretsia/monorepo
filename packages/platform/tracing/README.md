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

# coretsia/platform-tracing

`platform/tracing` provides transport-neutral, deterministic, bounded W3C `traceparent` extraction and injection through the existing Coretsia `ContextPropagationInterface`. Its W3C implementation is available only when the `platform.tracing` module is explicitly selected for an application target; otherwise Foundation's noop propagation remains in effect.

Scope: validated W3C trace identity, canonical `traceparent` generation, supported Trace Context Level 2 flags, fail-closed malformed tracing-field handling, and preservation of unrelated contract-valid carrier fields.

Out of scope: recording spans and durations, active span state, trace/span identity generation, sampling decisions, exporters, metrics, logging, persisted trace history, trace viewers, automatic HTTP middleware, full `tracestate` forwarding, baggage propagation, and Kernel correlation or UnitOfWork lifecycle ownership.

This README is a consumer-oriented package summary. `docs/ssot/tracecontext.md` is authoritative for the exact parsing, resource-limit, mutation, and security policies.

## Package identity

- Path: `packages/platform/tracing`
- Package id: `platform/tracing`
- Composer name: `coretsia/platform-tracing`
- Module id: `platform.tracing`
- Namespace: `Coretsia\Platform\Tracing\*` (PSR-4: `src/`)
- Kind: runtime
- Config root: `tracing`

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions MUST NOT be used.

## Dependency policy

The package declares these production Composer dependencies:

- PHP `^8.4`
- `coretsia/core-contracts`
- `coretsia/core-foundation`

Its `composer.json` is authoritative for exact constraints. Direct production PHP source dependencies are limited to `core/contracts` and `core/foundation`, as recorded in `docs/architecture/DEPENDENCIES.md`.

`core/contracts` owns `ContextPropagationInterface`, `TracerPortInterface`, and `SpanInterface`. `core/foundation` owns the canonical `NoopContextPropagation`, `NoopTracer`, and `NoopSpan` implementations, declarative container definitions, and the existing `ContextStore` reset boundary. Kernel owns module planning, provider ordering, and UnitOfWork orchestration; it is not a direct production-source dependency of this package.

Forbidden production dependencies include `platform/http`, concrete redaction, logging, or metrics packages, vendor tracing SDKs, exporters, and transport-specific adapters. Installing this package MUST NOT implicitly enable tracing or introduce a second tracer, redactor, context store, or reset owner.

## Runtime responsibilities

The public ports used by this package are the existing contracts:

```text
Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface
Coretsia\Contracts\Observability\Tracing\TracerPortInterface
Coretsia\Contracts\Observability\Tracing\SpanInterface
```

Only `ContextPropagationInterface` receives a new implementation from this package. The selected implementation is `Coretsia\Platform\Tracing\W3c\W3cTraceContextPropagation`; it has no constructor dependencies and operates solely on explicit carrier and context arguments.

`TracerPortInterface` continues to resolve to Foundation `NoopTracer`; Foundation `NoopSpan` also remains unchanged. These noop implementations are reused rather than copied into `platform/tracing`. In particular, this package has **no active recording tracer**, local span lifecycle, sampler, exporter, recording buffer, or tracing-specific reset registration.

### Capability matrix

- Foundation noop tracer and span behavior: **supported** through the existing Foundation bindings.
- W3C `traceparent` extraction and injection: **supported** when `platform.tracing` is explicitly selected.
- Automatic trace ID or span ID generation: **not supported**.
- Recording spans, events, or durations: **not supported**.
- Span sampling decisions or exporting: **not supported**.
- Trace files, telemetry endpoints, or persistent trace storage: **not supported**.
- Jaeger, Grafana Tempo, or other tracing backend integration: **not supported**.
- Automatic HTTP middleware or request/response integration: **not supported**.

No trace history is recorded or persisted by this package; there is nothing produced here for a user to view in a trace viewer. Availability of a validated `trace_id` does not imply a recorded trace or automatic cross-process continuity.

## Module selection and DI

The package's module declares a requirement on `core.foundation`. Kernel's target-local `ModulePlan` selects the module and determines the provider order; Composer installation alone does not select it.

When `platform.tracing` is selected, `TracingServiceProvider` contributes exactly one direct declarative binding:

```text
ContextPropagationInterface -> W3cTraceContextPropagation
```

When `platform.tracing` is absent or excluded, the existing Foundation binding remains:

```text
ContextPropagationInterface -> NoopContextPropagation
```

In either case `TracerPortInterface` retains its Foundation `NoopTracer` binding. The tracing provider does not replace any other service, introduce aliases or tags, or register reset services. Source and compiled containers use the same ordered declarative definitions.

The reserved `tracing` configuration root has empty defaults and a closed ruleset with no supported keys. There is no `propagation.mode`, `w3c|noop` runtime mode, enabled switch, `TracingServiceFactory`, or service-factory selector. Module selection, not configuration inspection, controls the implementation.

## Trace context propagation

The existing `ContextPropagationInterface` declares the following methods:

```php
public function extract(array $carrier): array;

public function inject(array $carrier, array $context = []): array;
```

The carrier PHPDoc type is `array<string,string|list<string>>`: string field names with string values or lists of strings. A `traceparent` value may be a single string or a list containing exactly one string. Both representations are parsed identically; an empty list, multiple strings, or an invalid value type is rejected. The implementation recognizes only `traceparent` and `tracestate` field names by exact, bounded ASCII case-insensitive comparison.

Successful `extract()` returns exactly three canonical string values:

```php
[
    'trace_id' => '4bf92f3577b34da6a3ce929d0e0e4736',
    'parent_id' => '00f067aa0ba902b7',
    'trace_flags' => '03',
]
```

`inject()` requires exactly `trace_id`, `parent_id`, and `trace_flags`, with no extra keys; input key insertion order does not matter. IDs must be lowercase hexadecimal, have the required lengths, and be nonzero. The extracted `parent_id` is the **upstream parent identity**, not a newly generated local `span_id`.

### Synthetic extraction and injection

The following example assumes `$propagation` is the application-resolved `ContextPropagationInterface` from the target's existing DI composition with `platform.tracing` selected. It does not create another application bootstrap or HTTP middleware.

```php
/** @var \Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface $propagation */
$incomingCarrier = [
    'TrAcEpArEnT' => ['00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-03'],
    'tracestate' => 'vendor=opaque',
    'authorization' => 'caller-owned',
];

$context = $propagation->extract($incomingCarrier);
// [
//     'trace_id' => '4bf92f3577b34da6a3ce929d0e0e4736',
//     'parent_id' => '00f067aa0ba902b7',
//     'trace_flags' => '03',
// ]

$outboundCarrier = $propagation->inject(
    ['x-request-id' => ['synthetic-request']],
    $context,
);
// [
//     'x-request-id' => ['synthetic-request'],
//     'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-03',
// ]
```

The unrelated `x-request-id` string list is preserved with its original PHP structure. The incoming `authorization` value is not included in extracted context. Incoming `tracestate` is not transferred to the independent outbound carrier: the three-field context has no field for opaque vendor state. These examples use synthetic IDs and are not evidence of automatic HTTP forwarding.

### Trace flags and W3C versions

W3C Trace Context Level 2 defines two supported flags: sampled is bit `0x01`, and random-trace-id is bit `0x02`. The supported mask is `0x03`:

- `00`: neither bit set.
- `01`: sampled bit set.
- `02`: random-trace-id bit set.
- `03`: both bits set.

The random-trace-id bit is not proof of actual ID entropy, and neither bit authorizes this propagator to make sampling decisions. With an unchanged `trace_id`, injection MUST preserve the random-trace-id bit.

A version-`00` `traceparent` has exactly 55 ASCII bytes and the form `00-<32 lowercase hex trace-id>-<16 lowercase hex parent-id>-<2 lowercase hex flags>`. Version `ff`, malformed separators, zero IDs, control bytes, and uppercase hexadecimal are rejected. Under Coretsia's strict version-`00` ingress policy, flags with reserved bits (for example `04`, `80`, or `ff`) are rejected, **not** silently rewritten.

Accepted forward versions `01..fe` use the bounded compatibility policy: known fields are parsed at their canonical positions, unknown extensions are not interpreted, and the supported flag bits are retained. Injection downgrades these accepted contexts to canonical version `00`, clearing unsupported bits only as part of that explicit downgrade. It is not a permissive rewrite of invalid version-`00` input.

When injecting into a carrier with an existing valid `traceparent`, changing the random-trace-id bit while retaining the same `trace_id` is prohibited; changing the sampled bit while retaining the same `parent_id` is prohibited. An allowed sampled change with a different parent ID relies on an identity explicitly supplied by the caller, not one created here. A prohibited same-carrier mutation removes existing tracing fields from the returned carrier and emits no replacement.

The implementation cannot establish provenance across independent carriers. The caller owns any decision to continue a trace, change an upstream parent identity, or integrate a future local span ID. W3C `trace_id` and `parent_id` remain distinct from Kernel `correlation_id`, `request_id`, and `uow_id`.

### Traceparent and tracestate handling

On injection, an existing traceparent is validated before being retained, replaced, or rejected. The outgoing field name is always the single lowercase `traceparent` key. Existing duplicate case-insensitive traceparent names, invalid lists, malformed values, or forbidden flag mutations fail closed.

A caller-owned `tracestate` is preserved only when it is already present in the **same carrier passed to `inject()`** and that carrier has one valid existing traceparent whose value is byte-identical to the required canonical outgoing value. The existing traceparent may be represented as one string or a one-string list, but the emitted representation is one canonical string.

```php
$sameCarrier = [
    'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-03',
    'tracestate' => 'vendor=opaque',
];

$propagation->inject($sameCarrier, $context);
// [
//     'tracestate' => 'vendor=opaque',
//     'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-03',
// ]
```

If a traceparent is missing, invalid, replaced, or downgraded, or if the supplied context is invalid, existing `tracestate` is removed from the returned carrier. Likewise, `extract($incomingCarrier)` followed by `inject($outboundCarrier, $context)` does **not** forward the incoming `tracestate` to a different carrier. The implementation neither parses nor validates vendor state, and it does not implement baggage propagation.

Full cross-carrier `tracestate` forwarding requires an explicitly approved transport/security contract. This package does not introduce an implicit raw-header store, opaque propagation state, or complete W3C Trace Context forwarding implementation.

## Context and UnitOfWork

Propagation is stateless and operates on supplied arrays only. It MUST NOT read or write Foundation `ContextStore`, bind `ContextAccessorInterface`, change the active span, or synchronize `trace_id` with Kernel correlation, request, or UnitOfWork IDs.

Foundation owns its existing UoW-local `ContextStore` resets; Kernel owns UnitOfWork lifecycle and reset orchestration. This package introduces no request-local or process-lifetime tracing state, reset hooks, tags, or services. Repeated calls with identical inputs produce identical outputs without creating trace or span identities.

## Observability

This package emits no baseline spans, recorded events, duration measurements, metrics, log records, telemetry exports, or trace history. Foundation's existing `NoopTracer` and `NoopSpan` remain canonical and do not constitute a recording backend.

No tracing exporter, sampler, meter, logger, reporter, trace viewer, or vendor SDK is registered by this package. Trace IDs and parent IDs are not emitted as metric labels, attributes, or diagnostic fields. Future recording/exporting functionality belongs to a separately owned integration and must respect the existing observability and security policies.

## Errors

Missing, duplicate, malformed, conflicting, oversized, or invalid-type `traceparent` fields fail closed without throwing an application-visible parsing exception. `extract()` returns `[]` for invalid trace context; it does not generate a fallback trace ID.

`inject()` with an invalid or empty context, invalid existing traceparent, or prohibited mutation emits no new tracing field. It removes existing case-insensitive `traceparent` and `tracestate` entries from the **returned** carrier and preserves unrelated entries. `inject([], [])` returns `[]`. The original caller-owned carrier array is not modified in place.

Processing is bounded to 128 carrier entries and 512 bytes per `traceparent` candidate. Over-limit extraction returns `[]`; over-limit injection performs only a tracing-field key-removal pass and never emits a new traceparent. The strict carrier PHP shape remains a caller precondition: malformed **unrelated** transport fields are not a tracing-package validation responsibility.

Propagation failures MUST NOT replace the primary application result or exception. The existing Foundation `TracerPortInterface::inSpan()` behavior preserves and rethrows an original callback `Throwable`; this package introduces no callback wrapper or exception suppression.

## Security / Redaction

A valid W3C trace identity is not an authorization token or a guarantee that arbitrary carrier content is safe to emit. `extract()` returns only validated canonical `trace_id`, `parent_id`, and `trace_flags`; it never exposes raw `traceparent`, `tracestate`, baggage, credentials, cookies, Authorization headers, SQL, payloads, or unchecked opaque vendor fields in normalized context or diagnostics.

Malformed tracing-field input, including objects and `Stringable` values, is rejected without implicit conversion, reflection, serialization, or raw-header disclosure. Tracing-header names are recognized with bounded ASCII case-insensitive checks; long unrelated names are not normalized. Duplicate names, malformed tracing values, and reserved version-`00` flags are handled by the documented fail-closed policy.

Unrelated carrier fields satisfying `array<string,string|list<string>>` remain caller-owned and are preserved without semantic parsing, redaction, or value normalization. Validation of unrelated fields outside this typed contract belongs to the transport owner. This package MUST NOT introduce a general-purpose header sanitizer, second redaction engine, classifier, or raw/debug passthrough.

Foundation `NoopContextPropagation` is **not** a sanitization boundary: its `extract()` always returns `[]`, and its `inject()` returns the carrier unchanged, even if tracing headers are malformed. A caller that requires W3C validation MUST explicitly select `platform.tracing` and use its W3C propagation service.

For vulnerability reporting and supported disclosure procedures, use the package's unmodified [Security Policy](./SECURITY.md).

## Non-goals

- Active tracer/span creation, sampling, recording, exporting, and persistent trace history.
- Jaeger/Grafana Tempo integrations, telemetry endpoints, SDKs, or tracing metrics.
- HTTP middleware, carrier acquisition, or automatic cross-process propagation; `platform/http` owns future transport integration.
- Cross-carrier `tracestate` forwarding, baggage propagation, or unchecked vendor-state storage.
- Kernel correlation/UnitOfWork identity conversion, ContextStore ownership, and new reset services.
- A tracing-owned mode selector, generalized carrier validator, or alternate DI/bootstrap path.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Tracing package source](https://github.com/coretsia/monorepo/tree/main/packages/platform/tracing)
- [Trace Context Propagation SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/tracecontext.md)
- [Observability SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability.md)
- [Observability and Errors SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability-and-errors.md)
- [Context Keys SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/context-keys.md)
- [Context Lifecycle SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/context-lifecycle.md)
- [Modules and Manifests SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/modules-and-manifests.md)
- [Runtime Container Definitions SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/runtime-container-definitions.md)
- [Config Roots SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/config-roots.md)
- [HTTP Middleware Catalog SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/http-middleware-catalog.md)
- [ADR-0038: Platform tracing W3C traceparent propagation](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0038-platform-tracing-w3c-propagation.md)
- [Package Dependencies](https://github.com/coretsia/monorepo/blob/main/docs/architecture/DEPENDENCIES.md)
