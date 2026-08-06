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

# coretsia/platform-worker

`platform/worker` is the experimental long-running Worker runtime package for the Coretsia Framework monorepo.

It provides one foreground persistent worker supervisor, cross-platform child-process adapters, live lifecycle control, deterministic readiness and shutdown, and package-local placeholder task factories. Production queue and HTTP task sources belong to platform or integration packages.

Scope: worker module metadata, canonical declarative worker container definitions, worker service provider/factory wiring, worker pool specification, foreground supervisor orchestration, lifecycle-lock authority, child readiness, lazy selected-driver resolution, single-child OS process drivers, PCNTL fork-exec isolation, proc process-host infrastructure, atomic-generation process-child boot, max-request recycle, deterministic worker state storage, payload-free live control, package-contributed worker commands, safe worker exceptions, and bounded worker observability summaries.

Out of scope: CLI binary dispatch, CLI command catalog construction, service-manager configuration and restart policy, HTTP platform adapters, real HTTP request production, real queue adapter behavior, external queue acknowledgement/retry/dead-letter semantics, scheduler integrations, RoadRunner/Swoole/FrankenPHP adapters, public task-source plugin APIs, public process-driver plugin APIs, Kernel UnitOfWork lifecycle ownership, Kernel hook discovery, reset discovery, reset execution semantics, observability exporters, and tooling-only behavior.

This README is a consumer-oriented package summary.

## Package identity

- Path: `framework/packages/platform/worker`
- Package id: `platform/worker`
- Composer name: `coretsia/platform-worker`
- Module id: `platform.worker`
- Namespace: `Coretsia\Platform\Worker\*` (PSR-4: `src/`)
- Kind: runtime
- Config root: `worker`
- Child launcher: `bin/coretsia-worker`
- Proc process host: `bin/coretsia-worker-proc-host`

The child launcher and proc process host are internal process-driver infrastructure.

They are not the user-facing `coretsia worker:*` command dispatcher.

`bin/coretsia-worker` performs artifact-only PCNTL and proc child boot.

`bin/coretsia-worker-proc-host` owns raw `proc_open()` resources on behalf of the foreground supervisor.

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions MUST NOT be used.

## Dependency policy

This package is runtime-safe and process-oriented.

- Depends on:
  - `core/contracts`
  - `core/foundation`
  - `core/kernel`
  - PSR interfaces used only as ports
- Forbidden:
  - `platform/cli`
  - `platform/http`
  - `integrations/*`
  - `devtools/*`

`platform/worker` contributes worker command classes, but CLI discovery, command catalog construction, binary dispatch, terminal UX, and output rendering remain owned by `platform/cli`.

`platform/worker` may preflight HTTP task mode through `Psr\Http\Server\RequestHandlerInterface`, but it MUST NOT depend on `platform/http` or import `Coretsia\Platform\Http\*`.

## Runtime responsibilities

This package provides the Worker runtime layer:

- worker module metadata through `Coretsia\Platform\Worker\Module\WorkerModule`;
- worker service provider registration through `Coretsia\Platform\Worker\Provider\WorkerServiceProvider`;
- stateless worker factory/wiring helpers through `Coretsia\Platform\Worker\Provider\WorkerServiceFactory`;
- worker command classes:
  - `Coretsia\Platform\Worker\Console\WorkerStartCommand`;
  - `Coretsia\Platform\Worker\Console\WorkerStopCommand`;
  - `Coretsia\Platform\Worker\Console\WorkerStatusCommand`;
  - `Coretsia\Platform\Worker\Console\WorkerHealthCommand`;
- foreground pool lifecycle ownership through `Coretsia\Platform\Worker\Supervisor\WorkerSupervisor`;
- lazy supervisor resolution through:
  - `Coretsia\Platform\Worker\Internal\WorkerSupervisorResolverInterface`;
  - `Coretsia\Platform\Worker\Supervisor\ContainerWorkerSupervisorResolver`;
- package-internal supervisor boundary through `Coretsia\Platform\Worker\Internal\WorkerSupervisorInterface`;
- package-internal single-child process-driver boundary through `Coretsia\Platform\Worker\Internal\WorkerProcessDriverInterface`;
- lazy selected-driver boundary through:
  - `Coretsia\Platform\Worker\Internal\WorkerProcessDriverResolverInterface`;
  - `Coretsia\Platform\Worker\Process\ContainerWorkerProcessDriverResolver`;
- canonical shell-free child argv construction through `Coretsia\Platform\Worker\Process\WorkerChildCommandBuilder`;
- Unix-like child execution through `Coretsia\Platform\Worker\Process\Driver\PcntlWorkerProcessDriver`;
- cross-platform proc child execution through `Coretsia\Platform\Worker\Process\Driver\ProcWorkerProcessDriver`;
- raw proc resource ownership through:
  - `Coretsia\Platform\Worker\Process\Proc\WorkerProcProcessHostClient`;
  - `Coretsia\Platform\Worker\Process\Proc\WorkerProcProcessHostProtocol`;
  - `Coretsia\Platform\Worker\Process\Proc\WorkerProcProcessHostHandoffEndpoint`;
  - `Coretsia\Platform\Worker\Process\Proc\WorkerProcProcessHostTransport`;
  - `bin/coretsia-worker-proc-host`;
