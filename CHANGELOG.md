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

## v0.5.0

### Added

- Publish-ready `coretsia/core-kernel` package as the Core Kernel split package for Phase 1 runtime orchestration, boot, module planning, configuration, artifacts, compiled-container boot, and deterministic runtime guards.
- Core Kernel UnitOfWork runtime shapes:
  - `UnitOfWorkContext`;
  - `UnitOfWorkResult`;
  - `UnitOfWorkType`;
  - `Outcome`;
  - deterministic JSON-like context/result export and validation failures.
- Foundation-level JSON-like runtime value normalization through `JsonLikeNormalizer` and `JsonLikeNormalizationException`, reused by Kernel UnitOfWork shape normalization.
- KernelRuntime UnitOfWork SPI and lifecycle orchestration for format-neutral HTTP, CLI, worker, queue, and scheduler runtime adapters.
- Bootstrap Phase A boot services for explicit app target selection, bootstrap preset resolution, dotenv loading, env source precedence, and immutable env repository snapshots.
- Deterministic ModulePlan resolution:
  - Composer installed metadata discovery;
  - framework default mode presets;
  - skeleton mode override support;
  - deterministic module graph resolution;
  - required/optional/disabled module policy;
  - safe module-resolution diagnostics and observability.
- Config Phase B pipeline through `ConfigKernel`, including package defaults, skeleton config, application config, environment overlays, directive processing, rules loading, validation, and safe explain traces.
- Kernel artifact and cache verification pipeline for:
  - `module-manifest.php`;
  - `config.php`;
  - deterministic fingerprint inputs;
  - cache clean/dirty/invalid verification.
- Compiled `container@1` artifact pipeline with descriptor-based container compilation, canonical `container.php` artifact emission, and artifact-only runtime boot through compiled artifacts.
- Runtime driver guard and matrix locks for deterministic HTTP/background driver selection, conflict detection, and ModulePlan compatibility checks.
- Platform worker runtime package with long-running worker lifecycle, worker commands, task factory seams, state persistence, control transport, and KernelRuntime UoW execution boundary.
- AppBuilder boot smoke suite for Core Kernel:
  - micro preset resolves, compiles artifacts, and boots through compiled artifacts;
  - express preset fails deterministically with `CORETSIA_MODULE_REQUIRED_MISSING` until `platform.http` exists;
  - skeleton-only custom preset names such as `worker-only` resolve through skeleton mode files.
- Split publishing coverage for:
  - `framework/packages/core/kernel` -> `coretsia/core-kernel`.

### Changed

- Framework default `express` mode preset now requires `platform.http`, making the HTTP/web runtime cutline explicit before Phase 2 platform HTTP availability.
- Modes SSoT now clarifies:
  - framework canonical preset names: `micro`, `express`, `hybrid`, `enterprise`;
  - owner-defined custom preset names as non-canonical names;
  - custom preset names MUST NOT use canonical framework names;
  - skeleton overrides MAY override framework canonical presets through `skeleton/config/modes/<canonical>.php`;
  - Express boot MUST fail deterministically with `CORETSIA_MODULE_REQUIRED_MISSING` until `platform.http` is present in the installed manifest.
- Runtime and observability boundaries were tightened across contracts, Foundation, Kernel, and worker packages:
  - public `ContextKeys` moved to `core/contracts`;
  - Foundation remains owner of context storage, write validation, reset behavior, and default observability bindings;
  - Kernel owns base UnitOfWork context writes and operation-boundary observability.
- Kernel, Foundation, worker, artifact, config, module-plan, reset, and runtime lifecycle services now consistently use provider-owned observability binding instead of business services depending on nullable observability dependencies or direct Noop implementation knowledge.
- KernelRuntime lifecycle handling was refactored to reduce duplicated control flow while preserving deterministic failure precedence.
- CI workflows were split into focused verification, spike/prototype rails, and architecture generator idempotence evidence workflows.

### Fixed

- Stopwatch start/stop failures are now failure-silent across Kernel and Foundation runtime timing boundaries.
- ConfigKernel, ModulePlanResolver, KernelRuntime, and PriorityResetOrchestrator now route timing through safe wrappers so unavailable timing collapses deterministically to `0` without changing primary failure precedence.
- Reset observability, context diagnostics, correlation id reads, and container diagnostics were hardened to avoid leaking unsafe raw runtime values.
- Container diagnostics now hash suspicious, sensitive, unsafe, path-like, URL-like, SQL-like, token-like, credential-like, control-character, and overlong service ids while preserving readable safe FQCNs and conservative aliases.
- ConfigKernel observability was aligned with the global label allowlist by removing non-allowlisted count attributes while preserving canonical outcome metric labels.
- Release-line-driven workspace and package constraints were stabilized so package publishing checks do not depend on hardcoded dev-version expectations.

### Security

- Kernel, Foundation, and worker diagnostics now consistently prefer deterministic error codes, fixed reason tokens, sanitized context, and safe placeholders over raw paths, raw metadata, previous throwable messages, runtime values, or environment data.
- AppBuilder and boot smoke tests avoid stdout/stderr assertions, full exception message assertions, secrets, and absolute-path assertions.
- Runtime reset, UnitOfWork, config, artifact, module-plan, compiled-container, worker, and observability boundaries were reinforced to keep telemetry failures failure-silent and prevent diagnostics from changing business behavior or lifecycle failure precedence.

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
