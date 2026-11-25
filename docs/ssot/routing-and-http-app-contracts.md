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

# Routing and HttpApp Contracts SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: core/contracts
```

## Scope

This document is the Single Source of Truth for Coretsia routing contracts, route descriptor shape policy, route match shape policy, route provider semantics, router matching semantics, HttpApp invocation ports, runtime ownership boundaries, and no-PSR-7 dependency rules.

This document governs the contracts under:

```text
packages/core/contracts/src/Routing/
packages/core/contracts/src/HttpApp/
```

It complements:

```text
docs/ssot/tags.md
docs/ssot/observability.md
docs/ssot/dto-policy.md
docs/ssot/artifacts.md
docs/ssot/routing-artifact.md
docs/ssot/context-keys.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Routing and HttpApp runtime packages must be implementable without changing `core/contracts` and without making `core/contracts` depend on HTTP transport abstractions.

The routing and HttpApp contracts define only:

- format-neutral route descriptors;
- format-neutral route match descriptors;
- route provider ports;
- router matching ports;
- routing artifact readiness ports;
- action invocation ports;
- argument resolution ports;
- deterministic and safe boundary rules.

The contracts package MUST NOT implement routing runtime behavior, HTTP middleware, controller dispatch, filesystem route discovery, route compilation, route artifact generation, DI registration, or transport rendering.

## Contract boundary

Routing and HttpApp contracts are format-neutral.

They MUST NOT require:

- PSR-7 request objects;
- PSR-7 response objects;
- `platform/http` request objects;
- `platform/http` response objects;
- concrete middleware objects;
- concrete router implementations;
- concrete controller implementations;
- service container implementations;
- platform package classes;
- integration package classes;
- vendor SDKs;
- generated route artifacts.

The contracts package defines stable ports and descriptor shapes only.

Runtime owner packages own concrete behavior. `platform/routing` implements source compilation, artifact production, readiness, and matching under `docs/ssot/routing-artifact.md`.

## Contract package dependency policy

Routing and HttpApp contracts MUST remain dependency-light.

They MUST NOT depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- `platform/http` runtime package
- `platform/cli` runtime package
- concrete router implementations
- concrete middleware implementations
- concrete controller implementations
- concrete service container implementations
- vendor-specific runtime clients
- generated architecture artifacts
- `devtools/*` packages
- repository tooling under `tools/**`

Runtime packages MAY depend on `core/contracts`.

`core/contracts` MUST NOT depend back on runtime packages.

Allowed direction:

```text
platform/routing → core/contracts
platform/http-app → core/contracts
platform/http → core/contracts
platform/cli → core/contracts
```

Forbidden direction:

```text
core/contracts → platform/routing
core/contracts → platform/http-app
core/contracts → platform/http
core/contracts → platform/cli
```

## DTO terminology boundary

This document uses the terms `descriptor`, `shape`, `result`, and `model` according to:

```text
docs/ssot/dto-policy.md
```

`RouteDefinition` and `RouteMatch` are contracts routing shapes/descriptors.

They are not DTO-marker classes by default.

DTO gates apply only to classes explicitly marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Routing descriptors MUST NOT be treated as DTOs merely because they have constructor arguments, fields, accessors, or exported array shapes.

`RouteDefinition`, `RouteMatch`, and related routing shapes are governed by this SSoT and contracts shape tests, not DTO gates. DTO policy applies only through explicit marker opt-in.

## Json-like value model

Any json-like payload exposed by routing or HttpApp contracts MUST contain only:

- `null`
- `bool`
- `int`
- `string`
- list of allowed values
- map with string keys and allowed values

Floating-point values are forbidden.

The following values MUST NOT appear in exported json-like routing or HttpApp contract shapes:

- floats
- `NaN`
- `INF`
- `-INF`
- PHP objects
- closures
- resources
- streams
- filesystem handles
- service instances
- runtime wiring objects
- executable validators
- request objects
- response objects
- middleware objects
- router implementation objects
- service container objects
- vendor SDK objects

If a future owner needs decimal values, they MUST be represented as strings with a documented format.

## Json-like determinism

Lists preserve order.

