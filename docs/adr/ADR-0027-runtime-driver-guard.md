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

## Status

Accepted.

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

Runtime driver activation is config-driven.

The canonical matrix input keys are:

```text
kernel.runtime.http_driver
worker.task_type
```

`worker.task_type` is a Worker-owned runtime-driver input used by the Kernel-owned runtime-driver matrix only when `platform.worker` is enabled in the resolved `ModulePlan`.

It is not a `core/kernel` config root ownership claim.

It also does not decide whether the `platform.worker` package participates in runtime composition.

Therefore:

- `core/kernel` does not own the `worker` config root;
- `core/kernel` does not define `worker.*` defaults;
- `core/kernel` does not validate the full `worker` subtree;
- package/module activation is owned by mode preset resolution and `ModulePlan`;
- package config flags must not decide whether a package participates in runtime composition;
- Worker-owned runtime-driver inputs are in scope only when `platform.worker` is enabled in the caller-provided `ModulePlan`;
- missing `worker.task_type` does not fail when `platform.worker` is not enabled in `ModulePlan`;
- when `platform.worker` is enabled in `ModulePlan`, `worker.task_type` is required;
- `worker.task_type` must be either `http` or `queue`;
- missing `worker.task_type` while `platform.worker` is enabled fails with `worker-task-type-missing`;
- invalid `worker.task_type` while `platform.worker` is enabled fails with `worker-task-type-invalid`;
- generic `worker.*` shape and unknown-key validation remains owned by the package that owns the `worker` root.

The runtime driver guard must not become a second source of truth for generic config validation.

Generic config shape validation is owned by declarative config rules.

Runtime driver ids, activation conditions, matrix compatibility, missing `worker.*` policy, and deterministic error code names are owned by:

```text
docs/ssot/runtime-drivers.md
```

The Kernel implementation needs a concrete runtime-driver matrix guard, but external runtime execution already has a contracts-level SPI:

```php
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

This ADR decides the package/API boundary for the runtime-driver guard and prevents the introduction of a premature contracts-level runtime-driver port.

## Decision

Coretsia introduces a Kernel-owned runtime-driver matrix implementation and a separate public runtime entrypoint compatibility boundary.

Runtime-driver matrix evaluation is Phase-B and ModulePlan-aware.

Package/module activation is owned exclusively by mode preset resolution and `ModulePlan`.

Package config flags MUST NOT decide whether a package participates in runtime composition.

Worker-owned config keys are consumed by the Kernel runtime-driver matrix only when `platform.worker` is enabled in the caller-provided `ModulePlan`.

The concrete public Kernel API class for runtime adapters and production boot paths is:

```php
Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard
```

The related public Kernel API symbols are:

```php
Coretsia\Kernel\Runtime\Driver\HttpDriver
Coretsia\Kernel\Runtime\Driver\BackgroundDriver
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

Runtime adapters and production boot paths must use `RuntimeEntrypointGuard`.

They must not call `RuntimeDriverGuard` directly.

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

- runtime driver ids;
- runtime driver categories;
- runtime driver activation conditions;
- canonical config and ModulePlan inputs;
- HTTP driver mutual-exclusion rules;
- HTTP/background compatibility rules;
- required runtime-driver input key policy, including owner-scoped Worker inputs;
- external runtime-owner input handling for `worker.task_type` and `platform.worker` owner scope;
- deterministic runtime-driver matrix failure semantics;
- canonical runtime-driver matrix error code names.

The implementation must conform to that SSoT.

Implementation files may encode the current SSoT values in enums, value objects, guard branches, exceptions, and tests, but they do not supersede the SSoT.

Any future runtime driver id, config key, compatibility rule, default policy, or failure semantic change must update the SSoT first.

Runtime entrypoints must not introduce local compatibility matrices that conflict with the SSoT.

## Guard behavior decision

`RuntimeEntrypointGuard` is the public runtime-adapter and production-entrypoint boundary.

Internally, it delegates runtime-driver matrix selection and ModulePlan compatibility enforcement to the Kernel-owned `RuntimeDriverGuard` implementation.

`RuntimeDriverGuard` derives active runtime drivers from Kernel-owned config values and, for owner-scoped runtime inputs, the caller-provided `ModulePlan`.

