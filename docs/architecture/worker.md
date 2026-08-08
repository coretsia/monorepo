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

## Guardian-owned worker generation

The mandatory `WorkerProcessGuardian` is the process-generation failure boundary below the foreground supervisor. It owns `worker.lock`, PCNTL child fork/wait/signal/reap operations, proc-host lifetime, and supervisor-death cleanup. The foreground `WorkerSupervisor` remains the owner of control, state, locator, readiness, recycle policy, and graceful shutdown orchestration.

If only the supervisor dies, the guardian retains the fence until the old worker generation is gone. A racing replacement therefore cannot overlap the old generation. External deployment remains responsible for restart policy and for containing guardian or whole-unit failure.

This document describes the current pre-stable Worker process topology and ownership boundaries.

The canonical generation fence has these semantics:

```text
worker.lock held
-> one Coretsia worker generation is active or recovering

worker.lock free
-> no Coretsia-owned active or recovering worker generation exists
```

The guardian MUST NOT release the generation fence until every worker it owns has exited and been reaped or closed. For the proc backend, the nested `WorkerProcProcessHost` must also have no owned worker resources before fence release.

The guardian does not own supervisor state, lifecycle locator, control socket or listener, or cooperative stop-signal artifacts. Those artifacts may remain stale after abrupt supervisor death. A free generation fence remains authoritative for `NOT_RUNNING`, and the next successful start replaces stale supervisor-owned artifacts.

No public `RECOVERING` state is introduced. While the generation fence is held and the supervisor endpoint is unavailable:

```text
status / health / stop
-> CORETSIA_WORKER_COMMUNICATION_FAILED

new worker:start
-> CORETSIA_WORKER_ALREADY_RUNNING
```

Restart policy remains external. Coretsia does not automatically respawn the foreground supervisor.

If the guardian itself dies, or the complete externally managed service unit is terminated, process-group, cgroup, container, or job-object containment remains the responsibility of deployment infrastructure. For the proc backend, `WorkerProcProcessHost` additionally performs fallback cleanup when its authenticated guardian connection disappears.

Process containment does not imply application transaction recovery. The Worker runtime does not guarantee rollback, idempotency, delivery acknowledgement, or correctness of arbitrary application side effects after abrupt process termination.

## Purpose

This document is the architecture overview for the `platform/worker` long-running runtime package.

It explains:

- package ownership;
- process model;
- driver selection;
- supervisor, process-driver, and application-worker boundaries;
- declarative runtime container wiring;
- lazy supervisor and task-factory resolution;
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
docs/adr/ADR-0017-persistent-worker-supervisor-application-worker.md
```

ADR-0017 records that `platform/worker` owns long-running worker runtime orchestration while Kernel and Foundation retain UnitOfWork, hook, and reset semantics.

If this document conflicts with any of the following, the SSoT or ADR wins:

```text
docs/adr/ADR-0017-persistent-worker-supervisor-application-worker.md
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

Among Coretsia packages, `platform/worker` may depend only on:

```text
core/contracts
core/foundation
core/kernel
```

The package may additionally depend on PSR interfaces used strictly as ports.

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

WorkerStartCommand
WorkerStopCommand
WorkerStatusCommand
WorkerHealthCommand

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

WorkerProcessGuardianInterface
WorkerProcessGuardianProtocol
WorkerProcessGuardianTransport
WorkerProcessGuardianClient
WorkerProcessGuardianRuntime

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

WorkerLifecyclePaths
WorkerLifecycleLock
WorkerLifecycleLocator
WorkerLifecycleLocatorStore
WorkerShutdownBudget
WorkerStopSignal
WorkerPoolSpec
WorkerPoolState
WorkerHealthState
WorkerStateStore
WorkerRuntimeEntrypointGuard

