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

# Runtime Container Definitions (SSoT)

```yaml
ssotVersion: 1
status: pre-stable
owner: core/foundation
```

This document is the Single Source of Truth for the Foundation-owned canonical in-memory runtime container-definition model, declarative definition-provider SPI, ordered operation stream, deterministic value and reference law, source-runtime application semantics, and safe definition-validation failures.

This document also defines the declarative runtime contribution rules used by Foundation, Kernel, and Worker providers.

It does not define the Kernel-owned `container@1` payload schema or artifact-only runtime boot policy. Those remain owned by `docs/ssot/compiled-container.md`.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Coretsia needs one canonical deterministic representation of runtime DI wiring.

The current integration state is:

```text
Foundation source runtime application
    -> actively consumes provider-owned canonical definitions
    -> uses caller-supplied declarative provider order
    -> applies one shared definition set once

Kernel production container compilation
    -> accepts one ModuleResolution
    -> compiles Phase-B config
    -> resolves one ContainerProviderPlan
    -> collects canonical provider definitions in plan order
    -> merges ordered provider definition sets
    -> compiles and validates one DefinitionGraph

Kernel cache verification
    -> rebuilds the expected graph through the same production path

artifact-only runtime boot
    -> consumes the approved compiled artifact
    -> does not run definition providers as fallback
```

The canonical model is the active production graph input model.

Production callers do not manually assemble or pass raw descriptor streams.

Artifact-only runtime boot must not execute definition providers or use the source model as a production fallback.

When compilation orchestration consumes provider-produced definitions, the model must preserve source-runtime semantics across the compiled path in:

- service construction;
- factory construction;
- alias behavior;
- parameter binding;
- tag registration;
- provider order;
- collision behavior;
- shared and non-shared lifecycle;
- deterministic value validation;
- typed argument references.

## Authority boundary (MUST)

This document owns:

- the Foundation declarative definition-provider SPI;
- definition context shape and access rules;
- canonical operation kinds;
- canonical operation shapes;
- operation-order preservation;
- deterministic definition values and limits;
- typed value-reference shapes;
- immutable definition-set semantics;
- required runtime service-id collection semantics;
- source-runtime application semantics;
- one-complete-set application policy;
- declarative provider batch aggregation semantics;
- consumption of an externally resolved provider order during declarative definition collection;
- production collection of ordered provider definition sets;
- the canonical definition-model side of final graph-completeness validation;
- declarative provider adapter semantics;
- Foundation, Kernel, and Worker runtime contribution boundaries;
- the Kernel compile-host/runtime-graph separation law;
- definition-validation exception taxonomy;
- the boundary between the canonical model and artifacts.

This document does not own:

- the global artifact envelope;
- artifact header fields;
- the `container@1` artifact payload schema;
- Kernel `DefinitionGraph` shape;
- artifact-only boot failure taxonomy;
- artifact production orchestration;
- module resolution;
- installed manifest discovery;
- `ModuleResolution`;
- module-provider ordering policy;
- `ContainerProviderPlan` construction;
- provider class discovery and reflection validation;
- fingerprint behavior;
- cache verification behavior;
- global config merge semantics;
- tag identifier ownership rows;
- middleware slot ownership;
- reset orchestration;
- provider implementation policy outside the Foundation, Kernel, and Worker providers named by this document.

## Ownership boundary (MUST)

The canonical model is owned by:

```text
core/foundation
```

It MUST NOT be moved to `core/contracts`.

The model is coupled to Foundation DI semantics and is not a technology-neutral framework port.

It MUST NOT be moved to `core/kernel`.

Foundation source providers must be able to describe Foundation DI wiring without depending on Kernel.

Kernel MAY depend on and consume the Foundation canonical model.

The exported descriptor stream is an internal normalization adapter and is not a production caller API.

Foundation MUST NOT depend on Kernel to define or apply the canonical model.

## Canonical implementation points

The canonical public model is:

```text
framework/packages/core/foundation/src/Container/Definition/ContainerDefinitionProviderInterface.php
framework/packages/core/foundation/src/Container/Definition/ContainerDefinitionContext.php
framework/packages/core/foundation/src/Container/Definition/ContainerDefinitionBuilder.php
framework/packages/core/foundation/src/Container/Definition/ContainerDefinitionSet.php
framework/packages/core/foundation/src/Container/Definition/ContainerDefinitionKind.php
framework/packages/core/foundation/src/Container/Definition/ContainerServiceDefinition.php
framework/packages/core/foundation/src/Container/Definition/ContainerValueReference.php
```

The source-runtime adapter is:

```text
framework/packages/core/foundation/src/Container/Definition/ContainerDefinitionApplier.php
```

The canonical validation and shared identifier policies include:

```text
framework/packages/core/foundation/src/Container/Internal/ContainerDefinitionPolicy.php
framework/packages/core/foundation/src/Container/Internal/ContainerServiceIdPolicy.php
framework/packages/core/foundation/src/Tag/Internal/TagNamePolicy.php
```

The canonical definition-validation exception is:

```text
framework/packages/core/foundation/src/Container/Exception/ContainerDefinitionInvalidException.php
```

The Kernel production definition-collection and completeness integration points are:

```text
framework/packages/core/kernel/src/Container/RuntimeContainerGraphCompiler.php
framework/packages/core/kernel/src/Container/ContainerGraphCompletenessValidator.php
framework/packages/core/kernel/src/Container/RuntimeContainerSeedIds.php
framework/packages/core/kernel/src/Container/ContainerCompiler.php
```

These implementation points do not change this document's authority boundary.

## Kernel compile-time provider-planning boundary

The Foundation canonical definition model does not discover or order module providers.

Kernel-owned compile-time provider planning is represented by:

```text
framework/packages/core/kernel/src/Module/ModuleResolution.php
framework/packages/core/kernel/src/Container/Provider/ContainerProviderPlan.php
framework/packages/core/kernel/src/Container/Provider/ContainerProviderPlanResolver.php
```

These classes are not part of the Foundation canonical model.

They provide ordered compile-time input to orchestration that chooses to collect provider-produced definitions.

The canonical order is:

```text
ModulePlan topological order
    -> declared provider order within each module
```

Provider FQCN sorting is forbidden.

`ContainerProviderPlan` stores no provider instances and no definition operations.

It contains only ordered provider identity records:

```text
moduleId
providerClass
moduleOrder
providerOrder
```

Provider planning MUST consume one `ModuleResolution` and MUST NOT perform a second manifest read.

The ordering and snapshot policy is owned by:

```text
docs/adr/ADR-0024-kernel-module-plan-resolution.md
docs/ssot/modules-and-manifests.md
```

This document owns how an already-ordered provider sequence contributes through the canonical definition model, including source-mode shared-builder aggregation and compile-mode per-provider definition-set collection followed by ordered merge.

## Canonical provider SPI (MUST)

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

A provider MUST produce the same contribution for the same:

- provider implementation state;
- already-compiled Phase-B config snapshot.

The complete combined definition result additionally depends on the exact orchestration-supplied provider sequence.

A provider MUST NOT:

- return closures;
- return runtime objects;
- place runtime objects into the builder;
- read filesystem sources;
- read environment sources;
- read dotenv files;
- read generated artifacts;
- resolve container services;
- instantiate runtime services;
- start UnitOfWork;
- invoke reset orchestration;
- run runtime lifecycle;
- emit stdout or stderr.

Provider implementations MAY be invoked by source-mode and compile-mode orchestration.

Source-mode orchestration supplies provider order directly through the registration call.

Compile-mode orchestration that uses module composition supplies provider order through `ContainerProviderPlan`.

Neither path may sort provider FQCNs.

