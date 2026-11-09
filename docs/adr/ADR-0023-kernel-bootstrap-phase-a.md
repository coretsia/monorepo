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

# ADR-0023: Kernel Bootstrap Phase A

```yaml
adrVersion: 1
status: pre-accepted
owner: core/kernel
```

## Context

Kernel needs a deterministic minimal bootstrap phase before full configuration compilation, module planning, runtime lifecycle orchestration, HTTP/CLI adapter execution, and unit-of-work handling.

This phase is called Bootstrap Phase A.

Bootstrap Phase A exists to resolve only the minimal boot input required by early runtime owners:

```text
applicationRoot
project-wide activeTargets (BootstrapSourceSnapshot only)
appTarget
appEnv
preset
debug
artifactsCacheDir
envSourcePolicy
appRoot
moduleOverrides: ResolvedModuleOverrides
immutable EnvRepositoryInterface snapshot
```

Project-wide source resolution does not select a runtime AppTarget. `BootstrapConfigResolver` subsequently constructs one target-local `BootstrapConfig` for an explicitly selected AppTarget, reusing the approved source snapshot.

Phase A must not become a full configuration merge phase.

`ConfigSourceLocationBuilder` owns package config-source location construction before Phase B. `ConfigKernel` Phase B owns orchestration of config loading from those prepared locations, application-root/app-target config loading, directives, deterministic merge, environment overlays, semantic validation, and explain output.

Phase A needs to support:

- explicit application target selection;
- deterministic app root derivation;
- optional bootstrap-only overrides;
- package fallback defaults;
- deterministic artifact cache directory resolution before artifact path lookup;
- dotenv loading based on already selected `appEnv`;
- deterministic dotenv/system env source precedence;
- immutable env repository snapshots;
- safe failure diagnostics.

The kernel package already owns the `kernel` config root.

The Phase A package defaults live under the existing `kernel` subtree:

```text
kernel.boot.default_env
kernel.boot.default_preset
kernel.boot.default_debug
kernel.boot.default_artifacts_cache_dir
kernel.env.source_policy.default_local
kernel.env.source_policy.default_production
kernel.env.dotenv.files
```

`kernel.boot.default_artifacts_cache_dir` is a package fallback only.

It is not the final artifact location source consumed by artifact services. The canonical resolved artifact cache directory is exposed through:

```text
BootstrapConfig::artifactsCacheDir()
```

These are config key namespaces under `kernel`, not separate config roots.

The kernel config defaults file returns the `kernel` subtree only and must not wrap values in a repeated root key:

```text
config/kernel.php => ['boot' => [...], 'env' => [...], 'uow' => [...]]
```

It must not return:

```text
['kernel' => [...]]
```

## Decision

Kernel Bootstrap Phase A is implemented as a small set of focused boot value objects, loaders, resolvers, and builders.

The public Bootstrap Phase A API surface is intentionally limited to:

```text
Coretsia\Kernel\Boot\AppTarget
Coretsia\Kernel\Boot\BootstrapConfig
Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy
Coretsia\Kernel\Boot\BootstrapInput
Coretsia\Kernel\Boot\Exception\BootstrapException
```

This list is limited to Bootstrap Phase A.

The separate artifact-only runtime entrypoint boundary may expose:

```text
Coretsia\Kernel\Boot\ArtifactRuntimeBooter
Coretsia\Kernel\Boot\ArtifactRuntimeInput
Coretsia\Kernel\Boot\Exception\ArtifactRuntimeBootException
```

Those symbols do not extend Bootstrap Phase A and do not make artifact-only runtime boot part of Phase A resolution.

Internal implementation helpers remain `@internal` and are not part of the public Kernel API.

The internal Phase A implementation helpers are:

```text
Coretsia\Kernel\Boot\BootstrapArtifactsCacheDir
Coretsia\Kernel\Boot\BootstrapConfigResolver
Coretsia\Kernel\Boot\BootstrapSourceResolver
Coretsia\Kernel\Boot\BootstrapSourceSnapshot
Coretsia\Kernel\Boot\BootstrapOverridesLoader
Coretsia\Kernel\Boot\DotenvLoader
Coretsia\Kernel\Boot\EnvRepositoryBuilder
Coretsia\Kernel\Boot\ArrayEnvRepository
```

## Decision 1: Phase A is a minimal boot-input phase

Bootstrap Phase A resolves only:

```text
applicationRoot
project-wide activeTargets (BootstrapSourceSnapshot only)
appTarget
appEnv
preset
debug
artifactsCacheDir
envSourcePolicy
appRoot
moduleOverrides: ResolvedModuleOverrides
immutable EnvRepositoryInterface snapshot
```

Project-wide source resolution does not select a runtime AppTarget. `BootstrapConfigResolver` subsequently constructs one target-local `BootstrapConfig` for an explicitly selected AppTarget, reusing the approved source snapshot.

Phase A must not read full application configuration files.

Phase A must not read:

```text
config/roots.php
config/<root>.php
config/environments/**
apps/<appTarget>/config/**
```

Phase A may read only one bootstrap-only application config input:

```text
config/app.php
```

This file is a Bootstrap Phase A input file only.

It is not a reserved config root.

It must not participate in ConfigKernel Phase B merge.

Phase A must not scan:

```text
apps/*
```

The application target must be explicit input.

## Decision 2: AppTarget is explicit and canonical

`Coretsia\Kernel\Boot\AppTarget` is the canonical application target enum.

The allowed target tokens are:

```text
web
api
console
worker
```

Invalid targets fail with:

```text
BootstrapException::REASON_INVALID_APP_TARGET
```

Invalid target diagnostics must not include the rejected raw input.

`appRoot` is derived deterministically as:

```text
applicationRoot/apps/<appTarget>
```

Phase A must not require this directory to exist.

Phase A must not infer the application target from filesystem state.

Phase A must not choose an application by scanning sibling app directories.

## Installation-planning boundary

Bootstrap Phase A separates project-wide source resolution from target-local runtime configuration.

`BootstrapSourceResolver::resolveApplicationRoot()` resolves one immutable `BootstrapSourceSnapshot` without creating an `AppTarget` or a synthetic `BootstrapInput`. The snapshot contains optional `activeTargets`, normalized bootstrap source overrides, frozen appEnv and source policy, safe provenance, and one immutable environment repository.

DependencySync derives its effective `ProjectApplicationSet` from the approved snapshot:

- configured non-empty `activeTargets` is authoritative;
- absent `activeTargets` uses the DependencySync-owned fallback `['web']`;
- target-specific `presets` and `moduleOverrides` never add application membership implicitly;
- application directories do not determine installed application membership.

