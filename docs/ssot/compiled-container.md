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

# Compiled Container Payload and Artifact-Only Boot Semantics (SSoT)

This document is the canonical SSoT for the Kernel-owned REAL `container@1` compiled-container payload schema, compiled-container graph rules, service definition lifecycle semantics, compiled alias lifecycle semantics, and artifact-only runtime boot policy.

It intentionally does not redefine the global artifact envelope, canonical artifact header fields, deterministic serialization law, or artifact registry rows. Those are owned by `docs/ssot/artifacts.md`.

It intentionally does not define Kernel artifact production orchestration, fingerprint input construction, fingerprint exclusions, artifact output path policy, or cache verification classification. Those are owned by `docs/ssot/artifacts-and-fingerprint.md` and `docs/ssot/cache-verify.md`.

## Goal

`container@1` is the Kernel-owned compiled-container artifact identity.

For the same deterministic compiled-container inputs, Coretsia must be able to produce the same REAL `container.php` bytes and build the same runtime Foundation container behavior without reading source config files, discovering modules, running providers as a fallback, or compiling a new container during production runtime boot.

This document defines:

- the first REAL `container@1` payload schema;
- the deterministic compiled graph shape used by that payload;
- service definition lifecycle semantics;
- compiled alias lifecycle semantics;
- parameter bag semantics;
- tag payload semantics;
- closure/callable rejection semantics;
- artifact-only runtime boot inputs;
- missing/invalid container artifact failure semantics.

## Authority Boundary (MUST)

This document owns only compiled-container-specific rules for:

- REAL `container@1` payload fields;
- compiled service definition schema;
- compiled parameter bag schema;
- compiled alias schema;
- compiled tag schema;
- descriptor-to-definition-graph compile semantics;
- compiled-container closure/callable rejection semantics;
- compiled-container runtime boot semantics;
- compiled-container missing/invalid artifact failure semantics;
- legacy `1.330.0` stub payload rejection semantics.

This document **MUST NOT** redefine:

- the canonical artifact envelope shape;
- canonical artifact header fields;
- canonical artifact registry rows;
- global deterministic serialization law;
- Kernel artifact path policy;
- Kernel fingerprint input construction;
- Kernel fingerprint exclusions;
- Kernel cache clean/dirty/invalid classification;
- global observability metric catalog or label allowlist;
- Foundation container provider ordering policy;
- Foundation tag registry ownership;
- reset orchestration semantics;
- platform routing artifacts such as `routes@1`.

Those rules remain owned by their canonical SSoT documents.

## Relationship to Global Artifact Law (MUST)

`container@1` **MUST** remain compatible with the global artifact envelope and deterministic serialization law defined by `docs/ssot/artifacts.md`.

The REAL compiled-container artifact **MUST** use the canonical Kernel artifact envelope:

```php
[
    '_meta' => <canonical artifact header>,
    'payload' => <compiled-container payload>,
]
```

This document describes only the `payload` value for the `container@1` artifact.

This document **MUST NOT** add, remove, rename, or redefine artifact header fields.

This document **MUST NOT** add, remove, rename, or redefine artifact registry rows.

## `container@1` Schema Evolution Note (MUST)

The `1.330.0` `container@1` payload was a transitional deterministic stub used to reserve and materialize the `container.php` artifact slot before REAL compiled-container semantics existed.

The transitional stub shape was:

```text
kind = stub
compiled = false
```

That transitional payload is no longer a supported production runtime container format.

Current Kernel-produced `container@1` artifacts **MUST** use REAL compiled-container semantics:

```text
kind = compiled
compiled = true
```

This REAL payload remains compatible with the `container@1` artifact identity.

This document does not introduce `container@2`.

A future `container@2` is required only if a later change needs to preserve an already-stable REAL `container@1` payload contract while introducing an incompatible compiled-container payload format.

## REAL `container@1` Payload Shape (MUST)

The REAL `container@1` payload **MUST** be a deterministic map with exactly these top-level fields:

```text
aliases
compiled
kind
parameters
services
tags
```

