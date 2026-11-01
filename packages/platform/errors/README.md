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

# coretsia/platform-errors

`platform/errors` is the canonical format-neutral Throwable normalization runtime package for Coretsia.

Scope: runtime `ErrorHandlerInterface` implementation, deterministic `error.mapper` dispatch through the Foundation `TagRegistry`, an untagged fixed safe fallback, mandatory shared descriptor redaction and strict reconstruction, invocation-local correlation enrichment, optional reporter filtering, safe mapper-failure diagnostics, and isolated mapping observability.

Out of scope: error contract or descriptor schema ownership, custom redaction engines, observability exporters and backends, transport rendering, HTTP problem-details production, CLI output formatting, Worker task settlement, Kernel module planning, Kernel UnitOfWork orchestration, reset infrastructure, global PHP exception handlers, and repository tooling.

## Package identity

- Path: `packages/platform/errors`
- Package id: `platform/errors`
- Composer name: `coretsia/platform-errors`
- Module id: `platform.errors`
- Namespace: `Coretsia\Platform\Errors\*` (PSR-4: `src/`)
- Kind: runtime
- Config root: `errors`

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions MUST NOT be used.

## Dependency policy

The package declares these production Composer dependencies:

- `coretsia/core-contracts`
- `coretsia/core-foundation`
- `coretsia/platform-redaction`
- `psr/container`
- `psr/log`

The package's `composer.json` is authoritative for exact dependency constraints. Internal Coretsia package constraints follow the active monorepo release line; external dependency constraints remain owned by the package manifest.

`core/contracts` owns public error and security ports and the canonical descriptor shape. `core/foundation` owns `TagRegistry`, baseline observability services, the DI definition model, correlation infrastructure, and `Stopwatch`.

`platform/redaction` is an explicit runtime installation and module-composition dependency. This package consumes its implementation exclusively through `SensitiveDataRedactorInterface`; it MUST NOT import concrete `Coretsia\Platform\Redaction\*` classes. That Composer dependency is not a new compile-time Deptrac allowance for concrete Redaction imports.

Forbidden production dependencies and imports include `core/kernel`, `platform/http`, `platform/problem-details`, CLI or Worker implementations, PSR-7/PSR-15 request and response APIs, integration packages, and repository tooling under `tools/**`.

This package MUST NOT duplicate a reporter, redactor, tag registry, config loader, exception hierarchy, or runtime adapter.

## Runtime responsibilities

The module provides the following services through canonical declarative container definitions:

- `Coretsia\Platform\Errors\ErrorHandler`, implementing `ErrorHandlerInterface`;
- `Coretsia\Platform\Errors\Mapper\ExceptionMapperRegistry`, dispatching contributed mappers;
- `Coretsia\Platform\Errors\Mapper\DefaultExceptionMapper`, providing the fixed fallback;
- the alias `ErrorHandlerInterface::class` to the canonical `ErrorHandler::class` service.

`ErrorsServiceProvider` declares required external services without constructing optional sinks or resolving mapper candidates. `ErrorsServiceFactory` reads and validates the merged errors reporting policy at service construction and passes immutable configuration values to the runtime services. These definitions apply to source and compiled containers without a separate wiring path.

The handler is stateless between invocations. No previous Throwable, descriptor, context, span, timing token, mapper list, or mutable reporting state is retained.

## Module selection and composition

The `platform.errors` runtime module declares these module requirements:

```text
core.foundation
platform.redaction
```

Kernel's canonical `ModulePlan` resolves the selected module and its dependency closure. Composer package installation is not implicit runtime module enablement. The module's provider is `Coretsia\Platform\Errors\Provider\ErrorsServiceProvider` and its package defaults path is `config/errors.php`.

The handler binding exists only after successful module composition and service construction. Missing or excluded required modules fail through the established Kernel/CLI host boundary; there is no hidden fallback provider or second ModulePlan.

## Canonical normalization

The public format-neutral contracts are:

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

