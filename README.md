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

**A deterministic PHP 8.4+ application framework with preset-driven module composition, reproducible artifacts, explicit runtime lifecycles, and machine-enforced package boundaries and framework architecture rules.**

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

## Core model

Coretsia's framework composition model is based on explicit inputs:

```text
selected mode preset
+ Composer installed package metadata
+ canonical Coretsia module metadata
        ↓
deterministic ModulePlan
        ↓
ordered provider planning
        ↓
compiled config, container, and module artifacts
        ↓
runtime
```

Runtime module discovery uses Composer metadata only. It does not scan package directories or source trees to infer application composition.

## Progressive capability modes

Coretsia defines four canonical framework modes:

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

Modes are capability profiles. They do not prescribe MVC, DDD, Clean Architecture, Vertical Slice Architecture, or another application architecture style.

An application may use a simple architecture in a broader mode or a highly structured architecture in `micro`. The application architecture remains an owner decision.

### Modes, targets, and runtime drivers

Coretsia keeps three concerns separate:

- a **mode preset** selects the active framework capability profile;
- an **application target** identifies an application entrypoint or execution surface, such as `web`, `api`, `console`, or `worker`;
- a **runtime driver** selects the execution mechanism, such as classic PHP, FrankenPHP, Swoole, or RoadRunner.

These concepts are related but are not interchangeable. An application target does not introduce a separate module-selection mechanism: module composition remains preset-driven.

## How Coretsia differs

Coretsia does not attempt to differentiate itself merely by providing a container, modules, middleware, queues, workers, or runtime adapters. Modern PHP frameworks already provide mature versions of those capabilities.

Its intended difference is the way those capabilities are composed and governed.

### Deterministic module composition

A mode preset and the installed Composer metadata resolve into one explicit `ModulePlan`.

The plan records:

- enabled modules;
- disabled modules;
- optional modules that are not installed;
- deterministic dependency order;
- deterministic warnings and exported diagnostics.

### Reproducible generated artifacts

Generated framework artifacts use a common versioned envelope and deterministic serialization rules.

Artifacts:

- have explicit schema identities;
- contain deterministic fingerprints;
- do not contain timestamps or absolute paths;
- must be rerun-no-diff;
- are validated by schema and header semantics.

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

- **Bootstrap & prototypes — implemented.** Repository bootstrap, packaging foundations, development tooling, CI/architecture verification, deterministic tooling primitives, prototypes, and initial CLI foundations are in place.
- **Core — implemented.** Contracts, Foundation, Kernel, the baseline persistent Worker runtime, and the supporting composition and runtime infrastructure are implemented.
- **Micro release track — active development.** Current work focuses on mode infrastructure, production CLI integration, target-aware application entrypoints, and the remaining runtime infrastructure required for the first complete `micro` application release.
- **Express release — planned.**
- **Hybrid release — planned.**
- **Enterprise extensions — planned.**

## Implemented today

### Core runtime and composition baseline

- contracts, foundation, and Kernel package baseline;
- deterministic mode-preset and module-plan contracts;
- Composer-metadata module discovery;
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

## Current stability limitations

Coretsia does not yet provide:

- long-term backward compatibility guarantees;
- stable upgrade paths;
- a mature third-party package ecosystem.

Product-level integration and release hardening remain in progress.

## Design priorities

Coretsia prioritizes:

1. a small initial application surface that can grow by adding capabilities without replacing the application foundation;
2. deterministic module and artifact composition;
3. consistent lifecycle behavior across classic and long-running runtimes;
4. enforceable architecture boundaries without unnecessary application-level ceremony.

## Who should evaluate Coretsia?

Coretsia may currently be relevant to:

- framework and platform engineers;
- maintainers of long-lived PHP systems;
- teams interested in reproducible framework artifacts;
- developers working with modular monoliths;
- engineers evaluating long-running PHP lifecycle safety;
- contributors interested in architecture tooling and enforceable package boundaries.

Coretsia should not currently be selected for a production application that requires a stable framework, broad ecosystem support, or proven long-term upgrade compatibility.

## Repository layout

```text
framework/
  packages/
    core/
    platform/
    integrations/
    enterprise/
    devtools/
    presets/
  tools/

skeleton/
docs/
```

- `framework/packages/<layer>/<slug>/` — publishable framework packages;
- `framework/tools/**` — repository tooling, generators, and CI support;
- `skeleton/**` — local application workspace, fixtures, entrypoints, E2E tests, and runtime caches;
- `docs/ssot/**` — canonical invariants, schemas, ownership, and policies;
- `docs/architecture/**` — architecture guidance that refers to SSoT for normative truth;
- `docs/ops/**` — operational and repository-maintenance documentation.

Canonical package layers are:

```text
core
platform
integrations
enterprise
devtools
presets
```

## Package and release model

Framework packages are developed in this monorepo and published as split Composer packages.

Canonical package identity:

```text
path: framework/packages/<layer>/<slug>/
package id: <layer>/<slug>
Composer name: coretsia/<layer>-<slug>
```

All Coretsia packages use one framework release train:

```text
vMAJOR.MINOR.PATCH
```

Independent per-package version streams are intentionally not supported.

This keeps framework packages, runtime contracts, generated artifact schemas, documentation, and tooling aligned under one compatibility line.

See:

- [Canonical packaging strategy](docs/architecture/PACKAGING.md)
- [Repository structure](docs/architecture/STRUCTURE.md)
- [Releasing guide](docs/guides/releasing.md)
- [Packagist split publishing guide](docs/guides/packagist-split-publishing-guide.md)

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
- `composer ci` runs the full verification pipeline, including the test suite and architecture checks.

This is a framework-development workflow, not yet an end-user application installation flow.

## Canonical documentation

### Start here

- [SSoT index](docs/ssot/INDEX.md)
- [Quickstart for repository development](docs/guides/quickstart.md)
- [Developer onboarding checklist](docs/guides/onboarding.md)
- [Dependency graph guide](docs/guides/dependency-graph.md)

### Architecture and operations

- [Canonical packaging strategy](docs/architecture/PACKAGING.md)
- [Repository structure](docs/architecture/STRUCTURE.md)
- [Command catalog](docs/guides/commands.md)
- [Git hooks and managed repositories](docs/guides/git-hooks.md)
- [Architecture generator idempotence evidence](docs/ops/architecture-generator-evidence.md)
- [Security policy](SECURITY.md)

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
