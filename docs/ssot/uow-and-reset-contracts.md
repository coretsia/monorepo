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

# UoW and Reset Contracts SSoT

## Scope

This document is the Single Source of Truth for Coretsia unit-of-work reset contracts, before/after unit-of-work hook contracts, reset discipline, runtime ownership boundaries, and DI tag usage policy.

This document governs contracts introduced by epic `1.120.0` under:

```text
framework/packages/core/contracts/src/Runtime/
framework/packages/core/contracts/src/Runtime/Hook/
```

It complements:

```text
docs/ssot/tags.md
docs/ssot/observability.md
docs/ssot/dto-policy.md
docs/ssot/profiling-ports.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Long-running runtimes must be able to enforce state cleanup between units of work using stable contracts and deterministic runtime discovery policy.

The contracts introduced by this epic define only:

- a minimal reset API for stateful services;
- a format-neutral before-UoW hook boundary;
- a format-neutral after-UoW hook boundary;
- policy linkage to existing reserved DI tags.

The contracts package MUST NOT implement reset orchestration, hook execution, DI discovery, worker loops, scheduler loops, queue consumers, HTTP middleware, CLI commands, or runtime wiring.

## Phase 0 lock-source alignment

This SSoT preserves the following Phase 0 invariants:

- `0.20.0` no-secrets output policy applies to reset and hook diagnostics.
- `0.60.0` presence-sensitive runtime behavior MUST NOT collapse distinct states when a future owner adds metadata or context around reset/hook execution.
- `0.70.0` json-like payloads, if introduced by a future owner around reset/hook diagnostics, MUST forbid floats, preserve list order, and sort map keys deterministically.
- `0.90.0` safe diagnostics MUST NOT expose raw values and MAY expose only safe derivations such as `hash(value)` or `len(value)`.

Epic `1.120.0` itself introduces no json-like payload model, no exported shape model, and no metadata schema for hook or reset tags.

## Unit of work

A unit of work is a runtime-owned execution boundary.

Examples include:

```text
HTTP request
CLI command invocation
Worker job
Queue message
Scheduler tick
Consumer batch item
Custom runtime boundary
```

The contracts package does not define a concrete unit-of-work object.

The contracts package does not define unit-of-work identity, payload, retry semantics, failure semantics, timing semantics, transport context, or result representation.

Runtime owner packages adapt their boundary-specific information to reset and hook execution without pushing transport-specific objects into `core/contracts`.

## Contract boundary

Reset and UoW hook contracts are format-neutral.

They define stable interfaces only.

The contracts package MUST NOT implement:

- reset service discovery;
- reset service ordering;
- reset orchestration;
- hook service discovery;
- hook service ordering;
- hook execution;
- worker lifecycle loops;
- scheduler lifecycle loops;
- queue consumer lifecycle loops;
- HTTP middleware;
- CLI command behavior;
- DI registration;
- generated runtime artifacts;
- config defaults;
- config rules;
- package providers.

Runtime owner packages implement concrete discovery, ordering, execution, failure handling, and integration behavior later.

## Contract package dependency policy

Reset and UoW hook contracts MUST remain dependency-free beyond PHP itself.

They MUST NOT depend on:

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
- concrete profiler implementations
- concrete logger implementations
- concrete tracing implementations
- concrete metrics implementations
- vendor-specific runtime clients
- framework tooling packages
- generated architecture artifacts

Runtime packages MAY depend on `core/contracts`.

`core/contracts` MUST NOT depend back on runtime packages.

Allowed direction:

```text
core/foundation → core/contracts
core/kernel → core/contracts
platform/http → core/contracts
platform/cli → core/contracts
worker runtime package → core/contracts
scheduler runtime package → core/contracts
queue runtime package → core/contracts
```

Forbidden direction:

```text
core/contracts → core/foundation
core/contracts → core/kernel
core/contracts → platform/http
core/contracts → platform/cli
core/contracts → worker runtime package
core/contracts → scheduler runtime package
core/contracts → queue runtime package
```

## DTO terminology boundary

This document uses the terms `contract`, `port`, and `runtime boundary` according to:

```text
docs/ssot/dto-policy.md
```

`ResetInterface`, `BeforeUowHookInterface`, and `AfterUowHookInterface` are contracts interfaces.

They are not DTO-marker classes.

DTO gates apply only to classes explicitly marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Interfaces introduced by epic `1.120.0` MUST NOT be treated as DTOs.

## Reset interface

`ResetInterface` is the canonical contracts-level reset boundary for stateful services.

The implementation path is:

```text
framework/packages/core/contracts/src/Runtime/ResetInterface.php
```

The canonical interface shape is:

```text
reset(): void
```

`reset()` resets implementation-owned mutable state after or between units of work.

A reset-capable service MAY clear:

- per-unit-of-work caches;
- buffered state;
- accumulated diagnostics;
- temporary runtime flags;
- request/job/message-local state;
- implementation-owned scoped state.

A reset-capable service MUST NOT require:

- PSR-7 request objects;
- PSR-7 response objects;
- framework HTTP request objects;
- framework HTTP response objects;
- CLI concrete input/output objects;
- queue vendor message objects;
- worker vendor context objects;
- scheduler vendor context objects;
- concrete service container objects.

`reset()` has no parameters.

`reset()` returns `void`.

`reset()` MUST NOT expose raw payloads, secrets, transport objects, or runtime implementation objects through the contract boundary.

Calling `reset()` SHOULD be safe between any two units of work handled by the same long-running runtime process.

Implementations SHOULD prefer idempotent reset behavior where practical.

Failure handling for reset implementations is runtime-owned.

The contracts package does not define reset exceptions, reset error descriptors, retry policy, fallback policy, or process termination policy.

Runtime owners that report reset failures MUST preserve the global redaction law and MUST NOT expose raw unit-of-work payloads, raw request data, raw queue messages, credentials, tokens, private customer data, or absolute local paths.

## Before-UoW hook interface

`BeforeUowHookInterface` is the canonical contracts-level hook boundary executed before a unit of work.

The implementation path is:

```text
framework/packages/core/contracts/src/Runtime/Hook/BeforeUowHookInterface.php
```

The canonical interface shape is:

```text
beforeUow(): void
```

`beforeUow()` is called by a runtime owner before the runtime begins processing a unit of work.

The hook is format-neutral.

It MUST NOT require:

- PSR-7 request objects;
- PSR-7 response objects;
- framework HTTP request objects;
- framework HTTP response objects;
- CLI concrete input/output objects;
- queue vendor message objects;
- worker vendor context objects;
- scheduler vendor context objects;
- concrete service container objects.

`beforeUow()` has no parameters.

`beforeUow()` returns `void`.

`beforeUow()` MUST NOT expose raw transport data, raw payloads, credentials, tokens, private customer data, or absolute local paths through the contract boundary.

Before-UoW hook implementations MAY initialize runtime-owned scoped state, prepare safe observability state, start profiling integration, or perform other implementation-owned preparation.

Concrete behavior belongs to runtime owner packages.

The contracts package does not define hook metadata, hook priority, hook discovery implementation, or hook failure policy.

## After-UoW hook interface

`AfterUowHookInterface` is the canonical contracts-level hook boundary executed after a unit of work.

The implementation path is:

```text
framework/packages/core/contracts/src/Runtime/Hook/AfterUowHookInterface.php
```

The canonical interface shape is:

```text
afterUow(): void
```

`afterUow()` is called by a runtime owner after the runtime finishes processing a unit of work according to owner-defined lifecycle policy.

The hook is format-neutral.

It MUST NOT require:

- PSR-7 request objects;
- PSR-7 response objects;
- framework HTTP request objects;
- framework HTTP response objects;
- CLI concrete input/output objects;
- queue vendor message objects;
- worker vendor context objects;
- scheduler vendor context objects;
- concrete service container objects.

`afterUow()` has no parameters.

`afterUow()` returns `void`.

`afterUow()` MUST NOT expose raw transport data, raw payloads, credentials, tokens, private customer data, or absolute local paths through the contract boundary.

After-UoW hook implementations MAY finalize runtime-owned scoped state, stop profiling integration, flush safe summaries, emit safe owner-defined signals, or perform other implementation-owned teardown.

Concrete behavior belongs to runtime owner packages.

The contracts package does not define hook metadata, hook priority, hook discovery implementation, hook failure policy, or whether after hooks run after failed units of work.

That behavior is runtime-owned and MUST remain deterministic within the owner implementation.

## No UoW context model in epic 1.120.0

Epic `1.120.0` intentionally introduces no unit-of-work context object.

The hook interfaces do not accept context parameters.

The reset interface does not accept context parameters.

This prevents transport-specific types from leaking into `core/contracts`.

Future owner epics MAY introduce safe context models only through their own SSoT and ADR updates.

Any future context model MUST remain format-neutral and MUST NOT expose:

- request objects;
- response objects;
- PSR-7 objects;
- queue vendor message objects;
- worker vendor context objects;
- raw headers;
- raw cookies;
- raw bodies;
- raw SQL;
- credentials;
- tokens;
- private customer data;
- absolute local paths.

## Runtime execution policy

The contracts package defines the policy expectation only.

A runtime that processes repeated units of work is expected to ensure:

1. before-UoW hooks are executed before each unit of work;
2. the unit of work is processed by the runtime owner;
3. after-UoW hooks are executed after each unit of work according to owner lifecycle policy;
4. reset-capable services are reset before mutable state can leak into the next unit of work.

The concrete orchestration order across kernel hook execution and foundation reset orchestration is runtime-owned.

Any owner implementation MUST ensure that stateful services do not carry unsafe unit-of-work-local state into the next unit of work.

Any owner implementation MUST ensure hook execution order is deterministic.

The contracts package does not define a priority schema, metadata schema, service id schema, or discovery algorithm.

## DI tag policy

Epic `1.120.0` introduces no DI tags.

The contracts package MUST NOT declare public tag constants for:

```text
kernel.reset
kernel.hook.before_uow
kernel.hook.after_uow
```

The contracts package MAY reference these tag strings in documentation as existing runtime policy.

The reserved tag names are owned by their registry owners in:

```text
docs/ssot/tags.md
```

Relevant existing reserved tags:

| tag                      | owner package_id  | contracts usage                                                     |
|--------------------------|-------------------|---------------------------------------------------------------------|
| `kernel.reset`           | `core/foundation` | existing reset-capable service discovery tag used by runtime policy |
| `kernel.hook.before_uow` | `core/kernel`     | existing before-UoW hook discovery tag used by runtime policy       |
| `kernel.hook.after_uow`  | `core/kernel`     | existing after-UoW hook discovery tag used by runtime policy        |

`core/contracts` is not the owner of these tags.

`core/contracts` MUST NOT define competing public tag APIs.

`core/contracts` MUST NOT define competing tag metadata keys.

`core/contracts` MUST NOT define competing priority semantics for these tags.

If owner metadata schema is not cemented yet, contributor packages MUST treat metadata as:

```text
meta per owner schema
```

Raw literal tag strings are allowed in docs, tests, and fixtures for readability according to the tag registry.

Runtime code MUST follow the runtime usage rule from:

```text
docs/ssot/tags.md
```

## Reset discovery policy

Reset discovery is runtime-owned by `core/foundation`.

The effective Foundation reset tag is:

```text
kernel.reset
```

A Foundation reset orchestrator is expected to discover reset-capable services through the effective Foundation reset tag and call:

```text
ResetInterface::reset()
```

on each discovered service according to deterministic owner policy.

The contracts package does not define:

- service discovery implementation;
- reset service ordering;
- reset tag metadata schema;
- reset failure aggregation;
- reset retry policy;
- reset timeout policy;
- reset diagnostics format;
- reset service registration.

## Hook discovery policy

Hook discovery is runtime-owned by `core/kernel`.

The existing before-UoW hook tag is:

```text
kernel.hook.before_uow
```

The existing after-UoW hook tag is:

```text
kernel.hook.after_uow
```

A Kernel hook executor is expected to discover hook services through these tags and call:

```text
BeforeUowHookInterface::beforeUow()
AfterUowHookInterface::afterUow()
```

according to deterministic owner policy.

The contracts package does not define:

- hook discovery implementation;
- hook service ordering;
- hook tag metadata schema;
- hook priority semantics;
- hook failure aggregation;
- hook retry policy;
- hook timeout policy;
- hook diagnostics format;
- hook service registration.

## Config policy

Epic `1.120.0` introduces no config roots and no config keys.

The contracts package MUST NOT require package config files for reset or hook contracts.

No files under package `config/` are introduced by this epic.

Future runtime owner packages MAY introduce config roots or config keys only through their own owner epics and the config roots registry process.

## Artifact policy

Epic `1.120.0` introduces no artifacts.

The contracts package MUST NOT generate reset artifacts, hook artifacts, worker lifecycle artifacts, or runtime lifecycle artifacts.

A future runtime owner MAY introduce generated artifacts only through its own owner epic and the artifact registry process.

## Observability and diagnostics policy

Reset and hook contracts do not define observability signals.

Runtime implementations MAY emit logs, spans, metrics, or profiling signals around reset and hook execution only when those signals follow:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/profiling-ports.md
```

