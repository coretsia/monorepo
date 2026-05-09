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

`coretsia/devtools-internal-toolkit` is the tooling-only deterministic helper library for Coretsia devtools.

It provides shared deterministic primitives for Coretsia tooling, gates, generators, runners, and development-time repository automation.

This repository is a split package generated from the Coretsia monorepo package `framework/packages/devtools/internal-toolkit`.

This package MUST NOT be required by runtime packages and MUST NOT become a production runtime dependency.

## Package identity

- **Monorepo source path:** `framework/packages/devtools/internal-toolkit`
- **Split repository:** `coretsia/devtools-internal-toolkit`
- **Package id:** `devtools/internal-toolkit`
- **Composer name:** `coretsia/devtools-internal-toolkit`
- **Namespace:** `Coretsia\Devtools\InternalToolkit\*` (PSR-4: `src/`)
- **Kind:** library
- **Lifecycle:** tooling-only / devtools / internal Coretsia tooling support

Versioning is monorepo-wide.

The monorepo tag `vMAJOR.MINOR.PATCH` is the single version source of truth, and the split repository receives the same tag for the corresponding package subtree.

Per-package independent versions MUST NOT be used.

## Dependency policy

This package is tooling-only and intentionally small.

- **Depends on:**
  - PHP
  - `ext-json`
- **Allowed consumers:**
  - Coretsia devtools packages
  - Coretsia repository tooling
  - Coretsia gates, generators, runners, and split/publishing automation
- **Forbidden consumers:**
  - runtime packages under `core/*`
  - runtime packages under `platform/*`
  - runtime packages under `integrations/*`
  - production skeleton/runtime code
  - consuming applications

Runtime packages MUST NOT depend on `coretsia/devtools-internal-toolkit`.

If runtime code needs deterministic serialization, ordering, path normalization, or naming helpers, the runtime owner package must provide runtime-safe primitives instead of importing this devtools package.

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

Coretsia tooling code under the monorepo `framework/tools/**` MUST NOT duplicate these helper implementations.

The monorepo enforces this through:

```text
framework/tools/gates/internal_toolkit_no_dup_gate.php
```

That gate is not part of this split repository. The Coretsia monorepo remains the source of truth for repository-level tooling gates.

The same monorepo gate also forbids direct `json_encode(...)` usage under `framework/tools/**`, except for explicit allowlisted deliverables.

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

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Internal Toolkit package source](https://github.com/coretsia/monorepo/tree/main/framework/packages/devtools/internal-toolkit)
- [Packaging strategy](https://github.com/coretsia/monorepo/blob/main/docs/architecture/PACKAGING.md)
- [Roadmap](https://github.com/coretsia/monorepo/blob/main/docs/roadmap/ROADMAP.md)
- [Monorepo anti-duplication gate](https://github.com/coretsia/monorepo/blob/main/framework/tools/gates/internal_toolkit_no_dup_gate.php)
