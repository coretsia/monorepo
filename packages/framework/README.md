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

# coretsia/framework

`coretsia/framework` is the public baseline runtime dependency distribution for the Coretsia Framework.

It provides one stable Composer entrypoint for installing the baseline Coretsia runtime dependency closure without requiring consuming applications to enumerate baseline packages or know monorepo workspace topology.

Scope: baseline runtime dependency composition and public framework distribution identity.

This README is a consumer-oriented distribution summary.

## Package identity

- Path: `packages/framework`
- Composer name: `coretsia/framework`
- Composer type: `metapackage`
- Role: baseline runtime dependency distribution
- Composer-installable payload: none

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

The corresponding split repository is `coretsia/framework` and receives the same release tag for this distribution.

Per-package independent versions MUST NOT be used.

## Dependency policy

`coretsia/framework` is intentionally dependency-only.

The current baseline Composer requirements declared directly by `coretsia/framework` are:

```text
PHP ^8.4
coretsia/core-kernel
coretsia/platform-cli
coretsia/platform-logging
```

Internal Coretsia Composer dependencies use constraints derived from the active monorepo release line. The exact constraints are declared in the package's `composer.json` and follow the canonical repository release policy.

This README documents dependency ownership, not a release-specific Composer manifest.

The baseline Coretsia dependency graph is:

```text
coretsia/framework
    -> coretsia/core-kernel
    -> coretsia/platform-cli
        -> coretsia/core-kernel
        -> coretsia/platform-errors
            -> coretsia/core-foundation
            -> coretsia/platform-redaction
        -> coretsia/platform-redaction
    -> coretsia/platform-logging
        -> coretsia/core-kernel
        -> coretsia/core-foundation
        -> coretsia/platform-redaction
```

`coretsia/platform-logging` is a direct baseline dependency of the framework metapackage. `coretsia/platform-errors` is installed transitively through CLI; `coretsia/platform-redaction` is required transitively by CLI, Errors, and Logging. Neither Errors nor Redaction is a direct framework requirement. Kernel, CLI, Logging, Errors, and Redaction retain ownership of their own dependency closures, including Coretsia Contracts and Foundation.

`coretsia/framework` SHOULD depend directly only on packages that are intentionally part of the public framework baseline.

It SHOULD NOT duplicate transitive Coretsia dependencies merely to make them visible in this manifest.

Internal `coretsia/*` dependency constraints MUST follow the active monorepo release line and MUST NOT be maintained as independent package versions.

The framework distribution MUST NOT depend on:

- `devtools/*` packages;
- repository machinery under `tools/**`;
- repository-only build or release dependencies;
- monorepo-local path repositories;
- application-specific packages;
- optional integrations merely because they exist in the monorepo.

Optional runtime capabilities remain independently installable Composer packages until they are explicitly promoted into the framework baseline.

## Distribution role

The canonical consumer operation is:

```bash
composer require coretsia/framework
```

This operation installs the baseline Coretsia Composer dependency graph into the consuming application's own Composer environment. The consuming project remains the application root and owns its Composer state, configuration, application code, and runtime data.

Physical Composer installation and Kernel runtime module selection are separate. `coretsia/framework` is not a runtime module, `ModePreset`, `ModuleSelection`, or `ModulePlan`, and it does not perform module selection. For each explicitly selected application target, Kernel resolves the effective preset and target-local module overrides into a `ModuleSelection`, then resolves the installed module dependency closure into that target's `ModulePlan`.

All four canonical presets (`micro`, `express`, `hybrid`, `enterprise`) select `platform.cli` and `platform.logging` as excludable runtime modules, not non-excludable required roots. `micro` is the default preset. Its effective enabled closure includes `core.foundation`, `core.kernel`, `platform.cli`, `platform.errors`, `platform.logging`, and `platform.redaction`; `hybrid` and `enterprise` additionally select `platform.worker`. Composer-installed packages do not automatically enable their runtime modules.

A target-local `moduleOverrides.exclude` entry for `platform.logging` removes the logging provider only from that target's runtime `ModulePlan`, retaining the installed Composer package and Foundation `NoopLogger` binding. `logging.driver='null'` is a separate, string-valued setting: it retains the selected module but returns Foundation `NoopLogger` after full configuration validation. A physically missing selected logging module or excluded required `platform.redaction` dependency fails through Kernel module planning; neither is an implicit noop fallback.

`coretsia/framework` only contributes the baseline dependency graph. Additional mode-required Coretsia packages are planned, reviewed, and reconciled through explicit Kernel DependencySync `plan` / `review` / `apply` operations for selected targets. The framework metapackage does not inspect application mode policy, modify the consumer's Composer manifest, or trigger dependency synchronization during application boot.

## Metapackage boundary

`coretsia/framework` intentionally provides no Composer-installable runtime payload of its own.

Its purpose is to express the baseline dependency composition through Composer.

Runtime implementation remains in the concrete Coretsia packages selected by the distribution dependency graph.

Only packages intentionally promoted into the baseline are direct requirements of this distribution.

The absence of `src/`, `config/`, `resources/`, and `autoload` in this distribution is intentional.

If `coretsia/framework` later becomes the canonical owner of installable runtime files or resources, that change requires an explicit distribution-contract review rather than silently adding implementation ownership to the metapackage.

