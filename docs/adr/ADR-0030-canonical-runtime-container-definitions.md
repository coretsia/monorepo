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

# ADR-0030: Canonical Runtime Container Definitions

```yaml
adrVersion: 1
status: pre-accepted
owner: core/foundation
```

## Context

Coretsia needs one deterministic representation of runtime DI wiring that can be produced once and consumed by more than one execution path.

The relevant paths are:

```text
source runtime
Kernel container compilation
artifact-only runtime boot
```

Before this decision, Foundation source providers could register runtime definitions imperatively through `ContainerBuilder`, while the Kernel container compiler consumed a separate descriptor-oriented input model.

Keeping those models independent would allow the source runtime and compiled runtime to drift in:

- service construction semantics;
- factory semantics;
- alias lifecycle behavior;
- parameter override behavior;
- tag dedupe behavior;
- provider ordering;
- shared and non-shared lifecycle behavior;
- accepted argument value shapes;
- validation and failure semantics.

The canonical model must be available below Kernel so Foundation source providers do not depend on `core/kernel`.

The model must not be moved to `core/contracts`, because it is not a technology-neutral framework port. It is coupled to Foundation DI semantics, including `ContainerBuilder`, service-definition lifecycle, tag registration, collision behavior, and source-runtime application.

The model must also remain distinct from the Kernel-owned `container@1` artifact schema.

The relevant live policies are owned by:

```text
docs/ssot/runtime-container-definitions.md
docs/ssot/di-tags-and-middleware-ordering.md
docs/ssot/compiled-container.md
```

The first document owns the canonical Foundation in-memory definition model.

The DI tags SSoT continues to own Foundation runtime tag discovery, first-wins tag dedupe, discovery ordering, definition lifecycle, and related consumer obligations.

The compiled-container SSoT continues to own the Kernel `container@1` payload and artifact-only boot semantics.

## Decision

### Decision 1: `core/foundation` owns the canonical model

Coretsia defines a Foundation-owned declarative runtime container-definition model under:

```text
framework/packages/core/foundation/src/Container/Definition/
```

The canonical public model consists of:

```text
Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface
Coretsia\Foundation\Container\Definition\ContainerDefinitionContext
Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder
Coretsia\Foundation\Container\Definition\ContainerDefinitionSet
Coretsia\Foundation\Container\Definition\ContainerDefinitionKind
Coretsia\Foundation\Container\Definition\ContainerServiceDefinition
Coretsia\Foundation\Container\Definition\ContainerValueReference
```

The source-runtime adapter is:

```text
Coretsia\Foundation\Container\Definition\ContainerDefinitionApplier
```

The model is Foundation-owned because it describes Foundation runtime DI behavior.

It is not owned by `core/contracts`.

It is not owned by `core/kernel`.

### Decision 2: The model is canonical in-memory source data, not an artifact schema

`ContainerDefinitionSet` is the canonical immutable in-memory result of one or more declarative provider contributions.

It is not:

- an artifact envelope;
- a `container@1` payload;
- a generated PHP artifact;
- a serialized runtime container;
- a runtime service graph instance;
- a stable promise that every exported descriptor field is itself an artifact field.

The active source-runtime flow is:

```text
ContainerDefinitionProviderInterface
  -> ContainerDefinitionBuilder
  -> ContainerDefinitionSet
  -> ContainerDefinitionApplier
  -> ContainerBuilder
  -> Container
```

When compilation orchestration selects provider-produced definitions, the required compilation flow is:

```text
ContainerDefinitionSet::toDescriptorStream()
  -> Kernel container compiler
  -> Kernel compiled graph
  -> container@1 artifact
```

The production artifact compiler does not consume provider-produced definition sets.

Production artifact compilation continues to use its currently approved input path.

Complete provider-produced definition sets become compiler input only when compilation orchestration explicitly selects them.

Artifact-only runtime boot consumes the currently approved compiled artifact.

