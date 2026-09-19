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

# Commands (SSoT)

> Scope: Canonical command catalog. \
> Normative: MUST / MUST NOT / SHOULD / MAY \
> Source of truth for workflow rules: `docs/roadmap/ROADMAP.md` \
> See also (workflow): `docs/guides/development-workflow.md`

This document fixes the canonical commands/entrypoints that actually exist in the repository, and the rules for documenting them.

---

## Global rules (applies to all commands)

- Commands in this document MUST be executed from the repo root (if a command requires otherwise, that MUST be explicitly stated in the entry).
- Adding/changing/removing a command MUST be accompanied by an update to this file.
- Canonical entrypoints policy: in this SSoT, canonical entrypoints are:
  - repo-root `composer <script>` (scripts from root `composer.json`) — preferred canonical entrypoints;
  - `php <repo-relative-path>` — DIRECT canonical entrypoints only if the command does not yet have a repo-root composer wrapper.
- If a command is documented as DIRECT (`php ...`), it SHOULD receive a repo-root `composer <script>` wrapper by the next cutline/milestone.
- For every command, the following MUST be stated clearly:
  - canonical path/entrypoint,
  - outputs (what exactly is created/updated),
  - determinism policy (deterministic vs nondeterministic modes),
  - usage examples.
- If a command has a mode/flag that makes output nondeterministic, that mode MUST be marked as NONDETERMINISTIC and MUST NOT be used in CI rails / rerun-no-diff workflows.
- Documentation/examples MUST avoid “non-existent” entrypoints for the current context (for example, do not reference non-existent `./dev/**` entrypoints).
- If a command has an alias (for example, `composer ...` as a proxy to `coretsia ...`), the document MUST explicitly state:
  - which entrypoint is canonical, and which one is alias/compat, and MUST guarantee behavior equivalence (semantics/outputs) in deterministic mode.
- When an entrypoint is migrated (the canonical one changes), the previous canonical entrypoint SHOULD remain as a compat alias for at least 1 epic/phase (or until the next cutline), and MUST be marked as `DEPRECATED` with a “remove-after” milestone.

### Tooling output policy

- This policy applies to tool-owned output, not to Composer's own script prelude lines such as `> @php ...`.
- Gates MUST emit no output on success.
- Gates MUST emit a stable error code on line 1 on failure.
- Check commands SHOULD emit no output on success, especially when used in aggregate CI rails.
- Check commands MUST emit deterministic failure output when drift or policy violations are detected.
- Generate/sync commands MAY emit a short deterministic success summary, such as `OK` and repo-relative changed paths.
- External tools MAY keep their native output.
- Default test runners SHOULD avoid discovery banners unless explicitly requested by a verbose/list flag.

---

## Entry format (how to extend this file)

Each new command is added as a separate section under `## Commands` (the format currently used is `### <title>`), in the following form:

- Id: stable id (snake / kebab, without spaces)
- Entrypoint: canonical entrypoint (repo-root)
- Category: classification (informational)
- Outputs: list of files/directories that are created/updated
- Determinism: mode table (deterministic / nondeterministic)
- Notes: semantics / invariants / CI policy / “under the hood” (MUST NOT duplicate lists of canonical entrypoints)
- Usage: usage examples (list). If a command has several canonical variants, all of them are listed here.

---

## Commands

### Monorepo workspace setup

Id: `project.setup` \
Entrypoint: `composer setup` \
Category: workspace bootstrap \
Outputs:
- (local) Git config: enables hooks via `core.hooksPath=.githooks`
- Potential update to root `composer.json` managed repositories block
- Potential update to root `composer.json` internal `coretsia/*` `require-dev` constraints
- Potential updates to publishable package `composer.json` internal `coretsia/*` constraints under `packages/**`
- `vendor/**` *(untracked)*
- (optional, only if repositories drift is detected) `var/backups/workspace/**` *(gitignored)*
- (optional, only if release-line Composer metadata drift is detected) `var/backups/release-line/**` *(gitignored)*

Determinism:

| Mode / flags | Determinism   | Notes                                                                                                                         |
|--------------|---------------|-------------------------------------------------------------------------------------------------------------------------------|
| default      | deterministic | Deterministic w.r.t. tracked files after successful apply; installs from `composer.lock`. Network I/O is expected (Composer). |

Notes:
- `composer setup` is an aggregate entrypoint and executes (in order):
  1) `composer hooks:install`
  2) `composer sync:repos`
  3) `composer release-line:workspace:sync`
  4) `composer release-line:public-constraints:sync`
  5) `composer --no-interaction install --prefer-dist`
  6) `composer --no-interaction validate --strict`
- `composer sync:repos` MAY create backups under `var/backups/workspace/**` only if repository drift is detected and files are changed.
- `composer release-line:workspace:sync` updates root `composer.json` internal `coretsia/*` `require-dev` constraints to release-line `devVersion`.
- `composer release-line:public-constraints:sync` updates existing package `composer.json` internal `coretsia/*` dependencies to release-line `publicConstraint`.
- Release-line apply steps run before installs so local workspace dependency resolution sees the canonical release-line constraints before Composer install/update work starts.

Usage (repo root):
- `composer setup`

---

### CI rails (project-wide)

Id: `project.ci` \
Entrypoint: `composer ci` \
Category: CI / verification \
Outputs:
- No tracked outputs on success (MUST be rerun-no-diff w.r.t. tracked files)
- Installs root workspace dependencies into `vendor/**` (untracked) and runs validation + gates + DTO rail + arch + quality + tests
- Fails if `composer.lock` changes after install

Determinism:

| Mode / flags | Determinism   | Notes                                                                                                             |
|--------------|---------------|-------------------------------------------------------------------------------------------------------------------|
| default      | deterministic | Deterministic w.r.t. tracked files; includes managed repository drift, release-line drift, and lock drift guards. |

Notes:
- `composer ci` is an aggregate rails command and executes (in order):
  1) `composer sync:check`
  2) `composer release-line:workspace:check`
  3) `composer release-line:public-constraints:check`
  4) `composer --no-interaction install --prefer-dist`
  5) `composer --no-interaction validate --strict`
  6) `composer gates`
  7) `composer dto:gate`
  8) `composer arch`
  9) `composer quality`
  10) `composer test`
  11) `composer lock:check`
- Release-line drift checks run before installs:
  - `composer release-line:workspace:check`
  - `composer release-line:public-constraints:check`
- `composer gates` is the canonical baseline/tooling gates aggregate rail and includes `composer package-publish-safety:gate`.
- `composer dto:gate` is the canonical aggregate DTO policy rail and MUST run after baseline gates and before arch/quality/tests.
- `composer arch` is the canonical aggregate architecture rail and MUST remain rerun-no-diff.
- `composer quality` is a third-party quality aggregate rail and MAY emit native ECS/PHPStan diagnostics.
- `composer test` MUST support args-forwarding via `--` (see `project.test`).
- Dedicated GitHub workflows may run additional CI-only rails that are intentionally not part of the local `composer ci` aggregate, such as architecture generator evidence.

Usage (repo root):
- `composer ci`

---

### Package index (arch) check

Id: `tool.arch_package_index_check` \
Entrypoint: `composer arch:package-index:check` \
Category: architecture / guard \
Outputs:
- none on success
- exits non-zero if package index drift is detected
- exits non-zero on unexpected failure

Determinism:

| Mode / flags | Determinism   | Notes                                                   |
|--------------|---------------|---------------------------------------------------------|
| default      | deterministic | Pure check; MUST be rerun-no-diff w.r.t. tracked files. |

Notes:
- Checks drift vs the generated artifact: `tools/testing/package-index.php`.
- Scope: the generated index contains layered packages only; special distributions `packages/framework` and `packages/applications/skeleton` are intentionally excluded.
- Implementation detail: `@php tools/build/package_index.php --check`.
- Success output policy:
  - emits no output when `tools/testing/package-index.php` is already up to date.
- Failure output policy:
  - drift: line 1 is stable code `CORETSIA_PACKAGE_INDEX_OUT_OF_DATE`
  - unexpected failure: line 1 starts with stable code `CORETSIA_PACKAGE_INDEX_FAILED`

Usage (repo root):
- `composer arch:package-index:check`

---

### Package index (arch) generate

Id: `tool.arch_package_index_generate` \
Entrypoint: `composer arch:package-index:generate` \
Category: architecture / generator \
Outputs:
- Updates generated package index artifact: `tools/testing/package-index.php`

Determinism:

| Mode / flags | Determinism   | Notes                                                               |
|--------------|---------------|---------------------------------------------------------------------|
| default      | deterministic | Deterministic generator; MUST be rerun-no-diff for same repo state. |

Notes:
- Scope: the generated index contains layered packages only; special distributions `packages/framework` and `packages/applications/skeleton` are intentionally excluded.
- Tool supports overriding output path via `--out`, but canonical workflow uses the default artifact path above.
- Implementation detail: `@php tools/build/package_index.php --apply`.
- Failure output policy:
  - unexpected failure: line 1 starts with stable code `CORETSIA_PACKAGE_INDEX_FAILED`

Usage (repo root):
- `composer arch:package-index:generate`

---

### Architecture aggregate rail

Id: `tool.arch` \
Entrypoint: `composer arch` \
Category: architecture / CI rail \
Outputs:
- none on success
- MUST NOT update tracked generated files
- MUST NOT materialize graph artifacts; graph generation is owned by `composer arch:deptrac:generate`

Determinism:

| Mode / flags    | Determinism   | Notes                                                                     |
|-----------------|---------------|---------------------------------------------------------------------------|
| `composer arch` | deterministic | Aggregate check/analyze rail; MUST be rerun-no-diff w.r.t. tracked files. |

Notes:
- Purpose: executes the canonical architecture verification rail.
- Execution order is cemented:
  1) `composer arch:package-index:check`
  2) `composer arch:deptrac:check`
  3) `composer arch:deptrac:analyze`
- Through `composer arch:deptrac:check`, this aggregate also validates that internal production Composer `require` edges between layered packages are allowed by the direct `depends_on` cells of `docs/architecture/DEPENDENCIES.md`.
- This command is a check/analyze aggregate. It does not run `composer arch:package-index:generate` or `composer arch:deptrac:generate`.
- CI may run `composer arch:deptrac:generate` separately to materialize Deptrac graph artifacts for upload, but that is not part of the `composer arch` aggregate.
- Implementation detail: aggregate `arch` script in `composer.json`.

Usage (repo root):
- `composer arch`

---

### Test suite (project-wide)

Id: `project.test` \
Entrypoint: `composer test` \
Category: testing \
Outputs:
- No tracked outputs on success
- `var/phpunit/phpunit.discovered.xml` *(gitignored; generated runtime artifact)*

Determinism:

| Mode / flags           | Determinism   | Notes                                                                        |
|------------------------|---------------|------------------------------------------------------------------------------|
| default                | deterministic | Runs the canonical tools suite plus all discovered package test suites once. |
| `--list-packages`      | deterministic | Prints discovered package test directories before PHPUnit.                   |
| `--file=<path>`        | deterministic | Runs one validated repo-relative test file.                                  |
| `--package=<selector>` | deterministic | Runs one package, package family, or unique package basename.                |
| `--repeat=<N>`         | deterministic | Repeats the resolved PHPUnit target `N` times in fresh processes.            |

Notes:
- `composer test` is the canonical repo-root entrypoint for `tools/tests/**` and discovered package tests under `packages/**`.
- Execution order is cemented:
  1) `composer package:phpunit:gate`
  2) package-discovery PHPUnit runner
- Runner semantics:
  - default mode runs `tools/tests/**` plus all discovered package test suites once and emits no package discovery banners
  - `--list-packages` prints discovered package test directories as `package: <repo-relative-tests-dir>`
  - `--file=<path>` accepts one test file under `tools/tests/**` or the `tests/**` tree of any discovered package product; paths are repo-relative and validated fail-closed
  - `--package=<selector>` accepts an exact package path selector (`core/kernel`), exact Composer package name (`coretsia/core-kernel`), unique package basename (`kernel`), or package family (`core`); missing or ambiguous selectors fail closed
  - `--file` and `--package` are mutually exclusive
  - `--repeat=<N>` accepts `1..1000`, may be combined with `--file` or `--package`, and runs every iteration in a fresh PHPUnit process
  - repeat mode runs all requested iterations, returns non-zero if any iteration fails, and emits a deterministic final summary
  - PHPUnit-native arguments such as `--filter`, `--group`, and `--testsuite` continue to be forwarded to PHPUnit
  - generates `var/phpunit/phpunit.discovered.xml` once per runner invocation
- Policy:
  - package-local `phpunit.xml` / `phpunit.dist.xml` are forbidden for every discovered product under `packages/**`, including special distributions
  - canonical source of truth for `tools/tests/**` and discovered package PHPUnit configuration is `tools/testing/phpunit.xml`
  - generated artifact `var/phpunit/phpunit.discovered.xml` is runtime-only and MUST NOT be hand-edited
  - package/file targeting does not introduce package-local or target-specific PHPUnit config files
- `--strict` is consumed by the package-discovery PHPUnit runner, but MUST NOT be interpreted as a requirement for package-local PHPUnit config files.

Usage (repo root):
- `composer test`
- `composer test -- --list-packages`
- `composer test -- --file=packages/platform/worker/tests/Integration/WorkerSupervisorGuardianFenceRaceTest.php`
- `composer test -- --package=worker`
- `composer test -- --package=core/kernel`
- `composer test -- --package=coretsia/core-kernel`
- `composer test -- --package=core`
- `composer test -- --repeat=20`
- `composer test -- --repeat=20 --package=kernel`
- `composer test -- --repeat=50 --file=packages/platform/worker/tests/Integration/WorkerSupervisorGuardianFenceRaceTest.php`
- `composer test -- --filter <pattern>`
- `composer test -- --group contract`
- `composer test -- --testsuite all`

---

### Managed composer repositories (apply)

Id: `tool.sync_repos_apply` \
Entrypoint: `composer sync:repos` \
Category: build tooling / workspace policy \
Outputs:
- Potential update to root `composer.json` *(managed repositories block only; canonicalized)*
- (optional) `var/backups/workspace/**` *(gitignored; duplicate backup names use numeric suffixes)*

Determinism:

| Mode / flags | Determinism   | Notes                                                       |
|--------------|---------------|-------------------------------------------------------------|
| default      | deterministic | Deterministic w.r.t. tracked files; backups are gitignored. |

Notes:
- Canonical manager implementation: `php tools/build/sync_composer_repositories.php`.
- Root workspace owns one managed `type = "path"` repository entry per product discovered by `WorkspacePackageCatalog`.
- The managed set includes the special distributions `packages/framework` and `packages/applications/skeleton` plus every discovered layered package.
- Each managed entry uses the product's exact repo-relative package path as `url`.
- Each managed entry uses `options.symlink = true`, `options.reference = "config"`, and `coretsia_managed = true`.
- Each managed entry contains exactly one `options.versions` mapping from the product's `composer.json.name` to release-line `devVersion`.
- Managed entries form one canonical contiguous block before unmanaged/user repository entries and are ordered by catalog product path.
- Package identity is read from each package `composer.json.name`; it is not derived from filesystem depth or layer name.
- Release-line data source: `tools/release/release-line.json`.
- Implementation detail: `@php tools/build/sync_composer_repositories.php`.
- Failure output policy:
  - unexpected failure: line 1 starts with stable code `CORETSIA_WORKSPACE_SYNC_FAILED`

