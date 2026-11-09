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

# ADR-0033: Application Dependency Synchronization from Configuration-Owned Installation Intent

```yaml
adrVersion: 1
status: pre-accepted
owner: core/kernel
```

## Context

Coretsia applications can select different runtime module policies for explicit application targets while still sharing one Composer project, one `composer.lock`, and one `vendor/` graph. Runtime policy therefore cannot be treated as an installed-package list, and installed Composer packages cannot be treated as the enabled runtime graph.

Application dependency synchronization needs a deterministic boundary between four different concerns:

```text
BootstrapSourceResolver
    -> one BootstrapSourceSnapshot
    -> effective configuration-owned activeTargets / ProjectApplicationSet
    -> independent target-local runtime selection policies
    -> project-wide physical Coretsia package requirements
    -> fresh installed per-target ModulePlan verification
```

The consumer project remains the owner of its root Composer manifest. Coretsia may manage only root requirements that it has explicitly claimed through its dependency-sync marker. Other root requirements are project-owned state.

## Decision

### Decision 1: Kernel owns the consumer-side subsystem and public boundary

Application dependency synchronization is a Kernel-owned consumer-side subsystem under `Coretsia\Kernel\DependencySync`.

The public consumer-facing values are:

```text
ProjectApplicationSet
ProjectInstallationIntent
ProjectPackagePlan
DependencySyncExecutionPolicy
ProjectDependencySync
ProjectDependencySyncResult
DependencySyncErrorCodes
DependencySyncException
```

Implementation helpers for catalog loading, Composer reconciliation/execution, fresh-process verification, and process execution remain internal Kernel implementation.

### Decision 2: Installation membership is configuration-owned

Effectful application dependency synchronization operates on one complete, non-empty, configuration-owned `ProjectApplicationSet`.

`BootstrapSourceResolver` resolves the optional project-wide `activeTargets` bootstrap value from `config/app.php` and permitted `CORETSIA_BOOT_*` environment sources into a `BootstrapSourceSnapshot`. Kernel-owned `ProjectDependencySync` derives the effective `ProjectApplicationSet` from that snapshot, applying the `['web']` fallback only when `activeTargets` is absent.

The canonical AppTarget values are:

```text
api
console
web
worker
```

An explicitly empty `activeTargets` list is invalid; absence is preserved in the source snapshot until DependencySync derives installation membership.

Preset-map keys, application directories, installed packages, and runtime metadata MUST NOT add targets implicitly.

The canonical structured bootstrap environment inputs are:

```dotenv
CORETSIA_BOOT_ACTIVE_TARGETS=["web","worker"]
CORETSIA_BOOT_PRESETS={"web":"express","worker":"hybrid"}
CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"worker":["platform.worker"]}
CORETSIA_BOOT_MODULE_OVERRIDES_EXCLUDE={"web":[]}
```

`CORETSIA_BOOT_ACTIVE_TARGETS` is a non-empty JSON list of canonical targets and replaces lower-priority membership as a complete list.

`CORETSIA_BOOT_PRESETS` is a JSON object mapping target to preset string. Each include/exclude environment key is a separate JSON object mapping target to a canonical ModuleId string list.

For these structured maps, `{}` is a no-op and `{"web":[]}` explicitly clears only the corresponding module override list. Entries merge independently by target and include/exclude field. Malformed JSON, repeated object keys, invalid targets, and invalid ModuleIds fail closed before higher-priority values can conceal them.

These environment inputs are interpreted only by canonical Bootstrap Phase A source resolution. The complete allowlist, validation, source precedence, and appEnv freeze rules belong to `docs/ssot/config-and-env.md` and `docs/ssot/config-precedence-matrix.md`.

For each selected target, the effective preset precedence is:

```text
explicit BootstrapInput::preset()
    -> merged presets[appTarget]
    -> merged global preset
    -> kernel.boot.default_preset
```

The global preset is a fallback for each selected target, not an application membership source.

The project-owned Skeleton DependencySync operations are:

```text
php coretsia plan
php coretsia review
php coretsia apply
```

