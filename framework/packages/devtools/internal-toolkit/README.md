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

# coretsia/devtools-internal-toolkit

`devtools/internal-toolkit` is the **tooling-only deterministic helper library** for Coretsia Framework monorepo tools.

It provides shared primitives used by Phase 0 tooling rails, spikes, gates, generators, and runners under `framework/tools/**`.

This package MUST NOT be required by runtime packages and MUST NOT become a production runtime dependency.

## Package identity

- **Path:** `framework/packages/devtools/internal-toolkit`
- **Package id:** `devtools/internal-toolkit`
- **Composer name:** `coretsia/devtools-internal-toolkit`
- **Namespace:** `Coretsia\Devtools\InternalToolkit\*` (PSR-4: `src/`)
- **Kind:** library
- **Lifecycle:** tooling-only / devtools

Monorepo versioning is **repo-wide only** via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions **MUST NOT** be used.

## Dependency policy

This package is tooling-only and intentionally small.

- **Depends on:**
  - PHP
  - `ext-json`
- **Forbidden as consumers:**
  - runtime packages under `core/*`
  - runtime packages under `platform/*`
  - runtime packages under `integrations/*`
  - production skeleton/runtime code

Runtime packages MUST NOT depend on `coretsia/devtools-internal-toolkit`.

If runtime code needs deterministic serialization or ordering, the runtime owner package must provide runtime-safe primitives instead of importing tooling helpers.

For example, `core/foundation` owns runtime-safe serialization through its own Foundation implementation, not through this devtools package.

## Owned API

This package is the canonical tooling location for the following deterministic primitives:

- `Coretsia\Devtools\InternalToolkit\Slug::toStudly(string $slug): string`
- `Coretsia\Devtools\InternalToolkit\Slug::toSnake(string $slug): string`
- `Coretsia\Devtools\InternalToolkit\Path::normalizeRelative(string $absOrRelPath, string $repoRoot): string`
- `Coretsia\Devtools\InternalToolkit\Json::encodeStable(array $value): string`

These helpers are for tooling code only.

## JSON helper

`Json::encodeStable()` provides stable JSON bytes for json-like tooling payloads.

Rules:

- maps are sorted recursively by byte-order `strcmp`;
- lists preserve declared order;
- empty arrays are encoded as JSON lists;
- allowed scalar values are `null`, `bool`, `int`, and `string`;
- floats are rejected;
- objects, resources, and callables are rejected;
- encoding uses `JSON_UNESCAPED_SLASHES`, `JSON_UNESCAPED_UNICODE`, and `JSON_THROW_ON_ERROR`.

This helper returns JSON bytes only. It does not append a final newline unless caller-owned output code does so.

## Path helper

`Path::normalizeRelative()` returns repo-relative normalized paths using forward slashes.

Rules:

- input may be absolute or repo-relative;
- output MUST be repo-relative;
- output MUST NOT contain absolute path prefixes;
- output MUST NOT escape the supplied repository root;
- output MUST NOT contain `..` segments;
- path normalization is lexical and deterministic.

This helper is intended for safe tooling diagnostics and generated tooling artifacts.

## Slug helper

`Slug::toStudly()` and `Slug::toSnake()` provide locale-independent ASCII identifier transformations.

They are intended for package ids, class names, generated test names, gate output, and tooling artifacts.

Slug helpers MUST NOT depend on process locale, ICU collation, filesystem casing, or platform-specific title-casing behavior.

## Anti-duplication contract

Tooling code under `framework/tools/**` MUST NOT duplicate these helper implementations.

The repository enforces this through:

```text
framework/tools/gates/internal_toolkit_no_dup_gate.php
```

The same gate also forbids direct `json_encode(...)` usage under `framework/tools/**`, except for explicit allowlisted deliverables.

## Usage

This package is consumed through Composer autoload only.

Path-based includes are forbidden.

```php
use Coretsia\Devtools\InternalToolkit\Json;
use Coretsia\Devtools\InternalToolkit\Path;
use Coretsia\Devtools\InternalToolkit\Slug;

$slug = Slug::toSnake('CoreFoundation');
$path = Path::normalizeRelative('/repo/framework/packages/core/contracts', '/repo');
$json = Json::encodeStable([
    'path' => $path,
    'slug' => $slug,
]);
```

## Determinism and safety expectations

All operations MUST be deterministic for identical inputs.

Outputs MUST NOT include:

- timestamps;
- random values;
- absolute local paths;
- host-specific bytes;
- process-specific bytes;
- secrets;
- PII.

Any future extension MUST preserve rerun-no-diff behavior for the same repository state and inputs.

## Observability

This package does not emit telemetry directly.

It provides deterministic tooling helpers only.

## Errors

This package does not define runtime error codes.

Helper failures use deterministic exception messages intended for tooling diagnostics.

These errors are not runtime transport errors and MUST NOT be exposed as production error contracts.

## Security / Redaction

This package does not process sensitive runtime payloads directly.

Tooling callers MUST NOT pass raw secrets, credentials, tokens, cookies, authorization headers, private keys, raw env values, or private customer data into generated diagnostics.

Path helper outputs are intended to avoid absolute local path leakage.

JSON helper output is stable, but caller-owned code remains responsible for redaction.

## References

- `docs/roadmap/ROADMAP.md`
- `framework/tools/gates/internal_toolkit_no_dup_gate.php`
