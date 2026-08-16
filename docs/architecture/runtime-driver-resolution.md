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

# Runtime Driver Resolution Architecture

## Purpose

This document describes the package-level architecture of Kernel runtime-driver resolution.

The canonical compatibility policy is:

```text
docs/ssot/runtime-drivers.md
```

The decision record is:

```text
docs/adr/ADR-0027-runtime-driver-resolution.md
```

The central split is:

```text
runtime-driver matrix validity != runtime implementation executability
```

Kernel resolves and validates the canonical driver matrix.

Owner packages validate their own package/module prerequisites, adapter availability, transport readiness, and implementation readiness.

## Public API surface

The public Kernel runtime-driver symbols are listed in:

```text
framework/packages/core/kernel/PUBLIC_API.md
```

The resolution boundary is:

```text
Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver
```

with the exact public operation:

```php
RuntimeDriverResolver::resolve(
    ConfigRepositoryInterface $config,
    RuntimeDriverContributions $contributions,
): RuntimeDrivers
```

The related public Kernel symbols are:

```text
Coretsia\Kernel\Runtime\Driver\HttpDriver
Coretsia\Kernel\Runtime\Driver\BackgroundDriver
Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions
Coretsia\Kernel\Runtime\Driver\RuntimeDrivers
Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver
Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException
Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException
```

This API is Kernel policy, not a `core/contracts` runtime execution SPI.

External UnitOfWork execution remains owned by:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

## Ownership model

`RuntimeDriverResolver` owns:

- `kernel.runtime.http_driver` selection;
- canonical Kernel runtime-driver vocabulary;
- explicit contribution composition;
- HTTP-driver mutual exclusion;
- HTTP/background matrix compatibility;
- deterministic matrix conflict diagnostics;
- Kernel-owned selector invalid-config semantics;
- `RuntimeDrivers` result construction.

It does not own:

- `ModulePlan` compatibility;
- module/package discovery;
- external package requirements;
- adapter availability;
- transport availability;
- executable readiness;
- owner config normalization;
- owner service requirements;
- filesystem or service-container adapter probing.

## Composition flow

```text
                 OWNER PACKAGE

owner config
    |
    v
owner normalization
    |
    v
RuntimeDriverContributions
    |
    v
+------------------------------+
|         core/kernel          |
|                              |
| RuntimeDriverResolver        |
|                              |
| Kernel selector              |
| + contributions              |
| + conflict matrix            |
|                              |
|      RuntimeDrivers          |
+------------------------------+
    |
    v
owner adapter / runtime boundary
    |
    +-- own prerequisites
    +-- own module dependencies
    +-- adapter/transport readiness
    +-- execution
```

`RuntimeDriverContributions` crosses the owner-to-Kernel boundary only after owner input has been normalized.

It communicates canonical driver identity, not executable-runtime requirements.

## Kernel config boundary

The only runtime-driver selector read by `RuntimeDriverResolver` is:

```text
kernel.runtime.http_driver
```

Config access is limited to the public config repository operations required to read that selector.

The resolver does not read owner-package configuration such as:

```text
worker.task_type
```

It does not use config enumeration/source-explanation APIs to discover owner settings.

## Structural boundary

`RuntimeDriverResolver` is intentionally stateless and constructible without `ModulePlan`.

It must not perform:

```text
ModulePlan inspection
module graph lookup
Composer/package discovery
service-container probing
filesystem adapter probing
extension/process/environment probing
```

That structural constraint is locked by Kernel boundary tests.

## Worker integration

The Worker-owned public runtime entrypoint boundary is:

```text
Coretsia\Platform\Worker\Runtime\WorkerRuntimeEntrypointGuard
```

Its flow is:

```text
WorkerPoolSpec already materialized
        |
        v
WorkerRuntimeEntrypointGuard
        |
        +-- WorkerModule::MODULE_ID participation check
        |
        +-- WorkerRuntimeDriverContributions::fromSpec(...)
        |       queue -> bg.worker_queue
        |       http  -> http.worker
        |
        v
RuntimeDriverResolver
        |
        v
RuntimeDrivers
        |
        v
lazy supervisor / child runtime execution
```

The `platform.worker` precondition belongs to Worker.

Failure is Worker-owned:

```text
CORETSIA_WORKER_START_FAILED: worker-module-not-enabled
```

`http.worker` does not imply `platform.http` inside Kernel.

A concrete HTTP task-source owner owns any package/module/transport/readiness prerequisites required for its implementation.

## Matrix-valid versus executable

For example:

```text
RuntimeDrivers(http.roadrunner)
```

means:

```text
http.roadrunner is the canonical active HTTP driver
the driver matrix is internally coherent
```

It does not prove:

```text
a RoadRunner adapter package is installed
its module is enabled
its executable is available
its transport is configured
runtime execution will succeed
```

This distinction is normative.

## Deterministic failures

Kernel matrix conflicts use:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
```

Kernel selector invalid-config failures use:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
```

with current reasons:

```text
config-key-missing
config-key-invalid
```

Conflict diagnostics contain only safe canonical driver ids and remain deterministically sorted.

Owner prerequisite failures use owner-package taxonomies and must not be reclassified as Kernel invalid-config errors.

## DI boundary

`RuntimeDriverResolver` is a canonical stateless Kernel runtime definition.

Container/provider construction must not execute resolution or inspect runtime selector values.

Owner packages may depend on the public resolver through explicit DI edges.

They must not add generic requirement registries, capability registries, package callbacks, or package topology to Kernel.

## Change protocol

Changes to runtime-driver ids, Kernel selector semantics, contribution composition, matrix rules, or Kernel error semantics require coordinated updates to:

```text
docs/ssot/runtime-drivers.md
docs/adr/ADR-0027-runtime-driver-resolution.md
docs/architecture/runtime-driver-resolution.md
framework/packages/core/kernel/PUBLIC_API.md
Kernel runtime-driver tests
```

Changes to Worker task-type mapping or Worker entrypoint ownership also require updates to:

```text
docs/architecture/worker.md
docs/adr/ADR-0017-persistent-worker-supervisor-application-worker.md
framework/packages/platform/worker/README.md
Worker runtime-driver/entrypoint tests
```

## Rejected boundary shapes

Do not reintroduce package-specific Kernel checks such as:

```text
http.X -> platform.Y
```

Do not add generic Kernel registries for owner requirements/capabilities.

Do not add `ModulePlan`, required module ids, required service ids, package metadata, adapter names, or owner-policy callbacks to `RuntimeDriverContributions` or `RuntimeDriverResolver`.

## Non-goals

This document does not define:

- concrete HTTP adapters;
- concrete Worker task sources;
- queue/scheduler backends;
- process supervision;
- server configuration schemas;
- generated artifact schemas;
- deployment topology;
- production observability backend behavior.

## Cross-references

- [Runtime Drivers SSoT](../ssot/runtime-drivers.md)
- [ADR-0027: Runtime driver resolution and compatibility matrix](../adr/ADR-0027-runtime-driver-resolution.md)
- [Kernel Public API evidence](../../framework/packages/core/kernel/PUBLIC_API.md)
- [Worker Architecture](./worker.md)
- [ADR-0020: Kernel runtime UnitOfWork SPI](../adr/ADR-0020-kernel-runtime-uow-spi.md)
