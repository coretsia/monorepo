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

`platform/cli` is the Kernel-integrated, tag-first command runtime and canonical command-line interface for the Coretsia Framework.

It discovers commands from enabled package providers through the Foundation `cli.command` tag, dispatches a selected command within the Kernel UnitOfWork lifecycle, exposes Kernel-owned operations through the contracts-level Ops port, and renders deterministic, safe output with mandatory shared redaction.

Scope: canonical CLI invocation, source operations-host bootstrap, tag-backed command catalog, descriptor-driven input validation, lazy command dispatch, built-in commands, Kernel Ops transport, invocation-local output buffering, deterministic JSON/table/plain formatting, terminal color policy, CLI execution observability, and safe failure reporting.

Out of scope: application module selection or dependency synchronization, Kernel configuration loading and compilation, Kernel artifact inspection, owner-package domain validation, owner-package command implementations, Kernel lifecycle hooks or reset orchestration, redaction engine implementation, observability exporters, and repository tooling.

This README is a consumer-oriented package summary.

## Package identity

- Path: `packages/platform/cli`
- Package id: `platform/cli`
- Composer name: `coretsia/platform-cli`
- Module id: `platform.cli`
- Namespace: `Coretsia\Platform\Cli\*` (PSR-4: `src/`)
- Kind: runtime
- Config root: `cli`
- Packaged CLI executable: `bin/coretsia`

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions MUST NOT be used.

## Dependency policy

This package is a Kernel-integrated source-host CLI runtime.

- Direct Composer requirements:
  - PHP `^8.4`
  - `coretsia/core-contracts`
  - `coretsia/core-foundation`
  - `coretsia/core-kernel`
  - `coretsia/platform-redaction`
  - `psr/container` and `psr/log`
- Direct PHP source dependencies:
  - `core/contracts`
  - `core/foundation`
  - `core/kernel`, restricted to approved runtime and host-bootstrap APIs
- Forbidden production implementation dependencies:
  - concrete `platform/redaction` implementation classes;
  - `platform/worker` or any other command-owning runtime/integration package solely for command discovery or dispatch;
  - Kernel artifact compilers, generation locators, artifact readers, module-planning internals, or provider-plan resolvers;
  - third-party console frameworks, filesystem command discovery, and repository machinery under `tools/**`.

`platform/redaction` is required in the Composer graph and through the `platform.cli` runtime module dependency closure. CLI consumes redaction only through `Coretsia\Contracts\Security\SensitiveDataRedactorInterface`; Composer installation does not grant permission to import concrete Redaction classes.

The `coretsia/framework` baseline explicitly installs Kernel and CLI; Redaction is installed transitively through CLI. Installing a Composer package does not enable its runtime module. Kernel resolves the enabled modules for each application target from the effective preset, overrides, and declared module dependencies. Additional mode-required physical packages are handled only by explicit Kernel DependencySync `plan` / `review` / `apply` operations; application boot performs no implicit dependency synchronization.

## Runtime responsibilities

`platform/cli` provides:

- one packaged `coretsia` executable and package-owned CLI composition root;
- canonical Console source operations-host bootstrap through `CliHostBootstrap` and `KernelOpsHostBooter`;
- immutable `CommandCatalog` descriptors derived from Foundation `TagRegistry` output;
- command metadata, catalog-view overrides, and structural argument/option validation;
- invocation-local `ArgvInput`, output buffer, and terminal capabilities;
- lazy selected-command resolution by `CommandRunner` within exactly one Kernel CLI UnitOfWork;
- built-in `doctor`, `list`, and `help` commands;
- `debug:modules`, `config:validate`, `config:debug`, `config:compile`, `config:hash`, and `cache:verify` through `KernelOpsInterface`;
- safe, deterministic JSON, table, and plain output through one final console writer;
- CLI-owned tracing, metrics, safe completion logs, and fixed error diagnostics.

Normal CLI execution reuses the Kernel-owned source operations host and its validated configuration, module/provider plan, context, UnitOfWork, and observability ports. CLI does not create a second configuration or module-resolution pipeline, and normal operations-host boot does not require generated runtime artifacts.

## Canonical CLI invocation

`coretsia` is the single canonical public CLI executable name.

From the monorepo root:

```bash
php coretsia list
php coretsia help
php coretsia doctor
php coretsia config:validate --target=web
```

