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

# Структура з **повним каталогом пакетів** (Non-product doc)

- Структура розбита по шарах (Core / Platform / Integrations / Enterprise / DevTools / Presets) +
  **правила найменування та залежностей**, щоб модульність була реальною, а не “залежності всюди”.

---

## 0) Канонічні правила структури (фіксуємо раз і назавжди)

### 0.0. Packaging strategy (canonical reference)

- Усі правила `path ↔ package_id ↔ composer ↔ namespace`, publishable units та versioning **MUST** відповідати:
  - `docs/architecture/PACKAGING.md`

### 0.1. Імена та відповідність “папка ↔ package_id ↔ composer ↔ namespace”

- Package path **MUST**: `framework/packages/<layer>/<slug>/`
- Package id **MUST**: `<layer>/<slug>` (напр. `platform/http-client`)
- Composer package **MUST**: `coretsia/<layer>-<slug>` (напр. `coretsia/platform-http-client`)
- Slug uniqueness policy (single-choice):
  - slug **MUST** бути унікальним в межах одного layer
  - slug **MAY** повторюватися між layers (унікальність забезпечує `<layer>-` префікс у composer name)
- Namespace mapping **MUST** be deterministic (single-choice; core exception):
  - For `core/*` packages:
    - `Coretsia\<StudlyCase(slug)>\...` (напр. `core/kernel` → `Coretsia\Kernel\...`)
  - For non-core packages (`platform/*`, `integrations/*`, `enterprise/*`, `devtools/*`, `presets/*`):
    - `Coretsia\<StudlyCase(layer)>\<StudlyCase(slug)>\...`
    - (напр. `platform/http-client` → `Coretsia\Platform\HttpClient\...`)
  - `src/` → відповідний root namespace, `tests/` → `...\Tests\...`
- Collision safety (MUST):
  - slugs `Core`, `Platform`, `Integrations`, `Devtools` **MUST NOT** бути використані як slugs у `core/*`.
- Versioning **MUST** be monorepo-wide через repo tags `vMAJOR.MINOR.PATCH`; per-package versions **MUST NOT**.

> Примітка: tooling-only libs, що живуть поза `framework/packages/**` (напр. `framework/tools/**`),
> не є publishable units і можуть мати окремі правила (див. `docs/architecture/PACKAGING.md`).

### 0.2. Core vs Platform vs Integrations — жорстке розділення

- **Core**: мінімум, на ньому стоїть все (contracts/foundation/kernel).
- **Platform**: “вбудовані” можливості фреймворку (http/db/queue/security/…).
- **Integrations**: все, що тягне конкретні драйвери/вендори (redis/prometheus/s3/otlp/smtp/…).
- **DevTools**: інструменти DX (debugbar/scaffolding/admin/api-docs профайлинг).
- **Enterprise**: політики/пакети “строгого стеку” (audit/compliance/tenancy/sso/…).
- **Presets**: composer convenience packages (підтягують залежності), не замінюють runtime modes.

### 0.3. Пакети фреймворку

- Кожен пакет самодостатній: `src/`, `config/`, `tests/`, `composer.json`, `README.md`
- Пакети **не тягнуть додаток**: ніяких `apps/*`, ніяких “проектних” конфігів усередині `framework/`.

### 0.4. Skeleton (додаток)

- Все, що пише користувач: `apps/`, `modules/`, `config/`, `resources/`, `var/`, `tests/`
- `config/` у skeleton — **тільки overrides**, а не дефолти фреймворку
- Дефолти живуть у пакетах (`framework/packages/**/config`), skeleton лише перекриває.

### 0.5. “contracts” — єдине джерело істини для портів

- `core/contracts` — централізовані порти (Module/Config/Env/Bus/Observability/Storage/Security/Runtime…)
- Реалізації — в конкретних пакетах (`integrations/*`, `platform/*`).
- Мінімізуємо “Contracts у пакетах”. Якщо дуже треба — тільки приватні внутрішні інтерфейси, не для cross-package API.
- Якщо для capability існує достатній external standard port (напр. `Psr\SimpleCache\CacheInterface`), `core/contracts` **MAY NOT** вводити дублюючий порт без окремої framework-specific потреби.