Maps MUST be ordered deterministically by string key using byte-order `strcmp`.

Map ordering MUST be locale-independent.

Implementations that expose json-like maps SHOULD normalize map ordering recursively before export, serialization, rendering, diagnostics, route compilation, or artifact generation.

An empty array is context-dependent:

- if the contract location requires a list, `[]` is treated as an empty list;
- if the contract location requires a map, `[]` is treated as an empty map at the semantic boundary;
- serialized PHP array representations may not preserve empty list vs empty map distinction.

Contracts MUST document the expected context for any ambiguous empty array field.

## Security and redaction

Routing and HttpApp contracts MUST NOT require storing secrets.

Routing descriptors, route matches, invocation inputs, resolver metadata, diagnostics, logs, spans, and metrics MUST NOT expose:

- raw headers;
- raw cookies;
- raw authorization data;
- raw auth identifiers;
- raw session identifiers;
- raw tokens;
- raw request bodies;
- raw response bodies;
- raw payloads;
- raw SQL;
- credentials;
- passwords;
- private keys;
- private customer data;
- absolute local paths;
- environment-specific bytes.

Safe diagnostics MAY use:

```text
hash(value)
len(value)
```

Safe derivations MUST NOT expose raw values or allow reconstruction of sensitive values.

## Raw path and route template boundary

The raw request path is transport input.

A router implementation MAY receive a raw path as an ephemeral scalar input for matching.

The raw request path MUST NOT be stored in `RouteDefinition`.

The raw request path MUST NOT be stored in `RouteMatch`.

The raw request path MUST NOT be used as a metric label, span name fragment, log field, route identity, route artifact identity, or deterministic route metadata field.

The route template is the canonical safe routing metadata.

Examples of route templates:

```text
/users/{id}
/articles/{slug}
/catalog
```

Examples of raw paths:

```text
/users/123
/articles/example-post
/catalog
```

Route template values MAY be used for:

- matching diagnostics;
- debugging;
- safe route match metadata;
- span context, subject to observability redaction rules;
- runtime routing context.

Route template values MUST NOT become metric labels unless:

```text
docs/ssot/observability.md
```

explicitly extends the metric label allowlist.

## Canonical routing context key

The canonical public key is `Coretsia\Contracts\Context\ContextKeys::PATH_TEMPLATE` (`path_template`).

`platform/routing` owns semantic mutation of this key through `Coretsia\Contracts\Context\ContextWriterInterface`: remove the previous value at the start of matching and set the complete declared template only on success, before completion observability.

Contracts owns the key vocabulary and context ports only. Foundation owns context storage and structural validation; Kernel owns UnitOfWork lifecycle and reset orchestration. See `docs/ssot/context-keys.md` and `docs/ssot/routing-artifact.md`.

## RouteDefinition

`RouteDefinition` is the canonical contracts descriptor for a declared route.

It describes route metadata consumed by `platform/routing` during source compilation and artifact-backed matching.

It MUST be format-neutral.

It MUST NOT contain:

- request objects;
- response objects;
- PSR-7 objects;
- middleware objects;
- controller objects;
- service instances;
- container references;
- runtime closures;
- raw headers;
- raw cookies;
- raw request bodies;
- raw response bodies;
- raw payloads;
- raw SQL;
- credentials;
- tokens;
- absolute local paths.

### RouteDefinition logical fields

The canonical `RouteDefinition` logical field set is:

```text
schemaVersion
name
methods
pathTemplate
handler
requirements
defaults
metadata
```

Field meanings:

| field           | meaning                                                                                                     |
|-----------------|-------------------------------------------------------------------------------------------------------------|
| `schemaVersion` | Stable route definition schema version.                                                                     |
| `name`          | Stable route name / route identity within the provider output.                                              |
| `methods`       | Deterministic list of safe method tokens accepted by the route.                                             |
| `pathTemplate`  | Safe route template, not a raw request path.                                                                |
| `handler`       | Stable implementation-owned action reference string. In HttpApp ports this is usually passed as `actionId`. |
| `requirements`  | Safe string-keyed map of placeholder constraints or matching requirements.                                  |
| `defaults`      | Json-like map of safe default route values.                                                                 |
| `metadata`      | Json-like map of safe deterministic non-transport route metadata.                                           |

