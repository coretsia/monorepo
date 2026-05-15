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

This guide defines the **canonical** (single-choice) release procedure for the Coretsia monorepo during the current development release line.

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

### Publishing policy

- Publishing **MUST be source-only**:
  - no built binaries
  - no generated artifacts attached to GitHub Releases
- GitHub Release is created from the tag.
- Packagist package updates are **automatic** via GitHub integration after the package has been submitted once.
- The first Packagist package submission is a one-time bootstrap action per public split repository.

## Packagist policy (MUST)

Packagist publish target is **split repositories** (one package → one repo), not the monorepo root.

Canonical packaging law:

- [Packaging strategy](../architecture/PACKAGING.md)

For each split repo `coretsia/<layer>-<slug>` that is submitted to Packagist:

- The split repository MUST be connected in Packagist using GitHub integration.
- Packagist auto-update on tag push MUST be enabled.
- A normal release MUST NOT require pressing “Update” manually in the Packagist UI.

Canonical mechanism for submitted public split repositories:

```text
push monorepo tag vMAJOR.MINOR.PATCH
  -> split-publish.yml pushes the same tag to each allowlisted split repository
  -> Packagist GitHub integration updates the package
```

## Status

- GitHub Release automation for this monorepo is implemented by `.github/workflows/release.yml`.
- Split publishing automation is implemented by `.github/workflows/split-publish.yml`.
- Packagist auto-update is a **policy requirement** for submitted public split repositories.
- Package-specific publication evidence belongs in release notes, epics, issues, or PR records.

## Preconditions (MUST)

Before cutting a release, ensure these files exist in the repo root:

- `CHANGELOG.md`
- `UPGRADE.md`
- `CONTRIBUTING.md`
- `.github/workflows/release.yml`
- `.github/workflows/split-publish.yml`
- `.github/scripts/split-plan.php`
- `.github/scripts/split-plan.schema.md`

## Canonical procedure (MUST)

All commands are run from the **repo root**.

### 1) Prepare the release notes (CHANGELOG)

1. Add a new section to `CHANGELOG.md`:

   - Header: `## vMAJOR.MINOR.PATCH`
   - Content: human-written bullets grouped by area (kernel / tooling / docs / etc.)

2. If the release contains upgrade-relevant changes, update `UPGRADE.md`.

### 2) Commit

Commit the changelog and any related changes:

```bash
git add CHANGELOG.md UPGRADE.md
git commit -m "[vMAJOR.MINOR.PATCH] chore(release): prepare release notes"
```

### 3) Preflight release tag checks

Set the release tag and run all checks before creating or pushing the tag:

```bash
TAG=vMAJOR.MINOR.PATCH
RELEASE_NOTES_FILE="$(mktemp)"
trap 'rm -f "${RELEASE_NOTES_FILE}"' EXIT

git switch main
git pull --ff-only

if ! [[ "${TAG}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Invalid tag format: ${TAG}" >&2
  exit 1
fi

if [[ -n "$(git status --short)" ]]; then
  echo "Working tree is not clean" >&2
  git status --short >&2
  exit 1
fi

if [ "$(git rev-parse HEAD)" != "$(git rev-parse origin/main)" ]; then
  echo "Local main is not at origin/main HEAD" >&2
  echo "Local:  $(git rev-parse HEAD)" >&2
  echo "Origin: $(git rev-parse origin/main)" >&2
  exit 1
fi

if git rev-parse -q --verify "refs/tags/${TAG}" >/dev/null; then
  echo "Local tag already exists: ${TAG}" >&2
  exit 1
fi

if git ls-remote --exit-code --tags origin "refs/tags/${TAG}" >/dev/null 2>&1; then
  echo "Remote tag already exists: ${TAG}" >&2
  exit 1
fi

test -f CHANGELOG.md

awk -v tag="${TAG}" '
  BEGIN { inside=0; found=0 }
  $0 ~ "^##[[:space:]]+" tag "([[:space:]]|$)" { inside=1; found=1; next }
  $0 ~ "^##[[:space:]]+" && inside==1 { exit }
  inside==1 { print }
  END { if (found==0) exit 2 }
' CHANGELOG.md > "${RELEASE_NOTES_FILE}" || {
  echo "Missing CHANGELOG section for ${TAG}" >&2
  echo "Expected heading: ## ${TAG}" >&2
  exit 1
}

if [[ ! -s "${RELEASE_NOTES_FILE}" ]]; then
  echo "Empty release notes for ${TAG} in CHANGELOG.md" >&2
  exit 1
fi

composer ci
```

### 4) Create the tag

Create an **annotated** tag where the message equals the tag:

```bash
git tag -a "${TAG}" -m "${TAG}"
```

### 5) Push the tag

```bash
git push origin "${TAG}"
```

### 6) Automation outcome

After the monorepo tag is pushed:

- GitHub Actions workflow `.github/workflows/release.yml` runs and:
  - validates the tag format;
  - validates required files;
  - extracts notes from `CHANGELOG.md`;
  - creates a GitHub Release for the tag (source-only).

For packages published through Packagist, package publishing is handled by the split publishing workflow:

```text
monorepo tag vMAJOR.MINOR.PATCH
  -> split-publish.yml pushes the package subtree to the public split repository
  -> split-publish.yml pushes the same tag to the public split repository
  -> Packagist GitHub integration updates the package
```

Packagist watches the split repository, not the monorepo.

For the detailed split publishing procedure, see:

- [Packagist split publishing guide](packagist-split-publishing-guide.md)

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