ApplicationWorker
TaskFactoryInternalInterface
QueueTaskFactory
HttpTaskFactory
WorkerRuntimeDriverContributions
```

The canonical package-internal interfaces are:

```text
Coretsia\Platform\Worker\Internal\WorkerSupervisorInterface
Coretsia\Platform\Worker\Internal\WorkerSupervisorResolverInterface
Coretsia\Platform\Worker\Internal\WorkerProcessDriverInterface
Coretsia\Platform\Worker\Internal\WorkerProcessDriverResolverInterface
Coretsia\Platform\Worker\Internal\WorkerControlClientInterface
Coretsia\Platform\Worker\Internal\TaskFactoryInternalInterface
```

These interfaces are package-local architectural seams.

They are not public framework extension points and must not be moved to `core/contracts`.

`RuntimePathContext` is Kernel-owned runtime infrastructure:

```text
Coretsia\Kernel\Runtime\RuntimePathContext
```

It supplies runtime-only skeleton and artifact roots to path-owning Worker services.

It is not a Worker extension point, canonical definition value, artifact payload, or fingerprint input.

The following helper is also package-internal:

```text
Coretsia\Platform\Worker\Internal\WorkerRuntimeDriverContributions
```

It maps normalized Worker task type to Kernel-owned runtime-driver contributions.

It does not select the internal OS process driver.

## Declarative container wiring

`WorkerServiceProvider` implements:

```text
ServiceProviderInterface
ContainerDefinitionProviderInterface
```

Its `define()` method is the only canonical source of Worker runtime wiring.

Its `register()` method delegates through:

```php
$builder->registerDefinitionProvider($this);
```

It must not maintain a parallel imperative runtime graph.

The canonical Worker contribution defines:

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
WorkerProcessGuardianProtocol
WorkerProcessGuardianTransport
WorkerProcessGuardianClient
WorkerProcessGuardianInterface alias
WorkerChildCommandBuilder

QueueTaskFactory
HttpTaskFactory
TaskFactoryInternalInterface
ApplicationWorker

PcntlWorkerProcessDriver
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

`WorkerProcessDriverResolverInterface` maps the resolved process-driver id to exactly one concrete driver service.

The resolver does not enumerate tags, does not fall back across drivers, and does not construct the unselected driver.

The contribution declares these required runtime service ids:

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

The remaining required ids must be supplied by the complete runtime graph.

`WorkerProcessDriverResolverInterface` is required because `WorkerSupervisor` performs a deferred lookup of only the selected concrete process driver.

`WorkerSupervisorInterface` is required because `ContainerWorkerSupervisorResolver` performs a deferred runtime-container lookup of that interface.

`WorkerControlClientInterface` is the live control boundary used by the status, health, and stop commands.

`QueueTaskFactory` and `HttpTaskFactory` are required because task-factory selection resolves only the selected canonical service through `ContainerInterface`.

Resolving `WorkerStartCommand` must not resolve `WorkerSupervisorInterface`.

The required order is:

```text
build WorkerPoolSpec
-> execute WorkerRuntimeEntrypointGuard
-> resolve WorkerSupervisorInterface lazily
-> run WorkerSupervisor
```

The Worker contribution contains no closures.

The package-internal task-work callback may be created only after definition production and must not enter definitions, descriptor streams, generated artifacts, or fingerprint input.

The PCNTL driver receives no child-bootstrap callback and captures no runtime container.

## Process model

The worker runtime uses one persistent foreground supervisor process.

The externally managed process tree is:

```text
service manager / container runtime
└─ worker:start
   └─ WorkerSupervisor
      ├─ owns private lifecycle locator
      ├─ owns control listener
      ├─ owns child table
      ├─ owns readiness aggregation
      ├─ owns state publication
      ├─ owns signal intent
      ├─ owns cooperative stop-signal writes
      ├─ owns recycle policy
      ├─ owns graceful and forced shutdown orchestration
      ├─ owns supervisor-artifact cleanup
      └─ WorkerProcessGuardian
         ├─ owns canonical worker.lock generation fence
         ├─ owns supervisor-death worker-generation containment
         ├─ PCNTL
         │  └─ owns worker fork / exec / signal / wait / reap
         └─ PROC
            └─ WorkerProcProcessHost
               └─ owns raw proc_open worker resources
```

The complete startup lifecycle is:

```text
WorkerStartCommand
  -> WorkerServiceFactory::workerPoolSpec(...)
  -> WorkerPoolSpec
  -> WorkerRuntimeEntrypointGuard::assertEntrypointAllowed(...)
       -> WorkerRuntimeDriverContributions::fromSpec(...) [internal]
       -> Kernel RuntimeEntrypointGuard
  -> WorkerSupervisorResolverInterface::resolve()
       -> ContainerWorkerSupervisorResolver
       -> runtime container get(WorkerSupervisorInterface)
  -> WorkerSupervisorInterface::run(...)
       -> WorkerProcessDriverResolverInterface::resolve(WorkerPoolSpec)
       -> launch and authenticate WorkerProcessGuardian
       -> guardian claims canonical WorkerLifecycleLock
       -> delete stale private lifecycle locator
       -> delete stale state
       -> clear stale cooperative stop signal
       -> generate supervisor-instance control credential
       -> open authenticated WorkerControlServer
       -> install WorkerSignalController
       -> publish state=starting
       -> publish private WorkerLifecycleLocator
       -> spawn configured child slots
       -> wait for readiness from every child
       -> publish state=running
       -> emit one startup summary callback
       -> enter persistent event loop
```

The persistent event loop is:

```text
accept status / health / stop requests
-> poll child readiness
-> poll child exits
-> recycle expected ready-child exits
-> fail the complete pool on unexpected exits
-> process signal-driven shutdown intent
-> continue until shutdown is requested
```

The shutdown lifecycle is:

```text
transition state to stopping
-> publish stopping state
-> write cooperative stop signal
-> wait up to stop_timeout_ms
-> terminate remaining children
-> wait up to force_kill_timeout_ms
-> kill remaining children
-> wait up to force_kill_timeout_ms
-> reap and close every child
-> delete state
-> clear stop signal
-> close control listener
-> remove Unix socket when applicable
-> delete private lifecycle locator
-> guardian release
   -> shut down ProcHost when applicable
   -> verify no owned workers remain
   -> release generation fence last
