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

This document is introduced by work item `G2-01`.

It does not define the Kernel-owned `container@1` payload schema or artifact-only runtime boot policy. Those remain owned by `docs/ssot/compiled-container.md`.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Coretsia needs one canonical deterministic representation of runtime DI wiring that can be produced by declarative providers and consumed consistently by:

```text
Foundation source runtime application
Kernel container compilation
artifact generation derived from Kernel compilation
```

Artifact-only runtime boot consumes the compiled artifact derived from this model. It does not execute definition providers or use this source model as a production fallback.

The model must prevent source and compiled runtime behavior from drifting in:

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
- definition-validation exception taxonomy;
- the boundary between the canonical model and artifacts.

This document does not own:

- the global artifact envelope;
- artifact header fields;
- the `container@1` artifact payload schema;
- Kernel `DefinitionGraph` shape;
- artifact-only boot failure taxonomy;
- artifact production orchestration;
- fingerprint behavior;
- cache verification behavior;
- global config merge semantics;
- tag identifier ownership rows;
- middleware slot ownership;
- reset orchestration;
- runtime provider migration scheduling.

## Ownership boundary (MUST)

The canonical model is owned by:

```text
core/foundation
```

It MUST NOT be moved to `core/contracts`.

The model is coupled to Foundation DI semantics and is not a technology-neutral framework port.

It MUST NOT be moved to `core/kernel`.

Foundation source providers must be able to describe Foundation DI wiring without depending on Kernel.

Kernel MAY depend on and consume the Foundation model or its exported descriptor stream.

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

These implementation points do not change this document's authority boundary.

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

A provider MUST be deterministic for the same:

- provider implementation state;
- caller-supplied provider order;
- already-compiled Phase-B config snapshot.

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

The same provider implementation SHOULD be used by both modes when that provider has been migrated to the declarative SPI.

Existing imperative `ServiceProviderInterface` providers MAY remain during staged migration.

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

Provider order is caller-supplied and significant.

The model MUST NOT infer a provider order from provider FQCNs.

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

The source `ContainerDefinitionApplier` does not validate final required-service completeness in G2-01.

Completeness validation belongs to a final graph/runtime-seed-aware validator introduced by later work.

Compiler or orchestration code MUST NOT silently discard required ids when that completeness validator is introduced.

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

Runtime closures created by the adapter are implementation details.

They MUST NOT be written back into the canonical set or descriptor stream.

### One-complete-set rule (MUST)

One `ContainerBuilder` may apply exactly one complete declarative definition set.

Repeated calls to `applyDefinitions()` on the same builder MUST fail deterministically.

Multiple provider contributions MUST first be aggregated through:

- one shared `ContainerDefinitionBuilder`; or
- `ContainerDefinitionSet::merge(...)`.

This is required so final parameter values do not depend on per-provider application grouping.

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

G2-01 introduces the canonical model and source adapter.

It does not change production artifact flow.

Current imperative providers MAY remain.

The new model is not a second production runtime boot path.

Production artifact-only runtime boot MUST continue to follow `docs/ssot/compiled-container.md`.

Production boot MUST NOT run declarative providers as a fallback when the compiled artifact is missing or invalid.

Kernel compiler adoption of this Foundation model requires explicit later integration work.

## Non-goals / Clarifications (MUST)

- This document does not define `container@1` payload fields.
- This document does not define the global artifact envelope.
- This document does not define Kernel `DefinitionGraph` internals.
- This document does not migrate every imperative provider.
- This document does not make provider execution part of artifact-only runtime boot.
- This document does not validate final required-service completeness.
- This document does not define module-provider ordering.
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

### Combining provider contributions

```php
$completeSet = ContainerDefinitionSet::merge(
    $firstProviderSet,
    $secondProviderSet,
    $thirdProviderSet,
);

$containerBuilder->applyDefinitions($completeSet);
```

Set order and operation order are preserved.

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
- shared service-id and tag-name policy parity between imperative and declarative paths.

## Runtime acceptance scenario

When a migrated runtime provider contributes canonical container definitions:

1. orchestration supplies an already-compiled Phase-B config snapshot;
2. orchestration creates one `ContainerDefinitionContext`;
3. providers run in deterministic caller-supplied order;
4. providers append operations to one builder or produce sets that are later merged in caller-supplied order;
5. the complete immutable set preserves semantic operation order;
6. source mode applies the complete set exactly once through `ContainerBuilder::applyDefinitions(...)`;
7. service, alias, and parameter collisions use later-wins behavior;
8. duplicate tag pairs use first-wins behavior;
9. source runtime closures exist only inside the adapter;
10. compile mode exports the same descriptor stream to the Kernel compiler;
11. production artifact-only boot consumes the resulting compiled artifact and does not run providers as a fallback.

## Cross-references

- [SSoT Index](./INDEX.md)
- [DI Container, Tags, and Middleware Ordering](./di-tags-and-middleware-ordering.md)
- [Compiled Container Payload and Artifact-Only Boot Semantics](./compiled-container.md)
- [JSON-like Runtime Values](./json-like-runtime-values.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Config Merge Order](./config-merge-order.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
- [ADR-0030: Canonical Runtime Container Definitions](../adr/ADR-0030-canonical-runtime-container-definitions.md)
