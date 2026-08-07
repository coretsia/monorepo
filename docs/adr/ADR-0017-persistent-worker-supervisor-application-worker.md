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

# ADR-0017: Persistent worker supervisor and application worker

```yaml
adrVersion: 1
status: pre-accepted
owner: platform/worker
```

## Context

Coretsia needs a long-running runtime package that can process many units of work without restarting PHP for each task.

The worker runtime must support:

- one deterministic owner for the complete worker-pool lifecycle;
- cross-platform child-process execution;
- foreground operation under an external service manager or container runtime;
- safe start, stop, status, and health commands;
- deterministic child readiness;
- deterministic graceful and forced shutdown;
- bounded max-request recycle;
- deterministic handling of unexpected child exits;
- a package-owned `worker` config root;
- deterministic diagnostic state persistence;
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

Among Coretsia packages, `platform/worker` may depend only on:

```text
core/contracts
core/foundation
core/kernel
```

It may additionally depend on PSR interfaces used strictly as ports.

The package must not depend on:

```text
platform/cli
platform/http
integrations/*
```

Long-running task execution must reuse Kernel-owned lifecycle semantics instead of defining a parallel task lifecycle inside `platform/worker`.

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

`platform/worker` owns:

- worker-pool orchestration;
- process lifecycle;
- readiness;
- control-channel behavior;
- lifecycle-lock ownership;
- worker state storage;
- recycle policy;
- package-local task source preflight;
- application worker task execution.

Runtime-driver composition must be checked before worker-pool startup.

The canonical public Kernel runtime-entrypoint compatibility boundary is:

```text
Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard
```

The Worker-owned boundary used by Worker runtime paths is:

```text
Coretsia\Platform\Worker\Runtime\WorkerRuntimeEntrypointGuard
```

`WorkerRuntimeEntrypointGuard` maps an already-normalized `WorkerPoolSpec` to explicit Kernel `RuntimeDriverContributions` and delegates canonical matrix and module compatibility validation to the Kernel boundary.

The worker package must not duplicate runtime-driver matrix policy.

The Kernel HTTP/background runtime-driver matrix is distinct from the package-internal OS process-driver selection.

The values:

```text
pcntl
proc
```

describe how the Worker supervisor creates and controls child processes.

They are not Kernel runtime-driver ids.

## Decision

`platform/worker` is the package that owns the long-running worker runtime.

Coretsia will use one foreground persistent supervisor as the sole worker-pool lifecycle owner.

The package provides:

```text
WorkerModule
WorkerServiceProvider
WorkerServiceFactory

WorkerLifecyclePaths
WorkerPoolSpec
WorkerPoolState
WorkerHealthState
WorkerStateStore
WorkerLifecycleLock
WorkerLifecycleLocator
WorkerLifecycleLocatorStore
WorkerShutdownBudget
WorkerStopSignal
WorkerRuntimeEntrypointGuard

WorkerSupervisorInterface
WorkerSupervisorResolverInterface
ContainerWorkerSupervisorResolver
WorkerSupervisor
WorkerChildTable
WorkerSignalController

WorkerProcessCapabilities
WorkerProcessDriverInterface
WorkerProcessDriverResolverInterface
ContainerWorkerProcessDriverResolver
WorkerChildCommandBuilder
PcntlWorkerProcessDriver
ProcWorkerProcessDriver
WorkerForkIsolation
WorkerProcProcessHostProtocol
WorkerProcProcessHostClient
WorkerProcProcessHostHandoffEndpoint
WorkerProcProcessHostTransport

WorkerControlClientInterface
WorkerControlTransport
WorkerControlProtocol
WorkerControlServer
WorkerControlClient
WorkerChildReadinessChannel

ApplicationWorker
TaskFactoryInternalInterface
QueueTaskFactory
HttpTaskFactory

WorkerStartCommand
WorkerStopCommand
WorkerStatusCommand
WorkerHealthCommand
```

The package does not expose lifecycle facades, static process registries, or duplicate process-ownership wrappers.

## Package ownership decision

`platform/worker` owns:

- worker module metadata;
- worker config defaults and validation rules;
- declarative worker runtime container definitions;
- source-container delegation of those definitions;
- worker-pool specification;
- foreground supervisor orchestration;
- package-internal lazy supervisor resolution;
- canonical lifecycle-artifact path ownership;
- lifecycle-lock implementation;
- private active-supervisor locator schema and storage;
- worker child table;
- child readiness protocol;
- worker state schema and storage;
- control transport and protocol;
- package-internal OS process drivers;
- package-internal lazy selected-process-driver resolution;
- canonical shell-free process-child command construction;
- PCNTL fork-exec child-runtime isolation;
- proc process-host infrastructure;
- signal-intent handling;
- graceful and forced shutdown;
- max-request recycle;
- package-internal task factories;
- application worker task loop;
- package-contributed worker CLI command classes.

It does not own:

- CLI binary dispatch;
- CLI command catalog construction;
- HTTP platform adapters;
- queue integrations;
- external queue transport semantics;
- external service-manager restart policy;
- Kernel UnitOfWork lifecycle semantics;
- Kernel hook discovery;
- reset discovery;
- reset execution semantics;
- Kernel runtime-driver matrix policy;
- deployment-specific process-group or cgroup policy.

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

The worker package may read worker config values only through `ConfigRepositoryInterface`.

It must not:

- read environment variables for defaults;
- invent missing defaults outside the package-owned defaults file;
- derive runtime paths from hidden process state;
- derive process-driver capability from unvalidated config strings.

`WorkerPoolSpec` is the normalized Worker-owned source of truth for:

```text
task type
requested OS process driver
resolved OS process driver
requested control transport
resolved control transport
worker count
max requests
lifecycle timeouts
runtime-relative paths
TCP control settings
```

Driver and control-transport auto-resolution must be deterministic.

Lifecycle discovery paths are package-owned and are not mutable worker configuration.

The canonical paths are:

```text
var/tmp/worker.lock
var/tmp/worker.lifecycle.json
var/tmp/worker.lifecycle.json.tmp
```

Current worker configuration is desired input for constructing a new `WorkerPoolSpec`. It does not address or redefine the lifecycle artifacts of an already-running supervisor.

## Declarative container wiring decision

`WorkerServiceProvider` implements:

```text
Coretsia\Foundation\Container\ServiceProviderInterface
Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface
```

`WorkerServiceProvider::define()` is the only canonical source of Worker runtime container wiring.

`WorkerServiceProvider::register()` must not maintain a parallel imperative copy of the Worker runtime graph.

Source registration delegates through:

```php
$builder->registerDefinitionProvider($this);
```

The Worker contribution is closure-free.

It defines:

```text
WorkerServiceFactory
StableJsonEncoder
StableJsonDecoder

WorkerPoolSpec
WorkerRuntimeEntrypointGuard
WorkerStateStore
WorkerLifecycleLock
WorkerLifecycleLocatorStore
WorkerStopSignal

WorkerControlTransport
WorkerControlProtocol
WorkerControlServer
WorkerControlClient
WorkerControlClientInterface alias
WorkerChildReadinessChannel

WorkerChildTable
WorkerSignalController
WorkerForkIsolation
WorkerChildCommandBuilder

QueueTaskFactory
HttpTaskFactory
TaskFactoryInternalInterface
ApplicationWorker

PcntlWorkerProcessDriver
WorkerProcProcessHostProtocol
WorkerProcProcessHostClient
ProcWorkerProcessDriver
ContainerWorkerProcessDriverResolver
WorkerProcessDriverResolverInterface alias

WorkerSupervisor
WorkerSupervisorInterface alias
ContainerWorkerSupervisorResolver
WorkerSupervisorResolverInterface alias

WorkerStartCommand
WorkerStopCommand
WorkerStatusCommand
WorkerHealthCommand
cli.command tags
```

