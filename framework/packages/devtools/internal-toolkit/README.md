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

`devtools/internal-toolkit` is the tooling-only deterministic helper package for Coretsia repository tooling and devtools.

Scope: small canonical primitives for stable JSON encoding, repository-relative path normalization, and deterministic identifier transformation used by Coretsia tooling, validation tooling, generators, runners, and repository automation.

Out of scope: application runtime behavior, production runtime dependencies, service-container integration, platform behavior, integrations, transport execution, runtime serialization, and general-purpose utility ownership.

## Package identity

- Path: `framework/packages/devtools/internal-toolkit`
- Package id: `devtools/internal-toolkit`
- Composer name: `coretsia/devtools-internal-toolkit`
- Namespace: `Coretsia\Devtools\InternalToolkit\*` (PSR-4: `src/`)
- Kind: library
- Lifecycle: tooling-only

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

The corresponding split repository is `coretsia/devtools-internal-toolkit` and receives the same tag for the package subtree.

Per-package independent versions MUST NOT be used.

## Dependency policy

This package is tooling-only and intentionally small.

- Depends on:
  - PHP
  - `ext-json`
- Allowed consumers:
  - Coretsia devtools packages
  - Coretsia repository tooling
  - Coretsia validation tooling
  - Coretsia generators
  - Coretsia runners
  - repository split and publishing automation
- Forbidden consumers:
  - runtime packages under `core/*`
  - runtime packages under `platform/*`
  - runtime packages under `integrations/*`
  - production skeleton/runtime code
  - consuming applications

Runtime packages MUST NOT depend on `coretsia/devtools-internal-toolkit`.

If runtime code requires deterministic serialization, path handling, naming, or another similar primitive, the runtime owner package MUST provide or depend on an appropriate runtime-safe implementation instead of importing this devtools package.

The package MUST NOT become a shared miscellaneous utility layer for unrelated runtime or application behavior.

## Tooling responsibilities

This package owns a deliberately narrow set of deterministic cross-cutting helpers for Coretsia tooling.

The canonical public API is:

```text
Coretsia\Devtools\InternalToolkit\Json::encodeStable(array $value): string
Coretsia\Devtools\InternalToolkit\Path::normalizeRelative(string $absOrRelPath, string $repoRoot): string
Coretsia\Devtools\InternalToolkit\Slug::toStudly(string $slug): string
Coretsia\Devtools\InternalToolkit\Slug::toSnake(string $slug): string
```

The helper classes are:

```text
Coretsia\Devtools\InternalToolkit\Json
Coretsia\Devtools\InternalToolkit\Path
Coretsia\Devtools\InternalToolkit\Slug
```

They are final, non-instantiable, static-only helpers.

The package owns these primitives so repository tooling can share one canonical implementation instead of reproducing slightly different local variants.

## Ownership boundaries

`devtools/internal-toolkit` owns deterministic helper behavior for Coretsia tooling only.

It does not own:

- runtime JSON normalization or serialization;
- runtime context normalization;
- application path resolution;
- filesystem abstraction;
- package discovery;
- repository scanning;
- generated artifact schemas;
- artifact publication;
- CLI command execution;
- DI wiring;
- config loading;
- logging or telemetry;
- runtime redaction;
- application naming conventions.

Callers own the higher-level semantics of the data they pass to these helpers.

For example:

- `Json::encodeStable()` owns stable JSON normalization and encoding, not the schema of the payload;
- `Path::normalizeRelative()` owns lexical repository-relative normalization, not filesystem discovery or existence checks;
- `Slug` owns deterministic string transformations, not package naming policy or class generation policy.

Tooling packages MUST NOT infer broader runtime or domain semantics from these helpers.

## Stable JSON

The canonical tooling JSON helper is:

```text
Coretsia\Devtools\InternalToolkit\Json
```

Its public API is:

```php
Json::encodeStable(array $value): string
```

`Json::encodeStable()` produces stable JSON bytes for supported json-like tooling arrays.

### Value model

Supported scalar values are:

```text
null
bool
int
string
```

Nested arrays are supported recursively.

Floats are forbidden, including:

```text
finite float
NAN
INF
-INF
```

Unsupported values include objects, resources, and other non-json-like PHP values.

### Lists and maps

Array classification uses:

```php
array_is_list(...)
```

Lists preserve caller-supplied order.

Maps:

- require string keys;
- are sorted recursively by byte-order `strcmp`;
- preserve normalized values under the sorted keys.

An empty PHP array is treated as a list and is encoded as:

```json
[]
```

### Encoding

Encoding uses:

```text
JSON_UNESCAPED_SLASHES
JSON_UNESCAPED_UNICODE
JSON_THROW_ON_ERROR
```

The helper returns JSON bytes only.

It does not append a trailing newline.

Caller-owned output code decides whether a final LF is required by the target file or artifact format.

### JSON failures

A float fails with:

```text
CORETSIA_JSON_FLOAT_FORBIDDEN
```

When the failure is nested, the structural path is appended.

Unsupported values or unsupported map keys fail with:

```text
CORETSIA_INTERNAL_TOOLKIT_JSON_UNSUPPORTED_TYPE
```

Again, a structural path may be appended for nested failures.

JSON encoding failures from `json_encode()` remain `JsonException` failures.

These are tooling failures, not runtime transport error contracts.

## Repository-relative paths

The canonical path helper is:

```text
Coretsia\Devtools\InternalToolkit\Path
```

Its public API is:

```php
Path::normalizeRelative(
    string $absOrRelPath,
    string $repoRoot,
): string
```

The helper converts an absolute or repository-relative input path into a normalized repository-relative path.

The operation is lexical and deterministic.

It does not require the input path to exist on the filesystem.

### Path normalization rules

The result:

- uses forward slashes;
- is relative to the supplied repository root;
- does not contain an absolute path prefix;
- does not contain unresolved `..` segments;
- cannot escape the supplied repository root.

When the normalized path identifies the repository root itself, the result is:

```text
.
```

Redundant separators and `.` segments are normalized.

Relative inputs are resolved lexically against the supplied repository root.

### Platform handling

The helper supports canonical absolute path handling for:

- POSIX paths;
- Windows drive paths;
- Windows UNC paths;
- Windows extended-length path forms;
- MSYS/MinGW drive-style paths when running on Windows.

Windows containment comparison follows case-insensitive Windows path semantics.

Drive letters are normalized for canonical absolute-path processing.

The output remains forward-slash repository-relative form.

### Path failures

Stable path failure tokens include:

```text
CORETSIA_INTERNAL_TOOLKIT_PATH_INVALID_REPO_ROOT
CORETSIA_INTERNAL_TOOLKIT_PATH_REPO_ROOT_NOT_ABSOLUTE
CORETSIA_INTERNAL_TOOLKIT_PATH_NOT_ABSOLUTE
CORETSIA_INTERNAL_TOOLKIT_PATH_OUTSIDE_REPO_ROOT
CORETSIA_INTERNAL_TOOLKIT_PATH_DOTDOT_ESCAPES_ROOT
CORETSIA_INTERNAL_TOOLKIT_PATH_UNC_INVALID
```

Path failures use `InvalidArgumentException`.

The helper MUST fail instead of returning a repository-relative path for an input that resolves outside the supplied repository root.

## Identifier transformations

The canonical identifier helper is:

```text
Coretsia\Devtools\InternalToolkit\Slug
```

It provides:

```php
Slug::toStudly(string $slug): string
Slug::toSnake(string $slug): string
```

The transformations are ASCII-oriented and deterministic.

They do not depend on process locale, ICU collation, filesystem casing, or locale-specific title-casing.

### Studly transformation

`Slug::toStudly()` converts slug-like identifiers into StudlyCase.

Examples:

```text
cli-tools        -> CliTools
internal_toolkit -> InternalToolkit
foo.bar-baz      -> FooBarBaz
psr-7            -> Psr7
```

Non-alphanumeric separators delimit parts.

Each part is normalized with locale-independent ASCII casing.

An empty or whitespace-only input produces an empty string.

### Snake transformation

`Slug::toSnake()` converts common identifier forms into lowercase snake_case.

Examples:

```text
CliTools                 -> cli_tools
JSONEncoder              -> json_encoder
CoreDTOAttribute         -> core_dto_attribute
cli-tools                -> cli_tools
foo.bar/baz qux          -> foo_bar_baz_qux
Coretsia\InternalToolkit -> coretsia_internal_toolkit
```

The helper:

- normalizes common separators to underscores;
- recognizes acronym-to-word boundaries;
- recognizes lowercase/digit-to-uppercase boundaries;
- lowercases ASCII characters deterministically;
- collapses repeated underscores;
- removes leading and trailing underscores.

