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

# Worker Architecture

## Purpose

This document is the architecture overview for the `platform/worker` long-running
runtime package.

It explains:

- package ownership;
- process model;
- driver selection;
- manager and application-worker boundaries;
- UnitOfWork and reset discipline;
- worker state ownership;
- control transport behavior;
- safety limits;
- observability and redaction rules;
- required update path for behavioral changes.

This document is intentionally not the worker config schema.

The canonical worker config root registration is:

```text
docs/ssot/config-roots.md
```

The canonical worker observability names and metric-label allowlist are:

```text
docs/ssot/observability.md
```

The runtime-driver compatibility matrix remains governed by:

```text
docs/ssot/runtime-drivers.md
```

## Source-of-truth boundary

Worker runtime architecture is decided by:

```text
docs/adr/ADR-0017-worker-manager-application-worker.md
```

ADR-0017 records that `platform/worker` owns long-running worker runtime
orchestration while Kernel and Foundation retain UnitOfWork, hook, and reset
semantics.

If this document conflicts with any of the following, the SSoT or ADR wins:

```text
docs/adr/ADR-0017-worker-manager-application-worker.md
docs/ssot/config-roots.md
docs/ssot/observability.md
docs/ssot/runtime-drivers.md
docs/ssot/uow-and-reset-contracts.md
```

This document owns only the package-level architecture explanation.

## Package identity

The worker runtime package is:

```text
package id: platform/worker
composer: coretsia/platform-worker
module id: platform.worker
kind: runtime
config root: worker
```

The owner path is:

```text
framework/packages/platform/worker/
```

The worker config root is owned by `platform/worker`.

The owning config files are:

```text
framework/packages/platform/worker/config/worker.php
framework/packages/platform/worker/config/rules.php
```

The worker defaults file returns the `worker` subtree only.

It must not wrap values in a repeated root key such as:

```php
['worker' => [...]]
```

## Compile-time dependency boundary

`platform/worker` may depend on:

```text
core/contracts
core/foundation
core/kernel
```

`platform/worker` must not depend on:

```text
platform/cli
platform/http
integrations/*
```

The worker package may contribute CLI command services, but CLI discovery,
catalog construction, binary dispatch, and command UX remain owned by
`platform/cli`.

The worker package may preflight HTTP task mode through
`Psr\Http\Server\RequestHandlerInterface`, but HTTP adapters and HTTP request
production remain owned by later platform/runtime adapter epics.

## Public architecture components

The main worker architecture components are:

```text
WorkerModule
WorkerServiceProvider
WorkerServiceFactory
WorkerStartCommand
WorkerStopCommand
WorkerStatusCommand
WorkerManager
WorkerManagerDriverInterface
PcntlWorkerManagerDriver
ProcWorkerManagerDriver
WorkerPoolSpec
WorkerPoolState
WorkerStateStore
WorkerSocketServer
ApplicationWorker
TaskFactoryInternalInterface
QueueTaskFactory
HttpTaskFactory
```

The internal interfaces are package-local seams only:

```text
Coretsia\Platform\Worker\Internal\WorkerManagerDriverInterface
Coretsia\Platform\Worker\Internal\TaskFactoryInternalInterface
```

They are not public framework extension points and must not be moved to
`core/contracts`.

## Process model

The worker runtime uses a master-plus-workers process model.

The lifecycle shape is:

```text
worker:start command
  -> RuntimeDriverGuard
  -> WorkerServiceFactory
  -> WorkerPoolSpec
  -> WorkerManager
  -> selected process driver
  -> master process state
  -> N worker children
  -> ApplicationWorker task loops
```

The master process owns pool lifecycle orchestration through `WorkerManager`.

The selected process driver owns concrete process lifecycle behavior.

Each child process runs an `ApplicationWorker`.

Each `ApplicationWorker` processes many tasks sequentially until:

- `worker.max_requests` is reached;
- a graceful stop flag is observed between tasks;
- the process exits due to a deterministic worker failure.

