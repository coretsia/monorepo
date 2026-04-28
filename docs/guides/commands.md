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

> **Scope:** Canonical command catalog.  
> **Normative:** MUST / MUST NOT / SHOULD / MAY  
> **Source of truth for workflow rules:** `docs/roadmap/ROADMAP.md`  
> **See also (workflow):** `docs/guides/development-workflow.md`

This document fixes the **canonical** commands/entrypoints that actually exist in the repository, and the rules for documenting them.

---

## Global rules (applies to all commands)

- Commands in this document **MUST** be executed from the **repo root** (if a command requires otherwise, that **MUST** be explicitly stated in the entry).
- Adding/changing/removing a command **MUST** be accompanied by an update to this file.
- **Canonical entrypoints policy:** in this SSoT, **canonical entrypoints** are:
  - repo-root `composer <script>` (scripts from root `composer.json`) — **preferred** canonical entrypoints;
  - `php <repo-relative-path>` — **DIRECT** canonical entrypoints **only if** the command does not yet have a repo-root composer wrapper.
- Direct calls such as `composer --working-dir=... <subcommand>` are an **implementation detail**.
  - They **MAY** be mentioned only in `Notes` as “under the hood”, but **MUST NOT** be treated as a public entrypoint and **MUST NOT** be used in CI rails / workflow examples.
- If a command is documented as **DIRECT** (`php ...`), it **SHOULD** receive a repo-root `composer <script>` wrapper by the next cutline/milestone.
- For every command, the following **MUST** be stated clearly:
  - canonical path/entrypoint,
  - outputs (what exactly is created/updated),
  - determinism policy (deterministic vs nondeterministic modes),
  - usage examples.
- If a command has a mode/flag that makes output **nondeterministic**, that mode **MUST** be marked as **NONDETERMINISTIC** and **MUST NOT** be used in CI rails / rerun-no-diff workflows.
- Documentation/examples **MUST** avoid “non-existent” entrypoints for the current context (for example, do not reference `./dev/**` in Prelude).
- If a command has an **alias** (for example, `composer ...` as a proxy to `coretsia ...`), the document **MUST** explicitly state:
  - which entrypoint is **canonical**, and which one is **alias/compat**, and **MUST** guarantee behavior equivalence (semantics/outputs) in deterministic mode.
- When an entrypoint is **migrated** (the canonical one changes), the previous canonical entrypoint **SHOULD** remain as a **compat alias** for at least 1 epic/phase (or until the next cutline), and **MUST** be marked as `DEPRECATED` with a “remove-after” milestone.

---

## Entry format (how to extend this file)

Each new command is added as a separate section under `## Commands` (the format currently used is `### <title>`), in the following form:

- **Id:** stable id (snake / kebab, without spaces)
- **Entrypoint:** canonical entrypoint (repo-root)
- **Category:** classification (informational)
- **Outputs:** list of files/directories that are created/updated
- **Determinism:** mode table (deterministic / nondeterministic)
- **Notes:** semantics / invariants / CI policy / “under the hood” (MUST NOT duplicate lists of canonical entrypoints)
- **Usage:** usage examples (list). If there are several canonical variants (for example `:all/:root/:framework/:skeleton`) — **all** of them are listed here.

---

## Commands

### Project structure generator

**Id:** `tool.generate_structure`
**Entrypoint:** `composer docs:structure`
**Category:** documentation generator
**Outputs:**
- `docs/generated/GENERATED_STRUCTURE.md` (full structure, includes PHP symbols)
- `docs/generated/GENERATED_STRUCTURE_TREE.md` (tree-only, no PHP symbols)

**Determinism:**

| Mode / flags                                  | Determinism      | Notes                         |
|-----------------------------------------------|------------------|-------------------------------|
| `composer docs:structure`                     | deterministic    | writes both outputs           |
| `composer docs:structure:tree`                | deterministic    | writes tree-only output       |
| `composer docs:structure:full`                | deterministic    | writes full output            |
| `composer docs:structure -- --timestamp`      | NONDETERMINISTIC | injects timestamp into output |
| `composer docs:structure:tree -- --timestamp` | NONDETERMINISTIC | tree-only + timestamp         |
| `composer docs:structure:full -- --timestamp` | NONDETERMINISTIC | full + timestamp              |

