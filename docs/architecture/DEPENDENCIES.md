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

# Compile-Time Package Dependencies

> Canonical / single-choice.
> This document is the single source of truth for allowed direct compile-time dependencies between Coretsia layered packages.
>
> Other documents MAY explain or summarize the dependency model, but they MUST NOT introduce alternative package-level dependency edges.
>
> Package identity, layered-package location, naming, and publishability are governed by:
>
> - `docs/architecture/PACKAGING.md`
>
> Repository structure and layer-level architectural explanation are provided by:
>
> - `docs/architecture/STRUCTURE.md`
>
> Conceptual guide (non-authoritative):
>
> - `docs/guides/dependency-graph.md`

---

## 0) Scope

This document owns:

- the canonical direct compile-time dependency policy between materialized Coretsia layered packages;
- the machine-readable package dependency matrix consumed by repository architecture tooling;
- consistency requirements between the dependency matrix, internal production Composer requirements, and static architecture analysis.

This document applies only to layered packages at:

```text
packages/<layer>/<slug>/
```

This document does NOT define:

- dependencies of special public distributions:
  - `coretsia/framework`
  - `coretsia/skeleton`
- root developer-workspace dependencies;
- external vendor dependencies;
- PHP extension requirements;
- package-local development/test dependencies declared only in `require-dev`;
- runtime service wiring;
- runtime module discovery;
- package identity, namespace, scaffold, versioning, or publishing rules;
- implementation or build order.

Dependencies of special public distributions remain owned by their distribution manifests and the packaging/distribution contracts that govern them.

---

## 1) Terminology (normative)

- layered package — a package at `packages/<layer>/<slug>/`, as defined by `docs/architecture/PACKAGING.md`.
- materialized layered package — a layered package whose canonical package directory and `composer.json` exist in the repository.
- package_id — the layered package identity `<layer>/<slug>`.
- compile-time dependency — a dependency that permits source code in one layered package to reference code owned by another layered package.
- direct dependency edge — an explicitly allowed package-to-package edge declared by the canonical matrix in this document.
- internal production Composer edge — a `composer.json` `require` entry from one materialized Coretsia layered package to another materialized Coretsia layered package.
- transitive dependency — a package reachable only through one or more intermediate direct dependency edges.

For an edge:

```text
A → B
```

the meaning is:

```text
package A MAY directly depend on package B
```

The absence of an edge means that the direct dependency is forbidden.

---

## 2) Dependency model (MUST)

### 2.1. Direct edges only

The canonical matrix declares direct dependency permissions only.

If:

```text
A → B
B → C
```

then:

```text
A → C
```

is NOT implicitly allowed.

A direct dependency from `A` to `C` requires an explicit `C` entry in `A`'s `depends_on` cell.

Transitive closure MUST NOT be interpreted as permission for undeclared direct package dependencies.

### 2.2. One policy for Composer and source dependencies

The same canonical matrix governs:

- internal production Composer dependencies between layered packages;
- static source-level dependencies between layered package code.

Every internal production Composer edge MUST be explicitly allowed by the source package's `depends_on` entry.

Static architecture analysis MUST NOT allow a source-level package dependency that is absent from the canonical matrix.

An allowed matrix edge does not require the source package to use that dependency. The matrix defines the permitted direct architecture boundary, not a requirement that every permitted edge be exercised.

Edges MUST NOT be added speculatively merely to make future dependencies easier to introduce.

### 2.3. Composer scope

For this dependency policy, internal production Composer edges are derived only from:

```text
composer.json
└── require
```

The following are outside this matrix:

```text
require-dev
```

and external requirements whose Composer package identity does not belong to another matrix-owned Coretsia layered package.

Development and test dependencies MUST NOT be used to widen production package dependency permissions.

### 2.4. No dependency truth from filesystem proximity

Filesystem placement does not create a dependency permission.

Packages located in the same layer, neighboring directories, or the same repository MUST NOT depend on each other unless the direct edge is explicitly allowed by this document.

Monorepo co-location is not an architectural dependency boundary.

---

## 3) Canonical matrix format (SSoT, parse-friendly) (MUST)

### 3.1. Single canonical representation

The dependency policy MUST be represented by exactly one canonical Markdown table under:

```text
## 4) Canonical direct dependency matrix (MUST)
```

The table MUST contain exactly these columns:

```text
package_id | depends_on | notes
```

Repository tooling MAY parse this table directly.

No other Markdown table in this document SHOULD use a package-id-shaped first column in a form that could be confused with the canonical dependency matrix.

### 3.2. `package_id` cell

`package_id`:

- MUST identify one materialized layered package;
- MUST use canonical `<layer>/<slug>` form;
- MUST match the package identity defined by `docs/architecture/PACKAGING.md`;
- MUST appear exactly once in the matrix.

Special distributions MUST NOT receive fabricated layered `package_id` values merely to enter this matrix.

### 3.3. `depends_on` cell

`depends_on` MUST be either:

- `—` (em dash), meaning no direct layered-package dependencies are allowed; or
- a comma-separated list of canonical `<layer>/<slug>` package ids.

Each listed dependency:

- MUST identify another matrix row;
- MUST NOT equal the source `package_id`;
- MUST be unique within the cell;
- represents one allowed direct dependency edge.

### 3.4. `notes` cell

`notes`:

- MAY be empty;
- MAY contain stable architectural clarification;
- MUST NOT alter dependency semantics;
- MUST NOT contain timestamps, generated values, environment-specific values, or other unstable data.

Dependency permissions MUST be represented only by `depends_on`.

### 3.5. Ordering

Rows MUST be sorted by `package_id` ascending using byte-order `strcmp` semantics.

Entries within each non-empty `depends_on` cell MUST be:

- unique;
- sorted ascending using byte-order `strcmp` semantics;
- separated by exactly `, ` (comma + one space).

The canonical empty dependency marker is:

```text
—
```

---

## 4) Canonical direct dependency matrix (MUST)

| package_id                | depends_on                                   | notes |
| ------------------------- | -------------------------------------------- | ----- |
| core/contracts            | —                                            |       |
| core/dto-attribute        | —                                            |       |
| core/foundation           | core/contracts                               |       |
| core/kernel               | core/contracts, core/foundation              |       |
| devtools/internal-toolkit | —                                            |       |
| platform/cli              | core/contracts                               |       |
| platform/worker           | core/contracts, core/foundation, core/kernel |       |

---

## 5) Completeness and consistency requirements (MUST)

### 5.1. Materialized-package coverage

Every materialized layered package MUST have exactly one row in the canonical matrix.

A newly materialized layered package MUST NOT be considered architecture-complete until its row has been added.

Roadmap-only or otherwise non-materialized packages MUST NOT be added merely as speculative future dependency permissions.

Future layers such as `enterprise` enter this matrix when concrete layered packages are materialized and their direct dependency boundaries are defined.

### 5.2. Closed matrix references

Every `depends_on` entry MUST resolve to another `package_id` row in the same canonical matrix.

Dangling dependency targets are forbidden.

### 5.3. No self-dependencies

A package MUST NOT list itself in `depends_on`.

### 5.4. No cycles

The directed graph defined by the canonical matrix MUST be acyclic.

Direct or indirect cycles are forbidden.

Examples of forbidden graphs include:

```text
A → A
```

```text
A → B
B → A
```

and:

```text
A → B
B → C
C → A
```

### 5.5. Composer consistency

For every materialized layered package:

- each internal Coretsia dependency declared in production `composer.json` `require`;
- whose target is another materialized layered package;

MUST correspond to an allowed direct edge in this matrix.

A Composer requirement MUST NOT silently create a new architectural dependency.

If a new internal production Composer dependency is architecturally required, the canonical matrix MUST be changed deliberately as part of the same architectural change.

### 5.6. Static dependency consistency

Source-level static analysis MUST use the canonical matrix as the package dependency permission model.

Code in package `A` MUST NOT directly depend on package `B` unless:

```text
B
```

is present in `A`'s `depends_on` cell.

A transitive dependency MUST NOT authorize a direct source-level dependency.

### 5.7. Layer summaries do not override this matrix

Layer-level dependency descriptions in `docs/architecture/STRUCTURE.md` are architectural summaries.

They MUST remain consistent with this document, but they MUST NOT be used to infer additional package-level edges.

For exact package-to-package dependency permission, this matrix is authoritative.

---

## 6) Tooling contract (MUST)

### 6.1. Architecture tooling

Repository architecture tooling MUST consume this document as the canonical package-level dependency policy.

The canonical Deptrac generator is:

```text
tools/build/deptrac_generate.php
```

It MUST use:

- materialized layered-package metadata discovered from package `composer.json` files;
- the canonical matrix in this document;

to validate and generate the static architecture model.

### 6.2. Required validation behavior

Architecture tooling MUST fail when any of the following occurs:

- a materialized layered package has no canonical matrix row;
- a matrix dependency references a missing row;
- the matrix contains a dependency cycle;
- an internal production Composer edge is not allowed by the matrix;
- generated Deptrac configuration is inconsistent with the canonical matrix;
- generated architecture artifacts are stale relative to their canonical inputs.

Tooling MUST NOT silently widen the dependency graph to make an otherwise forbidden dependency pass.

### 6.3. Package index independence

The generated package index and the canonical dependency matrix are separate concerns.

Package metadata such as:

```text
package identity
Composer name
layer
slug
source path
PSR-4 root
package kind
runtime module metadata
```

is discovered from package manifests and belongs to package-index generation.

The package index MUST NOT use this dependency matrix as the source of package metadata.

Likewise, this dependency matrix MUST NOT be generated from the package index or inferred automatically from existing source-code dependencies.

The architecture policy remains explicit.

### 6.4. Generated artifacts are not SSoT

Generated files such as:

```text
tools/testing/package-index.php
tools/testing/deptrac.yaml
var/arch/**
```

are derived repository-tooling artifacts.

They MUST NOT become an alternative dependency source of truth.

They MUST be reproducible from canonical repository inputs and validated for drift by the architecture rails.

Runtime code MUST NOT consume this document, the tooling package index, Deptrac configuration, or generated architecture-analysis artifacts as runtime configuration.

---

## 7) Changing package dependencies (MUST)

A change that introduces or removes an allowed direct layered-package dependency MUST review all affected architecture surfaces together.

When applicable, the change MUST update:

1. this canonical dependency matrix;
2. the source package `composer.json`;
3. package source code;
4. architecture-generated outputs through the canonical generator;
5. architecture tests or fixtures affected by the dependency boundary.

The matrix MUST be edited intentionally.

Generated architecture files MUST NOT be manually edited as a substitute for changing this SSoT.

A package dependency change is complete only when the canonical architecture checks pass without drift.

---

## 8) Ownership and references

This document owns exact direct compile-time dependency permissions between layered packages.

Related ownership is intentionally separate:

- `docs/architecture/PACKAGING.md`
  - package identity;
  - layered-package location;
  - namespaces;
  - package kinds;
  - publishing/versioning rules.
- `docs/architecture/STRUCTURE.md`
  - repository topology;
  - layer responsibilities;
  - package catalog;
  - conceptual layer-level dependency direction.
- `docs/guides/dependency-graph.md`
  - explanatory dependency model only;
  - MUST NOT override this document.

Any document that presents exact package-level dependency edges MUST either reproduce this matrix without changing its semantics or, preferably, refer directly to this document.
