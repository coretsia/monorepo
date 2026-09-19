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

# coretsia/platform-cli

Minimal `coretsia` CLI runtime base with deterministic, production-safe output.

This package provides a kernel-free CLI runtime that:

- uses a config-based command registry (`cli.commands: list<FQCN>`),
- produces deterministic safe output (text/JSON),
- enforces security redaction by default.

## Package identity

- Path: `packages/platform/cli`
- Package id: `platform/cli`
- Composer name: `coretsia/platform-cli`
- Module id: `platform.cli`
- Namespace: `Coretsia\Platform\Cli\*` (PSR-4: `src/`)
- Kind: runtime
- Lifecycle: runtime

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions MUST NOT be used.

## Dependency policy

This package is runtime-safe and intentionally kernel-free at compile time.

- Depends on:
  - `core/contracts`
- Forbidden:
  - any `core/*` package other than `core/contracts`
  - other `platform/*` packages
  - `integrations/*`
  - `enterprise/*`
  - `devtools/*`
  - `presets/*`

`platform/cli` MUST NOT import or consume repository machinery under `tools/**`.

Before public stable release, public Composer dependencies MUST use SemVer constraints and MUST NOT use `dev-main`.

## Scope

- No `core/kernel` dependency (compile-time forbidden).
- No DI / no container / no autowiring:
  - commands are instantiated as `new $fqcn()` (zero-arg constructor).
- No runtime filesystem scanning for discovery:
  - command discovery is purely config-driven (`cli.commands`).
- Repository/application path resolution inside the current standalone `Application` is transitional implementation detail and is not an architecture contract.

## Canonical entrypoints

Single-choice user entrypoint (cross-OS, canonical):

- `php coretsia ...`

Repository launcher implementation:

- `php tools/bin/coretsia ...`

Notes:

- `coretsia` (repo root) MUST delegate to `tools/bin/coretsia`.
- The repository launcher uses the root workspace autoload at `vendor/autoload.php`.
- The launcher is top-level exception-safe and prints only deterministic codes (no stack traces/messages).

## Configuration

This package owns the config root `cli`.

### Config files (shape rule)

All `config/cli.php` files MUST return the `cli` subtree (NO repeated root wrapper):

✅ allowed:

```php
return [
  'commands' => [],
  'output' => [
    'format' => 'text',
    'redaction' => ['enabled' => true],
  ],
];
```

❌ forbidden:

```php
return [
  'cli' => [ /* ... */ ],
];
```

### Keys (dot notation)

- `cli.commands` = `[]` (list of command FQCNs; may be empty)
- `cli.output.format` = `text` (`text` or `json` as implemented by output)
- `cli.output.redaction.enabled` = `true` (default-on)

## Deterministic config merge (single-choice)

The current standalone `Application` builds the final `cli` subtree using this fixed merge order:

1. Package defaults from `config/cli.php`
2. Optional application override

The physical application-override resolution used by the current standalone implementation is transitional and is not part of the canonical package architecture.

### Merge algorithm (cemented)

- `cli.commands` uses append-unique preserving first occurrence order:
  - apply sources in order: defaults → application override
  - remove duplicates deterministically by keeping the first occurrence
- all other `cli.*` keys:
  - higher-precedence values override lower-precedence values
  - lists (except `cli.commands`) are replaced (no implicit list merge)

## Command model

Configured commands MUST implement:

- `Coretsia\Contracts\Cli\Command\CommandInterface`

### Built-in reserved names

The names `help` and `list` are reserved for built-ins.
If a configured command returns a reserved name:

- CLI MUST fail deterministically with:
  - code: `CORETSIA_CLI_COMMAND_INVALID`
  - reason token: `cli-reserved-command-name`

### Instantiation policy

For each FQCN in `cli.commands`:

- CLI MUST instantiate via `new $fqcn()` (zero-arg constructor)

If the class is missing, non-instantiable, or requires args:

- deterministic failure (see Errors below)

## Output

Commands MUST NOT write to stdout/stderr directly.
All user-facing output MUST go through:

- `Coretsia\Contracts\Cli\Output\OutputInterface`

This package provides:

- `Coretsia\Platform\Cli\Output\CliOutput`

### Determinism invariants (cemented)

- all emitted output MUST end with a single `\n`
- JSON output MUST be a single line + trailing `\n` (no pretty print)
- MUST NOT leak absolute paths:
  - Windows drive/UNC
  - `/home/`, `/Users/`, etc.

## Security / Redaction

Redaction is enabled by default:

- `cli.output.redaction.enabled = true`

### Secret-like key matching (case-insensitive substrings)

`TOKEN`, `PASSWORD`, `PASS`, `SECRET`, `AUTH`, `COOKIE`, `SESSION`, `KEY`, `PRIVATE`

### Text redaction rules (deterministic)

- `KEY=VALUE` where KEY is secret-like → `KEY=<redacted>`
- `Authorization: ...` → `Authorization: <redacted>`

### JSON redaction rules (deterministic)

Before JSON normalization/encoding:

- recursively traverse payload
- for any map key matching secret-like rule → replace its value with the string `<redacted>`
- keys are never removed, list lengths never change (values only)

### JSON normalization + encoding (deterministic)

- maps: recursive key-sort by byte-order (`strcmp`)
- lists: preserve order (MUST NOT be sorted)
- encoding flags:
  - `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR`

## Errors

CLI errors are code-first and deterministic.
This package owns its CLI error codes:

- `CORETSIA_CLI_COMMAND_CLASS_MISSING`
- `CORETSIA_CLI_COMMAND_INVALID`
- `CORETSIA_CLI_CONFIG_INVALID`
- `CORETSIA_CLI_UNCAUGHT_EXCEPTION`

### Failure semantics (safe by design)

- Errors MUST NOT include:
  - exception messages
  - stack traces
  - absolute paths
  - raw config dumps / dotenv values / credentials

Launcher uncaught exception behavior (single-choice):

- line 1: `CORETSIA_CLI_UNCAUGHT_EXCEPTION`
- line 2: `uncaught-exception`
- exit code: `1`

## Observability

The CLI base is intentionally minimal.

- No tracing/metrics/logging ports are required here.
- Any diagnostics are user-facing and MUST respect:
  - determinism (stable bytes)
  - security (no secrets/PII)
  - path-safety (no absolute paths)

## Non-goals

- vendor-only install UX guarantees (`vendor/bin/coretsia`) are out of scope
- DI/container integration (tag discovery, autowire) is out of scope
- plugin system is out of scope
