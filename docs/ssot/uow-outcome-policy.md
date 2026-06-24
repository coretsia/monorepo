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

# UnitOfWork Outcome Policy SSoT

## Scope

This document is the Single Source of Truth for Coretsia Kernel UnitOfWork lifecycle policy, after-phase reset discipline, outcome token vocabulary, HTTP outcome mapping, CLI outcome mapping, failure precedence, and safe result metadata policy.

This document governs policy for:

```text
Coretsia\Kernel\Runtime\Outcome
Coretsia\Kernel\Runtime\UnitOfWorkResult
```

The implementation paths are:

```text
framework/packages/core/kernel/src/Runtime/Outcome.php
framework/packages/core/kernel/src/Runtime/UnitOfWorkResult.php
```

The future runtime lifecycle executor owner is:

```text
core/kernel
```

The canonical Foundation reset executor referenced by this policy is:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

This document complements:

```text
docs/ssot/uow-shapes.md
docs/ssot/uow-and-reset-contracts.md
docs/ssot/reset-tags.md
docs/ssot/runtime-drivers.md
docs/ssot/context-lifecycle.md
docs/ssot/stateful-services.md
docs/ssot/error-descriptor.md
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/dto-policy.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Canonical authority

`core/kernel` owns UnitOfWork outcome policy.

This document is the canonical human-readable policy reference for:

- lifecycle phase ordering;
- after-phase reset discipline;
- exactly-once reset invariant after after-phase entry;
- outcome token vocabulary;
- HTTP outcome mapping;
- CLI outcome mapping;
- result extension safety rules related to outcome/lifecycle data.

`docs/ssot/uow-shapes.md` owns the structure and field-level shape rules for:

```text
UnitOfWorkContext
UnitOfWorkResult
```

This document owns how outcome values are selected and how lifecycle completion must be ordered.

Runtime code, platform adapters, tests, generated artifacts, and documentation MUST treat this document as the canonical outcome mapping authority.

## Ownership

The owner package is:

```text
core/kernel
```

The package path is:

```text
framework/packages/core/kernel/
```

The Composer package is:

```text
coretsia/core-kernel
```

The module id is:

```text
core.kernel
```

The config root is:

```text
kernel
```

## Package dependency policy

`core/kernel` MAY depend on:

```text
core/contracts
core/foundation
```

`core/kernel` MUST NOT depend on:

```text
platform/*
integrations/*
Psr\Http\Message\*
Psr\Http\Server\*
```

Outcome policy MUST remain format-neutral.

The Kernel outcome vocabulary MUST NOT depend on:

- PSR-7 request objects;
- PSR-7 response objects;
- PSR-15 middleware or handler objects;
- Symfony HTTP objects;
- CLI command objects;
- queue message objects;
- scheduler job objects;
- transport-specific response objects;
- container objects;
- service instances;
- runtime wiring objects.

## Policy purpose

A UnitOfWork outcome is a stable completion category for one runtime unit of work.

The outcome answers:

```text
Did this UnitOfWork complete successfully, complete with a handled error, or fail fatally?
```

Outcome is not:

- an HTTP status code;
- a CLI exit code;
- an exception class name;
- a log level;
- an error descriptor severity;
- a transport response;
- a retry policy;
- a process supervision decision;
- a generated artifact status.

Transport adapters MAY derive outcome from transport-specific completion data, but the exported result shape remains Kernel-owned.

## Outcome token vocabulary

The canonical UnitOfWork outcome values are:

```text
success
handled_error
fatal_error
```

The values are stable lowercase ASCII tokens.

The values MUST be compared byte-for-byte.

The values MUST NOT be translated, localized, title-cased, or vendor-mapped.

No other outcome value is canonical in this epic.

Adding a new outcome token requires direct update of this SSoT and the corresponding contract tests.

## Outcome meaning

| outcome         | Meaning                                                                 |
|-----------------|-------------------------------------------------------------------------|
| `success`       | The UnitOfWork completed without a transport/application error outcome. |
| `handled_error` | The UnitOfWork completed with an expected/handled error result.         |
| `fatal_error`   | The UnitOfWork terminated through an uncaught exception/fatal failure.  |

`handled_error` means the runtime owner produced a controlled completion result.

`fatal_error` means the runtime owner observed an uncaught exception or equivalent fatal boundary failure.

`fatal_error` MUST take precedence over status-code or exit-code based mapping.

## Lifecycle phases

The canonical conceptual lifecycle is:

```text
beginUow()
before_uow hooks
run external runtime (http/cli/queue/scheduler/...)
after_uow hooks
ResetOrchestrator.resetAll()
endUoW()
```

The single-choice reset trigger position is:

```text
after hooks → ResetOrchestrator.resetAll() → endUoW
```

This ordering is canonical.

Runtime implementations MUST NOT move reset before after hooks.

Runtime implementations MUST NOT move `endUoW()` before reset.

Runtime implementations MUST NOT omit reset after after-phase entry.

## Begin invariant

When a UnitOfWork is started, the runtime owner MUST create or derive a `UnitOfWorkContext` with:

```text
uowId
type
startedAt
correlationId
attributes
```

The shape, field meanings, json-like constraints, and export rules are governed by:

```text
docs/ssot/uow-shapes.md
```

The context MUST be format-neutral.

The context MUST NOT expose transport objects.

The context MUST NOT expose secrets, PII, raw payloads, raw SQL, authorization data, cookies, session ids, or stack traces.

## Before-hook invariant

Before hooks run after the UnitOfWork context exists and before the external runtime work is executed.

Before-hook consumers MUST receive a normalized exported context array derived from `UnitOfWorkContext`.

No object instance MAY cross the hook/export boundary.

Forbidden boundary values include:

- `UnitOfWorkContext` object;
- request object;
- response object;
- PSR-7 object;
- CLI command object;
- queue message object;
- service instance;
- closure;
- resource.

Before-hook execution order is runtime-owned and MUST be deterministic when implemented.

## Runtime execution invariant

The external runtime work is transport-owned or adapter-owned.

Examples:

```text
HTTP request
CLI command invocation
queue message
scheduler tick
worker job
```

Kernel outcome policy does not require Kernel to build transport responses.

Kernel outcome policy does not require Kernel to know HTTP response classes, CLI command classes, queue message classes, or scheduler job classes.

Transport adapters derive safe completion facts and map them into Kernel outcome tokens using this SSoT.

## After-hook invariant

After hooks run after the external runtime work has produced a completion result or after the runtime owner has captured a fatal failure.

After-hook consumers MUST receive a normalized exported result array derived from `UnitOfWorkResult`.

No object instance MAY cross the hook/export boundary.

Forbidden boundary values include:

- `UnitOfWorkResult` object;
- `ErrorDescriptor` object;
- Throwable object;
- request object;
- response object;
- PSR-7 object;
- CLI command object;
- queue message object;
- service instance;
- closure;
- resource.

After-hook execution order is runtime-owned and MUST be deterministic when implemented.

## Reset discipline invariant

`core/foundation` owns reset discovery and reset orchestration mechanics.

The canonical reset executor name is:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

The typo `ResetOrcestrator` is invalid and MUST NOT be introduced in docs, code, tests, or generated artifacts.

This policy cements only the Kernel lifecycle invariant:

```text
after hooks → ResetOrchestrator.resetAll() → endUoW
```

Once the after-phase is entered, `ResetOrchestrator.resetAll()` MUST run exactly once before `endUoW()`.

This exactly-once reset requirement applies even if an after-uow hook throws.

The next UnitOfWork MUST start clean.

`core/kernel` runtime is the trigger point owner for this lifecycle in the future runtime executor epic.

`1.270.0` defines this policy and its contract evidence only.

Runtime lifecycle implementation is introduced by a later Kernel runtime epic.

## Reset tag boundary

This epic MUST NOT introduce reset DI tag identifier constants.

Framework-reserved reset and hook tag identifier constants are declared only in:

```text
Coretsia\Foundation\Tag\ReservedTags
```

This epic MUST NOT depend on reset tag naming.

This epic MUST NOT reference `TagRegistry` enumeration logic.

Reset discovery remains owned by `core/foundation`.

Reset tag naming and tag ownership remain governed by:

```text
docs/ssot/tags.md
docs/ssot/reset-tags.md
```

The Kernel lifecycle policy may reference `ResetOrchestrator.resetAll()` as the canonical reset trigger action.

It MUST NOT duplicate Foundation reset discovery implementation details.

## End invariant

`endUoW()` is the logical completion point after reset has run.

`endUoW()` MUST NOT occur before:

```text
ResetOrchestrator.resetAll()
```

The runtime owner MAY perform implementation-owned cleanup after `endUoW()` only if it does not invalidate the canonical UnitOfWork result or leak state into the next UnitOfWork.

## Exactly-once rule

For each UnitOfWork whose after-phase is entered:

```text
ResetOrchestrator.resetAll()
```

MUST be called exactly once.

Invalid behavior:

```text
after hooks → endUoW()
```

Invalid behavior:

```text
after hooks → ResetOrchestrator.resetAll() → ResetOrchestrator.resetAll() → endUoW()
```

Invalid behavior:

```text
after hooks → after-hook throws → endUoW()
```

Valid behavior:

```text
after hooks → ResetOrchestrator.resetAll() → endUoW()
```

Valid behavior when an after hook throws:

```text
after hooks begin
after hook throws
ResetOrchestrator.resetAll()
endUoW()
runtime reports/propagates failure according to owner policy
```

The reporting or propagation of the after-hook failure is runtime-owned.

The reset guarantee is not optional.

## Failure precedence

Outcome selection MUST follow this precedence:

1. uncaught exception or equivalent fatal boundary failure;
2. transport-specific controlled completion result;
3. default success condition for completed work.

If an uncaught exception occurs, the outcome MUST be:

```text
fatal_error
```

This applies even if a partial HTTP status code or CLI exit code exists.

If no uncaught exception occurs, HTTP and CLI adapters MUST use the exact mapping tables in this document.

## HTTP outcome mapping

HTTP outcome mapping is based on the completed HTTP status code when no uncaught exception occurred.

Exact mapping:

| HTTP condition     | Outcome         |
|--------------------|-----------------|
| status `< 400`     | `success`       |
| status `>= 400`    | `handled_error` |
| uncaught exception | `fatal_error`   |

Rules:

- HTTP status `< 400` MUST map to `success`.
- HTTP status `>= 400` MUST map to `handled_error`.
- An uncaught exception MUST map to `fatal_error`.
- `fatal_error` MUST take precedence over status-code mapping.
- Kernel MUST NOT build HTTP responses.
- Kernel MUST NOT select HTTP status codes.
- Kernel MUST NOT depend on PSR-7 or PSR-15.
- HTTP adapters MAY attach safe HTTP-specific attributes or extensions only through Kernel-owned shapes.

Examples:

| HTTP result                           | Outcome         |
|---------------------------------------|-----------------|
| `200` response, no uncaught exception | `success`       |
| `302` response, no uncaught exception | `success`       |
| `399` response, no uncaught exception | `success`       |
| `400` response, no uncaught exception | `handled_error` |
| `404` response, no uncaught exception | `handled_error` |
| `500` response, no uncaught exception | `handled_error` |
| uncaught exception before response    | `fatal_error`   |
| uncaught exception after response     | `fatal_error`   |

## CLI outcome mapping

CLI outcome mapping is based on the completed exit code when no uncaught exception occurred.

Exact mapping:

| CLI condition                               | Outcome         |
|---------------------------------------------|-----------------|
| `exitCode = 0`                              | `success`       |
| `exitCode != 0` without uncaught exceptions | `handled_error` |
| uncaught exception                          | `fatal_error`   |

Rules:

- Exit code `0` MUST map to `success`.
- Exit code other than `0` MUST map to `handled_error` when no uncaught exception occurred.
- An uncaught exception MUST map to `fatal_error`.
- `fatal_error` MUST take precedence over exit-code mapping.
- Kernel MUST NOT execute CLI commands directly in this policy epic.
- Kernel MUST NOT render CLI output.
- CLI adapters MAY attach safe CLI-specific attributes or extensions only through Kernel-owned shapes.

Examples:

| CLI result                              | Outcome         |
|-----------------------------------------|-----------------|
| exit code `0`, no uncaught exception    | `success`       |
| exit code `1`, no uncaught exception    | `handled_error` |
| exit code `2`, no uncaught exception    | `handled_error` |
| exit code `255`, no uncaught exception  | `handled_error` |
| uncaught exception before exit code     | `fatal_error`   |
| uncaught exception after partial output | `fatal_error`   |

## Queue and scheduler mapping boundary

This epic does not define queue outcome mapping.

This epic does not define scheduler outcome mapping.

Future queue or scheduler owner epics MAY define mappings only by updating this SSoT and corresponding contract tests.

Until then, only the following mapping policies are canonical:

```text
HTTP
CLI
```

The outcome token vocabulary still applies to all future UnitOfWork types.

## UnitOfWorkResult policy

Every completed UnitOfWork MUST produce or derive a `UnitOfWorkResult`.

The result shape is governed by:

```text
docs/ssot/uow-shapes.md
```

`docs/ssot/uow-shapes.md` also owns `UnitOfWorkResultInvalidException` reason-token vocabulary and safe diagnostic path hardening.

This outcome policy MUST NOT duplicate the result validation reason-token list.

Result shape validation failures, including invalid `extensions` and invalid exported `error` maps, are governed by `docs/ssot/uow-shapes.md` and MUST use `CORETSIA_UOW_RESULT_INVALID`.

`UnitOfWorkResult.outcome` MUST be one of:

```text
success
handled_error
fatal_error
```

`UnitOfWorkResult.startedAt` MUST match the originating context `startedAt`.

`UnitOfWorkResult.uowId` MUST match the originating context `uowId`.

`UnitOfWorkResult.type` MUST match the originating context `type`.

`UnitOfWorkResult.correlationId` MUST match the originating context `correlationId`.

`UnitOfWorkResult.durationMs` MUST be measured from the canonical monotonic timing source:

```text
Coretsia\Foundation\Time\Stopwatch
```

If monotonic timing is unavailable or `Stopwatch` start/stop fails, `UnitOfWorkResult.durationMs` MUST be `0`.

Unavailable timing MUST NOT affect outcome selection, error mapping, hook failure policy, reset policy, or lifecycle failure precedence.

`UnitOfWorkResult.durationMs` MUST NOT be calculated from:

```text
finishedAt - startedAt
```

## ErrorDescriptor policy

`UnitOfWorkResult.error` MAY be represented internally as:

```text
Coretsia\Contracts\Observability\Errors\ErrorDescriptor
```

Before `UnitOfWorkResult` crosses a Kernel hook/export boundary, `error` MUST be normalized to a json-like exported error map.

No `ErrorDescriptor` object instance MAY cross the hook/export boundary.

The exported error map MUST follow:

```text
docs/ssot/error-descriptor.md
docs/ssot/uow-shapes.md
```

The exported error map MUST NOT contain:

- raw Throwable objects;
- raw exception messages when unsafe;
- stack traces;
- transport objects;
- request objects;
- response objects;
- PSR-7 objects;
- CLI command objects;
- queue message objects;
- raw headers;
- raw cookies;
- raw authorization values;
- raw session identifiers;
- raw tokens;
- raw request payloads;
- raw response payloads;
- raw SQL;
- PII;
- private customer data;
- service instances;
- closures;
- resources.

## Result extensions safety

`UnitOfWorkResult.extensions` is safe completion metadata only.

It MUST be json-like.

It MUST be normalized deterministically before export.

It MUST NOT contain:

- stack traces;
- raw exception objects;
- raw exception messages when unsafe;
- raw request payloads;
- raw response payloads;
- raw queue payloads;
- raw SQL;
- raw headers;
- raw cookies;
- raw authorization values;
- raw session identifiers;
- raw tokens;
- PII;
- private customer data;
- direct user identifiers;
- absolute local paths;
- environment-specific bytes;
- service instances;
- closures;
- resources.

Allowed safe metadata includes:

```text
safe ids
stable enums
counts
lengths
hashes
bounded safe status/category tokens
```

Safe derivations such as `hash(value)` and `len(value)` MUST NOT allow reconstruction of sensitive values.

Runtime owners MUST prefer omission over unsafe emission.

## Stacktrace policy

Stack traces MUST NOT be stored in:

```text
UnitOfWorkResult.extensions
UnitOfWorkContext.attributes
```

Stack traces MUST NOT be emitted through Kernel hook/export arrays.

If an owner implementation reports stack traces in an internal development-only channel later, that channel MUST be explicitly owned by a future policy and MUST NOT reuse `result.extensions`.

This epic introduces no such channel.

## Raw payload policy

Raw payloads MUST NOT be stored in:

```text
UnitOfWorkResult.extensions
UnitOfWorkContext.attributes
```

Forbidden raw payload examples:

```text
HTTP request body
HTTP response body
raw headers
raw cookies
Authorization header
queue message body
CLI input dump
SQL statement
database row payload
profile payload
```

Safe alternatives MAY include:

```text
hash(value)
len(value)
stable enum
count
bounded category
```

## PII policy

PII MUST NOT be stored in:

```text
UnitOfWorkResult.extensions
UnitOfWorkContext.attributes
```

Forbidden direct identifiers include:

```text
email
phone
full name
username
external account id
tenant id where sensitive
user id where sensitive
address
private customer data
```

Safe ids MAY be used only when owner policy explicitly treats them as non-sensitive and bounded.

IDs MUST NOT be used as metric labels unless the observability SSoT explicitly allows that label key.

## Observability policy

This SSoT introduces no metrics, spans, or logs.

Future runtime owners MAY derive observability signals from UnitOfWork outcome data only when emissions comply with:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
```

Allowed candidate label keys are limited by the observability label allowlist.

Potential safe mappings where a future metric catalog explicitly allows them:

```text
operation = UnitOfWork type token
outcome = UnitOfWork outcome token
```

Metric labels MUST NOT include:

```text
uowId
correlationId
request_id
user_id
tenant_id
path
field
property
```

`durationMs` MAY be emitted as a metric value or span/log field according to owner policy.

`durationMs` MUST NOT be emitted as a metric label.

`attributes` and `extensions` MUST NOT be copied wholesale into logs, spans, or metric labels.

## Config policy

This SSoT introduces no new config root.

The existing config root is:

```text
kernel
```

This SSoT introduces no outcome mapping config keys.

Outcome mapping is canonical policy, not runtime configuration.

The following config keys MUST NOT be introduced by this epic:

```text
kernel.uow.outcomes.*
kernel.uow.outcome_mapping.*
kernel.outcome.*
kernel.http.outcome.*
kernel.cli.outcome.*
```

The Kernel config keys introduced by epic `1.270.0` are limited to:

```text
kernel.uow.attributes.max_depth
kernel.uow.attributes.max_keys
```

Those shape limits are governed by:

```text
docs/ssot/uow-shapes.md
```

## Tag policy

This SSoT introduces no new tags.

This SSoT introduces no DI tag identifier constants.

Existing framework-reserved DI tag identifier constants are declared only in:

```text
Coretsia\Foundation\Tag\ReservedTags
```

This SSoT MUST NOT redefine ownership of:

```text
kernel.reset
kernel.hook.before_uow
kernel.hook.after_uow
kernel.stateful
```

Their canonical code-level identifiers are:

```text
Coretsia\Foundation\Tag\ReservedTags::KERNEL_RESET
Coretsia\Foundation\Tag\ReservedTags::KERNEL_HOOK_BEFORE_UOW
Coretsia\Foundation\Tag\ReservedTags::KERNEL_HOOK_AFTER_UOW
Coretsia\Foundation\Tag\ReservedTags::KERNEL_STATEFUL
```

Runtime package source MUST use these constants for framework-reserved DI tag identifiers.

Tag ownership and discovery policy are governed by:

```text
docs/ssot/tags.md
docs/ssot/reset-tags.md
docs/ssot/uow-and-reset-contracts.md
```

## Artifact policy

This epic introduces no artifacts.

This SSoT introduces no generated artifact schema.

If future runtime owners export UnitOfWork results into artifacts, those artifacts MUST use normalized json-like exported shapes and MUST follow:

```text
docs/ssot/artifacts.md
docs/ssot/uow-shapes.md
```

## DTO boundary

`Outcome`, `UnitOfWorkContext`, and `UnitOfWorkResult` are Kernel runtime shapes/value objects or enum-like runtime symbols.

They are NOT DTO-marker classes by default.

DTO gates apply only to explicitly marked DTO transport classes.

A class is a DTO only when explicitly marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Kernel UnitOfWork outcome policy MUST NOT depend on DTO marker discovery.

## Acceptance scenarios

### HTTP success

Given an HTTP UnitOfWork completes with status `200` and no uncaught exception.

Then the outcome MUST be:

```text
success
```

### HTTP redirect success

Given an HTTP UnitOfWork completes with status `302` and no uncaught exception.

Then the outcome MUST be:

```text
success
```

### HTTP handled error

Given an HTTP UnitOfWork completes with status `404` and no uncaught exception.

Then the outcome MUST be:

```text
handled_error
```

### HTTP server response handled error

Given an HTTP UnitOfWork completes with status `500` and no uncaught exception.

Then the outcome MUST be:

```text
handled_error
```

This policy treats the completed status response as a handled runtime result.

If an uncaught exception occurred, the outcome MUST instead be `fatal_error`.

### HTTP fatal error

Given an HTTP UnitOfWork has an uncaught exception.

Then the outcome MUST be:

```text
fatal_error
```

This result MUST take precedence over any partial or fallback status code.

### CLI success

Given a CLI UnitOfWork completes with exit code `0` and no uncaught exception.

Then the outcome MUST be:

```text
success
```

### CLI handled error

Given a CLI UnitOfWork completes with exit code `2` and no uncaught exception.

Then the outcome MUST be:

```text
handled_error
```

### CLI fatal error

Given a CLI UnitOfWork has an uncaught exception.

Then the outcome MUST be:

```text
fatal_error
```

This result MUST take precedence over any partial or fallback exit code.

### Reset exactly once after after-phase entry

Given a UnitOfWork enters the after-phase.

Then after hooks MUST run according to runtime policy.

Then `ResetOrchestrator.resetAll()` MUST run exactly once.

Then `endUoW()` MAY complete.

The next UnitOfWork MUST start clean.

### Reset exactly once when after hook throws

Given a UnitOfWork enters the after-phase.

And an after-uow hook throws.

Then `ResetOrchestrator.resetAll()` MUST still run exactly once.

Then `endUoW()` MAY complete.

The next UnitOfWork MUST start clean.

Failure reporting or propagation is runtime-owned.

## Contract enforcement evidence

Expected Kernel contract enforcement includes:

```text
framework/packages/core/kernel/tests/Contract/OutcomeMappingStabilityContractTest.php
```

`OutcomeMappingStabilityContractTest` is the single contract lock for the HTTP/CLI mapping rules in this document.

It MUST verify at minimum:

- HTTP status `< 400` maps to `success`;
- HTTP status `>= 400` maps to `handled_error`;
- HTTP uncaught exception maps to `fatal_error`;
- CLI exit code `0` maps to `success`;
- CLI exit code other than `0` without uncaught exceptions maps to `handled_error`;
- CLI uncaught exception maps to `fatal_error`;
- outcome tokens are exactly `success`, `handled_error`, and `fatal_error`.

Shape-specific contract enforcement is owned by:

```text
framework/packages/core/kernel/tests/Contract/UnitOfWorkContextShapeContractTest.php
framework/packages/core/kernel/tests/Contract/UnitOfWorkResultShapeContractTest.php
framework/packages/core/kernel/tests/Contract/UnitOfWorkContextAttributesAreJsonLikeContractTest.php
framework/packages/core/kernel/tests/Contract/UnitOfWorkResultExtensionsAreJsonLikeContractTest.php
```

Those tests are governed by:

```text
docs/ssot/uow-shapes.md
```

## Non-goals

This SSoT does not define:

- UnitOfWork lifecycle executor implementation;
- hook dispatcher implementation;
- hook discovery implementation;
- hook priority schema;
- reset discovery implementation;
- reset DI tag identifier constants;
- `TagRegistry` enumeration logic;
- reset failure aggregation policy;
- HTTP response construction;
- HTTP status code selection;
- PSR-7 or PSR-15 integration;
- CLI command execution;
- CLI output rendering;
- queue outcome mapping;
- scheduler outcome mapping;
- retry policy;
- process supervision policy;
- problem-details formatting;
- generated artifact schemas;
- logging backend schema;
- tracing backend schema;
- metric backend schema.

## Cross-references

- [SSoT Index](./INDEX.md)
- [UnitOfWork Shapes SSoT](./uow-shapes.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [Reset Tags SSoT](./reset-tags.md)
- [Runtime Drivers SSoT](./runtime-drivers.md)
- [ContextStore lifecycle SSoT](./context-lifecycle.md)
- [Stateful Services SSoT](./stateful-services.md)
- [ErrorDescriptor SSoT](./error-descriptor.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [DTO Policy](./dto-policy.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
