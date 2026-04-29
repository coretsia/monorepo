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
owner_path: "framework/packages/platform/errors/"

package_id: "platform/errors"
composer: "coretsia/platform-errors"
kind: runtime
module_id: "platform.errors"

goal: "Будь-який Throwable завжди мапиться в deterministic ErrorDescriptor без падіння ErrorHandler."
provides:
- "Canonical flow: Throwable → ErrorDescriptor (format-neutral), без PSR-7 залежностей"
- "Deterministic ExceptionMapper registry через DI tag `error.mapper`"
- "ErrorHandler noop-safe + policy: never throws (завжди повертає ErrorDescriptor)"
- "Security redaction: detail/extensions не містять секретів/PII"
- "Cutline impact: blocks Phase 3 platform baseline cutline"
- "`Deterministic ExceptionMapper registry` означає: deterministic enumeration via TagRegistry invariants (не custom sorting)."

tags_introduced: []
config_roots_introduced:
- "errors"

artifacts_introduced: []
adr: "docs/adr/ADR-0031-platform-errors-errorhandler-exception-mapper.md"
ssot_refs:
- "docs/ssot/error-flow.md"
- "docs/ssot/error-codes.md"
- "docs/ssot/tags.md"
- "docs/ssot/config-roots.md"
- "docs/ssot/error-descriptor.md" # shape + extensions constraints
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - `errors.*` — config root used by this package (enabled/reporting/redaction policy)

- Required tags:
  - `error.mapper` MUST already be reserved in `docs/ssot/tags.md` with owner `platform/errors`.

- Tag owner implementation note (MUST):
  - This epic MUST NOT re-introduce `error.mapper` in the tag registry.
  - This epic is the owner implementation epic that introduces the canonical owner constant in
    `framework/packages/platform/errors/src/Provider/Tags.php`.

- Required config roots registry:
  - N/A at epic start.
  - This epic is the owner epic that adds root `errors` to `docs/ssot/config-roots.md`.

- Tag usage (MUST) — make ordering source-of-truth explicit
  - `ExceptionMapperRegistry` MUST enumerate mappers ONLY via:
    - `Coretsia\Foundation\Tag\TagRegistry::all(Tags::ERROR_MAPPER)`
  - Registry MUST NOT re-sort or de-duplicate results (TagRegistry already enforces):
    - order = `priority DESC, serviceId ASC (strcmp)`,
    - dedupe = `first wins`.

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface` — canonical handler port
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface` — mapper port
  - `Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface` — reporting port (optional/noop-safe impl)
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor` — normalized error payload
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — optional context source for safe enrichment / correlation reads
  - `Coretsia\Foundation\Context\ContextKeys` — reserved context keys (if context enrichment is used)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — optional tracing enrichment
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — optional metrics enrichment
  - `Psr\Log\LoggerInterface` — optional logging (redacted)

- tighten to cemented APIs
  - `Coretsia\Contracts\Context\ContextAccessorInterface::get(string $key): mixed` MUST be used without a default param.
  - Missing key handling MUST remain non-throwing for this epic’s runtime path.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http`
- `platform/problem-details`
- `Psr\Http\Message\*`
- `Psr\Http\Server\*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)

### Entry points / integration points (MUST)

- CLI:
  - N/A (used indirectly by `platform/cli`)
- HTTP:
  - N/A (HTTP rendering owner = `platform/problem-details`)
- Kernel hooks/tags:
  - `error.mapper` — deterministic registry ordering via Foundation TagRegistry invariants (no package-local re-sorting)
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/errors/src/Module/ErrorsModule.php` — module entry (runtime)
- [ ] `framework/packages/platform/errors/src/Provider/ErrorsServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/errors/src/Provider/ErrorsServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/errors/src/Provider/Tags.php` — tag constants (owner)
- [ ] `framework/packages/platform/errors/config/errors.php` — config subtree (no repeated root)
- [ ] `framework/packages/platform/errors/config/rules.php` — config shape rules
- [ ] `framework/packages/platform/errors/README.md` — docs (Observability / Errors / Security-Redaction)

- [ ] `framework/packages/platform/errors/src/ErrorHandler.php` — implements ErrorHandlerInterface (never throws)
  - [ ] MUST swallow failures from optional reporter/logger/tracer/meter enrichment paths
  - [ ] final fallback path MUST still return a deterministic `ErrorDescriptor`
  - [ ] internal sink/enrichment failures MUST NOT escape `handle()`
- [ ] `framework/packages/platform/errors/src/Mapper/ExceptionMapperRegistry.php` — aggregates `error.mapper` deterministically
- [ ] `framework/packages/platform/errors/src/Mapper/DefaultExceptionMapper.php` — fallback `CORETSIA_UNHANDLED_EXCEPTION`
- [ ] `framework/packages/platform/errors/src/Reporting/ErrorReporter.php` — glue to ErrorReporterPortInterface (noop-safe)
- [ ] `framework/packages/platform/errors/src/Redaction/ErrorRedactor.php` — prevents leaking secrets in detail/extensions
- [ ] `framework/packages/platform/errors/src/Exception/MapperFailedException.php` — internal exception (`CORETSIA_ERROR_MAPPER_FAILED`)

- [ ] `framework/packages/platform/errors/tests/Contract/ErrorHandlerNeverThrowsContractTest.php` — contract proof
- [ ] `framework/packages/platform/errors/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — cross-cutting proof
- [ ] `framework/packages/platform/errors/tests/Unit/ErrorRedactorDoesNotLeakSecretsTest.php` — unit proof
- [ ] `framework/packages/platform/errors/tests/Integration/RegistryDeterministicOrderTest.php` — deterministic registry proof
- [ ] `framework/packages/platform/errors/tests/Integration/DefaultMapperCatchesAnyThrowableTest.php` — fallback proof
- [ ] `framework/packages/platform/errors/tests/Integration/ErrorDescriptorIsRedactedTest.php` — redaction proof

- [ ] `docs/ssot/error-flow.md` — canonical error flow (HTTP/CLI/worker adapters), “no PSR-7 in errors”
- [ ] `docs/ssot/error-codes.md` — baseline error codes + stability rules

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/error-flow.md`
  - [ ] `docs/ssot/error-codes.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0031-platform-errors-errorhandler-exception-mapper.md`
- [ ] `docs/ssot/config-roots.md` — add root row for `errors` (owner `platform/errors`, defaults `framework/packages/platform/errors/config/errors.php`, rules `framework/packages/platform/errors/config/rules.php`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/errors/composer.json`
- [ ] `framework/packages/platform/errors/src/Module/ErrorsModule.php`
- [ ] `framework/packages/platform/errors/src/Provider/ErrorsServiceProvider.php`
- [ ] `framework/packages/platform/errors/config/errors.php`
- [ ] `framework/packages/platform/errors/config/rules.php`
- [ ] `framework/packages/platform/errors/README.md`
- [ ] `framework/packages/platform/errors/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/errors/config/errors.php`
- [ ] Keys (dot):
  - [ ] `errors.enabled` = true
  - [ ] `errors.reporting.enabled` = false
  - [ ] `errors.redaction.enabled` = true
  - [ ] `errors.detail.show_in_local` = true
- [ ] Rules:
  - [ ] `framework/packages/platform/errors/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/errors/src/Provider/Tags.php`
  - [ ] constants:
    - [ ] `ERROR_MAPPER = 'error.mapper'`
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Errors\ErrorHandler`
  - [ ] binds: `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface` → `Coretsia\Errors\ErrorHandler`
  - [ ] registers: `Coretsia\Errors\Mapper\ExceptionMapperRegistry` (reads tag `error.mapper`)
  - [ ] registers: `Coretsia\Errors\Mapper\DefaultExceptionMapper` (lowest priority fallback)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] expected: none (no stateful services)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `errors.handle` (attrs: `outcome`, `operation=map`)
- [ ] Metrics:
  - [ ] `errors.mapped_total` (labels: `operation=map`, `outcome=ok|fallback|fail`)
  - [ ] `errors.mapped_duration_ms` (labels: `operation=map`, `outcome`)
- [ ] Logs:
  - [ ] mapper failures logged with redaction (no stacktrace in prod, no secrets)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Errors\Exception\MapperFailedException` — errorCode `CORETSIA_ERROR_MAPPER_FAILED` (internal)
- [ ] Mapping:
  - [ ] registry: `ExceptionMapperInterface` via tag `error.mapper`, deterministic order (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/raw SQL/payload/stacktrace (prod)
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id` if already safe elsewhere)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `N/A` (no Context writes)
- [ ] If `kernel.reset` used → `N/A` (expected none)
- [ ] If metrics/spans/logs exist → `framework/packages/platform/errors/tests/Integration/RegistryDeterministicOrderTest.php` + metrics/log assertions where applicable
- [ ] If redaction exists → `framework/packages/platform/errors/tests/Unit/ErrorRedactorDoesNotLeakSecretsTest.php` + `framework/packages/platform/errors/tests/Integration/ErrorDescriptorIsRedactedTest.php`

#### Test harness / fixtures (when integration is needed)

N/A (integration tests should run with minimal container wiring)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/errors/tests/Unit/ErrorRedactorDoesNotLeakSecretsTest.php`
- Contract:
  - [ ] `framework/packages/platform/errors/tests/Contract/ErrorHandlerNeverThrowsContractTest.php`
  - [ ] `framework/packages/platform/errors/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/errors/tests/Integration/RegistryDeterministicOrderTest.php`
  - [ ] `framework/packages/platform/errors/tests/Integration/DefaultMapperCatchesAnyThrowableTest.php`
  - [ ] `framework/packages/platform/errors/tests/Integration/ErrorDescriptorIsRedactedTest.php`
- Gates/Arch:
  - [ ] deptrac green (no forbidden deps)

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] ErrorHandler never throws (contract test)
- [ ] Mapper registry deterministic (integration test)
- [ ] Redaction policy enforced (tests)
- [ ] README sections complete
- [ ] Determinism: rerun-no-diff (where applicable)

---

### 3.20.0 Platform logging (PSR-3) (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.20.0"
owner_path: "framework/packages/platform/logging/"

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
adr: "docs/adr/ADR-0032-platform-logging-psr3.md"
ssot_refs:
- "docs/ssot/logging-policy.md"
---

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
  - `Coretsia\Foundation\Context\ContextKeys` — reserved keys

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
  - `Coretsia\Foundation\Context\ContextKeys`

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

- [ ] `framework/packages/platform/logging/src/Module/LoggingModule.php` — module entry (runtime)
- [ ] `framework/packages/platform/logging/src/Provider/LoggingServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/logging/src/Provider/LoggingServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/logging/config/logging.php` — config subtree (no repeated root)
- [ ] `framework/packages/platform/logging/config/rules.php` — config shape rules
- [ ] `framework/packages/platform/logging/README.md` — docs (Observability / Errors / Security-Redaction)

- [ ] `framework/packages/platform/logging/src/LoggerFactory.php` — builds PSR-3 logger; default stream → `php://stderr`
- [ ] `framework/packages/platform/logging/src/Processor/ContextBagProcessor.php` — appends safe context keys
  - [ ] MUST NOT append raw `ContextKeys::PATH`, `HOST`, or `USER_AGENT` values directly
  - [ ] if request-context enrichment is enabled, those values MUST be redacted/normalized
    (for example `hash/len`, or `path_template` when already available from context)
  - [ ] default baseline enrichment MUST remain low-risk:
    - [ ] `correlation_id`
    - [ ] `uow_id`
    - [ ] `uow_type`
- [ ] `framework/packages/platform/logging/src/Redaction/LogRedactor.php` — blocks secrets/PII in metadata
- [ ] `framework/packages/platform/logging/src/Formatter/JsonLineFormatter.php` — deterministic field order

- [ ] `framework/packages/platform/logging/src/Exception/LoggingWriteFailedException.php` — (`CORETSIA_LOG_WRITE_FAILED`)

- [ ] `framework/packages/platform/logging/tests/Contract/LoggingDoesNotLogSecretsContractTest.php` — contract proof (no secrets)
- [ ] `framework/packages/platform/logging/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — cross-cutting proof
- [ ] `framework/packages/platform/logging/tests/Unit/JsonLineFormatterDeterministicOrderTest.php` — deterministic order proof
- [ ] `framework/packages/platform/logging/tests/Integration/CorrelationIdIsAlwaysPresentInLogsTest.php` — context proof
- [ ] `framework/packages/platform/logging/tests/Integration/NullLoggerResolvesWhenDisabledTest.php` — disable behavior proof

- [ ] `docs/ssot/logging-policy.md` — redaction rules + allowed fields + examples (AccessLog-friendly)

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/logging-policy.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0032-platform-logging-psr3.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/logging/composer.json`
- [ ] `framework/packages/platform/logging/src/Module/LoggingModule.php`
- [ ] `framework/packages/platform/logging/src/Provider/LoggingServiceProvider.php`
- [ ] `framework/packages/platform/logging/config/logging.php`
- [ ] `framework/packages/platform/logging/config/rules.php`
- [ ] `framework/packages/platform/logging/README.md`
- [ ] `framework/packages/platform/logging/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/logging/config/logging.php`
- [ ] Keys (dot):
  - [ ] `logging.enabled` = true
  - [ ] `logging.driver` = 'stream'
  - [ ] `logging.stream` = 'php://stderr'
  - [ ] `logging.level` = 'info'
  - [ ] `logging.redaction.enabled` = true