-> send terminal stopped response
-> exit with deterministic process code
```

The cooperative, terminate, and kill phases each create one monotonic deadline before their first process-driver operation. Every potentially blocking `pollExit`, `terminate`, `kill`, and `close` call receives only the remaining phase budget. Iterating over multiple children never restarts or extends a phase deadline.

Terminal guardian release receives the remaining cleanup budget defined by `WorkerShutdownBudget::CLEANUP_TIMEOUT_MS`.

The supervisor launches and claims the mandatory guardian before publishing supervisor-owned runtime artifacts. For proc, the guardian starts the dedicated process host before accepting the supervisor ownership connection and before acquiring the generation fence, preventing proc children from inheriting those later resources.

`WorkerPoolSpec` is the normalized Worker-owned source of truth for:

```text
task type
OS process driver
control transport
configurable socket, state, and stop-signal paths
worker count
max requests
lifecycle deadlines
```

The internal OS process driver is not a Kernel HTTP/background runtime driver.

Each child runs one `ApplicationWorker`.

`ApplicationWorker` processes tasks sequentially until:

- `worker.max_requests` is reached;
- the cooperative stop signal is observed between tasks;
- task execution produces a terminal process failure.

The control channel is lifecycle-only and must not transport task payloads.

## Runtime artifact ownership

| Artifact                  | Path source                                    | Filesystem owner                                                              | Semantics                                                                           |
|---------------------------|------------------------------------------------|-------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|
| generation fence          | canonical `WorkerLifecyclePaths::LOCK`         | `WorkerProcessGuardian` via `WorkerLifecycleLock`                             | active/recovering-generation authority; persistent anchor file is not unlinked      |
| private lifecycle locator | canonical `WorkerLifecyclePaths::LOCATOR`      | supervisor writes/deletes through `WorkerLifecycleLocatorStore`; client reads | active endpoint, control credential, and active stop deadlines; never public output |
| locator temporary file    | canonical `WorkerLifecyclePaths::LOCATOR_TEMP` | `WorkerLifecycleLocatorStore`                                                 | fixed atomic-publication temporary file; deleted after publication or cleanup       |
| Unix control socket       | `worker.socket_path` for a new start           | `WorkerControlTransport`                                                      | active control endpoint when transport is `unix`; configurable only for a new pool  |
| diagnostic state          | `worker.state_path`                            | `WorkerStateStore`                                                            | redacted snapshot only; never liveness authority                                    |
| state temporary file      | `worker.state_path + ".tmp"`                   | `WorkerStateStore`                                                            | atomic state-publication temporary file                                             |
| cooperative stop signal   | `worker.stop_flag_path`                        | `WorkerStopSignal`                                                            | supervisor-written between-task shutdown hint; not primary control                  |

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
pcntl when the required PCNTL fork/exec and POSIX capabilities are available and the platform is not Windows
proc when the guardian and secure proc process-host capability are available
deterministic lifecycle-validation failure when neither adapter is available
```

The `pcntl` driver is Unix-like and fork-based.

The `proc` driver is the cross-platform process adapter.

It delegates child ownership to the guardian; the guardian delegates raw `proc_open()` resources to the dedicated proc process host.

`WorkerSupervisor` receives an already-built `WorkerPoolSpec` and delegates exact lazy driver resolution to `WorkerProcessDriverResolverInterface`.

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
unix when the platform is not Windows and unix domain sockets are supported
tcp otherwise
```

Control-transport selection is independent from the selected Worker OS process driver.

The `unix` transport uses a skeleton-root-relative socket path.

The `tcp` transport uses the canonical IPv4 loopback host `127.0.0.1` and the explicitly configured deterministic TCP port.

`worker.tcp.host` MUST be exactly `127.0.0.1`.

TCP port `0` is forbidden because it would make endpoint identity and persisted worker state non-deterministic across runs.

Raw socket paths, raw TCP hosts, and raw TCP ports must not appear in public diagnostics, logs, metrics, or public command output.

Endpoint identity may be represented publicly only through a deterministic hash.

## Control-channel authentication

Every `status`, `health`, and `stop` request authenticates with one 256-bit supervisor-instance control credential.

The credential is generated after stale lifecycle cleanup and before listener publication. It remains stable during child spawn and recycle, and rotates only when a new supervisor instance starts.

The exact version-`1` request shape is:

```json
{
  "credential": "<64 lowercase hexadecimal characters>",
  "operation": "status",
  "request_id": "request-123-1",
  "version": 1
}
```

The server decodes the exact request schema and validates the credential through `hash_equals()` before creating a `WorkerControlSession`. Missing, malformed, or non-matching credentials close the connection silently and execute no operation. Responses never contain the credential.

Credential ownership is limited to supervisor memory, the active control server, the private lifecycle locator, and one private request frame. It must not enter state, endpoint hashes, logs, spans, metrics, CLI output, exceptions, child argv, or child environment.

TCP remains restricted to `127.0.0.1`; no non-loopback opt-in exists. A Unix listener is created under `umask(0177)`, verified as mode `0600`, and published only after that verification. On Windows, deployment owns restrictive ACLs for the skeleton and runtime directory.

The credential does not claim isolation from arbitrary processes running under the same compromised operating-system account.

The normative Worker control-protocol, credential, locator-confidentiality, and local threat-boundary decisions are recorded in ADR-0017.

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
→ call WorkerSupervisorResolverInterface::resolve()
→ run WorkerSupervisorInterface
```