From a Composer-installed Coretsia Skeleton project root:

```bash
php coretsia list
php coretsia help
php coretsia doctor
php coretsia config:validate --target=web
```

The direct Composer binary invocation from the consumer project is:

```bash
php vendor/bin/coretsia <command>
```

On supported Unix environments, when the Composer binary is directly executable:

```bash
vendor/bin/coretsia <command>
```

These supported invocations reach the same packaged CLI implementation at `packages/platform/cli/bin/coretsia` (installed as the `coretsia/platform-cli` Composer binary). The entrypoint layouts have distinct responsibilities:

- Monorepo-root `coretsia` wrapper: delegates by a fixed repository-relative path to the packaged CLI executable; it is not a command dispatcher and does not depend on the current working directory.
- Skeleton project-root `coretsia` wrapper: delegates only to `__DIR__ . '/vendor/bin/coretsia'`, preserving the Composer proxy's consumer application-root semantics and original argv. It performs no command parsing, configuration loading, application boot, or rendering.
- Composer binary proxy (`vendor/bin/coretsia`): provides the installed package executable and Composer proxy context used to identify the consuming application root.
- Packaged CLI executable (`bin/coretsia`): loads the selected Composer autoloader and invokes the package-owned composition root `src/Bootstrap/Entrypoint/cli.php`, which selects the ultra-early doctor path or the normal Console source host.

The Skeleton wrapper emits `CORETSIA_CLI_BOOTSTRAP_FAILED: autoload-unavailable` followed by one LF to stderr and exits `1` if the installed Composer binary is missing or unreadable. No additional CLI package installation, executable alias, or CLI-specific setup is required after a successful baseline Composer installation.

## Boot paths and Kernel operations

The exact `coretsia doctor` invocation uses `UltraEarlyDoctorRunner` and the same `DoctorCommand` class advertised in the normal catalog. It performs bounded, allowlisted diagnostics without constructing the Kernel host, catalog, generated-artifact runtime, UnitOfWork, or shared Redaction service. Its output is fixed plain text with ANSI disabled.

Every other normal invocation follows:

```text
bin/coretsia
    -> Composer autoload
    -> src/Bootstrap/Entrypoint/cli.php
    -> CliHostBootstrap
    -> KernelOpsHostBooter
    -> validated Console source operations container
    -> Console ModulePlan verification
    -> CliApplication
    -> parse and validate input
    -> CommandCatalog descriptor
    -> CommandRunner / Kernel CLI UnitOfWork
    -> lazy selected-command service resolution and execution
    -> buffered output, shared redaction, deterministic formatting
    -> ConsoleOutputWriter
```

The normal source host MUST resolve `ModulePlan` for `AppTarget::Console` and require `platform.cli` to be enabled. An absent or explicitly excluded CLI module fails closed with a fixed safe bootstrap diagnostic. Selecting `platform.cli` brings `core.kernel` and `platform.redaction` into the enabled module dependency closure; a Composer-installed but unselected module does not contribute its runtime services.

Built-in Kernel operation commands include:

```text
debug:modules --target=<target>
config:validate --target=<target>
config:debug --target=<target>
config:compile --target=<target>
config:hash --target=<target>
cache:verify --target=<target>
```

`<target>` must be explicitly supplied as `web`, `api`, `console`, or `worker`. The CLI never infers the operation target from the current directory, Console host, or command name. Built-in Kernel operation descriptors accept `--target` only; `--mode` and `--preset` are not accepted as Kernel operation overrides.

`KernelOpsRequestResolver` builds `KernelOpsRequest` with only the selected `appTarget`. The Kernel resolves the effective preset through its normal Bootstrap policy: target-specific `presets[appTarget]`, then global `preset`, then `kernel.boot.default_preset` (`micro` by default). CLI renders the effective preset returned by the safe `OpsResult`; it does not select or override that preset itself.

The Kernel owns module resolution, compilation, hashes, generation publication, and cache verification. Built-in CLI commands consume only `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface` and its safe result contract; they do not read `current`, generation manifests, compiled config, container artifacts, or other Kernel artifact files directly.

## Command discovery and catalog

`cli.command` is the sole normal container-backed command discovery tag. Foundation `ReservedTags::CLI_COMMAND` supplies the tag identifier; `platform/cli` owns its command metadata and dispatch semantics.

