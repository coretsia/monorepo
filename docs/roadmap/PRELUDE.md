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

## PRELUDE — Repo bootstrap (Non-product doc)

### PRELUDE.10.0 Repo bootstrap (first commit): git hygiene + top-level legal/docs (MUST) [TOOLING]

---
type: skeleton
phase: PRELUDE
epic_id: "PRELUDE.10.0"
owner_path: "./"

goal: "A clean clone has canonical repo hygiene + legal/docs so Phase 0 work can start immediately."
provides:

- "Deterministic git/editor behavior across contributors"
- "Top-level legal + navigation docs present from day zero"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
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
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- N/A

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [x] `.editorconfig` — repo-wide editor baseline
- [x] `.gitattributes` — canonical text normalization attributes:
  - [x] default: `* text=auto`
  - [x] code/docs/scripts: enforce `eol=lf` (single-choice)
  - [x] windows scripts: `*.bat`/`*.cmd` MUST use `eol=crlf`
  - [x] binaries MUST be explicit `-text` (at minimum: `*.png`, `*.jpg`, `*.jpeg`, `*.gif`, `*.webp`, `*.ico`, `*.pdf`, `*.zip`, `*.gz`, `*.7z`, `*.phar`)
- [x] `.gitignore` — baseline ignore policy
- [x] `LICENSE` — project license text
- [x] `NOTICE` — legal notices
- [x] `CONTRIBUTING.md` — contribution policy entrypoint
- [x] `CHANGELOG.md` — changelog entrypoint
- [x] `README.md` — root onboarding + navigation entrypoint
- [x] `UPGRADE.md` — upgrade policy entrypoint
- [x] `docs/roadmap/ROADMAP.md` — roadmap (non-product doc)
- [x] `docs/architecture/STRUCTURE.md` — repository structure doc
- [x] `docs/generated/GENERATED_STRUCTURE.md` — generated docs namespace placeholder (no real generators yet)
- [x] `docs/ssot/INDEX.md` — SSoT index entrypoint (placeholder):
  - [x] MUST exist before any SSoT registry docs are added (tags/config-roots/artifacts/observability)
  - [x] Later epics MUST only "Modify" this file (no re-ownership as Creates)

#### Modifies

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A

### Tests (MUST)

N/A

### DoD (MUST)

- [x] Deliverables complete (creates), paths exact
- [x] Preconditions satisfied (no forward references)
- [x] Determinism: first commit contains all deliverables (no generated artifacts)
- [x] MUST NOT (first commit invariants):
  - [x] MUST NOT introduce CI or automation: `.github/**`
  - [x] MUST NOT introduce tooling/runtime roots: `framework/**`, `skeleton/**`
  - [x] MUST NOT introduce entrypoints: `composer.json`, `./dev/**`, `.githooks/**`
  - [x] MUST NOT introduce any generators/scripts that produce artifacts
- [x] No duplication: later epics MUST NOT re-own these files as “Creates”
- [x] LF policy is enforced by `.gitattributes` and must be verified on Windows checkout
- [x] SSoT bootstrap:
  - [x] `docs/ssot/INDEX.md` exists as the single SSoT navigation entrypoint
  - [x] Later SSoT epics MUST only modify it (never re-create it)

---

### PRELUDE.20.0 Packaging strategy lock (MUST) [DOC/TOOLING]

---
type: docs
phase: PRELUDE
epic_id: "PRELUDE.20.0"
owner_path: "docs/architecture/"

goal: "Lock a single canonical packaging strategy for the monorepo to prevent divergence before implementation starts."
provides:

- "Canonical rules for package identity (composer name, namespace, layer/slug conventions)"
- "Canonical rules for repository layout and publishable units (what is a package vs skeleton vs tooling)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — repo docs/legal baseline exists

- Required deliverables (exact paths):
  - `docs/architecture/STRUCTURE.md` — repository structure entrypoint exists
  - `README.md` — root navigation entrypoint exists

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (doc-only)

Forbidden:

- N/A

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [x] `docs/architecture/PACKAGING.md` — canonical packaging strategy (monorepo packaging law)
  - [x] Must include:

## Namespace mapping (deterministic)

Packages MUST use a deterministic namespace mapping derived from `{layer, slug}`.

