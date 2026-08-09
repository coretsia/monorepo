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

# ADR-0027: Runtime driver and entrypoint guard

```yaml
adrVersion: 1
status: pre-accepted
owner: core/kernel
```

## Context

Coretsia supports multiple runtime execution modes.

Some runtime drivers execute HTTP units of work:

```text
http.classic
http.frankenphp
http.swoole
http.roadrunner
http.worker
```

Some runtime drivers execute background units of work:

```text
bg.worker_queue
```

Runtime driver composition must be deterministic before runtime entrypoint execution.

The key invariant is:

```text
exactly one HTTP driver may be active at a time
background drivers may run alongside compatible HTTP drivers
conflicts fail deterministically before runtime entrypoint execution
```

Runtime-driver composition has two explicit selection sources:

1. the Kernel-owned HTTP selector from the Phase-B config snapshot;
2. already-selected runtime-driver contributions supplied explicitly by owner packages.

The Kernel-owned runtime-driver input is:

```text
kernel.runtime.http_driver
```

Owner-selected drivers cross the package boundary through:

```text
Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions
```

`RuntimeDriverContributions` contains already-selected canonical drivers. It does not read config, inspect `ModulePlan`, discover packages, or know which package produced a contribution.

`worker.task_type` is owned by `platform/worker`.

The Worker package normalizes that input through `WorkerPoolSpec` and maps it to explicit contributions:

```text
worker.task_type=queue -> bg.worker_queue
worker.task_type=http  -> http.worker
```

Therefore:

- `core/kernel` does not own the `worker` config root;
- `core/kernel` does not define `worker.*` defaults;
- `core/kernel` does not validate the `worker` config subtree;
- `core/kernel` does not read `worker.task_type`;
- `core/kernel` does not inspect `ModulePlan` membership for `platform.worker` to infer Worker contributions;
- owner packages validate and normalize their own runtime inputs before creating contributions;
- owner packages that contribute no runtime drivers pass an explicit empty `RuntimeDriverContributions` object;
- `ModulePlan` is compatibility context, not a runtime-driver contribution discovery source;
- the current Kernel guard inspects `ModulePlan` only for the `platform.http` requirement;
- Worker entrypoints may enforce Worker-owned module preconditions before invoking the Kernel entrypoint guard.

Missing or invalid `worker.task_type` is a Worker-owned lifecycle-validation failure.

It is not a Kernel runtime-driver matrix invalid-config failure.

Runtime driver ids, Kernel selector policy, contribution composition, compatibility rules, and deterministic Kernel matrix failure semantics are owned by:

```text
docs/ssot/runtime-drivers.md
```

The Kernel implementation needs a concrete runtime-driver matrix guard, but external runtime execution already has a contracts-level SPI:

```php
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

This ADR decides the package/API boundary for the runtime-driver guard and prevents the introduction of a premature contracts-level runtime-driver port.

## Decision

Coretsia introduces a Kernel-owned runtime-driver matrix implementation, a public owner-contribution handoff object, and a separate public runtime entrypoint compatibility boundary.

Kernel-owned runtime-driver selection consumes the Phase-B config snapshot.

Owner packages select their own runtime drivers outside Kernel and pass them through explicit `RuntimeDriverContributions`.

The Kernel matrix composes:

```text
Kernel-selected HTTP driver + explicit owner RuntimeDriverContributions
```

`ModulePlan` MUST NOT be used to discover, infer, enable, disable, or synthesize owner contributions.

The current Kernel matrix inspects the caller-provided `ModulePlan` only to validate the `platform.http` requirement for the final composed HTTP driver.

The concrete public Kernel API class for runtime adapters and production boot paths is:

```php
Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard
```

The related public Kernel API symbols are:

```php
Coretsia\Kernel\Runtime\Driver\HttpDriver
Coretsia\Kernel\Runtime\Driver\BackgroundDriver
Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions
Coretsia\Kernel\Runtime\Driver\RuntimeDrivers
Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard
Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException
Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException
```

These symbols are intentionally registered in:

```text
framework/packages/core/kernel/PUBLIC_API.md
```

`RuntimeDriverGuard` is a Kernel-internal implementation detail behind `RuntimeEntrypointGuard`.

Runtime adapters and owner packages that have a resolved `ConfigRepositoryInterface`, caller-provided `ModulePlan`, and explicit `RuntimeDriverContributions` must use `RuntimeEntrypointGuard`.

Kernel production boot paths that have a resolved config snapshot and caller-provided `ModulePlan` must invoke this boundary with explicit `RuntimeDriverContributions`.

A Kernel-owned boot path that has no owner-package runtime-driver contributions must pass an explicit empty contribution object.

A production boot path that does not yet possess all required inputs must not infer them, synthesize owner contributions, or establish an implicit fallback policy for runtime adapters.

Callers must not call `RuntimeDriverGuard` directly.

It is not an external runtime execution SPI.

External runtime execution continues to use:

```php
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

