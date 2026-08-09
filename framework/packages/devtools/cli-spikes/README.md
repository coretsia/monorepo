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

`devtools/cli-spikes` is the development-only deterministic command-adapter package for the Coretsia CLI.

Scope: thin CLI adapters for repository diagnostics and tools-only spike workflows, deterministic bootstrap into `framework/tools/spikes/**`, safe repository-relative path presentation, binary exit-code mapping, and config-based registration of development-only commands.

Out of scope: spike algorithm implementation, production runtime commands, Kernel integration, container integration, tag-based command discovery, platform CLI infrastructure ownership, dynamic plugin discovery, runtime lifecycle behavior, and production application tooling.

## Package identity

- Path: `framework/packages/devtools/cli-spikes`
- Package id: `devtools/cli-spikes`
- Composer name: `coretsia/devtools-cli-spikes`
- Namespace: `Coretsia\Devtools\CliSpikes\*` (PSR-4: `src/`)
- Kind: library
- Lifecycle: development-only
- Config contribution: `cli.commands`

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

The corresponding split repository is `coretsia/devtools-cli-spikes` and receives the same tag for the package subtree.

Per-package independent versions MUST NOT be used.

## Dependency policy

This package is development-only.

It MUST be installed by the Coretsia framework workspace through `require-dev`, not through production `require`.

- Depends on:
  - `core/contracts`
  - `platform/cli`
- Suggested only:
  - `devtools/internal-toolkit`
- Forbidden as runtime ownership dependencies:
  - `core/kernel`
  - `core/foundation`
  - production runtime orchestration packages
  - integrations used as command execution infrastructure

`core/contracts` provides the public CLI command, input, and output boundaries.

`platform/cli` owns CLI infrastructure and base CLI error codes.

`devtools/internal-toolkit` is optional and MUST NOT be assumed to exist unless declared as an operational dependency.

`devtools/cli-spikes` MUST NOT depend on Kernel boot, container wiring, runtime lifecycle orchestration, or Kernel-backed discovery.

Production runtime packages MUST NOT depend on this package.

In particular, `platform/cli` MUST remain usable without `devtools/cli-spikes` and MUST NOT ship or register these commands directly.

## Package responsibilities

This package owns the development CLI adapter layer between:

```text
Coretsia CLI contracts
        ↓
devtools/cli-spikes command adapters
        ↓
SpikesBootstrap
        ↓
framework/tools/spikes/**
```

Its responsibilities are limited to:

- exposing devtools command classes through `CommandInterface`;
- parsing the narrow command-local options owned by each adapter;
- validating CLI-facing argument shape;
- resolving the canonical tools-spikes bootstrap path;
- dispatching to tools-side workflow entrypoints;
- validating returned workflow result shape before presentation;
- mapping package-owned bootstrap failures to safe CLI diagnostics;
- forwarding deterministic tools-side failures where appropriate;
- emitting user-visible data only through `OutputInterface`;
- preserving deterministic binary exit semantics;
- preventing absolute-path and sensitive-value leakage at the adapter boundary.

The package MUST NOT become an alternate implementation home for tools-spikes algorithms.

## Ownership boundaries

`devtools/cli-spikes` owns command adaptation, not the business logic behind the commands.

The actual tools-side workflows remain under:

```text
framework/tools/spikes/**
```

Command classes MUST remain thin adapters.

They MUST NOT duplicate or reimplement:

- fingerprint calculation;
- config merge or config explanation;
- Deptrac graph artifact construction;
- workspace synchronization engines;
- tools-side fixture loading;
- tools-side repository algorithms.

Canonical workflow delegation currently includes:

```text
FingerprintWorkflow
ConfigDebugWorkflow
DeptracGraphWorkflow
WorkspaceSyncEntryWorkflow
```

Workspace commands MUST delegate through `WorkspaceSyncEntryWorkflow`.

They MUST NOT call lower-level workspace synchronization engines directly.

`platform/cli` owns:

- CLI launcher infrastructure;
- command catalog infrastructure;
- input implementation;
- output implementation;
- base CLI error codes;
- generic command execution behavior.

`devtools/cli-spikes` owns only its registered command adapters and spike bootstrap support.

## CLI contract

Every registered command:

- MUST be `final`;
- MUST implement `Coretsia\Contracts\Cli\Command\CommandInterface`;
- MUST expose `name(): string`;
- MUST expose `run(InputInterface $input, OutputInterface $output): int`;
- MUST accept exactly the contracts-owned input and output ports;
- MUST return an integer process exit code;
- MUST have a unique command name.

