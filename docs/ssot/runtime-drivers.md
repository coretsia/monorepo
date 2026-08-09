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

# Runtime Drivers SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: repo
```

## Purpose

This document is the Single Source of Truth for Coretsia runtime driver compatibility.

In this document, a runtime driver means a Kernel-level HTTP or background execution mode.

It does not mean the package-internal OS process adapter selected by `platform/worker`.

It defines which HTTP runtime drivers and background runtime drivers may be active together.

The core invariant is:

```text
exactly one HTTP driver may be active at a time
background drivers may run alongside compatible HTTP drivers
conflicts fail deterministically before runtime entrypoint execution
```

This document is the normative source for the implemented Kernel runtime-driver matrix and runtime entrypoint compatibility boundary:

```text
1.350.0 Runtime Drivers / Runtime Entrypoint Guard
```

`RuntimeEntrypointGuard` and its internal runtime-driver matrix implementation MUST treat this document as the canonical compatibility matrix.

## Source-of-truth boundaries

This document owns:

- runtime driver ids;
- runtime driver categories;
- the Kernel-owned HTTP selector;
- the public owner-contribution handoff contract;
- Kernel-selected and owner-contributed driver composition;
- HTTP driver mutual-exclusion rules;
- allowed HTTP/background driver composition;
- final HTTP-driver `platform.http` requirements;
- deterministic Kernel runtime-driver matrix failure semantics;
- canonical Kernel matrix error codes and reason tokens;
- deterministic runtime-driver id ordering;
- the distinction between Kernel runtime drivers and Worker-internal OS process drivers;
- the boundary between owner input validation and Kernel matrix validation.

This document does not own concrete guard implementation mechanics.

Concrete implementation mechanics are owned by `core/kernel` source and tests:

```text
Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard
Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard
```

`RuntimeEntrypointGuard` is the public runtime-adapter boundary.

`RuntimeDriverGuard` is the internal Kernel implementation detail.

This document does not own runtime adapter implementation details.

This document also does not own Worker OS process-driver selection.

The Worker-internal values:

```text
auto
pcntl
proc
```

belong to `WorkerPoolSpec` and the `platform/worker` process-supervision architecture.

They do not participate in the Kernel runtime-driver matrix.

Runtime adapters are owned by later integration epics:

```text
20.x
```

This document does not introduce `worker.*` runtime implementation.

Worker runtime implementation and `worker.*` root ownership are owned by:

```text
platform/worker
```

`worker.task_type` is owned by `platform/worker`.

It is not a direct Kernel matrix input.

The Worker package normalizes it through `WorkerPoolSpec` and maps it to explicit `RuntimeDriverContributions`:

```text
worker.task_type=queue -> bg.worker_queue
worker.task_type=http  -> http.worker
```

Kernel receives only the resulting canonical drivers.

Kernel does not inspect `platform.worker` membership to infer, synthesize, enable, disable, or suppress Worker contributions.

Worker entrypoints may enforce their own module-owner preconditions before invoking the Kernel runtime entrypoint guard.

This document does not own UoW/reset implementation details. Long-running runtime state and reset discipline are governed by:

```text
docs/ssot/context-lifecycle.md
docs/ssot/reset-tags.md
docs/ssot/stateful-services.md
```

This document does not own generated artifact schemas or artifact production boundaries.

Artifact identity and ownership are governed by:

```text
docs/ssot/artifacts.md
docs/ssot/artifacts-and-fingerprint.md
docs/ssot/modules-and-manifests.md
```

This document MAY define composite artifact requirements for runtime entrypoints, but it MUST NOT redefine artifact ownership or present owner-package artifacts as Kernel-owned artifacts.

Every non-Kernel artifact requirement MUST identify:

- the owning package;
- the condition under which the artifact is required;
- the boundary responsible for production, path resolution, schema validation, and verification.

This document does not own config root registration rules. Config root ownership is governed by:

```text
docs/ssot/config-roots.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Terminology

A Kernel runtime driver is an activated application execution mode represented by a canonical runtime driver id.

An HTTP runtime driver is a Kernel runtime driver whose Unit of Work is an HTTP request.

A background runtime driver is a Kernel runtime driver whose Unit of Work is a background task, queue job, scheduled task, or similar non-HTTP cycle.

A Worker OS process driver is a package-internal adapter that starts, polls, terminates, kills, and closes one operating-system child process.

The canonical Worker OS process-driver implementations are:

```text
PcntlWorkerProcessDriver
ProcWorkerProcessDriver
```

The canonical Worker OS process-driver ids are:

```text
pcntl
proc
```

