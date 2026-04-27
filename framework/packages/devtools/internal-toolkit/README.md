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

# coretsia/internal-toolkit (devtools)

## Scope (tooling-only)

`coretsia/internal-toolkit` is a **tooling-only** library that provides **deterministic helpers** used by Phase 0 rails (`framework/tools/**`, spikes, gates, runners).

This package **MUST NOT** be required by runtime packages (`core/*`, `platform/*`, `integrations/*`) and **MUST NOT** become a runtime-mandatory dependency.

## Owned API (single source of truth)

This package is the **only** allowed location for the following deterministic primitives:

- `Slug::{toStudly,toSnake}`
- `Path::normalizeRelative`
- `Json::encodeStable`  
  Deterministic JSON bytes (recursive key sort for maps; list order preserved; flags: `JSON_UNESCAPED_*` + `JSON_THROW_ON_ERROR`; floats/NaN/INF forbidden by policy at higher layers).

## Anti-duplication contract (enforced by gate)

Tooling code under `framework/tools/**` is **not allowed** to duplicate these helpers.

The repository enforces this via the **anti-dup gate**:

- `framework/tools/gates/internal_toolkit_no_dup_gate.php`

The same gate also forbids direct `json_encode(...)` usage under `framework/tools/**` (with a strict allowlist for explicit deliverables).

## Usage

This package is consumed via Composer autoload (namespace-based). **No path-based includes**.

Example import (tooling only):

```php
use Coretsia\Devtools\InternalToolkit\Json;
use Coretsia\Devtools\InternalToolkit\Path;
use Coretsia\Devtools\InternalToolkit\Slug;
```

## Determinism & safety expectations

- All operations are **deterministic** given identical inputs.
- Outputs MUST NOT include timestamps, absolute paths, environment-specific bytes, or secrets/PII.
- Any future extensions MUST preserve “rerun => no diff” behavior for the same repo state and inputs.

## Observability

This package does not emit telemetry directly.

## Errors

This package does not define runtime error codes directly.

## Security / Redaction

This package does not process sensitive runtime payloads directly.

## License

Apache-2.0. See `LICENSE` and `NOTICE` in the repository root.
