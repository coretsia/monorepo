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

# Phase 0 — Spikes boundary (tools-only, single-choice)

> **Canonical / single-choice.**\
> This document fixes the **single** boundary for Phase 0 spikes.\
> Any other documents/implementations **MUST** conform to this file and **MUST NOT** introduce alternative “either/or” rules.

---

## 0) Scope

- **Scope:** Phase 0 spikes / prototypes and Phase 0 tooling rails.
- **Goal:** strictly cement that spikes are **tools-only**, and they **cannot** “leak” into runtime packages.
- **Non-goals:**
  - this document **does not** introduce runtime behavior;
  - this document **does not** introduce plugin/extensibility frameworks;
  - this document **does not** change the Phase 0 dependency SSoT (`00_2-dependency-table.md`);
  - this document **MUST NOT** require, introduce, or imply a compile-time dependency on `core/kernel` (unless this is explicitly stated in an epic / belongs to the scope of another epic).

---

## 1) Canonical references

### 1.1 Packaging law (MUST)

All statements about “package vs tooling” **MUST** rely on the monorepo packaging law:

- `docs/architecture/PACKAGING.md`

Key points (for this boundary):

- `framework/packages/**` — **publishable units** (runtime/devtools packages).
- `framework/tools/**` — **non-publishable tooling** (gates, rails, spikes, generators). (see `docs/architecture/PACKAGING.md`)

### 1.2 Phase 0 dependency truth (MUST)

This boundary **is not** a dependency table. The single truth-source for Phase 0 compile-time edges:

- `docs/roadmap/phase0/00_2-dependency-table.md`

---

## 2) Terms (normative)

- **Spike** — a Phase 0 prototype/experiment that allows fast iterations, but **MUST** be deterministic, rerun-no-diff, and free from secret leakage.
- **Tooling-only** — code that lives in `framework/tools/**` and **MUST NOT** be part of the production/runtime surface.
- **Runtime packages area** — any publishable units under `framework/packages/**` (including `core/*`, `platform/*`, `integrations/*`, `enterprise/*`, `devtools/*`, `presets/*`).
- **Path-based import** — any `require|require_once|include|include_once` path to `framework/packages/**/src/**` (or equivalent, including `..`/backslashes).

---

## 3) Boundary decision (single-choice) (MUST)

### 3.1 Spikes location (MUST)

- Spikes **MUST** live **only** under:
  - `framework/tools/spikes/**`

### 3.2 Spikes MUST NOT live in runtime packages (MUST NOT)

- Spike implementations **MUST NOT** live under:
  - `framework/packages/**`

This includes any classes/scripts containing “spike logic”, even if it is “convenient” to run them through CLI.

### 3.3 Spikes MUST NOT import runtime code (MUST NOT)

Spikes **MUST NOT** import (namespace imports) runtime packages:

- `core/*`
- `platform/*`
- `integrations/*`
- `enterprise/*`
- `presets/*`

That is, spikes **MUST NOT** have `use Coretsia\...` imports to runtime namespaces (for example `Coretsia\Kernel\...`, `Coretsia\Foundation\...`, `Coretsia\Platform\...`, `Coretsia\Integrations\...`).

> Note: one tooling-only exception is allowed separately — `coretsia/internal-toolkit` (see §4).

### 3.4 Spikes MUST NOT do path-based imports from packages/src (MUST NOT)

Spikes **MUST NOT** `require/include` from `framework/packages/**/src/**` (in any path form):

- `framework/packages/core/*/src/**`
- `framework/packages/platform/*/src/**`
- `framework/packages/integrations/*/src/**`
- etc.

This rule **forbids** “bypassing” dependencies through filesystem paths.

---

## 4) Single-choice exception: `coretsia/internal-toolkit` (MUST)

### 4.1 Allowed Coretsia dependency (exactly one) (MAY)

Spikes **MAY** depend on exactly **one** Coretsia internal tooling library:

- `coretsia/internal-toolkit`

And **MUST** use it **only** through Composer autoload (namespace-based), not through path-based `require`.

### 4.2 What the exception covers (CLOSED set) (MUST)

This exception is **closed** and covers **only** canonical *determinism primitives*:

- slug casing helpers: `Slug::*`
- path normalization helpers: `Path::*`
- stable JSON encoding helpers: `Json::*`

### 4.3 What the exception does NOT cover (MUST NOT)

- Tooling **MUST NOT** duplicate `Slug::*`, `Path::*`, `Json::*` anywhere under `framework/tools/**`.
- Deterministic file IO helpers (for example Phase 0 `DeterministicFile` for EOL/LF normalization and safe writes) **are not** part of the `internal-toolkit` primitive set and **MAY** live in:
  - `framework/tools/spikes/_support/**` provided that they **do not** duplicate `Slug::*`, `Path::*`, `Json::*`.

> This exception does not “open the door” for moving any Phase 0 logic into publishable packages.

---

## 5) Third-party tooling deps (allowed) vs Coretsia runtime deps (forbidden) (MUST)

- Spikes **MAY** use third-party dev tooling dependencies (for example PHPUnit) through the tooling workspace.
- Spikes **MUST NOT** have compile-time dependencies on Coretsia runtime packages:
  - `core/*`, `platform/*`, `integrations/*` (and other runtime publishable units), even if “it is only for a prototype”.

---