- post-fork resource isolation through `Coretsia\Platform\Worker\Process\WorkerForkIsolation`;
- canonical package-owned lifecycle paths through `Coretsia\Platform\Worker\Runtime\WorkerLifecyclePaths`;
- lifecycle-lock authority through `Coretsia\Platform\Worker\Runtime\WorkerLifecycleLock`;
- immutable active-supervisor discovery data through `Coretsia\Platform\Worker\Runtime\WorkerLifecycleLocator`;
- atomic private locator storage through `Coretsia\Platform\Worker\Runtime\WorkerLifecycleLocatorStore`;
- canonical shutdown request and cleanup budgets through `Coretsia\Platform\Worker\Runtime\WorkerShutdownBudget`;
- cooperative between-task shutdown signaling through `Coretsia\Platform\Worker\Runtime\WorkerStopSignal`;
- typed child ownership through `Coretsia\Platform\Worker\Supervisor\WorkerChildTable`;
- synchronous shutdown-intent handling through `Coretsia\Platform\Worker\Supervisor\WorkerSignalController`;
- child readiness through `Coretsia\Platform\Worker\Communication\WorkerChildReadinessChannel`;
- live control behavior through:
  - `Coretsia\Platform\Worker\Communication\WorkerControlTransport`;
  - `Coretsia\Platform\Worker\Communication\WorkerControlProtocol`;
  - `Coretsia\Platform\Worker\Communication\WorkerControlServer`;
  - `Coretsia\Platform\Worker\Communication\WorkerControlClient`;
- package-internal live control boundary through `Coretsia\Platform\Worker\Internal\WorkerControlClientInterface`;
- normalized worker pool config through `Coretsia\Platform\Worker\Runtime\WorkerPoolSpec`;
- immutable safe pool state through `Coretsia\Platform\Worker\Runtime\WorkerPoolState`;
- immutable live health projection through `Coretsia\Platform\Worker\Runtime\WorkerHealthState`;
- deterministic diagnostic state I/O through `Coretsia\Platform\Worker\Runtime\WorkerStateStore`;
- one-root process-child artifact handoff through `--coretsia-worker-artifact-root`;
- artifact-only PCNTL and proc child container boot through Kernel `ArtifactRuntimeBooter`;
- sequential child task execution through `Coretsia\Platform\Worker\Worker\ApplicationWorker`;
- package-internal task-factory seam through `Coretsia\Platform\Worker\Internal\TaskFactoryInternalInterface`;
- placeholder queue task work through `Coretsia\Platform\Worker\Task\QueueTaskFactory`;
- HTTP task-mode preflight through `Coretsia\Platform\Worker\Task\HttpTaskFactory`;
- package-local Kernel runtime-driver contribution mapping through `Coretsia\Platform\Worker\Internal\WorkerRuntimeDriverContributions`;
- Worker-owned runtime-entrypoint compatibility through `Coretsia\Platform\Worker\Runtime\WorkerRuntimeEntrypointGuard`;
- deterministic Worker exceptions under `Coretsia\Platform\Worker\Exception`.

## Process model

The Worker runtime uses one foreground persistent supervisor:

```text
external service manager / container runtime
└─ worker:start
   └─ WorkerSupervisor
      ├─ canonical lifecycle lock
      ├─ private lifecycle locator
      ├─ control server
      ├─ child table
      ├─ readiness aggregation
      ├─ diagnostic state publication
      ├─ signal intent
      ├─ recycle policy
      └─ graceful and forced shutdown
```

The canonical startup path is:

```text
WorkerStartCommand
  -> WorkerServiceFactory::workerPoolSpec(...)
  -> WorkerPoolSpec
  -> WorkerRuntimeEntrypointGuard
       -> WorkerRuntimeDriverContributions::fromSpec(...) [internal]
       -> Kernel RuntimeEntrypointGuard
  -> WorkerSupervisorResolverInterface::resolve()
  -> WorkerSupervisorInterface::run(...)
       -> WorkerProcessDriverResolverInterface::resolve(WorkerPoolSpec)
       -> prepare driver-owned infrastructure
       -> acquire canonical WorkerLifecycleLock
       -> delete stale lifecycle locator, state, and stop signal
       -> open WorkerControlServer
       -> install supervisor signal handling
       -> publish starting state
       -> publish private WorkerLifecycleLocator
       -> spawn configured child slots
       -> wait for every child to become ready
       -> publish running state
       -> enter persistent event loop
```

The persistent event loop:

```text
serves status / health / stop
-> polls readiness
-> polls child exits
-> recycles expected ready-child exits
-> fails the complete pool on unexpected exits
-> processes requested or signal-driven shutdown
```

`WorkerPoolSpec` is the normalized Worker-owned source of truth for:

```text
task type
requested and resolved OS process driver
requested and resolved control transport
worker count
max requests
configurable socket, state, and stop-signal paths
TCP control settings
lifecycle deadlines
```

Worker task type maps to Kernel runtime-driver contributions:

```text
queue -> bg.worker_queue
http  -> http.worker
```

Worker OS process-driver selection is separate:

```text
pcntl
proc
```

The foreground supervisor owns the complete pool lifecycle.

Process drivers own only low-level operations for one child.

Each child runs one `ApplicationWorker`.

Each `ApplicationWorker` processes tasks sequentially until:

- `worker.max_requests` is reached;
- the supervisor-written cooperative stop signal is observed between tasks;
- task execution or child bootstrap produces a terminal process failure.

