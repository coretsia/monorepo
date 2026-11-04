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

# Metrics Policy SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: platform/metrics
```

## Scope

This document is the Single Source of Truth for the `platform.metrics` deterministic, noop-only runtime baseline: explicit module activation, Foundation fallback, global metric-label admission, null rendering, failure isolation, and the absence of metric recording or state ownership.

The implementation boundary is:

```text
packages/platform/metrics/src/Module/MetricsModule.php
packages/platform/metrics/src/Provider/MetricsServiceProvider.php
packages/platform/metrics/src/Default/DefaultMeter.php
packages/platform/metrics/src/Labels/AllowedLabelSet.php
packages/platform/metrics/src/Noop/NullMetricsRenderer.php
packages/platform/metrics/config/metrics.php
packages/platform/metrics/config/rules.php
```

This document does not own metric names, metric types, the global label-key allowlist, metric-specific label sets, or span names. **`docs/ssot/observability.md` is the sole canonical authority** for all of those policies. The current baseline does not provide a recording metrics backend or a queryable metrics representation.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Provide a transport-neutral metrics boundary through the existing contracts while making metric admission deterministic and safe even when no recording backend exists.

The baseline MUST accept contract-valid meter calls without changing primary application behavior, MUST silently discard metric samples, and MUST NOT imply that installing the package enables a collector or exporter.

## Authority and ownership (MUST)

`core/contracts` owns the existing `MeterPortInterface` and `MetricsRendererInterface` signatures. `core/foundation` owns the canonical `NoopMeter` fallback and declarative container-definition APIs. Kernel owns target-local `ModulePlan` selection, dependency closure, provider ordering, UnitOfWork lifecycle, correlation identity, and reset orchestration.

`platform/metrics` owns the selected `DefaultMeter`, fixed `AllowedLabelSet` admission helper, and `NullMetricsRenderer` only. It MUST NOT introduce a competing metric catalog, label-key registry, context owner, reset orchestrator, service locator, or discovery mechanism.

The package's direct production source dependencies MUST remain limited to `core/contracts` and `core/foundation`. The canonical `docs/architecture/DEPENDENCIES.md` matrix MUST list `platform/metrics` with exactly those source dependencies. Kernel container and module-planning APIs are exercised by integration tests, not imported by the metrics production implementation.

## Canonical ports and signatures (MUST)

The existing meter port remains:

```php
public function increment(string $name, int $delta = 1, array $labels = []): void;

public function observe(string $name, int $value, array $labels = []): void;
```

`$name` is a contract-level non-empty metric name; `$labels` is documented as `array<string, string|int|bool>`. A counter uses `increment()` and an observation uses `observe()` according to the metric type owned by `docs/ssot/observability.md`. Durations SHOULD use integer milliseconds where appropriate. The baseline MUST NOT extend these methods or introduce a parallel meter port.

The existing renderer port remains:

```php
public function contentType(): string;