Usage (repo root):
- `composer sync:repos`

---

### Managed composer repositories (check)

Id: `tool.sync_repos_check` \
Entrypoint: `composer sync:check` \
Category: build tooling / guard \
Outputs:
- none on success
- exits non-zero on invalid managed block, repository drift, or unexpected failure

Determinism:

| Mode / flags | Determinism   | Notes                                   |
|--------------|---------------|-----------------------------------------|
| default      | deterministic | MUST be used in CI and pre-commit hook. |

Notes:
- Canonical manager implementation: `php tools/build/sync_composer_repositories.php`.
- Recomputes the complete canonical managed repository block from `WorkspacePackageCatalog` and release-line `devVersion`, then compares it with root `composer.json`.
- Each managed entry must have the canonical product path, `type = "path"`, `options.symlink = true`, `options.reference = "config"`, one canonical `options.versions` mapping, and `coretsia_managed = true`.
- The managed entries must form one canonical contiguous block; missing, extra, reordered, or otherwise drifted managed entries fail the check.
- Release-line data source: `tools/release/release-line.json`.
- Implementation detail: `@php tools/build/sync_composer_repositories.php --check`.
- Success output policy:
  - emits no output when repositories are already in canonical state.
- Failure output policy:
  - invalid managed block: line 1 is stable code `CORETSIA_WORKSPACE_MANAGED_BLOCK_INVALID`
  - managed repository drift: line 1 is stable code `CORETSIA_WORKSPACE_MANAGED_REPOS_OUT_OF_SYNC`
  - unexpected failure: line 1 starts with stable code `CORETSIA_WORKSPACE_SYNC_FAILED`

Usage (repo root):
- `composer sync:check`

---

### Release-line workspace constraints sync

Id: `tool.release_line_workspace_sync` \
Entrypoint: `composer release-line:workspace:sync` \
Category: build tooling / release-line workspace policy \
Outputs:
- Potential update to root `composer.json` `require-dev`
- (optional, only if drift is applied) `var/backups/release-line/**` *(gitignored)*

Determinism:

| Mode / flags | Determinism   | Notes                                                                           |
|--------------|---------------|---------------------------------------------------------------------------------|
| default      | deterministic | Mutating apply mode; MUST be rerun-no-diff for the same repo state after apply. |

Notes:
- Purpose: synchronizes root workspace internal `coretsia/*` dev constraints from `tools/release/release-line.json`.
- `tools/release/release-line.json` is the tooling SSoT for:
  - `currentMinor`
  - `devVersion`
  - `publicConstraint`
- `schemaVersion` in `release-line.json` is the file schema version, not the package release version.
- The command validates release-line consistency:
  - `devVersion` MUST equal `<currentMinor>.x-dev`
  - `publicConstraint` MUST equal `^<currentMinor>.0`
- The command discovers publishable Composer manifests under `packages/**`.
- Package identity is read from `composer.json.name`; filesystem location is not the package-name source of truth.
- The command rewrites managed internal `coretsia/*` requirements in root `composer.json` `require-dev` to release-line `devVersion`.
- Requirement ordering policy:
  - `ext-*` requirements remain before internal package requirements
  - internal `coretsia/*` requirements are sorted by `strcmp`
  - external dev tooling requirements remain after internal package requirements
- Implementation detail: `@php tools/release/sync_workspace_release_line.php`.
- Failure output policy:
  - unexpected failure: line 1 starts with stable code `CORETSIA_RELEASE_LINE_WORKSPACE_SYNC_FAILED`.

Usage (repo root):
- `composer release-line:workspace:sync`

---

### Release-line workspace constraints check

Id: `tool.release_line_workspace_check` \
Entrypoint: `composer release-line:workspace:check` \
Category: build tooling / guard \
Outputs:
- none on success; read-only check
- exits non-zero if root `composer.json` internal `coretsia/*` `require-dev` constraints drift from release-line `devVersion`
- exits non-zero on unexpected failure

Determinism:

| Mode / flags | Determinism   | Notes                                                      |
|--------------|---------------|------------------------------------------------------------|
| default      | deterministic | Read-only drift check; MUST be used in CI before installs. |

Notes:
- Read-only counterpart of `composer release-line:workspace:sync`.
- The command validates the same release-line and package discovery invariants as apply mode.
- The command MUST NOT create backups and MUST NOT rewrite composer files.
- CI policy:
  - `composer ci` runs this command after `composer sync:check` and before `composer install`.
- Success output policy:
  - emits no output when workspace constraints are already aligned with release-line `devVersion`.
- Failure output policy:
  - drift: line 1 is stable code `CORETSIA_RELEASE_LINE_WORKSPACE_OUT_OF_SYNC`
  - unexpected failure: line 1 starts with stable code `CORETSIA_RELEASE_LINE_WORKSPACE_SYNC_FAILED`
  - diagnostics use repo-relative paths and are sorted deterministically.

Usage (repo root):
- `composer release-line:workspace:check`

---

### Release-line package public constraints sync

Id: `tool.release_line_public_constraints_sync` \
Entrypoint: `composer release-line:public-constraints:sync` \
Category: build tooling / release-line package policy \
Outputs:
- Potential updates to publishable package `composer.json` files under `packages/**`
- (optional, only if drift is applied) `var/backups/release-line/**` *(gitignored)*

Determinism:

| Mode / flags | Determinism   | Notes                                                                           |
|--------------|---------------|---------------------------------------------------------------------------------|
| default      | deterministic | Mutating apply mode; MUST be rerun-no-diff for the same repo state after apply. |

Notes:
- Purpose: synchronizes existing package `composer.json` internal `coretsia/*` dependency constraints from `tools/release/release-line.json`.
- The command scans all discovered publishable package manifests under `packages/**`, not only split-publish allowlisted packages.
- The command scans only these dependency sections:
  - `require`
  - `require-dev`
- The command rewrites only existing internal `coretsia/*` dependency constraints to release-line `publicConstraint`.
- The command MUST NOT add missing dependencies.
- The command MUST NOT rewrite:
  - external package constraints
  - `php`
  - `ext-*`
  - `suggest`
  - `provide`
  - `replace`
  - `conflict`
- The command MUST NOT add a package-local `version` field.
- Implementation detail: `@php tools/release/sync_package_public_constraints.php`.
- Failure output policy:
  - unexpected failure: line 1 starts with stable code `CORETSIA_RELEASE_LINE_PUBLIC_CONSTRAINTS_SYNC_FAILED`.

Usage (repo root):
- `composer release-line:public-constraints:sync`

---

### Release-line package public constraints check

Id: `tool.release_line_public_constraints_check` \
Entrypoint: `composer release-line:public-constraints:check` \
Category: build tooling / guard \
Outputs:
- none on success; read-only check
- exits non-zero if any package `composer.json` internal `coretsia/*` dependency constraint drifts from release-line `publicConstraint`
- exits non-zero on unexpected failure

Determinism:

| Mode / flags | Determinism   | Notes                                                      |
|--------------|---------------|------------------------------------------------------------|
| default      | deterministic | Read-only drift check; MUST be used in CI before installs. |

Notes:
- Read-only counterpart of `composer release-line:public-constraints:sync`.
- The command validates the same release-line, package discovery, and package-name invariants as apply mode.
- The command MUST NOT create backups and MUST NOT rewrite composer files.
- CI policy:
  - `composer ci` runs this command after `composer release-line:workspace:check` and before `composer install`.
- Success output policy:
  - emits no output when package public constraints are already aligned with release-line `publicConstraint`.
- Failure output policy:
  - drift: line 1 is stable code `CORETSIA_RELEASE_LINE_PUBLIC_CONSTRAINTS_OUT_OF_SYNC`
  - unexpected failure: line 1 starts with stable code `CORETSIA_RELEASE_LINE_PUBLIC_CONSTRAINTS_SYNC_FAILED`
  - diagnostics use repo-relative paths and are sorted deterministically.

Usage (repo root):
- `composer release-line:public-constraints:check`

---

### Lock drift guard

Id: `tool.lock_check` \
Entrypoint: `composer lock:check` \
Category: CI guard \
Outputs:
- none (exits non-zero if `composer.lock` changed)

Determinism:

| Mode / flags | Determinism   | Notes                                                              |
|--------------|---------------|--------------------------------------------------------------------|
| default      | deterministic | Validates that tracked `composer.lock` is unchanged after install. |

Notes:
- Tracked workspace lockfile:
  - `composer.lock`

Usage (repo root):
- `composer lock:check`

---

### New package generator

Id: `tool.new_package` \
Entrypoint: `composer package:new` \
Category: build tooling / scaffolding \
Outputs:
- `packages/<layer>/<slug>/**` (scaffolded package tree; exact contents defined by the generator and packaging law)
- (local, gitignored) `var/tmp/` may be created as the staging parent; per-package `var/tmp/new-package-*` staging trees are cleaned before exit

Determinism:

| Mode / flags | Determinism   | Notes                                                                           |
|--------------|---------------|---------------------------------------------------------------------------------|
| default      | deterministic | Deterministic w.r.t. tracked files for the same inputs (`layer`,`slug`,`kind`). |

Notes:
- Required args: `--layer=<core|platform|integrations|enterprise|devtools|presets>`, `--slug=<kebab-case>`, `--kind=<library|runtime>`.
- This command is an orchestration entrypoint only.
- Package scaffold completion policy is owned by `composer package-scaffold:sync`.
- After creating the baseline package shell, the generator invokes package scaffold sync for the newly created package path.
- It MUST NOT duplicate canonical scaffold completion policy internally.
- The command does not synchronize root workspace repositories, root `require-dev`, package index, or Deptrac artifacts; those remain owned by their dedicated sync/generate commands.
- Success output is deterministic: `OK` followed by the created repo-relative package path.
- Implementation detail: `@php tools/build/new-package.php`.
- Failure output policy:
  - unexpected failure: line 1 starts with stable code `CORETSIA_NEW_PACKAGE_FAILED`
- Optional: supports `--repo-root` (implementation detail for tooling / fixtures).

Usage (repo root):
- `composer package:new -- --layer=core --slug=example --kind=library`
- `composer package:new -- --layer=platform --slug=cli --kind=runtime`

---

### Package scaffold sync

Id: `tool.package_scaffold_sync` \
Entrypoint: `composer package-scaffold:sync` \
Category: build tooling / scaffolding \
Outputs:
- Creates or updates package scaffold artifacts under `packages/<layer>/<slug>/**` or under the package path passed as an argument.
- Exact-canonical sync is allowed only for:
  - `LICENSE`
  - `NOTICE`
  - `SECURITY.md`
