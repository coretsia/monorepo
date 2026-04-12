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

## PHASE 5 — RELEASE: hybrid (Non-product doc)

### 5.10.0 coretsia/secrets — Secrets resolver (platform implementation) (MUST) [IMPL]

---
type: package
phase: 5
epic_id: "5.10.0"
owner_path: "framework/packages/platform/secrets/"

package_id: "platform/secrets"
composer: "coretsia/platform-secrets"
kind: runtime
module_id: "platform.secrets"

goal: "Будь-який пакет може викликати SecretsResolverInterface->resolve(ref) і отримати string|null детерміновано та без витоку ref/values у logs/spans/metrics, включно з noop режимом."
provides:
- "Reference SecretsResolver drivers: null (noop) + env (env-map)"
- "Deterministic secret_ref parsing (env:KEY) без експозиції значень"
- "Security-redaction helpers (hash/len) + базова noop-safe observability"

tags_introduced: []
config_roots_introduced: ["secrets"]
artifacts_introduced: []

adr: "docs/adr/ADR-0053-secrets-resolver-platform.md"
ssot_refs:
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/metrics-policy.md"
- "docs/ssot/config-and-env.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - (pre-existing) `core/contracts` — contains `Coretsia\Contracts\Secrets\SecretsResolverInterface` and observability ports used here.
  - (pre-existing) `core/foundation` — stable context APIs (optional usage) + container/wiring base.

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/src/Env/EnvRepositoryInterface.php` — env reads (policy-compliant).
  - `framework/packages/core/contracts/src/Secrets/SecretsResolverInterface.php` — contract port consumed by this package.
  - `framework/packages/core/contracts/src/Observability/Tracing/TracerPortInterface.php` — tracing (noop-safe).
  - `framework/packages/core/contracts/src/Observability/Metrics/MeterPortInterface.php` — metrics (noop-safe).
  - (optional) `framework/packages/core/contracts/src/Context/ContextAccessorInterface.php` — context reads (signature `get(string $key): mixed`, no default).

- Required config roots/keys:
  - `secrets` / `secrets.*` — this epic introduces and owns.

- Required tags:
  - `kernel.reset` — only if stateful resolvers appear later (NOT used by reference drivers in this epic).

- Required contracts / ports:
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` — main port.
  - `Psr\Log\LoggerInterface` — logging (policy-compliant redaction).
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — tracing.
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics.

- Rails reused (NO DEAD WEIGHT) (MUST):
  - Never print ref raw; only `hash(ref)` / `len(ref)`.
  - Never print resolved secret value (even in exceptions).
  - Determinism: same ref + same env map ⇒ same result; observability events have stable attrs (no random ids; no embedded timestamps).

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http`
- `platform/cli`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/secrets/src/Module/SecretsModule.php` — runtime module entry.
- [ ] `framework/packages/platform/secrets/src/Provider/SecretsServiceProvider.php` — DI wiring.
- [ ] `framework/packages/platform/secrets/src/Provider/SecretsServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/secrets/src/Provider/Tags.php` — constants (avoid typos).
- [ ] `framework/packages/platform/secrets/config/secrets.php` — config subtree `secrets` (no repeated root).
- [ ] `framework/packages/platform/secrets/config/rules.php` — config shape rules.
- [ ] `framework/packages/platform/secrets/README.md` — Observability / Errors / Security-Redaction.
- [ ] `framework/packages/platform/secrets/src/Secrets/NullSecretsResolver.php` — noop resolver (always null).
- [ ] `framework/packages/platform/secrets/src/Secrets/EnvSecretsResolver.php` — env-map resolver (never logs values).
- [ ] `framework/packages/platform/secrets/src/Secrets/SecretRefParser.php` — deterministic parsing (`env:KEY`).
- [ ] `framework/packages/platform/secrets/src/Exception/SecretsResolutionException.php` — deterministic errors.
- [ ] `framework/packages/platform/secrets/src/Security/Redaction.php` — `hash(string): string`, `len(string): int`.

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0053-secrets-resolver-platform.md`
- [ ] `docs/ssot/config-roots.md` — add root row for `secrets` (owner `platform/secrets`, defaults `framework/packages/platform/secrets/config/secrets.php`, rules `framework/packages/platform/secrets/config/rules.php`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/secrets/composer.json`
- [ ] `framework/packages/platform/secrets/src/Module/SecretsModule.php`
- [ ] `framework/packages/platform/secrets/src/Provider/SecretsServiceProvider.php`
- [ ] `framework/packages/platform/secrets/config/secrets.php`
- [ ] `framework/packages/platform/secrets/config/rules.php`
- [ ] `framework/packages/platform/secrets/README.md`
- [ ] `framework/packages/platform/secrets/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/secrets/config/secrets.php`
- [ ] Keys (dot):
  - [ ] `secrets.enabled` = true
  - [ ] `secrets.driver` = "null"            # "null" | "env"
  - [ ] `secrets.env_map` = []               # map refKey => envKey (values are env key names, not secrets)
  - [ ] `secrets.redaction.enabled` = true
- [ ] Rules:
  - [ ] `framework/packages/platform/secrets/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/secrets/src/Provider/Tags.php` (constants to avoid typos)
  - [ ] constant(s): `SECRETS_DRIVER` (optional, if you select by tag) OR none if selected by config
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Contracts\Secrets\SecretsResolverInterface` → selected driver (config-driven)
  - [ ] registers: `Coretsia\Secrets\Secrets\NullSecretsResolver`
  - [ ] registers: `Coretsia\Secrets\Secrets\EnvSecretsResolver`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (optional; safe log fields only)
- [ ] Context writes (safe only):
  - [ ] none (MUST NOT write secret-derived values)
- Reset discipline:
  - N/A (reference drivers are stateless; future stateful drivers must implement `ResetInterface` + tag `kernel.reset`)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `secrets.resolve` (attrs allowed: `driver`, `outcome`; NEVER raw `ref`)
- [ ] Metrics:
  - [ ] `secrets.resolve_total` (labels: `driver`, `outcome`)
  - [ ] `secrets.resolve_duration_ms` (labels: `driver`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`
- [ ] Logs:
  - [ ] invalid ref format → warn with redaction (`hash/len` only)
  - [ ] never log resolved values

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Secrets\Exception\SecretsResolutionException` — errorCode `CORETSIA_SECRETS_INVALID_REF`
- [ ] Mapping:
  - [ ] reuse existing default mapper (no dupes) unless a dedicated mapper becomes necessary later

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] secret values; env values; resolved bytes
  - [ ] raw secret_ref (treat as sensitive metadata)
- [ ] Allowed:
  - [ ] `hash(ref)` / `len(ref)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `framework/packages/platform/secrets/tests/Contract/ObservabilityPolicyTest.php`
- [ ] If redaction exists → `framework/packages/platform/secrets/tests/Contract/RedactionDoesNotLeakTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/secrets/tests/Unit/SecretRefParserTest.php`
- Contract:
  - [ ] `framework/packages/platform/secrets/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/secrets/tests/Contract/SecretsNeverLeakResolvedValuesContractTest.php`
  - [ ] `framework/packages/platform/secrets/tests/Contract/SecretRefRedactionIsStableContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/secrets/tests/Integration/EnvSecretsResolverDoesNotLeakValuesTest.php`
  - [ ] `framework/packages/platform/secrets/tests/Integration/NullResolverAlwaysReturnsNullTest.php`
  - [ ] `framework/packages/platform/secrets/tests/Integration/EnvSecretsResolverDeterministicOutcomeTest.php`
- Gates/Arch:
  - N/A

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: same ref inputs produce same outputs; no random ids in logs/spans
- [ ] Docs updated:
  - [ ] `framework/packages/platform/secrets/README.md`
  - [ ] `docs/adr/ADR-0053-secrets-resolver-platform.md`
- [ ] Ref parsing errors deterministic (`CORETSIA_SECRETS_INVALID_REF`) and contain no raw ref or values
- [ ] No secret values appear in any outputs (tests prove)
- [ ] Non-goals / out of scope
  - [ ] Інтеграції Vault/AWS/GCP (це Phase 6+ integrations)
  - [ ] Кешування секретів між UoW як stateful оптимізація (тільки якщо буде реальна потреба)
  - [ ] Будь-які CLI/HTTP “показати значення секрету”
- [ ] Secret refs MUST follow Phase 0/1 redaction discipline:
  - [ ] Never print ref raw; use `hash(ref)`/`len(ref)` only.
  - [ ] Never print resolved secret value, even in exceptions.
- [ ] Determinism expectations:
  - [ ] resolving the same ref with the same env map must be deterministic (same result, no randomness)
  - [ ] logs/spans/metrics must be stable (no random ids; no timestamps embedded)
- [ ] Коли будь-який пакет викликає `SecretsResolverInterface->resolve($ref)`, він отримує значення або `null` без витоку значень у logs/spans/metrics та без падінь у noop режимі.
- [ ] When `security.signed_urls.secret_ref="env:SIGNED_URL_SECRET"` and env key exists, then signed-url verification works without printing secret value anywhere.
- [ ] Phase-compat addendum (PRELUDE/PHASE1 locks)
  - [ ] Env policy wiring (MUST)
    - [ ] `platform/secrets` MUST read environment only via `Coretsia\Contracts\Env\EnvRepositoryInterface` (Phase 1 env semantics: missing vs empty distinct).
    - [ ] Direct `getenv()` MUST NOT be used in resolvers (violates EnvPolicy/Bootstrap semantics & testability).
  - [ ] Deterministic duration measurement (MUST)
    - [ ] Any `*_duration_ms` metric MUST be measured with `Coretsia\Foundation\Time\Stopwatch` (int ms).  
      `microtime(true)` / floats are forbidden.
  - [ ] SSoT registries (MUST)
    - [ ] This epic introduces config root `secrets` ⇒ MUST update `docs/ssot/config-roots.md` (owner row for `secrets`).

---

### 5.20.0 coretsia/auth — Authorization engines: RBAC + REBAC (OPTIONAL) [IMPL]

---
type: package
phase: 5
epic_id: "5.20.0"
owner_path: "framework/packages/platform/auth/"

package_id: "platform/auth"
composer: "coretsia/platform-auth"
kind: runtime
module_id: "platform.auth"

goal: "Коли auth.authorization.engine='rebac', авторизація працює детерміновано й без граф-експлозій завдяки max_depth; рішення стабільне між запусками."
provides:
- "REBAC engine як drop-in реалізація AuthorizationInterface"
- "Deterministic graph traversal + safety rail max_depth"
- "Config-driven switch RBAC vs REBAC"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-0058-authorization-engines-rbac-rebac.md"
ssot_refs:
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/metrics-policy.md"
- "docs/ssot/config-and-env.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `4.150.0` — `RbacAuthorization` exists + base auth wiring exists.
  - `4.130.0` — `Coretsia\Contracts\Auth\AuthorizationInterface` exists.

- Required deliverables (exact paths):
  - `framework/packages/platform/auth/src/Auth/RbacAuthorization.php` — baseline engine (switchable with REBAC).
  - `framework/packages/platform/auth/config/auth.php` — extended with `auth.authorization.engine` + rebac keys.
  - `framework/packages/platform/auth/config/rules.php` — updated config shape rules.

- Required config roots/keys:
  - `auth` / `auth.authorization.*`, `auth.rebac.*` — must exist/owned by platform/auth.

- Required tags:
  - none

- Required contracts / ports:
  - `Coretsia\Contracts\Auth\AuthorizationInterface`
  - `Coretsia\Contracts\Auth\IdentityInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Log\LoggerInterface`

- Rails reused (MUST):
  - Traversal order deterministic.
  - Logs MUST NOT dump tuples/graph; only safe ability + outcome.
  - Metrics labels MUST NOT include relation/tuple values.

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
  - `Coretsia\Contracts\Auth\AuthorizationInterface`
  - `Coretsia\Contracts\Auth\IdentityInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

- Integration:
  - selected by config inside `platform/auth` (`auth.authorization.engine = rbac|rebac`)
- Artifacts:
  - none

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/auth/src/Auth/RebacAuthorization.php` — implements `AuthorizationInterface`
- [ ] `framework/packages/platform/auth/src/Auth/Rebac/GraphStoreInterface.php` — internal interface (NOT cross-package port)
- [ ] `framework/packages/platform/auth/src/Auth/Rebac/InMemoryGraphStore.php` — reference store
- [ ] `framework/packages/platform/auth/src/Auth/Rebac/Relations.php` — relation definitions + deterministic normalize

#### Modifies

