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
- Namespace mapping MUST be deterministic (single-choice; core exception):
  - For `core/*` packages:
    - `Coretsia\<StudlyCase(slug)>\...` (e.g. `core/kernel` → `Coretsia\Kernel\...`)
  - For non-core packages (`platform/*`, `integrations/*`, `enterprise/*`, `devtools/*`, `presets/*`):
    - `Coretsia\<StudlyCase(layer)>\<StudlyCase(slug)>\...`
    - (e.g. `platform/http-client` → `Coretsia\Platform\HttpClient\...`)
  - `src/` → the corresponding root namespace, `tests/` → `...\Tests\...`
- Collision safety (MUST):
  - For `core/*` packages, the value of `Studly(<slug>)` MUST NOT equal any of:
    - `Core`
    - `Platform`
    - `Integrations`
    - `Enterprise`
    - `Devtools`
    - `Presets`
  - Equivalent forbidden slug values under `core/*` are:
    - `core`
    - `platform`
    - `integrations`
    - `enterprise`
    - `devtools`
    - `presets`
- Versioning MUST be monorepo-wide via repo tags `vMAJOR.MINOR.PATCH`; per-package versions MUST NOT be used.

> Note: tooling-only libs that live outside `packages/**` (for example `tools/**`)
> are not publishable units and may have separate rules (see `docs/architecture/PACKAGING.md`).

### 0.2. Core vs Platform vs Integrations — hard separation

- Core: the minimum everything stands on (contracts/foundation/kernel).
- Platform: the framework’s “built-in” capabilities (http/db/queue/security/…).
- Integrations: everything that pulls concrete drivers/vendors (redis/prometheus/s3/otlp/smtp/…).
- DevTools: DX tools (debugbar/scaffolding/admin/api-docs profiling).
- Enterprise: “strict stack” policies/packages (audit/compliance/tenancy/sso/…).
- Presets: composer convenience packages (they pull dependencies), and do not replace runtime modes.

### 0.3. Layered packages

- Each layered package is self-contained within its package directory; the exact scaffold depends on its package kind and is governed by `docs/architecture/PACKAGING.md`.
- Layered packages do not pull in the application: no `apps/*` and no project-specific configuration inside layered package source trees.

### 0.4. Skeleton distribution and consumer application

- `packages/applications/skeleton/` is the public `coretsia/skeleton` create-project template.
- Template-owned application surfaces may include `apps/`, `modules/`, `config/`, `resources/`, `var/`, and `tests/`.
- `config/` in the skeleton template contains application overrides only, not framework defaults.
- Defaults live in owning runtime packages under `packages/<layer>/<slug>/config`.
- After `composer create-project coretsia/skeleton <app>`, the extracted template root becomes the consumer application root.
- Runtime/package code MUST NOT depend on `packages/applications/skeleton/` or any other monorepo source path.

### 0.5. “contracts” — the single source of truth for ports

- `core/contracts` — centralized ports (Module/Config/Env/Bus/Observability/Storage/Security/Runtime…)
- Implementations live in concrete packages (`integrations/*`, `platform/*`).
- Minimize “Contracts in packages”. If absolutely needed, only private internal interfaces, not cross-package APIs.
- If a capability already has a sufficient external standard port (e.g. `Psr\SimpleCache\CacheInterface`), `core/contracts` MAY NOT introduce a duplicate port without a separate framework-specific need.

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
│   │       ├── composer.json
│   │       ├── .env.example
│   │       ├── config/
│   │       ├── apps/
│   │       └── var/
│   ├── core/
│   ├── platform/
│   ├── integrations/
│   ├── enterprise/
│   ├── devtools/
│   └── presets/
│
├── tools/
│   ├── bin/
│   ├── http/
│   ├── build/
│   ├── release/
│   ├── gates/
│   ├── architecture/
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

> Goal: a runtime package can be included as a module, receive default config, rules, tags, commands, migrations,
> and resources.

```txt
packages/<layer>/<slug>/
├── src/
│   ├── Module/                    # <Xxx>Module (ModuleInterface)
│   ├── Provider/                  # <Xxx>ServiceProvider
│   ├── Contracts/                 # ONLY if internal, not cross-package API
│   ├── Console/                   # CLI commands (if the package adds its own)
│   ├── Http/                      # middleware/handlers (if relevant)
│   └── ...
├── config/
│   ├── <slug>.php                 # defaults (subtree; without wrapper root)
│   ├── rules.php                  # validation/schema/metadata
│   └── deprecations.php           # optional
├── resources/                     # views/lang/assets (optional)
│   ├── views/
│   ├── lang/
│   └── assets/
├── database/
│   └── migrations/                # optional
├── tests/
│   ├── Unit/
│   ├── Contract/
│   └── Integration/               # only if the package has its own integration
├── README.md
└── composer.json
```

