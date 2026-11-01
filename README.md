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

<div align="center">

# Coretsia Framework

Coretsia [kɔˈrɛtsjɑ] / [ko-RET-si-ya] — from the Ukrainian word “серцевина” (*core, foundation*)

**A deterministic PHP 8.4+ application framework for systems that need to remain understandable, reproducible, and under explicit control as they evolve.**

*Start minimal. Add capabilities as the application grows. Keep the same foundation.*

</div>

> [!WARNING]
> **Coretsia is pre-release and is not production-ready.**
>
> The current repository is suitable for architecture review, framework development, and experimental evaluation. Stable Micro, Express, Hybrid, and Enterprise application releases are not available yet.

## What is Coretsia?

Coretsia is a modular PHP framework designed for applications that may begin as small APIs, command-line tools, or conventional web applications and later grow into systems with background workers, queues, scheduled workloads, broader integrations, and stricter operational requirements.

The framework is built around a single architectural premise:

> Application capabilities should be composed explicitly and deterministically, rather than emerging from incidental registration order, filesystem discovery, or undocumented bootstrap behavior.

Coretsia therefore treats module composition, generated artifacts, package boundaries, runtime lifecycles, and architecture verification as parts of the framework itself.

The goal is to make framework behavior explainable as an application evolves. Teams should be able to determine which modules are active, where configuration values originate, how runtime services are assembled, and whether generated artifacts represent the intended state.

Coretsia does not eliminate application complexity. It makes framework-owned decisions explicit and inspectable so teams can understand and control that complexity over time.

## Core model

Coretsia's framework composition model is based on explicit inputs:

```text
explicit application target set
+ per-target mode preset / module overrides
        ↓
per-target ModuleSelection
        ↓
release installation catalog
        ↓
ProjectPackagePlan + managed Coretsia root requirements
        ↓
Composer lock/vendor graph
        ↓
fresh installed metadata verification
        ↓
per-target deterministic ModulePlan
        ↓
ordered provider planning and runtime artifacts
        ↓
runtime
```

Runtime module discovery uses Composer installed metadata only. It does not scan package directories or source trees to infer application composition. Physical mode-dependent package requirements are planned separately from an explicit application target set through the Kernel installation catalog and are applied only by explicit dependency synchronization.

## Progressive capability modes

Coretsia defines four canonical framework modes. All four mode presets are represented in Kernel selection policy, but their presence does not imply that every corresponding application capability or release is implemented. The profiles below describe the intended progression of capabilities; currently available runtime packages are documented separately under Implemented today.

### `micro`

A minimal runtime profile for:

- focused APIs;
- small services;
- lightweight CLI workloads;
- applications that need a small active framework surface.

### `express`

A conventional application profile for:

- HTTP and web application workflows;
- routing and validation;
- persistence;
- filesystem and other common application IO concerns.

### `hybrid`

A mixed synchronous and asynchronous profile for:

- background processing;
- queues;
- events;
- scheduled workloads;
- more complex business workflows.

### `enterprise`

An extended platform profile for:

- stronger operational requirements;
- governance and observability;
- broader infrastructure integrations;
- larger and longer-lived systems.

Modes define capability profiles, not application architecture. MVC, DDD, Clean Architecture, Vertical Slice Architecture, or another organizational style remains an application-owned decision, independent of the selected mode.

### Modes, targets, and runtime drivers

Coretsia keeps three concerns separate:

- a **mode preset** selects the active framework capability profile;
- an **application target** identifies an application entrypoint or execution surface, such as `web`, `api`, `console`, or `worker`;
- a **runtime driver** identifies the execution mechanism; the compatibility matrix may define driver identities for integrations that are not yet implemented.

These concepts are related but are not interchangeable. The selected application target determines which validated `moduleOverrides` apply to the preset policy. Effective module selection is represented by `ModuleSelection`; the application target does not change dependency-graph traversal or introduce filesystem-based module discovery.

## How Coretsia differs

Coretsia differentiates through how framework capabilities are composed, inspected, and governed rather than through the size of its feature catalog.

The following mechanisms make framework decisions explicit, reproducible, and verifiable.

### Deterministic module composition

