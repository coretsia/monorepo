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

# Worker Process Bootstrap SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: platform/worker
```

This document is the Single Source of Truth for initial Worker child-process trust, bootstrap ownership, bootstrap authority, startup concurrency barriers, descriptor closure, and bootstrap failure containment for the package-internal Guardian and ProcHost processes.

It does not define the Worker control protocol, worker-child readiness protocol, per-worker ProcHost handoff protocol, public Worker error taxonomy, Kernel runtime boot, or deployment restart policy.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Process roles and ownership

```text
Supervisor
    owns Guardian bootstrap endpoint
    owns observation of CLAIM ACK
    does not own WorkerLifecycleLock

Guardian
    owns ProcHost bootstrap endpoint
    owns WorkerLifecycleLock after successful CLAIM acquisition
    owns nested ProcHost lifetime
    owns generation cleanup

ProcHost
    owns PROC worker process resources
    is subordinate to authenticated Guardian ownership

Worker
    owns process-bootstrap protocol semantics

Foundation
    owns deterministic Stable JSON primitives
```

`WorkerLifecycleLock` remains the sole worker-generation ownership and fencing authority. Initial bootstrap authentication proves only possession of the fresh child-specific bootstrap capability; it does not create generation ownership.

## Executable composition ownership

The Guardian and ProcHost executable files are package-owned OS entry shells:

```text
bin/coretsia-worker-guardian
bin/coretsia-worker-proc-host
```

They own only:

```text
executable invocation validation
Composer autoload
loading the package-owned source composition module
invocation of that module
terminal process exit status
```

Post-autoload process composition is owned by:

```text
src/Process/Entrypoint/worker-guardian.php
src/Process/Entrypoint/worker-proc-host.php
```

The source composition modules are part of the Worker package-internal source ownership boundary and MAY consume package-internal `@internal` Worker implementation.

The `bin/*` executable shells MUST NOT:

```text
remove @internal from package implementation to satisfy tooling
expose Bootstrap, Guardian, or ProcHost implementation as public API
introduce a public entrypoint facade solely for executable composition
directly consume package-internal PSR-4 implementation classes across the executable/source-root boundary
```

Named classes under the PSR-4 `src/` root MUST reside in matching PSR-4 files and remain package-internal where applicable.

This composition boundary does not alter runtime authority:

```text
Supervisor
    owns Guardian bootstrap endpoint
    ↓
authenticated Guardian
    ↓
CLAIM
    ↓
WorkerLifecycleLock::acquire()
    =
generation ownership

Guardian
    owns ProcHost bootstrap endpoint
    ↓
authenticated ProcHost
    =
subordinate worker-process ownership
```

It also does not alter the descriptor barrier:

```text
Composer autoload
↓
receive + validate private bootstrap frame
↓
close private bootstrap stdin
↓
only then create a descendant process capable of inheriting descriptors
```

## Stable JSON code ownership

Stable JSON ownership means code ownership, not DI ownership:

```text
Foundation owns:
    StableJsonEncoder
    StableJsonDecoder
    JsonLikeNormalizer

Worker consumes:
    canonical static API
```

`StableJsonEncoder` and `StableJsonDecoder` are Foundation-owned stateless deterministic primitives. They are not required Worker runtime services and MUST NOT be registered in the Foundation runtime graph solely for Worker process bootstrap.

Worker owns only the bounded process-bootstrap schema and process semantics layered on top of the Foundation static primitives.

## Bootstrap component responsibilities

```text
WorkerProcessBootstrapProtocol
    exact bounded wire schema
    canonical Stable JSON encoding/decoding
    role and field validation

WorkerProcessBootstrapEndpoint
    retained parent listener
    fresh one-shot credential
    non-blocking candidate admission
    bounded candidate authentication

WorkerProcessBootstrapClient
    child-side bootstrap stdin receive
    bootstrap stdin closure
    authenticated back-connect

WorkerProcessBootstrapLauncher
    proc_open exact child
    private bootstrap stdin ownership
    Endpoint creation after child launch
    launch-frame publication
    pre-auth direct-child cleanup
    atomic authenticated launch result
```

`WorkerProcessBootstrapFailure` is a redacted package-internal bootstrap failure boundary. It is not a public Worker runtime component and is not part of the package-level `WorkerException` taxonomy.

Only `WorkerProcessBootstrapLauncher` writes child bootstrap stdin.

`WorkerProcessBootstrapEndpoint` MUST NOT launch child processes and MUST NOT write child stdin.

Bootstrap classes MUST NOT acquire `WorkerLifecycleLock`.

## Authority distinction

```text
bootstrap authentication
    =
possession of a fresh one-shot capability
delivered exclusively through the exact child's private launch channel

WorkerLifecycleLock
    =
worker-generation ownership and fence
```

Therefore:

```text
authenticated Guardian
!=
generation-owned Guardian
```

Generation ownership begins inside Guardian only after:

```text
valid CLAIM
↓
WorkerLifecycleLock::acquire()
```

The Guardian commit point is successful `WorkerLifecycleLock::acquire()`.

Supervisor treats generation ownership as successfully observed only after:

```text
valid CLAIM ACK received and validated
```

The Supervisor observation point is a successfully received and validated `CLAIM ACK`.

A missing or lost `CLAIM ACK` does not prove that the Guardian failed to acquire `WorkerLifecycleLock`.

PID is process-management metadata only. Bootstrap authentication MUST NOT be documented as OS-level PID attestation.

## Canonical launch concurrency

`proc_open()` is the parent-side operation that creates the exact child process.

After successful `proc_open()`, parent and child execute concurrently. This SSoT therefore does not impose an artificial total ordering between parent post-launch bootstrap-endpoint preparation and child process startup, Composer autoload, or waiting on bootstrap stdin.

The normative requirements are causal ownership barriers.

For each initial bootstrap:

```text
proc_open(child)
MUST happen before
parent bootstrap listener creation
```

and:

```text
parent retained listener creation
MUST happen before
bootstrap frame publication
```

Child-side:

```text
bootstrap frame receive + validation
↓
bootstrap stdin close
```

MUST complete before any descendant process launch that could inherit the bootstrap descriptor.

Composer autoload MAY run child-side before bootstrap-frame consumption.

## Canonical startup sequence — Supervisor to PCNTL Guardian

```text
Supervisor lane                         Guardian lane

proc_open(Guardian)
    │
    ├────────────────────────────────→ process starts
    │                                  Composer autoload
    │                                  wait/read bootstrap stdin
    │
create retained Guardian listener
    │
create fresh Guardian credential
    │
publish bounded bootstrap frame ─────→ receive + validate bootstrap frame
                                       ↓
                                       close bootstrap stdin
                                       ↓
                                       connect to retained Supervisor listener
                                       ↓
                                       send Guardian authentication frame
    │
authenticate candidate ←──────────────┘
    │
authenticated Supervisor↔Guardian channel
    │
CLAIM ───────────────────────────────→ WorkerLifecycleLock::acquire()
    │                                  ↓
    │                                  generation ownership established
    │                                  ↓
CLAIM ACK ←───────────────────────────┘
    │
Supervisor observes successful claim
```

Only after bootstrap stdin closure may this Guardian later perform a PCNTL worker fork/exec.

Normative barriers:

```text
proc_open(Guardian)
BEFORE
Guardian bootstrap listener creation

Guardian bootstrap listener retained
BEFORE
Guardian bootstrap frame publication

Guardian bootstrap stdin closed
BEFORE
any PCNTL worker fork
```

## Canonical startup sequence — Supervisor to PROC Guardian

```text
Supervisor lane                         Guardian lane

proc_open(Guardian)
    │
    ├────────────────────────────────→ process starts
    │                                  Composer autoload
    │                                  wait/read bootstrap stdin
    │
create retained Guardian listener
    │
create fresh Guardian credential
    │
publish bounded bootstrap frame ─────→ receive + validate bootstrap frame
                                       ↓
                                       close bootstrap stdin
                                       ↓
                                       launch + authenticate ProcHost
                                       ↓
                                       only after ProcHost bootstrap succeeds:
                                       connect to retained Supervisor listener
                                       ↓
                                       send Guardian authentication frame
    │
authenticate candidate ←──────────────┘
    │
authenticated Supervisor↔Guardian channel
    │
CLAIM ───────────────────────────────→ WorkerLifecycleLock::acquire()
    │                                  ↓
    │                                  generation ownership established
    │                                  ↓
CLAIM ACK ←───────────────────────────┘
```

Normative barriers:

```text
proc_open(Guardian)
BEFORE
Guardian bootstrap listener creation

Guardian bootstrap listener retained
BEFORE
Guardian bootstrap frame publication

Guardian bootstrap stdin closed
BEFORE
ProcHost launch

ProcHost bootstrap and authentication completed
BEFORE
Guardian establishes authenticated Supervisor connection

WorkerLifecycleLock acquisition
AFTER
authenticated Guardian↔Supervisor channel exists
```

A PROC Guardian MUST complete Guardian bootstrap-stdin closure and ProcHost bootstrap/authentication before establishing the authenticated Guardian-to-Supervisor runtime connection.

This ordering prevents the ProcHost from inheriting the Guardian bootstrap stdin, the authenticated Guardian-to-Supervisor runtime connection, or the generation-fence descriptor.

## Canonical startup sequence — Guardian to ProcHost

```text
Guardian lane                           ProcHost lane

proc_open(ProcHost)
    │
    ├────────────────────────────────→ process starts
    │                                  Composer autoload
    │                                  wait/read bootstrap stdin
    │
create retained ProcHost listener
    │
create fresh ProcHost credential
    │
publish bounded bootstrap frame ─────→ receive + validate bootstrap frame
                                       ↓
                                       close bootstrap stdin
                                       ↓
                                       connect to retained Guardian listener
                                       ↓
                                       send ProcHost authentication frame
    │
authenticate candidate ←──────────────┘
    │
authenticated Guardian↔ProcHost connection
    │
ProcHost runtime protocol begins
```

Normative barriers:

```text
proc_open(ProcHost)
BEFORE
ProcHost bootstrap listener creation

ProcHost bootstrap listener retained
BEFORE
ProcHost bootstrap frame publication

ProcHost bootstrap stdin closed
BEFORE
any worker proc_open()
```

The authority guarantee depends on the exact child being created first, the listener being created and continuously retained only after that launch, the capability being delivered only through the exact child's private stdin, bootstrap stdin being closed before descendant creation, and the child authenticating back to the continuously parent-owned listener.

### Platform listener ownership

The retained bootstrap listener MUST prevent another local socket from binding the same loopback address and port while bootstrap authority remains active.

On POSIX, the canonical stream-server listener provides the required retained ownership semantics.

On Windows, initial Guardian and ProcHost bootstrap requires the sockets extension and `SO_EXCLUSIVEADDRUSE`. The package MUST create the listening socket with exclusive address use before exporting it to the stream API used by the common bootstrap endpoint.

```text
Windows initial bootstrap
    -> ext-sockets available
    -> SO_EXCLUSIVEADDRUSE available
    -> exclusive loopback bind
    -> export listening socket as stream
    -> common bounded bootstrap protocol
```

Absence of this capability makes secure Worker process bootstrap unavailable on Windows. The implementation MUST fail closed rather than fall back to a non-exclusive listener.

This section defines the requirement for initial Worker process bootstrap. The per-worker ProcHost handoff and worker-child readiness protocols remain separate boundaries, but their retained listeners use the same Windows exclusive-address-ownership primitive under their owning contracts. This does not change the existing per-worker ProcHost handoff descriptor-isolation sequence.

## Candidate admission and authentication

The retained bootstrap listener:

```text
is non-blocking
remains continuously owned until successful authentication or terminal failure
```

Unauthenticated candidate sockets:

```text
are non-blocking
are bounded in count
have independently bounded frame buffers
```

Authentication is not `first connection wins`.

Canonical handling is:

```text
accept candidate
↓
bounded incremental authentication-frame read
↓
validate protocol version
↓
validate expected role
↓
validate credential shape
↓
hash_equals(expected credential, candidate credential)
↓
invalid:
    close candidate only
    keep retained listener
    continue within the same overall deadline

valid:
    close other candidates
    close retained listener
    invalidate one-shot bootstrap state
    return authenticated connection
```

Normative requirements:

```text
oversized candidate MUST be evicted
invalid candidate MUST be evicted
expired or non-progressing candidate MUST be evicted
candidate admission and eviction policy MUST be deterministic and package-owned
pending candidate count MUST be bounded
candidate handling MUST NOT extend the overall startup deadline
```

Unlimited privileged local connection flooding is outside the documented availability guarantee.

## Credential domains

The following authority credentials are distinct and MUST NOT be reused:

```text
Supervisor control credential
!= Guardian bootstrap credential
!= ProcHost bootstrap credential
!= ProcHost handoff credential
!= worker child readiness credential
```

Every Guardian and ProcHost initial bootstrap receives an independent fresh credential.

## One-shot lifecycle

```text
fresh 256-bit credential
↓
private child delivery
↓
one successful authentication
↓
retained listener closes
↓
remaining candidates close
↓
bootstrap capability state invalidated
```

Bootstrap credentials MUST NOT appear in:

```text
argv
environment
diagnostics
public error payload
lifecycle locator
worker state
CLI output
logs, spans, or metrics
```

Parent MUST NOT send a bootstrap credential to an unauthenticated network peer.

## Descriptor inheritance boundary

Composer autoload is allowed before Worker bootstrap consumption.

The normative rule is:

```text
Guardian bootstrap stdin
MUST close before:
    ProcHost launch
    any PCNTL worker fork

ProcHost bootstrap stdin
MUST close before:
    any worker proc_open()
```

No Worker initial-bootstrap descriptor may survive into a descendant process.

Existing per-worker ProcHost handoff remains a separate descriptor-isolation mechanism and is not replaced by initial bootstrap:

```text
Guardian owns retained handoff listener
↓
authenticated Guardian→ProcHost channel delivers handoff capability
↓
ProcHost closes current Guardian connection
↓
proc_open(worker child)
↓
ProcHost reconnects to retained handoff listener
```

This SSoT does not claim arbitrary third-party descriptor isolation. Repository-wide descriptor ownership obligations remain governed by `docs/ssot/process-exec-descriptor-safety.md`.

## Startup deadline

Supervisor-to-Guardian startup uses one monotonic overall deadline covering:

```text
Guardian process launch
bootstrap publication
Guardian authentication
CLAIM and WorkerLifecycleLock acquisition
CLAIM ACK observation
```

Each parent-side phase receives only the remaining overall startup budget.

Initial bootstrap-frame publication and delivery remain bounded by the parent-side `WorkerProcessBootstrapLauncher` deadline.

After Guardian successfully receives and validates its bootstrap frame, the propagated `timeout_ms` represents only the remaining parent startup budget at publication time.

Guardian creates its local monotonic deadline from that propagated remaining budget for ProcHost startup when applicable and Supervisor authentication.

ProcHost receives only the remaining Guardian startup budget at the moment its own bootstrap frame is published.

No nested phase receives a fresh full startup timeout.

## Pre-auth failure atomicity

Before successful bootstrap authentication, `WorkerProcessBootstrapLauncher` owns direct-child cleanup.

Launch returns exactly one of:

```text
process + pid + authenticated connection

or

failure with no partial authenticated session
```

On failure Launcher closes the retained listener, bootstrap stdin, candidate sockets, and any not-yet-transferred authenticated connection, then gives the direct child bounded self-cleanup grace and MAY terminate and reap that exact direct child if required.

For a PROC Guardian, child-side cleanup after Guardian bootstrap receive owns the already-authenticated nested ProcHost. If Supervisor bootstrap authentication never completes and the direct Guardian is terminated by the pre-auth Launcher fallback, ProcHost owner-loss cleanup MUST prevent an orphan process host.

No generation fence is acquired before successful Guardian bootstrap authentication and a valid `CLAIM`.

## Post-auth and pre-observed-CLAIM-ACK failure atomicity

After Guardian bootstrap authentication, Supervisor `claimed` state remains false until Supervisor successfully observes a valid `CLAIM ACK`.

If `CLAIM` does not produce an observed valid ACK, the claim outcome may be unknown because Guardian may already have acquired `WorkerLifecycleLock`.

Supervisor MUST:

```text
keep claimed=false
close authenticated Guardian connection
allow Guardian-owned terminal cleanup
wait boundedly for Guardian exit
```

Supervisor MUST NOT treat missing ACK as proof that `WorkerLifecycleLock` was not acquired.

Supervisor MUST NOT force-kill a potentially generation-owning Guardian as a local rollback merely because ACK was not observed.

If Guardian does not complete cleanup, catastrophic Guardian or service-unit containment remains external.

## Claim acknowledgement ambiguity

The canonical uncertain-commit window is:

```text
Guardian receives valid CLAIM
↓
Guardian acquires WorkerLifecycleLock
↓
Guardian emits or attempts CLAIM ACK
        X
Supervisor never observes valid ACK
```

In this state:

```text
Supervisor:
    claimed=false

Guardian:
    may already own generation fence
```

Guardian MUST preserve the fence through cleanup:

```text
terminate and reap workers
↓
shutdown ProcHost
↓
close owned generation resources
↓
release WorkerLifecycleLock LAST
↓
exit
```

ACK write failure after successful lock acquisition is terminal supervisor-loss cleanup, not rollback of generation ownership.

## ProcHost owner-loss cleanup

The authenticated Guardian connection is ProcHost's owner channel.

Outside intentional per-worker handoff:

```text
unexpected Guardian connection EOF or loss
↓
terminal owner loss
↓
terminate and reap every owned worker child
↓
close owned process resources
↓
exit non-zero
```

During the existing per-worker handoff, intentional old-connection close is allowed because the handoff protocol owns the replacement connection transition.

Failure to establish or authenticate the replacement handoff connection after child creation is terminal owner-loss-equivalent failure:

```text
cleanup every registered child
↓
exit non-zero
```

This rule applies during startup and established runtime.

## Generation-fence authority

```text
WorkerLifecycleLock
```

remains the sole generation fence.

Bootstrap authentication does not acquire generation ownership.

ProcHost does not acquire generation ownership.

If Guardian owns `WorkerLifecycleLock`, it MUST NOT release the fence until every owned worker is terminated, reaped, and closed and the nested ProcHost has completed required cleanup or shutdown.

Before successful `CLAIM` and `WorkerLifecycleLock` acquisition, bounded failure cleanup MAY forcibly terminate the candidate ProcHost because generation ownership has not yet been acquired and worker spawn is not available through that Guardian.

After `WorkerLifecycleLock` acquisition, Guardian MUST NOT use hard termination of ProcHost as evidence that ProcHost-owned workers have been terminated, reaped, and closed. A ProcHost shutdown timeout or owner-channel failure during generation-owned cleanup MUST remain fail-closed: Guardian retains `worker.lock` until the nested ProcHost has completed its terminal cleanup and exited. If that cleanup cannot be confirmed, the generation fence remains held; external service-unit containment is the catastrophic escape boundary.

Generation-fence release remains the final generation-cleanup action.

## Forbidden designs

```text
reserve -> close -> child rebind
bootstrap credential in argv
bootstrap credential in environment
parent sends credential to first TCP peer
first connection wins
PID equality as sole identity proof
runtime HELLO as initial process identity mechanism
credential reuse between bootstrap domains
bootstrap stdin surviving into descendants
fresh timeout budget for every nested startup phase
Supervisor treating missing CLAIM ACK as proof that no lock exists
Supervisor force-killing a potentially generation-owning Guardian as local rollback
ProcHost surviving unexpected Guardian owner loss in detached mode
```

Worker process bootstrap MUST NOT move into Kernel merely because it is executable startup logic. Kernel does not own Guardian, ProcHost, or Worker-specific `proc_open()` topology.

Worker process bootstrap also remains outside Foundation. Foundation owns reusable deterministic serialization primitives; Worker owns the process-bootstrap protocol and process topology.

## Threat boundary

The mechanism protects against:

```text
initial loopback endpoint substitution
reserve/rebind TOCTOU
credential disclosure to unauthenticated endpoint
wrong-role candidate
wrong-credential candidate
credential-domain reuse
bootstrap descriptor inheritance
single silent candidate starvation
partial startup ownership leakage
lost CLAIM ACK split-brain
orphan ProcHost after Guardian owner loss
```

It does not claim protection from an attacker able to:

```text
read arbitrary Worker process memory
debug or instrument the exact child
steal arbitrary OS handles with elevated privilege
replace package-owned binaries
fully compromise the operating-system account
```

Whole-unit or catastrophic Guardian containment remains the external service-manager boundary.

## Change rules

Changing initial Guardian or ProcHost bootstrap trust, bootstrap credential delivery, startup deadline propagation, descriptor closure before descendant creation, CLAIM acknowledgement authority semantics, ProcHost owner-loss cleanup, or generation-fence release ordering requires updating:

```text
docs/ssot/worker-process-bootstrap.md
docs/ssot/process-exec-descriptor-safety.md
docs/ssot/runtime-container-definitions.md
docs/adr/ADR-0017-persistent-worker-supervisor-application-worker.md
docs/adr/ADR-0032-process-exec-descriptor-safety.md
docs/architecture/worker.md
framework/packages/platform/worker/README.md
```

## Cross-references

- [SSoT Index](./INDEX.md)
- [Process-Exec Descriptor Safety](./process-exec-descriptor-safety.md)
- [Runtime Container Definitions](./runtime-container-definitions.md)
- [Worker Task Sources SSoT](./worker-task-sources.md)
- [ADR-0017: Persistent worker supervisor and application worker](../adr/ADR-0017-persistent-worker-supervisor-application-worker.md)
- [ADR-0032: Process-Exec Descriptor Safety](../adr/ADR-0032-process-exec-descriptor-safety.md)
- [Worker Architecture](../architecture/worker.md)