The worker control channel is lifecycle/control-only.

It must not transport task payloads.

## Driver selection

Worker process driver selection is represented by `WorkerPoolSpec`.

The requested worker driver may be:

```text
auto
pcntl
proc
```

The resolved worker driver is one of:

```text
pcntl
proc
```

When `worker.driver=auto`, resolution is deterministic:

```text
pcntl when pcntl_fork is available and the platform is not Windows
proc otherwise
```

The `pcntl` driver is Unix-like and fork-based.

The `proc` driver is the cross-platform fallback and starts child workers through
`proc_open()`.

`WorkerManager` does not perform runtime capability discovery itself.

It receives an already-built `WorkerPoolSpec` and selects a driver by the
resolved driver id.

If no matching supported driver exists, lifecycle execution fails with a safe
deterministic worker start failure.

## Control transport selection

Worker control transport is represented by `WorkerPoolSpec`.

The requested control transport may be:

```text
auto
unix
tcp
```

The resolved control transport is one of:

```text
unix
tcp
```

When `worker.control.transport=auto`, resolution is deterministic:

```text
unix when the resolved driver is pcntl and unix domain sockets are supported
tcp otherwise
```

The `unix` transport uses a skeleton-root-relative socket path.

The `tcp` transport uses configured TCP host and port.

TCP port `0` is forbidden because it would make endpoint identity and persisted
worker state non-deterministic across runs.

Raw socket paths, raw TCP hosts, and raw TCP ports must not appear in public
diagnostics, logs, metrics, or public command output.

Endpoint identity may be represented publicly only through a deterministic hash.

## Runtime-driver guard boundary

Runtime-driver compatibility is Kernel-owned policy.

The canonical guard is:

```text
Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard
```

`WorkerStartCommand` must invoke the guard before starting the worker pool.

The command must call:

```text
RuntimeDriverGuard::assertCompatible(...)
```

before `WorkerManager::start(...)`.

When `worker.task_type=http`, the command must also call:

```text
RuntimeDriverGuard::assertHttpDriverCompatibleWithModules(...)
```

with the caller-provided `ModulePlan`.

The worker package must not duplicate runtime-driver matrix logic.

The worker package must not translate runtime-driver guard failures into
worker-specific driver failures.

The runtime-driver guard remains the source of deterministic matrix errors such
as:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
```

Missing `platform.http` for HTTP worker mode must fail through
`RuntimeDriverGuard` before request-handler resolution.

## Worker manager boundary

`WorkerManager` owns high-level lifecycle orchestration:

```text
start
stop
status
```

`WorkerManager` accepts an already-built `WorkerPoolSpec`.

It delegates process-specific behavior to package-internal process drivers.

`WorkerManager` must not:

- fork;
- call `proc_open()`;
- open sockets directly;
- write state files directly;
- write stop files directly;
- write socket files directly;
- call `RuntimeDriverGuard`;
- call `KernelRuntimeInterface` for task execution;
- enumerate Kernel hook tags;
- enumerate reset tags;
- call `ResetOrchestrator::resetAll()`;
- write stdout or stderr directly.

`WorkerManager` may emit safe lifecycle observability summaries through injected
ports.

Observability failures must not alter lifecycle semantics or primary failure
precedence.

## Process driver boundary

Process drivers own concrete process lifecycle behavior.

The `pcntl` driver owns fork-based process startup and graceful shutdown when
selected.

The `proc` driver owns `proc_open()` process startup and graceful shutdown when
selected.

Process drivers may write stop flags, communicate over the control channel, and
persist worker state through `WorkerStateStore`.

Process drivers must not:

- execute task bodies directly;
- call `KernelRuntimeInterface`;
- know about CLI dispatch;
- depend on `platform/cli`;
- depend on `platform/http`;
- log raw command lines;
- expose raw socket paths;
- expose raw TCP endpoints;
- expose absolute paths;
- expose environment values;
- write stdout or stderr directly.

Process command construction for the `proc` driver is argv-vector based.

It must not construct an untrusted shell string.

## Application worker boundary

`ApplicationWorker` owns the child-process task loop.

It processes tasks sequentially without restarting PHP between tasks.

The loop shape is:

```text
while processed < worker.max_requests:
    if stop flag is present:
        break

    run one task through KernelRuntimeInterface

    processed++
