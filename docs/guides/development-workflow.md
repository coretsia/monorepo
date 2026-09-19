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

# Development workflow

Goal: a newcomer can follow a canonical workflow from a clean clone without manually editing Composer `repositories`.

Scope: Current monorepo development baseline.

Hard invariant: commands are assumed to run from the repository root unless explicitly stated otherwise.

Hard rule: documented workflows MUST NOT rely on `./dev/**`.

---

## 0) Canonical navigation

- Quickstart (clean clone → green baseline): `docs/guides/quickstart.md`
- Onboarding checklist (what to install + baseline laws): `docs/guides/onboarding.md`
- Canonical command catalog (SSoT): `docs/guides/commands.md`
- Git hooks & managed repositories policy: `docs/guides/git-hooks.md`
- Packaging / identity law: `docs/architecture/PACKAGING.md`
- Compile-time dependency law: `docs/architecture/DEPENDENCIES.md`

---

## 1) Global invariants (MUST)

### 1.1 Repo-root execution (MUST)

- All workflow steps assume you are in the repo root (the directory that contains `composer.json`, `packages/`, and `tools/`).

### 1.2 Managed Composer repositories (MUST)

- You MUST NOT manually edit the managed `repositories` block in root `composer.json`.

- The canonical repo-root entrypoints are:
  - `composer sync:repos` (apply)
  - `composer sync:check` (check, CI/guard)

The underlying implementation is documented in the command catalog.

See: `docs/guides/git-hooks.md`.

### 1.3 Lock determinism (MUST)

- The root workspace `composer.lock` MUST be committed.

- CI entrypoint MUST fail on lock drift:
  - `composer lock:check` (part of `composer ci`)

---

## 2) Baseline: “clean clone → green” (single-choice)

If you are not sure where to start, do exactly this from repo root:

```bash
composer setup
composer ci
```

What this guarantees by policy:

- hooks are enabled (`core.hooksPath=.githooks`),
- managed repositories are synced/validated,
- root workspace dependencies are installed from the committed `composer.lock`,
- root `composer.json` is validated,
- the test suite is runnable.

If `composer ci` is not green, stop and fix baseline first.

---

## 3) Day-to-day loop (canonical)

This is the default contributor loop for the current monorepo development baseline.

### 3.1 Start-of-work check

From repo root:

```bash
composer ci
```

Rationale: `composer ci` runs the canonical rails in a fixed order (including `sync:check` and lock drift guard).

### 3.2 Make changes

- edit code / docs,
- keep changes minimal and reproducible,
- avoid introducing new workflow entrypoints in docs.

### 3.3 Pre-commit sanity

Before committing, run at least one of:

```bash
composer test
```

or the full rails:

```bash
composer ci
```

If you changed dependencies anywhere (any `composer.json` or root `composer.lock`), always run:

```bash
composer ci
```

---

## 4) Adding a new layered package (step-by-step)

This workflow creates a new publishable unit under `packages/<layer>/<slug>/` using the canonical generator.

### 4.1 Choose identity (MUST)

- `layer` MUST be one of:
  - `core`, `platform`, `integrations`, `enterprise`, `devtools`, `presets`
- `slug` MUST be kebab-case (see `docs/architecture/PACKAGING.md`).

### 4.2 Generate the package (canonical)

From repo root:

```bash
composer package:new -- --layer=<layer> --slug=<slug> --kind=<library|runtime>
```

This tool is the canonical entrypoint for scaffolding.

### 4.3 Validate layout & identity (MUST)

Verify the package matches the packaging law:

- path: `packages/<layer>/<slug>/`
- composer: package `composer.json` `name` MUST match the canonical identity for that package
- namespace mapping is deterministic (see `docs/architecture/PACKAGING.md`)
- dependency policy: the package MUST have exactly one canonical row in `docs/architecture/DEPENDENCIES.md`

### 4.4 Sync repositories (optional, safe; rerun-no-diff)

After creating or relocating a package, synchronize the managed root Composer repositories so the new package is included in the workspace:

```bash
composer sync:repos
```

Verify that the managed repositories are synchronized:

```bash
composer sync:check
```

### 4.5 If you added/changed dependencies (MUST follow lock policy)

Dependency changes must be intentional and must update the root workspace `composer.lock` when dependency resolution changes.

Any added or removed internal production dependency between layered packages MUST also be reflected intentionally in `docs/architecture/DEPENDENCIES.md`.

Use the appropriate native Composer update operation for the intended dependency change, then run:

```bash
composer ci
```

For canonical Coretsia workflow commands, see:

- `docs/guides/commands.md`

---

## 5) Common failures and deterministic fixes

### 5.1 Pre-commit fails: managed repositories drift

Fix (repo root):

```bash
composer sync:repos
composer sync:check
git add composer.json
git commit
```

### 5.2 `composer ci` fails: lock drift detected

Policy: CI must rely on `composer install` and must not modify `composer.lock`.

Deterministic fix approach:

1. ensure you are on the intended branch/commit,
2. rerun:

```bash
composer setup
composer ci
```

If the drift is intentional (you explicitly changed dependencies), ensure the root `composer.lock` is updated and committed.

### 5.3 Composer validation fails

Run the strict validators directly (repo root):

```bash
composer validate --strict
```

Then re-run:

```bash
composer ci
```

---

## 6) Canonical command entrypoints

This doc intentionally stays minimal. The authoritative catalog is:

- `docs/guides/commands.md`

Minimum set you will use constantly:

- `composer setup`
- `composer ci`
- `composer test`
- `composer sync:repos`
- `composer sync:check`

Invariant reminder: these commands are anchored in the repo root.

---

## 7) Additional verification rails

The current repository baseline includes additional repo-root verification rails that should be used when relevant:

- `composer arch`
- `composer gates`
- `composer dto:gate`
- `composer quality`
- `composer package-compliance:gate`
- `composer package-scaffold:check`

For the complete canonical command catalog, see:

- `docs/guides/commands.md`
