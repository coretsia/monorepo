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

# Runtime Driver Guard Architecture

## Purpose

This document is the architecture overview for the Kernel runtime-driver guard.

It explains:

- the public API surface;
- expected callers;
- deterministic failure codes;
- module-plan compatibility boundary;
- source-of-truth ownership;
- required update path for behavioral changes.

This document is intentionally not the compatibility matrix.

The canonical source for runtime driver ids, the Kernel-owned selector, owner-contribution composition, compatibility rules, owner-validation boundaries, and deterministic matrix decision rules is:

```text
docs/ssot/runtime-drivers.md
```

This overview MUST NOT duplicate or locally redefine the canonical runtime-driver matrix.

## Source-of-truth boundary

Runtime-driver compatibility is governed by:

```text
docs/ssot/runtime-drivers.md
```

That SSoT owns:

- canonical runtime driver ids and categories;
- the Kernel-owned `kernel.runtime.http_driver` selector;
- the public `RuntimeDriverContributions` handoff contract;
- Kernel-selected and owner-contributed driver composition;
- HTTP-driver mutual exclusion;
- HTTP/background category compatibility;
- final HTTP-driver `platform.http` requirements;
- deterministic runtime-driver matrix failures;
- canonical error codes and reason tokens;
- deterministic driver-id ordering;
- the boundary between owner validation and Kernel matrix validation.

This architecture document owns only a package-level explanation of how the Kernel implementation is structured around that SSoT.

If this document conflicts with `docs/ssot/runtime-drivers.md`, the SSoT wins.

## Decision record

The public API and package-boundary decision is recorded by:

```text
docs/adr/ADR-0027-runtime-driver-guard.md
```

ADR-0027 records that:

- `RuntimeEntrypointGuard` is public Kernel API;
- `RuntimeDriverContributions` is public Kernel API;
- `RuntimeDriverGuard` is a Kernel-internal implementation detail;
- owner packages select their own runtime drivers before calling Kernel;
- Kernel composes its selector with explicit owner contributions;
- `ModulePlan` is compatibility context, not contribution discovery;
- no new `core/contracts` runtime-driver port is introduced;
- `docs/ssot/runtime-drivers.md` remains the canonical matrix source;
- deterministic runtime-driver failures use code-first exception semantics.

## Public API surface

The public Kernel runtime-driver and entrypoint symbols are listed in:

```text
framework/packages/core/kernel/PUBLIC_API.md
```

The public symbols are:

```text
Coretsia\Kernel\Runtime\Driver\HttpDriver
Coretsia\Kernel\Runtime\Driver\BackgroundDriver
Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions
Coretsia\Kernel\Runtime\Driver\RuntimeDrivers
Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard
Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException
Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException
```

`RuntimeEntrypointGuard` is the public compatibility boundary.

`RuntimeDriverContributions` is the public owner-to-Kernel handoff object.

`RuntimeDriverGuard` is internal Kernel implementation and MUST NOT be listed as public API.

The runtime-driver API is not a `core/contracts` runtime execution SPI.

External UnitOfWork execution remains owned by:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

## Guard responsibilities

`RuntimeEntrypointGuard` is responsible for:

- accepting resolved config, caller-provided `ModulePlan`, and explicit `RuntimeDriverContributions`;
- invoking the Kernel-owned matrix implementation;
- exposing `resolveEntrypointDrivers(...)` for callers that need the validated `RuntimeDrivers`;
- exposing `assertEntrypointAllowed(...)` as an assertion-only `void` wrapper;
- ensuring both operations use the same composition and module-compatibility policy;
- providing one public Kernel entrypoint compatibility boundary.

The internal `RuntimeDriverGuard` is responsible for:

- validating the Kernel-owned HTTP selector;
- deriving the Kernel-selected HTTP driver;
- composing explicit HTTP and background contributions;
- replacing `http.classic` with one contributed HTTP driver;
- detecting multiple/conflicting HTTP drivers;
- preserving compatible background contributions;
- validating the final HTTP driver's `platform.http` requirement;
- producing safe deterministic exception data.

Neither guard resolves config or `ModulePlan`.

Neither guard discovers owner contributions.

`RuntimeDriverGuard` does not read `worker.task_type`.

Both guards are stateless and must not retain config, contributions, `ModulePlan`, resolved drivers, container instances, or mutable runtime state.

## Composition flow

```text
kernel.runtime.http_driver
  -> Kernel-selected HTTP driver

owner package input
  -> owner normalization
  -> RuntimeDriverContributions

Kernel-selected HTTP driver
  + RuntimeDriverContributions
  -> RuntimeDriverGuard::resolve(...)
  -> RuntimeDrivers

RuntimeDrivers
  + caller-provided ModulePlan
  -> platform.http compatibility
  -> allowed or deterministic failure
```