They use the complete configuration-owned application set. They MUST NOT support a `--target` argument.

A temporary `--preset=<target>=<preset>` is allowed only for `plan` and `review`, through the existing `ProjectInstallationIntent::fixedPresetByTarget()` and explicit `BootstrapInput::preset()` seam.

Effectful `apply` MUST reject temporary fixed presets even when the supplied values match persistent configuration. It MUST use only the effective persistent bootstrap configuration.

The existing programmatic `ProjectDependencySync::plan()` and non-effectful `synchronize()` MAY accept an explicit installation intent for read-only analysis.

Effectful programmatic synchronization MUST reject an explicit application set differing from the current configuration-owned set, and MUST reject non-empty fixed presets before Composer effects.

`activeTargets` does not select a runtime AppTarget. Every runtime entrypoint retains its independent explicit `BootstrapInput::appTarget()` contract.

No separate installation configuration registry, preset registry, or persistence format is introduced. DependencySync MUST NOT rewrite `config/app.php` or dotenv files.

### Decision 3: Installation planning reuses one canonical Phase A source snapshot

Kernel-owned DependencySync performs one project-wide Bootstrap Phase A source resolution per planning attempt.

`BootstrapSourceResolver::resolveApplicationRoot()` returns an immutable `BootstrapSourceSnapshot` containing normalized source overrides, frozen appEnv, frozen environment source policy, safe provenance, and one immutable environment repository.

The snapshot determines effective `activeTargets` before target-local planning begins. The same snapshot MUST be reused for all selected targets without repeated config/app.php, dotenv, or process environment loading.

The canonical planning path is:

```text
consumer application root
    -> BootstrapSourceResolver
    -> one BootstrapSourceSnapshot
    -> effective ProjectApplicationSet
    -> ProjectInstallationIntent
    -> BootstrapConfigResolver per target (same snapshot)
    -> namespace-bound target-local ModePreset
    -> target-local ModuleSelection
    -> release installation catalog
    -> per-target transitive module closure
    -> project-wide physical module/package union
    -> ProjectPackagePlan
```

`ModuleSelection` remains Kernel-internal. The immutable public `ProjectPackagePlan` records target-specific effective presets, optional read-only fixed presets, enabled/excluded module ids, expected module metadata, the project-wide union, and desired Coretsia root Composer requirements.

The plan also retains an internal canonical bootstrap installation identity. It is built from the approved application set, frozen appEnv/source policy, and effective presets and module overrides of active targets only.

The identity MUST NOT include inactive target-specific overrides, debug, artifactsCacheDir, source provenance, unrelated environment keys, credentials, raw dotenv JSON, or absolute application-root paths.

`ProjectPackagePlan::equals()` compares this canonical identity in addition to the existing planning fields. The public Skeleton plan/review/apply JSON payload MUST NOT expose the internal identity.

The project-wide union determines which physical Coretsia packages are required. It does not combine target-local runtime conflicts or exclusions into one runtime plan.

No second ConfigKernel, package planner, module graph resolver, installation catalog, or preset registry is introduced.

### Decision 4: Pre-install package mapping uses the release installation catalog

Pre-install planning uses the versioned package resource:

```text
packages/core/kernel/resources/packaging/installation-catalog.php
```

The catalog is generated by repository authoring tooling from validated package metadata and release-line policy. It contains consumer-safe module/package planning data and no repository source topology.

`ModuleId` is not a Composer package name. Conversion from module identity to Composer identity occurs only through catalog lookup.

Installed runtime discovery remains separate and continues to consume Composer installed metadata through `ModuleManifest`.

### Decision 5: DependencySync manages only explicitly owned Composer roots

DependencySync records only roots that it owns under:

```text
extra.coretsia.dependencySync
```

The marker schema is:

```json
{
  "schemaVersion": 1,
  "managedRequire": ["coretsia/..."],
  "lastAppliedRequire": {
    "coretsia/...": "<constraint>"
  }
}
```

