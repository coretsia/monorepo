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

## v0.4.0

### Added

- Publish-ready `coretsia/core-foundation` package as the first runtime split package with Packagist-safe internal dependency constraints.
- Release-line SSoT at `framework/tools/release/release-line.json` for:
  - current release minor;
  - Composer workspace dev version;
  - public internal package dependency constraint.
- Release-line workspace synchronization commands:
  - `composer release-line:workspace:sync`;
  - `composer release-line:workspace:check`.
- Release-line package public constraint synchronization commands:
  - `composer release-line:public-constraints:sync`;
  - `composer release-line:public-constraints:check`.
- Packagist publish-safety gate:
  - `composer package-publish-safety:gate`.
- Generated Composer path repository `options.versions` for all discovered workspace packages.
- Split publishing allowlist coverage for:
  - `framework/packages/core/foundation` -> `coretsia/core-foundation`.

### Changed

- Managed Composer repository synchronization now derives package path repository versions from release-line `devVersion`.
- Framework workspace internal `coretsia/*` `require-dev` constraints are synchronized to release-line `devVersion`.
- Package `composer.json` internal `coretsia/*` dependency constraints are synchronized to release-line `publicConstraint`.
- `composer setup` now applies release-line workspace and package public constraint synchronization before installs.
- `composer ci` now checks release-line workspace and package public constraint drift before installs.
- `composer gates` now includes the Packagist publish-safety gate.
- Packaging and release documentation now define release-line package policy, Packagist-safe internal constraints, and tag-derived package versions.

### Fixed

- Stabilized workspace Composer repository fixtures and tests so release-line-driven package versions do not require hardcoded `0.4.x-dev` expectations.
- Fixed managed Composer repository synchronization tests to account for release-line metadata and fixture-local release-line data.

## v0.3.0

### Added

- Publish-ready `coretsia/core-dto-attribute` package as the canonical DTO marker attribute split package.
- Publish-ready `coretsia/devtools-internal-toolkit` package as the tooling-only deterministic helper split package for Coretsia devtools.
- Split publishing coverage for:
  - `framework/packages/core/dto-attribute` -> `coretsia/core-dto-attribute`;
  - `framework/packages/devtools/internal-toolkit` -> `coretsia/devtools-internal-toolkit`.

### Changed

- Clarify `coretsia/core-dto-attribute` package metadata and README scope around explicit DTO opt-in only.
- Clarify `coretsia/devtools-internal-toolkit` package metadata and README scope around tooling-only deterministic helpers and forbidden runtime usage.
- Extend split publishing automation beyond `coretsia/core-contracts` to support the new public split repositories.

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