The runtime flow is:

```text
Throwable
  -> ErrorHandler::handle()
  -> capture canonical correlation snapshot (at most once)
  -> use explicit mapping context or construct a safe default
  -> start optional Stopwatch / error.map span when reporting is enabled
  -> ExceptionMapperRegistry (canonical tag order, lazy resolution)
  -> first non-null descriptor or DefaultExceptionMapper fallback
  -> ErrorDescriptor::toArray()
  -> SensitiveDataRedactorInterface::redactJsonLike()
  -> ErrorDescriptor::fromArray()
  -> fixed safe fallback if redaction or reconstruction fails
  -> final format-neutral ErrorDescriptor
  -> optional metrics, span finalization, and eligible reporting
  -> return the descriptor to the runtime consumer
```

Mapping, fallback selection, mandatory redaction, and strict reconstruction run even when optional reporting is disabled. This package does not render or serialize a transport response.

## Mapper registration and deterministic discovery

Contributing packages MAY register their owner-specific `ExceptionMapperInterface` services under the existing `error.mapper` tag. The canonical identifier is `ReservedTags::ERROR_MAPPER` and the tag's semantic owner is `platform/errors`. Foundation `TagRegistry` owns ordering and duplicate registration behavior.

The mapper contract requires:

- Every tag service id identifies an available service implementing `ExceptionMapperInterface`.
- Tag metadata MUST be `[]`; mapper priority belongs to the canonical tag priority argument.
- `TagRegistry::all(ReservedTags::ERROR_MAPPER)` provides priority descending, service id ascending by `strcmp`, and duplicate `(tag, serviceId)` first-wins semantics.
- `ExceptionMapperRegistry` MUST NOT re-sort, de-duplicate, scan the filesystem, or create another mapper catalog.
- Mapper services are resolved lazily through `ContainerInterface::get($serviceId)` during each dispatch, not during registry construction.
- `null` declines the Throwable; the first non-null `ErrorDescriptor` wins.
- Invalid metadata, resolution failures, invalid service types, and thrown mapper failures are isolated per candidate; later registered candidates remain eligible.
- An empty or exhausted registry returns `null` to `ErrorHandler`; fallback is selected outside tagged discovery.

`DefaultExceptionMapper` MUST NOT be tagged as `error.mapper`. An adapter-owned mapper must not register a competing global handler or implement its own dispatch/fallback pipeline.

## Safe fallback

`DefaultExceptionMapper` returns a deterministic descriptor for any otherwise unhandled Throwable:

```text
code: CORETSIA_UNHANDLED_EXCEPTION
message: Unhandled exception.
severity: error
httpStatus: null
extensions: []
```

Fallback is selected when no registered mapper claims the Throwable, registry-level discovery fails, or mandatory redaction/reconstruction rejects a candidate. A redaction failure MUST NOT return the original candidate, retry discovery, or recursively redact the fallback.

The fallback never copies raw exception messages, previous exceptions, file paths, stack traces, or caller metadata. Failures in optional observability collaborators do not replace the final descriptor.

## Configuration

The package owns exactly the `errors` configuration root:

- Defaults: `packages/platform/errors/config/errors.php`
- Rules: `packages/platform/errors/config/rules.php`
- Composer metadata: `extra.coretsia.defaultsConfigPath = config/errors.php`

The defaults file returns the root subtree without an enclosing `errors` key. Kernel `ConfigValidator` validates the declarative ruleset. `ErrorsServiceFactory` independently rejects missing, incorrectly typed, or out-of-range reporting values with a fixed safe construction error.

Baseline defaults include:

```php
[
    'reporting' => [
        'enabled' => false,
        'min_severity' => 'info',
        'exclude_codes' => [],
        'sample_rate' => 10000,
        'mapper_failures' => [
            'enabled' => false,
        ],
    ],
]
```

Important config rules:

- `errors.reporting.enabled=false` by default; reporting, mapper-failure diagnostics, tracing, and metrics emission are disabled while normalization and mandatory redaction remain active.
- `errors.reporting.enabled` must be a boolean.
- `errors.reporting.min_severity` must be `info`, `warning`, `error`, or `critical`; the default is `info`.
- Canonical severity order is `info < warning < error < critical`.
- `errors.reporting.exclude_codes` must be a list of non-empty strings without whitespace; the default is `[]`.
- Excluded codes are compared exactly and case-sensitively against the final descriptor code.
- `errors.reporting.sample_rate` must be an integer from `0` to `10000` inclusive, measured in basis points; the default is `10000`.
- A sample rate of `0` suppresses all reporter invocations, `10000` permits all otherwise eligible invocations, and `1..9999` uses probabilistic sampling.
- `errors.reporting.mapper_failures.enabled` must be a boolean; the default is `false`.
- Mapper-failure diagnostics require both `errors.reporting.enabled=true` and `errors.reporting.mapper_failures.enabled=true`.
- Minimum severity, excluded codes, and sampling filter only normalized reporter calls; they do not affect mapper discovery, descriptor selection, mandatory redaction, fallback, mapping spans, or metrics.
- Mapper-failure diagnostics are independent of reporter-only minimum severity, excluded codes, and sampling.
- `reporting` and `reporting.mapper_failures` must contain only their declared keys; missing required or unknown keys are rejected.
- Reserved `@*` configuration keys are forbidden.
- Invalid reporting configuration causes service construction to fail with `Invalid errors reporting configuration.`
- No configuration key may disable error normalization or redaction, expose raw Throwable details, change mapper discovery order, or register mapper classes.

## Context and correlation

`ErrorHandler` queries the canonical `CorrelationIdProviderInterface` at most once per invocation and captures its value locally. If no explicit `ErrorHandlingContext` is supplied, the mapper receives a context with operation `map`, the captured safe correlation id or `null`, and empty metadata.

An explicit mapping context remains authoritative and is not mutated. Optional reporting gets a separate context with operation `map`, the invocation's captured safe correlation id or `null`, and no arbitrary caller metadata. Rejected or throwing correlation enrichment cannot affect mapper dispatch or the selected descriptor.

This package does not access or modify `ContextStore` or `ContextAccessorInterface`. Correlation and request identifiers must not be copied into `ErrorDescriptor.extensions` or metric labels.

## UnitOfWork and reset boundary

Kernel exclusively owns UnitOfWork creation, completion, failure handling, hooks, and reset timing. `ErrorHandler` does not create or finish a Kernel UoW and does not write or reset Context state.

Because the handler, registry, factory, and fallback are stateless across invocations, `platform/errors` introduces no `kernel.reset` or `kernel.stateful` registrations and no package-local reset mechanism. Repeated calls retain no earlier invocation's Throwable or optional-sink state.

## Observability

Optional error observability uses existing injected ports and the real Foundation `Stopwatch`; this package creates no clock, tracer, meter, reporter implementation, or timing abstraction.

When `errors.reporting.enabled = true`, `ErrorHandler` attempts to start timing and an `error.map` span before mapper dispatch, then attempts:

- `error.map_total`: counter increment by `1`;
- `error.map_duration_ms`: non-negative integer-millisecond observation (safe `0` if timing is unavailable);
- span finalization and exactly one `end()` attempt for a successfully created span;
- optional `ErrorReporterPortInterface::report()` when the final descriptor satisfies reporter-only severity, excluded-code, and sampling policy.

Metric labels and finalized span attributes use only fixed low-cardinality values: `operation = map` and `outcome = ok|fallback`. Raw exception details, service ids, caller metadata, and correlation identifiers are not metric labels.

The reporter receives only the normalized descriptor and sanitized reporting context. Foundation's `NoopErrorReporter` remains a valid default binding; `platform/errors` requires the port instead of replacing it. Reporter, tracer, meter, `Stopwatch`, and span-end failures are independently isolated and do not trigger another normalization pass.

