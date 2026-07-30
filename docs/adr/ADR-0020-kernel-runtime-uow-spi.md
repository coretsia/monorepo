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

# ADR-0020: Kernel runtime UnitOfWork SPI

```yaml
adrVersion: 1
status: pre-accepted
owner: core/kernel
```

## Context

Coretsia needs a stable external runtime boundary for executing units of work across HTTP, CLI, worker, scheduler, queue consumer, and custom runtime adapters.

The boundary must allow adapters to enter Kernel-owned UnitOfWork lifecycle orchestration without depending on the concrete `core/kernel` implementation.

The lifecycle must support:

- begin context creation;
- base context key writes;
- before-unit-of-work hooks;
- external adapter body execution;
- after-unit-of-work hooks;
- reset orchestration;
- normalized safe context/result export;
- deterministic failure precedence;
- format-neutral payloads.

The public adapter-facing runtime SPI must live in `core/contracts`.

The concrete orchestration implementation must live in `core/kernel`.

The implementation uses Kernel-owned UnitOfWork runtime shapes:

```text
Coretsia\Kernel\Runtime\UnitOfWorkContext
Coretsia\Kernel\Runtime\UnitOfWorkResult
```

Those concrete runtime shapes must not leak into `core/contracts`.

The external runtime port is:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

Base UnitOfWork context key identifiers are provided by:

```text
Coretsia\Contracts\Context\ContextKeys
```

The read-only context access boundary is provided by:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
```

The concrete Kernel implementation is:

```text
Coretsia\Kernel\Runtime\KernelRuntime
```

Platform, worker, scheduler, queue, and custom adapters need the contracts port, not the concrete Kernel implementation.

## Decision

Coretsia will define the external UnitOfWork runtime SPI in `core/contracts`.

The contracts package owns:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface
Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface
Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface
Coretsia\Contracts\Context\ContextAccessorInterface
Coretsia\Contracts\Context\ContextKeys
```

The Kernel package owns:

```text
Coretsia\Kernel\Runtime\KernelRuntime
Coretsia\Kernel\Runtime\Hook\HookInvoker
Coretsia\Kernel\Runtime\Hook\HookContextNormalizer
Coretsia\Kernel\Runtime\UnitOfWorkContext
Coretsia\Kernel\Runtime\UnitOfWorkResult
```

`Coretsia\Kernel\Runtime\KernelRuntime` is the `core/kernel` implementation bound to the contracts port by DI.

External adapters MUST depend on:

```php
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

External adapters MUST NOT typehint, construct, or directly depend on:

```php
Coretsia\Kernel\Runtime\KernelRuntime
```

`core/kernel` MAY depend on `core/contracts`.

`core/contracts` MUST NOT depend on `core/kernel`.

## KernelRuntimeInterface decision

`core/contracts` owns the external Kernel runtime port.

The canonical interface path is:

```text
framework/packages/core/contracts/src/Runtime/KernelRuntimeInterface.php
```

The canonical interface is:

```php
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

The canonical method set is:

```text
runUnitOfWork(string $type, callable $body, array $attributes = []): mixed
beginUnitOfWork(string $type, array $attributes = []): UnitOfWorkHandle
afterUnitOfWork(UnitOfWorkHandle $handle, string $outcome, ?Throwable $error = null, array $extensions = []): array
```

`runUnitOfWork()` is the preferred high-level adapter API.

It lets `KernelRuntime` own before-hook handling, external body execution, after-phase execution when eligible, reset execution, and deterministic failure precedence.

`beginUnitOfWork()` and `afterUnitOfWork()` are low-level primitives for adapters that must integrate around an existing event loop or framework lifecycle.

Low-level adapters receive weaker lifecycle guarantees and must use `try/finally` around their external body execution.

The `try/finally` completion responsibility starts only after `beginUnitOfWork()` returns a `UnitOfWorkHandle` successfully.

If `beginUnitOfWork()` throws, no open lifecycle handle exists and the adapter MUST NOT call `afterUnitOfWork()` for that failed begin attempt.

