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

# Packagist Split Publishing Guide

This guide defines the operational rules for publishing Coretsia monorepo packages as public Composer packages through split GitHub repositories and Packagist.

## Scope

This applies to every package under:

```text
framework/packages/<layer>/<slug>/
```

Canonical identity law:

```text
package path:   framework/packages/<layer>/<slug>/
package_id:     <layer>/<slug>
composer name:  coretsia/<layer>-<slug>
split repo:     github.com/coretsia/<layer>-<slug>
```

Example:

```text
package path:   framework/packages/core/contracts/
package_id:     core/contracts
composer name:  coretsia/core-contracts
split repo:     github.com/coretsia/core-contracts
```

## Core rule

The monorepo is the source of truth.

Public Composer packages are published from split repositories.

Packagist watches each submitted split repository, not the monorepo.

```text
monorepo main/tag
  -> split-publish workflow
  -> allowlisted public split repositories main/tag
  -> Packagist GitHub integration
  -> Composer dev/stable package versions
```

## Automation model

Split publishing is implemented by:

```text
.github/workflows/split-publish.yml
```

Published split packages are selected through the explicit publish allowlist:

```text
.github/split-publish-packages.json
```

The allowlist is intentionally separate from package discovery.

`split-plan.php` discovers every package under `framework/packages/<layer>/<slug>/`, but only packages listed in `.github/split-publish-packages.json` are pushed to public split repositories.

This prevents unfinished or private packages from being published accidentally.

The publish allowlist source of truth is `.github/split-publish-packages.json`.

The file MUST use this shape:

```json
{
  "schemaVersion": "coretsia.splitPublishPackages.v1",
  "packages": [
    {
      "package_id": "<layer>/<slug>"
    }
  ]
}
```

`package_id` MUST match the canonical monorepo package id:

```text
<layer>/<slug>
```

Example:

```json
{
  "schemaVersion": "coretsia.splitPublishPackages.v1",
  "packages": [
    {
      "package_id": "core/contracts"
    },
    {
      "package_id": "core/dto-attribute"
    },
    {
      "package_id": "devtools/internal-toolkit"
    }
  ]
}
```

The allowlist MUST NOT contain deploy-key secret names or any other authentication material.

Authentication is provided by the Coretsia split publisher GitHub App.

The workflow uses:

```text
vars.SPLIT_PUBLISH_APP_CLIENT_ID
secrets.SPLIT_PUBLISH_APP_PRIVATE_KEY
```

The workflow creates a short-lived GitHub App installation token for each target split repository and pushes over HTTPS.

Each allowlisted package MUST also exist in the deterministic split plan generated from the monorepo package tree.

The workflow derives `pathPrefix`, split repository owner, split repository name, Composer package name, layer, and slug from the split plan. The allowlist stores only the package id.

The split publisher GitHub App MUST be installed on every selected split repository that can receive automated pushes.

The monorepo repository Actions allowlist MUST allow:

```text
actions/checkout@v6
actions/upload-artifact@v7
actions/create-github-app-token@v3
shivammathur/setup-php@v2
```

### Main branch sync

When a pull request is squash-merged into monorepo `main`:

```text
push to monorepo main
  -> split-publish.yml runs
  -> each allowlisted package subtree is split
  -> GitHub App installation token is created for each target split repository
  -> each split repository main branch is updated over HTTPS
  -> Packagist sees updated dev-main for each submitted package
```

### Release tag sync

When a monorepo release tag is pushed:

```text
push monorepo tag vMAJOR.MINOR.PATCH
  -> release.yml validates release invariants and creates GitHub Release
  -> split-publish.yml splits each allowlisted package subtree at that tag
  -> GitHub App installation token is created for each target split repository
  -> split-publish.yml pushes main and the same tag to each allowlisted split repository over HTTPS
  -> Packagist exposes the stable Composer version for each submitted package
```

Composer strips the leading `v` from SemVer tags, so Git tag `vMAJOR.MINOR.PATCH` becomes Composer version `MAJOR.MINOR.PATCH`.

## Version model

Version source of truth is the monorepo git tag:

```text
vMAJOR.MINOR.PATCH
```

Per-package independent versions are forbidden.

Inside the monorepo development workspace, local path packages may continue using branch constraints such as:

```json
"coretsia/<layer>-<slug>": "dev-main"
```

This is valid for monorepo-local development because the workspace uses path repositories.

Public installation instructions MUST use stable tag constraints after the package is published.

Replace all angle-bracket placeholders before running the commands.

Template:

```bash
composer require coretsia/<layer>-<slug>:^MAJOR.MINOR
```

Published package dependencies MUST NOT use `dev-main` in public `require` sections. Use SemVer constraints instead:

```json
"coretsia/<dependency-layer>-<dependency-slug>": "^MAJOR.MINOR"
```

## One-time bootstrap for a new split package

A new package needs a one-time publishing bootstrap before it can be released through the normal automated flow.

For a new package:

```text
framework/packages/<layer>/<slug>
```

perform:

