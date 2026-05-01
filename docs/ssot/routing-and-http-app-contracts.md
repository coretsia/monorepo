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

## Scope

This document is the Single Source of Truth for Coretsia routing contracts, route descriptor shape policy, route match shape policy, route provider semantics, router matching semantics, HttpApp invocation ports, runtime ownership boundaries, and no-PSR-7 dependency rules.

This document governs contracts introduced by epic `1.110.0` under:

```text
framework/packages/core/contracts/src/Routing/
framework/packages/core/contracts/src/HttpApp/
```

It complements:

```text
docs/ssot/tags.md
docs/ssot/observability.md
docs/ssot/dto-policy.md
docs/ssot/artifacts.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Routing and HttpApp runtime packages must be implementable without changing `core/contracts` and without making `core/contracts` depend on HTTP transport abstractions.

The contracts introduced by this epic define only:

- format-neutral route descriptors;
- format-neutral route match descriptors;
- route provider ports;
- router matching ports;
- action invocation ports;
- argument resolution ports;
- deterministic and safe boundary rules.

The contracts package MUST NOT implement routing runtime behavior, HTTP middleware, controller dispatch, filesystem route discovery, route compilation, route artifact generation, DI registration, or transport rendering.

## Contract boundary

Routing and HttpApp contracts are format-neutral.

They MUST NOT require:

- PSR-7 request objects;
- PSR-7 response objects;
- framework HTTP request objects;
- framework HTTP response objects;
- concrete middleware objects;
- concrete router implementations;
- concrete controller implementations;
- service container implementations;
- platform package classes;
- integration package classes;
- vendor SDKs;
- generated route artifacts.

The contracts package defines stable ports and descriptor shapes only.

Runtime owner packages implement concrete behavior later.

## Contract package dependency policy

Routing and HttpApp contracts MUST remain dependency-light.

They MUST NOT depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- framework HTTP runtime packages
- framework CLI runtime packages
- concrete router implementations
- concrete middleware implementations
- concrete controller implementations
- concrete service container implementations
- vendor-specific runtime clients
- generated architecture artifacts
- tooling packages

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

A future owner epic MAY explicitly opt a routing transport class into DTO policy.

Until such an epic exists, `RouteDefinition`, `RouteMatch`, and related routing shapes are governed by this SSoT and contracts shape tests, not DTO gates.

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
/health
```

Examples of raw paths:

```text
/users/123
/articles/example-post
/health
```

Route template values MAY be used for:

- matching diagnostics;
- debugging;
- safe route match metadata;
- span context, subject to observability redaction rules;
- future runtime routing context.

Route template values MUST NOT become metric labels unless:

```text
docs/ssot/observability.md
```

explicitly extends the metric label allowlist.

## Canonical routing context key

After a successful match, the canonical runtime routing metadata/context key for the route template is:

```text
path_template
```

This is runtime policy, not a `core/contracts` dependency.

Any concrete Foundation constant, key-carrier class, context accessor integration, or context storage mechanism for this key is owned by the corresponding future Foundation epic.

`core/contracts` MUST NOT introduce a Foundation constant or concrete context key carrier for `path_template`.

## RouteDefinition

`RouteDefinition` is the canonical contracts descriptor for a declared route.

It describes route metadata needed by future routing and route compilation owners.

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
name
methods
pathTemplate
handler
requirements
defaults
metadata
```

Field meanings:

| field          | meaning                                                                                                     |
|----------------|-------------------------------------------------------------------------------------------------------------|
| `name`         | Stable route name / route identity within the provider output.                                              |
| `methods`      | Deterministic list of safe method tokens accepted by the route.                                             |
| `pathTemplate` | Safe route template, not a raw request path.                                                                |
| `handler`      | Stable implementation-owned action reference string. In HttpApp ports this is usually passed as `actionId`. |
| `requirements` | Safe string-keyed map of placeholder constraints or matching requirements.                                  |
| `defaults`     | Json-like map of safe default route values.                                                                 |
| `metadata`     | Json-like map of safe deterministic non-transport route metadata.                                           |

No field may expose transport objects or runtime service objects.

### RouteDefinition field rules

`name` MUST be a non-empty safe single-line string.

`methods` MUST be a deterministic list of non-empty safe single-line strings.

`pathTemplate` MUST be a non-empty safe single-line string.

`handler` MUST be a non-empty safe single-line string.

`requirements` MUST be a map with string keys and string values.

`defaults` MUST be a json-like map.

`metadata` MUST be a json-like map.

`defaults` and `metadata` MUST follow the json-like value model in this document.

`requirements`, `defaults`, and `metadata` maps MUST use deterministic key ordering by byte-order `strcmp`.

### RouteDefinition identity

The route name is the stable route identity at the contracts boundary.

Provider outputs MUST NOT contain duplicate route names.

A runtime compiler MAY reject duplicate route names.

A runtime router MAY reject duplicate route names.

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
```