These ids are not Kernel runtime driver ids.

A runtime-driver contribution is an already-selected canonical runtime driver supplied explicitly by an owner package through:

```text
Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions
```

A contribution is not raw config.

It does not carry config paths, owner package ids, `ModulePlan` state, service instances, or discovery metadata.

The canonical HTTP driver ids are:

```text
http.classic
http.frankenphp
http.swoole
http.roadrunner
http.worker
```

The canonical background driver id introduced by this matrix is:

```text
bg.worker_queue
```

Reserved future background driver ids are:

```text
bg.queue
bg.scheduler
```

Reserved future ids are not part of the current Phase 1 guard inputs.

They MUST NOT be activated by the Phase 1 guard unless a later owner epic updates this SSoT.

## Worker internal OS process-driver boundary

Kernel runtime-driver selection and Worker OS process-driver selection are independent decisions.

Kernel runtime-driver selection answers:

```text
Which HTTP execution mode is active?
Which background execution modes are active?
Is their composition compatible?
```

Worker OS process-driver selection answers:

```text
How does the foreground Worker supervisor create and control one child process on this operating system?
```

The Worker-owned config value:

```text
worker.driver
```

may request:

```text
auto
pcntl
proc
```

`WorkerPoolSpec` deterministically resolves it to:

```text
pcntl
proc
```

This selection MUST NOT:

- create a Kernel `HttpDriver`;
- create a Kernel `BackgroundDriver`;
- enter `RuntimeDriverContributions`;
- affect HTTP-driver mutual exclusion;
- affect the Kernel `platform.http` requirement;
- introduce Kernel matrix error codes.

Separately, Worker task type maps to Kernel runtime-driver contributions:

```text
worker.task_type=queue -> bg.worker_queue
worker.task_type=http  -> http.worker
```

Therefore:

```text
worker.task_type
  -> Kernel runtime-driver contribution

worker.driver
  -> internal OS process adapter
```

The Kernel guard does not select `pcntl` or `proc`.

The Worker process supervisor does not independently evaluate the Kernel HTTP/background compatibility matrix.

Invalid Worker OS process-driver configuration and unsupported platform capability are Worker-owned deterministic failures.

