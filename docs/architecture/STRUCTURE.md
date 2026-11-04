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

# Structure with a full package catalog (Non-product doc)

- The structure is split by layers (Core / Platform / Integrations / Enterprise / DevTools / Presets) + naming and dependency rules, so that modularity is real rather than “dependencies everywhere”.

---

## 0) Canonical structure rules (fixed once and for all)

### 0.0. Packaging and dependency strategy (canonical references)

- All `path ↔ package_id ↔ composer ↔ namespace`, publishable units, and versioning rules MUST comply with:
  - `docs/architecture/PACKAGING.md`
- Exact direct compile-time dependency permissions between layered packages MUST comply with:
  - `docs/architecture/DEPENDENCIES.md`
- Application installation intent, managed Composer roots, explicit effects, and installed-state verification MUST comply with:
  - `docs/ssot/application-dependency-sync.md`
- Runtime mode selection and module metadata MUST comply with:
  - `docs/ssot/modes.md`
  - `docs/ssot/modules-and-manifests.md`

### 0.1. Layered package identity and special distributions

- Layered package path MUST be: `packages/<layer>/<slug>/`
- Layered package id MUST be: `<layer>/<slug>` (e.g. `platform/http-client`)
- Layered package Composer name MUST follow: `coretsia/<layer>-<slug>` (e.g. `coretsia/platform-http-client`)
- Special public distributions are:
  - `packages/framework/` → `coretsia/framework`
  - `packages/applications/skeleton/` → `coretsia/skeleton`
- For every publishable product, Composer identity MUST be read from its `composer.json` `name`; filesystem layout MUST NOT be used as the universal package-name source of truth.
- Slug uniqueness policy (single-choice):
  - slug MUST be unique within one layer
  - slug MAY repeat across layers (uniqueness is ensured by the `<layer>-` prefix in the composer name)
- Namespace mapping MUST follow the canonical `Studly()` algorithm:
  - `core/*` → `Coretsia\<Studly(slug)>\...`.
  - Exception: `core/dto-attribute` → `Coretsia\Dto\Attribute\...` (`src/Attribute/`).
  - Non-core packages (`platform/*`, `integrations/*`, `enterprise/*`, `devtools/*`, `presets/*`) → `Coretsia\<Studly(layer)>\<Studly(slug)>\...`.
  - `src/` and `tests/` MUST follow the canonical PSR-4 mappings.
- For `core/*` packages, reserved slugs `core`, `platform`, `integrations`, `enterprise`, `devtools`, and `presets` MUST NOT be used to prevent namespace collisions.
- Versioning MUST be monorepo-wide via repo tags `vMAJOR.MINOR.PATCH`; per-package versions MUST NOT be used.

> Note: tooling-only libs that live outside `packages/**` (for example `tools/**`)
> are not publishable units and may have separate rules (see `docs/architecture/PACKAGING.md`).

### 0.2. Core vs Platform vs Integrations — hard separation

- Core: the minimum everything stands on (contracts/foundation/kernel).
- Platform: the framework’s “built-in” capabilities (http/db/queue/security/…).
- Integrations: everything that pulls concrete drivers/vendors (redis/prometheus/s3/otlp/smtp/…).
- DevTools: development and repository tooling (scaffolding/generators/validation/profiling tools), not application runtime modules.
- Enterprise: “strict stack” policies/packages (audit/compliance/tenancy/sso/…).
- Presets: composer convenience packages (they pull dependencies), and do not replace runtime modes.

### 0.3. Layered packages

- Each layered package is self-contained within its package directory; the exact scaffold depends on its package kind and is governed by `docs/architecture/PACKAGING.md`.
- Layered packages do not pull in the application: no `apps/*` and no project-specific configuration inside layered package source trees.

### 0.4. Skeleton distribution and consumer application

- `packages/applications/skeleton/` is the public `coretsia/skeleton` create-project template.
- Template-owned application surfaces may include `apps/`, `modules/`, `config/`, `resources/`, `var/`, and `tests/`.
- `packages/applications/skeleton/bin/dependency-sync.php` is the canonical project-owned adapter for explicit dependency-sync `plan` / `review` / `apply` operations.
- `packages/applications/skeleton/coretsia` routes only these exact explicit operations to the adapter before normal CLI host boot; all other invocations delegate to the Composer-installed CLI binary.
- The Skeleton-specific dependency synchronization routes are not `cli.command` services and do not participate in the normal CLI command catalog or UnitOfWork lifecycle.
- `config/` in the skeleton template contains application overrides only, not framework defaults.
- Package defaults exist only for runtime packages that own a reserved config root. Their canonical defaults files live under `packages/<layer>/<slug>/config/<root>.php` and are declared through `extra.coretsia.defaultsConfigPath`.
- Config-free runtime packages do not contribute package default-config sources and MUST NOT be required to create placeholder config files.
- After `composer create-project coretsia/skeleton <app>`, the extracted template root becomes the consumer application root.
- The installation application set is explicit caller input; the presence of `apps/<appTarget>/`, preset keys, or other application directories MUST NOT infer target membership.
- Runtime/package code MUST NOT depend on `packages/applications/skeleton/` or any other monorepo source path.
- Consumer DependencySync MUST NOT discover package identity by walking monorepo `packages/**` or importing repository-only `tools/**`.