The same snapshot is reused to resolve independent target-local `BootstrapConfig` values for all selected applications. `BootstrapConfigResolver` never reads the source layers again when an approved snapshot is supplied.

`ProjectInstallationIntent::fixedPresetByTarget()` is allowed for temporary read-only `plan` and `review` operations. Its target-specific values use the existing explicit `BootstrapInput::preset()` precedence without changing persistent configuration.

Effectful synchronization uses configuration-owned application membership and persistent effective presets. It rejects a non-empty temporary fixed-preset map and rejects an explicit application set that disagrees with the current configured application set.

The canonical Skeleton entrypoints are:

```text
php coretsia plan
php coretsia review
php coretsia apply
```

They use the Skeleton application root and do not accept DependencySync `--target`. A temporary `--preset=<target>=<preset>` is supported only for plan and review, never apply.

The initial Composer bootstrap requires the Framework's Kernel-only baseline, not an already installed `platform.cli` runtime. DependencySync planning, review, synchronization, Composer reconciliation, and fresh-process verification remain Kernel-owned.

Bootstrap Phase A is non-effectful. It does not modify user configuration, execute Composer, read the installation catalog, or create `ModulePlan`.

## Decision 3: BootstrapInput is entrypoint-owned input only

`Coretsia\Kernel\Boot\BootstrapInput` is the immutable entrypoint-owned input object.

It carries only:

```text
applicationRoot
appTarget
appEnv?
preset?
debug?
envSourcePolicy?
artifactsCacheDir?
```

`BootstrapInput` must not:

- read filesystem state;
- infer app target;
- inspect `apps/*`;
- read process env;
- parse dotenv files;
- contain raw env values.

Optional values in `BootstrapInput` have the highest Phase A resolution precedence.

`BootstrapInput::artifactsCacheDir()` is the optional explicit entrypoint-owned artifact cache directory override.

When present, it has higher precedence than both:

```text
config/app.php artifactsCacheDir
kernel.boot.default_artifacts_cache_dir
```

The value is `applicationRoot`-relative and must satisfy the canonical portable artifact cache directory policy.

## Decision 4: BootstrapConfig is a resolved immutable VO only

`Coretsia\Kernel\Boot\BootstrapConfig` is a resolved immutable value object.

It represents the selected Phase A config after resolution has already happened.

It must contain:

```text
appEnv
preset
debug
artifactsCacheDir
envSourcePolicy
appTarget
applicationRoot
appRoot
moduleOverrides: ResolvedModuleOverrides
```

`BootstrapConfig` must not:

- resolve optional values from `BootstrapInput`;
- read package defaults;
- read `config/app.php`;
- read dotenv files;
- read system env;
- scan `apps/*`;
- require `appRoot` to exist;
- expose `fromInput()`;
- expose any method that performs Phase A resolution.

The object derives only `appRoot` from already resolved `applicationRoot` and `appTarget`.

`BootstrapConfig::artifactsCacheDir()` contains the already resolved and validated artifact cache directory.

`BootstrapConfig` does not derive this value from Phase B config and does not read compiled runtime config.

## Decision 5: BootstrapSourceResolver owns source resolution; BootstrapConfigResolver owns target-local configuration

`Coretsia\Kernel\Boot\BootstrapSourceResolver` is the canonical internal owner of Bootstrap Phase A source orchestration.

It is independent of `BootstrapConfigResolver` and does not construct a target-local `BootstrapConfig`.

For one resolution attempt, it:

1. validates the application-root identity;
2. captures one process environment snapshot;
3. reads and validates the optional bootstrap-only `config/app.php`;
4. reads and validates the base dotenv layers;
5. selects and freezes appEnv;
6. selects the effective `BootstrapEnvSourcePolicy` exactly once;
7. loads and validates the environment-specific dotenv layers;
8. merges normalized bootstrap logical keys in canonical source order;
9. applies permitted process bootstrap overrides only under `allow_system`;
10. builds one immutable `EnvRepositoryInterface` from the already captured dotenv and system values;
11. returns one immutable `BootstrapSourceSnapshot`.

`BootstrapSourceResolver` MUST NOT create `BootstrapConfig`, `ConfigKernel`, `ModulePlan`, `ProjectApplicationSet`, or Composer installation state.

### appEnv freeze and source policy

The initial appEnv precedence is:

```text
explicit BootstrapInput::appEnv()
explicit AllowSystem process CORETSIA_BOOT_APP_ENV
.env.local CORETSIA_BOOT_APP_ENV
.env CORETSIA_BOOT_APP_ENV
config/app.php appEnv
kernel.boot.default_env
```

Process environment can select appEnv only when `BootstrapEnvSourcePolicy::AllowSystem` is explicitly requested.

Explicit appEnv wins over a different process appEnv without a false mismatch.

Environment-specific dotenv layers do not reselect appEnv. If they contain `CORETSIA_BOOT_APP_ENV`, its value MUST match the frozen environment exactly.

After appEnv selection, the source policy is determined from explicit `BootstrapInput::envSourcePolicy()` or the canonical Kernel defaults.

`prod` and `production` default to `allow_system`. All other environments, including `staging`, default to `strict_dotenv`.

The production-like default MUST NOT trigger another appEnv selection from process environment.

### Normalized source merge

For ordinary bootstrap source overrides, precedence from strongest to weakest is:

```text
permitted process environment
.env.<appEnv>.local
.env.<appEnv>
.env.local
.env
config/app.php
Kernel defaults
```

Each permitted source layer MUST be validated before merging.

`activeTargets` is replaced as one complete non-empty list.

`presets[target]` is merged per target. An empty map is a no-op.

`moduleOverrides[target].include` and `moduleOverrides[target].exclude` are merged independently. An absent field preserves the lower layer; an explicit empty list clears only the corresponding field.

The canonical structured bootstrap environment inputs are:

```dotenv
CORETSIA_BOOT_ACTIVE_TARGETS=["web","worker"]
CORETSIA_BOOT_PRESETS={"web":"micro","worker":"hybrid"}
CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"web":["platform.logging"]}
CORETSIA_BOOT_MODULE_OVERRIDES_EXCLUDE={"web":[]}
```

`CORETSIA_BOOT_ACTIVE_TARGETS` is a non-empty JSON list. `CORETSIA_BOOT_PRESETS` is a JSON object mapping AppTarget to preset string; `CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE` and `CORETSIA_BOOT_MODULE_OVERRIDES_EXCLUDE` are separate JSON objects mapping AppTarget to ModuleId string lists. JSON object keys MUST be unique. An empty map `{}` is a no-op, while `{"web":[]}` explicitly clears only the named target's corresponding include or exclude field. The complete reserved-key allowlist and input limits are defined in `docs/ssot/config-and-env.md`.

