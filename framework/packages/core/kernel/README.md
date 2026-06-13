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

# coretsia/core-kernel

`core/kernel` is the **Kernel runtime** package for the Coretsia Framework monorepo.

**Scope:** Kernel module metadata, Kernel service provider/factory wiring, Bootstrap Phase A minimal boot-input resolution, deterministic app target selection, dotenv/system env source precedence, immutable env repository snapshot construction, deterministic ModulePlan resolution, mode preset loading, module graph policy, ConfigKernel Phase B orchestration, config directives, deterministic config merge, semantic config validation, safe config explain traces, Kernel-owned artifact production for `module-manifest.php`, `config.php`, and `container.php`, deterministic artifact fingerprint input construction and calculation, Kernel-owned cache verification for generated artifacts, Kernel-owned `KernelRuntime` implementation, hook invocation, Kernel-owned format-neutral UnitOfWork context/result shapes, UnitOfWork type and outcome vocabularies, UoW-specific json-like shape policy through a Foundation-backed internal wrapper, normalized hook payload production, canonical UnitOfWork lifecycle policy, and safe lifecycle summary observability.

**Out of scope:** public bootstrap orchestration facade ownership, public bootstrap aggregate result ownership, config CLI command UX, module debug CLI UX, reusable baseline json-like runtime value model ownership, generic redaction engine, HTTP response construction, HTTP status-code selection, PSR-7/PSR-15 integration, CLI command execution, CLI output rendering, platform-owned artifact production such as `routes@1`, platform adapters, integrations, observability exporters/backends, reset discovery implementation, and tooling-only behavior.

## Package identity

- **Path:** `framework/packages/core/kernel`
- **Package id:** `core/kernel`
- **Composer name:** `coretsia/core-kernel`
- **Module id:** `core.kernel`
- **Namespace:** `Coretsia\Kernel\*` (PSR-4: `src/`)
- **Kind:** runtime
- **Config root:** `kernel`

Monorepo versioning is **repo-wide only** via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions **MUST NOT** be used.

## Dependency policy

This package is runtime-safe and format-neutral.

- **Depends on:**
  - `core/contracts`
  - `core/foundation`
- **Forbidden:**
  - `platform/*`
  - `integrations/*`
  - `Psr\Http\Message\*`
  - `Psr\Http\Server\*`

`core/kernel` MUST NOT depend on transport implementation packages.

Kernel UnitOfWork shapes MUST remain format-neutral and MUST NOT expose transport objects.

Kernel UoW shape normalization consumes the Foundation-owned baseline json-like normalizer:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

through the Kernel-owned internal wrapper:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

Kernel MUST NOT duplicate the baseline recursive json-like walker.

## Runtime responsibilities

This package provides the Kernel baseline runtime layer:

- Kernel module metadata through `Coretsia\Kernel\Module\KernelModule`.
- Kernel service provider registration through `Coretsia\Kernel\Provider\KernelServiceProvider`.
- Stateless Kernel service factory/wiring helper through `Coretsia\Kernel\Provider\KernelServiceFactory`.
- Kernel-owned UnitOfWork lifecycle implementation through `Coretsia\Kernel\Runtime\KernelRuntime`.
- Contracts-level runtime port binding:
  - `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
  - bound by DI to `Coretsia\Kernel\Runtime\KernelRuntime`
- Kernel hook invocation through `Coretsia\Kernel\Runtime\Hook\HookInvoker`.
- Kernel hook payload normalization through `Coretsia\Kernel\Runtime\Hook\HookContextNormalizer`.
- Kernel-owned lifecycle hook discovery tag constants through `Coretsia\Kernel\Provider\Tags`.
- Kernel configuration defaults and validation rules under the `kernel` config root.
- Bootstrap Phase A public input/config API:
  - `Coretsia\Kernel\Boot\AppTarget`
  - `Coretsia\Kernel\Boot\BootstrapInput`
  - `Coretsia\Kernel\Boot\BootstrapConfig`
  - `Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy`
  - `Coretsia\Kernel\Boot\Exception\BootstrapException`
- Bootstrap Phase A internal implementation services registered through DI:
  - `Coretsia\Kernel\Boot\BootstrapConfigResolver`
  - `Coretsia\Kernel\Boot\BootstrapOverridesLoader`
  - `Coretsia\Kernel\Boot\DotenvLoader`
  - `Coretsia\Kernel\Boot\EnvRepositoryBuilder`
- Kernel-owned ConfigKernel Phase B orchestration:
  - `Coretsia\Kernel\Config\ConfigKernel`
  - `Coretsia\Kernel\Config\ConfigRulesLoader`
  - `Coretsia\Kernel\Config\ConfigValidator`
  - `Coretsia\Kernel\Config\ConfigMerger`
  - `Coretsia\Kernel\Config\DirectiveProcessor`
  - `Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard`
  - `Coretsia\Kernel\Config\Explain\ConfigExplainer`
  - `Coretsia\Kernel\Config\Loaders\PackageDefaultsConfigLoader`
  - `Coretsia\Kernel\Config\Loaders\SkeletonConfigLoader`
  - `Coretsia\Kernel\Config\Loaders\EnvironmentOverlayLoader`
- Kernel-owned artifact production, fingerprint, and cache verification services:
  - `Coretsia\Kernel\Artifacts\PayloadNormalizer`
  - `Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory`
  - `Coretsia\Kernel\Artifacts\ArtifactWriter`
  - `Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder`
  - `Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder`
  - `Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder`
  - `Coretsia\Kernel\Container\ContainerCompiler`
  - `Coretsia\Kernel\Container\CompiledContainerFactory`
  - `Coretsia\Kernel\Artifacts\Compiler\ArtifactCompiler`
  - `Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder`
  - `Coretsia\Kernel\Artifacts\Fingerprint\DeterministicFileLister`
  - `Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator`
  - `Coretsia\Kernel\Artifacts\Fingerprint\FingerprintExplainer`
  - `Coretsia\Kernel\Artifacts\Paths\ArtifactPathResolver`
  - `Coretsia\Kernel\Artifacts\Php\PhpArtifactReader`
  - `Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper`
  - `Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator`
  - `Coretsia\Kernel\Artifacts\Verifier\CacheVerifier`
- Kernel-owned generated artifact basenames:
  - `module-manifest.php`
  - `config.php`
  - `container.php`
- Kernel-owned artifact/fingerprint/container-compile/cache observability:
  - span: `kernel.artifacts_write`
  - span: `kernel.fingerprint_calculate`
  - span: `kernel.container_compile`
  - span: `kernel.cache_verify`
  - metrics: `kernel.artifacts_write_total`, `kernel.artifacts_write_duration_ms`
  - metrics: `kernel.fingerprint_calculate_total`, `kernel.fingerprint_calculate_duration_ms`
  - metrics: `kernel.container_compile_total`, `kernel.container_compile_duration_ms`
  - metrics: `kernel.cache_verify_total`, `kernel.cache_verify_duration_ms`
  - allowed metric label: `outcome`
- Kernel-owned deterministic ModulePlan resolution:
  - `Coretsia\Kernel\Module\ComposerInstalledMetadataProvider`
  - `Coretsia\Kernel\Module\ComposerManifestReader`
  - `Coretsia\Kernel\Module\ModePresetLoaderFactory`
  - `Coretsia\Kernel\Module\FilesystemModePresetLoader`
  - `Coretsia\Kernel\Module\ModePresetSchemaValidator`
  - `Coretsia\Kernel\Module\ModuleGraphResolver`
  - `Coretsia\Kernel\Module\TopologicalSorter`
  - `Coretsia\Kernel\Module\ModulePlanResolver`
  - `Coretsia\Kernel\Module\ModulePlan`
- Canonical UnitOfWork type tokens:
  - `http`
  - `cli`
  - `queue`
  - `scheduler`
- Canonical UnitOfWork outcome tokens:
  - `success`
  - `handled_error`
  - `fatal_error`
- Canonical UnitOfWork context shape:
  - `Coretsia\Kernel\Runtime\UnitOfWorkContext`
- Canonical UnitOfWork result shape:
  - `Coretsia\Kernel\Runtime\UnitOfWorkResult`
- Internal UoW-specific json-like shape wrapper through:
  - `Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer`
- Foundation-owned baseline json-like value validation and recursive deterministic normalization consumed through:
  - `Coretsia\Foundation\Serialization\JsonLikeNormalizer`
- Deterministic validation failures for UnitOfWork shapes:
  - `CORETSIA_UOW_CONTEXT_INVALID`
  - `CORETSIA_UOW_RESULT_INVALID`
- Deterministic Kernel runtime failures:
  - `Coretsia\Kernel\Runtime\Exception\KernelRuntimeException`
  - `CORETSIA_KERNEL_RUNTIME_ERROR`
- Safe lifecycle summary observability emitted by `KernelRuntime`:
  - span: `kernel.uow`
  - metrics: `kernel.uow_total`, `kernel.uow_duration_ms`
  - log message: `kernel.uow`
  - allowed labels/context keys: `operation`, `outcome`, `duration_ms` for logs

Foundation owns reusable runtime mechanisms such as ids, clocks, stopwatch, context storage, deterministic tags, and reset orchestration.

Foundation also owns the reusable baseline json-like runtime value model.

Kernel owns only the UoW-specific layer on top of that model: root map policy, unsafe metadata key policy, attributes limits, exported error map policy, and UoW exception mapping.

Platform packages own transport adapters.

## Bootstrap Phase A

Bootstrap Phase A is the minimal deterministic Kernel boot-input phase.

It resolves only:

```text
skeletonRoot
appTarget
appEnv
preset
debug
envSourcePolicy
appRoot
immutable EnvRepositoryInterface snapshot
```

Phase A is not a full config merge phase.

Full config file discovery, merge, directives, validation, explain output, and environment overlays are owned by ConfigKernel Phase B.

Phase A MAY read only one bootstrap-only skeleton config file:

```text
skeleton/config/app.php
```

This file is not a config root and MUST NOT participate in ConfigKernel Phase B merge.

Phase A MUST NOT read:

```text
skeleton/config/roots.php
skeleton/config/<root>.php
skeleton/config/environments/**
skeleton/apps/<appTarget>/config/**
```

Application target selection is explicit and entrypoint-owned.

Canonical app targets are:

```text
web
api
console
worker
```

The selected app root is derived deterministically as:

```text
skeletonRoot/apps/<appTarget>
```

Phase A MUST NOT scan `skeleton/apps/*` to infer the selected app.

`BootstrapConfig` is a resolved immutable value object only. It MUST NOT resolve defaults, read `skeleton/config/app.php`, parse dotenv files, read system env, or build an env repository.

`BootstrapConfigResolver` is internal and owns only Phase A config resolution:

```text
explicit BootstrapInput
→ bootstrap-only overrides from skeleton/config/app.php
→ package defaults from kernel.boot.* and kernel.env.*
→ BootstrapConfig
```

`EnvRepositoryBuilder` is internal and owns only immutable env repository snapshot construction.

`BootstrapEnvSourcePolicy` controls dotenv/system env source precedence:

```text
strict_dotenv
allow_system
```

It is intentionally separate from `Coretsia\Contracts\Env\EnvPolicy`, which remains a missing-value policy only.

Kernel does not introduce public `Bootstrapper` or public `BootstrapResult` in this package. Entrypoint and platform owners compose the explicit Phase A services through DI until a future owner epic requires a stable public orchestration facade.

## ConfigKernel Phase B

ConfigKernel Phase B is the deterministic full config pipeline.

The canonical orchestration entrypoint is:

```text
Coretsia\Kernel\Config\ConfigKernel
```

ConfigKernel Phase B consumes:

```text
BootstrapConfig
ModulePlan
immutable EnvRepositoryInterface snapshot
explicit package default source candidates
explicit package rules source candidates
explicit skeleton/app split-root names
optional explicit env overlay mappings
```

ConfigKernel Phase B MUST NOT read:

```text
$_ENV
$_SERVER
getenv()
skeleton/config/app.php
```

`ConfigKernel` is an orchestration service only. It coordinates loaders, directives, merge, validation, and explain generation. It does not implement loader, merger, validator, or explainer semantics itself.

The active Phase B order is:

```text
package defaults
→ skeleton shared config
→ skeleton environment config
→ app shared config
→ app environment config
→ env overlays
→ validation
→ optional explain
```

Package defaults are loaded only from enabled ModulePlan modules.

Skeleton/app config uses:

```text
roots.php
<root>.php
```

`roots.php` is the aggregate root-map file for a config layer.

`<root>.php` is the split root-subtree file for one config root.

At the same layer, root-specific `<root>.php` files override aggregate `roots.php` entries.

Config directives are processed per file before merge:

```text
@append
@prepend
@remove
@merge
@replace
```

Directive application happens during merge, when the previous/base value is known.

Environment overlays are generated only from the immutable `EnvRepositoryInterface` snapshot and only for known ruleset-backed or explicitly mapped config paths.

Example projection:

```text
kernel.boot.default_env → KERNEL_BOOT_DEFAULT_ENV
```

User-owned/custom roots are accepted when they obey global config safety rules. If no ruleset exists for a custom root, it is merged, explained, fingerprinted, and marked as:

```text
user_owned
unvalidated
```

Config explain is a baseline Kernel facility. It is caller-requested, not feature-disabled by config.

ConfigKernel emits safe observability:

```text
span: kernel.config_merge
span: kernel.config_explain
metric: kernel.config_merge_total
metric: kernel.config_merge_duration_ms
metric: kernel.config_explain_total
metric: kernel.config_explain_duration_ms
label: outcome
```

Config explain, logs, metrics, and spans MUST NOT expose raw config values, raw env values, secrets, tokens, DSNs, cookies, headers, raw SQL, payloads, stack traces, previous throwable messages, or absolute local paths.

Related documents:

```text
docs/adr/ADR-0026-config-kernel-merge-directives-reserved-namespaces.md
docs/ssot/config-directives.md
docs/ssot/config-merge-order.md
docs/ssot/config-precedence-matrix.md
docs/ssot/observability.md
```

## Kernel artifacts, fingerprint, and cache verification

`core/kernel` owns Kernel-side artifact production, fingerprint behavior, and cache verification for Kernel-owned artifacts.

The Kernel-owned artifact basenames are:

```text
module-manifest.php
config.php
container.php
```

The corresponding canonical artifact identities are:

```text
module-manifest@1
config@1
container@1
```

The canonical global artifact envelope, header fields, deterministic serialization law, and artifact registry are owned by:

```text
docs/ssot/artifacts.md
```

Kernel-side artifact production and fingerprint behavior are owned by:

```text
docs/ssot/artifacts-and-fingerprint.md
```

Kernel cache verification semantics are owned by:

```text
docs/ssot/cache-verify.md
```

Compiled container payload shape and artifact-only runtime boot semantics are owned by:

```text
docs/ssot/compiled-container.md
```

`routes@1` is not Kernel-owned. Route artifact production belongs to `platform/routing`.

`ArtifactCompiler` owns Kernel artifact production orchestration. It builds deterministic fingerprint input, calculates the current fingerprint, compiles descriptor-based container input through `ContainerCompiler`, builds Kernel-owned artifact envelopes, builds the compiled `container@1` envelope through `CompiledContainerBuilder`, resolves artifact paths, and writes Kernel-owned artifacts through `ArtifactWriter`.

`CacheVerifier` owns Kernel cache verification. It rebuilds expected Kernel artifacts in memory, rebuilds expected compiled `container@1` through `ContainerCompiler` and `CompiledContainerBuilder`, reads existing artifacts through `PhpArtifactReader`, validates existing artifact envelopes through `ArtifactSchemaValidator`, compares stored fingerprint to the current fingerprint, compares deterministic LF-normalized bytes, and returns safe clean/dirty/invalid summary data.

Cache verification semantics are:

```text
missing artifact        → dirty
fingerprint mismatch    → dirty
byte mismatch           → dirty
invalid PHP/envelope    → invalid
invalid header/schema   → invalid
valid fingerprint+bytes → clean
```

Cache verification MUST NOT use mtimes, ctimes, permissions, owners, inode ids, directory ordering, or filesystem traversal order as cache semantics.

### Compiled container artifact

`container.php` is the Kernel-owned `container@1` compiled container artifact.

The `container@1` compiled payload uses this canonical payload shape:

```text
aliases
compiled = true
kind = compiled
parameters
services
tags
```

Container compilation is descriptor-based and closure-free. `ContainerCompiler` consumes explicit deterministic descriptor input and produces a deterministic `DefinitionGraph`. It MUST NOT discover runtime providers, discover modules, read source config, read generated artifacts, write artifacts, instantiate runtime services, or use provider fallback.

Production runtime container construction is artifact-only. `CompiledContainerFactory` builds the runtime Foundation container from:

```text
container@1
already-read and already-validated config@1 payload
```

Production runtime boot MUST NOT read source config files, run ConfigKernel, discover modules, compile a new container graph, write or repair artifacts, or silently fall back to provider-based container construction.

Kernel artifact/fingerprint/container-compile/cache services are registered by `KernelServiceProvider` as factories only.

Artifact/fingerprint/container-compile/cache registration happens after ConfigKernel Phase B service registrations and before Kernel runtime service registrations.

Provider registration MUST NOT:

```text
write artifacts
read artifacts
calculate fingerprints
run cache verification
compile container descriptors
build a production runtime container from container.php
resolve BootstrapConfig
resolve ModulePlan
build EnvRepositoryInterface
run ConfigKernel::compile(...)
invoke ResetOrchestrator
start a UnitOfWork
emit stdout/stderr
start artifact/fingerprint/container-compile/cache spans
emit artifact/fingerprint/container-compile/cache metrics
write artifact/fingerprint/container-compile/cache logs
```

`KernelServiceFactory` artifact/fingerprint/container-compile/cache methods are construction/wiring methods only.

Factory methods MUST NOT write files, read generated artifacts, calculate fingerprints, run cache verification, resolve bootstrap/config/module plans, retain the container, retain mutable config snapshots, depend on `ResetOrchestrator`, or keep mutable runtime state.

Artifact/fingerprint/container-compile/cache services receive observability dependencies through public ports/interfaces only:

```text
Coretsia\Contracts\Observability\Tracing\TracerPortInterface
Coretsia\Contracts\Observability\Metrics\MeterPortInterface
Psr\Log\LoggerInterface
Coretsia\Foundation\Time\Stopwatch
```

`core/kernel` artifact/fingerprint/container-compile/cache services MUST NOT instantiate Noop observability implementations and MUST NOT know whether observability dependencies are real adapters or Noop/no-op adapters.

Real-vs-Noop/default binding is owned by the application/foundation composition layer.

Artifact/fingerprint/container-compile/cache observability failures MUST NOT change deterministic artifact writing, fingerprint calculation, container compilation, or cache verification behavior.

Compiled-container failures use deterministic Kernel-owned exceptions and safe fixed messages.

Compile-time failures use:

```text
CORETSIA_CONTAINER_COMPILE_FAILED
container-compile-failed
```

Production runtime boot failures use:

```text
CORETSIA_CONTAINER_ARTIFACT_MISSING
container-artifact-missing
CORETSIA_CONTAINER_ARTIFACT_INVALID
container-artifact-invalid
```

These failures MUST NOT expose absolute paths, raw config values, raw env values, raw payloads, closure dumps, source snippets, PHP warning text, OS error messages, stack traces, or previous throwable messages.

## ModulePlan resolution

ModulePlan resolution is Kernel-owned runtime policy.

The canonical orchestration entrypoint is:

```text
Coretsia\Kernel\Module\ModulePlanResolver
```

ModulePlan resolution uses these single-choice inputs:

```text
BootstrapConfig::preset()
BootstrapConfig::appTarget()
Composer installed metadata
mode preset files
```

`BootstrapConfig::preset()` selects the mode preset.

`BootstrapConfig::appTarget()` is output metadata for `ModulePlan::app()` and app-root derivation only. It MUST NOT create a parallel module-selection source.

Mode preset lookup order is:

```text
1. skeleton override: skeleton/config/modes/<preset>.php
2. framework default: resources/modes/<preset>.php
```

The first existing preset file wins.

Skeleton mode preset overrides replace framework defaults. They are not merged.

Module discovery is metadata-only. Runtime module discovery uses Composer installed metadata and Coretsia module metadata under:

```text
extra.coretsia
```

Runtime discovery MUST NOT scan:

```text
framework/packages/**
package source trees
skeleton/**
skeleton/apps/*
```

Module dependency and conflict edges are read only from:

```text
extra.coretsia.requires
extra.coretsia.conflicts
```

Composer package-level `require` and `conflict` are not Coretsia runtime module graph edges unless explicitly represented in `extra.coretsia.requires` or `extra.coretsia.conflicts`.

ModulePlan output is deterministic and artifact-ready.

ModulePlan resolution itself does not write artifacts. Kernel artifact production may materialize the ModulePlan-derived `module-manifest.php` artifact through `ArtifactCompiler` and `ModuleManifestBuilder`.

ModulePlan resolution emits safe observability:

```text
metric: kernel.modules_resolve_total
metric: kernel.modules_resolve_duration_ms
label: outcome
```

Diagnostics MUST NOT expose filesystem paths, raw Composer metadata, raw preset payloads, secrets, PII, stack traces, or previous throwable messages.

Related documents:

```text
docs/adr/ADR-0024-kernel-module-plan-resolution.md
docs/adr/ADR-0025-kernel-conflicts-optional-missing-policy.md
docs/ssot/modules-and-manifests.md
docs/ssot/modes.md
```

## ModulePlan diagnostics

ModulePlan resolution failures use deterministic Kernel-owned exceptions.

Canonical error codes include:

```text
CORETSIA_MODE_PRESET_NOT_FOUND
CORETSIA_MODE_PRESET_INVALID
CORETSIA_MODULE_MANIFEST_INVALID
CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED
CORETSIA_MODULE_CYCLE_DETECTED
CORETSIA_MODULE_CONFLICT
CORETSIA_MODULE_REQUIRED_MISSING
```

Optional missing modules are non-fatal warnings:

```text
CORETSIA_MODULE_OPTIONAL_MISSING
```

Diagnostics expose only stable reason tokens and safe deterministic context.

Diagnostics MUST NOT expose paths, raw Composer metadata, raw preset payloads, secrets, PII, stack traces, or previous throwable messages.

## KernelRuntime SPI

The external runtime SPI is owned by `core/contracts`:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

The concrete implementation is owned by `core/kernel`:

```text
Coretsia\Kernel\Runtime\KernelRuntime
```

`Coretsia\Kernel\Runtime\KernelRuntime` is the `core/kernel` implementation bound to the contracts port by DI.

Platform, worker, scheduler, queue, and custom runtime adapters MUST depend on:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

Adapters MUST NOT typehint, construct, or directly depend on:

```text
Coretsia\Kernel\Runtime\KernelRuntime
```

The high-level lifecycle API is:

```text
runUnitOfWork(string $type, callable $body, array $attributes = []): mixed
```

`runUnitOfWork()` returns the external body return value.

It MUST NOT return the exported UnitOfWork result array.

Low-level adapters that need exported context/result arrays MAY use:

```text
beginUnitOfWork(string $type, array $attributes = []): array
afterUnitOfWork(array $context, string $outcome, ?Throwable $error = null, array $extensions = []): array
```

Low-level adapters MUST execute their external body only after successful `beginUnitOfWork()`.

Low-level adapters that need the exported result array MUST use `afterUnitOfWork()`.

## KernelRuntime lifecycle

The canonical high-level lifecycle is:

```text
begin UnitOfWork
write base ContextStore keys
invoke before-uow hooks
run external runtime body
build UnitOfWork result
invoke after-uow hooks
ResetOrchestrator.resetAll()
surface result or primary failure
```

The conceptual shorthand is:

```text
begin → hooks → external runtime → after → reset
```

`KernelRuntime` writes these base context keys before the external runtime body is executed:

```text
Coretsia\Foundation\Context\ContextKeys::CORRELATION_ID
Coretsia\Foundation\Context\ContextKeys::UOW_ID
Coretsia\Foundation\Context\ContextKeys::UOW_TYPE
```

Before hooks receive the normalized exported UnitOfWork context array.

After hooks receive the normalized exported UnitOfWork context array and normalized exported UnitOfWork result array.

No `UnitOfWorkContext`, `UnitOfWorkResult`, `ErrorDescriptor`, `Throwable`, transport object, service object, closure, or resource may cross the hook/export boundary.

## Hook discovery

Kernel lifecycle hooks are discovered through Kernel-owned tags.

The canonical public owner constants are:

```text
Coretsia\Kernel\Provider\Tags::KERNEL_HOOK_BEFORE_UOW
Coretsia\Kernel\Provider\Tags::KERNEL_HOOK_AFTER_UOW
```

Their values are:

```text
kernel.hook.before_uow
kernel.hook.after_uow
```

Runtime code that is allowed to depend on `core/kernel` and needs these tags SHOULD use the constants instead of raw literal strings.

Hook services are resolved by `HookInvoker` from Foundation `TagRegistry` entries.

`HookInvoker` preserves the exact order returned by `TagRegistry::all()`.

It MUST NOT re-sort hooks.

It MUST NOT dedupe hooks.

It MUST NOT apply custom priority rules.

Hook service ids are resolved through PSR-11 container lookup.

A before hook must implement:

```text
Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface
```

An after hook must implement:

```text
Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface
```

## Configuration

The package owns the `kernel` configuration root.

Defaults live in:

```text
framework/packages/core/kernel/config/kernel.php
```

The defaults file MUST return the subtree only and MUST NOT repeat the root key.

Valid shape:

```php
return [
    'boot' => [
        'default_env' => 'local',
        'default_preset' => 'micro',
        'default_debug' => false,
    ],
    'env' => [
        'source_policy' => [
            'default_local' => 'strict_dotenv',
            'default_production' => 'allow_system',
        ],
        'dotenv' => [
            'files' => [
                '.env',
                '.env.local',
                '.env.<env>',
                '.env.<env>.local',
            ],
        ],
    ],
    'modules' => [
        'discovery' => [
            'source' => 'composer',
            'allowed_sources' => [
                'composer',
            ],
        ],
    ],
    'modes' => [
        'schema_version' => 1,
        'defaults_path' => 'resources/modes',
        'overrides_path' => 'config/modes',
    ],
    'config' => [
        'forbidden_top_level_roots' => [
            'coretsia',
            '_internal',
        ],
    ],
    'uow' => [
        'attributes' => [
            'max_depth' => 10,
            'max_keys' => 200,
        ],
    ],
];
```

Invalid shape:

```php
return [
    'kernel' => [
        'uow' => [
            'attributes' => [],
        ],
    ],
];
```

Runtime code reads from the global configuration under `kernel.*`.

Canonical Kernel config keys:

| key                                           | default                                                    |
|-----------------------------------------------|------------------------------------------------------------|
| `kernel.boot.default_env`                     | `"local"`                                                  |
| `kernel.boot.default_preset`                  | `"micro"`                                                  |
| `kernel.boot.default_debug`                   | `false`                                                    |
| `kernel.env.source_policy.default_local`      | `"strict_dotenv"`                                          |
| `kernel.env.source_policy.default_production` | `"allow_system"`                                           |
| `kernel.env.dotenv.files`                     | `[".env", ".env.local", ".env.<env>", ".env.<env>.local"]` |
| `kernel.modules.discovery.source`             | `"composer"`                                               |
| `kernel.modules.discovery.allowed_sources`    | `["composer"]`                                             |
| `kernel.modes.schema_version`                 | `1`                                                        |
| `kernel.modes.defaults_path`                  | `"resources/modes"`                                        |
| `kernel.modes.overrides_path`                 | `"config/modes"`                                           |
| `kernel.config.forbidden_top_level_roots`     | `["coretsia", "_internal"]`                                |
| `kernel.uow.attributes.max_depth`             | `10`                                                       |
| `kernel.uow.attributes.max_keys`              | `200`                                                      |

`kernel.modules.discovery.source` is shape-validated by config rules, but supported-source membership is enforced by `ModulePlanResolver` against `kernel.modules.discovery.allowed_sources`.

`kernel.modes.defaults_path` is package-relative.

`kernel.modes.overrides_path` is skeleton-root-relative.

Both mode paths MUST be relative safe paths.

`kernel.config.forbidden_top_level_roots` configures the global forbidden top-level config roots used when wiring `ConfigNamespaceGuard`.

The default forbidden roots are:

```text
coretsia
_internal
```

`kernel` and `foundation` MUST NOT be listed as forbidden top-level roots because applications must be able to configure those roots.

This package does not introduce generic json-like configuration.

The following config keys MUST NOT be introduced by Kernel:

```text
kernel.json_like.*
foundation.json_like.*
foundation.serialization.json_like.*
```

Baseline json-like runtime value policy is owned by Foundation and is not configurable by Kernel.

Both values MUST be integers greater than zero.

This package does not introduce outcome mapping configuration.

Outcome mapping is canonical policy, not runtime configuration.

## Config files

Package default config files use root-specific files:

```text
framework/packages/<vendor>/<package>/config/<root>.php
```

Package default config files MUST return the subtree for `<root>`.

Package defaults MUST NOT use:

```text
config/roots.php
```

Skeleton/app config files may use both aggregate and split styles:

```text
skeleton/config/roots.php
skeleton/config/<root>.php
skeleton/config/environments/<appEnv>/roots.php
skeleton/config/environments/<appEnv>/<root>.php
skeleton/apps/<appTarget>/config/roots.php
skeleton/apps/<appTarget>/config/<root>.php
skeleton/apps/<appTarget>/config/environments/<appEnv>/roots.php
skeleton/apps/<appTarget>/config/environments/<appEnv>/<root>.php
```

`roots.php` returns a global root map.

`<root>.php` returns only the subtree for `<root>`.

Root-specific files override aggregate `roots.php` files at the same layer.

Config rules are declarative data files:

```text
config/rules.php
```

Rules files MUST return plain arrays and MUST NOT contain closures, objects, resources, or executable validation callbacks.

## Config directives

Config directives are normalized per file before merge and applied during merge.

Supported directives are:

```text
@append
@prepend
@remove
@merge
@replace
```

Directive namespace is reserved.

Any unsupported `@*` key fails as a reserved namespace violation.

If a map level contains a directive key, that level must contain exactly one directive key and no normal config keys.

Directive examples are documented in:

```text
docs/ssot/config-directives.md
```

The canonical merge order is documented in:

```text
docs/ssot/config-merge-order.md
docs/ssot/config-precedence-matrix.md
```

## Config explain

Config explain is a safe Kernel baseline facility.

It may expose:

```text
normalized relative source ids
config dot paths
directive names
source type
source precedence/order
validated/unvalidated root status
safe hash/length metadata when produced upstream
```

It MUST NOT expose:

```text
raw config values
raw env values
secrets
tokens
DSNs
cookies
headers
raw SQL
payloads
stack traces
previous throwable messages
absolute local paths
```

User-owned roots without loaded rulesets are marked as:

```text
user_owned
unvalidated
```

## UnitOfWork types

The canonical UnitOfWork type values are:

```text
http
cli
queue
scheduler
```

The implementation is:

```text
Coretsia\Kernel\Runtime\UnitOfWorkType
```

The class is intentionally enum-like instead of a native PHP enum because exported UnitOfWork shapes carry plain strings and no object instance may cross Kernel hook/export boundaries.

The tokens are stable lowercase ASCII values and MUST be compared byte-for-byte.

## Outcomes

The canonical UnitOfWork outcome values are:

```text
success
handled_error
fatal_error
```

The implementation is:

```text
Coretsia\Kernel\Runtime\Outcome
```

The class is intentionally enum-like instead of a native PHP enum because exported UnitOfWork shapes carry plain strings and no object instance may cross Kernel hook/export boundaries.

The tokens are stable lowercase ASCII values and MUST be compared byte-for-byte.

HTTP and CLI outcome mapping is governed by:

```text
docs/ssot/uow-outcome-policy.md
```

## UnitOfWorkContext

`Coretsia\Kernel\Runtime\UnitOfWorkContext` is the canonical Kernel runtime shape for the beginning of a UnitOfWork.

Canonical fields:

```text
uowId
type
startedAt
correlationId
attributes
```

`attributes` MUST be a json-like map.

The baseline json-like runtime value model is owned by:

```text
docs/ssot/json-like-runtime-values.md
```

The baseline normalizer is:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

Kernel owns the UoW-specific `attributes` root map policy.

A non-empty root list MUST NOT be used as `attributes`.

`attributes` are normalized by the Kernel-owned internal wrapper:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer::normalizeContextAttributes()
```

The wrapper delegates baseline recursive normalization to Foundation and preserves Kernel-owned policy:

```text
attributes root map policy
unsafe metadata key policy
attributes max_depth
attributes max_keys
UoW context exception mapping
```

Validation failures MUST use:

```text
Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException
CORETSIA_UOW_CONTEXT_INVALID
```

## UnitOfWorkResult

`Coretsia\Kernel\Runtime\UnitOfWorkResult` is the canonical Kernel runtime shape for the completion of a UnitOfWork.

Canonical fields:

```text
uowId
type
correlationId
startedAt
finishedAt
durationMs
outcome
error
extensions
```

`error` is optional.

`durationMs` is the canonical duration field.

It MUST be:

```text
int
>= 0
```

`durationMs` MUST be measured from the canonical monotonic timing source:

```text
Coretsia\Foundation\Time\Stopwatch
```

`durationMs` MUST NOT be calculated from:

```text
finishedAt - startedAt
```

`extensions` MUST be a json-like map.

The baseline json-like runtime value model is owned by:

```text
docs/ssot/json-like-runtime-values.md
```

The baseline normalizer is:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

Kernel owns the UoW-specific `extensions` root map policy.

A non-empty root list MUST NOT be used as `extensions`.

`extensions` are normalized by the Kernel-owned internal wrapper:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer::normalizeResultExtensions()
```

The wrapper delegates baseline recursive normalization to Foundation and preserves Kernel-owned policy:

```text
extensions root map policy
unsafe metadata key policy
UoW result exception mapping
```

Validation failures MUST use:

```text
Coretsia\Kernel\Runtime\Exception\UnitOfWorkResultInvalidException
CORETSIA_UOW_RESULT_INVALID
```

## ErrorDescriptor boundary

`UnitOfWorkResult.error` MAY be represented internally as:

```text
Coretsia\Contracts\Observability\Errors\ErrorDescriptor
```

Before crossing any Kernel hook/export boundary, the error MUST be normalized to a json-like exported error map.

The exported error map is normalized by:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer::normalizeExportedErrorMap()
```

The wrapper delegates baseline recursive normalization to:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

Kernel owns the exported `error` map policy, including the non-empty root map requirement and UoW result exception mapping.

No `ErrorDescriptor` object instance MAY cross the hook/export boundary.

No Throwable object MAY cross the hook/export boundary.

Invalid exported error maps MUST fail with:

```text
CORETSIA_UOW_RESULT_INVALID
```

## Json-like shape normalization

Kernel centralizes UoW-specific json-like shape policy in:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

This class is internal and is not public API.

It is not a DI service.

It is not a transport extension point.

It MUST NOT be exposed through:

```text
framework/packages/core/kernel/PUBLIC_API.md
```

It provides:

```text
normalizeContextAttributes(array $attributes, int $maxDepth, int $maxKeys): array
normalizeResultExtensions(array $extensions): array
normalizeExportedErrorMap(array $error): array
```

Baseline json-like value validation and recursive deterministic normalization are owned by Foundation:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

Baseline failures are represented by:

```text
Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException
```

`JsonLikeShapeNormalizer` MUST delegate baseline recursive normalization to Foundation.

`JsonLikeShapeNormalizer` owns only UoW-specific policy:

- root map validation for `attributes`;
- root map validation for `extensions`;
- non-empty root map validation for exported `error`;
- context `max_depth`;
- context `max_keys`;
- deterministic unsafe-key rejection for known unsafe metadata keys;
- safe string and safe single-line string checks;
- UoW-specific exception reason mapping;
- UoW-safe diagnostics without raw values.

`JsonLikeShapeNormalizer` MUST NOT duplicate the baseline recursive json-like walker.

Foundation owns:

- scalar acceptance;
- float, `NAN`, `INF`, and `-INF` rejection;
- object, closure, resource, and unsupported type rejection;
- string-keyed maps only;
- list order preservation;
- recursive map sorting by byte-order `strcmp`;
- baseline safe path diagnostics.

## Hook and export boundary

Kernel hooks and low-level adapters receive exported array shapes, not shape objects.

Before hooks receive context data as:

```text
UnitOfWorkContext -> normalized array $context
```

After hooks receive context/result data as:

```text
UnitOfWorkContext -> normalized array $context
UnitOfWorkResult -> normalized array $result
```

The contracts hook signatures are:

```text
BeforeUowHookInterface::beforeUow(array $context): void
AfterUowHookInterface::afterUow(array $context, array $result): void
```

Nested `attributes`, `extensions`, and exported `error` maps MUST be baseline-normalized through `JsonLikeShapeNormalizer`, which delegates recursive normalization to Foundation.

No object instance MAY cross the hook/export boundary.

Forbidden boundary values include:

- `UnitOfWorkContext` object;
- `UnitOfWorkResult` object;
- `ErrorDescriptor` object;
- Throwable object;
- request object;
- response object;
- PSR-7 object;
- CLI command object;
- queue message object;
- service instance;
- closure;
- resource.

## Reset lifecycle boundary

Foundation owns reset discovery and reset orchestration mechanics.

The canonical Foundation reset executor is:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

Kernel runtime code consumes reset only through:

```text
ResetOrchestrator::resetAll()
```

`core/kernel` MUST NOT enumerate reset-tagged services directly.

`core/kernel` MUST NOT call `ResetInterface::reset()` directly on discovered services.

`core/kernel` MUST NOT define `KERNEL_RESET` constants.

The reset discovery tag is owned by `core/foundation`.

The canonical lifecycle position is:

```text
after-uow hooks → ResetOrchestrator.resetAll()
```

For every UnitOfWork lifecycle that reaches reset responsibility, `KernelRuntime` MUST call `ResetOrchestrator::resetAll()` exactly once.

If an earlier primary failure exists and reset also fails, the earlier primary failure remains surfaced.

If no earlier primary failure exists and reset fails, `KernelRuntime` surfaces a safe `KernelRuntimeException` with reason:

```text
kernel-runtime-reset-failed
```

## No PSR-7/15 runtime boundary

Kernel runtime APIs are format-neutral.

`core/kernel` MUST NOT expose or require:

```text
Psr\Http\Message\*
Psr\Http\Server\*
```

Kernel runtime code MUST NOT depend on platform HTTP request/response objects, PSR-7 request/response objects, PSR-15 middleware/handler objects, CLI command objects, queue vendor messages, scheduler vendor contexts, or integration package objects.

PSR logger and PSR container usage are allowed implementation dependencies:

```text
Psr\Container\ContainerInterface
Psr\Log\LoggerInterface
```

The PSR-7/15 ban is specific to transport APIs, not every `Psr\*` namespace.

## Observability

`KernelRuntime` emits safe lifecycle summary observability through injected ports.

The concrete exporters/backends remain out of scope for `core/kernel`.

KernelRuntime receives these observability dependencies through DI:

```text
Psr\Log\LoggerInterface
Coretsia\Contracts\Observability\Tracing\TracerPortInterface
Coretsia\Contracts\Observability\Metrics\MeterPortInterface
```

The canonical span name is:

```text
kernel.uow
```

The canonical metrics are:

```text
kernel.uow_total
kernel.uow_duration_ms
```

The lifecycle summary log message is:

```text
kernel.uow
```

Allowed labels/attributes for span and metrics are:

```text
operation
outcome
```

`operation` is the normalized UnitOfWork type.

For an HTTP UnitOfWork:

```text
operation = http
```

`outcome` is the normalized UnitOfWork outcome token.

The lifecycle summary log context contains only:

```text
duration_ms
operation
outcome
```

Lifecycle summary observability MUST NOT include:

- raw `uowId`;
- raw `correlationId`;
- raw context arrays;
- raw hook payloads;
- raw transport payloads;
- raw Throwable messages;
- stack traces;
- tokens;
- cookies;
- headers;
- raw SQL;
- local absolute paths.

Observability port failures MUST NOT replace primary KernelRuntime lifecycle failures.

## Errors

This package defines Kernel UnitOfWork validation exceptions:

- `Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException`
  - canonical error code: `CORETSIA_UOW_CONTEXT_INVALID`
- `Coretsia\Kernel\Runtime\Exception\UnitOfWorkResultInvalidException`
  - canonical error code: `CORETSIA_UOW_RESULT_INVALID`

Baseline json-like failures from:

```text
Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException
```

are mapped locally by:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

to UoW-specific validation exceptions and reason tokens.

Exception messages MUST be deterministic and safe.

Exception messages MAY include only:

```text
safe path-to-value
stable reason code
safe type name
```

Exception messages MUST NOT include:

- rejected raw values;
- payloads;
- secrets;
- raw SQL;
- authorization data;
- cookies;
- tokens;
- session ids;
- stack traces;
- PII;
- absolute local paths;
- environment-specific data.

Higher-level error reporting, rendering, and transport mapping are owned by higher layers.

## Security / Redaction

`core/kernel` MUST NOT leak sensitive runtime data through UnitOfWork shapes.

Forbidden in `UnitOfWorkContext.attributes` and `UnitOfWorkResult.extensions`:

- authorization headers;
- cookies;
- session ids;
- tokens;
- passwords;
- credentials;
- private keys;
- raw headers;
- raw request payloads;
- raw response payloads;
- raw queue messages;
- raw SQL;
- stack traces;
- raw Throwable objects;
- PII;
- private customer data;
- direct user identifiers;
- absolute local paths;
- environment-specific bytes.

Allowed safe metadata includes:

```text
safe ids
stable enums
counts
lengths
hashes
bounded safe status/category tokens
```

Runtime owners MUST prefer omission over unsafe emission.

The Kernel internal json-like shape wrapper includes deterministic unsafe-key guards for known unsafe metadata keys.

This guard is Kernel-owned UoW-specific policy.

It is not a PII detector.

It is a deterministic denylist for obvious policy-key names.

Foundation `JsonLikeNormalizer` does not own the unsafe metadata key denylist.

## Public API

Package-local public API evidence is maintained in:

```text
framework/packages/core/kernel/PUBLIC_API.md
```

That file is the source used by the Kernel public API gate.

Bootstrap Phase A public API symbols are:

```text
Coretsia\Kernel\Boot\AppTarget
Coretsia\Kernel\Boot\BootstrapConfig
Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy
Coretsia\Kernel\Boot\BootstrapInput
Coretsia\Kernel\Boot\Exception\BootstrapException
```

Bootstrap Phase A implementation helpers are internal and MUST NOT be listed as public API:

```text
Coretsia\Kernel\Boot\BootstrapConfigResolver
Coretsia\Kernel\Boot\BootstrapOverridesLoader
Coretsia\Kernel\Boot\DotenvLoader
Coretsia\Kernel\Boot\EnvRepositoryBuilder
Coretsia\Kernel\Boot\ArrayEnvRepository
```

`Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer` is internal implementation detail.

It MUST NOT be listed as Kernel public API.

Kernel public API consumers MUST use `UnitOfWorkContext` and `UnitOfWorkResult`, not the internal normalizer.

Runtime adapters MUST use the contracts-level runtime port:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

Runtime adapters MUST NOT typehint or construct the concrete implementation directly:

```text
Coretsia\Kernel\Runtime\KernelRuntime
```

The concrete implementation is resolved through DI binding in `core/kernel`.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Kernel package source](https://github.com/coretsia/monorepo/tree/main/framework/packages/core/kernel)
- [Packaging strategy](https://github.com/coretsia/monorepo/blob/main/docs/architecture/PACKAGING.md)
- [Json-like Runtime Values SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/json-like-runtime-values.md)
- [UnitOfWork Shapes SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/uow-shapes.md)
- [UnitOfWork Outcome Policy SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/uow-outcome-policy.md)
- [UoW and Reset Contracts SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/uow-and-reset-contracts.md)
- [ADR-0020: Kernel runtime UnitOfWork SPI](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0020-kernel-runtime-uow-spi.md)
- [ADR-0023: Kernel Bootstrap Phase A](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0023-kernel-bootstrap-phase-a.md)
- [Artifact Header and Schema Registry SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/artifacts.md)
- [Kernel Artifacts and Fingerprint Behavior SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/artifacts-and-fingerprint.md)
- [Kernel Cache Verification Semantics SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/cache-verify.md)
- [Compiled Container Payload and Artifact-Only Boot Semantics SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/compiled-container.md)
- [ADR-0028: Kernel Artifacts, Fingerprint, and Cache Verification](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0028-kernel-artifacts-fingerprint-cache-verify.md)
- [ADR-0029: Kernel compiled container artifact](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0029-kernel-container-compile-artifact.md)
