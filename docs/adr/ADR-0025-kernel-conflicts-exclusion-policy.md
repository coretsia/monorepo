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

# ADR-0025: Kernel module conflicts and exclusion policy

```yaml
adrVersion: 1
status: pre-accepted
owner: core/kernel
```

## Context

Phase A supplies immutable `ModuleSelection` roots and explicit exclusions. Phase B receives that selection plus one validated installed `ModuleManifest` snapshot. Graph policy must distinguish selected-root absence, required dependency absence, prohibited dependency activation, conflicts between enabled modules, and dependency cycles without consulting `ModePreset` or Composer package dependency edges.

Graph-policy results MUST remain deterministic across operating systems, process locales, installed-manifest descriptor ordering, filesystem ordering, and repeated runs. Module-id sets and failure candidates use byte-order `strcmp` ordering; incidental traversal order MUST NOT determine the selected failure.

ADR-0024 defines the complete module-resolution pipeline. This ADR defines the graph-policy decisions applied within Phase B and the corresponding deterministic failure contracts.

## Decision

`ModuleGraphResolver` consumes only `ModuleManifest`, `ModuleSelection`, and the selected app-target string. It returns a deterministic `ModulePlan` or throws a deterministic `ModuleResolutionException`. The manifest is the contracts-level installed discovery snapshot, distinct from the generated `module-manifest@1` artifact.

## Installed manifest and descriptor validation

Validate every installed module descriptor before graph traversal, including descriptors not selected as roots. Validate module ids, metadata shapes, required and conflicting runtime module-id lists, and installed-manifest invariants. Invalid installed metadata fails as `CORETSIA_MODULE_MANIFEST_INVALID` before choosing any graph-policy exception.

Runtime graph edges are read exclusively from `ModuleDescriptor::metadata()['requires']` and `ModuleDescriptor::metadata()['conflicts']`. These values originate from `extra.coretsia.requires` and `extra.coretsia.conflicts`; Composer package-level `require` and `conflict` MUST NOT be interpreted as runtime module graph edges.

A missing `requires` or `conflicts` metadata key represents an empty list. A present value MUST be a list containing valid runtime module-id strings. Invalid metadata types or module ids MUST fail as `CORETSIA_MODULE_MANIFEST_INVALID`. Valid dependency and conflict collections are normalized into deterministic, unique, strcmp-sorted module-id lists before graph traversal.

A descriptor MUST NOT require or conflict with itself. Its required and conflicting module-id sets MUST NOT overlap. Violations are installed-manifest validation failures, not graph-policy conflict candidates.

Installed-manifest validation MUST complete before graph-policy failure selection. An invalid unselected descriptor therefore takes precedence over a missing selected root, an excluded dependency, an enabled-module conflict, or a dependency cycle.

## Selected roots and exclusion

`ModuleSelection::roots()` contains the effective unique sorted root set constructed by Phase A; `ModuleSelection::excluded()` contains the explicit unique sorted exclusion set. The sets are disjoint. The resolver does not load a preset or reprocess overrides.

Each selected root must exist in the installed manifest. A missing selected root fails as `CORETSIA_MODULE_REQUIRED_MISSING` with reason `selected-root-module-missing` and safe context `missingModuleId`; it does not include preset provenance. Multiple missing roots are sorted by canonical module id so permutation of input cannot change the selected failure.

All selected roots and their transitive required dependencies must become enabled. Excluded modules may not be added, even if an excluded dependency is absent from the installed manifest. For an enabled module `A` with `extra.coretsia.requires` containing excluded `B`, fail as `CORETSIA_MODULE_CONFLICT`, reason `dependency-excluded`, with only safe `requiredByModuleId` and `excludedModuleId` context. Excluded-dependency detection precedes selection of missing-required-dependency candidates. If a non-excluded required dependency is absent, fail as `CORETSIA_MODULE_REQUIRED_MISSING`, reason `dependency-required-module-missing`, with safe `requiredByModuleId` and `missingModuleId`.

