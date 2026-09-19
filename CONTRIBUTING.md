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

- `packages/**` — publishable Composer products
  - `packages/framework/` — `coretsia/framework`
  - `packages/applications/skeleton/` — `coretsia/skeleton`
- `tools/**` — gates, generators, CI rails, and tooling support
- `var/**` — mutable/generated repository workspace state
- `docs/**` — SSoT (`docs/ssot/**`) + task-first roadmap (`docs/roadmap/**`)

## Ground rules (SSoT, MUST)

- If a change affects **architecture**, **invariants**, **public surfaces**, **runtime behavior**, or **determinism**, you **MUST** update the relevant SSoT docs under `docs/ssot/**`.
- If a change introduces/modifies a deliverable set for a phase/epic, you **MUST** update the roadmap under `docs/roadmap/**`.
- Compile-time dependencies **MUST** remain deptrac-enforceable. Avoid cross-layer coupling by design (no “it’s convenient” exceptions).

## Community RFCs and design proposals

Non-code proposals, design direction, website ideas, branding applications, documentation experience, and community-facing concepts should start as GitHub Discussions before implementation work.

Current website-related design work is tracked through:

- [Website design RFC](https://github.com/coretsia/monorepo/discussions/51)
- [Coretsia website repository](https://github.com/coretsia/website)

Accepted website and visual implementation decisions are recorded in the website repository under:

```text
docs/decisions/
```

Website implementation work happens through scoped issues and pull requests in:

```text
coretsia/website
```

Branding-related proposals must align with:

- [Branding specification](docs/architecture/BRANDING.md)

Implementation pull requests that follow from an accepted discussion should link the relevant Discussion, decision record, or implementation issue in the PR description.

## Packaging law (MUST)

- Publishable Composer products **MUST** live under `packages/**`.
- Special package locations are:
  - `packages/framework/` → `coretsia/framework`
  - `packages/applications/skeleton/` → `coretsia/skeleton`
- Layered packages **MUST** live at `packages/<layer>/<slug>/`.
- Package identity **MUST** be read from each package `composer.json`; filesystem location **MUST NOT** be used as the Composer-name source of truth.
- `packages/devtools/<slug>/` publishes `coretsia/devtools-<slug>`.
- Namespace and package-shape rules **MUST** comply with `docs/architecture/PACKAGING.md`.
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

The `repositories` block in root `composer.json` is **managed** and **MUST NOT** be manually edited.

Single source of truth tool:

```bash
composer sync:check
composer sync:repos
```

Policy (MUST):

- The tool is idempotent (rerun-no-diff), supports `--check`, is runnable without `vendor/autoload`, and writes backups under `var/backups/*` (ignored).
- Pre-commit guard **MUST** enforce drift check (`--check`) and fail on drift.

## Lock determinism (MUST)

- The root workspace `composer.lock` **MUST** be committed.
- Consumer applications own their own `composer.lock`.
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

For the complete canonical command catalog and rail composition, see:

- [Command catalog](docs/guides/commands.md)

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
- Publishing is source-only for current development release lines (no built artifacts).
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