`ModulePlan` enters only after explicit owner contributions exist.

## Config input boundary

The internal Kernel guard reads merged config through:

```text
Coretsia\Contracts\Config\ConfigRepositoryInterface
```

The only runtime-driver selector read by Kernel is:

```text
kernel.runtime.http_driver
```

Config access is restricted to:

```text
ConfigRepositoryInterface::get(...)
ConfigRepositoryInterface::has(...)
```

The guard must not call:

```text
ConfigRepositoryInterface::all()
ConfigRepositoryInterface::sourceOf(...)
ConfigRepositoryInterface::explain()
```

Owner-package config is outside the Kernel guard boundary.

In particular, Kernel must not read:

```text
worker.task_type
```

`platform/worker` owns the `worker` config root, defaults, validation, normalization, and task-type-to-contribution mapping.

Generic config shape and unknown-key validation remain outside the guard.

## ModulePlan boundary

The public entrypoint boundary exposes:

```php
RuntimeEntrypointGuard::resolveEntrypointDrivers(
    ConfigRepositoryInterface $config,
    ModulePlan $modulePlan,
    RuntimeDriverContributions $runtimeDriverContributions,
): RuntimeDrivers
```

and:

```php
RuntimeEntrypointGuard::assertEntrypointAllowed(
    ConfigRepositoryInterface $config,
    ModulePlan $modulePlan,
    RuntimeDriverContributions $runtimeDriverContributions,
): void
```

The internal module-aware method is:

```php
RuntimeDriverGuard::resolveForModules(
    ConfigRepositoryInterface $cfg,
    ModulePlan $plan,
    RuntimeDriverContributions $contributions,
): RuntimeDrivers
```

All three operations receive a caller-provided `ModulePlan`.

`assertEntrypointAllowed(...)` delegates to `resolveEntrypointDrivers(...)`.

`resolveEntrypointDrivers(...)` delegates to `resolveForModules(...)`.

Both methods receive a caller-provided `ModulePlan`.

They must not resolve `ModulePlan` internally.

`ModulePlan` must not be used to discover, infer, enable, disable, or synthesize owner contributions.

The current Kernel implementation inspects only the canonical `platform.http` module id after the final HTTP driver has been composed.

The guard must not inspect Composer metadata, providers, package paths, module manifests, generated artifacts, config source files, or container services.

## Expected callers

Direct Kernel runtime adapters and owner-package compatibility boundaries call `RuntimeEntrypointGuard` after all required inputs have been resolved and before runtime execution starts.

Expected direct caller categories include:

- Worker-owned `WorkerRuntimeEntrypointGuard`;
- FrankenPHP, Swoole, and RoadRunner entrypoint boundaries;
- Kernel-owned production boot paths;
- platform or integration package boundaries that already possess explicit `RuntimeDriverContributions`.

Worker command surfaces and the shipped Worker child launcher call `WorkerRuntimeEntrypointGuard`, not the Kernel guard directly. Worker task-source resolution/readiness occurs after this compatibility boundary succeeds.

Every caller supplies:

- a merged `ConfigRepositoryInterface`;
- a caller-resolved `ModulePlan`;
- explicit `RuntimeDriverContributions`.

A caller with no owner contributions must pass:

```php
RuntimeDriverContributions::fromDrivers(
    httpDrivers: [],
    backgroundDrivers: [],
)
```

Owner packages must validate and normalize their own runtime inputs before constructing contributions.

Callers must not call `RuntimeDriverGuard` directly, duplicate the matrix, or silently ignore guard failures.

## Deterministic errors

Runtime-driver matrix conflicts use:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
```

Runtime-driver invalid-config failures use:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
```

Missing or invalid owner-package runtime input is not automatically a Kernel matrix invalid-config failure.

For the current Worker package, missing or invalid `worker.task_type` fails through Worker policy:

```text
CORETSIA_WORKER_LIFECYCLE_FAILED: worker-invalid-state
```

The current Worker module-owner precondition is enforced before Kernel matrix evaluation.

Public exception messages are deterministic and safe.

The canonical message shape is:

```text
<ERROR_CODE>: <reason>
```

Exception diagnostics may expose only stable safe values:

- canonical runtime driver ids;
- canonical required module ids;
- stable reason tokens.

Diagnostics must not expose:

- raw config dumps;
- config source metadata;
- env values;
- adapter internals;
- Composer payloads;
- ModulePlan dumps;
- generated artifact payloads;
- filesystem paths;
- process details;
- stack traces;
- previous throwable messages;
- secrets;
- PII.