- Create-if-missing only, without overwriting existing user-owned content:
  - `README.md`
  - `tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - runtime-only scaffold files/directories

Determinism:

| Mode / flags                               | Determinism   | Notes                                                                                         |
|--------------------------------------------|---------------|-----------------------------------------------------------------------------------------------|
| `composer package-scaffold:sync`           | deterministic | Mutating; scans `packages/<layer>/<slug>` and creates/fixes scaffold files.                   |
| `composer package-scaffold:sync -- <path>` | deterministic | Mutating; narrows the discovered layered-package scope to `<path>` or one discovered package. |

Notes:
- This is the single source of truth for package scaffold completion.
- Default scope is layered packages discovered by `WorkspacePackageCatalog::layeredPackages()`; special distributions `packages/framework` and `packages/applications/skeleton` are not scaffold-sync targets.
- The command is deterministic but mutating.
- Apply mode MUST be rerun-no-diff for the same repo state after the first successful run.
- It MUST NOT rewrite existing user-owned README/config/code/test content once present.
- Canonical package file policy:
  - `LICENSE` is synchronized exactly from repo-root `LICENSE`.
  - `NOTICE` is synchronized exactly from repo-root `NOTICE`.
  - `SECURITY.md` is synchronized exactly from repo-root `SECURITY.md`.
- Runtime-only scaffold is created only for packages with `composer.json > extra.coretsia.kind = runtime`.
- Implementation detail: `@php tools/build/sync_package_scaffold.php`.
- Failure output policy:
  - unexpected failure: line 1 starts with stable code `CORETSIA_PACKAGE_SCAFFOLD_SYNC_FAILED`.

Usage (repo root):
- `composer package-scaffold:sync`
- `composer package-scaffold:sync -- packages/core/example`

---

### Package scaffold check

Id: `tool.package_scaffold_check` \
Entrypoint: `composer package-scaffold:check` \
Category: build tooling / guard \
Outputs:
- none; read-only check
- exits non-zero if scaffold drift is detected

Determinism:

| Mode / flags                                | Determinism   | Notes                                                                                          |
|---------------------------------------------|---------------|------------------------------------------------------------------------------------------------|
| `composer package-scaffold:check`           | deterministic | Read-only; scans `packages/<layer>/<slug>`.                                                    |
| `composer package-scaffold:check -- <path>` | deterministic | Read-only; narrows the discovered layered-package scope to `<path>` or one discovered package. |

Notes:
- This is the read-only verification mode for package scaffold sync.
- It MUST NOT create, modify, or delete files.
- It fails on missing or drifted canonical legal files.
- It fails on missing create-if-missing scaffold artifacts.
- Output policy:
  - scaffold drift: line 1 is stable code `CORETSIA_PACKAGE_SCAFFOLD_OUT_OF_SYNC`
  - unexpected failure: line 1 starts with stable code `CORETSIA_PACKAGE_SCAFFOLD_SYNC_FAILED`
  - diagnostics use relative paths only and are sorted by `strcmp`
- Implementation detail: `@php tools/build/sync_package_scaffold.php --check`.

Usage (repo root):
- `composer package-scaffold:check`
- `composer package-scaffold:check -- packages/platform/example`

---

### Package compliance gate

Id: `tool.package_compliance_gate` \
Entrypoint: `composer package-compliance:gate` \
Category: repo policy / guard \
Outputs:
- none; read-only gate
- exits non-zero on package compliance violations

Determinism:

| Mode / flags                                   | Determinism   | Notes                                       |
|------------------------------------------------|---------------|---------------------------------------------|
| `composer package-compliance:gate`             | deterministic | Read-only; scans `packages/<layer>/<slug>`. |
| `composer package-compliance:gate -- --path=…` | deterministic | Read-only scan override for tests/tools.    |

Notes:
- Purpose: enforces canonical package shape and metadata policy.
- The gate is read-only and MUST NOT create, modify, or delete files.
- Scanned scope by default: `packages/<layer>/<slug>`.
- Scope is limited to layered products from `WorkspacePackageCatalog::layeredPackages()`; `packages/framework` and `packages/applications/skeleton` are outside this gate.
- `--path` only narrows that already-discovered layered-product set; it does not turn an arbitrary directory into a package-compliance fixture root.
- The gate validates:
  - canonical package path shape
  - canonical composer package name mapping
  - required package scaffold artifacts
  - canonical `LICENSE`, `NOTICE`, and `SECURITY.md` package files
  - PSR-4 namespace mapping
  - package kind: `library|runtime`
  - runtime metadata and runtime scaffold
  - README minimum sections
  - runtime config shape
- Allowlist policy:
  - `tools/config/package_compliance_allowlist.php` is the only grandfathering mechanism.
  - allowlist entries are deterministic package ids: `<layer>/<slug>`.
  - allowlist content MUST be sorted by `strcmp`.
- Output policy:
  - compliance violation: line 1 is stable code `CORETSIA_PACKAGE_COMPLIANCE_VIOLATION`
  - unexpected failure: line 1 starts with stable code `CORETSIA_PACKAGE_COMPLIANCE_GATE_FAILED`
  - diagnostics use relative paths only and are sorted by `strcmp`
  - diagnostics MUST NOT include secrets, absolute paths, raw config payloads, headers, tokens, or environment values
- CI/rails policy:
  - `composer gates` MUST execute this gate as part of the baseline/tooling gates aggregate after `composer no-runtime-tooling-artifacts:gate`.
- Implementation detail: `@php tools/gates/package_compliance_gate.php`.

Usage (repo root):
- `composer package-compliance:gate`
- `composer package-compliance:gate -- --path=packages/core`
- `composer package-compliance:gate -- --path=packages/core/kernel`

---

### Package publish safety gate

Id: `tool.package_publish_safety_gate` \
Entrypoint: `composer package-publish-safety:gate` \
Category: repo policy / Packagist publish guard \
Outputs:
- none; read-only gate
- exits non-zero on Packagist publish-safety violations

Determinism:

| Mode / flags                           | Determinism   | Notes                                                        |
|----------------------------------------|---------------|--------------------------------------------------------------|
| `composer package-publish-safety:gate` | deterministic | Read-only; scans split-publish allowlisted package metadata. |

Notes:
- Purpose: validates Packagist-safe Composer metadata for packages allowlisted for split publishing.
- The split-publish allowlist is `.github/split-publish-packages.json`.
- Only allowlisted packages are checked as published/split-publish candidates by this gate.
- Every allowlisted package MUST resolve to an explicit publishable `composer.json` under `packages/**`.
- Supported source shapes include:
  - `packages/framework/composer.json`
  - `packages/applications/skeleton/composer.json`
  - `packages/<layer>/<slug>/composer.json`
- Package identity MUST be read from `composer.json.name`; it MUST NOT be derived from filesystem location.
- Package metadata policy:
  - `coretsia/framework` MUST use Composer type `metapackage`.
  - `coretsia/skeleton` MUST use Composer type `project`.
  - layered allowlisted packages MUST use Composer type `library`.
  - package `composer.json` MUST NOT contain a manual `version` field.
- Internal `coretsia/*` dependency policy in allowlisted package `require` and `require-dev` sections:
  - `dev-main` is forbidden
  - `*` is forbidden
  - `@dev` stability flags are forbidden
  - exact SemVer pins are forbidden
  - constraint MUST equal release-line `publicConstraint`
  - dependency package id MUST also be present in `.github/split-publish-packages.json`
- Future exact-pin exceptions require a dedicated policy change and are not part of this command.
- The gate reads `tools/release/release-line.json` to validate against release-line `publicConstraint`.
- The gate is read-only and MUST NOT create, modify, or delete files.
- Output policy:
  - publish-safety violation: line 1 is stable code `CORETSIA_PACKAGE_PUBLISH_SAFETY_VIOLATION`
  - unexpected failure: line 1 is stable code `CORETSIA_PACKAGE_PUBLISH_SAFETY_GATE_FAILED`
  - diagnostics use repo-relative paths and fixed reason tokens
  - diagnostics are sorted by `strcmp`
- Aggregate rail integration:
  - `composer gates` includes this gate after `composer package-compliance:gate`.
- Implementation detail: `@php tools/gates/package_publish_safety_gate.php`.

Usage (repo root):
- `composer package-publish-safety:gate`

---

### Atomic write gate

Id: `tool.atomic_write_gate` \
Entrypoint: `composer atomic-write:gate` \
Category: tooling / safety / CI rail \
Outputs:
- none on success
- deterministic error code and diagnostics on failure

Determinism:

| Mode / flags                                                                       | Determinism   | Notes                                                                         |
|------------------------------------------------------------------------------------|---------------|-------------------------------------------------------------------------------|
| `composer atomic-write:gate`                                                       | deterministic | Pure scanner; MUST be rerun-no-diff w.r.t. tracked files.                     |
| `composer atomic-write:gate -- --path=<tools-subtree> --allowlist=<allowlist.php>` | deterministic | Targeted tools-subtree scan; scan root MUST remain inside canonical `tools/`. |

Notes:
- Purpose: scans production tooling PHP files under `tools/**/*.php` for unsafe raw write sinks.
- Persistent tools-side writes MUST go through `Coretsia\Tools\Support\DeterministicFile`.
- Excludes test paths and the canonical `tools/support/DeterministicFile.php` helper; exact additional exceptions come only from the allowlist.
- Uses exact allowlist entries from:
  - `tools/config/atomic_write_allowlist.php`
- Forbidden raw write sinks outside allowlisted files:
  - `file_put_contents`
  - `fwrite`
  - `rename`
  - `copy`
  - writable `fopen` modes
  - writable `SplFileObject` modes
- Implementation detail: `@php tools/gates/atomic_write_gate.php`.
- Failure output policy:
  - violation: line 1 is stable code `CORETSIA_ATOMIC_WRITE_VIOLATION`
  - scanner/tooling failure: line 1 is stable code `CORETSIA_ATOMIC_WRITE_GATE_FAILED`
- Diagnostics contain only repo-relative file paths and line numbers in both default and targeted scan modes.

Usage (repo root):
- `composer atomic-write:gate`
- `composer atomic-write:gate -- --path=tools/build --allowlist=tools/config/atomic_write_allowlist.php`

---

### Documentation version drift gate

Id: `tool.doc_version_drift_gate` \
Entrypoint: `composer doc-version-drift:gate` \
Category: documentation governance / SSoT-ADR drift guard \
Outputs:
- none on success
- deterministic error code and diagnostics on failure

Determinism:

| Mode / flags                                               | Determinism   | Notes                                                                |
|------------------------------------------------------------|---------------|----------------------------------------------------------------------|
| `composer doc-version-drift:gate`                          | deterministic | Read-only; validates documentation version metadata against indexes. |
| `composer doc-version-drift:gate -- --path=<fixture-root>` | deterministic | Test/fixture override; scans the provided fixture repo root.         |

Notes:
- Purpose: prevents version drift between documentation index entries and the fenced YAML metadata block in the linked documents.
- The gate validates:
  - `docs/ssot/INDEX.md` `ssotVersion` entries against linked SSoT document `ssotVersion`;
  - `docs/adr/INDEX.md` `adrVersion` entries against linked ADR document `adrVersion`.
- The gate reads only version metadata from the first fenced `yaml` block immediately after the document H1.
- The gate does not compare `ssotVersion` and `adrVersion` to each other; they are separate version namespaces.
- Cross-reference entries such as `../roadmap/ROADMAP.md` are navigation links and are not version-governed by this gate.
- The gate is read-only and MUST NOT create, modify, or delete files.
- Failure output policy:
  - documentation version drift: line 1 is stable code `CORETSIA_DOC_VERSION_DRIFT`
  - unexpected failure: line 1 is stable code `CORETSIA_DOC_VERSION_GATE_FAILED`
- Diagnostics include only repo-relative paths and deterministic reason tokens.
- Diagnostics are deduplicated and sorted by byte-order `strcmp`.
- Diagnostics MUST NOT include absolute paths, raw document content, source snippets, stack traces, exception messages, secrets, tokens, credentials, or environment values.
- Aggregate rail integration:
  - `composer gates` includes this gate after `composer atomic-write:gate`.
- Implementation detail: `@php tools/gates/doc_version_drift_gate.php`.

Usage (repo root):
- `composer doc-version-drift:gate`
- `composer doc-version-drift:gate -- --path=tools/tests/Fixtures/DocVersion/Pass`

---

### License header compliance gate

Id: `tool.license_header_gate` \
Entrypoint: `composer license-header:gate` \
Category: repo policy / licensing guard \
Outputs:
- none on success
- deterministic error code and diagnostics on failure

Determinism:

| Mode / flags                                         | Determinism   | Notes                                                            |
|------------------------------------------------------|---------------|------------------------------------------------------------------|
| `composer license-header:gate`                       | deterministic | Read-only; scans the repository for canonical license headers.   |
| `composer license-header:gate -- --path=<scan-root>` | deterministic | Read-only scan override for tests/tools; path must stay in repo. |

Notes:
- Purpose: enforces the canonical Coretsia source-license header on repository-owned textual files whose supported format permits an in-file comment header.
- The gate is read-only and MUST NOT create, modify, or delete files.
- Default scan root is the repo root.
- Optional `--path=<scan-root>`:
  - accepts a repo-relative or absolute existing directory;
  - resolved path MUST remain inside the repo root;
  - is intended for targeted verification, fixtures, and tooling tests.
- The gate skips non-source/runtime/dependency directories including:
  - `.git`
  - `.idea`
  - `.vscode`
  - `.fleet`
  - `.osp`
  - `vendor`
  - `node_modules`
  - `var`
  - `tmp`
  - `coverage`
  - known tool cache directories
- `LICENSE` and `NOTICE` files are intrinsically exempt from in-file header validation.
- Supported canonical comment profiles include:
  - C-style block headers for PHP, JavaScript, TypeScript, TSX, CSS, and SCSS;
  - HTML comment headers for Markdown, HTML/HTM, XML, and SVG;
  - `#` comment headers for YAML, TOML, NEON, INI, shell-family scripts, PowerShell, `.env*`, `.editorconfig`, `.gitattributes`, `.gitignore`, and `.gitleaks.toml`;
  - `//` line-comment headers for Graphviz DOT files;
  - shebang scripts are classified according to their executable content.
- Formats without a supported in-file comment profile are not treated as license-header violations by this gate.
- Canonical header project policy:
  - files under `packages/applications/skeleton/**` MUST use project marker `Coretsia Skeleton`;
  - all other validated repository files MUST use project marker `Coretsia Framework (Monorepo)`;
  - the `Project:` line MUST match the applicable project marker.
  - `Authors:` MUST contain a non-empty value;
  - `Copyright (c)` MUST contain a non-empty copyright payload;
  - `SPDX-FileCopyrightText:` MUST contain the same copyright payload as `Copyright (c)`;
  - `SPDX-License-Identifier:` MUST be exactly `Apache-2.0`;
  - contributor/history and root `LICENSE`/`NOTICE` reference lines MUST match the canonical header shape.
- The gate MUST NOT require a specific author or copyright holder:
  - package/source ownership MAY belong to another contributor or organization;
  - the actual owner payload is validated structurally rather than hard-coded to `Vladyslav Mudrichenko`.
- Failure output policy:
  - policy violation: line 1 is stable code `CORETSIA_LICENSE_HEADER_VIOLATION`
  - unexpected gate/scanner failure: line 1 is stable code `CORETSIA_LICENSE_HEADER_GATE_FAILED`
- Stable violation reason tokens are:
  - `license-header-missing`
  - `license-header-invalid`
  - `license-header-copyright-mismatch`
- Diagnostics contain only a safe repo-relative path and one stable reason token.
- Non-ASCII path bytes are percent-encoded in diagnostics so output remains safe and deterministic.
- Diagnostics are deduplicated and sorted by byte-order `strcmp`.
- Diagnostics MUST NOT expose absolute paths, source contents, exception messages, stack traces, secrets, tokens, credentials, or environment values.
- Aggregate rail integration:
  - `composer gates` includes this gate after `composer doc-version-drift:gate`.
  - `composer ci` therefore enforces the license-header policy through the canonical gates rail.
- Implementation detail: `@php tools/gates/license_header_gate.php`.

Usage (repo root):
- `composer license-header:gate`
- `composer license-header:gate -- --path=packages`
- `composer license-header:gate -- --path=tools`

---

### Git hooks install (workspace)

Id: `tool.hooks_install` \
Entrypoint: `composer hooks:install` \
Category: workspace bootstrap \
Outputs:
- (local) Git config: enables hooks via `core.hooksPath=.githooks`

Determinism:

| Mode / flags | Determinism   | Notes                                              |
|--------------|---------------|----------------------------------------------------|
| default      | deterministic | Deterministic local git config change (no outputs) |

Usage (repo root):
- `composer hooks:install`

---

### Repo text normalization gate

Id: `tool.repo_text_normalization_gate` \
Entrypoint: `composer repo:text:gate` \
Category: repo policy / guard \
Outputs:
- none (exits non-zero on violations; emits deterministic diagnostics)

Determinism:

| Mode / flags                            | Determinism   | Notes                                                                  |
|-----------------------------------------|---------------|------------------------------------------------------------------------|
| `composer repo:text:gate`               | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines. |
| `composer repo:text:gate -- --path=...` | deterministic | Scans an override root; MUST resolve inside repo root.                 |

Notes:
- Default scan root: repo root.
- Optional override: `--path=<path>` where `<path>` is repo-relative or absolute; it MUST resolve inside repo root.
- Output policy: must be safe (no absolute paths / secret leaks); diagnostics are expected to be stable and minimal.
- Failure output policy:
  - policy violation: line 1 is stable code `CORETSIA_REPO_TEXT_POLICY_VIOLATION`
  - unexpected scanner failure: line 1 is stable code `CORETSIA_REPO_TEXT_POLICY_SCAN_FAILED`
- Implementation detail: `@php tools/gates/repo_text_normalization_gate.php`.

Usage (repo root):
- `composer repo:text:gate`
- `composer repo:text:gate -- --path=packages`

---

### Package-local PHPUnit config gate

Id: `tool.package_phpunit_gate` \
Entrypoint: `composer package:phpunit:gate` \
Category: repo policy / guard \
Outputs:
- none (exits non-zero on violations)

Determinism:

| Mode / flags | Determinism   | Notes                                                                               |
|--------------|---------------|-------------------------------------------------------------------------------------|
| default      | deterministic | Deterministic scan of every product discovered by `WorkspacePackageCatalog::all()`. |

Notes:
- Purpose: forbids package-local PHPUnit config files for every discovered product, including special distributions.
- Forbidden files:
  - `<product-path>/phpunit.xml`
  - `<product-path>/phpunit.dist.xml`
- Canonical policy:
  - package-local PHPUnit config files are forbidden
  - canonical repository PHPUnit source of truth is `tools/testing/phpunit.xml`
  - runtime artifact is `var/phpunit/phpunit.discovered.xml`
- Output policy:
  - violation: line 1 is stable code `CORETSIA_PACKAGE_PHPUNIT_CONFIG_FORBIDDEN`
  - unexpected scan failure: line 1 is stable code `CORETSIA_PACKAGE_PHPUNIT_CONFIG_GATE_FAILED`
  - diagnostics are `<repo-relative-path>: forbidden-package-phpunit-config`, sorted deterministically
- Implementation detail: `@php tools/gates/package_phpunit_config_gate.php`
- `composer test` MUST execute this gate before the package-discovery PHPUnit runner.

Usage (repo root):
- `composer package:phpunit:gate`

---

### Internal toolkit anti-duplication gate

Id: `tool.toolkit_gate` \
Entrypoint: `composer toolkit:gate` \
Category: repo policy / guard \
Outputs:
- none (exits non-zero on violations; emits deterministic code + minimal diagnostics)

Determinism:

| Mode / flags                           | Determinism   | Notes                                                                 |
|----------------------------------------|---------------|-----------------------------------------------------------------------|
| `composer toolkit:gate`                | deterministic | Deterministic scan of the default tools subtree.                      |
| `composer toolkit:gate -- --path=...`  | deterministic | Scan override (scan-only). Intended for contract tests / diagnostics. |

Notes:
- Purpose: enforces symbol-ownership for determinism helpers and forbids direct `json_encode(...)` under the scanned tools subtree.
  - Forbidden duplicated function/method names: `toStudly`, `toSnake`, `normalizeRelative`, `encodeStable`
  - Forbidden function calls: `json_encode(...)` (including qualified `\json_encode(...)`)
- Default scan root is the canonical `tools/` root from `RepositoryContext`.
- Optional `--path=<path>` MUST resolve inside the canonical `tools/` root.
- Excluded directory segments are `tests` and `fixtures`.
- Failure output policy:
  - if any forbidden `json_encode(...)` call is detected, line 1 is `CORETSIA_TOOLKIT_JSON_ENCODE_FORBIDDEN`
  - otherwise duplicated helper symbols use `CORETSIA_TOOLKIT_DUPLICATION_DETECTED`
  - unexpected scanner failure uses `CORETSIA_TOOLKIT_DUP_GATE_SCAN_FAILED`
  - diagnostics are tools-root-relative `<path>: <symbol>` lines
- Implementation detail: `@php tools/gates/internal_toolkit_no_dup_gate.php`

Usage (repo root):
- `composer toolkit:gate`
- `composer toolkit:gate -- --path=tools/support`

---

### Tools InvalidArgumentException policy gate

Id: `tool.tools_invalid_argument_exception_gate` \
Entrypoint: `composer tools:ia` \
Category: repo policy / guard \
Outputs:
- none (exits non-zero on violations; emits deterministic diagnostics)

Determinism:

| Mode / flags                    | Determinism   | Notes                                                                  |
|---------------------------------|---------------|------------------------------------------------------------------------|
| `composer tools:ia`             | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines. |
| `composer tools:ia -- --path=…` | deterministic | Scan override (scan-only). Intended for contract tests / diagnostics.  |

Notes:
- Purpose: forbids direct `throw new InvalidArgumentException(...)` under `tools/**`,
  except explicit allowlist (contracted exceptions for developer errors):
  - `build/sync_composer_repositories.php`
  - `support/DeterministicException.php`
- Scanned scope (conceptual): `tools/**` excluding `**/tests/**` and `**/fixtures/**`.
- Optional `--path=<path>` narrows the scan but MUST remain inside the canonical `tools/` root.
- Failure output policy:
  - violation: line 1 is `CORETSIA_TOOLS_INVALID_ARGUMENT_EXCEPTION_FORBIDDEN`
  - unexpected scanner failure: line 1 is `CORETSIA_TOOLS_INVALID_ARGUMENT_EXCEPTION_GATE_SCAN_FAILED`
  - diagnostics are `<tools-root-relative-path>: throw-new-InvalidArgumentException`, sorted deterministically
- Implementation detail: `@php tools/gates/tools_invalid_argument_exception_gate.php`.

Usage (repo root):
- `composer tools:ia`
- `composer tools:ia -- --path=tools`

---

### Coretsia CLI help

Id: `cli.help` \
Entrypoint: `php coretsia help` \
Category: CLI / built-in \
Outputs:
- none (prints deterministic help text to console)

Determinism:

| Mode / flags                  | Determinism   | Notes                                                            |
|-------------------------------|---------------|------------------------------------------------------------------|
| `php coretsia`                | deterministic | No-command dispatch; prints the same general help                |
| `php coretsia help`           | deterministic | General usage + available commands list                          |
| `php coretsia help <command>` | deterministic | Built-in help for `help`/`list`; generic help for known commands |

Notes:
- Purpose: provide kernel-free deterministic help for the Coretsia CLI runtime.
- No-command policy:
  - `php coretsia` dispatches directly to the built-in general help path and exits successfully.
- Known-subject policy:
  - `help help` and `help list` print built-in detailed help.
  - `help <known-command>` prints deterministic generic help when detailed built-in help is not available.
  - `help <unknown-command>` fails deterministically:
    - emits `OutputInterface::error(CORETSIA_CLI_COMMAND_INVALID, unknown-command)`
    - exits non-zero.
- Entrypoint topology:
  - repo-root `coretsia` is the canonical user-facing CLI entrypoint and is a thin wrapper;
  - the wrapper delegates to the canonical repository launcher `tools/bin/coretsia`;
  - direct `php tools/bin/coretsia help …` is an implementation-level entrypoint and MUST remain behavior-equivalent to the root wrapper.

Usage (repo root):
- `php coretsia`
- `php coretsia help`
- `php coretsia help help`
- `php coretsia help list`
- `php coretsia help <command>`

---

### Coretsia CLI list

Id: `cli.list` \
Entrypoint: `php coretsia list` \
Category: CLI / built-in \
Outputs:
- none (prints deterministic list of available commands)

Determinism:

| Mode / flags        | Determinism   | Notes                                         |
|---------------------|---------------|-----------------------------------------------|
| `php coretsia list` | deterministic | Prints built-ins + injected registry commands |

Notes:
- Prints a deterministic catalog:
  - built-ins: `help`, `list`
  - plus configured/injected registry commands (as resolved by `Application`).
- Extra tokens are ignored; the command remains tolerant because argument parsing is an `Application` concern.
- Output MUST be produced via `OutputInterface` only (no direct stdout/stderr in command code).
- Entrypoint topology:
  - repo-root `php coretsia list` is the canonical user-facing entrypoint;
  - root `coretsia` is a thin shim delegating to `tools/bin/coretsia`;
  - `php tools/bin/coretsia list` is the canonical repository launcher implementation and MUST remain behavior-equivalent.

Usage (repo root):
- `php coretsia list`

---

### Site icons builder

Id: `tool.build_icons` \
Entrypoint: `composer build:icons` \
Category: documentation / branding / asset generator \
Outputs:
- `docs/assets/branding/favicon/favicon-16x16.png`
- `docs/assets/branding/favicon/favicon-32x32.png`
- `docs/assets/branding/favicon/favicon-48x48.png`
- `docs/assets/branding/favicon/favicon-64x64.png`
- `docs/assets/branding/favicon/apple-touch-icon.png`
- `docs/assets/branding/favicon/android-chrome-192x192.png`
- `docs/assets/branding/favicon/android-chrome-512x512.png`
- `docs/assets/branding/favicon/favicon.ico`

Determinism:

| Mode / flags                      | Determinism   | Notes                                                      |
|-----------------------------------|---------------|------------------------------------------------------------|
| `composer build:icons`            | deterministic | Materializes canonical icon artifacts in branding output   |
| `composer build:icons -- --apply` | deterministic | Explicit apply mode; behavior-equivalent to default mode   |
| `composer build:icons -- --check` | deterministic | Pure check; exits non-zero if generated artifacts drift    |

Notes:
- Purpose: renders canonical favicon / app-icon artifacts for documentation / branding outputs.
- Canonical source assets:
  - required: `docs/assets/branding/favicon/favicon.svg`
  - optional: `docs/assets/branding/favicon/coretsia-favicon-micro.svg`
  - optional: `docs/assets/branding/favicon/apple-touch-icon.svg`
- Source semantics:
  - `coretsia-favicon-micro.svg`, if present, is the source for the 16×16, 32×32, 48×48, and 64×64 favicon PNGs
  - if `coretsia-favicon-micro.svg` is absent, those favicon PNGs fall back to `favicon.svg`
  - `favicon.svg` is the canonical source for the Android outputs
  - `apple-touch-icon.svg`, if present, is the canonical source for `apple-touch-icon.png`
  - if `apple-touch-icon.svg` is absent, `apple-touch-icon.png` falls back to `favicon.svg`
- ICO semantics:
  - `favicon.ico` is built from the generated 16×16, 32×32, 48×48, and 64×64 PNG layers
  - deterministic output is required for the same input assets / toolchain state
- Implementation detail: `@php tools/build/build_icons.php`.
- Success output policy:
  - apply mode emits `OK`, followed by changed repo-relative artifact paths when changes were written
  - clean `--check` mode emits `OK`
- Failure output policy:
  - drift in `--check` mode: line 1 is stable code `CORETSIA_BUILD_ICONS_OUT_OF_DATE`
  - unexpected failure: line 1 starts with stable code `CORETSIA_BUILD_ICONS_FAILED`
- Root workspace suggests `ext-imagick` for optional icon/image asset generation; alternative renderer support, if present in the tool implementation, does not change this optional dependency policy.

Usage (repo root):
- `composer build:icons`
- `composer build:icons -- --check`

---

### Security aggregate

Id: `project.security` \
Entrypoint: `composer security` \
Category: security / CI rail \
Outputs:
- none on success
- deterministic error code and diagnostics on failure from owned security gates

Determinism:

| Mode / flags        | Determinism   | Notes                                                                      |
|---------------------|---------------|----------------------------------------------------------------------------|
| `composer security` | deterministic | Delegates to dedicated security rails; external security data/tools apply. |

Notes:
- `composer security` is the canonical aggregate for security-specific rails.
- Execution order is cemented:
  1) `composer composer-audit:gate`
  2) `composer secret-leakage:gate`
- This aggregate is intentionally separate from `composer gates`.
- CI should run this aggregate in a dedicated security lane/job, not inside architecture/deptrac jobs.
- Implementation detail: aggregate `security` script in root `composer.json`.

Usage (repo root):
- `composer security`

---

### Composer audit gate

Id: `tool.composer_audit_gate` \
Entrypoint: `composer composer-audit:gate` \
Category: security / guard \
Outputs:
- none on success
- deterministic error code and diagnostics on vulnerability finding
- deterministic error code on network failure
- deterministic error code on scan/tooling failure

Determinism:

| Mode / flags                   | Determinism   | Notes                                                                                  |
|--------------------------------|---------------|----------------------------------------------------------------------------------------|
| `composer composer-audit:gate` | deterministic | Deterministic diagnostics for captured Composer audit JSON; advisory data is external. |

Notes:
- Purpose: runs Composer audit for the root workspace dependency graph.
- Root audit prerequisites:
  - repo-root `composer.json` exists
  - repo-root `composer.lock` exists
  - repo-root `vendor/` exists
- Once those prerequisites exist, the gate invokes Composer audit for the root workspace; it does not pre-classify `composer.lock` by package count.
- Package manifests under `packages/**` are not audited directly by this gate.
- The gate runs Composer as:
  - `composer audit --format=json --abandoned=ignore`
- `--abandoned=ignore` is intentional:
  - this gate classifies Composer security advisories only
  - abandoned-package policy is not part of this gate
- The gate captures stdout/stderr and MUST NOT stream raw Composer output.
- The gate parses JSON output from stdout/stderr when available, even if Composer exits non-zero.
- Valid audit JSON containing advisories is classified as `CORETSIA_COMPOSER_AUDIT_FAILED`, not scan failure.
- Valid audit JSON without advisories is treated as clean only when Composer exits successfully.
- Composer non-zero exit without usable advisory findings is classified as scan/tooling failure.
- Diagnostics include only:
  - audit root label: `root`
  - Composer package name
  - advisory id
- Diagnostics MUST NOT include URLs, advisory titles, raw Composer payloads, absolute paths, stack traces, secrets, tokens, or credentials.
- Failure output policy:
  - vulnerability finding: line 1 is stable code `CORETSIA_COMPOSER_AUDIT_FAILED`
  - vulnerability diagnostics, when present, are sorted and sanitized
  - recognized network failure: line 1 is stable code `CORETSIA_TOOLS_NETWORK_FAILED`
  - other scan/tooling failure: line 1 is stable code `CORETSIA_COMPOSER_AUDIT_SCAN_FAILED`
- This command is intentionally not part of `composer gates`.
- CI should run this command in a dedicated security lane/job.
- Implementation detail: `@php tools/gates/composer_audit_gate.php`.

Usage (repo root):
- `composer composer-audit:gate`

---

### Secret leakage gate

Id: `tool.secret_leakage_gate` \
Entrypoint: `composer secret-leakage:gate` \
Category: security / guard \
Outputs:
- none on success
- deterministic error code and sanitized diagnostics on secret-like finding
- deterministic error code on scan/tooling failure

Determinism:

| Mode / flags                                 | Determinism   | Notes                                                                       |
|----------------------------------------------|---------------|-----------------------------------------------------------------------------|
| `composer secret-leakage:gate`               | deterministic | Uses a temporary Gitleaks JSON report and emits only sanitized diagnostics. |
| `composer secret-leakage:gate -- --path=...` | deterministic | Test/fixture scan-root override; config defaults to that scan root.         |

Notes:
- Purpose: prevents accidental commits of secret-like material by scanning the repository working tree with Gitleaks.
- Default scan root: repo root.
- Default config path: repo-root `.gitleaks.toml`.
- With `--path=<scan-root>`, the default config becomes `<scan-root>/.gitleaks.toml`.
- An explicit `--config=<path>` is resolved relative to the selected scan root and MUST remain inside that scan root.
- The gate runs Gitleaks in directory scanning mode:
  - `gitleaks dir <repo-root> --config=<repo-root>/.gitleaks.toml --report-format=json --report-path=<temp-report> --redact --no-banner --no-color --max-archive-depth=0 --max-decode-depth=0`
- The gate MUST NOT use deprecated/hidden `gitleaks detect` / `gitleaks protect` command names.
- The gate captures stdout/stderr and MUST NOT stream raw Gitleaks output.
- The gate writes JSON to an explicit temporary `--report-path` file and parses only that JSON report file.
- The gate deletes the temporary report file before exit.
- The gate MUST NOT parse human-readable Gitleaks output.
- The gate MUST NOT print raw matches, source snippets, secrets, tokens, credentials, absolute paths, stack traces, or exception messages.
- Finding diagnostics include only:
  - normalized scan-root-relative path;
  - optional start line;
  - sanitized Gitleaks rule id.
- Failure output policy:
  - secret finding: line 1 is stable code `CORETSIA_SECRET_LEAK_DETECTED`
  - finding diagnostics, when present, are deduplicated and sorted by `strcmp`
  - scan/tooling failure: line 1 is stable code `CORETSIA_SECRET_GATE_SCAN_FAILED`
- This command is intentionally not part of `composer gates`.
- CI should run this command through the dedicated `composer security` aggregate.
- Implementation detail: `@php tools/gates/secret_leakage_gate.php`.

Usage (repo root):
- `composer secret-leakage:gate`
- `composer secret-leakage:gate -- --path=.`

---

### Tooling gates rail

Id: `tool.gates` \
Entrypoint: `composer gates` \
Category: CI / repo policy / guard rail \
Outputs:
- none (exits non-zero if any configured gate fails)

Determinism:

| Mode / flags | Determinism   | Notes                                                                |
|--------------|---------------|----------------------------------------------------------------------|
| default      | deterministic | Executes the canonical aggregate tooling gates rail in stable order. |

