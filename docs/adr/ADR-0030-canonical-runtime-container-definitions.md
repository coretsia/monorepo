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

The same provider implementation may be used by source-mode and compile-mode orchestration.

This decision introduces the SPI and canonical model only. It does not migrate existing imperative providers in the same change.

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

### Decision 8: Apply one complete set once in source mode

`ContainerBuilder` adds:

```php
public function applyDefinitions(
    ContainerDefinitionSet $definitions,
): self;
```

A `ContainerBuilder` may apply exactly one complete declarative definition set.

Multiple provider contributions must be aggregated before source application through either:

- one shared `ContainerDefinitionBuilder`; or
- `ContainerDefinitionSet::merge(...)`.

The selected source flow is:

```text
provider contributions
  -> one complete ContainerDefinitionSet
  -> one ContainerBuilder::applyDefinitions(...)
```

Repeated per-provider build-and-apply calls are rejected because they would make final parameter semantics depend on application grouping.

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

### Decision 11: Keep the production path unchanged in G2-01

This decision does not make the new model an alternative production boot path.

Existing providers may remain imperative.

Production artifact generation and artifact-only runtime boot remain unchanged until later integration work explicitly switches Kernel compilation input to the Foundation-owned canonical model.

No new Composer dependency is required.

## Consequences

### Positive consequences

- Source runtime wiring and compiled-container input gain one canonical semantic model.
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
- Required runtime service ids are collected but are not validated by the source adapter in this work item.
- Kernel integration requires follow-up work before the canonical model becomes the actual compile input for production artifacts.

### Operational consequences

Source-mode orchestration must create one `ContainerDefinitionContext`, invoke providers in deterministic caller-supplied order, build one complete set, and apply it once.

Compile-mode orchestration must invoke the same provider sequence against the same canonical builder/context model and pass the resulting descriptor stream to the Kernel compiler.

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

### Alternative 7: Migrate production artifact flow in the same change

Rejected.

G2-01 introduces and validates the canonical model without making it an unreviewed parallel production path.

Kernel compiler adoption and production-flow integration require separate follow-up work.

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

The initial required test files are:

```text
framework/packages/core/foundation/tests/Contract/ContainerDefinitionSetRejectsRuntimeValuesContractTest.php
framework/packages/core/foundation/tests/Contract/ContainerDefinitionSetIsDeterministicContractTest.php
framework/packages/core/foundation/tests/Integration/ContainerDefinitionApplierPreservesLaterBindingTest.php
framework/packages/core/foundation/tests/Integration/ContainerDefinitionApplierPreservesTagFirstWinsTest.php
framework/packages/core/foundation/tests/Integration/ContainerDefinitionApplierPreservesSharedLifecycleTest.php
```

## Related SSoT

- `docs/ssot/runtime-container-definitions.md`
- `docs/ssot/di-tags-and-middleware-ordering.md`
- `docs/ssot/compiled-container.md`
- `docs/ssot/json-like-runtime-values.md`
- `docs/ssot/artifacts.md`

## Related ADR

- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/adr/ADR-0029-kernel-container-compile-artifact.md`
