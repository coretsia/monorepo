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

# ADR-0025: Kernel module conflicts and optional-missing policy

## Status

Accepted.

## Context

Kernel module plan resolution needs deterministic graph policy for four related cases:

- preset-required modules;
- preset-optional modules;
- preset-disabled modules;
- module dependency/conflict metadata.

The Kernel must be able to distinguish fatal graph policy failures from non-fatal optional capability gaps.

The policy must be deterministic across operating systems, process locales, Composer metadata ordering, filesystem ordering, and repeated runs.

The policy must also keep diagnostics safe. Graph failures and warnings may expose stable module ids, preset names, error codes, and reason tokens, but they must not expose paths, raw preset payloads, raw Composer payloads, stack traces, secrets, PII, or filesystem layout.

ADR-0024 defines the overall ModulePlan resolution pipeline. This ADR defines the detailed graph-policy behavior used by that pipeline.

## Decision

Kernel graph policy is owned by:

```text
Coretsia\Kernel\Module\ModuleGraphResolver
```

The graph resolver consumes:

- installed `ModuleManifest`;
- validated `ModePresetInterface`;
- dependency metadata from `ModuleDescriptor::metadata()['requires']`;
- conflict metadata from `ModuleDescriptor::metadata()['conflicts']`.

It returns a deterministic `ModulePlan` or throws a deterministic module resolution exception.

Graph failures are collected before throwing so the selected failure is not an incidental result of traversal order.

## Input metadata

Runtime module graph edges are read only from:

```text
ModuleDescriptor::metadata()['requires']
ModuleDescriptor::metadata()['conflicts']
```

These values are normalized from Composer metadata:

```text
extra.coretsia.requires
extra.coretsia.conflicts
```

Composer package-level `require` and `conflict` MUST NOT be used as runtime module graph edges.

Missing `metadata.requires` is treated as an empty list.

Missing `metadata.conflicts` is treated as an empty list.

Non-list or invalid metadata values MUST hard fail as:

```text
CORETSIA_MODULE_MANIFEST_INVALID
```

Invalid graph metadata is a manifest error, not a graph policy candidate.

## Initial enabled seed set

The initial enabled seed set is built from the selected preset.

The resolver MUST enable:

- every module listed in preset `required`;
- every module listed in preset `optional` only when that module is installed.

The resolver MUST NOT enable:

- modules listed in preset `disabled`;
- optional modules that are not installed.

Preset list order is not semantic.

The `required`, `optional`, and `disabled` sets MUST be normalized and processed deterministically by module id using byte-order `strcmp`.

## Required modules

A preset-required module is mandatory.

If a preset-required module is missing from the installed manifest, resolution MUST hard fail as:

```text
CORETSIA_MODULE_REQUIRED_MISSING
```

The stable reason token is:

```text
preset-required-module-missing
```

The safe diagnostic context contains:

```text
preset
missingModuleId
```

The canonical required-missing failure key for preset-required missing modules is:

```text
presetName + "\0" + missingModuleId + "\0" + reason
```

The `presetName` value MUST be a safe preset token.

The `missingModuleId` value MUST be a valid module id token.

Diagnostics MUST NOT include raw preset payloads, filesystem paths, package paths, Composer raw payloads, stack traces, secrets, or PII.

## Optional modules

A preset-optional module is non-fatal.

If a preset-optional module is installed and not disabled, it is enabled.

If a preset-optional module is not installed, resolution MUST NOT fail.

Instead, the resolver MUST:

- add the module id to `ModulePlan::optionalMissing()`;
- create a `ModuleOptionalMissingWarning`;
- continue graph resolution.

The warning code is:

```text
CORETSIA_MODULE_OPTIONAL_MISSING
```

The stable reason token is:

```text
preset-optional-module-missing
```

The warning exported shape is:

```text
code
moduleId
preset
reason
```

The warning canonical sort key is:

```text
code + "\0" + preset + "\0" + moduleId + "\0" + reason
```

`optionalMissing` MUST be sorted by module id using byte-order `strcmp`.

Warnings MUST be sorted by their canonical warning key using byte-order `strcmp`.

Warnings MUST NOT contain paths, raw preset payloads, raw Composer payloads, stack traces, secrets, PII, or filesystem layout.

## Disabled modules

A preset-disabled module MUST NOT appear in the enabled module set.

Disabled modules MAY appear in the exported `ModulePlan::disabled()` list.

The disabled list MUST be sorted by module id using byte-order `strcmp`.

