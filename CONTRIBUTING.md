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

# Contributing to Coretsia (Monorepo)

This repository is **SSoT-first**, **deterministic-by-default**, and **boundary-strict**.

## Repository layout (canonical)

- `framework/` — framework meta-package + all framework packages
- `skeleton/` — local workspace app sandbox (fixtures, E2E, entrypoints)
- `docs/` — SSoT (`docs/ssot/**`) + task-first roadmap (`docs/roadmap/**`)
- `framework/tools/` — gates, generators, CI rails, spikes (Phase 0 / Prelude)

## Ground rules (SSoT, MUST)

- If a change affects **architecture**, **invariants**, **public surfaces**, **runtime behavior**, or **determinism**, you **MUST** update the relevant SSoT docs under `docs/ssot/**`.
- If a change introduces/modifies a deliverable set for a phase/epic, you **MUST** update the roadmap under `docs/roadmap/**`.
- Compile-time dependencies **MUST** remain deptrac-enforceable. Avoid cross-layer coupling by design (no “it’s convenient” exceptions).

## Packaging law (MUST)

- Packages **MUST** live at: `framework/packages/<layer>/<slug>/`
- Package id **MUST** be: `<layer>/<slug>`
- Composer name **MUST** be: `coretsia/<layer>-<slug>`
- Namespace mapping **MUST** be deterministic:
  - `Coretsia\<Studly(layer)>\<Studly(slug)>\...`
  - `src/` for sources, `tests/` for tests (`...\Tests\...`)
- Versioning is **monorepo-wide** via tags: `vMAJOR.MINOR.PATCH` (per-package independent versions **MUST NOT** be used).

## Canonical entrypoints (MUST)

All docs and workflows assume commands run from the **repo root**.

Canonical user entrypoints are repo-root composer scripts:

```bash
composer setup
composer test
composer ci
```

## Managed composer repositories (MUST NOT edit by hand)

`repositories` blocks in these files are **managed** and **MUST NOT** be manually edited:

- `composer.json`
- `framework/composer.json`
- `skeleton/composer.json`

Single source of truth tool:

```bash
php framework/tools/build/sync_composer_repositories.php --check
php framework/tools/build/sync_composer_repositories.php
```

Policy (MUST):

- The tool is idempotent (rerun-no-diff), supports `--check`, is runnable without `vendor/autoload`, and writes backups under `framework/var/backups/*` (ignored).
- Pre-commit guard **MUST** enforce drift check (`--check`) and fail on drift.

## Lock determinism (MUST)

- Lock files **MUST** be committed for: root / framework / skeleton.
- CI **MUST** use `composer install` (not update) and **MUST NOT** modify lock files.
- CI **MUST** fail on lock drift.
- CI **MUST** run managed-repos drift check **before** installs.

## Development setup

From repo root:

```bash
composer install
composer setup
```

## Quality checks

Fast baseline:

```bash
composer test
```

CI-like run:

```bash
composer ci
```

Typical CI rails include (by roadmap):

- deterministic gates (Phase 0 output policy)
- arch (deptrac generate + analyze; rerun-no-diff)
- unit + contract tests
- integration suites (fast/slow where applicable)
- determinism suites (Linux + Windows where relevant)

## Spikes boundary (Phase 0, MUST)

Spikes are strictly tools-only.

- The **only** spikes root is: `framework/tools/spikes/**`
- Spikes **MUST NOT**:
  - import runtime packages `core/*`, `platform/*`, `integrations/*` (any `Coretsia\*` runtime API),
  - do path-based imports from `framework/packages/**` (`require/include` pointing into `packages/**/src/**`).
- The **only exception** is deterministic primitives in `coretsia/devtools-internal-toolkit` (tooling-only library), used only via Composer autoload (namespace), never via path `require`.

If you touch tooling determinism/generators, run the spikes rails (if present in scripts):

```bash
composer spike:test
composer spike:test:determinism
```

## Deterministic output & redaction (MUST)

- Tooling/gates **MUST** follow code-first output:
  - line 1: `CODE`
  - line 2+: minimal diagnostics, normalized paths, sorted by `strcmp`
- Tooling output **MUST NOT** leak secrets/PII (no `.env` values, tokens, auth/session ids, raw payloads, raw SQL).
- Prefer stable error codes and safe diagnostics tokens over “pretty” text.

## Dependency SSoT (Phase 0, MUST)

Phase 0 compile-time dependency truth lives only in:

- `docs/roadmap/phase0/00_2-dependency-table.md`

Other docs may describe *build order*, but **MUST NOT** claim dependency truth; they must link to the dependency SSoT.

## Release process (MUST)

Canonical release procedure is defined in:

- `docs/guides/releasing.md`

Rules (summary):

- Version source of truth is the monorepo git tag: `vMAJOR.MINOR.PATCH`.
- Release notes source is `CHANGELOG.md` (must contain `## vMAJOR.MINOR.PATCH` section).
- Publishing is source-only in Phase 0 (no built artifacts).
- Packagist publishing must be automatic via GitHub integration (no manual “Update” step for normal releases).

## Commit messages (MUST)

Commit messages **MUST** be in English.

Guidelines:

- Use imperative mood: `Add ...`, `Fix ...`, `Refactor ...`
- Keep subject concise (ideally ≤ 72 chars)
- If needed, add a body explaining *why* (not just *what*)

Examples:

- `Add managed repositories drift gate`
- `Fix deterministic ordering in tag registry`
- `Refactor kernel boot error codes`

## Pull request expectations

- Keep changes scoped to one concern/epic where possible.
- If you touch invariants or public shapes, include:
  - SSoT updates (`docs/ssot/**`)
  - contract/locks where applicable
  - rerun-no-diff evidence (no generated drift)
- Never include secrets/PII in commits, fixtures, diagnostics, or docs.