The lifecycle lock is the sole liveness authority.

The state file is diagnostic only.

The control channel is lifecycle-only and supports:

```text
status
health
stop
```

It MUST NOT transport task payloads.

## Driver and transport selection

Worker process driver selection is represented by `WorkerPoolSpec`.

Requested driver values:

```text
auto
pcntl
proc
```

Resolved driver values:

```text
pcntl
proc
```

WorkerSupervisor depends on `WorkerProcessDriverResolverInterface`, not on concrete process drivers.

`ContainerWorkerProcessDriverResolver` performs one exact package-owned mapping from `WorkerPoolSpec::driver()` and resolves only the selected concrete driver. It does not enumerate process-driver tags, fall back to another driver, or construct the unselected driver.

When `worker.driver=auto`, resolution is deterministic:

```text
pcntl when the required PCNTL fork/exec and POSIX capabilities are available and the platform is not Windows
proc when the secure proc process-host capability is available
deterministic lifecycle-validation failure when neither adapter is available
```

The `pcntl` driver is Unix-like and uses fork only to establish the child PID. The forked child detaches Worker-owned inherited resources and immediately executes the package-owned artifact-only launcher through `pcntl_exec()`.

The package guarantees explicit detachment only for descriptors it owns and registers in `WorkerForkIsolation`.

It does not enumerate or close arbitrary application, integration, extension, deployment, or third-party descriptors.

Integrations used in a process-capable runtime must follow the repository-wide process-exec descriptor-safety SSoT:

```text
docs/ssot/process-exec-descriptor-safety.md
```

Neither the PCNTL driver nor the proc driver alone proves arbitrary integration-descriptor isolation.

The `proc` driver is the cross-platform process adapter.

It delegates raw `proc_open()` ownership to the dedicated `bin/coretsia-worker-proc-host` process instead of retaining proc resources inside the supervisor.

Worker control transport selection is also represented by `WorkerPoolSpec`.

Requested control transport values:

```text
auto
unix
tcp
```

Resolved control transport values:

```text
unix
tcp
```

When `worker.control.transport=auto`, resolution is deterministic:

```text
unix when the platform is not Windows and unix domain sockets are supported
tcp otherwise
```

Control-transport selection is independent from the resolved OS process driver.

Raw socket paths and raw TCP endpoints are not public diagnostics.

Endpoint identity may be exposed publicly only through `endpoint_hash`.

Worker OS process-driver ids are not Kernel runtime-driver ids.

```text
worker.driver
  -> pcntl | proc
  -> internal OS child-process adapter

worker.task_type
  -> queue | http
  -> Kernel runtime-driver contribution
```

The Kernel runtime-driver guard does not select `pcntl` or `proc`.

## Process-child artifact-only boot

Both process drivers enter a fresh PHP runtime image before Worker runtime boot.

The proc driver starts a fresh PHP child process through a dedicated process host. The PCNTL driver forks, detaches Worker-owned resources, and replaces the forked supervisor image through `pcntl_exec()`.

For every spawn, the proc process host rotates its authenticated supervisor connection through a one-shot tokenized handoff. The current connection closes before `proc_open()` and the replacement connection opens only afterward. The same bounded stream-based invariant applies on Windows and POSIX without `ext-sockets` or `SOCK_CLOEXEC`.

Neither driver resolves `ApplicationWorker` from the supervisor container.

`WorkerServiceFactory::workerChildCommandBuilder(...)` derives one validated skeleton-root-relative artifact root from:

```text
RuntimePathContext::skeletonRoot()
RuntimePathContext::artifactRoot()
```

The absolute artifact root MUST be a strict descendant of the skeleton root.

An equal root or a path outside the skeleton fails deterministically.

`WorkerChildCommandBuilder` retains one skeleton-root-relative artifact root and builds the exact child argv vector for both drivers.

`PcntlWorkerProcessDriver` retains the normalized skeleton root, the package-owned launcher command, the command builder, readiness channel, and Worker fork-isolation boundary. It does not receive `ContainerInterface` or `ApplicationWorker`.

`ProcWorkerProcessDriver` retains the normalized skeleton root, one normalized child command vector, the command builder, readiness channel, and `WorkerProcProcessHostClient`.

It does not own:

- `WorkerStateStore`;
- `WorkerControlServer`;
- the lifecycle lock;
- pool-wide child state;
- recycle policy;
- shutdown policy;
- raw `proc_open()` resources.

Raw proc resources are owned by:

```text
bin/coretsia-worker-proc-host
```

The canonical artifact argument is:

```text
--coretsia-worker-artifact-root=<relative-safe-path>
```

Each child also receives internal readiness arguments:

```text
--coretsia-worker-readiness-port=<1..65535>
--coretsia-worker-readiness-token=<64-lowercase-hex>
```

The child MUST reject individual artifact-path arguments:

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

The child uses its working directory as the normalized skeleton root and resolves the relative artifact root against it.

It creates:

```php
new ArtifactRuntimeInput(
    skeletonRoot: $skeletonRoot,
    artifactRoot: $artifactRoot,
);
```

The child then invokes:

```text
Coretsia\Kernel\Boot\ArtifactRuntimeBooter
```

The Kernel boot boundary:

1. locates `current`;
2. validates one complete immutable generation;
3. reads exact snapshots for all four generation files;
4. validates generation metadata and envelope fingerprints;
5. hydrates `ConfigRepositoryInterface`;
6. hydrates `ModulePlan`;
7. creates `RuntimePathContext`;
8. builds the compiled runtime container.