Resolving the command service itself must not resolve `WorkerSupervisorInterface`, process drivers, `ApplicationWorker`, or the selected task factory.

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

Missing or invalid `worker.task_type` is a Worker-owned lifecycle-validation failure:

```text
CORETSIA_WORKER_LIFECYCLE_FAILED: worker-invalid-state
```

Runtime-driver conflicts and missing `platform.http` remain Kernel runtime-driver failures.

HTTP Worker mode must pass `WorkerRuntimeEntrypointGuard` compatibility before `RequestHandlerInterface` resolution.

## Lazy WorkerSupervisor resolution boundary

`WorkerStartCommand` receives:

```text
WorkerSupervisorResolverInterface
```

instead of a closure factory or an eagerly resolved supervisor.

The canonical implementation is:

```text
ContainerWorkerSupervisorResolver
```

It stores only `ContainerInterface`.

`resolve()`:

1. requests `WorkerSupervisorInterface::class` from the active runtime container;
2. validates the resolved type;
3. returns the valid supervisor;
4. maps container failures and invalid bindings to a safe deterministic Worker start failure.

Resolution occurs only after:

```text
WorkerPoolSpec construction
-> WorkerRuntimeEntrypointGuard validation
```

The resolver interface and implementation are package-internal.

They are not public Worker plugin APIs.

## Worker supervisor boundary

`WorkerSupervisor` is the foreground worker-pool orchestration owner.

It owns:

```text
private lifecycle locator publication and deletion
control server
child table
readiness aggregation
diagnostic state publication
signal intent
cooperative stop-signal writes
recycle policy
graceful shutdown orchestration
forced shutdown orchestration
supervisor-owned runtime-artifact cleanup
```

It does not own:

- the canonical worker-generation fence;
- raw PCNTL worker-process lifetime;
- raw proc worker-process lifetime;
- `WorkerProcProcessHost` lifetime;
- deployment restart policy;
- external process-group, cgroup, container, or job-object containment.

It must not:

- read or resolve the service container;
- construct `WorkerPoolSpec`;
- apply Kernel runtime-driver matrix policy;
- execute task bodies;
- call `KernelRuntimeInterface`;
- daemonize;
- invoke shell background commands;
- write stdout or stderr directly;
- expose raw paths or endpoints.

`WorkerProcessGuardian` owns worker-generation process containment and the canonical generation fence.

The supervisor uses process drivers only as strict one-child command/readiness adapters over the guardian boundary.

## Process-driver boundary

The canonical package-internal interface is:

```text
WorkerProcessDriverInterface
```

Its operations are:

```text
name
supports
spawn
pollExit
terminate
kill
close
```

Process drivers do not own pool preparation, generation ownership, generation fencing, or terminal generation release.

A process driver must not own:

- lifecycle state;
- `WorkerStateStore`;
- the control listener;
- control operation semantics;
- the cooperative stop signal;
- a pool-wide child registry;
- recycle policy;
- pool-wide observability.

`PcntlWorkerProcessDriver` owns tokenized readiness-listener creation, child command construction, and translation between guardian child identity and `WorkerChildProcess`.

It delegates fork, exec, signal, wait, reap, and raw PCNTL worker-process ownership to `WorkerProcessGuardianInterface`.

It receives no `ContainerInterface` and no `ApplicationWorker`.

`WorkerChildCommandBuilder` owns the exact shell-free argv shape shared by PCNTL and proc children.

`ProcWorkerProcessDriver` owns tokenized readiness-listener creation, child command construction, and translation between guardian child identity and `WorkerChildProcess`.

It delegates process ownership to `WorkerProcessGuardianInterface`.

For the proc backend, the guardian owns `WorkerProcProcessHost` lifetime and delegates raw `proc_open()` resource ownership to that dedicated process host.

Process command construction is argv-vector based and must not construct an untrusted shell string.

## Process-child artifact-only boot boundary

Both process drivers enter a fresh PHP runtime image before Worker runtime boot.

Both drivers delegate process ownership to the guardian. The guardian forks PCNTL workers and immediately execs the child image after dropping guardian-owned descriptors; for proc it uses the nested process host.

Neither child resolves `ApplicationWorker` from the supervisor container.

`WorkerChildCommandBuilder` passes each newly spawned child exactly one skeleton-root-relative Kernel artifact root.

The canonical child argv field is:

```text
--coretsia-worker-artifact-root=<relative-safe-path>
```

Each child receives a dedicated internal readiness endpoint and one independently generated 64-character lowercase hexadecimal readiness token.

The readiness arguments are internal process-bootstrap arguments and must not appear in public diagnostics.

The child argument parser uses an exact key allowlist.

It MUST reject individual artifact-path arguments:

```text
--coretsia-worker-module-manifest
--coretsia-worker-config
--coretsia-worker-container
```