The canonical aliases are:

```text
WorkerControlClientInterface
    -> WorkerControlClient

WorkerProcessDriverResolverInterface
    -> ContainerWorkerProcessDriverResolver

WorkerSupervisorInterface
    -> WorkerSupervisor

WorkerSupervisorResolverInterface
    -> ContainerWorkerSupervisorResolver
```

`WorkerProcessDriverResolverInterface` performs one exact package-owned mapping from the resolved `WorkerPoolSpec::driver()` value to the matching concrete process-driver service.

The resolver does not enumerate tags and does not construct the unselected process driver.

The Worker contribution declares these required runtime service ids:

```text
ConfigRepositoryInterface
ModulePlan
RuntimePathContext
WorkerPoolSpec
WorkerRuntimeEntrypointGuard
WorkerProcessDriverResolverInterface
ApplicationWorker
WorkerSupervisorInterface
WorkerControlClientInterface
QueueTaskFactory
HttpTaskFactory
```

The allowed external runtime seeds are:

```text
ConfigRepositoryInterface
ModulePlan
RuntimePathContext
```

The remaining required ids must be supplied by the complete runtime definition graph.

`WorkerProcessDriverResolverInterface` is required because `WorkerSupervisor` resolves only the selected concrete process driver at lifecycle execution time.

`WorkerSupervisorInterface` is required because `ContainerWorkerSupervisorResolver` performs a deferred lookup through `ContainerInterface`.

`WorkerControlClientInterface` is required because `worker:stop`, `worker:status`, and `worker:health` resolve the canonical live control boundary.

`QueueTaskFactory` and `HttpTaskFactory` are required because task-factory selection resolves only the selected canonical service through `ContainerInterface`.

`Psr\Http\Server\RequestHandlerInterface` is not an unconditional required service.

It is a mode-dependent HTTP preflight dependency whose absence is an allowed deterministic startup failure.

Resolving `WorkerStartCommand` must not resolve `WorkerSupervisorInterface`.

The required ordering is:

```text
construct WorkerPoolSpec
-> validate WorkerRuntimeEntrypointGuard
-> resolve WorkerSupervisorInterface
-> run WorkerSupervisor
```

Runtime-only callbacks may be created after definition production, including the package-internal task-work run callback.

The PCNTL process driver does not receive a child-bootstrap closure or `ContainerInterface`.

Such callbacks must not enter:

- provider output;
- canonical definition operations;
- canonical definition values;
- descriptor streams;
- compiled graphs;
- generated artifacts;
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

Its object value and path values must never be serialized into definitions, descriptor streams, generated artifact payload values, or fingerprint input.

The canonical service id may appear in:

- required-service declarations;
- typed service references;
- complete runtime graph dependency metadata.

Source mode may construct `RuntimePathContext` from already-resolved bootstrap input.

Artifact mode must construct it from explicit runtime input.

The Worker runtime graph consumes runtime path data through path-owning services such as:

```text
WorkerStateStore
WorkerLifecycleLock
WorkerLifecycleLocatorStore
WorkerStopSignal
WorkerControlTransport
WorkerChildCommandBuilder
PcntlWorkerProcessDriver
ProcWorkerProcessDriver
WorkerProcProcessHostClient
```

`ApplicationWorker` receives `WorkerStopSignal`.

It must not independently reconstruct the skeleton root.

`WorkerChildCommandBuilder` receives one validated skeleton-root-relative artifact root.

`PcntlWorkerProcessDriver` and `ProcWorkerProcessDriver` receive the normalized skeleton root and the shared `WorkerChildCommandBuilder`. Neither driver receives a raw artifact root as an independent constructor value.

The PCNTL driver uses the skeleton root only for the post-fork artifact-only exec boundary. The proc process host uses it as the child working directory.

The constructed Worker services must not depend on `BootstrapConfig` or independently reconstruct generated artifact locations.

## Runtime entrypoint guard decision

Worker runtime paths must use:

```text
WorkerRuntimeEntrypointGuard::assertEntrypointAllowed(...)
```

The boundary receives:

```text
ConfigRepositoryInterface
ModulePlan
WorkerPoolSpec
```

It owns:

- validation that `platform.worker` participates in the resolved `ModulePlan`;
- delegation to `WorkerRuntimeDriverContributions::fromSpec(...)`;
- construction of explicit Kernel `RuntimeDriverContributions`;
- delegation to `RuntimeEntrypointGuard::assertEntrypointAllowed(...)`.

The canonical startup order is:

```text
build WorkerPoolSpec
-> invoke WorkerRuntimeEntrypointGuard
-> resolve WorkerSupervisorInterface
-> start driver-owned infrastructure
-> acquire lifecycle authority
```

`WorkerStartCommand`, `HttpTaskFactory`, and the shipped proc child launcher must use this Worker-owned boundary where applicable.

They must not:

- call the Kernel-internal `RuntimeDriverGuard`;
- resolve the runtime-driver matrix independently;
- infer `platform.http` compatibility independently;
- translate Kernel driver conflicts into unrelated Worker errors.

Runtime-driver matrix failures retain the Kernel error codes:

```text
CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT
CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG
```

The Worker OS process-driver selection is independent.

The Kernel guard does not select:

```text
pcntl
proc
```

## CLI command decision

`platform/worker` contributes:

```text
worker:start
worker:stop
worker:status
worker:health
```

The command classes implement contracts-level CLI ports only:

```text
Coretsia\Contracts\Cli\Command\CommandInterface
Coretsia\Contracts\Cli\Input\InputInterface
Coretsia\Contracts\Cli\Output\OutputInterface
```

They must not depend on `platform/cli`.

They may be tested through direct command invocation or a package-local command harness.

`platform/cli` owns:

- full `coretsia worker:*` binary dispatch;
- container-backed command tag discovery;
- command catalog construction;
- generic command rendering.

`platform/worker` owns only its command services, metadata, and `cli.command` tag contributions.

`WorkerStartCommand` owns:

- `WorkerPoolSpec` construction;
- runtime-entrypoint guard invocation;
- lazy supervisor resolution;
- one startup summary emitted after full readiness;
- deterministic mapping of supervisor completion to command exit code.

`WorkerStopCommand`, `WorkerStatusCommand`, and `WorkerHealthCommand` use `WorkerControlClientInterface`.

These lifecycle commands do not construct or resolve `WorkerPoolSpec`. They do not use current worker configuration to address an already-running supervisor.

Their discovery flow is:

```text
probe canonical lifecycle lock
-> read private active lifecycle locator
-> connect to the endpoint recorded by the active supervisor
-> perform the live status, health, or stop request
```

They must not:

- resolve `WorkerPoolSpec` from current config;
- construct a control server;
- own child processes;
- write the cooperative stop signal;
- infer liveness from the state file;
- read or render raw locator fields;
- keep a static process registry.

## Foreground supervisor decision

`worker:start` is a foreground process.

It must not daemonize through:

- shell `&`;
- `nohup`;
- detached shell commands;
- detached `proc_open()` from the command process;
- Windows `start /B`;
- double fork;
- package-owned daemon wrappers.

The canonical startup boundary is:

```text
WorkerStartCommand
  -> build WorkerPoolSpec
  -> execute WorkerRuntimeEntrypointGuard
  -> lazily resolve WorkerSupervisorInterface
  -> WorkerSupervisorInterface::run(...)
```

