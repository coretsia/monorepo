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
      "package_id": "<layer>/<slug>",
      "secret_name": "SPLIT_<LAYER>_<SLUG_WITH_HYPHENS_REPLACED_BY_UNDERSCORES>_DEPLOY_KEY"
    }
  ]
}
```

`package_id` MUST match the canonical monorepo package id:

```text
<layer>/<slug>
```

`secret_name` MUST be derived from the package id by:

```text
SPLIT_ + uppercase(<layer>) + "_" + uppercase(<slug with "-" replaced by "_">) + "_DEPLOY_KEY"
```

Example derivation:

```text
package_id:  core/dto-attribute
secret_name: SPLIT_CORE_DTO_ATTRIBUTE_DEPLOY_KEY
```

Each allowlisted package MUST also exist in the deterministic split plan generated from the monorepo package tree.

The workflow derives `pathPrefix`, split repository name, Composer package name, layer, and slug from the split plan. The allowlist stores only the package id and the deploy-key secret name.

### Main branch sync

When a pull request is squash-merged into monorepo `main`:

```text
push to monorepo main
  -> split-publish.yml runs
  -> each allowlisted package subtree is split
  -> each split repository main branch is updated
  -> Packagist sees updated dev-main for each submitted package
```

### Release tag sync

When a monorepo release tag is pushed:

```text
push monorepo tag vMAJOR.MINOR.PATCH
  -> release.yml validates release invariants and creates GitHub Release
  -> split-publish.yml splits each allowlisted package subtree at that tag
  -> split-publish.yml pushes the same tag to each allowlisted split repository
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
3. Add a package-specific deploy key with write access to the split repository.
4. Add the private deploy key as an Actions secret in coretsia/monorepo.
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
  "package_id": "<layer>/<slug>",
  "secret_name": "SPLIT_<LAYER>_<SLUG_WITH_HYPHENS_REPLACED_BY_UNDERSCORES>_DEPLOY_KEY"
}
```

Example:

```json
{
  "package_id": "core/dto-attribute",
  "secret_name": "SPLIT_CORE_DTO_ATTRIBUTE_DEPLOY_KEY"
}
```

Do not add a separate per-package workflow job.

`.github/workflows/split-publish.yml` builds its matrix from the allowlist and validates each allowlisted package against the deterministic split plan.

The split repository must contain `composer.json` at its root before submitting it to Packagist.

Do not submit an empty split repository to Packagist.

## Deploy key policy

Use one deploy key per split repository.

All shell snippets in this section are templates.

Replace `<layer>` and `<slug>` before running the commands.

Do not execute commands containing literal angle-bracket placeholders.

A deploy key is package-specific:

```text
one split repository -> one deploy key pair
```

Do not reuse the same deploy key across multiple split repositories.

Public key:

```text
coretsia/<layer>-<slug> -> Settings -> Deploy keys
```

Required setting:

```text
Allow write access: enabled
```

Private key:

```text
coretsia/monorepo -> Settings -> Secrets and variables -> Actions
```

Secret naming convention:

```text
SPLIT_<LAYER>_<SLUG_WITH_HYPHENS_REPLACED_BY_UNDERSCORES>_DEPLOY_KEY
```

Examples:

```text
package_id:  core/foundation
secret name: SPLIT_CORE_FOUNDATION_DEPLOY_KEY

package_id:  platform/cli
secret name: SPLIT_PLATFORM_CLI_DEPLOY_KEY

package_id:  core/dto-attribute
secret name: SPLIT_CORE_DTO_ATTRIBUTE_DEPLOY_KEY
```

Never commit private keys.

### Creating a deploy key pair

Generate the key pair from a temporary directory.

For a package:

```text
framework/packages/<layer>/<slug>
```

derive:

```text
split repo: coretsia/<layer>-<slug>
key file:   <layer>-<slug>_split_deploy_key
```

Run:

```bash
ssh-keygen -t ed25519 -C "coretsia/<layer>-<slug> split publisher" -f ./<layer>-<slug>_split_deploy_key
```

When prompted for passphrase, press Enter twice.

The key must be passphrase-less because GitHub Actions must use it non-interactively.

This creates:

```text
<layer>-<slug>_split_deploy_key
<layer>-<slug>_split_deploy_key.pub
```

Meaning:

```text
<layer>-<slug>_split_deploy_key      -> private key
<layer>-<slug>_split_deploy_key.pub  -> public key
```

### Copying the public key to the split repository

Copy the public key.

PowerShell:

```powershell
Get-Content -Raw .\<layer>-<slug>_split_deploy_key.pub | Set-Clipboard
```

Git Bash:

```bash
cat ./<layer>-<slug>_split_deploy_key.pub
```

Then add it to the split repository:

```text
coretsia/<layer>-<slug>
  -> Settings
  -> Deploy keys
  -> Add deploy key