The canonical payload shape is:

```php
[
    'aliases' => <alias map>,
    'compiled' => true,
    'kind' => 'compiled',
    'parameters' => <parameter map>,
    'services' => <service-definition map>,
    'tags' => <tag discovery map>,
]
```

The payload **MUST** satisfy:

```text
kind = compiled
compiled = true
```

The payload **MUST NOT** satisfy:

```text
kind = stub
compiled = false
```

The payload **MUST NOT** contain unknown top-level fields.

The payload top-level map fields **MUST** be deterministic maps:

```text
aliases
parameters
services
tags
```

Empty maps are valid:

```php
[
    'aliases' => [],
    'compiled' => true,
    'kind' => 'compiled',
    'parameters' => [],
    'services' => [],
    'tags' => [],
]
```

An empty compiled graph is a valid REAL `container@1` payload.

## Deterministic Value Law (MUST)

Compiled-container payload values **MUST** be deterministic schema values only.

Allowed value forms are:

```text
null
bool
int
string
list<value>
map<string, value>
```

Compiled-container payload values **MUST NOT** contain:

- floats;
- objects;
- resources;
- closures;
- anonymous functions;
- callable objects;
- raw PHP callable arrays as runtime data;
- reflection objects;
- source snippets;
- absolute paths;
- raw env values;
- raw config payload dumps;
- secrets;
- process ids;
- hostnames;
- usernames;
- timestamps;
- filesystem metadata.

Map keys owned by compiled-container payloads **MUST** be deterministic strings.

Map keys owned by compiled-container payloads **MUST** be sorted by byte-order string comparison where the producing component owns map ordering.

Lists preserve caller-supplied order when list order is semantic.

## Compile Input Semantics (MUST)

Compiled-container input **MUST** be descriptor-based and closure-free.

`ContainerCompiler` owns deterministic descriptor-to-`DefinitionGraph` compilation.

The descriptor stream order is caller-owned and semantically significant.

`ContainerCompiler` **MUST NOT** globally re-sort providers, modules, or descriptors before applying binding collision semantics.

Compiled-container compile semantics **MUST** preserve the Foundation binding collision policy:

- later service binding overrides earlier service binding for the same service id;
- later alias binding overrides earlier alias binding for the same alias;
- later parameter binding overrides earlier parameter binding for the same parameter name;
- duplicate tag registration for the same `(tag, serviceId)` is ignored after the first registration.

`ContainerCompiler` **MUST NOT**:

- read source config files;
- read generated artifacts;
- write artifacts;
- calculate fingerprints;
- resolve `BootstrapConfig`;
- resolve `ModulePlan`;
- run provider-based runtime boot;
- instantiate runtime services while compiling the graph;
- emit stdout or stderr;
- instantiate Noop observability implementations directly.

## `DefinitionGraph` Semantics (MUST)

`DefinitionGraph` is the Kernel-owned deterministic compiled-container graph model.

It is an internal compilation model, not a public DTO marker class.

It **MUST** contain only exported deterministic array data for:

```text
aliases
parameters
services
tags
```

It **MUST NOT** store `ServiceDefinition`, `ParameterBag`, `TagRegistry`, `TaggedService`, or runtime object instances as payload state.

It **MUST NOT** use PHP object identity as compiled payload state.

The exported graph shape **MUST** be:

```php
[
    'aliases' => <alias map>,
    'parameters' => <parameter map>,
    'services' => <service-definition map>,
    'tags' => <tag discovery map>,
]
```

## Service Definition Schema (MUST)

The `services` payload field **MUST** be a deterministic map:

```php
[
    '<service-id>' => <service definition>,
]
```

Each service definition **MUST** be a deterministic map with exactly these fields:

```text
arguments
construction
id
shared
type
```

The canonical service definition shape is:

```php
[
    'arguments' => <argument list>,
    'construction' => <construction map>,
    'id' => '<service-id>',
    'shared' => <bool>,
    'type' => '<service type>',
]
```

The `id` field **MUST** equal the surrounding service map key.

