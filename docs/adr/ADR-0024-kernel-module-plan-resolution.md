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

# ADR-0024: Kernel module plan resolution

## Status

Accepted.

## Context

Coretsia needs a deterministic Kernel-owned module plan that can be built from installed package metadata and a selected mode preset.

The module plan must answer a narrow runtime question:

> Given a resolved Bootstrap Phase A input, an installed module manifest, and one selected preset, which modules are enabled, disabled, missing as optional, and in which deterministic dependency order should they appear?

This decision depends on several already accepted boundaries:

- Bootstrap Phase A resolves the selected application target and selected preset.
- Module identity is represented by canonical `moduleId` values.
- Runtime module discovery is metadata-only.
- Composer package-level `require` / `conflict` are installation/build constraints, not module graph edges.
- Module graph edges are represented explicitly through Coretsia module metadata.
- Mode preset files describe user-facing mode intent (`micro`, `express`, `hybrid`, `enterprise`, or skeleton-defined overrides).
- Runtime code must not discover modules by scanning source trees, package directories, skeleton directories, or application directories.

The Kernel therefore needs one orchestration point that combines:

- selected preset from `BootstrapConfig`;
- preset loading from skeleton override or framework default files;
- Composer installed metadata discovery;
- module graph resolution;
- topological sorting;
- stable exported `ModulePlan` shape;
- safe diagnostics and observability.

## Decision

Kernel module plan resolution is owned by:

```text
Coretsia\Kernel\Module\ModulePlanResolver
```

`ModulePlanResolver` is the single orchestration entrypoint for creating a `ModulePlan`.

It coordinates:

1. discovery source validation;
2. mode preset loading;
3. preset schema validation through the loader path;
4. Composer installed metadata discovery through `ManifestReaderInterface`;
5. graph resolution through `ModuleGraphResolver`;
6. topological ordering through the graph resolver / sorter path;
7. stable `ModulePlan` construction;
8. safe logs and metrics.

## Resolution inputs

The canonical module-selection inputs are:

```text
BootstrapConfig::preset()
mode preset file
Composer installed metadata
extra.coretsia.requires
extra.coretsia.conflicts
```

`BootstrapConfig::appTarget()` is included in the resulting `ModulePlan` as output metadata only.

`BootstrapConfig::appTarget()` and `BootstrapConfig::appRoot()` MUST NOT introduce a separate module-selection source.

Application target may be used by later boot/config phases for application-local config/bootstrap boundaries, but it MUST NOT change module selection in `ModulePlanResolver`.

## Discovery source

The only supported runtime discovery source is Composer installed metadata:

```text
kernel.modules.discovery.source = composer
```

The selected discovery source MUST be validated against:

```text
kernel.modules.discovery.allowed_sources
```

Unsupported discovery source MUST fail before:

- preset loading;
- preset file reads;
- Composer metadata reads;
- graph resolution.

Unsupported discovery source failure is represented by:

```text
CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED
```

Config rules validate the shape of `kernel.modules.discovery.source` and `kernel.modules.discovery.allowed_sources`.

Supported-source membership is a runtime module-plan policy and is validated by `ModulePlanResolver`.

## Composer metadata discovery

Composer metadata discovery is implemented through:

```text
Coretsia\Kernel\Module\ComposerInstalledMetadataProvider
Coretsia\Kernel\Module\ComposerManifestReader
Coretsia\Contracts\Module\ManifestReaderInterface
```

Runtime discovery MUST use Composer installed metadata only.

Runtime discovery MUST NOT:

- scan `framework/packages/**`;
- scan package directories;
- scan source trees;
- scan `vendor/**` for package classes;
- scan skeleton directories;
- instantiate module classes;
- require package filesystem paths to derive module identity.

A package is included as a Coretsia runtime module only when:

```text
extra.coretsia.moduleId is present and valid
extra.coretsia.kind is "runtime"
```

Module identity MUST be parsed through:

```text
Coretsia\Contracts\Module\ModuleId
```

The module graph MUST NOT be inferred from Composer package-level `require` or `conflict`.

Runtime module graph edges are read only from:

```text
extra.coretsia.requires
extra.coretsia.conflicts
```

The normalized values are stored in:

```text
ModuleDescriptor::metadata()['requires']
ModuleDescriptor::metadata()['conflicts']
```

Missing `requires` and `conflicts` metadata are treated as empty deterministic lists.

## Mode preset loading

Mode preset loading is implemented through:

```text
Coretsia\Kernel\Module\ModePresetLoaderFactory
Coretsia\Kernel\Module\FilesystemModePresetLoader
Coretsia\Kernel\Module\ModePresetSchemaValidator
```

The active preset name comes only from:

```text
BootstrapConfig::preset()
```

Mode preset lookup order is:

1. skeleton override preset;
2. framework default preset.

The resolved locations are:

```text
BootstrapConfig::skeletonRoot() + kernel.modes.overrides_path + <preset>.php
core/kernel package root + kernel.modes.defaults_path + <preset>.php
```

The first existing preset file wins.

Skeleton and framework presets MUST NOT be merged.

Missing skeleton override is not an error.

Missing framework default after missing skeleton override is a deterministic hard failure:

```text
CORETSIA_MODE_PRESET_NOT_FOUND
```

A present but unreadable or invalid preset file is a deterministic hard failure:

```text
CORETSIA_MODE_PRESET_INVALID
```

Kernel-owned loaded preset construction MUST NOT be weaker than Kernel-owned preset schema validation.

`Coretsia\Kernel\Module\ModePreset` is an internal loaded-preset value object, but direct construction MUST still enforce the same stored-value safety policy as the validated loader path.

Direct construction MUST reject values that would be rejected by `ModePresetSchemaValidator`, including:

```text
preset names longer than 64 bytes
unsafe preset name characters
unsafe preset name start characters
path-like descriptions
featureBundles / metadata depth overflow
featureBundles / metadata map key overflow
featureBundles / metadata string length overflow
path-like featureBundles / metadata keys
path-like featureBundles / metadata string values
floats, objects, closures, resources
overlapping required / optional / disabled module sets
```

This prevents tests, internal helpers, and future construction paths from creating a `ModePreset` state that could not have been loaded through the canonical schema-validating loader path.

Resolved filesystem paths MUST NOT be exported in diagnostics, logs, warnings, or `ModulePlan`.

## Forbidden parallel module-selection paths

The following files MUST NOT be read by `ModulePlanResolver`:

```text
skeleton/config/modules.php
skeleton/apps/<app>/config/modules.php
```

The resolver MUST NOT scan:

```text
skeleton/apps/*
```

The resolver MUST NOT infer the selected app target from filesystem layout.

The selected app target is Phase A input, not a module-selection discovery mechanism.

## Graph resolution

Graph policy is delegated to:

```text
Coretsia\Kernel\Module\ModuleGraphResolver
```

Graph resolution uses:

- installed `ModuleManifest`;
- validated `ModePresetInterface`;
- module dependency metadata from `ModuleDescriptor::metadata()['requires']`;
- module conflict metadata from `ModuleDescriptor::metadata()['conflicts']`.

The initial enabled seed set is:

- all preset `required` modules;
- preset `optional` modules only when installed;
- never preset `disabled` modules.

Required dependency closure is then applied:

- if enabled module `A` requires installed module `B`, `B` is enabled transitively;
- if enabled module `A` requires missing module `B`, resolution fails;
- if enabled module `A` requires disabled module `B`, resolution fails.

Conflict, required-missing, optional-missing, and cycle policy is specified by:

```text
docs/adr/ADR-0025-kernel-conflicts-optional-missing-policy.md
```

This ADR defines the resolution pipeline and ownership boundaries. ADR-0025 defines the detailed graph failure policy.