Each tagged service id MUST equal the exact command-class FQCN. The class must be concrete, implement `Coretsia\Contracts\Cli\Command\CommandInterface`, and declare the following typed public constants:

```text
string NAME
string SUMMARY
string GROUP
bool   HIDDEN
array  ARGUMENTS
array  OPTIONS
```

The tag metadata must have exactly these six keys, with values derived from the corresponding class constants:

```text
name
summary
group
hidden
arguments
options
```

Argument descriptors use exactly `name`, `summary`, `required`, and `variadic`; option descriptors use exactly `name`, `summary`, `value`, and `repeatable`. Option `value` is `none`, `required`, or `optional`. Invalid or unknown metadata keys, including top-level `priority` or `mode`, are rejected. The actual `TaggedService::priority()` must be `0`; any non-zero priority fails before metadata validation.

`CommandTagSchema` validates exact identity, class constants, schema and constant-to-metadata equality by class reflection without resolving or instantiating command services. `CommandCatalog` retains the deterministic output of `TagRegistry::all(ReservedTags::CLI_COMMAND)`: priority descending, then service id in `strcmp` order; CLI neither re-sorts nor re-deduplicates it. TagRegistry keeps the first registration of each `(tag, serviceId)`. Distinct service ids claiming the same command name fail deterministically.

The names `doctor`, `help`, and `list` are reserved for their exact built-in service identities. `list` and `help` render catalog descriptors without resolving every command service. The selected service is resolved only during dispatch; its runtime type, exact class identity, and `name()` must agree with its descriptor.

## Providing commands from another package

Any enabled package may register its own command through `cli.command`. The owner package registers both the command service (under its exact class FQCN) and the tag in its own provider. The service may receive constructor-injected domain dependencies from its owner package; the CLI catalog does not construct command services.

For example, an owner package declaring `InspectCommand` registers its service through the canonical Foundation container definitions and tags the same FQCN with the class-owned metadata:

```php
$definitions
    ->classService(
        InspectCommand::class,
        InspectCommand::class,
        [ContainerValueReference::service(OwnerServiceInterface::class)],
    )
    ->tag(
        ReservedTags::CLI_COMMAND,
        InspectCommand::class,
        meta: [
            'name' => InspectCommand::NAME,
            'summary' => InspectCommand::SUMMARY,
            'group' => InspectCommand::GROUP,
            'hidden' => InspectCommand::HIDDEN,
            'arguments' => InspectCommand::ARGUMENTS,
            'options' => InspectCommand::OPTIONS,
        ],
    );
```

The example belongs in the command owner's declarative definition provider, with the relevant Foundation imports and its domain service binding in place. This is a registration illustration, not an additional CLI registry.

Owner-package command contracts are:

```text
Coretsia\Contracts\Cli\Command\CommandInterface
Coretsia\Contracts\Cli\Input\InputInterface
Coretsia\Contracts\Cli\Output\OutputInterface
```

`CommandInterface::run(InputInterface $input, OutputInterface $output): int` returns an exit code in `0..255`. The owner package defines positional arguments, options, command dependencies, and domain-specific validation; CLI performs structural validation against the selected descriptor before service resolution. Options support bare flags and valued options (`--name=value`), and repeatable required-value options preserve input order. CLI-global `--format` and `--color` are not owner-command options. Owner-domain options such as `--mode` may be declared by the owner but do not alter Kernel Bootstrap preset selection.

No changes to `platform/cli` or imports of the owner package are necessary for generic command discovery. A command package is visible only when Kernel's resolved module/provider graph enables its contributing provider.

## Configuration

The config root is `cli`. The canonical defaults file is `config/cli.php`, which returns the `cli` subtree only, without a repeated root key:

```php
return [
    'commands' => [
        'overrides' => [],
    ],
    'output' => [
        'format_default' => 'adaptive',
        'adaptive' => [
            'interactive' => 'table',
            'non_interactive' => 'plain',
        ],
        'color_default' => 'auto',
        'table' => [
            'max_width' => 120,
        ],
    ],
];
```

Kernel's canonical config pipeline loads, merges, and validates the global `cli.*` subtree. `config/rules.php` defines the strict schema: unknown structural keys are rejected; only `cli.commands.overrides` permits dynamic command-name keys, subsequently validated by `CommandOverrides`. CLI does not read application PHP config files or construct a separate config merge pipeline.