### 0.5. “contracts” — the single source of truth for ports

- `core/contracts` — centralized ports (Module/Config/Env/Bus/Observability/Storage/Security/Runtime…)
- Implementations live in concrete packages (`integrations/*`, `platform/*`).
- Minimize “Contracts in packages”. If absolutely needed, only private internal interfaces, not cross-package APIs.
- If a capability already has a sufficient external standard port (e.g. `Psr\SimpleCache\CacheInterface`), `core/contracts` MAY NOT introduce a duplicate port without a separate framework-specific need.

### 0.6. DependencySync ownership and distribution boundary

- Application dependency synchronization is Kernel-owned consumer-side functionality under `packages/core/kernel/src/DependencySync/**`.
- Shared low-level scoped file locking is Foundation-owned in `packages/core/foundation/src/Filesystem/ScopedFileLock.php`.
- Repository authoring generates the installation catalog through `tools/build/installation_catalog.php`:

```bash
php tools/build/installation_catalog.php --apply
php tools/build/installation_catalog.php --check
```

- Consumer/runtime code MUST NOT import that repository-only tool.
- The generated consumer-safe catalog is distributed as `packages/core/kernel/resources/packaging/installation-catalog.php`.
- The catalog maps implemented runtime `ModuleId` values to materialized Composer package identities and package-level module edges for the current release line.
- Runtime installed-state discovery still comes from the installed `ModuleManifest`; the installation catalog is not a substitute for installed Composer metadata.
- DependencySync and Artifact/Runtime implementation code do not depend directly on each other; both may reuse lower-level Foundation primitives.
- Composer effects are explicit consumer tooling effects and never execute as part of normal runtime boot.

---

## 1) Final monorepo structure (publishable products + tooling)

Canonical repository ownership:

- `packages/**` — publishable Composer products.
- `tools/**` — repository machinery: gates, generators, CI rails, architecture/testing support, and developer tooling.
- `var/**` — mutable/generated repository workspace state; never publishable package content.
- `composer.json` — the sole monorepo developer workspace manifest.
- `composer.lock` — the sole monorepo workspace lock.
- `vendor/**` — the sole monorepo workspace dependency installation.
- `.githooks/` — Git hooks, enabled via `composer setup`.

Layered package roots remain `core`, `platform`, `integrations`, `enterprise`, `devtools`, and `presets`.

Special public distributions are `packages/framework/` and `packages/applications/skeleton/`.

Canonical entrypoints: everything runs from the repo root via `composer setup|test|ci`.

See the minimal “clean clone → green baseline” scenario in `docs/guides/quickstart.md`.

The following layout is schematic; reserved layer roots may be materialized as packages are introduced.

```text
coretsia/
├── composer.json
├── composer.lock
├── vendor/
├── var/
│   └── backups/
│
├── coretsia
│
├── packages/
│   ├── framework/
│   │   └── composer.json
│   ├── applications/
│   │   └── skeleton/
│   │       ├── bin/
│   │       │   └── dependency-sync.php
│   │       ├── composer.json
│   │       ├── .env.example
│   │       ├── config/
│   │       ├── apps/
│   │       └── var/
│   ├── core/
│   │   ├── contracts/
│   │   ├── dto-attribute/
│   │   ├── foundation/
│   │   │   └── src/Filesystem/ScopedFileLock.php
│   │   └── kernel/
│   │       ├── bin/dependency-sync-verify.php
│   │       ├── resources/
│   │       │   ├── modes/
│   │       │   └── packaging/installation-catalog.php
│   │       └── src/DependencySync/
│   ├── platform/
│   ├── integrations/
│   ├── enterprise/
│   ├── devtools/
│   └── presets/
│
├── tools/
│   ├── build/
│   │   └── installation_catalog.php
│   ├── release/
│   ├── gates/
│   ├── testing/
│   ├── tests/
│   ├── support/
│   ├── phpstan/
│   └── cs/
│
├── docs/
├── .github/
└── .githooks/
```

---

## 2) Canonical template for a layered runtime package

> Goal: a runtime package participates in metadata-driven module and provider planning and MAY provide default config,
> rules, tags, commands, migrations, and resources when those surfaces belong to the package.