## Topological order

Topological sorting is deterministic.

For an edge:

```text
A requires B
```

`B` MUST appear before `A` in `ModulePlan::topologicalOrder()`.

Only enabled modules participate in topological sorting.

When multiple nodes are available, the lowest module id by byte-order `strcmp` wins.

Topological order MUST NOT depend on:

- filesystem traversal order;
- Composer package declaration order;
- PHP hash-map incidental insertion order;
- locale;
- OS-specific path behavior.

Cycle detection is a deterministic hard failure:

```text
CORETSIA_MODULE_CYCLE_DETECTED
```

Cycle diagnostics MUST expose only stable module id tokens and stable reason tokens.

## ModulePlan shape

The resolved plan is represented by:

```text
Coretsia\Kernel\Module\ModulePlan
```

`ModulePlan` is a stable payload shape for future artifacts, debug output, diagnostics, and adapters.

The canonical exported top-level key order is:

```text
app
disabled
enabled
modules
optionalMissing
preset
schemaVersion
topologicalOrder
warnings
```

`modules` is a map keyed by module id.

`modules` map keys MUST be sorted by byte-order `strcmp`.

Each module entry exported shape uses this key order:

```text
composerName
conflicts
moduleId
requires
```

`enabled`, `disabled`, and `optionalMissing` are module id lists sorted by byte-order `strcmp`.

`enabled`, `disabled`, and `optionalMissing` MUST be pairwise disjoint.

The following intersections MUST be empty:

```text
enabled ∩ disabled
enabled ∩ optionalMissing
disabled ∩ optionalMissing
```

A module id MUST NOT be exported as enabled, disabled, and/or optional-missing at the same time.

`ModulePlan` construction MUST reject contradictory module set state before the plan is used as artifact-ready output.

`topologicalOrder` preserves dependency order and must be deterministic.

`warnings` is a list of warning exported arrays.

Warning ordering is deterministic and defined by the warning canonical sort key.

`ModulePlan` MUST NOT store or export:

- `skeletonRoot`;
- `appRoot`;
- `defaultsPath`;
- `overridesPath`;
- absolute paths;
- provider class lists;
- raw Composer payloads;
- raw preset payloads;
- raw config payloads;
- services;
- containers;
- closures;
- resources;
- filesystem handles.

## Failure precedence

`ModulePlanResolver` classifies deterministic failures in this order:

1. unsupported discovery source;
2. preset not found;
3. preset invalid;
4. Composer/module manifest invalid;
5. module conflicts;
6. required missing;
7. cycle detected;
8. success with optional missing warnings.

If multiple failures of the same class exist, the reported failure MUST be selected by the smallest canonical failure key using byte-order `strcmp`.

The canonical failure key definitions for graph-policy failures are specified by ADR-0025.

## Observability

`ModulePlanResolver` emits metrics through:

```text
Coretsia\Contracts\Observability\Metrics\MeterPortInterface
```

It records:

```text
kernel.modules_resolve_total
kernel.modules_resolve_duration_ms
```

Each metric uses only the labels:

```text
operation
outcome
```

The `operation` label value is the stable token:

```text
resolve
```

Allowed outcome values are stable tokens:

```text
success
preset_not_found
preset_invalid
manifest_invalid
discovery_source_unsupported
conflict
required_missing
cycle
unexpected_failure
```

`success` MUST be emitted only after full successful `ModulePlan` resolution.

Known `ModuleResolutionException` failures MUST emit the mapped deterministic outcome token.

Unexpected non-`ModuleResolutionException` throwables MUST emit:

```text
unexpected_failure
```

and MUST be rethrown unchanged.

Unexpected throwables MUST NOT be logged through the deterministic module-resolution failure logger because that logger owns only safe `ModuleResolutionException` diagnostics.

Metric labels for module-plan resolution are summary-only and fixed to:

```text
operation = resolve
outcome = <stable outcome token>
```