- [ ] Rules:
  - [ ] `framework/packages/platform/logging/config/rules.php` enforces shape

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
- [ ] If metrics/spans/logs exist → `framework/packages/platform/logging/tests/Integration/CorrelationIdIsAlwaysPresentInLogsTest.php`
- [ ] If redaction exists → `framework/packages/platform/logging/tests/Contract/LoggingDoesNotLogSecretsContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/logging/tests/Unit/JsonLineFormatterDeterministicOrderTest.php`
- Contract:
  - [ ] `framework/packages/platform/logging/tests/Contract/LoggingDoesNotLogSecretsContractTest.php`
  - [ ] `framework/packages/platform/logging/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/logging/tests/Integration/CorrelationIdIsAlwaysPresentInLogsTest.php`
  - [ ] `framework/packages/platform/logging/tests/Integration/NullLoggerResolvesWhenDisabledTest.php`
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
owner_path: "framework/packages/platform/tracing/"

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
adr: "docs/adr/ADR-0033-platform-tracing-metrics-w3c-propagation.md"
ssot_refs:
- "docs/ssot/tracecontext.md"
---

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
  - `Coretsia\Foundation\Context\ContextKeys` (optional; if used)

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
  - `Coretsia\Foundation\Context\ContextKeys` (optional)

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

- [ ] `framework/packages/platform/tracing/src/Module/TracingModule.php` — module entry
- [ ] `framework/packages/platform/tracing/src/Provider/TracingServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/tracing/src/Provider/TracingServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/tracing/config/tracing.php` — config subtree
- [ ] `framework/packages/platform/tracing/config/rules.php` — config rules
- [ ] `framework/packages/platform/tracing/README.md` — docs

- [ ] `framework/packages/platform/tracing/src/Default/DefaultTracer.php` — TracerPortInterface
- [ ] `framework/packages/platform/tracing/src/Default/DefaultSpan.php` — SpanInterface
- [ ] `framework/packages/platform/tracing/src/W3c/W3cTraceContextPropagation.php` — ContextPropagationInterface (deterministic, no-throw)
- [ ] `framework/packages/platform/tracing/src/Noop/NullSpanExporter.php` — SpanExporterInterface
- [ ] `framework/packages/platform/tracing/src/Sampling/AlwaysOffSampler.php` — SamplerInterface (Phase 0 default; no-throw)

- [ ] `framework/packages/platform/tracing/tests/Unit/W3cPropagationExtractInjectDeterministicTest.php`
- [ ] `framework/packages/platform/tracing/tests/Contract/NoopNeverThrowsContractTest.php`
- [ ] `framework/packages/platform/tracing/tests/Contract/W3CPropagationDeterministicContractTest.php`
- [ ] `framework/packages/platform/tracing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/platform/tracing/tests/Integration/W3cPropagationMissingHeadersNoThrowTest.php`

Docs:
- [ ] `docs/ssot/tracecontext.md` — W3C propagation rules + redaction + “no labels for trace ids”

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/tracecontext.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0033-platform-tracing-metrics-w3c-propagation.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/tracing/composer.json`
- [ ] `framework/packages/platform/tracing/src/Module/TracingModule.php`
- [ ] `framework/packages/platform/tracing/src/Provider/TracingServiceProvider.php`
- [ ] `framework/packages/platform/tracing/config/tracing.php`
- [ ] `framework/packages/platform/tracing/config/rules.php`
- [ ] `framework/packages/platform/tracing/README.md`
- [ ] `framework/packages/platform/tracing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/tracing/config/tracing.php`
- [ ] Keys (dot):
  - [ ] `tracing.enabled` = true
  - [ ] `tracing.sampling.mode` = 'always_off'
  - [ ] `tracing.exporter` = 'null'
- [ ] Rules:
  - [ ] `framework/packages/platform/tracing/config/rules.php` enforces shape

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
  - [ ] `framework/packages/platform/tracing/tests/Contract/NoopNeverThrowsContractTest.php`
  - [ ] `framework/packages/platform/tracing/tests/Contract/W3CPropagationDeterministicContractTest.php`

#### Test harness / fixtures (when integration is needed)

- N/A (integration tests use minimal middleware harness)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/tracing/tests/Unit/W3cPropagationExtractInjectDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/tracing/tests/Contract/NoopNeverThrowsContractTest.php`
  - [ ] `framework/packages/platform/tracing/tests/Contract/W3CPropagationDeterministicContractTest.php`
  - [ ] `framework/packages/platform/tracing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/tracing/tests/Integration/W3cPropagationMissingHeadersNoThrowTest.php`
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

### 3.31.0 Platform metrics baseline (noop-safe + label allowlist + renderer port) (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.31.0"
owner_path: "framework/packages/platform/metrics/"

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
adr: "docs/adr/ADR-0033-platform-tracing-metrics-w3c-propagation.md"
ssot_refs:
- "docs/ssot/metrics-policy.md"
- "docs/ssot/observability.md"
---

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
  - `Coretsia\Foundation\Context\ContextKeys` (optional; if used)

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
  - `Coretsia\Foundation\Context\ContextKeys` (optional)

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

- [ ] `framework/packages/platform/metrics/src/Module/MetricsModule.php` — module entry
- [ ] `framework/packages/platform/metrics/src/Provider/MetricsServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/metrics/src/Provider/MetricsServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/metrics/config/metrics.php` — config subtree
- [ ] `framework/packages/platform/metrics/config/rules.php` — config rules
- [ ] `framework/packages/platform/metrics/README.md` — docs

- [ ] `framework/packages/platform/metrics/src/Default/DefaultMeter.php` — MeterPortInterface
- [ ] `framework/packages/platform/metrics/src/Noop/NullMetricsRenderer.php` — MetricsRendererInterface
- [ ] `framework/packages/platform/metrics/src/Labels/AllowedLabelSet.php` — allowlist enforcement
  - [ ] MUST enforce the fixed SSoT allowlist from `docs/ssot/observability.md`
  - [ ] MUST NOT read allowlist values from runtime/app config
- [ ] `framework/packages/platform/metrics/src/Exception/MetricLabelForbiddenException.php` — (`CORETSIA_METRIC_LABEL_FORBIDDEN`)

- [ ] `framework/packages/platform/metrics/tests/Unit/AllowedLabelSetRejectsForbiddenKeysTest.php`
- [ ] `framework/packages/platform/metrics/tests/Contract/NoopNeverThrowsContractTest.php`
- [ ] `framework/packages/platform/metrics/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/platform/metrics/tests/Integration/NoopMeterRecordNoThrowTest.php`

Docs:
- [ ] `docs/ssot/metrics-policy.md` — metric naming + normalization map + examples
  - [ ] MUST NOT redefine, extend, replace, or relax the canonical label allowlist from `docs/ssot/observability.md`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/metrics-policy.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0033-platform-tracing-metrics-w3c-propagation.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/metrics/composer.json`
- [ ] `framework/packages/platform/metrics/src/Module/MetricsModule.php`
- [ ] `framework/packages/platform/metrics/src/Provider/MetricsServiceProvider.php`
- [ ] `framework/packages/platform/metrics/config/metrics.php`
- [ ] `framework/packages/platform/metrics/config/rules.php`
- [ ] `framework/packages/platform/metrics/README.md`
- [ ] `framework/packages/platform/metrics/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/metrics/config/metrics.php`
- [ ] Keys (dot):
  - [ ] `metrics.enabled` = true
  - [ ] `metrics.renderer` = 'null'
- [ ] Normative:
  - [ ] there MUST be no configurable `metrics.labels.allowed` key
  - [ ] label allowlist is fixed by `docs/ssot/observability.md`
  - [ ] `docs/ssot/metrics-policy.md` MAY document metric-specific normalization/examples only
- [ ] Rules:
  - [ ] `framework/packages/platform/metrics/config/rules.php` enforces shape

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
  - [ ] `framework/packages/platform/metrics/tests/Contract/NoopNeverThrowsContractTest.php`
  - [ ] `framework/packages/platform/metrics/tests/Unit/AllowedLabelSetRejectsForbiddenKeysTest.php`
- [ ] If redaction exists → covered by SSoT “no labels for trace ids / request ids” + allowlist enforcement tests above

#### Test harness / fixtures (when integration is needed)

- N/A (integration tests use minimal middleware harness)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/metrics/tests/Unit/AllowedLabelSetRejectsForbiddenKeysTest.php`
- Contract:
  - [ ] `framework/packages/platform/metrics/tests/Contract/NoopNeverThrowsContractTest.php`
  - [ ] `framework/packages/platform/metrics/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/metrics/tests/Integration/NoopMeterRecordNoThrowTest.php`
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
owner_path: "framework/packages/platform/problem-details/"

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
adr: "docs/adr/ADR-0034-platform-problem-details-rfc7807.md"
ssot_refs:
- "docs/ssot/error-descriptor.md"
- "docs/ssot/tags.md"
---

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

- [ ] `framework/packages/platform/problem-details/src/Module/ProblemDetailsModule.php` — module entry
- [ ] `framework/packages/platform/problem-details/src/Provider/ProblemDetailsServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/problem-details/src/Provider/ProblemDetailsServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).

- [ ] `framework/packages/platform/problem-details/config/problem_details.php` — config subtree
- [ ] `framework/packages/platform/problem-details/config/rules.php` — config rules
- [ ] `framework/packages/platform/problem-details/README.md` — docs (Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/problem-details/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — cross-cutting proof

- [ ] `framework/packages/platform/problem-details/src/ProblemDetails.php` — VO (RFC7807 fields)
- [ ] `framework/packages/platform/problem-details/src/Renderer/ProblemDetailsRendererInterface.php` — package-local renderer port
- [ ] `framework/packages/platform/problem-details/src/Renderer/ProblemDetailsJsonRenderer.php` — deterministic JSON + content-type
  - [ ] MUST serialize via `Coretsia\Foundation\Serialization\StableJsonEncoder` (single-choice runtime writer)
  - [ ] MUST recursively sort map keys by `strcmp`; lists preserve order
  - [ ] MUST NOT emit floats/NaN/INF
  - [ ] MUST NOT use ad-hoc `json_encode` directly in renderer logic
- [ ] `framework/packages/platform/problem-details/src/Adapter/ErrorDescriptorToProblemDetailsAdapter.php` — ErrorDescriptor → RFC7807 mapping
- [ ] `framework/packages/platform/problem-details/src/Http/Middleware/HttpErrorHandlingMiddleware.php` — PSR-15 outer wrapper
- [ ] `framework/packages/platform/problem-details/src/Security/Redaction.php` — redaction helpers (hash/len, safe fields)
- [ ] `framework/packages/platform/problem-details/src/Exception/ProblemDetailsRenderFailedException.php` — (`CORETSIA_PROBLEM_DETAILS_RENDER_FAILED`)

- [ ] `framework/packages/platform/problem-details/tests/Unit/JsonRendererDeterministicOrderTest.php`
- [ ] `framework/packages/platform/problem-details/tests/Contract/ProblemDetailsJsonSchemaContractTest.php`
- [ ] `framework/packages/platform/problem-details/tests/Integration/HttpErrorMiddlewareReturnsRfc7807Test.php`
- [ ] `framework/packages/platform/problem-details/tests/Integration/DetailHiddenInProductionTest.php`

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0034-platform-problem-details-rfc7807.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/problem-details/composer.json`
- [ ] `framework/packages/platform/problem-details/src/Module/ProblemDetailsModule.php`
- [ ] `framework/packages/platform/problem-details/src/Provider/ProblemDetailsServiceProvider.php`
- [ ] `framework/packages/platform/problem-details/config/problem_details.php`
- [ ] `framework/packages/platform/problem-details/config/rules.php`
- [ ] `framework/packages/platform/problem-details/README.md`
- [ ] `framework/packages/platform/problem-details/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/problem-details/config/problem_details.php`
- [ ] Keys (dot):
  - [ ] `problem_details.enabled` = true
  - [ ] `problem_details.show_detail` = false
  - [ ] `problem_details.show_detail_in_local` = true
  - [ ] `problem_details.redaction.enabled` = true
- [ ] Rules:
  - [ ] `framework/packages/platform/problem-details/config/rules.php` enforces shape

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
  - [ ] `framework/packages/platform/problem-details/tests/Integration/DetailHiddenInProductionTest.php`
  - [ ] `framework/packages/platform/problem-details/tests/Contract/ProblemDetailsJsonSchemaContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A (PSR-15 middleware can be tested via unit/integration harness)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/problem-details/tests/Unit/JsonRendererDeterministicOrderTest.php`
- Contract:
  - [ ] `framework/packages/platform/problem-details/tests/Contract/ProblemDetailsJsonSchemaContractTest.php`
  - [ ] `framework/packages/platform/problem-details/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/problem-details/tests/Integration/HttpErrorMiddlewareReturnsRfc7807Test.php`
  - [ ] `framework/packages/platform/problem-details/tests/Integration/DetailHiddenInProductionTest.php`
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
owner_path: "framework/packages/platform/http/"

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

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0035-platform-http-runtime-pipeline-wiring.md"
ssot_refs:
- "docs/ssot/http-middleware-wiring.md"
- "docs/ssot/http-middleware-catalog.md"
---

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
    `framework/packages/platform/http/src/Provider/Tags.php`.

- Required contracts / ports:
  - `Coretsia\Kernel\Runtime\KernelRuntimeInterface` — UoW hooks
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
  - `Coretsia\Kernel\Runtime\KernelRuntimeInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextStore`
  - `Coretsia\Foundation\Context\ContextKeys`
  - `Coretsia\Foundation\Tag\TagRegistry`
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - N/A (maintenance commands are separate epic 3.80.0 in old plan; not part of this epic)
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

