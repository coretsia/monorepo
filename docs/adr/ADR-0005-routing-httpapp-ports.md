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

# ADR-0005: Routing and HttpApp ports

## Status

Accepted.

## Context

Epic `1.110.0` introduces stable contracts for routing and HttpApp invocation under:

```text
framework/packages/core/contracts/src/Routing/
framework/packages/core/contracts/src/HttpApp/
```

Runtime routing and HttpApp packages need shared contracts for route declarations, route matching, action argument resolution, and action invocation without coupling `core/contracts` to HTTP transport APIs, platform implementations, integrations, generated artifacts, middleware implementations, or vendor-specific runtime objects.

The contracts package must remain a pure library boundary.

It must not depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- framework HTTP runtime packages
- framework CLI runtime packages
- concrete router implementations
- concrete middleware implementations
- concrete controller implementations
- concrete service container implementations
- generated route artifacts
- framework tooling packages

The detailed normative policy for this ADR is defined by:

```text
docs/ssot/routing-and-http-app-contracts.md
```

The existing SSoT baseline already defines the relevant supporting rules:

```text
docs/ssot/tags.md
docs/ssot/observability.md
docs/ssot/dto-policy.md
docs/ssot/artifacts.md
```

## Decision

Coretsia will introduce routing and HttpApp contracts as format-neutral contracts in `core/contracts`.

The contracts introduced by epic `1.110.0` define:

- `RouteDefinition`
- `RouteMatch`
- `RouterInterface`
- `RouteProviderInterface`
- `ActionInvokerInterface`
- `ArgumentResolverInterface`

The contracts package defines ports, descriptors, shape invariants, safety rules, and deterministic ordering policy only.

It does not implement:

- concrete route matching;
- route collection storage;
- route compilation;
- route artifact generation;
- route artifact reading;
- filesystem route discovery;
- PHP attribute route discovery;
- controller discovery;
- controller construction;
- middleware wiring;
- HTTP response rendering;
- PSR-7 adaptation;
- DI registration;
- CLI command behavior.

## Routing boundary decision

`RouteDefinition` is the contracts descriptor for a declared route.

`RouteMatch` is the contracts descriptor for a successful route match.

Both are format-neutral routing shapes/descriptors.

They are not DTO-marker classes by default.

Their descriptor rules, exported shape policy, json-like value restrictions, redaction requirements, and deterministic ordering requirements are governed by:

```text
docs/ssot/routing-and-http-app-contracts.md
```

Routing descriptors must not expose:

- PSR-7 objects;
- framework request or response objects;
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

## Route provider decision

`RouteProviderInterface` is the contracts port for providing route definitions.

It is the source of `RouteDefinition` objects for future runtime route compilation and routing runtime owners.

The route provider contract exposes:

```text
id(): string
routes(): array
```

`id()` returns a stable provider id, such as an application id, module id, package id, or another owner-defined stable source identifier.

`routes()` returns a deterministic list of `RouteDefinition` objects.

The route provider contract does not prescribe how routes are discovered or loaded.

A future implementation may source route definitions from application code, module metadata, generated route artifacts, package-owned route files, PHP attributes, owner-defined config, or another owner-defined source.

The contracts package does not implement a route provider.

Any provider output containing multiple route definitions must be deterministic according to the detailed policy in:

```text
docs/ssot/routing-and-http-app-contracts.md
```

## Router decision

`RouterInterface` is the contracts port for matching scalar routing input to a `RouteMatch`.

The router contract must remain format-neutral.

It must not require request or response objects.

The router contract accepts scalar matching input:

```text
method
path
host?
```

The canonical interface shape is:

```text
match(string $method, string $path, ?string $host = null): ?RouteMatch
```

A concrete router implementation may receive scalar matching input such as method, path, and optional host according to the interface shape.

Raw matching input is ephemeral and must not become canonical routing metadata.

The normalized successful output is `RouteMatch`.

A null return value represents no match.

No-match rendering, exception mapping, or transport-specific error behavior is runtime-owned.

## Route template decision

The raw request path is transport input.

The route template is the canonical safe routing metadata.

A route template may be used for matching diagnostics, debugging, safe route match metadata, span context subject to observability redaction rules, and future runtime routing context.

The canonical runtime routing metadata/context key for the matched route template is:

```text
path_template
```

This is runtime policy, not a `core/contracts` dependency.

Any concrete Foundation constant, key-carrier class, context accessor integration, or context storage mechanism for this key is owned by the corresponding future Foundation epic.

The contracts package must not introduce a Foundation constant or concrete context key carrier for `path_template`.

## Observability decision

Route template values may be used as safe routing metadata or span context, subject to the global redaction law.

Route template values must not become metric labels unless the metric label allowlist is explicitly extended in:

```text
docs/ssot/observability.md
```

Routing contracts do not introduce new metric label keys.

Routing contracts must not copy route parameters, raw paths, raw query values, headers, cookies, request bodies, response bodies, tokens, session identifiers, user identifiers, tenant identifiers, or private customer data into metric labels.

## HttpApp decision

HttpApp contracts define invocation boundaries for application actions.

`ArgumentResolverInterface` is the contracts port for resolving action arguments.

`ActionInvokerInterface` is the contracts port for invoking an application action.

Both contracts must remain independent of request type.

HttpApp ports use the term `actionId` for the stable implementation-owned action reference.

At the routing boundary, this value is usually exposed by:

```text
RouteDefinition::handler()
RouteMatch::handler()
```

At the HttpApp boundary, this value is passed as:

```text
$actionId
```

`ArgumentResolverInterface` resolves named arguments using:

```text
resolve(string $actionId, RouteMatch $match, array $context = []): array
```

The resolver returns:

```text
array<string,mixed>
```

