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

- _TBD_

### Changed

- _TBD_

### Deprecated

- _TBD (deprecations follow: warn in N, remove in N+2)_

### Removed

- _TBD_

### Fixed

- _TBD_

### Security

- _TBD_

## v0.2.0

### Added

- Publish-ready `coretsia/core-contracts` package as the first public split package.
- Phase 1 contracts baseline for context, observability, errors, runtime reset/UoW, modules, modes, config/env, routing/http-app, validation, mail, database, filesystem, secrets, profiling, and rate-limit ports.
- Cross-cutting noop contract test coverage for contract-only noop implementations.

### Changed

- Lock HTTP middleware taxonomy and cross-cutting runtime invariants in `docs/ssot/http-middleware-catalog.md`.
- Register the HTTP Middleware Catalog SSoT in `docs/ssot/INDEX.md`.

## v0.1.0

### Added

- Initial monorepo structure:
  - `framework/`, `skeleton/`, `docs/`, `framework/tools/`.
- Roadmap scaffolding (`docs/roadmap/**`) and SSoT index layout (`docs/ssot/**`).
- Phase 0 / Prelude rails baseline:
  - gates,
  - determinism expectations,
  - boundary rules.
- Canonical monorepo packaging law:
  - `framework/packages/<layer>/<slug>/`,
  - composer name `coretsia/<layer>-<slug>`,
  - deterministic namespace mapping.
- Canonical repo entrypoints and “run from repo root” workflow:
  - `composer setup`,
  - `composer test`,
  - `composer ci`.
- Managed Composer repositories policy.
- Lock determinism policy.
- Spikes boundary law.
- SSoT-first development workflow.

### Changed

- Documentation strategy: single-source invariants moved into focused SSoT docs; non-SSoT docs must link to SSoT for truth.

### Security

- Deterministic output and redaction policy for tooling/gates:
  - code-first output,
  - no secrets/PII in diagnostics, logs, artifacts, or docs.