**Notes:**
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script docs:structure --`
  - `@composer --working-dir=framework run-script docs:structure:tree --`
  - `@composer --working-dir=framework run-script docs:structure:full --`
- Framework implementation detail: `@php tools/build/generate_structure.php` (framework workspace).
- Failure output policy:
  - on unexpected failure, line 1 starts with stable code: `CORETSIA_STRUCTURE_GENERATE_FAILED`
- Direct call `php framework/tools/build/generate_structure.php` is **NOT** a canonical entrypoint (kept as implementation detail only).

**Usage (repo root):**
- `composer docs:structure`
- `composer docs:structure:tree`
- `composer docs:structure:full`
- `composer docs:structure -- --timestamp`
- `composer docs:structure:tree -- --timestamp`
- `composer docs:structure:full -- --timestamp`

---

### Monorepo workspace setup

**Id:** `project.setup`  
**Entrypoint:** `composer setup`  
**Category:** workspace bootstrap  
**Outputs:**
- (local) Git config: enables hooks via `core.hooksPath=.githooks`
- `vendor/**` (root) *(untracked)*
- `framework/vendor/**` *(untracked)*
- `skeleton/vendor/**` *(untracked)*
- (optional, only if repositories drift is detected) `framework/var/backups/workspace/**` *(gitignored)*

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                                            |
|--------------|---------------|--------------------------------------------------------------------------------------------------|
| default      | deterministic | Deterministic w.r.t. tracked files; installs from lockfiles. Network I/O is expected (Composer). |

**Notes:**
- `composer setup` is an aggregate entrypoint and executes (in order):
  1) `composer hooks:install`
  2) `composer sync:repos`
  3) `composer install:all`
  4) `composer validate:all`
- `composer sync:repos` MAY create backups under `framework/var/backups/workspace/**` only if repositories drift is detected and files are changed.

**Usage (repo root):**
- `composer setup`

---

### CI rails (project-wide)

**Id:** `project.ci`  
**Entrypoint:** `composer ci`  
**Category:** CI / verification  
**Outputs:**
- No tracked outputs on success (MUST be rerun-no-diff w.r.t. tracked files)
- Installs vendors (untracked) and runs validations + gates + DTO rail + arch + quality + tests
- Fails if any lockfile changed after install

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                           |
|--------------|---------------|---------------------------------------------------------------------------------|
| default      | deterministic | Deterministic w.r.t. tracked files; includes `sync:check` and lock drift guard. |

**Notes:**
- `composer ci` is an aggregate rails command and executes (in order):
  1) `composer sync:check`
  2) `composer install:all`
  3) `composer validate:all`
  4) `composer gates`
  5) `composer dto:gate`
  6) `composer arch`
  7) `composer quality`
  8) `composer framework:test`
  9) `composer spike:test`
  10) `composer lock:check`
- `composer gates` is the canonical baseline/tooling gates aggregate rail.
- `composer dto:gate` is the canonical aggregate DTO policy rail and MUST run after baseline gates and before arch/quality/tests.
- `composer arch` is the canonical aggregate architecture rail and MUST remain rerun-no-diff.
- `composer quality` is a third-party quality aggregate rail and MAY emit native ECS/PHPStan diagnostics.
- `composer framework:test` MUST support args-forwarding via `--` (see `framework.test`).
- `composer spike:test` preserves the Phase 0 spikes rails chain.

**Usage (repo root):**
- `composer ci`

---

### Package index (arch) check

**Id:** `tool.arch_package_index_check`  
**Entrypoint:** `composer arch:package-index:check`  
**Category:** architecture / guard  
**Outputs:**
- none (exits non-zero if package index drift is detected)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                   |
|--------------|---------------|---------------------------------------------------------|
| default      | deterministic | Pure check; MUST be rerun-no-diff w.r.t. tracked files. |

**Notes:**
- Checks drift vs the generated artifact: `framework/tools/testing/package-index.php`.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script arch:package-index:check --`
- Framework implementation detail: `@php tools/build/package_index.php --check` (framework workspace).
- Failure output policy:
  - drift: line 1 is stable code `CORETSIA_PACKAGE_INDEX_OUT_OF_DATE`
  - unexpected failure: line 1 starts with stable code `CORETSIA_PACKAGE_INDEX_FAILED`

**Usage (repo root):**
- `composer arch:package-index:check`

---

### Package index (arch) generate

**Id:** `tool.arch_package_index_generate`  
**Entrypoint:** `composer arch:package-index:generate`  
**Category:** architecture / generator  
**Outputs:**
- Updates generated package index artifact: `framework/tools/testing/package-index.php`

**Determinism:**

| Mode / flags | Determinism   | Notes                                                               |
|--------------|---------------|---------------------------------------------------------------------|
| default      | deterministic | Deterministic generator; MUST be rerun-no-diff for same repo state. |

**Notes:**
- Tool supports overriding output path via `--out`, but canonical workflow uses the default artifact path above.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script arch:package-index:generate --`
- Framework implementation detail: `@php tools/build/package_index.php --apply` (framework workspace).
- Failure output policy:
  - unexpected failure: line 1 starts with stable code `CORETSIA_PACKAGE_INDEX_FAILED`

**Usage (repo root):**
- `composer arch:package-index:generate`

---

### Architecture aggregate rail

**Id:** `tool.arch`
**Entrypoint:** `composer arch`
**Category:** architecture / CI rail
**Outputs:**
- none on success
- MUST NOT update tracked generated files
- MUST NOT materialize graph artifacts; graph generation is owned by `composer arch:deptrac:generate`

**Determinism:**

| Mode / flags    | Determinism   | Notes                                                                     |
|-----------------|---------------|---------------------------------------------------------------------------|
| `composer arch` | deterministic | Aggregate check/analyze rail; MUST be rerun-no-diff w.r.t. tracked files. |

**Notes:**
- Purpose: executes the canonical architecture verification rail.
- Execution order is cemented:
  1) `composer arch:package-index:check`
  2) `composer arch:deptrac:check`
  3) `composer arch:deptrac:analyze`
- This command is a check/analyze aggregate. It does **not** run `composer arch:package-index:generate` or `composer arch:deptrac:generate`.
- CI may run `composer arch:deptrac:generate` separately to materialize Deptrac graph artifacts for upload, but that is not part of the `composer arch` aggregate.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script arch --`
- Framework implementation detail: aggregate `arch` script in `framework/composer.json`.

**Usage (repo root):**
- `composer arch`

---

### Test suite (project-wide)

**Id:** `project.test`  
**Entrypoint:** `composer test`  
**Category:** testing  
**Outputs:**
- No tracked outputs on success
- `framework/var/phpunit/phpunit.discovered.xml` *(gitignored; generated runtime artifact)*

**Determinism:**

| Mode / flags | Determinism   | Notes                                 |
|--------------|---------------|---------------------------------------|
| default      | deterministic | Delegates to `framework` test runner. |

**Notes:**
- `composer test` is the canonical repo-root entrypoint for framework/packages tests.
- Execution order is cemented:
  1) `composer package:phpunit:gate`
  2) framework package-discovery PHPUnit runner
- Runner semantics (deterministic):
  - prints a prelude list of discovered packages as `==> package: ...`
  - generates `framework/var/phpunit/phpunit.discovered.xml` *(gitignored runtime artifact)*
  - runs PHPUnit **once** (single process) using the generated config
- Policy:
  - package-local `phpunit.xml` / `phpunit.dist.xml` under `framework/packages/*/*` are **forbidden**
  - canonical source of truth for framework/packages PHPUnit config is `framework/tools/testing/phpunit.xml`
  - generated artifact `framework/var/phpunit/phpunit.discovered.xml` is runtime-only and MUST NOT be hand-edited
- `--strict` is forwarded to the framework runner, but MUST NOT be interpreted as a requirement for package-local PHPUnit config files.

**Usage (repo root):**
- `composer test`
- `composer test -- --filter <pattern>`
- `composer test -- --testsuite all`
- `composer test -- --group contract`

---

### Test suite (fast)

**Id:** `project.test_fast`  
**Entrypoint:** `composer test-fast`  
**Category:** testing  
**Outputs:**
- No tracked outputs on success
- `framework/var/phpunit/phpunit.discovered.xml` *(gitignored; generated runtime artifact)*

**Determinism:**

| Mode / flags | Determinism   | Notes                                                |
|--------------|---------------|------------------------------------------------------|
| default      | deterministic | Alias of framework tests (currently same as `test`). |

**Usage (repo root):**
- `composer test-fast`

---

### Test suite (slow)

**Id:** `project.test_slow`  
**Entrypoint:** `composer test-slow`  
**Category:** testing  
**Outputs:**
- No tracked outputs on success
- `framework/var/phpunit/phpunit.discovered.xml` *(gitignored; generated runtime artifact)*

**Determinism:**

| Mode / flags | Determinism   | Notes                                                |
|--------------|---------------|------------------------------------------------------|
| default      | deterministic | Alias of framework tests (currently same as `test`). |

**Usage (repo root):**
- `composer test-slow`

---

### Managed composer repositories (apply)

**Id:** `tool.sync_repos_apply`  
**Entrypoint:** `composer sync:repos`  
**Category:** build tooling / workspace policy  
**Outputs:**
- Potential updates to `composer.json`, `framework/composer.json`, `skeleton/composer.json` *(only repositories block; canonicalized)*
- (optional) `framework/var/backups/workspace/**` *(gitignored, may include timestamped names)*

**Determinism:**

| Mode / flags | Determinism   | Notes                                                       |
|--------------|---------------|-------------------------------------------------------------|
| default      | deterministic | Deterministic w.r.t. tracked files; backups are gitignored. |

**Notes:**
- Canonical manager implementation: `php framework/tools/build/sync_composer_repositories.php`.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script sync:repos --`
- Framework implementation detail: `@php tools/build/sync_composer_repositories.php` (framework workspace).
- Failure output policy:
  - unexpected failure: line 1 starts with stable code `CORETSIA_WORKSPACE_SYNC_FAILED`

**Usage (repo root):**
- `composer sync:repos`

---

### Managed composer repositories (check)

**Id:** `tool.sync_repos_check`  
**Entrypoint:** `composer sync:check`  
**Category:** build tooling / guard  
**Outputs:**
- none (exits non-zero on drift)

**Determinism:**

| Mode / flags | Determinism   | Notes                                   |
|--------------|---------------|-----------------------------------------|
| default      | deterministic | MUST be used in CI and pre-commit hook. |

**Notes:**
- Canonical manager implementation: `php framework/tools/build/sync_composer_repositories.php`.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script sync:check --`
- Framework implementation detail: `@php tools/build/sync_composer_repositories.php --check` (framework workspace).
- Failure output policy:
  - invalid managed block: line 1 is stable code `CORETSIA_WORKSPACE_MANAGED_BLOCK_INVALID`
  - managed repository drift: line 1 is stable code `CORETSIA_WORKSPACE_MANAGED_REPOS_OUT_OF_SYNC`
  - unexpected failure: line 1 starts with stable code `CORETSIA_WORKSPACE_SYNC_FAILED`

**Usage (repo root):**
- `composer sync:check`

---

### Lock drift guard

**Id:** `tool.lock_check`  
**Entrypoint:** `composer lock:check`  
**Category:** CI guard  
**Outputs:**
- none (exits non-zero if lockfiles changed)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                     |
|--------------|---------------|-----------------------------------------------------------|
| default      | deterministic | Validates tracked lockfiles are unchanged after installs. |

**Notes:**
- Tracked lockfiles (Prelude workflow invariant):
  - `composer.lock`
  - `framework/composer.lock`
  - `skeleton/composer.lock`

**Usage (repo root):**
- `composer lock:check`

---

### Composer roots validation (strict)

**Id:** `tool.validate_all`  
**Entrypoint:** `composer validate:all`  
**Category:** CI / verification  
**Outputs:**
- none (exits non-zero on validation errors)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                        |
|--------------|---------------|--------------------------------------------------------------|
| default      | deterministic | Pure validation; MUST be rerun-no-diff w.r.t. tracked files. |

**Notes:**
- Implementation detail (NOT an entrypoint; informational only):
  - root variant executes `composer validate --strict`
  - framework/skeleton variants execute `composer --working-dir=<scope> validate --strict`

**Usage (repo root):**
- `composer validate:all`
- `composer validate:root`
- `composer validate:framework`
- `composer validate:skeleton`

---

### New package generator (framework workspace)

**Id:** `tool.new_package`
**Entrypoint:** `composer package:new`
**Category:** build tooling / scaffolding
**Outputs:**
- `framework/packages/<layer>/<slug>/**` (scaffolded package tree; exact contents defined by the generator and packaging law)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                           |
|--------------|---------------|---------------------------------------------------------------------------------|
| default      | deterministic | Deterministic w.r.t. tracked files for the same inputs (`layer`,`slug`,`kind`). |

**Notes:**
- Required args: `--layer=<core|platform|integrations|devtools|enterprise|presets>`, `--slug=<kebab-case>`, `--kind=<library|runtime>`.
- This command is an orchestration entrypoint only.
- Package scaffold completion policy is owned by `composer package-scaffold:sync`.
- After creating the baseline package shell, the generator invokes package scaffold sync for the newly created package path.
- It MUST NOT duplicate canonical scaffold completion policy internally.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script package:new --`
- Framework implementation detail: `@php tools/build/new-package.php` (framework workspace).
- Failure output policy:
  - unexpected failure: line 1 starts with stable code `CORETSIA_NEW_PACKAGE_FAILED`
- Optional: supports `--repo-root` (implementation detail for tooling / fixtures).

**Usage (repo root):**
- `composer package:new -- --layer=core --slug=example --kind=library`
- `composer package:new -- --layer=platform --slug=cli --kind=runtime`

---

### Package scaffold sync

**Id:** `tool.package_scaffold_sync`
**Entrypoint:** `composer package-scaffold:sync`
**Category:** build tooling / scaffolding
**Outputs:**
- Creates or updates package scaffold artifacts under `framework/packages/*/*/**` or under the package path passed as an argument.
- Exact-canonical sync is allowed only for:
  - `LICENSE`
  - `NOTICE`
- Create-if-missing only, without overwriting existing user-owned content:
  - `README.md`
  - `tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - runtime-only scaffold files/directories

**Determinism:**

| Mode / flags                                  | Determinism   | Notes                                                                       |
|-----------------------------------------------|---------------|-----------------------------------------------------------------------------|
| `composer package-scaffold:sync`              | deterministic | Mutating; scans `framework/packages/*/*` and creates/fixes scaffold files.  |
| `composer package-scaffold:sync -- <path>`    | deterministic | Mutating; scans `<path>/packages/*/*` or a direct package path.             |

**Notes:**
- This is the single source of truth for package scaffold completion.
- The command is deterministic but mutating.
- Apply mode MUST be rerun-no-diff for the same repo state after the first successful run.
- It MUST NOT rewrite existing user-owned README/config/code/test content once present.
- Legal file policy:
  - `LICENSE` is synchronized from repo-root `LICENSE`.
  - `NOTICE` is synchronized from repo-root `NOTICE` according to current 1.60.0 scaffold policy.
- Runtime-only scaffold is created only for packages with `composer.json > extra.coretsia.kind = runtime`.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script package-scaffold:sync --`
- Framework implementation detail: `@php tools/build/sync_package_scaffold.php`.
- Failure output policy:
  - unexpected failure: line 1 starts with stable code `CORETSIA_PACKAGE_SCAFFOLD_SYNC_FAILED`.

**Usage (repo root):**
- `composer package-scaffold:sync`
- `composer package-scaffold:sync -- framework`
- `composer package-scaffold:sync -- framework/packages/core/example`

---

### Package scaffold check

**Id:** `tool.package_scaffold_check`
**Entrypoint:** `composer package-scaffold:check`
**Category:** build tooling / guard
**Outputs:**
- none; read-only check
- exits non-zero if scaffold drift is detected

**Determinism:**

| Mode / flags                                  | Determinism   | Notes                                                       |
|-----------------------------------------------|---------------|-------------------------------------------------------------|
| `composer package-scaffold:check`             | deterministic | Read-only; scans `framework/packages/*/*`.                  |
| `composer package-scaffold:check -- <path>`   | deterministic | Read-only; scans `<path>/packages/*/*` or a package path.   |

**Notes:**
- This is the read-only verification mode for package scaffold sync.
- It MUST NOT create, modify, or delete files.
- It fails on missing or drifted canonical legal files.
- It fails on missing create-if-missing scaffold artifacts.
- Output policy:
  - scaffold drift: line 1 is stable code `CORETSIA_PACKAGE_SCAFFOLD_OUT_OF_SYNC`
  - unexpected failure: line 1 starts with stable code `CORETSIA_PACKAGE_SCAFFOLD_SYNC_FAILED`
  - diagnostics use relative paths only and are sorted by `strcmp`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script package-scaffold:check --`
- Framework implementation detail: `@php tools/build/sync_package_scaffold.php --check`.

**Usage (repo root):**
- `composer package-scaffold:check`
- `composer package-scaffold:check -- framework`
- `composer package-scaffold:check -- framework/packages/platform/example`

---

### Package compliance gate

**Id:** `tool.package_compliance_gate`
**Entrypoint:** `composer package-compliance:gate`
**Category:** repo policy / guard
**Outputs:**
- none; read-only gate
- exits non-zero on package compliance violations

**Determinism:**

| Mode / flags                                   | Determinism   | Notes                                      |
|------------------------------------------------|---------------|--------------------------------------------|
| `composer package-compliance:gate`             | deterministic | Read-only; scans `framework/packages/*/*`. |
| `composer package-compliance:gate -- --path=…` | deterministic | Read-only scan override for tests/tools.   |

**Notes:**
- Purpose: enforces canonical package shape and metadata policy.
- The gate is read-only and MUST NOT create, modify, or delete files.
- Scanned scope by default: `framework/packages/*/*`.
- The gate validates:
  - canonical package path shape
  - canonical composer package name mapping
  - required package scaffold artifacts
  - canonical legal files
  - PSR-4 namespace mapping
  - package kind: `library|runtime`
  - runtime metadata and runtime scaffold
  - README minimum sections
  - runtime config shape
- Allowlist policy:
  - `framework/tools/gates/package_compliance_allowlist.php` is the only grandfathering mechanism.
  - allowlist entries are deterministic package ids: `<layer>/<slug>`.
  - allowlist content MUST be sorted by `strcmp`.
- Output policy:
  - compliance violation: line 1 is stable code `CORETSIA_PACKAGE_COMPLIANCE_VIOLATION`
  - unexpected failure: line 1 starts with stable code `CORETSIA_PACKAGE_COMPLIANCE_GATE_FAILED`
  - diagnostics use relative paths only and are sorted by `strcmp`
  - diagnostics MUST NOT include secrets, absolute paths, raw config payloads, headers, tokens, or environment values
- CI/rails policy:
  - `composer gates` MUST execute this gate as part of the baseline/tooling gates aggregate after `composer no-runtime-tooling-artifacts:gate`.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script package-compliance:gate --`
- Framework implementation detail: `@php tools/gates/package_compliance_gate.php`.

**Usage (repo root):**
- `composer package-compliance:gate`
- `composer package-compliance:gate -- --path=framework`
- `composer package-compliance:gate -- --path=framework/tools/tests/Fixtures/package_good`

---

### Git hooks install (workspace)

**Id:** `tool.hooks_install`  
**Entrypoint:** `composer hooks:install`  
**Category:** workspace bootstrap  
**Outputs:**
- (local) Git config: enables hooks via `core.hooksPath=.githooks`

**Determinism:**

| Mode / flags | Determinism   | Notes                                              |
|--------------|---------------|----------------------------------------------------|
| default      | deterministic | Deterministic local git config change (no outputs) |

**Usage (repo root):**
- `composer hooks:install`

---

### Dependencies install (Composer, all roots)

**Id:** `tool.install_all`  
**Entrypoint:** `composer install:all`  
**Category:** dependencies / workspace bootstrap  
**Outputs:**
- `vendor/**` (root) *(untracked)*
- `framework/vendor/**` *(untracked)*
- `skeleton/vendor/**` *(untracked)*
- MUST NOT change tracked lockfiles (see `tool.lock_check`)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                                            |
|--------------|---------------|--------------------------------------------------------------------------------------------------|
| default      | deterministic | Deterministic w.r.t. tracked files; installs from lockfiles. Network I/O is expected (Composer). |

**Notes:**
- Implementation detail (NOT an entrypoint; informational only):
  - variants execute `composer install --prefer-dist` in their respective roots.

**Usage (repo root):**
- `composer install:all`
- `composer install:root`
- `composer install:framework`
- `composer install:skeleton`

---

### Framework test runner (via repo root)

**Id:** `framework.test`  
**Entrypoint:** `composer framework:test`  
**Category:** testing  
**Outputs:**
- No tracked outputs on success
- `framework/var/phpunit/phpunit.discovered.xml` *(gitignored; generated runtime artifact)*

**Determinism:**

| Mode / flags | Determinism   | Notes                                                         |
|--------------|---------------|---------------------------------------------------------------|
| default      | deterministic | Delegates to framework workspace (`--working-dir=framework`). |

**Notes:**
- Repo-root wrapper delegates to framework workspace scripts and **MUST** support args-forwarding (pass-through) via `--`.
- Under the hood (implementation detail):
  - `composer --working-dir=framework run-script test --`
  - `composer --working-dir=framework run-script test-fast --`
  - `composer --working-dir=framework run-script test-slow --`

**Usage (repo root):**
- `composer framework:test`
- `composer framework:test-fast`
- `composer framework:test-slow`
- `composer framework:test -- --filter <pattern>`

---

### Spikes boundary enforcement gate

**Id:** `tool.spike_boundary_gate`  
**Entrypoint:** `composer spike:gate`  
**Category:** repo policy / guard  
**Outputs:**
- none (exits non-zero on boundary violations)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                  |
|--------------|---------------|------------------------------------------------------------------------|
| default      | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines. |

**Notes:**
- Purpose: enforces Phase 0 spikes boundary (no runtime imports, no path-based imports into `framework/packages/**/src/**`).
- Scanned scope (conceptual): `framework/tools/spikes/**` and `framework/tools/gates/**` excluding tests/fixtures.
- Output policy: fixed error code on line 1 + normalized scan-root-relative paths + short reason tokens only.
- CI/rails policy: `composer spike:test` MUST execute this gate first (see `tool.spike_test`).
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script spike:gate --`
  - framework implementation detail: `@php tools/gates/spikes_boundary_gate.php` (framework workspace).

**Usage (repo root):**
- `composer spike:gate`

---

### Spikes I/O policy gate

**Id:** `tool.spike_io_policy_gate`  
**Entrypoint:** `composer spike:io:policy`  
**Category:** repo policy / guard  
**Outputs:**
- none (exits non-zero on policy violations; emits deterministic diagnostics)

**Determinism:**

| Mode / flags                            | Determinism   | Notes                                                                  |
|-----------------------------------------|---------------|------------------------------------------------------------------------|
| `composer spike:io:policy`              | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines. |
| `composer spike:io:policy -- --path=…`  | deterministic | Scan override (scan-only). Intended for contract tests / diagnostics.  |

**Notes:**
- Purpose: forbids **direct file I/O + hashing I/O** micro-implementations under `framework/tools/spikes/**` and detects ad-hoc EOL-normalization patterns (token-based).
  - forbidden call sites include: `file_get_contents`, `file_put_contents`, `fopen`, `fread`, `fwrite`, `stream_get_contents`, `readfile`, `file`, `hash_file`, `md5_file`, `sha1_file`, etc.
  - forbidden EOL normalization heuristic: `str_replace|preg_replace` call arguments contain both `\r` and `\n` string literals.
- Scanned scope (conceptual): `framework/tools/spikes/**` excluding `spikes/_support/**`, `spikes/tests/**`, `spikes/fixtures/**`.
- Output policy: first line is stable `CODE`, next lines are `<scan-root-relative-path>: <reason>` sorted by `strcmp`.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script spike:io:policy --`
  - framework implementation detail: `@php tools/gates/spikes_io_policy_gate.php` (framework workspace).
- Direct call `php framework/tools/gates/spikes_io_policy_gate.php` is **NOT** a canonical entrypoint (implementation detail only).
- CI/rails policy: `composer spike:test` MUST execute this gate before the spikes PHPUnit suite (see `tool.spike_test`).

**Usage (repo root):**
- `composer spike:io:policy`
- `composer spike:io:policy -- --path=framework/tools`

---

### Spikes canonical paths gate

**Id:** `tool.spike_canonical_paths_gate`  
**Entrypoint:** `composer spike:canonical:paths`  
**Category:** repo policy / guard  
**Outputs:**
- none (exits non-zero on canonical path policy violations; emits deterministic diagnostics)

**Determinism:**

| Mode / flags                                   | Determinism   | Notes                                                                 |
|------------------------------------------------|---------------|-----------------------------------------------------------------------|
| `composer spike:canonical:paths`               | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines |
| `composer spike:canonical:paths -- --path=…`   | deterministic | Scan override (scan-only). Intended for contract tests / diagnostics  |

**Notes:**
- Purpose: enforces **canonical naming + required presence** for Phase 0 spikes directory layout.
- Canonical rules (cemented):
  - Tools workspace MUST contain `tools/spikes` directory with canonical lowercase name `spikes`
    (case-variants like `Spikes` are forbidden).
  - Required canonical spike MUST exist: `tools/spikes/config_merge`.
  - For **all directories** under `tools/spikes/**`: directory segment names MUST NOT contain uppercase letters
    (forbids PascalCase / CamelCase / mixed-case path segments).
  - Top-level spike ids `tools/spikes/<spike_id>` (except `_support` and `_artifacts`) MUST be **snake_case**:
    - regex: `^[a-z0-9][a-z0-9_]*$`
- Scanned scope (conceptual): directory tree under `framework/tools/spikes/**` (directories-only; not file content).
- Output policy: first line is stable `CODE`, next lines are `<scan-root-relative-path>: <reason>` sorted by `strcmp`.
  - Reasons are short stable tokens (e.g. `uppercase-dir-segment:<seg>`, `non-canonical-spike-id`, `missing-required-spike`).
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script spike:canonical:paths --`
  - framework implementation detail: `@php tools/gates/spikes_canonical_paths_gate.php` (framework workspace).
- Direct call `php framework/tools/gates/spikes_canonical_paths_gate.php` is **NOT** a canonical entrypoint
  (implementation detail only).
- CI/rails policy: `composer spike:test` MUST execute this gate **after** `composer spike:io:policy` and **before**
  `composer spike:output:gate` (see `tool.spike_test`).

**Usage (repo root):**
- `composer spike:canonical:paths`
- `composer spike:canonical:paths -- --path=framework/tools`

---

### Spikes output bypass gate

**Id:** `tool.spike_output_gate`  
**Entrypoint:** `composer spike:output:gate`  
**Category:** repo policy / guard  
**Outputs:**
- none (exits non-zero if bypass is detected)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                  |
|--------------|---------------|------------------------------------------------------------------------|
| default      | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines. |

**Notes:**
- CI/rails policy: `composer spike:test` MUST execute this gate after `composer spike:canonical:paths` (see `tool.spike_test`).
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script spike:output:gate --`
  - framework implementation detail: `@php tools/gates/spikes_output_gate.php` (framework workspace).

**Usage (repo root):**
- `composer spike:output:gate`

---

### Spikes test suite

**Id:** `tool.spike_test`  
**Entrypoint:** `composer spike:test`  
**Category:** testing  
**Outputs:**
- No tracked outputs on success

**Determinism:**

| Mode / flags | Determinism   | Notes                                       |
|--------------|---------------|---------------------------------------------|
| default      | deterministic | MUST be rerun-no-diff w.r.t. tracked files. |

**Notes:**
- This is the canonical Phase 0 spikes rails chain.
- Execution order is cemented (MUST be first steps before tests):
  1) `composer spike:gate`
  2) `composer toolkit:gate`
  3) `composer tools:ia`
  4) `composer spike:io:policy`
  5) `composer spike:canonical:paths`
  6) `composer repo:text:gate`
  7) `composer spike:output:gate`
  8) Spikes PHPUnit suite (`tools/spikes/phpunit.spikes.xml`)
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script spike:test --`
  - framework implementation detail: framework script executes:
    - `@spike:gate`
    - `@toolkit:gate`
    - `@tools:ia`
    - `@spike:io:policy`
    - `@spike:canonical:paths`
    - `@repo:text:gate`
    - `@spike:output:gate`
    - `vendor/bin/phpunit -c tools/spikes/phpunit.spikes.xml --do-not-cache-result`

**Usage (repo root):**
- `composer spike:test`

---

### Spikes determinism runner

**Id:** `tool.spike_test_determinism`  
**Entrypoint:** `composer spike:test:determinism`  
**Category:** determinism / verification  
**Outputs:**
- No tracked outputs on success *(may create temporary files under OS temp or gitignored paths)*

**Determinism:**

| Mode / flags | Determinism   | Notes                                                             |
|--------------|---------------|-------------------------------------------------------------------|
| default      | deterministic | Determinism verifier; MUST be rerun-no-diff w.r.t. tracked files. |

**Notes:**
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script spike:test:determinism --`
  - framework implementation detail: `@php tools/spikes/_support/DeterminismRunner.php` (framework workspace).

**Usage (repo root):**
- `composer spike:test:determinism`

---

### Repo text normalization gate

**Id:** `tool.repo_text_normalization_gate`
**Entrypoint:** `composer repo:text:gate`
**Category:** repo policy / guard
**Outputs:**
- none (exits non-zero on violations; emits deterministic diagnostics)

**Determinism:**

| Mode / flags                            | Determinism   | Notes                                                                  |
|-----------------------------------------|---------------|------------------------------------------------------------------------|
| `composer repo:text:gate`               | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines. |
| `composer repo:text:gate -- --path=...` | deterministic | Scans an override root; MUST resolve inside repo root.                 |

**Notes:**
- Default scan root: repo root.
- Optional override: `--path=<path>` where `<path>` is repo-relative or absolute; it MUST resolve inside repo root.
- Output policy: must be safe (no absolute paths / secret leaks); diagnostics are expected to be stable and minimal.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script repo:text:gate --`
- Framework implementation detail: `@php tools/gates/repo_text_normalization_gate.php` (framework workspace).
- Direct call `php framework/tools/gates/repo_text_normalization_gate.php` is **NOT** a canonical entrypoint (implementation detail only).

**Usage (repo root):**
- `composer repo:text:gate`
- `composer repo:text:gate -- --path=framework`

---

### Package-local PHPUnit config gate

**Id:** `tool.package_phpunit_gate`
**Entrypoint:** `composer package:phpunit:gate`
**Category:** repo policy / guard
**Outputs:**
- none (exits non-zero on violations)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                                            |
|--------------|---------------|--------------------------------------------------------------------------------------------------|
| default      | deterministic | Deterministic scan of `framework/packages/*/*` for forbidden package-local PHPUnit config files. |

**Notes:**
- Purpose: forbids package-local PHPUnit config files under framework packages.
- Forbidden files:
  - `framework/packages/*/*/phpunit.xml`
  - `framework/packages/*/*/phpunit.dist.xml`
- Canonical policy:
  - package-local PHPUnit config files are forbidden
  - canonical framework/packages PHPUnit source of truth is `framework/tools/testing/phpunit.xml`
  - runtime artifact is `framework/var/phpunit/phpunit.discovered.xml`
- Output policy:
  - first line is stable code: `CORETSIA_PACKAGE_PHPUNIT_CONFIG_FORBIDDEN`
  - next lines are framework-root-relative violating paths sorted by `strcmp`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script package:phpunit:gate --`
  - framework implementation detail: `@php tools/gates/package_phpunit_config_gate.php`
