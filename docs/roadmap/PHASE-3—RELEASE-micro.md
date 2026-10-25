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

- [ ] `packages/platform/errors/README.md`
  - [ ] describe canonical error normalization and contracts
  - [ ] describe module selection and dependency closure
  - [ ] document mapper registration and deterministic ordering
  - [ ] document safe fallback behavior
  - [ ] document config keys and defaults
  - [ ] document redaction ownership and mandatory fail-closed behavior
  - [ ] document Context and UoW boundaries
  - [ ] document reporter, logger, tracer, and meter behavior
  - [ ] document CLI/HTTP/Worker adapter ownership
  - [ ] document errors, tests, and architectural restrictions

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

- [ ] `docs/adr/ADR-0036-platform-errors-errorhandler-exception-mapper.md`
  - [ ] document the canonical contracts-level normalization boundary
  - [ ] document why TagRegistry remains the only mapper discovery authority
  - [ ] document why the default mapper is outside the tag registry
  - [ ] document use of the existing redaction port
  - [ ] document reuse of the Foundation Noop reporter
  - [ ] document the fail-closed handler behavior
  - [ ] document the absence of a package-local reset mechanism
  - [ ] document runtime module composition
  - [ ] document rejected duplicate redaction, reporter, registry, and adapter implementations

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

- [ ] `docs/ssot/modes.md`
  - [ ] update the declared `platform.cli` dependency closure to include `platform.errors`
  - [ ] update the effective enabled-module sets for `micro`, `express`, `hybrid`, and `enterprise`
  - [ ] update all target-local ModulePlan examples and project-wide module union examples where `platform.cli` is selected
  - [ ] include `platform.errors` transitively in every affected enabled-module set
  - [ ] preserve canonical byte-order sorting for enabled-module sets
  - [ ] preserve existing dependency-topological ordering rules independently from enabled-set sorting
  - [ ] preserve the existing preset root selections
  - [ ] document that `platform.errors` is selected transitively, not as a new preset root
  - [ ] document the fail-closed behavior when a selected CLI module is missing its Errors dependency

- [ ] `docs/ssot/application-dependency-sync.md`
  - [ ] update the canonical installation-catalog module table:
    - [ ] add `platform.errors` with its declared dependencies
    - [ ] add `platform.errors` to the declared `platform.cli` dependency set
  - [ ] update the Composer baseline dependency graph:
    - [ ] `coretsia/platform-cli` directly requires `coretsia/platform-errors`
    - [ ] `coretsia/platform-errors` directly requires `coretsia/platform-redaction`
  - [ ] update the effective Console `ModulePlan` example to include `platform.errors`
  - [ ] update both `web` and `worker` target-local dependency-closure examples
  - [ ] update the project-wide physical module union example
  - [ ] include `coretsia/platform-errors` in the example of desired Composer package identities
  - [ ] preserve the distinction between transitive Composer installation, target-local module enablement, and managed root requirements
  - [ ] preserve canonical plan/review/apply ownership
  - [ ] do not introduce implicit package installation during boot

- [ ] `packages/framework/README.md`
  - [ ] document that framework baseline installation includes `platform/errors` through CLI dependency closure
  - [ ] preserve the existing direct framework requirements and module-selection policy

- [ ] `packages/applications/skeleton/README.md`
  - [ ] update default `micro` installation and Console ModulePlan documentation
  - [ ] include the transitive `platform.errors` dependency
  - [ ] update the documented `web → micro` and `worker → enterprise` target-local module closures
  - [ ] include `platform.errors` in the documented project-wide physical module union
  - [ ] preserve the existing preset root selections
  - [ ] preserve the distinction between physical installation and runtime module enablement
  - [ ] preserve source-host boot without precompiled artifacts

- [ ] `packages/platform/cli/README.md`
  - [ ] replace references to legacy `CliErrorHandler` with the canonical `ErrorHandlerInterface` flow
  - [ ] document CLI-owned `CliExceptionMapper` and `error.mapper` registration
  - [ ] document retained CLI public fallback mapping
  - [ ] document updated Composer/module dependency closure
  - [ ] preserve the documented command, output, error-code, and exit-code contracts

- [ ] `docs/ssot/error-descriptor.md`
  - [ ] document `ErrorDescriptor::fromArray()` as the canonical strict reconstruction API
  - [ ] preserve existing descriptor field meanings and invariants
  - [ ] reconstruction MUST NOT perform sensitive-data classification or redaction
  - [ ] reconstruction MUST NOT replace mapper-owned safe-by-construction derivation

- [ ] `docs/ssot/errors-boundary.md`
  - [ ] document concrete `platform/errors` normalization flow
  - [ ] document canonical mapper registry dispatch and fallback policy
  - [ ] document the existing `ErrorHandlerInterface` binding ownership
  - [ ] document `CORETSIA_UNHANDLED_EXCEPTION` fallback code
  - [ ] document mapper failure isolation without a second exception hierarchy
  - [ ] document the canonical `platform/errors` runtime handler guarantee
  - [ ] preserve the implementation-neutral `ErrorHandlerInterface` signature
  - [ ] document CLI consumption through the canonical port and CLI-owned mapper registration
  - [ ] clarify that runtime adapters own presentation, while their packages MAY contribute owner-specific `ExceptionMapperInterface` services through the canonical `error.mapper` tag
  - [ ] contributing an owner-specific mapper MUST NOT transfer mapper discovery, ordering, fallback selection, or global handler ownership away from `platform/errors`
  - [ ] CLI-owned exception classification MUST remain separate from CLI transport rendering
  - [ ] no runtime adapter may introduce a second Throwable-to-ErrorDescriptor dispatch pipeline
  - [ ] preserve transport-specific rendering and failure-boundary ownership
  - [ ] preserve `ErrorDescriptor` and runtime adapter ownership boundaries
  - [ ] MUST NOT introduce a competing descriptor schema