The supervisor lifecycle is:

```text
resolve selected process driver
-> prepare driver-owned infrastructure
-> acquire canonical lifecycle lock
-> delete stale private lifecycle locator
-> delete stale diagnostic state
-> clear stale cooperative stop signal
-> open control listener
-> install signal handlers
-> publish starting state
-> publish private lifecycle locator
-> spawn configured worker slots
-> wait for readiness from every child
-> publish running state
-> emit one startup callback
-> enter the supervisor event loop
-> serve status / health / stop
-> reap and recycle expected exits
-> process signal-driven shutdown intent
-> stop and reap all children
-> shut down driver-owned infrastructure
-> delete diagnostic state
-> clear cooperative stop signal
-> close control listener
-> remove the Unix socket when applicable
-> delete private lifecycle locator
-> release canonical lifecycle lock
-> return a deterministic process exit code
```

Driver preparation occurs after runtime-entrypoint validation and before lifecycle-lock acquisition.

This ordering is required by the proc process-host model so proc children cannot inherit the supervisor lifecycle lock, control listener, or readiness listeners.

The startup callback is invoked exactly once.

It is invoked only after:

```text
all configured children ready
AND state == running
```

`WorkerSupervisorInterface::run()` remains blocked after the callback until complete shutdown.

## Ownership matrix

| Component                           | Owns                                                                                                                                                                                                               | Must not own                                                                                 |
|-------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------|
| `WorkerStartCommand`                | spec construction, runtime-entrypoint guard invocation, lazy supervisor resolution, startup summary                                                                                                                | lifecycle lock, control server, child table, process spawning, final cleanup                 |
| `WorkerSupervisor`                  | lifecycle lock, private locator publication and deletion, control server lifecycle, child table, readiness aggregation, state publication, signal intent, stop-signal writes, recycle policy, shutdown and cleanup | container lookup, spec construction, daemonization, stdout/stderr writes, task execution     |
| `WorkerSupervisorResolverInterface` | lazy resolution of `WorkerSupervisorInterface`                                                                                                                                                                     | runtime guard policy, pool lifecycle                                                         |
| `WorkerControlTransport`            | address derivation, listen, connect, accept, bounded frame I/O, close, Unix socket cleanup                                                                                                                         | protocol semantics, health policy, lifecycle decisions                                       |
| `WorkerControlProtocol`             | exact versioned request/response encoding and decoding                                                                                                                                                             | process lifecycle, endpoint ownership, task payload transport                                |
| `WorkerControlServer`               | supervisor-side listener and typed sessions                                                                                                                                                                        | startup decisions, health policy, shutdown orchestration                                     |
| `WorkerControlClient`               | lifecycle-lock probing, private locator reads, endpoint-consistency validation, and status/health/stop request-response flow                                                                                       | listener creation, state authority, locator writes, stop-signal writes, child lifecycle      |
| `WorkerProcessDriverInterface`      | capability checks, driver preparation, one-child spawn/poll/terminate/kill/close, driver shutdown                                                                                                                  | state store, control server, pool-wide registry, recycle policy, lifecycle observability     |
| `WorkerChildTable`                  | typed mapping of worker slot, child generation, readiness, and shutdown state                                                                                                                                      | OS process operations, state persistence, control communication                              |
| `WorkerStateStore`                  | atomic diagnostic state write/read/delete                                                                                                                                                                          | liveness authority, control decisions, process ownership                                     |
| `WorkerLifecycleLock`               | exclusive liveness authority and duplicate-start exclusion                                                                                                                                                         | state publication, control communication, child lifecycle                                    |
| `WorkerLifecycleLocatorStore`       | atomic private locator write/read/delete and exact locator file validation                                                                                                                                         | liveness authority, control protocol semantics, state publication, child lifecycle           |
| `WorkerStopSignal`                  | supervisor-written cooperative stop signal observed between tasks                                                                                                                                                  | primary control transport, liveness authority, terminal acknowledgement                      |
| `ApplicationWorker`                 | sequential task loop, task creation, KernelRuntime delegation, max-request exit, stop observation between tasks                                                                                                    | supervisor state, child table, control server, state publication, direct reset orchestration |
| external service manager            | foreground launch, restart policy, deployment policy, process-group or cgroup ownership                                                                                                                            | internal control protocol, state schema, per-task UnitOfWork semantics                       |

## Lazy supervisor resolution decision

The canonical package-internal resolver boundary is:

```text
Coretsia\Platform\Worker\Internal\WorkerSupervisorResolverInterface
```

The canonical implementation is:

```text
Coretsia\Platform\Worker\Supervisor\ContainerWorkerSupervisorResolver
```

It retains only `ContainerInterface`.

Its `resolve()` method:

1. requests `WorkerSupervisorInterface::class`;
2. validates the resolved service type;
3. returns the valid supervisor;
4. maps container failures and invalid bindings to safe deterministic Worker start failures.

Resolution occurs only after:

```text
WorkerPoolSpec construction
-> WorkerRuntimeEntrypointGuard validation
```

The resolver is package-internal.

It must not:

- be moved to `core/contracts`;
- be exported as a public package API;
- be documented as a third-party extension point;
- resolve a supervisor during resolver construction.

## Process-driver decision

The canonical package-internal process-driver interface is:

```text
Coretsia\Platform\Worker\Internal\WorkerProcessDriverInterface
```

It is not a public framework port.

Its operations cover:

```text
name
supports
prepare
spawn one child
poll one child exit
terminate one child
kill one child
close one child
shutdown driver-owned infrastructure
```

`prepare()` and `shutdown()` are driver-infrastructure hooks.

They do not grant pool-wide lifecycle ownership.

A process driver must not:

- write worker state;
- own the control listener;
- write the cooperative stop signal;
- decide status, health, or stop semantics;
- retain an authoritative pool-wide registry;
- decide recycle policy;
- execute task-source logic;
- call `KernelRuntimeInterface`;
- emit pool-wide observability;
- daemonize the supervisor process.

`platform/worker` provides:

```text
PcntlWorkerProcessDriver
ProcWorkerProcessDriver
```

The `pcntl` driver is selected only when:

```text
resolved driver == pcntl
AND required PCNTL/POSIX capabilities are available
AND platform != Windows
```

The `proc` driver is the cross-platform process adapter.

Proc selection requires `proc_open()` and the complete bounded loopback stream capability on every platform. Descriptor isolation does not depend on `ext-sockets` or `SOCK_CLOEXEC`.

Driver auto-resolution must be deterministic. It selects proc only when the process-host stream capability is available and fails start validation when neither process adapter is available.

Support checks must depend only on the normalized `WorkerPoolSpec`, injected platform and capability values, and exact required runtime-function availability.

They must not read Worker configuration or inspect filesystem state, ports, environment variables, external processes, or application services.

## PCNTL process decision

`PcntlWorkerProcessDriver` owns Unix-like operations for one child:

- tokenized readiness-listener creation;
- fork;
- Worker-owned inherited-resource detachment;
- process-image replacement through `pcntl_exec()`;
- non-blocking wait;
- terminate signal;
- kill signal;
- child resource closure.

The PCNTL driver receives `RuntimePathContext`-derived launch data, `WorkerChildCommandBuilder`, `WorkerChildReadinessChannel`, and `WorkerForkIsolation`.

It does not receive `ContainerInterface`, `ApplicationWorker`, `BootstrapConfig`, raw individual artifact paths, or a child-bootstrap closure.

After fork, the child closes its copy of the supervisor-owned readiness listener, invokes `WorkerForkIsolation::prepareForkedChild()`, changes to the explicit skeleton root, and executes the package-owned `bin/coretsia-worker` launcher.

