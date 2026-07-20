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

```yaml
adrVersion: 1
status: pre-accepted
owner: platform/worker
```

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

The canonical public Kernel runtime entrypoint compatibility boundary is:

```text
Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard
```

The public Worker-owned boundary used by Worker runtime paths is:

```text
Coretsia\Platform\Worker\Runtime\WorkerRuntimeEntrypointGuard
```

`WorkerRuntimeEntrypointGuard` maps an already-normalized `WorkerPoolSpec` to explicit Kernel `RuntimeDriverContributions` and delegates canonical matrix and module compatibility validation to the Kernel boundary.

The worker package must not duplicate runtime-driver matrix policy.

Worker callers must not call the Kernel-internal `RuntimeDriverGuard` directly.

## Decision

`platform/worker` is the package that owns the long-running worker runtime.

The package provides:

```text
WorkerModule
WorkerServiceProvider
WorkerServiceFactory
WorkerManagerResolverInterface
ContainerWorkerManagerResolver
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

The package keeps worker orchestration deterministic, side-effect boundaries explicit, and diagnostics safe.

## Package ownership decision

`platform/worker` owns the long-running worker runtime.

It owns:

- worker module metadata;
- worker config defaults and rules;
- declarative worker runtime container definitions;
- source-container delegation of those definitions;
- package-internal lazy `WorkerManager` resolution;
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

## Declarative container wiring decision

`WorkerServiceProvider` implements both:

```text
Coretsia\Foundation\Container\ServiceProviderInterface
Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface
```

`WorkerServiceProvider::define()` is the only canonical source of Worker runtime container wiring.

`WorkerServiceProvider::register()` must not maintain a parallel imperative copy of the Worker runtime graph.

Source registration delegates the same contribution through:

```php
$builder->registerDefinitionProvider($this);
```

The Worker definition contribution is closure-free.

It defines:

```text
WorkerServiceFactory
WorkerPoolSpec
WorkerRuntimeEntrypointGuard
StableJsonEncoder
StableJsonDecoder
WorkerStateStore
WorkerSocketServer
QueueTaskFactory
HttpTaskFactory
TaskFactoryInternalInterface
ApplicationWorker
PcntlWorkerManagerDriver
ProcWorkerManagerDriver
WorkerManager
ContainerWorkerManagerResolver
WorkerManagerResolverInterface alias
WorkerStartCommand
WorkerStopCommand
WorkerStatusCommand
cli.command tags for all Worker commands
```

The contribution declares the following required runtime service ids:

```text
ConfigRepositoryInterface
ModulePlan
RuntimePathContext
WorkerPoolSpec
WorkerRuntimeEntrypointGuard
ApplicationWorker
WorkerManager
QueueTaskFactory
HttpTaskFactory
```

The following may be supplied as external runtime seeds:

```text
ConfigRepositoryInterface
ModulePlan
RuntimePathContext
```

The following must be defined by the complete runtime definition graph:

```text
WorkerPoolSpec
WorkerRuntimeEntrypointGuard
ApplicationWorker
WorkerManager
QueueTaskFactory
HttpTaskFactory
```

`QueueTaskFactory` and `HttpTaskFactory` are required declarations because `WorkerServiceFactory::taskFactory(...)` selects one canonical service id and resolves that service through `ContainerInterface`.

`WorkerManager` is also a required declaration because `ContainerWorkerManagerResolver::resolve()` resolves `WorkerManager::class` through `ContainerInterface`.

These declarations describe deferred container-graph edges. They do not imply eager service resolution.

`Psr\Http\Server\RequestHandlerInterface` is not an unconditional Worker required-service declaration.

It is a mode-dependent runtime preflight dependency:

- queue mode does not require it;
- HTTP mode checks it only after `WorkerRuntimeEntrypointGuard` passes;
- its absence is an allowed preflight failure state;
- its absence maps to a deterministic Worker start failure.

It must therefore not make every complete Worker definition graph invalid when HTTP task mode is not selected.

The Worker runtime definition graph must not depend on:

```text
BootstrapConfig
```

Closures created internally by the Foundation source-runtime definition adapter are not part of the canonical Worker contribution.

Worker runtime factories and services may create execution callbacks only during runtime service construction or execution, after canonical definition production has completed.

The runtime-only callbacks include:

```text
PcntlWorkerManagerDriver child runner
package-internal task-work run callback
```

These callbacks are runtime behavior.

They must not be embedded into:

- Worker provider output;
- canonical container-definition operations;
- canonical definition values;
- descriptor streams;
- generated container artifacts;
- fingerprint input.

## Runtime path context decision

Kernel owns the immutable runtime-only path context:

```text
Coretsia\Kernel\Runtime\RuntimePathContext
```

It contains:

```text
skeletonRoot
artifactRoot
```

`RuntimePathContext` may contain normalized absolute runtime paths.

It is not:

- a Bootstrap Phase A result;
- part of `ContainerDefinitionContext`;
- a literal or parameter value in canonical definitions;
- a serializable runtime object value;
- part of fingerprint input.

The `RuntimePathContext` instance and its `skeletonRoot` and `artifactRoot` path values must never be serialized into definitions, descriptors, generated artifact payload values, or fingerprint input.

The canonical service id:

```text
Coretsia\Kernel\Runtime\RuntimePathContext
```

may appear in:

- `requireService()` declarations;
- typed service references;
- complete runtime graph dependency metadata.

The presence of that service id does not serialize the runtime context object or either path value.

In source mode, `KernelServiceProvider::register()` supplies a source-host factory that constructs `RuntimePathContext` from an already-resolved `BootstrapConfig`.

This source-host factory is not part of `KernelServiceProvider::define()` and must not enter compiled runtime definitions.

In artifact mode, boot orchestration must construct `RuntimePathContext` from explicit runtime input.

The Worker runtime graph receives runtime path data through:

```text
WorkerServiceFactory::applicationWorker(...)
WorkerServiceFactory::pcntlWorkerManagerDriver(...)
WorkerServiceFactory::procWorkerManagerDriver(...)
```

`WorkerServiceFactory::applicationWorker(...)` passes the normalized skeleton root from `RuntimePathContext::skeletonRoot()` to `ApplicationWorker`.

`WorkerServiceFactory::pcntlWorkerManagerDriver(...)` passes the normalized skeleton root from `RuntimePathContext::skeletonRoot()` to `PcntlWorkerManagerDriver`.

`WorkerServiceFactory::procWorkerManagerDriver(...)` passes the normalized skeleton root to `ProcWorkerManagerDriver` and derives the concrete compiled config and container artifact paths only from `RuntimePathContext::artifactRoot()`.

The constructed Worker runtime services must not depend on `BootstrapConfig` or independently reconstruct runtime roots or generated artifact locations.

## Runtime entrypoint guard decision

Worker runtime paths must use the Worker-owned public boundary:

```text
WorkerRuntimeEntrypointGuard::assertEntrypointAllowed(...)
```

The boundary receives:

```text
ConfigRepositoryInterface
ModulePlan
WorkerPoolSpec
```

The required startup order is:

```text
build WorkerPoolSpec
→ invoke WorkerRuntimeEntrypointGuard
→ resolve or start runtime services
```

`WorkerRuntimeEntrypointGuard` owns:

- validation that `platform.worker` participates in the resolved `ModulePlan`;
- delegation to the package-internal `WorkerRuntimeDriverContributions::fromSpec(...)` mapper;
- construction of explicit Kernel `RuntimeDriverContributions`;
- delegation to `RuntimeEntrypointGuard::assertEntrypointAllowed(...)`.

`WorkerStartCommand`, `HttpTaskFactory`, and the shipped `bin/coretsia-worker` child launcher must use this Worker-owned boundary.

They must not:

- import `WorkerRuntimeDriverContributions`;
- call the internal mapper directly;
- call the Kernel `RuntimeEntrypointGuard` directly;
- call the Kernel-internal `RuntimeDriverGuard`;
- resolve the runtime-driver matrix independently.

Runtime-driver matrix failures must be surfaced using the Kernel runtime-driver matrix deterministic error codes and reason tokens.

The worker package must not translate guard failures into worker-specific driver conflict errors.

The canonical guard errors remain:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
```