The `shared` field **MUST** be a boolean.

The `arguments` field **MUST** be a list.

The `construction` field **MUST** be a deterministic map.

Unknown service definition fields **MUST** be rejected.

## Service Definition Lifecycle Subsection (MUST)

Compiled service definitions carry lifecycle through the `shared` field.

The lifecycle values are:

```text
shared = true
shared = false
```

`shared = true` means the runtime Foundation container resolves the service once per container instance and stores the resolved value in the resolved-instance cache.

`shared = false` means the runtime Foundation container resolves the service on every `Container::get($id)` call and **MUST NOT** store the resolved value in the resolved-instance cache.

The default compile-side lifecycle for service definitions is `shared = true` unless the descriptor explicitly defines otherwise.

The compiled `shared` field applies only to compiled service definitions.

It does not apply to:

- compiled aliases;
- tag registrations;
- parameter values;
- runtime support instances injected by the compiled-container runtime boot boundary.

`ContainerBuilder::instance(...)` semantics remain Foundation-owned and represent already-created shared runtime instances.

## Service Type: `class` (MUST)

A class service definition **MUST** use:

```text
type = class
```

Its construction map **MUST** use exactly this shape:

```php
[
    'class' => '<class-reference>',
]
```

The `class` value **MUST** be a deterministic class reference string.

The runtime container MAY instantiate the class through reflection using the resolved `arguments` list.

The construction map **MUST NOT** contain closures, callable arrays, object instances, reflection objects, absolute paths, source snippets, or runtime callable payloads.

## Service Type: `factory` (MUST)

A factory service definition **MUST** use:

```text
type = factory
```

Its construction map **MUST** use exactly one nested `factory` map:

```php
[
    'factory' => <factory map>,
]
```

The factory map **MUST** use one of the canonical factory shapes defined below.

The factory map **MUST NOT** use legacy flat construction keys such as:

```text
factoryClass
factoryServiceId
serviceId
type
```

Those flat keys are not the canonical REAL `container@1` factory construction shape.

### Factory Class-Method Shape (MUST)

A factory class-method construction **MUST** use:

```php
[
    'factory' => [
        'class' => '<factory-class-reference>',
        'kind' => 'class-method',
        'method' => '<method-name>',
    ],
]
```

The nested factory map **MUST** contain exactly these fields:

```text
class
kind
method
```

The `kind` field **MUST** be:

```text
class-method
```

The `class` field **MUST** be a deterministic class reference string.

The `method` field **MUST** be a safe method name.

Runtime invocation **MUST** call a public static method on the factory class.

### Factory Service-Method Shape (MUST)

A factory service-method construction **MUST** use:

```php
[
    'factory' => [
        'kind' => 'service-method',
        'method' => '<method-name>',
        'service' => '<factory-service-id>',
    ],
]
```

The nested factory map **MUST** contain exactly these fields:

```text
kind
method
service
```

The `kind` field **MUST** be:

```text
service-method
```

The `service` field **MUST** reference a known compiled service id or known compiled alias id.

The `service` field **MUST NOT** reference a reserved runtime support service id.

The `method` field **MUST** be a safe method name.

Runtime invocation **MUST** resolve the factory service from the compiled runtime container and then call the named method.

## Argument and Reference Schema (MUST)

Compiled service arguments **MUST** be represented as a list.

Argument values **MUST** use deterministic value forms only.

Compiled-container references **MUST** be represented as deterministic maps.

### Service Reference (MUST)

A service reference argument **MUST** use exactly this shape:

```php
[
    'id' => '<service-id>',
    'type' => 'service',
]
```

The referenced id **MUST** be a known compiled service id, known compiled alias id, or allowed reserved runtime support id.

The reserved runtime support ids are:

```text
Coretsia\Foundation\Container\Container
Psr\Container\ContainerInterface
Coretsia\Foundation\Tag\TagRegistry
```

Compiled service and alias definitions **MUST NOT** define or shadow reserved runtime support ids.

### Parameter Reference (MUST)