- [ ] `docs/ssot/tags.md`
  - [ ] retain the existing `error.mapper` registry row
  - [ ] document mapper service-id and priority semantics
  - [ ] document empty metadata and lazy resolution requirements
  - [ ] document first-non-null descriptor selection
  - [ ] document that the default fallback mapper is not tagged
  - [ ] MUST NOT create a second tag row or another reserved identifier

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

- [ ] `docs/ssot/config-roots.md`
  - [ ] add exactly one root row:
    - [ ] root: `errors`
    - [ ] owner: `platform/errors`
    - [ ] defaults: `packages/platform/errors/config/errors.php`
    - [ ] rules: `packages/platform/errors/config/rules.php`
  - [ ] do not redefine an existing config root

- [x] `docs/architecture/DEPENDENCIES.md`
  - [x] add the materialized `platform/errors` package to the canonical dependency matrix
  - [x] direct source dependencies: `core/contracts`, `core/foundation`
  - [x] preserve `platform/redaction` as a runtime-only Composer dependency
  - [x] MUST NOT add a source dependency on `platform/redaction`
  - [x] document the direct Composer-only dependency from `platform/cli` to `platform/errors`
  - [x] preserve the CLI source dependency boundary through `core/contracts` and `core/foundation`
  - [x] MUST NOT add `platform/errors` to the CLI source import allowlist solely because CLI consumes the canonical port
  - [x] preserve Worker source and Composer dependencies unchanged

- [ ] `docs/adr/INDEX.md`
  - [ ] register `ADR-0036-platform-errors-errorhandler-exception-mapper.md`

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
- [ ] Failure diagnostics:
  - [x] mapper failures use fixed safe reason tokens
  - [x] no new `MapperFailedException` is required
  - [x] no new global error-code registry
  - [x] no exception chaining or previous Throwable retention
  - [x] internal mapper failures MUST NOT expose raw errors through public descriptors
  - [ ] stable normalized fallback codes are documented in `docs/ssot/errors-boundary.md`
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
- [ ] Safe-by-construction mapping:
  - [x] mapper codes MUST be stable and non-sensitive
  - [x] mapper messages MUST be fixed owner-approved public messages
  - [x] mapper messages MUST NOT interpolate Throwable messages, arbitrary input values, environment data, filesystem paths, SQL, or runtime context values
  - [x] dynamic sensitive values MUST NOT be embedded inside otherwise safe-looking message strings
  - [x] mapper codes MUST be stable owner-defined identifiers, not dynamically derived from input or Throwable data
  - [x] unknown or unclassified values MUST NOT be treated as safe merely because shared redaction did not match them
  - [x] enabled mapper implementations are trusted contributors bound by the existing ExceptionMapperInterface safety contract
  - [x] the handler MUST NOT introduce a duplicate free-text secret scanner, taint engine, or sensitive-value classifier
  - [ ] protection against contract-violating mapper implementations MUST be covered by mapper-owner security tests and review, not assumed from generic redaction alone
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
- [ ] MUST NOT leak:
  - [ ] Authorization headers
  - [ ] Cookie and session identifiers
  - [ ] credentials, passwords, tokens, and private keys
  - [ ] environment secrets
  - [ ] raw SQL or query payloads
  - [ ] request/response bodies
  - [x] raw exception messages and stack traces
  - [ ] private customer data and PII
  - [x] absolute local paths
  - [x] arbitrary runtime context metadata
- [ ] Allowed safe derivations:
  - [x] stable non-sensitive error codes
  - [x] fixed safe reason tokens
  - [ ] bounded counts
  - [ ] safe lengths where owner policy permits disclosure
  - [ ] safe hashes only where non-reconstruction and disclosure policy permit
  - [ ] validated opaque identifiers only in owner-approved context channels
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
  - [ ] deterministic rerun-no-diff checks green

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [x] Implementation complete
- [x] Tests green
- [x] Gates/Arch green
- [ ] Docs complete
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
- [ ] README sections complete
- [ ] Determinism: rerun-no-diff (where applicable)

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

goal: "У будь-якому preset LoggerInterface доступний і кожен log record має correlation/uow поля без витоку секретів."
provides:
- "Psr\\Log\\LoggerInterface завжди resolvable (мінімум NullLogger)"
- "ContextBagProcessor додає correlation_id/uow_id/uow_type (та safe request context за наявності)"
- "Deterministic JSON line форматтер зі стабільним порядком полів"
- "Redaction policy: no secrets/PII (no headers/cookies/body/.env values)"
- "Cutline impact: blocks Phase 3 platform baseline cutline"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-XXXX-platform-logging-psr3.md"
ssot_refs:
- "docs/ssot/logging-policy.md"
---