It does not execute definition providers or consume the source model as a production fallback.

### Decision 3: Use a closure-free definition-provider SPI

The canonical provider SPI is:

```php
interface ContainerDefinitionProviderInterface
{
    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void;
}
```

A definition provider must be deterministic for the same provider state and already-compiled config snapshot.

A definition provider must not:

- return closures;
- return runtime objects;
- read filesystem sources;
- read environment sources;
- read generated artifacts;
- resolve services;
- start runtime lifecycle;
- instantiate runtime services;
- emit stdout or stderr.

The closure prohibition applies to:

- provider output;
- canonical definition operations;
- canonical value references;
- descriptor streams;
- compiler input derived from those streams.

It does not prohibit a runtime service from producing an execution closure during normal runtime behavior.

Worker runtime factories and services may create execution callbacks during runtime service construction or execution, after canonical definition production has completed.

Examples include:

```text
PcntlWorkerManagerDriver child runner
package-internal task-work run callback
```

They must never be embedded into provider output, canonical definition operations, canonical definition values, descriptor streams, compiled graphs, generated artifacts, or fingerprint input.

Source-mode orchestration consumes provider-owned definitions through the declarative SPI.

Any compilation orchestration that selects provider-produced definition sets must invoke the same provider `define()` implementation.

Foundation, Kernel, and Worker source-runtime wiring use provider-owned definitions through that SPI.

Providers that do not implement the declarative SPI remain imperative and are outside this decision.

### Decision 4: Limit provider context to compiled Phase-B config

`ContainerDefinitionContext` contains only an already-compiled Phase-B config snapshot.

It must not expose:

- `BootstrapConfig`;
- env repositories;
- filesystem paths;
- source config locations;
- generated artifacts;
- a container instance;
- runtime services.

`RuntimePathContext` is not part of `ContainerDefinitionContext`.

Absolute skeleton or artifact roots must not be passed through definition context config roots, parameters, literal values, provider state, or descriptor fields.

They are supplied separately as runtime seeds by source-mode or artifact-mode boot orchestration.

`configRoot()` provides fail-closed access to a string-keyed config-root map.

The context validates the shape of the supplied snapshot. The orchestration layer remains responsible for supplying an actual Phase-B compiled config snapshot.

### Decision 5: Use an ordered operation stream

The canonical operation kinds are:

```text
service.class
service.factory.class-method
service.factory.service-method
alias
parameter
tag
```

Operation order is semantic and must be preserved exactly.

The model must not globally sort service, alias, parameter, or tag operations before applying collision and dedupe behavior.

The selected semantics are:

- later service definition wins for the same service id;
- later alias definition wins for the same alias id;
- later parameter definition wins for the same parameter name;
- first tag registration wins for the same `(tag, serviceId)` pair;
- source-registration provider order remains caller-supplied and significant;
- compile-time provider order remains orchestration-supplied and significant;
- neither order may be inferred by sorting provider FQCNs.

`requiredServiceIds()` is not part of operation ordering. It is exported as a deduplicated `strcmp`-sorted set.

### Decision 6: Use deterministic values and typed references

Definition arguments, parameters, and tag metadata may contain only bounded deterministic scalar/list/map data accepted by the Foundation definition policy.

The model rejects:

- floats;
- closures;
- arbitrary objects;
- callable objects;
- resources;
- reflection objects;
- invalid UTF-8 strings;
- source snippets;
- absolute paths;
- env references;
- sensitive-looking raw values.

Runtime argument references use the typed Foundation value object:

```text
ContainerValueReference::service(...)
ContainerValueReference::parameter(...)
ContainerValueReference::class(...)
```

Their canonical exported shapes are:

```php
['id' => '<service-id>', 'type' => 'service']
['name' => '<parameter-name>', 'type' => 'parameter']
['class' => '<class-name>', 'type' => 'class']
```

