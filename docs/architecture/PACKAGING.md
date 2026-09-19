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

> Canonical / single-choice. \
> This is the single source of truth for monorepo packaging rules. Any other documents MUST refer to this file and MUST NOT introduce alternative rules.

---

## 0) Scope

This document fixes one canonical packaging strategy for the monorepo:

- Package identity: `path ↔ package_id ↔ composer ↔ namespace`.
- Publishable units law: which Composer products under `packages/**` are publishable vs repository-only tooling/state/docs.
- Versioning: one release line for the entire repository.

---

## 1) Terminology (normative)

- publishable product — a Composer distribution explicitly owned under `packages/**`.
- layered package — a publishable package at `packages/<layer>/<slug>/`.
- layer — the top layered-package category under `packages/` (for example: `core`, `platform`).
- slug — the layered package identifier within a layer in kebab-case (for example: `problem-details`).
- package_id — stable repository tooling identity; for layered packages it is `<layer>/<slug>`. Special-distribution ids are defined by the deterministic package/split plan and MUST NOT be fabricated from fake layer/slug values.
- composer name — the exact package identity declared by `composer.json` `name`.
- namespace root — the root PHP namespace for products that own PHP source.
- special distribution — a publishable product whose source shape is not `packages/<layer>/<slug>/`, currently `packages/framework/` and `packages/applications/skeleton/`.

---

## 2) Canonical package location (MUST)

### 2.1. Publishable product paths (single-choice)

Publishable Composer products MUST live under `packages/**`.

Canonical source shapes are:

- layered packages: `packages/<layer>/<slug>/`
- framework distribution: `packages/framework/`
- skeleton distribution: `packages/applications/skeleton/`

For layered packages:

- package_id MUST be `<layer>/<slug>`;
- Composer naming MUST follow `coretsia/<layer>-<slug>`.

Special public distributions are:

- `packages/framework/` → `coretsia/framework`
- `packages/applications/skeleton/` → `coretsia/skeleton`

For every publishable product, the authoritative Composer identity is its `composer.json` `name` field. Filesystem location MUST NOT be used as a universal Composer-name derivation mechanism.

Examples:

- `packages/core/contracts/` ↔ `core/contracts` ↔ `coretsia/core-contracts`
- `packages/platform/problem-details/` ↔ `platform/problem-details` ↔ `coretsia/platform-problem-details`
- `packages/framework/` ↔ `coretsia/framework`
- `packages/applications/skeleton/` ↔ `coretsia/skeleton`

### 2.2. Allowed layers (single-choice)

For layered packages, `<layer>` MUST be one of:

- `core`
- `platform`
- `integrations`
- `enterprise`
- `devtools`
- `presets`

---

## 3) Slug rules (MUST)

### 3.1. Format (single-choice)

`<slug>` MUST be kebab-case and MUST match:

- `/\A[a-z0-9][a-z0-9-]*\z/`

### 3.2. Uniqueness policy (single-choice)

- `<slug>` MUST be unique within one layer.
- `<slug>` MAY repeat across different layers (global uniqueness is ensured by the composer prefix `coretsia/<layer>-...`).

---

## 4) Composer identity mapping (MUST)

The package `composer.json` `name` field is the Composer identity source of truth.

For layered packages, the canonical naming convention is:

- `coretsia/<layer>-<slug>`

Special distributions use fixed identities:

- `packages/framework/` → `coretsia/framework`
- `packages/applications/skeleton/` → `coretsia/skeleton`

Tooling MAY validate that layered package names match their canonical convention, but package discovery and publishing MUST read identity from `composer.json.name` rather than deriving it blindly from filesystem depth.

MUST NOT:

- non-`coretsia/*` package identities for Coretsia public products,
- fake layer/slug identities for special distributions,
- per-package independent versioning (see §11).

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

Examples:

- `problem-details` → `ProblemDetails`
- `http-client` → `HttpClient`
- `cli` → `Cli`

### 5.2. Rule (single-choice)

Layered package namespaces MUST be deterministically derived from `{layer, slug}` — with the core exception defined below.