---

## 1) Фінальна структура монорепо (framework + skeleton + tooling)

Канонічні repo roots (Prelude):

- `framework/` — **tooling workspace root** фреймворку (packages + tools) + workspace runtime state в `framework/var/**`
  - `framework/var/**` містить тимчасові/службові файли tooling (напр. backups), і не є publishable content
- `skeleton/` — **skeleton app workspace root** (sandbox додатка) + runtime state в `skeleton/var/**`
- `.githooks/` — Git hooks, **вмикаються** через `composer setup` (встановлює `git config core.hooksPath .githooks`)

> `framework/packages/` має підшари (core/platform/integrations/enterprise/devtools/presets).
> Composer name **детерміновано** походить від `{layer, slug}` за правилом: `coretsia/<layer>-<slug>`.

**Canonical entrypoints (Prelude):** усе запускається з **repo root** через `composer setup|test|ci`.  
Див. мінімальний сценарій “clean clone → green baseline” у `docs/guides/quickstart.md`.

```txt
coretsia/
├── .githooks/                     # enabled by `composer setup` (core.hooksPath)
├── .github/
│   └── workflows/                 # CI: lint / static / unit / contract / integration
├── docs/
│   ├── adr/                       # ADR-0001..., шаблон ADR
│   ├── architecture/              # порт-адаптер, залежності, модульність, режими
│   ├── guides/                    # quickstart/onboarding/git-hooks/dependency-graph/...
│   └── REPO.md
│
├── framework/
│   ├── var/                       # tooling workspace state (e.g. backups); ignored
│   │   └── backups/               # sync tools create backups here (deterministic policy)
│   ├── packages/
│   │   ├── core/
│   │   │   ├── contracts/         # coretsia/core-contracts
│   │   │   ├── foundation/        # coretsia/core-foundation
│   │   │   └── kernel/            # coretsia/core-kernel
│   │   │
│   │   ├── platform/
│   │   │   ├── cli/               # coretsia/platform-cli
│   │   │   ├── http/              # coretsia/platform-http
│   │   │   ├── routing/           # coretsia/platform-routing
│   │   │   ├── errors/            # coretsia/platform-errors
│   │   │   ├── problem-details/   # coretsia/platform-problem-details
│   │   │   ├── http-client/       # coretsia/platform-http-client
│   │   │   ├── database/          # coretsia/platform-database
│   │   │   ├── migrations/        # coretsia/platform-migrations (optional split)
│   │   │   ├── orm/               # coretsia/platform-orm (optional)
│   │   │   ├── cache/             # coretsia/platform-cache
│   │   │   ├── lock/              # coretsia/platform-lock
│   │   │   ├── filesystem/        # coretsia/platform-filesystem
│   │   │   ├── session/           # coretsia/platform-session
│   │   │   ├── auth/              # coretsia/platform-auth
│   │   │   ├── security/          # coretsia/platform-security
│   │   │   ├── rate-limit/        # coretsia/platform-rate-limit (optional split)
│   │   │   ├── events/            # coretsia/platform-events
│   │   │   ├── cqrs/              # coretsia/platform-cqrs
│   │   │   ├── outbox/            # coretsia/platform-outbox
│   │   │   ├── inbox/             # coretsia/platform-inbox
│   │   │   ├── event-sourcing/    # coretsia/platform-event-sourcing
│   │   │   ├── queue/             # coretsia/platform-queue
│   │   │   ├── scheduler/         # coretsia/platform-scheduler
│   │   │   ├── etl/               # coretsia/platform-etl
│   │   │   ├── logging/           # coretsia/platform-logging
│   │   │   ├── metrics/           # coretsia/platform-metrics
│   │   │   ├── tracing/           # coretsia/platform-tracing
│   │   │   ├── health/            # coretsia/platform-health
│   │   │   ├── observability/     # coretsia/platform-observability (meta/bundle)
│   │   │   ├── feature-flags/     # coretsia/platform-feature-flags
│   │   │   ├── validation/        # coretsia/platform-validation
│   │   │   ├── view/              # coretsia/platform-view
│   │   │   ├── translation/       # coretsia/platform-translation
│   │   │   ├── mail/              # coretsia/platform-mail
│   │   │   ├── reactive/          # coretsia/platform-reactive (optional)
│   │   │   ├── websocket/         # coretsia/platform-websocket (optional)
│   │   │   ├── graphql/           # coretsia/platform-graphql (optional)
│   │   │   ├── grpc/              # coretsia/platform-grpc (optional)
│   │   │   ├── openapi/           # coretsia/platform-openapi (optional)
│   │   │   └── profiling/         # coretsia/platform-profiling (optional)
│   │   │
│   │   ├── integrations/
│   │   │   ├── cache-redis/       # coretsia/integrations-cache-redis
│   │   │   ├── cache-apcu/        # coretsia/integrations-cache-apcu
│   │   │   ├── lock-redis/        # coretsia/integrations-lock-redis
│   │   │   ├── queue-redis/       # coretsia/integrations-queue-redis
│   │   │   ├── queue-rabbitmq/    # coretsia/integrations-queue-rabbitmq (optional)
│   │   │   ├── filesystem-local/  # coretsia/integrations-filesystem-local
│   │   │   ├── filesystem-s3/     # coretsia/integrations-filesystem-s3
│   │   │   ├── metrics-prometheus/# coretsia/integrations-metrics-prometheus
│   │   │   ├── tracing-otlp/      # coretsia/integrations-tracing-otlp
│   │   │   ├── tracing-zipkin/    # coretsia/integrations-tracing-zipkin
│   │   │   ├── mail-smtp/         # coretsia/integrations-mail-smtp
│   │   │   └── runtime-roadrunner/# coretsia/integrations-runtime-roadrunner (optional)
│   │   │
│   │   ├── enterprise/
│   │   │   ├── bundle/            # coretsia/enterprise-bundle (meta/bundle)
│   │   │   ├── audit/             # coretsia/enterprise-audit (optional split)
│   │   │   ├── tenancy/           # coretsia/enterprise-tenancy
│   │   │   ├── compliance/        # coretsia/enterprise-compliance
│   │   │   └── sso/               # coretsia/enterprise-sso (oidc/saml hooks)
│   │   │
│   │   ├── devtools/
│   │   │   ├── dev-tools/         # coretsia/devtools-dev-tools
│   │   │   ├── scaffolding/       # coretsia/devtools-scaffolding
│   │   │   ├── admin-panel/       # coretsia/devtools-admin-panel
│   │   │   └── api-docs/          # coretsia/devtools-api-docs
│   │   │
│   │   └── presets/
│   │       ├── preset-micro/      # coretsia/presets-preset-micro
│   │       ├── preset-express/    # coretsia/presets-preset-express
│   │       ├── preset-hybrid/     # coretsia/presets-preset-hybrid
│   │       └── preset-enterprise/ # coretsia/presets-preset-enterprise
│   │
│   ├── tools/
│   │   ├── cs/                    # ecs/php-cs-fixer config
│   │   ├── phpstan/               # phpstan.neon + baselines
│   │   ├── rector/
│   │   ├── testing/               # phpunit.xml templates, infection, deptrac rules
│   │   └── build/                 # release scripts, package discovery, manifest generators
│   └── composer.json              # framework workspace (managed repos + scripts)
│
├── skeleton/
│   ├── apps/
│   │   ├── web/
│   │   │   ├── public/            # index.php
│   │   │   ├── bootstrap/         # app entry bootstrap
│   │   │   └── config/            # per-app overrides (optional)
│   │   ├── api/
│   │   │   ├── public/
│   │   │   ├── bootstrap/
│   │   │   └── config/
│   │   ├── console/
│   │   │   ├── bootstrap/
│   │   │   └── config/
│   │   └── worker/
│   │       ├── bootstrap/
│   │       └── config/
│   │
│   ├── modules/                   # DDD bounded contexts (user code)
│   ├── config/                    # root overrides (shared)
│   │   ├── modes/                 # express/hybrid/enterprise/micro presets for kernel
│   │   └── environments/          # local/staging/production overrides (optional)
│   ├── bootstrap/                 # shared paths + bootstrap helpers
│   ├── resources/
│   │   ├── views/
│   │   ├── lang/
│   │   └── assets/
│   ├── var/                       # skeleton runtime state (cache/logs/tmp/...)
│   │   ├── cache/
│   │   ├── cache-data/
│   │   ├── etl/
│   │   ├── locks/
│   │   ├── logs/
│   │   ├── maintenance/
│   │   ├── quarantine/
│   │   ├── sessions/
│   │   └── tmp/
│   ├── bin/
│   ├── tests/
│   │   ├── Fixtures/
│   │   ├── Integration/
│   │   └── Contract/
│   ├── .env.example
│   └── composer.json
│
└── composer.json                  # monorepo root (managed repos + canonical entrypoints)
```