The configuration consumers are:

- `CliServiceFactory::commandCatalog()` reads `cli.commands.overrides` and passes it to `CommandOverrides`. Each existing command may override only catalog-view `summary`, `hidden`, or `group`; command name, arguments, options, service id, and original tag metadata remain unchanged.
- `CliServiceFactory::cliOutputPolicy()` constructs immutable `CliOutputPolicy` from `cli.output.*`; `FormatResolver` and `ColorResolver` combine it with invocation-local CLI-global options and terminal capabilities.
- `TableFormatter` uses `cli.output.table.max_width` (integer `40..240` display columns); table width is independent of the actual terminal width.

Valid `cli.output.format_default` values are `adaptive|json|table|plain`. The adaptive interactive and non-interactive settings accept `json|table|plain`. Valid `cli.output.color_default` values are `auto|always|never`.

The explicit CLI-global `--format=<adaptive|json|table|plain>` and `--color=<auto|always|never>` options take precedence over their respective configured defaults. They are parsed only before the `--` end-of-options marker and are removed from the command-facing input. Repeated or malformed global options fail deterministically. Tokens after `--` remain positional arguments, even if they look like global options.

`adaptive` chooses `cli.output.adaptive.interactive` when stdout is interactive, otherwise `cli.output.adaptive.non_interactive`. Terminal capabilities are detected once at the normal entrypoint boundary, not by commands or formatters.

There are no configurable switches for redaction, UnitOfWork, reset, observability, executable aliases, color palettes, or arbitrary ANSI codes. CLI configuration does not select the application target or effective Kernel preset.

## Output and determinism

Commands report output exclusively through `Coretsia\Contracts\Cli\Output\OutputInterface`. `CommandOutputBuffer` stores ordered `text`, `json`, and `error` records locally for one invocation; it never writes directly to stdout or stderr and is not a shared container service.

The pipeline is:

```text
owner command -> CommandOutputBuffer -> CommandOutputBatch
    -> mandatory shared redaction -> JSON | table | plain formatter
    -> FormattedOutput -> ConsoleOutputWriter
```

The output contract enforces:

- one deterministic render of the finalized batch; record and list order are preserved;
- normalized LF line endings, validated Unicode/control characters, and Foundation-owned bounded JSON-like normalization;
- recursively `strcmp`-sorted JSON-like map keys without reordering lists;
- maximum `1024` buffered records and `1048576` aggregate canonical record bytes per invocation;
- stable JSON envelope `coretsia.cli-output@1`, with `schema`, `meta` (`command`, `outcome`, `exit_code`), and `data.records`;
- a compact stable JSON document (not pretty-printed), with no ANSI and one final LF;
- deterministic table layout bounded by configured display-column width, and fixed plain-text rendering;
- separate stdout/stderr payloads; the final `ConsoleOutputWriter` exclusively appends one trailing LF to each non-empty payload.

Formatters receive normalized, redacted records rather than raw command payloads. The JSON envelope itself is constructed from safe, fixed CLI-owned fields after record redaction. A formatting or redaction failure discards unsafe output and produces a single fixed safe fallback; it never retries rendering through a less restrictive pipeline.

## Colors

Color policy accepts exactly `auto`, `always`, or `never`, through `cli.output.color_default` and the CLI-global `--color` override.

- `auto`: use ANSI only when stdout is interactive and both stdout and stderr support ANSI safely.
- `always`: request ANSI formatting for eligible plain/table presentation, independently of automatic terminal detection.
- `never`: disable ANSI.
- `json`: always ANSI-free, including when `--color=always` is provided.

`TerminalCapabilitiesDetector` is the only CLI component probing stdout/stderr interactivity and ANSI support. ANSI decoration uses the fixed internal semantic palette:

```text
heading -> 1
success -> 32
warning -> 33
error   -> 31
muted   -> 2
```

Only formatter-owned semantic roles can generate escape sequences. Command output and configuration cannot inject raw ANSI codes or define custom escape sequences or palettes. Ineligible formats and disabled color emit no ANSI sequences.

## Context and UnitOfWork