Adapters that require Kernel-owned before-hook failure handling SHOULD use `runUnitOfWork()`.

The contracts port intentionally uses only:

- strings;
- callables;
- arrays;
- `Throwable`;
- `mixed` return values;
- contracts-owned opaque lifecycle handles.

It MUST NOT expose:

- `Coretsia\Kernel\Runtime\UnitOfWorkContext`;
- `Coretsia\Kernel\Runtime\UnitOfWorkResult`;
- `Coretsia\Kernel\Runtime\Outcome`;
- `Coretsia\Kernel\Runtime\UnitOfWorkType`;
- PSR-7 request or response objects;
- PSR-15 middleware objects;
- platform request/response objects;
- worker vendor messages;
- scheduler vendor contexts;
- integration package objects;
- Foundation internals.

## Hook signature decision

`core/contracts` owns the hook method signatures.

The canonical before hook shape is:

```php
beforeUow(array $context): void
```

The canonical after hook shape is:

```php
afterUow(array $context, array $result): void
```

The hook payload arrays are normalized exported lifecycle payloads.

The before hook receives:

```text
normalized exported UnitOfWork context array
```

The after hook receives:

```text
normalized exported UnitOfWork context array
normalized exported UnitOfWork result array
```

The contracts package defines only the interface signatures.

It does not define concrete context/result classes, hook discovery, hook ordering, tag metadata, priority semantics, failure precedence, reset behavior, or DI wiring.

Hook interfaces remain format-neutral.

They MUST NOT require:

- `core/kernel` runtime classes;
- PSR-7 request or response objects;
- PSR-15 middleware objects;
- platform package classes;
- integration package classes;
- vendor worker messages;
- scheduler vendor contexts;
- concrete service containers.

## Hook invocation failure decision

Kernel hook invocation is sequential and fail-fast in the deterministic order returned by `TagRegistry`.

The first hook service resolution failure, interface mismatch, or exception thrown by a valid hook stops the remaining hooks in the same lifecycle phase.

Exceptions thrown by valid hook implementations propagate unchanged from `HookInvoker` to `KernelRuntime`.

`HookInvoker` MUST NOT:

- suppress hook failures;
- continue invoking later hooks after a failure;
- aggregate multiple hook failures;
- execute reset orchestration;
- select lifecycle failure precedence.

`KernelRuntime` owns lifecycle-level handling.

After the reset-responsibility boundary is crossed:

- a before-hook failure skips the external body and after phase;
- an after-hook failure stops the remaining after hooks;
- reset orchestration still runs exactly once;
- the first hook failure remains the primary lifecycle failure when reset also fails.

## Kernel implementation decision

`core/kernel` owns the concrete runtime implementation.

The canonical implementation path is:

```text
framework/packages/core/kernel/src/Runtime/KernelRuntime.php
```

The canonical implementation class is:

```php
Coretsia\Kernel\Runtime\KernelRuntime
```

`KernelRuntime` implements:

```php
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

`KernelRuntime` internally uses:

```text
Coretsia\Kernel\Runtime\UnitOfWorkContext
Coretsia\Kernel\Runtime\UnitOfWorkResult
```

The contracts package exposes the canonical low-level lifecycle handle:

```text
Coretsia\Contracts\Runtime\UnitOfWorkHandle
```

This handle is an opaque lifecycle handle, not a Kernel runtime shape.

It MUST expose only the normalized exported context array through `UnitOfWorkHandle::context()` and MUST NOT expose Stopwatch tokens.

`KernelRuntime` maintains exported context and private lifecycle timing state through separate channels:

```text
UnitOfWorkContext::toArray()
  → normalized exported context
  → UnitOfWorkHandle::context()

UnitOfWorkContext::startedAtToken()
  → private lifecycle state associated with the exact UnitOfWorkHandle identity
  → duration calculation during afterUnitOfWork()