It must read config only through:

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

The guard owns runtime-driver matrix selection and compatibility checks only.

It must not own generic config shape validation.

It must not validate unknown `worker.*` keys.

It must not introduce `worker.*` defaults or rules in `core/kernel`.

When `platform.worker` is not enabled in the caller-provided `ModulePlan`, Worker-owned runtime-driver inputs are outside the active owner domain.

In that state, missing `worker.task_type` must not fail, and worker-derived runtime drivers must not be selected.

When `platform.worker` is enabled in the caller-provided `ModulePlan`, missing `worker.task_type` must fail deterministically with `worker-task-type-missing`.

## Method boundary decision

The public runtime entrypoint boundary exposes:

```php
RuntimeEntrypointGuard::assertEntrypointAllowed(ConfigRepositoryInterface $config, ModulePlan $modulePlan): void
```

This method must be invoked after config and `ModulePlan` are resolved and before runtime execution starts.

It must not resolve config, resolve `ModulePlan`, inspect env, inspect container services, read artifacts, start `KernelRuntime`, or fallback to `http.classic`.

The following `RuntimeDriverGuard` methods are internal Kernel implementation details.

The guard exposes a config-only detection method:

```php
detect(ConfigRepositoryInterface $cfg): RuntimeDrivers
```

`detect()` returns a `RuntimeDrivers` value object only for a valid single-HTTP-driver selection.

If more than one HTTP driver is active, `detect()` must throw:

```php
Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException
```

The guard exposes a config-only assertion method:

```php
assertCompatible(ConfigRepositoryInterface $cfg): void
```

`assertCompatible()` must not inspect `ModulePlan`.

It may delegate to `detect()`.

The guard exposes a module-aware assertion method:

```php
assertHttpDriverCompatibleWithModules(ConfigRepositoryInterface $cfg, ModulePlan $plan): void
```

This is the only guard method that validates `platform.http` / `ModulePlan` compatibility.

It must first derive active drivers through the ModulePlan-aware runtime-entrypoint selection logic.

This logic is deterministic and uses the same Kernel-owned driver activation rules as `detect()`, but it scopes Worker-owned runtime-driver inputs through the caller-provided `ModulePlan`.

It must not resolve `ModulePlan` internally.

It must inspect only the caller-provided `ModulePlan`.

It must compare module ids by canonical string value.

It must not inspect Composer metadata, providers, package paths, module manifests, generated artifacts, config source files, or container services.

## ModulePlan compatibility decision

The following worker-derived runtime drivers are in the Worker owner scope:

```text
http.worker
bg.worker_queue
```

They are selected only when `platform.worker` is enabled in the caller-provided `ModulePlan`.

When `platform.worker` is not enabled, Worker-owned runtime-driver inputs are outside the active owner domain.

In that state, the guard must not select worker-derived runtime drivers and must not fail because `worker.task_type` is missing.

Owner entrypoints such as `worker:start` may perform their own package/module precheck before invoking the runtime entrypoint guard.

After Worker owner-scope is satisfied, the following non-classic HTTP drivers require `platform.http` to be enabled in the caller-provided `ModulePlan`:

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

It must not satisfy the `platform.http` requirement for selected non-classic HTTP drivers.

The guard must not silently downgrade a selected non-classic HTTP driver to `http.classic` when `platform.http` is missing.

Missing `platform.http` for a selected non-classic HTTP driver must fail deterministically.

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
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG: worker-task-type-missing
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

This public API exists so Kernel-owned runtime entrypoints, platform packages, and adapter wiring can invoke the canonical Kernel runtime-entrypoint compatibility boundary without duplicating matrix logic.

`RuntimeDriverGuard` is intentionally internal.

It remains the Kernel-owned implementation detail that performs runtime-driver matrix detection and module compatibility checks behind `RuntimeEntrypointGuard`.

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

- config-only full-matrix detection and selector validation behavior;
- ModulePlan-aware Worker input scoping;
- deterministic `worker-task-type-missing` and `worker-task-type-invalid` failures;
- module-aware `platform.http` compatibility behavior;
- worker HTTP conflicts with Kernel-selected non-classic HTTP drivers.

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