The same provider implementation MUST be used by both modes whenever the provider implements the declarative SPI.

The following providers MUST use their `define()` methods as their only runtime wiring sources:

```text
Coretsia\Foundation\Provider\FoundationServiceProvider
Coretsia\Kernel\Provider\KernelServiceProvider
Coretsia\Platform\Worker\Provider\WorkerServiceProvider
```

Other `ServiceProviderInterface` implementations MAY remain imperative when they do not implement `ContainerDefinitionProviderInterface`.

## Declarative provider adapter law (MUST)

A declarative provider MAY implement both:

```text
ServiceProviderInterface
ContainerDefinitionProviderInterface
```

Its `register()` method remains the normal source-container provider entrypoint.

Its `define()` method is the canonical runtime wiring source.

`register()` MUST NOT maintain an imperative copy of runtime definitions already present in `define()`.

Source registration MUST delegate the runtime contribution through:

```php
$builder->registerDefinitionProvider($this);
```

A declarative provider MAY perform deterministic source-host prevalidation before delegation.

`KernelServiceProvider::register()` MAY also register Kernel compile-host factories before delegating its runtime contribution.

`WorkerServiceProvider::register()` MUST delegate its complete runtime contribution through:

```php
$builder->registerDefinitionProvider($this);
```

It must not register Worker runtime services, aliases, or tags through a parallel imperative path.

Worker has no provider-owned source-host runtime wiring outside `define()`.

No separate parallel descriptor-provider class may duplicate the provider definition source.

## Foundation runtime contribution (MUST)

`FoundationServiceProvider::define()` is the canonical source for:

```text
SystemClock
ClockInterface alias
Stopwatch
UlidGenerator
UuidGenerator
IdGeneratorInterface alias
ContextStore
ContextAccessorInterface alias
CorrelationIdGenerator
CorrelationIdProvider
CorrelationIdProviderInterface alias
noop logging and observability ports
PriorityResetOrchestrator
ResetOrchestrator
Foundation reset tags
kernel.stateful tag
```

The default ID-generator alias target is selected from the already-compiled Foundation config snapshot.

Correlation-id generation remains ULID-backed independently of the selected default `IdGeneratorInterface` target.

Noop implementations are defined directly under their public interface or port service ids.

Reset orchestrators are represented through public static class-method factories.

Their config input is passed through a canonical parameter reference.

Dependencies resolved internally through `ContainerInterface` MUST have matching `requireService()` declarations.

The Foundation provider MUST NOT maintain a parallel imperative registration of these runtime services.

## Kernel runtime contribution and compile-host boundary (MUST)

`KernelServiceProvider::define()` is the canonical source for:

```text
RuntimeEntrypointGuard
HookInvoker
KernelRuntime
KernelRuntimeInterface alias
```

Kernel UnitOfWork attribute limits are represented as canonical parameter operations.

`KernelRuntime` may be constructed by a public static class-method factory that receives:

```text
ContainerInterface
validated scalar parameter values
```

The factory may resolve required runtime services through `ContainerInterface` to preserve deterministic resolution and failure order.

Every service resolved in this way MUST also be declared through `requireService()`.

The required Kernel runtime dependency set includes:

```text
ContainerInterface
TagRegistry
ContextAccessorInterface
ResetOrchestrator
Stopwatch
IdGeneratorInterface
CorrelationIdProviderInterface
CorrelationIdGenerator
HookInvoker
LoggerInterface
TracerPortInterface
MeterPortInterface
```

`KernelServiceProvider` declares the contracts-level `ContextAccessorInterface` binding.

`KernelServiceFactory` resolves that binding and requires the resolved service to be the canonical Foundation-owned `ContextStore` before constructing `KernelRuntime`.

Kernel compile-host services MUST remain in `KernelServiceProvider::register()` and MUST NOT be contributed by `define()`.

The explicit law is:

```text
Kernel compile-host services are not part of the compiled runtime
container definition graph.
```

Compile-host services include:

```text
Bootstrap Phase A services
dotenv loaders
Composer metadata readers
ModulePlanResolver
ContainerProviderPlanResolver
ConfigKernel
artifact builders
ArtifactCompiler
fingerprint services
CacheVerifier
artifact readers and writers
CompiledContainerFactory
ContainerCompiler
ContainerGraphCompletenessValidator
RuntimeContainerGraphCompiler
```

Compile-host services MUST NOT appear in the Kernel runtime definition operations or compiled runtime graph.

`ModuleResolution` and `ContainerProviderPlan` are per-operation compile-time values produced by those services.

They MUST NOT appear in the Kernel runtime definition operations or compiled runtime graph.

Kernel source-host orchestration MAY additionally register a factory for:

```text
RuntimePathContext
```

This factory:

- consumes an already-resolved `BootstrapConfig`;
- constructs runtime-only path context;
- does not execute Bootstrap Phase A;
- does not belong to `KernelServiceProvider::define()`;
- does not enter the Kernel runtime descriptor stream;
- does not enter generated artifacts;
- does not enter fingerprint input.

`RuntimePathContext` is an allowed external runtime seed, not a compile-host service and not a canonical definition value.

## Worker runtime contribution and runtime-seed boundary (MUST)

`WorkerServiceProvider::define()` is the canonical source for the complete Worker runtime contribution.

It MUST define:

```text
WorkerServiceFactory
StableJsonEncoder
StableJsonDecoder

WorkerPoolSpec
WorkerRuntimeEntrypointGuard
WorkerStateStore
WorkerLifecycleLock
WorkerLifecycleLocatorStore
WorkerStopSignal

WorkerControlTransport
WorkerControlProtocol
WorkerControlServer
WorkerControlClient
WorkerChildReadinessChannel

WorkerChildTable
WorkerSignalController
WorkerForkIsolation
WorkerChildCommandBuilder

QueueTaskFactory
HttpTaskFactory
TaskFactoryInternalInterface
ApplicationWorker

PcntlWorkerProcessDriver
WorkerProcProcessHostProtocol
WorkerProcProcessHostClient
ProcWorkerProcessDriver
ContainerWorkerProcessDriverResolver

WorkerSupervisor
ContainerWorkerSupervisorResolver

WorkerStartCommand
WorkerStopCommand
WorkerStatusCommand
WorkerHealthCommand
```

It MUST define the aliases:

```text
WorkerControlClientInterface
    -> WorkerControlClient

WorkerProcessDriverResolverInterface
    -> ContainerWorkerProcessDriverResolver

WorkerSupervisorInterface
    -> WorkerSupervisor

WorkerSupervisorResolverInterface
    -> ContainerWorkerSupervisorResolver
```

It MUST define `ContainerWorkerProcessDriverResolver` and alias `WorkerProcessDriverResolverInterface` to it.

The resolver MUST perform an exact package-owned mapping and MUST resolve only the driver selected by `WorkerPoolSpec`.

Process-driver tags are not part of the Worker runtime contribution.

`WorkerControlCredential` MUST NOT be declared as a container service, alias, tagged service, factory output, or runtime seed. It is generated once per supervisor run and passed explicitly only to the active `WorkerControlServer` and private `WorkerLifecycleLocator`.

`WorkerProcProcessHostTransport` is instantiated only by the package-owned proc-host executable. It is not a runtime-container service and must not be added to the Worker contribution.

It MUST contribute the canonical `cli.command` tags for:

```text
WorkerStartCommand
WorkerStopCommand
WorkerStatusCommand
WorkerHealthCommand
```

The complete Worker contribution MUST contain no closure values.

Container definition production MUST NOT introduce a tag, registry, or eager aggregate whose purpose is resolving arbitrary services so they can be closed after `fork()`.

