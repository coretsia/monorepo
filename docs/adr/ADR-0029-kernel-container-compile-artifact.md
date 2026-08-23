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

# ADR-0029: Kernel compiled container artifact

```yaml
adrVersion: 1
status: pre-accepted
owner: core/kernel
```

## Context

This decision defines the REAL Kernel-owned compiled-container artifact payload for the existing `container@1` artifact identity.

The global artifact registry contains the Kernel-owned `container@1` artifact identity. That registry, the canonical artifact envelope, canonical header fields, and deterministic serialization law are owned by:

```text
docs/ssot/artifacts.md
```

Current Kernel-produced `container@1` artifacts use REAL compiled-container semantics:

```text
kind = compiled
compiled = true
```

The unsupported transitional stub payload shape is invalid for production artifact-only runtime boot:

```text
kind = stub
compiled = false
```

The live payload law and artifact-only runtime boot semantics are owned by:

```text
docs/ssot/compiled-container.md
```

Kernel artifact production, fingerprint behavior, artifact writing, and cache verification linkage remain owned by:

```text
docs/ssot/artifacts-and-fingerprint.md
docs/ssot/cache-verify.md
```

Foundation container ordering, binding collision behavior, tag ordering, and tag dedupe semantics remain Foundation-owned and are referenced by compiled-container semantics rather than redefined here.

The canonical compiled `DefinitionGraph` is also part of Kernel artifact fingerprint identity.

Graph-to-fingerprint construction remains owned by `docs/ssot/artifacts-and-fingerprint.md`; this ADR records the required linkage between the compiled graph, the shared artifact fingerprint, and the REAL `container@1` envelope.

## Decision

### Decision 1: Keep the existing `container@1` artifact identity

This decision keeps the existing artifact identity:

```text
container@1
```

This decision does not introduce:

```text
container@2
```

This decision defines the REAL `container@1` payload schema for the registered `container@1` artifact identity.

A future `container@2` is required only if a later change needs to preserve the REAL `container@1` payload contract while introducing an incompatible compiled-container payload format.

### Decision 2: Reject the unsupported transitional stub payload

The unsupported transitional payload shape is:

```text
kind = stub
compiled = false
```

It is not a supported production runtime container format.

Kernel-produced `container@1` artifacts use REAL compiled-container semantics:

```text
kind = compiled
compiled = true
```

Production artifact-only runtime boot must reject the unsupported transitional stub payload.

### Decision 3: Define the first REAL `container@1` payload schema in SSoT

The first REAL `container@1` payload schema is defined by:

```text
docs/ssot/compiled-container.md
```

This ADR records the architectural decision to introduce that REAL payload.

It does not duplicate the full payload schema.

The SSoT owns:

- top-level payload fields;
- service definition schema;
- service definition lifecycle semantics;
- compiled alias lifecycle semantics;
- parameter bag schema;
- alias schema;
- tag schema;
- closure/callable rejection semantics;
- artifact-only runtime boot inputs;
- missing/invalid artifact failure semantics;
- unsupported stub rejection semantics.

### Decision 4: Compile the production graph from canonical provider definitions

Production compiled-container input is provider-produced, canonical, and closure-free.

The selected production compile model is:

```text
ModuleResolution + compiled Phase-B config
  -> RuntimeContainerGraphCompiler
      -> ContainerProviderPlanResolver
      -> provider instances in ContainerProviderPlan order
      -> one ContainerDefinitionBuilder per provider
      -> one ordered ContainerDefinitionSet per provider
      -> ContainerDefinitionSet::merge(...)
      -> ContainerCompiler
      -> DefinitionGraph
      -> ContainerGraphCompletenessValidator
  -> CompiledContainerBuilder
  -> container@1 artifact envelope
```

After completeness validation, the resulting `DefinitionGraph` has two required consumers:

```text
DefinitionGraph
  -> ContainerGraphFingerprintBucketBuilder
  -> ConfigFingerprintInputBuilder
  -> FingerprintCalculator

DefinitionGraph
  -> CompiledContainerBuilder
  -> container@1 artifact envelope
```

Both consumers must receive the same canonical graph produced for the current operation.

