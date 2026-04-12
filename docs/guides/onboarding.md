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

# Developer onboarding (Prelude)

This is a **checklist** to get from zero → productive contributor with the canonical workflows.

**Scope:** Prelude. Commands are assumed to run from the **repository root**.  
**Hard rule:** docs/workflows MUST NOT rely on `./dev/**`.

---

## 1) Environment checklist

- [ ] PHP **8.4+** installed and on PATH
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

- [ ] You MUST NOT manually edit `repositories` in:
  - `composer.json`
  - `framework/composer.json`
  - `skeleton/composer.json`

- [ ] If drift happens, fix via the canonical tool:

```bash
php framework/tools/build/sync_composer_repositories.php
```

Pre-commit enforces drift checks and MUST block commits on mismatch.

### 4.3 Lock determinism

- [ ] Lockfiles MUST be committed (root/framework/skeleton).
- [ ] CI MUST rely on `composer install` (not update) and MUST fail on lock drift.
- [ ] Avoid “fix by update”; do it only when the change is intentional and reviewed.

---

## 5) Monorepo packaging identity (naming & layout law)

When you create or review packages, verify these invariants:

- [ ] Package path MUST be: `framework/packages/<layer>/<slug>/`
- [ ] Package id MUST be: `<layer>/<slug>`
- [ ] Composer name MUST be: `coretsia/<layer>-<slug>`
- [ ] Namespace mapping MUST be deterministic:
  - `Coretsia\<Studly(layer)>\<Studly(slug)>\...`
  - source under `src/`, tests under `tests/`
- [ ] Versioning MUST be monorepo-wide via repo tags `vMAJOR.MINOR.PATCH` (no per-package versions).

---

## 6) Dependency truth source (SSoT)

- [ ] For Phase 0 / compile-time dependency truth, use the single source of truth:
  - `docs/roadmap/phase0/00_2-dependency-table.md`

Other docs MAY provide explanations, but MUST NOT claim dependency truth.

---

## 7) Spikes boundary (Prelude awareness)

If you touch spikes/tooling:

- [ ] Spikes live only under `framework/tools/spikes/**`.
- [ ] Spikes MUST NOT import runtime packages (`core/*`, `platform/*`, `integrations/*`) via `Coretsia\*` APIs.
- [ ] Any shared deterministic primitives belong only in `coretsia/internal-toolkit` and are consumed via Composer autoload.

---

## 8) What to read next (minimum set)

- [ ] `docs/roadmap/ROADMAP.md` (Prelude canonical rules)
- [ ] `docs/guides/git-hooks.md` (hooks + managed repos workflow)
- [ ] `docs/guides/dependency-graph.md` (conceptual model)
- [ ] `docs/ssot/INDEX.md` (SSoT registry entrypoint)

---

## 9) Next: development workflow

Continue with the canonical day-to-day workflow (including adding packages without manual Composer repository edits):

- `docs/guides/development-workflow.md`
