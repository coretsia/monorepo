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

# Errors Boundary SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: core/contracts
```

## Scope

This document is the Single Source of Truth for the Coretsia error normalization responsibility boundary, forbidden dependency directions, runtime adapter ownership, mapper discovery policy, and canonical high-level error flow.

This document governs the boundary between:

```text
core/contracts
platform/errors
platform/problem-details
HTTP/CLI/Worker runtime adapters
```

It complements:

```text
docs/ssot/error-descriptor.md
docs/ssot/observability-and-errors.md
docs/ssot/observability.md
docs/ssot/tags.md
docs/ssot/dto-policy.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Coretsia has exactly one canonical error normalization flow:

```text
Throwable → ErrorDescriptor → runtime adapters
```

No HTTP, CLI, worker, logging, tracing, or problem-details adapter may invent a competing normalized error shape.

## Responsibility boundary

### `core/contracts`

`core/contracts` owns contracts-level error ports and descriptor models.

It defines:

```text
Coretsia\Contracts\Observability\Errors\ErrorDescriptor
Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface
Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface
Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface
Coretsia\Contracts\Observability\Errors\ErrorHandlingContext
Coretsia\Contracts\Observability\Errors\ErrorSeverity
```

The canonical implementation-neutral handler port signature is:

```php
public function handle(
    Throwable $throwable,
    ?ErrorHandlingContext $context = null,
): ErrorDescriptor;
```

This signature belongs exclusively to `core/contracts`. It MUST NOT prescribe a concrete mapper registry, DI tag, fallback implementation, reporter backend, or transport-specific output type.

`core/contracts` MUST define only:

- stable ports;
- descriptor shape;
- context shape;
- enum vocabulary;
- format-neutral invariants;
- redaction and safety requirements at the contracts boundary.

`core/contracts` MUST NOT implement:

- exception mapper registries;
- concrete error handlers;
- HTTP middleware;
- CLI rendering;
- worker rendering;
- RFC7807/problem-details rendering;
- logging backends;
- tracing backends;
- metrics backends;
- DI registration;
- runtime discovery;
- generated runtime artifacts.

### `platform/errors`

`platform/errors` owns the canonical runtime error normalization implementation.

The selected `platform.errors` runtime module contributes the shared `Coretsia\Platform\Errors\ErrorHandler` implementation and binds `ErrorHandlerInterface` to that implementation through the canonical declarative container definitions.

`platform/errors` owns:

- `ErrorHandler` as the canonical format-neutral runtime normalization boundary;
- `ExceptionMapperRegistry` as the sole runtime dispatcher for tagged exception mappers;
- mapper discovery through `TagRegistry::all(ReservedTags::ERROR_MAPPER)`;
- deterministic first-non-null descriptor selection under canonical TagRegistry ordering;
- `DefaultExceptionMapper` as the fixed safe fallback outside tagged discovery;
- mandatory descriptor redaction through the existing `SensitiveDataRedactorInterface` binding;
- strict post-redaction reconstruction through `ErrorDescriptor::fromArray()`;
- optional reporter, logger, tracer, and meter integration with independent failure isolation.

The fixed canonical fallback descriptor uses code `CORETSIA_UNHANDLED_EXCEPTION`, message `Unhandled exception.`, severity `error`, `httpStatus = null`, and empty `extensions`. The fallback contains only static safe values and does not copy Throwable messages, traces, locations, previous exceptions, or caller metadata.

`platform.errors` requires `core.foundation` and `platform.redaction` through canonical module composition and matching Composer dependencies.

`platform/errors` MUST consume the existing redaction and observability ports. It MUST NOT introduce competing redaction engines, reporter implementations, mapper registries, container composition paths, or descriptor schemas.

A successfully constructed runtime `ErrorHandler` MUST return a canonical `ErrorDescriptor` for every `Throwable` passed to `handle()`, without propagating mapper, redaction, reporter, logger, tracer, meter, or timing collaborator failures.

This guarantee applies to the successfully constructed runtime handler. Pre-container bootstrap failures, module-composition failures, and service-construction failures remain owned by their existing boundaries.