A parameter reference argument **MUST** use exactly this shape:

```php
[
    'name' => '<parameter-name>',
    'type' => 'parameter',
]
```

The referenced parameter name **MUST** exist in the compiled `parameters` payload map.

### Class Reference (MUST)

A class reference argument **MUST** use exactly this shape:

```php
[
    'class' => '<class-reference>',
    'type' => 'class',
]
```

The class reference **MUST** be deterministic schema data.

It **MUST NOT** imply runtime object identity.

## Parameter Bag Schema (MUST)

The `parameters` payload field **MUST** be a deterministic map:

```php
[
    '<parameter-name>' => <deterministic value>,
]
```

Empty parameter maps are valid.

Parameter names **MUST** be deterministic safe strings.

Parameter values **MUST** use deterministic schema values only.

Parameter values **MUST NOT** contain:

- floats;
- objects;
- resources;
- closures;
- anonymous functions;
- callable objects;
- raw PHP callable arrays;
- reflection objects;
- source snippets;
- absolute paths;
- raw env values;
- secrets.

The parameter bag **MUST NOT** duplicate the full `config@1` compiled config payload.

The following parameter names are reserved and **MUST NOT** be accepted as compiled parameter names:

```text
config
compiledConfig
compiled_config
```

The parameter bag may contain intentionally selected deterministic parameter values, but it is not the canonical runtime config snapshot.

Runtime config snapshot ownership remains with the already-read and already-validated `config@1` payload.

## Alias Schema (MUST)

The `aliases` payload field **MUST** be a deterministic map:

```php
[
    '<alias-id>' => '<target-service-id>',
]
```

Empty alias maps are valid.

Alias ids **MUST** be deterministic safe service-id strings.

Alias targets **MUST** be deterministic safe service-id strings.

Alias ids **MUST NOT** equal their target ids.

Alias ids **MUST NOT** conflict with compiled service ids.

Alias ids **MUST NOT** define or shadow reserved runtime support service ids.

Alias targets **MUST** point to known compiled service ids or known compiled alias ids.

Alias targets **MUST NOT** point to reserved runtime support service ids.

## Alias Lifecycle Rule for Compiled Aliases (MUST)

Compiled aliases **MUST** be runtime delegation wrappers.

Compiled aliases **MUST** be registered as non-shared runtime definitions.

Compiled aliases **MUST NOT** own independent lifecycle state.

Compiled aliases **MUST NOT** convert a non-shared target into a shared target.

Compiled aliases **MUST NOT** cache the resolved target.

Each alias resolution **MUST** delegate to the target service id through the runtime container.

The target service definition owns lifecycle through its own `shared` field.

## Tag Schema (MUST)

The `tags` payload field **MUST** be a deterministic map:

```php
[
    '<tag-name>' => [
        [
            'id' => '<service-id>',
            'priority' => <int>,
        ],
    ],
]
```

Empty tag maps are valid.

Tag names **MUST** be deterministic safe tag strings.

Each tag entry **MUST** contain exactly these fields:

```text
id
priority
```

The `id` field **MUST** be a deterministic service id string.

The `priority` field **MUST** be an integer.

Tag entry lists **MUST** preserve canonical Foundation discovery order:

```text
priority DESC
id ASC
```

The `id ASC` comparison **MUST** use byte-order string comparison.

Duplicate service ids inside the same tag list **MUST** be rejected in existing artifact validation.

Compile-time tag duplicate handling **MUST** preserve canonical Foundation first-wins semantics per `(tag, serviceId)`.

Tag metadata **MUST NOT** be emitted into the compiled-container tag payload.

Owner-defined tag metadata may exist in compile-time descriptor input or Foundation `TagRegistry`, but the REAL `container@1` tag payload requires only deterministic service id and priority data.

## Foundation Tag and Reset Linkage (MUST)

Compiled-container tag payload semantics **MUST** preserve Foundation tag discovery semantics.

Compiled-container code **MUST NOT** invent a second tag ordering rule.

