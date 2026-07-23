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

This document is the architecture overview for the `platform/worker` long-running runtime package.

It explains:

- package ownership;
- process model;
- driver selection;
- manager and application-worker boundaries;
- declarative runtime container wiring;
- lazy manager and task-factory resolution;
- runtime path seed ownership;
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

ADR-0017 records that `platform/worker` owns long-running worker runtime orchestration while Kernel and Foundation retain UnitOfWork, hook, and reset semantics.

If this document conflicts with any of the following, the SSoT or ADR wins:

```text
docs/adr/ADR-0017-worker-manager-application-worker.md
docs/adr/ADR-0030-canonical-runtime-container-definitions.md
docs/adr/ADR-0029-kernel-container-compile-artifact.md
docs/ssot/compiled-container.md
docs/adr/ADR-0027-runtime-driver-guard.md
docs/ssot/config-roots.md
docs/ssot/observability.md
docs/ssot/runtime-drivers.md
docs/ssot/runtime-container-definitions.md
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

The worker package may contribute CLI command services, but CLI discovery, catalog construction, binary dispatch, and command UX remain owned by `platform/cli`.

The worker package may preflight HTTP task mode through `Psr\Http\Server\RequestHandlerInterface`, but HTTP adapters and HTTP request production remain outside `platform/worker` and are owned by platform/runtime adapters.

## Architecture components

The main worker architecture components are:

```text
WorkerModule
WorkerServiceProvider
WorkerServiceFactory
WorkerManagerResolverInterface
ContainerWorkerManagerResolver
RuntimePathContext
WorkerStartCommand
WorkerStopCommand
WorkerStatusCommand
WorkerManager
WorkerManagerDriverInterface
PcntlWorkerManagerDriver
ProcWorkerManagerDriver
WorkerPoolSpec
WorkerRuntimeEntrypointGuard
WorkerPoolState
WorkerStateStore
WorkerSocketServer
ApplicationWorker
TaskFactoryInternalInterface
QueueTaskFactory
HttpTaskFactory
WorkerRuntimeDriverContributions
```

The internal interfaces are package-local seams only:

```text
Coretsia\Platform\Worker\Internal\WorkerManagerDriverInterface
Coretsia\Platform\Worker\Internal\TaskFactoryInternalInterface
Coretsia\Platform\Worker\Internal\WorkerManagerResolverInterface
```

`WorkerManagerResolverInterface` is the package-local seam between `WorkerStartCommand`, `ContainerWorkerManagerResolver`, and Worker tests.

It preserves lazy `WorkerManager` resolution and is not a public framework extension point.

`RuntimePathContext` is Kernel-owned runtime infrastructure:

```text
Coretsia\Kernel\Runtime\RuntimePathContext
```

It is not a Worker extension point and is not owned by `platform/worker`.

The following helper is also package-internal:

```text
Coretsia\Platform\Worker\Internal\WorkerRuntimeDriverContributions
```

It maps normalized Worker-owned task type state to the public Kernel `RuntimeDriverContributions` handoff object.

It is not a public Worker extension point.

`WorkerManagerDriverInterface`, `TaskFactoryInternalInterface`, `WorkerManagerResolverInterface`, and `WorkerRuntimeDriverContributions` are package-internal seams.

They must not be treated as public framework extension points or moved to `core/contracts`.

## Declarative container wiring

`WorkerServiceProvider` implements:

```text
ServiceProviderInterface
ContainerDefinitionProviderInterface
```

Its `define()` method is the only canonical source of Worker runtime wiring.

Its `register()` method performs source-host validation and delegates the contribution through:

```php
$builder->registerDefinitionProvider($this);
```

It must not maintain a parallel imperative runtime graph.

The Worker provider defines:

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
cli.command tags
```

Its required service ids are:

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

The allowed entrypoint-owned external runtime seeds are:

```text
ConfigRepositoryInterface
ModulePlan
RuntimePathContext
```