This epic does not introduce a new `core/contracts` runtime-driver port.

In particular, this ADR does not introduce any of the following contracts:

```php
Coretsia\Contracts\Runtime\RuntimeDriverGuardInterface
Coretsia\Contracts\Runtime\RuntimeDriversInterface
Coretsia\Contracts\Runtime\RuntimeDriverResolverInterface
Coretsia\Contracts\Runtime\RuntimeDriverMatrixInterface
```

`core/kernel` may expose the concrete guard because the guard is Kernel-owned policy enforcement around Kernel runtime composition.

Adapters and entrypoints may call the Kernel public guard when they need Kernel-owned matrix validation.

They must not treat the guard as a replacement for the external UnitOfWork runtime SPI.

## Single source of truth decision

`docs/ssot/runtime-drivers.md` remains the single canonical source for:

- canonical runtime driver ids and categories;
- the Kernel-owned `kernel.runtime.http_driver` selector;
- the public `RuntimeDriverContributions` handoff contract;
- Kernel-selected and owner-contributed driver composition;
- HTTP driver mutual-exclusion rules;
- HTTP/background compatibility rules;
- final HTTP-driver `platform.http` compatibility requirements;
- deterministic Kernel runtime-driver matrix failure semantics;
- canonical Kernel runtime-driver matrix error codes and reason tokens;
- deterministic driver-id ordering;
- the boundary between owner input validation and Kernel matrix validation.

The current Worker-owned mapping is:

```text
worker.task_type=queue -> bg.worker_queue
worker.task_type=http  -> http.worker
```

The mapping is implemented and validated by `platform/worker`.

Kernel consumes only the resulting `RuntimeDriverContributions`.

The implementation must conform to that SSoT.

Implementation files may encode the current SSoT values in enums, value objects, guard branches, exceptions, and tests, but they do not supersede the SSoT.

Any future runtime driver id, config key, compatibility rule, default policy, or failure semantic change must update the SSoT first.

Runtime entrypoints must not introduce local compatibility matrices that conflict with the SSoT.

## Guard behavior decision

`RuntimeEntrypointGuard` is the public runtime-adapter and owner-package compatibility boundary.

It receives:

```text
ConfigRepositoryInterface
ModulePlan
RuntimeDriverContributions
```

It exposes two explicit operations:

```text
resolveEntrypointDrivers(...) → RuntimeDrivers
assertEntrypointAllowed(...)  → void
```

`resolveEntrypointDrivers(...)` returns the same canonical driver set that was validated against the caller-provided `ModulePlan`.

`assertEntrypointAllowed(...)` is an assertion-only wrapper around the resolver and discards the resolved result.

Internally, both operations delegate to the Kernel-owned `RuntimeDriverGuard`.

`RuntimeDriverGuard` derives the Kernel-selected HTTP driver only from Kernel-owned config.

It reads config only through:

```php
ConfigRepositoryInterface::get(...)
ConfigRepositoryInterface::has(...)
```

It must not call:

```php
ConfigRepositoryInterface::all()
ConfigRepositoryInterface::sourceOf(...)
ConfigRepositoryInterface::explain()
```

It must not read `worker.task_type` or any other owner-package config input.

It must not inspect `ModulePlan` to discover or infer owner contributions.

It must not inspect:

- environment variables;
- loaded PHP extensions;
- process names;
- CLI argv;
- ports;
- filesystem adapter presence;
- container services;
- reflection;
- generated artifacts;
- source config files;
- config source metadata.

`RuntimeDriverContributions` contains already-selected drivers only.

It must not read config, inspect `ModulePlan`, inspect container services, discover packages, inspect generated artifacts, or know which package produced it.

The Kernel guard owns:

- Kernel HTTP selector validation;
- deterministic composition with explicit contributions;
- HTTP-driver conflict detection;
- final HTTP-driver `platform.http` compatibility.

It does not own:

- Worker config normalization;
- Worker task-type validation;
- Worker package enablement inference;
- generic owner-package config validation;
- unknown `worker.*` key validation;
- Worker-owned defaults.

`platform/worker` owns the current mapping from normalized `WorkerPoolSpec::taskType()` to `RuntimeDriverContributions`.

Missing or invalid Worker task type fails through Worker exception policy before Kernel matrix evaluation.

`platform/worker` exposes `WorkerRuntimeEntrypointGuard` as its Worker-owned public compatibility boundary.