The artifact-root argument MUST:

- be non-empty;
- be skeleton-root-relative;
- use `/` separators;
- contain no whitespace;
- contain no control bytes;
- contain no empty segments;
- contain no `.` or `..` segments;
- contain no stream-wrapper syntax;
- contain no absolute-path prefix;
- contain no `@`-prefixed segment.

The child process uses its working directory as the explicit normalized skeleton root.

It resolves the validated relative artifact root against that skeleton root and creates:

```php
new ArtifactRuntimeInput(
    skeletonRoot: $skeletonRoot,
    artifactRoot: $artifactRoot,
);
```

The child invokes:

```text
ArtifactRuntimeBooter
```

with that input only.

`ArtifactRuntimeBooter` then:

1. locates `current`;
2. validates one selected generation;
3. reads exact snapshots for all four generation files;
4. validates generation metadata and fingerprints;
5. hydrates `ConfigRepositoryInterface`, `ModulePlan`, and `RuntimePathContext`;
6. builds the compiled runtime container.

These three objects form the exact entrypoint-owned runtime seed set.

The process-child artifact-only boot path MUST NOT:

- accept individual artifact paths;
- run Bootstrap Phase A;
- run ConfigKernel Phase B;
- read source config files;
- read Composer module metadata;
- discover modules;
- resolve presets;
- execute source providers;
- compile a replacement graph;
- calculate fingerprints;
- write or repair artifacts;
- scan `generations/` for a newest generation;
- fall back to another generation.

After the compiled container is built, the child resolves:

```text
WorkerPoolSpec
WorkerRuntimeEntrypointGuard
ConfigRepositoryInterface
ModulePlan
ApplicationWorker
```

The child validates that the received worker arguments match `WorkerPoolSpec` before starting the application-worker loop.

All child boot failures MUST remain deterministic and redacted.

The launcher emits no stdout or stderr diagnostics.

A boot failure terminates the child with a non-zero process code.

The supervisor observes the child exit and maps startup failure to package-owned deterministic Worker exceptions.

Raw artifact roots, generation paths, config payloads, module-manifest payloads, container payloads, and nested throwable messages must not appear in public diagnostics.

The launcher MUST NOT forward the nested `ArtifactRuntimeBootException` reason or message.

Raw artifact roots, generation paths, config payloads, module-manifest payloads, container payloads, and nested throwable messages MUST NOT appear in public child diagnostics.

Every PCNTL and proc spawn performs a fresh artifact-only boot.

This includes replacement children created during max-request recycle.

A recycled process child:

```text
receives the artifact root
-> resolves current
-> validates the selected generation
-> hydrates the runtime from that generation
-> resolves ApplicationWorker
-> emits the exact readiness frame
-> enters the task loop
```

A replacement process child must not inherit the previous child’s selected generation, loaded artifact snapshots, runtime container, or readiness state.

## Process-exec descriptor boundary

The PCNTL child performs the following owner-driven sequence before process-image replacement:

```text
forked PCNTL child
  -> close current readiness listener
  -> detach sibling readiness listeners
  -> detach control listener
  -> detach lifecycle lock
  -> reset signal-controller state
  -> exec package-owned child launcher
```

At the PCNTL fork boundary `WorkerProcessGuardian` closes only the Worker-owned guardian descriptors it knows: the supervisor ownership stream and generation-fence handle.

```text
known Worker-owned descriptor isolation != arbitrary application or integration descriptor isolation
```

Coretsia-owned local files that can coexist with process execution request close-on-exec on POSIX. Integration-owned descriptors must follow `docs/ssot/process-exec-descriptor-safety.md`.

The proc process host starts before the guardian accepts the supervisor ownership connection, before lifecycle-lock acquisition, and before supervisor listeners are opened. This prevents inheritance of those later Worker-owned descriptors, but does not prove isolation from descriptors opened before process-host startup.

For every proc spawn, the guardian-owned process-host client creates a one-shot tokenized handoff endpoint. The process host closes its current authenticated connection before `proc_open()` and establishes the replacement connection only after child launch. No proc-host protocol connection is open during `proc_open()`, so the same descriptor-isolation invariant applies on Windows and POSIX without `ext-sockets` or `SOCK_CLOEXEC`.

## Application worker boundary

`ApplicationWorker` owns the child-process task loop.

It processes tasks sequentially without restarting PHP between tasks.

`WorkerServiceFactory::applicationWorker(...)` receives `WorkerStopSignal`, `KernelRuntimeInterface`, `TaskFactoryInternalInterface`, `Stopwatch`, `TracerPortInterface`, and `MeterPortInterface`.

`ApplicationWorker` does not depend on `RuntimePathContext`, `BootstrapConfig`, or a raw skeleton root.

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

Controls the maximum number of tasks handled by one child process before `ApplicationWorker` exits normally and the supervisor recycles that worker slot.

The value must be a positive integer.

### `worker.stop_flag_path`

Controls cooperative shutdown observation between tasks.

The supervisor writes the cooperative stop signal.

`ApplicationWorker` checks it only between tasks.

It must not interrupt an in-flight task.