- `composer test` MUST execute this gate before the framework package PHPUnit runner.

**Usage (repo root):**
- `composer package:phpunit:gate`

---

### Outdated dependencies report (Composer, all roots)

**Id:** `tool.outdated_all`  
**Entrypoint:** `composer outdated:all`  
**Category:** dependencies / inspection  
**Outputs:**
- none (prints report to console; MUST NOT modify tracked files)

**Determinism:**

| Mode / flags | Determinism      | Notes                                                             |
|--------------|------------------|-------------------------------------------------------------------|
| default      | NONDETERMINISTIC | Output depends on remote repositories and time (latest versions). |

**Notes:**
- Implementation detail (NOT an entrypoint; informational only):
  - these scripts execute `composer outdated` in the selected root (`--working-dir=...` where applicable).
- MUST NOT be used in CI rails as a stability gate (result varies over time).

**Usage (repo root):**
- `composer outdated:all`
- `composer outdated:root`
- `composer outdated:framework`
- `composer outdated:skeleton`

---

### Autoloader dump (Composer, all roots)

**Id:** `tool.dump_autoload_all`  
**Entrypoint:** `composer dump-autoload:all`  
**Category:** dependencies / tooling  
**Outputs:**
- `vendor/**` (root) *(untracked)*
- `framework/vendor/**` *(untracked)*
- `skeleton/vendor/**` *(untracked)*

**Determinism:**

| Mode / flags | Determinism   | Notes                                                            |
|--------------|---------------|------------------------------------------------------------------|
| default      | deterministic | Deterministic w.r.t. tracked files; affects only vendor outputs. |

**Notes:**
- Implementation detail (NOT an entrypoint; informational only):
  - these scripts execute `composer dump-autoload -o` in the selected root (`--working-dir=...` where applicable).

**Usage (repo root):**
- `composer dump-autoload:all`
- `composer dump-autoload:root`
- `composer dump-autoload:framework`
- `composer dump-autoload:skeleton`

---

### Dependencies update (Composer, all roots)

**Id:** `tool.update_all`  
**Entrypoint:** `composer update:all`  
**Category:** dependencies / maintenance  
**Outputs:**
- Updates lockfiles (tracked):
  - `composer.lock`
  - `framework/composer.lock`
  - `skeleton/composer.lock`
- Updates vendors (untracked):
  - `vendor/**`
  - `framework/vendor/**`
  - `skeleton/vendor/**`

**Determinism:**

| Mode / flags | Determinism      | Notes                                                              |
|--------------|------------------|--------------------------------------------------------------------|
| default      | NONDETERMINISTIC | Depends on remote repos/time; changes tracked lockfiles by design. |

**Notes:**
- Implementation detail (NOT an entrypoint; informational only):
  - these scripts execute `composer update` in the selected root (`--working-dir=...` where applicable).
- MUST NOT be used in CI rails; it is a maintenance operation that intentionally changes tracked files.

**Usage (repo root):**
- `composer update:all`
- `composer update:root`
- `composer update:framework`
- `composer update:skeleton`

---

### Internal toolkit anti-duplication gate

**Id:** `tool.toolkit_gate`  
**Entrypoint:** `composer toolkit:gate`  
**Category:** repo policy / guard  
**Outputs:**
- none (exits non-zero on violations; emits deterministic code + minimal diagnostics)

**Determinism:**

| Mode / flags                           | Determinism   | Notes                                                                 |
|----------------------------------------|---------------|-----------------------------------------------------------------------|
| `composer toolkit:gate`                | deterministic | Deterministic scan of the default tools subtree.                      |
| `composer toolkit:gate -- --path=...`  | deterministic | Scan override (scan-only). Intended for contract tests / diagnostics. |