After the container is built, the child resolves:

```text
WorkerPoolSpec
WorkerRuntimeEntrypointGuard
ConfigRepositoryInterface
ModulePlan
ApplicationWorker
```

It validates that child arguments match `WorkerPoolSpec`, invokes the Worker runtime-entrypoint guard, resolves `ApplicationWorker`, emits the exact readiness frame, and only then enters the long-running task loop.

Every PCNTL and proc spawn performs a fresh artifact-only boot.

This includes replacement children created by max-request recycle.

A recycled process child:

```text
receives the artifact root
-> locates current
-> validates the selected generation
-> hydrates a new runtime container
-> resolves ApplicationWorker
-> publishes readiness
-> enters the task loop
```

A replacement process child MUST NOT inherit:

- the previous child’s selected generation;
- artifact file handles;
- previously read artifact bytes;
- the previous runtime container;
- previous readiness state;
- generation-local runtime state.

If `current` changes between child generations, the replacement boots the generation selected at replacement startup.

The child artifact-only boot path MUST NOT:

- accept individual artifact paths;
- run Bootstrap Phase A;
- run ConfigKernel Phase B;
- read source config files;
- discover modules;
- read Composer module metadata;
- execute source providers;
- compile a replacement container graph;
- calculate fingerprints;
- write or repair artifacts;
- scan `generations/` for a newest directory;
- fall back to another generation.

The child launcher emits no stdout or stderr diagnostics.

A boot failure exits with a non-zero process code.

The supervisor observes that exit and maps it to package-owned deterministic Worker failure semantics.

Raw paths, artifact payloads, generation identifiers, config values, readiness tokens, and nested throwable messages MUST NOT be exposed publicly.

## Configuration

The worker config root is:

```text
worker
```

The defaults file is:

```text
config/worker.php
```

It returns the `worker` subtree only.

It MUST NOT wrap the subtree in a repeated root key such as:

```php
['worker' => [...]]
```

Baseline defaults include:

```php
[
    'workers' => 4,
    'max_requests' => 1000,
    'task_type' => 'queue',
    'socket_path' => 'var/tmp/worker.sock',
    'driver' => 'auto',
    'proc' => [
        'command' => [
            '@php',
            'vendor/coretsia/platform-worker/bin/coretsia-worker',
        ],
    ],
    'control' => [
        'transport' => 'auto',
    ],
    'tcp' => [
        'host' => '127.0.0.1',
        'port' => 9327,
    ],
    'state_path' => 'var/tmp/worker.state.json',
    'stop_flag_path' => 'var/tmp/worker.stop',
    'start_timeout_ms' => 10000,
    'stop_timeout_ms' => 10000,
    'force_kill_timeout_ms' => 1000,
]
```

Important config rules:

- `worker.task_type=queue` by default.
- `worker.workers` must be a positive integer.
- `worker.max_requests` must be a positive integer.
- `worker.task_type` is `queue` or `http`.
- `worker.driver` is `auto`, `pcntl`, or `proc`.
- `worker.control.transport` is `auto`, `unix`, or `tcp`.
- `worker.tcp.port` must be an explicit TCP port from `1` to `65535`.
- `worker.tcp.host` must be exactly `127.0.0.1`.
- TCP port `0` is forbidden.
- `worker.start_timeout_ms` must be a positive bounded timeout.
- `worker.stop_timeout_ms` must be a positive bounded timeout and is the strict wall-clock budget of the cooperative child-shutdown phase.
- `worker.force_kill_timeout_ms` must be a positive bounded timeout and independently bounds both the terminate/reap phase and the kill/reap phase.
- `var/tmp/worker.lock` is the package-owned, non-configurable lifecycle anchor.
- `var/tmp/worker.lifecycle.json` is the package-owned private active-supervisor locator.
- `var/tmp/worker.lifecycle.json.tmp` is the fixed atomic-write temporary locator path.
- configurable socket, state, state-temp, and stop-signal paths must not overlap each other or canonical lifecycle artifacts.
- configurable runtime paths must be skeleton-root-relative.
- runtime paths must not be absolute.
- runtime paths must not contain `..`, `skeleton/`, backslashes, whitespace, control characters, `://`, or segments beginning with `@`.

Phase-B config rules enforce the Worker-specific `skeleton/` and `@` constraints through `forbiddenPrefixes` and `forbiddenSegmentPrefixes`. `WorkerPoolSpec` repeats the same checks as runtime defense in depth.

`worker.task_type` is Worker-owned runtime input.

It is normalized by `WorkerPoolSpec`.

It is mapped to Kernel runtime-driver contributions by the package-local `WorkerRuntimeDriverContributions` mapper:

```text
queue -> bg.worker_queue
http  -> http.worker
```

Invalid or missing `worker.task_type` is a Worker-owned lifecycle-validation failure, not a Kernel runtime-driver invalid-config failure.

## Lifecycle discovery artifacts

Three separate runtime concepts are intentionally preserved:

```text
WorkerPoolSpec = desired configuration for creating a new pool
WorkerPoolState = redacted diagnostic snapshot of an active pool
WorkerLifecycleLocator = private address and stop deadlines of the active supervisor
```

Canonical package-owned paths are:

```text
var/tmp/worker.lock
var/tmp/worker.lifecycle.json
var/tmp/worker.lifecycle.json.tmp
```

The lock is the liveness authority. A free lock means the worker is not running, regardless of stale state or locator files. A held lock with a missing, unreadable, malformed, oversized, symlinked, or schema-invalid locator is a deterministic communication failure.

The locator has an exact versioned schema, is written atomically with mode `0600`, and contains only the active control transport, its private address, and the active stop deadlines. It is never rendered by CLI commands, copied into `worker.state.json`, or included in logs and exception messages.

The supervisor publishes the locator only after the listener, signal handling, and `starting` state are ready, but before child spawn. During shutdown it deletes the locator before releasing the lifecycle lock.

## Worker commands

This package provides command classes for:

```text
worker:start
worker:stop
worker:status
worker:health
```

The command classes implement:

```text
Coretsia\Contracts\Cli\Command\CommandInterface
```

They consume parsed input through:

```text
Coretsia\Contracts\Cli\Input\InputInterface
```

They write only through:

```text
Coretsia\Contracts\Cli\Output\OutputInterface
```

They MUST NOT write stdout or stderr directly.

They MUST NOT depend on `platform/cli`.

Full `coretsia worker:*` binary dispatch through container-backed CLI tag discovery remains owned by `platform/cli`.

### `worker:start`

Starts one foreground persistent supervisor.

The strict startup order is:

```text
WorkerStartCommand
  -> WorkerServiceFactory::workerPoolSpec(...)
  -> WorkerPoolSpec
  -> WorkerRuntimeEntrypointGuard::assertEntrypointAllowed(...)
       -> WorkerRuntimeDriverContributions::fromSpec(...) [internal]
       -> Kernel RuntimeEntrypointGuard::assertEntrypointAllowed(...)
  -> WorkerSupervisorResolverInterface::resolve()
  -> WorkerSupervisorInterface::run(...)
```

`WorkerPoolSpec` is built before the Worker-owned runtime-entrypoint boundary is invoked.

The supervisor remains unresolved until runtime-entrypoint validation succeeds.

The command emits one startup summary only after:

```text
every configured child is ready
AND pool status == running
```

The command process then remains blocked in the foreground until shutdown completes.

### `worker:stop`

Requests shutdown through `WorkerControlClientInterface`.

The command:

1. probes the canonical lifecycle-lock authority;
2. reads the private lifecycle locator;
3. connects to the active endpoint from that locator;
4. uses the active supervisor's locator-published stop deadlines;
5. sends one `stop` request;
6. waits while the supervisor performs cooperative, graceful, and forced shutdown;
7. reports success only after the terminal `stopped` response.

Lifecycle commands do not resolve `WorkerPoolSpec` and do not use current worker configuration to address an active supervisor.

The command MUST NOT:

- resolve `WorkerPoolSpec` from current config;
- write the stop signal directly;
- create a control listener;
- read diagnostic state as liveness authority;
- own child processes;
- release the lifecycle lock.

### `worker:status`

Requests current in-memory state through `WorkerControlClientInterface`.

It probes the canonical lifecycle lock, reads the private locator, and connects to the endpoint of the active supervisor. It does not resolve current worker configuration.

It MUST NOT infer running state from `worker.state.json`.

### `worker:health`

Requests the live health projection through `WorkerControlClientInterface`.

It uses the same canonical-lock and private-locator discovery flow as status and stop, without resolving `WorkerPoolSpec` from current config.

Health is true only when:

```text
pool status == running
AND ready_worker_count == worker_count
AND no terminal child failure is pending
```

The command exits non-zero for an unhealthy live pool.

### Successful command summaries

Successful command summaries may expose only bounded safe fields:

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

`pid` is the persistent supervisor PID.

Child PIDs are not public command-summary fields.

Raw socket paths, raw TCP endpoints, config values, payloads, headers, tokens, readiness tokens, absolute paths, and throwable messages MUST NOT be exposed.

## Runtime-driver guard boundary

Runtime-driver matrix and module-compatibility policy are Kernel-owned.

The public Kernel boundary is:

```text
Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard
```

The public Kernel contribution handoff object is:

```text
Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions
```

Worker runtime callers do not invoke this Kernel boundary directly.

The public Worker-owned entrypoint boundary is:

```text
Coretsia\Platform\Worker\Runtime\WorkerRuntimeEntrypointGuard
```

The following Worker-owned runtime paths use this boundary:

```text
WorkerStartCommand
HttpTaskFactory
bin/coretsia-worker
```

They call:

```text
WorkerRuntimeEntrypointGuard::assertEntrypointAllowed(...)
```

with:

```text
ConfigRepositoryInterface
ModulePlan
WorkerPoolSpec
```

`WorkerRuntimeEntrypointGuard` owns:

- the `platform.worker` ModulePlan participation check;
- delegation to the package-internal `WorkerRuntimeDriverContributions::fromSpec(...)` mapper;
- construction of explicit Kernel `RuntimeDriverContributions`;
- delegation to the public Kernel `RuntimeEntrypointGuard`.

Worker callers MUST NOT:

- import `WorkerRuntimeDriverContributions`;
- call `WorkerRuntimeDriverContributions::fromSpec(...)` directly;
- call the Kernel `RuntimeEntrypointGuard` directly;
- call both Worker and Kernel guards for one entrypoint attempt;
- independently resolve the active runtime-driver set.