- [ ] `framework/packages/platform/auth/config/auth.php` — add:
  - `auth.authorization.engine` (default "rbac")
  - `auth.rebac.relations` = []
  - `auth.rebac.tuples` = []
  - `auth.rebac.max_depth` = 8
- [ ] `framework/packages/platform/auth/config/rules.php` — enforce updated config shape
- [ ] `framework/packages/platform/auth/src/Provider/AuthServiceProvider.php` — register/select authorization engine by config
- [ ] `framework/packages/platform/auth/README.md` — document RBAC vs REBAC switch + max_depth rail
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0058-authorization-engines-rbac-rebac.md`

#### Package skeleton (if type=package)

N/A

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/auth/config/auth.php`
- [ ] Keys (dot):
  - [ ] `auth.authorization.engine` = "rbac"
  - [ ] `auth.rebac.relations` = []
  - [ ] `auth.rebac.tuples` = []
  - [ ] `auth.rebac.max_depth` = 8
- [ ] Rules:
  - [ ] `framework/packages/platform/auth/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Auth\Auth\RebacAuthorization`
  - [ ] selects `Coretsia\Contracts\Auth\AuthorizationInterface` implementation by `auth.authorization.engine`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `auth.authorize` (attr: `engine` or mapped into `driver`)
- [ ] Metrics:
  - [ ] reuse `auth.forbidden_total` / `auth.duration_ms` with `driver` label = engine name (normalize `guard→driver` as per policy)
- [ ] Logs:
  - [ ] deny decision logs only ability + outcome (no tuples dump)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] tuples/relations/graph dumps
- [ ] Allowed:
  - [ ] ability name, outcome; safe ids only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `framework/packages/platform/auth/tests/Contract/ObservabilityPolicyTest.php` (reuse; assert engine label allowed)