Callable-shaped string lists remain ordinary deterministic list data. The declarative model never executes such lists as PHP callables.

### Decision 7: Preserve Foundation lifecycle and collision semantics

Service definitions are shared by default:

```text
shared = true
```

A service definition may explicitly use:

```text
shared = false
```

The source adapter must preserve the selected lifecycle when registering the runtime factory through `ContainerBuilder`.

Aliases are non-shared delegation wrappers.

An alias must not add an independent cache that changes the lifecycle of its target service.

Therefore:

- an alias to a shared target resolves the shared target instance;
- an alias to a non-shared target preserves repeated target resolution;
- alias registration does not make a non-shared target shared.

Tag registration continues to delegate to `TagRegistry`, where first registration wins for the same `(tag, serviceId)` pair.

### Decision 8: Aggregate declarative providers before one source application

`ContainerBuilder` exposes:

```php
public function register(
    ServiceProviderInterface ...$providers,
): self;

public function registerProviders(
    iterable $providers,
): self;

public function applyDefinitions(
    ContainerDefinitionSet $definitions,
): self;
```

A declarative provider-registration batch must contain only providers that implement:

```text
ContainerDefinitionProviderInterface
```

An imperative-only batch must contain only providers that do not implement that SPI.

A mixed declarative and imperative batch is rejected before any provider executes because deferred declarative application cannot preserve caller order relative to immediate imperative mutations.

For one declarative batch, `ContainerBuilder` must:

1. preserve the exact caller-supplied provider order;
2. create one shared `ContainerDefinitionBuilder`;
3. create one shared `ContainerDefinitionContext`;
4. invoke every provider through its normal `ServiceProviderInterface::register()` entrypoint;
5. let each declarative provider contribute through `registerDefinitionProvider($this)`;
6. invoke every provider `define()` against the same shared builder and context;
7. call `build()` exactly once;
8. call `applyDefinitions()` exactly once.

The selected source flow is:

```text
provider 1 register() -> define()
provider 2 register() -> define()
provider 3 register() -> define()
  -> one shared ContainerDefinitionBuilder
  -> one ContainerDefinitionSet
  -> one ContainerBuilder::applyDefinitions(...)
```

The following flow is forbidden:

```text
provider 1 -> build -> apply
provider 2 -> build -> apply
provider 3 -> build -> apply
```

A standalone declarative provider may apply one complete definition set when no declarative set has already been applied.

A second standalone or batched declarative application on the same `ContainerBuilder` must fail before provider execution or compile-host registration mutates the builder.

`ContainerDefinitionSet::merge(...)` remains available when orchestration already owns separate immutable sets, but source provider registration should normally contribute through one shared builder.

### Decision 9: Keep source-container factory closures inside the source adapter

`ContainerDefinitionApplier` may create source-container factory closures internally when adapting class and factory definitions to `ContainerBuilder::factory(...)`.

Those adapter-created closures are source-runtime implementation details.

They must never enter:

- `ContainerServiceDefinition`;
- `ContainerDefinitionSet`;
- descriptor streams;
- Kernel compiled graphs;
- generated artifacts.

Runtime factories and services may separately create execution callbacks during runtime service construction or execution, after canonical definition production has completed.

Such callbacks are outside the canonical definition model and are subject to the same non-serialization boundary.

### Decision 10: Use safe deterministic definition failures

Invalid declarative definitions use:

```text
Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException
```

The stable error code and public message token are:

```text
CORETSIA_CONTAINER_DEFINITION_INVALID
container-definition-invalid
```

The bounded reasons are:

```text
definition-invalid
reference-invalid
provider-invalid
required-service-invalid
```

Public definition-validation diagnostics must not contain raw service ids, class names, method names, argument values, config values, filesystem paths, source snippets, env values, secrets, or previous throwable messages.

Runtime source-adapter failures use safe `ContainerException` reason tokens and may retain the causal throwable through `previous` without embedding its message in the public reason token.

