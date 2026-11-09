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

`coretsia/framework` is the public Kernel-only Composer bootstrap metapackage for the Coretsia Framework.

It provides one stable Composer entrypoint for installing the minimal Kernel/Foundation dependency baseline without requiring consuming applications to enumerate internal packages or know monorepo workspace topology.

Scope: Kernel-only Composer bootstrap dependency composition and public framework distribution identity. Mode-specific runtime packages are installed separately through explicit Kernel-owned DependencySync.

This README is a consumer-oriented distribution summary.

## Package identity

- Path: `packages/framework`
- Composer name: `coretsia/framework`
- Composer type: `metapackage`
- Role: Kernel-only Composer bootstrap distribution
- Composer-installable payload: none

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

The corresponding split repository is `coretsia/framework` and receives the same release tag for this distribution.

Per-package independent versions MUST NOT be used.

## Dependency policy

`coretsia/framework` is intentionally dependency-only.

The canonical direct Composer requirements of `coretsia/framework` are exactly:

```text
PHP ^8.4
coretsia/core-kernel
```

Internal Coretsia Composer constraints follow the active monorepo release line. Their exact versions remain defined by the package's `composer.json`.

The initial Coretsia bootstrap dependency graph is:

```text
coretsia/framework
    -> coretsia/core-kernel
        -> coretsia/core-contracts
        -> coretsia/core-foundation
            -> coretsia/core-contracts
```

This graph shows the Coretsia-owned bootstrap closure; Composer also resolves any required external dependencies declared by those packages.

`coretsia/framework` MUST NOT directly require `coretsia/platform-cli`, `coretsia/platform-logging`, preset-specific runtime packages, or a replacement runtime metapackage.

The initial installation is sufficient for the Kernel-owned DependencySync composition boundary and the Skeleton's project-owned `plan`, `review`, and `apply` operations. It does not establish an installed Micro runtime.

`coretsia/framework` MUST retain exactly `php` and `coretsia/core-kernel` as direct Composer requirements. Mode-selected runtime packages MUST NOT be promoted into this bootstrap baseline implicitly.

It SHOULD NOT duplicate transitive Coretsia dependencies merely to make them visible in this manifest.

Internal `coretsia/*` dependency constraints MUST follow the active monorepo release line and MUST NOT be maintained as independent package versions.

The framework distribution MUST NOT depend on:

- `devtools/*` packages;
- repository machinery under `tools/**`;
- repository-only build or release dependencies;
- monorepo-local path repositories;
- application-specific packages;
- optional integrations merely because they exist in the monorepo.

## Distribution role

The canonical consumer operation is:

```bash
composer require coretsia/framework
```

This operation installs the Kernel-only Composer bootstrap graph into the consuming application's own Composer environment. The consuming project remains the application root and owns its Composer state, configuration, application code, and runtime data. Installing this baseline does not install the canonical Micro runtime modules.

The framework metapackage does not implement runtime module selection, configuration resolution, Composer reconciliation, or dependency synchronization.

In a Coretsia Skeleton application, the project-owned launcher exposes the Kernel-only bootstrap operations without requiring an installed `coretsia/platform-cli` package:

```bash
php coretsia plan
php coretsia review
php coretsia apply
```

These operations are separate from the framework metapackage implementation. Their normative behavior is defined in [Application Dependency Sync SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/application-dependency-sync.md).

## Metapackage boundary

`coretsia/framework` intentionally provides no Composer-installable runtime payload of its own.

Its purpose is to express the baseline dependency composition through Composer.

Runtime implementation remains in the concrete Coretsia packages selected by the distribution dependency graph.

The absence of `src/`, `config/`, `resources/`, and `autoload` in this distribution is intentional.

## Ownership boundaries

`coretsia/framework` composes dependencies; it does not absorb their responsibilities.

Requiring a package through this distribution does not transfer implementation or runtime responsibilities to `coretsia/framework`. The metapackage MUST NOT instantiate services, register runtime bindings, or implement runtime backends.

The distribution MUST NOT become:

- a duplicate source package;
- a monolithic copy of Coretsia runtime code;
- an alternate package-discovery mechanism;
- a repository workspace;
- a container for development tooling.

## Application template boundary

`coretsia/framework` and `coretsia/skeleton` have different responsibilities.

```text
coretsia/framework = Kernel-only Composer bootstrap distribution
coretsia/skeleton = create-project application template
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

The `coretsia/framework` requirement is project-owned bootstrap state. The metapackage does not register DependencySync-managed requirements, reconcile Composer roots, or modify consumer installation state.

The normative consumer synchronization contract is defined in [Application Dependency Sync SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/application-dependency-sync.md).

## Observability

`coretsia/framework` does not directly require `coretsia/platform-logging` and does not implement logging, metrics, tracing, profiling, or other observability behavior.

## Errors

`coretsia/framework` defines no runtime exception hierarchy and emits no runtime diagnostics of its own.

Composer dependency-resolution failures remain Composer-level installation concerns and are not converted into Coretsia runtime errors by this distribution.

## Security / Redaction

`coretsia/framework` contains no application secrets, environment values, runtime state, generated artifacts, or user data.

The distribution MUST NOT introduce:

- embedded credentials;
- application environment defaults containing secrets;
- repository-local absolute paths;
- monorepo-local repository declarations;
- runtime diagnostic payloads;
- generated application state.

The framework metapackage does not implement runtime redaction, sensitive-data classification, or security policy evaluation.

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
- [Framework distribution source](https://github.com/coretsia/monorepo/tree/main/packages/framework)
