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

- [Tag Registry](./tags.md) — owner: repo — scope: di,ownership,registry,tags

## Policies

_Empty for now (Prelude)._

## Shapes and Contracts

_Empty for now (Prelude)._

## Runtime Invariants

_Empty for now (Prelude)._

## Tooling and CI Contracts

_Empty for now (Prelude)._

## Cross-references (non-SSoT)

- [Roadmap](../roadmap/ROADMAP.md) — owner: repo — scope: navigation