```txt
packages/<layer>/<slug>/
├── src/
│   ├── Module/                    # package-local <StudlySlug>Module helper
│   ├── Provider/                  # <StudlySlug>ServiceProvider
│   ├── Contracts/                 # ONLY if internal, not cross-package API
│   ├── Console/                   # CLI commands (if the package adds its own)
│   ├── Http/                      # middleware/handlers (if relevant)
│   └── ...
├── config/                        # only when the package owns a config root
│   ├── <root>.php                 # defaults (subtree; without wrapper root)
│   ├── rules.php                  # declarative validation rules
│   └── deprecations.php           # optional
├── resources/                     # views/lang/assets (optional)
│   ├── views/
│   ├── lang/
│   └── assets/
├── database/
│   └── migrations/                # optional
├── tests/
│   ├── Contract/
│   │   └── CrossCuttingNoopDoesNotThrowTest.php
│   ├── Unit/                       # optional
│   └── Integration/                # optional
├── README.md
├── LICENSE
├── NOTICE
└── composer.json
```

Required conventions inside the package

- Runtime module identity, dependency/conflict edges, provider-planning declarations, and optional default-config metadata are authoritative under validated Composer `extra.coretsia` metadata.
- Kernel runtime discovery MUST read installed Composer metadata and MUST NOT instantiate package-local module classes to derive module identity, dependency/conflict edges, providers, or default-config paths.
- `src/Module/<StudlySlug>Module.php` is a package-local runtime metadata helper, not a runtime discovery source.
  - Helpers such as `FoundationModule`, `KernelModule`, and `RedactionModule` are not required to implement `Coretsia\Contracts\Module\ModuleInterface`.
  - A helper MAY mirror stable package metadata through `id()`, `packageId()`, `composerPackage()`, `kind()`, and `providers()`.
  - A package owning a reserved config root MAY additionally expose its owner-local `configRoot()`.
  - Helpers MUST NOT be treated as authoritative sources of package version, runtime dependency/conflict edges, `defaultsConfigPath`, or provider-planning metadata.
  - Package versions remain derived from canonical release tags, not module helper methods.
- `src/Provider/<StudlySlug>ServiceProvider.php` owns runtime DI definitions and applicable service tags.
  - Provider application remains controlled by the runtime composition owner.
  - Composer package presence or module helper construction MUST NOT automatically enable the runtime module or apply its provider.
- A runtime package that owns a reserved config root MUST provide `config/<root>.php` and `config/rules.php`.
  - `config/<root>.php` MUST return a plain array subtree without repeating the root wrapper.
  - `config/rules.php` MUST return declarative validation rules.
  - `config/deprecations.php` MAY be provided when needed.
- A config-free runtime package MAY omit `config/`, `CONFIG_ROOT`, `configRoot()`, and `extra.coretsia.defaultsConfigPath`.
  - No placeholder config directory, defaults file, rules file, or metadata declaration is required.
  - A module contributes a package default-config source only when validated `extra.coretsia.defaultsConfigPath` metadata is present.

---

## 3) Core contracts structure

```txt
packages/core/contracts/src/
├── Cli/
├── Config/
├── Context/
├── Database/          # ports, events contracts (not implementations)
├── Env/
├── Filesystem/
├── HttpApp/
├── Kernel/
├── Mail/
├── Migrations/
├── Module/
├── Observability/
├── RateLimit/
├── Routing/
├── Runtime/           # optional (loop/async primitives)
├── Secrets/
├── Security/
├── Validation/
└── Worker/
```

---

## 4) Adapter packages (integrations): canonical naming

- Package ID: `integrations/<capability>-<vendor>` or `integrations/<capability>-<driver>`.
- Composer identity: `coretsia/integrations-<slug>`.
- Canonical integration identities and responsibilities are listed in §10.3.
- Integration packages MAY depend on Core, Platform, or other Integration packages, subject to the canonical direct dependency matrix.
- Platform packages MUST NOT depend on concrete Integration implementations.

---

## 5) Preset packages vs Mode presets (to avoid confusion)

### 5.1. Mode presets (kernel runtime planning)

- Canonical names (`micro`, `express`, `hybrid`, `enterprise`) are Kernel-owned resources under `packages/core/kernel/resources/modes/*.php`, read exclusively through `CanonicalPresetSource`.
- Custom names belong to the consumer application under `<applicationRoot>/<kernel.modes.overrides_path>/*.php`, normally `config/modes/*.php`, and are read exclusively through `CustomPresetSource`.
- Application files cannot override reserved canonical names; names determine source ownership, never file-existence precedence.
- Each schema-version-1 preset declares disjoint `required` (non-excludable) and `modules` (explicitly excludable) runtime-module ids plus feature bundles. `ModuleSelectionFactory` combines this policy with selected app-target `moduleOverrides` before graph resolution.
- Selecting a runtime preset does not itself install or update Composer packages. Physical dependency synchronization occurs only through an explicit DependencySync invocation for an explicit application target set.

### 5.2. Preset packages (composer dependency convenience)

- Composer preset distributions live under `packages/presets/preset-*/`.
- Each distribution MUST represent the corresponding canonical mode's implemented default package composition from §9.
- Optional addons MUST NOT be included as additional default dependencies.
- Composer preset distributions MUST NOT define runtime module roots or activate installed modules.
- Kernel-owned PHP mode presets remain the authoritative runtime selection policy.

### 5.3. Mode intent vs physical package planning