Process-exec safety belongs to descriptor owners and `docs/ssot/process-exec-descriptor-safety.md`, not to DI graph enumeration.

Worker process-exec safety MUST NOT be implemented by clearing the resolved-service cache, eagerly resolving integration services, or executing arbitrary service callbacks in a forked child.

It MUST NOT define compatibility aliases, deprecated lifecycle facades, duplicate process-ownership services, or duplicate control-server abstractions.

### Required Worker service ids

The Worker contribution MUST declare through `requireService()`:

```text
ConfigRepositoryInterface
ModulePlan
RuntimePathContext
WorkerPoolSpec
WorkerRuntimeEntrypointGuard
WorkerProcessDriverResolverInterface
ApplicationWorker
WorkerSupervisorInterface
WorkerControlClientInterface
QueueTaskFactory
HttpTaskFactory
```

The following MAY be external runtime seeds:

```text
ConfigRepositoryInterface
ModulePlan
RuntimePathContext
```

The following MUST be defined by the complete definition graph:

```text
WorkerPoolSpec
WorkerRuntimeEntrypointGuard
WorkerProcessDriverResolverInterface
ApplicationWorker
WorkerSupervisorInterface
WorkerControlClientInterface
QueueTaskFactory
HttpTaskFactory
```

`WorkerProcessDriverResolverInterface` MUST be required because `WorkerSupervisor` performs a deferred exact lookup of the selected concrete process driver.

`WorkerSupervisorInterface` MUST be required because `ContainerWorkerSupervisorResolver::resolve()` performs a deferred `ContainerInterface::get(WorkerSupervisorInterface::class)` lookup.

`WorkerControlClientInterface` MUST be the live control dependency used by:

```text
WorkerStopCommand
WorkerStatusCommand
WorkerHealthCommand
```

`QueueTaskFactory` and `HttpTaskFactory` MUST both be required because `WorkerServiceFactory::taskFactory(...)` performs a runtime `ContainerInterface` lookup against one selected canonical id.

`Psr\Http\Server\RequestHandlerInterface` is not an unconditional required service id.

It is a mode-dependent HTTP preflight dependency that:

- is irrelevant in queue mode;
- is checked only after Worker runtime-driver compatibility passes;
- may be absent so HTTP preflight can produce its deterministic Worker start failure.

These declarations describe possible runtime graph edges, not eager resolution order.

### Worker task-factory selection

`WorkerServiceFactory::taskFactory(...)` MUST receive:

```text
WorkerPoolSpec
ContainerInterface
```

It MUST NOT receive closure factories.

It MUST:

1. select the canonical service id from `WorkerPoolSpec`;
2. resolve only that selected service;
3. validate `TaskFactoryInternalInterface`;
4. validate `supports($spec)`;
5. map lookup and validation failures to safe deterministic Worker start failures.

The canonical mapping is:

```text
queue -> QueueTaskFactory
http  -> HttpTaskFactory
```

Task-factory selection MUST NOT eagerly resolve both concrete factories.

A task-body closure produced after successful runtime resolution is allowed runtime work.

It MUST NOT enter:

- provider output;
- canonical operations;
- canonical values;
- descriptor streams;
- generated container artifacts;
- fingerprint input.

### Lazy WorkerSupervisor resolution

`WorkerStartCommand` MUST depend on:

```text
WorkerSupervisorResolverInterface
```

It MUST NOT depend on an eager supervisor or closure-valued supervisor factory.

The canonical implementation is:

```text
ContainerWorkerSupervisorResolver
```

The resolver MUST:

- retain only `ContainerInterface`;
- resolve `WorkerSupervisorInterface` on demand;
- validate the resolved type;
- map container failures and invalid bindings to safe deterministic Worker start failures;
- preserve guard-before-supervisor ordering.

Resolving `WorkerStartCommand` MUST NOT resolve `WorkerSupervisorInterface`.

The required order is:

```text
build WorkerPoolSpec
-> validate WorkerRuntimeEntrypointGuard
-> resolve WorkerSupervisorInterface
-> run WorkerSupervisor
```

### RuntimePathContext

`RuntimePathContext` is an immutable runtime-only seed owned by Kernel.

It MAY contain normalized absolute:

```text
skeletonRoot
artifactRoot
```

It MUST NOT:

- be part of `ContainerDefinitionContext`;
- be represented as a literal or parameter operation;
- be represented as a runtime-object definition value;
- serialize its path values into descriptors;
- emit its path values into artifact payloads;
- include its path values in fingerprint input;
- be resolved from `BootstrapConfig` by Worker services.

The Worker runtime graph MUST consume it as an external runtime seed.

The following Worker factory methods MUST receive `RuntimePathContext`:

```text
WorkerServiceFactory::workerStateStore(...)
WorkerServiceFactory::workerLifecycleLocatorStore(...)
WorkerServiceFactory::workerLifecycleLock(...)
WorkerServiceFactory::workerStopSignal(...)
WorkerServiceFactory::workerControlTransport(...)
WorkerServiceFactory::workerChildCommandBuilder(...)
WorkerServiceFactory::pcntlWorkerProcessDriver(...)
WorkerServiceFactory::procWorkerProcessDriver(...)
WorkerServiceFactory::workerProcProcessHostClient(...)
```

The path-owning factories use it as follows:

- `WorkerStateStore` receives the normalized skeleton root;
- `WorkerLifecycleLocatorStore` receives the normalized skeleton root;
- `WorkerLifecycleLock` receives the normalized skeleton root;
- `WorkerStopSignal` receives the normalized skeleton root;
- `WorkerControlTransport` receives the normalized skeleton root;
- `WorkerChildCommandBuilder` receives one validated skeleton-root-relative artifact root;
- `PcntlWorkerProcessDriver` receives the normalized skeleton root only for the post-fork artifact-only exec boundary;
- `ProcWorkerProcessDriver` receives the normalized skeleton root;
- `WorkerProcProcessHostClient` receives the normalized skeleton root as its working directory.

`ApplicationWorker` MUST receive `WorkerStopSignal`, not a raw skeleton root.

`PcntlWorkerProcessDriver` MUST NOT receive `BootstrapConfig`, `ContainerInterface`, `ApplicationWorker`, raw individual artifact paths, or a child-bootstrap closure.

The constructed Worker runtime services MUST NOT resolve `BootstrapConfig` or reconstruct runtime roots independently.

## Definition context (MUST)

`ContainerDefinitionContext` is an immutable input context for definition production.

It contains only an already-compiled Phase-B config snapshot:

```text
array<string, mixed>
```

The top-level config snapshot MUST be an empty array or a string-keyed map.

It MUST NOT be a non-empty list.

It MUST NOT contain top-level integer keys.

The context MUST NOT expose:

- `BootstrapConfig`;
- an env repository;
- dotenv state;
- filesystem paths;
- source config locations;
- generated artifacts;
- a container;
- service instances;
- `RuntimePathContext`;
- skeleton runtime roots;
- artifact runtime roots;
- absolute runtime filesystem paths;
- runtime lifecycle objects.

The context validates the supplied snapshot shape.

The caller remains responsible for supplying an actual already-compiled Phase-B snapshot.

### Config-root access (MUST)

The canonical config-root API is:

```php
public function configRoot(string $root): array;
```

A config-root name MUST:

- be non-empty;
- have no leading or trailing whitespace;
- contain no whitespace;
- be valid UTF-8.

The requested root MUST exist and MUST be an empty array or string-keyed map.

A non-empty list root is invalid.

A map with an integer key is invalid.

`configRoot()` MUST fail closed through `ContainerDefinitionInvalidException` and MUST NOT expose the root name or root value in its public message.

