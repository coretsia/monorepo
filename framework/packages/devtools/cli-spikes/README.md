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

# coretsia/devtools-cli-spikes

Phase 0 package: **Devtools-only command pack** for the `coretsia` CLI.

This package provides deterministic **spike command implementations** (e.g. `coretsia doctor`, `coretsia spike:*`)
without shipping them in production runtime.

## Scope (Phase 0 constraints)

- **Devtools-only by policy**: this package MUST be installed in `require-dev` only.
- **No `core/kernel` dependency** (compile-time forbidden).
- **No duplicated spike logic**:
  - commands are thin adapters;
  - actual spike algorithms remain under `framework/tools/spikes/**`.
- **Output authority is single-choice**:
  - all user-visible output MUST go through `Coretsia\Contracts\Cli\Output\OutputInterface`;
  - commands MUST NOT print to stdout/stderr directly.

## Provided commands

When installed and registered via the preset config, the CLI exposes:

- `coretsia doctor`
- `coretsia spike:fingerprint`
- `coretsia spike:config:debug --key=<dot.key>`
- `coretsia deptrac:graph`
- `coretsia workspace:sync --dry-run`
- `coretsia workspace:sync --apply`

## Registration (single source of truth)

This package ships a **config preset**:

- `config/cli.php`

The Phase 0 CLI base (`coretsia/platform-cli`) loads this preset deterministically when:

- `Composer\InstalledVersions::isInstalled('coretsia/devtools-cli-spikes') === true`

and merges it into the `cli` subtree using the fixed merge order:

1) platform-cli defaults
2) devtools preset (this package)
3) skeleton overrides

The preset file MUST return the **`cli` subtree** (NO repeated root key wrapper).

## Dispatch boundary (tools-only spikes)

Commands in this package MUST NOT implement spike business logic.
They MUST dispatch through a single canonical mechanism:

- `Coretsia\Devtools\CliSpikes\Spikes\SpikesBootstrap`

This bootstrap is responsible for loading the tooling bootstrap:

- `framework/tools/spikes/_support/bootstrap.php`

Determinism + safety rules:

- no directory probing/search;
- paths MUST be derived deterministically from the launcher path;
- no absolute paths MUST be rendered to the user.

## Output model

All commands receive:

- `Coretsia\Contracts\Cli\Input\InputInterface` (raw tokens)
- `Coretsia\Contracts\Cli\Output\OutputInterface` (deterministic safe output)

### Text and JSON output

- Text output MUST end with a single `\n`.
- JSON output MUST be deterministic (stable key ordering for maps, list order preserved).
- Any secret-like values MUST be redacted by output implementation (Phase 0 default is redaction-enabled).

## Errors

Phase 0 error handling is **code-first** and deterministic.

### Spikes errors (tools registry)

Tools-only spikes under `framework/tools/spikes/**` define deterministic codes in:

- `framework/tools/spikes/_support/ErrorCodes.php`

When a tool spike fails deterministically (e.g. throws the tooling deterministic exception),
commands SHOULD forward the spike code and a short safe reason to:

- `OutputInterface::error($code, $message)`

### CLI base errors (platform/cli)

Failures outside spikes (invalid config/preset, invalid command wiring, etc.) are owned by the CLI base:

- `Coretsia\Platform\Cli\Error\ErrorCodes`

This package MUST NOT redefine CLI base codes.

### Exit code policy (cemented)

Phase 0 devtools command pack uses a binary policy:

- `0` success
- `1` any failure (including deterministic spike failures and uncaught exceptions)

## Security / Redaction

**MUST NOT leak**:

- `.env` / `.env.local*` values
- tokens, passwords, cookies, Authorization headers
- raw config dumps (e.g. `composer.json` contents)
- absolute filesystem paths (Windows drive/UNC, `/home/`, `/Users/`)

**Allowed diagnostics** (deterministic + safe):

- repo-relative normalized paths (forward slashes)
- stable codes and short fixed reason tokens
- hashes/lengths instead of raw secret values

## Observability

Phase 0 observability is intentionally minimal:

- commands MAY emit safe diagnostics via `OutputInterface` only;
- no logging/tracing/metrics ports are introduced here;
- any diagnostics MUST remain deterministic and redaction-safe.

## Non-goals (Phase 0)

- plugin system (dynamic discovery) — out of scope
- kernel/container integration — out of scope
- vendor-only install UX guarantees — out of scope