## Ownership boundaries

`coretsia/framework` composes dependencies; it does not absorb their responsibilities.

Requiring a package through this distribution does not transfer its implementation or runtime responsibilities to `coretsia/framework`; those remain with the concrete package. In particular, the framework metapackage MUST NOT instantiate logging services, register `LoggerInterface` bindings, configure logging destinations, or implement a logging backend.

The distribution MUST NOT become:

- a duplicate source package;
- a monolithic copy of Coretsia runtime code;
- an alternate package-discovery mechanism;
- a repository workspace;
- a container for development tooling.

## Application template boundary

`coretsia/framework` and `coretsia/skeleton` have different responsibilities.

```text
coretsia/framework
    = runtime dependency distribution

coretsia/skeleton
    = create-project application template
```

The framework distribution MUST NOT contain application-template paths such as:

```text
apps/
var/
.env.example
application config overrides
```

Those belong to `coretsia/skeleton` or to the consuming application after project creation.

Likewise, `coretsia/skeleton` MUST NOT contain copies of framework runtime implementation.

## DependencySync boundary

The `coretsia/framework` root installed by the skeleton is project-owned baseline state. It is not claimed by the DependencySync managed-root marker.

Mode-dependent physical package requirements are derived by Kernel DependencySync from explicit application targets and the package-distributed installation catalog. DependencySync may write/remove only roots it previously marked as managed.

Explicit non-Coretsia roots remain protected project state. A Coretsia synchronization cannot silently change their root constraints, locked identities, or installed identities.

See `docs/ssot/application-dependency-sync.md` in the monorepo for the normative consumer workflow.

## Observability

`coretsia/framework` distributes `coretsia/platform-logging` by default but does not implement logging, metrics, tracing, profiling, or other observability behavior. Observability contracts and implementations remain with their owning Coretsia packages.

When `platform.logging` is selected and `logging.driver='stream'` (the default), the Logging package supplies `Psr\Log\LoggerInterface` through `StreamLogger` with the fixed internal `php://stderr` destination. The optional `logging.driver='file'` uses the same logger for automatic daily UTC local files; `logging.file.path`, `logging.file.name`, and output format are configured and validated by `platform/logging`, never by the framework metapackage. The emitted filename is `{app_target}-{file.name}-{YYYY-MM-DD}.{format}`. File retention, deletion, archival, and disk quotas remain deployment responsibilities.

Excluding the logging module or selecting the string driver `null` retains the Foundation `NoopLogger` binding. These are runtime policies, not changes to the installed dependency graph. Adding or removing a runtime dependency from the distribution MUST NOT create an alternate observability policy at the framework-distribution level.

## Errors

`coretsia/framework` defines no runtime exception hierarchy and emits no runtime diagnostics of its own.

Runtime failures remain owned by the package that implements the failing behavior. Kernel owns missing selected-module and required-dependency failures; `platform/logging` owns its validated configuration, best-effort stream/file emission, and internal write-failure isolation. Failure to initialize the fixed STDERR destination returns Foundation `NoopLogger`, while later stream/file write failures omit the affected records without recursive diagnostics. The distribution does not convert module-composition errors into `NoopLogger` fallbacks.

Composer dependency-resolution failures remain Composer-level installation concerns and MUST NOT be represented as Coretsia runtime errors by this distribution.

## Security / Redaction

`coretsia/framework` contains no application secrets, environment values, runtime state, generated artifacts, or user data.

The distribution MUST NOT introduce:

- embedded credentials;
- application environment defaults containing secrets;
- repository-local absolute paths;
- monorepo-local repository declarations;
- runtime diagnostic payloads;
- generated application state.

Security-sensitive runtime behavior remains the responsibility of the owning runtime packages. `platform/redaction` provides the shared redaction and sensitive-key classification implementations required by Logging; `platform/logging` owns its mandatory whole-record redaction use and sink admission policy. The framework metapackage neither exposes a redaction-disable configuration nor performs runtime redaction.

## Non-goals

This distribution does not provide:

- an application skeleton;
- runtime implementation code of its own;
- a monorepo development workspace;
- repository scripts or gates;
- PHPUnit, PHPStan, ECS, or architecture tooling;
- package scaffolding;
- split-publishing automation;
- local Composer path repositories;
- generated artifacts;
- application configuration;
- environment configuration;
- optional integrations by default;
- implicit or runtime-boot Composer dependency synchronization.

## References

- [Coretsia](https://coretsia.dev/)
- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Packaging Strategy](https://github.com/coretsia/monorepo/tree/main/docs/architecture/PACKAGING.md)
- [Repository Structure](https://github.com/coretsia/monorepo/tree/main/docs/architecture/STRUCTURE.md)
- [Compile-Time Package Dependencies](https://github.com/coretsia/monorepo/tree/main/docs/architecture/DEPENDENCIES.md)
- [Kernel package source](https://github.com/coretsia/monorepo/tree/main/packages/core/kernel)
- [Logging package source](https://github.com/coretsia/monorepo/tree/main/packages/platform/logging)
- [Logging Policy SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/logging-policy.md)
- [Framework distribution source](https://github.com/coretsia/monorepo/tree/main/packages/framework)
