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

This document is the single navigation entrypoint for all SSoT (Single Source of Truth) documents.

## Invariants (MUST)

- This index MUST be the only canonical navigation entrypoint for SSoT docs.
- SSoT docs MUST be registered here exactly once.
- This index MUST NOT contain forward references:
  - links MUST point only to existing files.
- This index MUST NOT contain unstable fields:
  - no dates, no “last updated”, no timestamps.
- Stable document version metadata is allowed:
  - `ssotVersion` is a deterministic positive integer;
  - current pre-stable SSoT documents use `ssotVersion: 1`;
  - `ssotVersion` is not a date, timestamp, or freshness marker.
- Ordering MUST be deterministic:
  - sections order is fixed;
  - entries inside a section are sorted by `relative-path` using byte-order `strcmp` (locale-independent).
- Entry format is single-choice (one line per file):
  - `- [<title>](<relative-path>) — owner: <package_id|repo> — ssotVersion: <int> — scope: <tokens>`

## Registries

- [Artifact Header and Schema Registry](./artifacts.md) — owner: repo — ssotVersion: 1 — scope: artifacts,determinism,envelope,registry,schema
- [Config Roots Registry](./config-roots.md) — owner: repo — ssotVersion: 1 — scope: config,ownership,registry,roots
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md) — owner: repo — ssotVersion: 1 — scope: labels,metrics-catalog,observability,redaction,registry,spans
- [Tag Registry](./tags.md) — owner: repo — ssotVersion: 1 — scope: di,ownership,registry,tags

## Policies

- [DTO Policy](./dto-policy.md) — owner: repo — ssotVersion: 1 — scope: dto,marker,policy,transport

## Shapes and Contracts

- [Config and env SSoT](./config-and-env.md) — owner: core/contracts — ssotVersion: 1 — scope: config,contracts,directives,env,ruleset,source-tracking
- [Config Directives Examples](./config-directives.md) — owner: core/kernel — ssotVersion: 1 — scope: config,directives,examples,merge,runtime
- [Config Merge Order](./config-merge-order.md) — owner: core/kernel — ssotVersion: 1 — scope: config,kernel,merge,phase-b,precedence
- [Config Precedence Matrix](./config-precedence-matrix.md) — owner: core/kernel — ssotVersion: 1 — scope: config,kernel,matrix,precedence,source-tracking
- [Database Contracts SSoT](./database-contracts.md) — owner: core/contracts — ssotVersion: 1 — scope: contracts,database,ports,redaction
- [ErrorDescriptor SSoT](./error-descriptor.md) — owner: core/contracts — ssotVersion: 1 — scope: contracts,error-descriptor,errors,redaction,shape
- [Errors Boundary SSoT](./errors-boundary.md) — owner: core/contracts — ssotVersion: 1 — scope: boundary,contracts,errors,normalization,runtime
- [Filesystem Contracts SSoT](./filesystem-contracts.md) — owner: core/contracts — ssotVersion: 1 — scope: contracts,filesystem,ports,redaction
- [HTTP Middleware Catalog SSoT](./http-middleware-catalog.md) — owner: platform/http — ssotVersion: 1 — scope: http,middleware,redaction,runtime,taxonomy
- [Mail Contracts SSoT](./mail-contracts.md) — owner: core/contracts — ssotVersion: 1 — scope: contracts,mail,ports,redaction
- [Migrations Contracts SSoT](./migrations-contracts.md) — owner: core/contracts — ssotVersion: 1 — scope: contracts,database,migrations,ports
- [Modes SSoT](./modes.md) — owner: core/contracts — ssotVersion: 1 — scope: contracts,mode-preset,modes,presets
- [Modules and manifests SSoT](./modules-and-manifests.md) — owner: core/contracts — ssotVersion: 1 — scope: contracts,manifest,module,module-descriptor,module-id
- [Observability and Errors SSoT](./observability-and-errors.md) — owner: core/contracts — ssotVersion: 1 — scope: contracts,error-descriptor,errors,observability,redaction
- [Profiling Ports SSoT](./profiling-ports.md) — owner: core/contracts — ssotVersion: 1 — scope: contracts,observability,profiling,redaction,uow
- [Rate Limit Contracts SSoT](./rate-limit-contracts.md) — owner: core/contracts — ssotVersion: 1 — scope: contracts,ports,rate-limit,redaction
- [Routing and HttpApp Contracts SSoT](./routing-and-http-app-contracts.md) — owner: core/contracts — ssotVersion: 1 — scope: contracts,http-app,routing,ports,redaction
- [Secrets Contracts SSoT](./secrets-contracts.md) — owner: core/contracts — ssotVersion: 1 — scope: contracts,redaction,secrets
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md) — owner: core/contracts — ssotVersion: 1 — scope: contracts,reset,uow,hooks,runtime
- [UnitOfWork Outcome Policy SSoT](./uow-outcome-policy.md) — owner: core/kernel — ssotVersion: 1 — scope: lifecycle,mapping,outcome,policy,uow
- [UnitOfWork Shapes SSoT](./uow-shapes.md) — owner: core/kernel — ssotVersion: 1 — scope: context,json-like,result,shape,uow
- [Validation Contracts SSoT](./validation-contracts.md) — owner: core/contracts — ssotVersion: 1 — scope: contracts,errors,redaction,validation