```

The stop flag is checked only between tasks.

The worker must not interrupt an in-flight task unless future cancellation
semantics are explicitly introduced.

Each task is executed by:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface::runUnitOfWork(...)
```

The resolved worker task type is passed as the UnitOfWork type.

The safe task operation id used for observability is produced by package-local
task work and is restricted to low-cardinality values such as:

```text
queue
http
```

`ApplicationWorker` must not:

- create UnitOfWork ids;
- create correlation ids;
- write context values;
- invoke Kernel hooks directly;
- enumerate reset tags;
- call `ResetOrchestrator::resetAll()` directly;
- implement queue adapters;
- implement HTTP adapters;
- write stdout or stderr directly.

## UnitOfWork and reset discipline

Reset discipline between worker tasks is achieved only transitively through:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

The canonical flow is:

```text
begin
  -> before hooks
  -> task
  -> after hooks
  -> ResetOrchestrator::resetAll()
```

`platform/worker` must not call hooks directly.

`platform/worker` must not call `ResetOrchestrator::resetAll()` directly.

`platform/worker` must not enumerate reset tags.

`platform/worker` must not redefine Foundation reset infrastructure.

The worker runtime controls only the long-running worker loop.

Kernel owns UnitOfWork lifecycle semantics.

Foundation owns reset orchestration infrastructure.

## Task source boundary

Task factories are package-internal.

They produce task work for `ApplicationWorker`.

Task work contains:

```text
operation_id
run
```

`operation_id` must be deterministic, low-cardinality, and safe for the
observability metric label `operation`.

`QueueTaskFactory` handles:

```text
worker.task_type=queue
```

It does not implement a real external queue adapter.

`HttpTaskFactory` handles:

```text
worker.task_type=http
```

It does not implement a real HTTP request source.

HTTP task mode must first pass runtime-driver and module compatibility checks.

After guard compatibility passes, HTTP task mode may require a resolvable:

```text
Psr\Http\Server\RequestHandlerInterface
```

Request-handler preflight failures must be deterministic and safe.

The worker package must not import `Coretsia\Platform\Http\*`.

## Safety limits

The worker runtime has the following safety controls.

### `worker.enabled`

Worker runtime is opt-in.

Installing `platform/worker` must not implicitly activate the long-running
runtime.

### `worker.workers`

Controls the number of worker child processes started by the selected process
driver.

The value must be a positive integer.

### `worker.max_requests`

Controls the maximum number of tasks handled by one child worker process before
the loop stops or the process is recycled by its supervisor/manager flow.

The value must be a positive integer.

### `worker.stop_flag_path`

Controls graceful shutdown between tasks.

`ApplicationWorker` checks the stop flag only between tasks.

It must not interrupt an in-flight task.

### `worker.stop_timeout_ms`

Controls the graceful stop timeout used by process-driver lifecycle logic.

The value must be a non-negative integer.

### Path safety

Worker runtime paths must be skeleton-root-relative.

They must not be absolute paths.

They must not contain:

```text
..
skeleton/
backslashes
control characters
whitespace
://
segments beginning with @
```

Worker paths must not be logged or exposed in public diagnostics.

## State model and state file

The worker state file is owned by `WorkerStateStore`.

The default path is:

```text
var/tmp/worker.state.json
```

There is no separate `worker.pid_path` config key.

The master pid is stored inside the worker state schema.

The persisted worker state schema contains only safe fields:

```text
version
pid
worker_count
driver_requested
driver
control_transport_requested
control_transport
endpoint_hash
```

The state file must not contain:

- timestamps;
- environment values;
- raw socket paths;
- raw TCP hosts or ports;
- absolute paths;
- task payloads;
- HTTP headers;
- cookies;
- Authorization values;
- tokens;
- raw endpoint identifiers.

`WorkerStateStore` is the only worker runtime class allowed to write
`worker.state.json`.

Process drivers must persist state only through `WorkerStateStore`.

Missing state marker means the worker pool is not currently running.

Existing but invalid state means invalid state, not not-running.

Invalid state includes:

- unreadable state marker;
- non-file state path;
- invalid JSON;
- non-map JSON;
- schema drift;
- forbidden extra keys;
- invalid value types;
- invalid value domains.

Public state-related failures must not expose raw state paths, absolute paths,
endpoint identifiers, OS error text, decoded payloads, or previous throwable
messages.

## Control channel

`WorkerSocketServer` owns worker control-channel behavior.

The control channel supports:

```text
unix
tcp
```

Control operations are payload-free.

Allowed stable control operation tokens include:

```text
start
stop
status
health
```

The control channel must not transport task payloads.

Control failures map to deterministic worker communication failures.

Public diagnostics must not expose:

- raw socket paths;
- socket basenames;
- raw TCP hosts;
- raw TCP ports;
- raw endpoint strings;
- payloads;
- headers;
- tokens;
- throwable messages.

## Command output

Worker command output must use only contracts-level output ports.

The worker command classes write through:

```text
Coretsia\Contracts\Cli\Output\OutputInterface
```

They must not write stdout or stderr directly.

Successful public summaries may include only safe fields:

```text
status
pid
worker_count
driver
control_transport
endpoint_hash
```

Failure output must use deterministic error codes and reason tokens.

Failure output must not include raw config values, raw endpoints, absolute
paths, environment values, payloads, headers, tokens, stack traces, or throwable
messages.

## Observability

Worker observability must comply with:

```text
docs/ssot/observability.md
```

The worker runtime uses the span names:

```text
worker.process
worker.task
```

The worker runtime uses the metric names:

```text
worker.process_total
worker.task_total
worker.task_duration_ms
```

The allowed worker process metric label is:

```text
status
```

The allowed worker task metric labels are:

```text
operation
outcome
```

Worker metric labels must not include:

```text
worker_id
pid
path
socket
endpoint
payload
exception_class
error_reason
```

Worker logs are summary-only.

Worker spans are summary-only.

Logger, tracer, meter, stopwatch, and context dependencies are injected.

Worker runtime classes must not instantiate observability adapters directly.

Observability adapter failures must be caught and must not change worker control
flow, task control flow, reset semantics, or selected public failure.

## Redaction rules

The worker runtime must not expose:

- raw socket paths;
- raw TCP hosts;
- raw TCP ports;
- raw endpoint identifiers;
- absolute paths;
- task payloads;
- HTTP request paths;
- HTTP headers;
- cookies;
- Authorization values;
- bearer tokens;
- secrets;
- environment values;
- config dumps;
- raw command lines;
- raw JSON payloads;
- stack traces;
- previous throwable messages.

Safe runtime summaries may include:

```text
status
pid
worker_count
driver
control_transport
endpoint_hash
operation
outcome
duration_ms
```

Endpoint identity may be represented publicly only as a deterministic hash.

## Operational notes

### State files

The worker state file is runtime state, not a generated architecture artifact.

It is stored under the skeleton runtime tree by default.

Operators may inspect it locally for debugging, but runtime public output must
remain redacted.

### Pid handling

The worker runtime does not introduce a separate pid file.

The safe master pid value is part of `worker.state.json`.

The pid may appear in allowed safe summaries and span/log attributes only where
the observability policy allows it.

The pid must not be used as a metric label.

### Control transport

Unix control transport is local and path-based.

TCP control transport is host/port based.

Both transports are lifecycle-only.

Neither transport may carry task payloads.

Raw transport endpoint values are considered sensitive.

### Graceful shutdown

Graceful shutdown is requested through worker-owned lifecycle mechanisms such as
the stop flag and the control channel.

