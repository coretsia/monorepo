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

## PHASE 4 — RELEASE: express (Non-product doc)

### 4.10.0 coretsia/validation (reference validation engine) (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.10.0"
owner_path: "framework/packages/platform/validation/"

package_id: "platform/validation"
composer: "coretsia/platform-validation"
kind: runtime
module_id: "platform.validation"

goal: "Надати reference validation engine для array/map payload з deterministic violations (stable order + cap) та канонічним мапінгом ValidationException → ErrorDescriptor(httpStatus=422) через `error.mapper`, без HTTP, DTO reflection, files, database та i18n."
provides:
- "Reference `ValidatorInterface` implementation"
- "Deterministic violations: stable ordering + max cap"
- "Canonical 422 mapping via `error.mapper`"
- "Config-driven rule registry"
- "Dot-notation access for nested array/map payloads"

tags_introduced: []
config_roots_introduced:
- "validation"
  artifacts_introduced: []

adr: docs/adr/ADR-0047-validation-engine.md
ssot_refs:
- docs/ssot/config-roots.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.90.0 — validation contracts + error mapper contracts exist
  - 1.100.0 — ErrorDescriptor boundary/policy documented
  - 1.200.0 — foundation DI/config baseline exists
  - 1.205.0 — noop-safe observability baseline exists

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/src/Validation/ValidatorInterface.php` — public validation port
  - `framework/packages/core/contracts/src/Validation/ValidationException.php` — canonical exception
  - `framework/packages/core/contracts/src/Validation/ValidationResult.php` — canonical result shape
  - `framework/packages/core/contracts/src/Validation/Violation.php` — canonical violation shape
  - `framework/packages/core/contracts/src/Observability/Errors/ExceptionMapperInterface.php` — mapper port
  - `framework/packages/core/contracts/src/Observability/Errors/ErrorDescriptor.php` — descriptor shape
  - `framework/packages/core/contracts/src/Observability/Tracing/TracerPortInterface.php` — tracer port
  - `framework/packages/core/contracts/src/Observability/Metrics/MeterPortInterface.php` — meter port
  - `docs/adr/ADR-0047-validation-engine.md` — ADR locked before impl

- Required config roots/keys:
  - `validation` / `validation.enabled` — package enable flag
  - `validation` / `validation.fail_fast` — fail-fast policy
  - `validation` / `validation.max_violations` — deterministic cap
  - `validation` / `validation.rules.registry` — explicit rule registry

- Required tags:
  - `error.mapper` — reserved tag for exception mappers

- Required contracts / ports:
  - `Psr\Log\LoggerInterface` — logging without payload leakage
  - `Coretsia\Contracts\Validation\ValidatorInterface` — implemented by engine
  - `Coretsia\Contracts\Validation\ValidationException` — thrown by engine policy
  - `Coretsia\Contracts\Validation\ValidationResult` — returned result
  - `Coretsia\Contracts\Validation\Violation` — returned violations
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface` — mapper API
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor` — mapper output
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — tracing
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `platform/http-app`
- `platform/errors`
- `platform/problem-details`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Validation\ValidatorInterface`
  - `Coretsia\Contracts\Validation\ValidationException`
  - `Coretsia\Contracts\Validation\ValidationResult`
  - `Coretsia\Contracts\Validation\Violation`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - N/A
- Kernel hooks/tags:
  - `error.mapper` priority `700` meta `{handles:'ValidationException'}` → `platform/validation` `src/Error/ValidationExceptionMapper.php`
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/validation/composer.json` — package manifest
- [ ] `framework/packages/platform/validation/README.md` — package docs
- [ ] `framework/packages/platform/validation/src/Module/ValidationModule.php` — runtime module
- [ ] `framework/packages/platform/validation/src/Provider/ValidationServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/validation/src/Provider/ValidationServiceFactory.php` — stateless factory
- [ ] `framework/packages/platform/validation/config/validation.php` — defaults subtree
- [ ] `framework/packages/platform/validation/config/rules.php` — config rules
- [ ] `docs/guides/validation.md` — guide for rule syntax + determinism policy

- [ ] `framework/packages/platform/validation/src/Validation/Validator.php` — engine implementation
- [ ] `framework/packages/platform/validation/src/Validation/ViolationSorter.php` — stable ordering
- [ ] `framework/packages/platform/validation/src/Validation/ValidationRule.php` — normalized parsed rule descriptor
- [ ] `framework/packages/platform/validation/src/Error/ValidationExceptionMapper.php` — maps exception to descriptor
- [ ] `framework/packages/platform/validation/src/Support/Arr.php` — dot-notation accessor

- [ ] `framework/packages/platform/validation/src/Rule/RuleInterface.php` — internal rule contract
- [ ] `framework/packages/platform/validation/src/Rule/AbstractRule.php` — shared helpers
- [ ] `framework/packages/platform/validation/src/Rule/RuleParser.php` — parses textual rule entries
- [ ] `framework/packages/platform/validation/src/Rule/RuleFactory.php` — creates rule instances
- [ ] `framework/packages/platform/validation/src/Rule/RuleRegistry.php` — explicit registry

- [ ] `framework/packages/platform/validation/src/Rule/RequiredRule.php` — baseline presence rule
- [ ] `framework/packages/platform/validation/src/Rule/NullableRule.php` — nullable policy
- [ ] `framework/packages/platform/validation/src/Rule/FilledRule.php` — filled policy
- [ ] `framework/packages/platform/validation/src/Rule/PresentRule.php` — presence assertion
- [ ] `framework/packages/platform/validation/src/Rule/MissingRule.php` — missing assertion
- [ ] `framework/packages/platform/validation/src/Rule/ArrayRule.php` — array type
- [ ] `framework/packages/platform/validation/src/Rule/ListRule.php` — list shape
- [ ] `framework/packages/platform/validation/src/Rule/MapRule.php` — map shape
- [ ] `framework/packages/platform/validation/src/Rule/BoolRule.php` — bool type
- [ ] `framework/packages/platform/validation/src/Rule/IntRule.php` — int type
- [ ] `framework/packages/platform/validation/src/Rule/StringRule.php` — string type
- [ ] `framework/packages/platform/validation/src/Rule/MinRule.php` — min constraint
- [ ] `framework/packages/platform/validation/src/Rule/MaxRule.php` — max constraint
- [ ] `framework/packages/platform/validation/src/Rule/BetweenRule.php` — range constraint
- [ ] `framework/packages/platform/validation/src/Rule/SizeRule.php` — exact size constraint
- [ ] `framework/packages/platform/validation/src/Rule/InRule.php` — inclusion check
- [ ] `framework/packages/platform/validation/src/Rule/NotInRule.php` — exclusion check
- [ ] `framework/packages/platform/validation/src/Rule/SameRule.php` — equality vs another field
- [ ] `framework/packages/platform/validation/src/Rule/DifferentRule.php` — difference vs another field
- [ ] `framework/packages/platform/validation/src/Rule/DistinctRule.php` — unique list items
- [ ] `framework/packages/platform/validation/src/Rule/RequiredIfRule.php` — conditional presence
- [ ] `framework/packages/platform/validation/src/Rule/RequiredUnlessRule.php` — conditional presence
- [ ] `framework/packages/platform/validation/src/Rule/RequiredWithRule.php` — conditional presence
- [ ] `framework/packages/platform/validation/src/Rule/RequiredWithoutRule.php` — conditional presence
- [ ] `framework/packages/platform/validation/src/Rule/RequiredArrayKeysRule.php` — required map keys

#### Modifies

- [ ] `docs/adr/INDEX.md` — register ADR-0047
- [ ] `docs/ssot/config-roots.md` — add `validation` root row

#### Package skeleton (if type=package)

- [ ] `composer.json`
- [ ] `src/Module/ValidationModule.php`
- [ ] `src/Provider/ValidationServiceProvider.php`
- [ ] `config/validation.php`
- [ ] `config/rules.php`
- [ ] `README.md`
- [ ] `tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/validation/config/validation.php`
- [ ] Keys (dot):
  - [ ] `validation.enabled` = true
  - [ ] `validation.fail_fast` = false
  - [ ] `validation.max_violations` = 50
  - [ ] `validation.rules.registry` = []
- [ ] Rules:
  - [ ] `framework/packages/platform/validation/config/rules.php` enforces shape
  - [ ] `validation.rules.registry` MUST be map<string, class-string>
  - [ ] DTO/i18n/file/database keys MUST NOT exist in this epic

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Platform\Validation\Validation\Validator`
  - [ ] registers: `Coretsia\Platform\Validation\Rule\RuleRegistry`
  - [ ] registers: `Coretsia\Platform\Validation\Rule\RuleFactory`
  - [ ] registers: `Coretsia\Platform\Validation\Rule\RuleParser`
  - [ ] registers: `Coretsia\Platform\Validation\Error\ValidationExceptionMapper`
  - [ ] adds tag: `error.mapper` priority `700` meta `{handles:'ValidationException'}`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `validation.validate`
- [ ] Metrics:
  - [ ] `validation.failed_total` (labels: `outcome`)
  - [ ] `validation.violations_total` (labels: `outcome`)
  - [ ] `validation.duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] redaction applied; no raw payload values

#### Errors

- [ ] Exceptions introduced:
  - [ ] N/A (reuses `Coretsia\Contracts\Validation\ValidationException`)
- [ ] Mapping:
  - [ ] new mapper via tag `error.mapper`

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw payload values
  - [ ] auth/cookies/session ids/tokens
- [ ] Allowed:
  - [ ] field path
  - [ ] rule id
  - [ ] code
  - [ ] safe params
  - [ ] `len(value)` / `hash(value)`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] `tests/Contract/ObservabilityPolicyTest.php`
- [ ] `tests/Contract/RedactionDoesNotLeakTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer`
  - [ ] `FakeMetrics`
  - [ ] `FakeLogger`

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/validation/tests/Unit/Validation/ViolationsDeterministicOrderTest.php`
  - [ ] `framework/packages/platform/validation/tests/Unit/Validation/MaxViolationsCapTest.php`
  - [ ] `framework/packages/platform/validation/tests/Unit/Validation/FailFastStopsAtFirstViolationTest.php`
  - [ ] `framework/packages/platform/validation/tests/Unit/Rule/RuleParserTest.php`
  - [ ] `framework/packages/platform/validation/tests/Unit/Rule/RuleFactoryTest.php`
  - [ ] `framework/packages/platform/validation/tests/Unit/Rule/RuleRegistryTest.php`
  - [ ] `framework/packages/platform/validation/tests/Unit/Support/ArrTest.php`
  - [ ] baseline rule tests for every rule created in this epic
- Contract:
  - [ ] `framework/packages/platform/validation/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/validation/tests/Contract/ViolationsAreJsonLikeAndFloatFreeContractTest.php`
  - [ ] `framework/packages/platform/validation/tests/Contract/ViolationsStableOrderContractTest.php`
  - [ ] `framework/packages/platform/validation/tests/Contract/ValidationMapperDoesNotDependOnHttpContractTest.php`
  - [ ] `framework/packages/platform/validation/tests/Contract/ObservabilityPolicyTest.php`
  - [ ] `framework/packages/platform/validation/tests/Contract/RedactionDoesNotLeakTest.php`
- Integration:
  - [ ] `framework/packages/platform/validation/tests/Integration/ValidatorIntegrationTest.php`
  - [ ] `framework/packages/platform/validation/tests/Integration/ValidationExceptionMapperProduces422DescriptorTest.php`
  - [ ] `framework/packages/platform/validation/tests/Integration/ValidationExceptionMappedViaErrorMapperTagTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Docs updated:
  - [ ] README
  - [ ] `docs/guides/validation.md`
  - [ ] ADR-0047
- [ ] No HTTP coupling
- [ ] No DTO reflection/attributes
- [ ] No database/file adapters
- [ ] Deterministic violations ordering + cap enforced
- [ ] Mapping uses `error.mapper` only

---

### 4.10.1 Extended format rules pack (SHOULD) [IMPL]

---
type: package
phase: 4
epic_id: "4.10.1"
owner_path: "framework/packages/platform/validation/"

package_id: "platform/validation"
composer: "coretsia/platform-validation"
kind: runtime
module_id: "platform.validation"

goal: "Додати розширений pack format/type rules до reference validation engine без date/time, file, database або DTO coupling."
provides:
- "Extended scalar/type/format rules"
- "Additional deterministic rule registry entries"
- "Stable validation for common textual identifiers and formats"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 4.10.0 — reference validation engine exists

- Required deliverables (exact paths):
  - `framework/packages/platform/validation/src/Rule/RuleInterface.php` — internal rule contract
  - `framework/packages/platform/validation/src/Rule/RuleRegistry.php` — rule registry exists
  - `framework/packages/platform/validation/src/Rule/RuleFactory.php` — factory exists
  - `framework/packages/platform/validation/config/validation.php` — registry config exists

- Required config roots/keys:
  - `validation` / `validation.rules.registry` — registry extension point

- Required tags:
  - none

- Required contracts / ports:
  - `Coretsia\Contracts\Validation\ValidatorInterface` — consuming engine exists

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `platform/http-app`
- `platform/problem-details`
- `platform/errors`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Validation\Violation`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/validation/src/Rule/TypeRule.php` — generic type comparator
- [ ] `framework/packages/platform/validation/src/Rule/FloatRule.php` — explicit float rule
- [ ] `framework/packages/platform/validation/src/Rule/NumericRule.php` — numeric-like rule
- [ ] `framework/packages/platform/validation/src/Rule/ObjectRule.php` — object type
- [ ] `framework/packages/platform/validation/src/Rule/CallableRule.php` — callable type
- [ ] `framework/packages/platform/validation/src/Rule/IterableRule.php` — iterable type

- [ ] `framework/packages/platform/validation/src/Rule/LessThanRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/LessThanOrEqualRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/GreaterThanRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/GreaterThanOrEqualRule.php`

- [ ] `framework/packages/platform/validation/src/Rule/EmailRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/UrlRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/IpRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/MacAddressRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/UuidRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/UlidRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/JsonRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/SlugRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/RegexRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/AlphaRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/AlphaNumRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/AlphaDashRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/PhoneRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/PostalCodeRule.php`

#### Modifies

- [ ] `framework/packages/platform/validation/config/validation.php` — extend default registry
- [ ] `framework/packages/platform/validation/README.md` — document extended rules
- [ ] `docs/guides/validation.md` — add examples for extended rules

#### Package skeleton (if type=package)

- [ ] `README.md`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/validation/config/validation.php`
- [ ] Keys (dot):
  - [ ] no new root keys
- [ ] Rules:
  - [ ] registry entries for new rule ids MUST be explicit and deterministic

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registry/factory can resolve new rules via config registry

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

- [ ] MUST NOT leak:
  - [ ] raw input values in violation metadata
- [ ] Allowed:
  - [ ] safe params only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] unit tests for every rule created in this epic
- Contract:
  - [ ] `framework/packages/platform/validation/tests/Contract/ViolationsAreJsonLikeAndFloatFreeContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/validation/tests/Integration/ExtendedFormatRulesIntegrationTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] All listed rules implemented
- [ ] Registry explicitly updated
- [ ] No HTTP/date-time/file/database/DTO coupling introduced
- [ ] Tests present for all added rules

---

### 4.10.2 Date/time rules pack (SHOULD) [IMPL]

---
type: package
phase: 4
epic_id: "4.10.2"
owner_path: "framework/packages/platform/validation/"

package_id: "platform/validation"
composer: "coretsia/platform-validation"
kind: runtime
module_id: "platform.validation"

goal: "Додати deterministic date/time validation rules pack до platform/validation без HTTP, file, database або DTO coupling."
provides:
- "Date/time rules pack"
- "Stable parsing/validation for date constraints"
- "Explicit registry entries for temporal rules"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 4.10.0 — reference validation engine exists

- Required deliverables (exact paths):
  - `framework/packages/platform/validation/src/Rule/RuleInterface.php`
  - `framework/packages/platform/validation/src/Rule/RuleRegistry.php`
  - `framework/packages/platform/validation/src/Rule/RuleFactory.php`

- Required config roots/keys:
  - `validation` / `validation.rules.registry`

- Required tags:
  - none

- Required contracts / ports:
  - `Coretsia\Contracts\Validation\Violation`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `platform/http-app`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Validation\Violation`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/validation/src/Rule/DateRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/DateFormatRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/AfterRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/AfterOrEqualRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/BeforeRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/BeforeOrEqualRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/TimezoneRule.php`

#### Modifies

- [ ] `framework/packages/platform/validation/config/validation.php` — extend registry
- [ ] `framework/packages/platform/validation/README.md` — document date/time rules
- [ ] `docs/guides/validation.md` — add temporal examples

#### Package skeleton (if type=package)

- [ ] `README.md`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/validation/config/validation.php`
- [ ] Keys (dot):
  - [ ] no new root keys
- [ ] Rules:
  - [ ] temporal rules MUST be explicitly registered

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

- [ ] MUST NOT leak:
  - [ ] raw compared values
- [ ] Allowed:
  - [ ] field names
  - [ ] rule ids
  - [ ] safe temporal params

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] unit tests for every temporal rule
- Contract:
  - [ ] `framework/packages/platform/validation/tests/Contract/ViolationsAreJsonLikeAndFloatFreeContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/validation/tests/Integration/DateTimeRulesIntegrationTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] All listed temporal rules implemented
- [ ] Registry explicitly updated
- [ ] Tests present for all temporal rules

---

### 4.10.3 Message/i18n layer (SHOULD) [IMPL]

---
type: package
phase: 4
epic_id: "4.10.3"
owner_path: "framework/packages/platform/validation/"

package_id: "platform/validation"
composer: "coretsia/platform-validation"
kind: runtime
module_id: "platform.validation"

goal: "Додати optional message/i18n layer для validation без впливу на deterministic ordering та without changing canonical violation sort keys."
provides:
- "MessageBag for grouped message access"
- "MessageResolver for deterministic message resolution"
- "Default English message catalog"
- "Custom messages / attributes input support at presentation layer"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 4.10.0 — reference engine exists

- Required deliverables (exact paths):
  - `framework/packages/platform/validation/src/Validation/Validator.php`
  - `framework/packages/platform/validation/src/Validation/ViolationSorter.php`
  - `framework/packages/platform/validation/src/Validation/ValidationRule.php`

- Required config roots/keys:
  - `validation` / `validation.enabled`

- Required tags:
  - none

- Required contracts / ports:
  - `Coretsia\Contracts\Validation\ValidationResult`
  - `Coretsia\Contracts\Validation\Violation`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `platform/http-app`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Validation\ValidationResult`
  - `Coretsia\Contracts\Validation\Violation`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/validation/src/Message/MessageBag.php` — grouped message access
- [ ] `framework/packages/platform/validation/src/Message/MessageResolver.php` — deterministic message lookup
- [ ] `framework/packages/platform/validation/resources/lang/en/validation.php` — default messages catalog

#### Modifies

- [ ] `framework/packages/platform/validation/config/validation.php` — optional message-related defaults if needed
- [ ] `framework/packages/platform/validation/README.md` — message layer docs
- [ ] `docs/guides/validation.md` — custom messages/attributes docs

#### Package skeleton (if type=package)

- [ ] `README.md`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/validation/config/validation.php`
- [ ] Keys (dot):
  - [ ] optional presentation keys only if introduced here
- [ ] Rules:
  - [ ] message config MUST NOT affect canonical violation sort order

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Platform\Validation\Message\MessageResolver`

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

- [ ] MUST NOT leak:
  - [ ] raw payload values through formatted messages
- [ ] Allowed:
  - [ ] safe field names
  - [ ] safe params

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/validation/tests/Unit/Message/MessageBagTest.php`
  - [ ] `framework/packages/platform/validation/tests/Unit/Message/MessageResolverTest.php`
- Contract:
  - [ ] `framework/packages/platform/validation/tests/Contract/MessagesDoNotAffectViolationOrderContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/validation/tests/Integration/MessageLayerIntegrationTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Message layer exists and is optional
- [ ] Message text does not affect sorting or cap policy
- [ ] Default English messages provided
- [ ] No DTO/file/database/HTTP coupling introduced

---

### 4.10.4 DTO validation adapter (SHOULD) [IMPL]

---
type: package
phase: 4
epic_id: "4.10.4"
owner_path: "framework/packages/platform/validation/"

package_id: "platform/validation"
composer: "coretsia/platform-validation"
kind: runtime
module_id: "platform.validation"

goal: "Додати DTO validation adapter поверх reference validation engine, де DTO rules декларуються атрибутами на властивостях і адаптуються в canonical array/rules model."
provides:
- "DTO validation adapter"
- "Property-level validation attribute"
- "Nested DTO flattening into canonical dot-notation rules/data"
- "Reuse of base validator pipeline"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 4.10.0 — reference engine exists
  - 1.480.0 — DTO policy/gate exists

- Required deliverables (exact paths):
  - `framework/packages/platform/validation/src/Validation/Validator.php`
  - `framework/packages/platform/validation/src/Validation/ValidationRule.php`
  - `framework/packages/platform/validation/src/Support/Arr.php`
  - `docs/guides/validation.md`

- Required config roots/keys:
  - `validation` / `validation.enabled`

- Required tags:
  - none

- Required contracts / ports:
  - `Coretsia\Contracts\Validation\ValidationResult`
  - `Coretsia\Contracts\Validation\ValidationException`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `platform/http-app`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Validation\ValidationResult`
  - `Coretsia\Contracts\Validation\ValidationException`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/validation/src/Dto/DtoValidatorInterface.php` — DTO adapter API
- [ ] `framework/packages/platform/validation/src/Dto/DtoValidator.php` — reflection-based adapter
- [ ] `framework/packages/platform/validation/src/Dto/DtoValidationResult.php` — optional DTO-oriented wrapper
- [ ] `framework/packages/platform/validation/src/Attribute/ValidationRule.php` — property attribute for rules
- [ ] `docs/guides/dto-validation.md` — DTO validation guide

#### Modifies

- [ ] `framework/packages/platform/validation/config/validation.php` — add DTO adapter defaults if needed
- [ ] `framework/packages/platform/validation/README.md` — document DTO adapter
- [ ] `docs/guides/validation.md` — cross-link DTO guide

#### Package skeleton (if type=package)

- [ ] `README.md`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/validation/config/validation.php`
- [ ] Keys (dot):
  - [ ] `validation.dto.enabled` = true
  - [ ] `validation.dto.nullable_as_missing` = false
  - [ ] `validation.dto.require_all_properties` = false
  - [ ] `validation.dto.validate_nested` = true
  - [ ] `validation.dto.nested_separator` = '.'
- [ ] Rules:
  - [ ] DTO config MUST NOT alter base engine determinism guarantees

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Platform\Validation\Dto\DtoValidator`

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

- [ ] MUST NOT leak:
  - [ ] raw DTO values
  - [ ] reflection internals in diagnostics
- [ ] Allowed:
  - [ ] property paths
  - [ ] rule ids
  - [ ] safe params

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture DTO classes:
  - [ ] `tests/Fixtures/Dto/...`

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/validation/tests/Unit/Dto/DtoValidatorTest.php`
  - [ ] `framework/packages/platform/validation/tests/Unit/Dto/DtoValidationResultTest.php`
  - [ ] `framework/packages/platform/validation/tests/Unit/Attribute/ValidationRuleTest.php`
- Contract:
  - [ ] `framework/packages/platform/validation/tests/Contract/DtoAttributesDoNotAffectBaseEngineDeterminismContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/validation/tests/Integration/DtoValidationIntegrationTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] DTO adapter implemented
- [ ] Attributes live only here, not in 4.10.0
- [ ] Nested DTO validation works deterministically
- [ ] DTO support reuses base validator instead of duplicating logic

---

### 4.10.5 File validation pack (OPTIONAL) [IMPL]

---
type: package
phase: 4
epic_id: "4.10.5"
owner_path: "framework/packages/platform/validation/"

package_id: "platform/validation"
composer: "coretsia/platform-validation"
kind: runtime
module_id: "platform.validation"

goal: "Додати optional file/image validation rules pack для canonical file-like input model, без HTTP upload coupling."
provides:
- "File-related validation rules"
- "Image dimension/mime validation rules"
- "Deterministic file metadata validation"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 4.10.0 — reference engine exists

- Required deliverables (exact paths):
  - `framework/packages/platform/validation/src/Rule/RuleInterface.php`
  - `framework/packages/platform/validation/src/Rule/RuleRegistry.php`

- Required config roots/keys:
  - `validation` / `validation.rules.registry`

- Required tags:
  - none

- Required contracts / ports:
  - `Coretsia\Contracts\Validation\Violation`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `platform/http-app`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Validation\Violation`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/validation/src/Rule/FileRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/ImageRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/MimesRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/MimetypesRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/DimensionsRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/MaxSizeRule.php`
- [ ] `framework/packages/platform/validation/src/Rule/MinSizeRule.php`

#### Modifies

- [ ] `framework/packages/platform/validation/config/validation.php` — extend registry
- [ ] `framework/packages/platform/validation/README.md` — file rules docs
- [ ] `docs/guides/validation.md` — add file validation examples

#### Package skeleton (if type=package)

- [ ] `README.md`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/validation/config/validation.php`
- [ ] Keys (dot):
  - [ ] no mandatory new root keys
- [ ] Rules:
  - [ ] canonical file-like input shape MUST be documented

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

- [ ] MUST NOT leak:
  - [ ] file paths
  - [ ] raw file contents
- [ ] Allowed:
  - [ ] mime/type
  - [ ] size
  - [ ] dimensions
  - [ ] hashes/lengths

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture file-like inputs:
  - [ ] `tests/Fixtures/Files/...`

### Tests (MUST)

- Unit:
  - [ ] unit tests for every file rule
- Contract:
  - [ ] `framework/packages/platform/validation/tests/Contract/FileRulesDoNotLeakPathsOrContentsContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/validation/tests/Integration/FileRulesIntegrationTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] File rules implemented
- [ ] No HTTP upload dependency introduced
- [ ] No raw paths/contents leaked

---

### 4.10.6 Database validation pack (OPTIONAL) [IMPL]

---
type: package
phase: 4
epic_id: "4.10.6"
owner_path: "framework/packages/platform/validation/"

package_id: "platform/validation"
composer: "coretsia/platform-validation"
kind: runtime
module_id: "platform.validation"

goal: "Додати optional database-backed validation rules pack через contracts-only database boundary, without coupling reference engine to concrete database adapters."
provides:
- "Database-backed uniqueness validation"
- "Reference rule-to-database adapter wiring"
- "Deterministic database validation diagnostics"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 4.10.0 — reference engine exists
  - 1.150.0 — database contracts exist

- Required deliverables (exact paths):
  - `framework/packages/platform/validation/src/Rule/RuleInterface.php`
  - `framework/packages/core/contracts/src/Database/ConnectionInterface.php` — database boundary
  - `framework/packages/core/contracts/src/Database/QueryResultInterface.php` — query result boundary

- Required config roots/keys:
  - `validation` / `validation.rules.registry`

- Required tags:
  - none

- Required contracts / ports:
  - `Coretsia\Contracts\Database\ConnectionInterface`
  - `Coretsia\Contracts\Database\QueryResultInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `integrations/*`
- concrete database drivers

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Database\ConnectionInterface`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/validation/src/Rule/Database/UniqueRule.php` — DB uniqueness rule

#### Modifies

- [ ] `framework/packages/platform/validation/config/validation.php` — extend registry
- [ ] `framework/packages/platform/validation/README.md` — document DB rule boundary
- [ ] `docs/guides/validation.md` — add DB validation examples

#### Package skeleton (if type=package)

- [ ] `README.md`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/validation/config/validation.php`
- [ ] Keys (dot):
  - [ ] optional DB rule config only if needed
- [ ] Rules:
  - [ ] MUST remain contracts-only at compile time

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

- [ ] MUST NOT leak:
  - [ ] SQL
  - [ ] credentials
  - [ ] raw DB values
- [ ] Allowed:
  - [ ] table name if policy allows
  - [ ] safe ids
  - [ ] counts

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] fake connection / fake query result

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/validation/tests/Unit/Rules/Database/UniqueRuleTest.php`
- Contract:
  - [ ] `framework/packages/platform/validation/tests/Contract/DatabaseRulesDoNotDependOnConcreteDriversContractTest.php`
  - [ ] `framework/packages/platform/validation/tests/Contract/DatabaseRulesDoNotLeakSqlContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/validation/tests/Integration/DatabaseUniqueRuleIntegrationTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] DB rule implemented through contracts-only DB boundary
- [ ] No concrete driver compile-time deps
- [ ] No SQL/credentials/raw values leakage

---

### 4.10.7 Convenience facade/helpers (OPTIONAL) [IMPL]

---
type: package
phase: 4
epic_id: "4.10.7"
owner_path: "framework/packages/platform/validation/"

package_id: "platform/validation"
composer: "coretsia/platform-validation"
kind: runtime
module_id: "platform.validation"

goal: "Додати optional convenience facade/helpers поверх existing validation services, без зміни canonical engine contracts або deterministic policy."
provides:
- "Convenience facade API"
- "Helper function for quick validator access"
- "Sugar layer for application ergonomics"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 4.10.0 — reference validation engine exists

- Required deliverables (exact paths):
  - `framework/packages/platform/validation/src/Validation/Validator.php`
  - `framework/packages/platform/validation/src/Provider/ValidationServiceProvider.php`

- Required config roots/keys:
  - `validation` / `validation.enabled`

- Required tags:
  - none

- Required contracts / ports:
  - `Coretsia\Contracts\Validation\ValidatorInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Validation\ValidatorInterface`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/validation/src/Validation.php` — facade-like convenience class
- [ ] `framework/packages/platform/validation/src/helpers.php` — global helper `validator()`

#### Modifies

- [ ] `framework/packages/platform/validation/composer.json` — autoload helper if accepted
- [ ] `framework/packages/platform/validation/README.md` — helper/facade docs
- [ ] `docs/guides/validation.md` — quick-start sugar examples

#### Package skeleton (if type=package)

- [ ] `README.md`

#### Configuration (keys + defaults)

- Files:
  - N/A
- Keys (dot):
  - no new keys
- [ ] Rules:
  - [ ] helper/facade MUST delegate to canonical validator service

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

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

N/A

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/validation/tests/Unit/ValidationFacadeTest.php`
  - [ ] `framework/packages/platform/validation/tests/Unit/Helpers/ValidatorHelperTest.php`
- Contract:
  - [ ] `framework/packages/platform/validation/tests/Contract/FacadeAndHelperDelegateToCanonicalValidatorContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/validation/tests/Integration/FacadeAndHelperIntegrationTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Facade/helper implemented
- [ ] Canonical contracts and determinism policy unchanged
- [ ] Sugar layer is optional and purely delegating

---

### 4.20.0 coretsia/http-client (outgoing HTTP) (SHOULD) [IMPL]

---
type: package
phase: 4
epic_id: "4.20.0"
owner_path: "framework/packages/platform/http-client/"

package_id: "platform/http-client"
composer: "coretsia/platform-http-client"
kind: runtime
module_id: "platform.http-client"

goal: "Надати canonical outbound PSR-18 клієнт із deterministic middleware order та жорсткою redaction-політикою (Authorization/Cookie/URL/query), з noop-safe observability і без залежності від inbound HTTP пакетів."
provides:
- 'DI binding для `Psr\Http\Client\ClientInterface` (PSR-18)'
- "Deterministic middleware stack: timeout/retry/headers/tracecontext/redacted logging"
- "Deterministic retry/backoff (ints only, stable list, no randomness)"
- "Tracecontext injection (noop-safe)"
- "Security: no payload/PII/Authorization/Cookie logging; no raw URL/query"

tags_introduced: []
config_roots_introduced: ["http_client"]
artifacts_introduced: []

adr: docs/adr/ADR-0048-http-client-outgoing.md
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `core/contracts` — tracing/metrics/context propagation ports
  - `core/foundation` — DI/config baseline
  - (policy) Phase 0 determinism: retry/backoff ints only; no randomness
  - (policy) Phase 1 redaction: never log Authorization/Cookie/Set-Cookie; never log raw URL/query (hash/len only)

- Required deliverables (exact paths):
  - `docs/adr/ADR-0048-http-client-outgoing.md` — ADR (locked before impl)

- Required config roots/keys:
  - `http_client` / `http_client.*` — this epic introduces the root & keys

- Required contracts / ports:
  - `Psr\Http\Client\ClientInterface`
  - `Psr\Http\Message\RequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\UriInterface`
  - `Psr\Http\Message\RequestFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`
  - `Psr\Log\LoggerInterface`
  - (optional) `Coretsia\Contracts\Context\ContextAccessorInterface` (correlation/tracecontext input)
  - `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

- Runtime expectation (policy, NOT deps):
  - `platform/logging|tracing|metrics` provide implementations/noop-safe

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http` *(inbound)*
- `platform/http-app`
- `platform/errors`
- `platform/problem-details`
- `platform/logging`
- `platform/tracing`
- `platform/metrics`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Client\ClientInterface`
  - `Psr\Http\Message\RequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\UriInterface`
  - `Psr\Http\Message\RequestFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` (if used)
  - `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Transport ownership & discovery (MUST)

- `platform/http-client` MUST NOT hard-bind to a concrete vendor transport via hidden probing/discovery.
- One of the following single-choice models MUST be declared in ADR and enforced:

  **Model A (preferred, matches filesystem/database pattern):**
  - Introduce a discovery tag owned by this package:
    - tag: `http_client.driver`
  - Integrations packages provide concrete PSR-18 transports and register them via `http_client.driver` (e.g. curl/guzzle adapters).
  - `platform/http-client` selects driver deterministically by config `http_client.driver` and decorates it with middleware.
  - Tag introduction (MUST, if Model A)
    tags_introduced:
    - "http_client.driver"
    - Owner: `platform/http-client`
    - Owner constant MUST exist:
      - `framework/packages/platform/http-client/src/Provider/Tags.php` with `HTTP_CLIENT_DRIVER = 'http_client.driver'`

  **Model B (minimal core):**
  - This package does NOT provide a transport.
  - It only decorates an already-bound `Psr\Http\Client\ClientInterface` from the container.
  - If Model B is used, this epic MUST NOT claim “provides DI binding for ClientInterface”; it provides decorator/factory only.

### Observability locks (MUST)

- Metric labels MUST use allowlist only: `method,status,driver,operation,table,outcome`.
  - MUST NOT label by `host`.
  - If host is needed for debugging: only `host_hash=sha256(host)` as span attribute (NOT metric label).

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/http-client/src/Module/HttpClientModule.php` — runtime module
- [ ] `framework/packages/platform/http-client/src/Provider/HttpClientServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/http-client/src/Provider/HttpClientServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/http-client/config/http_client.php` — config subtree (no repeated root)
- [ ] `framework/packages/platform/http-client/config/rules.php` — config shape rules
- [ ] `framework/packages/platform/http-client/README.md` — docs (Observability / Errors / Security-Redaction)
- [ ] `docs/guides/http-client.md` — usage examples + retry semantics

- [ ] `framework/packages/platform/http-client/src/Client/HttpClientFactory.php` — builds PSR-18 client + middleware stack
- [ ] `framework/packages/platform/http-client/src/Policy/HttpClientPolicy.php` — VO (timeouts, retry policy, redaction headers)
- [ ] `framework/packages/platform/http-client/src/Middleware/TimeoutMiddleware.php`
- [ ] `framework/packages/platform/http-client/src/Middleware/RetryMiddleware.php`
- [ ] `framework/packages/platform/http-client/src/Middleware/DefaultHeadersMiddleware.php`
- [ ] `framework/packages/platform/http-client/src/Middleware/TraceContextInjectMiddleware.php`
- [ ] `framework/packages/platform/http-client/src/Middleware/RedactingLoggerMiddleware.php`
- [ ] `framework/packages/platform/http-client/src/Retry/BackoffPolicy.php` — deterministic (ints only)
- [ ] `framework/packages/platform/http-client/src/Security/Redaction.php` — `hash/len`, header allow/deny lists
- [ ] `framework/packages/platform/http-client/src/Exception/HttpClientException.php` — errorCode `CORETSIA_HTTP_CLIENT_FAILED`

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0048-http-client-outgoing.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/http-client/composer.json`
- [ ] `framework/packages/platform/http-client/src/Module/HttpClientModule.php`
- [ ] `framework/packages/platform/http-client/src/Provider/HttpClientServiceProvider.php`
- [ ] `framework/packages/platform/http-client/config/http_client.php`
- [ ] `framework/packages/platform/http-client/config/rules.php`
- [ ] `framework/packages/platform/http-client/README.md`
- [ ] `framework/packages/platform/http-client/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/http-client/config/http_client.php`
- [ ] Keys (dot):
  - [ ] `http_client.enabled` = true
  - [ ] `http_client.timeout_seconds` = 10
  - [ ] `http_client.connect_timeout_seconds` = 3
  - [ ] `http_client.retry.enabled` = true
  - [ ] `http_client.retry.max_attempts` = 3
  - [ ] `http_client.retry.allowed_methods` = ['GET','HEAD','PUT','DELETE']
  - [ ] `http_client.retry.backoff_ms` = [0, 200, 500]
  - [ ] `http_client.headers.default` = []
  - [ ] `http_client.redaction.headers` = ['Authorization','Cookie','Set-Cookie']
- [ ] Rules:
  - [ ] `framework/packages/platform/http-client/config/rules.php` enforces shape + int-only backoff

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] binds `Psr\Http\Client\ClientInterface` → built client instance (factory)
  - [ ] binds PSR-17 factories if required by chosen PSR-18 implementation

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (for safe log context only)
- [ ] Context writes (safe only):
  - [ ] none
- Reset discipline:
  - N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `http.client` (attrs: `method`, `host`, `status`, `outcome`)