`ArtifactCompiler` and `CacheVerifier` must accept `ModuleResolution` and must not accept a raw descriptor iterable.

`RuntimeContainerGraphCompiler` owns production provider-plan resolution, ordered provider instantiation, definition collection, ordered set merging, low-level graph compilation, and final graph-completeness validation.

Each planned provider is instantiated only at its ordered collection step and contributes through the same `define()` implementation used by source mode.

`ContainerCompiler` remains the low-level deterministic normalizer. Its public input is one ordered `ContainerDefinitionSet`. Conversion through `ContainerDefinitionSet::toDescriptorStream()` is a private normalization detail.

Provider-plan order, merged definition-operation order, and set order are semantically significant.

Neither `RuntimeContainerGraphCompiler` nor `ContainerCompiler` may globally sort providers, modules, definition sets, or definition operations before applying binding collision semantics.

The compiled graph must preserve Foundation-aligned semantics:

- later service binding overrides earlier service binding for the same service id;
- later alias binding overrides earlier alias binding for the same alias;
- later parameter binding overrides earlier parameter binding for the same parameter name;
- tag duplicate handling remains first-wins per `(tag, serviceId)`;
- tag discovery order remains `priority DESC, id ASC`;
- deterministic owner-defined tag metadata survives graph compilation and artifact-only runtime hydration unchanged;
- Kernel transports tag metadata generically and does not interpret owner-specific metadata keys.

Before the graph can be written or used as expected cache state, `ContainerGraphCompletenessValidator` must reject:

- unresolved service references;
- unresolved parameter references;
- unresolved factory-service references;
- aliases that are cyclic or do not terminate in a graph-defined service;
- tagged service ids that do not resolve to graph-defined services;
- unsatisfied required service ids;
- service and alias bindings that use the same id;
- runtime-seed ids defined or shadowed by graph services or aliases;
- compile-host service ids present in runtime graph topology.

The canonical external runtime-seed service-id allowlist is:

```text
Coretsia\Foundation\Container\Container
Psr\Container\ContainerInterface
Coretsia\Foundation\Tag\TagRegistry
Coretsia\Contracts\Config\ConfigRepositoryInterface
Coretsia\Kernel\Module\ModulePlan
Coretsia\Kernel\Runtime\RuntimePathContext
```

Runtime seeds may satisfy service argument references and required-service declarations.

The canonical runtime-seed allowlist contains two ownership categories.

Container-owned runtime support ids are:

```text
Coretsia\Foundation\Container\Container
Psr\Container\ContainerInterface
Coretsia\Foundation\Tag\TagRegistry
```

These instances are materialized by the Foundation runtime container itself.

Entrypoint-owned runtime seed ids are:

```text
Coretsia\Contracts\Config\ConfigRepositoryInterface
Coretsia\Kernel\Module\ModulePlan
Coretsia\Kernel\Runtime\RuntimePathContext
```

These objects are materialized by the artifact-only runtime entrypoint and supplied through an exact immutable `RuntimeContainerSeedSet`.

The explicit law is:

```text
Runtime seeds are entrypoint-owned runtime objects.
They are not provider definitions, artifact payloads, or fingerprint inputs.
```

The statement applies specifically to the entrypoint-owned seed objects. Container-owned support instances remain Foundation container infrastructure.

Runtime seeds must not be alias targets, service-method factory services, or tagged services.

The compiled graph must contain deterministic schema data only.

It must not contain:

- closures;
- anonymous functions;
- callable objects;
- raw PHP callable arrays as runtime payload data;
- object instances;
- resources;
- reflection objects;
- source snippets;
- absolute paths;
- raw env values;
- raw config values;
- secrets;
- timestamps;
- process-specific bytes.

Factory behavior is represented through deterministic schema data such as class references, service ids, method names, service references, parameter references, and scalar/list/map arguments.

Factory behavior is not represented by serialized PHP callables or closures.

The compiled graph must enter fingerprint input through `ContainerGraphFingerprintBucketBuilder`.

The builder hashes the canonical `DefinitionGraph::toArray()` representation through stable JSON encoding and SHA-256, and exposes only safe bounded structural counts.