The compatibility check is based on the complete runtime-driver matrix, not only on `worker.task_type`.

In particular, the worker package MUST NOT decide independently that `platform.http` is required only for `worker.task_type=http`.

Missing `platform.http` for any selected non-classic HTTP driver must fail through the Worker-owned boundary, which delegates to the Kernel guard, before `RequestHandlerInterface` resolution.

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

`platform/cli` owns full end-to-end `coretsia worker:*` dispatch through container-backed CLI tag discovery.

`platform/worker` owns only its command services, command metadata, and `cli.command` tag contributions.

When `platform/worker` contributes commands through the `cli.command` tag, it uses the existing reserved tag owned by `platform/cli`.

This contribution does not make `platform/worker` an owner of CLI discovery, catalog construction, dispatch semantics, or command rendering.

`WorkerStartCommand` receives:

```text
WorkerManagerResolverInterface
```

instead of a closure-based `WorkerManager` factory.

Its private manager boundary delegates only to:

```php
return $this->managerResolver->resolve();
```

The canonical container-backed implementation is:

```text
ContainerWorkerManagerResolver
```

It resolves `WorkerManager` lazily from the active runtime container.

Resolution must happen only after:

```text
WorkerPoolSpec construction
→ WorkerRuntimeEntrypointGuard compatibility validation
```