```

Fields:

```text
Title: Coretsia monorepo split publisher
Key: contents of <layer>-<slug>_split_deploy_key.pub
Allow write access: enabled
```

The deploy key must show as read/write.

### Copying the private key to the monorepo Actions secret

Copy the private key.

PowerShell:

```powershell
Get-Content -Raw .\<layer>-<slug>_split_deploy_key | Set-Clipboard
```

Git Bash:

```bash
cat ./<layer>-<slug>_split_deploy_key
```

Then add it to the monorepo repository:

```text
coretsia/monorepo
  -> Settings
  -> Secrets and variables
  -> Actions
  -> Secrets
  -> New repository secret
```

Fields:

```text
Name: SPLIT_<LAYER>_<SLUG_WITH_HYPHENS_REPLACED_BY_UNDERSCORES>_DEPLOY_KEY
Secret: full contents of <layer>-<slug>_split_deploy_key
```

The secret belongs to the monorepo because `.github/workflows/split-publish.yml` runs from the monorepo and pushes to the split repository.

### Deleting local key files

After both GitHub settings are saved and verified, delete the local key files.

Git Bash / Linux / WSL:

```bash
rm -f ./<layer>-<slug>_split_deploy_key ./<layer>-<slug>_split_deploy_key.pub
```

PowerShell:

```powershell
Remove-Item .\<layer>-<slug>_split_deploy_key, .\<layer>-<slug>_split_deploy_key.pub -Force
```

Verify that no key files remain in the working tree:

```bash
git status --short
```

There must be no files such as:

```text
<layer>-<slug>_split_deploy_key
<layer>-<slug>_split_deploy_key.pub
```

If a key file was accidentally staged, unstage it and delete it immediately:

```bash
git restore --staged ./<layer>-<slug>_split_deploy_key ./<layer>-<slug>_split_deploy_key.pub
rm -f ./<layer>-<slug>_split_deploy_key ./<layer>-<slug>_split_deploy_key.pub
```

For larger scale, prefer a GitHub App based publisher with fine-grained repository write access over many long-lived deploy keys.

## First release procedure for a new split package

Use this for the first public release of any newly allowlisted split package.

This procedure applies after the package publishing bootstrap is prepared on a feature branch:

```text
1. The split repository exists.
2. The package deploy key is configured.
3. The monorepo Actions secret exists.
4. The package is listed in `.github/split-publish-packages.json`.
5. Release notes for the target monorepo tag exist in `CHANGELOG.md` under `## vMAJOR.MINOR.PATCH`.
```

Input package identity:

```text
framework/packages/<layer>/<slug>
package_id:    <layer>/<slug>
composer name: coretsia/<layer>-<slug>
split repo:    https://github.com/coretsia/<layer>-<slug>
secret name:   SPLIT_<LAYER>_<SLUG_WITH_HYPHENS_REPLACED_BY_UNDERSCORES>_DEPLOY_KEY
```

Example:

```text
framework/packages/core/dto-attribute
package_id:    core/dto-attribute
composer name: coretsia/core-dto-attribute
split repo:    https://github.com/coretsia/core-dto-attribute
secret name:   SPLIT_CORE_DTO_ATTRIBUTE_DEPLOY_KEY
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
- Packagist watches split repositories.
- Package discovery does not imply publication; only allowlisted packages are pushed to split repositories.
- First Packagist package submission is a one-time bootstrap action.
- Normal package updates/releases must be automatic through GitHub integration.
- Monorepo dev workspace may use dev-main path dependencies.
- Public packages and docs must use stable SemVer constraints.
- Feature branch pushes do not publish packages.
- Squash merge to main updates split main.
- Release tag push creates stable Composer versions.
- A package is considered publicly published only after the split repository tag exists, Packagist exposes the corresponding Composer version, and the package installs successfully outside the monorepo.
```