**Notes:**
- Purpose: enforces **symbol-ownership** for determinism helpers and forbids direct `json_encode(...)` under the scanned tools subtree.
  - Forbidden duplicated function/method names: `toStudly`, `toSnake`, `normalizeRelative`, `encodeStable`
  - Forbidden function calls: `json_encode(...)` (including qualified `\json_encode(...)`)
- Default scan root: **tools root** (`framework/tools`), derived from the gate file location (runtime tools root).
- Exclusions (matched against scan-root-relative paths): `**/tests/**`, `**/fixtures/**`.
- Thin wrapper exception (allowlisted by path): `spikes/*/StableJsonEncoder.php`:
  - MAY declare `encodeStable`, but MUST delegate to `\Coretsia\Devtools\InternalToolkit\Json::encodeStable(...)`
  - MUST NOT call `json_encode` directly
- Output policy: line 1 is a stable error `CODE`, next lines are `<scan-root-relative-path>: <symbol>` sorted by path (`strcmp`).
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script toolkit:gate --`
  - framework implementation detail: `@php tools/gates/internal_toolkit_no_dup_gate.php`
- CI/rails policy: `composer spike:test` MUST execute this gate **between** `composer spike:gate` and `composer spike:output:gate`
  (see `tool.spike_test`).

**Usage (repo root):**
- `composer toolkit:gate`
- `composer toolkit:gate -- --path=/absolute/path/to/temp-scan-root`

---

### Tools InvalidArgumentException policy gate

**Id:** `tool.tools_invalid_argument_exception_gate`  
**Entrypoint:** `composer tools:ia`  
**Category:** repo policy / guard  
**Outputs:**
- none (exits non-zero on violations; emits deterministic diagnostics)

**Determinism:**

| Mode / flags                    | Determinism   | Notes                                                                  |
|---------------------------------|---------------|------------------------------------------------------------------------|
| `composer tools:ia`             | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines. |
| `composer tools:ia -- --path=…` | deterministic | Scan override (scan-only). Intended for contract tests / diagnostics.  |

**Notes:**
- Purpose: forbids **direct** `throw new InvalidArgumentException(...)` under `framework/tools/**`,
  except explicit allowlist (contracted exceptions for developer errors):
  - `build/sync_composer_repositories.php`
  - `spikes/_support/DeterministicException.php`
- Scanned scope (conceptual): `framework/tools/**` excluding `**/tests/**` and `**/fixtures/**`.
- Output policy: first line is stable `CODE`, next lines are `<tools-root-relative-path>: throw-new-InvalidArgumentException`
  sorted by `strcmp`.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script tools:ia --`
  - framework implementation detail: `@php tools/gates/tools_invalid_argument_exception_gate.php` (framework workspace).
- CI/rails policy: `composer spike:test` MUST execute this gate between `composer toolkit:gate` and `composer spike:io:policy`
  (see `tool.spike_test`).

**Usage (repo root):**
- `composer tools:ia`
- `composer tools:ia -- --path=framework/tools`

---

### Coretsia CLI help (Phase 0)

**Id:** `cli.help`
**Entrypoint:** `php coretsia help`
**Category:** CLI / built-in (Phase 0)
**Outputs:**
- none (prints deterministic help text to console)

**Determinism:**

| Mode / flags                  | Determinism   | Notes                                                            |
|-------------------------------|---------------|------------------------------------------------------------------|
| `php coretsia help`           | deterministic | General usage + available commands list                          |
| `php coretsia help <command>` | deterministic | Built-in help for `help`/`list`; generic help for known commands |

**Notes:**
- Purpose: provide **kernel-free** deterministic help for Phase 0 CLI runtime.
- Known-subject policy:
  - `help help` and `help list` print **built-in** detailed help.
  - `help <known-command>` prints a **generic** “no detailed help in Phase 0” message.
  - `help <unknown-command>` fails deterministically:
    - emits `OutputInterface::error(CORETSIA_CLI_COMMAND_INVALID, unknown-command)`
    - exits non-zero.
- Alias/compat (implementation detail): if repo uses launcher at `framework/bin/coretsia`, the equivalent call is `php framework/bin/coretsia help …` (MUST be behavior-equivalent in deterministic mode).

**Usage (repo root):**
- `php coretsia help`
- `php coretsia help help`
- `php coretsia help list`
- `php coretsia help <command>`

---

### Coretsia CLI list (Phase 0)

**Id:** `cli.list`
**Entrypoint:** `php coretsia list`
**Category:** CLI / built-in (Phase 0)
**Outputs:**
- none (prints deterministic list of available commands)

**Determinism:**

| Mode / flags        | Determinism   | Notes                                         |
|---------------------|---------------|-----------------------------------------------|
| `php coretsia list` | deterministic | Prints built-ins + injected registry commands |

**Notes:**
- Prints a deterministic catalog:
  - built-ins: `help`, `list`
  - plus configured/injected registry commands (as resolved by `Application`).
- Phase 0 parsing semantics: extra tokens are **ignored** (command is tolerant; parsing is Application concern).
- Output MUST be produced via `OutputInterface` only (no direct stdout/stderr in command code).
- Alias/compat (implementation detail): `php framework/bin/coretsia list` MAY exist and MUST be behavior-equivalent in deterministic mode.

**Usage (repo root):**
- `php coretsia list`

---

### Coretsia CLI doctor (Phase 0)

**Id:** `cli.doctor`
**Entrypoint:** `php coretsia doctor`
**Category:** CLI / devtools / diagnostics (Phase 0)
**Outputs:**
- none (prints deterministic JSON payload to console)

**Determinism:**

| Mode / flags          | Determinism   | Notes                                        |
|-----------------------|---------------|----------------------------------------------|
| `php coretsia doctor` | deterministic | Emits safe repo-relative diagnostics payload |

**Notes:**
- Purpose: validates that the CLI spikes bootstrap path can be resolved from the current launcher context and exposes a minimal safe diagnostics payload.
- Success payload shape is deterministic and includes:
  - `command: "doctor"`
  - `ok: true`
  - `paths.framework_root`
  - `paths.repo_root: "."`
  - `paths.spikes_bootstrap`
  - `paths.spikes_fixtures_root`
- All reported paths MUST be repo-relative / safe-display paths; absolute paths MUST NOT be emitted.
- Failure policy:
  - bootstrap/path failures emit `OutputInterface::error(...)`
  - exit code is non-zero
  - failure reasons are stable short tokens (for example `launcher-path-unresolvable`).
- Alias/compat (implementation detail): `php framework/bin/coretsia doctor` MAY exist and MUST be behavior-equivalent in deterministic mode.

**Usage (repo root):**
- `php coretsia doctor`

---

### Coretsia CLI spike fingerprint (Phase 0)

**Id:** `cli.spike_fingerprint`
**Entrypoint:** `php coretsia spike:fingerprint`
**Category:** CLI / devtools / diagnostics (Phase 0)
**Outputs:**
- none (prints deterministic JSON payload to console)

**Determinism:**

| Mode / flags                     | Determinism   | Notes                                                   |
|----------------------------------|---------------|---------------------------------------------------------|
| `php coretsia spike:fingerprint` | deterministic | Emits safe fingerprint payload from tools-only workflow |

**Notes:**
- Purpose: dispatches to the tools-side fingerprint workflow and returns a deterministic repo-state fingerprint summary.
- Success payload shape is deterministic and includes:
  - `command: "spike:fingerprint"`
  - `ok: true`
  - `fixture_repo_root`
  - `fingerprint` *(sha256 hex)*
  - `bucket_digests` *(map of sha256 hex digests)*
- Path safety policy:
  - command MUST dispatch through `SpikesBootstrap`
  - payload MUST NOT leak absolute paths
  - `fixture_repo_root` is emitted via safe display-path conversion.
- Secret safety policy:
  - payload MUST NOT expose dotenv values / secrets
  - only digest-level information is allowed.
- Failure policy:
  - bootstrap failures emit CLI base failure codes
  - deterministic tool failures are forwarded via their deterministic error code/message
  - uncaught failures emit a stable CLI failure token.
- Alias/compat (implementation detail): `php framework/bin/coretsia spike:fingerprint` MAY exist and MUST be behavior-equivalent in deterministic mode.

**Usage (repo root):**
- `php coretsia spike:fingerprint`

---

### Coretsia CLI spike config debug (Phase 0)

**Id:** `cli.spike_config_debug`
**Entrypoint:** `php coretsia spike:config:debug`
**Category:** CLI / devtools / config diagnostics (Phase 0)
**Outputs:**
- none (prints deterministic JSON payload to console)

**Determinism:**

| Mode / flags                                                               | Determinism   | Notes                          |
|----------------------------------------------------------------------------|---------------|--------------------------------|
| `php coretsia spike:config:debug --key=<dot.key>`                          | deterministic | Uses cemented default scenario |
| `php coretsia spike:config:debug --key=<dot.key> --scenario=<scenario-id>` | deterministic | Uses explicit scenario id      |

**Notes:**
- Purpose: dispatches to the tools-side config debug workflow and returns a deterministic **safe projection** for a requested dot-key.
- Required flag:
  - `--key=<dot.key>` or `--key <dot.key>`
- Optional flag:
  - `--scenario=<scenario-id>` or `--scenario <scenario-id>`
- Default scenario is cemented:
  - `baseline.defaults_only.all_middleware_slots_present`
- Success payload shape is deterministic and includes:
  - `schema_version`
  - `command: "spike:config:debug"`
  - `scenario`
  - `key`
  - `resolved`
  - `trace`
- Payload semantics:
  - payload is **workflow-owned safe projection**
  - requested key **MAY be redacted**
  - `trace` **MAY be empty**
  - command guarantees deterministic safe output, **not** raw debug internals
- Documentation MUST NOT imply that success payload always contains:
  - the original requested key verbatim
  - a non-empty trace
- Validation/failure policy:
  - missing `--key` => `CORETSIA_CLI_COMMAND_INVALID` + `missing-required-flag-key`
  - invalid scenario id syntax => `CORETSIA_CLI_COMMAND_INVALID` + `scenario-id-invalid`
  - some fixture/scenario-loading failures are normalized to `CORETSIA_CLI_CONFIG_INVALID`
  - other deterministic tool failures are forwarded as-is
- Output MUST be safe and deterministic; absolute paths / secret values MUST NOT be emitted.
- Alias/compat (implementation detail): `php framework/bin/coretsia spike:config:debug ...` MAY exist and MUST be behavior-equivalent in deterministic mode.

**Usage (repo root):**
- `php coretsia spike:config:debug --key=cli.commands`
- `php coretsia spike:config:debug --key=cli.commands --scenario=baseline.defaults_only.all_middleware_slots_present`

---

### Coretsia CLI deptrac graph (Phase 0)

**Id:** `cli.deptrac_graph`
**Entrypoint:** `php coretsia deptrac:graph`
**Category:** CLI / devtools / artifact generator (Phase 0)
**Outputs:**
- default output directory: `framework/tools/spikes/_artifacts/deptrac_graph`
- generated files in that directory:
  - `deptrac_graph.dot`
  - `deptrac_graph.svg`
  - `deptrac_graph.html`

**Determinism:**

| Mode / flags                                                                                                        | Determinism   | Notes                                     |
|---------------------------------------------------------------------------------------------------------------------|---------------|-------------------------------------------|
| `php coretsia deptrac:graph`                                                                                        | deterministic | Uses default fixture + default output dir |
| `php coretsia deptrac:graph --json`                                                                                 | deterministic | Emits JSON payload instead of text        |
| `php coretsia deptrac:graph --fixture=deptrac_min/package_index_ok.php --out=framework/tools/spikes/_artifacts/...` | deterministic | Explicit deterministic inputs             |
| `php coretsia deptrac:graph --help`                                                                                 | deterministic | Prints built-in command help text         |

**Notes:**
- Purpose: dispatches to the tools-side deptrac graph workflow and materializes graph artifacts into a repo-relative output directory.
- Default fixture:
  - `deptrac_min/package_index_ok.php`
- Default output dir:
  - `framework/tools/spikes/_artifacts/deptrac_graph`
- Flags:
  - `--fixture=<relative-path>`
  - `--out=<repo-relative-path>`
  - `--json`
  - `--help` / `-h`
- Validation policy:
  - fixture MUST be under `deptrac_min/`
  - output directory MUST be repo-relative
  - absolute paths and parent traversal MUST be rejected.
- Success behavior:
  - text mode prints summary lines + files list
  - JSON mode emits:
    - `ok: true`
    - `command: "deptrac:graph"`
    - `fixture`
    - `output_dir`
    - `files`
- Failure policy:
  - invalid fixture => `CORETSIA_CLI_COMMAND_INVALID` + `fixture-must-be-under-deptrac-min`
  - invalid output path => `CORETSIA_CLI_COMMAND_INVALID` + `output-relpath-invalid`
  - workflow/bootstrap/runtime failures emit stable deterministic failure tokens.
- Alias/compat (implementation detail): `php framework/bin/coretsia deptrac:graph ...` MAY exist and MUST be behavior-equivalent in deterministic mode.

**Usage (repo root):**
- `php coretsia deptrac:graph`
- `php coretsia deptrac:graph --json`
- `php coretsia deptrac:graph --fixture=deptrac_min/package_index_ok.php --out=framework/tools/spikes/_artifacts/deptrac_graph --json`
- `php coretsia deptrac:graph --help`

---

### Coretsia CLI workspace sync dry-run (Phase 0)

**Id:** `cli.workspace_sync_dry_run`
**Entrypoint:** `php coretsia workspace:sync --dry-run`
**Category:** CLI / devtools / workspace tooling (Phase 0)
**Outputs:**
- none in dry-run mode (prints deterministic JSON or text payload to console)

**Determinism:**

| Mode / flags                                                                   | Determinism   | Notes                                   |
|--------------------------------------------------------------------------------|---------------|-----------------------------------------|
| `php coretsia workspace:sync --dry-run`                                        | deterministic | Default format is JSON                  |
| `php coretsia workspace:sync --dry-run --format=json`                          | deterministic | Emits structured JSON payload           |
| `php coretsia workspace:sync --dry-run --format=text`                          | deterministic | Emits text summary                      |
| `php coretsia workspace:sync --dry-run --fixture=<fixture-name> --format=json` | deterministic | Runs against selected fixture workspace |

**Notes:**
- Purpose: dispatches to the tools-side workspace sync entry workflow in **dry-run** mode.
- Supported flags:
  - `--format=json|text`
  - `--fixture=<fixture-name>`
  - `--json` *(alias for JSON format)*
  - `--text` *(alias for text format)*
- Target resolution:
  - no fixture => target type is `repo`
  - fixture provided => target type is `fixture`, resolved under `framework/tools/spikes/fixtures/<fixture-name>`
- Success JSON payload includes:
  - `command: "workspace:sync"`
  - `mode: "dry-run"`
  - `target`
  - `changedPaths`
  - `files`
  - `packageIndex`
- Success text mode prints:
  - command header
  - target summary
  - `changedPaths` list (or `(none)`)
- Validation/failure policy:
  - invalid format / invalid fixture token => `CORETSIA_CLI_COMMAND_INVALID` + `invalid-arguments`
  - missing/invalid fixture directory => `CORETSIA_CLI_COMMAND_INVALID` + `fixture-invalid`
  - repo/bootstrap/workflow failures emit stable CLI failure tokens
- Dry-run mode MUST NOT mutate tracked workspace files.
- Current fixture tree includes a valid workspace example:
  - `workspace_min`
- Alias/compat (implementation detail): `php framework/bin/coretsia workspace:sync --dry-run ...` MAY exist and MUST be behavior-equivalent in deterministic mode.

**Usage (repo root):**
- `php coretsia workspace:sync --dry-run`
- `php coretsia workspace:sync --dry-run --format=json`
- `php coretsia workspace:sync --dry-run --format=text`
- `php coretsia workspace:sync --dry-run --fixture=workspace_min --format=json`

---

### Coretsia CLI workspace sync apply (Phase 0)

**Id:** `cli.workspace_sync_apply`
**Entrypoint:** `php coretsia workspace:sync --apply`
**Category:** CLI / devtools / workspace tooling (Phase 0)
**Outputs:**
- workspace-managed files as determined by the tools-side workflow
- structured JSON payload describing applied result

**Determinism:**

| Mode / flags                          | Determinism   | Notes                                                       |
|---------------------------------------|---------------|-------------------------------------------------------------|
| `php coretsia workspace:sync --apply` | deterministic | Deterministic w.r.t. the current repo/workspace input state |

**Notes:**
- Purpose: dispatches to the tools-side workspace sync entry workflow in **apply** mode.
- Success payload includes:
  - `command: "workspace:sync"`
  - `mode: "apply"`
  - `result`
- `result` is workflow-owned structured output and is expected to describe the applied workspace changes.
- Validation/failure policy:
  - unresolved / invalid repo root => stable CLI failure token
  - missing workflow class => stable CLI failure token
  - invalid workflow result => stable CLI failure token
  - deterministic tool failures are forwarded as-is.
- Unlike dry-run mode, apply mode MAY modify workspace-managed files according to workflow rules.
- Command output itself MUST remain safe:
  - no absolute paths
  - no secret markers
  - no direct stdout/stderr bypass in command code.
- Alias/compat (implementation detail): `php framework/bin/coretsia workspace:sync --apply` MAY exist and MUST be behavior-equivalent in deterministic mode.

**Usage (repo root):**
- `php coretsia workspace:sync --apply`

---

### Site icons builder

**Id:** `tool.build_icons`
**Entrypoint:** `composer build:icons`
**Category:** documentation / branding / asset generator
**Outputs:**
- `docs/assets/branding/favicon/favicon-16x16.png`
- `docs/assets/branding/favicon/favicon-32x32.png`
- `docs/assets/branding/favicon/apple-touch-icon.png`
- `docs/assets/branding/favicon/android-chrome-192x192.png`
- `docs/assets/branding/favicon/android-chrome-512x512.png`
- `docs/assets/branding/favicon/favicon.ico`

**Determinism:**

| Mode / flags                      | Determinism   | Notes                                                      |
|-----------------------------------|---------------|------------------------------------------------------------|
| `composer build:icons`            | deterministic | Materializes canonical icon artifacts in branding output   |
| `composer build:icons -- --check` | deterministic | Pure check; exits non-zero if generated artifacts drift    |

**Notes:**
- Purpose: renders canonical favicon / app-icon artifacts for documentation / branding outputs.
- Canonical source assets:
  - required: `docs/assets/branding/favicon/favicon.svg`
  - optional: `docs/assets/branding/favicon/apple-touch-icon.svg`
- Source semantics:
  - `favicon.svg` is the canonical source for favicon / android outputs
  - `apple-touch-icon.svg`, if present, is the canonical source for `apple-touch-icon.png`
  - if `apple-touch-icon.svg` is absent, the builder falls back to `favicon.svg`
- ICO semantics:
  - `favicon.ico` is built from the canonical generated PNG layers
  - deterministic output is required for the same input assets / toolchain state
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script build:icons --`
- Framework implementation detail: `@php tools/build/build_icons.php` (framework workspace).
- Failure output policy:
  - drift in `--check` mode: line 1 is stable code `CORETSIA_BUILD_ICONS_OUT_OF_DATE`
  - unexpected failure: line 1 starts with stable code `CORETSIA_BUILD_ICONS_FAILED`
- Direct call `php framework/tools/build/build_icons.php` is **NOT** a canonical entrypoint (implementation detail only).
- Current workspace platform requirement includes `ext-imagick` in `framework/composer.json`; alternative renderer support, if present in the tool implementation, does **NOT** change the documented workspace requirement.

**Usage (repo root):**
- `composer build:icons`
- `composer build:icons -- --check`

---

### Tooling gates rail

**Id:** `tool.gates`
**Entrypoint:** `composer gates`
**Category:** CI / repo policy / guard rail
**Outputs:**
- none (exits non-zero if any configured gate fails)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                |
|--------------|---------------|----------------------------------------------------------------------|
| default      | deterministic | Executes the canonical aggregate tooling gates rail in stable order. |

**Notes:**
- Purpose: executes the canonical aggregate gates rail for baseline/tooling/public-API/package-compliance enforcement.
- This command is the preferred CI entrypoint for gates owned by tooling epics after Phase 0.
- Individual `*:gate` scripts remain separately invokable and are the canonical unit entrypoints.
- `composer gates` is the canonical aggregate rail entrypoint.
- Execution order is cemented:
  1) `composer no-skeleton-http-default:gate`
  2) `composer no-skeleton-bundles-default:gate`
  3) `composer no-skeleton-mode-presets-default:gate`
  4) `composer no-skeleton-modules-default:gate`
  5) `composer contracts-only-ports:gate`
  6) `composer tag-constant-mirror:gate`
  7) `composer observability-naming:gate`
  8) `composer artifact-header-schema:gate`
  9) `composer cross-cutting-contract:gate`
  10) `composer kernel-public-api:gate`
  11) `composer no-runtime-tooling-artifacts:gate`
  12) `composer package-compliance:gate`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script gates --`
  - framework implementation detail: aggregate `gates` script in `framework/composer.json`
- Policy:
  - order of invoked gates inside the aggregate rail MUST be deterministic
  - aggregate rail MUST invoke named composer `*:gate` scripts, not raw `php tools/gates/*.php` paths
  - runtime tooling artifact purity MUST run before package compliance in this aggregate rail
  - package compliance MUST run after baseline public-API/runtime-purity/policy gates in this aggregate rail
  - spikes rails are separate and MUST NOT be silently folded into this aggregate command
  - this rail SHOULD run before `composer quality` and `composer test` in CI

**Usage (repo root):**
- `composer gates`

---

### DTO aggregate gate rail

**Id:** `tool.dto_gate`
**Entrypoint:** `composer dto:gate`
**Category:** CI / repo policy / DTO guard rail
**Outputs:**
- none on success
- exits non-zero if a materialized specialized DTO gate fails
- forwards the first failing specialized DTO gate output unchanged
- emits deterministic aggregate diagnostics only if the aggregate runner itself fails before a sub-gate can produce canonical diagnostics

**Determinism:**

| Mode / flags        | Determinism   | Notes                                                                     |
|---------------------|---------------|---------------------------------------------------------------------------|
| `composer dto:gate` | deterministic | Executes materialized DTO specialized gates in fixed deterministic order. |

**Notes:**
- Purpose: executes the canonical aggregate DTO policy rail.
- DTO policy is explicit opt-in only:
  - only classes marked with `#[Coretsia\Dto\Attribute\Dto]` are in DTO gate scope
  - unmarked classes are outside DTO gate scope
- This command is the aggregate DTO rail entrypoint.
- Specialized DTO gates are materialized incrementally and are also available as standalone canonical unit entrypoints.
- All listed specialized DTO gates are required. A missing or unreadable listed sub-gate is treated as an aggregate orchestration failure with `CORETSIA_DTO_GATE_FAILED`.
- Execution order is cemented:
  1) DTO marker consistency gate: `composer dto-marker-consistency:gate`
  2) DTO no-logic gate: `composer dto-no-logic:gate`
  3) DTO shape gate: `composer dto-shape:gate`
- Materialized specialized gate entrypoints:
  - `composer dto-marker-consistency:gate`
  - `composer dto-no-logic:gate`
  - `composer dto-shape:gate`
- Future specialized DTO gates, once materialized, MUST also have their own repo-root `composer <name>:gate` wrapper and framework workspace script.
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
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script dto:gate --`
  - framework implementation detail: `@php tools/gates/dto_gate.php`
- Framework aggregate implementation detail:
  - `framework/tools/gates/dto_marker_consistency_gate.php`
  - `framework/tools/gates/dto_no_logic_gate.php`
  - `framework/tools/gates/dto_shape_gate.php`
- Direct call `php framework/tools/gates/dto_gate.php` is **NOT** a canonical entrypoint (implementation detail only).
- CI/rails policy:
  - this command SHOULD run in the dedicated `gates` CI job after `composer gates`
  - this command SHOULD run before architecture checks, quality checks, and tests
  - this command SHOULD be included in root `composer ci`

**Usage (repo root):**
- `composer dto:gate`

---

### DTO marker consistency gate

**Id:** `tool.dto_marker_consistency_gate`
**Entrypoint:** `composer dto-marker-consistency:gate`
**Category:** repo policy / DTO guard
**Outputs:**
- none on success
- exits non-zero on DTO marker policy violations
- emits deterministic diagnostics only through the canonical tooling output policy

**Determinism:**

| Mode / flags                                      | Determinism   | Notes                                                                 |
|---------------------------------------------------|---------------|-----------------------------------------------------------------------|
| `composer dto-marker-consistency:gate`            | deterministic | Scans the framework package source tree using the default scan root.  |
| `composer dto-marker-consistency:gate -- --path=` | deterministic | Overrides scan root only; bootstrap/runtime root remains tools-owned. |

**Notes:**
- Purpose: enforces the canonical DTO marker strategy for Phase 1.
- Canonical marker:
  - `Coretsia\Dto\Attribute\Dto`
  - package: `coretsia/core-dto-attribute`
  - declaration path: `framework/packages/core/dto-attribute/src/Attribute/Dto.php`
- Policy:
  - only `Coretsia\Dto\Attribute\Dto` is the canonical DTO marker
  - alias imports are allowed only when they resolve to `Coretsia\Dto\Attribute\Dto`
  - interface markers such as `DtoInterface` are forbidden
  - local/custom DTO marker attributes are forbidden
  - simultaneous marker strategies are forbidden
  - classes without the canonical marker are outside DTO gate scope
  - the canonical marker declaration itself MUST NOT be reported as a custom marker
- Default scan scope:
  - `framework/packages/**/src/**/*.php`
- Excluded paths:
  - `**/tests/**`
  - `**/fixtures/**`
  - `**/vendor/**`
- `--path=<dir>` policy:
  - overrides only the scan root
  - does not affect bootstrap discovery
  - bootstrap is always loaded from the runtime tools root:
    - `framework/tools/spikes/_support/bootstrap.php`
  - intended for integration tests and local focused checks
- Output policy:
  - marker violations: line 1 is stable code `CORETSIA_DTO_MARKER_VIOLATION`
  - scan/bootstrap/internal failure: line 1 is stable code `CORETSIA_DTO_GATE_SCAN_FAILED`
  - line 2+ diagnostics use scan-root-relative normalized paths and fixed reason tokens
  - diagnostics are sorted by `strcmp`
  - if no violations exist, command exits 0 and prints nothing
- Fixed reason tokens:
  - `non-canonical-dto-marker`
  - `legacy-dto-interface-marker`
  - `custom-dto-marker-class`
  - `multiple-dto-marker-strategies`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script dto-marker-consistency:gate --`
  - framework implementation detail: `@php tools/gates/dto_marker_consistency_gate.php`
- Direct call `php framework/tools/gates/dto_marker_consistency_gate.php` is **NOT** a canonical entrypoint (implementation detail only).
- Aggregate rail integration:
  - `composer dto:gate` invokes this gate as the first materialized DTO sub-gate
  - failing output MUST pass through the aggregate runner unchanged

**Usage (repo root):**
- `composer dto-marker-consistency:gate`
- `composer dto-marker-consistency:gate -- --path=framework`

---

### DTO no-logic gate

**Id:** `tool.dto_no_logic_gate`
**Entrypoint:** `composer dto-no-logic:gate`
**Category:** repo policy / DTO guard
**Outputs:**
- none on success
- exits non-zero on DTO no-logic policy violations
- emits deterministic diagnostics only through the canonical tooling output policy

**Determinism:**

| Mode / flags                              | Determinism   | Notes                                                                 |
|-------------------------------------------|---------------|-----------------------------------------------------------------------|
| `composer dto-no-logic:gate`              | deterministic | Scans the framework package source tree using the default scan root.  |
| `composer dto-no-logic:gate -- --path=`   | deterministic | Overrides scan root only; bootstrap/runtime root remains tools-owned. |

**Notes:**
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
  - `framework/packages/**/src/**/*.php`
- Excluded paths:
  - `**/tests/**`
  - `**/fixtures/**`
  - `**/vendor/**`
- `--path=<dir>` policy:
  - overrides only the scan root
  - does not affect bootstrap discovery
  - bootstrap is always loaded from the runtime tools root:
    - `framework/tools/spikes/_support/bootstrap.php`
  - intended for integration tests and local focused checks
- Output policy:
  - no-logic violations: line 1 is stable code `CORETSIA_DTO_NO_LOGIC_VIOLATION`
  - scan/bootstrap/internal failure: line 1 is stable code `CORETSIA_DTO_GATE_SCAN_FAILED`
  - line 2+ diagnostics use scan-root-relative normalized paths and fixed reason tokens
  - diagnostics are sorted by `strcmp`
  - if no violations exist, command exits 0 and prints nothing
  - diagnostics MUST NOT leak class contents, property values, constructor body text, or method bodies
- Fixed reason tokens:
  - `disallowed-method`
  - `constructor-calls-function`
  - `constructor-calls-method`
  - `constructor-static-call`
  - `constructor-control-flow`
  - `constructor-loop`
  - `constructor-try-catch`
  - `constructor-throw`
  - `constructor-new-object`
  - `constructor-nontrivial-body`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script dto-no-logic:gate --`
  - framework implementation detail: `@php tools/gates/dto_no_logic_gate.php`
- Direct call `php framework/tools/gates/dto_no_logic_gate.php` is **NOT** a canonical entrypoint (implementation detail only).
- Aggregate rail integration:
  - `composer dto:gate` invokes this gate after `dto-marker-consistency:gate` and before `dto-shape:gate`
  - failing output MUST pass through the aggregate runner unchanged

**Usage (repo root):**
- `composer dto-no-logic:gate`
- `composer dto-no-logic:gate -- --path=framework`

---

### DTO shape gate

**Id:** `tool.dto_shape_gate`
**Entrypoint:** `composer dto-shape:gate`
**Category:** repo policy / DTO guard
**Outputs:**
- none on success
- exits non-zero on DTO shape policy violations
- emits deterministic diagnostics only through the canonical tooling output policy

**Determinism:**

| Mode / flags                           | Determinism   | Notes                                                                 |
|----------------------------------------|---------------|-----------------------------------------------------------------------|
| `composer dto-shape:gate`              | deterministic | Scans the framework package source tree using the default scan root.  |
| `composer dto-shape:gate -- --path=`   | deterministic | Overrides scan root only; bootstrap/runtime root remains tools-owned. |

**Notes:**
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
  - `framework/packages/**/src/**/*.php`
- Excluded paths:
  - `**/tests/**`
  - `**/fixtures/**`
  - `**/vendor/**`
- `--path=<dir>` policy:
  - overrides only the scan root
  - does not affect bootstrap discovery
  - bootstrap is always loaded from the runtime tools root:
    - `framework/tools/spikes/_support/bootstrap.php`
  - intended for integration tests and local focused checks
- Output policy:
  - shape violations: line 1 is stable code `CORETSIA_DTO_SHAPE_VIOLATION`
  - scan/bootstrap/internal failure: line 1 is stable code `CORETSIA_DTO_GATE_SCAN_FAILED`
  - line 2+ diagnostics use scan-root-relative normalized paths and fixed reason tokens
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
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script dto-shape:gate --`
  - framework implementation detail: `@php tools/gates/dto_shape_gate.php`
- Direct call `php framework/tools/gates/dto_shape_gate.php` is **NOT** a canonical entrypoint (implementation detail only).
- Aggregate rail integration:
  - `composer dto:gate` invokes this gate after `dto-marker-consistency:gate` and `dto-no-logic:gate`
  - failing output MUST pass through the aggregate runner unchanged

**Usage (repo root):**
- `composer dto-shape:gate`
- `composer dto-shape:gate -- --path=framework`

---

### No skeleton HTTP default gate

**Id:** `tool.no_skeleton_http_default_gate`
**Entrypoint:** `composer no-skeleton-http-default:gate`
**Category:** repo policy / guard
**Outputs:**
- none (exits non-zero on violations; emits deterministic diagnostics)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                     |
|--------------|---------------|---------------------------------------------------------------------------|
| default      | deterministic | Deterministic check for forbidden default skeleton HTTP config file only. |

**Notes:**
- Purpose: forbids shipping the default skeleton HTTP config file:
  - `skeleton/config/http.php`
- Canonical policy:
  - HTTP defaults are framework/package-owned
  - skeleton `config/http.php` is app-override only and MUST NOT be present by default
- Output policy:
  - first line is stable code: `CORETSIA_NO_SKELETON_HTTP_DEFAULT_FORBIDDEN`
  - next lines are repo-relative violating paths with fixed reason token, sorted by `strcmp`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script no-skeleton-http-default:gate --`
  - framework implementation detail: `@php tools/gates/no_skeleton_http_default_gate.php`
- Direct call `php framework/tools/gates/no_skeleton_http_default_gate.php` is **NOT** a canonical entrypoint (implementation detail only).
- CI/rails policy:
  - this gate SHOULD run in the dedicated `gates` rail before tests
  - it MUST remain deterministic and rerun-no-diff

**Usage (repo root):**
- `composer no-skeleton-http-default:gate`

---

### No skeleton bundles default gate

**Id:** `tool.no_skeleton_bundles_default_gate`
**Entrypoint:** `composer no-skeleton-bundles-default:gate`
**Category:** repo policy / guard
**Outputs:**
- none (exits non-zero on violations; emits deterministic diagnostics)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                        |
|--------------|---------------|------------------------------------------------------------------------------|
| default      | deterministic | Deterministic check for forbidden default skeleton bundle config files only. |

**Notes:**
- Purpose: forbids shipping default skeleton bundle config files:
  - `skeleton/config/bundles/*.php`
- Canonical policy:
  - bundle defaults are framework-owned
  - skeleton `config/bundles/*.php` files are app-override only and MUST NOT be present by default
- Output policy:
  - first line is stable code: `CORETSIA_NO_SKELETON_BUNDLES_DEFAULT_FORBIDDEN`
  - next lines are repo-relative violating paths with fixed reason token, sorted by `strcmp`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script no-skeleton-bundles-default:gate --`
  - framework implementation detail: `@php tools/gates/no_skeleton_bundles_default_gate.php`
- Direct call `php framework/tools/gates/no_skeleton_bundles_default_gate.php` is **NOT** a canonical entrypoint (implementation detail only).
- CI/rails policy:
  - this gate SHOULD run in the dedicated `gates` rail before tests
  - it MUST remain deterministic and rerun-no-diff

**Usage (repo root):**
- `composer no-skeleton-bundles-default:gate`

---

### No skeleton mode presets default gate

**Id:** `tool.no_skeleton_mode_presets_default_gate`
**Entrypoint:** `composer no-skeleton-mode-presets-default:gate`
**Category:** repo policy / guard
**Outputs:**
- none (exits non-zero on violations; emits deterministic diagnostics)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                             |
|--------------|---------------|-----------------------------------------------------------------------------------|
| default      | deterministic | Deterministic check for forbidden default skeleton mode preset config files only. |

**Notes:**
- Purpose: forbids shipping default skeleton mode preset config files:
  - `skeleton/config/modes/*.php`
- Canonical policy:
  - mode presets are framework-owned
  - skeleton `config/modes/*.php` files are app-override only and MUST NOT be present by default
- Output policy:
  - first line is stable code: `CORETSIA_NO_SKELETON_MODE_PRESETS_DEFAULT_FORBIDDEN`
  - next lines are repo-relative violating paths with fixed reason token, sorted by `strcmp`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script no-skeleton-mode-presets-default:gate --`
  - framework implementation detail: `@php tools/gates/no_skeleton_mode_presets_default_gate.php`
- Direct call `php framework/tools/gates/no_skeleton_mode_presets_default_gate.php` is **NOT** a canonical entrypoint (implementation detail only).
- CI/rails policy:
  - this gate SHOULD run in the dedicated `gates` rail before tests
  - it MUST remain deterministic and rerun-no-diff

**Usage (repo root):**
- `composer no-skeleton-mode-presets-default:gate`

---

### No skeleton modules default gate

**Id:** `tool.no_skeleton_modules_default_gate`
**Entrypoint:** `composer no-skeleton-modules-default:gate`
**Category:** repo policy / guard
**Outputs:**
- none (exits non-zero on violations; emits deterministic diagnostics)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                           |
|--------------|---------------|---------------------------------------------------------------------------------|
| default      | deterministic | Deterministic check for forbidden default skeleton module-selection files only. |

**Notes:**
- Purpose: forbids shipping parallel default module-selection files in the skeleton:
  - `skeleton/config/modules.php`
  - `skeleton/apps/*/config/modules.php`
- Canonical policy:
  - module selection is kernel-owned
  - it is resolved only via preset files + composer metadata
  - skeleton module-selection files are app-level overrides only and MUST NOT be present by default
- Output policy:
  - first line is stable code: `CORETSIA_NO_SKELETON_MODULES_DEFAULT_FORBIDDEN`
  - next lines are repo-relative violating paths with fixed reason token, sorted by `strcmp`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script no-skeleton-modules-default:gate --`
  - framework implementation detail: `@php tools/gates/no_skeleton_modules_default_gate.php`