Driver id diagnostics and required module id diagnostics must be sorted using byte-order comparison:

```text
strcmp
```

## Public API and contracts boundary

The public compatibility boundary is owned by `core/kernel`:

```text
Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard
```

The public owner-contribution handoff object is:

```text
Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions
```

The internal matrix implementation is:

```text
Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard
```

No runtime-driver port is introduced in `core/contracts`.

Do not introduce the following without a future ADR:

```text
Coretsia\Contracts\Runtime\RuntimeDriverGuardInterface
Coretsia\Contracts\Runtime\RuntimeDriversInterface
Coretsia\Contracts\Runtime\RuntimeDriverResolverInterface
Coretsia\Contracts\Runtime\RuntimeDriverMatrixInterface
```

The current boundary is:

```text
entrypoint compatibility: core/kernel public API
owner contribution handoff: core/kernel public API
matrix implementation: core/kernel internal
UnitOfWork execution SPI: core/contracts public API
matrix policy: docs/ssot/runtime-drivers.md
```

## Wiring boundary

`RuntimeEntrypointGuard` may be registered by Kernel DI as a factory-only stateless service.

`RuntimeDriverGuard` is not a public DI service.

It is constructed and owned internally by `RuntimeEntrypointGuard`.

An owner package may expose its own public wrapper around `RuntimeEntrypointGuard` when that wrapper owns package-specific input normalization or contribution mapping.

The current Worker wrapper is:

```text
Coretsia\Platform\Worker\Runtime\WorkerRuntimeEntrypointGuard
```

It may depend on the package-internal Worker contribution mapper, but external Worker callers must not.

Provider registration must not:

- execute guard validation;
- inspect runtime-driver config values;
- create owner contributions;
- resolve `ModulePlan`;
- read generated artifacts;
- emit stdout or stderr;
- log directly;
- start a UnitOfWork;
- start a runtime loop.

Actual guard execution belongs to explicit runtime and entrypoint paths.

## Change protocol

Any behavioral change to runtime-driver selection, compatibility, diagnostics, required modules, config keys, driver ids, missing-key policy, or deterministic error semantics MUST update all of the following:

```text
docs/ssot/runtime-drivers.md
docs/adr/ADR-0027-runtime-driver-guard.md
docs/architecture/runtime-driver-guard.md
```

```text
Kernel unit/integration locks
```

```text
framework/tools/tests/Fixtures/RuntimeDriverMatrix/*
```

E2E matrix fixture tests under `framework/tools/tests/Fixtures/RuntimeDriverMatrix/*` must stay aligned with the SSoT and Kernel guard behavior.

Changes to Worker-owned task-type mapping or Worker entrypoint ownership must also update:

```text
docs/architecture/worker.md
docs/adr/ADR-0017-persistent-worker-supervisor-application-worker.md
framework/packages/platform/worker/src/Runtime/WorkerRuntimeEntrypointGuard.php
framework/packages/platform/worker/src/Internal/WorkerRuntimeDriverContributions.php
framework/packages/platform/worker/tests/Unit/WorkerRuntimeDriverContributionsTest.php
framework/packages/platform/worker/tests/Contract/WorkerStartCommandContractTest.php
framework/packages/platform/worker/tests/Contract/CoretsiaWorkerChildLauncherContractTest.php
```

Implementation changes must not be treated as canonical until the SSoT and locks are updated.

## Non-goals

This document does not define:

- the canonical runtime-driver matrix;
- runtime driver ids beyond referencing the public API surface;
- activation rules;
- concrete HTTP runtime adapter implementations;
- concrete worker implementation;
- queue backend behavior;
- scheduler behavior;
- process supervision;
- RoadRunner configuration schema;
- Swoole server configuration schema;
- FrankenPHP server configuration schema;
- socket binding;
- port selection;
- generated artifact schemas;
- container orchestration policy;
- production observability backend behavior.

This document does not introduce `worker.*` root ownership in `core/kernel`.

This document does not introduce a `core/contracts` runtime-driver interface.

## Cross-references

- [Runtime Drivers SSoT](../ssot/runtime-drivers.md)
- [ADR-0027: Runtime driver guard](../adr/ADR-0027-runtime-driver-guard.md)
- [Kernel Public API evidence](../../framework/packages/core/kernel/PUBLIC_API.md)
- [ADR-0020: Kernel runtime UnitOfWork SPI](../adr/ADR-0020-kernel-runtime-uow-spi.md)
- [Worker Architecture](./worker.md)