No field may expose transport objects or runtime service objects.

### RouteDefinition field rules

`schemaVersion` MUST be a positive integer.

The initial canonical `RouteDefinition` schema version is:

```text
1
```

`name` MUST be a non-empty safe single-line string and MUST NOT contain whitespace.

`methods` MUST be a deterministic list of non-empty safe method tokens.

`methods` MUST be normalized to uppercase.

`methods` MUST be unique.

`methods` MUST be sorted ascending using byte-order `strcmp`.

`pathTemplate` MUST be a non-empty safe single-line string.

`pathTemplate` MUST start with `/`.

`pathTemplate` MUST NOT contain whitespace.

`handler` MUST be a non-empty safe single-line string and MUST NOT contain whitespace.

`requirements` MUST be a map with string keys and string values.

`defaults` MUST be a json-like map.

`metadata` MUST be a json-like map.

`defaults` and `metadata` MUST follow the json-like value model in this document.

`requirements`, `defaults`, and `metadata` maps MUST use deterministic key ordering by byte-order `strcmp`.

Route identity/token fields MUST be validated exactly as supplied unless this SSoT explicitly defines a canonical transformation.

Constructors MUST NOT trim leading or trailing whitespace from `name`, `pathTemplate`, `handler`, route method tokens, requirement keys, or parameter keys before validation.

Route method tokens MAY be normalized to uppercase after the original token has passed empty, safe single-line, and no-whitespace validation.

### RouteDefinition identity

The route name is the stable route identity at the contracts boundary.

Provider outputs MUST NOT contain duplicate route names.

`platform/routing` MUST reject duplicate route names across the complete target-local route table during compilation and artifact validation.

The route name MUST NOT contain raw path values, raw query values, credentials, tokens, user identifiers, private customer data, or environment-specific bytes.

### RouteDefinition exported order

When exported as a PHP array shape, `RouteDefinition` SHOULD use deterministic top-level key ordering by byte-order `strcmp`:

```text
defaults
handler
metadata
methods
name
pathTemplate
requirements
schemaVersion
```

Contract tests cement this order as part of the descriptor shape contract.

## RouteMatch

`RouteMatch` is the canonical contracts descriptor for a successful route match.

It represents the safe normalized result of matching, not the raw request.

It MUST be format-neutral.

It MUST NOT contain:

- request objects;
- response objects;
- PSR-7 objects;
- middleware objects;
- controller objects;
- service instances;
- container references;
- runtime closures;
- raw headers;
- raw cookies;
- raw request bodies;
- raw response bodies;
- raw payloads;
- raw SQL;
- credentials;
- tokens;
- absolute local paths.

### RouteMatch logical fields

The canonical `RouteMatch` logical field set is:

```text
schemaVersion
name
pathTemplate
handler
parameters
metadata
```

Field meanings:

| field           | meaning                                                                                                     |
|-----------------|-------------------------------------------------------------------------------------------------------------|
| `schemaVersion` | Stable route match schema version.                                                                          |
| `name`          | Stable matched route name.                                                                                  |
| `pathTemplate`  | Safe route template for the matched route.                                                                  |
| `handler`       | Stable implementation-owned action reference string. In HttpApp ports this is usually passed as `actionId`. |
| `parameters`    | Deterministic string map of matched route parameters needed for invocation.                                 |
| `metadata`      | Json-like map of safe deterministic route match metadata.                                                   |

### RouteMatch field rules

`schemaVersion` MUST be a positive integer.

The initial canonical `RouteMatch` schema version is:

```text
1
```

`name` MUST be a non-empty safe single-line string and MUST NOT contain whitespace.

`pathTemplate` MUST be a non-empty safe single-line string.

`pathTemplate` MUST start with `/`.

`pathTemplate` MUST NOT contain whitespace.

`handler` MUST be a non-empty safe single-line string and MUST NOT contain whitespace.

`parameters` MUST be a string-keyed map of string values.

`parameters` keys MUST be non-empty safe single-line strings and MUST NOT contain whitespace.

`parameters` values MUST be non-empty safe strings.