- Direct call `php framework/tools/gates/no_skeleton_modules_default_gate.php` is **NOT** a canonical entrypoint (implementation detail only).
- CI/rails policy:
  - this gate SHOULD run in the dedicated `gates` rail before tests
  - it MUST remain deterministic and rerun-no-diff

**Usage (repo root):**
- `composer no-skeleton-modules-default:gate`

---

### Contracts-only ports gate

**Id:** `tool.contracts_only_ports_gate`
**Entrypoint:** `composer contracts-only-ports:gate`
**Category:** repo policy / guard
**Outputs:**
- none (exits non-zero on violations; emits deterministic diagnostics)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                                    |
|--------------|---------------|------------------------------------------------------------------------------------------|
| default      | deterministic | Deterministic scan of framework packages source tree for forbidden public-port patterns. |

**Notes:**
- Purpose: forbids declaring canonical public ports outside the owner package:
  - allowed owner scope: `framework/packages/core/contracts/src/**`
  - forbidden outside owner scope:
    - files named `*PortInterface.php`
    - paths under `src/**/Port/**`
- Deterministic scan scope:
  - `framework/packages/*/*/src/**/*.php`
- Exclusions:
  - `**/tests/**`
  - `**/fixtures/**`
  - `**/vendor/**`
- Output policy:
  - first line is stable code: `CORETSIA_CONTRACTS_ONLY_PORTS_FORBIDDEN`
  - next lines are framework-root-relative diagnostics in the form:
    - `<path>: <reason>`
  - diagnostics are sorted by `strcmp`
