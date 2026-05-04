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
package_id:    <layer>/<slug>
composer name: coretsia/<layer>-<slug>
split repo:    github.com/coretsia/<layer>-<slug>
```

Example:

```text
framework/packages/core/contracts/
package_id:    core/contracts
composer name: coretsia/core-contracts
split repo:    github.com/coretsia/core-contracts
```

## Core rule

The monorepo is the source of truth.

Public Composer packages are published from split repositories.

Packagist watches the split repository, not the monorepo.

```text
monorepo main/tag
  -> split-publish workflow
  -> public split repository main/tag
  -> Packagist GitHub integration
  -> Composer dev/stable package version
```

## Automation model

Split publishing is implemented by:

```text
.github/workflows/split-publish.yml
```

Current package mapping:

```text
framework/packages/core/contracts -> coretsia/core-contracts
```

### Main branch sync

When a pull request is squash-merged into monorepo `main`:

```text
push to monorepo main
  -> split-publish.yml runs
  -> package subtree is split
  -> split repo main is updated
  -> Packagist sees updated dev-main
```

### Release tag sync

When a monorepo release tag is pushed:

```text
push monorepo tag vMAJOR.MINOR.PATCH
  -> release.yml validates release invariants and creates GitHub Release
  -> split-publish.yml splits package subtree at that tag
  -> split-publish.yml pushes the same tag to the split repository
  -> Packagist exposes the stable Composer version
```

Composer strips the leading `v` from SemVer tags, so Git tag `v0.2.0` becomes Composer version `0.2.0`.

## Version model

Version source of truth is the monorepo git tag:

```text
vMAJOR.MINOR.PATCH
```

Per-package independent versions are forbidden.

Inside the monorepo development workspace, local path packages may continue using branch constraints such as:

```json
"coretsia/core-contracts": "dev-main"
```

This is valid for monorepo-local development because the workspace uses path repositories.

Public installation instructions must use stable tag constraints after the package is published:

```bash
composer require coretsia/core-contracts:^0.2
```

Published package dependencies must not use `dev-main` in public `require` sections. Use SemVer constraints instead:

```json
"coretsia/core-contracts": "^0.2"
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
5. Add a package job or matrix entry to .github/workflows/split-publish.yml.
6. Merge the workflow change to monorepo main.
7. Verify the split repository root contains package files and composer.json.
8. Submit the split repository URL to Packagist once.
9. Verify Packagist GitHub integration / auto-update.
10. Release through normal monorepo tag flow.
```

The split repository must contain `composer.json` at its root before submitting it to Packagist.

Do not submit an empty split repository to Packagist.

## Deploy key policy

Use one deploy key per split repository.

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
SPLIT_<LAYER>_<SLUG>_DEPLOY_KEY
```

Examples:

```text
SPLIT_CORE_CONTRACTS_DEPLOY_KEY
SPLIT_CORE_FOUNDATION_DEPLOY_KEY
SPLIT_PLATFORM_CLI_DEPLOY_KEY
```

Never commit private keys.

### Creating a deploy key pair

Generate the key pair from a temporary directory.

For example, for:

```text
coretsia/core-contracts
```

run:

```bash
ssh-keygen -t ed25519 -C "coretsia/core-contracts split publisher" -f ./core-contracts_split_deploy_key
```

When prompted for passphrase, press Enter twice.

The key must be passphrase-less because GitHub Actions must use it non-interactively.

This creates:

```text
core-contracts_split_deploy_key
core-contracts_split_deploy_key.pub
```

Meaning:

```text
core-contracts_split_deploy_key      -> private key
core-contracts_split_deploy_key.pub  -> public key
```

### Copying the public key to the split repository

Copy the public key.

PowerShell:

```powershell
Get-Content -Raw .\core-contracts_split_deploy_key.pub | Set-Clipboard
```

Git Bash:

```bash
cat ./core-contracts_split_deploy_key.pub
```

Then add it to the split repository:

```text
coretsia/core-contracts
  -> Settings
  -> Deploy keys
  -> Add deploy key
```

Fields:

```text
Title: Coretsia monorepo split publisher
Key: contents of core-contracts_split_deploy_key.pub
Allow write access: enabled
```

The deploy key must show as read/write.

### Copying the private key to the monorepo Actions secret

Copy the private key.

PowerShell:

```powershell
Get-Content -Raw .\core-contracts_split_deploy_key | Set-Clipboard
```

Git Bash:

```bash
cat ./core-contracts_split_deploy_key
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
Name: SPLIT_CORE_CONTRACTS_DEPLOY_KEY
Secret: full contents of core-contracts_split_deploy_key
```