These objects are materialized by the artifact-only runtime entrypoint and supplied to the compiled container through the exact Kernel-owned `RuntimeContainerSeedSet`.

The explicit law is:

```text
Runtime seeds are entrypoint-owned runtime objects.
They are not provider definitions, artifact payloads, or fingerprint inputs.
```

Worker definitions may reference these service ids, but Worker providers must not define, instantiate, serialize, tag, or shadow the seed objects.

The remaining required ids must be defined by the complete runtime graph.

`QueueTaskFactory` and `HttpTaskFactory` are required because task-factory selection resolves one of them internally through `ContainerInterface`.

`WorkerManager` is required because `ContainerWorkerManagerResolver::resolve()` performs a deferred `ContainerInterface::get(WorkerManager::class)` lookup.

`RequestHandlerInterface` is not an unconditional required service. It is a mode-dependent HTTP preflight dependency and may be absent until HTTP task mode is actually validated.

The Worker definition contribution contains no closures.

The Foundation definition adapter may create source-container factory closures while applying canonical definitions.

Worker runtime factories and services may create execution callbacks during runtime service construction or execution, after canonical definition production has completed, including:

```text
PcntlWorkerManagerDriver child runner
task-work run callback
```

Neither adapter-created closures nor Worker runtime callbacks may enter canonical Worker definitions, descriptor values, generated artifact payload values, or fingerprint input.

The Worker runtime graph does not depend on `BootstrapConfig`.

Production artifact compilation does not consume the Worker provider contribution.

Any compilation orchestration that selects provider-produced definitions must consume the same `WorkerServiceProvider::define()` contribution.

## Process model

The worker runtime uses a master-plus-workers process model.

The lifecycle shape is:

```text
worker:start command
  -> WorkerServiceFactory::workerPoolSpec(...)
  -> WorkerPoolSpec
  -> WorkerRuntimeEntrypointGuard::assertEntrypointAllowed(...)
       -> WorkerRuntimeDriverContributions::fromSpec(...) [internal]
       -> Kernel RuntimeEntrypointGuard::assertEntrypointAllowed(...)
  -> WorkerManagerResolverInterface::resolve()
       -> ContainerWorkerManagerResolver
       -> runtime container get(WorkerManager)
  -> WorkerManager::start(...)
  -> selected process driver
  -> master process state
  -> N worker children
  -> ApplicationWorker task loops
```

`WorkerPoolSpec` is the normalized Worker-owned source of truth for `worker.task_type`.

Contribution mapping occurs only inside `WorkerRuntimeEntrypointGuard`, after `WorkerPoolSpec` construction and before Kernel compatibility validation.

Worker commands and launchers do not import or invoke the internal mapper directly.

Kernel does not read the `worker` config root.

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

The `proc` driver is the cross-platform fallback and starts child workers through `proc_open()`.

`WorkerManager` does not perform runtime capability discovery itself.

It receives an already-built `WorkerPoolSpec` and selects a driver by the resolved driver id.

If no matching supported driver exists, lifecycle execution fails with a safe deterministic worker start failure.

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

TCP port `0` is forbidden because it would make endpoint identity and persisted worker state non-deterministic across runs.

Raw socket paths, raw TCP hosts, and raw TCP ports must not appear in public diagnostics, logs, metrics, or public command output.

Endpoint identity may be represented publicly only through a deterministic hash.

## Runtime-driver guard boundary

Runtime-driver composition and module compatibility are Kernel-owned policy.

The public Kernel boundary is:

```text
Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard
```

The public Kernel contribution handoff object is:

```text
Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions
```

The public Worker-owned runtime entrypoint boundary is:

```text
Coretsia\Platform\Worker\Runtime\WorkerRuntimeEntrypointGuard
```

Worker runtime callers use the Worker-owned boundary:

```text
WorkerStartCommand
HttpTaskFactory
bin/coretsia-worker
```

They supply:

```text
ConfigRepositoryInterface
ModulePlan
WorkerPoolSpec
```

`WorkerRuntimeEntrypointGuard` owns:

- validation that `platform.worker` participates in the resolved `ModulePlan`;
- delegation to the package-internal `WorkerRuntimeDriverContributions::fromSpec(...)` mapper;
- construction of explicit Kernel `RuntimeDriverContributions`;
- delegation to the Kernel `RuntimeEntrypointGuard::assertEntrypointAllowed(...)`.

The Worker package owns:

```text
worker.task_type
```

The normalized task type is read from `WorkerPoolSpec` and mapped internally:

```text
queue -> bg.worker_queue
http  -> http.worker
```

`WorkerStartCommand` uses this order:

```text
build WorkerPoolSpec
→ call WorkerRuntimeEntrypointGuard
→ call WorkerManagerResolverInterface::resolve()
→ start WorkerManager
```

Resolving the command service itself must not resolve `WorkerManager`, process drivers, `ApplicationWorker`, or the selected task factory.

Worker callers must not:

- ask Kernel to read `worker.task_type`;
- import `WorkerRuntimeDriverContributions`;
- call `WorkerRuntimeDriverContributions::fromSpec(...)` directly;
- call the Kernel `RuntimeEntrypointGuard` directly;
- call `RuntimeDriverGuard` directly;
- infer contributions from `ModulePlan`;
- duplicate runtime-driver composition;
- duplicate the `platform.http` requirement;
- translate Kernel matrix failures into Worker driver failures.

Missing or invalid `worker.task_type` is a Worker-owned start-validation failure:

```text
CORETSIA_WORKER_START_FAILED: worker-invalid-state
```

Runtime-driver conflicts and missing `platform.http` remain Kernel runtime-driver failures.

HTTP Worker mode must pass `WorkerRuntimeEntrypointGuard` compatibility before `RequestHandlerInterface` resolution.

## Lazy WorkerManager resolution boundary

`WorkerStartCommand` receives `WorkerManagerResolverInterface` instead of a closure factory or an eager `WorkerManager`.

The canonical implementation is:

```text
ContainerWorkerManagerResolver
```

It stores only `ContainerInterface`.

`resolve()`:

1. requests `WorkerManager::class` from the active runtime container;
2. validates the resolved type;
3. returns the valid manager;
4. maps container failures and invalid bindings to a safe deterministic Worker start failure.

It must not expose service ids, container diagnostics, runtime paths, config values, environment values, or nested throwable messages.

The resolver interface and implementation are package-internal and are not public Worker plugin APIs.

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

`WorkerManager` may emit safe lifecycle observability summaries through injected ports.

Observability failures must not alter lifecycle semantics or primary failure precedence.

## Process driver boundary

Process drivers own concrete process lifecycle behavior.

The `pcntl` driver owns fork-based process startup and graceful shutdown when selected.

The `proc` driver owns `proc_open()` process startup and graceful shutdown when selected.

The process-driver factory methods receive runtime filesystem roots through `RuntimePathContext`.

`WorkerServiceFactory::pcntlWorkerManagerDriver(...)` extracts the normalized skeleton root and passes it to `PcntlWorkerManagerDriver`.

`WorkerServiceFactory::procWorkerManagerDriver(...)` extracts the normalized skeleton root and derives the concrete `module-manifest.php`, `config.php`, and `container.php` paths only from `RuntimePathContext::artifactRoot()`.

The concrete process drivers do not receive `RuntimePathContext` or `BootstrapConfig`.

Process drivers may write stop flags, communicate over the control channel, and persist worker state through `WorkerStateStore`.

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

## Proc child artifact-only boot boundary

The `proc` driver starts a fresh PHP child process.

Unlike a forked `pcntl` child, the proc child does not inherit the already-built in-memory runtime container.

The master therefore passes three skeleton-root-relative artifact paths:

```text
module-manifest.php
config.php
container.php
```

