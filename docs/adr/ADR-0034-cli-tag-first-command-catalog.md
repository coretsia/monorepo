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

# ADR-0034: CLI Tag-First Command Catalog and Kernel Operations Consumption

```yaml
adrVersion: 1
status: pre-accepted
owner: platform/cli
```

## Context

Coretsia CLI is a source-host command runtime that must discover commands from the enabled application module graph without importing or knowing the command-owning packages. The CLI has one public executable name, `coretsia`, while command discovery, domain execution, Kernel operations, and output rendering have separate owners.

Normal command dispatch requires a deterministic catalog that can render `list` and `help` without constructing command services. Built-in commands that request Kernel-owned configuration, module, or cache operations must not take ownership of Kernel bootstrap, module planning, artifacts, or generation state.

Command output is another trust boundary. Every command producer owns the safety of the values it emits; a shared redactor provides defense in depth, not permission to disclose arbitrary source data. The CLI must render bounded, normalized output deterministically across supported formats and must never expose raw bootstrap or execution diagnostics.

The canonical flow is:

```text
coretsia
    -> Console source operations host
    -> Foundation TagRegistry (`cli.command`)
    -> CommandCatalog and CommandDescriptor
    -> descriptor-driven input validation
    -> one Kernel CLI UnitOfWork
    -> lazy command service resolution and execution
    -> invocation-local CommandOutputBuffer
    -> shared redaction and deterministic formatter
    -> ConsoleOutputWriter
```

The exact `doctor` invocation has a separate dependency-free ultra-early path. The same `DoctorCommand` is also present in the normal catalog.

## Decision

### Decision 1: Discover normal commands only through the canonical `cli.command` tag

`platform/cli` owns the command catalog and the Coretsia-reserved discovery tag:

```text
Coretsia\Foundation\Tag\ReservedTags::CLI_COMMAND
cli.command
```

Enabled command-owner packages register their own command services and contribute their own `cli.command` tags through their selected providers. `CommandCatalog` consumes `TagRegistry::all(ReservedTags::CLI_COMMAND)` in its existing deterministic order. Foundation owns first-wins behavior for repeated `(tag, serviceId)` registrations; the CLI neither sorts nor de-duplicates the returned entries.

Each tagged service id is the exact command class FQCN. That class must exist, implement `Coretsia\Contracts\Cli\Command\CommandInterface`, and provide the static metadata contract. Catalog construction reads the class metadata without resolving or instantiating the command service. `list` and `help` use descriptors alone.

Each tag has actual priority `0`. A tag with non-zero priority, an invalid service id, duplicate command name, or invalid metadata fails catalog construction deterministically. Command routing does not use priority, filesystem scans, or owner-specific discovery paths.

### Decision 2: Use the exact six-key static command metadata contract

The tag metadata has exactly these keys:

```text
name
summary
group
hidden
arguments
options
```

Each tagged command exposes matching typed public constants:

```text
NAME: string
SUMMARY: string
GROUP: string
HIDDEN: bool
ARGUMENTS: array
OPTIONS: array
```

The `name` must match `\A[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)*\z`. The service id, class constants, and tag metadata must agree. The command's `name()` must return the same name when the service is dispatched. Providers derive metadata from the owning command constants, not independently maintained names.

`arguments` and `options` contain static descriptor data. Structural validation of positional arguments, declared options, option value forms, and repeatability occurs before command service resolution. Domain validation belongs to the command owner. Unknown metadata fields, including `priority`, are invalid; tag metadata must contain no closures, objects, resources, runtime payloads, secret values, or runtime-derived paths.

Validated `cli.commands.overrides` may change only the catalog-view `summary`, `hidden`, and `group`. Overrides cannot change command identity, service id, arguments, options, or the original tag metadata.

### Decision 3: Reserve `doctor`, `help`, and `list` for built-in command identities

The following command names are reserved:

```text
doctor
help
list
```

Only the exact built-in `platform/cli` command service ids may use these names. A command contributed by any other package that uses one of these names fails catalog construction deterministically. Distinct tagged services cannot claim the same command name.

