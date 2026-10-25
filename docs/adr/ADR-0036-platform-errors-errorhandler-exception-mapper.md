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

# ADR-0036: Platform errors ErrorHandler and ExceptionMapper registry

```yaml
adrVersion: 1
status: pre-accepted
owner: platform/errors
```

## Context

`platform/errors` implements Coretsia's format-neutral error normalization at runtime. `core/contracts` defines `ErrorHandlerInterface`, `ExceptionMapperInterface`, `ErrorReporterPortInterface`, `ErrorHandlingContext`, `ErrorDescriptor`, and `ErrorSeverity`. It must not own runtime discovery, dependency injection, error reporting policy, redaction execution, or transport presentation.

Exception classification can be contributed by multiple selected runtime packages, but classification must not create competing `Throwable`-to-`ErrorDescriptor` pipelines. Ordering and duplicate registration handling already belong to the Foundation `TagRegistry`. Sensitive-data redaction already has a canonical `SensitiveDataRedactorInterface` port implemented by the selected `platform.redaction` module. Foundation already supplies the default noop reporter and other baseline observability ports.

A runtime handler must return a stable descriptor even when mapper candidates fail, redaction rejects their output, or optional observability collaborators throw. That guarantee applies after the handler and its required dependencies have been successfully constructed; it cannot replace Kernel module composition, initial container construction, or an adapter's pre-container failure handling.

`platform.cli` consumes normalized errors and contributes its own allowlisted exception classification. CLI output rendering remains independent from that mapping, while HTTP and Worker retain their established adapter and lifecycle boundaries.

## Decision

### Decision 1: Keep contracts format-neutral and give `platform/errors` one runtime owner

`core/contracts` continues to own the canonical public signatures:

```text
ErrorHandlerInterface::handle(
    Throwable $throwable,
    ?ErrorHandlingContext $context = null,
): ErrorDescriptor

ExceptionMapperInterface::map(
    Throwable $throwable,
    ?ErrorHandlingContext $context = null,
): ?ErrorDescriptor

ErrorReporterPortInterface::report(
    ErrorDescriptor $descriptor,
    ?ErrorHandlingContext $context = null,
): void
```

`ErrorDescriptor` remains the six-field normalized descriptor defined by `docs/ssot/error-descriptor.md`. `ErrorDescriptor::fromArray(array $payload): self` is the contracts-owned strict reconstruction API, not a new descriptor schema or a sensitive-data classifier.

The selected `platform.errors` runtime module provides the canonical `Coretsia\Platform\Errors\ErrorHandler` and aliases `ErrorHandlerInterface` to that service. The package owns `ExceptionMapperRegistry`, `DefaultExceptionMapper`, and its reporting configuration. It does not install a global PHP exception handler, implement HTTP or CLI responses, or construct a second container.

### Decision 2: Reuse Foundation `TagRegistry` as the sole mapper discovery authority

`ExceptionMapperRegistry` enumerates candidates through exactly:

```php
$this->tags->all(ReservedTags::ERROR_MAPPER)
```

The tag is the already reserved `error.mapper` identifier. Foundation defines the effective order: priority descending, service id ascending under byte-order `strcmp`, and first-wins registration for duplicate `(tag, serviceId)` pairs. `platform/errors` preserves that order and does not perform consumer-side sorting, de-duplication, filesystem scanning, or discovery-cache construction.

Each contributed tag identifies a container service implementing `ExceptionMapperInterface`. Tag metadata must be the empty map `[]`; priority is the canonical registration argument, not metadata. A mapper service is resolved lazily by its service id during dispatch and is not resolved during registry construction. Services are not cached by the registry between invocations.

Each candidate may return `null` to decline the Throwable. The first non-null `ErrorDescriptor` is selected. Invalid tag metadata, service resolution failures, invalid service types, and thrown mapper failures are isolated per candidate; later candidates remain eligible in canonical order. Registry-wide enumeration failure is caught at the handler boundary rather than triggering a second discovery pass.

### Decision 3: Select the fixed fallback outside the tagged registry

`DefaultExceptionMapper` handles every Throwable with one fixed safe descriptor:

```text
code: CORETSIA_UNHANDLED_EXCEPTION
message: Unhandled exception.
severity: error
httpStatus: null
extensions: []
```