- [ ] `framework/packages/platform/http/src/Module/HttpModule.php` — module entry
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/http/src/Provider/Tags.php` —  define constants for ALL 9 tags above (owner = platform/http)
- [ ] `framework/packages/platform/http/config/http.php` — config subtree
- [ ] `framework/packages/platform/http/config/rules.php` — config rules
- [ ] `framework/packages/platform/http/README.md` — docs (Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/http/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

Core runtime:
- [ ] `framework/packages/platform/http/src/HttpKernelInterface.php`
- [ ] `framework/packages/platform/http/src/HttpKernel.php`
- [ ] `framework/packages/platform/http/src/Middleware/Pipeline.php`
- [ ] `framework/packages/platform/http/src/Middleware/MiddlewareResolver.php`
- [ ] `framework/packages/platform/http/src/Middleware/HttpMiddlewareStackBuilder.php`
- [ ] `framework/packages/platform/http/src/Middleware/Dedupe.php`

PSR-7 glue (no superglobals inside):
- [ ] `framework/packages/platform/http/src/Psr7/ServerRequestFactory.php`
- [ ] `framework/packages/platform/http/src/Response/ResponseEmitter.php`

System payload helpers:
- [ ] `framework/packages/platform/http/src/System/SystemEndpointPayload.php`

Core middlewares (Phase 0 baseline):
- [ ] `framework/packages/platform/http/src/Middleware/CorrelationIdMiddleware.php`
- [ ] `framework/packages/platform/http/src/Middleware/TraceContextMiddleware.php`
- [ ] `framework/packages/platform/http/src/Middleware/HttpMetricsMiddleware.php`
- [ ] `framework/packages/platform/http/src/Middleware/AccessLogMiddleware.php`

Request normalization/safety (Phase 0, config-driven):
- [ ] `framework/packages/platform/http/src/Middleware/TrustedProxyMiddleware.php`
- [ ] `framework/packages/platform/http/src/Proxy/TrustedProxyPolicy.php`
- [ ] `framework/packages/platform/http/src/Middleware/RequestContextMiddleware.php`
- [ ] `framework/packages/platform/http/src/Middleware/TrustedHostMiddleware.php`
- [ ] `framework/packages/platform/http/src/Host/TrustedHostPolicy.php`
- [ ] `framework/packages/platform/http/src/Middleware/RequestBodySizeLimitMiddleware.php`
- [ ] `framework/packages/platform/http/src/Middleware/JsonBodyParserMiddleware.php`

Terminal handler:
- [ ] `framework/packages/platform/http/src/Routing/FallbackRouterHandler.php`

Errors (exceptions, mapped by `platform/errors`):
- [ ] `framework/packages/platform/http/src/Exception/BadJsonException.php` — `CORETSIA_HTTP_BAD_JSON`
- [ ] `framework/packages/platform/http/src/Exception/BodyTooLargeException.php` — `CORETSIA_HTTP_PAYLOAD_TOO_LARGE`
- [ ] `framework/packages/platform/http/src/Exception/TrustedHostRejectedException.php` — `CORETSIA_HTTP_TRUSTED_HOST_REJECTED`

Tests:
- [ ] `framework/packages/platform/http/tests/Unit/ForwardedParsingDeterministicTest.php`
- [ ] `framework/packages/platform/http/tests/Unit/TrustedHostPolicyDeterministicTest.php`
- [ ] `framework/packages/platform/http/tests/Contract/MiddlewareOrderDeterministicTest.php`
- [ ] `framework/packages/platform/http/tests/Contract/NoopTracingAndMetricsNeverThrowInPipelineContractTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/HttpKernelInvokesKernelRuntimeUowHooksTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/CorrelationHeaderAlwaysPresentTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/BodySizeLimitReturns413Test.php`
- [ ] `framework/packages/platform/http/tests/Integration/BadJsonTriggersCanonicalErrorFlowTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/AccessLogDoesNotLeakHeadersCookiesOrBodyTest.php`

Docs:
- [ ] `docs/ssot/http-middleware-wiring.md` — tags/slots/dedupe/deterministic order/override recipe
- [ ] `docs/ssot/http-middleware-catalog.md` — canonical middleware catalog + priority bands

#### Modifies

- [ ] `framework/tools/gates/no_skeleton_http_default_gate.php` — must remain green (no skeleton default http config)
- [ ] deptrac expectations updated (if needed)
- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/http-middleware-wiring.md`
  - [ ] `docs/ssot/http-middleware-catalog.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0035-platform-http-runtime-pipeline-wiring.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/http/composer.json`
- [ ] `framework/packages/platform/http/src/Module/HttpModule.php`
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceProvider.php`
- [ ] `framework/packages/platform/http/config/http.php`
- [ ] `framework/packages/platform/http/config/rules.php`
- [ ] `framework/packages/platform/http/README.md`
- [ ] `framework/packages/platform/http/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

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
  - [ ] `framework/packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.enabled` = true

  # auto include tagged middlewares per-slot
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

- [ ] Rules:
  - [ ] `framework/packages/platform/http/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/http/src/Provider/Tags.php`
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
  - [ ] `framework/packages/platform/http/tests/Integration/CorrelationHeaderAlwaysPresentTest.php`
  - [ ] (context enrichment) add/verify via middleware integration tests where applicable
- [ ] If `kernel.reset` used → `N/A` (not expected)
- [ ] If metrics/spans/logs exist → covered by:
  - [ ] `framework/packages/platform/http/tests/Contract/NoopTracingAndMetricsNeverThrowInPipelineContractTest.php`
  - [ ] access log behavior proven indirectly via pipeline tests (Phase 0)
- [ ] If redaction exists → covered by:
  - [ ] `framework/packages/platform/http/tests/Integration/AccessLogDoesNotLeakHeadersCookiesOrBodyTest.php`

#### Test harness / fixtures (when integration is needed)

N/A (integration tests use minimal kernel/http harness)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/http/tests/Unit/ForwardedParsingDeterministicTest.php`
  - [ ] `framework/packages/platform/http/tests/Unit/TrustedHostPolicyDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/http/tests/Contract/MiddlewareOrderDeterministicTest.php`
  - [ ] `framework/packages/platform/http/tests/Contract/NoopTracingAndMetricsNeverThrowInPipelineContractTest.php`
  - [ ] `framework/packages/platform/http/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/http/tests/Integration/HttpKernelInvokesKernelRuntimeUowHooksTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/CorrelationHeaderAlwaysPresentTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/BodySizeLimitReturns413Test.php`
  - [ ] `framework/packages/platform/http/tests/Integration/BadJsonTriggersCanonicalErrorFlowTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/AccessLogDoesNotLeakHeadersCookiesOrBodyTest.php`
- Gates/Arch:
  - [ ] `framework/tools/gates/no_skeleton_http_default_gate.php` green
  - [ ] deptrac updated (if needed)

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

---

### 3.60.0 RequestId vs CorrelationId policy (SHOULD) [IMPL]

---
type: package
phase: 3
epic_id: "3.60.0"
owner_path: "framework/packages/platform/http/"

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
adr: "docs/adr/ADR-0036-requestid-correlationid-policy.md"
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 3.50.0 — base HTTP runtime exists (middleware tags/stack builder + config/http.php)
  - PHASE 1 Foundation `ContextKeys::REQUEST_ID` MUST exist (see PHASE 1 patch above)

- Required deliverables (exact paths):
  - `framework/packages/platform/http/config/http.php` — base config file must exist
  - `framework/packages/platform/http/config/rules.php` — base rules must exist
  - `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — wiring base exists
  - `framework/packages/platform/http/src/Provider/Tags.php` — provides `http.middleware.system_pre`

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
  - `Coretsia\Foundation\Context\ContextKeys` (reserved key `REQUEST_ID`)
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
  - `Coretsia\Foundation\Context\ContextKeys`
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

- [ ] `framework/packages/platform/http/src/Middleware/RequestIdMiddleware.php` — read/validate/generate request id
- [ ] `framework/packages/platform/http/src/RequestId/RequestIdPolicy.php` — VO policy
- [ ] `framework/packages/platform/http/src/RequestId/RequestIdValidator.php` — deterministic charset/len validation

- [ ] `framework/packages/platform/http/tests/Integration/UpstreamRequestIdAcceptedWhenValidTest.php` — asserts accepted upstream id is written to `ContextKeys::REQUEST_ID` and returned in the configured response header
- [ ] `framework/packages/platform/http/tests/Integration/InvalidRequestIdIsReplacedByGeneratedTest.php` — asserts invalid upstream id is replaced deterministically, written to `ContextKeys::REQUEST_ID`, and returned in the configured response header

#### Modifies

- [ ] `framework/packages/platform/http/config/http.php` — add `http.request_id.*` keys + defaults
- [ ] `framework/packages/platform/http/config/rules.php` — enforce shape for new keys
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — register middleware + add tag `system_pre` priority `940`
- [ ] `framework/packages/platform/http/README.md` — section “RequestIdMiddleware” (slot/priority, header policy, upstream accept/replace rules)
- [ ] `docs/ssot/http-middleware-catalog.md` — add/update row for `RequestIdMiddleware` (system_pre 940)
- [ ] `framework/tools/spikes/fixtures/http_middleware_catalog.php` — add/update row for `RequestIdMiddleware` (system_pre 940)
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0036-requestid-correlationid-policy.md`

#### Package skeleton (if type=package)

N/A (same package as 3.50.0)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.request_id.enabled` = false
  - [ ] `http.request_id.header_name` = 'X-Request-Id'
  - [ ] `http.request_id.accept_from_upstream` = true
  - [ ] `http.request_id.max_length` = 128
- [ ] Rules:
  - [ ] `framework/packages/platform/http/config/rules.php` enforces shape

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
  - [ ] `framework/packages/platform/http/tests/Integration/UpstreamRequestIdAcceptedWhenValidTest.php` — asserts ContextStore write + response header echo for valid upstream id
  - [ ] `framework/packages/platform/http/tests/Integration/InvalidRequestIdIsReplacedByGeneratedTest.php` — asserts deterministic replacement + ContextStore write + response header emission
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
owner_path: "framework/packages/platform/http/"

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
adr: "docs/adr/ADR-0037-response-hardening-headers-https.md"
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 3.50.0 — base HTTP runtime exists (system_pre/system_post slots)
  - 3.50.0 — `http.middleware.system_post` tag exists
  - (optional) 3.50.0 — TrustedProxyMiddleware exists if redirect relies on proxy scheme

- Required deliverables (exact paths):
  - `framework/packages/platform/http/config/http.php`
  - `framework/packages/platform/http/config/rules.php`
  - `framework/packages/platform/http/src/Provider/HttpServiceProvider.php`

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
  - `Coretsia\Foundation\Context\ContextKeys` — required because redirect scheme resolution MAY read `ContextKeys::SCHEME`

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
  - `Coretsia\Foundation\Context\ContextKeys`

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

- [ ] `framework/packages/platform/http/src/Middleware/SecurityHeadersMiddleware.php` — adds headers deterministically
- [ ] `framework/packages/platform/http/src/SecurityHeaders/SecurityHeadersPolicy.php` — VO policy (CSP/Referrer/etc)
- [ ] `framework/packages/platform/http/src/Middleware/HttpsRedirectMiddleware.php` — redirect policy-based
- [ ] `framework/packages/platform/http/src/HttpsRedirect/HttpsRedirectPolicy.php` — VO policy (status/allowlist)

- [ ] `framework/packages/platform/http/tests/Integration/SecurityHeadersAppliedToAllResponsesTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/HttpsRedirectRespectsProxySchemeTest.php`
- [ ] `framework/packages/platform/http/tests/Contract/SecurityHeadersDeterministicOrderContractTest.php`

#### Modifies

- [ ] `framework/packages/platform/http/config/http.php` — add `security_headers.*` + `https_redirect.*` keys + defaults
- [ ] `framework/packages/platform/http/config/rules.php` — enforce shape for new keys
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — register middlewares + add tags/priority
- [ ] `framework/packages/platform/http/README.md` — sections “HttpsRedirectMiddleware” and “SecurityHeadersMiddleware” (slot/priority, toggle keys, override recipe)
- [ ] `docs/ssot/http-middleware-catalog.md` — add/update rows:
  - [ ] `HttpsRedirectMiddleware` → `http.middleware.system_pre` priority `850`
  - [ ] `SecurityHeadersMiddleware` → `http.middleware.system_post` priority `600`
- [ ] `framework/tools/spikes/fixtures/http_middleware_catalog.php` — add/update rows:
  - [ ] `HttpsRedirectMiddleware` → `http.middleware.system_pre` priority `850`
  - [ ] `SecurityHeadersMiddleware` → `http.middleware.system_post` priority `600`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0037-response-hardening-headers-https.md`

#### Package skeleton (if type=package)

N/A (same package as 3.50.0)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/http/config/http.php`
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
  - [ ] `framework/packages/platform/http/config/rules.php` enforces shape

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
  - [ ] `framework/packages/platform/http/tests/Contract/SecurityHeadersDeterministicOrderContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/http/tests/Integration/SecurityHeadersAppliedToAllResponsesTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/HttpsRedirectRespectsProxySchemeTest.php`
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
owner_path: "framework/packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "Maintenance toggle працює без `config:compile` і без впливу на fingerprint/artifacts, повертаючи deterministic 503."
provides:
- "Real maintenance toggle через `skeleton/var/maintenance/*` (поза artifacts/fingerprint)"
- "Deterministic 503 response + optional `message.json` schema"
- "CLI команди enable/disable/status живуть у `platform/http` і discover-яться через existing tag `cli.command` (owner = `platform/cli`) за non-owner tag usage rule"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-0038-maintenance-mode-toggle.md"
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - `framework/packages/platform/http/config/http.php` — додаємо `http.maintenance.*` ключі
  - `framework/packages/platform/http/config/rules.php` — додаємо rules для `http.maintenance.*`
  - `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — wiring middleware + CLI commands
  - `framework/packages/platform/http/src/Provider/Tags.php` — tag constants owner=http (для `http.middleware.system_pre`)

- Runtime state location policy (MUST):
  - `skeleton/var/maintenance/` is the reserved runtime state directory for this epic.
  - The directory itself is created by this epic via `skeleton/var/maintenance/.gitkeep`.

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
  - `maintenance:enable` → `framework/packages/platform/http/src/Console/MaintenanceEnableCommand.php`
  - `maintenance:disable` → `framework/packages/platform/http/src/Console/MaintenanceDisableCommand.php`
  - `maintenance:status` → `framework/packages/platform/http/src/Console/MaintenanceStatusCommand.php`
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
    - `skeleton/var/maintenance/enabled`
    - `skeleton/var/maintenance/message.json` (optional)
  - writes (via CLI commands only):
    - `skeleton/var/maintenance/enabled`
    - `skeleton/var/maintenance/message.json` (optional)

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/http/src/Maintenance/MaintenanceStateReader.php` — reads flag + validates optional message schema (safe)
- [ ] `framework/packages/platform/http/src/Maintenance/MaintenanceMiddleware.php` — returns deterministic 503 when enabled; bypass `/_alive`
- [ ] `framework/packages/platform/http/src/Maintenance/MaintenanceMessage.php` — VO schema `{schemaVersion,message,retryAfterSeconds?}`
- [ ] `framework/packages/platform/http/src/Maintenance/Exception/MaintenanceMessageInvalidException.php` — errorCode `CORETSIA_MAINTENANCE_MESSAGE_INVALID`