---

## 2) Канонічний шаблон будь-якого framework-пакета

> Мета: будь-який пакет можна “включити як модуль”, отримати дефолтний конфіг, правила, теги, команди, міграції,
> ресурси.

```txt
framework/packages/<layer>/<slug>/
├── src/
│   ├── Module/                    # <Xxx>Module (ModuleInterface)
│   ├── Provider/                  # <Xxx>ServiceProvider
│   ├── Contracts/                 # ТІЛЬКИ якщо внутрішні, не cross-package API
│   ├── Console/                   # CLI commands (якщо пакет додає свої)
│   ├── Http/                      # middleware/handlers (якщо релевантно)
│   └── ...
├── config/
│   ├── <slug>.php                 # defaults (subtree; без wrapper root)
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
│   └── Integration/               # тільки якщо пакет має свою інтеграцію
├── README.md
└── composer.json
```

**Обов’язкові домовленості в пакеті**

- `src/Module/*Module.php` експортує:
  - id/version/deps/providers
  - defaults config path (для ConfigKernel)
  - optional: migrations/resources

- `src/Provider/*ServiceProvider.php`:
  - DI bindings
  - tags (health/middleware/exporters/commands)
- `config/<slug>.php` повертає **subtree** (без повторення wrapper root).

---

## 3) “contracts” як реальний центр: фінальна структура підпапок

