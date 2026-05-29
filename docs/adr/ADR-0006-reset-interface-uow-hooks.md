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

# ADR-0006: Reset interface and UoW hooks

## Status

Accepted.

## Reset failure diagnostics hardening follow-up note

Epic `1.277.0` clarifies the reset failure diagnostics boundary.

Canonical live policy is owned by:

```text
docs/ssot/uow-and-reset-contracts.md
docs/ssot/observability-and-errors.md
```

`ResetInterface` implementations MAY throw arbitrary exceptions while clearing implementation-owned mutable state.

Reset failure handling remains runtime-owned.

Foundation reset orchestration MAY preserve the original throwable as an in-process previous throwable for programmatic chaining.

Sanitized reset observability MUST NOT record or emit raw previous throwable chains.

Reset diagnostics, logs, metrics, spans, and exported observability MUST remain safe and summary-only.

## Hook payload signatures follow-up note

ADR-0020 updates the hook signature decision from parameterless hooks to normalized array payload hooks.

Canonical live hook signatures are:

```text
beforeUow(array $context): void
afterUow(array $context, array $result): void
```

`core/contracts` owns the format-neutral hook port shape.

`core/kernel` owns production and normalization of the exported hook payloads from Kernel-owned `UnitOfWorkContext` and `UnitOfWorkResult` runtime shapes.

The contracts package does not own concrete UnitOfWork runtime classes, payload normalization implementation, hook discovery, hook ordering, failure precedence, reset orchestration, or DI wiring.

## Context

Epic `1.120.0` introduces stable contracts for reset-capable services and unit-of-work lifecycle hooks under:

```text
framework/packages/core/contracts/src/Runtime/
framework/packages/core/contracts/src/Runtime/Hook/
```

Long-running runtimes such as workers, queue consumers, schedulers, long-lived CLI processes, and custom runtime loops need a stable way to prevent mutable state from leaking between units of work.

The contracts package must remain a pure library boundary.

It must not depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- framework HTTP runtime packages
- framework CLI runtime packages
- worker runtime packages
- queue vendor clients
- scheduler vendor clients
- concrete service container implementations
- concrete middleware implementations
- framework tooling packages
- generated architecture artifacts

The detailed normative policy for this ADR is defined by:

```text
docs/ssot/uow-and-reset-contracts.md
```

The existing tag registry already reserves the runtime discovery tags used by this epic:

```text
docs/ssot/tags.md
```

Relevant existing tags:

```text
kernel.reset
kernel.hook.before_uow
kernel.hook.after_uow
```

These tags are existing runtime policy, not tags introduced by `core/contracts`.

## Decision

Coretsia will introduce reset and unit-of-work hook contracts as format-neutral contracts in `core/contracts`.

The contracts introduced by epic `1.120.0` define:

- `ResetInterface`
- `BeforeUowHookInterface`
- `AfterUowHookInterface`

The contracts package defines only stable interfaces and boundary policy.

It does not implement:

- reset orchestration;
- hook execution;
- DI discovery;
- tag metadata schema;
- tag priority schema;
- worker loops;
- scheduler loops;
- queue consumer loops;
- HTTP middleware;
- CLI command behavior;
- runtime service registration;
- config defaults;
- config rules;
- generated artifacts.

## Reset interface decision

`ResetInterface` is the canonical minimal reset port for stateful services.

The canonical interface shape is:

```text
reset(): void
```

The interface intentionally has no parameters and returns `void`.

A reset-capable service owns its own mutable state and knows how to clear it.

The reset contract does not receive a unit-of-work object, request object, queue message, container reference, or runtime context.

This keeps reset behavior usable across HTTP, CLI, worker, scheduler, queue, and custom runtime boundaries without coupling `core/contracts` to any transport or runtime implementation.

Reset orchestration belongs to the Foundation runtime owner.

A Foundation reset orchestrator is expected to discover reset-capable services through the effective Foundation reset tag:

```text
kernel.reset
```

and call:

```text
ResetInterface::reset()
```

according to deterministic owner policy.

This is runtime policy, not a `core/contracts` implementation.

## UoW hook decision

Coretsia defines separate before/after unit-of-work hook interfaces as format-neutral contracts in `core/contracts`.

The contracts package owns the hook port shape:

```text
Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface
Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface
```

The canonical before hook shape is:

```text
beforeUow(array $context): void
```

The canonical after hook shape is:

```text
afterUow(array $context, array $result): void
```

The `$context` payload is the normalized exported UnitOfWork context array.

The `$result` payload is the normalized exported UnitOfWork result array.

