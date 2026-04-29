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

Phase 0 package: **Minimal `coretsia` CLI base (prod-safe)**.

This package provides a **kernel-free** CLI runtime that:

- uses a **config-based** command registry (`cli.commands: list<FQCN>`),
- produces **deterministic safe output** (text/JSON),
- enforces **security redaction** by default,
- is scoped to the **Coretsia monorepo layout** in Phase 0 (no vendor-only install promises).

## Scope (Phase 0 constraints)

- **No `core/kernel` dependency** (compile-time forbidden).
- **No DI / no container / no autowiring**:
  - commands are instantiated as `new $fqcn()` (zero-arg constructor).
- **No runtime filesystem scanning for discovery**:
  - command discovery is purely config-driven (`cli.commands`).
- **Monorepo layout assumption is explicit**:
  - the CLI base assumes `framework/` and (if present) `skeleton/` are siblings under a common repo root.

## Canonical entrypoints

Single-choice user entrypoint (cross-OS, canonical):

- `php coretsia ...`

Launcher implementation (still runnable):

- `php framework/bin/coretsia ...`

Notes:

- `coretsia` (repo root) MUST delegate to `framework/bin/coretsia`.
- The launcher selects composer autoload by ordered fallback:
  1) `framework/vendor/autoload.php`
  2) `vendor/autoload.php`
- The launcher is top-level exception-safe and prints **only deterministic codes** (no stack traces/messages).

## Configuration

This package owns the config root **`cli`**.

### Config files (shape rule)

All `config/cli.php` files MUST return the **`cli` subtree** (NO repeated root wrapper):

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
- `cli.output.format` = `text` (Phase 0: `text` or `json` as implemented by output)
- `cli.output.redaction.enabled` = `true` (default-on)

## Deterministic config merge (single-choice)

The runtime builds the final `cli` subtree using this fixed merge order:

1. **Package defaults**

- `framework/packages/platform/cli/config/cli.php`

2. **Devtools preset** (optional; allowlisted, NOT a plugin system)

- only if the preset package is installed (detected via `Composer\InstalledVersions::isInstalled('coretsia/devtools-cli-spikes')`)
- preset file path is derived from the selected `$autoloadFile`:
  - `$vendorDir = dirname($autoloadFile)`
  - preset path: `$vendorDir . '/coretsia/devtools-cli-spikes/config/cli.php'`

3. **Skeleton overrides**

- `skeleton/config/cli.php`
- if `skeleton/` directory is missing: treated as empty overlay (no error)
- if `skeleton/config/cli.php` is missing: treated as empty overlay (no error)

### Merge algorithm (cemented)

- `cli.commands` uses **append-unique** preserving **first occurrence order**:
  - apply sources in order: defaults → preset → skeleton
  - remove duplicates deterministically by keeping the first occurrence
- all other `cli.*` keys:
  - higher-precedence values override lower-precedence values
  - lists (except `cli.commands`) are **replaced** (no implicit list merge)

## Command model

Configured commands MUST implement:

- `Coretsia\Contracts\Cli\Command\CommandInterface`

### Built-in reserved names

The names `help` and `list` are reserved for built-ins.
If a configured command returns a reserved name:

- CLI MUST fail deterministically with:
  - code: `CORETSIA_CLI_COMMAND_INVALID`
  - reason token: `reserved-command-name`

### Instantiation policy (Phase 0)

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
- JSON output MUST be a single line + trailing `\n` (no pretty print in Phase 0)
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

Phase 0 CLI errors are **code-first** and deterministic.
This package owns its CLI error codes (NOT the spikes registry):

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

Phase 0 CLI base is intentionally minimal.

- No tracing/metrics/logging ports are required here.
- Any diagnostics are user-facing and MUST respect:
  - determinism (stable bytes)
  - security (no secrets/PII)
  - path-safety (no absolute paths)

## Non-goals (Phase 0)

- vendor-only install UX guarantees (`vendor/bin/coretsia`) are out of scope
- DI/container integration (tag discovery, autowire) is out of scope
- plugin system is out of scope (only allowlisted preset merge is permitted)
