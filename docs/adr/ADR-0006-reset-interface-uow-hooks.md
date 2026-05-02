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

Coretsia will introduce separate before/after unit-of-work hook interfaces.

The canonical before hook shape is:

```text
beforeUow(): void
```

The canonical after hook shape is:

```text
afterUow(): void
```

The hook interfaces intentionally have no parameters and return `void`.

Hooks do not receive:

- PSR-7 request objects;
- PSR-7 response objects;
- framework HTTP request objects;
- framework HTTP response objects;
- CLI input/output objects;
- queue vendor message objects;
- worker vendor context objects;
- scheduler vendor context objects;
- concrete service container objects.

This preserves a format-neutral lifecycle boundary.

Runtime owners may adapt boundary-specific state into implementation-owned services before invoking hooks, but that state must not become part of the contracts interface.

Hook discovery and execution belong to the Kernel runtime owner.

The Kernel is expected to discover hook services through:

```text
kernel.hook.before_uow
kernel.hook.after_uow
```

and execute them according to deterministic owner policy.

This is runtime policy, not a `core/contracts` implementation.

## No UoW context object decision

Epic `1.120.0` introduces no unit-of-work context model.

A context object is deliberately excluded because any first context shape would risk prematurely encoding HTTP, CLI, worker, queue, scheduler, or vendor-specific assumptions into `core/contracts`.

Future owner epics may introduce safe context models only through their own SSoT and ADR updates.

Any future context model must remain format-neutral and must not expose raw transport objects, raw payloads, secrets, private customer data, or absolute local paths.

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
- Hooks can be reused across HTTP, CLI, worker, scheduler, queue, and custom runtime boundaries.
- `core/contracts` stays dependency-light and format-neutral.
- Runtime packages retain ownership of discovery, ordering, metadata, failure policy, and integration behavior.
- Existing tag registry ownership remains intact.

Trade-offs:

- Contracts do not provide a concrete reset orchestrator.
- Contracts do not provide a concrete hook executor.
- Hooks cannot receive direct request, response, queue message, or unit-of-work context arguments.
- Runtime owners must implement deterministic discovery and execution later.

## Rejected alternatives

### Put PSR-7 request/response objects in hooks

Rejected.

PSR-7 would make hooks HTTP-specific and would leak transport concerns into non-HTTP runtimes.

### Pass a generic unit-of-work context object in epic 1.120.0

Rejected.

A context object would prematurely freeze a context model before runtime owners have defined a safe cross-runtime shape.

### Pass UoW type, context, and result arrays into hooks

Rejected.

Passing `string $uowType`, `array $context`, or `array $result` would introduce
a contracts-level unit-of-work context/result schema in epic `1.120.0`.

That would prematurely freeze UoW type vocabulary, result semantics, json-like
payload policy, redaction requirements, and failure handling behavior.

The hook contracts intentionally remain parameterless. Runtime owners may adapt
boundary-specific state through implementation-owned services, but that state
must not become part of the `core/contracts` hook interface in this epic.

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
- transport-specific context objects.

## Related SSoT

- `docs/ssot/uow-and-reset-contracts.md`
- `docs/ssot/tags.md`
- `docs/ssot/observability.md`
- `docs/ssot/dto-policy.md`
- `docs/ssot/profiling-ports.md`

## Related epic

- `1.120.0 Contracts: ResetInterface + UoW hooks`
