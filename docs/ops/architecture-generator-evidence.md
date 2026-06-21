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

# Architecture Generator Idempotence Evidence

Coretsia currently collects early evidence for architecture generator idempotence.

This is not an application benchmark and not a production framework comparison.

## Scope

The dedicated `architecture-evidence` workflow runs an evidence-lite CI step that repeatedly runs:

- `arch:package-index:check`
- `arch:deptrac:check`

After each iteration, CI verifies that tracked generated architecture files do not drift.

## Generated files

The current drift check covers:

- `framework/tools/testing/package-index.php`
- `framework/tools/testing/deptrac.yaml`
- `framework/tools/testing/deptrac.allowlist.yaml`

## Metrics

| Metric      | Meaning                      |
|-------------|------------------------------|
| iteration   | Repeated execution number    |
| duration_ms | Wall-clock command duration  |
| result      | Command result               |
| git diff    | Generated source drift check |

## Current target

The current target is intentionally small:

- all repeated architecture generator checks pass;
- generated files remain unchanged;
- duration is visible in the GitHub Actions summary.

## CI workflow

The current evidence is collected by the dedicated GitHub Actions workflow:

- `architecture-evidence`
- job: `architecture-generator-idempotence / ubuntu / PHP 8.4`

The workflow summary contains the per-iteration command result and `duration_ms` for each repeated architecture generator check.

Workflow runs: [architecture-evidence](https://github.com/coretsia/monorepo/actions/workflows/architecture-evidence.yml)

Historical run summaries are retained according to GitHub Actions retention policy.

## Non-goals

This evidence does not yet measure:

- runtime HTTP behavior;
- application-level performance;
- flaky test rate;
- Windows parity;
- comparison with other frameworks;
- production runtime determinism claims.

## Why this exists

The first measurable deterministic property in Coretsia is generator idempotence.

Given the same repository checkout and installed dependencies, architecture generator checks should produce no source drift across repeated executions.
