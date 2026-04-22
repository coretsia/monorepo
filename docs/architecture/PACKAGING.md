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
> This is the single source of truth for monorepo packaging rules. Any other documents **MUST** refer to this file and **MUST NOT** introduce alternative rules.

---

## 0) Scope

This document fixes **one** canonical packaging strategy for the monorepo:

- **Package identity**: `path ↔ package_id ↔ composer ↔ namespace`.
- **Publishable units law**: what is publishable (packages) vs non-publishable (tools/skeleton/docs).
- **Versioning**: one release line for the entire repository.

---

## 1) Terminology (normative)

- **layer** — the top package layer under `framework/packages/` (for example: `core`, `platform`).
- **slug** — the package identifier within a layer in kebab-case (for example: `problem-details`).
- **package_id** — `<layer>/<slug>` (for example: `platform/problem-details`).
- **composer name** — `coretsia/<layer>-<slug>` (for example: `coretsia/platform-problem-details`).
- **namespace root** — the root PHP namespace for `src/` and `tests/`.

---

## 2) Canonical package location (MUST)

### 2.1. Package path (single-choice)

Every framework package **MUST** live at the path:

- `framework/packages/<layer>/<slug>/`

Accordingly:

- **package_id MUST** be: `<layer>/<slug>`.
- **composer name MUST** be: `coretsia/<layer>-<slug>`.

**Examples:**

- `framework/packages/core/contracts/` ↔ `core/contracts` ↔ `coretsia/core-contracts`
- `framework/packages/platform/problem-details/` ↔ `platform/problem-details` ↔ `coretsia/platform-problem-details`

### 2.2. Allowed layers (single-choice)

`<layer>` **MUST** be one of:

- `core`
- `platform`
- `integrations`
- `enterprise`
- `devtools`
- `presets`

---

## 3) Slug rules (MUST)

### 3.1. Format (single-choice)

`<slug>` **MUST** be kebab-case and **MUST** match:

- `/\A[a-z0-9][a-z0-9-]*\z/`

### 3.2. Uniqueness policy (single-choice)

- `<slug>` **MUST** be unique **within one layer**.
- `<slug>` **MAY** repeat **across different layers** (global uniqueness is ensured by the composer prefix `coretsia/<layer>-...`).

---

## 4) Composer identity mapping (MUST)

Composer package name **MUST** be derived **only** from `{layer, slug}` by the formula:

- `coretsia/<layer>-<slug>`

**MUST NOT:**

- any other prefixes/naming (such as `coretsia/<slug>` without the layer),
- “personal” vendor names for framework packages,
- per-package versioning (see §7).

---

## 5) Deterministic namespace mapping (MUST)

### 5.1. StudlyCase algorithm (single-choice)

We define `Studly(x)` as follows:

- `x` is split by `-` into tokens;
- for each token:
  - first character → uppercase,
  - all other characters → lowercase,
  - digits are preserved as-is;
- the tokens are concatenated.

**Examples:**

- `problem-details` → `ProblemDetails`
- `http-client` → `HttpClient`
- `cli` → `Cli`

### 5.2. Rule (single-choice)

Namespaces **MUST** be deterministically derived from `{layer, slug}` — but with a **core exception** (short canonical namespaces).

#### A) Core packages (`core/*`) (single-choice)

For `core/*` packages, the namespace root **MUST** be:

- `Coretsia\<Studly(slug)>\...`

**Examples:**

- `core/contracts` → `Coretsia\Contracts\...`
- `core/foundation` → `Coretsia\Foundation\...`
- `core/kernel` → `Coretsia\Kernel\...`

#### B) Non-core packages (`platform/*`, `integrations/*`, `enterprise/*`, `devtools/*`, `presets/*`) (single-choice)

For non-core packages, the namespace root **MUST** be:

- `Coretsia\<Studly(layer)>\<Studly(slug)>\...`

**Examples:**

- `platform/cli` → `Coretsia\Platform\Cli\...`
- `platform/problem-details` → `Coretsia\Platform\ProblemDetails\...`
- `integrations/cache-redis` → `Coretsia\Integrations\CacheRedis\...`
- `devtools/cli-spikes` → `Coretsia\Devtools\CliSpikes\...`

### 5.3. Source + tests mapping (MUST)

For any package:

- `framework/packages/<layer>/<slug>/src` **MUST** map to the namespace root (see §5.2).
- `framework/packages/<layer>/<slug>/tests` **MUST** map to `...\Tests\...` under the same root.

**Examples:**

- `framework/packages/core/kernel/src` → `Coretsia\Kernel\...`
- `framework/packages/core/kernel/tests` → `Coretsia\Kernel\Tests\...`
- `framework/packages/platform/problem-details/src` → `Coretsia\Platform\ProblemDetails\...`
- `framework/packages/platform/problem-details/tests` → `Coretsia\Platform\ProblemDetails\Tests\...`

---

## 6) Collision safety (MUST)

Because `core/*` uses the short root `Coretsia\<Studly(slug)>`, collisions with non-core layers must be prevented.

### 6.1. Reserved slugs for `core/*` (MUST)

Because for `core/*` the namespace root is defined as `Coretsia\<Studly(slug)>`,
collisions with non-core layers must be prevented, where the namespace root contains the layer segment
(for example `Coretsia\Platform\...`).

#### Normative rule (single-choice)

