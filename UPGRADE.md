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

# Upgrade Guide

This document contains migration notes between versions of the Coretsia monorepo.

## Policy (normative)

- Version source of truth is the monorepo git tag: `vMAJOR.MINOR.PATCH`.
- Per-package independent versions **MUST NOT** be used.
- Breaking changes in **public API** require a **major** version bump.
- Deprecations follow: **warn in N, remove in N+2**.
- If an upgrade changes **runtime behavior**, **shapes**, **invariants**, **determinism**, or **boundaries**, the change **MUST** be reflected in SSoT docs under `docs/ssot/**`.

## Canonical invariants you must assume during upgrades

### 1) Config subtree rule (cemented)

`config/<name>.php` returns a **subtree** (no wrapper repeating the root key).  
Runtime reads from global config under that root key (e.g. `foundation.container.*`).

If you previously relied on wrapper roots in config files, you must adjust your config files accordingly.

### 2) Managed Composer repositories (must not drift)

`repositories` blocks are managed only by:

```bash
composer sync:repos
```

During upgrades (or rebases), treat manual edits of `repositories` as invalid and expect CI/pre-commit to fail on drift.

### 3) Lock determinism (must not drift)

Lock files are committed for root/framework/skeleton. CI uses `composer install` and fails on lock drift.
If your upgrade changes dependencies, regenerate locks intentionally and commit them.

### 4) Determinism & redaction baseline

Tooling/gates/artifacts must remain deterministic and must not leak secrets/PII.
Expect upgrades to reject:

- timestamps, absolute paths, env-specific bytes in artifacts/diagnostics
- raw `.env` values, tokens, Authorization/cookies/session ids, raw payloads, raw SQL

## Upgrading (checklist)

1. **Pick the target tag**

- Decide the target `vMAJOR.MINOR.PATCH` you upgrade to.

2. **Read release notes**

- Review `CHANGELOG.md` entries between your current tag and the target tag.

3. **Review SSoT deltas**

- Scan `docs/ssot/**` changes in that range, focusing on:
  - shapes / outcomes / invariants
  - config rules and reserved namespaces (e.g. `@*` directives policy if relevant)
  - runtime driver composition rules (if your app uses long-running runtimes)

4. **Sync managed repositories (if needed)**

```bash
composer sync:check
```

5. **Install dependencies using locks**

```bash
composer install:all
```

6. **Run the canonical rails**

```bash
composer test
composer ci
```

7. **Search for deprecations**

- Search your app for `@deprecated` usage and follow migration notes.
- Remember: removals happen in `N+2` by policy.

## v0.x development snapshots

The `0.x` line is a development snapshot line. These tags are useful for early package publication, integration testing, and external smoke checks, but they are not stable support lines.

### From v0.2.0 to v0.3.0

#### Compatibility

- No stable API compatibility guarantee is provided for `0.x` development snapshots.

#### Notes

- Review `CHANGELOG.md` for the `v0.3.0` package publication details.
- This release introduced additional publish-ready split packages.

#### Migration steps

1. Review `CHANGELOG.md`.
2. Refresh dependency locks intentionally if your workspace consumes split packages.
3. Run:

```bash
composer setup
composer ci
```

### From v0.1.0 to v0.2.0

#### Compatibility

- No stable API compatibility guarantee is provided for `0.x` development snapshots.

#### Notes

- Review `CHANGELOG.md` for the `v0.2.0` contracts baseline and first public split package details.
- This release introduced the first publish-ready split package and the Phase 1 contracts baseline.

#### Migration steps

1. Review `CHANGELOG.md`.
2. Refresh dependency locks intentionally if your workspace consumes split packages.
3. Run:

```bash
composer setup
composer ci
```

## Entry template

> Add a new section per upgrade jump you want to document.

### From X.Y.Z to A.B.C

#### Breaking changes

- *TBD*

#### Deprecations

- *TBD* (warn in N, remove in N+2)

#### Migration steps

1. *TBD*

#### Notes

- *TBD* (include only deterministic, non-sensitive diagnostics; avoid raw values/paths)