- [ ] update `3.20.0 Platform logging` to consume `SensitiveDataRedactorInterface` for log record redaction.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - `logging.*` — config root used by this package (driver/stream/level/redaction)

- Required config roots registry:
  - `logging` MUST already be reserved in `docs/ssot/config-roots.md` with owner `platform/logging`.
  - This epic adds/implements package-owned defaults and rules under the existing root; it does not introduce a new root.

- Required tags:
  - N/A

- Required contracts / ports:
  - `Psr\Log\LoggerInterface` — primary port
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — optional enrichment only
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — optional enrichment only
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — context source (stable API)
  - `Coretsia\Contracts\Context\ContextKeys` — reserved keys

- tighten to cemented APIs
  - `Coretsia\Contracts\Context\ContextAccessorInterface::get(string $key): mixed` MUST be used without a default param.
  - Missing key handling MUST be non-throwing (log record must still be emitted).

- Deterministic JSON formatter (MUST)
  - `JsonLineFormatter` MUST serialize via Foundation stable encoder:
    - `Coretsia\Foundation\Serialization\StableJsonEncoder`
  - Map keys MUST be recursively sorted by `strcmp` (byte-order), lists preserve order.
  - Floats MUST NOT be emitted:
    - durations/timestamps MUST be ints or strings (e.g. `duration_ms: int`, `ts_utc: string` ISO-8601).

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http`
- `platform/errors`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)
- Foundation stable APIs:
  - `Coretsia\Contracts\Context\ContextKeys`

### Entry points / integration points (MUST)

- CLI:
  - N/A (used implicitly by commands elsewhere)
- HTTP:
  - used by owner middleware `platform/http`:
    - `\Coretsia\Http\Middleware\AccessLogMiddleware::class` (system_pre ~910)
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `packages/platform/logging/src/Module/LoggingModule.php` — module entry (runtime)
- [ ] `packages/platform/logging/src/Provider/LoggingServiceProvider.php` — DI wiring
- [ ] `packages/platform/logging/src/Provider/LoggingServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `packages/platform/logging/config/logging.php` — config subtree (no repeated root)
- [ ] `packages/platform/logging/config/rules.php` — config shape rules
- [ ] `packages/platform/logging/README.md` — docs (Observability / Errors / Security-Redaction)

- [ ] `packages/platform/logging/src/LoggerFactory.php` — builds PSR-3 logger; default stream → `php://stderr`
- [ ] `packages/platform/logging/src/Processor/ContextBagProcessor.php` — appends safe context keys
  - [ ] MUST NOT append raw `ContextKeys::PATH`, `HOST`, or `USER_AGENT` values directly
  - [ ] if request-context enrichment is enabled, those values MUST be redacted/normalized
    (for example `hash/len`, or `path_template` when already available from context)
  - [ ] default baseline enrichment MUST remain low-risk:
    - [ ] `correlation_id`
    - [ ] `uow_id`
    - [ ] `uow_type`
- [ ] `packages/platform/logging/src/Redaction/LogRedactor.php` — blocks secrets/PII in metadata
- [ ] `packages/platform/logging/src/Formatter/JsonLineFormatter.php` — deterministic field order

- [ ] `packages/platform/logging/src/Exception/LoggingWriteFailedException.php` — (`CORETSIA_LOG_WRITE_FAILED`)

- [ ] `packages/platform/logging/tests/Contract/LoggingDoesNotLogSecretsContractTest.php` — contract proof (no secrets)
- [ ] `packages/platform/logging/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — cross-cutting proof
- [ ] `packages/platform/logging/tests/Unit/JsonLineFormatterDeterministicOrderTest.php` — deterministic order proof
- [ ] `packages/platform/logging/tests/Integration/CorrelationIdIsAlwaysPresentInLogsTest.php` — context proof
- [ ] `packages/platform/logging/tests/Integration/NullLoggerResolvesWhenDisabledTest.php` — disable behavior proof

- [ ] `docs/ssot/logging-policy.md` — redaction rules + allowed fields + examples (AccessLog-friendly)

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/logging-policy.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-platform-logging-psr3.md`

#### Package skeleton (if type=package)

- [ ] `packages/platform/logging/composer.json`
- [ ] `packages/platform/logging/src/Module/LoggingModule.php`
- [ ] `packages/platform/logging/src/Provider/LoggingServiceProvider.php`
- [ ] `packages/platform/logging/config/logging.php`
- [ ] `packages/platform/logging/config/rules.php`
- [ ] `packages/platform/logging/README.md`
- [ ] `packages/platform/logging/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/logging/config/logging.php`
- [ ] Keys (dot):
  - [ ] `logging.enabled` = true
  - [ ] `logging.driver` = 'stream'
  - [ ] `logging.stream` = 'php://stderr'
  - [ ] `logging.level` = 'info'
  - [ ] `logging.redaction.enabled` = true
