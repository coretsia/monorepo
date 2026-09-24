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

```yaml
adrVersion: 1
status: pre-accepted
owner: core/kernel
```

## Context

Kernel module compilation must separate preset policy, effective module selection, installed-module discovery, and the resolved executable graph. Bootstrap Phase A returns the selected preset name and immutable app-target-specific module overrides; the compile-host orchestration then resolves one installed manifest snapshot and one module plan. No runtime boot operation reloads presets or re-discovers Composer packages.

## Decision

The six ownership values are distinct:

```text
BootstrapConfig = resolved bootstrap name/state + ResolvedModuleOverrides
ModePreset = namespace-owned preset policy source
ModuleSelection = immutable effective module-selection intent (compile host)
ModuleManifest = installed-module discovery snapshot (contracts)
ModulePlan = resolved executable runtime graph
ModuleResolution = one ModuleManifest + ModulePlan snapshot
```

`ModePreset != ModuleSelection != ModulePlan`. The contracts `ModuleManifest` snapshot is **not** the generated `module-manifest@1` artifact.

## Phase A: bootstrap and namespace-bound preset policy

`BootstrapConfigResolver` resolves preset-name precedence: explicit `BootstrapInput::preset()`, `config/app.php` `presets[appTarget]`, global `preset`, then `kernel.boot.default_preset`. It validates the selected app-target `moduleOverrides` into `ResolvedModuleOverrides` with unique sorted `include`/`exclude` `ModuleId` lists; absent overrides are empty. Malformed external input fails with `BootstrapException::REASON_OVERRIDES_INVALID`, before module-resolution observability begins.

`PresetNamespaceResolver` classifies by name only. Kernel-owned `micro`, `express`, `hybrid`, and `enterprise` are bound to `CanonicalPresetSource`, which reads `<kernelPackageRoot>/<kernel.modes.defaults_path>/<name>.php`. All other safe names are bound to application-owned `CustomPresetSource`, which reads `<applicationRoot>/<kernel.modes.overrides_path>/<name>.php`. Neither source searches the other or derives ownership from file presence. A canonical filename in the application source is forbidden for every selected preset and fails with `CanonicalPresetOverrideException` even if represented by a dangling symlink.

`ModePresetLoaderFactory::createFor(BootstrapConfig, PresetNamespace)` binds exactly one source to `FilesystemModePresetLoader`. The loader validates schema `1` against precisely `schemaVersion`, `name`, `description`, `required`, `modules`, `featureBundles`, and `metadata`; no other top-level fields are valid. Files must stay within their configured namespace and owning-root boundaries. An existing unreadable or escaping file is invalid rather than missing. An actually absent selected file fails `ModePresetNotFoundException` without a cross-namespace fallback.

`ModePreset` is an immutable Kernel-owned representation of validated preset policy. Direct construction MUST enforce the same stored-value safety requirements as construction through `ModePresetSchemaValidator` and the namespace-bound loader. It MUST NOT provide a weaker path for creating preset state.

Preset names MUST satisfy the canonical safe-name grammar and the 64-byte limit. Descriptions, `featureBundles`, and `metadata` MUST satisfy their respective length, depth, collection-shape, and path-safety restrictions. Preset state MUST NOT contain filesystem paths, application roots, service instances, closures, resources, or loader state.

`required` and `modules` MUST each be a list of valid `ModuleId` values. Duplicate ids within either source list and intersections between the two lists are invalid. Valid collections are exported in deterministic byte-order `strcmp` order. The `featureBundles` value remains an `array<string,mixed>` JSON-like map, not a module-id list.

Preset validation does not inspect the installed `ModuleManifest`, resolve dependencies, or determine the effective runtime graph. Those responsibilities belong to subsequent selection and graph-resolution stages.

`ModePresetLoaderFactory::sourceCandidateFor()` describes one namespace-owned source independently of whether its file exists; ConfigSourceLocationBuilder supplies only this candidate to the fingerprint pipeline. Canonical `sourceId` begins `core/kernel:` with precedence `10`; custom `sourceId` begins `application:` with precedence `20`. The candidate has `path`, `filesystemPath`, `sourceId`, `precedence`; candidate inspection does not execute PHP. Canonical shadowing and existing invalid paths fail before fingerprinting.

`ModuleSelectionFactory` combines loaded policy and resolved overrides:

```text
BaseRoots = preset.required ∪ preset.modules
required ∩ exclude -> InvalidModuleSelectionException
roots = (BaseRoots ∪ include) − exclude
excluded = exclude
```

Both outputs are immutable unique `strcmp`-sorted `ModuleId` lists. `ModuleSelectionFactory` has no `ModuleManifest` dependency and does not resolve graph dependencies. Required-module exclusion fails before manifest reading; the selected app target determines overrides but does not alter graph traversal semantics.

## Phase B: installed discovery and pure graph coordination

`ModuleResolutionOrchestrator::resolve(BootstrapConfig)` is the compile-host orchestration entrypoint. It first validates the configured discovery source (only the configured allowed Composer source), resolves and loads the namespace-owned preset, creates `ModuleSelection`, reads `ManifestReaderInterface::read()` **exactly once** into `ModuleManifest`, and invokes:

```php
$plan = $modulePlanResolver->resolve(
    app: $bootstrapConfig->appTarget()->value,
    manifest: $manifest,
    selection: $selection,
);
```

The result is one `ModuleResolution(manifest: $manifest, plan: $plan)` snapshot. The pure `ModulePlanResolver` only coordinates already supplied `app`, `ModuleManifest`, and `ModuleSelection` with `ModuleGraphResolver`; it does not load presets, discover packages, or own observability. Only metadata under installed Composer `extra.coretsia` supplies module descriptors; package-index tooling, Composer package `require`/`conflict` edges and application `config/modules.php` do not select the runtime graph.

### Installed module discovery

The installed discovery boundary consists of `ComposerInstalledMetadataProvider`, `ComposerManifestReader`, and the contracts-level `ManifestReaderInterface`. The only supported discovery source is Composer installed metadata, selected through `kernel.modules.discovery.source = composer` and validated against `kernel.modules.discovery.allowed_sources`.

Discovery MUST NOT scan `packages/**`, `vendor/**` for package classes, package source trees, application directories, or filesystem layout to infer runtime modules. It MUST NOT instantiate module classes or require package filesystem paths to derive module identity.

A Composer package contributes a runtime `ModuleDescriptor` only when `extra.coretsia.moduleId` is present and valid and `extra.coretsia.kind` is exactly `runtime`. Module identity is represented by `Coretsia\Contracts\Module\ModuleId`.

Runtime dependency and conflict edges are read exclusively from `extra.coretsia.requires` and `extra.coretsia.conflicts` and stored in the corresponding `ModuleDescriptor::metadata()` lists. Missing lists are empty. These lists are deterministic module-id sets; they do not inherit Composer package declaration order.

Compile-time container provider declarations are read from `extra.coretsia.providers`. A missing declaration represents an empty list. A present declaration MUST be a list of safe, non-empty provider FQCN strings without a leading backslash, each no longer than 512 bytes. Duplicate provider classes within one declaration are rejected using case-insensitive ASCII class-name identity.

Provider declaration order is semantic and MUST be preserved in `ModuleDescriptor::metadata()['providers']`. Provider FQCNs MUST NOT be alphabetically sorted or normalized as an unordered set. Provider metadata is available to compile-time provider planning through the installed manifest; it is not exported as part of `ModulePlan`.

`ModuleGraphResolver` validates all installed descriptors, including unselected descriptors, **before** graph-policy failure selection. It rejects missing selected roots, expands the transitive dependency closure, rejects excluded dependencies including excluded modules absent from the installed manifest, detects conflicts among enabled modules, and detects cycles. Failure precedence and deterministic candidate ordering are specified in ADR-0025. Topological order puts dependencies first and deterministically breaks ties; it is not a final alphabetical sort.

## Compile-time operation snapshot and consumer boundary

`KernelArtifactOperation` owns the canonical module-resolution invocation for one artifact compile or verify operation. After resolving `BootstrapConfig` and the environment input, it MUST invoke `ModuleResolutionOrchestrator::resolve(BootstrapConfig)` exactly once and retain the resulting `ModuleResolution` for that operation.

The same snapshot MUST be passed to `ConfigSourceLocationBuilder` and then to `ArtifactCompiler` or `CacheVerifier` together with the resulting `ConfigSourceSet`:

```text
one compile / verify operation
    -> one ModuleResolutionOrchestrator::resolve()
    -> one ModuleResolution(manifest, plan)
    -> ConfigSourceLocationBuilder
    -> ArtifactCompiler / CacheVerifier
```