```

The presence of `startedAtToken` in the internal `UnitOfWorkContext` does not make it a key of the exported handle context.

Private timing state MUST NOT be copied into, reconstructed from, or exposed through `UnitOfWorkHandle::context()`.

Those internal objects are Kernel-owned and must not become part of the contracts port.

`KernelRuntime` is responsible for:

- validating UnitOfWork type tokens;
- creating UnitOfWork context objects;
- writing base context keys using `Coretsia\Contracts\Context\ContextKeys`;
- invoking before-uow hooks;
- executing the external body for the high-level API;
- validating low-level lifecycle handles and their normalized exported context payloads;
- validating outcome tokens;
- creating UnitOfWork result objects;
- producing normalized exported context/result payloads;
- invoking after-uow hooks;
- delegating reset to Foundation reset orchestration;
- preserving deterministic failure precedence;
- emitting safe lifecycle summary observability.

`KernelRuntime` `Stopwatch` failures MUST NOT change UnitOfWork lifecycle behavior, hook invocation policy, reset policy, outcome selection, or lifecycle failure precedence.

When timing is unavailable, `KernelRuntime` MAY use `0` only as an internal unavailable timer sentinel for private lifecycle timing state.

The internal timer sentinel MUST NOT be exported in context arrays, `UnitOfWorkHandle::context()`, result arrays, hook payloads, logs, metrics, traces, diagnostics, generated artifacts, or persistence payloads.

The internal timer sentinel MUST NOT be passed to `Stopwatch::stop()`.

When duration cannot be measured, `UnitOfWorkResult.durationMs` MUST be `0`.

The Kernel-owned base context writes are:

```text
Coretsia\Contracts\Context\ContextKeys::CORRELATION_ID
Coretsia\Contracts\Context\ContextKeys::UOW_ID
Coretsia\Contracts\Context\ContextKeys::UOW_TYPE
```

Importing `ContextKeys` provides stable key vocabulary only.

Write ownership remains Kernel-owned for these base UnitOfWork keys.

Reset responsibility starts only after Kernel-owned UnitOfWork context creation and base `ContextStore` key writing both complete successfully.

If UnitOfWork context creation fails, `KernelRuntime` MUST surface that primary failure without invoking reset orchestration.

If base `ContextStore` key writing fails, `KernelRuntime` MUST surface that primary failure without invoking reset orchestration.

Before-uow hook execution happens after this reset-responsibility boundary. Therefore, if a before-uow hook fails, reset orchestration still runs according to Kernel failure-precedence policy.

A before-uow hook failure does not enter after-phase handling.

For a before-uow hook failure, `KernelRuntime` MUST NOT execute the external body, MUST NOT construct a `UnitOfWorkResult`, and MUST NOT invoke after-uow hooks.

The before-uow hook failure remains the primary lifecycle failure; reset failure is surfaced only when no primary lifecycle failure exists.

The canonical high-level lifecycle failure precedence is:

| failure situation                                        | surfaced failure                                                        |
|----------------------------------------------------------|-------------------------------------------------------------------------|
| context creation fails                                   | context creation failure; reset does not run                            |
| base context key writing fails                           | context write failure; reset does not run                               |
| before hook fails                                        | exact before-hook failure                                               |
| body fails and after phase also fails                    | exact body failure                                                      |
| body succeeds and after phase fails                      | exact after-phase failure                                               |
| an earlier lifecycle failure exists and reset also fails | exact earlier lifecycle failure                                         |
| no earlier lifecycle failure exists and reset fails      | safe `KernelRuntimeException` with reason `kernel-runtime-reset-failed` |

`KernelRuntime` MUST return the existing primary throwable unchanged.

It MUST NOT replace, wrap, or mutate an existing primary throwable with a secondary reset failure.

Secondary after-phase or reset failures are not aggregated into the surfaced lifecycle throwable.

## Hook payload production decision

`core/kernel` owns normalized hook payload production.

The canonical internal normalizer path is:

```text
framework/packages/core/kernel/src/Runtime/Hook/HookContextNormalizer.php
```

Kernel hook payload production converts Kernel-owned runtime shapes into normalized json-like arrays.

The input MUST be one of the Kernel-owned runtime shapes:

```text
UnitOfWorkContext
UnitOfWorkResult
```

`HookContextNormalizer` MUST NOT accept arbitrary context or result arrays.

Raw array input would permit internal callers to bypass UoW-specific validation owned by `UnitOfWorkContext`, `UnitOfWorkResult`, and `JsonLikeShapeNormalizer`.

The runtime shapes first normalize their UoW-owned fields through `JsonLikeShapeNormalizer`. `HookContextNormalizer` then performs a final Foundation baseline normalization pass over the complete exported map.

The output passed to hooks is always array payload data.

After normalization, hook payloads MUST NOT contain object instances.

If `UnitOfWorkResult` internally contains:

```php
Coretsia\Contracts\Observability\Errors\ErrorDescriptor
```

the hook result payload MUST contain a normalized json-like error map, not the `ErrorDescriptor` object.

`core/kernel` MUST NOT define a second json-like policy.

Baseline json-like validation and normalization are delegated to Foundation serialization policy.

## DI binding decision

The DI binding is owned by `core/kernel`.

The provider binds:

```php
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

