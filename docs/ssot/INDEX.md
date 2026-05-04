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

# SSoT Index

This document is the **single navigation entrypoint** for all SSoT (Single Source of Truth) documents.

## Invariants (MUST)

- This index **MUST** be the only canonical navigation entrypoint for SSoT docs.
- SSoT docs **MUST** be registered here **exactly once**.
- This index **MUST NOT** contain forward references:
  - links **MUST** point only to existing files.
- This index **MUST NOT** contain unstable fields:
  - no dates, no “last updated”, no timestamps.
- Ordering **MUST** be deterministic:
  - sections order is fixed;
  - entries inside a section are sorted by `relative-path` using byte-order `strcmp` (locale-independent).
- Entry format is **single-choice** (one line per file):
  - `- [<title>](<relative-path>) — owner: <package_id|repo> — scope: <tokens>`

## Registries

- [Artifact Header and Schema Registry](./artifacts.md) — owner: repo — scope: artifacts,determinism,envelope,registry,schema
- [Config Roots Registry](./config-roots.md) — owner: repo — scope: config,ownership,registry,roots
- [Observability Naming and Labels Allowlist](./observability.md) — owner: repo — scope: labels,observability,redaction,registry,spans
- [Tag Registry](./tags.md) — owner: repo — scope: di,ownership,registry,tags

## Policies

- [DTO Policy](./dto-policy.md) — owner: repo — scope: dto,marker,policy,transport

## Shapes and Contracts

- [Config and env SSoT](./config-and-env.md) — owner: core/contracts — scope: config,contracts,directives,env,ruleset,source-tracking
- [Database Contracts SSoT](./database-contracts.md) — owner: core/contracts — scope: contracts,database,ports,redaction
- [ErrorDescriptor SSoT](./error-descriptor.md) — owner: core/contracts — scope: contracts,error-descriptor,errors,redaction,shape
- [Errors Boundary SSoT](./errors-boundary.md) — owner: core/contracts — scope: boundary,contracts,errors,normalization,runtime
- [Filesystem Contracts SSoT](./filesystem-contracts.md) — owner: core/contracts — scope: contracts,filesystem,ports,redaction
- [HTTP Middleware Catalog SSoT](./http-middleware-catalog.md) — owner: platform/http — scope: http,middleware,redaction,runtime,taxonomy
- [Mail Contracts SSoT](./mail-contracts.md) — owner: core/contracts — scope: contracts,mail,ports,redaction
- [Migrations Contracts SSoT](./migrations-contracts.md) — owner: core/contracts — scope: contracts,database,migrations,ports
- [Modes SSoT](./modes.md) — owner: core/contracts — scope: contracts,mode-preset,modes,presets
- [Modules and manifests SSoT](./modules-and-manifests.md) — owner: core/contracts — scope: contracts,manifest,module,module-descriptor,module-id
- [Observability and Errors SSoT](./observability-and-errors.md) — owner: core/contracts — scope: contracts,error-descriptor,errors,observability,redaction
- [Profiling Ports SSoT](./profiling-ports.md) — owner: core/contracts — scope: contracts,observability,profiling,redaction,uow
- [Rate Limit Contracts SSoT](./rate-limit-contracts.md) — owner: core/contracts — scope: contracts,ports,rate-limit,redaction
- [Routing and HttpApp Contracts SSoT](./routing-and-http-app-contracts.md) — owner: core/contracts — scope: contracts,http-app,routing,ports,redaction
- [Secrets Contracts SSoT](./secrets-contracts.md) — owner: core/contracts — scope: contracts,redaction,secrets
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md) — owner: core/contracts — scope: contracts,reset,uow,hooks,runtime
- [Validation Contracts SSoT](./validation-contracts.md) — owner: core/contracts — scope: contracts,errors,redaction,validation

## Runtime Invariants

_Empty for now (Prelude)._

## Tooling and CI Contracts

_Empty for now (Prelude)._

## Cross-references (non-SSoT)

- [ADR Index](../adr/INDEX.md) — owner: repo — scope: navigation
- [Roadmap](../roadmap/ROADMAP.md) — owner: repo — scope: navigation