The exact `coretsia doctor` invocation uses `UltraEarlyDoctorRunner` and the same dependency-free `DoctorCommand` registered in the normal catalog. This path does not boot Kernel, discover modules, build a catalog, or require generated artifacts. Other invocations, including `list` and `help`, use the normal source-host/catalog path.

### Decision 4: Keep Kernel operations behind the contracts port

Built-in Kernel operations consume only:

```text
Coretsia\Contracts\Kernel\Ops\KernelOpsInterface
Coretsia\Contracts\Kernel\Ops\KernelOpsRequest
Coretsia\Contracts\Kernel\Ops\OpsResult
```

The built-in operations are:

```text
debug:modules
config:validate
config:debug
config:compile
config:hash
cache:verify
```

Each operation requires `--target=web|api|console|worker`. The CLI does not infer the target and does not accept `--mode` or `--preset` for these built-in operations. `KernelOpsRequest` carries the explicit `appTarget` only. Kernel owns effective preset selection, configuration/module orchestration, compiled artifacts, generation lookup, fingerprinting, and cache verification. CLI renders only the safe `OpsResult`, including its effective preset and generation-aware results where applicable; it never reads Kernel artifacts to derive them.

The normal CLI entrypoint boots the canonical Console source operations host through `CliHostBootstrap`, `KernelOpsHostInput`, and `KernelOpsHostBooter`. The resolved Console `ModulePlan` must have `platform.cli` enabled; a missing or excluded CLI module fails closed. Source-host bootstrap uses Kernel's canonical Phase A and Phase B configuration mechanisms, not an additional CLI-owned configuration pipeline.

External commands are not routed through `KernelOpsInterface` by default. Their packages own execution semantics and may invoke a Kernel public port explicitly when their domain requires a Kernel-owned operation. `platform/cli` has no production compile-time dependency on an external command-owner package.

### Decision 5: Resolve selected commands lazily within one Kernel UnitOfWork

Normal invocation flow is:

```text
parse argv and separate global format|color
    -> select CommandDescriptor
    -> validate declared argument and option structure
    -> KernelRuntimeInterface::runUnitOfWork(UnitOfWorkType::CLI, ...)
    -> resolve selected CommandInterface service inside the callback
    -> verify command class and name identity
    -> CommandInterface::run(InputInterface, OutputInterface): int
```

The command may return an owner-selected portable exit code from `0` through `255`. The CLI preserves valid external-command exit codes. Observability uses the canonical context, tracing, metrics, and logging ports with bounded, safe fields; observability failures do not change the command outcome.

### Decision 6: Buffer once, redact once, and render deterministically

`CommandOutputBuffer` is created per invocation and implements `Coretsia\Contracts\Cli\Output\OutputInterface`. Commands produce only `text`, `json`, and `error` records through this interface; they do not write directly to stdout or stderr. The buffer immediately normalizes records, enforces Foundation JSON-like validation and stable encoding, rejects unsafe control characters, and applies fixed record and byte limits.

One finalized `CommandOutputBatch` is passed to `OutputFormatter`. Redaction uses the injected `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` with the canonical placeholder policy before the selected JSON, table, or plain renderer. Redaction and rendering failures fail closed through a fixed, non-recursive, path-safe diagnostic; a partial command batch is not emitted after an execution exception.

The deterministic presentation contract is:

- `json`: stable `coretsia.cli-output@1` envelope, canonical Foundation JSON encoding, and no ANSI;
- `table`: deterministic row rendering and configured width, with ANSI only when effective color permits it;
- `plain`: deterministic record/stream ordering, with ANSI only when effective color permits it;
- adaptive format and automatic color: selected from validated `CliOutputPolicy` and invocation-local `TerminalCapabilities`;
- `ConsoleOutputWriter`: the sole final stdout/stderr sink and owner of one trailing LF for each non-empty stream payload.

CLI-global `--format` and `--color` options are separated from command-facing input. Effective output state is invocation-local; command services and formatters do not probe terminal streams.

### Decision 7: Make output safe by construction before shared redaction