### Rule (single-choice)

- For `core/*` packages:
  - Namespace MUST be: `Coretsia\<Studly(slug)>\...`
  - Examples:
    - `core/contracts` → `Coretsia\Contracts\...`
    - `core/foundation` → `Coretsia\Foundation\...`
    - `core/kernel` → `Coretsia\Kernel\...`

- For non-core packages (`platform/*`, `integrations/*`, `devtools/*`):
  - Namespace MUST be: `Coretsia\<Studly(layer)>\<Studly(slug)>\...`
  - Examples:
    - `platform/cli` → `Coretsia\Platform\Cli\...`
    - `devtools/cli-spikes` → `Coretsia\Devtools\CliSpikes\...`

### Collision safety (MUST)

To prevent namespace collisions between `core/*` and non-core layers:

- The following slugs are RESERVED and MUST NOT be used as package slugs in `core/*`:
  - `Core`, `Platform`, `Integrations`, `Devtools`

Rationale:
- `core/*` uses shorter canonical namespaces (`Coretsia\Foundation`, etc.) already present in the codebase.
- Non-core layers keep the layer segment to guarantee global uniqueness.

#### Modifies

- [x] `README.md` — add link to `docs/architecture/PACKAGING.md` in the docs/navigation section
- [x] `docs/architecture/STRUCTURE.md` — add a short “Packaging strategy” reference section linking to `docs/architecture/PACKAGING.md`

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags

N/A

#### Artifacts / outputs

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (doc-only)

### Tests (MUST)

N/A

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] Preconditions satisfied (no forward references)
- [x] The packaging strategy is **single-choice** (no “either/or”), with explicit MUST/MUST NOT rules
- [x] `docs/architecture/PACKAGING.md` MUST include (as MUST/MUST NOT rules):
  - [x] Package identity mapping: `framework/packages/<layer>/<slug>` ↔ `package_id=<layer>/<slug>`
  - [x] Composer name mapping (single-choice): `composer=coretsia/<layer>-<slug>`
  - [x] Slug uniqueness policy (single-choice):
    - [x] slug MAY be reused across layers; global uniqueness is ensured by the `<layer>-` prefix in composer name
  - [x] Namespace mapping (single-choice; deterministic algorithm):
    - [x] `layer` → StudlyCase(`<layer>`)
    - [x] `slug` (kebab-case) → StudlyCase(`<slug>`)
    - [x] Source: `framework/packages/<layer>/<slug>/src` → `Coretsia\<Layer>\<Slug>\...`
    - [x] Tests: `framework/packages/<layer>/<slug>/tests` → `Coretsia\<Layer>\<Slug>\Tests\...`
    - [x] Example: `framework/packages/platform/problem-details` → `Coretsia\Platform\ProblemDetails\...`
  - [x] Publishable units law: what is publishable (packages) vs non-publishable (tools/skeleton/docs)
  - [x] Versioning policy (single-choice): monorepo tags
    - [x] The repository uses a single release line: tags vMAJOR.MINOR.PATCH
    - [x] All packages in the monorepo share the same version derived from the repo tag
    - [x] Per-package independent versions MUST NOT be used
- [x] README/STRUCTURE navigation points to the packaging strategy (discoverable on clean clone)

---

### PRELUDE.30.0 Repo skeleton + базові composer/CI entrypoints (MUST) [TOOLING]

---
type: skeleton
phase: PRELUDE
epic_id: "PRELUDE.30.0"
owner_path: "./"

goal: "A clean clone can run canonical bootstrap commands and has minimal CI rails + managed composer repositories
sync."
provides:

- "Canonical repo-root composer entrypoints (`composer setup|ci|test`)"
- "Managed composer repositories sync tooling (idempotent) + backups"
- "Skeleton var directory structure scaffolded"
- "Project-wide PHPUnit harness usable before any packages/spikes exist"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — repo hygiene + legal/docs exist
  - PRELUDE.20.0 — packaging strategy locked (generator follows conventions)

- Required deliverables (exact paths):
  - `docs/architecture/PACKAGING.md` — canonical packaging rules for generators
  - `README.md` — root entrypoint exists (from PRELUDE.10.0)
  - `docs/roadmap/ROADMAP.md` — roadmap exists (from PRELUDE.10.0)
  - `docs/architecture/STRUCTURE.md` — repository structure entrypoint exists (from PRELUDE.10.0)

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (repo-level tooling)