### `worker.start_timeout_ms`

Controls the complete startup deadline, including guardian launch/claim, generation-fence acquisition, child spawn, runtime boot, and readiness aggregation.

The value must be a positive integer no greater than `86400000`.

### `worker.stop_timeout_ms`

Controls the cooperative shutdown phase owned by the supervisor as a strict wall-clock budget for process-driver operations in that phase.

The value must be a positive integer no greater than `86400000`.

### `worker.force_kill_timeout_ms`

Controls each post-cooperative shutdown phase as an independent strict wall-clock budget for process-driver operations:

```text
graceful terminate wait
hard-kill wait
```

The value must be a positive integer no greater than `86400000`.

### Path safety

Configured Worker-owned path values remain skeleton-root-relative configuration values.

These include:

```text
worker.socket_path
worker.state_path
worker.stop_flag_path
```

Lifecycle discovery artifacts are package-owned and non-configurable:

```text
var/tmp/worker.lock
var/tmp/worker.lifecycle.json
var/tmp/worker.lifecycle.json.tmp
```

The configurable socket, state, state temporary, and stop-signal paths must not overlap each other or any canonical lifecycle artifact.

The derived state temporary path is:

```text
worker.state_path + ".tmp"
```

Configured relative Worker paths must:

- be non-empty;
- use normalized `/` separators;
- reject NUL and control characters;
- reject URI schemes;
- reject parent traversal;
- reject absolute Unix paths;
- reject absolute Windows drive paths;
- reject the `skeleton/` prefix;
- reject every path segment beginning with `@`.

Phase-B config validation declares the last two Worker-specific restrictions through `forbiddenPrefixes` and `forbiddenSegmentPrefixes` on the generic `relative-safe-path` type. `WorkerPoolSpec` repeats the same checks as a runtime defense-in-depth boundary.

These relative config values are distinct from both:

- the process-child artifact-root argument;
- runtime roots carried by `RuntimePathContext`.

The process-child artifact root is one launcher-owned, skeleton-root-relative runtime input:

```text
--coretsia-worker-artifact-root=<relative-safe-path>
```

It is not a Worker config value and is not a generated artifact payload field.

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

`WorkerStateStore` owns deterministic diagnostic state snapshots.

The default state path is:

```text
var/tmp/worker.state.json
```

The canonical state schema version is:

```text
1
```

The exact persisted fields are:

```text
version
pid
status
worker_count
ready_worker_count
driver_requested
driver
control_transport_requested
control_transport
endpoint_hash
```

The persistent status vocabulary is:

```text
starting
running
stopping
```

`stopped` is a terminal control response only and is not persisted.

`pid` is the persistent supervisor PID.

The state file is diagnostic only.

It is not liveness authority.

The liveness rules are:

```text
lifecycle lock free
  -> no Coretsia-owned active or recovering worker generation exists
  -> not running

lifecycle lock held + valid private locator + reachable authenticated control endpoint
  -> live supervisor determines starting, running, or stopping state

lifecycle lock held + missing, unreadable, malformed, oversized, symlinked, or schema-invalid private locator
  -> communication failure

lifecycle lock held + unavailable or unauthenticated control endpoint
  -> communication failure
```

A stale state file with a free lifecycle lock does not mean the pool is running.

State writes use deterministic JSON and atomic temp-file plus rename publication.

After complete shutdown, the state file is deleted.

The state file must not contain:

- timestamps;
- raw socket paths;
- raw TCP hosts or ports;
- absolute paths;
- task payloads;
- headers;
- tokens;
- environment values;
- config dumps;
- throwable messages.

Only `endpoint_hash` may identify the control endpoint in safe state and output.

## Private lifecycle locator

Lifecycle discovery deliberately separates three concepts:

```text
WorkerPoolSpec = desired configuration for creating a new pool
WorkerPoolState = redacted diagnostic snapshot of an active pool
WorkerLifecycleLocator = private endpoint, control credential, and stop deadlines of the active supervisor
```

`WorkerLifecyclePaths` is the single source of truth for canonical lifecycle artifacts:

```text
LOCK         = var/tmp/worker.lock
LOCATOR      = var/tmp/worker.lifecycle.json
LOCATOR_TEMP = var/tmp/worker.lifecycle.json.tmp
```

The locator exact schema version is `1`.

Unix locator fields are:

```text
version = 1
control_credential = 64 lowercase hexadecimal characters
control_transport = unix
socket_path = non-empty relative-safe path
tcp_host = null
tcp_port = null
stop_timeout_ms = positive bounded integer
force_kill_timeout_ms = positive bounded integer
```

TCP locator fields are:

```text
version = 1
control_credential = 64 lowercase hexadecimal characters
control_transport = tcp
socket_path = null
tcp_host = 127.0.0.1
tcp_port = 1..65535
stop_timeout_ms = positive bounded integer
force_kill_timeout_ms = positive bounded integer
```

Unknown keys, missing keys, numeric strings, unsupported versions, absolute or unsafe Unix paths, and non-null inactive transport fields are rejected.

`WorkerLifecycleLocatorStore` is the exclusive filesystem owner. It:

- encodes through `StableJsonEncoder`;
- limits the complete locator to `4096` bytes;
- creates the fixed temporary path under `umask(0177)` on POSIX;
- verifies mode `0600` before writing credential bytes;
- publishes by atomic rename;
- rejects POSIX locator files whose effective permission bits are not exactly `0600`;
- rejects symlinks and non-regular files on read;
- maps client-side read failures to deterministic communication failure;
- deletes both final and temporary locator paths idempotently.

The locator is published only after the control listener, signal handling, and `starting` state are ready, but before child spawn. It is deleted before terminal guardian release; the guardian releases the canonical generation fence last.

The locator is not liveness authority without the lock. It is not a protocol frame, must not enter `worker.state.json`, and its raw endpoint fields or control credential must not be logged or rendered by CLI commands. Endpoint identity and endpoint hash deliberately exclude the credential.

The lifecycle-command sequence is:

```text
probe canonical lifecycle lock
-> if free, return not running
-> read private lifecycle locator
-> connect to the active endpoint from the locator
-> send an authenticated status, health, or stop request
-> validate the response endpoint hash against the locator
```

`worker:status`, `worker:health`, and `worker:stop` do not construct `WorkerPoolSpec`. They probe the canonical lock, read the active locator, and connect to the endpoint recorded by the active supervisor. `worker:stop` derives its complete request timeout from the active locator:

```text
stop_timeout_ms + (2 * force_kill_timeout_ms) + WorkerShutdownBudget::CLEANUP_TIMEOUT_MS
```

Current worker configuration therefore cannot redirect lifecycle commands away from an already-running pool or shorten the active pool's shutdown deadline.

### Crash recovery and stale locator semantics

A crashed supervisor may leave diagnostic state, a private locator, or a Unix socket. If its guardian survives, the generation fence remains held until old workers are contained and is then released by the guardian.

The canonical classifications remain:

```text
free lifecycle lock
  -> not running
  -> stale state and locator are ignored for liveness

held lifecycle lock + missing or invalid locator
  -> communication failure
```

A new start first constructs its own `WorkerPoolSpec` from current config. After its guardian acquires the canonical generation fence, the supervisor deletes the stale canonical locator before binding and publishing the new active endpoint.

Configurable state, stop-signal, and Unix-socket cleanup uses the paths from the new start spec. The canonical lock and locator paths never drift with current config.

## Control channel

The supervisor-owned control layer is split into:

```text
WorkerControlTransport
WorkerControlProtocol
WorkerControlServer
WorkerControlClient
WorkerControlClientInterface
```

`WorkerControlTransport` owns:

- address derivation;
- `listen`;
- `connect`;
- timeout-aware `accept`;
- bounded frame reads and writes;
- close;
- Unix socket cleanup.

`WorkerControlProtocol` owns exact versioned request and response schemas.

`WorkerControlServer` owns the live supervisor listener and typed sessions.

`WorkerControlClient` owns canonical lifecycle-lock probing, private locator resolution, endpoint-consistency validation, and live command request-response behavior.

The control protocol supports exactly:

```text
status
health
stop
```

It must not contain:

```text
start
```

Pool startup belongs only to the foreground `worker:start` command.

The protocol is:

- versioned;
- newline framed;
- deterministic JSON;
- bounded to one frame;
- exact-key;
- payload-free;
- strict about unknown operations and versions.

A stop request remains pending until:

- all children have exited;
- all child resources have been reaped or closed;
- state is deleted;
- the stop signal is cleared;
- the listener is closed;
- the Unix socket is removed when applicable;
- the private lifecycle locator is deleted;
- terminal guardian release has completed;
- the canonical generation fence is released.

Only then may the server return terminal:

```text
stopped
```

A disconnected status or health client must not terminate the supervisor.

Control failures map to deterministic `WorkerCommunicationFailedException`.

Public diagnostics must not expose raw endpoints, paths, payloads, headers, tokens, or throwable messages.

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
pool_status
pid
worker_count
ready_worker_count
healthy
reason
driver
control_transport
endpoint_hash
```

`pool_status`, `healthy`, and `reason` are health-summary fields.

`reason` must be a bounded package-owned token.

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
worker_index
child_generation
exit_code
signal
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
pool_status
pid
worker_count
ready_worker_count
healthy
reason
driver
control_transport
endpoint_hash
operation
outcome
duration_ms
```

`reason` is allowed only as a bounded package-owned health or error token.

Child PIDs and child generations are not public summary fields.

Endpoint identity may be represented publicly only as a deterministic hash.

## Operational notes

### State files

The worker state file is runtime state, not a generated architecture artifact.

It is stored under the skeleton runtime tree by default.

Operators may inspect it locally for debugging, but runtime public output must remain redacted.

### Pid handling

The worker runtime does not introduce a separate pid file.

The safe persistent supervisor PID is part of `worker.state.json`.

The pid may appear in allowed safe summaries and span/log attributes only where the observability policy allows it.

The pid must not be used as a metric label.

### Control transport

Unix control transport is local and path-based.