The canonical child argv fields are:

```text
--coretsia-worker-module-manifest=<relative-path>
--coretsia-worker-config=<relative-path>
--coretsia-worker-container=<relative-path>
```

Every artifact argument must:

- be non-empty;
- be skeleton-root-relative;
- use `/` separators;
- reject whitespace;
- reject NUL and control characters;
- reject URI schemes;
- reject absolute Unix paths;
- reject absolute Windows paths;
- reject empty path segments;
- reject `.` and `..` segments;
- reject `@`-prefixed segments.

The child process uses its working directory as the explicit normalized skeleton root.

It resolves the three validated relative artifact paths against that skeleton root.

The child then creates:

```text
ArtifactRuntimeInput(
    skeletonRoot,
    artifactRoot
)
```

where `artifactRoot` is derived from the already explicit `container.php` path.

This derivation is not generation discovery and does not select a current generation.

The child invokes:

```text
ArtifactRuntimeBooter
```

with:

```text
ArtifactRuntimeInput
module-manifest.php
config.php
container.php
```

The Kernel artifact hydration boundary restores:

```text
config@1
  -> ArrayConfigRepository
  -> ConfigRepositoryInterface

module-manifest@1
  -> ModulePlanArtifactHydrator
  -> ModulePlan

ArtifactRuntimeInput
  -> RuntimePathContext
```

These three objects form the exact entrypoint-owned runtime seed set.

The proc child artifact-only boot path must not:

- run Bootstrap Phase A;
- run ConfigKernel Phase B;
- read source config files;
- read Composer metadata;
- discover modules;
- resolve presets;
- execute source providers;
- compile a replacement graph;
- calculate fingerprints;
- write or repair artifacts;
- discover a generation directory;
- select a current generation.

After the compiled container is built, the child resolves:

```text
WorkerPoolSpec
WorkerRuntimeEntrypointGuard
ApplicationWorker
```

from that container.

The child validates that the received worker arguments match `WorkerPoolSpec` before starting the application-worker loop.

All child boot failures must remain deterministic and redacted.

Raw artifact paths, absolute paths, config payloads, module-manifest payloads, container payloads, and nested throwable messages must not appear in public child diagnostics.

## Application worker boundary

`ApplicationWorker` owns the child-process task loop.

It processes tasks sequentially without restarting PHP between tasks.

`WorkerServiceFactory::applicationWorker(...)` receives `RuntimePathContext` and passes the normalized value returned by `RuntimePathContext::skeletonRoot()` to `ApplicationWorker`.

`ApplicationWorker` does not depend on `RuntimePathContext` or `BootstrapConfig`.

The loop shape is:

```text
while processed < worker.max_requests:
    if stop flag is present:
        break

    run one task through KernelRuntimeInterface

    processed++
```

The stop flag is checked only between tasks.

The worker must not interrupt an in-flight task.

In-flight task cancellation is outside the Worker runtime contract.