The source `ContainerBuilder::configRoot()` path and declarative `ContainerDefinitionContext::configRoot()` path MUST preserve equivalent root-shape semantics.

## Canonical model lifecycle (MUST)

`ContainerDefinitionBuilder` is mutable while provider contributions are being collected.

`ContainerDefinitionBuilder::build()` returns an immutable `ContainerDefinitionSet`.

The builder MUST NOT instantiate runtime services.

The immutable set MUST contain only:

- an ordered operation stream;
- a canonical required runtime service-id set.

The immutable set MUST NOT contain:

- closures;
- runtime services;
- container instances;
- resolved factory objects;
- reflection objects;
- resources;
- provider objects;
- config repositories;
- env repositories;
- filesystem handles.

`ContainerDefinitionSet::fromValidatedState(...)` MUST fully revalidate supplied state.

It MUST NOT trust:

- the method name;
- `@internal` documentation;
- the caller type;
- prior builder validation;
- prior normalization.

`ContainerDefinitionSet::empty()` returns an immutable empty set.

## Operation-stream law (MUST)

The canonical operation kinds are:

```text
service.class
service.factory.class-method
service.factory.service-method
alias
parameter
tag
```

They are represented by:

```text
Coretsia\Foundation\Container\Definition\ContainerDefinitionKind
```

Operation order is semantic.

The builder MUST preserve call order exactly.

`ContainerDefinitionSet::toDescriptorStream()` MUST preserve that exact semantic operation order.

The model MUST NOT globally sort operations before source application or compiler consumption.

The operation stream MUST be a list.

Each operation MUST be a string-keyed map with the exact keys defined for its kind.

Unknown operation kinds are invalid.

Missing keys are invalid.

Unknown extra keys are invalid.

Integer operation keys are invalid.

## Service class operation (MUST)

The canonical `service.class` operation is:

```php
[
    'arguments' => <argument-list>,
    'class' => '<class-reference>',
    'id' => '<service-id>',
    'kind' => 'service.class',
    'shared' => <bool>,
]
```

The operation MUST contain exactly:

```text
arguments
class
id
kind
shared
```

`arguments` MUST be a list.

`class` MUST be a valid class reference under the canonical Foundation definition policy.

`id` MUST be a valid Foundation service id.

`shared` MUST be boolean.

Source application resolves arguments and then creates the class through reflection.

The class MUST be instantiable at runtime.

## Class-method factory operation (MUST)

The canonical `service.factory.class-method` operation is:

```php
[
    'arguments' => <argument-list>,
    'factoryClass' => '<factory-class-reference>',
    'id' => '<service-id>',
    'kind' => 'service.factory.class-method',
    'method' => '<method-name>',
    'shared' => <bool>,
]
```

The operation MUST contain exactly:

```text
arguments
factoryClass
id
kind
method
shared
```

The factory class method MUST exist at definition validation time.

It MUST be:

- public;
- static;
- non-abstract.

The canonical model MUST NOT represent a class-method factory through a Closure, callable object, callable string, or PHP callable array.

## Service-method factory operation (MUST)

The canonical `service.factory.service-method` operation is:

```php
[
    'arguments' => <argument-list>,
    'factoryServiceId' => '<factory-service-id>',
    'id' => '<service-id>',
    'kind' => 'service.factory.service-method',
    'method' => '<method-name>',
    'shared' => <bool>,
]
```

The operation MUST contain exactly:

```text
arguments
factoryServiceId
id
kind
method
shared
```

The method name is validated lexically at definition time.

Method existence, visibility, staticness, and compatibility cannot be fully validated until the factory service is resolved or the final graph has identified its concrete type.

At source-runtime invocation time, the factory service result MUST be an object and the method MUST be:

- public;
- non-static;
- non-abstract.

A missing factory service MUST remain distinguishable from a factory service that exists but fails while resolving a nested dependency.

## Alias operation (MUST)

The canonical `alias` operation is:

```php
[
    'alias' => '<alias-service-id>',
    'kind' => 'alias',
    'serviceId' => '<target-service-id>',
]
```

The operation MUST contain exactly:

```text
alias
kind
serviceId
```

Both ids MUST be valid Foundation service ids.

An alias MUST NOT target itself.

Later alias registration for the same alias id overrides an earlier alias definition when the operation stream is applied.

### Alias lifecycle (MUST)

A source-runtime alias MUST be registered as a non-shared delegation wrapper.

The alias wrapper MUST NOT cache the target independently.

Therefore:

- an alias to a shared target returns the target's shared result;
- an alias to a non-shared target preserves repeated target resolution;
- an alias MUST NOT convert a non-shared target into a shared target.

This rule aligns with the compiled alias lifecycle rule in `docs/ssot/compiled-container.md`.

## Parameter operation (MUST)

The canonical `parameter` operation is:

```php
[
    'kind' => 'parameter',
    'name' => '<parameter-name>',
    'value' => <deterministic-value>,
]
```

The operation MUST contain exactly:

```text
kind
name
value
```

Later parameter registration for the same name wins.

The source adapter MUST compute the final parameter map from the complete operation stream before registering runtime service factories.

Every parameter reference in the complete set MUST therefore resolve against the final later-binding value, regardless of whether the parameter operation appears before or after the service operation that references it.

Parameter values MUST NOT contain typed service, parameter, or class references.

Parameter values are deterministic data only.

## Tag operation (MUST)

The canonical `tag` operation is:

```php
[
    'kind' => 'tag',
    'meta' => <string-keyed-meta-map>,
    'priority' => <int>,
    'serviceId' => '<service-id>',
    'tag' => '<tag-name>',
]
```

The operation MUST contain exactly:

```text
kind
meta
priority
serviceId
tag
```

Tag names MUST use the same canonical Foundation tag-name policy as imperative `TagRegistry` registration.

The tag-name policy is owned by:

```text
Coretsia\Foundation\Tag\Internal\TagNamePolicy
```

`meta` MUST be an empty array or string-keyed deterministic map.

`meta` MUST NOT be a non-empty list.

Typed service, parameter, and class references are not allowed in tag metadata.

Tag application MUST delegate to `TagRegistry`.

For duplicate `(tag, serviceId)` registrations:

```text
first wins
```

Tag discovery ordering remains:

```text
priority DESC, id ASC by strcmp
```

The complete tag discovery and consumer policy remains owned by `docs/ssot/di-tags-and-middleware-ordering.md`.

## Service-definition lifecycle (MUST)

Service definitions default to:

```text
shared = true
```

A definition MAY explicitly use:

```text
shared = false
```

A shared definition is resolved once per container instance after the first successful resolution and is cached by service id.

A non-shared definition is resolved on every `Container::get($id)` call and MUST NOT be stored in the resolved-instance cache.

When a later definition replaces an earlier definition for the same service id, the later `shared` flag replaces the earlier lifecycle state.

Definition lifecycle does not alter tag dedupe, tag priority, or tag discovery ordering.

## Collision and dedupe law (MUST)

The canonical operation application semantics are:

```text
service definition collision -> later wins
alias collision              -> later wins
parameter collision          -> later wins
tag duplicate pair           -> first wins
```

Provider order is orchestration-supplied and significant.

For source registration, it is the exact caller-supplied registration order.

For module-aware compile-time collection, it is the exact `ContainerProviderPlan` order.

The Foundation model MUST NOT infer provider order from provider FQCNs, manifest map order, Composer package order, or filesystem order.

The model MUST NOT globally sort providers or operations.

Consumers MUST NOT reconstruct provider order after operation application.

## Deterministic value law (MUST)

Allowed deterministic value forms are:

```text
null
bool
int
string
list<value>
map<string, value>
```