A namespace-owned `ModePreset` and one explicit application target's validated `ResolvedModuleOverrides` produce an immutable target-local `ModuleSelection`. For dependency synchronization, the Kernel resolves each selected target against the release installation catalog, retains per-target closures, and forms one immutable `ProjectPackagePlan` for the shared Composer graph. After Composer, a fresh process resolves each target independently against the installed `ModuleManifest` to produce its deterministic `ModulePlan`.

The plan records:

- the selected application target;
- enabled runtime modules;
- explicitly excluded runtime modules;
- deterministic dependency-first topological order;
- resolved entries for exactly the enabled runtime modules.

The immutable `ModulePlan` is the payload of the existing `module-manifest@1` artifact. Runtime boot hydrates it from the validated artifact without reloading preset policy or rediscovering installed modules.

### Reproducible generated artifacts

Generated framework artifacts use a common versioned envelope and deterministic serialization rules.

Artifacts:

- have explicit schema identities;
- contain deterministic fingerprints;
- do not contain timestamps or absolute paths;
- must be rerun-no-diff;
- are validated by schema and header semantics.

The production determinism contract — including canonical ordering, fingerprint and generation identity, artifact byte reproducibility, runtime verification, Worker semantic determinism, and cross-platform guarantees — is defined in [Coretsia Determinism Contract](docs/architecture/DETERMINISM.md).

### Shared runtime lifecycle

Coretsia uses a format-neutral Unit-of-Work model for runtime operations such as:

- HTTP requests;
- CLI invocations;
- worker jobs;
- queue messages;
- scheduler ticks.

Framework-managed Unit-of-Work-local state is reset after each operation so that participating services do not retain state from previous operations.

### Explicit runtime compatibility

Runtime drivers are selected explicitly and checked against a canonical compatibility matrix.

The Kernel must not infer active runtimes from:

- loaded extensions;
- process names;
- open ports;
- filesystem contents;
- container services;
- reflection.

Runtime conflicts fail deterministically before the runtime entrypoint executes.

### Machine-enforced architecture

Coretsia architecture rules are backed by repository tooling and CI rather than documentation alone.

Enforcement covers package and dependency boundaries, DTO and public API policy, runtime/tooling separation, deterministic generated outputs, security checks, and publishing safety.

## Current status

Development status by capability track:

- **Bootstrap & prototypes — implemented.** Repository development, packaging, and architecture verification infrastructure are in place.
- **Core infrastructure — implemented.** The foundational composition, artifact, lifecycle, and persistent Worker infrastructure is available.
- **Micro application release — active development.** A complete HTTP application entrypoint and the first usable `micro` application release remain in development.
- **Express release — planned.**
- **Hybrid release — planned.**
- **Enterprise extensions — planned.**

## Implemented today

### Core runtime and composition baseline

- contracts, foundation, and Kernel package baseline;
- deterministic mode-preset and module-plan contracts;
- Composer-metadata module discovery;
- explicit multi-target application dependency planning and managed Composer root synchronization;
- package-distributed installation catalog and fresh installed per-target verification;
- module dependency and conflict resolution;
- deterministic topological ordering;
- immutable module-resolution snapshots;
- configuration Kernel and merge policy;
- deterministic artifact generation and fingerprinting;
- cache verification;
- compiled container artifact baseline;
- format-neutral Unit-of-Work shapes and lifecycle;
- runtime context and reset orchestration;
- runtime-driver selection and compatibility guard.

### Command-line interface and dependency synchronization

- tag-first command discovery through enabled runtime modules;
- lazy command resolution and Kernel UnitOfWork execution;
- built-in `list`, `help`, and ultra-early `doctor` commands;
- Kernel operations for module inspection, configuration validation, debugging, compilation, hashing, and cache verification;
- deterministic, configuration-defined sequential CLI workflows;
- structured command input validation and safe output rendering;
- Kernel-owned dependency synchronization with managed Composer root requirements, protected project dependencies, fresh installed verification, and recovery reporting.

The Skeleton project-root `coretsia` launcher exposes `plan`, `review`, and `apply` as explicit adapter operations before normal CLI host boot. These operations are not registered `cli.command` services and are not exposed through the packaged CLI command catalog.

