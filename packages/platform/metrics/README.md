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

# coretsia/platform-metrics

`platform/metrics` provides a deterministic, stateless metrics baseline using the existing Coretsia meter and renderer ports. When explicitly selected, it admits only canonical global metric label keys and supported scalar values, then discards all samples; its renderer returns an empty representation. Without selection, Foundation's existing `NoopMeter` remains the meter fallback.

Scope: target-local module selection, declarative `MeterPortInterface` and `MetricsRendererInterface` bindings, fixed global label admission, silent rejection of invalid labels, and deterministic empty rendering.

Out of scope: recording metrics, metric-specific runtime catalog enforcement, aggregation, cardinality enforcement by a backend, metrics registries, buffers, storage, exporters, HTTP middleware or endpoints, and ContextStore, UnitOfWork, or reset ownership.

This README is a consumer-oriented package summary. `docs/ssot/metrics-policy.md` defines the canonical runtime boundary; `docs/ssot/observability.md` remains the sole authority for metric names, types, global label keys, and metric-specific label restrictions.

## Package identity

- Path: `packages/platform/metrics`
- Package id: `platform/metrics`
- Composer name: `coretsia/platform-metrics`
- Module id: `platform.metrics`
- Namespace: `Coretsia\Platform\Metrics\*` (PSR-4: `src/`)
- Kind: runtime
- Config root: `metrics`

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions MUST NOT be used.

## Dependency policy

The package declares these production Composer dependencies:

- PHP `^8.4`
- `coretsia/core-contracts`
- `coretsia/core-foundation`

Its `composer.json` is authoritative for exact constraints. The only direct Coretsia production-source dependencies are `core/contracts` and `core/foundation`; the canonical dependency policy is governed by `docs/architecture/DEPENDENCIES.md`.

`core/contracts` owns the vendor-neutral metrics ports. `core/foundation` owns the canonical `NoopMeter` fallback and declarative container-definition APIs. Kernel owns target-local module planning, provider ordering, UnitOfWork lifecycle, and reset orchestration; this package does not import Kernel production classes.

Production dependencies on `platform/http`, logging, redaction, tracing, worker implementations, integrations, vendor metrics SDKs, exporters, and persistence adapters are forbidden. No second metric catalog, runtime registry, label-policy service, context owner, or reset orchestrator is introduced.

## Runtime responsibilities

The existing public contracts are:

```text
Coretsia\Contracts\Observability\Metrics\MeterPortInterface
Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface
```

`MeterPortInterface` exposes these exact operations:

```php
public function increment(string $name, int $delta = 1, array $labels = []): void;

public function observe(string $name, int $value, array $labels = []): void;
```

`increment()` applies to catalog-registered `counter` metrics; `observe()` applies to catalog-registered `observe` metrics. The contract requires a non-empty name and documents labels as `array<string,string|int|bool>`. Durations SHOULD use integer milliseconds where appropriate. Neither method returns a sample or a recording result.

`MetricsRendererInterface` exposes `contentType(): string` and `render(): string`. It is transport-neutral and does not imply an HTTP response, endpoint, Prometheus integration, or any vendor SDK.

The package's runtime classes are:

- `MetricsModule`: module identity and the sole `MetricsServiceProvider` registration, without runtime discovery or service construction;
- `MetricsServiceProvider`: exactly two direct declarative class bindings, with no factory or additional runtime service;
- `Default\DefaultMeter`: final, constructor-free, stateless meter that checks global labels and discards every sample;
- `Labels\AllowedLabelSet`: final, stateless helper enforcing the canonical global label-key and scalar-type boundary;
- `Noop\NullMetricsRenderer`: final, constructor-free, stateless renderer producing an empty rendered representation.

There is no meter buffer, collector, registry, exporter, aggregation pipeline, or second null renderer. The selected `DefaultMeter` is **not** an active metrics backend or a second metrics state owner.

## Module selection and DI

Installing `coretsia/platform-metrics` does not activate `platform.metrics`. The target-local Kernel `ModulePlan` explicitly selects the module, includes its required `core.foundation` module, and orders the metrics provider after Foundation.

Without `platform.metrics` selected:

```text
MeterPortInterface       -> Foundation NoopMeter
MetricsRendererInterface -> no binding
```

With `platform.metrics` explicitly selected:

```text
MeterPortInterface       -> DefaultMeter
MetricsRendererInterface -> NullMetricsRenderer
```

`MetricsServiceProvider::define()` uses the canonical Foundation `ContainerDefinitionBuilder::classService()` API for both constructor-free services. No `MetricsServiceFactory`, runtime closure, service locator, tag, alias, backend selection, or reset registration is required. Source and compiled containers preserve the same effective bindings. A selected module with a missing or excluded required dependency fails through the existing Kernel module-planning policy rather than silently falling back to another backend.

The renderer port has **no guaranteed binding** in targets that do not select `platform.metrics`.

## Configuration

The reserved package-owned `metrics` root has exact empty defaults:

```php
return [];
```

`config/rules.php` declares the following closed shape:

```php
return [
    'schemaVersion' => 1,
    'configRoot' => 'metrics',
    'additionalKeys' => false,
    'keys' => [],
];
```

There are no runtime metrics configuration keys: no `enabled` switch, collector, exporter, renderer or backend selector, metric catalog, label allowlist, label normalization map, sampling policy, or storage settings. Unknown keys are rejected by the existing Kernel configuration validator. Module activation is controlled solely by explicit module selection, not by these defaults.

