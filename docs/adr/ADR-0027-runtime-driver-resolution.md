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

# ADR-0027: Runtime driver resolution and compatibility matrix

```yaml
adrVersion: 1
status: pre-accepted
owner: core/kernel
```

## Context

Coretsia has a Kernel-level runtime-driver vocabulary for HTTP and background execution modes:

```text
http.classic
http.frankenphp
http.swoole
http.roadrunner
http.worker

bg.worker_queue
```

The runtime-driver matrix must answer a narrow question before runtime execution:

```text
Which canonical runtime drivers are active, and is that driver set internally coherent?
```

The matrix has two explicit selection sources:

1. the Kernel-owned `kernel.runtime.http_driver` selector from the Phase-B config snapshot;
2. already-selected canonical driver contributions supplied explicitly by owner packages through `RuntimeDriverContributions`.

Runtime-driver identity MUST NOT be interpreted by Kernel as a concrete external package/module requirement. Reasoning of the form:

```text
http.X -> platform.Y must be enabled
```

is outside the runtime-driver matrix because it creates a semantic package-boundary leak. Kernel owns runtime-driver vocabulary and matrix semantics, not implementation package topology.

The required architectural split is:

```text
A. Runtime-driver matrix is valid.
B. Selected runtime implementation is executable.
```

Kernel owns `A` only.

Owner packages and adapters own `B`, including package/module prerequisites, adapter availability, transport availability, and implementation readiness.

`worker.task_type` is therefore Worker-owned input. `platform/worker` normalizes it through `WorkerPoolSpec` and maps it to canonical contributions:

```text
worker.task_type=queue -> bg.worker_queue
worker.task_type=http  -> http.worker
```

Kernel consumes only the resulting `RuntimeDriverContributions`.

## Decision

The canonical public Kernel runtime-driver resolution boundary is:

```text
Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver
```

Its public operation is exactly:

```php
RuntimeDriverResolver::resolve(
    ConfigRepositoryInterface $config,
    RuntimeDriverContributions $contributions,
): RuntimeDrivers
```

`RuntimeDriverResolver` owns:

- validation of the Kernel-owned `kernel.runtime.http_driver` selector;
- Kernel runtime-driver vocabulary;
- composition of the Kernel-selected HTTP driver with explicit owner contributions;
- HTTP-driver mutual exclusion;
- HTTP/background composition;
- deterministic runtime-driver conflict detection;
- deterministic Kernel-owned invalid-config semantics;
- canonical resolved `RuntimeDrivers` identity.

`RuntimeDriverResolver` does not own:

- `ModulePlan` compatibility for runtime adapters;
- external package/module requirements;
- package discovery;
- service-container probing;
- filesystem adapter probing;
- adapter availability;
- transport availability;
- executable readiness;
- owner-package config normalization.

Runtime-driver identity MUST NOT encode or imply a concrete package dependency inside Kernel.

A successful `RuntimeDriverResolver::resolve(...)` call means only:

```text
the canonical runtime-driver matrix is internally coherent
```

It does not mean:

```text
the selected adapter package is installed
its module is enabled
its binary/extension is available
its transport is ready
runtime execution is guaranteed to succeed
```

## Owner contribution decision

Owner-selected canonical drivers cross the package boundary through:

```text
Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions
```

`RuntimeDriverContributions` communicates selected canonical runtime drivers only.

It MUST NOT carry:

- required module ids;
- required service ids;
- package metadata;
- adapter names;
- executable requirements;
- callbacks that inspect `ModulePlan`;
- package/readiness policy.

Owner packages validate and normalize their own runtime inputs before constructing contributions.

An owner that contributes no runtime drivers supplies an explicit empty object:

```php
RuntimeDriverContributions::fromDrivers(
    httpDrivers: [],
    backgroundDrivers: [],
)
```

Kernel MUST NOT infer owner contributions from `ModulePlan`, Composer metadata, generated artifacts, service-container contents, filesystem state, loaded extensions, process names, or environment probing.

## Worker ownership decision

The Worker-owned runtime entrypoint boundary is:

```text
Coretsia\Platform\Worker\Runtime\WorkerRuntimeEntrypointGuard
```

It owns the `platform.worker` participation precondition.

If that precondition fails, Worker surfaces:

```text
CORETSIA_WORKER_START_FAILED: worker-module-not-enabled
```

The Worker-owned mapping is:

```text
WorkerPoolSpec(task_type=queue) -> bg.worker_queue
WorkerPoolSpec(task_type=http)  -> http.worker
```

through the package-internal:

```text
Coretsia\Platform\Worker\Internal\WorkerRuntimeDriverContributions
```

After the Worker precondition passes, `WorkerRuntimeEntrypointGuard` delegates only the canonical matrix operation to `RuntimeDriverResolver`.

`http.worker` does not imply `platform.http` inside Kernel.

Any concrete HTTP task-source package/module prerequisite is owned by the concrete task-source owner and must be validated or declared before readiness/execution at that owner boundary.