public function render(): string;
```

`MetricsRendererInterface` is transport-neutral. Its presence in contracts does not guarantee a runtime binding for every target, and its implementation MUST NOT require a vendor metrics SDK or an HTTP response abstraction.

## Module selection and declarative DI (MUST)

Physical installation of `coretsia/platform-metrics` MUST NOT select the `platform.metrics` module. The effective target-local Kernel `ModulePlan` alone determines module activation and provider order.

- Without `platform.metrics` selected, Foundation continues to resolve `MeterPortInterface` to its existing `Coretsia\Foundation\Observability\Metrics\NoopMeter`. Foundation does not bind `MetricsRendererInterface` in this baseline.
- With `platform.metrics` explicitly selected, its `core.foundation` prerequisite is included and the later `MetricsServiceProvider` binds `MeterPortInterface` directly to `DefaultMeter` and `MetricsRendererInterface` directly to `NullMetricsRenderer`.
- A selected but unavailable module or a conflicting exclusion of its required Foundation module MUST fail through the existing Kernel planning rules; it MUST NOT silently change to a different backend.
- Provider registration MUST use the existing Foundation declarative `ContainerDefinitionBuilder::classService()` definitions. These two constructor-free services require no factory, runtime closure, configuration reader, or additional service registry.
- Source and compiled containers MUST expose equivalent selected and unselected bindings. Selecting the metrics module MUST NOT add tags, aliases, parameters, reset services, or any other runtime service definition.

No renderer binding, collection capability, or endpoint is activated merely by the presence of package metadata or configuration defaults.

## Configuration and policy closure (MUST)

The `metrics` configuration root is already reserved for `platform/metrics` in `docs/ssot/config-roots.md`.

`config/metrics.php` returns exactly `[]`. `config/rules.php` declares `schemaVersion: 1`, `configRoot: metrics`, `additionalKeys: false`, and an empty `keys` map. Existing Kernel configuration validation MUST reject unknown or reserved keys.

There are no configurable `enabled`, backend, renderer, collector, exporter, sampler, metric catalog, label allowlist, label normalization, storage, or reset options. Runtime and application configuration MUST NOT extend, replace, or relax the canonical label policy. There is no package-local configuration validator or alternative validation pipeline.

## Global admission and metric-specific restrictions (MUST)

`AllowedLabelSet::accepts(array $labels): bool` enforces a fixed runtime representation of the **global label-key allowlist** in `docs/ssot/observability.md`. It accepts empty labels and only string keys belonging to that canonical allowlist, with values of type `string`, `int`, or `bool`.

Unknown keys, numeric keys, `null`, floats, lists, maps, objects, resources, and closures MUST be rejected without coercion, normalization, hashing, redaction, logging, or `Stringable::__toString()` invocation. The helper MUST remain stateless; production execution MUST NOT read Markdown, configuration, filesystem data, or a dynamically supplied allowlist.

Global admission is **necessary but not sufficient** for emission. Every emitted metric must also satisfy the canonical catalog row for its name, owner, type, and permitted metric-specific labels. A globally permitted key is not automatically valid for every metric. For example, a `table` key cannot be attached to `http.request_total` merely because it passes the global check.

`AllowedLabelSet` does not validate metric names, catalog rows, metric-specific subsets, semantic safety of label values, or cardinality budgets. These remain producer-owned obligations and, before a future backend emits samples, must also be enforced at that recording boundary. The existing observability catalog gates remain authoritative for production callsites. This document introduces neither a second catalog nor new canonical metric or span names.

## Safe values, cardinality, and redaction (MUST)

Metric producers MUST derive stable, bounded-cardinality label values from explicitly approved inputs. Allowlisted label keys do not authorize raw paths, URL queries, request or response bodies, authorization headers, cookies, session identifiers, credentials, tokens, SQL, user identifiers, profile payloads, or private customer data.

When safe derivation cannot be established, the producer MUST omit the label or refrain from emitting the metric. Hashes, lengths, truncated values, and redacted summaries are not automatically authorized metric values or new label dimensions. Redaction is defense in depth: it MUST NOT add label keys, override a metric-specific catalog row, expand cardinality, or transform a forbidden value into an implicitly permitted label.

The canonical global and metric-specific restrictions are defined by `docs/ssot/observability.md`, while shared sensitive-data treatment is governed by `docs/ssot/sensitive-data-redaction.md`.

## Deterministic noop behavior and failure isolation (MUST)

The selected `DefaultMeter` performs `AllowedLabelSet::accepts($labels)` for every `increment()` and `observe()` invocation before deterministic noop completion.

- Admitted calls MUST discard their names, values, and labels without recording, forwarding, buffering, aggregating, exporting, or retaining samples.
- Rejected labels MUST cause a deterministic skip. They MUST NOT throw, generate diagnostics, emit secondary metrics, or disclose rejected inputs.
- Contract-valid calls MUST be no-throw and MUST NOT alter the primary operation's result, exception precedence, or UnitOfWork lifecycle.
- Programming errors outside the public typed method contracts are not metric-admission failures and are not subject to a blanket exception suppression policy.
- Repeated calls MUST be independent and MUST NOT produce metrics, logs, traces, process-lifetime state, or recursive self-instrumentation.

No metric-specific backend admission or runtime cardinality enforcement is claimed for this noop-only implementation because it never emits metric samples.

## Null rendering and transport boundary (MUST)

The selected `NullMetricsRenderer` implements `MetricsRendererInterface` with exact deterministic results:

```text
contentType(): text/plain; charset=utf-8
render():      ''
```

`render()` returns an empty string for every call, including after any meter invocation. It MUST NOT retain or expose metric names, values, labels, samples, private data, or a serialized metrics payload.

There is no active metric collector, registry, exporter, aggregation store, persistence adapter, metrics endpoint, HTTP middleware, or scraping transport. Rendering is not an HTTP response; any future transport adapter remains independently owned and MUST NOT be inferred from module selection.

## Context, UnitOfWork, and reset ownership (MUST)

Kernel remains the sole owner of UnitOfWork lifecycle and reset orchestration. The metrics baseline MUST NOT read or write `ContextStore`, derive labels from implicit correlation or request identity, or introduce `ContextAccessorInterface`, `ContextKeys`, `ResetInterface`, or `kernel.reset` registrations.

`DefaultMeter`, `AllowedLabelSet`, and `NullMetricsRenderer` MUST have no mutable instance or static state, request-local metric buffer, process-lifetime cache, or reset/snapshot hook. The provider MUST NOT establish an additional state owner. Repeated calls across independent UnitOfWork executions therefore cannot retain samples or stale context.

## Future recording boundary (MUST)

A future recording implementation MAY extend the existing package behind the same public ports, but it MUST preserve the global label allowlist and enforce the canonical metric-specific catalog before emitting any sample. It MUST establish explicit lifecycle, bounded-cardinality, storage, and reset ownership before introducing state.

A future format-specific renderer MAY replace the selected `MetricsRendererInterface` binding without creating a second baseline null renderer. No future collector or exporter is automatically enabled by package installation, and no HTTP endpoint or exporter integration is implied by this policy.

## Deterministic examples

These examples use only synthetic, catalog-registered metrics and explicitly supplied safe labels:

```php
$meter->increment('kernel.operation_total', 1, [
    'operation' => 'config.validate',
    'outcome' => 'success',
]);