Typed references are allowed only in service constructor/factory arguments and are normalized to exact deterministic maps.

The canonical model MUST reject:

- floats;
- objects other than input `ContainerValueReference` objects at the builder argument boundary;
- runtime service instances;
- closures;
- anonymous functions;
- callable objects;
- resources;
- reflection objects;
- invalid UTF-8 strings;
- absolute paths;
- source snippets;
- env-like references;
- sensitive-looking raw strings;
- maps with invalid or integer keys;
- values that exceed deterministic limits.

Callable-shaped string lists are ordinary deterministic list data.

For example:

```php
[
    ExampleFactory::class,
    'create',
]
```

MAY be stored as list data.

The canonical model MUST NOT execute that list as a PHP callable.

Executable behavior must be represented by an explicit service construction kind.

### Deterministic limits (MUST)

The current canonical limits are:

```text
service/class/parameter identifier maximum: 256 bytes
generic schema string maximum:           1024 bytes
nested value maximum depth:               16
normalized value maximum nodes:           4096
map maximum keys:                         256
list maximum items:                       512
operation stream maximum operations:      100000
required service maximum entries:         10000
tag-name maximum:                         256 bytes
```

A producing or validating component MUST fail closed when a limit is exceeded.

Limits apply before state can enter an immutable `ContainerDefinitionSet`.

## Service-id policy linkage (MUST)

Declarative definitions MUST use the same Foundation service-id policy as:

- `Container`;
- `ContainerBuilder`;
- `NotFoundException`;
- `TaggedService`.

The canonical policy is:

```text
Coretsia\Foundation\Container\Internal\ContainerServiceIdPolicy
```

The declarative model MUST NOT introduce a second stricter service-id regex.

A valid service id MUST:

- be non-empty;
- be at most 256 bytes;
- be valid UTF-8 under the canonical whitespace check;
- contain no whitespace;
- not be an integer-like decimal string that PHP may coerce to an integer array key.

Diagnostic readability and redaction are separate from syntactic service-id validity.

A syntactically valid but diagnostic-unsafe service id MUST still be redacted according to the Foundation diagnostics policy.

## Parameter-name policy (MUST)

A parameter name MUST:

- be non-empty;
- be at most 256 bytes;
- match the canonical parameter-name pattern;
- have no leading or trailing whitespace;
- contain no control characters;
- not look like an absolute path;
- not look like source code;
- not look like an env reference;
- not look like a sensitive value.

The canonical parameter-name pattern is equivalent to:

```text
[A-Za-z_][A-Za-z0-9_.-]*
```

## Class-reference policy (MUST)

A class reference MUST be a non-leading-backslash class-like name using namespace separators.

It MUST NOT contain `::`.

It MUST NOT be an absolute path or source snippet.

It MUST NOT be one of the reserved pseudo-types or contextual names, including:

```text
array
bool
callable
false
float
int
iterable
mixed
never
null
object
parent
resource
self
static
string
true
void
```

A class reference does not by itself require the class to exist, except where class-method factory validation requires reflection of the factory method.

## Method-name policy (MUST)

A method name MUST use the canonical method pattern:

```text
[A-Za-z_][A-Za-z0-9_]*
```

The current maximum method-name length is 128 bytes.

A method name MUST NOT contain control characters.

## Map-key policy (MUST)

Definition-owned deterministic maps MUST use string keys.

Map keys MUST match the canonical map-key pattern equivalent to:

```text
[A-Za-z_][A-Za-z0-9_.-]*
```

The current maximum map-key length is 128 bytes.

Map keys MUST NOT look like:

- absolute paths;
- source snippets;
- env references;
- sensitive values.

Definition-owned maps MUST be normalized recursively using `strcmp` byte-order key sorting.

List order MUST be preserved.

## Typed value references (MUST)

Typed references are represented in provider code by:

```text
Coretsia\Foundation\Container\Definition\ContainerValueReference
```

The only supported reference constructors are:

```php
ContainerValueReference::service(string $serviceId)
ContainerValueReference::parameter(string $parameterName)
ContainerValueReference::class(string $className)
```

### Service reference

The canonical exported shape is:

```php
[
    'id' => '<service-id>',
    'type' => 'service',
]
```

At source runtime, the reference resolves through `Container::get($id)`.

The following service references resolve to the current Foundation container instance:

```text
Coretsia\Foundation\Container\Container
Psr\Container\ContainerInterface
```

The following service reference resolves to the builder-owned Foundation runtime tag registry in source mode:

```text
Coretsia\Foundation\Tag\TagRegistry
```

`TagRegistry` is a runtime seed.

A provider that references it MUST also declare:

```php
$definitions->requireService(
    TagRegistry::class,
);
```

The provider MUST NOT define or replace the runtime seed through a canonical service operation.

### Parameter reference

The canonical exported shape is:

```php
[
    'name' => '<parameter-name>',
    'type' => 'parameter',
]
```

At source runtime, the reference resolves from the final parameter map for the complete definition set.

A missing parameter reference fails deterministically.

### Class reference

The canonical exported shape is:

```php
[
    'class' => '<class-name>',
    'type' => 'class',
]
```

At source runtime, the reference resolves to the class-name string.

It does not instantiate the class.

### Reference boundary

Provider builder input MUST use `ContainerValueReference` objects.

Raw reference maps supplied through the normal builder argument API are invalid.

An already exported descriptor stream MUST use exact reference maps and MUST NOT contain `ContainerValueReference` objects.

Malformed reference-shaped maps are invalid.

Unknown keys in a reference map are invalid.

Unknown reference types are invalid when the map has an exact reserved reference shape.

References MUST NOT appear in parameter values or tag metadata.

## Definition-set semantics (MUST)

`ContainerDefinitionSet` is immutable.

The operation list is preserved exactly.

`requiredServiceIds()` returns a canonical set represented as a list:

- duplicates removed;
- sorted by byte-order `strcmp`;
- valid Foundation service ids only.

`ContainerDefinitionSet::merge(self ...$sets)` MUST:

- concatenate operation streams in caller-supplied set order;
- preserve operation order within every set;
- deduplicate required service ids;
- reapply all operation and required-service validation;
- reapply global operation and required-service limits.

`merge()` MUST NOT apply service, alias, parameter, or tag semantics eagerly.

Those semantics remain operation-consumer responsibilities.

## Required runtime service ids (MUST)

Providers MAY declare runtime prerequisites through:

```php
ContainerDefinitionBuilder::requireService(string $serviceId)
```

Required ids are declarative completeness requirements.

They are not service operations.

They do not register a service.

They do not alter operation order.

They do not imply autowire.

A provider contribution MUST declare through `requireService()` every canonical service id that may be resolved through `ContainerInterface` as a mandatory or possible edge of the container-owned runtime graph.

This includes deferred resolver and selector lookups whose target is expected to exist in every complete graph supporting that resolution path.

A mode-dependent capability or preflight dependency that is intentionally allowed to be absent is not an unconditional required service id.

Such an optional lookup MUST be explicitly documented by its owner package and MUST fail only at its approved runtime boundary.

This requirement preserves graph topology without adding hidden service-construction operations or expanding the canonical value-reference vocabulary.

A required service id MAY refer to:

- a service defined in the same complete definition set;
- a service defined by another provider contribution in the complete set;
- an allowed external runtime seed.

The canonical production external runtime-seed service-id allowlist is:

```text
Coretsia\Foundation\Container\Container
Psr\Container\ContainerInterface
Coretsia\Foundation\Tag\TagRegistry
Coretsia\Contracts\Config\ConfigRepositoryInterface
Coretsia\Kernel\Module\ModulePlan
Coretsia\Kernel\Runtime\RuntimePathContext
```