An attempted exclusion of a non-excludable `ModePreset::required` root fails earlier in `ModuleSelectionFactory` as `CORETSIA_MODULE_SELECTION_INVALID` (`module-selection-required-excluded`); Phase B does not reinterpret that preset policy.

## Enabled-module conflicts

After required dependency closure is collected, every enabled descriptor's `extra.coretsia.conflicts` is checked against all enabled module ids. An explicitly excluded, non-enabled module does not create an enabled-module conflict. For each conflicting enabled pair, collect a sorted candidate and throw `ModuleConflictException::between()` as `CORETSIA_MODULE_CONFLICT`, reason `module-conflict`; pair ordering and candidate choice are independent of descriptor order.

## Graph failure precedence

The complete pipeline first rejects unsupported discovery source, invalid namespace/preset source and selection-policy failures before reading the installed manifest. Once that snapshot is read, all installed descriptors are validated **before** graph-policy failure selection.

Within graph-policy evaluation, collect conflict candidates for excluded required dependencies (`dependency-excluded`) and enabled-module conflicts (`module-conflict`), as well as missing candidates for selected roots (`selected-root-module-missing`) and required dependencies (`dependency-required-module-missing`).

1. If any conflict candidate exists, sort all conflict candidates by the canonical key below using byte-order `strcmp`, then throw the first:

   ```text
   lowerModuleId + "\0" + higherModuleId + "\0" + reason
   ```

   `lowerModuleId` and `higherModuleId` are the two module ids sorted by byte-order `strcmp`. The same key applies to `dependency-excluded` and `module-conflict` candidates. An excluded required dependency contributes a conflict candidate even when the excluded module is absent from the installed manifest.

2. Otherwise sort all required-missing candidates by the canonical key below using byte-order `strcmp`, then throw the first:

   ```text
   requiredBy + "\0" + missingModuleId + "\0" + reason
   ```

   For `selected-root-module-missing`, `requiredBy` is the missing selected root's module id. For `dependency-required-module-missing`, `requiredBy` is the requiring module's id. This internal candidate identity MUST NOT add preset provenance to the public exception context.

3. Otherwise detect cycles and throw `CORETSIA_MODULE_CYCLE_DETECTED` if any exist.

Thus conflict candidates (including excluded dependencies) precede missing candidates, but an excluded-dependency candidate and an enabled-module conflict are ordered by the canonical conflict-candidate key, not an additional hard-coded subtype ranking. Unselected installed descriptor invalidity takes precedence over graph-policy failures.

## Cycle and topological-order policy

Cycle detection occurs only after installed-manifest validation and graph-policy conflict and required-missing candidate selection have completed. A cycle in the enabled dependency graph fails deterministically with `CORETSIA_MODULE_CYCLE_DETECTED` and reason `module-cycle-detected`.

Cycle diagnostics contain a `moduleIds` list of unique, valid module ids sorted by byte-order `strcmp`. Cycle selection and diagnostic ordering MUST NOT depend on input descriptor order or filesystem enumeration. The diagnostic list identifies the selected cycle's modules; it is not required to reproduce the dependency traversal path.

Cycle diagnostics MUST NOT expose graph dumps, raw metadata, filesystem paths, application roots, raw Composer or preset payloads, stack traces, secrets, or environment-specific values.

For an acyclic graph, `topologicalOrder` contains every enabled module exactly once, with every dependency preceding its dependent. Simultaneously eligible modules are selected using deterministic byte-order tie-breaking. `topologicalOrder` is **not** alphabetically sorted after graph ordering.

## Output policy

The immutable `ModulePlan` exports exactly `app`, `enabled`, `excluded`, `modules`, `schemaVersion`, and `topologicalOrder`.

`app` identifies the selected app target and does not influence runtime module selection. `enabled` and `excluded` are disjoint, unique lists sorted by module id using byte-order `strcmp`.

