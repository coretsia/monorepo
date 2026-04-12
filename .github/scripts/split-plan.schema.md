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

# split-plan.json — schema (normative)

> **Canonical / single-choice (for CI evidence artifacts).**  
> This document defines the only allowed shape and determinism invariants for the `split-plan.json` workflow artifact
> produced by `.github/scripts/split-plan.php`.

## 0) Purpose

`split-plan.json` is a **CI evidence artifact** proving that package discovery is deterministic and consistent with the monorepo packaging law:

- discovery source: `framework/packages/<layer>/<slug>/composer.json`
- composer identity MUST match: `coretsia/<layer>-<slug>`
- split repo identity MUST match: `coretsia/<layer>-<slug>`
- output MUST be byte-stable (rerun-no-diff)

Non-goals:

- This plan does **not** perform repository splitting.
- This plan does **not** contact GitHub/Packagist (no network).
- This plan does **not** mutate the repository.

## 1) Output rules (MUST)

### 1.1 Stable JSON bytes (MUST)

- Output MUST be valid UTF-8 JSON.
- JSON MUST be emitted with:
  - no escaped slashes,
  - no unicode escaping,
  - preserved zero fractions,
  - no trailing whitespace.
- Output MUST end with exactly one `\n`.

### 1.2 Deterministic ordering (MUST)

- Top-level object keys MUST be emitted in the exact order defined in §2.1.
- Each `packages[]` item keys MUST be emitted in the exact order defined in §2.2.
- `packages[]` MUST be sorted lexicographically by `package_id` using `strcmp`.

### 1.3 Strict discovery + validation (MUST)

- The generator MUST scan only:
  - `framework/packages/<layer>/<slug>/composer.json`
- `<layer>` MUST be one of:
  - `core|platform|integrations|enterprise|devtools|presets`
- `<slug>` MUST match:
  - `/\A[a-z0-9][a-z0-9-]*\z/`
- For each package:
  - expected composer name MUST be `coretsia/<layer>-<slug>`
  - `composer.json:name` MUST exist and MUST equal the expected value
- Symlink directories under `framework/packages/**` are forbidden (hard-fail).

## 2) Schema (MUST)

### 2.1 Top-level object

Type: JSON object.

Required keys (in this exact order):

1. `schemaVersion` (string) MUST be `coretsia.splitPlan.v1`
2. `sourceCommit` (string) MUST be `git rev-parse HEAD` from the monorepo root
3. `tag` (string|null)
  - MUST be the release tag in tag workflows
  - MUST be null when not provided (local / non-release context)
4. `packages` (array) list of package entries (see below)

No additional fields are allowed unless `schemaVersion` is bumped.

### 2.2 `packages[]` entry

Type: JSON object.

Required keys (in this exact order):

1. `package_id` (string) = `<layer>/<slug>`
2. `pathPrefix` (string) = `framework/packages/<layer>/<slug>/` (trailing slash required)
3. `splitRepo` (string) = `coretsia/<layer>-<slug>`
4. `composerName` (string) = `coretsia/<layer>-<slug>`

No additional fields are allowed unless `schemaVersion` is bumped.

## 3) CI evidence artifacts (MUST)

Workflow MUST produce (as artifacts; MUST NOT be committed):

- `ci/split-plan.json`
- `ci/split-plan.sha256` containing `sha256(ci/split-plan.json)` as lowercase hex (no extra text)

Rerun-no-diff MUST be proven in a single run by generating the plan twice and comparing bytes (or hash).
