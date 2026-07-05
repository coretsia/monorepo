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

## Purpose

This document is the Single Source of Truth for Coretsia runtime driver compatibility.

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
- runtime driver activation conditions;
- HTTP driver mutual-exclusion rules;
- allowed HTTP/background driver composition;
- deterministic runtime-driver matrix failure semantics;
- canonical runtime-driver matrix error code names;
- required runtime-driver input key policy;
- external runtime-owner input handling for `worker.task_type` and `platform.worker` owner scope;
- canonical reason tokens for runtime-driver matrix failures.

This document does not own concrete guard implementation mechanics.

Concrete implementation mechanics are owned by `core/kernel` source and tests:

```text
Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard
Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard
```

`RuntimeEntrypointGuard` is the public runtime-adapter boundary.

`RuntimeDriverGuard` is the internal Kernel implementation detail.

This document does not own runtime adapter implementation details.

Runtime adapters are owned by later integration epics:

```text
20.x
```

This document does not introduce `worker.*` runtime implementation.

Worker runtime implementation and `worker.*` root ownership are owned by:

```text
platform/worker
```

In this document, `worker.task_type` is a Worker-owned runtime-driver input consumed by the Kernel-owned runtime-driver matrix only when `platform.worker` is enabled in the resolved `ModulePlan`.

It is not a `core/kernel` config root ownership claim.

`platform.worker` module participation is owned by mode preset resolution and `ModulePlan`.

This document does not own UoW/reset implementation details. Long-running runtime state and reset discipline are governed by:

```text
docs/ssot/context-lifecycle.md
docs/ssot/reset-tags.md
docs/ssot/stateful-services.md
```

This document does not own generated artifact schemas. Artifact ownership is governed by:

```text
docs/ssot/artifacts.md
docs/ssot/modules-and-manifests.md
```

This document does not own config root registration rules. Config root ownership is governed by:

```text
docs/ssot/config-roots.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Terminology

A runtime driver is an activated runtime execution mode.

An HTTP driver is a runtime driver whose Unit of Work is an HTTP request.

A background driver is a runtime driver whose Unit of Work is a background task, queue job, scheduled task, or similar non-HTTP cycle.

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

## Canonical matrix inputs

The runtime-driver matrix is evaluated from a Phase-B merged and validated config snapshot together with the resolved `ModulePlan`.

The Kernel-owned runtime-driver input keys are:

```text
kernel.runtime.http_driver
```

The Worker-owned runtime-driver input key is:

```text
worker.task_type
```

`worker.task_type` is in scope only when `platform.worker` is enabled in the resolved `ModulePlan`.

`worker.task_type` selects the Worker-owned runtime driver:

```text
queue → bg.worker_queue
http  → http.worker
```

Worker package/module activation is owned by mode presets and `ModulePlan`.

Worker process startup is an explicit runtime command action.

The guard MUST NOT decide active drivers by environment probing.

The guard MUST NOT inspect:

- loaded PHP extensions;
- server process names;
- CLI argv outside the owner command contract;
- open ports;
- environment variables outside canonical config loading;
- filesystem runtime adapter presence;
- container service existence;
- generated artifact existence;
- reflection.

Generated artifacts MUST NOT participate in runtime-driver selection.

The resolved `ModulePlan` MAY be inspected only for module-owner scope and explicit module compatibility requirements defined by this SSoT.

## Driver ids and activation rules

### `http.classic`

`http.classic` is the default classic request/response HTTP runtime mode.

It is selected by the Kernel-owned selector:

```text
kernel.runtime.http_driver = "http.classic"
```

It is active when no scoped worker HTTP runtime driver is active.

A scoped worker HTTP runtime driver is active only when `platform.worker` is enabled in `ModulePlan` and:

```text
worker.task_type = "http"
```

Conflicts:

```text
none by itself
```

May run alongside:

```text
bg.worker_queue
```

Notes:

- `http.classic` is the safe default HTTP driver.
- `http.classic` MUST NOT require long-running runtime adapter boot.
- `http.classic` is replaced by `http.worker` only when Worker owner-scope is active and `worker.task_type = "http"`.

### `http.frankenphp`

`http.frankenphp` is the FrankenPHP HTTP runtime mode.

It is selected by the Kernel-owned selector:

```text
kernel.runtime.http_driver = "http.frankenphp"
```

Conflicts:

```text
scoped http.worker
```

May run alongside:

```text
bg.worker_queue
```

Notes:

- artifact-only boot is required:

```text
module-manifest.php
config.php
container.php
routes.php
```

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
scoped http.worker
```

May run alongside:

```text
bg.worker_queue
```

Notes:

- artifact-only boot is required:

```text
module-manifest.php
config.php
container.php
routes.php
```

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
scoped http.worker
```

May run alongside:

```text
bg.worker_queue
```

Notes:

- artifact-only boot is required:

```text
module-manifest.php
config.php
container.php
routes.php
```

- UoW boundary MUST be enforced per request.
- Reset MUST run exactly once per UoW through Kernel runtime.
- Long-running loop state MUST NOT leak context or mutable state across requests.

### `http.worker`

`http.worker` is an HTTP runtime mode executed through the worker command surface.

It is active when `platform.worker` is enabled in the resolved `ModulePlan` and:

```text
worker.task_type = "http"
```

Conflicts:

```text
any Kernel-selected non-classic HTTP driver
```

Notes:

- `http.worker` is an HTTP runtime mode.
- `http.worker` is not a background driver.
- `http.worker` participates in HTTP-driver mutual exclusion.
- `http.worker` MUST NOT be treated as `bg.worker_queue`.
- `http.worker` is selected only when `platform.worker` is enabled in the caller-provided `ModulePlan`.
- `http.worker` also requires `platform.http` because it is an HTTP runtime driver.

### `bg.worker_queue`

`bg.worker_queue` is a background worker queue runtime mode.

It is active when `platform.worker` is enabled in the resolved `ModulePlan` and:

```text
worker.task_type = "queue"
```

Conflicts:

```text
none at the matrix level
```

May run alongside:

```text
http.classic
http.frankenphp
http.swoole
http.roadrunner
```

Notes:

- `bg.worker_queue` is a background driver.
- `bg.worker_queue` is not an HTTP driver.
- `bg.worker_queue` MUST require `platform.worker` because it is selected through Worker-owned runtime inputs.
- `bg.worker_queue` MUST NOT require `platform.http` because it is not an HTTP runtime driver.
- With the current Phase 1 matrix inputs, `bg.worker_queue` and `http.worker` cannot both be active because `worker.task_type` is a single selected value.
- This input-level exclusion is deterministic and MUST NOT be interpreted as an HTTP/background policy conflict.

## Reserved future ids

The following ids are reserved for future owner epics and are not active Phase 1 guard inputs:

```text
bg.queue
bg.scheduler
```

The Phase 1 guard MUST NOT activate them.

A future epic MAY promote a reserved id into the active matrix only by updating this SSoT.

## Hard compatibility rules

The matrix rules are single-choice:

1. Exactly one HTTP driver may be active at a time.
2. Background drivers MAY run alongside HTTP drivers unless the active-driver inputs make the combination impossible.
3. `http.worker` conflicts with any other `http.*` driver.
4. `http.worker` MUST NOT be treated as a background driver.
5. `bg.worker_queue` MUST NOT be treated as an HTTP driver.

The current Phase 1 background driver `bg.worker_queue` may run alongside:

```text
http.classic
http.frankenphp
http.swoole
http.roadrunner
```

It cannot run alongside `http.worker` under current Phase 1 inputs because both are selected through the single `worker.task_type` value.

The active HTTP driver is selected from:

```text
http.classic
http.frankenphp
http.swoole
http.roadrunner
http.worker
```

The active background drivers are selected from:

```text
bg.worker_queue
```

## Default safety policy

The Kernel-owned safe default is:

```text
kernel.runtime.http_driver = "http.classic"
```

`kernel.runtime.http_driver` is owned by `core/kernel`.

It selects exactly one Kernel-owned HTTP runtime driver.

`http.worker` is not a valid value for `kernel.runtime.http_driver`.

Worker-owned safe defaults are owned by the package that owns the `worker` config root and are present only when `platform.worker` participates in the resolved `ModulePlan` and its package defaults are loaded.

The current Worker-owned safe default is:

```text
worker.task_type = "queue"
```

The runtime-driver matrix consumes the Phase-B merged and validated runtime config snapshot and MUST NOT invent this default locally.

When `platform.worker` is enabled in `ModulePlan`, the default Worker-owned runtime driver is:

```text
bg.worker_queue
```

This does not start a worker process.

Worker process startup is an explicit runtime command action, for example:

```text
worker:start
```

When `platform.worker` is not enabled in `ModulePlan`, missing `worker.task_type` is not defaulted locally by Kernel; Worker-owned runtime-driver inputs are outside the active owner domain.

With Kernel-owned defaults and no scoped worker runtime activation, active drivers are:

```text
http.classic
```

Inactive drivers are:

```text
http.frankenphp
http.swoole
http.roadrunner
http.worker
bg.worker_queue
```

## Runtime-driver input presence policy

The runtime-driver matrix consumes a Phase-B merged and validated config snapshot together with the resolved `ModulePlan`.

The following Kernel-owned runtime-driver selector MUST be present in every Phase-B runtime config snapshot:

```text
kernel.runtime.http_driver
```

Missing Kernel-owned required runtime-driver config keys MUST fail deterministically with:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
config-key-missing
```