Notes:
- Purpose: executes the canonical aggregate gates rail for baseline/tooling/public-API/package metadata enforcement.
- This command is the preferred CI entrypoint for gates owned by tooling.
- Individual `*:gate` scripts remain separately invokable and are the canonical unit entrypoints.
- `composer gates` is the canonical aggregate rail entrypoint.
- Execution order is cemented:
  1) `composer no-skeleton-http-default:gate`
  2) `composer no-skeleton-bundles-default:gate`
  3) `composer no-skeleton-mode-presets-default:gate`
  4) `composer no-skeleton-modules-default:gate`
  5) `composer contracts-only-ports:gate`
  6) `composer reserved-tags:gate`
  7) `composer observability-naming:gate`
  8) `composer observability-span-naming:gate`
  9) `composer observability-metric-catalog:gate`
  10) `composer artifact-header-schema:gate`
  11) `composer cross-cutting-contract:gate`
  12) `composer kernel-public-api:gate`
  13) `composer toolkit:gate`
  14) `composer tools:ia`
  15) `composer repo:text:gate`
  16) `composer no-runtime-tooling-artifacts:gate`
  17) `composer package-compliance:gate`
  18) `composer package-publish-safety:gate`
  19) `composer atomic-write:gate`
  20) `composer doc-version-drift:gate`
  21) `composer license-header:gate`
- Implementation detail: aggregate `gates` script in root `composer.json`.
- Policy:
  - order of invoked gates inside the aggregate rail MUST be deterministic
  - aggregate rail MUST invoke named composer `*:gate` scripts, not raw `php tools/gates/*.php` paths
  - runtime tooling artifact purity MUST run before package compliance in this aggregate rail
  - package compliance MUST run after baseline public-API/runtime-purity/policy gates in this aggregate rail
  - package publish safety MUST run after package compliance in this aggregate rail
  - package publish safety MUST remain read-only and MUST NOT rewrite package composer metadata
  - this rail SHOULD run before `composer quality` and `composer test` in CI

Usage (repo root):
- `composer gates`

---

### DTO aggregate gate rail

Id: `tool.dto_gate` \
Entrypoint: `composer dto:gate` \
Category: CI / repo policy / DTO guard rail \
Outputs:
- none on success
- exits non-zero if a materialized specialized DTO gate fails
- forwards the first failing specialized DTO gate output unchanged
- emits deterministic aggregate diagnostics only if the aggregate runner itself fails before a sub-gate can produce canonical diagnostics

Determinism:

| Mode / flags                        | Determinism   | Notes                                                                                              |
|-------------------------------------|---------------|----------------------------------------------------------------------------------------------------|
| `composer dto:gate`                 | deterministic | Executes materialized DTO specialized gates in fixed deterministic order.                          |
| `composer dto:gate -- --path=<dir>` | deterministic | Resolves one repo-contained scan selector and forwards it unchanged to every specialized DTO gate. |

Notes:
- Purpose: executes the canonical aggregate DTO policy rail.
- DTO policy is explicit opt-in only:
  - only classes marked with `#[Coretsia\Dto\Attribute\Dto]` are in DTO gate scope
  - unmarked classes are outside DTO gate scope
- This command is the aggregate DTO rail entrypoint.
- Specialized DTO gates are materialized incrementally and are also available as standalone canonical unit entrypoints.
- All listed specialized DTO gates are required. A missing or unreadable listed sub-gate is treated as an aggregate orchestration failure with `CORETSIA_DTO_GATE_FAILED`.
- Execution order is cemented:
  1) DTO shape gate: `composer dto-shape:gate`
  2) DTO marker consistency gate: `composer dto-marker-consistency:gate`
  3) DTO no-logic gate: `composer dto-no-logic:gate`
- Materialized specialized gate entrypoints:
  - `composer dto-shape:gate`
  - `composer dto-marker-consistency:gate`
  - `composer dto-no-logic:gate`
- Future specialized DTO gates, once materialized, MUST also have their own repo-root `composer <name>:gate` script.
- Failure behavior:
  - the aggregate runner stops on the first failing materialized sub-gate
  - the first failing sub-gate output is passed through unchanged
  - the aggregate runner MUST NOT merge, rewrite, or reformat specialized gate diagnostics
  - if all materialized sub-gates pass, the command exits 0 and prints nothing
- Aggregate/orchestration failure policy:
  - if the aggregate runner itself cannot start a materialized sub-gate, line 1 is stable code `CORETSIA_DTO_GATE_FAILED`
  - diagnostics use normalized repo-relative paths and fixed reason tokens
- Fixed aggregate reason tokens:
  - `dto_sub_gate_missing`
  - `dto_sub_gate_unreadable`
  - `dto_sub_gate_process_start_failed`
- Implementation detail: `@php tools/gates/dto_gate.php`
- Aggregate implementation detail:
  - `tools/gates/dto_shape_gate.php`
  - `tools/gates/dto_marker_consistency_gate.php`
  - `tools/gates/dto_no_logic_gate.php`
- CI/rails policy:
  - this command SHOULD run in the dedicated `gates` CI job after `composer gates`
  - this command SHOULD run before architecture checks, quality checks, and tests
  - this command SHOULD be included in root `composer ci`

Usage (repo root):
- `composer dto:gate`
- `composer dto:gate -- --path=packages/core`

---

### DTO marker consistency gate

Id: `tool.dto_marker_consistency_gate` \
Entrypoint: `composer dto-marker-consistency:gate` \
Category: repo policy / DTO guard \
Outputs:
- none on success
- exits non-zero on DTO marker policy violations
- emits deterministic diagnostics only through the canonical tooling output policy

Determinism:

| Mode / flags                                      | Determinism   | Notes                                                                 |
|---------------------------------------------------|---------------|-----------------------------------------------------------------------|
| `composer dto-marker-consistency:gate`            | deterministic | Scans the Coretsia package source tree using the default scan root.   |
| `composer dto-marker-consistency:gate -- --path=` | deterministic | Overrides scan root only; bootstrap/runtime root remains tools-owned. |

Notes:
- Purpose: enforces the canonical DTO marker strategy for Phase 1.
- Canonical marker:
  - `Coretsia\Dto\Attribute\Dto`
  - package: `coretsia/core-dto-attribute`
  - declaration path: `packages/core/dto-attribute/src/Attribute/Dto.php`
- Policy:
  - only `Coretsia\Dto\Attribute\Dto` is the canonical DTO marker
  - alias imports are allowed only when they resolve to `Coretsia\Dto\Attribute\Dto`
  - interface markers such as `DtoInterface` are forbidden
  - local/custom DTO marker attributes are forbidden
  - simultaneous marker strategies are forbidden
  - classes without the canonical marker are outside DTO gate scope
  - the canonical marker declaration itself MUST NOT be reported as a custom marker
- Default scan scope:
  - PHP source files under the `src/` roots of all products discovered by `WorkspacePackageCatalog::all()`
- Excluded paths:
  - `**/tests/**`
  - `**/fixtures/**`
  - `**/vendor/**`
- `--path=<dir>` policy:
  - path MUST resolve inside the repository root
  - the selector is restricted to canonical product `src/` roots through `GateRuntime::selectScanRoots()`
  - an ancestor selector includes the canonical product `src/` roots below it
  - a selector inside one canonical product `src/` root narrows the scan to that subtree
  - unrelated repository directories select no DTO source roots
  - scan selection does not affect bootstrap discovery
  - bootstrap is always loaded from the runtime tools root:
    - `tools/support/bootstrap.php`
  - intended for integration tests and local focused checks
- Output policy:
  - marker violations: line 1 is stable code `CORETSIA_DTO_MARKER_VIOLATION`
  - scan/bootstrap/internal failure: line 1 is stable code `CORETSIA_DTO_GATE_SCAN_FAILED`
  - line 2+ diagnostics use repo-relative normalized paths and fixed reason tokens
  - diagnostics are sorted by `strcmp`
  - if no violations exist, command exits 0 and prints nothing
- Fixed reason tokens:
  - `non-canonical-dto-marker`
  - `legacy-dto-interface-marker`
  - `custom-dto-marker-class`
  - `multiple-dto-marker-strategies`
- Implementation detail: `@php tools/gates/dto_marker_consistency_gate.php`
- Aggregate rail integration:
  - `composer dto:gate` invokes this gate after `dto-shape:gate` and before `dto-no-logic:gate`
  - failing output MUST pass through the aggregate runner unchanged

Usage (repo root):
- `composer dto-marker-consistency:gate`
- `composer dto-marker-consistency:gate -- --path=packages`

---

### DTO no-logic gate

Id: `tool.dto_no_logic_gate` \
Entrypoint: `composer dto-no-logic:gate` \
Category: repo policy / DTO guard \
Outputs:
- none on success
- exits non-zero on DTO no-logic policy violations
- emits deterministic diagnostics only through the canonical tooling output policy

Determinism:

| Mode / flags                            | Determinism   | Notes                                                                 |
|-----------------------------------------|---------------|-----------------------------------------------------------------------|
| `composer dto-no-logic:gate`            | deterministic | Scans the Coretsia package source tree using the default scan root.   |
| `composer dto-no-logic:gate -- --path=` | deterministic | Overrides scan root only; bootstrap/runtime root remains tools-owned. |

Notes:
- Purpose: enforces that explicitly marked DTOs remain transport-only and do not contain executable behavior beyond trivial construction.
- DTO policy is explicit opt-in only:
  - only classes marked with `#[Coretsia\Dto\Attribute\Dto]` are analyzed
  - unmarked classes are outside DTO gate scope
- Allowed DTO methods:
  - no methods except optional `__construct`
- Allowed constructor forms:
  - property promotion only
  - empty constructor body
  - trivial assignments of the form `$this->property = $parameter;`
- Constructor policy:
  - right-hand side MUST be a direct constructor parameter variable
  - left-hand side MUST be a direct instance property
  - computed expressions, normalization, validation, calls, object allocation, branching, loops, try/catch, and throw are forbidden
- Default scan scope:
  - PHP source files under the `src/` roots of all products discovered by `WorkspacePackageCatalog::all()`
- Excluded paths:
  - `**/tests/**`
  - `**/fixtures/**`
  - `**/vendor/**`
- `--path=<dir>` policy:
  - path MUST resolve inside the repository root
  - the selector is restricted to canonical product `src/` roots through `GateRuntime::selectScanRoots()`
  - an ancestor selector includes the canonical product `src/` roots below it
  - a selector inside one canonical product `src/` root narrows the scan to that subtree
  - unrelated repository directories select no DTO source roots
  - scan selection does not affect bootstrap discovery
  - bootstrap is always loaded from the runtime tools root:
    - `tools/support/bootstrap.php`
  - intended for integration tests and local focused checks
- Output policy:
  - no-logic violations: line 1 is stable code `CORETSIA_DTO_NO_LOGIC_VIOLATION`
  - scan/bootstrap/internal failure: line 1 is stable code `CORETSIA_DTO_GATE_SCAN_FAILED`
  - line 2+ diagnostics use repo-relative normalized paths and fixed reason tokens
  - diagnostics are sorted by `strcmp`
  - if no violations exist, command exits 0 and prints nothing
  - diagnostics MUST NOT leak class contents, property values, constructor body text, or method bodies
- Fixed reason tokens:
  - `disallowed-method`
  - `disallowed-property-hook`
  - `constructor-calls-function`
  - `constructor-calls-method`
  - `constructor-static-call`
  - `constructor-control-flow`
  - `constructor-loop`
  - `constructor-try-catch`
  - `constructor-throw`
  - `constructor-new-object`
  - `constructor-nontrivial-body`
- Implementation detail: `@php tools/gates/dto_no_logic_gate.php`
- Aggregate rail integration:
  - `composer dto:gate` invokes this gate after `dto-shape:gate` and `dto-marker-consistency:gate` as the final specialized DTO gate
  - failing output MUST pass through the aggregate runner unchanged

Usage (repo root):
- `composer dto-no-logic:gate`
- `composer dto-no-logic:gate -- --path=packages`

---

### DTO shape gate

Id: `tool.dto_shape_gate` \
Entrypoint: `composer dto-shape:gate` \
Category: repo policy / DTO guard \
Outputs:
- none on success
- exits non-zero on DTO shape policy violations
- emits deterministic diagnostics only through the canonical tooling output policy

Determinism:

| Mode / flags                         | Determinism   | Notes                                                                 |
|--------------------------------------|---------------|-----------------------------------------------------------------------|
| `composer dto-shape:gate`            | deterministic | Scans the Coretsia package source tree using the default scan root.   |
| `composer dto-shape:gate -- --path=` | deterministic | Overrides scan root only; bootstrap/runtime root remains tools-owned. |

Notes:
- Purpose: enforces the canonical structural shape for explicitly marked DTOs.
- DTO policy is explicit opt-in only:
  - only classes marked with `#[Coretsia\Dto\Attribute\Dto]` are analyzed
  - unmarked classes are outside DTO gate scope
- Required DTO shape:
  - DTO MUST be a class
  - DTO MUST be `final`
  - DTO MUST NOT be `abstract`
  - DTO MUST NOT extend another class
  - DTO MUST NOT implement interfaces
  - DTO MUST NOT use traits
  - DTO MUST NOT declare static properties
  - every declared property MUST be public
  - every declared property MUST be typed
  - promoted properties MUST be public and typed
- Default scan scope:
  - PHP source files under the `src/` roots of all products discovered by `WorkspacePackageCatalog::all()`
- Excluded paths:
  - `**/tests/**`
  - `**/fixtures/**`
  - `**/vendor/**`
- `--path=<dir>` policy:
  - path MUST resolve inside the repository root
  - the selector is restricted to canonical product `src/` roots through `GateRuntime::selectScanRoots()`
  - an ancestor selector includes the canonical product `src/` roots below it
  - a selector inside one canonical product `src/` root narrows the scan to that subtree
  - unrelated repository directories select no DTO source roots
  - scan selection does not affect bootstrap discovery
  - bootstrap is always loaded from the runtime tools root:
    - `tools/support/bootstrap.php`
  - intended for integration tests and local focused checks
- Output policy:
  - shape violations: line 1 is stable code `CORETSIA_DTO_SHAPE_VIOLATION`
  - scan/bootstrap/internal failure: line 1 is stable code `CORETSIA_DTO_GATE_SCAN_FAILED`
  - line 2+ diagnostics use repo-relative normalized paths and fixed reason tokens
  - diagnostics are sorted by `strcmp`
  - if no violations exist, command exits 0 and prints nothing
  - diagnostics MUST NOT leak class contents, property values, constructor body text, or method bodies
- Fixed reason tokens:
  - `not-final`
  - `abstract-class`
  - `extends-class`
  - `implements-interface`
  - `uses-trait`
  - `static-property`
  - `untyped-property`
  - `non-public-property`
- Implementation detail: `@php tools/gates/dto_shape_gate.php`
- Aggregate rail integration:
  - `composer dto:gate` invokes this gate as the first specialized DTO gate
  - failing output MUST pass through the aggregate runner unchanged

Usage (repo root):
- `composer dto-shape:gate`
- `composer dto-shape:gate -- --path=packages`

---

### No skeleton HTTP default gate

Id: `tool.no_skeleton_http_default_gate` \
Entrypoint: `composer no-skeleton-http-default:gate` \
Category: repo policy / guard \
Outputs:
- none (exits non-zero on violations; emits deterministic diagnostics)

Determinism:

| Mode / flags | Determinism   | Notes                                                                     |
|--------------|---------------|---------------------------------------------------------------------------|
| default      | deterministic | Deterministic check for forbidden default skeleton HTTP config file only. |