- Fixed reason tokens:
  - `forbidden-public-port-interface`
  - `forbidden-public-port-namespace`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script contracts-only-ports:gate --`
- Framework implementation detail: `@php tools/gates/contracts_only_ports_gate.php`
- Direct call `php framework/tools/gates/contracts_only_ports_gate.php` is **NOT** a canonical entrypoint (implementation detail only).

**Usage (repo root):**
- `composer contracts-only-ports:gate`

---

### Tag constant mirror gate

**Id:** `tool.tag_constant_mirror_gate`  
**Entrypoint:** `composer tag-constant-mirror:gate`  
**Category:** repo policy / guard  
**Outputs:**
- none (exits non-zero on tag constant / mirror drift; emits deterministic diagnostics)

**Determinism:**

| Mode / flags                         | Determinism   | Notes                                                                  |
|--------------------------------------|---------------|------------------------------------------------------------------------|
| `composer tag-constant-mirror:gate`  | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines. |

**Notes:**
- Purpose: validates reserved DI tag constant usage against `docs/ssot/tags.md`.
- Enforces the temporal tag-constant policy:
  - registry rows may exist before the owner public constant becomes mandatory;
  - existing owner constants, if present, must equal the canonical tag string;
  - package-local mirror constants, if present, must equal the canonical tag string;
  - non-owner packages must not expose competing owner-like tag APIs.
- Output policy:
  - first line is stable code: `CORETSIA_TAG_CONSTANT_MIRROR_DRIFT`
  - next lines are framework-root-relative violating paths + short reason tokens sorted by `strcmp`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script tag-constant-mirror:gate --`
  - framework implementation detail: `@php tools/gates/tag_constant_mirror_gate.php`