`platform/worker` MUST NOT acquire a direct dependency on `platform/http` merely to make `http.worker` valid.

## Public API decision

The public Kernel runtime-driver API is:

```text
Coretsia\Kernel\Runtime\Driver\HttpDriver
Coretsia\Kernel\Runtime\Driver\BackgroundDriver
Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions
Coretsia\Kernel\Runtime\Driver\RuntimeDrivers
Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver
Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException
Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException
```

These symbols are intentionally listed in:

```text
framework/packages/core/kernel/PUBLIC_API.md
```

The public Kernel runtime-driver API contains no generic runtime-entrypoint compatibility facade.

This ADR does not introduce a contracts-level runtime-driver port. External UnitOfWork execution continues to use:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

In particular, do not introduce without a future ADR:

```text
Coretsia\Contracts\Runtime\RuntimeDriversInterface
Coretsia\Contracts\Runtime\RuntimeDriverResolverInterface
Coretsia\Contracts\Runtime\RuntimeDriverMatrixInterface
```

## Deterministic failures

Runtime-driver conflicts use:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
```

Kernel-owned runtime-driver invalid config uses:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
```

The only current Kernel invalid-config reason tokens are:

```text
config-key-missing
config-key-invalid
```

Conflict diagnostics remain Kernel-owned matrix semantics. Safe diagnostics may include canonical active/conflicting driver ids sorted by byte-order `strcmp`.

Kernel invalid-config errors MUST NOT carry owner-package/module diagnostics.

Owner prerequisite failures use owner-package failure taxonomies. They are not reclassified as Kernel matrix invalid-config failures.

## Single source of truth

`docs/ssot/runtime-drivers.md` is the canonical source for:

- canonical runtime driver ids and categories;
- `kernel.runtime.http_driver` selection semantics;
- explicit owner contribution composition;
- HTTP-driver mutual exclusion;
- HTTP/background matrix compatibility;
- deterministic Kernel matrix failure semantics;
- canonical Kernel error codes/reason tokens;
- deterministic driver-id ordering;
- the boundary between matrix validity and runtime executability;
- owner-prerequisite responsibility.

Implementation and tests must conform to that SSoT.

## Rejected designs

### Kernel package-specific checks

Rejected:

```text
http.X -> platform.Y
```

inside Kernel runtime-driver resolution.

Reasons:

- semantic dependency inversion;
- god-package growth;
- adapter topology becomes frozen into Kernel;
- a domain runtime id becomes an implicit package locator.

### Generic runtime requirement registry

Rejected generic abstractions such as:

```text
RuntimeRequirementProvider
RuntimeCompatibilityRegistry
RuntimeModuleRequirements
```

A registry would merely move arbitrary external-package policy through Kernel rather than remove the semantic dependency.

### ModulePlan as RuntimeDriverResolver input

Rejected because runtime-driver matrix validity does not own package/module executability.

`RuntimeDriverResolver` therefore receives exactly config plus explicit contributions, not `ModulePlan`.

### RuntimeDrivers containing requirements

Rejected because `RuntimeDrivers` represents resolved canonical driver identity, not implementation topology, package prerequisites, transport state, or executable readiness.

## Consequences

The Kernel package has a smaller and more stable ownership boundary:

```text
Kernel selector + canonical contributions + conflict matrix
```

Future adapter owners can add their own module/package/readiness policy without changing Kernel merely to teach it package topology.

Tests must preserve at minimum:

- classic, FrankenPHP, Swoole, and RoadRunner Kernel selector semantics;
- missing/non-string/unsupported selector failures;
- explicit empty contributions;
- Worker HTTP/background contributions;
- HTTP conflicts and deterministic diagnostic ordering;
- no owner-config inspection by Kernel;
- no package/module/container/filesystem probing in `RuntimeDriverResolver`;
- `http.worker` without `platform.http` as a valid Kernel matrix composition;
- Worker-owned `platform.worker` precondition before Kernel resolution;
- Worker-owned task-type validation;
- owner and Kernel failure taxonomies remaining distinct.

## Non-goals

This ADR does not define:

- concrete HTTP adapter implementations;
- concrete HTTP task-source prerequisites;
- concrete Worker task-source implementations;
- queue backend behavior;
- scheduler behavior;
- process supervision;
- RoadRunner/Swoole/FrankenPHP deployment configuration;
- socket binding or port selection;
- generated artifact schemas;
- deployment topology;
- production observability backend implementation.

## Cross-references

- [Runtime Drivers SSoT](../ssot/runtime-drivers.md)
- [Runtime Driver Resolution Architecture](../architecture/runtime-driver-resolution.md)
- [Kernel Public API evidence](../../framework/packages/core/kernel/PUBLIC_API.md)
- [Worker Architecture](../architecture/worker.md)
- [ADR-0017: Persistent worker supervisor and application worker](./ADR-0017-persistent-worker-supervisor-application-worker.md)
- [ADR-0020: Kernel runtime UnitOfWork SPI](./ADR-0020-kernel-runtime-uow-spi.md)
