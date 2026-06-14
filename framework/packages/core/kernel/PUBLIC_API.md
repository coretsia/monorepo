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
- `Coretsia\Kernel\Boot\BootstrapConfig`
- `Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy`
- `Coretsia\Kernel\Boot\BootstrapInput`
- `Coretsia\Kernel\Boot\Exception\BootstrapException`
- `Coretsia\Kernel\Module\Exception\ModuleResolutionException`
- `Coretsia\Kernel\Module\Exception\ModuleErrorCodes`
- `Coretsia\Kernel\Module\KernelModule`
- `Coretsia\Kernel\Module\ModulePlan`
- `Coretsia\Kernel\Module\ModulePlanEntry`
- `Coretsia\Kernel\Module\Warning\ModuleOptionalMissingWarning`
- `Coretsia\Kernel\Provider\KernelServiceProvider`
- `Coretsia\Kernel\Provider\Tags`
- `Coretsia\Kernel\Runtime\Driver\BackgroundDriver`
- `Coretsia\Kernel\Runtime\Driver\HttpDriver`
- `Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard`
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

## Internal implementation helpers

Internal implementation helpers are intentionally not enumerated in this file.

The `kernel-public-api:gate` treats fully-qualified symbols in this file as public API evidence. Therefore internal symbols MUST NOT be listed here as fully-qualified names.

Internal Kernel symbols must instead be protected in source by one of the gate recognized mechanisms:

- an `@internal` docblock marker;
- an `Internal` namespace/path segment.

Bootstrap Phase A implementation helpers such as config resolvers, dotenv loaders, env repository builders, and bootstrap-only override loaders are not public API and must remain marked `@internal` in source.

Config Phase B implementation services such as the config orchestrator, merger, directive processor, validator, explainer, config loaders, namespace guards, and config-specific exceptions are not public API and must remain marked `@internal` in source until a dedicated public config facade or contract is introduced.

Artifact, fingerprint, container compilation, compiled-container runtime boot, and cache verification services are internal implementation services. They may be registered in the container and used by package-owned tooling, but they are not package public API and must remain marked `@internal` until a dedicated public artifact/cache/kernel-ops facade or contract is introduced.

Compiled-container implementation models such as service definitions, parameter bags, and definition graphs are internal Kernel compilation models. They are not DTO marker classes, not transport contracts, and not public package API unless a later dedicated public contract explicitly promotes them.
