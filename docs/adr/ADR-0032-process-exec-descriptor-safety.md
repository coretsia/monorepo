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

# ADR-0032: Process-Exec Descriptor Safety

```yaml
adrVersion: 1
status: pre-accepted
owner: repo
```

## Context

Coretsia Worker children use fresh process images:

```text
PCNTL: fork -> detach Worker-owned resources -> exec child launcher
proc: guardian -> pre-lock process host -> proc_open child launcher
```

Process-image replacement removes the inherited PHP object graph as active runtime state, including the parent container, resolved DI cache, `ApplicationWorker`, static PHP state, and extension process memory.

Operating-system descriptors have separate inheritance semantics.

A descriptor may cross exec when it is not marked close-on-exec. A proc-created process may likewise inherit descriptors that were open in the process that launched it.

The Worker package already explicitly detaches the descriptors it owns and knows about. It cannot safely enumerate arbitrary application, integration, third-party, or deployment-owned descriptors.

Coretsia therefore needs a precise layered guarantee without claiming universal descriptor closure.

The normative policy is owned by:

```text
docs/ssot/process-exec-descriptor-safety.md
```

## Decision

### Decision 1: Use layered descriptor safety

Coretsia distinguishes:

```text
object-graph isolation
package-owned descriptor isolation
integration-owned descriptor obligations
unsupported arbitrary-descriptor claims
```

`exec` provides process-image replacement. It does not provide a framework-wide proof that every descriptor is closed.

### Decision 2: Request close-on-exec for Coretsia-owned local files

Coretsia-owned local files that can coexist with process execution request the POSIX `fopen()` `e` flag.

Windows uses the equivalent valid mode without `e`.

The canonical owner-local policies are:

```text
WorkerLifecycleLock: Windows c+b, POSIX c+be
WorkerLifecycleLocatorStore temporary file: Windows x+b, POSIX x+be
ArtifactGenerationLock: Windows c+b, POSIX c+be
ArtifactWriter durable file: Windows xb, POSIX xbe
```

These policies are defense in depth. They do not replace explicit closure by the descriptor owner.

### Decision 3: Explicitly detach known package-owned resources

Known Worker-owned listeners, sessions, locks, child readiness endpoints, and signal state are detached by their exact owners before PCNTL exec.

The PCNTL guardian explicitly closes its supervisor-ownership connection and detaches `WorkerLifecycleLock` in the forked child before exec. No supervisor-side fork-isolation aggregate remains because the foreground supervisor no longer forks worker children.

For every proc spawn, the guardian-owned process-host client creates a one-shot loopback handoff listener with a fresh 256-bit token. After validating the complete spawn request, the process host closes its current authenticated guardian connection, calls `proc_open()` with no proc-host protocol connection open, and only then connects to the handoff listener and publishes the exact spawn response. The same stream-based invariant applies on Windows and POSIX without `ext-sockets`, `SOCK_CLOEXEC`, FFI, or a native launcher.

A failed child launch still rotates the connection and returns `child-start-failed`. A failed replacement handoff causes the process host to terminate and reap all registered children and exit non-zero, because the guardian cannot safely retain a child whose identity was not delivered.

It does not enumerate application or integration services.

### Decision 4: Make integrations responsible for their descriptors

An integration that opens persistent resources in a process capable of launching children must request close-on-exec, explicitly detach its resources, delay their creation until after launch infrastructure isolation, or declare the launch mode unsupported.

Neither PCNTL nor proc is documented as automatically safe for arbitrary integration descriptors.

### Decision 5: Do not introduce a generic post-fork cleanup registry

Coretsia does not introduce:

```text
DescriptorManager
ForkCleanupRegistry
CloseOnExecRegistry
ForkIsolationParticipantInterface
```

A generic registry would require registration, discovery, or eager service resolution; would execute arbitrary code in a forked process; and would still omit unregistered third-party descriptors.

## Rejected alternatives

### Clear the DI resolved cache after fork

Rejected because it does not close inherited operating-system descriptors or replace the inherited process image.

### Scan `/proc/self/fd` and close discovered descriptors

Rejected because it is Linux-specific, race-prone, ownership-blind, and can close descriptors required by the process launcher.

### Guess descriptor integers or use `php://fd`

Rejected because descriptor numbers are not a stable ownership API and reopening a descriptor does not establish ownership of the original descriptor.

### Use FFI to close arbitrary descriptors

Rejected because it expands the trusted runtime surface, remains platform-specific, and cannot determine descriptor ownership safely.

### Invoke every stateful service after fork

Rejected because it requires eager service resolution, executes arbitrary application code in a forked process, and cannot cover unregistered resources.

### Claim `proc_open()` is automatically isolated

Rejected because the launching process may already own application or integration descriptors.

### Claim `pcntl_exec()` closes every descriptor

Rejected because descriptors without close-on-exec may cross successful exec.

## Consequences

Positive consequences:

- parent PHP/container state does not cross the successful PCNTL exec boundary;
- Coretsia-owned local files gain close-on-exec defense in depth on POSIX;
- Worker-owned descriptors at the PCNTL fork boundary retain explicit ownership and detachment;
- integration obligations become explicit and testable;
- PCNTL and proc guarantees are documented without overstatement.

Trade-offs:

- pure PHP cannot guarantee closure of arbitrary unregistered descriptors;
- integrations must make an explicit process-exec safety decision;
- the accepted guarantee is limited to Coretsia-owned descriptors and descriptors whose owners comply with the canonical process-exec safety contract;
- isolation of arbitrary unregistered application, integration, extension, or deployment descriptors is a non-goal of this decision.

The local-file close-on-exec policy is defense in depth and does not replace explicit descriptor ownership.

This decision requires no generic descriptor registry, post-fork DI cleanup, or additional process broker.

## Related SSoT

- `docs/ssot/process-exec-descriptor-safety.md`
- `docs/ssot/runtime-container-definitions.md`
- `docs/ssot/artifact-generations.md`

## Related ADRs

- `docs/adr/ADR-0017-persistent-worker-supervisor-application-worker.md`
- `docs/adr/ADR-0031-atomic-artifact-generations.md`