Forbidden:

- N/A

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- Canonical entrypoint (repo root, single-choice):
  - `composer setup`
  - `composer ci`
  - `composer test` (`composer test-fast|test-slow` optional later)

- Tooling (repo root, explicit calls when needed):
  - `php framework/tools/build/sync_composer_repositories.php`
  - `php framework/tools/build/new-package.php ...`

- Artifacts:
  - tooling writes: `framework/var/*` (ignored)
  - skeleton runtime dirs are scaffolded with keep files only (no runtime code/artifacts)

### Deliverables (MUST)

#### Creates

**CI + runner**

- [x] `.github/workflows/ci.yml` MUST run project-wide checks:
  - [x] `php framework/tools/build/sync_composer_repositories.php --check` (MUST fail on drift; MUST run before any `composer install`)
  - [x] `composer install` (root; MUST NOT modify `composer.lock`)
  - [x] `composer -d framework install` (MUST NOT modify `framework/composer.lock`)
  - [x] `composer -d skeleton install` (MUST NOT modify `skeleton/composer.lock`)
  - [x] `composer validate` (root)
  - [x] `composer -d framework validate`
  - [x] `composer -d skeleton validate`
  - [x] `composer test` (repo root; delegates to `framework/tools/testing/phpunit.xml`)
  - [x] lock drift check: job MUST fail if any `composer.lock` changed
- [x] `.github/workflows/ci.yml` SHOULD run on at least:
  - [x] ubuntu-latest
  - [x] windows-latest (smoke: install+validate+test; lock drift check)

**Composer roots**

- [x] `composer.json` — repo root scripts + workspace pointers (baseline)
- [x] `framework/composer.json` — tooling workspace root
- [x] `skeleton/composer.json` — skeleton app workspace root
- [x] `skeleton/.env.example` — baseline env template

**Deterministic dependency locks**

- [x] `composer.lock` — repo root lock (deterministic CI installs)
- [x] `framework/composer.lock` — tooling workspace lock
- [x] `skeleton/composer.lock` — skeleton workspace lock

**Managed repositories tooling**

- [x] `framework/tools/build/package_index.php` — package index build (tooling-only)
- [x] `framework/tools/build/sync_composer_repositories.php` — idempotent sync + backups + check mode
- [x] `framework/tools/build/new-package.php` — create new package skeleton (tooling-side)

**Var dirs**

- [x] `framework/var/.gitignore`
- [x] `framework/var/backups/.gitignore`
- [x] `skeleton/var/cache/.gitkeep`
- [x] `skeleton/var/logs/.gitkeep`
- [x] `skeleton/var/tmp/.gitkeep`
- [x] `skeleton/var/quarantine/.gitkeep`
- [x] `skeleton/var/maintenance/.gitkeep`
- [x] `skeleton/var/sessions/.gitkeep`
- [x] `skeleton/var/locks/.gitkeep`
- [x] `skeleton/var/cache-data/.gitkeep`
- [x] `skeleton/var/etl/.gitkeep`

**Git hooks + guides**

- [x] `.githooks/pre-commit` — blocks unmanaged edits of managed composer repositories (via `--check`)
- [x] `docs/guides/git-hooks.md` — how to enable/use hooks
- [x] `docs/guides/quickstart.md` — “clean clone → working baseline”
- [x] `docs/guides/onboarding.md` — developer onboarding checklist (repo-root `composer ...`; MUST NOT reference `./dev/**`)
- [x] `docs/guides/dependency-graph.md` — conceptual doc (no deptrac enforcement claims yet)

**Testing infrastructure (project-wide)**

- [x] `framework/tools/testing/phpunit.xml` — canonical PHPUnit config for the whole monorepo MUST:
  - [x] define a default testsuite that always exists (Smoke)
  - [x] not require directories that do not exist yet (no forward refs to `framework/tools/spikes/**` until 0.20.0)