Metric labels MUST NOT contain:

- module ids;
- preset names;
- paths;
- raw errors;
- exception messages;
- stack traces;
- secrets;
- PII.

Metric backend failures MUST NOT affect module plan resolution and MUST NOT replace deterministic module resolution exceptions.

`Stopwatch` start/stop failures used for module-plan duration metrics MUST NOT affect `ModulePlan` resolution and MUST NOT replace deterministic module resolution exceptions.

When module-plan duration cannot be measured, the duration metric value MUST collapse to `0` or the timing signal MUST be omitted according to owner policy.

`ModulePlanResolver` MAY emit safe logs through:

```text
Psr\Log\LoggerInterface
```

Logging is optional and MUST NOT affect resolution.

Log context MAY contain only:

- stable error or warning code;
- stable reason token;
- safe preset token;
- stable module id tokens.

Log context MUST NOT contain:

- filesystem paths;
- raw Composer metadata;
- raw preset payloads;
- exception messages;
- stack traces;
- secrets;
- PII.

## Provider and factory wiring

Kernel provider wiring registers module-plan services as factories only.

Provider registration MUST NOT:

- resolve `ModulePlan`;
- read Composer installed metadata;
- read preset files;
- scan filesystem paths;
- create `FilesystemModePresetLoader`;
- bind `ModePresetLoaderInterface` globally;
- bind `FilesystemModePresetLoader` globally.

`FilesystemModePresetLoader` is BootstrapConfig-specific and MUST be created only through:

```text
ModePresetLoaderFactory::createFor(BootstrapConfig)
```

`ModulePlanResolver` MAY be a shared service.

Per-resolution freshness applies to:

- `FilesystemModePresetLoader`;
- loaded preset objects;
- resolved `ModulePlan`.

Shared resolver/factory services MUST NOT retain:

- `BootstrapConfig`;
- loaded presets;
- resolved plans;
- Composer metadata snapshots.

## Consequences

Positive consequences:

- Module plan resolution has one Kernel-owned orchestration point.
- Runtime discovery is metadata-only and does not depend on monorepo layout.
- Split packages can keep deterministic module planning behavior.
- Mode preset overrides are explicit and non-merged.
- Module selection cannot be silently changed by application-local `modules.php` files.
- `ModulePlan` is safe to use for future debug output and artifact payloads.
- Failure behavior is deterministic and stable for CLI, CI, and tests.
- Observability has stable low-cardinality labels.

Trade-offs:

- Composer package-level `require` / `conflict` are not sufficient to define runtime module graph edges.
- Runtime modules must explicitly declare Coretsia module graph metadata in `extra.coretsia.requires` and `extra.coretsia.conflicts`.
- Preset overrides replace framework defaults instead of merging with them.
- Application targets cannot customize module selection directly; they must select a preset through Bootstrap Phase A.
- `FilesystemModePresetLoader` cannot be registered as a global service because it depends on a resolved `BootstrapConfig`.

## Non-goals

This ADR does not define:

- module boot lifecycle;
- service provider execution order;
- config Phase B merge;
- artifact writing;
- CLI command UX;
- package installation policy;
- Composer dependency solving;
- platform or integration package behavior;
- HTTP routing or middleware selection;
- app-local module-selection files;
- automatic discovery from `skeleton/apps/*`;
- merge semantics between framework and skeleton presets.

Detailed conflict, required-missing, optional-missing, and cycle failure policy is defined separately by ADR-0025.

## Related SSoT

- `docs/ssot/modules-and-manifests.md`
- `docs/ssot/modes.md`
- `docs/ssot/config-roots.md`

## Related ADRs

- `docs/adr/ADR-0001-module-descriptor-manifest-modepreset-ports.md`
- `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`
- `docs/adr/ADR-0025-kernel-conflicts-optional-missing-policy.md`

## Related epic

- `1.310.0 Kernel: Module Plan (discovery + presets + graph + policies)`