These arrays are lifecycle hook payloads. They are not transport payloads and must remain format-neutral.

Hook interfaces intentionally do not receive:

- PSR-7 request objects;
- PSR-7 response objects;
- PSR-15 middleware objects;
- framework HTTP request objects;
- framework HTTP response objects;
- CLI input/output objects;
- queue vendor message objects;
- worker vendor context objects;
- scheduler vendor context objects;
- concrete service container objects;
- `core/kernel` runtime objects.

This preserves a format-neutral lifecycle boundary while allowing the Kernel runtime owner to provide safe normalized lifecycle payloads.

The contracts package defines only method signatures and boundary policy.

It does not define:

- concrete UnitOfWork context classes;
- concrete UnitOfWork result classes;
- hook payload production;
- hook payload normalization;
- hook discovery;
- hook execution;
- hook ordering;
- DI tags;
- tag metadata schema;
- priority semantics;
- failure precedence;
- reset behavior;
- DI wiring.

Hook payload production belongs to the Kernel runtime owner.

For the canonical Kernel implementation, `core/kernel` produces hook payloads from:

```text
Coretsia\Kernel\Runtime\UnitOfWorkContext
Coretsia\Kernel\Runtime\UnitOfWorkResult
```

and normalizes them before invoking hooks.

This implementation policy is owned by ADR-0020 and the Kernel runtime SSoT.

## No contracts-owned UoW object decision

Epic `1.120.0` introduced no contracts-owned UnitOfWork context object.

ADR-0020 later defines normalized array payload hook signatures, but it keeps concrete UnitOfWork runtime shapes out of `core/contracts`.

The contracts package must not expose:

```text
Coretsia\Kernel\Runtime\UnitOfWorkContext
Coretsia\Kernel\Runtime\UnitOfWorkResult
Coretsia\Kernel\Runtime\Outcome
Coretsia\Kernel\Runtime\UnitOfWorkType
```

The hook payload arrays are boundary payloads, not contracts-owned runtime objects.

`core/contracts` owns the hook signatures.

`core/kernel` owns the concrete payload producer and normalization implementation.

Any future change to the exported context/result payload shape must remain format-neutral and must not expose raw transport objects, raw payloads, secrets, private customer data, credentials, tokens, cookies, authorization values, raw SQL, stack traces, object dumps, or absolute local paths.

## DI tag decision

Epic `1.120.0` introduces no DI tags.

The contracts package must not declare public tag constants for:

```text
kernel.reset
kernel.hook.before_uow
kernel.hook.after_uow
```

Those tags are reserved in the tag registry and owned by runtime owner packages:

| tag                      | owner package_id  |
|--------------------------|-------------------|
| `kernel.reset`           | `core/foundation` |
| `kernel.hook.before_uow` | `core/kernel`     |
| `kernel.hook.after_uow`  | `core/kernel`     |

The contracts package may reference these tag strings in documentation as runtime policy.

It must not define competing public tag APIs, competing tag metadata keys, or competing priority semantics.

## Runtime ownership decision

Reset orchestration is runtime-owned by `core/foundation`.

Hook execution is runtime-owned by `core/kernel`.

Hook payload production and normalization for Kernel-owned UnitOfWork runtime shapes are runtime-owned by `core/kernel`.

The contracts package owns only the format-neutral hook port signatures.

The contracts package does not define the concrete execution order between:

- before-UoW hooks;
- unit-of-work processing;
- after-UoW hooks;
- reset orchestration.

Runtime owners must ensure deterministic execution and must prevent unsafe unit-of-work-local mutable state from leaking into the next unit of work.

The acceptance scenario for this decision is:

```text
worker job 1
before-UoW hooks
job processing
after-UoW hooks
reset-capable services reset
worker job 2
no stale mutable state from job 1
```

The concrete worker loop, failure handling, diagnostics, tag metadata, and service ordering are runtime-owned.

## Config and artifact decision

Epic `1.120.0` introduces no config roots and no config keys.

The contracts package must not add package config defaults or config rules for reset or UoW hooks.

Epic `1.120.0` introduces no artifacts.

The contracts package must not generate reset artifacts, hook artifacts, worker lifecycle artifacts, or runtime lifecycle artifacts.

Future runtime owners may introduce config or artifacts only through their own owner epics and the corresponding SSoT registry process.

## Observability and redaction decision

Reset and hook contracts do not define observability signals.

Runtime implementations may emit safe logs, spans, metrics, or profiling signals around reset and hook execution only when those signals follow the existing observability and profiling policy.