Each task is executed by:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface::runUnitOfWork(...)
```

The resolved worker task type is passed as the UnitOfWork type.

The safe task operation id used for observability is produced by package-local task work and is restricted to low-cardinality values such as:

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

`operation_id` must be deterministic, low-cardinality, and safe for the observability metric label `operation`.

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

`HttpTaskFactory` receives the normalized `WorkerPoolSpec`.

It invokes `WorkerRuntimeEntrypointGuard` before resolving an HTTP request handler.

The Worker-owned guard performs the internal contribution mapping and delegates to the Kernel guard.

`HttpTaskFactory` must not import the mapper or call the Kernel guard directly.

Only after compatibility passes may HTTP task mode require a resolvable:

```text
Psr\Http\Server\RequestHandlerInterface
```

`WorkerServiceFactory::taskFactory(...)` performs lazy task-factory selection.

It receives:

```text
WorkerPoolSpec
ContainerInterface
```

It selects the canonical service id:

```text
queue -> QueueTaskFactory
http  -> HttpTaskFactory
```

It resolves only the selected service.

The resolved service must:

- implement `TaskFactoryInternalInterface`;
- return `true` from `supports($spec)`.

Closure-based queue-task-factory and HTTP-task-factory constructor arguments are forbidden.

Resolution or validation failures are mapped to safe deterministic Worker start failures.

The task-body closure produced by a valid task factory remains runtime work and is not part of declarative container wiring.

Request-handler preflight failures must be deterministic and safe.

The worker package must not import `Coretsia\Platform\Http\*`.

## Safety limits

The worker runtime has the following safety controls.

### Module participation and process startup

`platform.worker` module participation is controlled by mode preset resolution and the resolved `ModulePlan`.

`WorkerRuntimeEntrypointGuard` verifies that `platform.worker` is enabled before Worker runtime execution starts.

`WorkerStartCommand`, `HttpTaskFactory`, and the child launcher reach this check through the same Worker-owned boundary.

This owner precondition does not cause Kernel to discover or infer Worker runtime-driver contributions.

Contributions are produced explicitly from `WorkerPoolSpec` inside the Worker-owned boundary.

Installing `platform/worker` must not start worker processes by itself.

Starting the worker pool is an explicit runtime command action:

```text
worker:start
```

### `worker.workers`

Controls the number of worker child processes started by the selected process driver.

The value must be a positive integer.

### `worker.max_requests`

Controls the maximum number of tasks handled by one child worker process before the loop stops or the process is recycled by its supervisor/manager flow.

The value must be a positive integer.

### `worker.stop_flag_path`

Controls graceful shutdown between tasks.

`ApplicationWorker` checks the stop flag only between tasks.

It must not interrupt an in-flight task.

### `worker.stop_timeout_ms`

Controls the graceful stop timeout used by process-driver lifecycle logic.

The value must be a non-negative integer.

### Path safety

Configured Worker-owned path values remain skeleton-root-relative configuration values.

These include:

```text
worker.state_path
worker.stop_flag_path
worker.control.socket_path
```

Configured relative Worker paths must:

- be non-empty;
- use normalized `/` separators;
- reject NUL and control characters;
- reject URI schemes;
- reject parent traversal;
- reject absolute Unix paths;
- reject absolute Windows drive paths.

These relative config values are distinct from both:

- proc-child artifact path arguments;
- runtime roots carried by `RuntimePathContext`.

The proc-child artifact path arguments are launcher-owned, skeleton-root-relative runtime inputs:

```text
module-manifest.php
config.php
container.php
```

They are not Worker config values and are not generated artifact payload fields.

The runtime roots are carried by:

```text
RuntimePathContext
```

`RuntimePathContext::skeletonRoot()` and `RuntimePathContext::artifactRoot()` may be normalized absolute runtime paths.

The runtime context object and its path values must never be:

- exposed in public diagnostics;
- copied into canonical definition values;
- emitted as descriptor field values;
- serialized as generated artifact payload values;
- included in fingerprint input;
- inferred from Worker config by Worker runtime services.

The service id:

```text
Coretsia\Kernel\Runtime\RuntimePathContext
```

may still appear in required-service declarations and typed service references.

`RuntimePathContext` validation does not read the filesystem or resolve symlinks.

All Worker path diagnostics remain redacted.

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

`WorkerStateStore` is the only worker runtime class allowed to write `worker.state.json`.

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

Public state-related failures must not expose raw state paths, absolute paths, endpoint identifiers, OS error text, decoded payloads, or previous throwable messages.

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

Worker-owned config and normalization failures use Worker error policy.

Kernel runtime-driver matrix failures are surfaced with their original Kernel error code and reason token.

The command must not reclassify one category as the other.

Failure output must not include raw config values, raw endpoints, absolute paths, environment values, payloads, headers, tokens, stack traces, or throwable messages.

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

Observability adapter failures must be caught and must not change worker control flow, task control flow, reset semantics, or selected public failure.

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

Operators may inspect it locally for debugging, but runtime public output must remain redacted.

### Pid handling

The worker runtime does not introduce a separate pid file.

The safe master pid value is part of `worker.state.json`.

The pid may appear in allowed safe summaries and span/log attributes only where the observability policy allows it.

The pid must not be used as a metric label.

### Control transport

Unix control transport is local and path-based.

TCP control transport is host/port based.

Both transports are lifecycle-only.

Neither transport may carry task payloads.

Raw transport endpoint values are considered sensitive.

### Graceful shutdown

Graceful shutdown is requested through worker-owned lifecycle mechanisms such as the stop flag and the control channel.

`ApplicationWorker` observes the stop flag only between task iterations.

An in-flight UnitOfWork is allowed to finish.

`worker.stop_timeout_ms` bounds stop behavior in process-driver lifecycle logic.

### Cross-platform behavior

`pcntl` is not assumed to exist on every platform.

Windows resolves `worker.driver=auto` to `proc`.

Unix-domain sockets are not assumed to exist on every PHP runtime.

When Unix-domain sockets are unavailable, `worker.control.transport=auto` resolves to `tcp`.

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
- production artifact-compilation consumption of declarative provider definitions;
- container artifact schema;
- config merge implementation;
- config validation implementation;
- production observability exporter configuration.

## Required update path

Changing Worker declarative container wiring, required-service declarations, lazy manager resolution, lazy task-factory selection, or runtime path seed policy requires updating:

```text
docs/adr/ADR-0017-worker-manager-application-worker.md
docs/architecture/worker.md
docs/adr/ADR-0030-canonical-runtime-container-definitions.md
docs/ssot/runtime-container-definitions.md
```

Changing proc-child artifact paths, artifact-runtime hydration, runtime seed ownership, or child compiled-container boot requires updating:

```text
docs/architecture/worker.md
docs/adr/ADR-0029-kernel-container-compile-artifact.md
docs/ssot/compiled-container.md
framework/packages/platform/worker/tests/Contract/CoretsiaWorkerChildLauncherContractTest.php
framework/packages/platform/worker/tests/Integration/CompiledWorkerGraphContainsRequiredRuntimeServicesTest.php
```

Changing worker process ownership, manager/application-worker boundaries, state schema, task factory visibility, or process driver extension policy requires updating:

```text
docs/adr/ADR-0017-worker-manager-application-worker.md
docs/architecture/worker.md
```

Changing runtime-driver ids, Kernel selector rules, contribution composition, compatibility rules, or runtime-driver matrix failure semantics requires updating:

```text
docs/adr/ADR-0027-runtime-driver-guard.md
docs/ssot/runtime-drivers.md
docs/architecture/runtime-driver-guard.md
docs/architecture/worker.md
```

Changing the Worker task-type-to-contribution mapping or Worker entrypoint boundary also requires updating:

```text
framework/packages/platform/worker/src/Runtime/WorkerRuntimeEntrypointGuard.php
framework/packages/platform/worker/src/Internal/WorkerRuntimeDriverContributions.php
framework/packages/platform/worker/tests/Unit/WorkerRuntimeDriverContributionsTest.php
framework/packages/platform/worker/tests/Contract/WorkerStartCommandContractTest.php
framework/packages/platform/worker/tests/Contract/CoretsiaWorkerChildLauncherContractTest.php
```

Changing the `worker` config root ownership or defaults/rules authority requires updating:

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
- [Compiled Container SSoT](../ssot/compiled-container.md)
- [ADR-0029: Kernel compiled container artifact](../adr/ADR-0029-kernel-container-compile-artifact.md)
- [Canonical Runtime Container Definitions SSoT](../ssot/runtime-container-definitions.md)
- [ADR-0030: Canonical Runtime Container Definitions](../adr/ADR-0030-canonical-runtime-container-definitions.md)