The exec-created PHP runtime performs a fresh artifact-only boot and resolves `ApplicationWorker` from a newly hydrated runtime container.

The forked child may briefly inherit the parent process image, but it resolves no child runtime services before `pcntl_exec()`.

No parent runtime container, shared service cache, `ApplicationWorker` instance, or PHP object graph crosses the exec boundary.

PCNTL and proc children use the same independently tokenized loopback TCP readiness protocol.

### Process-exec descriptor guarantee

`WorkerForkIsolation` closes every Worker-owned supervisor descriptor registered with that boundary.

This guarantee does not extend to arbitrary application, integration, extension, deployment, or third-party descriptors that are unknown to the Worker package.

Such descriptors are governed by:

```text
docs/ssot/process-exec-descriptor-safety.md
```

Neither `PcntlWorkerProcessDriver` nor `ProcWorkerProcessDriver` alone is a framework-wide proof that arbitrary descriptors cannot cross an exec boundary.

Coretsia-owned local file handles request close-on-exec on POSIX where the handle can coexist with process execution. Known Worker-owned sockets, listeners, sessions, and locks retain explicit owner-driven detachment.

## Proc process-host decision

`ProcWorkerProcessDriver` owns one-child operations through a dedicated process host.

The supervisor process must not directly own raw `proc_open()` resources.

The canonical infrastructure is:

```text
WorkerProcProcessHostProtocol
WorkerProcProcessHostClient
WorkerProcProcessHostHandoffEndpoint
WorkerProcProcessHostTransport
bin/coretsia-worker-proc-host
```

The process host starts during driver preparation, before lifecycle-lock acquisition.

This prevents proc children from inheriting:

- the supervisor lifecycle lock;
- the control listener;
- supervisor readiness listeners;
- supervisor-owned control sessions.

Before every proc worker-child launch, the supervisor creates a one-shot handoff listener with a fresh token. The process host validates the spawn request, closes its current authenticated supervisor connection, calls `proc_open()`, and only then establishes and authenticates the replacement connection.

No proc-host protocol connection is open during worker-child launch. This invariant is identical on Windows and POSIX and requires only bounded loopback stream operations.

The process host owns:

- raw proc resources;
- process spawn commands;
- non-blocking process polling;
- terminate and kill operations;
- raw process-resource closure.

It does not own:

- pool lifecycle;
- state publication;
- readiness policy;
- control operations;
- recycle policy;
- shutdown policy.

Process-host communication is:

- internal;
- versioned;
- newline framed;
- deterministic;
- bounded;
- payload-minimal;
- safe for cross-platform operation.

Process command construction must use argv vectors.

It must not construct an untrusted shell command string.

## Process-child artifact-only boot decision

Each PCNTL and proc child receives one validated skeleton-root-relative artifact root:

```text
--coretsia-worker-artifact-root=<relative-safe-path>
```

It must not receive independent config, module-manifest, or container artifact paths.

The child performs:

```text
resolve artifact root
-> locate current
-> validate selected immutable generation
-> read exact generation snapshots
-> hydrate artifact runtime
-> execute WorkerRuntimeEntrypointGuard
-> resolve ApplicationWorker
-> emit readiness
-> enter task loop
```

Each PCNTL and proc spawn performs a fresh artifact-only boot.

This includes a replacement child created during max-request recycle.

A recycled process child must not inherit:

- the previous child’s selected artifact generation;
- artifact file handles;
- previously read artifact bytes;
- the previous runtime container;
- readiness state;
- generation-local runtime state.

If `current` changes between the original child and its replacement, the replacement boots the generation selected at replacement startup.

The process child emits no stdout or stderr diagnostics.

A boot failure terminates the child with a non-zero process code.

Raw artifact roots, generation paths, artifact payloads, config values, and nested throwable messages must not enter public diagnostics.

## Control protocol decision

The supervisor-owned control protocol supports exactly:

```text
status
health
stop
```

It must not contain:

```text
start
```

Pool startup is performed only by the foreground `worker:start` command.

The protocol uses:

- protocol version `1`;
- `StableJsonEncoder`;
- `StableJsonDecoder`;
- one newline-delimited JSON frame per request or response;
- maximum frame size `4096` bytes;
- exact key sets;
- rejection of unknown keys;
- rejection of unsupported versions;
- bounded request identifiers matching `[A-Za-z0-9._-]{1,64}`;
- one per-supervisor-instance 256-bit control credential;
- exact lowercase hexadecimal credential representation;
- constant-time credential comparison through `hash_equals()`;
- silent rejection before session creation;
- payload-free operations.

The canonical request shape is:

```json
{
  "credential": "<64 lowercase hexadecimal characters>",
  "operation": "status",
  "request_id": "request-123-1",
  "version": 1
}
```

The canonical successful response shape is:

```json
{
  "version": 1,
  "request_id": "request-123-1",
  "status": "ok",
  "result": {}
}
```

The credential is generated once for each supervisor instance after stale lifecycle cleanup and before listener publication. Child recycle does not rotate it. A new supervisor start does rotate it.

The server validates the credential after exact protocol decoding and before creating `WorkerControlSession`. Missing, malformed, and non-matching credentials cause silent connection closure and execute no lifecycle operation. Responses never contain or echo the credential.

The control channel supports:

```text
unix
tcp
```

The listener is owned only by the persistent supervisor.

A stop session remains pending until:

- every child has exited;
- every child has been reaped;
- child resources are closed;
- driver infrastructure is shut down;
- diagnostic state is deleted;
- the cooperative stop signal is cleared;
- the control listener is closed;
- the Unix socket is removed when applicable;
- the private lifecycle locator is deleted;
- the lifecycle lock is released.

Only then may the supervisor return:

```text
status = stopped
```

A disconnected status or health client must not terminate the worker pool.

The control protocol must not transport:

- task payloads;
- HTTP bodies;
- queue payloads;
- headers;
- cookies;
- readiness or proc-host tokens;
- credentials other than the required supervisor-instance control credential;
- environment values;
- raw filesystem paths;
- raw endpoint values.

## Lifecycle-lock authority decision

`WorkerLifecycleLock` is the sole liveness authority.

Its anchor path is package-owned and immutable:

```text
var/tmp/worker.lock
```

The path is resolved through `WorkerLifecyclePaths::LOCK`.

It is not read from worker configuration, and current config cannot move the lifecycle anchor.

The lock uses an inode-stable anchor file and non-blocking exclusive lock semantics equivalent to:

```php
$mode = PHP_OS_FAMILY === 'Windows'
    ? 'c+b'
    : 'c+be';

fopen($path, $mode);
flock($handle, LOCK_EX | LOCK_NB);
```

The POSIX `e` flag requests close-on-exec as defense in depth. Explicit `WorkerForkIsolation` detachment remains mandatory and is not replaced by the open mode.

The semantics are:

```text
exclusive lock acquired
  -> this supervisor owns the pool lifecycle

exclusive lock unavailable
  -> a pool is starting, running, or stopping

exclusive lock available during a client probe
  -> the pool is not running
```

A second `worker:start` must fail deterministically as already running.

The lock anchor file must not be unlinked during normal cleanup.

The persistent zero-byte file is an inode-stable lock anchor, not stale runtime state.

Unlinking the path while a process owns the old inode could permit a second process to create and lock a different inode at the same path.

The state file must not be used as liveness authority.

A stale state file with a free lifecycle lock means the pool is not running.

A held lifecycle lock with an unavailable or invalid control endpoint means communication failure, not not-running.