Kernel runtime-driver conflicts remain Kernel-owned failures:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
```

## Canonical matrix inputs

Runtime entrypoint validation consumes three explicit inputs:

```text
Phase-B ConfigRepositoryInterface
RuntimeDriverContributions
ModulePlan
```

Runtime-driver selection and composition consume the Kernel selector and explicit contributions.

`ModulePlan` participates only in compatibility validation after composition.

The Phase-B config snapshot supplies only the Kernel-owned selector:

```text
kernel.runtime.http_driver
```

`RuntimeDriverContributions` supplies already-selected owner drivers.

`ModulePlan` supplies compatibility context.

It MUST NOT be used to discover or infer owner contributions.

The current Kernel implementation uses `ModulePlan` only to validate whether the final composed HTTP driver requires:

```text
platform.http
```

Owner packages MUST map their own runtime inputs to contributions before calling the Kernel entrypoint guard.

The current Worker-owned mapping is:

```text
worker.task_type=queue -> bg.worker_queue
worker.task_type=http  -> http.worker
```

`worker.task_type` itself is not read by Kernel.

Worker package/module activation is owned by mode presets, `ModulePlan`, and Worker entrypoint policy.

Worker process startup remains an explicit command action.

The guard MUST NOT decide active drivers by environment probing.

The guard MUST NOT inspect:

- loaded PHP extensions;
- server process names;
- CLI argv outside the caller contract;
- open ports;
- environment variables outside canonical config loading;
- filesystem runtime adapter presence;
- container service existence;
- generated artifact existence;
- reflection.

Generated artifacts MUST NOT participate in runtime-driver selection.

The resolved `ModulePlan` MAY be inspected only for explicit module compatibility requirements defined by this SSoT.

## Driver ids and activation rules

### `http.classic`

`http.classic` is the default classic request/response HTTP runtime mode.

It is selected by the Kernel-owned selector:

```text
kernel.runtime.http_driver = "http.classic"
```

It remains the final HTTP driver when no HTTP contribution is supplied.

When exactly one HTTP contribution is supplied, the contributed HTTP driver replaces `http.classic`.

More than one contributed HTTP driver is a conflict.

Conflicts:

```text
more than one contributed HTTP driver
```

May run alongside:

```text
any compatible background contribution
```

Notes:

- `http.classic` is the safe Kernel-owned default.
- `http.classic` MUST NOT require long-running runtime adapter boot.
- replacement is driven by explicit HTTP contributions, not by Kernel reading owner config.

### `http.frankenphp`

`http.frankenphp` is the FrankenPHP HTTP runtime mode.

It is selected by the Kernel-owned selector:

```text
kernel.runtime.http_driver = "http.frankenphp"
```

Conflicts:

```text
any contributed HTTP driver
```

May run alongside:

```text
bg.worker_queue
```

Notes:

- artifact-only boot MUST satisfy the composite, ownership-aware artifact contract defined in [Artifact-only boot boundary](#artifact-only-boot-boundary).
- UoW boundary MUST be enforced per request.
- Reset MUST run exactly once per UoW through Kernel runtime.
- Long-running runtime state MUST NOT leak across requests.

### `http.swoole`

`http.swoole` is the Swoole HTTP runtime mode.

It is selected by the Kernel-owned selector:

```text
kernel.runtime.http_driver = "http.swoole"
```

Conflicts:

```text
any contributed HTTP driver
```

May run alongside:

```text
bg.worker_queue
```

Notes:

- artifact-only boot MUST satisfy the composite, ownership-aware artifact contract defined in [Artifact-only boot boundary](#artifact-only-boot-boundary).
- UoW boundary MUST be enforced per request.
- Reset MUST run exactly once per UoW through Kernel runtime.
- Long-running loop state MUST NOT leak context or mutable state across requests.

### `http.roadrunner`

`http.roadrunner` is the RoadRunner HTTP runtime mode.

It is selected by the Kernel-owned selector:

```text
kernel.runtime.http_driver = "http.roadrunner"
```

Conflicts:

```text
any contributed HTTP driver
```

May run alongside:

```text
bg.worker_queue
```

Notes:

- artifact-only boot MUST satisfy the composite, ownership-aware artifact contract defined in [Artifact-only boot boundary](#artifact-only-boot-boundary).
- UoW boundary MUST be enforced per request.
- Reset MUST run exactly once per UoW through Kernel runtime.
- Long-running loop state MUST NOT leak context or mutable state across requests.

### `http.worker`

`http.worker` is an HTTP runtime mode executed through a Worker-owned runtime surface.

It is active only when supplied explicitly as an HTTP contribution.

The current Worker package produces that contribution from:

```text
worker.task_type=http
```

Kernel does not read that config input.

Conflicts:

```text
any Kernel-selected non-classic HTTP driver
any additional contributed HTTP driver
```

Notes:

- `http.worker` is an HTTP runtime mode;
- `http.worker` is not a background driver;
- `http.worker` participates in HTTP-driver mutual exclusion;
- `http.worker` MUST NOT be treated as `bg.worker_queue`;
- `http.worker` replaces `http.classic` when it is the only contributed HTTP driver;
- `http.worker` requires `platform.http` because it is the final HTTP runtime driver;
- Kernel does not require or infer `platform.worker` membership when composing this contribution;
- the Worker package owns the `platform.worker` module precondition through `WorkerRuntimeEntrypointGuard`.

### `bg.worker_queue`

`bg.worker_queue` is a background worker queue runtime mode.

It is active only when supplied explicitly as a background contribution.

The current Worker package produces that contribution from:

```text
worker.task_type=queue
```

Kernel does not read that config input.

Conflicts:

```text
none at the current matrix level
```

May run alongside:

```text
http.classic
http.frankenphp
http.swoole
http.roadrunner
http.worker
```

Notes:

- `bg.worker_queue` is a background driver;
- `bg.worker_queue` is not an HTTP driver;
- `bg.worker_queue` MUST NOT require `platform.http`;
- Kernel does not infer or validate `platform.worker` membership from this contribution;
- the Worker package owns the `platform.worker` module precondition through `WorkerRuntimeEntrypointGuard`;
- the current `WorkerRuntimeDriverContributions` mapper produces either `http.worker` or `bg.worker_queue` from one `WorkerPoolSpec`;
- the generic Kernel matrix nevertheless permits `http.worker` and `bg.worker_queue` together because one is HTTP and the other is background.

## Reserved future ids

The following ids are reserved for future owner epics and are not active Phase 1 guard inputs:

```text
bg.queue
bg.scheduler
```

The Phase 1 guard MUST NOT activate them.

A future epic MAY promote a reserved id into the active matrix only by updating this SSoT.

## Hard compatibility rules

The composition rules are:

1. `kernel.runtime.http_driver` selects exactly one Kernel-owned HTTP driver.
2. Zero contributed HTTP drivers preserve the Kernel-selected HTTP driver.
3. Exactly one contributed HTTP driver replaces `http.classic`.
4. Any contributed HTTP driver conflicts with a Kernel-selected non-classic HTTP driver.
5. More than one contributed HTTP driver is a conflict.
6. Exactly one final HTTP driver MUST exist after composition.
7. Background contributions MAY run alongside any final HTTP driver unless a future SSoT rule explicitly forbids the combination.
8. `http.worker` MUST NOT be treated as a background driver.
9. `bg.worker_queue` MUST NOT be treated as an HTTP driver.

The current canonical HTTP driver vocabulary is:

```text
http.classic
http.frankenphp
http.swoole
http.roadrunner
http.worker
```

The current canonical background driver vocabulary is:

```text
bg.worker_queue
```

The current Worker mapper produces one of:

```text
http.worker
bg.worker_queue
```

from a single normalized Worker task type.

That owner-mapping constraint does not create a general Kernel HTTP/background conflict rule.

## Default safety policy

The Kernel-owned safe default is:

```text
kernel.runtime.http_driver = "http.classic"
```

`kernel.runtime.http_driver` is owned by `core/kernel`.

It selects exactly one Kernel-owned HTTP runtime driver.

`http.worker` is not a valid value for `kernel.runtime.http_driver`.

Worker-owned defaults are owned entirely by `platform/worker`.

The current Worker-owned default is:

```text
worker.task_type = "queue"
```

Kernel does not read or invent this default.

When Worker builds a normalized `WorkerPoolSpec` from the merged Worker config, the current mapper converts that default to:

```text
bg.worker_queue
```

This does not start a worker process.

Worker process startup remains an explicit command action:

```text
worker:start
```

When no owner package contributes a runtime driver, callers pass explicit empty contributions.

With the Kernel default and empty contributions, the final active driver set is:

```text
http.classic
```

`ModulePlan` membership for `platform.worker` does not alter Kernel runtime-driver selection.

Kernel does not read, default, validate, activate, deactivate, or scope `worker.task_type`.

Worker contributions exist only when an owner package supplies them explicitly through `RuntimeDriverContributions`.

## Runtime-driver input presence policy

The Kernel runtime-driver matrix reads only the Kernel-owned selector from the Phase-B config snapshot:

```text
kernel.runtime.http_driver
```

That key MUST be present.

A missing selector MUST fail with:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
config-key-missing
```

