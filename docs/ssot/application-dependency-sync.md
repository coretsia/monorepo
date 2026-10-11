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

This document is the Single Source of Truth for explicit consumer installation intent, Kernel package planning, managed Composer root reconciliation, Composer execution policy, fresh installed verification, recovery semantics, and the shipped consumer dependency-sync adapter.

It governs the consumer-side DependencySync subsystem for one consumer project and its explicitly selected application targets.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Coretsia needs one deterministic consumer-side path from explicit application installation intent to a physically compatible Composer graph without turning runtime boot, preset selection, or installed module discovery into package-management mechanisms.

The subsystem therefore exists to:

- resolve installation policy independently for every explicitly selected application target;
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

Foundation may provide domain-neutral lower-level primitives such as `ScopedFileLock` and stable JSON decoding. Reuse of those primitives does not transfer DependencySync ownership out of Kernel.

## Source-of-truth boundaries

This SSoT owns explicit installation intent, package planning, managed Composer root ownership, effect policy, project locking, recovery, fresh installed verification, machine failure codes, and the shipped consumer workflow.

Adjacent contracts remain owned by their dedicated sources:

- `docs/ssot/modes.md` owns `ModePreset`, effective preset policy, target-local overrides, and the `ModePreset` → `ModuleSelection` boundary;
- `docs/ssot/modules-and-manifests.md` owns installed `ModuleManifest` and target-local `ModulePlan` semantics;
- `docs/architecture/PACKAGING.md` owns package identity, distribution, and release-line packaging policy;
- `docs/architecture/DEPENDENCIES.md` owns package and implementation dependency direction;
- ADR-0033 owns the architectural decision to derive consumer Composer state from explicit installation intent.

This SSoT MUST NOT be interpreted as redefining those contracts.

## Core invariants

- The installation application set is explicit, non-empty, unique, and project-local.
- Application directories, preset-map keys, installed packages, and runtime metadata MUST NOT infer target membership.
- Every selected target resolves its effective preset and `ModuleSelection` independently through the existing Kernel Phase A path.
- `ModuleId` and Composer package identity are distinct; package identity is obtained only from the release installation catalog.
- The project-wide module/package union is physical installation intent only; runtime exclusion and conflict semantics remain target-local.
- One consumer project has one `composer.json`, one `composer.lock`, and one `vendor/` graph for all selected targets.
- Only tracked DependencySync-managed Coretsia root requirements may be rewritten or removed.
- Untracked roots are project-owned, including untracked `coretsia/*` roots.
- Explicit non-Coretsia roots are protected project state and MUST retain their root, lock, and installed identities across synchronization.
- DependencySync planning and Composer effects MUST NOT run during runtime boot.
- Composer effects require explicit apply authorization; review remains non-effectful.
- Successful effectful synchronization requires current lock/vendor state and fresh installed verification for every explicit target.
- A repeated unchanged synchronization from a fresh process is a stable no-op.
- Recovery semantics MUST remain truthful: DependencySync does not promise atomic rollback of `composer.json`, `composer.lock`, and `vendor/`.

## Installation intent

One `ProjectInstallationIntent` MUST contain one non-empty `ProjectApplicationSet` of unique explicit `AppTarget` values.

The supported target tokens are:

```text
api
console
web
worker
```

Target membership MUST NOT be inferred from application directories, preset keys, installed packages, or runtime metadata.

`fixedPresetByTarget` MAY contain one explicit preset token for a selected target. It MUST NOT contain a target outside the selected application set.

For a target without a fixed installation preset, effective preset resolution is the existing Phase A precedence:

```text
BootstrapInput::preset()
    -> config/app.php presets[target]
    -> config/app.php preset
    -> kernel.boot.default_preset
```

A fixed installation preset is passed through `BootstrapInput::preset()` for that target. DependencySync MUST NOT write it back to application configuration.

`moduleOverrides[target].include` and `moduleOverrides[target].exclude` remain target-local runtime selection policy. An exclusion in one target MUST NOT remove a package that another selected target requires.

## Installation planning

Planning MUST occur independently for every explicit target through the existing Kernel Phase A selection path.

The canonical pipeline is:

```text
explicit ProjectApplicationSet
    -> ProjectInstallationIntent
    -> BootstrapConfigResolver per target
    -> namespace-bound ModePreset
    -> ModuleSelection per target
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
```

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

The current committed catalog establishes these identities:

| ModuleId             | Composer package              | Module dependencies |
|----------------------|-------------------------------|---------------------|
| `core.foundation`    | `coretsia/core-foundation`    | —                   |
| `core.kernel`        | `coretsia/core-kernel`        | `core.foundation`   |
| `platform.cli`       | `coretsia/platform-cli`       | —                   |
| `platform.redaction` | `coretsia/platform-redaction` | `core.foundation`   |
| `platform.worker`    | `coretsia/platform-worker`    | `core.kernel`       |

The catalog is generated by repository-only authoring tooling:

```bash
php tools/build/installation_catalog.php --apply
php tools/build/installation_catalog.php --check
```

The generator derives catalog entries from validated package manifests and release-line policy. Repository source paths MUST NOT appear in the distributed catalog.

Consumer/runtime code MUST NOT use the repository tooling package index as an installation catalog.

Unknown modules, dangling module edges, inconsistent release policy, invalid catalog shape, or a missing Kernel catalog resource MUST fail closed.

## Bootstrap floor

DependencySync executes after the consumer baseline is installed and `vendor/autoload.php` exists.

The baseline distribution remains:

```text
coretsia/skeleton
    -> coretsia/framework
    -> coretsia/core-kernel
```

Every planned target MUST resolve a module closure containing `core.kernel`. DependencySync does not provide a zero-vendor bootstrap path.

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
          "coretsia/core-kernel"
        ],
        "lastAppliedRequire": {
          "coretsia/core-foundation": "^0.7.0",
          "coretsia/core-kernel": "^0.7.0"
        }
      }
    }
  }
}
```

`managedRequire` MUST be unique and `strcmp`-sorted. `lastAppliedRequire` MUST contain exactly the same package keys.

A managed root MAY be rewritten or removed only while its current root constraint still equals the recorded `lastAppliedRequire` value. Divergence MUST fail with `MANAGED_STATE_CONFLICT`.

An untracked root requirement is project-owned. DependencySync MUST preserve it and MUST NOT add it to the marker merely because it is a Coretsia package.

`coretsia/framework` in the skeleton is project-owned baseline state.

If a desired Coretsia package already exists in project-owned `require`, its existing constraint MUST be preserved and MAY satisfy the package plan. If a desired Coretsia package exists only in project-owned `require-dev`, synchronization MUST fail with `COMPOSER_UNSUPPORTED_POLICY` rather than moving or duplicating it.

Unrelated `composer.json` values outside the managed subtree MUST preserve their JSON object/list/value semantics.

## Protected third-party roots

Every explicit root package whose Composer name does not start with `coretsia/` is protected project state when it appears in `require` or `require-dev`.

For every protected root, a synchronization MUST preserve:

- the `composer.json` constraint;
- locked package type/version identity;
- locked source/dist reference identity when present;
- installed presence/version/reference identity;
- required physical package-root presence for non-metapackage installations.

DependencySync update targets MUST contain Coretsia root package names only. A solve that requires changing a protected root MUST fail rather than widening the update scope.

Broad update authorization MUST NOT override protected-root identity. Missing or stale lock state with any protected root MUST fail before an unrestricted Composer update.

## Consumer workflow

The operator workflow is:

```text
baseline Composer installation
    -> explicit application targets
    -> per-target preset and module policy
    -> plan/review
    -> explicit apply
    -> lock/vendor validation
    -> fresh per-target ModulePlan verification
```

The shipped skeleton adapter is:

```text
bin/dependency-sync.php
```

It supports exactly these operations:

```bash
php bin/dependency-sync.php plan --target=web
php bin/dependency-sync.php review --target=web
php bin/dependency-sync.php apply --target=web
```

Multiple targets are passed by repeating `--target`:

```bash
php bin/dependency-sync.php review --target=web --target=worker
```

An optional installation-only fixed preset is target-qualified:

```bash
php bin/dependency-sync.php review \
  --target=web \
  --target=worker \
  --preset=worker=enterprise