- [x] `framework/tools/testing/bootstrap.php` — canonical test bootstrap (autoload + strict ini)
- [x] `framework/tools/testing/tests/Smoke/MonorepoSmokeTest.php` — minimal always-on smoke test (prevents “0 tests” / proves harness works)
- [x] `framework/tools/testing/phpunit.xml` MUST include (no forward refs; paths relative to `framework/`):
  - [x] `tools/testing/tests/**` (Smoke suite)
  - [x] `tools/tests/**` (tooling integration tests, incl. managed repositories guard)
- [x] `framework/tools/tests/Integration/ManagedComposerRepositoriesGuardTest.php` — test evidence: sync/check exists + restores canonical state + rerun-no-diff

#### Modifies

- [x] `README.md` — add links to:
  - [x] `docs/guides/quickstart.md`
  - [x] `docs/guides/onboarding.md`
  - [x] `docs/guides/git-hooks.md`
  - [x] `docs/guides/dependency-graph.md`
  - [x] and reiterate canonical repo-root commands: `composer setup|ci|test`
- [x] `docs/architecture/STRUCTURE.md` — update repository layout to reflect introduction of:
  - [x] `framework/` (tooling workspace root + `framework/var/*`)
  - [x] `skeleton/` (skeleton app workspace root + `skeleton/var/*`)
  - [x] `.githooks/` (enabled by `composer setup`)

#### Configuration (keys + defaults)

- [x] Files:
  - [x] `composer.json`
  - [x] `framework/composer.json`
  - [x] `skeleton/composer.json`
- [x] Managed composer repositories (single-choice; MUST be explicit):
  - [x] The `repositories` key in each of:
    - [x] `composer.json`
    - [x] `framework/composer.json`
    - [x] `skeleton/composer.json`
  - [x] MUST be fully managed by:
    - [x] `php framework/tools/build/sync_composer_repositories.php`
  - [x] MUST NOT be edited manually; enforcement:
    - [x] `.githooks/pre-commit` MUST run `php framework/tools/build/sync_composer_repositories.php --check` and fail on drift
- [x] Root `composer.json` scripts (canonical delegation; single-choice):
  - [x] `setup` = (MUST run from repo root):
    - [x] enable hooks: `git config core.hooksPath .githooks`
    - [x] `php framework/tools/build/sync_composer_repositories.php`
    - [x] `composer install` (root)
    - [x] `composer -d framework install`
    - [x] `composer -d skeleton install`
  - [x] `test` = `composer -d framework test`
  - [x] `test-fast` = `composer -d framework test-fast` (if defined; otherwise maps to `test`)
  - [x] `test-slow` = `composer -d framework test-slow` (if defined; otherwise N/A)
  - [x] `ci` = `composer validate` + `composer test` (+ optional `composer test-fast`)
- [x] `framework/composer.json`:
  - [x] `test` = `vendor/bin/phpunit -c tools/testing/phpunit.xml`
  - [x] `test-fast` / `test-slow` = conventions (groups/testsuites), may be introduced progressively

#### Artifacts / outputs (if applicable)

N/A (Prelude tooling only; no deterministic runtime artifacts yet)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [x] Tooling output MUST be deterministic and MUST NOT print secrets/PII

#### Errors

N/A

#### Security / Redaction

- [x] MUST block unmanaged edits of managed composer repositories via `.githooks/pre-commit` (sync `--check`)
- [x] MUST write backups before applying managed-block changes (backups live under `framework/var/backups/*` and are ignored)
- [x] MUST NOT (Prelude-safe constraints):
  - [x] MUST NOT add runtime skeleton overrides: `skeleton/config/**` (e.g. `skeleton/config/http.php`, `skeleton/config/modes/*.php`)
  - [x] MUST NOT add scripts that call framework runtime/CLI (`coretsia`, `doctor`, `cache:verify`) in `composer.json` at this stage
  - [x] MUST NOT claim or require arch rails (`deptrac`, gates) in CI/scripts at this stage

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] Add an integration test that fails if the managed composer repositories guard is removed:
  - [x] `framework/tools/tests/Integration/ManagedComposerRepositoriesGuardTest.php`
    - [x] asserts: drift in `repositories` causes `sync --check` to fail (guard present)
    - [x] asserts: running `php framework/tools/build/sync_composer_repositories.php` restores canonical state
    - [x] asserts: rerun sync is rerun-no-diff (stable output; no additional changes)

### Tests (MUST)