This mapper is an explicit handler dependency, not an `error.mapper` registration. If the registry is empty, every candidate declines or fails, or registry-wide dispatch cannot complete, `ErrorHandler` invokes the fallback. A tagged default would compete with contributed mapper priorities, obscure exhaustion, and weaken the first-non-null contract.

The fallback does not copy the original Throwable's message, class, file, line, trace, previous exception, or caller metadata. Its constants are not derived from runtime input. No transport owner may replace canonical fallback selection with an additional Throwable dispatch pipeline.

### Decision 4: Perform mandatory shared redaction and strict reconstruction

Mapper owners must derive safe codes, messages, and extensions before creating descriptors. Neither `ErrorDescriptor` validation nor the shared redactor is a general-purpose guarantee that arbitrary producer text is safe to disclose. In particular, a classifier non-match is not authorization to expose an unclassified value.

The canonical handler passes the candidate's `toArray()` result through the already injected `SensitiveDataRedactorInterface::redactJsonLike()` port with one fixed context:

```text
scope: errors.descriptor
mode: placeholder
```

It accepts only array output and rebuilds the descriptor through `ErrorDescriptor::fromArray()`. Incorrect output shape, invalid canonical fields, forbidden extension data, resource-bound violations, or redactor exceptions fail closed to the fixed fallback. The handler must not return the candidate before successful redaction and reconstruction, retry the registry, or redact the fallback recursively when redaction fails.

`platform.redaction` owns redaction implementation and classification policy. `platform/errors` consumes the existing binding without constructing a concrete redactor, adding a local masking engine, or altering the canonical descriptor schema. Mandatory normalization and redaction remain active when optional reporting is disabled.

### Decision 5: Isolate enrichment and optional observability from normalization

For each `handle()` invocation, `ErrorHandler` queries `CorrelationIdProviderInterface` at most once and captures its result locally. With no caller-supplied mapping context it constructs a canonical context for operation `map`; an explicitly supplied `ErrorHandlingContext` remains authoritative for mapping. A separate sanitized reporting context uses the captured correlation value and contains no caller metadata. Invalid correlation enrichment is discarded without changing descriptor selection. The handler does not access or write `ContextStore` directly.

The `errors` config root controls optional reporting and mapper-failure diagnostics. When `errors.reporting.enabled` is true, the handler attempts to start the real Foundation `Stopwatch` and an `error.map` span before mapper dispatch. After normalization it attempts to increment `error.map_total` and observe a non-negative integer-millisecond duration in `error.map_duration_ms`, using only the fixed `operation` and `outcome` labels, finalizes the span, and attempts `end()` once for a successfully created span. Stopwatch, meter, and tracer failures remain independently isolated.

Reporter eligibility is evaluated only against the final descriptor and only after mandatory normalization. It uses minimum severity (`info`, `warning`, `error`, `critical`), exact case-sensitive excluded codes, and integer-basis-point sampling (`0..10000`). Reporter filtering affects only `ErrorReporterPortInterface::report()`; it does not alter mapper selection, redaction, fallback, metric emission, or spans. A reporter failure must not replace the descriptor or trigger recursive reporting.

`ExceptionMapperRegistry` owns safe `error.mapper_failed` warning events using only the fixed `operation=map` and low-cardinality `reason` values. Mapper-failure diagnostics require both `errors.reporting.enabled` and `errors.reporting.mapper_failures.enabled`; reporter-only severity, excluded-code, and sampling filters do not affect them. The PSR-3 logger's failures cannot affect dispatch. The Foundation `NoopErrorReporter` remains a valid baseline port implementation; this package registers no duplicate reporter.

### Decision 6: Preserve module composition, statelessness, and Kernel lifecycle ownership

`platform.errors` is a runtime module with declared requirements `core.foundation` and `platform.redaction`. Its Composer package `coretsia/platform-errors` directly requires the corresponding Foundation, Contracts, and Redaction packages. Its only package-owned config root is `errors`, whose defaults and declarative validation rules remain under `packages/platform/errors/config/`.

`ErrorsServiceProvider` contributes the handler, registry, fallback, and `ErrorHandlerInterface` alias through the canonical declarative definition API. It requires the existing redactor, reporter, correlation, tracing, metrics, logger, `Stopwatch`, config, container, and `TagRegistry` services. `ErrorsServiceFactory` validates reporting values when constructing services, using the already merged configuration. Source and compiled containers consume equivalent definitions. Composer installation alone does not enable a runtime module; Kernel `ModulePlan` selects the effective dependency closure.