```

Effect flags are apply-only:

```text
--allow-composer-scripts
--allow-composer-plugins
--allow-broad-update
--allow-repair
```

`plan` returns the package plan without synchronization. `review` runs reconciliation/validation with `apply=false` and MUST NOT intentionally mutate Composer state. `apply` is the only adapter operation that may execute Composer effects.

The adapter emits one machine JSON document. Successful output uses `schemaVersion=1`, `status=ok`, `operation`, and `result`; failure uses `schemaVersion=1`, `status=error`, a stable error `code`, and bounded `context`.

No additional CLI alias is part of this contract.

## Composer execution policy

One consumer project has one `composer.json`, one `composer.lock`, and one `vendor/` graph for all selected targets.

DependencySync MUST NOT run during runtime boot.

Before effects, DependencySync validates that the lock is current using a non-mutating Composer validation command with scripts/plugins disabled.

When the reconciled manifest changed and the existing lock is current, DependencySync performs one constrained update whose explicit package targets are the reconciled Coretsia root requirements and whose dependency scope uses Composer `--with-dependencies`.

`allowBroadUpdate=true` only authorizes a full update when a documented state requires it. It MUST NOT widen a healthy constrained solve merely because broad update was permitted.

`allowRepair=true` authorizes one Composer install repair only for installed-vendor incompleteness. It MUST NOT turn semantic plan/metadata divergence into a generic install repair.

Composer scripts and plugins are disabled unless their dedicated policy flags are explicitly enabled.

Composer and verifier processes MUST execute through exact argv without shell fallback, with bounded stdin/stdout/stderr handling and configured timeouts.

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

Recovery files MUST complete full write, flush, filesystem synchronization, close, and required permission handling before effects continue. Unsafe recovery paths or durability failures MUST fail with `RECOVERY_STORAGE_FAILED` before Composer starts.

The synchronization is not transactionally atomic across `composer.json`, `composer.lock`, and `vendor/`.

If an effectful Composer process has started and Composer or post-install verification fails, recovery material MUST be retained and the public failure MUST be `RECOVERY_REQUIRED` with bounded `recoveryReceiptId` and `causeCode` context.

No hidden Composer rollback operation is executed.

Successful synchronization MUST discard its recovery snapshot before returning clean success. Failure to discard required recovery state is not clean success.

## Fresh installed verification

After a successful effectful Composer operation, DependencySync MUST validate the resulting lock and manifest state and verify installed runtime policy in a fresh PHP process.

The verifier entrypoint is the Kernel package resource:

```text
bin/dependency-sync-verify.php
```

The parent process supplies a bounded canonical verification envelope over stdin. The fresh verifier loads the current consumer `vendor/autoload.php`, obtains installed Composer metadata, and invokes the public `ProjectDependencySync::verifyInstalled()` seam.

Verification MUST independently resolve one `ModulePlan` for every explicit target and compare it with the approved planning expectations.

A package that is physically installed because another target needs it, or because Composer pulled it transitively, MUST NOT become enabled in a target whose policy did not select it.

The verifier protocol is canonical and bounded. Timeout, malformed stdout, non-canonical field order/bytes, unsupported schema, or exit-code/protocol mismatch MUST fail with `VERIFICATION_EXECUTION_FAILED` or `VERIFICATION_INPUT_INVALID` as appropriate.

Installed metadata divergence, vendor incompleteness, plan mismatch, or protected-root identity change MUST fail with their dedicated stable codes.

After an effectful update, the parent process's already-loaded pre-update Kernel/Composer classes MUST NOT become authority for another synchronization. A subsequent synchronization requires a fresh consumer PHP process.

## Stable no-op

Re-running the same installation intent from a fresh process with unchanged `composer.json`, current lock, complete vendor state, and matching per-target installed plans MUST return a stable no-op.

A no-op may perform non-mutating lock-current validation and fresh verification, but it MUST NOT intentionally rewrite `composer.json`, `composer.lock`, or `vendor/`.

## Canonical multi-target example

Consumer configuration:

```php
return [
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

Explicit installation intent:

```text
web
worker
```

Current canonical policy resolves:

```text
web    -> micro      -> core.foundation, core.kernel
worker -> enterprise -> core.foundation, core.kernel, platform.worker

project union:
core.foundation, core.kernel, platform.worker

desired Composer package identities:
coretsia/core-foundation
coretsia/core-kernel
coretsia/platform-worker
```

The existing `coretsia/framework` root remains project-owned. Composer resolves one project graph. `ModulePlan(web)` does not enable `platform.worker` merely because the worker target caused that package to be installed.

The `worker` target participates only because the caller explicitly selected it; the presence of `presets.worker` does not add it to the installation set.

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

DependencySync MUST NOT import Kernel Artifact/Runtime implementation. Artifact/Runtime implementation MUST NOT import DependencySync.

Shared Foundation primitives remain domain-neutral. DependencySync owns its own lock path, error translation, Composer lifecycle, and recovery semantics.

Runtime Phase B MUST NOT depend on the installation catalog, Composer process runner, recovery state, or repository authoring tooling.

## Security and redaction

DependencySync MUST NOT execute through a shell fallback.

Project roots, manifest files, lock/recovery paths, and verifier scripts are validated against their filesystem boundaries. Unsafe symlinks are rejected on protected write/lock paths.

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
- a second persistent preset source or mutation of `config/app.php` by DependencySync;
- project-wide reinterpretation of target-local module exclusions or runtime conflicts;
- mutation of project-owned Coretsia roots merely because they are Coretsia packages;
- weakening protected third-party root identity in order to make a Coretsia solve succeed;
- shell fallback for Composer or verifier execution;
- hidden or guaranteed atomic rollback of Composer/vendor side effects;
- a final public CLI alias beyond the shipped `bin/dependency-sync.php` integration seam;
- direct DependencySync dependency on Kernel Artifact/Runtime implementation or the reverse.

## Change rules

A change to installation intent, catalog schema, marker schema, public error codes, protected-root policy, Composer effect policy, verifier protocol, or recovery lifecycle MUST update this SSoT and the corresponding tests in the same change.

Repository-only generator changes MUST keep the committed installation catalog rerun-no-diff.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Modes SSoT](./modes.md)
- [Modules and manifests SSoT](./modules-and-manifests.md)
- [ADR-0033: Application dependency synchronization from explicit installation intent](../adr/ADR-0033-application-dependency-sync-installation-intent.md)
- [Packaging](../architecture/PACKAGING.md)
- [Dependencies](../architecture/DEPENDENCIES.md)
- [Structure](../architecture/STRUCTURE.md)