An empty or whitespace-only input produces an empty string.

## Anti-duplication boundary

These helpers are intended to be the canonical implementations for their owned tooling responsibilities.

Coretsia repository tooling MUST NOT duplicate an owned helper implementation when the canonical toolkit API already provides the required behavior.

This anti-duplication rule does not expand the package into a generic utility owner.

New helpers SHOULD be added only when there is a genuine cross-tooling invariant that benefits from one canonical deterministic implementation.

## Usage

This package is consumed through Composer autoloading.

Path-based includes are not part of the supported package API.

```php
<?php

declare(strict_types=1);

use Coretsia\Devtools\InternalToolkit\Json;
use Coretsia\Devtools\InternalToolkit\Path;
use Coretsia\Devtools\InternalToolkit\Slug;

$slug = Slug::toSnake('InternalToolkit');

$path = Path::normalizeRelative(
    '/repo/framework/packages/devtools/internal-toolkit',
    '/repo',
);

$json = Json::encodeStable([
    'path' => $path,
    'slug' => $slug,
]);
```

The resulting JSON is stable for the same supported input:

```json
{"path":"framework/packages/devtools/internal-toolkit","slug":"internal_toolkit"}
```

Higher-level tooling remains responsible for writing files, adding final newlines where required, choosing schemas, and deciding how the resulting values are consumed.

## Determinism

All owned helper operations MUST produce the same result for the same supported inputs under the same documented platform semantics.

Deterministic behavior includes:

- byte-order map-key sorting in stable JSON;
- preserved list order;
- explicit array list/map classification;
- lexical path normalization;
- repository-root containment checks;
- locale-independent ASCII identifier casing;
- stable failure tokens.

Owned helper output MUST NOT introduce:

- timestamps;
- random values;
- process ids;
- machine-specific identifiers;
- environment-derived values;
- current working directory state;
- filesystem enumeration order;
- locale-dependent ordering.

The helpers MUST NOT perform hidden filesystem discovery or environment inspection to determine their normal result.

Any future extension MUST preserve rerun-no-diff behavior for identical repository state and inputs.

## Observability

This package does not emit telemetry.

It does not define:

- logs;
- spans;
- metrics;
- tracing;
- profiling;
- exporters.

Helper execution is local deterministic tooling behavior.

Higher-level tools own any user-facing reporting or diagnostics around helper invocation.

## Errors

This package does not define production runtime error contracts.

Invalid helper input fails through deterministic tooling exceptions.

Current public failure classes are standard PHP exceptions:

```text
InvalidArgumentException
JsonException
```

Stable toolkit-specific reason tokens are used where the helper performs explicit validation.

These failures are intended for tooling diagnostics and automated tooling checks.

They MUST NOT be reinterpreted as application, transport, HTTP, Worker, Kernel, or other production runtime error contracts.

Callers MAY add higher-level tooling context, but MUST NOT change the deterministic semantics of the underlying helper operation.

## Security / Redaction

This package is tooling-only and does not intentionally process sensitive runtime payloads.

Callers MUST NOT pass raw sensitive material into helper-produced diagnostics or generated tooling outputs unless the owning tool has an explicit safe handling policy.

Sensitive material includes:

- secrets;
- credentials;
- passwords;
- private keys;
- bearer tokens;
- session identifiers;
- cookies;
- Authorization values;
- raw environment values;
- private customer data;
- direct PII.

`Path::normalizeRelative()` prevents returned paths from retaining an absolute prefix outside its repository-relative output contract, but repository-relative path contents remain caller-owned data.

Callers MUST NOT assume that path normalization by itself makes an arbitrary path safe for public diagnostics.

`Json::encodeStable()` provides deterministic encoding, not redaction.

It does not inspect values for secrets or PII.

Structural paths appended to JSON validation failures may contain caller-supplied map key names, so sensitive data MUST NOT be encoded into diagnostic key names.

`Slug` performs deterministic identifier transformation only and provides no sanitization or security classification guarantee.

Redaction and public diagnostic safety remain responsibilities of the higher-level tooling owner.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Internal Toolkit package source](https://github.com/coretsia/monorepo/tree/main/framework/packages/devtools/internal-toolkit)
- [Packaging strategy](https://github.com/coretsia/monorepo/blob/main/docs/architecture/PACKAGING.md)