`ApplicationWorker` observes the stop flag only between task iterations.

An in-flight UnitOfWork is allowed to finish.

`worker.stop_timeout_ms` bounds stop behavior in process-driver lifecycle logic.

### Cross-platform behavior

`pcntl` is not assumed to exist on every platform.

Windows resolves `worker.driver=auto` to `proc`.

Unix-domain sockets are not assumed to exist on every PHP runtime.

When Unix-domain sockets are unavailable, `worker.control.transport=auto`
resolves to `tcp`.

## Non-goals

This architecture document does not define:

- queue backend behavior;
- acknowledgement semantics;
- retry semantics;
- dead-letter behavior;
- scheduler behavior;
- HTTP request production;
- PSR-7 request construction;
- routing;
- middleware;
- CLI binary dispatch;
- command catalog construction;
- external process supervision;
- RoadRunner integration;
- Swoole integration;
- FrankenPHP integration;
- public worker plugin APIs;
- public task-source plugin APIs;
- container artifact schema;
- config merge implementation;
- config validation implementation;
- production observability exporter configuration.

## Required update path

Changing worker process ownership, manager/application-worker boundaries, state
schema, task factory visibility, or process driver extension policy requires
updating:

```text
docs/adr/ADR-0017-worker-manager-application-worker.md
docs/architecture/worker.md
```

Changing runtime-driver ids, activation rules, compatibility rules, or
runtime-driver matrix failure semantics requires updating:

```text
docs/ssot/runtime-drivers.md
docs/architecture/runtime-driver-guard.md
```

Changing the `worker` config root ownership or defaults/rules authority requires
updating:

```text
docs/ssot/config-roots.md
```

Changing worker spans, metrics, or allowed metric labels requires updating:

```text
docs/ssot/observability.md
```

## Cross-references

- [Runtime Driver Guard Architecture](./runtime-driver-guard.md)
- [Config Roots Registry](../ssot/config-roots.md)
- [Observability SSoT](../ssot/observability.md)
- [Runtime Drivers SSoT](../ssot/runtime-drivers.md)
- [UnitOfWork and Reset Contracts SSoT](../ssot/uow-and-reset-contracts.md)
- [ADR-0017: Worker manager and application worker](../adr/ADR-0017-worker-manager-application-worker.md)
- [ADR-0019: Enhanced reset for long-running services](../adr/ADR-0019-enhanced-reset-long-running.md)
- [ADR-0020: Kernel runtime UnitOfWork SPI](../adr/ADR-0020-kernel-runtime-uow-spi.md)
- [ADR-0027: Runtime driver guard](../adr/ADR-0027-runtime-driver-guard.md)

## Related implementation

- `framework/packages/platform/worker/config/worker.php`
- `framework/packages/platform/worker/config/rules.php`
- `framework/packages/platform/worker/bin/coretsia-worker`
- `framework/packages/platform/worker/src/Console/WorkerStartCommand.php`
- `framework/packages/platform/worker/src/Console/WorkerStopCommand.php`
- `framework/packages/platform/worker/src/Console/WorkerStatusCommand.php`
- `framework/packages/platform/worker/src/Manager/WorkerManager.php`
- `framework/packages/platform/worker/src/Manager/Driver/PcntlWorkerManagerDriver.php`
- `framework/packages/platform/worker/src/Manager/Driver/ProcWorkerManagerDriver.php`
- `framework/packages/platform/worker/src/Runtime/WorkerPoolSpec.php`
- `framework/packages/platform/worker/src/Runtime/WorkerPoolState.php`
- `framework/packages/platform/worker/src/Runtime/WorkerStateStore.php`
- `framework/packages/platform/worker/src/Communication/WorkerSocketServer.php`
- `framework/packages/platform/worker/src/Worker/ApplicationWorker.php`
- `framework/packages/platform/worker/src/Task/QueueTaskFactory.php`
- `framework/packages/platform/worker/src/Task/HttpTaskFactory.php`