For DependencySync, the runtime-mode policy and the physical Composer graph remain separate:

```text
explicit application targets
    → per-target ModePreset + moduleOverrides
    → per-target ModuleSelection
    → versioned installation catalog
    → per-target installation closure
    → project-wide physical module union
    → managed Coretsia Composer roots
    → one composer.lock / vendor graph
    → fresh installed-state verification
    → one ModulePlan per target
```

The installation catalog is the only consumer-side mapping from implemented runtime `ModuleId` values to materialized Composer package identities. Planned package names in sections 9–10 do not become DependencySync inputs until the package exists in the validated catalog source set and is emitted into the release installation catalog.

A package installed for the project-wide Composer graph is not automatically enabled for every target. Target-local `exclude` remains runtime policy; it is not a project-global Composer package removal instruction.

---

## 6) Consumer application DDD module layout

Recommended structure for application-owned bounded contexts:

```txt
modules/<ContextName>/
├── src/
│   ├── Domain/                    # Entities, VOs, Domain Events, Specs
│   ├── Application/               # Use-cases, Commands/Queries, Handlers
│   ├── Infrastructure/            # DB repos, integrations, adapters
│   └── Presentation/              # Controllers/Endpoints (optional)
├── config/
│   └── <context>.php              # overrides only (if needed)
├── database/
│   └── migrations/                # optional, if the module has a schema
├── resources/
│   ├── views/                     # optional
│   └── lang/                      # optional
├── tests/
│   ├── Unit/
│   └── Integration/
├── <Context>Module.php            # optional helper; not a discovery source
└── README.md
```

> Application module directories do not imply runtime registration. Runtime discovery and activation require validated module metadata and Kernel module planning.

Integration channels between modules:

- domain events
- query bus (read model)
- ports (interfaces from contracts, implementations via DI)
- (optional) outbox/inbox for delivery guarantees

---

## 7) Layer-level dependency direction (conceptual summary)

> Exact direct package-to-package compile-time dependency permissions are defined only in `docs/architecture/DEPENDENCIES.md`. \
> This section summarizes layer direction and MUST NOT be used to infer additional package-level edges.

Conceptual layer-direction matrix:

| From \ To    | Core | Platform |   Integrations | DevTools | Enterprise |
|--------------|-----:|---------:|---------------:|---------:|-----------:|
| Core         |   ✅ |       ❌ |             ❌ |       ❌ |         ❌ |
| Platform     |   ✅ |      ✅* |             ❌ |       ❌ |         ❌ |
| Integrations |   ✅ |       ✅ |             ✅ |       ❌ |         ❌ |
| DevTools     |   ✅ |       ✅ | ✅ (sometimes) |       ✅ |         ❌ |
| Enterprise   |   ✅ |       ✅ |             ✅ |       ❌ |         ✅ |

---

## 8) Runtime artifact layout

- consumer application: `var/cache`, `var/logs`, `var/tmp`, `var/quarantine`
- Kernel artifact root: `<applicationRoot>/<artifactsCacheDir>/<appTarget>` (by default, `var/cache/<appTarget>` relative to the application root).
- Finalized generation: `<artifact-root>/generations/<generation-id>/` containing `module-manifest.php`, `config.php`, `container.php`, and `generation-manifest.php`.
- Generation selection and coordination: `<artifact-root>/current` and `<artifact-root>/generation.lock`. Runtime boot consumes one validated generation selected through `current`.

---

## 9) Final mode map

> Canonical target composition for `micro`, `express`, `hybrid`, and `enterprise`. Only implemented runtime modules may be selected by executable PHP presets.

### 9.0. Composition rules

- **Direct roots** — top-level `layer/slug` entries represent runtime packages selected by the mode. Executable PHP presets declare their corresponding `ModuleId` values under `required` (non-excludable) or `modules` (excludable).
- **Transitive dependencies** — nested entries show modules included through `extra.coretsia.requires` rather than direct preset selection. They do not represent the complete dependency graph; validated module metadata remains authoritative.
- **Composer dependencies** — libraries such as `core/contracts` contribute to the package dependency closure but are not runtime modules. Composer installation MUST NOT imply module activation.
- **Mode composition** — canonical modes define independent default module selections with shared runtime foundations. Modes do not inherit package roots from one another.
- **Kernel baseline** — `core.foundation` and `core.kernel` remain explicit `required` roots, regardless of their dependency relationship.
- **Runtime composition** — Composer installation is project-wide; `ModuleSelection` and `ModulePlan` are application-target-local.
- **Dependency ownership** — validated module metadata defines the effective runtime graph. Target dependency edges MUST be declared in `extra.coretsia.requires` when implemented.
- **Cross-mode addons** — packages included by default in one canonical mode MAY be explicitly selected in another mode when their runtime dependencies and conflicts permit. This MUST NOT alter either canonical default composition.

### 9.1. Micro — production-safe HTTP minimum

**Direct preset roots:**