Provider-plan metadata and provider class names are not added as separate fingerprint fields. Class and factory identities already present in canonical graph service definitions remain covered by the graph hash.

Filesystem paths, runtime instances, provider instances, and runtime seeds must not enter the graph fingerprint bucket.

The exact graph used to calculate the artifact fingerprint must also be passed to `CompiledContainerBuilder`; production compilation must not compile a second graph after fingerprint calculation.

Producer acceptance of an external runtime-seed reference establishes graph completeness only.

Artifact-only runtime boot materializes the entrypoint-owned seed objects through the canonical artifact hydration boundary before compiled graph services are registered.

Runtime seed objects remain outside the compiled graph, generated artifact payloads, and fingerprint input.

### Decision 5: Use one selected artifact generation for production runtime boot

Production runtime boot uses one immutable generation selected from the Kernel artifact root.

The public boot API is:

```php
public function boot(
    ArtifactRuntimeInput $input,
): ContainerInterface;
```

`ArtifactRuntimeInput` contains only:

```text
skeletonRoot
artifactRoot
```

It MUST NOT contain individual artifact paths.

The selected production boot policy is:

```text
ArtifactRuntimeInput
  -> ArtifactGenerationLocator
  -> current
  -> one validated ArtifactGeneration
  -> exact read of module-manifest.php
  -> exact read of config.php
  -> exact read of container.php
  -> exact read of generation-manifest.php
  -> validate generation manifest, bytes, hashes, schemas, and fingerprints
  -> hydrate runtime seeds
  -> build the Foundation container from the already-read container envelope
```

Production runtime boot MUST use:

```text
ArtifactRuntimeBooter
ArtifactRuntimeSeedFactory
CompiledContainerFactory
```

`ArtifactRuntimeSeedFactory` is an internal artifact-runtime helper instantiated by `ArtifactRuntimeBooter`.

It is not a provider-registered runtime service, is not a runtime provider definition, and MUST NOT be added to the compiled graph.

Production runtime boot MUST NOT:

- accept caller-selected `module-manifest.php`, `config.php`, `container.php`, or `generation-manifest.php` paths;
- silently fall back to provider-based container construction;
- compile a new container;
- read source config files;
- run source config discovery;
- run module discovery;
- calculate fingerprints;
- write artifacts;
- mutate or repair finalized generations.

### Decision 6: Artifact-only runtime boot hydrates exact entrypoint-owned runtime seeds

After one generation is selected and validated, artifact-only runtime boot hydrates these exact runtime objects:

```text
config@1 payload.config
  -> ArrayConfigRepository
  -> ConfigRepositoryInterface

module-manifest@1 payload
  -> ModulePlanArtifactHydrator
  -> ModulePlan

ArtifactRuntimeInput
  -> RuntimePathContext
```

The final entrypoint-owned seed set is exactly:

```text
ConfigRepositoryInterface
ModulePlan
RuntimePathContext
```

These objects are carried by:

```text
RuntimeContainerSeedSet
```

The seed set MUST reject:

- missing seed ids;
- additional seed ids;
- arbitrary seed ids;
- list-shaped input;
- non-object values;
- object values that do not satisfy their corresponding service ids.

`CompiledContainerFactory` receives:

```php
public function buildFromEnvelope(
    array $containerEnvelope,
    RuntimeContainerSeedSet $seeds,
): Container;
```

`CompiledContainerFactory` MUST NOT read artifacts from the filesystem.

It receives the already-read `container@1` envelope and the exact runtime seed set.

It obtains the Foundation container config snapshot from the seeded `ConfigRepositoryInterface`.

It MUST NOT receive a separate config payload or container path.

The runtime container construction order is:

```text
ConfigRepositoryInterface seed
  -> ContainerBuilder config snapshot

entrypoint-owned runtime seeds
  -> ContainerBuilder::instance(...)

already-read REAL container@1 envelope
  -> validate compiled payload
  -> register compiled service factories
  -> register compiled aliases
  -> create compiled TagRegistry
  -> ContainerBuilder::build()
```

The compiled graph MUST NOT define or shadow any canonical runtime seed id.

Compiled aliases MUST NOT define or shadow runtime seed ids.

Entrypoint-owned runtime seed instances MUST NOT appear in:

- provider definitions;
- `DefinitionGraph`;
- `container@1`;
- `module-manifest@1`;
- `config@1`;
- graph fingerprint buckets;
- complete fingerprint input.

Absolute runtime paths carried by `ArtifactRuntimeInput` or `RuntimePathContext` MUST NOT be serialized into graph or artifact state.

The caller resolves only the artifact root. `ArtifactRuntimeBooter` owns current-generation selection and generation-specific path resolution.

### Decision 7: Keep provider-based construction outside production artifact-only boot

Provider-based container construction remains allowed only for:

- compile-time artifact production;
- test scaffolding;
- explicitly documented non-production paths.

It is not a production runtime fallback for a missing or invalid selected generation.

Any future developer-mode fallback requires a separate ADR and MUST NOT be implied by this decision.

### Decision 8: Artifact-runtime boot failures are deterministic

The public artifact-runtime failure boundary is:

```text
CORETSIA_ARTIFACT_RUNTIME_BOOT_FAILED: reason-token
```

The stable reasons are:

```text
artifact-runtime-boot-generation-invalid
artifact-runtime-boot-module-manifest-artifact-invalid
artifact-runtime-boot-container-artifact-invalid
artifact-runtime-boot-runtime-container-invalid
```

`artifact-runtime-boot-generation-invalid` covers:

- absent or malformed `current`;
- invalid selected generation paths;
- missing, unreadable, or symlinked generation files;
- invalid generation manifest;
- byte-length or SHA-256 mismatch;
- artifact schema failure before runtime hydration;
- a schema-valid `config@1` payload that cannot hydrate `ArrayConfigRepository`;
- any artifact envelope fingerprint that differs from the selected generation id.

`artifact-runtime-boot-module-manifest-artifact-invalid` covers a schema-valid module manifest that cannot hydrate one canonical `ModulePlan`.

`artifact-runtime-boot-container-artifact-invalid` covers an already-read, generation-valid `container@1` envelope that cannot produce a valid runtime Foundation container.

`artifact-runtime-boot-runtime-container-invalid` covers unexpected failure while assembling the exact runtime seed set.

`CompiledContainerFactory` MAY use `ContainerArtifactInvalidException` internally, but `ArtifactRuntimeBooter` exposes only the public artifact-runtime failure boundary.

Failure diagnostics MUST NOT expose:

- artifact roots or generation paths;
- configured path strings;
- raw artifact bytes or payloads;
- raw config values;
- raw env values;
- PHP warning text;
- OS error messages;
- closure dumps;
- source snippets;
- stack traces;
- throwable messages;
- previous throwable messages.

## Consequences

### Positive consequences

- `container@1` keeps a stable artifact identity while replacing the transitional stub semantics with the first REAL compiled-container payload.
- Production runtime boot becomes explicit and deterministic.
- `ConfigRepositoryInterface` is restored from `config@1` without ConfigKernel Phase B.
- `ModulePlan` is restored from `module-manifest@1` without Composer discovery.
- `RuntimePathContext` is created from explicit entrypoint-owned runtime input.
- Compiled services can resolve all canonical external runtime dependencies without provider fallback.
- Runtime seed objects and absolute runtime paths remain outside graph, artifact, and fingerprint identity.
- A missing or invalid selected generation fails hard instead of silently switching to a different runtime construction mode.
- Production graph input is collected automatically from enabled declarative module providers through one `ModuleResolution` snapshot instead of being supplied manually as a descriptor stream.
- Artifact compilation and cache verification use the same production runtime-graph compiler.
- The canonical production runtime graph is included in the shared Kernel artifact fingerprint.
- The same `DefinitionGraph` determines both artifact fingerprint identity and the REAL `container@1` payload.
- Semantic graph changes cannot leave `module-manifest.php`, `config.php`, or `container.php` falsely fingerprint-clean.
- Repeated compilation of the same canonical graph produces the same graph bucket and fingerprint.
- Incomplete runtime graphs fail before artifact write or expected-artifact comparison.
- Service definitions, aliases, parameters, and tags are represented as artifact schema data.
- The compiled container can be validated by schema semantics rather than PHP object identity.
- The compiled container remains compatible with the global artifact envelope and registry law.
- The REAL payload law is centralized in `docs/ssot/compiled-container.md`.