to:

```php
Coretsia\Kernel\Runtime\KernelRuntime
```

Runtime adapters consume the contracts interface.

They do not construct or typehint the concrete implementation.

The concrete `KernelRuntime` receives its implementation dependencies through DI, including:

- Foundation `ContextStore` for Kernel-owned base context writes;
- Foundation `ResetOrchestrator`;
- Foundation `Stopwatch`;
- Foundation `IdGeneratorInterface`;
- contracts `CorrelationIdProviderInterface`;
- Foundation `CorrelationIdGenerator`;
- Kernel `HookInvoker`;
- PSR logger;
- tracing port;
- metrics port.

## Adapter policy

Platform, worker, scheduler, queue, and custom runtime adapters MUST depend on:

```php
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

Adapters MUST NOT depend on:

```php
Coretsia\Kernel\Runtime\KernelRuntime
```

Adapters that only need Kernel-owned lifecycle wrapping SHOULD call:

```php
runUnitOfWork()
```

Adapters that must integrate around an existing framework lifecycle MAY call:

```php
beginUnitOfWork()
afterUnitOfWork()
```

Low-level adapters must execute their external body only after successful `beginUnitOfWork()` returns a `UnitOfWorkHandle`.

Low-level adapters that need the exported context payload may read it through:

```php
UnitOfWorkHandle::context()
```

Low-level adapters that need the exported result payload must pass the exact handle to `afterUnitOfWork()`.

`runUnitOfWork()` returns the external body return value.

It does not return the exported UnitOfWork result array.

## Reset boundary decision

Foundation owns reset orchestration.

Kernel runtime code depends on:

```php
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

Kernel runtime code calls:

```text
ResetOrchestrator::resetAll()
```

Kernel runtime code MUST NOT enumerate reset services directly.

Kernel runtime code MUST NOT call `ResetInterface::reset()` directly on discovered services.

Kernel runtime code MUST NOT own reset discovery tag identifiers.

The Foundation reset discovery tag remains Foundation-owned.

The reserved default reset discovery tag is:

```text
kernel.reset
```

The canonical code-level identifier for this framework-reserved DI tag is:

```text
Coretsia\Foundation\Tag\ReservedTags::KERNEL_RESET
```

Kernel runtime MUST invoke `ResetOrchestrator::resetAll()` only for UnitOfWork executions that crossed the reset-responsibility boundary.

The reset-responsibility boundary is crossed only after:

```text id="q8g7y9"
UnitOfWorkContext created
base ContextStore keys written successfully
```

Failures before this boundary MUST NOT trigger reset orchestration.

Failures after this boundary MUST preserve Kernel failure precedence and run reset according to the accepted lifecycle policy.

Before-uow hook failures are after the reset-responsibility boundary but before after-phase eligibility. They MUST trigger reset but MUST NOT trigger after-uow hooks.

