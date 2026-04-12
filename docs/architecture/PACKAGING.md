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

# Packaging strategy (monorepo packaging law) (Non-product doc)

> **Canonical / single-choice.**  
> Це — єдине джерело істини для правил пакування монорепо. Будь-які інші документи **MUST** посилатися на цей файл і **MUST NOT** вводити альтернативні правила.

---

## 0) Scope

Цей документ фіксує **одну** канонічну стратегію пакування для монорепо:

- **Package identity**: `path ↔ package_id ↔ composer ↔ namespace`.
- **Publishable units law**: що є publishable (packages) vs non-publishable (tools/skeleton/docs).
- **Versioning**: один release line для всього репозиторію.

---

## 1) Terminology (normative)

- **layer** — верхній шар пакетів у `framework/packages/` (наприклад: `core`, `platform`).
- **slug** — ідентифікатор пакета в межах шару у kebab-case (наприклад: `problem-details`).
- **package_id** — `<layer>/<slug>` (наприклад: `platform/problem-details`).
- **composer name** — `coretsia/<layer>-<slug>` (наприклад: `coretsia/platform-problem-details`).
- **namespace root** — корінь PHP namespace для `src/` та `tests/`.

---

## 2) Canonical package location (MUST)

### 2.1. Package path (single-choice)

Кожен framework-пакет **MUST** жити за шляхом:

- `framework/packages/<layer>/<slug>/`

Відповідно:

- **package_id MUST** бути: `<layer>/<slug>`.
- **composer name MUST** бути: `coretsia/<layer>-<slug>`.

**Examples:**

- `framework/packages/core/contracts/` ↔ `core/contracts` ↔ `coretsia/core-contracts`
- `framework/packages/platform/problem-details/` ↔ `platform/problem-details` ↔ `coretsia/platform-problem-details`

### 2.2. Allowed layers (single-choice)

`<layer>` **MUST** бути одним із:

- `core`
- `platform`
- `integrations`
- `enterprise`
- `devtools`
- `presets`

---

## 3) Slug rules (MUST)

### 3.1. Format (single-choice)

`<slug>` **MUST** бути kebab-case і **MUST** відповідати:

- `/\A[a-z0-9][a-z0-9-]*\z/`

### 3.2. Uniqueness policy (single-choice)

- `<slug>` **MUST** бути унікальним **в межах одного layer**.
- `<slug>` **MAY** повторюватися **між різними layers** (глобальна унікальність забезпечується composer prefix `coretsia/<layer>-...`).

---

## 4) Composer identity mapping (MUST)

Composer package name **MUST** бути похідним **тільки** з `{layer, slug}` за формулою:

- `coretsia/<layer>-<slug>`

**MUST NOT:**

- будь-які інші префікси/неймінг (типу `coretsia/<slug>` без layer),
- “персональні” vendor names для framework packages,
- пер-package версіонування (див. §7).

---

## 5) Deterministic namespace mapping (MUST)

### 5.1. StudlyCase algorithm (single-choice)

Визначаємо `Studly(x)` так:

- `x` розбивається по `-` на токени;
- для кожного токена:
  - перший символ → uppercase,
  - інші символи → lowercase,
  - цифри зберігаються як є;
- токени конкатенуються.

**Examples:**

- `problem-details` → `ProblemDetails`
- `http-client` → `HttpClient`
- `cli` → `Cli`

### 5.2. Rule (single-choice)

Namespaces **MUST** бути детерміновано похідні з `{layer, slug}` — але з **винятком для core** (короткі canonical namespaces).

#### A) Core packages (`core/*`) (single-choice)

Для `core/*` пакетів namespace root **MUST** бути:

- `Coretsia\<Studly(slug)>\...`

**Examples:**

- `core/contracts` → `Coretsia\Contracts\...`
- `core/foundation` → `Coretsia\Foundation\...`
- `core/kernel` → `Coretsia\Kernel\...`

#### B) Non-core packages (`platform/*`, `integrations/*`, `enterprise/*`, `devtools/*`, `presets/*`) (single-choice)

Для non-core пакетів namespace root **MUST** бути:

- `Coretsia\<Studly(layer)>\<Studly(slug)>\...`

**Examples:**

- `platform/cli` → `Coretsia\Platform\Cli\...`
- `platform/problem-details` → `Coretsia\Platform\ProblemDetails\...`
- `integrations/cache-redis` → `Coretsia\Integrations\CacheRedis\...`
- `devtools/cli-spikes` → `Coretsia\Devtools\CliSpikes\...`

### 5.3. Source + tests mapping (MUST)

Для будь-якого пакета:

- `framework/packages/<layer>/<slug>/src` **MUST** мапитись у namespace root (див. §5.2).
- `framework/packages/<layer>/<slug>/tests` **MUST** мапитись у `...\Tests\...` під тим самим root.

**Examples:**

- `framework/packages/core/kernel/src` → `Coretsia\Kernel\...`
- `framework/packages/core/kernel/tests` → `Coretsia\Kernel\Tests\...`
- `framework/packages/platform/problem-details/src` → `Coretsia\Platform\ProblemDetails\...`
- `framework/packages/platform/problem-details/tests` → `Coretsia\Platform\ProblemDetails\Tests\...`

---

## 6) Collision safety (MUST)

Оскільки `core/*` використовує короткий root `Coretsia\<Studly(slug)>`, потрібно запобігти колізіям з non-core layers.

### 6.1. Reserved slugs for `core/*` (MUST)

Оскільки для `core/*` namespace root визначається як `Coretsia\<Studly(slug)>`,
потрібно запобігти колізіям з non-core layers, де namespace root містить layer segment
(наприклад `Coretsia\Platform\...`).

#### Normative rule (single-choice)