- [ ] Rules:
  - [ ] `packages/platform/logging/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] binds `Psr\Log\LoggerInterface` → factory result (or NullLogger)
  - [ ] ensures `ContextBagProcessor` attached deterministically

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
  - [ ] (optional) `ContextKeys::CLIENT_IP`, `SCHEME`, `HOST`, `PATH`, `USER_AGENT`
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] expected stateless; if state introduced → implement `ResetInterface` + tag `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `logging.write` (attrs: `driver`, `outcome`)
- [ ] Metrics:
  - [ ] `logging.write_total` (labels: `driver`, `outcome`)
  - [ ] `logging.write_duration_ms` (labels: `driver`, `outcome`)
- [ ] Logs:
  - [ ] never log Authorization/Cookie/session id/tokens/raw payload/raw SQL
  - [ ] if reference needed: only `hash/len`

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Logging\Exception\LoggingWriteFailedException` — errorCode `CORETSIA_LOG_WRITE_FAILED`
    - [ ] `LoggingWriteFailedException` is internal-only:
      - [ ] public `Psr\Log\LoggerInterface` calls MUST NOT surface this exception to callers
      - [ ] write failures MUST degrade deterministically to `NullLogger` / noop-safe behavior
      - [ ] runtime MUST remain no-throw from the caller perspective
- [ ] Mapping:
  - [ ] failures must not crash runtime; fallback to NullLogger / noop-safe behavior with no stateful "warn once" requirement

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/raw payload/raw SQL
  - [ ] raw request path / raw host / raw user-agent via generic logging enrichment
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id` only if already safe elsewhere)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `N/A` (no Context writes)
- [ ] If `kernel.reset` used → `N/A` (not expected)
- [ ] If metrics/spans/logs exist → `packages/platform/logging/tests/Integration/CorrelationIdIsAlwaysPresentInLogsTest.php`
- [ ] If redaction exists → `packages/platform/logging/tests/Contract/LoggingDoesNotLogSecretsContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] `packages/platform/logging/tests/Unit/JsonLineFormatterDeterministicOrderTest.php`
- Contract:
  - [ ] `packages/platform/logging/tests/Contract/LoggingDoesNotLogSecretsContractTest.php`
  - [ ] `packages/platform/logging/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `packages/platform/logging/tests/Integration/CorrelationIdIsAlwaysPresentInLogsTest.php`
  - [ ] `packages/platform/logging/tests/Integration/NullLoggerResolvesWhenDisabledTest.php`
- Gates/Arch:
  - [ ] deptrac green (no forbidden deps)

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] LoggerInterface always resolvable
- [ ] ContextBagProcessor attaches correlation/uow keys
- [ ] No secrets leakage (tests)
- [ ] README sections complete
- [ ] Any mention like “used by owner middleware platform/http” is OK as documentation, BUT tests and compile-time deps MUST NOT reference platform/http symbols.

---

### 3.30.0 Platform tracing baseline (noop-safe + W3C propagation) (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.30.0"
owner_path: "packages/platform/tracing/"

package_id: "platform/tracing"
composer: "coretsia/platform-tracing"
kind: runtime
module_id: "platform.tracing"

goal: "У будь-якому runtime виклики tracer/propagation ніколи не кидають і дають deterministic behavior."
provides:
- "Noop-safe TracerPortInterface + SpanInterface (без if(enabled) у callers)"
- "Deterministic W3C tracecontext propagation (extract/inject) для HTTP middleware (owner `platform/http`)"
- "Callers-safe baseline: tracer/propagation завжди resolvable і no-throw (noop за замовчуванням)"
- "Cutline impact: blocks Phase 3 platform baseline cutline"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-XXXX-platform-tracing-metrics-w3c-propagation.md"
ssot_refs:
- "docs/ssot/tracecontext.md"
---

- [ ] update `3.30.0 Platform tracing baseline` to consume `SensitiveDataRedactorInterface` for span attribute summaries where redaction is needed.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - `tracing.*` — config root used by tracing

- Required config roots registry:
  - `tracing` MUST already be reserved in `docs/ssot/config-roots.md` with owner `platform/tracing`.
  - This epic adds/implements package-owned defaults and rules under the existing root; it does not introduce a new root.

- Required tags:
  - N/A

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Tracing\SpanInterface`
  - `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`
  - `Coretsia\Contracts\Observability\Tracing\SpanExporterInterface`
  - `Coretsia\Contracts\Observability\Tracing\SamplerInterface`
  - `Psr\Log\LoggerInterface` (optional warn-only)
  - `Coretsia\Contracts\Context\ContextKeys` (optional; if used)

- tighten to cemented APIs
  - `Coretsia\Contracts\Context\ContextAccessorInterface::get(string $key): mixed` MUST be used without a default param.
  - Missing key handling MUST remain non-throwing for this epic’s runtime path.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Tracing\SpanInterface`
  - `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`
  - `Coretsia\Contracts\Observability\Tracing\SpanExporterInterface`
  - `Coretsia\Contracts\Observability\Tracing\SamplerInterface`
- Foundation stable APIs:
  - `Coretsia\Contracts\Context\ContextKeys` (optional)

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - used by `platform/http` middlewares (owner=http):
    - `\Coretsia\Http\Middleware\TraceContextMiddleware::class` (system_pre ~930) uses `ContextPropagationInterface`
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

Naming lock (MUST)
- This epic uses neutral platform-local names to avoid cross-package collisions:
  - `Default/DefaultTracer.php`
  - `Default/DefaultSpan.php`