Only previously managed Coretsia root requirements may be rewritten or removed. An untracked root requirement is project-owned by default, including an existing `coretsia/framework` requirement and any other untracked `coretsia/*` requirement.

A tracked requirement whose current constraint differs from `lastAppliedRequire` is a managed-state conflict and is not overwritten.

If a desired Coretsia package already exists as a project-owned `require` root, its existing constraint is preserved and may satisfy the plan without becoming managed. A desired package present only in `require-dev` is an unsupported ownership collision and is not moved or duplicated.

### Decision 6: Explicit third-party roots remain protected project state

Every explicit non-Coretsia package root in `require` or `require-dev` is protected project state for one synchronization.

DependencySync preserves its root constraint, locked identity, and installed identity. A Coretsia solve that would require changing a protected root fails instead of broadening the operation to update that root.

A missing or stale lock cannot be repaired through a broad update while protected roots exist because there is no authoritative frozen identity that permits such widening.

### Decision 7: Composer effects are explicit and project-wide

Planning and review are non-effectful. Composer mutation occurs only for an explicit apply operation.

The project-owned Skeleton adapter exposes `php coretsia plan`, `php coretsia review`, and `php coretsia apply` independently of the normal `platform.cli` command catalog. These operations are available after Kernel-only Composer bootstrap, before the selected Micro runtime packages have been installed.

The adapter MUST reject `--target` for all three operations and MUST reject `--preset` for apply. Temporary target-qualified presets are allowed only for plan/review.

The existing apply-only authorization flags are `--allow-composer-scripts`, `--allow-composer-plugins`, `--allow-broad-update`, and `--allow-repair`.

The existing machine JSON success/error envelope, schemaVersion, stable error codes, and bounded safe context remain unchanged.

The execution policy independently authorizes:

```text
apply
Composer scripts
Composer plugins
broad update
vendor repair
```

Scripts and plugins are disabled unless explicitly authorized. Broad update is permission, not a default widening rule: a lock-current change uses the constrained Coretsia-targeted solve when that solve is valid.

One consumer project has one Composer graph. DependencySync does not create separate `vendor/` trees for targets.

Composer subprocesses use exact argv execution with no shell fallback. Runtime boot never invokes dependency synchronization or Composer effects.

### Decision 8: Effectful synchronization uses project locking and durable recovery

Effectful synchronization uses the shared Foundation `ScopedFileLock` at the consumer-owned path:

```text
var/locks/dependency-sync.lock
```

Lock contention is reported separately from lock infrastructure failure.

Under the project lock and before Composer effects, effectful synchronization MUST execute an invocation-local configuration-driven replan.

The replan MUST resolve a new BootstrapSourceSnapshot, rederive configuration-owned activeTargets and ProjectInstallationIntent, and construct a complete new ProjectPackagePlan. The approved and replanned values MUST compare equal through `ProjectPackagePlan::equals()`, including canonical bootstrap installation identity.

Changes to effective application membership, active-target presets, active-target module overrides, frozen appEnv, or source policy MUST fail with `PROJECT_STATE_CHANGED` before Composer effects.

Canonical Bootstrap source failures during locked replan are mapped to `PROJECT_STATE_CHANGED` through the existing bounded BootstrapException source context. Independent preset, catalog, and module-graph failures retain their own existing error codes.

Normalized installation-equivalent changes to inactive targets, debug, artifactsCacheDir, unrelated environment keys, or source provenance MUST NOT independently invalidate the approved plan.

The replan MUST NOT trust the previous explicit application set or reuse the initial BootstrapSourceSnapshot. The existing project lock, Composer state checks, protected-root policy, and recovery protocol remain unchanged.

Before an effectful Composer process starts, DependencySync writes durable recovery material under:

```text
var/dependency-sync/recovery/<receipt>/
```

The Composer lifecycle is not transactionally atomic across `composer.json`, `composer.lock`, and `vendor/`. Once an effectful Composer subprocess has started, a later failure cannot be reported as a clean rollback. Such failures use `RECOVERY_REQUIRED` with a bounded recovery receipt and cause code.

DependencySync does not perform a hidden Composer rollback operation.