## 6) Non-primitive Phase 0 rails infrastructure (allowed in tools) (MAY)

Phase 0 rails infrastructure (gates/runner/diagnostics utilities) **MAY** live under:

- `framework/tools/spikes/_support/**`

provided that it:

- respects the prohibitions in §3 (no runtime imports, no path-imports from `framework/packages/**/src/**`);
- does not duplicate the primitive set `Slug::*`, `Path::*`, `Json::*` (see §4).

Examples of allowed rails infrastructure:

- ErrorCodes registry,
- deterministic exception carrier,
- deterministic IO wrappers (not primitives),
- CI rails runner,
- gates scripts,
- safe console output adapter (if the policy/rails allow it).

---

## 7) CLI exception (explicit; does NOT change spikes boundary) (MUST)

### 7.1 CLI runtime package may exist (MAY)

- CLI runtime package `coretsia/cli` **MAY** exist as a UX entrypoint.

### 7.2 Spike command implementations MUST NOT live in production runtime packages (MUST NOT)

- Phase 0 spike command **IMPLEMENTATIONS MUST NOT** live in production runtime packages.
- Spike commands **MUST** live:
  - either as tools-only scripts in `framework/tools/spikes/**`,
  - or in a devtools-only package (`coretsia/cli-spikes`, epic `0.140.0`) as a thin dispatcher **without** spike business logic.

### 7.3 Production safety invariant (MUST)

- Installing only `coretsia/cli` **MUST NOT** include doctor/spike/deptrac/workspace command classes in the package.

### 7.4 Boundary remains tools-only (MUST)

- CLI **does not** grant permission to move spikes out of `framework/tools/spikes/**`.
- CLI may only dispatch/exec spikes and read fixtures; spikes remain tools-only.

> This boundary decision **does not** require `0.120/0.130/0.140` to already be implemented — it only fixes the rule.

---

## 8) Examples (copy-pastable)

> **DoD note:** the minimal set of examples (3–4) is **§8.1–§8.4** (4 examples).\
> **§8.5** is an additional edge-case example (does not change the boundary and does not extend the rules).

### 8.1 ✅ Valid: spike lives under `framework/tools/spikes/**` and uses `internal-toolkit` via autoload

```php
<?php

declare(strict_types=1);

use Coretsia\Devtools\InternalToolkit\Path;
use Coretsia\Devtools\InternalToolkit\Json;

$rel = Path::normalizeRelative('a\\b/../c');
echo Json::encodeStable(['path' => $rel]) . "\n";
```

**Placement (valid):**

```txt
framework/tools/spikes/example_valid/run.php
```

---

### 8.2 ✅ Valid: non-primitive rails helper under `_support/**` (no duplication of Slug/Path/Json)

```php
<?php

declare(strict_types=1);

final class DeterministicFile
{
    public static function readTextNormalizedEol(string $path): string
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('CORETSIA_SPIKES_IO_READ_FAILED');
        }

        $raw = str_replace("\r\n", "\n", $raw);
        $raw = str_replace("\r", "\n", $raw);

        return $raw;
    }
}
```

**Placement (valid):**

```txt
framework/tools/spikes/_support/DeterministicFile.php
```

---

### 8.3 ❌ Invalid: moving spike implementation into `framework/packages/**` (boundary violation)

**Placement (invalid):**

```txt
framework/packages/core/kernel/src/Spike/FingerprintPrototype.php
```

**File content (invalid even if syntactically correct):**

```php
<?php

declare(strict_types=1);

namespace Coretsia\Kernel\Spike;

/**
 * ❌ Boundary violation:
 * A spike/prototype implementation MUST NOT live under framework/packages/**,
 * even if it is "temporary" and even if CLI can dispatch it.
 */
final class FingerprintPrototype
{
    public function run(): void
    {
        // spike logic placeholder
    }
}
```

Even if this is “temporary” and even if CLI can run it — this **MUST** be considered a boundary violation.

---

### 8.4 ❌ Invalid: importing runtime namespaces from spikes

```php
<?php

declare(strict_types=1);

use Coretsia\Kernel\KernelRuntime; // ❌ forbidden runtime import

final class Spike
{
}
```

---

### 8.5 ❌ Invalid (additional): path-based import from `framework/packages/**/src/**`

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../../../packages/core/kernel/src/KernelRuntime.php'; // ❌ forbidden
```

---

## 9) Cutline / enforcement intent (MUST)

- This document is a **cutline blocker** for Phase 0:
  - until the boundary is cemented, subsequent tooling epics must not expand the spikes surface.
- Any Phase 0 tooling implementation **MUST** be compatible with this boundary.
- Subsequent epics **SHOULD** add enforcement gates, but this epic is **doc-only** and fixes the rule.

---

## 10) Deliverables (exact paths only) (MUST)

### Creates

- `docs/roadmap/phase0/00_3-spikes-boundary.md` — canonical boundary decision (single-choice) + MUST/MUST NOT rules + examples

### Modifies

N/A (doc-only)

---

## 11) See also

- Packaging law (publishable vs tooling): `../../architecture/PACKAGING.md`
- Repo structure overview: `../../architecture/STRUCTURE.md`
- Phase 0 build order (guidance): `./00_1-build-order.md`
- Phase 0 dependency truth (SSoT): `./00_2-dependency-table.md`
- Prelude rules snapshot: `../ROADMAP.md`
