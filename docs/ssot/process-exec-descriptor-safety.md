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

# Process-Exec Descriptor Safety (SSoT)

```yaml
ssotVersion: 1
status: pre-stable
owner: repo
```

This document is the Single Source of Truth for Coretsia process-exec descriptor ownership, fork and exec inheritance guarantees, close-on-exec policy, explicit fork-child detachment, integration obligations, and public guarantee levels.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Authority boundary (MUST)

This document owns:

- fork-time descriptor inheritance;
- exec-time descriptor inheritance;
- Coretsia-owned and package-owned descriptor policy;
- integration-owned, third-party, and deployment-owned descriptor obligations;
- POSIX close-on-exec policy for local files;
- explicit fork-child detachment for sockets, listeners, sessions, locks, pipes, and process resources;
- process-launch guarantee levels;
- descriptor-related diagnostics and redaction.

This document does not own:

- DI container lifecycle;
- reset orchestration;
- application service discovery;
- deployment-specific descriptor injection;
- operating-system process groups, cgroups, or job objects;
- a public process-driver plugin API;
- arbitrary third-party resource cleanup.

## Terms

### Coretsia-owned descriptor

An operating-system descriptor opened and lifecycle-managed by Coretsia production code.

### Package-owned descriptor

A Coretsia-owned descriptor whose exact owner, closure point, and process-boundary behavior belong to one package.

### Integration-owned descriptor

A descriptor opened by an integration package or adapter, including database, queue, telemetry, network, or file resources.

### Third-party descriptor

A descriptor opened by third-party code that Coretsia does not own or enumerate.

### Deployment-owned descriptor

A descriptor inherited from the shell, service manager, container runtime, orchestrator, or host process before Coretsia runtime boot.

### Fork boundary

The point at which a child receives a copy of the parent process image and inherited descriptors.

### Exec boundary

The point at which an existing process image is replaced by another executable image.

### Close-on-exec

An operating-system descriptor flag that closes the descriptor during successful process-image replacement.

### Explicit detach

Owner-driven closure or reset of a known inherited resource in a forked child before process-image replacement.

## Guarantee levels (MUST)

### Level 1: object-graph isolation

`pcntl_exec()` replaces the inherited PHP process image.

A correctly implemented fork-exec child MUST resolve no child runtime service before exec.

A parent runtime container, resolved DI cache, `ApplicationWorker`, PHP object graph, static PHP state, and extension process memory MUST NOT cross the successful exec boundary as active PHP runtime state.

### Level 2: package-owned descriptor isolation

Coretsia-owned local files that can coexist with process execution MUST request close-on-exec on POSIX.

Known package-owned sockets, listeners, accepted sessions, locks, pipes, and process resources MUST have explicit owner-driven closure or fork-child detachment whenever they can exist at that boundary.

Close-on-exec is defense in depth and MUST NOT replace explicit package ownership where explicit detachment is available.

### Level 3: integration obligation

An integration that opens a persistent operating-system resource in a process that may launch children MUST do at least one of the following:

- request close-on-exec where its runtime API supports it;
- explicitly close or detach the resource at the package-owned process boundary;
- create the resource only after child-launch infrastructure is isolated;
- explicitly declare the affected process driver or launch mode unsupported.

Integrations MUST NOT assume that PCNTL or proc automatically closes their descriptors.

### Level 4: unsupported absolute claim

The Coretsia pure-PHP runtime does not claim to discover or close arbitrary unregistered third-party or deployment-owned descriptors.

Neither `pcntl_exec()` nor `proc_open()` alone proves arbitrary descriptor isolation.

## Local-file rule (MUST)

Production `fopen()` calls for Coretsia-owned local operating-system files MUST request the POSIX `e` flag when the handle can coexist with process execution.

Windows MUST use the equivalent valid mode without `e`.

The canonical modes are:

| Owner                                        | Windows |  POSIX |
|----------------------------------------------|--------:|-------:|
| `WorkerLifecycleLock`                        |   `c+b` | `c+be` |
| `WorkerLifecycleLocatorStore` temporary file |   `x+b` | `x+be` |
| `ArtifactGenerationLock`                     |   `c+b` | `c+be` |
| `ArtifactWriter` exclusive durable file      |    `xb` |  `xbe` |

Owner-local mode methods are preferred over a generic descriptor manager because PHP does not expose one universal close-on-exec operation for every stream and socket type.

## Socket, pipe, and process-resource rule (MUST)

An owner of a socket, listener, accepted session, pipe, or process resource MUST explicitly close or detach that resource before forked-child exec when the resource can exist at that boundary.

The owner MUST define:

- where the resource is opened;
- which process owns it;
- when it is closed;
- whether it can cross fork;
- whether it can cross exec;
- which test proves the boundary.

