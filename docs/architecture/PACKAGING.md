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

### 5.4. Canonical namespace/source-path exceptions (MUST)

Most package namespace roots and source paths are derived mechanically by §5.2 and §5.3.

The following exceptions are canonical and MUST be treated as part of the packaging law:

| package_id           | namespace root               | source path      | rationale                                                              |
|----------------------|------------------------------|------------------|------------------------------------------------------------------------|
| `core/dto-attribute` | `Coretsia\Dto\Attribute\...` | `src/Attribute/` | DTO marker attribute namespace is locked by DTO policy and public API. |

Rules:

- Exceptions in this table are normative.
- Package compliance tooling MAY encode these exceptions directly.
- New exceptions MUST NOT be added without an explicit roadmap/ADR justification.
- Packages not listed here MUST use the derived mapping from §5.2 and §5.3.

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
- `Enterprise`
- `Devtools`
- `Presets`

#### Equivalent slug values (derived)

Because `<slug>` **MUST** be kebab-case (see §3.1), this rule means that
the following `<slug>` values **MUST NOT** be used under `core/*`:

- `core`
- `platform`
- `integrations`
- `enterprise`
- `devtools`
- `presets`

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

## 8) Package scaffold baseline (MUST)

Every publishable package under `framework/packages/<layer>/<slug>/` MUST contain the canonical baseline package scaffold.

### 8.1. Required artifacts for every package (single-choice)

Every package MUST contain:

- `composer.json`
- `README.md`
- `LICENSE`
- `NOTICE`
- `src/`
- `tests/Contract/`
- `tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### 8.2. Canonical legal files (single-choice)

Package legal files MUST be byte-identical to the monorepo root legal files:

- package `LICENSE` MUST equal repo root `LICENSE`
- package `NOTICE` MUST equal repo root `NOTICE`

Package-level legal files MUST NOT drift from the repository canonical legal text.

### 8.3. README baseline (single-choice)

Every package `README.md` MUST include at minimum the following sections:

- `## Observability`
- `## Errors`
- `## Security / Redaction`

The sections MAY be short for packages where the topic is not applicable, but they MUST exist to keep package policy review uniform.

---

## 9) Composer package metadata (MUST)

Every publishable package MUST define canonical Composer metadata in `composer.json`.

### 9.1. Baseline Composer fields (single-choice)

For every package:

- `"name"` MUST equal `coretsia/<layer>-<slug>` derived from the package path.
- `"type"` MUST equal `library`.
- `"license"` MUST equal `Apache-2.0`.
- `autoload.psr-4` MUST map the canonical package namespace root to `src/`.
- `autoload-dev.psr-4`, when present, MUST map the canonical package test namespace root to `tests/`.

### 9.2. Coretsia package kind (single-choice)

Every package MUST declare:

```json
{
  "extra": {
    "coretsia": {
      "kind": "library"
    }
  }
}
```

The value of `extra.coretsia.kind` MUST be exactly one of:

- `library`
- `runtime`

### 9.3. Library packages (MUST)

A package with:

```json
{
  "extra": {
    "coretsia": {
      "kind": "library"
    }
  }
}
```

is a library package.

Library packages:

- MUST NOT be required to define runtime module metadata.
- MUST NOT be required to contain runtime-only scaffold paths such as `src/Module/`, `src/Provider/`, or `config/`.
- MAY contain only library code, contracts, marker attributes, value objects, test support, or other non-runtime package surfaces.

### 9.4. Runtime packages (MUST)

A package with:

```json
{
  "extra": {
    "coretsia": {
      "kind": "runtime"
    }
  }
}
```

is a runtime package.

Runtime packages MUST declare canonical runtime metadata under `extra.coretsia`:

- `moduleId`
- `moduleClass`
- `providers`
- `defaultsConfigPath`

For a runtime package at:

```text
framework/packages/<layer>/<slug>/
```

the metadata MUST be derived as follows:

- `moduleId` MUST equal `<layer>.<slug>`
- `moduleClass` MUST equal the canonical runtime module FQCN
- `providers` MUST include the canonical runtime service provider FQCN
- `defaultsConfigPath` MUST equal `config/<slug>.php`

The canonical runtime module class file MUST be:

```text
src/Module/<StudlySlug>Module.php
```

The canonical runtime service provider class file MUST be:

```text
src/Provider/<StudlySlug>ServiceProvider.php
```

Where `StudlySlug` is derived by the `Studly(slug)` algorithm defined in this document.

---

## 10) Runtime package shape and reserved package identifiers (MUST)

### 10.1. Runtime package scaffold (single-choice)

Every runtime package MUST contain:

- `src/Module/`
- `src/Provider/`
- `src/Module/<StudlySlug>Module.php`
- `src/Provider/<StudlySlug>ServiceProvider.php`
- `config/`
- `config/<slug>.php`
- `config/rules.php`

### 10.2. Runtime config shape (single-choice)

Runtime package defaults file:

```text
config/<slug>.php
```

MUST return a plain array subtree and MUST NOT repeat the root wrapper.

Runtime package rules file:

```text
config/rules.php
```

MUST return a plain array.

Config root ownership and config subtree invariants are governed by:

```text
docs/ssot/config-roots.md
```

This packaging document MUST NOT introduce an alternative config-root ownership model.

### 10.3. Globally forbidden slugs (single-choice)

The following slugs MUST NOT be used for framework packages in any layer:

- `app`
- `modules`
- `shared`

Rationale:

- `app` is reserved for consuming applications / skeleton semantics.
- `modules` is reserved for module-selection/config terminology.
- `shared` is ambiguous and does not encode package ownership or layer semantics.

### 10.4. Roadmap-reserved slugs (single-choice)

The following slugs are reserved and MUST NOT be used by arbitrary new packages:

- `kernel`
- `observability`

Reserved slugs MAY be used only by canonical owner packages or paths explicitly defined by roadmap/SSoT ownership.

Current reserved slug ownership:

| slug            | allowed canonical package_id | notes                                                                                                                                       |
|-----------------|------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------|
| `kernel`        | `core/kernel`                | Canonical kernel runtime owner package.                                                                                                     |
| `observability` | none yet                     | Reserved umbrella term; use concrete packages such as logging, metrics, or tracing unless a future owner epic assigns this slug explicitly. |

The existing canonical package `framework/packages/core/kernel/` MUST remain valid and MUST NOT fail package compliance because of the reserved-slug rule.

---

## 11) Versioning policy (MUST)

Versioning **MUST** be monorepo-wide:

- the repository has **one** release line: git tags `vMAJOR.MINOR.PATCH`;
- all packages in the monorepo have **the same** version derived from the repo tag.

**MUST NOT:**

- per-package independent versions,
- “internal” tags for individual packages.

---

## 12) Publishing target: Packagist via split repositories (MUST)

### 12.1. Canonical publish target (single-choice)

Packagist.org publish target **MUST** be **only** split repositories (one package → one VCS repo):

- For every publishable unit `framework/packages/<layer>/<slug>/` there is a **separate** split repository,
  where the **package itself** lives at the repository root (that is, `composer.json` is at the repository root).
- Monorepo root (`coretsia/`) is the **dev workspace / source of truth** and **MUST NOT** be submitted to Packagist
  as a canonical publishable package.

**Rationale (normative):**
Packagist expects `composer.json` at the root of the VCS repository, and versions are taken automatically from git tags.

### 12.2. Split repository naming & mapping (single-choice)

For every `package_id = <layer>/<slug>`, split repository identity **MUST** be deterministic:

- **VCS host (single-choice):** GitHub
- **GitHub org/user (single-choice):** `coretsia`
- **Repository name (single-choice):** `<layer>-<slug>`
- **Repository URL (derived):** `https://github.com/coretsia/<layer>-<slug>`

**Examples (derived):**

- `core/contracts` → repo `coretsia/core-contracts`
- `platform/problem-details` → repo `coretsia/platform-problem-details`
- `integrations/cache-redis` → repo `coretsia/integrations-cache-redis`

### 12.3. Split content law (single-choice)

Split repository content **MUST** equal **exactly** the package subtree:

- split repo root == `framework/packages/<layer>/<slug>/` (including `src/`, `config/`, `tests/`, `README.md`, `composer.json`, …)
- split repo **MUST NOT** contain anything outside the package (for example: `docs/**`, `framework/tools/**`, `skeleton/**`, monorepo root files).

### 12.4. Tag/version propagation (single-choice)

Versioning remains monorepo-wide (§11), therefore:

- Monorepo git tags `vMAJOR.MINOR.PATCH` are the **single source of version truth**.
- Every split repo **MUST** receive the **same** tag `vMAJOR.MINOR.PATCH`,
  which **MUST** point to the split commit corresponding to that package.
- Tags **MUST NOT** be rewritten/re-tagged (immutability policy).

Packagist picks up new versions automatically from tags in the VCS repository.

### 12.5. Auto-update policy (single-choice)

Canonical publishing procedure **MUST** use auto-update (service hook / GitHub integration):

- For every split repo that is submitted to Packagist, GitHub hook / auto-sync **MUST** be enabled.
- Manual “Update” in the UI **MUST NOT** be part of the canonical release procedure.
- Hook mode (GitHub) is recommended and gives crawl-on-push behavior.

### 12.6. Private phase status (MUST)

While the repositories are not public:

- Packagist submission **MUST NOT** be considered completed, because submission is done via a public repository URL.
- The roadmap checkbox for Packagist auto-update **MUST** remain in the `[ ]` state with the semantics:
  **blocked until first public release** (or until switching to a private registry / Private Packagist as an explicit architectural change).

At the same time, split automation rails (dry-run / verify) **MAY** be implemented and verified in CI without Packagist,
but they **MUST NOT** change the canonical checkbox status until public evidence exists (see the plan below).

---

## 13) Required references (MUST)

- `docs/architecture/STRUCTURE.md` **MUST** refer to this document as the packaging law.
- `README.md` **MUST** include a link to this document in the documentation/navigation section.