`ErrorHandler` MUST NOT install a global PHP exception handler, create or finish a Kernel UnitOfWork, reset ContextStore, or produce transport-specific output.

`platform/errors` MUST NOT redefine the `ErrorDescriptor` field schema. The canonical descriptor shape remains owned by `core/contracts`.

### `platform/problem-details`

`platform/problem-details` owns HTTP problem-details adaptation.

It MAY adapt `ErrorDescriptor` to RFC7807 or another HTTP-specific representation.

It MUST treat `ErrorDescriptor.httpStatus` as an optional HTTP status hint only.

It MUST NOT require `core/contracts` to depend on:

- PSR-7;
- `platform/http` request objects;
- `platform/http` response objects;
- concrete middleware implementations;
- RFC7807-specific classes.

When installed, `platform/problem-details` is expected to wire `HttpErrorHandlingMiddleware` into:

```text
http.middleware.system_pre
```

with priority:

```text
1000
```

This is runtime policy, not a `core/contracts` dependency.

### HTTP, CLI, and Worker adapters

Runtime adapters own their transport-specific presentation and failure boundaries.

Adapters MAY render a normalized `ErrorDescriptor` into transport-specific output.

Adapter-owning packages MAY also contribute owner-specific `ExceptionMapperInterface` services through the canonical `error.mapper` tag, provided that they preserve the mapper-owner safety contract.

Contributing an owner-specific mapper MUST NOT transfer discovery, ordering, fallback selection, or global `ErrorHandlerInterface` binding ownership away from `platform/errors`.

No runtime adapter may introduce a second `Throwable`-to-`ErrorDescriptor` dispatch pipeline or an alternative normalized descriptor shape.

Adapters MUST NOT leak raw throwable details, stack traces, headers, cookies, request bodies, response bodies, raw SQL, credentials, tokens, private customer data, profile payloads, or absolute local paths.

## Canonical flow

The canonical runtime normalization flow is:

```text
Throwable
  ↓
ErrorHandlerInterface::handle()
  ↓
Capture canonical correlation id at most once
  ↓
Preserve explicit mapper context or construct a safe default context
  ↓
If reporting is enabled: start Stopwatch and error.map span independently
  ↓
ExceptionMapperRegistry::map()
  → TagRegistry::all(ReservedTags::ERROR_MAPPER)
  → preserve priority DESC / serviceId ASC order
  → lazily resolve candidates; first non-null ErrorDescriptor wins
  ↓
DefaultExceptionMapper if discovery is exhausted or registry dispatch fails
  ↓
ErrorDescriptor::toArray()
  ↓
SensitiveDataRedactorInterface::redactJsonLike()
  ↓
ErrorDescriptor::fromArray()
  ↓
Fixed safe fallback if redaction or reconstruction fails (no redaction retry)
  ↓
Final format-neutral ErrorDescriptor
  ↓
If reporting is enabled: stop Stopwatch if started, emit metrics,
finalize and end the span if created
  ↓
If reporting is enabled and reporter filters allow: report once with safe context
  ↓
Return ErrorDescriptor to the requesting runtime consumer
```

The canonical runtime handler MUST apply one fixed redaction context:

```text
scope: errors.descriptor
mode: placeholder
```

The shared redactor is mandatory even when error reporting is disabled.

An invalid redaction result, a thrown redactor exception, or failed descriptor reconstruction MUST select the fixed safe fallback. The handler MUST NOT return the unredacted candidate, retry mapper discovery, or recursively redact the fallback.

Correlation enrichment MUST be invocation-local. Explicit mapper context MUST remain authoritative for mapping, while optional reporting MUST receive a separately constructed sanitized context without arbitrary caller metadata.

Reporter filtering by minimum severity, excluded codes, and sampling MUST occur only after normalization. These filters MUST NOT change mapper discovery, descriptor selection, mandatory redaction, or mapping telemetry.

Optional reporting, tracing, metrics, and timing operations MUST NOT replace the selected descriptor or cause recursive error handling.

The stable normalization point remains:

```text
ErrorDescriptor
```