- Direct call `php framework/tools/gates/tag_constant_mirror_gate.php` is **NOT** a canonical entrypoint (implementation detail only).
- CI/rails policy: `composer gates` MUST execute this gate through the named composer script, not by raw PHP path.

**Usage (repo root):**
- `composer tag-constant-mirror:gate`

---

### Observability naming gate

**Id:** `tool.observability_naming_gate`  
**Entrypoint:** `composer observability-naming:gate`  
**Category:** repo policy / guard  
**Outputs:**
- none (exits non-zero on observability naming / label policy violations)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                  |
|--------------|---------------|------------------------------------------------------------------------|
| default      | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines. |

**Notes:**
- Purpose: enforces the canonical observability metric naming and label allowlist policy from `docs/ssot/observability.md`.
- Default scan scope:
  - `framework/packages/**/src/**/*.php`
- Excluded paths:
  - `**/tests/**`
  - `**/fixtures/**`
  - `**/vendor/**`
- Enforces at minimum:
  - metric names follow the canonical SSoT shape, for example `http.request_total` and `http.request_duration_ms`
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
  - next lines are framework-root-relative paths with fixed reason tokens, sorted by `strcmp`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script observability-naming:gate --`
  - framework implementation detail: `@php tools/gates/observability_naming_gate.php`
- Direct call `php framework/tools/gates/observability_naming_gate.php` is **NOT** a canonical entrypoint; it is an implementation detail only.
- CI/rails policy: `composer gates` SHOULD execute this gate with the other Phase 1 tooling gates.

**Usage (repo root):**
- `composer observability-naming:gate`

---

### Artifact header/schema gate

**Id:** `tool.artifact_header_schema_gate`  
**Entrypoint:** `composer artifact-header-schema:gate`  
**Category:** repo policy / guard  
**Outputs:**
- none (exits non-zero on artifact envelope/header/schema violations; emits deterministic diagnostics)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                  |
|--------------|---------------|------------------------------------------------------------------------|
| default      | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines. |

**Notes:**
- Purpose: validates generated artifacts against the canonical artifact envelope and header schema from `docs/ssot/artifacts.md`.
- Enforced baseline:
  - top-level envelope MUST be exactly `{ "_meta", "payload" }`
  - required `_meta` fields are `name`, `schemaVersion`, `fingerprint`, `generator`
  - artifact `name` and `schemaVersion` MUST match the canonical artifact registry
  - generated artifacts MUST NOT contain timestamps, absolute paths, or environment-specific bytes
  - JSON artifacts and PHP artifacts returning arrays are both supported
- Temporal artifact-materialization policy:
  - registry rows alone do not require an artifact file to exist yet
  - if no matching generated artifact file exists, the gate behaves as a deterministic no-op
  - if a matching generated artifact file exists, malformed envelope/header/schema fails deterministically
- Output policy:
  - first line is stable code: `CORETSIA_ARTIFACT_HEADER_SCHEMA_DRIFT`
  - next lines are normalized repo-relative paths plus fixed reason tokens sorted by `strcmp`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script artifact-header-schema:gate --`
  - framework implementation detail: `@php tools/gates/artifact_header_schema_gate.php`
- Direct call `php framework/tools/gates/artifact_header_schema_gate.php` is **NOT** a canonical entrypoint (implementation detail only).
- `composer gates` MUST execute this gate as part of the tooling rails chain.

**Usage (repo root):**
- `composer artifact-header-schema:gate`

---

### Cross-cutting contract gate

**Id:** `tool.cross_cutting_contract_gate`  
**Entrypoint:** `composer cross-cutting-contract:gate`  
**Category:** repo policy / guard  
**Outputs:**
- none on success
- deterministic diagnostics on cross-cutting contract policy violations

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                  |
|--------------|---------------|------------------------------------------------------------------------|
| default      | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines. |

**Notes:**
- Purpose: enforces cross-cutting Kernel/Foundation contract invariants once the required owner-package evidence exists.
- Enforced baseline:
  - services tagged as `kernel.stateful` MUST implement `Coretsia\Contracts\Runtime\ResetInterface`
  - services tagged as `kernel.stateful` MUST also be discoverable through the effective Foundation reset discovery tag
  - default effective Foundation reset discovery tag is `kernel.reset`
  - if `foundation.reset.tag` config evidence exists, that configured value is the effective Foundation reset discovery tag
  - the gate MUST NOT hardcode only `kernel.reset` when custom `foundation.reset.tag` evidence is present
  - if the required owner-package evidence is not present yet, the gate behaves as a deterministic no-op
- Foundation reset tag evidence:
  - canonical default: `kernel.reset`
  - optional config evidence path: `framework/packages/core/foundation/config/foundation.php`
  - optional config key namespace: `foundation.reset.tag`
  - the config file MUST return the `foundation` subtree, so the file-level shape is:
    - `['reset' => ['tag' => '<tag-name>']]`
  - custom tag values MUST follow canonical reserved tag naming syntax:
    - `^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$`
- Additional enforcement:
  - once `ContextStore` / `ContextKeys` owner symbols exist, forbidden direct usage is reported deterministically
- Output policy:
  - first line is stable code: `CORETSIA_CROSS_CUTTING_CONTRACT_DRIFT`
  - next lines are framework-root-relative paths plus fixed reason tokens sorted by byte-order `strcmp`
  - diagnostics MUST NOT include absolute paths, raw config payloads, secrets, source snippets, or environment-specific values
- Fixed reason tokens:
  - `kernel-tags-drift`
  - `foundation-reset-tag-invalid`
  - `kernel-stateful-service-missing-reset-tag`
  - `kernel-stateful-service-class-unresolved`
  - `kernel-stateful-service-not-resettable`
  - `forbidden-context-store-usage`
  - `forbidden-context-keys-usage`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script cross-cutting-contract:gate --`
  - framework implementation detail: `@php tools/gates/cross_cutting_contract_gate.php`
- Direct call `php framework/tools/gates/cross_cutting_contract_gate.php` is **NOT** a canonical entrypoint (implementation detail only).
- `composer gates` MUST execute this gate as part of the tooling rails chain.

**Usage (repo root):**
- `composer cross-cutting-contract:gate`

---

### Kernel public API gate

**Id:** `tool.kernel_public_api_gate`  
**Entrypoint:** `composer kernel-public-api:gate`  
**Category:** repo policy / guard  
**Outputs:**
- none (exits non-zero on kernel public API surface violations; emits deterministic diagnostics)

**Determinism:**

| Mode / flags | Determinism   | Notes                                                                  |
|--------------|---------------|------------------------------------------------------------------------|
| default      | deterministic | Deterministic scan; on failure emits minimal stable diagnostics lines. |

**Notes:**
- Purpose: enforces the canonical `core/kernel` public API surface once the owner public-surface evidence exists.
- Temporal public API evidence policy:
  - if `framework/packages/core/kernel/src` does not exist yet, the gate behaves as a deterministic no-op
  - if the kernel public-surface evidence does not exist yet, the gate behaves as a deterministic no-op
  - once owner evidence exists, the gate validates the `core/kernel` public surface without changing the canonical output policy
- Evidence sources include explicit kernel public API docs/config and public API contract-test evidence, for example:
  - `framework/packages/core/kernel/PUBLIC_API.md`
  - `framework/packages/core/kernel/public-api.md`
  - `framework/packages/core/kernel/public_api.md`
  - `framework/packages/core/kernel/public-api.json`
  - `framework/packages/core/kernel/public_api.json`
  - `framework/packages/core/kernel/public-api.php`
  - `framework/packages/core/kernel/public_api.php`
  - `framework/packages/core/kernel/docs/PUBLIC_API.md`
  - `framework/packages/core/kernel/docs/public-api.md`
  - `framework/packages/core/kernel/docs/public_api.md`
  - `docs/ssot/kernel-public-api.md`
  - `docs/ssot/kernel_public_api.md`
  - `docs/architecture/kernel-public-api.md`
  - `docs/architecture/kernel_public_api.md`
  - `framework/packages/core/kernel/tests/**/*PublicApi*.php`
  - `framework/packages/core/kernel/tests/**/*PublicAPI*.php`
  - `framework/packages/core/kernel/tests/**/*PublicSurface*.php`
  - `framework/packages/core/kernel/tests/**/*ApiSurface*.php`
  - `framework/packages/core/kernel/tests/**/*KernelPublic*.php`
- Enforced baseline once evidence exists:
  - public `core/kernel/src` symbols MUST be listed in the public API evidence
  - symbols listed as public API MUST NOT be internal
  - internal symbols are detected through `@internal` docblocks or `Internal` / `internal` path segments
- Output policy:
  - first line is stable code: `CORETSIA_KERNEL_PUBLIC_API_DRIFT`
  - next lines are normalized repo/framework-relative paths plus fixed reason tokens sorted by `strcmp`
- Fixed reason tokens:
  - `kernel-public-api-evidence-empty`
  - `kernel-public-api-symbol-internal-listed`
  - `kernel-public-api-symbol-unlisted`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script kernel-public-api:gate --`
  - framework implementation detail: `@php tools/gates/kernel_public_api_gate.php`
- Direct call `php framework/tools/gates/kernel_public_api_gate.php` is **NOT** a canonical entrypoint (implementation detail only).
- This gate is a standalone rail; optional phpstan/static-analysis rules MAY exist later only as supplemental enforcement, not as a replacement for this command.
- `composer gates` MUST execute this gate as part of the tooling rails chain.

**Usage (repo root):**
- `composer kernel-public-api:gate`

---

### No runtime tooling artifacts gate

**Id:** `tool.no_runtime_tooling_artifacts_gate`
**Entrypoint:** `composer no-runtime-tooling-artifacts:gate`
**Category:** repo policy / runtime purity guard
**Outputs:**
- none on success
- exits non-zero on runtime tooling artifact violations
- emits deterministic diagnostics only through the canonical tooling output policy

**Determinism:**

| Mode / flags                                  | Determinism   | Notes                                                                                |
|-----------------------------------------------|---------------|--------------------------------------------------------------------------------------|
| `composer no-runtime-tooling-artifacts:gate`  | deterministic | Read-only; scans runtime package `src/` and `config/` roots only.                    |

**Notes:**
- Purpose: prevents runtime packages from importing, requiring, executing, or reading Phase 0/Phase 1 tooling code or tooling-generated architecture artifacts.
- This is a runtime-purity gate, not a second architecture dependency brain.
- It complements deptrac because deptrac catches namespace/class dependencies, while this gate catches string-path reads, require/include paths, shell invocations, and accidental runtime consumption of tooling artifacts.
- Default scan scope:
  - `framework/packages/core/*/src`
  - `framework/packages/core/*/config`
  - `framework/packages/platform/*/src`
  - `framework/packages/platform/*/config`
  - `framework/packages/integrations/*/src`
  - `framework/packages/integrations/*/config`
  - `framework/packages/presets/*/src`
  - `framework/packages/presets/*/config`
  - `framework/packages/enterprise/*/src`
  - `framework/packages/enterprise/*/config`
- Excluded paths:
  - `framework/packages/devtools/**`
  - `**/tests/**`
  - `**/fixtures/**`
  - `**/vendor/**`
  - `framework/tools/**` as scan input