The final normalized shape is completed only after source merging. The snapshot stores safe source provenance for each logical key and one immutable environment repository.

### Target-local BootstrapConfig

`Coretsia\Kernel\Boot\BootstrapConfigResolver` consumes the approved `BootstrapSourceSnapshot` and the explicit `BootstrapInput` for one AppTarget.

Its canonical preset precedence is:

```text
explicit BootstrapInput::preset()
merged presets[appTarget]
merged global preset
kernel.boot.default_preset
```

Explicit `BootstrapInput::debug()` and `BootstrapInput::artifactsCacheDir()` similarly take precedence over merged source values and Kernel defaults.

The canonical resolved artifact directory remains:

```text
BootstrapConfig::artifactsCacheDir()
```

Artifact path consumers MUST NOT re-resolve it through Phase B or compiled configuration.

`BootstrapConfigResolver` validates and normalizes the selected target's module overrides, including include/exclude conflicts. Module graph policy and dependency closure remain the responsibility of existing Kernel orchestration.

The resolver MUST NOT resolve preset files, read Composer metadata, build the environment repository, parse dotenv, discover applications, or execute lifecycle behavior.

A pre-resolved snapshot MUST be bound to the same `applicationRoot`. Conflicting explicit appEnv or envSourcePolicy MUST fail without attempting another source resolution.

The same snapshot MAY be reused for different explicit AppTargets of the same project.

The snapshot MUST NOT infer installation membership, determine runtime AppTarget, or construct an independent installation identity.

## Decision 6: BootstrapOverridesLoader reads only config/app.php

`Coretsia\Kernel\Boot\BootstrapOverridesLoader` reads only:

```text
config/app.php
```

Missing `config/app.php` means no overrides.

Existing but invalid `config/app.php` fails deterministically.

The file must return an array.

Allowed override keys are exactly:

```text
appEnv
preset
presets
activeTargets
debug
artifactsCacheDir
moduleOverrides
```

Unknown top-level keys fail with:

```text
BootstrapException::REASON_OVERRIDES_INVALID
```

Allowed values are:

```text
appEnv: non-empty safe string
preset: non-empty safe string
presets: string-keyed map of appTarget => non-empty safe preset string
activeTargets: non-empty list of unique canonical AppTarget strings
debug: bool
artifactsCacheDir: portable applicationRoot-relative artifact output directory
moduleOverrides: partial map<appTarget, {include?: list<string>, exclude?: list<string>}>
```

`preset` is a global fallback preset override.

`presets` is a bootstrap-only per-app preset override map.

An invalid `artifactsCacheDir` value loaded from `config/app.php` fails with:

```text
BootstrapException::REASON_OVERRIDES_INVALID
```

The dedicated:

```text
BootstrapException::REASON_ARTIFACTS_CACHE_DIR_INVALID
```

is used for invalid explicit `BootstrapInput` and direct `BootstrapConfig` artifact cache directory values.

An invalid package fallback `kernel.boot.default_artifacts_cache_dir` fails internally and deterministically with:

```text
bootstrap-config-default-artifacts-cache-dir-invalid
```

Example:

```php
return [
    'preset' => 'hybrid',
    'artifactsCacheDir' => 'var/artifacts_cache',
    'presets' => [
        'api' => 'micro',
        'web' => 'express',
        'console' => 'hybrid',
        'worker' => 'enterprise',
    ],
    'activeTargets' => ['web', 'worker'],
    'moduleOverrides' => [
        'web' => [
            'include' => ['integrations.foo'],
            'exclude' => ['platform.worker'],
        ],
    ],
];
```

`artifactsCacheDir` is a global Bootstrap Phase A override for the selected entrypoint.

It does not select an app target and is not part of ConfigKernel Phase B merge.

`presets` may be partial.

Empty `presets` behaves as absent.

`presets` must be a string-keyed map.

`presets` must not be a list or sequence.

`presets` keys must be valid app targets:

```text
web
api
console
worker
```

Unknown `presets` app target keys fail with:

```text
BootstrapException::REASON_OVERRIDES_INVALID
```

`presets` values must be non-empty safe strings.

`presets` must not:

- select app target;
- infer app target;
- modify app target;
- scan `apps/*`;
- determine effective module-selection roots; the separately validated selected-target `moduleOverrides` value is handled by `BootstrapConfigResolver`.

The loader must not read:

```text
config/modules.php
config/roots.php
config/<root>.php
config/environments/**
apps/<appTarget>/config/**
```

Phase A validates selected-app-target include/exclude override input but does not load preset policy or resolve effective roots and dependency closure.

`BootstrapConfigResolver` validates selected-app-target `moduleOverrides` in Phase A. For installed runtime resolution, the compile-host orchestrator combines them with namespace-owned preset policy into `ModuleSelection`, then resolves that selection against installed module metadata.

For DependencySync installation planning, one project-wide `BootstrapSourceSnapshot` is reused for independent target-local configurations. Each selected target receives its own `ModuleSelection`, which is resolved against the versioned installation catalog before Composer. Bootstrap Phase A itself does not know or read that catalog.

Override values must not appear in exception messages.

### Bootstrap app-target module overrides

`config/app.php` MAY contain a partial `moduleOverrides` map keyed only by `api`, `console`, `web`, and `worker`, each with optional `include` and `exclude` lists of canonical ModuleIds.

`BootstrapOverridesLoader` validates the shape and canonical ModuleId syntax of every provided target, including inactive targets. It rejects duplicate, noncanonical, and invalid ModuleIds before source merging. Missing `include` or `exclude` fields remain absent during the merge; they are normalized to empty lists only after source merging finishes.

`BootstrapConfigResolver` validates selected-target include/exclude conflicts, normalizes the lists through `ModuleIdSetNormalizer`, and constructs an immutable `ResolvedModuleOverrides`. The selected target's absence produces empty include and exclude lists.

Phase A MUST NOT load preset files, read `ModuleManifest`, construct `ModuleSelection`, or resolve dependency closure. Those operations remain with their existing Kernel owners. Invalid source configuration fails through bounded `BootstrapException` diagnostics without exposing raw values.

## Decision 7: BootstrapEnvSourcePolicy is source precedence only

`Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy` is a Kernel-owned Bootstrap Phase A env source precedence enum.

Allowed values are:

```text
strict_dotenv
allow_system
```

This enum must not be confused with:

```text
Coretsia\Contracts\Env\EnvPolicy
```

`Coretsia\Contracts\Env\EnvPolicy` remains a missing-value policy only:

```text
required
optional
defaulted
```

It does not control dotenv/system env source precedence.