#### A) Core packages (`core/*`) (single-choice)

For `core/*` packages, the namespace root MUST be:

- `Coretsia\<Studly(slug)>\...`

Examples:

- `core/contracts` → `Coretsia\Contracts\...`
- `core/foundation` → `Coretsia\Foundation\...`
- `core/kernel` → `Coretsia\Kernel\...`

#### B) Non-core packages (`platform/*`, `integrations/*`, `enterprise/*`, `devtools/*`, `presets/*`) (single-choice)

For non-core packages, the namespace root MUST be:

- `Coretsia\<Studly(layer)>\<Studly(slug)>\...`

Examples:

- `platform/cli` → `Coretsia\Platform\Cli\...`
- `platform/problem-details` → `Coretsia\Platform\ProblemDetails\...`
- `integrations/cache-redis` → `Coretsia\Integrations\CacheRedis\...`
- `devtools/internal-toolkit` → `Coretsia\Devtools\InternalToolkit\...`

### 5.3. Source + tests mapping (MUST)

For any layered package:

- `packages/<layer>/<slug>/src` MUST map to the namespace root (see §5.2).
- `packages/<layer>/<slug>/tests` MUST map to `...\Tests\...` under the same root.

Examples:

- `packages/core/kernel/src` → `Coretsia\Kernel\...`
- `packages/core/kernel/tests` → `Coretsia\Kernel\Tests\...`
- `packages/platform/problem-details/src` → `Coretsia\Platform\ProblemDetails\...`
- `packages/platform/problem-details/tests` → `Coretsia\Platform\ProblemDetails\Tests\...`

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

Because for `core/*` the namespace root is defined as `Coretsia\<Studly(slug)>`, collisions with non-core layers must be prevented, where the namespace root contains the layer segment (for example `Coretsia\Platform\...`).

#### Normative rule (single-choice)

For `core/*` packages, the value of `Studly(<slug>)` MUST NOT equal any of:

- `Core`
- `Platform`
- `Integrations`
- `Enterprise`
- `Devtools`
- `Presets`

#### Equivalent slug values (derived)

Because `<slug>` MUST be kebab-case (see §3.1), this rule means that the following `<slug>` values MUST NOT be used under `core/*`:

- `core`
- `platform`
- `integrations`
- `enterprise`
- `devtools`
- `presets`

Rationale:

- `core/*` has short canonical namespaces (`Coretsia\Foundation`, etc.).
- non-core packages include the layer segment (`Coretsia\Platform\...`) for global uniqueness.
- the prohibition guarantees that `Coretsia\<Studly(slug)>` will not intersect with `Coretsia\<Studly(layer)>\...`.

---

## 7) Publishable units law (MUST)

### 7.1. Publishable (single-choice)

Publishable units are explicit Composer products under `packages/**`.

Supported source shapes include:

- `packages/framework/composer.json`
- `packages/applications/skeleton/composer.json`
- `packages/<layer>/<slug>/composer.json`

Package discovery MUST inspect publishable Composer manifests under `packages/**`.

Package identity MUST be read from `composer.json.name`.

A grouping directory without its own `composer.json` is not itself a publishable package.

### 7.2. Non-publishable (single-choice)

The following parts of the repository MUST NOT be considered publishable packages (and MUST NOT be positioned as such):

- `tools/**` — tooling, gates, CI rails, generators, and tooling support
- `var/**` — mutable/generated repository workspace state
- `vendor/**` — root workspace dependencies
- `docs/**` — documentation
- repo root `composer.json` — developer workspace manifest, not a public package
- other repo root files (`README.md`, `LICENSE`, etc.) — navigation/rules/legal SSoT, not packages

> If tooling requires a composer package, it MUST be implemented as a normal package under `packages/devtools/*` (as a publishable unit), or explicitly marked as a tooling-only library with its own rule space (outside this document).

---

## 8) Package scaffold baseline (MUST)

Every layered package under `packages/<layer>/<slug>/` MUST contain the canonical layered-package scaffold.