```txt
framework/packages/core/contracts/src/
├── Module/
├── Config/
├── Env/
├── Container/
├── Bus/
├── Events/
├── Observability/
├── Security/
├── Storage/
├── Database/          # ports, events contracts (не реалізації)
├── Queue/
├── Scheduler/
├── Etl/
├── Http/              # лише порти
└── Runtime/           # optional (loop/async primitives)
```

**Правило:** якщо щось потенційно потрібно **двом+ пакетам** — воно має шанс жити в `contracts`.
**Антиправило:** не тягнути в `contracts` “конкретику” (PDO/Redis/S3/Prometheus).

---

## 4) Пакети-адаптери (integrations): канонічне найменування

Формула:

- layer = `integrations`
- slug = `<capability>-<vendor>` (або `-<driver>`)
- composer = `coretsia/integrations-<slug>`

де capability ∈ `cache|queue|filesystem|metrics|tracing|mail|runtime|lock`.

Приклади (всі в `framework/packages/integrations/`):

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

**Жорстке правило залежностей:** integrations може залежати від platform, але platform **ніколи** не залежить від integrations.

---

## 5) Preset пакети vs Mode presets (щоб не плутати)

### 5.1. Mode presets (kernel runtime planning)

- Живуть у skeleton: `skeleton/config/modes/*.php`
- Впливають на **план модулів** (required/optional/disabled + bundles типу `observability=minimal`)

### 5.2. Preset пакети (composer dependency convenience)

- Живуть у framework: `framework/packages/presets/preset-*/`
- Роблять “composer require coretsia/presets-preset-express” → підтягується рекомендований dependency set для відповідного release line
- **Не замінюють** mode presets. Вони лише ставлять залежності.
- Preset package **MUST** бути phase-consistent з `ROADMAP.md`:
  - `preset-micro` — тільки `micro` baseline
  - `preset-express` — `micro` + required `express` additions
  - `preset-hybrid` — `express` + `hybrid` additions
  - `preset-enterprise` — `hybrid` + `enterprise` additions