## Decision 8: DotenvLoader owns ordered dotenv loading only

`Coretsia\Kernel\Boot\DotenvLoader` is the canonical dotenv parser.

It exposes ordered per-file dotenv layers without flattening away source provenance. `BootstrapSourceResolver` uses this capability to load base layers before appEnv selection and environment-specific layers after appEnv is frozen.

The canonical templates are supplied by:

```text
kernel.env.dotenv.files
```

Their default order is:

```text
.env
.env.local
.env.<env>
.env.<env>.local
```

`DotenvLoader::loadBaseLayers()` loads the environment-independent layers.

`DotenvLoader::loadEnvironmentLayers()` loads the environment-specific layers using frozen appEnv and reuses preloaded layers where applicable.

`DotenvLoader::flattenLayers()` preserves the existing ordinary dotenv snapshot behavior after the bootstrap-specific source merge has completed.

The existing `load(BootstrapConfig, kernelConfig)` entrypoint remains available for existing consumers.

`DotenvLoader` MUST NOT independently select appEnv, apply process source precedence, merge bootstrap JSON maps, resolve presets, load application configuration, or construct Composer installation state.

Configured dotenv names remain subject to existing safe filename validation. Invalid or unreadable files fail through safe `BootstrapException` mapping without raw dotenv values or absolute filesystem paths.

## Decision 9: EnvRepositoryBuilder owns immutable environment snapshot construction

`Coretsia\Kernel\Boot\EnvRepositoryBuilder` constructs the canonical immutable `EnvRepositoryInterface`.

For Bootstrap Phase A source orchestration, it consumes already loaded dotenv values and safe sources, one captured system environment snapshot, and the frozen `BootstrapEnvSourcePolicy`.

The internal snapshot-construction entrypoint MUST NOT require a target-local `BootstrapConfig`, reload dotenv files, capture a second independent process environment snapshot, reselect appEnv, or merge bootstrap presets and module overrides.

The existing `build(BootstrapConfig, kernelConfig)` entrypoint remains available for compatible existing consumers and delegates to the same scalar source-precedence behavior.

`strict_dotenv` excludes process values and process fallback from the resulting repository.

`allow_system` gives present process values precedence over dotenv values and preserves dotenv values where the corresponding process key is absent.

Presence and empty strings are distinct. The immutable repository retains all permitted environment keys needed by ordinary Phase B package overlays, not only `CORETSIA_BOOT_*`.

One source resolution attempt produces one immutable repository, which is retained by `BootstrapSourceSnapshot` and reused by all its target-local consumers.

The builder MUST NOT implement application membership, preset selection, config validation, module graph policy, Composer reconciliation, or Phase B environment projection.

## Decision 10: ArrayEnvRepository is the internal immutable repository

`Coretsia\Kernel\Boot\ArrayEnvRepository` is the internal immutable implementation of:

```text
Coretsia\Contracts\Env\EnvRepositoryInterface
```

It stores:

```text
array<string,string>
```

as a normalized snapshot.

It may store safe `ConfigValueSource` metadata per env key.

It must preserve present empty string as present.

`has()` must return true for present empty string.

`get()` returns:

```text
EnvValue::present($value)
```

when a value is present.

`get()` returns:

```text
EnvValue::missing()
```

when the key is absent.

`all()` returns a copy of present raw env values for runtime owners only.

`sourceOf()` returns safe source metadata only.

`ArrayEnvRepository` is internal and must not be added to the public Kernel API.

## Decision 11: strict_dotenv precedence

When `envSourcePolicy` is:

```text
strict_dotenv
```

then:

- dotenv values win;
- system env values are ignored;
- system env fallback is forbidden;
- missing dotenv keys remain missing in the repository;
- present empty dotenv values remain present.

`strict_dotenv` still permits system env to be snapshotted internally before precedence application, but the snapshot must not affect repository values.

## Decision 12: allow_system precedence

When `envSourcePolicy` is:

```text
allow_system
```

then:

- system env values win;
- dotenv values are used only when the same key is absent from system env;
- system-only keys are present;
- present empty system values remain present;
- missing keys remain missing.

System env names that are unsafe for source metadata are ignored before `ConfigValueSource` metadata is created.

System source metadata must be redacted and must not contain raw env values.

## Decision 13: BootstrapException owns safe Phase A failure messages

`Coretsia\Kernel\Boot\Exception\BootstrapException` is the canonical Bootstrap Phase A exception.

Its error code is:

```text
CORETSIA_BOOTSTRAP_FAILED
```

Stable reason tokens are:

```text
bootstrap-invalid-app-target
bootstrap-invalid-application-root
bootstrap-artifacts-cache-dir-invalid
bootstrap-dotenv-file-invalid
bootstrap-dotenv-load-failed
bootstrap-overrides-invalid
bootstrap-env-source-policy-invalid
```

The exception message contains only:

```text
ERROR_CODE: reason-token
```

The message must not contain:

- dotenv values;
- raw env values;
- raw override arrays;
- raw PHP warnings;
- OS error messages;
- absolute paths;
- tokens;
- cookies;
- Authorization values;
- secrets;
- stack traces;
- environment-specific data.

## Decision 14: Provider wiring registers Phase A services as factories only

`KernelServiceFactory` may provide deterministic constructors/factories for Bootstrap Phase A services.

`KernelServiceProvider` may register Phase A boot services as DI factories:

```text
BootstrapOverridesLoader
BootstrapConfigResolver
BootstrapSourceResolver
DotenvLoader
EnvRepositoryBuilder
```

`BootstrapSourceSnapshot` is a resolution-local immutable value, not a container service. Service registration MUST NOT create snapshots, read bootstrap source files, or execute environment resolution.

Provider registration must not execute Bootstrap Phase A.

Provider registration must not:

- resolve `BootstrapInput`;
- read `config/app.php`;
- parse dotenv files;
- snapshot system env;
- build `EnvRepositoryInterface`;
- start UnitOfWork;
- trigger reset orchestration.

The factory must not keep mutable runtime state.

## Decision 15: No public Phase A orchestration facade or aggregate result are introduced

Kernel does not introduce public:

```text
Bootstrapper
BootstrapResult
```

in this ADR.

This is intentional.

`BootstrapSourceResolver` is the canonical internal Phase A source orchestrator. It creates one `BootstrapSourceSnapshot` for the application root, frozen environment, source policy, and resolution attempt.

`BootstrapConfigResolver` is the internal target-local configuration resolver. It accepts explicit `BootstrapInput` and the approved source snapshot and returns one `BootstrapConfig`. It does not load bootstrap sources or construct the environment repository.

Both resolvers are internal. Neither introduces a public `Bootstrapper` or public `BootstrapResult`.

