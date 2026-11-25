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

# Kernel Public API

This document is the package-local public API evidence file for `core/kernel`.

The `kernel-public-api:gate` uses this file to lock which non-internal kernel symbols are intentionally public.

## Public symbols

- `Coretsia\Kernel\Boot\AppTarget`
- `Coretsia\Kernel\Boot\ArtifactRuntimeBooter`
- `Coretsia\Kernel\Boot\ArtifactRuntimeInput`
- `Coretsia\Kernel\Boot\BootstrapConfig`
- `Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy`
- `Coretsia\Kernel\Boot\BootstrapInput`
- `Coretsia\Kernel\Boot\Exception\ArtifactRuntimeBootException`
- `Coretsia\Kernel\Boot\Exception\BootstrapException`
- `Coretsia\Kernel\DependencySync\DependencySyncExecutionPolicy`
- `Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes`
- `Coretsia\Kernel\DependencySync\Exception\DependencySyncException`
- `Coretsia\Kernel\DependencySync\ProjectApplicationSet`
- `Coretsia\Kernel\DependencySync\ProjectDependencySync`
- `Coretsia\Kernel\DependencySync\ProjectDependencySyncResult`
- `Coretsia\Kernel\DependencySync\ProjectInstallationIntent`
- `Coretsia\Kernel\DependencySync\ProjectPackagePlan`
- `Coretsia\Kernel\Module\Exception\ModuleResolutionException`
- `Coretsia\Kernel\Module\Exception\ModuleErrorCodes`
- `Coretsia\Kernel\Module\KernelModule`
- `Coretsia\Kernel\Module\ModulePlan`
- `Coretsia\Kernel\Module\ModulePlanEntry`
- `Coretsia\Kernel\Ops\KernelOpsHostBooter`
- `Coretsia\Kernel\Ops\KernelOpsHostInput`
- `Coretsia\Kernel\Provider\KernelServiceProvider`
- `Coretsia\Kernel\Runtime\Driver\BackgroundDriver`
- `Coretsia\Kernel\Runtime\Driver\HttpDriver`
- `Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions`
- `Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver`
- `Coretsia\Kernel\Runtime\Driver\RuntimeDrivers`
- `Coretsia\Kernel\Runtime\Outcome`
- `Coretsia\Kernel\Runtime\UnitOfWorkType`
- `Coretsia\Kernel\Runtime\UnitOfWorkContext`
- `Coretsia\Kernel\Runtime\UnitOfWorkResult`
- `Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException`
- `Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException`
- `Coretsia\Kernel\Runtime\Exception\KernelRuntimeException`
- `Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException`
- `Coretsia\Kernel\Runtime\Exception\UnitOfWorkResultInvalidException`
- `Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput`
- `Coretsia\Kernel\Runtime\RuntimePathContext`

## Kernel source operations host

`KernelOpsHostBooter` exposes the public source-host entrypoints:

```php
public function boot(KernelOpsHostInput $input): ContainerInterface
public function bootForTarget(KernelOpsHostInput $input, AppTarget $target): ContainerInterface
```

Both return a PSR-11 `Psr\Container\ContainerInterface` for canonical source operations.

`boot()` delegates to `bootForTarget()` with `AppTarget::Console`. Explicit target-local composition MUST use `bootForTarget()` with the supplied `AppTarget`; the target MUST NOT be inferred from installed modules, process environment, or filesystem discovery.

The source host exposes authoritative configuration, module plan, runtime paths, and runtime diagnostics for the selected target. Configuration and module resolution MUST use the same canonical bootstrap snapshot.

`RuntimeDiagnosticsInput::artifactGenerationLoaded()` returns `false`. Source-host construction MUST NOT require or load a current artifact generation, invoke Kernel operations, or start a UnitOfWork.

Host-boot failures use the canonical contracts-level `KernelOpsFailedException` failure boundary with `REASON_HOST_BOOT_FAILED`.

## Internal implementation helpers

Internal implementation helpers are intentionally not enumerated in this file.

The `kernel-public-api:gate` treats fully-qualified symbols in this file as public API evidence. Therefore internal symbols MUST NOT be listed here as fully-qualified names.

Internal Kernel symbols must instead be protected in source by one of the gate recognized mechanisms:

- an `@internal` docblock marker;
- an `Internal` namespace/path segment.

Bootstrap Phase A implementation helpers such as config resolvers, dotenv loaders, env repository builders, and bootstrap-only override loaders are not public API and must remain marked `@internal` in source.

Config Phase B implementation services such as the config orchestrator, merger, directive processor, validator, explainer, config loaders, namespace guards, and config-specific exceptions are not public API and must remain marked `@internal` in source until a dedicated public config facade or contract is introduced.

Artifact, fingerprint, container compilation, compiled-container runtime boot, and cache verification services are internal implementation services. They may be registered in the container and used by package-owned tooling, but they are not package public API and must remain marked `@internal`. The Kernel Ops API does not expose those lower-level compile-host services. Platform consumers access Kernel operations only through `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface` and the public source-host boot API provided by `Coretsia\Kernel\Ops\KernelOpsHostBooter` and `Coretsia\Kernel\Ops\KernelOpsHostInput`.

Compiled-container implementation models such as service definitions, parameter bags, and definition graphs are internal Kernel compilation models. They are not DTO marker classes, not transport contracts, and not public package API unless a later dedicated public contract explicitly promotes them.