`metadata` MUST be a json-like map.

`metadata` MUST follow the json-like value model in this document.

`parameters` and `metadata` maps MUST use deterministic key ordering by byte-order `strcmp`.

Route parameters MAY originate from user-controlled path segments.

Therefore, route parameters MUST NOT be treated as observability-safe by default.

Route parameters MUST NOT be copied wholesale into logs, spans, metrics, diagnostics, errors, or generated artifacts.

Safe diagnostics for route parameters MAY use only safe derivations such as:

```text
hash(value)
len(value)
```

Route identity/token fields MUST be validated exactly as supplied unless this SSoT explicitly defines a canonical transformation.

Constructors MUST NOT trim leading or trailing whitespace from `name`, `pathTemplate`, `handler`, parameter keys, or parameter values before validation.

### RouteMatch exported order

When exported as a PHP array shape, `RouteMatch` SHOULD use deterministic top-level key ordering by byte-order `strcmp`:

```text
handler
metadata
name
parameters
pathTemplate
schemaVersion
```

Contract tests cement this order as part of the descriptor shape contract.

## RouteProviderInterface

`RouteProviderInterface` is the contracts port for providing route definitions.

It is the source of `RouteDefinition` objects for explicit target-local source compilation.

The canonical interface shape is:

```text
id(): string
routes(): array
```

`id()` returns the stable provider id.

The provider id MAY be:

- an application id;
- a module id;
- a package id;
- another owner-defined stable source identifier.

The provider id MUST be a non-empty safe single-line string.

The provider id MUST NOT contain:

- raw paths;
- raw request data;
- raw query values;
- credentials;
- tokens;
- private customer data;
- environment-specific bytes.

`routes()` returns declared route definitions.

The returned value MUST be a list of `RouteDefinition` objects.

The route provider port MUST NOT prescribe:

- filesystem route discovery;
- PHP attribute scanning;
- controller scanning;
- Composer metadata scanning;
- runtime module scanning;
- generated artifact loading;
- DI container integration;
- HTTP middleware wiring;
- CLI command behavior.

Concrete providers MAY use owner-specific source declaration helpers, including grouping, internally. All helpers MUST produce ordinary named `RouteDefinition` instances before crossing the contracts boundary.

The returned list MUST be flat. Group objects, DI objects, transport objects, generated route artifacts, and concrete runtime implementation objects MUST NOT cross this boundary. No group-specific contract or additional descriptor field is introduced.

The dependency restrictions apply to the contracts package and interface boundary, not to owner-specific declaration helpers used internally by concrete providers. Providing declarations MUST NOT require filesystem or attribute scanning, transport objects, service container objects, or generated artifacts. Grouping and admission semantics are owned by `docs/ssot/routing-artifact.md`.

The contracts package MUST NOT implement a concrete route provider.

### Route provider output ordering

Any provider output containing multiple route definitions MUST be deterministic. Provider-emitted order defines the declared matching precedence within the provider and MUST be preserved. Provider outputs MUST NOT contain duplicate route names.

Multi-provider aggregation is runtime-owned. `platform/routing` MUST enumerate providers exclusively through `TagRegistry::all(ReservedTags::ROUTING_ROUTE_PROVIDER)` with empty tag metadata, following `docs/ssot/tags.md`.

The effective order is tag priority descending, then service id ascending using byte-order `strcmp`, then the exact route order emitted by each provider. Routing MUST NOT re-sort or re-deduplicate the TagRegistry output, globally sort routes by name, or reorder them by specificity.

## RouterInterface

`RouterInterface` is the contracts port for matching scalar routing input to a `RouteMatch`.

It MUST remain format-neutral.

The canonical interface shape is:

```text
match(string $method, string $path, ?string $host = null): ?RouteMatch
```

`method` is scalar matching input.

`path` is raw matching input and MUST remain ephemeral.

`host` is optional scalar matching input and MUST remain ephemeral unless a future owner explicitly defines a safe normalized host-template contract.

`RouterInterface` MUST NOT require:

- PSR-7 request objects;
- PSR-7 response objects;
- `platform/http` request objects;
- `platform/http` response objects;
- concrete middleware objects;
- concrete route collection objects;
- concrete route compiler objects.