```text
1. Verify package identity with split-plan.
2. Create public empty split repository: coretsia/<layer>-<slug>.
3. Add the split repository to the Coretsia split publisher GitHub App selected repositories list.
4. Verify the GitHub App has Contents: Read and write for the selected split repository.
5. Add the package to `.github/split-publish-packages.json`.
6. Merge the allowlist change to monorepo main.
7. Verify the split repository root contains package files and composer.json.
8. Submit the split repository URL to Packagist once.
9. Verify the Packagist package page points to the split repository and receives split repository updates.
10. Release through normal monorepo tag flow.
```

The publish allowlist entry MUST use this shape:

```json
{
  "package_id": "<layer>/<slug>"
}
```

Example:

```json
{
  "package_id": "core/dto-attribute"
}
```

Do not add a deploy key for the split repository.

Do not add a per-package Actions secret to the monorepo.

Do not add a separate per-package workflow job.

`.github/workflows/split-publish.yml` builds its matrix from the allowlist and validates each allowlisted package against the deterministic split plan.

The split repository must contain `composer.json` at its root before submitting it to Packagist.

Do not submit an empty split repository to Packagist.

## GitHub App publisher policy

Split publishing MUST use the Coretsia split publisher GitHub App.

Per-package SSH deploy keys are not part of the canonical split publishing model.

The GitHub App must be configured with repository permission:

```text
Contents: Read and write
```

The GitHub App installation scope MUST be:

```text
Only selected repositories
```

Each public split repository that should receive automated split pushes MUST be selected in the GitHub App installation.

Examples:

```text
coretsia/core-contracts
coretsia/core-dto-attribute
coretsia/devtools-internal-toolkit
```

The monorepo stores the GitHub App authentication material once:

```text
coretsia/monorepo
  -> Settings
  -> Secrets and variables
  -> Actions
```

Required repository variable:

```text
SPLIT_PUBLISH_APP_CLIENT_ID
```

Required repository secret:

```text
SPLIT_PUBLISH_APP_PRIVATE_KEY
```

The private key must be generated from the GitHub App settings page.

Never commit GitHub App private keys.

Never store GitHub App private keys in package directories.

Never add package-specific deploy-key secrets for split publishing.

The workflow uses `actions/create-github-app-token@v3` to create a short-lived installation token for the selected split repository.

The workflow pushes split branches and tags over HTTPS.

Canonical push target shape:

```text
https://github.com/coretsia/<layer>-<slug>.git
```

If the GitHub App is not installed on a split repository, token creation for that repository must fail.

This is intentional. Repository write access is controlled through the GitHub App selected repositories list.

## First release procedure for a new split package

Use this for the first public release of any newly allowlisted split package.

This procedure applies after the package publishing bootstrap is prepared on a feature branch:

```text
1. The split repository exists.
2. The split repository is selected in the Coretsia split publisher GitHub App installation.
3. The GitHub App has Contents: Read and write for the selected split repository.
4. The monorepo has vars.SPLIT_PUBLISH_APP_CLIENT_ID.
5. The monorepo has secrets.SPLIT_PUBLISH_APP_PRIVATE_KEY.
6. The package is listed in `.github/split-publish-packages.json`.
7. Release notes for the target monorepo tag exist in `CHANGELOG.md` under `## vMAJOR.MINOR.PATCH`.
```

Input package identity:

```text
framework/packages/<layer>/<slug>
package_id:    <layer>/<slug>
composer name: coretsia/<layer>-<slug>
split repo:    https://github.com/coretsia/<layer>-<slug>
```

Example:

```text
framework/packages/core/dto-attribute
package_id:    core/dto-attribute
composer name: coretsia/core-dto-attribute
split repo:    https://github.com/coretsia/core-dto-attribute
```

### 1. Merge the allowlist change

After `.github/split-publish-packages.json` is merged to monorepo `main`, verify that `split-publish.yml` ran successfully.

Expected split repository:

```text
https://github.com/coretsia/<layer>-<slug>
```

Expected root files include at minimum:

```text
composer.json
README.md
LICENSE
NOTICE
src/
```

Package tests SHOULD be present when the package owns test coverage:

```text
tests/
```

The split repository root must not contain the monorepo package path:

```text
framework/packages/<layer>/<slug>/
```

Verify package name in root `composer.json`:

```json
{
  "name": "coretsia/<layer>-<slug>"
}
```

For the concrete package, replace `<layer>` and `<slug>` with the package identity values.

If `split-publish.yml` fails while creating the GitHub App token, verify that the split repository is selected in the GitHub App installation.

### 2. Submit package to Packagist once

Submit this repository URL:

```text
https://github.com/coretsia/<layer>-<slug>
```

Verify package name:

```text
coretsia/<layer>-<slug>
```

Verify that the Packagist package page points to the split repository and exposes the expected package metadata.

For a new package, stable versions appear only after the monorepo release tag is pushed and propagated to the split repository.

### 3. Push first release tag

Use the next monorepo-wide release tag.

Example:

```bash
TAG=vMAJOR.MINOR.PATCH

git checkout main
git pull --ff-only

composer ci