`CommandRunner` executes each selected normal command inside exactly one `KernelRuntimeInterface::runUnitOfWork(UnitOfWorkType::CLI, ...)`. The command service is resolved and invoked inside that callback, not while constructing the catalog. `list` and `help` are normal commands under this lifecycle; the exact ultra-early `doctor` path does not enter a UnitOfWork.

CLI supplies only these safe UnitOfWork attributes:

```text
operation     = canonical command name
output_format = effective json | table | plain
```

KernelRuntime owns UnitOfWork context writes (`ContextKeys::CORRELATION_ID`, `ContextKeys::UOW_ID`, `ContextKeys::UOW_TYPE`), hook execution, reset orchestration, and failure precedence. CLI does not write `ContextStore`, invent correlation/UoW ids, enumerate `kernel.reset`, invoke reset services, or store a CLI-specific output format in context.

For optional log correlation, `CommandRunner` reads only `CORRELATION_ID` and `UOW_ID` through `ContextAccessorInterface::has()` / `get()`, inside the UnitOfWork callback before Kernel cleanup. Missing or invalid safe identifiers are omitted without affecting execution. Invocation-local output buffers and immutable catalog/output-policy services introduce no shared mutable state requiring CLI-owned resets; any future shared mutable service must follow the canonical `ResetInterface` discipline.

## Observability

`CommandRunner` owns CLI command-execution telemetry, separate from Kernel-owned `kernel.uow` and `kernel.operation` observability. No CLI formatter, writer, or built-in Kernel operation command duplicates Kernel operation telemetry.

The canonical command span name is:

```text
cli.command
```

Its initial attributes are `operation` and `format`. The final attributes are `operation`, `format`, `outcome`, and `exit_code`.

The canonical CLI command metrics are:

```text
cli.command_total
cli.command_duration_ms
```

Both metrics allow only `operation` and `outcome` labels. Command duration is observed in integer milliseconds.

The safe completion log message is:

```text
cli.command
```

Completion logs include `operation`, `outcome`, and `exit_code`, with optional `correlation_id` and `uow_id`.

`operation` is the canonical descriptor command name, `format` is the effective `json|table|plain` presentation format, and `outcome` is `success` for exit code `0` or `failure` for a non-zero return or thrown command failure. Kernel CLI UnitOfWork attributes use `output_format`, which is distinct from the command span's `format` attribute. Span `exit_code` is not a metric label. Metric labels MUST NOT include output format, exit code, target, preset, correlation/UoW identifiers, or unbounded values.

The command span begins before the Kernel UnitOfWork call and ends after its lifecycle completes. `Stopwatch` measures elapsed time with a non-negative integer `0` fallback when unavailable. Trace start, attribute update, end, metric increment/observation, logger, stopwatch, and optional context-read failures are independently best-effort: they never skip or repeat command execution, replace the primary failure, alter exit codes, or interfere with Kernel-owned hooks and reset. The ultra-early `doctor` path emits no normal command telemetry.

Raw argv, option or argument values, command output, paths, endpoints, config/env values, payloads, secrets, and exception traces are forbidden in CLI telemetry.

## Errors

CLI reports stable, path-safe error codes and bounded reason tokens rather than raw Throwable messages, stack traces, command-line contents, configuration values, or filesystem diagnostics. Known CLI and Kernel Ops port failures map through `CliErrorHandler` to safe `ErrorDescriptor` values; unknown execution errors become `CORETSIA_CLI_INTERNAL_ERROR`. The normal command failure boundary discards partial output before emitting a deterministic error record.

The main CLI error code families are:

- `CORETSIA_CLI_BOOTSTRAP_FAILED`: packaged entrypoint/bootstrap layout validation.
- `CORETSIA_CLI_HOST_BOOT_FAILED`: Console source host, module plan, or CLI application resolution.
- `CORETSIA_CLI_INPUT_INVALID`: command input, arguments, options, or target validation.
- `CORETSIA_CLI_CONFIG_INVALID`: CLI output policy or catalog-view configuration.
- `CORETSIA_CLI_INVALID_COMMAND_META`: tag schema, priority, reserved name, or command identity.
- `CORETSIA_CLI_COMMAND_FAILED`: invalid command execution result or exit-code contract.
- `CORETSIA_CLI_INTERNAL_ERROR`: unrecognized normal-path failure.
- `CORETSIA_CLI_RENDER_FAILURE`: unsafe/unavailable diagnostics, formatter, redactor, or output fallback.
- `CORETSIA_CLI_UNCAUGHT_EXCEPTION`: uncaught post-autoload entrypoint failure.