A router implementation MAY accept scalar matching input such as method, path, and optional host according to its PHP interface shape.

Raw matching input is ephemeral.

Raw matching input MUST NOT be stored in `RouteMatch`.

Raw matching input MUST NOT be emitted through diagnostics, logs, spans, metrics, or errors unless converted to a safe derivation.

The normalized successful output is `RouteMatch`.

No-match behavior is represented by `null`.

Runtime owner packages MAY wrap no-match behavior into owner-defined exceptions or error handling outside the contracts boundary.

### Runtime matching policy

`platform/routing` owns the supported method and template grammar, requirement languages, conflict detection, reserved-domain protection, and artifact-backed matching, as defined by `docs/ssot/routing-artifact.md`. These admission rules are additional to the format-neutral descriptor invariants and do not change their shapes.

- Equivalent matching domains with intersecting method sets MUST be rejected. Placeholder names do not distinguish equivalent domains. Distinct overlapping domains use first-match-wins in canonical stored order.
- Matching MUST consume canonical encoded URI paths without decoding or alias normalization, and use exact canonical method values. Invalid, reserved, unsupported, and unmatched input returns `null`. Host is not used for matching.
- The runtime router MUST consume only the validated immutable route table. It MUST NOT resolve providers, interpret source groups, read artifacts per match, compile routes, or resolve or invoke handlers.

## RoutingArtifactReadinessInterface

`RoutingArtifactReadinessInterface` is the transport-neutral port for owner-artifact readiness.

The canonical interface shape is:

```text
assertReady(): void
```

Normal completion indicates readiness for runtime consumption. Failures are reported through implementation-owned exceptions.

The port MUST NOT expose artifact paths, payloads, filesystem operations, concrete Routing services, or router operations. It MUST NOT extend or change `RouterInterface`.

`platform/routing` owns artifact selection and validation. Its readiness implementation and router consume the same shared validated immutable route table. When Routing is enabled in the effective HTTP target, the HTTP-owned entrypoint guard MUST resolve and assert readiness before HTTP runtime construction, after the module, driver, and PSR-17 factory checks. Missing or invalid readiness bindings and artifact validation failures MUST prevent HTTP startup. When Routing is not enabled, the guard MUST NOT resolve the readiness port or require the route artifact. This is runtime policy, not a dependency of `core/contracts`.

## HttpApp boundary

HttpApp contracts define invocation boundaries for application actions.

They do not define HTTP transport objects.

They do not define HTTP response rendering.

They do not define middleware implementation.

They do not define controller discovery.

They do not define request body parsing.

They do not define validation or serialization frameworks.

Runtime owner packages adapt transport-specific data into safe contract-level invocation inputs.

## HttpApp action id terminology

HttpApp ports use the term `actionId` for the stable implementation-owned action reference used during argument resolution and invocation.

At the routing boundary, the same logical value is exposed as:

```text
RouteDefinition::handler()
RouteMatch::handler()
```

At the HttpApp boundary, that value is usually passed as:

```text
$actionId
```

The `actionId` value MUST be a non-empty safe single-line string.

The `actionId` value MUST NOT contain:

- raw paths;
- raw query values;
- raw headers;
- raw cookies;
- raw request bodies;
- raw response bodies;
- credentials;
- tokens;
- private customer data;
- environment-specific bytes.

The contracts package MUST NOT prescribe how an `actionId` is resolved into a callable, controller, closure, service method, or another runtime-owned invocation target.

## ArgumentResolverInterface

`ArgumentResolverInterface` is the contracts port for resolving named action arguments.

It MUST remain independent of request type.

The canonical interface shape is:

```text
resolve(string $actionId, RouteMatch $match, array $context = []): array
```

The returned array MUST be a named argument map:

```text
array<string,mixed>
```

The returned array is not required to be json-like because resolved arguments MAY be runtime invocation values.

The `context` argument is optional safe context metadata.

The `context` map SHOULD be json-like whenever possible.

The `context` map MUST NOT contain:

- raw headers;
- raw cookies;
- raw request bodies;
- raw response bodies;
- credentials;
- tokens;
- raw SQL;
- transport objects;
- service container objects;
- private customer data.

