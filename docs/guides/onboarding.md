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

# Developer onboarding

This is a checklist to get from zero → productive contributor with the canonical workflows.

Scope: Current monorepo development baseline. Commands are assumed to run from the repository root.

Hard rule: docs/workflows MUST NOT rely on `./dev/**`.

---

## 1) Environment checklist

- [ ] PHP 8.4+ installed and on PATH
- [ ] Composer 2.x installed
- [ ] Git installed
- [ ] (Windows) You can run Bash scripts (Git Bash recommended)

---

## 2) Clone and bootstrap

- [ ] Clone and enter repo:

```bash
git clone <repo-url>
cd <repo-dir>
```

- [ ] Run canonical setup:

```bash
composer setup
```

- [ ] Verify hooks are enabled:

```bash
git config --get core.hooksPath
```

Expected output:

```txt
.githooks
```

---

## 3) Establish a green baseline

- [ ] Run tests:

```bash
composer test
```

- [ ] Run CI entrypoint:

```bash
composer ci
```

If baseline is not green, stop and fix it before making changes.

---

## 4) Core workflow laws (you will hit these immediately)

### 4.1 Repo entrypoints (single-choice)

- [ ] Use only repo-root Composer scripts as canonical entrypoints:
  - `composer setup`
  - `composer test`
  - `composer ci`

### 4.2 Managed Composer repositories (single source of truth)

- [ ] You MUST NOT manually edit the managed `repositories` block in root `composer.json`.

- [ ] If drift happens, fix via the canonical tool:

```bash
composer sync:repos
composer sync:check
```

Pre-commit enforces drift checks and MUST block commits on mismatch.

### 4.3 Lock determinism

- [ ] The root workspace `composer.lock` MUST be committed.
- [ ] CI MUST rely on `composer install` (not update) and MUST fail on lock drift.
- [ ] Avoid “fix by update”; do it only when the change is intentional and reviewed.

---

## 5) Monorepo packaging identity (naming & layout law)

When you create or review publishable Composer products, verify these invariants:

- [ ] Publishable products MUST live under `packages/**`.
- [ ] Layered packages use `packages/<layer>/<slug>/` and package id `<layer>/<slug>`.
- [ ] Special public distributions are:
  - `packages/framework/` → `coretsia/framework`
  - `packages/applications/skeleton/` → `coretsia/skeleton`
- [ ] Composer identity MUST be read from the package `composer.json` `name` field.
- [ ] Layered package namespace mapping MUST follow `docs/architecture/PACKAGING.md`, including the canonical `core/*` short-namespace exception.
- [ ] Layered package source/tests mapping MUST follow the package namespace rules in `docs/architecture/PACKAGING.md`.
- [ ] Versioning MUST be monorepo-wide via repo tags `vMAJOR.MINOR.PATCH` (no per-package versions).

---

## 6) Dependency truth source (SSoT)

- [ ] For exact direct compile-time dependency permissions between layered packages, use:
  - `docs/architecture/DEPENDENCIES.md`

Other docs MAY provide explanations or layer-level summaries, but MUST NOT introduce alternative package-level edges.

---

## 7) What to read next (minimum set)

- [ ] `docs/roadmap/ROADMAP.md` (canonical roadmap and implementation phases)
- [ ] `docs/architecture/DEPENDENCIES.md` (exact direct compile-time dependency SSoT)
- [ ] `docs/guides/git-hooks.md` (hooks + managed repos workflow)
- [ ] `docs/guides/dependency-graph.md` (conceptual dependency model)
- [ ] `docs/ssot/INDEX.md` (SSoT registry entrypoint)

---

## 8) Next: development workflow

Continue with the canonical day-to-day workflow (including adding packages without manual Composer repository edits):

- `docs/guides/development-workflow.md`