- `core/foundation`
- `core/kernel`
- `platform/cli`
  - `platform/errors`
    - `platform/redaction`
  - `platform/redaction`
- `platform/http`
- `platform/http-app`
- `platform/logging`
  - `platform/redaction`
- `platform/problem-details`
  - `platform/errors`
    - `platform/redaction`
- `platform/routing`

**Capabilities:** HTTP runtime, routing, application dispatch, error handling, problem details, maintenance mode, CORS, and structured logging.

### 9.2. Express — web, persistence, and I/O

**Direct preset roots:**

- `core/foundation`
- `core/kernel`
- `platform/auth`
- `platform/cli`
  - `platform/errors`
    - `platform/redaction`
  - `platform/redaction`
- `platform/database`
- `platform/database-driver-sqlite`
- `platform/filesystem`
- `platform/hashing`
- `platform/http`
- `platform/http-app`
- `platform/logging`
  - `platform/redaction`
- `platform/migrations`
- `platform/problem-details`
  - `platform/errors`
    - `platform/redaction`
- `platform/routing`
- `platform/secrets`
- `platform/security`
- `platform/session`
- `platform/validation`
- `platform/view`
- `integrations/filesystem-local`

**Capabilities:** HTTP web applications with persistence, validation, filesystem, views, sessions, authentication, password hashing, secret resolution, and web security.

### 9.3. Hybrid — business orchestration and worker workloads

**Direct preset roots:**

- `core/foundation`
- `core/kernel`
- `platform/cli`
  - `platform/errors`
    - `platform/redaction`
  - `platform/redaction`
- `platform/database`
- `platform/database-driver-sqlite`
- `platform/events`
- `platform/http`
- `platform/http-app`
- `platform/logging`
  - `platform/redaction`
- `platform/migrations`
- `platform/problem-details`
  - `platform/errors`
    - `platform/redaction`
- `platform/queue`
- `platform/routing`
- `platform/validation`
- `platform/worker`

**Capabilities:** HTTP/API applications with persistence, workers, events, and queue processing. Background execution requires a configured async-capable driver.

### 9.4. Enterprise — operations, observability and governance

**Direct preset roots:**

- `core/foundation`
- `core/kernel`
- `platform/auth`
- `platform/cli`
  - `platform/errors`
    - `platform/redaction`
  - `platform/redaction`
- `platform/database`
- `platform/database-driver-sqlite`
- `platform/events`
- `platform/health`
- `platform/http`
- `platform/http-app`
- `platform/logging`
  - `platform/redaction`
- `platform/metrics`
- `platform/migrations`
- `platform/observability`
- `platform/problem-details`
  - `platform/errors`
    - `platform/redaction`
- `platform/queue`
- `platform/routing`
- `platform/secrets`
- `platform/tracing`
- `platform/validation`
- `platform/worker`
- `enterprise/audit`

**Capabilities:** HTTP/API and worker workloads with authentication, authorization, health checks, observability instrumentation, secret management, and auditing. Specialized governance and infrastructure integrations are opt-in.

### 9.5. Opt-in packages and their mode eligibility

Optional packages MAY be enabled in compatible modes after implementation, subject to module dependencies, conflicts, and configuration requirements.

The `micro+`, `express+`, and `hybrid+` labels indicate the earliest intended compatible mode. They do not change canonical default compositions.

**Optional runtime modules:**

- `platform/ai` — `express+`.
- `platform/ai-guardrails` — `express+`.
- `platform/ai-vectorstore` — `express+`.
- `platform/api-versioning` — `micro+`.
- `platform/async` — `hybrid+`.
- `platform/cache` — `micro+`.
- `platform/cqrs` — `hybrid+`.
- `platform/encryption` — `micro+`.
- `platform/etl` — `hybrid+`.
- `platform/event-sourcing` — `hybrid+`.
- `platform/features` — `micro+`.
- `platform/graphql` — `micro+`.
- `platform/grpc` — `micro+`.
- `platform/http-client` — `micro+`.
- `platform/inbox` — `hybrid+`.
- `platform/lock` — `micro+`.
- `platform/mail` — `express+`.
- `platform/orm` — `express+`.
- `platform/outbox` — `hybrid+`.
- `platform/reactive` — `micro+`.
- `platform/scheduler` — `hybrid+`.
- `platform/search` — `express+`.
- `platform/streaming` — `micro+`.
- `platform/translation` — `express+`.
- `platform/uploads` — `express+`.
- `platform/webhooks` — `express+`.
- `platform/websocket` — `micro+`.

**Database drivers:**

- `platform/database-driver-mariadb` — `express+`.
- `platform/database-driver-mysql` — `express+`.
- `platform/database-driver-pgsql` — `express+`.
- `platform/database-driver-sqlserver` — `express+`.

**Infrastructure and runtime adapters:**