TCP control transport is host/port based.

Both transports are lifecycle-only.

Neither transport may carry task payloads.

Raw transport endpoint values are considered sensitive.

### Graceful and forced shutdown

Shutdown may be requested through:

```text
live stop request
SIGTERM
SIGINT
platform-native console control event
```

Signal handlers record shutdown intent only.

The synchronous supervisor event loop performs shutdown.

The cooperative stop signal is written only by the supervisor.

`ApplicationWorker` observes it only between tasks, so an in-flight UnitOfWork is allowed to finish.

The shutdown deadlines are:

```text
stop_timeout_ms
force_kill_timeout_ms
force_kill_timeout_ms
```

They apply to:

```text
cooperative exit
-> graceful terminate
-> hard kill
```

Successful shutdown requires all children to be reaped and all process resources to be closed.

The stop signal is not primary control and is not terminal acknowledgement.

The private lifecycle locator is deleted before terminal guardian release.

The generation fence is released by the guardian only after every owned worker is reaped/closed and proc-host shutdown is complete when applicable.

### Cross-platform behavior

`pcntl` is not assumed to exist on every platform.

Every platform resolves `worker.driver=auto` to `proc` only when `proc_open()` and the bounded loopback process-host transport are available. Otherwise start validation fails deterministically.

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
- external service-manager configuration;
- deployment restart policy;
- process-group, job-object, or cgroup policy;
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

Changing Worker declarative container wiring, required-service declarations, lazy supervisor resolution, lazy task-factory selection, or runtime path seed policy requires updating:

```text
docs/adr/ADR-0017-persistent-worker-supervisor-application-worker.md
docs/architecture/worker.md
docs/adr/ADR-0030-canonical-runtime-container-definitions.md
docs/ssot/runtime-container-definitions.md
```

Changing the process-child artifact-root input, artifact-generation selection, artifact-runtime hydration, runtime seed ownership, or child compiled-container boot requires updating:

```text
docs/architecture/worker.md
docs/adr/ADR-0029-kernel-container-compile-artifact.md
docs/ssot/compiled-container.md
docs/ssot/artifact-generations.md
docs/adr/ADR-0031-atomic-artifact-generations.md
framework/packages/platform/worker/tests/Contract/CoretsiaWorkerChildLauncherContractTest.php
framework/packages/platform/worker/tests/Integration/CompiledWorkerGraphContainsRequiredRuntimeServicesTest.php
```

Changing worker process ownership, supervisor/process-driver/application-worker boundaries, state schema, task factory visibility, or process-driver extension policy requires updating:

```text
docs/adr/ADR-0017-persistent-worker-supervisor-application-worker.md
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

Changing canonical lifecycle paths, locator schema, locator publication, lifecycle-command discovery, or active timeout ownership requires updating:

```text
docs/adr/ADR-0017-persistent-worker-supervisor-application-worker.md
docs/architecture/worker.md
docs/ssot/observability.md
framework/packages/platform/worker/README.md
framework/packages/platform/worker/tests/Unit/WorkerLifecycleLocatorTest.php
framework/packages/platform/worker/tests/Integration/WorkerLifecycleLocatorStoreFilesystemTest.php
framework/packages/platform/worker/tests/Integration/WorkerLifecycleConfigDriftTest.php
framework/packages/platform/worker/tests/Contract/WorkerLifecycleLocatorOwnershipContractTest.php
```

Changing worker spans, metrics, or allowed metric labels requires updating:

```text
docs/ssot/observability.md
```

## Cross-references

- [Runtime Driver Guard Architecture](./runtime-driver-guard.md)
- [Config Roots Registry](../ssot/config-roots.md)
- [Observability SSoT](../ssot/observability.md)
- [Process-Exec Descriptor Safety SSoT](../ssot/process-exec-descriptor-safety.md)
- [Runtime Drivers SSoT](../ssot/runtime-drivers.md)
- [UnitOfWork and Reset Contracts SSoT](../ssot/uow-and-reset-contracts.md)
- [ADR-0017: Persistent worker supervisor and application worker](../adr/ADR-0017-persistent-worker-supervisor-application-worker.md)
- [ADR-0019: Enhanced reset for long-running services](../adr/ADR-0019-enhanced-reset-long-running.md)
- [ADR-0020: Kernel runtime UnitOfWork SPI](../adr/ADR-0020-kernel-runtime-uow-spi.md)
- [ADR-0027: Runtime driver guard](../adr/ADR-0027-runtime-driver-guard.md)
- [Artifact Generations SSoT](../ssot/artifact-generations.md)
- [Compiled Container SSoT](../ssot/compiled-container.md)
- [ADR-0029: Kernel compiled container artifact](../adr/ADR-0029-kernel-container-compile-artifact.md)
- [Canonical Runtime Container Definitions SSoT](../ssot/runtime-container-definitions.md)
- [ADR-0030: Canonical Runtime Container Definitions](../adr/ADR-0030-canonical-runtime-container-definitions.md)
- [ADR-0032: Process-Exec Descriptor Safety](../adr/ADR-0032-process-exec-descriptor-safety.md)