Body failures, result construction failures, and after-uow failures happen after after-phase eligibility and therefore follow the after-phase reset policy.

## Observability decision

KernelRuntime may emit lifecycle summary observability around UnitOfWork completion.

The canonical operation label is the normalized UnitOfWork type.

For an HTTP UnitOfWork, the operation label is:

```text
http
```

The span name remains:

```text
kernel.uow
```

The canonical metrics are:

```text
kernel.uow_total
kernel.uow_duration_ms
```

Allowed labels for these runtime summary signals are:

```text
operation
outcome
```

Lifecycle summary logs must remain summary-only.

They MUST NOT contain:

- raw `uowId`;
- raw `correlationId`;
- raw context arrays;
- hook payloads;
- transport payloads;
- raw throwable messages;
- stack traces;
- tokens;
- cookies;
- headers;
- raw SQL;
- local absolute paths.

Observability failures MUST NOT replace primary KernelRuntime lifecycle failures.

## Consequences

Positive consequences:

- Adapters get a stable external runtime SPI in `core/contracts`.
- `core/contracts` remains independent from `core/kernel`.
- `core/kernel` can evolve implementation internals without changing adapter typehints.
- Hook signatures are explicit and payload-aware.
- Hook payload production remains Kernel-owned.
- UnitOfWork context/result objects remain implementation details.
- Platform, worker, scheduler, and queue adapters share one format-neutral runtime entrypoint.
- DI can bind the contracts port to the Kernel implementation without leaking Kernel concrete classes into adapter code.

Trade-offs:

- Adapters that need exported context/result arrays must use the low-level pair.
- `runUnitOfWork()` body callables do not receive context/result arguments.
- Hook payload shape is produced by Kernel, not contracts.
- Contracts tests must guard against `core/kernel`, PSR-7/15, platform, and integration dependencies.
- Runtime tests must guard lifecycle behavior, reset behavior, hook ordering, and safe observability.

## Rejected alternatives

### Define a kernel-local KernelRuntimeInterface

Rejected.

A `Coretsia\Kernel\Runtime\KernelRuntimeInterface` would force adapters to depend on `core/kernel` for the external runtime SPI.

That would invert the intended dependency boundary.

The external adapter port belongs in `core/contracts`.

The implementation belongs in `core/kernel`.

### Typehint concrete KernelRuntime in adapters

Rejected.

Adapters must not know or construct the concrete Kernel implementation.

They consume:

```php
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

DI binds that contracts port to the Kernel implementation.

This keeps platform, worker, scheduler, queue, and custom runtime adapters decoupled from Kernel internals.

### Keep parameterless hooks and defer payload normalization to a future owner

Rejected.

Parameterless hooks are insufficient for Kernel-owned lifecycle payload delivery.

The runtime now has a stable normalized exported UnitOfWork context/result shape, and hooks need that safe payload at the lifecycle boundary.

Deferring payload-aware hooks would force either hidden side channels or a second future breaking change to hook signatures.

The accepted hook signatures are:

```php
beforeUow(array $context): void
afterUow(array $context, array $result): void
```

### Put UnitOfWorkContext and UnitOfWorkResult in core/contracts

Rejected.

Concrete UnitOfWork runtime shapes are implementation-owned.

Putting them in `core/contracts` would freeze Kernel internals as public contracts and create pressure for platform/runtime-specific fields to leak into the contracts package.

The contracts port exposes normalized arrays only.

### Let contracts own hook payload production

Rejected.

Hook payload production depends on Kernel-owned UnitOfWork runtime shapes and Kernel lifecycle semantics.

Therefore payload production belongs to `core/kernel`.

The contracts package owns signatures only.

### Let Kernel enumerate reset services directly

Rejected.

Reset discovery, reset ordering, reset tag ownership, and reset service execution belong to Foundation reset orchestration.

Kernel consumes only:

```text
ResetOrchestrator::resetAll()
```

## Non-goals

This ADR does not define:

- platform HTTP adapter implementation;
- platform CLI adapter implementation;
- worker loop implementation;
- scheduler loop implementation;
- queue consumer implementation;
- transport-specific request/response/message models;
- vendor-specific runtime integrations;
- a contracts-level UnitOfWork object;
- a second json-like policy in `core/kernel`;
- reset service discovery in `core/kernel`;
- reset DI tag identifier ownership in `core/kernel`;
- hook priority metadata schema;
- hook retry policy;
- hook timeout policy;
- generated artifacts.

## Verification evidence

Expected verification includes:

```text
framework/packages/core/contracts/tests/Contract/KernelRuntimeInterfaceIsFormatNeutralContractTest.php
framework/packages/core/contracts/tests/Contract/HookInterfacesDoNotDependOnPlatformTest.php
framework/packages/core/kernel/tests/Contract/KernelPublicApiDoesNotExposePsr7Test.php
framework/packages/core/kernel/tests/Contract/KernelDoesNotWriteToStdoutTest.php
framework/packages/core/kernel/tests/Contract/KernelDoesNotEnumerateResetDiscoveryTagTest.php
framework/packages/core/kernel/tests/Integration/KernelServiceProviderWiresKernelRuntimeTest.php
framework/packages/core/kernel/tests/Integration/KernelRuntimeWritesBaseContextKeysAtBeginUowTest.php
framework/packages/core/kernel/tests/Integration/KernelRuntimeUsesCorrelationSourcesAndDefaultIdGeneratorTest.php
framework/packages/core/kernel/tests/Integration/KernelRuntimeInvokesHooksInDeterministicOrderTest.php
framework/packages/core/kernel/tests/Integration/KernelRuntimeExportsNormalizedHookPayloadsTest.php
framework/packages/core/kernel/tests/Integration/KernelRuntimeHandleDoesNotExportTimingTokensTest.php
framework/packages/core/kernel/tests/Integration/KernelRuntimeResetHappensAfterAfterUowHooksTest.php
framework/packages/core/kernel/tests/Integration/KernelRuntimeAlwaysResetsAfterUowTest.php
framework/packages/core/kernel/tests/Integration/KernelRuntimeRejectsInvalidExportedContextTest.php
framework/packages/core/kernel/tests/Integration/KernelRuntimeEmitsPolicyCompliantObservabilityTest.php
```

These tests are expected to verify:

- `core/contracts` owns the external `KernelRuntimeInterface`;
- `core/kernel` owns the `KernelRuntime` implementation;
- `core/contracts` owns hook signatures;
- `core/contracts` owns public context key identifiers used by Kernel base context writes;
- `core/kernel` owns normalized hook payload production;
- `beginUnitOfWork()` returns a handle whose context excludes `startedAt`, `startedAtToken`, and `finishedAt`;
- the exact returned handle retains access to private lifecycle timing state through Kernel-owned identity association;
- `afterUnitOfWork()` completes successfully without reading timing state from `UnitOfWorkHandle::context()`;
- a body throwable remains the exact surfaced throwable when after-phase or reset handling also fails;
- an after-hook throwable remains the exact surfaced throwable when reset also fails;
- a before-hook throwable remains the exact surfaced throwable when reset also fails;
- reset failure is surfaced only when no earlier lifecycle failure exists;
- a surfaced reset failure preserves the original reset throwable through in-process previous-throwable chaining;
- adapters consume the contracts port;
- Kernel does not define a competing runtime interface;
- Kernel does not expose PSR-7/15 in public runtime APIs;
- Kernel does not enumerate reset discovery tags or depend on reset DI tag identifiers directly;
- Kernel delegates reset to Foundation `ResetOrchestrator`;
- Kernel provider binds the contracts port to the Kernel implementation.

## Related SSoT

- `docs/ssot/uow-and-reset-contracts.md`
- `docs/ssot/tags.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/context-keys.md`
- `docs/ssot/context-store.md`

## Related ADRs

- `docs/adr/ADR-0003-observability-errordescriptor-health-profiling-ports.md`
- `docs/adr/ADR-0006-reset-interface-uow-hooks.md`