The shipped `bin/coretsia-worker` executable MUST NOT import classes from:

```text
Coretsia\Platform\Worker\Internal\*
```

The Kernel assertion method delegates internally to the canonical `RuntimeEntrypointGuard::resolveEntrypointDrivers(...)` implementation.

The Worker package owns:

```text
worker.task_type
```

The Worker package maps task type to Kernel runtime-driver contributions:

```text
worker.task_type=queue -> bg.worker_queue
worker.task_type=http  -> http.worker
```

This mapping is independent from Worker OS process-driver selection:

```text
worker.driver=pcntl -> WorkerProcessDriverResolverInterface -> PcntlWorkerProcessDriver
worker.driver=proc  -> WorkerProcessDriverResolverInterface -> ProcWorkerProcessDriver
```

`pcntl` and `proc` MUST NOT enter `RuntimeDriverContributions`.

The Kernel runtime-driver guard does not evaluate OS child-process capability.

The Worker supervisor does not independently evaluate the Kernel HTTP/background compatibility matrix.

This mapping is package-local and is owned by:

```text
WorkerRuntimeDriverContributions
```

The Worker package MUST NOT ask Kernel to read `worker.task_type`.

The Worker package MUST NOT make `RuntimeDriverGuard` read the `worker` config root.

The Worker package MUST NOT call `RuntimeDriverGuard` directly.

`RuntimeDriverGuard` remains a Kernel-internal implementation detail behind `RuntimeEntrypointGuard`.

Missing or invalid `worker.task_type` is Worker-owned invalid state and must fail through Worker exception policy.

Runtime-driver matrix conflicts and module-compatibility failures remain Kernel runtime-driver guard failures.

The worker package MUST NOT duplicate runtime-driver matrix logic.

The worker package MUST NOT reclassify Kernel runtime-driver guard failures as worker exceptions.

HTTP worker mode must pass Kernel runtime entrypoint compatibility before request-handler resolution.

Missing `platform.http` for `http.worker` must fail through the Kernel runtime entrypoint guard before request-handler resolution.

## UnitOfWork and reset boundary

`ApplicationWorker` executes each task through:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface::runUnitOfWork(...)
```

Reset discipline between worker tasks is achieved only transitively through KernelRuntime.

The canonical lifecycle is:

```text
begin
  -> before hooks
  -> task
  -> after hooks
  -> ResetOrchestrator::resetAll()
```

`platform/worker` MUST NOT:

- call before/after UnitOfWork hooks directly;
- enumerate hook tags;
- enumerate reset tags;
- call `ResetOrchestrator::resetAll()` directly;
- create UnitOfWork ids directly;
- create correlation ids directly;
- write context values directly.

Kernel owns UnitOfWork lifecycle semantics.

Foundation owns reset orchestration infrastructure.

The worker package owns only the long-running loop and task submission into the Kernel runtime boundary.

## Task modes

Supported task types are:

```text
queue
http
```

### Queue task mode

`QueueTaskFactory` handles:

```text
worker.task_type=queue
```

The shipped queue task factory performs package-local placeholder task work.

It does not implement a production queue adapter.

Real queue sources, transports, acknowledgement semantics, retry semantics, dead-letter behavior, and integration-specific adapters are owned by integration packages.

### HTTP task mode

`HttpTaskFactory` handles:

```text
worker.task_type=http
```

It does not implement a production HTTP request source.

It does not create PSR-7 requests.

It does not depend on `platform/http`.

HTTP task mode first requires `RuntimeEntrypointGuard` compatibility to pass with the explicit `http.worker` contribution produced from the normalized `WorkerPoolSpec`.

Only after that may it require a resolvable:

```text
Psr\Http\Server\RequestHandlerInterface
```

Request-handler preflight failures use deterministic worker start reasons:

```text
worker-request-handler-missing
worker-request-handler-unresolvable
worker-request-handler-invalid
```

## State files

`WorkerStateStore` owns deterministic diagnostic state I/O.

The default state path is:

```text
var/tmp/worker.state.json
```

There is no separate `worker.pid_path` config key.

The stored PID is the persistent supervisor PID.

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

`stopped` is not persisted.

It is returned only as a terminal live control result after shutdown cleanup.

The state file is diagnostic only.

It is not liveness authority.

The liveness rules are:

```text
lifecycle lock free
  -> pool is not running

lifecycle lock held + valid private locator
  -> pool is starting, running, or stopping

lifecycle lock held + missing, unreadable, malformed, oversized, symlinked, or schema-invalid private locator
  -> communication failure

lifecycle lock held + unavailable control endpoint
  -> communication failure