- [ ] Metrics:
  - [ ] `http.client.request_total` (labels: `method`, `status`, `outcome`)
  - [ ] `http.client.duration_ms` (labels: `method`, `status`, `outcome`)
  - [ ] `http.client.retry_total` (labels: `method`, `outcome=scheduled`)
- [ ] Logs:
  - [ ] never log raw URL/query/payload; only `host`, `method`, `status`, optional `hash(url)` if needed

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/http-client/src/Exception/HttpClientException.php` — errorCode `CORETSIA_HTTP_CLIENT_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (DefaultExceptionMapper) *(no dupes)*

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/Set-Cookie headers, tokens
  - [ ] raw payload / PII
  - [ ] raw URL/query
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe host+method

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → proof:
  - [ ] `framework/packages/platform/http-client/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → proof:
  - [ ] `framework/packages/platform/http-client/tests/Contract/RedactionNeverPrintsSensitiveHeadersContractTest.php`
- [ ] If determinism exists → proof:
  - [ ] `framework/packages/platform/http-client/tests/Unit/RetryBackoffDeterministicTest.php`
  - [ ] `framework/packages/platform/http-client/tests/Contract/BackoffIsIntOnlyDeterministicContractTest.php`
  - [ ] `framework/packages/platform/http-client/tests/Contract/MiddlewareOrderDeterministicContractTest.php`

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/http-client/tests/Unit/RetryBackoffDeterministicTest.php`
  - [ ] `framework/packages/platform/http-client/tests/Unit/RedactionHeaderListAppliedTest.php`
- Contract:
  - [ ] `framework/packages/platform/http-client/tests/Contract/MiddlewareOrderDeterministicContractTest.php`
  - [ ] `framework/packages/platform/http-client/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/http-client/tests/Contract/BackoffIsIntOnlyDeterministicContractTest.php`
  - [ ] `framework/packages/platform/http-client/tests/Contract/RedactionNeverPrintsSensitiveHeadersContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/http-client/tests/Integration/RetryOnlyOnAllowedMethodsTest.php`
  - [ ] `framework/packages/platform/http-client/tests/Integration/TraceContextIsInjectedNoopSafeTest.php`
  - [ ] `framework/packages/platform/http-client/tests/Integration/RedactionDoesNotLogAuthorizationTest.php`
- Gates/Arch:
  - [ ] deptrac expectations satisfied (no forbidden deps)

### DoD (MUST)

- [ ] Deterministic middleware order + retry policy (ints only; no randomness)
- [ ] No secret leakage (Authorization/Cookie/URL/query/payload) (tests)
- [ ] Works with noop tracer/meter/logger
- [ ] Docs updated (`README.md`, `docs/guides/http-client.md`)

---

### 4.30.0 Platform filesystem + Local driver (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.30.0"
owner_path: "framework/packages/platform/filesystem/"

package_id: "platform/filesystem"
composer: "coretsia/platform-filesystem"
kind: runtime
module_id: "platform.filesystem"

goal: "Надати канонічний filesystem layer (DiskManager + path safety + deterministic list) та reference local driver в integrations/*, без витоку raw paths і без platform→integrations залежності."
provides:
- "DiskManager + DiskInterface integration point"
- "SSoT path safety: forbid traversal/absolute paths; symlinks forbidden by default"
- "Deterministic list() order (lexicographic)"
- "Observability (spans/metrics/logs) без raw paths (hash/len only)"
- "Reference driver: integrations/filesystem-local (local disk)"

tags_introduced: ["filesystem.disk_driver"]
config_roots_introduced: ["filesystem", "filesystem_local"]
artifacts_introduced: []

adr: docs/adr/ADR-0049-platform-filesystem-local-driver.md
ssot_refs:
- docs/ssot/filesystem-path-safety.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `core/contracts` — `DiskInterface` + observability ports
  - `core/foundation` — DI/config baseline
  - (policy) Phase 0 fingerprint/path rules + Phase 1 redaction — forbid traversal; symlink policy; deterministic list; no raw paths
  - (policy) runtime MUST NOT depend on `devtools/internal-toolkit` (tooling-only); invariants are proven via runtime tests (golden vectors)

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/src/Filesystem/DiskInterface.php` — public port
  - `docs/adr/ADR-0049-platform-filesystem-local-driver.md` — ADR (locked before impl)

- Required config roots/keys:
  - `filesystem` / `filesystem.*` — this epic introduces the root & keys
  - `filesystem_local` / `filesystem_local.*` — reference integration config root & keys

- Required tags:
  - `filesystem.disk_driver` — introduced by this epic as canonical driver discovery mechanism
  - `kernel.reset` — only if stateful services appear (default expectation: none)

- Required contracts / ports:
  - `Psr\Log\LoggerInterface`
  - (optional) `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Filesystem\DiskInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - (optional) `Coretsia\Foundation\Discovery\DeterministicOrder`

- Runtime expectation (policy, NOT deps):
  - `platform/logging|tracing|metrics` provide implementations/noop-safe
  - `integrations.filesystem-local` may be enabled to provide a `local` disk driver

