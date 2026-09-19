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

The current baseline dependency set is:

```text
PHP ^8.4
coretsia/core-kernel
```

`coretsia/core-kernel` owns its own dependency closure, including lower-level Coretsia runtime packages required by Kernel.

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

This operation installs the baseline Coretsia runtime dependency graph into the consuming application's own Composer environment.

The consuming project remains the application root and owns its Composer state, configuration, application code, and runtime data.

`coretsia/framework` only contributes the baseline dependency graph.

## Metapackage boundary

`coretsia/framework` intentionally provides no Composer-installable runtime payload of its own.

Its purpose is to express the baseline dependency composition through Composer.

Runtime implementation remains in the concrete Coretsia packages selected by the distribution dependency graph.

Only packages intentionally promoted into the baseline are direct requirements of this distribution.

The absence of `src/`, `config/`, `resources/`, and `autoload` in this distribution is intentional.

If `coretsia/framework` later becomes the canonical owner of installable runtime files or resources, that change requires an explicit distribution-contract review rather than silently adding implementation ownership to the metapackage.

## Ownership boundaries

`coretsia/framework` composes dependencies; it does not absorb their responsibilities.

Requiring a package through this distribution does not transfer its implementation or runtime responsibilities to `coretsia/framework`; those remain with the concrete package.

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

## Observability

`coretsia/framework` does not implement logging, metrics, tracing, profiling, or other observability behavior.

Observability contracts and implementations belong to their owning Coretsia packages.

Adding or removing a runtime package from this distribution MUST NOT create an alternate observability policy at the framework-distribution level.

## Errors

`coretsia/framework` defines no runtime exception hierarchy and emits no runtime diagnostics of its own.

Runtime failures remain owned by the package that implements the failing behavior.

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

Security-sensitive runtime behavior remains the responsibility of the owning runtime packages.

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
- automatic mode-to-Composer dependency synchronization.

## References

- [Coretsia](https://coretsia.dev/)
- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Packaging Strategy](https://github.com/coretsia/monorepo/tree/main/docs/architecture/PACKAGING.md)
- [Repository Structure](https://github.com/coretsia/monorepo/tree/main/docs/architecture/STRUCTURE.md)
- [Compile-Time Package Dependencies](https://github.com/coretsia/monorepo/tree/main/docs/architecture/DEPENDENCIES.md)
- [Kernel package source](https://github.com/coretsia/monorepo/tree/main/packages/core/kernel)
- [Framework distribution source](https://github.com/coretsia/monorepo/tree/main/packages/framework)