Transport-specific representations MUST be derived from the canonical descriptor and MUST NOT become an alternative internal error model.

## Mapper discovery

Exception mapper discovery and dispatch are runtime-owned by `platform/errors`.

The canonical reserved discovery tag is:

```text
error.mapper
```

The canonical code-level identifier is:

```text
Coretsia\Foundation\Tag\ReservedTags::ERROR_MAPPER
```

The tag is registered in `docs/ssot/tags.md`. The contracts package MAY reference the tag as runtime policy but MUST NOT declare its identifier or own its runtime semantics.

`ExceptionMapperRegistry` MUST enumerate mapper registrations exclusively through:

```text
TagRegistry::all(ReservedTags::ERROR_MAPPER)
```

The registry MUST preserve the existing Foundation TagRegistry invariants:

- priority descending;
- service id ascending under byte-order `strcmp`;
- first-wins handling of duplicate `(tag, serviceId)` registrations.

Mapper registration metadata MUST be exactly `[]`. The actual priority MUST be supplied through canonical tag registration, not metadata.

Mapper services MUST be resolved lazily through their registered service ids and MUST implement `ExceptionMapperInterface`.

The registry MUST preserve candidate order without re-sorting, re-deduplicating, filesystem scanning, or maintaining a separate mapper catalog.

A mapper returning `null` declines the exception. The first non-null `ErrorDescriptor` wins.

Invalid metadata, failed service resolution, invalid service types, and mapper execution failures MUST be isolated to the affected candidate. Subsequent candidates MUST remain eligible in canonical order.

Mapper failures MAY produce only fixed safe diagnostic events and low-cardinality reason tokens when the configured diagnostics policy allows them. Raw Throwable details, mapper exceptions, service identifiers, and caller metadata MUST NOT be disclosed.

Mapper failures MUST NOT introduce a second exception hierarchy or alter the canonical error contracts.

An exhausted or empty registry returns `null` to `ErrorHandler`, which selects the fixed `DefaultExceptionMapper` fallback.

`DefaultExceptionMapper` MUST NOT be registered under `error.mapper`. No adapter or contributing mapper owns the final fallback policy.

Non-owner packages using this tag MUST follow the canonical tag contract and MUST NOT define competing mapper registries, metadata schemas, discovery mechanisms, ordering rules, or global error-handler bindings.

## Forbidden dependency directions

### Contracts dependency law

`core/contracts` MUST NOT depend on:

- `platform/*`;
- `integrations/*`;
- `Psr\Http\Message\*`;
- `platform/http` package;
- `platform/cli` package;
- worker runtime packages;
- concrete logger implementations;
- concrete tracing implementations;
- concrete metrics implementations;
- concrete exception mapper registries;
- concrete problem-details renderers;
- vendor SDKs;
- generated architecture artifacts.

### Platform dependency law

Runtime platform packages MAY depend on `core/contracts`.

Runtime platform packages MUST NOT force `core/contracts` to depend back on platform packages.

Allowed direction:

```text
platform/errors → core/contracts
platform/problem-details → core/contracts
platform/http → core/contracts
platform/cli → core/contracts
worker runtime package → core/contracts
```

Forbidden direction:

```text
core/contracts → platform/errors
core/contracts → platform/problem-details
core/contracts → platform/http
core/contracts → platform/cli
core/contracts → worker runtime package
```

### Adapter dependency law

Adapters MAY adapt from contracts to transport-specific output.

Adapters MUST NOT push transport-specific types into contracts.

Forbidden examples:

```text
ErrorDescriptor contains PSR-7 request
ErrorDescriptor contains PSR-7 response
ErrorDescriptor contains problem-details object
ErrorDescriptor contains HTTP headers
ErrorDescriptor contains CLI output object
ExceptionMapperInterface requires HTTP request
ErrorHandlerInterface requires PSR-7 response
```

## ErrorDescriptor authority

The single human-readable field reference for `ErrorDescriptor` is:

```text
docs/ssot/error-descriptor.md
```

`docs/ssot/observability-and-errors.md` remains the ports, boundary, payload, and redaction overview.

