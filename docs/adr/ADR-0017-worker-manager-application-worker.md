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

# ADR-0017: Worker manager and application worker

## Status

Accepted.

## Context

Coretsia needs a long-running runtime package that can process many units of work without restarting PHP for each task.

The worker runtime must support:

- a deterministic worker pool lifecycle;
- a process-driver abstraction for Unix-like and cross-platform execution;
- safe start, stop, and status operations;
- a package-owned `worker` config root;
- deterministic state persistence;
- payload-free control communication;
- task execution through the canonical Kernel UnitOfWork runtime boundary;
- reset discipline between units of work;
- safe observability and diagnostics;
- package-contributed CLI commands without depending on `platform/cli`.

The worker runtime belongs to:

```text
framework/packages/platform/worker/
```

The package identity is:

```text
package id: platform/worker
composer: coretsia/platform-worker
module id: platform.worker
kind: runtime
config root: worker
```

The `worker` config root is registered in the canonical config roots registry.

The package must depend only on:

```text
core/contracts
core/foundation
core/kernel
```

The package must not depend on:

```text
platform/cli
platform/http
integrations/*
```

Long-running task execution must reuse Kernel-owned lifecycle semantics instead of defining a parallel lifecycle inside `platform/worker`.

The canonical runtime entrypoint for executing a task as a unit of work is:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

Kernel owns:

- UnitOfWork id creation;
- correlation id creation;
- base context writes;
- before-unit-of-work hook invocation;
- after-unit-of-work hook invocation;
- reset orchestration.

`platform/worker` owns only worker-pool orchestration, process lifecycle, control-channel behavior, worker state storage, and package-local task source preflight.

Runtime-driver composition must be checked before worker pool startup.

The canonical runtime-driver matrix guard is:

```text
Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard
```

The worker package must not duplicate runtime-driver matrix policy.

## Decision

Coretsia will introduce `platform/worker` as the package that owns the long-running worker runtime.

The package will provide:

```text
WorkerModule
WorkerServiceProvider
WorkerServiceFactory
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
WorkerStartCommand
WorkerStopCommand
WorkerStatusCommand
```

The package will keep worker orchestration deterministic, side-effect boundaries explicit, and diagnostics safe.

## Package ownership decision

`platform/worker` owns the long-running worker runtime.

It owns:

- worker module metadata;
- worker config defaults and rules;
- worker service provider wiring;
- worker pool specification;
- worker pool state schema;
- worker state storage;
- worker control channel protocol;
- worker process manager orchestration;
- package-internal process drivers;
- package-internal task factories;
- application worker task loop;
- package-contributed worker CLI command classes.

It does not own:

- CLI binary dispatch;
- CLI command catalog construction;
- HTTP platform adapters;
- queue integrations;
- external queue transport semantics;
- Kernel UnitOfWork lifecycle semantics;
- Kernel hook discovery;
- reset discovery;
- reset execution semantics;
- runtime-driver matrix policy.

## Config root decision

The `worker` config root is owned by `platform/worker`.

The package-owned files are:

```text
framework/packages/platform/worker/config/worker.php
framework/packages/platform/worker/config/rules.php
```

`config/worker.php` returns the worker subtree directly.

It must not return a wrapper array such as:

```php
['worker' => [...]]
```

The worker config root contains worker-pool configuration only.

The worker package may read worker config values through `ConfigRepositoryInterface`.

It must not read environment variables for defaults.

It must not invent missing defaults outside the package-owned defaults file.

## Runtime-driver guard decision

`WorkerStartCommand` must call `RuntimeDriverGuard` before starting the worker pool.

The command must call:

```text
RuntimeDriverGuard::assertCompatible(...)
```

before creating or starting the worker manager.

When `worker.task_type=http`, the command must also call:

```text
RuntimeDriverGuard::assertHttpDriverCompatibleWithModules(...)
```

with the caller-provided `ModulePlan`.

Runtime-driver guard failures must be surfaced using the guard's deterministic error codes and reason tokens.

The worker package must not translate guard failures into worker-specific driver conflict errors.

