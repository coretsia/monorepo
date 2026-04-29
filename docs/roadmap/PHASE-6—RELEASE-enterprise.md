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

## PHASE 6+ — RELEASE: enterprise (extensions, Non-product doc)

### 6.10.0 coretsia/health (real owner for `/health*`) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.10.0"
owner_path: "framework/packages/platform/health/"

package_id: "platform/health"
composer: "coretsia/platform-health"
kind: runtime
module_id: "platform.health"

goal: "When `platform.health` is enabled, `/health*` is no longer 501: an owner PSR-15 middleware returns a deterministic report and runs checks in deterministic order."
provides:
- "Owns `/health`, `/health/live`, `/health/ready` without breaking transport boundary (`platform/http` must not depend on health)."
- "Canonical health registry via DI tag `health.check` (deterministic order) + deterministic JSON-like report shape."
- "Reference checks (1–2) to prove liveness/readiness wiring and ordering."

tags_introduced:
- "health.check"

config_roots_introduced:
- "health"

artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/tags.md"
- "docs/ssot/config-roots.md"
- "docs/ssot/observability.md"
- "docs/ssot/http-middleware-catalog.md"
- "docs/ssot/context-keys.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `platform/http` — provides and executes middleware slot/tag `http.middleware.system_pre` (health integrates ONLY via tag; no compile-time dep).
  - `core/contracts` — provides observability + health ports referenced below.
  - `core/foundation` — provides `DeterministicOrder` + `ContextAccessorInterface`.

- Required config roots/keys:
  - `health.*` — this epic introduces the root; consumers must read via global config under `health` root (config file returns subtree, no repeated root).

- Required tags:
  - `http.middleware.system_pre` — must exist and be executed by HTTP stack (health does not own this tag).

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Health\HealthCheckInterface` — health check contract.
  - `Coretsia\Contracts\Observability\Health\HealthStatus` — status enum/value object.
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — noop-safe tracer port.
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — noop-safe meter port.

- Constraints / out of scope:
  - No per-package filesystem scanning without deterministic sort.
  - No compile-time dependency on `platform/http` (integration only via tags).
  - No “every package implements checks” requirement — only registry + reference checks.

- Cutline impact: none.

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
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\ResponseFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Health\HealthCheckInterface`
  - `Coretsia\Contracts\Observability\Health\HealthStatus`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextKeys`
  - `Coretsia\Foundation\Serialization\StableJsonEncoder`   # for deterministic JSON bytes, float-forbidden
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - routes: `GET /health`, `GET /health/live`, `GET /health/ready` → `platform/health` `\Coretsia\Health\Http\Middleware\HealthEndpointsMiddleware`
  - middleware slots/tags: `http.middleware.system_pre` priority `600` meta `{"owner":"platform/health","paths":"/health*"}`
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Health checks selection & order (single-choice, deterministic)

- Discovery source: **DI tag** `health.check` (owner `platform/health` per `docs/ssot/tags.md`).
- Base order: **exactly** as returned by `TagRegistry->all('health.check')` (priority DESC, id ASC).  
  `platform/health` **MUST NOT** re-sort or dedupe again.
- Liveness/readiness subset:
  - `health.checks.liveness` / `health.checks.readiness` are `list<string>` of **serviceId/FQCN**.
  - If the list is **empty** → run **all** discovered checks in TagRegistry order.
  - If the list is **non-empty** → run **only listed checks** in **list order**; any missing id → deterministic fail `CORETSIA_HEALTH_CHECK_UNKNOWN`.
- Report encoding for HTTP response: **StableJsonEncoder** (sorted map keys recursively, lists preserved, LF-only, final newline; floats forbidden).

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/health/composer.json` — package manifest
- [ ] `framework/packages/platform/health/src/Module/HealthModule.php` — runtime module entry
- [ ] `framework/packages/platform/health/src/Provider/HealthServiceProvider.php` — wiring
- [ ] `framework/packages/platform/health/src/Provider/HealthServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/health/src/Provider/Tags.php` — tag constants (`health.check`)
- [ ] `framework/packages/platform/health/config/health.php` — config subtree (root: `health`)
- [ ] `framework/packages/platform/health/config/rules.php` — config shape rules
- [ ] `framework/packages/platform/health/README.md` — usage + Observability / Errors / Security-Redaction
- [ ] `framework/packages/platform/health/src/Health/HealthCheckRegistry.php` — collects tagged checks (DeterministicOrder)
- [ ] `framework/packages/platform/health/src/Health/HealthReport.php` — deterministic report VO (stable order)
- [ ] `framework/packages/platform/health/src/Health/Checks/PhpExtensionsCheck.php` — reference check
- [ ] `framework/packages/platform/health/src/Http/Middleware/HealthEndpointsMiddleware.php` — owns `/health*` (PSR-15, short-circuit)
- [ ] `framework/packages/platform/health/src/Exception/HealthException.php` — deterministic error codes
- [ ] `docs/architecture/health.md` — endpoints + checks tag + ordering + override notes
- [ ] `framework/packages/platform/health/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — noop-safe proof
- [ ] `framework/packages/platform/health/tests/Unit/HealthReportOrderIsDeterministicTest.php` — determinism proof
- [ ] `framework/packages/platform/health/tests/Integration/ChecksRunInDeterministicOrderTest.php` — ordering proof
- [ ] `framework/packages/platform/health/tests/Integration/HealthEndpointsReturn200Or503WhenEnabledTest.php` — endpoint policy proof
- [ ] `framework/packages/platform/health/tests/Integration/Http/HealthOwnedWhenModuleEnabledTest.php` — ownership/no-501 proof

#### Modifies

- [ ] `docs/ssot/config-roots.md` — add root row for `health` (owner `platform/health`, defaults `framework/packages/platform/health/config/health.php`, rules `framework/packages/platform/health/config/rules.php`)
- [ ] `docs/ssot/http-middleware-catalog.md` — add entry for HealthEndpointsMiddleware in `http.middleware.system_pre` (priority 600, owner platform/health, toggle `health.http.enabled`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/health/composer.json`
- [ ] `framework/packages/platform/health/src/Module/HealthModule.php`
- [ ] `framework/packages/platform/health/src/Provider/HealthServiceProvider.php`
- [ ] `framework/packages/platform/health/config/health.php`
- [ ] `framework/packages/platform/health/config/rules.php`
- [ ] `framework/packages/platform/health/README.md`
- [ ] `framework/packages/platform/health/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/health/config/health.php`
- [ ] Keys (dot):
  - [ ] `health.enabled` = true
  - [ ] `health.http.enabled` = true
  - [ ] `health.http.prefix` = "/health"
  - [ ] `health.checks.liveness` = []
  - [ ] `health.checks.readiness` = []
- [ ] Rules:
  - [ ] `framework/packages/platform/health/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/health/src/Provider/Tags.php` (constants)
  - [ ] constants:
    - [ ] `HEALTH_CHECK = "health.check"`
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Health\Http\Middleware\HealthEndpointsMiddleware::class`
  - [ ] adds tag: `http.middleware.system_pre` priority `600` meta `{"owner":"platform/health","paths":"/health*"}`
  - [ ] registers: `\Coretsia\Health\Health\HealthCheckRegistry::class`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- [ ] Context writes (safe only):
  - [ ] none (health must not write secrets/PII; only reads)
- [ ] Reset discipline:
  - [ ] registry/stateful checks (if any) implement `ResetInterface`
  - [ ] stateful services tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `health.check` (attrs: check class, outcome; no PII)
- [ ] Metrics:
  - [ ] `health.check_total` (labels: `outcome`)
  - [ ] `health.check_failed_total` (labels: `outcome`)
  - [ ] `health.check_duration_ms` (labels: `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `kind/op/uow_type→operation` (only if you ever need it; prefer span attr)
- [ ] Logs:
  - [ ] check fail → warn/error (check class + correlation_id), no config dumps; no secrets/PII (hash/len only)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Health\Exception\HealthException` — errorCode `CORETSIA_HEALTH_CHECK_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes); avoid HTTP coupling
- [ ] HTTP status hint policy:
  - [ ] endpoint returns 200/503 by policy; no new ErrorDescriptor mapping required

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] env values, DSNs, credentials, stacktraces in response
- [ ] Allowed:
  - [ ] safe ids + `hash(value)` / `len(value)` (only if needed)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → N/A (no context writes)
- [ ] If `kernel.reset` used → covered by integration wiring tests when reset-tagged services are introduced (no mandatory reset wiring in this epic)
- [ ] If metrics/spans/logs exist → covered by:
  - [ ] `framework/packages/platform/health/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/health/tests/Integration/HealthEndpointsReturn200Or503WhenEnabledTest.php`
- [ ] If redaction exists → covered by:
  - [ ] `framework/packages/platform/health/tests/Integration/HealthEndpointsReturn200Or503WhenEnabledTest.php` (asserts no stacktrace/config/env dumps)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` (if used for boot/wiring)
- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger capture events for assertions (test-only)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/health/tests/Unit/HealthReportOrderIsDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/health/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/health/tests/Integration/ChecksRunInDeterministicOrderTest.php`
  - [ ] `framework/packages/platform/health/tests/Integration/HealthEndpointsReturn200Or503WhenEnabledTest.php`
  - [ ] `framework/packages/platform/health/tests/Integration/Http/HealthOwnedWhenModuleEnabledTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; boundary preserved: no `platform/http` dep)
- [ ] Verification tests present where applicable
- [ ] Determinism: stable report field order + deterministic check order
- [ ] Docs updated:
  - [ ] `framework/packages/platform/health/README.md`
  - [ ] `docs/architecture/health.md`
- [ ] What problem this epic solves
  - [ ] Забирає 501 “until owner installed” для `/health*` без порушення transport boundary (`platform/http` не залежить від health)
  - [ ] Дає канонічний health registry через DI tag `health.check` (deterministic order) + формат детермінованого репорту
  - [ ] Додає PSR-15 middleware owner для `/health`, `/health/live`, `/health/ready` з правильним місцем у HTTP stack (system_pre)
- [ ] Non-goals / out of scope
  - [ ] Реалізація health-check у кожному пакеті (лише реєстр + 1–2 reference checks)
  - [ ] Будь-яке сканування filesystem без deterministic sort
  - [ ] Залежність від `platform/http` (заборонено; інтеграція тільки через DI tag)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/http` executes `http.middleware.system_pre`
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] Discovery / wiring is via tags:
  - [ ] `health.check` (checks registry)
  - [ ] `http.middleware.system_pre` (endpoint middleware)
- [ ] Коли `platform.health` увімкнено, `/health*` більше не 501: endpoint owner middleware повертає deterministic report і запускає checks у deterministic порядку.
- [ ] When module `platform.health` is enabled, then GET `/health/live` returns 200 with deterministic JSON report, and `/health` aggregates checks deterministically.

---

### 6.20.0 coretsia/metrics (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.20.0"
owner_path: "framework/packages/platform/metrics/"

package_id: "platform/metrics"
composer: "coretsia/platform-metrics"
kind: runtime
module_id: "platform.metrics"

goal: "When `platform.metrics` is enabled, `/metrics` is owned by metrics middleware and returns deterministic output with safe fallback; exporter is selected via config without coupling `platform/http` to metrics."
provides:
- "Metrics registry (counters/histograms) with deterministic dump order."
- "SSoT policy enforcement: metric naming + allowlisted label keys only; deterministic label ordering."
- "HTTP `/metrics` endpoint ownership via PSR-15 middleware; stable fallback when exporter is missing."

tags_introduced: []
config_roots_introduced:
- "metrics"

artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/config-roots.md"
- "docs/ssot/observability.md"
- "docs/ssot/http-middleware-catalog.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `platform/http` — provides and executes `http.middleware.system_pre` slot/tag (metrics integrates ONLY via tag; no compile-time dep).
  - `core/contracts` — provides metrics ports (MeterPortInterface + MetricsRendererInterface).
  - `core/foundation` — provides `DeterministicOrder` + `ContextAccessorInterface`.

- Required config roots/keys:
  - `metrics.*` — this epic introduces the root; consumers must read via global config under `metrics` root (config file returns subtree).

- Required tags:
  - `http.middleware.system_pre` — must exist and be executed by HTTP stack (metrics does not own this tag).

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional usage)

- Constraints / out of scope:
  - No compile-time dependency on `platform/http`.
  - No new label keys outside allowlist.
  - Never log metric dumps.
  - Does NOT implement Prometheus renderer (handled by a separate integration epic).

- Cutline impact: none.

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
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\ResponseFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextKeys`
  - `Coretsia\Foundation\Serialization\StableJsonEncoder`   # only if JSON is ever emitted (prefer text/plain for /metrics)
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - routes: `GET /metrics` → `platform/metrics` `\Coretsia\Metrics\Http\Middleware\MetricsEndpointMiddleware`
  - middleware slots/tags: `http.middleware.system_pre` priority `590` meta `{"owner":"platform/metrics","path":"/metrics"}`
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Metrics exporter selection (single-choice, no new tags)

- Selector: `metrics.exporter` (string), default `"null"`.
- `platform/metrics` MUST always provide a safe fallback binding:
  - `MetricsRendererInterface` → `NullMetricsRenderer`.
- Integrations (e.g. `integrations/metrics-prometheus`) MUST implement **conditional override**:
  - if `metrics.exporter === "prometheus"` and the integration module is enabled → bind `MetricsRendererInterface` to its renderer.
  - otherwise → do nothing.
- `platform/metrics` MUST NOT enumerate exporters via tags and MUST NOT introduce `metrics.exporter` tag.

### Deliverables (MUST)

#### Creates

Platform (`coretsia/metrics`):
- [ ] `framework/packages/platform/metrics/composer.json` — package manifest
- [ ] `framework/packages/platform/metrics/src/Module/MetricsModule.php` — runtime module entry
- [ ] `framework/packages/platform/metrics/src/Provider/MetricsServiceProvider.php` — wiring
- [ ] `framework/packages/platform/metrics/src/Provider/MetricsServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/metrics/src/Provider/Tags.php` — (optional) constants for exporter tagging
- [ ] `framework/packages/platform/metrics/config/metrics.php` — config subtree (root: `metrics`)
- [ ] `framework/packages/platform/metrics/config/rules.php` — config shape rules
- [ ] `framework/packages/platform/metrics/README.md` — usage + Observability / Errors / Security-Redaction
- [ ] `framework/packages/platform/metrics/src/Registry/MetricsRegistry.php` — counters/histograms store + deterministic dump
- [ ] `framework/packages/platform/metrics/src/Metric/Counter.php` — counter primitive
- [ ] `framework/packages/platform/metrics/src/Metric/Histogram.php` — histogram/timer primitive
- [ ] `framework/packages/platform/metrics/src/Labels/LabelSet.php` — allowlist enforcement + deterministic ordering
- [ ] `framework/packages/platform/metrics/src/Export/NullMetricsRenderer.php` — stable fallback renderer
- [ ] `framework/packages/platform/metrics/src/Http/Middleware/MetricsEndpointMiddleware.php` — owns `/metrics` (PSR-15)
- [ ] `framework/packages/platform/metrics/src/Exception/MetricsException.php` — deterministic error codes

Docs:
- [ ] `docs/architecture/metrics.md` — endpoint ownership + exporter selection + label policy (base doc, without Prometheus-specific renderer details)

Tests:
- [ ] `framework/packages/platform/metrics/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/platform/metrics/tests/Contract/LabelSetRejectsForbiddenLabelsContractTest.php`
- [ ] `framework/packages/platform/metrics/tests/Unit/LabelSetRejectsForbiddenLabelsTest.php`
- [ ] `framework/packages/platform/metrics/tests/Integration/MetricsEndpointReturnsStableFallbackWhenNoExporterTest.php`
- [ ] `framework/packages/platform/metrics/tests/Integration/MetricsRegistryDeterministicDumpOrderTest.php`
- [ ] `framework/packages/platform/metrics/tests/Integration/Http/MetricsOwnedWhenModuleEnabledTest.php`

#### Modifies

- [ ] `docs/ssot/http-middleware-catalog.md` — add entry for MetricsEndpointMiddleware in `http.middleware.system_pre` (priority 590, owner platform/metrics, toggle `metrics.http.enabled`)

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
  - [ ] `metrics.http.enabled` = true
  - [ ] `metrics.http.path` = "/metrics"
  - [ ] `metrics.exporter` = "null"              # "null"|"<integration-provided>"
  - [ ] `metrics.labels.allowed` = ["method","status","driver","operation","table","outcome"]
- [ ] Rules:
  - [ ] `framework/packages/platform/metrics/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/metrics/src/Provider/Tags.php`
  - [ ] constant(s): `EXPORTER = 'metrics.exporter'` (optional) (if you choose tag-based selection)
- [ ] ServiceProvider wiring evidence:
  - [ ] `platform/metrics` registers: `\Coretsia\Metrics\Http\Middleware\MetricsEndpointMiddleware::class`
  - [ ] `platform/metrics` adds tag: `http.middleware.system_pre` priority `590` meta `{"owner":"platform/metrics","path":"/metrics"}`
  - [ ] `platform/metrics` binds default: `Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface` → `\Coretsia\Metrics\Export\NullMetricsRenderer::class` (fallback)

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
  - [ ] none (endpoint must not mutate context)
- Reset discipline:
  - N/A (registry is process-wide; determinism is enforced by dump ordering)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `metrics.render` (optional; attrs: exporter, outcome)
- [ ] Metrics:
  - [ ] N/A (package must NOT emit high-cardinality labels; `LabelSet` enforces keys at call sites)
- [ ] Label normalization applied (if needed):
  - [ ] `op→operation`, `via→driver` (enforced at call sites; renderer only validates keys)
- [ ] Logs:
  - [ ] exporter issues warn (no dumps); redaction applied; no secrets/PII

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Metrics\Exception\MetricsException` — errorCodes:
    - [ ] `CORETSIA_METRICS_LABEL_FORBIDDEN`
    - [ ] `CORETSIA_METRICS_RENDER_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)
- [ ] HTTP status hint policy:
  - [ ] `/metrics` returns 200 with fallback unless policy says otherwise (avoid coupling to ErrorDescriptor)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] metric samples in logs; secrets/PII
- [ ] Allowed:
  - [ ] deterministic `text/plain` response for exporter output

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → N/A (no context writes)
- [ ] If `kernel.reset` used → N/A
- [ ] If metrics/spans/logs exist → covered by:
  - [ ] `framework/packages/platform/metrics/tests/Unit/LabelSetRejectsForbiddenLabelsTest.php`
  - [ ] `framework/packages/platform/metrics/tests/Contract/LabelSetRejectsForbiddenLabelsContractTest.php`
- [ ] If redaction exists → covered by:
  - [ ] `framework/packages/platform/metrics/tests/Integration/MetricsEndpointReturnsStableFallbackWhenNoExporterTest.php` (asserts stable, no payload dumps)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` (if used for boot/wiring)
- [ ] Fake adapters:
  - [ ] FakeLogger capture warnings (no dump)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/metrics/tests/Unit/LabelSetRejectsForbiddenLabelsTest.php`
- Contract:
  - [ ] `framework/packages/platform/metrics/tests/Contract/LabelSetRejectsForbiddenLabelsContractTest.php`
  - [ ] `framework/packages/platform/metrics/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/metrics/tests/Integration/MetricsEndpointReturnsStableFallbackWhenNoExporterTest.php`
  - [ ] `framework/packages/platform/metrics/tests/Integration/MetricsRegistryDeterministicDumpOrderTest.php`
  - [ ] `framework/packages/platform/metrics/tests/Integration/Http/MetricsOwnedWhenModuleEnabledTest.php`
- Gates/Arch:
  - [ ] `framework/tools/gates/observability_naming_gate.php` updated (optional; if enforcing early)
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (platform/http boundary preserved; no integrations deps)
- [ ] Verification tests present where applicable
- [ ] Determinism: dump order stable; label ordering deterministic
- [ ] Docs updated:
  - [ ] `framework/packages/platform/metrics/README.md`
  - [ ] `docs/architecture/metrics.md`
- [ ] What problem this epic solves
  - [ ] Апгрейд `platform/metrics` з ports+noop до registry + deterministic dump + HTTP endpoint owner `/metrics`
  - [ ] Enforce SSoT policy: metric naming + allowlist labels (`method,status,driver,operation,table,outcome`) + deterministic ordering
  - [ ] Safe fallback: коли exporter відсутній — `/metrics` повертає стабільний текст без exception
- [ ] Non-goals / out of scope
  - [ ] Залежність `platform/http` від metrics (заборонено)
  - [ ] Нові labels поза allowlist (заборонено)
  - [ ] Логування дампу метрик (заборонено)
  - [ ] Prometheus exporter (винесено в окремий integration epic)
- [ ] Discovery / wiring is via tags:
  - [ ] `http.middleware.system_pre` (endpoint middleware)
  - [ ] (optional) `metrics.exporter` (if you choose tag-based exporter selection)
- [ ] When exporter module is not installed, then GET `/metrics` returns a stable fallback output without throwing.

---

### 6.21.0 integrations/metrics-prometheus (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.21.0"
owner_path: "framework/packages/integrations/metrics-prometheus/"

package_id: "integrations/metrics-prometheus"
composer: "coretsia/integrations-metrics-prometheus"
kind: runtime
module_id: "integrations.metrics-prometheus"

goal: "When `integrations.metrics-prometheus` is enabled, it provides a Prometheus text renderer for `MetricsRendererInterface` with deterministic output, without coupling `platform/http` to metrics."
provides:
- "Prometheus text renderer implementing `MetricsRendererInterface`."
- "Deterministic metric name sanitization + label escaping + stable text rendering."
- "Optional exporter-specific config under its own root (does not own `/metrics`; no HTTP deps)."

tags_introduced: []
config_roots_introduced:
- "metrics_prometheus"

artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/config-roots.md"
- "docs/ssot/observability.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `6.20.0` (`platform/metrics`) — provides registry + endpoint owner + selection mechanism and `MetricsRendererInterface` binding point.
  - `core/contracts` — provides `MetricsRendererInterface` contract.
  - `core/foundation` — provides `DeterministicOrder` utilities if needed for stable formatting.

- Required deliverables (exact paths):
  - `framework/packages/platform/metrics/src/Export/NullMetricsRenderer.php` — baseline fallback exists (this integration replaces/bypasses it via selection).
  - `framework/packages/platform/metrics/config/metrics.php` — must contain `metrics.exporter` selection key.

- Required config roots/keys:
  - `metrics.exporter` — must support value `"prometheus"` to select this renderer.
  - `metrics_prometheus.*` — this epic introduces exporter-specific config root (optional; renderer must still work with defaults).

- Constraints / out of scope:
  - No compile-time dependency on `platform/http`.
  - No new label keys outside `metrics.labels.allowed`.
  - Never log metric dumps.

- Cutline impact: none.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `platform/metrics`

Forbidden:
- `platform/http`
- `platform/cli`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

N/A (no CLI/HTTP entry points; integration is pure DI wiring that provides a renderer implementation)

### Integration wiring (single-choice)

- This integration MUST NOT own `/metrics` and MUST NOT depend on `platform/http`.
- Conditional override rule:
  - When `metrics.exporter === "prometheus"` AND `integrations.metrics-prometheus` module is enabled:
    - bind `Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface` → `PrometheusTextRenderer`
  - Otherwise: do nothing (platform fallback remains active).

### Deliverables (MUST)

#### Creates

Integration (`coretsia/metrics-prometheus`):
- [ ] `framework/packages/integrations/metrics-prometheus/composer.json` — integration manifest
- [ ] `framework/packages/integrations/metrics-prometheus/src/Module/MetricsPrometheusModule.php` — runtime module entry
- [ ] `framework/packages/integrations/metrics-prometheus/src/Provider/MetricsPrometheusServiceProvider.php` — wiring
- [ ] `framework/packages/integrations/metrics-prometheus/src/Provider/MetricsPrometheusServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/metrics-prometheus/config/metrics_prometheus.php` — config subtree (root: `metrics_prometheus`)
- [ ] `framework/packages/integrations/metrics-prometheus/config/rules.php` — config shape rules
- [ ] `framework/packages/integrations/metrics-prometheus/README.md` — usage + Observability / Errors / Security-Redaction
- [ ] `framework/packages/integrations/metrics-prometheus/src/Prometheus/PrometheusTextRenderer.php` — implements `MetricsRendererInterface`
- [ ] `framework/packages/integrations/metrics-prometheus/src/Prometheus/PrometheusNameSanitizer.php` — deterministic naming
- [ ] `framework/packages/integrations/metrics-prometheus/src/Prometheus/PrometheusLabelEscaper.php` — deterministic escaping

Docs:
- [ ] `docs/architecture/metrics.md` — add Prometheus exporter section (selection + format guarantees)

Tests:
- [ ] `framework/packages/integrations/metrics-prometheus/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/integrations/metrics-prometheus/tests/Integration/PrometheusRendererProducesDeterministicTextTest.php`

#### Modifies

- [ ] `docs/architecture/metrics.md` — extend with Prometheus renderer semantics + selection details
- [ ] `docs/ssot/config-roots.md` — add root row for `metrics_prometheus` (owner `integrations/metrics-prometheus`, defaults `.../config/metrics_prometheus.php`, rules `.../config/rules.php`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/metrics-prometheus/composer.json`
- [ ] `framework/packages/integrations/metrics-prometheus/src/Module/MetricsPrometheusModule.php`
- [ ] `framework/packages/integrations/metrics-prometheus/src/Provider/MetricsPrometheusServiceProvider.php`
- [ ] `framework/packages/integrations/metrics-prometheus/config/metrics_prometheus.php`
- [ ] `framework/packages/integrations/metrics-prometheus/config/rules.php`
- [ ] `framework/packages/integrations/metrics-prometheus/README.md`
- [ ] `framework/packages/integrations/metrics-prometheus/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/metrics-prometheus/config/metrics_prometheus.php`
- [ ] Keys (dot):
  - [ ] `metrics_prometheus.enabled` = true
  - [ ] `metrics_prometheus.sanitize_names` = true
  - [ ] `metrics_prometheus.escape_labels` = true
- [ ] Rules:
  - [ ] `framework/packages/integrations/metrics-prometheus/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] `integrations/metrics-prometheus` registers: `\Coretsia\MetricsPrometheus\Prometheus\PrometheusTextRenderer::class`
  - [ ] `integrations/metrics-prometheus` binds/exports renderer so that `platform/metrics` can select it when `metrics.exporter="prometheus"` (selection mechanism may be direct binding or registry-based; MUST remain compile-time decoupled from `platform/http`)

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `metrics.render` (optional; attrs: exporter="prometheus", outcome)
- [ ] Label normalization applied (if needed):
  - [ ] `op→operation`, `via→driver` (enforced at call sites; renderer only validates keys)
- [ ] Logs:
  - [ ] warn on render failure without dumps; redaction applied; no secrets/PII/raw samples

#### Errors

- [ ] Exceptions introduced:
  - [ ] N/A (renderer failures must be surfaced via existing `CORETSIA_METRICS_RENDER_FAILED` mapping in `platform/metrics` or handled internally with deterministic fallback behavior)
- Mapping:
  - N/A

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] metric samples in logs; secrets/PII
- [ ] Allowed:
  - [ ] deterministic `text/plain` Prometheus exposition format

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → covered by:
  - [ ] `framework/packages/integrations/metrics-prometheus/tests/Integration/PrometheusRendererProducesDeterministicTextTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] N/A (renderer can be tested as pure unit/integration without HTTP boot)
- Fake adapters:
  - N/A

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/integrations/metrics-prometheus/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/metrics-prometheus/tests/Integration/PrometheusRendererProducesDeterministicTextTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (`platform/http` boundary preserved)
- [ ] Verification tests present where applicable
- [ ] Determinism: Prometheus text output is stable (ordering + escaping + naming)
- [ ] Docs updated:
  - [ ] `framework/packages/integrations/metrics-prometheus/README.md`
  - [ ] `docs/architecture/metrics.md`
- [ ] What problem this epic solves
  - [ ] Додати Prometheus text exporter як integration module
  - [ ] Забезпечити deterministic rendering: stable order, stable escaping, stable naming sanitizer
  - [ ] Підключення без зміни `platform/http` (тільки DI + selection через `metrics.exporter`)
- [ ] Non-goals / out of scope
  - [ ] Будь-які HTTP маршрути/слоти (інтеграція не володіє `/metrics`)
  - [ ] Нові labels поза allowlist (заборонено)
  - [ ] Логування дампу метрик (заборонено)
- [ ] Коли `integrations.metrics-prometheus` увімкнено і `metrics.exporter="prometheus"` — `/metrics` повертає Prometheus format; при проблемах рендера — поведінка лишається policy-compliant (no dumps; deterministic fallback/handling).

---

### 6.30.0 coretsia/tracing (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.30.0"
owner_path: "framework/packages/platform/tracing/"

package_id: "platform/tracing"
composer: "coretsia/platform-tracing"
kind: runtime
module_id: "platform.tracing"

goal: "Tracing works as a noop-safe SDK behind `TracerPortInterface` with deterministic W3C propagation, int-only sampling, and exporter selection via config with null exporter fallback."
provides:
- "SDK tracer implementation behind `TracerPortInterface` with deterministic W3C extract/inject."
- "Exporter selection via config with `NullSpanExporter` fallback (no integrations required)."
- "Deterministic, int-only sampling policy (no floats)."

tags_introduced: []
config_roots_introduced:
- "tracing"

artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/config-roots.md"
- "docs/ssot/observability.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `core/contracts` — provides tracing ports (Tracer/Span/Propagation/Exporter/Sampler).
  - `core/foundation` — provides context access (if used).
  - `platform/http` MAY provide an HTTP middleware that uses `ContextPropagationInterface` for request context extraction/injection.
    `platform/tracing` MUST NOT depend on `platform/http` and MUST remain usable without any HTTP package.

- Required config roots/keys:
  - `tracing.*` — this epic introduces the root; consumers read via global config under `tracing` root (config file returns subtree).

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Tracing\SpanInterface`
  - `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`
  - `Coretsia\Contracts\Observability\Tracing\SpanExporterInterface`
  - `Coretsia\Contracts\Observability\Tracing\SamplerInterface`

- Constraints / out of scope:
  - No HTTP endpoints in tracing.
  - No new contracts ports introduced here.
  - No float-based sampling; only integers (e.g., percent 0..100).
  - Exporter failure must be noop-safe (warn logs; no crash; no secrets/payloads).
  - Does NOT implement OTLP/Zipkin exporters (handled by separate integration epics).

- Cutline impact: none.

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
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Tracing\SpanInterface`
  - `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`
  - `Coretsia\Contracts\Observability\Tracing\SpanExporterInterface`
  - `Coretsia\Contracts\Observability\Tracing\SamplerInterface`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - (indirect) used by `platform/http` `\Coretsia\Http\Middleware\TraceContextMiddleware::class` (already SSoT)
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Tracing exporter selection (single-choice, no new tags)

- Selector: `tracing.exporter` (string), default `"null"`.
- `platform/tracing` MUST always provide a safe fallback binding:
  - `SpanExporterInterface` → `NullSpanExporter`.
- Integrations (OTLP/Zipkin) MUST implement conditional override:
  - if `tracing.exporter === "otlp"` (or `"zipkin"`) and the integration module is enabled → bind `SpanExporterInterface` to that exporter.
  - otherwise → do nothing.
- `platform/tracing` MUST NOT introduce or enumerate `tracing.exporter` tag.

### Deliverables (MUST)

#### Creates

Platform (`coretsia/tracing`):
- [ ] `framework/packages/platform/tracing/composer.json`
- [ ] `framework/packages/platform/tracing/src/Module/TracingModule.php`
- [ ] `framework/packages/platform/tracing/src/Provider/TracingServiceProvider.php`
- [ ] `framework/packages/platform/tracing/src/Provider/TracingServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/tracing/src/Provider/Tags.php` — (optional) exporter tag constants
- [ ] `framework/packages/platform/tracing/config/tracing.php`
- [ ] `framework/packages/platform/tracing/config/rules.php`
- [ ] `framework/packages/platform/tracing/README.md`
- [ ] `framework/packages/platform/tracing/src/Tracer/SdkTracer.php`
- [ ] `framework/packages/platform/tracing/src/Span/SdkSpan.php`
- [ ] `framework/packages/platform/tracing/src/Export/NullSpanExporter.php`
- [ ] `framework/packages/platform/tracing/src/Sampling/AlwaysOnSampler.php`
- [ ] `framework/packages/platform/tracing/src/Sampling/RatioSampler.php` — int percent (0..100), deterministic
- [ ] `framework/packages/platform/tracing/src/Propagation/W3CContextPropagation.php` — deterministic W3C extract/inject
- [ ] `framework/packages/platform/tracing/src/Exception/TracingException.php`

Docs:
- [ ] `docs/architecture/tracing.md` — base tracing architecture, selection, guarantees (without OTLP/Zipkin implementation details)

Tests:
- [ ] `framework/packages/platform/tracing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/platform/tracing/tests/Contract/W3CPropagationDeterministicContractTest.php`
- [ ] `framework/packages/platform/tracing/tests/Contract/NoopNeverThrowsContractTest.php`
- [ ] `framework/packages/platform/tracing/tests/Unit/SamplingRatePercentDeterministicTest.php`
- [ ] `framework/packages/platform/tracing/tests/Integration/ExporterFailureDoesNotThrowTest.php`

#### Modifies

N/A

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
  - [ ] `tracing.sampling.rate_percent` = 100
  - [ ] `tracing.exporter` = "null"          # "null"|"otlp"|"zipkin"
- [ ] Rules:
  - [ ] `framework/packages/platform/tracing/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/tracing/src/Provider/Tags.php` (optional)
  - [ ] constant(s): `TRACING_EXPORTER = 'tracing.exporter'` (optional)
- [ ] ServiceProvider wiring evidence:
  - [ ] `platform/tracing` binds `TracerPortInterface` → `SdkTracer` (or noop if disabled)
  - [ ] `platform/tracing` binds `ContextPropagationInterface` → `W3CContextPropagation`
  - [ ] `platform/tracing` binds `SpanExporterInterface` → `NullSpanExporter` (fallback)
  - [ ] `platform/tracing` binds `SamplerInterface` → `RatioSampler` (int-only policy)

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (as span attr allowed)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] N/A (unless buffers are introduced later by exporters)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `tracing.export` (optional; attrs: exporter, outcome)
- [ ] Metrics:
  - [ ] `tracing.export_total` (labels: `driver`, `outcome`) (optional)
  - [ ] `tracing.export_duration_ms` (labels: `driver`, `outcome`) (optional)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`
- [ ] Logs:
  - [ ] exporter failures warn (no secrets, no payloads)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Tracing\Exception\TracingException` — errorCode `CORETSIA_TRACING_EXPORT_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] span payloads, headers/cookies, tokens, endpoint URLs in logs (only hash/len)
- [ ] Allowed:
  - [ ] safe ids + `hash(value)` / `len(value)`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → N/A (no context writes)
- [ ] If `kernel.reset` used → N/A
- [ ] If metrics/spans/logs exist → covered by:
  - [ ] `framework/packages/platform/tracing/tests/Contract/W3CPropagationDeterministicContractTest.php`
  - [ ] `framework/packages/platform/tracing/tests/Integration/ExporterFailureDoesNotThrowTest.php`
- [ ] If redaction exists → covered by:
  - [ ] `framework/packages/platform/tracing/tests/Integration/ExporterFailureDoesNotThrowTest.php` (asserts warnings are redacted; no raw endpoints/payloads)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeLogger capture redacted warnings

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/tracing/tests/Unit/SamplingRatePercentDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/tracing/tests/Contract/W3CPropagationDeterministicContractTest.php`
  - [ ] `framework/packages/platform/tracing/tests/Contract/NoopNeverThrowsContractTest.php`
  - [ ] `framework/packages/platform/tracing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/tracing/tests/Integration/ExporterFailureDoesNotThrowTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (no `platform/http` dep; no integrations deps)
- [ ] Verification tests present where applicable
- [ ] Determinism: W3C deterministic; sampling int-only deterministic
- [ ] Docs updated:
  - [ ] `framework/packages/platform/tracing/README.md`
  - [ ] `docs/architecture/tracing.md`
- [ ] What problem this epic solves
  - [ ] Апгрейд `platform/tracing`: noop → SDK tracer + exporter selection з null fallback
  - [ ] Гарантувати deterministic W3C propagation та int-only sampling
  - [ ] Гарантувати noop-safety: exporter failure не крашить runtime
- [ ] Non-goals / out of scope
  - [ ] Будь-які HTTP endpoints у tracing (не треба)
  - [ ] Додавати нові contracts ports (заборонено)
  - [ ] OTLP/Zipkin exporters (винесено в окремі integration epics)
  - [ ] Float-based sampling (заборонено)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/http` base stack includes `TraceContextMiddleware` (already Phase 0)
  - [ ] exporter modules optional; when absent, use null exporter
- [ ] Discovery / wiring is via tags:
  - [ ] (optional) `tracing.exporter` (if you choose tag-based exporter selection)
- [ ] Tracing працює як noop-safe SDK; W3C propagation deterministic; exporter failure не крашить runtime.
- [ ] When exporter is misconfigured/unreachable, then spans are dropped (or buffered per policy) and only a redacted warning is logged.

---

### 6.31.0 integrations/tracing-otlp (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.31.0"
owner_path: "framework/packages/integrations/tracing-otlp/"

package_id: "integrations/tracing-otlp"
composer: "coretsia/integrations-tracing-otlp"
kind: runtime
module_id: "integrations.tracing-otlp"

goal: "When `integrations.tracing-otlp` is enabled and selected via `tracing.exporter=\"otlp\"`, it provides an OTLP-over-HTTP span exporter with deterministic payload encoding and noop-safe failure handling."
provides:
- "OTLP HTTP `SpanExporterInterface` implementation."
- "Deterministic OTLP payload encoder (no floats; stable ordering; deterministic bytes)."
- "Redacted request factory (never logs raw endpoints/payloads)."

tags_introduced: []
config_roots_introduced:
- "tracing_otlp"

artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/config-roots.md"
- "docs/ssot/observability.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `6.30.0` (`platform/tracing`) — provides tracer SDK + exporter selection contract surface.
  - `core/contracts` — provides `SpanExporterInterface` and related tracing ports.
  - `core/foundation` — deterministic helpers (if needed) and context access patterns.

- Required deliverables (exact paths):
  - `framework/packages/platform/tracing/config/tracing.php` — must include `tracing.exporter` key.
  - `framework/packages/platform/tracing/src/Export/NullSpanExporter.php` — base fallback exists.

- Required config roots/keys:
  - `tracing.exporter` — must support `"otlp"` as selector value.
  - `tracing_otlp.*` — this epic introduces exporter-specific config subtree.

- Constraints / out of scope:
  - No compile-time dependency on `platform/http`.
  - Exporter uses PSR-18 client as a port (API surface), not as a compile-time dep.
  - Never log raw endpoint URLs or payloads (hash/len only).
  - No float usage in payload encoding.

- Cutline impact: none.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `platform/tracing`

Forbidden:
- `platform/http`
- `platform/cli`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
  - `Psr\Http\Client\ClientInterface`
  - `Psr\Http\Message\RequestFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\SpanExporterInterface`

### Entry points / integration points (MUST)

N/A (no CLI/HTTP entry points; integration is pure DI wiring that provides an exporter)

### Integration wiring (single-choice)

- Conditional override:
  - When `tracing.exporter === "otlp"` AND `integrations.tracing-otlp` module is enabled:
    - bind `Coretsia\Contracts\Observability\Tracing\SpanExporterInterface` → `OtlpHttpSpanExporter`
  - Otherwise: do nothing (platform fallback remains active).
- Exporter MUST be noop-safe on failures:
  - no throws to caller; only redacted warning via `Psr\Log\LoggerInterface`.
  - never log raw endpoint, headers, or payloads; only `hash/len`.

### Deliverables (MUST)

#### Creates

Integration (`coretsia/tracing-otlp`):
- [ ] `framework/packages/integrations/tracing-otlp/composer.json`
- [ ] `framework/packages/integrations/tracing-otlp/src/Module/TracingOtlpModule.php`
- [ ] `framework/packages/integrations/tracing-otlp/src/Provider/TracingOtlpServiceProvider.php`
- [ ] `framework/packages/integrations/tracing-otlp/src/Provider/TracingOtlpServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/tracing-otlp/config/tracing_otlp.php`
- [ ] `framework/packages/integrations/tracing-otlp/config/rules.php`
- [ ] `framework/packages/integrations/tracing-otlp/README.md`
- [ ] `framework/packages/integrations/tracing-otlp/src/Otlp/OtlpHttpSpanExporter.php`
- [ ] `framework/packages/integrations/tracing-otlp/src/Otlp/OtlpPayloadEncoder.php` — deterministic payload encoding (no floats)
- [ ] `framework/packages/integrations/tracing-otlp/src/Otlp/OtlpRequestFactory.php` — PSR-18 request (redacted)

Docs:
- [ ] `docs/architecture/tracing.md` — add OTLP exporter details (selection + encoding + redaction guarantees)

Tests:
- [ ] `framework/packages/integrations/tracing-otlp/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/integrations/tracing-otlp/tests/Integration/OtlpPayloadIsDeterministicTest.php`

#### Modifies

- [ ] `docs/architecture/tracing.md` — extend with OTLP exporter semantics and config
- [ ] `docs/ssot/config-roots.md` — add root row for `tracing_otlp` (owner `integrations/tracing-otlp`, defaults `.../config/tracing_otlp.php`, rules `.../config/rules.php`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/tracing-otlp/composer.json`
- [ ] `framework/packages/integrations/tracing-otlp/src/Module/TracingOtlpModule.php`
- [ ] `framework/packages/integrations/tracing-otlp/src/Provider/TracingOtlpServiceProvider.php`
- [ ] `framework/packages/integrations/tracing-otlp/config/tracing_otlp.php`
- [ ] `framework/packages/integrations/tracing-otlp/config/rules.php`
- [ ] `framework/packages/integrations/tracing-otlp/README.md`
- [ ] `framework/packages/integrations/tracing-otlp/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/tracing-otlp/config/tracing_otlp.php`
- [ ] Keys (dot):
  - [ ] `tracing_otlp.enabled` = true
  - [ ] `tracing_otlp.endpoint` = ""         # never log raw; hash/len only
  - [ ] `tracing_otlp.headers` = []          # optional; redacted in logs
  - [ ] `tracing_otlp.timeout_ms` = 3000     # int only
- [ ] Rules:
  - [ ] `framework/packages/integrations/tracing-otlp/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\TracingOtlp\Otlp\OtlpHttpSpanExporter::class`
  - [ ] binds/exports `Coretsia\Contracts\Observability\Tracing\SpanExporterInterface` when `tracing.exporter="otlp"` (mechanism: config selection or registry/tag; must remain compile-time decoupled from `platform/http`)

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `tracing.export` (optional; attrs: exporter="otlp", outcome)
- [ ] Metrics:
  - [ ] `tracing.export_total` (labels: `driver`, `outcome`) (optional)
  - [ ] `tracing.export_duration_ms` (labels: `driver`, `outcome`) (optional)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`
- [ ] Logs:
  - [ ] failures warn; endpoint logged as hash/len only; no payload dumps

#### Errors

- [ ] Exceptions introduced:
  - [ ] N/A (export failures are surfaced/handled by `platform/tracing` policy; integration must not crash runtime)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] OTLP payloads, auth headers, endpoint URLs in logs (only hash/len)
- [ ] Allowed:
  - [ ] safe ids + `hash(value)` / `len(value)`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If redaction exists → covered by:
  - [ ] `framework/packages/integrations/tracing-otlp/tests/Integration/OtlpPayloadIsDeterministicTest.php` (asserts deterministic bytes + no float fields)
- [ ] If metrics/spans/logs exist → covered by:
  - [ ] `framework/packages/integrations/tracing-otlp/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/integrations/tracing-otlp/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/tracing-otlp/tests/Integration/OtlpPayloadIsDeterministicTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (no `platform/http` dep)
- [ ] Verification tests present where applicable
- [ ] Determinism: encoder deterministic; int-only; no floats
- [ ] Docs updated:
  - [ ] `framework/packages/integrations/tracing-otlp/README.md`
  - [ ] `docs/architecture/tracing.md`
- [ ] What problem this epic solves
  - [ ] Додати OTLP HTTP exporter як integration module
  - [ ] Гарантувати deterministic OTLP payload encoding (stable ordering; no floats)
  - [ ] Noop-safe failures: export issues не крашать runtime; warnings redacted
- [ ] Non-goals / out of scope
  - [ ] HTTP middleware/endpoints (не задача integration)
  - [ ] Залежність від `platform/http` (заборонено)
  - [ ] Логування payload/endpoint у raw вигляді (заборонено)
- [ ] When OTLP exporter is unreachable, spans are dropped (or buffered per policy) and only a redacted warning is logged.

---

### 6.32.0 integrations/tracing-zipkin (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.32.0"
owner_path: "framework/packages/integrations/tracing-zipkin/"

package_id: "integrations/tracing-zipkin"
composer: "coretsia/integrations-tracing-zipkin"
kind: runtime
module_id: "integrations.tracing-zipkin"

goal: "When `integrations.tracing-zipkin` is enabled and selected via `tracing.exporter=\"zipkin\"`, it provides a Zipkin span exporter with deterministic payload encoding and noop-safe failure handling."
provides:
- "Zipkin HTTP `SpanExporterInterface` implementation."
- "Deterministic Zipkin payload encoder (stable ordering; deterministic bytes)."
- "Redacted request factory (never logs raw endpoints/payloads)."

tags_introduced: []
config_roots_introduced:
- "tracing_zipkin"

artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/config-roots.md"
- "docs/ssot/observability.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `6.30.0` (`platform/tracing`) — provides tracer SDK + exporter selection contract surface.
  - `core/contracts` — provides `SpanExporterInterface` and related tracing ports.
  - `core/foundation` — deterministic helpers (if needed) and context access patterns.

- Required deliverables (exact paths):
  - `framework/packages/platform/tracing/config/tracing.php` — must include `tracing.exporter` key.
  - `framework/packages/platform/tracing/src/Export/NullSpanExporter.php` — base fallback exists.

- Required config roots/keys:
  - `tracing.exporter` — must support `"zipkin"` as selector value.
  - `tracing_zipkin.*` — this epic introduces exporter-specific config subtree.

- Constraints / out of scope:
  - No compile-time dependency on `platform/http`.
  - Exporter uses PSR-18 client as a port (API surface), not as a compile-time dep.
  - Never log raw endpoint URLs or payloads (hash/len only).

- Cutline impact: none.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `platform/tracing`

Forbidden:
- `platform/http`
- `platform/cli`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
  - `Psr\Http\Client\ClientInterface`
  - `Psr\Http\Message\RequestFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\SpanExporterInterface`

### Entry points / integration points (MUST)

N/A (no CLI/HTTP entry points; integration is pure DI wiring that provides an exporter)

### Integration wiring (single-choice)

- Conditional override:
  - When `tracing.exporter === "zipkin"` AND `integrations.tracing-zipkin` module is enabled:
    - bind `Coretsia\Contracts\Observability\Tracing\SpanExporterInterface` → `ZipkinHttpSpanExporter`
  - Otherwise: do nothing (platform fallback remains active).
- Failure handling + redaction identical to OTLP integration (no raw endpoint/payload logging; hash/len only).

### Deliverables (MUST)

#### Creates

Integration (`coretsia/tracing-zipkin`):
- [ ] `framework/packages/integrations/tracing-zipkin/composer.json`
- [ ] `framework/packages/integrations/tracing-zipkin/src/Module/TracingZipkinModule.php`
- [ ] `framework/packages/integrations/tracing-zipkin/src/Provider/TracingZipkinServiceProvider.php`
- [ ] `framework/packages/integrations/tracing-zipkin/src/Provider/TracingZipkinServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/tracing-zipkin/config/tracing_zipkin.php`
- [ ] `framework/packages/integrations/tracing-zipkin/config/rules.php`
- [ ] `framework/packages/integrations/tracing-zipkin/README.md`
- [ ] `framework/packages/integrations/tracing-zipkin/src/Zipkin/ZipkinHttpSpanExporter.php`
- [ ] `framework/packages/integrations/tracing-zipkin/src/Zipkin/ZipkinPayloadEncoder.php` — deterministic payload encoding
- [ ] `framework/packages/integrations/tracing-zipkin/src/Zipkin/ZipkinRequestFactory.php` — PSR-18 request (redacted)

Docs:
- [ ] `docs/architecture/tracing.md` — add Zipkin exporter details (selection + encoding + redaction guarantees)

Tests:
- [ ] `framework/packages/integrations/tracing-zipkin/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/integrations/tracing-zipkin/tests/Integration/ZipkinPayloadIsDeterministicTest.php`

#### Modifies

- [ ] `docs/architecture/tracing.md` — extend with Zipkin exporter semantics and config
- [ ] `docs/ssot/config-roots.md` — add root row for `tracing_zipkin` (owner `integrations/tracing-zipkin`, defaults `.../config/tracing_zipkin.php`, rules `.../config/rules.php`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/tracing-zipkin/composer.json`
- [ ] `framework/packages/integrations/tracing-zipkin/src/Module/TracingZipkinModule.php`
- [ ] `framework/packages/integrations/tracing-zipkin/src/Provider/TracingZipkinServiceProvider.php`
- [ ] `framework/packages/integrations/tracing-zipkin/config/tracing_zipkin.php`
- [ ] `framework/packages/integrations/tracing-zipkin/config/rules.php`
- [ ] `framework/packages/integrations/tracing-zipkin/README.md`
- [ ] `framework/packages/integrations/tracing-zipkin/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/tracing-zipkin/config/tracing_zipkin.php`
- [ ] Keys (dot):
  - [ ] `tracing_zipkin.enabled` = true
  - [ ] `tracing_zipkin.endpoint` = ""        # never log raw; hash/len only
  - [ ] `tracing_zipkin.timeout_ms` = 3000    # int only
- [ ] Rules:
  - [ ] `framework/packages/integrations/tracing-zipkin/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\TracingZipkin\Zipkin\ZipkinHttpSpanExporter::class`
  - [ ] binds/exports `Coretsia\Contracts\Observability\Tracing\SpanExporterInterface` when `tracing.exporter="zipkin"` (mechanism: config selection or registry/tag; must remain compile-time decoupled from `platform/http`)

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `tracing.export` (optional; attrs: exporter="zipkin", outcome)
- [ ] Metrics:
  - [ ] `tracing.export_total` (labels: `driver`, `outcome`) (optional)
  - [ ] `tracing.export_duration_ms` (labels: `driver`, `outcome`) (optional)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`
- [ ] Logs:
  - [ ] failures warn; endpoint logged as hash/len only; no payload dumps

#### Errors

- [ ] Exceptions introduced:
  - [ ] N/A (export failures are surfaced/handled by `platform/tracing` policy; integration must not crash runtime)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Zipkin payloads, auth headers, endpoint URLs in logs (only hash/len)
- [ ] Allowed:
  - [ ] safe ids + `hash(value)` / `len(value)`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If redaction exists → covered by:
  - [ ] `framework/packages/integrations/tracing-zipkin/tests/Integration/ZipkinPayloadIsDeterministicTest.php`
- [ ] If metrics/spans/logs exist → covered by:
  - [ ] `framework/packages/integrations/tracing-zipkin/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/integrations/tracing-zipkin/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/tracing-zipkin/tests/Integration/ZipkinPayloadIsDeterministicTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (no `platform/http` dep)
- [ ] Verification tests present where applicable
- [ ] Determinism: encoder deterministic; stable ordering; deterministic bytes
- [ ] Docs updated:
  - [ ] `framework/packages/integrations/tracing-zipkin/README.md`
  - [ ] `docs/architecture/tracing.md`
- [ ] What problem this epic solves
  - [ ] Додати Zipkin exporter як integration module
  - [ ] Гарантувати deterministic Zipkin payload encoding (stable ordering)
  - [ ] Noop-safe failures: export issues не крашать runtime; warnings redacted
- [ ] Non-goals / out of scope
  - [ ] HTTP middleware/endpoints (не задача integration)
  - [ ] Залежність від `platform/http` (заборонено)
  - [ ] Логування payload/endpoint у raw вигляді (заборонено)
- [ ] When Zipkin exporter is unreachable, spans are dropped (or buffered per policy) and only a redacted warning is logged.

---

### 6.40.0 coretsia/observability (glue module, NOT a monolith) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.40.0"
owner_path: "framework/packages/platform/observability/"

package_id: "platform/observability"
composer: "coretsia/platform-observability"
kind: runtime
module_id: "platform.observability"

goal: "Enabling `platform.observability` adds only glue behavior (Server-Timing / safe trace headers) via HTTP system_post tags, without creating a monolith or introducing platform-to-platform coupling."
provides:
- "Glue-level HTTP middlewares: deterministic `Server-Timing` and optional safe trace response headers."
- "Wiring through DI tags into `http.middleware.system_post` (no special HTTP skeleton config beyond this package)."
- "Explicit non-goals enforcement: no re-implementation of logging/tracing/metrics; no new ports; no mandatory dependency edges."

tags_introduced: []
config_roots_introduced:
- "observability"

artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/config-roots.md"
- "docs/ssot/observability.md"
- "docs/ssot/http-middleware-catalog.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `platform/http` — provides and executes middleware slot/tag `http.middleware.system_post` (observability integrates ONLY via tag; no compile-time dep).
  - `core/contracts` — provides tracer/meter ports.
  - `core/foundation` — provides `ContextAccessorInterface` (if used).

- Required config roots/keys:
  - `observability.*` — this epic introduces the root; consumers read via global config under `observability` root (config file returns subtree).

- Required tags:
  - `http.middleware.system_post` — must exist and be executed by HTTP stack (observability does not own this tag).

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

- Constraints / out of scope:
  - No re-implementation of logging/tracing/metrics packages.
  - No new ports outside `core/contracts`.
  - Other platform packages must NOT depend on `platform/observability` (exceptions only by explicit policy, e.g., devtools).

- Cutline impact: none.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http`
- `platform/logging`
- `platform/tracing`
- `platform/metrics`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - middleware slots/tags:
    - `http.middleware.system_post` priority `750` meta `{"reason":"server-timing glue"}` → `\Coretsia\Observability\Http\Middleware\ServerTimingMiddleware`
    - `http.middleware.system_post` priority `740` meta `{"reason":"safe trace headers (optional)"}` → `\Coretsia\Observability\Http\Middleware\TraceResponseHeadersMiddleware`
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/observability/composer.json`
- [ ] `framework/packages/platform/observability/src/Module/ObservabilityModule.php`
- [ ] `framework/packages/platform/observability/src/Provider/ObservabilityServiceProvider.php`
- [ ] `framework/packages/platform/observability/src/Provider/ObservabilityServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/observability/config/observability.php`
- [ ] `framework/packages/platform/observability/config/rules.php`
- [ ] `framework/packages/platform/observability/README.md`
- [ ] `framework/packages/platform/observability/src/Http/Middleware/ServerTimingMiddleware.php` — deterministic `Server-Timing`
- [ ] `framework/packages/platform/observability/src/Http/Middleware/TraceResponseHeadersMiddleware.php` — safe trace headers (optional)
- [ ] `framework/packages/platform/observability/src/Exception/ObservabilityGlueException.php`
- [ ] `docs/architecture/observability-glue.md`
- [ ] `framework/packages/platform/observability/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/platform/observability/tests/Unit/ServerTimingHeaderDeterminismTest.php`
- [ ] `framework/packages/platform/observability/tests/Integration/ServerTimingHeaderIsDeterministicWhenEnabledTest.php`
- [ ] `framework/packages/platform/observability/tests/Integration/TraceHeadersDoNotLeakSecretsTest.php`
- [ ] `framework/tools/tests/Integration/Http/ObservabilityGlueMiddlewareActiveWhenEnabledTest.php` (optional system-level)

#### Modifies

- [ ] `docs/ssot/config-roots.md` — add root row for `observability` (owner `platform/observability`, defaults `.../config/observability.php`, rules `.../config/rules.php`)
- [ ] `docs/ssot/http-middleware-catalog.md` — add 2 entries in `http.middleware.system_post`:
  - `ServerTimingMiddleware` (priority 750, toggle `observability.http.server_timing.enabled`)
  - `TraceResponseHeadersMiddleware` (priority 740, toggle `observability.http.trace_response_headers.enabled`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/observability/composer.json`
- [ ] `framework/packages/platform/observability/src/Module/ObservabilityModule.php`
- [ ] `framework/packages/platform/observability/src/Provider/ObservabilityServiceProvider.php`
- [ ] `framework/packages/platform/observability/config/observability.php`
- [ ] `framework/packages/platform/observability/config/rules.php`
- [ ] `framework/packages/platform/observability/README.md`
- [ ] `framework/packages/platform/observability/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/observability/config/observability.php`
- [ ] Keys (dot):
  - [ ] `observability.enabled` = true
  - [ ] `observability.redaction.enabled` = true
  - [ ] `observability.http.server_timing.enabled` = false
  - [ ] `observability.http.trace_response_headers.enabled` = false
- [ ] Rules:
  - [ ] `framework/packages/platform/observability/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (uses existing HTTP slot tag; provides constants only)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Observability\Http\Middleware\ServerTimingMiddleware::class`
  - [ ] adds tag: `http.middleware.system_post` priority `750` meta `{"reason":"server-timing glue"}`
  - [ ] registers: `\Coretsia\Observability\Http\Middleware\TraceResponseHeadersMiddleware::class`
  - [ ] adds tag: `http.middleware.system_post` priority `740` meta `{"reason":"safe trace headers (optional)"}`

- Tag ownership fix (MUST)
  - `platform/observability` MUST NOT define constants for `http.middleware.*` tags (owner is `platform/http` per `docs/ssot/tags.md`).
  - Remove deliverable:
    - `framework/packages/platform/observability/src/Provider/Tags.php`
  - Use tag string literal in wiring:
    - `'http.middleware.system_post'`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::REQUEST_ID` (optional)
- Context writes (safe only):
  - none
- Reset discipline:
  - N/A (middlewares are stateless)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `observability.server_timing` (optional)
- [ ] Metrics:
  - [ ] `observability.server_timing_total` (labels: `outcome`) (optional)
  - [ ] `observability.server_timing_duration_ms` (labels: `outcome`) (optional)
- [ ] Label normalization applied (if needed):
  - [ ] `kind/op/uow_type→operation` (prefer span attrs)
- [ ] Logs:
  - [ ] only warn/error on internal failures; no headers dump

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Observability\Exception\ObservabilityGlueException` — errorCode `CORETSIA_OBSERVABILITY_GLUE_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie, tokens, session id, raw headers/cookies
- [ ] Allowed:
  - [ ] only safe ids already in context; deterministic header output

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → N/A (no context writes)
- [ ] If `kernel.reset` used → N/A
- [ ] If metrics/spans/logs exist → covered by:
  - [ ] `framework/packages/platform/observability/tests/Unit/ServerTimingHeaderDeterminismTest.php`
  - [ ] `framework/packages/platform/observability/tests/Integration/ServerTimingHeaderIsDeterministicWhenEnabledTest.php`
- [ ] If redaction exists → covered by:
  - [ ] `framework/packages/platform/observability/tests/Integration/TraceHeadersDoNotLeakSecretsTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` (if used for boot/wiring)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/observability/tests/Unit/ServerTimingHeaderDeterminismTest.php`
- Contract:
  - [ ] `framework/packages/platform/observability/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/observability/tests/Integration/ServerTimingHeaderIsDeterministicWhenEnabledTest.php`
  - [ ] `framework/packages/platform/observability/tests/Integration/TraceHeadersDoNotLeakSecretsTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (no platform-to-platform coupling introduced; no integrations)
- [ ] Verification tests present where applicable
- [ ] Determinism: `Server-Timing` bytes stable
- [ ] Docs updated:
  - [ ] `framework/packages/platform/observability/README.md`
  - [ ] `docs/architecture/observability-glue.md`
- [ ] What problem this epic solves
  - [ ] Дати glue-рівень для узгоджених observability policies поверх logging/tracing/metrics без створення “моноліту”
  - [ ] Додати спільні HTTP middlewares, які логічно “між” пакетами: Server-Timing та (опційно) trace response headers
  - [ ] Підключити ці middlewares через DI tags у `http.middleware.system_post` (без skeleton config)
- [ ] Non-goals / out of scope
  - [ ] Ре-імплементація logging/tracing/metrics (заборонено)
  - [ ] Введення нових ports поза `core/contracts` (заборонено)
  - [ ] Залежність інших platform пакетів від `platform/observability` (заборонено; окрім devtools продуктів за явним винятком)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/http` executes `http.middleware.system_post`
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] Discovery / wiring is via tags:
  - [ ] `http.middleware.system_post`
- [ ] Вмикання `platform.observability` додає лише glue-поведінку (Server-Timing/trace headers) без змін core flows і без залежностей на інші platform пакети.
- [ ] When `observability.http.server_timing.enabled=true`, then responses include deterministic `Server-Timing` without leaking secrets/PII.

---

### 6.50.0 coretsia/profiling (sampling profiler hooks) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.50.0"
owner_path: "framework/packages/platform/profiling/"

package_id: "platform/profiling"
composer: "coretsia/platform-profiling"
kind: runtime
module_id: "platform.profiling"

goal: "When `platform.profiling` is enabled, profiling starts/stops exactly once per UoW via kernel hooks, is deterministic (int-only sampling), and never leaks profile payload into logs/metrics."
provides:
- "Kernel hook-based sampling profiler across UoW types (HTTP/CLI/Queue/Scheduler)."
- "Deterministic sampling policy (ints only: `per_mille`, `min_duration_ms`) + noop-safe exporter."
- "Strict redaction policy: profile payload is never logged and never used in metric labels."

tags_introduced:
- "profiling.exporter"

config_roots_introduced:
- "profiling"

artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/config-roots.md"
- "docs/ssot/tags.md"
- "docs/ssot/observability.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `core/contracts` — provides profiling ports + kernel hook ports.
  - `core/foundation` — provides context access and deterministic utilities (if used).
  - Kernel runtime — executes `kernel.hook.before_uow` and `kernel.hook.after_uow` tags.
  - No exporter implementation required at runtime: null exporter must exist as default.

- Required config roots/keys:
  - `profiling.*` — this epic introduces the root; consumers read via global config under `profiling` root (config file returns subtree).

- Required tags:
  - `kernel.hook.before_uow` — executed by kernel runtime
  - `kernel.hook.after_uow` — executed by kernel runtime
  - `kernel.reset` — available for stateful components (conditional)
  - `profiling.exporter` — introduced by this epic as optional selection mechanism

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface`
  - `Coretsia\Contracts\Observability\Profiling\ProfileExporterInterface`
  - `Coretsia\Contracts\Observability\Profiling\ProfileArtifact`
  - `Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface`
  - `Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface` (conditional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)

- Constraints / out of scope:
  - No vendor profiler SDKs in `platform/profiling` (vendors live in integrations).
  - No HTTP endpoint for profiles.
  - No float sampling; no high-cardinality labels.
  - Never log profile payload.

- Cutline impact: none.

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
  - `Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface`
  - `Coretsia\Contracts\Observability\Profiling\ProfileExporterInterface`
  - `Coretsia\Contracts\Observability\Profiling\ProfileArtifact`
  - `Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface`
  - `Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface` (if stateful components exist)
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
  - `kernel.hook.before_uow` priority `50` meta `{"reason":"start profiling for UoW"}` → `framework/packages/platform/profiling/src/Kernel/ProfilingBeforeUowHook.php`
  - `kernel.hook.after_uow`  priority `50` meta `{"reason":"stop profiling and export artifact"}` → `framework/packages/platform/profiling/src/Kernel/ProfilingAfterUowHook.php`
- Artifacts:
  - writes: `skeleton/var/tmp/profiles/<profileId>.<ext>` (optional runtime output; excluded from fingerprint)

### Profiling exporter selection (single-choice, no new tags)

- Selector: `profiling.exporter` (string), default `"null"`.
- `platform/profiling` MUST always provide a safe fallback:
  - `ProfileExporterInterface` → noop/null exporter (never throws, never logs payload).
- Integrations (future profilers/exporters) MUST conditionally override binding:
  - if `profiling.exporter === "<name>"` and the integration module is enabled → bind `ProfileExporterInterface` to that exporter.
  - otherwise → do nothing.
- `platform/profiling` MUST NOT introduce `profiling.exporter` DI tag.

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/profiling/composer.json`
- [ ] `framework/packages/platform/profiling/src/Module/ProfilingModule.php`
- [ ] `framework/packages/platform/profiling/src/Provider/ProfilingServiceProvider.php`
- [ ] `framework/packages/platform/profiling/src/Provider/ProfilingServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/profiling/src/Provider/Tags.php` — constants (`profiling.exporter`)
- [ ] `framework/packages/platform/profiling/config/profiling.php`
- [ ] `framework/packages/platform/profiling/config/rules.php`
- [ ] `framework/packages/platform/profiling/README.md`
- [ ] `framework/packages/platform/profiling/src/Profiling/Sampling/SamplingPolicy.php` — deterministic ints policy
- [ ] `framework/packages/platform/profiling/src/Profiling/Sampling/DeterministicSampler.php`
- [ ] `framework/packages/platform/profiling/src/Profiling/Profiler.php` — implements `ProfilerPortInterface`
- [ ] `framework/packages/platform/profiling/src/Export/NullProfileExporter.php` — implements `ProfileExporterInterface`
- [ ] `framework/packages/platform/profiling/src/Kernel/ProfilingBeforeUowHook.php` — implements `BeforeUowHookInterface`
- [ ] `framework/packages/platform/profiling/src/Kernel/ProfilingAfterUowHook.php` — implements `AfterUowHookInterface`
- [ ] `framework/packages/platform/profiling/src/Exception/ProfilingException.php`
- [ ] `docs/architecture/profiling.md`

Tests:
- [ ] `framework/packages/platform/profiling/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/platform/profiling/tests/Unit/SamplingPerMilleDeterministicTest.php`
- [ ] `framework/packages/platform/profiling/tests/Integration/HooksStartAndStopExactlyOncePerUowTest.php`
- [ ] `framework/packages/platform/profiling/tests/Integration/ProfilerDoesNotLeakPayloadToLogsTest.php`
- [ ] `framework/tools/tests/Integration/E2E/ProfilingRunsInHttpAndQueueUowTest.php` (optional system-level)

#### Modifies

- [ ] `docs/ssot/config-roots.md` — add root row for `profiling` (owner `platform/profiling`, defaults `.../config/profiling.php`, rules `.../config/rules.php`)

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/profiling/composer.json`
- [ ] `framework/packages/platform/profiling/src/Module/ProfilingModule.php`
- [ ] `framework/packages/platform/profiling/src/Provider/ProfilingServiceProvider.php`
- [ ] `framework/packages/platform/profiling/config/profiling.php`
- [ ] `framework/packages/platform/profiling/config/rules.php`
- [ ] `framework/packages/platform/profiling/README.md`
- [ ] `framework/packages/platform/profiling/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/profiling/config/profiling.php`
- [ ] Keys (dot):
  - [ ] `profiling.enabled` = false
  - [ ] `profiling.sampling.per_mille` = 0
  - [ ] `profiling.sampling.min_duration_ms` = 0
  - [ ] `profiling.exporter` = "null"                     # "null"|<name from integrations>
  - [ ] `profiling.storage.path` = "skeleton/var/tmp/profiles"
  - [ ] `profiling.redaction.enabled` = true
- [ ] Rules:
  - [ ] `framework/packages/platform/profiling/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/profiling/src/Provider/Tags.php` (constants)
  - [ ] constants:
    - [ ] `PROFILING_EXPORTER = "profiling.exporter"`
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Profiling\Kernel\ProfilingBeforeUowHook::class`
  - [ ] adds tag: `kernel.hook.before_uow` priority `50` meta `{"reason":"start profiling for UoW"}`
  - [ ] registers: `\Coretsia\Profiling\Kernel\ProfilingAfterUowHook::class`
  - [ ] adds tag: `kernel.hook.after_uow` priority `50` meta `{"reason":"stop profiling and export artifact"}`
  - [ ] binds: `ProfileExporterInterface` → `NullProfileExporter` by default
  - [ ] (optional) selects exporter by `profiling.exporter` via tag `profiling.exporter` meta `{"name":"..."}`

- Kernel hooks integration (cemented)
  - Hook services MUST be tagged using string literals:
    - `'kernel.hook.before_uow'`
    - `'kernel.hook.after_uow'`
  - Ordering MUST be exactly TagRegistry order; `platform/profiling` MUST NOT re-sort.
  - Start/stop exactly once per UoW is enforced by:
    - start in before_uow hook
    - stop+export in after_uow hook
    - hook implementations MUST be stateless; if stateful buffers are introduced → implement `ResetInterface` + tag `kernel.reset`.

### Runtime outputs (non-artifact; MUST)

- Profiling output files (if enabled) are **NOT** kernel artifacts and MUST NOT use artifact envelope rules.
- Output path MUST be config-driven:
  - `profiling.output.dir` (default: `"var/tmp/profiles"`)
- Output naming MUST be deterministic per generated `profileId` (no timestamps in filenames).
- MUST NOT be included into fingerprint/cache verification inputs.
- MUST NOT log profile payload; only `profileId`, `hash/len` may appear in logs.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::UOW_TYPE`
  - [ ] `ContextKeys::CORRELATION_ID`
- [ ] Context writes (safe only):
  - [ ] none (do not write profile payload)
- [ ] Reset discipline:
  - [ ] any stateful sampler/exporter selector implements `ResetInterface`
  - [ ] stateful services tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `profiling.uow` (optional; attrs: uow_type, outcome)
- [ ] Metrics:
  - [ ] `profiling.sampled_total` (labels: `operation`, `outcome`)
  - [ ] `profiling.export_total` (labels: `outcome`)
  - [ ] `profiling.duration_ms` (labels: `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `uow_type→operation` (values: `http|cli|queue|scheduler`)
- [ ] Logs:
  - [ ] allowed: profileId (safe), exporter name, durationMs
  - [ ] never log payload

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Profiling\Exception\ProfilingException` — errorCodes:
    - [ ] `CORETSIA_PROFILING_FAILED`
    - [ ] `CORETSIA_PROFILING_EXPORT_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] profile payload, stacktraces in prod logs, secrets/PII
- [ ] Allowed:
  - [ ] safe ids + hash/len only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → N/A (no context writes)
- [ ] If `kernel.reset` used → covered by integration wiring tests when stateful components are introduced (conditional)
- [ ] If metrics/spans/logs exist → covered by:
  - [ ] `framework/packages/platform/profiling/tests/Integration/ProfilerDoesNotLeakPayloadToLogsTest.php`
- [ ] If redaction exists → covered by:
  - [ ] `framework/packages/platform/profiling/tests/Integration/ProfilerDoesNotLeakPayloadToLogsTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` (if used for boot/wiring)
- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger capture events for assertions (test-only)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/profiling/tests/Unit/SamplingPerMilleDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/profiling/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/profiling/tests/Integration/HooksStartAndStopExactlyOncePerUowTest.php`
  - [ ] `framework/packages/platform/profiling/tests/Integration/ProfilerDoesNotLeakPayloadToLogsTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (no vendor deps in platform; no integrations deps)
- [ ] Verification tests present where applicable
- [ ] Determinism: sampling decisions deterministic (ints only); hooks exactly-once per UoW
- [ ] Docs updated:
  - [ ] `framework/packages/platform/profiling/README.md`
  - [ ] `docs/architecture/profiling.md`
- [ ] What problem this epic solves
  - [ ] Дати sampling profiler, який працює для будь-якого UoW (HTTP/CLI/Queue/Scheduler) через kernel hooks
  - [ ] Детермінований sampling policy (ints only: `per_mille`, `min_duration_ms`) + pluggable exporters через integrations (майбутні)
  - [ ] Заборонити leakage: profile payload ніколи не логиться/не йде в metrics labels
- [ ] Non-goals / out of scope
  - [ ] Vendor профайлер SDKs у platform package (лише ports + glue; vendor — integrations)
  - [ ] Будь-який HTTP endpoint для профайлів (не потрібно)
  - [ ] Float sampling або high-cardinality labels (заборонено)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] KernelRuntime executes `kernel.hook.before_uow` + `kernel.hook.after_uow`
  - [ ] exporter implementation may be missing → noop exporter used
- [ ] Discovery / wiring is via tags:
  - [ ] `kernel.hook.before_uow`
  - [ ] `kernel.hook.after_uow`
  - [ ] `kernel.reset` (if any stateful sampler/exporter selection)
  - [ ] `profiling.exporter` (optional tag used for exporter selection)
- [ ] Увімкнений `platform.profiling` стартує/стопає профайлер рівно 1 раз на UoW через hooks, робить це deterministic і ніколи не ллє payload у логи/метрики.
- [ ] When `profiling.sampling.per_mille=1000`, then every UoW produces a ProfileArtifact and exporter is invoked (or noop) without throwing.

---

### 6.60.0 integrations/cache-redis (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.60.0"
owner_path: "framework/packages/integrations/cache-redis/"

package_id: "integrations/cache-redis"
composer: "coretsia/integrations-cache-redis"
kind: runtime
module_id: "integrations.cache-redis"

goal: "Увімкнення `integrations.cache-redis` дає drop-in PSR-16 Redis store без зміни app коду, з deterministic config-driven client creation і без витоку секретів/ключів."
provides:
- "PSR-16 cache store: Redis (production-grade) без leakage ключів/паролів."
- "Deterministic config-driven Redis client creation (host/port/db + optional secret_ref password)."
- "Security redaction helpers (hash/len only) для ключів/секретів."
- "Contract-like parity semantics (мінімальна сумісність з reference stores)."

tags_introduced: []
config_roots_introduced: ["cache_redis"]
artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required config roots/keys:
  - `cache.default` (в `platform/cache`) — store selection by name (e.g. `redis`), deterministic.

- Required contracts / ports:
  - `Psr\SimpleCache\CacheInterface` — PSR-16 binding in the app.
  - `Psr\Log\LoggerInterface` — safe logging.
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` — OPTIONAL, only if redis password configured via `cache_redis.password_secret_ref`.
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — OPTIONAL (noop-safe).
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — OPTIONAL (noop-safe).

- Runtime expectations (policy; not deps, but MUST be true for end-to-end value):
  - `platform/cache` can select store by config + enabled module, and has an internal driver/store registry keyed by name (`redis`).
  - Secret-like values MUST NOT appear in logs/spans/metrics labels; only redacted forms.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `platform/cache`

Forbidden:
- `platform/http`
- `platform/cli`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\SimpleCache\CacheInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` (if used)
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/cache-redis/composer.json`
- [ ] `framework/packages/integrations/cache-redis/src/Module/CacheRedisModule.php` — module entry (runtime enable)
- [ ] `framework/packages/integrations/cache-redis/src/Provider/CacheRedisServiceProvider.php` — DI wiring
- [ ] `framework/packages/integrations/cache-redis/src/Provider/CacheRedisServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/cache-redis/config/cache_redis.php` — config root `cache_redis` (returns subtree, no repeated root)
- [ ] `framework/packages/integrations/cache-redis/config/rules.php` — config shape rules
- [ ] `framework/packages/integrations/cache-redis/README.md` — docs (Observability / Errors / Security-Redaction)

Implementation:
- [ ] `framework/packages/integrations/cache-redis/src/Store/RedisCacheStore.php` — PSR-16 store
- [ ] `framework/packages/integrations/cache-redis/src/Redis/RedisClientFactory.php` — deterministic client creation from config
- [ ] `framework/packages/integrations/cache-redis/src/Security/Redaction.php` — key hashing helpers (no raw keys in logs)
- [ ] `framework/packages/integrations/cache-redis/src/Exception/RedisCacheException.php` — errorCode `CORETSIA_CACHE_REDIS_FAILED`

Tests:
- [ ] `framework/packages/integrations/cache-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — noop-safe cross-cutting
- [ ] `framework/packages/integrations/cache-redis/tests/Unit/KeyRedactionDoesNotLeakRawKeyTest.php` — redaction proof
- [ ] `framework/packages/integrations/cache-redis/tests/Contract/CacheSemanticsParityTest.php` — parity vs reference store
- [ ] `framework/packages/integrations/cache-redis/tests/Integration/RedisStoreTtlSemanticsTest.php` — TTL semantics (optional CI service)

#### Modifies

- [ ] Update `docs/ssot/config-roots.md` (owner registry):
  - Add row:
    - root: `cache_redis`
    - owner: `integrations/cache-redis`
    - defaults: `framework/packages/integrations/cache-redis/config/cache_redis.php`
    - rules: `framework/packages/integrations/cache-redis/config/rules.php`

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/cache-redis/composer.json`
- [ ] `framework/packages/integrations/cache-redis/src/Module/CacheRedisModule.php`
- [ ] `framework/packages/integrations/cache-redis/src/Provider/CacheRedisServiceProvider.php`
- [ ] `framework/packages/integrations/cache-redis/config/cache_redis.php`
- [ ] `framework/packages/integrations/cache-redis/config/rules.php`
- [ ] `framework/packages/integrations/cache-redis/README.md`
- [ ] `framework/packages/integrations/cache-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Composer metadata (MUST)

`composer.json` MUST include runtime module metadata for metadata-only discovery:
- `extra.coretsia.providers` (list of ServiceProvider FQCNs)
- `extra.coretsia.defaultsConfigPath` (path to defaults config file)
  No runtime filesystem scanning is allowed.

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/cache-redis/config/cache_redis.php`

- [ ] Keys (dot):
  - [ ] `cache_redis.enabled` = false
  - [ ] `cache_redis.host` = '127.0.0.1'
  - [ ] `cache_redis.port` = 6379
  - [ ] `cache_redis.database` = 0
  - [ ] `cache_redis.password_secret_ref` = null

- [ ] Rules:
  - [ ] `framework/packages/integrations/cache-redis/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (no new tags; binding is direct via ServiceProvider)
- [ ] ServiceProvider wiring evidence:
  - [ ] binds PSR-16 store into `platform/cache` driver/store registry by name (`redis`)
  - [ ] never prints secrets; only `hash(value)` / `len(value)` and safe metadata

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (for safe logs)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] redis client is stateless; if pooling state is introduced → implement `ResetInterface`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `redis.op` (attrs: op, outcome) (optional)
- [ ] Metrics:
  - [ ] reuse `cache.*` metrics only (no new metric names)
- [ ] Label normalization applied (if needed):
  - [ ] `op→operation` (if you label; prefer span attr)
- [ ] Logs:
  - [ ] never log raw keys; only `hash(key)` / `len(key)`; never log raw endpoints/passwords

- Spans (optional, noop-safe):
  - Use canonical span names: `cache.get`, `cache.set`, `cache.delete`, `cache.clear`, `cache.has` (domain=`cache`, operation=`get|set|...`).
  - Span attributes MAY include:
    - `driver` = `redis|apcu`
    - `outcome` = `success|handled_error|fatal_error` (if mapped)
  - MUST NOT include cache keys or values (only `hash(key)` / `len(key)` if absolutely required for diagnostics).

- Metrics (optional, noop-safe):
  - If emitted, names MUST follow: `cache.op_total`, `cache.op_duration_ms`.
  - Labels MUST be allowlisted: `driver`, `operation`, `outcome` only.
  - MUST NOT label by key/prefix/value.

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/integrations/cache-redis/src/Exception/RedisCacheException.php` — errorCode `CORETSIA_CACHE_REDIS_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] redis password, raw cache keys, payload values
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` only

### Redis endpoint redaction (MUST)

- MUST NOT log/emit raw Redis endpoint (`host`, `host:port`, socket paths), db index, or password.
- Allowed diagnostics only:
  - `endpoint_hash = sha256(normalized_endpoint_descriptor)`
  - `db` MAY be reported only as int if it is not sensitive in your threat model; otherwise hash it too.
- Never print `password_secret_ref` resolution results.

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If redaction exists → `framework/packages/integrations/cache-redis/tests/Unit/KeyRedactionDoesNotLeakRawKeyTest.php`
  (asserts: raw key never appears; only hash/len)
- [ ] If metrics/spans/logs exist (even optional/noop-safe) → `framework/packages/integrations/cache-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  (asserts: tracer/meter/logger missing/noop does not throw; no secret leakage via instrumentation path)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeLogger/FakeTracer/FakeMeter capture events for assertions (when used in the tests above)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/integrations/cache-redis/tests/Unit/KeyRedactionDoesNotLeakRawKeyTest.php`
- Contract:
  - [ ] `framework/packages/integrations/cache-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/integrations/cache-redis/tests/Contract/CacheSemanticsParityTest.php`
- Integration:
  - [ ] `framework/packages/integrations/cache-redis/tests/Integration/RedisStoreTtlSemanticsTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Driver is drop-in via module enable + config only
- [ ] No secrets/keys leakage in logs/spans/metrics labels
- [ ] Contract-like parity tests exist
- [ ] Docs in README cover configuration + redaction
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: rerun-no-diff (no generated artifacts here)
- [ ] What problem this epic solves
  - [ ] Додати production-grade Redis cache store як integration, не змінюючи `platform/cache` public API (PSR-16)
  - [ ] Забезпечити deterministic config + safe secret handling (password via `secret_ref`), без leakage в logs/spans/metrics labels
  - [ ] Parity semantics: contract-like тести гарантують базову сумісність з reference stores
- [ ] Non-goals / out of scope
  - [ ] Залежність `platform/cache` від integrations (заборонено)
  - [ ] Будь-які нові contracts ports (заборонено)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/cache` selects store by config + enabled module
- [ ] Увімкнення `integrations.cache-redis` дає drop-in PSR-16 store без зміни app коду і без витоку секретів.
- [ ] When `cache.default='redis'` and redis module enabled, then `CacheInterface` resolves to Redis store and respects TTL deterministically.

---

### 6.61.0 integrations/cache-apcu (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.61.0"
owner_path: "framework/packages/integrations/cache-apcu/"

package_id: "integrations/cache-apcu"
composer: "coretsia/integrations-cache-apcu"
kind: runtime
module_id: "integrations.cache-apcu"

goal: "Увімкнення `integrations.cache-apcu` дає drop-in PSR-16 APCu store без зміни app коду, з deterministic prefix semantics і без витоку ключів/значень."
provides:
- "PSR-16 cache store: APCu (local) з deterministic prefix semantics."
- "Minimal contract-like basic semantics coverage; noop-safe cross-cutting."

tags_introduced: []
config_roots_introduced: ["cache_apcu"]
artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required config roots/keys:
  - `cache.default` (в `platform/cache`) — store selection by name (e.g. `apcu`), deterministic.

- Required contracts / ports:
  - `Psr\SimpleCache\CacheInterface` — PSR-16 binding in the app.
  - `Psr\Log\LoggerInterface` — safe logging.
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — OPTIONAL (noop-safe).
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — OPTIONAL (noop-safe).

- Runtime expectations (policy; not deps, but MUST be true for end-to-end value):
  - `platform/cache` can select store by config + enabled module, and has an internal driver/store registry keyed by name (`apcu`).
  - Key/value-like data MUST NOT appear in logs/spans/metrics labels; only redacted forms if logged.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `platform/cache`

Forbidden:
- `platform/http`
- `platform/cli`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\SimpleCache\CacheInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` (if used)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/cache-apcu/composer.json`
- [ ] `framework/packages/integrations/cache-apcu/src/Module/CacheApcuModule.php` — module entry (runtime enable)
- [ ] `framework/packages/integrations/cache-apcu/src/Provider/CacheApcuServiceProvider.php` — DI wiring
- [ ] `framework/packages/integrations/cache-apcu/src/Provider/CacheApcuServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/cache-apcu/config/cache_apcu.php` — config root `cache_apcu` (returns subtree, no repeated root)
- [ ] `framework/packages/integrations/cache-apcu/config/rules.php` — config shape rules
- [ ] `framework/packages/integrations/cache-apcu/README.md` — docs (Observability / Errors / Security-Redaction)

Implementation:
- [ ] `framework/packages/integrations/cache-apcu/src/Store/ApcuCacheStore.php` — PSR-16 store
- [ ] `framework/packages/integrations/cache-apcu/src/Exception/ApcuCacheException.php` — errorCode `CORETSIA_CACHE_APCU_FAILED`

Tests:
- [ ] `framework/packages/integrations/cache-apcu/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — noop-safe cross-cutting
- [ ] `framework/packages/integrations/cache-apcu/tests/Integration/ApcuStoreBasicSemanticsTest.php` — basic semantics

#### Modifies

- [ ] Update `docs/ssot/config-roots.md`:
  - Add row:
    - root: `cache_apcu`
    - owner: `integrations/cache-apcu`
    - defaults: `framework/packages/integrations/cache-apcu/config/cache_apcu.php`
    - rules: `framework/packages/integrations/cache-apcu/config/rules.php`

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/cache-apcu/composer.json`
- [ ] `framework/packages/integrations/cache-apcu/src/Module/CacheApcuModule.php`
- [ ] `framework/packages/integrations/cache-apcu/src/Provider/CacheApcuServiceProvider.php`
- [ ] `framework/packages/integrations/cache-apcu/config/cache_apcu.php`
- [ ] `framework/packages/integrations/cache-apcu/config/rules.php`
- [ ] `framework/packages/integrations/cache-apcu/README.md`
- [ ] `framework/packages/integrations/cache-apcu/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Composer metadata (MUST)

`composer.json` MUST include runtime module metadata for metadata-only discovery:
- `extra.coretsia.providers` (list of ServiceProvider FQCNs)
- `extra.coretsia.defaultsConfigPath` (path to defaults config file)
  No runtime filesystem scanning is allowed.

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/cache-apcu/config/cache_apcu.php`

- [ ] Keys (dot):
  - [ ] `cache_apcu.enabled` = false
  - [ ] `cache_apcu.prefix` = 'coretsia:'

- [ ] Rules:
  - [ ] `framework/packages/integrations/cache-apcu/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (no new tags; binding is direct via ServiceProvider)
- [ ] ServiceProvider wiring evidence:
  - [ ] binds PSR-16 store into `platform/cache` driver/store registry by name (`apcu`)
  - [ ] never prints raw keys/values; any optional logs use hash/len only

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (for safe logs)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] APCu is process-local; no pooling state; if stateful adapter introduced → implement `ResetInterface`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `apcu.op` (attrs: op, outcome) (optional)
- [ ] Metrics:
  - [ ] reuse `cache.*` metrics only (no new metric names)
- [ ] Label normalization applied (if needed):
  - [ ] `op→operation` (if you label; prefer span attr)
- [ ] Logs:
  - [ ] never log raw keys/values; only `hash(key)` / `len(key)` (if anything is logged)

- Spans (optional, noop-safe):
  - Use canonical span names: `cache.get`, `cache.set`, `cache.delete`, `cache.clear`, `cache.has` (domain=`cache`, operation=`get|set|...`).
  - Span attributes MAY include:
    - `driver` = `redis|apcu`
    - `outcome` = `success|handled_error|fatal_error` (if mapped)
  - MUST NOT include cache keys or values (only `hash(key)` / `len(key)` if absolutely required for diagnostics).

- Metrics (optional, noop-safe):
  - If emitted, names MUST follow: `cache.op_total`, `cache.op_duration_ms`.
  - Labels MUST be allowlisted: `driver`, `operation`, `outcome` only.
  - MUST NOT label by key/prefix/value.

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/integrations/cache-apcu/src/Exception/ApcuCacheException.php` — errorCode `CORETSIA_CACHE_APCU_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw cache keys, payload values
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist (even optional/noop-safe) → `framework/packages/integrations/cache-apcu/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  (asserts: tracer/meter/logger missing/noop does not throw; no leakage via instrumentation path)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeLogger/FakeTracer/FakeMeter capture events for assertions (when used)

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/integrations/cache-apcu/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/cache-apcu/tests/Integration/ApcuStoreBasicSemanticsTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Driver is drop-in via module enable + config only
- [ ] No keys/values leakage in logs/spans/metrics labels
- [ ] Basic semantics test exists
- [ ] Docs in README cover configuration + redaction
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: prefix semantics deterministic; rerun-no-diff
- [ ] What problem this epic solves
  - [ ] Додати local APCu cache store як integration, не змінюючи `platform/cache` public API (PSR-16)
  - [ ] Deterministic prefix semantics (`cache_apcu.prefix`)
  - [ ] Noop-safe cross-cutting: відсутність tracer/meter/logger не ламає runtime
- [ ] Non-goals / out of scope
  - [ ] Залежність `platform/cache` від integrations (заборонено)
  - [ ] Будь-які нові contracts ports (заборонено)
- [ ] Увімкнення `integrations.cache-apcu` дає drop-in PSR-16 store без зміни app коду і без витоку секретів.
- [ ] When `cache.default='apcu'` and apcu module enabled, then `CacheInterface` resolves to APCu store and respects prefix deterministically.

---

### 6.70.0 integrations/lock-redis (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.70.0"
owner_path: "framework/packages/integrations/lock-redis/"

package_id: "integrations/lock-redis"
composer: "coretsia/integrations-lock-redis"
kind: runtime
module_id: "integrations.lock-redis"

goal: "Увімкнення `integrations.lock-redis` робить Redis-backed `LockFactoryInterface` доступним через selection в `platform/lock`, з redaction lock keys, deterministic Redis client factory та parity semantics."
provides:
- "Redis-backed `LockFactoryInterface` implementation (no raw lock key leakage)."
- "Deterministic Redis client factory (config-driven; optional password via secret_ref)."
- "Security redaction helpers (hash/len only) для lock keys/секретів."
- "Parity tests vs reference lock implementation (де можливо)."

tags_introduced: []
config_roots_introduced: ["lock_redis"]
artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required config roots/keys:
  - `lock.default` (в `platform/lock`) — select factory by name (e.g. `redis`).

- Required contracts / ports:
  - `Psr\Log\LoggerInterface`
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` — OPTIONAL (password via `lock_redis.password_secret_ref`).
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — OPTIONAL (noop-safe).
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — OPTIONAL (noop-safe).
  - `Coretsia\Contracts\Lock\LockFactoryInterface`

- Runtime expectations (policy; not deps):
  - `platform/lock` selects factories by config + enabled module, without depending on integrations.
  - Redaction policy is enforced end-to-end (no raw lock keys in logs/spans/metrics labels).

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `platform/lock`

Forbidden:
- `platform/http`
- `platform/cli`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` (if used)
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)
  - `Coretsia\Contracts\Lock\LockFactoryInterface`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/lock-redis/composer.json`
- [ ] `framework/packages/integrations/lock-redis/src/Module/LockRedisModule.php` — module entry
- [ ] `framework/packages/integrations/lock-redis/src/Provider/LockRedisServiceProvider.php` — DI wiring
- [ ] `framework/packages/integrations/lock-redis/src/Provider/LockRedisServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/lock-redis/config/lock_redis.php` — config root `lock_redis` (returns subtree)
- [ ] `framework/packages/integrations/lock-redis/config/rules.php` — config rules
- [ ] `framework/packages/integrations/lock-redis/README.md` — Observability / Errors / Security-Redaction

Implementation:
- [ ] `framework/packages/integrations/lock-redis/src/Lock/RedisLockFactory.php` — implements `LockFactoryInterface`
- [ ] `framework/packages/integrations/lock-redis/src/Redis/RedisClientFactory.php` — deterministic config-driven
- [ ] `framework/packages/integrations/lock-redis/src/Security/Redaction.php` — `hashKey()` helpers
- [ ] `framework/packages/integrations/lock-redis/src/Exception/RedisLockException.php` — errorCode `CORETSIA_LOCK_REDIS_FAILED`

Tests:
- [ ] `framework/packages/integrations/lock-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/integrations/lock-redis/tests/Unit/LockKeyRedactionTest.php`
- [ ] `framework/packages/integrations/lock-redis/tests/Integration/RedisLockAcquireReleaseParityTest.php`

Docs:
- [ ] `docs/ops/redis-drivers.md` — add lock-redis section (enable, secret_ref, redaction)

#### Modifies

- [ ] Update `docs/ssot/config-roots.md`:
  - Add row:
    - root: `lock_redis`
    - owner: `integrations/lock-redis`
    - defaults: `framework/packages/integrations/lock-redis/config/lock_redis.php`
    - rules: `framework/packages/integrations/lock-redis/config/rules.php`

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/lock-redis/composer.json`
- [ ] `framework/packages/integrations/lock-redis/src/Module/LockRedisModule.php`
- [ ] `framework/packages/integrations/lock-redis/src/Provider/LockRedisServiceProvider.php`
- [ ] `framework/packages/integrations/lock-redis/config/lock_redis.php`
- [ ] `framework/packages/integrations/lock-redis/config/rules.php`
- [ ] `framework/packages/integrations/lock-redis/README.md`
- [ ] `framework/packages/integrations/lock-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Composer metadata (MUST)

`composer.json` MUST include runtime module metadata for metadata-only discovery:
- `extra.coretsia.providers` (list of ServiceProvider FQCNs)
- `extra.coretsia.defaultsConfigPath` (path to defaults config file)
  No runtime filesystem scanning is allowed.

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/lock-redis/config/lock_redis.php`
- [ ] Keys (dot):
  - [ ] `lock_redis.enabled` = false
  - [ ] `lock_redis.host` = '127.0.0.1'
  - [ ] `lock_redis.port` = 6379
  - [ ] `lock_redis.database` = 0
  - [ ] `lock_redis.password_secret_ref` = null
  - [ ] `lock_redis.prefix` = 'lock:'          # deterministic namespace (optional but recommended)
- [ ] Rules:
  - [ ] `framework/packages/integrations/lock-redis/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (binding is direct via ServiceProvider)
- [ ] ServiceProvider wiring evidence:
  - [ ] binds `LockFactoryInterface` implementation into `platform/lock` factory registry by name (`redis`)
  - [ ] never logs raw lock keys or passwords; only `hash(value)` / `len(value)`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] redis client is stateless; if pooling state is introduced → implement `ResetInterface`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `redis.op` (attrs: op, outcome) (optional)
- [ ] Metrics:
  - [ ] reuse existing package metrics names (`lock.*`, `session.*`, `queue.*`) (no new metric names)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`
- [ ] Logs:
  - [ ] never log raw lock keys; only `hash(key)` / `len(key)`; never log raw endpoint/password

- Spans (optional, noop-safe):
  - `lock.acquire`, `lock.release` (domain=`lock`)
  - `session.read`, `session.write`, `session.delete` (domain=`session`)
  - `queue.push`, `queue.pop`, `queue.ack`, `queue.fail` (domain=`queue`)
  - Attributes MAY include `driver=redis`, `outcome=...` (allowlisted).
  - MUST NOT include raw ids/keys/payload (only hash/len where needed).

- Metrics (optional, noop-safe):
  - Names MUST follow `<domain>.<metric>_{total|duration_ms}`.
  - Labels MUST be allowlisted: `driver`, `operation`, `outcome` only.

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/integrations/lock-redis/src/Exception/RedisLockException.php` — errorCode `CORETSIA_LOCK_REDIS_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] redis password, raw lock keys
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` only

### Redis endpoint redaction (MUST)

- MUST NOT log/emit raw Redis endpoint (`host`, `host:port`, socket paths), db index, or password.
- Allowed diagnostics only:
  - `endpoint_hash = sha256(normalized_endpoint_descriptor)`
  - `db` MAY be reported only as int if it is not sensitive in your threat model; otherwise hash it too.
- Never print `password_secret_ref` resolution results.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] Redaction proof:
  - [ ] `framework/packages/integrations/lock-redis/tests/Unit/LockKeyRedactionTest.php`
- [ ] Noop-safe cross-cutting:
  - [ ] `framework/packages/integrations/lock-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/integrations/lock-redis/tests/Unit/LockKeyRedactionTest.php`
- Contract:
  - [ ] `framework/packages/integrations/lock-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/lock-redis/tests/Integration/RedisLockAcquireReleaseParityTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Drop-in driver selectable by config + module enable only (`lock.default='redis'`)
- [ ] No secrets/lock keys leakage in logs/spans/metrics labels
- [ ] Parity test exists and passes (where CI services available)
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] deps/forbidden respected (no `platform/http`; no cycles)
- [ ] Determinism: config-driven; prefix semantics stable; rerun-no-diff
- [ ] Docs updated:
  - [ ] `docs/ops/redis-drivers.md`
- [ ] When `lock.default='redis'` and `integrations.lock-redis` enabled, then `LockFactoryInterface` produces redis locks and never logs raw keys.

---

### 6.71.0 integrations/session-redis (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.71.0"
owner_path: "framework/packages/integrations/session-redis/"

package_id: "integrations/session-redis"
composer: "coretsia/integrations-session-redis"
kind: runtime
module_id: "integrations.session-redis"

goal: "Увімкнення `integrations.session-redis` робить Redis-backed `SessionStorageInterface` доступним через selection в `platform/session`, з redaction session ids, deterministic Redis client factory та parity semantics."
provides:
- "Redis-backed `SessionStorageInterface` implementation (no session id leakage)."
- "Deterministic Redis client factory (config-driven; optional password via secret_ref)."
- "Security redaction helpers (hash/len only) для session ids/секретів."
- "Parity tests vs reference session storage (де можливо)."

tags_introduced: []
config_roots_introduced: ["session_redis"]
artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required config roots/keys:
  - `session.default` (в `platform/session`) — select storage by name (e.g. `redis`).

- Required contracts / ports:
  - `Psr\Log\LoggerInterface`
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` — OPTIONAL (password via `session_redis.password_secret_ref`).
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — OPTIONAL (noop-safe).
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — OPTIONAL (noop-safe).
  - `Coretsia\Contracts\Session\SessionStorageInterface`

- Runtime expectations (policy; not deps):
  - `platform/session` selects storages by config + enabled module, without depending on integrations.
  - Redaction policy is enforced end-to-end (no session ids/tokens in logs/spans/metrics labels).

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `platform/session`

Forbidden:
- `platform/http`
- `platform/cli`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` (if used)
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)
  - `Coretsia\Contracts\Session\SessionStorageInterface`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/session-redis/composer.json`
- [ ] `framework/packages/integrations/session-redis/src/Module/SessionRedisModule.php`
- [ ] `framework/packages/integrations/session-redis/src/Provider/SessionRedisServiceProvider.php`
- [ ] `framework/packages/integrations/session-redis/src/Provider/SessionRedisServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/session-redis/config/session_redis.php` — config root `session_redis`
- [ ] `framework/packages/integrations/session-redis/config/rules.php`
- [ ] `framework/packages/integrations/session-redis/README.md`

Implementation:
- [ ] `framework/packages/integrations/session-redis/src/Storage/RedisSessionStorage.php` — implements `SessionStorageInterface`
- [ ] `framework/packages/integrations/session-redis/src/Redis/RedisClientFactory.php` — deterministic config-driven
- [ ] `framework/packages/integrations/session-redis/src/Security/Redaction.php` — never logs session id
- [ ] `framework/packages/integrations/session-redis/src/Exception/RedisSessionException.php` — errorCode `CORETSIA_SESSION_REDIS_FAILED`

Tests:
- [ ] `framework/packages/integrations/session-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/integrations/session-redis/tests/Integration/RedisSessionStorageParityTest.php`

Docs:
- [ ] `docs/ops/redis-drivers.md` — add session-redis section (ttl, secret_ref, redaction)

#### Modifies

- [ ] Update `docs/ssot/config-roots.md`:
  - Add row:
    - root: `session_redis`
    - owner: `integrations/session-redis`
    - defaults: `framework/packages/integrations/session-redis/config/session_redis.php`
    - rules: `framework/packages/integrations/session-redis/config/rules.php`

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/session-redis/composer.json`
- [ ] `framework/packages/integrations/session-redis/src/Module/SessionRedisModule.php`
- [ ] `framework/packages/integrations/session-redis/src/Provider/SessionRedisServiceProvider.php`
- [ ] `framework/packages/integrations/session-redis/config/session_redis.php`
- [ ] `framework/packages/integrations/session-redis/config/rules.php`
- [ ] `framework/packages/integrations/session-redis/README.md`
- [ ] `framework/packages/integrations/session-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Composer metadata (MUST)

`composer.json` MUST include runtime module metadata for metadata-only discovery:
- `extra.coretsia.providers` (list of ServiceProvider FQCNs)
- `extra.coretsia.defaultsConfigPath` (path to defaults config file)
  No runtime filesystem scanning is allowed.

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/session-redis/config/session_redis.php`
- [ ] Keys (dot):
  - [ ] `session_redis.enabled` = false
  - [ ] `session_redis.host` = '127.0.0.1'
  - [ ] `session_redis.port` = 6379
  - [ ] `session_redis.database` = 0
  - [ ] `session_redis.password_secret_ref` = null
  - [ ] `session_redis.ttl_seconds` = 3600
  - [ ] `session_redis.prefix` = 'sess:'     # deterministic namespace (optional but recommended)
- [ ] Rules:
  - [ ] `framework/packages/integrations/session-redis/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (binding is direct via ServiceProvider)
- [ ] ServiceProvider wiring evidence:
  - [ ] binds `SessionStorageInterface` implementation into `platform/session` storage registry by name (`redis`)
  - [ ] never logs raw session ids/tokens; only `hash(value)` / `len(value)` and safe metadata

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] redis client is stateless; if pooling state is introduced → implement `ResetInterface`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `redis.op` (attrs: op, outcome) (optional)
- [ ] Metrics:
  - [ ] reuse existing package metrics names (`lock.*`, `session.*`, `queue.*`) (no new metric names)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`
- [ ] Logs:
  - [ ] never log raw session ids; only `hash(id)` / `len(id)`; never log raw endpoint/password

- Spans (optional, noop-safe):
  - `lock.acquire`, `lock.release` (domain=`lock`)
  - `session.read`, `session.write`, `session.delete` (domain=`session`)
  - `queue.push`, `queue.pop`, `queue.ack`, `queue.fail` (domain=`queue`)
  - Attributes MAY include `driver=redis`, `outcome=...` (allowlisted).
  - MUST NOT include raw ids/keys/payload (only hash/len where needed).

- Metrics (optional, noop-safe):
  - Names MUST follow `<domain>.<metric>_{total|duration_ms}`.
  - Labels MUST be allowlisted: `driver`, `operation`, `outcome` only.

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/integrations/session-redis/src/Exception/RedisSessionException.php` — errorCode `CORETSIA_SESSION_REDIS_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] redis password, session ids/tokens, session payload values
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` only

### Redis endpoint redaction (MUST)

- MUST NOT log/emit raw Redis endpoint (`host`, `host:port`, socket paths), db index, or password.
- Allowed diagnostics only:
  - `endpoint_hash = sha256(normalized_endpoint_descriptor)`
  - `db` MAY be reported only as int if it is not sensitive in your threat model; otherwise hash it too.
- Never print `password_secret_ref` resolution results.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] Noop-safe cross-cutting:
  - [ ] `framework/packages/integrations/session-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] Parity:
  - [ ] `framework/packages/integrations/session-redis/tests/Integration/RedisSessionStorageParityTest.php` (asserts basic semantics; never asserts on raw session ids in logs)

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/integrations/session-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/session-redis/tests/Integration/RedisSessionStorageParityTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Drop-in driver selectable by config + module enable only (`session.default='redis'`)
- [ ] No secrets/session-id leakage in logs/spans/metrics labels
- [ ] Parity test exists and passes (where CI services available)
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] deps/forbidden respected (no `platform/http`; no cycles)
- [ ] Determinism: config-driven; ttl int-only; prefix semantics stable; rerun-no-diff
- [ ] Docs updated:
  - [ ] `docs/ops/redis-drivers.md`
- [ ] When `session.default='redis'` and `integrations.session-redis` enabled, then `SessionStorageInterface` resolves to redis storage and never logs raw session ids.

---

### 6.72.0 integrations/queue-redis (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.72.0"
owner_path: "framework/packages/integrations/queue-redis/"

package_id: "integrations/queue-redis"
composer: "coretsia/integrations-queue-redis"
kind: runtime
module_id: "integrations.queue-redis"

goal: "Увімкнення `integrations.queue-redis` робить Redis-backed `QueueDriverInterface` доступним через selection в `platform/queue`, з redaction job payload, deterministic Redis client factory та parity semantics."
provides:
- "Redis-backed `QueueDriverInterface` implementation (no job payload leakage)."
- "Deterministic Redis client factory (config-driven; optional password via secret_ref)."
- "Security redaction helpers (hash/len only) для job ids/keys/секретів."
- "Parity tests vs reference queue driver (де можливо)."

tags_introduced: []
config_roots_introduced: ["queue_redis"]
artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required config roots/keys:
  - `queue.default` (в `platform/queue`) — select driver by name (e.g. `redis`).

- Required contracts / ports:
  - `Psr\Log\LoggerInterface`
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` — OPTIONAL (password via `queue_redis.password_secret_ref`).
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — OPTIONAL (noop-safe).
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — OPTIONAL (noop-safe).
  - `Coretsia\Contracts\Queue\QueueDriverInterface`

- Runtime expectations (policy; not deps):
  - `platform/queue` selects drivers by config + enabled module, without depending on integrations.
  - Redaction policy is enforced end-to-end (no job payloads in logs/spans/metrics labels).

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `platform/queue`

Forbidden:
- `platform/http`
- `platform/cli`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` (if used)
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)
  - `Coretsia\Contracts\Queue\QueueDriverInterface`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/queue-redis/composer.json`
- [ ] `framework/packages/integrations/queue-redis/src/Module/QueueRedisModule.php`
- [ ] `framework/packages/integrations/queue-redis/src/Provider/QueueRedisServiceProvider.php`
- [ ] `framework/packages/integrations/queue-redis/src/Provider/QueueRedisServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/queue-redis/config/queue_redis.php`
- [ ] `framework/packages/integrations/queue-redis/config/rules.php`
- [ ] `framework/packages/integrations/queue-redis/README.md`

Implementation:
- [ ] `framework/packages/integrations/queue-redis/src/Driver/RedisQueueDriver.php` — implements `QueueDriverInterface`
- [ ] `framework/packages/integrations/queue-redis/src/Redis/RedisClientFactory.php` — deterministic config-driven
- [ ] `framework/packages/integrations/queue-redis/src/Security/Redaction.php` — never logs payload
- [ ] `framework/packages/integrations/queue-redis/src/Exception/RedisQueueException.php` — errorCode `CORETSIA_QUEUE_REDIS_FAILED`

Tests:
- [ ] `framework/packages/integrations/queue-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/integrations/queue-redis/tests/Integration/RedisQueueDriverParityTest.php`

Docs:
- [ ] `docs/ops/redis-drivers.md` — add queue-redis section (prefix, secret_ref, redaction)

#### Modifies

- [ ] Update `docs/ssot/config-roots.md`:
  - Add row:
    - root: `queue_redis`
    - owner: `integrations/queue-redis`
    - defaults: `framework/packages/integrations/queue-redis/config/queue_redis.php`
    - rules: `framework/packages/integrations/queue-redis/config/rules.php`

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/queue-redis/composer.json`
- [ ] `framework/packages/integrations/queue-redis/src/Module/QueueRedisModule.php`
- [ ] `framework/packages/integrations/queue-redis/src/Provider/QueueRedisServiceProvider.php`
- [ ] `framework/packages/integrations/queue-redis/config/queue_redis.php`
- [ ] `framework/packages/integrations/queue-redis/config/rules.php`
- [ ] `framework/packages/integrations/queue-redis/README.md`
- [ ] `framework/packages/integrations/queue-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Composer metadata (MUST)

`composer.json` MUST include runtime module metadata for metadata-only discovery:
- `extra.coretsia.providers` (list of ServiceProvider FQCNs)
- `extra.coretsia.defaultsConfigPath` (path to defaults config file)
  No runtime filesystem scanning is allowed.

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/queue-redis/config/queue_redis.php`
- [ ] Keys (dot):
  - [ ] `queue_redis.enabled` = false
  - [ ] `queue_redis.host` = '127.0.0.1'
  - [ ] `queue_redis.port` = 6379
  - [ ] `queue_redis.database` = 0
  - [ ] `queue_redis.password_secret_ref` = null
  - [ ] `queue_redis.prefix` = 'queue:'
- [ ] Rules:
  - [ ] `framework/packages/integrations/queue-redis/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (binding is direct via ServiceProvider)
- [ ] ServiceProvider wiring evidence:
  - [ ] binds `QueueDriverInterface` implementation into `platform/queue` driver registry by name (`redis`)
  - [ ] never logs raw job payloads; only hash/len and safe metadata

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] redis client is stateless; if buffering exists → implement `ResetInterface`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `redis.op` (attrs: op, outcome) (optional)
- [ ] Metrics:
  - [ ] reuse existing package metrics names (`lock.*`, `session.*`, `queue.*`) (no new metric names)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`
- [ ] Logs:
  - [ ] never log job payload; never log raw keys; use `hash(value)` / `len(value)`

- Spans (optional, noop-safe):
  - `lock.acquire`, `lock.release` (domain=`lock`)
  - `session.read`, `session.write`, `session.delete` (domain=`session`)
  - `queue.push`, `queue.pop`, `queue.ack`, `queue.fail` (domain=`queue`)
  - Attributes MAY include `driver=redis`, `outcome=...` (allowlisted).
  - MUST NOT include raw ids/keys/payload (only hash/len where needed).

- Metrics (optional, noop-safe):
  - Names MUST follow `<domain>.<metric>_{total|duration_ms}`.
  - Labels MUST be allowlisted: `driver`, `operation`, `outcome` only.

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/integrations/queue-redis/src/Exception/RedisQueueException.php` — errorCode `CORETSIA_QUEUE_REDIS_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] redis password, job payload, raw queue keys
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` only

### Redis endpoint redaction (MUST)

- MUST NOT log/emit raw Redis endpoint (`host`, `host:port`, socket paths), db index, or password.
- Allowed diagnostics only:
  - `endpoint_hash = sha256(normalized_endpoint_descriptor)`
  - `db` MAY be reported only as int if it is not sensitive in your threat model; otherwise hash it too.
- Never print `password_secret_ref` resolution results.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] Noop-safe cross-cutting:
  - [ ] `framework/packages/integrations/queue-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] Parity:
  - [ ] `framework/packages/integrations/queue-redis/tests/Integration/RedisQueueDriverParityTest.php` (asserts basic semantics; never asserts on payload leakage)

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/integrations/queue-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/queue-redis/tests/Integration/RedisQueueDriverParityTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Drop-in driver selectable by config + module enable only (`queue.default='redis'`)
- [ ] No secrets/payload leakage in logs/spans/metrics labels
- [ ] Parity test exists and passes (where CI services available)
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] deps/forbidden respected (no `platform/http`; no cycles)
- [ ] Determinism: config-driven; prefix stable; rerun-no-diff
- [ ] Docs updated:
  - [ ] `docs/ops/redis-drivers.md`
- [ ] When `queue.default='redis'` and `integrations.queue-redis` enabled, then `QueueDriverInterface` resolves to redis driver and never logs raw job payloads.

---

### 6.80.0 coretsia/features (feature flags + experiments + analytics hooks + CLI) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.80.0"
owner_path: "framework/packages/platform/features/"

package_id: "platform/features"
composer: "coretsia/platform-features"
kind: runtime
module_id: "platform.features"

goal: "Feature flags можна оцінювати детерміновано в будь-якому UoW, а CLI може показати список/стан без витоку секретів."
provides:
- "Deterministic feature evaluation rules доступні через DI"
- "Reference repository: in-memory + optional PSR-16 cache-backed (без driver coupling)"
- "CLI commands `features:list|features:dump` через reverse discovery (`cli.command` tag)"
- "Observability helper (noop-safe) без high-cardinality labels (flag name only span/log field)"

tags_introduced: []  # `cli.command` tag already exists; this epic only uses it
config_roots_introduced: ["features"]
artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
- docs/ssot/modes.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required config roots/keys:
  - `features.*` — introduced by this epic.
  - `platform/cli` supports deterministic discovery of commands via `cli.command` tag (runtime expectation; compile-time forbidden).

- Required tags:
  - `cli.command` — command discovery by `platform/cli` (this package attaches the tag to its command services).

- Required contracts / ports:
  - `Psr\Log\LoggerInterface`
  - `Psr\SimpleCache\CacheInterface` — OPTIONAL (only if repository driver is `cache`)
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - Foundation (if used):
    - `Coretsia\Foundation\Discovery\DeterministicOrder`

- Runtime expectations (policy; not deps):
  - `platform/logging|tracing|metrics` provide implementations or noop-safe ports.
  - CLI outputs must be safe: no secrets/PII; do not print full flag payload if it may contain sensitive data.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/cli`
- `platform/http`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
  - `Psr\SimpleCache\CacheInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - `features:list` → `platform/features` `framework/packages/platform/features/src/Console/FeaturesListCommand.php`
  - `features:dump` → `platform/features` `framework/packages/platform/features/src/Console/FeaturesDumpCommand.php`
- HTTP:
  - none
- Kernel hooks/tags:
  - none
- Artifacts:
  - reads: none
  - writes: none

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/features/src/Module/FeaturesModule.php` — module entry
- [ ] `framework/packages/platform/features/src/Provider/FeaturesServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/features/src/Provider/FeaturesServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/features/config/features.php` — config root `features` (returns subtree, no repeated root)
- [ ] `framework/packages/platform/features/config/rules.php` — config shape rules
- [ ] `framework/packages/platform/features/README.md` — Observability / Errors / Security-Redaction
- [ ] `framework/packages/platform/features/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — noop-safe cross-cutting

Implementation:
- [ ] `framework/packages/platform/features/src/Feature/FeatureRepositoryInterface.php` — package API
- [ ] `framework/packages/platform/features/src/Feature/InMemoryFeatureRepository.php` — reference storage
- [ ] `framework/packages/platform/features/src/Feature/CacheBackedFeatureRepository.php` — optional PSR-16 backed
- [ ] `framework/packages/platform/features/src/Feature/FeatureEvaluator.php` — deterministic evaluation rules
- [ ] `framework/packages/platform/features/src/Observability/FeatureInstrumentation.php` — spans/metrics helper (noop-safe)
- [ ] `framework/packages/platform/features/src/Console/FeaturesListCommand.php` — safe list output
- [ ] `framework/packages/platform/features/src/Console/FeaturesDumpCommand.php` — safe dump output
- [ ] `framework/packages/platform/features/src/Exception/FeaturesException.php` — errorCodes:
  - `CORETSIA_FEATURES_INVALID_FLAG`
  - `CORETSIA_FEATURES_REPOSITORY_FAILED`

Docs:
- [ ] `docs/architecture/features.md` — usage in HTTP/CLI/Queue + wiring notes

Tests:
- [ ] `framework/packages/platform/features/tests/Unit/FeatureEvaluatorDeterministicTest.php`
- [ ] `framework/packages/platform/features/tests/Integration/CliCommandsDoNotLeakSecretsTest.php`
- [ ] `framework/packages/platform/features/tests/Integration/CacheBackedRepositoryParityTest.php` (optional when cache available)
- [ ] `framework/packages/platform/cli/tests/Integration/Cli/FeaturesCommandsSchemaJsonTest.php` (optional; if standardized JSON output)

#### Modifies

- [ ] Update `docs/ssot/config-roots.md`:
  - Add row:
    - root: `features`
    - owner: `platform/features`
    - defaults: `framework/packages/platform/features/config/features.php`
    - rules: `framework/packages/platform/features/config/rules.php`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/features/composer.json`
- [ ] `framework/packages/platform/features/src/Module/FeaturesModule.php`
- [ ] `framework/packages/platform/features/src/Provider/FeaturesServiceProvider.php`
- [ ] `framework/packages/platform/features/config/features.php`
- [ ] `framework/packages/platform/features/config/rules.php`
- [ ] `framework/packages/platform/features/README.md`
- [ ] `framework/packages/platform/features/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Composer metadata (MUST)

`composer.json` MUST include runtime module metadata for metadata-only discovery:
- `extra.coretsia.providers` (list of ServiceProvider FQCNs)
- `extra.coretsia.defaultsConfigPath` (path to defaults config file)
  No runtime filesystem scanning is allowed.

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/features/config/features.php`
- [ ] Keys (dot):
  - [ ] `features.enabled` = true
  - [ ] `features.repository.driver` = 'memory'                 # 'memory'|'cache'
  - [ ] `features.cache.store` = null                           # optional cache store name
  - [ ] `features.flags` = []                                   # map flag => bool|scalar|map (json-like)
  - [ ] `features.cli.enabled` = true
- [ ] Rules:
  - [ ] `framework/packages/platform/features/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - N/A (tag `cli.command` owned by `platform/cli`; this package contributes)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Features\Feature\FeatureEvaluator::class`
  - [ ] registers: `\Coretsia\Features\Console\FeaturesListCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"features:list"}`
  - [ ] registers: `\Coretsia\Features\Console\FeaturesDumpCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"features:dump"}`

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
  - [ ] none
- [ ] Reset discipline:
  - [ ] cache-backed repository (if memoized state exists) implements `ResetInterface`
  - [ ] stateful services tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `features.evaluate` (attrs: flag name as span attr, outcome; no PII)
- [ ] Metrics:
  - [ ] `features.evaluate_total` (labels: `outcome`)
  - [ ] `features.evaluate_duration_ms` (labels: `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `kind/op/uow_type→operation` (prefer span attrs; do not label by flag name)
- [ ] Logs:
  - [ ] debug only in local; never print full flag payload if it may contain sensitive data
  - [ ] no secrets/PII (hash/len only)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/features/src/Exception/FeaturesException.php` — errorCodes:
    - `CORETSIA_FEATURES_INVALID_FLAG`
    - `CORETSIA_FEATURES_REPOSITORY_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] any secret-like config values in CLI output
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `framework/packages/platform/features/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  (asserts: noop-safe ports; instrumentation path does not throw)
- [ ] If redaction/safe output exists → `framework/packages/platform/features/tests/Integration/CliCommandsDoNotLeakSecretsTest.php`
  (asserts: CLI never prints secret-like values)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeTracer/FakeMetrics/FakeLogger capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/features/tests/Unit/FeatureEvaluatorDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/features/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/features/tests/Integration/CliCommandsDoNotLeakSecretsTest.php`
  - [ ] `framework/packages/platform/features/tests/Integration/CacheBackedRepositoryParityTest.php` (optional)
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Dependency rules satisfied (no `platform/cli` dep; reverse discovery only)
- [ ] Observability policy satisfied (no high-cardinality labels; flag name only span/log)
- [ ] Determinism: same inputs → same decision
- [ ] Tests: unit + contract + integration pass
- [ ] Docs: README sections complete and accurate
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] What problem this epic solves
  - [ ] Дає канонічний feature flags/evaluation механізм, доступний однаково у HTTP/CLI/Queue/Scheduler через DI
  - [ ] Дає CLI інструменти для інспекції фіч (`features:list`, `features:dump`) без залежності на `platform/cli` (reverse discovery via tag)
  - [ ] Дає (optional) cache-backed storage через PSR-16 без жорсткої прив’язки до конкретного драйвера
- [ ] Non-goals / out of scope
  - [ ] Не вводимо нові contracts ports (фічі лишаються platform-пакетом)
  - [ ] Не додаємо high-cardinality метрики/лейбли (flag name тільки span attribute / log field)
  - [ ] Не додаємо HTTP endpoints (CLI/DI-only)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
  - [ ] optional PSR-16 cache is available when `platform/cache` (or any PSR-16 binding) enabled
- [ ] Discovery / wiring is via tags:
  - [ ] `cli.command` (commands discovered by `platform/cli`)
- [ ] Feature flags можна оцінювати детерміновано в будь-якому UoW, а CLI може показати список/стан без витоку секретів.
- [ ] When a flag is evaluated in two runs with same inputs, then the decision is identical and only safe metadata is logged.

---

### 6.90.0 coretsia/outbox (reliable publish) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.90.0"
owner_path: "framework/packages/platform/outbox/"

package_id: "platform/outbox"
composer: "coretsia/platform-outbox"
kind: runtime
module_id: "platform.outbox"

goal: "Outbox rows можна детерміновано опублікувати (batch) без витоку payload, з повторним запуском без side-effect surprises."
provides:
- "Reference outbox pattern: persist → publish later (at-least-once) без втрати при падіннях"
- "Batch publisher з deterministic selection + marking policy (idempotent)"
- "Queue-friendly job+handler (NO payload) для async publish (optional)"
- "Observability helper (noop-safe) з low-cardinality labels"

tags_introduced: []
config_roots_introduced: ["outbox"]
artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required contracts / ports:
  - `Psr\Log\LoggerInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface` — OPTIONAL (only if worker loop state exists)
  - OPTIONAL (if publishing via queue):
    - `Coretsia\Contracts\Queue\QueueInterface`
  - OPTIONAL:
    - `Coretsia\Contracts\Events\EventDispatcherInterface`

- Required platform capabilities:
  - `platform/database` provides deterministic DB access (SQLite-first tests) + migrations execution.

- Runtime expectations (policy; not deps):
  - `platform/logging|tracing|metrics` provide implementations/noop-safe.
  - If `outbox.dispatch.via='queue'`, `platform/queue` is enabled and job handler mapping is configured by app config (no magic).

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `core/kernel`
- `platform/database`

Forbidden:
- `platform/http`
- `platform/cli`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` (if used)
  - `Coretsia\Contracts\Queue\QueueInterface` (optional)
  - `Coretsia\Contracts\Events\EventDispatcherInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface` (if worker loop state exists)

### Entry points / integration points (MUST)

- CLI:
  - none
- HTTP:
  - none
- Kernel hooks/tags:
  - none
- Queue (integration point; configured externally):
  - `PublishOutboxJob` → `platform/outbox` `framework/packages/platform/outbox/src/Worker/PublishOutboxJob.php`
  - `PublishOutboxJobHandler` → `platform/outbox` `framework/packages/platform/outbox/src/Worker/PublishOutboxJobHandler.php`
  - wiring into queue job map is documented (no hard dep on `platform/queue`)
- Artifacts:
  - reads: none
  - writes: none

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/outbox/src/Module/OutboxModule.php` — module entry
- [ ] `framework/packages/platform/outbox/src/Provider/OutboxServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/outbox/src/Provider/OutboxServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/outbox/config/outbox.php` — config root `outbox` (returns subtree, no repeated root)
- [ ] `framework/packages/platform/outbox/config/rules.php` — config shape rules
- [ ] `framework/packages/platform/outbox/README.md` — Observability / Errors / Security-Redaction
- [ ] `framework/packages/platform/outbox/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — noop-safe cross-cutting

Database + core implementation:
- [ ] `framework/packages/platform/outbox/database/migrations/2026_0000_000000_create_outbox_table.php` — outbox table schema
- [ ] `framework/packages/platform/outbox/src/Outbox/OutboxRepository.php` — DB repository (SQLite-first deterministic)
- [ ] `framework/packages/platform/outbox/src/Outbox/OutboxRow.php` — VO (schemaVersion, json-like)
- [ ] `framework/packages/platform/outbox/src/Outbox/OutboxPublisher.php` — batch publish + marking policy
- [ ] `framework/packages/platform/outbox/src/Worker/PublishOutboxJob.php` — queue job definition (NO payload)
- [ ] `framework/packages/platform/outbox/src/Worker/PublishOutboxJobHandler.php` — job handler (publishes batch)
- [ ] `framework/packages/platform/outbox/src/Observability/OutboxInstrumentation.php` — spans/metrics helper
- [ ] `framework/packages/platform/outbox/src/Exception/OutboxException.php` — errorCodes:
  - `CORETSIA_OUTBOX_PUBLISH_FAILED`
  - `CORETSIA_OUTBOX_SCHEMA_INVALID`

Docs:
- [ ] `docs/architecture/outbox.md` — pattern, schema, idempotency, wiring to queue/webhooks

Tests:
- [ ] `framework/packages/platform/outbox/tests/Unit/PublisherBatchSelectionDeterministicTest.php`
- [ ] `framework/packages/platform/outbox/tests/Integration/PublishOnSqliteDeterministicTest.php`
- [ ] `framework/packages/platform/outbox/tests/Integration/NoPayloadLeakInLogsTest.php`
- [ ] `framework/tools/tests/Integration/E2E/OutboxPublishJobWorksTest.php` (optional; if queue fixture wired)
- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` (optional fixture wiring note)

#### Modifies

- [ ] Update `docs/ssot/config-roots.md`:
  - Add row:
    - root: `outbox`
    - owner: `platform/outbox`
    - defaults: `framework/packages/platform/outbox/config/outbox.php`
    - rules: `framework/packages/platform/outbox/config/rules.php`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/outbox/composer.json`
- [ ] `framework/packages/platform/outbox/src/Module/OutboxModule.php`
- [ ] `framework/packages/platform/outbox/src/Provider/OutboxServiceProvider.php`
- [ ] `framework/packages/platform/outbox/config/outbox.php`
- [ ] `framework/packages/platform/outbox/config/rules.php`
- [ ] `framework/packages/platform/outbox/README.md`
- [ ] `framework/packages/platform/outbox/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Composer metadata (MUST)

`composer.json` MUST include runtime module metadata for metadata-only discovery:
- `extra.coretsia.providers` (list of ServiceProvider FQCNs)
- `extra.coretsia.defaultsConfigPath` (path to defaults config file)
  No runtime filesystem scanning is allowed.

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/outbox/config/outbox.php`
- [ ] Keys (dot):
  - [ ] `outbox.enabled` = false
  - [ ] `outbox.storage.table` = 'outbox'
  - [ ] `outbox.publisher.enabled` = true
  - [ ] `outbox.publisher.batch_size` = 100
  - [ ] `outbox.publisher.lock.enabled` = true
  - [ ] `outbox.publisher.lock_key` = 'outbox:publish'
  - [ ] `outbox.publisher.lock_ttl_seconds` = 60
  - [ ] `outbox.dispatch.via` = 'direct'                       # 'direct'|'queue' (policy)
  - [ ] `outbox.queue.job_name` = 'PublishOutboxJob'            # if via=queue
- [ ] Rules:
  - [ ] `framework/packages/platform/outbox/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (no new tags; external wiring to queue is via config, not tags)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Outbox\Outbox\OutboxPublisher::class`
  - [ ] registers: `\Coretsia\Outbox\Worker\PublishOutboxJobHandler::class`
  - [ ] documents how to wire handler into queue job map (platform/queue config) without importing `platform/queue`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] worker loop flags/state implement `ResetInterface`
  - [ ] stateful services tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `outbox.publish` (attrs: outcome, batch_size; no payload)
- [ ] Metrics:
  - [ ] `outbox.published_total` (labels: `outcome`)
  - [ ] `outbox.failed_total` (labels: `outcome`)
  - [ ] `outbox.publish_duration_ms` (labels: `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `kind/op/uow_type→operation` (prefer span attrs; never label by event type)
- [ ] Logs:
  - [ ] publish summary (counts only), no payload
  - [ ] no secrets/PII (hash/len only)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/outbox/src/Exception/OutboxException.php` — errorCodes:
    - `CORETSIA_OUTBOX_PUBLISH_FAILED`
    - `CORETSIA_OUTBOX_SCHEMA_INVALID`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] row payload, tokens, raw SQL, request bodies
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id`)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `framework/packages/platform/outbox/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  (asserts: noop-safe ports; instrumentation path does not throw)
- [ ] If redaction/no-payload policy exists → `framework/packages/platform/outbox/tests/Integration/NoPayloadLeakInLogsTest.php`
  (asserts: payload never appears in logs)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeTracer/FakeMetrics/FakeLogger capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/outbox/tests/Unit/PublisherBatchSelectionDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/outbox/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/outbox/tests/Integration/PublishOnSqliteDeterministicTest.php`
  - [ ] `framework/packages/platform/outbox/tests/Integration/NoPayloadLeakInLogsTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Dependency rules satisfied (no `platform/queue` hard dep; ports only)
- [ ] Observability policy satisfied (no payload; low-cardinality labels)
- [ ] Determinism: stable row selection + stable marking policy
- [ ] Tests: unit + contract + integration pass
- [ ] Docs: README sections complete and accurate
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] What problem this epic solves
  - [ ] Дає reference outbox pattern: persist → publish later (at-least-once) без втрати при падіннях
  - [ ] Дозволяє publish через queue/webhook/transport (через contracts ports; без hard deps на реалізації)
  - [ ] Інтегрується з UoW/reset дисципліною (publisher/worker може обгортати виконання в KernelRuntime)
- [ ] Non-goals / out of scope
  - [ ] Не робимо “монолітний інтеграційний автобус” (тільки outbox storage + publisher)
  - [ ] Не логимо payload (ніколи)
  - [ ] Не робимо зовнішні транспорти напряму (webhooks/queue інтеграція через ports/config)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
  - [ ] `platform/queue` available if you enable async publish path (optional)
- [ ] Discovery / wiring is via tags:
  - [ ] (optional) queue handler mapping is configured in queue package config (no magic)
  - [ ] (optional) `kernel.hook.*` if you later add hooks; kernel remains dumb orchestrator
- [ ] Outbox rows можна детерміновано опублікувати (batch) без витоку payload, з повторним запуском без side-effect surprises.
- [ ] When outbox publisher runs twice, then already published rows are not re-published (idempotent marking policy) and output remains deterministic.

---

### 6.100.0 coretsia/inbox (idempotency middleware + store) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.100.0"
owner_path: "framework/packages/platform/inbox/"

package_id: "platform/inbox"
composer: "coretsia/platform-inbox"
kind: runtime
module_id: "platform.inbox"

goal: "Повторний HTTP запит з тим самим `Idempotency-Key` повертає той самий результат детерміновано, а конфлікт (інший fingerprint) дає 409 через canonical error flow."
provides:
- "PSR-15 middleware idempotency layer (`Idempotency-Key`) з deterministic fingerprint"
- "Reference store: DB preferred (SQLite-first) + optional PSR-16 cache store fallback"
- "Canonical error flow: conflict → deterministic exception → ExceptionMapper → RFC7807 (409)"
- "Observability helper (noop-safe) з low-cardinality labels; fingerprint never logged"

tags_introduced: []  # uses existing tags: http.middleware.app_pre, error.mapper
config_roots_introduced: ["inbox"]
artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required tags:
  - `http.middleware.app_pre` — executed by `platform/http` before routing boundary.
  - `error.mapper` — executed by `platform/errors` for exception → ErrorDescriptor mapping.

- Required contracts / ports:
  - PSR:
    - `Psr\Http\Server\MiddlewareInterface`
    - `Psr\Http\Message\ServerRequestInterface`
    - `Psr\Http\Message\ResponseInterface`
    - `Psr\Log\LoggerInterface`
    - `Psr\SimpleCache\CacheInterface` (optional fallback store)
  - Contracts:
    - `Coretsia\Contracts\Context\ContextAccessorInterface`
    - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
    - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
    - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
    - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - Foundation stable APIs (if used):
    - `Coretsia\Foundation\Context\ContextKeys`

- Required platform capabilities:
  - `platform/database` provides deterministic DB access + migrations execution.
  - `platform/errors` runs `error.mapper` chain and `platform/problem-details` renders RFC7807 ProblemDetails (runtime expectation; compile-time forbidden dep on `platform/http` only, but runtime must exist for middleware to execute).

- Middleware ordering policy expectations (must be documented + tested):
  - CSRF priority `100` → uploads priority `80` → inbox priority `50` (after session/auth/csrf/uploads if enabled; before routing boundary).

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `platform/database`

Forbidden:
- `platform/http`
- `platform/cli`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
  - `Psr\SimpleCache\CacheInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- CLI:
  - none
- HTTP:
  - middleware slots/tags:
    - `http.middleware.app_pre` priority `50` meta `{"reason":"idempotency after csrf/uploads, before routing"}`
    - service: `\Coretsia\Inbox\Http\Middleware\IdempotencyKeyMiddleware::class`
- Kernel hooks/tags:
  - none
- Errors:
  - mapper tag `error.mapper` priority `300` meta `{"handles":"IdempotencyConflictException"}`
- Artifacts:
  - reads: none
  - writes: none

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/inbox/src/Module/InboxModule.php` — module entry
- [ ] `framework/packages/platform/inbox/src/Provider/InboxServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/inbox/src/Provider/InboxServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/inbox/config/inbox.php` — config root `inbox` (returns subtree, no repeated root)
- [ ] `framework/packages/platform/inbox/config/rules.php` — config shape rules
- [ ] `framework/packages/platform/inbox/README.md` — Observability / Errors / Security-Redaction
- [ ] `framework/packages/platform/inbox/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — noop-safe cross-cutting

Database + core implementation:
- [ ] `framework/packages/platform/inbox/database/migrations/2026_0000_000000_create_inbox_idempotency_table.php` — store schema
- [ ] `framework/packages/platform/inbox/src/Inbox/IdempotencyStoreInterface.php` — store abstraction
- [ ] `framework/packages/platform/inbox/src/Inbox/DbIdempotencyStore.php` — reference store (SQLite-first)
- [ ] `framework/packages/platform/inbox/src/Inbox/CacheIdempotencyStore.php` — optional PSR-16 store
- [ ] `framework/packages/platform/inbox/src/Inbox/RequestFingerprint.php` — deterministic fingerprint (hash only; no body logging)
- [ ] `framework/packages/platform/inbox/src/Http/Middleware/IdempotencyKeyMiddleware.php` — PSR-15 middleware

Errors + mapping:
- [ ] `framework/packages/platform/inbox/src/Exception/IdempotencyConflictException.php` — errorCode `CORETSIA_INBOX_IDEMPOTENCY_CONFLICT`
- [ ] `framework/packages/platform/inbox/src/Exception/InboxProblemMapper.php` — maps conflict → ErrorDescriptor(409)

Observability:
- [ ] `framework/packages/platform/inbox/src/Observability/InboxInstrumentation.php` — spans/metrics helper

Docs:
- [ ] `docs/architecture/inbox-idempotency.md` — ordering (CSRF 100 → uploads 80 → inbox 50), override notes, safe logging

Tests:
- [ ] `framework/packages/platform/inbox/tests/Unit/RequestFingerprintDeterministicTest.php`
- [ ] `framework/packages/platform/inbox/tests/Integration/SameKeySameFingerprintReturnsSameOutcomeTest.php`
- [ ] `framework/packages/platform/inbox/tests/Integration/SameKeyDifferentFingerprintReturns409ProblemDetailsTest.php`
- [ ] `framework/packages/platform/inbox/tests/Integration/MiddlewarePlacementIsAppPrePriority50Test.php`
- [ ] `framework/packages/platform/inbox/tests/Integration/NoBodyLeakInLogsTest.php`
- [ ] `framework/packages/platform/inbox/tests/Integration/Http/InboxIdempotencyEndToEndTest.php`
- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` (optional fixture wiring note)

#### Modifies

- [ ] Update `docs/ssot/config-roots.md`:
  - Add row:
    - root: `inbox`
    - owner: `platform/inbox`
    - defaults: `framework/packages/platform/inbox/config/inbox.php`
    - rules: `framework/packages/platform/inbox/config/rules.php`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/inbox/composer.json`
- [ ] `framework/packages/platform/inbox/src/Module/InboxModule.php`
- [ ] `framework/packages/platform/inbox/src/Provider/InboxServiceProvider.php`
- [ ] `framework/packages/platform/inbox/config/inbox.php`
- [ ] `framework/packages/platform/inbox/config/rules.php`
- [ ] `framework/packages/platform/inbox/README.md`
- [ ] `framework/packages/platform/inbox/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Composer metadata (MUST)

`composer.json` MUST include runtime module metadata for metadata-only discovery:
- `extra.coretsia.providers` (list of ServiceProvider FQCNs)
- `extra.coretsia.defaultsConfigPath` (path to defaults config file)
  No runtime filesystem scanning is allowed.

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/inbox/config/inbox.php`
- [ ] Keys (dot):
  - [ ] `inbox.idempotency.enabled` = false
  - [ ] `inbox.idempotency.header_name` = 'Idempotency-Key'
  - [ ] `inbox.idempotency.methods` = ['POST','PUT','PATCH']
  - [ ] `inbox.idempotency.ttl_seconds` = 86400
  - [ ] `inbox.idempotency.store` = 'db'                        # 'db'|'cache'
  - [ ] `inbox.idempotency.fingerprint.include_query` = false
  - [ ] `inbox.idempotency.fingerprint.include_headers` = []     # allowlist (safe only)
- [ ] Rules:
  - [ ] `framework/packages/platform/inbox/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - N/A (tag `http.middleware.app_pre` owned by `platform/http`; this package contributes)
  - N/A (tag `error.mapper` owned by `platform/errors`; this package contributes)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Inbox\Http\Middleware\IdempotencyKeyMiddleware::class`
  - [ ] adds tag: `http.middleware.app_pre` priority `50` meta `{"reason":"idempotency after csrf/uploads, before routing"}`
  - [ ] registers: `\Coretsia\Inbox\Exception\InboxProblemMapper::class`
  - [ ] adds tag: `error.mapper` priority `300` meta `{"handles":"IdempotencyConflictException"}`
  - [ ] binds `IdempotencyStoreInterface` to DB store by default when enabled

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::ACTOR_ID` (optional; never used as key material unless policy says)
  - [ ] `ContextKeys::CLIENT_IP` (optional; diagnostics only; never logged raw)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] any in-memory caches inside middleware/store implement `ResetInterface`
  - [ ] stateful services tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `inbox.idempotency` (attrs: outcome, method; no fingerprint/body)
- [ ] Metrics:
  - [ ] `inbox.hit_total` (labels: `outcome`)
  - [ ] `inbox.conflict_total` (labels: `outcome`)
  - [ ] `inbox.store_duration_ms` (labels: `driver`, `outcome`)     # driver=db|cache (via→driver)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`, `reason→status` (if you ever map HTTP code; prefer outcome/status separation)
- [ ] Logs:
  - [ ] conflict → info/warn без key/body; дозволено `hash(key)` і `len(key)`
  - [ ] no secrets/PII (hash/len only)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/inbox/src/Exception/IdempotencyConflictException.php` — errorCode `CORETSIA_INBOX_IDEMPOTENCY_CONFLICT`
- [ ] Mapping:
  - [ ] `ExceptionMapperInterface` via tag `error.mapper`
- [ ] HTTP status hint:
  - [ ] conflict → `httpStatus=409`

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/raw SQL/payload
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id`)

### Idempotency response storage safety (MUST)

If the store persists any material required to return “the same result”:
- MUST define an explicit storage model with strict allowlists and limits:
  - status code (int) — allowed
  - headers — allowlist only (no `Set-Cookie`, no `Authorization`, no session identifiers)
  - body — MUST NOT be stored by default
- If body storage is ever enabled (future), it MUST be:
  - explicitly gated by config,
  - size-limited,
  - and documented as security-sensitive.

Default policy (single-choice):
- Persist only: `fingerprint_hash`, `outcome`, `status`, and safe metadata.
- Never persist raw request/response payloads.

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `framework/packages/platform/inbox/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  (asserts: noop-safe ports; instrumentation path does not throw)
- [ ] If redaction/no-body policy exists → `framework/packages/platform/inbox/tests/Integration/NoBodyLeakInLogsTest.php`
  (asserts: request body/headers never appear in logs)
- [ ] If middleware wiring exists → `framework/packages/platform/inbox/tests/Integration/MiddlewarePlacementIsAppPrePriority50Test.php`
  (asserts: correct tag + priority)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/platform/inbox/tests/Integration/Http/InboxIdempotencyEndToEndTest.php` (boots minimal HTTP stack fixture)
- [ ] Fake adapters:
  - [ ] FakeTracer/FakeMetrics/FakeLogger capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/inbox/tests/Unit/RequestFingerprintDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/inbox/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/inbox/tests/Integration/SameKeySameFingerprintReturnsSameOutcomeTest.php`
  - [ ] `framework/packages/platform/inbox/tests/Integration/SameKeyDifferentFingerprintReturns409ProblemDetailsTest.php`
  - [ ] `framework/packages/platform/inbox/tests/Integration/MiddlewarePlacementIsAppPrePriority50Test.php`
  - [ ] `framework/packages/platform/inbox/tests/Integration/NoBodyLeakInLogsTest.php`
  - [ ] `framework/packages/platform/inbox/tests/Integration/Http/InboxIdempotencyEndToEndTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Dependency rules satisfied (no `platform/http` dep; middleware wired by tags)
- [ ] Observability policy satisfied (no body/headers; low-cardinality labels only)
- [ ] Determinism: same inputs → same fingerprint; store semantics deterministic
- [ ] Tests: unit + contract + integration pass
- [ ] Docs: README sections complete and accurate
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] What problem this epic solves
  - [ ] Додає idempotency для unsafe HTTP запитів через PSR-15 middleware (`Idempotency-Key`) з deterministic fingerprint
  - [ ] Дає reference store (DB preferred) + (optional) cache store (fallback), без дублювання low-level алгоритмів по контролерах
  - [ ] Інтегрує canonical error flow: конфлікт → deterministic exception → `ExceptionMapperInterface` → RFC7807 (409)
- [ ] Non-goals / out of scope
  - [ ] Не робимо “payment-grade” exactly-once; це idempotency layer (best-effort + correct semantics)
  - [ ] Не логимо raw body/headers; fingerprint тільки як hash (і не як metric label)
  - [ ] Не додаємо залежність `platform/http` (wiring only via DI tags)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/http` executes `http.middleware.app_pre`
  - [ ] `platform/errors` runs exception mappers (`error.mapper`) and `platform/problem-details` renders RFC7807
  - [ ] `platform/session|auth|security` may be enabled; ordering must respect SSoT middleware catalog
- [ ] Discovery / wiring is via tags:
  - [ ] `http.middleware.app_pre` (middleware)
  - [ ] `error.mapper` (problem mapper)
- [ ] Повторний HTTP запит з тим самим `Idempotency-Key` повертає той самий результат детерміновано, а конфлікт (інший fingerprint) дає 409 через canonical error flow.
- [ ] When two requests share the same idempotency key but different fingerprints, then second request is rejected with 409 ProblemDetails and no body leakage.

---

### 6.110.0 coretsia/event-sourcing (optional world) (OPTIONAL) [IMPL]

---
type: package
phase: 6+
epic_id: "6.110.0"
owner_path: "framework/packages/platform/event-sourcing/"

package_id: "platform/event-sourcing"
composer: "coretsia/platform-event-sourcing"
kind: runtime
module_id: "platform.event-sourcing"

goal: "Є один reference aggregate, який апендить події в store і може бути відтворений (replay) детерміновано в тестах."
provides:
- "Reference event store (append + read) поверх `platform/database` з deterministic behavior (SQLite-first)"
- "Мінімальний reference aggregate + projector як “optional world slice” без зміни ядра"
- "Optional інтеграція з `platform/events` через contracts dispatcher (без payload у logs/metrics labels)"

tags_introduced: []
config_roots_introduced: ["event_sourcing"]
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - (package deps) `core/contracts`, `core/foundation`, `core/kernel`, `platform/database` — потрібні для портів/DI/SQLite-first store
  - (optional runtime) `platform/logging|tracing|metrics` — мають бути noop-safe (policy), коли модуль увімкнено
  - (optional runtime) `platform/events` — якщо увімкнено інтеграцію з dispatcher/projectors як listeners

- Required deliverables (exact paths):
  - N/A (усе нове створюється цим епіком)

- Required config roots/keys:
  - `foundation.*` — доступ до Container/config у runtime (policy)
  - `event_sourcing.*` — root буде створений цим епіком (використання після наявності)

- Required tags:
  - `kernel.reset` — для reset дисципліни stateful сервісів (якщо з’являються)
  - (optional) `events.listener` — якщо проектори підключаються як listeners

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — spans
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics
  - (optional) `Coretsia\Contracts\Events\EventDispatcherInterface` — інтеграція з events (optional)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`
- `platform/database`

Forbidden:

- `platform/http`
- `platform/cli`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Events\EventDispatcherInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/event-sourcing/src/Module/EventSourcingModule.php` — runtime module entry
- [ ] `framework/packages/platform/event-sourcing/src/Provider/EventSourcingServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/event-sourcing/src/Provider/EventSourcingServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/event-sourcing/config/event_sourcing.php` — config subtree (no repeated root)
- [ ] `framework/packages/platform/event-sourcing/config/rules.php` — shape rules
- [ ] `framework/packages/platform/event-sourcing/README.md` — must include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/platform/event-sourcing/database/migrations/2026_0000_000000_create_event_store_table.php` — event store schema
- [ ] `framework/packages/platform/event-sourcing/src/Store/EventStore.php` — append/read API
- [ ] `framework/packages/platform/event-sourcing/src/Store/EventRecord.php` — VO (schemaVersion, json-like)
- [ ] `framework/packages/platform/event-sourcing/src/Aggregate/AggregateRoot.php` — reference base aggregate
- [ ] `framework/packages/platform/event-sourcing/src/Projection/Projector.php` — reference projector
- [ ] `framework/packages/platform/event-sourcing/src/Observability/EventSourcingInstrumentation.php` — spans/metrics helper
- [ ] `framework/packages/platform/event-sourcing/src/Exception/EventSourcingException.php` — deterministic error codes
- [ ] `framework/packages/platform/event-sourcing/src/Provider/Tags.php` — (optional) constants if package documents tags usage
- [ ] `framework/packages/platform/event-sourcing/tests/Fixtures/EventSourcingApp/config/modules.php` — reference fixture wiring
- [ ] `framework/packages/platform/event-sourcing/tests/Fixtures/EventSourcingApp/src/Domain/*` — fixture-only aggregate + events
- [ ] `docs/architecture/event-sourcing.md` — optional module rules + fixture walkthrough

Tests:
- [ ] `framework/packages/platform/event-sourcing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract (noop-safe)
- [ ] `framework/packages/platform/event-sourcing/tests/Unit/EventRecordShapeDeterministicTest.php` — unit proof for deterministic shape
- [ ] `framework/packages/platform/event-sourcing/tests/Integration/AppendAndReadOnSqliteDeterministicTest.php` — sqlite deterministic replay
- [ ] `framework/tools/tests/Integration/E2E/EventSourcingAppReplayWorksTest.php` — system-level fixture replay

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/event-sourcing/composer.json`
- [ ] `framework/packages/platform/event-sourcing/src/Module/EventSourcingModule.php`
- [ ] `framework/packages/platform/event-sourcing/src/Provider/EventSourcingServiceProvider.php`
- [ ] `framework/packages/platform/event-sourcing/config/event_sourcing.php`
- [ ] `framework/packages/platform/event-sourcing/config/rules.php`
- [ ] `framework/packages/platform/event-sourcing/README.md`
- [ ] `framework/packages/platform/event-sourcing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/event-sourcing/config/event_sourcing.php`
- [ ] Keys (dot):
  - [ ] `event_sourcing.enabled` = false
  - [ ] `event_sourcing.store.table` = 'event_store'
  - [ ] `event_sourcing.append.batch_size` = 100
  - [ ] `event_sourcing.read.max_events` = 10000
- [ ] Rules:
  - [ ] `framework/packages/platform/event-sourcing/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (uses existing tags; may provide constants only)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\EventSourcing\Store\EventStore::class`
  - [ ] (optional) registers projectors/listeners and documents tagging via `events.listener` (projector name NOT a metric label)

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
  - [ ] none
- [ ] Reset discipline:
  - [ ] stateful projector caches (if any) implement `ResetInterface`
  - [ ] stateful services tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `event_sourcing.append`
  - [ ] `event_sourcing.read`
- [ ] Metrics:
  - [ ] `event_sourcing.append_total` (labels: `outcome`)
  - [ ] `event_sourcing.append_duration_ms` (labels: `outcome`)
  - [ ] `event_sourcing.read_total` (labels: `outcome`)
  - [ ] `event_sourcing.read_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] errors only, no payload dump; event name only as span attr (NOT label)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/event-sourcing/src/Exception/EventSourcingException.php` — errorCode `CORETSIA_EVENT_SOURCING_APPEND_FAILED`
  - [ ] `framework/packages/platform/event-sourcing/src/Exception/EventSourcingException.php` — errorCode `CORETSIA_EVENT_SOURCING_READ_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] event payloads, raw SQL, tokens
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` (N/A: no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` (optional: only if stateful caches appear)
- [ ] If metrics/spans/logs exist → evidence:
  - [ ] `framework/packages/platform/event-sourcing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → evidence (payload non-leak expectation):
  - [ ] `framework/packages/platform/event-sourcing/tests/Integration/AppendAndReadOnSqliteDeterministicTest.php` (asserts: no payload leaks to logs)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/platform/event-sourcing/tests/Fixtures/EventSourcingApp/config/modules.php`
- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger (capture events for assertions) — via existing noop-safe implementations/policy

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/event-sourcing/tests/Unit/EventRecordShapeDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/event-sourcing/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/event-sourcing/tests/Integration/AppendAndReadOnSqliteDeterministicTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)
  - [ ] gates updated (if new invariants)

### DoD (MUST)

- [ ] Optional module works as reference slice and doesn’t change core flows
- [ ] No payload leakage and deterministic replay
- [ ] Tests green + docs complete
- [ ] Cutline impact: none
- [ ] Out of scope / non-goals remain true:
  - [ ] Не робимо повний CQRS/ES framework (лише reference slice)
  - [ ] Не робимо distributed snapshots/compaction (може бути пізніше)
  - [ ] Не вводимо нові contracts ports (використовуємо наявні contracts/events як optional)
- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Gates/Arch green
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: rerun-no-diff (where applicable)
- [ ] Docs updated:
  - [ ] `framework/packages/platform/event-sourcing/README.md`
  - [ ] `docs/architecture/event-sourcing.md`
- [ ] What problem this epic solves
  - [ ] Дає reference event store (append + read) поверх `platform/database` з deterministic behavior (SQLite-first)
  - [ ] Дає мінімальний reference aggregate + projector, щоб показати “optional world” без зміни ядра
  - [ ] Дозволяє інтеграцію з `platform/events` (optional) без жорсткої залежності на доменні події у logs/metrics labels
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
  - [ ] `platform/events` may be enabled if you want projections/listeners via event dispatcher
- [ ] Discovery / wiring is via tags:
  - [ ] (optional) `events.listener` if you add projectors as listeners (projector name NOT a metric label)
- [ ] Є один reference aggregate, який апендить події в store і може бути відтворений (replay) детерміновано в тестах.
- [ ] When two events are appended and then reloaded, then the aggregate state is identical and no payload leaks to logs.

---

### 6.120.0 devtools/scaffolding (idempotent generators) (MUST) [TOOLING]

---
type: package
phase: 6+
epic_id: "6.120.0"
owner_path: "framework/packages/devtools/scaffolding/"

package_id: "devtools/scaffolding"
composer: "coretsia/devtools-scaffolding"
kind: runtime
module_id: "devtools.scaffolding"

goal: "`scaffold:*` команди генерують каркаси детерміновано і повторний запуск не дає diff."
provides:
- "Idempotent generators (rerun-no-diff) для типових артефактів: package/module/controller/migration"
- "Tooling-grade safety: protected blocks + backups + deterministic output (без random/wall-clock за замовчуванням)"
- "Reuse `devtools/internal-toolkit` (slug/path/json) без дублювання логіки"

tags_introduced: []
config_roots_introduced: ["scaffolding"]
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - (package deps) `devtools/internal-toolkit`, `core/contracts`, `core/foundation` — базові утиліти + контракти/DI
  - (runtime expectation) `platform/cli` — присутній для запуску `scaffold:*` (reverse dep; discovery via tag)

- Required deliverables (exact paths):
  - N/A (усе нове створюється цим епіком)

- Required config roots/keys:
  - `scaffolding.*` — root буде створений цим епіком (використання після наявності)

- Required tags:
  - `cli.command` — discovery команд (reverse dep; tag already exists)

- Required contracts / ports:
  - (optional) `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - (optional) `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `devtools/internal-toolkit`
- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/cli`
- `platform/http`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)
- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - `scaffold:new-package` → `framework/packages/devtools/scaffolding/src/Console/NewPackageCommand.php`
  - `scaffold:module` → `framework/packages/devtools/scaffolding/src/Console/NewModuleCommand.php`
  - `scaffold:controller` → `framework/packages/devtools/scaffolding/src/Console/NewControllerCommand.php`
  - `scaffold:migration` → `framework/packages/devtools/scaffolding/src/Console/NewMigrationCommand.php`

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/devtools/scaffolding/src/Module/ScaffoldingModule.php` — runtime module entry
- [ ] `framework/packages/devtools/scaffolding/src/Provider/ScaffoldingServiceProvider.php` — DI wiring
- [ ] `framework/packages/devtools/scaffolding/src/Provider/ScaffoldingServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/devtools/scaffolding/config/scaffolding.php` — config subtree (no repeated root)
- [ ] `framework/packages/devtools/scaffolding/config/rules.php` — shape rules
- [ ] `framework/packages/devtools/scaffolding/README.md` — must include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/devtools/scaffolding/src/Console/NewPackageCommand.php` — generates `framework/packages/<layer>/<slug>/...`
- [ ] `framework/packages/devtools/scaffolding/src/Console/NewModuleCommand.php` — generates module/provider/config skeleton
- [ ] `framework/packages/devtools/scaffolding/src/Console/NewControllerCommand.php` — generates http-app controller + route provider (optional)
- [ ] `framework/packages/devtools/scaffolding/src/Console/NewMigrationCommand.php` — generates migration skeleton (deterministic timestamp policy)
- [ ] `framework/packages/devtools/scaffolding/src/Generator/TemplateEngine.php` — deterministic templating (no random)
- [ ] `framework/packages/devtools/scaffolding/src/Generator/ManagedBlockWriter.php` — protected blocks + backups
- [ ] `framework/packages/devtools/scaffolding/src/Generator/IdempotencyGuard.php` — rerun-no-diff validation
- [ ] `framework/packages/devtools/scaffolding/src/Exception/ScaffoldingException.php` — deterministic error codes
- [ ] `docs/guides/scaffolding.md` — canonical generator usage + idempotency rules

Tests:
- [ ] `framework/packages/devtools/scaffolding/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract (noop-safe)
- [ ] `framework/packages/devtools/scaffolding/tests/Unit/TemplateEngineDeterministicTest.php` — unit proof deterministic templating
- [ ] `framework/packages/devtools/scaffolding/tests/Integration/RerunNoDiffNewPackageTest.php` — integration rerun-no-diff
- [ ] `framework/packages/devtools/scaffolding/tests/Integration/ManagedBlockWriterPreservesUserBlocksTest.php` — integration preserves user blocks

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/devtools/scaffolding/composer.json`
- [ ] `framework/packages/devtools/scaffolding/src/Module/ScaffoldingModule.php`
- [ ] `framework/packages/devtools/scaffolding/src/Provider/ScaffoldingServiceProvider.php`
- [ ] `framework/packages/devtools/scaffolding/config/scaffolding.php`
- [ ] `framework/packages/devtools/scaffolding/config/rules.php`
- [ ] `framework/packages/devtools/scaffolding/README.md`
- [ ] `framework/packages/devtools/scaffolding/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/devtools/scaffolding/config/scaffolding.php`
- [ ] Keys (dot):
  - [ ] `scaffolding.enabled` = true
  - [ ] `scaffolding.backups.enabled` = true
  - [ ] `scaffolding.backups.path` = 'framework/var/backups/scaffolding'  *(directory is allowed; backup filenames MUST be deterministic: .bak, .bak.1, ...)*
  - [ ] `scaffolding.managed_block.marker` = 'coretsia_managed'
  - [ ] `scaffolding.time.policy` = 'deterministic'  *(MUST NOT read wall-clock unless user explicitly provides an id/token as input)*
- [ ] Rules:
  - [ ] `framework/packages/devtools/scaffolding/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (uses existing `cli.command`; provides constants only)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Scaffolding\Console\NewPackageCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"scaffold:new-package"}`
  - [ ] registers: `\Coretsia\Scaffolding\Console\NewModuleCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"scaffold:module"}`
  - [ ] registers: `\Coretsia\Scaffolding\Console\NewControllerCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"scaffold:controller"}`
  - [ ] registers: `\Coretsia\Scaffolding\Console\NewMigrationCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"scaffold:migration"}`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `framework/packages/<layer>/<slug>/...` (generated package skeleton; deterministic bytes)
  - [ ] `framework/var/backups/scaffolding/<ts_or_counter>/...` (if backups enabled; deterministic naming policy)
- [ ] Reads:
  - [ ] validates that rerun produces no diff for generated outputs

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (optional)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] generator caches (if any) implement `ResetInterface`
  - [ ] tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `scaffolding.generate` (attrs: generator name, outcome; no file contents)
- [ ] Metrics:
  - [ ] `scaffolding.generate_total` (labels: `outcome`)
  - [ ] `scaffolding.generate_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] safe summary (created/updated file counts), no contents

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/devtools/scaffolding/src/Exception/ScaffoldingException.php` — errorCode `CORETSIA_SCAFFOLDING_TEMPLATE_MISSING`
  - [ ] `framework/packages/devtools/scaffolding/src/Exception/ScaffoldingException.php` — errorCode `CORETSIA_SCAFFOLDING_IDEMPOTENCY_VIOLATION`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] secrets from env/config in console output
- [ ] Allowed:
  - [ ] file paths (repo-relative) only; no content dumps unless explicitly requested and redacted

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` (N/A: no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` (optional: only if caches appear)
- [ ] If metrics/spans/logs exist → evidence:
  - [ ] `framework/packages/devtools/scaffolding/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → evidence:
  - [ ] `framework/packages/devtools/scaffolding/tests/Integration/ManagedBlockWriterPreservesUserBlocksTest.php` (asserts: user-owned blocks not overwritten)
  - [ ] `framework/packages/devtools/scaffolding/tests/Integration/RerunNoDiffNewPackageTest.php` (asserts: rerun-no-diff)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] N/A (integration tests operate on filesystem outputs)
- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger (optional; only if observability assertions are added)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/devtools/scaffolding/tests/Unit/TemplateEngineDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/devtools/scaffolding/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/devtools/scaffolding/tests/Integration/RerunNoDiffNewPackageTest.php`
  - [ ] `framework/packages/devtools/scaffolding/tests/Integration/ManagedBlockWriterPreservesUserBlocksTest.php`
- Gates/Arch:
  - [ ] `framework/tools/gates/internal_toolkit_no_dup_gate.php` already enforces no duplicates (reuse)
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Generators are idempotent (rerun no diff)
- [ ] Uses internal-toolkit for slug/path/json
- [ ] Tests green + docs complete
- [ ] Cutline impact: none
- [ ] Out of scope / non-goals remain true:
  - [ ] Не робимо runtime feature пакет (це tooling)
  - [ ] Не генеруємо “бізнес-логіку”; лише каркаси і шаблони
  - [ ] Не залежимо від `platform/cli` (команди discovered reverse via tag)
- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Gates/Arch green
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: rerun-no-diff (outputs generated)
- [ ] Docs updated:
  - [ ] `framework/packages/devtools/scaffolding/README.md`
  - [ ] `docs/guides/scaffolding.md`
- [ ] What problem this epic solves
  - [ ] Дає генератори для типових артефактів (new package/module/controller/migration) з rerun-no-diff (idempotent)
  - [ ] Гарантує deterministic output + protected blocks + backups при зміні файлів (tooling-grade safety)
  - [ ] Reuse `devtools/internal-toolkit` для slug/path/json (no duplicate logic)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/cli` is present to run `scaffold:*` commands (reverse dep)
- [ ] Discovery / wiring is via tags:
  - [ ] `cli.command`
- [ ] `scaffold:*` команди генерують каркаси детерміновано і повторний запуск не дає diff.
- [ ] When `scaffold:new-package` is run twice with same args, then generated files are byte-identical and no user-owned blocks are overwritten.

---

### 6.130.0 devtools/api-docs (OpenAPI UI glue + /docs reserved) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.130.0"
owner_path: "framework/packages/devtools/api-docs/"

package_id: "devtools/api-docs"
composer: "coretsia/devtools-api-docs"
kind: runtime
module_id: "devtools.api-docs"

goal: "Коли `devtools.api-docs` увімкнено у local/dev, `/docs` віддає UI детерміновано і guarded, а в prod — вимкнено за замовчуванням."
provides:
- "Dev-only UI/endpoint для перегляду OpenAPI (static spec + UI glue)"
- "Ownership для `/docs` через reserved path policy (`SystemRouteTable`)"
- "Deterministic route registration через `RouteProviderInterface` (contracts) + guard policy"

tags_introduced: []
config_roots_introduced: ["api_docs"]
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - (package deps) `core/contracts`, `core/foundation` — routing contracts + DI
  - (runtime expectation) `platform/http + platform/routing + platform/http-app` — щоб реально serve-ити `/docs` (policy; не compile-time)
  - (runtime expectation) `platform/errors + platform/problem-details` — canonical error flow for guard (policy)

- Required deliverables (exact paths):
  - `framework/packages/platform/http/src/System/SystemRouteTable.php` — існує і дозволяє додати reserved `/docs`
  - `framework/tools/gates/system_route_table_gate.php` — існує і може бути оновлений для нового reserved entry
  - `framework/packages/platform/http/tests/Unit/ReservedPathMatcherSegmentPrefixTest.php` — існує (може бути розширений) якщо потрібно

- Required config roots/keys:
  - `api_docs.*` — root буде створений цим епіком (використання після наявності)

- Required tags:
  - `routing.route_provider` — discovery route providers
  - (optional) `error.mapper` — mapping disabled/forbidden to ErrorDescriptor

- Required contracts / ports:
  - `Coretsia\Contracts\Routing\RouteProviderInterface`
  - `Coretsia\Contracts\Routing\RouteDefinition`
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` — resolve `api_docs.guard.token_ref` (never leak value)
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http` (direct compile-time dep not required)
- `platform/cli`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Routing\RouteProviderInterface`
  - `Coretsia\Contracts\Routing\RouteDefinition`

### Entry points / integration points (MUST)

- HTTP:
  - routes: `GET /docs` → `framework/packages/devtools/api-docs/src/Http/Controller/DocsController.php`
- Kernel hooks/tags:
  - `routing.route_provider` priority `0` meta `{"owner":"devtools/api-docs"}`
  - (optional) `error.mapper` priority `100` meta `{"handles":"ApiDocsDisabledException"}`

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/devtools/api-docs/src/Module/ApiDocsModule.php` — runtime module entry
- [ ] `framework/packages/devtools/api-docs/src/Provider/ApiDocsServiceProvider.php` — DI wiring
- [ ] `framework/packages/devtools/api-docs/src/Provider/ApiDocsServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/devtools/api-docs/config/api_docs.php` — config subtree (no repeated root)
- [ ] `framework/packages/devtools/api-docs/config/rules.php` — shape rules
- [ ] `framework/packages/devtools/api-docs/README.md` — must include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/devtools/api-docs/src/Routing/ApiDocsRouteProvider.php` — provides `/docs` route (contracts VO)
- [ ] `framework/packages/devtools/api-docs/src/Http/Controller/DocsController.php` — dev-only controller
- [ ] `framework/packages/devtools/api-docs/src/Http/Guard/DocsGuard.php` — local/token allowlist guard (deterministic)
- [ ] `framework/packages/devtools/api-docs/src/Exception/ApiDocsDisabledException.php` — deterministic error code
- [ ] `framework/packages/devtools/api-docs/src/Exception/ApiDocsProblemMapper.php` — maps disabled/forbidden (404/403 policy)
- [ ] `framework/packages/devtools/api-docs/resources/openapi/openapi.json` — reference spec (static; deterministic)
- [ ] `framework/packages/devtools/api-docs/resources/ui/index.html` — reference UI (static)
- [ ] `docs/architecture/api-docs.md` — enablement + guard + reserved endpoint policy

Tests:
- [ ] `framework/packages/devtools/api-docs/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract (noop-safe)
- [ ] `framework/packages/devtools/api-docs/tests/Unit/DocsGuardDeterministicTest.php` — unit deterministic guard
- [ ] `framework/packages/devtools/api-docs/tests/Integration/DocsRouteIsRegisteredDeterministicallyTest.php` — integration route registration
- [ ] `framework/packages/devtools/api-docs/tests/Integration/DocsDisabledInProductionByDefaultTest.php` — integration prod default off
- [ ] `framework/tools/tests/Integration/Http/DocsEndpointAvailableOnlyInLocalTest.php` — system-level policy

#### Modifies

- [ ] `framework/packages/platform/http/src/System/SystemRouteTable.php` — add `/docs` reserved owner entry (schema bump policy)
- [ ] `framework/tools/gates/system_route_table_gate.php` — update fixtures/expectations for new reserved endpoint
- [ ] `framework/packages/platform/http/tests/Unit/ReservedPathMatcherSegmentPrefixTest.php` — extend cases for `/docs` if needed
- [ ] `framework/packages/platform/http/tests/Contract/SystemRouteTableSchemaContractTest.php` (or existing gate coverage) — ensure SCHEMA_VERSION bump handled

#### Package skeleton (if type=package)

- [ ] `framework/packages/devtools/api-docs/composer.json`
- [ ] `framework/packages/devtools/api-docs/src/Module/ApiDocsModule.php`
- [ ] `framework/packages/devtools/api-docs/src/Provider/ApiDocsServiceProvider.php`
- [ ] `framework/packages/devtools/api-docs/config/api_docs.php`
- [ ] `framework/packages/devtools/api-docs/config/rules.php`
- [ ] `framework/packages/devtools/api-docs/README.md`
- [ ] `framework/packages/devtools/api-docs/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/devtools/api-docs/config/api_docs.php`
- [ ] Keys (dot):
  - [ ] `api_docs.enabled` = false
  - [ ] `api_docs.http.path` = '/docs'
  - [ ] `api_docs.guard.enabled` = true
  - [ ] `api_docs.guard.allowlist` = ['127.0.0.1','::1']
  - [ ] `api_docs.guard.token_ref` = null
  - [ ] `api_docs.expose_in_production` = false
- [ ] Rules:
  - [ ] `framework/packages/devtools/api-docs/config/rules.php` enforces shape

- **No env probing (single-choice):** enablement/production exposure рішення **MUST** бути pure-config:
  - `api_docs.enabled` + `api_docs.expose_in_production` + guard config.
  - Вибір “local/dev/prod” робиться preset-ами/модулями конфігів (Kernel ModulePlan/ConfigKernel), а не через пряме читання `getenv()` у runtime.

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (uses existing tags `ROUTING_ROUTE_PROVIDER = 'routing.route_provider'`, `ERROR_MAPPER = 'error.mapper'`; provides constants only)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\ApiDocs\Routing\ApiDocsRouteProvider::class`
  - [ ] adds tag: `routing.route_provider` priority `0` meta `{"owner":"devtools/api-docs"}`
  - [ ] registers: `\Coretsia\ApiDocs\Http\Controller\DocsController::class`
  - [ ] registers: `\Coretsia\ApiDocs\Exception\ApiDocsProblemMapper::class`
  - [ ] adds tag: `error.mapper` priority `100` meta `{"handles":"ApiDocsDisabledException"}`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::CLIENT_IP` (safe usage for guard; never logged raw)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] guard caches (if any) implement `ResetInterface`
  - [ ] tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `api_docs.render` (attrs: outcome)
- [ ] Metrics:
  - [ ] `api_docs.render_total` (labels: `outcome`)
  - [ ] `api_docs.render_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] denied → info/warn (no token dump; no headers/cookies dump)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/devtools/api-docs/src/Exception/ApiDocsDisabledException.php` — errorCode `CORETSIA_API_DOCS_DISABLED`
- [ ] Mapping:
  - [ ] new mapper via tag `error.mapper` (ApiDocsProblemMapper)
- [ ] Policy note (documented in README):
  - [ ] default: 404 in production when disabled; 403 when guard fails

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] token values, request headers/cookies, any config dumps
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` (N/A: no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` (optional: only if caches appear)
- [ ] If metrics/spans/logs exist → evidence:
  - [ ] `framework/packages/devtools/api-docs/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → evidence:
  - [ ] `framework/packages/devtools/api-docs/tests/Integration/DocsDisabledInProductionByDefaultTest.php` (asserts: prod default off)
  - [ ] `framework/packages/devtools/api-docs/tests/Unit/DocsGuardDeterministicTest.php` (asserts: deterministic guard decisions)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] (if needed) `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php`
- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger (capture events for assertions)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/devtools/api-docs/tests/Unit/DocsGuardDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/devtools/api-docs/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/devtools/api-docs/tests/Integration/DocsRouteIsRegisteredDeterministicallyTest.php`
  - [ ] `framework/packages/devtools/api-docs/tests/Integration/DocsDisabledInProductionByDefaultTest.php`
- Gates/Arch:
  - [ ] `framework/tools/gates/system_route_table_gate.php` updated for `/docs` entry

### DoD (MUST)

- [ ] `/docs` is reserved to `devtools/api-docs` in SystemRouteTable (schema bump + gate updated)
- [ ] Endpoint guarded and disabled by default in production
- [ ] Deterministic route registration + tests + docs complete
- [ ] Cutline impact: none
- [ ] Out of scope / non-goals remain true:
  - [ ] Не робимо “генерацію OpenAPI з коду” (лише UI/serve static spec як reference)
  - [ ] Не витягаємо конфіги/секрети у UI
  - [ ] Не додаємо залежність `platform/http` (використання тільки через runtime expectation)
- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Tests green
- [ ] Gates/Arch green
- [ ] Docs complete
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Docs updated:
  - [ ] `framework/packages/devtools/api-docs/README.md`
  - [ ] `docs/architecture/api-docs.md`
- [ ] What problem this epic solves
  - [ ] Дає dev-only UI/endpoint для перегляду OpenAPI (без підключення в prod за замовчуванням)
  - [ ] Гарантує ownership для `/docs` через `SystemRouteTable` (щоб інші пакети не могли забрати шлях)
  - [ ] Інтеграція з routing compile через `RouteProviderInterface` (contracts) + deterministic route registration
- [ ] Usually present when enabled in presets/bundles:
  - [ ] served when `platform/http + platform/routing + platform/http-app` are enabled
  - [ ] error flow via `platform/errors + platform/problem-details` (for guard failures)
- [ ] Discovery / wiring is via tags:
  - [ ] `routing.route_provider`
  - [ ] (optional) `error.mapper`
- [ ] Коли `devtools.api-docs` увімкнено у local/dev, `/docs` віддає UI детерміновано і guarded, а в prod — вимкнено за замовчуванням.
- [ ] When `APP_ENV=production`, then `/docs` is not available unless explicitly enabled by config guard.

---

### 6.140.0 devtools/dev-tools (debugbar middleware, dev-only) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.140.0"
owner_path: "framework/packages/devtools/dev-tools/"

package_id: "devtools/dev-tools"
composer: "coretsia/devtools-dev-tools"
kind: runtime
module_id: "devtools.dev-tools"

goal: "Коли `devtools.dev-tools` увімкнено в local, debugbar middleware додає dev diagnostics без витоку секретів і не впливає на production."
provides:
- "Dev-only debugbar middleware у slot `http.middleware.system_post` (SSoT priority 500)"
- "Strict guard (local/token) + safe panels (без секретів/PII/headers/payload/raw SQL)"
- "Optional інтеграція з profiling + meter/tracer ports (noop-safe)"

tags_introduced: []
config_roots_introduced: ["dev_tools"]
artifacts_introduced: []

adr: none
ssot_refs: MUST include policy docs that this epic реально використовує:
- "docs/ssot/config-roots.md"
- "docs/ssot/http-middleware-catalog.md"
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/metrics-policy.md"
- "docs/ssot/redaction.md"
- "docs/ssot/tags.md" # якщо middleware slot taxonomy документується там)
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - (package deps) `core/contracts`, `core/foundation`, `platform/observability` — allowed exception: devtools may depend on observability glue
  - (runtime expectation) `platform/http` — executes `http.middleware.system_post`
  - (runtime expectation) `platform/logging|tracing|metrics` — provide implementations/noop-safe

- Required deliverables (exact paths):
  - N/A (усе нове створюється цим епіком)

- Required config roots/keys:
  - `dev_tools.*` — root буде створений цим епіком (використання після наявності)

- Required tags:
  - `http.middleware.system_post` — middleware wiring slot/tag
  - `kernel.reset` — якщо з’являється mutable collector state

- Required contracts / ports:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface` (optional)

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
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface` (optional)

### Entry points / integration points (MUST)

- HTTP:
  - middleware slots/tags: `http.middleware.system_post` priority `500` meta `{"dev_only":true,"reason":"debugbar middleware"}`
  - handler: `framework/packages/devtools/dev-tools/src/Http/Middleware/DebugbarMiddleware.php`

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/devtools/dev-tools/src/Module/DevToolsModule.php` — runtime module entry
- [ ] `framework/packages/devtools/dev-tools/src/Provider/DevToolsServiceProvider.php` — DI wiring
- [ ] `framework/packages/devtools/dev-tools/src/Provider/DevToolsServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/devtools/dev-tools/config/dev_tools.php` — config subtree (no repeated root)
- [ ] `framework/packages/devtools/dev-tools/config/rules.php` — shape rules
- [ ] `framework/packages/devtools/dev-tools/README.md` — must include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/devtools/dev-tools/src/Http/Middleware/DebugbarMiddleware.php` — dev-only middleware (no secrets)
- [ ] `framework/packages/devtools/dev-tools/src/Http/Guard/DevToolsGuard.php` — allowlist/token guard (deterministic)
- [ ] `framework/packages/devtools/dev-tools/src/Debug/PanelCollector.php` — collects safe timings/ids (no payload)
- [ ] `framework/packages/devtools/dev-tools/src/Security/Redaction.php` — hash/len helpers
- [ ] `framework/packages/devtools/dev-tools/src/Exception/DevToolsException.php` — errorCode `CORETSIA_DEVTOOLS_DEBUGBAR_FAILED` (optional; prefer no-throw)
- [ ] `docs/architecture/devtools-debugbar.md` — enablement + ordering + override/disable notes

Tests:
- [ ] `framework/packages/devtools/dev-tools/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract (noop-safe)
- [ ] `framework/packages/devtools/dev-tools/tests/Unit/GuardDeterministicTest.php` — unit deterministic guard
- [ ] `framework/packages/devtools/dev-tools/tests/Integration/DebugbarMiddlewareOnlyWhenEnabledAndGuardPassesTest.php` — integration enablement/guard
- [ ] `framework/packages/devtools/dev-tools/tests/Integration/DebugbarDoesNotLeakSecretsTest.php` — integration redaction/no leak
- [ ] `framework/tools/tests/Integration/Http/DebugbarVisibleOnlyInLocalTest.php` — (optional) system-level

#### Modifies

- [ ] `docs/ssot/config-roots.md` — register root `dev_tools` (owner `devtools/dev-tools`)
- [ ] `docs/ssot/http-middleware-catalog.md` — add entry:
  - slot: `http.middleware.system_post`
  - priority: `500`
  - owner: `devtools/dev-tools`
  - service: `Coretsia\DevTools\Http\Middleware\DebugbarMiddleware`
  - note: `dev_only=true` + guard required

#### Package skeleton (if type=package)

- [ ] `framework/packages/devtools/dev-tools/composer.json`
- [ ] `framework/packages/devtools/dev-tools/src/Module/DevToolsModule.php`
- [ ] `framework/packages/devtools/dev-tools/src/Provider/DevToolsServiceProvider.php`
- [ ] `framework/packages/devtools/dev-tools/config/dev_tools.php`
- [ ] `framework/packages/devtools/dev-tools/config/rules.php`
- [ ] `framework/packages/devtools/dev-tools/README.md`
- [ ] `framework/packages/devtools/dev-tools/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/devtools/dev-tools/config/dev_tools.php`
- [ ] Keys (dot):
  - [ ] `dev_tools.enabled` = false
  - [ ] `dev_tools.http.debugbar.enabled` = false
  - [ ] `dev_tools.http.debugbar.guard.allowlist` = ['127.0.0.1','::1']
  - [ ] `dev_tools.http.debugbar.guard.token_ref` = null
  - [ ] `dev_tools.expose_in_production` = false
- [ ] Rules:
  - [ ] `framework/packages/devtools/dev-tools/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (uses existing `http.middleware.system_post`; provides constants only)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\DevTools\Http\Middleware\DebugbarMiddleware::class`
  - [ ] adds tag: `http.middleware.system_post` priority `500` meta `{"dev_only":true,"reason":"debugbar middleware"}`
  - [ ] ensures middleware is no-op when disabled/guard fails

- ServiceProvider wiring evidence MUST state:
  - adds tag: `http.middleware.system_post` priority `500` meta `{"dev_only":true,"reason":"debugbar middleware"}`
  - middleware MUST be strict no-op when:
    - `dev_tools.enabled=false` OR `dev_tools.http.debugbar.enabled=false`
    - guard fails (ip not allowlisted AND token_ref mismatch)
    - `dev_tools.expose_in_production=false` and preset treats runtime as “prod-like” (визначається конфігом/пакетом пресетів, не getenv)

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::REQUEST_ID` (if enabled in http)
  - [ ] `ContextKeys::UOW_ID`
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] collectors with mutable state implement `ResetInterface`
  - [ ] tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `devtools.debugbar` (attrs: outcome)
- [ ] Metrics:
  - [ ] `devtools.debugbar_total` (labels: `outcome`)
  - [ ] `devtools.debugbar_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] denied/disabled → debug/info (no token, no headers dump)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/devtools/dev-tools/src/Exception/DevToolsException.php` — errorCode `CORETSIA_DEVTOOLS_DEBUGBAR_FAILED` (optional; prefer no-throw)
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) (prefer no-op)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/raw SQL/payload
- [ ] Allowed:
  - [ ] safe ids only + `hash(value)` / `len(value)`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` (N/A: no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` (optional: only if mutable state exists)
- [ ] If metrics/spans/logs exist → evidence:
  - [ ] `framework/packages/devtools/dev-tools/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → evidence:
  - [ ] `framework/packages/devtools/dev-tools/tests/Integration/DebugbarDoesNotLeakSecretsTest.php`

- Add (contract-level) because spans/metrics/logs are described:
  - [ ] `framework/packages/devtools/dev-tools/tests/Contract/ObservabilityPolicyTest.php`
    - asserts: metric names + label allowlist + no payload/headers/cookies/tokens in emitted telemetry
- Keep existing integration redaction test as proof:
  - `DebugbarDoesNotLeakSecretsTest.php` remains REQUIRED

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] N/A (middleware integration tests cover wiring behavior)
- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger (capture events for assertions)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/devtools/dev-tools/tests/Unit/GuardDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/devtools/dev-tools/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/devtools/dev-tools/tests/Integration/DebugbarMiddlewareOnlyWhenEnabledAndGuardPassesTest.php`
  - [ ] `framework/packages/devtools/dev-tools/tests/Integration/DebugbarDoesNotLeakSecretsTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Middleware is wired to `http.middleware.system_post` priority 500 and is dev-only
- [ ] No secrets/PII leakage in headers/body/logs
- [ ] Tests green + docs complete
- [ ] Cutline impact: none
- [ ] Out of scope / non-goals remain true:
  - [ ] Не додаємо debugbar у production за замовчуванням
  - [ ] Не логимо/не показуємо Authorization/Cookie/session id/payload/raw SQL
  - [ ] Не залежимо від `platform/http` (тільки DI tag wiring)
- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Gates/Arch green
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Docs updated:
  - [ ] `framework/packages/devtools/dev-tools/README.md`
  - [ ] `docs/architecture/devtools-debugbar.md`
- [ ] What problem this epic solves
  - [ ] Додає dev-only debugbar middleware у `http.middleware.system_post` (SSoT priority 500) без зміни `platform/http`
  - [ ] (Optional) інтегрується з profiling ports (якщо є binding) і з meter/tracer для safe timings
  - [ ] Дає строгий guard (local/token) і гарантує, що не віддаються секрети/PII
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/http` executes `http.middleware.system_post`
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] Discovery / wiring is via tags:
  - [ ] `http.middleware.system_post`
- [ ] Коли `devtools.dev-tools` увімкнено в local, debugbar middleware додає dev diagnostics без витоку секретів і не впливає на production.
- [ ] When request is made from non-allowed IP/token, then middleware is a no-op and does not add headers/body or leak data.

---

### 6.150.0 devtools/admin-panel (product, /_admin reserved) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.150.0"
owner_path: "framework/packages/devtools/admin-panel/"

package_id: "devtools/admin-panel"
composer: "coretsia/devtools-admin-panel"
kind: runtime
module_id: "devtools.admin-panel"

goal: "Admin panel доступний тільки коли модуль увімкнено і guard проходить, а `/_admin/*` зарезервований та захищений auth+csrf."
provides:
- "Optional admin panel (devtools product) з UI на `platform/view` і guarded доступом"
- "Reserved `/_admin` prefix у `SystemRouteTable` (ownership policy)"
- "Runtime prerequisites: session+auth+csrf як обов’язкові (security не розмазується по контролерах)"

tags_introduced: []
config_roots_introduced: ["admin_panel"]
artifacts_introduced: []

adr: none
ssot_refs: add minimum policy docs this epic touches:
- "docs/ssot/config-roots.md"
- "docs/ssot/tags.md" # uses routing.route_provider
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/redaction.md"
- "docs/ssot/metrics-policy.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - (package deps) `core/contracts`, `core/foundation`, `platform/view`
  - (runtime expectation) `platform/http + platform/routing + platform/http-app` — serve routes (policy; не compile-time)
  - (runtime expectation) `platform/session + platform/auth + platform/security` — CSRF + access control (mandatory)
  - (optional runtime) `platform/logging|tracing|metrics` — noop-safe implementations (policy)
  - (optional runtime) `platform/http` rate-limit enabled for hardening (policy; not dep)

- Required deliverables (exact paths):
  - `framework/packages/platform/http/src/System/SystemRouteTable.php` — існує і дозволяє додати reserved `/_admin/`
  - `framework/tools/gates/system_route_table_gate.php` — існує і може бути оновлений
  - `framework/packages/platform/http/tests/Unit/ReservedPathMatcherSegmentPrefixTest.php` — існує (може бути розширений)

- Required config roots/keys:
  - `admin_panel.*` — root буде створений цим епіком (використання після наявності)

- Required tags:
  - `routing.route_provider` — discovery admin routes
  - `http.middleware.app_pre` — canonical stack already contains Session/Auth/Csrf by SSoT (runtime expectation)

- Required contracts / ports:
  - `Coretsia\Contracts\Routing\RouteProviderInterface`
  - `Coretsia\Contracts\Routing\RouteDefinition`
  - `Coretsia\Contracts\Session\SessionManagerInterface`
  - `Coretsia\Contracts\Security\CsrfTokenManagerInterface`
  - `Coretsia\Contracts\Auth\AuthorizationInterface`
  - `Coretsia\Contracts\Auth\AuthenticatorInterface` (optional usage)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `platform/view`

Forbidden:

- `platform/http`
- `platform/cli`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Session\SessionManagerInterface`
  - `Coretsia\Contracts\Security\CsrfTokenManagerInterface`
  - `Coretsia\Contracts\Auth\AuthorizationInterface`
  - `Coretsia\Contracts\Auth\AuthenticatorInterface` (optional usage)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Routing\RouteProviderInterface`
  - `Coretsia\Contracts\Routing\RouteDefinition`

### Entry points / integration points (MUST)

- HTTP:
  - routes: `/_admin/*` → via `framework/packages/devtools/admin-panel/src/Routing/AdminPanelRouteProvider.php`
- Kernel hooks/tags:
  - `routing.route_provider` priority `0` meta `{"owner":"devtools/admin-panel","prefix":"/_admin"}`
- `routing.route_provider` meta MUST be closed allowlist:
  - `owner` (string, required) == "devtools/admin-panel"
  - `prefix` (string, required) == "/_admin"
  - optional: `id` (stable string)

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/devtools/admin-panel/src/Module/AdminPanelModule.php` — runtime module entry
- [ ] `framework/packages/devtools/admin-panel/src/Provider/AdminPanelServiceProvider.php` — DI wiring
- [ ] `framework/packages/devtools/admin-panel/src/Provider/AdminPanelServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/devtools/admin-panel/config/admin_panel.php` — config subtree (no repeated root)
- [ ] `framework/packages/devtools/admin-panel/config/rules.php` — shape rules
- [ ] `framework/packages/devtools/admin-panel/README.md` — must include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/devtools/admin-panel/src/Routing/AdminPanelRouteProvider.php` — provides `/_admin/*` routes (contracts)
- [ ] `framework/packages/devtools/admin-panel/src/Http/Controller/LoginController.php` — GET/POST login (CSRF enforced)
- [ ] `framework/packages/devtools/admin-panel/src/Http/Controller/LogoutController.php` — POST logout (CSRF enforced)
- [ ] `framework/packages/devtools/admin-panel/src/Http/Controller/DashboardController.php` — main dashboard (auth required)
- [ ] `framework/packages/devtools/admin-panel/src/Http/Controller/UsersController.php` — sample CRUD (ability required)
- [ ] `framework/packages/devtools/admin-panel/src/Http/Guard/AdminPanelGuard.php` — enablement + prod exposure guard
  - **No env probing (single-choice):** доступність panel визначається тільки конфігом:
    - `admin_panel.enabled`, `admin_panel.expose_in_production`, `admin_panel.guard.*`.
    - “prod vs local” задається preset-ами/конфігом, а не прямим `getenv()` у runtime.

- [ ] `framework/packages/devtools/admin-panel/resources/views/admin/*.php` — templates
- [ ] `framework/packages/devtools/admin-panel/src/Security/Redaction.php` — hash/len helpers
- [ ] `framework/packages/devtools/admin-panel/src/Exception/AdminPanelDisabledException.php` — errorCode `CORETSIA_ADMIN_PANEL_DISABLED` (optional)
- [ ] `docs/architecture/admin-panel.md` — routing, auth/ability requirements, CSRF, redaction

Tests:
- [ ] `framework/packages/devtools/admin-panel/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract (noop-safe)
- [ ] `framework/packages/devtools/admin-panel/tests/Unit/GuardPolicyDeterministicTest.php` — unit deterministic guard policy
- [ ] `framework/packages/devtools/admin-panel/tests/Integration/RoutesRegisteredDeterministicallyUnderAdminPrefixTest.php` — integration routes deterministic
- [ ] `framework/packages/devtools/admin-panel/tests/Integration/UnauthorizedIsBlockedTest.php` — integration auth denial via canonical flow
- [ ] `framework/packages/devtools/admin-panel/tests/Integration/CsrfEnforcedOnMutationsTest.php` — integration CSRF enforced
- [ ] `framework/tools/tests/Integration/Http/AdminPanelAccessibleOnlyWhenEnabledTest.php` — system-level enablement
- [ ] (optional fixture wiring) `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` — if needed

#### Modifies

- [ ] `framework/packages/platform/http/src/System/SystemRouteTable.php` — add `/_admin/` prefix reserved owner entry (schema bump policy)
- [ ] `framework/tools/gates/system_route_table_gate.php` — update fixtures/expectations for `/_admin/` reserved prefix
- [ ] `framework/packages/platform/http/tests/Unit/ReservedPathMatcherSegmentPrefixTest.php` — extend cases for `/_admin/` prefix
- [ ] `docs/ssot/config-roots.md` — register root `admin_panel` (owner `devtools/admin-panel`)
- Keep existing Modifies (SystemRouteTable + gate + tests) as-is.

#### Package skeleton (if type=package)

- [ ] `framework/packages/devtools/admin-panel/composer.json`
- [ ] `framework/packages/devtools/admin-panel/src/Module/AdminPanelModule.php`
- [ ] `framework/packages/devtools/admin-panel/src/Provider/AdminPanelServiceProvider.php`
- [ ] `framework/packages/devtools/admin-panel/config/admin_panel.php`
- [ ] `framework/packages/devtools/admin-panel/config/rules.php`
- [ ] `framework/packages/devtools/admin-panel/README.md`
- [ ] `framework/packages/devtools/admin-panel/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/devtools/admin-panel/config/admin_panel.php`
- [ ] Keys (dot):
  - [ ] `admin_panel.enabled` = false
  - [ ] `admin_panel.http.prefix` = '/_admin'
  - [ ] `admin_panel.guard.allowlist` = ['127.0.0.1','::1']
  - [ ] `admin_panel.guard.token_ref` = null
  - [ ] `admin_panel.expose_in_production` = false
  - [ ] `admin_panel.auth.required_ability` = 'admin.access'
- [ ] Rules:
  - [ ] `framework/packages/devtools/admin-panel/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (uses existing `routing.route_provider`; provides constants only)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\AdminPanel\Routing\AdminPanelRouteProvider::class`
  - [ ] adds tag: `routing.route_provider` priority `0` meta `{"owner":"devtools/admin-panel","prefix":"/_admin"}`
  - [ ] registers controllers as services (Login/Logout/Dashboard/Users)
  - [ ] documents runtime prerequisites: session+auth+security (CSRF) enabled

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::ACTOR_ID` (for auth; safe id only)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] any per-request caches implement `ResetInterface`
  - [ ] tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `admin_panel.request` (attrs: outcome, controller/action id as span attr; no PII)
- [ ] Metrics:
  - [ ] `admin_panel.request_total` (labels: `outcome`)
  - [ ] `admin_panel.request_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] auth/ability failures via canonical flow; no credentials/session ids; ability name only as span attr (NOT label)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/devtools/admin-panel/src/Exception/AdminPanelDisabledException.php` — errorCode `CORETSIA_ADMIN_PANEL_DISABLED` (optional)
- [ ] Mapping:
  - [ ] reuse existing mapper (prefer existing auth/security mappers)
- [ ] Policy note (documented in README):
  - [ ] disabled in prod default: 404

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/CSRF token/raw SQL/payload
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id`)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` (N/A: no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` (optional: only if caches exist)
- [ ] If metrics/spans/logs exist → evidence:
  - [ ] `framework/packages/devtools/admin-panel/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → evidence:
  - [ ] `framework/packages/devtools/admin-panel/tests/Integration/UnauthorizedIsBlockedTest.php` (asserts: no secrets leaked on denial)
  - [ ] `framework/packages/devtools/admin-panel/tests/Integration/CsrfEnforcedOnMutationsTest.php` (asserts: CSRF enforced)

- Ensure at least one test explicitly fails if reserved-prefix enforcement removed:
  - Either extend `system_route_table_gate.php` expectations
  - Or add integration:
    - [ ] `framework/packages/platform/http/tests/Integration/AdminPrefixIsReservedToDevtoolsTest.php`
      (asserts: other providers cannot claim `/_admin`)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/tools/tests/Integration/Http/AdminPanelAccessibleOnlyWhenEnabledTest.php` (system-level wiring)
- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger (capture events for assertions)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/devtools/admin-panel/tests/Unit/GuardPolicyDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/devtools/admin-panel/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/devtools/admin-panel/tests/Integration/RoutesRegisteredDeterministicallyUnderAdminPrefixTest.php`
  - [ ] `framework/packages/devtools/admin-panel/tests/Integration/UnauthorizedIsBlockedTest.php`
  - [ ] `framework/packages/devtools/admin-panel/tests/Integration/CsrfEnforcedOnMutationsTest.php`
- Gates/Arch:
  - [ ] `framework/tools/gates/system_route_table_gate.php` updated for `/_admin/` reserved prefix

### DoD (MUST)

- [ ] `/_admin` prefix reserved to `devtools/admin-panel` in SystemRouteTable (schema bump + gate updated)
- [ ] Disabled by default in production; guarded access in local
- [ ] Uses session+auth+csrf (via existing middleware stack) and does not leak secrets
- [ ] Tests green + docs complete
- [ ] Cutline impact: none
- [ ] Out of scope / non-goals remain true:
  - [ ] Не робимо production-ready “enterprise admin suite” за замовчуванням (devtools product, disabled by default)
  - [ ] Не логимо credentials, session id, CSRF tokens, payloads
  - [ ] Не додаємо залежність `platform/http` (served via runtime expectation)
- [ ] Spec locked (this epic text reviewed + no open questions)
- [ ] Implementation complete
- [ ] Gates/Arch green
- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Docs updated:
  - [ ] `framework/packages/devtools/admin-panel/README.md`
  - [ ] `docs/architecture/admin-panel.md`
- [ ] What problem this epic solves
  - [ ] Дає optional admin panel (devtools product) з UI на `platform/view` і guarded доступом
  - [ ] Резервує `/_admin` prefix у `SystemRouteTable` (щоб інші пакети не могли використовувати цей шлях)
  - [ ] Використовує session+auth+csrf як обов’язкові runtime prerequisites; не виносить security у контролери
- [ ] Usually present when enabled in presets/bundles:
  - [ ] served when `platform/http + platform/routing + platform/http-app` are enabled
  - [ ] requires `platform/session + platform/auth + platform/security` enabled (CSRF + access control)
  - [ ] optional `platform/http` rate-limit enabled for hardening (policy; not a dep)
- [ ] Discovery / wiring is via tags:
  - [ ] `routing.route_provider` (admin routes)
  - [ ] (optional) `http.middleware.app_pre` already contains Session/Auth/Csrf by SSoT table
- [ ] Admin panel доступний тільки коли модуль увімкнено і guard проходить, а `/_admin/*` зарезервований та захищений auth+csrf.
- [ ] When user is not authenticated, then accessing `/_admin` is denied via canonical auth error flow (401/403) and no secrets are leaked.

---

### 6.160.0 Realtime/protocols (reactive/websocket/graphql/grpc) — roadmap index (SHOULD) [DOC]

---
type: docs
phase: 6+
epic_id: "6.160.0"
owner_path: "docs/architecture/protocols.md"  # single canonical path (doc)

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- docs/ssot/tags.md
- docs/ssot/config-roots.md
- docs/ssot/observability-and-errors.md
- docs/ssot/runtime-drivers.md
- docs/ssot/metrics-policy.md
- docs/ssot/redaction.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

N/A

#### Compile-time deps (deptrac-enforceable) (MUST)

N/A

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `docs/architecture/protocols.md` — canonical index: protocol → owner package path → entrypoint → wiring → fixture → reserved endpoints; plus integration rules
  - Must include:
    - Protocol package checklist (MUST for any new protocol module)
      - For each protocol module (`platform/*` or `integrations/*`) the proposal MUST include:
        - **Owner package_id** + **module_id**
        - **Config root**:
          - registered in `docs/ssot/config-roots.md`
          - `config/<root>.php` returns subtree (no wrapper)
        - **Reserved endpoints / routes**:
          - MUST be declared without leaking raw paths/tokens; prefer route templates / safe ids
        - **DI / discovery**:
          - no filesystem scans; composer metadata + TagRegistry only (where applicable)
          - middleware tags MUST be from SSoT taxonomy (`http.middleware.system|app|route` slots only)
          - legacy `http.middleware.user_*` MUST NOT be used
        - **Observability**:
          - metric labels allowlist: `method,status,driver,operation,table,outcome` only
          - payload/body/headers/cookies/auth/session/tokens MUST NOT be logged/emitted
        - **Errors**:
          - MUST map via canonical flow `Throwable -> ErrorDescriptor -> adapters` (HTTP: ProblemDetails)
        - **Fixtures + tests**:
          - at least 1 reference fixture + contract tests proving “noop-safe + no leaks” where applicable
  - In `docs/architecture/protocols.md` MUST contain table columns:
    - `protocol` | `owner package_id` | `module_id` | `config root` | `entrypoint tag` | `reserved endpoints` | `fixture/tests`
  - Must explicitly say:
    - platform packages MUST NOT depend on `integrations/*`
    - runtime/server adapters live in `integrations/runtime-*`

#### Modifies

N/A

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

- [ ] Spans:
  - [ ] documented rule: protocol name / stream id / request id only as span attributes (NOT metric labels)
- [ ] Metrics:
  - [ ] documented rule: only low-cardinality labels `method|status|driver|operation|table|outcome`
- [ ] Logs:
  - [ ] documented rule: no secrets/PII; payload never logged; use `hash/len` only

#### Errors

- Exceptions introduced:
  - none
- [ ] Mapping:
  - [ ] documented rule: protocol adapters should map failures via canonical `ErrorDescriptor → ProblemDetails` in HTTP world

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] documented rule: Authorization/Cookie/session id/tokens/raw payload/raw SQL
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id`)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

N/A (docs-only epic)

#### Test harness / fixtures (when integration is needed)

N/A (docs-only epic)

### Tests (MUST)

- Unit/Contract/Gates/Arch:
  - N/A
- Integration:
  - [ ] `framework/tools/tests/Integration/Docs/ProtocolsIndexExistsTest.php` (optional: ensure file exists in repo template)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: rerun-no-diff (if outputs generated)
- [ ] What problem this epic solves:
  - [ ] Дає один канонічний “індекс протоколів” (SSE/WebSocket/GraphQL/gRPC/…) з ownership, entrypoints і правилами інтеграції
  - [ ] Фіксує інваріанти: не “просочувати” протоколи в kernel або `platform/http`, і як резервувати endpoints через SystemRouteTable
  - [ ] Визначає вимоги до кожного протокол-пакета: 1 owner package + 1 reference fixture + contract tests + docs
- [ ] Non-goals / out of scope:
  - [ ] Не реалізуємо самі протоколи тут (тільки docs/index)
  - [ ] Не змінюємо kernel/contracts (no ADR needed)
  - [ ] Не додаємо нових CI gates (тільки описуємо, де вони повинні бути)
- [ ] Goal / Acceptance scenario:
  - [ ] One sentence definition of success: Є один документ, який відповідає “де живе кожен протокол, як вмикається, який entrypoint, який fixture, які reserved endpoints”.
  - [ ] Acceptance scenario: When a new protocol package is proposed, then it can be added to the table with clear owner path, wiring method, and fixture/test requirements.
- [ ] Runtime expectation:
  - [ ] протоколи вмикаються як окремі модулі (`platform/*` або `integrations/*`) через preset/bundle
  - [ ] discovery/wiring через tags: `http.middleware.*`, `routing.route_provider`, `cli.command` (коли застосовно)

---

### 6.161.0 Contracts: Realtime protocols ports (reactive/websocket/graphql/grpc) (MUST) [CONTRACTS]

---
type: package
phase: 6+
epic_id: "6.161.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Надати vendor-agnostic ports/VO/exceptions для reactive/websocket/graphql/grpc без залежності від конкретних runtime (roadrunner/swoole) та без витоку payload/PII."
provides:
- "Contracts для websocket handlers + connection/session abstraction"
- "Contracts для graphql schema/provider + executor boundary"
- "Contracts для grpc service registry + interceptors boundary"
- "Reactive streams primitives (publisher/subscription) **без** залежності від будь-яких async/runtime типів; cancellation виражається тільки через `SubscriptionInterface::cancel()`."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
- docs/ssot/redaction.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.90.0 — observability ports exist in contracts (tracing/metrics/errors)
  - reactive contracts MUST NOT reference `platform/*` types
  - Cancellation interop — **implementation-only** (platform/*). Contracts **MUST NOT** reference `platform/async` або `CancellationToken` у сигнатурах; cancellation у contracts дозволено **лише** як загальний метод `cancel()` у `SubscriptionInterface`.

- Required deliverables (exact paths):
  - `Coretsia\Contracts\Reactive\*`
  - `Coretsia\Contracts\Protocols\WebSocket\*`
  - `Coretsia\Contracts\Protocols\GraphQL\*`
  - `Coretsia\Contracts\Protocols\Grpc\*`

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - `Psr\Log\LoggerInterface` (optional usage in adapters; contracts must not require a logger)
  - Existing observability ports (Tracer/Meter) should exist for later platform impls

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (contracts-only)

Forbidden:

- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/Reactive/PublisherInterface.php`
- [ ] `framework/packages/core/contracts/src/Reactive/SubscriptionInterface.php`

- [ ] `framework/packages/core/contracts/src/Protocols/WebSocket/WebSocketHandlerInterface.php`
- [ ] `framework/packages/core/contracts/src/Protocols/WebSocket/WebSocketConnectionInterface.php`
- [ ] `framework/packages/core/contracts/src/Protocols/WebSocket/WebSocketMessage.php`

- [ ] `framework/packages/core/contracts/src/Protocols/GraphQL/GraphQlSchemaProviderInterface.php`
- [ ] `framework/packages/core/contracts/src/Protocols/GraphQL/GraphQlExecutorInterface.php`
- [ ] `framework/packages/core/contracts/src/Protocols/GraphQL/GraphQlRequest.php`
- [ ] `framework/packages/core/contracts/src/Protocols/GraphQL/GraphQlResponse.php`

- [ ] `framework/packages/core/contracts/src/Protocols/Grpc/GrpcServiceInterface.php`
- [ ] `framework/packages/core/contracts/src/Protocols/Grpc/GrpcServiceRegistryInterface.php`
- [ ] `framework/packages/core/contracts/src/Protocols/Grpc/GrpcInterceptorInterface.php`

- [ ] Redaction / toString invariants (add)
  - Усі VO/Exception в `src/Protocols/**` і `src/Reactive/**` **MUST** гарантувати:
    - `__toString()` (якщо існує) **не містить** raw payload/query/metadata; тільки safe diagnostics (`len`, `hash`, fixed tokens).
    - exception messages **redaction-safe**.

- [ ] `framework/packages/core/contracts/src/Protocols/ProtocolException.php` — deterministic errorCode(s), redaction-safe message MUST guarantee:
  - message is redaction-safe (no raw payload/query/metadata)
  - errorCode is deterministic and from a closed set

- [ ] `framework/packages/core/contracts/tests/Contract/RealtimeProtocolsNoRawPayloadToStringTest.php`
  - asserts: `WebSocketMessage`, `GraphQlRequest`, `GraphQlResponse`, `ProtocolException` (і будь-які інші VO/Exception цього епіка) не друкують raw дані в `__toString()`/message.

#### Modifies

- [ ] `framework/packages/core/contracts/README.md` — register new namespaces + security/redaction notes

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A (contracts-only)

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/packages/core/contracts/tests/Contract/ProtocolsContractsDoNotDependOnPlatformTest.php` (deptrac/gate style)

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/core/contracts/tests/Contract/ProtocolsContractsDoNotDependOnPlatformTest.php`

### DoD (MUST)

- [ ] No forward refs: contracts compile без runtime deps
- [ ] Ports/VO/exceptions не містять raw payloads у `__toString()`/messages
- [ ] README оновлено

---

### 6.162.0 coretsia/reactive (streams + deterministic safety rails) (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.162.0"
owner_path: "framework/packages/platform/reactive/"

package_id: "platform/reactive"
composer: "coretsia/platform-reactive"
kind: runtime
module_id: "platform.reactive"

goal: "Дати reference реалізацію reactive primitives (publisher/subscription) без event-loop залежності та з policy-compliant observability/redaction."
provides:
- "Reference Publisher/Subscription implementations"
- "Interop з `platform/async` (implementation-level cancellation), без введення будь-яких `platform/*` типів у contracts."
- "Noop-safe logging/tracing hooks (без payload у logs/spans/metrics)"

tags_introduced: []
config_roots_introduced:
- "reactive"

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-roots.md
- docs/ssot/observability-and-errors.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 6.161.0 — realtime protocol ports exist
  - 6.180.0 — CancellationToken primitives exist (`platform/async`) (interop прямо заявлений у provides)
  - 1.200.0 — TagRegistry/DeterministicOrder exist in foundation (if used)

- Required deliverables (exact paths):
  - `framework/packages/platform/reactive/config/reactive.php` — default config subtree (no repeated root)
  - `framework/packages/platform/reactive/config/rules.php` — shape validation rails
  - `framework/packages/platform/reactive/src/Provider/ReactiveServiceProvider.php` — DI wiring proof
  - `framework/packages/platform/reactive/src/Provider/ReactiveServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).

- Required config roots/keys:
  - `reactive.*` — this epic introduces and owns

- Required contracts / ports:
  - `Coretsia\Contracts\Reactive\PublisherInterface`
  - `Coretsia\Contracts\Reactive\SubscriptionInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `platform/async`

Forbidden:

- `platform/http`
- `integrations/*`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/reactive/composer.json` MUST include:
  - `extra.coretsia.providers` with `ReactiveServiceProvider`
  - `extra.coretsia.defaultsConfigPath` = `config/reactive.php`
- [ ] `framework/packages/platform/reactive/src/Module/ReactiveModule.php`
- [ ] `framework/packages/platform/reactive/src/Provider/ReactiveServiceProvider.php`
- [ ] `framework/packages/platform/reactive/src/Provider/ReactiveServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/reactive/src/Reactive/InlinePublisher.php`
- [ ] `framework/packages/platform/reactive/src/Reactive/BufferedPublisher.php`
- [ ] `framework/packages/platform/reactive/src/Reactive/Subscription.php`
- [ ] `framework/packages/platform/reactive/config/reactive.php`
- [ ] `framework/packages/platform/reactive/config/rules.php`
- [ ] `framework/packages/platform/reactive/README.md`
- [ ] `framework/packages/platform/reactive/src/Reactive/PublisherFactory.php` — config-driven publisher creation (deterministic)

- [ ] `framework/packages/platform/reactive/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Modifies

- [ ] `docs/ssot/config-roots.md` — add root row for `reactive`

### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/reactive/config/reactive.php`  # returns subtree (NO `reactive` root key)
- [ ] Keys (dot):
  - [ ] `reactive.enabled` = true
  - [ ] `reactive.publisher.default` = "inline"            # enum: inline|buffered
  - [ ] `reactive.publisher.buffered.max_buffer` = 1024     # int >= 1
  - [ ] `reactive.publisher.buffered.overflow_policy` = "drop_oldest"  # enum: drop_oldest|drop_newest|throw
    → operation token mapping MUST be stable (див. observability rewrite).
- [ ] Rules:
  - [ ] `framework/packages/platform/reactive/config/rules.php` enforces:
    - type safety for every key
    - enum allowlists for `default` + `overflow_policy`
    - numeric floors (>=1), no unknown keys (closed shape)

### Wiring / DI tags (when applicable)

- [ ] Tags introduced: `N/A` (this epic introduces no tags)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Platform\Reactive\Provider\ReactiveServiceFactory`
  - [ ] registers: `\Coretsia\Platform\Reactive\Reactive\PublisherFactory`
  - [ ] binds (example):
    - `PublisherFactory` as singleton (stateless + deterministic)
    - publishers are created by factory (no container iteration = deterministic)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads (optional; for policy-compliant diagnostics only):
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- [ ] Context writes (safe only):
  - [ ] `ContextKeys::UOW_TYPE` MAY be set **only on a derived/child context** for internal hooks/telemetry:
    - allowed low-cardinality tokens: `reactive.publish|reactive.subscribe|reactive.buffer_overflow_drop_oldest|reactive.buffer_overflow_drop_newest|reactive.buffer_overflow_throw`
  - [ ] `ContextKeys::CORRELATION_ID` — N/A (created by outer boundary; MUST NOT be generated here)
  - [ ] `ContextKeys::UOW_ID` — N/A (created by outer boundary; MUST NOT be generated here)
  - [ ] request-safe keys (`CLIENT_IP,SCHEME,HOST,PATH,USER_AGENT`) — N/A (no request boundary)
- [ ] Reset discipline:
  - [ ] Default: N/A — publishers/subscriptions are not container singletons.
  - [ ] If any stateful publisher becomes a long-lived singleton (e.g., buffered queue) → it MUST implement `ResetInterface` and be tagged `kernel.reset`.

#### Observability (policy-compliant)

- [ ] Metrics (allowed):
  - `reactive.op_total` (labels: `operation`, `outcome`)
  - `reactive.op_duration_ms` (labels: `operation`, `outcome`)
  - де `operation` — low-cardinality token: `publish|subscribe|buffer_overflow_drop_oldest|buffer_overflow_drop_newest|buffer_overflow_throw`
- Logs/Spans:
  - span names: `reactive.publish`, `reactive.subscribe` (attrs: тільки `operation`, `outcome`)
  - **NO** payload/event bytes; дозволено тільки `len/hash/counts`.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] If metrics/spans/logs exist → `framework/packages/platform/reactive/tests/Contract/ObservabilityPolicyTest.php`
  (asserts: no payload/PII, label allowlist)
- [ ] Deterministic construction proof:
  - [ ] `framework/packages/platform/reactive/tests/Unit/PublisherFactoryIsDeterministicTest.php`
    (fails if config->publisher mapping becomes non-deterministic)

- If instrumentation реально існує (declared in provides) → add:
  - `framework/packages/platform/reactive/tests/Contract/ObservabilityPolicyTest.php`

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/reactive/tests/Unit/PublisherOrderIsDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/reactive/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### DoD (MUST)

- [ ] Config root registered + rules enforce shape
- [ ] No payload leakage in logs/spans/metrics

---

### 6.163.0 coretsia/websocket (handler registry + kernel) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.163.0"
owner_path: "framework/packages/platform/websocket/"

package_id: "platform/websocket"
composer: "coretsia/platform-websocket"
kind: runtime
module_id: "platform.websocket"

goal: "Надати transport-agnostic WebSocket kernel + handler registry через DI tags, із deterministic order та redaction-safe observability."
provides:
- "WebSocketHandlerRegistry (DI tag discovery, deterministic order)"
- "WebSocketKernel: open/message/close dispatch"
- "Reference error mapping (never leaks frames/payloads)"

tags_introduced:
- "websocket.handler"

config_roots_introduced:
- "websocket"

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/tags.md
- docs/ssot/config-roots.md
- docs/ssot/observability-and-errors.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 6.161.0 — websocket contracts exist
  - 1.10.0 — tags registry exists (to register `websocket.handler`)
  - (optional) 6.180.0 — cancellation tokens exist (if supported)

- Required config roots/keys:
  - `websocket.*` — this epic introduces and owns (MUST include defaults + rules)

- Required tags:
  - `websocket.handler` — MUST be registered in `docs/ssot/tags.md` with owner `platform/websocket`

- Required contracts / ports:
  - `Coretsia\Contracts\Protocols\WebSocket\WebSocketHandlerInterface`
  - `Coretsia\Contracts\Protocols\WebSocket\WebSocketConnectionInterface` (if kernel dispatch uses connection)
  - `Coretsia\Contracts\Protocols\WebSocket\WebSocketMessage` (or frame/message VO реально створений в 6.161)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http` (transport boundary; handshake lives in integrations/runtime-* later)
- `integrations/*` (no hard coupling)

### Entry points / integration points (MUST)

- Tag: `websocket.handler` (owner `platform/websocket`)
- Ordering (cemented): `WebSocketHandlerRegistry` **MUST** consume handlers in the exact order returned by `TagRegistry::all(Tags::WEBSOCKET_HANDLER)` and **MUST NOT** re-sort or dedupe.
  - canonical order already applied: `priority DESC, serviceId ASC`
- Meta schema (closed allowlist; priority НЕ тут):
  - required:
    - `path` (string, required)
  - optional:
    - `protocol` (string, default `"rfc6455"`)
    - `subprotocol` (string|null)
    - `auth` (`"none"|"required"`, default `"none"`)
    - `id` (string, optional; **NOT used for ordering**)
  - unknown meta keys → deterministic fail `CORETSIA_WEBSOCKET_HANDLER_META_INVALID`
- "Meta schema is **closed**: required `path`; optional `protocol, subprotocol, auth, id`; **no other keys**."
- "`priority` is **NOT** a meta key (priority belongs to tag registration / TagRegistry, not to meta)."

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/websocket/composer.json` MUST include:
  - `extra.coretsia.providers` = [`...WebsocketServiceProvider`]
  - `extra.coretsia.defaultsConfigPath` = `config/websocket.php`
- [ ] `framework/packages/platform/websocket/src/Module/WebsocketModule.php`
- [ ] `framework/packages/platform/websocket/src/Provider/WebsocketServiceProvider.php`
- [ ] `framework/packages/platform/websocket/src/Provider/WebsocketServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/websocket/src/Provider/Tags.php` — constants for owned tags
- [ ] `framework/packages/platform/websocket/src/WebSocket/WebSocketHandlerRegistry.php`
- [ ] `framework/packages/platform/websocket/src/WebSocket/WebSocketKernel.php`
- [ ] `framework/packages/platform/websocket/src/Exception/WebsocketException.php`
- [ ] `framework/packages/platform/websocket/config/websocket.php`
- [ ] `framework/packages/platform/websocket/config/rules.php`
- [ ] `framework/packages/platform/websocket/README.md`
- [ ] `framework/packages/platform/websocket/src/WebSocket/WebSocketHandlerDescriptor.php`
  — normalized (serviceId, priority, meta) record used for deterministic ordering + validation
- [ ] `framework/packages/platform/websocket/src/WebSocket/WebSocketHandlerResolver.php`
  — selects handler(s) by path/protocol without leaking request payload

- [ ] `framework/packages/platform/websocket/src/Exception/ErrorCodes.php` — constants:
  - [ ] `CORETSIA_WEBSOCKET_INVALID_FRAME`
  - [ ] `CORETSIA_WEBSOCKET_HANDLER_META_INVALID`
  - [ ] `CORETSIA_WEBSOCKET_HANDLER_FAILED`
  - [ ] `CORETSIA_WEBSOCKET_KERNEL_FAILED`

- [ ] `framework/packages/platform/websocket/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Modifies

- [ ] `docs/ssot/tags.md` — add tag row for `websocket.handler` (owner `platform/websocket`)
- [ ] `docs/ssot/config-roots.md` — add root row for `websocket`

### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/websocket/config/websocket.php`  # returns subtree (NO `websocket` root key)
  - [ ] `framework/packages/platform/websocket/config/rules.php`
- [ ] Keys (dot):
  - [ ] `websocket.enabled` = true
  - [ ] `websocket.kernel.max_frame_bytes` = 65536          # int >= 1
  - [ ] `websocket.kernel.max_message_bytes` = 1048576      # int >= 1
  - [ ] `websocket.kernel.close_on_handler_exception` = true
- [ ] Rules:
  - [ ] enforce ints floors, booleans, closed shape (no unknown keys)

### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/websocket/src/Provider/Tags.php`
  - [ ] constants:
    - [ ] `Tags::WEBSOCKET_HANDLER = 'websocket.handler'`
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Platform\Websocket\WebSocket\WebSocketHandlerRegistry`
  - [ ] registry builds deterministic ordered list from tag `websocket.handler`
    - Does **not** apply any additional ordering: TagRegistry order is final. The registry may only validate meta and build normalized descriptors while preserving order.
    - validates tag meta against allowlist schema (reject/throw typed exception)
  - [ ] registers: `\Coretsia\Platform\Websocket\WebSocket\WebSocketKernel`
    - depends on `WebSocketHandlerRegistry` (and optionally CancellationToken if supported)

- Registry MUST validate meta allowlist schema for `websocket.handler`:
  - required: `path`
  - optional: `protocol, subprotocol, auth, id`
  - reject unknown meta keys deterministically (typed exception)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads (from transport/integration-provided context):
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- [ ] Context writes (safe only):
  - [ ] For each dispatched operation, WebSocketKernel MUST create a **derived/child context** passed into handler execution:
    - [ ] `ContextKeys::UOW_TYPE` = `websocket.open|websocket.message|websocket.close` (low-cardinality)
    - [ ] `ContextKeys::UOW_ID` — MAY be set on derived context **only if** the canonical UoW id strategy already exists at runtime; otherwise keep existing `UOW_ID` unchanged.
  - [ ] `ContextKeys::CORRELATION_ID` — N/A here (MUST be set by transport adapter in `integrations/runtime-*` if missing)
  - [ ] request-safe keys (`CLIENT_IP,SCHEME,HOST,PATH,USER_AGENT`):
    - [ ] `PATH` MAY be written only if it is a **route template / static path** (no query, no dynamic identifiers). Otherwise store only a hash at telemetry layer (not as raw context value).
- [ ] Reset discipline:
  - [ ] `WebSocketHandlerRegistry`, `WebSocketKernel` are stateless → no reset required.
  - [ ] If any connection/session cache is introduced later → MUST implement `ResetInterface` and be tagged `kernel.reset`.

#### Observability (policy-compliant)

- Будь-які metrics/spans/log fields **MUST** використовувати лише allowlisted keys (`driver,operation,outcome,...`) і **MUST NOT** включати `path` raw (дозволено `hash(path)`).

- [ ] Spans:
  - [ ] `<span.name>`
- [ ] Metrics:
  - [ ] `<metric_name_total>` (labels: allowlist only)
  - [ ] `<metric_name_duration_ms>` (labels: allowlist only)
- [ ] Logs:
  - [ ] redaction applied; no secrets/PII/raw payloads

#### Errors

- [ ] Exceptions introduced:
  - [ ] `\Coretsia\Platform\Websocket\Exception\WebsocketException` — errorCode (closed set, prefix `CORETSIA_`):
    - `CORETSIA_WEBSOCKET_INVALID_FRAME` — invalid frame/message shape (MUST NOT embed payload/frame bytes)
    - `CORETSIA_WEBSOCKET_HANDLER_META_INVALID` — invalid/unknown tag meta for `websocket.handler`
    - `CORETSIA_WEBSOCKET_HANDLER_FAILED` — handler threw (MUST NOT embed payload/frame bytes)
    - `CORETSIA_WEBSOCKET_KERNEL_FAILED` — unexpected kernel failure (catch-all; redaction-safe)

- [ ] Mapping:
  - [ ] reuse existing mapper (preferred) OR (only if this package owns mapping) provide mapper via tag `error.mapper`
    - mapping MUST NOT expose frame/payload; only safe ids + errorCode

### Security / Redaction (make it enforceable)

- [ ] MUST NOT leak: frames, payloads, headers, cookies, tokens, raw close reason
- [ ] Allowed telemetry: `len(payload)`, `hash(operation/id)`, handler id, status/close code

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] Deterministic order:
  - [ ] `framework/packages/platform/websocket/tests/Integration/HandlerOrderIsDeterministicTest.php` (already listed)
- [ ] If logs/spans/metrics exist → add:
  - [ ] `framework/packages/platform/websocket/tests/Contract/ObservabilityPolicyTest.php`
- [ ] Redaction proof (recommended because kernel handles frames):
  - [ ] `framework/packages/platform/websocket/tests/Integration/RedactionDoesNotLeakFramesTest.php`
    (fails if payload/frame bytes appear in exception/log/span)
- [ ] `framework/packages/platform/websocket/tests/Contract/ErrorCodesAreCanonicalTest.php`
  (fails if any non-`CORETSIA_` code is introduced, or if code set changes without updating constants/docs)

- Keep deterministic order test.
- Add (if kernel touches frames/messages):
  - `tests/Integration/RedactionDoesNotLeakFramesTest.php`

### Tests (MUST)

- Integration:
  - [ ] `framework/packages/platform/websocket/tests/Integration/HandlerOrderIsDeterministicTest.php`

### DoD (MUST)

- [ ] Tag + config-root registered in SSoT
- [ ] Registry order deterministic, proven by test

---

### 6.164.0 coretsia/graphql (schema provider + executor + HTTP adapter) (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.164.0"
owner_path: "framework/packages/platform/graphql/"

package_id: "platform/graphql"
composer: "coretsia/platform-graphql"
kind: runtime
module_id: "platform.graphql"

goal: "Надати deterministic schema discovery та executor boundary для GraphQL, з HTTP adapter як optional integration (через existing http stack), без витоку query/variables у logs."
provides:
- "GraphQL schema providers registry (DI tag discovery)"
- "Executor boundary (request -> response) без прямої залежності від конкретної vendor-lib у contracts"
- "HTTP middleware/handler (optional) для `/graphql` (redaction-safe)"

tags_introduced:
- "graphql.schema_provider"

config_roots_introduced:
- "graphql"

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/tags.md
- docs/ssot/config-roots.md
- docs/ssot/observability-and-errors.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 6.161.0 — graphql contracts exist
  - 3.130.0 — routing/system routes exist (if HTTP endpoint is provided)
  - 1.10.0 — tags registry exists

- Required tags:
  - `routing.route_provider` — **pre-existing** tag (owner визначений у SSoT tags registry).  
    `platform/graphql` **MUST NOT** вводити/міняти meta schema цього tag — лише заповнює значення згідно SSoT.

- Required contracts / ports:
  - `Coretsia\Contracts\Protocols\GraphQL\GraphQlSchemaProviderInterface`
  - `Coretsia\Contracts\Protocols\GraphQL\GraphQlExecutorInterface`
  - `Coretsia\Contracts\Protocols\GraphQL\GraphQlRequest`
  - `Coretsia\Contracts\Protocols\GraphQL\GraphQlResponse`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `integrations/*` (vendor adapters are future; keep core clean)

### Entry points / integration points (MUST)

- Kernel hooks/tags:
  - "Uses pre-existing tag `routing.route_provider` (schema per SSoT tags registry).
    - This epic MUST NOT define/extend meta schema.
    - `GraphQlRouteProvider` reads `graphql.http.*` config and returns route definitions accordingly.
    - Tag meta (if any) MUST be minimal and SSoT-compliant (e.g. `id: 'graphql'` if that key exists in SSoT)."
- RouteProvider returns `POST /graphql` handler mapping to `GraphQlMiddleware` (only when `graphql.http.enabled=true`)

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/graphql/composer.json` MUST include:
  - `extra.coretsia.providers`
  - `extra.coretsia.defaultsConfigPath` = `config/graphql.php`
- [ ] `framework/packages/platform/graphql/src/Module/GraphQlModule.php`
- [ ] `framework/packages/platform/graphql/src/Provider/GraphQlServiceProvider.php`
- [ ] `framework/packages/platform/graphql/src/Provider/GraphQlServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/graphql/src/Provider/Tags.php`
- [ ] `framework/packages/platform/graphql/src/Schema/SchemaProviderRegistry.php`
- [ ] `framework/packages/platform/graphql/src/Routing/GraphQlRouteProvider.php`
  - make `GraphQlMiddleware.php` explicitly optional **and** only if a stable middleware interface exists in the already-cemented HTTP stack.
    - If not, prefer `GraphQlAction` / `GraphQlHandler` invoked via existing `HttpApp` ports (format-neutral).
  - Wiring evidence MUST include:
    - registers RouteProvider as service
    - adds tag `routing.route_provider` with meta above
    - route provider is no-op when `graphql.http.enabled=false`
- [ ] `framework/packages/platform/graphql/src/Exec/GraphQlExecutor.php`
- [ ] `framework/packages/platform/graphql/src/Http/GraphQlMiddleware.php` (optional; only if compatible with the cemented HTTP adapter surface; otherwise provide `GraphQlHttpAction.php` invoked via existing `HttpApp` ports)."
- [ ] `framework/packages/platform/graphql/config/graphql.php`
- [ ] `framework/packages/platform/graphql/config/rules.php`
- [ ] `framework/packages/platform/graphql/README.md`
- [ ] `framework/packages/platform/graphql/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Modifies

- [ ] `docs/ssot/tags.md` — add tag row for `graphql.schema_provider` (owner `platform/graphql`)
- [ ] `docs/ssot/config-roots.md` — add root row for `graphql`

### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/graphql/config/graphql.php`  # returns subtree (NO `graphql` root key)
  - [ ] `framework/packages/platform/graphql/config/rules.php`
- [ ] Keys (dot):
  - [ ] `graphql.enabled` = true
  - [ ] `graphql.http.enabled` = false                 # default OFF (optional integration)
  - [ ] `graphql.http.path` = "/graphql"
  - [ ] `graphql.http.method` = "POST"                 # closed enum: POST only (by policy)
  - [ ] `graphql.http.max_body_bytes` = 1048576
  - [ ] `graphql.security.allow_introspection` = false
- [ ] Rules:
  - [ ] closed shape + numeric floors + method enum allowlist

### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/graphql/src/Provider/Tags.php`
  - [ ] constants:
    - [ ] `Tags::GRAPHQL_SCHEMA_PROVIDER = 'graphql.schema_provider'`
      - Tag: `graphql.schema_provider` (owner `platform/graphql`)
      - Ordering (cemented): `TagRegistry::all(Tags::GRAPHQL_SCHEMA_PROVIDER)` order is final.
      - "Meta schema (closed allowlist; priority is NOT meta):
        - optional:
          - `id` (string, optional stable schema id; NOT used for ordering)
        - unknown meta keys → deterministic fail"
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Platform\GraphQl\Schema\SchemaProviderRegistry`
    - builds deterministic list from tag `graphql.schema_provider`
    - tag meta schema (closed allowlist):
      - `id` (string, optional)        # schema id
      - `priority` (int, optional)
  - [ ] registers: `\Coretsia\Platform\GraphQl\Exec\GraphQlExecutor`
    - MUST NOT log query/variables
  - [ ] registers: `\Coretsia\Platform\GraphQl\Http\GraphQlMiddleware` (exists always, but route wiring is gated)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- [ ] Context writes (safe only):
  - [ ] When `graphql.http.enabled=true`, the HTTP entrypoint (middleware/handler/action) MUST create a **derived/child context** for execution:
    - [ ] `ContextKeys::UOW_TYPE` = `graphql.execute`
    - [ ] `ContextKeys::UOW_ID` — MAY be set on derived context **only if** canonical UoW id strategy exists; otherwise preserve existing `UOW_ID`.
  - [ ] `ContextKeys::CORRELATION_ID` — SHOULD be provided by the HTTP stack; if missing, set only at the outer HTTP boundary (not inside pure executor code).
  - [ ] request-safe keys (only at HTTP boundary; NEVER store query/variables/body):
    - [ ] `CLIENT_IP`
    - [ ] `SCHEME`
    - [ ] `HOST`
    - [ ] `PATH` (expected static `/graphql`)
    - [ ] `USER_AGENT`
- [ ] Reset discipline:
  - [ ] Registries/executor are stateless → no reset required.
  - [ ] If schema caching is introduced as mutable singleton → MUST implement `ResetInterface` and be tagged `kernel.reset`.

#### Observability (policy-compliant)

- RouteProvider/Middleware **MUST** бути no-op when `graphql.http.enabled=false`.
- Any observability:
  - **MUST NOT** include raw query/variables/body/headers
  - keys only from allowlist; recommend:
    - metric `graphql.op_total` labels: `operation`, `outcome` where `operation=execute`

- [ ] Spans:
  - [ ] `<span.name>`
- [ ] Metrics:
  - [ ] `<metric_name_total>` (labels: allowlist only)
  - [ ] `<metric_name_duration_ms>` (labels: allowlist only)
- [ ] Logs:
  - [ ] redaction applied; no secrets/PII/raw payloads

#### Security / Redaction

- [ ] MUST NOT leak: query, variables, headers, auth tokens
- [ ] Allowed telemetry:
  - operationName `hash/len`
  - response size bytes
  - status (ok/error) + error count (no messages)

### Verification (TEST EVIDENCE)

- [ ] `framework/packages/platform/graphql/tests/Integration/RedactionDoesNotLeakQueryTest.php` (already listed)
- [ ] If metrics/spans/logs exist → add:
  - [ ] `framework/packages/platform/graphql/tests/Contract/ObservabilityPolicyTest.php`
    (asserts: query/variables never appear raw; labels allowlisted)

- Keep: `RedactionDoesNotLeakQueryTest.php`
- Add (if route provider introduced):
  - `tests/Integration/RouteProviderIsNoopWhenHttpDisabledTest.php`

### Tests (MUST)

- Integration:
  - [ ] `framework/packages/platform/graphql/tests/Integration/RedactionDoesNotLeakQueryTest.php`

### DoD (MUST)

- [ ] HTTP adapter is optional and gated by config
- [ ] Redaction доказана тестом

---

### 6.165.0 coretsia/grpc (service registry + interceptors boundary) (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.165.0"
owner_path: "framework/packages/platform/grpc/"

package_id: "platform/grpc"
composer: "coretsia/platform-grpc"
kind: runtime
module_id: "platform.grpc"

goal: "Надати deterministic gRPC service registry через DI tags та interceptor pipeline (auth/metrics/tracing), з transport adapters винесеними в integrations/runtime-*."
provides:
- "GrpcServiceRegistry (DI tag discovery, deterministic order)"
- "Interceptors pipeline contracts + reference implementation"
- "Redaction policy: never log request/response payloads"

tags_introduced:
- "grpc.service"
- "grpc.interceptor"

config_roots_introduced:
- "grpc"

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/tags.md
- docs/ssot/config-roots.md
- docs/ssot/observability-and-errors.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 6.161.0 — grpc contracts exist
  - 1.10.0 — tags registry exists

- Required tags:
  - `grpc.service` — registered in `docs/ssot/tags.md` (owner `platform/grpc`)
  - `grpc.interceptor` — registered in `docs/ssot/tags.md` (owner `platform/grpc`)

- Required contracts / ports:
  - `Coretsia\Contracts\Protocols\Grpc\GrpcServiceInterface`
  - `Coretsia\Contracts\Protocols\Grpc\GrpcInterceptorInterface`
  - (optional) `Coretsia\Contracts\Protocols\Grpc\GrpcServiceRegistryInterface` (if you expose registry via contract)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `integrations/*` (runtime/server specifics out of this epic)

### Entry points / integration points (MUST)

- Tag `grpc.service` (owner `platform/grpc`)
  - Ordering (cemented): consumers MUST preserve the exact order returned by `TagRegistry::all(...)` and MUST NOT re-sort/dedupe.
  - Meta schema (closed allowlist; priority НЕ тут):
    - `service` (string, required)   # stable logical name (low-cardinality)
    - `proto` (string, required)     # stable proto id (low-cardinality)
    - `id` (string, optional; NOT used for ordering)
- Tag `grpc.interceptor` (owner `platform/grpc`)
  - Ordering: `TagRegistry::all(Tags::GRPC_INTERCEPTOR)` order is final
  - Meta schema (closed allowlist; priority НЕ тут):
    - `stage` (`auth|metrics|tracing|custom`, required; low-cardinality)
    - `id` (string, optional; NOT used for ordering)
- Unknown meta keys → deterministic fail (typed exception; no metadata dumps)

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/grpc/composer.json` MUST include:
  - `extra.coretsia.providers`
  - `extra.coretsia.defaultsConfigPath` = `config/grpc.php`
- [ ] `framework/packages/platform/grpc/src/Module/GrpcModule.php`
- [ ] `framework/packages/platform/grpc/src/Provider/GrpcServiceProvider.php`
- [ ] `framework/packages/platform/grpc/src/Provider/GrpcServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/grpc/src/Provider/Tags.php`
- [ ] `framework/packages/platform/grpc/src/Grpc/GrpcServiceRegistry.php`
- [ ] `framework/packages/platform/grpc/src/Grpc/InterceptorPipeline.php`
- [ ] `framework/packages/platform/grpc/config/grpc.php`
- [ ] `framework/packages/platform/grpc/config/rules.php`
- [ ] `framework/packages/platform/grpc/README.md`
- [ ] `framework/packages/platform/grpc/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Modifies

- [ ] `docs/ssot/config-roots.md` — add root row for `grpc`
- [ ] `docs/ssot/tags.md` — add tag rows:
  - `grpc.service` (owner `platform/grpc`)
  - `grpc.interceptor` (owner `platform/grpc`)

### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/grpc/config/grpc.php`  # returns subtree (NO `grpc` root key)
  - [ ] `framework/packages/platform/grpc/config/rules.php`
- [ ] Keys (dot):
  - [ ] `grpc.enabled` = true
  - [ ] `grpc.interceptors.enabled` = true
- [ ] Rules:
  - [ ] closed shape; booleans only; no unknown keys

### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/grpc/src/Provider/Tags.php` constants:
    - [ ] `Tags::GRPC_SERVICE = 'grpc.service'`
    - [ ] `Tags::GRPC_INTERCEPTOR = 'grpc.interceptor'`
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Platform\Grpc\Grpc\GrpcServiceRegistry`
    - deterministic list from tag `grpc.service`
  - [ ] registers: `\Coretsia\Platform\Grpc\Grpc\InterceptorPipeline`
    - deterministic list from tag `grpc.interceptor`
    - respects `grpc.interceptors.enabled` (if false → pipeline is empty/noop but deterministic)

- `grpc.service` meta allowlist: `service, proto, id`
- `grpc.interceptor` meta allowlist: `stage, id`
- Reject unknown meta keys deterministically (typed exception)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads (from transport/integration-provided context):
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- [ ] Context writes (safe only):
  - [ ] For each call, interceptor pipeline / dispatcher MUST use a **derived/child context**:
    - [ ] `ContextKeys::UOW_TYPE` = `grpc.call`
    - [ ] `ContextKeys::UOW_ID` — MAY be set on derived context only if canonical UoW id strategy exists; otherwise preserve existing.
  - [ ] `ContextKeys::CORRELATION_ID` — N/A here (set by transport adapter if missing)
  - [ ] request-safe keys (`CLIENT_IP,SCHEME,HOST,PATH,USER_AGENT`) — N/A (grpc transport-specific; do not invent)
  - [ ] MUST NOT write raw grpc metadata/headers into context.
- [ ] Reset discipline:
  - [ ] Registry/pipeline are stateless → no reset required.
  - [ ] If any mutable service cache is introduced → MUST implement `ResetInterface` and be tagged `kernel.reset`.

#### Observability (policy-compliant)

- Будь-які telemetry keys **MUST** бути тільки allowlisted.  
  Рекомендовано:
  - `grpc.op_total` labels: `operation`, `outcome` (`operation=call`)
  - без request/response payloads, без metadata headers.

- [ ] Spans:
  - [ ] `<span.name>`
- [ ] Metrics:
  - [ ] `<metric_name_total>` (labels: allowlist only)
  - [ ] `<metric_name_duration_ms>` (labels: allowlist only)
- [ ] Logs:
  - [ ] redaction applied; no secrets/PII/raw payloads

#### Security / Redaction

- [ ] MUST NOT leak: protobuf payloads, metadata headers (authorization), tokens
- [ ] Allowed telemetry: service/method id, status code, sizes (len), hashes

### Verification (TEST EVIDENCE)

- [ ] Deterministic order proof (services + interceptors):
  - [ ] `framework/packages/platform/grpc/tests/Integration/RegistryOrderIsDeterministicTest.php`
    (fails if tag iteration order becomes non-deterministic)
- [ ] If metrics/spans/logs exist → add:
  - [ ] `framework/packages/platform/grpc/tests/Contract/ObservabilityPolicyTest.php`
- [ ] Redaction proof (recommended):
  - [ ] `framework/packages/platform/grpc/tests/Integration/RedactionDoesNotLeakMetadataTest.php`

- Keep deterministic order test.
- Add:
  - `tests/Contract/ObservabilityPolicyTest.php` (if instrumentation exists)
  - `tests/Integration/RedactionDoesNotLeakMetadataTest.php` (if metadata is processed)

### DoD (MUST)

- [ ] Registry deterministic, proven
- [ ] Out-of-scope clarified: server transport adapters belong to integrations

---

### 6.170.0 coretsia/streaming (SSE + NDJSON + disable buffering) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.170.0"
owner_path: "framework/packages/platform/streaming/"

package_id: "platform/streaming"
composer: "coretsia/platform-streaming"
kind: runtime
module_id: "platform.streaming"

goal: "Streaming endpoints (SSE/NDJSON) можна будувати з deterministic framing, а middleware вимикає buffering коли модуль увімкнено."
provides:
- "Канонічні утиліти для streaming responses (SSE + NDJSON) без ручного форматування в контролерах"
- "Middleware DisableBufferingMiddleware (system_post, priority 650) для коректного streaming (proxy buffering off)"
- "Deterministic framing + безпечна observability (без payload/PII)"

tags_introduced: []
config_roots_introduced:
- "streaming"

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/tags.md
- docs/ssot/http-middleware-catalog.md
- docs/ssot/config-roots.md
- docs/ssot/observability.md
- docs/ssot/observability-and-errors.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required SSoT invariants:
  - MUST use only middleware taxonomy slots: `http.middleware.system_*|app_*|route_*`
  - MUST NOT reference legacy forbidden tags: `http.middleware.user_*`
- Required tags (used, not owned):
  - `http.middleware.system_post` (owned by `platform/http` in SSoT tags registry)

- Epic prerequisites:
  - none

- Required deliverables (exact paths):
  - none

- Required config roots/keys:
  - none

- Required tags:
  - `http.middleware.system_post` — slot must exist (owned by HTTP pipeline) so this package can register middleware

- Required contracts / ports:
  - `Psr\Http\Server\MiddlewareInterface` — middleware integration surface
  - `Psr\Http\Message\ResponseInterface` / `Psr\Http\Message\StreamInterface` — streaming response surface

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `platform/http-app`
- `platform/cli`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\StreamInterface`
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- HTTP:
  - middleware slots/tags: `http.middleware.system_post` priority `650` meta `{"reason":"disable buffering for streaming responses"}`
    - service: `Coretsia\Streaming\Http\Middleware\DisableBufferingMiddleware`
- CLI:
  - N/A
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/streaming/src/Sse/SseEmitter.php` — SSE framing (deterministic)
- [ ] `framework/packages/platform/streaming/src/Ndjson/NdjsonEmitter.php` — NDJSON framing (deterministic order)
- [ ] `framework/packages/platform/streaming/src/Http/Response/StreamingResponseFactory.php` — build streaming `ResponseInterface`
- [ ] `framework/packages/platform/streaming/src/Http/Middleware/DisableBufferingMiddleware.php` — disables proxy buffering for streaming responses
- [ ] `framework/packages/platform/streaming/src/Observability/StreamingInstrumentation.php` — spans/metrics helper (noop-safe)
- [ ] `framework/packages/platform/streaming/src/Exception/StreamingException.php` — deterministic error codes (write failures)
- [ ] `docs/architecture/streaming.md` — usage (SSE/NDJSON) + middleware slot/priority + override/disable notes
- [ ] `framework/packages/platform/streaming/config/streaming.php` — config subtree
- [ ] `framework/packages/platform/streaming/config/rules.php` — config shape rules
- [ ] `framework/packages/platform/streaming/src/Module/StreamingModule.php` — runtime module
- [ ] `framework/packages/platform/streaming/src/Provider/StreamingServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/streaming/src/Provider/StreamingServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/streaming/README.md` — must include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/platform/streaming/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract proof (noop-safe)

#### Modifies

- [ ] `docs/ssot/config-roots.md` — add `streaming` root registry row (owner `platform/streaming`)
- [ ] `docs/ssot/http-middleware-catalog.md` — register middleware entry:
  - slot `http.middleware.system_post`
  - priority `650`
  - owner `platform/streaming`
  - service `Coretsia\Streaming\Http\Middleware\DisableBufferingMiddleware`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/streaming/composer.json`
- [ ] `framework/packages/platform/streaming/src/Module/StreamingModule.php` (runtime only)
- [ ] `framework/packages/platform/streaming/src/Provider/StreamingServiceProvider.php` (runtime only)
- [ ] `framework/packages/platform/streaming/config/streaming.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/platform/streaming/config/rules.php`
- [ ] `framework/packages/platform/streaming/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/streaming/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/streaming/config/streaming.php`
- [ ] Keys (dot):
  - [ ] `streaming.enabled` = false
  - [ ] `streaming.sse.enabled` = true
  - [ ] `streaming.ndjson.enabled` = true
  - [ ] `streaming.http.disable_buffering.enabled` = true
  - [ ] `streaming.http.disable_buffering.header` = 'X-Accel-Buffering'
  - [ ] `streaming.http.disable_buffering.value` = 'no'
- [ ] Rules:
  - [ ] `framework/packages/platform/streaming/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (uses existing `http.middleware.system_post`; provides constants only)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Streaming\Http\Middleware\DisableBufferingMiddleware`
  - [ ] adds tag: `http.middleware.system_post` priority `650` meta `{"reason":"disable buffering for streaming responses"}`
  - [ ] registers: `Coretsia\Streaming\Http\Response\StreamingResponseFactory`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::PATH_TEMPLATE` (if available; for safe metrics/logs)
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] any stateful emitters/buffers implement `ResetInterface`
  - [ ] all stateful services tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `streaming.sse.write`
  - [ ] `streaming.ndjson.write`
- [ ] Metrics:
  - [ ] `streaming.write_total` (labels: `operation`, `outcome`)
  - [ ] `streaming.write_duration_ms` (labels: `operation`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `kind/op/uow_type→operation`
- [ ] Logs:
  - [ ] warnings/errors only (no payload), include counts/bytes only (no content)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/streaming/src/Exception/StreamingException.php` — errorCode `CORETSIA_STREAMING_WRITE_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] streamed payload contents, Authorization/Cookie/session id/tokens
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id`)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `framework/packages/platform/streaming/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  (asserts: noop adapters do not throw; extend for names/label allowlist as needed)
- [ ] If redaction exists → `framework/packages/platform/streaming/tests/Integration/StreamingFactoryBuildsResponseWithoutLeakingPayloadTest.php`
  (asserts: payload not leaked into logs/observability surface by factory/instrumentation)

- If instrumentation exists:
  - add `tests/Contract/ObservabilityPolicyTest.php`
  - keep existing redaction/no-payload integration test

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions (used by tests above)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/streaming/tests/Unit/SseFramingDeterministicTest.php`
  - [ ] `framework/packages/platform/streaming/tests/Unit/NdjsonFramingDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/streaming/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/streaming/tests/Integration/DisableBufferingMiddlewareSetsDeterministicHeadersTest.php`
  - [ ] `framework/packages/platform/streaming/tests/Integration/StreamingFactoryBuildsResponseWithoutLeakingPayloadTest.php`
  - [ ] `framework/packages/platform/streaming/tests/Integration/Http/StreamingDisableBufferingMiddlewareIsActiveWhenEnabledTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)
  - [ ] gates updated (if new invariants)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: rerun-no-diff (if outputs generated)
- [ ] Non-goals / out of scope:
  - [ ] Не реалізуємо WebSocket/GraphQL/gRPC (це окремі протоколи)
  - [ ] Не додаємо нових reserved endpoints (streaming — це response type, не системний endpoint)
  - [ ] Не додаємо залежність на `platform/http` (виконується у його pipeline через DI tags)
- [ ] Acceptance scenario:
  - [ ] When an action returns an SSE response, then lines are framed deterministically and buffering is disabled via middleware when enabled.
- [ ] Observability policy satisfied (no payload/PII, low-cardinality labels)
- [ ] Determinism:
  - [ ] framing output stable for same inputs

---

### 6.180.0 coretsia/async (timeouts + cancellation tokens) (OPTIONAL) [IMPL]

---
type: package
phase: 6+
epic_id: "6.180.0"
owner_path: "framework/packages/platform/async/"

package_id: "platform/async"
composer: "coretsia/platform-async"
kind: runtime
module_id: "platform.async"

goal: "Timeout middleware може детерміновано перервати довгі запити (soft), а cancellation token доступний для cooperative cancel без витоку даних."
provides:
- "Thin soft-timeout middleware для HTTP без event-loop"
- "Cancellation primitives (token/source) для cooperative cancel у CLI/HTTP/jobs, runtime-agnostic"
- "Autowiring RequestTimeoutMiddleware у `http.middleware.app_pre` (priority 350) коли enabled"

tags_introduced: []
config_roots_introduced:
- "async"

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/tags.md
- docs/ssot/http-middleware-catalog.md
- docs/ssot/config-roots.md
- docs/ssot/observability.md
- docs/ssot/observability-and-errors.md
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
  - `http.middleware.app_pre` — slot must exist (owned by HTTP pipeline) so timeout middleware can be registered
  - `error.mapper` — only if exception mapping is enabled/used

- Required contracts / ports:
  - `Psr\Http\Server\MiddlewareInterface` — middleware integration surface
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface` — only if mapper is enabled/used

- Timing invariant (cemented):
  - MUST NOT use `microtime(true)` or any float-based timing
  - MUST use `Coretsia\Foundation\Time\Stopwatch` (int-only) to compute durations

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
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface` (optional)
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextKeys`
  - `Coretsia\Foundation\Time\Stopwatch`  # int-only (hrtime), no floats

### Entry points / integration points (MUST)

- HTTP:
  - middleware slots/tags: `http.middleware.app_pre` priority `350` meta `{"reason":"soft request timeout before routing boundary"}`
    - service: `Coretsia\Async\Http\Middleware\RequestTimeoutMiddleware`
- CLI:
  - N/A
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/async/src/Timeout/TimeoutPolicy.php` — deterministic policy (ints only)
- [ ] `framework/packages/platform/async/src/Cancel/CancellationToken.php` — cancel check API
- [ ] `framework/packages/platform/async/src/Cancel/CancellationTokenSource.php` — token source
- [ ] `framework/packages/platform/async/src/Http/Middleware/RequestTimeoutMiddleware.php` — soft timeout middleware
- [ ] `framework/packages/platform/async/src/Exception/RequestTimeoutException.php` — deterministic timeout exception (optional)
- [ ] `framework/packages/platform/async/src/Exception/AsyncProblemMapper.php` — maps timeout to ErrorDescriptor (optional)
- [ ] `framework/packages/platform/async/src/Observability/AsyncInstrumentation.php` — spans/metrics helper
- [ ] `docs/architecture/async-timeouts.md` — ordering (user_before_routing 350), override/disable notes, safe logging rules
- [ ] `framework/packages/platform/async/config/async.php` — config subtree
- [ ] `framework/packages/platform/async/config/rules.php` — config shape rules
- [ ] `framework/packages/platform/async/src/Module/AsyncModule.php` — runtime module
- [ ] `framework/packages/platform/async/src/Provider/AsyncServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/async/src/Provider/AsyncServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/async/README.md` — must include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/platform/async/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract proof (noop-safe)

#### Modifies

- [ ] `docs/ssot/config-roots.md` — add `async` root registry row (owner `platform/async`)
- [ ] `docs/ssot/http-middleware-catalog.md` — register middleware entry:
  - slot `http.middleware.app_pre`
  - priority `350`
  - owner `platform/async`
  - service `Coretsia\Async\Http\Middleware\RequestTimeoutMiddleware`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/async/composer.json`
- [ ] `framework/packages/platform/async/src/Module/AsyncModule.php` (runtime only)
- [ ] `framework/packages/platform/async/src/Provider/AsyncServiceProvider.php` (runtime only)
- [ ] `framework/packages/platform/async/config/async.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/platform/async/config/rules.php`
- [ ] `framework/packages/platform/async/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/async/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/async/config/async.php`
- [ ] Keys (dot):
  - [ ] `async.enabled` = false
  - [ ] `async.http.timeout.enabled` = false
  - [ ] `async.http.timeout.ms` = 30000
  - [ ] `async.http.timeout.grace_ms` = 250
  - [ ] `async.cancel.enabled` = true
  - [ ] `async.http.timeout.error_mapper_enabled` = false          # when true → register mapper + tag `error.mapper`
- [ ] Rules:
  - [ ] `framework/packages/platform/async/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (uses existing tags `HTTP_MIDDLEWARE_APP_PRE = 'http.middleware.app_pre'`, `ERROR_MAPPER = 'error.mapper'`; provides constants only)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Async\Http\Middleware\RequestTimeoutMiddleware`
  - [ ] adds tag: `http.middleware.app_pre` priority `350` meta `{"reason":"soft request timeout before routing boundary"}`
  - [ ] registers: `Coretsia\Async\Cancel\CancellationTokenSource`
  - [ ] (optional) registers: `Coretsia\Async\Exception\AsyncProblemMapper`
  - [ ] (optional) adds tag: `error.mapper` priority `200` meta `{"handles":"RequestTimeoutException"}`

- If `async.http.timeout.error_mapper_enabled=true`:
  - registers `AsyncProblemMapper`
  - adds tag `error.mapper` priority `200` meta `{"handles":"RequestTimeoutException"}`
- Else:
  - mapper service may exist, but MUST NOT be tagged (no accidental activation)

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
  - [ ] token sources / middleware state implement `ResetInterface` (if any state cached)
  - [ ] all stateful services tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `async.http.timeout`
- [ ] Metrics:
  - [ ] `async.timeout_total` (labels: `operation`, `outcome`)
  - [ ] `async.timeout_duration_ms` (labels: `operation`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `kind/op/uow_type→operation`
- [ ] Logs:
  - [ ] timeout → warn/info (duration only), no headers/cookies/body

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/async/src/Exception/RequestTimeoutException.php` — errorCode `CORETSIA_HTTP_REQUEST_TIMEOUT`
- [ ] Mapping:
  - [ ] new mapper via tag `error.mapper` (optional) OR reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/raw payload
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id`)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `framework/packages/platform/async/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  (asserts: noop adapters do not throw; extend for names/label allowlist as needed)
- [ ] If redaction exists → `framework/packages/platform/async/tests/Integration/TimeoutDoesNotLeakSecretsToLogsTest.php`
  (asserts: no secret leakage to logs/observability surface)

- Add (if mapper gating added):
  - `tests/Integration/TimeoutMapperIsNotTaggedWhenDisabledTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/async/tests/Unit/TimeoutPolicyDeterministicTest.php`
  - [ ] `framework/packages/platform/async/tests/Unit/CancellationTokenDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/async/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/async/tests/Integration/RequestTimeoutMiddlewareTriggersDeterministicallyTest.php`
  - [ ] `framework/packages/platform/async/tests/Integration/TimeoutDoesNotLeakSecretsToLogsTest.php`
  - [ ] `framework/packages/platform/async/tests/Integration/Http/RequestTimeoutMiddlewareIsActiveWhenEnabledTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)
  - [ ] gates updated (if new invariants)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: rerun-no-diff (if outputs generated)
- [ ] Non-goals / out of scope:
  - [ ] Не реалізуємо повноцінний async runtime/event loop
  - [ ] Не логимо request payload або headers; timeout decision тільки safe metadata
  - [ ] Не додаємо залежність `platform/http` (wiring через DI tags)
- [ ] Acceptance scenario:
  - [ ] When a request exceeds configured timeout, then it returns a deterministic error response via canonical error flow and no payload is logged.
- [ ] HTTP status hint policy:
  - [ ] timeout → `httpStatus=504` (documented in README if mapper used)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/http` executes `http.middleware.app_pre`
  - [ ] `platform/errors + platform/problem-details` handle exceptions via mapper (if you choose exception mapping)
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe

---

### 6.190.0 integrations/runtime-frankenphp (long-running adapter) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.190.0"
owner_path: "framework/packages/integrations/runtime-frankenphp/"

package_id: "integrations/runtime-frankenphp"
composer: "coretsia/integrations-runtime-frankenphp"
kind: runtime
module_id: "integrations.runtime-frankenphp"

goal: "FrankenPHP може обслуговувати багато запитів підряд, а кожен запит має свій UoW і не тече контекст/стан між запитами."
provides:
- "Runtime adapter для FrankenPHP, який запускає `platform/http` HttpKernel без зміни `platform/http`"
- "Long-running safety: UoW boundary + reset discipline між requests (100+ sequential requests harness)"
- "Skeleton entrypoint для запуску у FrankenPHP середовищі"

tags_introduced: []
config_roots_introduced:
- "runtime_frankenphp"

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-roots.md
- docs/ssot/runtime-drivers.md
- docs/ssot/artifacts.md
- docs/ssot/artifacts-and-fingerprint.md
- docs/ssot/observability-and-errors.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - none

- Required deliverables (exact paths):
  - none

- Required config roots/keys:
  - `worker.enabled` — MUST be `false` when `runtime_frankenphp.enabled=true` (policy carried from legacy epic)

- Required tags:
  - none

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — tracing port (noop-safe allowed)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics port (noop-safe allowed)

- Runtime driver matrix precondition (MUST):
  - `docs/ssot/runtime-drivers.md` MUST define this driver (`http.frankenphp` / `http.swoole`) and its conflicts
  - `Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard` MUST recognize:
    - `runtime_frankenphp.enabled=true` as `http.frankenphp`
    - `runtime_swoole.enabled=true` as `http.swoole`
  - Entrypoint MUST call `RuntimeDriverGuard->assertCompatible()` before boot

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`
- `platform/http`

Forbidden:

- `platform/http-app`
- `platform/cli`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation:
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- HTTP:
  - routes: N/A (uses existing `platform/http` HttpKernel; no new routes)
  - middleware slots/tags: N/A
- CLI:
  - N/A
- Kernel hooks/tags:
  - N/A (relies on KernelRuntime UoW begin/after inside `platform/http` as policy)
- Artifacts:
  - reads:
    - `skeleton/var/cache/<appId>/module-manifest.php`
    - `skeleton/var/cache/<appId>/config.php`
    - `skeleton/var/cache/<appId>/container.php`
    - `skeleton/var/cache/<appId>/routes.php`
  - writes: N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/runtime-frankenphp/src/Runtime/FrankenphpKernelRunner.php` — bridge FrankenPHP request lifecycle to HttpKernel
- [ ] `framework/packages/integrations/runtime-frankenphp/src/Runtime/FrankenphpServerRequestFactory.php` — builds PSR-7 request deterministically (no superglobals beyond allowed entrypoint)
- [ ] `framework/packages/integrations/runtime-frankenphp/src/Runtime/FrankenphpResponseEmitter.php` — emits response safely (deterministic headers policy)
- [ ] `framework/packages/integrations/runtime-frankenphp/src/Exception/RuntimeFrankenphpException.php` — deterministic error codes
- [ ] `skeleton/apps/web/public/frankenphp.php` — FrankenPHP entrypoint (thin glue)
- [ ] `docs/ops/runtime-frankenphp.md` — ops guide: enable module, run, troubleshooting, long-running reset notes
- [ ] `framework/packages/integrations/runtime-frankenphp/config/runtime_frankenphp.php` — config subtree
- [ ] `framework/packages/integrations/runtime-frankenphp/config/rules.php` — config shape rules
- [ ] `framework/packages/integrations/runtime-frankenphp/src/Module/RuntimeFrankenphpModule.php` — runtime module
- [ ] `framework/packages/integrations/runtime-frankenphp/src/Provider/RuntimeFrankenphpServiceProvider.php` — DI wiring
- [ ] `framework/packages/integrations/runtime-frankenphp/src/Provider/RuntimeFrankenphpServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/runtime-frankenphp/README.md` — must include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/integrations/runtime-frankenphp/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract proof (noop-safe)

#### Modifies

- [ ] `docs/ssot/config-roots.md` — add `runtime_frankenphp` root registry row (owner `integrations/runtime-frankenphp`)
- [ ] `docs/ssot/runtime-drivers.md` — add `http.frankenphp` driver entry + conflicts

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/runtime-frankenphp/composer.json`
- [ ] `framework/packages/integrations/runtime-frankenphp/src/Module/RuntimeFrankenphpModule.php` (runtime only)
- [ ] `framework/packages/integrations/runtime-frankenphp/src/Provider/RuntimeFrankenphpServiceProvider.php` (runtime only)
- [ ] `framework/packages/integrations/runtime-frankenphp/config/runtime_frankenphp.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/integrations/runtime-frankenphp/config/rules.php`
- [ ] `framework/packages/integrations/runtime-frankenphp/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/integrations/runtime-frankenphp/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/runtime-frankenphp/config/runtime_frankenphp.php`
- [ ] Keys (dot):
  - [ ] `runtime_frankenphp.enabled` = false
  - [ ] `runtime_frankenphp.app_id` = 'web'
  - [ ] `runtime_frankenphp.max_requests` = 0
  - [ ] `runtime_frankenphp.graceful_shutdown.enabled` = true
- [ ] Rules:
  - [ ] `framework/packages/integrations/runtime-frankenphp/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\RuntimeFrankenphp\Runtime\FrankenphpKernelRunner`
  - [ ] documents how entrypoint resolves runner from container (artifact-only policy respected by kernel)

#### Artifacts / outputs (if applicable)

- [ ] Reads:
  - [ ] validates header + payload schema (as defined by Kernel artifacts policy)
  - [ ] `skeleton/var/cache/<appId>/module-manifest.php`
  - [ ] `skeleton/var/cache/<appId>/config.php`
  - [ ] `skeleton/var/cache/<appId>/container.php`
  - [ ] `skeleton/var/cache/<appId>/routes.php`

- Artifact validation (MUST):
  - MUST reuse kernel artifact envelope/header policy (`_meta` + `payload`)
  - MUST validate schemaVersion and fingerprint fields as defined by kernel artifacts SSoT
  - MUST NOT introduce a parallel artifact format or re-implement stable JSON rules differently

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (via logging/tracing)
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] integration test proves no context leak across sequential requests (memory harness)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `runtime.frankenphp.request`
- [ ] Metrics:
  - [ ] `runtime.request_total` (labels: `driver`, `outcome`)
  - [ ] `runtime.request_duration_ms` (labels: `driver`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`
- [ ] Logs:
  - [ ] startup/shutdown + per-request summary only; no headers/cookies/payloads

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/integrations/runtime-frankenphp/src/Exception/RuntimeFrankenphpException.php` — errorCode `CORETSIA_RUNTIME_FRANKENPHP_BOOT_FAILED`
  - [ ] `framework/packages/integrations/runtime-frankenphp/src/Exception/RuntimeFrankenphpException.php` — errorCode `CORETSIA_RUNTIME_FRANKENPHP_REQUEST_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) (prefer HTTP problem-details flow when request reaches HttpKernel)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/raw payload
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id`)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `framework/packages/integrations/runtime-frankenphp/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  (asserts: noop adapters do not throw; extend for policy assertions as needed)
- [ ] If long-running reset discipline exists → `framework/packages/integrations/runtime-frankenphp/tests/Integration/SequentialRequestsDoNotLeakContextTest.php`
  (asserts: no context leak across sequential requests)

#### Test harness / fixtures (when integration is needed)

- [ ] Harness:
  - [ ] sequential requests (100+) memory harness as part of integration tests

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/integrations/runtime-frankenphp/tests/Unit/RequestFactoryDoesNotUseGlobalsOutsideEntrypointTest.php`
- Contract:
  - [ ] `framework/packages/integrations/runtime-frankenphp/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/runtime-frankenphp/tests/Integration/SequentialRequestsDoNotLeakContextTest.php`
  - [ ] `framework/packages/integrations/runtime-frankenphp/tests/Integration/GracefulShutdownStopsAfterMaxRequestsTest.php` (if max_requests>0)
  - [ ] fails when worker_http enabled
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: rerun-no-diff (if outputs generated)
- [ ] Non-goals / out of scope:
  - [ ] Не додаємо middleware або endpoints (це runtime runner)
  - [ ] Не змінюємо `platform/http` public API (тільки використовуємо HttpKernelInterface)
  - [ ] Не додаємо HTTP/2/3 логіку у код (це ops-level, див. 3.240.0)
- [ ] Entrypoint invariant:
  - [ ] Entrypoint MUST call `RuntimeDriverGuard->assertCompatible()` before booting HttpKernel
- [ ] Runtime expectation:
  - [ ] Має бути вимкнено: `worker.enabled=false`, якщо `runtime_frankenphp` активний
- [ ] Acceptance scenario:
  - [ ] When 100 sequential HTTP requests are handled, then memory harness shows no context leak and all responses are correct.
- [ ] Usually present when enabled in presets/bundles:
  - [ ] runs with `platform/http` module enabled (and its required baseline modules by preset)

---

### 6.200.0 integrations/runtime-swoole (long-running adapter) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.200.0"
owner_path: "framework/packages/integrations/runtime-swoole/"

package_id: "integrations/runtime-swoole"
composer: "coretsia/integrations-runtime-swoole"
kind: runtime
module_id: "integrations.runtime-swoole"

goal: "Swoole server може обслуговувати long-running трафік без витоку контексту/стану між запитами і з graceful shutdown."
provides:
- "Runtime adapter для Swoole HTTP server, який запускає `platform/http` HttpKernel без зміни `platform/http`"
- "Long-running safety: UoW boundary + reset дисципліна між requests, graceful shutdown hooks"
- "Ops guide + reference server factory (deterministic config)"

tags_introduced: []
config_roots_introduced:
- "runtime_swoole"

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-roots.md
- docs/ssot/runtime-drivers.md
- docs/ssot/artifacts.md
- docs/ssot/artifacts-and-fingerprint.md
- docs/ssot/observability-and-errors.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - none

- Required deliverables (exact paths):
  - none

- Required config roots/keys:
  - `worker.enabled` — MUST be `false` when `runtime_swoole.enabled=true` (policy carried from legacy epic)

- Required tags:
  - none

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — tracing port (noop-safe allowed)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics port (noop-safe allowed)

- Runtime driver matrix precondition (MUST):
  - `docs/ssot/runtime-drivers.md` MUST define this driver (`http.frankenphp` / `http.swoole`) and its conflicts
  - `Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard` MUST recognize:
    - `runtime_frankenphp.enabled=true` as `http.frankenphp`
    - `runtime_swoole.enabled=true` as `http.swoole`
  - Entrypoint MUST call `RuntimeDriverGuard->assertCompatible()` before boot

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`
- `platform/http`

Forbidden:

- `platform/http-app`
- `platform/cli`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation:
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- HTTP:
  - routes: N/A (uses existing `platform/http` HttpKernel; no new routes)
  - middleware slots/tags: N/A
- CLI:
  - N/A
- Kernel hooks/tags:
  - N/A (relies on KernelRuntime UoW begin/after inside `platform/http` as policy)
- Artifacts:
  - reads:
    - `skeleton/var/cache/<appId>/module-manifest.php`
    - `skeleton/var/cache/<appId>/config.php`
    - `skeleton/var/cache/<appId>/container.php`
    - `skeleton/var/cache/<appId>/routes.php`
  - writes: N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/runtime-swoole/src/Runtime/SwooleKernelRunner.php` — bridge Swoole lifecycle to HttpKernel
- [ ] `framework/packages/integrations/runtime-swoole/src/Runtime/SwooleServerFactory.php` — server factory (deterministic config)
- [ ] `framework/packages/integrations/runtime-swoole/src/Runtime/SwooleRequestFactory.php` — PSR-7 request creation (safe)
- [ ] `framework/packages/integrations/runtime-swoole/src/Runtime/SwooleResponseEmitter.php` — response emit (safe)
- [ ] `framework/packages/integrations/runtime-swoole/src/Exception/RuntimeSwooleException.php` — deterministic error codes
- [ ] `docs/ops/runtime-swoole.md` — ops guide: enable module, run, tuning, graceful shutdown
- [ ] `skeleton/apps/web/public/swoole.php` (optional) — thin entrypoint/runner bootstrap
- [ ] `framework/packages/integrations/runtime-swoole/config/runtime_swoole.php` — config subtree
- [ ] `framework/packages/integrations/runtime-swoole/config/rules.php` — config shape rules
- [ ] `framework/packages/integrations/runtime-swoole/src/Module/RuntimeSwooleModule.php` — runtime module
- [ ] `framework/packages/integrations/runtime-swoole/src/Provider/RuntimeSwooleServiceProvider.php` — DI wiring
- [ ] `framework/packages/integrations/runtime-swoole/src/Provider/RuntimeSwooleServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/runtime-swoole/README.md` — must include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/integrations/runtime-swoole/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract proof (noop-safe)

#### Modifies

- [ ] `docs/ssot/config-roots.md` — add `runtime_swoole` root registry row (owner `integrations/runtime-swoole`)
- [ ] `docs/ssot/runtime-drivers.md` — add `http.swoole` driver entry + conflicts

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/runtime-swoole/composer.json`
- [ ] `framework/packages/integrations/runtime-swoole/src/Module/RuntimeSwooleModule.php` (runtime only)
- [ ] `framework/packages/integrations/runtime-swoole/src/Provider/RuntimeSwooleServiceProvider.php` (runtime only)
- [ ] `framework/packages/integrations/runtime-swoole/config/runtime_swoole.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/integrations/runtime-swoole/config/rules.php`
- [ ] `framework/packages/integrations/runtime-swoole/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/integrations/runtime-swoole/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/runtime-swoole/config/runtime_swoole.php`
- [ ] Keys (dot):
  - [ ] `runtime_swoole.enabled` = false
  - [ ] `runtime_swoole.app_id` = 'web'
  - [ ] `runtime_swoole.host` = '127.0.0.1'
  - [ ] `runtime_swoole.port` = 9501
  - [ ] `runtime_swoole.workers` = 1
  - [ ] `runtime_swoole.max_requests` = 0
  - [ ] `runtime_swoole.graceful_shutdown.enabled` = true
  - [ ] `runtime_swoole.graceful_shutdown.timeout_ms` = 10000
- [ ] Rules:
  - [ ] `framework/packages/integrations/runtime-swoole/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\RuntimeSwoole\Runtime\SwooleKernelRunner`
  - [ ] registers: `Coretsia\RuntimeSwoole\Runtime\SwooleServerFactory`

#### Artifacts / outputs (if applicable)

- [ ] Reads:
  - [ ] validates header + payload schema (as defined by Kernel artifacts policy)
  - [ ] `skeleton/var/cache/<appId>/module-manifest.php`
  - [ ] `skeleton/var/cache/<appId>/config.php`
  - [ ] `skeleton/var/cache/<appId>/container.php`
  - [ ] `skeleton/var/cache/<appId>/routes.php`

- Artifact validation (MUST):
  - MUST reuse kernel artifact envelope/header policy (`_meta` + `payload`)
  - MUST validate schemaVersion and fingerprint fields as defined by kernel artifacts SSoT
  - MUST NOT introduce a parallel artifact format or re-implement stable JSON rules differently

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] integration test proves no context leak under long-running server loop

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `runtime.swoole.request`
- [ ] Metrics:
  - [ ] `runtime.request_total` (labels: `driver`, `outcome`)
  - [ ] `runtime.request_duration_ms` (labels: `driver`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`
- [ ] Logs:
  - [ ] startup/shutdown events + request summary only; no secret leakage

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/integrations/runtime-swoole/src/Exception/RuntimeSwooleException.php` — errorCode `CORETSIA_RUNTIME_SWOOLE_BOOT_FAILED`
  - [ ] `framework/packages/integrations/runtime-swoole/src/Exception/RuntimeSwooleException.php` — errorCode `CORETSIA_RUNTIME_SWOOLE_REQUEST_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/raw payload
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id`)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If metrics/spans/logs exist → `framework/packages/integrations/runtime-swoole/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  (asserts: noop adapters do not throw; extend for policy assertions as needed)
- [ ] If long-running reset discipline exists → `framework/packages/integrations/runtime-swoole/tests/Integration/LongRunningRequestsDoNotLeakContextTest.php`
  (asserts: no context leak under server loop)
- [ ] If graceful shutdown exists → `framework/packages/integrations/runtime-swoole/tests/Integration/GracefulShutdownStopsDeterministicallyTest.php`
  (asserts: SIGTERM handling and shutdown determinism)

#### Test harness / fixtures (when integration is needed)

- [ ] Harness:
  - [ ] long-running server loop + shutdown signal simulation

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/integrations/runtime-swoole/tests/Unit/ServerFactoryDeterministicConfigTest.php`
- Contract:
  - [ ] `framework/packages/integrations/runtime-swoole/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/runtime-swoole/tests/Integration/LongRunningRequestsDoNotLeakContextTest.php`
  - [ ] `framework/packages/integrations/runtime-swoole/tests/Integration/GracefulShutdownStopsDeterministicallyTest.php`
  - [ ] fails when worker_http enabled
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: rerun-no-diff (if outputs generated)
- [ ] Non-goals / out of scope:
  - [ ] Не додаємо middleware або endpoints (це runtime runner)
  - [ ] Не додаємо HTTP/2/3 логіку у код (це ops-level, див. 3.240.0)
  - [ ] Не змінюємо kernel/contracts
- [ ] Entrypoint invariant:
  - [ ] Entrypoint MUST call `RuntimeDriverGuard->assertCompatible()` before booting HttpKernel
- [ ] Runtime expectation:
  - [ ] Має бути вимкнено: `worker.enabled=false`, якщо `runtime_swoole` активний
- [ ] Acceptance scenario:
  - [ ] When server receives SIGTERM, then it stops accepting new requests and finishes in-flight requests deterministically.
- [ ] Usually present when enabled in presets/bundles:
  - [ ] runs with `platform/http` module enabled (and baseline modules by preset)

---

### 6.210.0 integrations/runtime-roadrunner (long-running adapter) (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.210.0"
owner_path: "framework/packages/integrations/runtime-roadrunner/"

package_id: "integrations/runtime-roadrunner"
composer: "coretsia/integrations-runtime-roadrunner"
kind: runtime
module_id: "integrations.runtime-roadrunner"

goal: "RoadRunner може обслуговувати багато запитів підряд, а кожен запит має свій UoW і не тече контекст/стан між запитами."
provides:
- "Runtime adapter для RoadRunner, який запускає `platform/http` HttpKernel без змін у `platform/http`."
- "Long-running safety: UoW boundary + reset discipline між requests (аналогічно `runtime-frankenphp`)."
- "Ops docs + skeleton entrypoint/glue для запуску в RoadRunner середовищі."

tags_introduced: []
config_roots_introduced:
- runtime_roadrunner

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
- docs/ssot/config-and-env.md
---

### Config-root ownership + registry
- This epic introduces config root `runtime_roadrunner` and therefore MUST:
  - add a row into `docs/ssot/config-roots.md` (owner = `integrations/runtime-roadrunner`),
  - ensure `config/runtime_roadrunner.php` returns a subtree (no wrapper root key).

### Runtime driver matrix / guard linkage (cemented)
- Entrypoint `skeleton/apps/web/public/roadrunner.php` MUST call:
  - `Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard->assertCompatible(...)`
  - **before** booting `platform/http` kernel.
- Guard decision is **pure config-keys decision** (no probing). This epic MUST NOT add ad-hoc checks outside the guard.
- This epic MUST explicitly document which keys are checked for incompatibilities, at minimum:
  - `worker.enabled` conflicts with RoadRunner HTTP runtime,
  - any other mutually-exclusive `http.*` driver key(s) as defined by `docs/ssot/runtime-drivers.md`.

### Artifacts reads (clarification)
- Runner does not produce artifacts, but it will **indirectly read** standard app artifacts via `platform/http` boot:
  - e.g. compiled container/config/routes artifacts located under `skeleton/var/cache/<appId>/...` (as owned by kernel/http).
- In this epic, “Artifacts: reads/writes” MUST reflect that as **indirect** reads (not “none”).

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - `framework/packages/platform/http/` — наявний HttpKernel (публічний контракт) який adapter викликає **без змін**.
  - `framework/packages/core/kernel/` — наявна runtime guard policy (Entrypoint MUST call `RuntimeDriverGuard->assertCompatible()` before booting HttpKernel).

- Required config roots/keys:
  - `worker.enabled` — MUST be `false`, якщо `runtime_roadrunner.enabled=true` (worker-mode вимкнений для цього runtime).
  - `runtime_roadrunner.*` — корінь вводиться цим епіком; форма ключів фіксується `config/rules.php`.

- Required tags:
  - N/A (entrypoint-based runner; discovery через tags не використовується)

- Required contracts / ports:
  - `Psr\Http\Message\ServerRequestInterface` — запит (PSR-7 surface).
  - `Psr\Http\Message\ResponseInterface` — відповідь (PSR-7 surface).
  - `Psr\Log\LoggerInterface` — error-only логування (без payload/headers/cookies).

- Spec invariants / non-goals (locked):
  - Не додаємо middleware або endpoints (це лише runner/adapter).
  - Не змінюємо `platform/http` public API (тільки використовуємо HttpKernelInterface).
  - Не реалізуємо “process manager” — RoadRunner цим займається сам.
  - Не логимо payload/headers/vars; не додаємо high-cardinality metric labels.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`
- `platform/http`

Forbidden:

- `platform/http-app`
- `platform/cli`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
- Foundation stable APIs:
  - N/A

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - routes: N/A (reuses existing `platform/http` HttpKernel; no new routes)
  - middleware slots/tags: N/A
- Kernel hooks/tags:
  - N/A (relies on existing kernel/http UoW + `kernel.reset` discipline as policy; no wiring here)
- Artifacts:
  - reads: `skeleton/var/cache/<appId>/*` (standard app artifacts as used by kernel/http)
  - writes: N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/runtime-roadrunner/src/Module/RuntimeRoadrunnerModule.php` — module entry (runtime)
- [ ] `framework/packages/integrations/runtime-roadrunner/src/Provider/RuntimeRoadrunnerServiceProvider.php` — DI wiring
- [ ] `framework/packages/integrations/runtime-roadrunner/src/Provider/RuntimeRoadrunnerServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/runtime-roadrunner/config/runtime_roadrunner.php` — config subtree (no repeated root)
- [ ] `framework/packages/integrations/runtime-roadrunner/config/rules.php` — config shape rules
- [ ] `framework/packages/integrations/runtime-roadrunner/README.md` — must include: Observability / Errors / Security-Redaction

- [ ] `framework/packages/integrations/runtime-roadrunner/src/Runtime/RoadrunnerKernelRunner.php` — adapter bridges RR request lifecycle to HttpKernel
- [ ] `framework/packages/integrations/runtime-roadrunner/src/Runtime/RoadrunnerServerRequestFactory.php` — deterministic PSR-7 request builder (no unsafe globals)
- [ ] `framework/packages/integrations/runtime-roadrunner/src/Runtime/RoadrunnerResponseEmitter.php` — safe response emission (deterministic headers policy)

- [ ] `framework/packages/integrations/runtime-roadrunner/src/Exception/RuntimeRoadrunnerException.php` — deterministic error codes
- [ ] `skeleton/apps/web/public/roadrunner.php` — RoadRunner entrypoint (thin glue; MUST call guard before boot)
- [ ] `docs/ops/runtime-roadrunner.md` — ops guide (enable module, RR config hints, troubleshooting, long-running reset notes)

- [ ] `framework/packages/integrations/runtime-roadrunner/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/integrations/runtime-roadrunner/tests/Unit/RoadrunnerRequestFactoryIsDeterministicTest.php`
- [ ] `framework/packages/integrations/runtime-roadrunner/tests/Integration/HundredSequentialRequestsNoContextLeakTest.php`
- [ ] `framework/tools/tests/Integration/Runtime/RoadrunnerSmokeTest.php`
- [ ] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/RoadrunnerHttpApp/config/modules.php` — enables `integrations.runtime-roadrunner`

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/runtime-roadrunner/composer.json`
- [ ] `framework/packages/integrations/runtime-roadrunner/src/Module/RuntimeRoadrunnerModule.php`
- [ ] `framework/packages/integrations/runtime-roadrunner/src/Provider/RuntimeRoadrunnerServiceProvider.php`
- [ ] `framework/packages/integrations/runtime-roadrunner/config/runtime_roadrunner.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/integrations/runtime-roadrunner/config/rules.php`
- [ ] `framework/packages/integrations/runtime-roadrunner/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/integrations/runtime-roadrunner/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/runtime-roadrunner/config/runtime_roadrunner.php`
- [ ] Keys (dot):
  - [ ] `runtime_roadrunner.enabled` = false
  - [ ] `runtime_roadrunner.headers.deterministic_order` = true
  - [ ] `runtime_roadrunner.request.max_body_bytes` = 10485760
- [ ] Rules:
  - [ ] `framework/packages/integrations/runtime-roadrunner/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Integrations\RuntimeRoadrunner\Runtime\RoadrunnerKernelRunner::class`

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
  - [ ] none
- [ ] Reset discipline:
  - [ ] relies on existing `kernel.reset` discipline invoked per UoW by kernel/http policy

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] reuse existing `http.request` span (no new high-cardinality attrs)
- [ ] Metrics:
  - [ ] reuse existing HTTP metrics (no new metric series required)
- [ ] Logs:
  - [ ] errors only; no headers/cookies/body; no rr internal payload dumps

#### Errors

- [ ] Exceptions introduced:
  - [ ] `\Coretsia\Integrations\RuntimeRoadrunner\Exception\RuntimeRoadrunnerException` — errorCode `CORETSIA_RUNTIME_RR_REQUEST_BUILD_FAILED`
  - [ ] `\Coretsia\Integrations\RuntimeRoadrunner\Exception\RuntimeRoadrunnerException` — errorCode `CORETSIA_RUNTIME_RR_RESPONSE_EMIT_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] request headers/cookies/body, rendered content, rr internals
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php`
  (N/A — no Context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php`
  (policy via kernel/http; доказ через `tests/Integration/HundredSequentialRequestsNoContextLeakTest.php`)
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  (N/A — adapter не вводить нових signals; reuse існуючих)
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  (доказ через error-only logging policy + integration smoke; окремий redaction layer не вводиться)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/RoadrunnerHttpApp/config/modules.php`
- Fake adapters:
  - N/A (RR smoke/E2E harness)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/integrations/runtime-roadrunner/tests/Unit/RoadrunnerRequestFactoryIsDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/integrations/runtime-roadrunner/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/runtime-roadrunner/tests/Integration/HundredSequentialRequestsNoContextLeakTest.php`
  - [ ] `framework/tools/tests/Integration/Runtime/RoadrunnerSmokeTest.php`
  - [ ] fails when worker_http enabled
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Entrypoint calls `RuntimeDriverGuard->assertCompatible()` before HttpKernel boot
- [ ] Determinism: request build + response emission stable for same inputs
- [ ] Verification tests present where applicable (sequential no-leak proof)
- [ ] Docs updated:
  - [ ] `framework/packages/integrations/runtime-roadrunner/README.md`
  - [ ] `docs/ops/runtime-roadrunner.md`
- [ ] What problem this epic solves
  - [ ] Дає runtime adapter для RoadRunner, який запускає `platform/http` HttpKernel **без змін** у `platform/http`.
  - [ ] Гарантує long-running safety: **UoW boundary + reset discipline** між requests (аналогічно `runtime-frankenphp`) .
  - [ ] Дає ops docs + skeleton entrypoint/glue для запуску в RoadRunner середовищі.
- [ ] Non-goals / out of scope
  - [ ] Не додаємо middleware або endpoints (це лише runner/adapter).
  - [ ] Не змінюємо `platform/http` public API (тільки використовуємо HttpKernelInterface).
  - [ ] Не реалізуємо “process manager” — RoadRunner цим займається сам.
  - [ ] Не логимо payload/headers/vars; не додаємо high-cardinality metric labels.
- [ ] Entrypoint MUST call `RuntimeDriverGuard->assertCompatible()` before booting HttpKernel
- [ ] Має бути вимкнено: worker.enabled=false, якщо runtime_roadrunner активний
- [ ] RoadRunner може обслуговувати багато запитів підряд, а кожен запит має свій UoW і не тече контекст/стан між запитами.
- [ ] When 100 sequential HTTP requests are handled via RoadRunner adapter, then harness shows no context leak and all responses are correct.
- [ ] Usually present when enabled in presets/bundles:
  - [ ] runs with `platform/http` module enabled (and its required baseline modules by preset)

---

### 6.220.0 Runtime ops: HTTP/2 + HTTP/3 enablement guide (MUST) [DOC]

---
type: docs
phase: 6+
epic_id: "6.220.0"
owner_path: "docs/ops/http2-http3.md"

goal: "Документ відповідає “як ввімкнути HTTP/2/3” без будь-яких змін у коді framework."
provides:
- "Один канонічний ops документ “як ввімкнути HTTP/2 та HTTP/3” для популярних проксі/серверів."
- "Правило: H2/H3 — ops concern і не потребує змін у framework code."
- "Troubleshooting/безпекові нотатки (TLS, ALPN, proxy headers) без витоку секретів."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

## Phase compliance addendum (MUST)

- This is DOC-only: MUST NOT introduce new tags, config roots, artifacts, or implied framework code changes.
- Examples MUST NOT contain secrets/certs/private keys; use placeholders only.
- Any mention of middleware/classes MUST be phrased as conceptual unless the exact FQCN is already implemented in the repo.
- Doc SHOULD reference redaction/observability constraints:
  - `docs/ssot/observability-and-errors.md`
  - `docs/ssot/di-tags-and-middleware-ordering.md` (ordering/taxonomy context)

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
  - N/A

- Spec invariants / non-goals (locked):
  - Не додаємо код у `platform/http` для H2/H3.
  - Не додаємо runtime adapters (це окремі runtime-епіки).
  - Не включаємо vendor-specific “magic” у framework.

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

N/A

### Deliverables (MUST)

#### Creates

- [ ] `docs/ops/http2-http3.md` — includes:
  - Caddy example (HTTP/2 + HTTP/3)
  - Nginx example (HTTP/2)
  - Envoy example (HTTP/2/H3 conceptual)
  - notes: TLS/ALPN, proxy headers, `TrustedProxyMiddleware` interaction (conceptual)
  - rule: no framework code changes

- [ ] `framework/tools/tests/Integration/Docs/OpsHttp2Http3DocExistsTest.php` (optional)
- [ ] `framework/tools/tests/Integration/Docs/HttpPerformanceDocExistsTest.php` (optional)

#### Modifies

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
  - [ ] no secrets/certs/private keys in examples (use placeholders)
- [ ] Allowed:
  - [ ] safe placeholders + guidance

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` (N/A)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` (N/A)
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php` (N/A)
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  (doc-level redaction enforced by review + optional doc-exists test)

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit/Contract:
  - N/A
- Integration:
  - [ ] `framework/tools/tests/Integration/Docs/OpsHttp2Http3DocExistsTest.php` (optional)
  - [ ] `framework/tools/tests/Integration/Docs/HttpPerformanceDocExistsTest.php` (optional)
- Gates/Arch:
  - N/A

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] Docs updated:
  - [ ] `docs/ops/http2-http3.md` complete and accurate
- [ ] Acceptance scenario:
  - [ ] When a user asks how to enable H3, then the doc provides a ready-to-apply example and notes that no framework changes are required.
- [ ] What problem this epic solves
  - [ ] Дає один канонічний ops документ “як ввімкнути HTTP/2 та HTTP/3” для популярних проксі/серверів
  - [ ] Фіксує правило: H2/H3 — це ops concern, не потребує змін у framework code
  - [ ] Дає troubleshooting/безпекові нотатки (TLS, ALPN, proxy headers) без витоку секретів
- [ ] Non-goals / out of scope
  - [ ] Не додаємо код у `platform/http` для H2/H3
  - [ ] Не додаємо runtime adapters (це 3.210.0/3.220.0)
  - [ ] Не включаємо vendor-specific “magic” у framework
- [ ] Usually present when enabled in presets/bundles:
  - [ ] будь-який runtime (`php -S`, FrankenPHP, Swoole, FPM) може бути за проксі (Caddy/Nginx/Envoy)
- [ ] Документ відповідає “як ввімкнути HTTP/2/3” без будь-яких змін у коді framework.
- [ ] When a user asks how to enable H3, then the doc provides a ready-to-apply example and notes that no framework changes are required.

---

### 6.230.0 coretsia/etl (pipelines + CLI) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.230.0"
owner_path: "framework/packages/platform/etl/"

package_id: "platform/etl"
composer: "coretsia/platform-etl"
kind: runtime
module_id: "platform.etl"

goal: "ETL pipeline можна запустити детерміновано з CLI, отримати стабільний report/status і не мати витоку state між runs."
provides:
- "ETL pipeline runner з детермінованими кроками/репортами, який працює як CLI UoW (KernelRuntime)."
- "Registry (list/run/status/retry-failed) без compile-time залежності на `platform/cli` (reverse discovery via tag)."
- "Reference repositories (file-based) + optional DB integration через contracts ports (без hard deps на integrations)."

tags_introduced: []
config_roots_introduced:
- etl

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
- docs/ssot/config-and-env.md
---

## Phase compliance addendum (MUST)

### Naming & namespace law (Prelude)
- Package path is `framework/packages/platform/etl`, so namespaces MUST be:
  - `Coretsia\Platform\Etl\...` (NOT `Coretsia\Etl\...`).
- All FQCNs in this epic (commands, services, exceptions) MUST follow that mapping.

### CLI boundary (ports-only contracts)
- ETL CLI commands MUST implement:
  - `Coretsia\Contracts\Cli\Command\CommandInterface`
  - and interact only via `Coretsia\Contracts\Cli\Input\InputInterface` + `Coretsia\Contracts\Cli\Output\OutputInterface`.
- This package MUST NOT depend on `platform/cli` at compile-time. Discovery is via tag usage only.

### Tag usage (SSoT)
- Tag string literals MUST NOT be duplicated. Use the owner constant:
  - `Coretsia\Platform\Cli\Provider\Tags::CLI_COMMAND` (or equivalent owner constant defined by `platform/cli`).
- Tag meta schema MUST be stable and minimal; for `cli.command` MUST include:
  - `{"name": "<command-name>"}` only (no extra keys unless owner SSoT permits).

### Runtime file outputs: artifact envelope + registry
- Any persisted `*.json` state with schemaVersion MUST follow the canonical artifact envelope:
  - `{ "_meta": <ArtifactHeader>, "payload": <schema> }`
  - deterministic bytes: stable map key sort `strcmp`, lists preserve order, LF-only, final newline, floats forbidden.
- This epic introduces artifacts and therefore MUST update `docs/ssot/artifacts.md` with:
  - `etl_run@1`, `etl_status@1` (owner = `platform/etl`).

### Deterministic runtime IO (no tools dependency)
- Runtime code MUST NOT use `framework/tools/**` helpers (Phase 0 boundary).
- If atomic write helpers are needed, they MUST live inside this package (or reuse a runtime-safe helper from `core/foundation`/`core/kernel` if already exists).

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - `framework/packages/core/kernel/` — CLI UoW model (KernelRuntime) + ContextKeys invariants.
  - `framework/packages/platform/cli/` — runtime expectation: саме цей пакет/модуль виконує discovery `cli.command` і обгортає виконання як CLI UoW (але **forbidden** як compile-time dep для цього пакета).

- Required config roots/keys:
  - `etl.*` — корінь вводиться цим епіком; форма ключів фіксується `config/rules.php`.

- Required tags:
  - `cli.command` — має існувати як механізм discovery (owner поза цим епіком); цей пакет лише tag usage.

- Required contracts / ports:
  - `Psr\Log\LoggerInterface` — summary-only logs (no payload).
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — Context reads.
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — spans.
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics.

- Spec invariants / non-goals (locked):
  - Не робимо “монолітний data platform” (тільки ETL runner + reference storage).
  - Не логимо payload/records (тільки counts + hashes/len).
  - Не вводимо нові contracts ports у цьому епіку.
  - Cutline impact: none.

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
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface` (indirect via kernel UoW model)
- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - `etl:list` → `platform/etl` `framework/packages/platform/etl/src/Console/EtlListCommand.php`
  - `etl:run` → `platform/etl` `framework/packages/platform/etl/src/Console/EtlRunCommand.php`
  - `etl:status` → `platform/etl` `framework/packages/platform/etl/src/Console/EtlStatusCommand.php`
  - `etl:retry-failed` → `platform/etl` `framework/packages/platform/etl/src/Console/EtlRetryFailedCommand.php`
- HTTP:
  - N/A
- Kernel hooks/tags:
  - `cli.command` (tag usage; executed under CLI UoW by `platform/cli` as runtime policy)
- Artifacts:
  - reads: `skeleton/var/etl/<appId>/...` (runtime state; not fingerprinted)
  - writes: `skeleton/var/etl/<appId>/...` (runtime state; not fingerprinted)

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/etl/src/Module/EtlModule.php`
- [ ] `framework/packages/platform/etl/src/Provider/EtlServiceProvider.php`
- [ ] `framework/packages/platform/etl/src/Provider/EtlServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/etl/config/etl.php`
- [ ] `framework/packages/platform/etl/config/rules.php`
- [ ] `framework/packages/platform/etl/README.md` (must include: Observability / Errors / Security-Redaction)

- [ ] `framework/packages/platform/etl/src/Pipeline/PipelineInterface.php` — pipeline definition (id + steps)
- [ ] `framework/packages/platform/etl/src/Pipeline/StepInterface.php` — step API (no payload logging)
- [ ] `framework/packages/platform/etl/src/Runner/PipelineRunner.php` — deterministic execution + report
- [ ] `framework/packages/platform/etl/src/Registry/PipelineRegistry.php` — deterministic listing (`DeterministicOrder`)
- [ ] `framework/packages/platform/etl/src/Report/PipelineRunReport.php` — stable VO shape (json-like)
- [ ] `framework/packages/platform/etl/src/State/RunStateRepository.php` — status + failed runs storage (file reference)
- [ ] `framework/packages/platform/etl/src/Console/EtlListCommand.php`
- [ ] `framework/packages/platform/etl/src/Console/EtlRunCommand.php`
- [ ] `framework/packages/platform/etl/src/Console/EtlStatusCommand.php`
- [ ] `framework/packages/platform/etl/src/Console/EtlRetryFailedCommand.php`
- [ ] `framework/packages/platform/etl/src/Observability/EtlInstrumentation.php` — spans/metrics helper
- [ ] `docs/architecture/etl.md` — pipelines, determinism rules, storage paths, safe logging

- [ ] `framework/packages/platform/etl/src/Exception/EtlException.php` — deterministic error codes:
  - `CORETSIA_ETL_PIPELINE_NOT_FOUND`
  - `CORETSIA_ETL_STEP_FAILED`
  - `CORETSIA_ETL_STATE_IO_FAILED`

- [ ] `framework/packages/platform/etl/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/platform/etl/tests/Unit/PipelineOrderDeterministicTest.php`
- [ ] `framework/packages/platform/etl/tests/Unit/ReportShapeDeterministicTest.php`
- [ ] `framework/packages/platform/etl/tests/Integration/EtlRunIsWrappedAsCliUowNoLeakTest.php`
- [ ] `framework/packages/platform/etl/tests/Integration/RetryFailedIsDeterministicTest.php`
- [ ] `framework/packages/platform/etl/tests/Integration/NoPayloadLeakInLogsTest.php`
- [ ] `framework/packages/platform/etl/tests/Integration/Cli/EtlCommandsWorkInHybridPresetTest.php` (optional)

#### Modifies

- [ ] `framework/packages/core/kernel/tests/Fixtures/HybridApp/config/modules.php` (if needed) — adds module enablement for E2E wiring
- [ ] `docs/ssot/artifacts.md` - add:
  - ## Additional artifacts (Phase 6+)
    - > These artifacts MUST follow the canonical envelope: `{ "_meta": <header>, "payload": <schema-specific> }`.
    - > Producers MUST generate deterministic bytes (stable map key order `strcmp`, lists preserve order, LF-only, final newline).

  - ### etl_run@1 (owner: platform/etl)
    - **name:** `etl_run`
    - **schemaVersion:** 1
    - **format:** JSON (envelope)
    - **path pattern:** `skeleton/var/etl/<appId>/runs/<runId>.json`
    - **payload:** stable json-like report of a single ETL run (counts, step outcomes, no raw records/payloads).
    - **security:** MUST NOT include dataset rows, secrets, PII; only safe ids, counts, `hash/len` where needed.

  - ### etl_status@1 (owner: platform/etl)
    - **name:** `etl_status`
    - **schemaVersion:** 1
    - **format:** JSON (envelope)
    - **path:** `skeleton/var/etl/<appId>/status.json`
    - **payload:** stable snapshot (last run id, counts, last outcomes), no secrets.

  - ### preload_plan@1 (owner: devtools/preload)
    - **name:** `preload_plan`
    - **schemaVersion:** 1
    - **format:** JSON (envelope)
    - **path:** `skeleton/var/cache/<appId>/preload.plan.json`
    - **payload:** deterministic plan metadata (repo-relative file list, counts, excludes). No file contents.

  - > Note: `preload.php` is an executable preload script (PHP), not a Coretsia JSON artifact.
  - > Its determinism is enforced separately (LF-only, stable ordering, no timestamps/abs paths).

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/etl/composer.json`
- [ ] `framework/packages/platform/etl/src/Module/EtlModule.php`
- [ ] `framework/packages/platform/etl/src/Provider/EtlServiceProvider.php`
- [ ] `framework/packages/platform/etl/config/etl.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/platform/etl/config/rules.php`
- [ ] `framework/packages/platform/etl/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/etl/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/etl/config/etl.php`
- [ ] Keys (dot):
  - [ ] `etl.enabled` = false
  - [ ] `etl.storage.path` = 'skeleton/var/etl'
  - [ ] `etl.max_failed_records` = 1000
  - [ ] `etl.cli.enabled` = true
  - [ ] `etl.pipelines` = []  # optional map pipelineId => class/serviceId
- [ ] Rules:
  - [ ] `framework/packages/platform/etl/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (tag `cli.command` is owned outside this package)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Etl\Console\EtlListCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"etl:list"}`
  - [ ] registers: `\Coretsia\Etl\Console\EtlRunCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"etl:run"}`
  - [ ] registers: `\Coretsia\Etl\Console\EtlStatusCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"etl:status"}`
  - [ ] registers: `\Coretsia\Etl\Console\EtlRetryFailedCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"etl:retry-failed"}`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `skeleton/var/etl/<appId>/runs/<runId>.json` (schemaVersion, deterministic bytes; no secrets)
  - [ ] `skeleton/var/etl/<appId>/status.json` (stable status snapshot)
- [ ] Reads:
  - [ ] validates schemaVersion + payload schema for the same file(s)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] `PipelineRunner` internal state implements `ResetInterface` if stateful
  - [ ] all stateful services tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `etl.run` (attrs: pipeline id as span attr, outcome)
  - [ ] `etl.step` (attrs: step name as span attr, outcome)
- [ ] Metrics:
  - [ ] `etl.run_total` (labels: `outcome`)
  - [ ] `etl.run_duration_ms` (labels: `outcome`)
  - [ ] `etl.step_total` (labels: `operation`, `outcome`)
  - [ ] `etl.step_duration_ms` (labels: `operation`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `kind/op/uow_type→operation`
- [ ] Logs:
  - [ ] run summary (counts only), no row payloads, no PII

#### Errors

- [ ] Exceptions introduced:
  - [ ] `\Coretsia\Etl\Exception\EtlException` — errorCode `CORETSIA_ETL_PIPELINE_NOT_FOUND`
  - [ ] `\Coretsia\Etl\Exception\EtlException` — errorCode `CORETSIA_ETL_STEP_FAILED`
  - [ ] `\Coretsia\Etl\Exception\EtlException` — errorCode `CORETSIA_ETL_STATE_IO_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) (CLI renders deterministic error output)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] dataset rows/payloads, secrets, tokens
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id`)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php`
  (N/A — no Context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php`
  (covered indirectly; execution+no-leak proof via `tests/Integration/EtlRunIsWrappedAsCliUowNoLeakTest.php`)
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  (covered by unit+integration: `ReportShapeDeterministicTest.php` + `NoPayloadLeakInLogsTest.php`)
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  (covered by `tests/Integration/NoPayloadLeakInLogsTest.php`)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/HybridApp/config/modules.php` (if needed)
- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger capture events for assertions (via tests above)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/etl/tests/Unit/PipelineOrderDeterministicTest.php`
  - [ ] `framework/packages/platform/etl/tests/Unit/ReportShapeDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/etl/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/etl/tests/Integration/EtlRunIsWrappedAsCliUowNoLeakTest.php`
  - [ ] `framework/packages/platform/etl/tests/Integration/RetryFailedIsDeterministicTest.php`
  - [ ] `framework/packages/platform/etl/tests/Integration/NoPayloadLeakInLogsTest.php`
  - [ ] `framework/packages/platform/etl/tests/Integration/Cli/EtlCommandsWorkInHybridPresetTest.php` (optional)
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: rerun same inputs → same report bytes
- [ ] Docs updated:
  - [ ] `framework/packages/platform/etl/README.md`
  - [ ] `docs/architecture/etl.md`
- [ ] Acceptance scenario:
  - [ ] When `coretsia etl:run <pipeline>` is executed twice with same inputs, then the run report is deterministic and no context leaks between runs.
- [ ] What problem this epic solves
  - [ ] Дає ETL pipeline runner з детермінованими кроками/репортами, який працює як CLI UoW (KernelRuntime)
  - [ ] Дає registry (list/run/status/retry-failed) без залежності на `platform/cli` (reverse discovery via tag)
  - [ ] Дає reference repositories (file-based) + optional DB integration через contracts ports (без hard deps на integrations)
- [ ] Non-goals / out of scope
  - [ ] Не робимо “монолітний data platform” (тільки ETL runner + reference storage)
  - [ ] Не логимо payload/records (тільки counts + hashes/len)
  - [ ] Не вводимо нові contracts ports у цьому епіку
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/cli` is present to discover and run `etl:*` commands via `cli.command`
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] Discovery / wiring is via tags:
  - [ ] `cli.command`
- [ ] ETL pipeline можна запустити детерміновано з CLI, отримати стабільний report/status і не мати витоку state між runs.
- [ ] When `coretsia etl:run <pipeline>` is executed twice with same inputs, then the run report is deterministic and no context leaks between runs.

---

### 6.240.0 coretsia/preload (deterministic preload generator + CLI) (MUST) [TOOLING]

---
type: package
phase: 6+
epic_id: "6.240.0"
owner_path: "framework/packages/devtools/preload/"

package_id: "devtools/preload"
composer: "coretsia/devtools-preload"
kind: runtime
module_id: "devtools.preload"

goal: "`preload:dump` генерує preload artifact детерміновано, а повторний запуск дає ідентичні байти (no diff)."
provides:
- "Deterministic preload generator для PHP (stable across OS) з rerun-no-diff."
- "CLI команда `preload:dump`, яка генерує preload script як deterministic artifact."
- "Reuse `devtools/internal-toolkit` для slug/path/json (no duplicate logic)."

tags_introduced: []
config_roots_introduced:
- preload

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
- docs/ssot/config-and-env.md
---

## Phase compliance addendum (MUST)

### Package identity (Prelude) + dev-only policy
- This epic is `[TOOLING]` and MUST be treated as dev-only tooling:
  - install policy MUST be `require-dev` in `skeleton/composer.json` (no implicit prod enablement).
- `kind` MUST be `tooling` (or the repo’s canonical tooling kind), not `runtime`, to avoid polluting production presets.

### Naming & namespace law (Prelude)
- Package path is `framework/packages/devtools/preload`, so namespaces MUST be:
  - `Coretsia\Devtools\Preload\...` (NOT `Coretsia\Preload\...`).
- All FQCNs in this epic (command, generator, writer, exception) MUST follow that mapping.

### Compile-time deps completeness
- This package uses contracts observability ports, therefore compile-time deps MUST include:
  - `core/contracts`
  - `core/foundation`
  - `core/kernel`
  - plus `coretsia/internal-toolkit` (tooling-only helper) if that is the canonical package name.
- `platform/cli` remains forbidden as compile-time dep; discovery is via tag usage only.

### Artifact policy (avoid conflicting definitions)
- `preload.php` is an executable preload script (PHP) and MUST NOT pretend to be a Coretsia JSON artifact envelope.
- If metadata is required, write it as a separate JSON artifact:
  - `skeleton/var/cache/<appId>/preload.plan.json` (artifact name `preload_plan@1`)
  - and register it in `docs/ssot/artifacts.md` (owner = `devtools/preload`).

### Deterministic scanning rules (tooling-safe)
- Preload plan builder MUST:
  - use repo-relative normalized paths (forward slashes),
  - stable ordering `strcmp`,
  - hard-fail on symlinks (no traversal),
  - exclude configured paths deterministically,
  - produce LF-only output with final newline for any emitted files.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - `framework/packages/devtools/internal-toolkit/` — canonical helpers (slug/path/json) для deterministic output.
  - `framework/packages/core/kernel/` — kernel/runtime environment for CLI UoW (runtime expectation via `platform/cli`).
  - `framework/packages/platform/cli/` — runtime expectation: discovery `cli.command` та виконання команди (але **forbidden** як compile-time dep).

- Required config roots/keys:
  - `preload.*` — корінь вводиться цим епіком; форма ключів фіксується `config/rules.php`.

- Required tags:
  - `cli.command` — має існувати як механізм discovery (owner поза цим епіком).

- Required contracts / ports:
  - `Psr\Log\LoggerInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)

- Spec invariants / non-goals (locked):
  - Не робимо runtime “optimizer” у `platform/http` або kernel (тільки tooling output).
  - Не додаємо залежність на `platform/cli` (reverse discovery через `cli.command`).
  - Не додаємо випадковості (no wall-clock, no nondeterministic FS order).
  - Cutline impact: none.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`
- `coretsia/internal-toolkit`  # tooling-only helper; generator-time only

Forbidden:

- `platform/cli`
- `platform/http`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)
- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - `preload:dump` → `devtools/preload` `framework/packages/devtools/preload/src/Console/PreloadDumpCommand.php`
- HTTP:
  - N/A
- Kernel hooks/tags:
  - `cli.command` (tag usage; executed under CLI UoW by `platform/cli` as runtime policy)
- Artifacts:
  - writes: `skeleton/var/cache/<appId>/preload.php`
  - reads: N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/devtools/preload/src/Module/PreloadModule.php`
- [ ] `framework/packages/devtools/preload/src/Provider/PreloadServiceProvider.php`
- [ ] `framework/packages/devtools/preload/src/Provider/PreloadServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/devtools/preload/config/preload.php`
- [ ] `framework/packages/devtools/preload/config/rules.php`
- [ ] `framework/packages/devtools/preload/README.md` (must include: Observability / Errors / Security-Redaction)

- [ ] `framework/packages/devtools/preload/src/Console/PreloadDumpCommand.php` — CLI command to generate preload artifact
- [ ] `framework/packages/devtools/preload/src/Generator/PreloadPlanBuilder.php` — deterministic scan of classes/files (no symlinks; stable order)
- [ ] `framework/packages/devtools/preload/src/Generator/PreloadScriptRenderer.php` — deterministic PHP output (stable ordering)
- [ ] `framework/packages/devtools/preload/src/Artifacts/PreloadArtifactWriter.php` — atomic write + header schemaVersion
- [ ] `framework/packages/devtools/preload/src/Security/Redaction.php` — safe output helpers (no secrets)
- [ ] `docs/ops/preload.md` — how to generate + how to enable in runtimes (FPM/FrankenPHP/Swoole), without code changes

- [ ] `framework/packages/devtools/preload/src/Exception/PreloadException.php` — deterministic error codes:
  - `CORETSIA_PRELOAD_PLAN_TOO_LARGE`
  - `CORETSIA_PRELOAD_WRITE_FAILED`
  - `CORETSIA_PRELOAD_DETERMINISM_VIOLATION`

- [ ] `framework/packages/devtools/preload/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/devtools/preload/tests/Unit/PlanBuilderDeterministicOrderTest.php`
- [ ] `framework/packages/devtools/preload/tests/Unit/RendererProducesDeterministicPhpTest.php`
- [ ] `framework/packages/devtools/preload/tests/Integration/RerunNoDiffGeneratesSameBytesTest.php`
- [ ] `framework/packages/devtools/preload/tests/Integration/ArtifactHeaderShapeIsValidTest.php`
- [ ] `framework/packages/platform/cli/tests/Integration/Cli/PreloadDumpCommandWritesArtifactTest.php` (optional)

#### Modifies

- [ ] `framework/packages/core/kernel/tests/Fixtures/MicroApp/config/modules.php` (if needed) — adds module enablement for E2E wiring

#### Package skeleton (if type=package)

- [ ] `framework/packages/devtools/preload/composer.json`
- [ ] `framework/packages/devtools/preload/src/Module/PreloadModule.php`
- [ ] `framework/packages/devtools/preload/src/Provider/PreloadServiceProvider.php`
- [ ] `framework/packages/devtools/preload/config/preload.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/devtools/preload/config/rules.php`
- [ ] `framework/packages/devtools/preload/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/devtools/preload/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/devtools/preload/config/preload.php`
- [ ] Keys (dot):
  - [ ] `preload.enabled` = false
  - [ ] `preload.app_id` = 'web'
  - [ ] `preload.output.path` = 'skeleton/var/cache/<appId>/preload.php'
  - [ ] `preload.plan.exclude_paths` = ['skeleton/var','vendor/tests']
  - [ ] `preload.plan.max_files` = 5000
- [ ] Rules:
  - [ ] `framework/packages/devtools/preload/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (tag `cli.command` is owned outside this package)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Preload\Console\PreloadDumpCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"preload:dump"}`
  - [ ] registers: `\Coretsia\Preload\Artifacts\PreloadArtifactWriter::class`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `skeleton/var/cache/<appId>/preload.php` (schemaVersion, deterministic bytes)
- [ ] Reads:
  - [ ] validates header + payload schema for the same file(s)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (optional for logs)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] generator caches (if any) implement `ResetInterface`
  - [ ] all stateful services tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `preload.dump`
- [ ] Metrics:
  - [ ] `preload.dump_total` (labels: `outcome`)
  - [ ] `preload.dump_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] summary only (files count, output path), no contents

#### Errors

- [ ] Exceptions introduced:
  - [ ] `\Coretsia\Preload\Exception\PreloadException` — errorCode `CORETSIA_PRELOAD_PLAN_TOO_LARGE`
  - [ ] `\Coretsia\Preload\Exception\PreloadException` — errorCode `CORETSIA_PRELOAD_WRITE_FAILED`
  - [ ] `\Coretsia\Preload\Exception\PreloadException` — errorCode `CORETSIA_PRELOAD_DETERMINISM_VIOLATION`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) (CLI prints deterministic error output)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] any secret env/config values; never print file contents
- [ ] Allowed:
  - [ ] repo-relative paths only; `hash/len` helpers

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php`
  (N/A — no Context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php`
  (covered by determinism+rerun tests; reset is required for no-leak in long CLI sessions)
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  (covered by integration tests validating safe summary behavior + no content leak)
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  (covered by policy + integration coverage; redaction helpers introduced in `src/Security/Redaction.php`)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/MicroApp/config/modules.php` (if needed)
- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger capture events for assertions (via tests above)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/devtools/preload/tests/Unit/PlanBuilderDeterministicOrderTest.php`
  - [ ] `framework/packages/devtools/preload/tests/Unit/RendererProducesDeterministicPhpTest.php`
- Contract:
  - [ ] `framework/packages/devtools/preload/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/devtools/preload/tests/Integration/RerunNoDiffGeneratesSameBytesTest.php`
  - [ ] `framework/packages/devtools/preload/tests/Integration/ArtifactHeaderShapeIsValidTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/Cli/PreloadDumpCommandWritesArtifactTest.php` (optional)
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Determinism: rerun-no-diff for generated preload artifact (cross-OS)
- [ ] Verification tests present where applicable
- [ ] Docs updated:
  - [ ] `framework/packages/devtools/preload/README.md`
  - [ ] `docs/ops/preload.md`
- [ ] Acceptance scenario:
  - [ ] When `preload:dump` is run twice on the same codebase, then the generated preload file is byte-identical across OS.
- [ ] What problem this epic solves
  - [ ] Дає deterministic preload generator для PHP (stable across OS) з rerun-no-diff
  - [ ] Дає CLI команду `preload:dump`, яка генерує preload script як deterministic artifact
  - [ ] Reuse `devtools/internal-toolkit` для slug/path/json (no duplicate logic)
- [ ] Non-goals / out of scope
  - [ ] Не робимо runtime “optimizer” у `platform/http` або kernel (тільки tooling output)
  - [ ] Не додаємо залежність на `platform/cli` (reverse discovery через `cli.command`)
  - [ ] Не додаємо випадковості (no wall-clock, no nondeterministic FS order)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/cli` is present to discover and run `preload:dump` via `cli.command`
- [ ] Discovery / wiring is via tags:
  - [ ] `cli.command`
- [ ] `preload:dump` генерує preload artifact детерміновано, а повторний запуск дає ідентичні байти (no diff).
- [ ] When `preload:dump` is run twice on the same codebase, then the generated preload file is byte-identical across OS.

---

### 6.250.0 HTTP response performance middlewares catalog (Compression + ETag + Cache-Headers) (SHOULD) [DOC]

---
type: docs
phase: 6+
epic_id: "6.250.0"
owner_path: "docs/architecture/http-performance.md"

goal: "Є один документ, який відповідає “де живе perf middleware, у який slot/priority воно вбудовується, як вимкнути/override, і які інваріанти”."
provides:
- "Канонічний catalog “response shaping/perf” middleware у `http.middleware.system_post` (Compression, ETag, CacheHeaders)."
- "Опис порядку/пріоритетів (900/800/700) і взаємодії (ETag до Compression, CacheHeaders outer в slot)."
- "Policy: determinism + redaction, як вимкнути/override через manual mode."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

## Phase compliance addendum (MUST)

- This is DOC-only: MUST NOT claim implementation exists unless it already does.
  - If middleware FQCNs or config keys are not implemented yet, mark them explicitly as "planned / illustrative".
- Any slot/priority statements MUST align with the canonical middleware taxonomy:
  - `docs/ssot/http-middleware-catalog.md` (single source of truth)
  - `docs/ssot/di-tags-and-middleware-ordering.md`
- No new observability label keys are allowed; doc MUST reference the allowlist:
  - `docs/ssot/observability.md` (labels allowlist)
- Examples MUST use placeholders; no secrets/PII/payloads.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A (doc-first epic; реалізація middleware може з’являтися в окремих імплементаційних епіках)

- Required config roots/keys:
  - N/A (doc описує можливі ключі/override; самих ключів цей епік не вводить)

- Required tags:
  - `http.middleware.system_post` — runtime expectation: slot/tag існує у `platform/http` (owner поза цим епіком)

- Required contracts / ports:
  - N/A

- Spec invariants / non-goals (locked):
  - Не додає нового коду в `platform/http` (це doc-only).
  - Не змінює stack model або `platform/http` dependency law.
  - Не вводить нові metric label keys (використовує allowlist).
  - Cutline impact: none.

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
  - N/A
- HTTP:
  - middleware slots/tags (catalog, reference):
    - `http.middleware.system_post`:
      - `\Coretsia\Http\Middleware\CacheHeadersMiddleware::class` priority `900` (if enabled)
      - `\Coretsia\Http\Middleware\EtagMiddleware::class` priority `800` (if enabled)
      - `\Coretsia\Http\Middleware\CompressionMiddleware::class` priority `700` (if enabled)
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - reads: none
  - writes: none

### Deliverables (MUST)

#### Creates

- [ ] `docs/architecture/http-performance.md` — canonical notes:
  - why perf middlewares are `system_post`
  - ordering rationale (CacheHeaders → ETag → Compression)
  - deterministic policy + redaction rules
  - manual override example (`http.middleware.auto.system_post=false` + explicit list)

- [ ] `framework/tools/tests/Integration/Docs/HttpPerformanceDocExistsTest.php` (optional)

#### Modifies

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
  - [ ] secrets/PII/raw payloads in examples (використовувати placeholders)
- [ ] Allowed:
  - [ ] safe placeholders + redaction guidance

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` (N/A)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` (N/A)
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php` (N/A)
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  (doc-level redaction enforced by review + optional doc-exists test)

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit/Contract:
  - N/A
- Integration:
  - [ ] `framework/tools/tests/Integration/Docs/HttpPerformanceDocExistsTest.php` (optional)
- Gates/Arch:
  - N/A

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] Docs updated:
  - [ ] `docs/architecture/http-performance.md` complete and accurate
- [ ] Acceptance scenario:
  - [ ] When a developer enables `http.etag.enabled`, then they can find its slot/priority, config keys, and override steps in one place.
- [ ] What problem this epic solves
  - [ ] Фіксує канонічний набір “response shaping/perf” middleware у `http.middleware.system_post` (Compression, ETag, CacheHeaders)
  - [ ] Описує порядок/пріоритети з middleware catalog (900/800/700) і як вони взаємодіють (ETag до Compression, CacheHeaders outer in slot)
  - [ ] Документує policy: deterministic поведінка, redaction, як вимкнути/override через manual mode
- [ ] Non-goals / out of scope
  - [ ] Не додає нового коду в `platform/http`
  - [ ] Не змінює stack model або `platform/http` dependency law
  - [ ] Не вводить нові metric label keys (використовує allowlist)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/http` executes `http.middleware.system_post`
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] Discovery / wiring is via tags:
  - [ ] `http.middleware.system_post`
- [ ] Є один документ, який відповідає “де живе perf middleware, у який slot/priority воно вбудовується, як вимкнути/override, і які інваріанти”.
- [ ] When a developer enables `http.etag.enabled`, then they can find its slot/priority, config keys, and override steps in one place.

---

### 6.260.0 HTTP Compression middleware (gzip/br) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.260.0"
owner_path: "framework/packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "Коли `http.compression.enabled=true`, відповіді (не-streaming) можуть детерміновано стискатися без витоку даних."
provides:
- "Response compression як `system_post` middleware з deterministic policy (алгоритм, min_bytes, header order)"
- "Eligibility/skip policy (status/content-type/streaming) з повагою до DisableBuffering/streaming semantics"
- "Noop-safe observability (spans/metrics/logs) з редукцією/редакцією (без payload/headers/cookies)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
- docs/ssot/di-tags-and-middleware-ordering.md
- docs/ssot/http-middleware-catalog.md
---

### Phase-1 cemented compliance addendum (MUST)

#### Ordering / tags (MUST)
- Middleware **MUST** be discovered via Foundation TagRegistry and executed **exactly** in TagRegistry order.
- Ordering law is **cemented**: `priority DESC, id ASC` (strcmp, locale-independent) via `Coretsia\Foundation\Discovery\DeterministicOrder`.
- Wiring **MUST** use owner constants from `platform/http` (e.g. `Coretsia\Http\Provider\Tags::HTTP_MIDDLEWARE_SYSTEM_POST`) — **no inline tag strings** in providers.

#### Headers determinism (MUST)
- PSR-7 does **not** guarantee emitted header order. This epic MUST guarantee:
  - deterministic **presence** of headers,
  - deterministic **values** (case/format/policy),
  - deterministic **sequence of withHeader/withAddedHeader calls** (best-effort),
  - and MUST NOT rely on “header order” as a correctness invariant.

#### Compression bytes determinism (MUST)
- Encoders MUST produce deterministic bytes for the same input:
  - gzip: MUST NOT embed timestamps/filenames/comments; gzip header fields must be stable (mtime=0-equivalent, no extra fields).
  - br (if enabled): parameters MUST be fixed by policy (quality/window/etc); no adaptive/jitter behavior.
- If deterministic bytes cannot be guaranteed on a platform/encoder, middleware MUST degrade-to-uncompressed deterministically with outcome token (no throw).

#### Vary: Accept-Encoding (MUST)
- When compression is applied OR considered eligible for the response, middleware MUST ensure `Vary` contains `Accept-Encoding` deterministically.
- This MUST hold even if `http.cache_headers.enabled=false` (i.e. CompressionMiddleware is self-sufficient).

#### ETag interaction (single-choice, MUST)
- If an `ETag` header is present on the response:
  - CompressionMiddleware MUST NOT log/label the ETag value.
  - CompressionMiddleware MUST NOT attempt to recompute ETag.
  - Correctness is ensured by Epic 6.270.0 policy: when strong ETag is requested and compression is enabled, ETag is skipped deterministically (see 6.270 addendum).

### Dependencies (MUST)

#### Preconditions (MUST)

- Required deliverables (exact paths):
  - `framework/packages/platform/http/config/http.php` — існує та читається як root `http`
  - `framework/packages/platform/http/config/rules.php` — існує (shape validation)
  - `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — існує (wiring/DI tags)

- Required config roots/keys:
  - `http.compression.*` — конфіг компресії (toggle/policy)

- Required tags:
  - `http.middleware.system_post` — slot/tag для post-response shaping

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — доступ до ContextStore
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — spans (noop-safe)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics (noop-safe)
  - `Psr\Http\Server\MiddlewareInterface` — PSR-15 middleware contract
  - `Psr\Http\Message\ServerRequestInterface` / `Psr\Http\Message\ResponseInterface` / `Psr\Http\Message\StreamInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- `platform/logging`
- `platform/tracing`
- `platform/metrics`
- `platform/health`
- `platform/profiling`
- `platform/observability`
- any other runtime packages (errors/problem-details/http-app/routing/etc)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\StreamInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- CLI:
  - none
- HTTP:
  - middleware slots/tags: `http.middleware.system_post` priority `700` meta `{"toggle":"http.compression.enabled","reason":"compress eligible responses"}`
  - notes: runs after ETag (`800`) and after CacheHeaders (`900`) inside `system_post` slot
- Kernel hooks/tags:
  - none
- Artifacts:
  - reads: none
  - writes: none

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/http/src/Middleware/CompressionMiddleware.php` — compress response if eligible
- [ ] `framework/packages/platform/http/src/Compression/CompressionPolicy.php` — deterministic policy (algorithms/min_bytes/exclusions)
- [ ] `framework/packages/platform/http/src/Compression/CompressionDecider.php` — eligibility checks (status/content-type/streaming)
- [ ] `framework/packages/platform/http/src/Compression/Encoders/GzipEncoder.php` — gzip encoder (deterministic)
- [ ] `framework/packages/platform/http/src/Compression/Encoders/BrEncoder.php` — br encoder (optional; deterministic)
- [ ] `framework/packages/platform/http/src/Observability/HttpCompressionInstrumentation.php` — spans/metrics helper
- [ ] `framework/packages/platform/http/src/Exception/HttpCompressionException.php` — deterministic codes (only if needed; prefer no-throw)
- [ ] `framework/packages/platform/http/docs/middlewares/compression.md` — slot/priority/toggle/override instructions

#### Modifies

- [ ] `framework/packages/platform/http/config/http.php` — add `http.compression.*` keys + defaults
- [ ] `framework/packages/platform/http/config/rules.php` — enforce `http.compression.*` shape
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — register services + tag wiring for `http.middleware.system_post` priority `700`
- [ ] `framework/packages/platform/http/README.md` — document middleware slot + priority + override/disable

#### Package skeleton (if type=package)

N/A (package `framework/packages/platform/http/` already exists)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.compression.enabled` = false
  - [ ] `http.compression.algorithms` = ['gzip','br']
  - [ ] `http.compression.min_bytes` = 1024
  - [ ] `http.compression.excluded_content_types` = ['text/event-stream','application/x-ndjson']
- [ ] Rules:
  - [ ] `framework/packages/platform/http/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - N/A (uses existing tag `http.middleware.system_post`)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Http\Middleware\CompressionMiddleware::class`
  - [ ] adds tag: `http.middleware.system_post` priority `700` meta `{"toggle":"http.compression.enabled","reason":"compress eligible responses"}`
  - [ ] registers: `\Coretsia\Http\Compression\CompressionPolicy::class`
  - [ ] registers: `\Coretsia\Http\Compression\CompressionDecider::class`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::PATH_TEMPLATE` (optional; for safe logging/spans only)
- Context writes (safe only):
  - none
- Reset discipline:
  - N/A (stateful services not expected)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `http.response.compress` (attrs: `algorithm`, `outcome`, `bytes_in`, `bytes_out` — no payload)
- [ ] Metrics:
  - [ ] `http.response_compress_total` (labels: `driver|outcome`)
  - [ ] `http.response_compress_duration_ms` (labels: `driver|outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`
- [ ] Logs:
  - [ ] only summary (algorithm + sizes), no headers/cookies/body

#### Errors

- [ ] Exceptions introduced:
  - [ ] `\Coretsia\Http\Exception\HttpCompressionException` — errorCode `CORETSIA_HTTP_COMPRESSION_FAILED` (only if needed; prefer degrade-to-uncompressed, no throw)
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) (caught by problem-details outer middleware)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/raw payload
- [ ] Allowed:
  - [ ] `len(value)` / sizes, safe ids (`actor_id`) if present elsewhere

### Verification (TEST EVIDENCE) (MUST when applicable)

> These are the proof that the above is real.

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` — N/A (no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` — N/A (no reset)
- [ ] If metrics/spans/logs exist → satisfied by contract/integration tests below
- [ ] If redaction exists → satisfied by integration test below (no payload/headers leakage)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions (if harness exists in package)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/http/tests/Unit/CompressionDeciderDeterministicTest.php`
  - [ ] `framework/packages/platform/http/tests/Unit/GzipEncodingDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/http/tests/Contract/NoopTracingAndMetricsNeverThrowInPipelineContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/http/tests/Integration/CompressionAppliesWhenAcceptedAndEligibleTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/CompressionSkippedForStreamingResponsesTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/CompressionDoesNotLeakPayloadToLogsTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)
  - [ ] gates updated (if new invariants)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Observability policy satisfied (names + label allowlist + redaction)
- [ ] Determinism: rerun-no-diff (same inputs → same compressed bytes where applicable)
- [ ] Docs updated:
  - [ ] `framework/packages/platform/http/README.md`
  - [ ] `framework/packages/platform/http/docs/middlewares/compression.md`
- [ ] Problem solved:
  - [ ] Додає response compression як `system_post` middleware з deterministic policy (алгоритм, min_bytes, header order)
  - [ ] Гарантує: не ламає error responses/streaming semantics (поважає `DisableBufferingMiddleware`/streaming responses)
  - [ ] Дає noop-safe observability + redaction (без payload/headers/cookies)
- [ ] Non-goals / out of scope:
  - [ ] Не додаємо HTTP/2/3 (ops-only, окремий документ)
  - [ ] Не вводимо “автоматичні” нестабільні heuristics (рандом/час/FS)
  - [ ] Не робимо компресію для streaming SSE/NDJSON (якщо response streaming — пропускаємо)
- [ ] Коли `http.compression.enabled=true`, відповіді (не-streaming) можуть детерміновано стискатися без витоку даних.
- [ ] When client sends `Accept-Encoding: gzip`, then response body is gzip-compressed (when eligible) and headers are set deterministically.
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
  - [ ] `platform/problem-details` outer wrapper handles fatal errors (compression should not throw)
- [ ] Discovery / wiring is via tags:
  - [ ] `http.middleware.system_post`

---

### 6.270.0 HTTP ETag + Conditional GET middleware (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.270.0"
owner_path: "framework/packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "Коли `http.etag.enabled=true`, відповіді отримують deterministic ETag, а `If-None-Match` дає 304 без зайвого навантаження."
provides:
- "Deterministic ETag middleware + conditional GET (304) як `system_post` middleware"
- "Policy (weak/strong, inclusion rules) + deterministic header behavior"
- "No high-cardinality observability (без raw path/etag як labels/логи), не ламає error/streaming responses (skip on streaming)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
- docs/ssot/di-tags-and-middleware-ordering.md
- docs/ssot/http-middleware-catalog.md
---

### Phase-1 cemented compliance addendum (MUST)

#### Ordering / tags (MUST)
- Middleware MUST be discovered via TagRegistry and executed exactly in TagRegistry order (no re-sort/dedupe by consumers).
- Ordering law: `priority DESC, id ASC` via `Coretsia\Foundation\Discovery\DeterministicOrder`.
- Wiring MUST use owner constants from `platform/http` `Provider\Tags` (no inline tag strings).

#### PSR-7 constraints (MUST)
- Epic guarantees deterministic ETag **value computation** and **header set**, but MUST NOT claim deterministic **emitted header order**.

#### Strong ETag vs Compression (single-choice, MUST)
- If `http.etag.weak=false` (strong ETag requested) **and** `http.compression.enabled=true`:
  - ETag middleware MUST **skip** ETag + conditional GET deterministically (no throw), with outcome token:
    - `outcome = "skipped_strong_etag_with_compression"`
  - Rationale: strong ETag must represent the served representation; without coupling/rewriting compression pipeline, the only safe deterministic policy is skip.

#### Weak ETag semantics (MUST)
- If `http.etag.weak=true`, ETag value MAY be based on canonical (pre-compression) payload bytes, but MUST still:
  - avoid reading streaming/non-seekable bodies,
  - avoid leaking body/headers,
  - ensure `Vary` contains `Accept-Encoding` deterministically when compression is enabled or eligible.

#### Redaction / observability (MUST)
- Metrics/labels MUST NOT include raw ETag value and MUST NOT include raw path/query.
- Logging MUST NOT include ETag value; allowed: `len(body)`, outcome tokens, `weak=true|false`.

### Dependencies (MUST)

#### Preconditions (MUST)

- Required deliverables (exact paths):
  - `framework/packages/platform/http/config/http.php` — існує та читається як root `http`
  - `framework/packages/platform/http/config/rules.php` — існує (shape validation)
  - `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — існує (wiring/DI tags)

- Required config roots/keys:
  - `http.etag.*` — конфіг ETag/conditional GET

- Required tags:
  - `http.middleware.system_post` — slot/tag для post-response shaping

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface` / `Psr\Http\Message\ResponseInterface` / `Psr\Http\Message\StreamInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- `platform/logging`
- `platform/tracing`
- `platform/metrics`
- `platform/health`
- `platform/profiling`
- `platform/observability`
- any other runtime packages (errors/problem-details/http-app/routing/etc)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\StreamInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- CLI:
  - none
- HTTP:
  - middleware slots/tags: `http.middleware.system_post` priority `800` meta `{"toggle":"http.etag.enabled","reason":"etag + conditional get"}`
  - notes: runs before Compression (`700`) so ETag is computed on pre-compressed representation
- Kernel hooks/tags:
  - none
- Artifacts:
  - reads: none
  - writes: none

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/http/src/Middleware/EtagMiddleware.php` — computes ETag and returns 304 on match
- [ ] `framework/packages/platform/http/src/Etag/EtagPolicy.php` — deterministic policy (weak/strong, eligible statuses, methods)
- [ ] `framework/packages/platform/http/src/Etag/EtagCalculator.php` — deterministic hash policy (no secrets)
- [ ] `framework/packages/platform/http/src/Observability/HttpEtagInstrumentation.php` — spans/metrics helper
- [ ] `framework/packages/platform/http/src/Exception/HttpEtagException.php` — deterministic codes (only if needed; prefer no-throw)
- [ ] `framework/packages/platform/http/docs/middlewares/etag.md` — slot/priority/toggle/override instructions

#### Modifies

- [ ] `framework/packages/platform/http/config/http.php` — add `http.etag.*` keys + defaults
- [ ] `framework/packages/platform/http/config/rules.php` — enforce `http.etag.*` shape
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — register services + tag wiring for `http.middleware.system_post` priority `800`
- [ ] `framework/packages/platform/http/README.md` — update: ETag middleware docs

#### Package skeleton (if type=package)

N/A (package `framework/packages/platform/http/` already exists)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.etag.enabled` = false
  - [ ] `http.etag.weak` = true
  - [ ] `http.etag.allowed_methods` = ['GET','HEAD']
  - [ ] `http.etag.allowed_statuses` = [200]
- [ ] Rules:
  - [ ] `framework/packages/platform/http/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - N/A (uses existing tag `http.middleware.system_post`)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Http\Middleware\EtagMiddleware::class`
  - [ ] adds tag: `http.middleware.system_post` priority `800` meta `{"toggle":"http.etag.enabled","reason":"etag + conditional get"}`
  - [ ] registers: `\Coretsia\Http\Etag\EtagPolicy::class`
  - [ ] registers: `\Coretsia\Http\Etag\EtagCalculator::class`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::PATH_TEMPLATE` (optional; span/log only)
- Context writes (safe only):
  - none
- Reset discipline:
  - N/A (stateful services not expected)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `http.etag` (attrs: `outcome=hit|miss|skipped`, `weak=true|false`, `bytes`)
- [ ] Metrics:
  - [ ] `http.etag_total` (labels: `driver|outcome`)
  - [ ] `http.etag_duration_ms` (labels: `driver|outcome`)
  - [ ] `http.etag_not_modified_total` (labels: `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`
- [ ] Logs:
  - [ ] only safe summary; MUST NOT log the ETag value

#### Errors

- [ ] Exceptions introduced:
  - [ ] `\Coretsia\Http\Exception\HttpEtagException` — errorCode `CORETSIA_HTTP_ETAG_FAILED` (only if needed; prefer passthrough response)
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) (caught by problem-details outer middleware)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw payload / headers / ETag value
- [ ] Allowed:
  - [ ] `len(value)` / sizes only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` — N/A (no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` — N/A (no reset)
- [ ] If metrics/spans/logs exist → satisfied by contract/integration tests below
- [ ] If redaction exists → satisfied by integration tests below (no ETag/raw payload leakage)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions (if harness exists in package)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/http/tests/Unit/EtagCalculatorDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/http/tests/Contract/MiddlewareOrderDeterministicTest.php` (ensure ETag(800) > Compression(700))
- Integration:
  - [ ] `framework/packages/platform/http/tests/Integration/EtagHeaderIsDeterministicForSamePayloadTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/IfNoneMatchReturns304Test.php`
  - [ ] `framework/packages/platform/http/tests/Integration/EtagSkippedForStreamingResponsesTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)
  - [ ] gates updated (if new invariants)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Observability policy satisfied (no etag/raw path as labels; redaction)
- [ ] Determinism: same payload → same ETag
- [ ] Docs updated:
  - [ ] `framework/packages/platform/http/README.md`
  - [ ] `framework/packages/platform/http/docs/middlewares/etag.md`
- [ ] Problem solved:
  - [ ] Додає deterministic ETag middleware + conditional GET (304) як `system_post` middleware
  - [ ] Дає policy (weak/strong, inclusion rules) і deterministic header behavior
  - [ ] Забезпечує: не використовує raw path як labels/логи, не ламає error responses
- [ ] Non-goals / out of scope:
  - [ ] Не робимо кешування body на диску (тільки ETag/304)
  - [ ] Не додаємо high-cardinality metrics (жодних raw path/etag як label)
  - [ ] Не намагаємось рахувати ETag для streaming responses (skip)
- [ ] Коли `http.etag.enabled=true`, відповіді отримують deterministic ETag, а `If-None-Match` дає 304 без зайвого навантаження.
- [ ] When client repeats GET with `If-None-Match` equal to server ETag, then server returns 304 with deterministic headers.
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] Discovery / wiring is via tags:
  - [ ] `http.middleware.system_post`

---

### 6.280.0 HTTP Cache-Control headers middleware (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.280.0"
owner_path: "framework/packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "Коли `http.cache_headers.enabled=true`, відповіді завжди містять deterministic cache headers згідно policy."
provides:
- "Централізовану постановку cache headers (Cache-Control/Expires/Pragma) як `system_post` middleware"
- "Deterministic header order та policy-based defaults для всіх responses (включно errors/maintenance)"
- "Конфіг defaults map без дублювання в контролерах"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
- docs/ssot/di-tags-and-middleware-ordering.md
- docs/ssot/http-middleware-catalog.md
---

### Phase-1 cemented compliance addendum (MUST)

#### Deterministic cache headers (MUST)
- This epic MUST avoid per-request timestamps in headers as a correctness invariant.
- Policy MUST be expressible via deterministic tokens:
  - prefer `Cache-Control` directives (`no-store`, `no-cache`, `max-age=<int>`, etc.),
  - `Pragma` and `Expires` (if used) MUST use fixed deterministic values (e.g. `Pragma: no-cache`, `Expires: 0` or a fixed epoch HTTP-date), not “now”.

#### Vary merging (MUST)
- When middleware sets `Vary`, it MUST merge deterministically:
  - treat existing Vary as a comma-separated set,
  - normalize tokens (trim + stable case strategy),
  - output in stable sorted order (`strcmp`) or stable first-seen order (single-choice: pick one and lock it in this epic).
- MUST ensure `Accept-Encoding` is present when compression is enabled/eligible.

#### PSR-7 note (MUST)
- Middleware MUST guarantee deterministic header **values** and merge policy.
- Middleware MUST NOT rely on emitted header order being deterministic across PSR-7 implementations.

### Dependencies (MUST)

#### Preconditions (MUST)

- Required deliverables (exact paths):
  - `framework/packages/platform/http/config/http.php` — існує та читається як root `http`
  - `framework/packages/platform/http/config/rules.php` — існує (shape validation)
  - `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — існує (wiring/DI tags)

- Required config roots/keys:
  - `http.cache_headers.*` — конфіг cache headers policy

- Required tags:
  - `http.middleware.system_post` — slot/tag для post-response shaping

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface` / `Psr\Http\Message\ResponseInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- `platform/logging`
- `platform/tracing`
- `platform/metrics`
- `platform/health`
- `platform/profiling`
- `platform/observability`
- any other runtime packages (errors/problem-details/http-app/routing/etc)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- CLI:
  - none
- HTTP:
  - middleware slots/tags: `http.middleware.system_post` priority `900` meta `{"toggle":"http.cache_headers.enabled","reason":"cache-control headers"}`
  - notes: runs first inside system_post slot (outermost response shaping)
- Kernel hooks/tags:
  - none
- Artifacts:
  - reads: none
  - writes: none

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/http/src/Middleware/CacheHeadersMiddleware.php` — sets cache headers (deterministic)
- [ ] `framework/packages/platform/http/src/CacheHeaders/CacheHeadersPolicy.php` — defaults + deterministic header order
- [ ] `framework/packages/platform/http/src/Observability/HttpCacheHeadersInstrumentation.php` — spans/metrics helper
- [ ] `framework/packages/platform/http/docs/middlewares/cache_headers.md` — slot/priority/toggle/override instructions

#### Modifies

- [ ] `framework/packages/platform/http/config/http.php` — add `http.cache_headers.*` keys + defaults
- [ ] `framework/packages/platform/http/config/rules.php` — enforce `http.cache_headers.*` shape
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceProvider.php` — register services + tag wiring for `http.middleware.system_post` priority `900`
- [ ] `framework/packages/platform/http/README.md` — update: CacheHeaders middleware docs

#### Package skeleton (if type=package)

N/A (package `framework/packages/platform/http/` already exists)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.cache_headers.enabled` = false
  - [ ] `http.cache_headers.defaults.cache_control` = 'no-store'
  - [ ] `http.cache_headers.defaults.vary` = ['Accept-Encoding']
- [ ] Rules:
  - [ ] `framework/packages/platform/http/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - N/A (uses existing tag `http.middleware.system_post`)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Http\Middleware\CacheHeadersMiddleware::class`
  - [ ] adds tag: `http.middleware.system_post` priority `900` meta `{"toggle":"http.cache_headers.enabled","reason":"cache-control headers"}`
  - [ ] registers: `\Coretsia\Http\CacheHeaders\CacheHeadersPolicy::class`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (optional; safe log)
- Context writes (safe only):
  - none
- Reset discipline:
  - N/A (stateful services not expected)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `http.cache_headers`
- [ ] Metrics:
  - [ ] `http.cache_headers_total` (labels: `outcome`)
  - [ ] `http.cache_headers_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] no header dumps; only enabled/disabled + outcome

#### Errors

N/A (failures should not introduce new exceptions; middleware must be safe)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/payload
- [ ] Allowed:
  - [ ] safe config-derived values only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` — N/A (no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` — N/A (no reset)
- [ ] If metrics/spans/logs exist → satisfied by unit/integration tests below
- [ ] If redaction exists → satisfied by integration tests below (no dumps)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions (if harness exists in package)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/http/tests/Unit/CacheHeadersPolicyDeterministicOrderTest.php`
- Contract:
  - N/A
- Integration:
  - [ ] `framework/packages/platform/http/tests/Integration/CacheHeadersAppliedDeterministicallyTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/CacheHeadersAppliedOnErrorResponsesTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)
  - [ ] gates updated (if new invariants)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Observability policy satisfied (no secret leakage)
- [ ] Tests:
  - [ ] unit + integration pass
- [ ] Docs updated:
  - [ ] `framework/packages/platform/http/README.md`
  - [ ] `framework/packages/platform/http/docs/middlewares/cache_headers.md`
- [ ] Problem solved:
  - [ ] Додає централізовану постановку cache headers (Cache-Control/Expires/Pragma) як `system_post` middleware
  - [ ] Гарантує deterministic header order та застосування policy для всіх responses (включно errors/maintenance)
  - [ ] Дає конфіг (defaults map) без дублювання в контролерах
- [ ] Non-goals / out of scope:
  - [ ] Не реалізує ETag або Compression (це окремі epics)
  - [ ] Не вводить route-level “smart caching” (лише глобальні policy-based defaults)
  - [ ] Не додає raw path як label або лог
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] Discovery / wiring is via tags:
  - [ ] `http.middleware.system_post`
- [ ] Коли `http.cache_headers.enabled=true`, відповіді завжди містять deterministic cache headers згідно policy.
- [ ] When a response is returned, then Cache-Control headers are applied deterministically without controller code.

---

### 6.290.0 Database advanced: ORM + Transactions (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.290.0"
owner_path: "framework/packages/platform/orm/"

package_id: "platform/orm"
composer: "coretsia/platform-orm"
kind: runtime
module_id: "platform.orm"

goal: "Application може використовувати ORM/repositories і TransactionRunner, не змінюючи `platform/database`, з deterministic поведінкою та без витоку SQL/PII."
provides:
- "Optional ORM поверх `platform/database` без зміни його public API"
- "Deterministic metadata mapping (attributes або PHP arrays), repositories, мінімальний unit-of-work"
- "TransactionRunner + deterministic retry policy (deadlock/backoff) без raw SQL/log leakage"

tags_introduced: []
config_roots_introduced:
- "orm"

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
---

### Phase-1 cemented compliance addendum (MUST)

#### Config root registry (MUST)
- `orm` is a new config root → MUST be registered in `docs/ssot/config-roots.md` (owner: `platform/orm`).
- `framework/packages/platform/orm/config/orm.php` MUST return subtree only (no `['orm'=>...]` wrapper).

#### Metadata discovery (single-choice, MUST)
- Runtime MUST NOT do directory scanning for entities.
- `orm.metadata` MUST be explicit and deterministic:
  - replace `orm.metadata.paths` with `orm.metadata.entities: list<class-string>` (sorted `strcmp` OR first-seen — pick one and lock).
  - AttributesMetadataDriver MUST operate only on the explicit entities list.
- Any “discover from paths” (if ever needed) is tooling-only (CLI) and MUST NOT be required for production boot.

#### Observability allowlists (MUST)
- Metrics labels MUST stay within SSoT allowlist: `operation,driver,outcome,table` (no entity payloads, no SQL, no raw identifiers).
- Any “operation” token MUST be low-cardinality (e.g. `select|insert|update|delete|transaction`).

#### Retry policy determinism (MUST)
- Retry MUST be driven only by fixed integer policy (`max_attempts`, `backoff_ms[]`).
- No jitter, no random, no float math.
- Classification of retryable errors MUST NOT rely on driver/vendor message strings (prefer deterministic driver codes from `platform/database` choke-point).

### Dependencies (MUST)

#### Preconditions (MUST)

- Required deliverables (exact paths):
  - `framework/packages/platform/database/` — існує (ORM використовує choke point)
  - `Coretsia\Contracts\Database\ConnectionInterface` — існує (для роботи через platform/database)
  - `Coretsia\Foundation\Discovery\DeterministicOrder` — існує (якщо використовується для детермінованого discovery)

- Required config roots/keys:
  - `orm.*` — конфіг ORM (toggle, metadata driver/paths, retry policy)

- Required tags:
  - none

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Database\ConnectionInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - (optional) `Psr\Log\LoggerInterface`
  - (optional) `Psr\Clock\ClockInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `platform/database`

Forbidden:

- `platform/http`
- `platform/http-app`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
  - `Psr\Clock\ClockInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Database\ConnectionInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - none
- HTTP:
  - none
- Kernel hooks/tags:
  - none
- Artifacts:
  - reads: none
  - writes: none

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/orm/composer.json` — package manifest (workspace/composer wiring)
- [ ] `framework/packages/platform/orm/src/Module/OrmModule.php`
- [ ] `framework/packages/platform/orm/src/Provider/OrmServiceProvider.php`
- [ ] `framework/packages/platform/orm/src/Provider/OrmServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/orm/config/orm.php`
- [ ] `framework/packages/platform/orm/config/rules.php`
- [ ] `framework/packages/platform/orm/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/orm/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

- [ ] `framework/packages/platform/orm/src/Metadata/MetadataDriverInterface.php` — attributes|arrays driver
- [ ] `framework/packages/platform/orm/src/Metadata/AttributesMetadataDriver.php` — attribute mapping
- [ ] `framework/packages/platform/orm/src/Metadata/ArrayMetadataDriver.php` — PHP-array mapping
- [ ] `framework/packages/platform/orm/src/Mapper/EntityMapper.php` — entity <-> row mapping
- [ ] `framework/packages/platform/orm/src/Repository/RepositoryInterface.php` — app-facing repository
- [ ] `framework/packages/platform/orm/src/Repository/GenericRepository.php` — reference repository
- [ ] `framework/packages/platform/orm/src/Pagination/Paginator.php` — deterministic pagination helpers
- [ ] `framework/packages/platform/orm/src/Transaction/TransactionRunner.php` — commit/rollback wrapper
- [ ] `framework/packages/platform/orm/src/Transaction/RetryPolicy.php` — deterministic retry (ints only; no floats)
- [ ] `framework/packages/platform/orm/src/Observability/OrmInstrumentation.php` — spans/metrics helper
- [ ] `framework/packages/platform/orm/src/Exception/OrmException.php` — deterministic codes
- [ ] `docs/architecture/orm.md` — mapping modes, deterministic rules, redaction notes

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/orm/composer.json`
- [ ] `framework/packages/platform/orm/src/Module/OrmModule.php` (runtime only)
- [ ] `framework/packages/platform/orm/src/Provider/OrmServiceProvider.php` (runtime only)
- [ ] `framework/packages/platform/orm/config/orm.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/platform/orm/config/rules.php`
- [ ] `framework/packages/platform/orm/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/orm/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/orm/config/orm.php`
- [ ] Keys (dot):
  - [ ] `orm.enabled` = false
  - [ ] `orm.metadata.driver` = 'attributes'
  - [ ] `orm.metadata.paths` = []                         # list-like, deterministic order
  - [ ] `orm.transactions.retry.enabled` = true
  - [ ] `orm.transactions.retry.max_attempts` = 3
  - [ ] `orm.transactions.retry.backoff_ms` = [0, 50, 200] # ints only
- [ ] Rules:
  - [ ] `framework/packages/platform/orm/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Orm\Metadata\MetadataDriverInterface::class` (selected driver)
  - [ ] registers: `\Coretsia\Orm\Mapper\EntityMapper::class`
  - [ ] registers: `\Coretsia\Orm\Repository\GenericRepository::class`
  - [ ] registers: `\Coretsia\Orm\Transaction\TransactionRunner::class`
  - [ ] registers: `\Coretsia\Orm\Transaction\RetryPolicy::class`
  - [ ] registers: `\Coretsia\Orm\Observability\OrmInstrumentation::class`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- Context writes (safe only):
  - none
- Reset discipline:
  - N/A (stateful services not expected)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `orm.op` (attrs: `operation`, `outcome`)
  - [ ] `orm.transaction` (attrs: `outcome`, `attempt`)
- [ ] Metrics:
  - [ ] `orm.op_total` (labels: `operation|outcome|driver`)
  - [ ] `orm.op_duration_ms` (labels: `operation|outcome|driver`)
  - [ ] `orm.transaction_retry_total` (labels: `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `kind/op/uow_type→operation`
  - [ ] `via→driver`
- [ ] Logs:
  - [ ] only safe metadata (entity class/table as span attr OK; no SQL, no entity payload)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `\Coretsia\Orm\Exception\OrmException` — errorCode(s):
    - `CORETSIA_ORM_METADATA_INVALID`
    - `CORETSIA_ORM_MAPPING_FAILED`
    - `CORETSIA_ORM_TRANSACTION_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw SQL, entity payload values, secrets/PII
- [ ] Allowed:
  - [ ] safe ids (`actor_id`), `hash(value)`/`len(value)` for debug only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` — N/A (no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` — N/A (no reset)
- [ ] If metrics/spans/logs exist → satisfied by contract/integration tests below
- [ ] If redaction exists → satisfied by integration test below (no raw SQL/log leakage)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/platform/orm/tests/Fixtures/<App>/...` (only if wiring requires boot)
- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/orm/tests/Unit/MetadataDriverDeterministicTest.php`
  - [ ] `framework/packages/platform/orm/tests/Unit/RetryPolicyDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/orm/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/orm/tests/Integration/CrudMapperOnSqliteDeterministicTest.php`
  - [ ] `framework/packages/platform/orm/tests/Integration/TransactionRunnerCommitRollbackTest.php`
  - [ ] `framework/packages/platform/orm/tests/Integration/NoRawSqlLoggedOrLabeledTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)
  - [ ] gates updated (if new invariants)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Observability policy satisfied (names + label allowlist + redaction)
- [ ] Tests:
  - [ ] unit + contract + integration pass
- [ ] Docs updated:
  - [ ] `framework/packages/platform/orm/README.md`
  - [ ] `docs/architecture/orm.md`
- [ ] Problem solved:
  - [ ] Додає optional ORM поверх `platform/database` без зміни його public API
  - [ ] Дає deterministic metadata mapping (attributes або PHP arrays), repositories, unit-of-work (мінімум)
  - [ ] Дає TransactionRunner + deterministic retry policy (deadlock/backoff) без raw SQL/log leakage
- [ ] Non-goals / out of scope:
  - [ ] Не робимо “монстр DSL/grammar” у `platform/database`
  - [ ] Не гарантуємо cross-DB feature parity (reference SQLite-first)
  - [ ] Не логимо entity payload або SQL; лише safe metadata
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
  - [ ] DB driver modules are enabled via integrations (sqlite/mysql/pg) — ORM uses `platform/database` choke point
- [ ] Discovery / wiring is via tags:
  - [ ] (optional) `cli.command` if you later add orm tooling commands (not required in this epic)
- [ ] Application може використовувати ORM/repositories і TransactionRunner, не змінюючи `platform/database`, з deterministic поведінкою та без витоку SQL/PII.
- [ ] When a repository saves an entity inside TransactionRunner, then commit/rollback semantics are correct and observability is emitted without raw SQL.

---

### 6.300.0 Bundles/Feature Packs (meta-modules) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.300.0"
owner_path: "framework/packages/"  # multi-package epic (core/kernel + platform/cli)

package_id: "core/kernel"
composer: "coretsia/core-kernel"
kind: runtime
module_id: "core.kernel"

goal: "Presets можуть посилатися на bundles, а kernel детерміновано розгортає їх у модулі без циклів і з чітким debug output."
provides:
- "Framework-default bundles/feature-packs для скорочення enable lists у presets"
- "Deterministic bundle expansion (cycle-safe) + schema validation + deterministic errors"
- "CLI інспекція bundles через reverse-dep (команди в platform/cli via `cli.command`) без kernel→cli залежності"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: docs/adr/ADR-0072-bundles-feature-packs.md
ssot_refs:
- docs/ssot/modules-and-manifests.md
- docs/ssot/modes.md
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
---

### Phase-1 cemented compliance addendum (MUST)

#### No platform deps in kernel (MUST)
- `core/kernel` MUST NOT depend on any `platform/*` packages (including `platform/cli`).
- CLI commands are reverse-deps: they live in `platform/cli`, depend on `core/kernel`, and are discovered via owner tag `cli.command`.

#### Bundles loading (MUST)
- Kernel MUST NOT “scan directories” to discover bundles during ModulePlan resolution.
- Kernel MUST load bundles only by **explicit names referenced by the preset**:
  - resolve to deterministic file path (skeleton override > framework resource),
  - load by name, validate schema, expand deterministically, detect cycles deterministically.
- CLI `bundles:list` MAY scan known directories for listing purposes, but MUST:
  - normalize relpaths,
  - sort results `strcmp`,
  - output via OutputInterface with redaction rules (no env/config dumps).

#### Preset schema extension (MUST)
- Bundles are a **ModePreset schema extension** and MUST be documented in SSoT:
  - `docs/ssot/modes.md` MUST describe the new field (e.g. `bundles: list<string>`),
  - `ModePresetSchemaValidator` MUST validate list-like semantics and forbid unknown shapes deterministically.

### Dependencies (MUST)

#### Preconditions (MUST)

- Required deliverables (exact paths):
  - `framework/packages/platform/cli/` — існує (для команд `bundles:*`, reverse-dep via `cli.command`)
  - `framework/tools/gates/no_skeleton_bundles_default_gate.php` — існує та запускається (ensure no default skeleton bundles)
  - `Coretsia\Contracts\Module\ManifestReaderInterface` — існує
  - `Coretsia\Contracts\Module\ModePresetLoaderInterface` — існує
  - `Coretsia\Contracts\Module\ModuleDescriptor` — існує
  - `Coretsia\Foundation\Discovery\DeterministicOrder` — існує (якщо використовується)

- Required config roots/keys:
  - N/A (bundle definitions live in files; не `config/<root>.php`)

- Required tags:
  - `cli.command` — для discovery CLI команд (owner: platform/cli)

- Required contracts / ports:
  - `Coretsia\Contracts\Module\ManifestReaderInterface`
  - `Coretsia\Contracts\Module\ModePresetLoaderInterface`
  - `Coretsia\Contracts\Module\ModuleDescriptor`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/*` (kernel MUST NOT depend on platform packages, including `platform/cli`)
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - none
- Contracts:
  - `Coretsia\Contracts\Module\ManifestReaderInterface`
  - `Coretsia\Contracts\Module\ModePresetLoaderInterface`
  - `Coretsia\Contracts\Module\ModuleDescriptor`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - `bundles:list` → `platform/cli` `framework/packages/platform/cli/src/Command/BundlesListCommand.php`
  - `bundles:debug` → `platform/cli` `framework/packages/platform/cli/src/Command/BundlesDebugCommand.php`
- HTTP:
  - none
- Kernel hooks/tags:
  - none
- Artifacts:
  - reads: `skeleton/var/cache/<appId>/module-manifest.php`, `skeleton/var/cache/<appId>/config.php` (planning inputs; no new artifact type)
  - writes: `skeleton/var/cache/<appId>/module-manifest.php`, `skeleton/var/cache/<appId>/config.php` (existing artifact types; expanded plan)

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/kernel/src/Module/BundleExpander.php` — deterministic expansion, cycle-safe
- [ ] `framework/packages/core/kernel/src/Module/FilesystemBundleLoader.php` — loads bundle defs (skeleton override > framework default)
- [ ] `framework/packages/core/kernel/src/Module/BundleSchemaValidator.php` — schema + list-like checks, deterministic errors
- [ ] `framework/packages/core/kernel/resources/bundles/observability-minimal.php` — example framework default bundle
- [ ] `framework/packages/core/kernel/resources/bundles/observability-full.php` — example framework default bundle
- [ ] `framework/packages/core/kernel/resources/bundles/enterprise-baseline.php` — example framework default bundle
- [ ] `docs/guides/bundles.md` — how bundles work + override rules + examples
- [ ] `docs/adr/ADR-0072-bundles-feature-packs.md` — ADR covering preset format extension + determinism + compat

- [ ] `framework/packages/platform/cli/src/Command/BundlesListCommand.php` — prints available bundles
- [ ] `framework/packages/platform/cli/src/Command/BundlesDebugCommand.php` — prints expanded modules + reasons
- [ ] `framework/packages/platform/cli/tests/Integration/BundlesCommandsDoNotLeakSecretsTest.php` — CLI output redaction proof

#### Modifies

- [ ] deptrac expectations updated (if needed)
- [ ] `framework/tools/gates/no_skeleton_bundles_default_gate.php` — ensure exists/enforced for default skeleton (already SSoT; update only if required)
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php` — wire Bundle* services (if kernel uses central provider wiring)
- [ ] `framework/packages/core/kernel/src/Module/*` integration points — connect expander/loader/validator into preset/module planning (where applicable)
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0072-bundles-feature-packs.md`
- [ ] `docs/ssot/modes.md` — document bundles field + precedence (skeleton override > framework)

#### Package skeleton (if type=package)

N/A (package `framework/packages/core/kernel/` already exists)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/core/kernel/resources/bundles/*.php`
  - [ ] `skeleton/config/bundles/<name>.php` (user-owned override, OPTIONAL; not shipped by default)
- [ ] Keys (dot):
  - [ ] `bundle.schemaVersion` = 1
  - [ ] `bundle.name` = '<name>'
  - [ ] `bundle.description` = ''
  - [ ] `bundle.modules.required` = []          # list<moduleId>
  - [ ] `bundle.modules.optional` = []          # list<moduleId>
  - [ ] `bundle.modules.disabled` = []          # list<moduleId>
- [ ] Rules:
  - [ ] `framework/packages/core/kernel/src/Module/BundleSchemaValidator.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - N/A (kernel does not own `cli.command`)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Kernel\Module\BundleExpander::class`
  - [ ] registers: `\Coretsia\Kernel\Module\FilesystemBundleLoader::class`
  - [ ] registers: `\Coretsia\Kernel\Module\BundleSchemaValidator::class`
  - [ ] CLI reverse-dep wiring via `cli.command` (in `platform/cli`, not kernel):
    - [ ] registers: `\Coretsia\Cli\Command\BundlesListCommand::class`
    - [ ] registers: `\Coretsia\Cli\Command\BundlesDebugCommand::class`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `skeleton/var/cache/<appId>/module-manifest.php` (existing artifact type; deterministic bytes)
  - [ ] `skeleton/var/cache/<appId>/config.php` (existing artifact type; deterministic bytes)
- [ ] Reads:
  - [ ] validates header + payload schema where applicable (bundle schema + list-like determinism)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `kernel.bundle.expand` (optional; attrs: outcome, bundle name as span attr)
- [ ] Metrics:
  - [ ] `kernel.bundle_expand_total` (labels: `outcome`)
  - [ ] `kernel.bundle_expand_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] only names + counts; no secrets/PII

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/core/kernel/src/Exception/BundleException.php` — errorCode(s):
    - `CORETSIA_BUNDLE_NOT_FOUND`
    - `CORETSIA_BUNDLE_INVALID`
    - `CORETSIA_BUNDLE_CYCLE_DETECTED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) (CLI prints deterministic error output)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] any secret values from env/config in `bundles:debug` output
- [ ] Allowed:
  - [ ] module ids, bundle names, file paths (repo-relative)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` — N/A
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` — N/A
- [ ] If metrics/spans/logs exist → satisfied by unit/integration tests below
- [ ] If redaction exists → satisfied by CLI integration test below

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture wiring:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/HybridApp/config/modes/hybrid.php` (optional: references bundles to prove expansion)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/core/kernel/tests/Unit/BundleSchemaValidatorTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/BundleExpansionDeterministicTest.php`
- Contract:
  - N/A
- Integration:
  - [ ] `framework/packages/core/kernel/tests/Integration/BundleOverrideOrderSkeletonWinsTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/BundleCycleDetectionFailsDeterministicallyTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/Kernel/BundlesDoNotRequireSkeletonDefaultsTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)
  - [ ] gates updated (if new invariants)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (kernel has no platform deps; no cycles)
- [ ] Determinism:
  - [ ] rerun expansion → same module plan
- [ ] Tests:
  - [ ] unit + integration pass
  - [ ] CLI: `BundlesCommandsDoNotLeakSecretsTest.php` pass
- [ ] Docs updated:
  - [ ] ADR accepted: `docs/adr/ADR-0072-bundles-feature-packs.md`
  - [ ] `docs/guides/bundles.md` complete and accurate
- [ ] Problem solved:
  - [ ] Додає bundles/feature-packs як framework defaults (не skeleton defaults) для скорочення enable lists у presets
  - [ ] Дає deterministic expansion (cycle-safe) і CLI tooling для інспекції (list/debug)
  - [ ] Зберігає SSoT правила: skeleton overrides optional; default skeleton MUST NOT ship `skeleton/config/bundles/*.php`
- [ ] Non-goals / out of scope:
  - [ ] Не змінюємо dependency law (Platform !=> Integrations; http boundary unchanged)
  - [ ] Не робимо bundles “dependency graph” у compile-time deps
  - [ ] Не дозволяємо bundles змінювати compile-time deps або public contracts shape без ADR
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/cli` is present to expose `bundles:*` commands (reverse dep via `cli.command`)
- [ ] Discovery / wiring is via tags:
  - [ ] `cli.command` (commands live in platform/cli)
- [ ] Presets можуть посилатися на bundles, а kernel детерміновано розгортає їх у модулі без циклів і з чітким debug output.
- [ ] When a preset references bundle `observability-minimal`, then kernel expands it deterministically and CLI can show expanded modules.

---

### 6.310.0 Enterprise SSO: OIDC (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.310.0"
owner_path: "framework/packages/enterprise/sso-oidc/"  # package root

package_id: "enterprise/sso-oidc"
composer: "coretsia/enterprise-sso-oidc"
kind: runtime
module_id: "enterprise.sso-oidc"

goal: "OIDC login+callback працює детерміновано й без витоку секретів: verified claims → linked local identity → session established."
provides:
- "Vendor-agnostic SSO contracts ports (SSO provider + identity claims + identity link repo) без вшивання vendor concretes у core/contracts"
- "OIDC provider (PSR-18) із deterministic/security policy: state/nonce, issuer/audience, JWKS caching, redaction"
- "Reference HTTP flow (login+callback) як app routes + deterministic error mapping + docs/troubleshooting"

tags_introduced: []
config_roots_introduced: ["sso_oidc"]
artifacts_introduced: []

adr: docs/adr/ADR-0073-enterprise-sso-oidc-contracts.md
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Add epic prerequisites:
  - 1.185.0 — Contracts: SSO ports (`Coretsia\Contracts\Sso\*`) MUST exist
  - 1.188.0 — Contracts: Auth ports (Identity/UserProvider) MUST exist (if not already in roadmap)
  - 1.189.0 — Contracts: Session port MUST exist (if not already in roadmap)
  - Tag registry MUST include `routing.route_provider` (owner `platform/routing`) in `docs/ssot/tags.md`

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - N/A

- Required tags:
  - `routing.route_provider` — route discovery для app routes
  - `error.mapper` — deterministic mapping exceptions → ErrorDescriptor/HTTP hints

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — correlation/uow context reads
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` — resolve `client_secret_ref`
  - `Coretsia\Contracts\Session\SessionManagerInterface` — state/nonce store + session establish
  - `Coretsia\Contracts\Auth\IdentityInterface` — local identity shape
  - `Coretsia\Contracts\Auth\UserProviderInterface` — link/resolve local identity
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface` — error mapping port
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor` — deterministic error descriptor
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — spans
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http` (transport boundary; SSO працює через routing/http-app)
- `platform/observability` (glue is not shared)
- `integrations/*` (enterprise package MUST NOT depend on integrations)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\ResponseFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`
  - `Psr\Http\Client\ClientInterface` (PSR-18)
  - `Psr\SimpleCache\CacheInterface` (PSR-16, optional for JWKS cache)
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface`
  - `Coretsia\Contracts\Session\SessionManagerInterface`
  - `Coretsia\Contracts\Auth\IdentityInterface`
  - `Coretsia\Contracts\Auth\UserProviderInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - routes: `GET /sso/oidc/login` → `enterprise/sso-oidc` `\Coretsia\Enterprise\SsoOidc\Http\Controller\OidcLoginController`
  - routes: `GET /sso/oidc/callback` → `enterprise/sso-oidc` `\Coretsia\Enterprise\SsoOidc\Http\Controller\OidcCallbackController`
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/enterprise/sso-oidc/src/Module/SsoOidcModule.php` — runtime module entry
- [ ] `framework/packages/enterprise/sso-oidc/src/Provider/SsoOidcServiceProvider.php` — DI wiring
- [ ] `framework/packages/enterprise/sso-oidc/src/Provider/SsoOidcServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/enterprise/sso-oidc/config/sso_oidc.php` — config subtree provider (no repeated root)
- [ ] `framework/packages/enterprise/sso-oidc/config/rules.php` — config shape rules
- [ ] `framework/packages/enterprise/sso-oidc/README.md` — must include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/enterprise/sso-oidc/docs/oidc.md` — flow, config keys, redaction, troubleshooting

- [ ] `framework/packages/core/contracts/src/Sso/SsoProviderInterface.php` — contracts port (vendor-agnostic SSO provider)
- [ ] `framework/packages/core/contracts/src/Sso/SsoIdentityClaims.php` — VO (json-like; no secrets)
- [ ] `framework/packages/core/contracts/src/Sso/IdentityLinkRepositoryInterface.php` — link external subject → local identity id
- [ ] `framework/packages/core/contracts/src/Sso/SsoException.php` — base deterministic exception for SSO domain

- [ ] `docs/adr/ADR-0073-enterprise-sso-oidc-contracts.md` — ADR for new contracts + boundary

- [ ] `framework/packages/enterprise/sso-oidc/src/Oidc/OidcProvider.php` — implements `SsoProviderInterface` using PSR-18
- [ ] `framework/packages/enterprise/sso-oidc/src/Oidc/OidcProviderConfig.php` — deterministic config VO (issuer/clientId/redirectUri/scopes)
- [ ] `framework/packages/enterprise/sso-oidc/src/Oidc/StateNonceStore.php` — session-backed state/nonce (no logging)
- [ ] `framework/packages/enterprise/sso-oidc/src/Oidc/Jwks/JwksFetcher.php` — fetch + cache JWKS (PSR-16 optional)
- [ ] `framework/packages/enterprise/sso-oidc/src/Oidc/Jwt/JwtVerifier.php` — signature + claims verification (issuer/audience/exp)
- [ ] `framework/packages/enterprise/sso-oidc/src/Http/Controller/OidcLoginController.php` — redirects to provider (302)
- [ ] `framework/packages/enterprise/sso-oidc/src/Http/Controller/OidcCallbackController.php` — exchanges code, verifies, links identity
- [ ] `framework/packages/enterprise/sso-oidc/src/Routing/OidcRouteProvider.php` — provides routes (tag `routing.route_provider`)
- [ ] `framework/packages/enterprise/sso-oidc/src/Exception/OidcProblemMapper.php` — `ExceptionMapperInterface` (tag `error.mapper`)

- [ ] `framework/packages/enterprise/sso-oidc/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — baseline: noops don’t throw

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0073-enterprise-sso-oidc-contracts.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/enterprise/sso-oidc/composer.json`
- [ ] `framework/packages/enterprise/sso-oidc/src/Module/SsoOidcModule.php` (runtime only)
- [ ] `framework/packages/enterprise/sso-oidc/src/Provider/SsoOidcServiceProvider.php` (runtime only)
- [ ] `framework/packages/enterprise/sso-oidc/config/sso_oidc.php`
- [ ] `framework/packages/enterprise/sso-oidc/config/rules.php`
- [ ] `framework/packages/enterprise/sso-oidc/README.md`
- [ ] `framework/packages/enterprise/sso-oidc/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/enterprise/sso-oidc/config/sso_oidc.php`
- [ ] Keys (dot):
  - [ ] `sso_oidc.enabled` = false
  - [ ] `sso_oidc.issuer` = ''
  - [ ] `sso_oidc.client_id` = ''
  - [ ] `sso_oidc.client_secret_ref` = ''              # resolved via SecretsResolverInterface
  - [ ] `sso_oidc.redirect_uri` = ''
  - [ ] `sso_oidc.scopes` = ['openid','profile']
  - [ ] `sso_oidc.jwks.cache.enabled` = true
  - [ ] `sso_oidc.jwks.cache_ttl_seconds` = 3600
  - [ ] `sso_oidc.session.state_key` = 'sso_oidc_state'
  - [ ] `sso_oidc.session.nonce_key` = 'sso_oidc_nonce'
- [ ] Rules:
  - [ ] `framework/packages/enterprise/sso-oidc/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (tag `routing.route_provider`, `error.mapper` is owned outside this package)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Enterprise\SsoOidc\Oidc\OidcProvider`
  - [ ] registers: `\Coretsia\Enterprise\SsoOidc\Routing\OidcRouteProvider`
  - [ ] adds tag: `routing.route_provider` priority `0` meta `{"owner":"enterprise/sso-oidc"}`
  - [ ] registers: `\Coretsia\Enterprise\SsoOidc\Exception\OidcProblemMapper`
  - [ ] adds tag: `error.mapper` priority `200` meta `{"domain":"sso.oidc"}`

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
- Reset discipline:
  - N/A (stateful services only if introduced later)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `sso_oidc.login`
  - [ ] `sso_oidc.callback`
  - [ ] `sso_oidc.token_exchange`
  - [ ] `sso_oidc.jwks_fetch`
- [ ] Metrics:
  - [ ] `sso_oidc.flow_total` (labels: `driver|operation|outcome|status`)
  - [ ] `sso_oidc.flow_duration_ms` (labels: `driver|operation|outcome|status`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`, `op→operation`, `reason→status`
- [ ] Logs:
  - [ ] warn/error on verification failure with redaction (`hash/len` only)
  - [ ] no secrets/PII (no code/id_token/access_token, no email/sub in logs)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Contracts\Sso\SsoException` — errorCode `CORETSIA_SSO_OIDC_STATE_INVALID`
  - [ ] `Coretsia\Contracts\Sso\SsoException` — errorCode `CORETSIA_SSO_OIDC_TOKEN_EXCHANGE_FAILED`
  - [ ] `Coretsia\Contracts\Sso\SsoException` — errorCode `CORETSIA_SSO_OIDC_JWT_INVALID`
  - [ ] `Coretsia\Contracts\Sso\SsoException` — errorCode `CORETSIA_SSO_OIDC_JWKS_UNAVAILABLE`
- [ ] Mapping:
  - [ ] new mapper via tag `error.mapper` (`\Coretsia\Enterprise\SsoOidc\Exception\OidcProblemMapper`)
  - [ ] HTTP status hints (documented in ErrorDescriptor, optional):
    - invalid state/verification → 401
    - provider unavailable → 503

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization headers, cookies, session id, OAuth code, tokens, raw JWT, raw claims payload
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id`) якщо вже встановлено platform/auth

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `framework/packages/enterprise/sso-oidc/tests/Contract/ContextWriteSafetyTest.php` (N/A)
- [ ] If `kernel.reset` used → `framework/packages/enterprise/sso-oidc/tests/Contract/ResetWiringTest.php` (N/A)
- [ ] If metrics/spans/logs exist → `framework/packages/enterprise/sso-oidc/tests/Contract/ObservabilityPolicyTest.php`
- [ ] If redaction exists → `framework/packages/enterprise/sso-oidc/tests/Contract/RedactionDoesNotLeakTest.php`
- [ ] Baseline cross-cutting noop safety → `framework/packages/enterprise/sso-oidc/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` (if wiring requires boot)
- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/enterprise/sso-oidc/tests/Unit/JwtVerifierDeterministicValidationTest.php`
  - [ ] `framework/packages/enterprise/sso-oidc/tests/Unit/StateNonceStoreDoesNotLeakTest.php`
- Contract:
  - [ ] `framework/packages/enterprise/sso-oidc/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/enterprise/sso-oidc/tests/Contract/ObservabilityPolicyTest.php`
  - [ ] `framework/packages/enterprise/sso-oidc/tests/Contract/RedactionDoesNotLeakTest.php`
- Integration:
  - [ ] `framework/packages/enterprise/sso-oidc/tests/Integration/LoginRedirectIsDeterministicTest.php`
  - [ ] `framework/packages/enterprise/sso-oidc/tests/Integration/CallbackFailsOnInvalidStateTest.php`
  - [ ] `framework/packages/enterprise/sso-oidc/tests/Integration/CallbackVerifiesJwtAndLinksIdentityTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)
  - [ ] gates updated (if new invariants)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] login redirect URL canonicalized (params sorted)
  - [ ] JWKS cache key deterministic
- [ ] Docs updated:
  - [ ] `framework/packages/enterprise/sso-oidc/README.md`
  - [ ] `framework/packages/enterprise/sso-oidc/docs/oidc.md`
  - [ ] ADR present (`docs/adr/ADR-0073-...`) (+ legacy ADR reference resolved/removed if redundant)
- [ ] Scope & intent (MUST)
  - [ ] Дає enterprise SSO через OIDC (login + callback) без вшивання vendor concretes у `core/contracts`
  - [ ] Узгоджує boundary: SSO провайдер → verified identity claims → link-to-local identity (без зміни `platform/auth` internals)
  - [ ] Забезпечує deterministic/security policy: state/nonce, issuer/audience, JWKS caching, redaction (no tokens/PII)
- [ ] Non-goals / out of scope
  - [ ] Не реалізує “full auth system” (RBAC/session/auth — це `platform/*`)
  - [ ] Не логить/не метрикує email/sub/token як labels (тільки span attrs або safe hashes)
  - [ ] Не робить “multi-provider federation UI” (тільки reference OIDC provider flow)
  - [ ] Не додає reserved endpoints у `SystemRouteTable` (OIDC routes — app routes)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/http + platform/routing + platform/http-app` (щоб експонувати controllers/routes)
  - [ ] `platform/session + platform/auth` (щоб встановити local session identity після SSO)
  - [ ] `platform/logging|tracing|metrics` (impl/noop-safe)
  - [ ] `platform/http-client` (або будь-який PSR-18 binding) → `Psr\Http\Client\ClientInterface`
  - [ ] optional cache module → `Psr\SimpleCache\CacheInterface` (JWKS caching)
- [ ] Discovery / wiring через tags:
  - [ ] `routing.route_provider`
  - [ ] `error.mapper`
- [ ] OIDC login+callback працює детерміновано й без витоку секретів: verified claims → linked local identity → session established.
- [ ] When user completes OIDC callback with valid state+signature, then the app resolves/links identity and user becomes authenticated via session.

---

### 6.320.0 Enterprise SSO: SAML (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.320.0"
owner_path: "framework/packages/enterprise/sso-saml/"  # package root

package_id: "enterprise/sso-saml"
composer: "coretsia/enterprise-sso-saml"
kind: runtime
module_id: "enterprise.sso-saml"

goal: "SAML ACS callback детерміновано верифікується, мапиться в local identity і встановлює session без витоку секретів."
provides:
- "Enterprise SAML SSO (login + ACS callback) як optional module"
- "Deterministic signature/issuer/time-window validation + predictable error mapping"
- "SecretsResolverInterface-based cert/key refs + docs/troubleshooting без витоку секретів"

tags_introduced: []
config_roots_introduced: ["sso_saml"]
artifacts_introduced: []

adr: none  # reuses SSO contracts from epic 6.310.0
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Keep precondition on 6.310.0, але краще прив’язати до Phase 1 contracts:
  - 1.185.0 — Contracts: SSO ports (`Coretsia\Contracts\Sso\*`) MUST exist
  - 1.188.0 — Auth ports MUST exist (Identity)
  - 1.189.0 — Session port MUST exist
  - Tag registry MUST include `routing.route_provider` in `docs/ssot/tags.md`

- Epic prerequisites:
  - 6.310.0 — SSO contracts ports (`Coretsia\Contracts\Sso\*`) MUST already exist

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/src/Sso/SsoProviderInterface.php` — reused port
  - `framework/packages/core/contracts/src/Sso/SsoIdentityClaims.php` — reused VO
  - `framework/packages/core/contracts/src/Sso/IdentityLinkRepositoryInterface.php` — reused link port
  - `framework/packages/core/contracts/src/Sso/SsoException.php` — reused deterministic SSO exception

- Required config roots/keys:
  - N/A

- Required tags:
  - `routing.route_provider` — routes discovery
  - `error.mapper` — deterministic error mapping

- Required contracts / ports:
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` — cert/key refs
  - `Coretsia\Contracts\Session\SessionManagerInterface` — establish session
  - `Coretsia\Contracts\Auth\IdentityInterface` — local identity shape
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — correlation context
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `platform/observability`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\ResponseFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Sso\SsoProviderInterface`
  - `Coretsia\Contracts\Sso\SsoIdentityClaims`
  - `Coretsia\Contracts\Sso\IdentityLinkRepositoryInterface`
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface`
  - `Coretsia\Contracts\Session\SessionManagerInterface`
  - `Coretsia\Contracts\Auth\IdentityInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - routes: `GET /sso/saml/login` → `enterprise/sso-saml` `\Coretsia\Enterprise\SsoSaml\Http\Controller\SamlLoginController`
  - routes: `POST /sso/saml/acs` → `enterprise/sso-saml` `\Coretsia\Enterprise\SsoSaml\Http\Controller\SamlAcsController`
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/enterprise/sso-saml/src/Module/SsoSamlModule.php` — runtime module entry
- [ ] `framework/packages/enterprise/sso-saml/src/Provider/SsoSamlServiceProvider.php` — DI wiring
- [ ] `framework/packages/enterprise/sso-saml/src/Provider/SsoSamlServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/enterprise/sso-saml/src/Provider/Tags.php` — tag/constants owner
- [ ] `framework/packages/enterprise/sso-saml/config/sso_saml.php` — config subtree provider (no repeated root)
- [ ] `framework/packages/enterprise/sso-saml/config/rules.php` — config shape rules
- [ ] `framework/packages/enterprise/sso-saml/README.md` — must include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/enterprise/sso-saml/docs/saml.md` — configuration + redaction + troubleshooting

- [ ] `framework/packages/enterprise/sso-saml/src/Saml/SamlProvider.php` — implements `SsoProviderInterface` (SAML)
- [ ] `framework/packages/enterprise/sso-saml/src/Saml/SamlConfig.php` — policy (issuer/audience/acsUrl/certs refs)
- [ ] `framework/packages/enterprise/sso-saml/src/Saml/SamlResponseVerifier.php` — deterministic signature+claims verification
- [ ] `framework/packages/enterprise/sso-saml/src/Http/Controller/SamlLoginController.php` — initiates SAML login (redirect/post)
- [ ] `framework/packages/enterprise/sso-saml/src/Http/Controller/SamlAcsController.php` — ACS endpoint
- [ ] `framework/packages/enterprise/sso-saml/src/Routing/SamlRouteProvider.php` — routes (tag `routing.route_provider`)
- [ ] `framework/packages/enterprise/sso-saml/src/Exception/SamlProblemMapper.php` — `ExceptionMapperInterface` (tag `error.mapper`)

- [ ] `framework/packages/enterprise/sso-saml/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — baseline: noops don’t throw

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/enterprise/sso-saml/composer.json`
- [ ] `framework/packages/enterprise/sso-saml/src/Module/SsoSamlModule.php` (runtime only)
- [ ] `framework/packages/enterprise/sso-saml/src/Provider/SsoSamlServiceProvider.php` (runtime only)
- [ ] `framework/packages/enterprise/sso-saml/config/sso_saml.php`
- [ ] `framework/packages/enterprise/sso-saml/config/rules.php`
- [ ] `framework/packages/enterprise/sso-saml/README.md`
- [ ] `framework/packages/enterprise/sso-saml/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/enterprise/sso-saml/config/sso_saml.php`
- [ ] Keys (dot):
  - [ ] `sso_saml.enabled` = false
  - [ ] `sso_saml.idp.entity_id` = ''
  - [ ] `sso_saml.idp.sso_url` = ''
  - [ ] `sso_saml.idp.certificate_ref` = ''          # secrets ref
  - [ ] `sso_saml.sp.entity_id` = ''
  - [ ] `sso_saml.sp.acs_url` = ''
  - [ ] `sso_saml.sp.private_key_ref` = ''           # secrets ref (optional if signing requests)
  - [ ] `sso_saml.clock_skew_seconds` = 120
- [ ] Rules:
  - [ ] `framework/packages/enterprise/sso-saml/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (tag `routing.route_provider`, `error.mapper` is owned outside this package)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Enterprise\SsoSaml\Saml\SamlProvider`
  - [ ] registers: `\Coretsia\Enterprise\SsoSaml\Routing\SamlRouteProvider`
  - [ ] adds tag: `routing.route_provider` priority `0` meta `{"owner":"enterprise/sso-saml"}`
  - [ ] registers: `\Coretsia\Enterprise\SsoSaml\Exception\SamlProblemMapper`
  - [ ] adds tag: `error.mapper` priority `190` meta `{"domain":"sso.saml"}`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
- Context writes (safe only):
  - N/A
- Reset discipline:
  - N/A (stateful services only if introduced later)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `sso_saml.login`
  - [ ] `sso_saml.acs`
  - [ ] `sso_saml.verify`
- [ ] Metrics:
  - [ ] `sso_saml.flow_total` (labels: `driver|operation|outcome|status`)
  - [ ] `sso_saml.flow_duration_ms` (labels: `driver|operation|outcome|status`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`, `op→operation`, `reason→status`
- [ ] Logs:
  - [ ] verification failures logged redacted; no SAMLResponse/Assertion dump

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Contracts\Sso\SsoException` — errorCode `CORETSIA_SSO_SAML_RESPONSE_INVALID`
  - [ ] `Coretsia\Contracts\Sso\SsoException` — errorCode `CORETSIA_SSO_SAML_SIGNATURE_INVALID`
  - [ ] `Coretsia\Contracts\Sso\SsoException` — errorCode `CORETSIA_SSO_SAML_TIME_WINDOW_INVALID`
- [ ] Mapping:
  - [ ] new mapper via tag `error.mapper` (`\Coretsia\Enterprise\SsoSaml\Exception\SamlProblemMapper`)
  - [ ] HTTP status hints (optional):
    - invalid/unauthorized → 401

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] SAMLResponse/Assertion XML, cookies/session id, certificates/private keys
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `framework/packages/enterprise/sso-saml/tests/Contract/ContextWriteSafetyTest.php` (N/A)
- [ ] If `kernel.reset` used → `framework/packages/enterprise/sso-saml/tests/Contract/ResetWiringTest.php` (N/A)
- [ ] If metrics/spans/logs exist → `framework/packages/enterprise/sso-saml/tests/Contract/ObservabilityPolicyTest.php`
- [ ] If redaction exists → `framework/packages/enterprise/sso-saml/tests/Contract/RedactionDoesNotLeakTest.php`
- [ ] Baseline cross-cutting noop safety → `framework/packages/enterprise/sso-saml/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/enterprise/sso-saml/tests/Unit/SamlVerifierDeterministicValidationTest.php`
- Contract:
  - [ ] `framework/packages/enterprise/sso-saml/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/enterprise/sso-saml/tests/Contract/ObservabilityPolicyTest.php`
  - [ ] `framework/packages/enterprise/sso-saml/tests/Contract/RedactionDoesNotLeakTest.php`
- Integration:
  - [ ] `framework/packages/enterprise/sso-saml/tests/Integration/AcsRejectsInvalidSignatureTest.php`
  - [ ] `framework/packages/enterprise/sso-saml/tests/Integration/AcsLinksIdentityWhenValidTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] canonical time window validation (`sso.saml.clock_skew_seconds`)
- [ ] Docs updated:
  - [ ] `framework/packages/enterprise/sso-saml/README.md`
  - [ ] `framework/packages/enterprise/sso-saml/docs/saml.md`
- [ ] Scope & intent (MUST)
  - [ ] Додає enterprise SAML SSO (login + ACS callback) як optional module
  - [ ] Використовує SecretsResolverInterface для сертифікатів/keys (refs), без секретів у логах/метриках
  - [ ] Забезпечує deterministic signature/issuer validation + predictable error mapping
- [ ] Non-goals / out of scope
  - [ ] Не робимо “SAML metadata UI” beyond minimal endpoint (optional)
  - [ ] Не додаємо reserved endpoints у `SystemRouteTable`
  - [ ] Не вводимо нові contracts порти (все через `core/contracts` SSO ports)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/http + platform/routing + platform/http-app`
  - [ ] `platform/session + platform/auth`
  - [ ] `platform/logging|tracing|metrics` (impl/noop-safe)
- [ ] Discovery / wiring через tags:
  - [ ] `routing.route_provider`
  - [ ] `error.mapper`
- [ ] SAML ACS callback детерміновано верифікується, мапиться в local identity і встановлює session без витоку секретів.
- [ ] When SP receives valid signed SAMLResponse on `/sso/saml/acs`, then user becomes authenticated via session.

---

### 6.330.0 Enterprise: SCIM (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.330.0"
owner_path: "framework/packages/enterprise/scim/"  # package root

package_id: "enterprise/scim"
composer: "coretsia/enterprise-scim"
kind: runtime
module_id: "enterprise.scim"

goal: "SCIM endpoints працюють як guarded API з deterministic responses і без витоку PII/секретів."
provides:
- "SCIM v2 provisioning endpoints (Users/Groups) як optional enterprise module"
- "Deterministic schema/validation + guard через AuthorizationInterface (без PII leakage)"
- "Reference mapping layer (SCIM resource ↔ internal identity store) без прив’язки до конкретної БД"

tags_introduced: []
config_roots_introduced: ["scim"]
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - Tag registry MUST include `routing.route_provider` in `docs/ssot/tags.md`

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - N/A

- Required tags:
  - `routing.route_provider` — routes discovery
  - `error.mapper` — deterministic error mapping

- Required contracts / ports:
  - `Coretsia\Contracts\Auth\AuthorizationInterface` — SCIM guard ability check
  - `Coretsia\Contracts\Auth\IdentityInterface` — actor identity (safe id)
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — correlation/actor context reads
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `platform/observability`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Message\ResponseFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Auth\AuthorizationInterface`
  - `Coretsia\Contracts\Auth\IdentityInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - routes: `GET /scim/v2/Users` → `enterprise/scim` `\Coretsia\Enterprise\Scim\Http\Controller\UsersController`
  - routes: `GET /scim/v2/Groups` → `enterprise/scim` `\Coretsia\Enterprise\Scim\Http\Controller\GroupsController`
  - notes: base path is configurable via `scim.base_path` (default `/scim/v2`)
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/enterprise/scim/src/Module/ScimModule.php` — runtime module entry
- [ ] `framework/packages/enterprise/scim/src/Provider/ScimServiceProvider.php` — DI wiring
- [ ] `framework/packages/enterprise/scim/src/Provider/ScimServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/enterprise/scim/config/scim.php` — config subtree provider (no repeated root)
- [ ] `framework/packages/enterprise/scim/config/rules.php` — config shape rules
- [ ] `framework/packages/enterprise/scim/README.md` — must include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/enterprise/scim/docs/scim.md` — endpoints, auth abilities, redaction policy

- [ ] `framework/packages/enterprise/scim/src/Routing/ScimRouteProvider.php` — registers SCIM routes (tag `routing.route_provider`)
- [ ] `framework/packages/enterprise/scim/src/Http/Controller/UsersController.php` — Users CRUD (subset)
- [ ] `framework/packages/enterprise/scim/src/Http/Controller/GroupsController.php` — Groups CRUD (subset)
- [ ] `framework/packages/enterprise/scim/src/Scim/Schema/ScimSchemaValidator.php` — deterministic schema validation
- [ ] `framework/packages/enterprise/scim/src/Scim/Mapping/ScimMapper.php` — maps internal model ↔ SCIM JSON
- [ ] `framework/packages/enterprise/scim/src/Scim/Repository/ScimIdentityRepositoryInterface.php` — internal API (package-local)
- [ ] `framework/packages/enterprise/scim/src/Scim/Repository/InMemoryScimIdentityRepository.php` — reference repo for fixtures
- [ ] `framework/packages/enterprise/scim/src/Exception/ScimProblemMapper.php` — `ExceptionMapperInterface` (tag `error.mapper`)

- [ ] `framework/packages/enterprise/scim/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — baseline: noops don’t throw

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/enterprise/scim/composer.json`
- [ ] `framework/packages/enterprise/scim/src/Module/ScimModule.php` (runtime only)
- [ ] `framework/packages/enterprise/scim/src/Provider/ScimServiceProvider.php` (runtime only)
- [ ] `framework/packages/enterprise/scim/config/scim.php`
- [ ] `framework/packages/enterprise/scim/config/rules.php`
- [ ] `framework/packages/enterprise/scim/README.md`
- [ ] `framework/packages/enterprise/scim/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/enterprise/scim/config/scim.php`
- [ ] Keys (dot):
  - [ ] `scim.enabled` = false
  - [ ] `scim.base_path` = '/scim/v2'
  - [ ] `scim.auth.ability` = 'scim.manage'
  - [ ] `scim.pagination.default_limit` = 50
  - [ ] `scim.pagination.max_limit` = 200
- [ ] Rules:
  - [ ] `framework/packages/enterprise/scim/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (tag `routing.route_provider`, `error.mapper` is owned outside this package)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Enterprise\Scim\Routing\ScimRouteProvider`
  - [ ] adds tag: `routing.route_provider` priority `0` meta `{"owner":"enterprise/scim"}`
  - [ ] registers: `\Coretsia\Enterprise\Scim\Exception\ScimProblemMapper`
  - [ ] adds tag: `error.mapper` priority `150` meta `{"domain":"scim"}`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::ACTOR_ID` (safe; set by platform/auth)
- Context writes (safe only):
  - N/A
- Reset discipline:
  - N/A (stateful services only if introduced later)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `scim.request`
  - [ ] `scim.mapping`
- [ ] Metrics:
  - [ ] `scim.request_total` (labels: `method|status|operation|outcome`)
  - [ ] `scim.request_duration_ms` (labels: `method|status|operation|outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `op→operation`, `reason→status`
- [ ] Logs:
  - [ ] logs only method/status/counts; no user payload/email/ids dumped

#### Errors

- [ ] Exceptions introduced:
  - [ ] `\Coretsia\Enterprise\Scim\Exception\ScimException` — errorCode `CORETSIA_SCIM_UNAUTHORIZED`
  - [ ] `\Coretsia\Enterprise\Scim\Exception\ScimException` — errorCode `CORETSIA_SCIM_INVALID_REQUEST`
  - [ ] `\Coretsia\Enterprise\Scim\Exception\ScimException` — errorCode `CORETSIA_SCIM_NOT_FOUND`
- [ ] Mapping:
  - [ ] new mapper via tag `error.mapper` (`\Coretsia\Enterprise\Scim\Exception\ScimProblemMapper`)
  - [ ] HTTP status hints (optional):
    - 400/401/404/409 as SCIM semantics

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] request payload, emails, external ids, Authorization/Cookie/session id
- [ ] Allowed:
  - [ ] safe ids (`actor_id`) and counts; `hash(value)`/`len(value)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `framework/packages/enterprise/scim/tests/Contract/ContextWriteSafetyTest.php` (N/A)
- [ ] If `kernel.reset` used → `framework/packages/enterprise/scim/tests/Contract/ResetWiringTest.php` (N/A)
- [ ] If metrics/spans/logs exist → `framework/packages/enterprise/scim/tests/Contract/ObservabilityPolicyTest.php`
- [ ] If redaction exists → `framework/packages/enterprise/scim/tests/Contract/RedactionDoesNotLeakTest.php`
- [ ] Baseline cross-cutting noop safety → `framework/packages/enterprise/scim/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/enterprise/scim/tests/Unit/SchemaValidatorDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/enterprise/scim/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/enterprise/scim/tests/Contract/ObservabilityPolicyTest.php`
  - [ ] `framework/packages/enterprise/scim/tests/Contract/RedactionDoesNotLeakTest.php`
- Integration:
  - [ ] `framework/packages/enterprise/scim/tests/Integration/AuthorizationIsEnforcedTest.php`
  - [ ] `framework/packages/enterprise/scim/tests/Integration/ListUsersIsDeterministicTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Observability policy satisfied (no high-cardinality labels)
- [ ] Docs updated:
  - [ ] `framework/packages/enterprise/scim/README.md`
  - [ ] `framework/packages/enterprise/scim/docs/scim.md`
- [ ] Scope & intent (MUST)
  - [ ] Додає SCIM v2 provisioning endpoints (Users/Groups) як optional enterprise module
  - [ ] Гарантує deterministic schema/validation + guard через AuthorizationInterface (без PII leakage)
  - [ ] Дає reference mapping layer (SCIM resource ↔ internal identity store) без прив’язки до конкретної БД
- [ ] Non-goals / out of scope
  - [ ] Не робимо “повну IAM платформу” (тільки SCIM surface + reference mapping)
  - [ ] Не використовуємо userId/email як metric labels (заборонено)
  - [ ] Не додаємо reserved endpoints у `SystemRouteTable` (SCIM routes — app routes)
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/http + platform/routing + platform/http-app`
  - [ ] `platform/auth` (binding для `AuthorizationInterface`)
  - [ ] `platform/logging|tracing|metrics` (impl/noop-safe)
- [ ] Discovery / wiring через tags:
  - [ ] `routing.route_provider`
  - [ ] `error.mapper`
- [ ] SCIM endpoints працюють як guarded API з deterministic responses і без витоку PII/секретів.
- [ ] When a SCIM client calls `GET /scim/v2/Users`, then it receives a deterministic list response and authorization is enforced.

---

### 6.340.0 Enterprise Tenancy (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.340.0"
owner_path: "framework/packages/enterprise/tenancy/"  # package root

package_id: "enterprise/tenancy"
composer: "coretsia/enterprise-tenancy"
kind: runtime
module_id: "enterprise.tenancy"

goal: "Кожен HTTP request (до routing) має deterministic tenant resolution і safe `tenant_id` у ContextStore без витоку PII/секретів."
provides:
- "Multi-tenant контекст для HTTP UoW: deterministic визначення tenant + safe `tenant_id` у ContextStore"
- "Pluggable resolvers (host/header/path/jwt_claim) з deterministic order і safety rails"
- "Optional tenant-aware adapters (cache/queue/filesystem/rate-limit) без high-cardinality leakage"

tags_introduced: ["tenancy.resolver"]
config_roots_introduced: ["tenancy"]
artifacts_introduced: []

adr: docs/adr/ADR-0074-enterprise-tenancy-contracts.md
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - Foundation ContextKeys MUST include TENANT_ID (see Foundation insert 1.210.0 extension)

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - N/A

- Required tags:
  - `http.middleware.app_pre` — middleware slot у platform/http (runtime policy)
  - `kernel.reset` — reset discipline (тільки якщо stateful resolver/cache введені)
  - `tenancy.resolver` — deterministic resolver discovery (якщо tag-based)

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — context reads
  - `Coretsia\Contracts\Runtime\ResetInterface` — stateful resolver/cache reset (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — spans
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics
  - `Coretsia\Contracts\RateLimit\RateLimitStoreInterface` — optional adapter
  - `Coretsia\Contracts\Queue\QueueInterface` — optional adapter
  - `Coretsia\Contracts\Filesystem\DiskInterface` — optional adapter

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http` (transport boundary; tenancy middleware підключається через DI tags)
- `platform/observability`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
  - `Psr\SimpleCache\CacheInterface` (optional adapter)
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\RateLimit\RateLimitStoreInterface` (optional)
  - `Coretsia\Contracts\Queue\QueueInterface` (optional)
  - `Coretsia\Contracts\Filesystem\DiskInterface` (optional)
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Context\ContextStore`
  - `Coretsia\Foundation\Context\ContextKeys`
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - middleware slots/tags: `http.middleware.app_pre` priority `500` meta `{"toggle":"tenancy.http.enabled","reason":"tenant context before session/auth"}`
  - handler: `\Coretsia\Tenancy\Http\Middleware\TenantContextMiddleware::class`
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Resolver ordering — make it single-choice (no “if tag-based”)
- Cement policy inside epic:
  - Discovery: ALL resolvers are registered as services and tagged `tenancy.resolver`.
  - Effective order: `tenancy.resolvers.order` is the canonical ordered list of resolver names.
    - Unknown name → deterministic fail `CORETSIA_TENANCY_RESOLVER_UNKNOWN`
    - Duplicate name → deterministic fail `CORETSIA_TENANCY_RESOLVER_DUPLICATE`
  - TagRegistry ordering (priority/id) is used only as a stable tiebreaker for same-name or multiple instances (SHOULD NOT happen).

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/enterprise/tenancy/src/Module/TenancyModule.php` — runtime module entry
- [ ] `framework/packages/enterprise/tenancy/src/Provider/TenancyServiceProvider.php` — DI wiring
- [ ] `framework/packages/enterprise/tenancy/src/Provider/TenancyServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/enterprise/tenancy/src/Provider/Tags.php` — tag/constants owner
- [ ] `framework/packages/enterprise/tenancy/config/tenancy.php` — config subtree provider (no repeated root)
- [ ] `framework/packages/enterprise/tenancy/config/rules.php` — config shape rules
- [ ] `framework/packages/enterprise/tenancy/README.md` — must include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/enterprise/tenancy/docs/tenancy.md` — resolvers, ordering, how to override/disable middleware

- [ ] `framework/packages/core/contracts/src/Tenancy/TenantContext.php` — VO (tenantId + source + attributes json-like)
- [ ] `framework/packages/core/contracts/src/Tenancy/TenantResolverInterface.php` — resolve tenant from request/context
- [ ] `framework/packages/core/contracts/src/Tenancy/TenancyException.php` — deterministic codes

- [ ] `docs/adr/ADR-0074-enterprise-tenancy-contracts.md` — ADR for tenancy contracts + boundary
- [ ] `docs/adr/ADR-0030-enterprise-tenancy-contracts.md` — legacy ADR reference from prior draft (resolve/cleanup when spec locked)

- [ ] `framework/packages/enterprise/tenancy/src/Tenancy/TenantResolverRegistry.php` — collects resolvers deterministically
- [ ] `framework/packages/enterprise/tenancy/src/Tenancy/Resolver/HostTenantResolver.php` — host-based
- [ ] `framework/packages/enterprise/tenancy/src/Tenancy/Resolver/HeaderTenantResolver.php` — header-based
- [ ] `framework/packages/enterprise/tenancy/src/Tenancy/Resolver/PathTenantResolver.php` — path-prefix based
- [ ] `framework/packages/enterprise/tenancy/src/Tenancy/Resolver/JwtClaimTenantResolver.php` — claim-based (no raw token logging)
- [ ] `framework/packages/enterprise/tenancy/src/Http/Middleware/TenantContextMiddleware.php` — PSR-15 middleware

- [ ] `framework/packages/enterprise/tenancy/src/Adapters/Cache/TenantPrefixedCache.php` — PSR-16 adapter (optional)
- [ ] `framework/packages/enterprise/tenancy/src/Adapters/Queue/TenantPrefixedQueue.php` — QueueInterface adapter (optional)
- [ ] `framework/packages/enterprise/tenancy/src/Adapters/Filesystem/TenantPrefixedDisk.php` — DiskInterface adapter (optional)
- [ ] `framework/packages/enterprise/tenancy/src/Adapters/RateLimit/TenantPrefixedRateLimitStore.php` — store adapter (optional)

- [ ] `framework/packages/enterprise/tenancy/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — baseline: noops don’t throw

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0074-enterprise-tenancy-contracts.md`
- docs/ssot/tags.md — add `tenancy.resolver` row (owner enterprise/tenancy)

#### Package skeleton (if type=package)

- [ ] `framework/packages/enterprise/tenancy/composer.json`
- [ ] `framework/packages/enterprise/tenancy/src/Module/TenancyModule.php` (runtime only)
- [ ] `framework/packages/enterprise/tenancy/src/Provider/TenancyServiceProvider.php` (runtime only)
- [ ] `framework/packages/enterprise/tenancy/config/tenancy.php`
- [ ] `framework/packages/enterprise/tenancy/config/rules.php`
- [ ] `framework/packages/enterprise/tenancy/README.md`
- [ ] `framework/packages/enterprise/tenancy/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/enterprise/tenancy/config/tenancy.php`
- [ ] Keys (dot):
  - [ ] `tenancy.enabled` = false
  - [ ] `tenancy.http.enabled` = true
  - [ ] `tenancy.resolvers.order` = ['host','header','path','jwt_claim']   # deterministic list
  - [ ] `tenancy.resolvers.host.enabled` = true
  - [ ] `tenancy.resolvers.host.allowed_suffixes` = []                      # list
  - [ ] `tenancy.resolvers.header.enabled` = true
  - [ ] `tenancy.resolvers.header.name` = 'X-Tenant-Id'
  - [ ] `tenancy.resolvers.path.enabled` = false
  - [ ] `tenancy.resolvers.path.prefix` = '/t/'
  - [ ] `tenancy.resolvers.jwt_claim.enabled` = false
  - [ ] `tenancy.resolvers.jwt_claim.claim` = 'tenant_id'
  - [ ] `tenancy.adapters.prefix_separator` = ':'                           # deterministic key building
- [ ] Rules:
  - [ ] `framework/packages/enterprise/tenancy/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/enterprise/tenancy/src/Provider/Tags.php` (constants)
  - [ ] constants:
    - [ ] `TENANCY_RESOLVER`
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Tenancy\Http\Middleware\TenantContextMiddleware::class`
  - [ ] adds tag: `http.middleware.app_pre` priority `500` meta `{"toggle":"tenancy.http.enabled","reason":"tenant context before session/auth"}`
  - [ ] registers: `\Coretsia\Tenancy\Tenancy\TenantResolverRegistry::class`
  - [ ] registers: `HostTenantResolver|HeaderTenantResolver|PathTenantResolver|JwtClaimTenantResolver`
  - [ ] adds tag: `tenancy.resolver` priority `<int>` meta `{"name":"host|header|path|jwt_claim"}` (if using tags)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::CLIENT_IP` (optional)
- [ ] Context writes (safe only):
  - [ ] `ContextKeys::TENANT_ID` (safe id; no PII)
- [ ] Reset discipline:
  - [ ] all stateful services implement `ResetInterface` (if resolver caches state)
  - [ ] all stateful services tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `tenancy.resolve` (attrs: `resolver`, `outcome`; tenant_id NOT included)
- [ ] Metrics:
  - [ ] `tenancy.resolve_total` (labels: `driver|outcome|operation`)
  - [ ] `tenancy.resolve_duration_ms` (labels: `driver|outcome|operation`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`, `op→operation`
- [ ] Logs:
  - [ ] resolver chosen + outcome; no raw host/header/token dump

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Contracts\Tenancy\TenancyException` — errorCode `CORETSIA_TENANCY_NOT_RESOLVED`
  - [ ] `Coretsia\Contracts\Tenancy\TenancyException` — errorCode `CORETSIA_TENANCY_INVALID_TENANT_ID`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)
  - [ ] optional HTTP hint: invalid tenant id → 400 (via mapper if introduced later)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw host/header/token/session id
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` only (if needed)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `framework/packages/enterprise/tenancy/tests/Contract/ContextWriteSafetyTest.php`
- [ ] If `kernel.reset` used → `framework/packages/enterprise/tenancy/tests/Contract/ResetWiringTest.php`
- [ ] If metrics/spans/logs exist → `framework/packages/enterprise/tenancy/tests/Contract/ObservabilityPolicyTest.php`
- [ ] If redaction exists → `framework/packages/enterprise/tenancy/tests/Contract/RedactionDoesNotLeakTest.php`
- [ ] Baseline cross-cutting noop safety → `framework/packages/enterprise/tenancy/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php` (if needed)
- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/enterprise/tenancy/tests/Unit/ResolverOrderIsDeterministicTest.php`
  - [ ] `framework/packages/enterprise/tenancy/tests/Unit/TenantIdValidationTest.php`
- Contract:
  - [ ] `framework/packages/enterprise/tenancy/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/enterprise/tenancy/tests/Contract/ContextWriteSafetyTest.php`
  - [ ] `framework/packages/enterprise/tenancy/tests/Contract/ResetWiringTest.php`
  - [ ] `framework/packages/enterprise/tenancy/tests/Contract/ObservabilityPolicyTest.php`
  - [ ] `framework/packages/enterprise/tenancy/tests/Contract/RedactionDoesNotLeakTest.php`
- Integration:
  - [ ] `framework/packages/enterprise/tenancy/tests/Integration/MiddlewareWritesTenantIdToContextStoreTest.php`
  - [ ] `framework/packages/enterprise/tenancy/tests/Integration/DeterministicResolverSelectionTest.php`
  - [ ] `framework/packages/enterprise/tenancy/tests/Integration/NoRawHostHeaderLoggedTest.php`
  - [ ] `framework/packages/enterprise/tenancy/tests/Integration/Http/TenancyMiddlewareActiveInUserBeforeRoutingSlotTest.php` (package/E2E)
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Observability policy satisfied (tenant_id not used as label)
- [ ] Determinism:
  - [ ] resolver order stable: config list order preserved; any derived lists are deterministic (strcmp); TagRegistry ordering is not re-sorted by consumers
- [ ] Docs updated:
  - [ ] `framework/packages/enterprise/tenancy/README.md`
  - [ ] `framework/packages/enterprise/tenancy/docs/tenancy.md`
  - [ ] ADR present (`docs/adr/ADR-0074-...`) (+ legacy ADR reference resolved/removed if redundant)
- [ ] Scope & intent (MUST)
  - [ ] Додає multi-tenant контекст для HTTP UoW: детерміноване визначення tenant + запис `tenant_id` у ContextStore (safe id)
  - [ ] Дає pluggable resolvers (host/header/path/jwt_claim) з deterministic order і safety rails
  - [ ] Дає optional adapters для tenant-aware key prefixing (cache/queue/filesystem/rate-limit) без high-cardinality leakage
- [ ] Non-goals / out of scope
  - [ ] Не вводимо нові labels поза allowlist (tenant_id не label)
  - [ ] Не реалізуємо повний “tenant management UI/DB” (це app/domain responsibility)
  - [ ] Не ламаємо HTTP stack model: middleware лише у `app_pre` і керується enable/config
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/http` виконує `http.middleware.app_pre`
  - [ ] `platform/logging|tracing|metrics` (impl/noop-safe)
  - [ ] якщо tenant-aware adapters використовуються:
    - [ ] cache binding → `Psr\SimpleCache\CacheInterface`
    - [ ] queue binding → `Coretsia\Contracts\Queue\QueueInterface`
    - [ ] filesystem binding → `Coretsia\Contracts\Filesystem\DiskInterface`
- [ ] Discovery / wiring через tags:
  - [ ] `http.middleware.app_pre`
  - [ ] `tenancy.resolver` (якщо resolver discovery tag-based)
- [ ] Кожен HTTP request (до routing) має deterministic tenant resolution і safe `tenant_id` у ContextStore без витоку PII/секретів.
- [ ] When request comes with a matching tenant header, then `tenant_id` is written to ContextStore and resolvers order is deterministic.

---

### 6.350.0 Enterprise Audit (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.350.0"
owner_path: "framework/packages/enterprise/audit/"  # package root

package_id: "enterprise/audit"
composer: "coretsia/enterprise-audit"
kind: runtime
module_id: "enterprise.audit"

goal: "Audit entries append’яться детерміновано, включають safe context, і не можуть “протекти” між UoW."
provides:
- "Enterprise audit trail: append-only записи з safe context (correlation/request/tenant/actor ids) без payload/PII"
- "Vendor-agnostic contracts ports/VO для append/read (admin/devtools) без прив’язки до store vendor"
- "Optional tamper-evident deterministic hash chain + reference DB store (SQLite-first) + flush policy via kernel hooks"

tags_introduced: []
config_roots_introduced: ["audit"]
artifacts_introduced: []

adr: docs/adr/ADR-0075-enterprise-audit-contracts.md
ssot_refs: []
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
  - `kernel.hook.after_uow` — flush audit buffer after successful UoW
  - `kernel.reset` — clear audit buffer between UoW

- Required contracts / ports:
  - `Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface` — kernel hook port
  - `Coretsia\Contracts\Runtime\ResetInterface` — reset discipline
  - `Coretsia\Contracts\Database\ConnectionInterface` — DB access port
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — safe context reads
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — spans
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel` (UoW hooks / flush policy)
- `platform/database` (reference store)

Forbidden:

- `platform/http`
- `platform/observability`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
  - `Psr\Clock\ClockInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Coretsia\Contracts\Database\ConnectionInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Context\ContextKeys`
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - N/A
- Kernel hooks/tags:
  - `kernel.hook.after_uow` priority `60` meta `{"reason":"flush audit buffer on success"}`
  - `kernel.reset` priority `0` meta `{"reason":"clear audit buffer"}`
- Artifacts:
  - N/A

### Flush policy vs Outcome tokens (Kernel 1.270.0)
- Lock rule in epic text:
  - audit.flush.on_outcome MUST be one of kernel Outcome tokens:
    - success | handled_error | fatal_error
  - If set to 'success', hook MUST flush only when Outcome == success.
  - Regardless of outcome, buffer MUST be cleared via reset discipline (kernel.reset).

### Determinism scope clarification (avoid implied deterministic timestamps)
- Add note (one-liner in goal/provides or docs):
  - "Event time is produced by Clock (runtime-dependent); determinism constraints apply to ordering, hashing, and redaction — not to wall-clock values."

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/enterprise/audit/src/Module/AuditModule.php` — runtime module entry
- [ ] `framework/packages/enterprise/audit/src/Provider/AuditServiceProvider.php` — DI wiring
- [ ] `framework/packages/enterprise/audit/src/Provider/AuditServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/enterprise/audit/src/Provider/Tags.php` — tag/constants owner
- [ ] `framework/packages/enterprise/audit/config/audit.php` — config subtree provider (no repeated root)
- [ ] `framework/packages/enterprise/audit/config/rules.php` — config shape rules
- [ ] `framework/packages/enterprise/audit/README.md` — must include: Observability / Errors / Security-Redaction
- [ ] `framework/packages/enterprise/audit/docs/audit.md` — schema, retention notes, redaction policy

- [ ] `framework/packages/core/contracts/src/Audit/AuditEntry.php` — VO (schemaVersion, ts, action, safe ids, hashes)
- [ ] `framework/packages/core/contracts/src/Audit/AuditAppenderInterface.php` — append API (no payload)
- [ ] `framework/packages/core/contracts/src/Audit/AuditReaderInterface.php` — read API for admin/devtools (filtered/paged)
- [ ] `framework/packages/core/contracts/src/Audit/AuditException.php` — deterministic error codes

- [ ] `docs/adr/ADR-0075-enterprise-audit-contracts.md` — ADR for audit contracts + boundary

- [ ] `framework/packages/enterprise/audit/database/migrations/2026_...._create_audit_log_table.php` — append-only table
- [ ] `framework/packages/enterprise/audit/src/Audit/AuditBuffer.php` — in-memory buffer (implements ResetInterface)
- [ ] `framework/packages/enterprise/audit/src/Audit/DbAuditRepository.php` — append/read via platform/database
- [ ] `framework/packages/enterprise/audit/src/Audit/HashChain/HashChainBuilder.php` — optional tamper-evident chain (deterministic)
- [ ] `framework/packages/enterprise/audit/src/Kernel/AuditFlushAfterUowHook.php` — AfterUoW hook flush (success only)

- [ ] `framework/packages/enterprise/audit/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — baseline: noops don’t throw

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0075-enterprise-audit-contracts.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/enterprise/audit/composer.json`
- [ ] `framework/packages/enterprise/audit/src/Module/AuditModule.php` (runtime only)
- [ ] `framework/packages/enterprise/audit/src/Provider/AuditServiceProvider.php` (runtime only)
- [ ] `framework/packages/enterprise/audit/config/audit.php`
- [ ] `framework/packages/enterprise/audit/config/rules.php`
- [ ] `framework/packages/enterprise/audit/README.md`
- [ ] `framework/packages/enterprise/audit/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/enterprise/audit/config/audit.php`
- [ ] Keys (dot):
  - [ ] `audit.enabled` = false
  - [ ] `audit.store` = 'db'
  - [ ] `audit.hash_chain.enabled` = true
  - [ ] `audit.flush.on_outcome` = 'success'              # fixed; documented
  - [ ] `audit.max_buffered_entries` = 200
- [ ] Rules:
  - [ ] `framework/packages/enterprise/audit/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] N/A (tag `kernel.hook.after_uow`, `kernel.reset` is owned outside this package)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Enterprise\Audit\Kernel\AuditFlushAfterUowHook`
  - [ ] adds tag: `kernel.hook.after_uow` priority `60` meta `{"reason":"flush audit buffer on success"}`
  - [ ] registers: `\Coretsia\Enterprise\Audit\Audit\AuditBuffer`
  - [ ] adds tag: `kernel.reset` priority `0` meta `{"reason":"clear audit buffer"}`
  - [ ] registers: `\Coretsia\Enterprise\Audit\Audit\DbAuditRepository`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
  - [ ] `ContextKeys::ACTOR_ID` (safe; if present)
  - [ ] `ContextKeys::TENANT_ID` (safe; if present)
  - [ ] `ContextKeys::REQUEST_ID` (optional; safe id)
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] all stateful services implement `ResetInterface`
  - [ ] all stateful services tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `audit.append`
  - [ ] `audit.flush`
- [ ] Metrics:
  - [ ] `audit.append_total` (labels: `driver|outcome|operation`)
  - [ ] `audit.append_duration_ms` (labels: `driver|outcome|operation`)
  - [ ] `audit.append_failed_total` (labels: `driver|outcome|operation`)
- [ ] Label normalization applied (if needed):
  - [ ] `op→operation`, `via→driver`
- [ ] Logs:
  - [ ] errors only include counts + error codes; no payload/PII

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Contracts\Audit\AuditException` — errorCode `CORETSIA_AUDIT_APPEND_FAILED`
  - [ ] `Coretsia\Contracts\Audit\AuditException` — errorCode `CORETSIA_AUDIT_INVALID_ENTRY`
  - [ ] `Coretsia\Contracts\Audit\AuditException` — errorCode `CORETSIA_AUDIT_HASH_CHAIN_INVALID` (optional verify path)
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] payloads, raw SQL, tokens/session ids, PII fields
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id`, `tenant_id`)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `framework/packages/enterprise/audit/tests/Contract/ContextWriteSafetyTest.php` (N/A)
- [ ] If `kernel.reset` used → `framework/packages/enterprise/audit/tests/Contract/ResetWiringTest.php`
- [ ] If metrics/spans/logs exist → `framework/packages/enterprise/audit/tests/Contract/ObservabilityPolicyTest.php`
- [ ] If redaction exists → `framework/packages/enterprise/audit/tests/Contract/RedactionDoesNotLeakTest.php`
- [ ] Baseline cross-cutting noop safety → `framework/packages/enterprise/audit/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/enterprise/audit/tests/Unit/HashChainDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/enterprise/audit/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/enterprise/audit/tests/Contract/ResetWiringTest.php`
  - [ ] `framework/packages/enterprise/audit/tests/Contract/ObservabilityPolicyTest.php`
  - [ ] `framework/packages/enterprise/audit/tests/Contract/RedactionDoesNotLeakTest.php`
- Integration:
  - [ ] `framework/packages/enterprise/audit/tests/Integration/AppendOnlySemanticsTest.php`
  - [ ] `framework/packages/enterprise/audit/tests/Integration/AuditIncludesSafeContextFieldsTest.php`
  - [ ] `framework/packages/enterprise/audit/tests/Integration/BufferResetBetweenUowTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)
  - [ ] gates updated (if new invariants)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Observability policy satisfied (no PII/high-card labels)
- [ ] Determinism:
  - [ ] hash chain deterministic for same inputs (if enabled)
- [ ] Docs updated:
  - [ ] `framework/packages/enterprise/audit/README.md`
  - [ ] `framework/packages/enterprise/audit/docs/audit.md`
  - [ ] ADR present (`docs/adr/ADR-0075-...`) (+ legacy ADR reference resolved/removed if redundant)
- [ ] Scope & intent (MUST)
  - [ ] Додає enterprise audit trail: append-only записи з safe context (correlation/request/tenant/actor ids) без payload/PII
  - [ ] Дає vendor-agnostic contracts ports/VO для аудиту (щоб devtools/admin-panel могли читати без прив’язки)
  - [ ] Підтримує optional tamper-evident hash chain (детермінований) + reference DB store (SQLite-first)
- [ ] Non-goals / out of scope
  - [ ] Не робимо “SIEM exporter” (це окремі integrations)
  - [ ] Не використовуємо actor_id/tenant_id як metric labels (заборонено)
  - [ ] Не зберігаємо raw payload/PII у audit entries
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/logging|tracing|metrics` (impl/noop-safe)
  - [ ] `platform/database` надає DB driver via integrations
- [ ] Discovery / wiring через tags:
  - [ ] `kernel.hook.after_uow`
  - [ ] `kernel.reset`
- [ ] Audit entries append’яться детерміновано, включають safe context, і не можуть “протекти” між UoW.
- [ ] When an audited action occurs during a successful UoW, then an append-only record is persisted and includes correlation/actor/tenant safe ids.

---

### 6.360.0 Enterprise Compliance (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.360.0"
owner_path: "framework/packages/enterprise/compliance/"

package_id: "enterprise/compliance"
composer: "coretsia/enterprise-compliance"
kind: runtime
module_id: "enterprise.compliance"

goal: "Production preset не може пройти CI з небезпечним compliance/retention/redaction конфігом; gate падає детерміновано з чітким reason."
provides:
- "Мінімальний enterprise compliance posture: policy VO + deterministic redaction helpers + retention planner skeleton"
- "Deterministic CI gate, що блокує небезпечні production конфіги (compliance policy gate)"
- "Enforcement: “no secrets/PII” у compliance outputs через gate + тести"

tags_introduced: []
config_roots_introduced:
- "compliance"

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
- docs/ssot/config-roots.md  # root ownership cemented
- docs/ssot/observability.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - `framework/tools/gates/` — існуючий gate harness/runner у CI (цей епік додає gate-файл)

- Required config roots/keys:
  - N/A (цей епік вводить root `compliance`)

- Required tags:
  - N/A

- Required contracts / ports:
  - `Psr\Log\LoggerInterface` — optional logs
  - `Coretsia\Contracts\Crypto\EncrypterInterface` — optional (encryption checks)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — optional spans
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — optional metrics
  - `Coretsia\Foundation\Discovery\DeterministicOrder` — optional stable ordering helper

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
  - `Coretsia\Contracts\Crypto\EncrypterInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)
- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder` (optional)

- Runtime expectation (policy, NOT deps):
  - compliance runtime module optional (вмикається пресетами/бандлами), але CI gate доступний завжди у framework tools
  - discovery/wiring: без DI tags

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/enterprise/compliance/src/Module/ComplianceModule.php` — runtime module
- [ ] `framework/packages/enterprise/compliance/src/Provider/ComplianceServiceProvider.php` — DI wiring
- [ ] `framework/packages/enterprise/compliance/src/Provider/ComplianceServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/enterprise/compliance/config/compliance.php` — config subtree (root `compliance`)
- [ ] `framework/packages/enterprise/compliance/config/rules.php` — config rules/shape enforcement
- [ ] `framework/packages/enterprise/compliance/README.md` — package README (Observability / Errors / Security-Redaction)
- [ ] `framework/packages/enterprise/compliance/src/Policy/CompliancePolicy.php` — VO (retention/redaction/encryption flags; ints only)
- [ ] `framework/packages/enterprise/compliance/src/Policy/RedactionPolicy.php` — VO (what to redact)
- [ ] `framework/packages/enterprise/compliance/src/Redaction/Redactor.php` — deterministic redaction helpers
- [ ] `framework/packages/enterprise/compliance/src/Retention/RetentionPlanner.php` — outputs deterministic plan skeleton
- [ ] `framework/packages/enterprise/compliance/src/Gdpr/ForgetMeWorkflow.php` — skeleton workflow (no PII logging)
- [ ] `framework/tools/gates/compliance_policy_gate.php` — CI gate enforcing minimum production posture
  - MUST use existing tools bootstrap + ConsoleOutput writer (no echo/print).
  - MUST NOT use `json_encode(...)` under `framework/tools/**` (internal-toolkit gate rule).
  - Output policy:
    - line1: deterministic `CODE`
    - line2+: diagnostics lines, stable tokens only (no raw values), sorted `strcmp`
  - MUST NOT print:
    - any secret/PII
    - raw config values
    - absolute paths
  - Allowed diagnostics:
    - `keyPath` only (e.g. `compliance.retention.audit_days`)
    - reason tokens (e.g. `retention-disabled`, `audit-days-too-low`)
- [ ] `docs/ops/compliance.md` — ops how-to (config + gate rules)

Tests:
- [ ] `framework/packages/enterprise/compliance/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — runtime cross-cutting contract
- [ ] `framework/packages/enterprise/compliance/tests/Unit/RetentionPlannerDeterministicTest.php` — unit determinism proof
- [ ] `framework/packages/enterprise/compliance/tests/Integration/RedactorDoesNotLeakValuesTest.php` — integration redaction proof
- [ ] `framework/tools/gates/tests/Integration/CompliancePolicyGateFailsDeterministicallyTest.php` — gate determinism proof

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/enterprise/compliance/composer.json`
- [ ] `framework/packages/enterprise/compliance/src/Module/ComplianceModule.php`
- [ ] `framework/packages/enterprise/compliance/src/Provider/ComplianceServiceProvider.php`
- [ ] `framework/packages/enterprise/compliance/config/compliance.php`
- [ ] `framework/packages/enterprise/compliance/config/rules.php`
- [ ] `framework/packages/enterprise/compliance/README.md`
- [ ] `framework/packages/enterprise/compliance/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/enterprise/compliance/config/compliance.php`
- [ ] Keys (dot):
  - [ ] `compliance.enabled` = false
  - [ ] `compliance.redaction.enabled` = true
  - [ ] `compliance.retention.enabled` = true
  - [ ] `compliance.retention.default_days` = 30
  - [ ] `compliance.retention.audit_days` = 365
  - [ ] `compliance.gdpr.forget_me.enabled` = false
  - [ ] `compliance.encryption.required` = false
- [ ] Rules:
  - [ ] `framework/packages/enterprise/compliance/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (no new tags)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Enterprise\Compliance\Policy\CompliancePolicy`
  - [ ] registers: `\Coretsia\Enterprise\Compliance\Redaction\Redactor`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads:
  - N/A
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] stateful services implement `ResetInterface` (if any)
  - [ ] tagged `kernel.reset` (if any)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `compliance.check` (optional)
- [ ] Metrics:
  - [ ] `compliance.check_total` (labels: `outcome`)
  - [ ] `compliance.check_duration_ms` (labels: `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `op→operation`
- [ ] Logs:
  - [ ] only policy flags + counts; no secrets/PII

#### Errors

- [ ] Exceptions introduced:
  - [ ] `\Coretsia\Enterprise\Compliance\Exception\ComplianceException` — errorCode `CORETSIA_COMPLIANCE_POLICY_INVALID`
  - [ ] `\Coretsia\Enterprise\Compliance\Exception\ComplianceException` — errorCode `CORETSIA_COMPLIANCE_RETENTION_INVALID`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] any PII/secrets in gate output or logs
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / config key names only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php`  
  (N/A: no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php`  
  (N/A: no required stateful services)
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`  
  (asserts: names + label allowlist + no PII)
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`  
  (asserts: gate/log outputs never include raw secrets/PII)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] N/A (gate + unit/integration tests достатні)
- [ ] Fake adapters:
  - [ ] FakeLogger/FakeTracer/FakeMeter for assertions (if observability enabled in tests)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/enterprise/compliance/tests/Unit/RetentionPlannerDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/enterprise/compliance/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/enterprise/compliance/tests/Integration/RedactorDoesNotLeakValuesTest.php`
  - [ ] `framework/tools/gates/tests/Integration/CompliancePolicyGateFailsDeterministicallyTest.php`
- Gates/Arch:
  - [ ] `framework/tools/gates/compliance_policy_gate.php` wired into CI `gates` job

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: gate output stable (same config → same fail reason)
- [ ] Docs updated:
  - [ ] `framework/packages/enterprise/compliance/README.md`
  - [ ] `docs/ops/compliance.md`
- [ ] What problem this epic solves
  - [ ] Дає мінімальний enterprise compliance posture: policy VO + redaction helpers + retention планер skeleton
  - [ ] Додає CI gate, який детерміновано блокує небезпечні production конфіги (compliance policy gate)
  - [ ] Забезпечує “no secrets/PII” у compliance outputs; enforcement через gate + тести
- [ ] Non-goals / out of scope
  - [ ] Не реалізує повний GDPR/ISO програма (тільки мінімальні rails + hooks)
  - [ ] Не вводить нові contracts порти без потреби (reuse existing)
  - [ ] Не робить runtime “policy engine”; тут policy data + tooling enforcement
- [ ] Usually present when enabled in presets/bundles:
  - [ ] compliance runtime module is optional; CI gate is always available in framework tools
- [ ] Production preset не може пройти CI з небезпечним compliance/retention/redaction конфігом; gate падає детерміновано з чітким reason.
- [ ] When CI runs gates for production env and policy is missing retention settings, then `compliance_policy_gate.php` fails with deterministic error code.

---

### 6.370.0 integrations/secrets-vault + ops docs (Vault) (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.370.0"
owner_path: "framework/packages/integrations/secrets-vault/"

package_id: "integrations/secrets-vault"
composer: "coretsia/integrations-secrets-vault"
kind: runtime
module_id: "integrations.secrets-vault"

goal: "Увімкнення `integrations.secrets-vault` робить `secret_ref` resolvable через Vault at runtime, з детермінованим клієнтом/запитами та нульовим leakage секретних значень."
provides:
- "`integrations/secrets-vault` модуль, що біндить `SecretsResolverInterface` на Vault backend."
- "Vault PSR-18 wrapper/client з deterministic request/headers semantics."
- "Contract-like гарантії: secret values ніколи не друкуються (only refs/hashes/len)."
- "Ops-документація: `secret_ref` usage + redaction rules + troubleshooting + Vault examples."

tags_introduced: []
config_roots_introduced: ["secrets_vault"]
artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required contracts / ports (runtime usage):
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` — binding target (provided by `platform/secrets`; overridden by integration when enabled).
  - `Psr\Http\Client\ClientInterface` — outbound HTTP client (provided by `platform/http-client` or any PSR-18 binding).
  - `Psr\Log\LoggerInterface` — safe logging (noop-safe allowed).
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — OPTIONAL (noop-safe).
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — OPTIONAL (noop-safe).

- Runtime expectation (policy, NOT deps):
  - `platform/secrets` provides default resolver; integrations override/bind by module enable.
  - No secret values in artifacts/config; only `secret_ref` references.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http`
- `platform/cli`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Client\ClientInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` (optional)
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

Docs:
- [ ] `docs/ops/secrets.md` — canonical guide (includes Vault section: ref format, auth, timeouts, troubleshooting; never print values)
  - `secret_ref` format
  - redaction rules (never print values)
  - troubleshooting (timeouts, auth)
  - examples for Vault
- [ ] `docs/architecture/secrets-boundary.md` — boundary rules: artifacts/config never embed secrets

- [ ] `framework/packages/integrations/secrets-vault/composer.json`
- [ ] `framework/packages/integrations/secrets-vault/src/Module/SecretsVaultModule.php` — module (Vault)
- [ ] `framework/packages/integrations/secrets-vault/src/Provider/SecretsVaultServiceProvider.php` — wiring
- [ ] `framework/packages/integrations/secrets-vault/src/Provider/SecretsVaultServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/secrets-vault/config/secrets_vault.php` — config subtree (root: `secrets_vault`; returns subtree)
- [ ] `framework/packages/integrations/secrets-vault/config/rules.php` — rules
- [ ] `framework/packages/integrations/secrets-vault/README.md` — Observability / Errors / Security-Redaction
- [ ] `framework/packages/integrations/secrets-vault/src/Vault/VaultSecretsResolver.php` — implements `SecretsResolverInterface`
- [ ] `framework/packages/integrations/secrets-vault/src/Vault/VaultClient.php` — PSR-18 wrapper (deterministic; redacted logging)
- [ ] `framework/packages/integrations/secrets-vault/src/Exception/VaultSecretsException.php` — deterministic codes
- [ ] `framework/packages/integrations/secrets-vault/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/integrations/secrets-vault/tests/Integration/ResolverDoesNotLeakSecretValueTest.php`

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/secrets-vault/composer.json`
- [ ] `framework/packages/integrations/secrets-vault/src/Module/SecretsVaultModule.php`
- [ ] `framework/packages/integrations/secrets-vault/src/Provider/SecretsVaultServiceProvider.php`
- [ ] `framework/packages/integrations/secrets-vault/config/secrets_vault.php`
- [ ] `framework/packages/integrations/secrets-vault/config/rules.php`
- [ ] `framework/packages/integrations/secrets-vault/README.md`
- [ ] `framework/packages/integrations/secrets-vault/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/secrets-vault/config/secrets_vault.php`
- [ ] Keys (dot):
  - [ ] `secrets_vault.enabled` = false              # integration-local toggle (implementation)
  - [ ] `secrets_vault.address` = ''
  - [ ] `secrets_vault.token_ref` = ''               # a secret_ref, never log raw
  - [ ] `secrets_vault.kv_prefix` = 'secret/'
- [ ] Rules:
  - [ ] `framework/packages/integrations/secrets-vault/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced:
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] binds `Coretsia\Contracts\Secrets\SecretsResolverInterface` → `VaultSecretsResolver` when module enabled
  - [ ] when disabled → delegates to `platform/secrets` default (noop-safe)

- registers backend service (implementation detail) and TAGS it:
  - tag: `secrets.backend` (owner: `platform/secrets`)
  - meta allowlist: `{ driver: 'vault' }`
- `platform/secrets` owns the global binding `SecretsResolverInterface` and selects backend by config.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (optional; safe logs)
- Context writes:
  - N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `secrets.resolve` (attrs: backend='vault', outcome; ref as hash only)
- [ ] Metrics:
  - [ ] `secrets.resolve_total` (labels: `driver|outcome|operation`)
  - [ ] `secrets.resolve_duration_ms` (labels: `driver|outcome|operation`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`, `op→operation`
- [ ] Logs:
  - [ ] failures log only backend + ref hash/len, never value/address/token

#### Errors

- [ ] Exceptions introduced:
  - [ ] `\Coretsia\Integrations\SecretsVault\Exception\VaultSecretsException` — errorCodes:
    - [ ] `CORETSIA_SECRETS_BACKEND_UNAVAILABLE`
    - [ ] `CORETSIA_SECRETS_REF_INVALID`
    - [ ] `CORETSIA_SECRETS_ACCESS_DENIED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] secret values, vault token, raw refs, raw URLs
- [ ] Allowed:
  - [ ] `hash(ref)` / `len(ref)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] Redaction does not leak:
  - [ ] `framework/packages/integrations/secrets-vault/tests/Integration/ResolverDoesNotLeakSecretValueTest.php`
- [ ] Noop-safe cross-cutting:
  - [ ] `framework/packages/integrations/secrets-vault/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/integrations/secrets-vault/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/secrets-vault/tests/Integration/ResolverDoesNotLeakSecretValueTest.php`

### DoD (MUST)

- [ ] Secret values never printed (only ref/hash/len)
- [ ] Determinism: request building stable; logs stable ordering; rerun-no-diff
- [ ] Docs updated:
  - [ ] `docs/ops/secrets.md`
  - [ ] `docs/architecture/secrets-boundary.md`
- [ ] Enabling module makes `secret_ref` resolvable at runtime without leakage (Vault backend).

---

### 6.371.0 integrations/secrets-aws (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.371.0"
owner_path: "framework/packages/integrations/secrets-aws/"

package_id: "integrations/secrets-aws"
composer: "coretsia/integrations-secrets-aws"
kind: runtime
module_id: "integrations.secrets-aws"

goal: "Увімкнення `integrations.secrets-aws` робить `secret_ref` resolvable через AWS Secrets Manager/Parameter Store at runtime без leakage значень і без vendor concretes в contracts."
provides:
- "`integrations/secrets-aws` модуль, що біндить `SecretsResolverInterface` на AWS backend."
- "Deterministic request semantics для AWS backend (через PSR-18; без плаваючих значень/рандому)."
- "Contract-like гарантії: secret values ніколи не друкуються (only refs/hashes/len)."

tags_introduced: []
config_roots_introduced: ["secrets_aws"]
artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
- docs/ssot/config-roots.md
- docs/ssot/tags.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required contracts / ports (runtime usage):
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` — binding target.
  - `Psr\Http\Client\ClientInterface` — outbound client.
  - `Psr\Log\LoggerInterface` — safe logging.
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — OPTIONAL (noop-safe).
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — OPTIONAL (noop-safe).

- Runtime expectation (policy, NOT deps):
  - `platform/secrets` provides default resolver; integrations override/bind by module enable.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http`
- `platform/cli`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Client\ClientInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` (optional)
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

Docs:
- [ ] `docs/ops/secrets.md` — add AWS section (refs, auth, troubleshooting; never print values)
  - `secret_ref` format
  - redaction rules (never print values)
  - troubleshooting (timeouts, auth)
  - examples for AWS

- [ ] `framework/packages/integrations/secrets-aws/composer.json`
- [ ] `framework/packages/integrations/secrets-aws/src/Module/SecretsAwsModule.php` — module (AWS)
- [ ] `framework/packages/integrations/secrets-aws/src/Provider/SecretsAwsServiceProvider.php` — wiring
- [ ] `framework/packages/integrations/secrets-aws/src/Provider/SecretsAwsServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/secrets-aws/config/secrets_aws.php` — config subtree (root: `secrets_aws`; returns subtree)
- [ ] `framework/packages/integrations/secrets-aws/config/rules.php` — rules
- [ ] `framework/packages/integrations/secrets-aws/README.md` — Observability / Errors / Security-Redaction
- [ ] `framework/packages/integrations/secrets-aws/src/Aws/AwsSecretsResolver.php` — implements `SecretsResolverInterface`
- [ ] `framework/packages/integrations/secrets-aws/src/Aws/AwsRequestFactory.php` — deterministic request building (redacted)
- [ ] `framework/packages/integrations/secrets-aws/src/Exception/AwsSecretsException.php` — deterministic codes
- [ ] `framework/packages/integrations/secrets-aws/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/integrations/secrets-aws/tests/Integration/ResolverDoesNotLeakSecretValueTest.php`

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/secrets-aws/composer.json`
- [ ] `framework/packages/integrations/secrets-aws/src/Module/SecretsAwsModule.php`
- [ ] `framework/packages/integrations/secrets-aws/src/Provider/SecretsAwsServiceProvider.php`
- [ ] `framework/packages/integrations/secrets-aws/config/secrets_aws.php`
- [ ] `framework/packages/integrations/secrets-aws/config/rules.php`
- [ ] `framework/packages/integrations/secrets-aws/README.md`
- [ ] `framework/packages/integrations/secrets-aws/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/secrets-aws/config/secrets_aws.php`
- [ ] Keys (dot):
  - [ ] `secrets_aws.enabled` = false                # integration-local toggle (implementation)
  - [ ] `secrets_aws.region` = ''
  - [ ] `secrets_aws.credentials_ref` = ''           # optional secret_ref; never log raw
  - [ ] `secrets_aws.endpoint` = ''                  # optional (localstack); never log raw
  - [ ] `secrets_aws.timeout_ms` = 2000              # deterministic int-only
- [ ] Rules:
  - [ ] `framework/packages/integrations/secrets-aws/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced:
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] binds `SecretsResolverInterface` → `AwsSecretsResolver` when module enabled
  - [ ] when disabled → delegates to `platform/secrets` default resolver

- TAG backend service with:
  - `secrets.backend` meta `{ driver: 'aws' }`
- `platform/secrets` owns `SecretsResolverInterface` selection/binding.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (optional; safe logs)
- Context writes:
  - N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `secrets.resolve` (attrs: backend='vault', outcome; ref as hash only)
- [ ] Metrics:
  - [ ] `secrets.resolve_total` (labels: `driver|outcome|operation`)
  - [ ] `secrets.resolve_duration_ms` (labels: `driver|outcome|operation`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`, `op→operation`
- [ ] Logs:
  - [ ] failures log only backend + ref hash/len, never value/address/token

#### Errors

- [ ] Exceptions introduced:
  - [ ] `\Coretsia\Integrations\SecretsAws\Exception\AwsSecretsException` — errorCodes:
    - [ ] `CORETSIA_SECRETS_BACKEND_UNAVAILABLE`
    - [ ] `CORETSIA_SECRETS_REF_INVALID`
    - [ ] `CORETSIA_SECRETS_ACCESS_DENIED`

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] secret values, raw refs, raw credentials, raw endpoints
- [ ] Allowed:
  - [ ] `hash(ref)` / `len(ref)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/packages/integrations/secrets-aws/tests/Integration/ResolverDoesNotLeakSecretValueTest.php`
- [ ] `framework/packages/integrations/secrets-aws/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/integrations/secrets-aws/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/secrets-aws/tests/Integration/ResolverDoesNotLeakSecretValueTest.php`

### DoD (MUST)

- [ ] Secret values never printed (only ref/hash/len)
- [ ] Determinism: stable request building + int-only timeouts; rerun-no-diff
- [ ] Docs updated:
  - [ ] `docs/ops/secrets.md` (AWS section)
- [ ] Enabling module makes `secret_ref` resolvable at runtime without leakage (AWS backend).

---

### 6.372.0 integrations/secrets-gcp (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.372.0"
owner_path: "framework/packages/integrations/secrets-gcp/"

package_id: "integrations/secrets-gcp"
composer: "coretsia/integrations-secrets-gcp"
kind: runtime
module_id: "integrations.secrets-gcp"

goal: "Увімкнення `integrations.secrets-gcp` робить `secret_ref` resolvable через GCP Secret Manager at runtime без leakage значень і без vendor concretes в contracts."
provides:
- "`integrations/secrets-gcp` модуль, що біндить `SecretsResolverInterface` на GCP backend."
- "Deterministic request semantics для GCP backend (через PSR-18; без плаваючих значень/рандому)."
- "Contract-like гарантії: secret values ніколи не друкуються (only refs/hashes/len)."

tags_introduced: []
config_roots_introduced: ["secrets_gcp"]
artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
- docs/ssot/config-roots.md
- docs/ssot/tags.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required contracts / ports (runtime usage):
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` — binding target.
  - `Psr\Http\Client\ClientInterface` — outbound client.
  - `Psr\Log\LoggerInterface` — safe logging.
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — OPTIONAL (noop-safe).
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — OPTIONAL (noop-safe).

- Runtime expectation (policy, NOT deps):
  - `platform/secrets` provides default resolver; integrations override/bind by module enable.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/http`
- `platform/cli`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Client\ClientInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` (optional)
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

Docs:
- [ ] `docs/ops/secrets.md` — add GCP section (refs, auth, troubleshooting; never print values)
  - `secret_ref` format
  - redaction rules (never print values)
  - troubleshooting (timeouts, auth)
  - examples for GCP

- [ ] `framework/packages/integrations/secrets-gcp/composer.json`
- [ ] `framework/packages/integrations/secrets-gcp/src/Module/SecretsGcpModule.php` — module (GCP)
- [ ] `framework/packages/integrations/secrets-gcp/src/Provider/SecretsGcpServiceProvider.php` — wiring
- [ ] `framework/packages/integrations/secrets-gcp/src/Provider/SecretsGcpServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/secrets-gcp/config/secrets_gcp.php` — config subtree (root: `secrets_gcp`; returns subtree)
- [ ] `framework/packages/integrations/secrets-gcp/config/rules.php` — rules
- [ ] `framework/packages/integrations/secrets-gcp/README.md` — Observability / Errors / Security-Redaction
- [ ] `framework/packages/integrations/secrets-gcp/src/Gcp/GcpSecretsResolver.php` — implements `SecretsResolverInterface`
- [ ] `framework/packages/integrations/secrets-gcp/src/Gcp/GcpRequestFactory.php` — deterministic request building (redacted)
- [ ] `framework/packages/integrations/secrets-gcp/src/Exception/GcpSecretsException.php` — deterministic codes
- [ ] `framework/packages/integrations/secrets-gcp/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/integrations/secrets-gcp/tests/Integration/ResolverDoesNotLeakSecretValueTest.php`

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/secrets-gcp/composer.json`
- [ ] `framework/packages/integrations/secrets-gcp/src/Module/SecretsGcpModule.php`
- [ ] `framework/packages/integrations/secrets-gcp/src/Provider/SecretsGcpServiceProvider.php`
- [ ] `framework/packages/integrations/secrets-gcp/config/secrets_gcp.php`
- [ ] `framework/packages/integrations/secrets-gcp/config/rules.php`
- [ ] `framework/packages/integrations/secrets-gcp/README.md`
- [ ] `framework/packages/integrations/secrets-gcp/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/secrets-gcp/config/secrets_gcp.php`
- [ ] Keys (dot):
  - [ ] `secrets_gcp.enabled` = false                # integration-local toggle (implementation)
  - [ ] `secrets_gcp.project_id` = ''
  - [ ] `secrets_gcp.credentials_ref` = ''           # optional secret_ref; never log raw
  - [ ] `secrets_gcp.endpoint` = ''                  # optional emulator; never log raw
  - [ ] `secrets_gcp.timeout_ms` = 2000              # deterministic int-only
- [ ] Rules:
  - [ ] `framework/packages/integrations/secrets-gcp/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced:
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] binds `SecretsResolverInterface` → `GcpSecretsResolver` when module enabled
  - [ ] when disabled → delegates to `platform/secrets` default resolver

- TAG backend service with:
  - `secrets.backend` meta `{ driver: 'gcp' }`
- `platform/secrets` owns `SecretsResolverInterface` selection/binding.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (optional; safe logs)
- Context writes:
  - N/A

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `secrets.resolve` (attrs: backend='vault', outcome; ref as hash only)
- [ ] Metrics:
  - [ ] `secrets.resolve_total` (labels: `driver|outcome|operation`)
  - [ ] `secrets.resolve_duration_ms` (labels: `driver|outcome|operation`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver`, `op→operation`
- [ ] Logs:
  - [ ] failures log only backend + ref hash/len, never value/address/token

#### Errors

- [ ] Exceptions introduced:
  - [ ] `\Coretsia\Integrations\SecretsGcp\Exception\GcpSecretsException` — errorCodes:
    - [ ] `CORETSIA_SECRETS_BACKEND_UNAVAILABLE`
    - [ ] `CORETSIA_SECRETS_REF_INVALID`
    - [ ] `CORETSIA_SECRETS_ACCESS_DENIED`

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] secret values, raw refs, raw credentials, raw endpoints
- [ ] Allowed:
  - [ ] `hash(ref)` / `len(ref)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/packages/integrations/secrets-gcp/tests/Integration/ResolverDoesNotLeakSecretValueTest.php`
- [ ] `framework/packages/integrations/secrets-gcp/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/integrations/secrets-gcp/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/secrets-gcp/tests/Integration/ResolverDoesNotLeakSecretValueTest.php`

### DoD (MUST)

- [ ] Secret values never printed (only ref/hash/len)
- [ ] Determinism: stable request building + int-only timeouts; rerun-no-diff
- [ ] Docs updated:
  - [ ] `docs/ops/secrets.md` (GCP section)
- [ ] Enabling module makes `secret_ref` resolvable at runtime without leakage (GCP backend).

---

### 6.380.0 Webhooks (outgoing dispatch + HMAC signing/verify + retry) (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.380.0"
owner_path: "framework/packages/platform/webhooks/"

package_id: "platform/webhooks"
composer: "coretsia/platform-webhooks"
kind: runtime
module_id: "platform.webhooks"

goal: "Webhook deliveries are signed and dispatched deterministically with retry/backoff, producing no secret leaks and stable observability signals."
provides:
- "Canonical outgoing webhooks dispatcher (sign + send + retry) via PSR-18, deterministic semantics"
- "HMAC signing/verify helpers with canonicalization and strict redaction policy"
- "Observability helpers (spans/metrics/logs) with allowlist labels and no payload/secret leakage"

tags_introduced: []
config_roots_introduced:
- "webhooks"

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - N/A (цей епік вводить root `webhooks`)

- Required tags:
  - `error.mapper` — існуючий tag (використовується опційно, якщо verify-exceptions мапляться у HTTP errors)
  - `cli.command` — існуючий tag (optional, якщо додавати CLI tools пізніше)

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface`
  - `Coretsia\Contracts\Queue\QueueInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface` (optional)
  - `Coretsia\Foundation\Context\ContextKeys`
  - `Coretsia\Foundation\Time\Stopwatch`
  - PSR-18/PSR-7 factories:
    - `Psr\Http\Client\ClientInterface`
    - `Psr\Http\Message\RequestFactoryInterface`
    - `Psr\Http\Message\StreamFactoryInterface`
  - `Psr\Log\LoggerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `platform/http-client`

Forbidden:

- `platform/http`
- `platform/http-app`
- `platform/routing`
- `platform/observability`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
  - `Psr\Http\Client\ClientInterface`
  - `Psr\Http\Message\RequestFactoryInterface`
  - `Psr\Http\Message\StreamFactoryInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface`
  - `Coretsia\Contracts\Queue\QueueInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface` (optional)
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextKeys`
  - `Coretsia\Foundation\Time\Stopwatch`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/webhooks/src/Module/WebhooksModule.php` — runtime module
- [ ] `framework/packages/platform/webhooks/src/Provider/WebhooksServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/webhooks/src/Provider/WebhooksServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/webhooks/config/webhooks.php` — config subtree (root `webhooks`)
- [ ] `framework/packages/platform/webhooks/config/rules.php` — config rules/shape
- [ ] `framework/packages/platform/webhooks/README.md` — README
- [ ] `framework/packages/platform/webhooks/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — runtime contract

- [ ] `framework/packages/platform/webhooks/src/Webhook/WebhookDispatcher.php` — orchestrates sign + send + retry decision
- [ ] `framework/packages/platform/webhooks/src/Webhook/WebhookRequestFactory.php` — PSR-7 request builder (deterministic)
- [ ] `framework/packages/platform/webhooks/src/Webhook/WebhookSignature.php` — HMAC sign/verify + canonicalization
  - MUST use `Coretsia\Foundation\Serialization\StableJsonEncoder` (map keys sorted recursively; lists preserved)
  - MUST NOT depend on `coretsia/internal-toolkit` (tooling-only)
- [ ] `framework/packages/platform/webhooks/src/Webhook/Retry/RetryPolicy.php` — deterministic retry/backoff (ints only)
- [ ] `framework/packages/platform/webhooks/src/Webhook/Retry/BackoffStrategy.php` — deterministic delays (no jitter/floats)
- [ ] `framework/packages/platform/webhooks/src/Observability/WebhookInstrumentation.php` — spans/metrics/log redaction helpers
- [ ] `framework/packages/platform/webhooks/src/Security/Redaction.php` — `hash/len` helpers for urls/headers/payload metadata
- [ ] `framework/packages/platform/webhooks/src/Exception/WebhookException.php` — base exception + deterministic code
- [ ] `framework/packages/platform/webhooks/src/Exception/WebhookSignatureInvalidException.php` — verify failure
- [ ] `framework/packages/platform/webhooks/src/Exception/WebhookDeliveryFailedException.php` — delivery failure (typed outcome)
- [ ] `framework/packages/platform/webhooks/src/Errors/WebhookProblemMapper.php` — maps verify exceptions to ErrorDescriptor (optional)

- [ ] `framework/packages/platform/webhooks/tests/Unit/SignatureCanonicalizationDeterministicTest.php` — unit proof
- [ ] `framework/packages/platform/webhooks/tests/Unit/HmacSignatureStableVectorsTest.php` — unit proof
- [ ] `framework/packages/platform/webhooks/tests/Unit/RetryBackoffDeterministicTest.php` — unit proof
- [ ] `framework/packages/platform/webhooks/tests/Contract/NoSecretLoggingContractTest.php` — contract redaction proof
- [ ] `framework/packages/platform/webhooks/tests/Integration/DispatcherBuildsPsr18RequestWithoutLeakingSecretsTest.php` — integration proof
- [ ] `framework/packages/platform/webhooks/tests/Integration/RetryScheduledOnTransientFailuresTest.php` — integration proof
- [ ] `framework/packages/platform/webhooks/tests/Integration/Webhooks/DispatcherSmokeTest.php` — optional harness (no network)

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/webhooks/composer.json`
- [ ] `framework/packages/platform/webhooks/src/Module/WebhooksModule.php`
- [ ] `framework/packages/platform/webhooks/src/Provider/WebhooksServiceProvider.php`
- [ ] `framework/packages/platform/webhooks/config/webhooks.php`
- [ ] `framework/packages/platform/webhooks/config/rules.php`
- [ ] `framework/packages/platform/webhooks/README.md`
- [ ] `framework/packages/platform/webhooks/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/webhooks/config/webhooks.php`
- [ ] Keys (dot):
  - [ ] `webhooks.enabled` = false
  - [ ] `webhooks.signing.enabled` = true
  - [ ] `webhooks.signing.header_name` = 'X-Signature'
  - [ ] `webhooks.signing.algo` = 'hmac_sha256'
  - [ ] `webhooks.signing.secret_ref` = null
  - [ ] `webhooks.http.timeout_ms` = 10000
  - [ ] `webhooks.http.connect_timeout_ms` = 3000
  - [ ] `webhooks.retry.enabled` = true
  - [ ] `webhooks.retry.max_attempts` = 5
  - [ ] `webhooks.retry.backoff.base_delay_ms` = 5000
  - [ ] `webhooks.retry.backoff.max_delay_ms` = 300000
  - [ ] `webhooks.redaction.headers` = ['Authorization','Cookie','Set-Cookie']
- [ ] Rules:
  - [ ] `framework/packages/platform/webhooks/config/rules.php` enforces shape
    - Rules MUST enforce:
      - int >= 0
      - no float/NaN/INF

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (no new tag names; provides constants only)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Webhooks\Webhook\WebhookDispatcher`
  - [ ] registers: `Coretsia\Webhooks\Webhook\WebhookSignature`
  - [ ] registers: `Coretsia\Webhooks\Errors\WebhookProblemMapper` (optional)
  - [ ] adds tag: `error.mapper` priority `0` meta `[]` (optional; only if verify exceptions exposed to HTTP error flow)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
  - [ ] `ContextKeys::ACTOR_ID` (if present; safe id only)
  - [ ] `ContextKeys::TENANT_ID` (if present; safe id only)
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] stateless by default; if a stateful retry buffer exists → implements `ResetInterface` + tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `webhooks.dispatch` (attrs: `outcome`, `attempt`, `endpoint_hash`)
  - [ ] `webhooks.sign` (attrs: `outcome`)
  - [ ] `webhooks.http_send` (attrs: `outcome`, `status` as span attr only)
- [ ] Metrics:
  - [ ] `webhooks.dispatch_total` (labels: `driver|operation|outcome`)
  - [ ] `webhooks.dispatch_duration_ms` (labels: `driver|operation|outcome`)
  - [ ] `webhooks.retry_scheduled_total` (labels: `operation|outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `kind/op/uow_type→operation` (use `operation=dispatch|sign|send|retry_schedule`)
- [ ] Logs:
  - [ ] dispatch summary with redaction; no payload/signature secret

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Webhooks\Exception\WebhookSignatureInvalidException` — errorCode `CORETSIA_WEBHOOK_SIGNATURE_INVALID`
  - [ ] `Coretsia\Webhooks\Exception\WebhookDeliveryFailedException` — errorCode `CORETSIA_WEBHOOK_DELIVERY_FAILED`
  - [ ] `Coretsia\Webhooks\Exception\WebhookException` — errorCode `CORETSIA_WEBHOOK_ERROR`
- [ ] Mapping:
  - [ ] `ExceptionMapperInterface` via tag `error.mapper` for verify exceptions **OR** reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] Authorization/Cookie/session id/tokens/raw payload/body
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids (`actor_id`, `tenant_id`)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php`  
  (N/A: no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php`  
  (optional, only if stateful retry buffer exists)
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`  
  (asserts: allowlist labels; no secrets/PII)
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`  
  (asserts: tokens/cookies/auth never appear raw)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeLogger/FakeTracer/FakeMeter capture events for assertions
  - [ ] Fake PSR-18 client to avoid real network

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/webhooks/tests/Unit/SignatureCanonicalizationDeterministicTest.php`
  - [ ] `framework/packages/platform/webhooks/tests/Unit/HmacSignatureStableVectorsTest.php`
  - [ ] `framework/packages/platform/webhooks/tests/Unit/RetryBackoffDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/webhooks/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/webhooks/tests/Contract/NoSecretLoggingContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/webhooks/tests/Integration/DispatcherBuildsPsr18RequestWithoutLeakingSecretsTest.php`
  - [ ] `framework/packages/platform/webhooks/tests/Integration/RetryScheduledOnTransientFailuresTest.php`
  - [ ] `framework/packages/platform/webhooks/tests/Integration/Webhooks/DispatcherSmokeTest.php` (optional; no network)
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: retry/backoff has no jitter; rerun same inputs → same decisions
- [ ] Docs updated:
  - [ ] `framework/packages/platform/webhooks/README.md`
- [ ] What problem this epic solves
  - [ ] Provide a canonical outgoing webhooks dispatcher with deterministic signing + retries, without leaking secrets/PII.
  - [ ] Enable safe delivery via `platform/http-client` (PSR-18) with consistent observability and redaction.
  - [ ] Offer optional verification helpers for inbound webhooks without coupling contracts/kernel.
- [ ] Non-goals / out of scope
  - [ ] Not a generic event bus (outbox/events remain owners for event production).
  - [ ] No requirement on storage backend (DB tables optional; no hard dependency on `platform/database`).
  - [ ] No inbound HTTP endpoint ownership in this epic (controllers live in apps/devtools/enterprise as needed).
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
  - [ ] `platform/secrets` provides `SecretsResolverInterface` binding (fallback must exist)
- [ ] Discovery / wiring is via tags:
  - [ ] `cli.command` (optional; only if you add webhooks CLI tools later)
- [ ] Webhook deliveries are signed and dispatched deterministically with retry/backoff, producing no secret leaks and stable observability signals.
- [ ] When an app enqueues a webhook delivery with a `secret_ref`, then the dispatcher signs the payload, performs a PSR-18 request, and on transient failure schedules a deterministic retry without logging payload or secrets.

---

### 6.390.0 Search (ports + platform facade + ADR + fake adapter) (SHOULD) [CONTRACTS]

---
type: package
phase: 6+
epic_id: "6.390.0"
owner_path: "framework/packages/platform/search/"

package_id: "platform/search"
composer: "coretsia/platform-search"
kind: runtime
module_id: "platform.search"

goal: "Search is accessible via stable contracts, with deterministic indexing plan and CI-safe fake backend, without leaking data in logs/metrics."
provides:
- "Vendor-agnostic Search ports у `core/contracts` (apps + integrations)."
- "`platform/search` facade: deterministic indexing plan + query execution via injected client(s)."
- "CI-safe fake adapter + optional `search:reindex` command (no payload print)."
- "ADR-0076 locking the boundary and invariants."

tags_introduced:
- "search.client"   # discovery of SearchClientInterface implementations

config_roots_introduced:
- "search"

artifacts_introduced: []
adr: "docs/adr/ADR-0076-search-ports-and-platform-search.md"
ssot_refs:
- "docs/ssot/config-and-env.md"
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/metrics-policy.md"
- "docs/ssot/tags.md"
- "docs/ssot/config-roots.md"
- "docs/ssot/observability.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/` — contracts package exists (цей епік додає Search ports у нього).
  - `framework/packages/platform/search/src/Provider/Tags.php` with constant: public const `SEARCH_CLIENT = 'search.client'`;

- Required tags:
  - `cli.command` — існуючий tag (епік додає command за потреби).
  - `kernel.reset` — існуючий tag (optional; only if fake client keeps state).

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - PSR:
    - `Psr\Log\LoggerInterface`
    - `Psr\SimpleCache\CacheInterface` (optional)
  - Foundation:
    - `Coretsia\Foundation\Time\Stopwatch`
    - `Coretsia\Foundation\Discovery\DeterministicOrder`

- Constraints / out of scope:
  - No `integrations/*` compile-time deps in this epic.
  - Never log query text, document payloads, backend credentials; only hash/len + safe ids.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http`
- `platform/http-app`
- `platform/routing`
- `platform/observability`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
  - `Psr\SimpleCache\CacheInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Search\SearchClientInterface`
  - `Coretsia\Contracts\Search\IndexerInterface`
  - `Coretsia\Contracts\Search\SearchQuery`
  - `Coretsia\Contracts\Search\SearchResult`
  - `Coretsia\Contracts\Search\SearchException`
  - `Coretsia\Contracts\Search\SearchBackendUnavailableException`
  - `Coretsia\Contracts\Search\SearchQueryInvalidException`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Time\Stopwatch`
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

- Runtime expectation (policy, NOT deps):
  - `platform/logging|tracing|metrics` provide implementations/noop-safe.
  - integrations may provide `SearchClientInterface` implementations, but this epic ships a CI-safe fake.

### Entry points / integration points (MUST)

- CLI:
  - `search:reindex` → `framework/packages/platform/search/src/Console/SearchReindexCommand.php`
- HTTP:
  - N/A
- Kernel hooks/tags:
  - `kernel.reset` (optional; only if fake keeps state)
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

Platform (`platform/search`) skeleton:
- [ ] `framework/packages/platform/search/composer.json`
- [ ] `framework/packages/platform/search/src/Module/SearchModule.php` — runtime module
- [ ] `framework/packages/platform/search/src/Provider/SearchServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/search/src/Provider/SearchServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/search/config/search.php` — config subtree (root `search`)
- [ ] `framework/packages/platform/search/config/rules.php` — config rules/shape
- [ ] `framework/packages/platform/search/README.md` — README
- [ ] `framework/packages/platform/search/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — runtime contract

Contracts (`core/contracts`) ports + shapes:
- [ ] `framework/packages/core/contracts/src/Search/SearchClientInterface.php` — port for query + indexing ops
- [ ] `framework/packages/core/contracts/src/Search/IndexerInterface.php` — port for deterministic index plan exec
- [ ] `framework/packages/core/contracts/src/Search/SearchQuery.php` — VO (json-like), deterministic fields
- [ ] `framework/packages/core/contracts/src/Search/SearchResult.php` — VO (json-like), deterministic ordering
- [ ] `framework/packages/core/contracts/src/Search/SearchException.php` — base exception + deterministic codes
- [ ] `framework/packages/core/contracts/src/Search/SearchBackendUnavailableException.php` — code `CORETSIA_SEARCH_BACKEND_UNAVAILABLE`
- [ ] `framework/packages/core/contracts/src/Search/SearchQueryInvalidException.php` — code `CORETSIA_SEARCH_QUERY_INVALID`

Platform facade + fake:
- [ ] `framework/packages/platform/search/src/Search/SearchManager.php` — selects client by config, executes ops
- [ ] `framework/packages/platform/search/src/Search/IndexPlan/IndexPlan.php` — deterministic plan VO (no payload)
- [ ] `framework/packages/platform/search/src/Search/IndexPlan/IndexPlanner.php` — builds deterministic plan from config
- [ ] `framework/packages/platform/search/src/Client/FakeSearchClient.php` — CI fake implementing SearchClientInterface
- [ ] `framework/packages/platform/search/src/Observability/SearchInstrumentation.php` — spans/metrics/log redaction helpers
- [ ] `framework/packages/platform/search/src/Console/SearchReindexCommand.php` — optional CLI entrypoint (no payload print)

ADR:
- [ ] `docs/adr/ADR-0076-search-ports-and-platform-search.md` — ADR (contracts ports + platform facade + fake)

Tests (proof):
- [ ] `framework/packages/core/contracts/tests/Contract/SearchContractsShapeContractTest.php` — contracts shape contract
- [ ] `framework/packages/platform/search/tests/Contract/NoSecretLoggingContractTest.php` — redaction contract
- [ ] `framework/packages/platform/search/tests/Unit/IndexPlanDeterministicTest.php` — unit determinism proof
- [ ] `framework/packages/platform/search/tests/Unit/FakeClientQueryDeterministicResultOrderTest.php` — unit determinism proof
- [ ] `framework/packages/platform/search/tests/Integration/ReindexCommandDoesNotLeakPayloadTest.php` — integration proof
- [ ] `framework/packages/platform/search/tests/Integration/FakeClientSupportsQueryAndIndexWithoutExternalServicesTest.php` — integration proof
- [ ] `framework/packages/platform/search/tests/Integration/Search/FakeSearchWorksInHybridPresetTest.php` — optional integration
- [ ] `framework/packages/core/kernel/tests/Fixtures/HybridApp/config/modules.php` — fixture wiring (if needed)

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0076-search-ports-and-platform-search.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/search/composer.json`
- [ ] `framework/packages/platform/search/src/Module/SearchModule.php`
- [ ] `framework/packages/platform/search/src/Provider/SearchServiceProvider.php`
- [ ] `framework/packages/platform/search/config/search.php`
- [ ] `framework/packages/platform/search/config/rules.php`
- [ ] `framework/packages/platform/search/README.md`
- [ ] `framework/packages/platform/search/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/search/config/search.php`
- [ ] Keys (dot):
  - [ ] `search.enabled` = false
  - [ ] `search.default_client` = 'fake'
  - [ ] `search.clients` = []
  - [ ] `search.indexing.enabled` = true
  - [ ] `search.indexing.batch_size` = 500
  - [ ] `search.cache.enabled` = false
  - [ ] `search.cache.ttl_seconds` = 60
- [ ] Rules:
  - [ ] `framework/packages/platform/search/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (no new tag names; provides constants only)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Search\Search\SearchManager`
  - [ ] registers: `Coretsia\Search\Client\FakeSearchClient`
  - [ ] adds tag: `cli.command` priority `0` meta `[]` (for `SearchReindexCommand`, if enabled)
  - [ ] if fake is stateful → implements `ResetInterface` + tagged `kernel.reset`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
  - [ ] `ContextKeys::TENANT_ID` (if present; safe id only)
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] any in-memory fake client state implements `ResetInterface` and is tagged `kernel.reset` (if state kept across calls)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `search.query` (attrs: `outcome`, `driver`)
  - [ ] `search.index` (attrs: `outcome`, `driver`)
- [ ] Metrics:
  - [ ] `search.query_total` (labels: `driver|operation|outcome`)
  - [ ] `search.query_duration_ms` (labels: `driver|operation|outcome`)
  - [ ] `search.index_total` (labels: `driver|operation|outcome`)
  - [ ] `search.index_duration_ms` (labels: `driver|operation|outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver` (backend name is `driver`)
  - [ ] `kind/op→operation` (use `operation=query|index|reindex_plan`)
- [ ] Logs:
  - [ ] summaries only; never log search query text or document payload

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Contracts\Search\SearchException` = `CORETSIA_SEARCH_ERROR`
  - [ ] `Coretsia\Contracts\Search\SearchBackendUnavailableException` = `CORETSIA_SEARCH_BACKEND_UNAVAILABLE`
  - [ ] `Coretsia\Contracts\Search\SearchQueryInvalidException` = `CORETSIA_SEARCH_QUERY_INVALID`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) OR introduce a mapper in platform/search if you expose these via HTTP/CLI normalized errors
- [ ] HTTP status hint policy documented (optional in ErrorDescriptor)
  - [ ] invalid query → `httpStatus=400` (only if you map for HTTP)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] query text, document payloads, backend credentials (api keys), raw response bodies
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/packages/core/contracts/tests/Contract/SearchContractsShapeContractTest.php`
- [ ] `framework/packages/platform/search/tests/Contract/NoSecretLoggingContractTest.php`
- [ ] `framework/packages/platform/search/tests/Unit/IndexPlanDeterministicTest.php`
- [ ] `framework/packages/platform/search/tests/Unit/FakeClientQueryDeterministicResultOrderTest.php`
- [ ] `framework/packages/platform/search/tests/Integration/ReindexCommandDoesNotLeakPayloadTest.php`
- [ ] `framework/packages/platform/search/tests/Integration/FakeClientSupportsQueryAndIndexWithoutExternalServicesTest.php`

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/search/tests/Unit/IndexPlanDeterministicTest.php`
  - [ ] `framework/packages/platform/search/tests/Unit/FakeClientQueryDeterministicResultOrderTest.php`
- Contract:
  - [ ] `framework/packages/core/contracts/tests/Contract/SearchContractsShapeContractTest.php`
  - [ ] `framework/packages/platform/search/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/search/tests/Contract/NoSecretLoggingContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/search/tests/Integration/ReindexCommandDoesNotLeakPayloadTest.php`
  - [ ] `framework/packages/platform/search/tests/Integration/FakeClientSupportsQueryAndIndexWithoutExternalServicesTest.php`
  - [ ] `framework/packages/platform/search/tests/Integration/Search/FakeSearchWorksInHybridPresetTest.php` (optional)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] ADR committed and indexed
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: fake adapter results order stable; reindex plan stable
- [ ] No payload/query/creds leakage (tests enforce)

---

### 6.391.0 integrations/search-elastic (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.391.0"
owner_path: "framework/packages/integrations/search-elastic/"

package_id: "integrations/search-elastic"
composer: "coretsia/integrations-search-elastic"
kind: runtime
module_id: "integrations.search-elastic"

goal: "Provide `integrations/search-elastic` package skeleton that can bind `SearchClientInterface` for Elastic via contracts only, with secret_ref-friendly config and strict redaction."
provides:
- "Module + ServiceProvider + config + rules + README for Elastic integration."
- "Config root `search_elastic` with `secret_ref` auth shape (never log raw)."
- "Noop-safe contract test for cross-cutting."

tags_introduced: []
config_roots_introduced:
- "search_elastic"

artifacts_introduced: []
adr: "docs/adr/ADR-0076-search-ports-and-platform-search.md"
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
- docs/ssot/tags.md
- docs/ssot/config-roots.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `6.390.0` — Search ports exist; platform facade + fake exists.

- Required config roots/keys:
  - N/A (this epic introduces `search_elastic` root)

- Required contracts / ports:
  - `Coretsia\Contracts\Search\SearchClientInterface`
  - PSR:
    - `Psr\Log\LoggerInterface`

- Constraints / out of scope:
  - Skeleton only (no full backend implementation required unless you choose to implement minimal client).
  - MUST NOT depend on `platform/search` compile-time (contracts-only).
  - MUST NOT leak base_url/auth ref/headers; only hash/len.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/search`
- `platform/http`
- `platform/http-app`
- `platform/routing`
- `platform/observability`
- `platform/cli`
- `integrations/*` (other than itself)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
  - `Psr\Http\Client\ClientInterface` (optional if you implement minimal client)
- Contracts:
  - `Coretsia\Contracts\Search\SearchClientInterface`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/search-elastic/composer.json`
- [ ] `framework/packages/integrations/search-elastic/src/Module/SearchElasticModule.php`
- [ ] `framework/packages/integrations/search-elastic/src/Provider/SearchElasticServiceProvider.php`
- [ ] `framework/packages/integrations/search-elastic/src/Provider/SearchElasticServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/search-elastic/config/search_elastic.php` — config subtree (root `search_elastic`)
- [ ] `framework/packages/integrations/search-elastic/config/rules.php` — config rules
- [ ] `framework/packages/integrations/search-elastic/README.md` — Observability / Errors / Security-Redaction
- [ ] `framework/packages/integrations/search-elastic/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/search-elastic/composer.json`
- [ ] `framework/packages/integrations/search-elastic/src/Module/SearchElasticModule.php`
- [ ] `framework/packages/integrations/search-elastic/src/Provider/SearchElasticServiceProvider.php`
- [ ] `framework/packages/integrations/search-elastic/config/search_elastic.php`
- [ ] `framework/packages/integrations/search-elastic/config/rules.php`
- [ ] `framework/packages/integrations/search-elastic/README.md`
- [ ] `framework/packages/integrations/search-elastic/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/search-elastic/config/search_elastic.php`
- [ ] Keys (dot):
  - [ ] `search_elastic.enabled` = false
  - [ ] `search_elastic.base_url` = 'http://127.0.0.1:9200'
  - [ ] `search_elastic.auth.secret_ref` = null
- [ ] Rules:
  - [ ] `framework/packages/integrations/search-elastic/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced:
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] when enabled, binds `Coretsia\Contracts\Search\SearchClientInterface` (or registers in selection mechanism) for `driver=elastic`
  - [ ] when disabled, MUST NOT override active bindings

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
  - [ ] `ContextKeys::TENANT_ID` (if present; safe id only)
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] any in-memory fake client state implements `ResetInterface` and is tagged `kernel.reset` (if state kept across calls)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `search.query` (attrs: `outcome`, `driver`)
  - [ ] `search.index` (attrs: `outcome`, `driver`)
- [ ] Metrics:
  - [ ] `search.query_total` (labels: `driver|operation|outcome`)
  - [ ] `search.query_duration_ms` (labels: `driver|operation|outcome`)
  - [ ] `search.index_total` (labels: `driver|operation|outcome`)
  - [ ] `search.index_duration_ms` (labels: `driver|operation|outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver` (backend name is `driver`)
  - [ ] `kind/op→operation` (use `operation=query|index|reindex_plan`)
- [ ] Logs:
  - [ ] summaries only; never log search query text or document payload

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Contracts\Search\SearchException` = `CORETSIA_SEARCH_ERROR`
  - [ ] `Coretsia\Contracts\Search\SearchBackendUnavailableException` = `CORETSIA_SEARCH_BACKEND_UNAVAILABLE`
  - [ ] `Coretsia\Contracts\Search\SearchQueryInvalidException` = `CORETSIA_SEARCH_QUERY_INVALID`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) OR introduce a mapper in platform/search if you expose these via HTTP/CLI normalized errors
- [ ] HTTP status hint policy documented (optional in ErrorDescriptor)
  - [ ] invalid query → `httpStatus=400` (only if you map for HTTP)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] auth secrets, raw headers, query text, payloads
- [ ] Allowed:
  - [ ] `hash(ref)` / `len(ref)` only; safe ids

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/packages/integrations/search-elastic/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/integrations/search-elastic/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### DoD (MUST)

- [ ] Package skeleton complete (module/provider/config/rules/README/test)
- [ ] No forbidden deps (deptrac green)
- [ ] Config supports `secret_ref` and never leaks secrets

---

### 6.392.0 integrations/search-opensearch (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.392.0"
owner_path: "framework/packages/integrations/search-opensearch/"

package_id: "integrations/search-opensearch"
composer: "coretsia/integrations-search-opensearch"
kind: runtime
module_id: "integrations.search-opensearch"

goal: "Provide `integrations/search-opensearch` package skeleton that can bind `SearchClientInterface` for OpenSearch via contracts only, with secret_ref-friendly config and strict redaction."
provides:
- "Module + ServiceProvider + config + rules + README for OpenSearch integration."
- "Config root `search_opensearch` with `secret_ref` auth shape (never log raw)."
- "Noop-safe contract test for cross-cutting."

tags_introduced: []
config_roots_introduced:
- "search_opensearch"

artifacts_introduced: []
adr: "docs/adr/ADR-0076-search-ports-and-platform-search.md"
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
- docs/ssot/tags.md
- docs/ssot/config-roots.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `6.390.0` — Search ports exist; platform facade + fake exists.

- Required contracts / ports:
  - `Coretsia\Contracts\Search\SearchClientInterface`
  - PSR:
    - `Psr\Log\LoggerInterface`

- Constraints / out of scope:
  - Skeleton only.
  - MUST NOT depend on `platform/search` compile-time (contracts-only).
  - MUST NOT leak base_url/auth ref/headers; only hash/len.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/search`
- `platform/http`
- `platform/http-app`
- `platform/routing`
- `platform/observability`
- `platform/cli`
- `integrations/*` (other than itself)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
  - `Psr\Http\Client\ClientInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Search\SearchClientInterface`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/search-opensearch/composer.json`
- [ ] `framework/packages/integrations/search-opensearch/src/Module/SearchOpensearchModule.php`
- [ ] `framework/packages/integrations/search-opensearch/src/Provider/SearchOpensearchServiceProvider.php`
- [ ] `framework/packages/integrations/search-opensearch/src/Provider/SearchOpensearchServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/search-opensearch/config/search_opensearch.php` — config subtree (root `search_opensearch`)
- [ ] `framework/packages/integrations/search-opensearch/config/rules.php` — config rules
- [ ] `framework/packages/integrations/search-opensearch/README.md` — Observability / Errors / Security-Redaction
- [ ] `framework/packages/integrations/search-opensearch/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/search-opensearch/composer.json`
- [ ] `framework/packages/integrations/search-opensearch/src/Module/SearchOpensearchModule.php`
- [ ] `framework/packages/integrations/search-opensearch/src/Provider/SearchOpensearchServiceProvider.php`
- [ ] `framework/packages/integrations/search-opensearch/config/search_opensearch.php`
- [ ] `framework/packages/integrations/search-opensearch/config/rules.php`
- [ ] `framework/packages/integrations/search-opensearch/README.md`
- [ ] `framework/packages/integrations/search-opensearch/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/search-opensearch/config/search_opensearch.php`
- [ ] Keys (dot):
  - [ ] `search_opensearch.enabled` = false
  - [ ] `search_opensearch.base_url` = 'http://127.0.0.1:9200'
  - [ ] `search_opensearch.auth.secret_ref` = null
- [ ] Rules:
  - [ ] `framework/packages/integrations/search-opensearch/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced:
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] when enabled, binds `Coretsia\Contracts\Search\SearchClientInterface` (or registers in selection mechanism) for `driver=opensearch`
  - [ ] when disabled, MUST NOT override active bindings

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
  - [ ] `ContextKeys::TENANT_ID` (if present; safe id only)
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] any in-memory fake client state implements `ResetInterface` and is tagged `kernel.reset` (if state kept across calls)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `search.query` (attrs: `outcome`, `driver`)
  - [ ] `search.index` (attrs: `outcome`, `driver`)
- [ ] Metrics:
  - [ ] `search.query_total` (labels: `driver|operation|outcome`)
  - [ ] `search.query_duration_ms` (labels: `driver|operation|outcome`)
  - [ ] `search.index_total` (labels: `driver|operation|outcome`)
  - [ ] `search.index_duration_ms` (labels: `driver|operation|outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver` (backend name is `driver`)
  - [ ] `kind/op→operation` (use `operation=query|index|reindex_plan`)
- [ ] Logs:
  - [ ] summaries only; never log search query text or document payload

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Contracts\Search\SearchException` = `CORETSIA_SEARCH_ERROR`
  - [ ] `Coretsia\Contracts\Search\SearchBackendUnavailableException` = `CORETSIA_SEARCH_BACKEND_UNAVAILABLE`
  - [ ] `Coretsia\Contracts\Search\SearchQueryInvalidException` = `CORETSIA_SEARCH_QUERY_INVALID`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) OR introduce a mapper in platform/search if you expose these via HTTP/CLI normalized errors
- [ ] HTTP status hint policy documented (optional in ErrorDescriptor)
  - [ ] invalid query → `httpStatus=400` (only if you map for HTTP)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] auth secrets, raw headers, query text, payloads
- [ ] Allowed:
  - [ ] `hash(ref)` / `len(ref)` only; safe ids

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/packages/integrations/search-opensearch/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/integrations/search-opensearch/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### DoD (MUST)

- [ ] Package skeleton complete (module/provider/config/rules/README/test)
- [ ] No forbidden deps (deptrac green)
- [ ] Config supports `secret_ref` and never leaks secrets

---

### 6.393.0 integrations/search-meilisearch (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.393.0"
owner_path: "framework/packages/integrations/search-meilisearch/"

package_id: "integrations/search-meilisearch"
composer: "coretsia/integrations-search-meilisearch"
kind: runtime
module_id: "integrations.search-meilisearch"

goal: "Provide `integrations/search-meilisearch` package skeleton that can bind `SearchClientInterface` for Meilisearch via contracts only, with secret_ref-friendly config and strict redaction."
provides:
- "Module + ServiceProvider + config + rules + README for Meilisearch integration."
- "Config root `search_meilisearch` with `secret_ref` API key shape (never log raw)."
- "Noop-safe contract test for cross-cutting."

tags_introduced: []
config_roots_introduced:
- "search_meilisearch"

artifacts_introduced: []
adr: "docs/adr/ADR-0076-search-ports-and-platform-search.md"
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
- docs/ssot/tags.md
- docs/ssot/config-roots.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - `6.390.0` — Search ports exist; platform facade + fake exists.

- Required contracts / ports:
  - `Coretsia\Contracts\Search\SearchClientInterface`
  - PSR:
    - `Psr\Log\LoggerInterface`

- Constraints / out of scope:
  - Skeleton only.
  - MUST NOT depend on `platform/search` compile-time (contracts-only).
  - MUST NOT leak base_url/api key ref/headers; only hash/len.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/search`
- `platform/http`
- `platform/http-app`
- `platform/routing`
- `platform/observability`
- `platform/cli`
- `integrations/*` (other than itself)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
  - `Psr\Http\Client\ClientInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Search\SearchClientInterface`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/search-meilisearch/composer.json`
- [ ] `framework/packages/integrations/search-meilisearch/src/Module/SearchMeilisearchModule.php`
- [ ] `framework/packages/integrations/search-meilisearch/src/Provider/SearchMeilisearchServiceProvider.php`
- [ ] `framework/packages/integrations/search-meilisearch/src/Provider/SearchMeilisearchServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/search-meilisearch/config/search_meilisearch.php` — config subtree (root `search_meilisearch`)
- [ ] `framework/packages/integrations/search-meilisearch/config/rules.php` — config rules
- [ ] `framework/packages/integrations/search-meilisearch/README.md` — Observability / Errors / Security-Redaction
- [ ] `framework/packages/integrations/search-meilisearch/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/search-meilisearch/composer.json`
- [ ] `framework/packages/integrations/search-meilisearch/src/Module/SearchMeilisearchModule.php`
- [ ] `framework/packages/integrations/search-meilisearch/src/Provider/SearchMeilisearchServiceProvider.php`
- [ ] `framework/packages/integrations/search-meilisearch/config/search_meilisearch.php`
- [ ] `framework/packages/integrations/search-meilisearch/config/rules.php`
- [ ] `framework/packages/integrations/search-meilisearch/README.md`
- [ ] `framework/packages/integrations/search-meilisearch/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/search-meilisearch/config/search_meilisearch.php`
- [ ] Keys (dot):
  - [ ] `search_meilisearch.enabled` = false
  - [ ] `search_meilisearch.base_url` = 'http://127.0.0.1:7700'
  - [ ] `search_meilisearch.api_key.secret_ref` = null
- [ ] Rules:
  - [ ] `framework/packages/integrations/search-meilisearch/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced:
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] when enabled, binds `Coretsia\Contracts\Search\SearchClientInterface` (or registers in selection mechanism) for `driver=meilisearch`
  - [ ] when disabled, MUST NOT override active bindings

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
  - [ ] `ContextKeys::TENANT_ID` (if present; safe id only)
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] any in-memory fake client state implements `ResetInterface` and is tagged `kernel.reset` (if state kept across calls)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `search.query` (attrs: `outcome`, `driver`)
  - [ ] `search.index` (attrs: `outcome`, `driver`)
- [ ] Metrics:
  - [ ] `search.query_total` (labels: `driver|operation|outcome`)
  - [ ] `search.query_duration_ms` (labels: `driver|operation|outcome`)
  - [ ] `search.index_total` (labels: `driver|operation|outcome`)
  - [ ] `search.index_duration_ms` (labels: `driver|operation|outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver` (backend name is `driver`)
  - [ ] `kind/op→operation` (use `operation=query|index|reindex_plan`)
- [ ] Logs:
  - [ ] summaries only; never log search query text or document payload

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Contracts\Search\SearchException` = `CORETSIA_SEARCH_ERROR`
  - [ ] `Coretsia\Contracts\Search\SearchBackendUnavailableException` = `CORETSIA_SEARCH_BACKEND_UNAVAILABLE`
  - [ ] `Coretsia\Contracts\Search\SearchQueryInvalidException` = `CORETSIA_SEARCH_QUERY_INVALID`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) OR introduce a mapper in platform/search if you expose these via HTTP/CLI normalized errors
- [ ] HTTP status hint policy documented (optional in ErrorDescriptor)
  - [ ] invalid query → `httpStatus=400` (only if you map for HTTP)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] api keys, raw headers, query text, payloads
- [ ] Allowed:
  - [ ] `hash(ref)` / `len(ref)` only; safe ids

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/packages/integrations/search-meilisearch/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/integrations/search-meilisearch/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### DoD (MUST)

- [ ] Package skeleton complete (module/provider/config/rules/README/test)
- [ ] No forbidden deps (deptrac green)
- [ ] Config supports `secret_ref` and never leaks secrets

---

### 6.400.0 API versioning (header-based middleware + optional route-provider helper) (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.400.0"
owner_path: "framework/packages/platform/api-versioning/"

package_id: "platform/api-versioning"
composer: "coretsia/platform-api-versioning"
kind: runtime
module_id: "platform.api-versioning"

goal: "When enabled, API versioning deterministically maps requests to the correct versioned path before routing without leaking headers or creating high-cardinality signals."
provides:
- "Header-based version selection (rewrite path before routing), deterministic rules"
- "Optional helper: path-prefix versioning wrapper for `RouteProviderInterface`"
- "Policy-compliant observability (no version metric label; version only as span attr)"

tags_introduced: []
config_roots_introduced:
- "api_versioning"

artifacts_introduced: []
adr: "docs/adr/ADR-0077-api-versioning-header-middleware.md"
ssot_refs:
- docs/ssot/config-and-env.md
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - N/A (цей епік вводить root `api_versioning`)

- Required tags:
  - `http.middleware.system_pre` — існуючий middleware slot/tag (platform/http виконує)
  - `error.mapper` — існуючий tag (для мапера помилок)

- Required contracts / ports:
  - PSR:
    - `Psr\Http\Server\MiddlewareInterface`
    - `Psr\Http\Message\ServerRequestInterface`
    - `Psr\Http\Message\ResponseInterface`
    - `Psr\Log\LoggerInterface`
  - Contracts:
    - `Coretsia\Contracts\Context\ContextAccessorInterface`
    - `Coretsia\Contracts\Routing\RouteProviderInterface` (optional helper)
    - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
    - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
    - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
  - Foundation:
    - `Coretsia\Foundation\Time\Stopwatch`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http` (integration only via PSR-15 + DI tags)
- `platform/routing`
- `platform/http-app`
- `platform/observability`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Routing\RouteProviderInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Time\Stopwatch`

- Runtime expectation (policy, NOT deps):
  - `platform/http` executes middleware via `http.middleware.system_pre` slot
  - `platform/problem-details` renders exceptions as RFC7807 (optional integration)

### Entry points / integration points (MUST)

- HTTP:
  - middleware: `\Coretsia\ApiVersioning\Http\Middleware\ApiVersioningMiddleware::class`
  - middleware slots/tags: `http.middleware.system_pre` priority `835` meta `[]`
- CLI:
  - N/A
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/api-versioning/src/Module/ApiVersioningModule.php` — runtime module
- [ ] `framework/packages/platform/api-versioning/src/Provider/ApiVersioningServiceProvider.php` — DI wiring
- [ ] `framework/packages/platform/api-versioning/src/Provider/ApiVersioningServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/api-versioning/config/api_versioning.php` — config subtree (root `api_versioning`)
- [ ] `framework/packages/platform/api-versioning/config/rules.php` — config rules/shape
- [ ] `framework/packages/platform/api-versioning/README.md` — README
- [ ] `framework/packages/platform/api-versioning/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — runtime contract

- [ ] `framework/packages/platform/api-versioning/src/Http/Middleware/ApiVersioningMiddleware.php` — header-based mapping `/x` → `/vN/x`
- [ ] `framework/packages/platform/api-versioning/src/Http/ApiVersioningPolicy.php` — VO deterministic rules
- [ ] `framework/packages/platform/api-versioning/src/Exception/ApiVersioningException.php` — base deterministic codes
- [ ] `framework/packages/platform/api-versioning/src/Exception/ApiVersionNotSupportedException.php` — unsupported version
- [ ] `framework/packages/platform/api-versioning/src/Errors/ApiVersioningProblemMapper.php` — maps to ErrorDescriptor (400)
- [ ] `framework/packages/platform/api-versioning/src/Routing/PrefixedRouteProvider.php` — optional wrapper for `RouteProviderInterface`
- [ ] `framework/packages/platform/api-versioning/src/Observability/ApiVersioningInstrumentation.php` — spans/metrics/log redaction helpers

- [ ] `framework/packages/platform/api-versioning/tests/Unit/HeaderToPrefixMappingDeterministicTest.php` — unit proof
- [ ] `framework/packages/platform/api-versioning/tests/Unit/RewriteDoesNotAffectQueryStringTest.php` — unit proof
- [ ] `framework/packages/platform/api-versioning/tests/Integration/MiddlewareRewritesPathBeforeRoutingBoundaryTest.php` — integration proof
- [ ] `framework/packages/platform/api-versioning/tests/Integration/UnsupportedVersionThrowsDeterministicExceptionTest.php` — integration proof
- [ ] `framework/tools/tests/Integration/Http/ApiVersioningWorksWithRoutingCompileTest.php` — optional system test
- [ ] `framework/packages/core/kernel/tests/Fixtures/HybridApp/config/modules.php` — fixture wiring (if needed)

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0077-api-versioning-header-middleware.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/api-versioning/composer.json`
- [ ] `framework/packages/platform/api-versioning/src/Module/ApiVersioningModule.php`
- [ ] `framework/packages/platform/api-versioning/src/Provider/ApiVersioningServiceProvider.php`
- [ ] `framework/packages/platform/api-versioning/config/api_versioning.php`
- [ ] `framework/packages/platform/api-versioning/config/rules.php`
- [ ] `framework/packages/platform/api-versioning/README.md`
- [ ] `framework/packages/platform/api-versioning/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/api-versioning/config/api_versioning.php`
- [ ] Keys (dot):
  - [ ] `api_versioning.enabled` = false
  - [ ] `api_versioning.mode` = 'header'               // 'header'|'disabled'
  - [ ] `api_versioning.header_name` = 'X-Api-Version'
  - [ ] `api_versioning.default_version` = 'v1'
  - [ ] `api_versioning.supported_versions` = ['v1']
  - [ ] `api_versioning.prefix_map` = ['v1' => '/v1']  // version => path prefix
  - [ ] `api_versioning.strict` = true                 // unsupported => error
- [ ] Rules:
  - [ ] `framework/packages/platform/api-versioning/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (no new tag names; provides constants only)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\ApiVersioning\Http\Middleware\ApiVersioningMiddleware`
  - [ ] adds tag: `http.middleware.system_pre` priority `835` meta `[]`
  - [ ] registers: `Coretsia\ApiVersioning\Errors\ApiVersioningProblemMapper`
  - [ ] adds tag: `error.mapper` priority `0` meta `[]`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- Context writes (safe only):
  - N/A (version stored only in request attributes or span attributes)
- Reset discipline:
  - N/A (stateless)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `http.api_versioning` (attrs: `outcome`, `version` as span attr only)
- [ ] Metrics:
  - [ ] `http.api_versioning_total` (labels: `method|outcome`)
  - [ ] `http.api_versioning_duration_ms` (labels: `method|outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `reason→status` (if you count `status=400` as outcome mapping in higher-level http metrics; do not add new label keys)
- [ ] Logs:
  - [ ] summary only (version string allowed; never dump raw headers)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\ApiVersioning\Exception\ApiVersionNotSupportedException` — errorCode `CORETSIA_API_VERSION_NOT_SUPPORTED`
- [ ] Mapping:
  - [ ] `ExceptionMapperInterface` via tag `error.mapper` (maps to `httpStatus=400`)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw request headers dump, cookies, tokens
- [ ] Allowed:
  - [ ] version string (e.g. `v1`) as safe value
  - [ ] `hash(value)` / `len(value)`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php`  
  (N/A: no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php`  
  (N/A: stateless)
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`  
  (asserts: names + label allowlist; no headers dump)
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`  
  (asserts: cookies/tokens not leaked)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/HybridApp/...` (only if routing boundary test requires boot)
- [ ] Fake adapters:
  - [ ] FakeLogger/FakeTracer/FakeMeter capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/api-versioning/tests/Unit/HeaderToPrefixMappingDeterministicTest.php`
  - [ ] `framework/packages/platform/api-versioning/tests/Unit/RewriteDoesNotAffectQueryStringTest.php`
- Contract:
  - [ ] `framework/packages/platform/api-versioning/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/api-versioning/tests/Integration/MiddlewareRewritesPathBeforeRoutingBoundaryTest.php`
  - [ ] `framework/packages/platform/api-versioning/tests/Integration/UnsupportedVersionThrowsDeterministicExceptionTest.php`
  - [ ] `framework/tools/tests/Integration/Http/ApiVersioningWorksWithRoutingCompileTest.php` (optional)
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: same headers + path → same rewrite
- [ ] Docs updated:
  - [ ] `framework/packages/platform/api-versioning/README.md`
  - [ ] `docs/adr/ADR-0077-api-versioning-header-middleware.md`
- [ ] What problem this epic solves
  - [ ] Provide an optional, deterministic API versioning mechanism without changing contracts/kernel.
  - [ ] Support header-based version selection that rewrites request path before routing.
  - [ ] Provide a helper for path-prefix versioning by wrapping `RouteProviderInterface` deterministically (opt-in).
- [ ] Non-goals / out of scope
  - [ ] Not a router; routing remains `platform/routing`.
  - [ ] Not an API gateway feature set (rate-limits/auth/tenancy remain separate owners).
  - [ ] No high-cardinality observability (no version as metric label; only span attr).
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/http` executes middleware via `http.middleware.system_pre` slot
  - [ ] `platform/problem-details` wraps errors, so versioning exceptions render as RFC7807
- [ ] Discovery / wiring is via tags:
  - [ ] `http.middleware.system_pre`
- [ ] When enabled, API versioning deterministically maps requests to the correct versioned path before routing without leaking headers or creating high-cardinality signals.
- [ ] When a request includes `X-Api-Version: v2`, then middleware rewrites `/users` → `/v2/users`, and routing resolves to the v2 route set.

---

### 6.410.0 Rate limit advanced (Redis store + tiers + burst, backward-compatible) (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.410.0"
owner_path: "framework/packages/integrations/rate-limit-redis/"

package_id: "integrations/rate-limit-redis"
composer: "coretsia/integrations-rate-limit-redis"
kind: runtime
module_id: "integrations.rate-limit-redis"

goal: "З Redis store увімкненим, rate limiting працює консистентно між інстансами з deterministic tier/burst правилами та policy-compliant observability."
provides:
- "Distributed `RateLimitStoreInterface` implementation backed by Redis (no raw keys leakage)"
- "Backward-compatible tier + burst support in `platform/http` rate-limit rules schema"
- "Policy-compliant observability + redaction (no high-cardinality labels; no secrets)"

tags_introduced: []
config_roots_introduced: ["rate_limit_redis"]
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/tags.md"                         # `http.middleware.*` reserved tags
- "docs/ssot/observability.md"                # label allowlist: driver|outcome only
- "docs/ssot/config-roots.md"                 # register `rate_limit_redis` root
- "docs/ssot/rate-limit-contracts.md"         # no high-cardinality leakage; no correlation_id in keys
- "docs/ssot/http-middleware-catalog.md"      # placement + priority constraints (rate-limit in app_pre)
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - `framework/packages/platform/http/src/Middleware/RateLimitMiddleware.php` — існуючий middleware owner `platform/http` (tier selection logic extends here)
  - `framework/packages/platform/http/src/RateLimit/Algorithm/TokenBucketLimiter.php` — існуючий алгоритм (extend для burst/tier; backward compatible)
  - `framework/packages/platform/http/config/http.php` — існуючий schema root `http.*` (extensions only)
  - `framework/packages/platform/http/config/rules.php` — існуючі validation rules (update: accept tier/burst fields)
  - `framework/packages/platform/http/README.md` — існуюча документація (update: tiers/burst + redis store integration)

- Required config roots/keys:
  - `http.rate_limit.*` — існуючий root (розширюється tiers/burst без лому сумісності)
  - `http.rate_limit.store` — має існувати/бути доступним для вибору driver (`in_memory|redis`)

- Required tags:
  - `http.middleware.system_pre` — існуючий slot для middleware
  - (priority) `790` — існуючий SSoT slot (rate-limit middleware)

- Required contracts / ports:
  - `Coretsia\Contracts\RateLimit\RateLimitStoreInterface` — store port (реюз, без змін контрактів)
  - `Coretsia\Contracts\RateLimit\RateLimitDecision` — decision shape
  - `Coretsia\Contracts\RateLimit\RateLimitState` — state shape
  - `Psr\Log\LoggerInterface` — логування (redaction обов’язковий)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — tracing (noop-safe)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics (noop-safe)
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` — optional (redis auth via `secret_ref`)
  - (optional) `Coretsia\Foundation\Time\Stopwatch` — stable API для duration

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http-app`
- `platform/routing`
- none other (integration package may depend on vendor redis client, but MUST NOT introduce new framework ports)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\RateLimit\RateLimitStoreInterface`
  - `Coretsia\Contracts\RateLimit\RateLimitDecision`
  - `Coretsia\Contracts\RateLimit\RateLimitState`
  - `Coretsia\Contracts\Secrets\SecretsResolverInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Time\Stopwatch`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - middleware (existing owner `platform/http`): `\Coretsia\Http\Middleware\RateLimitMiddleware::class`
  - middleware slots/tags: `http.middleware.system_pre` priority `790`
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/rate-limit-redis/src/Module/RateLimitRedisModule.php` — runtime module
- [ ] `framework/packages/integrations/rate-limit-redis/src/Provider/RateLimitRedisServiceProvider.php` — DI wiring
- [ ] `framework/packages/integrations/rate-limit-redis/src/Provider/RateLimitRedisServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/rate-limit-redis/config/rate_limit_redis.php` — config subtree (no repeated root)
- [ ] `framework/packages/integrations/rate-limit-redis/config/rules.php` — config validation rules
- [ ] `framework/packages/integrations/rate-limit-redis/README.md` — docs (incl. Observability / Errors / Security-Redaction)
- [ ] `framework/packages/integrations/rate-limit-redis/src/Store/RedisRateLimitStore.php` — implements `RateLimitStoreInterface`
- [ ] `framework/packages/integrations/rate-limit-redis/src/Redis/RedisClientFactory.php` — deterministic client creation (no secrets in logs)
- [ ] `framework/packages/integrations/rate-limit-redis/src/Security/Redaction.php` — `hash/len` helpers
- [ ] `framework/packages/integrations/rate-limit-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract proof (noop-safe)

#### Modifies

- [ ] `framework/packages/platform/http/src/RateLimit/Algorithm/TokenBucketLimiter.php` — extend підтримку burst/tier (backward compatible)
- [ ] `framework/packages/platform/http/src/Middleware/RateLimitMiddleware.php` — tier selection logic (no new labels)
- [ ] `framework/packages/platform/http/config/http.php` — extend schema for tier rules + store selection
- [ ] `framework/packages/platform/http/config/rules.php` — accept tier/burst fields (backward compatible)
- [ ] `framework/packages/platform/http/README.md` — document tiers/burst + Redis store integration

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/rate-limit-redis/composer.json`
- [ ] `framework/packages/integrations/rate-limit-redis/src/Module/RateLimitRedisModule.php`
- [ ] `framework/packages/integrations/rate-limit-redis/src/Provider/RateLimitRedisServiceProvider.php`
- [ ] `framework/packages/integrations/rate-limit-redis/config/rate_limit_redis.php`
- [ ] `framework/packages/integrations/rate-limit-redis/config/rules.php`
- [ ] `framework/packages/integrations/rate-limit-redis/README.md`
- [ ] `framework/packages/integrations/rate-limit-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/rate-limit-redis/config/rate_limit_redis.php`
  - [ ] `framework/packages/platform/http/config/http.php` (extensions only)
- [ ] Keys (dot):
  - [ ] `rate_limit_redis.enabled` = false
  - [ ] `rate_limit_redis.connection.host` = '127.0.0.1'
  - [ ] `rate_limit_redis.connection.port` = 6379
  - [ ] `rate_limit_redis.connection.db` = 0
  - [ ] `rate_limit_redis.connection.password.secret_ref` = null
  - [ ] `rate_limit_redis.key_prefix` = 'rl:'
  - [ ] `http.rate_limit.store` = 'in_memory'  // 'in_memory'|'redis'
  - [ ] `http.rate_limit.tiers.enabled` = false
  - [ ] `http.rate_limit.tiers.map` = []       // role/plan → tier config (deterministic)
  - [ ] `http.rate_limit.rules` = []           // backward-compatible; may include burst fields
- [ ] Rules:
  - [ ] `framework/packages/integrations/rate-limit-redis/config/rules.php` enforces shape
  - [ ] `framework/packages/platform/http/config/rules.php` updated to accept tier/burst fields (backward compatible)

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] binds `Coretsia\Contracts\RateLimit\RateLimitStoreInterface`
    → `Coretsia\RateLimitRedis\Store\RedisRateLimitStore` (when `rate_limit_redis.enabled=true`)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::ACTOR_ID` (if present; safe id only)
  - [ ] `ContextKeys::CLIENT_IP`
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] any stateful redis client wrapper implements `ResetInterface` and tagged `kernel.reset` (only if it holds mutable state)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `http.rate_limit` (attrs: `outcome`, `tier` as span attr only)
  - [ ] `redis.op` (attrs: `outcome`, `op`)
- [ ] Metrics:
  - [ ] reuse existing `platform/http` metrics:
    - [ ] `http.rate_limit_allowed_total` (labels: `driver|outcome`)
    - [ ] `http.rate_limit_blocked_total` (labels: `driver|outcome`)
    - [ ] `http.rate_limit_duration_ms` (labels: `driver|outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `tier→operation` (tier MUST NOT be a label; use span attr only)
- [ ] Logs:
  - [ ] allow/deny summary only; no raw keys; only `hash(key)` where unavoidable
- [ ] Policy note:
  - [ ] tier MUST NOT be a metric label (span attr only)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\RateLimitRedis\Exception\RateLimitRedisException` — errorCode `CORETSIA_RATE_LIMIT_REDIS_ERROR`
- [ ] Mapping:
  - [ ] keep errors internal OR reuse existing mapper (no dupes)
  - [ ] HTTP 429 still produced by http middleware
  - [ ] store failure policy: fail-open or fail-closed per `platform/http` policy (documented)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] redis password
  - [ ] raw rate-limit keys
  - [ ] raw path
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` — N/A (no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` — only if stateful wrapper + tag used
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  - evidence (planned): `framework/packages/integrations/rate-limit-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - evidence (planned): `framework/packages/integrations/rate-limit-redis/tests/Contract/NoSecretLoggingContractTest.php`
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  - evidence (planned): `framework/packages/integrations/rate-limit-redis/tests/Contract/NoSecretLoggingContractTest.php` (asserts no secrets/raw keys)

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger capture events for assertions
- [ ] Fixture app:
  - [ ] `framework/tools/tests/Integration/Http/RateLimitRedisWorksInHybridPresetTest.php` (optional; only if cross-package boot needed)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/http/tests/Unit/TierSelectionDeterministicTest.php`
  - [ ] `framework/packages/integrations/rate-limit-redis/tests/Unit/KeyNormalizationNoRawPathTest.php`
- Contract:
  - [ ] `framework/packages/integrations/rate-limit-redis/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/integrations/rate-limit-redis/tests/Contract/NoSecretLoggingContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/http/tests/Integration/TierBurstRulesEnforcedDeterministicallyTest.php`
  - [ ] `framework/packages/integrations/rate-limit-redis/tests/Integration/RedisStoreParityWithInMemoryStoreTest.php` (can be dockerized in CI)
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] tier/burst decisions stable for same inputs
- [ ] Docs updated:
  - [ ] `framework/packages/integrations/rate-limit-redis/README.md`
  - [ ] `framework/packages/platform/http/README.md`
- [ ] What problem this epic solves
  - [ ] Provide a distributed `RateLimitStoreInterface` implementation backed by Redis.
  - [ ] Extend `platform/http` rate-limit rules schema to support tiers + burst (backward compatible).
  - [ ] Preserve SSoT constraints: no high-cardinality keys/labels; deterministic decisions; no secret leaks.
- [ ] Non-goals / out of scope
  - [ ] Not changing contracts ports (reuse existing `RateLimitStoreInterface`).
  - [ ] Not adding new HTTP endpoints; rate-limit remains a middleware in `platform/http`.
  - [ ] No “dynamic pricing plans” feature; tier selection is config/policy-based only.
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/http` rate-limit middleware remains in `http.middleware.system_pre` priority `790` (existing SSoT slot)
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] With Redis store enabled, rate limiting works consistently across instances with deterministic tier/burst rules and policy-compliant observability.
- [ ] When two app instances receive the same burst of requests under the same tier rule, both enforce the same allow/deny decisions via Redis with stable retry-after behavior.

---

### 6.420.0 Uploads virus scanning hook (quarantine → scan job → verdict) (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.420.0"
owner_path: "framework/packages/integrations/virus-scan-clamav/"

package_id: "integrations/virus-scan-clamav"
composer: "coretsia/integrations-virus-scan-clamav"
kind: runtime
module_id: "integrations.virus-scan-clamav"

goal: "Quarantine uploads скануються асинхронно, та отримують deterministic verdict (clean/quarantined) без витоку filenames/contents у логи/метрики."
provides:
- "Opt-in pipeline: quarantine → enqueue scan job → deterministic verdict record"
- "Pluggable scanner integration (ClamAV reference) as module"
- "Policy-compliant logging/metrics/tracing (no filenames, no payloads)"

tags_introduced: []
config_roots_introduced: ["virus_scan_clamav"]
artifacts_introduced: ["quarantine_verdict@1"]

adr: none
ssot_refs:
- "docs/ssot/artifacts.md"                    # register `quarantine_verdict@1` envelope + invariants
- "docs/ssot/config-roots.md"                 # register `virus_scan_clamav` root
- "docs/ssot/observability.md"                # labels allowlist: driver|outcome
- "docs/ssot/context-store.md"                # context reads only; no unsafe keys
- "docs/ssot/filesystem-contracts.md"         # optional DiskInterface usage, no path leaks
- "docs/ssot/uow-and-reset-contracts.md"      # ResetInterface rules if stateful handler exists
---

### Artifact registration & ownership (MUST)

- Artifact `quarantine_verdict@1` MUST be registered in `docs/ssot/artifacts.md` (Phase 1.30.0 envelope).
- Canonical semantic owner MUST be `platform/uploads` (artifact belongs to uploads quarantine domain).
  - This integration (`integrations/virus-scan-clamav`) provides a scanner implementation that WRITES the artifact,
    but MUST NOT own/fragment the schema (future scanners MUST reuse the same artifact schema).

### Artifact envelope (single-choice, MUST)

File: `skeleton/var/quarantine/<id>.verdict.json`

Shape (all artifacts use envelope):
{
"_meta": {
"name": "quarantine_verdict",
"schemaVersion": 1,
"fingerprint": "<sha256 of stable-encoded payload>",
"generator": "platform/uploads",
"requires": {
"scanner": "clamav"
}
},
"payload": {
"quarantineId": "<opaque id token (NOT filename)>",
"verdict": "clean|quarantined|scan_failed",
"engine": "clamav",
"checkedAt": null
}
}

Rules:
- Stable JSON bytes: map keys sorted recursively (`strcmp`), lists preserve order, LF-only + final newline.
- `checkedAt` MUST be `null` (timestamps forbidden in artifacts per 1.30.0). Do NOT write time-of-scan.
- MUST NOT contain: filename/originalName, paths, file bytes, user ids, headers/cookies/auth/session tokens.
- Allowed: safe tokens, hashes/len if needed (prefer none in artifact).

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - `framework/packages/platform/uploads/src/Http/Middleware/MultipartFormDataMiddleware.php` — existing uploads middleware (owner `platform/uploads`)
  - `framework/packages/platform/uploads/config/uploads.php` — existing uploads config root (extended with `uploads.virus_scan.*`)
  - `framework/packages/platform/uploads/config/rules.php` — existing validation rules (update: accept `uploads.virus_scan.*`)
  - `framework/packages/platform/uploads/README.md` — existing docs (update: scan hook + queue wiring)
  - `framework/packages/platform/queue/src/Console/QueueWorkCommand.php` — existing worker entrypoint used to run scan jobs

- Required config roots/keys:
  - `uploads.*` — root exists; add subtree `uploads.virus_scan.*`
  - `queue.handlers.map` (in platform/queue config) — mapping for job handler must be possible:
    - `queue.handlers.map['uploads.virus_scan']` documented

- Required tags:
  - `http.middleware.app_pre` — uploads middleware slot
  - (priority) `80` — existing SSoT priority for multipart middleware
  - `cli.command` — existing discovery tag for CLI commands (queue worker already uses it)

- Required contracts / ports:
  - `Coretsia\Contracts\Queue\QueueInterface` — enqueue scan job
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — context reads (correlation/uow)
  - `Coretsia\Contracts\Filesystem\DiskInterface` (optional) — if scanner reads quarantine files via disk abstraction
  - `Psr\Log\LoggerInterface` — redacted logging
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — tracing (noop-safe)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics (noop-safe)
  - (optional) `Coretsia\Foundation\Time\Stopwatch` — duration helper

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/http` (uploads middleware already exists; integration MUST NOT touch http directly)
- `platform/observability`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Queue\QueueInterface`
  - `Coretsia\Contracts\Filesystem\DiskInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Time\Stopwatch`

### Entry points / integration points (MUST)

- CLI:
  - `queue:work` → `framework/packages/platform/queue/src/Console/QueueWorkCommand.php` (existing; runs scan jobs)
- HTTP:
  - middleware (existing owner `platform/uploads`): `\Coretsia\Uploads\Http\Middleware\MultipartFormDataMiddleware::class`
  - middleware slots/tags: `http.middleware.app_pre` priority `80`
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - writes (runtime-only, not fingerprint):
    - `skeleton/var/quarantine/<id>.verdict.json`
  - reads:
    - validates `schemaVersion` + payload schema for verdict files

### Scanner transport policy (MUST)

- Default transport MUST be clamd socket (`tcp://...` or `unix://...`).
- This epic MUST NOT execute external processes (`exec/shell_exec/system/proc_open/...`) for scanning.
  Rationale: determinism + security + CI portability.

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/virus-scan-clamav/src/Module/VirusScanClamavModule.php` — runtime module
- [ ] `framework/packages/integrations/virus-scan-clamav/src/Provider/VirusScanClamavServiceProvider.php` — DI wiring
- [ ] `framework/packages/integrations/virus-scan-clamav/src/Provider/VirusScanClamavServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/virus-scan-clamav/config/virus_scan_clamav.php` — config subtree (no repeated root)
- [ ] `framework/packages/integrations/virus-scan-clamav/config/rules.php` — config validation rules
- [ ] `framework/packages/integrations/virus-scan-clamav/README.md` — docs (incl. Observability / Errors / Security-Redaction)
- [ ] `framework/packages/integrations/virus-scan-clamav/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract proof (noop-safe)

- [ ] `framework/packages/integrations/virus-scan-clamav/src/Scan/ClamavClient.php` — deterministic client (socket/exec adapter)
- [ ] `framework/packages/integrations/virus-scan-clamav/src/Scan/ClamavPolicy.php` — VO (deterministic timeouts/limits)
- [ ] `framework/packages/integrations/virus-scan-clamav/src/Queue/ScanQuarantineJobHandler.php` — queue handler, no payload logs
- [ ] `framework/packages/integrations/virus-scan-clamav/src/Security/Redaction.php` — hash/len helpers
- [ ] `framework/packages/integrations/virus-scan-clamav/src/Observability/VirusScanInstrumentation.php` — spans/metrics helpers

#### Modifies

- [ ] `framework/packages/platform/uploads/src/VirusScan/VirusScanDispatch.php` — emits scan job on quarantine write (if enabled)
- [ ] `framework/packages/platform/uploads/config/uploads.php` — adds virus scan toggles
- [ ] `framework/packages/platform/uploads/config/rules.php` — updated to accept `uploads.virus_scan.*`
- [ ] `framework/packages/platform/uploads/README.md` — documents scan hook + queue wiring
- [ ] `docs/ssot/artifacts.md` - add:

| name               | schemaVersion | owner package_id | purpose                                                                 |
|--------------------|--------------:|------------------|-------------------------------------------------------------------------|
| quarantine_verdict |             1 | platform/uploads | deterministic verdict for quarantined upload (no filenames/paths/bytes) |

<!-- INSERT below (or in per-artifact section) -->

## quarantine_verdict@1 (owner: platform/uploads)

Envelope (single-choice):
- `{ "_meta": <header>, "payload": <schema-specific> }`

Header (single-choice):
- `name: "quarantine_verdict"`
- `schemaVersion: 1`
- `fingerprint: string` (sha256 of stable-encoded payload)
- `generator: "platform/uploads"` (no timestamps/abs paths)
- `requires: { "scanner": "clamav" }` (stable tokens only)

Payload (schemaVersion=1):
- `quarantineId: string` (opaque token; MUST NOT be filename)
- `verdict: "clean|quarantined|scan_failed"`
- `engine: "clamav"`
- `checkedAt: null` (timestamps forbidden)

Security/PII:
- MUST NOT include filenames, originalName, paths, bytes, user ids, headers/cookies/auth/session.

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/virus-scan-clamav/composer.json`
- [ ] `framework/packages/integrations/virus-scan-clamav/src/Module/VirusScanClamavModule.php`
- [ ] `framework/packages/integrations/virus-scan-clamav/src/Provider/VirusScanClamavServiceProvider.php`
- [ ] `framework/packages/integrations/virus-scan-clamav/config/virus_scan_clamav.php`
- [ ] `framework/packages/integrations/virus-scan-clamav/config/rules.php`
- [ ] `framework/packages/integrations/virus-scan-clamav/README.md`
- [ ] `framework/packages/integrations/virus-scan-clamav/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/uploads/config/uploads.php`
  - [ ] `framework/packages/integrations/virus-scan-clamav/config/virus_scan_clamav.php`
- [ ] Keys (dot):
  - [ ] `uploads.virus_scan.enabled` = false
  - [ ] `uploads.virus_scan.dispatch.via` = 'queue'
  - [ ] `uploads.virus_scan.queue.job_name` = 'uploads.virus_scan'
  - [ ] `uploads.virus_scan.queue.payload_schema_version` = 1
  - [ ] `virus_scan_clamav.enabled` = false
  - [ ] `virus_scan_clamav.socket` = 'tcp://127.0.0.1:3310'
  - [ ] `virus_scan_clamav.timeout_seconds` = 10
  - [ ] `virus_scan_clamav.max_bytes` = 52428800
- [ ] Rules:
  - [ ] `framework/packages/platform/uploads/config/rules.php` updated to accept `uploads.virus_scan.*`
  - [ ] `framework/packages/integrations/virus-scan-clamav/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers service: `Coretsia\VirusScanClamav\Queue\ScanQuarantineJobHandler`
  - [ ] queue handler mapping documented for:
    - `framework/packages/platform/queue/config/queue.php` key `queue.handlers.map['uploads.virus_scan']`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `skeleton/var/quarantine/<id>.json` (already exists via uploads; schemaVersion, no filename)
  - [ ] `skeleton/var/quarantine/<id>.verdict.json` (new; schemaVersion, verdict only, deterministic)
- [ ] Reads:
  - [ ] validates `schemaVersion` + payload schema for verdict files

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_TYPE` (queue)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] handler state implements `ResetInterface` + tagged `kernel.reset` (only if stateful)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `uploads.virus_scan.dispatch` (attrs: `outcome`)
  - [ ] `uploads.virus_scan.execute` (attrs: `outcome`)
- [ ] Metrics:
  - [ ] `uploads.virus_scan_total` (labels: `driver|outcome`)
  - [ ] `uploads.virus_scan_duration_ms` (labels: `driver|outcome`)
  - [ ] `uploads.virus_scan_failed_total` (labels: `driver|outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `via→driver` (driver=`clamav`)
- [ ] Logs:
  - [ ] only quarantine id hash + bytes + outcome; never filename/content
- [ ] Label normalization:
  - [ ] `via→driver` (driver=`clamav`)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\VirusScanClamav\Exception\VirusScanFailedException` — errorCode `CORETSIA_VIRUS_SCAN_FAILED`
  - [ ] `Coretsia\VirusScanClamav\Exception\VirusScanUnavailableException` — errorCode `CORETSIA_VIRUS_SCAN_UNAVAILABLE`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)
  - [ ] job failures handled by queue retry policy
  - [ ] HTTP status hint: N/A (async job)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] filenames/originalName
  - [ ] file bytes
  - [ ] authorization/cookies
  - [ ] user ids
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` — N/A (no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` — only if handler state tagged `kernel.reset`
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  - evidence (planned): `framework/packages/integrations/virus-scan-clamav/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - evidence (planned): `framework/packages/integrations/virus-scan-clamav/tests/Contract/NoSecretLoggingContractTest.php`
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  - evidence (planned): `framework/packages/integrations/virus-scan-clamav/tests/Contract/NoSecretLoggingContractTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/tools/tests/Integration/E2E/UploadsVirusScanHookWorksTest.php` (optional; uses `queue:work --once`)
- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/uploads/tests/Unit/VirusScanDispatchBuildsDeterministicPayloadTest.php`
  - [ ] `framework/packages/integrations/virus-scan-clamav/tests/Unit/VerdictSchemaDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/integrations/virus-scan-clamav/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/integrations/virus-scan-clamav/tests/Contract/NoSecretLoggingContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/uploads/tests/Integration/QuarantineEmitsScanJobDeterministicallyTest.php`
  - [ ] `framework/packages/integrations/virus-scan-clamav/tests/Integration/ScanFailureDoesNotCrashWorkerDeterministicallyTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] same quarantine entry → same job payload bytes
  - [ ] verdict file schema + key order deterministic
- [ ] Docs updated:
  - [ ] `framework/packages/integrations/virus-scan-clamav/README.md`
  - [ ] `framework/packages/platform/uploads/README.md`
- [ ] What problem this epic solves
  - [ ] Add an opt-in pipeline: uploads quarantine triggers a virus scan job.
  - [ ] Provide a ClamAV (or generic) scanner integration as a pluggable module.
  - [ ] Ensure deterministic, policy-compliant logging/metrics (no filenames, no payloads).
- [ ] Non-goals / out of scope
  - [ ] Not implementing full AV farm orchestration; only a reference integration.
  - [ ] Not changing uploads multipart parsing middleware semantics.
  - [ ] No storing original filenames or user identifiers in scan artifacts/logs.
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/uploads` provides quarantine write; emits scan job when enabled
  - [ ] `platform/queue` provides worker; job handler lives in this integration
- [ ] Discovery / wiring is via tags:
  - [ ] `cli.command` (queue worker commands already exist)
  - [ ] (job handler mapping is explicit via queue config map)
- [ ] Uploaded files placed into quarantine are scanned asynchronously and deterministically marked clean/quarantined without leaking filenames or contents.
- [ ] When an upload is quarantined, then a scan job is enqueued, the handler runs ClamAV scan, and writes a deterministic verdict record without logging file content or name.

---

### 6.430.0 Policy engine (OPA-like DSL) (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.430.0"
owner_path: "framework/packages/enterprise/policy-engine/"

package_id: "enterprise/policy-engine"
composer: "coretsia/enterprise-policy-engine"
kind: runtime
module_id: "enterprise.policy-engine"

goal: "Authorization decisions evaluated deterministically from a policy DSL with optional cache, emitting safe observability signals and never leaking inputs."
provides:
- "Deterministic authorization rules evaluation via compact OPA-like DSL"
- "Optional PSR-16 caching with deterministic keys and safe redaction"
- "Drop-in `AuthorizationInterface` implementation (no platform→enterprise dependency)"

tags_introduced: []
config_roots_introduced: ["policy_engine"]
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/config-roots.md"                 # register `policy_engine` root
- "docs/ssot/observability.md"                # label allowlist: driver|operation|outcome
- "docs/ssot/context-store.md"                # safe context reads only
- "docs/ssot/stateful-services.md"            # if cache is stateful -> ResetInterface + kernel.reset tag
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - N/A (new package; integrates via contract binding only)

- Required config roots/keys:
  - N/A (introduces `policy_engine.*`)

- Required tags:
  - N/A

- Required contracts / ports:
  - `Coretsia\Contracts\Auth\AuthorizationInterface` — binding target (drop-in override)
  - `Coretsia\Contracts\Auth\IdentityInterface` — identity input
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — context reads
  - `Psr\Log\LoggerInterface` — logging (redacted)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — tracing (noop-safe)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics (noop-safe)
  - `Psr\SimpleCache\CacheInterface` (optional) — cache
- Foundation (if used):
  - `Coretsia\Foundation\Context\ContextKeys`
  - `Coretsia\Foundation\Discovery\DeterministicOrder`
  - `Coretsia\Foundation\Time\Stopwatch`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/auth` (MUST integrate via `AuthorizationInterface` only)
- `platform/http`
- `platform/observability`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
  - `Psr\SimpleCache\CacheInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Auth\AuthorizationInterface`
  - `Coretsia\Contracts\Auth\IdentityInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Context\ContextKeys`
  - `Coretsia\Foundation\Discovery\DeterministicOrder`
  - `Coretsia\Foundation\Time\Stopwatch`

### Entry points / integration points (MUST)

- CLI:
  - (optional) `policy:debug` → `framework/packages/enterprise/policy-engine/src/Console/PolicyDebugCommand.php` (only if added)
- HTTP:
  - N/A
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/enterprise/policy-engine/src/Module/PolicyEngineModule.php` — runtime module
- [ ] `framework/packages/enterprise/policy-engine/src/Provider/PolicyEngineServiceProvider.php` — DI wiring
- [ ] `framework/packages/enterprise/policy-engine/src/Provider/PolicyEngineServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/enterprise/policy-engine/config/policy_engine.php` — config subtree (no repeated root)
- [ ] `framework/packages/enterprise/policy-engine/config/rules.php` — config validation rules
- [ ] `framework/packages/enterprise/policy-engine/README.md` — docs (incl. Observability / Errors / Security-Redaction)
- [ ] `framework/packages/enterprise/policy-engine/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract proof (noop-safe)

- [ ] `framework/packages/enterprise/policy-engine/src/Policy/PolicyEngineAuthorization.php` — implements `AuthorizationInterface`
- [ ] `framework/packages/enterprise/policy-engine/src/Policy/Dsl/PolicyParser.php` — deterministic parse (no randomness)
- [ ] `framework/packages/enterprise/policy-engine/src/Policy/Dsl/PolicyEvaluator.php` — deterministic evaluation order
- [ ] `framework/packages/enterprise/policy-engine/src/Policy/Cache/PolicyCacheKey.php` — deterministic key (hash only)
- [ ] `framework/packages/enterprise/policy-engine/src/Observability/PolicyInstrumentation.php` — spans/metrics/log helpers
- [ ] `framework/packages/enterprise/policy-engine/src/Security/Redaction.php` — hash/len helpers
- [ ] `framework/packages/enterprise/policy-engine/src/Exception/PolicyEngineException.php` — deterministic codes
- [ ] `framework/packages/enterprise/policy-engine/src/Exception/PolicyParseException.php` — invalid DSL

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/enterprise/policy-engine/composer.json`
- [ ] `framework/packages/enterprise/policy-engine/src/Module/PolicyEngineModule.php`
- [ ] `framework/packages/enterprise/policy-engine/src/Provider/PolicyEngineServiceProvider.php`
- [ ] `framework/packages/enterprise/policy-engine/config/policy_engine.php`
- [ ] `framework/packages/enterprise/policy-engine/config/rules.php`
- [ ] `framework/packages/enterprise/policy-engine/README.md`
- [ ] `framework/packages/enterprise/policy-engine/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/enterprise/policy-engine/config/policy_engine.php`
- [ ] Keys (dot):
  - [ ] `policy_engine.enabled` = false
  - [ ] `policy_engine.dsl.enabled` = true
  - [ ] `policy_engine.dsl.policies` = []      // deterministic list/map of rules
  - [ ] `policy_engine.cache.enabled` = false
  - [ ] `policy_engine.cache.ttl_seconds` = 60
  - [ ] `policy_engine.max_depth` = 10         // safety rail
- [ ] Rules:
  - [ ] `framework/packages/enterprise/policy-engine/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] binds `Coretsia\Contracts\Auth\AuthorizationInterface`
    → `Coretsia\PolicyEngine\Policy\PolicyEngineAuthorization` (when `policy_engine.enabled=true`)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::ACTOR_ID` (safe id only)
  - [ ] `ContextKeys::TENANT_ID` (safe id only, if present)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] any in-memory policy cache implements `ResetInterface` + tagged `kernel.reset` (only if stateful)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `policy.evaluate` (attrs: `outcome`, `ability` as span attr OK)
  - [ ] `policy.cache` (attrs: `outcome`, `driver` as span attr)
- [ ] Metrics:
  - [ ] `policy.evaluate_total` (labels: `driver|operation|outcome`)
  - [ ] `policy.evaluate_duration_ms` (labels: `driver|operation|outcome`)
  - [ ] `policy.cache_total` (labels: `driver|operation|outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `tier→operation` (if you later use tiers; never add new label keys)
- [ ] Logs:
  - [ ] decision summary (allowed/denied) without input values; no DSL source dump in prod

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\PolicyEngine\Exception\PolicyParseException` — errorCode `CORETSIA_POLICY_PARSE_FAILED`
  - [ ] `Coretsia\PolicyEngine\Exception\PolicyEngineException` — errorCode `CORETSIA_POLICY_ENGINE_ERROR`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) OR add mapper in this package (optional; typically 500)
  - [ ] HTTP status hint (optional): parse/config errors are server errors → `httpStatus=500` (if mapped)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] identity attributes beyond safe ids
  - [ ] tenant ids as labels
  - [ ] request headers/cookies
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` — N/A (no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` — only if cache tagged `kernel.reset`
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  - evidence (planned): `framework/packages/enterprise/policy-engine/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - evidence (planned): `framework/packages/enterprise/policy-engine/tests/Contract/NoSecretLoggingContractTest.php`
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  - evidence (planned): `framework/packages/enterprise/policy-engine/tests/Contract/NoSecretLoggingContractTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/tools/tests/Integration/Auth/PolicyEngineAuthorizationOverridesRbacTest.php` (optional)
- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/enterprise/policy-engine/tests/Unit/DslEvaluationOrderDeterministicTest.php`
  - [ ] `framework/packages/enterprise/policy-engine/tests/Unit/CacheKeyDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/enterprise/policy-engine/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/enterprise/policy-engine/tests/Contract/NoSecretLoggingContractTest.php`
- Integration:
  - [ ] `framework/packages/enterprise/policy-engine/tests/Integration/AuthorizationInterfaceDecisionStableAcrossRunsTest.php`
  - [ ] `framework/packages/enterprise/policy-engine/tests/Integration/OptionalCacheDoesNotChangeDecisionTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] same inputs → same decisions
- [ ] Docs updated:
  - [ ] `framework/packages/enterprise/policy-engine/README.md`
- [ ] What problem this epic solves
  - [ ] Provide deterministic authorization rules evaluation via a compact DSL (OPA-like) without embedding rules in controllers.
  - [ ] Allow optional caching (PSR-16) with deterministic keys and safe redaction.
  - [ ] Integrate as a drop-in `AuthorizationInterface` implementation (no platform→enterprise dependency).
- [ ] Non-goals / out of scope
  - [ ] Not a full OPA runtime; DSL is limited and deterministic by design.
  - [ ] No user/profile/PII evaluation inputs by default; only safe ids + role/ability strings.
  - [ ] No dynamic rule fetching from network in this epic.
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/auth` will resolve `AuthorizationInterface` from DI (this module may override default RBAC)
  - [ ] `platform/logging|tracing|metrics` provide implementations/noop-safe
- [ ] Authorization decisions are evaluated deterministically from a policy DSL with optional cache, emitting safe observability signals and never leaking inputs.
- [ ] When `platform/auth` checks an ability via `AuthorizationInterface->can(...)`, then the policy-engine returns the same decision for identical inputs across runs and instances, optionally serving from cache.

---

### 6.440.0 Distributed scheduler (leader election via LockFactory) (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.440.0"
owner_path: "framework/packages/platform/scheduler/"

package_id: "platform/scheduler"
composer: "coretsia/platform-scheduler"
kind: runtime
module_id: "platform.scheduler"

goal: "У кластері лише elected leader виконує due schedules; non-leaders виходять deterministically без side effects."
provides:
- "Leader election for scheduler in multi-instance deployments via `LockFactoryInterface`"
- "Backward-compatible scheduler API/CLI (`schedule:run`) with deterministic leader/non-leader outcome"
- "Policy-compliant observability without high-cardinality metric labels"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/observability.md"                # label allowlist: outcome only for leader election metrics
- "docs/ssot/tags.md"                         # cli.command reserved tag
- "docs/ssot/stateful-services.md"            # only if election loop is stateful
- "docs/ssot/uow-and-reset-contracts.md"      # ResetInterface + kernel.reset discipline (if needed)
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - `framework/packages/platform/scheduler/src/Console/ScheduleRunCommand.php` — existing CLI entrypoint (behavior remains backward compatible)
  - `framework/packages/platform/scheduler/src/Console/ScheduleListCommand.php` — existing CLI command
  - `framework/packages/platform/scheduler/src/Scheduler/ScheduleRunner.php` — existing runner (extended to guard execution)
  - `framework/packages/platform/scheduler/config/scheduler.php` — existing config root (extended)
  - `framework/packages/platform/scheduler/config/rules.php` — existing rules (updated)
  - `framework/packages/platform/scheduler/README.md` — existing docs (updated)

- Required config roots/keys:
  - `scheduler.*` — existing root; add distributed/leader keys

- Required tags:
  - `cli.command` — existing discovery tag (platform/cli discovers scheduler commands)
  - `kernel.reset` — only if stateful long-running election loop state used

- Required contracts / ports:
  - `Coretsia\Contracts\Scheduler\ScheduleRunnerInterface`
  - `Coretsia\Contracts\Scheduler\ScheduleRegistryInterface`
  - `Coretsia\Contracts\Scheduler\ScheduleDefinition`
  - `Coretsia\Contracts\Lock\LockFactoryInterface` — leader lock acquire/release
  - `Psr\Log\LoggerInterface` — redacted logging
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — tracing (noop-safe)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — metrics (noop-safe)
- optional:
  - `Coretsia\Contracts\Bus\CommandBusInterface`
  - `Coretsia\Contracts\Queue\QueueInterface`
  - `Coretsia\Foundation\Time\Stopwatch`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- `platform/http`
- `platform/observability`
- `integrations/*` (platform package must not depend on integrations)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Scheduler\ScheduleRunnerInterface`
  - `Coretsia\Contracts\Scheduler\ScheduleRegistryInterface`
  - `Coretsia\Contracts\Scheduler\ScheduleDefinition`
  - `Coretsia\Contracts\Lock\LockFactoryInterface`
  - `Coretsia\Contracts\Bus\CommandBusInterface` (optional)
  - `Coretsia\Contracts\Queue\QueueInterface` (optional)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs (if used):
  - `Coretsia\Foundation\Time\Stopwatch`

### Entry points / integration points (MUST)

- CLI:
  - `schedule:run` → `framework/packages/platform/scheduler/src/Console/ScheduleRunCommand.php` (existing)
  - `schedule:list` → `framework/packages/platform/scheduler/src/Console/ScheduleListCommand.php` (existing)
- HTTP:
  - N/A
- Kernel hooks/tags:
  - N/A (scheduler wraps tasks in KernelRuntime UoW as per existing design)
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/scheduler/src/Leader/LeaderElection.php` — deterministic lock acquire/renew/release orchestration
- [ ] `framework/packages/platform/scheduler/src/Leader/LeaderPolicy.php` — VO (ttl/renew ints only)
- [ ] `framework/packages/platform/scheduler/src/Observability/SchedulerLeaderInstrumentation.php` — spans/metrics helpers
- [ ] `framework/packages/platform/scheduler/src/Security/Redaction.php` — hash lock key, no raw key logs
- [ ] `framework/packages/platform/scheduler/src/Provider/Tags.php` — only if not already present (tag constants)

#### Modifies

- [ ] `framework/packages/platform/scheduler/src/Scheduler/ScheduleRunner.php` — guard execution behind leader election when enabled
- [ ] `framework/packages/platform/scheduler/config/scheduler.php` — add distributed mode + leader policy keys
- [ ] `framework/packages/platform/scheduler/config/rules.php` — updated to enforce new shape
- [ ] `framework/packages/platform/scheduler/README.md` — document distributed mode + config

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/scheduler/src/Module/SchedulerModule.php` (already exists)
- [ ] `framework/packages/platform/scheduler/src/Provider/SchedulerServiceProvider.php` (already exists)
- [ ] `framework/packages/platform/scheduler/src/Provider/SchedulerServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/scheduler/config/scheduler.php` (already exists; modified)
- [ ] `framework/packages/platform/scheduler/config/rules.php` (already exists; modified)
- [ ] `framework/packages/platform/scheduler/README.md` (modified)
- [ ] `framework/packages/platform/scheduler/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (already exists)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/scheduler/config/scheduler.php`
- [ ] Keys (dot):
  - [ ] `scheduler.distributed.enabled` = false
  - [ ] `scheduler.leader.lock_key` = 'scheduler:leader'
  - [ ] `scheduler.leader.ttl_seconds` = 60
  - [ ] `scheduler.leader.renew.enabled` = true
  - [ ] `scheduler.leader.renew_every_seconds` = 20
- [ ] Rules:
  - [ ] `framework/packages/platform/scheduler/config/rules.php` updated to enforce shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Scheduler\Leader\LeaderElection`
  - [ ] registers: `Coretsia\Scheduler\Leader\LeaderPolicy`
  - [ ] ensures `ScheduleRunner` uses leader election when `scheduler.distributed.enabled=true`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_TYPE` (scheduler)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] long-running leader election loop state implements `ResetInterface` + tagged `kernel.reset` (only if stateful)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `scheduler.leader_election` (attrs: `outcome`)
  - [ ] reuse existing: `scheduler.run`, `scheduler.task`
- [ ] Metrics:
  - [ ] `scheduler.leader_election_total` (labels: `outcome`)
  - [ ] `scheduler.leader_election_duration_ms` (labels: `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `kind/op→operation` (if you record operation, use `operation=leader_election` and keep as allowed label)
- [ ] Logs:
  - [ ] acquire success/fail summaries, no raw lock key (hash only)
- [ ] Policy note:
  - [ ] no leader id / instance id / schedule id as metric labels

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Scheduler\Exception\LeaderElectionFailedException` — errorCode `CORETSIA_SCHEDULER_LEADER_ELECTION_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes) (CLI reports normalized error)
  - [ ] HTTP status hint: N/A

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw lock key
  - [ ] instance identifiers
  - [ ] schedule ids in metrics labels
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` — N/A (no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` — only if stateful loop tagged `kernel.reset`
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  - evidence (existing/planned): `framework/packages/platform/scheduler/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  - evidence (planned): contract asserting no raw lock key in logs/metrics (can be part of observability/redaction contract)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/tools/tests/Integration/Cli/DistributedScheduleRunSingleLeaderTest.php` (optional)
- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/scheduler/tests/Unit/LeaderPolicyDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/scheduler/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/platform/scheduler/tests/Integration/LeaderElectionPreventsDoubleRunWithSharedLockTest.php`
  - [ ] `framework/packages/platform/scheduler/tests/Integration/NonLeaderReportsDeterministicOutcomeTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] same lock state → same leader/non-leader outcome
- [ ] Docs updated:
  - [ ] `framework/packages/platform/scheduler/README.md`
- [ ] What problem this epic solves
  - [ ] Ensure only one scheduler instance runs due tasks in multi-instance deployments (leader election).
  - [ ] Reuse `LockFactoryInterface` (contracts) with deterministic acquire/release semantics.
  - [ ] Preserve existing scheduler public API and CLI behavior (`schedule:run`) as backward compatible.
- [ ] Non-goals / out of scope
  - [ ] Not implementing distributed cron or external coordinator; lock is the coordinator.
  - [ ] Not guaranteeing exactly-once execution (still at-least-once; idempotency handled by jobs/commands).
  - [ ] No new metric label keys (leader id, instance id, schedule id are forbidden as labels).
- [ ] Usually present when enabled in presets/bundles:
  - [ ] lock implementation is available when `platform/lock` or `integrations/lock-redis` enabled
  - [ ] CLI commands discovered by `platform/cli` via `cli.command` tag (reverse dep)
- [ ] Discovery / wiring is via tags:
  - [ ] `cli.command`
- [ ] In a cluster, only the elected leader executes due schedules, and non-leaders exit deterministically without side effects.
- [ ] When two scheduler processes run `schedule:run` concurrently, then exactly one acquires the leader lock and executes tasks; the other reports “not leader” outcome deterministically.

---

### 6.450.0 integrations/view-twig (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.450.0"
owner_path: "framework/packages/integrations/view-twig/"

package_id: "integrations/view-twig"
composer: "coretsia/integrations-view-twig"
kind: runtime
module_id: "integrations.view-twig"

goal: "Коли `integrations.view-twig` увімкнено, рендеринг працює через Twig з тим самим deterministic locator policy, без витоку vars у логи і з allowlist observability."
provides:
- "Twig renderer integration implementing `platform/view` `RendererInterface` via DI override"
- "Deterministic template resolution via `platform/view` `TemplateLocator` policy bridge"
- "Policy-compliant observability + strict redaction (no vars/HTML; no template paths)"

tags_introduced: []
config_roots_introduced: ["view_twig"]
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/config-roots.md"                 # register `view_twig` / `view_blade` roots
- "docs/ssot/observability.md"                # label allowlist: outcome only for view.render metrics
- "docs/ssot/context-store.md"                # correlation id reads only; no unsafe keys
- "docs/ssot/modules-and-manifests.md"        # conflict expressed via module metadata, resolved by ModulePlan
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - `framework/packages/platform/view/` (package) — must exist; provides `RendererInterface` + `TemplateLocator` policy
  - `Coretsia\View\View\RendererInterface` (FQCN) — DI binding target
  - `TemplateLocator` policy (themes/areas/overrides) — canonical resolution must exist in `platform/view`

- Required config roots/keys:
  - N/A (introduces `view_twig.*`; optional runtime cache under `skeleton/var/cache/<appId>/...`)

- Required tags:
  - N/A (binding override via ServiceProvider)

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — context reads
  - `Psr\Log\LoggerInterface` — logging (no vars/HTML)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional) — noop-safe
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional) — noop-safe

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `platform/view`

Forbidden:

- `platform/http`
- `platform/cli`
- `integrations/*` (крім vendor twig)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - N/A
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - reads/writes: none (optional twig cache runtime-only under `skeleton/var/cache/<appId>/view-twig/*` if enabled)

### Conflict policy (MUST) — ModulePlan-owned, not integration-owned

- This epic MUST NOT implement “engine conflict detection” by inspecting other integrations’ config roots.
- Conflicts MUST be expressed via module conflict metadata (as consumed by Kernel ModulePlan),
  so that enabling two engines fails deterministically with `CORETSIA_MODULE_CONFLICT` (kernel policy).
- Any package-specific `*_CONFLICT` runtime exception becomes OPTIONAL and MUST NOT be the primary mechanism.

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/view-twig/src/Module/ViewTwigModule.php` — runtime module
- [ ] `framework/packages/integrations/view-twig/src/Provider/ViewTwigServiceProvider.php` — DI override wiring
- [ ] `framework/packages/integrations/view-twig/src/Provider/ViewTwigServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/view-twig/config/view_twig.php` — config subtree (no repeated root)
- [ ] `framework/packages/integrations/view-twig/config/rules.php` — config validation rules
- [ ] `framework/packages/integrations/view-twig/README.md` — docs (incl. Observability / Errors / Security-Redaction)
- [ ] `framework/packages/integrations/view-twig/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract proof (noop-safe)

- [ ] `framework/packages/integrations/view-twig/src/View/TwigTemplateRenderer.php` — `RendererInterface` impl, emits spans/metrics, never logs vars
- [ ] `framework/packages/integrations/view-twig/src/View/TwigEnvironmentFactory.php` — builds `Twig\Environment` deterministically (options, autoescape)
- [ ] `framework/packages/integrations/view-twig/src/View/TwigLoader.php` — bridges `TemplateLocator` → twig loader (no raw paths in errors/logs)
- [ ] `framework/packages/integrations/view-twig/src/Exception/ViewTwigException.php` — deterministic codes:
  - `CORETSIA_VIEW_TWIG_INIT_FAILED`
  - `CORETSIA_VIEW_TWIG_RENDER_FAILED`
  - `CORETSIA_VIEW_TWIG_CONFLICT`
- [ ] `docs/architecture/view-twig.md` — override mechanics, escaping, config examples, conflict rules

#### Modifies

N/A

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/view-twig/composer.json`
- [ ] `framework/packages/integrations/view-twig/src/Module/ViewTwigModule.php`
- [ ] `framework/packages/integrations/view-twig/src/Provider/ViewTwigServiceProvider.php`
- [ ] `framework/packages/integrations/view-twig/config/view_twig.php`
- [ ] `framework/packages/integrations/view-twig/config/rules.php`
- [ ] `framework/packages/integrations/view-twig/README.md`
- [ ] `framework/packages/integrations/view-twig/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/view-twig/config/view_twig.php`
- [ ] Keys (dot):
  - [ ] `view_twig.enabled` = false
  - [ ] `view_twig.autoescape` = 'html'
  - [ ] `view_twig.strict_variables` = false
  - [ ] `view_twig.cache.enabled` = false
  - [ ] `view_twig.cache.dir` = 'skeleton/var/cache/<appId>/view-twig'  # runtime-only
  - [ ] `view_twig.cache.subdir`  = 'view-twig'   # suffix only; base cache dir is owned by runtime (no '<appId>' placeholder in config)
- [ ] Rules:
  - [ ] `framework/packages/integrations/view-twig/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\View\View\RendererInterface::class` → `TwigTemplateRenderer`
  - [ ] (optional) registers: `Twig\Environment::class` via `TwigEnvironmentFactory`
- [ ] Conflict policy:
  - [ ] увімкнено має бути максимум 1 view engine integration; конфлікт → deterministic error `CORETSIA_VIEW_TWIG_CONFLICT`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none (default); if cache enabled → `skeleton/var/cache/<appId>/view-twig/*` (runtime-only; not fingerprint)
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] if Twig env caches mutable state → implement `ResetInterface` + tag `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `view.render` (attrs: `template_hash`, `area`, `theme`, `outcome`) — template id only as hash/attr (never label)
- [ ] Metrics:
  - [ ] `view.render_total` (labels: `outcome`)
  - [ ] `view.render_duration_ms` (labels: `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] engine kind → span attr, НЕ label
- [ ] Logs:
  - [ ] errors only; no vars; no rendered HTML
- [ ] Policy note:
  - [ ] engine kind → span attr, НЕ label

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/integrations/view-twig/src/Exception/ViewTwigException.php` — deterministic error codes (listed above)
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] template vars
  - [ ] rendered HTML
  - [ ] raw template file paths
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` — N/A (no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` — only if reset-tag used
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  - evidence (planned): `framework/packages/integrations/view-twig/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  - evidence (planned): `framework/packages/integrations/view-twig/tests/Unit/TwigRendererDoesNotLogVariablesTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/integrations/view-twig/tests/Unit/TwigRendererDoesNotLogVariablesTest.php`
  - [ ] `framework/packages/integrations/view-twig/tests/Unit/TwigUsesTemplateLocatorDeterministicallyTest.php`
- Contract:
  - [ ] `framework/packages/integrations/view-twig/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/view-twig/tests/Integration/RenderThroughThemesAndLayoutsTest.php`
  - [ ] `framework/packages/integrations/view-twig/tests/Integration/DeterminismSameInputsSameBytesTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] same inputs → same output bytes
- [ ] Docs updated:
  - [ ] `framework/packages/integrations/view-twig/README.md`
  - [ ] `docs/architecture/view-twig.md`
- [ ] What problem this epic solves
  - [ ] Дати **Twig renderer** як integration, який імплементує `platform/view` `RendererInterface` і **підміняє** дефолтний `PhpTemplateRenderer` через DI binding (без змін у `platform/view`).
  - [ ] Гарантувати **deterministic template resolution** через `platform/view` `TemplateLocator`/policy (themes/areas/overrides) і safe escaping policy (autoescape=html + заборона raw vars logging).
  - [ ] Забезпечити observability (noop-safe): span/metrics/logs відповідно до allowlist (тільки `outcome`, `operation/driver` як labels; без template/vars як labels).
- [ ] Non-goals / out of scope
  - [ ] Не додаємо власний “view registry” і не робимо мульти-engine arbitration. (Увімкнено має бути максимум 1 engine інтеграція одночасно; виявлення конфлікту — deterministic error.)
  - [ ] Не додаємо HTTP endpoints/middleware.
  - [ ] Не логимо template vars / rendered HTML.
  - [ ] Не гарантуємо детермінізм внутрішнього twig cache; cache — runtime-only (якщо вмикається) і не входить у fingerprint.
- [ ] Runtime expectation (policy, NOT deps):
  - [ ] `platform.view` is enabled and provides:
    - [ ] `TemplateLocator` policy (themes/areas/overrides)
- [ ] Коли модуль `integrations.view-twig` увімкнено, рендеринг працює через Twig з тим самим deterministic locator policy, без витоку vars у логи і з observability за allowlist.
- [ ] When a template is rendered twice with same inputs + same theme/area/layout, then output bytes are identical and logs contain no variables.

---

### 6.460.0 integrations/view-blade (SHOULD) [IMPL]

---
type: package
phase: 6+
epic_id: "6.460.0"
owner_path: "framework/packages/integrations/view-blade/"

package_id: "integrations/view-blade"
composer: "coretsia/integrations-view-blade"
kind: runtime
module_id: "integrations.view-blade"

goal: "Коли `integrations.view-blade` увімкнено, Blade рендерить шаблони з тим самим deterministic locator policy, без витоку vars/HTML і з allowlist observability."
provides:
- "Blade renderer integration implementing `platform/view` `RendererInterface` via DI override"
- "Deterministic template resolution via `platform/view` locator policy bridge"
- "Strict redaction: no vars/HTML/path leaks; allowlist observability only"

tags_introduced: []
config_roots_introduced: ["view_blade"]
artifacts_introduced: []

adr: none
ssot_refs:
- "docs/ssot/config-roots.md"                 # register `view_twig` / `view_blade` roots
- "docs/ssot/observability.md"                # label allowlist: outcome only for view.render metrics
- "docs/ssot/context-store.md"                # correlation id reads only; no unsafe keys
- "docs/ssot/modules-and-manifests.md"        # conflict expressed via module metadata, resolved by ModulePlan
---

### Dependencies (MUST)

#### Preconditions (MUST)

> Everything that MUST already exist (files/keys/tags/contracts/artifacts).

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - `framework/packages/platform/view/` — must exist; provides `RendererInterface` + `TemplateLocator` policy
  - `Coretsia\View\View\RendererInterface` (FQCN) — DI binding target

- Required config roots/keys:
  - N/A (introduces `view_blade.*`; optional runtime cache under `skeleton/var/cache/<appId>/...`)

- Required tags:
  - N/A (binding override via ServiceProvider)

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — context reads
  - `Psr\Log\LoggerInterface` — logging (no vars/HTML)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `platform/view`

Forbidden:

- `platform/http`
- `platform/cli`
- `integrations/*` (крім vendor blade)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional)

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - N/A
- Kernel hooks/tags:
  - N/A
- Artifacts:
  - N/A (optional blade cache runtime-only)

### Conflict policy (MUST) — ModulePlan-owned, not integration-owned

- This epic MUST NOT implement “engine conflict detection” by inspecting other integrations’ config roots.
- Conflicts MUST be expressed via module conflict metadata (as consumed by Kernel ModulePlan),
  so that enabling two engines fails deterministically with `CORETSIA_MODULE_CONFLICT` (kernel policy).
- Any package-specific `*_CONFLICT` runtime exception becomes OPTIONAL and MUST NOT be the primary mechanism.

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/integrations/view-blade/src/Module/ViewBladeModule.php` — runtime module
- [ ] `framework/packages/integrations/view-blade/src/Provider/ViewBladeServiceProvider.php` — DI override wiring
- [ ] `framework/packages/integrations/view-blade/src/Provider/ViewBladeServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/integrations/view-blade/config/view_blade.php` — config subtree (no repeated root)
- [ ] `framework/packages/integrations/view-blade/config/rules.php` — config validation rules
- [ ] `framework/packages/integrations/view-blade/README.md` — docs (incl. Observability / Errors / Security-Redaction)
- [ ] `framework/packages/integrations/view-blade/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — contract proof (noop-safe)

- [ ] `framework/packages/integrations/view-blade/src/View/BladeTemplateRenderer.php` — `RendererInterface` impl, safe logging
- [ ] `framework/packages/integrations/view-blade/src/View/BladeEnvironmentFactory.php` — configures blade engine deterministically
- [ ] `framework/packages/integrations/view-blade/src/View/BladeLocatorAdapter.php` — bridges `TemplateLocator` → blade file resolution
- [ ] `framework/packages/integrations/view-blade/src/Exception/ViewBladeException.php` — deterministic codes:
  - `CORETSIA_VIEW_BLADE_INIT_FAILED`
  - `CORETSIA_VIEW_BLADE_RENDER_FAILED`
  - `CORETSIA_VIEW_BLADE_CONFLICT`
- [ ] `docs/architecture/view-blade.md` — how it overrides renderer, config, conflict rules

#### Modifies

N/A (platform/view not modified; override via DI binding)

#### Package skeleton (if type=package)

- [ ] `framework/packages/integrations/view-blade/composer.json`
- [ ] `framework/packages/integrations/view-blade/src/Module/ViewBladeModule.php`
- [ ] `framework/packages/integrations/view-blade/src/Provider/ViewBladeServiceProvider.php`
- [ ] `framework/packages/integrations/view-blade/config/view_blade.php`
- [ ] `framework/packages/integrations/view-blade/config/rules.php`
- [ ] `framework/packages/integrations/view-blade/README.md`
- [ ] `framework/packages/integrations/view-blade/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/integrations/view-blade/config/view_blade.php`
- [ ] Keys (dot):
  - [ ] `view_blade.enabled` = false
  - [ ] `view_blade.cache.enabled` = false
  - [ ] `view_blade.cache.dir` = 'skeleton/var/cache/<appId>/view-blade'  # runtime-only
  - [ ] `view_blade.cache.subdir`  = 'view-blade' # suffix only; base cache dir is owned by runtime (no '<appId>' placeholder in config)
  - [ ] `view_blade.strict_variables` = false
- [ ] Rules:
  - [ ] `framework/packages/integrations/view-blade/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\View\View\RendererInterface::class` → `BladeTemplateRenderer`
- [ ] Conflict policy:
  - [ ] максимум 1 view engine integration одночасно; конфлікт → deterministic error `CORETSIA_VIEW_BLADE_CONFLICT`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] none (default); if cache enabled → `skeleton/var/cache/<appId>/view-blade/*` (runtime-only; not fingerprint)
- [ ] Reads:
  - [ ] none

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] if Blade env caches mutable state → implement `ResetInterface` + tag `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `view.render` (attrs: `template_hash`, `area`, `theme`, `outcome`) — template id only as hash/attr (never label)
- [ ] Metrics:
  - [ ] `view.render_total` (labels: `outcome`)
  - [ ] `view.render_duration_ms` (labels: `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] engine kind → span attr, НЕ label
- [ ] Logs:
  - [ ] errors only; no vars; no rendered HTML
- [ ] Policy note:
  - [ ] engine kind → span attr, НЕ label

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/integrations/view-blade/src/Exception/ViewBladeException.php` — deterministic error codes (listed above)
- [ ] Mapping:
  - [ ] reuse existing mapper (no dupes)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] template vars
  - [ ] rendered HTML
  - [ ] raw template file paths
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` only

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php` — N/A (no context writes)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php` — only if reset-tag used
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  - evidence (planned): `framework/packages/integrations/view-blade/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  - evidence (planned): `framework/packages/integrations/view-blade/tests/Unit/BladeRendererDoesNotLogVariablesTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] FakeTracer / FakeMetrics / FakeLogger capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/integrations/view-blade/tests/Unit/BladeRendererDoesNotLogVariablesTest.php`
- Contract:
  - [ ] `framework/packages/integrations/view-blade/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [ ] `framework/packages/integrations/view-blade/tests/Integration/DeterminismSameInputsSameBytesTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] same inputs → same output bytes
- [ ] Docs updated:
  - [ ] `framework/packages/integrations/view-blade/README.md`
  - [ ] `docs/architecture/view-blade.md`
- [ ] What problem this epic solves
  - [ ] Дати Blade renderer як integration, який імплементує `platform/view` `RendererInterface` і підміняє дефолтний renderer через DI binding.
  - [ ] Забезпечити deterministic template resolution через `platform/view` policy (themes/areas/overrides) .
  - [ ] Забезпечити strict redaction: не логити vars/HTML, observability лише allowlist.
- [ ] Non-goals / out of scope
  - [ ] Не додаємо мульти-engine arbitration.
  - [ ] Не додаємо HTTP endpoints/middleware.
  - [ ] Компіляція шаблонів (якщо є у Blade) — runtime-only, під `skeleton/var/cache/<appId>/...` і не входить у fingerprint.
- [ ] Коли `integrations.view-blade` увімкнено, Blade рендерить шаблони з тим самим deterministic locator policy, без витоку vars/HTML і з allowlist observability.
- [ ] When a template is rendered twice with same inputs, then output bytes are identical and no variables are logged.
- [ ] Runtime expectation (policy, NOT deps):
  - [ ] `platform.view` is enabled; TemplateLocator policy is canonical

---

### 6.470.0 Contracts: AI (LLM gateway + tools + embeddings ports) (MUST) [CONTRACTS]

---
type: package
phase: 6+
epic_id: "6.470.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Визначити vendor-agnostic AI ports для LLM gateway, tools та embeddings так, щоб runtime реалізації були замінні і policy-compliant (no prompt leaks у logs/metrics)."
provides:
- "LLM gateway порт (chat/completions) з request/response VO"
- "Tool contracts (definition + invocation result envelope)"
- "Embeddings port (text->vector) з deterministic representation policy для output"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- docs/ssot/observability-and-errors.md
- docs/ssot/metrics-policy.md
- docs/ssot/redaction.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.90.0 — observability ports exist (Tracer/Meter/ErrorDescriptor)
  - 6.180.0 — cancellation tokens exist (optional interop)

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/README.md` — namespace registry must be updated

- Required config roots/keys:
  - N/A (contracts-only)

- Required tags:
  - N/A (contracts-only)

- Required contracts / ports:
  - N/A

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (contracts-only)

Forbidden:

- `platform/*`
- `integrations/*`

### Entry points / integration points (MUST)

N/A

### Json-like + float-free law (add, cemented alignment)

- Усі structured поля в AI contracts (tool args/results, extensions, metadata) **MUST** бути json-like:
  - scalars: `null|bool|int|string` (float/NaN/INF forbidden)
  - arrays: list або map; map keys тільки `string`
  - `[]` в serialized form трактувати як list (consumer interprets by schema)
- Якщо потрібно передати decimal/float-like число — **MUST** бути `string` (decimal-string).

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/AI/Llm/LlmGatewayInterface.php`
- [ ] `framework/packages/core/contracts/src/AI/Llm/LlmRequest.php`
  - `LlmRequest`, `LlmResponse`, `ToolCall`, `ToolResult`, `EmbeddingVector`, `AiException`
    MUST NOT розкривати raw prompt/tool args/model output/vectors через:
    - exception message
    - `__toString()` / debug string
    - public “dump” helpers (якщо такі з’являться)

- [ ] `framework/packages/core/contracts/src/AI/Llm/LlmResponse.php`

- [ ] `framework/packages/core/contracts/src/AI/Tools/ToolDefinition.php`
- [ ] `framework/packages/core/contracts/src/AI/Tools/ToolInterface.php`
- [ ] `framework/packages/core/contracts/src/AI/Tools/ToolCall.php`
- [ ] `framework/packages/core/contracts/src/AI/Tools/ToolResult.php`

- [ ] `framework/packages/core/contracts/src/AI/Embeddings/EmbeddingProviderInterface.php`
- [ ] `framework/packages/core/contracts/src/AI/Embeddings/EmbeddingVector.php`  # MUST be float-free in public serialization; e.g. decimal-string list
  - `EmbeddingVector` public serialization MUST бути float-free:
    - list<decimal-string> + (optional) `dim:int` (або dim виводиться з length)
    - `__toString()` → only `dim`, `hash`, `len`

- [ ] `framework/packages/core/contracts/src/AI/AiException.php` — deterministic error codes, message redaction-safe

- [ ] `framework/packages/core/contracts/src/AI/ErrorCodes.php` — constants (closed set):
  - [ ] `CORETSIA_AI_GATEWAY_FAILED`
  - [ ] `CORETSIA_AI_REQUEST_INVALID`
  - [ ] `CORETSIA_AI_TOOL_FAILED`
  - [ ] `CORETSIA_AI_EMBEDDINGS_FAILED`

- [ ] `framework/packages/core/contracts/tests/Contract/EmbeddingVectorIsFloatFreeContractTest.php`
  - asserts: embedding vector API exposes no float in public shapes/serialization helpers.

#### Modifies

- [ ] `framework/packages/core/contracts/README.md` — register AI namespace + redaction rules (no raw prompts)

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE)

- [ ] `framework/packages/core/contracts/tests/Contract/AiContractsDoNotDependOnPlatformTest.php`
  (fails if any `platform/*` types appear in `src/AI/**` signatures or dependencies)
- [ ] `framework/packages/core/contracts/tests/Contract/AiContractsNoRawPromptToStringTest.php`
  (already listed; MUST assert: `__toString()`/exception messages/redaction-safe debug strings never include raw prompt/tool args/model output)

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/core/contracts/tests/Contract/AiContractsDoNotDependOnPlatformTest.php`
  - [ ] `framework/packages/core/contracts/tests/Contract/AiContractsNoRawPromptToStringTest.php`


### DoD (MUST)

- [ ] Contracts не протікають prompt/response payloads через messages/toString
- [ ] Float-free policy зафіксована (EmbeddingVector)
- [ ] No platform deps: `src/AI/**` has zero references to `platform/*` or `integrations/*`
- [ ] errorCode keys are `CORETSIA_*` only (no numeric codes)
- [ ] `EmbeddingVector` public serialization is float-free (documented in docblock + enforced by test)
- [ ] README updated with:
  - no raw prompt/tool args/output in logs/metrics/errors
  - allowed telemetry: `hash/len`, model id, counts/tokens

---

### 6.471.0 coretsia/ai (LLM gateway runtime + tools discovery + fake drivers) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.471.0"
owner_path: "framework/packages/platform/ai/"

package_id: "platform/ai"
composer: "coretsia/platform-ai"
kind: runtime
module_id: "platform.ai"

goal: "Кожен app може викликати LlmGatewayInterface/EmbeddingProviderInterface через runtime фасад із tool discovery та guardrail hooks, без витоку prompt/PII в observability."
provides:
- "Config-driven driver selection (null/fake/real adapters later)"
- "Tool registry discovery через DI tag `ai.tool`"
- "Fake drivers для deterministic tests (CI safe)"

tags_introduced:
- "ai.tool"

config_roots_introduced:
- "ai"

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/tags.md
- docs/ssot/config-roots.md
- docs/ssot/observability-and-errors.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 6.470.0 — AI contracts exist
  - 4.20.0 — http-client exists (OPTIONAL; only if you add an HTTP adapter here)
  - 1.10.0 — tags registry exists

- Required tags:
  - `ai.tool` — MUST be registered in `docs/ssot/tags.md` with owner `platform/ai`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `integrations/*` (vendor drivers out-of-scope here by default)

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/ai/composer.json`
- [ ] `framework/packages/platform/ai/src/Module/AiModule.php`
- [ ] `framework/packages/platform/ai/src/Provider/AiServiceProvider.php`
- [ ] `framework/packages/platform/ai/src/Provider/AiServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/ai/src/Provider/Tags.php` — owns `ai.tool`
- [ ] `framework/packages/platform/ai/src/Tools/ToolRegistry.php`
- [ ] `framework/packages/platform/ai/src/Llm/ConfigDrivenLlmGateway.php` (selects driver)
- [ ] `framework/packages/platform/ai/src/Llm/NullLlmGateway.php` (policy: deterministic error/empty)
- [ ] `framework/packages/platform/ai/src/Llm/FakeLlmGateway.php` (test-only deterministic)
- [ ] `framework/packages/platform/ai/src/Embeddings/NullEmbeddingProvider.php`
- [ ] `framework/packages/platform/ai/src/Embeddings/FakeEmbeddingProvider.php`
- [ ] `framework/packages/platform/ai/config/ai.php`
- [ ] `framework/packages/platform/ai/config/rules.php`
- [ ] `framework/packages/platform/ai/README.md`
- [ ] `framework/packages/platform/ai/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Modifies

- [ ] `docs/ssot/tags.md` — add tag row for `ai.tool` (owner `platform/ai`)
- [ ] `docs/ssot/config-roots.md` — add root row for `ai`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/ai/composer.json` MUST include:
  - `extra.coretsia.providers`
  - `extra.coretsia.defaultsConfigPath` = `config/ai.php`
- [ ] `framework/packages/platform/ai/src/Module/AiModule.php`
- [ ] `framework/packages/platform/ai/src/Provider/AiServiceProvider.php`
- [ ] `framework/packages/platform/ai/config/ai.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/platform/ai/config/rules.php`
- [ ] `framework/packages/platform/ai/README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `framework/packages/platform/ai/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/ai/config/ai.php`
- [ ] Keys (dot):
  - [ ] `ai.enabled` = false
  - [ ] `ai.llm.driver` = "null"                    # enum: null|fake
  - [ ] `ai.llm.default_model` = null               # string|null (safe id only, not secret)
  - [ ] `ai.embeddings.driver` = "null"             # enum: null|fake
  - [ ] `ai.embeddings.default_model` = null        # string|null
  - [ ] `ai.tools.enabled` = true
- [ ] Rules:
  - [ ] `framework/packages/platform/ai/config/rules.php` enforces:
    - closed shape (no unknown keys)
    - enum allowlists for `*.driver`
    - booleans for toggles
    - `default_model` is `null|string` (no secrets; docs must say “model id only”)

### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/ai/src/Provider/Tags.php`
  - [ ] constants:
    - [ ] `Tags::AI_TOOL = 'ai.tool'`
- [ ] Tag `ai.tool` meta schema (closed allowlist; validated by registry):
  - `id` is required for diagnostics/selection only; it MUST NOT be used for ordering.
  - `name` (string, optional; for UI only)
  - `timeout_ms` (int|null, optional; >= 1)
  - `stage` (string, optional; default "default")     # low-cardinality bucket, NOT a path
  - reject unknown meta keys deterministically
- [ ] ServiceProvider wiring evidence:
  - [ ] binds:
    - `Coretsia\Contracts\AI\Llm\LlmGatewayInterface` → `ConfigDrivenLlmGateway`
    - `Coretsia\Contracts\AI\Embeddings\EmbeddingProviderInterface` → config-driven provider
  - [ ] registers: `Coretsia\Platform\Ai\Tools\ToolRegistry`
    - deterministic order: `ToolRegistry` preserves the exact order returned by `TagRegistry::all(Tags::AI_TOOL)`; meta `id` is **NOT** an ordering key.
    - validates tag meta schema; throws typed exception with `CORETSIA_AI_TOOL_FAILED` (no args leak)
  - [ ] registers null/fake drivers:
    - `NullLlmGateway`, `FakeLlmGateway`
    - `NullEmbeddingProvider`, `FakeEmbeddingProvider`

- Closed allowlist:
  - required: `id` (string, stable; diagnostics only; NOT used for ordering)
  - optional: `name` (string), `timeout_ms` (int|null), `stage` (string, low-cardinality)
- Unknown meta keys → deterministic fail `CORETSIA_AI_TOOL_FAILED` (redaction-safe)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads (optional; for diagnostics only):
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- [ ] Context writes (safe only):
  - [ ] `ContextKeys::UOW_TYPE` MAY be set **only on a derived/child context** around each operation:
    - `ai.llm_call|ai.embeddings_call|ai.tool_call`
  - [ ] `ContextKeys::UOW_ID` — N/A (do not generate IDs here)
  - [ ] `ContextKeys::CORRELATION_ID` — N/A (outer boundary responsibility)
  - [ ] request-safe keys (`CLIENT_IP,SCHEME,HOST,PATH,USER_AGENT`) — N/A
- [ ] Reset discipline:
  - [ ] Default drivers/registries are stateless → no reset required.
  - [ ] If any in-memory caches are introduced later → MUST implement `ResetInterface` and be tagged `kernel.reset`.

#### Observability (policy-compliant)

- [ ] Spans:
  - `ai.llm.call` (attrs: `driver`, `operation`, `outcome`)
  - `ai.embeddings.call` (attrs: `driver`, `operation`, `outcome`)
  - `ai.tool.call` (attrs: `driver`, `operation`, `outcome`)
  - де `operation` tokens: `llm_call|embeddings_call|tool_call`
- [ ] Metrics:
  - `ai.op_total` (labels: `driver`, `operation`, `outcome`)
  - `ai.op_duration_ms` (labels: `driver`, `operation`, `outcome`)
- [ ] Logs:
  - [ ] MUST NOT include raw prompts/tool args/model outputs/embedded text/vectors
  - [ ] allowed: `hash/len`, model id, counts, outcome

- Заборонено в telemetry keys/attrs: `model`, `tokens_in`, `tokens_out`, `dim`, `count` (як окремі keys).  
  Якщо це критично — кодувати в `operation` як low-cardinality token або не емитити.

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Platform\Ai\Exception\AiException` — errorCode (closed set):
    - `CORETSIA_AI_GATEWAY_FAILED`
    - `CORETSIA_AI_REQUEST_INVALID`
    - `CORETSIA_AI_TOOL_FAILED`
    - `CORETSIA_AI_EMBEDDINGS_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper (preferred); if mapper exists it MUST NOT leak prompt/tool args/output

#### Security / Redaction

- [ ] MUST NOT leak: raw prompts, tool args, LLM outputs
- [ ] Allowed: hash/len, model id, outcome tokens

### Verification (TEST EVIDENCE)

- [ ] Deterministic discovery:
  - [ ] `framework/packages/platform/ai/tests/Integration/ToolRegistryOrderIsDeterministicTest.php` MUST assert:
    - registry output order === TagRegistry order (no re-sort),
    - unknown meta keys cause deterministic failure without leaking tool args.
- [ ] No leak:
  - [ ] `framework/packages/platform/ai/tests/Integration/NoPromptLeakToLogsTest.php` (already listed)
- [ ] Policy compliance:
  - [ ] `framework/packages/platform/ai/tests/Contract/ObservabilityPolicyTest.php`
    (asserts: label allowlist + no raw prompt/tool args/output in telemetry)

### Tests (MUST)

- Integration:
  - [ ] `framework/packages/platform/ai/tests/Integration/NoPromptLeakToLogsTest.php`

### DoD (MUST)

- [ ] Tool discovery preserves TagRegistry order exactly, proven by integration test
- [ ] Null/fake drivers exist so feature “працює” без vendor інтеграцій

---

### 6.472.0 Contracts: AI guardrails (prompt policy + PII redaction hooks) (MUST) [CONTRACTS]

---
type: package
phase: 6+
epic_id: "6.472.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Визначити contracts для guardrails pipeline: prompt policy, allow/deny lists та PII redaction hooks як ports без runtime залежностей."
provides:
- "PromptPolicyInterface (allow/deny, rule outcomes)"
- "RedactionHookInterface (PII scrubber boundary)"
- "GuardrailViolation envelope (redaction-safe diagnostics)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- docs/ssot/redaction.md
- docs/ssot/observability-and-errors.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 6.470.0 — базові AI contracts (request/response envelopes) існують

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/README.md` — guardrails namespace registry update

- Required config roots/keys:
  - N/A (contracts-only)

- Required tags:
  - N/A (contracts-only)

- Required contracts / ports:
  - `Coretsia\Contracts\AI\Llm\LlmRequest` / `LlmResponse` — guardrails operate on these envelopes
  - `Coretsia\Contracts\AI\Tools\ToolCall` / `ToolResult` — if hooks cover tool args/results

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (contracts-only)

Forbidden:

- `platform/*`
- `integrations/*`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/AI/Guardrails/PromptPolicyInterface.php`
- [ ] `framework/packages/core/contracts/src/AI/Guardrails/RedactionHookInterface.php`
- [ ] `framework/packages/core/contracts/src/AI/Guardrails/GuardrailDecision.php`
  - MUST містити лише:
    - rule ids, fixed tokens, counts
    - `hash/len` (для прив’язки до даних без витоку)
  - MUST NOT store raw prompt/tool args/model output/PII fragments.
- [ ] `framework/packages/core/contracts/src/AI/Guardrails/GuardrailViolation.php`
  - MUST містити лише:
    - rule ids, fixed tokens, counts
    - `hash/len` (для прив’язки до даних без витоку)
  - MUST NOT store raw prompt/tool args/model output/PII fragments.

- [ ] `framework/packages/core/contracts/src/AI/Guardrails/ErrorCodes.php` — constants:
  - [ ] `CORETSIA_AI_GUARDRAILS_DENIED`
  - [ ] `CORETSIA_AI_GUARDRAILS_REDACTION_FAILED`

- [ ] `framework/packages/core/contracts/tests/Contract/GuardrailsViolationIsRedactionSafeContractTest.php`
  - asserts: no raw strings from prompt/tool/response can appear in violation/decision serialization/toString/message.

#### Modifies

- [ ] `framework/packages/core/contracts/README.md` — register guardrails namespace

### Cross-cutting

N/A

### Verification (TEST EVIDENCE)

- [ ] `framework/packages/core/contracts/tests/Contract/GuardrailsContractsDoNotDependOnPlatformTest.php`
  (fails if `platform/*` types appear in `src/AI/Guardrails/**`)
- [ ] `framework/packages/core/contracts/tests/Contract/GuardrailsContractsNoRawDataTest.php`
  (already listed; MUST assert: violations/decisions never store raw prompt/PII; only hashes/len + safe ids)

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/core/contracts/tests/Contract/GuardrailsContractsNoRawDataTest.php`

### DoD

- [ ] Guardrails contracts contain only redaction-safe diagnostics (no raw prompt/PII)
- [ ] errorCode identifiers are `CORETSIA_*` only
- [ ] No platform/integrations deps

---

### 6.473.0 coretsia/ai-guardrails (policy engine + PII hooks + allow/deny lists) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.473.0"
owner_path: "framework/packages/platform/ai-guardrails/"

package_id: "platform/ai-guardrails"
composer: "coretsia/platform-ai-guardrails"
kind: runtime
module_id: "platform.ai-guardrails"

goal: "Перед LLM/embeddings викликом застосувати guardrails policy (allow/deny + PII redaction hooks) і гарантувати, що observability не містить raw prompt/PII."
provides:
- "GuardrailsPipeline (pre-request + post-response stages)"
- "Config-based allow/deny rules + hook discovery"
- "Reference PII redaction hook (regex-based baseline) + test harness"

tags_introduced:
- "ai.guardrail"
- "ai.redaction_hook"

config_roots_introduced:
- "ai_guardrails"

artifacts_introduced: []

adr: none
ssot_refs:
- docs/ssot/tags.md
- docs/ssot/config-roots.md
- docs/ssot/redaction.md
- docs/ssot/observability-and-errors.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 6.472.0 — guardrails contracts exist
  - 6.471.0 — `LlmGatewayInterface` / `EmbeddingProviderInterface` are bound in container (runtime expectation)

- Required config roots/keys:
  - `ai_guardrails.*` — introduced & owned by this epic

- Required tags:
  - `ai.guardrail` — MUST be registered in `docs/ssot/tags.md` (owner `platform/ai-guardrails`)
  - `ai.redaction_hook` — MUST be registered in `docs/ssot/tags.md` (owner `platform/ai-guardrails`)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/ai`            # avoid cycles; integrate by decorating contracts interfaces
- `platform/http`
- `integrations/*`

### Entry points / integration points (MUST)

- Uses ports:
  - `Coretsia\Contracts\AI\Llm\LlmGatewayInterface` (wrap/decorate)
  - `Coretsia\Contracts\AI\Embeddings\EmbeddingProviderInterface` (wrap/decorate)

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/ai-guardrails/composer.json`
- [ ] `framework/packages/platform/ai-guardrails/src/Module/AiGuardrailsModule.php`
- [ ] `framework/packages/platform/ai-guardrails/src/Provider/AiGuardrailsServiceProvider.php`
- [ ] `framework/packages/platform/ai-guardrails/src/Provider/AiGuardrailsServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/ai-guardrails/src/Provider/Tags.php`
- [ ] `framework/packages/platform/ai-guardrails/src/Guardrails/GuardrailsPipeline.php`
  - builds lists in the exact order returned by TagRegistry:
    - `TagRegistry::all(Tags::AI_GUARDRAIL)` order is final
    - `TagRegistry::all(Tags::AI_REDACTION_HOOK)` order is final
    - pipeline MUST NOT re-sort/dedupe
- [ ] `framework/packages/platform/ai-guardrails/src/Guardrails/AllowDenyPolicy.php`
- [ ] `framework/packages/platform/ai-guardrails/src/Redaction/RegexPiiRedactionHook.php`
- [ ] `framework/packages/platform/ai-guardrails/config/ai_guardrails.php`
- [ ] `framework/packages/platform/ai-guardrails/config/rules.php`
- [ ] `framework/packages/platform/ai-guardrails/README.md`
- [ ] `framework/packages/platform/ai-guardrails/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Modifies

- [ ] `docs/ssot/tags.md` — add tag rows for `ai.guardrail`, `ai.redaction_hook` (owner `platform/ai-guardrails`)
- [ ] `docs/ssot/config-roots.md` — add root row for `ai_guardrails`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/ai-guardrails/composer.json` MUST include:
  - `extra.coretsia.providers`
  - `extra.coretsia.defaultsConfigPath` = `config/ai_guardrails.php`
- [ ] `framework/packages/platform/ai-guardrails/src/Module/AiGuardrailsModule.php`
- [ ] `framework/packages/platform/ai-guardrails/src/Provider/AiGuardrailsServiceProvider.php`
- [ ] `framework/packages/platform/ai-guardrails/config/ai_guardrails.php`
- [ ] `framework/packages/platform/ai-guardrails/config/rules.php`
- [ ] `framework/packages/platform/ai-guardrails/README.md`
- [ ] `framework/packages/platform/ai-guardrails/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/ai-guardrails/config/ai_guardrails.php`
- [ ] Keys (dot):
  - [ ] `ai_guardrails.enabled` = false
  - [ ] `ai_guardrails.apply_to.llm` = true
  - [ ] `ai_guardrails.apply_to.embeddings` = true
  - [ ] `ai_guardrails.policy.allow_introspection` = false         # example policy knob (if used)
  - [ ] `ai_guardrails.redaction.enabled` = true
  - [ ] `ai_guardrails.redaction.mode` = "scrub"                   # enum: scrub|deny
  - [ ] `ai_guardrails.allowlist.models` = []                      # list<string> (safe ids)
  - [ ] `ai_guardrails.denylist.models` = []                       # list<string>
- [ ] Rules:
  - [ ] closed shape; enums allowlisted; lists are list<string>; no unknown keys

### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] `framework/packages/platform/ai-guardrails/src/Provider/Tags.php`
  - [ ] constants:
    - [ ] `Tags::AI_GUARDRAIL = 'ai.guardrail'`
    - [ ] `Tags::AI_REDACTION_HOOK = 'ai.redaction_hook'`
- [ ] Tag meta schemas (validated; closed allowlist):
  - `ai.guardrail` meta (closed):
    - `id` (string, required; NOT for ordering)
    - `stage` enum: `pre_request|post_response`
  - `ai.redaction_hook` meta (closed):
    - `id` (string, required; NOT for ordering)
    - `stage` enum: `prompt|tool_args|response|embeddings`
  - Unknown keys → deterministic fail
- [ ] ServiceProvider wiring evidence:
  - [ ] registers `GuardrailsPipeline` (builds deterministic ordered lists from tags:
    - preserves TagRegistry order exactly
  - [ ] decorates (service decoration) contracts interfaces:
    - `Coretsia\Contracts\AI\Llm\LlmGatewayInterface` → `GuardedLlmGatewayDecorator`
    - `Coretsia\Contracts\AI\Embeddings\EmbeddingProviderInterface` → `GuardedEmbeddingProviderDecorator`
  - [ ] provides baseline hook: `RegexPiiRedactionHook` tagged `ai.redaction_hook`

- For `ai.guardrail`: allowlist keys only: `id`, `stage`
- For `ai.redaction_hook`: allowlist keys only: `id`, `stage`
- Explicit: "`priority` is not a meta key."

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads (optional; diagnostics only):
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- [ ] Context writes (safe only):
  - [ ] `ContextKeys::UOW_TYPE` MAY be set **only on a derived/child context** when applying guardrails:
    - `ai.guardrails.pre_request|ai.guardrails.post_response`
  - [ ] `ContextKeys::UOW_ID` — N/A (do not generate IDs here)
  - [ ] `ContextKeys::CORRELATION_ID` — N/A
  - [ ] request-safe keys (`CLIENT_IP,SCHEME,HOST,PATH,USER_AGENT`) — N/A
- [ ] Reset discipline:
  - [ ] Default: N/A (pipeline is deterministic + stateless)
  - [ ] If regex caches or compiled rule caches become mutable singletons → MUST implement `ResetInterface` and be tagged `kernel.reset`.

#### Observability (policy-compliant)

- Якщо є telemetry:
  - metric `ai.guardrails_op_total` labels: `operation`, `outcome`
  - span `ai.guardrails.apply` attrs: `operation`, `outcome`
  - **NO** new keys (тільки allowlist)

- [ ] Spans:
  - [ ] `<span.name>`
- [ ] Metrics:
  - [ ] `<metric_name_total>` (labels: allowlist only)
  - [ ] `<metric_name_duration_ms>` (labels: allowlist only)
- [ ] Logs:
  - [ ] redaction applied; no secrets/PII/raw payloads

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Platform\AiGuardrails\Exception\AiGuardrailsException` — errorCode (closed set):
    - `CORETSIA_AI_GUARDRAILS_DENIED`
    - `CORETSIA_AI_GUARDRAILS_REDACTION_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper; denial should surface as safe “policy denied” without prompt echo

#### Security / Redaction

- [ ] MUST NOT leak: raw prompts, tool args, model outputs, detected PII fragments
- [ ] Allowed: `hash/len`, rule ids, model id, outcome, counts

### Verification (TEST EVIDENCE)

- [ ] Deterministic ordering:
  - [ ] `framework/packages/platform/ai-guardrails/tests/Integration/GuardrailsOrderIsDeterministicTest.php`
- [ ] No leak:
  - [ ] `framework/packages/platform/ai-guardrails/tests/Contract/RedactionDoesNotLeakTest.php` (already listed)
- [ ] Enforcement:
  - [ ] `framework/packages/platform/ai-guardrails/tests/Integration/DeniedPolicyBlocksCallTest.php`
  - [ ] `framework/packages/platform/ai-guardrails/tests/Contract/ObservabilityPolicyTest.php`

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/platform/ai-guardrails/tests/Contract/RedactionDoesNotLeakTest.php`

---

### 6.474.0 Contracts: AI vectorstore (vector DB ports) (MUST) [CONTRACTS]

---
type: package
phase: 6+
epic_id: "6.474.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Визначити ports для vectorstore (upsert/query/delete) і canonical value objects (vector/similarity) без vendor залежностей."
provides:
- "VectorStoreInterface + query envelope"
- "Deterministic result ordering policy (score DESC, id ASC) для stable behavior"
- "No raw vectors in logs/errors (redaction-safe diagnostics)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs:
- docs/ssot/observability-and-errors.md
- docs/ssot/redaction.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/README.md` — vectorstore namespace registry update

- Required config roots/keys:
  - N/A (contracts-only)

- Required tags:
  - N/A (contracts-only)

- Required contracts / ports:
  - `Coretsia\Contracts\AI\Embeddings\EmbeddingVector` — vector representation (float-free serialization policy)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (contracts-only)

Forbidden:

- `platform/*`
- `integrations/*`

### Entry points / integration points (MUST)

N/A

### Score representation (float-free) (cement)

- Будь-який similarity/score у contracts **MUST** бути float-free:
  - Score representation (float-free, single-choice):
    - `scoreMicros: int` where `scoreMicros = round(score * 1_000_000)` at the implementation boundary.
    - Contracts MUST NOT expose float score.
    - Ordering policy (cemented):
      - results sorted by `scoreMicros DESC`,
      - tie-break `id ASC` (`strcmp`).
- Ordering policy (cemented, remains):
  - results sorted by `score DESC` (numeric compare, implementation-defined for chosen representation),
  - tie-break `id ASC (strcmp)`.

- Pgvector adapter MUST map DB similarity to `scoreMicros:int` deterministically (no float leakage).

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/AI/Vectorstore/VectorStoreInterface.php`
- [ ] `framework/packages/core/contracts/src/AI/Vectorstore/VectorRecord.php`
  - MUST NOT leak raw vectors/text/metadata values в `__toString()`/messages.
  - allowed: `hash/len`, ids, counts.
- [ ] `framework/packages/core/contracts/src/AI/Vectorstore/VectorQuery.php`
  - MUST NOT leak raw vectors/text/metadata values в `__toString()`/messages.
  - allowed: `hash/len`, ids, counts.
- [ ] `framework/packages/core/contracts/src/AI/Vectorstore/VectorQueryResult.php`
  - MUST NOT leak raw vectors/text/metadata values в `__toString()`/messages.
  - allowed: `hash/len`, ids, counts.
- [ ] `framework/packages/core/contracts/src/AI/Vectorstore/VectorstoreException.php` — redaction-safe, deterministic errorCode
  - MUST NOT leak raw vectors/text/metadata values в `__toString()`/messages.
  - allowed: `hash/len`, ids, counts.
- [ ] `framework/packages/core/contracts/src/AI/Vectorstore/ErrorCodes.php` — constants:
  - [ ] `CORETSIA_AI_VECTORSTORE_FAILED`
  - [ ] `CORETSIA_AI_VECTORSTORE_REQUEST_INVALID`

- [ ] `framework/packages/core/contracts/tests/Contract/VectorstoreScoreIsFloatFreeContractTest.php`
  - asserts: score type is never float in any public API surface / shape.

#### Modifies

- [ ] `framework/packages/core/contracts/README.md` — register vectorstore namespace

### Verification (TEST EVIDENCE)

- [ ] `framework/packages/core/contracts/tests/Contract/VectorstoreContractsDoNotDependOnPlatformTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/VectorstoreNoRawVectorsLeakTest.php` (already listed)
  (asserts: no raw vectors/text in `__toString()`/exception messages/VO debug output)

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/core/contracts/tests/Contract/VectorstoreNoRawVectorsLeakTest.php`

### DoD

- [ ] Deterministic ordering policy documented in contract docblocks:
  - score DESC, id ASC (stable tie-break)
- [ ] No raw vectors/text ever appear in messages/toString
- [ ] No platform/integrations deps
- [ ] errorCode identifiers are `CORETSIA_*` only

---

### 6.475.0 coretsia/ai-vectorstore (ports wiring + adapters: pgvector + in-memory) (MUST) [IMPL]

---
type: package
phase: 6+
epic_id: "6.475.0"
owner_path: "framework/packages/platform/ai-vectorstore/"

package_id: "platform/ai-vectorstore"
composer: "coretsia/platform-ai-vectorstore"
kind: runtime
module_id: "platform.ai-vectorstore"

goal: "Надати runtime VectorStoreInterface через config-driven adapters (in-memory для тестів + pgvector через platform/database), з deterministic order і redaction-safe observability."
provides:
- "Config-driven adapter selection (memory|pgvector)"
- "InMemoryVectorStore adapter (CI safe)"
- "Pgvector adapter через `platform/database` (PostgreSQL) як реальний vector DB шлях"

tags_introduced: []
config_roots_introduced:
- "ai_vectorstore"

artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/config-roots.md
- docs/ssot/observability-and-errors.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 6.474.0 — vectorstore contracts exist
  - 4.60.0 — platform/database exists (pgvector adapter)
  - 4.73.0 — database-driver-pgsql exists (runtime prereq for pgvector)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `platform/database` (only for pgvector adapter)

Forbidden:

- `integrations/*`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/ai-vectorstore/composer.json`
- [ ] `framework/packages/platform/ai-vectorstore/src/Module/AiVectorstoreModule.php`
- [ ] `framework/packages/platform/ai-vectorstore/src/Provider/AiVectorstoreServiceProvider.php`
- [ ] `framework/packages/platform/ai-vectorstore/src/Provider/AiVectorstoreServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/ai-vectorstore/src/Vectorstore/ConfigDrivenVectorStore.php`
- [ ] `framework/packages/platform/ai-vectorstore/src/Adapter/InMemoryVectorStore.php`
- [ ] `framework/packages/platform/ai-vectorstore/src/Adapter/PgvectorVectorStore.php`
- [ ] `framework/packages/platform/ai-vectorstore/config/ai_vectorstore.php`
- [ ] `framework/packages/platform/ai-vectorstore/config/rules.php`
- [ ] `framework/packages/platform/ai-vectorstore/README.md`
- [ ] `framework/packages/platform/ai-vectorstore/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Modifies

- [ ] `docs/ssot/config-roots.md` — add root row for `ai_vectorstore`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/ai-vectorstore/composer.json` MUST include:
  - `extra.coretsia.providers`
  - `extra.coretsia.defaultsConfigPath` = `config/ai_vectorstore.php`
- [ ] `framework/packages/platform/ai-vectorstore/src/Module/AiVectorstoreModule.php`
- [ ] `framework/packages/platform/ai-vectorstore/src/Provider/AiVectorstoreServiceProvider.php`
- [ ] `framework/packages/platform/ai-vectorstore/config/ai_vectorstore.php`
- [ ] `framework/packages/platform/ai-vectorstore/config/rules.php`
- [ ] `framework/packages/platform/ai-vectorstore/README.md`
- [ ] `framework/packages/platform/ai-vectorstore/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/ai-vectorstore/config/ai_vectorstore.php`  # returns subtree (no repeated root)
- [ ] Keys (dot):
  - [ ] `ai_vectorstore.enabled` = false
  - [ ] `ai_vectorstore.driver` = "memory"                 # enum: memory|pgvector
  - [ ] `ai_vectorstore.pgvector.connection` = "default"   # db connection name (safe id)
  - [ ] `ai_vectorstore.pgvector.table` = "ai_vectors"     # string (safe id)
  - [ ] `ai_vectorstore.pgvector.dim` = 1536               # int >= 1
  - [ ] `ai_vectorstore.pgvector.distance` = "cosine"      # enum: cosine|l2|ip
  - [ ] `ai_vectorstore.query.max_k` = 50                  # int >= 1
- [ ] Rules:
  - [ ] closed shape; enums allowlisted; ints have floors; no unknown keys

### Wiring / DI tags (when applicable)

- Tags introduced:
  - N/A (no discovery tags; selection is config-driven)
- [ ] ServiceProvider wiring evidence:
  - [ ] binds `Coretsia\Contracts\AI\Vectorstore\VectorStoreInterface` → `ConfigDrivenVectorStore`
  - [ ] `ConfigDrivenVectorStore` selects adapter deterministically by `ai_vectorstore.driver`
  - [ ] registers adapters:
    - `InMemoryVectorStore` (default; CI safe)
    - `PgvectorVectorStore` (only used when driver=pgvector; MUST be deterministic SQL)

### Cross-cutting

#### Context & UoW

- [ ] Context reads (optional; diagnostics only):
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- [ ] Context writes (safe only):
  - [ ] `ContextKeys::UOW_TYPE` MAY be set **only on a derived/child context** per operation:
    - `ai.vectorstore.upsert|ai.vectorstore.query|ai.vectorstore.delete`
  - [ ] `ContextKeys::UOW_ID` — N/A (do not generate IDs here)
  - [ ] `ContextKeys::CORRELATION_ID` — N/A
  - [ ] request-safe keys (`CLIENT_IP,SCHEME,HOST,PATH,USER_AGENT`) — N/A
- [ ] Reset discipline:
  - [ ] InMemory adapter MAY hold mutable state → if registered as a singleton service, it MUST implement `ResetInterface` and be tagged `kernel.reset`.
  - [ ] Pgvector adapter should be stateless → no reset required.

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `ai.vectorstore.upsert`
  - [ ] `ai.vectorstore.query`
  - [ ] `ai.vectorstore.delete`
    (attrs only: `driver`, `operation`, `outcome`; **never** vectors/text)
- [ ] Metrics:
  - [ ] `ai.vectorstore_op_total` (labels: `driver`, `operation`, `outcome`)
  - [ ] `ai.vectorstore_op_duration_ms` (labels: `driver`, `operation`, `outcome`)
- [ ] Logs:
  - [ ] MUST NOT include vectors, raw text, metadata values
  - [ ] allowed: ids, `hash/len`, counts, outcome

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Platform\AiVectorstore\Exception\AiVectorstoreException` — errorCode (closed set):
    - `CORETSIA_AI_VECTORSTORE_FAILED`
    - `CORETSIA_AI_VECTORSTORE_REQUEST_INVALID`
    - `CORETSIA_AI_VECTORSTORE_DRIVER_NOT_CONFIGURED`
    - `CORETSIA_AI_VECTORSTORE_PGVECTOR_QUERY_FAILED`
- [ ] Mapping:
  - [ ] reuse existing mapper; messages MUST be redaction-safe (no SQL fragments with values; no vectors)
- Diagnostics MUST NOT include raw SQL with values; additionally MUST NOT include raw DB similarity floats — only `scoreMicros` int (or derived hashes/len).

#### Security / Redaction

- [ ] MUST NOT leak: vectors, raw text, metadata values (only safe ids + hash/len)

### Verification (TEST EVIDENCE)

- [ ] Deterministic ordering:
  - [ ] `framework/packages/platform/ai-vectorstore/tests/Integration/DeterministicQueryOrderTest.php` (already listed)
    (asserts: score DESC, id ASC tie-break; stable for same inputs)
- [ ] No leak:
  - [ ] `framework/packages/platform/ai-vectorstore/tests/Integration/NoRawVectorLeakTest.php` (already listed)
- [ ] Config shape rails:
  - [ ] `framework/packages/platform/ai-vectorstore/tests/Contract/ConfigRulesRejectUnknownKeysTest.php`
- [ ] Observability policy:
  - [ ] `framework/packages/platform/ai-vectorstore/tests/Contract/ObservabilityPolicyTest.php`

### Tests (MUST)
- Unit:
  - [ ] `framework/packages/platform/ai-vectorstore/tests/Unit/InMemoryVectorStoreDeterministicTest.php`
- Contract:
  - [ ] `framework/packages/platform/ai-vectorstore/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/ai-vectorstore/tests/Contract/ObservabilityPolicyTest.php`
- Integration:
  - [ ] `framework/packages/platform/ai-vectorstore/tests/Integration/DeterministicQueryOrderTest.php`
  - [ ] `framework/packages/platform/ai-vectorstore/tests/Integration/NoRawVectorLeakTest.php`

### DoD (MUST)

- [ ] Default adapter memory працює без зовнішніх сервісів
- [ ] Pgvector adapter має deterministic SQL + redaction policy
- [ ] Default `memory` driver працює без зовнішніх сервісів
- [ ] Pgvector adapter **MUST** use stable ORDER BY (semantic):
  - [ ] compute `scoreMicros:int` deterministically,
  - [ ] sort by `scoreMicros DESC, id ASC`.
- [ ] Diagnostics/errors **MUST NOT** include raw SQL fragments with values або raw vectors.
- [ ] No vectors/text leaks in logs/spans/metrics/errors
- [ ] Config root registered in `docs/ssot/config-roots.md`

---

### 6.480.0 Advanced Caching & Preloading Strategy (SHOULD) [IMPL+TOOLING]

---
type: package
phase: 6+
epic_id: "6.480.0"
owner_path: "framework/packages/devtools/preload/"

package_id: "devtools/preload"
composer: "coretsia/devtools-preload"
kind: runtime
module_id: "devtools.preload"

goal: "Розширити deterministic preload tooling так, щоб preload plan міг будуватися не лише статично, а й artifact-aware, з окремими advisory outputs для OPcache/JIT без втрати determinism і без залежності від недетермінованих production traces."
provides:
- "Artifact-aware preload plan optimization on top of the existing deterministic preload generator"
- "Separate deterministic preload plan JSON and optional OPcache/JIT recommendation output"
- "Explain/recommend commands for preload coverage and runtime tuning"

tags_introduced: []
config_roots_introduced:
- "preload"   # extends existing root from 6.240.0
  artifacts_introduced:
- "preload_plan@1"
- "opcache_recommendation@1"

adr: none
ssot_refs:
- "docs/ssot/artifacts.md"
- "docs/ssot/config-roots.md"
- "docs/ssot/observability-and-errors.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

> Everything that MUST already exist (files/keys/tags/contracts/artifacts).

- Epic prerequisites:
  - 6.240.0 — base deterministic preload generator exists
  - 1.330.0 — module/config artifacts exist
  - 1.340.0 — compiled container artifact exists
  - 3.130.0 — routes artifact/runtime exists where route-aware weighting is used

- Required deliverables (exact paths):
  - `framework/packages/devtools/preload/` — existing preload package root
  - `framework/packages/core/kernel/` — source of module/artifact inputs
  - `docs/ssot/artifacts.md` — artifact registry to extend
  - `docs/ops/preload.md` — existing preload ops doc to extend

- Required config roots/keys:
  - `preload.enabled`
  - `preload.output.path`
  - `preload.plan.exclude_paths`

- Required tags:
  - `cli.command` — used, not owned, for explain/recommend commands

- Required contracts / ports:
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
- direct reliance on live production traces as canonical preload input

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

- CLI:
  - `preload:dump` → existing command from 6.240.0
  - `preload:explain` → `devtools/preload` `src/Console/PreloadExplainCommand.php`
  - `opcache:recommend` → `devtools/preload` `src/Console/OpcacheRecommendCommand.php`

- HTTP:
  - N/A

- Kernel hooks/tags:
  - N/A

- Other runtime discovery / integration tags:
  - `cli.command` priority `0` meta `per owner schema`

- Artifacts:
  - reads:
    - `skeleton/var/cache/<appId>/container.php`
    - `skeleton/var/cache/<appId>/config.php`
    - route artifact(s) if available
  - writes:
    - `skeleton/var/cache/<appId>/preload.php`
    - `skeleton/var/cache/<appId>/preload.plan.json`
    - `skeleton/var/cache/<appId>/opcache.recommendation.json`

- Notes (only if applicable):
  - This epic MUST NOT invent any non-deterministic “auto-learn from prod traffic” mode.
  - If external usage evidence is ever imported, it MUST be normalized offline into deterministic input data before planning.

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/devtools/preload/src/Generator/PreloadPlanOptimizer.php` — artifact-aware candidate weighting
- [ ] `framework/packages/devtools/preload/src/Generator/ArtifactWeightedPreloadSource.php` — deterministic extraction from Coretsia artifacts
- [ ] `framework/packages/devtools/preload/src/Console/PreloadExplainCommand.php` — explain chosen/excluded files
- [ ] `framework/packages/devtools/preload/src/Console/OpcacheRecommendCommand.php` — emit advisory recommendation output
- [ ] `framework/packages/devtools/preload/src/Artifacts/PreloadPlanWriter.php` — writes `preload.plan.json`
- [ ] `framework/packages/devtools/preload/src/Artifacts/OpcacheRecommendationWriter.php` — writes advisory recommendation JSON
- [ ] `framework/packages/devtools/preload/src/Opcache/JitPolicy.php` — deterministic advisory model only
- [ ] `framework/packages/devtools/preload/tests/Unit/PreloadPlanOptimizerDeterministicTest.php`
- [ ] `framework/packages/devtools/preload/tests/Integration/PreloadExplainCommandDeterministicOutputTest.php`
- [ ] `framework/packages/devtools/preload/tests/Integration/OpcacheRecommendCommandDeterministicOutputTest.php`
- [ ] `docs/ops/preload-optimization.md` — artifact-aware preload and OPcache/JIT guidance

#### Modifies

- [ ] `framework/packages/devtools/preload/config/preload.php` — extend config subtree
- [ ] `framework/packages/devtools/preload/config/rules.php` — enforce new shape
- [ ] `framework/packages/devtools/preload/README.md` — document explain/recommend flow
- [ ] `docs/ssot/artifacts.md` — register:
  - [ ] `preload_plan@1`
  - [ ] `opcache_recommendation@1`
- [ ] `docs/ops/preload.md` — link advanced strategy
- [ ] `framework/packages/devtools/preload/src/Generator/PreloadPlanBuilder.php` — allow optimizer integration without losing deterministic ordering

#### Package skeleton (if type=package)

- [ ] `framework/packages/devtools/preload/composer.json`
- [ ] `framework/packages/devtools/preload/src/Module/PreloadModule.php`
- [ ] `framework/packages/devtools/preload/src/Provider/PreloadServiceProvider.php`
- [ ] `framework/packages/devtools/preload/config/preload.php`
- [ ] `framework/packages/devtools/preload/config/rules.php`
- [ ] `framework/packages/devtools/preload/README.md`
- [ ] `framework/packages/devtools/preload/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/devtools/preload/config/preload.php`
- [ ] Keys (dot):
  - [ ] `preload.plan.strategy` = `'static'|'artifact_weighted'`
  - [ ] `preload.plan.sources` = `['container','config','routes']`
  - [ ] `preload.plan.write_json` = true
  - [ ] `preload.opcache.recommend.enabled` = false
  - [ ] `preload.opcache.recommend.jit` = `'off'|'tracing'`
  - [ ] `preload.opcache.recommend.emit_ini` = false
- [ ] Rules:
  - [ ] recommendation output is advisory only and MUST NOT mutate runtime environment automatically
  - [ ] planning inputs MUST be deterministic and file-backed

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Devtools\Preload\Console\PreloadExplainCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"preload:explain"}`
  - [ ] registers: `\Coretsia\Devtools\Preload\Console\OpcacheRecommendCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `{"name":"opcache:recommend"}`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `skeleton/var/cache/<appId>/preload.plan.json` (artifact `preload_plan@1`, deterministic bytes)
  - [ ] `skeleton/var/cache/<appId>/opcache.recommendation.json` (artifact `opcache_recommendation@1`, deterministic bytes)
- [ ] Reads:
  - [ ] validates source artifact headers/payloads before planning

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID` (optional logs only)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] caches/planners with mutable state implement `ResetInterface`
  - [ ] stateful services tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `preload.optimize`
  - [ ] `opcache.recommend`
- [ ] Metrics:
  - [ ] `preload.optimize_total` (labels: `outcome`)
  - [ ] `preload.optimize_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] summary only: candidate counts, included/excluded counts, output paths
  - [ ] no file contents, no raw source payloads

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Devtools\Preload\Exception\PreloadPlanSourceException` — errorCode `CORETSIA_PRELOAD_PLAN_SOURCE_INVALID`
  - [ ] `Coretsia\Devtools\Preload\Exception\OpcacheRecommendationException` — errorCode `CORETSIA_OPCACHE_RECOMMENDATION_WRITE_FAILED`
- [ ] Mapping:
  - [ ] deterministic CLI error output only

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] absolute paths outside configured output roots
  - [ ] artifact payload contents
  - [ ] secrets from config/env artifacts
- [ ] Allowed:
  - [ ] normalized relative paths
  - [ ] counts
  - [ ] safe recommendation flags

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If `kernel.reset` used → `framework/packages/devtools/preload/tests/Contract/PreloadPlannerResetSafetyTest.php`
- [ ] If metrics/spans/logs exist → `framework/packages/devtools/preload/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] If redaction exists → `framework/packages/devtools/preload/tests/Integration/PreloadExplainCommandDeterministicOutputTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/MicroApp/`
- [ ] Fake adapters:
  - [ ] FakeTracer
  - [ ] FakeMetrics
  - [ ] FakeLogger

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/devtools/preload/tests/Unit/PreloadPlanOptimizerDeterministicTest.php`
  - [ ] `framework/packages/devtools/preload/tests/Unit/JitPolicyAdvisoryOnlyTest.php`
- Contract:
  - [ ] `framework/packages/devtools/preload/tests/Contract/PreloadPlanIsDeterministicContractTest.php`
  - [ ] `framework/packages/devtools/preload/tests/Contract/PreloadPlannerResetSafetyTest.php`
- Integration:
  - [ ] `framework/packages/devtools/preload/tests/Integration/PreloadExplainCommandDeterministicOutputTest.php`
  - [ ] `framework/packages/devtools/preload/tests/Integration/OpcacheRecommendCommandDeterministicOutputTest.php`
  - [ ] `framework/packages/devtools/preload/tests/Integration/RerunNoDiffForPreloadPlanArtifactTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] preload plan remains rerun-no-diff
  - [ ] recommendation artifacts remain deterministic
  - [ ] no live nondeterministic traces become canonical inputs
- [ ] Docs updated:
  - [ ] `framework/packages/devtools/preload/README.md`
  - [ ] `docs/ops/preload.md`
  - [ ] `docs/ops/preload-optimization.md`

---

### 6.490.0 Production Performance Profiling & Observability (SHOULD) [IMPL+DOC]

---
type: package
phase: 6+
epic_id: "6.490.0"
owner_path: "framework/packages/platform/profiling/"

package_id: "platform/profiling"
composer: "coretsia/platform-profiling"
kind: runtime
module_id: "platform.profiling"

goal: "Дати production-safe profiling layer, який вмикається через config/policy, інтегрується з observability ports та external profilers без жорсткого runtime coupling, а також фіксує рекомендації для FPM/RoadRunner/Swoole tuning."
provides:
- "Profiling runtime with null + xhprof-style + external bridge drivers"
- "UoW-aware profiling hooks and optional HTTP trigger middleware"
- "Normalized deterministic profile summary outputs plus runtime tuning docs"

tags_introduced: []
config_roots_introduced:
- "profiling"
  artifacts_introduced:
- "profile_summary@1"

adr: none
ssot_refs:
- "docs/ssot/config-roots.md"
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/metrics-policy.md"
- "docs/ssot/http-middleware-catalog.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

> Everything that MUST already exist (files/keys/tags/contracts/artifacts).

- Epic prerequisites:
  - 3.20.0 — logging baseline exists
  - 3.30.0 — tracing baseline exists
  - 3.31.0 — metrics baseline exists
  - 3.50.0 — HTTP runtime exists
  - 1.280.0 — KernelRuntime exists
  - 6.240.0 — preload tooling exists (optional complementary tuning path)

- Required deliverables (exact paths):
  - `framework/packages/core/kernel/` — UoW lifecycle hooks source
  - `framework/packages/platform/http/` — HTTP integration point via tags
  - `docs/ssot/config-roots.md` — root registry to extend

- Required config roots/keys:
  - `profiling.*` — this epic introduces and owns
  - `http.*` — only for middleware integration toggle/placement
  - `app.id`

- Required tags:
  - `kernel.hook.before_uow`
  - `kernel.hook.after_uow`
  - `http.middleware.system_pre`
  - `cli.command`

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Psr\Log\LoggerInterface`
  - `Psr\Http\Server\MiddlewareInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- `platform/http` as compile-time dependency
- `platform/cli` as compile-time dependency
- hard dependency on external SaaS/vendor SDKs in the core runtime package
- storing raw profiler payloads as canonical Coretsia artifacts

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Server\MiddlewareInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- CLI:
  - `profile:explain` → `platform/profiling` `src/Console/ProfileExplainCommand.php`
  - `profile:prune` → `platform/profiling` `src/Console/ProfilePruneCommand.php`

- HTTP:
  - middleware slots/tags:
    - `http.middleware.system_pre` priority `520` meta `per owner schema`

- Kernel hooks/tags:
  - `kernel.hook.before_uow` priority `200` meta `per owner schema`
  - `kernel.hook.after_uow` priority `-200` meta `per owner schema`

- Other runtime discovery / integration tags:
  - `cli.command` priority `0` meta `per owner schema`

- Artifacts:
  - writes: `skeleton/var/profiling/<appId>/summary-*.json`
  - reads: N/A

- Notes (only if applicable):
  - Raw vendor-specific profile payloads, if emitted, are debug outputs only and MUST NOT be treated as Coretsia artifacts.
  - Only normalized summaries written by this package are deterministic/Coretsia-owned.

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/platform/profiling/composer.json`
- [ ] `framework/packages/platform/profiling/src/Module/ProfilingModule.php`
- [ ] `framework/packages/platform/profiling/src/Provider/ProfilingServiceProvider.php`
- [ ] `framework/packages/platform/profiling/src/Provider/ProfilingServiceFactory.php`
- [ ] `framework/packages/platform/profiling/config/profiling.php`
- [ ] `framework/packages/platform/profiling/config/rules.php`
- [ ] `framework/packages/platform/profiling/README.md`
- [ ] `framework/packages/platform/profiling/src/Profiling/ProfilerManager.php`
- [ ] `framework/packages/platform/profiling/src/Profiling/UowProfilerHook.php`
- [ ] `framework/packages/platform/profiling/src/Http/Middleware/ProfileTriggerMiddleware.php`
- [ ] `framework/packages/platform/profiling/src/Driver/NullProfilerDriver.php`
- [ ] `framework/packages/platform/profiling/src/Driver/XhprofProfilerDriver.php`
- [ ] `framework/packages/platform/profiling/src/Driver/BlackfireBridgeDriver.php`
- [ ] `framework/packages/platform/profiling/src/Artifacts/ProfileSummaryWriter.php`
- [ ] `framework/packages/platform/profiling/src/Console/ProfileExplainCommand.php`
- [ ] `framework/packages/platform/profiling/src/Console/ProfilePruneCommand.php`
- [ ] `framework/packages/platform/profiling/src/Exception/ProfilingException.php`
- [ ] `docs/ops/profiling.md` — package usage + drivers + safety policy
- [ ] `docs/ops/runtime-performance-tuning.md` — FPM/RoadRunner/Swoole tuning recommendations

- [ ] `framework/packages/platform/profiling/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- [ ] `framework/packages/platform/profiling/tests/Contract/ProfileSummaryDoesNotLeakTest.php`
- [ ] `framework/packages/platform/profiling/tests/Integration/UowProfilingHooksDeterministicTest.php`
- [ ] `framework/packages/platform/profiling/tests/Integration/HttpProfileTriggerMiddlewarePolicyTest.php`
- [ ] `framework/packages/platform/profiling/tests/Integration/ProfileExplainCommandSafeOutputTest.php`

#### Modifies

- [ ] `docs/ssot/config-roots.md` — add root row for `profiling`
- [ ] `docs/ssot/http-middleware-catalog.md` — add profiling trigger middleware entry
- [ ] `docs/ssot/artifacts.md` — register `profile_summary@1`

#### Package skeleton (if type=package)

- [ ] `composer.json`
- [ ] `src/Module/ProfilingModule.php`
- [ ] `src/Provider/ProfilingServiceProvider.php`
- [ ] `config/profiling.php`
- [ ] `config/rules.php`
- [ ] `README.md`
- [ ] `tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/profiling/config/profiling.php`
- [ ] Keys (dot):
  - [ ] `profiling.enabled` = false
  - [ ] `profiling.driver` = `'null'|'xhprof'|'blackfire'`
  - [ ] `profiling.http.enabled` = false
  - [ ] `profiling.http.trigger.mode` = `'off'|'header'|'sampled'`
  - [ ] `profiling.http.trigger.header` = `'X-Coretsia-Profile'`
  - [ ] `profiling.cli.enabled` = true
  - [ ] `profiling.output.dir` = `'skeleton/var/profiling/<appId>'`
  - [ ] `profiling.output.max_summaries` = 100
- [ ] Rules:
  - [ ] raw vendor payloads are optional debug outputs only
  - [ ] normalized summary JSON is the only Coretsia-owned deterministic output

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (uses existing tags)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `\Coretsia\Profiling\Profiling\UowProfilerHook::class`
  - [ ] adds tag: `kernel.hook.before_uow` priority `200` meta `per owner schema`
  - [ ] adds tag: `kernel.hook.after_uow` priority `-200` meta `per owner schema`
  - [ ] registers: `\Coretsia\Profiling\Http\Middleware\ProfileTriggerMiddleware::class`
  - [ ] adds tag: `http.middleware.system_pre` priority `520` meta `per owner schema`
  - [ ] registers: `\Coretsia\Profiling\Console\ProfileExplainCommand::class`
  - [ ] adds tag: `cli.command` priority `0` meta `per owner schema`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `skeleton/var/profiling/<appId>/summary-*.json` (artifact `profile_summary@1`, deterministic keys, LF-only, final newline)
- Reads:
  - N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
  - [ ] `ContextKeys::REQUEST_ID` (if present; safe id only)
- [ ] Context writes (safe only):
  - [ ] none
- [ ] Reset discipline:
  - [ ] active profiler sessions/buffers MUST be reset-safe
  - [ ] stateful services implement `ResetInterface`
  - [ ] tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `profiling.session`
- [ ] Metrics:
  - [ ] `profiling.session_total` (labels: `driver,outcome`)
  - [ ] `profiling.session_duration_ms` (labels: `driver,outcome`)
- [ ] Logs:
  - [ ] summary only: driver, trigger source, duration, summary path
  - [ ] no raw headers/query/body/cookies/session ids/tokens/raw SQL

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Profiling\Exception\ProfilingException` — errorCode `CORETSIA_PROFILING_DRIVER_FAILED`
- [ ] Mapping:
  - [ ] reuse existing error pipeline; no profiling-specific HTTP coupling required

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw request headers/bodies
  - [ ] auth/cookies/session ids/tokens
  - [ ] raw SQL
  - [ ] raw profiler vendor payloads through logs/errors
- [ ] Allowed:
  - [ ] safe ids
  - [ ] `hash(value)` / `len(value)`
  - [ ] normalized summary metrics

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If `kernel.reset` used → `tests/Contract/ProfilingResetWiringTest.php`
  (asserts: tagged services implement `ResetInterface`; repeated UoW is clean)
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  (asserts: names + label allowlist + no PII)
- [ ] If redaction exists → `tests/Contract/ProfileSummaryDoesNotLeakTest.php`
  (asserts: raw headers/body/sql/tokens never appear)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/`
- [ ] Fake adapters:
  - [ ] FakeTracer
  - [ ] FakeMetrics
  - [ ] FakeLogger

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/profiling/tests/Unit/ProfileSummaryWriterDeterministicTest.php`
  - [ ] `framework/packages/platform/profiling/tests/Unit/DriverSelectionPolicyTest.php`
- Contract:
  - [ ] `framework/packages/platform/profiling/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] `framework/packages/platform/profiling/tests/Contract/ProfileSummaryDoesNotLeakTest.php`
  - [ ] `framework/packages/platform/profiling/tests/Contract/ProfilingResetWiringTest.php`
- Integration:
  - [ ] `framework/packages/platform/profiling/tests/Integration/UowProfilingHooksDeterministicTest.php`
  - [ ] `framework/packages/platform/profiling/tests/Integration/HttpProfileTriggerMiddlewarePolicyTest.php`
  - [ ] `framework/packages/platform/profiling/tests/Integration/ProfileExplainCommandSafeOutputTest.php`
- Gates/Arch:
  - [ ] deptrac updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected
- [ ] Verification tests present where applicable
- [ ] Determinism:
  - [ ] normalized summary outputs are deterministic
  - [ ] raw vendor payloads are explicitly non-canonical/debug-only
- [ ] Docs updated:
  - [ ] `framework/packages/platform/profiling/README.md`
  - [ ] `docs/ops/profiling.md`
  - [ ] `docs/ops/runtime-performance-tuning.md`
- [ ] Runtime tuning guidance covers:
  - [ ] PHP-FPM
  - [ ] RoadRunner
  - [ ] Swoole