Для `core/*` пакетів значення `Studly(<slug>)` **MUST NOT** дорівнювати жодному з:

- `Core`
- `Platform`
- `Integrations`
- `Devtools`

#### Equivalent slug values (derived)

Оскільки `<slug>` **MUST** бути kebab-case (див. §3.1), це правило означає, що
наступні `<slug>` **MUST NOT** бути використані у `core/*`:

- `core`
- `platform`
- `integrations`
- `devtools`

**Rationale:**

- `core/*` має короткі canonical namespaces (`Coretsia\Foundation`, etc.).
- non-core пакети включають layer segment (`Coretsia\Platform\...`) для глобальної унікальності.
- заборона гарантує, що `Coretsia\<Studly(slug)>` не перетнеться з `Coretsia\<Studly(layer)>\...`.

---

## 7) Publishable units law (MUST)

### 7.1. Publishable (single-choice)

**Publishable units** — це **тільки** пакети під:

- `framework/packages/<layer>/<slug>/`

Кожен такий каталог є **одним** Composer package (`coretsia/<layer>-<slug>`).

### 7.2. Non-publishable (single-choice)

Наступні частини репозиторію **MUST NOT** вважатися publishable packages (і **MUST NOT** позиціонуватися як такі):

- `framework/tools/**` — tooling, gates, CI rails, spikes, генератори
- `skeleton/**` — workspace app sandbox, fixtures, runtime caches (`skeleton/var/**`)
- `docs/**` — документація
- repo root файли (`README.md`, `LICENSE`, etc.) — навігація/правила, не packages

> Якщо tooling потребує composer package — він **MUST** бути реалізований як звичайний пакет у `framework/packages/devtools/*` (як publishable unit), або явно позначений як tooling-only library із власним простором правил (поза цим документом).

---

## 8) Versioning policy (MUST)

Версіонування **MUST** бути monorepo-wide:

- репозиторій має **одну** release line: git tags `vMAJOR.MINOR.PATCH`;
- усі пакети в монорепо мають **однакову** версію, похідну від repo tag.

**MUST NOT:**

- per-package independent versions,
- “внутрішні” теги для окремих пакетів.

---

## 9) Publishing target: Packagist via split repositories (MUST)

### 9.1. Canonical publish target (single-choice)

Packagist.org publish target **MUST** бути **тільки** split repositories (one package → one VCS repo):

- Для кожного publishable unit `framework/packages/<layer>/<slug>/` існує **окремий** split repository,
  де в корені лежить **сам пакет** (тобто `composer.json` у корені репозиторію).
- Monorepo root (`coretsia/`) є **dev workspace / source of truth** і **MUST NOT** бути сабмічений у Packagist
  як canonical publishable package.

**Rationale (normative):**
Packagist очікує `composer.json` у корені VCS-репозиторію, а версії автоматично беруться з git tags.

### 9.2. Split repository naming & mapping (single-choice)

Для кожного `package_id = <layer>/<slug>` split repository identity **MUST** бути детермінованою:

- **VCS host (single-choice):** GitHub
- **GitHub org/user (single-choice):** `coretsia`
- **Repository name (single-choice):** `<layer>-<slug>`
- **Repository URL (derived):** `https://github.com/coretsia/<layer>-<slug>`

**Examples (derived):**
- `core/contracts` → repo `coretsia/core-contracts`
- `platform/problem-details` → repo `coretsia/platform-problem-details`
- `integrations/cache-redis` → repo `coretsia/integrations-cache-redis`

### 9.3. Split content law (single-choice)

Split repository content **MUST** дорівнювати **точно** subtree пакета:

- split repo root == `framework/packages/<layer>/<slug>/` (включно з `src/`, `config/`, `tests/`, `README.md`, `composer.json`, …)
- split repo **MUST NOT** містити будь-що поза пакетом (наприклад: `docs/**`, `framework/tools/**`, `skeleton/**`, repo-root файли монорепо).

### 9.4. Tag/version propagation (single-choice)

Versioning залишається monorepo-wide (§8), тому:

- Monorepo git tags `vMAJOR.MINOR.PATCH` є **single source of version truth**.
- Кожен split repo **MUST** отримати **той самий** tag `vMAJOR.MINOR.PATCH`,
  який **MUST** вказувати на split commit, що відповідає цьому пакету.
- Tags **MUST NOT** переписуватися/ретагатися (immutability policy).

Packagist підтягує нові версії автоматично з tags у VCS repository.

### 9.5. Auto-update policy (single-choice)

Canonical publishing procedure **MUST** використовувати auto-update (service hook / GitHub integration):

- Для кожного split repo, який сабмічений у Packagist, **MUST** бути увімкнено GitHub hook / auto-sync.
- Manual “Update” у UI **MUST NOT** бути частиною canonical release процедури.
- Hook mode (GitHub) є рекомендованим і дає crawl “коли пушиш”.

### 9.6. Private phase status (MUST)

Поки репозиторії не є public:

- Packagist submission **MUST NOT** вважатися виконаним, бо сабміт робиться через public repository URL.
- Roadmap чекбокс для Packagist auto-update **MUST** залишатися в статусі `[ ]` з семантикою:
  **blocked until first public release** (або until switching to a private registry / Private Packagist as an explicit architectural change).

При цьому split automation rails (dry-run / verify) **MAY** бути реалізовані й перевірені в CI без Packagist,
але вони **MUST NOT** змінювати canonical статус чекбоксу до появи public evidence (див. план нижче).

---

## 10) Required references (MUST)

- `docs/architecture/STRUCTURE.md` **MUST** посилатися на цей документ як на packaging law.
- `README.md` **MUST** містити посилання на цей документ у секції документації/навігації.