## Worker boundary (MUST)

The PCNTL child sequence is:

```text
close current child readiness listener
-> WorkerChildTable detaches sibling readiness listeners
-> WorkerControlServer detaches the control listener
-> WorkerLifecycleLock detaches the lifecycle-lock handle
-> WorkerSignalController resets inherited signal state
-> pcntl_exec package-owned child launcher
```

`WorkerForkIsolation` owns only Worker-known supervisor resources.

It MUST NOT enumerate or close arbitrary application, integration, extension, deployment, or third-party descriptors.

The proc process host starts before Worker lifecycle-lock acquisition and before supervisor listeners are opened. This prevents inheritance of those later Worker-owned resources, but it does not prove isolation from descriptors opened before process-host startup.

The proc process host MUST prevent its authenticated supervisor connection from crossing worker-child launch.

For every spawn request, the supervisor MUST create a one-shot loopback handoff listener after process-host startup and MUST authenticate it with a fresh 256-bit token.

After validating the complete spawn request, the process host MUST close its current authenticated supervisor connection before calling `proc_open()`.

The process host MUST establish the replacement authenticated connection only after `proc_open()` returns. The replacement handoff frame MUST bind the handoff token, request id, protocol version, and exact encoded spawn response.

No proc-host protocol connection may be open while the host calls `proc_open()`.

A failed child launch MUST still restore the authenticated connection and return the deterministic `child-start-failed` response through the handoff.

If the replacement connection cannot be established or authenticated after a child was created, the process host MUST terminate and reap every registered child and MUST exit non-zero. The supervisor MUST treat the handoff failure as a process-host failure and MUST NOT retain an unidentified child.

The proc process-host transport is available only when `proc_open()` and every required bounded loopback stream operation are available. It MUST NOT require `ext-sockets` or `SOCK_CLOEXEC` for descriptor isolation.

Starting the host before lifecycle-lock acquisition does not satisfy this requirement for descriptors owned by the process host itself; the per-spawn connection handoff is the owner-specific isolation boundary.

## Driver semantics (MUST)

PCNTL and proc both depend on descriptor-owner discipline.

The PCNTL fork-exec path provides object-graph isolation and explicit Worker-owned detachment.

The proc process-host path avoids inheritance of Worker-owned resources opened after driver preparation and rotates its authenticated supervisor connection around every worker-child launch.

Neither driver alone proves arbitrary integration-descriptor isolation.

This SSoT does not guarantee isolation for descriptors opened by application, integration, extension, or deployment code before process launch.

Such descriptors remain the responsibility of their owners under this contract.

## Forbidden implementations (MUST NOT)

Production code MUST NOT implement descriptor safety by:

- sweeping `/proc/self/fd` and closing discovered integers;
- guessing descriptor numbers;
- using FFI to close arbitrary descriptors;
- reopening guessed descriptors through `php://fd`;
- clearing the DI resolved-service cache after fork;
- eagerly resolving tagged integration services for cleanup;
- invoking arbitrary application or integration callbacks after fork;
- claiming `proc_open()` automatically isolates arbitrary descriptors;
- claiming `pcntl_exec()` closes every descriptor.

A DI-driven fork-cleanup registry is forbidden because it would require discovery or eager resolution of services and would still omit unregistered third-party resources.

## Diagnostics and redaction (MUST)

Production diagnostics MUST NOT expose:

- raw descriptor integers;
- `/proc/self/fd` listings;
- PHP resource ids;
- raw socket addresses used only for internal descriptor verification;
- inherited command descriptors;
- integration connection details;
- deployment-owned descriptor metadata.

Tests MAY inspect descriptor behavior through bounded behavioral effects, such as lock reacquisition or endpoint rebinding, but MUST NOT publish raw descriptor inventories.

## Change rules

Changing process-exec descriptor ownership, local-file open modes, Worker fork-child detachment, proc process-host ordering, or integration obligations requires updating:

```text
docs/ssot/process-exec-descriptor-safety.md
docs/adr/ADR-0032-process-exec-descriptor-safety.md
docs/adr/ADR-0017-persistent-worker-supervisor-application-worker.md
docs/architecture/worker.md
docs/ssot/runtime-container-definitions.md
framework/packages/platform/worker/README.md
```

## Cross-references

- [SSoT Index](./INDEX.md)
- [Runtime Container Definitions](./runtime-container-definitions.md)
- [Artifact Generations](./artifact-generations.md)
- [ADR-0017: Persistent worker supervisor and application worker](../adr/ADR-0017-persistent-worker-supervisor-application-worker.md)
- [ADR-0032: Process-Exec Descriptor Safety](../adr/ADR-0032-process-exec-descriptor-safety.md)
- [Worker Architecture](../architecture/worker.md)