Diagnostics MUST NOT expose:

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

Safe implementation diagnostics MAY use:

```text
hash(value)
len(value)
type(value)
```

Raw values MUST NOT be printed or logged.

Metric labels MUST remain within the canonical allowlist from:

```text
docs/ssot/observability.md
```

Epic `1.120.0` introduces no new metric label keys.

## Security and redaction

Reset and hook contracts MUST NOT require storing secrets.

Reset and hook implementations MUST NOT leak unit-of-work-local sensitive data across units of work.

Reset and hook diagnostics MUST NOT expose raw unit-of-work data.

Runtime owners MUST prefer omission over unsafe emission.

Any safe derived diagnostic MUST be deterministic and non-reversible where applicable.

## Acceptance scenario

When a long-running worker processes two jobs in the same process:

1. before-UoW hooks discovered through `kernel.hook.before_uow` run deterministically before the first job;
2. the first job is processed by the runtime owner;
3. after-UoW hooks discovered through `kernel.hook.after_uow` run deterministically after the first job according to owner lifecycle policy;
4. reset-capable services discovered through the effective Foundation reset tag `kernel.reset` are reset before unsafe state can leak into the second job;
5. before-UoW hooks run deterministically before the second job;
6. the second job observes no stale unit-of-work-local mutable state from the first job.