A non-string or unsupported selector MUST fail with:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
config-key-invalid
```

Every public runtime entrypoint guard invocation MUST also provide a `RuntimeDriverContributions` object.

No owner contributions MUST be represented explicitly as:

```php
RuntimeDriverContributions::fromDrivers(
    httpDrivers: [],
    backgroundDrivers: [],
)
```

The Kernel guard MUST NOT treat omission of the contribution argument as an implicit empty contribution set.

Owner packages own the presence, type, allowed values, defaults, and normalization of their own runtime inputs.

For `platform/worker`, missing or invalid `worker.task_type` is a Worker-owned lifecycle-validation failure.

The current public failure is:

```text
CORETSIA_WORKER_LIFECYCLE_FAILED
worker-invalid-state
```

The following old Kernel matrix reason tokens are no longer valid:

```text
worker-task-type-missing
worker-task-type-invalid
```

The Kernel matrix MUST NOT read `worker.task_type`, validate unknown `worker.*` keys, or invent Worker defaults.

## Active driver resolution contract

The Kernel-selected HTTP driver is derived from:

```text
kernel.runtime.http_driver
```

The owner contribution sets are supplied explicitly as:

```text
RuntimeDriverContributions.httpDrivers
RuntimeDriverContributions.backgroundDrivers
```

HTTP composition is conceptually:

```text
no contributed HTTP drivers
    -> final HTTP = Kernel-selected HTTP driver

exactly one contributed HTTP driver
    + Kernel-selected http.classic
    -> final HTTP = contributed HTTP driver

one or more contributed HTTP drivers
    + Kernel-selected non-classic HTTP driver
    -> conflict

more than one contributed HTTP driver
    -> conflict