## Active lifecycle discovery

Lifecycle discovery separates three distinct concepts:

```text
WorkerPoolSpec = desired configuration for creating a new worker pool
WorkerPoolState = redacted diagnostic snapshot of an active worker pool
WorkerLifecycleLocator = private control address and stop deadlines of the active supervisor
```

The canonical package-owned lifecycle paths are:

```text
LOCK         = var/tmp/worker.lock
LOCATOR      = var/tmp/worker.lifecycle.json
LOCATOR_TEMP = var/tmp/worker.lifecycle.json.tmp
```

The lock path and locator paths are immutable package-owned artifacts. They are not part of mutable worker configuration.

`worker:start` uses current worker configuration to construct a new `WorkerPoolSpec`.

`worker:status`, `worker:health`, and `worker:stop` do not construct `WorkerPoolSpec`. Their discovery flow is:

```text
probe canonical lifecycle lock
-> read private lifecycle locator
-> connect to the active endpoint from the locator
-> communicate with the active supervisor
```

The locator is not liveness authority without the lifecycle lock.

The canonical classifications are:

```text
lifecycle lock free
  -> worker is not running
  -> stale state or locator files do not change that result

lifecycle lock held + valid locator
  -> connect to the active supervisor

lifecycle lock held + missing, unreadable, malformed, oversized, symlinked,
or schema-invalid locator
  -> deterministic communication failure

lifecycle lock held + unavailable endpoint
  -> deterministic communication failure
```

The locator is a private versioned filesystem capability record. It contains the active supervisor-instance control credential, is not a control protocol request or response, and must not enter `worker.state.json`.

It contains only:

```text
version
control_credential
control_transport
socket_path
tcp_host
tcp_port
stop_timeout_ms
force_kill_timeout_ms
```

The inactive transport fields must be `null`.

The locator is published only after:

```text
control listener is bound
AND signal handling is installed
AND starting state is published
```

It is published before child spawn, so the supervisor can serve early status, health, or stop requests.

The active locator, rather than current config, owns the stop-client request deadline:

```text
stop_timeout_ms + (2 * force_kill_timeout_ms) + WorkerShutdownBudget::CLEANUP_TIMEOUT_MS
```

Changing current worker endpoint or timeout configuration does not redirect lifecycle commands and does not shorten the shutdown deadline of an active pool.

A process crash may leave a stale locator after the operating system releases the lifecycle lock. A free lock remains authoritative and classifies the pool as not running. The next successful start deletes the stale locator after acquiring the canonical lock and before publishing a fresh locator.

Raw locator fields, the control credential, and raw locator JSON must not enter logs, spans, metrics, CLI output, state snapshots, response frames, endpoint hashes, or public exception messages.

On POSIX, the locator temporary file is created under `umask(0177)` and verified as mode `0600` before credential bytes are written. The locator read boundary rejects effective permission bits other than `0600`.

Unix control sockets are created under `umask(0177)` and verified as mode `0600` before publication. TCP remains exactly loopback-only at `127.0.0.1`; no non-loopback opt-in is defined. Windows deployment owns restrictive directory ACLs.

The supervisor deletes the private locator before releasing the lifecycle lock.

## Readiness protocol decision

A child is not part of the ready pool until it has completed runtime boot and emitted one exact internal readiness frame.

Readiness occurs only after:

- artifact generation selection and loading where applicable;
- runtime-entrypoint validation;
- successful resolution of `ApplicationWorker` and required services;
- child bootstrap completion.

Readiness occurs before `ApplicationWorker::run()` enters the long-running task loop.

PCNTL and proc children use a dedicated per-child loopback TCP readiness endpoint.

The readiness frame is:

```text
ready:<64-lowercase-hex-token>\n
```

The token is independently generated for every process-child endpoint.

Readiness rules are:

- exactly one frame;
- exact token and framing;
- bounded input;
- no task payload;
- no stdout/stderr transport;
- EOF before the expected frame is startup failure;
- different or additional content is readiness protocol failure;
- timeout before the exact frame is readiness timeout.

The supervisor publishes `running` only after every configured child is ready.

A child exit before readiness is a startup failure and causes complete startup rollback.

## State schema decision

`worker.state.json` is an optional deterministic diagnostic snapshot.

It is not liveness authority.

The canonical schema version is:

```text
1
```

The exact safe shape is:

```json
{
  "version": 1,
  "pid": 12345,
  "status": "running",
  "worker_count": 4,
  "ready_worker_count": 4,
  "driver_requested": "auto",
  "driver": "pcntl",
  "control_transport_requested": "auto",
  "control_transport": "unix",
  "endpoint_hash": "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"
}
```

`pid` is the persistent supervisor PID.

The persistent status vocabulary is exactly:

```text
starting
running
stopping
```

`stopped` is not persisted.

It is a terminal control result only.

State writes use deterministic JSON and atomic temp-file plus rename publication.

Unknown keys, missing keys, wrong types, unsupported versions, and invalid value domains are rejected.

After complete shutdown, the state file is deleted.

State must not contain:

- timestamps;
- raw Unix socket paths;
- raw TCP hosts or ports;
- absolute paths;
- child PIDs;
- task payloads;
- headers;
- tokens;
- environment values;
- config dumps;
- exception messages;
- stack traces.

Only SHA-256 `endpoint_hash` may identify the selected control endpoint in state and safe output.

## Status and health decision

`worker:status`, `worker:health`, and `worker:stop` first probe the canonical lifecycle lock and then read the private active lifecycle locator.

They connect to the endpoint from the locator and do not resolve `WorkerPoolSpec` from current worker configuration.

`worker:status` requests the current in-memory supervisor state.

It must not infer running state from `worker.state.json`.

`worker:health` is healthy only when:

```text
status == running
AND ready_worker_count == worker_count
AND no terminal child failure is pending
```

The canonical unhealthy categories are bounded values for:

- starting;
- stopping;
- incomplete readiness;
- terminal child failure.

The persisted state may mirror current supervisor state.

The live supervisor response remains authoritative for status and health.

## Graceful and forced shutdown decision

Shutdown may be initiated by:

- a live `stop` control request;
- `SIGTERM`;
- `SIGINT`;
- a platform-native console control event supported by `WorkerSignalController`.

Signal handlers record shutdown intent only.

They must not perform:

- child termination;
- state writes;
- control responses;
- runtime cleanup;
- service resolution.

The synchronous supervisor loop owns shutdown execution.

The canonical phases are:

```text
transition state to stopping
-> publish stopping state
-> write cooperative stop signal
-> wait up to stop_timeout_ms
-> terminate remaining children
-> wait up to force_kill_timeout_ms
-> kill remaining children
-> wait up to force_kill_timeout_ms
-> reap every child
-> close every child resource
-> shut down driver-owned infrastructure
-> delete state
-> clear stop signal
-> close control listener
-> remove Unix socket when applicable
-> delete private lifecycle locator
-> release lifecycle lock
-> send terminal stopped response
```

Each cooperative, terminate, and kill phase creates one monotonic deadline before its first process-driver operation. Every potentially blocking `pollExit`, `terminate`, `kill`, and `close` call receives only the remaining phase budget. Iterating over multiple children must not restart or extend the phase deadline.

Driver infrastructure shutdown receives the remaining cleanup budget defined by `WorkerShutdownBudget::CLEANUP_TIMEOUT_MS`.

`ApplicationWorker` observes the cooperative stop signal only between tasks.

An in-flight UnitOfWork is allowed to complete.

The stop signal is not:

- the primary control mechanism;
- liveness authority;
- terminal shutdown acknowledgement.