The existing public values remain narrowly scoped:

- `BootstrapInput` carries entrypoint-owned target-local inputs;
- `BootstrapConfig` carries resolved target-local configuration;
- `EnvRepositoryInterface` provides runtime access to the immutable environment snapshot.

`BootstrapSourceSnapshot` is an internal value object, not a public aggregate result, runtime singleton, or verification envelope.

The canonical Kernel source and compile-host owners reuse these values without creating a second ConfigKernel, preset registry, or environment-loading pipeline.

`KernelArtifactOperation` remains the canonical internal compile/verify preparation owner.

`platform/cli` invokes Kernel operations only through `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`.

For CLI-owned Kernel operations, `platform/cli` MUST NOT construct the compile-host pipeline or call `KernelArtifactOperation` directly.

The canonical CLI handoff is:

```text
platform/cli
  -> Coretsia\Kernel\Ops\KernelOpsHostBooter
  -> source-operations ContainerInterface
  -> Coretsia\Contracts\Kernel\Ops\KernelOpsInterface
  -> internal Coretsia\Kernel\Ops\KernelOpsFacade
```

Operation routing then remains Kernel-owned:

```text
validateConfig() / debugConfig() / hashConfig() / debugModules()
  -> existing Kernel orchestration services

compileConfig() / verifyCache()
  -> internal KernelArtifactOperation
  -> BootstrapSourceResolver
  -> one BootstrapSourceSnapshot / EnvRepositoryInterface
  -> BootstrapConfigResolver (same snapshot)
  -> ModuleResolutionOrchestrator::resolve()
  -> ConfigSourceLocationBuilder
  -> ArtifactCompiler / CacheVerifier
```

`KernelArtifactOperation` remains an internal Kernel compile-host operation and the canonical `BootstrapInput` → compile/verify input-preparation owner.

`KernelArtifactOperation`, `ArtifactCompiler`, and `CacheVerifier` remain internal implementation services. The Kernel Ops public boundary does not promote them to package public API.

`KernelOpsFacade` is likewise an internal implementation behind `KernelOpsInterface`; the public Kernel source-host boot API is `KernelOpsHostBooter` plus `KernelOpsHostInput`.

## Decision 16: Phase A and Kernel compile-host services are not runtime graph definitions

`KernelServiceProvider` has two distinct wiring responsibilities:

```text
source compile-host registration
canonical runtime definition contribution
```

Source compile-host registration remains in:

```php
KernelServiceProvider::register(...)
```

Canonical Kernel runtime contribution is owned by:

```php
KernelServiceProvider::define(...)
```

Bootstrap Phase A services are compile-host services.

They may be registered as source-container factories so bootstrap, configuration compilation, module planning, artifact compilation, and verification can execute.

They must not be contributed to the canonical runtime definition graph.

The explicit law is:

```text
Kernel compile-host services are not part of the compiled runtime
container definition graph.
```

The exclusion applies to:

```text
BootstrapOverridesLoader
BootstrapConfigResolver
BootstrapSourceResolver
DotenvLoader
EnvRepositoryBuilder
Composer metadata readers
ModuleResolutionOrchestrator
ModuleSelectionFactory
PresetNamespaceResolver
ModePresetLoaderFactory
CanonicalPresetSource
CustomPresetSource
ModuleSelection
ResolvedModuleOverrides
ModuleGraphResolver
ModulePlanResolver
ComposerPackageInstallPathResolver
ConfigSourceLocationBuilder
ConfigKernel
artifact builders
ArtifactCompiler
fingerprint services
CacheVerifier
KernelArtifactOperation
artifact readers and writers
ContainerCompiler
```

The same canonical source-host service wiring MUST be available when constructing source and compiled artifacts. `BootstrapSourceResolver` and its resolution-local snapshots MUST NOT be contributed to the compiled runtime definition graph or registered as runtime singleton state.

`ConfigSourceSet` is not a compile-host service.

It is one immutable per-operation value produced by `ConfigSourceLocationBuilder` and consumed during one compile or verify operation. It MUST NOT be registered as a DI service or contributed to the runtime definition graph.

These services may construct, compile, write, read, or verify runtime artifacts.

They are not runtime services consumed by the compiled application container.

`KernelServiceProvider::define()` is limited to Kernel runtime definitions such as:

```text
RuntimeDriverResolver
HookInvoker
KernelRuntime
KernelRuntimeInterface alias
```

Phase A or Phase B services must not be re-read by Kernel runtime factories.

The compile-host-only boundary includes `ModuleResolutionOrchestrator`, `ModuleSelectionFactory`, `PresetNamespaceResolver`, `CanonicalPresetSource`, `CustomPresetSource`, `ModuleSelection`, `ResolvedModuleOverrides`, and pure `ModulePlanResolver`. These types never become compiled runtime definitions or runtime seeds. Only `ModulePlan` is hydrated as the approved immutable module-graph runtime seed from the validated `module-manifest@1` payload, whose exact keys are `app`, `enabled`, `excluded`, `modules`, `schemaVersion`, and `topologicalOrder`; artifact identity and both schema versions remain `1`. Runtime boot never invokes preset loading or Composer discovery.

### ModulePlan-derived module-manifest payload

The existing `module-manifest@1` artifact has envelope `_meta.schemaVersion = 1` and an exact `ModulePlan`-derived `payload.schemaVersion = 1`. Its only payload keys are:

```text
app
enabled
excluded
modules
schemaVersion
topologicalOrder
```

`enabled` and `excluded` are disjoint unique sorted module-id lists; `topologicalOrder` preserves dependency-first order, not alphabetical order. `modules` contains exactly enabled module entries. `ModulePlanArtifactHydrator` validates and restores this payload as the immutable runtime `ModulePlan`; the contracts `ModuleManifest` remains a distinct compile-host installed-discovery snapshot. No compile-host selection or namespace-source object enters the payload or runtime seeds. `config@1`, `container@1`, and `artifact-generation@1` retain their identities and schema versions.

## Decision 17: Artifact-only runtime boot is separate from Bootstrap Phase A

Artifact-only runtime boot is a separate entrypoint boundary.

It does not re-run Bootstrap Phase A and does not consume `BootstrapInput` or `BootstrapConfig` as runtime container seeds.

The public entrypoint-owned input is:

```text
Coretsia\Kernel\Boot\ArtifactRuntimeInput
```

`ArtifactRuntimeInput` carries only:

```text
applicationRoot
artifactRoot
```

`artifactRoot` is the final Kernel artifact root containing:

```text
generations/
current
generation.lock
```

It does not carry individual `module-manifest.php`, `config.php`, `container.php`, or `generation-manifest.php` paths.