Compiled-container code **MUST NOT** invent a second tag dedupe rule.

Reset discovery semantics remain Foundation/reset-owned.

The compiled-container payload may carry the service ids and priorities required for reset discovery, but it **MUST NOT** redefine reset tag ownership, reset ordering semantics, reset failure taxonomy, or reset-specific observability.

Those rules are owned by:

```text
docs/ssot/di-tags-and-middleware-ordering.md
docs/ssot/reset-tags.md
```

## Closure and Callable Rejection Semantics (MUST)

Compiled-container compile and artifact production **MUST** reject any closure, anonymous function, callable object, raw PHP callable array, object instance, resource, reflection object, source snippet, absolute path, runtime callable payload, raw env value, or secret before such data can become serialized `container@1` payload state.

Closure rejection is a compiled-container semantic invariant.

It is not a generic PHP-source static gate.

Runtime provider closures may exist in non-artifact builder mode, compile-time wiring, tests, or provider-based scaffolding, but any definition that reaches the compiled-container graph **MUST** be represented as deterministic schema data.

Factory behavior **MUST** be represented by deterministic class references, service ids, method names, service references, parameter references, and scalar/list/map arguments.

Factory behavior **MUST NOT** be represented by serialized PHP callables or closures.

Compile failures for invalid compiled-container input **MUST** use:

```text
CORETSIA_CONTAINER_COMPILE_FAILED
container-compile-failed
```

Diagnostics for compile failures **MUST NOT** expose:

- closure dumps;
- source snippets;
- absolute paths;
- raw config values;
- raw env values;
- raw payload dumps;
- OS error messages;
- stack traces;
- throwable messages;
- previous throwable messages.

## Artifact Production Linkage (MUST)

`ContainerCompiler` owns descriptor-to-`DefinitionGraph` compilation.

`CompiledContainerBuilder` owns wrapping the compiled `DefinitionGraph` payload in the canonical Kernel artifact envelope through `ArtifactEnvelopeFactory`.

`CompiledContainerBuilder` **MUST** build the REAL `container@1` payload shape defined by this document.

`CompiledContainerBuilder` **MUST** set:

```text
kind = compiled
compiled = true
```

`CompiledContainerBuilder` **MUST NOT**:

- compile the container graph;
- calculate fingerprints;
- assemble artifact envelopes manually;
- read files;
- write files;
- validate existing artifacts;
- instantiate runtime services;
- emit stdout or stderr.

Fingerprint calculation, artifact writing, artifact path policy, and cache verification linkage are owned by `docs/ssot/artifacts-and-fingerprint.md`.

## Existing Artifact Validation Semantics (MUST)

Existing `container@1` artifacts **MUST** be validated by artifact header semantics and compiled-container payload schema semantics.

Validation **MUST NOT** rely on PHP object identity.

Validation **MUST NOT** rely on runtime class type checks as the source of truth for artifact validity.

A valid REAL `container@1` artifact payload **MUST** satisfy:

```text
kind = compiled
compiled = true
```

A legacy transitional stub payload **MUST** be rejected for production runtime boot:

```text
kind = stub
compiled = false
```

Existing artifact validation **MUST** reject:

- invalid envelope shape;
- invalid header semantics;
- wrong artifact name;
- wrong schema version;
- missing payload fields;
- unknown payload fields;
- non-REAL payload kind;
- `compiled !== true`;
- legacy stub payloads;
- invalid service definition shapes;
- invalid parameter maps;
- invalid alias maps;
- invalid tag maps;
- duplicate ids inside a tag entry list;
- tag entries not ordered by `priority DESC, id ASC`;
- floats, objects, resources, closures, callable payloads, raw source snippets, raw env values, and other non-deterministic payload values.

## Artifact-Only Runtime Boot Inputs (MUST)

Production runtime boot paths covered by compiled-container semantics **MUST** use artifact-only boot.

The artifact-only runtime boot inputs are:

```text
container@1
already-read/validated config@1 payload
```

`CompiledContainerFactory` **MUST** read the `container@1` artifact through Kernel artifact reading infrastructure.