Required conventions inside the package

- `src/Module/*Module.php` exports:
  - id/version/deps/providers
  - defaults config path (for ConfigKernel)
  - optional: migrations/resources

- `src/Provider/*ServiceProvider.php`:
  - DI bindings
  - tags (health/middleware/exporters/commands)
- `config/<slug>.php` returns a subtree (without repeating the wrapper root).

---

## 3) “contracts” as the real center: final subfolder structure

```txt
packages/core/contracts/src/
├── Module/
├── Config/
├── Env/
├── Container/
├── Bus/
├── Events/
├── Observability/
├── Security/
├── Storage/
├── Database/          # ports, events contracts (not implementations)
├── Queue/
├── Scheduler/
├── Etl/
├── Http/              # ports only
└── Runtime/           # optional (loop/async primitives)
```

Rule: if something is potentially needed by two or more packages, it has a chance to live in `contracts`.
Anti-rule: do not pull “concrete things” (PDO/Redis/S3/Prometheus) into `contracts`.

---

## 4) Adapter packages (integrations): canonical naming

Formula:

- layer = `integrations`
- slug = `<capability>-<vendor>` (or `-<driver>`)
- composer = `coretsia/integrations-<slug>`

where capability ∈ `cache|queue|filesystem|metrics|tracing|mail|runtime|lock`.

Examples (all under `packages/integrations/`):

- `cache-redis` → `coretsia/integrations-cache-redis`
- `cache-apcu` → `coretsia/integrations-cache-apcu`
- `queue-redis` → `coretsia/integrations-queue-redis`
- `queue-rabbitmq` → `coretsia/integrations-queue-rabbitmq`
- `filesystem-local` → `coretsia/integrations-filesystem-local`
- `filesystem-s3` → `coretsia/integrations-filesystem-s3`
- `metrics-prometheus` → `coretsia/integrations-metrics-prometheus`
- `tracing-otlp` → `coretsia/integrations-tracing-otlp`
- `mail-smtp` → `coretsia/integrations-mail-smtp`
- `runtime-roadrunner` → `coretsia/integrations-runtime-roadrunner`

Hard dependency rule: integrations may depend on platform, but platform must never depend on integrations.

---

## 5) Preset packages vs Mode presets (to avoid confusion)

### 5.1. Mode presets (kernel runtime planning)

- Template source: `packages/applications/skeleton/config/modes/*.php`
- Consumer application path after `create-project`: `config/modes/*.php`
- Affect the module plan (required/optional/disabled + bundles such as `observability=minimal`)

### 5.2. Preset packages (composer dependency convenience)

- Live as layered packages under `packages/presets/preset-*/`
- They make “composer require coretsia/presets-preset-express” pull the recommended dependency set for the corresponding release line
- They do not replace mode presets. They only install dependencies.
- Preset package MUST be phase-consistent with `ROADMAP.md`:
  - `preset-micro` — `micro` baseline only
  - `preset-express` — `micro` + required `express` additions
  - `preset-hybrid` — `express` + `hybrid` additions
  - `preset-enterprise` — `hybrid` + `enterprise` additions
- If the release line contains `SHOULD` packages (for example `platform/cache` in `express`), the canonical preset:
  - MUST clearly distinguish required baseline from later-optional additions,
  - MUST NOT implicitly make an optional package part of the required mode definition without a separate policy note,
  - MAY have separate convenience variants / extras for richer distribution.

---

## 6) Skeleton: canonical DDD module template (user code)

To prevent `modules/` from becoming “a pile of classes”, we fix the bounded context structure:

```txt
packages/applications/skeleton/modules/<ContextName>/
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
├── <Context>Module.php            # implements ModuleInterface (user module)
└── README.md
```

Integration channels between modules:

- domain events
- query bus (read model)
- ports (interfaces from contracts, implementations via DI)
- (optional) outbox/inbox for delivery guarantees

---

## 7) Layer-level dependency direction (conceptual summary)

> Exact direct package-to-package compile-time dependency permissions are defined only in `docs/architecture/DEPENDENCIES.md`. \
> This section summarizes layer direction and MUST NOT be used to infer additional package-level edges.

A useful layer-level model:

- Core (`contracts`, `foundation`, `kernel`)
  → depends on nothing “above” it (except PSR/standards).
- Platform
  → depends on Core (+ sometimes on each other, but without cycles).
- Integrations
  → depend on Platform + Core, but Platform never depends on Integrations.