`docs/ssot/observability-and-errors.md` MUST NOT redefine a competing field-by-field `ErrorDescriptor` schema.

Runtime packages, adapters, tests, and documentation MUST reference `docs/ssot/error-descriptor.md` for the canonical field meanings and examples.

## Runtime adapter policy

Runtime adapters MUST preserve the canonical descriptor semantics.

### HTTP adapter policy

HTTP adapters MAY map:

```text
ErrorDescriptor → RFC7807/problem-details
```

HTTP adapters MAY use `httpStatus` when present.

HTTP adapters MUST define safe fallback status behavior when `httpStatus` is null.

HTTP adapters MUST NOT expose unsafe `extensions`.

HTTP adapters MUST NOT emit raw throwable data.

### CLI adapter policy

CLI adapters MAY map:

```text
ErrorDescriptor → CLI diagnostic output
```

`platform.cli` consumes the canonical `ErrorHandlerInterface` supplied by the selected `platform.errors` module.

`CliApplication` MUST invoke the canonical handler for normal-path Throwable normalization and MUST NOT construct or use a competing CLI-owned error handler.

`platform.cli` contributes `CliExceptionMapper` as an owner-specific `ExceptionMapperInterface` implementation through `ReservedTags::ERROR_MAPPER`, using canonical tag priority `100` and empty metadata.

`CliExceptionMapper` owns only the classification of its allowlisted CLI and Kernel Ops exceptions into safe `ErrorDescriptor` values. It MUST preserve the existing public error codes and reason tokens without copying raw Throwable messages, previous exceptions, traces, or arbitrary caller metadata.

CLI-owned exception classification MUST remain separate from CLI transport rendering.

`ExceptionRenderer` owns CLI-specific presentation of normalized descriptors. The canonical fallback descriptor:

```text
CORETSIA_UNHANDLED_EXCEPTION
Unhandled exception.
```

MUST be rendered through the existing CLI public fallback:

```text
CORETSIA_CLI_INTERNAL_ERROR
Internal CLI error.
```

This translation is CLI presentation policy, not a second exception mapping pass.

CLI command output buffering, formatting, suggestions, exit codes, and ultra-early bootstrap failure handling remain owned by their existing CLI boundaries.

HTTP and Worker are not wired to `ErrorHandlerInterface` by this epic; they retain their owner-defined failure boundaries. Any adapter consumption of the canonical handler requires an explicit integration contract, and Worker task settlement must continue receiving the original `Throwable`.

CLI output MUST remain safe.

CLI output MUST NOT include raw stack traces, secrets, raw payloads, raw SQL, credentials, tokens, cookies, private customer data, or absolute local paths.

### Worker adapter policy

Worker adapters MAY map:

```text
ErrorDescriptor → worker failure result
```

Worker failure output MUST remain safe.

Worker failure output MUST NOT expose raw message payloads, queue vendor objects, credentials, tokens, profile payloads, raw SQL, or private customer data.

## Redaction boundary