Special distributions `coretsia/framework` and `coretsia/skeleton` are governed by their own distribution contracts and MUST NOT be forced into the layered-package scaffold.

### 8.1. Required artifacts for every layered package (single-choice)

Every layered package MUST contain:

- `composer.json`
- `README.md`
- `LICENSE`
- `NOTICE`
- `src/`
- `tests/Contract/`
- `tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

### 8.2. Canonical legal files (single-choice)

Legal files in every publishable product MUST be byte-identical to the monorepo root legal files:

- package `LICENSE` MUST equal repo root `LICENSE`
- package `NOTICE` MUST equal repo root `NOTICE`

Package-level legal files MUST NOT drift from the repository canonical legal text.

### 8.3. README baseline (single-choice)

Every layered package `README.md` MUST include at minimum the following sections:

- `## Observability`
- `## Errors`
- `## Security / Redaction`

The sections MAY be short for layered packages where the topic is not applicable, but they MUST exist to keep package policy review uniform.

---

## 9) Composer package metadata (MUST)

Every publishable product MUST define canonical Composer metadata in `composer.json`.

### 9.1. Baseline Composer fields (single-choice)

For every publishable product:

- `"name"` MUST contain the canonical Coretsia Composer identity for that product.
- `"license"` MUST equal `Apache-2.0`.
- `coretsia/skeleton` MUST use Composer `"type": "project"`.
- `coretsia/framework` MUST use Composer `"type": "metapackage"` while it remains a dependency-only distribution with no installable package payload.
- all currently defined layered Coretsia packages MUST use Composer `"type": "library"`.
- `autoload.psr-4`, when the product owns PHP source, MUST map its canonical namespace to the owned source path.
- `autoload-dev.psr-4`, when present, MUST map owned test namespaces to owned test paths.

Layered package naming and namespace rules remain governed by §§4–5.

### 9.2. Coretsia package kind (single-choice)

Every layered package MUST declare:

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

A layered package with:

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

A layered package with:

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
packages/<layer>/<slug>/
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

The following slugs MUST NOT be used for layered packages:

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

The existing canonical package `packages/core/kernel/` MUST remain valid and MUST NOT fail package compliance because of the reserved-slug rule.

---

## 11) Versioning and release-line package policy (MUST)

Versioning MUST be monorepo-wide:

- the repository has one release line: git tags `vMAJOR.MINOR.PATCH`;
- all packages in the monorepo have the same version derived from the repo tag.

MUST NOT:

- per-package independent versions,
- “internal” tags for individual packages.

### 11.1. Package version source (single-choice)

Package versions published to Packagist MUST be derived from git tags in split repositories.

Package `composer.json` files MUST NOT contain a manual `version` field.

Rationale:

- Packagist derives package versions from VCS tags.
- Package-local `version` fields can drift from the monorepo release tag.
- The monorepo release tag remains the single source of package version truth.

### 11.2. Release-line tooling SSoT (single-choice)

The tooling SSoT for the active package release line is:

```text
tools/release/release-line.json
```

This file owns:

- `currentMinor` — current release minor, for example `0.4`;
- `devVersion` — Composer workspace dev version, for example `0.4.x-dev`;
- `publicConstraint` — public internal dependency constraint, for example `^0.4.0`.

`schemaVersion` is the schema version of `release-line.json`, not the package release version.

`schemaVersion` MUST NOT be changed for ordinary patch or minor releases.

### 11.3. Monorepo workspace package resolution (single-choice)

The monorepo workspace MUST resolve local package changes through Composer path repositories.

Managed local path repository entries (`packages/framework` and `packages/*/*`) MUST contain canonical release-line metadata derived from:

```text
tools/release/release-line.json
```

Each discovered local package version in managed workspace path repositories MUST use `devVersion`.

Example:

```json
{
  "options": {
    "symlink": true,
    "reference": "config",
    "versions": {
      "coretsia/core-contracts": "0.4.x-dev"
    }
  }
}
```

This keeps local development source-linked through symlinks while allowing package constraints to use release-line dev versions instead of `dev-main`.

### 11.4. Internal Coretsia dependency constraints (MUST)

