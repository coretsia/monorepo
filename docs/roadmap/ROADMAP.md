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

# Coretsia Framework (Monorepo) — ROADMAP (Non-product doc)

## Рекомендована “канонічна інструкція читання”:

- [x] PRELUDE — Repo bootstrap
- [x] PHASE 0 — SPIKES (Prototype найризиковіших частин)
- [ ] PHASE 1 — CORE (повний core/*: contracts + foundation + kernel)
- [ ] PHASE 2 — Mode Infrastructure & CLI
- [ ] PHASE 3 — RELEASE: micro (перший релізний режим)
- [ ] PHASE 4 — RELEASE: express
- [ ] PHASE 5 — RELEASE: hybrid
- [ ] PHASE 6+ — RELEASE: enterprise (extensions)

---

> Note: .x під-епіки дозволені тільки як розбиття одного епіку без зміни порядку/семантики.  
> Note: Ця дорожня карта є планом «з нуля»; ідентифікатори в цьому документі є канонічними.

## [PRELUDE — Repo bootstrap](PRELUDE.md)

### PRELUDE.10.0 Repo bootstrap (first commit): git hygiene + top-level legal/docs (MUST) [TOOLING]
### PRELUDE.20.0 Packaging strategy lock (MUST) [DOC/TOOLING]
### PRELUDE.30.0 Repo skeleton + базові composer/CI entrypoints (MUST) [TOOLING]
### PRELUDE.40.0 Phase 0 build order (recommended, NOT a dependency graph) (SHOULD) [DOC]
### PRELUDE.50.0 Phase 0 dependency table (SSoT) (MUST) [DOC]
### PRELUDE.60.0 Development workflow (Phase 0, canonical commands) (MUST) [DOC]

---

## [PHASE 0 — SPIKES (Prototype найризиковіших частин)](PHASE-0—SPIKES.md)

> Note: PHASE 0 зарезервована для spikes/prototypes.

### 0.10.0 Spikes boundary decision: tools-only vs devtools package (MUST) [DOC]
### 0.20.0 Spikes sandbox scaffolding + CI rails (MUST) [TOOLING]
### 0.30.0 Spikes boundary enforcement gate (MUST) [TOOLING]
### 0.40.0 `coretsia/internal-toolkit` (MUST) [TOOLING]
### 0.50.0 Deterministic file IO policy for spikes (MUST) [TOOLING]
### 0.60.0 Fingerprint determinism prototype (MUST) [TOOLING]
### 0.70.0 PayloadNormalizer + StableJsonEncoder prototype (MUST) [TOOLING]
### 0.80.0 Deptrac generator prototype (MUST) [TOOLING]
### 0.90.0 Two-phase config merge + directives + explain prototype (MUST) [TOOLING]
### 0.100.0 Composer workspace SPOF prototype: package-index + repositories sync (MUST) [TOOLING]
### 0.110.0 Publishing rails (GitHub/Packagist) (MUST) [TOOLING]
### 0.120.0 CLI ports in `coretsia/contracts` (MUST) [CONTRACTS]
### 0.130.0 Minimal `coretsia` CLI base (prod-safe) (MUST) [TOOLING]
### 0.140.0 `coretsia/cli-spikes` Phase 0 command pack (require-dev) (MUST) [TOOLING]

---

## [PHASE 1 — CORE (повний core/*: contracts + foundation + kernel + baseline platform invariants where required)](PHASE-1—CORE.md)

### 1.10.0 Tag registry (SSoT): reserved tags + naming rules (MUST) [DOC]
### 1.20.0 Config roots registry (SSoT): reserved roots + ownership (MUST) [DOC]
### 1.30.0 Artifact header & schema registry (SSoT) (MUST) [DOC]
### 1.40.0 Observability naming/labels allowlist (SSoT) (MUST) [DOC]
### 1.50.0 Tooling baseline + arch-rails + public API gates (MUST) [TOOLING]
### 1.50.1 DTO Policy + Compliance Rail (MUST) [TOOLING]
### 1.50.2 DTO Marker Consistency Gate (MUST) [TOOLING]
### 1.50.3 DTO No-Logic Gate (MUST) [TOOLING]
### 1.50.4 DTO Shape Gate (MUST) [TOOLING]
### 1.60.0 Package Compliance Gates (MUST) [TOOLING]

### 1.70.0 Contracts: Module/Descriptor/Manifest + ModePreset ports (MUST) [CONTRACTS]
### 1.80.0 Contracts: Config + Env + source tracking + directives invariants (MUST) [CONTRACTS]
### 1.90.0 Contracts: Observability + ErrorDescriptor + Health + Profiling ports (MUST) [CONTRACTS]
### 1.95.0 Contracts: ContextAccessorInterface (MUST) [CONTRACTS]
### 1.100.0 Errors SSoT: contracts ↔ platform boundary + ErrorDescriptor shape (MUST) [DOC]
### 1.110.0 Contracts: Routing + HttpApp ports (MUST) [CONTRACTS]
### 1.120.0 Contracts: ResetInterface + UoW hooks (MUST) [CONTRACTS]
### 1.130.0 Contracts: Validation ports (MUST) [CONTRACTS]
### 1.140.0 Contracts: Filesystem ports (MUST) [CONTRACTS]
### 1.150.0 Contracts: Database + Migrations ports (MUST) [CONTRACTS]
### 1.160.0 Contracts: RateLimit ports (MUST) [CONTRACTS]
### 1.170.0 Contracts: Mail port (MUST) [CONTRACTS]
### 1.180.0 Contracts: Secrets port (MUST) [CONTRACTS]
### 1.190.0 Cross-cutting baseline runtime invariants + HTTP middleware taxonomy enforcement (MUST) [DOC]

### 1.200.0 Foundation: DI Container + Tags + DeterministicOrder + Reset orchestration (MUST) [IMPL]
### 1.205.0 Foundation: Noop Observability + Logger baseline bindings (MUST) [IMPL]
### 1.210.0 Foundation: ContextBag + ContextStore + CorrelationId (MUST) [IMPL]
### 1.220.0 Foundation: Clock + IDs + Stopwatch (MUST) [IMPL]
### 1.230.0 ContextStore lifecycle usage (SSoT) (MUST) [DOC]
### 1.240.0 Stateful services policy (SSoT) (MUST) [DOC]
### 1.250.0 Enhanced Reset Mechanism for Long-Running Services (MUST) [IMPL]
### 1.260.0 Runtime drivers & long-running composition matrix (SSoT) (MUST) [DOC]

### 1.270.0 Kernel: UnitOfWork Shapes Pack (Context + Result + Outcome + SSoT invariants) (MUST) [IMPL+DOC]
### 1.280.0 Kernel: KernelRuntime (UoW SPI, no PSR-7) (MUST) [IMPL]
### 1.290.0 HTTP Kernel long-running safety harness (MUST) [IMPL]
### 1.300.0 Kernel boot: Bootstrap Phase A (env policy + minimal inputs) (MUST) [IMPL]
### 1.310.0 Kernel: Module Plan (discovery + presets + graph + policies) (MUST) [IMPL]
### 1.320.0 Kernel: ConfigKernel Phase B + SSoT Docs (directives + merge order + precedence) (MUST) [IMPL+DOC]
### 1.330.0 Kernel: Artifacts (manifest + config) + fingerprint + cache:verify core (MUST) [IMPL]
### 1.340.0 Kernel: Container compile (REAL) + `container.php` artifact (MUST) [IMPL]
### 1.350.0 Kernel: RuntimeDriverGuard + Runtime driver matrix E2E locks (MUST) [IMPL+TOOLING]
### 1.360.0 Long-Running Runtime: Worker Manager & Application Worker (MUST) [IMPL]
### 1.370.0 Framework: AppBuilder + boot smoke suite (MUST) [TOOLING]

## 1.380.0 Cyclic Dependencies Gate (package level) (MUST) [TOOLING]
## 1.390.0 Deprecated API Gate (MUST) [TOOLING]
## 1.400.0 Composer Audit Gate (MUST) [TOOLING]
## 1.410.0 Unified Code Style Gate (MUST) [TOOLING]
## 1.420.0 Test Coverage Gate (MUST) [TOOLING]
## 1.430.0 SOLID Architecture Enforcer Gate (MUST) [TOOLING]
## 1.440.0 Secret Leakage Gate (MUST) [TOOLING]
## 1.450.0 PR Size / Focus Gate (MUST) [TOOLING]
## 1.460.0 CLI Performance Gate (MUST) [TOOLING]
## 1.470.0 Atomic Transaction Gate (MUST) [TOOLING]

---

## [PHASE 2 — Mode Infrastructure & CLI](PHASE-2—Mode-Infrastructure.md)

### 2.10.0 Mode presets: SSoT format + packaging enforcement gate (MUST) [DOC/TOOLING]
### 2.20.0 Kernel fixtures for mode presets (SHOULD) [IMPL]
### 2.25.0 Kernel ops façade for CLI (MUST) [IMPL]
### 2.30.0 Platform CLI — Tag-first Command Catalog + Kernel ops façade (MUST) [IMPL]
### 2.40.0 Platform CLI: Workflows + Advanced UX (SHOULD) [IMPL]
### 2.50.0 Front Controller stub + deterministic smoke (MUST) [IMPL]
### 2.60.0 Devtools CLI-spikes: migrate to tag-first `cli.command` (MUST) [IMPL]

---

## [PHASE 3 — RELEASE: micro (перший релізний режим)](PHASE-3—RELEASE-micro.md)

> Мета: повноцінний HTTP runtime + базова observability/errors UX.

### 3.10.0 Platform errors: ErrorHandler + ExceptionMapper registry (MUST) [IMPL]
### 3.20.0 Platform logging (PSR-3) (MUST) [IMPL]
### 3.30.0 Platform tracing baseline (noop-safe + W3C propagation) (MUST) [IMPL]
### 3.31.0 Platform metrics baseline (noop-safe + label allowlist + renderer port) (MUST) [IMPL]
### 3.40.0 Platform problem-details (RFC7807) (MUST) [IMPL]
### 3.50.0 Platform HTTP runtime (pipeline + middleware + UoW + wiring model) (MUST) [IMPL]
### 3.60.0 RequestId vs CorrelationId policy (SHOULD) [IMPL]
### 3.70.0 Response hardening (security headers + HTTPS redirect) (SHOULD) [IMPL]
### 3.80.0 Maintenance mode (real toggle, MUST) (MUST) [IMPL]
### 3.90.0 SystemRouteTable reserved map (SSoT) (MUST) [DOC]
### 3.100.0 Reserved path matching rules (SSoT) (MUST) [DOC]
### 3.110.0 HTTP FallbackRouter + system endpoints (MUST) [IMPL]
### 3.120.0 HTTP dev diagnostics endpoints (SHOULD) [IMPL]
### 3.130.0 Platform routing (compiled routes artifact + runtime router) (MUST) [IMPL]
### 3.140.0 Platform http-app (RouterMiddleware + ActionInvoker) (MUST) [IMPL]
### 3.145.0 Web app entrypoint: real boot + module registration + browser-visible output (MUST) [IMPL]
### 3.150.0 CORS middleware (inside platform/http) (MUST) [IMPL]
### 3.160.0 Method override + Content negotiation (umbrella) (SHOULD) [DOC]
### 3.170.0 Method override middleware (inside platform/http) (SHOULD) [IMPL]
### 3.180.0 Content negotiation middleware (inside platform/http) (SHOULD) [IMPL]
### 3.190.0 ProblemDetails HTML renderer + error pages (SHOULD) [IMPL]
### 3.200.0 HTTP Performance Benchmarking Harness (MUST) [TOOLING]

---

## [PHASE 4 — RELEASE: express](PHASE-4—RELEASE-express.md)

> Мета: “web + persistence + IO” (validation/filesystem/db/uploads/migrations).

### 4.10.0 coretsia/validation (reference validation engine) (MUST) [IMPL]
### 4.10.1 Extended format rules pack (SHOULD) [IMPL]
### 4.10.2 Date/time rules pack (SHOULD) [IMPL]
### 4.10.3 Message/i18n layer (SHOULD) [IMPL]
### 4.10.4 DTO validation adapter (SHOULD) [IMPL]
### 4.10.5 File validation pack (OPTIONAL) [IMPL]
### 4.10.6 Database validation pack (OPTIONAL) [IMPL]
### 4.10.7 Convenience facade/helpers (OPTIONAL) [IMPL]
### 4.20.0 coretsia/http-client (outgoing HTTP) (SHOULD) [IMPL]
### 4.30.0 Platform filesystem + Local driver (MUST) [IMPL]
### 4.40.0 Filesystem drivers (S3/FTP/SFTP) (SHOULD) [DOC]
### 4.50.0 Uploads: multipart parsing + validation + quarantine (MUST) [IMPL]
### 4.60.0 Platform database core (DriverPort + ConnectionManager + QueryBuilder) (MUST) [IMPL]
### 4.70.0 Platform database-driver-sqlite (MUST) [IMPL]
### 4.71.0 Platform database-driver-mysql (MUST) [IMPL]
### 4.72.0 Platform database-driver-mariadb (MUST) [IMPL]
### 4.73.0 Platform database-driver-pgsql (MUST) [IMPL]
### 4.74.0 Platform database-driver-sqlserver (MUST) [IMPL]
### 4.80.0 Database drivers (MySQL/MariaDB/PostgreSQL/SQL Server) (SHOULD) [DOC]
### 4.90.0 Migrations (driver-agnostic) (MUST) [IMPL]
### 4.95.0 Contracts: Queue (jobs, serialization, retry) (MUST) [CONTRACTS]
### 4.100.0 platform/mail (MUST) [IMPL]
### 4.101.0 integrations/mail-smtp (MUST) [IMPL]
### 4.110.0 coretsia/view (MUST) [IMPL]
### 4.120.0 Translation/i18n (SHOULD) [IMPL]
### 4.130.0 Contracts: Auth/Session/Security/Lock (IMPL boundary ports) (MUST) [CONTRACTS]
### 4.140.0 coretsia/session — Session layer (file storage + middleware) (MUST) [IMPL]
### 4.150.0 coretsia/auth — Session auth + RBAC (MUST) [IMPL]
### 4.160.0 coretsia/auth — Token/Bearer + optional JWT guard (SHOULD) [IMPL]
### 4.170.0 coretsia/security — CSRF + Signed URLs (MUST) [IMPL]
### 4.180.0 coretsia/encryption — Data encryption + key management (SHOULD) [IMPL]
### 4.190.0 platform/http — Rate limiting (identity-aware) (SHOULD) [IMPL]
### 4.200.0 coretsia/hashing — Password hashing (SHOULD) [IMPL]
### 4.210.0 coretsia/lock — Lock factory + reference drivers (SHOULD) [IMPL]
### 4.220.0 coretsia/cache — PSR-16 cache + manager + reference stores (SHOULD) [IMPL]
### 4.230.0 Core Kernel + Foundation Hot-path Optimization (MUST) [IMPL+TOOLING]
### 4.240.0 Performance Gates for Database and Filesystem (SHOULD) [TOOLING]

---

## [PHASE 5 — RELEASE: hybrid](PHASE-5—RELEASE-hybrid.md)

> Мета: asynchronous patterns + enterprise-grade features (events/queue/scheduler/cqrs/secrets + enterprise E2E).

### 5.10.0 coretsia/secrets — Secrets resolver (platform implementation) (MUST) [IMPL]
### 5.20.0 coretsia/auth — Authorization engines: RBAC + REBAC (OPTIONAL) [IMPL]
### 5.30.0 Contracts: Events (sync + deferred, UoW-friendly) (MUST) [CONTRACTS]
### 5.40.0 coretsia/events — Sync dispatcher + deferred queue (MUST) [IMPL]
### 5.50.0 Deferred events semantics (flush policy) (SHOULD) [DOC]
### 5.60.0 coretsia/queue — Core (sync driver + worker runtime) (MUST) [IMPL]
### 5.70.0 coretsia/queue — DB driver (async reference) (SHOULD) [IMPL]
### 5.80.0 coretsia/queue — CLI commands queue:* (MUST) [IMPL]
### 5.90.0 Contracts: CommandBus + Scheduler (MUST) [CONTRACTS]
### 5.100.0 coretsia/cqrs — Command bus + middleware pipeline (MUST) [IMPL]
### 5.110.0 coretsia/scheduler — Schedule registry + schedule:run (MUST) [IMPL]
### 5.120.0 Enterprise fixture E2E (RC MUST) (MUST) [IMPL]
### 5.130.0 Asynchronous Performance Tuning (SHOULD) [IMPL+TOOLING]

---

## [PHASE 6+ — RELEASE: enterprise (extensions)](PHASE-6—RELEASE-enterprise.md)

### 6.10.0 coretsia/health (real owner for `/health*`) (MUST) [IMPL]
### 6.20.0 coretsia/metrics (MUST) [IMPL]
### 6.21.0 integrations/metrics-prometheus (MUST) [IMPL]
### 6.30.0 coretsia/tracing (MUST) [IMPL]
### 6.31.0 integrations/tracing-otlp (MUST) [IMPL]
### 6.32.0 integrations/tracing-zipkin (MUST) [IMPL]
### 6.40.0 coretsia/observability (glue module, NOT a monolith) (MUST) [IMPL]
### 6.50.0 coretsia/profiling (sampling profiler hooks) (MUST) [IMPL]
### 6.60.0 integrations/cache-redis (MUST) [IMPL]
### 6.61.0 integrations/cache-apcu (MUST) [IMPL]
### 6.70.0 integrations/lock-redis (MUST) [IMPL]
### 6.71.0 integrations/session-redis (MUST) [IMPL]
### 6.72.0 integrations/queue-redis (MUST) [IMPL]
### 6.80.0 coretsia/features (feature flags + experiments + analytics hooks + CLI) (MUST) [IMPL]
### 6.90.0 coretsia/outbox (reliable publish) (MUST) [IMPL]
### 6.100.0 coretsia/inbox (idempotency middleware + store) (MUST) [IMPL]
### 6.110.0 coretsia/event-sourcing (optional world) (OPTIONAL) [IMPL]
### 6.120.0 devtools/scaffolding (idempotent generators) (MUST) [TOOLING]
### 6.130.0 devtools/api-docs (OpenAPI UI glue + /docs reserved) (MUST) [IMPL]
### 6.140.0 devtools/dev-tools (debugbar middleware, dev-only) (MUST) [IMPL]
### 6.150.0 devtools/admin-panel (product, /_admin reserved) (MUST) [IMPL]
### 6.160.0 Realtime/protocols (reactive/websocket/graphql/grpc) — roadmap index (SHOULD) [DOC]
### 6.161.0 Contracts: Realtime protocols ports (reactive/websocket/graphql/grpc) (MUST) [CONTRACTS]
### 6.162.0 coretsia/reactive (streams + deterministic safety rails) (SHOULD) [IMPL]
### 6.163.0 coretsia/websocket (handler registry + kernel) (MUST) [IMPL]
### 6.164.0 coretsia/graphql (schema provider + executor + HTTP adapter) (SHOULD) [IMPL]
### 6.165.0 coretsia/grpc (service registry + interceptors boundary) (SHOULD) [IMPL]
### 6.170.0 coretsia/streaming (SSE + NDJSON + disable buffering) (MUST) [IMPL]
### 6.180.0 coretsia/async (timeouts + cancellation tokens) (OPTIONAL) [IMPL]
### 6.190.0 integrations/runtime-frankenphp (long-running adapter) (MUST) [IMPL]
### 6.200.0 integrations/runtime-swoole (long-running adapter) (MUST) [IMPL]
### 6.210.0 integrations/runtime-roadrunner (long-running adapter) (SHOULD) [IMPL]
### 6.220.0 Runtime ops: HTTP/2 + HTTP/3 enablement guide (MUST) [DOC]
### 6.230.0 coretsia/etl (pipelines + CLI) (MUST) [IMPL]
### 6.240.0 coretsia/preload (deterministic preload generator + CLI) (MUST) [TOOLING]
### 6.250.0 HTTP response performance middlewares catalog (Compression + ETag + Cache-Headers) (SHOULD) [DOC]
### 6.260.0 HTTP Compression middleware (gzip/br) (MUST) [IMPL]
### 6.270.0 HTTP ETag + Conditional GET middleware (MUST) [IMPL]
### 6.280.0 HTTP Cache-Control headers middleware (SHOULD) [IMPL]
### 6.290.0 Database advanced: ORM + Transactions (SHOULD) [IMPL]
### 6.300.0 Bundles/Feature Packs (meta-modules) (MUST) [IMPL]
### 6.310.0 Enterprise SSO: OIDC (SHOULD) [IMPL]
### 6.320.0 Enterprise SSO: SAML (SHOULD) [IMPL]
### 6.330.0 Enterprise: SCIM (SHOULD) [IMPL]
### 6.340.0 Enterprise Tenancy (MUST) [IMPL]
### 6.350.0 Enterprise Audit (MUST) [IMPL]
### 6.360.0 Enterprise Compliance (MUST) [IMPL]
### 6.370.0 integrations/secrets-vault + ops docs (Vault) (SHOULD) [IMPL]
### 6.371.0 integrations/secrets-aws (SHOULD) [IMPL]
### 6.372.0 integrations/secrets-gcp (SHOULD) [IMPL]
### 6.380.0 Webhooks (outgoing dispatch + HMAC signing/verify + retry) (SHOULD) [IMPL]
### 6.390.0 Search (ports + platform facade + ADR + fake adapter) (SHOULD) [CONTRACTS]
### 6.391.0 integrations/search-elastic (SHOULD) [IMPL]
### 6.392.0 integrations/search-opensearch (SHOULD) [IMPL]
### 6.393.0 integrations/search-meilisearch (SHOULD) [IMPL]
### 6.400.0 API versioning (header-based middleware + optional route-provider helper) (SHOULD) [IMPL]
### 6.410.0 Rate limit advanced (Redis store + tiers + burst, backward-compatible) (SHOULD) [IMPL]
### 6.420.0 Uploads virus scanning hook (quarantine → scan job → verdict) (SHOULD) [IMPL]
### 6.430.0 Policy engine (OPA-like DSL) (SHOULD) [IMPL]
### 6.440.0 Distributed scheduler (leader election via LockFactory) (SHOULD) [IMPL]
### 6.450.0 integrations/view-twig (SHOULD) [IMPL]
### 6.460.0 integrations/view-blade (SHOULD) [IMPL]
### 6.470.0 Contracts: AI (LLM gateway + tools + embeddings ports) (MUST) [CONTRACTS]
### 6.471.0 coretsia/ai (LLM gateway runtime + tools discovery + fake drivers) (MUST) [IMPL]
### 6.472.0 Contracts: AI guardrails (prompt policy + PII redaction hooks) (MUST) [CONTRACTS]
### 6.473.0 coretsia/ai-guardrails (policy engine + PII hooks + allow/deny lists) (MUST) [IMPL]
### 6.474.0 Contracts: AI vectorstore (vector DB ports) (MUST) [CONTRACTS]
### 6.475.0 coretsia/ai-vectorstore (ports wiring + adapters: pgvector + in-memory) (MUST) [IMPL]
### 6.480.0 Advanced Caching & Preloading Strategy (SHOULD) [IMPL+TOOLING]
### 6.490.0 Production Performance Profiling & Observability (SHOULD) [IMPL+DOC]

## 🚀 [Додаток A: Ops (Non-SSoT, поза DAG фреймворку)](APPENDIX-A.md)