`ArtifactCompiler`, `CacheVerifier`, `ConfigFingerprintInputBuilder`, and `ConfigKernel` MUST consume their supplied operation inputs. They MUST NOT independently invoke module-resolution orchestration, read the installed manifest again, reconstruct `ModuleResolution`, or rediscover module providers or configuration-source locations.

`RuntimeContainerGraphCompiler` MAY pass the already-supplied `ModuleResolution` to `ContainerProviderPlanResolver`; doing so does not create another installed-manifest discovery or module-resolution run.

CLI commands, HTTP runtime code, database runtime code, and other transport or adapter layers MUST NOT invoke compile-host module-resolution services as alternative orchestration entrypoints.

Every downstream consumer belonging to one compile or verify operation MUST use the `ModuleManifest` and `ModulePlan` from that operation's single `ModuleResolution` snapshot. Production runtime container-definition compilation follows ADR-0030.

## ModulePlan contract and artifact boundary

`ModulePlan::SCHEMA_VERSION = 1` and the canonical `toArray()` payload keys are exactly:

```text
app
enabled
excluded
modules
schemaVersion
topologicalOrder
```

`app` is one of `api`, `console`, `web`, `worker`; `enabled` and `excluded` are disjoint unique sorted runtime ModuleId lists. `topologicalOrder` contains each enabled module once in dependency-first order. `modules` is a canonical map of exactly the enabled module entries (with safe `moduleId`, `composerName`, `requires`, and `conflicts` data). The plan contains no source paths, raw preset or Composer payloads, provider class lists, or installed-manifest snapshot.

`ModulePlan` construction MUST reject contradictory resolved graph state. `enabled` and `excluded` MUST be disjoint; `topologicalOrder` MUST contain every enabled module exactly once and MUST NOT reference a non-enabled module. Every enabled module MUST have exactly one corresponding `ModulePlanEntry`, and no entry may represent a non-enabled module.

Each `ModulePlanEntry` exports exactly these keys in this order:

```text
composerName
conflicts
moduleId
requires
```

The `modules` map is keyed by module id and exported in byte-order `strcmp` order. Its `requires` and `conflicts` values are deterministic module-id lists. Every required dependency of an enabled entry MUST have a corresponding enabled entry; an enabled entry MUST NOT conflict with another enabled entry.

`topologicalOrder` preserves dependency order and MUST NOT be alphabetically sorted during `ModulePlan` normalization. Its order must remain independent of filesystem traversal, installed-package ordering, incidental PHP array insertion order, process locale, and operating-system path representation.

`ModulePlan` MUST NOT store or export application or package installation roots, preset source paths, absolute filesystem paths, provider class lists, provider-order indexes, `ContainerProviderPlan`, `ModuleResolution`, raw Composer or configuration payloads, service instances, containers, closures, resources, or filesystem handles.

`ModuleManifestBuilder` serializes this plan as the payload of the **existing** `module-manifest@1` envelope. The envelope `_meta.schemaVersion` remains `1`; `payload.schemaVersion` remains `ModulePlan::SCHEMA_VERSION == 1`. The exact payload key contract is enforced on read, and `ModulePlanArtifactHydrator` creates the immutable runtime `ModulePlan` from the validated artifact. `config@1`, `container@1`, and `artifact-generation@1` identities remain unchanged.

`ModuleResolution` remains compile-host context, allowing `ContainerProviderPlanResolver` to combine installed descriptor provider metadata with the already resolved plan in `ModulePlan::topologicalOrder()`. Neither descriptor provider lists nor the `ModuleManifest` snapshot are runtime seeds.

`ModuleResolution` is an immutable compile-time value containing exactly one installed `ModuleManifest` and its corresponding resolved `ModulePlan`. Its canonical accessors are `manifest(): ModuleManifest` and `plan(): ModulePlan`.

The contained manifest MUST be the same snapshot supplied to `ModuleGraphResolver` when producing the contained plan. `ModuleResolution` MUST NOT introduce a second discovery run, be serialized into an artifact, become part of `ModulePlan`, be retained by runtime services, or enter the compiled runtime container-definition graph.

Only the validated, artifact-hydrated `ModulePlan` crosses the module-resolution boundary into runtime boot. The installed `ModuleManifest` and compile-time provider metadata remain compile-host state.

## Compile-time container provider plan

`Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver` consumes one already-resolved `ModuleResolution` and returns an immutable `ContainerProviderPlan`. It MUST NOT initiate preset loading, module selection, installed-manifest discovery, or graph resolution.

Modules are processed in the exact order supplied by `ModulePlan::topologicalOrder()`. For each enabled module, the resolver retrieves its `ModuleDescriptor` from the manifest in the same `ModuleResolution` snapshot and reads `ModuleDescriptor::metadata()['providers']`.

The resolver MUST preserve the declared provider order within each module. For every provider declaration, it MUST validate the safe FQCN, require the class to exist, require its reflected class name to match the declaration exactly, require it to be instantiable, and require it to implement `ContainerDefinitionProviderInterface`.

A provider class declared more than once across the complete enabled module plan MUST be rejected using case-insensitive ASCII class-name identity.

`ContainerProviderPlan` contains an immutable ordered list of entries with exactly these fields:

```text
moduleId
providerClass
moduleOrder
providerOrder
```

`moduleOrder` is the zero-based index of the module in `ModulePlan::topologicalOrder()`. `providerOrder` is the zero-based index of the provider within its module's declared provider list.

The resulting provider order is:

```text
module topological order
    -> declared provider order within each enabled module
```

The resolver MUST NOT sort providers by FQCN, infer provider order from installed-manifest ordering, instantiate providers while constructing the plan, retain provider instances, or extend `ModulePlan` with provider class lists.

Provider class-validation failures and invalid `ContainerProviderPlan` construction MUST surface through the safe container-definition failure reason `provider-invalid`.

`RuntimeContainerGraphCompiler` consumes this plan during compile-time container-definition compilation. It instantiates providers for that compilation operation, collects their definition sets in provider-plan order, and produces the canonical runtime definition graph. Provider instances and `ContainerProviderPlan` are not artifact payloads or runtime services.

## Observability and failure precedence

`ModuleResolutionOrchestrator`, not `ModulePlanResolver`, owns the single `kernel.modules_resolve` span, `kernel.modules_resolve_total` counter, `kernel.modules_resolve_duration_ms` observation and safe failure logging. Only `operation=resolve` and one bounded `outcome` token are allowed as span/metric attributes. Allowed outcomes: `success`, `preset_not_found`, `preset_invalid`, `selection_invalid`, `manifest_invalid`, `discovery_source_unsupported`, `conflict`, `required_missing`, `cycle`, `unexpected_failure`. Canonical shadowing is `preset_invalid`; selection policy failure is `selection_invalid`. No filesystem paths, raw payloads, module ids or preset names enter span/metric labels; safe ids in logs are validated and sorted. Span finalization occurs exactly once after a successful start, even if attribute updating or stopwatch access fails; observability failure never replaces the primary exception.

The `kernel.modules_resolve` span lifecycle is:

```text
ModuleResolutionOrchestrator::resolve()
    -> attempt span start
    -> perform module resolution
    -> classify the stable outcome
    -> attempt final safe span attributes
    -> attempt span end
    -> return the result or rethrow the primary exception
```

If span start succeeds, the initial attributes contain only `operation = resolve`. Completion attempts final attributes containing `operation = resolve` and the bounded `outcome` token, then attempts to end the span exactly once. Failure to update attributes MUST NOT prevent the attempt to end the span.

`SpanInterface::recordException()` MUST NOT be called at this resolution boundary. Failure classification is represented by the bounded outcome token.

Known `ModuleResolutionException` failures emit their mapped deterministic outcome. Unexpected throwables emit `unexpected_failure` and MUST be rethrown unchanged; they MUST NOT enter the deterministic module-resolution failure logger.

Tracer, meter, logger, and stopwatch failures MUST NOT change the module-resolution result or replace its primary exception. When duration measurement is unavailable, the duration metric uses `0` or is omitted according to the observability policy.

Module-resolution exceptions retain the safe message format:

```text
ERROR_CODE: reason-token
```

Exception messages MUST NOT include context values or previous-throwable messages. Safe logging MAY contain a stable error code, reason token, validated safe preset name, and canonical module ids extracted from documented exception-context fields. Module ids MUST be validated, deduplicated, and sorted by byte-order `strcmp`.

Logs, span attributes, and metric labels MUST NOT expose filesystem paths, raw Composer metadata, raw preset or configuration payloads, graph dumps, exception messages, stack traces, secrets, PII, or environment-specific values. Module ids, preset names, and app targets MUST NOT appear in span attributes or metric labels.