`CompiledContainerFactory` **MUST** receive the `config@1` payload from the caller as an already-read and already-validated payload.

`CompiledContainerFactory` **MUST** use the `config` field from the `config@1` payload as the runtime Foundation container config snapshot.

`CompiledContainerFactory` **MUST NOT** read source config files.

`CompiledContainerFactory` **MUST NOT** run source config discovery.

`CompiledContainerFactory` **MUST NOT** run module discovery.

`CompiledContainerFactory` **MUST NOT** run providers as an implicit fallback.

`CompiledContainerFactory` **MUST NOT** compile a new container during production runtime boot.

`CompiledContainerFactory` **MUST NOT** calculate fingerprints.

`CompiledContainerFactory` **MUST NOT** write artifacts.

`CompiledContainerFactory` **MUST NOT** mutate existing artifacts.

`CompiledContainerFactory` **MUST NOT** emit stdout or stderr.

Provider-based container construction remains allowed only for:

- compile-time artifact production;
- test scaffolding;
- explicitly documented non-production paths outside this SSoT.

Any future developer-mode fallback requires a separate epic/ADR and **MUST NOT** be implied by this document.

## Runtime Container Construction Semantics (MUST)

Runtime container construction from `container@1` **MUST** be based only on deterministic compiled graph entries.

Compiled service definitions **MUST** be registered into the Foundation container as runtime factories using their compiled `shared` lifecycle value.

Compiled aliases **MUST** be registered as non-shared delegation factories.

The runtime `TagRegistry` **MUST** be derived from the compiled `tags` payload and injected as a runtime support instance.

Reserved runtime support ids **MUST** be available for service reference resolution where explicitly allowed:

```text
Coretsia\Foundation\Container\Container
Psr\Container\ContainerInterface
Coretsia\Foundation\Tag\TagRegistry
```

Compiled service ids and alias ids **MUST NOT** conflict with reserved runtime support ids.

## Missing Artifact Failure Semantics (MUST)

If the required `container.php` artifact is missing during artifact-only runtime boot, runtime boot **MUST** fail deterministically.

Missing artifact failure **MUST** use:

```text
CORETSIA_CONTAINER_ARTIFACT_MISSING
container-artifact-missing
```

The missing artifact exception **MUST NOT** expose:

- the missing filesystem path;
- absolute paths;
- configured path strings;
- OS error messages;
- stack traces;
- filesystem details;
- previous throwable messages.

## Invalid Artifact Failure Semantics (MUST)

If `container.php` exists but cannot be accepted as a production REAL `container@1` artifact, runtime boot **MUST** fail deterministically.

Invalid artifact failure **MUST** use:

```text
CORETSIA_CONTAINER_ARTIFACT_INVALID
container-artifact-invalid
```

Invalid artifact failure covers:

- unreadable artifacts;
- read failures;
- non-array PHP return values;
- invalid envelope shape;
- invalid header semantics;
- wrong artifact name;
- wrong schema version;
- invalid payload shape;
- schema-invalid REAL payload data;
- legacy `1.330.0` stub payloads;
- non-REAL `container@1` payloads.

Invalid artifact diagnostics **MUST NOT** expose:

- absolute paths;
- raw artifact payloads;
- raw config values;
- raw env values;
- PHP warning text;
- closure dumps;
- source snippets;
- OS error messages;
- stack traces;
- object dumps;
- filesystem details;
- throwable messages;
- previous throwable messages.

## Observability Semantics (MUST)

Compiled-container compile observability is owned by `ContainerCompiler`.

`ContainerCompiler` **MUST** emit only safe bounded observability data.

The compile span name is:

```text
kernel.container_compile
```

The compile metrics are:

```text
kernel.container_compile_total
kernel.container_compile_duration_ms
```

The only compile metric label is:

```text
outcome
```

Allowed `outcome` values are:

```text
success
failure
```

Compiled-container compile observability **MUST** comply with the global observability SSoT.

This document does not own the global metrics catalog.