- `integrations/cache-apcu` — `micro+`.
- `integrations/cache-redis` — `micro+`.
- `integrations/lock-redis` — `micro+`.
- `integrations/queue-redis` — `hybrid+`.
- `integrations/runtime-frankenphp` — `micro+`.
- `integrations/runtime-roadrunner` — `micro+`.
- `integrations/runtime-swoole` — `micro+`.
- `integrations/session-redis` — `express+`.
- `integrations/tracing-zipkin` — `micro+`.

**Provider and protocol adapters:**

- `integrations/mail-smtp` — `express+`.
- `integrations/metrics-prometheus` — `micro+`.
- `integrations/rate-limit-redis` — `micro+`.
- `integrations/search-elastic` — `express+`.
- `integrations/search-meilisearch` — `express+`.
- `integrations/search-opensearch` — `express+`.
- `integrations/secrets-aws` — `express+`.
- `integrations/secrets-gcp` — `express+`.
- `integrations/secrets-vault` — `express+`.
- `integrations/tracing-otlp` — `micro+`.
- `integrations/view-blade` — `express+`.
- `integrations/view-twig` — `express+`.
- `integrations/virus-scan-clamav` — `express+`.

**Enterprise extensions:**

- `enterprise/compliance` — `enterprise`.
- `enterprise/policy-engine` — `enterprise`.
- `enterprise/scim` — `enterprise`.
- `enterprise/sso-oidc` — `enterprise`.
- `enterprise/sso-saml` — `enterprise`.
- `enterprise/tenancy` — `enterprise`.

> Addons MUST satisfy their runtime dependencies and conflicts. Driver packages require their owning platform capabilities. Enabling addons MUST NOT modify the canonical default compositions.

### 9.6. Custom — explicit consumer-owned composition

`custom` is an application-owned mode composition defined under `<applicationRoot>/<kernel.modes.overrides_path>/*.php`.

- MUST use a safe, non-canonical preset name.
- MUST follow the canonical schema-version-1 `required` / `modules` policy.
- MUST select only implemented runtime modules.
- MUST pass Kernel module dependency and conflict validation.
- MUST NOT override the canonical `micro`, `express`, `hybrid`, or `enterprise` presets.

> For the complete package catalog, Composer identities, and package responsibilities, see §10.

---

## 10) Full package catalog

> Canonical catalog of Coretsia package identities and ownership. Mode composition and addon eligibility are defined in §9.

- Layered package IDs and Composer naming MUST follow §0.1.
- Each package MUST have one canonical identity and a clearly defined responsibility.
- Catalog membership MUST NOT imply Composer installation or runtime module activation.
- Runtime module selection MUST use validated Composer metadata and Kernel module planning.

### 10.1. Core packages

| Package ID           | Composer identity             | Responsibility                                         |
|----------------------|-------------------------------|--------------------------------------------------------|
| `core/contracts`     | `coretsia/core-contracts`     | Cross-package ports and public contracts               |
| `core/dto-attribute` | `coretsia/core-dto-attribute` | DTO attribute library                                  |
| `core/foundation`    | `coretsia/core-foundation`    | Container, tags, context, deterministic primitives     |
| `core/kernel`        | `coretsia/core-kernel`        | Boot, module planning, feature bundles, artifacts, UoW |

### 10.2. Platform packages

