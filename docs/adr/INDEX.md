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

# ADR Index

This document is the **single navigation entrypoint** for all ADR (Architecture Decision Record) documents.

## Invariants (MUST)

- This index **MUST** be the only canonical navigation entrypoint for ADR docs.
- ADR docs **MUST** be registered here **exactly once**.
- This index **MUST NOT** contain forward references:
  - links **MUST** point only to existing files.
- This index **MUST NOT** contain unstable fields:
  - no dates, no “last updated”, no timestamps.
- Ordering **MUST** be deterministic:
  - sections order is fixed;
  - entries inside a section are sorted by `relative-path` using byte-order `strcmp` (locale-independent).
- Entry format is **single-choice** (one line per file):
  - `- [<title>](<relative-path>) — owner: <package_id|repo> — scope: <tokens>`

## Architecture Decision Records

- [ADR-0001: Module descriptor, manifest reader, and mode preset contracts](./ADR-0001-module-descriptor-manifest-modepreset-ports.md) — owner: core/contracts — scope: contracts,manifest,module,module-descriptor,mode-preset
- [ADR-0002: Config, env, source tracking, and directive invariants](./ADR-0002-config-env-source-tracking-directives-invariants.md) — owner: core/contracts — scope: config,contracts,directives,env,source-tracking
- [ADR-0003: Observability, ErrorDescriptor, health, and profiling ports](./ADR-0003-observability-errordescriptor-health-profiling-ports.md) — owner: core/contracts — scope: contracts,error-descriptor,errors,health,observability,profiling
- [ADR-0004: Foundation json-like runtime values](./ADR-0004-foundation-json-like-runtime-values.md) — owner: core/foundation — scope: foundation,json-like,normalization,runtime,serialization
- [ADR-0005: Routing and HttpApp ports](./ADR-0005-routing-httpapp-ports.md) — owner: core/contracts — scope: contracts,http-app,routing,ports
- [ADR-0006: Reset interface and UoW hooks](./ADR-0006-reset-interface-uow-hooks.md) — owner: core/contracts — scope: contracts,reset,uow,hooks,runtime
- [ADR-0007: Validation ports](./ADR-0007-validation-ports.md) — owner: core/contracts — scope: contracts,errors,validation,ports
- [ADR-0008: Filesystem ports](./ADR-0008-filesystem-ports.md) — owner: core/contracts — scope: contracts,filesystem,ports
- [ADR-0009: Database and migrations ports](./ADR-0009-database-and-migrations-ports.md) — owner: core/contracts — scope: contracts,database,migrations,ports
- [ADR-0011: Rate limit ports](./ADR-0011-ratelimit-ports.md) — owner: core/contracts — scope: contracts,ports,rate-limit,redaction
- [ADR-0012: Mail port](./ADR-0012-mail-port.md) — owner: core/contracts — scope: contracts,mail,ports,redaction
- [ADR-0013: Secrets port](./ADR-0013-secrets-port.md) — owner: core/contracts — scope: contracts,secrets,redaction
- [ADR-0014: DI container, tags, deterministic ordering, and reset orchestration](./ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md) — owner: core/foundation — scope: container,di,ordering,reset,runtime,tags
- [ADR-0015: ContextBag, ContextStore, and CorrelationId](./ADR-0015-context-bag-context-store-correlation-id.md) — owner: core/foundation — scope: context,context-bag,context-store,correlation-id,runtime
- [ADR-0016: Clock, IDs, and Stopwatch](./ADR-0016-clock-ids-stopwatch.md) — owner: core/foundation — scope: clock,duration,ids,runtime,time
- [ADR-0019: Enhanced reset for long-running services](./ADR-0019-enhanced-reset-long-running.md) — owner: core/foundation — scope: long-running,observability,reset,runtime
- [ADR-0020: Kernel runtime UnitOfWork SPI](./ADR-0020-kernel-runtime-uow-spi.md) — owner: core/kernel — scope: contracts,hooks,kernel,runtime,spi,uow
- [ADR-0021: UnitOfWork context shape](./ADR-0021-unit-of-work-context-shape.md) — owner: core/kernel — scope: context,kernel,shape,uow
- [ADR-0022: UnitOfWork result and outcome policy](./ADR-0022-unit-of-work-result-outcome-policy.md) — owner: core/kernel — scope: lifecycle,outcome,result,uow
- [ADR-0023: Kernel Bootstrap Phase A](./ADR-0023-kernel-bootstrap-phase-a.md) — owner: core/kernel — scope: bootstrap,config,env,kernel,phase-a
- [ADR-0024: Kernel module plan resolution](./ADR-0024-kernel-module-plan-resolution.md) — owner: core/kernel — scope: composer,discovery,kernel,module-plan,presets,resolution
- [ADR-0025: Kernel module conflicts and optional-missing policy](./ADR-0025-kernel-conflicts-optional-missing-policy.md) — owner: core/kernel — scope: conflicts,graph,kernel,module-plan,optional-missing,policy
- [ADR-0026: Config Kernel Merge, Directives, and Reserved Namespaces](./ADR-0026-config-kernel-merge-directives-reserved-namespaces.md) — owner: core/kernel — scope: config,directives,kernel,merge,reserved-namespaces
- [ADR-0027: Runtime driver guard](./ADR-0027-runtime-driver-guard.md) — owner: core/kernel — scope: guard,kernel,matrix,runtime,runtime-drivers
- [ADR-0028: Kernel Artifacts, Fingerprint, and Cache Verification](./ADR-0028-kernel-artifacts-fingerprint-cache-verify.md) — owner: core/kernel — scope: artifacts,cache-verify,fingerprint,kernel
- [ADR-0029: Kernel compiled container artifact](./ADR-0029-kernel-container-compile-artifact.md) — owner: core/kernel — scope: artifacts,boot,container,kernel,runtime

## Cross-references

- [SSoT Index](../ssot/INDEX.md) — owner: repo — scope: navigation
- [Roadmap](../roadmap/ROADMAP.md) — owner: repo — scope: navigation