`modules` contains exactly one resolved `ModulePlanEntry` for every enabled module, ordered by module id using byte-order `strcmp`. Each entry contains only its module id, Composer package name, required runtime module ids, and conflicting runtime module ids. Required and conflicting module-id lists are deterministic, unique, and strcmp-sorted.

`topologicalOrder` contains every enabled module exactly once, in dependency-respecting order. No excluded module id may appear in `enabled`, `modules`, or `topologicalOrder`.

`ModulePlan` MUST NOT contain or export application roots, package installation paths, preset source paths, absolute filesystem paths, provider class lists, raw Composer metadata, raw preset or configuration payloads, service instances, containers, closures, resources, or filesystem handles.

`ModulePlan::SCHEMA_VERSION` remains `1`. The generated module-manifest artifact retains the identity `module-manifest@1`, envelope schema version `1`, and the exact ModulePlan-derived payload defined above.

## Safe diagnostics and observability

Graph exceptions inherit the stable message format defined by `ModuleResolutionException`:

```text
ERROR_CODE: reason-token
```

Exception messages MUST NOT contain context values. Graph exception contexts contain only the documented safe module-id fields and deterministic module-id lists. Selected-root-missing diagnostics MUST NOT include preset provenance.

Exception messages, contexts, and observability payloads MUST NOT disclose filesystem paths, application roots, filesystem layout, raw Composer metadata, raw preset or configuration payloads, graph dumps, service instances, stack traces, secrets, PII, environment-specific values, or messages from previous throwables.

`ModuleResolutionOrchestrator` owns `kernel.modules_resolve` observability. It maps excluded-dependency and enabled-module conflict failures to `conflict`, missing roots and required dependencies to `required_missing`, invalid selection to `selection_invalid`, and canonical shadowing to `preset_invalid`.

Span and metric labels contain only `operation` and bounded `outcome` values. Module ids and preset names MUST NOT appear in metric labels. Module ids may appear in safe log context only after canonical-id validation, deduplication, and strcmp sorting. Log context MUST NOT contain raw exception messages, paths, raw metadata, or preset payloads.

Graph resolution MUST NOT depend on logging, tracing, or metrics backends. Observability failures MUST NOT alter the graph-policy result or replace its primary resolution exception.

## Consequences

The effective root set is explicit before Phase B begins. Every selected root must be present in the installed manifest; a missing selected root is a deterministic fatal resolution failure.

An excluded module cannot be activated through dependency closure. If an enabled module requires an excluded module, resolution fails with a deterministic conflict rather than silently omitting or enabling that dependency.

Conflicts are evaluated only between enabled modules. Excluding a module prevents it from becoming an enabled root, but does not override a dependency requirement declared by another enabled module.

All installed descriptors are validated before graph-policy failures are selected. Multiple simultaneous graph failures produce one deterministic exception according to the canonical candidate keys in this ADR.

Runtime module dependency and conflict metadata remain independent of Composer package dependency solving. Module selection and graph resolution do not install packages or modify the installed package set.

`ModulePlan` is an immutable, deterministic representation of the resolved executable runtime graph. Runtime boot consumes its validated artifact-derived representation without reloading preset policy or recomputing module selection.

## Non-goals

This ADR does not define Composer dependency solving, package installation, package synchronization, or mutation of the installed module set.

It does not define module boot lifecycle, service provider execution, configuration Phase B merge, generated artifact writing, CLI command output, or HTTP middleware selection.

It does not introduce application-local module-selection files or automatic module discovery from filesystem layout. Effective selection is supplied to Phase B exclusively through `ModuleSelection`.

It does not introduce runtime preset loading, runtime module-graph recomputation, or new artifact identities or schema versions.

## Related SSoT

- `docs/ssot/modules-and-manifests.md`
- `docs/ssot/modes.md`
- `docs/ssot/artifacts.md`
- `docs/ssot/observability.md`

## Related ADRs

- `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
- `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`