```

All background contributions are preserved in the resulting `RuntimeDrivers` value.

The final driver set is sorted by canonical id using byte-order `strcmp`.

The matrix MUST NOT produce a valid state with zero final HTTP drivers.

`ModulePlan` does not participate in driver selection.

After composition, `ModulePlan` is used only for final HTTP-driver compatibility validation.

## HTTP driver mutual-exclusion matrix

| Kernel HTTP selector | contributed HTTP drivers | Result   | Reason                                                        |
|----------------------|-------------------------:|----------|---------------------------------------------------------------|
| any selector         |                     none | allowed  | Kernel selection remains final                                |
| `http.classic`       |              exactly one | allowed  | contribution replaces classic                                 |
| `http.classic`       |            more than one | conflict | more than one contributed HTTP driver                         |
| non-classic HTTP     |              one or more | conflict | Kernel and contributed HTTP drivers are simultaneously active |

## HTTP/background compatibility matrix

| Final HTTP driver | `bg.worker_queue` | Result  |
|-------------------|-------------------|---------|
| `http.classic`    | present           | allowed |
| `http.frankenphp` | present           | allowed |
| `http.swoole`     | present           | allowed |
| `http.roadrunner` | present           | allowed |
| `http.worker`     | present           | allowed |

This table describes driver-category compatibility only.

Final non-classic HTTP drivers remain subject to the `platform.http` requirement defined below.

The current Worker task-type mapper does not emit `http.worker` and `bg.worker_queue` from the same `WorkerPoolSpec`.

That owner-level mapping constraint does not make the combination a Kernel matrix conflict.

Another owner package or future composition layer may contribute a compatible background driver independently of the Worker HTTP contribution.

## Concrete compatibility examples

Examples with a final non-classic HTTP driver assume that `platform.http` is enabled unless the example explicitly demonstrates the missing-module failure.

### Default classic

Kernel config:

```text
kernel.runtime.http_driver = "http.classic"
```

Owner contributions:

```text
none
```

### RoadRunner + queue worker

Kernel config:

```text
kernel.runtime.http_driver = "http.roadrunner"
```

Worker owner input:

```text
worker.task_type = "queue"
```

Explicit contribution:

```text
bg.worker_queue
```

### Swoole + queue worker

Kernel config:

```text
kernel.runtime.http_driver = "http.swoole"
```

Explicit contribution:

```text
bg.worker_queue
```

### FrankenPHP + queue worker

Kernel config:

```text
kernel.runtime.http_driver = "http.frankenphp"
```

Explicit contribution:

```text
bg.worker_queue
```

### RoadRunner + worker HTTP

Kernel config:

```text
kernel.runtime.http_driver = "http.roadrunner"
```

Explicit contribution:

```text
http.worker
```

Result:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
```

## Deterministic enforcement contract

The guard MUST decide active drivers by evaluating the Kernel-owned HTTP selector and explicit `RuntimeDriverContributions`.

The guard MUST NOT use environment probing to decide active drivers.

On conflict, the guard MUST fail deterministically.

The deterministic error `CODE` string is the primary failure semantic.

For HTTP driver conflicts, the canonical failure code is:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
```

Diagnostics, if emitted, MUST be stable.

Diagnostics MUST contain driver ids only.

For a conflict:

- `activeDriverIds` contains the Kernel-selected driver and all explicit HTTP/background contributions participating in the attempted composition;
- `conflictingDriverIds` contains only conflicting HTTP driver ids;
- both lists are sorted by byte-order `strcmp`.

Diagnostics MUST be sorted lexicographically using byte-order comparison:

```php
strcmp($left, $right)
```

Diagnostics MUST NOT include:

- secrets;
- PII;
- raw config dumps;
- environment values;
- process details;
- filesystem paths;
- adapter internals;
- generated artifact payloads;
- module plan payload dumps.

Example safe conflict diagnostics:

```text
http.roadrunner
http.worker
```

Example forbidden diagnostics:

```text
worker.task_type=http from /absolute/path/.env
```

## ModulePlan compatibility requirements

`ModulePlan` MUST NOT be used to discover, infer, enable, disable, or synthesize owner runtime-driver contributions.

The Kernel guard does not inspect `platform.worker` membership.

The Worker package owns that module precondition through:

```text
Coretsia\Platform\Worker\Runtime\WorkerRuntimeEntrypointGuard
```

The boundary requires:

```text
platform.worker
```

before Worker runtime execution starts.

`WorkerStartCommand` and the shipped Worker child launcher must use this boundary rather than performing independent module checks. Task-source resolution/readiness occurs only after the Worker-owned guard succeeds.

A failed Worker owner precondition is surfaced as:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
requires-platform-worker-module
```

This precondition is enforced by the Worker entrypoint before Kernel matrix evaluation.

After driver composition, the following final HTTP drivers require `platform.http`:

```text
http.frankenphp
http.swoole
http.roadrunner
http.worker
```

A missing `platform.http` requirement fails with:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
requires-platform-http-module
```

`http.classic` does not require `platform.http`.

Background drivers do not satisfy or create the `platform.http` requirement.

The guard MUST NOT silently downgrade a final non-classic HTTP driver to `http.classic`.

## Canonical error codes

The canonical guard error codes for runtime driver matrix violations are:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
```

