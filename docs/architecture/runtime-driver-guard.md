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

The canonical source for runtime driver ids, canonical config input keys, activation rules, compatibility rules, missing `worker.*` policy, and deterministic matrix decision rules is:

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

- canonical runtime driver ids;
- runtime driver categories;
- canonical matrix input config keys;
- active-driver selection rules;
- HTTP driver mutual-exclusion rules;
- HTTP/background compatibility rules;
- missing `worker.*` key policy before `1.360.0`;
- deterministic runtime-driver matrix failure semantics;
- canonical runtime-driver matrix error code names.

This architecture document owns only a package-level explanation of how the Kernel implementation is structured around that SSoT.

If this document conflicts with `docs/ssot/runtime-drivers.md`, the SSoT wins.

## Decision record

The public API and package-boundary decision is recorded by:

```text
docs/adr/ADR-0027-runtime-driver-guard.md
```

ADR-0027 records that:

- `RuntimeDriverGuard` is public Kernel API;
- no new `core/contracts` runtime-driver port is introduced by this epic;
- `docs/ssot/runtime-drivers.md` remains the single canonical source for driver ids, config keys, and matrix rules;
- deterministic runtime-driver matrix failures use code-first exception semantics.

## Public API surface

The Kernel runtime-driver guard API is package-public and listed in:

```text
framework/packages/core/kernel/PUBLIC_API.md
```

The public symbols are:

```text
Coretsia\Kernel\Runtime\Driver\HttpDriver
Coretsia\Kernel\Runtime\Driver\BackgroundDriver
Coretsia\Kernel\Runtime\Driver\RuntimeDrivers
Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard
Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException
Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException
```

`RuntimeDriverGuard` is public Kernel API.

It is not a `core/contracts` runtime SPI.

External UnitOfWork execution remains owned by:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

Runtime-driver matrix validation remains owned by `core/kernel`.

## Guard responsibilities

`RuntimeDriverGuard` is responsible for:

- deriving active runtime drivers from canonical config inputs;
- enforcing single-HTTP-driver selection;
- returning `RuntimeDrivers` for valid config-only selections;
- failing deterministically for conflicting selections;
- validating the `platform.http` ModulePlan requirement for selected non-classic HTTP drivers;
- exposing safe deterministic exception data.

The guard is stateless.

The guard must not keep mutable runtime state, cache detected drivers, cache config, cache ModulePlan values, inspect adapters, inspect the environment, or read generated artifacts.

## Config input boundary

The guard reads merged config through:

```text
Coretsia\Contracts\Config\ConfigRepositoryInterface
```

The guard may read config only through:

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

Generic config shape validation remains outside the guard.

The guard must not become a generic config validator.

Unknown-key validation for `kernel.runtime.*` is owned by Kernel config rules.

Generic `worker.*` root shape and unknown-key validation is owned by the future `platform/worker` owner epic.

## ModulePlan boundary

The guard has one module-aware method:

```text
assertHttpDriverCompatibleWithModules(ConfigRepositoryInterface $cfg, ModulePlan $plan): void
```

This method receives a caller-provided `ModulePlan`.

It must not resolve `ModulePlan` internally.

It must not inspect Composer metadata, providers, package paths, module manifests, generated artifacts, config source files, or container services.

The module compatibility check compares module ids by canonical string value.

The canonical module requirement itself is defined by:

```text
docs/ssot/runtime-drivers.md
```

This document intentionally does not restate the full matrix.

## Expected callers

Future runtime entrypoints and runtime owners are expected to call `RuntimeDriverGuard` before starting runtime execution.

Expected caller categories:

- future worker command surfaces, such as `coretsia worker:start`;
- future long-running HTTP runtime entrypoints, such as FrankenPHP, Swoole, and RoadRunner entrypoints;
- Kernel-owned boot or runtime paths that need to enforce runtime-driver matrix validity before entrypoint execution;
- integration or platform packages that need Kernel-owned matrix validation without duplicating matrix rules.

Callers are responsible for supplying:

- a merged `ConfigRepositoryInterface`;
- a caller-resolved `ModulePlan` when module compatibility must be checked.

Callers must not implement competing local runtime-driver matrices.

Callers must not silently ignore guard failures.

## Deterministic errors

Runtime-driver matrix conflicts use:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
```

Runtime-driver invalid-config failures use:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
```

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

The runtime-driver guard is public in `core/kernel` because it enforces Kernel-owned runtime composition policy.

This epic does not introduce a new `core/contracts` runtime-driver port.

Do not introduce any of the following without a future ADR:

```text
Coretsia\Contracts\Runtime\RuntimeDriverGuardInterface
Coretsia\Contracts\Runtime\RuntimeDriversInterface
Coretsia\Contracts\Runtime\RuntimeDriverResolverInterface
Coretsia\Contracts\Runtime\RuntimeDriverMatrixInterface
```

The current boundary is:

```text
runtime-driver matrix checking: core/kernel public API
runtime UnitOfWork execution SPI: core/contracts public API
runtime-driver matrix rules: docs/ssot/runtime-drivers.md
```

## Wiring boundary

`RuntimeDriverGuard` may be registered in Kernel DI as a factory-only stateless service.

Provider registration must not:

- run guard detection;
- inspect runtime-driver config values;
- resolve `ModulePlan`;
- read generated artifacts;
- emit stdout or stderr;
- log directly;
- start a UnitOfWork;
- start a runtime loop.

Actual guard execution belongs to runtime or entrypoint paths, not provider registration.

## Change protocol

Any behavioral change to runtime-driver selection, compatibility, diagnostics, required modules, config keys, driver ids, missing-key policy, or deterministic error semantics MUST update all of the following:

```text
docs/ssot/runtime-drivers.md
```

```text
Kernel unit/integration locks
```

```text
framework/tools/tests/Fixtures/RuntimeDriverMatrix/*
```

E2E matrix fixture tests under `framework/tools/tests/Fixtures/RuntimeDriverMatrix/*` must stay aligned with the SSoT and Kernel guard behavior.

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