- Якщо release line містить `SHOULD` packages (напр. `platform/cache` у `express`), canonical preset:
  - **MUST** чітко відрізняти required baseline від later-optional additions,
  - **MUST NOT** неявно робити optional package частиною required mode definition без окремої policy note,
  - **MAY** мати окремі convenience variants / extras для richer distribution.

---

## 6) Skeleton: канонічний шаблон DDD-модуля (user code)

Щоб `modules/` не став “кучею класів”, фіксуємо структуру bounded context:

```txt
skeleton/modules/<ContextName>/
├── src/
│   ├── Domain/                    # Entities, VOs, Domain Events, Specs
│   ├── Application/               # Use-cases, Commands/Queries, Handlers
│   ├── Infrastructure/            # DB repos, integrations, adapters
│   └── Presentation/              # Controllers/Endpoints (optional)
├── config/
│   └── <context>.php              # overrides only (якщо треба)
├── database/
│   └── migrations/                # optional, якщо модуль має схему
├── resources/
│   ├── views/                     # optional
│   └── lang/                      # optional
├── tests/
│   ├── Unit/
│   └── Integration/
├── <Context>Module.php            # реалізує ModuleInterface (user module)
└── README.md
```

**Канали інтеграції між модулями:**

- domain events
- query bus (read model)
- ports (інтерфейси з contracts, реалізації — через DI)
- (опц) outbox/inbox для гарантій доставки

---

## 7) Правила залежностей між шарами (щоб не було “залежності всюди”)

Ось простий “закон”:

- **Core** (`contracts`, `foundation`, `kernel`)
  → не залежить ні від чого “вище” (окрім PSR/стандартів).
- **Platform**
  → залежить від Core (+ інколи один від одного, але без циклів).
- **Integrations**
  → залежить від Platform + Core, але Platform ніколи не залежить від Integrations.
- **DevTools**
  → залежить від Platform (http/observability/routing/db), але не навпаки.
- **Enterprise**
  → може залежати від Platform/Integrations, але це “верхній шар”.

Міні-матриця дозволів:

| From \ To    | Core | Platform | Integrations |   DevTools | Enterprise |
|--------------|-----:|---------:|-------------:|-----------:|-----------:|
| Core         |    ✅ |        ❌ |            ❌ |          ❌ |          ❌ |
| Platform     |    ✅ |       ✅* |            ❌ |          ❌ |          ❌ |
| Integrations |    ✅ |        ✅ |            ✅ |          ❌ |          ❌ |
| DevTools     |    ✅ |        ✅ |   ✅ (інколи) |          ✅ |          ❌ |
| Enterprise   |    ✅ |        ✅ |            ✅ | ✅ (інколи) |          ✅ |

- Platform→Platform дозволено тільки dependency-safe (kernel не тягне http/db і т.д.)
- Platform ніколи не тягне DevTools/Enterprise
- DevTools можуть тягнути Platform (+ інколи Integrations), але це односторонньо

---

## 8) Маленькі “дотяжки”, щоб структура була завершеною

1. **Єдине місце для “build artifacts” у runtime**

- skeleton: `var/cache`, `var/logs`, `var/tmp`, `var/quarantine`
- kernel artifacts: `var/cache/module-manifest.php`, `var/cache/config.php`, `var/cache/container.php` (stub/пізніше)

2. **Єдині правила конфігів**

- defaults — тільки в пакетах (`framework/packages/**/config`)
- overrides — тільки в skeleton (`skeleton/config`, `skeleton/apps/*/config`, `skeleton/modules/*/config`)
- валідатори/metadata — `config/rules.php` (пакет) + explain/source tracking у kernel

3. **Пакет завжди “включається” через Module + Provider**

- навіть якщо пакет “малий”: це рятує від хаосу з реєстраціями

---

## 9) Остаточна мапа режимів — `micro | express | hybrid | enterprise | custom`