The guard MUST use code-first deterministic failure semantics.

The guard MUST NOT use free-form messages as primary failure semantics.

The guard MAY include minimal safe diagnostics.

Allowed diagnostics:

```text
driver ids
```

Forbidden diagnostics:

```text
secrets
PII
raw config payloads
environment values
absolute paths
adapter internals
module plan dumps
```

## Canonical reason tokens

Runtime-driver matrix conflict reason tokens are:

```text
worker-http-conflicts-with-http-driver
```

The following defensive conflict reason is retained for non-config composition invariants:

```text
multiple-http-drivers
```

Kernel runtime-driver invalid-config reason tokens are:

```text
requires-platform-http-module
config-key-missing
config-key-invalid
```

The following owner-entrypoint precondition reason is emitted by the current Worker command before Kernel matrix evaluation:

```text
requires-platform-worker-module
```

The following tokens are no longer Kernel runtime-driver reason tokens:

```text
worker-task-type-missing
worker-task-type-invalid
```

Missing or invalid Worker task type uses Worker-owned failure semantics:

```text
CORETSIA_WORKER_LIFECYCLE_FAILED
worker-invalid-state
```

Reason tokens MUST use kebab-case.

Config paths remain dot-paths and may contain snake_case segments.

Driver ids remain canonical runtime ids and may contain dots or underscores.

## Entry points and integration points

Runtime entrypoints that have all required inputs MUST use the public `RuntimeEntrypointGuard` boundary.

Runtime adapters that need the resolved active runtime-driver set MUST call:

```php
RuntimeEntrypointGuard::resolveEntrypointDrivers(
    ConfigRepositoryInterface $config,
    ModulePlan $modulePlan,
    RuntimeDriverContributions $runtimeDriverContributions,
): RuntimeDrivers
```

Entrypoints that require only a compatibility gate MAY call:

```php
RuntimeEntrypointGuard::assertEntrypointAllowed(
    ConfigRepositoryInterface $config,
    ModulePlan $modulePlan,
    RuntimeDriverContributions $runtimeDriverContributions,
): void
```

`assertEntrypointAllowed(...)` is an assertion-only wrapper around `resolveEntrypointDrivers(...)`.

Callers MUST NOT invoke both methods for the same entrypoint attempt.

Owner packages MUST build contributions before calling either boundary.

An owner package MAY centralize this responsibility in its own public entrypoint boundary.

`platform/worker` does so through:

```text
Coretsia\Platform\Worker\Runtime\WorkerRuntimeEntrypointGuard
```

Worker production callers MUST call that Worker-owned boundary.

They MUST NOT:

- import `WorkerRuntimeDriverContributions`;
- call the package-internal mapper directly;
- call the Kernel `RuntimeEntrypointGuard` directly;
- invoke both Worker and Kernel guards for one entrypoint attempt.

`WorkerRuntimeEntrypointGuard` builds explicit contributions and delegates to the Kernel assertion boundary.

The `RuntimeDriverContributions` argument is mandatory even when no owner package contributes a driver. The explicit empty value is:

```php
RuntimeDriverContributions::fromDrivers(
    httpDrivers: [],
    backgroundDrivers: [],
)
```

Callers MUST NOT resolve the runtime-driver matrix independently after either public guard method succeeds.

They MUST NOT call `RuntimeDriverGuard` directly.

The current Worker entrypoint MUST treat this document as normative:

```text
coretsia worker:start
```

The following future HTTP runtime entrypoints MUST treat this document as normative:

```text
frankenphp.php
swoole.php
roadrunner.php
```

Kernel runtime guard integration MUST treat this document as normative for compatibility decisions.

Runtime entrypoints MUST NOT apply local compatibility rules that conflict with this SSoT.

Runtime entrypoints MUST NOT silently ignore conflicts.

Runtime entrypoints MUST surface deterministic guard failure semantics.

## Long-running runtime safety

Long-running HTTP drivers MUST enforce a UoW boundary per request.

The relevant HTTP drivers are:

```text
http.frankenphp
http.swoole
http.roadrunner
http.worker
```

Each request MUST be treated as one UoW.

Reset MUST run exactly once per UoW through Kernel runtime.

Long-running HTTP drivers MUST NOT leak:

- context values;
- stateful service memory;
- request objects;
- response objects;
- headers;
- cookies;
- Authorization values;
- tokens;
- session ids;
- raw payloads;
- raw SQL.

The reusable reset semantics are governed by:

```text
docs/ssot/reset-tags.md
docs/ssot/context-lifecycle.md
docs/ssot/stateful-services.md
```