If children remain after the final forced-shutdown deadline, shutdown fails deterministically.

No child resource or zombie process may remain after successful shutdown.

## Max-request recycle decision

`ApplicationWorker` processes tasks sequentially inside one child process.

When `worker.max_requests` is reached, the application worker exits normally with exit code `0`.

A ready-child exit is classified as expected only when:

```text
shutdown is not pending
AND child was ready
AND child was not signal-terminated
AND exit code == 0
```

For an expected exit, the supervisor performs:

```text
poll and reap old child
-> close old child resources
-> remove slot entry
-> publish reduced ready count
-> spawn the same worker index
-> increment child generation
-> wait for replacement readiness
-> restore ready count
-> keep pool running
```

Recycle preserves the deterministic worker slot index.

It assigns:

- a new process PID;
- a monotonically increasing generation within that slot.

The process driver does not decide recycle policy.

The child does not replace itself.

The external service manager is not involved in normal max-request recycle.

For PCNTL and proc children, every replacement performs a fresh artifact-generation selection and runtime boot.

## Unexpected child exit policy

A child exit is unexpected when any of the following applies:

- the child exits before readiness;
- the child exits with a non-zero code while shutdown is not pending;
- the child is signal-terminated while shutdown is not pending;
- the process adapter reports an invalid or unreapable state;
- replacement readiness fails during recycle.

An unexpected child exit during startup causes:

```text
startup failure
-> stop all spawned children
-> reap and close all child resources
-> shut down driver infrastructure
-> clean runtime artifacts
-> release lifecycle lock
-> return deterministic start failure
```

An unexpected child exit after the pool is running causes:

```text
transition to stopping
-> stop and reap the complete pool
-> perform deterministic cleanup
-> return non-zero supervisor exit code
```

The baseline policy is fail-fast for the complete pool.

The supervisor does not silently continue with reduced capacity.

Automatic restart of the failed foreground supervisor belongs to an external service manager.

## Application worker decision

`ApplicationWorker` owns the child-process task loop.

It processes tasks sequentially without restarting PHP between tasks.

Each task executes through:

```text
KernelRuntimeInterface::runUnitOfWork(...)
```

Each task is a separate UnitOfWork.

`ApplicationWorker` owns:

- task creation through the selected package-internal factory;
- sequential task execution;
- KernelRuntime delegation;
- max-request counting;
- cooperative stop observation between tasks;
- bounded task observability.

It must not:

- create its own UnitOfWork id;
- create its own correlation id;
- write context values directly;
- invoke Kernel hooks directly;
- enumerate reset tags;
- call `ResetOrchestrator` directly;
- implement queue transport behavior;
- implement HTTP adapter behavior;
- own supervisor state;
- own child replacement;
- write stdout or stderr directly.

The resolved worker task type is passed to KernelRuntime as the UnitOfWork type.

The operation id used for task observability comes from package-internal task work.

It must not come from untrusted payloads.

## Task factory decision

`TaskFactoryInternalInterface` is package-internal.

It is not a public task-source extension point.

It must not:

- be moved to `core/contracts`;
- be exported through package metadata as public API;
- be documented as a stable third-party plugin boundary.

`WorkerServiceFactory::taskFactory(...)` receives:

```text
WorkerPoolSpec
ContainerInterface
```

It must not receive closure factories for concrete task factories.

It:

1. selects the canonical service id from `WorkerPoolSpec`;
2. resolves only that selected service;
3. validates `TaskFactoryInternalInterface`;
4. validates support for the supplied spec;
5. maps failures to safe deterministic Worker start failures.

The canonical mapping is:

```text
queue -> QueueTaskFactory
http  -> HttpTaskFactory
```

The unselected factory must not be resolved as a side effect.

Task work contains:

```text
operation_id
run
```

The canonical operation ids are:

```text
queue
http
```

The `run` callback executes inside the KernelRuntime UnitOfWork boundary.

It is runtime behavior and must never enter:

- provider definition sets;
- descriptor streams;
- compiled graphs;
- generated artifacts;
- fingerprint input.

`QueueTaskFactory` does not implement a production external queue adapter.

External queue sources, acknowledgement, retry, and dead-letter behavior belong to integration packages.

`HttpTaskFactory` does not implement a production HTTP request source.

It must not depend on `platform/http`.

It may validate that:

```text
Psr\Http\Server\RequestHandlerInterface
```

is resolvable after Worker runtime-entrypoint compatibility has passed.

## Error decision

Worker package failures use deterministic package exceptions.

The package introduces or uses:

```text
WorkerException
WorkerStartFailedException
WorkerLifecycleFailedException
WorkerForkFailedException
WorkerAlreadyRunningException
WorkerCommunicationFailedException
WorkerNotRunningException
```

Duplicate-start ownership failures use:

```text
CORETSIA_WORKER_ALREADY_RUNNING: worker-already-running
```

They are not collapsed into the generic Worker start-failure boundary.

Startup-only failures use `WorkerStartFailedException` and `CORETSIA_WORKER_START_FAILED`.

Supervisor-lifecycle failures that are not owned exclusively by startup use `WorkerLifecycleFailedException` and `CORETSIA_WORKER_LIFECYCLE_FAILED`.

The lifecycle taxonomy owns invalid lifecycle state, unexpected child exit, shutdown, runtime cleanup, lifecycle-lock, lifecycle-locator, and proc-host failures. CLI commands preserve concrete `WorkerException` code and reason values. Unknown non-Worker throwables remain command-specific safe catch-all failures.

Public exception messages use:

```text
<ERROR_CODE>: <reason>
```

Worker exceptions expose stable:

```text
errorCode()
reason()
```

They must not expose previous throwable messages in public output.

Unknown internal failures are mapped to safe deterministic Worker failures.

Kernel runtime-driver matrix failures remain Kernel failures and must not be reclassified as unrelated Worker errors.

Public errors must not expose:

- service ids;
- raw class names used for internal resolution;
- filesystem paths;
- raw endpoints;
- command lines;
- environment values;
- config values;
- readiness tokens;
- task payloads;
- nested throwable messages.

## Observability decision

Worker observability complies with the canonical observability SSoT.

The worker runtime uses the spans:

```text
worker.process
worker.task
```

It uses the metrics:

```text
worker.process_total
worker.task_total
worker.task_duration_ms
```

Worker process metrics use only:

```text
status
```

The currently emitted bounded process status values are:

```text
start_success
start_failure
stop_success
stop_failure
status_success
status_failure
```

The reserved recycle status values are:

```text
recycle_success
recycle_failure
```

Reserved recycle values must not be emitted until the corresponding supervisor metric path is implemented and covered by deterministic tests.

Values must not be dynamically expanded with:

- worker index;
- generation;
- PID;
- signal;
- exit code;
- error reason.

