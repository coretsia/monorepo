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

# Application Dependency Sync SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: core/kernel
```

## Scope

This document is the Single Source of Truth for configuration-owned consumer installation intent, Kernel package planning, managed Composer root reconciliation, Composer execution policy, fresh installed verification, recovery semantics, and the shipped consumer dependency-sync adapter.

It governs the consumer-side DependencySync subsystem for one consumer project and its effective configuration-owned application set. Explicit programmatic installation intent remains available for read-only planning but MUST NOT override configuration-owned membership during effectful synchronization.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Coretsia needs one deterministic consumer-side path from configuration-owned application installation intent to a physically compatible Composer graph without turning runtime boot, preset selection, or installed module discovery into package-management mechanisms.

The subsystem therefore exists to:

- derive the effective application set from canonical Bootstrap Phase A configuration and resolve installation policy independently for every selected target;
- map validated runtime `ModuleId` values to materialized Coretsia Composer package identities only through the release installation catalog;
- derive one deterministic project-wide physical package union while preserving target-local runtime policy;
- reconcile only DependencySync-owned Coretsia root requirements and preserve project-owned state;
- execute Composer effects only after explicit authorization;
- verify the resulting installed state in a fresh process and produce one valid target-local `ModulePlan` per selected target;
- fail closed with stable machine codes and truthful recovery semantics.

## Authority and ownership

The Kernel owns application dependency synchronization policy and orchestration.

The governed Kernel implementation is under:

```text
packages/core/kernel/src/DependencySync/**
packages/core/kernel/bin/dependency-sync-verify.php
packages/core/kernel/resources/packaging/installation-catalog.php
```

The shipped consumer integration seam is:

```text
packages/applications/skeleton/bin/dependency-sync.php
```

Repository authoring owns generation of the distributed installation catalog through:

```text
tools/build/installation_catalog.php
```

Repository tooling is not a consumer/runtime dependency.

Foundation may provide domain-neutral lower-level primitives such as `ScopedFileLock` and stable JSON decoding.

Reuse of those primitives does not transfer DependencySync ownership out of Kernel.

## Source-of-truth boundaries

This SSoT owns configuration-owned installation intent, package planning, managed Composer root ownership, effect policy, project locking, recovery, fresh installed verification, machine failure codes, and the shipped consumer workflow.

Adjacent contracts remain owned by their dedicated sources:

- `docs/ssot/modes.md` owns `ModePreset`, effective preset policy, target-local overrides, and the `ModePreset` → `ModuleSelection` boundary;
- `docs/ssot/modules-and-manifests.md` owns installed `ModuleManifest` and target-local `ModulePlan` semantics;
- `docs/architecture/PACKAGING.md` owns package identity, distribution, and release-line packaging policy;
- `docs/architecture/DEPENDENCIES.md` owns package and implementation dependency direction;
- ADR-0033 owns the architectural decision to derive consumer Composer state from configuration-owned installation intent.

This SSoT MUST NOT be interpreted as redefining those contracts.

## Core invariants

- The effectful installation application set is configuration-owned, non-empty, unique, canonical, and project-local.
- Effective `activeTargets` is resolved through one Bootstrap Phase A source snapshot. An absent value uses the DependencySync-owned `['web']` fallback; an explicitly empty value is invalid.
- Application directories, preset-map keys, installed packages, and runtime metadata MUST NOT infer target membership.
- An explicit `ProjectInstallationIntent` MAY select another application set for read-only planning or review, but effectful synchronization MUST use configuration-owned membership.
- Temporary fixed presets MAY affect read-only planning and review only. Effectful synchronization MUST reject them before Composer effects.
- `BootstrapSourceSnapshot` is project-wide; `BootstrapConfig` and `ModuleSelection` remain target-local.
- Every selected target resolves its effective preset and `ModuleSelection` independently through the existing Kernel Phase A path.
- `ModuleId` and Composer package identity are distinct; package identity is obtained only from the release installation catalog.
- The project-wide module/package union is physical installation intent only; runtime exclusion and conflict semantics remain target-local.
- One consumer project has one `composer.json`, one `composer.lock`, and one `vendor/` graph for all selected targets.
- Only tracked DependencySync-managed Coretsia root requirements may be rewritten or removed.
- Untracked roots are project-owned, including untracked `coretsia/*` roots.
- Explicit non-Coretsia roots are protected project state and MUST retain their root, lock, and installed identities across synchronization.
- DependencySync planning and Composer effects MUST NOT run during runtime boot.
- Composer effects require explicit apply authorization; review remains non-effectful.
- Successful effectful synchronization requires current lock/vendor state, an approved configuration-owned installation identity, and fresh installed verification for every effective active target.
- Effectful synchronization MUST re-resolve the configuration-owned application set and installation-relevant bootstrap state under the project lock before Composer effects.
- A repeated unchanged synchronization from a fresh process is a stable no-op.
- Recovery semantics MUST remain truthful: DependencySync does not promise atomic rollback of `composer.json`, `composer.lock`, and `vendor/`.

## Installation intent

Configuration-driven DependencySync operations MUST derive their application membership from one canonical `BootstrapSourceSnapshot` produced by `BootstrapSourceResolver::resolveApplicationRoot()` for the consumer project root.

The project-wide bootstrap-only `activeTargets` value is defined through `config/app.php` or permitted `CORETSIA_BOOT_ACTIVE_TARGETS` environment sources. It is not a field of target-local `BootstrapConfig`.

The canonical AppTarget tokens are:

```text
api
console
web
worker
```

The effective installation membership rules are:

- A configured `activeTargets` MUST contain a non-empty list of unique canonical AppTarget values.
- If `activeTargets` is absent from every permitted bootstrap source, DependencySync MUST use `['web']`.
- An explicitly empty `activeTargets` MUST fail validation instead of representing an empty installation.
- Each valid higher-priority `activeTargets` list replaces the entire lower-priority list.
- Preset-map keys, module-override keys, application directories, installed packages, and runtime metadata MUST NOT add or remove application membership implicitly.

Kernel-owned `ProjectApplicationSet` validates canonical application membership, uniqueness, non-emptiness, and deterministic ordering.

`ProjectInstallationIntent` retains one immutable `ProjectApplicationSet` and an optional `fixedPresetByTarget` map. A fixed preset MUST refer to an application already in the selected set.

`ProjectDependencySync::planConfigured()` derives the application set from the current approved snapshot and MAY accept temporary target-qualified fixed presets for read-only planning.

`ProjectDependencySync::synchronizeConfigured()` derives the application set from current configuration. A non-effectful review MAY use temporary fixed presets; effectful synchronization MUST reject any non-empty fixed-preset map.

The existing programmatic `ProjectDependencySync::plan()` and non-effectful `synchronize()` MAY accept an explicit `ProjectInstallationIntent` for read-only analysis.

For effectful `ProjectDependencySync::synchronize()`, the explicit application set MUST exactly equal the current configuration-owned `ProjectApplicationSet`. A mismatch MUST fail with `INSTALLATION_INTENT_INVALID` before Composer effects. Any non-empty `fixedPresetByTarget` MUST also fail, regardless of whether its values match persistent configuration.

Normal target-local preset precedence is:

```text
BootstrapInput::preset()
    -> merged presets[appTarget]
    -> merged global preset
    -> kernel.boot.default_preset
```

A temporary fixed preset is passed through the existing explicit `BootstrapInput::preset()` seam for the selected target only. It MUST NOT be persisted to `config/app.php`, dotenv files, Composer metadata, or an independent preset registry.

During effectful synchronization, temporary fixed presets are forbidden; the resolved target-local presets come exclusively from effective persistent bootstrap configuration and Kernel defaults.

`moduleOverrides[target].include` and `moduleOverrides[target].exclude` remain target-local runtime selection policy. Excluding a module from one target MUST NOT remove a physical package still required by another target.

The `activeTargets` project-wide membership contract MUST NOT change `BootstrapInput::appTarget()` or implicitly select the runtime target of any application entrypoint.

## Installation planning

Configuration-driven planning MUST resolve one immutable `BootstrapSourceSnapshot` for the complete planning attempt before constructing the effective `ProjectApplicationSet`.

The same snapshot and immutable environment repository MUST be reused for every selected target. Neither `config/app.php`, dotenv layers, nor process environment values may be independently reloaded for each target.

The canonical pipeline is:

```text
consumer application root
    -> BootstrapSourceResolver
    -> one BootstrapSourceSnapshot
    -> effective activeTargets / ProjectApplicationSet
    -> ProjectInstallationIntent
    -> BootstrapConfigResolver per target (same snapshot)
    -> target-local namespace-bound ModePreset
    -> target-local ModuleSelection
    -> ReleaseInstallationCatalog
    -> ModuleGraphResolver::resolveEntries() per target
    -> project-wide enabled module union
    -> ProjectPackagePlan
```

`ModuleSelection` is Kernel-internal and MUST NOT be serialized as the public installation plan.

`ProjectPackagePlan` is immutable and deterministic. It records:

```text
applications
effectivePresetsByTarget
fixedPresetByTarget
enabledModuleIdsByTarget
excludedModuleIdsByTarget
unionModuleIds
expectedModuleMetadataById
desiredRootRequirements
bootstrapInstallationIdentity (internal)
```

The canonical bootstrap installation identity is derived from the approved application set, frozen appEnv, frozen environment source policy, effective presets, and effective module overrides of active targets only.

The identity MUST NOT include inactive target-specific overrides, debug, artifactsCacheDir, arbitrary package environment values, raw dotenv JSON, source provenance, credentials, or absolute application-root paths.

`ProjectPackagePlan::equals()` MUST include the canonical bootstrap installation identity so that configuration-relevant drift is detected even when the physical Composer package union happens to remain unchanged.

The internal identity is required for approved planning and verification but MUST NOT be added to the public Skeleton plan/review/apply JSON payload.

The internal approved planning payload uses `schemaVersion=1` and requires `bootstrapInstallationIdentity`. The public plan/review/apply JSON protocols retain `schemaVersion=1`. Approved payloads that omit required fields or violate exact-key validation MUST fail closed through the canonical verification error boundary.

All deterministic sets/maps use canonical byte-order ordering.

The project-wide union is physical installation intent only. Runtime conflict and exclusion semantics remain per target.

## Release installation catalog

Pre-install planning MUST use the committed Kernel package resource:

```text
packages/core/kernel/resources/packaging/installation-catalog.php
```

The current schema is `1` and contains:

```text
schemaVersion
releaseLine
publicConstraint
modules
```

Each module record contains exactly the consumer-safe planning fields:

```text
composerName
requires
conflicts
```

The canonical materialized installation catalog MUST establish these identities:

| ModuleId             | Composer package              | Module dependencies                                    |
|----------------------|-------------------------------|--------------------------------------------------------|
| `core.foundation`    | `coretsia/core-foundation`    | —                                                      |
| `core.kernel`        | `coretsia/core-kernel`        | `core.foundation`                                      |
| `platform.cli`       | `coretsia/platform-cli`       | `core.kernel`, `platform.errors`, `platform.redaction` |
| `platform.errors`    | `coretsia/platform-errors`    | `core.foundation`, `platform.redaction`                |
| `platform.logging`   | `coretsia/platform-logging`   | `core.foundation`, `core.kernel`, `platform.redaction` |
| `platform.redaction` | `coretsia/platform-redaction` | `core.foundation`                                      |
| `platform.worker`    | `coretsia/platform-worker`    | `core.kernel`                                          |

The catalog is generated by repository-only authoring tooling:

```bash
php tools/build/installation_catalog.php --apply
php tools/build/installation_catalog.php --check
```

The generator derives catalog entries from validated package manifests and release-line policy.

The materialized catalog MUST contain `platform.logging` and its complete required-module closure before planning any canonical preset; a stale catalog missing a selected logging module fails closed instead of silently substituting Foundation `NoopLogger`. Catalog generation and its rerun-no-diff check MUST be completed before release.

Repository source paths MUST NOT appear in the distributed catalog.

Consumer/runtime code MUST NOT use the repository tooling package index as an installation catalog.

Unknown modules, dangling module edges, inconsistent release policy, invalid catalog shape, or a missing Kernel catalog resource MUST fail closed.

## Bootstrap floor

DependencySync executes after the consumer Kernel-only Composer baseline is installed and `vendor/autoload.php` exists.

The canonical bootstrap requirement is supplied by the Skeleton's project-owned `coretsia/framework` metapackage:

```text
coretsia/skeleton
    -> coretsia/framework
        -> php
        -> coretsia/core-kernel
            -> coretsia/core-contracts
            -> coretsia/core-foundation
                -> coretsia/core-contracts
```

The graph illustrates the minimal Coretsia bootstrap dependency closure; Composer may install additional transitive dependencies declared by the participating packages.

`coretsia/framework` is a dependency-only metapackage. Its direct Composer requirements MUST remain limited to `php` and `coretsia/core-kernel`. It does not own a second runtime, preset registry, or dependency-sync implementation.

The initial baseline MUST NOT require `coretsia/platform-cli` or `coretsia/platform-logging`.

The project-owned Skeleton launcher provides three early operations independently of the installed platform CLI:

```bash
php coretsia plan
php coretsia review
php coretsia apply
```

These operations use the Kernel-owned DependencySync subsystem through the Skeleton adapter. They do not require the Micro runtime to have been installed.

Before the applicable mode packages have been installed, ordinary commands such as `list`, `help`, and `doctor` through `platform.cli` are not guaranteed to be available.

The default Kernel preset remains `micro` through `kernel.boot.default_preset`. The `micro` preset requires `core.foundation` and `core.kernel` and selects `platform.cli` and `platform.logging` as independently excludable runtime roots.

The initial Composer baseline and the default Micro runtime selection MUST NOT be treated as equivalent physical installation states.

DependencySync resolves the selected targets' required packages through the release installation catalog. The canonical `micro` module closure, when its selectable roots remain enabled, includes:

```text
core.foundation
core.kernel
platform.cli
platform.errors
platform.logging
platform.redaction
```

`platform.errors` is required through the selected CLI module; `platform.redaction` is required through CLI, Errors, and Logging dependency edges.

Selecting or resolving the `micro` preset does not install these physical packages. Only explicitly authorized DependencySync apply may reconcile the required managed Composer roots and installed vendor state.

An explicit `moduleOverrides.exclude` for `platform.logging` affects only the selected target's runtime selection, not an unrelated target's requirements or the project-wide physical Composer union.

If no logging provider is selected, `LoggerInterface` retains the Foundation `NoopLogger` binding. A selected but missing module, or an excluded required transitive dependency, fails through existing Kernel graph policy rather than silently selecting a fallback.

Every planned target MUST resolve a module closure containing `core.kernel`.

DependencySync does not provide a zero-vendor bootstrap path. Baseline installation and runtime boot MUST NOT implicitly run DependencySync plan, review, apply, or Composer reconciliation.

## Managed Composer root ownership

DependencySync owns only root requirements recorded in:

```text
extra.coretsia.dependencySync
```

Canonical marker shape:

```json
{
  "extra": {
    "coretsia": {
      "dependencySync": {
        "schemaVersion": 1,
        "managedRequire": [
          "coretsia/core-foundation",
          "coretsia/core-kernel",
          "coretsia/platform-cli",
          "coretsia/platform-errors",
          "coretsia/platform-logging",
          "coretsia/platform-redaction"
        ],
        "lastAppliedRequire": {
          "coretsia/core-foundation": "<applied-constraint>",
          "coretsia/core-kernel": "<applied-constraint>",
          "coretsia/platform-cli": "<applied-constraint>",
          "coretsia/platform-errors": "<applied-constraint>",
          "coretsia/platform-logging": "<applied-constraint>",
          "coretsia/platform-redaction": "<applied-constraint>"
        }
      }
    }
  }
}
```

The marker illustrates an explicitly applied `micro` installation plan.

`<applied-constraint>` is documentation-only notation, not a literal Composer constraint or a value resolved dynamically at runtime. In an actual consumer `composer.json`, every `lastAppliedRequire` entry MUST contain the exact concrete constraint recorded when DependencySync last successfully applied the corresponding managed root requirement.

The desired constraints are derived from the active release installation catalog policy. The recorded constraints remain unchanged until an explicitly authorized DependencySync operation successfully updates the managed requirements.

Documentation examples MUST NOT require changes solely because the Coretsia release line changes.

Baseline Composer installation does not create or claim DependencySync-managed root requirements.

`managedRequire` MUST be unique and `strcmp`-sorted.

`lastAppliedRequire` MUST contain exactly the same package keys.

A managed root MAY be rewritten or removed only while its current root constraint still equals the recorded `lastAppliedRequire` value.

Divergence MUST fail with `MANAGED_STATE_CONFLICT`.

An untracked root requirement is project-owned.

DependencySync MUST preserve it and MUST NOT add it to the marker merely because it is a Coretsia package.

`coretsia/framework` in the skeleton is project-owned baseline state.

If a desired Coretsia package already exists in project-owned `require`, its existing constraint MUST be preserved and MAY satisfy the package plan.

If a desired Coretsia package exists only in project-owned `require-dev`, synchronization MUST fail with `COMPOSER_UNSUPPORTED_POLICY` rather than moving or duplicating it.

Unrelated `composer.json` values outside the managed subtree MUST preserve their JSON object/list/value semantics.

## Protected third-party roots

Every explicit root package whose Composer name does not start with `coretsia/` is protected project state when it appears in `require` or `require-dev`.

For every protected root, a synchronization MUST preserve:

- the `composer.json` constraint;
- locked package type/version identity;
- locked source/dist reference identity when present;
- installed presence/version/reference identity;
- required physical package-root presence for non-metapackage installations.

DependencySync update targets MUST contain Coretsia root package names only.

A solve that requires changing a protected root MUST fail rather than widening the update scope.

Broad update authorization MUST NOT override protected-root identity.

Missing or stale lock state with any protected root MUST fail before an unrestricted Composer update.

## Consumer workflow

The canonical consumer workflow is:

```text
Kernel-only Composer baseline installation
    -> configuration-owned activeTargets
    -> target-local effective presets and module policy
    -> plan
    -> review
    -> explicitly authorized apply
    -> Composer lock/vendor validation
    -> fresh-process configuration and installed ModulePlan verification
```

The shipped Skeleton project-root launcher is:

```text
coretsia
```

It MUST route only the exact operations `plan`, `review`, and `apply` to the project-owned adapter:

```text
bin/dependency-sync.php
```

Routing occurs before ordinary Composer-installed `platform.cli` host boot. The launcher forwards original argv without interpreting options, selecting targets, or implementing DependencySync policy.

The canonical commands, executed from the Skeleton application root, are:

```bash
php coretsia plan
php coretsia review
php coretsia apply
```

The equivalent direct adapter invocations are:

```bash
php bin/dependency-sync.php plan
php bin/dependency-sync.php review
php bin/dependency-sync.php apply
```

The application set is derived from effective Bootstrap Phase A `activeTargets` in `config/app.php` and permitted environment sources. If no source defines `activeTargets`, the DependencySync-owned default is `['web']`.

The adapter MUST NOT parse `config/app.php` or dotenv independently, infer application membership from directories, or construct a separate installation intent policy. `ProjectDependencySync` owns configuration-driven resolution.

`--target` is not supported by any of the six DependencySync invocations above. Passing it MUST fail with `INSTALLATION_INTENT_INVALID` before Composer effects, synchronization locking, or project-file changes.

This restriction does not change `--target` in ordinary Kernel Ops, `platform.cli`, Worker, or unrelated commands, which retain their own explicit runtime target-selection contracts.

### Operations

| Operation | Behavior |
|-----------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `plan` | Resolves the effective configured application set and returns a deterministic physical installation plan without synchronization |
| `review` | Performs read-only reconciliation and validation using `apply=false`; it does not intentionally mutate Composer state |
| `apply` | Uses current persistent effective bootstrap configuration, performs authorized synchronization, and verifies the resulting installed state in a fresh PHP process |

A temporary target-qualified preset override is supported only for `plan` and `review`:

```bash
php coretsia plan --preset=web=enterprise
php coretsia review --preset=web=enterprise
```

Equivalent direct invocations are:

```bash
php bin/dependency-sync.php plan --preset=web=enterprise
php bin/dependency-sync.php review --preset=web=enterprise
```

The syntax is exactly:

```text
--preset=<target>=<preset>
```

Each target MUST belong to the effective application set. Duplicate target assignments and invalid preset tokens MUST fail with `INSTALLATION_INTENT_INVALID`.

The temporary override affects only the specified target's read-only planning result. It MUST NOT change configuration files, source snapshots for other targets, persistent installation state, or the configured application set.

`apply` MUST reject every `--preset` argument, even when its value equals the configured effective preset.

An actual preset change MUST be made through the permitted persistent bootstrap configuration sources before running ordinary apply.

### Apply authorization

The existing effect authorization flags are apply-only:

```text
--allow-composer-scripts
--allow-composer-plugins
--allow-broad-update
--allow-repair
```

They retain the execution-policy semantics defined below. Scripts and plugins remain disabled unless separately authorized.

The adapter MUST reject these flags for `plan` and `review`. Unknown arguments, duplicate flags, unsupported `--target`, and apply-time `--preset` MUST be rejected before effectful execution.

### Machine JSON protocol

Each adapter invocation MUST emit exactly one machine JSON document on stdout.

Successful output retains the existing public envelope:

```json
{
  "schemaVersion": 1,
  "status": "ok",
  "operation": "plan",
  "result": {}
}
```

The actual `result` object for `plan` contains the canonical plan payload. For `review` and `apply`, it contains the existing synchronization result payload, including the plan.

Failure retains the existing public envelope:

```json
{
  "schemaVersion": 1,
  "status": "error",
  "code": "INSTALLATION_INTENT_INVALID",
  "context": {}
}
```

The stable `DependencySyncErrorCodes` registry, bounded sanitized context, field ordering, `schemaVersion=1`, and existing exit-code contract MUST remain unchanged.

The public plan payload MUST NOT expose the internal `bootstrapInstallationIdentity`, raw bootstrap source snapshots, environment values, or credentials.

These project-local operations are not `cli.command` catalog entries. They do not add implicit dependency synchronization to normal CLI or runtime boot.

## Composer execution policy

One consumer project has one `composer.json`, one `composer.lock`, and one `vendor/` graph for all selected targets.

DependencySync MUST NOT run during runtime boot.

Before effects, DependencySync validates that the lock is current using a non-mutating Composer validation command with scripts/plugins disabled.

When the reconciled manifest changed and the existing lock is current, DependencySync performs one constrained update whose explicit package targets are the reconciled Coretsia root requirements and whose dependency scope uses Composer `--with-dependencies`.

`allowBroadUpdate=true` only authorizes a full update when a documented state requires it.

It MUST NOT widen a healthy constrained solve merely because broad update was permitted.

`allowRepair=true` authorizes one Composer install repair only for installed-vendor incompleteness.

It MUST NOT turn semantic plan/metadata divergence into a generic install repair.

Composer scripts and plugins are disabled unless their dedicated policy flags are explicitly enabled.

Composer and verifier processes MUST execute through exact argv without shell fallback, with bounded stdin/stdout/stderr handling and configured timeouts.

## Configuration-owned approval and locked replan

Effectful synchronization MUST use one approved `ProjectPackagePlan` derived from current configuration-owned application membership and one approved `BootstrapSourceSnapshot`.

The existing invocation-local replan callback runs under the consumer project lock, before Composer effects. It MUST resolve a new BootstrapSourceSnapshot, rederive effective `activeTargets` and `ProjectInstallationIntent`, and construct a complete new `ProjectPackagePlan`.

The locked replan MUST NOT trust only the original explicit application set or reuse the original source snapshot.

The new plan MUST be compared against the approved plan through `ProjectPackagePlan::equals()`, including the canonical bootstrap installation identity.

A change in application membership, effective active-target preset, effective active-target module overrides, frozen appEnv, or environment source policy MUST fail with `PROJECT_STATE_CHANGED` before Composer effects.

A Bootstrap source failure detected during locked replan MUST also become `PROJECT_STATE_CHANGED` when identified through the canonical bounded `BootstrapException` source error context. Independent preset, catalog, or module-graph failures MUST retain their own existing error codes.

The comparison uses normalized effective installation-relevant values, not raw dotenv bytes or source provenance.

Changes limited to debug, artifactsCacheDir, inactive target-specific entries, unrelated package environment values, or source provenance MUST NOT independently cause installation-plan drift.

The project lock, Composer manifest/lock identity checks, protected-root enforcement, and recovery mechanisms remain unchanged.

## Project lock

Effectful synchronization owns:

```text
var/locks/dependency-sync.lock
```

The lock uses Foundation `ScopedFileLock::exclusiveNonBlocking()`.

Contention MUST fail with `PROJECT_SYNC_LOCKED`. Unsafe lock paths, lock creation/open/acquisition infrastructure failures, and release/close failures MUST fail with `PROJECT_SYNC_LOCK_FAILED`.

Review-only execution MUST NOT create/open project lock infrastructure.

## Recovery

Before candidate publication or an effectful Composer process, required recovery state is written under:

```text
var/dependency-sync/recovery/<recoveryReceiptId>/
```

Recovery material captures the exact original `composer.json` and, when present, the original `composer.lock`.

Recovery files MUST complete full write, flush, filesystem synchronization, close, and required permission handling before effects continue.

Unsafe recovery paths or durability failures MUST fail with `RECOVERY_STORAGE_FAILED` before Composer starts.

The synchronization is not transactionally atomic across `composer.json`, `composer.lock`, and `vendor/`.

If an effectful Composer process has started and Composer or post-install verification fails, recovery material MUST be retained and the public failure MUST be `RECOVERY_REQUIRED` with bounded `recoveryReceiptId` and `causeCode` context.

No hidden Composer rollback operation is executed.

Successful synchronization MUST discard its recovery snapshot before returning clean success.

Failure to discard required recovery state is not clean success.

## Fresh installed verification

After a successful effectful Composer operation, DependencySync MUST validate the resulting lock and manifest state and verify installed runtime policy in a fresh PHP process.

Fresh verification MUST independently reconstruct the current effective application set from a newly resolved BootstrapSourceSnapshot, rather than trusting only the application list supplied in the approved verification envelope.

The verifier entrypoint is the Kernel package resource:

```text
bin/dependency-sync-verify.php
```

The parent process supplies a bounded canonical verification envelope over stdin.

The fresh verifier loads the current consumer `vendor/autoload.php`, obtains installed Composer metadata, and invokes the public `ProjectDependencySync::verifyInstalled()` seam.

It MUST resolve one new canonical BootstrapSourceSnapshot from current consumer configuration, rederive the full effective ProjectApplicationSet, and reuse that snapshot for all target-local configurations.

The actual application set MUST match the approved `ProjectPackagePlan::applications()` exactly. An invalid bootstrap source, mismatched membership, or differing canonical installation identity MUST fail with `INSTALLED_PLAN_MISMATCH`.

Verification MUST independently resolve one installed `ModulePlan` for every effective active target and compare it with the approved target-local enabled/excluded sets and expected module metadata.

The verifier MUST NOT reuse the initial planning or locked-replan snapshot and MUST NOT apply temporary fixed presets during effectful verification.

The verification envelope MUST retain the current required internal bootstrap installation identity without changing its existing schemaVersion or the public Skeleton JSON output schema.

A package that is physically installed because another target needs it, or because Composer pulled it transitively, MUST NOT become enabled in a target whose policy did not select it. In particular, `platform.logging` MUST remain disabled in any target that excludes it through `moduleOverrides.exclude`, even when the shared vendor graph contains `coretsia/platform-logging`; the target's installed `ModulePlan` MUST retain the same excluded selection and enabled required dependency closure as the approved plan.

The verifier protocol is canonical and bounded.

Timeout, malformed stdout, non-canonical field order/bytes, unsupported schema, or exit-code/protocol mismatch MUST fail with `VERIFICATION_EXECUTION_FAILED` or `VERIFICATION_INPUT_INVALID` as appropriate.

Installed metadata divergence, vendor incompleteness, plan mismatch, or protected-root identity change MUST fail with their dedicated stable codes.

After an effectful update, the parent process's already-loaded pre-update Kernel/Composer classes MUST NOT become authority for another synchronization.

A subsequent synchronization requires a fresh consumer PHP process.

## Stable no-op

Re-running the same effective configuration-owned installation intent from a fresh process, with unchanged managed Composer requirements, current lock, complete vendor state, matching canonical bootstrap installation identity, and matching per-target installed plans, MUST return a stable no-op.

Installation-equivalent bootstrap JSON maps MUST NOT change this result merely because object-key ordering, safe source provenance, debug, artifactsCacheDir, or inactive target-specific configuration differs.

A no-op may perform non-mutating lock-current validation and fresh verification, but it MUST NOT intentionally rewrite `composer.json`, `composer.lock`, or `vendor/`.

## Canonical multi-target example

Consumer configuration:

```php
return [
    'activeTargets' => ['web', 'worker'],
    'preset' => 'micro',
    'presets' => [
        'worker' => 'enterprise',
    ],
    'moduleOverrides' => [
        'worker' => [
            'include' => [],
            'exclude' => [],
        ],
    ],
];
```

Effective configuration-owned application set:

```text
web
worker
```

Current canonical policy resolves the following target-local module closures:

```text
web    -> micro      -> core.foundation, core.kernel, platform.cli, platform.errors, platform.logging, platform.redaction
worker -> enterprise -> core.foundation, core.kernel, platform.cli, platform.errors, platform.logging, platform.redaction, platform.worker

project union (physical installation intent):
core.foundation, core.kernel, platform.cli, platform.errors, platform.logging, platform.redaction, platform.worker

desired Composer package identities:
coretsia/core-foundation
coretsia/core-kernel
coretsia/platform-cli
coretsia/platform-errors
coretsia/platform-logging
coretsia/platform-redaction
coretsia/platform-worker
```

The `coretsia/framework` requirement remains project-owned baseline state; the desired Composer package identities are derived from the catalog, and only an explicit DependencySync `apply` may reconcile tracked managed root requirements.

Composer resolves one project graph, but physical installation is not runtime module enablement: `ModulePlan(web)` enables `platform.cli`, `platform.errors`, `platform.logging`, and `platform.redaction`, not `platform.worker`, even though the worker target requires that package in the project-wide union. Excluding `platform.logging` for `web` removes it only from the web runtime plan; the package can remain installed, and CLI's `platform.redaction` dependency remains enabled.

The `worker` target participates because it is present in effective `activeTargets`; the presence of `presets.worker` alone would not add it to the installation set. Removing an active target requires a persistent configuration change followed by recomputation of the complete physical package union.

## Error and failure policy

DependencySync exposes this stable machine-code registry:

```text
APPLICATION_SET_INVALID
BASELINE_NOT_INSTALLED
CATALOG_INVALID
CATALOG_MODULE_UNKNOWN
COMPOSER_EXECUTION_FAILED
COMPOSER_LOCK_STALE
COMPOSER_MANIFEST_INVALID
COMPOSER_MANIFEST_WRITE_FAILED
COMPOSER_UNSUPPORTED_POLICY
EXCLUDED_REQUIRED_DEPENDENCY
INSTALLATION_INTENT_INVALID
INSTALLED_METADATA_INVALID
INSTALLED_PLAN_MISMATCH
INSTALLED_VENDOR_INCOMPLETE
MANAGED_STATE_CONFLICT
MANAGED_STATE_INVALID
MODULE_GRAPH_CONFLICT
PRESET_POLICY_INVALID
PROJECT_ROOT_INVALID
PROJECT_STATE_CHANGED
PROJECT_SYNC_LOCKED
PROJECT_SYNC_LOCK_FAILED
PROTECTED_THIRD_PARTY_ROOT_CHANGED
RECOVERY_REQUIRED
RECOVERY_STORAGE_FAILED
VERIFICATION_EXECUTION_FAILED
VERIFICATION_INPUT_INVALID
```

Public messages and context MUST remain bounded and sanitized. Lower-level filesystem/process diagnostics, absolute paths, Composer output, credentials, or arbitrary exception messages MUST NOT become public machine context.

## Dependency boundaries

Allowed implementation direction:

```text
Kernel DependencySync
    -> Kernel Boot / Module policy
    -> Foundation shared primitives
    -> contracts
```

DependencySync MUST NOT import Kernel Artifact/Runtime implementation.

Artifact/Runtime implementation MUST NOT import DependencySync.

Shared Foundation primitives remain domain-neutral.

DependencySync owns its own lock path, error translation, Composer lifecycle, and recovery semantics.

Runtime Phase B MUST NOT depend on the installation catalog, Composer process runner, recovery state, or repository authoring tooling.

## Security and redaction

DependencySync MUST NOT execute through a shell fallback.

Project roots, manifest files, lock/recovery paths, and verifier scripts are validated against their filesystem boundaries.

Unsafe symlinks are rejected on protected write/lock paths.

Machine output and public exception context MUST NOT expose secrets, raw Composer stdout/stderr, arbitrary filesystem diagnostics, or absolute application paths.

## Enforcement evidence

Installation-catalog generation and completeness are enforced by:

```text
tools/tests/Unit/InstallationCatalogGenerationContractTest.php
tools/tests/Unit/InstallationCatalogSourceCompletenessTest.php
```

Kernel planning, ownership, effect, verification, error, and isolation semantics are enforced by focused tests including:

```text
packages/core/kernel/tests/Unit/ProjectApplicationSetTest.php
packages/core/kernel/tests/Unit/ProjectInstallationIntentTest.php
packages/core/kernel/tests/Unit/ProjectPackagePlanTest.php
packages/core/kernel/tests/Unit/ProjectPackagePlannerTest.php
packages/core/kernel/tests/Unit/ReleaseInstallationCatalogTest.php
packages/core/kernel/tests/Unit/ReleaseInstallationCatalogLoaderTest.php
packages/core/kernel/tests/Unit/ComposerRootReconcilerTest.php
packages/core/kernel/tests/Unit/ComposerManifestStoreTest.php
packages/core/kernel/tests/Unit/ComposerSyncCoordinatorTest.php
packages/core/kernel/tests/Unit/InstalledProjectVerifierTest.php
packages/core/kernel/tests/Unit/DependencySyncExecutionPolicyTest.php
packages/core/kernel/tests/Unit/DependencySyncProcessRunnerTest.php
packages/core/kernel/tests/Unit/DependencySyncVerificationCodecTest.php
packages/core/kernel/tests/Unit/ProjectDependencySyncTest.php
packages/core/kernel/tests/Contract/DependencySyncArtifactIsolationContractTest.php
packages/core/kernel/tests/Contract/DependencySyncDoesNotReadToolingPackageIndexContractTest.php
packages/core/kernel/tests/Contract/DependencySyncDoesNotRunAtRuntimeBootContractTest.php
packages/core/kernel/tests/Contract/DependencySyncSharedPrimitiveReuseContractTest.php
```

Cross-package and real-consumer behavior is enforced by:

```text
packages/core/kernel/tests/Integration/DependencySyncBootstrapCompatibilityTest.php
packages/core/kernel/tests/Integration/DependencySyncInstallationCatalogCoversCanonicalPresetsTest.php
packages/core/kernel/tests/Integration/DependencySyncRealComposerMetadataTest.php
tools/tests/Unit/DependencySyncConsumerManifestBoundaryTest.php
tools/tests/Integration/DependencySyncConsumerProjectTest.php
tools/tests/Integration/DependencySyncFailureRecoveryTest.php
tools/tests/Integration/DependencySyncFreshProcessVerificationTest.php
```

Repository validation MUST keep the generated installation catalog rerun-no-diff and run the normal architecture, quality, standard-test, and slow-test rails.

## Non-goals

This SSoT does not define or authorize:

- implicit target discovery from `apps/**`, preset keys, installed packages, or runtime state;
- a zero-vendor bootstrap path;
- automatic dependency synchronization during HTTP, CLI application, worker, or other runtime boot;
- package-name inference from `ModuleId` values;
- consumer/runtime use of `tools/testing/package-index.php` or repository `tools/**` as installation metadata;
- a second persistent preset source, a separate installation-state registry, or mutation of `config/app.php` or dotenv files by DependencySync;
- project-wide reinterpretation of target-local module exclusions or runtime conflicts;
- mutation of project-owned Coretsia roots merely because they are Coretsia packages;
- weakening protected third-party root identity in order to make a Coretsia solve succeed;
- shell fallback for Composer or verifier execution;
- hidden or guaranteed atomic rollback of Composer/vendor side effects;
- registration of dependency synchronization as a normal `cli.command` service or an automatic runtime/bootstrap operation; the explicit project-owned Skeleton plan/review/apply routes are governed by this SSoT;
- direct DependencySync dependency on Kernel Artifact/Runtime implementation or the reverse.

## Change rules

A change to installation intent, catalog schema, marker schema, public error codes, protected-root policy, Composer effect policy, verifier protocol, or recovery lifecycle MUST update this SSoT and the corresponding tests in the same change.

Repository-only generator changes MUST keep the committed installation catalog rerun-no-diff.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Modes SSoT](./modes.md)
- [Config and env SSoT](./config-and-env.md)
- [Config precedence matrix](./config-precedence-matrix.md)
- [Config merge order](./config-merge-order.md)
- [Modules and manifests SSoT](./modules-and-manifests.md)
- [ADR-0033: Application dependency synchronization from configuration-owned installation intent](../adr/ADR-0033-application-dependency-sync-installation-intent.md)
- [Packaging](../architecture/PACKAGING.md)
- [Dependencies](../architecture/DEPENDENCIES.md)
- [Structure](../architecture/STRUCTURE.md)