## Artifact-only boot boundary

The following HTTP drivers require artifact-only boot:

```text
http.frankenphp
http.swoole
http.roadrunner
```

Kernel-owned required artifacts are:

```text
module-manifest.php
config.php
container.php
```

These are the only artifacts in this boot contract that are produced, path-resolved, schema-validated, and cache-verified by `core/kernel`.

When the module owned by `platform/routing` is enabled in the resolved `ModulePlan`, artifact-only HTTP boot additionally requires the platform/routing-owned artifact:

```text
routes.php
```

`routes.php` is not part of the Kernel-owned artifact set.

It MUST NOT be produced, path-resolved, schema-validated, or cache-verified by `core/kernel`.

Production, path policy, schema validation, and verification of `routes.php` belong to `platform/routing`.

Therefore, artifact-only HTTP boot uses a composite requirement:

```text
Kernel-owned artifacts
+ artifacts required by enabled owner packages
```

When `platform/routing` does not participate in the resolved runtime plan, `routes.php` MUST NOT be treated as an unconditional consequence of selecting a long-running HTTP driver.

Artifact-only boot MUST NOT infer owner runtime-driver contributions from:

- generated artifacts;
- `ModulePlan` membership;
- container service availability;
- package metadata.

When a Kernel-owned artifact boot path invokes `RuntimeEntrypointGuard` without any owner-package runtime-driver participation, it MUST pass an explicit empty `RuntimeDriverContributions` object.

Owner-specific runtime entrypoints MUST ensure that explicit contributions are constructed and supplied before the Kernel guard is invoked.

They MAY do this through an owner-owned public wrapper such as `WorkerRuntimeEntrypointGuard`.

Owner callers behind such a wrapper MUST NOT invoke the Kernel guard a second time.

Runtime entrypoints for these drivers MUST NOT perform package filesystem scanning as a replacement for generated artifacts.

Artifact identity, ownership, production boundaries, and Kernel artifact path policy are governed by:

```text
docs/ssot/artifacts.md
docs/ssot/artifacts-and-fingerprint.md
docs/ssot/modules-and-manifests.md
```

## Enforcement rails

This document is policy.

It defines policy enforced by the current Kernel runtime-driver implementation, runtime entrypoint guard, guard tests, integration tests, and runtime-adapter tests.

Expected enforcement owner:

```text
1.350.0 Runtime Drivers / Runtime Entrypoint Guard
```

Current Worker owner:

```text
platform/worker
```

Expected future runtime adapter owners:

```text
20.x
```

Representative current enforcement evidence includes:

```text
framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixDefaultClassicIsAllowedTest.php
framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsRoadrunnerPlusWorkerHttpTest.php
framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllFixturesMatchGuardTest.php
framework/packages/platform/worker/tests/Unit/WorkerRuntimeDriverContributionsTest.php
framework/packages/platform/worker/tests/Contract/WorkerStartCommandContractTest.php
framework/packages/platform/worker/tests/Contract/CoretsiaWorkerChildLauncherContractTest.php
framework/packages/platform/worker/tests/Integration/WorkerTaskSourceResolverSelectsServiceLazilyTest.php
```

## Verification contract

Kernel guard and runtime entrypoint tests MUST prove at minimum:

- effective Kernel defaults select `http.classic`;
- missing `kernel.runtime.http_driver` fails with `config-key-missing`;
- non-string or unsupported Kernel selector values fail with `config-key-invalid`;
- `detect()` reads only Kernel-owned runtime config;
- Kernel guard code does not read `worker.task_type`;
- explicit empty contributions preserve the Kernel-selected HTTP driver;
- one contributed HTTP driver replaces `http.classic`;
- a contributed HTTP driver conflicts with a Kernel-selected non-classic HTTP driver;
- multiple contributed HTTP drivers fail deterministically;
- background contributions are preserved;
- `http.worker` and `bg.worker_queue` are not treated as the same category;
- `http.worker` may coexist with a background contribution at the Kernel matrix level;
- final non-classic HTTP drivers require `platform.http`;
- `ModulePlan` is not used to infer Worker contributions;
- active and conflicting driver ids are sorted by byte-order `strcmp`;
- active conflict diagnostics include background contributions when present;
- diagnostics do not expose secrets, raw config, environment values, paths, or adapter internals.

Worker package tests MUST prove at minimum:

- `worker.task_type=queue` maps to `bg.worker_queue`;
- `worker.task_type=http` maps to `http.worker`;
- missing or invalid Worker task type fails through Worker exception policy;
- `WorkerPoolSpec` is built before `WorkerRuntimeEntrypointGuard` is invoked;
- `platform.worker` owner precondition is checked by `WorkerRuntimeEntrypointGuard` before runtime execution;
- `WorkerRuntimeEntrypointGuard` maps `WorkerPoolSpec` to explicit contributions;
- the Worker-owned boundary invokes the Kernel `RuntimeEntrypointGuard` with those explicit contributions;
- Worker production callers do not import the internal mapper or invoke the Kernel guard directly;
- HTTP task compatibility is checked through `WorkerRuntimeEntrypointGuard` before HTTP task-source resolution/readiness;
- the child launcher invokes `WorkerRuntimeEntrypointGuard` before resolving `ApplicationWorker`;
- Kernel guard failures are surfaced without reclassification as Worker failures.

## Examples

### Valid: default classic with no owner contributions

Kernel config:

```text
kernel.runtime.http_driver = "http.classic"
```

Contributions:

```text
none
```

Final drivers:

```text
http.classic
```

### Valid: RoadRunner with Worker queue contribution

Kernel config:

```text
kernel.runtime.http_driver = "http.roadrunner"
```

Worker input:

```text
worker.task_type = "queue"
```

Contribution:

```text
bg.worker_queue
```

ModulePlan:

```text
platform.http enabled
```

Final driver ids in canonical order:

```text
bg.worker_queue
http.roadrunner
```

### Valid: Worker HTTP replaces classic

Kernel config:

```text
kernel.runtime.http_driver = "http.classic"
```

Contribution:

```text
http.worker
```

ModulePlan:

```text
platform.http enabled
```

Final drivers:

```text
http.worker
```

### Valid: Worker HTTP plus background contribution

Kernel config:

```text
kernel.runtime.http_driver = "http.classic"
```

Contributions:

```text
http.worker
bg.worker_queue
```

ModulePlan:

```text
platform.http enabled
```

Final driver ids:

```text
bg.worker_queue
http.worker
```

The current Worker task-type mapper does not produce this pair from one `WorkerPoolSpec`, but the generic Kernel matrix permits it.

### Invalid: RoadRunner with Worker HTTP contribution

Kernel config:

```text
kernel.runtime.http_driver = "http.roadrunner"
```

Contribution:

```text
http.worker
```

Failure:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
worker-http-conflicts-with-http-driver
```

Conflict ids:

```text
http.roadrunner
http.worker
```

### Invalid: non-classic HTTP without `platform.http`

Kernel config:

```text
kernel.runtime.http_driver = "http.frankenphp"
```

Contributions:

```text
bg.worker_queue
```

ModulePlan:

```text
platform.http disabled
```

Failure:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
requires-platform-http-module
```

Active driver ids:

```text
bg.worker_queue
http.frankenphp
```

### Worker-owned validation failure outside Kernel matrix

Worker input:

```text
worker.task_type = "scheduler"
```

Failure:

```text
CORETSIA_WORKER_LIFECYCLE_FAILED
worker-invalid-state
```

Kernel runtime-driver matrix evaluation does not run for this invalid Worker-owned input.

## Non-goals

This SSoT does not define:

- internal guard implementation mechanics;
- private helper-class structure behind the public entrypoint boundary;
- concrete runtime adapter classes;
- concrete worker implementation;
- concrete HTTP runtime entrypoint implementation;
- concrete CLI command implementation;
- Worker OS process-driver implementation;
- `WorkerProcessDriverInterface` mechanics;
- PCNTL fork/wait/signal implementation;
- proc process-host implementation;
- Worker control socket binding;
- Worker readiness transport;
- Worker process supervision;
- queue backend implementation;
- scheduler implementation;
- worker retry policy;
- worker timeout policy;
- worker payload schema;
- RoadRunner configuration file schema;
- Swoole server configuration schema;
- FrankenPHP server configuration schema;
- generated artifact schema details;
- module discovery implementation;
- package filesystem scanning;
- deployment topology;
- container orchestration policy;
- production observability backend implementation.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Kernel Artifacts, Fingerprint, and Cache Verification](./artifacts-and-fingerprint.md)
- [Config Roots Registry](./config-roots.md)
- [ContextStore lifecycle SSoT](./context-lifecycle.md)
- [Modules and manifests SSoT](./modules-and-manifests.md)
- [Reset Tags SSoT](./reset-tags.md)
- [Stateful Services SSoT](./stateful-services.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [Worker Architecture](../architecture/worker.md)
- [ADR-0017: Persistent worker supervisor and application worker](../adr/ADR-0017-persistent-worker-supervisor-application-worker.md)