Notes:
- Purpose: forbids shipping the default skeleton HTTP config file:
  - `packages/applications/skeleton/config/http.php`
- Canonical policy:
  - HTTP defaults are framework/package-owned
  - skeleton `config/http.php` is app-override only and MUST NOT be present by default
- Output policy:
  - violation: line 1 is stable code `CORETSIA_NO_SKELETON_HTTP_DEFAULT_FORBIDDEN`
  - diagnostics are `<repo-relative-path>: forbidden-default-http-config`, sorted deterministically
  - unexpected gate failure: line 1 is stable code `CORETSIA_NO_SKELETON_HTTP_DEFAULT_GATE_FAILED`
- Implementation detail: `@php tools/gates/no_skeleton_http_default_gate.php`
- CI/rails policy:
  - this gate SHOULD run in the dedicated `gates` rail before tests
  - it MUST remain deterministic and rerun-no-diff

Usage (repo root):
- `composer no-skeleton-http-default:gate`

---

### No skeleton bundles default gate

Id: `tool.no_skeleton_bundles_default_gate` \
Entrypoint: `composer no-skeleton-bundles-default:gate` \
Category: repo policy / guard \
Outputs:
- none (exits non-zero on violations; emits deterministic diagnostics)

Determinism:

| Mode / flags | Determinism   | Notes                                                                        |
|--------------|---------------|------------------------------------------------------------------------------|
| default      | deterministic | Deterministic check for forbidden default skeleton bundle config files only. |

Notes:
- Purpose: forbids shipping default skeleton bundle config files:
  - `packages/applications/skeleton/config/bundles/*.php`
- Canonical policy:
  - bundle defaults are framework-owned
  - skeleton `config/bundles/*.php` files are app-override only and MUST NOT be present by default
- Output policy:
  - violation: line 1 is stable code `CORETSIA_NO_SKELETON_BUNDLES_DEFAULT_FORBIDDEN`
  - diagnostics are `<repo-relative-path>: forbidden-default-bundle-config`, sorted deterministically
  - unexpected gate failure: line 1 is stable code `CORETSIA_NO_SKELETON_BUNDLES_DEFAULT_GATE_FAILED`
- Implementation detail: `@php tools/gates/no_skeleton_bundles_default_gate.php`
- CI/rails policy:
  - this gate SHOULD run in the dedicated `gates` rail before tests
  - it MUST remain deterministic and rerun-no-diff

Usage (repo root):
- `composer no-skeleton-bundles-default:gate`

---

### No skeleton mode presets default gate

Id: `tool.no_skeleton_mode_presets_default_gate` \
Entrypoint: `composer no-skeleton-mode-presets-default:gate` \
Category: repo policy / guard \
Outputs:
- none (exits non-zero on violations; emits deterministic diagnostics)

Determinism:

| Mode / flags | Determinism   | Notes                                                                             |
|--------------|---------------|-----------------------------------------------------------------------------------|
| default      | deterministic | Deterministic check for forbidden default skeleton mode preset config files only. |

Notes:
- Purpose: forbids shipping default skeleton mode preset config files:
  - `packages/applications/skeleton/config/modes/*.php`
- Canonical policy:
  - mode presets are framework-owned
  - skeleton `config/modes/*.php` files are app-override only and MUST NOT be present by default
- Output policy:
  - violation: line 1 is stable code `CORETSIA_NO_SKELETON_MODE_PRESETS_DEFAULT_FORBIDDEN`
  - diagnostics are `<repo-relative-path>: forbidden-default-mode-preset-config`, sorted deterministically
  - unexpected gate failure: line 1 is stable code `CORETSIA_NO_SKELETON_MODE_PRESETS_DEFAULT_GATE_FAILED`
- Implementation detail: `@php tools/gates/no_skeleton_mode_presets_default_gate.php`
- CI/rails policy:
  - this gate SHOULD run in the dedicated `gates` rail before tests
  - it MUST remain deterministic and rerun-no-diff

Usage (repo root):
- `composer no-skeleton-mode-presets-default:gate`

---

### No skeleton modules default gate

Id: `tool.no_skeleton_modules_default_gate` \
Entrypoint: `composer no-skeleton-modules-default:gate` \
Category: repo policy / guard \
Outputs:
- none (exits non-zero on violations; emits deterministic diagnostics)

Determinism:

| Mode / flags | Determinism   | Notes                                                                           |
|--------------|---------------|---------------------------------------------------------------------------------|
| default      | deterministic | Deterministic check for forbidden default skeleton module-selection files only. |

Notes:
- Purpose: forbids shipping parallel default module-selection files in the skeleton:
  - `packages/applications/skeleton/config/modules.php`
  - `packages/applications/skeleton/apps/*/config/modules.php`
- Canonical policy:
  - module selection is kernel-owned
  - it is resolved only via preset files + composer metadata
  - skeleton module-selection files are app-level overrides only and MUST NOT be present by default
- Output policy:
  - violation: line 1 is stable code `CORETSIA_NO_SKELETON_MODULES_DEFAULT_FORBIDDEN`
  - diagnostics are `<repo-relative-path>: forbidden-default-modules-config`, sorted deterministically
  - unexpected gate failure: line 1 is stable code `CORETSIA_NO_SKELETON_MODULES_DEFAULT_GATE_FAILED`
- Implementation detail: `@php tools/gates/no_skeleton_modules_default_gate.php`
- CI/rails policy:
  - this gate SHOULD run in the dedicated `gates` rail before tests
  - it MUST remain deterministic and rerun-no-diff

Usage (repo root):
- `composer no-skeleton-modules-default:gate`

---

### Contracts-only ports gate

Id: `tool.contracts_only_ports_gate` \
Entrypoint: `composer contracts-only-ports:gate` \
Category: repo policy / guard \
Outputs:
- none (exits non-zero on violations; emits deterministic diagnostics)

Determinism:

| Mode / flags | Determinism   | Notes                                                                                                  |
|--------------|---------------|--------------------------------------------------------------------------------------------------------|
| default      | deterministic | Deterministic scan of the `src/` roots of all products discovered by `WorkspacePackageCatalog::all()`. |

Notes:
- Purpose: forbids declaring canonical public ports outside the owner package:
  - allowed owner scope: `packages/core/contracts/src/**`
  - forbidden outside owner scope:
    - files named `*PortInterface.php`
    - paths under `src/**/Port/**`
- Deterministic scan scope:
  - PHP source files under the `src/` roots of all products discovered by `WorkspacePackageCatalog::all()`
  - the `core/contracts` product is the canonical public-port owner and its `src/` tree is exempt from forbidden-port reporting
- Exclusions:
  - `**/tests/**`
  - `**/fixtures/**`
  - `**/vendor/**`
- Output policy:
  - first line is stable code: `CORETSIA_CONTRACTS_ONLY_PORTS_FORBIDDEN`
  - next lines are repo-relative diagnostics in the form:
    - `<path>: <reason>`
  - diagnostics are sorted by `strcmp`
- Fixed reason tokens:
  - `forbidden-public-port-interface`
  - `forbidden-public-port-namespace`
- Unexpected gate/scanner failure: line 1 is stable code `CORETSIA_CONTRACTS_ONLY_PORTS_GATE_FAILED`.
- Implementation detail: `@php tools/gates/contracts_only_ports_gate.php`

Usage (repo root):
- `composer contracts-only-ports:gate`

---

### Reserved tags gate

Id: `tool.reserved_tags_registry_gate` \
Entrypoint: `composer reserved-tags:gate` \
Category: repo policy / guard \
Outputs:
- none on success
- deterministic diagnostics on reserved DI tag registry drift

Determinism:

| Mode / flags                                                | Determinism   | Notes                                                                                                  |
|-------------------------------------------------------------|---------------|--------------------------------------------------------------------------------------------------------|
| `composer reserved-tags:gate`                               | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines.                                 |
| `composer reserved-tags:gate -- --root=<fixture-repo-root>` | deterministic | Test/fixture repository-root override; the replacement root must satisfy canonical repository markers. |

Notes:
- Purpose: validates the centralized Coretsia-reserved DI tag identifier model.
- The canonical code-level registry is:
  - `Coretsia\Foundation\Tag\ReservedTags`
  - file: `packages/core/foundation/src/Tag/ReservedTags.php`
- `ReservedTags` owns reserved DI tag identifier strings only.
- Product source scanning uses the `src/` roots of all products discovered by `WorkspacePackageCatalog::all()`.
- `--root=<repo-root>` replaces the repository context for integration/fixture testing; it is not a scan-subtree selector.
- Runtime semantics, metadata schema, discovery, ordering, dispatch, validation, and consumer behavior remain owned by the semantic owner packages declared in `docs/ssot/tags.md`.
- Enforces the reserved tag registry policy:
  - `docs/ssot/tags.md` must contain a parseable reserved tag registry;
  - every reserved tag from `docs/ssot/tags.md` must have a matching public constant in `ReservedTags`;
  - each `ReservedTags` constant value must exactly equal the canonical tag string;
  - `ReservedTags` must not expose extra public tag-like constants outside the SSoT registry;
  - discovered product source must not define `src/Provider/Tags.php`;
  - discovered product source must not define package-local mirror constants for Coretsia-reserved DI tags;
  - discovered product source must not define local constants that alias `ReservedTags::*`.
- Output policy:
  - first line is stable code: `CORETSIA_RESERVED_TAGS_REGISTRY_DRIFT`
  - next lines are repo-root-relative violating paths + short reason tokens sorted by `strcmp`
- Scan-failure policy:
  - first line is stable code: `CORETSIA_RESERVED_TAGS_REGISTRY_GATE_FAILED`
  - no exception messages, stack traces, absolute paths, raw source snippets, secrets, or environment-specific diagnostics are emitted
- Implementation detail: `@php tools/gates/reserved_tags_registry_gate.php`
- CI/rails policy: `composer gates` MUST execute this gate through the named composer script, not by raw PHP path.

Usage (repo root):
- `composer reserved-tags:gate`
- `composer reserved-tags:gate -- --root=<fixture-repo-root>`

---

### Observability naming gate

Id: `tool.observability_naming_gate` \
Entrypoint: `composer observability-naming:gate` \
Category: repo policy / guard \
Outputs:
- none (exits non-zero on observability naming / label policy violations)

Determinism:

| Mode / flags                                         | Determinism   | Notes                                                                                          |
|------------------------------------------------------|---------------|------------------------------------------------------------------------------------------------|
| default                                              | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines.                         |
| `composer observability-naming:gate -- --path=<dir>` | deterministic | Narrows source scanning to canonical product `src/` roots selected by the repo-contained path. |

Notes:
- Purpose: enforces the canonical observability metric/span naming and global label allowlist policy from `docs/ssot/observability.md`.
- Default scan scope:
  - PHP source files under the `src/` roots of all products discovered by `WorkspacePackageCatalog::all()`
- Excluded paths:
  - `**/tests/**`
  - `**/fixtures/**`
  - `**/vendor/**`
- `--path=<dir>` policy:
  - path MUST resolve inside the repository root
  - the selector only narrows the canonical product `src/` roots through `GateRuntime::selectScanRoots()`
  - an unrelated repository directory selects no product source files
- Enforces at minimum:
  - metric names follow the canonical SSoT shape, for example `http.request_total` and `http.request_duration_ms`
  - span-name literals recognized in span-name positions follow the canonical dot-separated span-name shape
  - metric / label / attribute keys are limited to the SSoT allowlist:
    - `method`
    - `status`
    - `driver`
    - `operation`
    - `table`
    - `outcome`
  - forbidden label keys fail deterministically:
    - `field`
    - `path`
    - `property`
    - `request_id`
    - `correlation_id`
    - `tenant_id`
    - `user_id`
- Output policy:
  - first line is stable code: `CORETSIA_OBSERVABILITY_NAMING_DRIFT`
  - next lines are repo-relative paths with fixed reason tokens, sorted by `strcmp`
  - unexpected gate/scanner failure: line 1 is stable code `CORETSIA_OBSERVABILITY_NAMING_GATE_FAILED`
- Implementation detail: `@php tools/gates/observability_naming_gate.php`
- CI/rails policy: `composer gates` SHOULD execute this gate with the other Phase 1 tooling gates.

Usage (repo root):
- `composer observability-naming:gate`
- `composer observability-naming:gate -- --path=packages/core`

---

### Observability span naming gate

Id: `tool.observability_span_naming_gate` \
Entrypoint: `composer observability-span-naming:gate` \
Category: repo policy / guard \
Outputs:
- none on success
- exits non-zero on span naming policy violations
- emits deterministic diagnostics only through the canonical tooling output policy

Determinism:

| Mode / flags                                              | Determinism   | Notes                                                                                                  |
|-----------------------------------------------------------|---------------|--------------------------------------------------------------------------------------------------------|
| default                                                   | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines.                                 |
| `composer observability-span-naming:gate -- --path=<dir>` | deterministic | Narrows runtime source scanning to canonical product `src/` roots selected by the repo-contained path. |

Notes:
- Purpose: validates runtime span emissions against the canonical span naming policy from `docs/ssot/observability.md`.
- The gate is read-only and MUST NOT create, modify, or delete files.
- This gate is span-only.
- This gate complements `composer observability-naming:gate` and `composer observability-metric-catalog:gate`:
  - `observability-naming:gate` enforces generic observability naming and global observability label allowlist policy.
  - `observability-span-naming:gate` enforces `TracerPortInterface::startSpan(...)` and `TracerPortInterface::inSpan(...)` span name policy.
  - `observability-metric-catalog:gate` enforces `MeterPortInterface` metric catalog registration, meter method/type compatibility, and metric-specific label keys.
- Default scan scope:
  - PHP source files under the `src/` roots of all products discovered by `WorkspacePackageCatalog::all()`
- Excluded paths:
  - `**/docs/**`
  - `**/tests/**`
  - `**/tools/**`
  - `**/var/**`
  - `**/fixtures/**`
  - `**/vendor/**`
- `--path=<dir>` policy:
  - path MUST resolve inside the repository root
  - the selector only narrows canonical product `src/` roots through `GateRuntime::selectScanRoots()`
  - documentation policy validation still runs against `docs/ssot/observability.md`
- Enforces at minimum:
  - `docs/ssot/observability.md` contains a parseable canonical span naming policy
  - span names use shape `<domain>.<singular_operation>`
  - span operation segment is singular
  - runtime `TracerPortInterface::startSpan(...)` and `TracerPortInterface::inSpan(...)` span names follow canonical span naming policy
  - `TracerPortInterface::currentSpan()` is not validated because it does not accept or emit a span name
  - runtime span names are direct string literals or same-class private `const string` values accessed through `self::CONST`
  - malformed span names fail deterministically, for example:
    - `foundation..reset`
    - `foundation.reset.total`
  - plural span operation drift fails deterministically, for example:
    - `foundation.resets`
  - dynamic, computed, external, inherited, global, concatenated, or named-argument span emissions fail deterministically
- Span names are validated by span naming policy, not by the canonical metrics catalog.
- Span names MUST NOT be registered in the canonical metrics catalog.
- Metric catalog policy remains owned by `composer observability-metric-catalog:gate`.
- Output policy:
  - span naming drift / runtime span policy violation: line 1 is stable code `CORETSIA_OBSERVABILITY_SPAN_NAMING_DRIFT`
  - unexpected failure: line 1 is stable code `CORETSIA_OBSERVABILITY_SPAN_NAMING_GATE_FAILED`
  - diagnostics use repo-relative paths or `docs/ssot/observability.md` with fixed reason tokens
  - diagnostics are sorted by `strcmp`
  - if no violations exist, command exits 0 and prints nothing