The handler and registry have no retained per-invocation state. Mapper lists, Throwables, descriptors, timing tokens, spans, and contexts are not stored across `handle()` calls. There is no `kernel.reset` or `kernel.stateful` registration, package-local reset interface, reset hook, or independent UnitOfWork. Kernel continues to own UoW start, finish, hooks, and reset orchestration.

The runtime never-throws guarantee covers failures from mapper, redaction, and optional collaborator operations after successful service construction. Invalid required configuration and unavailable required module dependencies are service-construction or module-composition errors, not errors normalized by a handler that does not yet exist.

### Decision 7: Allow adapter-owned classification without transferring handler ownership

The CLI module requires `platform.errors` through both its Composer dependency and its runtime module requirements. `CliApplication` consumes `ErrorHandlerInterface` and calls it once on the normal Throwable path. The CLI's `CliExceptionMapper` is registered as an `error.mapper` service at priority `100`, with empty metadata, and returns safe, allowlisted codes and reasons for known CLI and Kernel Ops failures. Unknown Throwables return `null` and proceed through canonical discovery and fallback.

`ExceptionRenderer` remains responsible for CLI presentation. It translates the canonical fallback `CORETSIA_UNHANDLED_EXCEPTION` into the existing public CLI code `CORETSIA_CLI_INTERNAL_ERROR` and message `Internal CLI error.` without creating another descriptor or remapping a Throwable. CLI output formatting, suggestions, exit codes, buffering, and ultra-early `doctor` behavior remain within their existing owners.

An adapter-owning package may contribute an owner-specific mapper through `error.mapper`, but this does not transfer discovery, ordering, fallback, or the global handler binding away from `platform/errors`. HTTP and Worker runtime adapters do not consume the canonical `ErrorHandlerInterface` in the current runtime composition. Worker task settlement continues to receive the original Throwable, and pre-container/runtime bootstrap failures retain their owner-defined safe diagnostics.

## Consequences

### Positive consequences

- All selected consumers of the canonical port share one format-neutral runtime normalization implementation.
- Mapper contributions remain extensible without duplicating discovery or overriding Foundation's deterministic ordering and duplicate semantics.
- An unhandled Throwable produces a fixed safe fallback independent of raw exception details.
- Shared redaction, strict reconstruction, and fail-closed behavior protect the normalization boundary without a second redaction engine.
- Optional reporter, logger, tracing, metrics, and timing collaborators cannot interrupt canonical normalization.
- Existing CLI error codes, output formats, normal exit behavior, and early-bootstrap boundaries remain owned by the CLI.
- Source and compiled DI definitions remain aligned; no package-local lifecycle orchestration is introduced.

### Trade-offs

- Contributed mapper owners must establish the safety of their own message and extension derivations; generic redaction is defense in depth, not a proof of arbitrary input safety.
- A failed shared redaction pass discards a candidate descriptor and returns the fixed fallback rather than exposing a partially processed result.
- Reporter sampling is probabilistic when the configured rate is between `0` and `10000`; normalized results and mapping telemetry do not depend on the sample decision.
- Runtime handler availability depends on successful module selection, configuration validation, and construction of required shared services.

## Rejected alternatives

### Implement a second mapper registry or order candidates inside each adapter

Rejected. Multiple registries would produce inconsistent precedence, violate the existing `TagRegistry` first-wins policy, and transfer canonical dispatch ownership away from `platform/errors`.

### Tag the default catch-all mapper

Rejected. A catch-all registration would compete with owner-specific mapper priorities and could prevent later candidates from being considered. Exhaustion and fallback are separate handler responsibilities.

### Copy raw Throwable messages and depend solely on generic redaction

Rejected. Classifier non-matches cannot establish disclosure permission, and raw messages, traces, previous exceptions, private values, or caller metadata must not be copied into a public descriptor by default.

### Add an Errors-local redactor, reporter, or observability backend

Rejected. The canonical redaction implementation belongs to `platform.redaction` and baseline observability ports belong to Foundation. Duplicate bindings and masking pipelines would undermine dependency and security ownership.

### Create a package-local reset mechanism or UnitOfWork wrapper

Rejected. Error normalization has no cross-invocation mutable lifecycle to reset. Kernel owns UoW lifecycle, while Foundation supplies shared reset infrastructure.