- External test prerequisite:
  - `framework/packages/core/contracts/tests/Contract/FilesystemDiskInterfaceShapeContractTest.php` exists in contracts scope (referenced as parity proof)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http`
- `integrations/*` *(for `platform/filesystem` package)*

> NOTE: `framework/packages/integrations/filesystem-local/` MAY depend on `platform/filesystem`,
> but `platform/filesystem` MUST NOT depend on integrations.

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Filesystem\DiskInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Registry locks (MUST)

- Because this epic introduces:
  - tag `filesystem.disk_driver`
  - config roots `filesystem` and `filesystem_local`
    it MUST update SSoT registries:

1) `docs/ssot/tags.md`:
- add row: `filesystem.disk_driver` owner `platform/filesystem`
- only owner package defines public constant for this tag.

2) `docs/ssot/config-roots.md`:
- add root `filesystem` owner `platform/filesystem`
- add root `filesystem_local` owner `integrations/filesystem-local`

- Integrations packages MUST NOT be a compile-time dependency of `platform/filesystem` (already stated); driver ownership lives in `integrations/*`.

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - N/A
- Kernel hooks/tags:
  - `filesystem.disk_driver` priority `0` meta `{name:'local', driver:'local'}` → `framework/packages/integrations/filesystem-local/src/Driver/LocalFilesystemDriver.php`
  - `kernel.reset` *(only if stateful services added; default: none)*
- Artifacts:
  - N/A

### Runtime path normalization contract (MUST)

Runtime MUST re-encode Phase 0 path invariants without importing tooling:

- Normalize separators: treat `\` as `/` for user input, output uses `/` only.
- Forbid absolute paths:
  - leading `/` or `\`
  - Windows drive prefixes like `C:\` / `C:/`
  - UNC prefixes like `\\server\share`
- Forbid traversal segments `..` after normalization.
- Forbid empty / NUL bytes.
- Deterministic list order:
  - `list()` returns normalized relative paths sorted by `strcmp` (byte-order), locale-independent.
- Redaction:
  - logs/metrics/spans MUST NOT include raw paths; only `hash(path)` and `len(path)` are allowed.

### Deliverables (MUST)

#### Creates

**platform/filesystem**

- [ ] `framework/packages/platform/filesystem/src/Module/FilesystemModule.php` — runtime module
- [ ] `framework/packages/platform/filesystem/src/Provider/FilesystemServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/filesystem/src/Provider/FilesystemServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/filesystem/src/Provider/Tags.php` — constants (`DISK_DRIVER = 'filesystem.disk_driver'`)
- [ ] `framework/packages/platform/filesystem/config/filesystem.php` — config subtree (no repeated root)
- [ ] `framework/packages/platform/filesystem/config/rules.php` — config shape rules + path-safety policy
- [ ] `framework/packages/platform/filesystem/README.md` — docs (Observability / Errors / Security-Redaction + path safety)
- [ ] `docs/ssot/filesystem-path-safety.md` — SSoT traversal/symlink/normalize rules
- [ ] `docs/guides/filesystem.md` — configure disks; local driver example

Core:
- [ ] `framework/packages/platform/filesystem/src/Disk/DiskManager.php` — resolve disk by name (config + DI)

Path safety:
- [ ] `framework/packages/platform/filesystem/src/Path/PathPolicy.php` — safety rails (ints/bools only)
- [ ] `framework/packages/platform/filesystem/src/Path/SafePathJoiner.php` — normalize + forbid traversal + optional symlink check

Exceptions:
- [ ] `framework/packages/platform/filesystem/src/Exception/FilesystemException.php`
- [ ] `framework/packages/platform/filesystem/src/Exception/PathTraversalForbiddenException.php` — `CORETSIA_FS_PATH_TRAVERSAL_FORBIDDEN`
- [ ] `framework/packages/platform/filesystem/src/Exception/SymlinkForbiddenException.php` — `CORETSIA_FS_SYMLINK_FORBIDDEN`
- [ ] `framework/packages/platform/filesystem/src/Exception/IoException.php` — `CORETSIA_FS_IO_ERROR`

Observability + redaction:
- [ ] `framework/packages/platform/filesystem/src/Observability/FilesystemInstrumentation.php` — spans/metrics/logs wrapper
- [ ] `framework/packages/platform/filesystem/src/Security/Redaction.php` — `hashPath()/len()`

**integrations/filesystem-local**

- [ ] `framework/packages/integrations/filesystem-local/src/Module/FilesystemLocalModule.php` — runtime module
- [ ] `framework/packages/integrations/filesystem-local/src/Provider/FilesystemLocalServiceProvider.php` — DI wiring
- [ ] `framework/packages/integrations/filesystem-local/src/Provider/FilesystemLocalServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/filesystem-local/config/filesystem_local.php` — config subtree (no repeated root)
- [ ] `framework/packages/integrations/filesystem-local/config/rules.php` — config shape rules
- [ ] `framework/packages/integrations/filesystem-local/README.md` — docs (config + limitations + redaction)

Driver:
- [ ] `framework/packages/integrations/filesystem-local/src/Driver/LocalFilesystemDriver.php` — implements `DiskInterface`, atomic write where possible
- [ ] `framework/packages/integrations/filesystem-local/src/Driver/LocalFilesystemPolicy.php` — root dir, chmod policy (no secrets)

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/filesystem-path-safety.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0049-platform-filesystem-local-driver.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/filesystem/composer.json`
- [ ] `framework/packages/platform/filesystem/src/Module/FilesystemModule.php`
- [ ] `framework/packages/platform/filesystem/src/Provider/FilesystemServiceProvider.php`
- [ ] `framework/packages/platform/filesystem/config/filesystem.php`
- [ ] `framework/packages/platform/filesystem/config/rules.php`
- [ ] `framework/packages/platform/filesystem/README.md`
- [ ] `framework/packages/platform/filesystem/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

*(integration package skeleton is also created by this epic; see “Creates”)*

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/filesystem/config/filesystem.php`
  - [ ] `framework/packages/integrations/filesystem-local/config/filesystem_local.php`
- [ ] Keys (dot):
  - [ ] `filesystem.enabled` = true
  - [ ] `filesystem.default` = 'local'
  - [ ] `filesystem.disks` = []                       # name => {driver, ...}
  - [ ] `filesystem.path_policy.forbid_symlinks` = true
  - [ ] `filesystem.path_policy.forbid_absolute_paths` = true
  - [ ] `filesystem.path_policy.max_path_length` = 4096
  - [ ] `filesystem.redaction.enabled` = true
  - [ ] `filesystem_local.enabled` = true
  - [ ] `filesystem_local.root` = 'skeleton/var/tmp'  # reference safe default
- [ ] Rules:
  - [ ] `framework/packages/platform/filesystem/config/rules.php` enforces shape + path policy invariants
  - [ ] `framework/packages/integrations/filesystem-local/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/filesystem/src/Provider/Tags.php`
  - [ ] constant(s):
    - [ ] `DISK_DRIVER = 'filesystem.disk_driver'`
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Filesystem\Disk\DiskManager`
  - [ ] registers: `Coretsia\Filesystem\Path\SafePathJoiner`
  - [ ] registers: `Coretsia\Filesystem\Observability\FilesystemInstrumentation`
  - [ ] adds tag: `filesystem.disk_driver` priority `0` meta `{name:'local', driver:'local'}`
    - service id: `Coretsia\FilesystemLocal\Driver\LocalFilesystemDriver`

#### Artifacts / outputs (if applicable)

N/A *(runtime I/O only; `skeleton/var/**` is not an artifact)*

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (for log correlation only)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] no stateful services expected; if added → `ResetInterface` + tag `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `fs.op` (attrs: `driver`, `operation`, `outcome`, `bytes?` — no path)
- [ ] Metrics:
  - [ ] `fs.op_total` (labels: `driver`, `operation`, `outcome`)
  - [ ] `fs.op_duration_ms` (labels: `driver`, `operation`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `op→operation`, `via→driver`
- [ ] Logs:
  - [ ] errors/warnings include `hash(path)` / `len(path)` only; never raw paths

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/filesystem/src/Exception/PathTraversalForbiddenException.php` — `CORETSIA_FS_PATH_TRAVERSAL_FORBIDDEN`
  - [ ] `framework/packages/platform/filesystem/src/Exception/SymlinkForbiddenException.php` — `CORETSIA_FS_SYMLINK_FORBIDDEN`
  - [ ] `framework/packages/platform/filesystem/src/Exception/IoException.php` — `CORETSIA_FS_IO_ERROR`
- [ ] Mapping:
  - [ ] reuse existing mapper (DefaultExceptionMapper) *(no dupes)*

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw paths, file contents, tokens
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` and safe disk names

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → proof:
  - [ ] `framework/packages/platform/filesystem/tests/Contract/RedactionDoesNotLeakPathsContractTest.php`
- [ ] If redaction exists → proof:
  - [ ] `framework/packages/platform/filesystem/tests/Contract/RedactionDoesNotLeakPathsContractTest.php`
- [ ] If determinism exists → proof:
  - [ ] `framework/packages/platform/filesystem/tests/Contract/ListOrderDeterministicContractTest.php`
  - [ ] `framework/packages/platform/filesystem/tests/Integration/ListReturnsDeterministicOrderTest.php`
- [ ] If path-safety exists → proof:
  - [ ] `framework/packages/platform/filesystem/tests/Contract/PathTraversalForbiddenGoldenVectorsContractTest.php`
  - [ ] `framework/packages/platform/filesystem/tests/Contract/SymlinkForbiddenByDefaultContractTest.php`

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/filesystem/tests/Unit/SafePathJoinerNormalizesPosixAndForbidsTraversalTest.php`
  - [ ] `framework/packages/platform/filesystem/tests/Unit/PathPolicyDefaultsAreSafeTest.php`
- Contract:
  - [ ] `framework/packages/platform/filesystem/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/filesystem/tests/Contract/RedactionDoesNotLeakPathsContractTest.php`
  - [ ] `framework/packages/platform/filesystem/tests/Contract/PathTraversalForbiddenGoldenVectorsContractTest.php`
  - [ ] `framework/packages/platform/filesystem/tests/Contract/SymlinkForbiddenByDefaultContractTest.php`
  - [ ] `framework/packages/platform/filesystem/tests/Contract/ListOrderDeterministicContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/filesystem/tests/Integration/ListReturnsDeterministicOrderTest.php`
  - [ ] `framework/packages/integrations/filesystem-local/tests/Integration/LocalDriverUsesSafePathJoinerTest.php`
  - [ ] `framework/packages/integrations/filesystem-local/tests/Integration/SymlinkForbiddenTest.php`
- Gates/Arch:
  - [ ] deptrac: `platform/filesystem` MUST NOT depend on `integrations/*`

### DoD (MUST)

- [ ] Dependency rules satisfied (no platform→integrations)
- [ ] Path safety enforced (traversal + symlink policy; golden vectors)
- [ ] Observability policy satisfied (no raw paths; labels allowlist)
- [ ] Tests pass (unit+contract+integration)
- [ ] Docs complete (READMEs + `docs/ssot/filesystem-path-safety.md` + `docs/guides/filesystem.md`)
- [ ] path safety lock (MUST)
  - [ ] Filesystem path safety MUST align with Phase 0 fingerprint rules + Phase 1 redaction:
    - [ ] forbid traversal (`..`)
    - [ ] forbid symlinks by default
    - [ ] deterministic list order
    - [ ] never print raw paths (hash/len only)
  - [ ] IMPORTANT: runtime MUST NOT depend on `devtools/internal-toolkit` (tooling-only).
    - [ ] Instead: re-encode the same invariants with runtime tests (golden vectors), so Phase 0 value is not lost.

---

### 4.40.0 Filesystem drivers (S3/FTP/SFTP) (SHOULD) [DOC]

---
type: docs
phase: 4
epic_id: "4.40.0"
owner_path: "docs/architecture/filesystem-drivers.md"

goal: "Зафіксувати ownership та parity-інваріанти для filesystem drivers (S3/FTP/SFTP): drivers тільки в integrations/*, platform/filesystem не залежить від них, і драйвери не витікають secrets/paths."
provides:
- "Ownership rule: drivers only in `integrations/*`"
- "Parity semantics invariants (exists/get/put/delete/list)"
- "Security/redaction rules for drivers (secret_ref; no raw paths)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- docs/ssot/filesystem-driver-parity.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `platform/filesystem` exists as canonical filesystem layer (DiskManager + SafePathJoiner)
  - `core/contracts` provides `DiskInterface`

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/src/Filesystem/DiskInterface.php` — driver API surface
  - `framework/packages/platform/filesystem/README.md` — MUST reference the ownership rule doc after this epic lands

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- none

Forbidden:
- none

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Filesystem\DiskInterface`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `docs/architecture/filesystem-drivers.md` — drivers table + invariants + tests outline
- [ ] `docs/ssot/filesystem-driver-parity.md` — contract-like semantics (exists/get/put/delete/list)
- [ ] `framework/tools/tests/Fixtures/FilesystemS3App/` — framework fixtures plan (Phase 6+, optional)

#### Modifies

- [ ] `framework/packages/platform/filesystem/README.md` — reference ownership rule + ssot refs
- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/filesystem-driver-parity.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A *(DOC epic; proof is the existence of the docs + referenced links from platform/filesystem README)*

### Tests (MUST)

N/A

### DoD (MUST)

- [ ] Ownership rule written and referenced by `platform/filesystem` README
- [ ] Parity test outline exists (what to test, where to put fixtures)
- [ ] Security/redaction rules for drivers documented (secret_ref; no raw paths)
- [ ] ensure `platform/filesystem/README.md` references the ownership + parity docs as normative

---

### 4.50.0 Uploads: multipart parsing + validation + quarantine (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.50.0"
owner_path: "framework/packages/platform/uploads/"

package_id: "platform/uploads"
composer: "coretsia/platform-uploads"
kind: runtime
module_id: "platform.uploads"

goal: "Реалізувати multipart uploads через PSR-15 middleware без superglobals, з deterministic quarantine flow (safe ids/metadata/paths) та канонічними помилками 400/413/415 через error.mapper → ProblemDetails."
provides:
- "PSR-15 MultipartFormDataMiddleware (no $_FILES)"
- "UploadPolicy + UploadedFileValidator (mime/ext/size) без витоку filename/bytes"
- "Quarantine storage on filesystem (SafePathJoiner; deterministic filename strategy)"
- "Canonical error mapping via error.mapper (400/413/415) for RFC7807 rendering"

tags_introduced: []
config_roots_introduced: ["uploads"]
artifacts_introduced: []

adr: docs/adr/ADR-0050-multipart-parsing-validation-quarantine.md
ssot_refs:
- docs/ssot/http-middleware-catalog.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `core/contracts` — filesystem + observability + error mapper ports
  - `core/foundation` — DI/config baseline
  - `platform/filesystem` — SafePathJoiner + DiskInterface resolution policy
  - (policy) canonical error flow exists: ExceptionMapper → ErrorDescriptor → ProblemDetails (no direct rendering here)

- Required deliverables (exact paths):
  - `docs/adr/ADR-0050-multipart-parsing-validation-quarantine.md` — ADR (locked before impl)
  - `docs/ssot/http-middleware-catalog.md` — catalog must include middleware row after wiring

- Required config roots/keys:
  - `uploads` / `uploads.*` — this epic introduces root & keys

- Required tags:
  - `http.middleware.app_pre` — slot tag exists and is executed by inbound HTTP runtime (this epic contributes middleware)
  - `error.mapper` — tag exists and is collected by runtime error subsystem (this epic contributes mapper)

- Required contracts / ports:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
  - (optional) `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Filesystem\DiskInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - (optional) `Coretsia\Foundation\Discovery\DeterministicOrder`

- Runtime expectation (policy, NOT deps):
  - `platform/http` executes middleware slot `http.middleware.app_pre`
  - `platform/errors` executes `error.mapper` contributions
  - `platform/problem-details` renders RFC7807
  - `platform/logging|tracing|metrics` provide noop-safe implementations

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `platform/filesystem`

Forbidden:
- `platform/http` *(package dep forbidden; may use only PSR-7/15 types)*
- `platform/http-app`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Filesystem\DiskInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - middleware slot/tag: `http.middleware.app_pre` priority `80` meta `{toggle:'uploads.middleware.enabled'}` →
    `framework/packages/platform/uploads/src/Http/Middleware/MultipartFormDataMiddleware.php`
- Kernel hooks/tags:
  - `error.mapper` priority `650` meta `{handles:'uploads exceptions'}` →
    `framework/packages/platform/uploads/src/Http/UploadsProblemMapper.php`
- Artifacts:
  - N/A *(quarantine writes are runtime files; fingerprint ignores `skeleton/var/**`)*

### Middleware slot & tag ownership corrections (MUST)

- **HTTP middleware taxonomy (PHASE 1.190.0) is cemented.**
  - Replace any usage of `http.middleware.app_before_routing` with:
    - `http.middleware.app_pre`
  - This middleware MUST be placed only in the `app_pre` slot (not system/route), priority remains `80`.

- **Reserved tag ownership:**
  - `http.middleware.*` tags are owned by `platform/http`.
  - `error.mapper` tag is owned by `platform/errors`.
  - `platform/uploads` MUST NOT claim ownership and MUST NOT duplicate owner constants.
  - Therefore: remove `framework/packages/platform/uploads/src/Provider/Tags.php` **if it only contains** constants for `http.middleware.*` and/or `error.mapper`.
  - Wiring uses string literals in ServiceProvider:
    - `'http.middleware.app_pre'`
    - `'error.mapper'`

- **Error flow boundary:**
  - This package maps exceptions to `ErrorDescriptor` only.
  - It MUST NOT render RFC7807 itself; rendering is performed by `platform/problem-details` adapter.

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/uploads/src/Module/UploadsModule.php` — runtime module
- [ ] `framework/packages/platform/uploads/src/Provider/UploadsServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/uploads/src/Provider/UploadsServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/uploads/config/uploads.php` — config subtree (no repeated root)
- [ ] `framework/packages/platform/uploads/config/rules.php` — config shape rules
- [ ] `framework/packages/platform/uploads/README.md` — docs (Observability / Errors / Security-Redaction + slot/priority)

Middleware:
- [ ] `framework/packages/platform/uploads/src/Http/Middleware/MultipartFormDataMiddleware.php` — parse multipart safely (no superglobals)

Policy/validation:
- [ ] `framework/packages/platform/uploads/src/Upload/UploadPolicy.php` — max_files/max_bytes allowlists
- [ ] `framework/packages/platform/uploads/src/Upload/UploadedFileValidator.php` — mime/ext/size checks

Quarantine:
- [ ] `framework/packages/platform/uploads/src/Upload/QuarantineEntry.php` — `{schemaVersion,id,bytes,mime,ext,sha256,createdAt}`
- [ ] `framework/packages/platform/uploads/src/Upload/QuarantineStorage.php` — uses DiskInterface + SafePathJoiner

Errors + mapping:
- [ ] `framework/packages/platform/uploads/src/Exception/UploadsException.php` — base
- [ ] `framework/packages/platform/uploads/src/Exception/BadMultipartException.php` — `CORETSIA_HTTP_BAD_MULTIPART` (400)
- [ ] `framework/packages/platform/uploads/src/Exception/PayloadTooLargeException.php` — `CORETSIA_HTTP_PAYLOAD_TOO_LARGE` (413)
- [ ] `framework/packages/platform/uploads/src/Exception/UnsupportedMediaTypeException.php` — `CORETSIA_HTTP_UNSUPPORTED_MEDIA_TYPE` (415)
- [ ] `framework/packages/platform/uploads/src/Http/UploadsProblemMapper.php` — implements `ExceptionMapperInterface` (tag `error.mapper`)

Docs:
- [ ] `docs/guides/uploads.md` — policy examples + quarantine layout + security notes

#### Modifies

- [ ] `docs/ssot/http-middleware-catalog.md` — add/update row for `MultipartFormDataMiddleware` priority 80
  - [ ] row MUST use slot `http.middleware.app_pre` and priority `80`.
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0050-multipart-parsing-validation-quarantine.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/uploads/composer.json`
- [ ] `framework/packages/platform/uploads/src/Module/UploadsModule.php`
- [ ] `framework/packages/platform/uploads/src/Provider/UploadsServiceProvider.php`
- [ ] `framework/packages/platform/uploads/config/uploads.php`
- [ ] `framework/packages/platform/uploads/config/rules.php`
- [ ] `framework/packages/platform/uploads/README.md`
- [ ] `framework/packages/platform/uploads/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/uploads/config/uploads.php`
- [ ] Keys (dot):
  - [ ] `uploads.enabled` = true
  - [ ] `uploads.middleware.enabled` = true
  - [ ] `uploads.max_files` = 10
  - [ ] `uploads.max_total_bytes` = 10485760
  - [ ] `uploads.max_file_bytes` = 5242880
  - [ ] `uploads.allowed_mime` = []
  - [ ] `uploads.allowed_extensions` = []
  - [ ] `uploads.quarantine.enabled` = true
  - [ ] `uploads.quarantine.disk` = 'local'
  - [ ] `uploads.quarantine.prefix` = 'quarantine'
  - [ ] `uploads.filename_strategy` = 'content_hash'   # deterministic
- [ ] Rules:
  - [ ] `framework/packages/platform/uploads/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A *(uses global tags; contributes to slots/mappers)*
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Uploads\Http\Middleware\MultipartFormDataMiddleware`
  - [ ] adds tag: `http.middleware.app_pre` priority `80` meta `{toggle:'uploads.middleware.enabled'}`
  - [ ] registers: `Coretsia\Uploads\Http\UploadsProblemMapper`
  - [ ] adds tag: `error.mapper` priority `650` meta `{handles:'uploads exceptions'}`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `skeleton/var/quarantine/<id>.bin` (opaque bytes; never logged)
  - [ ] `skeleton/var/quarantine/<id>.json` (schemaVersion, deterministic json-like metadata)
- [ ] Reads:
  - [ ] validates quarantine entry schemaVersion when reading metadata (if read-path exists)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (for logs)
- [ ] Context writes (safe only):
  - [ ] none
- Reset discipline:
  - N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `uploads.process` (attrs: `outcome`, `bytes_total`)
  - [ ] `uploads.quarantine.write` (attrs: `outcome`)
- [ ] Metrics:
  - [ ] `uploads.received_total` (labels: `outcome=received`)
  - [ ] `uploads.rejected_total` (labels: `status`, `outcome=rejected`)
  - [ ] `uploads.quarantine_total` (labels: `outcome=written|skipped`)
  - [ ] `uploads.process_duration_ms` (labels: `outcome=ok|fail`)
- [ ] Label normalization applied (if needed):
  - [ ] `reason→status`
- [ ] Logs:
  - [ ] only counts + mime/ext/bytes; no filename/originalName/body

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/uploads/src/Exception/BadMultipartException.php` — `CORETSIA_HTTP_BAD_MULTIPART` (400)
  - [ ] `framework/packages/platform/uploads/src/Exception/PayloadTooLargeException.php` — `CORETSIA_HTTP_PAYLOAD_TOO_LARGE` (413)
  - [ ] `framework/packages/platform/uploads/src/Exception/UnsupportedMediaTypeException.php` — `CORETSIA_HTTP_UNSUPPORTED_MEDIA_TYPE` (415)
- [ ] Mapping:
  - [ ] `framework/packages/platform/uploads/src/Http/UploadsProblemMapper.php` via tag `error.mapper`

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens
  - [ ] raw payload/multipart bytes
  - [ ] filename/originalName
- [ ] Allowed:
  - [ ] counts + mime/ext/bytes
  - [ ] `hash(value)` / `len(value)` (safe ids; deterministic content hash)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → proof:
  - [ ] `framework/packages/platform/uploads/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → proof:
  - [ ] `framework/packages/platform/uploads/tests/Contract/NoSecretLoggingContractTest.php`
- [ ] If wiring exists → proof:
  - [ ] `framework/packages/platform/uploads/tests/Integration/Http/UploadsMiddlewareIsActiveWhenModuleEnabledTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/platform/uploads/tests/Fixtures/UploadsApp/config/modules.php` — enables `platform.uploads`

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/uploads/tests/Unit/UploadPolicyDefaultsAreSafeTest.php`
  - [ ] `framework/packages/platform/uploads/tests/Unit/UploadedFileValidatorRejectsForbiddenMimeAndExtTest.php`
- Contract:
  - [ ] `framework/packages/platform/uploads/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/uploads/tests/Contract/NoSecretLoggingContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/uploads/tests/Integration/MultipartRequestIsParsedWithoutSuperglobalsTest.php`
  - [ ] `framework/packages/platform/uploads/tests/Integration/FileTooLargeReturns413ProblemDetailsTest.php`
  - [ ] `framework/packages/platform/uploads/tests/Integration/MimeNotAllowedReturns415ProblemDetailsTest.php`
  - [ ] `framework/packages/platform/uploads/tests/Integration/QuarantineWritesDeterministicEntryTest.php`
  - [ ] `framework/packages/platform/uploads/tests/Integration/Http/UploadsMiddlewareIsActiveWhenModuleEnabledTest.php`
- Gates/Arch:
  - [ ] deptrac expectations satisfied (no forbidden deps)

### DoD (MUST)

- [ ] Middleware wired into `http.middleware.app_pre` priority 80
- [ ] Quarantine uses filesystem SafePathJoiner (NO DUPES)
- [ ] Quarantine schema & determinism boundaries (MUST)
  - [ ] Quarantine files are **runtime files**, not kernel artifacts.
    - [ ] Schema MUST be stable (`schemaVersion`), but values MAY be runtime-dependent.
  - [ ] If the epic requires deterministic fixtures/tests:
    - [ ] time MUST be injected via `Psr\Clock\ClockInterface` (use `Foundation\FrozenClock` in tests),
    - [ ] IDs MUST be deterministic when `uploads.filename_strategy=content_hash`:
      - [ ] `id = sha256(fileBytes)` (hex)
      - [ ] storage path derives only from `(prefix, id)` (no original filename).
  - [ ] Metadata MUST NOT contain raw filesystem paths or original filenames.
    - [ ] Allowed: `id`, `bytes`, `mime`, `ext`, `sha256`, optional `createdAt` (clock-controlled), and policy tokens.
- [ ] Error mapping via `error.mapper` (no direct rendering)
- [ ] Observability names/labels/redaction policy satisfied
- [ ] Tests pass + docs updated (`README.md`, `docs/guides/uploads.md`, `docs/ssot/http-middleware-catalog.md`)

---

### 4.60.0 Platform database core (DriverPort + ConnectionManager + QueryBuilder) (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.60.0"
owner_path: "framework/packages/platform/database/"

package_id: "platform/database"
composer: "coretsia/platform-database"
kind: runtime
module_id: "platform.database"

goal: "Надати driver-agnostic DB core: ConnectionManager + DriverRegistry + QueryExecutor choke point + повний Query DSL (Select/Insert/Update/Delete + Expression DSL) з детермінованою компіляцією SQL, noop-safe observability та жорсткою політикою redaction: жодного raw SQL у logs/metrics/CLI (hash/len only)."
provides:
- "ConnectionManager for resolving ConnectionInterface by name"
- "QueryExecutor as choke point with instrumentation + SQL redaction"
- "Fluent QueryBuilder pack (Select/Insert/Update/Delete + Expression DSL) with deterministic SQL compilation"
- "QueryBlueprint (sql+bindings+meta) for safe execution (never printed)"
- "Driver discovery contract: database.driver (... at least one reference driver package is required for CI (recommended: `platform/database-driver-sqlite`))"
- "Central PDO defaults policy (driver-agnostic config schema) for PDO-based driver packages"

tags_introduced: ["database.driver"]
config_roots_introduced: ["database"]
artifacts_introduced: []

adr: docs/adr/ADR-0051-database-core-driver-port-querybuilder.md
ssot_refs:
- docs/ssot/database-redaction.md
- docs/ssot/database-pdo-options.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `core/contracts` — database ports + observability ports
  - `core/foundation` — DI/config baseline
  - (policy) Phase 0 observability/security: No raw SQL (logs/metrics/CLI); durations int ms only
  - (policy) migrations ordering determinism (id/filename ASC) is enforced in migrations layer (separate epic)

- Required deliverables (exact paths):
  - `docs/adr/ADR-0051-database-core-driver-port-querybuilder.md` — ADR (locked before impl)
  - `docs/ssot/database-redaction.md` — SSoT: no raw SQL; safe table label policy

- Required config roots/keys:
  - `database` / `database.*` — this epic introduces root & keys

- Required tags:
  - `database.driver` — introduced by this epic as canonical driver discovery mechanism (drivers live in `platform/database-driver-*` packages)

- Required contracts / ports:
  - `Psr\Log\LoggerInterface`
  - `Psr\Clock\ClockInterface`
  - (optional) `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Database\DatabaseDriverInterface`
  - `Coretsia\Contracts\Database\ConnectionInterface`
  - `Coretsia\Contracts\Database\QueryResultInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - (optional) `Coretsia\Foundation\Time\Stopwatch`

- Runtime expectation (policy, NOT deps):
  - `platform/logging|tracing|metrics` provide implementations/noop-safe
  - at least one DB driver package is enabled for CI/fixtures baseline (recommended: `platform/database-driver-sqlite`).
  - production chooses any supported driver integration; sqlite is not “first production driver”

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `integrations/*`
- `platform/http`
- `platform/migrations` *(migrations depends on database, not vice versa)*

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
  - `Psr\Clock\ClockInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Database\DatabaseDriverInterface`
  - `Coretsia\Contracts\Database\ConnectionInterface`
  - `Coretsia\Contracts\Database\QueryResultInterface`
  - `Coretsia\Contracts\Database\SqlDialectInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Time\Stopwatch`

### Registry locks (MUST)

- This epic introduces:
  - tag: `database.driver`
  - config root: `database`
  - ssot doc: `docs/ssot/database-redaction.md`

It MUST update:
- `docs/ssot/tags.md` (row for `database.driver`, owner `platform/database`)
- `docs/ssot/config-roots.md` (row for `database`, owner `platform/database`)
- `docs/ssot/INDEX.md` (register `database-redaction.md`)

### SQL redaction lock (MUST)

- No raw SQL in logs/metrics/traces/CLI output.
- Allowed only:
  - `sql_hash = sha256(sql)` and `sql_len = strlen(sql)`
  - safe `operation`, `driver`, optional `table` ONLY if allowlisted by policy.
- SQL redaction applies to:
  - logs/metrics/traces/CLI output
  - exceptions/messages produced by platform/database components
- QueryBlueprint MUST NOT implement `__toString()` and MUST NOT expose raw SQL via implicit casts.

### Entry points / integration points (MUST)

- CLI:
  - N/A *(migrations CLI is separate)*
- HTTP:
  - N/A
- Kernel hooks/tags:
  - `database.driver` — **OWNER TAG** (owned by `platform/database`).
    - `platform/database-driver-*` packages contribute driver services tagged with `database.driver`
    - Meta schema (allowlisted): `{ name: '<driver>' }` (e.g. `{name:'sqlite'}`, `{name:'mysql'}`)
    - `platform/database` MUST NOT ship any vendor driver implementations.
- Artifacts:
  - N/A

### Cemented separation rules (MUST)

- `platform/database` is the **single owner** of:
  - QueryExecutor choke point (observability + redaction policy)
  - SQL redaction policy (no raw SQL anywhere)
  - `Driver discovery mechanism (database.driver) + optional database.driver_services map override`
  - Deterministic SQL build rules (QueryBuilder sorting/placeholder order)

- `platform/database-driver-*` MUST be thin:
  - "Implements `DatabaseDriverInterface` (+ its `ConnectionInterface`) only"
  - "MUST NOT implement QueryExecutor/QueryBuilder/observability/redaction policies"
  - "MUST NOT emit raw SQL/DSN/secrets (even in exception messages)"
  - "MUST NOT introduce config roots/keys; package config subtree MUST be `[]` and MUST NOT touch `database` root rules"

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/database/src/Dialect/SqlDialectRegistry.php`
  - Отримує dialect від driver/connection, кешує (якщо треба) та гарантує детермінований вибір.
- [ ] `framework/packages/platform/database/src/Driver/DriverCapabilities.php`
  - Опис можливостей драйвера (supportsLimitOffsetSyntaxX, supportsReturning, needsIdentitySelect, …) — без vendor-deps у core.

- [ ] `framework/packages/platform/database/src/Module/DatabaseModule.php` — runtime module
- [ ] `framework/packages/platform/database/src/Provider/DatabaseServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/database/src/Provider/DatabaseServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/database/src/Provider/Tags.php` — constants (`DB_DRIVER = 'database.driver'`)
- [ ] `framework/packages/platform/database/config/database.php` — config subtree (no repeated root)
- [ ] `framework/packages/platform/database/config/rules.php` — config shape rules
- [ ] `framework/packages/platform/database/README.md` — docs (Observability / Errors / Security-Redaction)
- [ ] `docs/guides/database.md` — configure drivers; sqlite example

Core:
- [ ] `framework/packages/platform/database/src/Connection/ConnectionManager.php` — resolve `ConnectionInterface` by name MUST:
  - Алгоритм (детерміністичний):
    - `connection($name ?? database.default)`
    - читаємо `database.connections[$name]`, якщо нема → `DatabaseConnectionNotConfiguredException`
    - беремо `driverId = connections[$name].driver`
    - `DriverRegistry->get(driverId)`, якщо нема → `DatabaseDriverNotFoundException`
    - Resolve driver config deterministically:
      - `driverCfg = database.drivers[driverId] ?? ['enabled' => true, 'tuning' => []]`
      - If `driverCfg.enabled === false`:
        - throw `DatabaseDriverDisabledException` (deterministic code)
    - Compute effective tuning (NO secrets):
      - `tuning = merge(driverCfg.tuning, connections[$name].tuning ?? [])`
    - Compute effective canonical PDO options map (string keys only):
      - `pdoBase = []`
      - If `database.pdo.enabled === true` AND `driverId` is in `database.pdo.apply_to`:
        - `pdoBase = database.pdo.options`
      - `pdoOverride = connections[$name].config.pdo_options ?? []`
      - `pdoEffective = merge(pdoBase, pdoOverride)` (override wins)
      - MUST normalize deterministically:
        - key-sort by `strcmp` after merge
        - reject any unknown keys (allowlist is locked by `docs/ssot/database-pdo-options.md`)
        - reject any float-like value anywhere (float-forbidden policy)
    - Pass to driver via `$config` only:
      - `config = connections[$name].config`
      - `config['pdo_options'] = pdoEffective`
    - Call:
      - `driver->connect($name, config, $tuning)`
    - кешуємо `ConnectionInterface` по `$name` (lazy-connect: створення — тільки при першому запиті)
  - Reset discipline (довгоживучі воркери):
    - якщо кешуєш connections → сервіс має імплементувати `ResetInterface` і бути під `kernel.reset` (або явно “no caching” як MVP)
  - Rationale (cemented):
    - Drivers MUST NOT read global config; therefore platform/database owns base-option merge.
    - Drivers only map canonical string keys to vendor attrs internally (PDO::ATTR_* never appears in public API).

- [ ] `framework/packages/platform/database/src/Query/QueryExecutor.php` — choke point instrumentation + redaction
- [ ] `framework/packages/platform/database/src/Query/QueryBuilder.php` — MVP CRUD builder (uses QueryExecutor)
- [ ] `framework/packages/platform/database/src/Query/Sql/SqlPlan.php` (або QueryPlan)
  - Проміжне IR (operation + table + columns + predicates + limit/offset + returning intent). Core генерує план, driver/dialect допомагає з SQL bytes.
- [ ] `framework/packages/platform/database/src/Driver/DriverRegistry.php` — resolves `DatabaseDriverInterface` by logical name:
  - `sources: database.driver_services map (override) OR database.driver tag discovery`
  - deterministic resolution; throws `DatabaseDriverNotFoundException` if missing

*(ConnectionManager uses DriverRegistry; QueryExecutor remains choke point for any query execution.)*

Observability:
- [ ] `framework/packages/platform/database/src/Observability/QueryInstrumentation.php` — spans/metrics/logging helpers

Redaction:
- [ ] `framework/packages/platform/database/src/Security/SqlRedaction.php` — never output SQL; allow `hash/len` only
- [ ] `framework/packages/platform/database/src/Security/DsnRedaction.php`
  - Утиліта: ніколи не логувати DSN/host/user/pass; дозволено лише hash/len + driver + connection name.

Query blueprint / compiler (core DX):
- [ ] `framework/packages/platform/database/src/Query/Blueprint/QueryBlueprint.php` — immutable: operation + sql + bindings + meta (no __toString)
- [ ] `framework/packages/platform/database/src/Query/Blueprint/QueryOperation.php` — enum-like (select|insert|update|delete|transaction)
- [ ] `framework/packages/platform/database/src/Query/Sql/SqlCompiler.php` — deterministic compilation from builders to QueryBlueprint
  - Тепер компіляція детермінована але з викликом dialect для частин синтаксису, що відрізняються між драйверами.
- [ ] `framework/packages/platform/database/src/Query/Sql/Bindings.php` — deterministic bindings normalization + placeholder ordering
- [ ] `framework/packages/platform/database/src/Query/Sql/Identifier.php` — strict identifier validation (no quoting dependency)
  - policy: identifiers MUST match allowlisted regex (e.g. `[A-Za-z_][A-Za-z0-9_]*` + optional dot segments)
- [ ] `framework/packages/platform/database/src/Query/Sql/DeterministicOrder.php` — helper for stable sorting (or reuse Foundation helper if exists)

Fluent QueryBuilder entrypoints:
- [ ] `framework/packages/platform/database/src/Query/Query.php` — user-facing entrypoint (creates builders bound to connection)
  - methods: `connection(?string $name)` / `table(string $table)` / `rawConnection(?string $name)` (optional)
- [ ] `framework/packages/platform/database/src/Query/Builder/TableQuery.php` — base: bound table + connection
- [ ] `framework/packages/platform/database/src/Query/Builder/SelectQuery.php`
- [ ] `framework/packages/platform/database/src/Query/Builder/InsertQuery.php`
- [ ] `framework/packages/platform/database/src/Query/Builder/UpdateQuery.php`
- [ ] `framework/packages/platform/database/src/Query/Builder/DeleteQuery.php`

Expression DSL (portable subset; deterministic):
- [ ] `framework/packages/platform/database/src/Query/Expr/Expr.php` — interface
- [ ] `framework/packages/platform/database/src/Query/Expr/AndX.php`
- [ ] `framework/packages/platform/database/src/Query/Expr/OrX.php`
- [ ] `framework/packages/platform/database/src/Query/Expr/Cmp.php` — (=, !=, <, <=, >, >=)
- [ ] `framework/packages/platform/database/src/Query/Expr/InList.php`
- [ ] `framework/packages/platform/database/src/Query/Expr/Between.php`
- [ ] `framework/packages/platform/database/src/Query/Expr/IsNull.php`
- [ ] `framework/packages/platform/database/src/Query/Expr/Like.php`
- [ ] `framework/packages/platform/database/src/Query/Expr/Not.php`
- [ ] `framework/packages/platform/database/src/Query/Expr/ExprFactory.php` — convenience builders

Transactions (DX):
- [ ] `framework/packages/platform/database/src/Transaction/TransactionManager.php`
  - runs closure with begin/commit/rollback through ConnectionInterface
  - MUST throw `DatabaseTransactionFailedException` on rollback failure path

Result helpers (if QueryResultInterface is minimal):
- [ ] `framework/packages/platform/database/src/Query/Result/QueryResult.php` — platform implementation of QueryResultInterface (rows + rowCount + lastInsertId + columns meta if available)
- [ ] `framework/packages/platform/database/src/Query/Result/Row.php` — optional value object / typed accessors (kept minimal, format-neutral)

Errors:
- [ ] `framework/packages/platform/database/src/Exception/DatabaseException.php`
- [ ] `framework/packages/platform/database/src/Exception/DatabaseConnectionFailedException.php` — `CORETSIA_DB_CONNECTION_FAILED`
- [ ] `framework/packages/platform/database/src/Exception/DatabaseQueryFailedException.php` — `CORETSIA_DB_QUERY_FAILED`
- [ ] `framework/packages/platform/database/src/Exception/DatabaseTransactionFailedException.php` — `CORETSIA_DB_TRANSACTION_FAILED`
- [ ] `framework/packages/platform/database/src/Exception/DatabaseDriverNotFoundException.php` — `CORETSIA_DB_DRIVER_NOT_FOUND`
- [ ] `framework/packages/platform/database/src/Exception/DatabaseConnectionNotConfiguredException.php` — `CORETSIA_DB_CONNECTION_NOT_CONFIGURED`
- [ ] `framework/packages/platform/database/src/Exception/DatabaseDriverDisabledException.php` — `CORETSIA_DB_DRIVER_DISABLED`

Docs:
- [ ] `docs/ssot/database-redaction.md` — SSoT: no raw SQL; safe table label policy
- [ ] `docs/ssot/database-pdo-options.md` — SSoT: driver-agnostic PDO options schema, merge order, allowlist rules
- [ ] `docs/ssot/database-config-schema.md` — canonical allowlists:
  - `database.*` keys allowlist
    - per-driver allowlists for `database.connections.<name>.config` by driverId
    - per-driver allowlists for `database.connections.<name>.tuning` by driverId (NO secrets)
      - MUST forbid secret-like keys: `password`, `secret`, `token`, `dsn`, `url`, `username`
    - `database.pdo.options` allowlist (string keys only)

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/database-redaction.md`
  - [ ] `docs/ssot/database-pdo-options.md`
  - [ ] `docs/ssot/database-config-schema.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0051-database-core-driver-port-querybuilder.md`
- [ ] `docs/ssot/tags.md` — add tag row:
  - tag: `database.driver`
  - owner: `platform/database`
  - purpose: driver discovery for platform/database
  - stability: stable
- [ ] `docs/ssot/config-roots.md` — add root row:
  - root: `database`
  - owner: `platform/database`
  - defaults: `framework/packages/platform/database/config/database.php`
  - rules: `framework/packages/platform/database/config/rules.php`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/database/composer.json`
- [ ] `framework/packages/platform/database/src/Module/DatabaseModule.php`
- [ ] `framework/packages/platform/database/src/Provider/DatabaseServiceProvider.php`
- [ ] `framework/packages/platform/database/config/database.php`
- [ ] `framework/packages/platform/database/config/rules.php`
- [ ] `framework/packages/platform/database/README.md`
- [ ] `framework/packages/platform/database/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/database/config/database.php`
- [ ] Keys (dot):
  - [ ] `database.enabled` = true
  - [ ] `database.default` = 'sqlite'                               # default connection name (NOT driver)
  - [ ] `database.labels` = []
  - [ ] `database.labels.table_allowlist` = []                      # list<string>
  - [ ] `database.labels.connection_allowlist` = []                 # list<string>
  - [ ] `database.driver_services` = []                             # map<string driverId, string serviceId> (optional override)

  - [ ] `database.drivers` = []                                     # map<string driverId, { enabled: bool, tuning: array }>

  - [ ] `database.pdo` = []                                         # canonical schema (string keys only)
  - [ ] `database.pdo.enabled` = true
  - [ ] `database.pdo.apply_to` = ['sqlite','mysql','mariadb','pgsql','sqlserver']
  - [ ] `database.pdo.options` = []
  - [ ] `database.pdo.options.errmode` = 'exception'                # exception|silent|warning
  - [ ] `database.pdo.options.default_fetch_mode` = 'assoc'         # assoc|num|both|obj
  - [ ] `database.pdo.options.stringify_fetches` = false
  - [ ] `database.pdo.options.persistent` = false
  - [ ] `database.pdo.options.timeout_s` = 30                       # int seconds (NO float)
  - [ ] `database.pdo.options.emulate_prepares` = false

  - [ ] `database.connections` = []                                 # map<connectionName, {driver,config,tuning?}>
  - [ ] `database.connections.sqlite` = []
  - [ ] `database.connections.sqlite.driver` = 'sqlite'
  - [ ] `database.connections.sqlite.config` = []
  - [ ] `database.connections.sqlite.config.url` = ''
  - [ ] `database.connections.sqlite.config.database` = 'database.sqlite'
  - [ ] `database.connections.sqlite.config.memory` = false
  - [ ] `database.connections.sqlite.config.prefix` = ''
  - [ ] `database.connections.sqlite.config.pdo_options` = []        # per-connection override (canonical keys)
  - [ ] `database.connections.sqlite.tuning` = []                    # NO secrets (driver allowlist; optional)

  - [ ] `database.connections.mysql` = []
  - [ ] `database.connections.mysql.driver` = 'mysql'
  - [ ] `database.connections.mysql.config` = []
  - [ ] `database.connections.mysql.config.url` = ''
  - [ ] `database.connections.mysql.config.host` = '127.0.0.1'
  - [ ] `database.connections.mysql.config.port` = 3306
  - [ ] `database.connections.mysql.config.database` = 'forge'
  - [ ] `database.connections.mysql.config.username` = 'forge'
  - [ ] `database.connections.mysql.config.password` = ''
  - [ ] `database.connections.mysql.config.unix_socket` = null
  - [ ] `database.connections.mysql.config.prefix` = ''
  - [ ] `database.connections.mysql.config.pdo_options` = []
  - [ ] `database.connections.mysql.tuning` = []

  - [ ] `database.connections.mariadb` = []
  - [ ] `database.connections.mariadb.driver` = 'mariadb'
  - [ ] `database.connections.mariadb.config` = []
  - [ ] `database.connections.mariadb.config.url` = ''
  - [ ] `database.connections.mariadb.config.host` = '127.0.0.1'
  - [ ] `database.connections.mariadb.config.port` = 3306
  - [ ] `database.connections.mariadb.config.database` = 'forge'
  - [ ] `database.connections.mariadb.config.username` = 'forge'
  - [ ] `database.connections.mariadb.config.password` = ''
  - [ ] `database.connections.mariadb.config.unix_socket` = null
  - [ ] `database.connections.mariadb.config.prefix` = ''
  - [ ] `database.connections.mariadb.config.pdo_options` = []
  - [ ] `database.connections.mariadb.tuning` = []

  - [ ] `database.connections.pgsql` = []
  - [ ] `database.connections.pgsql.driver` = 'pgsql'
  - [ ] `database.connections.pgsql.config` = []
  - [ ] `database.connections.pgsql.config.url` = ''
  - [ ] `database.connections.pgsql.config.host` = '127.0.0.1'
  - [ ] `database.connections.pgsql.config.port` = 5432
  - [ ] `database.connections.pgsql.config.database` = 'forge'
  - [ ] `database.connections.pgsql.config.username` = 'forge'
  - [ ] `database.connections.pgsql.config.password` = ''
  - [ ] `database.connections.pgsql.config.prefix` = ''
  - [ ] `database.connections.pgsql.config.pdo_options` = []
  - [ ] `database.connections.pgsql.tuning` = []

  - [ ] `database.connections.sqlserver` = []
  - [ ] `database.connections.sqlserver.driver` = 'sqlserver'
  - [ ] `database.connections.sqlserver.config` = []
  - [ ] `database.connections.sqlserver.config.url` = ''
  - [ ] `database.connections.sqlserver.config.host` = 'localhost'
  - [ ] `database.connections.sqlserver.config.port` = 1433
  - [ ] `database.connections.sqlserver.config.database` = 'forge'
  - [ ] `database.connections.sqlserver.config.username` = 'forge'
  - [ ] `database.connections.sqlserver.config.password` = ''
  - [ ] `database.connections.sqlserver.config.prefix` = ''
  - [ ] `database.connections.sqlserver.config.pdo_options` = []
  - [ ] `database.connections.sqlserver.tuning` = []
- [ ] Rules:
  - [ ] `framework/packages/platform/database/config/rules.php` enforces shape (MVP):
    - [ ] subtree shape (no repeated root)
    - [ ] `database.driver_services` is `map<string, string>` (driverId => serviceId)
    - [ ] `database.drivers` is `map<string, array{enabled:bool, tuning:array}>`
    - [ ] `database.pdo.enabled` is bool
    - [ ] `database.pdo.apply_to` is `list<string>` and each item MUST be one of: `sqlite|mysql|mariadb|pgsql|sqlserver`
    - [ ] `database.pdo.options` is allowlisted canonical map (string keys only; float-forbidden)
    - [ ] `database.connections` is `map<string, array>`
    - [ ] `database.connections.<name>.driver` is non-empty `string` and MUST be one of: `sqlite|mysql|mariadb|pgsql|sqlserver`
    - [ ] `database.connections.<name>.config` is `array`
    - [ ] `database.connections.<name>.config.pdo_options` is allowlisted canonical map (string keys only; float-forbidden)
    - [ ] `database.connections.<name>.tuning` is `array` (NO secrets; allowlist-only; secret-like keys forbidden by schema)
    - [ ] `database.labels.table_allowlist` is `list<string>`
    - [ ] `database.labels.connection_allowlist` is `list<string>`
- [ ] Cemented rule:
  - [ ] hard-fail if any float found under `database.*`
  - [ ] `database.pdo.*` is used ONLY by PDO-based driver packages; non-PDO drivers MUST ignore it.
  - [ ] `platform/database` MUST NOT reference PDO constants anywhere.

- Non-goals / OUT OF SCOPE for 4.60.0 (MUST):
  - `database.pool.*`
  - `database.logging.*`
  - `database.read_write.*`
  - `database.health.*`
  - `database.security.*`
  - `database.observability.*`
- These return as separate epics; they MUST NOT inflate 4.60.0 rules/defaults.

- Щоб коректно підтримати multi-connection без змішування “secrets” і “tuning”, я пропоную додати опційний ключ:
  - `database.connections.<name>.tuning` = `[]` (NO secrets)
- Тоді в `connect()` передаємо:
  - `$config` = тільки `connections.<name>.config` (можуть бути secret values)
  - `$tuning` = merge(`drivers.<driver>.tuning`, `connections.<name>.tuning`) (secrets заборонені rules-ами)
- Це дає чітку модель:
  - secrets живуть тільки у `connections.*.config`
  - tuning живе у `drivers.*.tuning` + опційно per connection override

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `database.driver`
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Database\Connection\ConnectionManager`
  - [ ] registers: `Coretsia\Database\Query\QueryExecutor`
  - [ ] registers: `Coretsia\Database\Query\QueryBuilder`
  - [ ] registers: `Coretsia\Database\Query\Sql\SqlCompiler`
  - [ ] registers: `Coretsia\Database\Query\Query`
  - [ ] registers: `Coretsia\Database\Driver\DriverRegistry`
  - [ ] registers: `Coretsia\Database\Transaction\TransactionManager`
  - [ ] defines tag constant: `database.driver` (integrations provide tagged driver services)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (for logs)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] no stateful services expected; if connection caching added → `ResetInterface` + `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `db.query` (attrs: `driver`, `operation`, `outcome`, `table?` if safe)
- [ ] Metrics:
  - [ ] `db.query_total` (labels: `driver`, `operation`, `outcome` + optional `table`)
  - [ ] `db.query_failed_total` (labels: `driver`, `operation`, `outcome=fail`)
  - [ ] `db.query_duration_ms` (labels: `driver`, `operation`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `op→operation`, `kind→operation`
- [ ] Logs:
  - [ ] slow query log is redacted (no SQL); may include `hash(sql)` and `duration_ms`

- Політика, яка добре лягає на інваріанти:
  - SQL: як і раніше — тільки `sql_hash/sql_len`
  - DSN/creds: ніколи
  - connection name:
    - у logs/spans можна (це low-card у нормальних системах)
    - у metrics labels — тільки якщо allowlisted (напр. `database.labels.connection_allowlist = ['main','analytics']`), інакше `hash(name)`
- Logs/Spans:
  - MAY include raw connection name (low-card), but MUST NOT include DSN/user/pass or raw SQL
- Metrics labels:
  - Introduce allowlist:
    - `database.labels.connection_allowlist = []`
  - If `name` is allowlisted → label value is raw `name`
  - Else → label value is `sha256(name)` (or stable short form), never raw

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/database/src/Exception/DatabaseConnectionFailedException.php` — `CORETSIA_DB_CONNECTION_FAILED`
  - [ ] `framework/packages/platform/database/src/Exception/DatabaseQueryFailedException.php` — `CORETSIA_DB_QUERY_FAILED`
  - [ ] `framework/packages/platform/database/src/Exception/DatabaseTransactionFailedException.php` — `CORETSIA_DB_TRANSACTION_FAILED`
  - [ ] `framework/packages/platform/database/src/Exception/DatabaseDriverNotFoundException.php` — `CORETSIA_DB_DRIVER_NOT_FOUND`
  - [ ] `framework/packages/platform/database/src/Exception/DatabaseConnectionNotConfiguredException.php` — `CORETSIA_DB_CONNECTION_NOT_CONFIGURED`
  - [ ] `framework/packages/platform/database/src/Exception/DatabaseDriverDisabledException.php` — `CORETSIA_DB_DRIVER_DISABLED`
- [ ] Mapping:
  - [ ] reuse existing mapper (DefaultExceptionMapper) *(no dupes)*

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw SQL
  - [ ] DSN credentials
  - [ ] payload values / PII
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)`; safe `driver` name
  - [ ] optional safe `table` label only if policy allows (whitelist)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → proof:
  - [ ] `framework/packages/platform/database/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/database/tests/Contract/NoRawSqlInMetricsLabelsContractTest.php`
- [ ] If redaction exists → proof:
  - [ ] `framework/packages/platform/database/tests/Contract/SqlNeverPrintedContractTest.php`
- [ ] Deterministic SQL build → proof:
  - [ ] `framework/packages/platform/database/tests/Unit/QueryBuilderBuildsDeterministicSqlForBasicCrudTest.php`

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/database/tests/Unit/SqlRedactionNeverReturnsRawSqlTest.php`
  - [ ] `framework/packages/platform/database/tests/Unit/QueryBuilderBuildsDeterministicSqlForBasicCrudTest.php`
- Contract:
  - [ ] `framework/packages/platform/database/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/database/tests/Contract/NoRawSqlInMetricsLabelsContractTest.php`
  - [ ] `framework/packages/platform/database/tests/Contract/SqlNeverPrintedContractTest.php`
  - [ ] `framework/packages/platform/database/tests/Contract/MetricsLabelsDoNotContainSqlOrIdsContractTest.php`
  - [ ] `framework/packages/core/contracts/tests/Contract/DatabaseContractsShapeContractTest.php` *(contracts scope)*
- Integration:
  - [ ] `framework/packages/platform/database/tests/Integration/CrudOnSqliteTest.php`
  - [ ] `framework/packages/platform/database/tests/Integration/QueryExecutorEmitsMetricsNoopSafeTest.php`
  - [ ] `framework/packages/platform/database/tests/Integration/QueryLoggingIsRedactedTest.php`
- Gates/Arch:
  - [ ] deptrac: `platform/database` MUST NOT depend on `integrations/*`

### DoD (MUST)

- [ ] One choke point QueryExecutor exists and is used by QueryBuilder
- [ ] Observability names/labels allowlist + no raw SQL leakage (logs/metrics/CLI)
- [ ] Tests pass
- [ ] Docs updated (`README.md`, `docs/ssot/database-redaction.md`, `docs/guides/database.md`)
- [ ] Deterministic SQL build contract (MUST)
  - [ ] QueryBuilder MUST generate identical SQL bytes for identical semantic inputs:
    - [ ] associative maps (columns/where) MUST be key-sorted by `strcmp` before SQL emission,
    - [ ] placeholder ordering MUST follow the same sorted order,
    - [ ] durations MUST be `int` ms only (no floats, no microtime).
  - [ ] Query results MUST respect contracts “no floats”:
    - [ ] any float-like DB values MUST be converted to `string` deterministically by the driver layer.
- [ ] Developer UX:
  - [ ] Can write queries without touching driver layer:
    - `Query->table('users')->select([...])->where(...)->get()`
    - `->insert([...])->execute()`
    - `->update([...])->where(...)->execute()`
    - `->delete()->where(...)->execute()`
  - [ ] Deterministic SQL bytes for same semantic input (sorted keys + stable placeholder order)
- [ ] No policy leaks:
  - [ ] QueryBlueprint raw SQL is never printed/logged
  - [ ] QueryExecutor logs/metrics/traces contain only sql_hash/sql_len + safe labels
- [ ] Disabled driver referenced by a connection fails deterministically with `CORETSIA_DB_DRIVER_DISABLED`

---

### 4.70.0 Platform database-driver-sqlite (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.70.0"
owner_path: "framework/packages/platform/database-driver-sqlite/"

package_id: "platform/database-driver-sqlite"
composer: "coretsia/platform-database-driver-sqlite"
kind: runtime
module_id: "platform.database-driver-sqlite"

goal: "Надати runtime SQLite драйвер для `platform/database` через tag `database.driver` із lazy-connect, deterministic DSN/options та жорсткою політикою no raw SQL/DSN/secrets у будь-яких output-paths."
provides:
- "SQLite driver implementation (PDO sqlite) for `Coretsia\\Contracts\\Database\\DatabaseDriverInterface`"
- "Wiring into `platform/database` via tag `database.driver` meta `{name:'sqlite'}`"
- "Driver-owned deterministic DSN builder + canonical PDO options mapping (string keys лише)"
- "SQLite dialect (`SqlDialectInterface`) for limit/offset/returning/identity semantics"
- "Value normalization policy: no-floats (float-like DB values -> string deterministically)"
- "Deterministic exception hygiene (never leak raw SQL/DSN/secrets; stable messages)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/database-config-schema.md"
- "docs/ssot/database-pdo-options.md"
- "docs/ssot/database-redaction.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.150.0 — `core/contracts` Database ports exist (no vendor concretes in contracts)
  - 4.60.0 — `platform/database` core exists and OWNS:
    - tag `database.driver`
    - root `database` config + schema rules

- Canonical schema prerequisites (MUST):
  - `docs/ssot/database-config-schema.md` exists and locks:
    - per-driver allowlist for `connections.*.config` (this driver)
    - per-driver allowlist for `connections.*.tuning` (NO secrets)
    - `database.pdo.options` allowlist (string keys only)
  - `docs/ssot/database-pdo-options.md` exists and locks canonical keys + merge policy
  - `docs/ssot/database-redaction.md` exists and locks no-SQL/no-DSN output policy

- Required tags:
  - `database.driver` — integration point (owner: platform/database)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `platform/database`

Forbidden:

- `platform/http`
- `platform/migrations`
- other `platform/database-driver-*` packages (no coupling між драйверами)
- `integrations/*`
- vendor concretes in contracts layer (vendor types allowed ONLY inside this package impl)

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Database\DatabaseDriverInterface`
  - `Coretsia\Contracts\Database\ConnectionInterface`
  - `Coretsia\Contracts\Database\QueryResultInterface`
  - `Coretsia\Contracts\Database\SqlDialectInterface`

- MUST ensure its `ConnectionInterface` implementation returns:
  - `name()` = `$connectionName` passed into `connect(...)`
  - `driverId()` = driver's logical id (`sqlite|mysql|mariadb|pgsql|sqlserver`)

### Entry points / integration points (MUST)

- Kernel hooks/tags:
  - tag: `database.driver` (owner: platform/database)
  - meta allowlist (MUST): `{name:'sqlite'}`
  - This package MUST reference the owner tag constant from `platform/database` (no local duplicates).

#### PDO options canonical map (MUST)

- `platform/database` owns the canonical merge:
  - effective map is passed to the driver via `$config['pdo_options']`
  - keys are canonical string keys only (no PDO constants)
- Driver MUST:
  - treat `$config['pdo_options']` as the effective canonical map (already merged upstream)
  - map canonical keys → vendor attrs internally (PDO::ATTR_* is internal to this package)
  - be deterministic (stable mapping + stable normalization)
  - MUST NOT read global config directly

#### Exception hygiene (MUST)

For any thrown exception from driver/connect/execute:
- MUST NOT include: raw SQL, DSN, username, password, token, file paths (if any)
- MAY include: `driverId`, `connectionName`, `sql_hash/sql_len` (if query-related), stable error code/category

Update the driver epic text to explicitly mention:
- connectionName may appear in message/spans/logs
- metrics label uses platform/database policy (allowlist/hash)

#### Dependency note (MUST)

Because we do NOT introduce a shared PDO epic:
- remove any mention of dependency on `platform/database-driver-pdo`
- keep duplicated PDO infra inside each driver package, but require semantic parity via tests + SSoT refs:
  - `docs/ssot/database-pdo-options.md`
  - `docs/ssot/database-redaction.md`
  - (optional) `docs/ssot/database-config-schema.md` if adopted in 4.60.0

### Deliverables (MUST)

#### Creates

Package skeleton:
- [ ] `framework/packages/platform/database-driver-sqlite/composer.json` — package definition
- [ ] `framework/packages/platform/database-driver-sqlite/src/Module/DatabaseDriverSqliteModule.php` — runtime module entry
- [ ] `framework/packages/platform/database-driver-sqlite/src/Provider/DatabaseDriverSqliteServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/database-driver-sqlite/src/Provider/DatabaseDriverSqliteServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/database-driver-sqlite/config/database_driver_sqlite.php` — config subtree (MUST return `[]`)
- [ ] `framework/packages/platform/database-driver-sqlite/config/rules.php` — config shape enforcement (MUST NOT touch `database` root rules)
- [ ] `framework/packages/platform/database-driver-sqlite/README.md` — docs (Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/database-driver-sqlite/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — runtime-only contract smoke

Shared PDO infra (duplicated per driver by design):
- [ ] `framework/packages/platform/database-driver-sqlite/src/Driver/Pdo/PdoConnection.php` — implements `ConnectionInterface` (thin wrapper)
- [ ] `framework/packages/platform/database-driver-sqlite/src/Driver/Pdo/PdoConnector.php` — lazy factory: builds PDO on-demand (MUST NOT connect during container build)
- [ ] `framework/packages/platform/database-driver-sqlite/src/Driver/Pdo/PdoOptionsCanonicalizer.php` — validates + normalizes EFFECTIVE canonical options map (string keys)
  - MUST NOT merge global base options (ownership is platform/database)
  - input source is `$config['pdo_options']` (already merged upstream)
  - MUST hard-fail on unknown keys and any float-like values

SQLite implementation:
- [ ] `framework/packages/platform/database-driver-sqlite/src/Driver/Sqlite/PdoSqliteDriver.php`
- [ ] `framework/packages/platform/database-driver-sqlite/src/Driver/Sqlite/SqliteDialect.php`
- [ ] `framework/packages/platform/database-driver-sqlite/src/Driver/Sqlite/SqliteDsnBuilder.php`
- [ ] `framework/packages/platform/database-driver-sqlite/src/Driver/Sqlite/SqlitePdoOptionsMapper.php`
- [ ] `framework/packages/platform/database-driver-sqlite/src/Driver/Sqlite/SqliteValueNormalizer.php`
- [ ] `framework/packages/platform/database-driver-sqlite/src/Driver/Sqlite/SqliteExceptionHygiene.php` — safe exception messages (no DSN/paths/secrets)

Tests (non-exhaustive, MUST be enforceable):
- [ ] `framework/packages/platform/database-driver-sqlite/tests/Integration/CrudOnSqliteTest.php` — Tier A CI real DB test (sqlite)
- [ ] `framework/packages/platform/database-driver-sqlite/tests/Integration/Wiring/SqliteDriverServiceWiringTest.php` — tag/meta + lazy-connect no side effects
- [ ] `framework/packages/platform/database-driver-sqlite/tests/Contract/NoDsnOrSecretLeakInDriverExceptionsContractTest.php` — asserts: secrets not present in thrown messages

#### Modifies

- [ ] `docs/guides/database.md` — update module enable example to use `platform.database-driver-sqlite`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/database-driver-sqlite/composer.json`
- [ ] `framework/packages/platform/database-driver-sqlite/src/Module/DatabaseDriverSqliteModule.php` (runtime only)
- [ ] `framework/packages/platform/database-driver-sqlite/src/Provider/DatabaseDriverSqliteServiceProvider.php` (runtime only)
- [ ] `framework/packages/platform/database-driver-sqlite/config/database_driver_sqlite.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/platform/database-driver-sqlite/config/rules.php`
- [ ] `framework/packages/platform/database-driver-sqlite/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/database-driver-sqlite/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/database-driver-sqlite/config/database_driver_sqlite.php`
- [ ] Keys (dot):
  - [ ] N/A (package does not own keys; MUST return `[]`)
- [ ] Rules:
  - [ ] `framework/packages/platform/database-driver-sqlite/config/rules.php` enforces shape
    - MUST return `[]`
    - MUST NOT add/override rules for `database` root (owner rules are in `platform/database`)

Driver MUST NOT read global config directly.
It MUST rely exclusively on:
- `$config` (connection config; secrets allowed)
- `$tuning` (NO secrets; already merged by platform/database)

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\DatabaseDriverSqlite\Driver\Sqlite\PdoSqliteDriver`
  - [ ] adds tag: `database.driver` priority `0` meta `{name:'sqlite'}`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Delegates observability to `platform/database` choke point (QueryExecutor)
- [ ] MUST NOT log DSN credentials / raw SQL; allowed: driver id + connection name + hash/len summaries only

#### Errors

- [ ] Deterministic exception hygiene: no raw SQL/DSN/secrets in thrown messages

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw SQL
  - [ ] DSN credentials / env secrets
  - [ ] full file paths (sqlite file path) — only hash/len if needed
- [ ] Allowed:
  - [ ] driver id, connection name, `hash(value)` / `len(value)`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If redaction exists → `framework/packages/platform/database-driver-sqlite/tests/Contract/NoDsnOrSecretLeakInDriverExceptionsContractTest.php`

#### Test harness / fixtures (when integration is needed)

- Fixture app:
  - N/A (sqlite integration test can boot minimal container fixture if required by harness)
- Fake adapters:
  - N/A (observability is owned by platform/database)

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `framework/packages/platform/database-driver-sqlite/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/database-driver-sqlite/tests/Contract/NoDsnOrSecretLeakInDriverExceptionsContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/database-driver-sqlite/tests/Integration/CrudOnSqliteTest.php`
  - [ ] `framework/packages/platform/database-driver-sqlite/tests/Integration/Wiring/SqliteDriverServiceWiringTest.php`
- Gates/Arch:
  - [ ] deptrac: forbidden deps enforced (no platform/http, no platform/migrations, no other driver packages)

### DoD (MUST)

- [ ] Driver module wired via tag `database.driver` meta `{name:'sqlite'}`
- [ ] Lazy-connect enforced (no connect during container build)
- [ ] SQLite CRUD integration test passes deterministically (Tier A CI)
- [ ] No DSN/SQL/secrets leakage in any output path
- [ ] Tests green + README/docs updated
- [ ] No new config roots/keys introduced (config subtree is `[]`)
- [ ] deps/forbidden respected (deptrac; no cycles)

---

### 4.71.0 Platform database-driver-mysql (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.71.0"
owner_path: "framework/packages/platform/database-driver-mysql/"

package_id: "platform/database-driver-mysql"
composer: "coretsia/platform-database-driver-mysql"
kind: runtime
module_id: "platform.database-driver-mysql"

goal: "Надати runtime MySQL драйвер для `platform/database` через tag `database.driver` із lazy-connect, deterministic DSN/options та no raw SQL/DSN/secrets у будь-яких output-paths."
provides:
- "MySQL driver implementation (PDO mysql) for `Coretsia\\Contracts\\Database\\DatabaseDriverInterface`"
- "Wiring into `platform/database` via tag `database.driver` meta `{name:'mysql'}`"
- "Deterministic DSN builder + canonical PDO options mapping (string keys only)"
- "MySQL dialect (`SqlDialectInterface`) for limit/offset/returning semantics"
- "Value normalization policy: no-floats (float-like DB values -> string deterministically)"
- "Deterministic exception hygiene (never leak raw SQL/DSN/secrets; stable messages)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/database-config-schema.md"
- "docs/ssot/database-pdo-options.md"
- "docs/ssot/database-redaction.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.150.0 — `core/contracts` Database ports exist
  - 4.60.0 — `platform/database` core exists and OWNS `database.driver` + `database` root schema

- Canonical schema prerequisites (MUST):
  - `docs/ssot/database-config-schema.md` exists and locks:
    - per-driver allowlist for `connections.*.config` (this driver)
    - per-driver allowlist for `connections.*.tuning` (NO secrets)
    - `database.pdo.options` allowlist (string keys only)
  - `docs/ssot/database-pdo-options.md` exists and locks canonical keys + merge policy
  - `docs/ssot/database-redaction.md` exists and locks no-SQL/no-DSN output policy

- Required tags:
  - `database.driver` — integration point (owner: platform/database)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `platform/database`

Forbidden:

- `platform/http`
- `platform/migrations`
- other `platform/database-driver-*` packages
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Database\DatabaseDriverInterface`
  - `Coretsia\Contracts\Database\ConnectionInterface`
  - `Coretsia\Contracts\Database\QueryResultInterface`
  - `Coretsia\Contracts\Database\SqlDialectInterface`

- MUST ensure its `ConnectionInterface` implementation returns:
  - `name()` = `$connectionName` passed into `connect(...)`
  - `driverId()` = driver's logical id (`sqlite|mysql|mariadb|pgsql|sqlserver`)

### Entry points / integration points (MUST)

- Kernel hooks/tags:
  - tag: `database.driver` (owner: platform/database)
  - meta allowlist (MUST): `{name:'mysql'}`
  - MUST reference the owner tag constant from `platform/database` (no local duplicates).

#### PDO options canonical map (MUST)

- `platform/database` owns the canonical merge:
  - effective map is passed to the driver via `$config['pdo_options']`
  - keys are canonical string keys only (no PDO constants)
- Driver MUST:
  - treat `$config['pdo_options']` as the effective canonical map (already merged upstream)
  - map canonical keys → vendor attrs internally (PDO::ATTR_* is internal to this package)
  - be deterministic (stable mapping + stable normalization)
  - MUST NOT read global config directly

#### Exception hygiene (MUST)

For any thrown exception from driver/connect/execute:
- MUST NOT include: raw SQL, DSN, username, password, token, file paths (if any)
- MAY include: `driverId`, `connectionName`, `sql_hash/sql_len` (if query-related), stable error code/category

Update the driver epic text to explicitly mention:
- connectionName may appear in message/spans/logs
- metrics label uses platform/database policy (allowlist/hash)

#### Dependency note (MUST)

Because we do NOT introduce a shared PDO epic:
- remove any mention of dependency on `platform/database-driver-pdo`
- keep duplicated PDO infra inside each driver package, but require semantic parity via tests + SSoT refs:
  - `docs/ssot/database-pdo-options.md`
  - `docs/ssot/database-redaction.md`
  - (optional) `docs/ssot/database-config-schema.md` if adopted in 4.60.0

### Deliverables (MUST)

#### Creates

Package skeleton:
- [ ] `framework/packages/platform/database-driver-mysql/composer.json`
- [ ] `framework/packages/platform/database-driver-mysql/src/Module/DatabaseDriverMysqlModule.php`
- [ ] `framework/packages/platform/database-driver-mysql/src/Provider/DatabaseDriverMysqlServiceProvider.php`
- [ ] `framework/packages/platform/database-driver-mysql/src/Provider/DatabaseDriverMysqlServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/database-driver-mysql/config/database_driver_mysql.php` — MUST return `[]`
- [ ] `framework/packages/platform/database-driver-mysql/config/rules.php` — MUST NOT touch `database` root rules
- [ ] `framework/packages/platform/database-driver-mysql/README.md`
- [ ] `framework/packages/platform/database-driver-mysql/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

Shared PDO infra (duplicated per driver by design):
- [ ] `framework/packages/platform/database-driver-mysql/src/Driver/Pdo/PdoConnection.php`
- [ ] `framework/packages/platform/database-driver-mysql/src/Driver/Pdo/PdoConnector.php`
- [ ] `framework/packages/platform/database-driver-mysql/src/Driver/Pdo/PdoOptionsCanonicalizer.php` — validates + normalizes EFFECTIVE canonical options map (string keys)
  - MUST NOT merge global base options (ownership is platform/database)
  - input source is `$config['pdo_options']` (already merged upstream)
  - MUST hard-fail on unknown keys and any float-like values

MySQL implementation:
- [ ] `framework/packages/platform/database-driver-mysql/src/Driver/Mysql/PdoMysqlDriver.php`
- [ ] `framework/packages/platform/database-driver-mysql/src/Driver/Mysql/MysqlDialect.php`
- [ ] `framework/packages/platform/database-driver-mysql/src/Driver/Mysql/MysqlDsnBuilder.php`
- [ ] `framework/packages/platform/database-driver-mysql/src/Driver/Mysql/MysqlPdoOptionsMapper.php`
- [ ] `framework/packages/platform/database-driver-mysql/src/Driver/Mysql/MysqlValueNormalizer.php`
- [ ] `framework/packages/platform/database-driver-mysql/src/Driver/Mysql/MysqlExceptionHygiene.php`

Tests:
- [ ] `framework/packages/platform/database-driver-mysql/tests/Integration/Wiring/MysqlDriverServiceWiringTest.php`
- [ ] `framework/packages/platform/database-driver-mysql/tests/Contract/NoDsnOrSecretLeakInDriverExceptionsContractTest.php`

#### Modifies

- [ ] `docs/guides/database.md` — add/adjust MySQL module enable example (`platform.database-driver-mysql`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/database-driver-mysql/composer.json`
- [ ] `framework/packages/platform/database-driver-mysql/src/Module/DatabaseDriverMysqlModule.php`
- [ ] `framework/packages/platform/database-driver-mysql/src/Provider/DatabaseDriverMysqlServiceProvider.php`
- [ ] `framework/packages/platform/database-driver-mysql/config/database_driver_mysql.php`
- [ ] `framework/packages/platform/database-driver-mysql/config/rules.php`
- [ ] `framework/packages/platform/database-driver-mysql/README.md`
- [ ] `framework/packages/platform/database-driver-mysql/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/database-driver-mysql/config/database_driver_mysql.php`
- [ ] Keys (dot):
  - [ ] N/A (package does not own keys; MUST return `[]`)
- [ ] Rules:
  - [ ] `framework/packages/platform/database-driver-mysql/config/rules.php` enforces: MUST return `[]`

Driver MUST NOT read global config directly.
It MUST rely exclusively on:
- `$config` (connection config; secrets allowed)
- `$tuning` (NO secrets; already merged by platform/database)

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\DatabaseDriverMysql\Driver\Mysql\PdoMysqlDriver`
  - [ ] adds tag: `database.driver` priority `0` meta `{name:'mysql'}`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Delegates observability to `platform/database`
- [ ] MUST NOT emit raw SQL/DSN/secrets

#### Errors

- [ ] Deterministic exception hygiene (no secrets / no SQL / no DSN)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] DSN credentials / env secrets / raw SQL
- [ ] Allowed:
  - [ ] driver id, connection name, hash/len summaries

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If redaction exists → `framework/packages/platform/database-driver-mysql/tests/Contract/NoDsnOrSecretLeakInDriverExceptionsContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A (wiring-only; no real MySQL server required)

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `framework/packages/platform/database-driver-mysql/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/database-driver-mysql/tests/Contract/NoDsnOrSecretLeakInDriverExceptionsContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/database-driver-mysql/tests/Integration/Wiring/MysqlDriverServiceWiringTest.php`
- Gates/Arch:
  - [ ] deptrac: forbidden deps enforced (no platform/http, no platform/migrations, no other driver packages)

### DoD (MUST)

- [ ] Driver module wired via tag `database.driver` meta `{name:'mysql'}`
- [ ] Lazy-connect enforced
- [ ] No DSN/SQL/secrets leakage proven by contract test
- [ ] Tests green + README/docs updated
- [ ] No new config roots/keys introduced (config subtree is `[]`)

---

### 4.72.0 Platform database-driver-mariadb (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.72.0"
owner_path: "framework/packages/platform/database-driver-mariadb/"

package_id: "platform/database-driver-mariadb"
composer: "coretsia/platform-database-driver-mariadb"
kind: runtime
module_id: "platform.database-driver-mariadb"

goal: "Надати runtime MariaDB драйвер для `platform/database` через tag `database.driver` із lazy-connect та deterministic DSN/options без leakage."
provides:
- "MariaDB driver implementation (PDO mysql; driverId=mariadb) for `DatabaseDriverInterface`"
- "Wiring into `platform/database` via tag `database.driver` meta `{name:'mariadb'}`"
- "Deterministic DSN builder + canonical PDO options mapping (string keys only)"
- "MariaDB dialect (`SqlDialectInterface`) (може reuse MySQL-сумісну поведінку, але без cross-package deps)"
- "Value normalization policy: no-floats"
- "Deterministic exception hygiene (no raw SQL/DSN/secrets)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/database-config-schema.md"
- "docs/ssot/database-pdo-options.md"
- "docs/ssot/database-redaction.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.150.0 — `core/contracts` Database ports exist
  - 4.60.0 — `platform/database` core exists and OWNS `database.driver` + `database` root schema

- Canonical schema prerequisites (MUST):
  - `docs/ssot/database-config-schema.md` exists and locks:
    - per-driver allowlist for `connections.*.config` (this driver)
    - per-driver allowlist for `connections.*.tuning` (NO secrets)
    - `database.pdo.options` allowlist (string keys only)
  - `docs/ssot/database-pdo-options.md` exists and locks canonical keys + merge policy
  - `docs/ssot/database-redaction.md` exists and locks no-SQL/no-DSN output policy

- Required tags:
  - `database.driver` — integration point (owner: platform/database)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `platform/database`

Forbidden:

- `platform/http`
- `platform/migrations`
- other `platform/database-driver-*` packages
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Database\DatabaseDriverInterface`
  - `Coretsia\Contracts\Database\ConnectionInterface`
  - `Coretsia\Contracts\Database\QueryResultInterface`
  - `Coretsia\Contracts\Database\SqlDialectInterface`

- MUST ensure its `ConnectionInterface` implementation returns:
  - `name()` = `$connectionName` passed into `connect(...)`
  - `driverId()` = driver's logical id (`sqlite|mysql|mariadb|pgsql|sqlserver`)

### Entry points / integration points (MUST)

- Kernel hooks/tags:
  - tag: `database.driver` (owner: platform/database)
  - meta allowlist (MUST): `{name:'mariadb'}`
  - MUST reference the owner tag constant from `platform/database`.

#### PDO options canonical map (MUST)

- `platform/database` owns the canonical merge:
  - effective map is passed to the driver via `$config['pdo_options']`
  - keys are canonical string keys only (no PDO constants)
- Driver MUST:
  - treat `$config['pdo_options']` as the effective canonical map (already merged upstream)
  - map canonical keys → vendor attrs internally (PDO::ATTR_* is internal to this package)
  - be deterministic (stable mapping + stable normalization)
  - MUST NOT read global config directly

#### Exception hygiene (MUST)

For any thrown exception from driver/connect/execute:
- MUST NOT include: raw SQL, DSN, username, password, token, file paths (if any)
- MAY include: `driverId`, `connectionName`, `sql_hash/sql_len` (if query-related), stable error code/category

Update the driver epic text to explicitly mention:
- connectionName may appear in message/spans/logs
- metrics label uses platform/database policy (allowlist/hash)

#### Dependency note (MUST)

Because we do NOT introduce a shared PDO epic:
- remove any mention of dependency on `platform/database-driver-pdo`
- keep duplicated PDO infra inside each driver package, but require semantic parity via tests + SSoT refs:
  - `docs/ssot/database-pdo-options.md`
  - `docs/ssot/database-redaction.md`
  - (optional) `docs/ssot/database-config-schema.md` if adopted in 4.60.0

### Deliverables (MUST)

#### Creates

Package skeleton:
- [ ] `framework/packages/platform/database-driver-mariadb/composer.json`
- [ ] `framework/packages/platform/database-driver-mariadb/src/Module/DatabaseDriverMariadbModule.php`
- [ ] `framework/packages/platform/database-driver-mariadb/src/Provider/DatabaseDriverMariadbServiceProvider.php`
- [ ] `framework/packages/platform/database-driver-mariadb/src/Provider/DatabaseDriverMariadbServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/database-driver-mariadb/config/database_driver_mariadb.php` — MUST return `[]`
- [ ] `framework/packages/platform/database-driver-mariadb/config/rules.php`
- [ ] `framework/packages/platform/database-driver-mariadb/README.md`
- [ ] `framework/packages/platform/database-driver-mariadb/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

Shared PDO infra (duplicated per driver by design):
- [ ] `framework/packages/platform/database-driver-mariadb/src/Driver/Pdo/PdoConnection.php`
- [ ] `framework/packages/platform/database-driver-mariadb/src/Driver/Pdo/PdoConnector.php`
- [ ] `framework/packages/platform/database-driver-mariadb/src/Driver/Pdo/PdoOptionsCanonicalizer.php` — validates + normalizes EFFECTIVE canonical options map (string keys)
  - MUST NOT merge global base options (ownership is platform/database)
  - input source is `$config['pdo_options']` (already merged upstream)
  - MUST hard-fail on unknown keys and any float-like values

MariaDB implementation:
- [ ] `framework/packages/platform/database-driver-mariadb/src/Driver/Mariadb/PdoMariadbDriver.php`
- [ ] `framework/packages/platform/database-driver-mariadb/src/Driver/Mariadb/MariadbDialect.php`
- [ ] `framework/packages/platform/database-driver-mariadb/src/Driver/Mariadb/MariadbDsnBuilder.php`
- [ ] `framework/packages/platform/database-driver-mariadb/src/Driver/Mariadb/MariadbPdoOptionsMapper.php`
- [ ] `framework/packages/platform/database-driver-mariadb/src/Driver/Mariadb/MariadbValueNormalizer.php`
- [ ] `framework/packages/platform/database-driver-mariadb/src/Driver/Mariadb/MariadbExceptionHygiene.php`

Tests:
- [ ] `framework/packages/platform/database-driver-mariadb/tests/Integration/Wiring/MariadbDriverServiceWiringTest.php`
- [ ] `framework/packages/platform/database-driver-mariadb/tests/Contract/NoDsnOrSecretLeakInDriverExceptionsContractTest.php`

#### Modifies

- [ ] `docs/guides/database.md` — add/adjust MariaDB module enable example (`platform.database-driver-mariadb`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/database-driver-mariadb/composer.json`
- [ ] `framework/packages/platform/database-driver-mariadb/src/Module/DatabaseDriverMariadbModule.php`
- [ ] `framework/packages/platform/database-driver-mariadb/src/Provider/DatabaseDriverMariadbServiceProvider.php`
- [ ] `framework/packages/platform/database-driver-mariadb/config/database_driver_mariadb.php`
- [ ] `framework/packages/platform/database-driver-mariadb/config/rules.php`
- [ ] `framework/packages/platform/database-driver-mariadb/README.md`
- [ ] `framework/packages/platform/database-driver-mariadb/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/database-driver-mariadb/config/database_driver_mariadb.php`
- [ ] Keys (dot):
  - [ ] N/A (package does not own keys; MUST return `[]`)
- [ ] Rules:
  - [ ] `framework/packages/platform/database-driver-mariadb/config/rules.php` enforces: MUST return `[]`

Driver MUST NOT read global config directly.
It MUST rely exclusively on:
- `$config` (connection config; secrets allowed)
- `$tuning` (NO secrets; already merged by platform/database)

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\DatabaseDriverMariadb\Driver\Mariadb\PdoMariadbDriver`
  - [ ] adds tag: `database.driver` priority `0` meta `{name:'mariadb'}`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Delegates observability to `platform/database`
- [ ] MUST NOT emit raw SQL/DSN/secrets

#### Errors

- [ ] Deterministic exception hygiene (no secrets / no SQL / no DSN)

#### Security / Redaction

- [ ] MUST NOT leak: DSN credentials / env secrets / raw SQL
- [ ] Allowed: driver id, connection name, hash/len summaries

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If redaction exists → `framework/packages/platform/database-driver-mariadb/tests/Contract/NoDsnOrSecretLeakInDriverExceptionsContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A (wiring-only; no real server required)

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `framework/packages/platform/database-driver-mariadb/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/database-driver-mariadb/tests/Contract/NoDsnOrSecretLeakInDriverExceptionsContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/database-driver-mariadb/tests/Integration/Wiring/MariadbDriverServiceWiringTest.php`
- Gates/Arch:
  - [ ] deptrac: forbidden deps enforced (no platform/http, no platform/migrations, no other driver packages)

### DoD (MUST)

- [ ] Driver module wired via tag `database.driver` meta `{name:'mariadb'}`
- [ ] Lazy-connect enforced
- [ ] No DSN/SQL/secrets leakage proven by contract test
- [ ] Tests green + README/docs updated
- [ ] No new config roots/keys introduced (config subtree is `[]`)

---

### 4.73.0 Platform database-driver-pgsql (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.73.0"
owner_path: "framework/packages/platform/database-driver-pgsql/"

package_id: "platform/database-driver-pgsql"
composer: "coretsia/platform-database-driver-pgsql"
kind: runtime
module_id: "platform.database-driver-pgsql"

goal: "Надати runtime PostgreSQL (pgsql) драйвер для `platform/database` через tag `database.driver` із lazy-connect та deterministic DSN/options без leakage."
provides:
- "PostgreSQL driver implementation (PDO pgsql) for `DatabaseDriverInterface`"
- "Wiring into `platform/database` via tag `database.driver` meta `{name:'pgsql'}`"
- "Deterministic DSN builder + canonical PDO options mapping (string keys only)"
- "PgSQL dialect (`SqlDialectInterface`) (returning, boolean literals, limit/offset нюанси)"
- "Value normalization policy: no-floats"
- "Deterministic exception hygiene (no raw SQL/DSN/secrets)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/database-config-schema.md"
- "docs/ssot/database-pdo-options.md"
- "docs/ssot/database-redaction.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.150.0 — `core/contracts` Database ports exist
  - 4.60.0 — `platform/database` core exists and OWNS `database.driver` + `database` root schema

- Canonical schema prerequisites (MUST):
  - `docs/ssot/database-config-schema.md` exists and locks:
    - per-driver allowlist for `connections.*.config` (this driver)
    - per-driver allowlist for `connections.*.tuning` (NO secrets)
    - `database.pdo.options` allowlist (string keys only)
  - `docs/ssot/database-pdo-options.md` exists and locks canonical keys + merge policy
  - `docs/ssot/database-redaction.md` exists and locks no-SQL/no-DSN output policy

- Required tags:
  - `database.driver` — integration point (owner: platform/database)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `platform/database`

Forbidden:

- `platform/http`
- `platform/migrations`
- other `platform/database-driver-*` packages
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Database\DatabaseDriverInterface`
  - `Coretsia\Contracts\Database\ConnectionInterface`
  - `Coretsia\Contracts\Database\QueryResultInterface`
  - `Coretsia\Contracts\Database\SqlDialectInterface`

- MUST ensure its `ConnectionInterface` implementation returns:
  - `name()` = `$connectionName` passed into `connect(...)`
  - `driverId()` = driver's logical id (`sqlite|mysql|mariadb|pgsql|sqlserver`)

### Entry points / integration points (MUST)

- Kernel hooks/tags:
  - tag: `database.driver` (owner: platform/database)
  - meta allowlist (MUST): `{name:'pgsql'}`
  - MUST reference the owner tag constant from `platform/database`.

#### PDO options canonical map (MUST)

- `platform/database` owns the canonical merge:
  - effective map is passed to the driver via `$config['pdo_options']`
  - keys are canonical string keys only (no PDO constants)
- Driver MUST:
  - treat `$config['pdo_options']` as the effective canonical map (already merged upstream)
  - map canonical keys → vendor attrs internally (PDO::ATTR_* is internal to this package)
  - be deterministic (stable mapping + stable normalization)
  - MUST NOT read global config directly

#### Exception hygiene (MUST)

For any thrown exception from driver/connect/execute:
- MUST NOT include: raw SQL, DSN, username, password, token, file paths (if any)
- MAY include: `driverId`, `connectionName`, `sql_hash/sql_len` (if query-related), stable error code/category

Update the driver epic text to explicitly mention:
- connectionName may appear in message/spans/logs
- metrics label uses platform/database policy (allowlist/hash)

#### Dependency note (MUST)

Because we do NOT introduce a shared PDO epic:
- remove any mention of dependency on `platform/database-driver-pdo`
- keep duplicated PDO infra inside each driver package, but require semantic parity via tests + SSoT refs:
  - `docs/ssot/database-pdo-options.md`
  - `docs/ssot/database-redaction.md`
  - (optional) `docs/ssot/database-config-schema.md` if adopted in 4.60.0

### Deliverables (MUST)

#### Creates

Package skeleton:
- [ ] `framework/packages/platform/database-driver-pgsql/composer.json`
- [ ] `framework/packages/platform/database-driver-pgsql/src/Module/DatabaseDriverPgsqlModule.php`
- [ ] `framework/packages/platform/database-driver-pgsql/src/Provider/DatabaseDriverPgsqlServiceProvider.php`
- [ ] `framework/packages/platform/database-driver-pgsql/src/Provider/DatabaseDriverPgsqlServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/database-driver-pgsql/config/database_driver_pgsql.php` — MUST return `[]`
- [ ] `framework/packages/platform/database-driver-pgsql/config/rules.php`
- [ ] `framework/packages/platform/database-driver-pgsql/README.md`
- [ ] `framework/packages/platform/database-driver-pgsql/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

Shared PDO infra (duplicated per driver by design):
- [ ] `framework/packages/platform/database-driver-pgsql/src/Driver/Pdo/PdoConnection.php`
- [ ] `framework/packages/platform/database-driver-pgsql/src/Driver/Pdo/PdoConnector.php`
- [ ] `framework/packages/platform/database-driver-pgsql/src/Driver/Pdo/PdoOptionsCanonicalizer.php` — validates + normalizes EFFECTIVE canonical options map (string keys)
  - MUST NOT merge global base options (ownership is platform/database)
  - input source is `$config['pdo_options']` (already merged upstream)
  - MUST hard-fail on unknown keys and any float-like values

PgSQL implementation:
- [ ] `framework/packages/platform/database-driver-pgsql/src/Driver/Pgsql/PdoPgsqlDriver.php`
- [ ] `framework/packages/platform/database-driver-pgsql/src/Driver/Pgsql/PgsqlDialect.php`
- [ ] `framework/packages/platform/database-driver-pgsql/src/Driver/Pgsql/PgsqlDsnBuilder.php`
- [ ] `framework/packages/platform/database-driver-pgsql/src/Driver/Pgsql/PgsqlPdoOptionsMapper.php`
- [ ] `framework/packages/platform/database-driver-pgsql/src/Driver/Pgsql/PgsqlValueNormalizer.php`
- [ ] `framework/packages/platform/database-driver-pgsql/src/Driver/Pgsql/PgsqlExceptionHygiene.php`

Tests:
- [ ] `framework/packages/platform/database-driver-pgsql/tests/Integration/Wiring/PgsqlDriverServiceWiringTest.php`
- [ ] `framework/packages/platform/database-driver-pgsql/tests/Contract/NoDsnOrSecretLeakInDriverExceptionsContractTest.php`

#### Modifies

- [ ] `docs/guides/database.md` — add/adjust PgSQL module enable example (`platform.database-driver-pgsql`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/database-driver-pgsql/composer.json`
- [ ] `framework/packages/platform/database-driver-pgsql/src/Module/DatabaseDriverPgsqlModule.php`
- [ ] `framework/packages/platform/database-driver-pgsql/src/Provider/DatabaseDriverPgsqlServiceProvider.php`
- [ ] `framework/packages/platform/database-driver-pgsql/config/database_driver_pgsql.php`
- [ ] `framework/packages/platform/database-driver-pgsql/config/rules.php`
- [ ] `framework/packages/platform/database-driver-pgsql/README.md`
- [ ] `framework/packages/platform/database-driver-pgsql/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/database-driver-pgsql/config/database_driver_pgsql.php`
- [ ] Keys (dot):
  - [ ] N/A (package does not own keys; MUST return `[]`)
- [ ] Rules:
  - [ ] `framework/packages/platform/database-driver-pgsql/config/rules.php` enforces: MUST return `[]`

Driver MUST NOT read global config directly.
It MUST rely exclusively on:
- `$config` (connection config; secrets allowed)
- `$tuning` (NO secrets; already merged by platform/database)

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\DatabaseDriverPgsql\Driver\Pgsql\PdoPgsqlDriver`
  - [ ] adds tag: `database.driver` priority `0` meta `{name:'pgsql'}`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Delegates observability to `platform/database`
- [ ] MUST NOT emit raw SQL/DSN/secrets

#### Errors

- [ ] Deterministic exception hygiene (no secrets / no SQL / no DSN)

#### Security / Redaction

- [ ] MUST NOT leak: DSN credentials / env secrets / raw SQL
- [ ] Allowed: driver id, connection name, hash/len summaries

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If redaction exists → `framework/packages/platform/database-driver-pgsql/tests/Contract/NoDsnOrSecretLeakInDriverExceptionsContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A (wiring-only; no real server required)

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `framework/packages/platform/database-driver-pgsql/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/database-driver-pgsql/tests/Contract/NoDsnOrSecretLeakInDriverExceptionsContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/database-driver-pgsql/tests/Integration/Wiring/PgsqlDriverServiceWiringTest.php`
- Gates/Arch:
  - [ ] deptrac: forbidden deps enforced (no platform/http, no platform/migrations, no other driver packages)

### DoD (MUST)

- [ ] Driver module wired via tag `database.driver` meta `{name:'pgsql'}`
- [ ] Lazy-connect enforced
- [ ] No DSN/SQL/secrets leakage proven by contract test
- [ ] Tests green + README/docs updated
- [ ] No new config roots/keys introduced (config subtree is `[]`)

---

### 4.74.0 Platform database-driver-sqlserver (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.74.0"
owner_path: "framework/packages/platform/database-driver-sqlserver/"

package_id: "platform/database-driver-sqlserver"
composer: "coretsia/platform-database-driver-sqlserver"
kind: runtime
module_id: "platform.database-driver-sqlserver"

goal: "Надати runtime SQL Server драйвер для `platform/database` через tag `database.driver` із lazy-connect та deterministic DSN/options без leakage."
provides:
- "SQL Server driver implementation (PDO sqlsrv) for `DatabaseDriverInterface`"
- "Wiring into `platform/database` via tag `database.driver` meta `{name:'sqlserver'}`"
- "Deterministic DSN builder + canonical PDO options mapping (string keys only)"
- "SQL Server dialect (`SqlDialectInterface`) (TOP/offset-fetch, identity, returning/insert-id нюанси)"
- "Value normalization policy: no-floats"
- "Deterministic exception hygiene (no raw SQL/DSN/secrets)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/database-config-schema.md"
- "docs/ssot/database-pdo-options.md"
- "docs/ssot/database-redaction.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.150.0 — `core/contracts` Database ports exist
  - 4.60.0 — `platform/database` core exists and OWNS `database.driver` + `database` root schema

- Canonical schema prerequisites (MUST):
  - `docs/ssot/database-config-schema.md` exists and locks:
    - per-driver allowlist for `connections.*.config` (this driver)
    - per-driver allowlist for `connections.*.tuning` (NO secrets)
    - `database.pdo.options` allowlist (string keys only)
  - `docs/ssot/database-pdo-options.md` exists and locks canonical keys + merge policy
  - `docs/ssot/database-redaction.md` exists and locks no-SQL/no-DSN output policy

- Required tags:
  - `database.driver` — integration point (owner: platform/database)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `platform/database`

Forbidden:

- `platform/http`
- `platform/migrations`
- other `platform/database-driver-*` packages
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Database\DatabaseDriverInterface`
  - `Coretsia\Contracts\Database\ConnectionInterface`
  - `Coretsia\Contracts\Database\QueryResultInterface`
  - `Coretsia\Contracts\Database\SqlDialectInterface`

- MUST ensure its `ConnectionInterface` implementation returns:
  - `name()` = `$connectionName` passed into `connect(...)`
  - `driverId()` = driver's logical id (`sqlite|mysql|mariadb|pgsql|sqlserver`)

### Entry points / integration points (MUST)

- Kernel hooks/tags:
  - tag: `database.driver` (owner: platform/database)
  - meta allowlist (MUST): `{name:'sqlserver'}`
  - MUST reference the owner tag constant from `platform/database`.

#### PDO options canonical map (MUST)

- `platform/database` owns the canonical merge:
  - effective map is passed to the driver via `$config['pdo_options']`
  - keys are canonical string keys only (no PDO constants)
- Driver MUST:
  - treat `$config['pdo_options']` as the effective canonical map (already merged upstream)
  - map canonical keys → vendor attrs internally (PDO::ATTR_* is internal to this package)
  - be deterministic (stable mapping + stable normalization)
  - MUST NOT read global config directly

#### Exception hygiene (MUST)

For any thrown exception from driver/connect/execute:
- MUST NOT include: raw SQL, DSN, username, password, token, file paths (if any)
- MAY include: `driverId`, `connectionName`, `sql_hash/sql_len` (if query-related), stable error code/category

Update the driver epic text to explicitly mention:
- connectionName may appear in message/spans/logs
- metrics label uses platform/database policy (allowlist/hash)

#### Dependency note (MUST)

Because we do NOT introduce a shared PDO epic:
- remove any mention of dependency on `platform/database-driver-pdo`
- keep duplicated PDO infra inside each driver package, but require semantic parity via tests + SSoT refs:
  - `docs/ssot/database-pdo-options.md`
  - `docs/ssot/database-redaction.md`
  - (optional) `docs/ssot/database-config-schema.md` if adopted in 4.60.0

### Deliverables (MUST)

#### Creates

Package skeleton:
- [ ] `framework/packages/platform/database-driver-sqlserver/composer.json`
- [ ] `framework/packages/platform/database-driver-sqlserver/src/Module/DatabaseDriverSqlserverModule.php`
- [ ] `framework/packages/platform/database-driver-sqlserver/src/Provider/DatabaseDriverSqlserverServiceProvider.php`
- [ ] `framework/packages/platform/database-driver-sqlserver/src/Provider/DatabaseDriverSqlserverServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/database-driver-sqlserver/config/database_driver_sqlserver.php` — MUST return `[]`
- [ ] `framework/packages/platform/database-driver-sqlserver/config/rules.php`
- [ ] `framework/packages/platform/database-driver-sqlserver/README.md`
- [ ] `framework/packages/platform/database-driver-sqlserver/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

Shared PDO infra (duplicated per driver by design):
- [ ] `framework/packages/platform/database-driver-sqlserver/src/Driver/Pdo/PdoConnection.php`
- [ ] `framework/packages/platform/database-driver-sqlserver/src/Driver/Pdo/PdoConnector.php`
- [ ] `framework/packages/platform/database-driver-sqlserver/src/Driver/Pdo/PdoOptionsCanonicalizer.php` — validates + normalizes EFFECTIVE canonical options map (string keys)
  - MUST NOT merge global base options (ownership is platform/database)
  - input source is `$config['pdo_options']` (already merged upstream)
  - MUST hard-fail on unknown keys and any float-like values

SQL Server implementation:
- [ ] `framework/packages/platform/database-driver-sqlserver/src/Driver/Sqlserver/PdoSqlserverDriver.php`
- [ ] `framework/packages/platform/database-driver-sqlserver/src/Driver/Sqlserver/SqlserverDialect.php`
- [ ] `framework/packages/platform/database-driver-sqlserver/src/Driver/Sqlserver/SqlserverDsnBuilder.php`
- [ ] `framework/packages/platform/database-driver-sqlserver/src/Driver/Sqlserver/SqlserverPdoOptionsMapper.php`
- [ ] `framework/packages/platform/database-driver-sqlserver/src/Driver/Sqlserver/SqlserverValueNormalizer.php`
- [ ] `framework/packages/platform/database-driver-sqlserver/src/Driver/Sqlserver/SqlserverExceptionHygiene.php`

Tests:
- [ ] `framework/packages/platform/database-driver-sqlserver/tests/Integration/Wiring/SqlserverDriverServiceWiringTest.php`
- [ ] `framework/packages/platform/database-driver-sqlserver/tests/Contract/NoDsnOrSecretLeakInDriverExceptionsContractTest.php`

#### Modifies

- [ ] `docs/guides/database.md` — add/adjust SQL Server module enable example (`platform.database-driver-sqlserver`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/database-driver-sqlserver/composer.json`
- [ ] `framework/packages/platform/database-driver-sqlserver/src/Module/DatabaseDriverSqlserverModule.php`
- [ ] `framework/packages/platform/database-driver-sqlserver/src/Provider/DatabaseDriverSqlserverServiceProvider.php`
- [ ] `framework/packages/platform/database-driver-sqlserver/config/database_driver_sqlserver.php`
- [ ] `framework/packages/platform/database-driver-sqlserver/config/rules.php`
- [ ] `framework/packages/platform/database-driver-sqlserver/README.md`
- [ ] `framework/packages/platform/database-driver-sqlserver/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/database-driver-sqlserver/config/database_driver_sqlserver.php`
- [ ] Keys (dot):
  - [ ] N/A (package does not own keys; MUST return `[]`)
- [ ] Rules:
  - [ ] `framework/packages/platform/database-driver-sqlserver/config/rules.php` enforces: MUST return `[]`

Driver MUST NOT read global config directly.
It MUST rely exclusively on:
- `$config` (connection config; secrets allowed)
- `$tuning` (NO secrets; already merged by platform/database)

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\DatabaseDriverSqlserver\Driver\Sqlserver\PdoSqlserverDriver`
  - [ ] adds tag: `database.driver` priority `0` meta `{name:'sqlserver'}`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Delegates observability to `platform/database`
- [ ] MUST NOT emit raw SQL/DSN/secrets

#### Errors

- [ ] Deterministic exception hygiene (no secrets / no SQL / no DSN)

#### Security / Redaction

- [ ] MUST NOT leak: DSN credentials / env secrets / raw SQL
- [ ] Allowed: driver id, connection name, hash/len summaries

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If redaction exists → `framework/packages/platform/database-driver-sqlserver/tests/Contract/NoDsnOrSecretLeakInDriverExceptionsContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A (wiring-only; no real SQL Server required)

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `framework/packages/platform/database-driver-sqlserver/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/database-driver-sqlserver/tests/Contract/NoDsnOrSecretLeakInDriverExceptionsContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/database-driver-sqlserver/tests/Integration/Wiring/SqlserverDriverServiceWiringTest.php`
- Gates/Arch:
  - [ ] deptrac: forbidden deps enforced (no platform/http, no platform/migrations, no other driver packages)

### DoD (MUST)

- [ ] Driver module wired via tag `database.driver` meta `{name:'sqlserver'}`
- [ ] Lazy-connect enforced
- [ ] No DSN/SQL/secrets leakage proven by contract test
- [ ] Tests green + README/docs updated
- [ ] No new config roots/keys introduced (config subtree is `[]`)

---

### 4.80.0 Database drivers (MySQL/MariaDB/PostgreSQL/SQL Server) (SHOULD) [DOC]

---
type: docs
phase: 4
epic_id: "4.80.0"
owner_path: "docs/architecture/database-drivers.md"

goal: "Зафіксувати ownership та parity-invariants для DB driver packages (`platform/database-driver-*`) без змін public API `platform/database`."
provides:
- "drivers are provided as separate platform runtime packages: platform/database-driver-sqlite, platform/database-driver-mysql, platform/database-driver-mariadb, platform/database-driver-pgsql, platform/database-driver-sqlserver"
- "Parity semantics: same core operations semantics + deterministic error codes"
- "No raw SQL leakage policy для logs/metrics/traces"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/database-driver-parity.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required contracts / ports:
  - `Coretsia\Contracts\Database\DatabaseDriverInterface`
  - `Coretsia\Contracts\Database\ConnectionInterface`
- Required tags:
  - `database.driver` — canonical discovery/wiring mechanism for driver modules (documented)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- none

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Database\DatabaseDriverInterface`
  - `Coretsia\Contracts\Database\ConnectionInterface`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `docs/architecture/database-drivers.md` — drivers matrix (sqlite|mysql|mariadb|pgsql|sqlserver), ownership, env/CI notes
  - Ensure `docs/architecture/database-drivers.md` references:
    - `docs/ssot/database-config-schema.md` (single source for allowlists)
    - `docs/ssot/database-driver-parity.md`
    - `docs/ssot/database-redaction.md`
  - include a matrix:
    - `driverId` (logical)
    - PDO driver name (internal)
    - composer package id
    - module_id
    - CI coverage tier (sqlite real DB; others wiring-only unless you opt-in services)
- [ ] `docs/ssot/database-driver-parity.md` — parity semantics + determinism requirements
- [ ] `docs/ops/ci-database-services.md` — CI strategy (local docker vs CI service)

- [ ] doc-only placeholders:
  - Document planned package_ids + composer names in `docs/architecture/database-drivers.md` without creating directories.

#### Modifies

- [ ] (optional reference) `platform/database` README — посилання на ownership + parity rules (якщо існує; документ має це вимагати)
- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/database-driver-parity.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (doc-only epic)

### Tests (MUST)

N/A (doc-only epic)

### DoD (MUST)

- [ ] Ownership + parity rules documented and referenced by `platform/database` documentation
- [ ] “No raw SQL” policy clearly reiterated for drivers
- [ ] Test plan exists (local docker vs CI service)

---

### 4.90.0 Migrations (driver-agnostic) (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.90.0"
owner_path: "framework/packages/platform/migrations/"

package_id: "platform/migrations"
composer: "coretsia/platform-migrations"
kind: runtime
module_id: "platform.migrations"

goal: "Надати driver-agnostic migrations на DB ports із детермінованим ordering та CLI командами, discovered через `cli.command` tag без compile-time залежності на `platform/cli`."
provides:
- "Migrator + repository + loader (deterministic ordering)"
- "CLI: `db:migrate`, `db:rollback`, `db:status` (via `cli.command` tag)"
- "Observability без SQL leakage; deterministic error codes"

tags_introduced: []
config_roots_introduced:
- "migrations"

artifacts_introduced: []
adr: "docs/adr/ADR-0052-migrations-driver-agnostic.md"
ssot_refs:
- "docs/ssot/migrations-ordering.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `platform/database` забезпечує `ConnectionInterface` та choke point observability/mapping (QueryExecutor або еквівалент).
  - `platform/cli` існує як runtime bundle, що **discover** commands через tag `cli.command` (але ця залежність НЕ compile-time).

- CLI tag usage rule (cemented):
  - This package MUST NOT depend on `platform/cli` at compile-time.
  - Therefore, it MUST use the literal tag name `'cli.command'` (string) when tagging services,
    and MUST NOT reference `platform/cli` tag constants in code.
  - Tag ownership remains `platform/cli` (SSoT: `docs/ssot/tags.md`).

- Required config roots/keys:
  - `migrations.*` — enabled/table/paths/transactional/strict.

- Required tags:
  - `cli.command` — tag для reverse-dep discovery CLI команд.

- Required contracts / ports:
  - `Coretsia\Contracts\Migrations\MigrationInterface`
  - `Coretsia\Contracts\Database\ConnectionInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Database\SqlDialectInterface` via `ConnectionInterface::dialect()` from Contracts PATCH 1.150.0

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `platform/database`

Forbidden:

- `platform/cli` (commands discovered via tag)
- `integrations/*`
- `platform/http`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
  - `Psr\Clock\ClockInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Migrations\MigrationInterface`
  - `Coretsia\Contracts\Database\ConnectionInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - `db:migrate` → `framework/packages/platform/migrations/src/Console/MigrateCommand.php`
  - `db:rollback` → `framework/packages/platform/migrations/src/Console/RollbackCommand.php`
  - `db:status` → `framework/packages/platform/migrations/src/Console/StatusCommand.php`
- HTTP:
  - N/A
- Kernel hooks/tags:
  - `cli.command` priority `0` meta `{name:'db:migrate'|'db:rollback'|'db:status'}`
- Artifacts:
  - N/A (migrations are runtime DB state)

- Migrator MUST resolve connection via `platform/database`:
  - `connectionName = migrations.connection ?? database.default`
  - `ConnectionManager->connection(connectionName)`
  - All repository and migration execution uses that connection

- Commands MAY print selected connection name (safe), but MUST NOT print:
  - raw SQL
  - DSN/credentials
  - migration file paths (use stable ids or hashes)

### Deliverables (MUST)

#### Creates

Package skeleton:
- [ ] `framework/packages/platform/migrations/src/Module/MigrationsModule.php`
- [ ] `framework/packages/platform/migrations/src/Provider/MigrationsServiceProvider.php`
- [ ] `framework/packages/platform/migrations/src/Provider/MigrationsServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/migrations/config/migrations.php` — returns subtree for root `migrations` (no repeated root)
- [ ] `framework/packages/platform/migrations/config/rules.php`
- [ ] `framework/packages/platform/migrations/README.md`
- [ ] `framework/packages/platform/migrations/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

Core implementation:
- [ ] `framework/packages/platform/migrations/src/Migrator.php` — migrate/rollback orchestration
- [ ] `framework/packages/platform/migrations/src/Repository/MigrationRepository.php` — stores applied migrations
- [ ] `framework/packages/platform/migrations/src/Migration/MigrationLoader.php` — deterministic file/class order
  - Deterministic filesystem policy for `MigrationLoader` (cemented):
    - Inputs: `migrations.paths` is a list (order preserved, no implicit sorting at config-merge time).
    - For each path:
      - list files deterministically (normalize relpaths to forward slashes; sort by `strcmp`).
      - symlink hard-fail (MUST NOT follow; first symlink => deterministic failure code).
    - Migration ID derivation (single-choice):
      - ID = normalized relative path without extension (or explicit class constant), and MUST be stable across OS.

Schema (MVP):
- [ ] `framework/packages/platform/migrations/src/Schema/Blueprint.php` — create table/columns/indexes (MVP)
- [ ] `framework/packages/platform/migrations/src/Schema/SchemaBuilder.php` — optional helper

CLI:
- [ ] `framework/packages/platform/migrations/src/Console/MigrateCommand.php`
- [ ] `framework/packages/platform/migrations/src/Console/RollbackCommand.php`
- [ ] `framework/packages/platform/migrations/src/Console/StatusCommand.php`

Observability:
- [ ] `framework/packages/platform/migrations/src/Observability/MigrationsInstrumentation.php` — spans/metrics/logging helpers

Errors:
- [ ] `framework/packages/platform/migrations/src/Exception/MigrationsException.php`
- [ ] `framework/packages/platform/migrations/src/Exception/MigrationFailedException.php` — `CORETSIA_DB_MIGRATION_FAILED`
- [ ] `framework/packages/platform/migrations/src/Exception/MigrationInvalidException.php` — `CORETSIA_DB_MIGRATION_INVALID`
- [ ] `framework/packages/platform/migrations/src/Exception/MigrationRepositoryException.php` — `CORETSIA_DB_MIGRATION_REPOSITORY_ERROR`

Docs:
- [ ] `docs/ssot/migrations-ordering.md` — deterministic ordering + redaction rules

Tests:
- [ ] `framework/packages/platform/migrations/tests/Unit/MigrationLoaderDeterministicOrderTest.php`
- [ ] `framework/packages/platform/migrations/tests/Unit/BlueprintGeneratesDeterministicSqlForCreateTableTest.php`
- [ ] `framework/packages/platform/migrations/tests/Contract/NoRawSqlLoggedContractTest.php`
- [ ] `framework/packages/platform/migrations/tests/Integration/MigrateAndRollbackOnSqliteTest.php`
- [ ] `framework/packages/platform/migrations/tests/Integration/MigrationsEmitMetricsNoopSafeTest.php`
- [ ] `framework/packages/platform/migrations/tests/Integration/CliCommandsProduceStableJsonSchemaWhenFormatJsonTest.php`
- [ ] `framework/packages/platform/migrations/tests/Integration/Cli/MigrationsWorkInExpressFixtureOnSqliteTest.php`
- [ ] `framework/packages/platform/migrations/tests/Fixtures/ExpressSqliteApp/config/modules.php`
- [ ] `framework/packages/platform/migrations/tests/Integration/MigrationsUseConfiguredConnectionNameTest.php`
  - asserts:
    - `migrations.connection` overrides `database.default`
    - resolution fails deterministically if connection not configured (proper exception code)

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/migrations-ordering.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0052-migrations-driver-agnostic.md`
- [ ] `docs/guides/migrations.md` — how to add migrations paths via `@append`, how to run commands
- [ ] `docs/ssot/config-roots.md` — register `migrations` root (owner `platform/migrations`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/migrations/composer.json`
- [ ] `framework/packages/platform/migrations/src/Module/MigrationsModule.php`
- [ ] `framework/packages/platform/migrations/src/Provider/MigrationsServiceProvider.php`
- [ ] `framework/packages/platform/migrations/config/migrations.php`
- [ ] `framework/packages/platform/migrations/config/rules.php`
- [ ] `framework/packages/platform/migrations/README.md`
- [ ] `framework/packages/platform/migrations/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/migrations/config/migrations.php`
- [ ] Keys (dot):
  - [ ] `migrations.enabled` = true
  - [ ] `migrations.connection` = null           # meaning: if null → use `database.default` else: use that explicit connection name
  - [ ] `migrations.table` = 'migrations'
  - [ ] `migrations.paths` = [] (list-like; deterministic order)
  - [ ] `migrations.transactional` = true
  - [ ] `migrations.strict` = true
- [ ] Rules:
  - [ ] `framework/packages/platform/migrations/config/rules.php` enforces shape
    - `migrations.connection` is `null|string` (string non-empty)

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - N/A (tag `cli.command` owned by CLI; this package contributes)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Migrations\Migrator`
  - [ ] registers: `Coretsia\Migrations\Repository\MigrationRepository`
  - [ ] registers: `Coretsia\Migrations\Console\MigrateCommand`
  - [ ] adds tag: `cli.command` priority `0` meta `{name:'db:migrate'}`
  - [ ] registers: `Coretsia\Migrations\Console\RollbackCommand`
  - [ ] adds tag: `cli.command` priority `0` meta `{name:'db:rollback'}`
  - [ ] registers: `Coretsia\Migrations\Console\StatusCommand`
  - [ ] adds tag: `cli.command` priority `0` meta `{name:'db:status'}`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (for logs)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] no stateful services expected

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `db.migrate` (attrs: `operation=apply|rollback`, `outcome`)
- [ ] Metrics:
  - [ ] `db.migrations_applied_total` (labels: `driver`, `outcome`)
  - [ ] `db.migrations_rolled_back_total` (labels: `driver`, `outcome`)
  - [ ] `db.migrations_duration_ms` (labels: `driver`, `operation`, `outcome`)
- [ ] Logs:
  - [ ] summary only (counts + migration ids), **без SQL**

- High-cardinality guard (cemented):
  - Migration identifiers MUST NOT be used as metric labels.
  - If emitted as span attributes or logs, they MUST be hashed (e.g. `migration_id_hash=sha256(id)`),
    never raw file paths or class names.

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Migrations\Exception\MigrationFailedException` — errorCode `CORETSIA_DB_MIGRATION_FAILED`
  - [ ] `Coretsia\Migrations\Exception\MigrationInvalidException` — errorCode `CORETSIA_DB_MIGRATION_INVALID`
  - [ ] `Coretsia\Migrations\Exception\MigrationRepositoryException` — errorCode `CORETSIA_DB_MIGRATION_REPOSITORY_ERROR`
- [ ] Mapping:
  - [ ] reuse existing mapper (DefaultExceptionMapper) (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw SQL, DSN credentials, env secrets
- [ ] Allowed:
  - [ ] migration ids, counts, `hash/len` if needed

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `framework/packages/platform/migrations/tests/Contract/NoRawSqlLoggedContractTest.php`
  (asserts: no SQL leakage; observability naming/labels follow policy)
- [ ] If redaction exists → `framework/packages/platform/migrations/tests/Contract/NoRawSqlLoggedContractTest.php`
  (asserts: SQL is not emitted raw)
- [ ] Context reads exist → covered by integration/contract tests that validate logs include only safe correlation id usage (no context writes exist)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/platform/migrations/tests/Fixtures/ExpressSqliteApp/config/modules.php`
    - Add enablement of `platform.database-driver-sqlite`
    - Ensure connection driver is `sqlite` under `database.connections.sqlite.driver`
- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` captured by integration tests (implied by tests list)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/migrations/tests/Unit/MigrationLoaderDeterministicOrderTest.php`
  - [ ] `framework/packages/platform/migrations/tests/Unit/BlueprintGeneratesDeterministicSqlForCreateTableTest.php`
- Contract:
  - [ ] `framework/packages/platform/migrations/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/migrations/tests/Contract/NoRawSqlLoggedContractTest.php`
  - [ ] `framework/packages/core/contracts/tests/Contract/MigrationInterfaceShapeContractTest.php` (contracts scope)
- Integration:
  - [ ] `framework/packages/platform/migrations/tests/Integration/MigrateAndRollbackOnSqliteTest.php`
  - [ ] `framework/packages/platform/migrations/tests/Integration/MigrationsEmitMetricsNoopSafeTest.php`
  - [ ] `framework/packages/platform/migrations/tests/Integration/CliCommandsProduceStableJsonSchemaWhenFormatJsonTest.php`
  - [ ] `framework/packages/platform/migrations/tests/Integration/Cli/MigrationsWorkInExpressFixtureOnSqliteTest.php`
- Gates/Arch:
  - [ ] deptrac: `platform/migrations` MUST NOT depend on `platform/cli`

### DoD (MUST)

- [ ] Deterministic migration ordering (filename/id ASC)
- [ ] CLI commands discovered via `cli.command` (no compile-time dep)
- [ ] Observability policy satisfied; no SQL leakage
- [ ] Tests pass + docs updated
- [ ] Non-goals / out of scope
  - [ ] Не обіцяє full schema DSL parity (MVP Blueprint).
  - [ ] Не робить “online migrations” (Phase 6+).
- [ ] When migrations are run twice with no changes, then the second run is a no-op and output is deterministic.
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/cli` discovers CLI commands via tag `cli.command`
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
  - [ ] DB driver module enabled (`platform.database-driver-sqlite`; sqlite connection configured)
- [ ] `MigrateAndRollbackOnSqliteTest` is the only CI-required “real DB” test
- [ ] Any future pgsql/mysql/sqlserver migration coverage is opt-in (local docker), not CI-hard

---

### 4.95.0 Contracts: Queue (jobs, serialization, retry) (MUST) [CONTRACTS]

---
type: package
phase: 4
epic_id: "4.95.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Визначити стабільні queue contracts (job shape, serialization, driver API, retry/backoff, failed jobs, worker runtime surface) із детермінізмом та без HTTP/PSR-7 залежностей."
provides:
- "Queue ports: job shape + driver API (reserve/ack/fail/release)"
- "Детерміністичні правила payload (json-like; no floats; no objects) на рівні shape/serializer contract"
- "Retry/backoff policy surface + failed jobs repository port + worker runtime surface (без реалізацій)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: docs/adr/ADR-0067-queue-ports-jobs-retry.md
ssot_refs: []
---

### Goal (MUST)

- **Definition of success:** Contracts дозволяють реалізувати sync driver і DB driver, а також worker loop, без зміни портів та без HTTP залежностей.
- **Acceptance scenario:** When a job is serialized/deserialized, then the payload remains json-like and deterministic, and the driver API supports reserve/ack/fail/release.

## Dependencies (MUST)

### Preconditions (MUST)

- Epic prerequisites:
  - N/A
- Required deliverables (exact paths):
  - N/A
- Required config roots/keys:
  - N/A
- Required tags:
  - N/A
- Required contracts / ports:
  - N/A

### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- `platform/*`
- `Psr\Http\Message\*`
- `Psr\Http\Server\*`

### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - none
- Contracts:
  - none
- Foundation stable APIs:
  - none

## Entry points / integration points (MUST)

N/A

## Deliverables (exact paths only) (MUST)

### Creates

- [ ] `framework/packages/core/contracts/src/Queue/JobInterface.php` — job contract (shape/metadata; payload правила на рівні contract-очікувань)
- [ ] `framework/packages/core/contracts/src/Queue/JobSerializerInterface.php` — серіалізація/десеріалізація з інваріантами json-like + детермінізм
- [ ] `framework/packages/core/contracts/src/Queue/QueueDriverInterface.php` — low-level driver port (reserve/ack/fail/release)
- [ ] `framework/packages/core/contracts/src/Queue/QueueInterface.php` — high-level queue facade port (dispatch/enqueue поверх driver)
- [ ] `framework/packages/core/contracts/src/Queue/BackoffStrategyInterface.php` — retry/backoff policy surface (delay computation)
- [ ] `framework/packages/core/contracts/src/Queue/FailedJobRepositoryInterface.php` — port для зберігання/читання failed jobs
- [ ] `framework/packages/core/contracts/src/Queue/QueueWorkerRuntimeInterface.php` — worker runtime surface (loop/control без реалізації)
- [ ] `framework/packages/core/contracts/src/Queue/QueueException.php` — базова доменна exception для queue contracts

### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0067-queue-ports-jobs-retry.md`

### Package skeleton (if type=package)

N/A (пакет `core/contracts` вже існує; цей епік додає лише Queue contracts)

### Configuration (keys + defaults)

N/A

### Wiring / DI tags (when applicable)

N/A

### Artifacts / outputs (if applicable)

N/A

## Cross-cutting (only if applicable; otherwise `N/A`)

### Context & UoW

N/A

### Observability (policy-compliant)

N/A

### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/core/contracts/src/Queue/QueueException.php` — базова помилка домену Queue (конкретні errorCode/мапінг — на стороні імплементаційних пакетів)

### Security / Redaction (MUST)

- [ ] MUST NOT leak:
  - [ ] payload/tokens/session id у будь-якому contract shape (payload тільки json-like; логування — відповідальність імплементації)

## Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/packages/core/contracts/tests/Unit/QueueContractsShapesTest.php`
  - asserts: payload is json-like; deterministic invariants (no floats, no objects) are enforced at shape/serializer contract level
- [ ] `framework/packages/core/contracts/tests/Contract/QueueContractsTest.php`
  - asserts: ports are stable/consistent; driver surface supports reserve/ack/fail/release + retry/backoff surfaces are present
- [ ] `framework/packages/core/contracts/tests/Contract/ContractsDoNotDependOnPsr7ContractTest.php`
  - asserts: contracts remain HTTP-agnostic (no PSR-7/15 deps)

## Tests (MUST)

- Unit:
  - [ ] `framework/packages/core/contracts/tests/Unit/QueueContractsShapesTest.php`
- Contract:
  - [ ] `framework/packages/core/contracts/tests/Contract/QueueContractsTest.php`
  - [ ] `framework/packages/core/contracts/tests/Contract/ContractsDoNotDependOnPsr7ContractTest.php`
- Integration:
  - N/A
- Gates/Arch:
  - N/A (deptrac/forbidden enforced policy-wide)

## DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Ports/VO shapes stable + protected by contract tests
- [ ] ADR exists and documents invariants (json-like payload, no PSR-7)
- [ ] Docs updated:
  - [ ] ADR: `docs/adr/ADR-0067-queue-ports-jobs-retry.md`
- [ ] Contracts дозволяють реалізувати sync driver і DB driver, а також worker loop, без зміни портів та без HTTP залежностей.
- [ ] When a job is serialized/deserialized, then the payload remains json-like and deterministic, and the driver API supports reserve/ack/fail/release.
- [ ] Problem this epic solves
  - [ ] Визначити contracts для queue: job shape, serialization, driver API, retry/backoff, failed jobs, worker runtime surface
  - [ ] Забезпечити json-like payload rule + детермінізм (no floats, no objects) на рівні shape
  - [ ] Дати стабільний базис для sync driver та DB/Redis drivers без зміни contracts
- [ ] Non-goals / out of scope
  - [ ] Реалізація driver/worker/CLI (це 4.96.0–4.98.0)
  - [ ] Storage schema (DB tables) — це імплементаційні епіки
  - [ ] HTTP-aware типи/PSR-7 (заборонено у contracts)
- [ ] Cutline impact
  - [ ] blocks Phase 4 cutline

---

### 4.100.0 platform/mail (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.100.0"
owner_path: "framework/packages/platform/mail/"

package_id: "platform/mail"
composer: "coretsia/platform-mail"
kind: runtime
module_id: "platform.mail"

goal: "Надати канонічний mail layer (Mailer + Transport) з NullTransport fallback та суворою політикою redaction (без PII/секретів у signals)."
provides:
- "MailerInterface завжди резолвиться (NullTransport fallback)"
- "Transport selection через config (`mail.transport`) без compile-time залежності на integrations"
- "Optional async send через contracts `QueueInterface` без compile-time залежності на `platform/queue`"

tags_introduced: []
config_roots_introduced:
- "mail"

artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/config-roots.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `core/contracts` містить mail contracts (`MailerInterface`, `MailTransportInterface`, `MailMessage`) + secrets/queue/observability ports (як вказано нижче).
  - `core/foundation` доступний для стабільних API (DI, ContextAccessorInterface — якщо використовується).
  - (optional runtime) реалізації observability/secrets/queue можуть бути NOOP-safe і підʼєднуються пресетами/бандлами.

- Required deliverables (exact paths):
  - `docs/ssot/config-roots.md` — існує та дозволяє реєстрацію нового root `mail`.

- Required config roots/keys:
  - `mail.*` — платформа mail (включно з transport selection та async флагами).

- Required contracts / ports:
  - `Coretsia\Contracts\Mail\MailerInterface` — API mailer
  - `Coretsia\Contracts\Mail\MailTransportInterface` — API transport
  - `Coretsia\Contracts\Mail\MailMessage` — immutable message shape
  - `Coretsia\Contracts\Queue\QueueInterface` — optional async send
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` — optional (лише якщо mail layer сам резолвить refs; інакше N/A)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — spans
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics
  - `Psr\Log\LoggerInterface` — optional logs

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `platform/cli`
- `integrations/*`  # transport integrations підʼєднуються без compile-time deps

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Mail\MailerInterface`
  - `Coretsia\Contracts\Mail\MailTransportInterface`
  - `Coretsia\Contracts\Mail\MailMessage`
  - `Coretsia\Contracts\Queue\QueueInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/mail/src/Module/MailModule.php`
- [ ] `framework/packages/platform/mail/src/Provider/MailServiceProvider.php`
- [ ] `framework/packages/platform/mail/src/Provider/MailServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/mail/config/mail.php` — returns subtree for root `mail` (no repeated root)
- [ ] `framework/packages/platform/mail/config/rules.php`
- [ ] `framework/packages/platform/mail/README.md` (Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/mail/src/Mail/Mailer.php` — implements `MailerInterface`
- [ ] `framework/packages/platform/mail/src/Transport/NullTransport.php` — implements `MailTransportInterface`
- [ ] `framework/packages/platform/mail/src/Security/Redaction.php` — hash/len helpers for safe logging
- [ ] `framework/packages/platform/mail/src/Observability/MailInstrumentation.php` — spans/metrics helpers
- [ ] `framework/packages/platform/mail/src/Exception/MailException.php` — deterministic codes (`CORETSIA_MAIL_SEND_FAILED`)

Optional async:
- [ ] `framework/packages/platform/mail/src/Queue/SendMailJob.php` — job payload (NO recipients/body)
- [ ] `framework/packages/platform/mail/src/Queue/SendMailJobHandler.php` — handler uses Mailer (registered explicitly)

Docs:
- [ ] `docs/architecture/mail.md` — canonical mail layer: transports, redaction, async option (SMTP section added by 4.101.0)

Tests:
- [ ] `framework/packages/platform/mail/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/platform/mail/tests/Unit/RedactionDoesNotLeakRecipientsTest.php`
- [ ] `framework/packages/platform/mail/tests/Integration/NullTransportDoesNotThrowTest.php`
- [ ] `framework/packages/platform/mail/tests/Integration/AsyncJobPayloadDoesNotContainPiiTest.php` (if async)

#### Modifies

- [ ] `docs/ssot/config-roots.md` — register:
  - `mail` (owner `platform/mail`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/mail/composer.json`
- [ ] `framework/packages/platform/mail/src/Module/MailModule.php`
- [ ] `framework/packages/platform/mail/src/Provider/MailServiceProvider.php`
- [ ] `framework/packages/platform/mail/config/mail.php`
- [ ] `framework/packages/platform/mail/config/rules.php`
- [ ] `framework/packages/platform/mail/README.md`
- [ ] `framework/packages/platform/mail/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/mail/config/mail.php`
- [ ] Keys (dot):
  - [ ] `mail.enabled` = `true`
  - [ ] `mail.transport` = `'null'` (`'null'|'smtp'`)
  - [ ] `mail.async.enabled` = `false` (requires queue at runtime)
- [ ] Rules:
  - [ ] `framework/packages/platform/mail/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] binds `Coretsia\Contracts\Mail\MailerInterface` → `Mailer`
  - [ ] binds default `Coretsia\Contracts\Mail\MailTransportInterface` → `NullTransport` (fallback)
  - [ ] transport selection is config-driven and MUST NOT import integrations at compile-time

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
- [ ] Context writes (safe only):
  - [ ] none (mail must not write recipients/body)
- [ ] Reset discipline:
  - N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `mail.send` (attrs: transport, outcome)
- [ ] Metrics:
  - [ ] `mail.sent_total` (labels: `driver`, `outcome`)
  - [ ] `mail.failed_total` (labels: `driver`, `outcome`)
  - [ ] `mail.duration_ms` (labels: `driver`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`
- [ ] Logs:
  - [ ] never log recipients/body/headers; only counts + message id hash

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/mail/src/Exception/MailException.php` — errorCode `CORETSIA_MAIL_SEND_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] recipients, subject/body, headers, raw payloads
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  (asserts: names + label allowlist + no PII)
- [ ] If redaction exists → `framework/packages/platform/mail/tests/Unit/RedactionDoesNotLeakRecipientsTest.php`
- [ ] If async payload exists → `framework/packages/platform/mail/tests/Integration/AsyncJobPayloadDoesNotContainPiiTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/mail/tests/Unit/RedactionDoesNotLeakRecipientsTest.php`
- Contract:
  - [ ] `framework/packages/platform/mail/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/mail/tests/Integration/NullTransportDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/mail/tests/Integration/AsyncJobPayloadDoesNotContainPiiTest.php` (if async)
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] `MailerInterface` always resolvable (NullTransport fallback)
- [ ] Redaction policy enforced (no PII/secret leaks in signals/logs)
- [ ] Optional async send does not serialize PII/secret payload
- [ ] Tests green + docs complete
- [ ] Forbidden deps respected (no compile-time deps on integrations/queue/http/cli)
- [ ] Determinism: rerun-no-diff (where applicable)

---

### 4.101.0 integrations/mail-smtp (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.101.0"
owner_path: "framework/packages/integrations/mail-smtp/"

package_id: "integrations/mail-smtp"
composer: "coretsia/integrations-mail-smtp"
kind: runtime
module_id: "integrations.mail-smtp"

goal: "Надати SMTP transport integration для mail layer: secret_ref для пароля та сувора redaction-політика (без витоку PII/секретів), без compile-time залежності на platform/mail."
provides:
- "SMTP transport (`MailTransportInterface`) як окремий integrations package"
- "Secrets resolver support через `password_secret_ref`"
- "Deterministic SMTP client (без raw transcript leakage; optional reset discipline)"

tags_introduced: []
config_roots_introduced:
- "mail_smtp"

artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/config-roots.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `4.100.0` (platform/mail) — canonical layer існує; integration підʼєднується як transport опція (runtime composition).
  - `core/contracts` містить:
    - mail contracts (`MailTransportInterface`, `MailMessage`)
    - secrets contract (`SecretsResolverInterface`)
    - observability ports (tracing/metrics)
  - `core/foundation` доступний для DI/Context APIs (якщо потрібні).

- Required deliverables (exact paths):
  - `docs/ssot/config-roots.md` — існує та дозволяє реєстрацію нового root `mail_smtp`.
  - `docs/architecture/mail.md` — створено в `4.100.0` (ця епіка лише доповнює SMTP секцію).

- Required config roots/keys:
  - `mail_smtp.*` — smtp params (password via secret_ref)
  - (integration point) `mail.transport='smtp'` — key owned by `platform/mail`

- Required contracts / ports:
  - `Coretsia\Contracts\Mail\MailTransportInterface`
  - `Coretsia\Contracts\Mail\MailMessage`
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Log\LoggerInterface` (optional)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/*`  # no compile-time dependency on platform/mail, queue, etc.
- `integrations/*` (except self)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Mail\MailTransportInterface`
  - `Coretsia\Contracts\Mail\MailMessage`
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/mail-smtp/src/Module/MailSmtpModule.php`
- [ ] `framework/packages/integrations/mail-smtp/src/Provider/MailSmtpServiceProvider.php`
- [ ] `framework/packages/integrations/mail-smtp/src/Provider/MailSmtpServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/mail-smtp/config/mail_smtp.php` — returns subtree for root `mail_smtp` (no repeated root)
- [ ] `framework/packages/integrations/mail-smtp/config/rules.php`
- [ ] `framework/packages/integrations/mail-smtp/README.md` (Observability / Errors / Security-Redaction)
- [ ] `framework/packages/integrations/mail-smtp/src/Smtp/SmtpTransport.php` — implements `MailTransportInterface`
- [ ] `framework/packages/integrations/mail-smtp/src/Smtp/SmtpClient.php` — minimal SMTP client (deterministic)
- [ ] `framework/packages/integrations/mail-smtp/src/Exception/SmtpTransportException.php` — deterministic codes (`CORETSIA_MAIL_SMTP_FAILED`)

Tests:
- [ ] `framework/packages/integrations/mail-smtp/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/integrations/mail-smtp/tests/Integration/SmtpTransportDoesNotLeakSecretsTest.php`

#### Modifies

- [ ] `docs/ssot/config-roots.md` — register:
  - `mail_smtp` (owner `integrations/mail-smtp`)
- [ ] `docs/architecture/mail.md` — add SMTP transport section (config root `mail_smtp`, secret_ref, redaction rules)

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/mail-smtp/composer.json`
- [ ] `framework/packages/integrations/mail-smtp/src/Module/MailSmtpModule.php`
- [ ] `framework/packages/integrations/mail-smtp/src/Provider/MailSmtpServiceProvider.php`
- [ ] `framework/packages/integrations/mail-smtp/config/mail_smtp.php`
- [ ] `framework/packages/integrations/mail-smtp/config/rules.php`
- [ ] `framework/packages/integrations/mail-smtp/README.md`
- [ ] `framework/packages/integrations/mail-smtp/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/mail-smtp/config/mail_smtp.php`
- [ ] Keys (dot):
  - [ ] `mail_smtp.enabled` = `false`
  - [ ] `mail_smtp.host` = `'localhost'`
  - [ ] `mail_smtp.port` = `25`
  - [ ] `mail_smtp.username` = `null`
  - [ ] `mail_smtp.password_secret_ref` = `null`
  - [ ] `mail_smtp.encryption` = `'none'` (`'none'|'tls'|'starttls'`)
- [ ] Rules:
  - [ ] `framework/packages/integrations/mail-smtp/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers `SmtpTransport` as a concrete transport service (availability depends on package presence)
  - [ ] runtime selection is driven by `platform/mail` (`mail.transport='smtp'`) without compile-time coupling

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (optional, for trace correlation)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] if `SmtpClient` is stateful (buffers/connection) → implement `ResetInterface` + tag `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `mail.send` (attrs: transport=`smtp`, outcome)
- [ ] Metrics:
  - [ ] `mail.sent_total` (labels: `driver`, `outcome`)
  - [ ] `mail.failed_total` (labels: `driver`, `outcome`)
  - [ ] `mail.duration_ms` (labels: `driver`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`
- [ ] Logs:
  - [ ] never log: password, recipients, subject/body, headers, raw SMTP transcript; only safe metadata (counts + hashed ids)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/integrations/mail-smtp/src/Exception/SmtpTransportException.php` — errorCode `CORETSIA_MAIL_SMTP_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] smtp password (even resolved), secret_ref values, raw SMTP transcript, recipients/subject/body/headers
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  (asserts: names + label allowlist + no PII)
- [ ] If secrets resolver is used → `framework/packages/integrations/mail-smtp/tests/Integration/SmtpTransportDoesNotLeakSecretsTest.php`
  (asserts: resolved password не зʼявляється у signals/logs/exceptions)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` (only if tagging is introduced)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] fake socket / fake SMTP server for SMTP tests
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/integrations/mail-smtp/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/mail-smtp/tests/Integration/SmtpTransportDoesNotLeakSecretsTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] SMTP transport works with `password_secret_ref` via `SecretsResolverInterface`
- [ ] No leakage: password/recipients/subject/body/headers/raw transcript не потрапляють у signals/logs/exceptions
- [ ] Tests green + docs complete
- [ ] Forbidden deps respected (no compile-time deps on platform/*)
- [ ] Determinism: rerun-no-diff (where applicable)

---

### 4.110.0 coretsia/view (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.110.0"
owner_path: "framework/packages/platform/view/"

package_id: "platform/view"
composer: "coretsia/platform-view"
kind: runtime
module_id: "platform.view"

goal: "Надати production-usable view subsystem (PHP templates) з детермінованим resolution (areas/themes/layouts/overrides), allowlisted roots policy та суворим redaction (без витоку vars/HTML/paths)."
provides:
- "RendererInterface + PHP template renderer з layout support"
- "TemplateId + deterministic TemplateLocator (overrides → theme → fallback themes → base roots)"
- "Roots allowlist policy + admin/user separation + escaping helpers"

tags_introduced: []
config_roots_introduced:
- "view"

artifacts_introduced: []
adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required config roots/keys:
  - `view.*` — areas/themes/roots/overrides policy
- Required contracts / ports:
  - `Coretsia\Contracts\Filesystem\DiskInterface` (через `platform/filesystem` та filesystem integrations як runtime policy)
  - (optional) tracing/metrics ports: `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`, `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Filesystem\DiskInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)

### Entry points / integration points (MUST)

N/A

### Canonical behavior (SSoT for this package) (MUST)

#### Template reference format (MUST)
- Canonical template id is **logical**, not a filesystem path:
  - `<area>::<name>` where:
    - `<area>` = `user|admin` (extensible через config allowlist)
    - `<name>` = posix-like relative id без `..`, напр. `dashboard/index` або `auth/login`
- Optional layout id:
  - `<area>::@layout/<name>` (canonical convention)
- Template file extension default: `.php` (configurable via `view.default_extension`)

#### Resolution order (deterministic) (MUST)
Given `(area, theme, templateId)` the locator MUST resolve in this deterministic order:
1. **Explicit override map** (per logical template id):
- `view.overrides["<area>::<id>"] = "<area>::<otherId>"` OR `@file:<rootKey>:<relpath>`
2. **Active theme** (if enabled):
- theme roots for `<area>` in stable order
3. **Fallback themes** (if configured) in stable order
4. **Base (non-theme) roots** for `<area>` in stable order
   Hard rules:
- Never accept `..` segments.
- Never accept raw absolute paths unless they are inside an allowlisted root (see “roots policy”).
- If multiple matches exist, “first match wins” due to ordered roots list (deterministic).

#### Roots & “any address” policy (MUST)
- View reads templates **only** from allowlisted roots configured in `view.roots`.
- A “root” is: `{key, disk, basePath}`:
  - `key` = stable string id, e.g. `app`, `module`, `shared`, `custom1`
  - `disk` = `DiskInterface` instance id
  - `basePath` = path within disk
- “будь де встановити власний шаблон” = user MAY add any directory as a root:
  - by adding a new entry to `view.roots` (explicit allowlist)
- TemplateLocator MUST use filesystem policy (SafePathJoiner / normalize) and MUST NOT log raw paths.

#### Themes mode (MUST)
- `view.theme` accepts:
  - `false` or `'disable'` → themes OFF (“standard directories mode”)
  - `<themeName>` → themes ON
- When themes OFF:
  - resolution uses only `view.areas.<area>.paths` (base roots) + overrides (if any)
- When themes ON:
  - theme contributes additional roots *ahead* of base paths (see deterministic order)

#### Admin vs User separation (MUST)
- Areas are explicit:
  - defaults: `user`, `admin`
- Each area has independent:
  - `paths` (base roots)
  - `layouts_path` (optional convention)
  - `default_layout` (optional)
- No implicit cross-area fallback unless explicitly configured.

### Deliverables (MUST)

#### Creates

Package skeleton:
- [ ] `framework/packages/platform/view/src/Module/ViewModule.php`
- [ ] `framework/packages/platform/view/src/Provider/ViewServiceProvider.php`
- [ ] `framework/packages/platform/view/src/Provider/ViewServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/view/config/view.php` — returns subtree for root `view` (no repeated root)
- [ ] `framework/packages/platform/view/config/rules.php`
- [ ] `framework/packages/platform/view/README.md` (Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/view/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

Public API (package-level):
- [ ] `framework/packages/platform/view/src/View/RendererInterface.php`
  - `render(string $template, array $vars = [], ?RenderOptions $opt = null): RenderResult`
- [ ] `framework/packages/platform/view/src/View/RenderOptions.php`
  - fields: `area`, `theme`, `layout`, `strict`, `overridesEnabled`, `traceEnabled`
- [ ] `framework/packages/platform/view/src/View/RenderResult.php`
  - fields: `bytes`, `contentType?`, `meta` (safe; no vars)

Core:
- [ ] `framework/packages/platform/view/src/View/PhpTemplateRenderer.php`
  - PHP template renderer using output buffering
  - layout support: render content → render layout with `$content` variable
  - provides template scope object with `e()` escaping helpers
- [ ] `framework/packages/platform/view/src/View/TemplateLocator.php`
  - deterministic resolution order (overrides → theme → fallback themes → base)
  - returns `ResolvedTemplate` (safe metadata)
- [ ] `framework/packages/platform/view/src/View/ResolvedTemplate.php`
  - fields: `templateId`, `rootKey`, `relativePath`, `fullPathHash` (no raw full path in logs)
- [ ] `framework/packages/platform/view/src/View/TemplateId.php`
  - parser/validator for `<area>::<name>`; forbids `..`, backslashes, null bytes

Themes + areas:
- [ ] `framework/packages/platform/view/src/Theme/ThemeManager.php`
  - computes theme roots for each area deterministically from config
- [ ] `framework/packages/platform/view/src/Theme/ThemeName.php`
  - validator for theme names (kebab-case; no traversal)
- [ ] `framework/packages/platform/view/src/Area/AreaRegistry.php`
  - validates configured areas allowlist; default `user|admin`

Security:
- [ ] `framework/packages/platform/view/src/Security/Escaper.php`
  - `escapeHtml`, `escapeAttr`, `escapeUrl`, `escapeJsString` (deterministic)
- [ ] `framework/packages/platform/view/src/Security/TemplateScope.php`
  - object passed into template scope exposing `e()` and safe helpers only
- [ ] `framework/packages/platform/view/src/Security/TemplateVariableRedaction.php`
  - helper for logging: only `hash/len` diagnostics (never values)

Errors:
- [ ] `framework/packages/platform/view/src/Exception/ViewException.php`
  - deterministic codes:
    - `CORETSIA_VIEW_TEMPLATE_NOT_FOUND`
    - `CORETSIA_VIEW_INVALID_TEMPLATE_ID`
    - `CORETSIA_VIEW_RENDER_FAILED`
    - `CORETSIA_VIEW_PATH_FORBIDDEN`
- [ ] `framework/packages/platform/view/src/Exception/ViewErrorCodes.php`
  - string enum (single SSoT for this package)

Docs:
- [ ] `docs/architecture/view.md`
  - canonical template locations
  - themes vs non-themes mode
  - admin/user areas
  - layout convention
  - safe roots policy + examples
  - redaction & observability

Tests:
- [ ] `framework/packages/platform/view/tests/Unit/TemplateIdValidationTest.php`
- [ ] `framework/packages/platform/view/tests/Unit/TemplateResolutionIsDeterministicTest.php`
- [ ] `framework/packages/platform/view/tests/Unit/ThemeResolutionOrderIsDeterministicTest.php`
- [ ] `framework/packages/platform/view/tests/Unit/AdminVsUserAreasDoNotCrossTest.php`
- [ ] `framework/packages/platform/view/tests/Unit/EscaperIsDeterministicTest.php`
- [ ] `framework/packages/platform/view/tests/Unit/OverridesMappingWorksTest.php`
- [ ] `framework/packages/platform/view/tests/Unit/PathTraversalIsForbiddenTest.php`
- [ ] `framework/packages/platform/view/tests/Integration/RenderSimpleTemplateTest.php`
- [ ] `framework/packages/platform/view/tests/Integration/RenderWithLayoutInThemeTest.php`
- [ ] `framework/packages/platform/view/tests/Integration/ThemeDisabledUsesBasePathsOnlyTest.php`
- [ ] `framework/packages/platform/view/tests/Integration/NoVariableLeakInLogsTest.php`
- [ ] `framework/packages/platform/view/tests/Integration/NotFoundIsDeterministicCodeTest.php`

#### Modifies

- [ ] `docs/ssot/config-roots.md` — register `view` root (owner `platform/view`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/view/composer.json`
- [ ] `framework/packages/platform/view/src/Module/ViewModule.php`
- [ ] `framework/packages/platform/view/src/Provider/ViewServiceProvider.php`
- [ ] `framework/packages/platform/view/config/view.php`
- [ ] `framework/packages/platform/view/config/rules.php`
- [ ] `framework/packages/platform/view/README.md`
- [ ] `framework/packages/platform/view/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/view/config/view.php`
- [ ] Keys (dot):
  - [ ] `view.enabled` = `true`
  - [ ] `view.default_extension` = `'.php'`
  - [ ] `view.strict` = `false`
    - meaning: if true → deny rendering when template uses undefined vars (fixture-enforced; implementation-specific)
  - [ ] `view.theme` = `false` (allowed: `false|'disable'|'<themeName>'`)
  - [ ] `view.theme.fallbacks` = `[]`  # list-like, deterministic order
  - [ ] `view.areas.user.paths` = `[]`   # list-like (base roots references)
  - [ ] `view.areas.user.default_layout` = `null`
  - [ ] `view.areas.admin.paths` = `[]`  # list-like
  - [ ] `view.areas.admin.default_layout` = `null`
  - [ ] `view.roots` = `[]` (list-like: `{(string) key, disk (service id or well-known disk name), (string) base_path}`)
  - [ ] `view.paths` = `[]` (legacy/simple mode; transformed into roots+areas by config loader)
    - if non-empty, MUST be transformed by config loader into `view.roots + view.areas.*.paths`
  - [ ] `view.overrides` = `[]` (map: `"<area>::<id>" => "<area>::<id>"| "@file:<rootKey>:<relpath>"`)
  - [ ] `view.allow_custom_file_refs` = `false`
    - if true, allows `@file:<rootKey>:<relpath>` references (still root-restricted)
- [ ] Rules:
  - [ ] `framework/packages/platform/view/config/rules.php` enforces shape (union types, list-like, template id validation)
    - `view.theme` union type: bool|string (string must be non-empty; `'disable'` normalized to false)
    - `view.areas` must include at least `user` and `admin` unless explicitly overridden with allowlist
    - all lists are list-like (no associative arrays) and preserve deterministic order
    - `view.overrides` keys MUST be valid TemplateId

#### Wiring / DI tags (when applicable)

- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\View\View\PhpTemplateRenderer::class`
  - [ ] binds: `Coretsia\View\View\RendererInterface::class` → default renderer
- [ ] No required DI tags.
- [ ] Integration readiness (documented):
  - [ ] Integrations MAY replace `RendererInterface` (Twig/Blade) by overriding DI binding.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (safe error logs)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] if renderer caches mutable state → `ResetInterface` + tag `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `view.render` (attrs safe):
    - `template_hash` (hash of logical template id)
    - `area`
    - `theme` (name or `none`)
    - `outcome` (`ok|not_found|error|forbidden`)
- [ ] Metrics:
  - [ ] `view.render_total` (labels: `outcome`)
  - [ ] `view.render_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] errors only; NEVER dump vars/content/html; no raw filesystem paths
  - [ ] allowed diagnostics only: `template_hash`, `area`, `theme`, `correlation_id`, `code`

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/view/src/Exception/ViewException.php` — deterministic codes:
    - `CORETSIA_VIEW_TEMPLATE_NOT_FOUND`
    - `CORETSIA_VIEW_INVALID_TEMPLATE_ID`
    - `CORETSIA_VIEW_RENDER_FAILED`
    - `CORETSIA_VIEW_PATH_FORBIDDEN`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) — if http layer wants RFC7807, it maps ViewException codes safely

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] template variables, rendered HTML, raw filesystem paths, file contents
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` only
- [ ] Path policy:
  - [ ] forbid traversal (`..`), backslashes, null bytes
  - [ ] allow reads only from allowlisted `view.roots`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `framework/packages/platform/view/tests/Integration/NoVariableLeakInLogsTest.php`
- [ ] If redaction exists → `framework/packages/platform/view/tests/Integration/NoVariableLeakInLogsTest.php`
- [ ] Path safety/traversal forbidden → `framework/packages/platform/view/tests/Unit/PathTraversalIsForbiddenTest.php`

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/view/tests/Unit/TemplateIdValidationTest.php`
  - [ ] `framework/packages/platform/view/tests/Unit/TemplateResolutionIsDeterministicTest.php`
  - [ ] `framework/packages/platform/view/tests/Unit/ThemeResolutionOrderIsDeterministicTest.php`
  - [ ] `framework/packages/platform/view/tests/Unit/AdminVsUserAreasDoNotCrossTest.php`
  - [ ] `framework/packages/platform/view/tests/Unit/EscaperIsDeterministicTest.php`
  - [ ] `framework/packages/platform/view/tests/Unit/OverridesMappingWorksTest.php`
  - [ ] `framework/packages/platform/view/tests/Unit/PathTraversalIsForbiddenTest.php`
- Contract:
  - [ ] `framework/packages/platform/view/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/view/tests/Integration/RenderSimpleTemplateTest.php`
  - [ ] `framework/packages/platform/view/tests/Integration/RenderWithLayoutInThemeTest.php`
  - [ ] `framework/packages/platform/view/tests/Integration/ThemeDisabledUsesBasePathsOnlyTest.php`
  - [ ] `framework/packages/platform/view/tests/Integration/NoVariableLeakInLogsTest.php`
  - [ ] `framework/packages/platform/view/tests/Integration/NotFoundIsDeterministicCodeTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deterministic resolution order implemented (overrides → theme → fallbacks → base)
- [ ] Themes on/off + fallback chain deterministic
- [ ] Admin/User separation enforced (no implicit cross-area fallback)
- [ ] Roots allowlist policy enforced; traversal forbidden
- [ ] Determinism: same fixtures + same inputs → same output bytes
- [ ] No variables/HTML/paths leaked to logs/metrics/traces
- [ ] Tests green + docs (`README.md` + `docs/architecture/view.md`) complete
- [ ] Дати **production-usable view subsystem** (PHP templates) з:
  - [ ] deterministic template resolution,
  - [ ] safe escaping policy (helpers + opt-in strict mode),
  - [ ] **themes (template sets)** + layout support,
  - [ ] чітким розділенням **user vs admin** templates.
  - [ ] Дати **гнучку модель розміщення шаблонів**:
    - [ ] стандартні директорії “без тем”,
    - [ ] теми (themes) з власними layout’ами,
    - [ ] можливість підключити **будь-які директорії** як roots (allowlist), включно з “будь-де” (explicit allowlist).
  - [ ] Підготувати path/policy для mail templates (optional usage) і основу для modern template engines через integrations (twig/blade) **без перетворення view на моноліт**.
- [ ] Non-goals / out of scope
  - [ ] Повноцінний templating DSL (twig/blade) у coretsia/view (це окремі `integrations/view-*`).
  - [ ] Автоматичне логування/дамп template variables або rendered HTML (заборонено).
  - [ ] “Магічна” автоіндексація шаблонів по FS (ніяких nondeterministic directory scans у runtime).
- [ ] Runtime expectation (policy, NOT deps):
  - [ ] Usually present when enabled in presets/bundles:
    - [ ] filesystem driver integration enabled for real IO (e.g. `integrations/filesystem-local`)
  - [ ] View is pure runtime service; no required DI tags by default.
  - [ ] Integrations MAY provide alternative renderer implementations (twig/blade) by depending on `platform/view` and wiring their own service (documented here; implemented in separate epics).
- [ ] When a template is rendered twice with same inputs (fixtures) and the same theme/area/layout, then output bytes are identical and no variables/HTML are logged.
- [ ] `platform.view` дозволяє **детерміновано** знайти й відрендерити template (з layout/theme/area), **без витоку** даних у логи, з чіткою політикою roots і escaping.

---

### 4.120.0 Translation / i18n (SHOULD) [IMPL]

---
type: package
phase: 4
epic_id: "4.120.0"
owner_path: "framework/packages/platform/translation/"

package_id: "platform/translation"
composer: "coretsia/platform-translation"
kind: runtime
module_id: "platform.translation"

goal: "Надати мінімальний translation/i18n шар з детермінованим loading/resolution та policy-compliant observability (без key/value як metric labels; без витоку значень)."
provides:
- "Translator + loaders (PHP-array, JSON) з deterministic order"
- "Locale resolver з fallback chain"
- "Redaction + noop-safe observability (no high-cardinality labels)"

tags_introduced: []
config_roots_introduced:
- "translation"

artifacts_introduced: []
adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required config roots/keys:
  - `translation.*` — locale/fallback/loaders/paths/disk

- Required contracts / ports:
  - (optional) `Coretsia\Contracts\Filesystem\DiskInterface` — якщо loader читає з disk binding
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)

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
  - `Coretsia\Contracts\Filesystem\DiskInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

Package skeleton:
- [ ] `framework/packages/platform/translation/src/Module/TranslationModule.php`
- [ ] `framework/packages/platform/translation/src/Provider/TranslationServiceProvider.php`
- [ ] `framework/packages/platform/translation/src/Provider/TranslationServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/translation/config/translation.php` — returns subtree for root `translation` (no repeated root)
- [ ] `framework/packages/platform/translation/config/rules.php`
- [ ] `framework/packages/platform/translation/README.md` (Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/translation/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

Implementation:
- [ ] `framework/packages/platform/translation/src/Translation/TranslatorInterface.php` — app-facing translator API
- [ ] `framework/packages/platform/translation/src/Translation/Translator.php` — reference translator (deterministic)
- [ ] `framework/packages/platform/translation/src/Loader/TranslationLoaderInterface.php`
- [ ] `framework/packages/platform/translation/src/Loader/PhpArrayLoader.php` — load `*.php` dictionaries (sorted)
  - Deterministic loading policy (cemented):
    - If loaders read from filesystem/disk, they MUST:
      - normalize relpaths (forward slashes),
      - sort file lists by `strcmp`,
      - hard-fail on symlinks (MUST NOT follow),
      - never emit raw paths or file contents in diagnostics/signals.
    - Dictionary merge order is single-choice:
      - loaders order = `translation.loaders` (list order preserved),
      - within a loader: files order = sorted `strcmp`,
      - later files override earlier keys (deterministic).

- [ ] `framework/packages/platform/translation/src/Loader/JsonLoader.php` — load `*.json` dictionaries (sorted)
  - Deterministic loading policy (cemented):
    - If loaders read from filesystem/disk, they MUST:
      - normalize relpaths (forward slashes),
      - sort file lists by `strcmp`,
      - hard-fail on symlinks (MUST NOT follow),
      - never emit raw paths or file contents in diagnostics/signals.
    - Dictionary merge order is single-choice:
      - loaders order = `translation.loaders` (list order preserved),
      - within a loader: files order = sorted `strcmp`,
      - later files override earlier keys (deterministic).

- [ ] `framework/packages/platform/translation/src/Resolver/LocaleResolver.php` — deterministic locale + fallback chain
- [ ] `framework/packages/platform/translation/src/Observability/TranslationInstrumentation.php` — spans/metrics helper
- [ ] `framework/packages/platform/translation/src/Exception/TranslationException.php` — deterministic codes

Docs:
- [ ] `docs/architecture/translation.md` — format, ordering, redaction, integration patterns

Tests:
- [ ] `framework/packages/platform/translation/tests/Unit/FallbackChainDeterministicTest.php`
- [ ] `framework/packages/platform/translation/tests/Unit/LoaderOrderDeterministicTest.php`
- [ ] `framework/packages/platform/translation/tests/Integration/LoadsCatalogsInDeterministicOrderTest.php`
- [ ] `framework/packages/platform/translation/tests/Integration/MissingKeyDoesNotLeakToLogsTest.php`

#### Modifies

- [ ] `docs/ssot/config-roots.md` — register `translation` root (owner `platform/translation`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/translation/composer.json`
- [ ] `framework/packages/platform/translation/src/Module/TranslationModule.php`
- [ ] `framework/packages/platform/translation/src/Provider/TranslationServiceProvider.php`
- [ ] `framework/packages/platform/translation/config/translation.php`
- [ ] `framework/packages/platform/translation/config/rules.php`
- [ ] `framework/packages/platform/translation/README.md`
- [ ] `framework/packages/platform/translation/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/translation/config/translation.php`
- [ ] Keys (dot):
  - [ ] `translation.enabled` = `false`
  - [ ] `translation.default_locale` = `'en'`
  - [ ] `translation.fallback_locales` = `['en']`
  - [ ] `translation.loaders` = `['php','json']`
  - [ ] `translation.paths` = `[]` (list-like; deterministic order)
  - [ ] `translation.disk` = `'local'` (optional; resolved at runtime)
- [ ] Rules:
  - [ ] `framework/packages/platform/translation/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] binds `TranslatorInterface` → `Translator`
  - [ ] registers loaders + resolver as services (deterministic ordering)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (optional; safe logs)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] cached catalogs implement `ResetInterface` + tag `kernel.reset` (if cached in memory)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `translation.lookup` (attrs: `outcome=hit|miss`, `locale` as span attr OK)
- [ ] Metrics:
  - [ ] `translation.lookup_total` (labels: `outcome`)
  - [ ] `translation.lookup_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] warn on missing key/locale (NO key/value dump; key only as `hash(key)`)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Translation\Exception\TranslationException` — deterministic codes:
    - `CORETSIA_TRANSLATION_LOAD_FAILED`
    - `CORETSIA_TRANSLATION_INVALID_CATALOG`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] translation values, user-provided interpolation values
- [ ] Allowed:
  - [ ] `hash(key)` / `len(value)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `framework/packages/platform/translation/tests/Integration/MissingKeyDoesNotLeakToLogsTest.php`
  (asserts: no key/value raw dump; no high-cardinality labels)
- [ ] Deterministic ordering → `framework/packages/platform/translation/tests/Integration/LoadsCatalogsInDeterministicOrderTest.php`

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/translation/tests/Unit/FallbackChainDeterministicTest.php`
  - [ ] `framework/packages/platform/translation/tests/Unit/LoaderOrderDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/translation/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/translation/tests/Integration/LoadsCatalogsInDeterministicOrderTest.php`
  - [ ] `framework/packages/platform/translation/tests/Integration/MissingKeyDoesNotLeakToLogsTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deterministic loading + deterministic fallback chain
- [ ] Observability policy satisfied (no locale/key as metric labels; no value leakage)
- [ ] Tests green + docs complete (`README.md` + `docs/architecture/translation.md`)
- [ ] What problem this epic solves
  - [ ] Дає мінімальний translation/i18n шар (translator + loaders) з deterministic resolution order
  - [ ] Підтримує PHP-array і JSON каталоги перекладів; без runtime FS scan без сортування
  - [ ] Забезпечує redaction (не логити шаблонні змінні/значення) + noop-safe observability
- [ ] Non-goals / out of scope
  - [ ] Не робимо повноцінний ICU messageformat engine (може бути окремим пакетом пізніше)
  - [ ] Не нав’язуємо інтеграцію іншим platform пакетам через compile-time deps (інтеграція — через app code/DI)
  - [ ] Не додаємо висококардинальних labels (locale/key як label — заборонено)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] a `DiskInterface` binding is provided by enabled filesystem modules (policy; not a compile-time dependency)
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] When translating a key with missing locale, then translator deterministically falls back to configured fallback locale.
- [ ] Переклади завантажуються детерміновано, локаль/fallback працюють стабільно, а логи/метрики не містять ключів/значень як labels.

---

### 4.130.0 Contracts: Auth / Session / Security / Lock (IMPL boundary ports) (MUST) [CONTRACTS]

---
type: package
phase: 4
epic_id: "4.130.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Contracts формалізують boundary ports/VO/exceptions для auth/session/security/lock без PSR-7 leakage, щоб platform реалізації були замінні та не створювали циклів залежностей."
provides:
- "Auth ports + exceptions (Identity/UserProvider/Authenticator/Authorization/PasswordHasher)"
- "Session ports (Session/SessionStorage/SessionManager)"
- "Security ports (CSRF token manager, URL signer) та Lock ports (Lock/LockFactory + exception)"
- "Format-neutral RequestContext VO (json-like policy)"
- "`RequestContext` is format-neutral and MUST be `json-like` only (null|bool|int|string + list/map arrays)"
- "`RequestContext` MUST NOT contain: raw path/query, raw headers/cookies, Authorization/token, session id, payload/body, raw SQL, stack traces"
- "If any identifiers are needed, they MUST be safe forms only: `hash(value)` and/or `len(value)` computed by implementation (contracts do not compute)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-0054-auth-session-security-lock-ports.md"
ssot_refs:
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/config-and-env.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - none

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/src/Env/EnvRepositoryInterface.php` — env reads (policy-compliant).
  - `framework/packages/core/contracts/src/Secrets/SecretsResolverInterface.php` — contract port consumed by this package.
  - `framework/packages/core/contracts/src/Observability/Tracing/TracerPortInterface.php` — tracing (noop-safe).
  - `framework/packages/core/contracts/src/Observability/Metrics/MeterPortInterface.php` — metrics (noop-safe).
  - (optional) `framework/packages/core/contracts/src/Context/ContextAccessorInterface.php` — context reads (signature `get(string $key): mixed`, no default).

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none (this epic INTRODUCES them)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- none

Forbidden:
- `platform/*`
- `Psr\Http\Message\*` (contracts must remain format-neutral)

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/Runtime/RequestAttributes.php` — reserved request attribute keys (string constants only; no PSR-7 imports).
  - RequestAttributes reserved keys (single-choice)
    - `RequestAttributes::SESSION` — a request attribute holding `Coretsia\Contracts\Session\SessionInterface`.
    - `RequestAttributes::IDENTITY` — a request attribute holding `Coretsia\Contracts\Auth\IdentityInterface`.

- [ ] `framework/packages/core/contracts/src/Runtime/RequestContext.php` — VO (format-neutral request context)
- [ ] `framework/packages/core/contracts/src/Auth/IdentityInterface.php`
- [ ] `framework/packages/core/contracts/src/Auth/UserProviderInterface.php`
- [ ] `framework/packages/core/contracts/src/Auth/PasswordHasherInterface.php`
- [ ] `framework/packages/core/contracts/src/Auth/AuthenticatorInterface.php`
- [ ] `framework/packages/core/contracts/src/Auth/AuthorizationInterface.php`
- [ ] `framework/packages/core/contracts/src/Auth/AuthException.php`
- [ ] `framework/packages/core/contracts/src/Auth/UnauthenticatedException.php`
- [ ] `framework/packages/core/contracts/src/Auth/ForbiddenException.php`

- [ ] `framework/packages/core/contracts/src/Session/SessionInterface.php`
- [ ] `framework/packages/core/contracts/src/Session/SessionStorageInterface.php`
- [ ] `framework/packages/core/contracts/src/Session/SessionManagerInterface.php`

- [ ] `framework/packages/core/contracts/src/Security/CsrfTokenManagerInterface.php`
- [ ] `framework/packages/core/contracts/src/Security/UrlSignerInterface.php`

- [ ] `framework/packages/core/contracts/src/Lock/LockInterface.php`
- [ ] `framework/packages/core/contracts/src/Lock/LockFactoryInterface.php`
- [ ] `framework/packages/core/contracts/src/Lock/LockException.php`

- [ ] `framework/packages/core/contracts/README.md` (optional) — boundary docs + policy notes
- [ ] `framework/packages/core/contracts/config/rules.php` (optional) — package rules (if maintained)

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0054-auth-session-security-lock-ports.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/core/contracts/composer.json`
- [ ] `framework/packages/core/contracts/README.md` (optional)
- [ ] `framework/packages/core/contracts/config/rules.php` (optional)

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

> For contracts, verification is by contract/unit tests (no runtime cross-cutting emitters).

#### Required policy tests matrix

N/A

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/core/contracts/tests/Unit/RequestContextShapeTest.php`
- Contract:
  - [ ] `framework/packages/core/contracts/tests/Contract/AuthContractsTest.php`
  - [ ] `framework/packages/core/contracts/tests/Contract/SessionContractsTest.php`
  - [ ] `framework/packages/core/contracts/tests/Contract/LockContractsTest.php`
  - [ ] `framework/packages/core/contracts/tests/Contract/RequestContextIsFormatNeutralContractTest.php`
  - [ ] `framework/packages/core/contracts/tests/Contract/ContractsDoNotDependOnPsr7ContractTest.php`
- Integration:
  - [ ] none
- Gates/Arch:
  - [ ] `framework/tools/gates/contracts_only_ports_gate.php` expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Determinism: VO shapes stable + tests protect them
- [ ] Tests green: contract tests pass
- [ ] Docs updated:
  - [ ] `docs/adr/ADR-0054-auth-session-security-lock-ports.md`
  - [ ] `framework/packages/core/contracts/README.md` (if maintained)
- [ ] Додавання HTTP-aware типів/Response у contracts (заборонено)
- [ ] Contracts дозволяють реалізувати session/auth/security/lock у platform layer без змін contracts і без PSR-7 leakage.
- [ ] When `platform/auth` implements `AuthenticatorInterface`, then `platform/http` can run `AuthMiddleware` without contracts importing PSR-7.

---

### 4.140.0 coretsia/session — Session layer (file storage + middleware) (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.140.0"
owner_path: "framework/packages/platform/session/"

package_id: "platform/session"
composer: "coretsia/platform-session"
kind: runtime
module_id: "platform.session"

goal: "Коли module platform.session увімкнено, HTTP запити отримують робочу сесію (cookie+storage), middleware працює детерміновано та без витоку session id/cookie/payload."
provides:
- "Reference session manager + SessionInterface"
- "File session storage через filesystem policy (safe paths)"
- "PSR-15 SessionMiddleware (start/commit) + noop-safe observability"

tags_introduced: []
config_roots_introduced: ["session"]
artifacts_introduced: []

adr: "docs/adr/ADR-0055-session-layer-file-storage.md"
ssot_refs:
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/metrics-policy.md"
- "docs/ssot/config-and-env.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `4.130.0` — session contracts (`SessionInterface`, `SessionStorageInterface`, `SessionManagerInterface`) exist.
  - (pre-existing) `platform/filesystem` — provides filesystem policy + disk port implementation.
  - `4.130.0` — provides `Coretsia\Contracts\Runtime\RequestAttributes` (shared request attribute keys).

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/src/Session/SessionInterface.php` — contract port.
  - `framework/packages/core/contracts/src/Session/SessionStorageInterface.php` — contract port.
  - `framework/packages/core/contracts/src/Session/SessionManagerInterface.php` — contract port.
  - `framework/packages/core/contracts/src/Filesystem/DiskInterface.php` — used by file storage.
  - `framework/packages/core/contracts/src/Observability/Tracing/TracerPortInterface.php` — tracing (noop-safe).
  - `framework/packages/core/contracts/src/Observability/Metrics/MeterPortInterface.php` — metrics (noop-safe).

- Required config roots/keys:
  - `session` / `session.*` — this epic introduces and owns.

- Required tags:
  - `http.middleware.app_pre` — middleware slot (owned by http layer; referenced here).
  - `kernel.reset` — only if stateful services are introduced (optional).

- Required contracts / ports:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
  - `Coretsia\Contracts\Session\SessionInterface`
  - `Coretsia\Contracts\Session\SessionManagerInterface`
  - `Coretsia\Contracts\Session\SessionStorageInterface`
  - `Coretsia\Contracts\Filesystem\DiskInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `platform/filesystem`

Forbidden:
- `platform/http` (session package MUST NOT import http runtime types)
- `platform/auth`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` (optional safe logs)
  - `Coretsia\Contracts\Session\SessionInterface`
  - `Coretsia\Contracts\Session\SessionManagerInterface`
  - `Coretsia\Contracts\Session\SessionStorageInterface`
  - `Coretsia\Contracts\Filesystem\DiskInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface` (only if stateful services appear)

### Entry points / integration points (MUST)

- HTTP:
  - middleware slots/tags: `http.middleware.app_pre` priority `300` meta `{"reason":"session opens/closes before auth/csrf"}`
- Artifacts:
  - reads: `skeleton/var/sessions/*` (runtime data; excluded from fingerprint)
  - writes: `skeleton/var/sessions/*` (runtime data; excluded from fingerprint)

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/session/src/Module/SessionModule.php` — runtime module entry.
- [ ] `framework/packages/platform/session/src/Provider/SessionServiceProvider.php` — DI wiring.
- [ ] `framework/packages/platform/session/src/Provider/SessionServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/session/src/Provider/Tags.php` — constants for used tags/slots.
- [ ] `framework/packages/platform/session/config/session.php` — config subtree `session` (no repeated root).
- [ ] `framework/packages/platform/session/config/rules.php` — config shape rules.
- [ ] `framework/packages/platform/session/README.md` — Observability / Errors / Security-Redaction.
- [ ] `framework/packages/platform/session/src/Session/SessionManager.php` — implements `SessionManagerInterface`.
- [ ] `framework/packages/platform/session/src/Session/Session.php` — implements `SessionInterface`.
- [ ] `framework/packages/platform/session/src/Storage/FileSessionStorage.php` — implements `SessionStorageInterface` (filesystem policy).
- [ ] `framework/packages/platform/session/src/Http/Middleware/SessionMiddleware.php` — PSR-15 middleware (start/commit).
- [ ] `framework/packages/platform/session/src/Http/SessionRequestAttributes.php` — OPTIONAL thin-alias only; MUST delegate to `Coretsia\Contracts\Runtime\RequestAttributes::SESSION` (source of truth lives in contracts).
- [ ] `framework/packages/platform/session/src/Exception/SessionException.php` — deterministic codes (io/corrupt).
- [ ] `framework/packages/platform/session/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0055-session-layer-file-storage.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/session/composer.json`
- [ ] `framework/packages/platform/session/src/Module/SessionModule.php`
- [ ] `framework/packages/platform/session/src/Provider/SessionServiceProvider.php`
- [ ] `framework/packages/platform/session/config/session.php`
- [ ] `framework/packages/platform/session/config/rules.php`
- [ ] `framework/packages/platform/session/README.md`
- [ ] `framework/packages/platform/session/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/session/config/session.php`
- [ ] Keys (dot):
  - [ ] `session.enabled` = true
  - [ ] `session.driver` = "file"
  - [ ] `session.cookie.name` = "coretsia_session"
  - [ ] `session.cookie.secure` = false
  - [ ] `session.cookie.http_only` = true
  - [ ] `session.cookie.same_site` = "lax"
  - [ ] `session.cookie.path` = "/"
  - [ ] `session.cookie.ttl_seconds` = 7200
  - [ ] `session.storage.path` = "skeleton/var/sessions"
  - [ ] `session.gc.per_mille` = 10
- [ ] Rules:
  - [ ] `framework/packages/platform/session/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (uses existing http middleware slot tag; publishes constants only)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Contracts\Session\SessionManagerInterface`
  - [ ] registers: `Coretsia\Contracts\Session\SessionStorageInterface`
  - [ ] registers: `Coretsia\Session\Http\Middleware\SessionMiddleware`
  - [ ] adds tag: `http.middleware.app_pre` priority `300` meta `{"reason":"session opens/closes before auth/csrf"}`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `skeleton/var/sessions/<id>.json` (or `.php`/binary; deterministic schemaVersion; atomic write)
- [ ] Reads:
  - [ ] validates header + payload schema for the same file(s)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (safe logs)
- [ ] Context writes (safe only):
  - [ ] none (MUST NOT write session id / cookie / token data)
- [ ] Reset discipline:
  - [ ] N/A by default (no stateful caches); if added → implement `ResetInterface` + tag `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `session.load`
  - [ ] `session.save`
  - [ ] `session.gc`
- [ ] Metrics:
  - [ ] `session.load_total` (labels: `driver`, `outcome`)
  - [ ] `session.save_total` (labels: `driver`, `outcome`)
  - [ ] `session.miss_total` (labels: `driver`, `outcome`)
  - [ ] `session.gc_total` (labels: `driver`, `outcome`)
  - [ ] `session.op_duration_ms` (labels: `driver`, `operation`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `guard→driver`, `op→operation`
- [ ] Logs:
  - [ ] corrupted session → warn (no cookie/session id; allowed: `hash/len` only)
  - [ ] no secrets/PII (no Cookie header dump)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Session\Exception\SessionException` — errorCode `CORETSIA_SESSION_STORAGE_FAILED`
  - [ ] `Coretsia\Session\Exception\SessionException` — errorCode `CORETSIA_SESSION_CORRUPT`
- [ ] Mapping:
  - [ ] `ExceptionMapperInterface` via tag `error.mapper` OR reuse default mapper (choose one)
  - [ ] optional create: `framework/packages/platform/session/src/Exception/SessionProblemMapper.php` (if dedicated mapping needed)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Cookie header, session id, session payload
- [ ] Allowed:
  - [ ] `hash(session_id)` only if absolutely required for dedupe; otherwise none

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `framework/packages/platform/session/tests/Contract/ObservabilityPolicyTest.php`
- [ ] If redaction exists → `framework/packages/platform/session/tests/Contract/RedactionDoesNotLeakTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions
- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` (only if boot wiring is asserted)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/session/tests/Unit/SessionIdValidationTest.php`
  - [ ] `framework/packages/platform/session/tests/Unit/FileSessionStorageSafePathTest.php`
- Contract:
  - [ ] `framework/packages/platform/session/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/session/tests/Integration/SessionMiddlewareSetsCookieAndPersistsBetweenRequestsTest.php`
  - [ ] `framework/packages/platform/session/tests/Integration/GcPerMilleDeterministicPolicyTest.php`
  - [ ] `framework/packages/platform/session/tests/Integration/Http/SessionWorksInEnterprisePresetTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: storage schema stable + atomic writes; no nondeterministic outputs
- [ ] Docs updated:
  - [ ] `framework/packages/platform/session/README.md`
  - [ ] `docs/adr/ADR-0055-session-layer-file-storage.md`
- [ ] Non-goals / out of scope
  - [ ] Redis session storage (Phase 6+ integration)
  - [ ] Будь-які auth/token механізми (це `platform/auth`)
  - [ ] Логування cookie/session id (заборонено)
- [ ] Коли модуль `platform.session` увімкнений, HTTP запити мають робочу сесію (cookie+storage), а middleware працює детерміновано і без витоку session id.
- [ ] When first request has no cookie, then middleware creates a new session and sets cookie; when second request sends that cookie, then session data is loaded and persisted.
- [ ] Phase-compat addendum (PHASE1 locks)
  - [ ] No platform/session ↔ platform/auth coupling (MUST)
    - [ ] Cross-package request attribute keys MUST come from `core/contracts`:
      - [ ] use `Coretsia\Contracts\Runtime\RequestAttributes::SESSION` (NOT a package-local key class as the source of truth).
  - [ ] Deterministic GC sampling (MUST if `session.gc.per_mille` is used)
    - [ ] Session GC decision MUST be deterministic (hash-based), NOT random:
      - [ ] sample = `sha256(session_id)` (or stable hash) → `mod 1000 < per_mille`.
    - [ ] `random_int()` / time-based sampling MUST NOT be used (breaks determinism tests).
  - [ ] Deterministic duration measurement (MUST)
    - [ ] Any `*_duration_ms` metric MUST use `Coretsia\Foundation\Time\Stopwatch` (int ms); floats forbidden.
  - [ ] SSoT registries (MUST)
    - [ ] This epic introduces config root `session` ⇒ MUST update `docs/ssot/config-roots.md` (owner row for `session`).

---

### 4.150.0 coretsia/auth — Session auth + RBAC (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.150.0"
owner_path: "framework/packages/platform/auth/"

package_id: "platform/auth"
composer: "coretsia/platform-auth"
kind: runtime
module_id: "platform.auth"

goal: "Коли platform.auth увімкнено, middleware детерміновано встановлює identity, записує safe actor_id у ContextStore і забезпечує 401/403 через canonical error flow без витоку credentials/session/token."
provides:
- "AuthMiddleware (PSR-15): authenticate + write safe actor_id"
- "SessionAuthenticator (reference guard) + IdentityStore (resettable)"
- "RBAC Authorization engine (reference) + deterministic 401/403 mapping via error.mapper"

tags_introduced: []
config_roots_introduced: ["auth"]
artifacts_introduced: []

adr: "docs/adr/ADR-0056-session-auth-rbac.md"
ssot_refs:
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/metrics-policy.md"
- "docs/ssot/config-and-env.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `4.130.0` — auth contracts ports/exceptions exist.
  - (usually present when enabled) `platform/logging|tracing|metrics` — provide noop-safe implementations.
  - (runtime-only expectation) `platform/session` may provide session identity via request attribute (NO compile-time dependency).
  - `4.130.0` — provides `Coretsia\Contracts\Runtime\RequestAttributes` (shared request attribute keys).

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/src/Auth/*` — ports + exceptions used here.
  - `framework/packages/core/contracts/src/Runtime/RequestContext.php` — format-neutral request context.
  - `framework/packages/core/contracts/src/Observability/Errors/ExceptionMapperInterface.php` + `ErrorDescriptor` — canonical error flow.
  - `framework/packages/core/foundation/src/Context/ContextStore.php` + `ContextKeys.php` — safe actor_id write target.

- Required config roots/keys:
  - `auth` / `auth.*` — this epic introduces and owns.

- Required tags:
  - `http.middleware.app_pre` — middleware slot (owned by http layer; referenced here).
  - `error.mapper` — exception mapper registration (owned by errors/observability layer; referenced here).
  - `kernel.reset` — resettable services registration (owned by kernel; referenced here).

- Required contracts / ports:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
  - `Coretsia\Contracts\Runtime\RequestContext`
  - `Coretsia\Contracts\Auth\IdentityInterface`
  - `Coretsia\Contracts\Auth\UserProviderInterface`
  - `Coretsia\Contracts\Auth\AuthenticatorInterface`
  - `Coretsia\Contracts\Auth\AuthorizationInterface`
  - `Coretsia\Contracts\Auth\PasswordHasherInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http` (auth package MUST NOT import http runtime)
- `platform/session` (use contracts only)
- `platform/hashing` (use PasswordHasherInterface from contracts only)
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Auth\*`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Runtime\RequestContext`
  - `Coretsia\Contracts\Observability\Errors\*`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextStore`
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- HTTP:
  - middleware slots/tags: `http.middleware.app_pre` priority `200` meta `{"reason":"writes actor_id before csrf/routing"}`
  - middleware (opt-in, MUST NOT be auto-wired):
    - `\Coretsia\Auth\Http\Middleware\RequireAuthMiddleware::class`
    - `\Coretsia\Auth\Http\Middleware\RequireAbilityMiddleware::class`
- Kernel hooks/tags:
  - `kernel.reset` — for `IdentityStore`
- Artifacts:
  - none

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/auth/src/Module/AuthModule.php` — runtime module entry.
- [ ] `framework/packages/platform/auth/src/Provider/AuthServiceProvider.php` — DI wiring.
- [ ] `framework/packages/platform/auth/src/Provider/AuthServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/auth/config/auth.php` — config subtree `auth` (no repeated root).
- [ ] `framework/packages/platform/auth/config/rules.php` — config shape rules.
- [ ] `framework/packages/platform/auth/README.md` — Observability / Errors / Security-Redaction.
- [ ] `framework/packages/platform/auth/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

- [ ] `framework/packages/platform/auth/src/Auth/IdentityStore.php` — stateful store (implements `ResetInterface`, tag `kernel.reset`).
- [ ] `framework/packages/platform/auth/src/Auth/SessionAuthenticator.php` — implements `AuthenticatorInterface`.
- [ ] `framework/packages/platform/auth/src/Auth/InMemoryUserProvider.php` — reference provider (fixtures/dev).
- [ ] `framework/packages/platform/auth/src/Auth/RbacAuthorization.php` — implements `AuthorizationInterface`.

- [ ] `framework/packages/platform/auth/src/Http/Middleware/AuthMiddleware.php` — PSR-15 (writes actor_id to ContextStore).
- [ ] `framework/packages/platform/auth/src/Http/Middleware/RequireAuthMiddleware.php` — opt-in.
- [ ] `framework/packages/platform/auth/src/Http/Middleware/RequireAbilityMiddleware.php` — opt-in.

- [ ] `framework/packages/platform/auth/src/Exception/AuthProblemMapper.php` — implements `ExceptionMapperInterface` (tag `error.mapper`).

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0056-session-auth-rbac.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/auth/composer.json`
- [ ] `framework/packages/platform/auth/src/Module/AuthModule.php`
- [ ] `framework/packages/platform/auth/src/Provider/AuthServiceProvider.php`
- [ ] `framework/packages/platform/auth/config/auth.php`
- [ ] `framework/packages/platform/auth/config/rules.php`
- [ ] `framework/packages/platform/auth/README.md`
- [ ] `framework/packages/platform/auth/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/auth/config/auth.php`
- [ ] Keys (dot):
  - [ ] `auth.enabled` = true
  - [ ] `auth.default_guard` = "session"
  - [ ] `auth.providers.users` = "in_memory"      # "in_memory" | "db" (db later)
  - [ ] `auth.rbac.roles` = []
  - [ ] `auth.rbac.permissions` = []
- [ ] Rules:
  - [ ] `framework/packages/platform/auth/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (uses existing tags; publishes constants only)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Contracts\Auth\AuthenticatorInterface` → `SessionAuthenticator` (default)
  - [ ] registers: `Coretsia\Contracts\Auth\AuthorizationInterface` → `RbacAuthorization`
  - [ ] registers: `Coretsia\Auth\Auth\IdentityStore` (tag `kernel.reset`)
  - [ ] registers: `Coretsia\Auth\Http\Middleware\AuthMiddleware`
  - [ ] adds tag: `http.middleware.app_pre` priority `200` meta `{"reason":"writes actor_id before csrf/routing"}`
  - [ ] registers: `Coretsia\Auth\Exceptions\AuthProblemMapper` (tag `error.mapper` priority `300`)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::CLIENT_IP` (optional, safe)
- [ ] Context writes (safe only):
  - [ ] `ContextKeys::ACTOR_ID` (canonical key `actor_id`)
  - [ ] optional: `auth.roles` (list-like, low-cardinality) (only if committed as safe)
- [ ] Reset discipline:
  - [ ] `IdentityStore` implements `ResetInterface`
  - [ ] `IdentityStore` tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `auth.authenticate`
  - [ ] `auth.authorize`
- [ ] Metrics:
  - [ ] `auth.authenticate_total` (labels: `driver`, `outcome`)
  - [ ] `auth.authenticate_failed_total` (labels: `driver`, `outcome`)
  - [ ] `auth.unauthenticated_total` (labels: `driver`, `outcome`)
  - [ ] `auth.forbidden_total` (labels: `driver`, `outcome`)
  - [ ] `auth.duration_ms` (labels: `driver`, `operation`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `guard→driver`, `kind/op→operation`
- [ ] Logs:
  - [ ] auth fail → info/warn without credentials/session id/token
  - [ ] forbidden → info (ability name only)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Contracts\Auth\UnauthenticatedException` — errorCode `CORETSIA_AUTH_UNAUTHENTICATED`
  - [ ] `Coretsia\Contracts\Auth\ForbiddenException` — errorCode `CORETSIA_AUTH_FORBIDDEN`
- [ ] Mapping:
  - [ ] `AuthProblemMapper` via tag `error.mapper`
- [ ] HTTP status hint policy (documented in mapper/descriptors):
  - [ ] unauthenticated → `httpStatus=401`
  - [ ] forbidden → `httpStatus=403`

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] credentials, Authorization header value, Cookie header, session id, tokens
- [ ] Allowed:
  - [ ] safe `actor_id` only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `framework/packages/platform/auth/tests/Contract/ContextWriteSafetyTest.php`
- [ ] If `kernel.reset` used → `framework/packages/platform/auth/tests/Contract/ResetWiringTest.php`
- [ ] If metrics/spans/logs exist → `framework/packages/platform/auth/tests/Contract/ObservabilityPolicyTest.php`
- [ ] If redaction exists → `framework/packages/platform/auth/tests/Contract/RedactionDoesNotLeakTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions
- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` (boot wiring proof)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/auth/tests/Unit/RbacAuthorizationDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/auth/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/auth/tests/Integration/ProtectedRouteReturns401WhenNoIdentityTest.php`
  - [ ] `framework/packages/platform/auth/tests/Integration/ProtectedRouteReturns403WhenNoAbilityTest.php`
  - [ ] `framework/packages/platform/auth/tests/Integration/ActorIdWrittenToContextStoreAfterAuthTest.php`
  - [ ] `framework/packages/platform/auth/tests/Integration/Http/AuthEndToEndTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: mapper outputs deterministic codes/status; no random ids
- [ ] Docs updated:
  - [ ] `framework/packages/platform/auth/README.md` (incl. middleware slot/priority + opt-in middlewares usage)
  - [ ] `docs/adr/ADR-0056-session-auth-rbac.md`
- [ ] What problem this epic solves
  - [ ] Reference auth middleware (`AuthMiddleware`) який формує identity для UoW і пише safe `actor_id` у ContextStore
  - [ ] RBAC authorization engine як reference реалізація `AuthorizationInterface`
  - [ ] Consistent 401/403 через `ExceptionMapperInterface` (tag `error.mapper`) → ErrorDescriptor → ProblemDetails
- [ ] Non-goals / out of scope
  - [ ] JWT/OIDC/SSO (це enterprise/integrations)
  - [ ] Auto-wiring policy middlewares `RequireAuth/RequireAbility` у глобальний стек (вони opt-in)
  - [ ] Зберігання credentials/tokens у логах/метриках (заборонено)
- [ ] Коли `platform.auth` увімкнено, middleware детерміновано встановлює identity, записує safe `actor_id` у ContextStore і забезпечує 401/403 через canonical error flow.
- [ ] When request has valid session identity, then `actor_id` is present in ContextStore and protected routes pass; when no identity, then `RequireAuthMiddleware` yields 401 RFC7807.
- [ ] Phase-compat addendum (Foundation/Kernel locks)
  - [ ] ContextKeys allowlist (MUST)
    - [ ] `actor_id` MUST be written only under the canonical allowlisted key `ContextKeys::ACTOR_ID`.
    - [ ] Configurable key `auth.context.actor_id_key` MUST NOT exist (or MUST be ignored) because unknown keys hard-fail in `ContextStorePolicy`.
  - [ ] Session integration without platform/session dependency (MUST)
    - [ ] If authentication consumes session established by `platform/session`, it MUST read it from request attributes using contracts key:
      - [ ] `Coretsia\Contracts\Runtime\RequestAttributes::SESSION`.
    - [ ] `platform/auth` MUST NOT import anything from `platform/session` (no compile-time dep).
  - [ ] Stateful markers (MUST)
    - [ ] Any stateful auth store (e.g., `IdentityStore`) MUST:
      - [ ] implement `Coretsia\Contracts\Runtime\ResetInterface`,
      - [ ] be tagged `kernel.reset`,
      - [ ] be marker-tagged `kernel.stateful` (enforcement-only marker).
  - [ ] Deterministic duration measurement (MUST)
    - [ ] Any `*_duration_ms` metric MUST use `Coretsia\Foundation\Time\Stopwatch` (int ms); floats forbidden.

---

### 4.160.0 coretsia/auth — Token/Bearer + optional JWT guard (SHOULD) [IMPL]

---
type: package
phase: 4
epic_id: "4.160.0"
owner_path: "framework/packages/platform/auth/"

package_id: "platform/auth"
composer: "coretsia/platform-auth"
kind: runtime
module_id: "platform.auth"

goal: "Коли увімкнено token/jwt guard, API може аутентифікуватись без session cookies, а 401/403 лишаються консистентні, без витоку токенів/claims у logs/spans/metrics."
provides:
- "Bearer token authenticator (PAT) як реалізація AuthenticatorInterface"
- "Optional JWT authenticator + verifier (config-driven)"
- "Deterministic guard selection rules (config) + redaction (hash/len only)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-0057-token-bearer-jwt-guard.md"
ssot_refs:
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/metrics-policy.md"
- "docs/ssot/config-and-env.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `4.150.0` — platform/auth skeleton + AuthMiddleware + base wiring exist.
  - `4.130.0` — auth contracts exist (AuthenticatorInterface etc).

- Required deliverables (exact paths):
  - `framework/packages/platform/auth/src/Http/Middleware/AuthMiddleware.php` — reused; guard selection extended deterministically.
  - `framework/packages/platform/auth/config/auth.php` — updated with guards config.
  - `framework/packages/platform/auth/config/rules.php` — updated shape rules.

- Required config roots/keys:
  - `auth` / `auth.guards.*` — must exist from 4.150.0; extended here.

- Required tags:
  - none new (reuses existing wiring from 4.150.0)

- Required contracts / ports:
  - `Coretsia\Contracts\Auth\AuthenticatorInterface`
  - `Coretsia\Contracts\Auth\IdentityInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface`
  - `Psr\Log\LoggerInterface`

- Rails reused (MUST):
  - Token/JWT raw values MUST NOT be logged; only `hash(token)` / `len(token)`.
  - Metric labels MUST NOT contain token/jwt claims or high-cardinality user data.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Auth\AuthenticatorInterface`
  - `Coretsia\Contracts\Auth\IdentityInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

- HTTP:
  - middleware: reuses existing `\Coretsia\Auth\Http\Middleware\AuthMiddleware::class` (4.150.0)
  - integration: deterministic guard selection is config-driven (`auth.guards.*`)
- Artifacts:
  - none

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/auth/src/Auth/BearerTokenAuthenticator.php` — implements `AuthenticatorInterface`
- [ ] `framework/packages/platform/auth/src/Auth/Token/TokenRepositoryInterface.php` — internal interface (NOT cross-package port)
- [ ] `framework/packages/platform/auth/src/Auth/Token/InMemoryTokenRepository.php` — reference store (Phase 2)
- [ ] `framework/packages/platform/auth/src/Auth/Token/TokenHasher.php` — hashes only, no plaintext
- [ ] `framework/packages/platform/auth/src/Auth/JwtAuthenticator.php` (optional) — implements `AuthenticatorInterface`
- [ ] `framework/packages/platform/auth/src/Auth/Jwt/JwtVerifier.php` (optional) — issuer/audience/algs policy

#### Modifies

- [ ] `framework/packages/platform/auth/config/auth.php` — add `auth.guards.*` keys + defaults; keep deterministic selection rules
- [ ] `framework/packages/platform/auth/config/rules.php` — enforce updated config shape
- [ ] `framework/packages/platform/auth/src/Provider/AuthServiceProvider.php` — register new authenticators/services; selection wiring config-driven
- [ ] `framework/packages/platform/auth/src/Http/Middleware/AuthMiddleware.php` — deterministic guard selection (no behavior randomness)
- [ ] `framework/packages/platform/auth/README.md` — document guard selection + redaction policy
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0057-token-bearer-jwt-guard.md`

#### Package skeleton (if type=package)

N/A

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/auth/config/auth.php`
- [ ] Keys (dot):
  - [ ] `auth.guards.default` = "session"
  - [ ] `auth.guards.session.enabled` = true
  - [ ] `auth.guards.token.enabled` = false
  - [ ] `auth.guards.token.header` = "Authorization"
  - [ ] `auth.guards.token.prefix` = "Bearer "
  - [ ] `auth.guards.token.ttl_seconds` = 86400
  - [ ] `auth.guards.token.revoke.enabled` = true
  - [ ] `auth.guards.jwt.enabled` = false
  - [ ] `auth.guards.jwt.issuer` = ""
  - [ ] `auth.guards.jwt.audience` = ""
  - [ ] `auth.guards.jwt.allowed_algs` = ["HS256"]
  - [ ] `auth.guards.jwt.secret_ref` = ""   # resolved via SecretsResolverInterface; raw secrets in config are forbidden
- [ ] Rules:
  - [ ] `framework/packages/platform/auth/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Auth\Auth\BearerTokenAuthenticator`
  - [ ] registers: `Coretsia\Auth\Auth\JwtAuthenticator` (optional)
  - [ ] guard selection in `AuthMiddleware` is deterministic and config-driven

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context writes (safe only):
  - [ ] `actor_id` (safe id only)
- [ ] Reset discipline:
  - [ ] any in-memory token store implements `ResetInterface` only if it caches per-UoW (optional)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `auth.authenticate` (attr: `driver=token|jwt`)
- [ ] Metrics:
  - [ ] reuse `auth.authenticate_total` / `auth.authenticate_failed_total` with `driver=token|jwt`
- [ ] Logs:
  - [ ] token invalid → warn/info without token dump (`hash/len` only)

#### Errors

- [ ] Exceptions introduced:
  - [ ] none new required (reuse existing unauthenticated/forbidden flow from 4.150.0)
- [ ] Mapping:
  - [ ] reuse existing mapper from 4.150.0

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] bearer token/jwt raw value
  - [ ] jwt claims that are PII/high-cardinality (esp. in metrics labels)
- [ ] Allowed:
  - [ ] `hash(token)` / `len(token)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `framework/packages/platform/auth/tests/Contract/ContextWriteSafetyTest.php` (reuse from 4.150.0 if applicable)
- [ ] If metrics/spans/logs exist → `framework/packages/platform/auth/tests/Contract/ObservabilityPolicyTest.php` (reuse; assert driver=token|jwt allowed)
- [ ] If redaction exists → `framework/packages/platform/auth/tests/Contract/RedactionDoesNotLeakTest.php` (reuse; assert token never appears raw)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] (optional) token parsing/hasher deterministic tests if implemented
- Contract:
  - [ ] `framework/packages/platform/auth/tests/Contract/NoSecretLoggingContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/auth/tests/Integration/BearerTokenInvalidReturns401Test.php`
  - [ ] `framework/packages/platform/auth/tests/Integration/BearerTokenValidSetsActorIdTest.php`
- Gates/Arch:
  - N/A

### DoD (MUST)

- [ ] No payload/token leakage (logs/spans/metrics)
- [ ] Deterministic guard selection rules documented in README
- [ ] Tests green
- [ ] ADR updated:
  - [ ] `docs/adr/ADR-0057-token-bearer-jwt-guard.md`
- [ ] Non-goals / out of scope
  - [ ] OIDC/JWKS discovery, enterprise SSO
  - [ ] Збереження plaintext токенів (заборонено)
  - [ ] Metric labels із token/jwt claims (заборонено)
- [ ] Коли увімкнено token/jwt guard, API може аутентифікуватись без session cookies, а 401/403 лишаються консистентні.
- [ ] When request has `Authorization: Bearer <token>` and token is valid, then `actor_id` is set and protected route passes; when invalid token, then 401 RFC7807 without token leakage.
- [ ] Phase-compat addendum (Secrets/Env/No-leak locks)
  - [ ] JWT secrets MUST be refs (MUST)
    - [ ] JWT verification MUST NOT accept raw secret material in config.
    - [ ] Any secret material MUST be provided as `secret_ref` and resolved via `Coretsia\Contracts\Secrets\SecretsResolverInterface`.
      - [ ] Example: `auth.guards.jwt.secret_ref = "env:JWT_SECRET"`.
  - [ ] Deterministic duration measurement (MUST)
    - [ ] Any `*_duration_ms` metric MUST use `Coretsia\Foundation\Time\Stopwatch` (int ms); floats forbidden.

---

### 4.170.0 coretsia/security — CSRF + Signed URLs (MUST) [IMPL]

---
type: package
phase: 4
epic_id: "4.170.0"
owner_path: "framework/packages/platform/security/"

package_id: "platform/security"
composer: "coretsia/platform-security"
kind: runtime
module_id: "platform.security"

goal: "Коли `platform.security` увімкнено, CSRF блокує unsafe requests без валідного токена, а signed-url verify працює детерміновано і без витоку секретів."
provides:
- "Central CSRF enforcement via PSR-15 middleware for unsafe methods"
- "Signed URLs service (HMAC + TTL) з SecretsResolver (без дублювання у контролерах)"
- "Consistent failure mapping через `error.mapper` (403/400 policy), RFC7807 responses, redaction-by-default"

tags_introduced: []
config_roots_introduced: ["security"]
artifacts_introduced: []

adr: docs/adr/ADR-0059-csrf-signed-urls.md
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required SSoT registries updated:
  - `docs/ssot/config-roots.md` includes `security` root (owner `platform/security`)
  - `docs/ssot/http-middleware-catalog.md` includes CsrfMiddleware entry in `http.middleware.app_pre`
- Tag usage rule (compile-time safe):
  - This package MUST use tag names as **string literals** (`'http.middleware.app_pre'`, `'error.mapper'`) per SSoT.
  - MUST NOT import tag constants from `platform/http`/`platform/errors` to preserve forbidden deps.

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - `security.*` — root is introduced by this package (must be compiled/loaded into global config)

- Required tags:
  - `http.middleware.app_pre` — middleware discovery slot exists in HTTP pipeline catalog
  - `error.mapper` — exception mapping discovery tag exists

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — optional (safe reads)
  - `Coretsia\Contracts\Security\CsrfTokenManagerInterface` — CSRF token manager port
  - `Coretsia\Contracts\Security\UrlSignerInterface` — signed url signer/verifier port
  - `Coretsia\Contracts\Session\SessionManagerInterface` — session-backed CSRF storage
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` — secret resolution by secret_ref
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface` — mapping via `error.mapper`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor` — error descriptor shape
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — spans (noop-safe)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics (noop-safe)
  - `Coretsia\Foundation\Context\ContextKeys` — context keys allowlist

- Runtime expectation (policy, NOT deps):
  - When enabled in presets/bundles: `platform/logging|tracing|metrics` provide implementations/noop-safe
  - Session available when `platform/session` enabled (CSRF stores state in session)
  - Secrets resolver bound when `platform/secrets` enabled (`security.signed_urls.secret_ref`)
  - Discovery/wiring uses tags: `http.middleware.app_pre`, `error.mapper`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `platform/session` (use contracts only)
- `platform/secrets` (use contracts only)
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Security\CsrfTokenManagerInterface`
  - `Coretsia\Contracts\Security\UrlSignerInterface`
  - `Coretsia\Contracts\Session\SessionManagerInterface`
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- HTTP:
  - middleware: `\Coretsia\Security\Http\Middleware\CsrfMiddleware::class`
  - middleware slots/tags: `http.middleware.app_pre` priority `100` meta `{"reason":"csrf before routing/controllers"}`
- Kernel hooks/tags:
  - N/A
- CLI:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/security/composer.json` — package definition
- [ ] `framework/packages/platform/security/src/Module/SecurityModule.php` — runtime module entry
- [ ] `framework/packages/platform/security/src/Provider/SecurityServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/security/src/Provider/SecurityServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/security/config/security.php` — config subtree (`security.*`)
- [ ] `framework/packages/platform/security/config/rules.php` — config shape enforcement
- [ ] `framework/packages/platform/security/README.md` — docs (Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/security/src/Csrf/CsrfTokenManager.php` — implements `CsrfTokenManagerInterface` (session-backed)
- [ ] `framework/packages/platform/security/src/Http/Middleware/CsrfMiddleware.php` — PSR-15 (unsafe methods)
- [ ] `framework/packages/platform/security/src/SignedUrl/UrlSigner.php` — implements `UrlSignerInterface` (HMAC + TTL; secret via SecretsResolver)
- [ ] `framework/packages/platform/security/src/Exceptions/SecurityProblemMapper.php` — implements `ExceptionMapperInterface` (tag `error.mapper`)
- [ ] `framework/packages/platform/security/src/Exception/CsrfMissingException.php` — errorCode `CORETSIA_CSRF_MISSING`
- [ ] `framework/packages/platform/security/src/Exception/CsrfInvalidException.php` — errorCode `CORETSIA_CSRF_INVALID`
- [ ] `framework/packages/platform/security/src/Exception/SignedUrlInvalidException.php` — errorCode `CORETSIA_SIGNED_URL_INVALID`
- [ ] `framework/packages/platform/security/src/Exception/SignedUrlExpiredException.php` — errorCode `CORETSIA_SIGNED_URL_EXPIRED`
- [ ] `framework/packages/platform/security/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — noop-safe contract
- [ ] `framework/packages/platform/security/tests/Contract/NoSecretLoggingContractTest.php` — redaction/no-secret contract
- [ ] `framework/packages/platform/security/tests/Unit/SignedUrlCanonicalizationDeterministicTest.php` — determinism proof
- [ ] `framework/packages/platform/security/tests/Integration/CsrfMiddlewareBlocksMissingTokenTest.php` — behavior proof
- [ ] `framework/packages/platform/security/tests/Integration/CsrfMiddlewareBlocksInvalidTokenTest.php` — behavior proof
- [ ] `framework/packages/platform/security/tests/Integration/SignedUrlVerifyDeterministicTest.php` — behavior+determinism proof
- [ ] `framework/packages/platform/security/tests/Integration/Http/CsrfEndToEndTest.php` — pipeline proof
- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` — fixture wiring for E2E

#### Modifies

- [ ] `framework/packages/platform/security/config/rules.php` — enforces updated/complete config shape (if expanded during impl)
- [ ] `framework/packages/platform/security/README.md` — include middleware slot/priority + override/disable in manual HTTP mode
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0059-csrf-signed-urls.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/security/composer.json`
- [ ] `framework/packages/platform/security/src/Module/SecurityModule.php` (runtime only)
- [ ] `framework/packages/platform/security/src/Provider/SecurityServiceProvider.php` (runtime only)
- [ ] `framework/packages/platform/security/config/security.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/platform/security/config/rules.php`
- [ ] `framework/packages/platform/security/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/security/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/security/config/security.php`
- [ ] Keys (dot):
  - [ ] `security.csrf.enabled` = true
  - [ ] `security.csrf.header_name` = "X-CSRF-Token"
  - [ ] `security.csrf.form_field_name` = "_csrf"
  - [ ] `security.csrf.methods` = ["POST","PUT","PATCH","DELETE"]
  - [ ] `security.signed_urls.enabled` = true
  - [ ] `security.signed_urls.secret_ref` = ""
  - [ ] `security.signed_urls.ttl_seconds` = 300
- [ ] Rules:
  - [ ] `framework/packages/platform/security/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (reuses existing tags/slots; package provides constants only)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Contracts\Security\CsrfTokenManagerInterface` → `Coretsia\Security\Csrf\CsrfTokenManager`
  - [ ] registers: `Coretsia\Contracts\Security\UrlSignerInterface` → `Coretsia\Security\SignedUrl\UrlSigner`
  - [ ] registers: `Coretsia\Security\Http\Middleware\CsrfMiddleware`
  - [ ] adds tag: `http.middleware.app_pre` priority `100` meta `{"reason":"csrf before routing/controllers"}`
  - [ ] registers mapper: `Coretsia\Security\Exceptions\SecurityProblemMapper` tag `error.mapper` priority `300`
- Middleware tag MUST be registered as:
  - tag: `'http.middleware.app_pre'` (string literal), priority `100`, meta keys MUST be stable + non-sensitive.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::ACTOR_ID` (optional; only for safe decisions/logs)
- [ ] Context writes (safe only):
  - [ ] N/A (CSRF token never written to ContextStore)
- Reset discipline:
  - N/A (security services should be stateless)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `security.csrf`
  - [ ] `security.signed_url.verify`
- [ ] Metrics:
  - `security.csrf_missing_total` (labels: `driver`, `outcome`)
  - `security.csrf_invalid_total` (labels: `driver`, `outcome`)
  - `security.signed_url_invalid_total` (labels: `driver`, `outcome`)
  - `security.signed_url_expired_total` (labels: `driver`, `outcome`)
- MUST NOT emit raw URL/query/token; allowed logs/metrics only with `hash(x)`/`len(x)` (implementation-side).
- [ ] Label normalization:
  - [ ] `via → driver` (if any upstream emits `via`)
- [ ] Logs:
  - [ ] CSRF fail → warn (no token)
  - [ ] signed url fail → info/warn (no URL dump; only hash/len)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Security\Exception\CsrfMissingException` — errorCode `CORETSIA_CSRF_MISSING`
  - [ ] `Coretsia\Security\Exception\CsrfInvalidException` — errorCode `CORETSIA_CSRF_INVALID`
  - [ ] `Coretsia\Security\Exception\SignedUrlInvalidException` — errorCode `CORETSIA_SIGNED_URL_INVALID`
  - [ ] `Coretsia\Security\Exception\SignedUrlExpiredException` — errorCode `CORETSIA_SIGNED_URL_EXPIRED`
- [ ] Mapping:
  - [ ] new mapper via tag `error.mapper` (`SecurityProblemMapper`)
- [ ] HTTP status hint policy (optional in ErrorDescriptor):
  - [ ] CSRF missing/invalid → `httpStatus=403` (policy; 400 allowed only if explicitly chosen)
  - [ ] signed url invalid/expired → `httpStatus=403`

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] CSRF token
  - [ ] signed-url secret
  - [ ] raw URL/query
- [ ] Allowed:
  - [ ] `hash(token)` / `len(token)` (token itself still secret-like)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` (N/A: no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` (N/A)
- [ ] If metrics/spans/logs exist → `framework/packages/platform/security/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → `framework/packages/platform/security/tests/Contract/NoSecretLoggingContractTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` (wiring)
- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger capture events for assertions (as needed by contract tests)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/security/tests/Unit/SignedUrlCanonicalizationDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/security/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/security/tests/Contract/NoSecretLoggingContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/security/tests/Integration/CsrfMiddlewareBlocksMissingTokenTest.php`
  - [ ] `framework/packages/platform/security/tests/Integration/CsrfMiddlewareBlocksInvalidTokenTest.php`
  - [ ] `framework/packages/platform/security/tests/Integration/SignedUrlVerifyDeterministicTest.php`
  - [ ] `framework/packages/platform/security/tests/Integration/Http/CsrfEndToEndTest.php`
- Gates/Arch:
  - N/A

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: signed-url canonicalization stable; no random in outputs
- [ ] Docs updated:
  - [ ] README includes middleware slot/priority + how to override/disable in http manual mode
  - [ ] ADR present: `docs/adr/ADR-0059-csrf-signed-urls.md`
- [ ] Non-goals / out of scope
  - [ ] CAPTCHA/anti-bot та WAF (інший шар)
  - [ ] Вивід токенів/секретів в дебаг-ендпоінтах (заборонено)
  - [ ] Пер-роут policy як окремий DSL (можливо пізніше)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
  - [ ] session available when `platform/session` enabled (CSRF stores state in session)
  - [ ] secrets resolver bound when `platform/secrets` enabled (signed url secret_ref)
- [ ] Коли `platform.security` увімкнено, CSRF блокує unsafe requests без валідного токена, а signed-url verify працює детерміновано і без витоку секретів.
- [ ] When POST request has missing/invalid CSRF token, then response is 403 RFC7807 and logs contain no token.
- [ ] Solves:
  - [ ] Central CSRF enforcement via PSR-15 middleware (unsafe methods)
  - [ ] Signed URLs service (HMAC + TTL) без дублювання у контролерах
  - [ ] Consistent failure mapping через `error.mapper` (403/400 policy)
- [ ] Non-goals / out of scope:
  - [ ] CAPTCHA/anti-bot та WAF (інший шар)
  - [ ] Вивід токенів/секретів в дебаг-ендпоінтах (заборонено)
  - [ ] Пер-роут policy як окремий DSL (можливо пізніше)

---

### 4.180.0 coretsia/encryption — Data encryption + key management (SHOULD) [IMPL]

---
type: package
phase: 4
epic_id: "4.180.0"
owner_path: "framework/packages/platform/encryption/"

package_id: "platform/encryption"
composer: "coretsia/platform-encryption"
kind: runtime
module_id: "platform.encryption"

goal: "Коли `platform.encryption` увімкнено, інші пакети можуть шифрувати/дешифрувати дані через encrypter port без витоку payload/keys і з deterministic failures."
provides:
- "DI-resolvable encrypter (sodium) + key selection без витоку key material"
- "Deterministic error codes + noop-safe path (NullEncrypter)"
- "Key resolution via `SecretsResolverInterface` (`secret_ref`)"

tags_introduced: []
config_roots_introduced: ["encryption"]
artifacts_introduced: []

adr: docs/adr/ADR-0060-data-encryption-key-management.md
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required SSoT registries updated:
  - `docs/ssot/config-roots.md` includes `encryption` root (owner `platform/encryption`)
- Dependency hygiene:
  - MUST use `SecretsResolverInterface` via contracts only; MUST NOT depend on `platform/secrets` compile-time.

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - `encryption.*` — root introduced by this package

- Required tags:
  - N/A

- Required contracts / ports:
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` — resolves key bytes by `secret_ref`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — spans (noop-safe)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics (noop-safe)
  - `Coretsia\Contracts\Runtime\ResetInterface` — only if stateful caches exist (optional)
  - `Coretsia\Contracts\Crypto\EncrypterInterface` — encrypter port (if exists/added)
  - `Coretsia\Contracts\Crypto\KeyManagerInterface` — key selection port (if exists/added)
  - `Psr\Log\LoggerInterface` — logging (redacted)

- Runtime expectation (policy, NOT deps):
  - `platform/logging|tracing|metrics` provide implementations/noop-safe
  - `platform/secrets` binds `SecretsResolverInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `platform/secrets` (use contracts only)
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface` (optional)
  - `Coretsia\Contracts\Crypto\EncrypterInterface` (if exists/added)
  - `Coretsia\Contracts\Crypto\KeyManagerInterface` (if exists/added)

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/Crypto/EncrypterInterface.php`
- [ ] `framework/packages/core/contracts/src/Crypto/KeyManagerInterface.php`

- [ ] `framework/packages/platform/encryption/composer.json` — package definition
- [ ] `framework/packages/platform/encryption/src/Module/EncryptionModule.php` — runtime module entry
- [ ] `framework/packages/platform/encryption/src/Provider/EncryptionServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/encryption/src/Provider/EncryptionServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/encryption/config/encryption.php` — config subtree (`encryption.*`)
- [ ] `framework/packages/platform/encryption/config/rules.php` — config shape enforcement
- [ ] `framework/packages/platform/encryption/README.md` — docs (Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/encryption/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — noop-safe contract
- [ ] `framework/packages/platform/encryption/src/Key/StaticKeyManager.php` — selects active key id (no key bytes in logs)
- [ ] `framework/packages/platform/encryption/src/Encryption/SodiumEncrypter.php` — ext-sodium implementation
- [ ] `framework/packages/platform/encryption/src/Encryption/NullEncrypter.php` — noop/dev
- [ ] `framework/packages/platform/encryption/src/Exception/EncryptionException.php` — deterministic error codes
- [ ] `framework/packages/platform/encryption/src/Provider/Tags.php` — optional constants (if needed)
- [ ] `framework/packages/platform/encryption/tests/Contract/EncryptDecryptRoundTripTest.php` — round-trip proof
- [ ] `framework/packages/platform/encryption/tests/Contract/WrongKeyThrowsDeterministicExceptionTest.php` — deterministic failure proof

#### Modifies

- [ ] `framework/packages/platform/encryption/README.md` — integration expectations + redaction policy
- [ ] `framework/packages/platform/encryption/config/rules.php` — shape completeness as impl evolves
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0060-data-encryption-key-management.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/encryption/composer.json`
- [ ] `framework/packages/platform/encryption/src/Module/EncryptionModule.php` (runtime only)
- [ ] `framework/packages/platform/encryption/src/Provider/EncryptionServiceProvider.php` (runtime only)
- [ ] `framework/packages/platform/encryption/config/encryption.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/platform/encryption/config/rules.php`
- [ ] `framework/packages/platform/encryption/README.md`
- [ ] `framework/packages/platform/encryption/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/encryption/config/encryption.php`
- [ ] Keys (dot):
  - [ ] `encryption.enabled` = false
  - [ ] `encryption.driver` = "null"   # "null"|"sodium"
  - [ ] `encryption.active_key_id` = "default"
  - [ ] `encryption.keys` = []         # map keyId => {secret_ref: string}
  - [ ] `encryption.aad.enabled` = false
- [ ] Rules:
  - [ ] `framework/packages/platform/encryption/config/rules.php` enforces shape

- Types (cemented):
  - `encryption.enabled`: bool
  - `encryption.driver`: enum `"null"|"sodium"`
  - `encryption.active_key_id`: non-empty string (when driver != "null")
  - `encryption.keys`: map<string, {secret_ref: string}> (keys sorted/validated by rules.php)
  - `encryption.aad.enabled`: bool

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Contracts\Crypto\EncrypterInterface` (Null or Sodium)
  - [ ] registers: `Coretsia\Contracts\Crypto\KeyManagerInterface` (`StaticKeyManager`)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (safe logs)
- [ ] Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] stateful caches (if any) implement `ResetInterface` + tag `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `crypto.encrypt`
  - [ ] `crypto.decrypt`
- [ ] Metrics:
  - [ ] `crypto.encrypt_total` (labels: `driver`, `outcome`)
  - [ ] `crypto.decrypt_total` (labels: `driver`, `outcome`)
  - [ ] `crypto.decrypt_failed_total` (labels: `driver`, `outcome`)
  - [ ] `crypto.duration_ms` (labels: `driver`, `operation`, `outcome`)
- [ ] Logs:
  - [ ] failures log key id only (no key bytes), no payload

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Encryption\Exception\EncryptionException` — errorCode(s) `CORETSIA_CRYPTO_ENCRYPT_FAILED`, `CORETSIA_CRYPTO_DECRYPT_FAILED`, `CORETSIA_CRYPTO_KEY_MISSING`
- [ ] Mapping:
  - [ ] reuse default mapper (no dupes) OR add mapper if explicit httpStatus hints are required
- HTTP status hint policy:
  - N/A (domain-level; mapping depends on caller)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] key material
  - [ ] resolved `secret_ref` value
  - [ ] plaintext/ciphertext payload
- [ ] Allowed:
  - [ ] key id (safe)
  - [ ] `len(payload)` (optional)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` (N/A)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` (only if reset is introduced)
- [ ] If metrics/spans/logs exist → `framework/packages/platform/encryption/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → covered by contract tests (no payload/keys leak)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger capture events for assertions (as needed)

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `framework/packages/platform/encryption/tests/Contract/EncryptDecryptRoundTripTest.php`
  - [ ] `framework/packages/platform/encryption/tests/Contract/WrongKeyThrowsDeterministicExceptionTest.php`
  - [ ] `framework/packages/platform/encryption/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - N/A
- Gates/Arch:
  - N/A

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] No secret/payload leakage
- [ ] Deterministic exception codes
- [ ] If contracts ports were added: ADR merged + contracts tests updated
- [ ] ADR present: `docs/adr/ADR-0060-data-encryption-key-management.md`
- [ ] Non-goals / out of scope
  - [ ] KMS integrations (Vault/AWS KMS) — Phase 6+
  - [ ] Виносити crypto vendor namespaces у contracts (заборонено)
  - [ ] Логувати payload (заборонено)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
  - [ ] `platform/secrets` binds SecretsResolverInterface
- [ ] Коли `platform.encryption` увімкнено, інші пакети можуть шифрувати/дешифрувати дані через encrypter port без витоку payload/keys і з deterministic failures.
- [ ] When decrypt uses wrong key id, then deterministic exception code is produced and no payload is logged.
- [ ] Solves:
  - [ ] Дати DI-resolvable encrypter (sodium) + key selection без витоку key material
  - [ ] Забезпечити deterministic error codes + noop-safe path (NullEncrypter)
  - [ ] Використання `SecretsResolverInterface` для key `secret_ref`
- [ ] Non-goals / out of scope:
  - [ ] KMS integrations (Vault/AWS KMS) — Phase 6+
  - [ ] Виносити crypto vendor namespaces у contracts (заборонено)
  - [ ] Логувати payload (заборонено)

---

### 4.190.0 platform/http — Rate limiting (identity-aware) (SHOULD) [IMPL]

---
type: package
phase: 4
epic_id: "4.190.0"
owner_path: "framework/packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "Коли `http.rate_limit.early.enabled=true` і `http.rate_limit.enabled=true`, запити обмежуються детерміновано і без витоку PII, а 429 відповіді мають Retry-After."
provides:
- "Rate-limit PSR-15 middleware with deterministic keys (actor_id/client_ip)"
- "429 + Retry-After policy, redaction/no-PII logs"
- "Reference in-memory store (без Redis) через contracts store interfaces"

tags_introduced: []
config_roots_introduced: []  # `http.*` root already exists in platform/http; epic extends it
artifacts_introduced: []

adr: docs/adr/ADR-0061-rate-limiting-identity-aware.md
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Contracts alignment (Phase 1, 1.160.0 RateLimit ports):
  - Key hashing MUST be delegated to `Coretsia\Contracts\RateLimit\RateLimitKeyHasherInterface`
  - Middleware MUST NOT invent a new hashing policy outside that port.

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - `framework/packages/platform/http/config/http.php` — config root exists; will be extended
  - `framework/packages/platform/http/config/rules.php` — rules exist; will be extended

- Required config roots/keys:
  - `http.rate_limit.*` — keys will be introduced/extended under existing `http.*` root

- Required tags:
  - `http.middleware.app_pre` — middleware discovery slot exists

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — optional
  - `Coretsia\Contracts\RateLimit\RateLimitStoreInterface` — store port
  - `Coretsia\Contracts\RateLimit\RateLimitDecision` — decision shape
  - `Coretsia\Contracts\RateLimit\RateLimitState` — state shape
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — spans (noop-safe)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics (noop-safe)
  - `Coretsia\Foundation\Context\ContextKeys` — context keys allowlist
  - PSR-15 / PSR-7 interfaces used by middleware

- Runtime expectation (policy, NOT deps):
  - `platform/logging|tracing|metrics` provide implementations/noop-safe
  - Discovery / wiring via tag `http.middleware.app_pre` (recommended: after auth, before csrf)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- `platform/observability`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\RateLimit\RateLimitStoreInterface`
  - `Coretsia\Contracts\RateLimit\RateLimitDecision`
  - `Coretsia\Contracts\RateLimit\RateLimitState`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- HTTP:
  - middleware: `\Coretsia\Http\Middleware\EarlyRateLimitMiddleware::class`
  - middleware slots/tags: `http.middleware.system_pre` priority `790` meta `{"reason":"after auth for actor_id, before csrf"}`
  - middleware: `\Coretsia\Http\Middleware\RateLimitMiddleware::class`
  - middleware slots/tags: `http.middleware.app_pre` priority `150` meta `{"reason":"after auth for actor_id, before csrf"}`
- CLI:
  - N/A
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/http/src/RateLimit/RateLimitKeyHasher.php` — implements `RateLimitKeyHasherInterface` (hash-only; no raw identity in logs)
- [ ] `framework/packages/platform/http/src/RateLimit/Algorithm/TokenBucketLimiter.php` — deterministic limiter
- [ ] `framework/packages/platform/http/src/RateLimit/Store/InMemoryRateLimitStore.php` — reference store
- [ ] `framework/packages/platform/http/src/RateLimit/RateLimitKeyBuilder.php` — deterministic key builder (actor_id|client_ip)
- [ ] `framework/packages/platform/http/src/Middleware/EarlyRateLimitMiddleware.php` — PSR-15 middleware, early anonymous/IP/infra rate-limit middleware:
  - [ ] MAY be registered ONLY into `http.middleware.system_pre`
  - [ ] MUST run before app identity/session/auth context is available
  - [ ] MUST use only pre-identity inputs (for example: client IP, forwarded client identity after proxy normalization, host, method, route-independent request characteristics)
  - [ ] MUST NOT depend on authenticated actor/tenant/session context
  - [ ] MUST remain low-cost and safe for early rejection
- [ ] `framework/packages/platform/http/src/Middleware/RateLimitMiddleware.php` — PSR-15 middleware, identity-aware rate-limit middleware:
  - [ ] MAY be registered ONLY into `http.middleware.app_pre`
  - [ ] MUST run after identity-enriching middleware needed for actor-aware decisions
  - [ ] MAY use actor/tenant/session-derived context when available
  - [ ] MUST NOT be registered into `http.middleware.system_pre|system|system_post`
- [ ] `framework/packages/platform/http/src/Exception/RateLimitedException.php` — errorCode `CORETSIA_HTTP_RATE_LIMITED`
- [ ] `framework/packages/platform/http/tests/Unit/RateLimitKeyBuildingDeterministicTest.php` — determinism proof
- [ ] `framework/packages/platform/http/tests/Integration/RateLimitReturns429WithRetryAfterTest.php` — behavior proof
- [ ] `framework/packages/platform/http/tests/Contract/NoopTracingAndMetricsNeverThrowInPipelineContractTest.php` — noop-safe proof (reuse)

#### Modifies

- [ ] `framework/packages/platform/http/config/http.php` — add `http.rate_limit.*` keys
- [ ] `framework/packages/platform/http/config/rules.php` — enforce shape for rate limit config
- [ ] `framework/packages/platform/http/README.md` — document middleware slot/priority + override instructions
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — wire middleware + tag (evidence point for DI wiring)
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0061-rate-limiting-identity-aware.md`

#### Package skeleton (if type=package)

N/A (package already exists; epic extends it)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.rate_limit.early.enabled` = false
  - [ ] `http.rate_limit.enabled` = false
  - [ ] `http.rate_limit.early.rules` = []
  - [ ] `http.rate_limit.identity.rules` = []
  - [ ] `http.rate_limit.early.response.headers_enabled` = true
  - [ ] `http.rate_limit.identity.response.headers_enabled` = true
- [ ] Rules:
  - [ ] `framework/packages/platform/http/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Http\Middleware\EarlyRateLimitMiddleware::class`
  - [ ] adds tag: `http.middleware.system_pre` priority `790` meta `{"reason":"after auth for actor_id, before csrf"}`
  - [ ] registers: `\Coretsia\Http\Middleware\RateLimitMiddleware::class`
  - [ ] adds tag: `http.middleware.app_pre` priority `150` meta `{"reason":"after auth for actor_id, before csrf"}`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::ACTOR_ID` (preferred identity)
  - [ ] `ContextKeys::CLIENT_IP` (fallback identity)
- [ ] Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] in-memory store implements `ResetInterface` only if it keeps per-UoW state
  - [ ] recommended: keep store process-wide; then no reset

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `http.rate_limit`
- [ ] Metrics:
  - [ ] `http.rate_limit_allowed_total` (labels: `driver`, `outcome`)
  - [ ] `http.rate_limit_blocked_total` (labels: `driver`, `outcome`)
  - [ ] `http.rate_limit_duration_ms` (labels: `driver`, `outcome`)
- [ ] Label normalization:
  - [ ] `via → driver` (if any upstream emits `via`)
- [ ] Logs:
  - [ ] blocked → warn/info without raw ip/token/path (allowed: `hash(identity)`)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Http\Exception\RateLimitedException` — errorCode `CORETSIA_HTTP_RATE_LIMITED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) OR add mapper in http package if needed
- [ ] HTTP status hint policy:
  - [ ] 429 with Retry-After

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw ip
  - [ ] raw auth identifiers
  - [ ] correlation/request id as limiter key/label
- [ ] Allowed:
  - [ ] `hash(identity)` in logs only

- Limiter key MUST be built as:
  - `hashed_identity = RateLimitKeyHasherInterface::hash(actor_id|client_ip)`
  - raw `actor_id`/`client_ip` MUST NOT appear in logs/metrics/exceptions

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` (N/A)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` (N/A unless reset added)
- [ ] If metrics/spans/logs exist → `framework/packages/platform/http/tests/Contract/NoopTracingAndMetricsNeverThrowInPipelineContractTest.php`
- [ ] If redaction exists → proven by integration/contract tests (no raw ip/path/token)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger capture events for assertions (as needed)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/http/tests/Unit/RateLimitKeyBuildingDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/http/tests/Contract/NoopTracingAndMetricsNeverThrowInPipelineContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/http/tests/Integration/RateLimitReturns429WithRetryAfterTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] deterministic key building
- [ ] no PII leakage
- [ ] README updated with middleware slot/priority + override instructions
- [ ] ADR present: `docs/adr/ADR-0061-rate-limiting-identity-aware.md`
- [ ] Solves:
  - [ ] Додати rate-limit middleware з deterministic ключами (actor_id або client_ip)
  - [ ] Забезпечити 429 з Retry-After без витоку PII
  - [ ] Дати reference in-memory store (без Redis) через contracts store
- [ ] Non-goals / out of scope:
  - [ ] Redis rate-limit store (Phase 6+ integration)
  - [ ] High-cardinality labels (raw path, user_id, correlation_id) — заборонено
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] Discovery / wiring is via tags:
  - [ ] `http.middleware.app_pre` (recommended placement after auth, before csrf)
- [ ] Коли `http.rate_limit.early.enabled=true` і `http.rate_limit.enabled=true`, запити обмежуються детерміновано і без витоку PII, а 429 відповіді мають Retry-After.
- [ ] When same actor exceeds rule limit, then response is 429 with deterministic headers and metrics incremented.

---

### 4.200.0 coretsia/hashing — Password hashing (SHOULD) [IMPL]

---
type: package
phase: 4
epic_id: "4.200.0"
owner_path: "framework/packages/platform/hashing/"

package_id: "platform/hashing"
composer: "coretsia/platform-hashing"
kind: runtime
module_id: "platform.hashing"

goal: "Auth може інжектити `PasswordHasherInterface` і виконувати verify/needsRehash без витоку секретів і з конфігованими алгоритмами."
provides:
- "Reference `PasswordHasherInterface` (argon2id/bcrypt) з конфігом і deterministic error codes"
- "`needsRehash()` policy is deterministic and test-proven"
- "Redaction-by-default: never log password/hash"

tags_introduced: []
config_roots_introduced: ["hashing"]
artifacts_introduced: []

adr: docs/adr/ADR-0062-password-hashing.md
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required SSoT registries updated:
  - `docs/ssot/config-roots.md` includes `hashing` root (owner `platform/hashing`)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - `hashing.*` — root introduced by this package

- Required tags:
  - N/A

- Required contracts / ports:
  - `Coretsia\Contracts\Auth\PasswordHasherInterface` — consumed by `platform/auth`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)
  - `Psr\Log\LoggerInterface`

- Runtime expectation (policy, NOT deps):
  - `platform/logging|tracing|metrics` provide implementations/noop-safe

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/auth`
- `platform/http`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Auth\PasswordHasherInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/hashing/composer.json` — package definition
- [ ] `framework/packages/platform/hashing/src/Module/HashingModule.php` — runtime module entry
- [ ] `framework/packages/platform/hashing/src/Provider/HashingServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/hashing/src/Provider/HashingServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/hashing/config/hashing.php` — config subtree (`hashing.*`)
- [ ] `framework/packages/platform/hashing/config/rules.php` — config shape enforcement
- [ ] `framework/packages/platform/hashing/README.md` — docs (Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/hashing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — noop-safe contract
- [ ] `framework/packages/platform/hashing/tests/Contract/NoSecretLoggingContractTest.php` — no password/hash leakage contract
- [ ] `framework/packages/platform/hashing/src/Password/Argon2idPasswordHasher.php`
- [ ] `framework/packages/platform/hashing/src/Password/BcryptPasswordHasher.php`
- [ ] `framework/packages/platform/hashing/src/Password/PasswordHasherManager.php`
- [ ] `framework/packages/platform/hashing/src/Exception/HashingException.php`
- [ ] `framework/packages/platform/hashing/tests/Unit/VerifyAndNeedsRehashPolicyTest.php` — policy proof

#### Modifies

- [ ] `framework/packages/platform/hashing/README.md` — document integration expectations for `platform/auth`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0062-password-hashing.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/hashing/composer.json`
- [ ] `framework/packages/platform/hashing/src/Module/HashingModule.php` (runtime only)
- [ ] `framework/packages/platform/hashing/src/Provider/HashingServiceProvider.php` (runtime only)
- [ ] `framework/packages/platform/hashing/config/hashing.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/platform/hashing/config/rules.php`
- [ ] `framework/packages/platform/hashing/README.md`
- [ ] `framework/packages/platform/hashing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/hashing/config/hashing.php`
- [ ] Keys (dot):
  - [ ] `hashing.password.driver` = "argon2id"
  - [ ] `hashing.password.argon2id.memory_cost` = 65536
  - [ ] `hashing.password.argon2id.time_cost` = 4
  - [ ] `hashing.password.argon2id.threads` = 2
  - [ ] `hashing.password.bcrypt.cost` = 12
- [ ] Rules:
  - [ ] `framework/packages/platform/hashing/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Contracts\Auth\PasswordHasherInterface` (manager default)
  - [ ] selects backend by `hashing.password.driver`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `hash.password_verify` (optional)
- [ ] Metrics:
  - `auth.password_verify_total` (labels: `driver`, `outcome`)
  - `auth.password_needs_rehash_total` (labels: `driver`, `outcome`)
  - `auth.password_hash_total` (labels: `driver`, `outcome`) (optional)
- [ ] Logs:
  - [ ] only algorithm + params; never password/hash

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Hashing\Exception\HashingException` — errorCode `CORETSIA_HASH_BACKEND_UNAVAILABLE`
- [ ] Mapping:
  - [ ] reuse default mapper

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] password
  - [ ] hash
- [ ] Allowed:
  - [ ] algorithm name only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` (N/A)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` (N/A)
- [ ] If metrics/spans/logs exist → `framework/packages/platform/hashing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → `framework/packages/platform/hashing/tests/Contract/NoSecretLoggingContractTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeLogger capture messages to assert no password/hash leakage (as needed)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/hashing/tests/Unit/VerifyAndNeedsRehashPolicyTest.php`
- Contract:
  - [ ] `framework/packages/platform/hashing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/hashing/tests/Contract/NoSecretLoggingContractTest.php`
- Integration:
  - N/A
- Gates/Arch:
  - N/A

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] password never logged
- [ ] needsRehash policy tested
- [ ] README documents integration expectations for `platform/auth`
- [ ] ADR present: `docs/adr/ADR-0062-password-hashing.md`
- [ ] Solves:
  - [ ] Надати reference `PasswordHasherInterface` (argon2id/bcrypt) з конфігом і deterministic error codes
  - [ ] Забезпечити, що `platform/auth` не реалізує hashing самостійно
- [ ] Non-goals / out of scope:
  - [ ] Зберігати/логувати паролі або хеші (заборонено)
  - [ ] KDF/pepper management (можливо пізніше через secrets)
- [ ] Usually present when enabled:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] Auth може інжектити `PasswordHasherInterface` і виконувати verify/needsRehash без витоку секретів і з конфігованими алгоритмами.
- [ ] When hashing driver is argon2id and parameters change, then `needsRehash()` returns true deterministically.

---

### 4.210.0 coretsia/lock — Lock factory + reference drivers (SHOULD) [IMPL]

---
type: package
phase: 4
epic_id: "4.210.0"
owner_path: "framework/packages/platform/lock/"

package_id: "platform/lock"
composer: "coretsia/platform-lock"
kind: runtime
module_id: "platform.lock"

goal: "Коли `platform.lock` увімкнено, scheduler/queue (та інші) можуть створювати lock-и через `LockFactoryInterface` без Redis і без витоку raw key."
provides:
- "Reference lock factory (`in_memory`, `file`) через contracts `LockFactoryInterface`"
- "Safe file locks via filesystem policy (no traversal/symlinks)"
- "Redaction helpers for lock keys (hash/len only)"

tags_introduced: []
config_roots_introduced: ["lock"]
artifacts_introduced: []

adr: docs/adr/ADR-0063-lock-factory-reference-drivers.md
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required SSoT registries updated:
  - `docs/ssot/config-roots.md` includes `lock` root (owner `platform/lock`)
- Filesystem integration policy (optional deps safe):
  - File lock driver MUST rely only on `Coretsia\Contracts\Filesystem\DiskInterface` (contracts).
  - This package MUST NOT require `platform/filesystem` as compile-time dependency.
  - If file driver selected but DiskInterface is not bound: deterministic failure `CORETSIA_LOCK_DISK_NOT_AVAILABLE` (no paths leaked).

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - `lock.*` — root introduced by this package

- Required tags:
  - N/A

- Required contracts / ports:
  - `Coretsia\Contracts\Lock\LockFactoryInterface`
  - `Coretsia\Contracts\Lock\LockInterface`
  - `Coretsia\Contracts\Filesystem\DiskInterface` (only if file driver uses Disk)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Log\LoggerInterface`

- Runtime expectation (policy, NOT deps):
  - `platform/logging|tracing|metrics` provide implementations/noop-safe

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Lock\LockFactoryInterface`
  - `Coretsia\Contracts\Lock\LockInterface`
  - `Coretsia\Contracts\Filesystem\DiskInterface` (if file driver uses Disk)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/lock/composer.json` — package definition
- [ ] `framework/packages/platform/lock/src/Module/LockModule.php` — runtime module entry
- [ ] `framework/packages/platform/lock/src/Provider/LockServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/lock/src/Provider/LockServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/lock/config/lock.php` — config subtree (`lock.*`)
- [ ] `framework/packages/platform/lock/config/rules.php` — config shape enforcement
- [ ] `framework/packages/platform/lock/README.md` — docs (Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/lock/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — noop-safe contract
- [ ] `framework/packages/platform/lock/src/Lock/InMemoryLock.php`
- [ ] `framework/packages/platform/lock/src/Lock/InMemoryLockFactory.php`
- [ ] `framework/packages/platform/lock/src/Lock/FileLock.php`
- [ ] `framework/packages/platform/lock/src/Lock/FileLockFactory.php` — uses filesystem SafePathJoiner/Disk
- [ ] `framework/packages/platform/lock/src/Security/Redaction.php` — `hashKey()`, `len()`
- [ ] `framework/packages/platform/lock/tests/Integration/InMemoryLockAcquireReleaseTest.php`
- [ ] `framework/packages/platform/lock/tests/Integration/FileLockAcquireReleaseTest.php`

#### Modifies

- [ ] `framework/packages/platform/lock/README.md` — driver options + redaction policy
- [ ] `framework/packages/platform/lock/config/rules.php` — shape completeness as impl evolves
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0063-lock-factory-reference-drivers.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/lock/composer.json`
- [ ] `framework/packages/platform/lock/src/Module/LockModule.php` (runtime only)
- [ ] `framework/packages/platform/lock/src/Provider/LockServiceProvider.php` (runtime only)
- [ ] `framework/packages/platform/lock/config/lock.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/platform/lock/config/rules.php`
- [ ] `framework/packages/platform/lock/README.md`
- [ ] `framework/packages/platform/lock/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/lock/config/lock.php`
- [ ] Keys (dot):
  - [ ] `lock.enabled` = true
  - [ ] `lock.default` = "in_memory"
  - [ ] `lock.file.path` = "skeleton/var/locks"
- [ ] Rules:
  - [ ] `framework/packages/platform/lock/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Contracts\Lock\LockFactoryInterface` (selects driver by config)
  - [ ] registers: `Coretsia\Contracts\Lock\LockInterface` instances created by factory

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `lock.acquire`
  - [ ] `lock.release`
- [ ] Metrics:
  - [ ] `lock.acquire_total` (labels: `driver`, `outcome`)
  - [ ] `lock.contention_total` (labels: `driver`, `outcome`)
  - [ ] `lock.acquire_duration_ms` (labels: `driver`, `outcome`)
- [ ] Logs:
  - [ ] never log raw key; only `hash(key)`

#### Errors

N/A

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw lock key
- [ ] Allowed:
  - [ ] `hash(key)` / `len(key)`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` (N/A)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` (N/A)
- [ ] If metrics/spans/logs exist → `framework/packages/platform/lock/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → proven by integration tests + logger assertions (no raw key)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeLogger capture messages to assert no raw key leakage (as needed)

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `framework/packages/platform/lock/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/lock/tests/Integration/InMemoryLockAcquireReleaseTest.php`
  - [ ] `framework/packages/platform/lock/tests/Integration/FileLockAcquireReleaseTest.php`
- Gates/Arch:
  - N/A

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] file lock path safety enforced
- [ ] key redaction enforced
- [ ] ADR present: `docs/adr/ADR-0063-lock-factory-reference-drivers.md`
- [ ] Solves:
  - [ ] Reference lock factory (`in_memory`, `file`) через contracts `LockFactoryInterface`
  - [ ] Безпечні file locks через filesystem policy (no traversal/symlinks)
  - [ ] No secret leakage for lock keys
- [ ] Non-goals / out of scope:
  - [ ] Redis lock (Phase 6+ integration)
  - [ ] Виносити lock keys у metric labels (заборонено)
- [ ] Usually present when enabled:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] Коли `platform.lock` увімкнено, scheduler/queue (та інші) можуть створювати lock-и через `LockFactoryInterface` без Redis і без витоку raw key.
- [ ] When two contenders try to acquire same lock, then only one acquires and contention metric increments.

---

### 4.220.0 coretsia/cache — PSR-16 cache + manager + reference stores (SHOULD) [IMPL]

---
type: package
phase: 4
epic_id: "4.220.0"
owner_path: "framework/packages/platform/cache/"

package_id: "platform/cache"
composer: "coretsia/platform-cache"
kind: runtime
module_id: "platform.cache"

goal: "Коли `platform.cache` увімкнено, інші пакети можуть використовувати PSR-16 cache без знання драйверів, а ключі не витікають у logs/metrics."
provides:
- "Unified PSR-16 cache layer for other packages (feature flags, inbox/outbox, rate limit stores, etc.)"
- "Reference stores (array/file/null) без integrations"
- "Key redaction policy (no raw keys), deterministic TTL semantics with guardrails"

tags_introduced: []
config_roots_introduced: ["cache"]
artifacts_introduced: []  # runtime data is not a deterministic artifact

adr: docs/adr/ADR-0064-cache-psr16-manager-stores.md
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required SSoT registries updated:
  - `docs/ssot/config-roots.md` includes `cache` root (owner `platform/cache`)
- Filesystem integration policy (optional deps safe):
  - File cache store MUST rely only on `Coretsia\Contracts\Filesystem\DiskInterface` (contracts).
  - Package MUST NOT require `platform/filesystem` as compile-time dependency.
  - If file store selected but DiskInterface is not bound: deterministic failure `CORETSIA_CACHE_DISK_NOT_AVAILABLE` (no paths leaked).

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - `cache.*` — root introduced by this package

- Required tags:
  - N/A

- Required contracts / ports:
  - `Psr\SimpleCache\CacheInterface` — PSR-16 port
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Log\LoggerInterface`

- Runtime expectation (policy, NOT deps):
  - `platform/logging|tracing|metrics` provide implementations/noop-safe

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\SimpleCache\CacheInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

- Artifacts:
  - reads/writes (runtime data, excluded from fingerprint): `skeleton/var/cache-data/*`
- CLI:
  - N/A
- HTTP:
  - N/A
- Kernel hooks/tags:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/cache/composer.json` — package definition
- [ ] `framework/packages/platform/cache/src/Module/CacheModule.php` — runtime module entry
- [ ] `framework/packages/platform/cache/src/Provider/CacheServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/cache/src/Provider/CacheServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/cache/config/cache.php` — config subtree (`cache.*`)
- [ ] `framework/packages/platform/cache/config/rules.php` — config shape enforcement
- [ ] `framework/packages/platform/cache/README.md` — docs (Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/cache/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — noop-safe contract
- [ ] `framework/packages/platform/cache/tests/Contract/NoSecretLoggingContractTest.php` — redaction/no-secret contract
- [ ] `framework/packages/platform/cache/src/Cache/CacheManager.php` — `store()` selects by config
- [ ] `framework/packages/platform/cache/src/Store/ArrayCacheStore.php` — PSR-16 reference
- [ ] `framework/packages/platform/cache/src/Store/FileCacheStore.php` — filesystem policy, path `skeleton/var/cache-data`
- [ ] `framework/packages/platform/cache/src/Store/NullCacheStore.php` — noop
- [ ] `framework/packages/platform/cache/src/Policy/TtlPolicy.php` — TTL guardrails
- [ ] `framework/packages/platform/cache/src/Security/Redaction.php` — key hashing
- [ ] `framework/packages/platform/cache/src/Exception/CacheStoreException.php` — deterministic error codes
- [ ] `framework/packages/platform/cache/tests/Integration/TtlSemanticsTest.php` — TTL proof
- [ ] `framework/packages/platform/cache/tests/Integration/FileStoreSafePathTest.php` — safe path proof

#### Modifies

- [ ] `framework/packages/platform/cache/README.md` — driver options + redaction policy
- [ ] `framework/packages/platform/cache/config/rules.php` — shape completeness as impl evolves
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0064-cache-psr16-manager-stores.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/cache/composer.json`
- [ ] `framework/packages/platform/cache/src/Module/CacheModule.php` (runtime only)
- [ ] `framework/packages/platform/cache/src/Provider/CacheServiceProvider.php` (runtime only)
- [ ] `framework/packages/platform/cache/config/cache.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/platform/cache/config/rules.php`
- [ ] `framework/packages/platform/cache/README.md`
- [ ] `framework/packages/platform/cache/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/cache/config/cache.php`
- [ ] Keys (dot):
  - [ ] `cache.enabled` = true
  - [ ] `cache.default` = "array"
  - [ ] `cache.default_ttl_seconds` = 300
  - [ ] `cache.max_ttl_seconds` = 86400
  - [ ] `cache.file.path` = "skeleton/var/cache-data"
- [ ] Rules:
  - [ ] `framework/packages/platform/cache/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Psr\SimpleCache\CacheInterface` (manager default or configured store)
  - [ ] selects store driver by `cache.default`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `skeleton/var/cache-data/*` (runtime data; excluded from fingerprint; not a deterministic artifact)
- [ ] Reads:
  - [ ] store reads within same runtime policy (TTL + safe path)

- Runtime store path `skeleton/var/cache-data/*`:
  - MUST be excluded from deterministic fingerprint inputs.
  - MUST NOT be treated as Coretsia artifact (no `_meta` envelope).

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `cache.get`
  - [ ] `cache.set`
  - [ ] `cache.delete`
- [ ] Metrics:
  - [ ] `cache.hit_total` (labels: `driver`, `outcome`)
  - [ ] `cache.miss_total` (labels: `driver`, `outcome`)
  - [ ] `cache.op_total` (labels: `driver`, `operation`, `outcome`)
  - [ ] `cache.op_duration_ms` (labels: `driver`, `operation`, `outcome`)
- [ ] Label normalization:
  - [ ] `op → operation`, `via → driver` (if any upstream emits legacy labels)
- [ ] Logs:
  - [ ] no raw keys; only `hash(key)` and operation outcome

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Cache\Exception\CacheStoreException` — errorCode(s) `CORETSIA_CACHE_IO_FAILED`, `CORETSIA_CACHE_TTL_INVALID`
- [ ] Mapping:
  - [ ] reuse default mapper

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw cache keys
  - [ ] cached values in logs
- [ ] Allowed:
  - [ ] `hash(key)` / `len(key)`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` (N/A)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` (N/A)
- [ ] If metrics/spans/logs exist → `framework/packages/platform/cache/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → `framework/packages/platform/cache/tests/Contract/NoSecretLoggingContractTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger capture events for assertions (as needed)

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - [ ] `framework/packages/platform/cache/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/cache/tests/Contract/NoSecretLoggingContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/cache/tests/Integration/TtlSemanticsTest.php`
  - [ ] `framework/packages/platform/cache/tests/Integration/FileStoreSafePathTest.php`
- Gates/Arch:
  - N/A

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] switching driver works via config
- [ ] no raw keys in logs
- [ ] deterministic behavior for TTL semantics proven by tests
- [ ] README documents driver options + redaction policy
- [ ] ADR present: `docs/adr/ADR-0064-cache-psr16-manager-stores.md`
- [ ] Solves:
  - [ ] Єдиний cache layer (PSR-16) для інших пакетів (feature flags, inbox/outbox, rate limit stores тощо)
  - [ ] Reference stores (array/file/null) без integrations
  - [ ] Redaction для cache keys (no raw keys in logs)
- [ ] Non-goals / out of scope:
  - [ ] Redis/APCu stores (Phase 6+ integrations)
  - [ ] High-cardinality labels із key/value (заборонено)
- [ ] Usually present when enabled:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] Коли `platform.cache` увімкнено, інші пакети можуть використовувати PSR-16 cache без знання драйверів, а ключі не витікають у logs/metrics.
- [ ] When using file store with TTL, then expired values are not returned and operations are observable via noop-safe meter/tracer.

---

### 4.230.0 Core Kernel + Foundation Hot-path Optimization (MUST) [IMPL+TOOLING]

---
type: tools
phase: 4
epic_id: "4.230.0"
owner_path: "framework/tools/benchmarks/core/"

goal: "Оптимізувати найгарячіші internal hot paths у core/foundation та core/kernel без зміни семантики, а факт виграшу довести стабільними microbenchmarks і downstream HTTP benchmarks."
provides:
- "Core microbenchmark suite for container, TagRegistry, StableJsonEncoder, config/runtime hot paths"
- "Concrete optimization work in foundation/kernel hot paths with behavior-lock tests"
- "Performance regression thresholds tied to deterministic benchmark methodology"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/tags.md"
- "docs/ssot/config-roots.md"
- "docs/ssot/observability.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

> Everything that MUST already exist (files/keys/tags/contracts/artifacts).

- Epic prerequisites:
  - 1.200.0 — Foundation DI container + TagRegistry + reset orchestration baseline exists
  - 1.280.0 — KernelRuntime exists
  - 1.320.0 — ConfigKernel/config merge runtime exists
  - 1.340.0 — compiled container artifact exists
  - 1.460.0 — CLI performance gate methodology exists
  - 3.200.0 — HTTP benchmark harness exists and can validate downstream effect

- Required deliverables (exact paths):
  - `framework/packages/core/foundation/src/` — Foundation hot-path implementation root
  - `framework/packages/core/kernel/src/` — Kernel hot-path implementation root
  - `framework/packages/core/foundation/tests/` — existing behavior contracts that MUST remain green
  - `framework/packages/core/kernel/tests/` — existing behavior contracts that MUST remain green

- Required config roots/keys:
  - `foundation.*` — foundation runtime config
  - `kernel.*` — kernel runtime/config compile behavior
  - `app.id` — artifact/cache isolation

- Required tags:
  - `kernel.reset`
  - `kernel.stateful`
  - `kernel.hook.before_uow`
  - `kernel.hook.after_uow`

- Required contracts / ports:
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Psr\Log\LoggerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (tools epic that modifies existing core packages)

Forbidden:

- semantic changes to public contracts as part of optimization
- weakening security-sensitive hashing/policy under the pretext of performance
- adding non-deterministic caches keyed by wall-clock/random data

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Serialization\StableJsonEncoder`
  - `Coretsia\Foundation\Tag\TagRegistry`

### Entry points / integration points (MUST)

- CLI:
  - `composer benchmark:core` → `framework/tools/benchmarks/core/run.php`
  - `composer benchmark:core:gate` → `framework/tools/gates/core_performance_gate.php`

- HTTP:
  - N/A

- Kernel hooks/tags:
  - optimized services that remain stateful MUST still implement `ResetInterface` and remain reset-discoverable

- Other runtime discovery / integration tags:
  - N/A

- Artifacts:
  - reads: `framework/tools/benchmarks/core/core.baseline.json`
  - writes: `framework/tools/benchmarks/core/core.report.json`

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/benchmarks/core/run.php` — core microbenchmark runner
- [ ] `framework/tools/benchmarks/core/CoreBenchmarkConfig.php` — scenario list + methodology
- [ ] `framework/tools/benchmarks/core/core.baseline.json` — baseline for pinned runner
- [ ] `framework/tools/gates/core_performance_gate.php` — regression comparator
- [ ] `framework/tools/tests/Integration/Benchmarks/CoreBenchmarkHarnessTest.php`
- [ ] `framework/tools/tests/Integration/Gates/CorePerformanceGateTest.php`
- [ ] `docs/architecture/performance-core.md` — hot-path inventory + optimization rules

#### Modifies

- [ ] `framework/packages/core/foundation/src/` — optimize container/registry/serialization hot paths without changing output semantics
- [ ] `framework/packages/core/kernel/src/` — optimize runtime/config/artifact hot paths without changing output semantics
- [ ] `framework/packages/core/foundation/tests/` — add behavior-lock proofs where optimization could drift semantics
- [ ] `framework/packages/core/kernel/tests/` — add behavior-lock proofs where optimization could drift semantics
- [ ] `framework/composer.json` — add scripts:
  - [ ] `benchmark:core`
  - [ ] `benchmark:core:gate`
- [ ] `composer.json` — add mirror scripts delegating to `framework/`
- [ ] `.github/workflows/ci.yml` — add dedicated core benchmark job
- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_CORE_PERFORMANCE_DEGRADED`
  - [ ] `CORETSIA_CORE_PERFORMANCE_RUN_FAILED`

#### Package skeleton (if type=package)

N/A

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/tools/benchmarks/core/CoreBenchmarkConfig.php`
- [ ] Keys (dot):
  - [ ] `benchmark.core.scenarios.container_get` = true
  - [ ] `benchmark.core.scenarios.tag_registry_all` = true
  - [ ] `benchmark.core.scenarios.stable_json_encode` = true
  - [ ] `benchmark.core.scenarios.kernel_boot_minimal` = true
  - [ ] `benchmark.core.threshold_multiplier` = 1.10
- [ ] Rules:
  - [ ] every scenario MUST preserve identical public output bytes/ordering vs pre-optimization behavior

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- ServiceProvider wiring evidence:
  - N/A

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `framework/tools/benchmarks/core/core.report.json` (deterministic bytes)
- [ ] Reads:
  - [ ] validates baseline/report shape before comparison

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads:
  - N/A
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] any newly added caches/buffers in runtime services MUST either be immutable or reset-safe
  - [ ] if mutable and long-running relevant, service MUST implement `ResetInterface`
  - [ ] such services MUST remain discoverable via Foundation reset policy

#### Observability (policy-compliant)

- [ ] Logs:
  - [ ] benchmark/gate output MUST contain only scenario ids, medians, thresholds, outcome
  - [ ] MUST NOT include config dumps, raw payloads, or absolute paths

#### Errors

- Exceptions introduced:
  - N/A
- [ ] Mapping:
  - [ ] deterministic tool exit codes only

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] secrets
  - [ ] raw payloads
  - [ ] absolute paths
- [ ] Allowed:
  - [ ] safe scenario ids
  - [ ] counts
  - [ ] timing numbers

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If `kernel.reset` used → existing and new reset tests remain green after optimization
- [ ] If logs exist → harness/gate tests assert safe output only

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeTracer
  - [ ] FakeMetrics
  - [ ] FakeLogger

### Tests (MUST)

- Unit:
  - [ ] `framework/tools/tests/Unit/Benchmarks/CoreBenchmarkConfigTest.php`
- Contract:
  - [ ] `framework/packages/core/foundation/tests/Contract/OptimizationPreservesDeterministicOutputContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/OptimizationPreservesArtifactBytesContractTest.php`
- Integration:
  - [ ] `framework/tools/tests/Integration/Benchmarks/CoreBenchmarkHarnessTest.php`
  - [ ] `framework/tools/tests/Integration/Gates/CorePerformanceGateTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] public behavior is unchanged
  - [ ] artifact bytes/order stay stable
  - [ ] benchmark reports are deterministic
- [ ] Downstream proof:
  - [ ] `3.200.0` HTTP benchmark shows neutral or improved results for relevant scenarios
- [ ] Docs updated:
  - [ ] `docs/architecture/performance-core.md`

---

### 4.240.0 Performance Gates for Database and Filesystem (SHOULD) [TOOLING]

---
type: tools
phase: 4
epic_id: "4.240.0"
owner_path: "framework/tools/gates/"

goal: "Додати pinned-runner performance gates для типових database та filesystem операцій, щоб бачити регресії від змін драйверів, query/path shaping або IO policy до виходу релізу."
provides:
- "Reference DB benchmarks for minimal round-trip operations"
- "Reference filesystem benchmarks for read/write/stat operations"
- "Dedicated gate and baseline policy aligned with CLI/HTTP/core performance gates"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/config-roots.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.50.0 — deterministic file IO policy exists
  - 1.460.0 — CLI performance gate methodology exists
  - 4.70.0 — platform/database exists
  - 4.90.0 — migrations exist
  - 4.230.0 — core performance gate exists as same-family methodology

- Required deliverables (exact paths):
  - `framework/packages/platform/database/` — database runtime under test
  - `framework/packages/platform/migrations/` — schema setup for DB benchmark fixture
  - `framework/tools/spikes/_support/` — canonical deterministic tooling helpers

- Required config roots/keys:
  - `database.default`
  - `database.connections.*`
  - `app.id`

- Required tags:
  - none

- Required contracts / ports:
  - `PDO`
  - `Psr\Log\LoggerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (tools-only gate/harness)

Forbidden:

- live external database services in CI baseline job
- non-deterministic temporary path handling
- printing raw SQL text or absolute file paths in gate output

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`

### Entry points / integration points (MUST)

- CLI:
  - `composer benchmark:io`
  - `composer benchmark:io:gate`

- HTTP:
  - N/A

- Kernel hooks/tags:
  - N/A

- Other runtime discovery / integration tags:
  - N/A

- Artifacts:
  - reads: `framework/tools/config/io_performance.baseline.json`
  - writes: `framework/tools/config/io_performance.report.json`

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/config/io_performance.php` — benchmark scenarios + thresholds
- [ ] `framework/tools/config/io_performance.baseline.json` — pinned-runner baseline
- [ ] `framework/tools/gates/database_filesystem_performance_gate.php` — comparator gate
- [ ] `framework/tools/benchmarks/io/run.php` — benchmark runner
- [ ] `framework/tools/tests/Integration/Gates/DatabaseFilesystemPerformanceGateTest.php`
- [ ] `framework/tools/tests/Integration/Benchmarks/IoBenchmarkHarnessTest.php`
- [ ] `framework/tools/tests/Fixtures/IoBench/` — SQLite DB + temp fs fixture root
- [ ] `docs/ops/performance-db-fs.md` — methodology + safe interpretation guide

#### Modifies

- [ ] `framework/composer.json` — add scripts:
  - [ ] `benchmark:io`
  - [ ] `benchmark:io:gate`
- [ ] `composer.json` — add mirror scripts delegating to `framework/`
- [ ] `.github/workflows/ci.yml` — add dedicated DB/FS benchmark job
- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_DBFS_PERFORMANCE_DEGRADED`
  - [ ] `CORETSIA_DBFS_PERFORMANCE_RUN_FAILED`
  - [ ] `CORETSIA_DBFS_BENCHMARK_ENV_MISMATCH`

#### Package skeleton (if type=package)

N/A

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/tools/config/io_performance.php`
- [ ] Keys (dot):
  - [ ] `benchmark.io.database.driver` = `sqlite`
  - [ ] `benchmark.io.database.scenarios.select_one` = true
  - [ ] `benchmark.io.database.scenarios.insert_and_rollback` = true
  - [ ] `benchmark.io.filesystem.scenarios.read_small_file` = true
  - [ ] `benchmark.io.filesystem.scenarios.write_small_file` = true
  - [ ] `benchmark.io.filesystem.scenarios.stat_existing_file` = true
  - [ ] `benchmark.io.threshold_multiplier` = 1.20
- [ ] Rules:
  - [ ] DB benchmark fixture MUST be local SQLite-first in CI
  - [ ] FS benchmark fixture MUST use repo-local temp root with normalized paths only

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `framework/tools/config/io_performance.report.json` (deterministic bytes)
- [ ] Reads:
  - [ ] validates report/baseline schema before compare

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads:
  - N/A
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] temporary DB/filesystem fixtures MUST be recreated or cleaned deterministically between measured runs

#### Observability (policy-compliant)

- [ ] Logs:
  - [ ] output only operation ids, medians, threshold, outcome
  - [ ] MUST NOT print raw SQL or absolute paths

#### Errors

- Exceptions introduced:
  - N/A
- [ ] Mapping:
  - [ ] deterministic tool exit codes only

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw SQL
  - [ ] DSNs with credentials
  - [ ] absolute paths
- [ ] Allowed:
  - [ ] driver id
  - [ ] safe operation ids
  - [ ] timing numbers

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If logs exist → gate/harness tests assert no raw SQL and no absolute paths
- [ ] If redaction exists → `framework/tools/tests/Integration/Gates/DatabaseFilesystemPerformanceGateTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture root:
  - [ ] `framework/tools/tests/Fixtures/IoBench/`
- [ ] Fake adapters:
  - [ ] FakeLogger

### Tests (MUST)

- Unit:
  - [ ] `framework/tools/tests/Unit/Benchmarks/IoPerformanceConfigTest.php`
- Contract:
  - [ ] `framework/tools/tests/Contract/Benchmarks/IoBenchmarkOutputDoesNotLeakTest.php`
- Integration:
  - [ ] `framework/tools/tests/Integration/Benchmarks/IoBenchmarkHarnessTest.php`
  - [ ] `framework/tools/tests/Integration/Gates/DatabaseFilesystemPerformanceGateTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] fixture setup/cleanup is deterministic
  - [ ] baseline/report shapes are stable
- [ ] Gate runs only on pinned environment for fail/pass source of truth
- [ ] Docs updated:
  - [ ] `docs/ops/performance-db-fs.md`