Observability failures **MUST NOT** alter compile behavior, compile results, exception precedence, artifact payload semantics, or runtime boot behavior.

Provider registration and factory wiring **MUST NOT** start spans, emit metrics, or write logs merely by registering compiled-container services.

## Provider Registration Non-Semantics (MUST)

Registering compiled-container services in a provider **MUST NOT** compile a container.

Provider registration **MUST NOT**:

- read source config files;
- read generated artifacts;
- write artifacts;
- calculate fingerprints;
- verify cache;
- run `ConfigKernel::compile(...)`;
- resolve `BootstrapConfig`;
- resolve `ModulePlan`;
- build `EnvRepositoryInterface`;
- run module discovery;
- run runtime providers as a fallback;
- invoke reset orchestration;
- start a UnitOfWork;
- emit stdout or stderr;
- start compiled-container spans;
- emit compiled-container metrics;
- write compiled-container logs.

Provider registration only wires services.

## Security and Redaction (MUST)

Compiled-container code **MUST** prefer safe tokens, omission, hashes, lengths, counts, and safe ids over raw values.

Compiled-container compile, payload construction, artifact validation, and artifact-only runtime boot **MUST NOT** expose:

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
- raw artifact payloads;
- raw fingerprint input;
- absolute paths;
- local usernames;
- hostnames;
- process ids;
- stack traces;
- throwable messages;
- previous throwable messages;
- PHP warning text;
- private customer data;
- PII.

Safe diagnostics may include:

```text
safe artifact names
safe basenames
safe status tokens
safe reason tokens
safe counts
safe hashes
safe lengths
safe source ids
safe service ids when explicitly safe
safe key paths
```

## Non-goals / Clarifications (MUST)

- This document does not define the global artifact envelope.
- This document does not define artifact header fields.
- This document does not define artifact registry rows.
- This document does not define Kernel artifact production orchestration.
- This document does not define Kernel fingerprint input construction.
- This document does not define Kernel fingerprint exclusions.
- This document does not define Kernel cache verification classification.
- This document does not define the global observability metrics catalog.
- This document does not define config root ownership.
- This document does not define the `config@1` payload schema.
- This document does not define `routes@1` production.
- This document does not define platform routing artifact behavior.
- This document does not define reset orchestration semantics.
- This document does not define middleware discovery semantics.
- This document does not make artifact generation part of runtime request lifecycle.
- This document does not require cache verification during normal provider registration.
- This document does not allow automatic runtime fallback when `container.php` is missing or invalid.
- This document does not preserve the `1.330.0` stub payload as a supported production runtime format.
- This document does not introduce `container@2`.

## Implementation Linkage

Canonical implementation points include:

```text
framework/packages/core/kernel/src/Container/ContainerCompiler.php
framework/packages/core/kernel/src/Container/CompiledContainerFactory.php
framework/packages/core/kernel/src/Container/Definition/ServiceDefinition.php
framework/packages/core/kernel/src/Container/Definition/ParameterBag.php
framework/packages/core/kernel/src/Container/Definition/DefinitionGraph.php
framework/packages/core/kernel/src/Artifacts/Builders/CompiledContainerBuilder.php
framework/packages/core/kernel/src/Artifacts/Verifier/ArtifactSchemaValidator.php
framework/packages/core/kernel/src/Artifacts/Php/PhpArtifactReader.php
framework/packages/core/kernel/src/Artifacts/ArtifactEnvelopeFactory.php
framework/packages/core/foundation/src/Tag/TagRegistry.php
framework/packages/core/foundation/src/Discovery/DeterministicOrder.php
```

These implementation points do not change this document's authority boundary.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Kernel Artifacts and Fingerprint Behavior](./artifacts-and-fingerprint.md)
- [Cache Verification Behavior](./cache-verify.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [DI Container, Tags, and Middleware Ordering](./di-tags-and-middleware-ordering.md)
- [Reset Tags and Long-Running Runtime Reset Semantics](./reset-tags.md)
- [DTO Policy](./dto-policy.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