### Decision 11: Keep the production artifact path unchanged

The canonical definition model and source adapter are active for source-runtime wiring.

Foundation, Kernel, and Worker source-runtime wiring uses provider-owned canonical definitions.

Production artifact compilation continues to use its existing input path and does not consume provider-produced `ContainerDefinitionSet` input.

Providers that do not implement `ContainerDefinitionProviderInterface` may remain imperative.

Production artifact generation and artifact-only runtime boot remain governed by their existing compiled-container contracts.

The canonical model is not a second production runtime boot path.

Artifact-only runtime boot must not execute declarative providers as a fallback when the compiled container artifact is missing or invalid.

No new Composer dependency is required.

### Decision 12: Foundation, Kernel, and Worker providers own their runtime definitions

The following providers MUST use their `define()` methods as their only runtime wiring sources:

```text
Coretsia\Foundation\Provider\FoundationServiceProvider
Coretsia\Kernel\Provider\KernelServiceProvider
Coretsia\Platform\Worker\Provider\WorkerServiceProvider
```

Their `register()` methods must not maintain parallel imperative copies of definitions contributed by `define()`.

Source registration delegates through:

```php
$builder->registerDefinitionProvider($this);
```

No parallel descriptor-provider classes may duplicate these provider contributions.

The following duplicate sources are forbidden:

```text
FoundationContainerDescriptorProvider
KernelContainerDescriptorProvider
WorkerContainerDescriptorProvider
```

#### Foundation contribution

`FoundationServiceProvider::define()` remains the canonical source for Foundation runtime services, aliases, parameters, and runtime tags defined by this ADR.

#### Kernel contribution

`KernelServiceProvider::define()` remains the canonical source for:

```text
RuntimeEntrypointGuard
HookInvoker
KernelRuntime
KernelRuntimeInterface alias
```

Kernel compile-host services remain outside this runtime contribution.

#### Worker contribution

`WorkerServiceProvider::define()` is the canonical source for:

```text
WorkerServiceFactory
WorkerPoolSpec
WorkerRuntimeEntrypointGuard
StableJsonEncoder
StableJsonDecoder
WorkerStateStore
WorkerSocketServer
QueueTaskFactory
HttpTaskFactory
TaskFactoryInternalInterface
ApplicationWorker
PcntlWorkerManagerDriver
ProcWorkerManagerDriver
WorkerManager
ContainerWorkerManagerResolver
WorkerManagerResolverInterface alias
WorkerStartCommand
WorkerStopCommand
WorkerStatusCommand
cli.command tags
```

The Worker contribution declares:

```text
ConfigRepositoryInterface
ModulePlan
RuntimePathContext
WorkerPoolSpec
WorkerRuntimeEntrypointGuard
ApplicationWorker
WorkerManager
QueueTaskFactory
HttpTaskFactory
```

as required runtime service ids.

The following may be external runtime seeds:

```text
ConfigRepositoryInterface
ModulePlan
RuntimePathContext
```

The following must be provided by the complete definition graph:

```text
WorkerPoolSpec
WorkerRuntimeEntrypointGuard
ApplicationWorker
WorkerManager
QueueTaskFactory
HttpTaskFactory
```

`QueueTaskFactory` and `HttpTaskFactory` are required because `WorkerServiceFactory::taskFactory(...)` resolves the selected service internally through `ContainerInterface`.

`WorkerManager` is required because `ContainerWorkerManagerResolver::resolve()` performs a deferred lookup of `WorkerManager::class` through `ContainerInterface`.

`RequestHandlerInterface` is not an unconditional required-service id because it is a mode-dependent HTTP preflight dependency whose absence is intentionally handled at Worker runtime startup.

Runtime construction may use:

```text
public static class-method factories
service-method factories
class constructors
aliases
tags
```

Every canonical service id resolved through `ContainerInterface` as a mandatory or possible edge of the container-owned runtime graph must have a matching `requireService()` declaration.