Discovery-source validation precedes preset loading and manifest read. Installed descriptor validation precedes graph-policy failures. Within graph policy ADR-0025 defines excluded dependency, enabled conflict, selected-root missing, dependency missing, and cycle precedence. Failed operations never return a partially resolved plan.

## Provider and factory wiring

`KernelServiceProvider` registers module-resolution, preset-source, and container-provider-planning dependencies as compile-host factories. Factory registration MUST NOT resolve `BootstrapConfig`, `ModuleResolution`, `ModulePlan`, or `ContainerProviderPlan`; load preset files; read Composer installed metadata; scan application directories; load provider classes; instantiate definition providers; or collect container definitions.

Compile-host factory wiring includes `ManifestReaderInterface`, `ComposerManifestReader`, `ModePresetLoaderFactory`, `PresetNamespaceResolver`, `ModuleSelectionFactory`, `ModuleGraphResolver`, `ModulePlanResolver`, `ModuleResolutionOrchestrator`, and `ContainerProviderPlanResolver`.

`FilesystemModePresetLoader` is bound to one selected namespace and the current `BootstrapConfig`. It MUST be created through `ModePresetLoaderFactory::createFor()` during module-resolution orchestration and MUST NOT be registered as a global loader service.

Stateless compile-host resolver and factory services MUST NOT retain operation-specific `BootstrapConfig`, loaded preset objects, installed manifest snapshots, resolved module selections, `ModulePlan`, `ModuleResolution`, `ContainerProviderPlan`, or provider instances.

Per-operation freshness applies to the namespace-bound preset loader, loaded preset policy, effective `ModuleSelection`, installed `ModuleManifest`, resolved `ModulePlan`, `ModuleResolution`, and `ContainerProviderPlan`.

`ContainerProviderPlanResolver` construction performs no module resolution, installed-metadata read, provider class loading, validation, or provider instantiation. Those operations belong to its explicit `resolve(ModuleResolution)` invocation and the subsequent compile-time container-definition compilation boundary.

## Compile-host/runtime boundary

`ModuleResolutionOrchestrator`, `ModulePlanResolver`, `ModuleGraphResolver`, `ModuleSelectionFactory`, `PresetNamespaceResolver`, `ResolvedModuleOverrides`, `ModuleSelection`, preset sources, the preset loader, and Composer metadata discovery remain compile-host-only. They are not runtime graph definitions or runtime seeds. Only an immutable, artifact-hydrated `ModulePlan` (along with the approved runtime config and path seeds) crosses into runtime boot. Worker runtime consumers read that plan without re-running Phase A selection, preset loading or installed discovery.

## Consequences

The preset name remains a resolved bootstrap selection input; the preset source never becomes runtime intent by itself. Per-target overrides are validated at bootstrap, runtime graph selection is explicit, and no file's incidental presence changes namespace ownership. Artifact identity remains stable while its strict payload contract is the one defined above.

## Non-goals

This ADR does not define Composer dependency solving, package installation, package synchronization, or mutation of the installed package set.

It does not define module boot lifecycle, runtime service-provider lifecycle, provider instance construction, execution of provider `define()` methods, collection or merging of provider-produced definition sets, or the internal compilation rules for canonical runtime container definitions. The production container-definition boundary is specified by ADR-0030.

It does not define configuration Phase B merge, artifact writing, CLI command output, HTTP routing or middleware selection, or platform-specific runtime behavior.

It does not introduce application-local module-selection files, automatic runtime module discovery from application or package filesystem layout, or an alternative module-resolution orchestration entrypoint.

It does not introduce new artifact identities or schema versions, runtime preset loading, runtime installed-manifest discovery, or runtime graph recomputation.

## Related SSoT

- `docs/ssot/modes.md`
- `docs/ssot/modules-and-manifests.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/runtime-container-definitions.md`
- `docs/ssot/artifacts.md`
- `docs/ssot/observability.md`

## Related ADRs

- `docs/adr/ADR-0001-module-descriptor-manifest-modepreset-ports.md`
- `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`
- `docs/adr/ADR-0025-kernel-conflicts-exclusion-policy.md`
- `docs/adr/ADR-0028-kernel-artifacts-fingerprint-cache-verify.md`
- `docs/adr/ADR-0029-kernel-container-compile-artifact.md`
- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md`