The packaged CLI and Skeleton wrapper use the fixed pre-autoload/bootstrap fallback `CORETSIA_CLI_BOOTSTRAP_FAILED: autoload-unavailable` when their required Composer autoload/binary path is not available. Console source-host failure emits `CORETSIA_CLI_HOST_BOOT_FAILED: host-boot-failed`. A final rendering failure emits `CORETSIA_CLI_RENDER_FAILURE: render-failure`. These diagnostics do not disclose Throwable details or paths.

Command exit codes are integers in `0..255`. The built-in Kernel operation mapping is:

```text
0  successful command or positive operation result;
   config:validate valid=true; cache:verify clean
1  invalid input/config, handled Kernel operation failure;
   config:validate valid=false; unexpected execution/bootstrap failure
2  cache:verify dirty
3  cache:verify invalid
```

The `cache:verify` statuses come only from safe Kernel `OpsResult` data, never direct artifact inspection. Non-built-in owner commands select their own valid `0..255` exit codes; CLI preserves those returns without reinterpreting their domain semantics. An out-of-range return fails closed.

## Security / Redaction

Redaction is mandatory for every normal CLI output batch and cannot be disabled through configuration or command options. `OutputFormatter` consumes `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` supplied by the selected `platform.redaction` module provider and applies `RedactionContext('cli.output', RedactionMode::Placeholder)` to text, JSON-like payloads, and safe error messages before rendering.

Redaction is defense in depth, not permission to pass secrets or raw sensitive data through a public result. Kernel `OpsResult` remains safe by construction; Kernel owns the classification and disclosure boundaries for configuration, manifests, artifacts, paths, and environment data. CLI must never attempt to sanitize raw Kernel artifacts or secret-bearing configuration by formatting them.

Command output normalization rejects unsafe control characters, raw ANSI input, malformed JSON-like data, unsafe map keys, and oversized record batches before final publication. CLI diagnostics, telemetry, and built-in operations must not expose credentials, personal data, request/response bodies, raw command argv, absolute paths, environment values, previous exceptions, or stack traces. Redaction or formatting failures fail closed with a fixed safe diagnostic and no unsafe fallback output.

The ultra-early `doctor` uses a fixed allowlist of booleans and fixed diagnostic records, requires no shared redactor, and must not output raw environment values, paths, or runtime internals.

## Non-goals

This package does not provide:

- an independent Composer package resolver, preset distribution, or implicit dependency synchronization;
- a separate module plan, provider planner, configuration loader, or artifact compiler;
- filesystem scanning or unrelated command-owner package dependencies for discovery;
- command-owner domain services, validation, or execution policies;
- direct reads of Kernel generation artifacts or compiled runtime containers;
- a shared mutable command-output singleton or CLI-owned context/reset orchestration;
- an independent sensitive-data classifier, redaction policy engine, or configurable redaction disable switch;
- custom ANSI palettes, raw ANSI command output, or additional framework-owned executable aliases;
- tracing/metrics exporters, Kernel operation telemetry duplication, or direct repository tooling dependencies.

## References

- [Coretsia](https://coretsia.dev/)
- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Platform CLI package source](https://github.com/coretsia/monorepo/tree/main/packages/platform/cli)
- [CLI Tag Registry SSoT](https://github.com/coretsia/monorepo/tree/main/docs/ssot/tags.md)
- [Mode Presets SSoT](https://github.com/coretsia/monorepo/tree/main/docs/ssot/modes.md)
- [Application Dependency Sync SSoT](https://github.com/coretsia/monorepo/tree/main/docs/ssot/application-dependency-sync.md)
- [Observability SSoT](https://github.com/coretsia/monorepo/tree/main/docs/ssot/observability.md)
- [Sensitive Data Redaction SSoT](https://github.com/coretsia/monorepo/tree/main/docs/ssot/sensitive-data-redaction.md)
- [ADR-0034: CLI Tag-first Command Catalog](https://github.com/coretsia/monorepo/tree/main/docs/adr/ADR-0034-cli-tag-first-command-catalog.md)