- This epic text does not use platform-local `NoopTracer/NoopSpan` names.

#### Creates

- [ ] `packages/platform/tracing/src/Module/TracingModule.php` — module entry
- [ ] `packages/platform/tracing/src/Provider/TracingServiceProvider.php` — DI wiring
- [ ] `packages/platform/tracing/src/Provider/TracingServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `packages/platform/tracing/config/tracing.php` — config subtree
- [ ] `packages/platform/tracing/config/rules.php` — config rules
- [ ] `packages/platform/tracing/README.md` — docs

- [ ] `packages/platform/tracing/src/Default/DefaultTracer.php` — TracerPortInterface
- [ ] `packages/platform/tracing/src/Default/DefaultSpan.php` — SpanInterface
- [ ] `packages/platform/tracing/src/W3c/W3cTraceContextPropagation.php` — ContextPropagationInterface (deterministic, no-throw)
- [ ] `packages/platform/tracing/src/Noop/NullSpanExporter.php` — SpanExporterInterface
- [ ] `packages/platform/tracing/src/Sampling/AlwaysOffSampler.php` — SamplerInterface (Phase 0 default; no-throw)

- [ ] `packages/platform/tracing/tests/Unit/W3cPropagationExtractInjectDeterministicTest.php`
- [ ] `packages/platform/tracing/tests/Contract/NoopNeverThrowsContractTest.php`
- [ ] `packages/platform/tracing/tests/Contract/W3CPropagationDeterministicContractTest.php`
- [ ] `packages/platform/tracing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `packages/platform/tracing/tests/Integration/W3cPropagationMissingHeadersNoThrowTest.php`

Docs:
- [ ] `docs/ssot/tracecontext.md` — W3C propagation rules + redaction + “no labels for trace ids”

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/tracecontext.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-platform-tracing-metrics-w3c-propagation.md`

#### Package skeleton (if type=package)

- [ ] `packages/platform/tracing/composer.json`
- [ ] `packages/platform/tracing/src/Module/TracingModule.php`
- [ ] `packages/platform/tracing/src/Provider/TracingServiceProvider.php`
- [ ] `packages/platform/tracing/config/tracing.php`
- [ ] `packages/platform/tracing/config/rules.php`
- [ ] `packages/platform/tracing/README.md`
- [ ] `packages/platform/tracing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/tracing/config/tracing.php`
- [ ] Keys (dot):
  - [ ] `tracing.enabled` = true
  - [ ] `tracing.sampling.mode` = 'always_off'
  - [ ] `tracing.exporter` = 'null'
- [ ] Rules:
  - [ ] `packages/platform/tracing/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] binds:
    - [ ] `TracerPortInterface` → `DefaultTracer`
    - [ ] `ContextPropagationInterface` → `W3cTraceContextPropagation`
    - [ ] `SpanExporterInterface` → `NullSpanExporter`
    - [ ] `SamplerInterface` → `AlwaysOffSampler`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (span/log only; never metric label)
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] noop tracer is stateless

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `tracing.noop` (debug-only; optional)
- [ ] Metrics:
  - [ ] `tracing.export_total` (labels: `outcome=skipped|ok|fail`)
  - [ ] `tracing.export_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] never log trace headers or raw payloads; only safe meta + hashes/len

#### Errors

- Exceptions introduced:
  - N/A (normative: W3C extract/inject MUST be no-throw; invalid headers → ignore + deterministic outcome)
- [ ] Mapping:
  - [ ] prefer noop/skip over throwing (all phases)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/raw SQL/payload
  - [ ] trace headers in logs (raw)
- [ ] Allowed:
  - [ ] safe ids in span/log fields, `hash/len`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `N/A`
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist →
  - [ ] `packages/platform/tracing/tests/Contract/NoopNeverThrowsContractTest.php`
  - [ ] `packages/platform/tracing/tests/Contract/W3CPropagationDeterministicContractTest.php`

#### Test harness / fixtures (when integration is needed)

- N/A (integration tests use minimal middleware harness)

### Tests (MUST)

- Unit:
  - [ ] `packages/platform/tracing/tests/Unit/W3cPropagationExtractInjectDeterministicTest.php`
- Contract:
  - [ ] `packages/platform/tracing/tests/Contract/NoopNeverThrowsContractTest.php`
  - [ ] `packages/platform/tracing/tests/Contract/W3CPropagationDeterministicContractTest.php`
  - [ ] `packages/platform/tracing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `packages/platform/tracing/tests/Integration/W3cPropagationMissingHeadersNoThrowTest.php`
- Gates/Arch:
  - [ ] deptrac green

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] TracerPortInterface + ContextPropagationInterface always resolvable
- [ ] W3C propagation deterministic and no-throw
- [ ] README sections complete

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

goal: "У будь-якому runtime виклики meter/renderer ніколи не кидають і дають deterministic behavior (label allowlist enforced)."
provides:
- "Noop-safe MeterPortInterface (без if(enabled) у callers)"
- "MetricsRendererInterface baseline (null renderer у Phase 3 baseline)"
- "Label allowlist enforcement even in noop"
- "Callers-safe baseline: meter/renderer завжди resolvable і no-throw (noop за замовчуванням)"
- "Cutline impact: blocks Phase 3 platform baseline cutline"

