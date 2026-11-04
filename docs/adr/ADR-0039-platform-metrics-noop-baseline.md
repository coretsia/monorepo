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

# ADR-0039: Platform metrics noop baseline

```yaml
adrVersion: 1
status: pre-accepted
owner: platform/metrics
```

## Context

Coretsia already defines the vendor-neutral `MeterPortInterface` and `MetricsRendererInterface` in `core/contracts`. Foundation provides the canonical `NoopMeter` for `MeterPortInterface`; it does not provide a `MetricsRendererInterface` binding. Kernel owns target-local module selection, deterministic provider ordering, UnitOfWork lifecycle, and reset orchestration.

A transport-neutral metrics port is useful before a recording backend exists, but package installation must not silently activate collection, storage, export, or HTTP exposure. Introducing a second noop meter, a runtime backend selector, or a hidden metrics state owner would duplicate established Foundation and Kernel responsibilities while providing no observable metrics samples.

The decision is to provide an explicitly selected, stateless baseline that validates the global metric label-key boundary and exposes an empty renderer without claiming collection. The canonical names, types, and label restrictions remain governed by `docs/ssot/observability.md`; the detailed runtime policy is specified by `docs/ssot/metrics-policy.md`.

## Decision

### Decision 1: Reuse Foundation NoopMeter as the unselected fallback

`core/foundation` remains the canonical owner of `Coretsia\Foundation\Observability\Metrics\NoopMeter`. When `platform.metrics` is absent from a target's effective module plan, `MeterPortInterface` continues to resolve to that Foundation implementation and `MetricsRendererInterface` has no binding.

`platform/metrics` does not introduce another class named `NoopMeter`, a second Foundation fallback, or an alternate metrics port. The selected package-local meter is deliberately named `DefaultMeter` because its responsibility includes label admission, not simply duplicating Foundation's disabled behavior.

### Decision 2: Activate the baseline only through target-local module selection

Installing `coretsia/platform-metrics` or loading the reserved `metrics` configuration root does not select the `platform.metrics` module. Kernel determines activation solely from the target-local `ModulePlan` and the installed Composer module manifest. The module declares `core.foundation` as a prerequisite and must be ordered after Foundation.

When the module is explicitly selected, its provider contributes the following effective bindings:

```text
MeterPortInterface           -> DefaultMeter
MetricsRendererInterface     -> NullMetricsRenderer
```

When unselected, Foundation's existing meter binding is unchanged and the renderer remains unavailable. Missing modules or excluded required dependencies are rejected by Kernel's established module-planning policy; the metrics package does not silently select a different implementation or configure itself on installation.

### Decision 3: Keep the existing metrics ports and validate labels before noop completion

The existing `MeterPortInterface` signatures remain `increment(string $name, int $delta = 1, array $labels = []): void` and `observe(string $name, int $value, array $labels = []): void`. `MetricsRendererInterface` continues to expose `contentType(): string` and `render(): string` without a vendor SDK or HTTP response type.

The selected final `DefaultMeter` checks `AllowedLabelSet::accepts($labels)` in both meter methods. The helper accepts only the fixed global label keys and `string`, `int`, or `bool` values defined by the canonical observability policy. Invalid keys and values result in a deterministic skip without coercion, normalization, logging, throwing, or emitting secondary metrics.

Both admitted and rejected calls discard their metric name, value, and labels. Global admission is not a metric-specific recording validator: it does not establish the permitted labels, metric type, owner, or cardinality budget for a given metric name. Those rules remain authoritative in `docs/ssot/observability.md` and must be enforced before any future recording implementation emits samples.

### Decision 4: Use exactly two declarative service definitions without a factory

`MetricsServiceProvider` uses the existing Foundation `ServiceProviderInterface` and `ContainerDefinitionProviderInterface` registration path. Its `define()` method contributes exactly two `ContainerDefinitionBuilder::classService()` definitions, binding `MeterPortInterface` to `DefaultMeter` and `MetricsRendererInterface` to `NullMetricsRenderer`, with no constructor arguments.

There is no `MetricsServiceFactory`, runtime closure, service locator, configurable backend discovery, alias, or new tag. Kernel's canonical compile-time provider plan supplies the same ordered definitions to source and compiled container paths. Selection replaces only the effective meter implementation and adds the renderer binding; it must not change other services, aliases, parameters, tags, or reset definitions.

### Decision 5: Keep the reserved metrics configuration root empty and closed

The package retains the previously reserved `metrics` root. `config/metrics.php` returns exactly `[]`; `config/rules.php` declares `schemaVersion: 1`, `configRoot: metrics`, `additionalKeys: false`, and an empty `keys` map.

No `enabled` flag, backend selector, collector, exporter, renderer mode, sampler, label allowlist, metric catalog, registry, persistence, or reset option is introduced. Kernel's existing configuration validation rejects unknown keys. Runtime configuration cannot extend or replace the fixed label policy, and a second package-local validator is unnecessary.

### Decision 6: Keep the renderer empty and defer recording and transport

`NullMetricsRenderer` is the single selected null-renderer implementation of `MetricsRendererInterface`. It returns exactly `text/plain; charset=utf-8` from `contentType()` and `''` from `render()`, including after repeated meter calls. The content type does not imply a nonempty payload or a configured scraping endpoint.

The baseline creates no metric samples, counters, histograms, registry, buffer, aggregation, persistence, exporter, or recording SDK integration. It does not register HTTP middleware, a metrics route, a response adapter, or an endpoint. A future transport owns its own integration and cannot infer exposure from package installation or module selection.

A future recording backend may extend the existing `platform/metrics` package and reuse its public ports and global admission helper, but must first establish metric-specific validation, bounded cardinality, and explicit state lifecycle and reset ownership. A future format-specific renderer may replace the existing renderer binding without creating another baseline null renderer.