## Runtime Invariants

- [Artifact Generations](./artifact-generations.md) — owner: core/kernel — ssotVersion: 1 — scope: artifacts,atomic,determinism,generations,publication,storage
- [Artifacts and Fingerprint Behavior](./artifacts-and-fingerprint.md) — owner: core/kernel — ssotVersion: 1 — scope: artifacts,determinism,fingerprint,kernel,production
- [Cache Verification Semantics](./cache-verify.md) — owner: core/kernel — ssotVersion: 1 — scope: artifacts,cache,kernel,verification
- [Compiled Container Payload and Artifact-Only Boot Semantics](./compiled-container.md) — owner: core/kernel — ssotVersion: 1 — scope: artifacts,boot,compile,container,kernel,payload,runtime
- [Context Keys SSoT](./context-keys.md) — owner: core/foundation — ssotVersion: 1 — scope: context,keys,registry,redaction,runtime
- [ContextStore lifecycle SSoT](./context-lifecycle.md) — owner: core/foundation — ssotVersion: 1 — scope: context,context-store,lifecycle,reset,runtime,uow
- [Context Store SSoT](./context-store.md) — owner: core/foundation — ssotVersion: 1 — scope: context,context-bag,context-store,correlation-id,reset,runtime
- [DI Tags and Middleware Ordering SSoT](./di-tags-and-middleware-ordering.md) — owner: core/foundation — ssotVersion: 1 — scope: di,discovery,middleware,ordering,runtime,tags
- [Json-like Runtime Values SSoT](./json-like-runtime-values.md) — owner: core/foundation — ssotVersion: 1 — scope: json-like,normalization,runtime,serialization,uow
- [Middleware → ContextKeys map](./middleware-context-keys-map.md) — owner: platform/http — ssotVersion: 1 — scope: context,http,middleware,redaction,reference,runtime
- [Reset Tags SSoT](./reset-tags.md) — owner: core/foundation — ssotVersion: 1 — scope: reset,runtime,stateful,tags,uow
- [Runtime Container Definitions (SSoT)](./runtime-container-definitions.md) — owner: core/foundation — ssotVersion: 1 — scope: container,definitions,di,foundation,runtime
- [Runtime Drivers SSoT](./runtime-drivers.md) — owner: repo — ssotVersion: 1 — scope: background,drivers,http,long-running,matrix,runtime
- [Stateful Services SSoT](./stateful-services.md) — owner: core/foundation — ssotVersion: 1 — scope: reset,runtime,stateful,uow,redaction
- [Time, IDs, and Duration SSoT](./time-ids-and-duration.md) — owner: core/foundation — ssotVersion: 1 — scope: clock,duration,ids,runtime,time

## Tooling and CI Contracts

_Empty for now (Prelude)._

## Cross-references (non-SSoT)

- [ADR Index](../adr/INDEX.md) — owner: repo — scope: navigation
- [Roadmap](../roadmap/ROADMAP.md) — owner: repo — scope: navigation
