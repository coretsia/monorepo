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
- [ADR-0005: Routing and HttpApp ports](./ADR-0005-routing-httpapp-ports.md) — owner: core/contracts — scope: contracts,http-app,routing,ports
- [ADR-0006: Reset interface and UoW hooks](./ADR-0006-reset-interface-uow-hooks.md) — owner: core/contracts — scope: contracts,reset,uow,hooks,runtime
- [ADR-0007: Validation ports](./ADR-0007-validation-ports.md) — owner: core/contracts — scope: contracts,errors,validation,ports
- [ADR-0008: Filesystem ports](./ADR-0008-filesystem-ports.md) — owner: core/contracts — scope: contracts,filesystem,ports
- [ADR-0009: Database and migrations ports](./ADR-0009-database-and-migrations-ports.md) — owner: core/contracts — scope: contracts,database,migrations,ports
- [ADR-0011: Rate limit ports](./ADR-0011-ratelimit-ports.md) — owner: core/contracts — scope: contracts,ports,rate-limit,redaction

## Cross-references

- [SSoT Index](../ssot/INDEX.md) — owner: repo — scope: navigation
- [Roadmap](../roadmap/ROADMAP.md) — owner: repo — scope: navigation