The secret belongs to the monorepo because `.github/workflows/split-publish.yml` runs from the monorepo and pushes to the split repository.

### Deleting local key files

After both GitHub settings are saved and verified, delete the local key files.

Git Bash / Linux / WSL:

```bash
rm -f ./core-contracts_split_deploy_key ./core-contracts_split_deploy_key.pub
```

PowerShell:

```powershell
Remove-Item .\core-contracts_split_deploy_key, .\core-contracts_split_deploy_key.pub -Force
```

Verify that no key files remain in the working tree:

```bash
git status --short
```

There must be no files such as:

```text
core-contracts_split_deploy_key
core-contracts_split_deploy_key.pub
```

If a key file was accidentally staged, unstage it and delete it immediately:

```bash
git restore --staged ./core-contracts_split_deploy_key ./core-contracts_split_deploy_key.pub
rm -f ./core-contracts_split_deploy_key ./core-contracts_split_deploy_key.pub
```

For larger scale, prefer a GitHub App based publisher with fine-grained repository write access over many long-lived deploy keys.

## First package: coretsia/core-contracts

The first package is:

```text
framework/packages/core/contracts -> coretsia/core-contracts
```

One-time bootstrap status:

```text
[x] Public split repository exists: coretsia/core-contracts
[x] Deploy key with write access is configured on coretsia/core-contracts
[x] Monorepo Actions secret exists: SPLIT_CORE_CONTRACTS_DEPLOY_KEY
[x] Packagist OAuth access to the coretsia GitHub organization is granted
[ ] split-publish.yml has run on monorepo main and populated the split repository
[ ] coretsia/core-contracts has been submitted to Packagist
[ ] Packagist auto-update has been verified
[ ] v0.2.0 appears on Packagist without manual Update
```

The Packagist checkbox in epic `0.110.0` must remain open until the final evidence exists.

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
split-publish.yml updates split repository main
Packagist sees updated dev-main
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
  splits package subtree
  pushes split repository main
  pushes release tag to split repository

Packagist:
  receives GitHub hook from split repository
  exposes Composer stable version
```

## First release procedure for coretsia/core-contracts

Use this only for the first public package release.

### 1. Merge split publishing workflow

After `.github/workflows/split-publish.yml` is merged to monorepo `main`, verify that the workflow ran successfully.

Expected split repository:

```text
https://github.com/coretsia/core-contracts
```

Expected root files:

```text
composer.json
README.md
LICENSE
NOTICE
src/
tests/
```

The split repository root must not contain:

```text
framework/packages/core/contracts/
```

Verify package name in root `composer.json`:

```json
{
  "name": "coretsia/core-contracts"
}
```

### 2. Submit package to Packagist once

Submit this repository URL:

```text
https://github.com/coretsia/core-contracts
```

Verify package name:

```text
coretsia/core-contracts
```

Verify Packagist GitHub integration / auto-update.

Do not close the Packagist epic checkbox yet.

### 3. Push first release tag

```bash
TAG=v0.2.0

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
3. coretsia/core-contracts contains tag v0.2.0.
4. Packagist shows version 0.2.0.
5. Packagist update happened without pressing manual "Update".
```

Only after this evidence exists may epic `0.110.0` Packagist checkbox be closed.

## Smoke test

Run outside the monorepo:

```bash
mkdir /tmp/coretsia-packagist-smoke
cd /tmp/coretsia-packagist-smoke

composer init \
  --name=coretsia/smoke \
  --no-interaction

composer require coretsia/core-contracts:^0.2

php -r "require 'vendor/autoload.php'; echo interface_exists('Coretsia\\Contracts\\Runtime\\ResetInterface') ? 'OK'.PHP_EOL : 'MISSING'.PHP_EOL;"
```

Expected:

```text
OK
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
5. The split repository received a push/tag event.
```

Manual Packagist Update may be used only as troubleshooting evidence, not as the canonical release path.

## Policy summary

```text
- Monorepo remains the source of truth.
- Split repositories are publish targets.
- Packagist watches split repositories.
- First Packagist package submission is a one-time bootstrap action.
- Normal package updates/releases must be automatic through GitHub integration.
- Monorepo dev workspace may use dev-main path dependencies.
- Public packages and docs must use stable SemVer constraints.
- Feature branch pushes do not publish packages.
- Squash merge to main updates split main.
- Release tag push creates stable Composer versions.
- Packagist checkbox closes only after public auto-update evidence exists.
```