The canonical guard errors remain:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
```

Missing `platform.http` for `worker.task_type=http` must fail through the runtime-driver guard before `RequestHandlerInterface` resolution.

## CLI command decision

`platform/worker` introduces package-contributed command classes:

```text
worker:start
worker:stop
worker:status
```

The command classes implement the contracts-level CLI command port.

They use only:

```text
Coretsia\Contracts\Cli\Command\CommandInterface
Coretsia\Contracts\Cli\Input\InputInterface
Coretsia\Contracts\Cli\Output\OutputInterface
```

They must not depend on `platform/cli`.

They must not require full binary or catalog dispatch.

They may be tested through direct command invocation or a package-local command harness.

Full end-to-end `coretsia worker:*` dispatch through container-backed CLI tag discovery belongs to a later `platform/cli` epic.

When `platform/worker` contributes commands through the `cli.command` tag, it uses the existing reserved tag owned by `platform/cli`.

This contribution does not make `platform/worker` an owner of CLI discovery, catalog construction, dispatch semantics, or command rendering.

## Worker manager decision

`WorkerManager` owns high-level pool lifecycle orchestration:

```text
start
stop
status
```

`WorkerManager` accepts an already-built `WorkerPoolSpec`.

It delegates process-specific behavior to package-internal `WorkerManagerDriverInterface` implementations.

`WorkerManager` must not:

- fork;
- call `proc_open()`;
- open sockets directly;
- write state files directly;
- write stop files directly;
- write socket files directly;
- call `RuntimeDriverGuard`;
- call `KernelRuntimeInterface` for individual task execution;
- enumerate reset tags;
- enumerate before/after UnitOfWork hook tags;
- call `ResetOrchestrator` directly;
- write stdout or stderr directly.

Runtime-driver compatibility belongs to `WorkerStartCommand`.

Task execution belongs to `ApplicationWorker`.

Reset execution belongs to KernelRuntime and Foundation reset orchestration.

## Process driver decision

`WorkerManagerDriverInterface` is package-internal.

It is not a public framework port.

It must remain under:

```text
Coretsia\Platform\Worker\Internal
```

It must be marked `@internal`.

It must not be moved to `core/contracts`.

It must not be documented as a public extension point.

The interface defines only the package-local process-driver seam between `WorkerManager`, concrete drivers, and tests.

It exposes:

```text
name(): string
supports(WorkerPoolSpec $spec): bool
start(WorkerPoolSpec $spec): WorkerPoolState
stop(WorkerPoolSpec $spec): WorkerPoolState
status(WorkerPoolSpec $spec): WorkerPoolState
```

`platform/worker` provides two process drivers:

```text
pcntl
proc
```

The `pcntl` driver is selected only when the resolved driver is `pcntl`, `pcntl_fork` is available, and the current platform is not Windows.

The `proc` driver is the cross-platform fallback.

Driver auto-resolution must be deterministic.

Driver support checks must not depend on hidden global state beyond explicit capability inputs used to build `WorkerPoolSpec`.

## Application worker decision

`ApplicationWorker` owns the child-process task loop.

It processes tasks sequentially without restarting PHP.

Each task must execute through:

```text
KernelRuntimeInterface::runUnitOfWork(...)
```

Each task is a separate UnitOfWork.

`ApplicationWorker` must not:

- create its own UnitOfWork id;
- create its own correlation id;
- write context values directly;
- invoke Kernel hooks directly;
- enumerate reset tags;
- call `ResetOrchestrator` directly;
- implement queue adapter behavior;
- implement HTTP adapter behavior;
- write stdout or stderr directly.

The resolved worker task type is passed to KernelRuntime as the UnitOfWork type.

The task operation id used for worker task observability comes from package-internal task work, not from untrusted payloads or raw runtime data.

## Task factory decision

`TaskFactoryInternalInterface` is package-internal.

It is not a public task-source extension point.

It must not be moved to `core/contracts`.

It must not be exported through package metadata as a public API.

Task factories produce package-internal task work for `ApplicationWorker`.

Task work contains:

```text
operation_id
run
```

`operation_id` must be deterministic and safe for observability.

The allowed operation ids introduced by this ADR are:

```text
queue
http
```

The `run` value is a closure executed inside the KernelRuntime UnitOfWork boundary.

`QueueTaskFactory` handles:

```text
worker.task_type=queue
```

It does not implement a real external queue adapter.

External queue sources, acknowledgement semantics, retry semantics, and integration-specific adapters are owned by later integration epics.

`HttpTaskFactory` handles:

```text
worker.task_type=http
```

It does not implement a real HTTP request source.

It must not depend on `platform/http`.

It may validate that `Psr\Http\Server\RequestHandlerInterface` is resolvable.

Request-handler preflight must happen only after RuntimeDriverGuard/module compatibility has passed.

Request handler preflight failures use deterministic worker start failures:

```text
request_handler_missing
request_handler_unresolvable
request_handler_invalid
```

## Worker state decision

`WorkerPoolState` is the deterministic runtime state model for a running worker pool.

It records only safe state fields required by start, stop, and status flows.

The state serialization must be deterministic.

`WorkerStateStore` owns reading and writing the worker state file.

Process drivers must not write state JSON directly.

Missing state marker means the worker pool is not currently running.

Existing invalid state markers, unreadable state markers, non-file state paths, invalid JSON, schema drift, and invalid values are invalid state failures, not not-running failures.

Public diagnostics must not expose raw state paths, raw socket paths, TCP endpoints, absolute paths, payloads, headers, tokens, environment values, or raw JSON bytes.

## Control channel decision

`WorkerSocketServer` owns the worker control channel.

The control channel supports:

```text
unix
tcp
```

The control channel is for lifecycle/control frames only.

It must not be used for task payload transport.

Control frames are payload-free.

Allowed control operations are stable low-cardinality operation tokens such as:

```text
start
stop
status
health
```

Control communication failures must map to deterministic worker communication failures.

Public communication failure diagnostics must not expose raw socket paths, raw TCP endpoints, hostnames, ports, payloads, headers, tokens, or throwable messages.

## Error decision

Worker package failures use deterministic package exceptions.

The worker package introduces or uses:

```text
WorkerException
WorkerStartFailedException
WorkerForkFailedException
WorkerCommunicationFailedException
WorkerNotRunningException
```

Public worker exception messages use the canonical form:

```text
<ERROR_CODE>: <reason>
```

Worker exceptions expose stable:

```text
errorCode()
reason()
```

Worker exceptions must not expose previous throwable messages in public messages.

Unknown internal failures must be mapped to safe deterministic worker failures.

Runtime-driver matrix failures remain Kernel runtime-driver guard failures and must not be reclassified as worker failures.

## Observability decision

Worker observability must comply with the canonical observability SSoT.

The worker runtime introduces the spans:

```text
worker.process
worker.task
```

The worker runtime introduces the metrics:

```text
worker.process_total
worker.task_total
worker.task_duration_ms
```

Worker metric labels are restricted to allowlisted low-cardinality labels.

Worker process metrics may use:

```text
status
```

Worker task metrics may use:

```text
operation
outcome
```

Worker metrics must not use:

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

Logs and spans must be summary-only.

Observability failures must not alter worker lifecycle semantics, task execution semantics, reset semantics, or primary failure precedence.

ApplicationWorker stopwatch start/stop failures are task observability failures. They must not alter task execution, KernelRuntime delegation, task outcome selection, or primary failure precedence. When task timing is unavailable, worker task duration metadata must collapse to `0`.

Worker runtime classes must not instantiate observability adapters directly.

Logger, tracer, meter, stopwatch, and context access dependencies are injected.

## Security and redaction decision

The worker runtime must not leak:

- raw socket paths;
- raw TCP hosts or ports;
- absolute paths;
- task payloads;
- HTTP payloads;
- queue payloads;
- headers;
- cookies;
- Authorization values;
- tokens;
- environment values;
- config dumps;
- raw command lines;
- stack traces;
- previous throwable messages.

Allowed public summaries may include only safe fields such as:

```text
status
pid
worker_count
driver
control_transport
endpoint_hash
operation
outcome
```

Raw endpoint identifiers must not be public output.

Endpoint identity may be represented publicly only as a deterministic hash.

## Consequences

### Positive

Coretsia gains a deterministic long-running worker runtime package.

Worker lifecycle orchestration has a single package owner.

Process-driver behavior is isolated behind a package-internal seam.

The worker package can run on platforms without `pcntl` through the `proc` fallback.

Worker tasks reuse the canonical Kernel UnitOfWork lifecycle.

Reset discipline remains Kernel/Foundation-owned rather than duplicated by `platform/worker`.

Runtime-driver compatibility remains centralized in `core/kernel`.

Worker commands can exist without coupling `platform/worker` to `platform/cli`.

HTTP task mode can verify the presence of a request handler without importing `platform/http`.

Worker state and control-channel behavior have explicit redaction boundaries.

Observability names and labels are registered and low-cardinality.

### Trade-offs

The first worker implementation intentionally uses placeholder task factories instead of real queue or HTTP transport integrations.

Real queue sources are deferred to later integration epics.

Real HTTP request production is deferred to later platform/runtime adapter epics.

`WorkerManagerDriverInterface` and `TaskFactoryInternalInterface` are internal seams, so third-party task-source extension is not introduced by this ADR.

`proc` fallback requires deterministic command construction and stricter command argument validation.

Safe public diagnostics provide less ad hoc debugging context than raw process, socket, path, or payload output.

Full `coretsia worker:*` binary dispatch is deferred until the container-backed `platform/cli` command catalog exists.

## Rejected alternatives

### Put worker runtime ports in `core/contracts`

Rejected.

The process manager and task factory seams are package-local implementation details.

They are not cross-framework contracts.

Moving them to `core/contracts` would freeze an extension surface before real queue, scheduler, HTTP, and external worker integration requirements are known.

### Let `WorkerManager` enforce runtime-driver compatibility

Rejected.

Runtime-driver composition is Kernel-owned policy.

`WorkerManager` receives an already-built `WorkerPoolSpec` and delegates process lifecycle behavior.

`WorkerStartCommand` is the correct boundary for enforcing runtime-driver guard policy before pool startup.

### Let `ApplicationWorker` invoke hooks and reset directly

Rejected.

Hook discovery, hook ordering, context lifecycle, and reset orchestration are Kernel/Foundation-owned semantics.

The worker runtime must enter the canonical UnitOfWork boundary through `KernelRuntimeInterface`.

### Depend on `platform/cli` for worker commands

Rejected.

`platform/worker` may contribute command services using the existing `cli.command` tag, but command catalog construction and binary dispatch are owned by `platform/cli`.

The worker package must be testable through direct command invocation without requiring `platform/cli`.

### Depend on `platform/http` for HTTP task mode

Rejected.

HTTP task mode needs a request handler preflight, not a compile-time dependency on a concrete platform HTTP package.

Future presets or packages may provide the handler binding.

`platform/worker` must not import `Coretsia\Platform\Http\*`.

### Send task payloads over the control socket

Rejected.

The worker control channel is lifecycle-only.

Task payload transport belongs to future queue, HTTP, scheduler, or integration adapters.

The control protocol remains payload-free to keep diagnostics safe and low-cardinality.

## Non-goals

This ADR does not define:

- a production queue adapter;
- queue acknowledgement semantics;
- retry semantics;
- dead-letter semantics;
- a production HTTP request source;
- PSR-7 request construction for worker HTTP tasks;
- HTTP routing;
- CLI binary dispatch;
- CLI command catalog construction;
- a public task-source plugin API;
- a public worker process-driver plugin API;
- external process supervision;
- RoadRunner integration;
- Swoole integration;
- FrankenPHP integration;
- scheduler integration;
- container artifact schema;
- config merge implementation;
- config validation implementation;
- reset tag discovery;
- hook discovery;
- hook ordering semantics;
- production observability exporter configuration.

## Verification evidence

Expected verification includes:

```text
framework/packages/platform/worker/tests/Unit/WorkerManagerLifecycleTest.php
framework/packages/platform/worker/tests/Unit/WorkerPoolSpecTest.php
framework/packages/platform/worker/tests/Unit/WorkerPoolStateTest.php
framework/packages/platform/worker/tests/Contract/ApplicationWorkerStopwatchFailurePolicyContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerConfigSubtreeShapeContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerRuntimeDoesNotWriteToStdoutTest.php
framework/packages/platform/worker/tests/Contract/WorkerExceptionsAreDeterministicContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerInternalInterfacesAreNotPublicApiContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerCommandsUseCliContractsOnlyTest.php
framework/packages/platform/worker/tests/Contract/WorkerStateJsonSchemaContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerSocketProtocolSafetyContractTest.php
framework/packages/platform/worker/tests/Contract/ProcWorkerManagerDriverSafetyContractTest.php
framework/packages/platform/worker/tests/Integration/ApplicationWorkerTest.php
framework/packages/platform/worker/tests/Integration/WorkerHandlesMultipleTasksSequentiallyTest.php
framework/packages/platform/worker/tests/Integration/MaxRequestsTriggersRecycleTest.php
framework/packages/platform/worker/tests/Integration/WorkerHttpTaskRequiresRequestHandlerTest.php
framework/packages/platform/worker/tests/Integration/WorkerSocketServerTransportTest.php
framework/packages/platform/worker/tests/Integration/WorkerStateStoreFilesystemTest.php
framework/packages/platform/worker/tests/Integration/ProcWorkerManagerDriverProcessTest.php
```

These tests are expected to verify:

- worker config root shape is a subtree;
- worker config rejects invalid scalar, path, and transport values;
- driver and transport auto-resolution is deterministic;
- TCP port `0` is rejected;
- worker state JSON schema is deterministic;
- public state summaries expose endpoint hashes rather than raw endpoints;
- process drivers do not execute task logic directly;
- process drivers do not call KernelRuntime directly;
- WorkerManager does not enforce runtime-driver guard policy;
- WorkerStartCommand invokes RuntimeDriverGuard before pool startup;
- HTTP task mode checks module compatibility before request handler resolution;
- worker command classes use contracts-level CLI ports only;
- worker runtime code does not write stdout or stderr directly;
- worker exceptions expose stable error codes and reason tokens;
- worker exception public messages do not expose previous throwable messages;
- control protocol frames are payload-free;
- ApplicationWorker executes tasks through KernelRuntimeInterface;
- ApplicationWorker accesses Stopwatch only through safe timing wrappers;
- ApplicationWorker stopwatch failures do not alter worker task execution semantics;
- max_requests stops or recycles the worker loop deterministically;
- worker observability uses registered names and allowlisted labels only.

## Related SSoT

- `docs/ssot/config-roots.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/runtime-drivers.md`
- `docs/ssot/tags.md`
- `docs/ssot/uow-and-reset-contracts.md`
- `docs/ssot/context-keys.md`
- `docs/ssot/context-store.md`

## Related ADRs

- `docs/adr/ADR-0016-clock-ids-stopwatch.md`
- `docs/adr/ADR-0019-enhanced-reset-long-running.md`
- `docs/adr/ADR-0020-kernel-runtime-uow-spi.md`
- `docs/adr/ADR-0027-runtime-driver-guard.md`

## Related implementation

- `framework/packages/platform/worker/src/Module/WorkerModule.php`
- `framework/packages/platform/worker/src/Provider/WorkerServiceProvider.php`
- `framework/packages/platform/worker/src/Provider/WorkerServiceFactory.php`
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
- `framework/packages/platform/worker/src/Console/WorkerStartCommand.php`
- `framework/packages/platform/worker/src/Console/WorkerStopCommand.php`
- `framework/packages/platform/worker/src/Console/WorkerStatusCommand.php`

## Related epic

- `1.360.0 Long-Running Runtime: Worker Manager & Application Worker`