Diagnostics must not expose:

- `.env` values;
- passwords;
- credentials;
- tokens;
- private keys;
- cookies;
- authorization headers;
- session identifiers;
- request bodies;
- response bodies;
- raw queue messages;
- raw worker payloads;
- raw SQL;
- profile payloads;
- private customer data;
- absolute local paths.

Safe diagnostics may use owner-approved derivations such as:

```text
hash(value)
len(value)
type(value)
```

Raw values must not be printed or logged.

## Consequences

Positive consequences:

- Long-running runtimes can enforce cleanup through a stable minimal contract.
- Stateful services can expose reset behavior without depending on runtime packages.
- Hooks can be reused across HTTP, CLI, worker, scheduler, queue, and custom runtime boundaries through normalized format-neutral payload arrays.
- `core/contracts` stays dependency-light and format-neutral.
- Runtime packages retain ownership of discovery, ordering, metadata, failure policy, and integration behavior.
- Existing tag registry ownership remains intact.

Trade-offs:

- Contracts do not provide a concrete reset orchestrator.
- Contracts do not provide a concrete hook executor.
- Hooks receive normalized exported lifecycle arrays, not direct request, response, queue message, vendor runtime objects, or Kernel-owned UnitOfWork objects.
- Runtime owners must implement deterministic discovery, execution, payload production, payload normalization, and failure precedence.

## Rejected alternatives

### Put PSR-7 request/response objects in hooks

Rejected.

PSR-7 would make hooks HTTP-specific and would leak transport concerns into non-HTTP runtimes.

### Pass a generic unit-of-work context object in epic 1.120.0

Rejected.

A context object would prematurely freeze a context model before runtime owners have defined a safe cross-runtime shape.

### Put Kernel UnitOfWork objects into contracts hooks

Rejected.

Hooks must not receive Kernel-owned runtime objects such as:

```text
Coretsia\Kernel\Runtime\UnitOfWorkContext
Coretsia\Kernel\Runtime\UnitOfWorkResult
```

Those objects are implementation-owned by `core/kernel`.

Exposing them through `core/contracts` hook signatures would freeze Kernel implementation internals as public contracts and would create pressure for transport-specific data to leak into the contracts package.

The accepted follow-up design from ADR-0020 is to pass normalized exported array payloads:

```text
beforeUow(array $context): void
afterUow(array $context, array $result): void
```

The contracts package owns these format-neutral signatures only.

The Kernel runtime owner produces and normalizes the arrays from Kernel-owned UnitOfWork runtime shapes before invoking hooks.

### Put reset orchestration into contracts

Rejected.

Reset orchestration requires DI discovery, service ordering, failure handling, and runtime policy.

Those responsibilities belong to runtime owner packages, not `core/contracts`.

### Put hook execution into contracts

Rejected.

Hook execution requires discovery, ordering, metadata interpretation, and lifecycle policy.

Those responsibilities belong to `core/kernel`, not `core/contracts`.

### Introduce new DI tags in contracts

Rejected.

The needed tags already exist in `docs/ssot/tags.md`.

The contracts package is not the owner of `kernel.reset` or `kernel.hook.*`.

### Define tag priority metadata in contracts

Rejected.

Priority and metadata schemas are owner-specific runtime policy.

`core/contracts` must not define competing tag semantics for tags owned by `core/foundation` or `core/kernel`.

## Non-goals

This ADR does not implement:

- concrete reset orchestrator;
- concrete hook executor;
- DI service discovery;
- DI tag constants in `core/contracts`;
- tag metadata schema;
- tag priority schema;
- worker loop behavior;
- scheduler loop behavior;
- queue consumer behavior;
- HTTP middleware;
- CLI commands;
- reset failure handling;
- hook failure handling;
- config roots;
- config defaults;
- config rules;
- generated artifacts;
- transport-specific context objects;
- contracts-owned UnitOfWork context/result objects;
- hook payload producer implementation in `core/contracts`;
- hook payload normalization implementation in `core/contracts`.

## Related SSoT

- `docs/ssot/uow-and-reset-contracts.md`
- `docs/ssot/tags.md`
- `docs/ssot/observability.md`
- `docs/ssot/dto-policy.md`
- `docs/ssot/profiling-ports.md`

## Related ADRs

- `docs/adr/ADR-0003-observability-errordescriptor-health-profiling-ports.md`
- `docs/adr/ADR-0020-kernel-runtime-uow-spi.md`

## Related epic

- `1.120.0 Contracts: ResetInterface + UoW hooks`