tags_introduced: []

config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-XXXX-platform-tracing-metrics-w3c-propagation.md"
ssot_refs:
- "docs/ssot/metrics-policy.md"
- "docs/ssot/observability.md"
---

- [ ] update `3.35.0 Platform metrics baseline` to clarify that redaction does not permit arbitrary metric labels; metrics remain allowlist-only.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - `metrics.*` — config root used by metrics

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

- Required tags:
  - N/A

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface`
  - `Psr\Log\LoggerInterface` (optional warn-only)
  - `Coretsia\Contracts\Context\ContextKeys` (optional; if used)

- tighten to cemented APIs
  - `Coretsia\Contracts\Context\ContextAccessorInterface::get(string $key): mixed` MUST be used without a default param.
  - Missing key handling MUST remain non-throwing for this epic’s runtime path.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface`
- Foundation stable APIs:
  - `Coretsia\Contracts\Context\ContextKeys` (optional)

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - used by `platform/http` middlewares (owner=http):
    - `\Coretsia\Http\Middleware\HttpMetricsMiddleware::class` (system_pre ~920) uses `MeterPortInterface`
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

- [ ] `packages/platform/metrics/src/Module/MetricsModule.php` — module entry
- [ ] `packages/platform/metrics/src/Provider/MetricsServiceProvider.php` — DI wiring
- [ ] `packages/platform/metrics/src/Provider/MetricsServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `packages/platform/metrics/config/metrics.php` — config subtree
- [ ] `packages/platform/metrics/config/rules.php` — config rules
- [ ] `packages/platform/metrics/README.md` — docs

- [ ] `packages/platform/metrics/src/Default/DefaultMeter.php` — MeterPortInterface
- [ ] `packages/platform/metrics/src/Noop/NullMetricsRenderer.php` — MetricsRendererInterface
- [ ] `packages/platform/metrics/src/Labels/AllowedLabelSet.php` — allowlist enforcement
  - [ ] MUST enforce the fixed SSoT allowlist from `docs/ssot/observability.md`
  - [ ] MUST NOT read allowlist values from runtime/app config
- [ ] `packages/platform/metrics/src/Exception/MetricLabelForbiddenException.php` — (`CORETSIA_METRIC_LABEL_FORBIDDEN`)

- [ ] `packages/platform/metrics/tests/Unit/AllowedLabelSetRejectsForbiddenKeysTest.php`
- [ ] `packages/platform/metrics/tests/Contract/NoopNeverThrowsContractTest.php`
- [ ] `packages/platform/metrics/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `packages/platform/metrics/tests/Integration/NoopMeterRecordNoThrowTest.php`

Docs:
- [ ] `docs/ssot/metrics-policy.md` — metric naming + normalization map + examples
  - [ ] MUST NOT redefine, extend, replace, or relax the canonical label allowlist from `docs/ssot/observability.md`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/metrics-policy.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-platform-tracing-metrics-w3c-propagation.md`

#### Package skeleton (if type=package)

- [ ] `packages/platform/metrics/composer.json`
- [ ] `packages/platform/metrics/src/Module/MetricsModule.php`
- [ ] `packages/platform/metrics/src/Provider/MetricsServiceProvider.php`
- [ ] `packages/platform/metrics/config/metrics.php`
- [ ] `packages/platform/metrics/config/rules.php`
- [ ] `packages/platform/metrics/README.md`
- [ ] `packages/platform/metrics/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/metrics/config/metrics.php`
- [ ] Keys (dot):
  - [ ] `metrics.enabled` = true
  - [ ] `metrics.renderer` = 'null'
- [ ] Normative:
  - [ ] there MUST be no configurable `metrics.labels.allowed` key
  - [ ] label allowlist is fixed by `docs/ssot/observability.md`
  - [ ] `docs/ssot/metrics-policy.md` MAY document metric-specific normalization/examples only
- [ ] Rules:
  - [ ] `packages/platform/metrics/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] binds:
    - [ ] `MeterPortInterface` → `DefaultMeter`
    - [ ] `MetricsRendererInterface` → `NullMetricsRenderer`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::UOW_TYPE` (normalized → `operation`) (optional; if you ever map UoW into metrics)
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] noop meter is stateless

#### Observability (policy-compliant)

- [ ] Metrics:
  - [ ] `metrics.record_total` (labels: `operation=record`, `outcome=ok|noop`)
  - [ ] `metrics.record_duration_ms` (labels: `operation`, `outcome`)
- [ ] Logs:
  - [ ] never log raw payloads; only safe meta + hashes/len
- [ ] Label normalization applied (if needed):
  - [ ] `uow_type→operation` (when used)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Metrics\Exception\MetricLabelForbiddenException` — errorCode `CORETSIA_METRIC_LABEL_FORBIDDEN`
    - [ ] `MetricLabelForbiddenException` is internal-only:
      - [ ] public `MeterPortInterface` calls MUST NOT surface this exception to callers
      - [ ] forbidden labels MUST degrade deterministically to noop/skip behavior
      - [ ] runtime MUST remain no-throw from the caller perspective