| Package ID                           | Composer identity                             | Responsibility                                                 |
|--------------------------------------|-----------------------------------------------|----------------------------------------------------------------|
| `platform/ai`                        | `coretsia/platform-ai`                        | AI service capability                                          |
| `platform/ai-guardrails`             | `coretsia/platform-ai-guardrails`             | AI policy/guardrails extension                                 |
| `platform/ai-vectorstore`            | `coretsia/platform-ai-vectorstore`            | Vector-store capability for AI                                 |
| `platform/api-versioning`            | `coretsia/platform-api-versioning`            | API versioning and selection                                   |
| `platform/async`                     | `coretsia/platform-async`                     | Optional async primitives                                      |
| `platform/auth`                      | `coretsia/platform-auth`                      | Authentication and RBAC/REBAC authorization                    |
| `platform/cache`                     | `coretsia/platform-cache`                     | Caching abstraction and reference stores                       |
| `platform/cli`                       | `coretsia/platform-cli`                       | CLI commands and feature-bundle management                     |
| `platform/cqrs`                      | `coretsia/platform-cqrs`                      | Command/query orchestration                                    |
| `platform/database`                  | `coretsia/platform-database`                  | Database abstraction and connection management                 |
| `platform/database-driver-mariadb`   | `coretsia/platform-database-driver-mariadb`   | Alternative MariaDB SQL driver                                 |
| `platform/database-driver-mysql`     | `coretsia/platform-database-driver-mysql`     | Alternative MySQL SQL driver                                   |
| `platform/database-driver-pgsql`     | `coretsia/platform-database-driver-pgsql`     | Alternative PostgreSQL SQL driver                              |
| `platform/database-driver-sqlite`    | `coretsia/platform-database-driver-sqlite`    | SQLite reference database driver                               |
| `platform/database-driver-sqlserver` | `coretsia/platform-database-driver-sqlserver` | Alternative SQL Server driver                                  |
| `platform/encryption`                | `coretsia/platform-encryption`                | Encryption services                                            |
| `platform/errors`                    | `coretsia/platform-errors`                    | Canonical Throwable normalization and reporting boundary       |
| `platform/etl`                       | `coretsia/platform-etl`                       | ETL execution facilities                                       |
| `platform/event-sourcing`            | `coretsia/platform-event-sourcing`            | Event-sourcing persistence model                               |
| `platform/events`                    | `coretsia/platform-events`                    | Events and deferred dispatch                                   |
| `platform/features`                  | `coretsia/platform-features`                  | Feature management and experiments                             |
| `platform/filesystem`                | `coretsia/platform-filesystem`                | Filesystem service and path safety                             |
| `platform/graphql`                   | `coretsia/platform-graphql`                   | GraphQL transport/integration                                  |
| `platform/grpc`                      | `coretsia/platform-grpc`                      | gRPC transport/integration                                     |
| `platform/hashing`                   | `coretsia/platform-hashing`                   | Password/data hashing services                                 |
| `platform/health`                    | `coretsia/platform-health`                    | Health checks and owned endpoints                              |
| `platform/http`                      | `coretsia/platform-http`                      | HTTP transport, middleware, rate limiting and caching policies |
| `platform/http-app`                  | `coretsia/platform-http-app`                  | HTTP application dispatch wiring                               |
| `platform/http-client`               | `coretsia/platform-http-client`               | Outbound HTTP client                                           |
| `platform/inbox`                     | `coretsia/platform-inbox`                     | Inbox deduplication and delivery handling                      |
| `platform/lock`                      | `coretsia/platform-lock`                      | Application locking primitives                                 |
| `platform/logging`                   | `coretsia/platform-logging`                   | Logging pipeline with redaction                                |
| `platform/mail`                      | `coretsia/platform-mail`                      | Mail transport abstraction and dispatch                        |
| `platform/metrics`                   | `coretsia/platform-metrics`                   | Metrics validation, instrumentation and recording              |
| `platform/migrations`                | `coretsia/platform-migrations`                | Schema migrations and execution                                |
| `platform/observability`             | `coretsia/platform-observability`             | Observability composition and integration                      |
| `platform/orm`                       | `coretsia/platform-orm`                       | Object-relational mapping                                      |
| `platform/outbox`                    | `coretsia/platform-outbox`                    | Transactional outbox integration                               |
| `platform/problem-details`           | `coretsia/platform-problem-details`           | RFC 9457 JSON and HTML error responses                         |
| `platform/queue`                     | `coretsia/platform-queue`                     | Queue dispatch and synchronous/asynchronous execution          |
| `platform/reactive`                  | `coretsia/platform-reactive`                  | Reactive application primitives                                |
| `platform/redaction`                 | `coretsia/platform-redaction`                 | Shared sensitive-data redaction                                |
| `platform/routing`                   | `coretsia/platform-routing`                   | Routing and route resolution                                   |
| `platform/scheduler`                 | `coretsia/platform-scheduler`                 | Local and distributed scheduling                               |
| `platform/search`                    | `coretsia/platform-search`                    | Search abstraction                                             |
| `platform/secrets`                   | `coretsia/platform-secrets`                   | Secret resolution and safe access                              |
| `platform/security`                  | `coretsia/platform-security`                  | CSRF, signing and security policies                            |
| `platform/session`                   | `coretsia/platform-session`                   | Session state management                                       |
| `platform/streaming`                 | `coretsia/platform-streaming`                 | Stream-oriented transport primitives                           |
| `platform/tracing`                   | `coretsia/platform-tracing`                   | Trace context, sampling and instrumentation                    |
| `platform/translation`               | `coretsia/platform-translation`               | Translations and locale support                                |
| `platform/uploads`                   | `coretsia/platform-uploads`                   | Multipart upload handling and quarantine                       |
| `platform/validation`                | `coretsia/platform-validation`                | Validation engine and rule composition                         |
| `platform/view`                      | `coretsia/platform-view`                      | View rendering abstraction                                     |
| `platform/webhooks`                  | `coretsia/platform-webhooks`                  | Webhook handling and delivery                                  |
| `platform/websocket`                 | `coretsia/platform-websocket`                 | WebSocket transport and events                                 |
| `platform/worker`                    | `coretsia/platform-worker`                    | Long-running worker runtime lifecycle                          |

### 10.3. Integration packages