- [ ] `framework/packages/platform/http/src/Console/MaintenanceEnableCommand.php` — creates `enabled` + optional `message.json` (deterministic bytes)
- [ ] `framework/packages/platform/http/src/Console/MaintenanceDisableCommand.php` — removes `enabled` (atomic)
- [ ] `framework/packages/platform/http/src/Console/MaintenanceStatusCommand.php` — reads state safely (no secrets)

- [ ] `skeleton/var/maintenance/.gitkeep` — runtime state dir placeholder; maintenance state files themselves are NOT committed deliverables
  - [ ] Runtime state policy (MUST):
    - [ ] `enabled` and `message.json` are runtime state written/removed by commands and MUST NOT be committed as repository deliverables

- [ ] `docs/ops/maintenance-mode.md` — how to enable/disable + safety notes (no artifacts)

- [ ] `framework/packages/platform/http/tests/Integration/MaintenanceEnabledReturns503ForNonBypassedRoutesTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/AliveEndpointBypassesMaintenanceTest.php`
- [ ] `framework/packages/platform/http/tests/Contract/MaintenanceMessageSchemaContractTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/MaintenanceCommandsDoNotDirtyArtifactsTest.php`

#### Modifies

- [ ] `framework/packages/platform/http/config/http.php` — add `http.maintenance.*` keys + defaults
- [ ] `framework/packages/platform/http/config/rules.php` — enforce shape for `http.maintenance.*`
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — register middleware + add tag (system_pre) + register CLI commands (tag `cli.command`)
- [ ] `framework/packages/platform/http/README.md` — section “Maintenance” (slot/tag/priority, bypass rules, file paths + schema)
- [ ] `docs/ssot/http-middleware-catalog.md` — add/update row for `MaintenanceMiddleware` (`http.middleware.system_pre` priority `900`)
- [ ] `framework/tools/spikes/fixtures/http_middleware_catalog.php` — add/update row for `MaintenanceMiddleware` (`http.middleware.system_pre` priority `900`)
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0038-maintenance-mode-toggle.md`

#### Package skeleton (if type=package)

N/A (package `platform/http` already exists; this epic adds maintenance slice only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.maintenance.enabled` = true
  - [ ] `http.maintenance.flag_path` = 'skeleton/var/maintenance/enabled'
  - [ ] `http.maintenance.message_path` = 'skeleton/var/maintenance/message.json'
  - [ ] `http.maintenance.retry_after_default_seconds` = 60
- [ ] Rules:
  - [ ] `framework/packages/platform/http/config/rules.php` enforces shape

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
  - [ ] `skeleton/var/maintenance/enabled` (existence flag)
  - [ ] `skeleton/var/maintenance/message.json` (schemaVersion, deterministic bytes)