### Decision 9: Success requires fresh installed-state verification

Success requires more than a zero Composer exit code. The parent process validates the resulting manifest/lock state and starts the Kernel package verifier through a fresh `PHP_BINARY` process.

The fresh process loads current consumer autoload metadata and resolves one new canonical BootstrapSourceSnapshot from actual consumer configuration.

It rederives effective activeTargets rather than trusting only the approved verification envelope. The resulting application membership and canonical bootstrap installation identity MUST match the approved ProjectPackagePlan.

The verifier reuses the new snapshot to construct independent target-local BootstrapConfig and installed ModulePlan values for every effective active target, without temporary fixed presets.

Invalid effective bootstrap configuration, application membership drift, or installation identity mismatch fails with `INSTALLED_PLAN_MISMATCH`.

The approved planning payload retains its existing internal schemaVersion and required bootstrap installation identity. The outer verification envelope and public Skeleton JSON protocol retain their current version contracts.

The verifier emits a bounded canonical machine protocol; malformed or noncanonical output is rejected.

A physical package installed for another target or as a Composer transitive dependency does not become enabled automatically in an unrelated target's `ModulePlan`.

After an effectful Composer update, the pre-update parent process is not reused as authority for newly installed Kernel/Composer classes. A subsequent synchronization starts from a fresh consumer PHP process.

### Decision 10: Dependency boundaries remain one-way

DependencySync may depend on Kernel Boot/Module policy and lower-level Foundation infrastructure.

DependencySync has no direct implementation dependency on Kernel Artifact or Runtime subsystems, and those subsystems have no direct implementation dependency on DependencySync.

Artifact generation and DependencySync may both use Foundation `ScopedFileLock`; DependencySync verification may reuse Foundation stable JSON decoding. Shared primitives do not transfer domain ownership.

## Consequences

### Positive consequences

- effectful installation membership is configuration-owned, canonical, and deterministic;
- the same approved bootstrap snapshot is reused for all target-local planning within one attempt;
- temporary presets cannot change effectful application membership or persistent installation policy;
- Kernel-only Composer bootstrap makes project-owned DependencySync available before the Micro runtime and platform CLI installation;
- canonical bootstrap installation identity detects effective configuration drift without treating unrelated source changes as installation drift;
- runtime policy and physical package state remain separate concepts;
- package identity mapping has one consumer-safe release catalog;
- project-owned Composer roots are not silently claimed;
- explicit third-party roots are protected across Coretsia synchronization;
- one project maintains one Composer graph while retaining per-target runtime plans;
- effectful failures expose recovery state rather than false atomicity;
- post-install verification observes current installed code through a fresh process.

### Trade-offs

- operators must maintain application membership through configuration-owned `activeTargets` rather than per-command target selection;
- an absent `activeTargets` uses the deliberate `['web']` fallback, while explicitly empty membership remains invalid;
- changing the effective application set or persistent preset policy requires a new configuration-driven synchronization attempt;
- package synchronization is an explicit operator action rather than runtime boot behavior;
- some manifest/lock states require operator intervention instead of automatic widening;
- Composer effects remain externally observable filesystem operations and cannot provide transactional rollback of `vendor/`.

## Non-goals

This decision does not define registration of DependencySync operations in the normal CLI command catalog, runtime auto-install, multiple Composer graphs per project, package publication orchestration, or Artifact/Runtime activation orchestration. Project-local Skeleton launcher routes are governed by the Application Dependency Sync SSoT and do not change Kernel DependencySync ownership.

## Related SSoT

- `docs/ssot/application-dependency-sync.md`
- `docs/ssot/modes.md`
- `docs/ssot/modules-and-manifests.md`
- `docs/ssot/config-and-env.md`
- `docs/ssot/config-precedence-matrix.md`
- `docs/ssot/config-merge-order.md`

## Related ADRs

- `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`
- `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
- `docs/adr/ADR-0025-kernel-conflicts-exclusion-policy.md`
- `docs/adr/ADR-0032-process-exec-descriptor-safety.md`