Published or split-publish allowlisted packages MUST NOT require internal `coretsia/*` packages as `dev-main`.

Published or split-publish allowlisted packages MUST NOT require internal `coretsia/*` packages as `*`.

Published or split-publish allowlisted packages MUST NOT require internal `coretsia/*` packages with `@dev` stability flags.

Published or split-publish allowlisted packages MUST NOT require internal `coretsia/*` packages with exact SemVer pins.

Published or split-publish allowlisted packages MUST use the release-line `publicConstraint` for internal `coretsia/*` dependencies.

Example for release line `0.4`:

```json
{
  "require": {
    "coretsia/core-contracts": "^0.4.0"
  }
}
```

The public internal constraint MUST be synchronized from:

```text
tools/release/release-line.json
```

Do not edit public internal `coretsia/*` dependency constraints manually except as part of changing the release-line SSoT and running the canonical synchronization commands.

---

## 12) Publishing target: Packagist via split repositories (MUST)

### 12.1. Canonical publish target (single-choice)

Packagist.org publish target MUST be split repositories (one publishable product → one VCS repository).

For every allowlisted publishable product discovered under `packages/**`:

- the deterministic split plan owns its source `pathPrefix`, package identity, and target split repository;
- the contents of `pathPrefix` become the split repository root;
- `composer.json` therefore lives at the split repository root.

The monorepo root is the developer workspace and source of truth and MUST NOT be submitted to Packagist as a public package.

Rationale (normative): Packagist expects `composer.json` at the root of the VCS repository, and versions are taken automatically from git tags.

### 12.2. Split repository identity and mapping (single-choice)

Split repository identity MUST come from the deterministic split plan.

For each publishable product, the plan MUST provide at minimum:

- stable `package_id`
- source `pathPrefix`
- Composer package name
- GitHub repository owner
- GitHub repository name

Layered packages MAY use their canonical `<layer>-<slug>` repository-name convention.

Special distributions MUST be represented explicitly and MUST NOT require fake layer/slug identities.

Package publishing code MUST NOT derive Composer identity solely from filesystem layout.

### 12.3. Split content law (single-choice)

Split repository content MUST equal exactly the source distribution subtree selected by the deterministic split plan.

- the contents of `pathPrefix` MUST become the split repository root;
- `pathPrefix` itself MUST NOT appear as a nested directory wrapper;
- split content MUST NOT include unrelated monorepo content such as `docs/**`, `tools/**`, root `var/**`, root workspace files, or other package source trees.

### 12.4. Tag/version propagation (single-choice)

Versioning remains monorepo-wide (§11), therefore:

- Monorepo git tags `vMAJOR.MINOR.PATCH` are the single source of version truth.
- Every split repo MUST receive the same tag `vMAJOR.MINOR.PATCH`, which MUST point to the split commit corresponding to that package.
- Tags MUST NOT be rewritten/re-tagged (immutability policy).

Packagist picks up new versions automatically from tags in the VCS repository.

### 12.5. Auto-update policy (single-choice)

Canonical publishing procedure MUST use auto-update (service hook / GitHub integration):

- For every split repo that is submitted to Packagist, GitHub hook / auto-sync MUST be enabled.
- Manual “Update” in the UI MUST NOT be part of the canonical release procedure.
- Hook mode (GitHub) is recommended and gives crawl-on-push behavior.

### 12.6. Private phase status (MUST)

While the repositories are not public:

- Packagist submission MUST NOT be considered completed, because submission is done via a public repository URL.
- The roadmap checkbox for Packagist auto-update MUST remain in the `[ ]` state with the semantics: blocked until first public release (or until switching to a private registry / Private Packagist as an explicit architectural change).

At the same time, split automation rails (dry-run / verify) MAY be implemented and verified in CI without Packagist, but they MUST NOT change the canonical checkbox status until public evidence exists (see the plan below).

---

## 13) Required references (MUST)

- `docs/architecture/STRUCTURE.md` MUST refer to this document as the packaging law.
- `README.md` MUST include a link to this document in the documentation/navigation section.