That boundary validates the `platform.worker` module precondition, maps the already-normalized `WorkerPoolSpec` to explicit contributions through the package-internal mapper, and then invokes the Kernel entrypoint guard.

`WorkerStartCommand` and the shipped Worker child launcher must not perform that mapping or call the Kernel guard directly. Task-source implementations are resolved only after the Worker-owned compatibility boundary passes.

## Method boundary decision

The public runtime entrypoint boundary exposes:

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

`resolveEntrypointDrivers(...)` is the canonical query-and-validation operation.

It must return the exact `RuntimeDrivers` set that was composed and validated against the caller-provided `ModulePlan`.

`assertEntrypointAllowed(...)` is a thin assertion-only wrapper around `resolveEntrypointDrivers(...)`.

A caller must not invoke both methods for the same entrypoint attempt.

Both public methods must be invoked only after config, `ModulePlan`, and explicit owner contributions are available.

They must not resolve config, resolve `ModulePlan`, read owner-package config, inspect env, inspect container services, read artifacts, start `KernelRuntime`, or synthesize owner contributions.

The following `RuntimeDriverGuard` methods are internal Kernel implementation details.

Kernel-only detection:

```php
detect(ConfigRepositoryInterface $cfg): RuntimeDrivers
```

`detect()` reads only `kernel.runtime.http_driver`.

It does not include owner contributions.

Config-only assertion:

```php
assertCompatible(ConfigRepositoryInterface $cfg): void
```

`assertCompatible()` validates only Kernel-owned runtime-driver config and does not inspect `ModulePlan` or owner contributions.

Explicit composition:

```php
resolve(
    ConfigRepositoryInterface $cfg,
    RuntimeDriverContributions $contributions,
): RuntimeDrivers
```

`resolve()` composes the Kernel-selected HTTP driver with explicit owner contributions.

Module-aware resolution and validation:

```php
resolveForModules(
    ConfigRepositoryInterface $cfg,
    ModulePlan $plan,
    RuntimeDriverContributions $contributions,
): RuntimeDrivers
```

`resolveForModules()` is the only current internal Kernel guard method that validates `platform.http` compatibility.

It first resolves the complete driver set from Kernel config and explicit contributions and then validates that exact set against the caller-provided `ModulePlan`.

It must not resolve `ModulePlan` internally.

It must not inspect `platform.worker` to infer contributions.

It must not inspect Composer metadata, providers, package paths, module manifests, generated artifacts, config source files, or container services.

## ModulePlan compatibility decision

Owner contributions are supplied explicitly.

The Kernel guard MUST NOT select or suppress contributions based on `platform.worker` membership in `ModulePlan`.

The current Worker package may contribute:

```text
http.worker
bg.worker_queue
```

through the package-internal `WorkerRuntimeDriverContributions` mapper.

The public Worker-owned boundary is:

```text
Coretsia\Platform\Worker\Runtime\WorkerRuntimeEntrypointGuard
```

It owns the precondition that `platform.worker` must be enabled before Worker runtime execution starts.

When that owner precondition fails, the Worker runtime caller surfaces:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
requires-platform-worker-module
```

This failure occurs inside `WorkerRuntimeEntrypointGuard` before delegation to the Kernel `RuntimeEntrypointGuard` matrix evaluation.

After explicit contributions have been supplied, the final composed HTTP driver determines the `platform.http` requirement.

The following final HTTP drivers require `platform.http`:

```text
http.frankenphp
http.swoole
http.roadrunner
http.worker
```

The following drivers do not require `platform.http`:

```text
http.classic
bg.worker_queue
```

`bg.worker_queue` is a background runtime driver.

It must not be treated as an HTTP driver.

It must not satisfy the `platform.http` requirement for a selected non-classic HTTP driver.

The guard must not silently downgrade a selected or contributed non-classic HTTP driver to `http.classic`.

Missing `platform.http` for the final composed non-classic HTTP driver must fail deterministically.

## Error code decision

Runtime driver matrix conflicts use this deterministic error code:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
```

Runtime driver invalid-config failures use this deterministic error code:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
```

The public exception messages must be deterministic and safe.

The canonical message shape is:

```text
<ERROR_CODE>: <reason>
```

For example:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT: worker-http-conflicts-with-http-driver
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG: requires-platform-http-module
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG: config-key-missing
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG: config-key-invalid
```

Missing or invalid `worker.task_type` is not a Kernel matrix invalid-config failure.

The current Worker package surfaces that owner-validation failure as:

```text
CORETSIA_WORKER_LIFECYCLE_FAILED: worker-invalid-state
```

Diagnostics may expose only stable safe data:

- canonical runtime driver ids;
- canonical required module ids;
- stable reason tokens.

Diagnostics must not expose:

- raw config dumps;
- config source metadata;
- config paths;
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

Diagnostic runtime driver id lists must be sorted by byte-order comparison:

```php
strcmp($left, $right)
```

Required module id lists must also be sorted by byte-order comparison.

## Public API boundary decision

`RuntimeEntrypointGuard` is intentionally public in `core/kernel`.

`RuntimeDriverContributions` is also intentionally public in `core/kernel`.

It is the stable handoff object through which owner packages provide already-selected canonical drivers without exposing owner config to Kernel.

This public API exists so Kernel-owned runtime entrypoints, platform packages, and adapter wiring can invoke the canonical Kernel runtime-entrypoint compatibility boundary without duplicating matrix logic.

`RuntimeDriverGuard` is intentionally internal.

It remains the Kernel-owned implementation detail that validates the Kernel selector, composes explicit owner contributions, detects HTTP-driver conflicts, and validates final HTTP-driver `platform.http` compatibility behind `RuntimeEntrypointGuard`.

This does not promote runtime-driver matrix enforcement to `core/contracts`.

The contracts package remains the owner of external adapter-facing runtime execution ports.

The Kernel package remains the owner of concrete runtime-driver matrix enforcement.

The public Kernel API boundary is therefore:

```text
runtime entrypoint compatibility boundary: core/kernel public API
runtime-driver matrix implementation: core/kernel internal implementation detail
runtime UnitOfWork execution SPI: core/contracts public API
runtime-driver matrix rules: docs/ssot/runtime-drivers.md
```

## Consequences

Runtime driver matrix enforcement has one Kernel-owned implementation behind a public entrypoint boundary.

Runtime entrypoints must call `RuntimeEntrypointGuard` rather than implement local conflict checks or call `RuntimeDriverGuard` directly.

Public error handling can rely on deterministic code-first exception semantics.

Tests must verify:

- config-only Kernel selector detection;
- missing, non-string, and unsupported `kernel.runtime.http_driver` failures;
- explicit empty contributions preserve the Kernel-selected driver;
- Kernel-owned production boot paths pass explicit empty contributions when no owner package contributes runtime drivers;
- `http.classic` is replaced by one contributed HTTP driver;
- a contributed HTTP driver conflicts with a Kernel-selected non-classic HTTP driver;
- background contributions are preserved alongside compatible HTTP drivers;
- an HTTP contribution may coexist with a background contribution at the generic Kernel matrix level;
- contribution and diagnostic driver ids are sorted by byte-order `strcmp`;
- `RuntimeDriverGuard` does not read `worker.task_type`;
- `RuntimeDriverGuard` does not infer contributions from `platform.worker`;
- final non-classic HTTP drivers require `platform.http`;
- missing or invalid `worker.task_type` is tested in `platform/worker`;
- Worker task type maps deterministically to `RuntimeDriverContributions`;
- `WorkerRuntimeEntrypointGuard` performs the `platform.worker` precondition before runtime execution;
- Worker production callers use `WorkerRuntimeEntrypointGuard` rather than importing the internal mapper or invoking the Kernel guard directly;
- HTTP task-source readiness occurs only after `WorkerRuntimeEntrypointGuard` has passed;
- `resolveEntrypointDrivers(...)` returns the same composed set that passed module compatibility validation;
- `assertEntrypointAllowed(...)` is an assertion-only `void` wrapper.

Changing driver ids, config keys, activation rules, or error code names requires updating:

```text
docs/ssot/runtime-drivers.md
```

before implementation changes.

Adding a contracts-level runtime-driver port requires a future ADR.

## Non-goals

This ADR does not define:

- concrete HTTP adapter implementations;
- concrete worker runtime implementation;
- queue backend behavior;
- scheduler behavior;
- process supervision;
- RoadRunner configuration schema;
- Swoole server configuration schema;
- FrankenPHP server configuration schema;
- socket binding;
- port selection;
- generated artifact schemas;
- package filesystem scanning;
- container orchestration policy;
- production observability backend implementation.

This ADR does not introduce `worker.*` root ownership in `core/kernel`.

This ADR does not introduce a new `core/contracts` runtime-driver interface.

This ADR does not replace the external UnitOfWork runtime SPI.

## Cross-references

- [Runtime Drivers SSoT](../ssot/runtime-drivers.md)
- [Config Roots Registry](../ssot/config-roots.md)
- [Kernel Public API evidence](../../framework/packages/core/kernel/PUBLIC_API.md)
- [ADR-0020: Kernel runtime UnitOfWork SPI](./ADR-0020-kernel-runtime-uow-spi.md)