A disabled module MUST NOT appear in `ModulePlan::optionalMissing()`.

If a module is explicitly disabled by preset policy, it is disabled, not optional-missing.

If a preset-required module is also disabled, resolution MUST hard fail as a conflict:

```text
CORETSIA_MODULE_CONFLICT
```

The stable reason token is:

```text
required-module-disabled
```

The safe diagnostic context contains:

```text
moduleId
disabledModuleId
```

When the same module is both required and disabled by preset policy, both context values MAY contain the same module id.

Diagnostics MUST NOT expose preset payloads, paths, filesystem layout, raw Composer payloads, stack traces, secrets, or PII.

## Required dependency closure

After the initial seed set is built, the resolver MUST expand required dependency closure.

For each enabled module `A`:

```text
A requires B
```

the resolver applies this policy:

1. If `B` is installed and not disabled, `B` MUST be enabled transitively.
2. If `B` is missing, resolution MUST hard fail as `CORETSIA_MODULE_REQUIRED_MISSING`.
3. If `B` is disabled, resolution MUST hard fail as `CORETSIA_MODULE_CONFLICT`.

The stable reason token for missing transitive dependency is:

```text
dependency-required-module-missing
```

The safe diagnostic context contains:

```text
requiredByModuleId
missingModuleId
```

The canonical required-missing failure key for dependency-required missing modules is:

```text
requiredByModuleId + "\0" + missingModuleId + "\0" + reason
```

The stable reason token for disabled transitive dependency is:

```text
required-module-disabled
```

The safe diagnostic context contains:

```text
moduleId
disabledModuleId
```

The dependency closure traversal MUST be deterministic. When multiple module ids are queued, the next id MUST be selected by byte-order `strcmp`.

## Module conflicts

An enabled module may declare conflicts through:

```text
ModuleDescriptor::metadata()['conflicts']
```

If enabled module `A` conflicts with enabled module `B`, resolution MUST hard fail as:

```text
CORETSIA_MODULE_CONFLICT
```

The stable reason token is:

```text
module-conflict
```

Conflicts against modules that are not enabled MUST NOT fail.

The conflict pair in diagnostics MUST be sorted as:

```text
[lowerModuleId, higherModuleId]
```

using byte-order `strcmp`.

The safe diagnostic context contains:

```text
lowerModuleId
higherModuleId
```

The canonical conflict failure key is:

```text
lowerModuleId + "\0" + higherModuleId + "\0" + reason
```

If multiple conflict candidates exist, the reported candidate MUST be the one with the smallest canonical conflict failure key by byte-order `strcmp`.

Conflict diagnostics MUST NOT expose graph dumps, raw Composer metadata, raw preset payloads, paths, filesystem layout, stack traces, secrets, or PII.

## Candidate collection and graph failure precedence

Graph policy candidates MUST be collected before throwing.

The graph-level failure precedence is:

1. module conflicts;
2. required missing;
3. cycle detected.

Manifest-invalid failures are not graph policy candidates. They represent invalid installed metadata or invalid descriptor metadata and MUST fail immediately as:

```text
CORETSIA_MODULE_MANIFEST_INVALID
```

Conflict candidates MUST be keyed by:

```text
lowerModuleId + "\0" + higherModuleId + "\0" + reason
```

Required-missing candidates MUST be keyed by:

```text
requiredBy + "\0" + missingModuleId + "\0" + reason
```

For preset-required missing modules, `requiredBy` is the preset name.

For dependency-required missing modules, `requiredBy` is the requiring module id.

If multiple candidates of the same class exist, the selected candidate MUST be the one with the smallest canonical key by byte-order `strcmp`.

## Cycle policy

Cycle detection happens only after:

- missing required modules are classified;
- disabled required dependencies are classified;
- enabled-module conflicts are classified.

A cycle is a fatal deterministic graph failure:

```text
CORETSIA_MODULE_CYCLE_DETECTED
```

Cycle diagnostics MUST contain only stable module id tokens and a stable reason token.

Cycle diagnostics MUST NOT contain:

- graph dumps;
- raw metadata payloads;
- Composer raw payloads;
- raw preset payloads;
- paths;
- filesystem layout;
- stack traces;
- secrets;
- PII.

Cycle module ids in diagnostics MUST be deterministic.

Unless a test explicitly requires a canonical minimal cycle path, cycle module ids SHOULD be sorted by byte-order `strcmp`.

## Output policy

When graph resolution succeeds, the resulting `ModulePlan` MUST contain:

- enabled module ids sorted by byte-order `strcmp`;
- disabled module ids sorted by byte-order `strcmp`;
- optional missing module ids sorted by byte-order `strcmp`;
- topological order preserving dependency order;
- module entries keyed by module id and sorted by byte-order `strcmp`;
- warning list sorted by canonical warning key.

The resulting `ModulePlan` module id sets MUST be pairwise disjoint:

```text
enabled ∩ disabled = ∅
enabled ∩ optionalMissing = ∅
disabled ∩ optionalMissing = ∅
```

Graph resolution MUST NOT produce a `ModulePlan` where the same module id is exported in more than one of `enabled`, `disabled`, or `optionalMissing`.

`topologicalOrder` MUST contain all enabled modules exactly once.

`topologicalOrder` MUST NOT contain disabled modules.

`topologicalOrder` MUST NOT contain optional missing modules.

`ModulePlan` MUST NOT contain or export:

- skeleton root;
- app root;
- defaults path;
- overrides path;
- absolute paths;
- provider class lists;
- raw Composer payloads;
- raw preset payloads;
- raw config payloads;
- service instances;
- containers;
- closures;
- resources;
- filesystem handles.

## Global failure precedence

Within the complete `ModulePlanResolver` pipeline, deterministic failures are classified in this order:

1. unsupported discovery source;
2. preset not found;
3. preset invalid;
4. Composer/module manifest invalid;
5. module conflicts;
6. required missing;
7. cycle detected;
8. success with optional missing warnings.

This ADR owns graph-policy classes:

```text
CORETSIA_MODULE_CONFLICT
CORETSIA_MODULE_REQUIRED_MISSING
CORETSIA_MODULE_OPTIONAL_MISSING
CORETSIA_MODULE_CYCLE_DETECTED
```

ADR-0024 owns the full resolution pipeline and non-graph failure classes.

## Safe diagnostics

Graph exceptions and warnings MAY expose only:

- stable error or warning code;
- stable reason token;
- safe preset token;
- stable module id tokens.

Graph exceptions and warnings MUST NOT expose:

- filesystem paths;
- skeleton root;
- app root;
- defaults path;
- overrides path;
- raw Composer metadata;
- raw preset payload;
- raw config payload;
- graph dumps;
- service instances;
- exception messages from previous throwables;
- stack traces;
- secrets;
- PII;
- environment-specific values.

Exception message format is inherited from the Kernel module resolution exception policy:

```text
ERROR_CODE: reason-token
```

The message MUST NOT include context values.

## Observability implications

Optional missing modules are non-fatal and MAY be logged as warnings.

Conflict and required-missing failures MAY be logged only as safe deterministic summaries.

Log context MAY contain only:

- stable error or warning code;
- stable reason token;
- safe preset token;
- stable module id tokens.

Log context MUST NOT contain paths, raw Composer metadata, raw preset payloads, exception messages, stack traces, secrets, or PII.

Metrics labels MUST NOT contain module ids or preset names.

The graph policy itself MUST NOT depend on logging or metrics backends.

## Consequences

Positive consequences:

- Optional module absence is explicit, visible, and non-fatal.
- Required module absence is deterministic and fatal.
- Disabled modules have strict precedence over accidental enablement.
- Conflicts are detected before topological sorting.
- Failure reporting does not depend on traversal order.
- Failure diagnostics are stable and safe.
- The resulting `ModulePlan` can be used by future debug/artifact paths without exposing raw metadata or filesystem layout.

Trade-offs:

- Optional modules are enabled only when installed; there is no automatic package installation.
- A disabled module required by an enabled module is a hard conflict, not a silent omission.
- Composer package-level `require` and `conflict` do not affect runtime graph policy.
- Presets must be explicit about `required`, `optional`, and `disabled` intent.
- Multiple simultaneous graph failures report only the deterministic first candidate according to this ADR.

## Non-goals

This ADR does not define:

- Composer dependency solving;
- package installation UX;
- module boot lifecycle;
- service provider execution;
- config Phase B merge;
- generated artifact writing;
- CLI command output;
- HTTP middleware selection;
- application-local module-selection files;
- automatic module discovery from filesystem layout;
- automatic installation of optional modules.

## Related SSoT

- `docs/ssot/modules-and-manifests.md`
- `docs/ssot/modes.md`

## Related ADRs

- `docs/adr/ADR-0024-kernel-module-plan-resolution.md`

## Related epic

- `1.310.0 Kernel: Module Plan (discovery + presets + graph + policies)`