### Create adapter-owned global error handlers or rewire HTTP and Worker automatically

Rejected. Adapter rendering and failure handling remain transport-specific. The CLI consumes the canonical port explicitly; HTTP and Worker integration requires separately defined adapter boundaries.

### Add a new descriptor schema or configurable raw-debug disclosure mode

Rejected. The canonical schema is contracts-owned. Raw Throwable disclosure switches and alternate normalized descriptors would violate the fixed safe output boundary.

## Non-goals

This decision does not change the public `ErrorHandlerInterface` signature, introduce another error hierarchy, define HTTP problem-details rendering, redesign CLI output or Worker task settlement, install global PHP handlers, add package-specific reporter or redaction implementations, change Kernel `ModulePlan` or UnitOfWork algorithms, introduce a local clock or reset coordinator, or register a new tag identifier.

## Verification evidence

The following package tests verify the selected runtime and architectural decisions:

```text
packages/platform/errors/tests/Unit/DefaultExceptionMapperTest.php
packages/platform/errors/tests/Unit/ErrorHandlerFallbackTest.php
packages/platform/errors/tests/Unit/ErrorHandlerRedactionTest.php
packages/platform/errors/tests/Unit/ErrorsServiceFactoryTest.php
packages/platform/errors/tests/Contract/ErrorHandlerNeverThrowsContractTest.php
packages/platform/errors/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php
packages/platform/errors/tests/Contract/ErrorsDoNotDuplicateRedactionPolicyContractTest.php
packages/platform/errors/tests/Contract/ErrorsModuleComposerMetadataContractTest.php
packages/platform/errors/tests/Contract/ErrorsProviderDefinitionsContainNoClosuresContractTest.php
packages/platform/errors/tests/Contract/ErrorsProviderSourceDefinitionsParityTest.php
packages/platform/errors/tests/Integration/RegistryDeterministicOrderTest.php
packages/platform/errors/tests/Integration/MapperLazyResolutionTest.php
packages/platform/errors/tests/Integration/MapperFailureIsolationTest.php
packages/platform/errors/tests/Integration/DefaultMapperCatchesAnyThrowableTest.php
packages/platform/errors/tests/Integration/ErrorDescriptorIsRedactedTest.php
packages/platform/errors/tests/Integration/ErrorReportingPolicyTest.php
packages/platform/errors/tests/Integration/ErrorHandlingContextIsolationTest.php
packages/platform/errors/tests/Integration/ErrorHandlingUoWIsolationTest.php
packages/platform/errors/tests/Integration/ErrorsModuleCompositionTest.php
packages/platform/cli/tests/Unit/CliExceptionMapperTest.php
packages/platform/cli/tests/Integration/CliCanonicalErrorHandlerIntegrationTest.php
```

These tests cover safe fallback, candidate failure isolation, deterministic discovery, mandatory redaction, reporting policy, correlation and UnitOfWork isolation, source/compiled definitions, module composition, and CLI consumption. Canonical repository gates remain authoritative for package dependencies, tags, security boundaries, and architectural policy.

## Related SSoT

- `docs/ssot/errors-boundary.md`
- `docs/ssot/error-descriptor.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/observability.md`
- `docs/ssot/sensitive-data-redaction.md`
- `docs/ssot/tags.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/modules-and-manifests.md`
- `docs/ssot/runtime-container-definitions.md`
- `docs/ssot/compiled-container.md`
- `docs/ssot/context-store.md`
- `docs/ssot/modes.md`
- `docs/ssot/application-dependency-sync.md`

## Related ADRs

- `docs/adr/ADR-0003-observability-errordescriptor-health-profiling-ports.md`
- `docs/adr/ADR-0010-sensitive-data-redaction-boundary.md`
- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/adr/ADR-0015-context-bag-context-store-correlation-id.md`
- `docs/adr/ADR-0016-clock-ids-stopwatch.md`
- `docs/adr/ADR-0019-enhanced-reset-long-running.md`
- `docs/adr/ADR-0020-kernel-runtime-uow-spi.md`
- `docs/adr/ADR-0029-kernel-container-compile-artifact.md`
- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md`
- `docs/adr/ADR-0034-cli-tag-first-command-catalog.md`
- `docs/adr/ADR-0035-cli-composite-workflows.md`