### Trade-offs

- Production runtime boot requires one complete valid selected generation containing all four generation files.
- The runtime entrypoint must supply explicit `ArtifactRuntimeInput` with one artifact root.
- A cold cache without a valid `current` selection is not a valid production runtime boot state.
- Provider-based fallback is intentionally unavailable in production artifact-only boot.
- Developer-mode fallback, if ever needed, must be designed explicitly in a separate ADR.
- Every enabled provider selected for production graph compilation must implement `ContainerDefinitionProviderInterface` and produce definitions that pass final graph-completeness validation.
- Descriptor export remains necessary inside the low-level normalizer, but it is no longer a production caller responsibility.
- Runtime closures and raw PHP callable arrays cannot cross into the compiled graph.
- Any semantic graph change invalidates the complete Kernel-owned artifact set through the shared graph-bound fingerprint.
- Changes to canonical graph serialization or graph-bucket schema intentionally change fingerprint identity and require corresponding golden-hash updates.

### Operational consequences

Artifact production must run before production artifact-only runtime boot.

Cache verification reports each expected generation file with status `clean`, `dirty`, or `invalid`. When `current` is absent, every expected file is reported as `dirty` with reason `missing`. Verification itself must not repair or write artifacts.

Artifact production writes artifacts.

Artifact-only runtime boot reads artifacts.

Those responsibilities are intentionally separate.

## Rejected Alternatives

### Alternative 1: Introduce `container@2` immediately

Rejected.

The earlier `container@1` form was a transitional stub payload under the already-registered artifact identity, not a stable production compiled-container contract.

Introducing `container@2` for the first REAL compiled-container payload would incorrectly treat the transitional stub as if it were a stable production payload contract.

The selected design keeps `container@1` and defines its first REAL production payload schema under that identity.

### Alternative 2: Preserve the legacy stub payload as a supported production runtime format

Rejected.

The stub payload cannot build a real runtime container from compiled service definitions, aliases, parameters, and tags.

Keeping it as a supported production runtime format would make `container@1` ambiguous and would weaken artifact-only boot semantics.

The selected design treats the stub as transitional and unsupported for production runtime boot.

### Alternative 3: Serialize provider closures or PHP callables into the artifact

Rejected.

Closures, anonymous functions, callable objects, raw callable arrays, reflection metadata, source snippets, and runtime callable payloads are not deterministic compiled-container schema data.

They are not suitable for stable artifact bytes, schema validation, or safe diagnostics.

The selected design uses provider-produced canonical definition sets. Descriptor export remains an internal closure-free normalization detail of `ContainerCompiler`.

### Alternative 4: Re-run providers as a production runtime fallback

Rejected.

Provider fallback would make production runtime behavior depend on non-artifact source state and would blur the boundary between compile-time artifact production and production runtime boot.

The selected design requires production boot to use the compiled artifact and fail deterministically when the artifact is missing or invalid.

### Alternative 5: Let `CompiledContainerFactory` read source config files

Rejected.

Runtime config snapshot input is `config@1`.

Reading source config files during compiled-container runtime boot would violate artifact-only boot and duplicate config compilation responsibilities.

The selected design requires the artifact-runtime facade to read and validate `config@1`, hydrate `ConfigRepositoryInterface`, and include that repository in the exact runtime seed set.

`CompiledContainerFactory` receives the seed set and uses the seeded repository as the Foundation container config snapshot without running ConfigKernel Phase B.

## Validation and Testing Expectations

This decision should be locked by tests covering:

- identical compiled-container inputs produce identical `container.php` bytes;
- REAL `container@1` payload uses `kind = compiled` and `compiled = true`;
- legacy `kind = stub`, `compiled = false` payloads are rejected for production runtime boot;
- absent or invalid current generation fails with `artifact-runtime-boot-generation-invalid`;
- semantic module-manifest hydration failure fails with `artifact-runtime-boot-module-manifest-artifact-invalid`;
- invalid compiled-container hydration fails with `artifact-runtime-boot-container-artifact-invalid`;
- `CompiledContainerFactory` builds a runtime Foundation container from an already-read REAL `container@1` envelope and an exact `RuntimeContainerSeedSet`;
- `RuntimeContainerSeedSet` rejects missing, additional, arbitrary, and type-invalid seeds;
- compiled services resolve `ConfigRepositoryInterface`, `ModulePlan`, and `RuntimePathContext`;
- runtime graph services and aliases cannot define or shadow runtime seed ids;
- `ModulePlanArtifactHydrator` rejects invalid dependency closure, enabled conflicts, cycles, and non-canonical topological order;
- `ModulePlan` hydration does not read Composer metadata;
- `ConfigRepositoryInterface` hydration does not run ConfigKernel Phase B;
- runtime seed objects and absolute runtime paths do not enter artifacts or fingerprint input;
- production artifact boot accepts only an artifact root and selects `current` through `ArtifactGenerationLocator`;
- production artifact boot reads all four files from one selected generation;
- the public boot API cannot accept mixed-generation artifact paths;
- runtime boot does not read source config files;
- runtime boot does not run module discovery;
- runtime boot does not run provider fallback;
- runtime boot does not compile a new container;
- at least one canonical end-to-end integration acceptance test uses a
  package-shaped fixture that declares a module dependency,
  `defaultsConfigPath`, a container-definition provider, a validated
  ruleset-owned config root, and a shared `ResetInterface` service; the test
  starts from `BootstrapInput`, performs Composer-backed module resolution,
  compiles package defaults and rules plus provider-produced runtime
  definitions, publishes and clean-verifies one immutable generation, boots
  that generation through `ArtifactRuntimeBooter`, resolves the fixture
  service from the compiled container, and proves reset isolation across two
  sequential KernelRuntime UnitOfWork lifecycles without source-config,
  mode-resolution, provider-execution, or container-compilation fallback;
- production compilation resolves enabled providers from one `ModuleResolution`;
- provider definitions are collected in exact provider-plan order and merged without re-sorting;
- `ArtifactCompiler` and `CacheVerifier` use `RuntimeContainerGraphCompiler` and expose no raw descriptor iterable;
- `ArtifactCompiler` and `CacheVerifier` include the graph produced by `RuntimeContainerGraphCompiler` in fingerprint input;
- compiler and verifier produce the same graph-bound fingerprint for the same resolved inputs;
- repeated canonical graph compilation produces the same graph SHA-256 and artifact fingerprint;
- the exact graph used for fingerprint construction is also used to build the expected REAL `container@1` envelope;
- service class changes change the fingerprint;
- factory class changes change the fingerprint;
- factory method changes change the fingerprint;
- service reference changes change the fingerprint;
- parameter changes change the fingerprint;
- alias target changes change the fingerprint;
- effective tag priority changes change the fingerprint;
- deterministic tag metadata changes change the fingerprint;
- artifact-only runtime `TagRegistry` preserves deterministic owner-defined tag metadata without provider fallback;
- shared lifecycle flag changes change the fingerprint;
- incomplete graphs fail before artifact write or expected-artifact comparison;
- canonical definition input rejects closures and callable payloads before artifact write;
- unresolved service, parameter, factory-service, alias, tag, and required-service edges fail deterministically;
- runtime seed overrides and compile-host leakage fail before artifact write;
- compiled aliases remain non-shared delegation wrappers;
- service definition `shared` lifecycle is preserved;
- Foundation tag ordering and first-wins dedupe are preserved.

## Related SSoT

- `docs/ssot/compiled-container.md`
- `docs/ssot/runtime-container-definitions.md`
- `docs/ssot/artifacts.md`
- `docs/ssot/artifacts-and-fingerprint.md`
- `docs/ssot/cache-verify.md`
- `docs/ssot/observability.md`
- `docs/ssot/di-tags-and-middleware-ordering.md`
- `docs/ssot/reset-tags.md`

## Related ADR

- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/adr/ADR-0019-enhanced-reset-long-running.md`
- `docs/adr/ADR-0020-kernel-runtime-uow-spi.md`
- `docs/adr/ADR-0028-kernel-artifacts-fingerprint-cache-verify.md`
- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md`
