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

Coretsia will introduce a Foundation-owned declarative runtime container-definition model under:

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

The selected flow is:

```text
ContainerDefinitionProviderInterface
  -> ContainerDefinitionBuilder
  -> ContainerDefinitionSet
  -> source runtime adapter or Kernel compiler
```

For the source runtime:

```text
ContainerDefinitionSet
  -> ContainerDefinitionApplier
  -> ContainerBuilder
  -> Container
```

For compilation:

```text
ContainerDefinitionSet::toDescriptorStream()
  -> Kernel container compiler
  -> Kernel compiled graph
  -> container@1 artifact
```

Artifact-only runtime boot consumes the compiled artifact derived from the canonical model. It does not execute definition providers or consume the source model as a production fallback.

### Decision 3: Introduce a closure-free definition-provider SPI

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

The same provider implementation is used by source-mode and compile-mode orchestration whenever the provider implements `ContainerDefinitionProviderInterface`.

Foundation and Kernel runtime wiring use provider-owned definitions through that SPI.

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
- provider order remains caller-supplied and significant.

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

### Decision 9: Keep runtime closures inside the source adapter

`ContainerDefinitionApplier` may create runtime closures internally when adapting class and factory definitions to `ContainerBuilder::factory(...)`.

Those closures are source-runtime adapter implementation details.

They must never enter:

- `ContainerServiceDefinition`;
- `ContainerDefinitionSet`;
- descriptor streams;
- Kernel compiled graphs;
- generated artifacts.

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

Foundation and Kernel source-runtime wiring uses provider-owned canonical definitions.

Production artifact compilation continues to use its existing input path and does not consume provider-produced `ContainerDefinitionSet` input.

Providers that do not implement `ContainerDefinitionProviderInterface` may remain imperative.

Production artifact generation and artifact-only runtime boot remain governed by their existing compiled-container contracts.

The canonical model is not a second production runtime boot path.

Artifact-only runtime boot must not execute declarative providers as a fallback when the compiled container artifact is missing or invalid.

No new Composer dependency is required.

### Decision 12: Foundation and Kernel providers own their runtime definitions

The following service providers implement both:

```text
ServiceProviderInterface
ContainerDefinitionProviderInterface
```

```text
Coretsia\Foundation\Provider\FoundationServiceProvider
Coretsia\Kernel\Provider\KernelServiceProvider
```

Their `define()` methods are the canonical sources of their runtime wiring.

Their `register()` methods remain the source-container entrypoints required by `ServiceProviderInterface`, but they must not maintain a parallel imperative registration of the same runtime services.

Runtime contribution from `register()` is delegated through:

```php
$builder->registerDefinitionProvider($this);
```

Separate parallel provider classes such as:

```text
FoundationContainerDescriptorProvider
KernelContainerDescriptorProvider
```

are not introduced.

`FoundationServiceProvider::define()` owns the canonical runtime definitions for:

```text
SystemClock
ClockInterface
Stopwatch
UlidGenerator
UuidGenerator
IdGeneratorInterface
ContextStore
ContextAccessorInterface
correlation services
noop logging and observability ports
PriorityResetOrchestrator
ResetOrchestrator
Foundation reset tags
kernel.stateful tag
```

`KernelServiceProvider::define()` owns the canonical runtime definitions for:

```text
RuntimeEntrypointGuard
HookInvoker
KernelRuntime
KernelRuntimeInterface alias
```

Runtime construction that cannot be represented as direct class construction uses public static class-method factories.

A factory may accept:

```text
ContainerInterface
TagRegistry
canonical service references
canonical parameter references
deterministic scalar or map values
```

When a factory resolves runtime services through `ContainerInterface`, every such dependency must also be declared through:

```php
ContainerDefinitionBuilder::requireService(...)
```

This preserves graph topology while allowing deterministic runtime resolution order and failure taxonomy.

Runtime factories must not re-read Bootstrap Phase A or ConfigKernel Phase B.

### Decision 13: Kernel compile-host wiring is outside the runtime definition graph

Kernel service-provider wiring is divided into two categories.

`KernelServiceProvider::register()` may register source-host services needed to perform bootstrap, configuration compilation, module planning, artifact compilation, fingerprinting, cache verification, artifact IO, and container compilation.

Those services include:

```text
Bootstrap Phase A services
dotenv loaders
Composer metadata readers
ModulePlanResolver
ConfigKernel
artifact builders
ArtifactCompiler
fingerprint calculators and explainers
CacheVerifier
artifact readers and writers
ContainerCompiler
```

`KernelServiceProvider::define()` must not contribute those services to the canonical runtime definition graph.

The explicit law is:

```text
Kernel compile-host services are not part of the compiled runtime
container definition graph.
```

Only Kernel runtime services belong in the Kernel provider contribution.

Compile-host services may produce or validate runtime artifacts, but they are not runtime graph definitions and must not appear in the provider-produced runtime descriptor stream.

## Consequences

### Positive consequences

- Source runtime wiring and compiled-container input gain one canonical semantic model.
- Foundation and Kernel runtime wiring no longer have parallel imperative and declarative sources.
- Existing Foundation and Kernel service providers are the canonical definition providers.
- Kernel compile-host wiring remains explicitly separated from runtime graph wiring.
- Foundation remains independent from Kernel.
- Framework-wide contracts remain free from Foundation-specific DI semantics.
- Provider order and operation order remain explicit and testable.
- Source and compiled paths can share service, factory, alias, parameter, tag, and lifecycle semantics.
- Runtime closures are isolated to the source adapter.
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

Source-mode orchestration should register the declarative Foundation and Kernel providers in one batch:

```php
$builder->register(
    new FoundationServiceProvider(),
    new KernelServiceProvider(),
);
```

`ContainerBuilder` owns creation of the shared definition builder and definition context for that batch.

Provider order remains caller-supplied and significant.

Foundation and Kernel contributions are built into one complete definition set and applied once.

Kernel compile-host factories are registered by `KernelServiceProvider::register()` but do not enter the canonical runtime descriptor stream.

Compile-mode orchestration must invoke the same provider `define()` methods against the same canonical builder/context model and pass the resulting complete definition set to the Kernel compiler.

Artifact-only runtime boot must continue to consume the compiled artifact and must not run definition providers as a fallback.

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

### Alternative 7: Use provider-produced definitions as the production artifact input immediately

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
framework/packages/core/kernel/tests/Contract/KernelCompileHostServicesAreNotRuntimeDefinitionsContractTest.php
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

- Foundation and Kernel providers contribute through one shared builder;
- one complete set is applied once;
- mixed declarative and imperative provider batches fail before provider execution;
- a second declarative set fails before builder mutation;
- Kernel compile-host service ids do not appear in the Kernel runtime definition stream;
- container-resolved runtime dependencies have matching required-service declarations.

## Related SSoT

- `docs/ssot/runtime-container-definitions.md`
- `docs/ssot/di-tags-and-middleware-ordering.md`
- `docs/ssot/compiled-container.md`
- `docs/ssot/json-like-runtime-values.md`
- `docs/ssot/artifacts.md`

## Related ADR

- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`
- `docs/adr/ADR-0029-kernel-container-compile-artifact.md`