$meter->observe('kernel.operation_duration_ms', 12, [
    'operation' => 'config.validate',
    'outcome' => 'success',
]);
```

Both calls complete without recording a metric. The duration value is an integer millisecond count; no clock or timer is owned by the meter.

```php
$meter->increment('kernel.operation_total', 1, ['token' => 'synthetic']);
$meter->observe('kernel.operation_duration_ms', 12, ['outcome' => []]);
```

These labels fail global admission and both calls are silently skipped. They MUST NOT create a warning, metric, log entry, or raw diagnostic.

For a target selecting `platform.metrics`, repeated `render()` calls return the empty string and `contentType()` returns `text/plain; charset=utf-8`. For a Foundation-only target, the renderer port has no binding.

## Enforcement rails

Verification MUST establish:

- exact correspondence of runtime global label keys with `docs/ssot/observability.md`, including detection of added, removed, or renamed keys on either side;
- accepted scalar types, empty labels, rejected keys and values, insertion-order independence, no implicit `Stringable` conversion, and no diagnostic output;
- empty metrics defaults, strict configuration rules, rejection of unknown selectors and configurable allowlists, and the reserved config-root ownership;
- package identity, dependencies, module metadata, the exact two provider definitions, and the absence of unrelated source dependencies or runtime tags;
- Foundation-only `NoopMeter` and no renderer binding, explicit selected `DefaultMeter`/`NullMetricsRenderer` bindings, installation without selection, and source/compiled parity;
- deterministic no-throw calls, empty renderer output, no `ContextStore` mutation, stateful collector, registry, exporter, reset owner, or recursive metrics;
- continued authority of the canonical metric catalog and existing observability gates, without expanding metric names, metric-specific labels, or span names.

Missing Composer bootstrap or unavailable gates MUST NOT be reported as successful verification.

## Non-goals

- Recording, aggregating, exporting, persisting, querying, or scraping metric samples.
- A metrics registry, histogram/counter storage, active collector, configurable exporter, or vendor-specific SDK adapter.
- HTTP middleware, routing, a metrics endpoint, response adaptation, or transport-level instrumentation.
- Runtime metric-name/catalog validation, backend-level cardinality enforcement, or producer-owned safe-value normalization inside the noop meter.
- A second global label allowlist, dynamic policy registry, configurable label set, or new metric/span names.
- Context access, correlation-id generation, a second UnitOfWork owner, mutable metric state, or reset orchestration.
- Logging, tracing, diagnostic warnings, runtime exception hierarchy, or self-instrumentation of rejected samples.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md) — canonical metric names, types, owners, global label-key allowlist, metric-specific labels, and redaction law.
- [Observability and Errors SSoT](./observability-and-errors.md) — observability failure isolation and reset diagnostic policy.
- [Sensitive Data Redaction SSoT](./sensitive-data-redaction.md) — shared redaction and safe-disclosure boundaries.
- [Config Roots Registry](./config-roots.md) — reserved `metrics` root ownership.
- [Runtime Container Definitions SSoT](./runtime-container-definitions.md) — declarative definitions and source/compiled parity.
- [Modules and Manifests SSoT](./modules-and-manifests.md) — target-local module selection and dependency closure.
- [ContextStore Lifecycle SSoT](./context-lifecycle.md) — Foundation context and Kernel reset boundaries.
- [Reset Tags SSoT](./reset-tags.md) — canonical reset ownership and tags.
- [Architecture Dependencies](../architecture/DEPENDENCIES.md) — allowed direct package dependencies.
- [ADR-0030: Canonical Runtime Container Definitions](../adr/ADR-0030-canonical-runtime-container-definitions.md) — declarative provider design.