Container resolution failures and invalid `WorkerManager` bindings must be mapped to safe deterministic Worker start failures.

Public diagnostics must not expose service ids, runtime paths, config values, environment values, container implementation details, or nested throwable messages.

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
- call `RuntimeEntrypointGuard`;
- call `WorkerRuntimeEntrypointGuard`;
- call `KernelRuntimeInterface` for individual task execution;
- enumerate reset tags;
- enumerate before/after UnitOfWork hook tags;
- call `ResetOrchestrator` directly;
- write stdout or stderr directly.

Runtime-driver compatibility belongs to `WorkerRuntimeEntrypointGuard`.

`WorkerStartCommand` owns only the ordering requirement that `WorkerRuntimeEntrypointGuard` must pass before `WorkerManagerResolverInterface::resolve()` is called and before `WorkerManager` is started.

`WorkerManagerResolverInterface` is a package-internal lazy-resolution seam.

It must remain under:

```text
Coretsia\Platform\Worker\Internal
```

It must be marked `@internal`.

It must not:

- be moved to `core/contracts`;
- be exported as public package API;
- be documented as a third-party extension point;
- resolve or construct `WorkerManager` during resolver construction.

`ContainerWorkerManagerResolver` is the canonical runtime-container implementation.

It must validate that the resolved service is a `WorkerManager` and map container failures or invalid bindings to safe deterministic Worker start failures.

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

The process-driver factory methods receive runtime filesystem roots through:

```text
RuntimePathContext
```

`WorkerServiceFactory::pcntlWorkerManagerDriver(...)` derives the normalized skeleton root and passes it to `PcntlWorkerManagerDriver`.

`WorkerServiceFactory::procWorkerManagerDriver(...)` derives:

```text
skeleton runtime root
compiled config artifact path
compiled container artifact path
```

from `RuntimePathContext` and passes those concrete values to `ProcWorkerManagerDriver`.

The concrete process drivers do not receive `RuntimePathContext` or `BootstrapConfig`.

The `proc` driver must not independently derive generated artifact locations from environment values, source config discovery, or `BootstrapConfig`.

