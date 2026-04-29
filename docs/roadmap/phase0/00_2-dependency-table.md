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

# Phase 0 — compile-time dependency table (SSoT)

> **Canonical / single-choice.**  
> This document is the **single source of truth** for **Phase 0 compile-time** dependencies between Coretsia monorepo packages.
>
> **Other docs MAY explain the model**, but they **MUST NOT** claim dependency truth.
> Conceptual guide (non-authoritative): `docs/guides/dependency-graph.md`

---

## 0) Scope

- **Scope:** Phase 0 only.
- **What is defined here:** **compile-time** edges between **framework packages** (`framework/packages/<layer>/<slug>/`).
- **What is NOT defined here:**
  - external vendor dependencies (Symfony, PSR packages, etc.),
  - PHP extensions,
  - runtime wiring / module discovery mechanisms (see §3),
  - build order recommendations (see `00_1-build-order.md`).

---

## 1) Terminology (normative)

- **package_id** — `<layer>/<slug>` (see `docs/architecture/PACKAGING.md`).
- **compile-time dependency** — `A → B` means package `A` **requires** package `B` as a Composer requirement to build/test/run `A`.
- **direct edge only** — the table lists **direct** edges; transitive closure is not repeated unless the direct dependency is real/needed.

---

## 2) Table format (SSoT, parse-friendly) (MUST)

### 2.1. Single-choice representation

The dependency table **MUST** be represented as a Markdown table with **exactly** these columns:

- `package_id` | `depends_on` | `notes`

### 2.2. Cell rules (MUST)

- `package_id`:
  - MUST be `<layer>/<slug>`.
- `depends_on`:
  - MUST be either:
    - `—` (em dash) meaning “no dependencies”, or
    - a comma-separated list of `<layer>/<slug>`.
- `notes`:
  - OPTIONAL (may be empty), and MUST NOT contain unstable data (no timestamps).

### 2.3. Ordering rules (MUST)

- Rows **MUST** be sorted by `package_id` ascending using byte-order `strcmp` (locale-independent).
- `depends_on` entries **MUST** be:
  - unique (no duplicates),
  - sorted ascending using byte-order `strcmp`,
  - separated by `, ` (comma + single space).

---

## 3) Discovery boundaries (MUST)

These invariants are part of Phase 0 dependency law:

1. **Runtime module discovery = Composer metadata only**
  - Runtime MUST rely on installed packages’ Composer metadata (e.g., `composer.json` `extra.*`), not filesystem scanning.

2. **No runtime filesystem scanning**
  - Runtime MUST NOT scan directories (no “discover by walking `framework/packages/**`”, no “scan `vendor/**` for classes”, etc.).

3. **Tooling package index is tooling-only**
  - Any tooling-generated index (e.g., package-index) MUST be treated as **tooling input/output only** and MUST NOT become a runtime input.

---

## 4) Phase 0 baseline dependency table (MUST)

| package_id                | depends_on                                   | notes |
|---------------------------|----------------------------------------------|-------|
| core/contracts            | —                                            |       |
| core/dto-attribute        | —                                            |       |
| core/foundation           | core/contracts                               |       |
| core/kernel               | core/contracts, core/foundation              |       |
| devtools/cli-spikes       | core/contracts, platform/cli                 |       |
| devtools/internal-toolkit | —                                            |       |
| platform/cli              | core/contracts                               |       |
| platform/errors           | core/contracts, core/foundation              |       |
| platform/http             | core/contracts, core/foundation, core/kernel |       |
| platform/logging          | core/contracts, core/foundation              |       |
| platform/metrics          | core/contracts, core/foundation              |       |
| platform/problem-details  | core/contracts, core/foundation              |       |
| platform/tracing          | core/contracts, core/foundation              |       |

---

## 5) Consistency requirements (MUST)

- The table **MUST NOT** declare cycles.
- Every referenced `depends_on` entry **MUST** appear as a `package_id` row in this table (Phase 0 closed world).
- Any change to compile-time edges in Phase 0 **MUST** be expressed by editing this table (no “silent edges”).