The canonical artifact-only boot flow is:

```text
ArtifactRuntimeInput
  -> ArtifactGenerationLocator
  -> current
  -> one validated ArtifactGeneration
  -> exact read of all four generation files
  -> generation manifest, byte-length, SHA-256, schema, and fingerprint validation
  -> ArrayConfigRepository
  -> ModulePlan
  -> RuntimePathContext
  -> RuntimeContainerSeedSet
  -> CompiledContainerFactory::buildFromEnvelope(...)
  -> ContainerInterface
```

`ArtifactRuntimeBooter` owns current-generation selection for artifact-only runtime boot.

It MUST:

1. locate `current` through `ArtifactGenerationLocator`;
2. require one valid selected generation;
3. read the exact bytes and envelopes of all four generation files;
4. validate `artifact-generation@1`;
5. require every artifact envelope fingerprint to equal the selected generation id;
6. hydrate `ConfigRepositoryInterface` from `config@1`;
7. hydrate `ModulePlan` from `module-manifest@1`;
8. create `RuntimePathContext` from `ArtifactRuntimeInput`;
9. create the exact `RuntimeContainerSeedSet`;
10. build the runtime container from the already-read `container@1` envelope.

The exact entrypoint-owned runtime seeds are:

```text
Coretsia\Contracts\Config\ConfigRepositoryInterface
Coretsia\Kernel\Module\ModulePlan
Coretsia\Kernel\Runtime\RuntimePathContext
```

The explicit law is:

```text
Runtime seeds are entrypoint-owned runtime objects.
They are not provider definitions, artifact payloads, or fingerprint inputs.
```

Artifact-only runtime boot MUST NOT:

- resolve `BootstrapInput`;
- resolve `BootstrapConfig`;
- run Bootstrap Phase A;
- run ConfigKernel Phase B;
- read source config files;
- discover modules through Composer metadata;
- resolve mode presets;
- execute source providers;
- compile a replacement container graph;
- calculate fingerprints;
- write or repair artifacts;
- accept caller-selected individual artifact paths.

`ArtifactRuntimeInput` and the resulting `RuntimePathContext` may carry normalized absolute runtime roots.

Those paths MUST NOT be copied into:

- canonical runtime definitions;
- `DefinitionGraph`;
- generated artifact payloads;
- fingerprint input.

The caller resolves only the artifact root. Generation selection and generation-specific path resolution remain internal to the artifact-only runtime boundary.

## Consequences

### Positive

Bootstrap Phase A remains minimal and deterministic.

Application target selection is explicit.

`appRoot` derivation is stable and does not depend on filesystem scanning.

Applications can boot from package defaults without application-owned config files.

Artifact cache location is resolved before artifact lookup without depending on ConfigKernel Phase B or compiled runtime config.

Applications may relocate Kernel artifacts within the application root through a bootstrap-only override while preserving deterministic precedence.

Per-app preset selection and target-specific module overrides are resolved from the merged bootstrap sources (`config/app.php`, permitted dotenv layers, and permitted process environment values). Phase A resolves the selected preset name and validates immutable `ResolvedModuleOverrides` without traversing the installed module graph.

The selected app target remains explicit even when `presets` contains entries for multiple app targets.

Preset selection remains deterministic:

```text
BootstrapInput::preset()
merged presets[appTarget]
merged global preset
kernel.boot.default_preset
```

Phase A still does not load mode preset files or validate preset existence.

`BootstrapConfig` remains a clean resolved VO.

`BootstrapSourceResolver` owns project-wide Phase A source resolution and the immutable environment snapshot. `BootstrapConfigResolver` owns target-local `BootstrapConfig` resolution from that snapshot.

`EnvRepositoryBuilder` owns env snapshot construction only.

Dotenv template expansion cannot depend on reading `.env.<env>` before the final env is known.

`strict_dotenv` and `allow_system` have clear, testable precedence rules.

Present empty string remains distinct from missing env values.

Safe `ConfigValueSource` metadata can explain source origin without leaking raw env values.

Phase A boot code is isolated from runtime lifecycle/reset infrastructure.

Artifact-only runtime boot does not re-run Bootstrap Phase A or ConfigKernel Phase B.

`ConfigRepositoryInterface`, `ModulePlan`, and `RuntimePathContext` can be restored as runtime objects without source-provider execution.

Absolute runtime path state remains outside generated artifacts, compiled graphs, and fingerprint input.

The public API remains small.

### Negative / trade-offs

There is no one-call public bootstrap facade.

Entrypoints that own narrow Phase A composition use the canonical `BootstrapSourceResolver` and `BootstrapConfigResolver` through existing Kernel wiring, reusing one approved snapshot and environment repository per operation attempt. `platform/cli` MUST instead resolve `KernelOpsInterface` through the dedicated Kernel source-operations host and MUST NOT call `KernelArtifactOperation` directly. Internal `KernelOpsFacade` preserves `KernelArtifactOperation` as the canonical compile/verify preparation owner for `compileConfig()` and `verifyCache()`.

`staging` defaults to `strict_dotenv`, so deployments that want system env precedence for staging must pass explicit `BootstrapEnvSourcePolicy::AllowSystem`.

`BootstrapOverridesLoader` supports only `appEnv`, `activeTargets`, `preset`, `presets`, `debug`, `artifactsCacheDir`, and `moduleOverrides`. The separate bootstrap environment allowlist and strict JSON-map validation are owned by `BootstrapSourceResolver`.

Other bootstrap inputs require explicit entrypoint input or an explicit extension of the Bootstrap Phase A contract.

Artifact cache relocation is limited to a portable, bounded, `applicationRoot`-relative generated-output directory.

Absolute paths and relocation into source, config, public, dependency, or repository-owned roots are intentionally unsupported.

`presets` can select different preset names per app target, but it does not validate that those preset files exist. Missing selected preset files are reported later by the namespace-bound loader invoked by `ModuleResolutionOrchestrator`.

Phase A does not validate the existence of `appRoot`, so later phases must own application root existence checks when needed.

## Rejected alternatives

### Alternative 1: Let BootstrapConfig resolve from BootstrapInput

Rejected.

`BootstrapConfig` must be a resolved immutable VO only.

Putting resolution logic into `BootstrapConfig` would mix data representation with IO-aware/default-aware resolution policy and would make it harder to keep Phase A boundaries testable.

### Alternative 2: Read full application config during Phase A

Rejected.

`ConfigSourceLocationBuilder` owns package config-source location construction, while `ConfigKernel` Phase B owns config loading and merge orchestration from already-prepared source locations.