`ActionInvokerInterface` invokes the action using:

```text
invoke(string $actionId, array $arguments = []): mixed
```

The invoker accepts named arguments:

```text
array<string,mixed>
```

Both contracts must not require:

- PSR-7 request objects;
- PSR-7 response objects;
- framework HTTP request objects;
- framework HTTP response objects;
- concrete middleware objects;
- concrete service container objects;
- concrete controller base classes;
- concrete response factories.

Runtime owner packages adapt transport-specific data into safe contract-level invocation inputs.

Invocation return values are implementation-owned.

A runtime adapter may convert invocation output into a transport-specific response outside `core/contracts`.

## Runtime ownership decision

`platform/routing` is the expected future owner of concrete route matching and route compilation behavior.

It may provide:

- route collection implementation;
- router implementation;
- route compiler implementation;
- route artifact generation;
- route artifact reading;
- provider aggregation;
- deterministic route ordering enforcement.

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

The canonical HTTP middleware taxonomy remains:

```text
system/app/route
```

Middleware slot ownership and tag semantics remain governed by:

```text
docs/ssot/tags.md
```

## DI tag decision

Epic `1.110.0` introduces no DI tags.

The contracts package must not declare routing or HttpApp tag constants.

The contracts package may reference existing reserved middleware tags in documentation as runtime policy.

Reserved tag ownership remains governed by:

```text
docs/ssot/tags.md
```

Non-owner packages using existing reserved tags must follow the tag registry rules and must not redefine competing tag semantics or competing metadata schema.

## Config decision

Epic `1.110.0` introduces no config roots and no config keys.

Routing and HttpApp contracts must not require package config files.

Future runtime owners may introduce config roots or config keys only through their own owner epics and the config roots registry process.

## Artifact decision

Epic `1.110.0` introduces no artifact ownership for `core/contracts`.

A future routes artifact is runtime-owned by `platform/routing`.

If a routes artifact is generated, it must follow the global artifact envelope and deterministic serialization law from:

```text
docs/ssot/artifacts.md
```

The contracts package must not generate route artifacts.

The contracts package must not own the routes artifact payload schema.

## Json-like payload decision

Any json-like payload exposed by routing or HttpApp contracts must follow the same Phase 0 json-like policy used by the rest of the contracts boundary:

- allowed scalars are `string`, `int`, `bool`, and `null`;
- floats are forbidden, including `NaN`, `INF`, and `-INF`;
- lists preserve order;
- maps are ordered deterministically by byte-order `strcmp`;
- raw payload values must not be printed or logged;
- diagnostics may expose only safe derivations such as `hash(value)` or `len(value)`.

This applies to safe routing metadata, route defaults, route requirements where applicable, route parameters, and route match metadata.

The optional `ArgumentResolverInterface` context map SHOULD be json-like whenever possible and MUST obey the redaction policy.

Resolved invocation arguments are named runtime values and are not required to be json-like.

Raw resolved argument values MUST NOT be emitted through diagnostics, logs, spans, metrics, or errors.

## DTO boundary decision

`RouteDefinition` and `RouteMatch` are contracts routing shapes/descriptors.

They are not DTO-marker classes by default.

DTO policy remains explicit opt-in only through:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Contract tests for routing shape enforce the contracts surface directly.

DTO gates do not own these models unless a future epic explicitly opts them into DTO policy.

## Security and redaction decision

Routing and HttpApp contracts must not require storing secrets.

Routing descriptors, route matches, invocation inputs, resolver metadata, diagnostics, logs, spans, and metrics must not expose:

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

Secret-backed runtime behavior belongs to runtime owner packages, not to contracts descriptors or HttpApp ports.

## Consequences

Runtime packages can depend on stable routing and HttpApp contracts without depending on transport-specific APIs.

`platform/routing` can implement route matching and route compilation against stable descriptors.

`platform/http-app` can implement dispatch, argument resolution, action invocation, and router middleware without forcing HTTP request or response types into `core/contracts`.

Route artifacts can be introduced later by the routing runtime owner without changing the contracts package ownership boundary.

Routing metadata can be used safely for matching and diagnostics while preserving observability label discipline.

The contracts package remains format-neutral, dependency-light, and safe for non-HTTP runtimes.

## Rejected alternatives

### Put PSR-7 types in routing contracts

Rejected.

PSR-7 would make the contracts HTTP-specific and would leak transport concerns into non-HTTP runtimes.

### Make RouteMatch wrap a request object

Rejected.

A route match is a normalized routing descriptor, not a transport object wrapper.

Transport-specific request data belongs to runtime adapters.

### Put middleware wiring into contracts

Rejected.

Middleware wiring is runtime policy owned by platform packages and the tag registry.

The contracts package must not own DI wiring.

### Put route artifact generation into contracts

Rejected.

Route artifact generation is runtime-owned by `platform/routing`.

The contracts package defines descriptors and ports only.

### Treat route template as a metric label by default

Rejected.

Metric labels are governed by the closed allowlist in `docs/ssot/observability.md`.

Route template may be safe routing metadata or span context, but it is not a canonical metric label unless the observability SSoT explicitly changes.

### Treat routing descriptors as DTOs by convention

Rejected.

DTO detection is explicit opt-in only.

Routing descriptors are contracts shapes/descriptors unless a future epic explicitly marks a transport DTO with the DTO marker attribute.

## Non-goals

This ADR does not implement:

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

## Related SSoT

- `docs/ssot/routing-and-http-app-contracts.md`
- `docs/ssot/tags.md`
- `docs/ssot/observability.md`
- `docs/ssot/dto-policy.md`
- `docs/ssot/artifacts.md`

## Related epic

- `1.110.0 Contracts: Routing + HttpApp ports`
