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

# Releasing (GitHub Release + Packagist)

This guide defines the **canonical** (single-choice) release procedure for the Coretsia monorepo in Phase 0.

## Invariants (MUST)

### Version source of truth

- The **git tag** is the single source of truth for the released version.
- Tags **MUST** use semantic versioning format: `vMAJOR.MINOR.PATCH` (example: `v1.2.3`).
- Per-package independent versions **MUST NOT** be used.

### Release notes source

- Release notes source is **human-maintained** `CHANGELOG.md`.
- `CHANGELOG.md` **MUST** contain a section header exactly:
  - `## vMAJOR.MINOR.PATCH`
- The release notes body is the content under that header until the next `## ...` header.

### Publishing policy (Phase 0)

- Publishing **MUST be source-only**:
  - no built binaries
  - no generated artifacts attached to GitHub Releases
- GitHub Release is created from the tag.
- Packagist publishing is **automatic** via GitHub integration (see below).

## Packagist policy (MUST)

Packagist publish target is **split repositories** (one package → one repo), not the monorepo root.
Canonical packaging law: `docs/architecture/PACKAGING.md`.

For each split repo `coretsia/<layer>-<slug>` that is submitted to Packagist:

- The split repository MUST be connected in Packagist using GitHub integration.
- Packagist auto-update on tag push MUST be enabled.
- A normal release MUST NOT require pressing “Update” manually in the Packagist UI.

Canonical mechanism (when split repos exist and hooks are enabled):
`push tag vMAJOR.MINOR.PATCH` → split repos receive the same tag → Packagist auto-update (GitHub integration)

## Status (Phase 0)

- GitHub Release automation for this monorepo is implemented by `.github/workflows/release.yml`.
- Packagist auto-update is a **policy requirement** but remains **blocked until first public release evidence**:
  - split repositories exist and are public,
  - tags are propagated to split repositories,
  - evidence: the tag appears in Packagist without manual “Update”.

## Preconditions (MUST)

Before cutting a release, ensure these files exist in the repo root:

- `CHANGELOG.md`
- `UPGRADE.md`
- `CONTRIBUTING.md`

## Canonical procedure (MUST)

All commands are run from the **repo root**.

### 1) Prepare the release notes (CHANGELOG)

1. Add a new section to `CHANGELOG.md`:

- Header: `## vMAJOR.MINOR.PATCH`
- Content: human-written bullets grouped by area (kernel / tooling / docs / etc.)

2. If the release contains upgrade-relevant changes, update `UPGRADE.md`.
3. Ensure CI rails are green locally:

```bash
composer ci
```

### 2) Commit

Commit the changelog (and any related changes):

```bash
git add CHANGELOG.md UPGRADE.md
git commit -m "[vMAJOR.MINOR.PATCH] chore(release): prepare release notes"
```

### 3) Create the tag (single-choice)

Create an **annotated** tag where the message equals the tag:

```bash
git tag -a vMAJOR.MINOR.PATCH -m "vMAJOR.MINOR.PATCH"
```

### 4) Push the tag

```bash
git push origin vMAJOR.MINOR.PATCH
```

### 5) Automation outcome

After the tag is pushed:

- GitHub Actions workflow `.github/workflows/release.yml` runs and:
  - validates the tag format
  - validates required files
  - extracts notes from `CHANGELOG.md`
  - creates a GitHub Release for the tag (source-only)
- Packagist auto-updates via GitHub integration (no manual UI step).

## Validation / dry-run mode (MUST)

The workflow supports manual **validation** (no release creation) via `workflow_dispatch`.

Use this when:

- you want to verify tag formatting rules
- you want to confirm `CHANGELOG.md` contains the release notes section
- you want to confirm required files exist

Inputs:

- `tag`: `vMAJOR.MINOR.PATCH`

## Security & redaction (MUST)

- Workflow logs **MUST NOT** print secrets:
  - tokens, cookies, session ids, private keys, `.env` contents
- Diagnostics **MAY** include only safe tokens (e.g., tag string, file paths relative to repo root).
- No generated text must include timestamps/randomness.

## Troubleshooting

- **Validation fails: missing `## vX.Y.Z` in CHANGELOG**
  - Add the header and content, commit, and re-run validation.
- **Release already exists for the tag**
  - The workflow is idempotent: it will detect the existing release and exit successfully.
- **Packagist did not update**
  - Confirm the Packagist project is connected to GitHub and auto-update is enabled.
  - The canonical process forbids relying on manual “Update” for normal releases.