- Forbidden evidence:
  - namespace imports or references to `Coretsia\Tools\Spikes\`
  - namespace imports or references to `Coretsia\Devtools\`
  - composer/package references to devtools packages
  - runtime path reads/includes/execs involving `framework/tools/`
  - runtime path reads/includes/execs involving `tools/spikes/`, `tools/build/`, or `tools/gates/`
  - runtime reads of `framework/var/arch`
  - shell command strings that execute tooling paths from runtime code
- Allowed evidence:
  - docs-only mentions outside scan scope
  - tests/fixtures mentions outside runtime scan scope
  - CI/tooling code under `framework/tools/**`
  - generated architecture artifacts consumed by CI/tooling jobs only
- Output policy:
  - violation: line 1 is stable code `CORETSIA_RUNTIME_TOOLING_ARTIFACTS_VIOLATION`
  - scanner/internal failure: line 1 is stable code `CORETSIA_RUNTIME_TOOLING_ARTIFACTS_GATE_FAILED`
  - diagnostics are repo-relative
  - diagnostics are sorted by byte-order `strcmp`
  - diagnostics MUST NOT include source snippets, raw file contents, absolute paths, secrets, environment values, headers, or tokens
- Fixed reason tokens:
  - `runtime-imports-tools-spikes`
  - `runtime-imports-devtools`
  - `runtime-references-devtools-package`
  - `runtime-reads-framework-tools`
  - `runtime-executes-tooling-path`
  - `runtime-reads-architecture-artifact`
- Deterministic no-op policy:
  - if no runtime package scan roots exist, the gate exits 0 and prints nothing
- Non-goals:
  - MUST NOT duplicate deptrac layer rules
  - MUST NOT parse `docs/roadmap/phase0/00_2-dependency-table.md`
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script no-runtime-tooling-artifacts:gate --`
  - framework implementation detail: `@php tools/gates/no_runtime_tooling_artifacts_gate.php`
- Direct call `php framework/tools/gates/no_runtime_tooling_artifacts_gate.php` is **NOT** a canonical entrypoint (implementation detail only).
- Aggregate rail integration:
  - `composer gates` invokes this gate after `composer kernel-public-api:gate`
  - `composer gates` invokes this gate before `composer package-compliance:gate`
  - this gate SHOULD run before framework package tests in CI

**Usage (repo root):**
- `composer no-runtime-tooling-artifacts:gate`

---

### Deptrac config check

**Id:** `tool.arch_deptrac_check`
**Entrypoint:** `composer arch:deptrac:check`
**Category:** architecture / guard / CI rail
**Outputs:**
- none (exits non-zero if generated Deptrac config drift is detected)

**Determinism:**

| Mode / flags                    | Determinism   | Notes                                                   |
|---------------------------------|---------------|---------------------------------------------------------|
| `composer arch:deptrac:check`   | deterministic | Pure check; MUST be rerun-no-diff w.r.t. tracked files. |

**Notes:**
- Purpose: checks that canonical Deptrac generated files are up to date.
- Checked tracked outputs:
  - `framework/tools/testing/deptrac.yaml`
  - `framework/tools/testing/deptrac.allowlist.yaml`
- Reads dependency policy from:
  - `docs/roadmap/phase0/00_2-dependency-table.md`
- Scans framework packages from:
  - `framework/packages/*/*/composer.json`
- Generated config analyzes package `src/` roots only.
- Allowlist policy:
  - may exclude tests, fixtures, vendor, or tooling-only paths
  - MUST NOT exclude `framework/packages/**/src/**`
- This command is part of the `composer arch` aggregate rail.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script arch:deptrac:check --`
- Framework implementation detail:
  - `@php tools/build/deptrac_generate.php --check`
- Failure output policy:
  - drift: line 1 is stable code `CORETSIA_DEPTRAC_OUT_OF_DATE`
  - missing dependency policy: line 1 starts with stable code `CORETSIA_DEPTRAC_SSOT_RULESET_MISSING`
  - cycle detected: line 1 starts with stable code `CORETSIA_DEPTRAC_CYCLE_DETECTED`
  - invalid allowlist: line 1 starts with stable code `CORETSIA_DEPTRAC_ALLOWLIST_INVALID`
  - unexpected failure: line 1 starts with stable code `CORETSIA_DEPTRAC_GENERATE_FAILED`
- Direct call `php framework/tools/build/deptrac_generate.php --check` is **NOT** a canonical entrypoint (implementation detail only).

**Usage (repo root):**
- `composer arch:deptrac:check`

---

### Deptrac config and graph generator

**Id:** `tool.arch_deptrac_generate`
**Entrypoint:** `composer arch:deptrac:generate`
**Category:** architecture / generator / CI artifact materialization
**Outputs:**
- `framework/tools/testing/deptrac.yaml`
- `framework/tools/testing/deptrac.allowlist.yaml` *(created if missing)*
- `framework/var/arch/deptrac_graph.dot` *(gitignored / CI artifact)*
- `framework/var/arch/deptrac_graph.svg` *(gitignored / CI artifact)*
- `framework/var/arch/deptrac_graph.html` *(gitignored / CI artifact)*

**Determinism:**

| Mode / flags                       | Determinism   | Notes                                                                |
|------------------------------------|---------------|----------------------------------------------------------------------|
| `composer arch:deptrac:generate`   | deterministic | Generates Deptrac config, allowlist if missing, and graph artifacts. |

**Notes:**
- Purpose: materializes canonical Deptrac architecture outputs from repository SSoT data.
- Reads dependency policy from:
  - `docs/roadmap/phase0/00_2-dependency-table.md`
- Scans framework packages from:
  - `framework/packages/*/*/composer.json`
- Generated config analyzes package `src/` roots only.
- Exclusions are controlled by:
  - `framework/tools/testing/deptrac.allowlist.yaml`
- Allowlist policy:
  - may exclude tests, fixtures, vendor, or tooling-only paths
  - MUST NOT exclude `framework/packages/**/src/**`
- Graph artifacts are generated for CI upload / architecture inspection only.
- This command is **not** part of the `composer arch` aggregate rail because it is mutating/materializing.
- CI MAY run this command separately in the `arch` job to upload Deptrac graph artifacts.
- It MUST remain deterministic and rerun-no-diff for the same repo state.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script arch:deptrac:generate --`
- Framework implementation detail:
  - `@php tools/build/deptrac_generate.php --apply`
- Failure output policy:
  - missing dependency policy: line 1 starts with stable code `CORETSIA_DEPTRAC_SSOT_RULESET_MISSING`
  - cycle detected: line 1 starts with stable code `CORETSIA_DEPTRAC_CYCLE_DETECTED`
  - invalid allowlist: line 1 starts with stable code `CORETSIA_DEPTRAC_ALLOWLIST_INVALID`
  - unexpected failure: line 1 starts with stable code `CORETSIA_DEPTRAC_GENERATE_FAILED`
- Direct call `php framework/tools/build/deptrac_generate.php --apply` is **NOT** a canonical entrypoint (implementation detail only).

**Usage (repo root):**
- `composer arch:deptrac:generate`

---

### Deptrac architecture analysis

**Id:** `tool.arch_deptrac_analyze`
**Entrypoint:** `composer arch:deptrac:analyze`
**Category:** architecture / dependency analysis / CI rail
**Outputs:**
- none on success
- native Deptrac diagnostics on violations/failure

**Determinism:**

| Mode / flags                      | Determinism   | Notes                                                      |
|-----------------------------------|---------------|------------------------------------------------------------|
| `composer arch:deptrac:analyze`   | deterministic | Runs Deptrac against the canonical generated config.       |

**Notes:**
- Purpose: runs Deptrac architecture analysis using the canonical generated config:
  - `framework/tools/testing/deptrac.yaml`
- This command is part of the `composer arch` aggregate rail.
- This command depends on generated Deptrac config being up to date; CI SHOULD run `composer arch:deptrac:check` before this command.
- This is a third-party architecture analysis command, not a Coretsia policy gate.
  - It does **NOT** follow the Phase 0 gate output contract.
  - It does **NOT** guarantee line 1 is a `CORETSIA_*` code.
  - Native Deptrac diagnostics are expected.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script arch:deptrac:analyze --`
- Framework implementation detail:
  - `@php vendor/bin/deptrac analyse --config-file=tools/testing/deptrac.yaml --no-cache`
- Direct call `php framework/vendor/bin/deptrac analyse --config-file=framework/tools/testing/deptrac.yaml --no-cache` is **NOT** a canonical entrypoint (implementation detail only).

**Usage (repo root):**
- `composer arch:deptrac:analyze`

---

### Quality aggregate rail

**Id:** `tool.quality`
**Entrypoint:** `composer quality`
**Category:** quality / aggregate rail
**Outputs:**
- none directly
- delegates to quality tools, which may produce native diagnostics
- may create/update internal tool cache files, including:
  - `framework/var/phpstan/**`

**Determinism:**

| Mode / flags       | Determinism   | Notes                                                                      |
|--------------------|---------------|----------------------------------------------------------------------------|
| `composer quality` | deterministic | Runs the canonical quality checks in stable order; writes no source files. |

**Notes:**
- Purpose: aggregate quality rail for code style and static analysis.
- Execution order is cemented:
  1) `composer cs:check`
  2) `composer phpstan`
- This command is an aggregate quality rail, not a Coretsia policy gate.
  - It does **NOT** follow the Phase 0 gate output contract.
  - It does **NOT** guarantee line 1 is a `CORETSIA_*` code.
  - It preserves native diagnostics from the underlying tools.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script quality --`
  - framework implementation detail: aggregate `quality` script in `framework/composer.json`
- CI/rails policy:
  - this command SHOULD run in CI after `composer gates`
  - this command SHOULD run before `composer test`
  - this command MUST use `cs:check`, not `cs:fix`
  - this command MUST NOT modify source files
- Local full-check order SHOULD remain:
  1) `composer sync:check`
  2) `composer install:all`
  3) `composer validate:all`
  4) `composer gates`
  5) `composer quality`
  6) `composer test`
  7) `composer lock:check`

**Usage (repo root):**
- `composer quality`

---

### Code style check

**Id:** `tool.cs_check`
**Entrypoint:** `composer cs:check`
**Category:** quality / code style
**Outputs:**
- none on success
- tool diagnostics on failure
- may create/update internal tool cache files only if the underlying tool does so

**Determinism:**

| Mode / flags        | Determinism   | Notes                                                     |
|---------------------|---------------|-----------------------------------------------------------|
| `composer cs:check` | deterministic | Checks code style baseline; MUST NOT rewrite source code. |

**Notes:**
- Purpose: runs the canonical framework code style baseline.
- Configuration SSoT:
  - `framework/tools/cs/ecs.php`
- Scan scope is defined by the ECS config and currently includes:
  - `framework/packages`
  - `framework/tools`
  - `skeleton`
- Exclusions are defined by `framework/tools/cs/ecs.php`.
- This is a third-party quality command, not a Coretsia policy gate.
  - It does **NOT** follow the Phase 0 gate output contract.
  - It does **NOT** guarantee line 1 is a `CORETSIA_*` code.
  - Native ECS diagnostics are expected.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script cs:check --`
  - framework implementation detail: `@php vendor/bin/ecs check --config=tools/cs/ecs.php`
- Direct call `php framework/vendor/bin/ecs check --config=framework/tools/cs/ecs.php` is **NOT** a canonical entrypoint (implementation detail only).
- CI/rails policy:
  - this command SHOULD run in the `quality` rail
  - this command MAY run after gates and before tests
  - this command MUST NOT modify source files

**Usage (repo root):**
- `composer cs:check`

---

### Code style fixer

**Id:** `tool.cs_fix`
**Entrypoint:** `composer cs:fix`
**Category:** quality / code style / mutating fixer
**Outputs:**
- may rewrite PHP source files in the configured ECS scan scope

**Determinism:**

| Mode / flags      | Determinism                | Notes                                         |
|-------------------|----------------------------|-----------------------------------------------|
| `composer cs:fix` | deterministic but mutating | Rewrites files according to the ECS baseline. |

**Notes:**
- Purpose: applies the canonical framework code style baseline.
- Configuration SSoT:
  - `framework/tools/cs/ecs.php`
- This command is intentionally mutating:
  - it MAY rewrite files under the configured ECS paths
  - it MUST NOT be used as a CI check command
- CI/rails policy:
  - CI MUST use `composer cs:check`, not `composer cs:fix`
  - `composer cs:fix` is a local developer command only
- This is a third-party quality command, not a Coretsia policy gate.
  - It does **NOT** follow the Phase 0 gate output contract.
  - It does **NOT** guarantee line 1 is a `CORETSIA_*` code.
  - Native ECS diagnostics are expected.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script cs:fix --`
  - framework implementation detail: `@php vendor/bin/ecs check --config=tools/cs/ecs.php --fix`
- Direct call `php framework/vendor/bin/ecs check --config=framework/tools/cs/ecs.php --fix` is **NOT** a canonical entrypoint (implementation detail only).

**Usage (repo root):**
- `composer cs:fix`

---

### Static analysis baseline

**Id:** `tool.phpstan`
**Entrypoint:** `composer phpstan`
**Category:** quality / static analysis
**Outputs:**
- none on success
- native PHPStan diagnostics on failure
- internal PHPStan cache:
  - `framework/var/phpstan/**`

**Determinism:**

| Mode / flags       | Determinism   | Notes                                               |
|--------------------|---------------|-----------------------------------------------------|
| `composer phpstan` | deterministic | Runs the canonical framework static analysis setup. |

**Notes:**
- Purpose: runs the canonical framework static analysis baseline.
- Configuration SSoT:
  - `framework/tools/phpstan/phpstan.neon`
- Analysis scope is defined by PHPStan config and currently includes:
  - `framework/packages`
  - `framework/tools`
- PHPStan cache policy:
  - `framework/var/phpstan/**` is internal tool cache
  - it is **NOT** a Coretsia generated artifact
  - artifact/schema gates MUST NOT treat PHPStan cache as generated artifact output
- This is a third-party quality command, not a Coretsia policy gate.
  - It does **NOT** follow the Phase 0 gate output contract.
  - It does **NOT** guarantee line 1 is a `CORETSIA_*` code.
  - Native PHPStan diagnostics are expected.
- Under the hood (implementation detail): repo-root wrapper delegates to framework workspace script:
  - `@composer --working-dir=framework run-script phpstan --`
  - framework implementation detail: `@php vendor/bin/phpstan analyse --configuration=tools/phpstan/phpstan.neon --memory-limit=1G`
- Direct call `php framework/vendor/bin/phpstan analyse --configuration=framework/tools/phpstan/phpstan.neon` is **NOT** a canonical entrypoint (implementation detail only).
- CI/rails policy:
  - this command SHOULD run in the `quality` rail
  - this command MAY run after gates and before tests
  - lock drift checks MUST still fail if dependency installation or tooling changes lock files

**Usage (repo root):**
- `composer phpstan`