Reading full application config during Phase A would create ordering drift, duplicate merge behavior, and risk reading application config before the minimal boot boundary is stable.

### Alternative 3: Infer app target by scanning apps/*

Rejected.

App target must be explicit.

Scanning app directories would make boot behavior depend on filesystem shape, sibling directories, and local development artifacts.

The accepted design derives:

```text
appRoot = applicationRoot/apps/<appTarget>
```

from explicit input only.

### Alternative 4: Read config/modules.php during Phase A

Rejected.

Preset-policy combination with validated include/exclude overrides is owned by `ModuleSelectionFactory`, and installed dependency-graph resolution is owned by `ModuleGraphResolver`.

Phase A reads only bootstrap-only `config/app.php`.

`modules.php` must not be read here.

### Alternative 5: Use Coretsia\Contracts\Env\EnvPolicy for source precedence

Rejected.

`Coretsia\Contracts\Env\EnvPolicy` is a missing-value policy only.

It describes:

```text
required
optional
defaulted
```

It must not control dotenv/system source precedence.

The accepted design uses Kernel-owned `BootstrapEnvSourcePolicy`.

### Alternative 6: Introduce public Bootstrapper now

Rejected.

`BootstrapConfigResolver` already exists as an internal resolver, but it is not a public bootstrap orchestration facade.

A public `Bootstrapper` would compose multiple Phase A steps and would likely return both resolved config and env repository state.

No public platform entrypoint contract defined by this ADR requires that one-call facade.

Introducing a public one-call facade without a concrete platform entrypoint requirement would freeze orchestration prematurely and could grow into a hidden lifecycle/config merge owner.

The accepted design keeps orchestration explicit and keeps `BootstrapConfigResolver` narrow and internal.

### Alternative 7: Introduce public BootstrapResult now

Rejected.

The canonical result objects already exist:

```text
BootstrapConfig
EnvRepositoryInterface
```

`BootstrapConfigResolver` returns only `BootstrapConfig`.

`EnvRepositoryBuilder` returns `EnvRepositoryInterface`.

A public `BootstrapResult` would aggregate those objects into a second public result shape, which would create another object to version, preserve, and keep in sync.

A result wrapper may be introduced only if concrete public API pressure from platform entrypoints justifies an additional public result shape.

### Alternative 8: Treat staging as production-like by default

Rejected.

Production-like env names are exactly:

```text
prod
production
```

`staging` remains default-strict.

Deployments that want system env precedence for staging must pass explicit `BootstrapEnvSourcePolicy::AllowSystem`.

## Security and redaction

Bootstrap Phase A must prefer safe deterministic failure over leaking raw input.

Bootstrap exceptions must not expose:

- raw dotenv values;
- raw env values;
- raw override arrays;
- raw PHP warnings;
- OS error messages;
- absolute local paths;
- tokens;
- session ids;
- cookies;
- Authorization headers;
- credentials;
- passwords;
- private keys;
- raw SQL;
- stack traces;
- environment-specific bytes.

`EnvRepositoryInterface::all()` exposes raw env values to runtime owners only.

Diagnostics, traces, logs, validation errors, generated artifacts, and explain output must not print the raw env map directly.

`ConfigValueSource` metadata attached by Phase A must be safe and redacted.

Source metadata may include safe logical identifiers such as:

```text
dotenv/.env.test.local
system_env
env key name
```

Source metadata must not include raw values or absolute application roots.

## Runtime lifecycle impact

Bootstrap Phase A is lifecycle-free.

Boot source code under:

```text
packages/core/kernel/src/Boot/**
```

must not depend on:

```text
ResetOrchestrator
TagRegistry
ResetInterface
KernelRuntime
reset DI tag identifiers
```

Phase A does not start a unit of work.

Phase A does not trigger reset orchestration.

Phase A does not register runtime hooks.

Phase A only prepares minimal boot inputs and env snapshots for later owners.

## Boundaries

This ADR does not introduce:

- Bootstrap Phase A services in the compiled runtime definition graph;
- Kernel compile-host services in the compiled runtime definition graph;
- Bootstrap Phase A ownership of artifact-runtime seeds;
- `BootstrapInput` or `BootstrapConfig` as compiled runtime container seeds;
- artifact-runtime seed objects in generated artifact payloads;
- artifact-runtime seed objects in fingerprint input;
- Bootstrap Phase A ownership of current-generation selection;
- Bootstrap Phase A ownership of artifact-generation validation or runtime hydration;
- public `Bootstrapper`;
- public `BootstrapResult`;
- new config roots;
- full config merge;
- config explain output;
- env overlays;
- module planning;
- preset-policy combination into effective selected roots;
- preset file parsing;
- storing `presets` in `BootstrapConfig`;
- validating preset file existence during Phase A;
- loading mode preset files during Phase A;
- reading `kernel.modes.*` during Phase A;
- composer metadata scanning;
- app root existence validation;
- HTTP adapter behavior;
- CLI command behavior;
- runtime lifecycle execution;
- reset orchestration;
- UnitOfWork start/end behavior;
- generated artifacts.

## Verification

Expected verification includes:

```text
packages/core/kernel/tests/Unit/BootstrapArtifactsCacheDirValidationTest.php
packages/core/kernel/tests/Unit/BootstrapSourceSnapshotTest.php
packages/core/kernel/tests/Unit/ArtifactPathResolverUsesBootstrapAppTargetTest.php
packages/core/kernel/tests/Contract/ModulePlanArtifactHydratorContractTest.php
packages/core/kernel/tests/Contract/KernelBootstrapDoesNotUseRuntimeLifecycleTest.php
packages/core/kernel/tests/Contract/KernelCompileHostServicesAreNotRuntimeDefinitionsContractTest.php
packages/core/kernel/tests/Contract/KernelDoesNotWriteToStdoutTest.php
packages/core/kernel/tests/Integration/BootstrapSourceResolverPrecedenceTest.php
packages/core/kernel/tests/Integration/BootstrapSelectsExplicitAppTargetTest.php
packages/core/kernel/tests/Integration/BootstrapDoesNotScanAppsTest.php
packages/core/kernel/tests/Integration/BootstrapOverridesLoaderReadsOnlyAppPhpTest.php
packages/core/kernel/tests/Integration/BootstrapPresetResolutionPrecedenceTest.php
packages/core/kernel/tests/Integration/ArtifactPipelineUsesConfiguredCacheDirTest.php
packages/core/kernel/tests/Integration/FingerprintDoesNotDependOnArtifactsCacheDirTest.php
packages/core/kernel/tests/Integration/BootstrapWorksWithoutAnyApplicationConfigFilesTest.php
packages/core/kernel/tests/Integration/BootstrapDotenvRespectedUnderStrictPolicyTest.php
packages/core/kernel/tests/Integration/BootstrapSystemEnvOverridesDotenvUnderAllowSystemPolicyTest.php
packages/core/kernel/tests/Integration/RuntimeContainerSeedSetRejectsUnknownSeedsTest.php
packages/core/kernel/tests/Integration/CompiledContainerFactoryResolvesRuntimeSeedsTest.php
packages/core/kernel/tests/Integration/ArtifactRuntimeBootRejectsMixedGenerationTest.php
packages/core/kernel/tests/Integration/ArtifactRuntimeBootRejectsEnvelopeFingerprintMismatchTest.php
packages/core/kernel/tests/Integration/ArtifactOnlyBootHydratesModulePlanTest.php
packages/core/kernel/tests/Integration/ArtifactOnlyBootHydratesConfigRepositoryTest.php
```

