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

```yaml
adrVersion: 1
status: pre-accepted
owner: core/contracts
```

## Context

The stable routing and HttpApp invocation contracts are defined under:

```text
packages/core/contracts/src/Routing/
packages/core/contracts/src/HttpApp/
```

Runtime routing and HttpApp packages need shared contracts for route declarations, route matching, allowed-method enumeration, action argument resolution, and action invocation without coupling `core/contracts` to HTTP transport APIs, platform implementations, integrations, generated artifacts, middleware implementations, or vendor-specific runtime objects.

The contracts package must remain a pure library boundary.

It must not depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- `platform/http` runtime package
- `platform/cli` runtime package
- concrete router implementations
- concrete middleware implementations
- concrete controller implementations
- concrete service container implementations
- generated route artifacts
- `devtools/*` packages
- repository tooling under `tools/**`

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

Coretsia defines routing and HttpApp contracts as format-neutral contracts in `core/contracts`.

The contracts include:

- `RouteDefinition`
- `RouteMatch`
- `RouterInterface`
- `RouteProviderInterface`
- `RoutingArtifactReadinessInterface`
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

Both routing descriptor models expose a stable schema version.

The initial schema version is:

```text
1
```

The safe exported public shapes include `schemaVersion`.

`RouteDefinition` methods are normalized to uppercase, deduplicated, and sorted by byte-order `strcmp`.

`RouteMatch` parameters are a deterministic string map, not a generic object or runtime payload carrier.

The existing `RouteDefinition::metadata()` and `RouteMatch::metadata()` maps carry safe format-neutral consumer-owned declarations, including the ordered `http_app_middleware` service-id list. List order is preserved; Contracts and Routing do not resolve or execute these services. HttpApp owns semantic admission and execution under `docs/ssot/http-app-dispatch.md`. No descriptor field or constructor argument is added.

Both are format-neutral routing shapes/descriptors.

They are not DTO-marker classes by default.

Their descriptor rules, exported shape policy, json-like value restrictions, redaction requirements, and deterministic ordering requirements are governed by:

```text
docs/ssot/routing-and-http-app-contracts.md
```

Routing descriptors must not expose:

- PSR-7 objects;
- `platform/http` request or response objects;
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

It is the source of `RouteDefinition` objects for explicit target-local source compilation.

The route provider contract exposes:

```text
id(): string
routes(): array
```

`id()` returns a stable provider id, such as an application id, module id, package id, or another owner-defined stable source identifier.

`routes()` returns a deterministic flat list of `RouteDefinition` objects. Provider-emitted order defines matching precedence within the provider.

The route provider contract does not prescribe how routes are discovered or loaded. Concrete providers may use owner-specific source declaration helpers internally, including groups, provided they return ordinary flattened descriptors. The contracts package does not implement a route provider or expose group objects.

`platform/routing` consumes the canonical TagRegistry provider order (priority descending, service id ascending by byte-order `strcmp`) and preserves provider-emitted route order without sorting by name or specificity.

The detailed policy is defined in:

```text
docs/ssot/routing-and-http-app-contracts.md
```

## Router decision

