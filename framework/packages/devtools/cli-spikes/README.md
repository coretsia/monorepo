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

# coretsia/devtools-cli-spikes

`devtools/cli-spikes` is the **devtools-only deterministic command pack** for the Phase 0 `coretsia` CLI.

It provides command adapters for repository diagnostics, spike execution, deptrac graph rendering, and workspace sync operations.

These commands are intended for development and CI workflows only. They MUST NOT become production runtime dependencies.

## Package identity

- **Path:** `framework/packages/devtools/cli-spikes`
- **Package id:** `devtools/cli-spikes`
- **Composer name:** `coretsia/devtools-cli-spikes`
- **Namespace:** `Coretsia\Devtools\CliSpikes\*` (PSR-4: `src/`)
- **Kind:** library
- **Lifecycle:** Phase 0 devtools package

Monorepo versioning is **repo-wide only** via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions **MUST NOT** be used.

## Dependency policy

This package is devtools-only and MUST be installed through development dependencies only.

- **Depends on:**
  - `core/contracts`
  - `platform/cli`
- **Suggested only:**
  - `devtools/internal-toolkit`
- **Forbidden:**
  - `core/kernel`
  - production runtime packages as operational dependencies
  - integrations as command execution dependencies

This package may depend on `platform/cli` because Phase 0 CLI command execution is owned by `platform/cli`.

It MUST NOT depend on `core/kernel`, `core/foundation`, container wiring, runtime lifecycle orchestration, or kernel-backed discovery.

## Scope

This package owns thin command adapters only.

Commands MUST:

- implement `Coretsia\Contracts\Cli\Command\CommandInterface`;
- receive raw input through `Coretsia\Contracts\Cli\Input\InputInterface`;
- emit all user-visible output through `Coretsia\Contracts\Cli\Output\OutputInterface`;
- return deterministic process exit codes;
- avoid direct stdout or stderr writes;
- avoid leaking absolute local paths or secrets.

Commands MUST NOT duplicate spike business logic.

Tools-only spike algorithms remain under:

```text
framework/tools/spikes/**
```

## Provided commands

When this package is installed and its preset is loaded, the CLI exposes:

- `coretsia doctor`
- `coretsia spike:fingerprint`
- `coretsia spike:config:debug --key=<dot.key>`
- `coretsia deptrac:graph`
- `coretsia workspace:sync --dry-run`
- `coretsia workspace:sync --apply`

## Registration

This package ships a CLI config preset:

```text
framework/packages/devtools/cli-spikes/config/cli.php
```

The preset MUST return the `cli` subtree only.

Valid shape:

```php
return [
    'commands' => [
        CommandClass::class,
    ],
];
```

Invalid shape:

```php
return [
    'cli' => [
        'commands' => [],
    ],
];
```

Phase 0 command discovery is config-based. There is no tag-based command discovery in Phase 0.

Tag-based command discovery may exist only in a future kernel/container-backed CLI mode.

## Dispatch boundary

Commands in this package MUST NOT implement spike algorithms directly.

They dispatch through the canonical spike bootstrap boundary:

```text
Coretsia\Devtools\CliSpikes\Spikes\SpikesBootstrap
```

The bootstrap loads the tools-only spike support bootstrap:

```text
framework/tools/spikes/_support/bootstrap.php
```

Determinism and safety rules:

- no nondeterministic directory probing;
- no locale-dependent ordering;
- no absolute local paths in user-visible output;
- no direct output from command business logic;
- no process-global mutation beyond explicitly owned tooling bootstrap behavior.

## Output model

All command output goes through `OutputInterface`.

Text output MUST:

- be deterministic;
- end with a single newline;
- avoid environment-specific bytes;
- avoid raw secrets and absolute local paths.

JSON output MUST:

- be deterministic;
- use stable map key ordering where the output shape is owned by this package;
- preserve list order;
- contain safe structural diagnostics only.

The output implementation owns final redaction behavior, but commands MUST NOT pass known-sensitive raw values to output.

## Errors

Phase 0 error handling is deterministic and code-first.

Tools-only spikes under `framework/tools/spikes/**` define deterministic error codes in:

```text
framework/tools/spikes/_support/ErrorCodes.php
```

When a tool spike fails deterministically, commands SHOULD forward the stable spike code and a short safe reason to:

```php
$output->error($code, $message);
```

Failures outside spike execution, such as invalid CLI command wiring, invalid config preset shape, or generic command failures, are owned by `platform/cli`.

This package MUST NOT redefine platform CLI base error codes.

## Exit code policy

Phase 0 devtools command pack uses a binary exit policy:

- `0` for success;
- `1` for any failure.

This includes deterministic spike failures, bootstrap failures, invalid command execution, and uncaught exceptions.

## Security / Redaction

This package MUST NOT leak:

- `.env` or `.env.local*` values;
- tokens;
- passwords;
- cookies;
- authorization headers;
- private keys;
- raw config dumps;
- raw `composer.json` contents in diagnostics;
- absolute filesystem paths;
- Windows drive paths;
- UNC paths;
- `/home/` paths;
- `/Users/` paths.

Allowed diagnostics are limited to:

- stable error codes;
- short fixed reason tokens;
- repo-relative normalized paths using forward slashes;
- stable hashes or lengths instead of raw sensitive values.

## Observability

Phase 0 observability is intentionally minimal.

Commands MAY emit safe diagnostics through `OutputInterface` only.

This package does not introduce logging, tracing, metrics, health, profiler, or event ports.

Any diagnostics MUST remain deterministic and redaction-safe.

## Non-goals

This package does not provide:

- production runtime commands;
- kernel integration;
- container integration;
- tag-based command discovery;
- dynamic plugin discovery;
- HTTP middleware integration;
- runtime lifecycle hooks;
- vendor-only install UX guarantees;
- long-running runtime reset orchestration.

## References

- `docs/roadmap/ROADMAP.md`
- `docs/ssot/tags.md`
- `docs/ssot/config-roots.md`