The Worker contribution currently relies on this subset:

```text
ConfigRepositoryInterface
ModulePlan
RuntimePathContext
```

External-runtime-seed status does not authorize placing seed values into provider output.

A required service id records graph completeness only.

It does not permit eager resolution, hidden fallback construction, or serialization of the runtime seed.

`requireService()` declarations do not prescribe runtime resolution order.

Runtime resolution order remains owned by the consuming factory.

The source `ContainerDefinitionApplier` does not validate final required-service completeness.

Completeness validation belongs to a final graph/runtime-seed-aware validator.

Compiler or orchestration code MUST NOT silently discard required ids.

### Production graph-completeness integration (MUST)

The source `ContainerDefinitionApplier` does not validate final graph completeness.

Kernel production graph compilation MUST validate completeness after all provider definition sets have been merged and normalized into one `DefinitionGraph`.

The final validator is:

```text
Coretsia\Kernel\Container\ContainerGraphCompletenessValidator
```

Service argument references and required-service declarations MAY resolve to:

- a graph-defined service;
- a graph-defined alias terminating in a graph-defined service;
- a canonical external runtime-seed service id.

Alias targets, service-method factory service ids, and tagged service ids MUST resolve only to graph-owned services or aliases terminating in graph-owned services.

Runtime seeds MUST NOT be used as alias targets, service-method factory services, or tagged services.

The final validator MUST additionally reject:

- missing parameter references;
- alias cycles;
- service and alias bindings using the same id;
- runtime-seed overrides;
- compile-host service leakage.

Incomplete provider graphs MUST fail before artifact write or expected-artifact comparison.

## Source-runtime adapter (MUST)

The source-runtime adapter is:

```text
Coretsia\Foundation\Container\Definition\ContainerDefinitionApplier
```

It is an internal Foundation adapter.

External runtime code SHOULD call:

```php
$containerBuilder->applyDefinitions($completeSet);
```

External code SHOULD NOT invoke `ContainerDefinitionApplier` directly.

The adapter converts the canonical set into Foundation source-container calls through:

```text
ContainerBuilder::factory(...)
ContainerBuilder::set(...)
ContainerBuilder::tag(...)
```

Source-container factory closures created by the adapter are implementation details.

They MUST NOT be written back into the canonical set or descriptor stream.

This rule does not prohibit runtime factories or services from creating execution callbacks during runtime service construction or execution, after canonical definition production has completed.

Such callbacks remain runtime behavior and must never be copied back into canonical definitions.

### One-complete-set rule (MUST)

One `ContainerBuilder` may apply exactly one complete declarative definition set.

Repeated calls to `applyDefinitions()` on the same builder MUST fail deterministically.

Multiple provider contributions MUST first be aggregated through:

- one shared `ContainerDefinitionBuilder`; or
- `ContainerDefinitionSet::merge(...)`.

This is required so final parameter values do not depend on per-provider application grouping.

For source provider registration, `ContainerBuilder::register()` and `registerProviders()` MUST aggregate a declarative batch through one shared definition builder and one shared definition context.

Providers MUST execute in exact caller-supplied order.

The owner of the shared collection MUST call `build()` once and `applyDefinitions()` once.

A declarative registration batch MUST NOT contain imperative-only providers.

An imperative-only registration batch MUST NOT contain declarative providers.

A mixed batch MUST fail before any provider executes.

A second declarative batch or standalone declarative provider application on the same builder MUST fail before provider or compile-host registration mutates the builder.

### Source application order (MUST)

The adapter MUST:

1. read the complete ordered descriptor stream;
2. calculate the final later-wins parameter map;
3. iterate operations in semantic order;
4. register service factories, aliases, and tags;
5. skip parameter operations during the registration pass because their final values were already collected.

The adapter MUST NOT globally sort operations.

## Source runtime factory semantics (MUST)

### Class service

The adapter resolves argument references and instantiates the configured class through reflection.

The class must be instantiable.

### Class-method factory

The adapter resolves argument references and invokes the validated public non-abstract static method.

### Service-method factory

The adapter resolves the factory service through the Foundation container.

If the requested factory service id itself is missing, runtime failure uses the missing-factory-service classification.

If the factory service exists but fails because one of its nested dependencies is missing or another resolution failure occurs, runtime failure uses the factory-service-resolution-failed classification.

The resolved factory service must be an object.

The invoked method must be public, non-static, and non-abstract.

### Service-reference failures

A service-reference resolution failure must use a safe Foundation container reason token and preserve the causal throwable through `previous`.

The public reason token MUST NOT include the referenced service id or previous throwable message.

## Definition-validation failures (MUST)

Invalid canonical definitions use:

```text
Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException
```

The stable error code is:

```text
CORETSIA_CONTAINER_DEFINITION_INVALID
```

The stable public message token is:

```text
container-definition-invalid
```

The allowed reasons are:

```text
definition-invalid
reference-invalid
provider-invalid
required-service-invalid
```

The public exception message MUST be:

```text
CORETSIA_CONTAINER_DEFINITION_INVALID: container-definition-invalid
```

The public message and reason MUST NOT contain:

- service ids;
- class names;
- method names;
- raw arguments;
- parameter values;
- tag metadata;
- config values;
- filesystem paths;
- source snippets;
- environment values;
- secrets;
- throwable messages;
- previous throwable messages.

A causal throwable MAY be retained through `previous` for in-process debugging, but its message MUST NOT be copied into the public token or reason.

## Security and redaction (MUST)

Definition production, normalization, application, and diagnostics MUST prefer safe tokens, bounded counts, safe ids, and omission over raw values.

The canonical model and its public failures MUST NOT expose:

- raw config values;
- raw env values;
- dotenv values;
- credentials;
- tokens;
- private keys;
- cookies;
- authorization headers;
- request or response bodies;
- raw queue payloads;
- raw SQL;
- absolute paths;
- local usernames;
- hostnames;
- process ids;
- stack traces;
- throwable messages;
- private customer data;
- PII.

Service-id diagnostic redaction remains owned by the Foundation diagnostics policy in `docs/ssot/di-tags-and-middleware-ordering.md`.

Syntactic service-id validity MUST NOT be confused with diagnostic readability.

## Production-path boundary (MUST)

The canonical model and source adapter define source-runtime application.

Foundation, Kernel, and Worker source-runtime wiring uses provider-owned canonical definitions.

Production artifact compilation also consumes provider-owned canonical definitions.

The active production path is:

```text
ModuleResolution + compiled Phase-B config
    -> RuntimeContainerGraphCompiler
    -> ContainerProviderPlanResolver
    -> ordered provider definition collection
    -> ordered ContainerDefinitionSet merge
    -> ContainerCompiler
    -> DefinitionGraph
    -> ContainerGraphCompletenessValidator
```

`ArtifactCompiler` and `CacheVerifier` MUST use this same graph-production path.

Neither operation may accept a raw descriptor iterable.

Other `ServiceProviderInterface` implementations MAY remain imperative when they do not implement `ContainerDefinitionProviderInterface`, but an imperative-only provider cannot enter a production `ContainerProviderPlan`.

The canonical model is not a second production runtime boot path.

Production artifact-only runtime boot MUST continue to follow `docs/ssot/compiled-container.md`.

Production boot MUST NOT run declarative providers as a fallback when the compiled artifact is missing or invalid.

A resolved `ContainerProviderPlan`:

- identifies and orders declarative provider classes;
- contains no provider instances;
- contains no definition operations;
- does not authorize a second Composer metadata read.

`RuntimeContainerGraphCompiler` owns provider instantiation and definition collection after provider planning.