`ArgumentResolverInterface` MUST NOT require:

- PSR-7 request objects;
- PSR-7 response objects;
- `platform/http` request objects;
- `platform/http` response objects;
- raw headers;
- raw cookies;
- raw request bodies;
- raw response bodies;
- concrete service container objects;
- concrete router objects.

An argument resolver implementation MAY use:

- `actionId`;
- `RouteMatch`;
- safe route parameters;
- safe implementation-owned context;
- reflection;
- container lookups inside the runtime owner package;
- owner-defined resolver policies.

The contracts package MUST NOT implement a concrete argument resolver.

Argument values returned by an implementation MAY be runtime values required for invocation.

Diagnostics for argument resolution MUST NOT print or log raw argument values.

Safe diagnostics MAY use:

```text
hash(value)
len(value)
type(value)
```

## ActionInvokerInterface

`ActionInvokerInterface` is the contracts port for invoking an application action.

It MUST remain independent of request type.

The canonical interface shape is:

```text
invoke(string $actionId, array $arguments = []): mixed
```

The `arguments` array MUST be a named argument map:

```text
array<string,mixed>
```

The arguments map is expected to be the result of `ArgumentResolverInterface`.

The arguments map is not required to be json-like because resolved arguments MAY be runtime invocation values.

`ActionInvokerInterface` MUST NOT require:

- PSR-7 request objects;
- PSR-7 response objects;
- `platform/http` request objects;
- `platform/http` response objects;
- concrete middleware objects;
- concrete service container objects;
- concrete controller base classes;
- concrete response factories.

An action invoker implementation MAY resolve a stable `actionId` into a callable using runtime-owned mechanisms.

The contracts package MUST NOT implement action lookup, controller construction, DI lookup, response creation, or exception handling.

Invocation return values are implementation-owned.

A runtime adapter MAY convert invocation output into a transport-specific response outside `core/contracts`.

Raw argument values MUST NOT be emitted through diagnostics, logs, spans, metrics, or errors.

## Runtime ownership

### `platform/routing`

`platform/routing` owns concrete route compilation and artifact-backed matching.

It provides:

- deterministic provider aggregation and source-only declaration grouping;
- target-local route compilation and complete route admission;
- `routes@1` artifact schema, fingerprint, publication, and validation;
- immutable artifact-backed route matching and owner-artifact readiness;
- path-template context mutation and bounded routing match observability.

Detailed runtime policy belongs to `docs/ssot/routing-artifact.md`.

`platform/routing` MUST use contracts-defined descriptors and ports when crossing the contracts boundary.

`platform/routing` MUST NOT redefine competing `RouteDefinition` or `RouteMatch` field semantics.

### `platform/http-app`

`platform/http-app` is the expected future owner of concrete HttpApp dispatch behavior.

It may provide:

- router middleware;
- action invoker implementation;
- argument resolver implementation;
- application action dispatch;
- transport adapter integration.

When installed, `platform/http-app` is expected to supply router middleware and wire it into the canonical app middleware slot with priority:

```text
100
```

The canonical middleware tag is:

```text
http.middleware.app
```

The canonical code-level identifier for this Coretsia-reserved DI tag is:

```text
Coretsia\Foundation\Tag\ReservedTags::HTTP_MIDDLEWARE_APP
```

This is runtime policy, not a `core/contracts` dependency.

The canonical HTTP middleware taxonomy is:

```text
system/app/route
```

Router middleware wiring policy MUST use the canonical taxonomy and canonical tag names reserved in:

```text
docs/ssot/tags.md
```

Legacy user-middleware slot terminology MUST NOT be introduced as current tag names in contracts, SSoT, defaults, or gates.

## DI tag policy

The contracts package owns no DI tags.

The contracts package MUST NOT declare routing or HttpApp tag constants.

The contracts package MUST NOT define additional code-level registries for Coretsia-reserved middleware DI tag identifiers.

The contracts package MAY reference existing reserved routing and middleware tags in documentation as runtime policy.

Reserved tag ownership remains governed by:

```text
docs/ssot/tags.md
```

