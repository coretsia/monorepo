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

# ADR-0035: CLI Composite Workflows and Deterministic Suggestions

```yaml
adrVersion: 1
status: pre-accepted
owner: platform/cli
```

## Context

ADR-0034 establishes a tag-first `CommandCatalog`, lazy command service resolution, one Kernel CLI UnitOfWork (UoW) for each normal command, and one final output redaction and formatting boundary. Those rules remain authoritative for normal commands and built-in Kernel operations.

Coretsia also needs deterministic, configuration-defined sequences of existing CLI commands. Wrapping a complete workflow in an outer CLI UoW while each step enters its own UoW would violate Kernel's non-nesting lifecycle contract. Bypassing `CommandRunner` to avoid nesting would instead duplicate command identity checks, input validation, observability, and Kernel lifecycle ownership.

The CLI additionally needs bounded suggestions for unknown command names and readable nested results without creating new discovery, parsing, rendering, redaction, or persistence subsystems.

The canonical workflow execution flow is:

```text
coretsia workflow:run <workflow>
    -> final CommandCatalog and lazy WorkflowCatalog
    -> validated, ordered WorkflowDefinition
    -> for each normal step: shared input normalization and validation
    -> fresh child CommandOutputBuffer
    -> existing CommandRunner and one Kernel CLI UnitOfWork
    -> lazily resolved command owner and child CommandOutputBatch
    -> ordered WorkflowResult in one parent output record
    -> one shared redaction and formatting pass
    -> ConsoleOutputWriter
```

## Decision

### Decision 1: Reserve one platform-owned composite-command exception

`workflow:run` is the only source-host command allowed to use the internal `Coretsia\Platform\Cli\Runner\CompositeCommandInterface` in this decision. Its exact built-in service identity is `Coretsia\Platform\Cli\Command\WorkflowRunCommand`. It does not implement the public `Coretsia\Contracts\Cli\Command\CommandInterface` and is not an extension point for command-owner packages.

The composite exception is enforced by the platform-owned `CommandTagSchema` allowlist, not by an additional `cli.command` tag field. The exact six metadata keys remain `name`, `summary`, `group`, `hidden`, `arguments`, and `options`. A descriptor's derived `normal|composite` execution kind is internal and cannot be configured or overridden. External tagged commands continue to require `CommandInterface`.

`CommandRunner` retains lazy service resolution and the existing class, name, interface, exit-code, and lifecycle-observability checks for the selected composite command. No other composite dispatcher or cross-package composite contract is introduced.

### Decision 2: Execute each workflow step in one Kernel CLI UnitOfWork without an outer workflow UoW

The `workflow:run` composite invocation has no outer Kernel CLI UoW, including before service resolution or when composite service validation fails. Every successfully dispatched normal step goes through the existing `CommandRunner` and receives exactly one canonical Kernel CLI UoW, with Kernel-owned hooks, context, and reset lifecycle. No nested UoW is created.

`workflow:list` remains a normal command and receives one CLI UoW. All other normal tagged commands keep the ADR-0034 one-UoW rule. A workflow step cannot be composite.

`CommandRunner` owns one top-level `cli.command` lifecycle observation for `workflow:run`, without an outer UoW identity. Each executed step emits its own normal `cli.command` observation through `CommandRunner` inside its own UoW. `WorkflowRunner` and `WorkflowRunCommand` do not implement an independent observability or reset coordinator, and no workflow names, arguments, or option values become telemetry attributes or labels.

### Decision 3: Discover and validate workflows only from canonical configuration

Workflow definitions are declared only in the validated `cli.workflows.definitions` map. An empty map means no workflows. The CLI does not discover workflows from paths, directories, globs, the current working directory, command packages, or the filesystem.

`WorkflowSchema` performs bounded canonical JSON-like normalization and deep shape validation. `WorkflowCatalog` consumes those normalized definitions and the final `CommandCatalog` when `workflow:list` or `workflow:run` is selected. It remains a lazy dependency: invalid deep workflow definitions do not block unrelated CLI commands, while invalid static configuration map boundaries still fail through Kernel configuration validation.

Before any step executes, `WorkflowCatalog` validates every configured workflow and verifies that all referenced commands exist and have normal execution kind. It resolves no command services. `WorkflowRunner` obtains each final descriptor, constructs structured input through `WorkflowStepInputFactory`, and delegates descriptor-dependent input normalization and validation to the existing `CommandInputValidator` immediately before dispatch. Command-owner domain validation remains with each command.

Workflow definitions contain exact ordered step arguments and command-owned options. They do not contain callbacks, service ids, shell fragments, conditionals, loops, retries, templates, or environment substitutions. `workflow:run` accepts one required workflow name and has no command-owned options: it does not support `--mode`, `--preset`, or inherited `--target`. Kernel operation steps specify their own `target` in their definitions; Kernel retains bootstrap preset selection and operations ownership.

### Decision 4: Preserve ordered, sequential, fail-fast execution without transactions

`WorkflowRunner` executes steps in definition order and stops on the first non-zero valid exit code or propagated Throwable. A completed workflow result includes the first failing step with its completed output records, but no unexecuted steps. If all steps return `0`, the completed result succeeds; otherwise it returns the first non-zero step exit code.

Each step uses a fresh invocation-local `CommandOutputBuffer` created only after successful input validation. A throwing step's buffer is discarded without finalization or partial rendering. A Throwable propagates unchanged to the existing `CliApplication` error boundary; no completed `WorkflowResult` is produced on a thrown exception or step-input validation failure.

Workflow execution is not transactional across steps. Earlier completed steps may already have committed their Kernel-owned or command-owner effects when a later step fails validation, throws, returns non-zero, or encounters a final output error. There is no rollback, compensation, retry, continue-on-error policy, parallel execution, or recovery coordinator.