- [ ] Mapping:
  - [ ] label violations MUST be enforced deterministically; callers MUST NOT crash runtime

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/raw SQL/payload
  - [ ] raw path as metric label; correlation_id/request_id as metric label
- [ ] Allowed:
  - [ ] safe normalized labels (allowlist), `hash/len` when needed

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `N/A`
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist →
  - [ ] `packages/platform/metrics/tests/Contract/NoopNeverThrowsContractTest.php`
  - [ ] `packages/platform/metrics/tests/Unit/AllowedLabelSetRejectsForbiddenKeysTest.php`
- [ ] If redaction exists → covered by SSoT “no labels for trace ids / request ids” + allowlist enforcement tests above

#### Test harness / fixtures (when integration is needed)

- N/A (integration tests use minimal middleware harness)

### Tests (MUST)

- Unit:
  - [ ] `packages/platform/metrics/tests/Unit/AllowedLabelSetRejectsForbiddenKeysTest.php`
- Contract:
  - [ ] `packages/platform/metrics/tests/Contract/NoopNeverThrowsContractTest.php`
  - [ ] `packages/platform/metrics/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `packages/platform/metrics/tests/Integration/NoopMeterRecordNoThrowTest.php`
- Gates/Arch:
  - [ ] deptrac green

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] MeterPortInterface + MetricsRendererInterface always resolvable
- [ ] Label allowlist enforced (even in noop)
- [ ] No package-local allowlist drift:
  - [ ] runtime enforcement uses the canonical allowlist from `docs/ssot/observability.md`
  - [ ] `docs/ssot/metrics-policy.md` does not redefine label ownership or expand label keys
- [ ] README sections complete

---

### 3.40.0 Platform problem-details (RFC7807) (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.40.0"
owner_path: "packages/platform/problem-details/"

package_id: "platform/problem-details"
composer: "coretsia/platform-problem-details"
kind: runtime
module_id: "platform.problem-details"

goal: "Будь-який Throwable у HTTP перетворюється на deterministic RFC7807 response без витоку секретів."
provides:
- "Єдиний HTTP error rendering формат: RFC7807 `application/problem+json` з deterministic payload"
- "Outermost middleware ловить Throwable і делегує в `ErrorHandlerInterface` (format-neutral)"
- "Redaction policy: жодних секретів/PII у problem response та логах"
- "Phase 3 baseline: лише JSON RFC7807 (HTML pages — окремий epic 3.190.0 SHOULD)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-XXXX-platform-problem-details-rfc7807.md"
ssot_refs:
- "docs/ssot/error-descriptor.md"
- "docs/ssot/tags.md"
---

- [ ] update `3.40.0 Platform problem-details` to consume `SensitiveDataRedactorInterface` for safe `detail`, `extensions`, and debug/local-only diagnostics.
- [ ] update `3.40.0 Platform problem-details` to consume the canonical `ErrorHandlerInterface` from `coretsia/platform-errors` once at the outer HTTP Throwable boundary, add matching direct Composer and `platform.errors` module requirements, and adapt only its normalized `ErrorDescriptor` to RFC7807 without concrete `platform/errors` source imports or duplicate mapping.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - `problem_details.*` — config root used by this package

- Required config roots registry:
  - `problem_details` MUST already be reserved in `docs/ssot/config-roots.md` with owner `platform/problem-details`.
  - This epic adds/implements package-owned defaults and rules under the existing root; it does not introduce a new root.

- Required tags:
  - `http.middleware.system_pre` (owner: platform/http) is needed for wiring.

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\ResponseFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`
  - `Psr\Log\LoggerInterface` (optional; redacted only)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http`
- `platform/errors` (direct; allowed only via contracts)
- `core/kernel`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\ResponseFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`
  - `Psr\Log\LoggerInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - middleware: `\Coretsia\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware::class`
  - middleware slots/tags: `http.middleware.system_pre` priority `1000` (meta per owner `platform/http` tag schema, if any)
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

Normative:
- [ ] Non-owner packages MUST NOT define public owner-like constants for tags they do not own.
- [ ] Because owner package `platform/http` is a forbidden compile-time dependency here, runtime wiring SHOULD use a package-local internal mirror constant.
  - [ ] The canonical string literal `'http.middleware.system_pre'` is allowed only as a fallback.
- [ ] Any package-local mirror constant used here:
  - [ ] MUST be package-internal only
  - [ ] MUST equal the canonical tag string exactly
  - [ ] MUST NOT be treated as public API

#### Creates

- [ ] `packages/platform/problem-details/src/Module/ProblemDetailsModule.php` — module entry
- [ ] `packages/platform/problem-details/src/Provider/ProblemDetailsServiceProvider.php` — DI wiring
- [ ] `packages/platform/problem-details/src/Provider/ProblemDetailsServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).