git tag -a "${TAG}" -m "${TAG}"
git push origin "${TAG}"
```

### 4. Verify evidence

Verify:

```text
1. release.yml passed.
2. split-publish.yml passed.
3. The split repository contains tag vMAJOR.MINOR.PATCH.
4. Packagist shows version MAJOR.MINOR.PATCH without the leading "v".
5. Packagist update happened without pressing manual "Update".
6. The smoke test passes outside the monorepo.
```

After this evidence exists, the package is considered publicly published.

## Smoke test

Run outside the monorepo.

Replace all angle-bracket placeholders before running the commands.

Do not execute commands containing literal angle-bracket placeholders.

Template:

```bash
mkdir /tmp/coretsia-packagist-smoke
cd /tmp/coretsia-packagist-smoke

composer init \
  --name=coretsia/smoke \
  --no-interaction

composer require coretsia/<layer>-<slug>:^MAJOR.MINOR

php -r "require 'vendor/autoload.php'; echo class_exists('<Package\\PublicClass>') || interface_exists('<Package\\PublicInterface>') || enum_exists('<Package\\PublicEnum>') || trait_exists('<Package\\PublicTrait>') ? 'OK'.PHP_EOL : 'MISSING'.PHP_EOL;"
```

Expected:

```text
OK
```

The checked symbol MUST be owned by the split package and MUST be part of the package public surface.

Do not use a class from another package as the smoke-test symbol.

## Normal release procedure for published packages

Use this after the split repository and Packagist package already exist.

### 1. Prepare release notes

Add a release section to `CHANGELOG.md`:

```md
## vMAJOR.MINOR.PATCH

### Added

- ...
```

The heading must be exactly:

```text
## vMAJOR.MINOR.PATCH
```

### 2. Merge changes into main

Normal development flow:

```text
feature branch
  -> commit
  -> push feature branch
  -> Pull Request
  -> Squash and Merge to main
```

After merge:

```text
split-publish.yml updates each allowlisted split repository main branch
Packagist sees updated dev-main for each submitted package
```

### 3. Ensure main is green

```bash
git checkout main
git pull --ff-only

composer ci
```

### 4. Push release tag

```bash
TAG=vMAJOR.MINOR.PATCH

git tag -a "${TAG}" -m "${TAG}"
git push origin "${TAG}"
```

Expected result:

```text
release.yml:
  validates release invariants
  uploads split-plan evidence artifact
  creates GitHub Release

split-publish.yml:
  splits each allowlisted package subtree
  pushes each split repository main
  pushes the release tag to each split repository

Packagist:
  receives GitHub hooks from submitted split repositories
  exposes Composer stable versions
```

## Failure policy

### GitHub App token creation fails

Do not add a deploy key as a workaround.

Check:

```text
1. The split repository exists.
2. The split repository name matches coretsia/<layer>-<slug>.
3. The split repository is selected in the Coretsia split publisher GitHub App installation.
4. The GitHub App has Contents: Read and write.
5. The monorepo has vars.SPLIT_PUBLISH_APP_CLIENT_ID.
6. The monorepo has secrets.SPLIT_PUBLISH_APP_PRIVATE_KEY.
7. Repository Actions permissions allow actions/create-github-app-token@v3.
```

Fix the GitHub App configuration or Actions allowlist, then rerun the workflow.

### Split main push fails

Do not update Packagist manually as the canonical procedure.

Fix the workflow, rerun it, and verify the split repository received the expected commit.

### Split tag push fails because tag already exists

Do not overwrite published tags.

Investigate whether:

```text
the split tag already points to the correct split commit
or
a release mistake happened
```

If the tag is already public and wrong, treat this as a release incident. Do not retag silently.

### Packagist does not update

Check:

```text
1. The split repository is public.
2. Packagist package URL points to the split repository, not the monorepo.
3. Packagist GitHub integration has access to the coretsia organization.
4. Package page does not show an auto-sync warning.
5. The submitted split repository received a push/tag event.
```

Manual Packagist Update may be used only as troubleshooting evidence, not as the canonical release path.

## Policy summary

```text
- This guide is package-generic; package-specific evidence belongs in release notes, epics, or issue/PR records.
- Monorepo remains the source of truth.
- Split repositories are publish targets.
- Split publishing is allowlist-driven through .github/split-publish-packages.json.
- The publish allowlist stores package_id only.
- Packagist watches split repositories.
- Package discovery does not imply publication; only allowlisted packages are pushed to split repositories.
- Split publishing uses the Coretsia split publisher GitHub App.
- Split repository write access is controlled by the GitHub App selected repositories list.
- Per-package SSH deploy keys are not part of the canonical split publishing model.
- Per-package split deploy-key Actions secrets are forbidden for split publishing.
- GitHub App authentication material is stored once in the monorepo Actions variable/secret pair.
- First Packagist package submission is a one-time bootstrap action.
- Normal package updates/releases must be automatic through GitHub integration.
- Monorepo dev workspace may use dev-main path dependencies.
- Public packages and docs must use stable SemVer constraints.
- Feature branch pushes do not publish packages.
- Squash merge to main updates split main.
- Release tag push creates stable Composer versions.
- A package is considered publicly published only after the split repository tag exists, Packagist exposes the corresponding Composer version, and the package installs successfully outside the monorepo.
```