Verification must prove:

- `web`, `api`, `console`, and `worker` are accepted app targets;
- invalid app target fails with `BootstrapException::REASON_INVALID_APP_TARGET`;
- invalid app target diagnostics do not leak raw input;
- `appRoot` is derived as `applicationRoot/apps/<target>`;
- Phase A does not scan `apps/*`;
- sibling app directories do not affect selected app target;
- `BootstrapOverridesLoader` reads `config/app.php` when present;
- `BootstrapOverridesLoader` does not read `config/modules.php`;
- unknown override keys fail deterministically;
- moduleOverrides accepts only known app targets and per-target include/exclude list keys;
- duplicate, noncanonical, and invalid ModuleIds in every permitted source layer, including inactive targets, fail before source merging with `BootstrapException::REASON_OVERRIDES_INVALID`;
- selected-target include/exclude conflicts fail with `BootstrapException::REASON_OVERRIDES_INVALID`;
- missing selected-target moduleOverrides produce empty immutable `ResolvedModuleOverrides`;
- raw override values do not leak in exception messages;
- bare application boot works without any application config files;
- package defaults are used when explicit input and overrides are absent;
- explicit `BootstrapInput::preset()` wins over `config/app.php` `presets[appTarget]`;
- explicit `BootstrapInput::preset()` wins over global `preset`;
- `config/app.php` `presets[appTarget]` wins over global `preset`;
- global `preset` is used when `presets` does not contain selected app target;
- `kernel.boot.default_preset` is used when neither explicit input nor app.php preset exists;
- empty `presets` behaves as absent;
- `presets` does not select or modify app target;
- Phase A does not require selected preset file to exist;
- Phase A does not load `resources/modes/*.php`;
- Phase A does not load `config/modes/*.php`;
- `kernel.boot.default_artifacts_cache_dir` is used when neither explicit input nor `app.php` override exists;
- `config/app.php` `artifactsCacheDir` wins over the package fallback;
- explicit `BootstrapInput::artifactsCacheDir()` wins over both the app override and package fallback;
- invalid explicit artifact cache directory values fail with `BootstrapException::REASON_ARTIFACTS_CACHE_DIR_INVALID`;
- invalid `app.php` artifact cache directory values fail through the safe override failure policy;
- invalid package fallback `kernel.boot.default_artifacts_cache_dir` fails deterministically with `bootstrap-config-default-artifacts-cache-dir-invalid`;
- the resolved directory is exposed through `BootstrapConfig::artifactsCacheDir()`;
- Phase B config and compiled config are not used to re-resolve artifact location;
- strict dotenv policy ignores system env values;
- strict dotenv policy forbids system env fallback;
- strict dotenv policy ignores unknown process `CORETSIA_BOOT_*` keys;
- appEnv selection is frozen before environment-specific dotenv layers are loaded;
- explicit BootstrapInput appEnv wins over a different lower-priority process appEnv without false mismatch;
- process appEnv selects the environment only under explicitly requested AllowSystem;
- environment-specific dotenv appEnv mismatch fails closed;
- `activeTargets` is project-wide, validated, and never selects a runtime AppTarget;
- normalized presets and module overrides merge independently per target and field;
- `{}` is a bootstrap JSON-map no-op and `{"web":[]}` explicitly clears the corresponding module list;
- duplicate JSON object keys, malformed JSON, unsupported aliases, and noncanonical values fail closed;
- a supplied source snapshot is reused across target-local resolution without repeated source reads;
- a snapshot with another application root or conflicting explicit appEnv/source policy is rejected;
- source provenance and raw environment values do not enter installation verification identity;
- allow-system policy lets system env values override dotenv values;
- allow-system policy uses dotenv values only when system env keys are absent;
- present empty string remains present;
- missing env keys remain `EnvValue::missing()`;
- source metadata is redacted;
- source metadata does not contain raw dotenv values;
- source metadata does not contain raw system env values;
- source metadata does not contain absolute application roots;
- Boot source does not depend on runtime lifecycle/reset services;
- Kernel boot/runtime/provider source does not write to stdout or stderr;
- Kernel compile-host services are absent from the canonical runtime definition stream;
- Bootstrap Phase A service ids are absent from the compiled runtime definition graph;
- `KernelServiceProvider::define()` contributes only Kernel runtime services;
- `KernelServiceProvider::register()` may register compile-host factories without duplicating Kernel runtime wiring;
- Kernel runtime factories do not re-read Phase A or Phase B state;
- artifact-only runtime hydration does not execute Bootstrap Phase A;
- artifact-only runtime hydration does not execute ConfigKernel Phase B;
- `ArtifactRuntimeInput` is an entrypoint input rather than an artifact payload;
- artifact-only runtime hydration produces exactly `ConfigRepositoryInterface`, `ModulePlan`, and `RuntimePathContext`;
- runtime seed ids cannot be supplied arbitrarily;
- absolute runtime path state is absent from generated artifacts, compiled graphs, and fingerprint input;
- `ArtifactRuntimeBooter` accepts only an artifact root and cannot accept caller-selected individual artifact paths;
- `ArtifactRuntimeBooter` selects `current` through `ArtifactGenerationLocator`;
- artifact-only runtime reads and validates all four files from one selected generation;
- mixed-generation runtime input cannot be constructed through the public boot API.

## Related SSoT

- `docs/ssot/application-dependency-sync.md`
- `docs/ssot/config-and-env.md`
- `docs/ssot/config-precedence-matrix.md`
- `docs/ssot/config-merge-order.md`
- `docs/ssot/tags.md`
- `docs/ssot/uow-and-reset-contracts.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/runtime-container-definitions.md`
- `docs/ssot/artifacts-and-fingerprint.md`

## Related ADR

- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md`
- `docs/adr/ADR-0033-application-dependency-sync-installation-intent.md`