Non-owner packages using existing reserved tags MUST follow the tag registry rules, MUST use `Coretsia\Foundation\Tag\ReservedTags::*` in runtime package source, and MUST NOT redefine competing tag semantics, competing metadata schema, or additional code-level registries for Coretsia-reserved DI tag identifiers.

## Config policy

The contracts package owns no config roots or config keys for routing or HttpApp.

Routing and HttpApp contracts MUST NOT require package config files.

Runtime configuration belongs to the respective owner packages under `docs/ssot/config-roots.md`.

## Artifact policy

The contracts package owns no route artifacts.

`routes@1` is owned and produced by `platform/routing` at the authoritative target-local artifact root with basename `routes.php`, outside Kernel immutable generations.

The artifact MUST follow the global artifact envelope and deterministic serialization law in `docs/ssot/artifacts.md` and the owner-specific schema and lifecycle in `docs/ssot/routing-artifact.md`. Its route list contains only complete flattened canonical descriptors in matching order.

The contracts package MUST NOT generate route artifacts.

The contracts package MUST NOT own the routes artifact payload schema.

Route artifacts MUST NOT expose raw paths, raw headers, raw cookies, raw request bodies, raw response bodies, credentials, tokens, private customer data, absolute local paths, timestamps, random values, or environment-specific bytes.

## Observability policy

Route template values MAY be used as safe routing metadata or span context, subject to global redaction law.

The canonical safe routing metadata/context key for route templates is:

```text
path_template
```

Route template values MUST NOT become metric labels unless:

```text
docs/ssot/observability.md
```

explicitly extends the metric label allowlist.

Metric labels MUST remain within the canonical allowlist.

The baseline allowed metric label keys are:

```text
method
status
driver
operation
table
outcome
```

Routing contracts MUST NOT introduce a new metric label key.

Routing contracts MUST NOT copy route parameters, raw paths, raw query values, headers, cookies, request bodies, response bodies, tokens, session identifiers, user identifiers, tenant identifiers, or private customer data into metric labels.

## Error handling boundary

Routing and HttpApp contracts do not define error normalization.

Runtime routing and HttpApp packages MAY use the canonical error contracts from:

```text
docs/ssot/observability-and-errors.md
docs/ssot/errors-boundary.md
docs/ssot/error-descriptor.md
```

Routing and HttpApp packages MUST NOT invent a competing normalized error descriptor shape.

## Verification evidence

Contracts-level enforcement evidence includes:

```text
packages/core/contracts/tests/Contract/RoutingContractsDoNotUsePsr7Test.php
packages/core/contracts/tests/Contract/HttpAppContractsAreFormatNeutralTest.php
packages/core/contracts/tests/Contract/RouteProviderInterfaceShapeContractTest.php
packages/core/contracts/tests/Contract/RouteDefinitionShapeContractTest.php
packages/core/contracts/tests/Contract/RouteMatchShapeContractTest.php
packages/core/contracts/tests/Contract/RoutingArtifactReadinessInterfaceShapeContractTest.php
```

These tests verify:

- routing contracts do not reference PSR-7;
- HttpApp contracts stay format-neutral;
- route provider shape and provider-emitted ordering stay stable;
- the readiness port remains narrow and transport-neutral;
- no transport object leakage is introduced into the contracts package.

## Non-goals

This SSoT does not define:

- concrete route collection implementation;
- concrete router implementation;
- concrete route compiler implementation;
- route artifact payload schema;
- route artifact generator;
- route artifact reader;
- filesystem route discovery;
- PHP attribute route discovery;
- controller discovery;
- controller construction;
- dependency injection wiring;
- HTTP middleware implementation;
- HTTP response rendering;
- PSR-7 integration;
- request body parsing;
- validation framework integration;
- CLI route compiler command behavior;
- platform runtime package implementation.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Tag Registry](./tags.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [DTO Policy](./dto-policy.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Routing Artifact](./routing-artifact.md)
- [Context Keys](./context-keys.md)
- [ADR-0018: Platform routing compiled routes](../adr/ADR-0018-platform-routing-compiled-routes.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [Errors Boundary SSoT](./errors-boundary.md)