Commands MUST NOT write directly to stdout or stderr.

The only user-visible command output boundary is:

```text
Coretsia\Contracts\Cli\Output\OutputInterface
```

Direct use of output mechanisms such as:

```text
echo
print
php://stdout
php://stderr
php://output
```

is forbidden in command implementations.

## Provided commands

The package currently contributes six development command adapters.

### `doctor`

```text
coretsia doctor
```

`doctor` validates the tools-spikes bootstrap boundary and emits safe structural path diagnostics.

Returned paths are presented repository-relative.

Absolute local paths MUST NOT be exposed.

### `spike:fingerprint`

```text
coretsia spike:fingerprint
```

The command delegates to the tools-side:

```text
Coretsia\Tools\Spikes\fingerprint\FingerprintWorkflow
```

It MUST NOT implement fingerprint calculation locally.

Successful output contains safe structural data including:

- command id;
- success state;
- repository-relative fixture root;
- fingerprint;
- bucket digests.

Fingerprint and bucket digest values are expected to be lowercase 64-character hexadecimal SHA-256 values.

Raw tracked environment values MUST NOT be emitted.

### `spike:config:debug`

```text
coretsia spike:config:debug --key=<dot.key> [--scenario=<scenario-id>]
```

The command delegates to:

```text
Coretsia\Tools\Spikes\config_merge\ConfigDebugWorkflow
```

It MUST NOT implement config merging, explanation, fixture loading, or trace construction locally.

`--key` is required.

The default scenario is:

```text
baseline.defaults_only.all_middleware_slots_present
```

The command emits a stable JSON payload containing the workflow-produced safe config-debug result.

Raw config values that are not part of the tools-side safe result contract MUST NOT be reconstructed or exposed by the command.

### `deptrac:graph`

```text
coretsia deptrac:graph [--fixture=<fixture>] [--out=<repo-relative-path>] [--json]
```

The command delegates to:

```text
Coretsia\Tools\Spikes\deptrac\DeptracGraphWorkflow
```

The default fixture is:

```text
deptrac_min/package_index_ok.php
```

The default output directory is:

```text
framework/tools/spikes/_artifacts/deptrac_graph
```

Explicit fixtures MUST remain under:

```text
deptrac_min/
```

The output directory MUST be repository-relative and MUST NOT escape through `..`.

The workflow currently produces:

```text
deptrac_graph.dot
deptrac_graph.svg
deptrac_graph.html
```

The command MUST NOT implement graph artifact building locally.

### `workspace:sync --dry-run`

```text
coretsia workspace:sync --dry-run
```

The command delegates to:

```text
Coretsia\Tools\Spikes\workspace\WorkspaceSyncEntryWorkflow
```

Supported adapter options include:

```text
--fixture=<fixture>
--format=json
--format=text
--json
--text
```

JSON is the default output format.

When no fixture is supplied, the target is the repository workspace.

When a fixture is supplied, its name is constrained to the supported simple fixture identifier shape and resolves under:

```text
framework/tools/spikes/fixtures/
```

Dry-run mode MUST NOT be replaced by direct access to a lower-level workspace engine.

### `workspace:sync --apply`

```text
coretsia workspace:sync --apply
```

The command delegates to the same tools-side entry workflow with apply mode enabled.

Apply mode targets the resolved repository workspace.

The command adapter MUST NOT independently implement synchronization behavior.

## Command registration

The package contributes commands through:

```text
framework/packages/devtools/cli-spikes/config/cli.php
```

This file contributes the `cli` configuration subtree.

It MUST return:

```php
return [
    'commands' => [
        CommandClass::class,
    ],
];
```

It MUST NOT repeat the configuration root:

```php
return [
    'cli' => [
        'commands' => [],
    ],
];
```

The current canonical command-class list is:

```text
Coretsia\Devtools\CliSpikes\Command\DoctorCommand
Coretsia\Devtools\CliSpikes\Command\SpikeFingerprintCommand
Coretsia\Devtools\CliSpikes\Command\SpikeConfigDebugCommand
Coretsia\Devtools\CliSpikes\Command\DeptracGraphCommand
Coretsia\Devtools\CliSpikes\Command\WorkspaceSyncDryRunCommand
Coretsia\Devtools\CliSpikes\Command\WorkspaceSyncApplyCommand
```