Non-string or unsupported `kernel.runtime.http_driver` values MUST fail deterministically with:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
config-key-invalid
```

`worker.task_type` is a Worker-owned runtime-driver input.

It is in scope only when `platform.worker` is enabled in the resolved `ModulePlan`.

When `platform.worker` is not enabled:

- missing `worker.task_type` MUST NOT fail;
- Kernel MUST NOT invent `worker.*` defaults;
- worker-derived runtime drivers MUST NOT be selected.

When `platform.worker` is enabled:

- `worker.task_type` MUST be present;
- `worker.task_type` MUST be a string;
- `worker.task_type` MUST be either `http` or `queue`.

When `platform.worker` is enabled and `worker.task_type = "queue"`, the selected worker-derived runtime driver is:

```text
bg.worker_queue
```

When `platform.worker` is enabled and `worker.task_type = "http"`, the selected worker-derived runtime driver is:

```text
http.worker
```

When `platform.worker` is enabled and `worker.task_type` is missing, the matrix MUST fail deterministically with:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
worker-task-type-missing
```

When `platform.worker` is enabled and `worker.task_type` is present but invalid, the matrix MUST fail deterministically with:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
worker-task-type-invalid
```

The `worker` root defaults and full subtree validation remain owned by the package that owns the `worker` config root.

The Kernel runtime-driver matrix MUST NOT validate unknown `worker.*` keys and MUST NOT invent `worker.*` defaults locally.

Worker package/module activation is owned by mode presets and `ModulePlan`.

Worker process startup is an explicit runtime command action.

## Active driver resolution contract

The guard MUST derive the active driver set from canonical config inputs and the resolved `ModulePlan`.

The active HTTP driver set is computed conceptually as:

```text
http.classic     if kernel.runtime.http_driver = "http.classic"
                    and no scoped worker HTTP driver is selected
http.frankenphp  if kernel.runtime.http_driver = "http.frankenphp"
http.swoole      if kernel.runtime.http_driver = "http.swoole"
http.roadrunner  if kernel.runtime.http_driver = "http.roadrunner"
http.worker      if platform.worker is enabled in ModulePlan
                    && worker.task_type = "http"
```

The active background driver set is computed conceptually as:

```text
bg.worker_queue  if platform.worker is enabled in ModulePlan
                    && worker.task_type = "queue"
```

If more than one HTTP driver is active, the configuration is invalid.

If no non-classic HTTP driver is active, `http.classic` is active.

The guard MUST NOT produce a state with zero active HTTP drivers.

## HTTP driver mutual-exclusion matrix

Legend:

```text
✅ allowed
❌ conflict
— same driver
```

| Selection combination                       | Result | Reason                                          |
|---------------------------------------------|--------|-------------------------------------------------|
| one `kernel.runtime.http_driver` value only | ✅      | selector is single-choice                       |
| `http.classic` + scoped `http.worker`       | ✅      | worker HTTP replaces classic fallback           |
| `http.frankenphp` + scoped `http.worker`    | ❌      | two active HTTP runtime drivers                 |
| `http.swoole` + scoped `http.worker`        | ❌      | two active HTTP runtime drivers                 |
| `http.roadrunner` + scoped `http.worker`    | ❌      | two active HTTP runtime drivers                 |
| multiple Kernel-selected HTTP drivers       | ❌      | impossible through `kernel.runtime.http_driver` |

## HTTP/background compatibility matrix

Legend:

```text
✅ allowed
❌ conflict or impossible under current activation inputs
```

| HTTP driver       | `bg.worker_queue` | Reason                                                |
|-------------------|-------------------|-------------------------------------------------------|
| `http.classic`    | ✅                 | background driver may run alongside classic HTTP      |
| `http.frankenphp` | ✅                 | background driver may run alongside long-running HTTP |
| `http.swoole`     | ✅                 | background driver may run alongside long-running HTTP |
| `http.roadrunner` | ✅                 | background driver may run alongside long-running HTTP |
| `http.worker`     | ❌                 | impossible under single `worker.task_type` input      |

The `http.worker` + `bg.worker_queue` row is not a general HTTP/background policy conflict.

It is an activation-input impossibility because both drivers are selected through the same single-valued Worker-owned input:

```text
worker.task_type
```

with mutually exclusive values:

```text
http
queue
```

## Concrete compatibility examples

### Default classic

```text
kernel.runtime.http_driver = "http.classic"
```

### RoadRunner + queue worker

```text
kernel.runtime.http_driver = "http.roadrunner"
worker.task_type = "queue"
```

### Swoole + queue worker

```text
kernel.runtime.http_driver = "http.swoole"
worker.task_type = "queue"
```

### FrankenPHP + queue worker

```text
kernel.runtime.http_driver = "http.frankenphp"
worker.task_type = "queue"
```

### RoadRunner + worker HTTP

```text
kernel.runtime.http_driver = "http.roadrunner"
worker.task_type = "http"
```

### FrankenPHP + worker HTTP

```text
kernel.runtime.http_driver = "http.frankenphp"
worker.task_type = "http"
```

### Swoole + worker HTTP

```text
kernel.runtime.http_driver = "http.swoole"
worker.task_type = "http"
```

## Deterministic enforcement contract

The guard MUST decide active drivers by evaluating Kernel-owned config keys and the resolved `ModulePlan` owner scope for Worker-owned runtime-driver inputs.

The guard MUST NOT use environment probing to decide active drivers.

On conflict, the guard MUST fail deterministically.

The deterministic error `CODE` string is the primary failure semantic.

For HTTP driver conflicts, the canonical failure code is:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
```