- [ ] If redaction exists → `framework/packages/platform/auth/tests/Contract/RedactionDoesNotLeakTest.php` (reuse; assert no tuples dumped)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/auth/tests/Unit/RebacAuthorizationGraphTraversalDeterministicTest.php`
- Contract:
  - [ ] (optional) extend existing observability/redaction contracts to cover REBAC logs/labels
- Integration:
  - [ ] `framework/packages/platform/auth/tests/Integration/RebacMaxDepthEnforcedTest.php`
- Gates/Arch:
  - N/A

### DoD (MUST)

- [ ] Deterministic traversal order proven by tests
- [ ] max_depth enforced
- [ ] README documents RBAC vs REBAC switch
- [ ] ADR updated:
  - [ ] `docs/adr/ADR-0058-authorization-engines-rbac-rebac.md`
- [ ] Non-goals / out of scope
  - [ ] Зовнішнє сховище графа (DB/Redis) — Phase 6+
  - [ ] Використання relation/tuple як metric label (заборонено)
- [ ] Коли `auth.authorization.engine='rebac'`, авторизація працює детерміновано й без граф-експлозій завдяки `max_depth`.
- [ ] When graph contains multiple paths, then traversal order is deterministic and decision is stable between runs.
- [ ] Phase-compat addendum (Determinism + No-leak locks)
  - [ ] Deterministic traversal (MUST)
    - [ ] Graph traversal MUST be deterministic:
      - [ ] neighbor expansion order MUST be stable (`strcmp` over canonical node ids / tuple keys),
      - [ ] cycle/visited handling MUST not depend on insertion order from PHP hashes.
    - [ ] `max_depth` MUST be enforced as a hard rail.
  - [ ] No graph/tuples leakage (MUST)
    - [ ] Logs/spans/metrics MUST NOT include tuples/relations dumps or IDs.
    - [ ] Allowed: ability name + outcome only; optional `hash/len` for opaque ids if absolutely required.
  - [ ] Deterministic duration measurement (MUST)
    - [ ] Any `*_duration_ms` metric MUST use `Coretsia\Foundation\Time\Stopwatch` (int ms); floats forbidden.

---

### 5.30.0 Contracts: Events (sync + deferred, UoW-friendly) (MUST) [CONTRACTS]

---
type: package
phase: 5
epic_id: "5.30.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Contracts зацементовують format-neutral ports+VO для events (sync + deferred) без PSR-7 leakage і без потреби змінювати контракти при розширенні dispatch/listeners semantics."
provides:
- "Canonical domain event contract (name/time/payload) + json-like payload policy"
- "Ports: sync dispatcher + listener + deferred queue (UoW-friendly)"
- "Stable VO shapes for snapshot/contract tests (EventEnvelope metadata)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0065-events-ports-sync-deferred.md"
ssot_refs:
- docs/ssot/observability-and-errors.md   # json-like policy + float forbidden norms
- docs/ssot/uow-and-reset-contracts.md    # UoW-friendly / hook boundary context
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - none

- Required deliverables (exact paths):
  - none

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none (this epic INTRODUCES the ports below)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- none

Forbidden:
- `platform/*`
- `Psr\Http\Message\*`
- `Psr\Http\Server\*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/README.md` — (MODIFIES, optional) mention `Contracts\Events\*` ports + invariants

- [ ] `framework/packages/core/contracts/src/Events/DomainEventInterface.php` — canonical domain event shape (name/time/payload)
- [ ] `framework/packages/core/contracts/src/Events/EventDispatcherInterface.php` — sync dispatcher port
- [ ] `framework/packages/core/contracts/src/Events/EventListenerInterface.php` — listener port (single handler method)
- [ ] `framework/packages/core/contracts/src/Events/DeferredEventQueueInterface.php` — deferred queue port (push/releaseAll)
- [ ] `framework/packages/core/contracts/src/Events/EventEnvelope.php` — optional VO for stable metadata (schemaVersion, id, name, occurredAt, payload, correlationId?)

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0065-events-ports-sync-deferred.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/core/contracts/composer.json` — (MODIFIES if needed) ensure autoload covers `src/` only (no special changes usually)

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] none (contracts не оголошує DI tags)
- ServiceProvider wiring evidence:
  - N/A

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

N/A (ports only; impl in `platform/events`)

#### Errors

N/A (contracts may add optional exceptions later via ADR; not required now)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] payload with secrets/PII as logs/metrics labels (policy enforced in impl)
- [ ] Allowed:
  - [ ] only json-like payload in VO; any PII handling is impl responsibility

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

N/A (no Context writes; no `kernel.reset`; no metrics/spans/logs emitted in contracts)

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/core/contracts/tests/Unit/EventEnvelopeShapeTest.php`
- Contract:
  - [ ] `framework/packages/core/contracts/tests/Contract/EventsContractsTest.php`
  - [ ] `framework/packages/core/contracts/tests/Contract/ContractsDoNotDependOnPsr7ContractTest.php`
- Integration:
  - [ ] none
- Gates/Arch:
  - [ ] `framework/tools/gates/contracts_only_ports_gate.php` expectations updated (if needed)

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
- [ ] Determinism: VO shapes stable; json-like payload enforced by tests
- [ ] Docs updated:
  - [ ] README (optional)
  - [ ] ADR `docs/adr/ADR-0065-events-ports-sync-deferred.md` exists and explains ports + “no subscribers, use tags” rule
- [ ] Solves:
  - [ ] Зацементувати format-neutral contracts для events (sync dispatch + deferred queue), придатні для HTTP/CLI/Queue/Scheduler UoW
  - [ ] Забезпечити json-like payload policy і заборонити “event subscriber monolith” (канон: listeners через DI tag у `platform/events`)
  - [ ] Дати стабільні VO shapes для snapshot/contract tests
- [ ] Non-goals:
  - [ ] Реалізація dispatcher/listener registry/deferred flush (це 5.40.0)
  - [ ] Будь-які storage/DB/outbox semantics (це інші епіки)
  - [ ] HTTP-aware типи (PSR-7), роутинг, middleware (заборонено в contracts)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/events` provides implementations (sync + deferred), noop-safe observability via `platform/logging|tracing|metrics`
- [ ] Discovery / wiring is via tags:
  - [ ] `events.listener` (in platform/events)
  - [ ] `kernel.hook.after_uow` (in platform/events)
- [ ] Contracts дозволяють реалізувати `platform/events` без PSR-7 leakage і без змін контрактів при розширенні listeners/dispatch semantics.
- [ ] When a runtime package dispatches a domain event with json-like payload, then it can be queued/deferred and flushed after successful UoW via platform implementation.

---

### 5.40.0 coretsia/events — Sync dispatcher + deferred queue (MUST) [IMPL]

---
type: package
phase: 5
epic_id: "5.40.0"
owner_path: "framework/packages/platform/events/"

package_id: "platform/events"
composer: "coretsia/platform-events"
kind: runtime
module_id: "platform.events"

goal: "Коли `platform.events` увімкнено, події dispatch’аться sync, deferred події flush’аться тільки на success UoW, і state завжди очищується між UoW."
provides:
- "Sync `EventDispatcherInterface` з deterministic listener discovery через DI tag"
- "Deferred queue + flush on success-only via after_uow hook"
- "Reset discipline: deferred queue не протікає між UoW; noop-safe observability"

tags_introduced:
- "events.listener"

config_roots_introduced:
- "events"

artifacts_introduced: []
adr: "docs/adr/ADR-0066-events-sync-dispatcher-deferred-queue.md"
ssot_refs:
- docs/ssot/tags.md
- docs/ssot/config-roots.md
- docs/ssot/observability.md
- docs/ssot/uow-outcome-policy.md
- docs/ssot/stateful-services.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 5.30.0 — contracts ports for events must exist

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/src/Events/EventDispatcherInterface.php` — dispatcher port
  - `framework/packages/core/contracts/src/Events/EventListenerInterface.php` — listener port
  - `framework/packages/core/contracts/src/Events/DeferredEventQueueInterface.php` — deferred queue port
  - `framework/packages/core/kernel/src/Provider/Tags.php` — owner constants for `kernel.hook.*`
  - `framework/packages/core/foundation/src/Provider/Tags.php` — owner constants for `kernel.reset` / `kernel.stateful`
  - `framework/packages/platform/cli/src/Provider/Tags.php` — owner constants for `cli.command` (not used here, but reserve policy)

- Required config roots/keys:
  - `events.*` — introduced by this epic (see Configuration)

- Required tags:
  - `events.listener` — introduced/owned by this epic (listener discovery)
  - `kernel.hook.after_uow` — must exist in runtime wiring policy (used to flush deferred)
  - `kernel.reset` — must exist in runtime wiring policy (used to reset between UoW)

- Required contracts / ports:
  - `Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface` — hook contract
  - `Coretsia\Contracts\Runtime\ResetInterface` — reset contract
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — tracing port (impl provided by presets)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics port (impl provided by presets)
  - `Psr\Log\LoggerInterface` — logging port

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http`
- `platform/cli`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Events\EventDispatcherInterface`
  - `Coretsia\Contracts\Events\EventListenerInterface`
  - `Coretsia\Contracts\Events\DeferredEventQueueInterface`
  - `Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - N/A
- Kernel hooks/tags:
  - `kernel.hook.after_uow` priority `0` meta `{"reason":"flush deferred events only on success"}` → `framework/packages/platform/events/src/Kernel/DeferredEventsAfterUowHook.php`
  - `kernel.reset` priority `0` meta `{"reason":"clear deferred queue between UoW"}` → `framework/packages/platform/events/src/Deferred/DeferredEventQueue.php`
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/events/src/Module/EventsModule.php`
- [ ] `framework/packages/platform/events/src/Provider/EventsServiceProvider.php`
- [ ] `framework/packages/platform/events/src/Provider/EventsServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/events/config/events.php`
- [ ] `framework/packages/platform/events/config/rules.php`
- [ ] `framework/packages/platform/events/README.md` — MUST include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/platform/events/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

- [ ] `framework/packages/platform/events/src/Provider/Tags.php` — constants (ONLY owner tag):
  - [ ] `EVENTS_LISTENER = 'events.listener'`
- [ ] `framework/packages/platform/events/src/Dispatcher/SyncEventDispatcher.php` — implements `EventDispatcherInterface` (sync dispatch)
- [ ] `framework/packages/platform/events/src/Dispatcher/ListenerRegistry.php` — collects listeners from tag `events.listener` (DeterministicOrder)
- [ ] `framework/packages/platform/events/src/Deferred/DeferredEventQueue.php` — implements `DeferredEventQueueInterface`, `ResetInterface` (tag `kernel.reset`)
- [ ] `framework/packages/platform/events/src/Kernel/DeferredEventsAfterUowHook.php` — implements `AfterUowHookInterface` (flush only on success)
- [ ] `framework/packages/platform/events/src/Observability/EventInstrumentation.php` — spans/metrics/log policy helper
- [ ] `docs/architecture/events.md` — high-level (listeners tag, deferred semantics, redaction)

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0066-events-sync-dispatcher-deferred-queue.md`
- [ ] `docs/ssot/tags.md` — add rows:
  - [ ] `events.listener` | owner `platform/events` | purpose: deterministic listener discovery (TagRegistry order: priority DESC, id ASC)
- [ ] `docs/ssot/config-roots.md` — add rows:
  - [ ] `events` | owner `platform/events` | defaults `framework/packages/platform/events/config/events.php` | rules `framework/packages/platform/events/config/rules.php`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/events/composer.json`
- [ ] `framework/packages/platform/events/src/Module/EventsModule.php`
- [ ] `framework/packages/platform/events/src/Provider/EventsServiceProvider.php`
- [ ] `framework/packages/platform/events/config/events.php`
- [ ] `framework/packages/platform/events/config/rules.php`
- [ ] `framework/packages/platform/events/README.md`
- [ ] `framework/packages/platform/events/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/events/config/events.php`
- [ ] Keys (dot):
  - [ ] `events.enabled` = true
  - [ ] `events.deferred.enabled` = true
  - [ ] `events.deferred.flush_on_success_only` = true
  - [ ] `events.listeners.enabled` = true
- [ ] Rules:
  - [ ] `framework/packages/platform/events/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/events/src/Provider/Tags.php` (constants)
  - [ ] constants:
    - [ ] `EVENTS_LISTENER` → `events.listener`
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Contracts\Events\EventDispatcherInterface` → `Coretsia\Events\Dispatcher\SyncEventDispatcher`
  - [ ] registers: `Coretsia\Contracts\Events\DeferredEventQueueInterface` → `Coretsia\Events\Deferred\DeferredEventQueue`
  - [ ] registers: `Coretsia\Events\Kernel\DeferredEventsAfterUowHook`
  - [ ] adds tag: `<Coretsia\Foundation\...\Tags::KERNEL_RESET>` priority `0` meta `{"reason":"clear deferred queue between UoW"}`
  - [ ] adds tag: `<Coretsia\Kernel\...\Tags::KERNEL_HOOK_AFTER_UOW>` priority `0` meta `{"reason":"flush deferred events only on success"}`
  - [ ] listener discovery via tag: `events.listener` (other packages contribute listeners)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
- Context writes (safe only):
  - N/A (events package MUST NOT write payload/PII to context)
- [ ] Reset discipline:
  - [ ] stateful services implement `ResetInterface`
  - [ ] tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `events.dispatch` (attrs allowed: `operation=sync|deferred_flush`, `outcome`)
  - [ ] `events.listener` (attrs allowed: `listener_class` as span attribute only)
- [ ] Metrics:
  - [ ] `events.dispatched_total` (labels: `operation`, `outcome`)
  - [ ] `events.deferred_total` (labels: `outcome`)
  - [ ] `events.dispatch_duration_ms` (labels: `operation`, `outcome`)
  - [ ] `events.listener_duration_ms` (labels: `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `kind/op→operation`
- [ ] Logs:
  - [ ] listener fail → error (event name as log field; no payload)
  - [ ] no secrets/PII (hash/len only)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/events/src/Exception/EventDispatchException.php` — errorCode `CORETSIA_EVENTS_DISPATCH_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) (default mapper OK; avoid HTTP coupling)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] event payload (logs/spans attrs), Authorization/Cookie/session id/tokens
- [ ] Allowed:
  - [ ] safe ids only (`correlation_id`), `hash(value)`/`len(value)`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → N/A (no Context writes)
- [ ] If `kernel.reset` used → evidence:
  - [ ] `framework/packages/platform/events/tests/Integration/DeferredQueueResetBetweenUowTest.php`
- [ ] If metrics/spans/logs exist → evidence (noop-safe baseline):
  - [ ] `framework/packages/platform/events/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → evidence:
  - [ ] `framework/packages/platform/events/README.md` + `docs/architecture/events.md` document the “no payload” rule (tests listed below cover behavior paths)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php`
- [ ] Fake adapters:
  - [ ] (as needed by tests) FakeTracer / FakeMetrics / FakeLogger capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/events/tests/Unit/DeferredQueuePreservesPushOrderTest.php`
- Contract:
  - [ ] `framework/packages/platform/events/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/events/tests/Integration/DeterministicListenerOrderTest.php`
  - [ ] `framework/packages/platform/events/tests/Integration/DeferredEventsFlushedAfterSuccessfulUowTest.php`
  - [ ] `framework/packages/platform/events/tests/Integration/DeferredEventsNotFlushedOnHandledErrorTest.php`
  - [ ] `framework/packages/platform/events/tests/Integration/DeferredQueueResetBetweenUowTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

- Framework / E2E (system-level; cross-package):
  - [ ] `framework/tools/tests/Integration/E2E/DeferredEventsFlushOnlyOnSuccessTest.php`

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
- [ ] Determinism: listener order deterministic; flush semantics stable; rerun tests stable
- [ ] Docs updated:
  - [ ] README + `docs/architecture/events.md` complete (tag names, deferred semantics, redaction)
  - [ ] ADR `docs/adr/ADR-0066-events-sync-dispatcher-deferred-queue.md`
- [ ] Solves:
  - [ ] Reference `EventDispatcherInterface` реалізація (sync dispatch) з deterministic listener discovery через DI tag
  - [ ] Deferred queue, яка flush’иться тільки після `UnitOfWorkResult.outcome=success` через kernel after_uow hook
  - [ ] Reset discipline: deferred queue ніколи не протікає між UoW
- [ ] Non-goals:
  - [ ] Outbox/publisher/DB persistence (окремі епіки)
  - [ ] Metric labels із `event_name`/`listener` (заборонено; тільки span/log attrs)
  - [ ] Паралельний dispatch/async processing (це queue/outbox)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
  - [ ] KernelRuntime executes hooks `kernel.hook.after_uow`
- [ ] Discovery / wiring is via tags:
  - [ ] `events.listener` (deterministic order)
  - [ ] `kernel.hook.after_uow` (flush deferred on success)
  - [ ] `kernel.reset` (deferred queue reset)
- [ ] Коли `platform.events` увімкнено, події dispatch’аться sync, deferred події flush’аться тільки на success UoW, і state завжди очищується між UoW.
- [ ] When a handler queues deferred events during an HTTP request, then those deferred events are dispatched only if response outcome is success; otherwise they are discarded by reset.

---

### 5.50.0 Deferred events semantics (flush policy) (SHOULD) [DOC]

---
type: docs
phase: 5
epic_id: "5.50.0"
owner_path: "docs/architecture/"

goal: "Документ однозначно описує deferred semantics (flush only on success) і служить рев’ю-опорою для тестів/рефакторингів."
provides:
- "SSoT правило: deferred events flush тільки при `UnitOfWorkResult.outcome=success`"
- "Пояснення: де живе логіка (platform/events AfterUowHook) і чому kernel лишається dumb"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/uow-outcome-policy.md
- docs/ssot/tags.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 5.40.0 — implementation exists to reference in doc/tests (recommended)

- Required deliverables (exact paths):
  - none

- Required config roots/keys:
  - none

- Required tags:
  - `kernel.hook.after_uow`, `kernel.reset` — referenced as wiring semantics (from runtime policy)

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

N/A

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `docs/ssot/events-deferred-semantics.md` — canonical policy: flush only on `outcome=success` + rationale + links to tests

#### Modifies

- [ ] `framework/packages/platform/events/README.md` — link to `docs/ssot/events-deferred-semantics.md`
- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/events-deferred-semantics.md`

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [ ] Metrics/labels guidance included:
  - [ ] no `event_name` as metric label; use span/log attrs (as documented)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Test harness / fixtures (when integration is needed)

- [ ] Framework / E2E tests (system-level; cross-package):
  - [ ] `framework/tools/tests/Integration/E2E/DeferredEventsFlushOnlyOnSuccessTest.php`
- [ ] Fixture wiring:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php`

### Tests (MUST)

N/A

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete (N/A for docs-only)
- [ ] Tests green (N/A for docs-only)
- [ ] Gates/Arch green (N/A for docs-only)
- [ ] Docs complete
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Doc exists and is linked from `framework/packages/platform/events/README.md`
- [ ] Doc matches tests + implementation (no drift)
- [ ] Solves:
  - [ ] Зацементувати SSoT правило: deferred events flush тільки при `UnitOfWorkResult.outcome=success`
  - [ ] Пояснити “де живе логіка” (platform/events AfterUowHook) і “чому kernel залишається dumb”
- [ ] Non-goals:
  - [ ] Реалізація коду (це 5.40.0)
  - [ ] Outbox semantics (інший епік)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/events` enabled
- [ ] Discovery / wiring is via tags:
  - [ ] `kernel.hook.after_uow`, `kernel.reset`
- [ ] Документ однозначно описує deferred semantics і служить рев’ю-опорою для тестів/рефакторингів.
- [ ] When UoW ends with handled_error, then deferred events are not dispatched and the queue is cleared by reset.

---

### 5.60.0 coretsia/queue — Core (sync driver + worker runtime) (MUST) [IMPL]

---
type: package
phase: 5
epic_id: "5.60.0"
owner_path: "framework/packages/platform/queue/"

package_id: "platform/queue"
composer: "coretsia/platform-queue"
kind: runtime
module_id: "platform.queue"

goal: "Коли `platform.queue` увімкнено, `QueueInterface->dispatch()` працює (sync), а worker loop коректно виконує jobs як UoW без протікання контексту."
provides:
- "Reference queue runtime: sync driver + worker loop, кожен job = KernelRuntime UoW"
- "Deterministic serialization (stable JSON) + retry/backoff policy + never-leak-payload policy"
- "Extension point for async drivers (DB driver у 5.70.0)"

tags_introduced: []
config_roots_introduced:
- "queue"

artifacts_introduced: []
adr: "docs/adr/ADR-0068-queue-core-sync-driver.md"
ssot_refs:
- docs/ssot/config-roots.md
- docs/ssot/observability.md
- docs/ssot/stateful-services.md
- docs/ssot/uow-outcome-policy.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 5.30.0 — contracts package exists (queue contracts assumed available in `core/contracts`)
  - core/kernel UoW runtime exists — worker wraps each job in UoW (begin/after)

- Required deliverables (exact paths):
  - none

- Required config roots/keys:
  - `queue.*` — introduced by this epic (see Configuration)

- Required tags:
  - `kernel.reset` — used to reset worker runtime state (if stateful) via `framework/packages/platform/cli/src/Provider/Tags.php` (owner)
  - `cli.command` — referenced for commands (commands are in 5.90.0; discovered by `platform/cli`) via `framework/packages/core/foundation/src/Provider/Tags.php` (owner)

- Required contracts / ports:
  - `Coretsia\Contracts\Queue\JobInterface`
  - `Coretsia\Contracts\Queue\JobSerializerInterface`
  - `Coretsia\Contracts\Queue\QueueDriverInterface`
  - `Coretsia\Contracts\Queue\QueueInterface`
  - `Coretsia\Contracts\Queue\BackoffStrategyInterface`
  - `Coretsia\Contracts\Queue\FailedJobRepositoryInterface`
  - `Coretsia\Contracts\Queue\QueueWorkerRuntimeInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Log\LoggerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:
- `platform/http`
- `platform/cli`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Queue\JobInterface`
  - `Coretsia\Contracts\Queue\JobSerializerInterface`
  - `Coretsia\Contracts\Queue\QueueDriverInterface`
  - `Coretsia\Contracts\Queue\QueueInterface`
  - `Coretsia\Contracts\Queue\BackoffStrategyInterface`
  - `Coretsia\Contracts\Queue\FailedJobRepositoryInterface`
  - `Coretsia\Contracts\Queue\QueueWorkerRuntimeInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

- CLI:
  - N/A (commands are defined in 5.90.0 and discovered via `cli.command` by `platform/cli`)
- HTTP:
  - N/A
- Kernel hooks/tags:
  - (internal UoW wrapper inside worker) begin(type=`queue`) + after(result) via `core/kernel`
  - `kernel.reset` priority `0` meta `{"reason":"worker runtime state cleared between runs (if stateful)"}` (when applicable)
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/queue/src/Module/QueueModule.php`
- [ ] `framework/packages/platform/queue/src/Provider/QueueServiceProvider.php`
- [ ] `framework/packages/platform/queue/src/Provider/QueueServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/queue/config/queue.php`
- [ ] `framework/packages/platform/queue/config/rules.php`
- [ ] `framework/packages/platform/queue/README.md` — MUST include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/platform/queue/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

- [ ] `framework/packages/platform/queue/src/Queue/QueueManager.php` — driver registry + facade wiring
- [ ] `framework/packages/platform/queue/src/Driver/SyncQueueDriver.php` — reference sync driver
- [ ] `framework/packages/platform/queue/src/Serialization/JsonJobSerializer.php` — deterministic serialize/deserialize (no payload logging)
- [ ] `framework/packages/platform/queue/src/Retry/ExponentialBackoffStrategy.php` — deterministic backoff (ints only)
- [ ] `framework/packages/platform/queue/src/Worker/QueueWorkerRuntime.php` — worker loop (`work`, `runOnce`, `stop`) + UoW wrapper
- [ ] `framework/packages/platform/queue/src/Worker/JobRunner.php` — handler lookup + retry/fail decisions (no magic)
- [ ] `docs/architecture/queue.md` — runtime model, retry policy, redaction, UoW discipline

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0068-queue-core-sync-driver.md`
- [ ] `docs/ssot/config-roots.md` — add rows:
  - [ ] `queue`  | owner `platform/queue`  | defaults `framework/packages/platform/queue/config/queue.php`  | rules `framework/packages/platform/queue/config/rules.php`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/queue/composer.json`
- [ ] `framework/packages/platform/queue/src/Module/QueueModule.php`
- [ ] `framework/packages/platform/queue/src/Provider/QueueServiceProvider.php`
- [ ] `framework/packages/platform/queue/config/queue.php`
- [ ] `framework/packages/platform/queue/config/rules.php`
- [ ] `framework/packages/platform/queue/README.md`
- [ ] `framework/packages/platform/queue/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/queue/config/queue.php`
- [ ] Keys (dot):
  - [ ] `queue.enabled` = true
  - [ ] `queue.default` = "sync"              # "sync"|"db"
  - [ ] `queue.retry.enabled` = true
  - [ ] `queue.retry.max_attempts` = 5
  - [ ] `queue.retry.backoff.strategy` = "exponential"
  - [ ] `queue.failed.store` = "memory"       # "memory"|"db"
  - [ ] `queue.handlers.map` = []             # jobName => handler service id (explicit)
- [ ] Rules:
  - [ ] `framework/packages/platform/queue/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (queue does not own `cli.command` nor `kernel.reset`, but references them)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Contracts\Queue\QueueInterface` (thin facade)
  - [ ] registers: `Coretsia\Contracts\Queue\QueueDriverInterface` default → `Coretsia\Queue\Driver\SyncQueueDriver`
  - [ ] registers: `Coretsia\Contracts\Queue\JobSerializerInterface` → `Coretsia\Queue\Serialization\JsonJobSerializer`
  - [ ] registers: `Coretsia\Contracts\Queue\QueueWorkerRuntimeInterface` → `Coretsia\Queue\Worker\QueueWorkerRuntime`
  - [ ] adds tag: `kernel.reset` priority `0` meta `{"reason":"worker runtime state cleared between runs (if stateful)"}`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- [ ] Context writes (safe only):
  - [ ] optional: `queue.job_id` (safe id) (only if explicitly added; never payload)
- [ ] Reset discipline:
  - [ ] all stateful services implement `ResetInterface`
  - [ ] all stateful services tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `queue.job` (attrs allowed: `driver`, `attempt`, `outcome`; job name only as span attribute)
- [ ] Metrics:
  - [ ] `queue.job_total` (labels: `driver`, `outcome`)
  - [ ] `queue.job_failed_total` (labels: `driver`, `outcome`)
  - [ ] `queue.job_duration_ms` (labels: `driver`, `outcome`)
  - [ ] `queue.retry_total` (labels: `driver`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `uow_type→operation` (only if you ever label UoW type; otherwise keep as span attr)
- [ ] Logs:
  - [ ] start/finish summary (job_id, attempt, outcome) without payload
  - [ ] no secrets/PII (hash/len only)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/queue/src/Exception/QueueException.php` — errorCode `CORETSIA_QUEUE_FAILED`
  - [ ] `framework/packages/platform/queue/src/Exception/QueueException.php` — errorCode `CORETSIA_QUEUE_HANDLER_NOT_FOUND`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) (worker uses `ErrorHandlerInterface` port, not HTTP mapping)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] job payload, raw exception traces in production logs, tokens/session id
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)`; safe ids (`actor_id`, `job_id`)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → N/A (no required Context writes specified)
- [ ] If `kernel.reset` used → evidence:
  - [ ] `framework/packages/platform/queue/tests/Integration/WorkerDoesNotLeakContextBetweenJobsTest.php`
- [ ] If metrics/spans/logs exist → evidence (noop-safe baseline):
  - [ ] `framework/packages/platform/queue/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → evidence:
  - [ ] `framework/packages/platform/queue/tests/Integration/WorkerDoesNotLeakContextBetweenJobsTest.php` (behavior-level)
  - [ ] `docs/architecture/queue.md` documents “never leak payload” policy

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php`
- [ ] Fake adapters:
  - [ ] (as needed by tests) FakeTracer / FakeMetrics / FakeLogger capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/queue/tests/Unit/JsonJobSerializerIsDeterministicTest.php`
  - [ ] `framework/packages/platform/queue/tests/Unit/BackoffStrategyDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/queue/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/queue/tests/Integration/SyncDriverDispatchesImmediatelyTest.php`
  - [ ] `framework/packages/platform/queue/tests/Integration/WorkerCallsKernelRuntimeBeginAndAfterTest.php`
  - [ ] `framework/packages/platform/queue/tests/Integration/RetryBackoffPolicyTest.php`
  - [ ] `framework/packages/platform/queue/tests/Integration/WorkerDoesNotLeakContextBetweenJobsTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)
- Framework / E2E:
  - [ ] `framework/tools/tests/Integration/E2E/QueueWorkerProcessesConfirmationJobTest.php`

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
- [ ] Determinism: serializer stable; worker decisions stable
- [ ] Docs updated:
  - [ ] README + `docs/architecture/queue.md` complete
  - [ ] ADR `docs/adr/ADR-0068-queue-core-sync-driver.md`
- [ ] Solves:
  - [ ] Надати reference queue runtime: sync driver + worker loop, який обгортає кожен job у KernelRuntime UoW
  - [ ] Забезпечити deterministic serialization (stable JSON), retry/backoff policy, і “never leak payload” policy
  - [ ] Підготувати розширення під async drivers (DB driver у 5.70.0)
- [ ] Non-goals:
  - [ ] DB driver schema/locking (це 5.70.0)
  - [ ] Integrations redis/sqs/rabbit (Phase 6+)
  - [ ] Вивід payload у CLI/logs/spans (заборонено)
  - [ ] Не дублює керування процесами; працює лише як task-провайдер (через TaskFactory)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
  - [ ] `platform/errors` provides `ErrorHandlerInterface` implementation (via preset)
- [ ] Discovery / wiring is via tags:
  - [ ] `cli.command` (commands are in 2.190.0, discovered by platform/cli)
  - [ ] `kernel.reset` (worker runtime state, if any)
- [ ] Коли `platform.queue` увімкнено, `QueueInterface->dispatch()` працює (sync), а worker loop коректно виконує jobs як UoW без протікання контексту.
- [ ] When a job handler throws, then `ErrorHandlerInterface` maps it deterministically and worker decides retry/fail without leaking payload.

---

### 5.70.0 coretsia/queue — DB driver (async reference) (SHOULD) [IMPL]

---
type: package
phase: 5
epic_id: "5.70.0"
owner_path: "framework/packages/platform/queue/"

package_id: "platform/queue"
composer: "coretsia/platform-queue"
kind: runtime
module_id: "platform.queue"

goal: "Коли `queue.default='db'`, driver забезпечує `push/reserve/ack/release/fail` детерміновано та працює у CI на SQLite."
provides:
- "DB-backed queue driver (SQLite-first) з deterministic reserve ordering"
- "Failed jobs persistence (DB repository) + migrations"
- "No contracts changes; integrates via `QueueDriverInterface` + existing worker"

tags_introduced: []
config_roots_introduced:
- "queue"  # extends existing root from 5.60.0

artifacts_introduced: []
adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 5.60.0 — queue core runtime exists (sync driver + worker + config root)
  - `platform/database` + `platform/migrations` — required for DB driver + migrations

- Required deliverables (exact paths):
  - `framework/packages/platform/queue/config/queue.php` — must exist to modify
  - `framework/packages/platform/queue/config/rules.php` — must exist to modify

- Required config roots/keys:
  - `queue.default` — must exist (extended to allow "db")
  - `queue.failed.store` — must exist (extended to allow "db")

- Required tags:
  - `cli.command` — referenced for queue CLI (5.90.0)
  - migrations discovery via `platform/migrations` (reverse dependency via paths, not `platform/migrations` depending on queue)

- Required contracts / ports:
  - `Coretsia\Contracts\Queue\QueueDriverInterface`
  - `Coretsia\Contracts\Queue\FailedJobRepositoryInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Migrations\MigrationInterface` — migration class contract (Phase 1)
  - `Coretsia\Contracts\Database\ConnectionInterface` — DB access contract (Phase 1)
  - `Psr\Log\LoggerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `core/kernel`

Runtime expectation:
- `platform/database` provides `ConnectionInterface` implementation
- `platform/migrations` discovers and runs migrations from installed packages (including `platform/queue`)

Forbidden:
- `platform/http`
- `platform/cli`
- `integrations/*`
- `platform/database` (Use only contracts via core/contracts)
- `platform/migrations` (Use only contracts via core/contracts)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Queue\QueueDriverInterface`
  - `Coretsia\Contracts\Queue\FailedJobRepositoryInterface`
  - `Coretsia\Contracts\Database\ConnectionInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - none

### Entry points / integration points (MUST)

- CLI:
  - N/A (queue CLI in 5.90.0)
- HTTP:
  - N/A
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - reads/writes: DB tables via migrations

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/queue/database/migrations/2026_...._create_queue_jobs_table.php` — jobs table
- [ ] `framework/packages/platform/queue/database/migrations/2026_...._create_queue_failed_jobs_table.php` — failed jobs table
- [ ] `framework/packages/platform/queue/src/Driver/DbQueueDriver.php` — implements `QueueDriverInterface` (reserve ordering deterministic)
- [ ] `framework/packages/platform/queue/src/Failed/DbFailedJobRepository.php` — implements `FailedJobRepositoryInterface`

#### Modifies

- [ ] `framework/packages/platform/queue/config/queue.php` — allow `queue.default='db'`, `queue.failed.store='db'` + add db keys
- [ ] `framework/packages/platform/queue/config/rules.php` — extend rules for db config keys

#### Configuration (keys + defaults)

- [ ] Keys (dot):
  - [ ] `queue.default` = "sync"
  - [ ] `queue.db.connection` = "default"
  - [ ] `queue.db.table` = "queue_jobs"
  - [ ] `queue.db.failed_table` = "queue_failed_jobs"

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [ ] Metrics:
  - [ ] reuse `queue.*` metrics from 5.60.0 (no new metric names; driver label distinguishes)
- [ ] Logs:
  - [ ] no raw SQL; no payload

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] payload, raw SQL

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Test harness / fixtures (when integration is needed)

- [ ] SQLite CI coverage via integration tests (see below)
- [ ] Fixture/preset enables `platform.database` + `integrations.database-sqlite` when needed

### Tests (MUST)

- Unit/Contract:
  - N/A
- Integration:
  - [ ] `framework/packages/platform/queue/tests/Integration/DbDriverPushReserveAckTest.php`
  - [ ] `framework/packages/platform/queue/tests/Integration/FailedJobsRecordedTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] DB driver deterministic on SQLite CI
- [ ] migrations shipped and usable by fixture
- [ ] tests green
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Solves:
  - [ ] Додати DB-backed queue driver (SQLite-first) із deterministic reserve ordering
  - [ ] Додати failed jobs persistence (DB repository) і міграції
  - [ ] Не змінювати contracts; інтегруватися через `QueueDriverInterface` + existing worker
- [ ] Non-goals:
  - [ ] Redis driver (Phase 6+ integration)
  - [ ] High-cardinality labels / raw SQL logs (заборонено)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform.database` + `integrations.database-sqlite` enabled in fixtures/presets
- [ ] Discovery / wiring is via tags:
  - [ ] `cli.command` (queue CLI in 2.190.0)
  - [ ] migrations discovered by `platform/migrations` (reverse dependency via paths, not `platform/migrations` depending on queue)
- [ ] Коли `queue.default='db'`, driver забезпечує `push/reserve/ack/release/fail` детерміновано та працює у CI на SQLite.
- [ ] When multiple jobs are available, then `reserve()` picks the next job deterministically by `available_at ASC, id ASC`.

---

### 5.80.0 coretsia/queue — CLI commands queue:* (MUST) [IMPL]

---
type: package
phase: 5
epic_id: "5.80.0"
owner_path: "framework/packages/platform/queue/"

package_id: "platform/queue"
composer: "coretsia/platform-queue"
kind: runtime
module_id: "platform.queue"

goal: "Коли `platform.queue` увімкнено, `coretsia queue:*` команди доступні, deterministic, і не витікають payload."
provides:
- "CLI команди `queue:work|failed|retry|flush` без compile-time залежності на `platform/cli` (discovery через tag)"
- "SSoT-узгоджений JSON output schema + redaction: ніколи не друкувати payload/job args"
- "dev-only guard для `queue:flush` через конфіг"

tags_introduced: []
config_roots_introduced:
- "queue"

artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/config-and-env.md"
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/metrics-policy.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - `framework/packages/platform/cli/` — runtime discovery CLI команд через tag `cli.command` (але compile-time залежність заборонена)

- Required config roots/keys:
  - `queue` — базовий config root пакета queue має існувати/бути canonical (config file повертає subtree без повторення root)

- Required tags:
  - `cli.command` — tag має бути підтриманий runtime CLI discovery (owner: `platform/cli`)

- Required contracts / ports:
  - `Coretsia\Contracts\Queue\QueueWorkerRuntimeInterface` — виконання worker runtime
  - `Coretsia\Contracts\Queue\FailedJobRepositoryInterface` — читання failed jobs
  - `Coretsia\Contracts\Queue\QueueInterface` — retry/flush/work інтеграції з queue портом
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface` — normalized failures (optional)
  - `Coretsia\Contracts\Cli\Command\CommandInterface` — all `queue:*` commands MUST implement the contracts command port
  - `Coretsia\Contracts\Cli\Input\InputInterface` — argv/token access through the CLI port (no direct `$argv` parsing in business code)
  - `Coretsia\Contracts\Cli\Output\OutputInterface` — deterministic output channel (MUST NOT write to stdout/stderr directly)
  - `Coretsia\Contracts\Env\EnvRepositoryInterface` (optional) — only if `queue.cli.flush.allow_in_env` is enforced via env (missing vs empty semantics preserved)
  - `Psr\Log\LoggerInterface` — логування

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- `platform/cli`
- `platform/http`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Queue\QueueWorkerRuntimeInterface`
  - `Coretsia\Contracts\Queue\FailedJobRepositoryInterface`
  - `Coretsia\Contracts\Queue\QueueInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface` (optional)
- Foundation stable APIs:
  - N/A

### Entry points / integration points (MUST)

- CLI:
  - `queue:work` → `platform/queue` `src/Console/QueueWorkCommand.php`
  - `queue:failed` → `platform/queue` `src/Console/QueueFailedCommand.php`
  - `queue:retry` → `platform/queue` `src/Console/QueueRetryCommand.php`
  - `queue:flush` → `platform/queue` `src/Console/QueueFlushCommand.php`
- HTTP:
  - N/A
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - reads: N/A
  - writes: N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/queue/src/Console/QueueWorkCommand.php` — `coretsia queue:work [--once] [--sleep=1] [--max-jobs=N]` (safe JSON output; no payload)
- [ ] `framework/packages/platform/queue/src/Console/QueueFailedCommand.php` — `coretsia queue:failed` (safe JSON output; no payload)
- [ ] `framework/packages/platform/queue/src/Console/QueueRetryCommand.php` — `coretsia queue:retry <id|all>` (safe JSON output; no payload)
- [ ] `framework/packages/platform/queue/src/Console/QueueFlushCommand.php` — `coretsia queue:flush` (dev-only guard; safe JSON output; no payload)
- [ ] `framework/packages/platform/queue/config/queue.php` — config subtree for `queue.*`

#### Modifies

- [ ] `framework/packages/platform/queue/src/Provider/QueueServiceProvider.php` — register commands as services + tag `cli.command`
- [ ] `framework/packages/platform/queue/config/rules.php` — enforce config shape for `queue.cli.*`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/queue/composer.json`
- [ ] `framework/packages/platform/queue/src/Module/QueueModule.php`
- [ ] `framework/packages/platform/queue/src/Provider/QueueServiceProvider.php`
- [ ] `framework/packages/platform/queue/config/queue.php`
- [ ] `framework/packages/platform/queue/config/rules.php`
- [ ] `framework/packages/platform/queue/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/queue/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/queue/config/queue.php`
- [ ] Keys (dot):
  - [ ] `queue.cli.flush.enabled` = false
  - [ ] `queue.cli.flush.allow_in_env` = ['local']
- [ ] Rules:
  - [ ] `framework/packages/platform/queue/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (uses existing `cli.command`)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Queue\Console\QueueWorkCommand`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"queue:work"}`
  - [ ] registers: `Coretsia\Queue\Console\QueueFailedCommand`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"queue:failed"}`
  - [ ] registers: `Coretsia\Queue\Console\QueueRetryCommand`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"queue:retry"}`
  - [ ] registers: `Coretsia\Queue\Console\QueueFlushCommand`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"queue:flush","guard":"dev-only"}`

- `platform/queue` MUST NOT introduce tag constants for `cli.command` (owner is `platform/cli`).
- Therefore: runtime code MUST use the owner constant when allowed by deps, otherwise a package-local mirror constant; raw literal use is documentation-only.

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Logs:
  - [ ] command start/finish safe summary; no payload
- [ ] Metrics:
  - [ ] reuse `queue.*` metrics from runtime where applicable (policy-compliant; no payload/args)

#### Errors

N/A

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] job payload
  - [ ] job args
  - [ ] raw exception dumps у non-local outputs
- [ ] Allowed:
  - [ ] safe ids
  - [ ] `hash(value)` / `len(value)`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php`
  - N/A (context writes не заявлені)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php`
  - N/A (`kernel.reset` не заявлено)
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  - Evidence (existing tests listed нижче): `QueueCommandsSchemaJsonTest.php` (schema + redaction expectations)
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  - Evidence (existing tests listed нижче): `QueueCommandsSchemaJsonTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit/Contract:
  - N/A
- Integration:
  - [ ] `framework/packages/platform/queue/tests/Integration/CliQueueWorkOnceTest.php`
  - [ ] `framework/packages/platform/queue/tests/Integration/QueueCommandsSchemaJsonTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)
  - [ ] gates updated (if new invariants)

### DoD (MUST)

- [ ] commands discovered via tag (no dependency on `platform/cli`)
- [ ] JSON output follows schema; no payload/job args
- [ ] dev-only guard for `queue:flush` enforced by config
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] tests green
- [ ] README updated (Observability / Errors / Security-Redaction)
- [ ] What problem this epic solves
  - [ ] Дати операторські CLI команди для worker/failed/retry/flush без залежності на `platform/cli`
  - [ ] Забезпечити JSON output schema (SSoT) і redaction (ніколи не друкувати payload)
- [ ] Non-goals / out of scope
  - [ ] “Адмін панель” для queue (devtools)
  - [ ] Показ payload/job args у CLI (заборонено)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/cli` discovers these commands via `cli.command` tag
- [ ] Discovery / wiring is via tags:
  - [ ] `cli.command`
- [ ] Коли `platform.queue` увімкнено, `coretsia queue:*` команди доступні, deterministic, і не витікають payload.
- [ ] When `coretsia queue:work --once` runs, then it executes at most one job and prints safe JSON without payload.

---

### 5.90.0 Contracts: CommandBus + Scheduler (MUST) [CONTRACTS]

---
type: package
phase: 5
epic_id: "5.90.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Contracts дозволяють реалізувати scheduler як dispatch через command bus або queue без змін портів."
provides:
- "CommandBus ports: dispatch + handler + middleware pipeline"
- "Scheduler ports: schedule definition/registry/runner/report (format-neutral; без знання cron/lock/queue імплементацій)"
- "Policy: lock reuse через `LockFactoryInterface` (без окремого schedule lock port)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-0069-commandbus-scheduler-ports.md"
ssot_refs:
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/metrics-policy.md"
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
  - `Coretsia\Contracts\Lock\LockFactoryInterface` — reuse для scheduler implementations (contract already exists)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- `platform/*`
- `Psr\Http\Message\*`
- `Psr\Http\Server\*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - none
- Contracts:
  - `Coretsia\Contracts\Lock\LockFactoryInterface`
- Foundation stable APIs:
  - none

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/Bus/CommandBusInterface.php`
- [ ] `framework/packages/core/contracts/src/Bus/CommandHandlerInterface.php`
- [ ] `framework/packages/core/contracts/src/Bus/CommandMiddlewareInterface.php`
- [ ] `framework/packages/core/contracts/src/Scheduler/ScheduleProviderInterface.php`
- [ ] `framework/packages/core/contracts/src/Scheduler/ScheduleDefinition.php`
- [ ] `framework/packages/core/contracts/src/Scheduler/ScheduleRegistryInterface.php`
- [ ] `framework/packages/core/contracts/src/Scheduler/ScheduleRunnerInterface.php`
- [ ] `framework/packages/core/contracts/src/Scheduler/ScheduleRunReport.php`
- [ ] `docs/adr/ADR-0069-commandbus-scheduler-ports.md` — documents “LockFactory reuse” + “format-neutral scheduler”

Scheduler tag-based registry contract (cemented)
- Tag-based registries MUST tag *typed* services, not ad-hoc VOs.
- Scheduler schedule contributions MUST be services implementing `ScheduleProviderInterface`,
  returning an immutable `ScheduleDefinition` (json-like; float-forbidden).

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0069-commandbus-scheduler-ports.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/core/contracts/composer.json`
- [ ] `framework/packages/core/contracts/README.md` (optional; якщо policy вимагає)
- [ ] `framework/packages/core/contracts/config/rules.php` (N/A для library, якщо пакет не має конфігів)

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

- [ ] MUST NOT leak:
  - [ ] schedule args як metric labels (impl rule; contract keeps args json-like only)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

N/A (contracts layer)

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit/Integration:
  - N/A
- Contract:
  - [ ] `framework/packages/core/contracts/tests/Contract/BusContractsTest.php`
  - [ ] `framework/packages/core/contracts/tests/Contract/SchedulerContractsTest.php`
  - [ ] `framework/packages/core/contracts/tests/Contract/ContractsDoNotDependOnPsr7ContractTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] contract tests green
- [ ] ADR merged і фіксує: “LockFactory reuse” + “format-neutral scheduler”
- [ ] no forbidden deps (platform/*, PSR-7/15)
- [ ] What problem this epic solves
  - [ ] Зацементувати contracts для CommandBus (dispatch + handler + middleware pipeline)
  - [ ] Зацементувати contracts для Scheduler (schedule definition/registry/runner/report) без знання cron/lock/queue реалізацій
  - [ ] Визначити, що lock reuse йде через `LockFactoryInterface` (не вводимо окремий schedule lock port)
- [ ] Non-goals / out of scope
  - [ ] Реалізація cqrs/scheduler (це 2.210.0–2.220.0)
  - [ ] Реалізація lock (це 2.110.0)
  - [ ] Будь-які PSR-7/HTTP залежності (заборонено в contracts)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/cqrs` implements CommandBus
  - [ ] `platform/scheduler` implements Scheduler runner/registry
- [ ] Discovery / wiring is via tags:
  - [ ] `cli.command` (schedule:* commands provided by platform/scheduler)
  - [ ] `scheduler.schedule` (if registry uses tags in implementation)
- [ ] Contracts дозволяють реалізувати scheduler як dispatch через command bus або queue без змін портів.
- [ ] When a schedule is due at time T, then `ScheduleRunnerInterface->runDue($now)` returns a stable report with counts and items.

---

### 5.100.0 coretsia/cqrs — Command bus + middleware pipeline (MUST) [IMPL]

---
type: package
phase: 5
epic_id: "5.100.0"
owner_path: "framework/packages/platform/cqrs/"

package_id: "platform/cqrs"
composer: "coretsia/platform-cqrs"
kind: runtime
module_id: "platform.cqrs"

goal: "Коли `platform.cqrs` увімкнено, команда dispatch’иться детерміновано у handler, middleware порядок стабільний, а помилки мапляться через canonical error flow."
provides:
- "Implementation `CommandBusInterface` з deterministic handler lookup (explicit map; no scanning)"
- "Deterministic middleware pipeline order"
- "Deterministic error mapping “handler not found” через `error.mapper` (ExceptionMapperInterface)"
- "Noop-safe observability (logs/metrics/tracing без payload)"

tags_introduced: []
config_roots_introduced:
- "cqrs"

artifacts_introduced: []
adr: "docs/adr/ADR-0070-command-bus-middleware-pipeline.md"
ssot_refs:
- "docs/ssot/config-and-env.md"
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/metrics-policy.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 5.90.0 — contracts for CommandBus/Scheduler ports must exist (CommandBus interfaces used here)

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/src/Bus/CommandBusInterface.php` — port used by this implementation
  - `framework/packages/core/contracts/src/Bus/CommandHandlerInterface.php` — handler contract
  - `framework/packages/core/contracts/src/Bus/CommandMiddlewareInterface.php` — middleware contract

- Required config roots/keys:
  - `cqrs` / `cqrs.commands.map` — explicit deterministic mapping (command FQCN → handler service id)
  - `cqrs` / `cqrs.middleware` — deterministic middleware list

- Required tags:
  - `error.mapper` — runtime must support mapper discovery (owner typically `platform/errors`)

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Log\LoggerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `platform/cli`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Bus\CommandBusInterface`
  - `Coretsia\Contracts\Bus\CommandHandlerInterface`
  - `Coretsia\Contracts\Bus\CommandMiddlewareInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/cqrs/src/Module/CqrsModule.php`
- [ ] `framework/packages/platform/cqrs/src/Provider/CqrsServiceProvider.php`
- [ ] `framework/packages/platform/cqrs/src/Provider/CqrsServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/cqrs/config/cqrs.php`
- [ ] `framework/packages/platform/cqrs/config/rules.php`
- [ ] `framework/packages/platform/cqrs/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/cqrs/src/Bus/CommandBus.php` — implements `CommandBusInterface`
- [ ] `framework/packages/platform/cqrs/src/Bus/HandlerLocator.php` — deterministic handler lookup from config map
- [ ] `framework/packages/platform/cqrs/src/Bus/Middleware/Pipeline.php` — deterministic middleware chain
- [ ] `framework/packages/platform/cqrs/src/Exception/CommandHandlerNotFoundException.php` — deterministic code `CORETSIA_CQRS_HANDLER_NOT_FOUND`
- [ ] `framework/packages/platform/cqrs/src/Exception/CommandBusProblemMapper.php` — implements `ExceptionMapperInterface` (tag `error.mapper`)
- [ ] `framework/packages/platform/cqrs/src/Provider/Tags.php` — constants (`error.mapper`)
- [ ] `docs/architecture/cqrs.md` — usage + handler mapping + redaction

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0070-command-bus-middleware-pipeline.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/cqrs/composer.json`
- [ ] `framework/packages/platform/cqrs/src/Module/CqrsModule.php` (runtime)
- [ ] `framework/packages/platform/cqrs/src/Provider/CqrsServiceProvider.php` (runtime)
- [ ] `framework/packages/platform/cqrs/config/cqrs.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/platform/cqrs/config/rules.php`
- [ ] `framework/packages/platform/cqrs/README.md`
- [ ] `framework/packages/platform/cqrs/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/cqrs/config/cqrs.php`
- [ ] Keys (dot):
  - [ ] `cqrs.enabled` = true
  - [ ] `cqrs.commands.map` = []
  - [ ] `cqrs.middleware` = []
- [ ] Rules:
  - [ ] `framework/packages/platform/cqrs/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (uses existing `error.mapper`)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Contracts\Bus\CommandBusInterface` → `Coretsia\Cqrs\Bus\CommandBus`
  - [ ] registers: `Coretsia\Cqrs\Exception\CommandBusProblemMapper`
  - [ ] adds tag: `error.mapper` priority `300` meta `{"reason":"maps handler-not-found to deterministic ErrorDescriptor"}`

- Compliance delta (Phase 1 tag-ownership)
  - `platform/cqrs` MUST NOT introduce tag constants for `error.mapper` (owner is `platform/errors`).
  - Therefore: runtime code MUST use the owner constant when allowed by deps, otherwise a package-local mirror constant; raw literal use is documentation-only.

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
- [ ] Context writes (safe only):
  - [ ] none (command payload must not be written)
- [ ] Reset discipline:
  - [ ] any stateful middlewares implement `ResetInterface` + tag `kernel.reset` (if introduced)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `cqrs.command` (attrs allowed: command class as span attr; outcome)
- [ ] Metrics:
  - [ ] `cqrs.command_total` (labels: `outcome`)
  - [ ] `cqrs.command_failed_total` (labels: `outcome`)
  - [ ] `cqrs.command_duration_ms` (labels: `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `kind/op→operation` (only if you ever add operation label; otherwise keep as span attr)
- [ ] Logs:
  - [ ] handler not found → error (command class only; no payload)
  - [ ] command fail → error/warn (no payload)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Cqrs\Exception\CommandHandlerNotFoundException` — errorCode `CORETSIA_CQRS_HANDLER_NOT_FOUND`
- [ ] Mapping:
  - [ ] `Coretsia\Cqrs\Exception\CommandBusProblemMapper` via tag `error.mapper`
- [ ] HTTP status hint policy documented (optional in ErrorDescriptor)
  - [ ] misconfig → `httpStatus=500` (recommended)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] command payload/args
  - [ ] tokens/session id
- [ ] Allowed:
  - [ ] safe ids and class names

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php`
  - N/A (context writes не заявлені)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php`
  - N/A (заявлено умовно “if introduced”)
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  - Evidence: `framework/packages/platform/cqrs/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  - Evidence: `framework/packages/platform/cqrs/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` + integration tests (failure paths)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture wiring:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` (system/E2E wiring as listed below)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/cqrs/tests/Unit/MiddlewareOrderIsDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/cqrs/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/cqrs/tests/Integration/CommandDispatchCallsHandlerTest.php`
  - [ ] `framework/packages/platform/cqrs/tests/Integration/CommandBusProblemMapperWiresTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables list exists and paths are correct
- [ ] Preconditions satisfied (no forward references)
- [ ] Dependency rules satisfied (deptrac, no forbidden deps, no cycles)
- [ ] Observability policy satisfied (no payload as labels; no high-cardinality)
- [ ] Determinism: handler lookup deterministic; middleware order deterministic
- [ ] Tests: unit + contract + integration pass
- [ ] Docs: README + `docs/architecture/cqrs.md` complete
- [ ] What problem this epic solves
  - [ ] Реалізувати `CommandBusInterface` з deterministic handler lookup та middleware pipeline
  - [ ] Забезпечити deterministic error mapping для “handler not found” через `error.mapper`
  - [ ] Забезпечити noop-safe observability (без payload у logs/metrics)
- [ ] Non-goals / out of scope
  - [ ] CLI команди cqrs:* (можуть бути пізніше; не блокують)
  - [ ] Автоматичне сканування handlers (заборонено; тільки explicit map)
  - [ ] Будь-які HTTP залежності
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/errors` executes mappers tagged `error.mapper`
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] Discovery / wiring is via tags:
  - [ ] `error.mapper` (cqrs problem mapper)
- [ ] Коли `platform.cqrs` увімкнено, команда dispatch’иться детерміновано у handler, middleware порядок стабільний, а помилки мапляться через canonical error flow.
- [ ] When command is dispatched without a configured handler, then a deterministic error code is produced and rendered consistently by platform/errors adapters.

---

### 5.110.0 coretsia/scheduler — Schedule registry + schedule:run (MUST) [IMPL]

---
type: package
phase: 5
epic_id: "5.110.0"
owner_path: "framework/packages/platform/scheduler/"

package_id: "platform/scheduler"
composer: "coretsia/platform-scheduler"
kind: runtime
module_id: "platform.scheduler"

goal: "Коли `platform.scheduler` увімкнено, `coretsia schedule:run` виконує due tasks детерміновано під lock і обгортає кожну задачу у scheduler-UoW."
provides:
- "Schedule registry (tag-based, deterministic order) + runner (due selection, lock discipline)"
- "Dispatch mode: via command_bus або via queue (policy/config), без compile-time deps на імплементаційні пакети"
- "CLI `schedule:run` + `schedule:list` (discovered via `cli.command` tag)"
- "Базові scheduler expressions (canonical старт): EveryMinute, EveryHour"
- "Observability helper: spans/metrics/logs policy-compliant; redaction"

tags_introduced:
- "scheduler.schedule"

config_roots_introduced:
- "scheduler"

artifacts_introduced: []
adr: "docs/adr/ADR-0071-scheduler-registry-schedule-run.md"
ssot_refs:
- "docs/ssot/config-and-env.md"
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/metrics-policy.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 5.90.0 — Scheduler ports must exist (ScheduleRegistryInterface / ScheduleRunnerInterface / shapes)
  - 5.100.0 — optional at runtime if `scheduler.dispatch.via=command_bus` (policy/config)

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/src/Scheduler/ScheduleRegistryInterface.php`
  - `framework/packages/core/contracts/src/Scheduler/ScheduleRunnerInterface.php`
  - `framework/packages/core/contracts/src/Scheduler/ScheduleDefinition.php`
  - `framework/packages/core/contracts/src/Scheduler/ScheduleRunReport.php`
  - `framework/packages/core/contracts/src/Scheduler/ScheduleProviderInterface.php` — tagged schedule providers contract (typed schedule discovery)
  - `framework/packages/core/contracts/src/Lock/LockFactoryInterface.php` — lock discipline (reuse)

- Required config roots/keys:
  - `scheduler` / `scheduler.dispatch.via` — `'command_bus'|'queue'`
  - `scheduler` / `scheduler.lock.*` — lock key/ttl
  - `scheduler` / `scheduler.registry.mode` — `'tags'|'config'`

- Required tags:
  - `cli.command` — runtime discovery of CLI commands (owner: `platform/cli`)
  - `scheduler.schedule` — contributed schedules registry tag (owner: this epic)

- Required contracts / ports:
  - `Coretsia\Contracts\Lock\LockFactoryInterface`
  - `Coretsia\Contracts\Bus\CommandBusInterface` (optional when dispatch via command_bus)
  - `Coretsia\Contracts\Queue\QueueInterface` (optional when dispatch via queue)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Log\LoggerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- `platform/cli`
- `platform/http`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Scheduler\ScheduleRegistryInterface`
  - `Coretsia\Contracts\Scheduler\ScheduleRunnerInterface`
  - `Coretsia\Contracts\Scheduler\ScheduleDefinition`
  - `Coretsia\Contracts\Scheduler\ScheduleRunReport`
  - `Coretsia\Contracts\Bus\CommandBusInterface` (optional)
  - `Coretsia\Contracts\Queue\QueueInterface` (optional)
  - `Coretsia\Contracts\Lock\LockFactoryInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - `schedule:run` → `platform/scheduler` `src/Console/ScheduleRunCommand.php`
  - `schedule:list` → `platform/scheduler` `src/Console/ScheduleListCommand.php`
- HTTP:
  - N/A
- Kernel hooks/tags:
  - Scheduler executes tasks as UoW type `scheduler` using `core/kernel` KernelRuntime
- Artifacts:
  - reads: N/A
  - writes: N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/scheduler/src/Module/SchedulerModule.php`
- [ ] `framework/packages/platform/scheduler/src/Provider/SchedulerServiceProvider.php`
- [ ] `framework/packages/platform/scheduler/src/Provider/SchedulerServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/scheduler/config/scheduler.php`
- [ ] `framework/packages/platform/scheduler/config/rules.php`
- [ ] `framework/packages/platform/scheduler/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/scheduler/src/Provider/Tags.php`
  - Allowed:
    - `public const SCHEDULER_SCHEDULE = 'scheduler.schedule';`
  - Forbidden (not owner):
    - any constants for `'cli.command'` or other tags owned by other packages.

- [ ] `framework/packages/platform/scheduler/src/Scheduler/ScheduleRegistry.php` — implements `ScheduleRegistryInterface` (tag-based, deterministic)
- [ ] `framework/packages/platform/scheduler/src/Scheduler/ScheduleRunner.php` — implements `ScheduleRunnerInterface` (lock, due selection, dispatch)
- [ ] `framework/packages/platform/scheduler/src/Scheduler/Expression/EveryMinute.php` — simple expression
- [ ] `framework/packages/platform/scheduler/src/Scheduler/Expression/EveryHour.php` — simple expression
- [ ] `framework/packages/platform/scheduler/src/Console/ScheduleRunCommand.php` — CLI command (safe JSON)
- [ ] `framework/packages/platform/scheduler/src/Console/ScheduleListCommand.php` — CLI command (safe JSON)
- [ ] `framework/packages/platform/scheduler/src/Observability/SchedulerInstrumentation.php` — spans/metrics/log policy helper
- [ ] `docs/architecture/scheduler.md` — dispatch modes, lock, determinism, redaction

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0071-scheduler-registry-schedule-run.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/scheduler/composer.json`
- [ ] `framework/packages/platform/scheduler/src/Module/SchedulerModule.php` (runtime)
- [ ] `framework/packages/platform/scheduler/src/Provider/SchedulerServiceProvider.php` (runtime)
- [ ] `framework/packages/platform/scheduler/config/scheduler.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/platform/scheduler/config/rules.php`
- [ ] `framework/packages/platform/scheduler/README.md`
- [ ] `framework/packages/platform/scheduler/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/scheduler/config/scheduler.php`
- [ ] Keys (dot):
  - [ ] `scheduler.enabled` = true
  - [ ] `scheduler.timezone` = 'UTC'
  - [ ] `scheduler.lock.key` = 'scheduler:run'
  - [ ] `scheduler.lock.ttl_seconds` = 60
  - [ ] `scheduler.dispatch.via` = 'command_bus'  # 'command_bus'|'queue'
  - [ ] `scheduler.registry.mode` = 'tags'        # 'tags'|'config'
  - [ ] `scheduler.max_tasks_per_run` = 100
- [ ] Rules:
  - [ ] `framework/packages/platform/scheduler/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/scheduler/src/Provider/Tags.php` (constants)
  - [ ] constants:
    - [ ] `SCHEDULER_SCHEDULE = 'scheduler.schedule'`
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Contracts\Scheduler\ScheduleRegistryInterface` → `Coretsia\Scheduler\Scheduler\ScheduleRegistry`
  - [ ] registers: `Coretsia\Contracts\Scheduler\ScheduleRunnerInterface` → `Coretsia\Scheduler\Scheduler\ScheduleRunner`
  - [ ] registers: `Coretsia\Scheduler\Console\ScheduleRunCommand`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"schedule:run"}`
  - [ ] registers: `Coretsia\Scheduler\Console\ScheduleListCommand`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"schedule:list"}`
  - [ ] schedules contributed by other packages via tag: `scheduler.schedule` (deterministic order)

- ServiceProvider MUST use literal `'cli.command'` when tagging CLI commands.
- Schedules contributed by other packages via tag `scheduler.schedule` MUST be services implementing `Coretsia\Contracts\Scheduler\ScheduleProviderInterface`.
- `ScheduleRegistry` MUST enumerate providers ONLY via `TagRegistry::all('scheduler.schedule')` and MUST NOT re-sort/re-dedupe.

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- [ ] Context writes (safe only):
  - [ ] none (do not write schedule args)
- [ ] Reset discipline:
  - [ ] stateful runner components (if any) implement `ResetInterface` + tag `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `scheduler.run` (attrs: `due_count`, `executed_count`, `failed_count`)
  - [ ] `scheduler.task` (attrs: schedule id as span attr; outcome)
- [ ] Metrics:
  - [ ] `scheduler.run_total` (labels: `outcome`)
  - [ ] `scheduler.run_duration_ms` (labels: `outcome`)
  - [ ] `scheduler.task_total` (labels: `driver`, `outcome`)
  - [ ] `scheduler.task_duration_ms` (labels: `driver`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`
- [ ] Logs:
  - [ ] summary counts only; no args/payload
  - [ ] lock contention logged with `hash(lock_key)` only

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/scheduler/src/Exception/SchedulerException.php` — errorCode(s): `CORETSIA_SCHEDULER_LOCK_FAILED`, `CORETSIA_SCHEDULER_TASK_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] schedule args
  - [ ] raw lock key
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php`
  - N/A (context writes не заявлені)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php`
  - Evidence (existing tests below): `CrossCuttingNoopDoesNotThrowTest.php` + integration lock tests (reset дисципліна неявна; якщо вводите `kernel.reset`, потрібен явний тест)
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  - Evidence: `framework/packages/platform/scheduler/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` + integration tests
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  - Evidence: integration tests (CLI outputs + lock contention logging)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/...` (wiring for `schedule:run` fixture test)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/scheduler/tests/Unit/DueOrderingDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/scheduler/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/scheduler/tests/Integration/ScheduleRunDueDispatchesCommandTest.php`
  - [ ] `framework/packages/platform/scheduler/tests/Integration/LockPreventsParallelRunTest.php`
  - [ ] `framework/packages/platform/scheduler/tests/Integration/ScheduleOrderDeterministicTest.php`
  - [ ] `framework/packages/platform/scheduler/tests/Integration/Cli/ScheduleRunWorksInEnterprisePresetTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Observability policy satisfied (no schedule_id as metric label; redaction)
- [ ] Determinism: due ordering deterministic; lock handling deterministic
- [ ] Tests: unit + contract + integration pass; fixture proves `schedule:run` works
- [ ] Docs: README + `docs/architecture/scheduler.md` complete
- [ ] What problem this epic solves
  - [ ] Реалізувати scheduler runner з deterministic due ordering і lock discipline через `LockFactoryInterface`
  - [ ] Підтримати dispatch via command_bus або via queue (policy/config), не тягнучи імплементаційні пакети як deps
  - [ ] Додати CLI `schedule:run` та `schedule:list` (discovered via `cli.command` tag)
- [ ] Non-goals / out of scope
  - [ ] Distributed scheduler/leader election (Phase 3+)
  - [ ] Cron expression full parser (можна почати з simple expressions family)
  - [ ] Використання `schedule_id` як metric label (заборонено; тільки span/log attrs)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/lock` provides lock factory implementation
  - [ ] `platform/cqrs` provides command bus implementation (if dispatch.via=command_bus)
  - [ ] `platform/queue` provides queue implementation (if dispatch.via=queue)
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] Discovery / wiring is via tags:
  - [ ] `scheduler.schedule` (schedule definitions, deterministic)
  - [ ] `cli.command` (schedule:* commands)
  - [ ] KernelRuntime UoW is used for each executed task (type=`scheduler`)
- [ ] Коли `platform.scheduler` увімкнено, `coretsia schedule:run` виконує due tasks детерміновано під lock і обгортає кожну задачу у scheduler-UoW.
- [ ] When two scheduler runs overlap, then lock prevents the second run and outputs safe deterministic status without executing tasks.

---

### 5.120.0 Enterprise fixture E2E (RC MUST) (MUST) [IMPL]

---
type: skeleton
phase: 5
epic_id: "5.120.0"
owner_path: "framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/"

goal: "Один POST `/api/checkout` створює order у SQLite, пушить deferred event, listener ставить job у queue, і `coretsia queue:work --once` виконує side-effect — все без витоку PII/секретів і з deterministic поведінкою."
provides:
- "Phase-5 vertical slice “proof”: HTTP → routing/http-app → session/auth/csrf → CQRS → events(deferred) → queue worker → side-effect"
- "Доказ UoW/reset дисципліни (no context leak) для HTTP та queue worker"
- "Фіксація cross-cutting policy: observability + redaction (no payload/PII)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/config-and-env.md"
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/metrics-policy.md"
- "docs/ssot/modes.md"
- "docs/ssot/modules-and-manifests.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A (fixture агрегує вже наявні модулі Phase 5)

- Required deliverables (exact paths):
  - `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/` — fixture skeleton root
  - `framework/packages/core/kernel/` — KernelRuntime/UoW/reset дисципліна
  - `framework/packages/platform/queue/src/Console/QueueWorkCommand.php` — `coretsia queue:work --once` entry point
  - `framework/packages/platform/cqrs/` — command dispatch path (якщо fixture uses CQRS)
  - `framework/packages/platform/events/` — deferred flush after UoW (policy)
  - `framework/packages/platform/http-app/` + `framework/packages/platform/routing/` — HTTP routing pipeline

- Required config roots/keys:
  - `app.id` — canonical app id for cache paths
  - `events.deferred.enabled` — deferred events enabled
  - `database.default` — sqlite default
  - `queue.default` — sync/db (залежно від наявних реалізацій у Phase 5)

- Required tags:
  - HTTP middleware slots/tags/priorities (SSoT catalog):
    - `http.middleware.app_pre`
    - `http.middleware.app`
  - `events.listener`
  - `cli.command`
  - `kernel.hook.after_uow`

- Required contracts / ports:
  - N/A (fixture is system-level wiring; використовує модулі)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- none

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - none
- Contracts:
  - none
- Foundation stable APIs:
  - none

### Entry points / integration points (MUST)

- CLI:
  - `coretsia routes:compile` → `platform/routing` (owner)
  - `coretsia config:compile` → `platform/cli` (owner)
  - `coretsia queue:work --once` → `platform/queue` `src/Console/QueueWorkCommand.php`
- HTTP:
  - routes: `POST /api/checkout` → `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/src/Http/Controller/CheckoutController.php`
  - middleware slots/tags:
    - `http.middleware.app_pre`:
      - `\Coretsia\Session\Http\Middleware\SessionMiddleware::class` priority `300`
      - `\Coretsia\Auth\Http\Middleware\AuthMiddleware::class` priority `200`
      - `\Coretsia\Security\Http\Middleware\CsrfMiddleware::class` priority `100`
    - `http.middleware.app`:
      - `\Coretsia\HttpApp\Middleware\RouterMiddleware::class` priority `100`
- Kernel hooks/tags:
  - `kernel.hook.after_uow` — used by `platform/events` to flush deferred events
- Artifacts:
  - writes:
    - `skeleton/var/cache/<appId>/routes.php`
    - `skeleton/var/cache/<appId>/config.php`
    - `skeleton/var/cache/<appId>/module-manifest.php`
    - `skeleton/var/cache/<appId>/container.php`
  - reads:
    - runtime reads those artifacts in prod-policy boot for the fixture harness

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` — enables required modules for fixture
- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/app.php` — fixture app config (if needed)
- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/database.php` — sqlite config (fixture-local overrides)
- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/migrations.php` — append migrations paths deterministically
- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/src/Http/Controller/CheckoutController.php` — POST `/api/checkout`
- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/src/Cqrs/Command/CheckoutCommand.php` — command object (json-like args)
- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/src/Cqrs/Handler/CheckoutHandler.php` — writes order row + pushes deferred event
- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/src/Events/OrderPlaced.php` — domain event (no PII payload)
- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/src/Events/Listener/EnqueueSendConfirmationListener.php` — listener enqueues job (no payload logs)
- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/src/Queue/Job/SendConfirmationJob.php` — job definition (safe, no PII payload)
- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/src/Queue/Handler/SendConfirmationJobHandler.php` — side-effect marker (FS or DB)
- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/database/migrations/2026_...._create_orders_table.php` — orders table for fixture
- [ ] `docs/architecture/phase-enterprise-e2e.md` — scenario overview + commands to run + redaction notes

#### Modifies

N/A

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php`
- [ ] Keys (dot):
  - [ ] `app.id` = 'enterprise_fixture' (or canonical app id used in cache paths)
  - [ ] `database.default` = 'sqlite'
  - [ ] `queue.default` = 'sync' (or 'db' if queue backend enabled in fixture)
  - [ ] `events.deferred.enabled` = true
  - [ ] `scheduler.enabled` = false (unless extended)
- [ ] Rules:
  - [ ] none

#### Wiring / DI tags (when applicable)

N/A (fixture uses existing module wiring + SSoT middleware catalog + module enablement)

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `skeleton/var/cache/<appId>/routes.php` (compiled routes)
  - [ ] `skeleton/var/cache/<appId>/config.php` (compiled config)
  - [ ] `skeleton/var/cache/<appId>/module-manifest.php` (compiled module plan)
- [ ] Reads:
  - [ ] runtime reads those artifacts in prod-policy boot for the fixture harness
- [ ] Runtime writes (excluded from fingerprint):
  - [ ] `skeleton/var/tmp/*`
  - [ ] `skeleton/var/sessions/*`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
  - [ ] `ContextKeys::ACTOR_ID` (optional if auth enabled)
- [ ] Context writes (safe only):
  - [ ] `ContextKeys::ACTOR_ID` (written by AuthMiddleware; safe id only)
- [ ] Reset discipline:
  - [ ] identity store / deferred queue / worker runtime state do not leak between UoW (proved by tests)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `http.*` spans via http/http-app (noop-safe)
  - [ ] `cqrs.command`, `events.dispatch`, `queue.job`
- [ ] Metrics:
  - [ ] no high-cardinality labels; fixture assertions do not rely on forbidden labels
- [ ] Logs:
  - [ ] no secrets/PII/payload; only safe ids + `hash/len`

#### Errors

- Exceptions introduced:
  - N/A (fixture uses platform mappers + default fallback)
- [ ] Mapping:
  - [ ] errors rendered through ProblemDetails middleware (RFC7807) deterministically

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/raw SQL/payload
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id`)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php`
  - Evidence: `framework/tools/tests/Integration/E2E/UowResetNoContextLeakBetweenRequestsTest.php`
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php`
  - Evidence: `UowResetNoContextLeakBetweenRequestsTest.php` (system-level proof)
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  - Evidence: E2E tests assert safe behavior; policy validated indirectly via “no leaks” constraints
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  - Evidence: E2E tests must fail if payload/PII appears in outputs/logs

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/...`
- [ ] Fixture wiring:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/HybridApp/config/modules.php` (for reset/leak test per original text)

### Tests (MUST)

- Unit/Contract:
  - N/A
- Integration (system/E2E):
  - [ ] `framework/tools/tests/Integration/E2E/CheckoutCreatesOrderRowTest.php`
  - [ ] `framework/tools/tests/Integration/E2E/QueueWorkerProcessesConfirmationJobTest.php`
  - [ ] `framework/tools/tests/Integration/E2E/DeferredEventsFlushOnlyOnSuccessTest.php`
  - [ ] `framework/tools/tests/Integration/E2E/UowResetNoContextLeakBetweenRequestsTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)
  - [ ] gates remain green (fixture wiring does not introduce forbidden deps)

### DoD (MUST)

- [ ] Deliverables list exists and paths are correct
- [ ] Preconditions satisfied (no forward references)
- [ ] Determinism: rerun E2E tests stable; no random output dependencies
- [ ] Observability policy satisfied (no forbidden labels; no secrets/PII)
- [ ] Security/redaction satisfied (no Authorization/Cookie/session/token/raw SQL/payload)
- [ ] Tests: integration pass and prove wiring works end-to-end
- [ ] Docs: `docs/architecture/phase-enterprise-e2e.md` explains how to run scenario + redaction rules
- [ ] What problem this epic solves
  - [ ] Дати “доказ системи” Phase 2: HTTP → routing/http-app → session/auth/csrf → CQRS → events(deferred) → queue worker → side-effect
  - [ ] Перевірити UoW/reset дисципліну для HTTP і queue worker (no context leak)
  - [ ] Зацементувати cross-cutting: observability policy + redaction (no payload/PII)
- [ ] Non-goals / out of scope
  - [ ] Production-ready бізнес-домен (це лише fixture)
  - [ ] Реальні integrations (smtp/redis/exporters) — не потрібні для E2E
  - [ ] Розширений UI або view templates
- [ ] Usually present when enabled in presets/bundles:
  - [ ] Required modules for Enterprise fixture:
    - [ ] `platform.http`, `platform.problem-details`, `platform.errors`, `platform.logging`, `platform.tracing`, `platform.metrics`
    - [ ] `platform.routing`, `platform.http-app`
    - [ ] `platform.filesystem`, `integrations.filesystem-local` (if side-effect uses FS)
    - [ ] `platform.database`, `integrations.database-sqlite`, `platform.migrations`
    - [ ] `platform.session`, `platform.auth`, `platform.security`
    - [ ] `platform.cqrs`, `platform.events`, `platform.queue`
- [ ] Discovery / wiring is via tags:
  - [ ] HTTP middleware tags/slots/priorities (from SSoT middleware catalog):
    - [ ] `http.middleware.app_pre`:
      - [ ] `\Coretsia\Session\Http\Middleware\SessionMiddleware::class` priority `300` (auto)
      - [ ] `\Coretsia\Auth\Http\Middleware\AuthMiddleware::class` priority `200` (auto)
      - [ ] `\Coretsia\Security\Http\Middleware\CsrfMiddleware::class` priority `100` (auto)
    - [ ] `http.middleware.app`:
      - [ ] `\Coretsia\HttpApp\Middleware\RouterMiddleware::class` priority `100` (auto)
  - [ ] `events.listener` (Enterprise fixture contributes listener if needed)
  - [ ] `cli.command` (queue:* commands from platform/queue; schedule:* optional)
  - [ ] `kernel.hook.after_uow` (events deferred flush)
- [ ] Один POST `/api/checkout` створює order у SQLite, пушить deferred event, listener ставить job у queue, і `coretsia queue:work --once` виконує side-effect — все без витоку PII/секретів і з deterministic поведінкою.
- [ ] When checkout succeeds, then deferred event flushes and enqueues job; when queue worker runs once, then confirmation marker appears; rerun is deterministic.

---

### 5.130.0 Asynchronous Performance Tuning (SHOULD) [IMPL+TOOLING]

---
type: tools
phase: 5
epic_id: "5.130.0"
owner_path: "framework/tools/benchmarks/async/"

goal: "Зацементувати async benchmark suite для events/queue/scheduler та прибрати найдорогіші накладні витрати на dispatch, serialization/deserialization і UoW-boundary без порушення determinism та redaction policy."
provides:
- "Reference async benchmarks for event dispatch, deferred flush, queue dispatch/worker, scheduler run"
- "Pinned-runner performance baselines for asynchronous subsystems"
- "Concrete tuning targets for payload shaping, worker loop overhead and scheduler execution path"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/metrics-policy.md"
- "docs/ssot/observability-and-errors.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

> Everything that MUST already exist (files/keys/tags/contracts/artifacts).

- Epic prerequisites:
  - 5.40.0 — events runtime exists
  - 5.60.0 — queue core exists
  - 5.80.0 — queue CLI commands exist
  - 5.100.0 — command bus exists
  - 5.110.0 — scheduler runtime exists
  - 1.460.0 — base performance gate methodology exists
  - 4.240.0 — DB/FS gate exists when async paths use SQLite-backed queue or schedule persistence

- Required deliverables (exact paths):
  - `framework/packages/platform/events/`
  - `framework/packages/platform/queue/`
  - `framework/packages/platform/cqrs/`
  - `framework/packages/platform/scheduler/`
  - `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/`

- Required config roots/keys:
  - `events.*`
  - `queue.*`
  - `scheduler.*`
  - `app.id`

- Required tags:
  - `events.listener`
  - `kernel.hook.after_uow`
  - `cli.command`
  - `scheduler.schedule`

- Required contracts / ports:
  - `Coretsia\Contracts\Events\EventDispatcherInterface`
  - `Coretsia\Contracts\Queue\QueueInterface`
  - `Coretsia\Contracts\Scheduler\ScheduleRunnerInterface`
  - `Coretsia\Contracts\Bus\CommandBusInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Psr\Log\LoggerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (tools epic driving benchmark/tuning work)

Forbidden:

- emitting raw event/job payloads in benchmark output
- introducing non-reset-safe mutable caches into worker/runtime state
- using shared-runner CI timings as release gate source of truth

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Events\EventDispatcherInterface`
  - `Coretsia\Contracts\Queue\QueueInterface`
  - `Coretsia\Contracts\Scheduler\ScheduleRunnerInterface`
  - `Coretsia\Contracts\Bus\CommandBusInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface`

### Entry points / integration points (MUST)

- CLI:
  - `composer benchmark:async`
  - `composer benchmark:async:gate`

- HTTP:
  - N/A

- Kernel hooks/tags:
  - benchmark suite MUST cover:
    - `events.listener`
    - `kernel.hook.after_uow`
    - `scheduler.schedule`

- Other runtime discovery / integration tags:
  - `cli.command` — queue/scheduler commands may be used as benchmark entrypoints

- Artifacts:
  - reads: `framework/tools/benchmarks/async/async.baseline.json`
  - writes: `framework/tools/benchmarks/async/async.report.json`

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/benchmarks/async/run.php` — canonical async benchmark runner
- [ ] `framework/tools/benchmarks/async/AsyncBenchmarkConfig.php` — scenario list + methodology
- [ ] `framework/tools/benchmarks/async/async.baseline.json` — pinned baseline
- [ ] `framework/tools/gates/async_performance_gate.php` — comparator gate
- [ ] `framework/tools/tests/Integration/Benchmarks/AsyncBenchmarkHarnessTest.php`
- [ ] `framework/tools/tests/Integration/Gates/AsyncPerformanceGateTest.php`
- [ ] `docs/architecture/async-performance.md` — tuning guide + benchmark interpretation

#### Modifies

- [ ] `framework/packages/platform/events/src/` — reduce dispatch/deferred overhead without semantic drift
- [ ] `framework/packages/platform/queue/src/` — reduce serialization/worker overhead without semantic drift
- [ ] `framework/packages/platform/scheduler/src/` — reduce schedule enumeration/run overhead without semantic drift
- [ ] `framework/packages/platform/events/tests/` — behavior-lock tests for tuned paths
- [ ] `framework/packages/platform/queue/tests/` — behavior-lock tests for tuned paths
- [ ] `framework/packages/platform/scheduler/tests/` — behavior-lock tests for tuned paths
- [ ] `framework/composer.json` — add scripts:
  - [ ] `benchmark:async`
  - [ ] `benchmark:async:gate`
- [ ] `composer.json` — add mirror scripts delegating to `framework/`
- [ ] `.github/workflows/ci.yml` — add dedicated async benchmark job
- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_ASYNC_PERFORMANCE_DEGRADED`
  - [ ] `CORETSIA_ASYNC_PERFORMANCE_RUN_FAILED`

#### Package skeleton (if type=package)

N/A

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/tools/benchmarks/async/AsyncBenchmarkConfig.php`
- [ ] Keys (dot):
  - [ ] `benchmark.async.events.dispatch_sync` = true
  - [ ] `benchmark.async.events.flush_deferred` = true
  - [ ] `benchmark.async.queue.dispatch_sync` = true
  - [ ] `benchmark.async.queue.worker_once` = true
  - [ ] `benchmark.async.scheduler.run_one_due_task` = true
  - [ ] `benchmark.async.threshold_multiplier` = 1.15
- [ ] Rules:
  - [ ] scenarios MUST benchmark both empty/light and representative listener/job/task counts
  - [ ] payloads used in scenarios MUST remain json-like and redaction-safe

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `framework/tools/benchmarks/async/async.report.json` (deterministic bytes)
- [ ] Reads:
  - [ ] validates report/baseline schema before compare

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] tuned runtime services with mutable state MUST implement `ResetInterface`
  - [ ] worker loop and deferred queues MUST prove no state leak across runs/UoW

#### Observability (policy-compliant)

- [ ] Logs:
  - [ ] benchmark output MUST contain only scenario ids, counts, medians, thresholds, outcome
  - [ ] MUST NOT include raw event payloads, job payloads, or schedule arguments

#### Errors

- Exceptions introduced:
  - N/A
- [ ] Mapping:
  - [ ] deterministic tool exit codes only

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw event/job payloads
  - [ ] secrets/PII
  - [ ] absolute paths
- [ ] Allowed:
  - [ ] safe scenario ids
  - [ ] counts
  - [ ] medians

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If `kernel.reset` used → events/queue/scheduler integration tests MUST fail if reset discipline is removed
- [ ] If logs exist → async harness/gate tests assert no raw payload leakage
- [ ] If redaction exists → `framework/tools/tests/Integration/Gates/AsyncPerformanceGateTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/`
- [ ] Fake adapters:
  - [ ] FakeTracer
  - [ ] FakeMetrics
  - [ ] FakeLogger

### Tests (MUST)

- Unit:
  - [ ] `framework/tools/tests/Unit/Benchmarks/AsyncBenchmarkConfigTest.php`
- Contract:
  - [ ] `framework/packages/platform/queue/tests/Contract/TuningPreservesSerializationPolicyContractTest.php`
  - [ ] `framework/packages/platform/events/tests/Contract/TuningPreservesDeferredFlushPolicyContractTest.php`
- Integration:
  - [ ] `framework/tools/tests/Integration/Benchmarks/AsyncBenchmarkHarnessTest.php`
  - [ ] `framework/tools/tests/Integration/Gates/AsyncPerformanceGateTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] payload shaping remains stable
  - [ ] UoW/reset semantics remain stable
  - [ ] benchmark reports are stable bytes
- [ ] Downstream proof:
  - [ ] representative async scenarios show neutral or improved medians on pinned runner
- [ ] Docs updated:
  - [ ] `docs/architecture/async-performance.md`