Worker task metrics may use only:

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
worker_index
child_generation
```

Logs and spans are summary-only.

The private lifecycle locator is not an observability source.

The following locator data is forbidden in logs, spans, metrics, and metric labels:

```text
socket_path
tcp_host
tcp_port
raw locator JSON
```

Only the lowercase hexadecimal SHA-256 `endpoint_hash` may identify the selected endpoint in the defined safe log and state contexts.

`endpoint_hash` must not be emitted as a metric label.

Observability failures must not alter:

- worker lifecycle semantics;
- task execution;
- KernelRuntime delegation;
- reset semantics;
- task outcome;
- primary failure precedence.

ApplicationWorker timing failures collapse duration metadata to `0`.

Worker runtime classes must not instantiate observability adapters directly.

Logger, tracer, meter, stopwatch, and context dependencies are injected.

## Security and redaction decision

The worker runtime must not leak:

- raw socket paths;
- raw TCP hosts or ports;
- raw lifecycle locator JSON;
- absolute paths;
- task payloads;
- HTTP payloads;
- queue payloads;
- headers;
- cookies;
- Authorization values;
- readiness tokens;
- environment values;
- config dumps;
- raw command lines;
- stack traces;
- previous throwable messages.

Allowed public summaries may contain only safe fields such as:

```text
status
pid
worker_count
ready_worker_count
driver
control_transport
endpoint_hash
operation
outcome
```

The public `pid` is the persistent supervisor PID.

Child PIDs are not public state fields.

Raw endpoint identifiers must not be public output.

The private locator and its raw fields must not be emitted through CLI output, state snapshots, logs, spans, metrics, or exception messages.

Endpoint identity may be represented publicly only as a deterministic hash.

## External service-manager responsibility

The worker package runs one foreground process.

Deployment infrastructure is responsible for:

- launching `worker:start`;
- keeping it in the foreground;
- restart policy;
- startup ordering relative to external services;
- environment injection outside Worker config semantics;
- process-group, job-object, or cgroup ownership;
- deployment health checks;
- log collection;
- rolling replacement;
- container termination deadlines.

The worker package does not:

- daemonize itself;
- detach itself;
- respawn its own foreground supervisor;
- implement deployment restart loops;
- replace systemd, OpenRC, Supervisor, Kubernetes, Docker, or Windows service management.

The external service manager must not bypass the Worker control and shutdown contracts by treating the diagnostic state file as liveness authority.

## Consequences

### Positive

Coretsia gains one deterministic owner for the complete worker-pool lifecycle.

The foreground supervisor owns:

- liveness;
- control communication;
- readiness;
- child state;
- recycle;
- shutdown;
- cleanup.

Process drivers are reduced to explicit one-child OS adapters.

The same supervisor semantics operate across PCNTL and proc execution.

The proc process-host boundary prevents children from inheriting supervisor-owned resources.

The lifecycle lock provides one cross-process liveness authority.

The state file remains useful for safe diagnostics without becoming a process registry.

Status, health, and stop are live control operations.

Shutdown acknowledgement means cleanup is complete.

Max-request recycle preserves worker-slot identity without delegating replacement to the child or process driver.

Proc replacement children can boot a newer atomic artifact generation.

Worker tasks reuse the canonical Kernel UnitOfWork lifecycle.

Reset discipline remains Kernel/Foundation-owned.

Runtime-driver compatibility remains centralized in `core/kernel`.

Worker commands remain independent from `platform/cli`.

Worker runtime wiring has one declarative closure-free source.

Runtime-only paths remain isolated behind `RuntimePathContext`.

Worker state, control, errors, and observability have explicit redaction boundaries.

### Trade-offs

The worker runtime contains more explicit infrastructure than a short-lived command model.

Foreground operation requires an external service manager for production restart policy.

The proc path requires a dedicated process host because PHP process resources are not safely transferable and pipe behavior differs across platforms.

Readiness requires explicit internal channels instead of assuming process creation means application readiness.

A lifecycle lock introduces one persistent anchor file that must not be deleted during ordinary cleanup.

Fail-fast unexpected-child policy favors deterministic capacity over automatic degraded operation.

Safe public diagnostics expose less ad hoc debugging information than raw process, endpoint, path, or payload output.

`QueueTaskFactory` and `HttpTaskFactory` remain placeholder task sources rather than production transport integrations.

The process-driver and task-factory interfaces remain package-internal and are not stable third-party extension points.

## Rejected alternatives

### Run the control server inside `worker:stop`

Rejected.

The process issuing `worker:stop` does not own the running children.

A control server must already exist inside the persistent process that owns the pool.

Creating the server in the stop command reverses control ownership and cannot coordinate deterministic shutdown.

### Treat control connection as best effort

Rejected.

A held lifecycle lock means a supervisor owns or is establishing the pool.

If the live endpoint is unavailable while the lock is held, the result is a communication failure.

Silently treating it as not running would hide a broken lifecycle owner.

### Use the stop flag as primary control

Rejected.

A filesystem flag cannot provide:

- live status;
- health;
- terminal stop acknowledgement;
- exact shutdown completion;
- communication failure classification.

The stop signal remains a cooperative child hint written only by the supervisor.

### Launch a detached shell process

Rejected.

Shell backgrounding and platform-specific detached commands introduce:

- quoting risk;
- unstable PID ownership;
- inherited descriptors;
- unbounded shell behavior;
- inconsistent Windows and Unix semantics.

The canonical model is one foreground supervisor owned by external deployment infrastructure.

### Use the state file as liveness authority

Rejected.

A state file may remain after a crash or disappear while a process still owns resources.

It cannot provide exclusive ownership.

The lifecycle lock is the sole liveness authority.

### Use a static process registry

Rejected.

A static registry exists only inside one PHP process.

It cannot coordinate independent CLI invocations and cannot represent cross-process lifecycle ownership.

The supervisor child table is instance-owned and valid only inside the persistent supervisor.

### Let tests create the production control server

Rejected.

A production design must own its server in production code.

Tests may invoke the real server through a harness, but must not supply the missing lifecycle owner.

### Let process drivers own the complete pool lifecycle

Rejected.

That would create separate PCNTL and proc supervisor implementations.

Lifecycle policy would drift across drivers.

Drivers are restricted to one-child OS operations.

### Let children replace themselves

Rejected.

A terminating child cannot safely preserve authoritative slot generation, control state, readiness aggregation, and pool-wide failure policy.

Replacement belongs to the supervisor.

### Continue with reduced capacity after unexpected child failure

Rejected for the baseline policy.

Silent degraded operation complicates health, capacity guarantees, and failure precedence.

The baseline supervisor stops the complete pool and returns a non-zero result.

Future bounded restart strategies require a separate decision.

### Put Worker process ports in `core/contracts`

Rejected.

Supervisor, process-driver, control-client, and task-factory seams are package-local implementation boundaries.

They are not technology-neutral framework contracts.

### Let `ApplicationWorker` invoke hooks and reset directly

Rejected.

Hook discovery, context lifecycle, and reset orchestration are Kernel/Foundation-owned.

Every task enters the canonical UnitOfWork boundary through `KernelRuntimeInterface`.

### Depend on `platform/cli`

Rejected.

Worker command services use contracts-level CLI ports.

Catalog construction and binary dispatch remain owned by `platform/cli`.

### Depend on `platform/http`

Rejected.

HTTP task preflight requires a request-handler binding, not a compile-time dependency on one concrete HTTP platform package.

### Send task payloads over the control channel

Rejected.

The control protocol is lifecycle-only.

Task payload transport belongs to queue, HTTP, scheduler, or integration adapters.

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
- a public Worker process-driver plugin API;
- service-manager configuration;
- deployment restart policy;
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
- production observability exporter configuration;
- automatic degraded-capacity child restart policy;
- rolling in-process pool replacement.

## Verification evidence

Expected verification includes:

```text
framework/packages/platform/worker/tests/Unit/WorkerPoolSpecTest.php
framework/packages/platform/worker/tests/Unit/WorkerLifecycleLocatorTest.php
framework/packages/platform/worker/tests/Unit/WorkerPoolStateTest.php
framework/packages/platform/worker/tests/Unit/WorkerChildTableTest.php
framework/packages/platform/worker/tests/Unit/WorkerSupervisorLifecycleTest.php
framework/packages/platform/worker/tests/Unit/ContainerWorkerProcessDriverResolverTest.php
framework/packages/platform/worker/tests/Unit/WorkerChildCommandBuilderTest.php
framework/packages/platform/worker/tests/Unit/ApplicationWorkerMaxRequestsTest.php

