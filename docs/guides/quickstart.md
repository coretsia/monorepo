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

# Quickstart (Prelude)

**Goal:** clean clone → working baseline (tests green, CI entrypoints runnable).

**Scope:** Prelude. Commands are assumed to run from the **repository root**.

---

## 0) Prerequisites

- Git
- PHP **8.4+**
- Composer 2.x

Recommended:

- A POSIX-like shell on Windows (Git Bash) for local hooks.

---

## 1) Clean clone

```bash
git clone <repo-url>
cd <repo-dir>
```

---

## 2) One-time setup

Run the canonical entrypoint:

```bash
composer setup
```

What this MUST achieve (by policy):

- installs dependencies (via committed lockfiles),
- configures Git hooks path to `.githooks`,
- ensures managed Composer repositories policy is respected.

Verify hooks path:

```bash
git config --get core.hooksPath
```

Expected:

```txt
.githooks
```

---

## 3) Run the baseline checks

Tests:

```bash
composer test
```

CI entrypoint:

```bash
composer ci
```

---

## 4) After setup: follow the canonical development workflow

For day-to-day work (including adding packages without manual Composer repository edits), follow:

- `docs/guides/development-workflow.md`

---

## 5) Common failures and deterministic fixes

### A) Pre-commit fails: managed Composer repositories drift

Symptom: commit blocked, hook exits non-zero after running the managed repositories guard.

Fix:

```bash
php framework/tools/build/sync_composer_repositories.php
git add composer.json framework/composer.json skeleton/composer.json
git commit
```

### B) CI / install fails due to lock drift

Policy summary:

- Lock files MUST be committed (root/framework/skeleton).
- CI MUST use `composer install` (not update) and MUST NOT modify locks.

Fix approach:

- do **not** run `composer update` as a first reaction;
- re-run `composer setup` and/or ensure you are on the correct branch/commit;
- if you intentionally changed dependencies, ensure the correct lockfiles are updated and committed.

### C) Wrong PHP binary

If `php` is not found or is < 8.4, fix your PATH or invoke setup using an explicit PHP binary:

```bash
PHP=/path/to/php composer setup
```

---

## 6) Minimal workflow reminder

- Always run commands from the **repo root** (`composer setup|test|ci`).
- Do not manually edit managed `repositories` blocks in Composer roots.
- Keep your working tree clean; policies rely on deterministic “rerun-no-diff” discipline.