This acceptance scenario is policy intent.

The concrete worker loop, tag discovery, hook executor, reset orchestrator, error handling, and diagnostics are runtime-owned.

## Verification evidence

Contracts-level enforcement evidence for this epic includes:

```text
framework/packages/core/contracts/tests/Contract/ResetInterfaceIsMinimalContractTest.php
framework/packages/core/contracts/tests/Contract/HookInterfacesDoNotDependOnPlatformTest.php
```

These tests are expected to verify:

- `ResetInterface` exists and exposes only `reset(): void`;
- `BeforeUowHookInterface` exists and exposes only `beforeUow(): void`;
- `AfterUowHookInterface` exists and exposes only `afterUow(): void`;
- hook interfaces do not depend on platform packages;
- hook interfaces do not depend on integrations packages;
- hook interfaces do not depend on `Psr\Http\Message\*`;
- reset and hook contracts stay format-neutral.

Architecture gates are expected to verify that `core/contracts` does not introduce forbidden compile-time dependencies.

## Non-goals

This SSoT does not define:

- concrete reset orchestrator implementation;
- concrete hook executor implementation;
- DI service discovery implementation;
- DI tag constants in `core/contracts`;
- tag metadata schema;
- tag priority schema;
- worker loop implementation;
- scheduler loop implementation;
- queue consumer implementation;
- HTTP middleware implementation;
- CLI command behavior;
- reset failure handling policy;
- hook failure handling policy;
- config roots;
- config defaults;
- config rules;
- generated artifacts;
- profiling implementation;
- tracing implementation;
- metrics implementation;
- logging backend implementation;
- transport-specific context objects.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Tag Registry](./tags.md)
- [Observability Naming and Labels Allowlist](./observability.md)
- [DTO Policy](./dto-policy.md)
- [Profiling Ports SSoT](./profiling-ports.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