- [ ] Reads:
  - [ ] `skeleton/var/maintenance/message.json` schema is validated deterministically (schemaVersion int; safe fields only)

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
- [ ] If metrics/spans/logs exist → `framework/packages/platform/http/tests/Integration/MaintenanceEnabledReturns503ForNonBypassedRoutesTest.php`
- [ ] If redaction exists → `framework/packages/platform/http/tests/Contract/MaintenanceMessageSchemaContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `framework/packages/platform/http/tests/Contract/MaintenanceMessageSchemaContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/http/tests/Integration/MaintenanceEnabledReturns503ForNonBypassedRoutesTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/AliveEndpointBypassesMaintenanceTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/MaintenanceCommandsDoNotDirtyArtifactsTest.php`
- Gates/Arch:
  - [ ] deptrac green

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: CLI enable/disable/status не роблять artifacts dirty; rerun-no-diff where applicable
- [ ] Docs updated:
  - [ ] `framework/packages/platform/http/README.md`
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
owner_path: "framework/packages/platform/http/"

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

adr: "docs/adr/ADR-0039-system-route-table-reserved-map.md"
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
  - `framework/tools/gates/system_route_table_gate.php` — validates table invariants + version bump policy
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

- [ ] `framework/packages/platform/http/src/System/SystemRouteTable.php` — entries + `SCHEMA_VERSION`
- [ ] `framework/tools/gates/system_route_table_gate.php` — validates table invariants + version bump policy
- [ ] `framework/packages/platform/http/tests/Contract/SystemRouteTableShapeContractTest.php` — shape + invariants
- [ ] `framework/tools/gates/tests/SystemRouteTableGateTest.php` — fixture good/bad
- [ ] `docs/ssot/system-route-table.md` — meaning of `active|reserved|dev_only` + how owners claim endpoints later

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/system-route-table.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0039-system-route-table-reserved-map.md`

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
- [ ] Gate enforcement exists → `framework/tools/gates/tests/SystemRouteTableGateTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Gate fixtures:
  - [ ] `framework/tools/gates/tests/Fixtures/SystemRouteTable/*` (good/bad snapshots)

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `framework/packages/platform/http/tests/Contract/SystemRouteTableShapeContractTest.php`
- Integration:
  - N/A
- Gates/Arch:
  - [ ] `framework/tools/gates/tests/SystemRouteTableGateTest.php`

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable (gate test proves enforcement)
- [ ] Docs updated:
  - [ ] `docs/ssot/system-route-table.md`
  - [ ] ADR registered
- [ ] Gate output policy (MUST)
  - [ ] Gate MUST use tools ConsoleOutput only (`framework/tools/spikes/_support/ConsoleOutput.php`).
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
owner_path: "framework/packages/platform/http/"

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

adr: "docs/adr/ADR-0040-reserved-path-matching-rules.md"
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

- [ ] `framework/packages/platform/http/src/System/ReservedPathMatcher.php` — segment-prefix matcher (exact vs prefix boundary)
  - [ ] Matcher normalization invariants (MUST)
    - [ ] Matching MUST be defined on a **normalized path**:
      - [ ] collapse multiple slashes `//+` → `/` (single-choice),
      - [ ] ensure leading slash,
      - [ ] trailing slash semantics MUST be explicit in docs (`exact` vs `segment-prefix` boundary).
    - [ ] Segment-prefix match MUST require boundary:
      - [ ] `/health` matches `/health` and `/health/...`
      - [ ] MUST NOT match `/healthz`
    - [ ] Implementation MUST be pure string ops; no unbounded allocations proportional to input beyond O(n).

- [ ] `framework/packages/platform/http/tests/Unit/ReservedPathMatcherSegmentPrefixTest.php` — edge cases (`//`, trailing slash, boundary)
- [ ] `docs/ssot/reserved-path-matching.md` — exact vs prefix semantics + examples

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/reserved-path-matching.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0040-reserved-path-matching-rules.md`

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
- [ ] Semantic correctness proof → `framework/packages/platform/http/tests/Unit/ReservedPathMatcherSegmentPrefixTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/http/tests/Unit/ReservedPathMatcherSegmentPrefixTest.php`
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
owner_path: "framework/packages/platform/http/"

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
  - `framework/packages/platform/http/src/Routing/FallbackRouterHandler.php` — terminal handler hook point (owner=http runtime)
  - `framework/packages/platform/http/src/System/SystemEndpointPayload.php` — canonical payload helper (can be introduced earlier; this epic may extend it)
  - `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — wiring terminal handler to pipeline

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

- [ ] `framework/packages/platform/http/src/Routing/FallbackRouter.php` — endpoint dispatch (uses SystemRouteTable + ReservedPathMatcher)
- [ ] `framework/packages/platform/http/src/Routing/FallbackResponses.php` — helpers for deterministic response bodies/headers
  - [ ] JSON bodies produced here MUST serialize via `Coretsia\Foundation\Serialization\StableJsonEncoder`
  - [ ] map keys MUST be recursively sorted by `strcmp`; lists preserve order
  - [ ] MUST NOT emit floats/NaN/INF
  - [ ] MUST NOT use ad-hoc `json_encode` directly in response helpers
- [ ] `framework/packages/platform/http/src/Exception/IntentionalErrorException.php` — thrown on `/error` (`CORETSIA_HTTP_INTENTIONAL_ERROR`)
- [ ] `docs/ssot/http-system-endpoints.md` — owned vs reserved endpoints + owner-claim rules

- [ ] `framework/packages/platform/http/tests/Integration/HomeReturns200HtmlTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/PingReturns200JsonTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/AliveReturns200TextTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/SystemHealthEndpointsReturn501UntilOwnerInstalledTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/SystemMetricsEndpointReturns501PlaintextUntilOwnerInstalledTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/ErrorRouteTriggersCanonicalErrorFlowTest.php`

#### Modifies

- [ ] `framework/packages/platform/http/src/Routing/FallbackRouterHandler.php` — delegate to `FallbackRouter` (single dispatch point)
- [ ] `framework/packages/platform/http/src/System/SystemEndpointPayload.php` — add/align canonical payloads for 501/503 (no secrets)
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — wire router into terminal handler (if not already)
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
  - [ ] `framework/packages/platform/http/tests/Integration/HomeReturns200HtmlTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/PingReturns200JsonTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/AliveReturns200TextTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/SystemHealthEndpointsReturn501UntilOwnerInstalledTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/SystemMetricsEndpointReturns501PlaintextUntilOwnerInstalledTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/ErrorRouteTriggersCanonicalErrorFlowTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/UnsupportedMethodForSystemEndpointFallsThroughAsNotMatchedTest.php`
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
owner_path: "framework/packages/platform/http/"

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

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `3.90.0` — SystemRouteTable marks `/_debug/` as dev_only
  - `3.110.0` — FallbackRouter dispatches `/_debug/` prefix to debug router (or equivalent hook)

- Required deliverables (exact paths):
  - `framework/packages/platform/http/config/http.php` — додаємо `http.debug.*` keys
  - `framework/packages/platform/http/config/rules.php` — rules для `http.debug.*`
  - `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — register debug services/handlers

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
  - `Coretsia\Foundation\Context\ContextKeys`

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
    - may read `skeleton/var/cache/<appId>/*` only to show cache status summary (no values)

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/http/src/Debug/DebugGuard.php` — allowlist/token/local env guard
- [ ] `framework/packages/platform/http/src/Debug/DebugRouter.php` — routes `/_debug/*` to handlers deterministically
- [ ] `framework/packages/platform/http/src/Debug/DebugHeadersHandler.php` — outputs only safe headers (redacts Authorization/Cookie/Set-Cookie)
- [ ] `framework/packages/platform/http/src/Debug/DebugModuleListHandler.php` — lists enabled modules (safe)
- [ ] `framework/packages/platform/http/src/Debug/DebugConfigHandler.php` — formation trace + redacted value (no secrets)
- [ ] `framework/packages/platform/http/src/Debug/DebugCacheHandler.php` — cache artifact status summary (no values)
  - [ ] Cache status MUST be summary-only:
    - [ ] existence + byte size + sha256 (optional),
    - [ ] MUST NOT parse artifacts payloads,
    - [ ] MUST NOT output absolute paths or file contents.
- [ ] `framework/packages/platform/http/src/Debug/Redaction.php` — helper `hash/len`, header redaction map

- [ ] `framework/packages/platform/http/src/Debug/Exception/DebugForbiddenException.php` — errorCode `CORETSIA_HTTP_DEBUG_FORBIDDEN` (403)
- [ ] `framework/packages/platform/http/src/Debug/Exception/DebugDisabledException.php` — errorCode `CORETSIA_HTTP_DEBUG_DISABLED` (deterministic 404 when debug endpoints are disabled)
- [ ] `framework/packages/platform/http/src/Error/DebugExceptionMapper.php`
  - [ ] implements `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - [ ] maps:
    - [ ] `DebugDisabledException` → `ErrorDescriptor` with optional HTTP hint `404`
    - [ ] `DebugForbiddenException` → `ErrorDescriptor` with optional HTTP hint `403`
  - [ ] MUST NOT import `platform/errors`, because it is a forbidden compile-time dependency here
  - [ ] MUST be registered via DI tag `error.mapper` using a package-local internal mirror constant
    - [ ] canonical string literal `'error.mapper'` is allowed only as fallback

- [ ] `docs/guides/http-debug-endpoints.md` — how to enable in local + security warnings + redaction rules

- [ ] `framework/packages/platform/http/tests/Integration/DebugEndpointsGuardTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/DebugHeadersRedactsSensitiveHeadersTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/DebugConfigShowsSourcesButNotSecretsTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/DebugDisabledAndForbiddenTriggerCanonicalErrorFlowTest.php`

#### Modifies

- [ ] `framework/packages/platform/http/config/http.php` — add `http.debug.*` keys + defaults
- [ ] `framework/packages/platform/http/config/rules.php` — enforce shape for `http.debug.*`
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — register guard/router/handlers
- [ ] `framework/packages/platform/http/README.md` — section “Debug endpoints” (handlers dispatch model; why dev_only; redaction rules)
- [ ] `docs/ssot/http-system-endpoints.md` — add dev-only `/_debug/*` endpoint set + guard/disabled behavior policy

#### Package skeleton (if type=package)

N/A (package `platform/http` already exists; this epic adds debug diagnostics slice)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/http/config/http.php`
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
  - [ ] `framework/packages/platform/http/config/rules.php` enforces shape

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
- [ ] If metrics/spans/logs exist → `framework/packages/platform/http/tests/Integration/DebugEndpointsGuardTest.php`
- [ ] If redaction exists → `framework/packages/platform/http/tests/Integration/DebugHeadersRedactsSensitiveHeadersTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit/Contract:
  - N/A
- Integration:
  - [ ] `framework/packages/platform/http/tests/Integration/DebugEndpointsGuardTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/DebugHeadersRedactsSensitiveHeadersTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/DebugConfigShowsSourcesButNotSecretsTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/DebugDisabledAndForbiddenTriggerCanonicalErrorFlowTest.php`
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
  - [ ] `framework/packages/platform/http/README.md`
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
owner_path: "framework/packages/platform/routing/"

package_id: "platform/routing"
composer: "coretsia/platform-routing"
kind: runtime
module_id: "platform.routing"

goal: "Deterministic routes artifact компілюється і читається runtime router’ом (artifact-only у prod), із reserved endpoints guard."
provides:
- "Real deterministic routes artifact: `skeleton/var/cache/<appId>/routes.php` (schemaVersion pinned; rerun → no diff)"
- "Runtime `CompiledRouter` (artifact-only policy) + writes `ContextKeys::PATH_TEMPLATE` (low-cardinality)"
- "Compile-time reserved endpoints guard: uses `platform/http` `SystemRouteTable` + `ReservedPathMatcher` (NO DUPES)"

tags_introduced:
- "routing.route_provider"

config_roots_introduced:
- "routing"

artifacts_introduced: []
adr: "docs/adr/ADR-0041-platform-routing-compiled-routes.md"
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
      and introduces the canonical owner constant in `framework/packages/platform/routing/src/Provider/Tags.php`.

- Tag usage (MUST) — make ordering source-of-truth explicit
  - `RouteProviderRegistry` MUST enumerate providers ONLY via:
    - `Coretsia\Foundation\Tag\TagRegistry::all(Tags::ROUTE_PROVIDER)`
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
  - `Coretsia\Foundation\Context\ContextKeys`
  - `Coretsia\Foundation\Discovery\DeterministicOrder`
  - `Coretsia\Foundation\Tag\TagRegistry`

### Entry points / integration points (MUST)

- CLI:
  - `routes:compile` → `framework/packages/platform/routing/src/Console/RoutesCompileCommand.php`
- HTTP:
  - N/A (routing consumed by http-app RouterMiddleware; owner=`platform/http-app`)
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - writes: `skeleton/var/cache/<appId>/routes.php`
  - reads: `skeleton/var/cache/<appId>/routes.php` (runtime)
  - validates header + payload schema on read

### Deliverables (MUST)

#### Creates

Package skeleton:
- [ ] `framework/packages/platform/routing/composer.json`
- [ ] `framework/packages/platform/routing/src/Module/RoutingModule.php`
- [ ] `framework/packages/platform/routing/src/Provider/RoutingServiceProvider.php`
- [ ] `framework/packages/platform/routing/src/Provider/RoutingServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/routing/config/routing.php`
- [ ] `framework/packages/platform/routing/config/rules.php`
- [ ] `framework/packages/platform/routing/README.md`
- [ ] `framework/packages/platform/routing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

Tags + registry:
- [ ] `framework/packages/platform/routing/src/Provider/Tags.php` — tag constants
- [ ] `framework/packages/platform/routing/src/Compile/RouteProviderRegistry.php` — collects `RouteProviderInterface` via tag `routing.route_provider`

Compile:
- [ ] `framework/packages/platform/routing/src/Compile/RoutesCompiler.php` — deterministic compile (providers deterministic; per-provider order preserved)
- [ ] `framework/packages/platform/routing/src/Compile/ReservedRoutesGuard.php` — blocks reserved paths/prefixes using `SystemRouteTable` + `ReservedPathMatcher`

Artifacts:
- [ ] `framework/packages/platform/routing/src/Artifacts/RoutesArtifactWriter.php` — atomic write + deterministic bytes
- [ ] `framework/packages/platform/routing/src/Artifacts/RoutesArtifactReader.php` — validates header + payload schemaVersion
- [ ] `framework/packages/platform/routing/src/Artifacts/RoutesArtifactHeader.php` — header VO (schemaVersion pinned; no timestamps)
- [ ] `framework/packages/platform/routing/src/Artifacts/Php/StablePhpArrayDumper.php`
  - [ ] stable key ordering (`strcmp`) for maps at every depth,
  - [ ] consistent indentation,
  - [ ] LF-only + final newline,
  - [ ] no var_export locale dependence, no timestamps.

Runtime:
- [ ] `framework/packages/platform/routing/src/Runtime/CompiledRouter.php` — implements `RouterInterface`, reads artifact, writes `ContextKeys::PATH_TEMPLATE`
- [ ] `framework/packages/platform/routing/src/Runtime/PathNormalizer.php` — normalization MUST match reserved matcher policy

CLI:
- [ ] `framework/packages/platform/routing/src/Console/RoutesCompileCommand.php` — writes artifact, safe output, supports `--format=json`
  - [ ] `--format=json` output MUST serialize via `Coretsia\Foundation\Serialization\StableJsonEncoder`
  - [ ] JSON output MUST be deterministic: recursive map-key sort `strcmp`, lists preserve order
  - [ ] JSON output MUST NOT include absolute paths, raw config values, or secrets

Errors:
- [ ] `framework/packages/platform/routing/src/Exception/RoutesArtifactInvalidException.php` — `CORETSIA_ROUTES_ARTIFACT_INVALID`
- [ ] `framework/packages/platform/routing/src/Exception/ReservedRouteViolationException.php` — `CORETSIA_ROUTING_RESERVED_ROUTE_FORBIDDEN`

Docs:
- [ ] `docs/ssot/routing-artifact.md` — artifact schemaVersion + determinism rules
- [ ] `docs/guides/routing.md` — how to register providers + run compile

Tests:
- [ ] `framework/packages/platform/routing/tests/Unit/RouteMatcherStaticAndParamsTest.php`
- [ ] `framework/packages/platform/routing/tests/Unit/ReservedRoutesGuardRejectsReservedPathsTest.php`

- [ ] `framework/packages/platform/routing/tests/Contract/RoutesArtifactHeaderShapeContractTest.php`
- [ ] `framework/packages/platform/routing/tests/Contract/RoutesCompileIsDeterministicTest.php`
- [ ] `framework/packages/platform/routing/tests/Contract/ReservedGuardUsesSameMatcherAsHttpContractTest.php`
- [ ] `framework/packages/platform/routing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/platform/routing/tests/Contract/ArtifactBytesRerunNoDiffContractTest.php`
- [ ] `framework/packages/platform/routing/tests/Contract/RoutesArtifactHeaderMatchesKernelPolicyContractTest.php`
- [ ] `framework/packages/platform/routing/tests/Contract/ReservedGuardSemanticsMatchHttpMatcherContractTest.php`

- [ ] `framework/packages/platform/routing/tests/Integration/RoutesCompileWritesArtifactTest.php`
- [ ] `framework/packages/platform/routing/tests/Integration/RoutesArtifactRerunNoDiffTest.php`
- [ ] `framework/packages/platform/routing/tests/Integration/CliRoutesCompileCommandWritesArtifactTest.php`
- [ ] `framework/packages/platform/routing/tests/Integration/CompiledRouterSetsPathTemplateInContextStoreTest.php`

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0041-platform-routing-compiled-routes.md`
- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/routing-artifact.md`
- [ ] deptrac expectations updated: allow `platform/routing` → `platform/http` (compile-time) while keeping `platform/http` boundary intact
- [ ] `docs/ssot/tags.md` — register:
  - [ ] `routing.route_provider` (owner `platform/routing`) - RouteProvider discovery via Foundation TagRegistry; providers are ordered by TagRegistry (priority DESC, serviceId ASC), per-provider route order is preserved.
- [ ] `docs/ssot/config-roots.md` — register:
  - [ ] `routing | platform/routing | framework/packages/platform/routing/config/routing.php | framework/packages/platform/routing/config/rules.php | compiled routing artifact + runtime router settings`
- [ ] `docs/ssot/artifacts.md` — update the existing `routes@1` registry row if notes/path-shape need refinement; MUST NOT add a duplicate registry row
  - [ ] `routes` (owner `platform/routing`) - { "_meta": <header>, "payload": <schema> } | docs/ssot/routing-artifact.md | Deterministic bytes; LF-only + final newline; no timestamps/abs paths/secrets; rerun-no-diff.

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/routing/composer.json`
- [ ] `framework/packages/platform/routing/src/Module/RoutingModule.php`
- [ ] `framework/packages/platform/routing/src/Provider/RoutingServiceProvider.php`
- [ ] `framework/packages/platform/routing/config/routing.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/platform/routing/config/rules.php`
- [ ] `framework/packages/platform/routing/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/routing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/routing/config/routing.php`
- [ ] Keys (dot):
  - [ ] `routing.enabled` = true
  - [ ] `routing.compile.enabled` = true
  - [ ] `routing.runtime.enabled` = true
  - [ ] `routing.artifacts.path` = 'skeleton/var/cache/<appId>/routes.php'
  - [ ] `routing.compile.strict_reserved_guard` = true
- [ ] Rules:
  - [ ] `framework/packages/platform/routing/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/routing/src/Provider/Tags.php`
  - [ ] constants:
    - [ ] `ROUTE_PROVIDER = 'routing.route_provider'`
- [ ] `cli.command` usage rule (MUST):
  - [ ] because owner package `platform/cli` is a forbidden compile-time dependency here, any mirror constant for `cli.command`
    MUST be package-internal only (for example a private constant inside the wiring class)
  - [ ] `cli.command` MUST NOT be exposed from `src/Provider/Tags.php` as public API of `platform/routing`
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
  - [ ] `skeleton/var/cache/<appId>/routes.php` (schemaVersion pinned; deterministic bytes; rerun → no diff)
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

- [ ] If Context writes exist → `framework/packages/platform/routing/tests/Integration/CompiledRouterSetsPathTemplateInContextStoreTest.php`
- [ ] If `kernel.reset` used → `N/A` (unless caching added)
- [ ] If metrics/spans/logs exist → `framework/packages/platform/routing/tests/Contract/RoutesCompileIsDeterministicTest.php` + observability assertions where applicable
- [ ] If redaction exists → `framework/packages/platform/routing/tests/Contract/ArtifactBytesRerunNoDiffContractTest.php` (indirect) + CLI output safety assertions in CLI integration test

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/platform/routing/tests/Fixtures/<App>/...` (only if wiring requires boot)
- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions (if used in tests)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/routing/tests/Unit/RouteMatcherStaticAndParamsTest.php`
  - [ ] `framework/packages/platform/routing/tests/Unit/ReservedRoutesGuardRejectsReservedPathsTest.php`
- Contract:
  - [ ] `framework/packages/platform/routing/tests/Contract/RoutesArtifactHeaderShapeContractTest.php`
  - [ ] `framework/packages/platform/routing/tests/Contract/RoutesCompileIsDeterministicTest.php`
  - [ ] `framework/packages/platform/routing/tests/Contract/ReservedGuardUsesSameMatcherAsHttpContractTest.php`
  - [ ] `framework/packages/platform/routing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/routing/tests/Contract/ArtifactBytesRerunNoDiffContractTest.php`
  - [ ] `framework/packages/platform/routing/tests/Contract/RoutesArtifactHeaderMatchesKernelPolicyContractTest.php`
  - [ ] `framework/packages/platform/routing/tests/Contract/ReservedGuardSemanticsMatchHttpMatcherContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/routing/tests/Integration/RoutesCompileWritesArtifactTest.php`
  - [ ] `framework/packages/platform/routing/tests/Integration/RoutesArtifactRerunNoDiffTest.php`
  - [ ] `framework/packages/platform/routing/tests/Integration/CliRoutesCompileCommandWritesArtifactTest.php`
  - [ ] `framework/packages/platform/routing/tests/Integration/CompiledRouterSetsPathTemplateInContextStoreTest.php`
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
  - [ ] `framework/packages/platform/routing/README.md`
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
owner_path: "framework/packages/platform/http-app/"

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
adr: "docs/adr/ADR-0042-platform-http-app-router-middleware-action-invoker.md"
ssot_refs:
- "docs/ssot/http-middleware-catalog.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `3.130.0` — `platform/routing` provides `RouterInterface` implementation (expected binding = `CompiledRouter`)
  - Phase 0 HTTP stack model exists in `platform/http` and executes `http.middleware.app` slot.

- Required deliverables (exact paths):
  - `framework/packages/platform/http/src/Provider/Tags.php` — defines middleware slots incl. `http.middleware.app`
  - `docs/ssot/http-middleware-catalog.md` — canonical placement/priority table
  - `framework/packages/core/kernel/tests/Fixtures/ExpressApp/config/modules.php` — provided by 2.20.0

- Tag usage rule (MUST):
  - for `http.middleware.app`, runtime code MUST use the owner public constant from `platform/http`
    (canonical owner constant in this roadmap set: `Coretsia\Http\Provider\Tags::MIDDLEWARE_APP`)
  - for `error.mapper`, `platform/errors` is a forbidden compile-time dependency here,
    so runtime code MUST NOT import `Coretsia\Errors\Provider\Tags`
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
  - reads (indirect, via `RouterInterface` impl): `skeleton/var/cache/<appId>/routes.php`

### Deliverables (MUST)

#### Creates

Package skeleton:
- [ ] `framework/packages/platform/http-app/composer.json` — package manifest
- [ ] `framework/packages/platform/http-app/src/Module/HttpAppModule.php` — runtime module entry
- [ ] `framework/packages/platform/http-app/src/Provider/HttpAppServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/http-app/src/Provider/HttpAppServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/http-app/config/http_app.php` — config subtree (no repeated root)
- [ ] `framework/packages/platform/http-app/config/rules.php` — config rules
- [ ] `framework/packages/platform/http-app/README.md` — docs (Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/http-app/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

Implementation:
- [ ] `framework/packages/platform/http-app/src/Middleware/RouterMiddleware.php` — routing boundary + dispatch
- [ ] `framework/packages/platform/http-app/src/Action/ActionInvoker.php` — invokes handler; spans/metrics/logs
  - [ ] MUST resolve handler ids only through `http_app.handlers.map`
  - [ ] MUST NOT perform implicit FQCN/service-id fallback
- [ ] `framework/packages/platform/http-app/src/Action/ArgumentResolver/ArgumentResolver.php` — resolves args (no interface autowire)
- [ ] `framework/packages/platform/http-app/src/Action/ArgumentResolver/RouteParamsAccessor.php` — helper
- [ ] `framework/packages/platform/http-app/src/Response/ActionResponseMapper.php` — maps return value → ResponseInterface (json/html)
  - [ ] If `ActionResponseMapper` serializes JSON, it MUST:
    - [ ] use `Coretsia\Foundation\Serialization\StableJsonEncoder` as the single JSON writer
    - [ ] not use tooling `internal-toolkit`
    - [ ] not emit floats/NaN/INF
    - [ ] produce stable bytes (recursive map-key sort `strcmp`, lists preserve order)
    - [ ] MUST NOT use ad-hoc `json_encode` directly in mapper logic

Exceptions + mapping:
- [ ] `framework/packages/platform/http-app/src/Exception/HttpNotFoundException.php` — `CORETSIA_HTTP_404`
- [ ] `framework/packages/platform/http-app/src/Exception/HttpMethodNotAllowedException.php` — `CORETSIA_HTTP_405`
- [ ] `framework/packages/platform/http-app/src/Error/HttpAppProblemMapper.php` — implements `ExceptionMapperInterface`, tag `error.mapper`

Docs:
- [ ] `docs/guides/http-app.md` — how to register handlers + how 404/405 mapping works

Tests:
- [ ] `framework/packages/platform/http-app/tests/Unit/ArgumentResolverAllowlistTest.php`
- [ ] `framework/packages/platform/http-app/tests/Unit/ActionResponseMapperJsonAndHtmlTest.php`
- [ ] `framework/packages/platform/http-app/tests/Integration/RouterMiddlewareDispatchesMatchedHandlerTest.php`
- [ ] `framework/packages/platform/http-app/tests/Integration/NotFoundAndMethodNotAllowedTriggerCanonicalErrorFlowTest.php`
- [ ] `framework/packages/platform/http-app/tests/Integration/ActionInvokerCreatesSpanNoopSafeTest.php`
- [ ] `framework/packages/platform/http-app/tests/Contract/RouterMiddlewareWiringContractTest.php`
- [ ] `framework/packages/platform/http-app/tests/Integration/NotFound405GoThroughCanonicalErrorFlowTest.php`

Framework / E2E:
- [ ] `framework/tools/tests/Integration/Http/HelloRouteEndToEndTest.php` — routing+http-app+http

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0042-platform-http-app-router-middleware-action-invoker.md`
- [ ] `docs/ssot/http-middleware-catalog.md` — add/update row for `RouterMiddleware` (`http.middleware.app` priority `100`)
- [ ] `framework/tools/spikes/fixtures/http_middleware_catalog.php` — add/update row for `RouterMiddleware` (`http.middleware.app` priority `100`)
- [ ] `docs/ssot/config-roots.md` — add row:
  - [ ] `http_app | platform/http-app | framework/packages/platform/http-app/config/http_app.php | framework/packages/platform/http-app/config/rules.php | app-layer http routing+invocation settings`
- [ ] `docs/ssot/INDEX.md` — (only if config-roots.md is not already indexed) ensure it remains indexed as canonical SSoT.

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/http-app/composer.json`
- [ ] `framework/packages/platform/http-app/src/Module/HttpAppModule.php`
- [ ] `framework/packages/platform/http-app/src/Provider/HttpAppServiceProvider.php`
- [ ] `framework/packages/platform/http-app/config/http_app.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/platform/http-app/config/rules.php`
- [ ] `framework/packages/platform/http-app/README.md`
- [ ] `framework/packages/platform/http-app/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/http-app/config/http_app.php`
- [ ] Keys (dot):
  - [ ] `http_app.enabled` = true
  - [ ] `http_app.handlers.map` = []  # handlerId => serviceId
  - [ ] `http_app.arguments.allow_request` = true
  - [ ] `http_app.arguments.allow_route_params` = true
  - [ ] `http_app.arguments.allow_container_types` = []  # list<class-string> allowlist (concrete only)
  - [ ] `http_app.response.json.enabled` = true
  - [ ] `http_app.response.html.enabled` = true
- [ ] Rules:
  - [ ] `framework/packages/platform/http-app/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (uses existing owner tags; this epic MUST NOT create a public `src/Provider/Tags.php` owner file)
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
- [ ] If metrics/spans/logs exist → `framework/packages/platform/http-app/tests/Integration/ActionInvokerCreatesSpanNoopSafeTest.php`
- [ ] If redaction exists → `framework/packages/platform/http-app/tests/Integration/NotFound405GoThroughCanonicalErrorFlowTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/ExpressApp/config/modules.php`
- [ ] Fake adapters:
  - [ ] (as needed) `FakeTracer` / `FakeMetrics` / `FakeLogger`

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/http-app/tests/Unit/ArgumentResolverAllowlistTest.php`
  - [ ] `framework/packages/platform/http-app/tests/Unit/ActionResponseMapperJsonAndHtmlTest.php`
- Contract:
  - [ ] `framework/packages/platform/http-app/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/http-app/tests/Contract/RouterMiddlewareWiringContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/http-app/tests/Integration/RouterMiddlewareDispatchesMatchedHandlerTest.php`
  - [ ] `framework/packages/platform/http-app/tests/Integration/NotFoundAndMethodNotAllowedTriggerCanonicalErrorFlowTest.php`
  - [ ] `framework/packages/platform/http-app/tests/Integration/ActionInvokerCreatesSpanNoopSafeTest.php`
  - [ ] `framework/packages/platform/http-app/tests/Integration/NotFound405GoThroughCanonicalErrorFlowTest.php`
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
  - [ ] `framework/packages/platform/http-app/README.md`
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

### 3.145.0 Web app entrypoint: real boot + module registration + browser-visible output (MUST) [IMPL]

---
type: skeleton
phase: 3
epic_id: "3.145.0"
owner_path: "skeleton/apps/web/"

goal: "Замість Phase-2 stub (503 boot-not-ready) забезпечити реальну точку входу web-app: kernel boot → container → platform/http + platform/http-app, щоб можна було зайти в браузері і отримати 200 з demo output."
provides:
- "Реальний boot для `skeleton/apps/web/public/index.php` через core/kernel (mode preset + artifacts) без runtime сканувань"
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

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 2.50.0 — front controller stub exists (index.php + serve script)
  - 1.280.0–1.350.0 — core/kernel boot/config/artifacts/container compile baseline exists
  - 3.50.0 — platform/http runtime exists
  - 3.130.0 — platform/routing exists (routes artifact)
  - 3.140.0 — platform/http-app exists (router middleware + action invoker)
  - 3.10.0 + 3.40.0 — errors + problem-details exist (щоб 500/404 мали канонічний flow)

- Required deliverables (exact paths):
  - `skeleton/apps/web/public/index.php` (from 2.50.0)

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
  - `skeleton/apps/web/public/index.php` MUST dispatch to real boot when artifacts are ready
- Dev flow:
  - `framework/bin/serve` SHOULD compile required artifacts deterministically before starting server

### Deliverables (MUST)

#### Creates

App config (minimal, для demo):
- [ ] `skeleton/apps/web/config/app.php`
  - [ ] returns subtree with stable `id` used for `skeleton/var/cache/<appId>/...`
  - [ ] returns subtree with default `mode` = `express` for demo
  - [ ] runtime reads these values as global config keys `app.id` and `app.mode`
- [ ] `skeleton/apps/web/config/http_app.php`
  - [ ] returns subtree only (no repeated root)
  - [ ] MUST define deterministic `http_app.handlers.map` for the demo handler ids used by compiled routes
- [ ] Normative:
  - [ ] this epic MUST NOT introduce `skeleton/apps/web/config/modules.php` as a runtime module-selection path
  - [ ] module selection for web boot MUST remain kernel-owned and single-choice:
    - [ ] selected preset / mode files
    - [ ] composer metadata discovery
  - [ ] this epic MAY add skeleton-local app code (`AppModule`, providers, route provider, actions),
    but it MUST NOT add a parallel modules-list config file that bypasses the cemented ModulePlan policy

Demo module & action (показати “реєстрацію сервісів”):
- [ ] `skeleton/apps/web/src/Module/AppModule.php`
- [ ] `skeleton/apps/web/src/Provider/AppServiceProvider.php`
- [ ] `skeleton/apps/web/src/Routing/AppRouteProvider.php`
  - [ ] implements `Coretsia\Contracts\Routing\RouteProviderInterface`
  - [ ] provides deterministic route definitions for the demo web app
  - [ ] MUST define `GET /` with a stable `handlerId` consumed by `platform/http-app`
- [ ] `skeleton/apps/web/src/Http/Action/HomeAction.php`
  - [ ] returns deterministic response (JSON: `{ "ok": true, "app": "<appId>" }`)
  - [ ] handler id used by the route provider MUST be mapped in `skeleton/apps/web/config/http_app.php`

Real boot:
- [ ] `skeleton/apps/web/public/_boot_web.php`
  - [ ] MUST:
    - [ ] build Kernel runtime for selected `app.id` / `app.mode`
    - [ ] refuse with deterministic 503 payload if required artifacts missing
      (`skeleton/var/cache/<appId>/container.php`, `skeleton/var/cache/<appId>/config.php`, `skeleton/var/cache/<appId>/routes.php`)
    - [ ] on success: create request from globals → dispatch via http kernel → emit response
- [ ] `framework/tools/tests/Integration/WebEntryPointHelloEndToEndTest.php`
  - [ ] starts built-in server → GET `/` → asserts 200 + deterministic body
  - [ ] MUST prove that the matched app route `/` wins over `platform/http` fallback `/`
  - [ ] assertion target MUST be the demo app response body from `HomeAction`, not the fallback “Hello Coretsia” page
- [ ] `framework/tools/tests/Integration/WebEntryPointMissingArtifactsReturns503Test.php`
  - [ ] starts built-in server without required artifacts → GET `/` → asserts deterministic 503 body from `_boot_not_ready_payload.php`

#### Modifies

- [ ] `skeleton/apps/web/public/index.php`
  - [ ] replace stub call with:
    - [ ] call `_boot_web.php` when ready
    - [ ] keep `_boot_not_ready_payload.php` as fallback payload (single source for 503 body)
- [ ] `skeleton/apps/web/src/Provider/AppServiceProvider.php`
  - [ ] registers `HomeAction`
  - [ ] registers `AppRouteProvider`
  - [ ] adds tag `routing.route_provider` for `AppRouteProvider` using the owner public constant from `platform/routing`
- [ ] `framework/bin/serve`
  - [ ] before starting server, run deterministic compile chain (single-choice):
    - [ ] `coretsia config:compile`
    - [ ] `coretsia routes:compile`
  - [ ] `framework/bin/serve` MUST resolve selected HTTP app target via `--app=<web|api>` with default = `web`
  - [ ] compile + serve flow in this epic is executed for the selected app target only
  - [ ] docroot MUST resolve to `skeleton/apps/<app>/public`
  - [ ] NOTE:
    - [ ] `container.php` is produced by `config:compile`; `container:compile` is NOT a separate required step here.
  - [ ] MUST remain CWD-independent and repo-root anchored

### Cross-cutting (only if applicable; otherwise `N/A`)

- [ ] Security:
  - [ ] MUST NOT reflect request headers/cookies/body in demo output
  - [ ] MUST NOT leak env/config dumps in errors

### Tests (MUST)

- [ ] `framework/tools/tests/Integration/WebEntryPointHelloEndToEndTest.php`
- [ ] `framework/tools/tests/Integration/WebEntryPointMissingArtifactsReturns503Test.php`

### DoD (MUST)

- [ ] `composer serve` (або еквівалент) запускає сервер
- [ ] Після compile chain: GET `/` у браузері повертає 200 + deterministic body
- [ ] Без artifacts: deterministic 503 з payload з `_boot_not_ready_payload.php`
- [ ] Реєстрація сервісів продемонстрована через skeleton AppModule/AppServiceProvider
- [ ] Module selection remains compliant with kernel-owned policy:
  - [ ] this epic does NOT introduce `skeleton/apps/web/config/modules.php`
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
    - [ ] `skeleton/apps/web/`
    - [ ] `skeleton/apps/api/`
    - [ ] `skeleton/apps/console/`
    - [ ] `skeleton/apps/worker/`
  - [ ] it MUST NOT redefine module-selection policy or imply that `web` is the only valid app target in the skeleton

---

### 3.150.0 CORS middleware (inside platform/http) (MUST) [IMPL]

---
type: package
phase: 3
epic_id: "3.150.0"
owner_path: "framework/packages/platform/http/"

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

adr: "docs/adr/ADR-0043-cors-middleware.md"
ssot_refs:
- "docs/ssot/http-middleware-catalog.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - Phase 0 `platform/http` stack model exists and executes `http.middleware.system_pre`.

- Required deliverables (exact paths):
  - `docs/ssot/http-middleware-catalog.md` — middleware catalog (must be updated with CORS row)
  - `framework/tools/spikes/fixtures/http_middleware_catalog.php` — rails fixture (must be updated with CORS row)
  - `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — place to wire middleware

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

- [ ] `framework/packages/platform/http/src/Cors/CorsPolicy.php` — VO (allowed origins/methods/headers, deterministic)
- [ ] `framework/packages/platform/http/src/Middleware/CorsMiddleware.php` — PSR-15, handles preflight + response headers
- [ ] `framework/packages/platform/http/src/Exception/CorsBlockedException.php` — `CORETSIA_HTTP_CORS_BLOCKED`
- [ ] `framework/packages/platform/http/src/Error/CorsExceptionMapper.php`
  - [ ] implements `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - [ ] maps `CorsBlockedException` → `ErrorDescriptor` with optional HTTP hint `403`
  - [ ] MUST NOT leak raw `Origin` / request header values (`hash/len` only)
  - [ ] MUST be registered via DI tag `error.mapper` using a package-local internal mirror constant
    - [ ] canonical string literal `'error.mapper'` is allowed only as fallback
    - [ ] MUST NOT import `platform/errors`, because it is a forbidden compile-time dependency here
- [ ] `docs/guides/cors.md` — policy examples + security notes

Tests:
- [ ] `framework/packages/platform/http/tests/Contract/CorsHeadersDeterministicOrderContractTest.php`
- [ ] `framework/packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/CorsPreflightReturns204WithDeterministicHeadersTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/CorsBlockedOriginIncrementsMetricNoopSafeTest.php`

#### Modifies

- [ ] `framework/packages/platform/http/config/http.php` — add `http.cors.*` keys + defaults
- [ ] `framework/packages/platform/http/config/rules.php` — enforce shape for `http.cors.*`
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — registers + tags CORS middleware (system_pre 840)
- [ ] `framework/packages/platform/http/README.md` — “CorsMiddleware” section (slot/priority/override recipe)
- [ ] `docs/ssot/http-middleware-catalog.md` — add/update row for `CorsMiddleware` (priority 840)
- [ ] `framework/tools/spikes/fixtures/http_middleware_catalog.php` — add/update row for `CorsMiddleware` (priority 840)
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0043-cors-middleware.md`

#### Package skeleton (if type=package)

N/A (package `platform/http` already exists; this epic adds CORS slice only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.cors.enabled` = false
  - [ ] `http.cors.allowed_origins` = []
  - [ ] `http.cors.allowed_methods` = ['GET','POST','PUT','PATCH','DELETE','OPTIONS']
  - [ ] `http.cors.allowed_headers` = []
  - [ ] `http.cors.exposed_headers` = ['X-Correlation-Id','X-Request-Id']
  - [ ] `http.cors.allow_credentials` = false
  - [ ] `http.cors.max_age` = 600
- [ ] Rules:
  - [ ] `framework/packages/platform/http/config/rules.php` enforces shape

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
- [ ] If metrics/spans/logs exist → `framework/packages/platform/http/tests/Integration/CorsBlockedOriginIncrementsMetricNoopSafeTest.php`
- [ ] If redaction exists → `framework/packages/platform/http/tests/Integration/CorsBlockedOriginIncrementsMetricNoopSafeTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `framework/packages/platform/http/tests/Contract/CorsHeadersDeterministicOrderContractTest.php`
  - [ ] `framework/packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php`
- Integration:
  - [ ] `framework/packages/platform/http/tests/Integration/CorsPreflightReturns204WithDeterministicHeadersTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/CorsBlockedOriginIncrementsMetricNoopSafeTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/CorsBlockedOriginTriggersCanonicalErrorFlowTest.php`
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
  - [ ] `framework/packages/platform/http/README.md`
  - [ ] `docs/ssot/http-middleware-catalog.md`
  - [ ] `docs/guides/cors.md`
- [ ] Catalog fixture + SSoT doc updated in the same PR as middleware code
- [ ] Slot/priority locked by contract test
- [ ] What problem this epic solves:
  - [ ] Централізований CORS з deterministic поведінкою (preflight 204, headers order stable).
  - [ ] Працює **до** negotiation/routing/auth/csrf і не вимагає skeleton defaults.
- [ ] Non-goals / out of scope:
  - [ ] Не логить raw Origin/headers; лише safe summary.
  - [ ] Не вводить нові label keys (лише allowlist).
- [ ] Cutline impact: blocks Phase 3 cutline
- [ ] Phase -1/0 catalog lock (MUST):
  - [ ] Any middleware added/changed MUST update:
    - [ ] Phase 0 SSoT: `docs/ssot/http-middleware-catalog.md`
    - [ ] Phase -1 rails fixture: `framework/tools/spikes/fixtures/http_middleware_catalog.php`
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
  - `framework/packages/platform/http/README.md` — package docs exists (this epic updates it)

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
- [ ] `framework/tools/spikes/fixtures/http_middleware_catalog.php` — add/update rows:
  - [ ] `MethodOverrideMiddleware` → slot `http.middleware.system_pre`, priority `820`
  - [ ] `ContentNegotiationMiddleware` → slot `http.middleware.system_pre`, priority `810`
- [ ] `framework/packages/platform/http/README.md` — sections updated for both middlewares (placement, override recipes)
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
- [ ] Catalog fixture stays synchronized with the SSoT catalog:
  - [ ] `framework/tools/spikes/fixtures/http_middleware_catalog.php`
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
owner_path: "framework/packages/platform/http/"

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

adr: "docs/adr/ADR-0044-method-override-middleware.md"
ssot_refs:
- "docs/ssot/http-middleware-catalog.md"
- "docs/ssot/http-method-override.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `3.160.0` — umbrella docs define placement and security policy

- Required deliverables (exact paths):
  - `docs/ssot/http-middleware-catalog.md` — catalog (must be updated/locked)
  - `framework/tools/spikes/fixtures/http_middleware_catalog.php` — rails fixture (must be updated/locked)
  - `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — wiring point

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

- [ ] `framework/packages/platform/http/src/MethodOverride/MethodOverridePolicy.php` — VO
- [ ] `framework/packages/platform/http/src/Middleware/MethodOverrideMiddleware.php` — PSR-15
- [ ] `framework/packages/platform/http/src/Exception/InvalidMethodOverrideException.php` — `CORETSIA_HTTP_METHOD_OVERRIDE_INVALID`
  - [ ] Add (or extend existing) mapper in `platform/http`:
    - [ ] `framework/packages/platform/http/src/Error/MethodOverrideExceptionMapper.php`
      - [ ] implements `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
      - [ ] maps `InvalidMethodOverrideException` → `ErrorDescriptor` with optional HTTP hint 400
      - [ ] must not leak raw header/query/body values (only safe tokens/len/hash)
      - [ ] registered via DI tag `error.mapper` using a package-local internal mirror constant
        - [ ] canonical string literal `'error.mapper'` is allowed only as fallback
        - [ ] MUST NOT import `platform/errors`, because it is a forbidden compile-time dependency here

Tests:
- [ ] `framework/packages/platform/http/tests/Integration/MethodOverrideAppliesBeforeRoutingTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/InvalidMethodOverrideTriggersCanonicalErrorFlowTest.php`
- [ ] `framework/packages/platform/http/tests/Unit/MethodOverridePolicyDefaultsAreSafeTest.php`

#### Modifies

- [ ] `framework/packages/platform/http/config/http.php` — add `http.method_override.*` keys + defaults
- [ ] `framework/packages/platform/http/config/rules.php` — enforce shape for `http.method_override.*`
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — wire middleware (system_pre 820)
- [ ] `framework/packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php` — extend shared catalog lock contract for `MethodOverrideMiddleware` (`system_pre` 820)
- [ ] `docs/ssot/http-middleware-catalog.md` — ensure row (system_pre 820)
- [ ] `framework/tools/spikes/fixtures/http_middleware_catalog.php` — ensure row (system_pre 820)
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0044-method-override-middleware.md`

#### Package skeleton (if type=package)

N/A (package `platform/http` already exists; this epic adds method-override slice only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.method_override.enabled` = false
  - [ ] `http.method_override.allow_from_headers` = true
  - [ ] `http.method_override.allow_from_query` = false
  - [ ] `http.method_override.only_if_original_post` = true
  - [ ] `http.method_override.allowed_methods` = ['PUT','PATCH','DELETE']
  - [ ] `http.method_override.param_name` = '_method'
  - [ ] `http.method_override.header_name` = 'X-Http-Method-Override'
- [ ] Rules:
  - [ ] `framework/packages/platform/http/config/rules.php` enforces shape

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
- [ ] If metrics/spans/logs exist → `framework/packages/platform/http/tests/Integration/MethodOverrideAppliesBeforeRoutingTest.php`
- [ ] If redaction exists → `framework/packages/platform/http/tests/Integration/InvalidMethodOverrideTriggersCanonicalErrorFlowTest.php`
- [ ] conflicting header/query source handling is proven by:
  - [ ] `framework/packages/platform/http/tests/Integration/ConflictingMethodOverrideSourcesTriggerCanonicalErrorFlowTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/http/tests/Unit/MethodOverridePolicyDefaultsAreSafeTest.php`
- Contract:
  - [ ] `framework/packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php`
- Integration:
  - [ ] `framework/packages/platform/http/tests/Integration/MethodOverrideAppliesBeforeRoutingTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/InvalidMethodOverrideTriggersCanonicalErrorFlowTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/ConflictingMethodOverrideSourcesTriggerCanonicalErrorFlowTest.php`
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
- [ ] Catalog fixture + SSoT doc updated in the same PR as middleware code
- [ ] Slot/priority locked by contract test
- [ ] What problem this epic solves:
  - [ ] Додає deterministic method override policy (header/query за allowlist) без дублювання у контролерах.
  - [ ] Забезпечує placement **до routing boundary** без дублювання body parsing логіки.
- [ ] Non-goals / out of scope:
  - [ ] Не дозволяє override за замовчуванням з body/query (безпековий default).
  - [ ] Не логить raw header values.
- [ ] Cutline impact: none
- [ ] Phase -1/0 catalog lock (MUST):
  - [ ] Any middleware added/changed MUST update:
    - [ ] `docs/ssot/http-middleware-catalog.md`
    - [ ] `framework/tools/spikes/fixtures/http_middleware_catalog.php`
  - [ ] Wiring MUST match priority table:
    - [ ] MethodOverride: `system_pre` prio 820

---

### 3.180.0 Content negotiation middleware (inside platform/http) (SHOULD) [IMPL]

---
type: package
phase: 3
epic_id: "3.180.0"
owner_path: "framework/packages/platform/http/"

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

adr: "docs/adr/ADR-0045-content-negotiation-middleware.md"
ssot_refs:
- "docs/ssot/http-middleware-catalog.md"
- "docs/ssot/http-negotiation.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `3.160.0` — umbrella docs define placement + negotiation semantics

- Required deliverables (exact paths):
  - `docs/ssot/http-middleware-catalog.md` — catalog (must be updated/locked)
  - `framework/tools/spikes/fixtures/http_middleware_catalog.php` — rails fixture (must be updated/locked)
  - `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — wiring point

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
  - `Coretsia\Foundation\Context\ContextKeys`

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

- [ ] `framework/packages/platform/http/src/Negotiation/ContentNegotiationPolicy.php` — VO
- [ ] `framework/packages/platform/http/src/Middleware/ContentNegotiationMiddleware.php` — PSR-15
- [ ] `framework/packages/platform/http/src/Exception/NotAcceptableException.php` — `CORETSIA_HTTP_NOT_ACCEPTABLE` (406)
  - [ ] Canonical mapping for CORETSIA_HTTP_NOT_ACCEPTABLE (SHOULD)
    - [ ] If `strict_accept=true` yields 406:
      - [ ] Middleware MUST throw deterministic `NotAcceptableException` (errorCode `CORETSIA_HTTP_NOT_ACCEPTABLE`)
      - [ ] Mapping MUST be done via `error.mapper` to `ErrorDescriptor` (no ad-hoc rendering)
    - [ ] Add (or extend existing) mapper:
      - [ ] `framework/packages/platform/http/src/Error/NegotiationExceptionMapper.php`
        - [ ] implements `ExceptionMapperInterface`
        - [ ] maps `NotAcceptableException` → `ErrorDescriptor` with optional HTTP hint 406
        - [ ] never logs/prints raw `Accept` header (hash/len only)
        - [ ] registered via DI tag `error.mapper` using a package-local internal mirror constant
          - [ ] canonical string literal `'error.mapper'` is allowed only as fallback
          - [ ] MUST NOT import `platform/errors`, because it is a forbidden compile-time dependency here

Tests:
- [ ] `framework/packages/platform/http/tests/Integration/NegotiationSetsContextFormatTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/StrictAcceptTriggersCanonicalErrorFlowTest.php`
- [ ] `framework/packages/platform/http/tests/Unit/NegotiationPolicyDeterministicSelectionTest.php`
  - [ ] MUST prove:
    - [ ] query-param precedence over `Accept`
    - [ ] deterministic tie-break via `http.negotiation.supported` order
    - [ ] fallback to default when `strict_accept=false`

#### Modifies

- [ ] `framework/packages/platform/http/config/http.php` — add `http.negotiation.*` keys + defaults
- [ ] `framework/packages/platform/http/config/rules.php` — enforce shape for `http.negotiation.*`
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — wire middleware (system_pre 810)
- [ ] `framework/packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php` — extend shared catalog lock contract for `ContentNegotiationMiddleware` (`system_pre` 810)
- [ ] `docs/ssot/http-middleware-catalog.md` — ensure row (system_pre 810)
- [ ] `framework/tools/spikes/fixtures/http_middleware_catalog.php` — ensure row (system_pre 810)
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0045-content-negotiation-middleware.md`

#### Package skeleton (if type=package)

N/A (package `platform/http` already exists; this epic adds negotiation slice only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.negotiation.enabled` = false
  - [ ] `http.negotiation.supported` = ['json','html']
  - [ ] `http.negotiation.default` = 'json'
  - [ ] `http.negotiation.allow_query_param` = true
  - [ ] `http.negotiation.query_param_name` = '_format'
  - [ ] `http.negotiation.strict_accept` = false
- [ ] Rules:
  - [ ] `framework/packages/platform/http/config/rules.php` enforces shape

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

- [ ] If Context writes exist → `framework/packages/platform/http/tests/Integration/NegotiationSetsContextFormatTest.php`
- [ ] If `kernel.reset` used → `N/A`
- [ ] If metrics/spans/logs exist → `framework/packages/platform/http/tests/Integration/StrictAcceptTriggersCanonicalErrorFlowTest.php`
- [ ] If redaction exists → `framework/packages/platform/http/tests/Integration/StrictAcceptTriggersCanonicalErrorFlowTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/http/tests/Unit/NegotiationPolicyDeterministicSelectionTest.php`
- Contract:
  - [ ] `framework/packages/platform/http/tests/Contract/MiddlewareCatalogPriorityAndSlotLockTest.php`
- Integration:
  - [ ] `framework/packages/platform/http/tests/Integration/NegotiationSetsContextFormatTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/StrictAcceptTriggersCanonicalErrorFlowTest.php`
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
- [ ] Catalog fixture + SSoT doc updated in the same PR as middleware code
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
- [ ] Phase -1/0 catalog lock (MUST):
  - [ ] Any middleware added/changed MUST update:
    - [ ] `docs/ssot/http-middleware-catalog.md`
    - [ ] `framework/tools/spikes/fixtures/http_middleware_catalog.php`
  - [ ] Wiring MUST match priority table:
    - [ ] ContentNegotiation: `system_pre` prio 810

---

### 3.190.0 ProblemDetails HTML renderer + error pages (SHOULD) [IMPL]

---
type: package
phase: 3
epic_id: "3.190.0"
owner_path: "framework/packages/platform/problem-details/"

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

adr: "docs/adr/ADR-0046-problem-details-html-renderer-error-pages.md"
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - Baseline `platform/problem-details` exists and provides JSON renderer and HTTP error handling middleware.

- Required deliverables (exact paths):
  - `framework/packages/platform/problem-details/src/Http/Middleware/HttpErrorHandlingMiddleware.php` — existing middleware to be updated
  - `framework/packages/platform/problem-details/src/Renderer/ProblemDetailsJsonRenderer.php` — existing JSON renderer used via selector
  - `framework/packages/platform/problem-details/config/problem_details.php` — config file to extend

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
  - `Coretsia\Foundation\Context\ContextKeys`

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
  - `Coretsia\Foundation\Context\ContextKeys`

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

- [ ] `framework/packages/platform/problem-details/src/Renderer/ProblemDetailsHtmlRenderer.php` — deterministic escaped HTML
- [ ] `framework/packages/platform/problem-details/src/Renderer/ProblemDetailsRendererSelector.php` — selects json/html
- [ ] `framework/packages/platform/problem-details/src/Security/HtmlEscaper.php` — minimal escaper helper

Docs:
- [ ] `docs/guides/error-pages.md` — how to enable HTML + safety notes

Tests:
- [ ] `framework/packages/platform/problem-details/tests/Integration/AcceptHtmlReturnsTextHtmlErrorPageTest.php`
- [ ] `framework/packages/platform/problem-details/tests/Contract/ProblemDetailsHtmlDeterministicContractTest.php`
- [ ] `framework/packages/platform/problem-details/tests/Contract/HtmlRendererDeterministicBytesContractTest.php`
- [ ] `framework/packages/platform/problem-details/tests/Integration/FormatSelectionPrefersContextKeyOverAcceptTest.php`

#### Modifies

- [ ] `framework/packages/platform/problem-details/src/Renderer/ProblemDetailsJsonRenderer.php` — unchanged, but used via selector
- [ ] `framework/packages/platform/problem-details/src/Http/Middleware/HttpErrorHandlingMiddleware.php` — updated to use selector
- [ ] `framework/packages/platform/problem-details/config/problem_details.php` — add/extend html renderer settings
- [ ] `framework/packages/platform/problem-details/README.md` — selector rules + negotiation integration
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0046-problem-details-html-renderer-error-pages.md`

#### Package skeleton (if type=package)

N/A (package `platform/problem-details` already exists; this epic adds HTML renderer slice only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/problem-details/config/problem_details.php`
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
- [ ] If metrics/spans/logs exist → `framework/packages/platform/problem-details/tests/Integration/AcceptHtmlReturnsTextHtmlErrorPageTest.php`
- [ ] If redaction exists → `framework/packages/platform/problem-details/tests/Contract/ProblemDetailsHtmlDeterministicContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `framework/packages/platform/problem-details/tests/Contract/ProblemDetailsHtmlDeterministicContractTest.php`
  - [ ] `framework/packages/platform/problem-details/tests/Contract/HtmlRendererDeterministicBytesContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/problem-details/tests/Integration/AcceptHtmlReturnsTextHtmlErrorPageTest.php`
  - [ ] `framework/packages/platform/problem-details/tests/Integration/FormatSelectionPrefersContextKeyOverAcceptTest.php`
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
  - [ ] `framework/packages/platform/problem-details/README.md`
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

---

### 3.200.0 HTTP Performance Benchmarking Harness (MUST) [TOOLING]

---
type: tools
phase: 3
epic_id: "3.200.0"
owner_path: "framework/tools/benchmarks/http/"

goal: "Зацементувати deterministic HTTP benchmark harness для ключових micro-сценаріїв і ловити регресії продуктивності тільки у pinned benchmark environment до потрапляння змін у реліз."
provides:
- "Reference HTTP benchmarks for canonical scenarios: hello-world, JSON request/response, problem-details rendering"
- "Pinned-environment baseline + CI gate for regression detection"
- "Deterministic benchmark reports and comparison rules reused by later performance epics"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/metrics-policy.md"
- "docs/ssot/http-middleware-catalog.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.460.0 — CLI performance gate exists as baseline methodology for pinned-runner-only perf gating
  - 3.40.0 — problem-details runtime exists for benchmark scenario
  - 3.50.0 — HTTP runtime pipeline exists
  - 3.130.0 — routing runtime exists
  - 3.140.0 — http-app runtime exists
  - 3.145.0 — browser-visible web app entrypoint exists

- Required deliverables (exact paths):
  - `framework/packages/platform/http/` — HTTP runtime under test
  - `framework/packages/platform/problem-details/` — problem-details scenario under test
  - `framework/packages/platform/routing/` — compiled/runtime routing scenario under test
  - `framework/packages/platform/http-app/` — action invocation scenario under test
  - N/A beyond the runtime packages under test.
  - This epic creates and owns its dedicated benchmark fixture app:
    - `framework/tools/tests/Fixtures/HttpBenchApp/`

- Required config roots/keys:
  - `http.*` — HTTP runtime config used by fixture app
  - `problem_details.*` — problem-details scenario config
  - `app.id` — cache/app isolation for fixture benchmark app

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

- Required contracts / ports:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Server\RequestHandlerInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Log\LoggerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `devtools/internal-toolkit` (canonical tooling JSON writer / deterministic path helpers for benchmark report generation)

Forbidden:
- runtime imports from `framework/packages/**/src/**` by path (`require/include` with package src literals)
- live network calls during benchmark execution
- non-pinned shared-runner gating as source of truth

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
- Contracts:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

- CLI:
  - `composer benchmark:http` → `framework/tools/benchmarks/http/run.php`
  - `composer benchmark:http:gate` → `framework/tools/gates/http_benchmark_gate.php`

- HTTP:
  - benchmark scenarios MUST cover:
    - `GET /__bench/hello`
    - `POST /__bench/json`
    - `GET /__bench/problem`

- Kernel hooks/tags:
  - N/A

- Other runtime discovery / integration tags:
  - N/A

- Artifacts:
  - reads: `framework/tools/benchmarks/http/http.baseline.json`
  - writes: `framework/tools/benchmarks/http/http.report.json`

- Notes (only if applicable):
  - Gate MUST fail only in a dedicated pinned benchmark job.
  - Generic CI MAY run the harness in smoke/advisory mode, but MUST NOT be the release gate source of truth.

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/benchmarks/http/run.php` — canonical benchmark runner
- [ ] `framework/tools/benchmarks/http/BenchmarkConfig.php` — warmups/runs/scenarios/environment fingerprint policy
- [ ] `framework/tools/benchmarks/http/http.baseline.json` — pinned-runner baseline
- [ ] `framework/tools/benchmarks/http/http.report.schema.json` — report shape lock
- [ ] `framework/tools/benchmarks/http/README.md` — benchmark methodology + scenario list
- [ ] `framework/tools/gates/http_benchmark_gate.php` — baseline comparator gate
  - [ ] MUST use `framework/tools/spikes/_support/ConsoleOutput.php` only
  - [ ] line1 MUST be a deterministic CODE
  - [ ] line2+ MUST contain only safe diagnostics sorted `strcmp`
  - [ ] MUST NOT print absolute paths, raw payloads, headers, cookies, query strings, or stack traces
- [ ] `framework/tools/tests/Integration/Benchmarks/HttpBenchmarkHarnessTest.php` — harness proof
- [ ] `framework/tools/tests/Integration/Gates/HttpBenchmarkGateTest.php` — deterministic gate proof
- [ ] `framework/tools/tests/Unit/Benchmarks/HttpBenchmarkConfigTest.php`
- [ ] `framework/tools/tests/Unit/Benchmarks/HttpBenchmarkReportSchemaTest.php`
- [ ] `framework/tools/tests/Contract/Benchmarks/HttpBenchmarkOutputDoesNotLeakTest.php`
- [ ] `framework/tools/tests/Fixtures/HttpBenchApp/` — minimal benchmark app fixture
- [ ] `docs/ops/http-benchmarks.md` — how to run/update baseline and how CI interprets results

#### Modifies

- [ ] `framework/composer.json` — add scripts:
  - [ ] `benchmark:http`
  - [ ] `benchmark:http:gate`
- [ ] `composer.json` — add mirror scripts delegating to `framework/`
- [ ] `.github/workflows/ci.yml` — add dedicated pinned benchmark job
- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_HTTP_BENCHMARK_DEGRADED`
  - [ ] `CORETSIA_HTTP_BENCHMARK_BASELINE_MISSING`
  - [ ] `CORETSIA_HTTP_BENCHMARK_ENV_MISMATCH`
  - [ ] `CORETSIA_HTTP_BENCHMARK_RUN_FAILED`

#### Package skeleton (if type=package)

N/A

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/tools/benchmarks/http/BenchmarkConfig.php`
- [ ] Keys (dot):
  - [ ] `benchmark.http.warmup_runs` = 3
  - [ ] `benchmark.http.measure_runs` = 9
  - [ ] `benchmark.http.metric` = `median_wall_ms`
  - [ ] `benchmark.http.threshold_multiplier` = 1.15
  - [ ] `benchmark.http.require_env_fingerprint_match` = true
- [ ] Rules:
  - [ ] baseline MUST include PHP version, SAPI, opcache/JIT state, OS fingerprint, CPU class label

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `framework/tools/benchmarks/http/http.report.json` (deterministic key order, LF-only, final newline)
  - [ ] report JSON bytes MUST be produced via the canonical tooling JSON writer from `coretsia/devtools-internal-toolkit`
  - [ ] ad-hoc `json_encode` MUST NOT be used in benchmark tooling for baseline/report files
- [ ] Reads:
  - [ ] validates baseline/report shape before compare

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads:
  - N/A
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] benchmark runner MUST boot a fresh app/kernel per measured sample OR prove equivalent reset-safe isolation

#### Observability (policy-compliant)

- [ ] Metrics:
  - [ ] tool output only; no runtime metrics are required by this epic
- [ ] Logs:
  - [ ] benchmark output MUST contain only scenario name, run counts, median timings, threshold, outcome
  - [ ] MUST NOT dump raw request bodies, headers, cookies, query strings, or stack traces

#### Errors

- Exceptions introduced:
  - N/A
- [ ] Mapping:
  - [ ] deterministic tool exit codes via `ConsoleOutput` + registered error codes

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw payloads
  - [ ] raw headers/cookies
  - [ ] absolute paths
- [ ] Allowed:
  - [ ] scenario ids
  - [ ] safe counts
  - [ ] median/p95 numbers
  - [ ] environment class/fingerprint

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `framework/tools/tests/Integration/Benchmarks/HttpBenchmarkHarnessTest.php`
  (asserts: safe output shape only; no raw payloads)
- [ ] If redaction exists → `framework/tools/tests/Integration/Gates/HttpBenchmarkGateTest.php`
  (asserts: absolute paths and raw payloads never appear)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/tools/tests/Fixtures/HttpBenchApp/`
- [ ] Fake adapters:
  - [ ] FakeTracer
  - [ ] FakeMetrics
  - [ ] FakeLogger

### Tests (MUST)

- Unit:
  - [ ] `framework/tools/tests/Unit/Benchmarks/HttpBenchmarkConfigTest.php`
  - [ ] `framework/tools/tests/Unit/Benchmarks/HttpBenchmarkReportSchemaTest.php`
- Contract:
  - [ ] `framework/tools/tests/Contract/Benchmarks/HttpBenchmarkOutputDoesNotLeakTest.php`
- Integration:
  - [ ] `framework/tools/tests/Integration/Benchmarks/HttpBenchmarkHarnessTest.php`
  - [ ] `framework/tools/tests/Integration/Gates/HttpBenchmarkGateTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)
  - [ ] pinned benchmark CI job wired and isolated

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] baseline/report files are stable bytes
  - [ ] baseline/report JSON serialization MUST use the same canonical tooling JSON writer for both generation and comparison fixtures
  - [ ] scenario list order is fixed
  - [ ] comparison logic is deterministic
- [ ] Gate fails only on pinned benchmark environment
- [ ] Docs updated:
  - [ ] `framework/tools/benchmarks/http/README.md`
  - [ ] `docs/ops/http-benchmarks.md`