## Application worker decision

`ApplicationWorker` owns the child-process task loop.

It processes tasks sequentially without restarting PHP.

`WorkerServiceFactory::applicationWorker(...)` receives `RuntimePathContext` rather than a raw skeleton-root factory argument.

It passes the normalized value returned by:

```php
$runtimePaths->skeletonRoot()
```

to the `ApplicationWorker` constructor.

`ApplicationWorker` does not depend on `RuntimePathContext` or `BootstrapConfig` and must not reconstruct runtime roots independently.

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

`WorkerServiceFactory::taskFactory(...)` receives:

```text
WorkerPoolSpec
ContainerInterface
```

It must not receive closure factories for queue and HTTP task factories.

The method:

1. selects the canonical task-factory service id from the normalized `WorkerPoolSpec`;
2. resolves only that selected service through `ContainerInterface`;
3. validates that the resolved service implements `TaskFactoryInternalInterface`;
4. validates that the resolved factory supports the supplied `WorkerPoolSpec`;
5. maps resolution and validation failures to safe deterministic Worker start failures.

The canonical service-id mapping is:

```text
queue -> QueueTaskFactory
http  -> HttpTaskFactory
```

The unselected task-factory service must not be resolved as a side effect of task-factory selection.

Task work contains:

```text
operation_id
run
```

`operation_id` must be deterministic and safe for observability.

The canonical allowed operation ids are:

```text
queue
http
```

The `run` value is a closure executed inside the KernelRuntime UnitOfWork boundary.

This runtime task-body closure is not a container-definition value and must never enter the Worker provider definition set, descriptor stream, generated container artifact, or fingerprint input.

`QueueTaskFactory` handles:

```text
worker.task_type=queue
```

It does not implement a real external queue adapter.

External queue sources, acknowledgement semantics, retry semantics, and integration-specific adapters are owned by integration packages.

`HttpTaskFactory` handles:

```text
worker.task_type=http
```

It does not implement a real HTTP request source.

It must not depend on `platform/http`.

It may validate that `Psr\Http\Server\RequestHandlerInterface` is resolvable.

Request-handler preflight must happen only after `WorkerRuntimeEntrypointGuard` compatibility has passed.

`HttpTaskFactory` must not call the Kernel `RuntimeEntrypointGuard` or the package-internal contribution mapper directly.

Request handler preflight failures use deterministic worker start failures:

```text
worker-request-handler-missing
worker-request-handler-unresolvable
worker-request-handler-invalid
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

Worker runtime wiring has one declarative source.

Source mode consumes that contribution directly, and any compilation path that selects provider-produced definitions must consume the same contribution.

Worker provider definitions contain no closures.

Lazy `WorkerManager` and task-factory resolution are preserved without closure-valued entries in the Worker provider definition graph.

`BootstrapConfig` is no longer part of the Worker runtime dependency graph.

Runtime-only absolute paths are isolated behind an explicit non-serializable runtime seed.

HTTP task mode can verify the presence of a request handler without importing `platform/http`.

Worker state and control-channel behavior have explicit redaction boundaries.

Observability names and labels are registered and low-cardinality.

### Trade-offs

`QueueTaskFactory` and `HttpTaskFactory` are placeholder task sources and do not implement production transport integrations.

External queue sources and queue transport semantics remain owned by integration packages.

HTTP request production remains owned by platform/runtime adapters.

`WorkerManagerDriverInterface` and `TaskFactoryInternalInterface` remain package-internal seams and are not third-party extension points.

`proc` fallback requires deterministic command construction and stricter command argument validation.

Safe public diagnostics provide less ad hoc debugging context than raw process, socket, path, or payload output.

Full `coretsia worker:*` binary dispatch remains outside `platform/worker` and depends on the container-backed command catalog owned by `platform/cli`.

Source-mode and artifact-mode boot orchestration must explicitly provide `RuntimePathContext`.

Production artifact compilation does not consume the Worker provider contribution.

The contribution is closure-free and valid as provider-produced compiler input, but source mode is the active consumer of that contribution.

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

`WorkerRuntimeEntrypointGuard` is the correct Worker-owned boundary for enforcing runtime-driver compatibility.

`WorkerStartCommand` invokes that boundary after constructing `WorkerPoolSpec` and before resolving or starting `WorkerManager`.

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

A preset or package may provide the handler binding.

`platform/worker` must not import `Coretsia\Platform\Http\*`.

### Send task payloads over the control socket

Rejected.

The worker control channel is lifecycle-only.

Task payload transport belongs to queue, HTTP, scheduler, or integration adapters.

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
- production artifact-compilation consumption of the Worker provider contribution;
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
framework/packages/platform/worker/tests/Contract/WorkerStartCommandContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerProviderDefinitionsContainNoClosuresContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerSocketProtocolSafetyContractTest.php
framework/packages/platform/worker/tests/Contract/ProcWorkerManagerDriverSafetyContractTest.php
framework/packages/platform/worker/tests/Integration/ApplicationWorkerTest.php
framework/packages/platform/worker/tests/Integration/WorkerHandlesMultipleTasksSequentiallyTest.php
framework/packages/platform/worker/tests/Integration/MaxRequestsTriggersRecycleTest.php
framework/packages/platform/worker/tests/Integration/WorkerHttpTaskRequiresRequestHandlerTest.php
framework/packages/platform/worker/tests/Integration/WorkerSocketServerTransportTest.php
framework/packages/platform/worker/tests/Integration/WorkerStateStoreFilesystemTest.php
framework/packages/platform/worker/tests/Integration/ProcWorkerManagerDriverProcessTest.php
framework/packages/platform/worker/tests/Integration/WorkerProviderSourceDefinitionsParityTest.php
framework/packages/platform/worker/tests/Integration/WorkerStartCommandResolvesManagerLazilyTest.php
framework/packages/platform/worker/tests/Integration/WorkerTaskFactorySelectsServiceLazilyTest.php
framework/packages/core/kernel/tests/Unit/RuntimePathContextValidationTest.php
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
- `WorkerPoolSpec` is constructed before `WorkerRuntimeEntrypointGuard` is invoked;
- `WorkerStartCommand` invokes `WorkerRuntimeEntrypointGuard` before resolving or starting `WorkerManager`;
- Worker provider definitions contain no closures;
- Worker source registration applies the same definition contribution produced by `define()`;
- resolving `WorkerStartCommand` does not resolve `WorkerManager`;
- `WorkerManager` is resolved only after runtime entrypoint compatibility passes;
- task-factory selection resolves only the canonical selected factory service;
- every task-factory container lookup has a matching required-service declaration;
- `RuntimePathContext` validates and normalizes runtime roots without filesystem access;
- `RuntimePathContext::class` is retained as a required runtime service id;
- the `RuntimePathContext` object and its runtime path values never become canonical definition values, generated artifact payload values, or fingerprint input;
- the Worker runtime graph does not depend on `BootstrapConfig`;
- `HttpTaskFactory` invokes `WorkerRuntimeEntrypointGuard` before request-handler resolution;
- the shipped child launcher invokes `WorkerRuntimeEntrypointGuard` before resolving `ApplicationWorker`;
- Worker callers do not import `WorkerRuntimeDriverContributions` or call the Kernel guard directly;
- `WorkerRuntimeEntrypointGuard` validates the `platform.worker` owner precondition;
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
- `docs/ssot/runtime-container-definitions.md`

## Related ADRs

- `docs/adr/ADR-0016-clock-ids-stopwatch.md`
- `docs/adr/ADR-0019-enhanced-reset-long-running.md`
- `docs/adr/ADR-0020-kernel-runtime-uow-spi.md`
- `docs/adr/ADR-0027-runtime-driver-guard.md`
- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md`