A mode-dependent preflight dependency that is intentionally allowed to be absent is not an unconditional required service id and must fail only at its approved runtime boundary.

Runtime factories must not rerun Bootstrap Phase A, rerun Phase B config compilation, read source config files, or infer hidden runtime seeds.

### Decision 13: Kernel compile-host wiring and runtime seeds remain outside the runtime definition graph

Kernel service-provider wiring is divided into two categories.

`KernelServiceProvider::register()` may register source-host services needed to perform bootstrap, configuration compilation, module planning, artifact compilation, fingerprinting, cache verification, artifact IO, and container compilation.

Those services include:

```text
Bootstrap Phase A services
dotenv loaders
Composer metadata readers
ModulePlanResolver
ContainerProviderPlanResolver
ConfigKernel
artifact builders
ArtifactCompiler
fingerprint calculators and explainers
CacheVerifier
artifact readers and writers
ContainerCompiler
```

`KernelServiceProvider::register()` may also register a source-host factory for:

```text
RuntimePathContext
```

The factory constructs `RuntimePathContext` from an already-resolved `BootstrapConfig`.

This factory is source-host wiring, not a Kernel runtime definition contribution.

`RuntimePathContext`:

- may contain normalized absolute runtime paths;
- is not a Bootstrap Phase A result;
- is not part of `ContainerDefinitionContext`;
- must not be represented as a literal, parameter, or runtime-object definition value;
- must not serialize its runtime object or path values into descriptors or generated artifact payload values;
- must not contribute its runtime path values to fingerprint input;
- must not be contributed as a service definition by `KernelServiceProvider::define()`;
- must not be resolved from `BootstrapConfig` by Worker definitions.

Its canonical service id may appear in required-service declarations and typed service references.

Artifact representation of external runtime-seed service ids remains owned by the compiled-container SSoT.

Source mode constructs it from already-resolved Phase A values.

Artifact mode constructs it from explicit runtime input.

The complete Worker runtime graph consumes `RuntimePathContext` as an external runtime seed and must not depend on `BootstrapConfig`.

`KernelServiceProvider::define()` must not contribute compile-host services or the source-host `RuntimePathContext` factory to the canonical runtime definition graph.

The explicit law is:

```text
Kernel compile-host services are not part of the compiled runtime
container definition graph.
```

Only Kernel runtime services belong in the Kernel provider contribution.

Compile-host services may produce or validate runtime artifacts, but they are not runtime graph definitions and must not appear in the provider-produced runtime descriptor stream.

### Decision 14: Compile-time provider planning consumes one ModuleResolution

Kernel compile-time provider planning uses:

```text
Coretsia\Kernel\Module\ModuleResolution
Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver
Coretsia\Kernel\Container\Provider\ContainerProviderPlan
```

`ModuleResolution` contains the installed `ModuleManifest` and resolved `ModulePlan` produced by one module-resolution run.

`ContainerProviderPlanResolver` MUST consume that value directly.

At the current integration state, `ContainerProviderPlanResolver` is implemented and registered as a Kernel compile-host service.

No current production artifact-compilation path invokes it to collect provider-produced `ContainerDefinitionSet` values.

It MUST NOT:

- read Composer installed metadata independently;
- invoke `ManifestReaderInterface::read()`;
- rerun module graph resolution;
- add provider class lists to `ModulePlan`;
- infer provider order from FQCN sorting;
- create provider instances;
- invoke provider `define()` methods.

The provider plan order is:

```text
ModulePlan::topologicalOrder()
    -> ModuleDescriptor::metadata()['providers'] declaration order
```

Each immutable plan entry contains:

```text
moduleId
providerClass
moduleOrder
providerOrder
```

The plan stores class names only.

It never stores provider instances.

The resolver validates:

- provider FQCN shape;
- provider FQCN length of at most 512 bytes;
- class existence;
- exact reflected class identity;
- class instantiability;
- `ContainerDefinitionProviderInterface` implementation;
- case-insensitive global provider-class uniqueness across enabled modules.

`ModuleResolution` and `ContainerProviderPlan` are Kernel compile-time values.

They are not:

- canonical definition operations;
- runtime services;
- runtime seed values;
- generated artifact payloads;
- fingerprint inputs.

Provider planning and provider definition collection are separate stages.

The existence of a valid `ContainerProviderPlan` does not mean that production artifact compilation currently consumes provider-produced `ContainerDefinitionSet` values.

When compilation orchestration explicitly collects provider-produced definitions, it MUST:

1. use provider classes in `ContainerProviderPlan` order;
2. instantiate no provider before its ordered collection step;
3. invoke the same `define()` implementations used by source mode;
4. preserve one shared definition builder and one final definition set;
5. avoid any second Composer manifest read.

## Consequences

### Positive consequences

- Source runtime wiring uses one active canonical semantic model, while provider-produced definitions remain eligible compiler input only when compilation orchestration explicitly selects them.
- Foundation, Kernel, and Worker runtime wiring no longer have parallel imperative and declarative sources.
- Existing Foundation, Kernel, and Worker service providers are the canonical definition providers.
- Kernel compile-host wiring remains explicitly separated from runtime graph wiring.
- Foundation remains independent from Kernel.
- Framework-wide contracts remain free from Foundation-specific DI semantics.
- Provider order and operation order remain explicit and testable.
- Source and compiled paths can share service, factory, alias, parameter, tag, and lifecycle semantics.
- Source-container factory closures remain isolated to the Foundation adapter, while runtime execution callbacks remain runtime construction or execution behavior outside the canonical definition model.
- Definition sets can be revalidated independently of their producer.
- Exact typed references replace ambiguous strings or runtime object references.
- Deterministic limits make hostile or accidental oversized definition state fail closed.
- Kernel compilation can consume a Foundation-owned descriptor stream without Foundation depending on Kernel.

### Trade-offs

- Declarative providers are more constrained than imperative runtime providers.
- Definition strings and map keys use a deliberately narrow safety policy.
- Factory class-method definitions require a public non-abstract static method to exist at definition time.
- Service-method factory existence and compatibility cannot be fully validated until final graph or runtime resolution.
- All provider contributions must be collected before one source application.
- Required runtime service ids are collected but are not validated by the source adapter.
- Factories that resolve services through `ContainerInterface` must keep matching `requireService()` declarations synchronized with their lookup behavior.
- `KernelServiceProvider::register()` owns both compile-host registration and delegation of the Kernel runtime contribution, so that boundary must remain explicit in code and tests.
- Declarative and imperative-only providers cannot be mixed in one registration batch.
- The canonical model becomes a production compile input only when compilation orchestration explicitly consumes complete provider-produced definition sets.

### Operational consequences

Source-mode orchestration should register the declarative Foundation, Kernel, and Worker providers in one batch:

```php
$builder->register(
    new FoundationServiceProvider(),
    new KernelServiceProvider(),
    new WorkerServiceProvider(),
);
```

`ContainerBuilder` owns creation of the shared definition builder and definition context for that batch.

In source registration, provider order remains caller-supplied and significant.

When module-aware compile-time composition explicitly invokes provider planning, provider order is supplied by `ContainerProviderPlan` and is significant.

Foundation, Kernel, and Worker contributions are built into one complete definition set and applied once.

Kernel compile-host factories are registered by `KernelServiceProvider::register()` but do not enter the canonical runtime descriptor stream.

Compilation orchestration that selects provider-produced definitions MUST consume the same `define()` contributions used by source mode.

The current boundary is:

- Worker source mode consumes the Worker provider contribution;
- Worker definitions are closure-free and valid as provider-produced compiler input;
- Kernel exposes an available provider-planning capability through compile-host wiring;
- when provider-plan resolution is explicitly invoked, it may include `WorkerServiceProvider` when that provider is declared by an enabled module;
- provider-plan resolution does not instantiate Worker providers or collect Worker definitions;
- no current production artifact-compilation path invokes provider-plan resolution to collect Worker definitions;
- production artifact compilation continues to use its currently approved input path and does not consume complete provider-produced definition sets;
- documentation and tests must distinguish the available provider-planning capability from production provider-definition compilation, which is not currently active.

Artifact-only runtime boot must consume the compiled artifact and must not run definition providers as a fallback.

## Rejected Alternatives

### Alternative 1: Put the model in `core/contracts`

Rejected.

The model is coupled to Foundation container APIs, collision semantics, lifecycle semantics, tag behavior, and source-runtime application.

It is not a technology-neutral port.

### Alternative 2: Put the model in `core/kernel`

Rejected.

Foundation source providers would need to depend upward on Kernel merely to describe Foundation DI wiring.

That would invert package boundaries.

### Alternative 3: Keep independent source and compile definition models

Rejected.

Independent models would allow source/runtime drift in lifecycle, alias, parameter, tag, ordering, value, and validation semantics.

### Alternative 4: Store closures or raw PHP callables in the canonical model

Rejected.

Runtime callables are not stable deterministic schema data and cannot safely cross into compiled graphs or artifacts.

The selected design represents factories through class/service identity, method names, typed references, and deterministic argument data.

### Alternative 5: Apply one definition set per provider

Rejected.

Per-provider application would make parameter-reference resolution depend on grouping and application timing.

The selected design aggregates all contributions before one source application.

### Alternative 6: Globally sort operations before application

Rejected.

Operation order carries provider order, later-binding behavior, and first-tag-registration behavior.

Sorting would change semantics rather than merely canonicalize representation.

### Alternative 7: Use provider-produced definitions as the active production artifact input

Rejected.

The canonical model must preserve source-runtime semantics independently of production artifact orchestration.

Production compilation continues to use its existing input path until an explicit architecture decision assigns complete provider-produced definition sets as its input.

## Validation and Testing Expectations

This decision should be locked by tests covering:

- runtime objects, closures, resources, and floats are rejected;
- malformed and raw reference maps are rejected at the correct boundary;
- identical definitions produce identical descriptor streams;
- nested deterministic maps are `strcmp`-sorted;
- operation order is preserved;
- Composer-declared provider order is preserved;
- provider order is not derived from FQCN sorting;
- module order equals `ModulePlan::topologicalOrder()`;
- provider planning consumes the same manifest/plan resolution snapshot;
- provider-plan resolution creates no provider instances;
- duplicate provider classes fail deterministically;
- classes that do not implement `ContainerDefinitionProviderInterface` fail deterministically;
- required service ids are deduplicated and `strcmp`-sorted;
- later service, alias, and parameter definitions win;
- first duplicate tag registration wins;
- shared definitions are cached;
- non-shared definitions are resolved repeatedly;
- aliases preserve target lifecycle;
- class-method factories require public non-abstract static methods;
- service-method factories require public non-static runtime methods;
- one complete definition set may be applied only once;
- missing factory services and nested resolution failures remain distinguishable;
- Worker provider output contains no closure values;
- Worker `register()` delegates the same contribution produced by `define()`;
- Worker required-service declarations cover both internally selected task factories;
- `WorkerManager` remains unresolved until after runtime entrypoint validation;
- only the selected task factory is resolved;
- `RuntimePathContext::class` remains present as a required runtime service id;
- the runtime context object and its path values never become definition values, generated artifact payload values, or fingerprint input;
- production artifact compilation does not consume complete provider-produced definition sets;
- Worker definitions do not depend on `BootstrapConfig`;
- definition-validation diagnostics do not leak raw definition data.

The required canonical-model test files are:

```text
framework/packages/core/foundation/tests/Contract/ContainerDefinitionSetRejectsRuntimeValuesContractTest.php
framework/packages/core/foundation/tests/Contract/ContainerDefinitionSetIsDeterministicContractTest.php
framework/packages/core/foundation/tests/Integration/ContainerDefinitionApplierPreservesLaterBindingTest.php
framework/packages/core/foundation/tests/Integration/ContainerDefinitionApplierPreservesTagFirstWinsTest.php
framework/packages/core/foundation/tests/Integration/ContainerDefinitionApplierPreservesSharedLifecycleTest.php
```

The required provider-integration test files are:

```text
framework/packages/core/foundation/tests/Integration/FoundationProviderSourceDefinitionsParityTest.php
framework/packages/core/kernel/tests/Integration/KernelProviderSourceDefinitionsParityTest.php
framework/packages/platform/worker/tests/Contract/WorkerProviderDefinitionsContainNoClosuresContractTest.php
framework/packages/platform/worker/tests/Integration/WorkerProviderSourceDefinitionsParityTest.php
framework/packages/core/kernel/tests/Contract/KernelCompileHostServicesAreNotRuntimeDefinitionsContractTest.php
```

The required module-resolution and provider-plan test files are:

```text
framework/packages/core/kernel/tests/Contract/ComposerManifestReaderPreservesProviderOrderContractTest.php
framework/packages/core/kernel/tests/Integration/ModuleResolutionContainsManifestAndPlanTest.php
framework/packages/core/kernel/tests/Integration/ContainerProviderPlanUsesTopologicalModuleOrderTest.php
framework/packages/core/kernel/tests/Integration/ContainerProviderPlanPreservesDeclaredProviderOrderTest.php
framework/packages/core/kernel/tests/Integration/ContainerProviderPlanRejectsDuplicateProviderTest.php
framework/packages/core/kernel/tests/Integration/ContainerProviderPlanRejectsNonDefinitionProviderTest.php
```

The required Worker lazy-resolution and runtime-seed test files are:

```text
framework/packages/platform/worker/tests/Integration/WorkerStartCommandResolvesManagerLazilyTest.php
framework/packages/platform/worker/tests/Integration/WorkerTaskFactorySelectsServiceLazilyTest.php
framework/packages/core/kernel/tests/Unit/RuntimePathContextValidationTest.php
```

Provider parity tests must compare:

```text
service ids
aliases
tags
shared flags
```

between source registration and the canonical definition set.

Tests must also prove:

- Foundation, Kernel, and Worker providers contribute through one shared builder;
- one complete set is applied once;
- mixed declarative and imperative provider batches fail before provider execution;
- a second declarative set fails before builder mutation;
- Kernel compile-host service ids do not appear in the Kernel runtime definition stream;
- mandatory and possible container-owned graph lookups resolved through `ContainerInterface` have matching required-service declarations;
- one module-resolution run reads the installed manifest exactly once;
- `resolve()` delegates through `resolveResolution()->plan()`;
- `ModuleResolution` contains the exact manifest supplied to graph resolution;
- `ContainerProviderPlan` contains class names and ordering metadata only;
- `ModulePlan` remains free of provider class lists;
- provider planning does not imply production compiler consumption of provider-produced definition sets;
- optional mode-dependent preflight lookups remain outside unconditional required-service declarations.

## Related SSoT

- `docs/ssot/runtime-container-definitions.md`
- `docs/ssot/di-tags-and-middleware-ordering.md`
- `docs/ssot/compiled-container.md`
- `docs/ssot/json-like-runtime-values.md`
- `docs/ssot/artifacts.md`
- `docs/ssot/modules-and-manifests.md`

## Related ADRs

- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/adr/ADR-0017-worker-manager-application-worker.md`
- `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`
- `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
- `docs/adr/ADR-0029-kernel-container-compile-artifact.md`