Contract tests MAY cement this order once the PHP implementation exists.

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
name
pathTemplate
handler
parameters
metadata
```

Field meanings:

| field          | meaning                                                                                                     |
|----------------|-------------------------------------------------------------------------------------------------------------|
| `name`         | Stable matched route name.                                                                                  |
| `pathTemplate` | Safe route template for the matched route.                                                                  |
| `handler`      | Stable implementation-owned action reference string. In HttpApp ports this is usually passed as `actionId`. |
| `parameters`   | Json-like map of route parameters needed for invocation.                                                    |
| `metadata`     | Json-like map of safe deterministic route match metadata.                                                   |

### RouteMatch field rules

`name` MUST be a non-empty safe single-line string.

`pathTemplate` MUST be a non-empty safe single-line string.

`handler` MUST be a non-empty safe single-line string.

`parameters` MUST be a json-like map.

`metadata` MUST be a json-like map.

`parameters` and `metadata` MUST follow the json-like value model in this document.

`parameters` and `metadata` maps MUST use deterministic key ordering by byte-order `strcmp`.

Route parameters MAY originate from user-controlled path segments.

Therefore, route parameters MUST NOT be treated as observability-safe by default.

Route parameters MUST NOT be copied wholesale into logs, spans, metrics, diagnostics, errors, or generated artifacts.

Safe diagnostics for route parameters MAY use only safe derivations such as:

```text
hash(value)
len(value)
```

### RouteMatch exported order

When exported as a PHP array shape, `RouteMatch` SHOULD use deterministic top-level key ordering by byte-order `strcmp`:

```text
handler
metadata
name
parameters
pathTemplate
```

Contract tests MAY cement this order once the PHP implementation exists.

## RouteProviderInterface

`RouteProviderInterface` is the contracts port for providing route definitions.

It is the source of `RouteDefinition` objects for future runtime route compilation and routing runtime owners.

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
- framework module scanning;
- generated artifact loading;
- DI container integration;
- HTTP middleware wiring;
- CLI command behavior.

A route provider implementation MAY source route definitions from:

- application code;
- module metadata;
- generated route artifacts;
- package-owned route files;
- PHP attributes;
- owner-defined config;
- another future owner-defined source.

The contracts package MUST NOT implement a concrete route provider.

### Route provider output ordering

Any provider output containing multiple route definitions MUST be deterministic.

Provider outputs SHOULD be ordered by:

```text
name ascending using byte-order strcmp
```

Provider outputs MUST NOT contain duplicate route names.

If a future owner allows unnamed intermediate route declarations before conversion to `RouteDefinition`, that owner MUST normalize them into named `RouteDefinition` objects before exposing them through contracts.

Multi-provider aggregation is runtime-owned.

Any runtime aggregator MUST preserve deterministic output and SHOULD normalize final route order by route name using byte-order `strcmp`.

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
- framework HTTP request objects;
- framework HTTP response objects;
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
- framework HTTP request objects;
- framework HTTP response objects;
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
- framework HTTP request objects;
- framework HTTP response objects;
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

`platform/routing` is the expected future owner of concrete route matching and route compilation behavior.

It may provide:

- route collection implementation;
- router implementation;
- route compiler implementation;
- route artifact generation;
- route artifact reading;
- provider aggregation;
- deterministic route ordering enforcement.

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

This epic introduces no DI tags.

The contracts package MUST NOT declare routing or HttpApp tag constants.

The contracts package MAY reference existing reserved middleware tags in documentation as runtime policy.

Reserved tag ownership remains governed by:

```text
docs/ssot/tags.md
```

Non-owner packages using existing reserved tags MUST follow the tag registry rules and MUST NOT redefine competing tag semantics or competing metadata schema.

## Config policy

This epic introduces no config roots and no config keys.

Routing and HttpApp contracts MUST NOT require package config files.

Future runtime owners MAY introduce config roots or config keys only through their own owner epics and the config roots registry process.

## Artifact policy

This epic introduces no new artifact ownership for `core/contracts`.

A future routes artifact is runtime-owned by `platform/routing`.

If a routes artifact is generated, it MUST follow the global artifact envelope and deterministic serialization law from:

```text
docs/ssot/artifacts.md
```

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

This document is doc-only.

Current and future contracts-level enforcement evidence for this epic includes:

```text
framework/packages/core/contracts/tests/Contract/RoutingContractsDoNotUsePsr7Test.php
framework/packages/core/contracts/tests/Contract/HttpAppContractsAreFormatNeutralTest.php
framework/packages/core/contracts/tests/Contract/RouteProviderInterfaceShapeContractTest.php
```

These tests are expected to verify:

- routing contracts do not reference PSR-7;
- HttpApp contracts stay format-neutral;
- route provider shape stays stable;
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
- [Observability Naming and Labels Allowlist](./observability.md)
- [DTO Policy](./dto-policy.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [Errors Boundary SSoT](./errors-boundary.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