Collection MUST consume the existing plan order and the same `ModuleResolution` snapshot.

### Active Worker compilation boundary (MUST)

`WorkerServiceProvider::define()` provides the canonical closure-free Worker runtime contribution.

When `WorkerServiceProvider` is declared by an enabled module, production artifact compilation MUST:

- resolve it through `ContainerProviderPlanResolver`;
- instantiate it only at its ordered collection step;
- invoke its canonical `define()` implementation;
- merge its definition set with the other enabled provider sets;
- validate the final graph before artifact write.

The active boundary is:

- source mode applies the Worker provider contribution;
- production compile mode consumes the same Worker provider contribution;
- provider-plan resolution itself does not instantiate providers;
- `RuntimeContainerGraphCompiler` performs ordered provider execution;
- artifact-only boot does not execute Worker providers as fallback;
- no parallel Worker descriptor source is allowed.

## Non-goals / Clarifications (MUST)

- This document does not define `container@1` payload fields.
- This document does not define the global artifact envelope.
- This document does not define Kernel `DefinitionGraph` internals.
- This document does not require every `ServiceProviderInterface` implementation to implement `ContainerDefinitionProviderInterface`.
- This document does not make provider execution part of artifact-only runtime boot.
- The Foundation source adapter does not validate final required-service completeness; Kernel production graph compilation does.
- This document does not own module-provider ordering; it consumes the order resolved under ADR-0024 when compile-time orchestration supplies one.
- This document does not define `ModuleResolution` or `ContainerProviderPlan` construction.
- This document does not instantiate providers during provider-plan resolution.
- This document does not define config merge order.
- This document does not define config provenance.
- This document does not define tag identifier ownership rows.
- This document does not define middleware slot contents.
- This document does not define reset orchestration.
- This document does not allow runtime object references in definition values.
- This document does not treat callable-shaped list data as executable behavior.
- This document does not allow closures to cross into descriptor streams or artifacts.

## Correct usage examples

### Producing one definition set

```php
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;

$context = new ContainerDefinitionContext($compiledConfig);
$definitions = new ContainerDefinitionBuilder();

$definitions
    ->parameter('app.name', 'Coretsia')
    ->classService(
        id: AppService::class,
        class: AppService::class,
        arguments: [
            ContainerValueReference::parameter('app.name'),
        ],
    )
    ->tag(
        tag: 'app.service',
        serviceId: AppService::class,
    );

$set = $definitions->build();
```

The builder preserves call order and returns an immutable set.

### Applying one complete set in source mode

```php
$container = (new ContainerBuilder($compiledConfig))
    ->applyDefinitions($set)
    ->build();
```

The complete set is applied once.

### Registering declarative providers in source mode

```php
$container = (new ContainerBuilder($compiledConfig))
    ->register(
        new FoundationServiceProvider(),
        new KernelServiceProvider(),
        new WorkerServiceProvider(),
    )
    ->build();
```

All three providers contribute to one shared definition builder in deterministic caller-supplied order.

The complete definition set is built and applied once.

### Consuming an ordered provider plan in compile mode

Production compilation begins with:

```text
ModuleResolution + compiled Phase-B config
    -> RuntimeContainerGraphCompiler
    -> ContainerProviderPlanResolver
    -> ContainerProviderPlan
```

The orchestration layer processes provider classes in exact plan order.

For every planned provider class, it must:

1. instantiate the provider only at its ordered collection step;
2. create one fresh `ContainerDefinitionBuilder`;
3. invoke the canonical `define()` contribution with the shared compiled-config context;
4. build one immutable provider definition set;
5. append that set to the ordered provider-set list.

After all planned providers contribute, orchestration merges the sets:

```php
$completeSet = ContainerDefinitionSet::merge(
    ...$providerDefinitionSets,
);
```

It then delegates:

```text
complete ContainerDefinitionSet
    -> ContainerCompiler
    -> DefinitionGraph
    -> ContainerGraphCompletenessValidator
```

It MUST NOT:

- sort `ContainerProviderPlan::providerClasses()`;
- reorder provider definition sets before merge;
- apply provider sets independently;
- reread Composer metadata;
- rerun `ModulePlanResolver`;
- place provider objects into `ContainerDefinitionSet`;
- bypass final completeness validation.

### Combining already-built definition sets

```php
$completeSet = ContainerDefinitionSet::merge(
    $firstProviderSet,
    $secondProviderSet,
    $thirdProviderSet,
);

$containerBuilder->applyDefinitions($completeSet);
```

Set order and operation order are preserved.

Source provider registration SHOULD prefer one shared builder. Production compile orchestration intentionally builds one immutable set per planned provider and merges those sets in plan order.

### Preserving alias target lifecycle

```php
$definitions
    ->classService(
        id: RequestScopedService::class,
        class: RequestScopedService::class,
        shared: false,
    )
    ->alias(
        alias: 'request.service',
        serviceId: RequestScopedService::class,
    );
```

Repeated alias resolution delegates repeatedly to the non-shared target.

## Incorrect usage examples

### Returning a runtime closure from a declarative provider

```php
$definitions->parameter(
    'factory',
    static fn (): object => new Service(),
);
```

This is forbidden.

Executable behavior must use an explicit service construction kind.

### Passing a raw reference map through the builder API

```php
$definitions->classService(
    id: Consumer::class,
    class: Consumer::class,
    arguments: [
        ['id' => Dependency::class, 'type' => 'service'],
    ],
);
```

This is forbidden at the provider builder boundary.

Provider code must use:

```php
ContainerValueReference::service(Dependency::class)
```

### Applying one set per provider

```php
$containerBuilder->applyDefinitions($firstProviderSet);
$containerBuilder->applyDefinitions($secondProviderSet);
```

This is forbidden.

Provider contributions must be combined before one application.

### Sorting descriptor operations

```php
$operations = $set->toDescriptorStream();

usort(
    $operations,
    static fn (array $left, array $right): int =>
        strcmp((string) $left['kind'], (string) $right['kind']),
);
```

This is forbidden.

Sorting changes collision, parameter, and tag semantics.

## Test evidence

The canonical model SHOULD be locked by tests covering:

```text
framework/packages/core/foundation/tests/Contract/ContainerDefinitionSetRejectsRuntimeValuesContractTest.php
framework/packages/core/foundation/tests/Contract/ContainerDefinitionSetIsDeterministicContractTest.php
framework/packages/core/foundation/tests/Integration/ContainerDefinitionApplierPreservesLaterBindingTest.php
framework/packages/core/foundation/tests/Integration/ContainerDefinitionApplierPreservesTagFirstWinsTest.php
framework/packages/core/foundation/tests/Integration/ContainerDefinitionApplierPreservesSharedLifecycleTest.php
```

Kernel module-resolution and provider-plan behavior MUST additionally be locked by:

```text
framework/packages/core/kernel/tests/Contract/ComposerManifestReaderPreservesProviderOrderContractTest.php
framework/packages/core/kernel/tests/Integration/ModuleResolutionContainsManifestAndPlanTest.php
framework/packages/core/kernel/tests/Integration/ContainerProviderPlanUsesTopologicalModuleOrderTest.php
framework/packages/core/kernel/tests/Integration/ContainerProviderPlanPreservesDeclaredProviderOrderTest.php
framework/packages/core/kernel/tests/Integration/ContainerProviderPlanRejectsDuplicateProviderTest.php
framework/packages/core/kernel/tests/Integration/ContainerProviderPlanRejectsNonDefinitionProviderTest.php
```

Kernel production runtime-graph compilation MUST additionally be locked by:

```text
framework/packages/core/kernel/tests/Integration/RuntimeContainerGraphCompilerUsesProviderPlanTest.php
framework/packages/core/kernel/tests/Integration/RuntimeContainerGraphCompilerRejectsMissingRequiredServiceTest.php
framework/packages/core/kernel/tests/Integration/RuntimeContainerGraphCompilerAcceptsRuntimeSeedReferencesTest.php
framework/packages/core/kernel/tests/Integration/RuntimeContainerGraphCompilerRejectsRuntimeSeedOverrideTest.php
framework/packages/core/kernel/tests/Integration/ArtifactCompilerUsesProductionContainerGraphTest.php
framework/packages/core/kernel/tests/Integration/CacheVerifierUsesSameContainerGraphAsCompilerTest.php
```

Foundation, Kernel, and Worker provider integration SHOULD additionally be locked by:

```text
framework/packages/core/foundation/tests/Integration/FoundationProviderSourceDefinitionsParityTest.php
framework/packages/core/kernel/tests/Integration/KernelProviderSourceDefinitionsParityTest.php
framework/packages/platform/worker/tests/Contract/WorkerProviderDefinitionsContainNoClosuresContractTest.php
framework/packages/platform/worker/tests/Integration/WorkerProviderSourceDefinitionsParityTest.php
framework/packages/core/kernel/tests/Contract/KernelCompileHostServicesAreNotRuntimeDefinitionsContractTest.php
```

Worker lazy-resolution and runtime-seed behavior SHOULD additionally be locked by:

```text
framework/packages/platform/worker/tests/Integration/WorkerStartCommandResolvesSupervisorLazilyTest.php
framework/packages/platform/worker/tests/Integration/WorkerTaskFactorySelectsServiceLazilyTest.php
framework/packages/core/kernel/tests/Unit/RuntimePathContextValidationTest.php
```

Parity tests MUST compare:

```text
service ids
aliases
tags
shared flags
```

between source registration and canonical provider definitions.

Additional tests SHOULD cover:

- exact operation shapes;
- unknown operation rejection;
- malformed reference rejection;
- raw reference-map rejection at the builder boundary;
- descriptor reference-map acceptance at the validated-state boundary;
- deterministic nested map sorting;
- operation-order preservation after merge;
- required-service-id sorting and dedupe;
- one-complete-set application;
- parameter references using final later-wins values;
- alias preservation of non-shared target lifecycle;
- class-method factory validation;
- service-method factory runtime validation;
- missing factory service versus nested dependency failure;
- safe deterministic public exceptions;
- shared service-id and tag-name policy parity between imperative and declarative paths;
- declarative providers sharing one builder and context;
- one final build and application for a provider batch;
- mixed provider-registration mode rejection before execution;
- second declarative application rejection before mutation;
- one installed-manifest read per module-resolution run;
- `ModuleResolution` manifest/plan snapshot identity;
- topological module order in `ContainerProviderPlan`;
- declared provider order within each module;
- no provider FQCN sorting;
- no provider instances in `ContainerProviderPlan`;
- duplicate provider rejection;
- non-definition-provider rejection;
- `ContainerProviderPlanResolver` remaining outside runtime definitions;
- `ModulePlan` remaining free of provider metadata;
- Foundation runtime service parity;
- Kernel runtime service parity;
- Worker runtime service parity;
- Worker provider output containing no closures;
- `WorkerSupervisorInterface` lazy-resolution ordering;
- absence of legacy lifecycle compatibility services and duplicate control-server abstractions;
- presence of `WorkerControlClientInterface` and `WorkerSupervisorInterface` aliases;
- presence of `WorkerHealthCommand`;
- exact lazy selected-driver resolution without process-driver tags;
- lazy selection of only the canonical task-factory service;
- `RuntimePathContext::class` remaining visible as a required service id;
- runtime context path values remaining absent from definition values, artifact payload values, and fingerprint input;
- production artifact compilation consuming complete provider-produced definition sets;
- compile and verify using the same graph-production path;
- incomplete graphs failing before artifact write or expected-artifact comparison;
- runtime seed overrides failing deterministically;
- missing service, parameter, factory-service, alias, tag, and required-service edges failing deterministically;
- Kernel compile-host ids being absent from runtime definition operations and compiled graph topology;
- mandatory and possible container-owned graph lookups resolved through `ContainerInterface` having matching required-service declarations;
- optional mode-dependent preflight lookups remaining outside unconditional required-service declarations.

## Runtime acceptance scenario

When a declarative runtime provider contributes canonical container definitions:

1. orchestration supplies an already-compiled Phase-B config snapshot;
2. source orchestration creates one shared `ContainerDefinitionContext`;
3. declarative providers run in deterministic caller-supplied order;
4. Foundation, Kernel, and Worker providers append operations to one shared `ContainerDefinitionBuilder`;
5. the shared builder produces one complete immutable definition set;
6. source mode applies that set exactly once through `ContainerBuilder::applyDefinitions(...)`;
7. Foundation, Kernel, and Worker `register()` methods do not maintain parallel runtime wiring;
8. Kernel compile-host services and runtime-only seeds remain outside runtime definition contributions;
9. service, alias, and parameter collisions use later-wins behavior;
10. duplicate tag pairs use first-wins behavior;
11. source-container factory closures created for definition application exist only inside the Foundation adapter;
12. The task-work callback is created only during runtime service construction or execution and never enters canonical definitions; the PCNTL driver receives no child-bootstrap callback;
13. Worker source wiring defines supervisor, lazy selected-driver resolver, child-command builder, control, lifecycle-lock, process-driver, proc-host, readiness, and health-command services;
14. Worker source mode consumes `WorkerServiceProvider::define()`;
15. production compile-time orchestration obtains one `ModuleResolution` and resolves one ordered `ContainerProviderPlan`;
16. provider planning uses module topological order followed by declared provider order and creates no provider instances;
17. `RuntimeContainerGraphCompiler` instantiates providers in plan order and invokes the same canonical provider contributions;
18. one immutable definition set is built per provider and all sets are merged in exact plan order;
19. `ContainerCompiler` produces one deterministic `DefinitionGraph`;
20. `ContainerGraphCompletenessValidator` rejects incomplete runtime topology before artifact write or expected-artifact comparison;
21. `ArtifactCompiler` and `CacheVerifier` use the same graph-production path;
22. production artifact-only boot consumes approved compiled artifacts and does not run providers as a fallback.

Steps 1–13 describe the active source-runtime definition flow.

Steps 14–20 describe the active production graph-compilation flow.

Step 21 remains the separate artifact-only runtime boot boundary.

## Cross-references

- [SSoT Index](./INDEX.md)
- [DI Container, Tags, and Middleware Ordering](./di-tags-and-middleware-ordering.md)
- [Compiled Container Payload and Artifact-Only Boot Semantics](./compiled-container.md)
- [Process-Exec Descriptor Safety](./process-exec-descriptor-safety.md)
- [JSON-like Runtime Values](./json-like-runtime-values.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Config Merge Order](./config-merge-order.md)
- [Kernel Bootstrap Phase A](../adr/ADR-0023-kernel-bootstrap-phase-a.md)
- [ADR-0030: Canonical Runtime Container Definitions](../adr/ADR-0030-canonical-runtime-container-definitions.md)
- [ADR-0032: Process-Exec Descriptor Safety](../adr/ADR-0032-process-exec-descriptor-safety.md)
- [ADR-0017: Persistent worker supervisor and application worker](../adr/ADR-0017-persistent-worker-supervisor-application-worker.md)
- [Worker Architecture](../architecture/worker.md)
- [Modules and Manifests](./modules-and-manifests.md)
- [ADR-0024: Kernel Module Plan Resolution](../adr/ADR-0024-kernel-module-plan-resolution.md)
