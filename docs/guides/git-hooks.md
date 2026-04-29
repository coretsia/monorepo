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

# Git hooks (Prelude)

Git hooks are used as a **local convenience guard** to enforce the **managed Composer repositories** policy.

**Scope:** Prelude only (no `./dev/**` tooling is allowed here).

---

## 1) What is enforced

The `repositories` blocks in all three Composer roots:

- `composer.json`
- `framework/composer.json`
- `skeleton/composer.json`

**MUST** be managed only by:

- `php framework/tools/build/sync_composer_repositories.php`

Manual edits of managed entries **MUST NOT** be part of the workflow.

The pre-commit hook enforces this by running:

```bash
php framework/tools/build/sync_composer_repositories.php --check
```

If drift is detected, the commit is blocked.

---

## 2) Enable hooks (single-choice)

Hooks **MUST** be enabled by setting the Git hooks path:

```bash
git config core.hooksPath .githooks
```

Canonical one-time setup on a clean clone:

```bash
composer setup
```

(That script MUST configure `core.hooksPath` as part of setup.)

To verify hooks path:

```bash
git config --get core.hooksPath
```

Expected output:

```txt
.githooks
```

---

## 3) Fixing a hook failure (managed repositories drift)

If pre-commit fails due to managed repositories drift:

1. Restore canonical managed blocks:

```bash
php framework/tools/build/sync_composer_repositories.php
```

2. Re-stage and commit again:

```bash
git add composer.json framework/composer.json skeleton/composer.json
git commit
```

---

## 4) CI remains authoritative

CI repeats the same rule and MUST run the drift check **before** any `composer install`:

```bash
php framework/tools/build/sync_composer_repositories.php --check
```

Local hooks are convenience; CI is the source of truth.
