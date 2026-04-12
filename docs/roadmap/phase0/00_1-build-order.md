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

# Phase 0 — recommended build order (NOT a dependency graph)

> **This document is guidance only.**  
> It is a **recommended implementation order** for Phase 0 to reduce cycle risk and rework.
>
> **It is NOT a dependency graph and MUST NOT be treated as dependency truth.**  
> Compile-time dependency truth (Phase 0) is defined only in:
> - [`00_2-dependency-table.md`](./00_2-dependency-table.md)

---

## 0) Scope

- **Scope:** Phase 0 only (SPIKES + Phase 0 tooling rails).
- **Goal:** minimize risk by front-loading hard constraints (determinism, boundary rules, gate output policy) before building larger tooling.

---

## 1) How to use this order

- Treat this as a **default execution plan**.
- You MAY parallelize items if:
  - outputs do not overlap,
  - you preserve “green baseline” discipline (`composer ci` stays green),
  - you do not violate the dependency SSoT table.

---

## 2) Recommended implementation order (risk-reducing)

### A) Cement boundaries + deterministic rails first

1. **0.10.0 — Spikes boundary decision (DOC)**  
   Lock the “what is allowed” rule set early.

2. **0.20.0 — Spikes sandbox + CI rails (TOOLING)**  
   Establish deterministic test execution + rerun-no-diff discipline so later epics cannot drift.

### B) Establish deterministic primitives that everything will reuse

3. **0.40.0 — `coretsia/internal-toolkit` (TOOLING)**  
   Single-source deterministic primitives (Path/Slug/Stable JSON law for tools).

4. **0.50.0 — Deterministic file IO policy (TOOLING)**  
   Canonical read/write/hash behavior (EOL normalization, LF-only writes, safe errors).

### C) Enforce the boundaries (gates) before the surface area grows

5. **0.30.0 — Spikes boundary enforcement gate (TOOLING)**  
   Make boundary violations unmergeable as early as possible.

### D) Build the “deterministic core” of Phase 0 artifacts

6. **0.60.0 — Fingerprint determinism prototype (TOOLING)**  
   Deterministic listing + hashing; symlink hard-fail; safe explain policy.

7. **0.70.0 — PayloadNormalizer + StableJsonEncoder (TOOLING)**  
   Canonical json-like normalization (maps sorted, lists preserved, floats forbidden).

8. **0.80.0 — Deptrac generator prototype (TOOLING)**  
   Deterministic graph generation (pure PHP, stable ordering, no exec, safe outputs).

9. **0.90.0 — Two-phase config merge + directives + explain (TOOLING)**  
   Highest blast radius: do it after normalization + IO + determinism rules are locked.

### E) Monorepo workspace SPOF + publishing rails

10. **0.100.0 — Workspace SPOF (package-index + repositories sync) (TOOLING)**  
    Keep workspace management deterministic and CI-enforceable (pre-install drift check).

11. **0.110.0 — Publishing rails (GitHub/Packagist) (TOOLING)**  
    Add release workflow only after the repo is stable and deterministic.

### F) CLI (optional but onboarding-friendly) — after rails are reliable

12. **0.120.0 — CLI ports in `coretsia/contracts` (CONTRACTS)**  
    Small, stable contract surface first.

13. **0.130.0 — Minimal `coretsia` CLI base (prod-safe) (TOOLING)**  
    Canonical launcher, deterministic output discipline.

14. **0.140.0 — `coretsia/cli-spikes` Phase 0 command pack (TOOLING)**  
    A thin dispatcher to run spikes safely (no spike business logic in commands).

---

## 3) Navigation (Phase 0 essentials)

- Dependency truth (compile-time, Phase 0 SSoT):
  - [`00_2-dependency-table.md`](./00_2-dependency-table.md)

- Conceptual mental model (non-authoritative):
  - `docs/guides/dependency-graph.md`

- Canonical Prelude rules snapshot (why these invariants exist):
  - `docs/roadmap/ROADMAP.md`

---

## 4) Non-goals

- This file does **not** enumerate allowed compile-time edges.
- This file does **not** replace any SSoT registry or gate contracts.
