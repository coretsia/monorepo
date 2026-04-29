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

# Changelog

All notable changes to this monorepo are documented in this file.

The format is based on Keep a Changelog, with a single-choice heading rule: released version sections MUST start with "## vMAJOR.MINOR.PATCH" (no brackets). The unreleased section remains "## [Unreleased]".

> Version source of truth: monorepo git tags `vMAJOR.MINOR.PATCH` at the repository root.  
> Per-package independent versions **MUST NOT** be used.

## [Unreleased]

### Added

- Canonical monorepo packaging law:
  - `framework/packages/<layer>/<slug>/`
  - composer name `coretsia/<layer>-<slug>`
  - deterministic namespace mapping.
- Canonical repo entrypoints and “run from repo root” workflow:
  - `composer setup`, `composer test`, `composer ci`.
- Managed Composer repositories policy:
  - `repositories` blocks are managed solely by `php framework/tools/build/sync_composer_repositories.php`,
  - pre-commit drift check via `--check`.
- Lock determinism policy:
  - lock files committed (root/framework/skeleton),
  - CI uses `composer install`, fails on any lock drift.
- Spikes boundary law:
  - spikes live only under `framework/tools/spikes/**`,
  - no runtime package imports; internal exception only for `coretsia/internal-toolkit`.
- SSoT-first development workflow:
  - invariants, shapes, policies in `docs/ssot/**`,
  - deliverables tracked in `docs/roadmap/**`.

### Changed

- Documentation strategy: single-source invariants moved into focused SSoT docs; non-SSoT docs must link to SSoT for truth.

### Deprecated

- _TBD (deprecations follow: warn in N, remove in N+2)_

### Removed

- _TBD_

### Fixed

- _TBD_

### Security

- Deterministic output & redaction policy for tooling/gates:
  - code-first output,
  - no secrets/PII in diagnostics, logs, artifacts, or docs.

## v0.1.0

### Added

- Initial monorepo structure:
  - `framework/`, `skeleton/`, `docs/`, `framework/tools/`.
- Roadmap scaffolding (`docs/roadmap/**`) and SSoT index layout (`docs/ssot/**`).
- Phase 0 / Prelude rails baseline (gates, determinism expectations, boundary rules).

> Add the release date inside the section body if needed; the heading format is fixed: "## vMAJOR.MINOR.PATCH".