The `ExceptionMapperRegistry` MAY emit a PSR-3 warning named `error.mapper_failed` when both reporting and mapper-failure diagnostics are enabled. Its context includes only the fixed `operation = map` and one of these reason tokens:

```text
invalid_metadata
service_resolution_failed
invalid_service
mapper_failed
```

The logger is not invoked with raw Throwable messages, stack traces, failed mapper identifiers, or caller metadata. Logger failures do not interrupt discovery. Mapper diagnostics are independent of reporter minimum severity, excluded codes, and sampling.

## Errors

The runtime `ErrorHandler` implements a never-throws normalization boundary for mapper, registry, redaction, and optional collaborator failures **after successful service construction**. The result is a canonical `ErrorDescriptor`, not an HTTP problem response, CLI output record, Worker task result, or a wrapper containing the raw Throwable.

Invalid package reporting configuration can prevent service construction. Missing required module dependencies or unavailable required container bindings fail at module composition or service resolution. Such pre-handler failures are not covered by the constructed handler's runtime guarantee.

`ErrorDescriptor::fromArray()` performs strict contracts-owned shape and value validation. Its acceptance does not classify arbitrary strings as safe. Fallback code and public message are fixed; no package-specific exception hierarchy is introduced for mapper errors.

## Security / Redaction

Descriptors MUST be safe by construction. Mapper owners must derive approved stable codes, safe human-readable messages, and bounded safe extensions; they must not copy raw authorization headers, cookies, session ids, credentials, secrets, SQL, request/response bodies, private customer data, original Throwable messages, traces, or previous exceptions.

The handler always applies the existing shared `SensitiveDataRedactorInterface` port with `scope = errors.descriptor` and `RedactionMode::Placeholder`, then reconstructs the result through `ErrorDescriptor::fromArray()`. Invalid output, redactor failure, invalid extension keys, and canonical resource-budget violations fail closed to the fixed fallback.

Generic redaction is defense in depth. A classifier non-match or a successful schema reconstruction does not prove that arbitrary producer text may be disclosed. Safe derived counts, lengths, hashes, and opaque identifiers require applicable owner disclosure and non-reconstruction policy. The package does not provide a raw error-detail debug mode, local sensitive-data classifier, or second masking engine.

## Tests and architectural restrictions

The canonical monorepo PHPUnit runner discovers this package's `tests/Unit`, `tests/Contract`, and `tests/Integration` suites. From the repository root:

```bash
composer test -- --package=platform/errors
composer test
composer quality
composer arch
```

Tests verify fixed fallback mapping, mandatory shared redaction, malformed-redactor fail-closed behavior, mapper ordering and lazy resolution, candidate and registry failure isolation, reporter filtering and sampling, Context/correlation isolation, UnitOfWork non-interference, module dependency closure, source/compiled definition parity, and CLI mapper integration.

The package MUST NOT contain a second tag registry, consume concrete Redaction implementation classes, define a duplicate reporter or redactor binding, implement Kernel UoW/reset operations, import transport-specific implementations, or introduce package-local PHPUnit configuration. Canonical tooling and module composition remain outside this package's runtime.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Errors package source](https://github.com/coretsia/monorepo/tree/main/packages/platform/errors)
- [Errors boundary SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/errors-boundary.md)
- [ErrorDescriptor SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/error-descriptor.md)
- [Observability and Errors SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability-and-errors.md)
- [Observability SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability.md)
- [Sensitive Data Redaction SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/sensitive-data-redaction.md)
- [Tags SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/tags.md)
- [Config Roots SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/config-roots.md)
- [Modules and Manifests SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/modules-and-manifests.md)
- [Runtime Container Definitions SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/runtime-container-definitions.md)
- [Compiled Container SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/compiled-container.md)
- [Context Store SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/context-store.md)
- [ADR-0036: Platform errors ErrorHandler and ExceptionMapper registry](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0036-platform-errors-errorhandler-exception-mapper.md)