> **Канонічне правило:** `ROADMAP.md` визначає, **коли** capability входить у систему; цей розділ описує, **як** capability групуються на рівні runtime modes.  
> Mode map у цьому документі **MUST NOT** суперечити `ROADMAP.md` по фазі доступності capability.
>
> **Таксономія опису (single-choice):**
> - **Required** — мінімально необхідний payload режиму;
> - **Adds** — capability, які цей режим додає поверх попереднього;
> - **Optional / later addons** — не є mode-defining minimum і можуть з’являтися пізніше в тій самій фазі або в наступних фазах.

### Custom

- `custom` — це user-defined mode preset (`skeleton/config/modes/*.php`), який:
  - базується на тих самих правилах `required|optional|disabled`,
  - **MUST NOT** вимагати capability, яких ще немає в roadmap-фазі/встановлених пакетах,
  - **MAY** збирати вузькі сценарії (`api-gateway`, `backoffice`, `worker-only`, `admin-only`, ...).

### Micro

**Required:**

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

**Runtime baseline (cemented by roadmap):**

- real HTTP runtime
- router + http-app wiring
- web app entrypoint
- fallback router + system endpoints
- maintenance mode
- CORS

**Optional / later addons inside the same release line:**

- request-id policy refinements
- response hardening
- method override
- content negotiation
- HTML problem-details renderer
- dev diagnostics endpoints

> `micro` — перший чесний production-safe HTTP режим, а не “тільки transport shell”.

### Express

**Adds over `micro`:**

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

**Optional / later addons in express line:**

- `platform/http-client`
- `platform/translation`
- rate-limit capability
- encryption capability
- hashing capability
- lock capability
- `platform/cache` (`PSR-16` implementation / manager / reference stores)

> `express` = “web + persistence + IO”.  
> `platform/cache` належить до express release line, але **MUST NOT** вважатися required baseline цього режиму, якщо в roadmap він лишається `SHOULD`.

### Hybrid

**Adds over `express`:**

- secrets capability
- `platform/events`
- deferred events semantics
- `platform/queue`
- queue CLI commands
- command bus capability
- `platform/scheduler`
- `platform/cqrs`

**Optional / scenario-specific additions:**

- DB-backed async queue driver
- stronger authorization engines (RBAC/REBAC), якщо потрібні конкретному preset

> `hybrid` = async/business orchestration mode.  
> `http-client`, `feature-flags`, advanced observability, rate-limit hardening **MUST NOT** визначати цей режим, якщо вони вводяться в інших фазах.

### Enterprise

**Adds over `hybrid`:**

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

**Notes:**

- `enterprise` — це extension-heavy / strict-stack mode, а не базова умова для HTTP або persistence.
- `CQRS` **не** є enterprise-only capability, бо додається на `hybrid`.

### Preset packages vs runtime modes

- Runtime mode preset (`skeleton/config/modes/*.php`) визначає **module plan**.
- Composer preset package (`framework/packages/presets/preset-*`) визначає **dependency convenience set**.
- Preset package **MUST NOT** декларувати capability, яких ще немає у відповідному roadmap release line.
- Рекомендована відповідність:
  - `preset-micro` → required payload `micro`
  - `preset-express` → `micro` + required payload `express`; **MAY** додавати express-line `SHOULD` packages окремими preset variants або extras, але canonical baseline **MUST NOT** змішувати required і later-optional без явного policy note
  - `preset-hybrid` → `express` + adds `hybrid`
  - `preset-enterprise` → `hybrid` + adds `enterprise`

---

## 10) Повний каталог пакетів (DDD-friendly, PSR-first)

Нижче — **максимально повний** набір, який дає “повний стек”, але зберігає мінімальні залежності через **split на
“platform + integrations adapters”**.

### A) Core Runtime / Kernel (незамінне)

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

### G) Observability (розділено правильно)

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

### K) Enterprise Meta (як “комерційний шар” або “strict stack”)

48. `coretsia/enterprise-bundle` (meta/bundle)
49. `coretsia/enterprise-audit` (optional split)
50. `coretsia/enterprise-tenancy`
51. `coretsia/enterprise-compliance`
52. `coretsia/enterprise-sso`