- Implementation detail: `@php tools/gates/observability_span_naming_gate.php`
- CI/rails policy: `composer gates` SHOULD execute this gate immediately after `composer observability-naming:gate` and before `composer observability-metric-catalog:gate`.

Usage (repo root):
- `composer observability-span-naming:gate`
- `composer observability-span-naming:gate -- --path=packages/core`

---

### Observability metric catalog gate

Id: `tool.observability_metric_catalog_gate` \
Entrypoint: `composer observability-metric-catalog:gate` \
Category: repo policy / guard \
Outputs:
- none on success
- exits non-zero on canonical metrics catalog drift or runtime metric emission policy violations
- emits deterministic diagnostics only through the canonical tooling output policy

Determinism:

| Mode / flags                                                 | Determinism   | Notes                                                                                                  |
|--------------------------------------------------------------|---------------|--------------------------------------------------------------------------------------------------------|
| default                                                      | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines.                                 |
| `composer observability-metric-catalog:gate -- --path=<dir>` | deterministic | Narrows runtime source scanning to canonical product `src/` roots selected by the repo-contained path. |

Notes:
- Purpose: validates runtime metric emissions against the canonical metrics catalog and metric-specific label policy from `docs/ssot/observability.md`.
- The gate is read-only and MUST NOT create, modify, or delete files.
- This gate is metrics-only.
- This gate complements `composer observability-naming:gate` and `composer observability-span-naming:gate`:
  - `observability-naming:gate` enforces generic observability naming and global observability label allowlist policy.
  - `observability-span-naming:gate` enforces `TracerPortInterface::startSpan(...)` and `TracerPortInterface::inSpan(...)` span name policy.
  - `observability-metric-catalog:gate` enforces `MeterPortInterface` metric catalog registration, meter method/type compatibility, and metric-specific label keys.
- `MeterPortInterface` metric catalog membership, method/type compatibility, and metric-specific labels are validated by `composer observability-metric-catalog:gate`.
- Span names are validated by `composer observability-span-naming:gate`.
- Span names MUST NOT be registered in the canonical metrics catalog.
- Default scan scope:
  - PHP source files under the `src/` roots of all products discovered by `WorkspacePackageCatalog::all()`
- Excluded paths:
  - `**/docs/**`
  - `**/tests/**`
  - `**/tools/**`
  - `**/var/**`
  - `**/fixtures/**`
  - `**/vendor/**`
- `--path=<dir>` policy:
  - path MUST resolve inside the repository root
  - the selector only narrows canonical product `src/` roots through `GateRuntime::selectScanRoots()`
  - catalog and global-label policy validation still runs against `docs/ssot/observability.md`
- Enforces at minimum:
  - `docs/ssot/observability.md` contains a parseable `Canonical metrics catalog` section
  - catalog metric rows are unique
  - catalog metric type is exactly one of:
    - `counter`
    - `observe`
  - catalog labels are within the global label allowlist
  - runtime `MeterPortInterface::increment(...)` and `MeterPortInterface::observe(...)` metric names exist in the canonical catalog
  - runtime metric names are direct string literals or same-class private `const string` values accessed through `self::CONST`
  - `increment(...)` is used only with catalog `counter` metrics
  - `observe(...)` is used only with catalog `observe` metrics
  - emitted label keys match the metric-specific catalog row
  - label maps are omitted, direct array literals with string keys, or same-method local variables assigned before the meter call to resolvable array literals
  - dynamic, computed, external, inherited, global, concatenated, or named-argument meter emissions fail deterministically
- Output policy:
  - catalog drift / runtime metric policy violation: line 1 is stable code `CORETSIA_OBSERVABILITY_METRIC_CATALOG_DRIFT`
  - unexpected failure: line 1 is stable code `CORETSIA_OBSERVABILITY_METRIC_CATALOG_GATE_FAILED`
  - diagnostics use repo-relative paths or `docs/ssot/observability.md` with fixed reason tokens
  - diagnostics are sorted by `strcmp`
  - if no violations exist, command exits 0 and prints nothing
- Implementation detail: `@php tools/gates/observability_metric_catalog_gate.php`
- CI/rails policy: `composer gates` SHOULD execute this gate immediately after `composer observability-span-naming:gate`.

Usage (repo root):
- `composer observability-metric-catalog:gate`
- `composer observability-metric-catalog:gate -- --path=packages/core`

---

### Artifact header/schema gate

Id: `tool.artifact_header_schema_gate` \
Entrypoint: `composer artifact-header-schema:gate` \
Category: repo policy / guard \
Outputs:
- none (exits non-zero on artifact envelope/header/schema violations; emits deterministic diagnostics)

Determinism:

| Mode / flags | Determinism   | Notes                                                                  |
|--------------|---------------|------------------------------------------------------------------------|
| default      | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines. |

Notes:
- Purpose: validates generated artifacts against the canonical artifact envelope and header schema from `docs/ssot/artifacts.md`.
- Enforced baseline:
  - top-level envelope MUST be exactly `{ "_meta", "payload" }`
  - required `_meta` fields are `name`, `schemaVersion`, `fingerprint`, `generator`
  - artifact `name` and `schemaVersion` MUST match the canonical artifact registry
  - generated artifacts MUST NOT contain timestamps, absolute paths, or environment-specific bytes
  - JSON artifacts and PHP artifacts returning arrays are both supported
- Candidate generated-artifact roots:
  - repo-level: `var/`, `generated/`, `artifacts/`, `.generated/`, `.artifacts/`
  - per discovered product: `var/`, `generated/`, `artifacts/`, `.generated/`, `.artifacts/`, `build/generated/`, `build/artifacts/`
- Product roots are derived from `WorkspacePackageCatalog::all()`; package locations are not reconstructed from a fixed layer/slug path shape.
- Temporal artifact-materialization policy:
  - registry rows alone do not require an artifact file to exist yet
  - if no matching generated artifact file exists, the gate behaves as a deterministic no-op
  - if a matching generated artifact file exists, malformed envelope/header/schema fails deterministically
- Output policy:
  - first line is stable code: `CORETSIA_ARTIFACT_HEADER_SCHEMA_DRIFT`
  - next lines are normalized repo-relative paths plus fixed reason tokens sorted by `strcmp`
  - unexpected gate/scanner failure: line 1 is stable code `CORETSIA_ARTIFACT_HEADER_SCHEMA_GATE_FAILED`
- Implementation detail: `@php tools/gates/artifact_header_schema_gate.php`
- `composer gates` MUST execute this gate as part of the tooling rails chain.

Usage (repo root):
- `composer artifact-header-schema:gate`

---

### Cross-cutting contract gate

Id: `tool.cross_cutting_contract_gate` \
Entrypoint: `composer cross-cutting-contract:gate` \
Category: repo policy / guard \
Outputs:
- none on success
- deterministic diagnostics on cross-cutting contract policy violations

Determinism:

| Mode / flags | Determinism   | Notes                                                                  |
|--------------|---------------|------------------------------------------------------------------------|
| default      | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines. |

Notes:
- Purpose: enforces cross-cutting Kernel/Foundation/Contracts boundary invariants once the required owner-package evidence exists.
- Scan scope:
  - type/resettable analysis scans `src/` roots of all products discovered by `WorkspacePackageCatalog::all()`
  - service-tag and context-boundary analysis scans both `src/` and `config/` roots of all discovered products
- Enforced baseline:
  - services tagged as `kernel.stateful` MUST implement `Coretsia\Contracts\Runtime\ResetInterface`
  - services tagged as `kernel.stateful` MUST also be discoverable through the effective Foundation reset discovery tag
  - default effective Foundation reset discovery tag is `kernel.reset`
  - if `foundation.reset.tag` config evidence exists, that configured value is the effective Foundation reset discovery tag
  - the gate MUST NOT hardcode only `kernel.reset` when custom `foundation.reset.tag` evidence is present
  - if either `core/contracts` or `core/foundation` is not discovered, the gate behaves as a deterministic no-op
  - if `core/contracts/src/Runtime/ResetInterface.php` or `core/foundation/src/Tag/ReservedTags.php` is not present, the gate behaves as a deterministic no-op
  - public `Coretsia\Contracts\Context\ContextKeys` evidence does not activate forbidden-usage reporting by itself
- Foundation reset tag evidence:
  - canonical default: `kernel.reset`
  - optional config evidence path: `packages/core/foundation/config/foundation.php`
  - optional config key namespace: `foundation.reset.tag`
  - the config file MUST return the `foundation` subtree, so the file-level shape is:
    - `['reset' => ['tag' => '<tag-name>']]`
  - custom tag values MUST follow canonical reserved tag naming syntax:
    - `^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$`
- Additional enforcement:
  - direct `Coretsia\Foundation\Context\ContextStore` usage is forbidden outside explicit owner boundaries
  - public `Coretsia\Contracts\Context\ContextKeys` usage is allowed across runtime/platform packages as stable key vocabulary
  - legacy `Coretsia\Foundation\Context\ContextKeys` references are forbidden and reported deterministically
- Context boundary enforcement:
  - `Coretsia\Contracts\Context\ContextKeys` defines public context key identifiers only
  - importing `Coretsia\Contracts\Context\ContextKeys` does not grant write ownership over context values
  - mutable context storage ownership remains guarded through `Coretsia\Foundation\Context\ContextStore`
  - direct `ContextStore` usage is allowed in `packages/core/kernel/src/Runtime/KernelRuntime.php`
  - direct `ContextStore` usage is allowed in `packages/core/kernel/src/Provider/KernelServiceFactory.php`
  - in `packages/core/foundation/src/Provider/FoundationServiceProvider.php`, `ContextStore` is allowed only as the subject of `new ContextStore(...)` or a `ContextStore::class` reference
- Output policy:
  - first line is stable code: `CORETSIA_CROSS_CUTTING_CONTRACT_DRIFT`
  - next lines are repo-relative paths plus fixed reason tokens sorted by byte-order `strcmp`
  - diagnostics MUST NOT include absolute paths, raw config payloads, secrets, source snippets, or environment-specific values
  - unexpected gate/scanner failure: line 1 is stable code `CORETSIA_CROSS_CUTTING_CONTRACT_GATE_FAILED`
- Fixed reason tokens:
  - `kernel-tags-drift`
  - `foundation-reset-tag-invalid`
  - `kernel-stateful-service-missing-reset-tag`
  - `kernel-stateful-service-class-unresolved`
  - `kernel-stateful-service-not-resettable`
  - `forbidden-context-store-usage`
  - `forbidden-context-keys-usage`
- Reason token notes:
  - `forbidden-context-store-usage` applies to unauthorized direct `Coretsia\Foundation\Context\ContextStore` usage
  - `forbidden-context-keys-usage` applies to legacy `Coretsia\Foundation\Context\ContextKeys` references
  - `forbidden-context-keys-usage` MUST NOT be emitted for valid `Coretsia\Contracts\Context\ContextKeys` usage
- Implementation detail: `@php tools/gates/cross_cutting_contract_gate.php`
- `composer gates` MUST execute this gate as part of the tooling rails chain.

Usage (repo root):
- `composer cross-cutting-contract:gate`

---

### Kernel public API gate

Id: `tool.kernel_public_api_gate` \
Entrypoint: `composer kernel-public-api:gate` \
Category: repo policy / guard \
Outputs:
- none (exits non-zero on kernel public API surface violations; emits deterministic diagnostics)

Determinism:

| Mode / flags | Determinism   | Notes                                                                  |
|--------------|---------------|------------------------------------------------------------------------|
| default      | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines. |

Notes:
- Purpose: enforces the canonical `core/kernel` public API surface once the owner public-surface evidence exists.
- Activation and public API evidence policy:
  - if the `core/kernel` product is not discovered by `WorkspacePackageCatalog`, the gate behaves as a deterministic no-op
  - once `core/kernel` is discovered, a missing `src/` directory is a policy violation
  - once `core/kernel` is discovered, missing public-surface evidence is a policy violation
  - an existing `src/` tree with no declared class/interface/trait/enum symbols is a policy violation
  - once source and evidence exist, the gate validates the complete non-internal `core/kernel` type surface
- Evidence sources include explicit kernel public API docs/config and public API contract-test evidence, for example:
  - `packages/core/kernel/PUBLIC_API.md`
  - `packages/core/kernel/public-api.md`
  - `packages/core/kernel/public_api.md`
  - `packages/core/kernel/public-api.json`
  - `packages/core/kernel/public_api.json`
  - `packages/core/kernel/public-api.php`
  - `packages/core/kernel/public_api.php`
  - `packages/core/kernel/docs/PUBLIC_API.md`
  - `packages/core/kernel/docs/public-api.md`
  - `packages/core/kernel/docs/public_api.md`
  - `docs/ssot/kernel-public-api.md`
  - `docs/ssot/kernel_public_api.md`
  - `docs/architecture/kernel-public-api.md`
  - `docs/architecture/kernel_public_api.md`
  - `packages/core/kernel/tests/**/*PublicApi*.php`
  - `packages/core/kernel/tests/**/*PublicAPI*.php`
  - `packages/core/kernel/tests/**/*PublicSurface*.php`
  - `packages/core/kernel/tests/**/*ApiSurface*.php`
  - `packages/core/kernel/tests/**/*KernelPublic*.php`
- Enforced baseline once evidence exists:
  - public `core/kernel/src` symbols MUST be listed in the public API evidence
  - symbols listed in public API evidence MUST resolve to declared types under `core/kernel/src`
  - symbols listed as public API MUST NOT be internal
  - internal symbols are detected through `@internal` docblocks or `Internal` / `internal` path segments
- Output policy:
  - first line is stable code: `CORETSIA_KERNEL_PUBLIC_API_DRIFT`
  - next lines are normalized repo-relative paths plus fixed reason tokens sorted by `strcmp`
  - unexpected gate/scanner failure: line 1 is stable code `CORETSIA_KERNEL_PUBLIC_API_GATE_FAILED`
- Fixed reason tokens:
  - `kernel-public-api-source-missing`
  - `kernel-public-api-evidence-missing`
  - `kernel-public-api-source-empty`
  - `kernel-public-api-evidence-empty`
  - `kernel-public-api-symbol-internal-listed`
  - `kernel-public-api-symbol-unlisted`
- Missing evidence symbols are reported as `kernel-public-api-symbol-missing:<fqcn>`.
- Implementation detail: `@php tools/gates/kernel_public_api_gate.php`
- This gate is a standalone rail; optional phpstan/static-analysis rules MAY exist later only as supplemental enforcement, not as a replacement for this command.
- `composer gates` MUST execute this gate as part of the tooling rails chain.

Usage (repo root):
- `composer kernel-public-api:gate`

---

### No runtime tooling artifacts gate

Id: `tool.no_runtime_tooling_artifacts_gate` \
Entrypoint: `composer no-runtime-tooling-artifacts:gate` \
Category: repo policy / runtime purity guard \
Outputs:
- none on success
- exits non-zero on runtime tooling artifact violations
- emits deterministic diagnostics only through the canonical tooling output policy

Determinism:

| Mode / flags                                 | Determinism   | Notes                                                                               |
|----------------------------------------------|---------------|-------------------------------------------------------------------------------------|
| `composer no-runtime-tooling-artifacts:gate` | deterministic | Read-only; scans `src/` and `config/` roots of all non-`devtools` layered products. |

Notes:
- Purpose: prevents runtime packages from importing, requiring, executing, or reading repository tooling code or tooling-generated architecture artifacts.
- This is a runtime-purity gate, not a second architecture dependency brain.
- It complements deptrac because deptrac catches namespace/class dependencies, while this gate catches string-path reads, require/include paths, shell invocations, and accidental runtime consumption of tooling artifacts.
- Default scan scope:
  - `src/` and `config/` roots of all products returned by `WorkspacePackageCatalog::layeredPackages()`
  - products in the `devtools` layer are excluded
  - special distributions `packages/framework` and `packages/applications/skeleton` are outside this gate
- Excluded paths:
  - `packages/devtools/**`
  - `**/tests/**`
  - `**/fixtures/**`
  - `**/vendor/**`
  - `tools/**` as scan input
- Forbidden evidence:
  - namespace imports or references to `Coretsia\Tools\`
  - namespace imports or references to `Coretsia\Devtools\`
  - composer/package references to devtools packages
  - runtime path reads/includes/execs involving `tools/`
  - runtime reads of `var/arch`
  - shell command strings that execute tooling paths from runtime code
- Allowed evidence:
  - docs-only mentions outside scan scope
  - tests/fixtures mentions outside runtime scan scope
  - CI/tooling code under `tools/**`
  - generated architecture artifacts consumed by CI/tooling jobs only
  - PHP comments and PHPDoc are removed before tooling-path detection and therefore do not count as runtime dependency evidence
- Output policy:
  - violation: line 1 is stable code `CORETSIA_RUNTIME_TOOLING_ARTIFACTS_VIOLATION`
  - scanner/internal failure: line 1 is stable code `CORETSIA_RUNTIME_TOOLING_ARTIFACTS_GATE_FAILED`
  - diagnostics are repo-relative
  - diagnostics are sorted by byte-order `strcmp`
  - diagnostics MUST NOT include source snippets, raw file contents, absolute paths, secrets, environment values, headers, or tokens
- Fixed reason tokens:
  - `runtime-imports-tools`
  - `runtime-imports-devtools`
  - `runtime-references-devtools-package`
  - `runtime-reads-repository-tools`
  - `runtime-executes-tooling-path`
  - `runtime-reads-architecture-artifact`
- Deterministic no-op policy:
  - if no runtime package scan roots exist, the gate exits 0 and prints nothing
- Non-goals:
  - MUST NOT duplicate deptrac layer rules
  - MUST NOT parse `docs/architecture/DEPENDENCIES.md`
- Implementation detail: `@php tools/gates/no_runtime_tooling_artifacts_gate.php`
- Aggregate rail integration:
  - `composer gates` invokes this gate after `composer repo:text:gate`
  - `composer gates` invokes this gate before `composer package-compliance:gate`
  - this gate SHOULD run before package tests in CI

Usage (repo root):
- `composer no-runtime-tooling-artifacts:gate`

---

### Deptrac config and Composer-edge consistency check

Id: `tool.arch_deptrac_check` \
Entrypoint: `composer arch:deptrac:check` \
Category: architecture / guard / CI rail \
Outputs:
- none on success
- deterministic error code and diagnostics on failure
- MUST NOT update tracked generated files
- MUST NOT materialize graph artifacts; graph generation is owned by `composer arch:deptrac:generate`

Determinism:

| Mode / flags                  | Determinism   | Notes                                                   |
|-------------------------------|---------------|---------------------------------------------------------|
| `composer arch:deptrac:check` | deterministic | Pure check; MUST be rerun-no-diff w.r.t. tracked files. |

Notes:
- Purpose: checks that canonical Deptrac generated files are up to date and validates internal Composer require edges against the canonical SSoT dependency table.
- Checked tracked outputs:
  - `tools/testing/deptrac.yaml`
  - `tools/testing/deptrac.allowlist.yaml`
- Reads dependency policy from:
  - `docs/architecture/DEPENDENCIES.md`
- Package discovery:
  - uses `WorkspacePackageCatalog::layeredPackages()`
  - special distributions `packages/framework` and `packages/applications/skeleton` are excluded
  - Composer identity comes from each package `composer.json.name`
  - architecture policy identity uses the catalog package id `<layer>/<slug>`
- Internal Composer dependency policy:
  - only runtime `require` entries whose package names start with `coretsia/` are considered
  - external vendor packages are out of scope
  - every mapped internal production Composer dependency MUST be allowed by the source package’s direct `depends_on` cell in `docs/architecture/DEPENDENCIES.md`
  - internal package self-requires are forbidden
  - unknown internal `coretsia/*` package names fail deterministically
  - diagnostics use package ids, deterministic reason tokens, and deterministic error codes
  - diagnostics MUST NOT include absolute paths, raw Composer JSON, source code, filesystem layout, exception messages, or stack traces
- Generated config analyzes package `src/` roots only.
- Allowlist policy:
  - may exclude tests, fixtures, vendor, or tooling-only paths
  - MUST NOT exclude `packages/**/src/**`
- This command is part of the `composer arch` aggregate rail.
- Implementation detail: `@php tools/build/deptrac_generate.php --check`
- Failure output policy:
  - drift: line 1 is stable code `CORETSIA_DEPTRAC_OUT_OF_DATE`
  - missing dependency policy: line 1 starts with stable code `CORETSIA_DEPTRAC_SSOT_RULESET_MISSING`
  - cycle detected: line 1 starts with stable code `CORETSIA_DEPTRAC_CYCLE_DETECTED`
  - invalid allowlist: line 1 starts with stable code `CORETSIA_DEPTRAC_ALLOWLIST_INVALID`
  - Composer edge missing from SSoT: line 1 starts with stable code `CORETSIA_DEPTRAC_COMPOSER_EDGE_NOT_IN_SSOT`
  - unexpected failure: line 1 starts with stable code `CORETSIA_DEPTRAC_GENERATE_FAILED`

Usage (repo root):
- `composer arch:deptrac:check`

---

### Deptrac config and graph generator

Id: `tool.arch_deptrac_generate` \
Entrypoint: `composer arch:deptrac:generate` \
Category: architecture / generator / CI artifact materialization \
Outputs:
- `tools/testing/deptrac.yaml`
- `tools/testing/deptrac.allowlist.yaml` *(created if missing)*
- `var/arch/deptrac_graph.dot` *(gitignored / CI artifact)*
- `var/arch/deptrac_graph.svg` *(gitignored / CI artifact)*
- `var/arch/deptrac_graph.html` *(gitignored / CI artifact)*

Determinism:

| Mode / flags                       | Determinism   | Notes                                                                |
|------------------------------------|---------------|----------------------------------------------------------------------|
| `composer arch:deptrac:generate`   | deterministic | Generates Deptrac config, allowlist if missing, and graph artifacts. |

Notes:
- Purpose: materializes canonical Deptrac architecture outputs from repository SSoT data.
- Reads dependency policy from:
  - `docs/architecture/DEPENDENCIES.md`
- Package discovery:
  - uses `WorkspacePackageCatalog::layeredPackages()`
  - special distributions `packages/framework` and `packages/applications/skeleton` are excluded
  - Composer identity comes from each package `composer.json.name`
  - architecture policy identity uses the catalog package id `<layer>/<slug>`
- Generated config analyzes package `src/` roots only.
- Exclusions are controlled by:
  - `tools/testing/deptrac.allowlist.yaml`
- Allowlist policy:
  - may exclude tests, fixtures, vendor, or tooling-only paths
  - MUST NOT exclude `packages/**/src/**`
- Graph artifacts are generated for CI upload / architecture inspection only.
- This command is not part of the `composer arch` aggregate rail because it is mutating/materializing.
- CI MAY run this command separately in the `arch` job to upload Deptrac graph artifacts.
- It MUST remain deterministic and rerun-no-diff for the same repo state.
- Success output policy:
  - emits `OK`
  - when files change, additional lines contain the changed repo-relative output path or artifact directory
- Implementation detail: `@php tools/build/deptrac_generate.php --apply`
- Failure output policy:
  - missing dependency policy: line 1 starts with stable code `CORETSIA_DEPTRAC_SSOT_RULESET_MISSING`
  - cycle detected: line 1 starts with stable code `CORETSIA_DEPTRAC_CYCLE_DETECTED`
  - invalid allowlist: line 1 starts with stable code `CORETSIA_DEPTRAC_ALLOWLIST_INVALID`
  - unexpected failure: line 1 starts with stable code `CORETSIA_DEPTRAC_GENERATE_FAILED`

Usage (repo root):
- `composer arch:deptrac:generate`

---

### Deptrac architecture analysis

Id: `tool.arch_deptrac_analyze` \
Entrypoint: `composer arch:deptrac:analyze` \
Category: architecture / dependency analysis / CI rail \
Outputs:
- none on success
- native Deptrac diagnostics on violations/failure

Determinism:

| Mode / flags                      | Determinism   | Notes                                                      |
|-----------------------------------|---------------|------------------------------------------------------------|
| `composer arch:deptrac:analyze`   | deterministic | Runs Deptrac against the canonical generated config.       |

Notes:
- Purpose: runs Deptrac architecture analysis using the canonical generated config:
  - `tools/testing/deptrac.yaml`
- This command is part of the `composer arch` aggregate rail.
- This command depends on generated Deptrac config being up to date; CI SHOULD run `composer arch:deptrac:check` before this command.
- Composer-edge SSoT consistency is validated before this step by `composer arch:deptrac:check`; this command only runs native Deptrac analysis against the generated config.
- This is a third-party architecture analysis command, not a Coretsia policy gate.
  - It does NOT guarantee line 1 is a `CORETSIA_*` code.
  - Native Deptrac diagnostics are expected.
- Implementation detail: `@php vendor/bin/deptrac analyse --config-file=tools/testing/deptrac.yaml --no-cache`

Usage (repo root):
- `composer arch:deptrac:analyze`

---

### Quality aggregate rail

Id: `tool.quality` \
Entrypoint: `composer quality` \
Category: quality / aggregate rail \
Outputs:
- none directly
- delegates to quality tools, which may produce native diagnostics
- may create/update internal tool cache files, including:
  - `var/phpstan/**`

Determinism:

| Mode / flags       | Determinism   | Notes                                                                      |
|--------------------|---------------|----------------------------------------------------------------------------|
| `composer quality` | deterministic | Runs the canonical quality checks in stable order; writes no source files. |

Notes:
- Purpose: aggregate quality rail for code style and static analysis.
- Execution order is cemented:
  1) `composer cs:check`
  2) `composer phpstan`
- This command is an aggregate quality rail, not a Coretsia policy gate.
  - It does NOT guarantee line 1 is a `CORETSIA_*` code.
  - It preserves native diagnostics from the underlying tools.
- Implementation detail: aggregate `quality` script in `composer.json`
- CI/rails policy:
  - this command SHOULD run in CI after `composer gates`
  - this command SHOULD run before `composer test`
  - this command MUST use `cs:check`, not `cs:fix`
  - this command MUST NOT modify source files

Usage (repo root):
- `composer quality`

---

### Code style check

Id: `tool.cs_check` \
Entrypoint: `composer cs:check` \
Category: quality / code style \
Outputs:
- none on success
- tool diagnostics on failure
- may create/update internal tool cache files only if the underlying tool does so

Determinism:

| Mode / flags        | Determinism   | Notes                                                     |
|---------------------|---------------|-----------------------------------------------------------|
| `composer cs:check` | deterministic | Checks code style baseline; MUST NOT rewrite source code. |

Notes:
- Purpose: runs the canonical Coretsia code style baseline.
- Configuration SSoT:
  - `tools/cs/ecs.php`
- Scan scope is defined by the ECS config and currently includes:
  - `packages`
  - `tools`
- Exclusions are defined by `tools/cs/ecs.php`.
- This is a third-party quality command, not a Coretsia policy gate.
  - It does NOT guarantee line 1 is a `CORETSIA_*` code.
  - Native ECS diagnostics are expected.
- Implementation detail: `@php vendor/bin/ecs check --config=tools/cs/ecs.php`
- CI/rails policy:
  - this command SHOULD run in the `quality` rail
  - this command MAY run after gates and before tests
  - this command MUST NOT modify source files

Usage (repo root):
- `composer cs:check`

---

### Code style fixer

Id: `tool.cs_fix` \
Entrypoint: `composer cs:fix` \
Category: quality / code style / mutating fixer \
Outputs:
- may rewrite PHP source files in the configured ECS scan scope

Determinism:

| Mode / flags      | Determinism                | Notes                                         |
|-------------------|----------------------------|-----------------------------------------------|
| `composer cs:fix` | deterministic but mutating | Rewrites files according to the ECS baseline. |

Notes:
- Purpose: applies the canonical Coretsia code style baseline.
- Configuration SSoT:
  - `tools/cs/ecs.php`
- This command is intentionally mutating:
  - it MAY rewrite files under the configured ECS paths
  - it MUST NOT be used as a CI check command
- CI/rails policy:
  - CI MUST use `composer cs:check`, not `composer cs:fix`
  - `composer cs:fix` is a local developer command only
- This is a third-party quality command, not a Coretsia policy gate.
  - It does NOT guarantee line 1 is a `CORETSIA_*` code.
  - Native ECS diagnostics are expected.
- Implementation detail: `@php vendor/bin/ecs check --config=tools/cs/ecs.php --fix`

Usage (repo root):
- `composer cs:fix`

---

### Static analysis baseline

Id: `tool.phpstan` \
Entrypoint: `composer phpstan` \
Category: quality / static analysis \
Outputs:
- none on success
- native PHPStan diagnostics on failure
- internal PHPStan cache:
  - `var/phpstan/**`

Determinism:

| Mode / flags       | Determinism   | Notes                                              |
|--------------------|---------------|----------------------------------------------------|
| `composer phpstan` | deterministic | Runs the canonical Coretsia static analysis setup. |

Notes:
- Purpose: runs the canonical Coretsia static analysis baseline.
- Configuration SSoT:
  - `tools/phpstan/phpstan.neon`
- Analysis scope is defined by PHPStan config and currently starts from:
  - `packages`
  - `tools`
- Explicit analysis exclusions include:
  - package-local `vendor/**`
  - package-local `var/**`
  - package test trees under `packages/**/tests/**`
  - the repository tooling test tree `tools/tests/**`
  - configured tooling fixture paths
- PHPStan cache policy:
  - `var/phpstan/**` is internal tool cache
  - it is NOT a Coretsia generated artifact
  - artifact/schema gates MUST NOT treat PHPStan cache as generated artifact output
- This is a third-party quality command, not a Coretsia policy gate.
  - It does NOT guarantee line 1 is a `CORETSIA_*` code.
  - Native PHPStan diagnostics are expected.
- Implementation detail: `@php vendor/bin/phpstan analyse --configuration=tools/phpstan/phpstan.neon --memory-limit=1G`
- CI/rails policy:
  - this command SHOULD run in the `quality` rail
  - this command MAY run after gates and before tests
  - lock drift checks MUST still fail if dependency installation or tooling changes lock files

Usage (repo root):
- `composer phpstan`