- Unit / Contract:
  - N/A (unless tools code has pure functions worth unit tests)

- Integration:
  - [x] `composer validate` passes in root/framework/skeleton
  - [x] `php framework/tools/build/sync_composer_repositories.php` rerun-no-diff
  - [x] `php framework/tools/build/sync_composer_repositories.php --check` passes on canonical state

- Gates/Arch:
  - N/A (arch rails are introduced later; Prelude must not claim them)

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] No forward refs (no `coretsia`/front-controller dependencies)
- [x] Out of scope (deferred; MUST NOT be introduced here):
  - [x] `coretsia doctor` / `coretsia cache:verify`
  - [x] `composer serve` / front controller wiring
  - [x] any runtime artifacts beyond directory scaffolding (keep files only)
- [x] `framework/tools/build/sync_composer_repositories.php` is idempotent (rerun-no-diff)
- [x] `framework/tools/build/sync_composer_repositories.php` MUST be runnable on a clean clone without vendor deps (self-contained; no autoload requirement)
- [x] Pre-commit blocks unmanaged edits (via `--check`); backups exist
- [x] `composer setup` and `composer ci` run on a clean clone without hidden prerequisites
- [x] Dependency determinism:
  - [x] CI uses `composer install` (NOT update) and must not modify any `composer.lock`
  - [x] Local rerun: `composer install` (root/framework/skeleton) results in no diff
- [x] Lock policy:
  - [x] `composer.lock` / `framework/composer.lock` / `skeleton/composer.lock` are committed
  - [x] CI enforces no drift (job fails if any lock changed)
  - [x] Any lock change MUST be an explicit developer action committed in PR (never produced implicitly by CI)
- [x] CI runs monorepo tests (not CLI-only) using the canonical PHPUnit config:
  - [x] failing tests in any package fail the CI job
- [x] Command UX is single-source (no ambiguity):
  - [x] Prelude docs/guides MUST NOT reference `./dev/**` (it does not exist)
  - [x] Canonical commands in Prelude are repo-root `composer ...`

---

### PRELUDE.40.0 Phase 0 build order (recommended, NOT a dependency graph) (SHOULD) [DOC]

---
type: docs
phase: PRELUDE
epic_id: "PRELUDE.40.0"
owner_path: "docs/roadmap/phase0/"

goal: "There is a short recommended implementation order for Phase 0 that reduces cycle risk."
provides:

- "Human-readable recommended order for Phase 0 execution"
- "Onboarding-friendly navigation for Phase 0 work"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs root exists
  - PRELUDE.50.0 — Phase 0 dependency SSoT exists (this doc must link to it)

- Required deliverables (exact paths):
  - `docs/roadmap/phase0/00_2-dependency-table.md` — Phase 0 dependency SSoT exists

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- N/A

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [x] `docs/roadmap/phase0/00_1-build-order.md` — recommended order (explicitly NOT a dependency graph)

#### Modifies

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A

### Tests (MUST)

N/A

### DoD (MUST)

- [x] Doc exists and is readable as standalone guidance
- [x] Doc does NOT claim compile-time dependency truth (explicitly “recommended order” only)
- [x] Doc MUST link to the Phase 0 dependency SSoT:
  - [x] references `docs/roadmap/phase0/00_2-dependency-table.md` as the single source of truth for compile-time deps
- [x] No forward references required to understand the doc

---

### PRELUDE.50.0 Phase 0 dependency table (SSoT) (MUST) [DOC]

---
type: docs
phase: PRELUDE
epic_id: "PRELUDE.50.0"
owner_path: "docs/roadmap/phase0/"

goal: "A single document defines Phase 0 compile-time dependency law."
provides:

- "SSoT table for Phase 0 compile-time deps (deptrac-enforceable later)"
- "Invariants for discovery boundaries (no runtime FS scan)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs root exists

- Required deliverables (exact paths):
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (doc-only)

Forbidden:

- N/A

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [x] `docs/roadmap/phase0/00_2-dependency-table.md` — Phase 0 dependency table + invariants
  - [x] The document MUST define a stable SSoT table format (parse-friendly; single-choice):
    - [x] Columns: `package_id` | `depends_on` | `notes` (optional)
    - [x] `package_id` uses `<layer>/<slug>`
    - [x] `depends_on` is `—` or a comma-separated list of `<layer>/<slug>`
    - [x] Rows MUST be sorted by `package_id` ascending
    - [x] `depends_on` entries MUST be sorted ascending
  - [x] The document MUST include a Phase 0 baseline table (at minimum):
    - [x] `core/contracts` → —
    - [x] `core/foundation` → `core/contracts`
    - [x] `core/kernel` → `core/contracts`, `core/foundation`
    - [x] `platform/cli` → `core/kernel`, `core/foundation`
    - [x] `platform/errors` → `core/contracts`, `core/foundation`
    - [x] `platform/tracing` → `core/contracts`, `core/foundation`
    - [x] `platform/metrics` → `core/contracts`, `core/foundation`
    - [x] `platform/logging` → `core/contracts`, `core/foundation`
    - [x] `platform/problem-details` → `core/contracts`, `core/foundation`
    - [x] `platform/http` → `core/kernel`, `core/foundation`, `core/contracts`

#### Modifies

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (doc-only; enforcement introduced by later tooling epics)

### Tests (MUST)

N/A

### DoD (MUST)

- [x] Table is internally consistent (no cycles declared)
- [x] Table format is stable and unambiguous (columns + sorting rules are explicitly specified)
- [x] Invariants written explicitly:
  - [x] runtime module discovery = composer metadata only
  - [x] tooling package index is tooling-only (never runtime input)

---

### PRELUDE.60.0 Development workflow (Phase 0, canonical commands) (MUST) [DOC]

---
type: docs
phase: PRELUDE
epic_id: "PRELUDE.60.0"
owner_path: "docs/guides/"

goal: "A newcomer can follow a canonical workflow without manual composer repository edits."
provides:

- "Step-by-step workflow for adding packages and running baseline checks"
- "Canonical command entrypoints anchored in repo root"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.30.0 — repo-root composer entrypoints + tooling scripts exist

- Required deliverables (exact paths):
  - `.githooks/pre-commit` — hooks exist (enabled by `composer setup`)
  - `framework/tools/build/new-package.php` — package creation tool exists
  - `framework/tools/build/sync_composer_repositories.php` — managed sync exists
  - `framework/tools/testing/phpunit.xml` — monorepo PHPUnit harness exists
  - `composer.json` — root scripts exist
  - `docs/guides/onboarding.md` — onboarding exists (from PRELUDE.30.0)
  - `docs/guides/quickstart.md` — quickstart exists (from PRELUDE.30.0)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (doc-only)

Forbidden:

- N/A

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- CLI:
  - `php framework/tools/build/new-package.php --layer=<...> --slug=<...>` (example)
  - `php framework/tools/build/sync_composer_repositories.php`
  - `composer setup`
  - `composer ci`

- Canonical Prelude workflow (after PRELUDE.30.0; no forward refs):
  - `composer setup` (repo root; enables hooks, runs sync, installs deps)
  - `composer ci` (Prelude-only: validate + test; no deptrac/gates claims)
  - (optional explicit tooling) `php framework/tools/build/sync_composer_repositories.php` (rerun-no-diff)

- Extension points (later, when available; NOT required in Prelude):
  - dependency analysis / arch rails (e.g. dep graph enforcement)
  - package compliance gates per package

- Project-wide tests (canonical):
  - `composer test` (repo root) — runs the full monorepo suite
  - `composer test-fast` (repo root) — fast subset (if defined)
  - `composer test-slow` (repo root) — slow subset (if defined)

### Deliverables (MUST)

#### Creates

- [x] `docs/guides/development-workflow.md` — canonical workflow (run from repo root)

#### Modifies

- [x] `docs/guides/onboarding.md` — add a short “Next: development workflow” section linking to `docs/guides/development-workflow.md`
- [x] `docs/guides/quickstart.md` — add a short “After setup” section linking to `docs/guides/development-workflow.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A

### Tests (MUST)

N/A

### DoD (MUST)

- [x] Doc includes invariant: commands are run from repo root
- [x] Doc does not require tools that are not introduced yet (no forward refs)
- [x] Doc is consistent with repo-root `composer ...` entrypoints; MUST NOT reference `./dev/**`
- [x] Onboarding/Quickstart navigation points to the workflow doc (discoverable on clean clone)