- [ ] `packages/platform/problem-details/config/problem_details.php` — config subtree
- [ ] `packages/platform/problem-details/config/rules.php` — config rules
- [ ] `packages/platform/problem-details/README.md` — docs (Observability / Errors / Security-Redaction)
- [ ] `packages/platform/problem-details/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — cross-cutting proof

- [ ] `packages/platform/problem-details/src/ProblemDetails.php` — VO (RFC7807 fields)
- [ ] `packages/platform/problem-details/src/Renderer/ProblemDetailsRendererInterface.php` — package-local renderer port
- [ ] `packages/platform/problem-details/src/Renderer/ProblemDetailsJsonRenderer.php` — deterministic JSON + content-type
  - [ ] MUST serialize via `Coretsia\Foundation\Serialization\StableJsonEncoder` (single-choice runtime writer)
  - [ ] MUST recursively sort map keys by `strcmp`; lists preserve order
  - [ ] MUST NOT emit floats/NaN/INF
  - [ ] MUST NOT use ad-hoc `json_encode` directly in renderer logic
- [ ] `packages/platform/problem-details/src/Adapter/ErrorDescriptorToProblemDetailsAdapter.php` — ErrorDescriptor → RFC7807 mapping
- [ ] `packages/platform/problem-details/src/Http/Middleware/HttpErrorHandlingMiddleware.php` — PSR-15 outer wrapper
- [ ] `packages/platform/problem-details/src/Security/Redaction.php` — redaction helpers (hash/len, safe fields)
- [ ] `packages/platform/problem-details/src/Exception/ProblemDetailsRenderFailedException.php` — (`CORETSIA_PROBLEM_DETAILS_RENDER_FAILED`)

- [ ] `packages/platform/problem-details/tests/Unit/JsonRendererDeterministicOrderTest.php`
- [ ] `packages/platform/problem-details/tests/Contract/ProblemDetailsJsonSchemaContractTest.php`
- [ ] `packages/platform/problem-details/tests/Integration/HttpErrorMiddlewareReturnsRfc7807Test.php`
- [ ] `packages/platform/problem-details/tests/Integration/DetailHiddenInProductionTest.php`

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-XXXX-platform-problem-details-rfc7807.md`

#### Package skeleton (if type=package)

- [ ] `packages/platform/problem-details/composer.json`
- [ ] `packages/platform/problem-details/src/Module/ProblemDetailsModule.php`
- [ ] `packages/platform/problem-details/src/Provider/ProblemDetailsServiceProvider.php`
- [ ] `packages/platform/problem-details/config/problem_details.php`
- [ ] `packages/platform/problem-details/config/rules.php`
- [ ] `packages/platform/problem-details/README.md`
- [ ] `packages/platform/problem-details/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `packages/platform/problem-details/config/problem_details.php`
- [ ] Keys (dot):
  - [ ] `problem_details.enabled` = true
  - [ ] `problem_details.show_detail` = false
  - [ ] `problem_details.show_detail_in_local` = true
  - [ ] `problem_details.redaction.enabled` = true
- [ ] Rules:
  - [ ] `packages/platform/problem-details/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (contributes to `http.middleware.system_pre`, but tag owner is `platform/http`)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware`
  - [ ] adds tag: `http.middleware.system_pre` priority `1000`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (logs only; never metric labels)
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] expected none

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `http.error.render` (attrs: `outcome`, `status`)
- [ ] Metrics:
  - [ ] `http.error_total` (labels: `status`, `outcome`)
  - [ ] `http.error_duration_ms` (labels: `status`, `outcome`)
- [ ] Logs:
  - [ ] handled_error/fatal_error summary (no stacktrace in prod, no secrets)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\ProblemDetails\Exception\ProblemDetailsRenderFailedException` — errorCode `CORETSIA_PROBLEM_DETAILS_RENDER_FAILED`
- [ ] Mapping:
  - [ ] uses `ErrorHandlerInterface` (port) and never duplicates mapping logic

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/raw SQL/payload/stacktrace (prod)
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id` if already safe elsewhere)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `N/A`
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist → integration tests must assert deterministic body + no secrets
- [ ] If redaction exists →
  - [ ] `packages/platform/problem-details/tests/Integration/DetailHiddenInProductionTest.php`
  - [ ] `packages/platform/problem-details/tests/Contract/ProblemDetailsJsonSchemaContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A (PSR-15 middleware can be tested via unit/integration harness)

### Tests (MUST)

- Unit:
  - [ ] `packages/platform/problem-details/tests/Unit/JsonRendererDeterministicOrderTest.php`
- Contract:
  - [ ] `packages/platform/problem-details/tests/Contract/ProblemDetailsJsonSchemaContractTest.php`
  - [ ] `packages/platform/problem-details/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `packages/platform/problem-details/tests/Integration/HttpErrorMiddlewareReturnsRfc7807Test.php`
  - [ ] `packages/platform/problem-details/tests/Integration/DetailHiddenInProductionTest.php`
- Gates/Arch:
  - [ ] deptrac green (no forbidden deps)

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] Middleware wired via tag `http.middleware.system_pre` priority 1000
- [ ] Non-owner tag usage stays compliant:
  - [ ] no public owner-like tag API is introduced in `platform/problem-details`
  - [ ] runtime wiring prefers a package-local internal mirror constant
  - [ ] canonical string literal is used only as fallback
- [ ] Deterministic RFC7807 JSON output (stable order)
- [ ] No secrets/PII in body/logs (tests prove)
- [ ] README sections complete and accurate

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
  - [ ] `http.enabled` = true

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
  - [ ] `routing.enabled` = true
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
  - [ ] `http_app.enabled` = true
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