## Global label admission and catalog boundaries

`AllowedLabelSet::accepts(array $labels): bool` enforces exactly the six global keys defined by `docs/ssot/observability.md`:

- `method`
- `status`
- `driver`
- `operation`
- `table`
- `outcome`

An empty label map is accepted. Every key must be a permitted string; every value must be `string`, `int`, or `bool`. Unknown or numeric keys and `null`, float, list, map, object, resource, or closure values are rejected without coercion, `Stringable::__toString()`, logging, or diagnostics.

Both `DefaultMeter::increment()` and `DefaultMeter::observe()` perform admission before silently discarding their inputs. Rejected calls are skipped without throwing, reporting, or exposing their names, values, or labels. Accepted calls are discarded just as completely: there is no retained sample or renderer state.

Global label admission is **not** metric-specific validation. `docs/ssot/observability.md` is the single authority for metric names, types, owners, and permitted labels for each metric. Producers MUST supply safe, bounded-cardinality values and obey the corresponding catalog row; a future recording backend MUST independently enforce metric-specific restrictions before emitting samples. Redaction or application configuration cannot expand either policy. The noop baseline does not claim to implement recording-backend cardinality enforcement.

## Deterministic examples

The following synthetic calls use existing registered metric names, their correct method/type mapping, and catalog-permitted labels. `$meter` is a `MeterPortInterface` resolved by the selected application's container:

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

Both calls complete without recording, buffering, exporting, or retaining metrics. The observation value `12` represents a synthetic duration in integer milliseconds; `DefaultMeter` does not read a clock or measure it. Invalid labels are rejected silently, and no attempt is made to normalize or expose their contents.

For a target selecting `platform.metrics`, the renderer always returns:

```text
contentType(): text/plain; charset=utf-8
render():      ''
```

The content type is metadata for an empty representation; no HTTP endpoint, response adapter, or queryable metrics output is registered.

## Context and UnitOfWork

`DefaultMeter`, `AllowedLabelSet`, and `NullMetricsRenderer` have no mutable request-local or process-lifetime state. They do not read or write `ContextStore`, infer label values from correlation or request identifiers, or own UnitOfWork lifecycle, reset hooks, `ResetInterface`, or `kernel.reset` tags.

Kernel remains responsible for UnitOfWork and correlation identity; Foundation retains its existing context and reset mechanisms. Repeated meter and renderer calls are independent, deterministic, and harmless across UnitOfWork boundaries.

## Observability

The package does not emit new metric names, spans, span events, logs, or self-instrumentation. `DefaultMeter` admits labels but never records metric samples; `NullMetricsRenderer` always returns an empty string. There is no collection, aggregation, exporter, persistence, telemetry backend, or queryable metrics endpoint.

`docs/ssot/observability.md` remains authoritative for metric names, metric types, globally admitted label keys, and metric-specific labels. A future recording backend can extend the existing package behind the same `MeterPortInterface` and `MetricsRendererInterface` ports, but only after implementing catalog validation, bounded cardinality, and explicit state/reset lifecycle ownership. A future renderer may replace the selected binding without introducing a second baseline null renderer.

## Errors

Contract-typed calls to `increment()` and `observe()` are no-throw. Globally forbidden label keys or invalid label-value types cause deterministic silent skips, without warnings, logging, additional metrics, raw diagnostics, or changes to the primary operation's result or exception precedence.

`NullMetricsRenderer::contentType()` and `render()` are deterministic and no-throw. Programming errors outside the typed public contracts are not silently reclassified as metric admission failures or masked by blanket exception suppression.

## Security / Redaction

Global label-key admission does not establish the safety of a value. Metric producers MUST derive stable, bounded-cardinality values and MUST NOT supply raw request paths, URL queries, headers, cookies, session or correlation IDs, authorization tokens, SQL, bodies, profile payloads, arbitrary user identifiers, or private customer data as metric labels.

A hash, truncated value, length, or redacted summary is not automatically an authorized metric label. Redaction MUST NOT add keys or override metric-specific catalog restrictions. This baseline does not capture, log, persist, export, or render rejected inputs or accepted metric values, and it introduces no independent redactor or security policy registry.

For vulnerability reporting and supported disclosure procedures, use the package's unmodified [Security Policy](./SECURITY.md).

## Non-goals

- Active metric recording, registry ownership, sample buffering, aggregation, export, persistence, and queryable metrics.
- Prometheus or other vendor SDK integrations, HTTP routes, scraping endpoints, middleware, and response adapters.
- Runtime-selected metrics backends, renderer modes, configurable labels, metric catalogs, and additional configuration keys.
- Metric-specific runtime catalog enforcement, safe-value derivation by the meter, or backend cardinality enforcement.
- ContextStore access, implicit request or correlation labels, new UnitOfWork owners, reset services, or lifecycle hooks.
- New metric/span names, label dimensions, diagnostics for rejected values, or recursive metrics reporting.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Metrics Policy SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/metrics-policy.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability.md)
- [Observability and Errors SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability-and-errors.md)
- [Modules and Manifests SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/modules-and-manifests.md)
- [Runtime Container Definitions SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/runtime-container-definitions.md)
- [Config Roots SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/config-roots.md)
- [Sensitive Data Redaction SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/sensitive-data-redaction.md)
- [ADR-0039: Platform metrics noop baseline](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0039-platform-metrics-noop-baseline.md)
- [Package Dependencies](https://github.com/coretsia/monorepo/blob/main/docs/architecture/DEPENDENCIES.md)