Command discovery for this package is configuration-based.

This package does not use or own tag-based command discovery.

## Tools-spikes bootstrap

Commands dispatch to tools-only code through the canonical bootstrap boundary:

```text
Coretsia\Devtools\CliSpikes\Spikes\SpikesBootstrap
```

The tools-side bootstrap path is:

```text
framework/tools/spikes/_support/bootstrap.php
```

`SpikesBootstrap` MUST:

- load exactly the path resolved by `SpikesPaths`;
- use `require_once`;
- avoid probing alternate bootstrap locations;
- avoid fallback discovery;
- emit no stdout or stderr.

Composer autoload MUST already be loaded by the CLI launcher before the tools-spikes bootstrap is required.

`SpikesBootstrap` validates that state without triggering implicit autoloading.

If Composer autoload is unavailable, bootstrap fails deterministically.

## Launcher and path resolution

Canonical path resolution is owned by:

```text
Coretsia\Devtools\CliSpikes\Spikes\SpikesPaths
```

Launcher-path selection is single-choice:

```text
1. $_SERVER['SCRIPT_FILENAME']
2. $_SERVER['argv'][0]
```

Resolution MUST NOT search the filesystem for alternate launchers.

The package supports the canonical launcher forms:

```text
<repo-root>/coretsia
<repo-root>/framework/bin/coretsia
```

From the resolved launcher, `SpikesPaths` derives:

- framework root;
- repository root;
- tools-spikes bootstrap path;
- tools-spikes fixtures root.

Failure to resolve these boundaries MUST fail deterministically instead of triggering repository probing.

## Safe display paths

Internal tooling may need absolute paths for local filesystem operations.

Those paths MUST NOT cross the user-visible CLI output boundary.

`SpikesPaths::displayPath()` converts paths to canonical repository-relative display form.

Display paths MUST:

- use forward slashes;
- never expose an absolute prefix;
- remain within the repository root;
- contain no unresolved `.` segments;
- contain no unresolved `..` segments;
- reject attempts to escape the repository root.

The repository root itself is displayed as:

```text
.
```

Windows path containment follows case-insensitive filesystem comparison semantics.

Display-path normalization is a presentation-safety boundary, not a general filesystem abstraction.

## Workflow dispatch

Every command in this package MUST dispatch through:

```text
SpikesBootstrap
```

Commands MUST NOT directly require the tools bootstrap file.

The bootstrap boundary exists to keep:

- launcher assumptions;
- Composer-autoload validation;
- canonical path resolution;
- tools bootstrap loading;
- deterministic bootstrap failure mapping

in one package-owned location.

After bootstrap, commands dispatch to the relevant tools-side workflow class.

Workflow result validation in the command is limited to the output shape required for safe CLI adaptation.

Command adapters MUST NOT reproduce the underlying algorithm merely to validate its output.

## Determinism

Package commands MUST be deterministic for the same supported repository state and command input.

The command layer MUST NOT introduce:

- locale-dependent sorting;
- nondeterministic filesystem probing;
- random identifiers;
- timestamps in owned output shapes;
- machine-specific absolute paths;
- current-directory-dependent discovery;
- fallback bootstrap search;
- unbounded dynamic plugin discovery.

Tools-side workflows own their own deterministic algorithm contracts.

The command layer owns deterministic adaptation and presentation.

## Output model

All user-visible output goes through `OutputInterface`.

Commands may use:

```php
$output->json($payload);
$output->text($text);
$output->error($code, $message);
```

They MUST NOT bypass that boundary.

### JSON output

JSON payloads supplied by commands MUST contain only safe structural values required by the command contract.

Commands MUST preserve deterministic workflow ordering where ordering is semantic.

They MUST NOT expose absolute local paths or raw sensitive values.

Concrete JSON byte encoding remains the responsibility of the `OutputInterface` implementation.

### Text output

Text lines supplied to `OutputInterface` MUST be deterministic and safe.

The command adapter MUST NOT append machine-specific diagnostic material.

Final stream formatting, including the concrete newline behavior, belongs to the output implementation.

### Error output

Errors are emitted through:

```php
$output->error($code, $message);
```

Error codes and messages MUST be deterministic and redaction-safe.

## Bootstrap failures

Bootstrap failures use:

```text
Coretsia\Devtools\CliSpikes\Spikes\SpikesBootstrapFailedException
```

The public `reason()` values are restricted to:

```text
composer-autoload-missing
launcher-path-unresolvable
framework-root-unresolvable
repo-root-unresolvable
spikes-bootstrap-missing
```

The exception message is exactly the reason token.

Reason tokens:

- are lowercase deterministic tokens;
- contain no absolute path;
- contain no OS-specific error message;
- contain no dynamic filesystem data.

Unknown reason tokens are developer errors and fail with:

```text
spikes-bootstrap-invalid-reason-token
```

## Errors

This package distinguishes between CLI-owned failures and tools-side deterministic failures.

### CLI-owned failures

Failures owned by command adaptation use error codes from:

```text
Coretsia\Platform\Cli\Error\ErrorCodes
```

Examples include:

```text
CORETSIA_CLI_COMMAND_INVALID
CORETSIA_CLI_COMMAND_FAILED
CORETSIA_CLI_CONFIG_INVALID
```

`devtools/cli-spikes` MUST NOT define duplicate platform CLI base error codes.

CLI-owned failures include cases such as:

- invalid command arguments;
- invalid command-local options;
- bootstrap failure;
- unavailable workflow class;
- invalid workflow result shape;
- command-level unexpected failures.

### Tools-side failures

Tools-side spike workflows may throw deterministic tooling failures.

When the failure is already represented by an approved safe deterministic tools-spikes code and message, the command MAY forward that code and message through `OutputInterface`.

The command MUST NOT expose raw previous exception messages or reconstruct unsafe diagnostics.

Command-specific mapping MAY translate a tools-side failure into an appropriate platform CLI base error when ownership belongs to the CLI boundary.

## Exit code policy

The package uses one binary process exit policy:

```text
0 = success
1 = failure
```

The canonical mapper is:

```text
Coretsia\Devtools\CliSpikes\Spikes\SpikesExitCodeMapper
```

It exposes:

```text
SUCCESS = 0
FAILURE = 1
```

All deterministic failures, bootstrap failures, invalid command execution paths, and safely contained unexpected failures return `1`.

This package does not currently define a larger process exit-code taxonomy.

## Security / Redaction

This package MUST NOT leak sensitive or machine-specific runtime data through CLI output.

Forbidden user-visible data includes:

- `.env` values;
- `.env.local*` values;
- credentials;
- passwords;
- tokens;
- bearer tokens;
- cookies;
- `Set-Cookie` values;
- Authorization values;
- private keys;
- raw config dumps;
- raw sensitive `composer.json` content;
- absolute POSIX filesystem paths;
- Windows drive paths;
- UNC paths;
- raw `/home/...` paths;
- raw `/Users/...` paths;
- bootstrap filesystem details;
- dynamic OS failure messages.

Public output SHOULD use safe bounded representations such as:

```text
stable error codes
stable reason tokens
repo-relative paths
hashes
lengths
counts
bounded structural status values
```

Commands MUST NOT pass known-sensitive raw data to `OutputInterface` and rely on the output implementation to repair it later.

Safety is required at both boundaries:

```text
command-owned payload construction
        +
OutputInterface implementation
```

Repository-relative path normalization does not automatically make arbitrary path contents non-sensitive.

The command owner remains responsible for deciding whether a path is appropriate for public presentation.

## Observability

This package does not own runtime observability infrastructure.

It does not introduce:

- logging backends;
- tracing;
- metrics;
- profiling;
- health infrastructure;
- telemetry exporters.

Command diagnostics are emitted only through `OutputInterface`.

They MUST remain deterministic and redaction-safe.

Direct logging or telemetry MUST NOT be introduced merely to report tools-spikes command execution.

## Non-goals

This package does not provide:

- production application commands;
- production runtime dependencies;
- Kernel boot integration;
- service-container integration;
- Kernel lifecycle hooks;
- tag-based command discovery;
- dynamic command plugin discovery;
- HTTP middleware integration;
- Worker runtime behavior;
- long-running runtime reset orchestration;
- generic filesystem abstraction;
- general-purpose repository tooling algorithms;
- vendor-only standalone CLI guarantees.

The package remains a narrow development-only CLI adapter surface.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [CLI Spikes package source](https://github.com/coretsia/monorepo/tree/main/framework/packages/devtools/cli-spikes)
- [Packaging strategy](https://github.com/coretsia/monorepo/blob/main/docs/architecture/PACKAGING.md)
- [Tag Registry SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/tags.md)
- [Config Roots SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/config-roots.md)
