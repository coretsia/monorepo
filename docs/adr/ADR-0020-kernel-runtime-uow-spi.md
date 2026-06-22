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

## Status

Accepted.

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
beginUnitOfWork(string $type, array $attributes = []): array
afterUnitOfWork(array $context, string $outcome, ?Throwable $error = null, array $extensions = []): array
```

`runUnitOfWork()` is the preferred high-level adapter API.

It lets KernelRuntime own the full lifecycle, including after/reset execution and deterministic failure precedence.

`beginUnitOfWork()` and `afterUnitOfWork()` are low-level primitives for adapters that must integrate around an existing event loop or framework lifecycle.

Low-level adapters receive weaker lifecycle guarantees and must use `try/finally` around their external body execution.

Adapters that require Kernel-owned before-hook failure handling SHOULD use `runUnitOfWork()`.

The contracts port intentionally uses only:

- strings;
- callables;
- arrays;
- `Throwable`;
- `mixed` return values.

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

Those internal objects are Kernel-owned and must not become part of the contracts port.

`KernelRuntime` is responsible for:

- validating UnitOfWork type tokens;
- creating UnitOfWork context objects;
- writing base context keys using `Coretsia\Contracts\Context\ContextKeys`;
- invoking before-uow hooks;
- executing the external body for the high-level API;
- validating low-level exported context arrays;
- validating outcome tokens;
- creating UnitOfWork result objects;
- producing normalized exported context/result payloads;
- invoking after-uow hooks;
- delegating reset to Foundation reset orchestration;
- preserving deterministic failure precedence;
- emitting safe lifecycle summary observability.

The Kernel-owned base context writes are:

```text
Coretsia\Contracts\Context\ContextKeys::CORRELATION_ID
Coretsia\Contracts\Context\ContextKeys::UOW_ID
Coretsia\Contracts\Context\ContextKeys::UOW_TYPE
```

Importing `ContextKeys` provides stable key vocabulary only.

Write ownership remains Kernel-owned for these base UnitOfWork keys.

## Hook payload production decision

`core/kernel` owns normalized hook payload production.

The canonical internal normalizer path is:

```text
framework/packages/core/kernel/src/Runtime/Hook/HookContextNormalizer.php
```

Kernel hook payload production converts Kernel-owned runtime shapes into normalized json-like arrays.

The input may be Kernel-owned objects:

```text
UnitOfWorkContext
UnitOfWorkResult
```

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

Low-level adapters must execute their external body only after successful `beginUnitOfWork()`.

Low-level adapters that need the exported result payload must use `afterUnitOfWork()`.

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

## Related epic

- `1.280.0 Kernel: KernelRuntime (UoW SPI, no PSR-7)`