```

A stale state file with a free lifecycle lock does not mean the pool is running.

State publication uses deterministic JSON and atomic temp-file plus rename semantics.

After complete successful shutdown, the state file is deleted.

The state file MUST NOT contain:

- timestamps;
- environment values;
- raw socket paths;
- raw TCP hosts or ports;
- absolute paths;
- child PIDs;
- task payloads;
- HTTP headers;
- cookies;
- Authorization values;
- tokens;
- raw endpoint identifiers;
- exception messages;
- stack traces.

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

- deterministic address derivation;
- listen;
- connect;
- timeout-aware accept;
- bounded frame reads and writes;
- connection closure;
- Unix socket cleanup.

`WorkerControlProtocol` owns exact versioned request and response schemas.

`WorkerControlServer` owns the live supervisor listener and typed control sessions.

`WorkerControlClient` owns lifecycle-lock probing, private locator resolution, endpoint-consistency validation, and live request-response behavior.

Supported control transports are:

```text
unix
tcp
```

The control protocol supports exactly:

```text
status
health
stop
```

The control protocol MUST NOT contain:

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
- strict about unknown keys;
- strict about unsupported versions;
- payload-free.

A stop request remains pending until:

- every child has exited;
- every child has been reaped;
- every child resource is closed;
- driver infrastructure is shut down;
- diagnostic state is deleted;
- the cooperative stop signal is cleared;
- the control listener is closed;
- the Unix socket is removed when applicable;
- the private lifecycle locator is deleted;
- the lifecycle lock is released.

Only then may the server return terminal:

```text
stopped
```

A disconnected status or health client MUST NOT terminate the supervisor.

The control channel MUST NOT transport task payloads.

Control communication failures map to deterministic `WorkerCommunicationFailedException`.

Public diagnostics MUST NOT expose raw socket paths, socket basenames, raw TCP hosts, raw TCP ports, raw endpoint strings, payloads, headers, tokens, readiness tokens, or throwable messages.

## Observability

Worker observability follows the canonical observability SSoT.

Worker span names:

```text
worker.process
worker.task
```

Worker metric names:

```text
worker.process_total
worker.task_total
worker.task_duration_ms
```

Allowed worker process metric label:

```text
status
```

The currently emitted bounded `status` values are:

```text
start_success
start_failure
stop_success
stop_failure
status_success
status_failure
```

The reserved recycle values are:

```text
recycle_success
recycle_failure
```

Reserved values MUST NOT be emitted until the corresponding metric path is implemented and covered by deterministic tests.

Worker process status values MUST NOT encode:

- worker index;
- child generation;
- PID;
- signal;
- exit code;
- error reason.

Allowed worker task metric labels:

```text
operation
outcome
```

Forbidden metric labels include:

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

Worker logs and spans are summary-only.

Logger, tracer, meter, stopwatch, and context dependencies are injected.

Worker runtime classes MUST NOT instantiate observability adapters directly.

Observability failures MUST NOT change worker lifecycle semantics, task semantics, reset semantics, or selected public failure.

ApplicationWorker stopwatch start/stop failures MUST NOT change worker task execution, KernelRuntime delegation, task outcome selection, or worker task failure precedence. When worker task timing is unavailable, task duration metadata MUST collapse to `0`.

## Errors

Worker package failures use deterministic worker exceptions under:

```text
Coretsia\Platform\Worker\Exception
```

The base exception is:

```text
WorkerException
```

Concrete worker exceptions include:

```text
WorkerStartFailedException
WorkerLifecycleFailedException
WorkerForkFailedException
WorkerAlreadyRunningException
WorkerCommunicationFailedException
WorkerNotRunningException
```

Public worker exception messages have the canonical form:

```text
CORETSIA_WORKER_*: worker-reason-token
```

Examples:

```text
CORETSIA_WORKER_START_FAILED: worker-start-failed
CORETSIA_WORKER_START_FAILED: worker-request-handler-missing
CORETSIA_WORKER_START_FAILED: worker-request-handler-unresolvable
CORETSIA_WORKER_START_FAILED: worker-request-handler-invalid
CORETSIA_WORKER_START_FAILED: worker-readiness-timeout
CORETSIA_WORKER_START_FAILED: worker-readiness-invalid
CORETSIA_WORKER_START_FAILED: worker-child-start-failed
CORETSIA_WORKER_START_FAILED: worker-signal-handling-unavailable
CORETSIA_WORKER_LIFECYCLE_FAILED: worker-lifecycle-failed
CORETSIA_WORKER_LIFECYCLE_FAILED: worker-invalid-state
CORETSIA_WORKER_LIFECYCLE_FAILED: worker-child-exited
CORETSIA_WORKER_LIFECYCLE_FAILED: worker-shutdown-failed
CORETSIA_WORKER_LIFECYCLE_FAILED: worker-runtime-cleanup-failed
CORETSIA_WORKER_LIFECYCLE_FAILED: worker-lifecycle-lock-failed
CORETSIA_WORKER_LIFECYCLE_FAILED: worker-lifecycle-locator-failed
CORETSIA_WORKER_LIFECYCLE_FAILED: worker-process-host-failed
CORETSIA_WORKER_FORK_FAILED: worker-fork-failed
CORETSIA_WORKER_ALREADY_RUNNING: worker-already-running
CORETSIA_WORKER_COMMUNICATION_FAILED: worker-communication-failed
CORETSIA_WORKER_NOT_RUNNING: worker-not-running
```

Worker exception messages MUST NOT include previous throwable messages, stack traces, absolute paths, raw socket paths, raw TCP endpoints, raw config values, payload fragments, headers, tokens, process command lines, or environment data.

`WorkerStartFailedException` is limited to startup validation, request-handler resolution, readiness, child-process creation, and signal bootstrap.

`WorkerLifecycleFailedException` owns runtime-wide supervisor failures, including invalid lifecycle state, unexpected child exit, shutdown, runtime cleanup, lifecycle-lock, lifecycle-locator, and proc-host failures.

`worker:start`, `worker:status`, `worker:health`, and `worker:stop` preserve the error code and reason of concrete `WorkerException` instances. Unknown throwables are mapped to command-specific safe catch-all errors.

Runtime-driver matrix failures remain Kernel runtime-driver guard failures.

They must not be reclassified as worker exceptions.

Worker-owned task type validation failures are not runtime-driver matrix failures.

Missing or invalid `worker.task_type` is surfaced as:

```text
CORETSIA_WORKER_LIFECYCLE_FAILED: worker-invalid-state
```

after Worker-owned normalization fails.

Kernel runtime-driver failures are surfaced unchanged only after Worker has produced explicit `RuntimeDriverContributions`.

## Security / Redaction

The worker package treats the following values as unsafe for public diagnostics:

- raw socket paths;
- raw TCP hosts;
- raw TCP ports;
- raw endpoint identifiers;
- raw lifecycle locator JSON;
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

Safe public summaries may include only:

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

Endpoint identity may be represented publicly only as a deterministic hash.

The private lifecycle locator, `socket_path`, `tcp_host`, and `tcp_port` MUST NOT be emitted through logs, spans, metrics, CLI output, state snapshots, or exception messages.

`reason` is allowed only when it is a bounded package-owned health or error token.

Arbitrary throwable messages and dynamically generated reason values remain forbidden.

The public `pid` is the persistent supervisor PID.

Child PIDs, child generations, readiness endpoints, and readiness tokens are not public summary fields.

## Internal seams

The following interfaces are package-internal:

```text
Coretsia\Platform\Worker\Internal\WorkerSupervisorInterface
Coretsia\Platform\Worker\Internal\WorkerSupervisorResolverInterface
Coretsia\Platform\Worker\Internal\WorkerProcessCapabilities
Coretsia\Platform\Worker\Internal\WorkerProcessDriverInterface
Coretsia\Platform\Worker\Internal\WorkerProcessDriverResolverInterface
Coretsia\Platform\Worker\Internal\WorkerControlClientInterface
Coretsia\Platform\Worker\Internal\TaskFactoryInternalInterface
```

The following helper is also package-internal:

```text
Coretsia\Platform\Worker\Internal\WorkerRuntimeDriverContributions
```

It maps Worker-owned task type to the public Kernel `RuntimeDriverContributions` handoff object.

It does not select the Worker OS process driver.

These interfaces and helpers:

- are not public package APIs;
- are not application extension points;
- MUST NOT be moved to `core/contracts`;
- MUST NOT be exported through Composer `extra` metadata as public API;
- MUST NOT be documented as stable third-party plugin boundaries.

The process-driver seam is limited to one-child OS operations.

The supervisor seam owns pool-wide lifecycle.

The control-client seam owns live command communication.

The task-factory seam owns package-local task work creation.

## Non-goals

This package does not provide:

- production queue backend behavior;
- queue acknowledgement semantics;
- queue retry semantics;
- queue dead-letter behavior;
- scheduler behavior;
- production HTTP request production;
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
- container artifact schema;
- artifact-generation publication;
- artifact-generation validation policy;
- compiled-container payload construction;
- config merge implementation;
- config validation implementation;
- production observability exporter configuration;
- automatic degraded-capacity child restart policy;
- rolling in-process pool replacement;
- public supervisor plugin APIs;
- public control-protocol extension APIs.

## References

- [Worker Architecture](https://github.com/coretsia/monorepo/tree/main/docs/architecture/worker.md)
- [Runtime Driver Guard Architecture](https://github.com/coretsia/monorepo/tree/main/docs/architecture/runtime-driver-guard.md)
- [ADR-0017: Persistent worker supervisor and application worker](https://github.com/coretsia/monorepo/tree/main/docs/adr/ADR-0017-persistent-worker-supervisor-application-worker.md)
- [Config Roots Registry](https://github.com/coretsia/monorepo/tree/main/docs/ssot/config-roots.md)
- [Observability SSoT](https://github.com/coretsia/monorepo/tree/main/docs/ssot/observability.md)
- [Runtime Drivers SSoT](https://github.com/coretsia/monorepo/tree/main/docs/ssot/runtime-drivers.md)
- [Runtime Container Definitions SSoT](https://github.com/coretsia/monorepo/tree/main/docs/ssot/runtime-container-definitions.md)
- [Process-Exec Descriptor Safety SSoT](https://github.com/coretsia/monorepo/tree/main/docs/ssot/process-exec-descriptor-safety.md)
- [UnitOfWork and Reset Contracts SSoT](https://github.com/coretsia/monorepo/tree/main/docs/ssot/uow-and-reset-contracts.md)
- [Artifact Generations SSoT](https://github.com/coretsia/monorepo/tree/main/docs/ssot/artifact-generations.md)
- [Compiled Container SSoT](https://github.com/coretsia/monorepo/tree/main/docs/ssot/compiled-container.md)
- [ADR-0029: Kernel compiled container artifact](https://github.com/coretsia/monorepo/tree/main/docs/adr/ADR-0029-kernel-container-compile-artifact.md)
- [ADR-0031: Atomic Artifact Generations](https://github.com/coretsia/monorepo/tree/main/docs/adr/ADR-0031-atomic-artifact-generations.md)
- [ADR-0032: Process-Exec Descriptor Safety](https://github.com/coretsia/monorepo/tree/main/docs/adr/ADR-0032-process-exec-descriptor-safety.md)
- [Worker package source](https://github.com/coretsia/monorepo/tree/main/framework/packages/platform/worker)