Command owners must omit, safely derive, or explicitly classify known-sensitive values before producing CLI records. A classifier non-match does not authorize disclosure of raw values. Successful redaction does not expand the permitted output schema or admit arbitrary diagnostic metadata.

CLI output and diagnostics must not disclose raw environment or dotenv values, configuration values, tokens, authorization headers, cookies, session identifiers, SQL statements, request/response payloads, or exception stack traces. Filesystem paths may be emitted only as safe relative values under an explicit owner-approved output contract. Kernel Ops produces safe result data at its own boundary; the CLI does not redact raw Kernel config, env, Composer metadata, or artifact content as a substitute for that boundary.

The normal error boundary discards buffered command output after an exception and renders an allowlisted error record. Bootstrap, uncaught-exception, and render-failure boundaries emit fixed safe code/reason pairs; no throwable messages, source paths, or traces are included by default.

### Decision 8: Keep installation, module selection, and CLI ownership separate

The Composer-installable baseline is owned by `coretsia/framework` and includes Kernel and CLI, with `platform/redaction` installed through CLI's dependency closure. Runtime module enablement remains a separate Kernel policy decision. The default `micro` Console selection enables `platform.cli` through the canonical preset and ModulePlan; merely installing a package does not enable its module.

The `coretsia` monorepo wrapper delegates to the packaged CLI through a fixed repository-relative path, independent of the current working directory. The Composer-installed Skeleton wrapper delegates through its project-local Composer binary proxy. Neither wrapper performs command discovery or introduces a second CLI runtime.

## Consequences

### Positive consequences

- commands from enabled packages participate in one deterministic, package-agnostic catalog;
- `list` and `help` do not instantiate every command service;
- built-in names and command identities are protected against collisions;
- all built-in configuration, module, and cache operations retain Kernel ownership;
- external commands retain their own domain validation and portable exit codes;
- invocation-local output and shared redaction yield stable, bounded output contracts;
- the executable, module-selection, and Composer-installation boundaries remain independent.

### Trade-offs

- command-owning providers must maintain exact class/tag metadata parity;
- invalid tags and duplicate command identities fail catalog construction rather than being silently accepted;
- producers must guarantee safe output independently of the redactor's classifier coverage;
- normal commands require the canonical Console source operations host and Kernel UnitOfWork.

## Rejected alternatives

### Scan command-owner packages or instantiate commands during discovery

Such discovery would couple catalog creation to installed package layout and constructor side effects, preventing lazy `list` and `help` behavior.

### Allow external commands to override built-in identities

Unrestricted use of `doctor`, `help`, or `list` would make command routing ambiguous and compromise the dependency-free doctor boundary.

### Read Kernel artifacts directly from CLI commands

Direct artifact or generation inspection would duplicate Kernel authority and undermine source-host and generation invariants.

### Rely on redaction as the sole output safety boundary

Classifier coverage cannot prove that arbitrary raw values are safe for disclosure. Producer-owned schemas and safe diagnostics remain mandatory.

### Render or write output incrementally during command execution

Direct writes would expose partial records on failure and prevent one deterministic redaction/formatting pass.

## Non-goals

This decision does not define command-owner domain behavior, introduce additional executable names, create a second module planner, make package installation imply module enablement, provide raw diagnostic modes, or transfer Kernel artifact ownership to the CLI.

## Related SSoT

- `docs/ssot/tags.md`
- `docs/ssot/di-tags-and-middleware-ordering.md`
- `docs/ssot/modes.md`
- `docs/ssot/runtime-container-definitions.md`
- `docs/ssot/observability.md`
- `docs/ssot/sensitive-data-redaction.md`
- `docs/ssot/json-like-runtime-values.md`
- `docs/ssot/cache-verify.md`
- `docs/ssot/artifact-generations.md`
- `docs/ssot/compiled-container.md`

## Related ADRs

- `docs/adr/ADR-0010-sensitive-data-redaction-boundary.md`
- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/adr/ADR-0020-kernel-runtime-uow-spi.md`
- `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`
- `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
- `docs/adr/ADR-0028-kernel-artifacts-fingerprint-cache-verify.md`
- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md`
- `docs/adr/ADR-0033-application-dependency-sync-installation-intent.md`