| Package ID                        | Composer identity                          | Responsibility                    |
|-----------------------------------|--------------------------------------------|-----------------------------------|
| `integrations/cache-apcu`         | `coretsia/integrations-cache-apcu`         | APCu cache backend                |
| `integrations/cache-redis`        | `coretsia/integrations-cache-redis`        | Redis cache backend               |
| `integrations/filesystem-local`   | `coretsia/integrations-filesystem-local`   | Reference local filesystem driver |
| `integrations/lock-redis`         | `coretsia/integrations-lock-redis`         | Redis distributed lock backend    |
| `integrations/mail-smtp`          | `coretsia/integrations-mail-smtp`          | Reference SMTP mail transport     |
| `integrations/metrics-prometheus` | `coretsia/integrations-metrics-prometheus` | Prometheus exporter/adapter       |
| `integrations/queue-redis`        | `coretsia/integrations-queue-redis`        | Redis queue backend               |
| `integrations/rate-limit-redis`   | `coretsia/integrations-rate-limit-redis`   | Redis rate-limit state backend    |
| `integrations/runtime-frankenphp` | `coretsia/integrations-runtime-frankenphp` | FrankenPHP runtime adapter        |
| `integrations/runtime-roadrunner` | `coretsia/integrations-runtime-roadrunner` | RoadRunner runtime adapter        |
| `integrations/runtime-swoole`     | `coretsia/integrations-runtime-swoole`     | Swoole runtime adapter            |
| `integrations/search-elastic`     | `coretsia/integrations-search-elastic`     | Elasticsearch search provider     |
| `integrations/search-meilisearch` | `coretsia/integrations-search-meilisearch` | Meilisearch provider              |
| `integrations/search-opensearch`  | `coretsia/integrations-search-opensearch`  | OpenSearch provider               |
| `integrations/secrets-aws`        | `coretsia/integrations-secrets-aws`        | AWS secrets provider adapter      |
| `integrations/secrets-gcp`        | `coretsia/integrations-secrets-gcp`        | GCP secrets provider adapter      |
| `integrations/secrets-vault`      | `coretsia/integrations-secrets-vault`      | Vault secrets backend             |
| `integrations/session-redis`      | `coretsia/integrations-session-redis`      | Redis session backend             |
| `integrations/tracing-otlp`       | `coretsia/integrations-tracing-otlp`       | OTLP tracing exporter/adapter     |
| `integrations/tracing-zipkin`     | `coretsia/integrations-tracing-zipkin`     | Optional Zipkin tracing adapter   |
| `integrations/view-blade`         | `coretsia/integrations-view-blade`         | Blade view engine adapter         |
| `integrations/view-twig`          | `coretsia/integrations-view-twig`          | Twig view engine adapter          |
| `integrations/virus-scan-clamav`  | `coretsia/integrations-virus-scan-clamav`  | ClamAV upload-scanning adapter    |

### 10.4. Enterprise packages

| Package ID                 | Composer identity                   | Responsibility                        |
|----------------------------|-------------------------------------|---------------------------------------|
| `enterprise/audit`         | `coretsia/enterprise-audit`         | Audit logging and trails              |
| `enterprise/compliance`    | `coretsia/enterprise-compliance`    | Compliance policies and gates         |
| `enterprise/policy-engine` | `coretsia/enterprise-policy-engine` | Enterprise policy engine              |
| `enterprise/scim`          | `coretsia/enterprise-scim`          | SCIM user provisioning extension      |
| `enterprise/sso-oidc`      | `coretsia/enterprise-sso-oidc`      | OIDC single-sign-on extension         |
| `enterprise/sso-saml`      | `coretsia/enterprise-sso-saml`      | SAML single-sign-on extension         |
| `enterprise/tenancy`       | `coretsia/enterprise-tenancy`       | Tenant isolation and context policies |

### 10.5. Development tools

| Package ID                  | Composer identity                    | Responsibility                      |
|-----------------------------|--------------------------------------|-------------------------------------|
| `devtools/admin-panel`      | `coretsia/devtools-admin-panel`      | Administration tooling              |
| `devtools/api-docs`         | `coretsia/devtools-api-docs`         | API documentation tooling           |
| `devtools/dev-tools`        | `coretsia/devtools-dev-tools`        | Developer experience utilities      |
| `devtools/internal-toolkit` | `coretsia/devtools-internal-toolkit` | Repository development toolkit      |
| `devtools/preload`          | `coretsia/devtools-preload`          | Preloading and optimization tooling |
| `devtools/scaffolding`      | `coretsia/devtools-scaffolding`      | Scaffolding and generators          |

### 10.6. Composer distributions

| Composer identity                    | Repository path                       | Responsibility               |
|--------------------------------------|---------------------------------------|------------------------------|
| `coretsia/framework`                 | `packages/framework/`                 | Framework metapackage        |
| `coretsia/presets-preset-enterprise` | `packages/presets/preset-enterprise/` | Enterprise dependency preset |
| `coretsia/presets-preset-express`    | `packages/presets/preset-express/`    | Express dependency preset    |
| `coretsia/presets-preset-hybrid`     | `packages/presets/preset-hybrid/`     | Hybrid dependency preset     |
| `coretsia/presets-preset-micro`      | `packages/presets/preset-micro/`      | Micro dependency preset      |
| `coretsia/skeleton`                  | `packages/applications/skeleton/`     | Application skeleton         |

> Composer distributions MUST NOT be treated as runtime modules or replace Kernel mode presets.