### Decision 7: Preserve the canonical observability catalog and security boundary

`docs/ssot/observability.md` is the sole normative owner of metric names, types, owners, the global label-key allowlist, and metric-specific label restrictions. `AllowedLabelSet` is its fixed runtime global-key admission representation, not a second catalog or a configurable policy registry. The baseline introduces no new metric names, label dimensions, or span names.

Producers own safe, stable, bounded-cardinality label values. An allowed key does not authorize raw paths, queries, authentication headers, cookies, session or correlation identifiers, tokens, SQL, payloads, arbitrary user identities, or private customer data as values. Hashes, lengths, and redacted summaries are not automatically safe labels. Redaction does not expand either the global or metric-specific policy; the shared security boundary remains governed by `docs/ssot/sensitive-data-redaction.md`.

### Decision 8: Preserve Kernel UnitOfWork and Foundation context/reset ownership

`DefaultMeter`, `AllowedLabelSet`, and `NullMetricsRenderer` are stateless. They do not read or write `ContextStore`, derive labels from implicit request or correlation identity, retain UnitOfWork-local samples, or maintain process-lifetime metric caches. The provider introduces no context accessor, mutable registry, lifecycle hook, `ResetInterface` implementation, or `kernel.reset` registration.

Kernel remains the owner of UnitOfWork lifecycle, correlation identity, and reset orchestration; Foundation keeps its existing context and reset responsibilities. Metrics calls use only caller-supplied names, values, and labels and cannot change application context or the outcome of an active operation.

### Decision 9: Fail closed without diagnostics or changes to application outcomes

Forbidden label keys, invalid label value types, and other admission failures are silently skipped for contract-typed meter calls. No rejected input, raw label, metric sample, exception, warning, log record, tracing span, or recursive metrics report is emitted. `Stringable::__toString()` is not called to coerce rejected objects.

Both meter operations and null-renderer methods remain deterministic and no-throw for contract-valid arguments. Metrics admission cannot replace a primary application result or exception. Programming errors outside the public typed contracts are not treated as recoverable metric-admission failures and are not masked by blanket exception suppression.

## Consequences

### Positive consequences

- Foundation remains the single canonical unselected noop meter, with no accidental renderer availability or metric collection from package installation;
- selected applications receive deterministic global-label admission and an empty renderer through the existing public contracts;
- direct declarative bindings preserve effective source/compiled container parity without a metrics-specific factory or registry;
- Kernel and Foundation retain their existing module, UnitOfWork, context, and reset responsibilities;
- no metric samples, sensitive labels, or diagnostic side effects cross the noop-only boundary;
- future recording and transport integrations can reuse existing ports without redefining package identity or global label authority.

### Trade-offs

- selecting the metrics module provides no recorded, rendered, exported, persisted, or queryable metrics;
- valid global label admission does not perform catalog-row validation, value-safety derivation, or cardinality enforcement;
- applications must explicitly select the module, and an unselected target cannot rely on a renderer binding;
- a future recording backend requires its own policy-safe state, lifecycle, and verification before any samples may be emitted.

## Rejected alternatives

### Replace Foundation NoopMeter or introduce another platform-local noop meter

Rejected because the existing Foundation meter already defines the unselected fallback. A second noop implementation would create duplicate disabled behavior and unclear ownership without enabling collection.

### Activate the package automatically or choose a backend using runtime configuration

Rejected because target-local `ModulePlan` is the canonical activation mechanism. An enabled switch, runtime selector, or factory would introduce a second selection path and complicate source/compiled equivalence.

### Collect, buffer, export, or expose samples in the baseline

Rejected because sample retention requires metric-specific validation, bounded-cardinality policy, state lifecycle, reset discipline, and transport ownership that this stateless baseline does not establish.

### Maintain a second metric catalog or allow configurable labels

Rejected because the global and metric-specific label policies are already normative in `docs/ssot/observability.md`. A second catalog, mutable allowlist, or automatic label normalization would create policy drift and unsafe cardinality expansion.

### Attach meter calls to ContextStore or create a metrics reset service

Rejected because meter inputs are explicitly supplied by producers and no state is retained. A second UnitOfWork or reset owner would violate Kernel/Foundation lifecycle boundaries.

### Register HTTP middleware or a metrics endpoint together with the renderer

Rejected because `MetricsRendererInterface` is transport-neutral and `NullMetricsRenderer` returns no samples. HTTP integration cannot be inferred from a content-type string or a selected module.

### Log invalid labels or self-instrument admission failures

Rejected because rejected inputs can contain sensitive data and secondary telemetry could introduce recursion, diagnostic leaks, or changes to the primary operation's failure precedence.

## Non-goals

This decision does not implement metric recording, aggregation, exporter selection, persistence, a queryable registry, cardinality enforcement, an HTTP endpoint, middleware, an active metric backend, vendor SDK adapters, tracing, logging, span names, new metric names, or additional label dimensions. It introduces no runtime metrics settings, implicit context access, process-lifetime state, reset hooks, second catalog, exception hierarchy, or metrics-specific transport integration.

## Related SSoT

- `docs/ssot/metrics-policy.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/modules-and-manifests.md`
- `docs/ssot/runtime-container-definitions.md`
- `docs/ssot/context-lifecycle.md`
- `docs/ssot/reset-tags.md`
- `docs/ssot/sensitive-data-redaction.md`

## Related ADRs

- `docs/adr/ADR-0003-observability-errordescriptor-health-profiling-ports.md`
- `docs/adr/ADR-0006-reset-interface-uow-hooks.md`
- `docs/adr/ADR-0010-sensitive-data-redaction-boundary.md`
- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
- `docs/adr/ADR-0029-kernel-container-compile-artifact.md`
- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md`