`RouterInterface` is the contracts port for matching scalar routing input to a `RouteMatch` and enumerating declared methods for a concrete path.

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
allowedMethods(string $path, ?string $host = null): array
```

A concrete router implementation may receive scalar matching input such as method, path, and optional host according to the interface shape.

Raw matching input is ephemeral and must not become canonical routing metadata.

The normalized successful output is `RouteMatch`.

A null return value represents no match.

`allowedMethods()` returns a `list<non-empty-string>` containing the unique exact declared methods of all admitted route domains accepting the supplied concrete encoded path, including placeholder requirements. The list is sorted ascending by byte-order `strcmp`; invalid, non-canonical, reserved, and unmatched paths return `[]`. Enumeration does not change first-match precedence, add implicit HEAD or OPTIONS, mutate routing context, or emit additional routing observations. Host remains ephemeral and does not participate in matching. The implementation belongs to `platform/routing`, as defined in `docs/ssot/routing-artifact.md`.

No-match rendering, exception mapping, or transport-specific error behavior is runtime-owned.

## Routing artifact readiness decision

`RoutingArtifactReadinessInterface` is the independent transport-neutral owner-artifact readiness port:

```text
assertReady(): void
```

Normal completion indicates readiness; failures use implementation-owned exceptions. The port exposes no artifact paths, payloads, filesystem APIs, concrete Routing services, or router operations and does not extend `RouterInterface`.

Routing validates the owner artifact through the shared immutable route table consumed by its router. The HTTP-owned entrypoint asserts readiness only when Routing is enabled in the effective target, before HTTP runtime construction. Readiness failures prevent startup. HTTP and Routing communicate through the contracts boundary without direct package dependencies.

## Route template decision

The raw request path is transport input.

The route template is the canonical safe routing metadata.

A route template may be used for matching diagnostics, debugging, safe route match metadata, span context subject to observability redaction rules, and runtime routing context.

The canonical runtime routing metadata/context key for the matched route template is:

```text
path_template
```

The public key identifier is `Coretsia\Contracts\Context\ContextKeys::PATH_TEMPLATE`. Routing owns its semantic mutation through `ContextWriterInterface`, removing stale values at match start and writing the complete declared template only on success.

Contracts owns the vocabulary and ports; Foundation owns storage and structural validation; Kernel owns UnitOfWork lifecycle and reset orchestration. See `docs/ssot/context-keys.md` and `docs/ssot/routing-artifact.md`.

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
- `platform/http` request objects;
- `platform/http` response objects;
- concrete middleware objects;
- concrete service container objects;
- concrete controller base classes;
- concrete response factories.

Runtime owner packages adapt transport-specific data into safe contract-level invocation inputs.

The concrete `platform/http-app` implementation preserves these exact port signatures. Standalone argument resolution does not require a PSR-7 request in the optional context map; standalone invocation consumes the explicitly supplied named arguments and returns the action result without HTTP adaptation. Request injection, same-instance combined action resolution and invocation, and HTTP response mapping remain owner-local runtime operations outside the format-neutral ports.

Invocation return values are implementation-owned.

A runtime adapter may convert invocation output into a transport-specific response outside `core/contracts`.

## Runtime ownership decision

`platform/routing` owns concrete route compilation and artifact-backed matching. It provides:

- deterministic provider aggregation and source-only route grouping;
- target-local route compilation and complete route admission;
- `routes@1` artifact generation, publication, and validation;
- immutable runtime route matching, allowed-method enumeration, and owner-artifact readiness.

The runtime router consumes only the validated immutable route table and does not resolve handlers or invoke actions. The detailed artifact and matching decision belongs to `docs/adr/ADR-0018-platform-routing-compiled-routes.md`.

`platform/http-app` owns concrete HTTP application dispatch. It provides non-terminal application matching through `RouterMiddleware`, an immutable request-local routing handoff, final application dispatch through `ActionDispatchMiddleware`, ordered route-specific middleware admission and execution, concrete argument resolution and action invocation, response adaptation, and canonical application 404/405 exception mapping.

Its canonical contributions are:

- `Coretsia\Platform\HttpApp\Middleware\RouterMiddleware::class` — `http.middleware.app`, priority `100`, metadata `[]`;
- `Coretsia\Platform\HttpApp\Middleware\ActionDispatchMiddleware::class` — `http.middleware.route`, priority `-1000`, metadata `[]`.

The corresponding code-level tag identifiers are `ReservedTags::HTTP_MIDDLEWARE_APP` and `ReservedTags::HTTP_MIDDLEWARE_ROUTE`, owned by Foundation.

Both contributions participate as a canonical pair only when `platform.http-app` is selected in the effective target module plan and its required runtime dependencies are satisfied. Package installation alone does not activate dispatch. Matching delegates successful matches and reserved paths through the remaining global chain; the dispatcher is the final effective route contribution after ordinary tagged and manual route middleware. Matched routes execute through the admitted invocation-local route-specific chain and action terminal; reserved handoffs delegate to the HTTP-owned terminal.

`platform/http` owns global middleware composition and the public Pipeline API, including guarded continuation execution and mandatory outermost Problem Details participation when HttpApp is enabled. The canonical nine-slot taxonomy and composition rules remain governed by `docs/ssot/http-middleware-wiring.md` and `docs/ssot/tags.md`.

HttpApp dispatch and HTTP-specific adaptation are governed by `docs/ssot/http-app-dispatch.md` and `docs/adr/ADR-0043-platform-http-app-router-middleware-action-invoker.md`. These are runtime ownership rules, not dependencies of `core/contracts`.

## DI tag decision

The contracts package owns no DI tags.

The contracts package must not declare routing or HttpApp DI tag identifier constants.

The contracts package must not define additional code-level registries for Coretsia-reserved middleware DI tag identifiers.

The contracts package may reference reserved routing and middleware tags in documentation as runtime policy.

Reserved tag ownership remains governed by:

```text
docs/ssot/tags.md
```

Coretsia-reserved DI tag identifier strings are declared in:

```text
Coretsia\Foundation\Tag\ReservedTags
```

Non-owner packages using reserved tags must follow the tag registry rules, use `Coretsia\Foundation\Tag\ReservedTags::*` in runtime package source, and must not redefine competing tag semantics, competing metadata schema, or additional code-level registries for Coretsia-reserved DI tag identifiers.

## Config decision

The contracts package owns no config roots or config keys for routing or HttpApp.

Routing and HttpApp contracts must not require package config files.

Runtime configuration belongs to the respective owner packages under `docs/ssot/config-roots.md`.

## Artifact decision

The contracts package owns no route artifacts.

`platform/routing` produces `routes@1` at the authoritative target-local artifact root with basename `routes.php`, outside Kernel immutable generations. The artifact contains complete flattened canonical route descriptors in matching order.

The global artifact envelope and deterministic serialization law remain governed by `docs/ssot/artifacts.md`; the owner-specific schema and lifecycle are governed by `docs/ssot/routing-artifact.md`.

The contracts package must not generate route artifacts.

The contracts package must not own the routes artifact payload schema.

## Json-like payload decision

Any json-like payload exposed by routing or HttpApp contracts must follow the canonical json-like runtime value policy defined by `docs/ssot/json-like-runtime-values.md`:

- allowed scalars are `string`, `int`, `bool`, and `null`;
- floats are forbidden, including `NaN`, `INF`, and `-INF`;
- lists preserve order;
- maps are ordered deterministically by byte-order `strcmp`;
- raw payload values must not be printed or logged;
- diagnostics may expose only safe derivations such as `hash(value)` or `len(value)`.

This applies to safe routing metadata, route defaults, route requirements where applicable, and route match metadata.

`RouteMatch.parameters` is a deterministic string map for matched path parameters and is not a generic json-like payload carrier.

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

DTO gates apply only through explicit marker opt-in.

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

`platform/routing` implements route matching and route compilation against stable descriptors.

`platform/http-app` implements application matching integration, final action dispatch, argument resolution, action invocation, and route-specific middleware execution without forcing HTTP request or response types into `core/contracts`.

Routing produces and validates its owner artifact independently of the Kernel generation and the contracts package.

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

Routing descriptors are contracts shapes/descriptors and are not DTO-marker classes by default.

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
- `docs/ssot/routing-artifact.md`
- `docs/ssot/http-app-dispatch.md`
- `docs/ssot/http-middleware-wiring.md`
- `docs/ssot/context-keys.md`