### Logging, errors, redaction, and workers

- PSR-3 logging through `coretsia/platform-logging`, with configurable severity filtering and deterministic JSONL or logfmt records;
- fixed STDERR logging, optional daily UTC file destinations, and a configurable null backend;
- centralized error normalization and CLI exception mapping through `coretsia/platform-errors`;
- shared sensitive-data redaction through `coretsia/platform-redaction`;
- an internal persistent Worker runtime with process supervision, readiness handling, controlled shutdown, and worker recycling;
- Worker generation fencing, process lifecycle coordination, and artifact-based child bootstrap.

### Tooling and architecture governance

- deterministic tooling and CI verification;
- package identity and structure checks;
- DTO policy and consistency checks;
- architecture and dependency-boundary checks;
- managed Composer workspace synchronization;
- lock-drift checks;
- Composer audit;
- secret leakage checks;
- split-package publishing checks;
- release and publishing safety checks;
- architecture generator idempotence verification.

## Explore the current CLI

The Skeleton project-root launcher provides access to the Composer-installed CLI and its own explicit dependency synchronization operations.

Together, these entrypoints support the following framework inspection, verification, and maintenance tasks:

| Developer question                                                            | Available operation |
|-------------------------------------------------------------------------------|---------------------|
| Which runtime modules are enabled for this application target?                | `debug:modules`     |
| Where did a configuration value come from?                                    | `config:debug`      |
| Is the application configuration valid?                                       | `config:validate`   |
| Can the framework configuration and service graph be compiled into artifacts? | `config:compile`    |
| Do the generated artifacts still match the intended framework state?          | `cache:verify`      |
| Which managed Coretsia dependencies would change?                             | Skeleton `plan`     |

For executable CLI examples and the full consumer workflow, see the [Skeleton README](packages/applications/skeleton/README.md). The normative dependency synchronization contract is defined in the [Application Dependency Sync SSoT](docs/ssot/application-dependency-sync.md).

## Current stability limitations

Coretsia does not yet provide:

- long-term backward compatibility guarantees;
- stable upgrade paths;
- a complete out-of-the-box HTTP application experience;
- production-ready FrankenPHP, Swoole, or RoadRunner adapters;
- a mature third-party package ecosystem.

These limitations distinguish the implemented framework infrastructure from a complete application platform. Product-level integration, end-to-end application workflows, and release hardening remain in progress.

## Who should evaluate Coretsia?

Coretsia may be worth evaluating if your team:

- maintains PHP applications expected to evolve over many years;
- needs to understand active modules, configuration provenance, and framework service composition;
- wants dependency changes and generated framework artifacts to be explicit, reviewable, and verifiable;
- is building modular systems where package ownership and predictable composition matter;
- is evaluating lifecycle and state-reset boundaries for long-running PHP workloads;
- wants to explore machine-enforced framework architecture and reproducible builds.

Coretsia is particularly relevant to platform engineers, maintainers of long-lived systems, and teams investigating stronger control over application composition and operational behavior.

## Repository layout

```text
packages/
  framework/
  applications/
    skeleton/
  core/
  platform/
  integrations/
  devtools/

tools/
var/
docs/
```

- `packages/framework/` — public `coretsia/framework` distribution;
- `packages/applications/skeleton/` — public `coretsia/skeleton` application template, including its project-root CLI launcher and canonical `bin/dependency-sync.php` adapter;
- `packages/{core,platform,integrations,devtools}/**` — publishable split Composer packages;
- `tools/**` — repository tooling, generators, and CI support, including installation-catalog generation/checking;
- `var/**` — mutable/generated repository workspace state;
- `docs/ssot/**` — canonical invariants, schemas, ownership, and policies;
- `docs/architecture/**` — architecture guidance that refers to SSoT for normative truth;
- `docs/ops/**` — operational and repository-maintenance documentation.

## Package and release model

Coretsia packages are developed in this monorepo and published as split Composer packages.

Canonical package identity is defined by package metadata.

Layered package source:

```text
path: packages/<layer>/<slug>/
package id: <layer>/<slug>
Composer name: composer.json `name`
```

Special distributions:

```text
packages/framework/              → coretsia/framework
packages/applications/skeleton/ → coretsia/skeleton
```

The current `coretsia/framework` distribution directly requires the baseline Kernel, CLI, and Logging packages:

```text
coretsia/framework
├── coretsia/core-kernel
├── coretsia/platform-cli
└── coretsia/platform-logging
```

Additional Coretsia dependencies, including Foundation, Contracts, Errors, and Redaction, are installed transitively according to package metadata. A Composer-installed package is not automatically an enabled runtime module; effective runtime selection remains target-local and governed by Kernel policy.

All Coretsia packages use one monorepo-wide release train:

```text
vMAJOR.MINOR.PATCH
```

Independent per-package version streams are intentionally not supported.

This keeps Coretsia packages, runtime contracts, generated artifact schemas, documentation, and tooling aligned under one compatibility line.

## Evaluate the monorepo

Requirements:

- PHP 8.4 or later;
- Composer 2.x.

Run commands from the repository root:

```bash
composer setup
composer ci
```

- `composer setup` configures the repository development environment and managed Composer repositories;
- `composer ci` runs the full verification pipeline, including default tests, the dedicated slow test rail, and architecture checks.

This workflow is intended for framework development rather than consumer application management. For the separate consumer workflow, see [Explore the current CLI](#explore-the-current-cli) and the [Skeleton README](packages/applications/skeleton/README.md).

## Canonical documentation

### Start here

- [Application Dependency Sync SSoT](docs/ssot/application-dependency-sync.md)
- [SSoT index](docs/ssot/INDEX.md)
- [Quickstart for repository development](docs/guides/quickstart.md)
- [Developer onboarding checklist](docs/guides/onboarding.md)
- [Dependency graph guide](docs/guides/dependency-graph.md)

### Architecture and operations

- [ADR-0033: Application dependency synchronization](docs/adr/ADR-0033-application-dependency-sync-installation-intent.md)
- [ADR-0034: Tag-first CLI command catalog](docs/adr/ADR-0034-cli-tag-first-command-catalog.md)
- [ADR-0035: CLI composite workflows](docs/adr/ADR-0035-cli-composite-workflows.md)
- [ADR-0036: Errors and exception mapping](docs/adr/ADR-0036-platform-errors-errorhandler-exception-mapper.md)
- [ADR-0037: PSR-3 logging](docs/adr/ADR-0037-platform-logging-psr3.md)
- [Canonical packaging strategy](docs/architecture/PACKAGING.md)
- [Coretsia determinism contract](docs/architecture/DETERMINISM.md)
- [Repository structure](docs/architecture/STRUCTURE.md)
- [Releasing guide](docs/guides/releasing.md)
- [Packagist split publishing guide](docs/guides/packagist-split-publishing-guide.md)
- [Command catalog](docs/guides/commands.md)
- [Git hooks and managed repositories](docs/guides/git-hooks.md)
- [Architecture generator idempotence evidence](docs/ops/architecture-generator-evidence.md)
- [Security policy](SECURITY.md)

### Implemented platform packages

- [Command-line interface](packages/platform/cli/README.md)
- [Logging](packages/platform/logging/README.md)
- [Errors](packages/platform/errors/README.md)
- [Sensitive-data redaction](packages/platform/redaction/README.md)
- [Persistent Worker runtime](packages/platform/worker/README.md)
- [Application Skeleton](packages/applications/skeleton/README.md)

## Contributing and discussions

Coretsia is currently seeking technical review of:

- architecture boundaries;
- consistency between SSoT, implementation, tests, and tooling;
- deterministic behavior;
- package ownership;
- runtime lifecycle;
- unclear documentation;
- unnecessary complexity.

The project is not currently seeking production adoption claims or comparisons positioning Coretsia as a replacement for established frameworks.

Architecture and design discussions are tracked through:

- [Coretsia GitHub Discussions](https://github.com/coretsia/monorepo/discussions)
- [Coretsia organization](https://github.com/coretsia)
- [Coretsia website repository](https://github.com/coretsia/website)

## License

Licensed under the Apache License, Version 2.0.

See:

- [LICENSE](LICENSE)
- [NOTICE](NOTICE)