For `core/*` packages, the value of `Studly(<slug>)` **MUST NOT** equal any of:

- `Core`
- `Platform`
- `Integrations`
- `Devtools`

#### Equivalent slug values (derived)

Because `<slug>` **MUST** be kebab-case (see §3.1), this rule means that
the following `<slug>` values **MUST NOT** be used under `core/*`:

- `core`
- `platform`
- `integrations`
- `devtools`

**Rationale:**

- `core/*` has short canonical namespaces (`Coretsia\Foundation`, etc.).
- non-core packages include the layer segment (`Coretsia\Platform\...`) for global uniqueness.
- the prohibition guarantees that `Coretsia\<Studly(slug)>` will not intersect with `Coretsia\<Studly(layer)>\...`.

---

## 7) Publishable units law (MUST)

### 7.1. Publishable (single-choice)

**Publishable units** are **only** packages under:

- `framework/packages/<layer>/<slug>/`

Each such directory is **one** Composer package (`coretsia/<layer>-<slug>`).

### 7.2. Non-publishable (single-choice)

The following parts of the repository **MUST NOT** be considered publishable packages (and **MUST NOT** be positioned as such):

- `framework/tools/**` — tooling, gates, CI rails, spikes, generators
- `skeleton/**` — workspace app sandbox, fixtures, runtime caches (`skeleton/var/**`)
- `docs/**` — documentation
- repo root files (`README.md`, `LICENSE`, etc.) — navigation/rules, not packages

> If tooling requires a composer package, it **MUST** be implemented as a normal package under `framework/packages/devtools/*` (as a publishable unit), or explicitly marked as a tooling-only library with its own rule space (outside this document).

---

## 8) Versioning policy (MUST)

Versioning **MUST** be monorepo-wide:

- the repository has **one** release line: git tags `vMAJOR.MINOR.PATCH`;
- all packages in the monorepo have **the same** version derived from the repo tag.

**MUST NOT:**

- per-package independent versions,
- “internal” tags for individual packages.

---

## 9) Publishing target: Packagist via split repositories (MUST)

### 9.1. Canonical publish target (single-choice)

Packagist.org publish target **MUST** be **only** split repositories (one package → one VCS repo):

- For every publishable unit `framework/packages/<layer>/<slug>/` there is a **separate** split repository,
  where the **package itself** lives at the repository root (that is, `composer.json` is at the repository root).
- Monorepo root (`coretsia/`) is the **dev workspace / source of truth** and **MUST NOT** be submitted to Packagist
  as a canonical publishable package.

**Rationale (normative):**
Packagist expects `composer.json` at the root of the VCS repository, and versions are taken automatically from git tags.

### 9.2. Split repository naming & mapping (single-choice)

For every `package_id = <layer>/<slug>`, split repository identity **MUST** be deterministic:

- **VCS host (single-choice):** GitHub
- **GitHub org/user (single-choice):** `coretsia`
- **Repository name (single-choice):** `<layer>-<slug>`
- **Repository URL (derived):** `https://github.com/coretsia/<layer>-<slug>`

**Examples (derived):**
- `core/contracts` → repo `coretsia/core-contracts`
- `platform/problem-details` → repo `coretsia/platform-problem-details`
- `integrations/cache-redis` → repo `coretsia/integrations-cache-redis`

### 9.3. Split content law (single-choice)

Split repository content **MUST** equal **exactly** the package subtree:

- split repo root == `framework/packages/<layer>/<slug>/` (including `src/`, `config/`, `tests/`, `README.md`, `composer.json`, …)
- split repo **MUST NOT** contain anything outside the package (for example: `docs/**`, `framework/tools/**`, `skeleton/**`, monorepo root files).

### 9.4. Tag/version propagation (single-choice)

Versioning remains monorepo-wide (§8), therefore:

- Monorepo git tags `vMAJOR.MINOR.PATCH` are the **single source of version truth**.
- Every split repo **MUST** receive the **same** tag `vMAJOR.MINOR.PATCH`,
  which **MUST** point to the split commit corresponding to that package.
- Tags **MUST NOT** be rewritten/re-tagged (immutability policy).

Packagist picks up new versions automatically from tags in the VCS repository.

### 9.5. Auto-update policy (single-choice)

Canonical publishing procedure **MUST** use auto-update (service hook / GitHub integration):

- For every split repo that is submitted to Packagist, GitHub hook / auto-sync **MUST** be enabled.
- Manual “Update” in the UI **MUST NOT** be part of the canonical release procedure.
- Hook mode (GitHub) is recommended and gives crawl-on-push behavior.

### 9.6. Private phase status (MUST)

While the repositories are not public:

- Packagist submission **MUST NOT** be considered completed, because submission is done via a public repository URL.
- The roadmap checkbox for Packagist auto-update **MUST** remain in the `[ ]` state with the semantics:
  **blocked until first public release** (or until switching to a private registry / Private Packagist as an explicit architectural change).

At the same time, split automation rails (dry-run / verify) **MAY** be implemented and verified in CI without Packagist,
but they **MUST NOT** change the canonical checkbox status until public evidence exists (see the plan below).

---

## 10) Required references (MUST)

- `docs/architecture/STRUCTURE.md` **MUST** refer to this document as the packaging law.
- `README.md` **MUST** include a link to this document in the documentation/navigation section.