framework/packages/platform/worker/tests/Contract/ApplicationWorkerStopwatchFailurePolicyContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerConfigSubtreeShapeContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerLifecycleLocatorOwnershipContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerNotRunningLifecycleContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerRuntimeDoesNotWriteToStdoutTest.php
framework/packages/platform/worker/tests/Contract/WorkerExceptionsAreDeterministicContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerInternalInterfacesAreNotPublicApiContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerCommandsUseCliContractsOnlyTest.php
framework/packages/platform/worker/tests/Contract/WorkerStateJsonSchemaContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerStartCommandContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerHealthCommandContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerProviderDefinitionsContainNoClosuresContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerControlProtocolSafetyContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerControlProtocolSchemaContractTest.php
framework/packages/platform/worker/tests/Contract/ProcWorkerProcessDriverSafetyContractTest.php
framework/packages/platform/worker/tests/Contract/PcntlWorkerContainerIsolationContractTest.php
framework/packages/platform/worker/tests/Contract/WorkerLocalFileOpenModeContractTest.php

framework/packages/platform/worker/tests/Unit/ApplicationWorkerTest.php
framework/packages/platform/worker/tests/Integration/WorkerHandlesMultipleTasksSequentiallyTest.php
framework/packages/platform/worker/tests/Integration/WorkerHttpTaskRequiresRequestHandlerTest.php
framework/packages/platform/worker/tests/Integration/WorkerStateStoreFilesystemTest.php
framework/packages/platform/worker/tests/Integration/WorkerControlTransportTest.php
framework/packages/platform/worker/tests/Integration/ProcWorkerProcessDriverTest.php
framework/packages/platform/worker/tests/Integration/PcntlWorkerProcessDriverTest.php
framework/packages/platform/worker/tests/Integration/PcntlWorkerExecIsolationTest.php
framework/packages/platform/worker/tests/Integration/PcntlWorkerArtifactBootTest.php
framework/packages/platform/worker/tests/Integration/PcntlWorkerOwnedDescriptorIsolationTest.php
framework/packages/platform/worker/tests/Integration/CoretsiaWorkerChildReadinessTest.php
framework/packages/platform/worker/tests/Integration/WorkerStartCommandResolvesSupervisorLazilyTest.php
framework/packages/platform/worker/tests/Integration/WorkerTaskFactorySelectsServiceLazilyTest.php
framework/packages/platform/worker/tests/Integration/WorkerProviderSourceDefinitionsParityTest.php
framework/packages/platform/worker/tests/Integration/WorkerLifecycleLockFilesystemTest.php
framework/packages/platform/worker/tests/Integration/WorkerLifecycleLockCloseOnExecTest.php
framework/packages/platform/worker/tests/Integration/WorkerLifecycleLocatorStoreFilesystemTest.php
framework/packages/platform/worker/tests/Integration/WorkerLifecycleConfigDriftTest.php
framework/packages/platform/worker/tests/Integration/WorkerSupervisorProductionFlowTest.php
framework/packages/platform/worker/tests/Integration/WorkerSupervisorReadinessTest.php
framework/packages/platform/worker/tests/Integration/WorkerSupervisorRecycleTest.php
framework/packages/platform/worker/tests/Integration/WorkerSupervisorChildFailureTest.php
framework/packages/platform/worker/tests/Integration/WorkerSupervisorSignalShutdownTest.php
framework/packages/platform/worker/tests/Integration/WorkerRuntimeCleanupTest.php

framework/packages/core/kernel/tests/Unit/RuntimePathContextValidationTest.php
framework/packages/core/kernel/tests/Contract/ArtifactLocalFileOpenModeContractTest.php
```

These tests are expected to verify:

- worker config root shape is a subtree;
- invalid scalar, path, timeout, driver, and transport values are rejected;
- process-driver and control-transport auto-resolution is deterministic;
- Kernel runtime-driver selection is independent from Worker OS process-driver selection;
- TCP port `0` is rejected;
- the lifecycle lock is sole liveness authority;
- the lifecycle lock path is canonical and cannot drift with current config;
- Coretsia-owned local lock and artifact files request close-on-exec on POSIX;
- the private locator has an exact versioned schema;
- locator publication is atomic, bounded, private, and mode `0600` on Unix;
- locator ownership is limited to supervisor writes/deletes and control-client reads;
- lifecycle commands do not resolve `WorkerPoolSpec`;
- endpoint, transport, invalid-config, and timeout drift do not redirect lifecycle commands;
- a stale locator with a free lock still means not running;
- the next successful start replaces stale locator data;
- duplicate start fails deterministically;
- stale state with a free lock does not mean running;
- a held lock with unavailable control endpoint is a communication failure;
- state schema version and exact keys are deterministic;
- state exposes endpoint hashes rather than raw endpoints;
- state is deleted only after complete shutdown;
- process drivers do not execute task logic;
- process drivers do not call KernelRuntime;
- process drivers do not own state, control, recycle, or shutdown policy;
- process children do not retain Worker-owned supervisor resources;
- current readiness, sibling readiness, and control listeners do not cross PCNTL exec;
- PCNTL children replace the forked process image before runtime boot;
- PCNTL and proc children perform artifact-only runtime boot;
- only the selected process driver is resolved;
- every recycled process child performs fresh artifact-generation selection;
- PCNTL and proc readiness frames are tokenized, exact, and bounded;
- startup remains `starting` until every child is ready;
- one unready child rolls back the complete startup;
- a child exit before readiness never publishes `running`;
- `WorkerPoolSpec` is constructed before runtime-entrypoint validation;
- supervisor resolution occurs only after runtime-entrypoint validation;
- resolving `WorkerStartCommand` does not resolve the supervisor;
- Worker provider definitions contain no closures;
- Worker source registration applies the contribution produced by `define()`;
- the Worker graph contains only canonical lifecycle services;
- the selected task factory alone is resolved;
- every internal container lookup has a matching required-service declaration;
- `RuntimePathContext` values never enter definitions, artifacts, or fingerprint input;
- the Worker runtime graph does not depend on `BootstrapConfig`;
- Worker callers do not call the Kernel-internal driver guard;
- Worker command classes use contracts-level CLI ports only;
- control protocol operations are exactly `status`, `health`, and `stop`;
- control protocol frames are payload-free;
- delayed TCP request frames are handled cross-platform;
- terminal stop is returned only after cleanup and lifecycle-lock release;
- status and health use the live supervisor;
- signal shutdown uses the same deterministic cleanup path as control stop;
- expected child exit recycles the same slot with a new generation;
- unexpected child exit stops the complete pool;
- all spawned children exit during successful cleanup;
- ApplicationWorker executes tasks through `KernelRuntimeInterface`;
- ApplicationWorker observes cooperative stop only between tasks;
- max requests causes deterministic normal child exit;
- worker observability uses registered names and bounded labels only;
- worker code does not write directly to stdout or stderr;
- public diagnostics do not expose paths, endpoints, tokens, payloads, or nested throwable messages.

## Related SSoT

- `docs/ssot/artifact-generations.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/process-exec-descriptor-safety.md`
- `docs/ssot/runtime-container-definitions.md`
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
- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md`
- `docs/adr/ADR-0031-atomic-artifact-generations.md`
- `docs/adr/ADR-0032-process-exec-descriptor-safety.md`