Nested workflow execution is forbidden: a workflow step cannot dispatch `workflow:run` or any other composite command. No nested workflows or composite steps are allowed.

### Decision 5: Aggregate child records and reuse the existing final output boundary

`WorkflowResult` contains only `workflow`, `outcome`, `exit_code`, and ordered `steps`. Each exported step contains only `index`, `command`, `exit_code`, and `records`; indices retain their zero-based definition positions. Canonical internal child records remain unchanged in `WorkflowStepResult`.

For exported child JSON records, the internal structural `payload` field is projected to `data`. Child `text` and `error` records retain their existing fields, and nested content remains intact until final redaction. This prevents the shared sensitive-key classifier from redacting an entire child JSON record merely because its internal envelope key is named `payload`.

`WorkflowRunCommand` writes exactly one top-level JSON-like result record after completed execution. Child batches are not independently rendered, redacted, or written. The existing parent `CommandOutputBuffer` enforces canonical Foundation JSON-like limits, including bounds on aggregated output; an oversized parent result fails closed with no partial child output. `OutputFormatter` remains the only shared final redaction and format-selection boundary, followed by the sole `ConsoleOutputWriter` sink.

Explicit `--format=json` retains the existing `coretsia.cli-output@1` envelope and canonical encoding. The existing generic `TableFormatter` renders nested maps and lists as readable sections without a workflow-specific renderer or changed JSON schema.

### Decision 6: Derive suggestions only from the final visible command catalog

Unknown command-name suggestions use only final, visible `CommandDescriptor` names after validated catalog presentation overrides. `SmartSuggestor` performs bounded ASCII-only Levenshtein comparisons, orders accepted candidates by distance and final catalog order for ties, and returns at most three names. Hidden commands and invalid or out-of-bounds input produce no ineligible candidates; no workflow, option, path, service-id, or filesystem suggestions are generated.

The unknown-command error keeps its existing safe `CliInputInvalidException` code and reason. A non-empty suggestion list adds one JSON-like output record through the existing formatter and shared redactor. Suggestion calculation is best-effort and does not enter logs, metrics, or UoW attributes.

### Decision 7: Keep human-readable output within existing CLI presentation paths

The adaptive non-interactive format defaults to `table`; explicit `--format=json|table|plain|adaptive` remains authoritative. Nested JSON-like values are presented through the existing generic table renderer rather than serialized objects embedded in cells. Terminal capability detection controls interactivity and ANSI safety, not a second output pipeline.

The exact ultra-early `coretsia doctor` invocation remains independent of Kernel host boot and uses its existing fixed boolean diagnostic results, `UltraEarlyDoctorRunner`, `PlainFormatter`, and `ConsoleOutputWriter`. It projects fixed human-readable `OK|FAIL` capability states and `READY|NOT READY` status without shared redaction, arbitrary payloads, or a new diagnostics renderer.

## Consequences

### Positive consequences

- deterministic workflows reuse the final tag-first catalog, shared validation, and existing command-owner execution path;
- one Kernel CLI UoW per executed step preserves Kernel hooks, reset, and context isolation without nesting;
- workflow failures stop dispatch deterministically and produce no synthetic or partially rendered step results;
- normal commands and `workflow:list` retain their established execution and UoW contracts;
- workflow output and unknown-command suggestions reuse the sole shared redaction, formatting, and writing pipeline;
- human-readable CLI output improves without changing the explicit machine-readable JSON envelope.

### Trade-offs

- workflow definitions are static and must specify all step arguments and options in advance;
- one workflow can leave earlier completed command side effects when a later step fails;
- an aggregated result can exceed the existing parent output budget even when individual child records were valid;
- invalid configured workflow definitions prevent workflow commands from executing but do not prevent unrelated normal commands when static configuration boundaries remain valid.

## Rejected alternatives

### Give the entire workflow one outer Kernel UnitOfWork

An outer workflow UoW would conflict with the canonical one-UoW-per-step lifecycle and Kernel's prohibition on nested UoWs.

### Dispatch workflow steps directly or allow external composite commands

Direct command invocation would bypass shared descriptor validation, lazy service resolution, command lifecycle observability, and Kernel-owned UoW semantics. A public composite interface would widen the single platform-owned exception into an unsupported extension contract.

### Allow nested workflows, conditional orchestration, or continue-on-error

These capabilities would introduce a generic orchestration engine, additional lifecycle rules, and execution semantics not defined by this decision.

### Discover workflow files or infer bootstrap presets and targets

Filesystem discovery, inherited `--target`, and `--mode|--preset` semantics would create a second source of workflow configuration and alter Kernel bootstrap ownership.

### Persist workflow runs or introduce an independent output pipeline

Replay recording, storage, playback, retention, and output transcript artifacts lack an accepted capability and persistence boundary. A workflow-specific formatter or redactor would duplicate the established output safety boundary.

## Non-goals

This decision does not provide workflow replay or persistence artifacts, filesystem-based discovery, nested or parallel workflows, retries, rollback, transactional step execution, interactive prompts or confirmations, external composite-command extension points, independent Kernel orchestration, or a new output/JSON/redaction subsystem.

## Related SSoT

- `docs/ssot/tags.md`
- `docs/ssot/observability.md`
- `docs/ssot/sensitive-data-redaction.md`
- `docs/ssot/context-keys.md`
- `docs/ssot/context-store.md`
- `docs/ssot/stateful-services.md`
- `docs/ssot/json-like-runtime-values.md`

## Related ADRs

- `docs/adr/ADR-0010-sensitive-data-redaction-boundary.md`
- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/adr/ADR-0020-kernel-runtime-uow-spi.md`
- `docs/adr/ADR-0034-cli-tag-first-command-catalog.md`