Diagnostics, if emitted, MUST be stable.

Diagnostics MUST contain driver ids only.

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

## ModulePlan requirements

The following worker-derived drivers require `platform.worker` to be enabled in `ModulePlan`:

```text
http.worker
bg.worker_queue
```

Missing `platform.worker` for a selected worker-derived runtime driver MUST fail with:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
requires-platform-worker-module
```

A selected worker-derived runtime driver without `platform.worker` enabled in `ModulePlan` is invalid.

The guard MUST NOT select worker-derived runtime drivers when `platform.worker` is not enabled.

After worker-owner scope is satisfied, the following non-classic HTTP drivers require `platform.http` to be enabled in `ModulePlan`:

```text
http.frankenphp
http.swoole
http.roadrunner
http.worker
```

Missing required module for the selected HTTP driver MUST fail with:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
```

`http.classic` is the default HTTP mode and does not introduce an additional non-classic driver requirement in this matrix.

`bg.worker_queue` is a background driver and does not satisfy the `platform.http` requirement for HTTP drivers.

The guard MUST NOT silently downgrade a selected non-classic HTTP driver to `http.classic` when `platform.http` is missing.

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

Runtime driver matrix conflict reason tokens are:

```text
worker-http-conflicts-with-http-driver
```

The following conflict reason is reserved as a defensive implementation invariant and is not reachable from validated `kernel.runtime.http_driver` config:

```text
multiple-http-drivers
```

Runtime driver matrix invalid-config reason tokens are:

```text
requires-platform-http-module
requires-platform-worker-module
config-key-missing
config-key-invalid
worker-task-type-missing
worker-task-type-invalid
```

Reason tokens MUST use kebab-case.

Reason tokens MUST NOT use snake_case.

Config paths remain dot-paths and may contain snake_case config key segments, for example:

```text
worker.task_type
```

Driver ids remain canonical runtime ids and may contain dots or underscores, for example:

```text
bg.worker_queue
```

## Entry points and integration points

The following future entrypoints MUST treat this document as normative:

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

Required artifacts are:

```text
module-manifest.php
config.php
container.php
routes.php
```

Runtime entrypoints for these drivers MUST NOT perform package filesystem scanning as a replacement for generated artifacts.

Artifact schema and ownership are governed by:

```text
docs/ssot/artifacts.md
docs/ssot/modules-and-manifests.md
```

## Enforcement rails

This document is policy.

It defines policy enforced by the current Kernel runtime-driver implementation, runtime entrypoint guard, guard tests, integration tests, and runtime-adapter tests.

Expected enforcement owner:

```text
1.350.0 Runtime Drivers / Runtime Entrypoint Guard
```

Expected future CLI owner:

```text
1.360.0 platform/worker
```

Expected future runtime adapter owners:

```text
20.x
```

Required future enforcement test references are:

```text
framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixDefaultClassicIsAllowedTest.php
framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsRoadrunnerPlusWorkerHttpTest.php
```

These paths are referenced now as normative future evidence paths.

The doc-only `1.260.0` epic does not create those tests.

## Verification contract

Guard and runtime entrypoint tests MUST prove at minimum:

- effective default merged config activates `http.classic`;
- missing required Kernel-owned runtime-driver config keys fail with `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`;
- missing required Kernel-owned runtime-driver config keys use reason `config-key-missing`;
- non-string or unsupported `kernel.runtime.http_driver` values fail with reason `config-key-invalid`;
- missing `worker.task_type` does not fail when `platform.worker` is not enabled in `ModulePlan`;
- missing `worker.task_type` fails with reason `worker-task-type-missing` when `platform.worker` is enabled in `ModulePlan`;
- invalid `worker.task_type` fails with reason `worker-task-type-invalid` when `platform.worker` is enabled in `ModulePlan`;
- worker-derived runtime drivers are not selected when `platform.worker` is not enabled in `ModulePlan`;
- `http.roadrunner` + `bg.worker_queue` is allowed;
- `http.swoole` + `bg.worker_queue` is allowed;
- `http.frankenphp` + `bg.worker_queue` is allowed;
- `http.classic` + `bg.worker_queue` is allowed;
- `http.roadrunner` + `http.worker` fails with `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`;
- `http.frankenphp` + `http.worker` fails with `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`;
- `http.swoole` + `http.worker` fails with `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`;
- conflict diagnostics are sorted by driver id using byte-order `strcmp`;
- selected non-classic HTTP driver without `platform.http` fails with `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`;
- module-aware detection ignores `worker.task_type` when `platform.worker` is not enabled in `ModulePlan`;
- diagnostics contain driver ids only and do not expose secrets, raw config, environment values, paths, or adapter internals.

## Examples

### Valid: default classic HTTP without `platform.worker`

ModulePlan:

```text
platform.worker not enabled
```

Config:

```text
kernel.runtime.http_driver = "http.classic"
```

Active drivers:

```text
http.classic
```

This is valid.

Missing `worker.task_type` does not fail because Worker-owned runtime-driver inputs are outside the active owner domain.

### Valid: RoadRunner HTTP with queue worker

```text
kernel.runtime.http_driver = "http.roadrunner"
worker.task_type = "queue"
```

Active drivers:

```text
http.roadrunner
bg.worker_queue
```

This is valid.

### Valid: Swoole HTTP with queue worker

```text
kernel.runtime.http_driver = "http.swoole"
worker.task_type = "queue"
```

Active drivers:

```text
http.swoole
bg.worker_queue
```

This is valid.

### Valid: FrankenPHP HTTP with queue worker

```text
kernel.runtime.http_driver = "http.frankenphp"
worker.task_type = "queue"
```

Active drivers:

```text
http.frankenphp
bg.worker_queue
```

This is valid.

### Invalid: RoadRunner HTTP with worker HTTP

```text
kernel.runtime.http_driver = "http.roadrunner"
worker.task_type = "http"
```

Active drivers:

```text
http.roadrunner
http.worker
```

This is invalid.

Failure code:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
```

Safe diagnostics:

```text
http.roadrunner
http.worker
```

### Invalid: FrankenPHP HTTP with worker HTTP

```text
kernel.runtime.http_driver = "http.frankenphp"
worker.task_type = "http"
```

Active drivers:

```text
http.frankenphp
http.worker
```

This is invalid.

Failure code:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
```

Safe diagnostics:

```text
http.frankenphp
http.worker
```

### Invalid: Swoole HTTP with worker HTTP

```text
kernel.runtime.http_driver = "http.swoole"
worker.task_type = "http"
```

Active drivers:

```text
http.swoole
http.worker
```

This is invalid.

Failure code:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
```

Safe diagnostics:

```text
http.swoole
http.worker
```

### Impossible: multiple Kernel-selected HTTP drivers

Multiple Kernel-selected HTTP drivers are not expressible through the current config shape.

`kernel.runtime.http_driver` is a single selector, so the following legacy state is intentionally impossible:

```text
http.frankenphp + http.swoole
```

Such conflicts may still remain as defensive implementation invariants, but they are not valid Phase-B config states.

### Invalid: selected non-classic HTTP driver without `platform.http`

Selected driver:

```text
http.roadrunner
```

ModulePlan:

```text
platform.http disabled
```

This is invalid.

Failure code:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
```

## Non-goals

This SSoT does not define:

- guard implementation internals;
- concrete guard class names;
- concrete runtime adapter classes;
- concrete worker implementation;
- concrete HTTP runtime entrypoint implementation;
- concrete CLI command implementation;
- runtime adapter process management;
- socket binding;
- port selection;
- process supervision;
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
- [Config Roots Registry](./config-roots.md)
- [ContextStore lifecycle SSoT](./context-lifecycle.md)
- [Modules and manifests SSoT](./modules-and-manifests.md)
- [Reset Tags SSoT](./reset-tags.md)
- [Stateful Services SSoT](./stateful-services.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