Error normalization MUST follow the global observability redaction policy from:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
```

Errors, logs, spans, metrics, health output, CLI output, and worker output MUST NOT leak:

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
- raw SQL;
- raw profile payloads;
- raw persistence payloads;
- private customer data;
- absolute local paths.

Safe diagnostics MAY use:

```text
hash(value)
len(value)
```

Raw values MUST NOT be printed, logged, traced, exported, or rendered.

## Throwable handling

Mappers MAY inspect `Throwable` internally.

Mappers MAY return `null` when they do not handle the throwable.

The canonical `ExceptionMapperRegistry` MUST continue through registered mapper candidates in TagRegistry order until the first non-null `ErrorDescriptor` is selected or all candidates are exhausted. Final fallback selection belongs exclusively to `ErrorHandler`, outside tagged mapper dispatch.

Mappers MUST NOT expose raw `Throwable` objects through `ErrorDescriptor`.

Mappers MUST NOT expose stack traces by default.

Mappers MUST NOT place raw exception messages into `ErrorDescriptor.message` when those messages may contain secrets, raw paths, raw SQL, raw payload values, tokens, credentials, private customer data, or environment-specific bytes.

Mappers SHOULD convert implementation-specific exceptions into stable error codes.

Mappers MUST derive safe, bounded `ErrorDescriptor` extension metadata before descriptor construction.

Mappers MUST NOT rely on `ErrorDescriptor` to redact arbitrary producer input.

Known forbidden extension channels are rejected by `ErrorDescriptor` itself, but producer-owned derivation remains mandatory for arbitrary source values.

Mappers MUST NOT rely on `ErrorDescriptor` to truncate, drop, resize, or otherwise repair oversized producer metadata.

`ErrorDescriptor` enforces the canonical extension resource budget fail-closed. The canonical limits are owned by `docs/ssot/error-descriptor.md`.

Correlation metadata is context, not normalized error-descriptor data.

When available, a safe correlation identifier MUST remain in `ErrorHandlingContext.correlationId` or another owner-approved runtime context channel.

Mappers MUST NOT duplicate `correlationId` or `requestId` into `ErrorDescriptor.extensions`.

A runtime adapter that needs a safe request identifier MAY read it from the owning runtime context when that adapter's policy explicitly permits the use.

Safe mapper-owned extension metadata MAY include:

```text
operation
outcome
reason
count
hash(value)
len(value)
```

Unsafe mapper input MUST NOT be copied as descriptor extensions, including:

```text
raw throwable message
raw SQL
raw request body
raw credential
raw path
```

## Stability requirements

The canonical error normalization flow MUST remain stable across runtime boundaries.

The same logical error category SHOULD map to the same stable error code regardless of whether the runtime boundary is HTTP, CLI, worker, scheduler, or another unit of work.

Transport-specific differences belong to adapters, not to `ErrorDescriptor`.

## Verification evidence

This document is doc-only.

Current contracts-level enforcement evidence includes:

```text
packages/core/contracts/tests/Contract/ContractsDoNotReferencePsr7ContractTest.php
packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsAreJsonLikeContractTest.php
packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsAreBoundedContractTest.php
packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsEnforceRedactionContractTest.php
packages/core/contracts/tests/Contract/ErrorDescriptorFieldSetIsStableContractTest.php
packages/core/contracts/tests/Contract/ErrorDescriptorHttpStatusIsOptionalContractTest.php
packages/core/contracts/tests/Contract/ErrorDescriptorShapeContractTest.php
packages/core/contracts/tests/Contract/ErrorPortsShapeContractTest.php
```

Current runtime enforcement evidence includes:

```text
packages/platform/errors/tests/Contract/ErrorHandlerNeverThrowsContractTest.php
packages/platform/errors/tests/Integration/RegistryDeterministicOrderTest.php
packages/platform/errors/tests/Integration/MapperLazyResolutionTest.php
packages/platform/errors/tests/Integration/MapperFailureIsolationTest.php
packages/platform/errors/tests/Integration/DefaultMapperCatchesAnyThrowableTest.php
packages/platform/errors/tests/Integration/ErrorDescriptorIsRedactedTest.php
packages/platform/errors/tests/Integration/ErrorHandlingContextIsolationTest.php
packages/platform/errors/tests/Integration/ErrorsModuleCompositionTest.php
packages/platform/cli/tests/Unit/CliExceptionMapperTest.php
packages/platform/cli/tests/Integration/CliCanonicalErrorHandlerIntegrationTest.php
```

These tests verify the canonical runtime normalization flow, mapper dispatch, fallback safety, redaction, context isolation, module composition, and CLI adapter integration.

## Non-goals

This SSoT does not define:

- concrete HTTP middleware class shape;
- concrete CLI renderer;
- concrete worker failure renderer;
- problem-details JSON field formatting;
- logging backend implementation;
- tracing backend implementation;
- metrics backend implementation;
- DI provider code;
- generated artifacts.

## Cross-references

- [ErrorDescriptor SSoT](./error-descriptor.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Tag Registry](./tags.md)
- [DTO Policy](./dto-policy.md)
- [SSoT Index](./INDEX.md)