- DevTools
  → depend on Platform (http/observability/routing/db), but not the other way around.
- Enterprise
  → may depend on Platform/Integrations, but it is the “upper layer”.

Conceptual layer-direction matrix:

| From \ To    | Core | Platform |   Integrations |       DevTools | Enterprise |
|--------------|-----:|---------:|---------------:|---------------:|-----------:|
| Core         |   ✅ |       ❌ |             ❌ |             ❌ |         ❌ |
| Platform     |   ✅ |      ✅* |             ❌ |             ❌ |         ❌ |
| Integrations |   ✅ |       ✅ |             ✅ |             ❌ |         ❌ |
| DevTools     |   ✅ |       ✅ | ✅ (sometimes) |             ✅ |         ❌ |
| Enterprise   |   ✅ |       ✅ |             ✅ | ✅ (sometimes) |         ✅ |

- Platform→Platform is allowed only when dependency-safe (kernel must not pull http/db etc.)
- Platform never pulls DevTools/Enterprise
- DevTools may pull Platform (+ sometimes Integrations), but this is one-way

---

## 8) Small “finishing touches” so the structure is complete

1. A single place for runtime “build artifacts”

- consumer application: `var/cache`, `var/logs`, `var/tmp`, `var/quarantine`
- kernel artifacts: `var/cache/module-manifest.php`, `var/cache/config.php`, `var/cache/container.php` (stub/later)

2. Unified config rules

- defaults — owning runtime packages only (`packages/<layer>/<slug>/config`)
- template overrides — `packages/applications/skeleton/config`, `packages/applications/skeleton/apps/*/config`, `packages/applications/skeleton/modules/*/config`
- consumer overrides after `create-project` — `config`, `apps/*/config`, `modules/*/config`
- validators/metadata — `config/rules.php` (package) + explain/source tracking in kernel

3. A package is always “enabled” through Module + Provider

- even if a package is “small”: this saves you from registration chaos

---

## 9) Final mode map — `micro | express | hybrid | enterprise | custom`

> Canonical rule: `ROADMAP.md` defines when a capability enters the system; this section describes how capabilities are grouped at the runtime mode level. \
> The mode map in this document MUST NOT contradict `ROADMAP.md` on the phase availability of a capability.
>
> Description taxonomy (single-choice):
> - Required — the minimally required payload of the mode;
> - Adds — capabilities this mode adds on top of the previous one;
> - Optional / later addons — not mode-defining minimums and may appear later in the same phase or in later phases.

### Custom

- `custom` — a user-defined mode preset (`config/modes/*.php` in the consumer application; template source under `packages/applications/skeleton/config/modes/*.php`) that:
  - is based on the same `required|optional|disabled` rules,
  - MUST NOT require capabilities that do not yet exist in the roadmap phase / installed packages,
  - MAY assemble narrow scenarios (`api-gateway`, `backoffice`, `worker-only`, `admin-only`, ...).

### Micro

Required:

- `core/contracts`
- `core/foundation`
- `core/kernel`
- `platform/cli`
- `platform/http`
- `platform/routing`
- `platform/errors`
- `platform/problem-details`
- `platform/logging`
- `platform/tracing`
- `platform/metrics`

Runtime baseline (cemented by roadmap):

- real HTTP runtime
- router + http-app wiring
- web app entrypoint
- fallback router + system endpoints
- maintenance mode
- CORS

Optional / later addons inside the same release line:

- request-id policy refinements
- response hardening
- method override
- content negotiation
- HTML problem-details renderer
- dev diagnostics endpoints

> `micro` — the first honest production-safe HTTP mode, not “just a transport shell”.

### Express

Adds over `micro`:

- `platform/validation`
- `platform/filesystem`
- uploads capability
- `platform/database`
- database drivers
- `platform/migrations`
- `platform/mail`
- `integrations/mail-smtp`
- `platform/view`
- `platform/session`
- `platform/auth`
- `platform/security`

Optional / later addons in express line:

- `platform/http-client`
- `platform/translation`
- rate-limit capability
- encryption capability
- hashing capability
- lock capability
- `platform/cache` (`PSR-16` implementation / manager / reference stores)

> `express` = “web + persistence + IO”.  
> `platform/cache` belongs to the express release line, but MUST NOT be considered a required baseline of this mode if it remains `SHOULD` in the roadmap.

### Hybrid

Adds over `express`:

- secrets capability
- `platform/events`
- deferred events semantics
- `platform/queue`
- queue CLI commands
- command bus capability
- `platform/scheduler`
- `platform/cqrs`

Optional / scenario-specific additions:

- DB-backed async queue driver
- stronger authorization engines (RBAC/REBAC), if needed by a specific preset

