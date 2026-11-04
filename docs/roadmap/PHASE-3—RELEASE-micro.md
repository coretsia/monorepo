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

## PHASE 3 — RELEASE: micro (перший релізний режим, Non-product doc)

### 3.10.0 Platform errors: ErrorHandler + ExceptionMapper registry (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.10.0"
owner_path: "packages/platform/errors/"

package_id: "platform/errors"
composer: "coretsia/platform-errors"
kind: runtime
module_id: "platform.errors"

goal: "Будь-який Throwable, переданий успішно сконструйованому runtime ErrorHandler, нормалізується в deterministic ErrorDescriptor без витоку помилок mapper/redaction/observability collaborators."
provides:
- "Canonical flow: Throwable → ErrorDescriptor (format-neutral), без PSR-7 залежностей"
- "Deterministic ExceptionMapper registry через DI tag `error.mapper`"
- "ErrorHandler noop-safe + policy: never throws (завжди повертає ErrorDescriptor)"
- "Security: safe-by-construction ErrorDescriptor.code/message/extensions + shared redaction defense in depth"
- "Cutline impact: blocks Phase 3 platform baseline cutline"
- "`Deterministic ExceptionMapper registry` означає: deterministic enumeration via TagRegistry invariants (не custom sorting)."

tags_introduced: []
config_roots_introduced:
- "errors"

artifacts_introduced: []
adr: "docs/adr/ADR-0036-platform-errors-errorhandler-exception-mapper.md"
ssot_refs:
- "docs/ssot/errors-boundary.md"
- "docs/ssot/error-descriptor.md"
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/observability.md"
- "docs/ssot/sensitive-data-redaction.md"
- "docs/ssot/tags.md"
- "docs/ssot/config-roots.md"
- "docs/ssot/modules-and-manifests.md"
- "docs/ssot/runtime-container-definitions.md"
- "docs/ssot/compiled-container.md"
- "docs/ssot/context-store.md"
- "docs/ssot/modes.md"
- "docs/ssot/application-dependency-sync.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 2.25.0 — shared sensitive-data redaction boundary implemented:
    - `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` exists
    - `platform.redaction` provides the canonical concrete implementation
    - Foundation supplies the shared json-like normalization primitives
  - Foundation declarative container definitions, TagRegistry ordering, and default observability bindings exist.

- Required deliverables (already existing):
  - `packages/core/foundation/src/Tag/ReservedTags.php` already declares `ERROR_MAPPER = 'error.mapper'`
  - `docs/ssot/tags.md` already reserves `error.mapper` for `platform/errors`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor` and the canonical error ports already exist
  - `Coretsia\Foundation\Observability\Errors\NoopErrorReporter` already implements the default reporter port
  - `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` is already bound by the selected `platform.redaction` module

- Required config roots/keys:
  - This epic owns the new `errors` config root.
  - Package-owned configuration keys:
    - `errors.reporting.enabled`
    - `errors.reporting.min_severity`
    - `errors.reporting.exclude_codes`
    - `errors.reporting.sample_rate`
    - `errors.reporting.mapper_failures.enabled`
  - Reporter filters MUST NOT affect mapper discovery, normalization, redaction, fallback selection, or mapping telemetry.
  - Mapper-failure diagnostics MUST remain independently controlled from normalized error-report filtering.
  - Error normalization and security redaction MUST NOT be disabled through configuration.
  - No environment-specific raw Throwable disclosure switch may be introduced.

- Required tags:
  - `error.mapper` is an existing reserved tag owned by `platform/errors`.
  - `ReservedTags::ERROR_MAPPER` is the existing canonical code-level identifier.
  - This epic MUST NOT modify `ReservedTags.php` or introduce another tag constant.
  - This epic implements mapper discovery semantics, not tag registration infrastructure.

- Required config roots registry:
  - The `errors` root does not yet exist in `docs/ssot/config-roots.md`.
  - This epic adds exactly one canonical registry row for that root.
  - Config defaults and declarative rules remain exclusively owned by `platform/errors`.

- Tag usage (MUST):
  - `ExceptionMapperRegistry` MUST enumerate mapper registrations only through:
    - `TagRegistry::all(ReservedTags::ERROR_MAPPER)`
  - Preserve the canonical registry invariants:
    - priority DESC
    - serviceId ASC using `strcmp`
    - duplicate `(tag, serviceId)` registrations: first wins
  - The registry MUST NOT re-sort, re-deduplicate, scan the filesystem, or build another mapper catalog.
  - Mapper services MUST be resolved lazily from their tagged service ids.
  - `error.mapper` tag metadata MUST be an empty map.
  - Priority MUST be supplied through the canonical tag priority argument, not through metadata.
  - The default fallback mapper MUST NOT be registered under `error.mapper`.

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlingContext`
  - `Coretsia\Contracts\Observability\Errors\ErrorSeverity`
  - `Coretsia\Contracts\Security\SensitiveDataRedactorInterface`
  - `Coretsia\Contracts\Security\RedactionContext`
  - `Coretsia\Contracts\Security\RedactionMode`
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface`
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Tracing\SpanInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Foundation\Tag\TagRegistry`
  - `Coretsia\Foundation\Tag\ReservedTags`
  - `Coretsia\Foundation\Time\Stopwatch`
  - `Psr\Container\ContainerInterface`
  - `Psr\Log\LoggerInterface`

- Cemented APIs (MUST):
  - `ErrorHandlerInterface::handle(Throwable $throwable, ?ErrorHandlingContext $context = null): ErrorDescriptor`
  - `ErrorDescriptor::fromArray(array $payload): self` — contracts-owned strict reconstruction API introduced by this epic.
  - `ExceptionMapperInterface::map(Throwable $throwable, ?ErrorHandlingContext $context = null): ?ErrorDescriptor`
  - `ErrorReporterPortInterface::report(ErrorDescriptor $descriptor, ?ErrorHandlingContext $context = null): void`
  - `CorrelationIdProviderInterface::correlationId(): ?string`
  - Foundation owns safe correlation-id validation and missing-value handling.
  - The handler MUST NOT duplicate canonical correlation-id parsing or validation.
  - `SensitiveDataRedactorInterface::redactJsonLike(mixed $value, RedactionContext $context): mixed`
  - `MeterPortInterface::increment(string $name, int $delta = 1, array $labels = []): void`
  - `MeterPortInterface::observe(string $name, int $value, array $labels = []): void`
  - Missing or unreadable context values MUST NOT change the error-handling outcome.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Runtime-only Composer dependency:

- `platform/redaction` — required installation and module-composition dependency.
- The package MUST consume redaction only through `SensitiveDataRedactorInterface`.
- No PHP source import from `Coretsia\Platform\Redaction\*` is permitted.
- This Composer edge MUST NOT be added to the compile-time dependency matrix or Deptrac allowlist.

Module composition:

- `platform.errors` requires `core.foundation` and `platform.redaction`.
- Both module requirements MUST have matching direct Composer requirements.
- Selecting `platform.errors` MUST resolve its dependencies through the canonical Kernel ModulePlan.
- Composer package installation MUST NOT implicitly enable the runtime module.
- Exactly one `SensitiveDataRedactorInterface` binding MUST already be available before `ErrorHandler` resolution.
- The package MUST NOT instantiate the concrete redactor, register another fallback redactor, or manually append providers.
- No second ModulePlan, provider-composition pipeline, or dependency resolver may be introduced.

CLI consumer composition:

- `platform.cli` requires `platform.errors` as an explicit runtime module dependency.
- `coretsia/platform-cli` requires `coretsia/platform-errors` as a matching direct Composer dependency.
- The CLI dependency on `platform.redaction` remains direct because CLI output formatting independently consumes the redaction port.
- Selecting `platform.cli` MUST resolve `platform.errors` and `platform.redaction` through the existing Kernel ModulePlan.
- Missing or explicitly excluded `platform.errors` while `platform.cli` remains selected MUST fail closed at module composition.
- CLI MUST consume error normalization through `ErrorHandlerInterface`, not concrete `Coretsia\Platform\Errors\*` classes.
- CLI MUST contribute its own `ExceptionMapperInterface` implementation through the shared `error.mapper` tag.
- CLI MUST NOT provide a competing global `ErrorHandlerInterface` binding.
- Worker MUST NOT gain a mandatory `platform.errors` dependency without a separately justified worker runtime-consumption boundary.
- No additional Kernel preset root or second module composition mechanism is introduced.

Forbidden:

- `platform/http`
- `platform/problem-details`
- `Psr\Http\Message\*`
- `Psr\Http\Server\*`

#### Uses ports (API surface, NOT deps) (MUST)

- Error normalization:
  - `ErrorHandlerInterface`
  - `ExceptionMapperInterface`
  - `ErrorReporterPortInterface`
  - `ErrorDescriptor`
  - `ErrorHandlingContext`
  - `ErrorSeverity`
- Redaction:
  - `SensitiveDataRedactorInterface`
  - `RedactionContext`
  - `RedactionMode`
- Runtime infrastructure:
  - `ConfigRepositoryInterface`
  - `CorrelationIdProviderInterface`
  - `TagRegistry`
  - `ReservedTags`
  - `ContainerInterface`
  - `Stopwatch`
- Observability:
  - `TracerPortInterface`
  - `SpanInterface`
  - `MeterPortInterface`
  - `Psr\Log\LoggerInterface`

All runtime dependencies MUST be injected through the canonical container.

Package-local replacements for existing contracts, redaction implementations, reporter defaults, or observability ports are forbidden.

### Entry points / integration points (MUST)

- CLI:
  - This epic migrates normal CLI Throwable normalization to the canonical `ErrorHandlerInterface` supplied by `platform.errors`.
  - `platform.cli` MUST require `platform.errors` through matching Composer and module dependencies.
  - CLI-owned exception mapping MUST be contributed through `error.mapper`.
  - The legacy `CliErrorHandler` MUST be removed after its allowlisted mapping behavior is migrated.
  - `CliApplication` MUST consume `ErrorHandlerInterface`, not a transport-owned implementation of that port.
  - `ExceptionRenderer` remains CLI-owned and MUST preserve existing CLI-specific public fallback presentation.
  - CLI output buffering, redaction, formatting, exit codes, Kernel operations, and UoW ownership remain unchanged.
  - Ultra-early `doctor` and pre-container host-bootstrap failures MUST remain independent of `platform.errors` service resolution.
- HTTP:
  - N/A — HTTP error adaptation and rendering remain owned by `platform/problem-details`.
- Worker:
  - No mandatory `platform.errors` dependency is introduced for `platform.worker` by this epic.
  - Worker task settlement MUST continue receiving the original Throwable.
  - Kernel-owned UoW completion, failure, and reset ordering MUST remain unchanged.
  - Worker lifecycle/process/bootstrap protocols retain their existing fixed safe diagnostics.
  - Worker Console commands retain their owner-defined output and exit-code semantics.
  - A future explicit Worker integration MAY consume `ErrorHandlerInterface` for normalized reporting, without replacing task settlement or process-control protocol boundaries.
  - This epic MUST NOT modify Worker runtime or process lifecycle solely to force error-handler adoption.
- Kernel runtime:
  - The selected `platform.errors` module contributes the canonical `ErrorHandlerInterface` binding.
  - The handler MUST NOT start, finish, or replace a Kernel UnitOfWork.
  - No Kernel runtime hook is required for mapper discovery.
  - The handler is available only after successful module composition and service construction.
  - Pre-container bootstrap failures remain owned by their existing bootstrap boundaries.
  - This epic MUST NOT install global PHP exception/error handlers or modify `set_exception_handler()` behavior.
  - Normal-path CLI Throwable normalization is explicitly migrated to the canonical `ErrorHandlerInterface` by this epic.
  - CLI contributes owner-specific exception mapping through `error.mapper` and retains transport-specific rendering.
  - HTTP and Worker runtime adapters are not rewired by this epic.
  - Any future HTTP or Worker integration MUST preserve its owning runtime boundary and use the canonical port only through an explicit integration contract.
- Kernel hooks/tags:
  - `error.mapper` — deterministic registry ordering via Foundation TagRegistry invariants (no package-local re-sorting)
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Deletes

Legacy CLI normalization:

- [x] `packages/platform/cli/src/Diagnostics/CliErrorHandler.php`
  - [x] replace its allowlisted exception mapping with CLI-owned `ExceptionMapperInterface` implementation
  - [x] remove direct construction and DI registration of `CliErrorHandler`
  - [x] no compatibility handler, duplicate normalization pipeline, or fallback registry remains
  - [x] preserve existing public CLI error codes and reason tokens through the new canonical flow

Legacy CLI handler tests:

- [x] `packages/platform/cli/tests/Unit/CliErrorHandlerTest.php`
  - [x] replace with CLI-owned exception-mapper tests

- [x] `packages/platform/cli/tests/Integration/CliErrorHandlerMapsWorkflowExceptionsSafelyTest.php`
  - [x] replace with canonical handler integration tests
  - [x] preserve existing workflow error-code and output assertions

#### Creates

Package metadata and documentation:

- [x] `packages/platform/errors/composer.json`
  - [x] package name: `coretsia/platform-errors`
  - [x] type: `library`
  - [x] PHP requirement: `^8.4`
  - [x] production dependencies:
    - [x] `coretsia/core-contracts`: `^0.9.0`
    - [x] `coretsia/core-foundation`: `^0.9.0`
    - [x] `coretsia/platform-redaction`: `^0.9.0`
    - [x] `psr/container`: `^2.0`
    - [x] `psr/log`: `^3.0`
  - [x] production autoload:
    - [x] `Coretsia\Platform\Errors\` → `src/`
  - [x] test autoload:
    - [x] `Coretsia\Platform\Errors\Tests\` → `tests/`
  - [x] `extra.coretsia.kind = runtime`
  - [x] `extra.coretsia.moduleId = platform.errors`
  - [x] `extra.coretsia.moduleClass = Coretsia\Platform\Errors\Module\ErrorsModule`
  - [x] `extra.coretsia.providers = [Coretsia\Platform\Errors\Provider\ErrorsServiceProvider]`
  - [x] `extra.coretsia.requires = [core.foundation, platform.redaction]`
  - [x] `extra.coretsia.conflicts = []`
  - [x] `extra.coretsia.defaultsConfigPath = config/errors.php`
  - [x] MUST NOT declare `coretsia/core-kernel`, `platform/http`, or `platform/problem-details` as production dependencies
  - [x] Composer metadata MUST match the module class and canonical package index

- [x] `packages/platform/errors/LICENSE`
- [x] `packages/platform/errors/NOTICE`
- [x] `packages/platform/errors/SECURITY.md`
  - [x] follow canonical monorepo package scaffold and licensing conventions

- [x] `packages/platform/errors/README.md`
  - [x] describe canonical error normalization and contracts
  - [x] describe module selection and dependency closure
  - [x] document mapper registration and deterministic ordering
  - [x] document safe fallback behavior
  - [x] document config keys and defaults
  - [x] document redaction ownership and mandatory fail-closed behavior
  - [x] document Context and UoW boundaries
  - [x] document reporter, logger, tracer, and meter behavior
  - [x] document CLI/HTTP/Worker adapter ownership
  - [x] document errors, tests, and architectural restrictions

Module and DI:

- [x] `packages/platform/errors/src/Module/ErrorsModule.php`
  - [x] final module metadata class
  - [x] constants:
    - [x] `MODULE_ID = 'platform.errors'`
    - [x] `PACKAGE_ID = 'platform/errors'`
    - [x] `COMPOSER_PACKAGE = 'coretsia/platform-errors'`
    - [x] `KIND = 'runtime'`
    - [x] `CONFIG_ROOT = 'errors'`
  - [x] expose canonical `id()`, `packageId()`, `composerPackage()`, `kind()`, and `configRoot()` methods
  - [x] `providers()` returns exactly `[ErrorsServiceProvider::class]`
  - [x] MUST NOT construct containers, load config, resolve dependencies, or execute error handling
  - [x] MUST NOT introduce runtime module discovery or provider ordering

- [x] `packages/platform/errors/src/Provider/ErrorsServiceProvider.php`
  - [x] final class implements `ServiceProviderInterface` and `ContainerDefinitionProviderInterface`
  - [x] `register(ContainerBuilder $builder)` delegates to the canonical definition provider registration path
  - [x] `define(ContainerDefinitionBuilder $definitions, ContainerDefinitionContext $context)` contributes declarative services
  - [x] use `ContainerValueReference::service(...)` for service dependencies
  - [x] use canonical `classService()`, `classMethodFactory()`, and `alias()` definitions
  - [x] declare all external DI dependencies through `ContainerDefinitionBuilder::requireService()`
  - [x] every required service id MUST match the ordered factory argument references
  - [x] MUST NOT require `ContextAccessorInterface` or `ContextKeys` directly
  - [x] MUST NOT resolve the concrete Foundation `CorrelationIdProvider`
  - [x] register `DefaultExceptionMapper::class` through `classService()` with no constructor arguments
  - [x] register `ExceptionMapperRegistry::class` through:
    - [x] factory class: `ErrorsServiceFactory::class`
    - [x] method: `exceptionMapperRegistry`
    - [x] ordered service arguments:
      - [x] `TagRegistry::class`
      - [x] `ContainerInterface::class`
      - [x] `LoggerInterface::class`
      - [x] `ConfigRepositoryInterface::class`
  - [x] register `ErrorHandler::class` through:
    - [x] factory class: `ErrorsServiceFactory::class`
    - [x] method: `errorHandler`
    - [x] ordered service arguments:
      - [x] `ExceptionMapperRegistry::class`
      - [x] `DefaultExceptionMapper::class`
      - [x] `SensitiveDataRedactorInterface::class`
      - [x] `ErrorReporterPortInterface::class`
      - [x] `CorrelationIdProviderInterface::class`
      - [x] `TracerPortInterface::class`
      - [x] `MeterPortInterface::class`
      - [x] `Stopwatch::class`
      - [x] `ConfigRepositoryInterface::class`
  - [x] bind `ErrorHandlerInterface::class` through `alias(ErrorHandlerInterface::class, ErrorHandler::class)`
  - [x] all factory arguments MUST use `ContainerValueReference::service($serviceId)`
  - [x] all three concrete services are shared and contain no mutable invocation state
  - [x] provider registration and definition production MUST NOT instantiate the handler, registry, mapper, or any optional sink
  - [x] require existing:
    - [x] `ConfigRepositoryInterface`
    - [x] `ContainerInterface`
    - [x] `TagRegistry`
    - [x] `SensitiveDataRedactorInterface`
    - [x] `ErrorReporterPortInterface`
    - [x] `CorrelationIdProviderInterface`
    - [x] `TracerPortInterface`
    - [x] `MeterPortInterface`
    - [x] `LoggerInterface`
    - [x] `Stopwatch`
  - [x] MUST NOT bind or override `ErrorReporterPortInterface`
  - [x] MUST NOT bind or override `SensitiveDataRedactorInterface`
  - [x] MUST NOT register `DefaultExceptionMapper` under `error.mapper`
  - [x] MUST NOT register a duplicate `ERROR_MAPPER` constant
  - [x] MUST NOT contain closures in declarative service definitions
  - [x] MUST NOT resolve services during definition production
  - [x] source and compiled container definitions MUST remain equivalent

- [x] `packages/platform/errors/src/Provider/ErrorsServiceFactory.php`
  - [x] final stateless factory with public static construction methods
  - [x] canonical APIs:
    - [x] `exceptionMapperRegistry(TagRegistry $tags, ContainerInterface $container, LoggerInterface $logger, ConfigRepositoryInterface $config): ExceptionMapperRegistry`
    - [x] `errorHandler(ExceptionMapperRegistry $registry, DefaultExceptionMapper $fallback, SensitiveDataRedactorInterface $redactor, ErrorReporterPortInterface $reporter, CorrelationIdProviderInterface $correlations, TracerPortInterface $tracer, MeterPortInterface $meter, Stopwatch $stopwatch, ConfigRepositoryInterface $config): ErrorHandler`
  - [x] read the merged reporting configuration through `ConfigRepositoryInterface`
  - [x] required configuration values:
    - [x] `errors.reporting.enabled`: strict `bool`
    - [x] `errors.reporting.min_severity`: string-backed `ErrorSeverity` value
    - [x] `errors.reporting.exclude_codes`: `list<non-empty-string-no-ws>`
    - [x] `errors.reporting.sample_rate`: strict `int` in the inclusive range `0..10000`
    - [x] `errors.reporting.mapper_failures.enabled`: strict `bool`
  - [x] convert the validated minimum severity to the existing `ErrorSeverity` enum
  - [x] missing, incorrectly typed, or out-of-range reporting configuration MUST fail during service construction
  - [x] configuration validation MUST NOT create another config loader, ruleset validator, or descriptor schema
  - [x] pass the validated immutable reporting values explicitly to runtime services through constructor arguments
  - [x] `ExceptionMapperRegistry` receives the canonical TagRegistry, PSR-11 container, logger, reporting-enabled flag, and mapper-failure diagnostics flag
  - [x] `ErrorHandler` receives only its used collaborators, reporting-enabled flag, minimum severity, excluded codes, and sample rate
  - [x] `LoggerInterface` is injected into `ExceptionMapperRegistry` only, which owns safe mapper-failure diagnostics and their exception isolation
  - [x] `ErrorHandler` MUST NOT receive an unused logger dependency or introduce unspecified logging events
  - [x] all runtime services MUST remain shared and stateless with respect to individual `handle()` invocations
  - [x] configuration MUST NOT be read again during individual `handle()` or mapper dispatch calls
  - [x] MUST NOT introduce a second reporting-policy registry, mutable configuration cache, or container-resolution mechanism
  - [x] consume the existing `SensitiveDataRedactorInterface` binding
  - [x] consume the existing `ErrorReporterPortInterface` binding
  - [x] MUST NOT construct concrete Redaction or Foundation reporter implementations
  - [x] MUST NOT implement another config loader, ruleset validator, reporter, or redaction engine
  - [x] MUST NOT store mutable runtime state, caches, buffers, mapper lists, or previous results

Configuration:

- [x] `packages/platform/errors/config/errors.php`
  - [x] return the `errors` subtree only
  - [x] exact defaults:
    - [x] `reporting.enabled = false`
    - [x] `reporting.min_severity = 'info'`
    - [x] `reporting.exclude_codes = []`
    - [x] `reporting.sample_rate = 10000`
    - [x] `reporting.mapper_failures.enabled = false`
  - [x] `sample_rate` uses integer basis points; `10000` means 100% reporter eligibility
  - [x] default reporter filters MUST preserve all severity levels and error codes when reporting is enabled
  - [x] all five keys MUST be represented in the returned configuration subtree
  - [x] MUST NOT repeat the `errors` root wrapper
  - [x] MUST NOT contain mapper class registries or service ids
  - [x] MUST NOT contain a redaction disable switch
  - [x] MUST NOT contain a raw error-detail/debug disclosure switch

- [x] `packages/platform/errors/config/rules.php`
  - [x] return a declarative ruleset array
  - [x] `schemaVersion = 1`
  - [x] `configRoot = errors`
  - [x] top-level `additionalKeys = false`
  - [x] `reporting` is a required map
  - [x] `reporting.additionalKeys = false`
  - [x] `reporting.enabled` is a required boolean
  - [x] `reporting.min_severity` is a required string restricted to `info|warning|error|critical`
  - [x] severity values MUST match the existing `ErrorSeverity` enum
  - [x] `reporting.exclude_codes` is a required list of non-empty strings without whitespace
  - [x] `reporting.sample_rate` is a required integer in the inclusive range `0..10000`
  - [x] `reporting.mapper_failures` is a required map with `additionalKeys = false`
  - [x] `reporting.mapper_failures.enabled` is a required boolean
  - [x] use only supported canonical Kernel ConfigValidator rules and types
  - [x] unknown keys and reserved `@*` keys MUST be rejected
  - [x] no executable validators, closures, objects, or runtime service references
  - [x] validation is performed by the existing Kernel ConfigValidator

Error normalization:

- [x] `packages/platform/errors/src/Mapper/ExceptionMapperRegistry.php`
  - [x] final stateless mapper-discovery and dispatch service
  - [x] canonical API:
    - [x] `map(Throwable $throwable, ?ErrorHandlingContext $context = null): ?ErrorDescriptor`
  - [x] constructor dependencies:
    - [x] `TagRegistry`
    - [x] `ContainerInterface`
    - [x] `LoggerInterface`
    - [x] `bool $reportingEnabled`
    - [x] `bool $mapperFailureLoggingEnabled`
  - [x] consume the existing `TagRegistry`
  - [x] enumerate mapper registrations once per `map()` invocation
  - [x] no mapper snapshot or resolved mapper cache is retained
  - [x] empty tag metadata is required; non-empty metadata invalidates only that mapper candidate
  - [x] invalid mapper metadata or service resolution MUST NOT abort the remaining candidates
  - [x] invalid mapper service type MUST be treated as a candidate failure
  - [x] mapped results MUST be either `null` or `ErrorDescriptor`
  - [x] caught mapper `Throwable` or return-type violations MUST be treated as candidate failures
  - [x] invalid candidate failures continue discovery in original TagRegistry order
  - [x] safe mapper-failure logging is allowed only when both `reporting.enabled` and `reporting.mapper_failures.enabled` are true
  - [x] mapper-failure diagnostics MUST NOT be filtered by `min_severity`, `exclude_codes`, or `sample_rate`
  - [x] disabling mapper-failure logging MUST NOT change candidate isolation, discovery continuation, descriptor selection, or fallback behavior
  - [x] mapper-failure diagnostics MUST use only existing fixed safe event names and low-cardinality reason tokens
  - [x] logging failures MUST NOT affect discovery or fallback selection
  - [x] read mapper registrations through `TagRegistry::all(ReservedTags::ERROR_MAPPER)`
  - [x] preserve canonical priority and service-id ordering
  - [x] do not re-sort or de-duplicate discovery results
  - [x] resolve mapper services lazily through `ContainerInterface::get($serviceId)`
  - [x] require resolved services to implement `ExceptionMapperInterface`
  - [x] call `map($throwable, $context)` in canonical discovery order
  - [x] `null` means continue to the next mapper
  - [x] first non-null `ErrorDescriptor` wins
  - [x] exhausted discovery returns `null` to the handler
  - [x] no raw Throwable message, trace, mapper exception, or previous Throwable is logged
  - [x] MUST NOT own the final fallback descriptor
  - [x] MUST NOT implement another TagRegistry, tag sorter, or metadata registry

- [x] `packages/platform/errors/src/Mapper/DefaultExceptionMapper.php`
  - [x] final stateless implementation of `ExceptionMapperInterface`
  - [x] `map(Throwable $throwable, ?ErrorHandlingContext $context = null): ?ErrorDescriptor`
  - [x] handles every Throwable without inspecting or copying its raw message
  - [x] returns a canonical `ErrorDescriptor`
  - [x] code: `CORETSIA_UNHANDLED_EXCEPTION`
  - [x] message: `Unhandled exception.`
  - [x] severity: `ErrorSeverity::Error`
  - [x] httpStatus: `null`
  - [x] extensions: `[]`
  - [x] uses static, owner-approved fallback values only
  - [x] MUST NOT add Throwable class, file, line, message, trace, or previous exception to the descriptor
  - [x] MUST NOT be registered under `error.mapper`
  - [x] MUST NOT depend on a container, config repository, redactor, reporter, or runtime adapter

- [x] `packages/platform/errors/src/ErrorHandler.php`
  - [x] final stateless implementation of `ErrorHandlerInterface`
  - [x] public API:
    - [x] `handle(Throwable $throwable, ?ErrorHandlingContext $context = null): ErrorDescriptor`
  - [x] accept caller-supplied `ErrorHandlingContext` when available
  - [x] read `CorrelationIdProviderInterface::correlationId()` at most once per `handle()` invocation
  - [x] capture the canonical correlation id as an invocation-local immutable snapshot
  - [x] correlation provider invocation failures MUST be swallowed and normalized to unavailable correlation enrichment
  - [x] mapping and reporting context construction MUST be independently exception-isolated
  - [x] if `ErrorHandlingContext` rejects a captured provider value, treat correlation enrichment as unavailable
  - [x] use `null` as the invocation-local correlation snapshot after such rejection
  - [x] construct safe mapping and reporting contexts using the fixed `operation = map`, `correlationId = null`, and `metadata = []` values
  - [x] explicit caller-supplied mapping context MUST remain unchanged
  - [x] context construction failures MUST NOT affect mapper dispatch, descriptor selection, redaction, or fallback behavior
  - [x] MUST NOT re-query the correlation provider or introduce package-local correlation-id validation
  - [x] explicit caller-supplied `ErrorHandlingContext` MUST remain authoritative for mapper invocation
  - [x] when explicit context is absent, construct mapping context as:
    - [x] `operation = map`
    - [x] `correlationId = captured safe correlation id|null`
    - [x] `metadata = []`
  - [x] construct a separate sanitized reporting context using the same captured correlation snapshot
  - [x] reporting context MUST NOT copy caller-supplied metadata
  - [x] mapping and reporting MUST NOT independently invoke the correlation provider
  - [x] mapper-side Context mutations MUST NOT alter the captured reporting correlation id
  - [x] do not read `ContextStore` or `ContextAccessorInterface` directly
  - [x] do not validate, generate, or normalize correlation identifiers through a package-local implementation
  - [x] correlation enrichment failures MUST NOT change the selected ErrorDescriptor
  - [x] delegate mapper discovery and dispatch to `ExceptionMapperRegistry`
  - [x] protect the entire registry-dispatch invocation with an outer Throwable boundary
  - [x] registry enumeration, ordering, and dispatch failures MUST NOT escape `handle()`
  - [x] registry-dispatch failure MUST select the fixed safe fallback without retrying discovery
  - [x] use `DefaultExceptionMapper` when no mapper produces a descriptor or registry dispatch cannot complete
  - [x] candidate-level mapper failures remain isolated inside the registry and MAY continue to subsequent candidates
  - [x] registry-level failures MUST NOT be confused with individual mapper failures
  - [x] MUST NOT introduce another TagRegistry or discovery mechanism solely to implement failure isolation
  - [x] use `SensitiveDataRedactorInterface` as defense in depth
  - [x] use one fixed `RedactionContext`:
    - [x] scope: `errors.descriptor`
    - [x] mode: `RedactionMode::Placeholder`
  - [x] export the candidate descriptor through `ErrorDescriptor::toArray()`
  - [x] pass the exported json-like map to `SensitiveDataRedactorInterface::redactJsonLike()`
  - [x] require the redactor result to be an array
  - [x] reconstruct the result only through `ErrorDescriptor::fromArray()`
  - [x] the canonical contracts implementation owns strict field-set, field-type, schema-version, severity, and extension validation
  - [x] invalid redactor output or failed descriptor reconstruction MUST select the fixed safe fallback
  - [x] redactor exceptions MUST select the fixed safe fallback
  - [x] the fallback MUST NOT be passed recursively through the failing redaction path
  - [x] MUST NOT introduce package-local descriptor field definitions, schema validators, or reconstruction helpers
  - [x] MUST NOT silently coerce or repair malformed redaction output
  - [x] fallback construction MUST use only static safe constants
  - [x] never copy arbitrary Throwable messages into fallback output
  - [x] invoke the injected `ErrorReporterPortInterface` only when reporting is enabled and the final descriptor passes all reporter eligibility checks
  - [x] evaluate reporter eligibility only after mapper selection, mandatory redaction, descriptor reconstruction, and fallback selection
  - [x] apply the minimum severity threshold to the final `ErrorDescriptor::severity()`
  - [x] canonical severity order:
    - [x] `info < warning < error < critical`
  - [x] exclude descriptors whose final code exactly matches a configured entry in `reporting.exclude_codes`
  - [x] excluded-code matching MUST be strict and case-sensitive
  - [x] apply sampling only after severity and excluded-code filtering
  - [x] interpret `reporting.sample_rate` as integer basis points:
    - [x] `0`: suppress every reporter invocation
    - [x] `10000`: report every otherwise eligible descriptor
    - [x] `1..9999`: use `random_int(0, 9999)` and report only when the result is less than the configured rate
  - [x] random sampling MUST NOT modify the descriptor, mapper selection, fallback selection, or normalized outcome
  - [x] sampling MUST NOT use raw Throwable data, caller metadata, credentials, request payloads, or sensitive identifiers
  - [x] sampling failures MUST be swallowed and suppress only the optional reporter invocation
  - [x] min-severity, excluded-code, and sampling policies MUST NOT suppress mapping metrics, spans, or canonical normalization
  - [x] construct and pass only sanitized safe reporting context when reporter invocation is selected
  - [x] use only normalized descriptors and safe context for reporting
  - [x] no reporter eligibility failure may replace the selected descriptor
  - [x] reporter execution remains at most once per `handle()` invocation
  - [x] MUST NOT introduce a second error mapper, reporter implementation, sampling service, or mutable invocation counter
  - [x] do not forward unvalidated caller metadata to reporter or telemetry sinks
  - [x] isolate reporter, tracer, meter, and Stopwatch failures independently in `ErrorHandler`
  - [x] isolate mapper-failure logger failures independently in `ExceptionMapperRegistry`
  - [x] no reporting or enrichment failure may replace the selected descriptor
  - [x] avoid recursive fallback mapping or recursive self-reporting
  - [x] use a protected finally path to attempt span termination exactly once
  - [x] span end failures MUST NOT affect the returned descriptor
  - [x] return a format-neutral `ErrorDescriptor`
  - [x] MUST NOT produce HTTP responses, CLI output, or worker failure payloads
  - [x] MUST NOT import PSR-7 or transport-specific implementations
  - [x] MUST NOT start or finish a Kernel UnitOfWork
  - [x] MUST NOT write or reset ContextStore
  - [x] MUST NOT retain Throwable, context, descriptor, timing token, span, or previous result between calls
  - [x] the runtime `handle()` boundary MUST NOT propagate mapper, redaction, or optional sink failures

Canonical runtime flow (MUST):

```text
Throwable
→ capture canonical correlation snapshot (at most once)
→ select explicit or canonical safe mapping context
→ if reporting enabled:
  → start Stopwatch measurement
  → start error.map span with operation=map
→ ExceptionMapperRegistry
  → TagRegistry::all(ReservedTags::ERROR_MAPPER)
  → preserve TagRegistry ordering
  → lazy mapper service resolution
  → ExceptionMapperInterface::map()
  → null/failure: continue
  → first non-null ErrorDescriptor
→ DefaultExceptionMapper when no descriptor is selected
→ ErrorDescriptor::toArray()
→ SensitiveDataRedactorInterface::redactJsonLike()
→ ErrorDescriptor::fromArray()
  → canonical contracts-owned shape and invariant validation
→ fixed safe fallback on validation/redaction failure
→ final safe ErrorDescriptor
→ determine normalization outcome: ok|fallback
→ if reporting enabled:
  → stop Stopwatch measurement
  → emit error.map_total and error.map_duration_ms
  → finalize error.map span with operation/outcome
  → end successfully created span exactly once
  → evaluate reporter-only minimum severity threshold
  → evaluate reporter-only excluded error codes
  → apply reporter-only sampling policy
  → if reporter is eligible:
    → construct sanitized reporting context
    → invoke ErrorReporterPortInterface at most once
→ return final ErrorDescriptor
```

- Normalization MUST complete independently of optional reporting.
- Reporting-disabled execution MUST still perform complete mapper dispatch, redaction, descriptor reconstruction, and fallback handling.
- Mapping failure or exhaustion MUST NOT re-enter the registry recursively.
- The default mapper MUST execute only as the fallback path.
- A redaction failure MUST NOT return the pre-redaction descriptor.
- A redaction failure MUST NOT retry the registry or recursively redact the fallback.
- Mapping observability MUST use only fixed operation and outcome values.
- A reporter failure MUST NOT restart mapping.
- All invocation-local timing and span state MUST be discarded after the call.
- The handler MUST NOT perform application boot, module discovery, runtime adapter rendering, or Kernel UoW orchestration.

- [x] All Stopwatch, metric, tracer, and reporter operations MUST be independently exception-isolated.
- [x] Failure to start a span MUST NOT prevent mapping, metrics, or reporting.
- [x] Failure to stop timing MUST NOT prevent span finalization or reporting.
- [x] Span finalization MUST be attempted exactly once for every successfully created span.
- [x] The normalization outcome MUST be determined by the final descriptor selection, not by the success or failure of observability sinks.
- [x] Reporter execution occurs outside the measured normalization boundary.
- [x] No observability failure may cause another mapper-dispatch or redaction pass.

Cross-package CLI integration:

- [x] `packages/platform/cli/src/Mapper/CliExceptionMapper.php`
  - [x] final stateless implementation of `ExceptionMapperInterface`
  - [x] public API:
    - [x] `map(Throwable $throwable, ?ErrorHandlingContext $context = null): ?ErrorDescriptor`
  - [x] replace the known-exception allowlist from the existing `CliErrorHandler`
  - [x] supported Throwable types:
    - [x] `CliInputInvalidException`
    - [x] `CliConfigInvalidException`
    - [x] `InvalidCommandTagMetaException`
    - [x] `CliCommandFailedException`
    - [x] `WorkflowDefinitionInvalidException`
    - [x] `WorkflowNotFoundException`
    - [x] `KernelOpsFailedException`
  - [x] for supported exceptions, preserve:
    - [x] `code = $throwable->errorCode()`
    - [x] `message = $throwable->reason()`
    - [x] `severity = ErrorSeverity::Error`
    - [x] `httpStatus = null`
    - [x] `extensions = []`
  - [x] unsupported Throwable types MUST return `null`
  - [x] MUST NOT become a catch-all mapper
  - [x] MUST NOT inspect or copy arbitrary Throwable messages, traces, files, lines, or previous exceptions
  - [x] MUST NOT copy caller context metadata into the descriptor
  - [x] MUST NOT perform redaction, reporting, logging, rendering, or UoW orchestration
  - [x] MUST NOT depend on concrete `platform/errors` classes
  - [x] MUST NOT register a global `ErrorHandlerInterface` binding
  - [x] in the canonical CLI baseline, known error codes and reason tokens MUST remain byte-for-byte compatible with the previous CLI behavior
  - [x] the CLI mapper MUST use the canonical TagRegistry ordering without package-local priority overrides
  - [x] an explicitly contributed mapper enumerated earlier MAY claim the same Throwable according to the first-non-null registry policy
  - [x] CLI MUST NOT bypass mapper priority ordering to force its own descriptor selection

CLI normalization verification:

- [x] `packages/platform/cli/tests/Unit/CliExceptionMapperTest.php`
  - [x] verify each of the seven known exception types
  - [x] preserve existing code and reason values exactly
  - [x] return `null` for unknown Throwable types
  - [x] reject raw exception-message and previous-Throwable disclosure
  - [x] produce no transport-specific descriptor fields
  - [x] mapper does not perform redaction, reporting, or rendering

- [x] `packages/platform/cli/tests/Integration/CliCanonicalErrorHandlerIntegrationTest.php`
  - [x] compose `platform.cli`, `platform.errors`, and `platform.redaction` through canonical Kernel module composition
  - [x] verify `ErrorHandlerInterface` resolves to the `platform/errors` implementation
  - [x] verify CLI-owned mapper discovery through `ReservedTags::ERROR_MAPPER`
  - [x] verify known CLI and Kernel Ops failures preserve existing public code/message pairs in the canonical baseline
  - [x] verify earlier registered mapper contributions retain canonical first-non-null precedence
  - [x] verify CLI mapping does not perform consumer-side mapper reordering or candidate filtering
  - [x] verify unknown Throwable uses the canonical fallback internally
  - [x] verify unknown Throwable renders as `CORETSIA_CLI_INTERNAL_ERROR` with message `Internal CLI error.`
  - [x] verify exception rendering does not expose raw messages, traces, previous exceptions, or caller metadata
  - [x] verify mapped exceptions preserve existing JSON/plain/table error-output behavior
  - [x] verify unknown-command suggestions and workflow failures preserve current output semantics
  - [x] verify normal CLI exit codes remain unchanged
  - [x] verify disabled error reporting does not disable mapping or mandatory redaction
  - [x] verify source and compiled container execution produce equivalent normalized results
  - [x] verify ultra-early doctor does not resolve the canonical handler
  - [x] verify missing or excluded `platform.errors` causes safe Console host failure
  - [x] verify no duplicate global error-handler binding exists

Architecture decision:

- [x] `docs/adr/ADR-0036-platform-errors-errorhandler-exception-mapper.md`
  - [x] document the canonical contracts-level normalization boundary
  - [x] document why TagRegistry remains the only mapper discovery authority
  - [x] document why the default mapper is outside the tag registry
  - [x] document use of the existing redaction port
  - [x] document reuse of the Foundation Noop reporter
  - [x] document the fail-closed handler behavior
  - [x] document the absence of a package-local reset mechanism
  - [x] document runtime module composition
  - [x] document rejected duplicate redaction, reporter, registry, and adapter implementations

#### Modifies

Contracts hardening:

- [x] `packages/core/contracts/src/Observability/Errors/ErrorDescriptor.php`
  - [x] add canonical static reconstruction API:
    - [x] `public static function fromArray(array $payload): self`
  - [x] accept only the existing canonical exported field set:
    - [x] `code`
    - [x] `extensions`
    - [x] `httpStatus`
    - [x] `message`
    - [x] `schemaVersion`
    - [x] `severity`
  - [x] reject missing or unknown top-level fields
  - [x] field ordering in the input map MUST NOT affect acceptance
  - [x] require `schemaVersion === self::SCHEMA_VERSION`
  - [x] require strict field types:
    - [x] `code`: string
    - [x] `message`: string
    - [x] `severity`: valid string-backed `ErrorSeverity`
    - [x] `httpStatus`: int|null
    - [x] `extensions`: string-keyed map
  - [x] reconstruct severity through `ErrorSeverity::from()`
  - [x] delegate final field validation and extension normalization to the existing constructor
  - [x] reject malformed payloads using exactly one safe `InvalidArgumentException` message:
    - [x] `Invalid error descriptor payload.`
  - [x] validate the exact top-level field set and strict field types before enum conversion
  - [x] normalize constructor and enum-validation failures to the fixed reconstruction error
  - [x] MUST NOT expose rejected values, nested extension paths, or internal validation diagnostics
  - [x] MUST NOT retain the original validation exception through `previous`
  - [x] MUST NOT change the existing constructor's independent validation behavior
  - [x] MUST NOT introduce another descriptor schema, sanitizer, or redaction implementation
  - [x] MUST NOT modify the existing constructor signature or public exported field set
  - [x] MUST NOT silently coerce invalid types, remove unknown fields, or truncate invalid metadata
  - [x] MUST NOT treat successful reconstruction as authorization to disclose arbitrary producer data

- [x] `packages/core/contracts/tests/Contract/ErrorDescriptorShapeContractTest.php`
  - [x] valid `fromArray(toArray())` round-trip preserves descriptor values and canonical export shape
  - [x] input field ordering does not affect reconstruction
  - [x] unknown and missing fields are rejected
  - [x] invalid schema version is rejected
  - [x] invalid severity or field types are rejected
  - [x] malformed reconstruction exposes only `Invalid error descriptor payload.`
  - [x] invalid nested extension paths do not appear in the reconstruction exception
  - [x] reconstruction exceptions do not retain original validation exceptions as `previous`
  - [x] forbidden extension keys and resource-bound violations remain rejected
  - [x] existing constructor and getter contracts remain unchanged

Cross-package CLI normalization migration:

- [x] `packages/platform/cli/composer.json`
  - [x] add direct production dependency:
    - [x] `coretsia/platform-errors`: `^0.9.0`
  - [x] extend `extra.coretsia.requires`:
    - [x] `core.kernel`
    - [x] `platform.errors`
    - [x] `platform.redaction`
  - [x] preserve the direct `coretsia/platform-redaction` dependency because `OutputFormatter` still consumes its port
  - [x] preserve all existing CLI package metadata and command entrypoints
  - [x] Composer installation MUST NOT implicitly enable the CLI or Errors runtime module

- [x] `packages/platform/cli/src/Provider/CliServiceProvider.php`
  - [x] require the existing `ErrorHandlerInterface::class` binding in canonical declarative definitions
  - [x] register `CliExceptionMapper::class` as a shared class service
  - [x] register its tag through the existing canonical tag API:
    - [x] tag: `ReservedTags::ERROR_MAPPER`
    - [x] service id: `CliExceptionMapper::class`
    - [x] priority: `100`
    - [x] metadata: `[]`
  - [x] register the mapper and tag through `define()` so source and compiled definitions remain equivalent
  - [x] replace `CliApplication` factory argument `CliErrorHandler::class` with `ErrorHandlerInterface::class`
  - [x] remove the `CliErrorHandler::class` service definition
  - [x] MUST NOT bind or alias `ErrorHandlerInterface` from CLI
  - [x] MUST NOT instantiate the concrete `platform/errors` handler or registry
  - [x] MUST NOT register `CliExceptionMapper` under `cli.command`
  - [x] preserve all existing command tags, metadata, service ids, and source-only Kernel Ops wiring

- [x] `packages/platform/cli/src/Provider/CliServiceFactory.php`
  - [x] remove `cliErrorHandler(): CliErrorHandler`
  - [x] change `cliApplication()` argument:
    - [x] from `CliErrorHandler $errors`
    - [x] to `ErrorHandlerInterface $errors`
  - [x] forward the injected canonical port to `CliApplication` without wrapping it
  - [x] MUST NOT construct `ErrorHandler`, `ExceptionMapperRegistry`, or a fallback mapper

- [x] `packages/platform/cli/src/Application/CliApplication.php`
  - [x] change constructor dependency:
    - [x] from `CliErrorHandler $errors`
    - [x] to `ErrorHandlerInterface $errors`
  - [x] preserve existing normal-path Throwable handling:
    - [x] discard partial output before rendering an error
    - [x] call `$this->errors->handle($exception)` exactly once
    - [x] retain the returned `ErrorDescriptor` as an invocation-local value
    - [x] pass the captured descriptor to `ExceptionRenderer`
    - [x] preserve unknown-command suggestions and their existing safety restrictions
    - [x] change the third `appendSuggestions()` argument:
      - [x] from `CliInputInvalidException $exception`
      - [x] to `ErrorDescriptor $descriptor`
    - [x] pass the captured descriptor to `appendSuggestions()` for error-output recovery
    - [x] preserve the existing unknown-command condition in `run()` before invoking `appendSuggestions()`
    - [x] `appendSuggestions()` MUST NOT inspect or retain the original Throwable
    - [x] when suggestion output fails, discard partial output and re-render the captured descriptor
    - [x] suggestion-output recovery MUST NOT invoke `ErrorHandlerInterface` a second time
    - [x] preserve exit code `1` for uncaught normal-path failures
  - [x] preserve invocation-local `CommandOutputBuffer`
  - [x] preserve the independent final formatting/rendering failure boundary
  - [x] MUST NOT create a second error handler or mapper fallback
  - [x] MUST NOT move mapper dispatch inside an additional Kernel UoW

- [x] `packages/platform/cli/src/Diagnostics/ExceptionRenderer.php`
  - [x] continue to accept only `ErrorDescriptor` and `OutputInterface`
  - [x] render normalized known-error descriptors through the existing output port
  - [x] when the canonical descriptor has code `CORETSIA_UNHANDLED_EXCEPTION`, render the existing CLI public fallback:
    - [x] code: `CORETSIA_CLI_INTERNAL_ERROR`
    - [x] message: `Internal CLI error.`
  - [x] preserve all other accepted descriptor codes and messages unchanged
  - [x] fallback translation is CLI presentation policy, not another Throwable mapping pass
  - [x] MUST NOT construct another ErrorDescriptor or invoke `ErrorHandlerInterface`
  - [x] MUST NOT inspect Throwable objects or raw exception metadata
  - [x] MUST NOT change `CommandOutputBuffer`, `OutputFormatter`, or console-writer responsibilities

CLI and Kernel regression fixtures:

- [x] `packages/platform/cli/tests/Contract/CliDoesNotBindGlobalErrorHandlerPortContractTest.php`
  - [x] replace legacy `CliErrorHandler` expectations with canonical consumer assertions
  - [x] CLI provider MUST require `ErrorHandlerInterface` without binding or overriding it
  - [x] verify `CliApplication` references `ErrorHandlerInterface`
  - [x] verify CLI mapper is registered as a service and tagged under `error.mapper`
  - [x] verify CLI-only provider registration does not create a global handler alias
  - [x] verify canonical module composition supplies exactly one handler binding

- [x] `packages/platform/cli/tests/Contract/CliServiceProviderSeparatesSourceOnlyKernelOpsWiringContractTest.php`
  - [x] replace `CliErrorHandler` service assertions with `CliExceptionMapper`
  - [x] keep `cli.command` metadata assertions scoped only to `cli.command` tags
  - [x] validate `error.mapper` metadata is empty and priority is `100`
  - [x] preserve source-only Kernel Ops command registration
  - [x] preserve declarative source/compiled definition parity

- [x] `packages/platform/cli/tests/Contract/CliComposerRuntimeDependenciesContractTest.php`
  - [x] add `coretsia/platform-errors` to the exact internal dependency set
  - [x] update module requirements to include `platform.errors`
  - [x] preserve the ban on concrete platform/errors and platform/redaction source imports
  - [x] preserve CLI entrypoint and command-owner dependency restrictions

- [x] `packages/platform/cli/tests/Contract/CliCommandOutputBufferIsInvocationLocalContractTest.php`
  - [x] replace direct legacy handler construction with an injected `ErrorHandlerInterface` fixture
  - [x] preserve one buffer per CLI invocation
  - [x] preserve discard-before-render ordering and repeated-invocation isolation
  - [x] replace the source-level assertion requiring inline `$this->exceptions->render($this->errors->handle($exception), $buffer)`
  - [x] verify `CliApplication` invokes `ErrorHandlerInterface::handle()` exactly once for the original normal-path failure
  - [x] verify the returned `ErrorDescriptor` is retained as an invocation-local value
  - [x] verify `ExceptionRenderer` receives the captured descriptor
  - [x] verify `appendSuggestions()` receives and reuses the same descriptor
  - [x] verify suggestion-output recovery does not perform another mapper dispatch or reporting attempt
  - [x] preserve repeated-invocation isolation without storing the descriptor as service state

- [x] `packages/platform/cli/tests/Integration/Support/CliApplicationFixtureHarness.php`
  - [x] accept a canonical `ErrorHandlerInterface` fixture or service
  - [x] remove direct `CliErrorHandler` construction
  - [x] preserve real CLI rendering and output-formatting behavior
  - [x] use the canonical composed handler for mapping integration scenarios
  - [x] MUST NOT introduce a second production mapping pipeline inside test support

- [x] `packages/platform/cli/tests/Integration/CliRequiresExplicitConsoleModuleSelectionTest.php`
  - [x] include `platform.errors` in synthetic module manifests and dependency closure
  - [x] preserve the existing missing-redaction failure assertion
  - [x] add independent missing-errors and explicit-errors-exclusion failure assertions
  - [x] verify that installed but unselected CLI does not activate the Errors module

- [x] `packages/platform/cli/tests/Integration/NormalCommandsBootKernelOperationsHostTest.php`
  - [x] verify normal Console host includes `platform.errors`
  - [x] verify canonical `ErrorHandlerInterface` resolution
  - [x] preserve explicit CLI exclusion behavior and source-host boot invariants

- [x] `packages/platform/cli/tests/Integration/CliDefaultMicroCreateProjectBootTest.php`
  - [x] verify Composer baseline includes `coretsia/platform-errors` transitively
  - [x] verify default Console ModulePlan includes `platform.errors`
  - [x] preserve source boot without generated artifacts
  - [x] preserve packaged CLI invocation and output behavior

- [x] `packages/platform/cli/tests/Integration/CliHostResolvesCanonicalSensitiveDataRedactorTest.php`
  - [x] preserve the single canonical redactor binding
  - [x] verify both CLI output formatting and canonical ErrorHandler consume the existing redaction port
  - [x] MUST NOT create a CLI-local or Errors-local concrete redactor

- [x] `packages/core/kernel/tests/Support/AppBuilder.php`
  - [x] extend synthetic manifests with `platform.errors`
  - [x] add the Errors package config source and Composer package root to fixture resolution
  - [x] include `ErrorsServiceProvider` in the canonical fixture provider set
  - [x] update synthetic `platform.cli` module requirements
  - [x] preserve existing source/compiled container composition
  - [x] MUST NOT change Kernel ModulePlan algorithms or container compiler behavior

- [x] `packages/core/kernel/tests/Integration/BootMicroPresetTest.php`
  - [x] update exact enabled-module and topological-order expectations for transitive `platform.errors`
  - [x] preserve the same `micro` preset roots

- [x] `packages/core/kernel/tests/Integration/BootExpressPresetTest.php`
  - [x] update exact enabled-module and topological-order expectations for transitive `platform.errors`
  - [x] preserve the same `express` preset roots

- [x] `packages/core/kernel/tests/Integration/ModuleResolutionOrchestratorLoadsApplicationOnlyCustomPresetTest.php`
  - [x] update CLI-containing fixture dependency closures where required
  - [x] preserve custom-preset and target-local selection semantics

- [x] `packages/core/kernel/tests/Unit/ProjectPackagePlannerTest.php`
  - [x] update physical and runtime dependency-closure assertions affected by the new CLI requirement
  - [x] preserve deterministic union ordering and explicit dependency synchronization

- [x] `tools/tests/Integration/DependencySyncConsumerProjectTest.php`
  - [x] include `coretsia/platform-errors` in the real consumer fixture's local Composer package repositories
  - [x] use the canonical `packages/platform/errors` package root and existing release-version constraint
  - [x] preserve the existing explicit framework, CLI, Redaction, Kernel, and Worker repository entries
  - [x] verify the Composer solver resolves `platform/errors` transitively through `coretsia/platform-cli`
  - [x] preserve existing dependency-sync plan/review/apply and protected-package behavior
  - [x] MUST NOT introduce a separate dependency resolver or hard-code `platform/errors` as a direct consumer requirement

- [x] `tools/tests/Integration/DependencySyncFreshProcessVerificationTest.php`
  - [x] include `coretsia/platform-errors` in the fresh-process fixture's local Composer package repositories
  - [x] use the canonical `packages/platform/errors` package root and existing release-version constraint
  - [x] preserve all existing fixture package repositories and root Composer requirements
  - [x] verify fresh Composer resolution includes `platform/errors` through the CLI dependency
  - [x] preserve independent fresh-process dependency verification
  - [x] MUST NOT require `coretsia/platform-errors` directly in the consumer project's root Composer requirements

Canonical architecture and documentation:

- [x] `docs/ssot/modes.md`
  - [x] update the declared `platform.cli` dependency closure to include `platform.errors`
  - [x] update the effective enabled-module sets for `micro`, `express`, `hybrid`, and `enterprise`
  - [x] update all target-local ModulePlan examples and project-wide module union examples where `platform.cli` is selected
  - [x] include `platform.errors` transitively in every affected enabled-module set
  - [x] preserve canonical byte-order sorting for enabled-module sets
  - [x] preserve existing dependency-topological ordering rules independently from enabled-set sorting
  - [x] preserve the existing preset root selections
  - [x] document that `platform.errors` is selected transitively, not as a new preset root
  - [x] document the fail-closed behavior when a selected CLI module is missing its Errors dependency

- [x] `docs/ssot/application-dependency-sync.md`
  - [x] update the canonical installation-catalog module table:
    - [x] add `platform.errors` with its declared dependencies
    - [x] add `platform.errors` to the declared `platform.cli` dependency set
  - [x] update the Composer baseline dependency graph:
    - [x] `coretsia/platform-cli` directly requires `coretsia/platform-errors`
    - [x] `coretsia/platform-errors` directly requires `coretsia/platform-redaction`
  - [x] update the effective Console `ModulePlan` example to include `platform.errors`
  - [x] update both `web` and `worker` target-local dependency-closure examples
  - [x] update the project-wide physical module union example
  - [x] include `coretsia/platform-errors` in the example of desired Composer package identities
  - [x] preserve the distinction between transitive Composer installation, target-local module enablement, and managed root requirements
  - [x] preserve canonical plan/review/apply ownership
  - [x] do not introduce implicit package installation during boot

- [x] `packages/framework/README.md`
  - [x] document that framework baseline installation includes `platform/errors` through CLI dependency closure
  - [x] preserve the existing direct framework requirements and module-selection policy

- [x] `packages/applications/skeleton/README.md`
  - [x] update default `micro` installation and Console ModulePlan documentation
  - [x] include the transitive `platform.errors` dependency
  - [x] update the documented `web → micro` and `worker → enterprise` target-local module closures
  - [x] include `platform.errors` in the documented project-wide physical module union
  - [x] preserve the existing preset root selections
  - [x] preserve the distinction between physical installation and runtime module enablement
  - [x] preserve source-host boot without precompiled artifacts

- [x] `packages/platform/cli/README.md`
  - [x] replace references to legacy `CliErrorHandler` with the canonical `ErrorHandlerInterface` flow
  - [x] document CLI-owned `CliExceptionMapper` and `error.mapper` registration
  - [x] document retained CLI public fallback mapping
  - [x] document updated Composer/module dependency closure
  - [x] preserve the documented command, output, error-code, and exit-code contracts

- [x] `docs/ssot/error-descriptor.md`
  - [x] document `ErrorDescriptor::fromArray()` as the canonical strict reconstruction API
  - [x] preserve existing descriptor field meanings and invariants
  - [x] reconstruction MUST NOT perform sensitive-data classification or redaction
  - [x] reconstruction MUST NOT replace mapper-owned safe-by-construction derivation

- [x] `docs/ssot/errors-boundary.md`
  - [x] document concrete `platform/errors` normalization flow
  - [x] document canonical mapper registry dispatch and fallback policy
  - [x] document the existing `ErrorHandlerInterface` binding ownership
  - [x] document `CORETSIA_UNHANDLED_EXCEPTION` fallback code
  - [x] document mapper failure isolation without a second exception hierarchy
  - [x] document the canonical `platform/errors` runtime handler guarantee
  - [x] preserve the implementation-neutral `ErrorHandlerInterface` signature
  - [x] document CLI consumption through the canonical port and CLI-owned mapper registration
  - [x] clarify that runtime adapters own presentation, while their packages MAY contribute owner-specific `ExceptionMapperInterface` services through the canonical `error.mapper` tag
  - [x] contributing an owner-specific mapper MUST NOT transfer mapper discovery, ordering, fallback selection, or global handler ownership away from `platform/errors`
  - [x] CLI-owned exception classification MUST remain separate from CLI transport rendering
  - [x] no runtime adapter may introduce a second Throwable-to-ErrorDescriptor dispatch pipeline
  - [x] preserve transport-specific rendering and failure-boundary ownership
  - [x] preserve `ErrorDescriptor` and runtime adapter ownership boundaries
  - [x] MUST NOT introduce a competing descriptor schema

- [x] `docs/ssot/tags.md`
  - [x] retain the existing `error.mapper` registry row
  - [x] document mapper service-id and priority semantics
  - [x] document empty metadata and lazy resolution requirements
  - [x] document first-non-null descriptor selection
  - [x] document that the default fallback mapper is not tagged
  - [x] MUST NOT create a second tag row or another reserved identifier

- [x] `docs/ssot/observability.md`
  - [x] register `error.map` under `Baseline Canonical Events → Canonical Spans`
  - [x] register `error.map_total` in the canonical metrics catalog:
    - [x] owner: `platform/errors`
    - [x] type: `counter`
    - [x] labels: `operation`, `outcome`
  - [x] register `error.map_duration_ms` in the canonical metrics catalog:
    - [x] owner: `platform/errors`
    - [x] type: `observe`
    - [x] labels: `operation`, `outcome`
  - [x] `operation` values: `map`
  - [x] `outcome` values: `ok|fallback`
  - [x] `ok` requires a registered mapper descriptor accepted after redaction and validation
  - [x] `fallback` means that the final returned descriptor was selected through the canonical fallback path
  - [x] an individual mapper failure followed by a successfully accepted descriptor MUST produce `outcome = ok`
  - [x] mapper failure diagnostics MUST NOT independently determine the final normalization outcome
  - [x] preserve the existing global metric label allowlist
  - [x] MUST NOT register duplicate metric or span entries
  - [x] MUST NOT introduce new global label keys

- [x] `docs/ssot/config-roots.md`
  - [x] add exactly one root row:
    - [x] root: `errors`
    - [x] owner: `platform/errors`
    - [x] defaults: `packages/platform/errors/config/errors.php`
    - [x] rules: `packages/platform/errors/config/rules.php`
  - [x] do not redefine an existing config root

- [x] `docs/architecture/DEPENDENCIES.md`
  - [x] add the materialized `platform/errors` package to the canonical dependency matrix
  - [x] direct source dependencies: `core/contracts`, `core/foundation`
  - [x] preserve `platform/redaction` as a runtime-only Composer dependency
  - [x] MUST NOT add a source dependency on `platform/redaction`
  - [x] document the direct Composer-only dependency from `platform/cli` to `platform/errors`
  - [x] preserve the CLI source dependency boundary through `core/contracts` and `core/foundation`
  - [x] MUST NOT add `platform/errors` to the CLI source import allowlist solely because CLI consumes the canonical port
  - [x] preserve Worker source and Composer dependencies unchanged

- [x] `docs/adr/INDEX.md`
  - [x] register `ADR-0036-platform-errors-errorhandler-exception-mapper.md`

Monorepo package metadata and generated outputs:

- [x] `composer.json`
  - [x] add `coretsia/platform-errors = 0.9.x-dev` to root development requirements
  - [x] add its canonical local Composer path repository
  - [x] preserve the existing managed repository metadata convention
  - [x] do not add a separate direct `coretsia/platform-errors` requirement to `packages/framework/composer.json`
  - [x] `coretsia/framework` MUST receive `coretsia/platform-errors` transitively through its existing `coretsia/platform-cli` dependency
  - [x] preserve the distinction between Composer installation and runtime module selection
  - [x] synchronize Composer dependency metadata using the existing repository workflow

- [x] `tools/testing/package-index.php`
  - [x] regenerate from canonical package metadata
  - [x] include `platform.errors` with correct module, provider, config, namespace, and Composer identity
  - [x] MUST NOT edit generated records manually

- [x] `tools/testing/deptrac.yaml`
  - [x] regenerate from the canonical dependency matrix
  - [x] include the `platform.errors` source layer
  - [x] allow only `core.contracts` and `core.foundation`
  - [x] MUST NOT permit imports from the concrete `platform.redaction` implementation
  - [x] MUST NOT edit generated rules manually

- [x] `packages/core/kernel/resources/packaging/installation-catalog.php`
  - [x] regenerate through the canonical installation catalog generator
  - [x] include `platform.errors`
  - [x] preserve declared module requirements and Composer identity
  - [x] MUST NOT create another installation catalog or manually edit generated records

Generated repository artifacts MUST change only through their canonical generators or Composer dependency synchronization.

No Kernel ModulePlan implementation, runtime container compiler, Foundation TagRegistry, or Foundation Noop reporter is modified by this epic.

The existing CLI-owned error handler is intentionally replaced by CLI-owned mapper contribution and canonical `ErrorHandlerInterface` consumption.

Canonical Kernel preset root selections and direct framework metapackage requirements remain unchanged. The effective CLI module dependency closure and transitive Composer installation graph expand to include `platform.errors`.

No Worker runtime, task-settlement, process-control, or bootstrap implementation is modified by this epic.

#### Configuration (keys + defaults)

- [x] Files:
  - [x] `packages/platform/errors/config/errors.php` - canonical defaults subtree
    - [x] `errors.reporting.enabled` = false
    - [x] `errors.reporting.min_severity` = 'info'
    - [x] `errors.reporting.exclude_codes` = []
    - [x] `errors.reporting.sample_rate` = 10000
    - [x] `errors.reporting.mapper_failures.enabled` = false
  - [x] `packages/platform/errors/config/rules.php`

- Keys (dot):
  - `errors.reporting.enabled` = false
    - type: `bool`
    - default: `false`
    - consumer: `ErrorsServiceFactory`
    - controls optional reporter, logging, tracing, and metrics emission
  - `errors.reporting.min_severity` = 'info'
    - type: `string`
    - allowed: `info|warning|error|critical`
    - default: `info`
    - consumer: `ErrorHandler`
    - controls minimum severity for reporter invocation only
  - `errors.reporting.exclude_codes` = []
    - type: `list<non-empty-string-no-ws>`
    - default: `[]`
    - consumer: `ErrorHandler`
    - suppresses reporter invocation for exact matching final error codes
  - `errors.reporting.sample_rate` = 10000
    - type: `int`
    - range: `0..10000`
    - unit: basis points
    - default: `10000`
    - consumer: `ErrorHandler`
    - controls reporter-only probabilistic sampling
  - `errors.reporting.mapper_failures.enabled` = false
    - type: `bool`
    - default: `false`
    - consumer: `ExceptionMapperRegistry`
    - controls fixed safe mapper-failure diagnostics
    - effective only when `errors.reporting.enabled = true`
    - independent of reporter severity, exclusion, and sampling filters
  - All reporter filters MUST NOT disable error normalization, mapper discovery, fallback, or redaction.
  - Reporter filters MUST NOT change mapper ordering or final descriptor values.
  - Mapping metrics and spans MUST NOT be sampled or filtered by reporter eligibility.
- Declarative rules:
  - `schemaVersion = 1`
  - `configRoot = errors`
  - all declared maps use `additionalKeys = false`
  - all five reporting keys are required with strict types
  - `min_severity` uses the existing `ErrorSeverity` values
  - `sample_rate` uses the existing Kernel integer `min` and `max` validation rules
  - `exclude_codes` uses the existing Kernel list-item validation rules
  - missing, invalid, or unknown configuration keys fail at the canonical Kernel config-validation boundary
  - no package-local config validator
- Forbidden configuration surfaces:
  - `errors.enabled`
  - `errors.redaction.enabled`
  - `errors.detail.show_in_local`
  - `errors.mappers`
  - `errors.mapper_classes`
  - `errors.debug`
  - arbitrary sensitive-data classification or masking configuration
- Configuration consumption:
  - merged configuration is read through `ConfigRepositoryInterface`
  - `ErrorsServiceFactory` reads and strictly validates all five reporting configuration values
  - the validated immutable reporting values are passed to runtime services at construction
  - no reporting configuration is read during individual `handle()` or mapper dispatch calls
  - mapper discovery always uses the DI tag, never config lists
  - `exclude_codes` is a reporter-only suppression list, never a mapper registry or dispatch filter
  - reporter filtering and sampling occur only after final safe descriptor selection
  - the redaction port is always required regardless of reporting policy
  - no environment-driven raw detail disclosure

#### Wiring / DI tags (MUST)

- [x] Existing reserved tag:
  - [x] `ReservedTags::ERROR_MAPPER = 'error.mapper'`
  - [x] owner: `platform/errors`
  - [x] identifier already exists in `core/foundation`
  - [x] MUST NOT modify `ReservedTags.php`
- [x] Mapper contribution contract:
  - [x] any enabled package MAY contribute an `ExceptionMapperInterface` service
  - [x] the contributing package owns its mapper implementation and DI registration
  - [x] service id MUST identify the registered mapper service
  - [x] tag MUST use `ReservedTags::ERROR_MAPPER`
  - [x] priority is an integer supplied to the canonical tag registration API
  - [x] tag metadata MUST be empty
  - [x] consumers MUST preserve TagRegistry enumeration order
  - [x] services MUST be resolved only when mapping is executed
  - [x] `platform/errors` MUST NOT compile-time depend on mapper-contributing packages
- [x] ServiceProvider bindings:
  - [x] `DefaultExceptionMapper::class` → concrete class service
  - [x] `ExceptionMapperRegistry::class` → factory-backed service
  - [x] `ErrorHandler::class` → factory-backed service
  - [x] `ErrorHandlerInterface::class` → alias to `ErrorHandler::class`
- [x] Existing bindings consumed:
  - [x] `TagRegistry::class`
  - [x] `ContainerInterface::class`
  - [x] `ConfigRepositoryInterface::class`
  - [x] `CorrelationIdProviderInterface::class`
  - [x] `SensitiveDataRedactorInterface::class`
  - [x] `ErrorReporterPortInterface::class`
  - [x] `TracerPortInterface::class`
  - [x] `MeterPortInterface::class`
  - [x] `LoggerInterface::class`
  - [x] `Stopwatch::class`
- [x] Reporter ownership:
  - [x] Foundation already provides `ErrorReporterPortInterface` through `NoopErrorReporter`
  - [x] `platform/errors` consumes this binding
  - [x] concrete reporting adapters MAY replace the composition-level binding through their owning integration
  - [x] this epic MUST NOT add `Coretsia\Platform\Errors\Reporting\ErrorReporter`
  - [x] this epic MUST NOT override the Foundation reporter binding
- [x] Redaction ownership:
  - [x] `platform.redaction` owns the concrete implementation
  - [x] `platform.errors` consumes `SensitiveDataRedactorInterface`
  - [x] no duplicate redaction provider, classifier, policy registry, or hasher
  - [x] a missing required redactor is a module-composition failure, not permission to bypass redaction
- [x] Container invariants:
  - [x] registration uses canonical declarative definitions
  - [x] source and compiled definitions MUST be equivalent
  - [x] no runtime closures in declarative definitions
  - [x] no service resolution during definition production
  - [x] no dynamic provider registration during error handling
  - [x] no second container or service locator implementation

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [x] Context reads:
  - [x] canonical correlation source: `CorrelationIdProviderInterface::correlationId()`
  - [x] Foundation owns the underlying `ContextKeys::CORRELATION_ID` read and ULID validation
  - [x] `ContextKeys::UOW_ID`: N/A — no consumer in this package
  - [x] `ContextKeys::UOW_TYPE`: N/A — no consumer in this package
- [x] Context discipline:
  - [x] explicit `ErrorHandlingContext` is forwarded unchanged only to trusted mapper implementations
  - [x] when explicit context is absent, construct optional mapping context only from canonical safe correlation data
  - [x] reporting context MUST be constructed independently from caller-supplied mapping metadata, using the invocation-local canonical correlation snapshot
  - [x] the canonical correlation provider MUST NOT be queried a second time for reporting
  - [x] explicit mapping context MUST NOT override the reporting correlation snapshot
  - [x] reporting context MUST contain only:
    - [x] `operation = map`
    - [x] `correlationId = canonical safe id|null`
    - [x] `metadata = []`
  - [x] caller-supplied arbitrary metadata MUST NOT be forwarded to reporters, logs, metrics, or spans
  - [x] correlation id MUST NOT be copied into `ErrorDescriptor.extensions`
  - [x] correlation id MUST NOT become a metric label
  - [x] missing or failing correlation enrichment MUST NOT change the normalized descriptor
  - [x] invalid provider values rejected by `ErrorHandlingContext` MUST be treated as unavailable correlation enrichment
  - [x] mapping and reporting MUST use the same invocation-local null correlation snapshot after rejection
  - [x] no retry, independent correlation lookup, or package-local identifier parser is permitted
  - [x] MUST NOT import or resolve `ContextStore`, `ContextAccessorInterface`, or `CorrelationIdProvider` concrete implementations
  - [x] MUST NOT generate, replace, or write correlation identifiers
- Context writes:
  - N/A
- [x] UnitOfWork:
  - [x] ErrorHandler is a format-neutral normalization service used by caller-owned runtime boundaries
  - [x] MUST NOT create, begin, finish, abort, or replace a Kernel UnitOfWork
  - [x] MUST NOT call `KernelRuntimeInterface::runUnitOfWork()`
  - [x] MUST NOT alter caller-owned UoW outcome or reset precedence
  - [x] MUST NOT read or write Kernel UoW lifecycle state
  - [x] no UoW-specific mapping or enrichment implementation
- [x] Reset discipline:
  - [x] expected: none
  - [x] all mutable invocation values remain method-local
  - [x] no mapper instances, Throwable references, descriptors, contexts, spans, or timing tokens retained between calls
  - [x] no package-owned `ResetInterface`
  - [x] no `kernel.reset` registration
  - [x] no `kernel.stateful` registration
  - [x] existing Foundation/Kernel reset orchestration remains authoritative
  - [x] repeated handling in long-running workers MUST remain invocation-isolated

#### Observability (policy-compliant)

- [x] Reporting policy:
  - [x] `errors.reporting.enabled = false` disables optional error telemetry, mapper-failure diagnostics, and normalized reporting emission
  - [x] disabled reporting MUST NOT disable mapping, fallback, or redaction
  - [x] enabled reporting uses existing injected observability ports
  - [x] `min_severity`, `exclude_codes`, and `sample_rate` filter only `ErrorReporterPortInterface` invocation
  - [x] mapping metrics, spans, timing, and final normalization outcome MUST remain independent of reporter eligibility
  - [x] `mapper_failures.enabled` controls only internal safe mapper-failure logging when global reporting is enabled
  - [x] reporter suppression MUST NOT be treated as `outcome = fallback`
  - [x] Noop implementations remain valid baseline bindings
- [x] Spans:
  - [x] canonical name: `error.map`
  - [x] owner: `platform/errors`
  - [x] started only when reporting is enabled
  - [x] span starts before mapper registry dispatch
  - [x] initial attributes contain only:
    - [x] `operation = map`
  - [x] final attributes contain only:
    - [x] `operation = map`
    - [x] `outcome = ok|fallback`
  - [x] final outcome is determined only after redaction, descriptor validation, and fallback selection
  - [x] set final attributes in an independently protected operation
  - [x] span MUST measure normalization, not reporter execution
  - [x] span MUST be finalized before invoking `ErrorReporterPortInterface`
  - [x] span creation failures MUST NOT affect mapping
  - [x] span attribute failures MUST NOT affect mapping
  - [x] `SpanInterface::end()` MUST be attempted exactly once for every successfully created span
  - [x] span termination MUST occur in a protected finally path
  - [x] span termination failures MUST be swallowed without retries or recursive reporting
  - [x] span end failures MUST NOT escape
  - [x] MUST NOT record raw Throwable objects or call unsafe exception recording
- [x] Metrics:
  - [x] `error.map_total`
    - [x] type: `counter`
    - [x] API: `MeterPortInterface::increment()`
    - [x] labels: `operation`, `outcome`
  - [x] `error.map_duration_ms`
    - [x] type: `observe`
    - [x] API: `MeterPortInterface::observe()`
    - [x] value: non-negative integer milliseconds
    - [x] labels: `operation`, `outcome`
  - [x] allowed `operation`: `map`
  - [x] allowed `outcome`: `ok|fallback`
  - [x] `ok`: a registered mapper returned an accepted descriptor
  - [x] `fallback`: no registered mapper produced an accepted descriptor, or fail-closed fallback was required
  - [x] mapper failures do not create a third public normalization outcome
  - [x] metric names and labels MUST be registered in `docs/ssot/observability.md`
  - [x] no Throwable class, error message, path, correlation id, UoW id, or user identifier in metric labels
  - [x] metric emission failures MUST be isolated independently
- [x] Timing:
  - [x] use `Coretsia\Foundation\Time\Stopwatch`
  - [x] `start(): int`
  - [x] `stop(int $startedAt): int`
  - [x] timing token is invocation-local
  - [x] start timing before mapper registry dispatch when reporting is enabled
  - [x] stop timing after final descriptor selection and before reporter invocation
  - [x] measured operation includes mapper discovery, mapping, redaction, validation, and fallback selection
  - [x] measured duration MUST NOT include reporter execution
  - [x] failed timing MUST NOT trigger another mapping attempt
  - [x] timing token MUST NOT appear in descriptors, logs, spans, metrics labels, or context
  - [x] failed timing MAY use `0` as a safe duration fallback
  - [x] MUST NOT introduce another clock or duration utility
- [x] Logs:
  - [x] mapper failures MAY be logged with fixed event `error.mapper_failed`
  - [x] allowed diagnostic fields:
    - [x] `operation`
    - [x] `outcome`
    - [x] `reason`
  - [x] reason values MUST be static, low-cardinality owner-approved tokens
  - [x] no raw Throwable message, stack trace, class name, service id, filesystem path, SQL, credentials, tokens, or arbitrary context metadata
  - [x] logging failures MUST NOT escape
  - [x] failed observability operations MUST NOT recursively report themselves
- [x] Reporter:
  - [x] use the existing `ErrorReporterPortInterface`
  - [x] invoke only after successful selection of a safe descriptor
  - [x] invoke only when global reporting is enabled and the descriptor passes severity, exclusion, and sampling policies
  - [x] apply filters in fixed order: minimum severity → excluded codes → sampling
  - [x] preserve at-most-once reporter invocation
  - [x] pass only normalized descriptor data and sanitized safe context
  - [x] reporter filtering, sampling, or sink failures MUST NOT replace the returned descriptor
  - [x] MUST NOT apply reporter-only filters to canonical mapping telemetry
- [x] Reporting ownership:
  - [x] `ErrorReporterPortInterface` owns normalized error reporting
  - [x] ErrorHandler MUST invoke the injected reporter at most once per `handle()` call
  - [x] ErrorHandler MUST NOT reproduce reporter-owned normalized error log events
  - [x] `error.map` spans and metrics instrument only the normalization operation
  - [x] mapper-failure diagnostics are internal mapping diagnostics, not additional normalized error reports
  - [x] optional mapping telemetry MUST NOT recursively invoke the reporter
  - [x] reporter failure MUST NOT trigger a second reporting attempt

#### Errors

- Exceptions introduced:
  - N/A
- [x] Canonical fallback:
  - [x] code: `CORETSIA_UNHANDLED_EXCEPTION`
  - [x] message: `Unhandled exception.`
  - [x] severity: `ErrorSeverity::Error`
  - [x] httpStatus: `null`
  - [x] extensions: `[]`
  - [x] fallback is constructed only from static safe values
  - [x] fallback behavior is transport-neutral
- [x] Mapper dispatch:
  - [x] preserve `TagRegistry::all(ReservedTags::ERROR_MAPPER)` order
  - [x] resolve each candidate lazily
  - [x] `null` result continues discovery
  - [x] first accepted non-null descriptor wins
  - [x] invalid services and mapper failures are isolated
  - [x] exhausted discovery uses the default mapper
  - [x] the default mapper is not part of tagged discovery
- [x] ErrorHandler failure policy:
  - [x] mapper resolution failure → continue discovery
  - [x] mapper execution failure → continue discovery
  - [x] registry enumeration/dispatch failure → fixed safe fallback
  - [x] registry-level failure MUST NOT restart mapper enumeration
  - [x] invalid mapper result → continue discovery
  - [x] no mapper match → safe fallback
  - [x] invalid descriptor/redaction output → safe fallback
  - [x] redactor failure → safe fallback
  - [x] reporter failure → preserve selected descriptor
  - [x] logger failure → preserve selected descriptor
  - [x] tracer failure → preserve selected descriptor
  - [x] meter failure → preserve selected descriptor
  - [x] Stopwatch failure → preserve selected descriptor
- [x] Failure diagnostics:
  - [x] mapper failures use fixed safe reason tokens
  - [x] no new `MapperFailedException` is required
  - [x] no new global error-code registry
  - [x] no exception chaining or previous Throwable retention
  - [x] internal mapper failures MUST NOT expose raw errors through public descriptors
  - [x] stable normalized fallback codes are documented in `docs/ssot/errors-boundary.md`
- [x] Runtime guarantee:
  - [x] `ErrorHandler::handle()` MUST return a canonical `ErrorDescriptor` for ordinary runtime Throwable and collaborator-failure scenarios
  - [x] collaborator failures MUST NOT propagate outside the handler boundary
  - [x] no recursive fallback handling
  - [x] PHP engine termination and unrecoverable process failures are outside this runtime guarantee

#### Security / Redaction

- [x] Canonical redaction authority:
  - [x] `SensitiveDataRedactorInterface`
  - [x] implementation owner: `platform/redaction`
  - [x] `RedactionContext('errors.descriptor', RedactionMode::Placeholder)`
  - [x] no package-local key classifier
  - [x] no package-local value classifier
  - [x] no package-local hashing implementation
  - [x] no package-local redaction policy registry
  - [x] no configurable redaction bypass
- [x] Safe-by-construction mapping:
  - [x] mapper codes MUST be stable and non-sensitive
  - [x] mapper messages MUST be fixed owner-approved public messages
  - [x] mapper messages MUST NOT interpolate Throwable messages, arbitrary input values, environment data, filesystem paths, SQL, or runtime context values
  - [x] dynamic sensitive values MUST NOT be embedded inside otherwise safe-looking message strings
  - [x] mapper codes MUST be stable owner-defined identifiers, not dynamically derived from input or Throwable data
  - [x] unknown or unclassified values MUST NOT be treated as safe merely because shared redaction did not match them
  - [x] enabled mapper implementations are trusted contributors bound by the existing ExceptionMapperInterface safety contract
  - [x] the handler MUST NOT introduce a duplicate free-text secret scanner, taint engine, or sensitive-value classifier
  - [x] protection against contract-violating mapper implementations MUST be covered by mapper-owner security tests and review, not assumed from generic redaction alone
  - [x] mapper extensions MUST be safe, bounded, deterministic json-like values
  - [x] all mapper implementations MUST satisfy the existing `ExceptionMapperInterface` safety contract
  - [x] no direct copying of `Throwable::getMessage()` into descriptor messages
  - [x] no direct copying of Throwable properties or arbitrary context metadata into extensions
  - [x] `ErrorDescriptor` remains the final structural and semantic-key validation authority
  - [x] redaction is defense in depth and MUST NOT be treated as proof that arbitrary unmatched values are safe
- [x] Descriptor validation:
  - [x] preserve the canonical `ErrorDescriptor` field set
  - [x] preserve `code`, `message`, `severity`, `httpStatus`, and `extensions` semantics
  - [x] no new `detail` field
  - [x] no transport-specific fields
  - [x] reject invalid redacted output through the contracts-owned `ErrorDescriptor::fromArray()` API
  - [x] `fromArray()` MUST delegate semantic and extension validation to the existing constructor
  - [x] `platform/errors` MUST NOT implement another descriptor schema validator
  - [x] failures during redaction or reconstruction select the fixed safe fallback
  - [x] never return raw or partially processed candidate data after redaction failure
- [x] MUST NOT leak:
  - [x] Authorization headers
  - [x] Cookie and session identifiers
  - [x] credentials, passwords, tokens, and private keys
  - [x] environment secrets
  - [x] raw SQL or query payloads
  - [x] request/response bodies
  - [x] raw exception messages and stack traces
  - [x] private customer data and PII
  - [x] absolute local paths
  - [x] arbitrary runtime context metadata
- [x] Allowed safe derivations:
  - [x] stable non-sensitive error codes
  - [x] fixed safe reason tokens
  - [x] bounded counts
  - [x] safe lengths where owner policy permits disclosure
  - [x] safe hashes only where non-reconstruction and disclosure policy permit
  - [x] validated opaque identifiers only in owner-approved context channels
- [x] Security invariants:
  - [x] classifier non-matches MUST NOT be interpreted as authorization to disclose raw data
  - [x] `Placeholder` is the mandatory baseline redaction mode
  - [x] no implicit `Hash`, `Length`, or `HashAndLength` disclosure
  - [x] no raw-detail mode for local, development, testing, or production environments
  - [x] every returned descriptor MUST preserve the existing contracts-level invariants

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [x] Context and correlation:
  - [x] canonical correlation provider returns a valid safe id
  - [x] missing correlation id produces no enrichment
  - [x] throwing correlation provider does not affect mapping
  - [x] malformed correlation-provider values rejected by `ErrorHandlingContext` do not escape `handle()`
  - [x] rejected correlation values produce null enrichment without changing descriptor selection
  - [x] rejected correlation values are not forwarded to mapping or reporting sinks
  - [x] correlation provider is not queried again after a rejected value
  - [x] correlation provider is invoked at most once per handle invocation
  - [x] mapping and reporting use the same captured correlation id when no explicit mapping context is supplied
  - [x] explicit mapper context remains unchanged
  - [x] mapper-side Context mutations cannot change the captured reporting correlation id
  - [x] explicit mapper context is preserved
  - [x] reporting context contains only allowlisted fields
  - [x] arbitrary caller metadata is not forwarded to reporter or telemetry sinks
  - [x] correlation id is never copied into descriptor extensions or metric labels
  - [x] no direct ContextStore or ContextAccessor dependency
  - [x] no Context writes occur
- [x] UnitOfWork:
  - [x] handler does not create or finish a Kernel UoW
  - [x] repeated calls retain no previous invocation state
  - [x] no `kernel.reset` or `kernel.stateful` registrations
- [x] Mapper discovery:
  - [x] priority DESC ordering
  - [x] serviceId ASC ordering using canonical TagRegistry behavior
  - [x] duplicate `(tag, serviceId)` first-wins semantics
  - [x] no consumer-side sorting or deduplication
  - [x] lazy service resolution
  - [x] no mapper service resolution when the registry is constructed
  - [x] first non-null descriptor wins
  - [x] all-null mappings select fallback
  - [x] empty registry selects fallback
  - [x] invalid service, invalid metadata, and mapper failures remain isolated
- [x] Redaction:
  - [x] uses `SensitiveDataRedactorInterface`
  - [x] no package-local redaction engine
  - [x] safe descriptor round-trip preserves canonical shape
  - [x] known classified sensitive keys and values are redacted according to the shared redaction policy
  - [x] invalid or failed redaction MUST NOT return the original candidate descriptor
  - [x] unclassified arbitrary free-text values are not assumed to be sanitized
  - [x] mapper-owned safe-message and safe-extension derivation remains mandatory
  - [x] malformed redaction output selects safe fallback
  - [x] redactor exceptions select safe fallback
  - [x] forbidden extension keys remain rejected
  - [x] resource-limit violations remain fail-closed
- [x] Observability:
  - [x] reporting disabled suppresses optional emission
  - [x] reporting enabled invokes canonical ports
  - [x] metrics use registered names and labels
  - [x] duration metrics use integer milliseconds
  - [x] span end is attempted after successful span creation
  - [x] reporter/logger/tracer/meter failures are independently isolated through throwing fixtures
  - [x] Stopwatch uses the real Foundation implementation and valid invocation-local timing tokens
  - [x] Stopwatch failure isolation remains defensive behavior without requiring an injectable throwing Stopwatch fixture
  - [x] no package-local clock or timing port is introduced solely for testing
  - [x] no recursive error reporting
  - [x] no sensitive values or identifiers in metric labels
- [x] Architecture:
  - [x] module metadata and Composer metadata match
  - [x] module requirements resolve through canonical ModulePlan
  - [x] redaction binding comes from the selected `platform.redaction` module
  - [x] Source and compiled container definitions are equivalent
  - [x] no duplicate reporter or redactor bindings
  - [x] no direct `platform/redaction` implementation dependency
  - [x] no forbidden HTTP/CLI/Worker implementation dependency
  - [x] generated package index, installation catalog, and Deptrac configuration are consistent

#### Test harness / fixtures (when integration is needed)

- [x] use the existing monorepo package testing infrastructure
- [x] compose Foundation, Redaction, and Errors providers through the canonical container APIs
- [x] provide deterministic fake mapper services through `error.mapper`
- [x] provide throwing mapper, reporter, logger, tracer, meter, correlation provider, and redactor fixtures
- [x] use the real `Coretsia\Foundation\Time\Stopwatch` for timing integration tests
- [x] verify that timing uses valid invocation-local tokens and emits non-negative integer milliseconds
- [x] Stopwatch failure isolation MUST remain defensive runtime behavior without introducing a package-local timing abstraction solely for fault injection
- [x] use the real Foundation TagRegistry for ordering and duplicate-registration tests
- [x] do not construct a second production container implementation
- [x] do not alter Kernel ModulePlan or UoW implementations for test convenience
- [x] preserve package isolation and source/compiled parity invariants

### Tests (MUST)

- Unit:
  - [x] `packages/platform/errors/tests/Unit/DefaultExceptionMapperTest.php`
    - [x] maps arbitrary Throwable types to the same safe fallback
    - [x] never copies Throwable messages or traces
    - [x] preserves canonical descriptor shape

  - [x] `packages/platform/errors/tests/Unit/ErrorHandlerFallbackTest.php`
    - [x] empty registry
    - [x] all-null registry
    - [x] failed mapper
    - [x] invalid mapper result
    - [x] safe fallback code and message

  - [x] `packages/platform/errors/tests/Unit/ErrorHandlerRedactionTest.php`
    - [x] fixed redaction scope and Placeholder mode
    - [x] safe descriptor reconstruction
    - [x] malformed redactor output
    - [x] redactor failure
    - [x] no raw candidate leakage after redaction or reconstruction failure
    - [x] known classified sensitive values are not exposed in returned descriptors
    - [x] safe-by-construction requirements remain owned by mapper implementations
    - [x] whole-value sensitive string redaction changing `message` to a map selects safe fallback
    - [x] valid owner-approved descriptor preserves the original canonical field types
    - [x] nested sensitive extension replacement remains subject to ErrorDescriptor validation
    - [x] arbitrary unclassified embedded strings are not assumed to be sanitized by the shared redactor
    - [x] redaction cannot replace mapper-owner safe-message derivation requirements

  - [x] `packages/platform/errors/tests/Unit/ErrorsServiceFactoryTest.php`
    - [x] validated consumption of all five reporting configuration values
    - [x] default configuration constructs registry and handler successfully
    - [x] missing or incorrectly typed reporting values fail during construction
    - [x] reject unknown severity values
    - [x] reject invalid excluded-code list items
    - [x] reject negative, non-integer, and above-10000 sample rates
    - [x] verify `0` and `10000` are accepted sample-rate boundaries
    - [x] reject invalid mapper-failure diagnostics flags
    - [x] correct immutable constructor-policy injection
    - [x] no reporting config reads during mapper dispatch or `handle()`
    - [x] no package-local redactor, reporter, or config-validator construction

- Contract:
  - [x] `packages/platform/errors/tests/Contract/ErrorsModuleComposerMetadataContractTest.php`
    - [x] Composer identity, PSR-4 namespace, module metadata, and dependencies

  - [x] `packages/platform/errors/tests/Contract/ErrorsProviderDefinitionsContainNoClosuresContractTest.php`
    - [x] declarative definitions contain no closures or runtime objects

  - [x] `packages/platform/errors/tests/Contract/ErrorsProviderSourceDefinitionsParityTest.php`
    - [x] source and compiled definition parity

  - [x] `packages/platform/errors/tests/Contract/ErrorHandlerNeverThrowsContractTest.php`
    - [x] handler returns a canonical descriptor despite injectable collaborator failures
    - [x] mapper resolution and execution failures are independently isolated
    - [x] redaction and reconstruction failures select the fixed safe fallback
    - [x] reporter, logger, tracer, meter, and correlation-provider failures do not replace the selected descriptor
    - [x] handler contains an outer Throwable boundary around registry dispatch
    - [x] registry-level failure handling MUST NOT require modification of Foundation TagRegistry
    - [x] no recursive registry dispatch or fallback redaction occurs

  - [x] `packages/platform/errors/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
    - [x] Foundation Noop reporter, logger, tracer, and meter compatibility

  - [x] `packages/platform/errors/tests/Contract/ErrorsDoNotDuplicateRedactionPolicyContractTest.php`
    - [x] no concrete redaction implementation imports
    - [x] no package-local classifiers, hashers, or policy registries

- Integration:
  - [x] `packages/platform/errors/tests/Integration/RegistryDeterministicOrderTest.php`
    - [x] priority DESC, serviceId ASC, and first-wins behavior

  - [x] `packages/platform/errors/tests/Integration/MapperLazyResolutionTest.php`
    - [x] tagged mapper services resolve only during dispatch

  - [x] `packages/platform/errors/tests/Integration/MapperFailureIsolationTest.php`
    - [x] throwing and invalid mapper candidates do not break normalization
    - [x] subsequent valid mapper can still produce the selected descriptor
    - [x] a failed mapper followed by a successful mapper produces the successful descriptor
    - [x] a failed mapper followed by a successful mapper produces `outcome = ok`
    - [x] all mapper candidates exhausted produces `outcome = fallback`

  - [x] `packages/platform/errors/tests/Integration/DefaultMapperCatchesAnyThrowableTest.php`
    - [x] safe fallback for otherwise unhandled Throwable

  - [x] `packages/platform/errors/tests/Integration/ErrorDescriptorIsRedactedTest.php`
    - [x] real redaction port integration
    - [x] forbidden data does not leak
    - [x] invalid output fails closed

  - [x] `packages/platform/errors/tests/Integration/ErrorReportingPolicyTest.php`
    - [x] disabled and enabled reporting behavior
    - [x] reporter, logger, tracer, and meter invocation policy
    - [x] minimum severity accepts descriptors at or above the configured threshold
    - [x] minimum severity suppresses only reporter invocations below the threshold
    - [x] all four canonical `ErrorSeverity` values follow `info < warning < error < critical`
    - [x] excluded codes suppress matching reporter invocations using strict case-sensitive matching
    - [x] excluded codes do not prevent mapping, redaction, fallback selection, or descriptor return
    - [x] `sample_rate = 0` suppresses reporter invocation
    - [x] `sample_rate = 10000` preserves every otherwise eligible reporter invocation
    - [x] intermediate sampling rates use a bounded `random_int(0, 9999)` decision without changing descriptors
    - [x] sampling tests MUST NOT depend on probabilistic event counts or flaky statistical assertions
    - [x] sampling failure does not escape or replace the descriptor
    - [x] reporter filters do not suppress canonical mapping spans, metrics, or timing
    - [x] reporter suppression does not change `outcome = ok|fallback`
    - [x] `mapper_failures.enabled = false` suppresses internal mapper-failure logs
    - [x] mapper-failure logs require both global reporting and mapper-failure diagnostics to be enabled
    - [x] mapper-failure diagnostics remain independent of reporter severity, exclusion, and sampling filters
    - [x] default reporting policy preserves existing behavior
    - [x] span starts before registry dispatch
    - [x] span starts with operation-only attributes
    - [x] final outcome is written after descriptor validation and fallback selection
    - [x] successfully created span is ended exactly once
    - [x] span finalization precedes reporter invocation
    - [x] reporter execution is excluded from measured normalization duration
    - [x] metric emission failure does not prevent span finalization
    - [x] span attribute failure does not prevent span end
    - [x] span end failure does not prevent reporting or descriptor return
    - [x] disabled reporting does not affect mapping or redaction
    - [x] reporter is invoked at most once for each handle invocation

  - [x] `packages/platform/errors/tests/Integration/ErrorHandlingContextIsolationTest.php`
    - [x] canonical correlation-provider success, null, and failure cases
    - [x] caller-supplied mapping context preservation
    - [x] sanitized reporting-context construction
    - [x] no arbitrary caller metadata forwarded to reporting sinks
    - [x] no Context writes or identifier creation

  - [x] `packages/platform/errors/tests/Integration/ErrorHandlingUoWIsolationTest.php`
    - [x] handler does not introduce a nested UoW
    - [x] no reset registration or mutable invocation state

  - [x] `packages/platform/errors/tests/Integration/ErrorsModuleCompositionTest.php`
    - [x] canonical ModulePlan dependency closure
    - [x] Redaction module selected through declared requirements
    - [x] Foundation Noop observability and reporter bindings available
    - [x] exactly one canonical ErrorHandlerInterface binding
    - [x] exactly one canonical SensitiveDataRedactorInterface binding
    - [x] ErrorHandlerInterface resolves to the platform/errors implementation
    - [x] missing required redactor causes deterministic composition/resolution failure
    - [x] missing required reporter causes deterministic composition/resolution failure
    - [x] missing required correlation provider causes deterministic composition/resolution failure
    - [x] no package-local fallback bindings are registered for missing dependencies
    - [x] no implicit module enablement from Composer installation
    - [x] source and compiled container resolution produce equivalent service types and behavior

- Gates/Arch:
  - [x] package PHPUnit tests green
  - [x] PHPStan green
  - [x] repository formatting/static-analysis gates green
  - [x] canonical dependency matrix gate green
  - [x] Deptrac green
  - [x] generated package index check green
  - [x] generated installation catalog check green
  - [x] source/compiled container parity green
  - [x] deterministic rerun-no-diff checks green

### DoD (MUST)

- [x] Spec locked (this epic text reviewed + no open questions)
- [x] Implementation complete
- [x] Tests green
- [x] Gates/Arch green
- [x] Docs complete
- [x] Composer/module metadata consistent
- [x] Canonical ModulePlan dependency closure verified
- [x] Existing `ReservedTags::ERROR_MAPPER` reused without modification
- [x] ExceptionMapper registry deterministic and lazy
- [x] Default fallback mapper remains outside tagged discovery
- [x] ErrorHandler returns a canonical ErrorDescriptor without propagating runtime collaborator failures
- [x] ErrorDescriptor canonical shape preserved
- [x] Mandatory redaction consumes `SensitiveDataRedactorInterface`
- [x] No duplicate sensitive-data policy or redaction engine
- [x] Existing Foundation Noop reporter reused
- [x] CLI normal-path Throwable normalization consumes canonical `ErrorHandlerInterface`
- [x] Legacy `CliErrorHandler` implementation and DI registration removed
- [x] CLI-owned `CliExceptionMapper` is registered through `error.mapper`
- [x] CLI known-error codes and reason tokens remain backward compatible
- [x] CLI unknown-error presentation remains `CORETSIA_CLI_INTERNAL_ERROR`
- [x] CLI module dependency closure includes `platform.errors`
- [x] Framework baseline physically installs Errors through CLI dependency closure
- [x] Canonical preset root selections remain unchanged
- [x] CLI source and compiled container definitions remain equivalent
- [x] CLI output buffering, redaction, exit codes, and UoW semantics remain unchanged
- [x] Worker task settlement and lifecycle protocols remain unchanged
- [x] No second CLI or Worker error-normalization pipeline introduced
- [x] No duplicate reporter implementation or binding
- [x] No new exception hierarchy or error-code registry
- [x] No nested Kernel UnitOfWork
- [x] No package-local reset orchestration or mutable invocation state
- [x] Observability names and labels registered in canonical SSoT
- [x] Reporter, logger, tracer, meter, and timing failures isolated
- [x] All five reporting configuration keys are strictly validated and consumed at service construction
- [x] Reporter minimum-severity, excluded-code, and sampling policies are implemented and tested
- [x] Mapper-failure diagnostics are independently configurable without affecting mapper dispatch
- [x] Reporter-only filters do not affect normalization, redaction, fallback, or mapping telemetry
- [x] No package-local reporting-policy registry, RNG service, or config-validator layer introduced
- [x] No forbidden runtime adapter dependencies
- [x] README sections complete
- [x] Determinism: rerun-no-diff (where applicable)

---

### 3.20.0 Platform logging (PSR-3) (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.20.0"
owner_path: "packages/platform/logging/"

package_id: "platform/logging"
composer: "coretsia/platform-logging"
kind: runtime
module_id: "platform.logging"

goal: "Забезпечити реальний PSR-3 logging backend за замовчуванням у всіх canonical presets, починаючи з micro, із підтримкою фіксованого STDERR stream backend і контрольованого daily file backend; зберегти target-local відключення через moduleOverrides.exclude або logging.driver='null', Foundation NoopLogger як fallback, configurable deterministic jsonl/logfmt output, UoW-scoped correlation enrichment, mandatory shared redaction і fail-safe write semantics без дублювання Core capabilities."
provides:
- "coretsia/framework installs platform-logging as part of the default framework distribution."
- "Every canonical preset selects platform.logging by default through its excludable modules list."
- "Foundation NoopLogger remains available when platform.logging is not selected by the effective ModulePlan, explicitly excluded, or configured with logging.driver='null'."
- "An enabled preset referencing a physically missing platform.logging module fails closed during canonical module planning; missing required packages are never silently replaced with NoopLogger."
- "ContextBagProcessor enriches records from canonical ContextAccessorInterface and CorrelationIdProviderInterface without owning ContextStore state."
- "Configurable deterministic jsonl/logfmt output; jsonl reuses Foundation StableJsonEncoder and both formats emit exactly one LF-terminated record."
- "LogRecordPolicy enforces logging sink admissibility through its own canonical schema and the shared SensitiveKeyClassifierInterface, and applies mandatory SensitiveDataRedactorInterface redaction; no duplicate generic classifier or redaction engine."
- "Safe stream writes with deterministic no-throw degradation for internal logging failures."
- "logging.driver selects exactly stream, file, or null; stream writes only to the fixed php://stderr destination."
- "The file backend writes append-only daily UTC files using the canonical app target, a validated configured filename stem, and the selected jsonl/logfmt extension."
- "File emission reuses the same StreamLogger record pipeline, shared redaction, formatter, severity filtering, and byte limits; no second logger or sink admission policy."
- "Daily file partitioning is automatic and non-configurable; file retention, deletion, archival, and storage quotas remain deployment-owned."
- "Cutline impact: blocks Phase 3 platform baseline cutline"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0037-platform-logging-psr3.md"
ssot_refs:
- "docs/ssot/logging-policy.md"
- "docs/ssot/sensitive-data-redaction.md"
- "docs/ssot/observability.md"
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/context-lifecycle.md"
- "docs/ssot/config-roots.md"
- "docs/architecture/DEPENDENCIES.md"
- "docs/ssot/runtime-container-definitions.md"
- "docs/ssot/modes.md"
- "docs/ssot/modules-and-manifests.md"
- "docs/ssot/application-dependency-sync.md"
- "docs/ssot/context-keys.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.90.0 — canonical observability ports and safety policy.
  - 1.200.0 — Foundation declarative DI/container baseline.
  - 1.275.0 — Foundation json-like normalization and StableJsonEncoder.
  - 2.25.0 — contracts-level SensitiveDataRedactorInterface and platform.redaction default implementation.

- Repository packaging baseline:
  - repo-root `LICENSE`, `NOTICE`, and `SECURITY.md` MUST exist before this epic is implemented.
  - `tools/build/new-package.php` and `tools/build/sync_package_scaffold.php` MUST consume those canonical root files.
  - package LICENSE, NOTICE, and SECURITY.md MUST be synchronized from repository-owned canonical sources.
  - missing root legal/security sources are an external repository-baseline blocker.
  - this epic MUST NOT synthesize root legal/security files or promote arbitrary existing package copies into canonical sources.

- Repository architecture-tooling baseline:
  - `tools/build/package_index.php`
  - `tools/build/installation_catalog.php`
  - `tools/build/deptrac_generate.php`
  - `tools/build/sync_composer_repositories.php`
  - `tools/release/sync_workspace_release_line.php`
  - canonical generators MUST be reused without implementation changes.

- Required deliverables (exact paths):
  - `packages/core/foundation/src/Logging/NoopLogger.php`
  - `packages/core/foundation/src/Provider/FoundationServiceProvider.php`
  - `packages/core/foundation/src/Serialization/StableJsonEncoder.php`
  - `packages/core/foundation/src/Context/ContextStore.php`
  - `packages/core/foundation/src/Observability/CorrelationIdProvider.php`
  - `packages/core/contracts/src/Context/ContextAccessorInterface.php`
  - `packages/core/contracts/src/Config/ConfigRepositoryInterface.php`
  - `packages/core/contracts/src/Context/ContextKeys.php`
  - `packages/core/contracts/src/Observability/CorrelationIdProviderInterface.php`
  - `packages/core/contracts/src/Security/SensitiveDataRedactorInterface.php`
  - `packages/core/contracts/src/Security/RedactionKind.php`
  - `packages/platform/redaction/src/Redaction/SensitiveKeyClassifier.php`
  - `packages/core/contracts/src/Security/RedactionContext.php`
  - `packages/core/contracts/src/Security/RedactionMode.php`
  - `packages/platform/redaction/src/Redaction/DefaultSensitiveDataRedactor.php`
  - `packages/platform/redaction/src/Provider/RedactionServiceProvider.php`

- Required config roots/keys:
  - `logging.*` — existing package-owned config root.
  - `logging.driver` — required string enum: `stream|file|null`, default `stream`.
  - `logging.file` — required configuration map containing exactly path and name.
  - `logging.file.path` — required string, default ''; an empty value means no configured file directory and is valid only when the file backend is inactive.
  - `logging.file.name` — required bounded filename stem, default `app`, matching `[a-z][a-z0-9_-]{0,63}`.
  - `logging.level` — required canonical PSR-3 severity string.
  - `logging.max_record_bytes` — required integer, range 1024..65536, default 65536.
  - `logging.service_name` — required bounded string, default `auto`.
  - `logging.format` — required string enum: `jsonl|logfmt`, default `jsonl`.
  - format selection changes only the final serialization of an already admitted and redacted canonical record.
  - both formats preserve the same canonical record fields, safe values, severity filtering, redaction, and complete-record byte limit.
  - `logging.service_name='auto'` resolves the selected canonical app target through a contracts-level runtime port.
  - an explicit service_name overrides the emitted service label but never changes the selected app target.
  - no configurable redaction subtree, classifier, mode, or disable switch.
  - package/module enablement is not controlled by `logging.*`.
  - `logging.stream` MUST NOT be a recognized configuration key.
  - `driver='stream'` uses the non-configurable internal destination `php://stderr`.
  - `driver='file'` requires a non-empty, validated, absolute local directory in `logging.file.path`.
  - `driver='null'` selects Foundation NoopLogger.
  - `logging.file.name` is a bounded filename stem matching `[a-z][a-z0-9_-]{0,63}`, without an extension or path separator.
  - The configured stem is treated as opaque validated text; date-like substrings and app-target-like substrings are never interpreted, stripped, or deduplicated.
  - The complete filename is assembled exactly once using the canonical app_target, unchanged configured stem, UTC date, and selected format.
  - The exact generated filename is `{app_target}-{file.name}-{YYYY-MM-DD}.{format}`.
  - The app_target prefix is resolved through AppTargetProviderInterface and MUST NOT be derived from service_name or caller context.
  - The YYYY-MM-DD component is a UTC calendar date; jsonl and logfmt are the only permitted file extensions.
  - File mode always partitions output by UTC day. There is no rotation-mode configuration key.
  - File retention, deletion, archival, and total storage quotas remain external deployment responsibilities.
  - File configuration MUST NOT change admission, mandatory shared redaction, severity filtering, or canonical record serialization.

- Required config roots registry:
  - `logging` MUST already be reserved in `docs/ssot/config-roots.md` with owner `platform/logging`.
  - This epic adds/implements package-owned defaults and rules under the existing root; it does not introduce a new root.

- Required tags:
  - N/A

- Required contracts / ports:
  - `Psr\Log\LoggerInterface` — primary public logging port.
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface` — validated merged runtime configuration access.
  - `Coretsia\Contracts\Runtime\AppTargetProviderInterface` — read-only canonical selected app target source.
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — read-only UoW context source.
  - `Coretsia\Contracts\Context\ContextKeys` — canonical context key vocabulary.
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface` — canonical validated correlation-id source.
  - `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` — mandatory shared redaction port.
  - `Coretsia\Contracts\Security\SensitiveKeyClassifierInterface` — read-only shared sensitive-key classification port.
  - `Coretsia\Contracts\Security\RedactionKind` — canonical classification result enum; null means unclassified, never safe for emission.
  - `Coretsia\Contracts\Security\RedactionContext` — immutable redaction boundary context.
  - `Coretsia\Contracts\Security\RedactionMode` — mandatory Placeholder mode.
  - `Psr\Clock\ClockInterface` — existing Foundation-provided timestamp source.

- Cemented APIs:
  - `ContextAccessorInterface::has(string $key): bool` MUST be used before optional reads.
  - `ContextAccessorInterface::get(string $key): mixed` MUST be used without a default argument.
  - `CorrelationIdProviderInterface::correlationId(): ?string` MUST be reused rather than introducing another correlation-id validator.
  - missing, malformed, or throwing context reads MUST NOT fail logging.
  - `SensitiveDataRedactorInterface::redactJsonLike(...)` MUST be called using immutable `RedactionContext('logging.record', RedactionMode::Placeholder)`.
  - logging MUST NOT import platform.redaction implementation classes or classifiers.
  - `SensitiveKeyClassifierInterface::classify(string $key): ?RedactionKind` MUST reuse the canonical immutable SensitiveKeyClassifier implementation provided by platform.redaction.
  - The classifier preserves the existing exact 98-alias vocabulary and ASCII-only canonicalization semantics.
  - Classification applies to complete structural keys. A consumer inspecting canonical dotted key paths MUST additionally classify the relevant path segments.
  - A null classification result MUST NOT be interpreted as proof that a key or value is safe for emission.
  - Sensitive-key classification MUST NOT replace destination-specific schema admission, semantic-key restrictions, path constraints, or mandatory shared redaction.
  - Classification failures MUST fail closed without emitting raw input, diagnostic details, or an unclassified fallback value.
  - No second alias registry, generic classifier, or package-local copy of the shared sensitive-key vocabulary may be introduced.
  - redactor non-match MUST NOT authorize raw sink emission.

- Deterministic record formatting (MUST):
  - `logging.format` selects exactly one supported formatter: `jsonl` or `logfmt`.
  - `jsonl` remains the default and preserves the existing canonical JSON-lines format.
  - `JsonLineFormatter` MUST delegate JSON serialization and final-LF framing to `StableJsonEncoder::encodeStableMap()`.
  - Foundation owns recursive map sorting, list ordering, and json-like validation for JSON serialization.
  - `LogfmtLineFormatter` MUST encode the same fully admitted, redacted, canonical record map as deterministic single-line key=value pairs.
  - logfmt root fields MUST appear in one fixed canonical order.
  - logfmt string values MUST be double-quoted with deterministic escaping of backslashes, quotes, and control characters.
  - logfmt null values MUST use the unquoted `null` token; strings containing `null` remain quoted.
  - logfmt context MUST be represented as null or one quoted compact JSON value produced through Foundation StableJsonEncoder.
  - the Foundation-owned final LF MUST be removed only when embedding a JSON context value inside a logfmt field.
  - LogfmtLineFormatter owns exactly one final LF for its own output and MUST NOT append an additional LF to embedded JSON.
  - logging MUST NOT introduce another recursive JSON sorter, json-like normalizer, JSON encoder, generic redaction engine, or canonical record schema.
  - the jsonl serialized root MUST be a JSON object, including for an empty map.
  - floats, resources, Throwable instances, Stringable objects, and arbitrary PHP objects MUST NOT reach either formatter.
  - timestamps MUST be UTC ISO-8601 strings or null when the clock fails.
  - durations MUST be non-negative integer milliseconds.
  - both formats MUST produce exactly one physical LF-terminated record for every complete successful emission.
  - both formats MUST escape or reject embedded control characters that could create additional physical records.
  - deterministic serialization means identical accepted record values and the same selected format produce identical bytes.
  - runtime timestamps are captured externally and are not claimed to be invocation-independent.

#### Compile-time deps (deptrac-enforceable) (MUST)

Direct layered source dependencies:
- `core/contracts`
- `core/foundation`

Runtime-only Composer/module dependencies:
- `core/kernel`
  - `extra.coretsia.requires` MUST include `core.kernel`.
  - Composer MUST require `coretsia/core-kernel`.
  - Kernel provides the selected app target through AppTargetProviderInterface.
  - logging MUST NOT import Coretsia\Kernel implementation classes.
  - the dependency is runtime-only and does not grant a layered source dependency.

- `platform/redaction`
  - `extra.coretsia.requires` MUST include `platform.redaction`.
  - Composer MUST require `coretsia/platform-redaction`.
  - dependency is for port availability and module planning only.
  - no production PHP source import from `Coretsia\Platform\Redaction\**` is allowed.
  - The existing SensitiveKeyClassifier implementation MUST implement the contracts-level SensitiveKeyClassifierInterface.
  - RedactionServiceProvider MUST expose SensitiveKeyClassifierInterface as a declarative alias to the existing shared SensitiveKeyClassifier service.
  - The alias MUST resolve to the identical classifier instance already injected into DefaultSensitiveDataRedactor.
  - No additional classifier instance, module, runtime dependency, or source-layer permission is introduced.

External Composer dependencies:
- `psr/log`
- `psr/clock`

Forbidden direct source dependencies:
- `core/kernel`
- `platform/http`
- `platform/errors`
- `platform/cli`
- `platform/worker`
- concrete `platform/redaction` implementation classes
- other `platform/*` packages
- `integrations/*`
- `enterprise/*`
- `devtools/*`
- vendor logging, tracing, metrics, and HTTP SDK implementations

`docs/architecture/DEPENDENCIES.md` MUST declare only the direct layered source dependencies.

#### Uses ports (API surface, NOT concrete implementation dependencies) (MUST)

- PSR:
  - `Psr\Log\LoggerInterface`
  - `Psr\Clock\ClockInterface`

- Contracts:
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Context\ContextKeys`
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface`
  - `Coretsia\Contracts\Runtime\AppTargetProviderInterface`
  - `Coretsia\Contracts\Security\SensitiveDataRedactorInterface`
  - `Coretsia\Contracts\Security\SensitiveKeyClassifierInterface`
  - `Coretsia\Contracts\Security\RedactionKind`
  - `Coretsia\Contracts\Security\RedactionContext`
  - `Coretsia\Contracts\Security\RedactionMode`

- Foundation:
  - `Coretsia\Foundation\Logging\NoopLogger`
  - `Coretsia\Foundation\Serialization\StableJsonEncoder`
  - declarative container-definition APIs

`platform.logging` MUST NOT introduce another PSR-3 logging interface, context store, correlation-id generator, sensitive-value classifier, json-like normalizer, stable JSON encoder, or reset orchestrator.

### Entry points / integration points (MUST)

- CLI:
  - N/A (used implicitly by commands elsewhere)
- HTTP:
  - `platform/http` MAY consume the PSR-3 LoggerInterface binding.
  - Canonical future owner:
    - `\Coretsia\Platform\Http\Middleware\AccessLogMiddleware::class`
    - slot: `http.middleware.system_pre`
    - priority: `910`
  - middleware ownership, tags, request classification, and safe access-log fields remain platform/http responsibilities.
  - this epic MUST NOT introduce, register, instantiate, or test HTTP middleware implementations.
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A
- Runtime module composition:
  - `coretsia/framework` MUST require `coretsia/platform-logging` as a baseline Composer dependency.
  - every canonical preset (`micro`, `express`, `hybrid`, `enterprise`) MUST select `platform.logging` by default.
  - `platform.logging` MUST be declared in each preset's `modules` list, never in `required`.
  - the module MUST remain excludable through target-local `moduleOverrides.exclude`.
  - `moduleOverrides.exclude` affects runtime selection, not Composer package installation.
  - a physically installed package MUST NOT auto-enable itself outside the selected ModulePlan.
  - platform.logging module metadata MUST require `core.foundation`, `core.kernel`, and `platform.redaction`.
  - Foundation provides LoggerInterface through NoopLogger independently of platform.logging.
  - when platform.logging is selected and logging.driver='stream', LoggerInterface resolves to StreamLogger.
  - when platform.logging is selected and logging.driver='file', LoggerInterface resolves to the same StreamLogger implementation using the validated daily file destination.
  - the file backend MUST NOT introduce another LoggerInterface binding, runtime module, service provider, or logger implementation.
  - when platform.logging is not selected or explicitly excluded from the effective ModulePlan, LoggerInterface resolves to Foundation NoopLogger.
  - when platform.logging is selected but its installed runtime descriptor is missing, Kernel module planning MUST fail with the existing ModuleRequiredMissingException semantics.
  - when platform.redaction is excluded while required by an enabled module, Kernel MUST preserve its existing dependency-conflict failure semantics.
  - no missing selected runtime module may silently degrade to NoopLogger.
  - when platform.logging is selected and logging.driver='null', LoggerInterface resolves to Foundation NoopLogger.
  - excluding platform.logging MUST NOT exclude platform.redaction when another enabled module requires redaction.
  - excluding a non-excludable required module or an active transitive dependency MUST retain existing Kernel fail-closed semantics.
  - provider-plan ordering and later-binding precedence MUST be reused from Foundation/Kernel.
  - no new module-selection, Composer-installation, or runtime enablement mechanism is introduced.

### Deliverables (MUST)

Every CREATE and MODIFY deliverable MUST define or explicitly inherit the following implementation responsibilities without introducing duplicate owners:

- Exact file path and implementation status.
- Package/layer ownership and allowed compile-time dependencies.
- Public API, constructor dependencies, configuration reads, and DI references, when applicable.
- Context reads/writes and UoW ownership; N/A when not applicable.
- Spans and metrics; N/A with rationale where instrumentation is intentionally forbidden.
- Log/output behavior and ownership; N/A where the file does not emit records.
- Reset discipline and process-lifetime versus UoW-local state.
- Errors, warning suppression, failure isolation, and primary-exception precedence.
- Security, shared redaction, sensitive-key classification, and fail-closed behavior.
- Source/compiled parity, deterministic behavior, and explicit module selection where applicable.
- Verification by unit, contract, or integration tests corresponding to the file's responsibilities.

A deliverable MUST NOT introduce a new service, registry, validator, normalizer, serializer, lock abstraction, or state owner when the required behavior is already available through an existing Coretsia contract or a compatible Foundation implementation.

Cross-cutting requirements are inherited by all applicable deliverables and MUST NOT be reimplemented independently in multiple packages.

#### Creates

Shared runtime app-target contract:

- [x] `packages/core/contracts/src/Runtime/AppTargetProviderInterface.php`
  - [x] read-only contract exposing the already selected canonical app target.
  - [x] exact method: `app(): string`.
  - [x] accepted runtime values are web, api, console, and worker.
  - [x] the contract does not select, discover, override, or cache an app target.
  - [x] no dependency on Kernel implementation classes.
  - [x] no new app-target resolver, registry, or runtime context store.

- [x] `packages/core/contracts/src/Security/SensitiveKeyClassifierInterface.php`
  - [x] read-only contracts-level sensitive-key classification port.
  - [x] exact method: `classify(string $key): ?RedactionKind`.
  - [x] reuse the existing Coretsia\Contracts\Security\RedactionKind enum.
  - [x] a null result means unclassified, not safe for emission.
  - [x] preserves the existing platform.redaction canonicalization and classification semantics.
  - [x] does not own the alias vocabulary or implement key classification.
  - [x] no constants containing sensitive-key aliases.
  - [x] no dependency on platform.redaction implementation classes.
  - [x] no configuration, mutable state, service locator, or runtime policy registry.
  - [x] does not replace SensitiveDataRedactorInterface or destination-owned sink admission.

Package scaffold:
Uses `composer package:new -- --layer=platform --slug=logging --kind=runtime`.

- [x] `packages/platform/logging/LICENSE`
- [x] `packages/platform/logging/NOTICE`
- [x] `packages/platform/logging/SECURITY.md`

- [x] `packages/platform/logging/composer.json`
  - [x] `name = coretsia/platform-logging`
  - [x] `type = library`
  - [x] `license = Apache-2.0`
  - [x] `php: ^8.4`
  - [x] `coretsia/core-contracts: ^0.10.0`
  - [x] `coretsia/core-foundation: ^0.10.0`
  - [x] `coretsia/core-kernel: ^0.10.0`
  - [x] `coretsia/platform-redaction: ^0.10.0`
  - [x] `psr/log: ^3.0`
  - [x] `psr/clock: ^1.0`
  - [x] PSR-4: `Coretsia\Platform\Logging\` → `src/`
  - [x] autoload-dev: `Coretsia\Platform\Logging\Tests\` → `tests/`
  - [x] `extra.coretsia.kind = runtime`
  - [x] `extra.coretsia.moduleId = platform.logging`
  - [x] `extra.coretsia.moduleClass = Coretsia\Platform\Logging\Module\LoggingModule`
  - [x] `extra.coretsia.providers = [Coretsia\Platform\Logging\Provider\LoggingServiceProvider]`
  - [x] `extra.coretsia.requires = [core.foundation, core.kernel, platform.redaction]`
  - [x] `extra.coretsia.conflicts = []`
  - [x] `extra.coretsia.defaultsConfigPath = config/logging.php`
  - [x] no direct source dependency on platform.redaction implementation classes
  - [x] no vendor logging SDK is required

- [x] `packages/platform/logging/src/Module/LoggingModule.php`
  - [x] final runtime module metadata class
  - [x] typed constants:
    - [x] `MODULE_ID = 'platform.logging'`
    - [x] `PACKAGE_ID = 'platform/logging'`
    - [x] `COMPOSER_PACKAGE = 'coretsia/platform-logging'`
    - [x] `KIND = 'runtime'`
    - [x] `CONFIG_ROOT = 'logging'`
  - [x] exact instance methods:
    - [x] `id(): string`
    - [x] `packageId(): string`
    - [x] `composerPackage(): string`
    - [x] `kind(): string`
    - [x] `configRoot(): string`
    - [x] `providers(): array`
  - [x] `providers()` returns only LoggingServiceProvider::class
  - [x] no config loading, service resolution, runtime initialization, or stream opening

- [x] `packages/platform/logging/src/Provider/LoggingServiceProvider.php`
  - [x] final class
  - [x] implements ServiceProviderInterface
  - [x] implements ContainerDefinitionProviderInterface
  - [x] register() calls assertDefinitionProviderRegistrationAllowed()
  - [x] register() delegates through registerDefinitionProvider($this)
  - [x] define() is the single canonical source of DI definitions
  - [x] uses the already compiled configuration model without embedding stream URI values into definition parameters
  - [x] MUST NOT declare a `logging.config` container parameter containing `php://stderr`
  - [x] MUST NOT place stream destinations, filesystem paths, stream resources, or runtime configuration objects into declarative definition values
  - [x] requireService() declares:
    - [x] AppTargetProviderInterface
    - [x] ConfigRepositoryInterface
    - [x] ContextAccessorInterface
    - [x] CorrelationIdProviderInterface
    - [x] SensitiveDataRedactorInterface
    - [x] SensitiveKeyClassifierInterface
    - [x] ClockInterface
  - [x] binds Psr\Log\LoggerInterface through classMethodFactory
  - [x] factoryClass = LoggingServiceFactory::class
  - [x] method = createLogger
  - [x] LoggingServiceFactory::createLogger() MUST exist as a public static method before the classMethodFactory definition is constructed.
  - [x] source and compiled definition tests MUST verify that the declared factory method is reflectively valid.
  - [x] factory arguments use only ContainerValueReference::service()
  - [x] exact factory references, in positional order matching LoggingServiceFactory::createLogger():
    - [x] ConfigRepositoryInterface
    - [x] ContextAccessorInterface
    - [x] CorrelationIdProviderInterface
    - [x] SensitiveDataRedactorInterface
    - [x] SensitiveKeyClassifierInterface
    - [x] ClockInterface
    - [x] AppTargetProviderInterface
  - [x] factory argument order MUST exactly match the declared createLogger() parameter order in source and compiled definitions.
  - [x] provider definitions contain no logging.config parameter
  - [x] no closures, open streams, runtime objects, or resolved services in definitions
  - [x] no provider registration-time I/O or service resolution
  - [x] AppTargetProviderInterface is supplied through the Kernel-owned declarative classMethodFactory using the existing ModulePlan runtime seed.
  - [x] selected app identity is resolved only when constructing the runtime logger.
  - [x] no Kernel implementation class or app-target literal is embedded in logging provider definitions.
  - [x] no new DI tags
  - [x] later LoggerInterface binding overrides Foundation NoopLogger only when platform.logging is enabled
  - [x] no override occurs when platform.logging is absent from the selected ModulePlan
  - [x] no direct platform.redaction implementation references
  - [x] SensitiveKeyClassifierInterface is supplied by the existing platform.redaction provider alias.
  - [x] both sensitive-data ports are required declarative service references.
  - [x] no concrete SensitiveKeyClassifier implementation is imported or constructed.
  - [x] no classifier policy, alias vocabulary, or classification result is embedded in declarative definition values.
  - [x] source and compiled definition streams remain deterministic and equivalent

- [x] `packages/platform/logging/src/Provider/LoggingServiceFactory.php`
  - [x] final stateless wiring helper
  - [x] one public static createLogger() factory entrypoint
  - [x] exact parameters, in order:
    - [x] ConfigRepositoryInterface $config
    - [x] ContextAccessorInterface $context
    - [x] CorrelationIdProviderInterface $correlations
    - [x] SensitiveDataRedactorInterface $redactor
    - [x] SensitiveKeyClassifierInterface $keyClassifier
    - [x] ClockInterface $clock
    - [x] AppTargetProviderInterface $appTarget
  - [x] return type: Psr\Log\LoggerInterface
  - [x] reads only the validated merged keys:
    - [x] logging.driver
    - [x] logging.file.path
    - [x] logging.file.name
    - [x] logging.level
    - [x] logging.max_record_bytes
    - [x] logging.service_name
    - [x] logging.format
  - [x] does not read or recognize logging.stream.
  - [x] uses ConfigRepositoryInterface::has() and get() without importing Kernel configuration implementations
  - [x] captures validated scalar settings once during logger construction
  - [x] does not read mutable configuration on every log record
  - [x] independently enforces strict types and enum values for direct factory invocation outside normal Kernel configuration validation
  - [x] validate the five shared scalar configuration keys and the required file.path/file.name structure before branching on the selected driver.
  - [x] logging.driver='null' MUST NOT bypass validation of level, max_record_bytes, service_name, format, file.path type, or file.name grammar.
  - [x] an empty file.path is valid for stream and null but invalid for file.
  - [x] validate file.path destination semantics only when driver='file'.
  - [x] a direct factory invocation with missing, malformed, or unsupported shared configuration or malformed file configuration shape fails deterministically even when the requested driver is null.
  - [x] only a fully validated configuration with driver='null' returns the existing Foundation NoopLogger.
  - [x] configuration validation occurs before any destination initialization.
  - [x] invalid file.path type or file.name grammar is a deterministic configuration failure for every driver.
  - [x] when driver='file', malformed file.path syntax or unsupported destination semantics are deterministic configuration failures; a syntactically valid but unavailable directory is an emission-time I/O failure.
  - [x] a syntactically valid but currently missing, unavailable, or unwritable file directory is an I/O availability failure.
  - [x] file-mode I/O availability failure MUST NOT replace the selected driver with Foundation NoopLogger.
  - [x] StreamLogger remains the configured file-mode LoggerInterface implementation and discards affected emissions when the destination is unavailable.
  - [x] a later emission MAY succeed if the same validated directory becomes available without changing logger configuration.
  - [x] no mutable permanently-disabled flag, retry queue, or failure registry is introduced.
  - [x] independently validates max_record_bytes as an integer in the range 1024..65536.
  - [x] independently validates format against the exact supported enum ['jsonl', 'logfmt'].
  - [x] independently validates service_name as `auto` or a bounded ASCII service token matching `[a-z][a-z0-9_-]{0,63}`.
  - [x] the service_name grammar is identical to the existing Kernel ConfigValidator reset-group-id value grammar.
  - [x] invalid service_name values fail canonical configuration validation before logger construction; direct factory invocation enforces the same invariant independently.
  - [x] service_name='auto' uses AppTargetProviderInterface::app() as the effective service name.
  - [x] explicit service_name uses the validated configured token without changing the canonical selected app target.
  - [x] AppTargetProviderInterface::app() must resolve to one of web, api, console, worker.
  - [x] captures effective service_name, canonical app_target, format, and max_record_bytes once during logger construction.
  - [x] malformed app-target or logging configuration fails deterministically without silently selecting NoopLogger.
  - [x] no app-target inference from filesystem paths, environment variables, CLI arguments, event names, or UoW context.
  - [x] format=jsonl selects JsonLineFormatter.
  - [x] format=logfmt selects LogfmtLineFormatter.
  - [x] selects the formatter once during logger construction.
  - [x] both formatters receive the same admitted and redacted canonical record schema.
  - [x] no format-specific admission, enrichment, redaction, or severity filtering is introduced.
  - [x] no runtime formatter registry, dynamic formatter discovery, or alternate JSON encoder is introduced.
  - [x] malformed/missing configuration MUST fail deterministically through the existing configuration/bootstrap boundary
  - [x] malformed configuration MUST NOT silently select NoopLogger
  - [x] driver `null` string → Foundation NoopLogger without opening any destination.
  - [x] driver `stream` → construct StreamLogger with the fixed internal php://stderr destination.
  - [x] driver `file` → construct the same StreamLogger with a validated daily file destination policy.
  - [x] php://stderr remains a non-configurable internal constant.
  - [x] stream initialization failure → Foundation NoopLogger.
  - [x] file destination availability or write failure → discard the affected emission without exposing diagnostics or mutating the configured driver.
  - [x] no silent conversion of malformed configuration into Foundation NoopLogger.
  - [x] catches destination initialization and I/O errors or PHP warnings without exposing raw diagnostics.
  - [x] no arbitrary PHP stream wrapper, remote destination, or user-controlled stream URI is accepted.
  - [x] constructs ContextBagProcessor, LogRecordPolicy, exactly one selected formatter, and StreamLogger for either active driver.
  - [x] the same LogRecordPolicy and mandatory injected redactor/classifier ports apply to stream and file.
  - [x] no FileLogger, second writer pipeline, second formatter, or second logging-specific redaction policy.
  - [x] File destination validation:
    - [x] file.path is an absolute path to a local filesystem directory, never a filename or stream URI.
    - [x] file.path is a string of 1..1024 bytes when driver='file'.
    - [x] file.path MUST reject NUL, ASCII control characters, traversal components, PHP stream wrappers, remote URI schemes, and unsupported filesystem path forms.
    - [x] POSIX destinations MUST use absolute paths beginning with `/`.
    - [x] Windows destinations MAY use canonical drive-rooted local absolute paths; drive-relative paths, UNC shares, device namespaces, and remote destinations MUST be rejected.
    - [x] Windows drive-letter syntax MUST NOT be mistaken for a PHP stream scheme.
    - [x] the configured path is normalized and checked according to the current filesystem platform without importing Kernel implementation classes.
    - [x] factory construction validates the file.path type, absolute local path syntax, length, and forbidden components without requiring the directory to exist.
    - [x] factory construction MUST NOT create, open, canonicalize, or require filesystem availability of the configured file directory.
    - [x] the validated configured directory string is captured once during logger construction.
    - [x] before every eligible file emission, the directory MUST resolve to an existing canonical local directory.
    - [x] directory resolution, availability, and write-permission failures at emission time are I/O failures, not configuration failures.
    - [x] logging MUST NOT automatically create directories or silently select another destination.
    - [x] later eligible emissions MAY succeed when the same configured directory becomes available.
    - [x] per-emission directory resolution MUST NOT modify logging configuration, app_target, service_name, or canonical record fields.
    - [x] the configured directory and its contents MUST be controlled by the trusted deployment administrator.
    - [x] detectable symlink destinations MUST be rejected before opening a generated log file.
    - [x] an opened destination handle MUST be checked as a regular file before any write attempt.
    - [x] the implementation MUST NOT claim race-free symlink protection from PHP-level is_link(), realpath(), or fstat() checks alone.
    - [x] concurrent adversarial modification of the log directory is outside the supported security model and MUST be explicitly documented.
    - [x] file.name is validated through the existing bounded reset-group-id grammar.
    - [x] file.name MUST satisfy `[a-z][a-z0-9_-]{0,63}` and is treated only as a filename stem.
    - [x] file.name MUST NOT contain a dot, extension, directory separator, or traversal component.
    - [x] the factory MUST NOT infer, strip, normalize, or deduplicate application-target or date-like substrings in file.name.
    - [x] the canonical app_target, configured filename stem, UTC date, and format extension are assembled exactly once in the specified order.
    - [x] the selected canonical app_target is the only filename-prefix source.
    - [x] the selected format is the only filename-extension source.
    - [x] the generated filename grammar is exactly `{app_target}-{file.name}-{YYYY-MM-DD}.{format}`.
    - [x] file path validation MUST NOT import Kernel implementation classes or introduce another configuration-validator engine.
    - [x] the existing Kernel ConfigValidator validates the declarative shape and supported scalar types; the factory enforces driver-dependent filesystem semantics.
    - [x] an invalid configuration fails deterministically before a logger is returned.
    - [x] filesystem permission and availability failures do not expose paths, PHP warnings, or raw Throwable details.
    - [x] neither the configured directory nor the generated filename is embedded in declarative DI parameters.
    - [x] file configuration is captured once during logger construction; the output date alone changes at record-emission time.
  - [x] uses the mandatory injected SensitiveDataRedactorInterface
  - [x] uses the mandatory injected SensitiveKeyClassifierInterface for destination-specific structural-key admission.
  - [x] passes both injected ports to LogRecordPolicy without constructing another classifier or redactor.
  - [x] the classifier port resolves through the existing platform.redaction module dependency.
  - [x] classification is not inferred from logging configuration, environment variables, or producer identity.
  - [x] MUST NOT instantiate DefaultSensitiveDataRedactor, SensitiveKeyClassifier, or any package-internal redaction classifier.
  - [x] MUST NOT import Coretsia\Platform\Redaction implementation classes.
  - [x] MUST NOT instantiate another NoopLogger class
  - [x] no dynamic DI lookups or service locator
  - [x] no mutable caches, record buffers, static mutable properties, UoW snapshots, or mutable runtime-state snapshots.
  - [x] immutable validated configuration values and canonical service identity MAY be retained for the logger lifetime as explicitly required by this epic.
  - [x] no output or self-reporting on initialization failure

- [x] `packages/platform/logging/src/Logger/StreamLogger.php`
  - [x] final implementation of Psr\Log\LoggerInterface
  - [x] implements every PSR-3 severity method and log()
  - [x] accepts the canonical PSR-3 message and context input shapes
  - [x] delegates message/context admissibility to LogRecordPolicy
  - [x] delegates UoW enrichment to ContextBagProcessor
  - [x] delegates serialization to the factory-selected JsonLineFormatter or LogfmtLineFormatter.
  - [x] uses the same record admission, enrichment, redaction, severity filtering, and write pipeline for both formats.
  - [x] does not inspect the format on every log call or introduce format-specific logging branches outside serialization.
  - [x] uses the injected ClockInterface for timestamp capture
  - [x] enforces configured minimum severity
  - [x] uses the configured max_record_bytes as the effective per-record emission limit.
  - [x] retains the non-configurable MAX_RECORD_BYTES = 65536 hard upper bound.
  - [x] uses the factory-resolved service_name and app_target as immutable logger-owned record metadata.
  - [x] service_name and app_target are independent of the current UoW and remain available outside UoW scope.
  - [x] caller context cannot override canonical service_name or app_target fields.
  - [x] service identity is not inferred from event names or producer-specific context.
  - [x] exact record processing order:
    - [x] validate PSR-3 level
    - [x] apply configured minimum severity
    - [x] perform initial message/context sink admission
    - [x] enrich with current safe UoW context
    - [x] capture and normalize UTC timestamp through ClockInterface; use null on clock failure
    - [x] assemble the complete canonical record, including trusted level, ts_utc, service_name, and app_target
    - [x] invoke mandatory shared redaction on the complete assembled record
    - [x] validate the complete post-redaction record shape and permitted fields
    - [x] serialize through the selected formatter
    - [x] select the permitted STDERR or UTC-daily-file destination for the complete serialized record
    - [x] attempt exactly one bounded write to the selected destination
  - [x] initial sink admission uses the shared classifier port through LogRecordPolicy for admitted structural key-path fields.
  - [x] initial classifier failure aborts the complete current record before shared redaction, serialization, or stream I/O.
  - [x] post-redaction classifier failure aborts the current record before serialization or stream I/O, without repeating the already completed shared redaction operation.
  - [x] both classifier failure phases are isolated through the existing outer no-throw logging boundary.
  - [x] the outer logging boundary catches internal classifier failures without exposing them to the business caller.
  - [x] classifier failures are never converted to empty-context success or a fallback log record.
  - [x] no second classification attempt, recursive logging, alternate sink, or raw diagnostic output is permitted.
  - [x] enforce the complete instance-bound key-path admission policy after shared redaction.
  - [x] never rely on structural schema validation alone when sensitive-key classification is required.
  - [x] no canonical root field is added, replaced, or reconstructed after post-redaction validation.
  - [x] invalid PSR-3 level is rejected before severity filtering
  - [x] filtered records perform no context access, redaction, clock read, formatting, or I/O
  - [x] no record may bypass the redaction and post-redaction admissibility stages
  - [x] each successfully written complete record contains exactly one frame in the selected format.
  - [x] severity ordering: debug, info, notice, warning, error, critical, alert, emergency
  - [x] never interpolates raw context values into messages
  - [x] never evaluates arbitrary Stringable objects for diagnostic output
  - [x] unsupported or unsafe message content becomes a fixed safe event token
  - [x] malformed or unsafe context branches are omitted
  - [x] jsonl records use one deterministic JSON object per line.
  - [x] logfmt records use one deterministic sequence of canonical key=value pairs per line.
  - [x] file mode uses the same immutable configured service identity, canonical app_target, selected formatter, and effective byte limit.
  - [x] filename generation never reads caller context, UoW state, or service_name overrides.
  - [x] file names follow `{app_target}-{file.name}-{YYYY-MM-DD}.{format}` exactly.
  - [x] the filename date is derived from the final post-redaction validated canonical ts_utc when it is non-null.
  - [x] the filename date MUST NOT be derived from caller context, service_name, or a separately generated log-record timestamp.
  - [x] when the final canonical ts_utc is null, file partitioning uses one invocation-local system UTC date obtained through gmdate('Y-m-d').
  - [x] the system UTC fallback is used only for filename partitioning; it MUST NOT populate or replace canonical ts_utc.
  - [x] fallback date resolution MUST NOT invoke shared redaction again, reread UoW context, or modify the canonical record.
  - [x] failure to obtain a valid UTC filename date discards the current file emission without fallback to a different destination.
  - [x] the UTC date is resolved for every admitted file emission, never cached permanently at logger construction.
  - [x] a new dated file is used automatically after the UTC day changes.
  - [x] file emission resolves and validates the configured local directory before constructing the daily destination pathname.
  - [x] missing, inaccessible, or non-directory destinations discard the current emission without raw diagnostics or fallback.
  - [x] the generated daily basename is appended only to the resolved permitted directory.
  - [x] file emission never changes the configured directory or filename stem.
  - [x] file emission opens the selected day's file in append-binary mode and closes it after the write attempt.
  - [x] directory and destination checks are repeated for each eligible emission because filesystem availability may change after logger construction.
  - [x] all filesystem checks and I/O occur only after successful severity filtering, admission, mandatory redaction, formatting, and record-byte validation.
  - [x] file mode does not retain an obsolete process-lifetime file handle across UTC day changes.
  - [x] file emission does not rename, truncate, delete, archive, compress, or rotate existing files.
  - [x] an existing generated destination MUST be a regular local file, not a symlink, directory, device, pipe, or socket.
  - [x] deployment MUST prevent untrusted modification of the log directory and destination files.
  - [x] file writes use a non-blocking exclusive flock() for each complete record.
  - [x] flock() applies to the actual opened daily log-file handle, not to an independently derived lock-anchor file.
  - [x] Foundation ScopedFileLock MUST NOT be invoked for daily log records because its directory-creation, separate lock-anchor, and permission-management semantics do not match logging destination ownership.
  - [x] the logger MUST NOT introduce another reusable filesystem lock abstraction or duplicate Foundation ScopedFileLock.
  - [x] destination locking remains a minimal logging-owned I/O operation.
  - [x] failure to acquire the lock discards the current record without waiting, retrying, or emitting diagnostics.
  - [x] one fully serialized record is submitted through exactly one fwrite() attempt while the lock is held.
  - [x] the lock is released and the file handle is closed on every exit path.
  - [x] failed or short writes are failed emissions without raw fallback or a second write.
  - [x] no guarantee of durable fsync, atomic operation across unsupported filesystems, or recovery from partial writes is claimed.
  - [x] no file retention, automatic deletion, archival, total-file-size limit, or retention configuration is introduced.
  - [x] stream and file writes are attempted without caller-visible internal logging exceptions.
  - [x] PHP stream, filesystem, and locking warnings MUST NOT escape into diagnostics or response output.
  - [x] one fully serialized bounded record is submitted through a single write attempt to the selected destination.
  - [x] partial/short writes are treated as failed emissions
  - [x] partial writes MUST NOT trigger a second raw fallback write
  - [x] no guarantee of atomic multi-process writes or complete framing after an underlying partial write is claimed
  - [x] failed emissions remain no-throw and do not recursively report themselves
  - [x] internal write failure does not permanently disable the logger through mutable failure state
  - [x] destination open, lock, write, unlock, and close warnings are suppressed and isolated without disclosure.
  - [x] MAX_RECORD_BYTES = 65536 is the non-configurable hard upper bound.
  - [x] the effective per-record byte limit is the validated logging.max_record_bytes value.
  - [x] the effective limit MUST NOT exceed MAX_RECORD_BYTES.
  - [x] the limit applies to the complete serialized UTF-8 record in either format, including the final LF.
  - [x] byte-limit enforcement occurs after the selected formatter produces the complete record.
  - [x] the final serialized byte length is measured through strlen().
  - [x] records larger than the effective limit are discarded before stream I/O.
  - [x] records of exactly the effective limit are accepted.
  - [x] no truncation of serialized JSON, message, context, or final LF is allowed.
  - [x] no alternate output channel or fallback write is attempted for oversized records.
  - [x] no recursive self-logging or self-reporting
  - [x] no in-memory per-UoW record buffer, correlation cache, or mutable policy registry
  - [x] the STDERR stream handle is process-lifetime I/O infrastructure, not UoW context state.
  - [x] daily file handles are emission-scoped I/O infrastructure, not UoW state.
  - [x] no file handle, date, lock, or failure state is retained between file emissions.
  - [x] internal write failures are no-op-safe
  - [x] invalid PSR-3 log levels follow the PSR-3 InvalidArgumentException contract; internal write failures remain no-throw

- [x] `packages/platform/logging/src/Record/LogRecordPolicy.php`
  - [x] final logging-sink admissibility policy
  - [x] consumes SensitiveDataRedactorInterface and SensitiveKeyClassifierInterface only as injected security ports.
  - [x] preserves mandatory shared redaction through SensitiveDataRedactorInterface.
  - [x] uses SensitiveKeyClassifierInterface only for read-only sensitive-key classification during sink admission.
  - [x] exact constructor dependencies, in order:
    - [x] SensitiveDataRedactorInterface $redactor
    - [x] SensitiveKeyClassifierInterface $keyClassifier
  - [x] exact public admission operations:
    - [x] admitMessage(mixed $message): string
    - [x] admitContext(array $context): ?array
    - [x] redactRecord(array $record): ?array
  - [x] retain isValidCanonicalRecord(array $record): bool only as a structural canonical-schema validator.
  - [x] static structural validation never substitutes instance-bound sensitive-key admission.
  - [x] admitContext() uses the injected classifier for applicable key_paths before allowing their inclusion.
  - [x] a rejected key_paths branch is omitted in full without retaining a partial list of its original values.
  - [x] ordinarily empty or omitted context returns null without indicating an internal classifier failure.
  - [x] classifier failure aborts admission through the existing outer StreamLogger no-throw failure boundary.
  - [x] redactRecord() applies complete structural and instance-bound semantic admission to its supplied canonical record before invoking shared redaction.
  - [x] direct redactRecord() invocation cannot bypass sensitive-key classification by skipping admitContext().
  - [x] a valid pre-admission result proceeds to exactly one mandatory shared redaction operation on the complete assembled canonical record.
  - [x] an invalid structural or semantic pre-admission result is rejected without invoking the shared redactor.
  - [x] redactRecord() performs complete structural and instance-bound semantic validation of the redacted output.
  - [x] post-redaction key-path classification never substitutes a second redaction operation.
  - [x] no raw record, previous Throwable, partial redaction result, or classifier diagnostic is retained.
  - [x] no new public logging exception, service locator, classifier registry, or mutable policy state.
  - [x] does not import or instantiate the platform.redaction classifier implementation.
  - [x] fixed RedactionContext scope `logging.record`
  - [x] fixed RedactionMode::Placeholder
  - [x] no configurable redaction mode
  - [x] no duplicate generic key/value classifiers, hashing engine, or recursive redaction walker.
  - [x] no package-local copy of the canonical sensitive-key alias vocabulary or its equivalent regex patterns.
  - [x] destination-specific structural validation remains logging-owned.
  - [x] selects only producer-owned safe-by-construction fields for emission
  - [x] declares one canonical, immutable, logging-specific sink-field allowlist
  - [x] admissibility is field-specific; unknown field names are not admitted
  - [x] the baseline field families cover existing safe producer-owned log summaries:
    - [x] operation/outcome/event/reason/code/status
    - [x] driver/control_transport/presetName
    - [x] app_target/preset
    - [x] duration_ms/exit_code/pid
    - [x] byte_count and existing explicitly approved *_count fields
    - [x] artifact_basename/artifact_path/artifact_paths/artifact_reasons
    - [x] bucket_names
    - [x] source_paths/key_paths/directive_names/source_types/source_kinds/source_layers
    - [x] moduleIds
    - [x] endpoint_hash
    - [x] canonical safe UoW/correlation identifiers
    - [x] path_template as an optional context-only field, never as a canonical root field
  - [x] key_paths use the existing bounded structural-path admission rules.
  - [x] each canonical dotted key path is checked as a complete string and by its non-empty dot-separated segments.
  - [x] every complete key and segment is classified through the injected SensitiveKeyClassifierInterface.
  - [x] any non-null RedactionKind classification for the complete key path or any segment rejects the entire key_paths context field.
  - [x] no subset of the original key_paths list is emitted after one of its items is rejected.
  - [x] malformed dotted paths, including empty segments and unsupported structural characters, are rejected before semantic classification.
  - [x] existing safe Kernel key-path representations, including bounded structural placeholders, remain admissible when they satisfy the logging schema and shared classifier policy.
  - [x] a rejected field is an ordinary omission; an exception or contract failure from the classifier aborts the entire record.
  - [x] examples such as db.password, db.client_secret, db.access_token, and user.email_address are rejected.
  - [x] unclassified names are not automatically safe; existing logging sink restrictions and producer-owned semantic safety remain mandatory.
  - [x] Kernel ConfigKernel retains ownership of its existing safe key-path producer restrictions, including sensitive-key and SQL-like patterns.
  - [x] LogRecordPolicy MUST NOT copy, import, or maintain Kernel ConfigKernel sensitive-key or SQL-like regex tables.
  - [x] LogRecordPolicy applies its own producer-neutral structural key-path schema, bounded-value constraints, and the shared sensitive-key classifier port.
  - [x] a shared classifier null result does not override producer-owned rejection rules or authorize arbitrary runtime-derived key paths.
  - [x] a producer MUST NOT pass a key path that its owner-specific policy has rejected.
  - [x] logging MUST NOT reconstruct rejected producer paths, bypass producer validation, or introduce producer-specific admission branches.
  - [x] no duplication of the canonical platform.redaction alias list is permitted.
  - [x] a rejected key path is never replaced with its raw value, a hash, a length, or a partially redacted representation.
  - [x] classifier exceptions or invalid classification results cause failure of the entire current logging admission operation.
  - [x] classifier failure MUST NOT be represented as an ordinarily empty or omitted context.
  - [x] an empty admitted context and a classifier failure MUST remain distinguishable to StreamLogger.
  - [x] the failure is propagated only to the existing outer no-throw logging boundary; it is not emitted, logged recursively, or returned to the business caller.
  - [x] no previous Throwable, raw key path, partial classification result, hash, or length is retained in logging state.
  - [x] classifier failure during initial admission aborts the record before shared redaction, formatting, or stream I/O.
  - [x] classifier failure during post-redaction validation aborts the record after the already completed redaction call.
  - [x] post-redaction classifier failure MUST NOT trigger a second redaction operation, raw fallback, formatting, or stream I/O.
  - [x] both failure phases remain distinguishable from ordinary context omission without exposing internal diagnostics.
  - [x] the same key-path safety rules apply before and after mandatory shared record redaction.
  - [x] every allowed scalar field has an exact declared type, byte limit, and semantic safety constraint
  - [x] every allowed list has a fixed maximum item count, item type, item byte limit, and deterministic ordering contract
  - [x] relative artifact/source paths reuse existing owner-defined safe-path semantics
  - [x] absolute filesystem paths and raw request paths remain forbidden
  - [x] endpoint_hash is accepted only in its existing owner-approved safe form
  - [x] count and duration fields accept non-negative bounded integers only
  - [x] exit_code and pid use their documented safe integer bounds rather than arbitrary numeric strings
  - [x] operation, outcome, event, reason, code, status, and driver values remain bounded safe categories, not arbitrary user text
  - [x] operation values MUST follow one bounded, producer-neutral category-token grammar; colon-separated values are admissible when structurally safe, without source-specific exceptions or knowledge of command names.
  - [x] log-record admission MUST depend only on the canonical field schema, field-specific safety constraints, shared redaction, and current canonical runtime context; it MUST NOT branch on producer identity, package identity, or event/message name.
  - [x] caller-supplied correlation_id/uow_id/uow_type values MUST NOT populate or override processor-owned canonical root fields.
  - [x] caller-supplied identifiers MAY remain only as bounded, validated, non-authoritative nested context fields when admitted by the same canonical sink-field policy as other context values.
  - [x] canonical root correlation_id/uow_id/uow_type MUST reflect only the current canonical context; missing, invalid, or reset context values MUST resolve to null, regardless of previously captured caller data.
  - [x] the logger MUST NOT restore completed runtime context, retain per-UoW snapshots, or introduce producer-specific context reconstruction.
  - [x] preserve existing safe-by-construction Core log summaries without widening acceptance to arbitrary map entries
  - [x] redaction results are checked again against the declared output schema
  - [x] post-redaction validation also enforces the injected sensitive-key classification policy for any remaining key_paths.
  - [x] classification is instance-bound to the injected port; no static service locator, mutable classifier registry, or globally cached policy is introduced.
  - [x] a static structural schema check, if retained, MUST NOT independently authorize final sink emission without instance-bound semantic admission.
  - [x] both jsonl and logfmt receive only records that have passed the same complete instance-bound sink admission.
  - [x] a RedactedValue map is not automatically admissible in a field requiring a scalar string
  - [x] no second key classifier, value classifier, hasher, or general-purpose json-like traversal engine.
  - [x] semantic sensitive-key classification is delegated exclusively to the shared classifier port.
  - [x] logging-specific schema, category, path, type, and bounded-value validators remain part of LogRecordPolicy.
  - [x] forbids raw transport paths, raw host, raw user-agent, raw query, raw payload, headers, cookies, Authorization, tokens, credentials, raw SQL, raw env values, and arbitrary customer data
  - [x] treats unclassified values as untrusted rather than automatically safe
  - [x] drops unsupported keys instead of preserving arbitrary metadata
  - [x] rejects or omits unsupported PHP values: Throwable, objects, resources, closures, and floats
  - [x] does not call arbitrary __toString() methods
  - [x] sanitizes user-supplied message separately from the context map
  - [x] message policy distinguishes producer-owned static event identifiers from arbitrary diagnostic text
  - [x] static event identifiers follow an exact bounded ASCII token grammar documented in logging-policy.md
  - [x] existing Core message identifiers with dot, underscore, and hyphen separators remain supported
  - [x] unsupported message types and values violating the exact event-token grammar fall back to one fixed safe event token.
  - [x] high-confidence sensitive messages detected through the shared redactor MUST NOT be emitted unchanged.
  - [x] lexical validation cannot establish the provenance of an otherwise valid-looking runtime string.
  - [x] producer-owned static event identifiers are required; arbitrary user-derived messages are forbidden by the producer contract.
  - [x] no claim is made that a PSR-3 string alone can prove absence of all sensitive content.
  - [x] lexical validity alone MUST NOT be documented as proof that a runtime-generated message contains no sensitive data
  - [x] producer-owned static-message requirements remain mandatory
  - [x] raw Stringable objects MUST NOT be evaluated through __toString() to determine sink safety
  - [x] permits only static, bounded, safe event identifiers as message values
  - [x] must not treat lexical event-name validity as proof that runtime-derived user input is safe
  - [x] owner-provided static event identifiers remain a mandatory producer contract
  - [x] uses shared redaction as defense in depth after sink-field selection
  - [x] shared redaction failure rejects the entire current record.
  - [x] no partially redacted record is returned.
  - [x] no raw passthrough, second redaction attempt, or fallback sink is permitted.
  - [x] fixed safe event-token fallback applies only to rejected messages before record redaction.
  - [x] no raw input, redaction failure, or previous Throwable is retained
  - [x] no arbitrary hash/length disclosure of low-entropy secrets
  - [x] no mutable key registries, runtime patterns, or dynamic policy configuration

- [x] `packages/platform/logging/src/Processor/ContextBagProcessor.php`
  - [x] final stateless processor
  - [x] depends on ContextAccessorInterface and CorrelationIdProviderInterface
  - [x] correlation_id comes from the existing CorrelationIdProviderInterface
  - [x] uow_id and uow_type are read through ContextAccessorInterface
  - [x] optional ContextKeys::PATH_TEMPLATE may be read only when already safe-by-construction.
  - [x] an admitted route template is placed under context.path_template, never at the record root.
  - [x] raw request paths, runtime route parameters, and unverified templates are never emitted.
  - [x] HTTP route-template production and ownership remain outside platform.logging.
  - [x] MUST NOT read raw PATH, HOST, USER_AGENT, CLIENT_IP, headers, or request body for generic enrichment
  - [x] uses ContextKeys instead of duplicated literal source-key definitions
  - [x] checks has() before get()
  - [x] does not use a default parameter in get()
  - [x] validates optional UoW identifiers and categories against bounded safe shapes
  - [x] exposes correlation_id, uow_id, and uow_type as nullable record fields
  - [x] outside UoW or on failed reads, those fields are null
  - [x] does not generate synthetic correlation IDs outside Kernel UoW ownership
  - [x] processor-owned canonical fields cannot be overridden by caller context
  - [x] no ContextStore writes, context snapshots, reset orchestration, or stored request state

- [x] `packages/platform/logging/src/Formatter/JsonLineFormatter.php`
  - [x] final stateless formatter
  - [x] accepts only a fully sanitized canonical log record map
  - [x] accepts only the canonical post-redaction record schema, not an arbitrary json-like map.
  - [x] reuses LogRecordPolicy::isValidCanonicalRecord() for structural validation before encoding.
  - [x] rejects missing, unexpected, or malformed canonical fields through that shared structural validator.
  - [x] MUST NOT maintain a second root-field allowlist, independent field-type matrix, or duplicated canonical schema.
  - [x] static structural validation does not replace the instance-bound admission already required before StreamLogger invokes the formatter.
  - [x] ts_utc is nullable and otherwise an exact UTC ISO-8601 timestamp
  - [x] receives ts_utc already normalized to a UTC ISO-8601 string or null.
  - [x] validates ts_utc as part of the complete post-redaction canonical record.
  - [x] does not invoke ClockInterface, normalize timestamps, or reconstruct canonical root fields.
  - [x] null ts_utc after clock failure remains a valid canonical field value.
  - [x] context is null or a bounded non-empty string-keyed map containing only approved json-like values.
  - [x] no second recursive normalizer, stable encoder, or newline-framing implementation
  - [x] final record-byte budget is enforced before stream I/O
  - [x] record output guarantees apply only to complete successful writes
  - [x] canonical root fields:
    - [x] ts_utc
    - [x] level
    - [x] message
    - [x] service_name
    - [x] app_target
    - [x] correlation_id
    - [x] uow_id
    - [x] uow_type
    - [x] context
  - [x] service_name is a required bounded non-empty safe string.
  - [x] app_target is a required canonical string enum: web, api, console, worker.
  - [x] both identity fields are provided by trusted logger construction, not arbitrary caller context.
  - [x] both identity fields participate in mandatory shared redaction and post-redaction schema validation.
  - [x] the canonical root remains deterministic for identical accepted record values.
  - [x] correlation/uow fields always exist, nullable outside UoW
  - [x] context is null or a non-empty sanitized string-keyed map.
  - [x] empty admitted context is represented as null, never as an empty JSON list.
  - [x] non-empty context is serialized as a JSON object.
  - [x] no object-casting or second shape-preserving JSON encoder is introduced.
  - [x] the nested empty-map/list limitation remains Foundation-owned.
  - [x] no caller context key may replace canonical root fields
  - [x] uses StableJsonEncoder::encodeStableMap()
  - [x] delegates all recursive sorting and final-LF framing to Foundation
  - [x] does not append another newline
  - [x] embedded newline/control data cannot create an additional physical JSON record
  - [x] does not emit floats, objects, resources, or unnormalized maps
  - [x] serialization failure does not expose rejected input
  - [x] no stream ownership or I/O

- [x] `packages/platform/logging/src/Formatter/LogfmtLineFormatter.php`
  - [x] final stateless formatter.
  - [x] accepts only the same fully sanitized canonical log record map as JsonLineFormatter.
  - [x] does not perform message admission, context enrichment, shared redaction, or severity filtering.
  - [x] consumes the already admitted and redacted canonical record.
  - [x] reuses LogRecordPolicy::isValidCanonicalRecord() for defensive structural validation.
  - [x] MUST NOT define a second canonical root-field allowlist or independently duplicate field-type rules.
  - [x] structural validation does not substitute the instance-bound sensitive-key admission and mandatory redaction performed before formatting.
  - [x] preserves all canonical fields, including service_name, app_target, correlation_id, uow_id, uow_type, and context.
  - [x] emits root fields in the fixed order: ts_utc, level, message, service_name, app_target, correlation_id, uow_id, uow_type, context.
  - [x] serializes each field as a key=value pair separated by exactly one ASCII space.
  - [x] serializes non-null strings as double-quoted UTF-8 values.
  - [x] escapes backslash as `\\`, double quote as `\"`, LF as `\n`, CR as `\r`, and TAB as `\t`.
  - [x] escapes every remaining ASCII control byte in the ranges 0x00..0x1F and 0x7F as `\u00XX` using uppercase hexadecimal digits.
  - [x] valid UTF-8 characters outside the prohibited control ranges remain unchanged.
  - [x] no raw CR, LF, or prohibited control byte may appear inside a serialized field value.
  - [x] serializes null as an unquoted `null` token.
  - [x] serializes non-empty context as a quoted compact JSON string produced by StableJsonEncoder::encodeStableMap().
  - [x] removes only the Foundation-generated final LF from embedded context JSON before quoting.
  - [x] serializes absent admitted context as unquoted context=null.
  - [x] never performs a second recursive JSON sort, normalization, or JSON encoding.
  - [x] appends exactly one final LF to the complete logfmt record.
  - [x] embedded newlines and control characters cannot create additional physical log records.
  - [x] identical accepted canonical records produce byte-identical output.
  - [x] never evaluates arbitrary Stringable objects or serializes unsupported PHP values.
  - [x] does not emit raw Throwable data, filesystem paths, credentials, or unadmitted producer values.
  - [x] serialization failures are isolated by StreamLogger and never trigger raw fallback emission.
  - [x] the final serialized byte budget is enforced before stream I/O.
  - [x] no stream ownership, I/O, runtime configuration reads, or mutable state.

- [x] `packages/platform/logging/config/logging.php`
  - [x] returns the `logging` subtree only
  - [x] exact defaults:
    - [x] driver = stream
    - [x] file.path = ''
    - [x] file.name = app
    - [x] level = info
    - [x] max_record_bytes = 65536
    - [x] service_name = auto
    - [x] format = jsonl
  - [x] no logging.stream key
  - [x] file is a nested map containing exactly path and name.
  - [x] file.path is an empty string by default because file output is inactive.
  - [x] file.name is a filename stem without an extension.
  - [x] no repeated `logging` root
  - [x] no redaction disable switch
  - [x] no environment reads, runtime service objects, closures, or mutable state
  - [x] driver is always a string.
  - [x] `stream` selects the fixed php://stderr backend.
  - [x] `file` selects the automatic daily UTC file backend.
  - [x] `null` selects the Foundation NoopLogger.
  - [x] PHP null is not a valid driver configuration value.
  - [x] redaction is not configurable and no redaction subtree is returned.
  - [x] security and redaction invariants are not configurable.
  - [x] no daily rotation, retention, file-permission, or file-size configuration keys.
  - [x] no automatic filesystem directory creation or external configuration discovery.

- [x] `packages/platform/logging/config/rules.php`
  - [x] schemaVersion = 1
  - [x] configRoot = logging
  - [x] additionalKeys = false
  - [x] required top-level keys: driver, file, level, max_record_bytes, service_name, format
  - [x] driver:
    - [x] required = true
    - [x] type = string
    - [x] allowedValues = ['stream', 'file', 'null']
    - [x] PHP null and boolean false are rejected
  - [x] file:
    - [x] required = true
    - [x] type = map
    - [x] additionalKeys = false
    - [x] exact nested keys: path, name
  - [x] file.path:
    - [x] required = true
    - [x] type = string
    - [x] default = '' through logging.php
    - [x] empty string is permitted by the declarative schema for inactive file mode.
    - [x] non-empty absolute local directory validation is enforced by LoggingServiceFactory only for driver='file'.
    - [x] PHP null, arrays, objects, and other non-string values are rejected.
  - [x] file.name:
    - [x] required = true
    - [x] type = reset-group-id
    - [x] default = app through logging.php
    - [x] reuse the existing ConfigValidator bounded ASCII grammar: `[a-z][a-z0-9_-]{0,63}`.
    - [x] dots, file extensions, directory separators, whitespace, control characters, and traversal are rejected.
  - [x] level:
    - [x] required = true
    - [x] type = string
    - [x] allowedValues = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']
  - [x] max_record_bytes:
    - [x] required = true
    - [x] type = int
    - [x] min = 1024
    - [x] max = 65536
    - [x] booleans, floats, numeric strings, zero, and out-of-range values are rejected
  - [x] service_name:
    - [x] required = true
    - [x] type = reset-group-id
    - [x] reuse the existing ConfigValidator bounded ASCII token grammar: `[a-z][a-z0-9_-]{0,63}`
    - [x] `auto` is the only automatic-resolution sentinel
    - [x] explicit service names may use lowercase ASCII letters, digits, underscores, and hyphens.
    - [x] dots, paths, URLs, whitespace, control characters, and strings exceeding 64 bytes are rejected during canonical configuration validation.
    - [x] LoggingServiceFactory independently enforces the identical grammar for direct invocation outside canonical configuration validation.
  - [x] format:
    - [x] required = true
    - [x] type = string
    - [x] allowedValues = ['jsonl', 'logfmt']
    - [x] default = jsonl through logging.php
    - [x] unsupported formats fail configuration validation
    - [x] format selection cannot disable shared redaction or change the canonical record schema.
    - [x] format determines the filename extension only when driver='file'.
  - [x] ConfigValidator schema uses only supported declarative rule types.
  - [x] nested file validation reuses the existing map, keys, additionalKeys, string, and reset-group-id rule semantics.
  - [x] cross-field file.path requirements are independently enforced by LoggingServiceFactory.
  - [x] logging.stream is an unknown key and is rejected.
  - [x] Unknown logging.redaction configuration is rejected through additionalKeys = false.
  - [x] rejects unknown keys and reserved @* keys
  - [x] rejects unsupported driver values and unknown configurable stream destinations through the strict declarative schema.
  - [x] LoggingServiceFactory rejects arbitrary stream wrappers, remote URLs, and unsafe file destinations when driver='file'.
  - [x] no executable validators or package-local config-validation engine
  - [x] no user-configurable redaction policy

- [x] `packages/platform/logging/README.md`
  - [x] package purpose and ownership
  - [x] canonical `## Observability`, `## Errors`, and `## Security / Redaction` sections
  - [x] Foundation noop baseline versus enabled stream backend
  - [x] module enablement and driver configuration
  - [x] three supported drivers: stream, file, and null.
  - [x] stream writes to fixed php://stderr without a public logging.stream key.
  - [x] file.path is an administrator-controlled absolute local directory path validated during logger construction.
  - [x] the directory must exist and be accessible at emission time, not necessarily at logger construction.
  - [x] temporarily missing or unavailable directories cause best-effort record omission without changing the selected backend.
  - [x] the same logger instance may resume emission after external restoration of the configured directory.
  - [x] file.name is a configurable basename stem, default app, without extension or date.
  - [x] file names use `{app_target}-{file.name}-{YYYY-MM-DD}.{format}`.
  - [x] filename app_target comes from AppTargetProviderInterface, not service_name.
  - [x] daily UTC file partitioning is automatic and cannot be disabled.
  - [x] file output uses append-only writes and non-blocking exclusive file locks.
  - [x] file directory ownership, permissions, and symlink restrictions are documented.
  - [x] inactive file configuration does not open or create files or directories.
  - [x] daily file creation does not implement retention, deletion, archival, or disk quotas.
  - [x] file mode reuses StreamLogger, the same redaction policy, and the same formatters.
  - [x] examples distinguish module absence from logging.driver = 'null'
  - [x] coretsia/framework installs platform.logging as a baseline dependency.
  - [x] all canonical presets enable platform.logging by default.
  - [x] logging.driver='stream' is the default for the enabled module.
  - [x] moduleOverrides.exclude may disable real logging per application target.
  - [x] logging.driver='null' may disable stream emission without excluding the module.
  - [x] Foundation NoopLogger remains available when real logging is disabled.
  - [x] configuration has no redaction.enabled flag or redaction subtree
  - [x] invalid configuration is a bootstrap/configuration failure
  - [x] missing or failed stream/file emission is best-effort/no-op-safe.
  - [x] existing logging consumers continue to depend only on LoggerInterface
  - [x] the allowed operational context fields and fixed size limits are documented
  - [x] limitations of automatic sensitive-value detection are explicitly documented
  - [x] partial writes, non-blocking file-lock contention, multi-process limitations, and filesystem race limitations are documented.
  - [x] canonical JSON record example with synthetic data
  - [x] correlation/UoW field lifecycle and null semantics
  - [x] exact severity filtering behavior
  - [x] PSR-3 invalid-level exception distinction
  - [x] internal write failure/no-op behavior
  - [x] required shared redaction port
  - [x] required shared SensitiveKeyClassifierInterface port for key-path sink admission.
  - [x] distinguish shared sensitive-key classification from mandatory whole-record redaction.
  - [x] document full-path and segment-level classification for context.key_paths.
  - [x] document complete key_paths omission when any item is classified as sensitive.
  - [x] explain that classifier null results never establish emission safety.
  - [x] document ordinary field omission versus fail-closed whole-record rejection on classifier failure.
  - [x] document pre-redaction and post-redaction classifier failure semantics.
  - [x] no duplicated sensitive-key aliases, concrete platform.redaction imports, custom classifier, or redaction configuration.
  - [x] producer-owned safe-by-construction message/context policy
  - [x] no custom redaction implementation or disable switch
  - [x] no per-record logging spans or metrics
  - [x] no logger-owned UoW state/reset service
  - [x] points to logging-policy.md and sensitive-data-redaction.md
  - [x] documents logging.max_record_bytes and the immutable hard upper bound.
  - [x] documents logging.service_name=auto and automatic web/api/console/worker identity.
  - [x] documents explicit bounded service-name overrides.
  - [x] documents logging.format=jsonl|logfmt with jsonl as the default.
  - [x] provides canonical synthetic output examples for both formats.
  - [x] documents identical admission, redaction, service identity, severity filtering, and byte-limit behavior across formats.
  - [x] documents deterministic logfmt field ordering, quoting, escaping, null handling, and nested context representation.
  - [x] documents that changing format affects serialized stderr records and file extensions, but never CLI stdout or command exit codes.
  - [x] documents unsupported-format rejection.
  - [x] distinguishes per-record byte limits from automatic daily file partitioning, external retention, and total storage quotas.

Docs:
- [x] `docs/adr/ADR-0037-platform-logging-psr3.md`
  - [x] adrVersion = 1
  - [x] records reuse of Foundation LoggerInterface noop binding
  - [x] records platform.logging as the default real logging backend of canonical presets
  - [x] records its physical installation through coretsia/framework
  - [x] records its selectable and excludable runtime-module semantics
  - [x] records Foundation NoopLogger as the fallback after exclusion or disabled driver
  - [x] records mandatory dependency on shared redaction port
  - [x] records the additional contracts-level SensitiveKeyClassifierInterface dependency.
  - [x] records reuse of the existing platform.redaction SensitiveKeyClassifier and its immutable alias vocabulary.
  - [x] records why logging-specific sink admission remains necessary even when classification returns null.
  - [x] records why the shared alias vocabulary is not moved into core/contracts or duplicated inside platform.logging.
  - [x] records the declarative classifier alias and preservation of the existing shared classifier instance.
  - [x] records canonical logging sink-field admissibility ownership
  - [x] records why generic redaction alone is not proof of log safety
  - [x] records Foundation stable JSON reuse
  - [x] records declarative source/compiled DI parity
  - [x] records no per-record observability instrumentation due to recursion risk
  - [x] records stream initialization and write degradation semantics
  - [x] records no new LoggerInterface, generic redactor, JSON encoder, correlation provider, or reset service
  - [x] records reuse of Kernel ModulePlan through the contracts-level AppTargetProviderInterface.
  - [x] records automatic canonical app-target service identity without a second app-target resolver.
  - [x] records the configurable per-record size limit under the immutable 65536-byte hard ceiling.
  - [x] records jsonl as the default format and logfmt as an explicitly supported alternative.
  - [x] records why both formatters consume one canonical admitted and redacted record schema.
  - [x] records Foundation StableJsonEncoder reuse for jsonl and embedded logfmt context serialization.
  - [x] records deterministic single-line framing and the shared serialized byte budget.
  - [x] records that formatter selection is runtime configuration, not a second logging backend or DI mechanism.
  - [x] records why stream uses fixed php://stderr without a public logging.stream key.
  - [x] records file as a second destination mode using the existing StreamLogger pipeline.
  - [x] records the exact stream, file, and null driver semantics.
  - [x] records file.path as an administrator-controlled, syntactically validated absolute local directory; directory existence and availability are checked at emission time, not during logger construction.
  - [x] records file.name as a bounded extension-free filename stem.
  - [x] records deterministic app-target/date/format filename construction.
  - [x] records why filename identity uses app_target rather than service_name.
  - [x] records automatic daily UTC file partitioning without a rotation-mode switch.
  - [x] records per-record open/append/lock/write/close behavior and multi-process limitations.
  - [x] records why file mode does not require FileLogger or another record admission pipeline.
  - [x] records external ownership of retention, deletion, archival, and total-file-size management.

- [x] `docs/ssot/logging-policy.md`
  - [x] ssotVersion = 1
  - [x] owner = platform/logging
  - [x] exact canonical record schema, nullable field semantics, and format-specific jsonl/logfmt representations
  - [x] canonical records include required service_name and app_target root fields.
  - [x] service_name='auto' derives from the existing canonical selected app target through AppTargetProviderInterface.
  - [x] explicit service_name overrides are bounded safe labels and never change canonical app_target.
  - [x] exact service_name grammar is `[a-z][a-z0-9_-]{0,63}`, including the automatic-resolution sentinel auto.
  - [x] canonical configuration validation reuses the existing Kernel reset-group-id rule type without a package-local validator.
  - [x] invalid service_name configuration fails before LoggerInterface construction.
  - [x] service identity is immutable for the lifetime of the constructed logger.
  - [x] caller context cannot override canonical service identity.
  - [x] service identity participates in mandatory redaction and post-redaction validation.
  - [x] logging.max_record_bytes controls the serialized per-record budget, not total log-file size.
  - [x] MAX_RECORD_BYTES = 65536 remains the immutable hard ceiling.
  - [x] the file backend automatically partitions records into daily UTC files.
  - [x] daily partitioning is fixed behavior, not a configurable rotation policy.
  - [x] retention, old-file deletion, archival, and total-file-size policies are external runtime responsibilities.
  - [x] logging.format supports exactly jsonl and logfmt, with jsonl as the default.
  - [x] both formats consume one canonical record schema after sink-field admission, enrichment, mandatory shared redaction, and post-redaction validation.
  - [x] jsonl uses Foundation StableJsonEncoder for deterministic serialization and final-LF framing.
  - [x] logfmt uses deterministic canonical field ordering, key=value pairs, quoted strings, strict escaping, and one final LF.
  - [x] logfmt nullable fields use unquoted null, while string values are always quoted.
  - [x] logfmt non-empty context uses compact JSON generated by Foundation StableJsonEncoder and embedded as an escaped quoted value.
  - [x] the Foundation-generated LF is removed only from JSON embedded within logfmt context.
  - [x] both formats preserve canonical service_name, app_target, correlation_id, uow_id, uow_type, and context field semantics.
  - [x] the effective max_record_bytes limit applies to the complete output of either format, including the final LF.
  - [x] formatter selection does not change record admission, classification, redaction, severity filtering, or runtime UoW context ownership.
  - [x] no alternative formatter may bypass mandatory shared redaction, introduce raw diagnostics, or add another recursive JSON encoder.
  - [x] canonical context is null or a non-empty sanitized string-keyed map.
  - [x] jsonl serializes null context as JSON null and non-empty context as a JSON object.
  - [x] logfmt serializes null context as unquoted context=null and non-empty context as one quoted, escaped compact JSON value produced through Foundation StableJsonEncoder.
  - [x] both formats preserve identical canonical context values without introducing another context schema.
  - [x] no nested empty-map encoder is introduced.
  - [x] trusted logger-generated metadata is distinguished from untrusted producer input.
  - [x] shared redaction failure causes complete record omission.
  - [x] message provenance is producer-owned and cannot be proven from a PSR-3 string alone.
  - [x] MAX_RECORD_BYTES = 65536, including final LF.
  - [x] existing app_target, preset, moduleIds, and worker diagnostic fields retain their owner-approved safety constraints.
  - [x] active StreamLogger invalid-level semantics are distinguished from existing Foundation NoopLogger behavior.
  - [x] canonical presets select platform.logging by default.
  - [x] Composer installation alone does not activate a runtime module.
  - [x] platform.logging activation is determined by the target-local ModulePlan.
  - [x] moduleOverrides.exclude and logging.driver='null' are distinct supported disable mechanisms.
  - [x] excluding platform.logging preserves the Foundation NoopLogger binding.
  - [x] real backend availability begins after successful module/container composition; pre-composition logging is not guaranteed to use an active backend.
  - [x] exact logging.driver string enum: stream, file, null.
  - [x] stream uses fixed php://stderr; file uses the validated daily local file; null uses Foundation NoopLogger.
  - [x] logging.file.path and logging.file.name semantics, defaults, and validation boundaries.
  - [x] exact generated filename grammar `{app_target}-{file.name}-{YYYY-MM-DD}.{format}`.
  - [x] canonical app_target controls the filename prefix independently of service_name.
  - [x] filename format extension follows the selected jsonl or logfmt serializer.
  - [x] daily UTC date resolution, record timestamp handling, and null-timestamp fallback.
  - [x] disabled module versus selected null driver semantics
  - [x] no redaction configuration surface
  - [x] required redaction-module dependency for active logging module
  - [x] declarative DI values MUST NOT contain php://stderr
  - [x] runtime ConfigRepositoryInterface provides the selected driver and validated file configuration.
  - [x] the internal php://stderr destination is not configurable.
  - [x] file paths, dates, and stream handles never enter declarative service-definition parameters.
  - [x] exact pre-admission → enrichment → UTC timestamp capture → canonical record assembly → shared redaction → post-admission → formatting → I/O order.
  - [x] complete canonical root fields are assembled before mandatory shared redaction.
  - [x] post-redaction schema validation covers every field subsequently submitted to the selected formatter.
  - [x] no canonical record mutation occurs between post-redaction validation and serialization.
  - [x] exact per-field context admissibility matrix
  - [x] documents SensitiveKeyClassifierInterface as the shared read-only sensitive-key classification dependency.
  - [x] distinguishes shared semantic key classification from logging-owned destination sink admission.
  - [x] defines structural validation and per-segment classification for canonical dotted key_paths.
  - [x] documents rejection of classified sensitive keys and continued rejection of unsafe unclassified keys.
  - [x] documents Kernel ownership of existing sensitive-key and SQL-like producer restrictions.
  - [x] documents that logging does not duplicate Kernel-specific regex tables.
  - [x] documents the distinction between producer validation, shared classification, and logging sink admission.
  - [x] documents that producer-rejected key paths never become admissible through a classifier null result.
  - [x] classifier null results never authorize arbitrary key-path emission.
  - [x] classifier failures are fail-closed and never expose raw key paths.
  - [x] exact key-path restrictions are enforced before and after shared redaction.
  - [x] both jsonl and logfmt use identical key-path admission semantics.
  - [x] the canonical sensitive-key alias vocabulary remains exclusively owned by platform.redaction.
  - [x] no local copies of the shared alias list or generic sensitive-key classification regex are permitted.
  - [x] sink-field admission is producer-neutral: package-specific behavior, event-specific branches, command-name interpretation, and runtime lifecycle reconstruction are forbidden.
  - [x] producers own the semantics and safety of generated log values; platform.logging owns only canonical record admission, severity filtering, enrichment, shared redaction, formatting, and emission.
  - [x] new packages SHOULD reuse existing canonical log fields and safe value categories; extending the sink schema requires explicit SSoT review rather than producer-specific exceptions.
  - [x] existing Kernel, Foundation, CLI, Errors, and Worker safe log fields are covered
  - [x] explicit per-field scalar bounds and list limits
  - [x] exact static event identifier grammar and fixed unsafe-message fallback
  - [x] producer-owned safe-by-construction obligation and limits of classifier-based detection
  - [x] redacted summaries do not automatically satisfy destination field-type requirements
  - [x] UTC timestamp formatting and null-on-clock-failure policy
  - [x] serialized record byte budget and oversized-record behavior
  - [x] one write attempt per serialized record for either active destination.
  - [x] file mode uses non-blocking flock() and emission-scoped append-binary handles.
  - [x] file destination validation and administrator-owned directory security.
  - [x] no claim of filesystem race immunity, cross-filesystem atomicity, or guaranteed complete framing after partial writes.
  - [x] invalid configuration versus unavailable destination failure distinction.
  - [x] factory-time configuration syntax validation and emission-time filesystem availability checks have separate owners.
  - [x] missing file directories do not constitute malformed configuration when file.path syntax is otherwise valid.
  - [x] file-mode availability failures do not replace StreamLogger with Foundation NoopLogger.
  - [x] no directory recovery cache, failure registry, retry queue, or logging-owned reset capability is introduced.
  - [x] no file retention, automatic deletion, archival, or built-in disk quota management.
  - [x] source/compiled DI parity without URI-valued declarative parameters
  - [x] exact permitted levels and filtering order
  - [x] safe event-message contract
  - [x] safe operational context allowlist and per-field type/bounds
  - [x] owner-approved safe relative artifact summaries
  - [x] forbidden metadata and value categories
  - [x] redaction call order and fail-closed behavior
  - [x] fixed logging.record redaction scope and Placeholder mode
  - [x] no generic classifier duplication
  - [x] validated correlation and UoW enrichment semantics
  - [x] no arbitrary request-context export
  - [x] timestamp shape and clock-failure fallback
  - [x] jsonl delegates stable JSON serialization and final-LF framing to Foundation StableJsonEncoder.
  - [x] logfmt reuses Foundation StableJsonEncoder only for nested context JSON and owns the final LF of the complete logfmt record.
  - [x] both formats emit exactly one physical LF-terminated record after a successful complete write.
  - [x] null driver, stream initialization failure, file destination failure, and file-lock contention behavior.
  - [x] PSR-3 invalid-level exception contract.
  - [x] process-lifetime STDERR handle versus emission-scoped daily file handles.
  - [x] no logging-owned UoW state, file-date cache, or mutable failure registry.
  - [x] no per-record logging spans/metrics
  - [x] canonical synthetic examples and explicit forbidden counterexamples
  - [x] links to context, observability, redaction, configuration, and packaging SSoTs

#### Modifies

- [x] `packages/platform/redaction/src/Redaction/SensitiveKeyClassifier.php`
  - [x] implement Coretsia\Contracts\Security\SensitiveKeyClassifierInterface.
  - [x] preserve the exact public classify(string $key): ?RedactionKind method.
  - [x] preserve the exact immutable private 98-alias table and RedactionKind mappings.
  - [x] preserve ASCII-only case folding and the four canonical separator-removal rules.
  - [x] preserve existing ambiguous and unclassified key behavior.
  - [x] retain the concrete classifier as a package-internal implementation.
  - [x] no additional classifier, alias registry, configuration, or mutable state.
  - [x] no changes to DefaultSensitiveDataRedactor classification behavior.

- [x] `packages/platform/redaction/src/Provider/RedactionServiceProvider.php`
  - [x] add a declarative SensitiveKeyClassifierInterface alias to SensitiveKeyClassifier::class.
  - [x] reuse the existing shared SensitiveKeyClassifier classService definition.
  - [x] preserve the existing SensitiveDataRedactorInterface alias to DefaultSensitiveDataRedactor::class.
  - [x] append the new classifier alias after the existing redactor alias in the canonical definition stream.
  - [x] preserve the existing four classService operations and their ordering.
  - [x] preserve the three existing DefaultSensitiveDataRedactor constructor references and their order.
  - [x] both classifier service IDs MUST resolve to the identical shared instance.
  - [x] DefaultSensitiveDataRedactor MUST continue to use that identical instance.
  - [x] no additional classifier construction, closure, classMethodFactory, runtime lookup, configuration, or tag.
  - [x] preserve source and compiled container-definition parity.
  - [x] provider application remains explicit and module-selection owned.

- [x] `packages/platform/redaction/tests/Unit/SensitiveKeyClassifierTest.php`
  - [x] verify SensitiveKeyClassifier implements SensitiveKeyClassifierInterface.
  - [x] preserve all 98 canonical alias and RedactionKind mapping assertions.
  - [x] preserve separator normalization, ASCII case folding, and locale-independence tests.
  - [x] preserve ambiguous and unclassified key cases, including null results.

- [x] `packages/platform/redaction/tests/Contract/RedactionProviderDefinitionsContainNoClosuresContractTest.php`
  - [x] update the exact expected descriptor stream with the appended classifier port alias.
  - [x] verify the alias targets SensitiveKeyClassifier::class.
  - [x] preserve existing classService and redactor alias ordering.
  - [x] preserve typed reference, deterministic descriptor, and no-closure assertions.

- [x] `packages/platform/redaction/tests/Contract/RedactionProviderSourceDefinitionsParityTest.php`
  - [x] add SensitiveKeyClassifierInterface::class to the exact expected service IDs.
  - [x] verify source and declarative definitions produce identical classifier bindings.
  - [x] verify both classifier IDs resolve to the same shared instance.
  - [x] verify the redactor retains that identical injected classifier instance.
  - [x] preserve existing redactor service identity and no-tag assertions.

- [x] `packages/platform/redaction/tests/Integration/RedactionProviderRequiresExplicitApplicationTest.php`
  - [x] verify package/autoload presence does not register the classifier port.
  - [x] update the exact expected alias descriptors to include the new classifier alias.
  - [x] update the exact expected service IDs.
  - [x] verify both aliases are registered only after explicit provider application.
  - [x] preserve the absence of automatic module activation, configuration, and tags.

- [x] `packages/platform/redaction/tests/Integration/RedactionServiceProviderWiresDefaultRedactorTest.php`
  - [x] include SensitiveKeyClassifierInterface::class in expected bindings.
  - [x] verify the interface resolves to the existing shared SensitiveKeyClassifier instance.
  - [x] verify DefaultSensitiveDataRedactor retains the identical classifier dependency.
  - [x] preserve existing redactor constructor shape and shared identity assertions.
  - [x] preserve redaction behavior and configuration-free provider semantics.

- [x] `packages/core/kernel/src/Module/ModulePlan.php`
  - [x] implement Coretsia\Contracts\Runtime\AppTargetProviderInterface.
  - [x] reuse the existing app(): string method without changing its signature or behavior.
  - [x] preserve existing canonical app-target validation.
  - [x] do not introduce another app-target field, resolver, or state snapshot.

- [x] `packages/core/kernel/src/Provider/KernelServiceProvider.php`
  - [x] bind AppTargetProviderInterface through the existing declarative classMethodFactory API, not through alias().
  - [x] factoryClass = KernelServiceFactory::class
  - [x] method = appTargetProvider
  - [x] KernelServiceFactory::appTargetProvider() MUST exist as a public static method before the classMethodFactory definition is constructed.
  - [x] the implementation MUST preserve the same ModulePlan runtime-seed identity in source and compiled containers.
  - [x] factory arguments contain exactly ContainerValueReference::service(ModulePlan::class).
  - [x] declare ModulePlan::class as a required runtime seed service reference.
  - [x] reuse the existing canonical ModulePlan runtime seed without defining, replacing, or duplicating it.
  - [x] preserve source/compiled container definition parity.
  - [x] do not resolve ModulePlan during provider registration or definition construction.
  - [x] do not add logging-specific configuration, logging implementation references, or module-selection logic.

- [x] `packages/core/kernel/src/Provider/KernelServiceFactory.php`
  - [x] add one public static appTargetProvider() factory method.
  - [x] exact signature: appTargetProvider(ModulePlan $plan): AppTargetProviderInterface.
  - [x] return the injected ModulePlan instance unchanged.
  - [x] reuse ModulePlan's existing implementation of AppTargetProviderInterface.
  - [x] do not construct another app-target provider, resolver, or value object.
  - [x] do not copy, cache, mutate, or independently validate the selected app target.
  - [x] do not access runtime configuration, filesystem paths, environment variables, or UoW state.
  - [x] preserve existing KernelServiceFactory responsibilities and stateless behavior.

- [x] `packages/core/kernel/tests/Integration/KernelProviderSourceDefinitionsParityTest.php`
  - [x] add AppTargetProviderInterface::class to the exact expected Kernel runtime service IDs in canonical descriptor order.
  - [x] add ModulePlan::class to the exact expected requiredServiceIds set.
  - [x] provide one valid canonical ModulePlan instance as an external source-container seed before provider registration.
  - [x] exclude the test-owned ModulePlan seed from the provider-owned binding ID comparison.
  - [x] verify AppTargetProviderInterface resolves to the identical seeded ModulePlan instance.
  - [x] verify the AppTargetProviderInterface service definition remains shared.
  - [x] preserve the existing KernelRuntimeInterface alias assertions.
  - [x] preserve source/compiled definition parity and deterministic service ordering.
  - [x] do not introduce another app-target resolver, ModulePlan definition, or runtime state owner.

- [x] `packages/core/kernel/tests/Contract/KernelCompileHostServicesAreNotRuntimeDefinitionsContractTest.php`
  - [x] add AppTargetProviderInterface::class to the exact expected Kernel runtime definition binding IDs.
  - [x] add ModulePlan::class to the exact expected requiredServiceIds set.
  - [x] verify AppTargetProviderInterface is a graph-defined classMethodFactory service.
  - [x] verify ModulePlan::class remains an external runtime seed and never becomes a runtime graph binding.
  - [x] preserve the existing compile-host-only service exclusion assertions.
  - [x] preserve the prohibition on runtime definitions for compile-host services.
  - [x] do not change ContainerGraphCompletenessValidator or the canonical runtime seed allowlist.

- [x] `packages/framework/composer.json`
  - [x] add `coretsia/platform-logging: ^0.10.0` to `require`
  - [x] update the metapackage description to include Logging in the baseline runtime installation dependencies
  - [x] preserve existing coretsia/core-kernel and coretsia/platform-cli requirements
  - [x] Composer dependency ensures physical installation of the logging package
  - [x] platform-logging installs platform-redaction through its own Composer dependencies
  - [x] metapackage remains dependency-only
  - [x] no runtime module activation logic is introduced into Composer metadata

- [x] `packages/core/kernel/resources/modes/micro.php`
  - [x] add `platform.logging` to `modules`
  - [x] do not add platform.logging to `required`
  - [x] preserve core.foundation and core.kernel as required
  - [x] preserve platform.cli as a selectable module
  - [x] default micro selection includes real logging
  - [x] target-local exclude may disable platform.logging
  - [x] preserve schemaVersion, featureBundles, and metadata

- [x] `packages/core/kernel/resources/modes/express.php`
  - [x] add `platform.logging` to `modules`
  - [x] do not add platform.logging to `required`
  - [x] preserve all existing module roots
  - [x] default express selection includes real logging
  - [x] target-local exclude may disable platform.logging
  - [x] preserve schemaVersion, featureBundles, and metadata

- [x] `packages/core/kernel/resources/modes/hybrid.php`
  - [x] add `platform.logging` to `modules`
  - [x] do not add platform.logging to `required`
  - [x] preserve platform.cli and platform.worker
  - [x] default hybrid selection includes real logging
  - [x] target-local exclude may disable platform.logging independently of platform.worker
  - [x] preserve schemaVersion, featureBundles, and metadata

- [x] `packages/core/kernel/resources/modes/enterprise.php`
  - [x] add `platform.logging` to `modules`
  - [x] do not add platform.logging to `required`
  - [x] preserve platform.cli and platform.worker
  - [x] default enterprise selection includes real logging
  - [x] target-local exclude may disable platform.logging independently of platform.worker
  - [x] preserve schemaVersion, featureBundles, and metadata

- [x] `packages/platform/cli/src/Runner/CommandRunner.php`
  - [x] replace the dynamic `LoggerInterface::log()` severity selection with explicit `LoggerInterface::info()` for successful commands and `LoggerInterface::error()` for failed commands.
  - [x] construct the existing safe log context once and reuse it in both branches.
  - [x] preserve the static `cli.command` event identifier, operation, outcome, exit_code, and existing safe context identifiers.
  - [x] preserve exactly one logging attempt per command completion and the existing best-effort Throwable isolation.
  - [x] preserve CommandRunner-owned lifecycle observability, Kernel UoW ordering, exit-code semantics, primary exception propagation, and CLI output behavior.
  - [x] do not introduce any concrete platform/logging dependency or another logging abstraction.

- [x] `packages/platform/cli/tests/Unit/CommandRunnerObservabilityTest.php`
  - [x] preserve existing assertions that successful commands log at `info` and non-zero exit codes log at `error`.
  - [x] additionally assert that a command throwing an exception logs at `error`.
  - [x] verify exactly one log attempt per completed command lifecycle.
  - [x] preserve existing safe context, correlation identifier, telemetry ordering, and exception isolation assertions.
  - [x] preserve logging in CommandRunner's existing finally block after the completed runUnitOfWork() boundary.
  - [x] captured safe identifiers may remain only as validated non-authoritative log context fields.
  - [x] do not restore the completed UoW or repopulate canonical root correlation_id, uow_id, or uow_type from captured command identifiers.
  - [x] canonical root identifiers follow the current ContextAccessorInterface and CorrelationIdProviderInterface state at emission time.
  - [x] after the UoW has been reset, canonical root identifiers may be null even when bounded caller context identifiers are present.
  - [x] preserve exactly one completion logging attempt and existing primary exception precedence.

- [x] `packages/core/kernel/tests/Support/AppBuilder.php`
  - [x] add platform.logging to canonical micro/express test fixture manifests
  - [x] add logging module metadata, provider, Composer identity, and default config path
  - [x] declare logging module requires core.foundation, core.kernel, and platform.redaction
  - [x] reuse the canonical Kernel AppTargetProviderInterface binding through ModulePlan
  - [x] add logging package root to test ComposerPackageInstallPathResolver
  - [x] preserve existing Foundation, Kernel, CLI, Errors, and Redaction fixture wiring
  - [x] reuse the actual LoggingServiceProvider; no test-only logging DI implementation
  - [x] preserve source/compiled artifact boot coverage

- [x] `packages/core/kernel/tests/Integration/ModePresetSchemaValidatorEnforcesMicroAndExpressRulesTest.php`
  - [x] micro and express modules match exactly [platform.cli, platform.logging].
  - [x] hybrid and enterprise modules match exactly [platform.cli, platform.logging, platform.worker].
  - [x] existing required arrays remain exactly [core.foundation, core.kernel].
  - [x] preserve micro/express equality and hybrid/enterprise worker distinctions.
  - [x] verify platform.logging is never required
  - [x] preserve existing micro/express equality assertions
  - [x] preserve hybrid/enterprise worker distinctions
  - [x] verify canonical schema and featureBundles remain unchanged

- [x] `packages/core/kernel/tests/Unit/ProjectPackagePlannerTest.php`
  - [x] update canonical micro and express installation-planning expectations to include platform.logging.
  - [x] preserve the existing per-target preset selection and application discovery rules.
  - [x] the default micro and express resolved module closure includes core.foundation, core.kernel, platform.cli, platform.errors, platform.logging, and platform.redaction.
  - [x] hybrid and enterprise additionally include platform.worker.
  - [x] add platform.logging to exact byte-order sorted enabledModuleIdsByTarget() and unionModuleIds() expectations for canonical presets.
  - [x] add coretsia/platform-logging with the existing canonical release-line constraint to desiredRootRequirements() expectations.
  - [x] add the exact platform.logging expectedModuleMetadataById() entry:
    - [x] composerName = coretsia/platform-logging
    - [x] requires = [core.foundation, core.kernel, platform.redaction]
    - [x] conflicts = []
  - [x] preserve existing core.foundation, core.kernel, platform.cli, platform.errors, platform.redaction, and platform.worker metadata expectations.
  - [x] preserve exact deterministic package ordering and approved planning payload encoding.
  - [x] verify target-local exclusion of platform.logging removes it only from the affected target's enabled module closure.
  - [x] verify another target may retain platform.logging in the union installation plan.
  - [x] verify excluding platform.logging does not remove platform.redaction when another enabled module requires it.
  - [x] preserve custom application-only preset behavior without implicitly adding platform.logging.
  - [x] preserve the Kernel bootstrap floor, required dependency closure, and fail-closed unknown-module semantics.
  - [x] preserve the absence of implicit Composer mutations, application-target discovery, or runtime module auto-enablement.
  - [x] no new DependencySync planner, package registry, configuration source, span, metric, context state, or reset service.

- [x] `packages/core/kernel/tests/Integration/BootMicroPresetTest.php`
  - [x] expect platform.logging in default enabled ModulePlan
  - [x] verify compiled container resolves the real LoggerInterface backend
  - [x] update canonical topological-order expectations
  - [x] expected enabled modules are byte-order sorted and include platform.logging.
  - [x] platform.redaction precedes platform.logging in topological order.
  - [x] the real logging backend is resolved through the compiled container.
  - [x] no Kernel-owned alternate logger wiring is introduced.
  - [x] preserve existing artifact and Kernel boot assertions
  - [x] verify AppTargetProviderInterface resolves to the already selected canonical ModulePlan app.
  - [x] verify AppTargetProviderInterface resolves to the identical ModulePlan runtime seed instance.
  - [x] verify the AppTargetProviderInterface binding is compiled as a class-method factory, not an alias.
  - [x] verify both source and compiled containers preserve the selected app target without additional state.
  - [x] verify logging.service_name='auto' adds canonical service_name and app_target to emitted records.
  - [x] verify source and compiled container paths resolve identical service identity semantics.

- [x] `packages/core/kernel/tests/Integration/BootExpressPresetTest.php`
  - [x] expect platform.logging in default enabled ModulePlan
  - [x] verify compiled container resolves the real LoggerInterface backend
  - [x] update canonical topological-order expectations
  - [x] expected enabled modules are byte-order sorted and include platform.logging.
  - [x] platform.redaction precedes platform.logging in topological order.
  - [x] the real logging backend is resolved through the compiled container.
  - [x] no Kernel-owned alternate logger wiring is introduced.
  - [x] preserve existing artifact and Kernel boot assertions
  - [x] verify AppTargetProviderInterface resolves to the already selected canonical ModulePlan app.
  - [x] verify AppTargetProviderInterface resolves to the identical ModulePlan runtime seed instance.
  - [x] verify the AppTargetProviderInterface binding is compiled as a class-method factory, not an alias.
  - [x] verify both source and compiled containers preserve the selected app target without additional state.
  - [x] verify logging.service_name='auto' adds canonical service_name and app_target to emitted records.
  - [x] verify source and compiled container paths resolve identical service identity semantics.

- [x] `packages/core/kernel/tests/Integration/ModuleSelectionFactoryTest.php`
  - [x] verify platform.logging is selected by default in each canonical preset.
  - [x] verify target-local exclude removes platform.logging from selected roots.
  - [x] verify excluding logging does not remove core.foundation, core.kernel, or platform.cli.
  - [x] verify exclusion does not implicitly remove transitive platform.redaction required by another enabled module.
  - [x] reuse the canonical ModuleSelectionFactory without introducing logging-specific selection logic.

- [x] `packages/core/kernel/tests/Integration/ModuleGraphResolverRejectsMissingSelectedRootTest.php`
  - [x] verify a selected but physically missing platform.logging module fails closed.
  - [x] verify the existing missing-selected-root exception semantics remain authoritative.
  - [x] verify no implicit NoopLogger fallback replaces a missing selected module during planning.

- [x] `packages/core/kernel/tests/Integration/RuntimeContainerGraphCompilerAcceptsRuntimeSeedReferencesTest.php`
  - [x] verify a declarative classMethodFactory service may consume ModulePlan::class as a canonical external runtime seed reference.
  - [x] verify AppTargetProviderInterface is exposed as a graph-defined service rather than an alias targeting ModulePlan::class.
  - [x] verify the resulting graph passes the existing ContainerGraphCompletenessValidator.
  - [x] preserve the existing runtime seed allowlist and unknown-seed rejection behavior.
  - [x] do not modify the validator or introduce a new runtime seed.

- [x] `packages/platform/cli/tests/Integration/CliDefaultMicroCreateProjectBootTest.php`
  - [x] assert coretsia/skeleton requires coretsia/framework.
  - [x] assert coretsia/framework directly requires coretsia/platform-logging.
  - [x] assert coretsia/platform-logging requires coretsia/platform-redaction.
  - [x] assert kernel.boot.default_preset remains micro.
  - [x] assert canonical micro selects platform.logging through modules, not required.
  - [x] assert default Console ModulePlan includes platform.logging.
  - [x] assert normal Console source-host wiring resolves the real logging backend.
  - [x] preserve existing artifact-free normal CLI host boot behavior.
  - [x] verify successful CLI output remains valid coretsia.cli-output@1 JSON.
  - [x] verify permitted diagnostic logs are JSON-lines on stderr.
  - [x] do not require stderr to be empty when an admitted info-level log event is emitted.
  - [x] verify log records contain no PHP warnings, raw paths, credentials, or stack traces.
  - [x] preserve ultra-early doctor behavior independently of normal runtime logging.
  - [x] verify target-local exclusion selects Foundation NoopLogger without removing platform.logging from Composer.
  - [x] verify the default logging.format=jsonl preserves JSON-lines stderr diagnostics.
  - [x] verify a target-local logging.format=logfmt configuration produces parseable logfmt stderr diagnostics without changing canonical CLI stdout or exit codes.
  - [x] verify format selection does not affect command lifecycle logging severity or safe context semantics.

- [x] `packages/platform/cli/tests/Integration/CoretsiaBinaryListCommandTest.php`
  - [x] preserve identical command exit codes between Composer proxy and Skeleton wrapper.
  - [x] preserve byte-identical canonical command stdout for identical input.
  - [x] do not require two independent stderr log streams to be byte-identical when records contain independently captured runtime timestamps or correlation identifiers.
  - [x] parse and validate every emitted stderr JSON-lines record.
  - [x] verify the same safe event types and canonical field schema for both invocation paths.
  - [x] exclude runtime timestamps and UoW-specific identifiers only from cross-invocation equivalence comparisons.
  - [x] retain exact deterministic JSON serialization assertions for identical fixed input records.
  - [x] preserve failure-path safety assertions.
  - [x] preserve CLI output and logging pipeline separation.

- [x] `packages/platform/cli/tests/Integration/NonInteractiveAdaptiveOutputUsesTableFormatTest.php`
  - [x] preserve exact stdout and exit-code assertions for identical commands.
  - [x] do not compare entire process results byte-for-byte across independent executions when stderr contains runtime-generated log records.
  - [x] validate diagnostic stderr records against the canonical logging JSON-lines schema.
  - [x] preserve tests using explicitly injected logging test doubles.

- [x] `packages/platform/cli/tests/Integration/CoretsiaRepositoryWrapperIsCwdIndependentTest.php`
  - [x] preserve CWD-independent application-root, autoload, exit-code, and stdout semantics.
  - [x] replace byte-identical stderr comparisons across independent invocations with canonical log-record validation.
  - [x] ignore independently generated timestamps and UoW identifiers only when comparing otherwise equivalent log records.
  - [x] preserve protection against wrong-CWD autoload and configuration access.

- [x] `packages/platform/cli/tests/Integration/CoretsiaBinaryHelpCommandTest.php`
  - [x] preserve existing help-command stdout, metadata, formatting, and exit-code assertions.
  - [x] replace empty-stderr expectations for successfully dispatched commands with canonical JSON-lines logging validation when the real logging backend is enabled.
  - [x] preserve deterministic help output independently of runtime-generated logging timestamps and UoW identifiers.
  - [x] do not suppress normal CLI logging to satisfy legacy stderr assertions.

- [x] `packages/platform/cli/tests/Integration/WorkflowCatalogIsResolvedOnlyForWorkflowCommandsTest.php`
  - [x] preserve lazy workflow catalog resolution and existing command failure semantics.
  - [x] allow canonical JSON-lines diagnostic records for successfully dispatched commands when platform.logging is enabled.
  - [x] preserve empty-stderr expectations where execution fails before any logging-producing command lifecycle.
  - [x] preserve stdout error descriptors and sensitive-data non-disclosure assertions.

- [x] `docs/ssot/INDEX.md`
  - [x] register logging-policy.md exactly once
  - [x] use canonical SSoT entry format
  - [x] owner = platform/logging
  - [x] ssotVersion = 1
  - [x] preserve strcmp relative-path ordering
  - [x] no forward references

- [x] `docs/adr/INDEX.md`
  - [x] register ADR-0037-platform-logging-psr3.md exactly once
  - [x] owner = platform/logging
  - [x] adrVersion = 1
  - [x] preserve deterministic relative-path ordering
  - [x] no ADR-XXXX placeholder remains

- [x] `docs/architecture/DEPENDENCIES.md`
  - [x] add canonical platform/logging matrix row
  - [x] depends_on = core/contracts, core/foundation
  - [x] preserve sorted package-id rows
  - [x] document platform/logging → platform/redaction as runtime-only Composer/module edge
  - [x] document platform/logging → core/kernel as a runtime-only Composer/module edge for AppTargetProviderInterface availability.
  - [x] preserve the prohibition on direct platform/logging source imports from core/kernel.
  - [x] no direct source dependency permission on platform/redaction
  - [x] no changes to existing package dependency edges

- [x] `docs/ssot/application-dependency-sync.md`
  - [x] add platform.logging to the canonical materialized-module table
  - [x] ModuleId = platform.logging
  - [x] composerName = coretsia/platform-logging
  - [x] requires = core.foundation, core.kernel, platform.redaction
  - [x] update materialized installation catalog coverage expectations
  - [x] package installation does not imply module enablement
  - [x] platform.logging is selected by default in micro, express, hybrid, and enterprise.
  - [x] coretsia/framework installs coretsia/platform-logging as part of the Composer bootstrap floor.
  - [x] the documented bootstrap dependency graph includes coretsia/platform-logging and its platform-redaction dependency.
  - [x] canonical materialized-module table includes platform.logging.
  - [x] default ModulePlan examples include platform.logging.
  - [x] moduleOverrides.exclude removes platform.logging only from the selected target's runtime ModulePlan.
  - [x] target-local exclusion does not remove the package from the shared Composer vendor graph.
  - [x] installed-state verification respects target-local logging exclusions.
  - [x] no implicit Composer update or dependency synchronization occurs during boot.
  - [x] Foundation NoopLogger remains the baseline for all presets without platform.logging
  - [x] explicitly selected platform.logging contributes its own required module closure
  - [x] absence or exclusion of required platform.redaction fails during canonical module planning
  - [x] no changes to existing target-local preset selection semantics
  - [x] no new dependency-sync engine, package resolver, or Composer graph implementation

- [x] `docs/ssot/modes.md`
  - [x] update canonical preset runtime module policy table for all four presets
  - [x] add platform.logging to each preset's selectable modules
  - [x] preserve core.foundation and core.kernel as non-excludable required modules
  - [x] preserve existing platform.cli and platform.worker selection rules
  - [x] update the exact canonical ModePreset required/modules matrix for micro, express, hybrid, and enterprise.
  - [x] micro and express modules = [platform.cli, platform.logging].
  - [x] hybrid and enterprise modules = [platform.cli, platform.logging, platform.worker].
  - [x] every canonical preset retains required = [core.foundation, core.kernel].
  - [x] micro and express effective enabled ModulePlan contains core.foundation, core.kernel, platform.cli, platform.errors, platform.logging, platform.redaction.
  - [x] hybrid and enterprise additionally contain platform.worker.
  - [x] update all existing multi-target examples and effective module-union examples.
  - [x] update existing bootstrap Composer baseline documentation to include platform.logging.
  - [x] document missing selected logging module failure through existing graph resolver.
  - [x] document successful exclusion of platform.logging without excluding platform.redaction required by other modules.
  - [x] preserve the distinction between product-level Required capability and ModePreset.required non-excludable modules.
  - [x] document platform.logging target-local exclusion
  - [x] distinguish Composer installation from runtime module selection
  - [x] document Foundation NoopLogger fallback after exclusion
  - [x] preserve schemaVersion = 1
  - [x] no new ModePreset keys, ModuleSelection APIs, or ModuleGraphResolver behavior

- [x] `packages/framework/README.md`
  - [x] update the direct baseline Composer dependency list:
    - [x] coretsia/core-kernel
    - [x] coretsia/platform-cli
    - [x] coretsia/platform-logging
  - [x] update the baseline Composer dependency graph.
  - [x] document platform-redaction as a transitive dependency required by platform-cli and platform-logging.
  - [x] document platform.logging as part of the default public framework distribution.
  - [x] preserve coretsia/framework as a dependency-only metapackage.
  - [x] framework MUST NOT instantiate logging services or own runtime logging implementation.
  - [x] distinguish physical Composer installation from Kernel runtime module activation.
  - [x] document default platform.logging selection in all canonical presets.
  - [x] document target-local exclusion through moduleOverrides.exclude.
  - [x] document logging.driver='null' as a separate backend-disable mechanism.
  - [x] document stream as the default fixed STDERR backend and file as the optional daily local-file backend.
  - [x] explain that file output is configured by platform/logging, not framework.
  - [x] distinguish disabled logging from missing selected runtime dependencies.
  - [x] preserve existing DependencySync ownership and explicit-apply semantics.
  - [x] update Observability documentation without transferring logging ownership from platform/logging to framework.
  - [x] preserve Errors and Security / Redaction distribution boundaries.

- [x] `packages/applications/skeleton/README.md`
  - [x] preserve coretsia/framework as the only project-owned baseline Coretsia Composer root requirement.
  - [x] update the default create-project dependency graph to include coretsia/platform-logging.
  - [x] document platform-redaction as the required transitive dependency.
  - [x] preserve kernel.boot.default_preset='micro' as the default preset source.
  - [x] preserve config/app.php returning [] as a valid default configuration.
  - [x] default micro ModulePlan includes:
    - [x] core.foundation
    - [x] core.kernel
    - [x] platform.cli
    - [x] platform.errors
    - [x] platform.logging
    - [x] platform.redaction
  - [x] distinguish intended future micro capabilities from currently materialized runtime modules.
  - [x] document StreamLogger as the default runtime LoggerInterface implementation after successful module composition.
  - [x] document moduleOverrides.exclude for target-local logging deactivation.
  - [x] document logging.driver='null' for backend deactivation without excluding the runtime module.
  - [x] document logging.driver='file' and its required administrator-controlled file.path.
  - [x] document automatic UTC-daily filenames and their app-target prefixes.
  - [x] explain that daily file partitioning does not manage retention, deletion, or disk quotas.
  - [x] document that a missing selected logging module is a bootstrap failure, not a NoopLogger fallback.
  - [x] document that selecting another preset does not automatically install its additional Composer dependencies.
  - [x] preserve explicit DependencySync plan/review/apply as the package installation workflow.
  - [x] preserve existing project-root CLI wrapper and bootstrap ownership.
  - [x] document that runtime logs may be written to stderr independently of CLI command output.
  - [x] preserve the existing Observability, Errors, and Security / Redaction boundaries.

- [x] `packages/platform/cli/README.md`
  - [x] remove the coretsia/framework baseline Composer dependency description.
  - [x] CLI documentation MUST NOT enumerate or maintain coretsia/framework distribution dependencies.
  - [x] preserve CLI-owned direct Composer and PHP source dependency declarations.
  - [x] preserve LoggerInterface as the only logging dependency consumed by CLI.
  - [x] preserve canonical Kernel ModulePlan and DependencySync integration semantics.
  - [x] preserve CLI output, error handling, observability, and redaction ownership.
  - [x] no direct Composer or PHP source dependency on platform/logging is introduced.
  - [x] no changes to CliServiceProvider or CLI output pipeline are required solely for logging backend selection.

Shared redaction public contract documentation:

- [x] `docs/ssot/sensitive-data-redaction.md`
  - [x] register SensitiveKeyClassifierInterface as a second contracts-level cross-package security port.
  - [x] preserve SensitiveDataRedactorInterface as the mandatory redaction port.
  - [x] document exact classify(string $key): ?RedactionKind semantics.
  - [x] preserve the exact 98-alias vocabulary, RedactionKind mappings, and canonicalization algorithm.
  - [x] preserve platform.redaction ownership of the immutable classification policy.
  - [x] keep the concrete SensitiveKeyClassifier package-internal.
  - [x] update the exclusive one-port statements in Scope, Authority boundary, and Consumer safety.
  - [x] document the new declarative classifier interface alias to the existing shared implementation.
  - [x] update the exact provider definition operation order.
  - [x] document classifier alias identity with the instance used by DefaultSensitiveDataRedactor.
  - [x] clarify that a null classification result never authorizes consumer sink emission.
  - [x] document that composite key-path interpretation is consumer-owned; the classifier receives individual structural keys and does not parse paths.
  - [x] preserve lower-layer Core independence from platform.redaction.
  - [x] update canonical verification requirements for interface conformance, alias identity, and source/compiled definition parity.
  - [x] no new classifier implementation, public alias registry, configuration, or redaction engine.

- [x] `packages/platform/redaction/README.md`
  - [x] list SensitiveDataRedactorInterface and SensitiveKeyClassifierInterface as the two supported contracts-level ports.
  - [x] distinguish the redaction operation from read-only structural-key classification.
  - [x] document the unchanged concrete SensitiveKeyClassifier as an internal implementation.
  - [x] document both declarative provider aliases and their shared concrete service identities.
  - [x] preserve the requirement for explicit platform.redaction module selection.
  - [x] clarify that classifier non-match does not establish sink safety.
  - [x] preserve consumer ownership of destination-specific admission.
  - [x] no additional runtime configuration, classifier registry, or module.

- [x] `packages/core/contracts/README.md`
  - [x] add SensitiveKeyClassifierInterface to the canonical Security contracts inventory.
  - [x] document the exact classify(string $key): ?RedactionKind method.
  - [x] distinguish the read-only classification port from SensitiveDataRedactorInterface.
  - [x] preserve the two existing SensitiveDataRedactorInterface entrypoints unchanged.
  - [x] replace the exclusive SensitiveDataRedactorInterface-only consumer statement with the two supported contracts-level security ports.
  - [x] preserve platform.redaction ownership of the canonical 98-alias vocabulary and classification algorithm.
  - [x] document that null classification means unclassified, not safe for emission.
  - [x] preserve destination-owned schema, path, semantic-key, omission, cardinality, and resource-limit requirements.
  - [x] forbid direct consumer dependencies on concrete platform.redaction classifier implementations.
  - [x] preserve the existing RedactionKind enum and all existing redaction failure/result contracts.
  - [x] no second classifier, policy registry, runtime configuration, context store, spans, metrics, or reset capability.

- [x] `docs/adr/ADR-0010-sensitive-data-redaction-boundary.md`
  - [x] preserve the existing ADR identifier, metadata, decision history, and redaction ownership.
  - [x] update Decision 1 to distinguish one default redaction implementation from two supported contracts-level security ports.
  - [x] register SensitiveKeyClassifierInterface alongside SensitiveDataRedactorInterface.
  - [x] preserve the exact two-method SensitiveDataRedactorInterface API.
  - [x] document classify(string $key): ?RedactionKind as read-only classification, not redaction or sink authorization.
  - [x] preserve SensitiveKeyClassifier as the package-internal concrete classifier.
  - [x] preserve the exact 98-alias classification vocabulary and existing canonicalization semantics.
  - [x] document the new declarative classifier interface alias to the existing shared classifier instance.
  - [x] preserve identity with the instance injected into DefaultSensitiveDataRedactor.
  - [x] clarify that composite key-path segmentation is a consumer responsibility.
  - [x] preserve producer- and sink-owned safety constraints when classification returns null.
  - [x] preserve platform.redaction independence from Kernel, logging, context, observability, and reset implementations.
  - [x] preserve config-free, stateless, deterministic, and fail-closed redaction behavior.
  - [x] no second redaction implementation, alias registry, package, or runtime module.

Generated repository changes:

- [x] `composer.json`
  - [x] add managed workspace path repository for coretsia/platform-logging
  - [x] add canonical release-line require-dev entry
  - [x] use existing sync tools
  - [x] preserve unrelated unmanaged Composer state

- [x] `tools/testing/package-index.php`
  - [x] regenerate through package_index.php
  - [x] include platform/logging canonical package metadata
  - [x] do not hand-edit generated payload

- [x] `packages/core/kernel/resources/packaging/installation-catalog.php`
  - [x] regenerate through installation_catalog.php
  - [x] include platform.logging and required runtime module edges
  - [x] preserve schemaVersion and canonical deterministic ordering
  - [x] do not hand-edit generated payload

- [x] `tools/testing/deptrac.yaml`
  - [x] regenerate through deptrac_generate.php
  - [x] reflect only canonical source edges from DEPENDENCIES.md
  - [x] do not add runtime-only platform/redaction edge as source permission
  - [x] do not hand-edit generated rules

- [x] Runtime/Kernel/Foundation source:
  - [x] Kernel ModulePlan implements the contracts-level AppTargetProviderInterface using its existing app() method.
  - [x] KernelServiceProvider exposes the existing ModulePlan through KernelServiceFactory::appTargetProvider() and a declarative classMethodFactory binding.
  - [x] the binding references ModulePlan::class only as a canonical external runtime seed.
  - [x] no compiled-container alias targets a runtime seed.
  - [x] Foundation production source remains unchanged.
  - [x] no new app-target resolver, runtime seed, context store, or module-selection mechanism is introduced.

- [x] Canonical preset resources:
  - [x] micro.php selects platform.logging by default
  - [x] express.php selects platform.logging by default
  - [x] hybrid.php selects platform.logging by default
  - [x] enterprise.php selects platform.logging by default
  - [x] all four declare platform.logging in modules, not required
  - [x] target-local exclusion remains supported
  - [x] Foundation NoopLogger remains the fallback
  - [x] no new mode preset selection semantics are introduced

#### Configuration (keys + defaults)

- [x] Files:
  - [x] `packages/platform/logging/config/logging.php`
  - [x] `packages/platform/logging/config/rules.php`
- [x] Keys (dot):
  - [x] `logging.driver` = 'stream'
  - [x] `logging.file.path` = ''
  - [x] `logging.file.name` = 'app'
  - [x] `logging.level` = 'info'
  - [x] `logging.max_record_bytes` = 65536
  - [x] `logging.service_name` = 'auto'
  - [x] `logging.format` = 'jsonl'
  - [x] no logging.stream key
- [x] Service identity:
  - [x] `auto` resolves the selected app target through AppTargetProviderInterface.
  - [x] canonical app targets are web, api, console, worker.
  - [x] an explicit bounded service_name may replace the emitted service label.
  - [x] canonical app_target remains separately available in every emitted record.
  - [x] service identity is resolved during logger construction, not per record.
- [x] Record size:
  - [x] configurable per-record limit is 1024..65536 bytes.
  - [x] MAX_RECORD_BYTES = 65536 remains the immutable hard upper bound.
  - [x] automatic daily UTC file partitioning is introduced only for driver='file'.
  - [x] no configurable total-file-size limit, retention period, archival, or automatic old-file deletion is introduced.
- [x] Format:
  - [x] supported values are jsonl and logfmt.
  - [x] default format is jsonl.
  - [x] jsonl uses JsonLineFormatter and Foundation StableJsonEncoder.
  - [x] logfmt uses LogfmtLineFormatter and the same admitted, redacted canonical record map.
  - [x] logfmt nested context serialization reuses Foundation StableJsonEncoder.
  - [x] both formats produce one deterministic LF-terminated physical record.
  - [x] both formats preserve canonical service identity, correlation fields, and safe context semantics.
  - [x] format is selected once during LoggerInterface construction.
  - [x] format does not affect module selection, log severity, shared redaction, or driver selection.
  - [x] format determines the daily filename extension only for driver='file'.
  - [x] unsupported format values fail configuration validation.
- [x] Driver semantics:
  - [x] stream → fixed php://stderr backend using the selected logging.format.
  - [x] file → automatic daily UTC append-only file backend using the same StreamLogger and formatter.
  - [x] null → Foundation NoopLogger.
  - [x] package not enabled → Foundation NoopLogger remains active.
  - [x] invalid configuration never silently selects NoopLogger.
  - [x] unavailable file destinations and failed file writes are isolated without a raw fallback.
- [x] Security:
  - [x] no `logging.redaction.enabled`
  - [x] redaction is mandatory for every emitted stream or file record.
  - [x] no raw/debug/passthrough redaction modes
  - [x] no configurable classifier or sensitive-key registry
- [x] Rules:
  - [x] strict declarative config shape
  - [x] unknown and reserved keys rejected
  - [x] no arbitrary stream URI, remote destination, unsafe file directory, or unvalidated filename.
  - [x] file output is restricted to the configured absolute local directory after successful per-emission canonical resolution and validation, using only the generated daily filename; an unavailable directory causes emission omission without fallback or configuration mutation.
  - [x] config validation remains Kernel-owned

#### Wiring / DI tags (when applicable)

- Tags introduced:
  - N/A
- [x] ServiceProvider wiring evidence:
  - [x] Foundation LoggerInterface → NoopLogger remains unchanged.
  - [x] LoggingServiceProvider uses both canonical provider interfaces.
  - [x] platform.logging module requires platform.redaction.
  - [x] LoggingServiceProvider declares ConfigRepositoryInterface as a required service reference.
  - [x] LoggingServiceProvider declares SensitiveDataRedactorInterface and SensitiveKeyClassifierInterface as required service references.
  - [x] SensitiveKeyClassifierInterface resolves through the existing platform.redaction declarative alias.
  - [x] the classifier alias resolves to the identical shared instance used by DefaultSensitiveDataRedactor.
  - [x] LoggingServiceFactory receives the two security ports in the exact declared positional order.
  - [x] no concrete platform.redaction implementation or duplicated classifier is introduced into logging DI definitions.
  - [x] LoggingServiceFactory reads validated logging.driver, logging.file.path, logging.file.name, logging.level, logging.max_record_bytes, logging.service_name, and logging.format during LoggerInterface construction.
  - [x] file.path is validated semantically only when driver='file'; no filesystem I/O occurs for inactive file mode.
  - [x] file.name is validated through the canonical bounded ASCII grammar.
  - [x] AppTargetProviderInterface resolves through the Kernel-owned classMethodFactory binding backed by the existing ModulePlan runtime seed.
  - [x] source and compiled containers return the same ModulePlan instance through AppTargetProviderInterface.
  - [x] the compiled graph passes ContainerGraphCompletenessValidator without runtime-seed alias targets.
  - [x] LoggerInterface construction captures the validated selected app target and effective service_name.
  - [x] no app-target values, stream destinations, or runtime configuration objects enter deterministic declarative DI parameters.
  - [x] no logging.config declarative parameter is introduced.
  - [x] no php://stderr value enters canonical service-definition parameters.
  - [x] source and compiled container definitions use the same classMethodFactory references.
  - [x] LoggerInterface binding uses classMethodFactory with LoggingServiceFactory::createLogger().
  - [x] all service dependencies use ContainerValueReference.
  - [x] service definitions contain no closures, open streams, or mutable runtime values.
  - [x] enabled stream driver resolves StreamLogger with fixed php://stderr.
  - [x] enabled file driver resolves the same StreamLogger with validated daily file output.
  - [x] null driver resolves Foundation NoopLogger.
  - [x] stream initialization failure resolves Foundation NoopLogger.
  - [x] file destination and write failures discard the affected emission without disclosing diagnostics.
  - [x] file output introduces no additional LoggerInterface binding or provider.
  - [x] existing provider-plan ordering determines binding precedence.
  - [x] source and compiled container definitions remain semantically identical.
  - [x] no service locator or second DI implementation.
  - [x] no kernel.reset or kernel.stateful tags.

#### Artifacts / outputs (if applicable)

- [x] Build and compiled artifacts:
  - [x] N/A — no new Coretsia artifact schema, manifest, registry, or compiled artifact format.
- [x] Runtime outputs:
  - [x] driver=stream emits admitted and redacted records only to php://stderr.
  - [x] driver=file emits admitted and redacted records to `{app_target}-{file.name}-{YYYY-MM-DD}.{format}` inside the validated existing directory.
  - [x] driver=null emits no records.
  - [x] daily log files are runtime output files, not Kernel build artifacts.
  - [x] the file backend does not publish, register, fingerprint, or hydrate log files through the Kernel artifact pipeline.
  - [x] the selected format determines the encoded record and filename extension, not an additional artifact schema.
  - [x] no retention, deletion, archival, or total-storage management is introduced.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [x] Context reads:
  - [x] correlation_id through CorrelationIdProviderInterface
  - [x] `ContextKeys::UOW_ID`
  - [x] `ContextKeys::UOW_TYPE`
  - [x] optional `ContextKeys::PATH_TEMPLATE` only when already safe-by-construction
- [x] Context fields:
  - [x] correlation_id
  - [x] uow_id
  - [x] uow_type
  - [x] all three canonical record fields always exist
  - [x] absent/invalid/unreadable values become null
  - [x] values MUST NOT be copied from arbitrary caller context
  - [x] no synthetic UoW/correlation identifiers are generated
- [x] Context writes:
  - N/A
- [x] Reset discipline:
  - [x] ContextStore remains the sole owner of UoW-local context state.
  - [x] ContextBagProcessor reads context on each admitted emission.
  - [x] processor/logger MUST NOT cache ContextBag, current UoW IDs, raw request values, or per-UoW diagnostic records.
  - [x] process-lifetime STDERR stream handle is not UoW-scoped state.
  - [x] emission-scoped daily file handles are not UoW-scoped state.
  - [x] file partitioning never changes canonical UoW identity, correlation fields, or reset semantics.
  - [x] no ResetInterface or kernel.reset tag is introduced.
  - [x] after ContextStore reset, the next record cannot inherit identifiers from the previous UoW.
  - [x] missing/throwing context reads remain no-op-safe.

#### Observability (policy-compliant)

- [x] Spans:
  - [x] N/A — no per-log-record logging.write span.
  - [x] emitting spans from LoggerInterface risks recursive logging through tracer/exporter implementations.
  - [x] existing operation-boundary tracing remains authoritative.
- [x] Metrics:
  - [x] N/A — no per-log-record logging.write_total or logging.write_duration_ms.
  - [x] emitting metrics from LoggerInterface risks recursive logging through metric adapters.
  - [x] existing producer operation metrics remain authoritative.
  - [x] no changes to canonical metrics catalog are required.
- [x] Logs:
  - [x] every emitted record uses the selected jsonl or logfmt serialization of the same canonical admitted and redacted record schema.
  - [x] jsonl remains the default and preserves canonical JSON-lines record compatibility.
  - [x] logfmt preserves all canonical fields through deterministic key=value serialization.
  - [x] both formats emit exactly one physical LF-terminated record after a complete successful write.
  - [x] correlation/uow fields have UoW-scoped nullable semantics
  - [x] only static safe event identifiers are emitted
  - [x] only logging-policy-approved operational context fields are emitted
  - [x] redaction is defense in depth after producer-owned field admissibility
  - [x] no raw Throwable messages, stack traces, paths, headers, cookies, credentials, tokens, payloads, SQL, or env values
  - [x] no self-logging on internal logger failure

#### Errors

- [x] Exceptions introduced:
  - [x] N/A — no package-local LoggingWriteFailedException.
- [x] Error handling:
  - [x] stream initialization failure → Foundation NoopLogger.
  - [x] file destination unavailable or unwritable → discard current emission without altering validated configuration.
  - [x] stream or file write failure → discard current record without propagating internal failure.
  - [x] file lock contention → discard current record without blocking, retrying, or emitting diagnostics.
  - [x] file handles and acquired locks are released even when emission fails.
  - [x] formatting failure → discard current record
  - [x] redaction failure → no raw fallback or partial record emission
  - [x] optional context read failure → null/omitted enrichment
  - [x] no stateful permanent-disable flag or "warn once" mechanism
  - [x] no recursive error reporting through LoggerInterface
  - [x] no PHP warnings, configured or generated filesystem paths, OS errors, lock diagnostics, Throwable messages, or previous Throwable data escape from destination handling.
  - [x] invalid PSR-3 level remains subject to Psr\Log\InvalidArgumentException as required by PSR-3
  - [x] internal logging failures MUST NOT alter caller business behavior or primary exception precedence

#### Security / Redaction

- [x] Mandatory shared redaction:
  - [x] consumes SensitiveDataRedactorInterface
  - [x] consumes SensitiveKeyClassifierInterface for read-only classification of admitted structural key paths.
  - [x] the canonical 98-alias vocabulary remains exclusively owned by platform.redaction.
  - [x] initial classification failure prevents the mandatory redaction call for the rejected record.
  - [x] post-redaction classification failure rejects the already redacted record without repeating redaction.
  - [x] an unclassified key is not considered safe without the logging-owned sink admission constraints.
  - [x] classification and redaction never introduce logging-owned spans, metrics, reset hooks, or runtime context state.
  - [x] fixed RedactionContext scope = logging.record
  - [x] fixed RedactionMode = Placeholder
  - [x] producer-owned field admission precedes shared redaction
  - [x] the shared redactor processes the complete assembled canonical record, including admitted producer message/context, safe UoW enrichment, and trusted logger-generated metadata.
  - [x] trusted logger-generated level, ts_utc, service_name, and app_target fields are assembled before mandatory shared redaction.
  - [x] trusted logger-generated metadata MUST NOT contain arbitrary producer or request data.
  - [x] ClockInterface failure produces null ts_utc before shared redaction without failing the log call.
  - [x] the complete canonical record is validated after shared redaction and before serialization.
  - [x] no canonical root field may be added, overwritten, or reconstructed after post-redaction validation.
  - [x] no caller-controlled field may bypass the shared redactor by being classified as trusted logger metadata.
  - [x] post-redaction admissibility validation is mandatory
  - [x] classifier non-match never authorizes an otherwise forbidden field
  - [x] unexpected redactor output type is treated as a failed emission
  - [x] RedactionException and other redactor failures never return original or partially redacted data to the sink
  - [x] redaction failure deterministically discards the entire record.
  - [x] no second redaction pass or synthetic replacement record is emitted.
  - [x] a redaction failure MUST NOT be converted into a successful logging emission.
  - [x] no hash/length disclosure mode is introduced implicitly
  - [x] no redaction disable flag
  - [x] no duplicate generic classifiers, redaction walkers, or hashing utilities
- [x] Sink admissibility:
  - [x] producer-owned safe-by-construction event/context requirements remain mandatory
  - [x] only explicitly approved fields enter the canonical log record
  - [x] classifier non-match is not proof of safety
  - [x] unsupported fields and values are omitted
  - [x] unsafe messages become fixed safe event tokens
  - [x] no arbitrary Stringable evaluation
  - [x] no Throwable or object serialization
  - [x] no context interpolation into event messages
- [x] MUST NOT leak:
  - [x] Authorization, Cookie, Set-Cookie, session ids, credentials, tokens
  - [x] raw headers, query, request/response body, provider payloads, raw SQL
  - [x] raw env/config values or sensitive secret references
  - [x] raw request path, host, user-agent, client IP
  - [x] email, phone, username, names, or other private customer data
  - [x] raw exception messages, previous Throwable data, or stack traces
- [x] Allowed:
  - [x] canonical safe correlation/uow identifiers
  - [x] bounded operation/outcome/reason/status tokens
  - [x] non-negative integer durations and counts
  - [x] owner-approved route templates and normalized relative artifact summaries
  - [x] explicitly approved safe derivations only when the owner policy permits disclosure
- [x] Fail-closed:
  - [x] redaction failure never returns raw input to the sink
  - [x] unsupported values never bypass the shared redactor
  - [x] no alternate raw/debug output channel

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [x] Context writes:
  - [x] N/A — no ContextStore writes

- [x] kernel.reset:
  - [x] N/A — no package-owned UoW state

- [x] LoggerInterface baseline:
  - [x] `packages/platform/logging/tests/Integration/LoggingProviderOverridesFoundationNoopTest.php`

- [x] Correlation/UoW:
  - [x] `packages/platform/logging/tests/Integration/CorrelationIdIsAlwaysPresentInLogsTest.php`

- [x] Redaction:
  - [x] `packages/platform/logging/tests/Contract/LoggingDoesNotLogSecretsContractTest.php`

- [x] Deterministic serialization:
  - [x] `packages/platform/logging/tests/Unit/JsonLineFormatterDeterministicOrderTest.php`
  - [x] `packages/platform/logging/tests/Unit/LogfmtLineFormatterDeterministicOrderTest.php`
  - [x] both formats preserve the same canonical admitted and redacted record values.

- [x] Fail-safe/noop:
  - [x] `packages/platform/logging/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Test harness / fixtures

- [x] Tests use deterministic, isolated I/O fixtures: the fixed php://stderr destination is exercised in isolated PHP subprocesses, and the file backend is exercised using isolated temporary directories with deterministic cleanup.
- [x] StreamLogger stream-write and short-write failures are exercised against the fixed php://stderr destination in isolated PHP subprocesses using process-local stream-wrapper substitution where required.
- [x] Serialization, formatter-failure, and serialized-frame byte-limit tests exercise the existing formatter and StreamLogger pipeline using deterministic canonical records and isolated temporary file destinations where applicable.
- [x] Byte-limit tests verify exact inclusive and exclusive boundaries against the complete serialized frame, including the final LF, without bypassing canonical record admission or introducing a test-only production sink.
- [x] File backend tests use isolated temporary directories with cleanup and no dependence on the host's /var/log permissions.
- [x] Daily file tests cover UTC date transitions, multiple canonical app targets, both formats, and fixed filename stems.
- [x] File tests verify regular-file destinations, denied symbolic links, missing directories, permissions, non-blocking lock contention, and cleanup after failed writes.
- [x] File tests verify append-only behavior and complete-record locking without requiring external log-rotation services.
- [x] File tests use fixed clocks to verify deterministic date selection where ts_utc is available.
- [x] Clock failure tests verify the unchanged nullable ts_utc contract and the UTC system-date fallback used only for file partitioning.
- [x] No filesystem test mutates real application, system, or user log directories.
- [x] LoggingServiceFactory stream initialization failure is tested separately from StreamLogger write failure.
- [x] a failed php://stderr open is simulated only in an isolated PHP subprocess or an equivalently isolated test process.
- [x] tests may temporarily unregister and restore the PHP stream wrapper inside that isolated process.
- [x] stream-wrapper manipulation MUST NOT affect the PHPUnit parent process or parallel test workers.
- [x] the test restores modified process-local runtime facilities before subprocess exit.
- [x] no production stream-opener abstraction, arbitrary destination configuration, runtime closure, or test-only DI service is introduced solely for failure simulation.
- [x] No real HTTP, network, database, or external logging service required.
- [x] No platform/http production symbols imported.
- [x] Package-owned tests MUST NOT import platform/errors implementation classes or Kernel module-planning implementations.
- [x] Test-only imports of Coretsia\Kernel\Config\ConfigValidator and Coretsia\Kernel\Container\ContainerCompiler are permitted exclusively in package-owned contract tests for canonical configuration validation and source/compiled container-definition parity.
- [x] These narrowly scoped test-only dependencies MUST NOT introduce Kernel implementation imports into packages/platform/logging/src/**, extend the production layered dependency matrix, or duplicate Kernel-owned configuration validation, container compilation, or module planning.
- [x] ConfigRepositoryInterface test doubles replace Kernel concrete config repositories where only the port is required.
- [x] Runtime module planning is not duplicated inside platform.logging tests.
- [x] The package test harness MUST NOT require direct injection of arbitrary writable stream resources into StreamLogger.
- [x] The production stream backend retains the fixed, non-configurable php://stderr destination; failure simulation uses isolated subprocesses and process-local PHP stream-wrapper substitution without adding another constructor dependency, stream-opener abstraction, or configurable destination.
- [x] File destination tests use temporary directories and do not introduce a production runtime filesystem service solely for testing.
- [x] Fixed clocks used for deterministic timestamp tests.
- [x] Foundation ContextStore may be used in integration tests for UoW/reset behavior.
- [x] No package-local replacement context store, redaction engine, or serialization helper.

### Tests (MUST)

- Unit:
  - [x] `packages/platform/logging/tests/Unit/LoggingServiceFactoryConfigTest.php`
    - [x] reads exactly logging.driver, logging.file.path, logging.file.name, logging.level, logging.max_record_bytes, logging.service_name, and logging.format.
    - [x] does not recognize or read logging.stream.
    - [x] accepts SensitiveKeyClassifierInterface as the fifth positional factory dependency.
    - [x] preserves the exact seven-argument factory signature and provider reference order.
    - [x] passes the injected classifier and redactor ports to LogRecordPolicy.
    - [x] verifies the factory does not instantiate or import a concrete platform.redaction classifier.
    - [x] preserves all existing logging configuration validation and backend selection semantics.
    - [x] accepts max_record_bytes in the inclusive range 1024..65536.
    - [x] rejects malformed or out-of-range max_record_bytes values.
    - [x] accepts format=jsonl and format=logfmt.
    - [x] selects JsonLineFormatter for jsonl.
    - [x] selects LogfmtLineFormatter for logfmt.
    - [x] rejects unsupported formats, wrong types, and malformed configuration.
    - [x] verifies both formats use identical canonical record admission, enrichment, redaction, and severity filtering.
    - [x] verifies formatter selection is captured once during logger construction.
    - [x] resolves service_name=auto from an injected AppTargetProviderInterface test double.
    - [x] verifies automatic service names for web, api, console, and worker.
    - [x] accepts bounded explicit service names and rejects unsafe values.
    - [x] rejects invalid canonical app-target provider values.
    - [x] preserves the canonical app_target independently of explicit service_name overrides.
    - [x] does not import Kernel implementation classes or introduce another app-target resolver.
    - [x] accepts exact valid string enum values
    - [x] rejects PHP null, boolean false, unknown driver, and unknown level
    - [x] stream always selects the fixed php://stderr destination.
    - [x] file constructs StreamLogger with the validated directory policy, immutable filename stem, canonical app_target, and selected format; the daily destination pathname is resolved only during an eligible emission using the UTC record date.
    - [x] verifies filename identity is independent of explicit service_name overrides.
    - [x] rejects relative, traversing, malformed, remote, and stream-wrapper file.path values.
    - [x] rejects malformed file.name values and explicit extensions.
    - [x] requires non-empty file.path only for file driver.
    - [x] validates file.path and file.name types even when the file backend is inactive.
    - [x] stream and null drivers do not open or create daily file destinations; stream initializes only the fixed php://stderr destination, while null opens no destination.
    - [x] malformed configuration is not silently converted into NoopLogger
    - [x] rejects malformed non-driver configuration even when logging.driver='null'.
    - [x] validates the five shared scalar keys and the two required nested file keys before constructing or returning a logger.
    - [x] validates driver-dependent file.path syntax and file.name grammar before returning the logger.
    - [x] a syntactically valid file.path does not require an existing or writable directory during factory construction.
    - [x] factory construction does not create, resolve, open, or probe the configured file directory.
    - [x] a missing directory is treated as an emission-time I/O failure by StreamLogger.
    - [x] a malformed path is rejected deterministically before StreamLogger construction.
    - [x] distinguishes invalid configuration from stream initialization failure.
    - [x] valid null driver returns Foundation NoopLogger
    - [x] stream initialization failure returns Foundation NoopLogger
    - [x] simulate failure to open the exact allowlisted php://stderr destination in an isolated PHP process.
    - [x] verify the failure selects the existing Foundation NoopLogger without exposing PHP warnings.
    - [x] verify no alternative stream destination or second open attempt is used.
    - [x] verify the test does not alter PHP stream-wrapper state in the parent PHPUnit process.
    - [x] runtime settings are captured once during construction
    - [x] file mode captures the validated directory and filename stem once.
    - [x] file mode resolves the UTC day at emission time without rereading runtime configuration.
    - [x] valid file configuration selects the same StreamLogger class and shared record pipeline as stream mode.
    - [x] no logger-owned configuration cache
    - [x] no filesystem path or error message leakage

  - [x] `packages/platform/logging/tests/Unit/JsonLineFormatterDeterministicOrderTest.php`
    - [x] recursive strcmp key order
    - [x] list order preserved
    - [x] exact one final LF
    - [x] root emitted as JSON object
    - [x] empty context is emitted as JSON null, not JSON [].
    - [x] non-empty context is emitted as a JSON object.
    - [x] nested list values preserve their original order.
    - [x] no second JSON encoder or object-casting fallback is introduced.
    - [x] deterministic encoding across map insertion orders
    - [x] embedded newline cannot introduce another physical record
    - [x] no float/object/resource serialization
    - [x] stable canonical root field schema
    - [x] canonical root schema includes service_name and app_target.
    - [x] service identity fields remain present outside UoW.
    - [x] caller context cannot override canonical service identity fields.
    - [x] service identity fields participate in post-redaction validation.
    - [x] format=jsonl preserves deterministic JSON-lines serialization through Foundation StableJsonEncoder.

  - [x] `packages/platform/logging/tests/Unit/LogfmtLineFormatterDeterministicOrderTest.php`
    - [x] deterministic canonical root field ordering.
    - [x] exact key=value spacing and quoting.
    - [x] correct quoted-string versus unquoted-null distinction.
    - [x] deterministic escaping of backslashes, double quotes, LF, CR, TAB, and prohibited control characters.
    - [x] canonical service_name and app_target fields remain present.
    - [x] correlation_id, uow_id, and uow_type remain present and nullable.
    - [x] empty context is emitted as context=null.
    - [x] non-empty context is serialized through Foundation StableJsonEncoder and embedded as one quoted value.
    - [x] nested context ordering remains deterministic.
    - [x] exactly one final LF is emitted.
    - [x] embedded control characters cannot introduce additional physical records.
    - [x] no arbitrary Stringable evaluation or unsupported PHP value serialization.
    - [x] identical canonical records produce byte-identical logfmt output.
    - [x] formatting failures do not expose rejected input or diagnostics.
    - [x] no second recursive JSON normalizer or encoder.

  - [x] `packages/platform/logging/tests/Unit/LogRecordPolicyTest.php`
    - [x] accepts canonical safe operational fields
    - [x] omits unknown/unsafe fields
    - [x] preserves existing approved Kernel operational summaries
    - [x] preserves safe app_target and preset values from Kernel Ops.
    - [x] preserves bounded moduleIds and presetName from module-resolution diagnostics.
    - [x] preserves existing services_count and groups_count from Foundation reset diagnostics.
    - [x] preserves worker status, driver, control_transport, pid, worker_count, and endpoint_hash under their owner-approved safety rules.
    - [x] preserves the already bounded safe artifact/source metadata lists.
    - [x] does not reinterpret safe producer-owned relative paths as raw HTTP paths.
    - [x] rejects classified sensitive key_paths through SensitiveKeyClassifierInterface.
    - [x] preserves mandatory whole-record redaction through SensitiveDataRedactorInterface independently of key-path rejection.
    - [x] rejects messages violating the canonical event-token grammar.
    - [x] applies mandatory shared redaction to admitted message values.
    - [x] recognized sensitive messages never reach the output sink.
    - [x] unsupported Stringable messages are not evaluated.
    - [x] tests do not assume that generic redaction can detect every arbitrary unclassified secret.
    - [x] producer-owned static event identifiers remain a mandatory caller contract.
    - [x] does not pass through raw paths under innocent-looking keys
    - [x] omits invalid json-like branches
    - [x] rejects raw Throwable, Stringable object evaluation, resources, and floats
    - [x] catches shared redaction failure and fails closed
    - [x] uses only Placeholder disclosure mode
    - [x] no duplicate generic redaction classifier
    - [x] uses an injected SensitiveKeyClassifierInterface test double.
    - [x] does not import platform.redaction implementation classes.
    - [x] rejects canonical dotted key paths containing classified sensitive segments.
    - [x] rejects db.password, db.client_secret, db.access_token, and user.email_address.
    - [x] rejects sensitive aliases hidden in indexed structural segments, including db.password[0] and db.client_secret[3].
    - [x] rejects sensitive aliases hidden inside structural placeholders, including user.<password>.
    - [x] classifies the original complete path and dot-separated segments before inspecting structural components.
    - [x] rejects the complete key_paths field when any indexed or placeholder component is classified as sensitive.
    - [x] preserves safe bounded Kernel structural placeholders whose components are not classified as sensitive.
    - [x] verifies the additional structural-component checks do not duplicate the canonical sensitive-key alias vocabulary.
    - [x] accepts safe producer-owned examples such as logging.driver and worker.output.format.
    - [x] verifies that classifier null results do not bypass logging-owned structural and semantic constraints.
    - [x] verifies the policy does not import or duplicate Kernel ConfigKernel sensitive-key or SQL-like regex tables.
    - [x] verifies unsafe structural key paths are rejected independently of shared classifier results.
    - [x] preserves safe Core producer key paths that pass their owner-owned validation and logging sink admission.
    - [x] never treats an unclassified key as automatically safe.
    - [x] verifies both complete-path and segment-level classification.
    - [x] preserves exact structural, cardinality, and byte bounds.
    - [x] omits rejected key-path fields without emitting raw input, hashes, or lengths.
    - [x] rejects the complete key_paths field when any one item is classified as sensitive.
    - [x] does not emit a partially retained key_paths list.
    - [x] rejects empty dotted path segments.
    - [x] preserves safe bounded structural placeholders produced by existing Core diagnostics.
    - [x] distinguishes ordinary key-path rejection from classifier failure requiring complete record omission.
    - [x] classifier failure causes omission of the entire current record.
    - [x] distinguish a valid empty admitted context from a classifier failure.
    - [x] verify a throwing classifier never produces a successful record with context=null.
    - [x] verify initial classifier failure does not invoke the mandatory redactor or formatter.
    - [x] verify post-redaction classifier failure occurs after exactly one mandatory redaction call.
    - [x] verify post-redaction classifier failure does not invoke the formatter or stream writer.
    - [x] verify direct redactRecord() invocation cannot bypass instance-bound sensitive-key classification.
    - [x] verify a redactor test double that introduces a classified sensitive key path causes post-redaction record rejection.
    - [x] verify no second redaction call or partial record is emitted after post-redaction classifier failure.
    - [x] verify the entire record is omitted without exposing raw input or Throwable details.
    - [x] revalidates key-path safety after shared redaction.
    - [x] static structural validation cannot bypass instance-bound classifier admission.
    - [x] no local sensitive-key alias registry or duplicated classification regex.
    - [x] preserves one mandatory shared redaction operation per admitted record.
    - [x] admits bounded colon-separated operation category tokens through one producer-neutral grammar while rejecting unsafe, unbounded, or arbitrary user-derived values.
    - [x] caller-supplied identifiers are admitted only through the canonical context-field policy and cannot override processor-owned root identifiers.
    - [x] identical admissible records receive identical sink-policy decisions regardless of their producer or static event identifier.
    - [x] no producer-specific, package-specific, or event-specific admission rules are introduced.

  - [x] `packages/platform/logging/tests/Unit/StreamLoggerFailureIsolationTest.php`
    - [x] severity filtering before emission
    - [x] invalid PSR-3 level contract
    - [x] short/failed stream write isolation
    - [x] clock failure isolation
    - [x] ClockInterface is invoked only for records admitted by severity filtering and initial sink admission.
    - [x] UTC timestamp is captured and normalized before mandatory shared redaction.
    - [x] clock failure produces canonical ts_utc=null without interrupting logging.
    - [x] file mode uses the validated UTC timestamp date for daily filename selection.
    - [x] file mode retains ts_utc=null on ClockInterface failure and uses a separate UTC system-date fallback only for the filename.
    - [x] a UTC date rollover selects a new daily filename without restarting the logger.
    - [x] no filename date cache or stale file descriptor survives between file emissions.
    - [x] the shared redactor receives the complete assembled canonical record, including ts_utc, level, service_name, and app_target.
    - [x] post-redaction validation occurs after all canonical root fields have been assembled.
    - [x] no canonical root field is modified after post-redaction validation.
    - [x] jsonl and logfmt receive the same complete validated canonical record.
    - [x] formatter failure isolation
    - [x] no raw fallback output
    - [x] no recursive self-reporting
    - [x] no caller-visible internal logging failure
    - [x] verify MAX_RECORD_BYTES = 65536 remains the immutable hard ceiling.
    - [x] verify the configured effective byte limit cannot exceed MAX_RECORD_BYTES.
    - [x] verify hard-ceiling enforcement is applied to the complete serialized frame after formatting.
    - [x] do not bypass canonical record admission or widen safe field bounds merely to manufacture 65536-byte or 65537-byte fixtures.
    - [x] exercise exact inclusive and exclusive boundaries using configured byte limits reachable with fully admitted canonical records.
    - [x] do not introduce another formatter abstraction or synthetic production sink solely for hard-ceiling tests.
    - [x] configured max_record_bytes=16384 admits records of exactly 16384 serialized bytes.
    - [x] configured max_record_bytes=16384 discards records of 16385 serialized bytes before I/O.
    - [x] configured limits cannot exceed MAX_RECORD_BYTES.
    - [x] both jsonl and logfmt enforce the effective limit after complete serialization.
    - [x] both formats admit records of exactly the configured byte limit.
    - [x] both formats discard records exceeding the configured byte limit before I/O.
    - [x] both formats isolate serialization and write failures without raw fallback emission.
    - [x] both formats preserve one-write-attempt and no-recursive-reporting semantics.
    - [x] no truncation or fallback emission occurs at the configurable byte limit.
    - [x] final LF participates in byte-limit accounting.
    - [x] oversized records are not truncated into invalid JSON.
    - [x] no fallback emission occurs when the byte budget is exceeded.
    - [x] file mode enforces the identical record-byte limit including the final LF.
    - [x] no file is opened for records rejected by severity filtering, initial admission, redaction, post-admission, formatting, or byte-limit checks.
    - [x] file mode attempts exactly one bounded fwrite() for each admitted serialized record.
    - [x] failed or short file writes never trigger a second write or fallback destination.

  - [x] `packages/platform/logging/tests/Unit/DailyFileDestinationTest.php`
    - [x] driver=file uses the existing StreamLogger class.
    - [x] file.path is a syntactically validated absolute local directory path.
    - [x] the directory is required to exist only when an eligible file emission is attempted.
    - [x] a valid file logger can be constructed while its configured directory is temporarily missing.
    - [x] missing directories cause omission of the current emission without file creation, fallback, or diagnostic leakage.
    - [x] creating the configured directory externally permits a subsequent emission through the same StreamLogger instance.
    - [x] removing or making the directory inaccessible between emissions does not change logger configuration or cached runtime identity.
    - [x] no new LoggerInterface instance, DI resolution, or configuration read is required for recovery.
    - [x] file.name is an extension-free bounded stem.
    - [x] the exact generated filename is `{app_target}-{file.name}-{YYYY-MM-DD}.{format}`.
    - [x] canonical app targets web, api, console, and worker produce distinct prefixes.
    - [x] explicit service_name overrides never change the filename prefix.
    - [x] jsonl produces .jsonl; logfmt produces .logfmt.
    - [x] UTC calendar date controls daily file selection.
    - [x] multiple records on one UTC day append to the same file.
    - [x] a new UTC day creates a different filename.
    - [x] existing files are never renamed, truncated, deleted, compressed, or archived.
    - [x] no file is created when driver=stream or driver=null.
    - [x] invalid directory, unsupported destination type, and symbolic link destinations are rejected without raw diagnostics.
    - [x] file writes acquire a non-blocking exclusive flock().
    - [x] lock contention omits the record without blocking or retrying.
    - [x] one complete serialized bounded record is submitted through one fwrite() attempt.
    - [x] short writes and filesystem failures are isolated without raw fallback.
    - [x] resources and locks are released after success or failure.
    - [x] locking uses the actual daily destination handle, not a separate lock-anchor file.
    - [x] file emission does not instantiate or invoke Foundation ScopedFileLock.
    - [x] file emission creates no additional directory, lock file, manifest, or temporary log artifact.
    - [x] an opened destination is verified as a regular file before writing.
    - [x] detectable symlinks are rejected without attempting raw fallback.
    - [x] concurrent modifications by an untrusted actor are explicitly outside the supported trusted-directory guarantee.
    - [x] missing and temporarily unwritable directories are isolated as I/O failures without replacing StreamLogger with NoopLogger.
    - [x] a subsequent emission can succeed after the destination becomes available.
    - [x] POSIX and Windows path-shape tests cover the supported local absolute forms where applicable.
    - [x] both formats use the existing record admission and mandatory shared redaction.
    - [x] tests use isolated temporary directories and deterministic fixture cleanup.

- Contract:
  - [x] `packages/core/contracts/tests/Contract/SensitiveKeyClassifierInterfaceShapeContractTest.php`
    - [x] verify the exact Coretsia\Contracts\Security\SensitiveKeyClassifierInterface FQCN.
    - [x] verify it is an interface, not a class or enum.
    - [x] verify exactly one public instance method: classify.
    - [x] verify the exact string $key parameter and nullable RedactionKind return type.
    - [x] verify no additional public methods, properties, alias constants, or mutable policy state.
    - [x] verify the contract depends only on contracts-level types.
    - [x] verify no dependency on platform.redaction, Kernel, Foundation, or logging implementations.
    - [x] verify no service locator, configuration, runtime context, or reset contract.
    - [x] verify null classification is documented as unclassified, not sink-safe.
    - [x] preserve the existing SensitiveDataRedactorInterfaceShapeContractTest unchanged.
    - [x] no classifier implementation or redaction behavior is introduced in core/contracts tests.

  - [x] `packages/platform/logging/tests/Contract/LoggingConfigAndDefinitionSafetyContractTest.php`
    - [x] no logging.config parameter containing php://stderr
    - [x] no stream URI serialized into declarative container parameter values
    - [x] ConfigRepositoryInterface is a required factory service reference
    - [x] definition construction succeeds with valid logging configuration
    - [x] source and compiled definition inputs contain only admissible deterministic values
    - [x] logging.driver uses only supported ConfigValidator string enum semantics
    - [x] allowed logging.driver values are exactly stream, file, and null.
    - [x] logging.stream is rejected as an unknown configuration key.
    - [x] logging.file is a required map with exactly path and name.
    - [x] logging.file.path is a required string and defaults to ''.
    - [x] logging.file.name uses the existing reset-group-id rule and defaults to app.
    - [x] nested unknown and reserved keys are rejected.
    - [x] file.path requires a syntactically valid non-empty absolute local directory path when driver=file.
    - [x] Kernel ConfigValidator owns declarative nested-map and scalar validation only.
    - [x] LoggingServiceFactory owns driver-dependent file.path syntax validation without a new configuration rule type.
    - [x] StreamLogger owns per-emission filesystem availability checks.
    - [x] a missing but syntactically valid directory MUST NOT fail canonical configuration validation or silently select NoopLogger.
    - [x] no runtime filesystem checks, paths, file handles, or directory existence results enter declarative DI definition values.
    - [x] the factory independently validates driver-dependent file destination semantics.
    - [x] canonical ConfigValidator accepts file.path='' when the file backend is inactive.
    - [x] canonical ConfigValidator validates the nested file map without new rule types.
    - [x] factory rejects file.path='' when driver=file.
    - [x] factory rejects malformed absolute paths independently of declarative type validation.
    - [x] neither formatter duplicates the canonical root-field allowlist defined by LogRecordPolicy.
    - [x] both formatter implementations reuse LogRecordPolicy::isValidCanonicalRecord() for structural checks.
    - [x] file mode introduces no second sink admission policy, generic lock service, runtime registry, or DI tag.
    - [x] no file path, generated filename, or opened file handle enters declarative DI definitions.
    - [x] logging.service_name uses the existing ConfigValidator reset-group-id type.
    - [x] canonical configuration validation accepts auto, web, api, console, worker, and valid bounded explicit service names.
    - [x] canonical configuration validation rejects dotted names, paths, URLs, whitespace, control characters, uppercase letters, and values exceeding 64 bytes.
    - [x] malformed service_name fails canonical configuration validation without waiting for lazy LoggerInterface resolution.
    - [x] LoggingServiceFactory independently rejects the same invalid values when invoked directly.
    - [x] PHP null is rejected as driver
    - [x] logging.redaction.enabled cannot be introduced as a recognized config key
    - [x] unknown logging.redaction subtree is rejected
    - [x] mandatory redaction binding cannot be bypassed by configuration
    - [x] logging.format accepts exactly jsonl and logfmt.
    - [x] invalid format configuration fails deterministically before stream or file emission.
    - [x] formatter selection does not introduce non-declarative DI definitions or runtime formatter registries.

  - [x] `packages/platform/logging/tests/Contract/LoggingDoesNotLogSecretsContractTest.php`
    - [x] Authorization/Cookie/session/token redaction
    - [x] header/body/payload branch suppression
    - [x] raw SQL and raw env values suppressed
    - [x] raw path/host/user-agent/client IP suppressed
    - [x] unknown-key secret and PII cases fail closed
    - [x] classified sensitive key-path segments are rejected before serialization.
    - [x] classifier null results cannot bypass destination-specific admission.
    - [x] classifier exceptions fail closed without fallback output.
    - [x] malicious key-path values cannot be restored through post-redaction context processing.
    - [x] shared classifier aliases remain effective without being duplicated in logging.
    - [x] both jsonl and logfmt enforce identical key-path confidentiality.
    - [x] stream and file destinations enforce identical shared redaction and sink admission.
    - [x] file mode cannot emit a rejected record or an unredacted fallback.
    - [x] unsafe filenames and filesystem error diagnostics are never included in emitted records.
    - [x] unsafe message handling
    - [x] redaction failure does not leak raw input
    - [x] malformed map/object/float handling
    - [x] redaction cannot be disabled by config
    - [x] all existing secret, PII, malformed-value, and redaction-failure cases are verified for jsonl and logfmt.
    - [x] logfmt quoting and escaping cannot disclose rejected raw values.
    - [x] embedded JSON context values are emitted only after mandatory shared redaction and post-redaction validation.
    - [x] no format-specific redaction bypass or fallback sink exists.

  - [x] `packages/platform/logging/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
    - [x] all PSR-3 severity methods
    - [x] arbitrary PSR-3 context accepted safely
    - [x] internal write failures do not throw
    - [x] StreamLogger rejects invalid log levels through Psr\Log\InvalidArgumentException.
    - [x] Foundation NoopLogger retains its existing no-op behavior without new validation.
    - [x] disabled-driver tests do not require changes to Foundation NoopLogger semantics.
    - [x] internal stream/redaction/serialization failures remain distinct from invalid-level API errors.
    - [x] null driver performs no I/O

  - [x] `packages/platform/logging/tests/Contract/LoggingModuleComposerMetadataContractTest.php`
    - [x] canonical runtime metadata
    - [x] exact module/provider identity
    - [x] requires core.foundation, core.kernel, and platform.redaction
    - [x] canonical config root/defaultsConfigPath
    - [x] no unwanted dependencies or tags

  - [x] `packages/platform/logging/tests/Contract/LoggingProviderDeclarativeDefinitionsContractTest.php`
    - [x] dual-interface provider
    - [x] register delegates declaratively
    - [x] define is single wiring source
    - [x] no closures/runtime resources in definitions
    - [x] deterministic parameter/service references
    - [x] SensitiveKeyClassifierInterface is declared as a required service reference.
    - [x] the exact factory argument order includes SensitiveKeyClassifierInterface immediately after SensitiveDataRedactorInterface.
    - [x] source and compiled definitions preserve the identical seven factory references.
    - [x] no concrete platform.redaction classifier appears in logging definitions.
    - [x] no source-only imperative graph
    - [x] no new stateful/reset tags

- Integration:
  - [x] `packages/platform/logging/tests/Integration/LoggingProviderOverridesFoundationNoopTest.php`
    - [x] Foundation alone resolves NoopLogger
    - [x] enabled stream module resolves StreamLogger
    - [x] enabled file driver resolves the same StreamLogger implementation.
    - [x] file and stream modes preserve identical provider bindings and source/compiled definition parity.
    - [x] daily filename prefix derives from the injected canonical AppTargetProviderInterface.
    - [x] file mode does not require a new service-provider binding or constructor dependency.
    - [x] later binding precedence is deterministic
    - [x] AppTargetProviderInterface is supplied through a contracts-level test double for package-local provider tests.
    - [x] factory-resolved service_name=auto matches the injected canonical app target.
    - [x] explicit service_name overrides only the service label.
    - [x] logging provider source and compiled definitions retain equivalent service references.
    - [x] explicit Foundation + Logging provider registration is tested through Foundation ContainerBuilder
    - [x] required SensitiveDataRedactorInterface is supplied through a contracts-level test double
    - [x] required SensitiveKeyClassifierInterface is supplied through a contracts-level test double.
    - [x] logging provider composition requires both security ports.
    - [x] classifier service wiring does not depend on a package-local classifier implementation.
    - [x] no Kernel module resolver or module-planning implementation is imported into package-owned tests.
    - [x] Kernel ContainerCompiler is used only by the dedicated declarative-definitions contract test to verify source/compiled definition parity; package-local provider integration tests use Foundation ContainerBuilder and contracts-level test doubles without importing Kernel compiler implementations.
    - [x] source definition semantics and later-binding precedence are verified without rebuilding Kernel module-planning logic
    - [x] module dependency closure is verified through canonical module metadata and repository-owned module-planning gates
    - [x] compiled definition compatibility remains subject to the canonical repository architecture gates
    - [x] module package presence does not auto-enable logging
    - [x] logging.format=jsonl emits the canonical JSON-lines record.
    - [x] logging.format=logfmt emits the canonical deterministic logfmt record.
    - [x] both formats use the same LoggerInterface binding and StreamLogger backend.
    - [x] format selection does not change provider ordering, lifecycle ownership, or Foundation NoopLogger fallback semantics.
    - [x] both formats preserve the same canonical service identity and nullable UoW fields.

  - [x] `packages/platform/logging/tests/Integration/CorrelationIdIsAlwaysPresentInLogsTest.php`
    - [x] before UoW: nullable correlation/uow fields
    - [x] correlation_id field is structurally present even when its value is null
    - [x] UoW absence does not force creation of an artificial correlation identifier
    - [x] same canonical field-presence invariant applies to uow_id and uow_type
    - [x] during UoW: actual validated correlation_id/uow_id/uow_type
    - [x] optional safe path_template when available
    - [x] missing/throwing context reads do not fail logging
    - [x] caller context cannot override canonical identifiers
    - [x] after ContextStore reset: previous identifiers absent
    - [x] next UoW receives only its own identifiers
    - [x] no cross-UoW cached context state
    - [x] after canonical context reset, processor-owned root identifiers are null even when caller-supplied context contains previously captured identifiers.
    - [x] admitted caller-supplied identifiers remain non-authoritative nested context values and cannot restore completed runtime context or leak into subsequent UoWs.
    - [x] context lifecycle behavior is independent of producer identity and event name.
    - [x] canonical correlation_id, uow_id, and uow_type keys are always present, but their values may be null outside an active UoW.
    - [x] active UoW values come only from current canonical context providers.
    - [x] after reset, previous correlation and UoW identifiers are not restored.
    - [x] separately captured caller identifiers cannot override canonical root fields.
    - [x] no logger-owned UoW snapshot, cache, or reset orchestration is introduced.

  - [x] `packages/platform/logging/tests/Integration/FoundationNoopResolvesWhenDisabledTest.php`
    - [x] FoundationServiceProvider alone resolves Foundation NoopLogger.
    - [x] omitting LoggingServiceProvider preserves the Foundation LoggerInterface binding.
    - [x] registering LoggingServiceProvider with logging.driver='null' resolves Foundation NoopLogger.
    - [x] null driver does not open a stream.
    - [x] null driver does not open or create a file.
    - [x] inactive file configuration never causes filesystem I/O.
    - [x] selecting the null driver does not require an existing file.path directory.
    - [x] no additional NoopLogger implementation.
    - [x] no Kernel ModulePlan, ModuleSelectionFactory, or ModuleGraphResolver implementation is imported or duplicated.
    - [x] target-local exclusion and missing selected modules are verified by existing Kernel-owned integration test deliverables.

- Gates/Arch:
  - [x] package compliance green
  - [x] package scaffold check green
  - [x] module metadata and provider contract gates green
  - [x] package index check green
  - [x] installation catalog check green
  - [x] workspace Composer sync checks green
  - [x] deptrac generation/check/analyze green
  - [x] observability naming/catalog gates green
  - [x] PHPStan and package PHPUnit green
  - [x] full existing platform/cli integration suite green with canonical default logging enabled.
  - [x] existing empty-stderr expectations are reviewed for every normal CLI host execution.
  - [x] normal CLI diagnostic logging is validated independently from command stdout.
  - [x] tests with injected noop loggers retain their existing expectations.
  - [x] ultra-early doctor and pre-container diagnostic contracts remain unchanged.
  - [x] no production CLI code changes are introduced solely to suppress default logging.
  - [x] no forbidden platform/http or platform/errors source dependency

### DoD (MUST)

- [x] Spec locked (this epic text reviewed + no open questions)
- [x] Implementation complete
- [x] Tests green
- [x] Gates/Arch green
- [x] Docs complete
- [x] Foundation LoggerInterface noop baseline remains unchanged
- [x] Enabled platform.logging provides the real fixed-STDERR stream backend and optional daily-file backend.
- [x] coretsia/skeleton installs coretsia/framework with the baseline platform-logging dependency
- [x] default kernel.boot.default_preset remains micro
- [x] all four canonical presets select platform.logging through modules, not required
- [x] target-local moduleOverrides.exclude disables real logging without removing its Composer package
- [x] missing selected platform.logging fails closed during canonical module planning
- [x] platform.redaction remains required for every active stream or file backend.
- [x] framework, skeleton, and CLI README dependency/behavior contracts are updated
- [x] normal CLI command output remains independent from diagnostic logging in either supported format.
- [x] jsonl remains the default for canonical CLI integration tests.
- [x] target-local logfmt selection changes only diagnostic serialization, not CLI stdout or exit-code semantics.
- [x] default micro create-project integration verifies active real logging
- [x] affected CLI integration tests account for independently captured logging timestamps
- [x] existing ultra-early diagnostic and pre-container output contracts remain intact
- [x] no new package installer, module-selection mechanism, logger port, or reset orchestrator is introduced
- [x] Null driver resolves Foundation NoopLogger
- [x] No duplicated LoggerInterface, NoopLogger, redactor, JSON encoder, or context store
- [x] Shared SensitiveDataRedactorInterface is mandatory
- [x] Shared SensitiveKeyClassifierInterface is mandatory for active logging sink admission
- [x] SensitiveKeyClassifier implements the contracts-level classifier interface without modifying the canonical 98-alias vocabulary
- [x] RedactionServiceProvider exposes the classifier interface through an alias to the existing shared classifier instance
- [x] DefaultSensitiveDataRedactor retains the identical injected classifier instance
- [x] LoggingServiceProvider and LoggingServiceFactory use the exact seven-argument factory dependency order
- [x] LogRecordPolicy does not duplicate the shared sensitive-key alias vocabulary or generic classification regex
- [x] Canonical dotted key_paths are classified as complete paths and individual segments
- [x] Classified sensitive key paths are rejected without raw fallback, hashing, or length disclosure
- [x] Unclassified key paths remain subject to all destination-specific safety constraints
- [x] Classifier failures cause fail-closed omission of the current record
- [x] Sensitive-key admission is enforced both before and after shared record redaction
- [x] Both jsonl and logfmt preserve identical sensitive-key admission behavior
- [x] Redaction provider source/compiled definition parity and shared service identity are verified
- [x] Existing platform.redaction unit, contract, and integration tests remain green
- [x] docs/ssot/sensitive-data-redaction.md and platform/redaction README describe both supported security ports
- [x] No second classifier, redactor, alias vocabulary, configuration surface, or runtime module is introduced
- [x] Redaction cannot be disabled
- [x] LogRecordPolicy enforces destination sink safety independently of redactor classifier matches
- [x] Deterministic jsonl serialization uses Foundation StableJsonEncoder.
- [x] Deterministic logfmt serialization preserves the same canonical admitted and redacted record schema.
- [x] LogfmtLineFormatter reuses Foundation StableJsonEncoder for nested context JSON without introducing a second generic encoder.
- [x] Correlation/uow fields are UoW-scoped, nullable, and reset-safe
- [x] No raw secrets, PII, transport payloads, or arbitrary Throwable details leak
- [x] Internal logger failure does not alter business behavior
- [x] No recursive logger-owned spans/metrics or stateful telemetry guard
- [x] No new kernel.reset service is needed
- [x] Source and compiled container wiring parity verified
- [x] No php://stderr value appears inside deterministic DI parameters or serialized service-definition values
- [x] ConfigRepositoryInterface is used for runtime logging configuration access
- [x] logging.max_record_bytes is validated and enforced as the effective per-record byte limit.
- [x] MAX_RECORD_BYTES = 65536 remains an immutable hard upper bound.
- [x] logging.service_name='auto' resolves the canonical selected app target through AppTargetProviderInterface.
- [x] Kernel ModulePlan is reused without another app-target resolver or runtime state owner.
- [x] service_name and app_target are present in every emitted canonical log record.
- [x] service identity is immutable, safe, and cannot be overridden by caller context.
- [x] logging.format accepts exactly jsonl and logfmt, with jsonl as the default.
- [x] both formats preserve canonical service identity, correlation, nullable UoW identifiers, and context.
- [x] both formats enforce mandatory shared redaction and the same serialized byte limits.
- [x] selecting logfmt requires no second logger backend, context store, or redaction implementation.
- [x] file mode automatically partitions output by UTC day without configurable rotation modes.
- [x] retention, deletion, archival, and total storage quotas remain externally owned.
- [x] stream uses fixed php://stderr and logging.stream is not a recognized configuration key.
- [x] file.path and file.name are validated through canonical declarative configuration rules and factory-owned destination checks.
- [x] filenames use the canonical app_target, configured stem, UTC date, and selected format.
- [x] service_name overrides cannot change filename app-target prefixes.
- [x] stream and file modes reuse the same StreamLogger, LogRecordPolicy, redactor, classifier, and formatter.
- [x] file writes are append-only, use non-blocking exclusive locking, and isolate missing destinations, contention, and partial writes.
- [x] daily file rollover works without restarting the logger or retaining stale file handles.
- [x] neither daily file creation nor filename formatting introduces another runtime module, logger port, or DI mechanism.
- [x] logging.driver = 'null' is a string enum value, never PHP null
- [x] Invalid configuration does not silently disable logging
- [x] Post-redaction sink schema validation is complete
- [x] Existing safe Core log context fields remain supported
- [x] Existing http-middleware-catalog logging evidence path remains valid
- [x] No Kernel-owned module planning implementation is duplicated inside package tests
- [x] Successful full writes produce exactly one deterministic LF-terminated frame in the selected jsonl or logfmt format.
- [x] Both formats preserve no-throw internal failure handling, partial-write isolation, and no raw fallback emission.
- [x] Partial write and multi-process atomicity limitations are explicitly documented
- [x] Canonical architecture and installation artifacts regenerated
- [x] Root canonical legal/security sources exist and package scaffold verification passes
- [x] README, ADR, and SSoT sections complete
- [x] No platform/http production symbol appears in package source or tests

---

### 3.30.0 Platform tracing propagation baseline (Foundation noop reuse + W3C traceparent) (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.30.0"
owner_path: "packages/platform/tracing/"

package_id: "platform/tracing"
composer: "coretsia/platform-tracing"
kind: runtime
module_id: "platform.tracing"

goal: "Надати deterministic, fail-closed W3C traceparent propagation baseline поверх уже існуючих Foundation noop tracing ports, без другого tracer/span lifecycle, exporter pipeline, runtime state або ContextStore owner; tracing infrastructure failures не змінюють primary operation outcome, а callback exceptions зберігають контрактну precedence."
provides:
- "Reuse existing Foundation NoopTracer, NoopSpan, and NoopContextPropagation as the canonical disabled/fallback implementations."
- "Deterministic, bounded W3C traceparent extract/inject through the existing ContextPropagationInterface."
- "Explicit target-local module selection and one declarative ContextPropagationInterface binding when platform.tracing is enabled."
- "No duplicate tracer, span, sampler, exporter, context store, reset service, metrics catalog, or observability registry."
- "Fail-closed malformed propagation input without raw-header disclosure or primary-exception replacement."
- "HTTP middleware integration remains owned by a later platform/http implementation."
- "Cutline impact: establishes transport-neutral W3C traceparent propagation capability; no recording spans, persisted traces, trace viewer, or automatic HTTP integration are provided by this epic."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0038-platform-tracing-w3c-propagation.md"
ssot_refs:
- "docs/ssot/tracecontext.md"
- "docs/ssot/observability.md"
- "docs/ssot/context-keys.md"
- "docs/ssot/modules-and-manifests.md"
- "docs/ssot/runtime-container-definitions.md"
- "docs/ssot/config-roots.md"
- "docs/ssot/http-middleware-catalog.md"
---

### Scope boundary (MUST)

- This epic provides transport-neutral W3C traceparent propagation, not a recording tracing backend.
- No human-viewable trace history, exported spans, or persisted telemetry are produced.
- Existing Foundation noop tracer/span implementations remain authoritative.
- This epic MUST preserve the public ContextPropagationInterface contract for subsequent recording/exporting implementations.
- A later SDK tracer MAY consume validated trace identities but MUST NOT redefine the W3C parsing policy.
- Recording and exporting capabilities belong to epic 6.30.0 and subsequent integration epics 6.31.0/6.32.0.
- No HTTP middleware, active span lifecycle, exporter, sampler, trace storage, or trace viewer is introduced here.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - Foundation canonical noop tracing implementations MUST already exist.
  - Kernel declarative runtime container definitions and target-local ModulePlan MUST exist.
  - Core tracing contracts MUST already exist without modification.
  - The reserved `tracing` config root MUST already be owned by `platform/tracing`.
  - Repository package scaffold, Composer repository synchronization, package-index generation, and Deptrac generation MUST be available.

- Existing Foundation implementations to reuse:
  - `packages/core/foundation/src/Observability/Tracing/NoopTracer.php`
  - `packages/core/foundation/src/Observability/Tracing/NoopSpan.php`
  - `packages/core/foundation/src/Observability/Tracing/NoopContextPropagation.php`

- Existing contracts to reuse:
  - `packages/core/contracts/src/Observability/Tracing/TracerPortInterface.php`
  - `packages/core/contracts/src/Observability/Tracing/SpanInterface.php`
  - `packages/core/contracts/src/Observability/Tracing/ContextPropagationInterface.php`

- Existing implementation boundaries:
  - `TracerPortInterface` is already resolvable through Foundation.
  - `ContextPropagationInterface` is already resolvable through Foundation.
  - No new tracer/span port or binding is required for noop fallback.
  - `ContextAccessorInterface` is read-only and MUST NOT become a tracing context writer.
  - `CorrelationIdProviderInterface` provides Kernel correlation identity, not W3C trace identity.
  - Foundation ContextStore remains the sole owner of its UoW-local context state and reset behavior.
  - Kernel remains the owner of UnitOfWork lifecycle, correlation identity generation, and operation-boundary reset orchestration.
  - W3C propagation MUST NOT read, write, replace, or synchronize Kernel-owned context implicitly.
  - Trace/span lifecycle MUST remain independent of Kernel UnitOfWork lifecycle.
  - This epic introduces no active span lifecycle, reset hook, ContextStore adapter, or correlation-id bridge.

- Required existing implementation paths:
  - `packages/core/contracts/src/Observability/Tracing/ContextPropagationInterface.php`
  - `packages/core/contracts/src/Observability/Tracing/TracerPortInterface.php`
  - `packages/core/contracts/src/Observability/Tracing/SpanInterface.php`
  - `packages/core/foundation/src/Observability/Tracing/NoopContextPropagation.php`
  - `packages/core/foundation/src/Observability/Tracing/NoopTracer.php`
  - `packages/core/foundation/src/Observability/Tracing/NoopSpan.php`
  - `packages/core/foundation/src/Container/Definition/ContainerDefinitionBuilder.php`

- Required config roots/keys:
  - `tracing` — existing reserved package-owned root.
  - No configurable keys are introduced in the Phase 3 propagation baseline.
  - ModulePlan controls whether W3C propagation overrides the Foundation noop binding.

- Required config roots registry:
  - `tracing` MUST already be reserved in `docs/ssot/config-roots.md` with owner `platform/tracing`.
  - This epic adds/implements package-owned defaults and rules under the existing root; it does not introduce a new root.

- Required tags:
  - N/A

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Tracing\SpanInterface`
  - `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http`
- `integrations/*`
- `core/kernel` production-source imports
- `platform/redaction`
- `platform/logging`
- `platform/metrics`
- `platform/cli`
- `platform/worker`
- `enterprise/*`
- `devtools/*`
- vendor tracing SDK implementations

#### Uses ports (API surface, NOT deps) (MUST)

- Contracts:
  - `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (existing Foundation fallback only)
  - `Coretsia\Contracts\Observability\Tracing\SpanInterface` (existing Foundation fallback only)

- Foundation:
  - `Coretsia\Foundation\Observability\Tracing\NoopContextPropagation`
  - canonical declarative container-definition APIs

- No direct ContextStore, KernelRuntime, LoggerInterface, MeterPortInterface, TracerPortInterface implementation, SamplerInterface, SpanExporterInterface, or redaction-service dependency is introduced.

### Entry points / integration points (MUST)

- Runtime DI:
  - `ContextPropagationInterface` resolves to Foundation `NoopContextPropagation` when `platform.tracing` is absent or excluded.
  - When `platform.tracing` is explicitly selected, its provider contributes one target-local `ContextPropagationInterface` binding.
  - Explicit selection of `platform.tracing` binds `ContextPropagationInterface` directly to `W3cTraceContextPropagation`.
  - No `w3c|noop` configuration selector, runtime factory, or alternate noop-mode override is introduced.
  - `TracerPortInterface` continues to resolve through the existing Foundation `NoopTracer`.
  - This epic MUST NOT override `TracerPortInterface` or introduce a second `SpanInterface` implementation.
  - Package installation MUST NOT automatically enable the tracing module.

- Propagation:
  - `extract()` accepts an explicit `array<string,string|list<string>>` carrier.
  - `inject()` accepts an explicit `array<string,string|list<string>>` carrier and normalized safe context map.
  - The existing ContextPropagationInterface defines the accepted carrier shape; platform/tracing MUST NOT redefine or widen that contract.
  - Malformed traceparent field values are handled without throwing or invoking implicit type conversions.
  - Unrelated carrier fields remain caller-owned and are preserved when the carrier satisfies the existing port contract.
  - Validation of unrelated transport fields belongs to the transport owner, not the tracing package.
  - The implementation MUST NOT implicitly read or mutate `ContextStore`.
  - Extracted W3C trace identity MUST NOT overwrite Kernel correlation_id, request_id, or uow_id.
  - No new W3C trace ID or span ID is generated by this baseline.

- HTTP:
  - `platform/http` owns `TraceContextMiddleware`.
  - No HTTP middleware production file is created or modified by this epic.
  - This epic verifies only the reusable transport-neutral propagation contract.
  - HTTP lifecycle integration requires a separate owner epic once platform/http implementation exists.

- CLI:
  - N/A.

- Kernel hooks/tags:
  - N/A. No new hook, tag, lifecycle stage, or reset registration.

- Artifacts:
  - No new artifact schema.
  - Existing Kernel source/compiled container parity MUST be preserved.

### Deliverables (MUST)

Naming and ownership lock (MUST)

- Canonical noop tracing implementations remain owned by core/foundation:
  - `Coretsia\Foundation\Observability\Tracing\NoopTracer`
  - `Coretsia\Foundation\Observability\Tracing\NoopSpan`
  - `Coretsia\Foundation\Observability\Tracing\NoopContextPropagation`

- This epic MUST NOT create:
  - `Default/DefaultTracer.php`
  - `Default/DefaultSpan.php`
  - package-local NoopTracer or NoopSpan classes
  - new tracer lifecycle, active span stack, or context storage

- The only new propagation implementation is:
  - `W3c/W3cTraceContextPropagation.php`

- No tracer/propagation service aliases or wrapper implementations may be introduced solely to rename an existing Foundation noop service.

Every CREATE and MODIFY deliverable MUST define or explicitly inherit the following implementation responsibilities without introducing duplicate owners:

- Exact file path and implementation status.
- Package/layer ownership and allowed compile-time dependencies.
- Public API, constructor dependencies, configuration reads, and DI references, when applicable.
- Context reads/writes and UoW ownership; N/A when not applicable.
- Spans and metrics; N/A with rationale where instrumentation is intentionally forbidden.
- Log/output behavior and ownership; N/A where the file does not emit records.
- Reset discipline and process-lifetime versus UoW-local state.
- Errors, warning suppression, failure isolation, and primary-exception precedence.
- Security, shared redaction, sensitive-key classification, and fail-closed behavior.
- Source/compiled parity, deterministic behavior, and explicit module selection where applicable.
- Verification by unit, contract, or integration tests corresponding to the file's responsibilities.

A deliverable MUST NOT introduce a new service, registry, validator, normalizer, serializer, lock abstraction, or state owner when the required behavior is already available through an existing Coretsia contract or a compatible Foundation implementation.

Cross-cutting requirements are inherited by all applicable deliverables and MUST NOT be reimplemented independently in multiple packages.

#### Creates

Package scaffold:
- [x] Run `composer package:new -- --layer=platform --slug=tracing --kind=runtime --description="Coretsia tracing runtime package: deterministic W3C traceparent propagation, bounded validation, and Foundation noop fallback." --keywords=coretsia,php,framework,tracing,runtime,w3c,trace-context,traceparent,context-propagation,noop,deterministic,observability` exactly once.

- [x] `packages/platform/tracing/LICENSE`
- [x] `packages/platform/tracing/NOTICE`
- [x] `packages/platform/tracing/SECURITY.md`

- [x] `packages/platform/tracing/composer.json`
  - [x] `name = coretsia/platform-tracing`.
  - [x] `type = library`.
  - [x] `license = Apache-2.0`.
  - [x] Composer metadata generation (canonical scaffold-owned):
    - [x] `description` is generated from the explicit `--description` argument.
    - [x] `keywords` is always generated as a nonempty deterministic JSON list.
    - [x] when `--keywords` is omitted, generate exactly the canonical baseline `[coretsia, php, framework]` in that order.
    - [x] explicitly empty `--keywords` values are rejected instead of triggering the baseline.
    - [x] when `--keywords` is explicitly supplied, emit only validated caller-provided values, preserving first-occurrence order after deterministic deduplication.
    - [x] explicit keywords are never merged with the canonical baseline.
    - [x] no layer, slug, kind, or package capability keywords are inferred automatically.
    - [x] `authors` is inherited from repository-root `composer.json`.
    - [x] `homepage` is inherited from repository-root `composer.json`.
    - [x] `support` is inherited from repository-root `composer.json`.
    - [x] `config.sort-packages = true` remains generated by the existing scaffold.
    - [x] `config.platform.php` is inherited from repository-root `composer.json`.
    - [x] generated metadata uses the existing ComposerJson canonical encoder.
    - [x] no tracing-specific Composer metadata template or registry is introduced.
    - [x] package-specific requirements and module dependencies are completed explicitly after scaffold creation.
    - [x] generated metadata MUST NOT require manual correction for standard package identity, author, homepage, support, keywords, description, or PHP platform fields.
  - [x] PHP requirement follows the active monorepo PHP ^8.4 policy.
  - [x] Composer requires exactly:
    - [x] `php: ^8.4`.
    - [x] `coretsia/core-contracts: ^0.11.0`.
    - [x] `coretsia/core-foundation: ^0.11.0`.
  - [x] No direct Composer requirement on `coretsia/core-kernel`; Kernel remains the application-level module composition owner.
  - [x] production PHP imports remain restricted to core/contracts and core/foundation.
  - [x] no transitive runtime dependency grants additional compile-time source imports.
  - [x] future release-line changes use the repository's canonical release synchronization tooling rather than manual dependency drift.
  - [x] MUST NOT require platform/http, logging, metrics, redaction, vendor tracing SDKs, or optional exporter packages.
  - [x] PSR-4 `Coretsia\Platform\Tracing\` → `src/`.
  - [x] PSR-4 test namespace → `tests/`.
  - [x] `extra.coretsia.kind = runtime`.
  - [x] `extra.coretsia.moduleId = platform.tracing`.
  - [x] `extra.coretsia.moduleClass = Coretsia\Platform\Tracing\Module\TracingModule`.
  - [x] `extra.coretsia.providers = [Coretsia\Platform\Tracing\Provider\TracingServiceProvider]`.
  - [x] `extra.coretsia.requires = [core.foundation]`.
  - [x] `extra.coretsia.conflicts = []`.
  - [x] `extra.coretsia.defaultsConfigPath = config/tracing.php`.
  - [x] Composer/module dependencies do not grant additional direct PHP source imports.
  - [x] Package metadata MUST pass existing package identity, manifest, and dependency gates.

Module:
- [x] `packages/platform/tracing/src/Module/TracingModule.php`
  - [x] final module-metadata class.
  - [x] public typed constants:
    - [x] `MODULE_ID = 'platform.tracing'`.
    - [x] `PACKAGE_ID = 'platform/tracing'`.
    - [x] `COMPOSER_PACKAGE = 'coretsia/platform-tracing'`.
    - [x] `KIND = 'runtime'`.
    - [x] `CONFIG_ROOT = 'tracing'`.
  - [x] exact metadata methods follow the established LoggingModule shape.
  - [x] `providers()` returns only `TracingServiceProvider::class`.
  - [x] no runtime service construction, config loading, filesystem access, module discovery, ContextStore access, spans, metrics, logging, or reset ownership.
  - [x] metadata consistency verified against composer.json and config-roots registry.

Provider:
- [x] `packages/platform/tracing/src/Provider/TracingServiceProvider.php`
  - [x] final class implementing ServiceProviderInterface and ContainerDefinitionProviderInterface.
  - [x] `register()` calls `assertDefinitionProviderRegistrationAllowed()`.
  - [x] `register()` delegates to `registerDefinitionProvider($this)`.
  - [x] `define()` is the single canonical declarative wiring source.
  - [x] binds only `ContextPropagationInterface` directly to `W3cTraceContextPropagation` through the existing `ContainerDefinitionBuilder::classService()` API.
  - [x] no `TracingServiceFactory`, `ConfigRepositoryInterface`, `ContainerValueReference`, constructor arguments, runtime selector, or service locator is required.
  - [x] MUST NOT rebind TracerPortInterface or create a new NoopTracer/NoopSpan definition.
  - [x] MUST NOT bind SamplerInterface, SpanExporterInterface, MeterPortInterface, LoggerInterface, ContextAccessorInterface, or ContextStore.
  - [x] MUST NOT add tags, lifecycle hooks, reset registrations, mutable registries, or runtime closures to declarative definitions.
  - [x] Foundation `NoopContextPropagation` remains the fallback when `platform.tracing` is absent or excluded from target-local ModulePlan.
  - [x] module selection and provider order remain Kernel-owned.
  - [x] source/compiled service-definition behavior MUST be equivalent.
  - [x] no configuration reader, propagation-mode factory, or parallel DI path is introduced.

Configuration:
- [x] `packages/platform/tracing/config/tracing.php`
  - [x] returns exactly `[]` as the package-owned `tracing` configuration subtree.
  - [x] no `propagation.mode`, enabled switch, sampler, exporter, or recording backend selector.
  - [x] no runtime state, raw header, trace ID, span ID, context snapshot, or service instance in configuration.
  - [x] no executable validator, config reader, runtime factory, or environment access.
  - [x] Verification: empty defaults and existing reserved config-root ownership.

- [x] `packages/platform/tracing/config/rules.php`
  - [x] schemaVersion = 1.
  - [x] configRoot = tracing.
  - [x] additionalKeys = false.
  - [x] keys = [].
  - [x] no configurable keys or `propagation.mode` selector in this baseline.
  - [x] reject unknown keys through the existing Kernel ConfigValidator.
  - [x] plain declarative ruleset only; no closures, callbacks, custom rule types, config readers, or executable validators.
  - [x] Kernel owns config validation and env-overlay projection.
  - [x] Verification: strict empty-root shape and invalid-key rejection.

Propagation implementation:
- [x] `packages/platform/tracing/src/W3c/W3cTraceContextPropagation.php`
  - [x] final class implementing the exact ContextPropagationInterface.
  - [x] public methods:
    - [x] `inject(array $carrier, array $context = []): array`.
    - [x] `extract(array $carrier): array`.
  - [x] stateless; no constructor dependencies are required for the baseline.
  - [x] operates only on explicit carrier/context arguments.
  - [x] MUST NOT read or write ContextStore.
  - [x] MUST NOT generate trace IDs, span IDs, correlation IDs, request IDs, or UnitOfWork IDs.
  - [x] does not construct or retain SpanInterface instances.
  - [x] HTTP carrier field names are recognized ASCII case-insensitively.
  - [x] emitted traceparent field name is lowercase.
  - [x] validates exact W3C traceparent version-00 shape:
    - [x] version `00`.
    - [x] 32 lowercase hexadecimal trace-id characters.
    - [x] 16 lowercase hexadecimal parent-id characters.
    - [x] two lowercase hexadecimal trace-flags characters.
    - [x] no all-zero trace-id.
    - [x] no all-zero parent-id.
    - [x] no invalid separators, control bytes, or trailing content.
  - [x] W3C traceparent version handling:
    - [x] version `00` requires exactly 55 ASCII bytes and exactly four canonical fields.
    - [x] version `ff` is always invalid.
    - [x] versions `01..fe` use the W3C forward-compatibility parsing policy.
    - [x] forward-version values shorter than 55 bytes are rejected.
    - [x] forward-version trace-id, parent-id, and trace-flags positions follow the W3C version-00 field layout.
    - [x] unknown forward-version fields are never decoded, interpreted, stored, or exported.
    - [x] accepted forward-version context is normalized to the supported version-00 output model during injection.
    - [x] trace-flags follow the W3C Trace Context Level 2 bitfield policy.
    - [x] the sampled flag is bit `0x01`.
    - [x] the random-trace-id flag is bit `0x02`.
    - [x] sampled is determined by `(flags & 0x01) !== 0`.
    - [x] random-trace-id is determined by `(flags & 0x02) !== 0`.
    - [x] version-00 traceparent accepts only trace_flags values `00|01|02|03`; any nonzero reserved bits fail closed and extract() returns [].
    - [x] accepted version-00 trace_flags MUST NOT be silently rewritten or normalized to a different byte value.
    - [x] accepted forward-version trace_flags retain the supported sampled and random-trace-id bits; unsupported bits are cleared only when constructing the explicitly downgraded version-00 representation.
    - [x] the strict version-00 reserved-bit rejection is an explicit Coretsia ingress policy and MUST be documented as such.
    - [x] normalized trace_flags is exactly one of `00|01|02|03`.
    - [x] injection accepts only normalized trace_flags values `00|01|02|03`.
    - [x] Injection flag-mutation policy:
      - [x] when the carrier contains one valid existing traceparent, compare its parsed trace_id, parent_id, and supported trace_flags against the requested outgoing context.
      - [x] if trace_id is unchanged, the random-trace-id bit MUST remain unchanged, regardless of parent_id changes.
      - [x] if trace_id and parent_id are unchanged, the sampled bit MUST remain unchanged.
      - [x] changing sampled while retaining the same parent_id is rejected; injection follows the existing fail-closed tracing-field removal policy.
      - [x] when sampled changes and parent_id differs, the new parent_id is explicitly caller-provided; the propagator MUST NOT generate or claim ownership of that identity.
      - [x] the propagator MUST NOT make sampling decisions.
      - [x] no provenance guarantee can be inferred for context transferred between independent carriers; the caller owns continuity and parent identity outside the information available to inject().
      - [x] a future recording tracer owns the generation of local span identity and sampling decisions.
    - [x] unknown/reserved bits beyond `0x03` are cleared only during an accepted forward-version downgrade; an existing version-00 traceparent MUST NOT be rewritten solely to clear reserved bits.
    - [x] the propagator MUST NOT infer whether an incoming trace_id was actually generated randomly.
    - [x] identical-header pass-through is permitted only when the existing traceparent value is canonical and byte-identical to the required outgoing value.
    - [x] all rules are byte-oriented, bounded, locale-independent, and deterministic.
  - [x] extracted context uses only:
    - [x] `trace_id`.
    - [x] `parent_id`.
    - [x] `trace_flags`.
  - [x] extracted values are canonical strings, never raw header maps.
  - [x] Normalized context shape:
    - [x] the result contains exactly `trace_id`, `parent_id`, and `trace_flags` when extraction succeeds.
    - [x] extracted context contains no header version, tracestate, baggage, or arbitrary extensions.
    - [x] validation is independent of PHP associative-array insertion order.
    - [x] injection accepts the three required keys regardless of their insertion order.
    - [x] missing, extra, non-string, or structurally invalid context entries reject injection deterministically.
    - [x] no implicit conversion from integer, float, bool, object, or Stringable into trace-id components.
    - [x] no arbitrary context key is forwarded into traceparent.
    - [x] parent_id represents the upstream caller identity, not a newly recorded local span.
    - [x] no local span identity is allocated or published by this implementation.
    - [x] absent incoming context does not cause synthetic trace identity generation.
  - [x] Forward compatibility with 6.30.0:
    - [x] `trace_id` is a validated 128-bit W3C trace identity encoded as 32 lowercase hexadecimal characters.
    - [x] `parent_id` is the validated upstream W3C parent identity encoded as 16 lowercase hexadecimal characters.
    - [x] `trace_flags` preserves the canonical W3C sampled and random-trace-id bits: `00|01|02|03`.
    - [x] a future recording tracer MUST preserve the random-trace-id bit when continuing an extracted trace_id.
    - [x] extracted identifiers are immutable values returned to the caller, not references to mutable tracing runtime state.
    - [x] the propagation implementation does not infer or allocate the current local span identity.
    - [x] a future recording tracer owns local span-id generation and active-span lifecycle.
    - [x] a future recording tracer MAY reuse the extracted trace_id while creating its own local span_id.
    - [x] incoming parent_id MUST NOT be confused with the local span_id.
    - [x] the format of this extracted context MUST NOT be silently changed by 6.30.0.
    - [x] any future richer trace-context representation must be introduced through an explicitly compatible contract decision.
  - [x] no arbitrary carrier fields are copied into the extracted context.
  - [x] Existing traceparent validation:
    - [x] extract() rejects duplicate, conflicting, malformed, oversized, or invalid traceparent values deterministically.
    - [x] inject() validates an existing traceparent before evaluating continuity or preserving tracestate.
    - [x] zero existing traceparent fields permits injection of an independently validated normalized context.
    - [x] exactly one existing traceparent field is accepted as either a string or a list containing exactly one string.
    - [x] string and single-string-list representations use the same canonical traceparent parser.
    - [x] duplicate traceparent names, including ASCII case variants, invalidate existing carrier trace context.
    - [x] malformed, oversized, invalid-version, invalid-identifier, invalid-flags, or invalid-list traceparent input invalidates existing carrier trace context.
    - [x] invalid existing traceparent prevents injection of a new traceparent into that carrier.
    - [x] invalid existing traceparent and associated tracestate fields are removed from the returned carrier.
    - [x] unrelated contract-valid carrier fields remain unchanged.
    - [x] valid existing traceparent follows the documented identity and flag-mutation policy.
    - [x] when the existing traceparent value is byte-identical to the required outgoing value, existing tracestate remains unchanged.
    - [x] otherwise existing tracestate is removed under the documented privacy-preserving mutation policy.
    - [x] no second parser, context registry, carrier validator, or transport normalization layer is introduced.
  - [x] invalid extraction returns [].
  - [x] valid injection emits one canonical traceparent representation.
  - [x] invalid injection context emits no new propagation fields and MUST NOT forward an unvalidated pre-existing traceparent/tracestate value.
  - [x] Missing-context injection:
    - [x] the default empty context `[]` is not a valid normalized tracing context.
    - [x] empty context produces no new traceparent.
    - [x] pre-existing traceparent and tracestate are removed from the result.
    - [x] all unrelated carrier entries are preserved.
    - [x] inject([], []) returns [].
    - [x] this behavior does not apply to Foundation NoopContextPropagation, which preserves its existing pass-through contract.
  - [x] unrelated carrier fields remain unchanged.
  - [x] Carrier resource policy:
    - [x] Extraction carrier policy:
      - [x] maximum accepted carrier entry count is 128.
      - [x] when carrier entry count exceeds 128, extract() returns [] without inspecting individual header values.
      - [x] invalid or non-string carrier keys fail extraction deterministically.
      - [x] the entry count is based on the received PHP array, not a reconstructed header collection.
    - [x] Injection carrier policy:
      - [x] carriers with at most 128 entries follow the normal validation and mutation rules.
      - [x] when carrier entry count exceeds 128, injection emits no new traceparent.
      - [x] over-limit injection removes existing traceparent and tracestate fields using ASCII case-insensitive key comparison.
      - [x] all unrelated carrier entries remain unchanged.
      - [x] over-limit injection performs only a key-oriented removal pass; unrelated values are never inspected, normalized, reflected, serialized, or converted.
      - [x] over-limit injection never returns a partially constructed tracing context.
      - [x] no exception escapes because of the carrier size.
      - [x] this over-limit rule is an explicit fail-closed exception to normal propagation and is covered by deterministic tests.
    - [x] maximum traceparent candidate length: 512 bytes.
    - [x] version-00 traceparent length must be exactly 55 bytes.
    - [x] a traceparent carrier value may be a string or a list containing exactly one string.
    - [x] an empty or multi-value traceparent list is invalid.
    - [x] duplicate traceparent names, including ASCII case variants, are invalid.
    - [x] malformed or oversized traceparent input returns [] on extraction.
    - [x] Carrier contract boundary:
      - [x] accepted carrier keys are strings.
      - [x] Header-name matching:
        - [x] recognize only exact ASCII case-insensitive names `traceparent` and `tracestate`.
        - [x] compare header-name byte length before case-insensitive matching.
        - [x] `traceparent` name length is exactly 11 ASCII bytes.
        - [x] `tracestate` name length is exactly 10 ASCII bytes.
        - [x] unrelated header names MUST NOT be lowercased, copied into temporary normalized maps, or scanned through an unbounded normalization pipeline.
        - [x] long unrelated carrier keys remain caller-owned and unchanged.
        - [x] matching is locale-independent and does not invoke Unicode normalization.
        - [x] extract() and inject() MUST use equivalent tracing-header name matching semantics.
      - [x] accepted carrier values are strings or lists of strings.
      - [x] malformed traceparent values are rejected deterministically without implicit conversions, reflection, serialization, or Stringable::__toString().
      - [x] unrelated fields within the accepted carrier shape are preserved byte-for-byte and in their existing PHP value structure.
      - [x] unrelated fields are not semantically parsed, normalized, classified, or redacted by the tracing package.
      - [x] malformed unrelated fields outside the ContextPropagationInterface carrier contract are transport-owner validation failures, not a new tracing normalization responsibility.
      - [x] no general-purpose header validator, carrier serializer, or second transport normalization layer is introduced.
    - [x] outbound tracing fields are subject to the documented fail-closed mutation policy.
    - [x] numeric limits are package-owned fixed policy constants, not independently configurable runtime limits.
    - [x] the exact policy and failure precedence are documented once in tracecontext.md.
  - [x] tracestate policy:
    - [x] tracestate is not parsed into the normalized extracted context.
    - [x] this epic does not create, interpret, enrich, or serialize tracestate entries.
    - [x] when an existing valid traceparent is propagated byte-identically, existing caller-owned tracestate MUST remain unchanged.
    - [x] when traceparent is replaced or downgraded, existing tracestate handling follows the explicitly documented privacy-preserving removal policy.
    - [x] invalid traceparent MUST NOT authorize forwarding associated tracestate as a newly accepted trace context.
    - [x] unrelated carrier fields remain caller-owned and unchanged.
    - [x] transport-layer validation of unrelated headers remains the responsibility of the transport owner.
    - [x] full tracestate parsing, mutation, and vendor-specific semantics remain outside this epic.
  - [x] no baggage support.
  - [x] no raw header, opaque vendor value, Authorization, Cookie, token, credential, SQL, payload, or customer data is returned in context.
  - [x] no exceptions caused by absent/malformed transport data or invalid carrier contents.
  - [x] no output, logger, spans, metrics, exporter calls, or reset registrations.
  - [x] no mutable process-lifetime or UnitOfWork-local state.
  - [x] no duplicate generic normalization, serialization, redaction, or context storage layer.
  - [x] deterministic results for identical inputs.

Documentation:
- [x] `packages/platform/tracing/README.md`
  - [x] package identity, namespace, module id, dependencies, and exact supported public ports.
  - [x] explain reuse of Foundation noop tracer/span/propagation.
  - [x] explain that this package does not implement an active recording tracer.
  - [x] provide an exact capability matrix:
    - [x] Foundation noop tracer: supported.
    - [x] W3C traceparent extraction/injection: supported when selected.
    - [x] automatic trace/span ID creation: not supported.
    - [x] recording spans and durations: not supported.
    - [x] span sampling/exporting: not supported.
    - [x] trace files, telemetry endpoint, or persistent storage: not supported.
    - [x] Jaeger/Grafana Tempo integration: not supported.
    - [x] automatic HTTP middleware integration: not supported.
  - [x] explain that users cannot view trace history produced by this package.
  - [x] include synthetic extract/inject examples with expected return values.
  - [x] document the accepted carrier PHP shape defined by ContextPropagationInterface.
  - [x] distinguish malformed traceparent handling from transport-owned validation of unrelated carrier fields.
  - [x] demonstrate preservation of unrelated contract-valid carrier fields without implicit type conversion.
  - [x] explain that logging correlation_id and W3C trace_id are separate identities.
  - [x] explain the future recording/exporting integration boundary without claiming that it is implemented.
  - [x] document explicit module selection and target-local provider binding.
  - [x] document direct `ContextPropagationInterface → W3cTraceContextPropagation` binding when `platform.tracing` is selected.
  - [x] document Foundation `NoopContextPropagation` fallback when the module is absent and the absence of a runtime propagation-mode selector.
  - [x] document W3C sampled and random-trace-id flags with synthetic `00|01|02|03` examples.
  - [x] explain that version-00 headers with nonzero reserved trace-flags are rejected under the Coretsia strict ingress policy.
  - [x] explain that forward-version downgrade follows a separate documented compatibility policy.
  - [x] document same-carrier sampled/random flag mutation restrictions and caller-owned cross-carrier identity continuity.
  - [x] document traceparent-only scope and excluded tracestate/baggage.
  - [x] explicitly document that extract(incomingCarrier) followed by inject(outboundCarrier, context) does not transfer tracestate between separate carriers.
  - [x] explain that tracestate can remain unchanged only when it is already present in the carrier passed to inject() and traceparent remains byte-identical.
  - [x] full cross-carrier tracestate propagation requires a separately approved transport/security boundary; this package MUST NOT introduce an implicit raw-header store.
  - [x] document carrier/context examples using synthetic IDs only.
  - [x] document the difference between W3C trace_id and Kernel correlation_id/uow_id.
  - [x] document that full HTTP middleware integration belongs to platform/http.
  - [x] include canonical `## Observability`, `## Errors`, and `## Security / Redaction` sections.
  - [x] the Security / Redaction section documents raw traceparent/tracestate handling, carrier validation, diagnostic disclosure restrictions, and fail-closed behavior.
  - [x] refer to the unmodified canonical SECURITY.md for vulnerability reporting.
  - [x] no misleading claims about exporter pipelines, sampled spans, tracing metrics, or automatic cross-process trace continuity.
  - [x] references docs/ssot/tracecontext.md as normative policy.

- [x] `docs/adr/ADR-0038-platform-tracing-w3c-propagation.md`
  - [x] ADR version 1 and platform/tracing ownership.
  - [x] record reuse of Foundation noop ports instead of new DefaultTracer/DefaultSpan.
  - [x] record the only new runtime binding: `ContextPropagationInterface → W3cTraceContextPropagation` when the module is selected.
  - [x] record rejection of a redundant `w3c|noop` configuration selector and `TracingServiceFactory` in favor of ModulePlan selection.
  - [x] record explicit module selection and source/compiled parity.
  - [x] record traceparent-only baseline and its limitations.
  - [x] record no ContextStore ownership, trace-id generation, sampler, exporter, spans, metrics, or reset service.
  - [x] record security/failure isolation and future platform/http ownership.
  - [x] record why the full tracestate policy is deferred.
  - [x] explicitly evaluate why Foundation noop implementations are reused.
  - [x] document the value of transport-neutral propagation without an active recording backend.
  - [x] record the limitation that no production consumer exists until a transport owner integrates the port.
  - [x] record that no trace viewer or persisted tracing output is delivered.
  - [x] distinguish W3C traceparent-only support from full W3C tracecontext support.
  - [x] define pass-through behavior and the conditions under which tracestate can be removed.
  - [x] record why local span identity and trace generation are deferred.

- [x] `docs/ssot/tracecontext.md`
  - [x] canonical SSoT header, ssotVersion = 1, owner = platform/tracing.
  - [x] exact scope and non-goals.
  - [x] exact ContextPropagationInterface methods and carrier/context shapes.
  - [x] exact three-key extracted context schema.
  - [x] exact PHP types, allowed fields, required keys, and insertion-order independence.
  - [x] distinguish the existing typed carrier contract from defensive malformed traceparent parsing.
  - [x] define transport-owner responsibility for unrelated out-of-contract carrier fields.
  - [x] require preservation of contract-valid unrelated carrier fields without semantic inspection.
  - [x] forbid package-local general-purpose carrier validation or normalization.
  - [x] exact version-00 and forward-version parsing behavior.
  - [x] exact trace-flags sampled-bit interpretation and canonical output policy.
  - [x] explicitly lock the W3C Trace Context Level 2 sampled and random-trace-id bit semantics.
  - [x] define supported bits `0x01|0x02`, normalization mask `0x03`, and normalized values `00|01|02|03`.
  - [x] require random-trace-id flag preservation when the same trace_id is propagated.
  - [x] forbid inferring actual trace-id entropy or randomness from the flag.
  - [x] define reserved-bit clearing and associated traceparent/tracestate mutation behavior.
  - [x] define the strict version-00 reserved-bit ingress rejection policy.
  - [x] distinguish rejected version-00 reserved flags from accepted forward-version downgrades.
  - [x] forbid implicit version-00 flag rewriting without an authorized W3C mutation.
  - [x] specify same-carrier trace_id, parent_id, sampled-bit, and random-trace-id mutation checks.
  - [x] explicitly document the limitation that independent-carrier context provenance cannot be established by this stateless propagator.
  - [x] define the exact fail-closed result when a prohibited flag mutation is detected.
  - [x] exact distinction between upstream parent-id and a locally generated span-id.
  - [x] exact pass-through versus mutation behavior for traceparent and tracestate.
  - [x] exact handling of duplicate headers, list-valued carriers, and conflicting case-insensitive names.
  - [x] explicit policy for unrelated caller-owned carrier fields.
  - [x] explicit statement that Foundation noop propagation is not a sanitization boundary.
  - [x] document the direct W3C binding for a selected module, the Foundation noop fallback for an unselected module, and absence of a configurable propagation-mode selector.
  - [x] explicit statement that this SSoT does not define recording, exporting, sampling, or persisted trace history.
  - [x] exact traceparent version, hexadecimal, separator, nonzero-ID, and flags validation.
  - [x] exact behavior for unknown/forward versions.
  - [x] exact duplicate, case-insensitive, missing, malformed, invalid-type, and oversized header behavior.
  - [x] exact injection behavior when a carrier already contains invalid or duplicate traceparent fields.
  - [x] exact single-string and single-string-list traceparent equivalence.
  - [x] exact tracing-field removal behavior for invalid existing carrier context.
  - [x] exact bounded ASCII header-name matching policy for traceparent and tracestate.
  - [x] prohibit unbounded normalization of unrelated carrier header names.
  - [x] exact bounded carrier/list handling and deterministic precedence.
  - [x] document the exact package-owned carrier entry and traceparent byte limits.
  - [x] define failure precedence for oversized carrier, duplicates, malformed values, invalid version, and invalid identifiers.
  - [x] distinguish tracing-field validation from validation of unrelated caller-owned carrier fields.
  - [x] exact invalid extraction and invalid injection behavior.
  - [x] exact treatment of pre-existing traceparent/tracestate on injection.
  - [x] explicitly distinguish same-carrier tracestate preservation from cross-carrier propagation.
  - [x] document that the three-field normalized context cannot independently transport opaque tracestate values.
  - [x] do not claim complete W3C Trace Context forwarding compliance for the traceparent-only baseline.
  - [x] leave full tracestate forwarding, validation, and privacy policy to an explicitly approved future transport integration.
  - [x] no raw-header disclosure or unchecked opaque vendor state.
  - [x] explicit non-support of tracestate and baggage in this baseline.
  - [x] explicit separation of W3C trace identity from Kernel correlation/UoW identity.
  - [x] no implicit ContextStore write or active-span mutation.
  - [x] no hidden runtime state, reset tag, span, metric, or logging side effect.
  - [x] no-throw propagation failures without suppressing primary business exceptions.
  - [x] deterministic canonical fixtures, invalid-input matrix, and exact safety acceptance criteria.
  - [x] references W3C Trace Context specification and existing Coretsia observability/security policies.

#### Modifies

- [x] `tools/build/new-package.php`
  - [x] Extend the existing NewPackage generator; MUST NOT create a second package generator, Composer metadata registry, or template pipeline.
  - [x] Preserve the existing required CLI arguments:
    - [x] `--layer`
    - [x] `--slug`
    - [x] `--kind`
  - [x] Preserve the existing optional CLI argument:
    - [x] `--repo-root`
  - [x] When --repo-root is omitted, preserve the existing RepositoryContext discovery behavior.
  - [x] New metadata arguments MUST NOT change repository-root resolution.
  - [x] Add optional CLI arguments:
    - [x] `--description=<text>`
    - [x] `--keywords=<comma-separated-tokens>`
  - [x] Metadata argument parsing:
    - [x] Preserve raw values of --description and --keywords until their validation is complete.
    - [x] Validate raw metadata values before applying any whitespace normalization.
    - [x] Apply deterministic fallback only when --description or --keywords is omitted.
    - [x] Reject explicitly empty --description and --keywords values consistently.
    - [x] Do not trim or normalize raw metadata values to make invalid input acceptable.
    - [x] Reject leading/trailing whitespace in --description.
    - [x] Reject leading/trailing whitespace in --keywords and individual keyword tokens.
    - [x] Reject empty keyword entries and embedded control bytes.
    - [x] Preserve existing trim/parse behavior for --layer, --slug, --kind, and --repo-root.
    - [x] Support both existing CLI forms: --key=value and --key value.
    - [x] Do not introduce a second CLI argument parser or metadata normalization pipeline.
    - [x] Invalid metadata MUST fail through CORETSIA_NEW_PACKAGE_FAILED before package commit.
  - [x] Preserve backward compatibility with existing package:new invocations.
  - [x] Description:
    - [x] explicit description is used verbatim after validation.
    - [x] absent description produces a deterministic neutral description derived from layer, slug, and kind.
    - [x] reject empty, multiline, control-byte, surrounding-whitespace, or oversized descriptions.
    - [x] maximum accepted description length: 240 bytes.
    - [x] do not infer implementation features or capabilities from package names.
  - [x] Keywords:
    - [x] `--keywords` remains an optional CLI argument.
    - [x] the generated composer.json MUST always contain a nonempty `keywords` property.
    - [x] when `--keywords` is omitted, generate exactly `[coretsia, php, framework]` in canonical order.
    - [x] explicitly empty `--keywords` values MUST fail validation rather than activate fallback.
    - [x] when supplied, parse comma-separated keyword tokens in caller-provided order.
    - [x] allowed grammar: lowercase ASCII kebab-case tokens with single hyphen separators.
    - [x] numeric components are allowed, including `psr-11`, `data-transfer-object`, and `unit-of-work`.
    - [x] reject empty or malformed keyword tokens.
    - [x] reject repeated hyphens, leading/trailing hyphens, underscores, uppercase letters, and invalid separators.
    - [x] reject leading/trailing whitespace and control bytes.
    - [x] reject whitespace surrounding individual keyword tokens without trimming or normalization.
    - [x] remove duplicate supplied values while preserving first occurrence.
    - [x] maximum resulting keyword count: 24.
    - [x] explicit keyword values MUST replace, not merge with, the canonical baseline.
    - [x] no layer, slug, kind, or implementation capability keywords are inferred automatically.
    - [x] root Composer keywords MUST NOT be inherited.
    - [x] output remains a deterministic JSON list.
    - [x] no package-specific keyword registry, classifier, or automatic keyword discovery is introduced.
  - [x] Shared metadata:
    - [x] read repository-root composer.json through the existing ComposerJson utility.
    - [x] inherit only canonical `authors`, `homepage`, `support`, and `config.platform.php`.
    - [x] reject absent or structurally invalid required shared metadata before committing a new package.
    - [x] do not inherit root package name, type, require, require-dev, autoload, scripts, or distribution-specific metadata.
    - [x] preserve generated package-local name, type, PSR-4, and extra.coretsia identity.
    - [x] preserve `config.sort-packages = true`.
  - [x] Composer dependency ownership:
    - [x] keep generated `require.php = ^8.4`.
    - [x] do not infer extensions, PSR dependencies, package dependencies, or module requirements from layer/slug/kind.
    - [x] do not create implicit Kernel or platform package dependencies.
    - [x] package-specific requirements remain explicit implementation deliverables.
  - [x] Serialization:
    - [x] reuse ComposerJson::encodeCanonical().
    - [x] preserve UTF-8, LF-only, final newline, 2-space indentation, and deterministic key/list ordering.
    - [x] no new JSON encoder, serializer, filesystem writer, or manifest normalization pipeline.
  - [x] Failure isolation:
    - [x] validate new arguments and inherited metadata before final package commit.
    - [x] preserve atomic staging/commit behavior.
    - [x] on invalid arguments or metadata, fail through the existing CORETSIA_NEW_PACKAGE_FAILED boundary.
    - [x] no partially created package directory or leaked staging state.
    - [x] no raw malformed input or sensitive metadata values in diagnostics.
  - [x] Context/UoW: N/A; repository tooling only.
  - [x] Spans/metrics/logs: N/A; preserve existing deterministic CLI output behavior.
  - [x] Reset: N/A; no process-lifetime state or runtime service registration.
  - [x] Verification: existing atomic-creation tests plus new metadata-generation cases MUST pass.

- [x] `tools/tests/Integration/NewPackageAtomicCreationTest.php`
  - [x] Extend the existing integration test class; do not create a second package-new test harness.
  - [x] Update the sandbox root composer.json fixture with canonical authors, homepage, support, and config.platform.php metadata.
  - [x] Test existing required-only CLI invocation remains successful.
  - [x] Test deterministic neutral default description when --description is omitted.
  - [x] Test that omitting --keywords generates exactly `[coretsia, php, framework]`.
  - [x] Test that the canonical baseline is generated identically for library and runtime packages.
  - [x] Test that default keywords do not inherit root Composer keywords or infer layer, slug, kind, or implementation capabilities.
  - [x] Test that explicitly empty --description and --keywords values are rejected in both CLI argument forms.
  - [x] Test that explicit keywords replace the baseline without implicit merging.
  - [x] Test valid hyphenated keywords, including `psr-11`, `data-transfer-object`, and `unit-of-work`.
  - [x] Test that malformed hyphenation and whitespace are rejected without normalization.
  - [x] Test exact tracing keyword order, deduplication, and absence of unsupported capabilities.
  - [x] Test explicit description and keywords are correctly generated.
  - [x] Test inherited authors, homepage, support, and PHP platform match the sandbox root composer metadata.
  - [x] Test generated package name, type, autoload, autoload-dev, and extra.coretsia remain correct.
  - [x] Test library and runtime package generation independently.
  - [x] Test keyword deduplication and stable ordering.
  - [x] Test empty, malformed, and oversized metadata values are rejected.
  - [x] Test repeated --description or --keywords CLI options are rejected.
  - [x] Test duplicate keyword values within a single --keywords argument are deduplicated while preserving first-occurrence order.
  - [x] Test that explicitly supplied keywords appear exactly once after deduplication and no additional keywords are generated.
  - [x] Test leading/trailing whitespace in raw --description is rejected.
  - [x] Test leading/trailing whitespace in raw --keywords and individual tokens is rejected.
  - [x] Test both --key=value and --key value metadata argument forms.
  - [x] Test invalid raw metadata does not create a package or leave staging artifacts.
  - [x] Test missing or malformed canonical root metadata fails without final package creation.
  - [x] Test no partially committed package or staging directory remains on failure.
  - [x] Test generated composer.json retains LF-only encoding, final newline, and canonical JSON formatting.
  - [x] Test package-scaffold --check remains green.
  - [x] Preserve the existing missing-scaffold, existing-target, and atomic-failure regression tests.
  - [x] Context/UoW: N/A.
  - [x] Spans/metrics/logs: N/A; test only process output and generator results.
  - [x] Reset: N/A; test fixture isolation and cleanup remain canonical.
  - [x] Security: no secrets or raw invalid metadata emitted in failed command diagnostics.

- [x] `docs/architecture/PACKAGING.md`
  - [x] Extend section `9) Composer package metadata (MUST)` with the canonical new-package generation policy.
  - [x] State that new layered packages inherit shared authors, homepage, support, and PHP platform metadata from repository-root composer.json.
  - [x] State that the root composer.json is the development workspace metadata source for these fields, not a publishable package.
  - [x] Document deterministic neutral default description generation.
  - [x] Document that generated Composer keywords are always present as a nonempty deterministic JSON list.
  - [x] Document the canonical fallback `[coretsia, php, framework]` when --keywords is omitted.
  - [x] Document that explicitly empty --description and --keywords values fail validation.
  - [x] Document that explicitly supplied keywords replace the baseline without implicit merging.
  - [x] Document that no layer, slug, kind, or implementation capability keywords are inferred.
  - [x] Document lowercase ASCII kebab-case grammar, including numeric components and single hyphen separators.
  - [x] Document strict raw metadata validation without whitespace normalization.
  - [x] Document validated keyword ordering, deduplication, and resource limits.
  - [x] Document optional --description and --keywords arguments.
  - [x] Document validation and atomic failure behavior for invalid metadata.
  - [x] State that package-specific Composer requirements, extensions, PSR dependencies, and extra.coretsia.requires MUST remain explicitly defined.
  - [x] Preserve the existing package identity, namespace, legal-file, module, and config-root rules.
  - [x] No second metadata registry, configuration root, manifest owner, or package discovery mechanism.
  - [x] Context/UoW, spans, metrics, logs, reset: N/A; documentation-only change.
  - [x] Verification: generated package metadata and package-scaffold contract tests remain green.

- [x] `docs/architecture/DEPENDENCIES.md`
  - [x] add exactly one materialized package row for `platform/tracing`.
  - [x] direct production source dependencies: `core/contracts, core/foundation`.
  - [x] preserve strcmp package-id row ordering.
  - [x] no speculative platform/http, redaction, logging, metrics, or vendor SDK source dependencies.
  - [x] generated Deptrac policy MUST consume this row without a parallel allowlist.

- [x] `composer.json`
  - [x] register `coretsia/platform-tracing: 0.11.x-dev` under require-dev for the current monorepo workspace baseline.
  - [x] synchronize the managed path repository entry using existing repository tooling.
  - [x] MUST NOT hand-maintain a competing workspace repository catalog.
  - [x] preserve deterministic repository ordering and existing package references.

- [x] `docs/ssot/INDEX.md`
  - [x] register `docs/ssot/tracecontext.md` exactly once.
  - [x] use canonical title, owner, ssotVersion, and scope formatting.
  - [x] preserve strcmp ordering and no-forward-reference policy.

- [x] `docs/adr/INDEX.md`
  - [x] register `docs/adr/ADR-0038-platform-tracing-w3c-propagation.md` exactly once.
  - [x] use canonical title, owner, adrVersion, and scope formatting.
  - [x] preserve strcmp ordering and no-forward-reference policy.

Generated artifacts:
- [x] `tools/testing/package-index.php`
  - [x] regenerate through the existing package-index generator.
  - [x] MUST NOT edit the generated table manually.

- [x] `tools/testing/deptrac.yaml`
  - [x] regenerate through the existing Deptrac generator.
  - [x] MUST NOT add manually maintained exceptions or duplicated layer policy.

- [x] `packages/core/kernel/resources/packaging/installation-catalog.php`
  - [x] regenerate through the existing installation-catalog generator.
  - [x] preserve generated schema and existing Kernel ownership.
  - [x] MUST NOT introduce a tracing-owned installation catalog.

Generation and verification:
- [x] Generate workspace Composer repositories using the canonical sync tool.
- [x] Generate the package index using `composer arch:package-index:generate`.
- [x] Generate Deptrac configuration using `composer arch:deptrac:generate`.
- [x] Verify that the existing `tools/testing/deptrac.allowlist.yaml` remains unchanged and does not exclude production tracing sources.
- [x] Do not regenerate or modify the canonical Deptrac allowlist solely because `platform/tracing` is added.
- [x] Generate the installation catalog using `composer arch:installation-catalog:generate`.
- [x] Preserve the distinction between missing Composer bootstrap autoload and a genuine installation-catalog generation failure.
- [x] Do not claim installation-catalog verification is green until the required bootstrap environment exists and the check succeeds.
- [x] Do not edit generator-owned outputs by hand.

#### Configuration (keys + defaults)

- [x] Files:
  - [x] `packages/platform/tracing/config/tracing.php`
  - [x] `packages/platform/tracing/config/rules.php`
- [x] Root:
  - [x] `tracing` — existing reserved package-owned root.
- [x] Baseline defaults:
  - [x] `config/tracing.php` returns exactly `[]`.
  - [x] `config/rules.php` defines `schemaVersion = 1`, `configRoot = tracing`, `additionalKeys = false`, and `keys = []`.
- [x] No keys for:
  - [x] `propagation.mode` or any `w3c|noop` selector.
  - [x] enabled/disabled module switches.
  - [x] sampling, exporter, or tracer runtime selection.
  - [x] ContextStore, trace/span identity persistence, or span buffers.
  - [x] metrics/export instrumentation.
- [x] Rules:
  - [x] unknown config keys are rejected by the existing Kernel ConfigValidator.
  - [x] no custom validator, executable rules, or runtime configuration factory.
  - [x] no automatic modification of Phase A appEnv, preset, or ModulePlan.

#### Wiring / DI tags (when applicable)

- Tags introduced:
  - N/A.
- [x] ServiceProvider wiring:
  - [x] selected `platform.tracing` binds `ContextPropagationInterface` directly to `W3cTraceContextPropagation` through the existing declarative `classService()` API.
  - [x] unselected `platform.tracing` retains Foundation `ContextPropagationInterface → NoopContextPropagation`.
  - [x] no `TracingServiceFactory`, `ConfigRepositoryInterface`, `ContainerValueReference`, runtime selector, or alternate DI path.
  - [x] Foundation NoopContextPropagation::extract() returns [].
  - [x] Foundation NoopContextPropagation::inject() returns the original carrier unchanged.
  - [x] Foundation noop fallback is not a header sanitizer and MUST NOT be described as removing malformed trace headers.
  - [x] no transport parsing, validation, or security normalization is performed by the Foundation noop implementation.
  - [x] Foundation `TracerPortInterface` remains unchanged.
  - [x] Foundation `NoopSpan` remains unchanged.
  - [x] source/compiled binding parity MUST hold.
  - [x] target-local ModulePlan selection controls whether provider participates.
  - [x] no new tags, aliases, reset services, or alternate container paths.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads:
  - N/A. No implicit ContextAccessorInterface read.
- Context writes:
  - N/A. No ContextStore write.
- [x] Trace context:
  - [x] extracted W3C IDs are local values returned from extract().
  - [x] W3C trace_id is not Kernel correlation_id.
  - [x] W3C parent_id is not Kernel uow_id.
  - [x] no implicit correlation identity conversion.
  - [x] no synthetic context generation when source context is absent.
- [x] Reset discipline:
  - [x] W3cTraceContextPropagation is stateless.
  - [x] no request-local or UnitOfWork-local mutable state.
  - [x] no active span registry or stack.
  - [x] no kernel.reset service registration.
  - [x] no cross-UnitOfWork state retention.
  - [x] repeated calls are deterministic and independent.

#### Observability (policy-compliant)

- [x] Spans:
  - N/A.
  - [x] existing Foundation NoopTracer emits no recording spans.
  - [x] propagation helpers MUST NOT create a tracing.noop span.
- [x] Metrics:
  - N/A.
  - [x] no tracing.export_total.
  - [x] no tracing.export_duration_ms.
  - [x] no exporter lifecycle is introduced.
  - [x] no new metric name or label is introduced.
  - [x] trace_id, span_id, parent_id, and correlation_id MUST NOT become metric labels.
- [x] Logs:
  - N/A.
  - [x] propagation helpers emit no log records.
  - [x] raw traceparent, tracestate, baggage, and carrier contents MUST NOT reach logging sinks.
  - [x] no warn-only logger dependency is introduced.
- [x] Observability failures:
  - [x] no recursive self-instrumentation.
  - [x] no observability failure may replace a primary operation result or exception.

#### Errors

- New exception classes:
  - N/A.
- [x] Existing failure policy:
  - [x] No configuration-selector exception is introduced by this baseline.
  - [x] No exception for absent, malformed, conflicting, or oversized propagation carrier data.
  - [x] No propagation failure may replace a primary application exception.
- [x] Propagation:
  - [x] absent, malformed, conflicting, oversized, or invalid traceparent field data MUST NOT throw.
  - [x] invalid traceparent field types MUST NOT trigger implicit conversions or application exceptions.
  - [x] the existing ContextPropagationInterface carrier shape remains a caller precondition.
  - [x] unrelated malformed transport fields outside that contract are not interpreted or normalized by platform/tracing.
  - [x] no propagation parsing failure may replace a primary application exception.
  - [x] invalid extraction returns an empty safe context.
  - [x] W3cTraceContextPropagation invalid injection follows the fail-closed traceparent/tracestate mutation policy without introducing unvalidated tracing fields.
  - [x] Foundation noop fallback for an unselected module preserves its existing pass-through contract and is not subject to W3C sanitizer behavior.
  - [x] no raw input value, raw header, source path, or previous Throwable enters diagnostics.
  - [x] programming/type-boundary failures are not represented as transport parsing results.
- [x] Existing tracer contract:
  - [x] inSpan() MUST preserve and rethrow the original callback Throwable.
  - [x] no-throw tracing infrastructure MUST NOT swallow primary application exceptions.
  - [x] callback exception precedence remains governed by TracerPortInterface.

#### Security / Redaction

- [x] No raw trace headers, opaque vendor state, Authorization, Cookie, tokens, credentials, session ids, SQL, payloads, or private customer data may enter exported context.
- [x] Extracted traceparent components MUST be structurally validated before becoming public normalized context.
- [x] No unchecked string can become a span name, span attribute, metric label, or logger context field.
- [x] No raw carrier map or arbitrary nested structure may be returned from extract().
- [x] tracestate and baggage are never exported as normalized trace context; existing caller-owned carrier values are handled according to the explicit W3C pass-through and mutation policy.
- [x] The implementation MUST NOT introduce its own redactor, sensitive-key classifier, hash engine, serializer, or security policy registry.
- [x] Shared SensitiveDataRedactorInterface remains available for future tracing sinks, but is not consumed without a concrete eligible sink in this epic.
- [x] Any future use of redacted summaries in spans remains subject to owner-specific sink schemas; redaction does not automatically make arbitrary attributes admissible.

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- Context writes:
  - N/A; verify absence of ContextStore writes and new ContextKeys.
- Reset:
  - N/A; verify absence of reset tags, ResetInterface implementations, and request-local mutable state.
- Spans:
  - N/A; verify the existing Foundation noop tracer is preserved and no new spans are emitted.
- Metrics:
  - N/A; verify no runtime metric names, labels, or catalog entries are introduced.
- Logs:
  - N/A; verify no log emission or raw diagnostic output from propagation.
- [x] Dependency boundaries:
  - [x] verify only core/contracts and core/foundation production source imports.
  - [x] verify no duplicate tracing, ContextStore, exporter, sampler, or redaction implementations.
- [x] Propagation:
  - [x] verify exact deterministic W3C traceparent fixtures.
  - [x] verify malformed carrier fail-closed behavior.
  - [x] verify no unvalidated input reaches returned context or output carrier.
- [x] Module/DI:
  - [x] verify Foundation noop fallback.
  - [x] verify explicit module selection.
  - [x] verify selected-module W3C binding and unselected-module Foundation noop fallback without a runtime mode selector.
  - [x] verify source/compiled parity.
- [x] Packaging:
  - [x] verify canonical legal files, Composer metadata, package index, installation catalog, dependency matrix, and generated architecture gates.

#### Test harness / fixtures (when integration is needed)

- [x] Use deterministic synthetic carrier fixtures only.
- [x] No concrete HTTP middleware is introduced.
- [x] No real network, clock, filesystem, tracer backend, exporter, or active Kernel UoW is needed for propagation unit tests.
- [x] Source/compiled DI tests reuse existing Kernel/Foundation test infrastructure.
- [x] Production platform/http integration is explicitly deferred.

### Tests (MUST)

- Unit:
  - [x] `packages/platform/tracing/tests/Unit/W3cPropagationExtractInjectDeterministicTest.php`
    - [x] valid traceparent fixtures, malformed version/separators/hex/length cases.
    - [x] all-zero trace-id and parent-id rejection.
    - [x] duplicate/case-varied headers and list-valued carrier handling.
    - [x] injection replacement, invalid-context fail-closed behavior, unrelated carrier preservation.
    - [x] forward-version policy and bounded-input checks.
    - [x] extract() returns only the three normalized context fields and never exposes raw traceparent or tracestate values.
    - [x] inject() emits a canonical traceparent only through its returned carrier, as explicitly required by the propagation contract.
    - [x] existing tracestate is preserved only under the documented byte-identical traceparent pass-through policy.
    - [x] neither method emits raw-header diagnostics, logs, metrics, spans, or other output.
    - [x] absent traceparent returns [] without throwing.
    - [x] invalid traceparent value types, including object, resource, integer, bool, and malformed lists, are rejected without throwing or implicit conversion.
    - [x] unrelated carrier fields containing contract-valid strings and lists of strings remain unchanged.
    - [x] extraction with invalid carrier key types fails deterministically.
    - [x] tests distinguish malformed tracing-field input from carrier shapes outside the existing ContextPropagationInterface contract.
    - [x] no test simultaneously requires preserving an out-of-contract unrelated value and returning a contract-valid carrier.
    - [x] associative context key ordering does not change valid injection.
    - [x] duplicate traceparent header names differing only by ASCII case fail deterministically.
    - [x] inject() with valid context rejects duplicate existing traceparent headers.
    - [x] inject() with valid context rejects malformed existing traceparent values.
    - [x] inject() with valid context rejects invalid existing traceparent lists.
    - [x] invalid existing traceparent removes traceparent and tracestate without removing unrelated fields.
    - [x] absent existing traceparent permits injection from independently validated normalized context.
    - [x] a single-string-list traceparent is validated identically to its string representation.
    - [x] byte-identical traceparent values preserve existing tracestate for both accepted input representations.
    - [x] traceparent replacement removes existing tracestate.
    - [x] long unrelated header names do not trigger unbounded normalization.
    - [x] ASCII case-insensitive header matching is independent of locale.
    - [x] no Stringable, reflection, serialization, or Unicode conversion is invoked during header-name matching.
    - [x] version ff is rejected.
    - [x] version 00 length and separator boundaries are exact.
    - [x] higher-version fields follow the documented W3C forward-compatibility policy.
    - [x] sampled flag is interpreted by bit masking.
    - [x] trace_flags `00`, `01`, `02`, and `03` are extracted without losing supported bits.
    - [x] input trace_flags `03` extracts normalized `03`, not `01`.
    - [x] input trace_flags `02` extracts normalized `02`, not `00`.
    - [x] unsupported flag bits are cleared only when constructing an accepted forward-version downgrade; version-00 input with reserved bits is rejected without normalization.
    - [x] same-trace-id injection preserves the random-trace-id bit.
    - [x] outgoing traceparent with flags `03` preserves canonical byte identity when the incoming header is otherwise unchanged.
    - [x] injection rejects unsupported normalized trace_flags values.
    - [x] version-00 trace_flags `04`, `05`, `80`, and `ff` are rejected by the strict ingress policy.
    - [x] rejected version-00 reserved bits are never silently converted into a different version-00 traceparent.
    - [x] accepted forward-version traceparent with unsupported flag bits follows the explicit downgrade policy.
    - [x] unchanged trace_id with changed random-trace-id bit is rejected during same-carrier injection.
    - [x] unchanged parent_id with changed sampled bit is rejected during same-carrier injection.
    - [x] changed parent_id with an explicitly supplied valid context follows the documented caller-owned identity policy.
    - [x] invalid flag mutations do not preserve an unvalidated tracestate value.
    - [x] identical traceparent propagation preserves existing tracestate.
    - [x] replaced or downgraded traceparent follows the defined tracestate removal policy.
    - [x] unrelated carrier fields remain unchanged.
    - [x] no Stringable::__toString() is invoked to interpret invalid input.
    - [x] no trace/span/correlation ID is generated.
    - [x] exactly 128 carrier entries are accepted for extraction.
    - [x] 129 carrier entries return [] on extraction.
    - [x] over-limit injection strips traceparent and tracestate without removing unrelated fields.
    - [x] over-limit injection never emits a new traceparent, even with otherwise valid context.
    - [x] traceparent appearing after the 128th carrier entry cannot bypass the over-limit policy.
    - [x] missing/default empty context causes no tracing-field injection.
    - [x] inject([], []) returns [].
    - [x] malformed carrier entry types never trigger implicit scalar conversion.

- Contract:
  - [x] `packages/platform/tracing/tests/Contract/W3CPropagationDeterministicContractTest.php`
    - [x] test ContextPropagationInterface shape and canonical output.
    - [x] verify repeated calls with the same inputs produce identical results.
    - [x] verify no implicit id generation or ContextStore access.
    - [x] verify unsupported/invalid data follows the documented deterministic policy.

  - [x] `packages/platform/tracing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
    - [x] replace scaffold placeholder assertion with executable behavior checks.
    - [x] verify Foundation noop tracer/span behavior and absence of instrumentation side effects.
    - [x] verify repeated span end remains harmless.
    - [x] verify callback exceptions remain observable and are not swallowed.
    - [x] verify no new tracing state/reset dependency exists.

  - [x] `packages/platform/tracing/tests/Contract/TracingConfigRulesContractTest.php`
    - [x] `config/tracing.php` returns exactly `[]`.
    - [x] exact ruleset shape: schemaVersion = 1, configRoot = tracing, additionalKeys = false, keys = [].
    - [x] unknown config keys, including `propagation` and `propagation.mode`, are rejected by the existing Kernel ConfigValidator.
    - [x] verify package-owned defaultsConfigPath and registered tracing root.
    - [x] verify no `w3c|noop` selector or configuration factory exists.

  - [x] `packages/platform/tracing/tests/Contract/TracingModuleMetadataContractTest.php`
    - [x] exact module id, package identity, provider list, runtime requirements, and Composer metadata parity.

  - [x] `packages/platform/tracing/tests/Contract/TracingBoundaryNoForbiddenDependenciesContractTest.php`
    - [x] no forbidden production imports.
    - [x] no platform-local NoopTracer/NoopSpan.
    - [x] no ContextStore, KernelRuntime, HTTP middleware, exporter, sampler, logging, meter, or redaction implementation dependency.
    - [x] no ContextAccessorInterface or CorrelationIdProviderInterface constructor dependency in W3cTraceContextPropagation.
    - [x] no ContextStore reads, writes, snapshot exports, or reset registration.
    - [x] no implicit conversion between W3C trace_id, parent_id, Kernel correlation_id, request_id, or uow_id.
    - [x] Foundation remains the owner of noop tracing and UoW-local context reset.
    - [x] selected and unselected tracing modules preserve Kernel lifecycle ownership.

- Integration:
  - [x] `packages/platform/tracing/tests/Integration/TracingModuleSelectionAndFallbackTest.php`
    - [x] unselected module retains Foundation `ContextPropagationInterface → NoopContextPropagation`.
    - [x] explicitly selected `platform.tracing` resolves `ContextPropagationInterface → W3cTraceContextPropagation` without reading runtime config.
    - [x] no `w3c|noop` mode or service-factory selector is introduced.
    - [x] TracerPortInterface remains the existing Foundation binding.
    - [x] no unexpected services, tags, or reset registrations.

  - [x] `packages/platform/tracing/tests/Integration/TracingProviderSourceCompiledParityTest.php`
    - [x] verify declarative provider definition parity across available source and compiled-container integration harnesses.
    - [x] verify exactly one direct `ContextPropagationInterface → W3cTraceContextPropagation` definition for the selected module.
    - [x] verify no `ConfigRepositoryInterface` reference or factory-method descriptor is introduced.
    - [x] verify only the intended ContextPropagationInterface override.
    - [x] verify deterministic module/provider ordering and absence of runtime factory execution.

  - [x] `packages/platform/tracing/tests/Integration/TracingPropagationApplicationUsageSmokeTest.php`
    - [x] construct a real application-facing ContextPropagationInterface through the existing DI composition.
    - [x] enable platform.tracing explicitly for one selected application target.
    - [x] verify that the selected implementation is W3cTraceContextPropagation.
    - [x] extract a valid synthetic incoming traceparent.
    - [x] verify the exact normalized trace_id, parent_id, and trace_flags values.
    - [x] inject the validated context into a synthetic outbound carrier.
    - [x] verify the canonical outgoing traceparent value.
    - [x] verify noop fallback when the module is excluded.
    - [x] verify that no spans, metrics, logs, ContextStore values, or reset services are created.
    - [x] verify identical behavior across repeated calls.
    - [x] reuse existing Kernel/Foundation DI test harnesses.
    - [x] no fake HTTP middleware or second application bootstrap is introduced.

- Gates/Arch:
  - [x] deptrac green

### DoD (MUST)

- [x] Spec locked with no open W3C carrier/context shape decisions.
- [x] No duplicate Foundation tracer/span/noop implementation.
- [x] Platform tracing package scaffold and metadata complete.
- [x] NewPackage generator produces canonical shared Composer metadata without manual post-generation fixes and always emits validated description and keywords.
- [x] Missing --description and --keywords arguments generate their deterministic neutral description and canonical keyword baseline respectively.
- [x] Explicitly empty metadata arguments fail validation without partial package creation or staging leaks.
- [x] Explicitly supplied keywords replace the canonical baseline without implicit merging or package-capability inference.
- [x] Canonical baseline keywords are limited to `coretsia`, `php`, and `framework`; no layer, slug, or kind keywords are inferred.
- [x] Explicit lowercase ASCII kebab-case keywords, including hyphenated and numeric tokens, preserve caller order after deterministic deduplication.
- [x] Package-specific description and keywords remain explicitly configurable through the extended package:new CLI.
- [x] Existing package:new invocations remain backward-compatible.
- [x] NewPackageAtomicCreationTest covers generated metadata, invalid inputs, and atomic failure cleanup.
- [x] PACKAGING.md documents shared metadata ownership and package-specific dependency boundaries.
- [x] packages/platform/tracing/SECURITY.md remains byte-identical to repository-root SECURITY.md.
- [x] package-scaffold --check remains green.
- [x] W3C traceparent extract/inject deterministic and fail-closed under the exact version, carrier, normalized-context, and mutation policies defined in tracecontext.md.
- [x] W3C version-00 and forward-version behavior covered by deterministic contract tests.
- [x] tracestate pass-through and mutation rules covered by deterministic tests.
- [x] No claim of complete W3C tracestate implementation.
- [x] Unsupported tracestate/baggage behavior explicitly documented.
- [x] Existing Foundation TracerPortInterface always resolvable.
- [x] ContextPropagationInterface always resolvable with Foundation noop fallback.
- [x] Explicitly selected `platform.tracing` binds `ContextPropagationInterface` directly to `W3cTraceContextPropagation`, without a runtime propagation-mode selector.
- [x] No new UoW state, ContextStore writes, reset tags, or active span lifecycle.
- [x] No tracing exporter, sampler, metrics, or recording spans introduced.
- [x] No persisted traces, trace viewer, or automatic recording capability claimed.
- [x] No implicit generation or publication of trace IDs and local span IDs.
- [x] Foundation noop implementation and its existing behavior remain unchanged.
- [x] Selected tracing module provides only W3C propagation; unselected module retains the canonical Foundation noop fallback.
- [x] Production HTTP integration and future recording/exporting remain separate deliverables.
- [x] Primary callback exceptions remain preserved.
- [x] Source and compiled DI behavior equivalent.
- [x] Canonical dependency matrix, package index, installation catalog, Composer repository metadata, and Deptrac outputs synchronized.
- [x] All exact tests green.
- [x] Gates/Arch green.
- [x] ADR and SSoT registered in canonical indexes.
- [x] README sections complete.
- [x] platform/http integration remains outside this epic and is not claimed as implemented.

---

### 3.35.0 Platform metrics baseline (noop-safe + label allowlist + renderer port) (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.35.0"
owner_path: "packages/platform/metrics/"

package_id: "platform/metrics"
composer: "coretsia/platform-metrics"
kind: runtime
module_id: "platform.metrics"

goal: "Надати deterministic, noop-safe platform metrics baseline поверх існуючих Foundation observability contracts: перевірку дозволених label keys, stateless MeterPortInterface implementation та null MetricsRendererInterface без запису, накопичення або експорту метрик; Kernel UoW, Foundation noop і reset ownership залишаються незмінними."
provides:
- "Reuse existing Foundation NoopMeter as the canonical fallback when platform.metrics is absent."
- "Stateless DefaultMeter with deterministic, no-throw label admission and no metric recording."
- "NullMetricsRenderer implementing the existing MetricsRendererInterface."
- "Fixed global metric label allowlist aligned with docs/ssot/observability.md."
- "Explicit target-local module selection and declarative MeterPortInterface/MetricsRendererInterface bindings."
- "No collector, exporter, metrics buffer, metrics registry, ContextStore owner, reset service, or new metric catalog."
- "Cutline impact: establishes a policy-safe metrics baseline without claiming active metrics collection or rendering."

tags_introduced: []

config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0039-platform-metrics-noop-baseline.md"
ssot_refs:
- "docs/ssot/metrics-policy.md"
- "docs/ssot/observability.md"
---

### Scope boundary (MUST)

- This epic establishes a noop-only, policy-safe metrics baseline, not a recording or exporting metrics backend.
- No metric sample is retained, aggregated, exported, persisted, or exposed through an HTTP endpoint.
- Existing Foundation NoopMeter remains the canonical fallback when platform.metrics is not selected.
- The selected DefaultMeter performs label admission but does not collect metric data.
- MetricsRendererInterface is resolved through NullMetricsRenderer only when platform.metrics is selected.
- No metrics registry, counter/histogram storage, exporter selection, HTTP endpoint, or process-lifetime metrics state is introduced.
- A future recording backend MUST preserve the existing MeterPortInterface and MetricsRendererInterface contracts.
- Epic 6.20.0 MUST extend the existing platform/metrics package, not recreate its composer.json, module, provider, configuration files, or README.
- Epic 6.20.0 owns the future metrics registry, metric-specific runtime validation, aggregation, lifecycle, cardinality limits, and reset discipline.
- Epic 6.21.0 owns the optional Prometheus integration and MUST NOT introduce another platform metrics package or HTTP endpoint owner.
- The existing Noop/NullMetricsRenderer.php is the canonical null renderer; future integrations MUST reuse or explicitly replace its binding without creating a duplicate null renderer implementation.
- AllowedLabelSet remains the canonical global label-key admission helper; future metric-specific validation MUST NOT replace, extend, or relax the global allowlist.
- Future recording components MAY introduce state only with an explicit lifecycle and reset decision; the Phase 3 baseline remains stateless.
- No future exporter, renderer, or collector is implicitly enabled by package installation.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - [ ] Existing Foundation NoopMeter and canonical MeterPortInterface MUST remain available.
  - [ ] MetricsRendererInterface MUST already exist in core/contracts.
  - [ ] Kernel declarative runtime container definitions and target-local ModulePlan MUST be available.
  - [ ] The metrics configuration root MUST already be reserved for platform/metrics.
  - [ ] Epic 3.30.0 package:new metadata extension MUST be completed before using --description and --keywords.
  - [ ] ADR-0038 MUST already be registered before allocating ADR-0039.
  - [ ] Existing observability metric catalog and label allowlist gates MUST remain authoritative.

- Required existing implementation paths (MUST; reuse without modification):
  - `packages/core/contracts/src/Observability/Metrics/MeterPortInterface.php`
  - `packages/core/contracts/src/Observability/Metrics/MetricsRendererInterface.php`
  - `packages/core/foundation/src/Observability/Metrics/NoopMeter.php`
  - `packages/core/foundation/src/Provider/FoundationServiceProvider.php`
  - `packages/core/foundation/src/Container/Definition/ContainerDefinitionBuilder.php`
  - `packages/core/foundation/src/Container/Definition/ContainerDefinitionContext.php`
  - `docs/ssot/observability.md`
  - `docs/ssot/observability-and-errors.md`
  - `docs/ssot/config-roots.md`
  - `docs/ssot/runtime-container-definitions.md`
  - `tools/gates/observability_metric_catalog_gate.php`

- Required config roots/keys:
  - `metrics` — reserved package-owned config root.
  - No runtime-selectable metrics backend, collector, exporter, label allowlist, or renderer configuration is introduced by this baseline.
  - The existing package scaffold config files remain valid and declarative.

- Required config roots registry:
  - `metrics` MUST already be reserved in `docs/ssot/config-roots.md` with owner `platform/metrics`.
  - This epic adds/implements package-owned defaults and rules under the existing root; it does not introduce a new root.

- Label allowlist source-of-truth (MUST):
  - Allowed metric label keys are fixed by `docs/ssot/observability.md`.
  - `docs/ssot/metrics-policy.md` MAY document metric-specific examples / normalization,
    but MUST NOT redefine, extend, replace, or relax the canonical allowlist.
  - Runtime/app config MUST NOT extend, replace, or relax this allowlist.
  - `AllowedLabelSet` MUST enforce exactly:
    - `method`
    - `status`
    - `driver`
    - `operation`
    - `table`
    - `outcome`
  - [ ] Global label admission is not equivalent to full metric-specific validation.
  - [ ] Every emitted metric MUST also satisfy the canonical catalog row for its name, type, owner, and permitted labels.
  - [ ] Existing observability metric catalog gates remain authoritative for production callsites.
  - [ ] AllowedLabelSet enforces the fixed global label key set and accepted scalar value types; it MUST NOT introduce a second metric catalog.
  - [ ] The Phase 3 baseline emits no metric samples, so it MUST NOT claim complete backend-level metric-specific admission or cardinality enforcement.
  - [ ] A future recording backend MUST establish metric-specific runtime validation before emitting samples.
  - [ ] Redacted values MUST NOT expand either allowlist.

- Required tags:
  - N/A

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface`

- Existing Foundation fallback:
  - `Coretsia\Foundation\Observability\Metrics\NoopMeter`

- No ContextAccessorInterface, ContextKeys, CorrelationIdProviderInterface, LoggerInterface, TracerPortInterface, or ResetInterface dependency is required by the baseline.

- No second context owner, UnitOfWork lifecycle, or reset orchestrator is introduced.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http`
- `integrations/*`
- `core/kernel` production-source imports
- `platform/logging`
- `platform/redaction`
- `platform/tracing`
- `platform/worker`
- vendor metrics SDK implementations
- metrics exporters and persistence adapters

#### Uses ports (API surface, NOT deps) (MUST)

- Contracts:
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface`

- Foundation:
  - `Coretsia\Foundation\Observability\Metrics\NoopMeter` (existing unselected fallback)
  - canonical declarative container-definition APIs

- No ContextAccessorInterface, ContextKeys, CorrelationIdProviderInterface, LoggerInterface, TracerPortInterface, ConfigRepositoryInterface, or ResetInterface dependency is introduced.
- Metric names and labels are supplied explicitly by producers through the existing MeterPortInterface.
- The metrics baseline MUST NOT read or write Kernel-owned UnitOfWork context.

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - `platform/http` owns future HTTP metrics middleware integration.
  - This epic MUST NOT create or modify an HTTP middleware.
  - No HTTP route, endpoint, response adapter, or metrics scraping transport is introduced.
  - MetricsRendererInterface remains transport-neutral.
  - HTTP metrics producers consume the existing MeterPortInterface through their own module lifecycle.
- Runtime DI:
  - Foundation already provides MeterPortInterface → NoopMeter.
  - When platform.metrics is absent, the Foundation meter binding remains unchanged.
  - Foundation does not currently provide a MetricsRendererInterface binding.
  - When platform.metrics is explicitly selected, its provider binds MeterPortInterface → DefaultMeter and MetricsRendererInterface → NullMetricsRenderer.
  - Renderer availability is guaranteed only for targets selecting platform.metrics.
  - DefaultMeter remains stateless and discards every metric sample.
  - Provider registration MUST NOT create an additional metrics backend, collector, registry, buffer, or lifecycle owner.
  - Source and compiled container definitions MUST preserve the same effective bindings.
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

Naming lock (MUST)
- [ ] This epic uses a neutral platform-local name to avoid cross-package collisions:
  - [ ] `Default/DefaultMeter.php`
- [ ] This epic text does not use platform-local `NoopMeter` as the canonical class name.

#### Creates

- [ ] Run `composer package:new -- --layer=platform --slug=metrics --kind=runtime --description="Coretsia metrics runtime package: deterministic label allowlist validation, noop-safe meter calls, and null rendering." --keywords=coretsia,php,framework,metrics,runtime,observability,label-allowlist,noop,renderer,deterministic` exactly once.
- [ ] Preserve the canonical scaffold-generated package structure.
- [ ] LICENSE and NOTICE MUST remain byte-identical to their repository-root counterparts.
- [ ] SECURITY.md MUST remain byte-identical to repository-root SECURITY.md.
- [ ] Do not customize package-level SECURITY.md; metrics-specific security policy belongs to README.md and metrics-policy.md.
- [ ] Complete module, provider, configuration, and test placeholders using existing Coretsia conventions.
- [ ] Package creation MUST NOT implicitly enable platform.metrics in application targets or presets.

- [ ] `packages/platform/metrics/LICENSE`
  - [ ] generated by the existing package scaffold.
  - [ ] MUST remain byte-identical to repository-root LICENSE.
  - [ ] no package-specific license amendments.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; canonical legal file.
  - [ ] Verification: package-scaffold --check.

- [ ] `packages/platform/metrics/NOTICE`
  - [ ] generated by the existing package scaffold.
  - [ ] MUST remain byte-identical to repository-root NOTICE.
  - [ ] no package-specific notice amendments.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; canonical notice file.
  - [ ] Verification: package-scaffold --check.

- [ ] `packages/platform/metrics/SECURITY.md`
  - [ ] generated by the existing package scaffold.
  - [ ] MUST remain byte-identical to repository-root SECURITY.md.
  - [ ] MUST NOT introduce package-local security reporting authority.
  - [ ] metric label, data-admission, and export-safety restrictions belong to README.md and docs/ssot/metrics-policy.md.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; canonical security-policy file.
  - [ ] Verification: package-scaffold --check.

- [ ] `packages/platform/metrics/composer.json`
  - [ ] `name = coretsia/platform-metrics`.
  - [ ] `type = library`.
  - [ ] `license = Apache-2.0`.
  - [ ] Description and keywords are generated from the explicit package:new arguments.
  - [ ] Authors, homepage, support, and PHP platform follow the canonical package:new metadata policy established by 3.30.0.
  - [ ] Composer requires exactly:
    - [ ] `php: ^8.4`.
    - [ ] `coretsia/core-contracts: ^0.11.0`.
    - [ ] `coretsia/core-foundation: ^0.11.0`.
  - [ ] PSR-4 `Coretsia\Platform\Metrics\` → `src/`.
  - [ ] PSR-4 test namespace → `tests/`.
  - [ ] `extra.coretsia.kind = runtime`.
  - [ ] `extra.coretsia.moduleId = platform.metrics`.
  - [ ] `extra.coretsia.moduleClass = Coretsia\Platform\Metrics\Module\MetricsModule`.
  - [ ] `extra.coretsia.providers = [Coretsia\Platform\Metrics\Provider\MetricsServiceProvider]`.
  - [ ] `extra.coretsia.requires = [core.foundation]`.
  - [ ] `extra.coretsia.conflicts = []`.
  - [ ] `extra.coretsia.defaultsConfigPath = config/metrics.php`.
  - [ ] MUST NOT require core/kernel, platform/http, tracing, logging, redaction, or vendor metrics SDKs.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; Composer metadata only.
  - [ ] Verification: package identity, manifest, dependency, Composer metadata, and scaffold gates.

- [ ] `packages/platform/metrics/src/Module/MetricsModule.php`
  - [ ] final runtime module-metadata class.
  - [ ] public typed constants:
    - [ ] `MODULE_ID = 'platform.metrics'`.
    - [ ] `PACKAGE_ID = 'platform/metrics'`.
    - [ ] `COMPOSER_PACKAGE = 'coretsia/platform-metrics'`.
    - [ ] `KIND = 'runtime'`.
    - [ ] `CONFIG_ROOT = 'metrics'`.
  - [ ] Metadata methods follow the existing LoggingModule shape.
  - [ ] `providers()` returns only MetricsServiceProvider::class.
  - [ ] No runtime service construction, context access, metric emission, logging, or reset ownership.
  - [ ] Verification: module metadata matches composer.json and the config-roots registry.

- [ ] `packages/platform/metrics/src/Provider/MetricsServiceProvider.php`
  - [ ] final class implementing ServiceProviderInterface and ContainerDefinitionProviderInterface.
  - [ ] `register()` uses assertDefinitionProviderRegistrationAllowed() and registerDefinitionProvider($this).
  - [ ] `define()` is the single canonical declarative service-definition source.
  - [ ] binds MeterPortInterface directly to DefaultMeter.
  - [ ] binds MetricsRendererInterface directly to NullMetricsRenderer.
  - [ ] uses existing ContainerDefinitionBuilder::classService() APIs.
  - [ ] no factory helper is required for these constructor-free baseline services.
  - [ ] no configuration reader, service locator, runtime closure, dynamic registry, or backend discovery.
  - [ ] no ContextAccessorInterface, ContextStore, Stopwatch, LoggerInterface, or TracerPortInterface dependency.
  - [ ] no new tags, hooks, reset registrations, or stateful services.
  - [ ] Foundation NoopMeter binding remains authoritative when this module is absent.
  - [ ] Kernel owns explicit module selection and provider ordering.
  - [ ] Source and compiled service-definition behavior MUST be equivalent.
  - [ ] Verification: selected/unselected target bindings, definition descriptors, and source/compiled parity.

- [ ] `packages/platform/metrics/config/metrics.php`
  - [ ] returns an empty package-owned configuration subtree.
  - [ ] exact baseline result is `[]`.
  - [ ] no enabled switch, renderer selector, collector, exporter, or backend configuration.
  - [ ] no configurable label allowlist, metric catalog, or label normalization map.
  - [ ] no environment access, runtime objects, callbacks, or mutable state.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; defaults-only file.
  - [ ] Verification: exact defaults shape and reserved metrics root ownership.

- [ ] `packages/platform/metrics/config/rules.php`
  - [ ] schemaVersion = 1.
  - [ ] configRoot = metrics.
  - [ ] additionalKeys = false.
  - [ ] keys = [].
  - [ ] no user-configurable metrics keys in this baseline.
  - [ ] unknown or reserved keys are rejected by the existing Kernel ConfigValidator.
  - [ ] no executable validators, callbacks, closures, or custom schema types.
  - [ ] no duplicate configuration validation pipeline.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; declarative configuration policy only.
  - [ ] Verification: canonical ruleset shape and invalid-key rejection.

- [ ] `packages/platform/metrics/src/Default/DefaultMeter.php`
  - [ ] final stateless class implementing MeterPortInterface.
  - [ ] public methods match the existing contract exactly:
    - [ ] `increment(string $name, int $delta = 1, array $labels = []): void`.
    - [ ] `observe(string $name, int $value, array $labels = []): void`.
  - [ ] both increment() and observe() call AllowedLabelSet::accepts($labels) before deterministic noop completion.
  - [ ] no additional AllowedLabelSet service, wrapper, factory, or dependency-injection binding is introduced.
  - [ ] valid labels complete without recording, buffering, forwarding, or exporting samples.
  - [ ] forbidden labels fail closed through deterministic skip behavior.
  - [ ] forbidden labels MUST NOT throw, log, emit metrics, or expose raw values.
  - [ ] no metric names, values, labels, or invocation details are retained.
  - [ ] no self-instrumentation or recursive metrics.record_* emission.
  - [ ] no clocks, timers, context reads, configuration reads, or backend dependencies.
  - [ ] no mutable process-lifetime or UnitOfWork-local state.
  - [ ] no reset service or kernel.reset tag.
  - [ ] metric-specific catalog enforcement remains governed by the existing observability gates.
  - [ ] no claim of active recording or runtime backend-level cardinality enforcement.
  - [ ] Verification: contract signatures, admitted/rejected labels, no-throw calls, statelessness, and absence of output.

- [ ] `packages/platform/metrics/src/Noop/NullMetricsRenderer.php`
  - [ ] final stateless class implementing MetricsRendererInterface.
  - [ ] `contentType(): string` returns exactly `text/plain; charset=utf-8`.
  - [ ] `render(): string` returns exactly an empty string.
  - [ ] no collector, registry, filesystem, network, process state, or service dependency.
  - [ ] no HTTP response, endpoint, Prometheus integration, or telemetry export.
  - [ ] no metric names, samples, labels, payloads, or private data are rendered.
  - [ ] repeated calls are deterministic and no-throw.
  - [ ] no context reads/writes, spans, metrics, logs, or reset ownership.
  - [ ] Verification: exact content type, empty rendering, repeated calls, and source/compiled DI resolution.

- [ ] `packages/platform/metrics/src/Labels/AllowedLabelSet.php`
  - [ ] final stateless global metric label-key admission helper.
  - [ ] canonical allowed keys are exactly:
    - [ ] `method`.
    - [ ] `status`.
    - [ ] `driver`.
    - [ ] `operation`.
    - [ ] `table`.
    - [ ] `outcome`.
  - [ ] public static `accepts(array $labels): bool` performs deterministic, no-throw validation.
  - [ ] the helper has no constructor dependencies, service registration, mutable static properties, or runtime cache.
  - [ ] all keys must be strings matching the fixed global allowlist.
  - [ ] accepted values are only string, int, or bool.
  - [ ] null, float, list, map, object, resource, and closure values are rejected.
  - [ ] unknown label keys are rejected without invoking implicit conversion or Stringable::__toString().
  - [ ] empty label maps are accepted.
  - [ ] no arbitrary label-value normalization, hashing, redaction, or coercion is introduced.
  - [ ] the allowlist is a fixed runtime representation of the canonical SSoT, not a second policy owner.
  - [ ] runtime and app config MUST NOT extend or replace the allowlist.
  - [ ] no production filesystem reads or Markdown parsing.
  - [ ] tests MUST detect drift between this fixed set and docs/ssot/observability.md.
  - [ ] metric-specific catalog validation and producer-owned safe-value derivation remain separate responsibilities.
  - [ ] no context reads/writes, spans, metrics, logs, or reset ownership.
  - [ ] Verification: exact six-key policy, invalid types, deterministic ordering independence, and SSoT consistency.

Docs:
- [ ] `packages/platform/metrics/README.md`
  - [ ] package identity, namespace, dependencies, module ID, and supported ports.
  - [ ] document Foundation NoopMeter reuse when platform.metrics is absent.
  - [ ] document DefaultMeter as selected stateless label-validating noop behavior.
  - [ ] explain why DefaultMeter is not a second collector or metrics state owner.
  - [ ] document MetricsRendererInterface availability only when platform.metrics is selected.
  - [ ] document fixed null renderer content type and empty output.
  - [ ] document exact global label allowlist and metric-specific catalog restrictions by reference to observability.md.
  - [ ] include synthetic increment() and observe() examples using existing canonical metric names.
  - [ ] explicitly state that no metrics are collected, retained, exported, or queryable.
  - [ ] explain the future backend integration boundary without claiming one is implemented.
  - [ ] document that no runtime config keys are introduced in the baseline.
  - [ ] include canonical `## Observability`, `## Errors`, and `## Security / Redaction` sections.
  - [ ] no raw labels, sensitive values, or unsupported exporter claims.
  - [ ] reference metrics-policy.md and observability.md as normative policies.
  - [ ] vulnerability reporting follows the unmodified canonical SECURITY.md.

- [ ] `docs/ssot/metrics-policy.md`
  - [ ] canonical SSoT header, ssotVersion = 1, owner = platform/metrics.
  - [ ] define the Phase 3 noop-only metrics capability and non-goals.
  - [ ] document the exact MeterPortInterface and MetricsRendererInterface contract usage.
  - [ ] document selected versus unselected module DI behavior.
  - [ ] define DefaultMeter label admission and deterministic skip behavior.
  - [ ] distinguish global label keys from metric-specific catalog restrictions.
  - [ ] reference docs/ssot/observability.md as the single owner of metric names, metric types, label keys, and metric-specific labels.
  - [ ] MUST NOT introduce a competing runtime metric catalog or configurable allowlist.
  - [ ] document producer-owned safe-value derivation and bounded-cardinality obligations.
  - [ ] document that redaction does not authorize arbitrary label keys or values.
  - [ ] document the absence of active collection, export, storage, HTTP exposure, and queryable metrics.
  - [ ] document no ContextStore reads/writes, UnitOfWork ownership, or reset registration.
  - [ ] document no-throw behavior and absence of diagnostics from invalid labels.
  - [ ] include deterministic examples and verification requirements.
  - [ ] no new observability metric names or span names are introduced.

- [ ] `docs/adr/ADR-0039-platform-metrics-noop-baseline.md`
  - [ ] ADR version 1 and platform/metrics ownership.
  - [ ] record the decision to reuse Foundation NoopMeter as the unselected fallback.
  - [ ] explain why selected DefaultMeter performs label admission while remaining noop.
  - [ ] record the existing MeterPortInterface and MetricsRendererInterface as canonical ports.
  - [ ] record direct declarative DI bindings without a baseline factory.
  - [ ] record why active collection, exporter, metric storage, and HTTP rendering are deferred.
  - [ ] record why no runtime metrics configuration is required.
  - [ ] explicitly reject a second metrics registry, context owner, or reset orchestrator.
  - [ ] document SSoT authority for global and metric-specific labels.
  - [ ] record no-throw failure isolation, security restrictions, and source/compiled parity.
  - [ ] record that the baseline cannot produce queryable metrics.

#### Modifies

- [ ] `docs/architecture/DEPENDENCIES.md`
  - [ ] add exactly one materialized package row for platform/metrics.
  - [ ] direct production source dependencies: core/contracts, core/foundation.
  - [ ] preserve strcmp package-id ordering.
  - [ ] no source dependency on core/kernel, platform/http, logging, redaction, tracing, or vendor metrics backends.
  - [ ] Deptrac generator MUST consume this row as the canonical dependency policy.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; architecture documentation only.
  - [ ] Verification: dependency matrix and generated Deptrac policy remain consistent.

- [ ] `composer.json`
  - [ ] register `coretsia/platform-metrics: 0.11.x-dev` under the monorepo require-dev workspace baseline.
  - [ ] synchronize the managed path repository entry through existing Composer repository tooling.
  - [ ] preserve existing workspace package references and deterministic ordering.
  - [ ] do not create a separate workspace package registry.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; workspace metadata only.
  - [ ] Verification: Composer repository sync and package identity checks.

- [ ] `docs/ssot/INDEX.md`
  - [ ] register docs/ssot/metrics-policy.md exactly once.
  - [ ] use canonical title, owner, ssotVersion, and scope formatting.
  - [ ] preserve strcmp ordering and no-forward-reference policy.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; navigation only.
  - [ ] Verification: SSoT index consistency gate.

- [ ] `docs/adr/INDEX.md`
  - [ ] register docs/adr/ADR-0039-platform-metrics-noop-baseline.md exactly once.
  - [ ] preserve canonical ADR title, owner, adrVersion, and scope formatting.
  - [ ] maintain sequence after ADR-0038.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; navigation only.
  - [ ] Verification: ADR index consistency gate.

- [ ] `docs/ssot/http-middleware-catalog.md`
  - [ ] update the obsolete platform/metrics owner-package test reference from NoopNeverThrowsContractTest.php to CrossCuttingNoopDoesNotThrowTest.php.
  - [ ] preserve the existing future MetricsEndpointMiddleware reservation under http.middleware.system_pre at priority 590.
  - [ ] clarify that this reservation is documentation-only during epic 3.35.0.
  - [ ] update the priority-590 catalog registration description to explicitly identify the future epic 6.20.0 owner and prohibit registration during the Phase 3 noop-only baseline.
  - [ ] the generic optional-package registration guidance MUST NOT imply that installation or selection of platform.metrics during 3.35.0 activates MetricsEndpointMiddleware.
  - [ ] preserve the existing middleware class reservation without creating or registering its implementation.
  - [ ] preserve platform/metrics ownership of the future optional metrics endpoint.
  - [ ] this epic MUST NOT register MetricsEndpointMiddleware or activate the reserved HTTP slot.
  - [ ] HTTP endpoint implementation and its lifecycle remain owned by epic 6.20.0.
  - [ ] no new HTTP middleware class, tag, route, response adapter, or metrics exposure policy is introduced.
  - [ ] Context/UoW: N/A; documentation-only change.
  - [ ] Spans/metrics/logs: N/A; no production emission.
  - [ ] Reset: N/A; no runtime state.
  - [ ] Verification: canonical middleware catalog retains one future metrics endpoint owner and references an existing planned contract test.

- [ ] `tools/testing/package-index.php`
  - [ ] regenerate through the existing package-index generator.
  - [ ] MUST NOT edit generated package metadata manually.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; generated metadata only.
  - [ ] Verification: package-index --check.

- [ ] `tools/testing/deptrac.yaml`
  - [ ] regenerate through the existing Deptrac generator.
  - [ ] MUST NOT introduce a separate metrics dependency allowlist.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; generated architecture policy only.
  - [ ] Verification: deptrac generator --check.

- [ ] `packages/core/kernel/resources/packaging/installation-catalog.php`
  - [ ] regenerate through the existing installation-catalog generator.
  - [ ] preserve Kernel ownership and generated schema.
  - [ ] MUST NOT introduce a metrics-owned installation catalog.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; generated installation metadata only.
  - [ ] Verification: installation-catalog generation and --check with a valid Composer bootstrap.

Generation and verification:
- [ ] Synchronize workspace Composer repositories through the canonical tooling.
- [ ] Generate the package index through the canonical package-index generator.
- [ ] Generate Deptrac configuration through the canonical Deptrac generator.
- [ ] Generate the installation catalog through the canonical installation-catalog generator.
- [ ] Preserve the existing Deptrac allowlist unless a separate justified policy change is required.
- [ ] Run the existing observability metric catalog gate without introducing any new metric names.
- [ ] Run package-scaffold --check and package identity/manifest gates.
- [ ] Generated artifacts MUST NOT be hand-edited.
- [ ] Missing Composer bootstrap autoload MUST NOT be reported as successful installation-catalog verification.

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/metrics/config/metrics.php`
  - [ ] `packages/platform/metrics/config/rules.php`
- [ ] Root:
  - [ ] `metrics` — already reserved with platform/metrics ownership.
- [ ] Baseline defaults:
  - [ ] `config/metrics.php` returns [].
  - [ ] `config/rules.php` defines an empty strict configuration map.
- [ ] Runtime behavior:
  - [ ] selected platform.metrics always provides stateless DefaultMeter and NullMetricsRenderer.
  - [ ] no backend, collector, exporter, or metrics buffer is activated.
  - [ ] no enabled/disabled switch is required for the noop-only baseline.
- [ ] No keys for:
  - [ ] label allowlists.
  - [ ] metric catalogs.
  - [ ] renderer selection.
  - [ ] sampling.
  - [ ] collectors.
  - [ ] exporters.
  - [ ] metric storage.
  - [ ] ContextStore or UnitOfWork identity.
- [ ] Rules:
  - [ ] canonical Kernel configuration validation only.
  - [ ] no unknown or reserved keys.
  - [ ] no custom validator, config loader, or runtime policy registry.
  - [ ] no automatic modification of application presets or ModulePlan.

#### Wiring / DI tags (when applicable)

- Tags introduced:
  - N/A.
- [ ] ServiceProvider wiring:
  - [ ] Foundation continues to bind MeterPortInterface → NoopMeter when platform.metrics is absent.
  - [ ] explicitly selected platform.metrics binds MeterPortInterface → DefaultMeter.
  - [ ] explicitly selected platform.metrics binds MetricsRendererInterface → NullMetricsRenderer.
  - [ ] use existing declarative ContainerDefinitionBuilder::classService() definitions.
  - [ ] no MetricsServiceFactory is required for the constructor-free noop baseline.
  - [ ] no ConfigRepositoryInterface or ContextAccessorInterface dependency is required.
  - [ ] module/provider registration order remains Kernel-owned.
  - [ ] source and compiled containers MUST resolve equivalent selected services.
  - [ ] no new tags, aliases, reset services, backend registries, or alternative container wiring paths.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads:
  - N/A; no ContextAccessorInterface or ContextKeys consumption.
- Context writes:
  - N/A; no ContextStore writes.
- [ ] UnitOfWork ownership:
  - [ ] Kernel remains the owner of UnitOfWork lifecycle and correlation identity.
  - [ ] metrics baseline does not derive operation labels implicitly from Kernel context.
  - [ ] producer-owned metric labels are supplied explicitly through MeterPortInterface.
- [ ] Reset discipline:
  - [ ] DefaultMeter, AllowedLabelSet, and NullMetricsRenderer are stateless.
  - [ ] no request-local or process-lifetime metric buffer.
  - [ ] no kernel.reset registration or ResetInterface implementation.
  - [ ] no cross-UnitOfWork metric state retention.
  - [ ] repeated calls remain deterministic and independent.

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] N/A; no tracing contract dependency or span emission.
- [ ] Metrics:
  - [ ] no new baseline metric name is introduced.
  - [ ] no metrics.record_total or metrics.record_duration_ms emission.
  - [ ] DefaultMeter performs global label admission and discards all samples.
  - [ ] metric-specific catalog policy remains authoritative through existing observability gates.
  - [ ] no metric collector, exporter, registry, persistence, aggregation, or renderer state.
  - [ ] no self-instrumentation or recursive metrics reporting.
  - [ ] no new metric dimensions or high-cardinality labels.
- [ ] Logs:
  - [ ] N/A; baseline components emit no logs.
  - [ ] invalid metric names, labels, or values MUST NOT produce diagnostic records.
  - [ ] no optional warn-only logger dependency.
  - [ ] raw labels, payloads, tokens, credentials, SQL, and customer data are never emitted.

#### Errors

- Exceptions introduced:
  - N/A.
- [ ] Runtime failure policy:
  - [ ] MeterPortInterface calls remain no-throw for valid contract-typed arguments.
  - [ ] forbidden label keys and invalid label-value types degrade deterministically to skip behavior.
  - [ ] no MetricLabelForbiddenException or equivalent runtime exception class is introduced.
  - [ ] NullMetricsRenderer contentType() and render() remain deterministic and no-throw.
  - [ ] no metrics failure changes primary operation outcome or exception precedence.
  - [ ] programming errors outside the typed public contracts are not misclassified as metric admission failures.
  - [ ] no recursive reporting, raw diagnostics, or hidden fallback collector.

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/raw SQL/payload
  - [ ] raw path as metric label; correlation_id/request_id as metric label
- [ ] Allowed:
  - [ ] only globally allowlisted label keys with contract-valid scalar values.
  - [ ] metric-specific label restrictions remain authoritative for every emitted metric.
  - [ ] safe bounded-cardinality values are producer-owned and MUST NOT be inferred from arbitrary input.
  - [ ] hash/len derivations are not automatically permitted as metric labels.
  - [ ] redaction never authorizes new label keys or unsafe cardinality.
  - [ ] no raw metric input, rejected label, or sensitive value is logged or persisted by the baseline.

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- Context writes:
  - N/A; verify no ContextStore writes or new ContextKeys.
- Reset:
  - N/A; verify no ResetInterface implementation, kernel.reset tag, or mutable metric state.
- Spans:
  - N/A; verify no TracerPortInterface dependency or span emission.
- [ ] Metrics:
  - [ ] `packages/platform/metrics/tests/Unit/AllowedLabelSetRejectsForbiddenKeysTest.php`
  - [ ] `packages/platform/metrics/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `packages/platform/metrics/tests/Contract/AllowedLabelSetMatchesObservabilitySsotContractTest.php`
  - [ ] verify no metric recording, aggregation, buffering, exporting, or self-instrumentation.
- Logs:
  - N/A; verify no log emission or warn-only logger dependency.
- [ ] Redaction:
  - N/A; no redaction service or metric output sink is introduced.
  - [ ] verify forbidden label keys and invalid value types are rejected without exposing input.
  - [ ] verify redaction cannot authorize new metric labels.
- [ ] Runtime DI:
  - [ ] `packages/platform/metrics/tests/Integration/MetricsSelectedModuleBindingParityTest.php`
  - [ ] verify Foundation fallback and selected-module source/compiled parity.
- [ ] Future compatibility:
  - [ ] verify no second metric collector, registry, renderer fallback, context owner, or reset service is introduced.

#### Test harness / fixtures (when integration is needed)

- [ ] Reuse existing Foundation/Kernel declarative DI and ModulePlan test infrastructure.
- [ ] Use deterministic synthetic metric names and allowlisted labels.
- [ ] Use a Foundation-only target to verify the unselected NoopMeter fallback.
- [ ] Use an explicitly selected platform.metrics target to verify DefaultMeter and NullMetricsRenderer bindings.
- [ ] Exercise the same service definitions through source and compiled container paths.
- [ ] No HTTP middleware, HTTP request, endpoint, transport, network, filesystem sink, metrics backend, or exporter is required.
- [ ] No real UnitOfWork lifecycle or ContextStore mutation is required.
- [ ] No additional test container, service registry, or reset orchestrator is introduced.

### Tests (MUST)

- Unit:
  - [ ] `packages/platform/metrics/tests/Unit/AllowedLabelSetRejectsForbiddenKeysTest.php`
    - [ ] exact six-key allowlist.
    - [ ] allowed string, int, and bool label values.
    - [ ] reject null, float, list, map, object, resource, and closure values.
    - [ ] reject trace_id, span_id, correlation_id, request_id, uow_id, raw_path, token, and unknown labels.
    - [ ] empty labels are accepted.
    - [ ] associative-array insertion order does not change admission.
    - [ ] invalid values never invoke Stringable::__toString().
    - [ ] no runtime config, context, logger, or filesystem dependency.
    - [ ] no exceptions, mutations, or emitted diagnostics.

  - [ ] `packages/platform/metrics/tests/Unit/NullMetricsRendererDeterministicTest.php`
    - [ ] exact contentType() = text/plain; charset=utf-8.
    - [ ] render() returns an empty string.
    - [ ] repeated calls return byte-identical values.
    - [ ] no collected metrics, output side effects, context access, or reset dependency.
    - [ ] no exceptions.

- Contract:
  - [ ] `packages/platform/metrics/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
    - [ ] replace the scaffold placeholder assertion with executable behavior tests.
    - [ ] verify DefaultMeter implements MeterPortInterface.
    - [ ] verify increment() and observe() accept contract-valid arguments.
    - [ ] verify forbidden labels are skipped without throwing or emitting diagnostics.
    - [ ] verify no metric state, buffer, exporter, or renderer data is accumulated.
    - [ ] verify repeated calls remain harmless.
    - [ ] verify no ContextStore or reset hooks are introduced.
    - [ ] verify Foundation NoopMeter remains the unselected fallback.

  - [ ] `packages/platform/metrics/tests/Contract/AllowedLabelSetMatchesObservabilitySsotContractTest.php`
    - [ ] verify exact correspondence between AllowedLabelSet and the global label allowlist in docs/ssot/observability.md.
    - [ ] fail when a label key is added, removed, or renamed on either side.
    - [ ] verify metrics-policy.md does not introduce an alternative metric catalog or label-key authority.
    - [ ] verify runtime config cannot extend the allowlist.
    - [ ] verify canonical metric-specific catalog restrictions remain separate.

  - [ ] `packages/platform/metrics/tests/Contract/MetricsModuleComposerMetadataContractTest.php`
    - [ ] verify module ID, package ID, composer name, kind, and config root.
    - [ ] verify exact provider list.
    - [ ] verify Composer dependencies and extra.coretsia.requires.
    - [ ] verify no forbidden source dependency, runtime tag, or stateful service owner.

  - [ ] `packages/platform/metrics/tests/Contract/MetricsConfigRulesContractTest.php`
    - [ ] exact empty metrics defaults.
    - [ ] exact schemaVersion/configRoot/additionalKeys/keys shape.
    - [ ] reject unknown config keys.
    - [ ] verify no enabled, renderer, collector, exporter, or configurable label allowlist keys.

- Integration:
  - [ ] `packages/platform/metrics/tests/Integration/MetricsSelectedModuleBindingParityTest.php`
    - [ ] verify Foundation-only MeterPortInterface resolves NoopMeter.
    - [ ] verify Foundation-only runtime does not claim a MetricsRendererInterface binding.
    - [ ] verify explicit platform.metrics selection resolves DefaultMeter and NullMetricsRenderer.
    - [ ] verify the selected DefaultMeter performs label admission without recording metrics.
    - [ ] verify source and compiled containers preserve equivalent selected bindings.
    - [ ] verify package installation alone does not select the module.
    - [ ] verify no ContextStore writes, new metric catalog, reset service, or active exporter.
    - [ ] reuse the existing Foundation/Kernel DI test harnesses.

- Gates/Arch:
  - [ ] package identity and scaffold gates green.
  - [ ] Composer repository sync check green.
  - [ ] package-index generator check green.
  - [ ] Deptrac generator check green.
  - [ ] observability metric catalog gate green.
  - [ ] installation-catalog generator check green.
  - [ ] dependency matrix and source/compiled definition parity green.
  - [ ] no new unauthorized metric names or labels.

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] Foundation MeterPortInterface remains resolvable through canonical NoopMeter when platform.metrics is absent.
- [ ] Explicit platform.metrics selection resolves DefaultMeter and NullMetricsRenderer in source and compiled containers.
- [ ] DefaultMeter enforces the global label allowlist before deterministic noop completion.
- [ ] No guarantee of MetricsRendererInterface resolution is made for targets not selecting platform.metrics.
- [ ] Metric-specific catalog restrictions remain owned by docs/ssot/observability.md and the existing observability gates.
- [ ] No samples are recorded, buffered, exported, or made queryable by the Phase 3 baseline.
- [ ] No metrics.record_total or metrics.record_duration_ms is introduced.
- [ ] No ContextStore owner, active metric registry, exporter, or reset hook is introduced.
- [ ] No duplicate metrics factory, label policy registry, or runtime exception hierarchy is introduced.
- [ ] Future 6.20.0 recording backend can extend the existing platform/metrics package without recreating package identity, module metadata, or the public metrics ports.
- [ ] Future 6.20.0 reuses the canonical global AllowedLabelSet policy and adds metric-specific runtime validation without introducing a second global allowlist.
- [ ] Future 6.20.0 owns metrics registry lifecycle, cardinality controls, and reset discipline before metric samples can be retained.
- [ ] Future 6.21.0 can override the renderer through the existing MetricsRendererInterface without introducing a second null renderer or HTTP endpoint owner.
- [ ] Phase 3 metrics baseline remains stateless and does not claim recording, export, persistence, or queryable metrics.
- [ ] Canonical package metadata, ADR-0039, metrics-policy.md, generated artifacts, and source/compiled DI tests are complete.
- [ ] No package-local allowlist drift:
  - [ ] runtime enforcement uses the canonical allowlist from `docs/ssot/observability.md`
  - [ ] `docs/ssot/metrics-policy.md` does not redefine label ownership or expand label keys
- [ ] README sections complete

---

### 3.40.0 Platform problem-details (RFC9457 + RFC7807 compatibility) (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.40.0"
owner_path: "packages/platform/problem-details/"

package_id: "platform/problem-details"
composer: "coretsia/platform-problem-details"
kind: runtime
module_id: "platform.problem-details"

goal: "Надати deterministic, fail-closed HTTP Problem Details adaptation поверх канонічного ErrorHandlerInterface та ErrorDescriptor, з безпечним RFC9457 application/problem+json rendering і PSR-15 Throwable boundary; без другого exception mapper, redaction engine, HTTP pipeline owner, observability registry або reset lifecycle."
provides:
- "Reuse canonical platform/errors ErrorHandlerInterface and format-neutral ErrorDescriptor."
- "Transport-specific ErrorDescriptor to Problem Details adaptation without duplicate exception mapping."
- "Deterministic RFC9457 JSON rendering with RFC7807-compatible base fields."
- "PSR-15 outer HTTP Throwable boundary using the existing http.middleware.system_pre tag."
- "Fail-closed public error disclosure without raw descriptor messages, arbitrary extensions, exception traces, or context dumps."
- "Existing platform/errors redaction and reporting remain authoritative."
- "No duplicate error handler, serializer, redactor, logging pipeline, metric catalog, tracer lifecycle, or reset owner."
- "Cutline impact: provides JSON Problem Details output only; future HTML error pages remain outside this epic."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0040-platform-problem-details-http-adaptation.md"
ssot_refs:
- "docs/ssot/problem-details.md"
- "docs/ssot/error-descriptor.md"
- "docs/ssot/errors-boundary.md"
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/sensitive-data-redaction.md"
- "docs/ssot/http-middleware-catalog.md"
- "docs/ssot/runtime-container-definitions.md"
- "docs/ssot/config-roots.md"
- "docs/ssot/tags.md"
---

### Scope boundary (MUST)

- This epic owns HTTP Problem Details adaptation and rendering, not canonical exception normalization.
- `platform/errors` remains the single owner of Throwable mapping, ErrorDescriptor normalization, redaction during normalization, and eligible error reporting.
- `platform/problem-details` consumes ErrorHandlerInterface through the existing contracts-level port.
- Exactly one ErrorHandlerInterface::handle() invocation is permitted for each caught downstream Throwable.
- No second mapper registry, fallback exception mapper, error reporter, or redaction implementation is introduced.
- Problem Details is an HTTP response representation, not a telemetry exporter.
- This epic introduces no trace storage, span recording, metrics recording, error-event stream, or observability backend.
- The Phase 3 public payload contains only safe-by-construction Problem Details members.
- Raw ErrorDescriptor.message, extensions, Throwable details, runtime context, and debug metadata MUST NOT be forwarded automatically.
- `detail`, `instance`, and arbitrary extension members are omitted in the baseline unless a separately approved disclosure policy explicitly permits them.
- RFC9457 is the normative format specification; RFC7807-compatible baseline clients remain supported.
- HTML error pages, content negotiation between JSON and HTML, and custom problem-type URI catalogs remain outside this epic.
- `platform/http` owns middleware execution, pipeline ordering, HTTP request/response lifecycle, and transport integration.
- `platform/problem-details` contributes to the existing HTTP middleware slot without owning that slot.
- The module MUST be explicitly selected in a compatible HTTP target.
- Package installation alone MUST NOT activate HTTP error handling.
- No ContextStore owner, UnitOfWork lifecycle, reset hook, or mutable request-local registry is introduced.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - [ ] Canonical ErrorHandlerInterface and ErrorDescriptor MUST already exist in core/contracts.
  - [ ] Platform/errors canonical ErrorHandler implementation and provider MUST already exist.
  - [ ] Platform/redaction canonical redaction implementation MUST remain available through platform.errors.
  - [ ] Foundation StableJsonEncoder and declarative container-definition APIs MUST already exist.
  - [ ] ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE MUST already exist.
  - [ ] Kernel target-local ModulePlan and source/compiled container definitions MUST exist.
  - [ ] The problem_details config root MUST already be reserved for platform/problem-details.
  - [ ] Epic 3.30.0 package:new metadata extension MUST be available before using --description and --keywords.
  - [ ] ADR-0038 and ADR-0039 MUST be registered before allocating ADR-0040.
  - [ ] A compatible PSR-15 HTTP runtime and PSR response/stream factories are required for complete HTTP pipeline integration verification.
  - [ ] If platform/http has not yet been implemented, standalone middleware tests MAY proceed, but full outermost pipeline integration MUST remain unverified until the HTTP owner is available.

- Required existing implementation paths (MUST; reuse without modification):
  - `packages/core/contracts/src/Observability/Errors/ErrorHandlerInterface.php`
  - `packages/core/contracts/src/Observability/Errors/ErrorDescriptor.php`
  - `packages/core/contracts/src/Observability/Errors/ErrorHandlingContext.php`
  - `packages/platform/errors/src/ErrorHandler.php`
  - `packages/platform/errors/src/Provider/ErrorsServiceProvider.php`
  - `packages/platform/errors/src/Mapper/DefaultExceptionMapper.php`
  - `packages/core/foundation/src/Serialization/StableJsonEncoder.php`
  - `packages/core/foundation/src/Tag/ReservedTags.php`
  - `packages/core/foundation/src/Container/Definition/ContainerDefinitionBuilder.php`
  - `packages/core/foundation/src/Container/Definition/ContainerDefinitionContext.php`
  - `packages/core/foundation/src/Container/Definition/ContainerValueReference.php`
  - `docs/ssot/error-descriptor.md`
  - `docs/ssot/sensitive-data-redaction.md`
  - `docs/ssot/config-roots.md`
  - `docs/ssot/tags.md`

- Required config roots/keys:
  - `problem_details` — existing reserved package-owned root.
  - No public error-detail or debug-disclosure configuration is introduced in this baseline.

- Required config roots registry:
  - `problem_details` MUST remain reserved in docs/ssot/config-roots.md with owner platform/problem-details.
  - This epic MUST NOT introduce another config root.

- Required tags:
  - `http.middleware.system_pre` — existing tag owned by platform/http.
  - Priority `1000` — existing optional problem-details middleware reservation.

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Server\RequestHandlerInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\ResponseFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`

- No direct ContextAccessorInterface, ContextKeys, TracerPortInterface, MeterPortInterface, LoggerInterface, or ResetInterface dependency is introduced by the baseline.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Runtime-only Composer/module requirements:
- `platform/errors` — canonical ErrorHandlerInterface implementation.
- `platform/redaction` is supplied through the existing platform.errors module dependency.

Forbidden:
- `platform/http` production-source imports
- `platform/errors` concrete production-source imports
- `platform/redaction` concrete production-source imports
- `core/kernel` production-source imports
- `platform/logging`
- `platform/tracing`
- `platform/metrics`
- vendor exception-mapping SDK implementations
- vendor tracing or metrics exporters
- duplicate redaction and error-mapping implementations

- Composer/module requirements MUST NOT grant additional direct PHP source-import permissions.
- PSR HTTP interface dependencies are explicitly declared in composer.json.

#### Uses ports (API surface, NOT deps) (MUST)

- Contracts:
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`

- Foundation:
  - `Coretsia\Foundation\Serialization\StableJsonEncoder`
  - `Coretsia\Foundation\Tag\ReservedTags`
  - canonical declarative container-definition APIs

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Server\RequestHandlerInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\ResponseFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`

- No direct platform/errors implementation or mapper-registry dependency.
- No duplicate JSON serializer, redaction engine, logger, metrics backend, tracer, or HTTP pipeline.

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - middleware: `\Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware::class`
  - middleware slots/tags: `http.middleware.system_pre` priority `1000` (meta per owner `platform/http` tag schema, if any)
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

Naming and ownership lock (MUST)

- [ ] The canonical HTTP middleware tag identifier is ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE.
- [ ] Platform/http remains the semantic owner of this middleware slot.
- [ ] Platform/problem-details contributes only a middleware service and one tag registration.
- [ ] No package-local mirror constant, reserved-tag registry, or competing tag owner is introduced.
- [ ] No duplicate ErrorHandlerInterface implementation, exception mapper, redaction engine, JSON encoder, or HTTP pipeline owner is introduced.
- [ ] The package namespace is Coretsia\Platform\ProblemDetails.
- [ ] Module ID is platform.problem-details; config root is problem_details.
- [ ] The package MUST NOT replace ErrorDescriptor or modify its canonical field schema.

#### Creates

Package scaffold:
- [ ] Implementation order (MUST):
  - [ ] Complete the existing 3.30.0 package:new metadata argument extension before invoking the scaffold.
  - [ ] Complete the canonical hyphenated-slug config-path changes in new-package.php, sync_package_scaffold.php, and package_compliance_gate.php.
  - [ ] Verify the extended generator, scaffold synchronization, and compliance gate with their integration tests.
  - [ ] Run the problem-details package scaffold command exactly once after these tooling changes pass.
  - [ ] Do not invoke the unmodified package:new command with unsupported --description or --keywords arguments.
  - [ ] Complete problem-details-specific Composer dependencies and module requirements after canonical scaffold creation.
  - [ ] Preserve generated legal, security, and baseline package files without introducing duplicate configuration files.
- [ ] Run `composer package:new -- --layer=platform --slug=problem-details --kind=runtime --description="Coretsia HTTP Problem Details runtime package: safe error descriptor adaptation, deterministic RFC 9457 JSON rendering, and PSR-15 middleware." --keywords=coretsia,php,framework,problem-details,runtime,http,error-handling,error-descriptor,rfc9457,rfc7807,psr-15,deterministic` exactly once.
- [ ] Epic 3.30.0 metadata argument support MUST be verified before running this command.
- [ ] Preserve the canonical scaffold-generated package structure.
- [ ] Complete generated module, provider, configuration, and contract-test placeholders.
- [ ] Normalize the generated config defaults path to config/problem_details.php through the canonical packaging policy.
- [ ] No second package scaffold or duplicate configuration root is introduced.
- [ ] Package creation MUST NOT automatically enable platform.problem-details in application targets or presets.
- [ ] LICENSE, NOTICE, and SECURITY.md MUST remain byte-identical to their repository-root counterparts.
- [ ] Problem-details-specific disclosure and security restrictions belong to README.md and docs/ssot/problem-details.md.

- [ ] `packages/platform/problem-details/LICENSE`
  - [ ] generated by the existing package scaffold.
  - [ ] MUST remain byte-identical to repository-root LICENSE.
  - [ ] no package-specific license amendments.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; canonical legal file.
  - [ ] Verification: package-scaffold --check.

- [ ] `packages/platform/problem-details/NOTICE`
  - [ ] generated by the existing package scaffold.
  - [ ] MUST remain byte-identical to repository-root NOTICE.
  - [ ] no package-specific notice amendments.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; canonical notice file.
  - [ ] Verification: package-scaffold --check.

- [ ] `packages/platform/problem-details/SECURITY.md`
  - [ ] generated by the existing package scaffold.
  - [ ] MUST remain byte-identical to repository-root SECURITY.md.
  - [ ] MUST NOT introduce package-local security reporting authority.
  - [ ] Problem Details disclosure, HTTP response safety, and redaction restrictions belong to README.md and docs/ssot/problem-details.md.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; canonical security-policy file.
  - [ ] Verification: package-scaffold --check.

- [ ] `packages/platform/problem-details/composer.json`
  - [ ] `name = coretsia/platform-problem-details`.
  - [ ] `type = library`.
  - [ ] `license = Apache-2.0`.
  - [ ] Description and keywords are generated exclusively from explicit package:new arguments.
  - [ ] Authors, homepage, support, and PHP platform follow the canonical package:new metadata policy.
  - [ ] Composer requires:
    - [ ] `php: ^8.4`.
    - [ ] `coretsia/core-contracts: ^0.11.0`.
    - [ ] `coretsia/core-foundation: ^0.11.0`.
    - [ ] `coretsia/platform-errors: ^0.11.0`.
    - [ ] `psr/http-message: ^2.0`.
    - [ ] `psr/http-factory: ^1.0`.
    - [ ] `psr/http-server-middleware: ^1.0`.
    - [ ] `psr/http-server-handler: ^1.0`.
  - [ ] No direct platform/http or concrete platform/redaction implementation dependency.
  - [ ] PSR-4 `Coretsia\Platform\ProblemDetails\` → `src/`.
  - [ ] PSR-4 test namespace → `tests/`.
  - [ ] `extra.coretsia.kind = runtime`.
  - [ ] `extra.coretsia.moduleId = platform.problem-details`.
  - [ ] `extra.coretsia.moduleClass = Coretsia\Platform\ProblemDetails\Module\ProblemDetailsModule`.
  - [ ] `extra.coretsia.providers = [Coretsia\Platform\ProblemDetails\Provider\ProblemDetailsServiceProvider]`.
  - [ ] `extra.coretsia.requires = [core.foundation, platform.errors]`.
  - [ ] `extra.coretsia.conflicts = []`.
  - [ ] `extra.coretsia.defaultsConfigPath = config/problem_details.php`.
  - [ ] The platform.errors dependency supplies canonical error mapping and redaction through its existing module requirements.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; Composer metadata only.
  - [ ] Verification: package identity, manifest, dependency, scaffold, and Composer metadata gates.

- [ ] `packages/platform/problem-details/src/Module/ProblemDetailsModule.php`
  - [ ] final runtime module-metadata class.
  - [ ] public typed constants:
    - [ ] `MODULE_ID = 'platform.problem-details'`.
    - [ ] `PACKAGE_ID = 'platform/problem-details'`.
    - [ ] `COMPOSER_PACKAGE = 'coretsia/platform-problem-details'`.
    - [ ] `KIND = 'runtime'`.
    - [ ] `CONFIG_ROOT = 'problem_details'`.
  - [ ] Metadata methods follow the existing ErrorsModule shape.
  - [ ] `providers()` returns only ProblemDetailsServiceProvider::class.
  - [ ] No runtime service construction, request handling, config loading, metrics, spans, logging, or reset ownership.
  - [ ] Verification: Composer metadata and module identity parity.

- [ ] `packages/platform/problem-details/src/Provider/ProblemDetailsServiceProvider.php`
  - [ ] final class implementing ServiceProviderInterface and ContainerDefinitionProviderInterface.
  - [ ] `register()` calls assertDefinitionProviderRegistrationAllowed() and registerDefinitionProvider($this).
  - [ ] `define()` is the single declarative wiring source.
  - [ ] require the existing ErrorHandlerInterface binding.
  - [ ] require PSR ResponseFactoryInterface and StreamFactoryInterface services from the selected compatible HTTP target.
  - [ ] register ErrorDescriptorToProblemDetailsAdapter and ProblemDetailsJsonRenderer.
  - [ ] register HttpErrorHandlingMiddleware through constructor dependencies.
  - [ ] use ContainerValueReference::service() for required service references.
  - [ ] contribute one ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE tag at priority 1000.
  - [ ] use the existing ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE identifier and priority 1000.
  - [ ] use an empty tag metadata map while the platform/http owner has not defined a normative metadata schema.
  - [ ] platform/problem-details package ownership is declared by the canonical HTTP middleware catalog, not invented through a tag metadata key.
  - [ ] MUST NOT introduce owner, path, mode, or other package-local metadata keys without an explicit platform/http-owned contract.
  - [ ] if platform/http defines a normative metadata schema before implementation, consume that exact schema without introducing a competing one.
  - [ ] no alternative ErrorHandler, mapper, reporter, redactor, serializer, or service locator.
  - [ ] no runtime closures, imperative parallel DI graph, or mutable provider state.
  - [ ] module selection and provider order remain Kernel-owned.
  - [ ] source and compiled definitions MUST be equivalent.
  - [ ] Verification: provider descriptors, required services, exact tag, selected/unselected module behavior, and source/compiled parity.

- [ ] `packages/platform/problem-details/config/problem_details.php`
  - [ ] returns only the problem_details root subtree.
  - [ ] exact Phase 3 baseline result is [].
  - [ ] no show_detail, show_detail_in_local, raw exception disclosure, or debug switch.
  - [ ] no transport-specific secrets, runtime state, HTTP objects, or service instances.
  - [ ] no environment reads, executable validators, or callbacks.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; defaults-only file.
  - [ ] Verification: exact defaults shape and reserved-root ownership.

- [ ] `packages/platform/problem-details/config/rules.php`
  - [ ] schemaVersion = 1.
  - [ ] configRoot = problem_details.
  - [ ] additionalKeys = false.
  - [ ] keys = [].
  - [ ] no configurable disclosure, redaction, exception mapping, or serializer policy.
  - [ ] unknown keys are rejected through the existing Kernel ConfigValidator.
  - [ ] no duplicate config validator or custom schema types.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; declarative rules only.
  - [ ] Verification: canonical ruleset shape and invalid-key rejection.

- [ ] `packages/platform/problem-details/src/ProblemDetails.php`
  - [ ] final readonly HTTP response-model class.
  - [ ] distinct from the format-neutral ErrorDescriptor.
  - [ ] baseline public members are type, title, and status.
  - [ ] type defaults to the fixed safe URI reference about:blank.
  - [ ] status is restricted to an accepted HTTP error status in 400..599.
  - [ ] title is derived internally from status through one fixed canonical HTTP status/title mapping.
  - [ ] public construction accepts only an accepted HTTP error status; it MUST NOT accept caller-provided title, detail, instance, extensions, or arbitrary public text.
  - [ ] standard HTTP error statuses use fixed English status phrases consistent with RFC 9110.
  - [ ] non-standard error statuses within 400..599 use exactly HTTP Error.
  - [ ] status 500 uses exactly Internal Server Error.
  - [ ] the mapping MUST NOT depend on ErrorDescriptor, Throwable, HTTP request, localization, environment, or application configuration.
  - [ ] no second status/title lookup table or public title override is introduced.
  - [ ] no raw exception message or descriptor extension is copied automatically.
  - [ ] no arbitrary detail, instance, or extension fields in the baseline.
  - [ ] no Throwable, ErrorHandlingContext, request, response, or ContextStore reference is retained.
  - [ ] no clocks, random IDs, environment access, logging, spans, metrics, or reset ownership.
  - [ ] expose a deterministic safe map for the canonical renderer.
  - [ ] Verification: immutable shape, allowed statuses, safe fields, ordering independence, and no data disclosure.

- [ ] `packages/platform/problem-details/src/Adapter/ErrorDescriptorToProblemDetailsAdapter.php`
  - [ ] final stateless adapter consuming the existing ErrorDescriptor.
  - [ ] map(ErrorDescriptor $descriptor): ProblemDetails.
  - [ ] use the descriptor's httpStatus only as a transport status hint.
  - [ ] null, informational, redirection, or unsupported status hints produce a fixed safe HTTP 500 fallback.
  - [ ] HTTP status adaptation:
    - [ ] accepted status hints are integers in the inclusive range 400..599.
    - [ ] null and status hints outside 400..599 produce HTTP 500.
    - [ ] construct ProblemDetails using only the validated or fallback status.
    - [ ] ProblemDetails owns the single canonical status/title mapping.
    - [ ] the adapter MUST NOT accept, derive, or forward an arbitrary title.
    - [ ] the adapter MUST NOT introduce a second HTTP status/title table.
    - [ ] no ErrorDescriptor message, code, extensions, request data, or Throwable content influences the public title.
    - [ ] mapping is deterministic and independent of locale, environment, and configuration.
  - [ ] use about:blank instead of deriving a problem-type URI from arbitrary error codes.
  - [ ] do not copy message, extensions, severity, or schemaVersion into the HTTP response.
  - [ ] no new exception mapper, error-code catalog, or descriptor transformation pipeline.
  - [ ] no ContextAccessorInterface, ContextStore, LoggerInterface, or redactor implementation dependency.
  - [ ] no spans, metrics, logs, mutable caches, or reset hooks.
  - [ ] invalid adapter behavior fails closed to a safe internal-error problem.
  - [ ] Verification: status mapping, fallback, title stability, and absence of raw descriptor fields.

- [ ] `packages/platform/problem-details/src/Renderer/ProblemDetailsJsonRenderer.php`
  - [ ] final stateless JSON renderer.
  - [ ] render(ProblemDetails $problem): string.
  - [ ] contentType(): string returns application/problem+json.
  - [ ] use StableJsonEncoder::encodeStableMap() as the canonical JSON writer.
  - [ ] output is deterministic, with recursive map-key sorting and preserved list ordering.
  - [ ] output has a final LF and no runtime-dependent formatting.
  - [ ] no direct ad-hoc json_encode() in renderer logic.
  - [ ] no floats, NaN, INF, arbitrary objects, Throwable values, or raw nested payloads.
  - [ ] do not introduce a new renderer interface, serializer registry, or JSON normalization pipeline.
  - [ ] no HTTP response construction inside the JSON renderer.
  - [ ] no context reads/writes, spans, metrics, logs, or reset ownership.
  - [ ] Verification: exact Content-Type, RFC9457 fields, stable JSON, invalid data, and repeatability.

- [ ] `packages/platform/problem-details/src/Http/Middleware/HttpErrorHandlingMiddleware.php`
  - [ ] final PSR-15 middleware implementing MiddlewareInterface.
  - [ ] exact namespace Coretsia\Platform\ProblemDetails\Http\Middleware.
  - [ ] process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface.
  - [ ] delegate a successful downstream request unchanged.
  - [ ] catch Throwable from downstream processing at the outer HTTP error boundary.
  - [ ] invoke canonical ErrorHandlerInterface::handle() exactly once for the captured Throwable.
  - [ ] adapt only the returned normalized ErrorDescriptor.
  - [ ] render the resulting ProblemDetails through ProblemDetailsJsonRenderer.
  - [ ] construct the HTTP response through the injected PSR response and stream factories.
  - [ ] actual response status MUST equal the problem body status.
  - [ ] set Content-Type to application/problem+json.
  - [ ] apply a safe no-store caching policy for generated error responses.
  - [ ] do not forward raw request headers, cookies, body, exception message, trace, or descriptor extensions.
  - [ ] Emergency response policy:
    - [ ] failures of ErrorHandlerInterface, the adapter, or the renderer MUST NOT trigger a second ErrorHandlerInterface::handle() invocation.
    - [ ] an emergency response uses HTTP 500 and the fixed safe Problem Details representation.
    - [ ] the emergency JSON body is exactly {"status":500,"title":"Internal Server Error","type":"about:blank"} followed by LF.
    - [ ] emergency response Content-Type is application/problem+json.
    - [ ] emergency response headers include Cache-Control: no-store.
    - [ ] the emergency body is a fixed canonical byte sequence, not arbitrary error data or a second JSON serialization implementation.
    - [ ] a failed renderer MUST NOT be recursively invoked to render its own failure.
    - [ ] no raw Throwable, ErrorDescriptor, rendering exception, request data, or context metadata is included.
    - [ ] response and stream construction use the injected PSR factories.
    - [ ] failure of response or stream construction preserves the original downstream Throwable precedence.
    - [ ] no additional mapper, reporter, renderer registry, or exception hierarchy is introduced.
  - [ ] do not call ErrorHandlerInterface again when an adapter or renderer fails.
  - [ ] a response-factory failure MUST NOT be misrepresented as a successful safe response.
  - [ ] when no valid response can be produced, preserve the original Throwable precedence rather than substituting an internal rendering exception.
  - [ ] no second exception mapping, reporter, logger, redactor, or JSON serializer.
  - [ ] no implicit ContextStore reads/writes or Kernel UoW lifecycle ownership.
  - [ ] no additional spans, metrics, warning logs, or reset hooks.
  - [ ] Verification: success pass-through, Throwable mapping, fallback, status/body parity, output safety, and primary-exception precedence.

Documentation:
- [ ] `packages/platform/problem-details/README.md`
  - [ ] package identity, namespace, module ID, dependencies, and exact supported HTTP functionality.
  - [ ] explain ErrorHandlerInterface and ErrorDescriptor reuse.
  - [ ] explain why this package is an HTTP adapter rather than a second exception handler.
  - [ ] document RFC9457 baseline and RFC7807-compatible output.
  - [ ] document the exact type, title, and status response members.
  - [ ] document omitted detail, instance, and arbitrary extensions in the Phase 3 baseline.
  - [ ] explain safe fallback for null or invalid HTTP status hints.
  - [ ] document PSR-15 middleware selection and priority 1000.
  - [ ] document compatible HTTP target and response/stream factory requirements.
  - [ ] document no direct platform/http implementation dependency.
  - [ ] document that HTML error pages remain outside this epic.
  - [ ] include synthetic error-response examples with exact status, Content-Type, and body.
  - [ ] include canonical Observability, Errors, and Security / Redaction sections.
  - [ ] no claims of new tracing, metrics, error reporting, debug disclosure, or persistent telemetry.
  - [ ] reference docs/ssot/problem-details.md and docs/ssot/error-descriptor.md.
  - [ ] vulnerability reporting follows the unmodified canonical SECURITY.md.

- [ ] `docs/ssot/problem-details.md`
  - [ ] canonical SSoT header, ssotVersion = 1, owner = platform/problem-details.
  - [ ] define the exact RFC9457 JSON baseline and RFC7807 compatibility boundary.
  - [ ] define the exact HTTP Problem Details response schema.
  - [ ] define accepted status hints, fallback status, and status/title mapping.
  - [ ] define type = about:blank and excluded public fields.
  - [ ] define the distinction between ErrorDescriptor and HTTP ProblemDetails.
  - [ ] prohibit raw Throwable and arbitrary descriptor field forwarding.
  - [ ] define stable JSON encoding and final-LF behavior.
  - [ ] define error boundary, fallback, and primary-exception precedence.
  - [ ] define exact Content-Type and response status parity.
  - [ ] define module selection and middleware priority.
  - [ ] preserve platform/http ownership of middleware execution.
  - [ ] preserve platform/errors ownership of normalization and reporting.
  - [ ] preserve shared redaction policy without introducing another redaction engine.
  - [ ] define no ContextStore writes, spans, metrics, logs, or reset hooks.
  - [ ] include deterministic synthetic examples and security acceptance tests.
  - [ ] no duplicate ErrorDescriptor field schema or competing HTTP middleware catalog.

- [ ] `docs/adr/ADR-0040-platform-problem-details-http-adaptation.md`
  - [ ] ADR version 1 and platform/problem-details ownership.
  - [ ] record canonical ErrorHandlerInterface and ErrorDescriptor reuse.
  - [ ] record why a transport-specific ProblemDetails model is necessary.
  - [ ] record why no second ErrorHandler, renderer interface, redactor, serializer, or exception hierarchy is introduced.
  - [ ] record RFC9457 adoption and RFC7807 compatibility.
  - [ ] record safe-by-construction response disclosure.
  - [ ] record PSR-15 middleware and platform/http slot ownership.
  - [ ] record exact module and runtime dependency boundaries.
  - [ ] record deterministic fallback, source/compiled parity, and no runtime state.
  - [ ] record deferred HTML rendering and richer public problem metadata.

#### Modifies

- [ ] `docs/architecture/DEPENDENCIES.md`
  - [ ] add exactly one materialized package row for platform/problem-details.
  - [ ] direct production source dependencies: core/contracts, core/foundation.
  - [ ] platform/errors is a runtime-only Composer/module edge, not a direct PHP source-import permission.
  - [ ] preserve strcmp package-id ordering.
  - [ ] generated Deptrac policy consumes the canonical dependency row.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; architecture documentation only.
  - [ ] Verification: dependency matrix and generated Deptrac policy consistency.

- [ ] `composer.json`
  - [ ] register coretsia/platform-problem-details: 0.11.x-dev under the current monorepo require-dev baseline.
  - [ ] synchronize the managed path repository through existing tooling.
  - [ ] preserve existing package references and deterministic ordering.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; workspace metadata only.
  - [ ] Verification: Composer repositories synchronization and package identity gates.

- [ ] `docs/adr/INDEX.md`
  - [ ] register docs/adr/ADR-0040-platform-problem-details-http-adaptation.md exactly once.
  - [ ] preserve canonical title, owner, adrVersion, and scope formatting.
  - [ ] preserve strcmp ordering and no-forward-reference policy.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; navigation only.
  - [ ] Verification: ADR index consistency.

- [ ] `docs/ssot/INDEX.md`
  - [ ] register docs/ssot/problem-details.md exactly once.
  - [ ] use canonical title, owner, ssotVersion, and scope formatting.
  - [ ] preserve deterministic ordering and no-forward-reference policy.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; navigation only.
  - [ ] Verification: SSoT index consistency.

- [ ] `docs/ssot/http-middleware-catalog.md`
  - [ ] preserve the existing priority-1000 problem-details reservation.
  - [ ] clarify that registration requires explicit selection in a compatible HTTP target.
  - [ ] package installation alone MUST NOT activate HttpErrorHandlingMiddleware.
  - [ ] preserve platform/http ownership of slot execution and metadata semantics.
  - [ ] no new HTTP tag, route, middleware registry, or pipeline implementation.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; documentation-only change.
  - [ ] Verification: exact reservation owner, class, priority, and module-selection semantics.

- [ ] `docs/ssot/errors-boundary.md`
  - [ ] preserve platform/errors as the canonical Throwable normalization and error reporting owner.
  - [ ] preserve platform/problem-details as the HTTP adaptation and rendering owner.
  - [ ] replace installation-implies-registration wording with explicit target-local module selection requirements.
  - [ ] package installation alone MUST NOT activate HttpErrorHandlingMiddleware.
  - [ ] selected platform.problem-details contributes one existing http.middleware.system_pre registration at priority 1000.
  - [ ] preserve platform/http ownership of middleware execution and pipeline ordering.
  - [ ] document RFC9457 as the normative Problem Details specification with RFC7807-compatible baseline fields.
  - [ ] document that ErrorHandlerInterface::handle() is invoked once for a captured downstream Throwable.
  - [ ] preserve format-neutral ErrorDescriptor ownership and prohibit duplicate exception mapping.
  - [ ] no second redaction engine, error reporting pipeline, context owner, or reset orchestration.
  - [ ] Context/UoW: N/A; documentation-only change.
  - [ ] Spans/metrics/logs: N/A; no new production instrumentation.
  - [ ] Reset: N/A; no lifecycle change.
  - [ ] Verification: consistency with problem-details.md, http-middleware-catalog.md, and selected-module DI behavior.

- [ ] `tools/build/new-package.php`
  - [ ] preserve the existing generator and its atomic package creation behavior.
  - [ ] generate a valid config defaults filename for kebab-case runtime package slugs.
  - [ ] map hyphens to underscores in generated config-root filenames.
  - [ ] for problem-details, generate defaultsConfigPath = config/problem_details.php.
  - [ ] do not change the canonical module ID or package slug.
  - [ ] no package-specific filename exception or second config-path registry.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; repository tooling only.
  - [ ] Verification: runtime scaffold with a hyphenated slug and canonical config path.

- [ ] `tools/build/sync_package_scaffold.php`
  - [ ] validate the canonical defaultsConfigPath declared in package metadata.
  - [ ] use the validated declared defaults path rather than assuming config/<slug>.php.
  - [ ] preserve symlink/path safety and atomic scaffold behavior.
  - [ ] ensure one canonical defaults file is required, not a hyphen/underscore duplicate.
  - [ ] preserve byte-identical LICENSE, NOTICE, and SECURITY.md gates.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; repository tooling only.
  - [ ] Verification: package-scaffold --check passes with config/problem_details.php and rejects missing or malformed canonical paths.

- [ ] `tools/gates/package_compliance_gate.php`
  - [ ] preserve the existing canonical package-compliance gate and its diagnostic codes.
  - [ ] validate runtime defaultsConfigPath against the canonical config-root filename policy.
  - [ ] support kebab-case package slugs whose config root uses underscores.
  - [ ] for platform/problem-details, accept exactly config/problem_details.php.
  - [ ] preserve moduleId = platform.problem-details independently of configRoot = problem_details.
  - [ ] do not require an additional config/problem-details.php file.
  - [ ] validate the declared defaultsConfigPath before any filesystem access.
  - [ ] reject absolute paths, traversal, symlinks, malformed paths, and mismatched metadata.
  - [ ] preserve existing package namespace, module, provider, and configuration validations.
  - [ ] preserve existing behavior for packages whose slug and config-root spelling already match.
  - [ ] no package-specific exception, alternate config registry, or second compliance validator is introduced.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A; repository compliance tooling only.
  - [ ] Verification: canonical config-path acceptance, malformed-path rejection, existing fixture compatibility, and package-compliance gate green.

- [ ] `tools/tests/Integration/PackageComplianceGateAcceptsGoodFixtureTest.php`
  - [ ] add a valid runtime fixture using a hyphenated package slug.
  - [ ] verify moduleId = platform.problem-details and defaultsConfigPath = config/problem_details.php.
  - [ ] verify the canonical underscore defaults file is accepted without a duplicate hyphenated file.
  - [ ] preserve existing non-hyphenated runtime fixture acceptance.

- [ ] `tools/tests/Integration/PackageComplianceGateRejectsBadFixtureTest.php`
  - [ ] reject mismatched defaultsConfigPath metadata.
  - [ ] reject path traversal, absolute paths, and invalid config-root filenames.
  - [ ] reject missing canonical defaults files.
  - [ ] verify rejection does not require creating a second defaults file.
  - [ ] preserve existing deterministic diagnostic behavior.

- [ ] `tools/tests/Integration/NewPackageAtomicCreationTest.php`
  - [ ] add a hyphenated runtime-slug fixture.
  - [ ] verify moduleId preserves the original slug while defaultsConfigPath uses the canonical config-root filename.
  - [ ] verify no duplicate hyphenated defaults file is generated.
  - [ ] verify existing package:new invocations remain compatible.
  - [ ] preserve atomic failure and cleanup tests.

- [ ] `tools/tests/Integration/SyncPackageScaffoldCheckRejectsDriftTest.php`
  - [ ] verify the canonical metadata-defined defaults path is checked.
  - [ ] reject missing or malformed defaults paths.
  - [ ] verify no obsolete slug-derived defaults file is required.

- [ ] `tools/testing/package-index.php`
  - [ ] regenerate through the canonical package-index generator.
  - [ ] no manual generated-table edits.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A.
  - [ ] Verification: package-index --check.

- [ ] `tools/testing/deptrac.yaml`
  - [ ] regenerate through the canonical Deptrac generator.
  - [ ] no separate problem-details dependency allowlist.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A.
  - [ ] Verification: deptrac --check.

- [ ] `packages/core/kernel/resources/packaging/installation-catalog.php`
  - [ ] regenerate through the canonical installation-catalog generator.
  - [ ] preserve Kernel ownership and schema.
  - [ ] no package-local installation catalog.
  - [ ] Context/UoW, spans, metrics, logs, reset: N/A.
  - [ ] Verification: installation-catalog --check with valid Composer bootstrap.

Generation and verification:
- [ ] Synchronize Composer repositories through canonical tooling.
- [ ] Generate the package index through the existing generator.
- [ ] Generate Deptrac configuration through the existing generator.
- [ ] Generate the installation catalog through the existing generator.
- [ ] Run package identity, manifest, scaffold, dependency, and HTTP middleware catalog checks.
- [ ] Preserve the existing Deptrac allowlist.
- [ ] Do not edit generator-owned artifacts manually.
- [ ] Missing Composer bootstrap or unavailable HTTP runtime MUST NOT be reported as successful end-to-end verification.

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/problem-details/config/problem_details.php`
  - [ ] `packages/platform/problem-details/config/rules.php`
- [ ] Root:
  - [ ] `problem_details` — existing reserved package-owned root.
- [ ] Baseline defaults:
  - [ ] config/problem_details.php returns [].
  - [ ] config/rules.php defines a strict empty map.
- [ ] No keys for:
  - [ ] show_detail.
  - [ ] show_detail_in_local.
  - [ ] raw exception disclosure.
  - [ ] stacktrace output.
  - [ ] arbitrary extensions.
  - [ ] problem type registry.
  - [ ] renderer selection.
  - [ ] custom exception mapping.
  - [ ] redaction policy overrides.
- [ ] Rules:
  - [ ] existing Kernel configuration validation only.
  - [ ] unknown keys are rejected.
  - [ ] no executable validator or environment-derived debug policy.
  - [ ] no automatic modification of application presets or ModulePlan.

#### Wiring / DI tags (when applicable)

- Tags introduced:
  - N/A.
- [ ] Existing tag reuse:
  - [ ] ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE.
  - [ ] priority = 1000.
  - [ ] registered middleware package owner = platform/problem-details in the canonical HTTP middleware catalog.
  - [ ] tag meta = [] until the platform/http owner explicitly defines a non-empty metadata schema.
  - [ ] no package-local mirror constant or new tag registry.
- [ ] ServiceProvider wiring:
  - [ ] consume the existing ErrorHandlerInterface binding from platform.errors.
  - [ ] register ErrorDescriptorToProblemDetailsAdapter.
  - [ ] register ProblemDetailsJsonRenderer.
  - [ ] register HttpErrorHandlingMiddleware.
  - [ ] consume PSR response and stream factories from the selected compatible HTTP target.
  - [ ] contribute exactly one existing middleware tag registration.
  - [ ] no duplicate ErrorHandler, mapper, reporter, redactor, serializer, or runtime factory.
  - [ ] source and compiled service definitions MUST be equivalent.
  - [ ] unselected module MUST NOT contribute middleware services or tags.
  - [ ] no new reset, lifecycle, metrics, or tracing tags.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads:
  - N/A; no ContextAccessorInterface or ContextKeys consumption.
- Context writes:
  - N/A; no ContextStore writes.
- [ ] UnitOfWork ownership:
  - [ ] Kernel remains the owner of UnitOfWork lifecycle and correlation identity.
  - [ ] ErrorHandlerInterface remains responsible for its existing safe correlation enrichment.
  - [ ] problem-details does not derive public identifiers from Kernel context.
- [ ] Reset discipline:
  - [ ] ProblemDetails, adapter, renderer, provider, and middleware are stateless.
  - [ ] no request-local mutable registry or process-lifetime output cache.
  - [ ] no ResetInterface implementation or kernel.reset registration.
  - [ ] repeated rendering is deterministic and independent across requests.

#### Observability (policy-compliant)

- Spans:
  - N/A; no http.error.render span is introduced.
- Metrics:
  - N/A; no http.error_total or http.error_duration_ms is introduced.
- Logs:
  - N/A; no duplicate handled_error/fatal_error logging pipeline.
- [ ] Existing error observability:
  - [ ] platform.errors remains the owner of error reporting, existing spans, metrics, and diagnostics.
  - [ ] problem-details MUST NOT report or instrument the same Throwable a second time.
  - [ ] renderer and middleware failures MUST NOT trigger recursive self-instrumentation.

#### Errors

- New exception classes:
  - N/A.
- [ ] Runtime failure policy:
  - [ ] ErrorHandlerInterface::handle() is invoked once for each captured downstream Throwable.
  - [ ] no second exception mapping is performed.
  - [ ] absent HTTP status hint produces a fixed HTTP 500 fallback.
  - [ ] adapter or renderer failure produces a fixed safe fallback where response construction remains possible.
  - [ ] no raw Throwable, descriptor message, previous exception, or stack trace is serialized.
  - [ ] no rendering failure is reported as a new primary application error.
  - [ ] a response-factory failure cannot be claimed as successful no-throw response construction.
  - [ ] when response construction is impossible, preserve the original Throwable precedence.
  - [ ] successful requests pass through unchanged.

#### Security / Redaction

- [ ] Public Problem Details is safe by construction.
- [ ] Existing platform.errors redaction and error normalization remain authoritative.
- [ ] No independent sensitive-key classifier, redactor, hash engine, or masking implementation.
- [ ] The baseline does not export arbitrary descriptor message or extensions.
- [ ] No raw authorization headers, cookies, session IDs, tokens, SQL, payloads, customer identifiers, or local paths are exposed.
- [ ] No arbitrary hash/len summaries or actor identifiers are added to public error responses.
- [ ] Redaction is not sufficient authorization to expose arbitrary diagnostic values.
- [ ] Public response fields, status, Content-Type, and output size are governed by the package's fixed safe schema.
- [ ] Any future detail, instance, or extension disclosure requires an explicit owner-approved policy and corresponding security tests.

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- Context writes:
  - N/A; verify no ContextStore writes or new ContextKeys.
- Reset:
  - N/A; verify no ResetInterface implementation, kernel.reset tag, or mutable request-local state.
- Spans:
  - N/A; no new span name or tracing instrumentation.
- Metrics:
  - N/A; no new metric name or metrics recording.
- Logs:
  - N/A; canonical platform.errors reporting remains authoritative.
- [ ] Errors:
  - [ ] `packages/platform/problem-details/tests/Unit/ErrorDescriptorToProblemDetailsAdapterTest.php`
  - [ ] `packages/platform/problem-details/tests/Integration/HttpErrorMiddlewareReturnsProblemJsonTest.php`
  - [ ] verify ErrorHandlerInterface::handle() is invoked exactly once per captured downstream Throwable.
  - [ ] verify no duplicate mapper, reporter, or exception hierarchy.
  - [ ] verify primary-exception precedence when HTTP response construction fails.
- [ ] Security / Redaction:
  - [ ] `packages/platform/problem-details/tests/Integration/ProblemDetailsNeverExposesDescriptorMessageTest.php`
  - [ ] `packages/platform/problem-details/tests/Contract/ProblemDetailsJsonSchemaContractTest.php`
  - [ ] verify raw exception messages, descriptor extensions, request payloads, and private metadata are absent.
  - [ ] verify emergency fallback is safe and deterministic.
- [ ] Runtime DI:
  - [ ] `packages/platform/problem-details/tests/Contract/ProblemDetailsProviderDefinitionsParityTest.php`
  - [ ] `packages/platform/problem-details/tests/Integration/ProblemDetailsSelectedModuleBindingParityTest.php`
  - [ ] verify exact middleware tag, priority, module selection, and source/compiled parity.
- [ ] Package configuration:
  - [ ] verify config/problem_details.php is the only canonical defaults file.
  - [ ] verify scaffold, package compliance, manifest, and configuration gates.

#### Test harness / fixtures (when integration is needed)

- [ ] Reuse existing Foundation/Kernel declarative DI test infrastructure.
- [ ] Use deterministic synthetic ErrorDescriptor and Throwable fixtures.
- [ ] Use a controlled ErrorHandlerInterface test double for invocation-count and failure-isolation cases.
- [ ] Use PSR-7 response/stream factory and PSR-15 request-handler test doubles.
- [ ] No real network listener, external HTTP server, logs, metrics backend, or exporter is required.
- [ ] No real ContextStore mutation or UnitOfWork lifecycle is required.
- [ ] Full platform/http pipeline verification remains mandatory once its runtime owner is available.

### Tests (MUST)

- Unit:
  - [ ] `packages/platform/problem-details/tests/Unit/JsonRendererDeterministicOrderTest.php`
    - [ ] exact application/problem+json Content-Type.
    - [ ] deterministic stable JSON map ordering.
    - [ ] final LF and identical bytes for repeated rendering.
    - [ ] reject unsupported floats, objects, and malformed values.
    - [ ] no raw descriptor fields or output side effects.
    - [ ] no additional serializer, metrics, spans, logs, or reset state.

  - [ ] `packages/platform/problem-details/tests/Unit/ErrorDescriptorToProblemDetailsAdapterTest.php`
    - [ ] canonical ErrorDescriptor input.
    - [ ] accepted error-status hints and deterministic title mapping.
    - [ ] verify canonical status/title pairs for standard 4xx and 5xx statuses.
    - [ ] verify non-standard 4xx/5xx status hints use the fixed HTTP Error title.
    - [ ] verify null and non-error status hints use HTTP 500 and Internal Server Error.
    - [ ] verify title selection does not consume descriptor message, code, extensions, or request data.
    - [ ] null, informational, redirection, or unsupported status hints use HTTP 500 fallback.
    - [ ] exact about:blank type.
    - [ ] raw message, severity, extensions, and schemaVersion are not forwarded.
    - [ ] deterministic output and no exception-mapper duplication.

- Contract:
  - [ ] `packages/platform/problem-details/tests/Contract/ProblemDetailsJsonSchemaContractTest.php`
    - [ ] exact type, title, and status baseline members.
    - [ ] direct ProblemDetails construction cannot accept an arbitrary public title.
    - [ ] canonical title is derived exclusively from the accepted status.
    - [ ] standard, non-standard, and fallback status/title combinations follow one fixed policy.
    - [ ] no alternative title or arbitrary public text can be injected through ProblemDetails construction.
    - [ ] no arbitrary detail, instance, or extension members.
    - [ ] body status matches the actual HTTP response status.
    - [ ] RFC9457 baseline with RFC7807-compatible fields.
    - [ ] no secret, PII, diagnostic, or raw Throwable disclosure.

  - [ ] `packages/platform/problem-details/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
    - [ ] replace the scaffold placeholder assertion with executable behavior tests.
    - [ ] adapter and renderer preserve deterministic safe fallback behavior.
    - [ ] no duplicate error reporter, metrics, spans, logs, or reset hooks.
    - [ ] no ContextStore writes or mutable registry state.

  - [ ] `packages/platform/problem-details/tests/Contract/ProblemDetailsModuleComposerMetadataContractTest.php`
    - [ ] exact package, module ID, namespace, config root, providers, and requirements.
    - [ ] exact PSR HTTP dependencies.
    - [ ] platform.errors runtime dependency and no concrete implementation imports.
    - [ ] no forbidden Kernel or platform/http production-source imports.

  - [ ] `packages/platform/problem-details/tests/Contract/ProblemDetailsProviderDefinitionsParityTest.php`
    - [ ] exact canonical middleware tag and priority.
    - [ ] tag metadata remains empty unless required by the canonical platform/http-owned schema.
    - [ ] no package-local owner, path, or mode metadata key is invented.
    - [ ] middleware ownership and priority match docs/ssot/http-middleware-catalog.md.
    - [ ] required ErrorHandlerInterface and PSR factory references.
    - [ ] no closure, service locator, or second exception handler.
    - [ ] source and compiled definition descriptors match.

  - [ ] `packages/platform/problem-details/tests/Contract/ProblemDetailsConfigRulesContractTest.php`
    - [ ] exact empty defaults.
    - [ ] exact strict ruleset shape.
    - [ ] unknown debug, detail, and renderer keys are rejected.
    - [ ] canonical config path and root identity are consistent.

- Integration:
  - [ ] `packages/platform/problem-details/tests/Integration/HttpErrorMiddlewareReturnsProblemJsonTest.php`
    - [ ] successful request delegates unchanged.
    - [ ] downstream Throwable invokes canonical ErrorHandlerInterface exactly once.
    - [ ] deterministic status, headers, and response body.
    - [ ] adapter and renderer failure fallbacks.
    - [ ] ErrorHandlerInterface failure uses the exact fixed emergency body without retrying handle().
    - [ ] emergency response status, Content-Type, and Cache-Control match the normative fallback policy.
    - [ ] response or stream factory failure preserves the original downstream Throwable.
    - [ ] emergency output never contains exception messages, previous Throwable details, or request payloads.
    - [ ] no recursive fallback rendering or duplicate error reporting occurs.
    - [ ] response-factory failure preserves primary exception precedence.
    - [ ] no duplicate error reporting or additional metrics/spans/logs.

  - [ ] `packages/platform/problem-details/tests/Integration/ProblemDetailsNeverExposesDescriptorMessageTest.php`
    - [ ] synthetic descriptor containing sensitive-looking message and extensions.
    - [ ] no raw message or extension values in public JSON.
    - [ ] no raw request or exception data in fallback output.
    - [ ] no public detail or debug mode enabled by default.
    - [ ] identical safe disclosure policy across repeated requests.

  - [ ] `packages/platform/problem-details/tests/Integration/ProblemDetailsSelectedModuleBindingParityTest.php`
    - [ ] selected HTTP target resolves canonical ErrorHandlerInterface and problem-details middleware.
    - [ ] unselected target does not contribute problem-details middleware or tags.
    - [ ] exact priority-1000 registration.
    - [ ] source and compiled containers preserve equivalent service definitions.
    - [ ] reuse existing Foundation/Kernel DI test infrastructure.
    - [ ] verify full HTTP pipeline behavior only when the compatible HTTP runtime is available.

- Gates/Arch:
  - [ ] package identity, manifest, and scaffold gates green.
  - [ ] package-compliance gate green for the canonical problem_details config path.
  - [ ] hyphenated runtime package fixtures pass scaffold and compliance gates without duplicate defaults files.
  - [ ] Composer repository synchronization green.
  - [ ] package-index generator check green.
  - [ ] Deptrac generator check green.
  - [ ] ADR and SSoT indexes consistent.
  - [ ] installation-catalog generation and check green.
  - [ ] no forbidden imports or duplicate error/redaction implementations.
  - [ ] source/compiled DI parity green.
  - [ ] no new unauthorized metric or span names.

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions).
- [ ] Implementation complete.
- [ ] Tests green.
- [ ] Gates/Arch green.
- [ ] Docs complete.
- [ ] Canonical ErrorHandlerInterface remains the only exception normalization owner.
- [ ] Throwable mapping is invoked exactly once at the HTTP error boundary.
- [ ] ErrorDescriptor is adapted without changing its contracts-level schema.
- [ ] RFC9457 application/problem+json output is deterministic and RFC7807-compatible.
- [ ] Public Problem Details fields follow the exact safe-by-construction schema.
- [ ] HTTP response status matches body status.
- [ ] Null, unsupported, or invalid HTTP status hints use a deterministic safe fallback.
- [ ] No raw exception message, arbitrary extensions, stack trace, payload, or sensitive context is exposed.
- [ ] No second redactor, serializer, exception mapper, reporter, or renderer registry is introduced.
- [ ] Middleware uses ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE at priority 1000.
- [ ] No package-local mirror tag constant or competing tag owner is introduced.
- [ ] Module selection is explicit and does not implicitly enable HTTP integration.
- [ ] Source and compiled container definitions are equivalent.
- [ ] No ContextStore writes, UnitOfWork lifecycle ownership, metrics, spans, logs, or reset hooks are introduced.
- [ ] Canonical config path config/problem_details.php passes package-scaffold and configuration gates.
- [ ] Hyphenated runtime package scaffold generation is verified without duplicate config files.
- [ ] Canonical Composer metadata, ADR-0040, problem-details SSoT, indexes, and generated artifacts are complete.
- [ ] HTTP pipeline integration is verified against the compatible platform/http owner before final completion.
- [ ] README sections complete and accurate.

---

### 3.50.0 Platform HTTP runtime (pipeline + middleware + UoW + wiring model) (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.50.0"
owner_path: "packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "HTTP runtime збирає deterministic middleware stack без skeleton defaults і стабільно обробляє базові системні маршрути Phase 0."
provides:
- "Inbound HTTP runtime: PSR-7/15 pipeline + deterministic stack builder + terminal handler (Phase 0 pre-routing)"
- "UoW envelope: кожен request обгорнутий KernelRuntimeInterface begin/after + reset discipline"
- "SSoT модель middleware wiring через DI tags + optional manual overrides (no skeleton defaults)"
- "Baseline system middlewares (correlation/trace/metrics/access log + safety hardening toggles)"
- "Cutline impact: blocks Phase 3 platform/runtime cutline"
- "Kernel/platform HTTP compatibility boundary finalized: remove the temporary direct `platform.http` module-id knowledge from RuntimeDriverGuard."
- "Introduce and declare the canonical HTTP runtime provider contract used by RuntimeEntrypointGuard."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-XXXX-platform-http-runtime-pipeline-wiring.md"
ssot_refs:
- "docs/ssot/http-middleware-wiring.md"
- "docs/ssot/http-middleware-catalog.md"
---

- [ ] update `3.50.0 Platform HTTP runtime` to consume `SensitiveDataRedactorInterface` for request/response diagnostic summaries and to forbid raw headers, cookies, body, query payloads, and authorization values.
- [ ] update `3.50.0 Platform HTTP runtime` to contribute a safe `ExceptionMapperInterface` for its BadJson, BodyTooLarge, and TrustedHostRejected exceptions through the shared `error.mapper` tag with empty `[]` metadata, leaving HTTP rendering to `platform/problem-details`.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - `http.*` — config root used by runtime + middleware toggles

- Required config roots registry:
  - `http` MUST already be reserved in `docs/ssot/config-roots.md` with owner `platform/http`.
  - This epic adds/implements package-owned defaults and rules under the existing root; it does not introduce a new root.

- Required tags:
  - `http.middleware.system_pre`
  - `http.middleware.system`
  - `http.middleware.system_post`
  - `http.middleware.app_pre`
  - `http.middleware.app`
  - `http.middleware.app_post`
  - `http.middleware.route_pre`
  - `http.middleware.route`
  - `http.middleware.route_post`

- Required tags registry:
  - All nine `http.middleware.*` tags above MUST already be reserved in `docs/ssot/tags.md` with owner `platform/http`.
  - This epic is the owner implementation epic that introduces the canonical owner constants in
    `packages/core/foundation/src/Tag/ReservedTags.php`.

- Required contracts / ports:
  - `Coretsia\Contracts\Runtime\KernelRuntimeInterface` — UoW hooks
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface` — config source
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface` — correlation id provider
  - `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Log\LoggerInterface` — optional access logging only (noop-safe / nullable wiring allowed)
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Server\RequestHandlerInterface`
  - `Psr\Http\Message\ResponseFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`

- Existing temporary Kernel compatibility check:
  - `RuntimeDriverGuard` currently checks enabled module id `platform.http`.
  - The check MUST remain active until this epic supplies and wires its replacement.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:
- `platform/problem-details`
- `platform/errors`
- `platform/logging`
- `platform/tracing`
- `platform/metrics`
- `platform/health`
- `platform/http-app`
- `platform/routing`
- `platform/observability`
- `platform/profiling`
- any other `platform/*` runtime packages
- any `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Server\RequestHandlerInterface`
  - `Psr\Http\Message\ResponseFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface`
  - `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface`
  - `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextStore`
  - `Coretsia\Contracts\Context\ContextKeys`
  - `Coretsia\Foundation\Tag\TagRegistry`
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - N/A (maintenance commands are owned by epic 3.80.0; not part of this epic)
- HTTP:
  - terminal routes (owner = FallbackRouter 3.110.0):
    - `/`, `/ping`, `/_alive`, `/error`, reserved `/health*`, `/metrics`
  - middleware tags/slots/priorities (default auto wiring):
    - `http.middleware.system_pre`:
      - `\Coretsia\Http\Middleware\CorrelationIdMiddleware::class` priority `950`
      - `\Coretsia\Http\Middleware\TraceContextMiddleware::class` priority `930`
      - `\Coretsia\Http\Middleware\HttpMetricsMiddleware::class` priority `920`
      - `\Coretsia\Http\Middleware\AccessLogMiddleware::class` priority `910`
      - `\Coretsia\Http\Middleware\TrustedProxyMiddleware::class` priority `880` (auto if enabled)
      - `\Coretsia\Http\Middleware\RequestContextMiddleware::class` priority `870` (auto if enabled)
      - `\Coretsia\Http\Middleware\TrustedHostMiddleware::class` priority `860` (auto if enabled)
      - `\Coretsia\Http\Middleware\RequestBodySizeLimitMiddleware::class` priority `830`
      - `\Coretsia\Http\Middleware\JsonBodyParserMiddleware::class` priority `800` (auto if enabled)
    - `http.middleware.system`:
      - reserved (Phase 0 empty; owner = `platform/http`)
    - `http.middleware.system_post`:
      - reserved (Phase 0 empty)
  - note: `\Coretsia\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware::class` may be contributed by `platform/problem-details` at priority `1000` (outermost)
- Kernel hooks/tags:
  - N/A (KernelRuntime called directly by HttpKernel)
- Artifacts:
  - reads: config via `ConfigRepositoryInterface`
  - writes: N/A

### Deliverables (MUST)

#### Creates

- [ ] Before implementation, re-review this epic against the current runtime-driver ownership boundary: `RuntimeDriverResolver` remains Kernel matrix-only; owner packages/adapters own their package/module prerequisites, adapter/transport/executable readiness, and `RuntimeDriverContributions` carry selected canonical drivers only.

- [ ] `packages/platform/http/src/Module/HttpModule.php` — module entry
  - [ ] MUST declare canonical provided capability `runtime.http`
  - [ ] MUST use `RuntimeCapability::HTTP->value` or prove exact equality to that canonical id
  - [ ] MUST NOT rely on package id inference such as `platform.http -> runtime.http`

- [ ] `packages/platform/http/src/Provider/HttpServiceProvider.php` — DI wiring
- [ ] `packages/platform/http/src/Provider/HttpServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `packages/core/foundation/src/Tag/ReservedTags.php` —  define constants for ALL 9 tags above (owner = platform/http)
- [ ] `packages/platform/http/config/http.php` — config subtree
- [ ] `packages/platform/http/config/rules.php` — config rules
- [ ] `packages/platform/http/README.md` — docs (Observability / Errors / Security-Redaction)
- [ ] `packages/platform/http/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

Core runtime:
- [ ] `packages/platform/http/src/HttpKernelInterface.php`
- [ ] `packages/platform/http/src/HttpKernel.php`
- [ ] `packages/platform/http/src/Middleware/Pipeline.php`
- [ ] `packages/platform/http/src/Middleware/MiddlewareResolver.php`
- [ ] `packages/platform/http/src/Middleware/HttpMiddlewareStackBuilder.php`
- [ ] `packages/platform/http/src/Middleware/Dedupe.php`

PSR-7 glue (no superglobals inside):
- [ ] `packages/platform/http/src/Psr7/ServerRequestFactory.php`
- [ ] `packages/platform/http/src/Response/ResponseEmitter.php`

System payload helpers:
- [ ] `packages/platform/http/src/System/SystemEndpointPayload.php`

Core middlewares (Phase 0 baseline):
- [ ] `packages/platform/http/src/Middleware/CorrelationIdMiddleware.php`
- [ ] `packages/platform/http/src/Middleware/TraceContextMiddleware.php`
- [ ] `packages/platform/http/src/Middleware/HttpMetricsMiddleware.php`
- [ ] `packages/platform/http/src/Middleware/AccessLogMiddleware.php`

Request normalization/safety (Phase 0, config-driven):
- [ ] `packages/platform/http/src/Middleware/TrustedProxyMiddleware.php`
- [ ] `packages/platform/http/src/Proxy/TrustedProxyPolicy.php`
- [ ] `packages/platform/http/src/Middleware/RequestContextMiddleware.php`
- [ ] `packages/platform/http/src/Middleware/TrustedHostMiddleware.php`
- [ ] `packages/platform/http/src/Host/TrustedHostPolicy.php`
- [ ] `packages/platform/http/src/Middleware/RequestBodySizeLimitMiddleware.php`
- [ ] `packages/platform/http/src/Middleware/JsonBodyParserMiddleware.php`

Terminal handler:
- [ ] `packages/platform/http/src/Routing/FallbackRouterHandler.php`

Errors (exceptions, mapped by `platform/errors`):
- [ ] `packages/platform/http/src/Exception/BadJsonException.php` — `CORETSIA_HTTP_BAD_JSON`
- [ ] `packages/platform/http/src/Exception/BodyTooLargeException.php` — `CORETSIA_HTTP_PAYLOAD_TOO_LARGE`
- [ ] `packages/platform/http/src/Exception/TrustedHostRejectedException.php` — `CORETSIA_HTTP_TRUSTED_HOST_REJECTED`

Tests:
- [ ] `packages/platform/http/tests/Unit/ForwardedParsingDeterministicTest.php`
- [ ] `packages/platform/http/tests/Unit/TrustedHostPolicyDeterministicTest.php`
- [ ] `packages/platform/http/tests/Contract/MiddlewareOrderDeterministicTest.php`
- [ ] `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php` — locks the canonical `platform/http` middleware slot/priority projection against `docs/ssot/http-middleware-catalog.md`; later `platform/http` middleware epics extend this shared contract
- [ ] `packages/platform/http/tests/Contract/NoopTracingAndMetricsNeverThrowInPipelineContractTest.php`
- [ ] `packages/platform/http/tests/Integration/HttpKernelInvokesKernelRuntimeUowHooksTest.php`
- [ ] `packages/platform/http/tests/Integration/CorrelationHeaderAlwaysPresentTest.php`
- [ ] `packages/platform/http/tests/Integration/BodySizeLimitReturns413Test.php`
- [ ] `packages/platform/http/tests/Integration/BadJsonTriggersCanonicalErrorFlowTest.php`
- [ ] `packages/platform/http/tests/Integration/AccessLogDoesNotLeakHeadersCookiesOrBodyTest.php`
- [ ] `packages/platform/http/tests/Contract/HttpConfigSubtreeShapeContractTest.php`
  - [ ] MUST fail if `config/http.php` repeats root (`['http'=>...]` is forbidden; subtree only)
  - [ ] MUST fail if any key starting with `@` exists under returned subtree (any depth)

Docs:
- [ ] `docs/ssot/http-middleware-wiring.md` — tags/slots/dedupe/deterministic order/override recipe
- [ ] `docs/ssot/http-middleware-catalog.md` — canonical middleware catalog + priority bands

#### Modifies

- [ ] `tools/gates/no_skeleton_http_default_gate.php` — must remain green (no skeleton default http config)
- [ ] deptrac expectations updated (if needed)
- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/http-middleware-wiring.md`
  - [ ] `docs/ssot/http-middleware-catalog.md`

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-platform-http-runtime-pipeline-wiring.md`

- [ ] `packages/core/kernel/src/Runtime/Driver/RuntimeDriverGuard.php`
  - [ ] replace the temporary direct `platform.http` module-id check with the finalized HTTP runtime provider contract;
  - [ ] remove `TODO(platform-http-3.50.0)` only after replacement tests are green.

- [ ] `docs/adr/ADR-0027-runtime-driver-resolution.md`
- [ ] `docs/ssot/runtime-drivers.md`
- [ ] `docs/architecture/runtime-driver-resolution.md`

#### Package skeleton (if type=package)

- [ ] `packages/platform/http/composer.json`
- [ ] `packages/platform/http/src/Module/HttpModule.php`
- [ ] `packages/platform/http/src/Provider/HttpServiceProvider.php`
- [ ] `packages/platform/http/config/http.php`
- [ ] `packages/platform/http/config/rules.php`
- [ ] `packages/platform/http/README.md`
- [ ] `packages/platform/http/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

Normative:
- [ ] These `http.middleware.*` config arrays are **manual append lists** (class-strings).
- [ ] TagRegistry enumeration order remains the only ordering source for tagged services.

- [ ] Canonical slot execution model (MUST):
  - [ ] request-phase semantic order:
    - [ ] `http.middleware.system_pre`
    - [ ] `http.middleware.system`
    - [ ] `http.middleware.app_pre`
    - [ ] `http.middleware.app`
    - [ ] `http.middleware.route_pre`
    - [ ] `http.middleware.route`
  - [ ] response-phase semantic order (on unwind):
    - [ ] `http.middleware.route_post`
    - [ ] `http.middleware.app_post`
    - [ ] `http.middleware.system_post`
  - [ ] `HttpMiddlewareStackBuilder` MUST preserve this semantic model exactly.
  - [ ] `*_post` slots are response-decorator slots:
    - [ ] once the HTTP pipeline has been entered, applicable `*_post` middlewares MUST still run for short-circuit responses returned by earlier layers/middlewares
    - [ ] this prevents package-local divergence for maintenance / redirect / error responses

- [ ] Files:
  - [ ] `packages/platform/http/config/http.php`
- [ ] Keys (dot):

  # auto include tagged middlewares per-slot
  - [ ] `http.middleware.auto.enabled` = true
  - [ ] `http.middleware.auto.system_pre` = true
  - [ ] `http.middleware.auto.system` = true
  - [ ] `http.middleware.auto.system_post` = true
  - [ ] `http.middleware.auto.app_pre` = true
  - [ ] `http.middleware.auto.app` = true
  - [ ] `http.middleware.auto.app_post` = true
  - [ ] `http.middleware.auto.route_pre` = true
  - [ ] `http.middleware.auto.route` = true
  - [ ] `http.middleware.auto.route_post` = true

  # manual append lists (class-strings), appended after tagged portion
  - [ ] `http.middleware.system_pre` = []
  - [ ] `http.middleware.system` = []
  - [ ] `http.middleware.system_post` = []
  - [ ] `http.middleware.app_pre` = []
  - [ ] `http.middleware.app` = []
  - [ ] `http.middleware.app_post` = []
  - [ ] `http.middleware.route_pre` = []
  - [ ] `http.middleware.route` = []
  - [ ] `http.middleware.route_post` = []

  - [ ] `http.correlation.enabled` = true
  - [ ] `http.correlation.header_name` = 'X-Correlation-Id'

  - [ ] `http.tracing.enabled` = true
  - [ ] `http.metrics.enabled` = true
  - [ ] `http.access_log.enabled` = true

  - [ ] `http.proxy.enabled` = false
  - [ ] `http.proxy.trusted_proxies` = []
  - [ ] `http.context.enrich.enabled` = true
  - [ ] `http.hosts.enabled` = false
  - [ ] `http.hosts.allowed` = []

  - [ ] `http.request.max_body_bytes` = 1048576
  - [ ] `http.request.json.enabled` = true
  - [ ] `http.request.json.max_depth` = 32

- [ ] Feature toggles referenced by platform/http-owned middleware (must exist; defaults owned by `platform/http`):
  - [ ] `http.debug.endpoints.enabled`
  - [ ] alignment rule:
    - [ ] this list MUST cover every `platform/http`-owned middleware in `docs/ssot/http-middleware-catalog.md` whose placement is conditional on an `if enabled` rule
  - [ ] Clarification:
    - [ ] these keys are seeded here as `platform/http`-owned configuration surface only
    - [ ] `3.50.0` MUST NOT imply that every listed toggle already has a full middleware/runtime implementation in this epic
    - [ ] concrete behavior for individual HTTP features is introduced by later HTTP epics

- [ ] Rules:
  - [ ] `packages/platform/http/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `packages/core/foundation/src/Tag/ReservedTags.php`
  - [ ] constants:
    - [ ] `MIDDLEWARE_SYSTEM_PRE = 'http.middleware.system_pre'`
    - [ ] `MIDDLEWARE_SYSTEM = 'http.middleware.system'`
    - [ ] `MIDDLEWARE_SYSTEM_POST = 'http.middleware.system_post'`
    - [ ] `MIDDLEWARE_APP_PRE = 'http.middleware.app_pre'`
    - [ ] `MIDDLEWARE_APP = 'http.middleware.app'`
    - [ ] `MIDDLEWARE_APP_POST = 'http.middleware.app_post'`
    - [ ] `MIDDLEWARE_ROUTE_PRE = 'http.middleware.route_pre'`
    - [ ] `MIDDLEWARE_ROUTE = 'http.middleware.route'`
    - [ ] `MIDDLEWARE_ROUTE_POST = 'http.middleware.route_post'`
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Http\HttpKernel`
  - [ ] registers: PSR-17 factories (single implementation, e.g. `Nyholm\Psr7\Factory\Psr17Factory`)
  - [ ] registers services for each middleware class above
  - [ ] adds tag `http.middleware.system_pre`:
    - [ ] `CorrelationIdMiddleware` priority `950`
    - [ ] `TraceContextMiddleware` priority `930`
    - [ ] `HttpMetricsMiddleware` priority `920`
    - [ ] `AccessLogMiddleware` priority `910`
    - [ ] `TrustedProxyMiddleware` priority `880` (if enabled)
    - [ ] `RequestContextMiddleware` priority `870` (if enabled)
    - [ ] `TrustedHostMiddleware` priority `860` (if enabled)
    - [ ] `RequestBodySizeLimitMiddleware` priority `830`
    - [ ] `JsonBodyParserMiddleware` priority `800` (if enabled)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
  - [ ] `ContextKeys::CLIENT_IP` (if enriched)
  - [ ] `ContextKeys::SCHEME`
  - [ ] `ContextKeys::HOST`
  - [ ] `ContextKeys::PATH`
  - [ ] `ContextKeys::USER_AGENT`
- [ ] Context writes (safe only):
  - [ ] `ContextKeys::CORRELATION_ID` (CorrelationIdMiddleware)
  - [ ] `ContextKeys::CLIENT_IP|SCHEME|HOST|PATH|USER_AGENT` (RequestContextMiddleware)
- [ ] Reset discipline:
  - [ ] no stateful services expected; if introduced → `ResetInterface` + tag `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `http.request` (attrs: `method`, `status`, `outcome`)
  - [ ] `http.middleware` (attrs: `operation=<middleware>`, `outcome`) (optional debug)
- [ ] Metrics:
  - [ ] `http.request_total` (labels: `method`, `status`, `outcome`)
  - [ ] `http.request_duration_ms` (labels: `method`, `status`, `outcome`)
  - [ ] `http.middleware_total` (labels: `operation`, `outcome`)
  - [ ] `http.middleware_duration_ms` (labels: `operation`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `uow_type→operation` (if you ever emit UoW-type metrics here)
  - [ ] `reason→status` (e.g. 413/400 as status)
- [ ] Logs:
  - [ ] Access log: method/status/duration + safe path template (or `unknown`) + correlation_id
  - [ ] No headers/cookies/body; no tokens/session ids; only `hash/len` when needed

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Http\Exception\BadJsonException` — errorCode `CORETSIA_HTTP_BAD_JSON`
  - [ ] `Coretsia\Http\Exception\BodyTooLargeException` — errorCode `CORETSIA_HTTP_PAYLOAD_TOO_LARGE`
  - [ ] `Coretsia\Http\Exception\TrustedHostRejectedException` — errorCode `CORETSIA_HTTP_TRUSTED_HOST_REJECTED`
- [ ] Mapping:
  - [ ] package throws deterministic exceptions; mapping to ErrorDescriptor via `platform/errors` mappers (NO DUPES)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/raw SQL/payload
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`correlation_id` in logs only)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → covered by:
  - [ ] `packages/platform/http/tests/Integration/CorrelationHeaderAlwaysPresentTest.php`
  - [ ] (context enrichment) add/verify via middleware integration tests where applicable
- [ ] If `kernel.reset` used → `N/A` (not expected)
- [ ] If metrics/spans/logs exist → covered by:
  - [ ] `packages/platform/http/tests/Contract/NoopTracingAndMetricsNeverThrowInPipelineContractTest.php`
  - [ ] access log behavior proven indirectly via pipeline tests (Phase 0)
- [ ] If redaction exists → covered by:
  - [ ] `packages/platform/http/tests/Integration/AccessLogDoesNotLeakHeadersCookiesOrBodyTest.php`

#### Test harness / fixtures (when integration is needed)

N/A (integration tests use minimal kernel/http harness)

### Tests (MUST)

- Unit:
  - [ ] `packages/platform/http/tests/Unit/ForwardedParsingDeterministicTest.php`
  - [ ] `packages/platform/http/tests/Unit/TrustedHostPolicyDeterministicTest.php`
- Contract:
  - [ ] `packages/platform/http/tests/Contract/MiddlewareOrderDeterministicTest.php`
  - [ ] `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php`
  - [ ] `packages/platform/http/tests/Contract/NoopTracingAndMetricsNeverThrowInPipelineContractTest.php`
  - [ ] `packages/platform/http/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `packages/platform/http/tests/Contract/HttpConfigSubtreeShapeContractTest.php`
- Integration:
  - [ ] `packages/platform/http/tests/Integration/HttpKernelInvokesKernelRuntimeUowHooksTest.php`
  - [ ] `packages/platform/http/tests/Integration/CorrelationHeaderAlwaysPresentTest.php`
  - [ ] `packages/platform/http/tests/Integration/BodySizeLimitReturns413Test.php`
  - [ ] `packages/platform/http/tests/Integration/BadJsonTriggersCanonicalErrorFlowTest.php`
  - [ ] `packages/platform/http/tests/Integration/AccessLogDoesNotLeakHeadersCookiesOrBodyTest.php`
- Gates/Arch:
  - [ ] `tools/gates/no_skeleton_http_default_gate.php` green
  - [ ] deptrac updated (if needed)

- [ ] non-classic HTTP driver is rejected when no HTTP runtime provider is present;
- [ ] non-classic HTTP driver is accepted when `platform/http` supplies the canonical provider contract;
- [ ] `http.classic` remains valid without `platform/http`;
- [ ] `bg.worker_queue` remains valid without `platform/http`;
- [ ] Kernel no longer contains temporary owner-module knowledge after the replacement is complete.

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] Dependency rules satisfied:
  - [ ] `platform/http` has no runtime dependencies on other `platform/*` runtime packages
  - [ ] allowed dependencies are limited to `core/*` and PSR interfaces per the cemented boundary
- [ ] Stack assembly deterministic (tags + config + dedupe)
- [ ] Cross-slot execution model locked:
  - [ ] request-phase and response-phase slot semantics match the canonical model above
  - [ ] short-circuit responses still pass through applicable `*_post` decorators
- [ ] UoW begin/after always called + reset discipline preserved
- [ ] No secret leakage in logs/metrics/spans
- [ ] README sections complete and accurate
- [ ] Stack builder ordering & dedupe (MUST) — prevent double ordering logic
  - [ ] `HttpMiddlewareStackBuilder` MUST combine sources deterministically:
    - [ ] (A) tagged middlewares per slot (via `TagRegistry::all(tag)`), included only if `http.middleware.auto.<slot>=true`
    - [ ] (B) manual config list `http.middleware.<slot>` appended after (A)
  - [ ] Dedupe policy MUST be single-choice and stable:
    - [ ] If dedupe is needed, it MUST preserve first occurrence order (tagged first, then manual).
    - [ ] MUST NOT re-sort anything (TagRegistry already sorted tagged portion).
- [ ] temporary `platform.http` module-id coupling is either removed through the finalized provider contract or explicitly retained by a new ADR decision;
- [ ] no runtime service/container probing is used to establish HTTP runtime availability;
- [ ] RuntimeEntrypointGuard remains the single compatibility boundary.

---

### 3.55.0 HTTP long-running safety harness (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.55.0"
owner_path: "packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "Один і той самий process може обробити 100 sequential requests без leak, а reset виконується детерміновано після кожного request."
provides:
- "`platform/http` long-running safe: HttpKernel/handler викликається багато разів у одному процесі без протікання стану/контексту."
- "Harness/integration tests на sequential requests + reset discipline through the standard `HttpKernel` → `KernelRuntimeInterface` → Foundation `ResetOrchestrator` flow."
- "Optional span attribute `long_running=true` (НЕ metric label)."
- "Consumers of this harness (policy): `integrations/runtime-frankenphp`, `integrations/runtime-swoole`, `integrations/runtime-roadrunner`, and `platform/worker` when `worker.task_type=http`."
- "No extra runtime toggles required: protection/harness is activated by using a long-running HTTP runtime adapter; no middleware/endpoint changes."

runtime_invariant:
- "Long-running safety is a consequence of the standard KernelRuntime + Foundation reset flow."
- "It MUST NOT be controlled by a dedicated `enabled=false` style flag in Foundation."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0018-kernel-long-running-safety-harness.md"
ssot_refs:
- "docs/ssot/reset-tags.md"
- "docs/ssot/context-lifecycle.md"
- "docs/ssot/context-store.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 3.50.0 — `platform/http` package skeleton exists.
  - 3.50.0 — `packages/platform/http/src/HttpKernel.php` exists.
  - 3.50.0 — `packages/platform/http/src/HttpKernelInterface.php` exists.
  - 3.50.0 — HTTP middleware stack, terminal handler, config root, provider wiring, and UoW envelope exist.
  - 3.50.0 — `packages/platform/http/config/http.php` and `packages/platform/http/config/rules.php` exist.
  - 3.50.0 — canonical HTTP middleware tag owner constants exist in `packages/core/foundation/src/Tag/ReservedTags.php`.
  - 1.280.0 — `core/kernel` provides `KernelRuntime`, which wraps each request as a UoW and executes the canonical after/reset flow.
  - 1.250.0 — Foundation reset orchestration exists and owns reset ordering/meta behavior.

- Required pre-existing deliverables (exact paths):
  - `packages/platform/http/composer.json`
  - `packages/platform/http/src/HttpKernel.php`
  - `packages/platform/http/src/HttpKernelInterface.php`
  - `packages/platform/http/src/Provider/HttpServiceProvider.php`
  - `packages/platform/http/src/Provider/HttpServiceFactory.php`
  - `packages/core/foundation/src/Tag/ReservedTags.php`
  - `packages/platform/http/config/http.php`
  - `packages/platform/http/config/rules.php`
  - `packages/platform/http/README.md`
  - `docs/ssot/http-middleware-wiring.md`
  - `docs/ssot/http-middleware-catalog.md`

- Required tags:
  - reset discipline is provided by Foundation/Kernel runtime; `platform/http` relies on it but MUST NOT enumerate reset tags directly

- Required contracts / ports:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Server\RequestHandlerInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional; noop-safe)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional; noop-safe)

- Non-goals (legacy):
  - No process manager / worker pool (owned by other epics or integrations).
  - No middleware stack / endpoint changes.

- Acceptance scenario:
  - When running `LongRunning100SequentialRequestsNoLeakTest`, then one `HttpKernel` instance handles 100 sequential requests in the same process without request-local ContextStore leakage.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- `integrations/*`
- (cemented) `platform/http` MUST NOT depend on other `platform/*` runtime packages
  - `core/*` dependencies are allowed (e.g. `core/kernel`, `core/foundation`, `core/contracts`)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Server\RequestHandlerInterface`
  - `Psr\Log\LoggerInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - N/A (uses existing stack; adds harness/probe)
- Kernel hooks/tags:
  - relies on Foundation reset orchestration via `ResetOrchestrator`
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] Before implementation, re-review this epic against the current runtime-driver ownership boundary: `RuntimeDriverResolver` remains Kernel matrix-only; owner packages/adapters own their package/module prerequisites, adapter/transport/executable readiness, and `RuntimeDriverContributions` carry selected canonical drivers only.

Test-only harness/support:
- [ ] `packages/platform/http/tests/Support/LongRunningSafetyProbe.php` — helper used only by tests
  - [ ] MUST NOT change production behavior (no hooks, no middleware injection)
  - [ ] MUST NOT emit stdout/stderr output
  - [ ] MUST NOT log request payloads/headers/tokens
  - [ ] MAY inspect ContextStore via `ContextAccessorInterface` for invariants only

Docs:
- [ ] `docs/adr/ADR-0018-kernel-long-running-safety-harness.md`
- [ ] `docs/architecture/http-long-running.md` — rules: no statics, reset discipline, adapter notes

Tests:
- [ ] `packages/platform/http/tests/Integration/LongRunning100SequentialRequestsNoLeakTest.php`
- [ ] `packages/platform/http/tests/Integration/ResetIsCalledBetweenRequestsTest.php`

#### Modifies

- [ ] `packages/platform/http/README.md` — add long-running runtime safety section.
- [ ] `packages/platform/http/config/http.php` — add only `http.long_running.span_attr_enabled` if optional span attribute emission is configurable.
- [ ] `packages/platform/http/config/rules.php` — add shape rule only for `http.long_running.span_attr_enabled` if the config key is introduced.
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0018-kernel-long-running-safety-harness.md`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/http/config/http.php`
  - [ ] `packages/platform/http/config/rules.php`
- [ ] Keys (dot):
  - [ ] `http.long_running.span_attr_enabled` = true
    - [ ] controls only optional span attribute emission;
    - [ ] MUST NOT enable/disable long-running safety;
    - [ ] MUST NOT enable/disable reset discipline;
    - [ ] MUST NOT enable/disable harness behavior;
    - [ ] long-running safety remains unconditional when a long-running HTTP runtime adapter is used.
- [ ] Rules:
  - [ ] `packages/platform/http/config/rules.php` enforces `http.long_running.span_attr_enabled` as bool.

#### Wiring / DI tags (when applicable)

N/A for new tags.

- [ ] This epic MUST NOT introduce or modify `http.middleware.*` tag constants.
- [ ] HTTP middleware tag constants are already owned and introduced by `3.50.0` in:
  - [ ] `packages/core/foundation/src/Tag/ReservedTags.php`
- [ ] This epic MUST NOT add new middleware slots.
- [ ] This epic MUST NOT alter middleware stack ordering.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] via `Coretsia\Contracts\Context\ContextAccessorInterface` (for leak detection / invariants)
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] relies on the standard `HttpKernel` → `KernelRuntimeInterface` → `ResetOrchestrator::resetAll()` flow from 3.50.0 / 1.280.0.
  - [ ] `platform/http` MUST NOT enumerate `kernel.reset`.
  - [ ] `platform/http` MUST NOT read `foundation.reset.tag`.
  - [ ] `platform/http` MUST NOT call `ResetInterface::reset()` directly.
  - [ ] Long-running tests MAY observe reset effects/counts through test fixtures, but production code remains reset-tag-opaque.

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] optional attribute `long_running=true` on the existing request/runtime span only.
  - [ ] `long_running` MUST NOT be used as a metric label.
  - [ ] `long_running` MUST NOT contain request-specific data.
- Metrics/Logs:
  - N/A

#### Errors

N/A

#### Security / Redaction

- [ ] Long-running harness MUST enforce “no leak” without capturing secrets:
  - [ ] MUST NOT dump ContextStore values (only key presence/absence or safe ids)
  - [ ] MUST NOT include absolute paths in assertion messages
  - [ ] MUST NOT include raw headers/cookies/Authorization in test failure output
  - [ ] Allowed diagnostics: key names, stable counters, `hash(value)` / `len(value)` when necessary

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] if effective reset discovery is used → `packages/platform/http/tests/Integration/ResetIsCalledBetweenRequestsTest.php`
- [ ] If long-running no-leak promised → `packages/platform/http/tests/Integration/LongRunning100SequentialRequestsNoLeakTest.php`

### Tests (MUST)

- Contract:
  - N/A
- Integration:
  - [ ] `packages/platform/http/tests/Integration/LongRunning100SequentialRequestsNoLeakTest.php`
  - [ ] `packages/platform/http/tests/Integration/ResetIsCalledBetweenRequestsTest.php`
- [ ] Add policy/contract assertions inside these integration tests:
  - [ ] `LongRunning100SequentialRequestsNoLeakTest` MUST assert:
    - [ ] one `HttpKernel` instance can handle 100 sequential requests in the same process;
    - [ ] each request succeeds or deterministically returns the expected response shape;
    - [ ] request-local ContextStore values from a previous request do not leak into the next request;
    - [ ] no raw headers, cookies, Authorization values, tokens, request bodies, or absolute paths appear in failure diagnostics.
  - [ ] `ResetIsCalledBetweenRequestsTest` MUST assert:
    - [ ] reset orchestration is observed exactly once per request UoW, where observable;
    - [ ] reset happens after request after-phase completion and before the next request begins;
    - [ ] reset still happens when a request path throws before being mapped/handled by later platform error middleware, subject to the 3.50.0 runtime flow.
- Gates/Arch:
  - N/A

### DoD (MUST)

- [ ] Spec locked.
- [ ] Tests green.
- [ ] Gates/Arch green.
- [ ] No new runtime dependencies introduced.
- [ ] No package skeleton files created by this epic.
- [ ] No HTTP middleware tags introduced or modified by this epic.
- [ ] No HTTP config root introduced by this epic.
- [ ] Existing `platform/http` runtime from 3.50.0 remains the production target.
- [ ] One `HttpKernel` instance can process 100 sequential requests in one PHP process.
- [ ] Reset is observed once per request UoW.
- [ ] No request-local ContextStore state leaks between sequential requests.
- [ ] Harness/support remains tests-only.
- [ ] Harness/support does not emit stdout/stderr.
- [ ] Harness/support does not log or dump headers, cookies, Authorization values, tokens, request bodies, raw payloads, object dumps, stack traces, or absolute paths.
- [ ] Docs complete:
  - [ ] `docs/architecture/http-long-running.md`
  - [ ] `docs/adr/ADR-0018-kernel-long-running-safety-harness.md`
  - [ ] `packages/platform/http/README.md` long-running safety section.

---

### 3.60.0 RequestId vs CorrelationId policy (SHOULD) [IMPL]

---
type: package
phase: 3
epic_id: "3.60.0"
owner_path: "packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "Якщо ввімкнено, request_id завжди валідний, записаний у ContextStore і повертається як response header."
provides:
- "Policy розмежування `correlation_id` (end-to-end) та `request_id` (ingress id)"
- "Middleware приймає upstream `X-Request-Id` (опційно) або генерує safe id"
- "request_id ніколи не стає secret і не використовується як metric label"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-XXXX-requestid-correlationid-policy.md"
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 3.50.0 — base HTTP runtime exists (middleware tags/stack builder + config/http.php)
  - PHASE 1 Foundation `ContextKeys::REQUEST_ID` MUST exist (see PHASE 1 patch above)

- Required deliverables (exact paths):
  - `packages/platform/http/config/http.php` — base config file must exist
  - `packages/platform/http/config/rules.php` — base rules must exist
  - `packages/platform/http/src/Provider/HttpServiceProvider.php` — wiring base exists
  - `packages/core/foundation/src/Tag/ReservedTags.php` — provides `http.middleware.system_pre`

- Required config roots/keys:
  - `http.request_id.*` — introduced by this epic

- Existing root reuse note (MUST):
  - This epic extends the existing `http` root with `http.request_id.*` keys only.
  - It MUST NOT be treated as a config-root owner/introduction epic.

- Required tags:
  - `http.middleware.system_pre` — used for wiring priority `940`

- Required contracts / ports:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Coretsia\Foundation\Context\ContextStore`
  - `Coretsia\Contracts\Context\ContextKeys` (reserved key `REQUEST_ID`)
  - `Coretsia\Foundation\Id\UlidGenerator` (or canonical id generator)

- Generator single-choice (MUST)
  - RequestId generation MUST use canonical ID generator (single-choice):
    - `Coretsia\Foundation\Id\UlidGenerator`
  - Validation MUST be deterministic and MUST NOT throw on invalid upstream id:
    - invalid → generate replacement (outcome token stable)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:
- any other `platform/*` runtime packages (same boundary as 3.50.0)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
- Contracts:
  - `Coretsia\Contracts\Runtime\ResetInterface` (not expected)
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextStore`
  - `Coretsia\Contracts\Context\ContextKeys`
  - `Coretsia\Foundation\Id\UlidGenerator`

### Entry points / integration points (MUST)

- HTTP:
  - middleware: `\Coretsia\Http\Middleware\RequestIdMiddleware::class`
  - middleware slots/tags: `http.middleware.system_pre` priority `940` meta `{toggle:'http.request_id.enabled'}`
- CLI:
  - N/A
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `packages/platform/http/src/Middleware/RequestIdMiddleware.php` — read/validate/generate request id
- [ ] `packages/platform/http/src/RequestId/RequestIdPolicy.php` — VO policy
- [ ] `packages/platform/http/src/RequestId/RequestIdValidator.php` — deterministic charset/len validation

- [ ] `packages/platform/http/tests/Integration/UpstreamRequestIdAcceptedWhenValidTest.php` — asserts accepted upstream id is written to `ContextKeys::REQUEST_ID` and returned in the configured response header
- [ ] `packages/platform/http/tests/Integration/InvalidRequestIdIsReplacedByGeneratedTest.php` — asserts invalid upstream id is replaced deterministically, written to `ContextKeys::REQUEST_ID`, and returned in the configured response header

#### Modifies

- [ ] `packages/platform/http/config/http.php` — add `http.request_id.*` keys + defaults
- [ ] `packages/platform/http/config/rules.php` — enforce shape for new keys
- [ ] `packages/platform/http/src/Provider/HttpServiceProvider.php` — register middleware + add tag `system_pre` priority `940`
- [ ] `packages/platform/http/README.md` — section “RequestIdMiddleware” (slot/priority, header policy, upstream accept/replace rules)
- [ ] `docs/ssot/http-middleware-catalog.md` — add/update row for `RequestIdMiddleware` (system_pre 940)
- [ ] `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php` — extend shared catalog lock for `RequestIdMiddleware` (`http.middleware.system_pre` priority `940`)
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-requestid-correlationid-policy.md`

#### Package skeleton (if type=package)

N/A (same package as 3.50.0)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.request_id.enabled` = false
  - [ ] `http.request_id.header_name` = 'X-Request-Id'
  - [ ] `http.request_id.accept_from_upstream` = true
  - [ ] `http.request_id.max_length` = 128
- [ ] Rules:
  - [ ] `packages/platform/http/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (reuses `http.middleware.system_pre`)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Http\Middleware\RequestIdMiddleware`
  - [ ] adds tag: `http.middleware.system_pre` priority `940` meta `{toggle:'http.request_id.enabled'}`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads:
  - N/A
- [ ] Context writes (safe only):
  - [ ] `ContextKeys::REQUEST_ID` (safe id, not secret)
- Reset discipline:
  - N/A

#### Observability (policy-compliant)

- [ ] Metrics:
  - [ ] `http.request_id_total` (labels: `outcome=accepted|generated`)
- [ ] Logs:
  - [ ] optional debug log: outcome + `len(value)` or `hash(value)` only
- [ ] request_id MUST NOT become a metric label (already enforced by label allowlist; keep as explicit rule here).

#### Errors

- Exceptions introduced:
  - N/A (recommended: generate instead of reject)
- Single-choice outcome policy (MUST):
  - In this epic, invalid upstream request ids are replaced, not rejected.
  - Therefore `rejected` is not a valid runtime outcome token for this epic.
  - If a future epic introduces explicit reject behavior, it MUST add its own metric/error-flow update explicitly.
- [ ] Mapping:
  - [ ] deterministic error code (if reject is ever introduced): `CORETSIA_HTTP_REQUEST_ID_INVALID`

#### Security / Redaction

- [ ] Allowed:
  - [ ] request id in response header and logs (safe id)
- [ ] Forbidden:
  - [ ] never log other headers/cookies
  - [ ] request_id MUST NOT be used as metric label

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → covered by integration tests that assert both:
  - [ ] `ContextKeys::REQUEST_ID` is present in `ContextStore`
  - [ ] the configured request-id response header is emitted
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist → metric emission asserted in integration tests (where applicable)
- [ ] If redaction exists → logs use only `hash/len` (assert in tests if log capture harness exists)

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit/Contract:
  - N/A
- Integration:
  - [ ] `packages/platform/http/tests/Integration/UpstreamRequestIdAcceptedWhenValidTest.php` — asserts ContextStore write + response header echo for valid upstream id
  - [ ] `packages/platform/http/tests/Integration/InvalidRequestIdIsReplacedByGeneratedTest.php` — asserts deterministic replacement + ContextStore write + response header emission
- Gates/Arch:
  - [ ] deptrac green (same boundary as 3.50.0)

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] Deterministic validation policy
- [ ] Request id is written to `ContextStore` and returned in the configured response header when middleware is enabled
- [ ] Works under noop tracer/meter/logger
- [ ] Docs updated (README + middleware catalog entry)

---

### 3.70.0 Response hardening (security headers + HTTPS redirect) (SHOULD) [IMPL]

---
type: package
phase: 3
epic_id: "3.70.0"
owner_path: "packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "Коли ввімкнено, кожна відповідь має deterministic security headers, а HTTP→HTTPS redirect працює за policy."
provides:
- "Security headers middleware для всіх responses (включно з errors/maintenance)"
- "Optional HTTPS redirect policy (prod hardening), враховуючи scheme (TrustedProxy/Context)"
- "Deterministic header order + redaction: no raw URL/query in logs"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-XXXX-response-hardening-headers-https.md"
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 3.50.0 — base HTTP runtime exists (system_pre/system_post slots)
  - 3.50.0 — `http.middleware.system_post` tag exists
  - (optional) 3.50.0 — TrustedProxyMiddleware exists if redirect relies on proxy scheme

- Required deliverables (exact paths):
  - `packages/platform/http/config/http.php`
  - `packages/platform/http/config/rules.php`
  - `packages/platform/http/src/Provider/HttpServiceProvider.php`

- Required config roots/keys:
  - `http.security_headers.*`
  - `http.https_redirect.*`

- Existing root reuse note (MUST):
  - This epic extends the existing `http` root with `http.security_headers.*` and `http.https_redirect.*` keys only.
  - It MUST NOT be treated as a config-root owner/introduction epic.

- Required tags:
  - `http.middleware.system_pre` — for HttpsRedirectMiddleware (priority `850`)
  - `http.middleware.system_post` — for SecurityHeadersMiddleware (priority `600`)

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
- Foundation stable APIs:
  - `Coretsia\Contracts\Context\ContextKeys` — required because redirect scheme resolution MAY read `ContextKeys::SCHEME`

- tighten to cemented APIs
  - `Coretsia\Contracts\Context\ContextAccessorInterface::get(string $key): mixed` MUST be used without a default param.
  - Missing key handling MUST remain non-throwing for this epic’s runtime path.

- Scheme source (single-choice) (MUST)
  - Redirect decision MUST use scheme in this priority order (deterministic):
    1) trusted proxy derived scheme (if proxy middleware enabled and policy accepts)
    2) context key `ContextKeys::SCHEME` (if present)
    3) request URI scheme (PSR-7)
  - Logging MUST NOT dump raw URL/query; allowed only `hash/len`.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:
- any other `platform/*` runtime packages

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
- Foundation stable APIs:
  - `Coretsia\Contracts\Context\ContextKeys`

### Entry points / integration points (MUST)

- HTTP:
  - middleware: `\Coretsia\Http\Middleware\HttpsRedirectMiddleware::class`
    - tag: `http.middleware.system_pre` priority `850` meta `{toggle:'http.https_redirect.enabled'}`
  - middleware: `\Coretsia\Http\Middleware\SecurityHeadersMiddleware::class`
    - tag: `http.middleware.system_post` priority `600` meta `{toggle:'http.security_headers.enabled'}`
- CLI:
  - N/A
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `packages/platform/http/src/Middleware/SecurityHeadersMiddleware.php` — adds headers deterministically
- [ ] `packages/platform/http/src/SecurityHeaders/SecurityHeadersPolicy.php` — VO policy (CSP/Referrer/etc)
- [ ] `packages/platform/http/src/Middleware/HttpsRedirectMiddleware.php` — redirect policy-based
- [ ] `packages/platform/http/src/HttpsRedirect/HttpsRedirectPolicy.php` — VO policy (status/allowlist)

- [ ] `packages/platform/http/tests/Integration/SecurityHeadersAppliedToAllResponsesTest.php`
- [ ] `packages/platform/http/tests/Integration/HttpsRedirectRespectsProxySchemeTest.php`
- [ ] `packages/platform/http/tests/Contract/SecurityHeadersDeterministicOrderContractTest.php`

#### Modifies

- [ ] `packages/platform/http/config/http.php` — add `security_headers.*` + `https_redirect.*` keys + defaults
- [ ] `packages/platform/http/config/rules.php` — enforce shape for new keys
- [ ] `packages/platform/http/src/Provider/HttpServiceProvider.php` — register middlewares + add tags/priority
- [ ] `packages/platform/http/README.md` — sections “HttpsRedirectMiddleware” and “SecurityHeadersMiddleware” (slot/priority, toggle keys, override recipe)
- [ ] `docs/ssot/http-middleware-catalog.md` — add/update rows:
  - [ ] `HttpsRedirectMiddleware` → `http.middleware.system_pre` priority `850`
  - [ ] `SecurityHeadersMiddleware` → `http.middleware.system_post` priority `600`
- [ ] `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php` — extend shared catalog lock for:
  - [ ] `HttpsRedirectMiddleware` → `http.middleware.system_pre` priority `850`
  - [ ] `SecurityHeadersMiddleware` → `http.middleware.system_post` priority `600`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-response-hardening-headers-https.md`

#### Package skeleton (if type=package)

N/A (same package as 3.50.0)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.security_headers.enabled` = false
  - [ ] `http.security_headers.x_content_type_options` = 'nosniff'
  - [ ] `http.security_headers.x_frame_options` = 'deny'
  - [ ] `http.security_headers.referrer_policy` = 'no-referrer'
  - [ ] `http.security_headers.permissions_policy` = ''
  - [ ] `http.security_headers.csp` = ''
  - [ ] `http.https_redirect.enabled` = false
  - [ ] `http.https_redirect.status` = 308
  - [ ] `http.https_redirect.allow_methods` = ['GET','HEAD']
- [ ] Rules:
  - [ ] `packages/platform/http/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (reuses `http.middleware.system_pre` / `http.middleware.system_post`)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Http\Middleware\HttpsRedirectMiddleware`
  - [ ] adds tag: `http.middleware.system_pre` priority `850` meta `{toggle:'http.https_redirect.enabled'}`
  - [ ] registers: `Coretsia\Http\Middleware\SecurityHeadersMiddleware`
  - [ ] adds tag: `http.middleware.system_post` priority `600` meta `{toggle:'http.security_headers.enabled'}`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] scheme from trusted proxy/context (if configured)
- Context writes (safe only):
  - N/A
- Reset discipline:
  - N/A

#### Observability (policy-compliant)

- [ ] Metrics:
  - [ ] `http.https_redirect_total` (labels: `outcome=redirected|skipped`)
- [ ] Logs:
  - [ ] redirect decision logged as info (no URL dump; only `hash(url)` + status)

#### Errors

- Exceptions introduced:
  - N/A (prefer skip over throw)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw URL/query in logs (use `hash/len` only)
- [ ] Deterministic header order:
  - [ ] stable tests prove header order

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `N/A`
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist → redirect metric/log asserted in integration tests
- [ ] If redaction exists → logs must not include raw URL/query (assert via log capture where applicable)

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `packages/platform/http/tests/Contract/SecurityHeadersDeterministicOrderContractTest.php`
- Integration:
  - [ ] `packages/platform/http/tests/Integration/SecurityHeadersAppliedToAllResponsesTest.php`
  - [ ] `packages/platform/http/tests/Integration/HttpsRedirectRespectsProxySchemeTest.php`
- Gates/Arch:
  - [ ] deptrac green (same boundary as 3.50.0)

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] Works with maintenance and error responses too
- [ ] Deterministic header order
- [ ] Docs updated in middleware catalog

---

### 3.80.0 Maintenance mode (real toggle, MUST) (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.80.0"
owner_path: "packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "Maintenance toggle працює без `config:compile` і без впливу на fingerprint/artifacts, повертаючи deterministic 503."
provides:
- "Real maintenance toggle через `packages/applications/skeleton/var/maintenance/*` (поза artifacts/fingerprint)"
- "Deterministic 503 response + optional `message.json` schema"
- "CLI команди enable/disable/status живуть у `platform/http` і discover-яться через existing tag `cli.command` (owner = `platform/cli`) за non-owner tag usage rule"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-XXXX-maintenance-mode-toggle.md"
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - `packages/platform/http/config/http.php` — додаємо `http.maintenance.*` ключі
  - `packages/platform/http/config/rules.php` — додаємо rules для `http.maintenance.*`
  - `packages/platform/http/src/Provider/HttpServiceProvider.php` — wiring middleware + CLI commands
  - `packages/core/foundation/src/Tag/ReservedTags.php` — tag constants owner=http (для `http.middleware.system_pre`)

- Runtime state location policy (MUST):
  - `packages/applications/skeleton/var/maintenance/` is the reserved runtime state directory for this epic.
  - The directory itself is created by this epic via `packages/applications/skeleton/var/maintenance/.gitkeep`.

- Required config roots/keys:
  - `http.maintenance.*` — toggles + paths + retry-after defaults

- Existing root reuse note (MUST):
  - This epic extends an existing reserved config root with additional subkeys only.
  - It MUST NOT be treated as a config-root owner/introduction epic.

- Required tags:
  - `http.middleware.system_pre` — slot для middleware (owner=`platform/http`)
  - `cli.command` — discovery of commands (owner=`platform/cli`; preferred runtime-code pattern here is a package-local internal mirror constant, for example a private constant in the wiring class; canonical string literal is allowed only as fallback because owner package is a forbidden compile-time dependency here)

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — optional (якщо потрібно; без write)
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface` — optional guard toggles/paths (без секретів у виводі)
  - `Psr\Http\Server\MiddlewareInterface` — middleware
  - `Psr\Http\Message\ServerRequestInterface` — middleware
  - `Psr\Http\Message\ResponseInterface` — middleware

- CLI commands MUST implement:
  - `Coretsia\Contracts\Cli\Command\CommandInterface`
- CLI I/O MUST use only:
  - `Coretsia\Contracts\Cli\Input\InputInterface`
  - `Coretsia\Contracts\Cli\Output\OutputInterface`
- Commands MUST NOT write to stdout/stderr directly (no `echo/print/var_dump/...`).

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- direct imports of kernel artifact/compiler internals (maintenance state MUST stay independent from artifacts/fingerprint logic)
- any other `platform/*` runtime packages (крім self)
- `platform/cli` (commands discovered via tag; no dep)
- `integrations/*`

Slice boundary note (MUST):
- This epic lives inside package `platform/http`, whose Phase 3 baseline already depends on `core/kernel`.
- The restriction here is narrower:
  - maintenance slice code MUST NOT import kernel artifact/compiler internals or depend on artifact/fingerprint behavior.
- The real invariant is runtime-state independence, not package-level removal of the existing baseline dependency.

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` (optional)
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface` (optional)

### Entry points / integration points (MUST)

- CLI:
  - `maintenance:enable` → `packages/platform/http/src/Console/MaintenanceEnableCommand.php`
  - `maintenance:disable` → `packages/platform/http/src/Console/MaintenanceDisableCommand.php`
  - `maintenance:status` → `packages/platform/http/src/Console/MaintenanceStatusCommand.php`
- HTTP:
  - middleware: `\Coretsia\Http\Maintenance\MaintenanceMiddleware::class`
  - middleware slots/tags: `http.middleware.system_pre` priority `900` meta `{toggle:'http.maintenance.enabled'}`
  - bypass rules:
    - `/_alive` exact bypass (always 200)
    - (optional) `/_debug/*` bypass only if debug diagnostics are enabled+guarded by their owner policy (no secret leakage)
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - reads:
    - `packages/applications/skeleton/var/maintenance/enabled`
    - `packages/applications/skeleton/var/maintenance/message.json` (optional)
  - writes (via CLI commands only):
    - `packages/applications/skeleton/var/maintenance/enabled`
    - `packages/applications/skeleton/var/maintenance/message.json` (optional)

### Deliverables (MUST)

#### Creates

- [ ] `packages/platform/http/src/Maintenance/MaintenanceStateReader.php` — reads flag + validates optional message schema (safe)
- [ ] `packages/platform/http/src/Maintenance/MaintenanceMiddleware.php` — returns deterministic 503 when enabled; bypass `/_alive`
- [ ] `packages/platform/http/src/Maintenance/MaintenanceMessage.php` — VO schema `{schemaVersion,message,retryAfterSeconds?}`
- [ ] `packages/platform/http/src/Maintenance/Exception/MaintenanceMessageInvalidException.php` — errorCode `CORETSIA_MAINTENANCE_MESSAGE_INVALID`

- [ ] `packages/platform/http/src/Console/MaintenanceEnableCommand.php` — creates `enabled` + optional `message.json` (deterministic bytes)
- [ ] `packages/platform/http/src/Console/MaintenanceDisableCommand.php` — removes `enabled` (atomic)
- [ ] `packages/platform/http/src/Console/MaintenanceStatusCommand.php` — reads state safely (no secrets)

- [ ] `packages/applications/skeleton/var/maintenance/.gitkeep` — runtime state dir placeholder; maintenance state files themselves are NOT committed deliverables
  - [ ] Runtime state policy (MUST):
    - [ ] `enabled` and `message.json` are runtime state written/removed by commands and MUST NOT be committed as repository deliverables

- [ ] `docs/ops/maintenance-mode.md` — how to enable/disable + safety notes (no artifacts)

- [ ] `packages/platform/http/tests/Integration/MaintenanceEnabledReturns503ForNonBypassedRoutesTest.php`
- [ ] `packages/platform/http/tests/Integration/AliveEndpointBypassesMaintenanceTest.php`
- [ ] `packages/platform/http/tests/Contract/MaintenanceMessageSchemaContractTest.php`
- [ ] `packages/platform/http/tests/Integration/MaintenanceCommandsDoNotDirtyArtifactsTest.php`

#### Modifies

- [ ] `packages/platform/http/config/http.php` — add `http.maintenance.*` keys + defaults
- [ ] `packages/platform/http/config/rules.php` — enforce shape for `http.maintenance.*`
- [ ] `packages/platform/http/src/Provider/HttpServiceProvider.php` — register middleware + add tag (system_pre) + register CLI commands (tag `cli.command`)
- [ ] `packages/platform/http/README.md` — section “Maintenance” (slot/tag/priority, bypass rules, file paths + schema)
- [ ] `docs/ssot/http-middleware-catalog.md` — add/update row for `MaintenanceMiddleware` (`http.middleware.system_pre` priority `900`)
- [ ] `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php` — extend shared catalog lock for `MaintenanceMiddleware` (`http.middleware.system_pre` priority `900`)
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-maintenance-mode-toggle.md`

#### Package skeleton (if type=package)

N/A (package `platform/http` already exists; this epic adds maintenance slice only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.maintenance.enabled` = true
  - [ ] `http.maintenance.flag_path` = 'packages/applications/skeleton/var/maintenance/enabled'
  - [ ] `http.maintenance.message_path` = 'packages/applications/skeleton/var/maintenance/message.json'
  - [ ] `http.maintenance.retry_after_default_seconds` = 60
- [ ] Rules:
  - [ ] `packages/platform/http/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Http\Maintenance\MaintenanceMiddleware`
  - [ ] adds tag: `http.middleware.system_pre` priority `900` meta `{toggle:'http.maintenance.enabled'}`
  - [ ] registers: `Coretsia\Http\Console\MaintenanceEnableCommand`
  - [ ] registers: `Coretsia\Http\Console\MaintenanceDisableCommand`
  - [ ] registers: `Coretsia\Http\Console\MaintenanceStatusCommand`
  - [ ] adds tag: `cli.command` for each command using a package-local internal mirror constant
    (for example a private constant in the wiring class; canonical string literal is allowed only as fallback; priority bands per CLI SSoT)

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `packages/applications/skeleton/var/maintenance/enabled` (existence flag)
  - [ ] `packages/applications/skeleton/var/maintenance/message.json` (schemaVersion, deterministic bytes)
- [ ] Reads:
  - [ ] `packages/applications/skeleton/var/maintenance/message.json` schema is validated deterministically (schemaVersion int; safe fields only)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Metrics:
  - [ ] `http.maintenance_total` (labels: `outcome=served|bypassed`)
- [ ] Logs:
  - [ ] maintenance served → info (no request dump)
  - [ ] message.json parse error → warn (no file contents)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Http\Maintenance\Exception\MaintenanceMessageInvalidException` — errorCode `CORETSIA_MAINTENANCE_MESSAGE_INVALID`
- [ ] Mapping:
  - [ ] if message invalid → ignore message and serve generic 503 (preferred), log warn

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] debug token, request headers/cookies
- [ ] Allowed:
  - [ ] safe status + retry-after seconds

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `N/A`
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist → `packages/platform/http/tests/Integration/MaintenanceEnabledReturns503ForNonBypassedRoutesTest.php`
- [ ] If redaction exists → `packages/platform/http/tests/Contract/MaintenanceMessageSchemaContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `packages/platform/http/tests/Contract/MaintenanceMessageSchemaContractTest.php`
- Integration:
  - [ ] `packages/platform/http/tests/Integration/MaintenanceEnabledReturns503ForNonBypassedRoutesTest.php`
  - [ ] `packages/platform/http/tests/Integration/AliveEndpointBypassesMaintenanceTest.php`
  - [ ] `packages/platform/http/tests/Integration/MaintenanceCommandsDoNotDirtyArtifactsTest.php`
- Gates/Arch:
  - [ ] deptrac green

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: CLI enable/disable/status не роблять artifacts dirty; rerun-no-diff where applicable
- [ ] Docs updated:
  - [ ] `packages/platform/http/README.md`
  - [ ] `docs/ops/maintenance-mode.md`
  - [ ] ADR registered
- [ ] CLI tag usage stays compliant with tag SSoT:
  - [ ] no public owner-like `cli.command` API is introduced in `platform/http`
  - [ ] command discovery prefers a package-local internal mirror constant in existing wiring code
  - [ ] no extra non-owner `Tags.php` file is introduced for this slice
- [ ] Deterministic message.json bytes policy (MUST)
  - [ ] `message.json` bytes MUST be produced via `Coretsia\Foundation\Serialization\StableJsonEncoder` (no ad-hoc json_encode).
  - [ ] Writer MUST:
    - [ ] write LF-only + final newline,
    - [ ] be atomic (write temp + rename),
    - [ ] never include absolute paths/timestamps/env dumps in payload.
  - [ ] Schema:
    - [ ] `{ "schemaVersion": int, "message": string, "retryAfterSeconds"?: int }`
    - [ ] floats/NaN/INF forbidden, arrays normalized per json-like policy.

---

### 3.90.0 SystemRouteTable reserved map (SSoT) (MUST) [DOC]

---
type: package
phase: 3
epic_id: "3.90.0"
owner_path: "packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "Reserved/system endpoints список є centralized, versioned і enforced у CI (single source of truth)."
provides:
- "Єдине джерело правди для reserved/system endpoints (active/dev_only/reserved)"
- "CI enforcement через gate: таблиця не може “поплисти” без оновлення правил/fixtures"
- "База для compile-time guard у routing (ReservedRoutesGuard) та runtime FallbackRouter"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-XXXX-system-route-table-reserved-map.md"
ssot_refs:
- "docs/ssot/system-route-table.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - N/A

- Required tags:
  - N/A

- Required contracts / ports:
  - N/A (pure table + tooling gate)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/foundation` (optional; deterministic ordering helpers if needed)
- `core/contracts` (optional; no hard requirement)

Forbidden:
- any other `platform/*` runtime packages
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- Gates/CI:
  - `tools/gates/system_route_table_gate.php` — validates table invariants + version bump policy
- CLI:
  - N/A
- HTTP:
  - N/A
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `packages/platform/http/src/System/SystemRouteTable.php` — entries + `SCHEMA_VERSION`
- [ ] `tools/gates/system_route_table_gate.php` — validates table invariants + version bump policy
- [ ] `packages/platform/http/tests/Contract/SystemRouteTableShapeContractTest.php` — shape + invariants
- [ ] `tools/gates/tests/SystemRouteTableGateTest.php` — fixture good/bad
- [ ] `docs/ssot/system-route-table.md` — meaning of `active|reserved|dev_only` + how owners claim endpoints later

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/system-route-table.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-system-route-table-reserved-map.md`

#### Package skeleton (if type=package)

N/A (package `platform/http` already exists; this epic adds system table + gate + docs)

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

N/A

#### Errors

N/A

#### Security / Redaction

- [ ] Security:
  - [ ] reserved endpoints MUST include `/_debug/` as `dev_only` (guarded by its owner policy)
- [ ] Determinism:
  - [ ] stable ordering of entries (if export exists): `pattern ASC, type ASC`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `N/A`
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist → `N/A`
- [ ] If redaction exists → `N/A`
- [ ] Gate enforcement exists → `tools/gates/tests/SystemRouteTableGateTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Gate fixtures:
  - [ ] `tools/gates/tests/Fixtures/SystemRouteTable/*` (good/bad snapshots)

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `packages/platform/http/tests/Contract/SystemRouteTableShapeContractTest.php`
- Integration:
  - N/A
- Gates/Arch:
  - [ ] `tools/gates/tests/SystemRouteTableGateTest.php`

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable (gate test proves enforcement)
- [ ] Docs updated:
  - [ ] `docs/ssot/system-route-table.md`
  - [ ] ADR registered
- [ ] Gate output policy (MUST)
  - [ ] Gate MUST use tools ConsoleOutput only (`tools/support/ConsoleOutput.php`).
  - [ ] Output:
    - [ ] line1: deterministic CODE
    - [ ] line2+: diagnostics sorted `strcmp`, paths normalized (forward slashes), no absolute paths.
  - [ ] Gate MUST be rerun-no-diff (no writes).

---

### 3.100.0 Reserved path matching rules (SSoT) (MUST) [DOC]

---
type: package
phase: 3
epic_id: "3.100.0"
owner_path: "packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "Matching rules для reserved endpoints є canonical, стабільні й спільні для runtime та compile-time guard."
provides:
- "Єдина canonical реалізація matching правил (exact vs segment-prefix) для reserved endpoints"
- "Спільне використання у runtime (FallbackRouter) і compile-time guard (routing compiler)"
- "Документовані normalization правила (leading/trailing slash, prefix boundary)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-XXXX-reserved-path-matching-rules.md"
ssot_refs:
- "docs/ssot/reserved-path-matching.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `3.90.0` — `SystemRouteTable` визначає reserved endpoints; matcher не додає endpoints

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - N/A

- Required tags:
  - N/A

- Required contracts / ports:
  - N/A (works on `string $path`)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/foundation` (optional)

Forbidden:
- any other `platform/*` runtime packages
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `packages/platform/http/src/System/ReservedPathMatcher.php` — segment-prefix matcher (exact vs prefix boundary)
  - [ ] Matcher normalization invariants (MUST)
    - [ ] Matching MUST be defined on a **normalized path**:
      - [ ] collapse multiple slashes `//+` → `/` (single-choice),
      - [ ] ensure leading slash,
      - [ ] trailing slash semantics MUST be explicit in docs (`exact` vs `segment-prefix` boundary).
    - [ ] Segment-prefix match MUST require boundary:
      - [ ] `/health` matches `/health` and `/health/...`
      - [ ] MUST NOT match `/healthz`
    - [ ] Implementation MUST be pure string ops; no unbounded allocations proportional to input beyond O(n).

- [ ] `packages/platform/http/tests/Unit/ReservedPathMatcherSegmentPrefixTest.php` — edge cases (`//`, trailing slash, boundary)
- [ ] `docs/ssot/reserved-path-matching.md` — exact vs prefix semantics + examples

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/reserved-path-matching.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-reserved-path-matching-rules.md`

#### Package skeleton (if type=package)

N/A (package `platform/http` already exists; this epic adds matcher + docs)

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

N/A

#### Errors

N/A

#### Security / Redaction

- [ ] Security:
  - [ ] matcher MUST NOT allocate based on user input size unbounded (simple string ops only)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `N/A`
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist → `N/A`
- [ ] If redaction exists → `N/A`
- [ ] Semantic correctness proof → `packages/platform/http/tests/Unit/ReservedPathMatcherSegmentPrefixTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] `packages/platform/http/tests/Unit/ReservedPathMatcherSegmentPrefixTest.php`
- Contract/Integration:
  - N/A
- Gates/Arch:
  - [ ] deptrac green

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable (unit test covers edge cases)
- [ ] Docs updated:
  - [ ] `docs/ssot/reserved-path-matching.md`
  - [ ] ADR registered

---

### 3.110.0 HTTP FallbackRouter + system endpoints (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.110.0"
owner_path: "packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "FallbackRouter забезпечує deterministic pre-routing endpoints і reserved endpoints behavior до встановлення owners."
provides:
- "Deterministic “pre-routing” endpoints: `/`, `/ping`, `/_alive`, `/error`"
- "Reserved endpoints behavior (до owners): `/health*` → 501 JSON, `/metrics` → 501 text/plain"
- "Single source of truth: використовує `SystemRouteTable` + `ReservedPathMatcher` (NO DUPES)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/system-route-table.md"
- "docs/ssot/reserved-path-matching.md"
- "docs/ssot/http-system-endpoints.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `3.90.0` — `SystemRouteTable` exists (reserved map)
  - `3.100.0` — `ReservedPathMatcher` exists (matching semantics)

- Required deliverables (exact paths):
  - `packages/platform/http/src/Routing/FallbackRouterHandler.php` — terminal handler hook point (owner=http runtime)
  - `packages/platform/http/src/System/SystemEndpointPayload.php` — canonical payload helper (can be introduced earlier; this epic may extend it)
  - `packages/platform/http/src/Provider/HttpServiceProvider.php` — wiring terminal handler to pipeline

- Required config roots/keys:
  - N/A

- Required tags:
  - N/A

- Required contracts / ports:
  - `Psr\Http\Server\RequestHandlerInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\ResponseFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`
  - `Psr\Clock\ClockInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:
- any other `platform/*` runtime packages (особливо `platform/metrics|platform/health|platform/http-app|platform/routing`)
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\RequestHandlerInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\ResponseFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Clock\SystemClock` (optional; або інший canonical clock)

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - terminal routes (owner=`platform/http`):
    - `GET /` → 200 HTML “Hello Coretsia”
    - `GET /ping` → 200 JSON `{"ok":true,"ts":<unix>}`
    - `GET /_alive` → 200 text `ALIVE`
    - `GET /error` → throws deterministic exception for RFC7807 flow
  - reserved until owners installed:
    - `/health` exact + `/health/` prefix → 501 JSON (owner missing)
    - `/metrics` exact → 501 text/plain (owner missing)
  - dev-only:
    - `/_debug/` prefix delegates to dev diagnostics owner policy (see 3.120.0; if disabled → deterministic 404/403 by that policy)
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

- Precedence rule (MUST):
  - `FallbackRouter` is terminal fallback only.
  - If an earlier middleware/app routing layer already produced a response, `FallbackRouter` MUST NOT run.
  - Therefore the built-in `/` response applies only when no earlier route handled `/`.

- Method claim policy (MUST):
  - built-in system endpoints in this epic are claimed only for the exact method+path pairs listed above
  - `FallbackRouter` MUST NOT synthesize package-local `405` handling in this epic
  - unsupported methods for those paths fall through as not matched

### Deliverables (MUST)

#### Creates

- [ ] `packages/platform/http/src/Routing/FallbackRouter.php` — endpoint dispatch (uses SystemRouteTable + ReservedPathMatcher)
- [ ] `packages/platform/http/src/Routing/FallbackResponses.php` — helpers for deterministic response bodies/headers
  - [ ] JSON bodies produced here MUST serialize via `Coretsia\Foundation\Serialization\StableJsonEncoder`
  - [ ] map keys MUST be recursively sorted by `strcmp`; lists preserve order
  - [ ] MUST NOT emit floats/NaN/INF
  - [ ] MUST NOT use ad-hoc `json_encode` directly in response helpers
- [ ] `packages/platform/http/src/Exception/IntentionalErrorException.php` — thrown on `/error` (`CORETSIA_HTTP_INTENTIONAL_ERROR`)
- [ ] `docs/ssot/http-system-endpoints.md` — owned vs reserved endpoints + owner-claim rules

- [ ] `packages/platform/http/tests/Integration/HomeReturns200HtmlTest.php`
- [ ] `packages/platform/http/tests/Integration/PingReturns200JsonTest.php`
- [ ] `packages/platform/http/tests/Integration/AliveReturns200TextTest.php`
- [ ] `packages/platform/http/tests/Integration/SystemHealthEndpointsReturn501UntilOwnerInstalledTest.php`
- [ ] `packages/platform/http/tests/Integration/SystemMetricsEndpointReturns501PlaintextUntilOwnerInstalledTest.php`
- [ ] `packages/platform/http/tests/Integration/ErrorRouteTriggersCanonicalErrorFlowTest.php`

#### Modifies

- [ ] `packages/platform/http/src/Routing/FallbackRouterHandler.php` — delegate to `FallbackRouter` (single dispatch point)
- [ ] `packages/platform/http/src/System/SystemEndpointPayload.php` — add/align canonical payloads for 501/503 (no secrets)
- [ ] `packages/platform/http/src/Provider/HttpServiceProvider.php` — wire router into terminal handler (if not already)
- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/http-system-endpoints.md`

#### Package skeleton (if type=package)

N/A (package `platform/http` already exists; this epic adds fallback routing slice)

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A (terminal handler is wired by http runtime; no new tags introduced)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Metrics:
  - [ ] `http.system_endpoint_total` (labels: `operation`, `status`, `outcome`)
- [ ] Logs:
  - [ ] minimal logs via AccessLogMiddleware (no extra logs from router unless warn/error)

#### Errors

- [ ] `/error` throws deterministic exception; rendering handled by RFC7807 middleware (owner problem-details)

#### Security / Redaction

- [ ] `/ping` payload contains only `{ok, ts}` (no env/config details)
- [ ] `/health|/metrics` 501 payload must not leak configs (only “owner missing”)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `N/A`
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist → covered implicitly by endpoint integration tests (no extra emission required here)
- [ ] If redaction exists → endpoint integration tests assert bodies do not contain config/env secrets

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit/Contract:
  - N/A
- Integration:
  - [ ] `packages/platform/http/tests/Integration/HomeReturns200HtmlTest.php`
  - [ ] `packages/platform/http/tests/Integration/PingReturns200JsonTest.php`
  - [ ] `packages/platform/http/tests/Integration/AliveReturns200TextTest.php`
  - [ ] `packages/platform/http/tests/Integration/SystemHealthEndpointsReturn501UntilOwnerInstalledTest.php`
  - [ ] `packages/platform/http/tests/Integration/SystemMetricsEndpointReturns501PlaintextUntilOwnerInstalledTest.php`
  - [ ] `packages/platform/http/tests/Integration/ErrorRouteTriggersCanonicalErrorFlowTest.php`
  - [ ] `packages/platform/http/tests/Integration/UnsupportedMethodForSystemEndpointFallsThroughAsNotMatchedTest.php`
- Gates/Arch:
  - [ ] deptrac green

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Deterministic outputs (stable headers + body)
- [ ] System endpoint claim semantics are locked:
  - [ ] only the listed method+path pairs are handled by `FallbackRouter`
  - [ ] unsupported methods fall through as not matched
- [ ] `/error` proves RFC7807 flow (when problem-details installed)
- [ ] Docs updated:
  - [ ] `docs/ssot/http-system-endpoints.md`
- [ ] /ping time source policy (MUST)
  - [ ] `/ping` MUST obtain time via DI clock (no `time()` in core logic):
    - [ ] prefer `Psr\Clock\ClockInterface` binding provided by Foundation (`SystemClock` prod, `FrozenClock` tests).
  - [ ] Integration tests MUST assert:
    - [ ] `ts` is int,
    - [ ] not exact value (unless FrozenClock is used in fixture boot).

---

### 3.120.0 HTTP dev diagnostics endpoints (SHOULD) [IMPL]

---
type: package
phase: 3
epic_id: "3.120.0"
owner_path: "packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "У local/dev `/_debug/*` дає корисну діагностику без витоку секретів; у prod — недоступно/guarded deterministically."
provides:
- "Dev-only diagnostics endpoints під `/_debug/*` (handlers, не middleware у Phase 0/3 baseline)"
- "Strict guard: local/allowlist/token; default disabled; **ніколи** не друкує секрети"
- "Debug endpoints: safe headers, modules list, config formation trace (redacted), cache status summary"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/system-route-table.md"
---

- [ ] update `3.120.0 HTTP dev diagnostics endpoints` to consume `SensitiveDataRedactorInterface` and forbid raw config/env/request/session dumps.
- [ ] update `3.120.0 HTTP dev diagnostics endpoints` to contribute `DebugExceptionMapper` through the shared `error.mapper` tag with empty `[]` metadata and canonical priority ordering, reusing Foundation `ReservedTags::ERROR_MAPPER` without a second handler or local tag constant.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `3.90.0` — SystemRouteTable marks `/_debug/` as dev_only
  - `3.110.0` — FallbackRouter dispatches `/_debug/` prefix to debug router (or equivalent hook)

- Required deliverables (exact paths):
  - `packages/platform/http/config/http.php` — додаємо `http.debug.*` keys
  - `packages/platform/http/config/rules.php` — rules для `http.debug.*`
  - `packages/platform/http/src/Provider/HttpServiceProvider.php` — register debug services/handlers

- Required config roots/keys:
  - `http.debug.*` — enable/guard/token/allowlist/path allowlist

- Existing root reuse note (MUST):
  - This epic extends an existing reserved config root with additional subkeys only.
  - It MUST NOT be treated as a config-root owner/introduction epic.

- Required tags:
  - N/A for middleware wiring (debug endpoints are dispatched by router, not via middleware tags)
  - `error.mapper` — exception mapping for canonical 404/403 flow (owner=`platform/errors`; because owner package is a forbidden compile-time dependency here, runtime wiring SHOULD use a package-local internal mirror constant; canonical string literal is allowed only as fallback)

- Required contracts / ports:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface` (for config debug)
  - `ConfigValueSource` — source-tracking contract introduced by 1.80.0, used for deterministic redacted source info in debug config output
  - `Coretsia\Contracts\Module\ManifestReaderInterface` (for modules list)
  - `Coretsia\Contracts\Module\ModePresetLoaderInterface` (optional: show preset)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:
- any other `platform/*` runtime packages
- `platform/cli` (no dep)
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface`
  - `Coretsia\Contracts\Module\ManifestReaderInterface`
  - `Coretsia\Contracts\Module\ModePresetLoaderInterface` (optional)
- Foundation stable APIs (if used):
  - `Coretsia\Contracts\Context\ContextKeys`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - dev-only routes (owner=`platform/http`):
    - `GET /_debug/headers`
    - `GET /_debug/modules`
    - `GET /_debug/config?key=...`
    - `GET /_debug/cache`
  - dispatch model:
    - dispatched from `FallbackRouter` (3.110.0) on `/_debug/` prefix, then routed by `DebugRouter` if `DebugGuard` passes
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - reads (safe summary only):
    - may read `packages/applications/skeleton/var/cache/<appId>/*` only to show cache status summary (no values)

### Deliverables (MUST)

#### Creates

- [ ] `packages/platform/http/src/Debug/DebugGuard.php` — allowlist/token/local env guard
- [ ] `packages/platform/http/src/Debug/DebugRouter.php` — routes `/_debug/*` to handlers deterministically
- [ ] `packages/platform/http/src/Debug/DebugHeadersHandler.php` — outputs only safe headers (redacts Authorization/Cookie/Set-Cookie)
- [ ] `packages/platform/http/src/Debug/DebugModuleListHandler.php` — lists enabled modules (safe)
- [ ] `packages/platform/http/src/Debug/DebugConfigHandler.php` — formation trace + redacted value (no secrets)
- [ ] `packages/platform/http/src/Debug/DebugCacheHandler.php` — cache artifact status summary (no values)
  - [ ] Cache status MUST be summary-only:
    - [ ] existence + byte size + sha256 (optional),
    - [ ] MUST NOT parse artifacts payloads,
    - [ ] MUST NOT output absolute paths or file contents.
- [ ] `packages/platform/http/src/Debug/Redaction.php` — helper `hash/len`, header redaction map

- [ ] `packages/platform/http/src/Debug/Exception/DebugForbiddenException.php` — errorCode `CORETSIA_HTTP_DEBUG_FORBIDDEN` (403)
- [ ] `packages/platform/http/src/Debug/Exception/DebugDisabledException.php` — errorCode `CORETSIA_HTTP_DEBUG_DISABLED` (deterministic 404 when debug endpoints are disabled)
- [ ] `packages/platform/http/src/Error/DebugExceptionMapper.php`
  - [ ] implements `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - [ ] maps:
    - [ ] `DebugDisabledException` → `ErrorDescriptor` with optional HTTP hint `404`
    - [ ] `DebugForbiddenException` → `ErrorDescriptor` with optional HTTP hint `403`
  - [ ] MUST NOT import `platform/errors`, because it is a forbidden compile-time dependency here
  - [ ] MUST be registered via DI tag `error.mapper` using a package-local internal mirror constant
    - [ ] canonical string literal `'error.mapper'` is allowed only as fallback

- [ ] `docs/guides/http-debug-endpoints.md` — how to enable in local + security warnings + redaction rules

- [ ] `packages/platform/http/tests/Integration/DebugEndpointsGuardTest.php`
- [ ] `packages/platform/http/tests/Integration/DebugHeadersRedactsSensitiveHeadersTest.php`
- [ ] `packages/platform/http/tests/Integration/DebugConfigShowsSourcesButNotSecretsTest.php`
- [ ] `packages/platform/http/tests/Integration/DebugDisabledAndForbiddenTriggerCanonicalErrorFlowTest.php`

#### Modifies

- [ ] `packages/platform/http/config/http.php` — add `http.debug.*` keys + defaults
- [ ] `packages/platform/http/config/rules.php` — enforce shape for `http.debug.*`
- [ ] `packages/platform/http/src/Provider/HttpServiceProvider.php` — register guard/router/handlers
- [ ] `packages/platform/http/README.md` — section “Debug endpoints” (handlers dispatch model; why dev_only; redaction rules)
- [ ] `docs/ssot/http-system-endpoints.md` — add dev-only `/_debug/*` endpoint set + guard/disabled behavior policy

#### Package skeleton (if type=package)

N/A (package `platform/http` already exists; this epic adds debug diagnostics slice)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.debug.enabled` = false
  - [ ] `http.debug.allowlist` = ['127.0.0.1', '::1']
  - [ ] `http.debug.token` = null
  - [ ] `http.debug.token_header` = 'X-Debug-Token'
  - [ ] `http.debug.paths.enabled` = ['headers','modules','config','cache']
  - [ ] `http.debug.config.allowlist` = [] (default empty; when empty => endpoint disabled even if debug enabled)
- [ ] Normative:
  - [ ] raw config values MUST NEVER be returned by `DebugConfigHandler`
  - [ ] therefore there MUST be no `http.debug.config.show_values` toggle
  - [ ] debug config output is limited to:
    - [ ] type (`string|int|bool|null|list|map`)
    - [ ] `len(value)` / `sha256(value)` for strings
    - [ ] counts for lists/maps
    - [ ] source info (`ConfigValueSource`) without absolute paths
- [ ] Rules:
  - [ ] `packages/platform/http/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Http\Debug\DebugGuard`
  - [ ] registers: `Coretsia\Http\Debug\DebugRouter`
  - [ ] registers debug handlers
  - [ ] registers: `Coretsia\Http\Error\DebugExceptionMapper`
  - [ ] adds tag: `error.mapper` using a package-local internal mirror constant
    - [ ] canonical string literal `'error.mapper'` is allowed only as fallback

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Metrics:
  - [ ] `http.debug_total` (labels: `operation`, `outcome=allowed|blocked`)
- [ ] Logs:
  - [ ] blocked access → warn (no token dump; client_ip only if already safe in context)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Http\Debug\Exception\DebugForbiddenException` — errorCode `CORETSIA_HTTP_DEBUG_FORBIDDEN`
  - [ ] `Coretsia\Http\Debug\Exception\DebugDisabledException` — errorCode `CORETSIA_HTTP_DEBUG_DISABLED`
- [ ] Mapping:
  - [ ] throw deterministic exceptions; RFC7807 middleware renders (no dupes)
  - [ ] `DebugDisabledException` corresponds to the disabled case only (`404`)
  - [ ] `DebugForbiddenException` corresponds to the enabled-but-blocked case only (`403`)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/Set-Cookie headers, debug token, session ids, any config secret values
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (module ids)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `N/A`
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist → `packages/platform/http/tests/Integration/DebugEndpointsGuardTest.php`
- [ ] If redaction exists → `packages/platform/http/tests/Integration/DebugHeadersRedactsSensitiveHeadersTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit/Contract:
  - N/A
- Integration:
  - [ ] `packages/platform/http/tests/Integration/DebugEndpointsGuardTest.php`
  - [ ] `packages/platform/http/tests/Integration/DebugHeadersRedactsSensitiveHeadersTest.php`
  - [ ] `packages/platform/http/tests/Integration/DebugConfigShowsSourcesButNotSecretsTest.php`
  - [ ] `packages/platform/http/tests/Integration/DebugDisabledAndForbiddenTriggerCanonicalErrorFlowTest.php`
- Gates/Arch:
  - [ ] deptrac green

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Default disabled; guard works (local/allowlist/token)
- [ ] No secret leakage (tests)
- [ ] Docs updated:
  - [ ] `packages/platform/http/README.md`
  - [ ] `docs/guides/http-debug-endpoints.md`
  - [ ] `docs/ssot/http-system-endpoints.md`
- [ ] Debug disabled/blocked response policy (single-choice) (MUST)
  - [ ] If `http.debug.enabled=false`:
    - [ ] MUST respond `404` (not found) for all `/_debug/*` endpoints.
  - [ ] If enabled but guard fails (ip/token/path not allowed):
    - [ ] MUST respond `403` with deterministic error code `CORETSIA_HTTP_DEBUG_FORBIDDEN`.
  - [ ] This avoids ambiguous 404/403 behavior across environments.
- [ ] DebugConfigHandler redaction + allowlist (MUST)
  - [ ] allow only allowlisted key prefixes,
    - [ ] never output raw values; only:
      - [ ] type (`string|int|bool|null|list|map`),
      - [ ] `len(value)` / `sha256(value)` for strings (no raw),
      - [ ] counts for lists/maps,
      - [ ] source info (ConfigValueSource) without absolute paths.

---

### 3.130.0 Platform routing (compiled routes artifact + runtime router) (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.130.0"
owner_path: "packages/platform/routing/"

package_id: "platform/routing"
composer: "coretsia/platform-routing"
kind: runtime
module_id: "platform.routing"

goal: "Deterministic routes artifact компілюється і читається runtime router’ом (artifact-only у prod), із reserved endpoints guard."
provides:
- "Real deterministic routes artifact: `packages/applications/skeleton/var/cache/<appId>/routes.php` (schemaVersion pinned; rerun → no diff)"
- "Runtime `CompiledRouter` (artifact-only policy) + writes `ContextKeys::PATH_TEMPLATE` (low-cardinality)"
- "Compile-time reserved endpoints guard: uses `platform/http` `SystemRouteTable` + `ReservedPathMatcher` (NO DUPES)"

tags_introduced:
- "routing.route_provider"

config_roots_introduced:
- "routing"

artifacts_introduced: []
adr: "docs/adr/ADR-XXXX-platform-routing-compiled-routes.md"
ssot_refs:
- "docs/ssot/system-route-table.md"
- "docs/ssot/reserved-path-matching.md"
- "docs/ssot/routing-artifact.md"
- "docs/ssot/artifacts.md"
- "docs/ssot/tags.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `3.90.0` — `platform/http` `SystemRouteTable` exists (reserved endpoints SSoT)
  - `3.100.0` — `platform/http` `ReservedPathMatcher` exists (matching semantics)

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - `routing.*` — config root for compile+runtime toggles + artifact path

- Required tags:
  - `cli.command` — command discovery (owner=`platform/cli`; because owner package is a forbidden compile-time dependency here, runtime wiring SHOULD use a package-local internal mirror constant and MAY fall back to the canonical string literal)

- Required tags registry:
  - `routing.route_provider`:
    - N/A at epic start.
    - This epic is the owner epic that adds `routing.route_provider` to `docs/ssot/tags.md`
      and introduces the canonical owner constant in `packages/core/foundation/src/Tag/ReservedTags.php`.

- Tag usage (MUST) — make ordering source-of-truth explicit
  - `RouteProviderRegistry` MUST enumerate providers ONLY via:
    - `Coretsia\Foundation\Tag\TagRegistry::all(ReservedTags::ROUTE_PROVIDER)`
  - Registry/compiler MUST NOT re-sort or de-duplicate provider entries returned by TagRegistry.
  - Effective provider order is:
    - tag order = `priority DESC, serviceId ASC (strcmp)`
    - inside one provider, route definition order is preserved exactly as emitted by that provider.

- Required contracts / ports:
  - `Coretsia\Contracts\Routing\RouteDefinition`
  - `Coretsia\Contracts\Routing\RouteMatch`
  - `Coretsia\Contracts\Routing\RouterInterface`
  - `Coretsia\Contracts\Routing\RouteProviderInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)
  - `Psr\Log\LoggerInterface` (optional)

- Phase invariants reused (NO DUPES) (MUST):
  - Artifact determinism policy MUST match kernel artifacts policy (stable bytes, LF, no timestamps, schemaVersion pinned, rerun → no diff)
  - Reserved endpoints guard MUST reuse Phase SSoT:
    - `platform/http` `SystemRouteTable`
    - `platform/http` `ReservedPathMatcher`
  - “No raw path in labels” policy:
    - only `ContextKeys::PATH_TEMPLATE` allowed as span attr / context key, NOT as metric label.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `platform/http` (compile-time: uses `SystemRouteTable` + `ReservedPathMatcher`)

Forbidden:
- `core/kernel` (уникаємо залежності на kernel internals; header policy виконуємо локально)
- `platform/http-app`
- `platform/errors`
- `platform/problem-details`
- any `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Routing\RouteDefinition`
  - `Coretsia\Contracts\Routing\RouteMatch`
  - `Coretsia\Contracts\Routing\RouterInterface`
  - `Coretsia\Contracts\Routing\RouteProviderInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Context\ContextStore`
  - `Coretsia\Contracts\Context\ContextKeys`
  - `Coretsia\Foundation\Discovery\DeterministicOrder`
  - `Coretsia\Foundation\Tag\TagRegistry`

### Entry points / integration points (MUST)

- CLI:
  - `routes:compile` → `packages/platform/routing/src/Console/RoutesCompileCommand.php`
- HTTP:
  - N/A (routing consumed by http-app RouterMiddleware; owner=`platform/http-app`)
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - writes: `packages/applications/skeleton/var/cache/<appId>/routes.php`
  - reads: `packages/applications/skeleton/var/cache/<appId>/routes.php` (runtime)
  - validates header + payload schema on read

### Deliverables (MUST)

#### Creates

Package skeleton:
- [ ] `packages/platform/routing/composer.json`
- [ ] `packages/platform/routing/src/Module/RoutingModule.php`
- [ ] `packages/platform/routing/src/Provider/RoutingServiceProvider.php`
- [ ] `packages/platform/routing/src/Provider/RoutingServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `packages/platform/routing/config/routing.php`
- [ ] `packages/platform/routing/config/rules.php`
- [ ] `packages/platform/routing/README.md`
- [ ] `packages/platform/routing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

Tags + registry:
- [ ] `packages/core/foundation/src/Tag/ReservedTags.php` — tag constants
- [ ] `packages/platform/routing/src/Compile/RouteProviderRegistry.php` — collects `RouteProviderInterface` via tag `routing.route_provider`

Compile:
- [ ] `packages/platform/routing/src/Compile/RoutesCompiler.php` — deterministic compile (providers deterministic; per-provider order preserved)
- [ ] `packages/platform/routing/src/Compile/ReservedRoutesGuard.php` — blocks reserved paths/prefixes using `SystemRouteTable` + `ReservedPathMatcher`

Artifacts:
- [ ] `packages/platform/routing/src/Artifacts/RoutesArtifactWriter.php` — atomic write + deterministic bytes
- [ ] `packages/platform/routing/src/Artifacts/RoutesArtifactReader.php` — validates header + payload schemaVersion
- [ ] `packages/platform/routing/src/Artifacts/RoutesArtifactHeader.php` — header VO (schemaVersion pinned; no timestamps)
- [ ] `packages/platform/routing/src/Artifacts/Php/StablePhpArrayDumper.php`
  - [ ] stable key ordering (`strcmp`) for maps at every depth,
  - [ ] consistent indentation,
  - [ ] LF-only + final newline,
  - [ ] no var_export locale dependence, no timestamps.

Runtime:
- [ ] `packages/platform/routing/src/Runtime/CompiledRouter.php` — implements `RouterInterface`, reads artifact, writes `ContextKeys::PATH_TEMPLATE`
- [ ] `packages/platform/routing/src/Runtime/PathNormalizer.php` — normalization MUST match reserved matcher policy

CLI:
- [ ] `packages/platform/routing/src/Console/RoutesCompileCommand.php` — writes artifact, safe output, supports `--format=json`
  - [ ] `--format=json` output MUST serialize via `Coretsia\Foundation\Serialization\StableJsonEncoder`
  - [ ] JSON output MUST be deterministic: recursive map-key sort `strcmp`, lists preserve order
  - [ ] JSON output MUST NOT include absolute paths, raw config values, or secrets

Errors:
- [ ] `packages/platform/routing/src/Exception/RoutesArtifactInvalidException.php` — `CORETSIA_ROUTES_ARTIFACT_INVALID`
- [ ] `packages/platform/routing/src/Exception/ReservedRouteViolationException.php` — `CORETSIA_ROUTING_RESERVED_ROUTE_FORBIDDEN`

Docs:
- [ ] `docs/ssot/routing-artifact.md` — artifact schemaVersion + determinism rules
- [ ] `docs/guides/routing.md` — how to register providers + run compile

Tests:
- [ ] `packages/platform/routing/tests/Unit/RouteMatcherStaticAndParamsTest.php`
- [ ] `packages/platform/routing/tests/Unit/ReservedRoutesGuardRejectsReservedPathsTest.php`

- [ ] `packages/platform/routing/tests/Contract/RoutesArtifactHeaderShapeContractTest.php`
- [ ] `packages/platform/routing/tests/Contract/RoutesCompileIsDeterministicTest.php`
- [ ] `packages/platform/routing/tests/Contract/ReservedGuardUsesSameMatcherAsHttpContractTest.php`
- [ ] `packages/platform/routing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `packages/platform/routing/tests/Contract/ArtifactBytesRerunNoDiffContractTest.php`
- [ ] `packages/platform/routing/tests/Contract/RoutesArtifactHeaderMatchesKernelPolicyContractTest.php`
- [ ] `packages/platform/routing/tests/Contract/ReservedGuardSemanticsMatchHttpMatcherContractTest.php`

- [ ] `packages/platform/routing/tests/Integration/RoutesCompileWritesArtifactTest.php`
- [ ] `packages/platform/routing/tests/Integration/RoutesArtifactRerunNoDiffTest.php`
- [ ] `packages/platform/routing/tests/Integration/CliRoutesCompileCommandWritesArtifactTest.php`
- [ ] `packages/platform/routing/tests/Integration/CompiledRouterSetsPathTemplateInContextStoreTest.php`

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-platform-routing-compiled-routes.md`
- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/routing-artifact.md`
- [ ] deptrac expectations updated: allow `platform/routing` → `platform/http` (compile-time) while keeping `platform/http` boundary intact
- [ ] `docs/ssot/tags.md` — register:
  - [ ] `routing.route_provider` (owner `platform/routing`) - RouteProvider discovery via Foundation TagRegistry; providers are ordered by TagRegistry (priority DESC, serviceId ASC), per-provider route order is preserved.
- [ ] `docs/ssot/config-roots.md` — register:
  - [ ] `routing | platform/routing | packages/platform/routing/config/routing.php | packages/platform/routing/config/rules.php | compiled routing artifact + runtime router settings`
- [ ] `docs/ssot/artifacts.md` — update the existing `routes@1` registry row if notes/path-shape need refinement; MUST NOT add a duplicate registry row
  - [ ] `routes` (owner `platform/routing`) - { "_meta": <header>, "payload": <schema> } | docs/ssot/routing-artifact.md | Deterministic bytes; LF-only + final newline; no timestamps/abs paths/secrets; rerun-no-diff.

#### Package skeleton (if type=package)

- [ ] `packages/platform/routing/composer.json`
- [ ] `packages/platform/routing/src/Module/RoutingModule.php`
- [ ] `packages/platform/routing/src/Provider/RoutingServiceProvider.php`
- [ ] `packages/platform/routing/config/routing.php`  # returns subtree (no repeated root)
- [ ] `packages/platform/routing/config/rules.php`
- [ ] `packages/platform/routing/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `packages/platform/routing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/routing/config/routing.php`
- [ ] Keys (dot):
  - [ ] `routing.compile.enabled` = true
  - [ ] `routing.runtime.enabled` = true
  - [ ] `routing.artifacts.path` = 'packages/applications/skeleton/var/cache/<appId>/routes.php'
  - [ ] `routing.compile.strict_reserved_guard` = true
- [ ] Rules:
  - [ ] `packages/platform/routing/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `packages/core/foundation/src/Tag/ReservedTags.php`
  - [ ] constants:
    - [ ] `ROUTE_PROVIDER = 'routing.route_provider'`
- [ ] `cli.command` usage rule (MUST):
  - [ ] because owner package `platform/cli` is a forbidden compile-time dependency here, any mirror constant for `cli.command`
    MUST be package-internal only (for example a private constant inside the wiring class)
  - [ ] `cli.command` MUST NOT be exposed from `packages/core/foundation/src/Tag/ReservedTags.php` as public API of `platform/routing`
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Routing\Compile\RouteProviderRegistry`
  - [ ] registers: `Coretsia\Routing\Compile\RoutesCompiler`
  - [ ] registers: `Coretsia\Routing\Compile\ReservedRoutesGuard`
  - [ ] registers: `Coretsia\Routing\Artifacts\RoutesArtifactWriter`
  - [ ] registers: `Coretsia\Routing\Artifacts\RoutesArtifactReader`
  - [ ] registers: `Coretsia\Routing\Runtime\CompiledRouter`
  - [ ] registers: `Coretsia\Routing\Console\RoutesCompileCommand`
  - [ ] adds tag: `cli.command` priority `0` meta `{name:'routes:compile'}`
    - [ ] runtime wiring SHOULD use a package-local internal mirror constant
    - [ ] canonical string literal is allowed only as fallback

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `packages/applications/skeleton/var/cache/<appId>/routes.php` (schemaVersion pinned; deterministic bytes; rerun → no diff)
- [ ] Reads:
  - [ ] validates header + payload schema

- [ ] Produced artifact MUST have envelope:
  - [ ] `{ "_meta": { "name":"routes", "schemaVersion":1, "fingerprint":"...", "generator":"...", "requires":... }, "payload": <routes schema> }`
- [ ] Serialization MUST be deterministic:
  - [ ] map keys sorted recursively `strcmp`,
  - [ ] lists preserve order,
  - [ ] LF-only + final newline,
  - [ ] no timestamps/absolute paths/secrets.
- [ ] Artifact `routes@1` MUST be registered in `docs/ssot/artifacts.md` with owner=`platform/routing` and schema reference `docs/ssot/routing-artifact.md`.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::PATH` (optional; for normalization; never as metric label)
- [ ] Context writes (safe only):
  - [ ] `ContextKeys::PATH_TEMPLATE` (low-cardinality route template only)
- [ ] Reset discipline:
  - [ ] if caching compiled tables in-memory → `ResetInterface` + tag `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `routing.match` (attrs: `method`, `outcome`, `path_template` when matched)
- [ ] Metrics:
  - [ ] `routing.match_total` (labels: `method`, `outcome=matched|not_matched`)
  - [ ] `routing.match_duration_ms` (labels: `method`, `outcome`)
- [ ] Logs:
  - [ ] compile warnings (duplicates/shadowing) as warn; no payloads/PII

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Routing\Exception\RoutesArtifactInvalidException` — errorCode `CORETSIA_ROUTES_ARTIFACT_INVALID`
  - [ ] `Coretsia\Routing\Exception\ReservedRouteViolationException` — errorCode `CORETSIA_ROUTING_RESERVED_ROUTE_FORBIDDEN`
- [ ] Mapping:
  - [ ] reuse existing mapper via tag `error.mapper` OR add routing mapper later if needed (NO DUPES)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw request paths in metrics labels
  - [ ] config values/secrets in CLI output
- [ ] Allowed:
  - [ ] `path_template` as span attr and ContextStore key

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `packages/platform/routing/tests/Integration/CompiledRouterSetsPathTemplateInContextStoreTest.php`
- [ ] If `kernel.reset` used → `N/A` (unless caching added)
- [ ] If metrics/spans/logs exist → `packages/platform/routing/tests/Contract/RoutesCompileIsDeterministicTest.php` + observability assertions where applicable
- [ ] If redaction exists → `packages/platform/routing/tests/Contract/ArtifactBytesRerunNoDiffContractTest.php` (indirect) + CLI output safety assertions in CLI integration test

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `packages/platform/routing/tests/Fixtures/<App>/...` (only if wiring requires boot)
- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions (if used in tests)

### Tests (MUST)

- Unit:
  - [ ] `packages/platform/routing/tests/Unit/RouteMatcherStaticAndParamsTest.php`
  - [ ] `packages/platform/routing/tests/Unit/ReservedRoutesGuardRejectsReservedPathsTest.php`
- Contract:
  - [ ] `packages/platform/routing/tests/Contract/RoutesArtifactHeaderShapeContractTest.php`
  - [ ] `packages/platform/routing/tests/Contract/RoutesCompileIsDeterministicTest.php`
  - [ ] `packages/platform/routing/tests/Contract/ReservedGuardUsesSameMatcherAsHttpContractTest.php`
  - [ ] `packages/platform/routing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `packages/platform/routing/tests/Contract/ArtifactBytesRerunNoDiffContractTest.php`
  - [ ] `packages/platform/routing/tests/Contract/RoutesArtifactHeaderMatchesKernelPolicyContractTest.php`
  - [ ] `packages/platform/routing/tests/Contract/ReservedGuardSemanticsMatchHttpMatcherContractTest.php`
- Integration:
  - [ ] `packages/platform/routing/tests/Integration/RoutesCompileWritesArtifactTest.php`
  - [ ] `packages/platform/routing/tests/Integration/RoutesArtifactRerunNoDiffTest.php`
  - [ ] `packages/platform/routing/tests/Integration/CliRoutesCompileCommandWritesArtifactTest.php`
  - [ ] `packages/platform/routing/tests/Integration/CompiledRouterSetsPathTemplateInContextStoreTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: rerun-no-diff (artifact `routes.php`)
- [ ] Reserved endpoints blocked via Phase SSoT (SystemRouteTable + matcher), no local fork
- [ ] Router writes `ContextKeys::PATH_TEMPLATE` on match (no raw path in metric labels)
- [ ] Docs updated:
  - [ ] `packages/platform/routing/README.md`
  - [ ] `docs/ssot/routing-artifact.md`
  - [ ] `docs/guides/routing.md`
  - [ ] ADR registered
- [ ] Non-goals / out of scope
  - [ ] Не реалізує PSR-15 RouterMiddleware (це owner `platform/http-app`).
  - [ ] Не додає нові reserved endpoints (це owner `platform/http` SystemRouteTable, Phase 0 SSoT).
  - [ ] Не робить “динамічний runtime scan” маршрутів у prod (prod = artifact-only).

---

### 3.140.0 Platform http-app (RouterMiddleware + ActionInvoker) (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.140.0"
owner_path: "packages/platform/http-app/"

package_id: "platform/http-app"
composer: "coretsia/platform-http-app"
kind: runtime
module_id: "platform.http-app"

goal: "App-layer поверх `platform/http`: RouterMiddleware (routing boundary) + ActionInvoker (dispatch handlerId → action) з канонічним 404/405 через error flow."
provides:
- "RouterMiddleware у `http.middleware.app` (prio 100): consumes `RouterInterface`, dispatch-ить handlerId"
- "ActionInvoker + мінімальний ArgumentResolver (без “magic autowire interfaces”, allowlist policy)"
- "404/405 як deterministic exceptions, mapped via `error.mapper` → `ErrorDescriptor` → ProblemDetails (NO ad-hoc rendering)"

tags_introduced: []
config_roots_introduced:
- "http_app"

artifacts_introduced: []
adr: "docs/adr/ADR-XXXX-platform-http-app-router-middleware-action-invoker.md"
ssot_refs:
- "docs/ssot/http-middleware-catalog.md"
---

- [ ] update `3.140.0 Platform http-app` to consume `coretsia/platform-errors` through the shared `ExceptionMapperInterface`/`error.mapper` contract for 404/405 descriptors with empty `[]` tag metadata, reusing Foundation `ReservedTags::ERROR_MAPPER` without a local tag mirror or RFC7807 rendering.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `3.130.0` — `platform/routing` provides `RouterInterface` implementation (expected binding = `CompiledRouter`)
  - Phase 0 HTTP stack model exists in `platform/http` and executes `http.middleware.app` slot.

- Required deliverables (exact paths):
  - `packages/core/foundation/src/Tag/ReservedTags.php` — defines middleware slots incl. `http.middleware.app`
  - `docs/ssot/http-middleware-catalog.md` — canonical placement/priority table
  - `packages/core/kernel/tests/Fixtures/ExpressApp/config/modules.php` — provided by 2.20.0

- Tag usage rule (MUST):
  - for `http.middleware.app`, runtime code MUST use the owner public constant from `platform/http`
    (canonical owner constant in this roadmap set: `Coretsia\Foundation\Tag\ReservedTags::MIDDLEWARE_APP`)
  - for `error.mapper`, `platform/errors` is a forbidden compile-time dependency here,
    so runtime code MUST NOT import `Coretsia\Foundation\Tag\ReservedTags`
  - `error.mapper` MUST follow the non-owner tag usage rule:
    - runtime wiring SHOULD use a package-local internal mirror constant
    - canonical string literal `'error.mapper'` is allowed only as fallback

> This epic introduces no new tags, so it must not create a Tags owner file.

- Required config roots/keys:
  - `http.*` — HTTP pipeline exists and can execute `http.middleware.app` slot

- Handler resolution policy (MUST):
  - `RouteMatch::handlerId` MUST be resolved via config key `http_app.handlers.map`.
  - `ActionInvoker` MUST NOT treat `handlerId` as a class-string, FQCN, or container service id by implicit fallback.
  - This keeps handler wiring explicit, deterministic, and app-owned.

- Required tags:
  - `http.middleware.app` — slot for user middlewares (owner=`platform/http`)
  - `error.mapper` — exception mapper discovery (owner=`platform/errors`; because owner package is a forbidden compile-time dependency here, runtime code SHOULD use a package-local internal mirror constant; the canonical string literal is allowed only as fallback under the non-owner tag usage rule)

- Required contracts / ports:
  - `Psr\Http\Server\MiddlewareInterface` — RouterMiddleware
  - `Psr\Http\Message\ServerRequestInterface` — RouterMiddleware
  - `Psr\Http\Message\ResponseInterface` — RouterMiddleware / response mapping
  - `Coretsia\Contracts\Routing\RouterInterface` — routing boundary
  - `Coretsia\Contracts\Routing\RouteMatch` — route match shape
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface` — 404/405 mapper

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `platform/http`
- `platform/routing`

Forbidden:
- `platform/errors` (only contracts `ExceptionMapperInterface`)
- `platform/problem-details`
- `core/kernel`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Routing\RouterInterface`
  - `Coretsia\Contracts\Routing\RouteMatch`
  - `Coretsia\Contracts\HttpApp\ActionInvokerInterface`
  - `Coretsia\Contracts\HttpApp\ArgumentResolverInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - middleware: `\Coretsia\HttpApp\Middleware\RouterMiddleware::class`
  - middleware slots/tags: `http.middleware.app` priority `100` (meta per owner `platform/http` tag schema, if any)
- Kernel hooks/tags:
  - `error.mapper` priority `800` (meta per owner `platform/errors` tag schema, if any)
- Artifacts:
  - reads (indirect, via `RouterInterface` impl): `packages/applications/skeleton/var/cache/<appId>/routes.php`

### Deliverables (MUST)

#### Creates

Package skeleton:
- [ ] `packages/platform/http-app/composer.json` — package manifest
- [ ] `packages/platform/http-app/src/Module/HttpAppModule.php` — runtime module entry
- [ ] `packages/platform/http-app/src/Provider/HttpAppServiceProvider.php` — DI wiring
- [ ] `packages/platform/http-app/src/Provider/HttpAppServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `packages/platform/http-app/config/http_app.php` — config subtree (no repeated root)
- [ ] `packages/platform/http-app/config/rules.php` — config rules
- [ ] `packages/platform/http-app/README.md` — docs (Observability / Errors / Security-Redaction)
- [ ] `packages/platform/http-app/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

Implementation:
- [ ] `packages/platform/http-app/src/Middleware/RouterMiddleware.php` — routing boundary + dispatch
- [ ] `packages/platform/http-app/src/Action/ActionInvoker.php` — invokes handler; spans/metrics/logs
  - [ ] MUST resolve handler ids only through `http_app.handlers.map`
  - [ ] MUST NOT perform implicit FQCN/service-id fallback
- [ ] `packages/platform/http-app/src/Action/ArgumentResolver/ArgumentResolver.php` — resolves args (no interface autowire)
- [ ] `packages/platform/http-app/src/Action/ArgumentResolver/RouteParamsAccessor.php` — helper
- [ ] `packages/platform/http-app/src/Response/ActionResponseMapper.php` — maps return value → ResponseInterface (json/html)
  - [ ] If `ActionResponseMapper` serializes JSON, it MUST:
    - [ ] use `Coretsia\Foundation\Serialization\StableJsonEncoder` as the single JSON writer
    - [ ] not use tooling `internal-toolkit`
    - [ ] not emit floats/NaN/INF
    - [ ] produce stable bytes (recursive map-key sort `strcmp`, lists preserve order)
    - [ ] MUST NOT use ad-hoc `json_encode` directly in mapper logic

Exceptions + mapping:
- [ ] `packages/platform/http-app/src/Exception/HttpNotFoundException.php` — `CORETSIA_HTTP_404`
- [ ] `packages/platform/http-app/src/Exception/HttpMethodNotAllowedException.php` — `CORETSIA_HTTP_405`
- [ ] `packages/platform/http-app/src/Error/HttpAppProblemMapper.php` — implements `ExceptionMapperInterface`, tag `error.mapper`

Docs:
- [ ] `docs/guides/http-app.md` — how to register handlers + how 404/405 mapping works

Tests:
- [ ] `packages/platform/http-app/tests/Unit/ArgumentResolverAllowlistTest.php`
- [ ] `packages/platform/http-app/tests/Unit/ActionResponseMapperJsonAndHtmlTest.php`
- [ ] `packages/platform/http-app/tests/Integration/RouterMiddlewareDispatchesMatchedHandlerTest.php`
- [ ] `packages/platform/http-app/tests/Integration/NotFoundAndMethodNotAllowedTriggerCanonicalErrorFlowTest.php`
- [ ] `packages/platform/http-app/tests/Integration/ActionInvokerCreatesSpanNoopSafeTest.php`
- [ ] `packages/platform/http-app/tests/Contract/RouterMiddlewareWiringContractTest.php` — locks the `RouterMiddleware` contribution to `http.middleware.app` priority `100` against the canonical middleware catalog
- [ ] `packages/platform/http-app/tests/Integration/NotFound405GoThroughCanonicalErrorFlowTest.php`

Framework / E2E:
- [ ] `tools/tests/Integration/Http/HelloRouteEndToEndTest.php` — routing+http-app+http

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-platform-http-app-router-middleware-action-invoker.md`
- [ ] `docs/ssot/http-middleware-catalog.md` — add/update row for `RouterMiddleware` (`http.middleware.app` priority `100`)
- [ ] `docs/ssot/config-roots.md` — add row:
  - [ ] `http_app | platform/http-app | packages/platform/http-app/config/http_app.php | packages/platform/http-app/config/rules.php | app-layer http routing+invocation settings`
- [ ] `docs/ssot/INDEX.md` — (only if config-roots.md is not already indexed) ensure it remains indexed as canonical SSoT.

#### Package skeleton (if type=package)

- [ ] `packages/platform/http-app/composer.json`
- [ ] `packages/platform/http-app/src/Module/HttpAppModule.php`
- [ ] `packages/platform/http-app/src/Provider/HttpAppServiceProvider.php`
- [ ] `packages/platform/http-app/config/http_app.php`  # returns subtree (no repeated root)
- [ ] `packages/platform/http-app/config/rules.php`
- [ ] `packages/platform/http-app/README.md`
- [ ] `packages/platform/http-app/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/http-app/config/http_app.php`
- [ ] Keys (dot):
  - [ ] `http_app.handlers.map` = []  # handlerId => serviceId
  - [ ] `http_app.arguments.allow_request` = true
  - [ ] `http_app.arguments.allow_route_params` = true
  - [ ] `http_app.arguments.allow_container_types` = []  # list<class-string> allowlist (concrete only)
  - [ ] `http_app.response.json.enabled` = true
  - [ ] `http_app.response.html.enabled` = true
- [ ] Rules:
  - [ ] `packages/platform/http-app/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (uses existing owner tags; this epic MUST NOT create a public `packages/core/foundation/src/Tag/ReservedTags.php` owner file)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\HttpApp\Middleware\RouterMiddleware`
  - [ ] adds tag: `http.middleware.app` priority `100`
  - [ ] because `platform/http` is an allowed compile-time dependency here, runtime wiring MUST use the owner public constant for `http.middleware.app`
  - [ ] registers: `Coretsia\HttpApp\Error\HttpAppProblemMapper`
  - [ ] adds tag: `error.mapper` priority `800`
    - [ ] runtime wiring MUST NOT import `platform/errors`
    - [ ] runtime wiring SHOULD use a package-local internal mirror constant
    - [ ] canonical string literal is allowed only as fallback

#### Artifacts / outputs (if applicable)

N/A (consumes routing artifact via `RouterInterface`)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::PATH_TEMPLATE` (set by routing; used for logs only)
  - [ ] `ContextKeys::HTTP_RESPONSE_FORMAT` (optional; set by negotiation)
- Context writes (safe only):
  - N/A
- Reset discipline:
  - N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `http.action` (attrs: `handler_id`, `outcome`, `status`)
- [ ] Metrics:
  - [ ] `http.action_total` (labels: `method`, `status`, `outcome`)
  - [ ] `http.action_duration_ms` (labels: `method`, `status`, `outcome`)
- [ ] Logs:
  - [ ] handled errors: log code + status + handler_id (no payload)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\HttpApp\Exception\HttpNotFoundException` — errorCode `CORETSIA_HTTP_404`
  - [ ] `Coretsia\HttpApp\Exception\HttpMethodNotAllowedException` — errorCode `CORETSIA_HTTP_405`
- [ ] Mapping:
  - [ ] `HttpAppProblemMapper` via tag `error.mapper` (NO DUPES)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] request headers/cookies/body, tokens, raw payloads
- [ ] Allowed:
  - [ ] handlerId (safe), method/status

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `N/A`
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist → `packages/platform/http-app/tests/Integration/ActionInvokerCreatesSpanNoopSafeTest.php`
- [ ] If redaction exists → `packages/platform/http-app/tests/Integration/NotFound405GoThroughCanonicalErrorFlowTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `packages/core/kernel/tests/Fixtures/ExpressApp/config/modules.php`
- [ ] Fake adapters:
  - [ ] (as needed) `FakeTracer` / `FakeMetrics` / `FakeLogger`

### Tests (MUST)

- Unit:
  - [ ] `packages/platform/http-app/tests/Unit/ArgumentResolverAllowlistTest.php`
  - [ ] `packages/platform/http-app/tests/Unit/ActionResponseMapperJsonAndHtmlTest.php`
- Contract:
  - [ ] `packages/platform/http-app/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `packages/platform/http-app/tests/Contract/RouterMiddlewareWiringContractTest.php`
- Integration:
  - [ ] `packages/platform/http-app/tests/Integration/RouterMiddlewareDispatchesMatchedHandlerTest.php`
  - [ ] `packages/platform/http-app/tests/Integration/NotFoundAndMethodNotAllowedTriggerCanonicalErrorFlowTest.php`
  - [ ] `packages/platform/http-app/tests/Integration/ActionInvokerCreatesSpanNoopSafeTest.php`
  - [ ] `packages/platform/http-app/tests/Integration/NotFound405GoThroughCanonicalErrorFlowTest.php`
- Gates/Arch:
  - [ ] deptrac: ensure `platform/http` does NOT depend on `platform/http-app` (hard rule)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] RouterMiddleware auto-wired into `http.middleware.app` priority 100
- [ ] 404/405 go through canonical error flow (error.mapper)
- [ ] No interface autowire magic in argument resolver
- [ ] Docs updated:
  - [ ] `packages/platform/http-app/README.md`
  - [ ] `docs/guides/http-app.md`
- [ ] `config/http_app.php` returns subtree only (no `['http_app'=>...]` wrapper).
- [ ] runtime reads via `http_app.*` keys from global config.
- [ ] What problem this epic solves:
  - [ ] Дає app-layer поверх `platform/http`: RouterMiddleware (routing boundary) + ActionInvoker (dispatch handlerId → action).
  - [ ] Забезпечує 404/405 через canonical error flow (exceptions → `error.mapper` → `ErrorDescriptor` → ProblemDetails).
  - [ ] Додає мінімальний ArgumentResolver без “magic autowire interfaces” (узгоджено з foundation policy).
- [ ] Non-goals / out of scope:
  - [ ] Не робить compiled routes artifact (це `platform/routing`).
  - [ ] Не робить RFC7807 рендеринг (це `platform/problem-details`).
  - [ ] Не додає auth/session/csrf (це Phase 2).
- [ ] Cutline impact: blocks Phase 3 cutline
- [ ] Invariants reused (NO DUPES) (MUST):
  - [ ] Middleware wiring MUST respect HTTP stack model:
    - [ ] `RouterMiddleware` MUST be in slot `http.middleware.app` priority `100`.
  - [ ] Error flow MUST stay canonical:
    - [ ] 404/405 produced as deterministic exceptions,
    - [ ] mapped via `error.mapper` → `ErrorDescriptor` → ProblemDetails,
    - [ ] NO ad-hoc response rendering in http-app.
  - [ ] Observability MUST stay low-cardinality:
    - [ ] use `ContextKeys::PATH_TEMPLATE` if present; never label raw path.

---

### 3.143.0 Target-aware skeleton HTTP front controllers + deterministic smoke (MUST) [IMPL] [REQUIRES REDESIGN]

---
type: skeleton
phase: 3
epic_id: "3.143.0"
owner_path: "packages/applications/skeleton/apps/"

goal: "Надати стабільні target-aware HTTP front controllers для skeleton web/api та deterministic `composer serve` / `composer smoke:http` flow до появи platform/http; front-controller paths залишаються незмінними, а тимчасовий 503 fallback пізніше замінюється реальним HTTP runtime bootstrap."
provides:
- "Real stable HTTP entrypoints: `packages/applications/skeleton/apps/web/public/index.php` and `packages/applications/skeleton/apps/api/public/index.php`."
- "Explicit canonical target ownership: each front controller hardcodes exactly one target, `web` or `api`."
- "Shared non-public HTTP bootstrap seam that currently emits deterministic 503 and is replaced internally when platform/http becomes available."
- "Target-aware dev server command using `--target=web|api`."
- "Pure-PHP socket-based HTTP smoke checker with byte-exact body verification and no cURL dependency."
- "Deterministic smoke orchestration: start one server, wait for readiness, execute one checker, and terminate the server."
- "No request reflection, superglobal capture, request headers, cookies, request bodies, paths, env values, or process diagnostics in public output."
- "Repo-root Composer entrypoints with CWD-independent script behavior."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/architecture/PACKAGING.md"
- "docs/ssot/modes.md"
---

> **REQUIRES REDESIGN BEFORE IMPLEMENTATION (MUST)**
>
> Before implementation, redesign its scope against the current architecture, where `coretsia/framework` and `coretsia/skeleton` are independent Composer packages.
>
> Reassess HTTP front-controller ownership, application bootstrap, consumer-facing `serve` and `smoke:http` commands, repository tooling boundaries, and integration with the real `platform/http` runtime.
>
> The primary execution target MUST be an installed consumer application created through `composer create-project coretsia/skeleton`, not the framework monorepo itself.
>
> Review this epic together with 3.145.0 to eliminate duplicated responsibilities, obsolete paths, temporary bootstrap assumptions, and unnecessary tooling.
>
> All existing deliverables, implementation details, prerequisites, and acceptance criteria are provisional until the redesign is completed.

### Dependencies (MUST)

#### Preconditions (MUST)

- Required deliverables:
  - `packages/applications/skeleton/apps/web/public/` exists
  - `packages/applications/skeleton/apps/api/public/` exists
  - repo-root `composer.json` exists

- Existing packaging enforcement retained unchanged:
  - `tools/gates/no_skeleton_http_default_gate.php`

- Gate boundary:
  - the gate forbids only repo-root `packages/applications/skeleton/config/http.php`
  - application front controllers under `packages/applications/skeleton/apps/<http-app>/public/` are not HTTP config defaults
  - this epic MUST NOT add an allowlist or exception to the gate

- New tooling boundary:
  - `tools/http/serve` does not exist before this epic and is created here
  - `tools/http/smoke` does not exist before this epic and is created here
  - `tools/http/smoke-http` does not exist before this epic and is created here
  - source files imported from another project are implementation inputs only
  - imported implementations MUST be adapted completely to this epic and MUST NOT define compatibility requirements

- Canonical target tokens already exist:
  - `web`
  - `api`
  - `console`
  - `worker`

- Scope boundary:
  - this epic implements HTTP entrypoints only for `web|api`
  - `console` remains owned by platform/cli
  - `worker` remains owned by the worker runtime

### Entry points / integration points (MUST)

- Repo-root Composer scripts:
  - `composer serve`
    - delegates to `@php tools/http/serve`
    - accepts script arguments after `--`
    - canonical target option: `--target=web|api`
  - `composer smoke:http`
    - delegates to `@php tools/http/smoke http`
    - accepts script arguments after `--`
    - canonical target option: `--target=web|api`

- Target ownership:
  - `serve --target=web` selects `packages/applications/skeleton/apps/web/public`
  - `serve --target=api` selects `packages/applications/skeleton/apps/api/public`
  - `smoke http --target=<target>` starts and verifies the same target
  - target selection changes only the selected skeleton docroot
  - the public front controller independently declares the same canonical target
  - no command-line value is passed into the front controller as runtime target state

- Unsupported targets:
  - `console|worker` fail deterministically for `serve`
  - `console|worker` fail deterministically for `smoke http`
  - no HTTP entrypoint is created for those targets by this epic

### Deterministic CLI contract (MUST)

The following rules apply to `tools/http/serve`, `tools/http/smoke`, and `tools/http/smoke-http`:

- long options use only `--name=value` form
- boolean flags use only `--name`
- split forms such as `--port 8080` are rejected
- unknown options are rejected
- duplicate options are rejected
- missing option values are rejected
- empty option values are rejected
- option names are ASCII case-sensitive
- positional arguments are forbidden except the exact `http` command accepted by `tools/http/smoke`
- exact command layouts are:
  - `tools/http/serve [options]`
  - `tools/http/smoke http [options]`
  - `tools/http/smoke-http [options]`
- for `tools/http/smoke`, `http` must be the first argument after the script path
- options may appear in any order after the command token
- options before `http` are rejected
- additional positional arguments are rejected
- no option value is read from env or config

Host model:

- accepted `--host` values are exactly:
  - `localhost`
  - `127.0.0.1`
  - `0.0.0.0`
- every other hostname or IPv4 literal is rejected
- IPv6 host syntax is out of scope for this epic
- whitespace, control bytes, URI syntax, ports, paths, and user-info are forbidden inside `--host`

Canonical host derivation:

- requested `localhost`:
  - bind host = `127.0.0.1`
  - connect host = `127.0.0.1`
- requested `127.0.0.1`:
  - bind host = `127.0.0.1`
  - connect host = `127.0.0.1`
- requested `0.0.0.0`:
  - bind host = `0.0.0.0`
  - connect host = `127.0.0.1`

Usage:

- `serve` binds the PHP built-in server to the canonical bind host
- `serve` readiness probes use the canonical connect host
- `smoke` passes the canonical bind host to `serve`
- `smoke` readiness and cleanup probes use the canonical connect host
- `smoke` passes the canonical connect host to `smoke-http`
- `smoke-http` always connects through the canonical connect host
- `smoke-http` uses the canonical connect host in the HTTP `Host` field
- `localhost` is never passed to sockets or the PHP built-in server after canonicalization

Port validation:

- ASCII decimal digits only
- range `1..65535`
- no sign, whitespace, decimal point, exponent, or leading/trailing data

Timeout validation:

- ASCII decimal digits only
- range `1..60`
- default is exactly `5` seconds
- applies to `tools/http/smoke` readiness and cleanup waits
- applies to `tools/http/smoke-http` connect and response reads
- timeout measurement uses monotonic `hrtime(true)`
- wall-clock time, timezone, and system date do not participate
- each bounded phase receives one total deadline
- reading a partial chunk does not reset or extend a deadline
- `smoke` readiness polling interval is exactly `50` milliseconds
- `smoke` cleanup-port polling interval is exactly `50` milliseconds

Process result:

- successful finite commands exit with status `0`
- deterministic command failure exits with status `1`
- successful `serve` remains active until externally terminated
- every explicit command failure writes exactly one JSON line to stderr:
  - `{"schema":1,"code":"<CODE>"}\n`
- no command writes a PHP warning, stack trace, previous exception message, usage dump, or child-process output

Process execution and ephemeral IPC contract:

- platform null sink is:
  - `NUL` when `PHP_OS_FAMILY === 'Windows'`
  - `/dev/null` on every other supported OS
- every `proc_open` command uses an argument vector
- no child process inherits parent terminal streams implicitly
- stdout/stderr pipes are not used for readiness, control, or child diagnostics
- temporary IPC paths and contents never appear in stdout, stderr, HTTP output, or exceptions

`proc_open` options:

- on Windows:
  - `bypass_shell = true`
  - `suppress_errors = true`
- on every other supported OS:
  - no platform-specific process option is required
- on Windows, every `proc_open` call explicitly uses `bypass_shell = true`
- command arrays MUST NOT rely on implicit `cmd.exe` behavior

Bounded process termination:

- every termination deadline uses monotonic `hrtime(true)`
- process status is polled every `50` milliseconds
- graceful termination phase:
  - requests normal process termination
  - waits for at most the selected timeout
- hard termination phase runs only if the process remains active:
  - on POSIX, requests signal `9`
  - on Windows, repeats `proc_terminate` using native process termination
  - waits for at most one additional selected-timeout interval
- no process wait resets or extends its current deadline
- `proc_close` is called only after the process is observed inactive
- a process that remains active after the hard-termination deadline is a deterministic cleanup failure

`smoke` ephemeral control directory:

- before starting `serve`, `smoke` creates one private directory under `sys_get_temp_dir()`
- directory basename is exactly:
  - `coretsia-http-smoke-` followed by `bin2hex(random_bytes(16))`
- the generated suffix is internal collision-avoidance state only and does not participate in observable output
- directory creation uses exclusive semantics
- directory permissions are `0700` where the platform supports Unix permission modes
- an existing path, symlink, or failed directory creation maps to `CORETSIA_SMOKE_INTERNAL_FAILURE`
- the directory contains only:
  - `ready`
  - `ready.tmp`
  - `stop`
  - `stop.tmp`
- these files are ephemeral runtime IPC files, not generated artifacts
- `smoke` owns creation and final removal of the directory
- failure to remove owned IPC files or the directory after process startup maps to `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`
- failure to remove the owned control directory before any child process was successfully started maps to `CORETSIA_SMOKE_INTERNAL_FAILURE`
- control-directory cleanup is idempotent; repeated invocation performs no duplicate output and does not change an already selected failure unless cleanup itself fails

Atomic IPC file publication:

- `ready.tmp` and `stop.tmp` are created with exclusive-create semantics
- publication sequence is exact:
  1. confirm that both temporary and final destination paths are absent
  2. open the temporary path as a new regular file
  3. write all expected bytes using a complete-write loop
  4. flush the stream
  5. close the stream
  6. atomically rename the temporary file to its final filename in the same directory
- partial writes are never accepted
- rename never replaces an existing destination
- temporary and final paths must remain regular non-symlink paths
- readiness publication failure maps inside `serve` to `CORETSIA_SERVE_INTERNAL_FAILURE`
- stop publication failure inside `smoke` skips orderly stop and proceeds to bounded hard termination
- temporary IPC bytes are never read until publication rename has completed

Readiness IPC:

- `serve` receives the control directory through internal option `--control-dir=<absolute-path>`
- when no `--control-dir` is provided, direct interactive `serve` behavior remains unchanged
- when `--control-dir` is provided:
  - `--quiet` is required
  - the directory must already exist
  - the directory must not be a symlink
  - `ready`, `ready.tmp`, `stop`, and `stop.tmp` must initially be absent
- after built-in-server TCP readiness, `serve` publishes `ready` through the exact atomic IPC file-publication contract
- readiness bytes are exactly:
  - `CORETSIA_SERVE_READY target=<target> bind=<bind-host>:<port> url=http://<connect-host>:<port>\n`
- readiness is established only when:
  - `ready` exists
  - `ready` is a regular file
  - its complete contents equal the expected readiness bytes
  - the serve wrapper is still active
  - the post-readiness TCP probe succeeds
- malformed, oversized, unexpected, or symlinked readiness state maps to `CORETSIA_SMOKE_SERVER_START_FAILED`

Stop IPC:

- cleanup publishes `stop` with exact bytes `CORETSIA_SERVE_STOP\n` through the atomic IPC file-publication contract
- while active, `serve` polls for `stop` every `50` milliseconds
- `serve` accepts stop only when:
  - `stop` is a regular file
  - its contents equal `CORETSIA_SERVE_STOP\n` byte-for-byte
- valid stop state triggers orderly built-in-server termination and serve-wrapper exit status `0`
- malformed or symlinked stop state maps to `CORETSIA_SERVE_INTERNAL_FAILURE`
- EOF and stdin bytes have no serve-control semantics

Child descriptors:

- `serve` → PHP built-in server:
  - stdin = platform null sink
  - stdout = platform null sink
  - stderr = platform null sink
- `smoke` → `serve`:
  - stdin = platform null sink
  - stdout = platform null sink
  - stderr = platform null sink
- `smoke` → `smoke-http`:
  - stdin = platform null sink
  - stdout = platform null sink
  - stderr = platform null sink

Unexpected failure containment:

- each CLI script has one top-level failure boundary
- expected validation, process, socket, and response failures map to their documented codes
- every other caught `Throwable` maps to a script-local internal failure code:
  - `tools/http/serve` → `CORETSIA_SERVE_INTERNAL_FAILURE`
  - `tools/http/smoke` → `CORETSIA_SMOKE_INTERNAL_FAILURE`
  - `tools/http/smoke-http` → `CORETSIA_SMOKE_HTTP_INTERNAL_FAILURE`
- previous Throwable objects are not retained after mapping
- Throwable messages, classes, traces, files, and line numbers are never copied into output
- PHP warnings produced by filesystem, process, stream, or socket operations are suppressed or converted inside the top-level failure boundary
- any temporary error handler is restored before command termination

### Skeleton HTTP packaging boundary (MUST)

- HTTP-facing skeleton apps MAY ship their canonical executable front controller:
  - `packages/applications/skeleton/apps/web/public/index.php`
  - `packages/applications/skeleton/apps/api/public/index.php`

- A front controller:
  - is an application entrypoint
  - is not a config root
  - MUST NOT own module selection
  - MUST NOT own runtime-driver selection
  - MUST NOT embed environment-specific HTTP configuration

- Default HTTP config remains forbidden:
  - `packages/applications/skeleton/config/http.php` MUST remain absent
  - the existing `no_skeleton_http_default_gate.php` remains unchanged
  - no path-specific allowlist is introduced

### Deliverables (MUST)

#### Creates

- [ ] Before implementation, re-review this epic against the current runtime-driver ownership boundary: `RuntimeDriverResolver` remains Kernel matrix-only; owner packages/adapters own their package/module prerequisites, adapter/transport/executable readiness, and `RuntimeDriverContributions` carry selected canonical drivers only.

- [ ] `skeleton/bootstrap/HttpFrontController.php`
  - [ ] declares final class `Coretsia\Skeleton\Bootstrap\HttpFrontController`
  - [ ] file is outside every public docroot
  - [ ] requiring the file only declares the class
  - [ ] requiring the file emits no output, headers, status, warnings, or side effects
  - [ ] exact typed public constant:
    - [ ] `public const string BOOT_NOT_READY_BODY`
    - [ ] exact value: `{"schema":1,"code":"CORETSIA_HTTP_BOOT_NOT_READY","message":"boot not ready"}\n`
  - [ ] public static entrypoint:
    - [ ] `public static function run(string $target): void`
  - [ ] accepts exactly:
    - [ ] `web`
    - [ ] `api`
  - [ ] any other target throws fixed `LogicException('http-front-controller-target-invalid')`
  - [ ] target validation occurs before any status, header, or output side effect
  - [ ] current Phase-2 behavior:
    - [ ] removes `X-Powered-By`
    - [ ] sets status `503`
    - [ ] sets `Content-Type: application/json; charset=utf-8`
    - [ ] sets `Cache-Control: no-store`
    - [ ] sets `Content-Length` from exact body bytes
    - [ ] outputs `BOOT_NOT_READY_BODY` exactly once
  - [ ] body is ASCII-only, LF-only, and ends with exactly one LF
  - [ ] performs no JSON encoding at request time
  - [ ] reads no superglobals
  - [ ] reads no config or env
  - [ ] requires no vendor autoloader or HTTP runtime
  - [ ] performs no request reflection
  - [ ] contains no timestamp, path, host, port, target, header, cookie, query, body, process, or exception data in output
  - [ ] is the stable bootstrap seam whose internal fallback is replaced by platform/http in Phase 3

- [ ] `packages/applications/skeleton/apps/web/public/index.php`
  - [ ] real stable web front controller
  - [ ] CWD-independent require of `skeleton/bootstrap/HttpFrontController.php`
  - [ ] calls exactly:
    - [ ] `HttpFrontController::run('web')`
  - [ ] contains no target inference
  - [ ] reads no superglobals
  - [ ] contains no fallback response implementation of its own
  - [ ] contains no executable code after the `run()` call

- [ ] `packages/applications/skeleton/apps/api/public/index.php`
  - [ ] real stable API front controller
  - [ ] CWD-independent require of `skeleton/bootstrap/HttpFrontController.php`
  - [ ] calls exactly:
    - [ ] `HttpFrontController::run('api')`
  - [ ] contains no target inference
  - [ ] reads no superglobals
  - [ ] contains no fallback response implementation of its own
  - [ ] contains no executable code after the `run()` call

- [ ] `tools/http/serve`
  - [ ] new pure-PHP executable script
  - [ ] strict argument parser
  - [ ] CWD-independent repo-root discovery through `__DIR__`
  - [ ] accepts exactly:
    - [ ] `--target=<target>`
    - [ ] `--host=<host>`
    - [ ] `--port=<port>`
    - [ ] `--quiet`
    - [ ] `--control-dir=<absolute-path>` as an orchestration-only IPC option
  - [ ] defaults:
    - [ ] `target = web`
    - [ ] `host = 127.0.0.1`
    - [ ] `port = 8080`
    - [ ] `quiet = false`
    - [ ] `control-dir = null`
  - [ ] `--control-dir` rules:
    - [ ] path must be absolute
    - [ ] path must already exist as a directory
    - [ ] path must not be a symlink
    - [ ] path must contain none of the reserved IPC files before startup
    - [ ] option is valid only together with `--quiet`
    - [ ] invalid control-directory state maps to `CORETSIA_SERVE_INVALID_ARGUMENT`
    - [ ] rejected path is never included in failure output
  - [ ] MUST NOT accept:
    - [ ] `--app`
    - [ ] `--docroot`
    - [ ] `--router`
    - [ ] env overrides
    - [ ] arbitrary PHP binary
    - [ ] arbitrary child command
  - [ ] target mapping is exact:
    - [ ] `web` → `packages/applications/skeleton/apps/web/public`
    - [ ] `api` → `packages/applications/skeleton/apps/api/public`
  - [ ] target validation occurs after syntactic argument parsing:
    - [ ] `web|api` are accepted HTTP targets
    - [ ] `console|worker` fail with `CORETSIA_SERVE_TARGET_NOT_HTTP`
    - [ ] every other target fails with `CORETSIA_SERVE_TARGET_INVALID`
  - [ ] target comparison is exact ASCII and case-sensitive
  - [ ] router is always `<resolved-docroot>/index.php`
  - [ ] missing docroot fails with `CORETSIA_SERVE_DOCROOT_MISSING`
  - [ ] missing front controller fails with `CORETSIA_SERVE_FRONT_CONTROLLER_MISSING`
  - [ ] invalid arguments fail with `CORETSIA_SERVE_INVALID_ARGUMENT`
  - [ ] occupied port fails with `CORETSIA_SERVE_PORT_UNAVAILABLE`
  - [ ] port availability is checked before `proc_open` through a temporary bind to the canonical bind host and selected port
  - [ ] the temporary socket is closed before the PHP built-in server is started
  - [ ] failure of the preflight bind maps only to `CORETSIA_SERVE_PORT_UNAVAILABLE`
  - [ ] readiness polling connects through the canonical connect host
  - [ ] child startup failure fails with `CORETSIA_SERVE_START_FAILED`
  - [ ] unexpected post-readiness child exit fails with `CORETSIA_SERVE_CHILD_EXITED`
  - [ ] every other unexpected failure fails with `CORETSIA_SERVE_INTERNAL_FAILURE`
  - [ ] starts the PHP built-in server through `proc_open`
  - [ ] after `proc_open`, polls both:
    - [ ] child process status
    - [ ] TCP reachability of the canonical connect host and selected port
  - [ ] fixed startup timeout is exactly `5` seconds
  - [ ] readiness polling uses a fixed sleep interval of `50` milliseconds
  - [ ] child exit before TCP readiness maps to `CORETSIA_SERVE_START_FAILED`
  - [ ] startup timeout maps to `CORETSIA_SERVE_START_FAILED`
  - [ ] partial readiness diagnostics are never emitted
  - [ ] the readiness line is emitted only after a TCP connection succeeds
  - [ ] command is an argument vector using exactly:
    - [ ] `PHP_BINARY`
    - [ ] `-n`
    - [ ] `-d`
    - [ ] `expose_php=0`
    - [ ] `-d`
    - [ ] `display_errors=0`
    - [ ] `-d`
    - [ ] `html_errors=0`
    - [ ] `-d`
    - [ ] `log_errors=0`
    - [ ] `-S`
    - [ ] `<bind-host>:<port>`
    - [ ] `-t`
    - [ ] resolved target docroot
    - [ ] resolved target `index.php`
  - [ ] `-n` prevents ambient `php.ini`, scanned INI files, `auto_prepend_file`, and `auto_append_file` from changing the stub server
  - [ ] built-in-server child environment is inherited only after removing every environment key whose ASCII case-insensitive name equals `PHP_CLI_SERVER_WORKERS`
  - [ ] environment values are not used to select target, host, port, docroot, router, response, or process count
  - [ ] the built-in server always remains a single owned child process
  - [ ] MUST NOT use shell interpolation or a command string
  - [ ] child working directory is the repo root
  - [ ] child stdin/stdout/stderr use the exact platform-null descriptor contract
  - [ ] retains the child process handle
  - [ ] registers shutdown cleanup
  - [ ] forwards/handles supported termination signals where available
  - [ ] terminates and closes the child on shutdown
  - [ ] does not create a server log
  - [ ] does not include child output in diagnostics
  - [ ] normal mode without `--control-dir` prints exactly these readiness bytes:
    - [ ] `CORETSIA_SERVE_READY target=<target> bind=<bind-host>:<port> url=http://<connect-host>:<port>\n`
  - [ ] `--quiet` suppresses readiness stdout
  - [ ] when `--control-dir` is present, readiness is always published through the exact atomic ready-file protocol
  - [ ] `--quiet` does not suppress ready-file publication
  - [ ] `--quiet` suppresses only the readiness line; it does not suppress deterministic failure output
  - [ ] after readiness, the parent remains alive while the child server is running
  - [ ] while active, the parent polls:
    - [ ] built-in-server child status
    - [ ] the optional control-directory stop file
  - [ ] when no control directory exists, stdin has no command semantics
  - [ ] when a control directory exists, only the exact atomic stop-file protocol requests orderly shutdown
  - [ ] valid stop state:
    - [ ] is never written to stdout or stderr
    - [ ] triggers termination and closure of the built-in-server child
    - [ ] causes the serve wrapper to exit with status `0`
    - [ ] produces no failure payload
  - [ ] built-in-server child shutdown uses the bounded process-termination contract with timeout `5`
  - [ ] failure to terminate the built-in-server child after the hard-termination phase:
    - [ ] emits `CORETSIA_SERVE_INTERNAL_FAILURE`
    - [ ] exits with status `1`
  - [ ] unexpected child termination after readiness:
    - [ ] emits `{"schema":1,"code":"CORETSIA_SERVE_CHILD_EXITED"}\n` to stderr
    - [ ] exits with status `1`
  - [ ] orderly shutdown requested through the valid atomic stop-file protocol or a supported parent termination signal exits without failure output
  - [ ] failure output is one JSON line on stderr:
    - [ ] `{"schema":1,"code":"<CODE>"}\n`
  - [ ] failure output contains no host, port, path, process id, command, exception message, or child output

- [ ] `tools/http/smoke`
  - [ ] pure-PHP deterministic smoke orchestrator
  - [ ] accepts exactly:
    - [ ] positional command `http`
    - [ ] `--target=<target>`
    - [ ] `--host=<host>`
    - [ ] `--port=<port>`
    - [ ] `--timeout=<seconds>`
  - [ ] defaults:
    - [ ] `target = web`
    - [ ] `host = 127.0.0.1`
    - [ ] `port = 8080`
    - [ ] `timeout = 5`
  - [ ] target validation occurs after syntactic argument parsing:
    - [ ] `web|api` are accepted HTTP targets
    - [ ] `console|worker` fail with `CORETSIA_SMOKE_TARGET_NOT_HTTP`
    - [ ] every other target fails with `CORETSIA_SMOKE_TARGET_INVALID`
  - [ ] target comparison is exact ASCII and case-sensitive
  - [ ] rejects `all|cli|db|config` and unknown commands deterministically
  - [ ] creates one private ephemeral control directory before process startup
  - [ ] immediately after successful directory creation, registers one idempotent cleanup boundary
  - [ ] the cleanup boundary is valid before any child process exists
  - [ ] every path after control-directory creation executes that cleanup boundary
  - [ ] starts exactly one serve child through an argument-vector `proc_open` command:
    - [ ] `PHP_BINARY`
    - [ ] repo-root `tools/http/serve`
    - [ ] `--target=<target>`
    - [ ] `--host=<bind-host>`
    - [ ] `--port=<port>`
    - [ ] `--quiet`
    - [ ] `--control-dir=<absolute-control-directory>`
  - [ ] MUST NOT invoke the serve child through Composer
  - [ ] serve-child stdin/stdout/stderr use the platform null sink
  - [ ] expected readiness bytes are constructed exactly as:
    - [ ] `CORETSIA_SERVE_READY target=<target> bind=<bind-host>:<port> url=http://<connect-host>:<port>\n`
  - [ ] readiness polling checks:
    - [ ] serve-wrapper process status
    - [ ] existence and type of the `ready` file
    - [ ] exact byte contents of the `ready` file
  - [ ] readiness-file polling uses a fixed `50`-millisecond interval
  - [ ] readiness-file reading has a fixed `512`-byte budget
  - [ ] child exit before valid readiness maps to `CORETSIA_SMOKE_SERVER_START_FAILED`
  - [ ] malformed, truncated, unexpected, oversized, or symlinked readiness state maps to `CORETSIA_SMOKE_SERVER_START_FAILED`
  - [ ] after exact readiness-file validation, one TCP probe must succeed against the canonical connect host and selected port
  - [ ] failure of the post-readiness TCP probe maps to `CORETSIA_SMOKE_SERVER_START_FAILED`
  - [ ] readiness timeout maps to `CORETSIA_SMOKE_SERVER_NOT_READY`
  - [ ] TCP reachability without the exact owned readiness file never establishes server ownership
  - [ ] executes exactly one checker child through an argument-vector `proc_open` command:
    - [ ] `PHP_BINARY`
    - [ ] repo-root `tools/http/smoke-http`
    - [ ] `--host=<connect-host>`
    - [ ] `--port=<port>`
    - [ ] `--timeout=<timeout>`
  - [ ] checker stdin/stdout/stderr use the exact platform-null descriptor contract
  - [ ] no checker output pipe is created
  - [ ] checker exit status `0` means smoke success
  - [ ] any non-zero checker exit status maps to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] checker `proc_open` failure maps to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] checker child has a parent-enforced total process deadline of exactly:
    - [ ] `(2 * timeout) + 2` seconds
  - [ ] the deadline uses monotonic `hrtime(true)`
  - [ ] checker process status is polled every `50` milliseconds
  - [ ] checker timeout:
    - [ ] calls `proc_terminate`
    - [ ] waits for at most one additional `timeout` interval
    - [ ] maps to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] checker termination uses the bounded process-termination contract
  - [ ] checker remaining active after hard termination still maps to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] a checker that remains active after bounded hard termination:
    - [ ] prevents successful smoke completion
    - [ ] does not prevent serve-wrapper cleanup from executing
    - [ ] maps the pending smoke result to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] checker termination failure never bypasses serve-wrapper cleanup
  - [ ] checker process handle is closed only after the checker is inactive
  - [ ] checker timeout does not bypass serve cleanup
  - [ ] serve-wrapper `proc_open` failure maps to `CORETSIA_SMOKE_SERVER_START_FAILED`
  - [ ] post-readiness serve-wrapper exit before checker completion maps to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] retains the serve-process handle
  - [ ] immediately after successful `proc_open`, attaches the serve-process handle to the already-registered cleanup boundary
  - [ ] serve-wrapper `proc_open` failure removes the owned control directory before emitting `CORETSIA_SMOKE_SERVER_START_FAILED`
  - [ ] cleanup executes for every post-spawn path:
    - [ ] readiness success
    - [ ] readiness timeout
    - [ ] serve-wrapper early exit
    - [ ] checker startup failure
    - [ ] checker failure
    - [ ] successful checker completion
    - [ ] unexpected caught failure
  - [ ] cleanup publishes `stop` with exact bytes `CORETSIA_SERVE_STOP\n` through the shared atomic IPC file-publication contract
  - [ ] if `stop.tmp` or `stop` already exists, or atomic publication otherwise fails:
    - [ ] orderly stop is skipped
    - [ ] bounded process termination begins
    - [ ] the publication failure itself is not emitted separately
  - [ ] cleanup waits up to the selected timeout for orderly wrapper termination
  - [ ] if the wrapper remains active, cleanup applies the bounded process-termination contract
  - [ ] failure to make the wrapper inactive after hard termination maps to `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`
  - [ ] no process output pipes exist
  - [ ] cleanup closes every process handle only after the corresponding process is inactive
  - [ ] cleanup captures the final serve-wrapper exit status before closing its process handle
  - [ ] after an established readiness handshake, serve-wrapper exit status must be exactly `0`
  - [ ] a non-zero serve-wrapper exit during cleanup maps to `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`
  - [ ] cleanup removes `ready`, `ready.tmp`, `stop`, and `stop.tmp`
  - [ ] cleanup removes the owned control directory
  - [ ] port-release verification is performed only when the exact readiness handshake was previously completed
  - [ ] when readiness was never established:
    - [ ] a reachable port is treated as externally owned
    - [ ] cleanup does not wait for that port to close
    - [ ] cleanup does not classify that external listener as an orphan
  - [ ] when readiness was established:
    - [ ] cleanup polls the canonical connect host and selected port for at most the selected timeout
    - [ ] the port must become unreachable
  - [ ] cleanup never waits without a fixed deadline
  - [ ] server cleanup failure occurs when any of the following is true:
    - [ ] the serve wrapper remains active after bounded termination attempts
    - [ ] an owned, readiness-confirmed port remains reachable
    - [ ] after an established readiness handshake, the serve wrapper exits with a non-zero status
    - [ ] any owned `ready`, `ready.tmp`, `stop`, or `stop.tmp` path cannot be removed
    - [ ] the owned control directory cannot be removed
  - [ ] server cleanup failure maps to `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`
  - [ ] cleanup failure takes precedence over every pending success or failure result
  - [ ] when cleanup succeeds, the previously selected success or failure result is preserved
  - [ ] smoke writes its final failure payload only after cleanup has completed
  - [ ] successful smoke completion requires:
    - [ ] the exact readiness handshake was completed
    - [ ] checker exited with status `0`
    - [ ] serve wrapper is no longer active
    - [ ] serve wrapper exited with status `0`
    - [ ] the readiness-confirmed port is no longer reachable
    - [ ] every owned IPC file and the control directory were removed
  - [ ] silent on success
  - [ ] deterministic failure codes:
    - [ ] `CORETSIA_SMOKE_INVALID_ARGUMENT`
    - [ ] `CORETSIA_SMOKE_TARGET_NOT_HTTP`
    - [ ] `CORETSIA_SMOKE_TARGET_INVALID`
    - [ ] `CORETSIA_SMOKE_SERVER_START_FAILED`
    - [ ] `CORETSIA_SMOKE_SERVER_NOT_READY`
    - [ ] `CORETSIA_SMOKE_HTTP_FAILED`
    - [ ] `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`
    - [ ] `CORETSIA_SMOKE_INTERNAL_FAILURE`
  - [ ] failure output is exactly one JSON line on stderr:
    - [ ] `{"schema":1,"code":"<CODE>"}\n`
  - [ ] child stdout/stderr and child failure payloads are not forwarded
  - [ ] no profiles, env overrides, cURL, database checks, CLI checks, JUnit, GitHub annotations, colors, progress output, or dynamic logs remain

- [ ] `tools/http/smoke-http`
  - [ ] pure-PHP checker only
  - [ ] MUST NOT start, stop, or manage a server
  - [ ] MUST NOT call `proc_open`
  - [ ] MUST NOT use ext-curl, cURL, HTTP clients, or external executables
  - [ ] CWD-independent repo-root discovery through `__DIR__`
  - [ ] loads `skeleton/bootstrap/HttpFrontController.php`
  - [ ] reads only `HttpFrontController::BOOT_NOT_READY_BODY`
  - [ ] MUST NOT call `HttpFrontController::run()`
  - [ ] accepts exactly:
    - [ ] `--host=<host>`
    - [ ] `--port=<port>`
    - [ ] `--timeout=<seconds>`
  - [ ] defaults:
    - [ ] host `127.0.0.1`
    - [ ] port `8080`
    - [ ] timeout `5`
  - [ ] request path is fixed to `/`
  - [ ] `--url`, `--path`, query, fragment, user-info, scheme selection, and arbitrary endpoint selection are unsupported
  - [ ] opens connection through `stream_socket_client`
  - [ ] connection establishment has one total `<timeout>`-second deadline
  - [ ] connection refusal, DNS-free connect failure, or connect timeout maps to `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
  - [ ] request write failure or incomplete request write maps to `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
  - [ ] after a successful request write, response reading has a new total `<timeout>`-second deadline
  - [ ] response-read timeout maps to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] socket warnings and platform diagnostics are never emitted
  - [ ] writes exact request:
    - [ ] `GET / HTTP/1.1`
    - [ ] deterministic `Host`
    - [ ] `Connection: close`
    - [ ] empty body
  - [ ] exact request bytes terminate with `\r\n\r\n`
  - [ ] `Host` is emitted exactly as `<connect-host>:<port>`
  - [ ] the original requested token `localhost` or `0.0.0.0` never appears in request bytes after canonicalization
  - [ ] no additional request headers are emitted
  - [ ] reads response with a fixed total response budget of `65536` bytes
  - [ ] status line plus complete header section, including terminating `\r\n\r\n`, has a separate maximum of `16384` bytes
  - [ ] exceeding either the header-section budget or total response budget maps to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] EOF before a complete HTTP header section maps to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] the header section must end with exact `\r\n\r\n`
  - [ ] status line grammar is exactly:
    - [ ] `HTTP/1.1 SP <three ASCII decimal digits> [SP <reason phrase>] CRLF`
  - [ ] reason phrase:
    - [ ] may be empty
    - [ ] otherwise contains only ASCII HTAB, SP, and visible bytes `0x21..0x7E`
  - [ ] status line contains no NUL, ESC, bare CR, bare LF, or another C0 control byte
  - [ ] malformed version, separators, status digits, or reason phrase maps to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] malformed status or header lines map to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] obsolete folded header lines are rejected
  - [ ] each header line grammar is exactly:
    - [ ] `<field-name>:<optional-field-value>\r\n`
  - [ ] `field-name` is immediately followed by `:`
  - [ ] whitespace before `:` is forbidden
  - [ ] optional leading and trailing field-value whitespace consists only of ASCII SP or HTAB
  - [ ] an empty field value is syntactically valid unless the specific required-header contract rejects it
  - [ ] a header line without `:` maps to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] header names must be non-empty ASCII HTTP token values
  - [ ] header values containing CR, LF, NUL, ESC, or another C0 control byte other than horizontal tab are rejected
  - [ ] required singleton headers occur exactly once:
    - [ ] `Content-Type`
    - [ ] `Cache-Control`
    - [ ] `Content-Length`
  - [ ] missing or duplicate required singleton headers map to `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
  - [ ] forbidden headers occur zero times:
    - [ ] `Set-Cookie`
    - [ ] `Location`
    - [ ] `X-Powered-By`
    - [ ] `Transfer-Encoding`
  - [ ] any forbidden-header occurrence maps to `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
  - [ ] `Content-Length`:
    - [ ] contains ASCII decimal digits only
    - [ ] contains no sign, whitespace, comma, decimal point, or exponent
    - [ ] is parsed without integer overflow
    - [ ] does not exceed `65536 - <status-and-header-section-byte-length>`
    - [ ] the subtraction is checked before use and cannot underflow
    - [ ] response reading never allocates a buffer based solely on the received `Content-Length`
  - [ ] body length must equal the parsed `Content-Length`
  - [ ] trailing bytes beyond `Content-Length` map to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] parses status line and headers deterministically
  - [ ] header names are compared ASCII case-insensitively
  - [ ] header values are compared byte-exactly after removing only optional leading and trailing ASCII SP/HTAB
  - [ ] internal whitespace is preserved
  - [ ] repeated header fields are never comma-joined before validation
  - [ ] failure validation order is exact:
    1. connection establishment and request write
    2. total response byte budget and header-section framing
    3. status-line grammar and individual header-line grammar
    4. required singleton header cardinality and forbidden-header absence
    5. `Content-Length` syntax, overflow, and response-budget validation
    6. body framing against the parsed `Content-Length`
    7. expected status code
    8. required header values
    9. expected body bytes
  - [ ] the first failed stage determines the only emitted failure code
  - [ ] exact mappings:
    - [ ] stage 1 → `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
    - [ ] stages 2, 3, 5, or 6 → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] stage 4 or 8 → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] stage 7 → `CORETSIA_SMOKE_HTTP_STATUS_MISMATCH`
    - [ ] stage 9 → `CORETSIA_SMOKE_HTTP_BODY_MISMATCH`
  - [ ] asserts:
    - [ ] status exactly `503`
    - [ ] `Content-Type` exactly `application/json; charset=utf-8`
    - [ ] `Cache-Control` exactly `no-store`
    - [ ] `Content-Length` value is exactly the canonical unsigned base-10 ASCII representation of `strlen(BOOT_NOT_READY_BODY)`
    - [ ] the expected `Content-Length` contains no leading zero
    - [ ] numeric equality with a non-canonical representation is insufficient
    - [ ] no `Set-Cookie`
    - [ ] no `Location`
    - [ ] no `X-Powered-By`
    - [ ] no `Transfer-Encoding`
    - [ ] body equals `BOOT_NOT_READY_BODY` byte-for-byte
    - [ ] body ends with exactly one LF
  - [ ] does not accept URL, path, query, arbitrary expected status, regex, headers, body, method, request headers, or payload file
  - [ ] silent on success
  - [ ] deterministic failure codes:
    - [ ] `CORETSIA_SMOKE_HTTP_INVALID_ARGUMENT`
    - [ ] `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
    - [ ] `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] `CORETSIA_SMOKE_HTTP_STATUS_MISMATCH`
    - [ ] `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] `CORETSIA_SMOKE_HTTP_BODY_MISMATCH`
    - [ ] `CORETSIA_SMOKE_HTTP_INTERNAL_FAILURE`
  - [ ] failure output is one JSON line on stderr:
    - [ ] `{"schema":1,"code":"<CODE>"}\n`
  - [ ] failure output contains no URL, host, port, response body, response header value, path, exception message, or socket diagnostic

#### Modifies

- [ ] repo-root `composer.json` — add scripts:
  - [ ] `serve` → `@php tools/http/serve`
  - [ ] `smoke:http` → `@php tools/http/smoke http`
  - [ ] script arguments remain pass-through after Composer `--`

- [ ] `docs/architecture/PACKAGING.md`
  - [ ] register stable HTTP entrypoints:
    - [ ] `packages/applications/skeleton/apps/web/public/index.php`
    - [ ] `packages/applications/skeleton/apps/api/public/index.php`
  - [ ] document explicit front-controller target ownership
  - [ ] document shared non-public `HttpFrontController` bootstrap seam
  - [ ] document that console and worker use non-HTTP host entrypoints owned by their respective epics
  - [ ] document that HTTP front-controller paths remain stable when Phase 3 replaces the temporary fallback

### Verification (TEST EVIDENCE) (MUST)

- [ ] `composer serve -- --target=web`
  - [ ] selects `packages/applications/skeleton/apps/web/public`
  - [ ] serves the web front controller
  - [ ] returns deterministic 503 fallback

- [ ] `composer serve -- --target=api`
  - [ ] selects `packages/applications/skeleton/apps/api/public`
  - [ ] serves the API front controller
  - [ ] returns the same deterministic 503 fallback

- [ ] `composer smoke:http -- --target=web`
  - [ ] starts one server
  - [ ] verifies exact response
  - [ ] terminates the server
  - [ ] succeeds silently

- [ ] `composer smoke:http -- --target=api`
  - [ ] starts one server
  - [ ] verifies exact response
  - [ ] terminates the server
  - [ ] succeeds silently

- [ ] direct `tools/http/smoke-http` without a server:
  - [ ] fails with `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
  - [ ] starts no server

- [ ] occupied serve port:
  - [ ] fails with `CORETSIA_SERVE_PORT_UNAVAILABLE`
  - [ ] emits only exact deterministic JSON failure output

### Tests (MUST)

- Contract:
  - [ ] `tools/tests/Contract/RepoRootHttpSmokeComposerScriptsContractTest.php`
    - [ ] repo-root `composer.json` contains exact `serve` script:
      - [ ] `@php tools/http/serve`
    - [ ] repo-root `composer.json` contains exact `smoke:http` script:
      - [ ] `@php tools/http/smoke http`
    - [ ] no duplicate or alternative serve/smoke script entry exists
    - [ ] scripts contain no shell operator, platform-specific executable, env assignment, or CWD-dependent path

  - [ ] `tools/tests/Contract/HttpSmokeEphemeralIpcContractTest.php`
    - [ ] smoke/serve IPC does not rely on non-blocking `proc_open` pipes
    - [ ] no `stream_select()` call receives a process pipe
    - [ ] readiness uses only the atomic ready-file protocol
    - [ ] orderly stop uses only the atomic stop-file protocol
    - [ ] IPC directory and filenames match the exact contract
    - [ ] IPC paths are absent from public diagnostics
    - [ ] temporary IPC state is not classified as a generated artifact

  - [ ] `tools/tests/Contract/HttpFrontControllerFallbackContractTest.php`
    - [ ] class file can be required without producing output or headers
    - [ ] class is final
    - [ ] exact constant value is independently asserted as:
      - [ ] `{"schema":1,"code":"CORETSIA_HTTP_BOOT_NOT_READY","message":"boot not ready"}\n`
    - [ ] constant is ASCII-only
    - [ ] constant contains LF only
    - [ ] constant ends with exactly one LF
    - [ ] constant contains no target, timestamp, host, port, path, request, process, or exception data
    - [ ] `run('invalid')` throws exactly `LogicException('http-front-controller-target-invalid')`
    - [ ] invalid-target exception contains no rejected target value

  - [ ] `tools/tests/Contract/HttpSkeletonFrontControllersDeclareCanonicalTargetsTest.php`
    - [ ] web front controller calls shared bootstrap with exactly `web`
    - [ ] api front controller calls shared bootstrap with exactly `api`
    - [ ] both use CWD-independent paths
    - [ ] neither front controller reads superglobals
    - [ ] neither duplicates fallback response bytes
    - [ ] no HTTP front controller exists under console or worker skeleton apps

  - [ ] `tools/tests/Contract/SmokeHttpIsSocketCheckerOnlyTest.php`
    - [ ] rejects ext-curl and cURL symbols
    - [ ] rejects `proc_open`
    - [ ] rejects env reads
    - [ ] rejects server-start, URL, path, query, regex, preview, arbitrary-header, and expected-payload options
    - [ ] checker reads the canonical bootstrap body constant

- Integration:
  - [ ] `tools/tests/Integration/ServeSmokeAndSmokeHttpCliContractTest.php`
    - [ ] split option forms are rejected
    - [ ] duplicate options are rejected
    - [ ] unknown options are rejected
    - [ ] empty values are rejected
    - [ ] invalid host, port, and timeout values are rejected
    - [ ] `localhost` canonicalizes to `127.0.0.1`
    - [ ] `0.0.0.0` uses bind host `0.0.0.0` and connect host `127.0.0.1`
    - [ ] `console|worker` map to the exact target-not-HTTP codes
    - [ ] unknown targets map to the exact target-invalid codes
    - [ ] every failure exits with status `1`
    - [ ] every failure emits exactly one safe JSON line
    - [ ] the shared option grammar is verified independently for all three scripts
    - [ ] `serve` and `smoke` reject invalid target case variants
    - [ ] `smoke-http` rejects `--url` and `--path`
    - [ ] every script works from a non-repository current working directory
    - [ ] hostile or similarly named env values do not alter defaults or parsing
    - [ ] internal failure output contains only the script-local internal code

  - [ ] `tools/tests/Integration/ServeLifecycleControlTest.php`
    - [ ] serve emits readiness only after TCP reachability
    - [ ] exact atomic stop-file protocol terminates the wrapper and built-in server
    - [ ] orderly control shutdown exits with status `0`
    - [ ] orderly control shutdown emits no failure output
    - [ ] selected port is no longer reachable after successful shutdown
    - [ ] built-in-server stdin uses the platform null sink
    - [ ] canonical `ready` and valid `stop` files are regular files rather than symlinks
    - [ ] pre-existing reserved IPC files or symlinks cause `CORETSIA_SERVE_INVALID_ARGUMENT`
    - [ ] malformed or symlinked `stop` state causes `CORETSIA_SERVE_INTERNAL_FAILURE`
    - [ ] smoke-side readiness validation rejects any non-canonical ready-file state before TCP ownership confirmation
    - [ ] control-directory paths never appear in output
    - [ ] `serve` does not delete the caller-owned control directory
    - [ ] after orderly serve shutdown, the control directory contains no unexpected paths beyond the reserved IPC filenames
    - [ ] the test owner can remove all remaining reserved IPC files and the control directory
    - [ ] atomic `ready` file contains exactly one LF-terminated readiness line
    - [ ] atomic `ready` file uses canonical bind and connect hosts
    - [ ] direct serve without `--control-dir` emits the same readiness bytes to stdout
    - [ ] unexpected post-readiness child exit maps to `CORETSIA_SERVE_CHILD_EXITED`

  - [ ] `tools/tests/Integration/ServeIgnoresAmbientPhpConfigurationTest.php`
    - [ ] runs with a synthetic hostile `PHPRC`
    - [ ] runs with a synthetic hostile `PHP_INI_SCAN_DIR`
    - [ ] hostile INI attempts to configure:
      - [ ] `auto_prepend_file`
      - [ ] `auto_append_file`
      - [ ] `display_errors`
      - [ ] `expose_php`
    - [ ] runs with `PHP_CLI_SERVER_WORKERS=4`
    - [ ] response remains the exact canonical fallback
    - [ ] no prepend/append bytes appear
    - [ ] no `X-Powered-By` appears
    - [ ] `PHP_CLI_SERVER_WORKERS` does not change readiness, response, or shutdown behavior
    - [ ] orderly shutdown leaves no listener on the selected port
    - [ ] cleanup leaves no IPC file or control directory

  - [ ] `tools/tests/Integration/HttpStubSmokeTest.php`
    - [ ] matrix:
      - [ ] target `web`
      - [ ] target `api`
    - [ ] invokes `tools/http/smoke http --target=<target>`
    - [ ] verifies status, required headers, forbidden headers, content length, and exact body bytes
    - [ ] verifies silent success
    - [ ] verifies server process is terminated after the smoke run
    - [ ] verifies every owned IPC file and the smoke control directory are removed after the smoke run
    - [ ] every normal success and checker-failure path performs cleanup
    - [ ] final output is emitted only after cleanup
    - [ ] cleanup failure overrides an earlier success or failure result with `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`

  - [ ] `tools/tests/Integration/SmokeHttpDoesNotManageServerTest.php`
    - [ ] without a running server, `smoke-http` fails with `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
    - [ ] no server process is started
    - [ ] no log or temporary diagnostic file is created

  - [ ] `tools/tests/Integration/SmokeHttpFailureMappingTest.php`
    - [ ] uses a synthetic local socket responder with fixed response fixtures
    - [ ] malformed status line → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] incomplete header section → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] response over `65536` bytes → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] status/header section over `16384` bytes → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] `Content-Length` larger than the remaining total response budget → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] wrong status → `CORETSIA_SMOKE_HTTP_STATUS_MISMATCH`
    - [ ] missing or duplicate required header → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] forbidden header → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] malformed `Content-Length` → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] body-length mismatch → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] wrong body bytes → `CORETSIA_SMOKE_HTTP_BODY_MISMATCH`
    - [ ] every failure output contains no raw response data
    - [ ] missing `Content-Length` → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] duplicate `Content-Length` → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] syntactically malformed single `Content-Length` → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] numerically correct but non-canonical `Content-Length` representation → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] whitespace before a header colon → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] missing header colon → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] header mismatch takes precedence over body comparison
    - [ ] status mismatch takes precedence over header-value and body mismatch
    - [ ] response framing failure takes precedence over every semantic mismatch
    - [ ] response-read timeout → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`

  - [ ] `tools/tests/Integration/ServePortUnavailableDeterministicTest.php`
    - [ ] starts `tools/http/serve` on an already occupied port
    - [ ] asserts `CORETSIA_SERVE_PORT_UNAVAILABLE`
    - [ ] asserts exact one-line JSON failure output
    - [ ] asserts no child output, path, host, port, PID, command, or exception diagnostic is exposed

  - [ ] `tools/tests/Integration/SmokeOccupiedPortDoesNotEstablishReadinessTest.php`
    - [ ] starts an unrelated synthetic listener on the selected port
    - [ ] invokes `tools/http/smoke http`
    - [ ] external TCP reachability is not accepted as smoke readiness
    - [ ] smoke never invokes `smoke-http` against the unrelated listener
    - [ ] failure maps to `CORETSIA_SMOKE_SERVER_START_FAILED`
    - [ ] the unrelated listener remains active and is not treated as an owned orphan
    - [ ] cleanup does not wait for the unrelated listener to terminate

### DoD (MUST)

- [ ] Web and API have real stable public front-controller paths.
- [ ] Each front controller declares exactly one canonical target.
- [ ] No target is inferred from path, request, env, config, host, or command-line runtime data.
- [ ] `HttpFrontController` is outside public docroots.
- [ ] The temporary 503 response is owned by the shared HTTP bootstrap seam.
- [ ] `_boot_not_ready_payload.php` does not exist.
- [ ] Web and API return the same byte-exact fallback body.
- [ ] The fallback body is encoded statically and not generated at request time.
- [ ] Status is exactly 503.
- [ ] Required headers are deterministic.
- [ ] Cookies, redirects, `X-Powered-By`, request reflection, and dynamic diagnostics are absent.
- [ ] No superglobal is read by the Phase-2 fallback.
- [ ] `serve` and `smoke http` use canonical `--target`.
- [ ] `serve` accepts only HTTP targets `web|api`.
- [ ] `console|worker` are rejected deterministically and receive no HTTP entrypoints.
- [ ] `smoke` starts exactly one server and always terminates it.
- [ ] `smoke-http` is a checker only and never starts a server.
- [ ] No cURL or external HTTP client dependency remains.
- [ ] No env-controlled smoke behavior remains.
- [ ] No profiles, arbitrary expectations, dynamic logs, previews, colors, JUnit, or GitHub annotations remain.
- [ ] Success paths are silent except for the optional deterministic `serve` readiness line.
- [ ] Failure output is a fixed one-line JSON shape containing only schema and code.
- [ ] Port collision produces `CORETSIA_SERVE_PORT_UNAVAILABLE`.
- [ ] All scripts are CWD-independent.
- [ ] Child process commands use argument vectors without shell interpolation.
- [ ] A successful smoke run leaves no serve wrapper or PHP built-in server process.
- [ ] Cleanup has fixed deadlines and reports `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED` instead of waiting indefinitely.
- [ ] No default skeleton `config/http.php` is introduced.
- [ ] HTTP front controllers are classified as executable app entrypoints, not default HTTP configuration
- [ ] `tools/gates/no_skeleton_http_default_gate.php` remains unchanged
- [ ] No gate allowlist or front-controller exception is introduced
- [ ] Front-controller paths can remain unchanged when platform/http replaces the temporary fallback.
- [ ] `smoke`, `smoke-http`, and `serve` are created by this epic rather than treated as existing Coretsia files.
- [ ] Imported source code establishes no compatibility surface.
- [ ] CLI option grammar, host, port, timeout, duplicate-option, and exit-status behavior are deterministic.
- [ ] `serve` emits readiness only after the target port accepts connections.
- [ ] `smoke` suppresses all child diagnostics and emits only its own deterministic failure payload.
- [ ] Exact fallback bytes are independently protected by a contract test rather than verified only through the shared constant.
- [ ] HTTP response parsing has a fixed byte budget and rejects malformed or ambiguous framing.
- [ ] `smoke` accepts readiness only from the exact serve-wrapper readiness handshake plus TCP reachability.
- [ ] A pre-existing listener cannot be mistaken for the smoke-owned PHP server.
- [ ] Port-release verification is performed only for a readiness-confirmed owned server.
- [ ] Every post-spawn smoke path performs bounded cleanup before final output.
- [ ] Cleanup failure has deterministic precedence over every prior smoke result.
- [ ] Built-in-server stdin uses the platform null sink and serve orchestration control exists only through atomic filesystem IPC.
- [ ] Process descriptor mappings and platform null sinks are exact and cross-OS.
- [ ] Every CLI script has a safe internal-failure code and one top-level failure boundary.
- [ ] Socket connect, request-write, response-read, parsing, status, header, and body failures have exact precedence.
- [ ] Repo-root Composer serve and HTTP-smoke entrypoints are protected by a contract test.
- [ ] `docs/architecture/PACKAGING.md` documents the final target-aware front-controller and bootstrap ownership model.
- [ ] Cross-OS orchestration does not depend on non-blocking `proc_open` pipes or `stream_select()` over process descriptors.
- [ ] Smoke/serve readiness and stop control use private atomic ephemeral filesystem IPC.
- [ ] Every owned IPC file and directory is removed after success or failure.
- [ ] Built-in-server execution ignores ambient php.ini, scanned INI files, prepend/append files, and `PHP_CLI_SERVER_WORKERS`.
- [ ] Windows process creation explicitly bypasses `cmd.exe`.
- [ ] The checker child has a parent-enforced monotonic deadline.
- [ ] Required-header cardinality is validated before parsing and applying `Content-Length`.
- [ ] HTTP failure precedence is executable without circular or unavailable input dependencies.
- [ ] IPC cleanup is registered immediately after control-directory creation, before process startup.
- [ ] Atomic IPC publication uses exclusive temporary files, complete writes, flush, close, and same-directory rename.
- [ ] Serve, checker, and wrapper termination have bounded graceful and hard-termination phases.
- [ ] Successful smoke completion requires serve-wrapper exit status `0`.
- [ ] A non-zero wrapper exit during cleanup maps to `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`.
- [ ] Total HTTP response budget includes status line, headers, separator, and body.
- [ ] `Content-Length` cannot exceed the remaining total response budget.
- [ ] No stale stdin-pipe or readiness-output terminology remains after adoption of filesystem IPC.
- [ ] The shared atomic IPC publication contract is referenced rather than partially duplicated in smoke cleanup.
- [ ] Server cleanup failure triggers include process state, wrapper exit status, owned port state, and IPC removal.
- [ ] IPC directory deletion remains owned by `smoke`, not by `serve`.
- [ ] `tools/http/smoke` accepts only the exact layout `smoke http [options]`.
- [ ] HTTP `Host` uses the canonical connect host rather than the original requested host token.
- [ ] HTTP header-line colon and whitespace grammar is deterministic.
- [ ] Expected `Content-Length` uses one canonical decimal byte representation.
- [ ] All contract and integration tests pass.

---

### 3.145.0 Web app entrypoint: real boot + module registration + browser-visible output (MUST) [IMPL] [REQUIRES REDESIGN]

---
type: skeleton
phase: 3
epic_id: "3.145.0"
owner_path: "packages/applications/skeleton/apps/web/"

goal: "Замість Phase-2 stub (503 boot-not-ready) забезпечити реальну точку входу web-app: kernel boot → container → platform/http + platform/http-app, щоб можна було зайти в браузері і отримати 200 з demo output."
provides:
- "Реальний boot для `packages/applications/skeleton/apps/web/public/index.php` через core/kernel (mode preset + artifacts) без runtime сканувань"
- "Мінімальний app-module (у skeleton) з DI реєстрацією demo action/service"
- "Детермінований dev server flow: compile artifacts → serve → GET / повертає стабільний JSON/HTML"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/modes.md"
- "docs/ssot/routing-artifact.md"
---

> **REQUIRES REDESIGN BEFORE IMPLEMENTATION (MUST)**
>
> This epic MUST be redesigned together with 3.143.0 before implementation.
>
> Reconcile HTTP front-controller ownership, real application bootstrap, runtime module selection, consumer-facing serving commands, and deterministic smoke verification.
>
> All existing implementation details, prerequisites, and acceptance criteria are provisional until both epics have been reviewed against the current packaging architecture and completed HTTP runtime.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.280.0–1.340.0 — core/kernel boot/config/artifacts/container compile baseline exists
  - 3.10.0 + 3.40.0 — errors + problem-details exist (щоб 500/404 мали канонічний flow)
  - 3.50.0 — platform/http runtime exists
  - 3.130.0 — platform/routing exists (routes artifact)
  - 3.140.0 — platform/http-app exists (router middleware + action invoker)
  - 3.143.0 — front controller stub exists (index.php + serve script)

- Required deliverables (exact paths):
  - `packages/applications/skeleton/apps/web/public/index.php` (from 3.143.0)

- Required config roots/keys:
  - `app.*`, `foundation.*`, `kernel.*`, `http.*`, `routing.*`, `http_app.*` — з відповідних пакетів / skeleton app config
  - `database.*` — не обов’язково (не блокує demo)

- Required tags:
  - `cli.command` (для compile flow, якщо використовується CLI)
  - `http.middleware.*` (platform/http)
  - `routing.route_provider` (platform/routing)
  - `error.mapper` (platform/errors)

- Required contracts / ports:
  - (optional) `Coretsia\\Contracts\\Context\\ContextAccessorInterface`
  - `Psr\\Http\\Message\\ServerRequestInterface`
  - `Psr\\Http\\Server\\RequestHandlerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- N/A (skeleton + glue)

Forbidden:
- вводити нові runtime пакети у skeleton без mode-політики

### Entry points / integration points (MUST)

- Browser entrypoint:
  - `packages/applications/skeleton/apps/web/public/index.php` MUST dispatch to real boot when artifacts are ready
- Dev flow:
  - `tools/http/serve` SHOULD compile required artifacts deterministically before starting server

### Deliverables (MUST)

#### Creates

- [ ] Before implementation, re-review this epic against the current runtime-driver ownership boundary: `RuntimeDriverResolver` remains Kernel matrix-only; owner packages/adapters own their package/module prerequisites, adapter/transport/executable readiness, and `RuntimeDriverContributions` carry selected canonical drivers only.

App config (minimal, для demo):
- [ ] `packages/applications/skeleton/apps/web/config/app.php`
  - [ ] returns subtree with stable `id` used for `packages/applications/skeleton/var/cache/<appId>/...`
  - [ ] returns subtree with default `mode` = `express` for demo
  - [ ] runtime reads these values as global config keys `app.id` and `app.mode`
- [ ] `packages/applications/skeleton/apps/web/config/http_app.php`
  - [ ] returns subtree only (no repeated root)
  - [ ] MUST define deterministic `http_app.handlers.map` for the demo handler ids used by compiled routes
- [ ] Normative:
  - [ ] this epic MUST NOT introduce `packages/applications/skeleton/apps/web/config/modules.php` as a runtime module-selection path
  - [ ] module selection for web boot MUST remain kernel-owned and single-choice:
    - [ ] selected preset / mode files
    - [ ] composer metadata discovery
  - [ ] this epic MAY add application-local app code (`AppModule`, providers, route provider, actions),
    but it MUST NOT add a parallel modules-list config file that bypasses the cemented ModulePlan policy

Demo module & action (показати “реєстрацію сервісів”):
- [ ] `packages/applications/skeleton/apps/web/src/Module/AppModule.php`
- [ ] `packages/applications/skeleton/apps/web/src/Provider/AppServiceProvider.php`
- [ ] `packages/applications/skeleton/apps/web/src/Routing/AppRouteProvider.php`
  - [ ] implements `Coretsia\Contracts\Routing\RouteProviderInterface`
  - [ ] provides deterministic route definitions for the demo web app
  - [ ] MUST define `GET /` with a stable `handlerId` consumed by `platform/http-app`
- [ ] `packages/applications/skeleton/apps/web/src/Http/Action/HomeAction.php`
  - [ ] returns deterministic response (JSON: `{ "ok": true, "app": "<appId>" }`)
  - [ ] handler id used by the route provider MUST be mapped in `packages/applications/skeleton/apps/web/config/http_app.php`

Real boot:
- [ ] `packages/applications/skeleton/apps/web/public/_boot_web.php`
  - [ ] MUST:
    - [ ] build Kernel runtime for selected `app.id` / `app.mode`
    - [ ] refuse with deterministic 503 payload if required artifacts missing
      (`packages/applications/skeleton/var/cache/<appId>/container.php`, `packages/applications/skeleton/var/cache/<appId>/config.php`, `packages/applications/skeleton/var/cache/<appId>/routes.php`)
    - [ ] on success: create request from globals → dispatch via http kernel → emit response
- [ ] `tools/tests/Integration/WebEntryPointHelloEndToEndTest.php`
  - [ ] starts built-in server → GET `/` → asserts 200 + deterministic body
  - [ ] MUST prove that the matched app route `/` wins over `platform/http` fallback `/`
  - [ ] assertion target MUST be the demo app response body from `HomeAction`, not the fallback “Hello Coretsia” page
- [ ] `tools/tests/Integration/WebEntryPointMissingArtifactsReturns503Test.php`
  - [ ] starts built-in server without required artifacts → GET `/` → asserts deterministic 503 body from `_boot_not_ready_payload.php`

#### Modifies

- [ ] `packages/applications/skeleton/apps/web/public/index.php`
  - [ ] replace stub call with:
    - [ ] call `_boot_web.php` when ready
    - [ ] keep `_boot_not_ready_payload.php` as fallback payload (single source for 503 body)
- [ ] `packages/applications/skeleton/apps/web/src/Provider/AppServiceProvider.php`
  - [ ] registers `HomeAction`
  - [ ] registers `AppRouteProvider`
  - [ ] adds tag `routing.route_provider` for `AppRouteProvider` using the owner public constant from `platform/routing`
- [ ] `tools/http/serve`
  - [ ] before starting server, run deterministic compile chain (single-choice):
    - [ ] `coretsia config:compile`
    - [ ] `coretsia routes:compile`
  - [ ] `tools/http/serve` MUST resolve selected HTTP app target via `--app=<web|api>` with default = `web`
  - [ ] compile + serve flow in this epic is executed for the selected app target only
  - [ ] docroot MUST resolve to `packages/applications/skeleton/apps/<app>/public`
  - [ ] NOTE:
    - [ ] `container.php` is produced by `config:compile`; `container:compile` is NOT a separate required step here.
  - [ ] MUST remain CWD-independent and repo-root anchored

### Cross-cutting (only if applicable; otherwise `N/A`)

- [ ] Security:
  - [ ] MUST NOT reflect request headers/cookies/body in demo output
  - [ ] MUST NOT leak env/config dumps in errors

### Tests (MUST)

- [ ] `tools/tests/Integration/WebEntryPointHelloEndToEndTest.php`
- [ ] `tools/tests/Integration/WebEntryPointMissingArtifactsReturns503Test.php`

### DoD (MUST)

- [ ] `composer serve` (або еквівалент) запускає сервер
- [ ] Після compile chain: GET `/` у браузері повертає 200 + deterministic body
- [ ] Без artifacts: deterministic 503 з payload з `_boot_not_ready_payload.php`
- [ ] Реєстрація сервісів продемонстрована через skeleton AppModule/AppServiceProvider
- [ ] Module selection remains compliant with kernel-owned policy:
  - [ ] this epic does NOT introduce `packages/applications/skeleton/apps/web/config/modules.php`
  - [ ] demo web module is surfaced through the existing kernel-owned module selection flow
    (preset/mode selection + composer metadata), not via a parallel skeleton modules list
- [ ] Multi-app skeleton compatibility is preserved:
  - [ ] this epic materializes only the `web` app target
  - [ ] it does not forbid later `api|console|worker` app targets
  - [ ] app-local configuration stays selected-app scoped and MUST NOT become a parallel mode/module registry
- [ ] Skeleton runtime wiring uses the owner public constant for `routing.route_provider` (no local mirror/string fallback here because `platform/routing` is an allowed dependency)
- [ ] Scope clarification (MUST):
  - [ ] this epic materializes the `web` app target only
  - [ ] it MUST remain compatible with the canonical skeleton multi-app layout:
    - [ ] `packages/applications/skeleton/apps/web/`
    - [ ] `packages/applications/skeleton/apps/api/`
    - [ ] `packages/applications/skeleton/apps/console/`
    - [ ] `packages/applications/skeleton/apps/worker/`
  - [ ] it MUST NOT redefine module-selection policy or imply that `web` is the only valid app target in the skeleton

---

### 3.150.0 CORS middleware (inside platform/http) (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.150.0"
owner_path: "packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "Централізований CORS з deterministic поведінкою (preflight 204, stable headers order) до negotiation/routing/auth/csrf."
provides:
- "Deterministic CORS policy + preflight handling (204) у PSR-15 middleware"
- "Stable CORS headers order + safe logging/metrics (no raw Origin/headers)"
- "SSoT placement lock: `http.middleware.system_pre` prio 840 + catalog/fixture update in same PR"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-XXXX-cors-middleware.md"
ssot_refs:
- "docs/ssot/http-middleware-catalog.md"
---

- [ ] update `3.150.0 CORS middleware` to register `CorsExceptionMapper` under the shared `error.mapper` tag with empty `[]` metadata so blocked-origin failures use canonical normalization instead of ad-hoc HTTP responses or a local mirror of the Foundation tag constant.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - Phase 0 `platform/http` stack model exists and executes `http.middleware.system_pre`.

- Required deliverables (exact paths):
  - `docs/ssot/http-middleware-catalog.md` — middleware catalog (must be updated with CORS row)
  - `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php` — shared executable catalog lock created by `3.50.0`
  - `packages/platform/http/src/Provider/HttpServiceProvider.php` — place to wire middleware

- Required config roots/keys:
  - `http.cors.*` — CORS policy keys (introduced by this epic; no external precondition)

- Existing root reuse note (MUST):
  - This epic extends an existing reserved config root with additional subkeys only.
  - It MUST NOT be treated as a config-root owner/introduction epic.

- Required tags:
  - `http.middleware.system_pre` — middleware slot (owner=`platform/http`)

- Required contracts / ports:
  - `Psr\Http\Server\MiddlewareInterface` — middleware
  - `Psr\Http\Message\ServerRequestInterface` — middleware
  - `Psr\Http\Message\ResponseInterface` — middleware

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `core/kernel` *(platform/http baseline dep from Phase 0)*

Forbidden:
- `platform/http-app`
- `platform/routing`
- `platform/problem-details`
- `platform/errors`
- `platform/*` (any other runtime packages)
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
- Contracts:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (noop-safe)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (noop-safe)

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - middleware: `\Coretsia\Http\Middleware\CorsMiddleware::class`
  - middleware slots/tags: `http.middleware.system_pre` priority `840` meta `{toggle:'http.cors.enabled'}`
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `packages/platform/http/src/Cors/CorsPolicy.php` — VO (allowed origins/methods/headers, deterministic)
- [ ] `packages/platform/http/src/Middleware/CorsMiddleware.php` — PSR-15, handles preflight + response headers
- [ ] `packages/platform/http/src/Exception/CorsBlockedException.php` — `CORETSIA_HTTP_CORS_BLOCKED`
- [ ] `packages/platform/http/src/Error/CorsExceptionMapper.php`
  - [ ] implements `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - [ ] maps `CorsBlockedException` → `ErrorDescriptor` with optional HTTP hint `403`
  - [ ] MUST NOT leak raw `Origin` / request header values (`hash/len` only)
  - [ ] MUST be registered via DI tag `error.mapper` using a package-local internal mirror constant
    - [ ] canonical string literal `'error.mapper'` is allowed only as fallback
    - [ ] MUST NOT import `platform/errors`, because it is a forbidden compile-time dependency here
- [ ] `docs/guides/cors.md` — policy examples + security notes

Tests:
- [ ] `packages/platform/http/tests/Contract/CorsHeadersDeterministicOrderContractTest.php`
- [ ] `packages/platform/http/tests/Integration/CorsPreflightReturns204WithDeterministicHeadersTest.php`
- [ ] `packages/platform/http/tests/Integration/CorsBlockedOriginIncrementsMetricNoopSafeTest.php`

#### Modifies

- [ ] `packages/platform/http/config/http.php` — add `http.cors.*` keys + defaults
- [ ] `packages/platform/http/config/rules.php` — enforce shape for `http.cors.*`
- [ ] `packages/platform/http/src/Provider/HttpServiceProvider.php` — registers + tags CORS middleware (system_pre 840)
- [ ] `packages/platform/http/README.md` — “CorsMiddleware” section (slot/priority/override recipe)
- [ ] `docs/ssot/http-middleware-catalog.md` — add/update row for `CorsMiddleware` (priority 840)
- [ ] `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php` — extend shared catalog lock for `CorsMiddleware` (`http.middleware.system_pre` priority `840`)
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-cors-middleware.md`

#### Package skeleton (if type=package)

N/A (package `platform/http` already exists; this epic adds CORS slice only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.cors.enabled` = false
  - [ ] `http.cors.allowed_origins` = []
  - [ ] `http.cors.allowed_methods` = ['GET','POST','PUT','PATCH','DELETE','OPTIONS']
  - [ ] `http.cors.allowed_headers` = []
  - [ ] `http.cors.exposed_headers` = ['X-Correlation-Id','X-Request-Id']
  - [ ] `http.cors.allow_credentials` = false
  - [ ] `http.cors.max_age` = 600
- [ ] Rules:
  - [ ] `packages/platform/http/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Http\Middleware\CorsMiddleware`
  - [ ] adds tag: `http.middleware.system_pre` priority `840` meta `{toggle:'http.cors.enabled'}`
  - [ ] registers: `Coretsia\Http\Error\CorsExceptionMapper`
  - [ ] adds tag: `error.mapper` using a package-local internal mirror constant
    - [ ] canonical string literal `'error.mapper'` is allowed only as fallback

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `http.cors` (attrs: `operation=preflight|simple`, `outcome`)
- [ ] Metrics:
  - [ ] `http.cors.preflight_total` (labels: `outcome=ok|blocked`)
  - [ ] `http.cors.blocked_total` (labels: `status`, `outcome=blocked`)
- [ ] Logs:
  - [ ] blocked origin → warn (no header dump; only `hash(origin)` if needed)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Http\Exception\CorsBlockedException` — errorCode `CORETSIA_HTTP_CORS_BLOCKED`
- [ ] Mapping:
  - [ ] blocked CORS decisions MUST throw deterministic `CorsBlockedException`
  - [ ] mapping MUST go through `error.mapper` → `ErrorDescriptor`
  - [ ] middleware MUST NOT craft ad-hoc blocked error responses

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw Origin / raw request headers in logs
- [ ] Allowed:
  - [ ] `hash(origin)` / `len(origin)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `N/A`
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist → `packages/platform/http/tests/Integration/CorsBlockedOriginIncrementsMetricNoopSafeTest.php`
- [ ] If redaction exists → `packages/platform/http/tests/Integration/CorsBlockedOriginIncrementsMetricNoopSafeTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `packages/platform/http/tests/Contract/CorsHeadersDeterministicOrderContractTest.php`
  - [ ] `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php`
- Integration:
  - [ ] `packages/platform/http/tests/Integration/CorsPreflightReturns204WithDeterministicHeadersTest.php`
  - [ ] `packages/platform/http/tests/Integration/CorsBlockedOriginIncrementsMetricNoopSafeTest.php`
  - [ ] `packages/platform/http/tests/Integration/CorsBlockedOriginTriggersCanonicalErrorFlowTest.php`
- Gates/Arch:
  - [ ] deptrac green

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Middleware priority/slot matches SSoT table (system_pre 840)
- [ ] Deterministic headers order
- [ ] No secret/PII leakage
- [ ] Docs updated:
  - [ ] `packages/platform/http/README.md`
  - [ ] `docs/ssot/http-middleware-catalog.md`
  - [ ] `docs/guides/cors.md`
- [ ] Shared catalog lock contract + SSoT catalog updated in the same PR as middleware code
- [ ] Slot/priority locked by contract test
- [ ] What problem this epic solves:
  - [ ] Централізований CORS з deterministic поведінкою (preflight 204, headers order stable).
  - [ ] Працює **до** negotiation/routing/auth/csrf і не вимагає skeleton defaults.
- [ ] Non-goals / out of scope:
  - [ ] Не логить raw Origin/headers; лише safe summary.
  - [ ] Не вводить нові label keys (лише allowlist).
- [ ] Cutline impact: blocks Phase 3 cutline
- [ ] Canonical catalog lock (MUST):
  - [ ] Any middleware added/changed MUST update:
    - [ ] `docs/ssot/http-middleware-catalog.md`
    - [ ] `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php`
  - [ ] Wiring MUST match priority table:
    - [ ] CORS: `system_pre` prio 840
- [ ] Canonical blocked-policy is single-choice (MUST)
  - [ ] allowed preflight requests return deterministic `204`
  - [ ] blocked requests MUST throw deterministic `CorsBlockedException`
  - [ ] blocked requests MUST go through canonical error flow:
    - [ ] `error.mapper` → `ErrorDescriptor` → renderer/adapter
  - [ ] middleware MUST NOT craft ad-hoc blocked error bodies

---

### 3.160.0 Method override + Content negotiation (umbrella) (SHOULD) [DOC]

---
type: docs
phase: 3
epic_id: "3.160.0"
owner_path: "docs/ssot/http-middleware-catalog.md"

goal: "Зафіксувати SSoT placement + priorities для MethodOverride (820) і ContentNegotiation (810) у `http.middleware.system_pre` та узгодити override recipes."
provides:
- "SSoT: middleware catalog rows for MethodOverrideMiddleware (820) + ContentNegotiationMiddleware (810)"
- "SSoT: negotiation semantics `http.response.format` (`json|html`) і strict Accept behavior"
- "SSoT: method override sources/allowlist + security notes"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/http-middleware-catalog.md"
- "docs/ssot/http-negotiation.md"
- "docs/ssot/http-method-override.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - `docs/ssot/http-middleware-catalog.md` — catalog exists (this epic updates it)
  - `packages/platform/http/README.md` — package docs exists (this epic updates it)

- Required config roots/keys:
  - N/A

- Required tags:
  - `http.middleware.system_pre` — referenced in docs (owner=`platform/http`)

- Required contracts / ports:
  - N/A

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- N/A

Forbidden:
- N/A

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `docs/ssot/http-negotiation.md` — SSoT: `http.response.format` semantics + allowed values `json|html`
- [ ] `docs/ssot/http-method-override.md` — SSoT: allowed sources + security notes
  - [ ] Phase 3 implementation note:
    - [ ] baseline implementation covers header/query sources only
    - [ ] body-based override is reserved for a later epic with explicit placement/pipeline changes

#### Modifies

- [ ] `docs/ssot/http-middleware-catalog.md` — add/update rows:
  - [ ] `platform/http` `MethodOverrideMiddleware` priority `820`
  - [ ] `platform/http` `ContentNegotiationMiddleware` priority `810`
- [ ] `packages/platform/http/README.md` — sections updated for both middlewares (placement, override recipes)
- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/http-negotiation.md`
  - [ ] `docs/ssot/http-method-override.md`

#### Package skeleton (if type=package)

N/A

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A

### Tests (MUST)

N/A

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] Docs updated and referenced by package README
- [ ] Catalog priorities match runtime wiring (820 then 810)
- [ ] Override/disable recipe documented (manual mode)
- [ ] Canonical middleware placement and priorities are fully specified in `docs/ssot/http-middleware-catalog.md`
- [ ] Implementation epics `3.170.0` and `3.180.0` MUST extend the shared `MiddlewareCatalogPriorityAndSlotLockTest.php` when runtime wiring is added
- [ ] Taxonomy + ordering lock (MUST)
  - [ ] Middleware catalog rows MUST use only allowed slots
    - [ ] Add/confirm rows in `docs/ssot/http-middleware-catalog.md`:
      - [ ] `ContentNegotiationMiddleware` → slot `http.middleware.system_pre`, priority `810`
      - [ ] `MethodOverrideMiddleware`     → slot `http.middleware.system_pre`, priority `820`
    - [ ] Also (if present in Phase 3 set):
      - [ ] `CorsMiddleware`               → slot `http.middleware.system_pre`, priority `840`
  - [ ] Ordering semantics MUST match Foundation TagRegistry
    - [ ] Because ordering is `priority DESC, id ASC`, the effective execution order inside `system_pre` is:
      - [ ] `840 (CORS) → 820 (MethodOverride) → 810 (Negotiation)`.
- [ ] What problem this epic solves:
  - [ ] Фіксує placement + priorities для MethodOverride (820) і ContentNegotiation (810) у `http.middleware.system_pre`.
  - [ ] Забезпечує, що docs/каталог/override recipe оновлені синхронно з реалізаціями 3.170.0 і 3.180.0.
- [ ] Non-goals / out of scope:
  - [ ] Не містить коду (код у 3.170.0 і 3.180.0).
- [ ] Cutline impact: none

---

### 3.170.0 Method override middleware (inside platform/http) (SHOULD) [IMPL]

---
type: package
phase: 3
epic_id: "3.170.0"
owner_path: "packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "Deterministic method override policy (safe defaults) applied before routing boundary via `http.middleware.system_pre` prio 820."
provides:
- "MethodOverrideMiddleware (system_pre 820) with allowlisted sources + deterministic behavior"
- "Invalid override yields deterministic exception `CORETSIA_HTTP_METHOD_OVERRIDE_INVALID` via canonical error flow"
- "Catalog lock: update middleware catalog + rails fixture in same PR"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-XXXX-method-override-middleware.md"
ssot_refs:
- "docs/ssot/http-middleware-catalog.md"
- "docs/ssot/http-method-override.md"
---

- [ ] update `3.170.0 Method override middleware` to register `MethodOverrideExceptionMapper` under the shared `error.mapper` tag with empty `[]` metadata and let `platform/problem-details` render the normalized failure without a local mirror of the Foundation tag constant.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `3.160.0` — umbrella docs define placement and security policy

- Required deliverables (exact paths):
  - `docs/ssot/http-middleware-catalog.md` — catalog (must be updated/locked)
  - `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php` — shared executable catalog lock created by `3.50.0`
  - `packages/platform/http/src/Provider/HttpServiceProvider.php` — wiring point

- Required config roots/keys:
  - `http.method_override.*` — method override policy keys (introduced by this epic)

- Existing root reuse note (MUST):
  - This epic extends an existing reserved config root with additional subkeys only.
  - It MUST NOT be treated as a config-root owner/introduction epic.

- Supported sources in this epic (MUST):
  - This Phase 3 implementation supports only header and query sources.
  - Body source is out of scope in this epic because middleware placement is fixed at
    `http.middleware.system_pre` priority `820`, i.e. before `JsonBodyParserMiddleware` (`800`).
  - A future epic MAY add body-based override only together with an explicit placement/pipeline policy revision.

- Source conflict policy (MUST)
  - if both enabled sources are present and resolve to the same normalized method, the override is applied once
  - if both enabled sources are present and resolve to different methods, middleware MUST throw `InvalidMethodOverrideException`
  - middleware MUST NOT use implicit precedence (`header wins` / `query wins`) for conflicting values

- Required tags:
  - `http.middleware.system_pre` — middleware slot (owner=`platform/http`)

- Required contracts / ports:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:
- `platform/http-app`
- `platform/routing`
- `platform/problem-details`
- `platform/errors`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
- Contracts:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - middleware: `\Coretsia\Http\Middleware\MethodOverrideMiddleware::class`
  - middleware slots/tags: `http.middleware.system_pre` priority `820` meta `{toggle:'http.method_override.enabled'}`
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `packages/platform/http/src/MethodOverride/MethodOverridePolicy.php` — VO
- [ ] `packages/platform/http/src/Middleware/MethodOverrideMiddleware.php` — PSR-15
- [ ] `packages/platform/http/src/Exception/InvalidMethodOverrideException.php` — `CORETSIA_HTTP_METHOD_OVERRIDE_INVALID`
  - [ ] Add (or extend existing) mapper in `platform/http`:
    - [ ] `packages/platform/http/src/Error/MethodOverrideExceptionMapper.php`
      - [ ] implements `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
      - [ ] maps `InvalidMethodOverrideException` → `ErrorDescriptor` with optional HTTP hint 400
      - [ ] must not leak raw header/query/body values (only safe tokens/len/hash)
      - [ ] registered via DI tag `error.mapper` using a package-local internal mirror constant
        - [ ] canonical string literal `'error.mapper'` is allowed only as fallback
        - [ ] MUST NOT import `platform/errors`, because it is a forbidden compile-time dependency here

Tests:
- [ ] `packages/platform/http/tests/Integration/MethodOverrideAppliesBeforeRoutingTest.php`
- [ ] `packages/platform/http/tests/Integration/InvalidMethodOverrideTriggersCanonicalErrorFlowTest.php`
- [ ] `packages/platform/http/tests/Unit/MethodOverridePolicyDefaultsAreSafeTest.php`

#### Modifies

- [ ] `packages/platform/http/config/http.php` — add `http.method_override.*` keys + defaults
- [ ] `packages/platform/http/config/rules.php` — enforce shape for `http.method_override.*`
- [ ] `packages/platform/http/src/Provider/HttpServiceProvider.php` — wire middleware (system_pre 820)
- [ ] `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php` — extend shared catalog lock contract for `MethodOverrideMiddleware` (`system_pre` 820)
- [ ] `docs/ssot/http-middleware-catalog.md` — ensure row (system_pre 820)
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-method-override-middleware.md`

#### Package skeleton (if type=package)

N/A (package `platform/http` already exists; this epic adds method-override slice only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.method_override.enabled` = false
  - [ ] `http.method_override.allow_from_headers` = true
  - [ ] `http.method_override.allow_from_query` = false
  - [ ] `http.method_override.only_if_original_post` = true
  - [ ] `http.method_override.allowed_methods` = ['PUT','PATCH','DELETE']
  - [ ] `http.method_override.param_name` = '_method'
  - [ ] `http.method_override.header_name` = 'X-Http-Method-Override'
- [ ] Rules:
  - [ ] `packages/platform/http/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Http\Middleware\MethodOverrideMiddleware`
  - [ ] adds tag: `http.middleware.system_pre` priority `820` meta `{toggle:'http.method_override.enabled'}`
  - [ ] registers: `Coretsia\Http\Error\MethodOverrideExceptionMapper`
  - [ ] adds tag: `error.mapper` using a package-local internal mirror constant
    - [ ] canonical string literal `'error.mapper'` is allowed only as fallback

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `http.method_override` (attrs: `outcome=applied|skipped|invalid`)
- [ ] Metrics:
  - [ ] `http.method_override.applied_total` (labels: `outcome=applied`)
  - [ ] `http.method_override.invalid_total` (labels: `status`, `outcome=invalid`)
- [ ] Logs:
  - [ ] invalid override → warn (no header dump; only `len(value)`)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Http\Exception\InvalidMethodOverrideException` — errorCode `CORETSIA_HTTP_METHOD_OVERRIDE_INVALID`
- [ ] Mapping:
  - [ ] via canonical error flow (ProblemDetails when enabled); no custom rendering

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw header values
- [ ] Allowed:
  - [ ] `len(value)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `N/A`
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist → `packages/platform/http/tests/Integration/MethodOverrideAppliesBeforeRoutingTest.php`
- [ ] If redaction exists → `packages/platform/http/tests/Integration/InvalidMethodOverrideTriggersCanonicalErrorFlowTest.php`
- [ ] conflicting header/query source handling is proven by:
  - [ ] `packages/platform/http/tests/Integration/ConflictingMethodOverrideSourcesTriggerCanonicalErrorFlowTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] `packages/platform/http/tests/Unit/MethodOverridePolicyDefaultsAreSafeTest.php`
- Contract:
  - [ ] `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php`
- Integration:
  - [ ] `packages/platform/http/tests/Integration/MethodOverrideAppliesBeforeRoutingTest.php`
  - [ ] `packages/platform/http/tests/Integration/InvalidMethodOverrideTriggersCanonicalErrorFlowTest.php`
  - [ ] `packages/platform/http/tests/Integration/ConflictingMethodOverrideSourcesTriggerCanonicalErrorFlowTest.php`
- Gates/Arch:
  - [ ] deptrac green

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Priority/slot matches SSoT table (system_pre 820)
- [ ] Deterministic policy + safe defaults
- [ ] No secret leakage
- [ ] Docs updated (umbrella):
  - [ ] `docs/ssot/http-method-override.md`
  - [ ] `docs/ssot/http-middleware-catalog.md`
- [ ] Shared catalog lock contract + SSoT catalog updated in the same PR as middleware code
- [ ] Slot/priority locked by contract test
- [ ] What problem this epic solves:
  - [ ] Додає deterministic method override policy (header/query за allowlist) без дублювання у контролерах.
  - [ ] Забезпечує placement **до routing boundary** без дублювання body parsing логіки.
- [ ] Non-goals / out of scope:
  - [ ] Не дозволяє override за замовчуванням з body/query (безпековий default).
  - [ ] Не логить raw header values.
- [ ] Cutline impact: none
- [ ] Canonical catalog lock (MUST):
  - [ ] Any middleware added/changed MUST update:
    - [ ] `docs/ssot/http-middleware-catalog.md`
    - [ ] `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php`
  - [ ] Wiring MUST match priority table:
    - [ ] MethodOverride: `system_pre` prio 820

---

### 3.180.0 Content negotiation middleware (inside platform/http) (SHOULD) [IMPL]

---
type: package
phase: 3
epic_id: "3.180.0"
owner_path: "packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "Deterministically decide response format (`json|html`) and write `ContextKeys::HTTP_RESPONSE_FORMAT` via `http.middleware.system_pre` prio 810."
provides:
- "ContentNegotiationMiddleware (system_pre 810) sets `http.response.format` (`json|html`) deterministically"
- "Strict Accept mode can yield deterministic 406 via canonical error flow"
- "Catalog lock: update middleware catalog + rails fixture in same PR"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-XXXX-content-negotiation-middleware.md"
ssot_refs:
- "docs/ssot/http-middleware-catalog.md"
- "docs/ssot/http-negotiation.md"
---

- [ ] update `3.180.0 Content negotiation middleware` to register `NegotiationExceptionMapper` under the shared `error.mapper` tag with empty `[]` metadata for deterministic 406 descriptors, without local HTTP error rendering or a local mirror of the Foundation tag constant.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `3.160.0` — umbrella docs define placement + negotiation semantics

- Required deliverables (exact paths):
  - `docs/ssot/http-middleware-catalog.md` — catalog (must be updated/locked)
  - `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php` — shared executable catalog lock created by `3.50.0`
  - `packages/platform/http/src/Provider/HttpServiceProvider.php` — wiring point

- Required config roots/keys:
  - `http.negotiation.*` — negotiation policy keys (introduced by this epic)

- Selection precedence (MUST)
  - if `http.negotiation.allow_query_param=true` and the query format parameter is present with a supported value, it MUST win over `Accept`
  - otherwise selection MUST evaluate `Accept` deterministically
  - if multiple supported formats match with equal preference, tie-break MUST use the order of `http.negotiation.supported`
  - if no supported format is selected:
    - [ ] `strict_accept=false` → use `http.negotiation.default`
    - [ ] `strict_accept=true`  → throw `NotAcceptableException`

- Existing root reuse note (MUST):
  - This epic extends an existing reserved config root with additional subkeys only.
  - It MUST NOT be treated as a config-root owner/introduction epic.

- Required tags:
  - `http.middleware.system_pre` — middleware slot (owner=`platform/http`)

- Required contracts / ports:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:
- `platform/http-app`
- `platform/problem-details`
- `platform/errors`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
- Contracts:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextStore`
  - `Coretsia\Contracts\Context\ContextKeys`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - middleware: `\Coretsia\Http\Middleware\ContentNegotiationMiddleware::class`
  - middleware slots/tags: `http.middleware.system_pre` priority `810` meta `{toggle:'http.negotiation.enabled'}`
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `packages/platform/http/src/Negotiation/ContentNegotiationPolicy.php` — VO
- [ ] `packages/platform/http/src/Middleware/ContentNegotiationMiddleware.php` — PSR-15
- [ ] `packages/platform/http/src/Exception/NotAcceptableException.php` — `CORETSIA_HTTP_NOT_ACCEPTABLE` (406)
  - [ ] Canonical mapping for CORETSIA_HTTP_NOT_ACCEPTABLE (SHOULD)
    - [ ] If `strict_accept=true` yields 406:
      - [ ] Middleware MUST throw deterministic `NotAcceptableException` (errorCode `CORETSIA_HTTP_NOT_ACCEPTABLE`)
      - [ ] Mapping MUST be done via `error.mapper` to `ErrorDescriptor` (no ad-hoc rendering)
    - [ ] Add (or extend existing) mapper:
      - [ ] `packages/platform/http/src/Error/NegotiationExceptionMapper.php`
        - [ ] implements `ExceptionMapperInterface`
        - [ ] maps `NotAcceptableException` → `ErrorDescriptor` with optional HTTP hint 406
        - [ ] never logs/prints raw `Accept` header (hash/len only)
        - [ ] registered via DI tag `error.mapper` using a package-local internal mirror constant
          - [ ] canonical string literal `'error.mapper'` is allowed only as fallback
          - [ ] MUST NOT import `platform/errors`, because it is a forbidden compile-time dependency here

Tests:
- [ ] `packages/platform/http/tests/Integration/NegotiationSetsContextFormatTest.php`
- [ ] `packages/platform/http/tests/Integration/StrictAcceptTriggersCanonicalErrorFlowTest.php`
- [ ] `packages/platform/http/tests/Unit/NegotiationPolicyDeterministicSelectionTest.php`
  - [ ] MUST prove:
    - [ ] query-param precedence over `Accept`
    - [ ] deterministic tie-break via `http.negotiation.supported` order
    - [ ] fallback to default when `strict_accept=false`

#### Modifies

- [ ] `packages/platform/http/config/http.php` — add `http.negotiation.*` keys + defaults
- [ ] `packages/platform/http/config/rules.php` — enforce shape for `http.negotiation.*`
- [ ] `packages/platform/http/src/Provider/HttpServiceProvider.php` — wire middleware (system_pre 810)
- [ ] `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php` — extend shared catalog lock contract for `ContentNegotiationMiddleware` (`system_pre` 810)
- [ ] `docs/ssot/http-middleware-catalog.md` — ensure row (system_pre 810)
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-content-negotiation-middleware.md`

#### Package skeleton (if type=package)

N/A (package `platform/http` already exists; this epic adds negotiation slice only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.negotiation.enabled` = false
  - [ ] `http.negotiation.supported` = ['json','html']
  - [ ] `http.negotiation.default` = 'json'
  - [ ] `http.negotiation.allow_query_param` = true
  - [ ] `http.negotiation.query_param_name` = '_format'
  - [ ] `http.negotiation.strict_accept` = false
- [ ] Rules:
  - [ ] `packages/platform/http/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Http\Middleware\ContentNegotiationMiddleware`
  - [ ] adds tag: `http.middleware.system_pre` priority `810` meta `{toggle:'http.negotiation.enabled'}`
  - [ ] registers: `Coretsia\Http\Error\NegotiationExceptionMapper`
  - [ ] adds tag: `error.mapper` using a package-local internal mirror constant
    - [ ] canonical string literal `'error.mapper'` is allowed only as fallback

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context writes (safe only):
  - [ ] `ContextKeys::HTTP_RESPONSE_FORMAT` value `json|html`
- Context reads:
  - N/A
- Reset discipline:
  - N/A

#### Observability (policy-compliant)

- [ ] Metrics:
  - [ ] `http.negotiation.not_acceptable_total` (labels: `status`, `outcome=not_acceptable`)
  - [ ] `http.negotiation.selected_total` (labels: `outcome=json|html`)
- [ ] Logs:
  - [ ] not acceptable → info/warn (no Accept dump; only `hash/len`)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Http\Exception\NotAcceptableException` — errorCode `CORETSIA_HTTP_NOT_ACCEPTABLE` (406)
- [ ] Mapping:
  - [ ] via canonical error flow (ProblemDetails when enabled); no custom rendering

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw Accept header
- [ ] Allowed:
  - [ ] `hash/len` only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `packages/platform/http/tests/Integration/NegotiationSetsContextFormatTest.php`
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist → `packages/platform/http/tests/Integration/StrictAcceptTriggersCanonicalErrorFlowTest.php`
- [ ] If redaction exists → `packages/platform/http/tests/Integration/StrictAcceptTriggersCanonicalErrorFlowTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] `packages/platform/http/tests/Unit/NegotiationPolicyDeterministicSelectionTest.php`
- Contract:
  - [ ] `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php`
- Integration:
  - [ ] `packages/platform/http/tests/Integration/NegotiationSetsContextFormatTest.php`
  - [ ] `packages/platform/http/tests/Integration/StrictAcceptTriggersCanonicalErrorFlowTest.php`
- Gates/Arch:
  - [ ] deptrac green

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Priority/slot matches SSoT table (system_pre 810)
- [ ] Writes `http.response.format` safely
- [ ] Selection precedence is single-choice and locked:
  - [ ] query param (when allowed and valid) > `Accept` > default
  - [ ] equal-preference tie-break uses `http.negotiation.supported` order
- [ ] 406 handled via canonical error flow
- [ ] Docs updated (umbrella):
  - [ ] `docs/ssot/http-negotiation.md`
  - [ ] `docs/ssot/http-middleware-catalog.md`
- [ ] Shared catalog lock contract + SSoT catalog updated in the same PR as middleware code
- [ ] Slot/priority locked by contract test
- [ ] Context write discipline (MUST)
  - [ ] The middleware MUST write response format ONLY via the cemented ContextStore API
    and ONLY using an allowlisted key from `ContextKeys`:
    - [ ] key: `ContextKeys::HTTP_RESPONSE_FORMAT`
    - [ ] value: `json|html` (single-choice; must match SSoT doc `docs/ssot/http-negotiation.md`)
  - [ ] It MUST NOT write arbitrary keys and MUST NOT write any `@*` keys.
- [ ] What problem this epic solves:
  - [ ] Визначає формат відповіді (json|html) deterministically і пише `ContextKeys::HTTP_RESPONSE_FORMAT` (`http.response.format`).
  - [ ] Дозволяє ProblemDetails selector обирати JSON vs HTML без дублювання логіки.
- [ ] Non-goals / out of scope:
  - [ ] Не робить “content-type negotiation” для request body (лише response format decision).
  - [ ] Не логить raw Accept header.
- [ ] Cutline impact: none
- [ ] Canonical catalog lock (MUST):
  - [ ] Any middleware added/changed MUST update:
    - [ ] `docs/ssot/http-middleware-catalog.md`
    - [ ] `packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php`
  - [ ] Wiring MUST match priority table:
    - [ ] ContentNegotiation: `system_pre` prio 810

---

### 3.190.0 ProblemDetails HTML renderer + error pages (SHOULD) [IMPL]

---
type: package
phase: 3
epic_id: "3.190.0"
owner_path: "packages/platform/problem-details/"

package_id: "platform/problem-details"
composer: "coretsia/platform-problem-details"
kind: runtime
module_id: "platform.problem-details"

goal: "Додати deterministic HTML error pages + selector (json/html), інтегрований з `http.response.format` (preferred) і fallback на Accept (redacted)."
provides:
- "Deterministic escaped HTML renderer (no view engine) + minimal error pages"
- "Renderer selector: prefer `ContextKeys::HTTP_RESPONSE_FORMAT`, fallback Accept (never printed)"
- "No secrets/PII/stacktrace in HTML by default; JSON RFC7807 unchanged"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-XXXX-problem-details-html-renderer-error-pages.md"
ssot_refs: []
---

- [ ] update `3.190.0 ProblemDetails HTML renderer + error pages` to consume `SensitiveDataRedactorInterface` for rendered diagnostics and local debug pages.
- [ ] update `3.190.0 ProblemDetails HTML renderer + error pages` to reuse the `ErrorDescriptor` already returned by the canonical `ErrorHandlerInterface` in the existing HTTP error middleware, without another mapping pass when selecting HTML.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - Baseline `platform/problem-details` exists and provides JSON renderer and HTTP error handling middleware.

- Required deliverables (exact paths):
  - `packages/platform/problem-details/src/Http/Middleware/HttpErrorHandlingMiddleware.php` — existing middleware to be updated
  - `packages/platform/problem-details/src/Renderer/ProblemDetailsJsonRenderer.php` — existing JSON renderer used via selector
  - `packages/platform/problem-details/config/problem_details.php` — config file to extend

- Required config roots/keys:
  - `problem_details.*` — baseline config root exists

- Existing root reuse note (MUST):
  - This epic extends an existing reserved config root with additional subkeys only.
  - It MUST NOT be treated as a config-root owner/introduction epic.

- Required config roots registry:
  - `problem_details` MUST already be reserved in `docs/ssot/config-roots.md` with owner `platform/problem-details`.
  - This epic extends the existing root with additional renderer settings only; it does not introduce a new root.

- Required tags:
  - N/A (wired by `platform/http` stack; this epic changes internal selection only)

- Required contracts / ports:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Context\ContextKeys`

- tighten to cemented APIs
  - `Coretsia\Contracts\Context\ContextAccessorInterface::get(string $key): mixed` MUST be used without a default param.
  - Missing key handling MUST remain non-throwing for this epic’s runtime path.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http` (wired via tags; no compile-time dep)
- `platform/errors`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
- Foundation stable APIs:
  - `Coretsia\Contracts\Context\ContextKeys`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - middleware (existing): `\Coretsia\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware::class` (updated to select renderer)
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `packages/platform/problem-details/src/Renderer/ProblemDetailsHtmlRenderer.php` — deterministic escaped HTML
- [ ] `packages/platform/problem-details/src/Renderer/ProblemDetailsRendererSelector.php` — selects json/html
- [ ] `packages/platform/problem-details/src/Security/HtmlEscaper.php` — minimal escaper helper

Docs:
- [ ] `docs/guides/error-pages.md` — how to enable HTML + safety notes

Tests:
- [ ] `packages/platform/problem-details/tests/Integration/AcceptHtmlReturnsTextHtmlErrorPageTest.php`
- [ ] `packages/platform/problem-details/tests/Contract/ProblemDetailsHtmlDeterministicContractTest.php`
- [ ] `packages/platform/problem-details/tests/Contract/HtmlRendererDeterministicBytesContractTest.php`
- [ ] `packages/platform/problem-details/tests/Integration/FormatSelectionPrefersContextKeyOverAcceptTest.php`

#### Modifies

- [ ] `packages/platform/problem-details/src/Renderer/ProblemDetailsJsonRenderer.php` — unchanged, but used via selector
- [ ] `packages/platform/problem-details/src/Http/Middleware/HttpErrorHandlingMiddleware.php` — updated to use selector
- [ ] `packages/platform/problem-details/config/problem_details.php` — add/extend html renderer settings
- [ ] `packages/platform/problem-details/README.md` — selector rules + negotiation integration
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-problem-details-html-renderer-error-pages.md`

#### Package skeleton (if type=package)

N/A (package `platform/problem-details` already exists; this epic adds HTML renderer slice only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/problem-details/config/problem_details.php`
- [ ] Keys (dot):
  - [ ] `problem_details.renderers.enabled.json` = true
  - [ ] `problem_details.renderers.enabled.html` = true
  - [ ] `problem_details.html.title_prefix` = 'Error'
  - [ ] `problem_details.html.show_detail` = false
  - [ ] `problem_details.html.show_detail_in_local` = true
- [ ] Rules:
  - [ ] (existing rules policy) config shape enforced by package rules (if present)

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- ServiceProvider wiring evidence:
  - N/A (selection is internal to `platform/problem-details`; outer wiring handled by `platform/http`)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::HTTP_RESPONSE_FORMAT` (preferred) — no writes
- [ ] Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - N/A

#### Observability (policy-compliant)

- [ ] Metrics:
  - [ ] `http.error_render_total` (labels: `status`, `outcome=json|html`)
- [ ] Logs:
  - [ ] redaction applied; no secrets/PII/raw payloads

#### Errors

N/A (uses existing error handling contract; no new exception types required)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] secrets/PII in HTML, stacktrace in prod, raw Accept header
- [ ] Allowed:
  - [ ] deterministic minimal fields only; HTML escaped

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `N/A`
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist → `packages/platform/problem-details/tests/Integration/AcceptHtmlReturnsTextHtmlErrorPageTest.php`
- [ ] If redaction exists → `packages/platform/problem-details/tests/Contract/ProblemDetailsHtmlDeterministicContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `packages/platform/problem-details/tests/Contract/ProblemDetailsHtmlDeterministicContractTest.php`
  - [ ] `packages/platform/problem-details/tests/Contract/HtmlRendererDeterministicBytesContractTest.php`
- Integration:
  - [ ] `packages/platform/problem-details/tests/Integration/AcceptHtmlReturnsTextHtmlErrorPageTest.php`
  - [ ] `packages/platform/problem-details/tests/Integration/FormatSelectionPrefersContextKeyOverAcceptTest.php`
- Gates/Arch:
  - [ ] deptrac green

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] JSON RFC7807 unchanged
- [ ] HTML deterministic + escaped
- [ ] No secrets/PII in HTML or logs
- [ ] Docs updated:
  - [ ] `packages/platform/problem-details/README.md`
  - [ ] `docs/guides/error-pages.md`
- [ ] Renderer selection inputs (MUST)
  - [ ] Renderer selection MUST:
    - [ ] Prefer `ContextKeys::HTTP_RESPONSE_FORMAT` (read-only)
    - [ ] Fallback to `Accept` header ONLY for selection, never printed/logged
    - [ ] Never include raw Accept in ErrorDescriptor/logs/metrics (hash/len only)
  - [ ] No compile-time dependency on `platform/http` is introduced by this: it reads only ContextStore/ContextAccessor port.
- [ ] What problem this epic solves:
  - [ ] Додає deterministic HTML error pages (без view engine) і selector рендерера (json/html).
  - [ ] Інтегрується з negotiation через `ContextKeys::HTTP_RESPONSE_FORMAT` (preferred) і fallback на Accept (якщо negotiation вимкнено).
- [ ] Non-goals / out of scope:
  - [ ] Не додає темізацію/брендинг (мінімальний HTML).
  - [ ] Не робить side-effects або extra logging з payload.
- [ ] Cutline impact: none
- [ ] Phase 0 integration lock (MUST):
  - [ ] Renderer selection MUST:
    - [ ] prefer `ContextKeys::HTTP_RESPONSE_FORMAT`,
    - [ ] fallback: Accept header (redacted; never printed).
  - [ ] Redaction policy:
    - [ ] no secrets/PII in HTML, no stacktrace in prod.