> `hybrid` = async/business orchestration mode.  
> `http-client`, `feature-flags`, advanced observability, rate-limit hardening MUST NOT define this mode if they are introduced in other phases.

### Enterprise

Adds over `hybrid`:

- `platform/health`
- advanced metrics/tracing integrations
- `platform/observability` glue
- profiling capability
- advanced cache/session/lock/queue integrations
- feature flags / experiments
- outbox / inbox
- optional event-sourcing
- enterprise tenancy / audit / compliance / SSO
- devtools productized extensions
- realtime / protocol extensions
- advanced runtime adapters
- ETL / preload / performance middleware family
- enterprise integrations for secrets/search/AI and related extensions

Notes:

- `enterprise` — an extension-heavy / strict-stack mode, not a baseline condition for HTTP or persistence.
- `CQRS` is not an enterprise-only capability, because it is added at `hybrid`.

### Preset packages vs runtime modes

- Runtime mode preset (`config/modes/*.php` in the consumer application) defines the module plan; template source lives under `packages/applications/skeleton/config/modes/*.php`.
- Composer preset package (`packages/presets/preset-*`) defines the dependency convenience set.
- A preset package MUST NOT declare capabilities that do not yet exist in the corresponding roadmap release line.
- Recommended mapping:
  - `preset-micro` → required payload `micro`
  - `preset-express` → `micro` + required payload `express`; MAY add express-line `SHOULD` packages as separate preset variants or extras, but the canonical baseline MUST NOT mix required and later-optional items without an explicit policy note
  - `preset-hybrid` → `express` + adds `hybrid`
  - `preset-enterprise` → `hybrid` + adds `enterprise`

---

## 10) Full package catalog (DDD-friendly, PSR-first)

Below is the maximally complete set that gives a “full stack”, while still preserving minimal dependencies through a split into
“platform + integrations adapters”.

### A) Core Runtime / Kernel (indispensable)

1. `coretsia/core-kernel`
2. `coretsia/core-contracts`
3. `coretsia/core-foundation`
4. `coretsia/platform-cli`

### B) HTTP / Error Platform

5. `coretsia/platform-http`
6. `coretsia/platform-routing`
7. `coretsia/platform-errors`
8. `coretsia/platform-problem-details`
9. `coretsia/platform-http-client`
10. `coretsia/platform-openapi` (optional)

### C) Data Platform

11. `coretsia/platform-database`
12. `coretsia/platform-migrations` (optional split)
13. `coretsia/platform-orm` (optional)
14. `coretsia/platform-cache`
15. `coretsia/integrations-cache-redis` (adapter)
16. `coretsia/platform-lock`
17. `coretsia/integrations-lock-redis` (adapter)
18. `coretsia/platform-filesystem`

### D) Auth / Security Platform

19. `coretsia/platform-session`
20. `coretsia/platform-auth`
21. `coretsia/platform-security`
22. `coretsia/platform-rate-limit` (optional split)

### E) Application Architecture (DDD/CQRS/ES)

23. `coretsia/platform-events`
24. `coretsia/platform-cqrs`
25. `coretsia/platform-outbox`
26. `coretsia/platform-inbox`
27. `coretsia/platform-event-sourcing`

### F) Async / Scheduling / ETL

28. `coretsia/platform-queue`
29. `coretsia/integrations-queue-redis` (adapter)
30. `coretsia/platform-scheduler`
31. `coretsia/platform-etl`

### G) Observability (properly split)

32. `coretsia/platform-logging`
33. `coretsia/platform-metrics`
34. `coretsia/platform-tracing`
35. `coretsia/platform-health`
36. `coretsia/platform-profiling` (optional)
37. `coretsia/platform-observability` (meta/bundle)

### H) UX / Web product layer (optional)

38. `coretsia/platform-view`
39. `coretsia/platform-translation`
40. `coretsia/platform-validation`
41. `coretsia/platform-mail`
42. `coretsia/integrations-mail-smtp` (adapter)

### I) Feature Management

43. `coretsia/platform-feature-flags`

### J) Reactive / Realtime / Protocols (optional, adapters-first)

44. `coretsia/platform-reactive` (optional)
45. `coretsia/platform-websocket` (optional)
46. `coretsia/platform-graphql` (optional)
47. `coretsia/platform-grpc` (optional)

### K) Enterprise Meta (as a “commercial layer” or “strict stack”)

48. `coretsia/enterprise-bundle` (meta/bundle)
49. `coretsia/enterprise-audit` (optional split)
50. `coretsia/enterprise-tenancy`
51. `coretsia/enterprise-compliance`
52. `coretsia/enterprise-sso`
