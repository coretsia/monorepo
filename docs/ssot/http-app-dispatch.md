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

# HTTP Application Dispatch SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: platform/http-app
```

## Scope

This document is the Single Source of Truth for the `platform/http-app` application dispatch boundary: explicit handler registration, request-local routing handoff, ordered route-specific middleware admission and execution, guarded continuation policy, action argument resolution and invocation, response mapping, canonical application routing failures, and action observation ownership.

The implementation boundary is:

```text
packages/platform/http-app/src/
packages/platform/http-app/config/http_app.php
packages/platform/http-app/config/rules.php
```

This document owns the HttpApp-specific consumption of existing routing, container, HTTP composition, and error contracts. It does not redefine the canonical Routing artifact, HTTP middleware taxonomy, format-neutral ports, error descriptor, or observability catalogs.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Application route matching, middleware admission, and action execution MUST form one deterministic, fail-closed application dispatch boundary within the existing HTTP runtime, without implicit handler discovery or competing runtime infrastructure.

## Authority and ownership (MUST)

- `core/contracts` owns the format-neutral Routing and HttpApp ports and route descriptors.
- `core/foundation` owns declarative service registration, reserved tag identifiers, deterministic tagged discovery, container resolution and service lifecycle, reserved-path matching, stable JSON encoding, and Stopwatch.
- `platform/routing` owns route declarations, compilation, `routes@1` publication and hydration, effective `RouterInterface` behavior, route availability, and `ContextKeys::PATH_TEMPLATE` mutation.
- `platform/http` owns global middleware composition, the reusable public PSR-15 `Pipeline`, continuation enforcement, HTTP entrypoint readiness, PSR-17 bindings, the terminal system-endpoint fallback, and HTTP request finalization.
- `platform/http-app` owns the application matching contribution, routing handoff, route-specific middleware admission, explicit action resolution and invocation, action response mapping, application 404/405 exception mapping, and action observations.
- `platform/errors` owns canonical error normalization, mandatory descriptor redaction, and eligible reporting.
- `platform/problem-details` owns the canonical outer HTTP error boundary, public Problem Details rendering, and validated Allow-header materialization.
- Kernel owns effective target composition, production graph completeness, UnitOfWork and reset orchestration.

HttpApp MUST consume the effective `RouterInterface` and the explicitly public HTTP `Pipeline` composition API. It MUST NOT consume HTTP-internal stack builders, runtime factories, entrypoint guards, fallback implementations, or HttpKernel internals.

Direct Coretsia PHP source dependencies are limited to `core/contracts`, `core/foundation`, and `platform/http`. Runtime-only Composer and module dependencies on Routing, Errors, and Problem Details do not authorize direct PHP source dependencies on those implementations. HTTP, Routing, Errors, and Problem Details MUST NOT acquire reverse dependencies on HttpApp.

The format-neutral contract boundary remains governed by [Routing and HttpApp Contracts SSoT](./routing-and-http-app-contracts.md). The HTTP-specific adaptation APIs described here do not extend those ports with PSR-7 or PSR-15 types.

## Core invariants (MUST)

- A successful match is a non-terminal routing decision, not action execution.
- `RouteMatch::handler()` is a logical action identifier resolved exclusively through an explicit registration.
- Route-specific middleware admission MUST succeed before the action terminal can execute.
- A matched application invocation admits at most one action terminal entry through the canonical guarded Pipeline.
- The original routing handoff and routing inputs MUST remain unchanged through all subsequent delegating middleware boundaries.
- A middleware short-circuit MUST NOT cause implicit action execution.
- Missing or invalid dispatch state, unavailable services, and continuation violations MUST fail closed without rematching or falling back to the HTTP-owned terminal for a matched application route.
- All application dispatch work remains inside the existing HTTP UnitOfWork and outer error boundary.

## Module participation and declarative DI (MUST)

`platform.http-app` participates only when explicitly selected in the effective target module plan. Installation of `coretsia/platform-http-app` alone MUST NOT activate application dispatch.

Its required runtime module identities are exactly:

```text
core.foundation
platform.errors
platform.http
platform.problem-details
platform.routing
```

Each direct runtime module requirement has a corresponding direct production Composer requirement. Missing required modules MUST fail through canonical module planning. Package-local discovery or implicit module activation is not permitted.

`HttpAppServiceProvider` registers through the existing Foundation definition-provider API. Its `define()` method is the single declarative wiring source for source and compiled definition production.

The provider MUST:

- read the effective target-local subtree through `ContainerDefinitionContext::configRoot('http_app')`;
- reuse `HandlerResolver::validateRegistrations()`, `ArgumentResolver::validateContainerTypes()`, and `RouteMiddlewareSelector::validateServiceIds()` for semantic validation;
- validate all configuration and boolean values before contributing dependent definitions;
- declare every configured handler service id, allowlisted concrete argument type, and admitted route middleware service id through `ContainerDefinitionBuilder::requireService()`;
- use canonical `ContainerValueReference::service()` references for service dependencies and pass validated boolean values directly as constructor arguments;
- require the effective `ConfigRepositoryInterface`, Foundation `Container`, `RouterInterface`, `ResponseFactoryInterface`, `StreamFactoryInterface`, `TracerPortInterface`, `MeterPortInterface`, PSR-3 `LoggerInterface`, and `Stopwatch` services.

Application-owned actions, concrete argument services, and route-specific middleware MUST be explicitly registered through the existing Foundation declarative definition APIs, including `ContainerDefinitionBuilder::classService()`, `classMethodFactory()`, `serviceMethodFactory()`, and `alias()` where applicable. Their declared sharing policy remains authoritative.

The provider contributes shared definitions for `HandlerResolver`, `ArgumentResolver`, `ActionInvoker`, `ActionResponseMapper`, `RouterMiddleware`, `RouteMiddlewareSelector`, `ActionDispatchMiddleware`, and `HttpAppProblemMapper`.

The existing `ArgumentResolverInterface` and `ActionInvokerInterface` bindings are aliases to their respective implementations. HttpApp MUST NOT replace the effective router, error handler, observability ports, PSR-17 factories, global Pipeline, or HTTP-owned terminal handler.

### Effective configuration consumption

`HandlerResolver`, `ArgumentResolver`, and `RouteMiddlewareSelector` MUST receive the effective read-only `ConfigRepositoryInterface` through canonical service references. Each reads its own required list exactly once at construction, without a fallback default, reuses the provider-time validator, and retains the validated immutable lookup or allowlist rather than a mutable configuration registry.

The effective configuration and its source metadata retain their canonical Kernel artifact semantics. Source and compiled runtime definitions MUST consume equivalent registrations, allowlists, and flags. Dynamic handler maps and allowlists MUST NOT be copied into declarative constructor argument maps, container parameters, or escaped structural keys. Logical identifiers remain values in the effective configuration.

### Deferred service completeness

Structural ruleset validation and provider-owned semantic validation do not resolve services. A required-service declaration records a production graph dependency, not an instruction to instantiate the service at definition time.

Configured but missing handler, argument, or route middleware bindings MUST fail the canonical production graph completeness validation. The Kernel graph validator owns final completeness; the source `ContainerDefinitionApplier` is not a substitute for it.

A logical handler absent from the registration list is a dispatch-time failure. A registered service whose construction or resolved-instance admission fails is also a dispatch-time failure. Runtime admission independently checks explicit service registration; a graph-valid definition does not guarantee successful construction or an admissible instance.

HttpApp MUST NOT introduce an eager source-container completeness check, independent graph validator, executable definitions, runtime objects in artifacts, or a second service registry.

## Configuration policy (MUST)

The owning package defines the complete `http_app` subtree. `config/http_app.php` returns exactly the following defaults without repeating the root wrapper:

```php
return [
    'handlers' => [],
    'route_middleware' => [],
    'arguments' => [
        'allow_request' => true,
        'allow_route_params' => true,
        'allow_container_types' => [],
    ],
    'response' => [
        'json' => [
            'enabled' => true,
        ],
        'html' => [
            'enabled' => true,
        ],
    ],
    'tracing' => [
        'enabled' => true,
    ],
    'metrics' => [
        'enabled' => true,
    ],
    'logging' => [
        'enabled' => true,
    ],
];
```

The declarative ruleset uses `schemaVersion = 1`, `configRoot = http_app`, required canonical keys, and `additionalKeys = false` for every closed map. The three admission collections MUST be lists. Each handler item is a closed map of two required strings; route middleware items use the canonical `non-empty-string-no-ws` structural rule; container-type items are strings. All feature flags MUST be exact booleans.

Ruleset files perform structural validation only. Semantic admission remains provider-owned and MUST NOT be implemented through executable ruleset validators or callbacks.

### Handler registration list

`http_app.handlers` is a list of closed records. Each record contains exactly `action_id` and `service_id`, both non-empty strings. Empty lists are valid. Record key order is not semantic.

For example:

```php
'handlers' => [
    [
        'action_id' => 'hello.show:detail',
        'service_id' => 'hello.action',
    ],
],
```

The `action_id` MUST match the existing Routing logical identifier grammar:

```text
\A[A-Za-z_][A-Za-z0-9_.:-]*\z
```

Its maximum length is 256 bytes. Comparison is exact and case-sensitive, without trimming, normalization, or case folding. Duplicate action ids MUST be rejected even when their service ids agree. Different action ids MAY reference the same service id.

Each `service_id` MUST satisfy the canonical Foundation declarative service-id policy through `ContainerValueReference::service()` and MUST NOT contain ASCII control bytes (`0x00..0x1F`, `0x7F`). The exact admitted identifier is retained without transformation.

`HandlerResolver::validateRegistrations(mixed $registrations): array` builds the internal action-id-to-service-id lookup. Invalid input throws `InvalidArgumentException('http-app-handlers-invalid')` without rejected identifiers or previous validation exceptions in custom state.

The lookup is immutable, private to the resolver, and contains identifiers only. It is neither a service registry nor a callable or resolved-action cache. The registration-list representation MUST NOT widen canonical configuration source-path or container map-key grammar.

### Concrete argument allowlist

`http_app.arguments.allow_container_types` is an exact list of unique canonical class-name strings. `ArgumentResolver::validateContainerTypes(mixed $types): array` uses `ContainerValueReference::class()` for declarative admission without loading or constructing the configured classes.

Invalid input throws `InvalidArgumentException('http-app-container-types-invalid')` without rejected values or previous validation exceptions. Concrete-type eligibility is checked at runtime before service resolution.

### Route middleware admission list

`http_app.route_middleware` is an actual PHP list of unique non-empty service-id strings, including the valid empty list. Each entry MUST satisfy the same canonical Foundation service-id admission and ASCII control-byte exclusion as handler service ids. Identifiers MUST be compared byte-for-byte without trimming, normalization, or case folding.

`RouteMiddlewareSelector::validateServiceIds(mixed $value): array` rejects invalid input with `InvalidArgumentException('http-app-route-middleware-invalid')` and does not retain rejected values or previous exceptions.

The list defines admission only, not execution order. The following exact canonical framework role ids MUST NOT be admitted:

```text
Coretsia\Platform\HttpApp\Middleware\RouterMiddleware
Coretsia\Platform\HttpApp\Middleware\ActionDispatchMiddleware
Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware
```

`RouteMiddlewareSelector::FORBIDDEN_ROLE_IDS` owns these package-internal admission identities. The Problem Details identity is a string, not a direct dependency on the concrete implementation. Runtime resolved-instance admission MUST enforce these identities independently of the configured id.

## Canonical HTTP middleware participation (MUST)

HttpApp contributes exactly two PSR-15 middleware services using the existing Foundation declarative DI and `ReservedTags` APIs:

| service id                        | reserved tag                          | priority | metadata |
|-----------------------------------|---------------------------------------|---------:|----------|
| `RouterMiddleware::class`         | `ReservedTags::HTTP_MIDDLEWARE_APP`   |      100 | `[]`     |
| `ActionDispatchMiddleware::class` | `ReservedTags::HTTP_MIDDLEWARE_ROUTE` |    -1000 | `[]`     |

Both class identities belong to `Coretsia\Platform\HttpApp\Middleware`. The reserved tag values are `http.middleware.app` and `http.middleware.route`, respectively. No toggle metadata or manual substitute is permitted.

The existing HTTP composition boundary MUST enforce the canonical pair, exact slot, priority, empty metadata, effective module participation, and exact resolved runtime class identities. A selected HttpApp module requires both roles; an unselected module requires neither. Missing, malformed, or incompatible effective roles MUST fail composition before request execution.

`HttpErrorHandlingMiddleware` from `platform/problem-details` is mandatory when HttpApp is selected. The HTTP composition boundary MUST ensure its canonical `http.middleware.system_pre` contribution (priority `1000`, metadata `[]`) is the actual outermost effective middleware, with the exact concrete runtime class bound under the canonical id. A merely compatible `MiddlewareInterface` substitute does not satisfy this role.

The HTTP-owned stack builder MUST place `ActionDispatchMiddleware` after every effective ordinary global route middleware, including manual entries and tagged entries with lower numerical priority. This is a canonical final-placement rule, not merely a consequence of tag priority sorting.

The relevant execution flow is:

```text
Canonical outer Problem Details boundary
  -> existing HTTP global middleware composition
  -> RouterMiddleware (app)
  -> remaining effective app middleware
  -> route_pre middleware
  -> ordinary route middleware
  -> ActionDispatchMiddleware (final effective route contribution)
       -> reserved handoff: existing HTTP terminal handler
       -> matched handoff: invocation-local guarded Pipeline
            -> ordered route-specific middleware
            -> action terminal
```

This flow describes the application dispatch portion of the canonical topology. Post-phase wrappers, tagged ordering, manual append and deduplication within global composition, and reverse response decoration remain governed by [HTTP Middleware Wiring SSoT](./http-middleware-wiring.md) and [HTTP Middleware Catalog SSoT](./http-middleware-catalog.md).

Ordinary middleware contributes through existing Foundation definitions and reserved tags. Registration alone MUST NOT require a direct dependency on `platform/http`; such a dependency is justified for consumers of its public runtime APIs. HttpApp MUST NOT introduce a middleware registry or a second execution engine.

## Application route matching (MUST)

`RouterMiddleware` is the sole canonical producer of the request-local routing handoff. It receives the effective `RouterInterface` and MUST NOT resolve actions, select route middleware, or map responses.

Before matching, the middleware MUST reject the presence of `RouteDispatchState::ATTRIBUTE` through exact attribute-key admission using `ServerRequestInterface::getAttributes()`. An explicitly present null or incorrectly typed value is not absence.

For each request entering this boundary, the middleware MUST call `RouterInterface::match()` exactly once with the exact request method, encoded URI path, and URI host (empty string converted to null). No decoding, trimming, case folding, or method normalization is permitted.

On a successful match, it creates the immutable matched handoff, attaches it through `withAttribute()`, delegates exactly once to the downstream continuation, and returns the resulting response unchanged.

On a null match:

- consult the existing `ReservedPathMatcher::match()` policy;
- for a reserved path, attach the explicit reserved handoff and delegate exactly once to the downstream chain;
- convert only the canonical `InvalidArgumentException('http-reserved-path-invalid')` from reserved-path admission to `HttpNotFoundException::notFound()` without retaining rejected data or the previous exception;
- propagate unexpected infrastructure failures unchanged;
- for an ordinary non-reserved miss, reject an empty or malformed HTTP method token as not found without allowed-method enumeration;
- for a syntactically valid unmatched method, call `RouterInterface::allowedMethods($path, $host)` exactly once;
- an empty allowed-method list produces `HttpNotFoundException::notFound()`;
- a non-empty allowed-method list produces `HttpMethodNotAllowedException::methodNotAllowed($allowedMethods)` subject to exact method-list admission.

The method token syntax is:

```text
\A[!#$%&'*+\-.^_`|~0-9A-Za-z]+\z
```

Valid extension methods remain eligible for method-mismatch classification. Matching is exact and case-sensitive; there is no implicit GET-to-HEAD expansion or automatic OPTIONS handling. `OTHER` is an observability sentinel only.

Reserved ownership remains governed by [Reserved Path Matching SSoT](./reserved-path-matching.md) and [HTTP System Endpoints SSoT](./http-system-endpoints.md). HttpApp does not take ownership of system endpoints or their terminal fallback behavior.

HttpApp consumes the boot-selected artifact-backed Routing implementation through the existing effective router. It MUST NOT inspect, compile, read, hydrate, publish, or select route artifacts independently. Existing HTTP entrypoint readiness and Routing artifact ownership remain authoritative.

## Request-local routing handoff (MUST)

The canonical handoff is the final readonly `Coretsia\Platform\HttpApp\Routing\RouteDispatchState` with a private constructor and the exact attribute identity:

```php
public const string ATTRIBUTE = 'coretsia.http_app.route_dispatch';
```

Its factory and validation API is:

```php
public static function matched(
    ServerRequestInterface $request,
    RouteMatch $match,
): self;

public static function reserved(
    ServerRequestInterface $request,
): self;

public function match(): ?RouteMatch;

public function assertRequest(
    ServerRequestInterface $request,
): void;

public static function fromRequest(
    ServerRequestInterface $request,
): self;
```

Its immutable state contains exactly the original method, encoded URI path, original URI host normalized only from empty string to null, and nullable `RouteMatch`. A non-null match represents a successful application match; null represents only explicit reserved-path delegation, not an ordinary routing miss.

The state MUST NOT retain the request object or expose diagnostic or serialization APIs for the captured inputs.

`fromRequest()` admits only an actual `RouteDispatchState` instance at the exact attribute key. Missing or incorrectly typed state fails with `HttpAppDispatchException::REASON_ROUTE_STATE_INVALID`. `assertRequest()` compares the exact original method, encoded path, and host (with only empty-to-null conversion) and fails with `REASON_ROUTE_INPUT_CHANGED` on mismatch.

Downstream middleware MAY inspect the matched route through `RouteDispatchState::fromRequest($request)->match()`. It MUST NOT manufacture, replace, or remove the canonical handoff. Correct type alone does not establish original-state identity.

`ActionDispatchMiddleware` consumes the handoff only through `fromRequest()` and `assertRequest()` before middleware selection. The global guarded Pipeline enforces the identity of the original handoff across delegations to this boundary. The route-specific action terminal captures the same state instance and verifies it again before dispatch.

Other legitimate request adaptations, including unrelated request attributes or parsed-body changes, remain supported when routing identity is preserved and applicable security policies are respected.

The attribute is not a ContextKey. No second RouteMatch, mutable ownership token, request registry, ContextStore entry, or continuation guard belongs to the state object.

## Guarded continuation policy (MUST)

Both the global HTTP Pipeline with effective HttpApp participation and the invocation-local route-specific Pipeline MUST use the same HTTP-owned guarded continuation implementation with:

```php
guardedContinuation: true,
protectedRequestAttributes: [RouteDispatchState::ATTRIBUTE],
protectRoutingInputs: true,
```

Global guarded mode follows effective module participation, not a separate feature toggle. When HttpApp is not selected, the global composition uses unguarded mode, an empty protected-attribute list, and disabled routing-input protection.

### Single-entry and expiration

Each guarded middleware `process()` invocation receives an invocation-local downstream continuation. The middleware MAY return a response without entering it, or delegate at most once while `process()` is active. This is a Coretsia dispatch invariant, not a universal PSR-15 restriction.

The continuation MUST be marked entered before protected-attribute or routing-input validation and before downstream execution. Entry remains consumed when validation or downstream execution throws. A repeated attempt MUST fail before any further downstream execution.

The continuation MUST expire through `finally` when its owning `process()` returns or throws, including short-circuits. Expiration MUST release the downstream handler reference, captured protected values, and captured routing-input values. The continuation MUST NOT be cloneable or retained by application middleware beyond the invocation.

An expired continuation MUST fail before any downstream execution, even during a later request. No additional action resolution, invocation, or observation may result from repeated or expired entry.

### Protected attribute identity and routing inputs

The guarded Pipeline captures protected attribute values present at each middleware entry. A previously absent attribute can be introduced by the canonical matching boundary; once present, the exact key and original value identity MUST be preserved through every subsequent delegating boundary. Replacement with an equivalent but distinct `RouteDispatchState` instance is invalid.

When routing-input protection is enabled and a protected attribute is present, the continuation captures the exact method, encoded URI path, and URI host as returned by the request. The Pipeline compares the exact raw tuple at delegation without normalization. The handoff itself uses the separate empty-host-to-null convention for its dispatch-boundary comparison.

Attribute and routing-input checks MUST occur before entering the next middleware or terminal. A changed method, encoded path, or host MUST NOT be delegated to a downstream middleware in the expectation that it will later be restored. Temporary mutation followed by restoration in a later middleware is rejected at the first invalid delegation.

Terminal identity and `assertRequest()` checks remain necessary final admission checks; they MUST NOT replace continuation-boundary protection. No automatic rematching or second routing state is permitted.

### Continuation failures

The HTTP Pipeline owns the following exact `LogicException` messages:

```text
http-pipeline-continuation-reused
http-pipeline-continuation-expired
http-pipeline-protected-attribute-changed
http-pipeline-protected-routing-input-changed
```

The checks are ordered by expiration, previous entry, protected attribute preservation, then routing-input preservation. Failures expose fixed reason tokens only, not protected names or values, routing inputs, downstream service identities, or continuation state.

These failures MUST propagate unchanged through the canonical outer error boundary. They MUST NOT be wrapped in `HttpAppDispatchException`, mapped as route misses or method mismatch, or receive a dedicated continuation mapper or error-code family.

Guarded execution establishes at-most-once action terminal entry within the canonical dispatch invocation. It does not establish transaction rollback, cross-request idempotency, retry safety, or a sandbox against deliberately hostile application PHP code. A violation after completed action execution does not roll back side effects or rewrite completed observations.

## Route-specific middleware selection and execution (MUST)

### Route metadata

`RouteDefinition::metadata()` and `RouteMatch::metadata()` remain the canonical declaration and handoff surfaces. The sole HttpApp-consumed middleware selection member is `RouteMiddlewareSelector::METADATA_KEY`, exactly `http_app_middleware`.

For example, a route metadata map MAY contain:

```php
[
    'http_app_middleware' => ['hello.authorize', 'hello.decorate'],
]
```

Each selected id MUST be explicitly admitted in `http_app.route_middleware`. An absent member means an empty selection. An explicitly present empty list is also valid; null, a scalar, a non-list map, malformed entries, duplicate ids, forbidden ids, and unadmitted ids are invalid, not absence.

`RouteMiddlewareSelector::select(RouteMatch $match): array` reads only this exact member, reuses `validateServiceIds()`, checks exact allowlist membership, and returns the original route-declared list order. It does not resolve services, discover tags, inspect artifacts, infer ids from route or handler names, or execute middleware.

Selection MUST preserve order without sorting or implicit deduplication. Route-specific order is independent of the configuration admission-list order and global tag priority.

Route metadata remains a bounded safe json-like map. It MUST NOT contain middleware objects, factories, executable values, or raw request data. Existing `RouteGroup` inheritance combines group and route metadata without duplicate keys, including duplicate inherited `http_app_middleware` keys even when an inherited value is null. There is no recursive HttpApp metadata merge policy.

The canonical flattened route definitions, `routes@1` compilation, validation, deterministic serialization, and hydration preserve the list's semantic order. Routing remains format-neutral and MUST NOT interpret the member as an execution instruction or resolve its services. See [Routing Artifact SSoT](./routing-artifact.md).

### Explicit runtime service admission

For a matched handoff, `ActionDispatchMiddleware` MUST obtain the ordered selection from the original match. It verifies all selected ids through Foundation `Container::serviceIds()` before calling `Pipeline::resolveServices()`.

`Container::has()` alone is not evidence of explicit registration because the existing Container supports concrete-class autowire fallback. An unregistered selected id MUST NOT cause implicit autowiring.

`Pipeline::resolveServices()` resolves each selected service exactly once per chain construction, in selection order, and admits only actual `MiddlewareInterface` instances through the effective Container. The dispatcher MUST reject any resolved middleware whose exact runtime class belongs to `RouteMiddlewareSelector::FORBIDDEN_ROLE_IDS`, including forbidden instances reached through aliases or alternate ids. The selector-owned list is reused without a second policy registry.

Malformed or unadmitted selection and forbidden role instances fail with `REASON_ROUTE_MIDDLEWARE_INVALID`. Missing explicit registration, container resolution failure, or incompatible resolved values fail with `REASON_ROUTE_MIDDLEWARE_UNAVAILABLE`. Resolution failures MUST NOT be classified by matching raw exception messages; rejected values and previous resolution exceptions MUST NOT be retained.

Admission of the complete chain MUST succeed before middleware execution or action invocation. No invalid or unavailable selected middleware may be silently skipped.

### Invocation-local Pipeline

For the reserved handoff, `ActionDispatchMiddleware` delegates exactly once to the existing downstream terminal and performs no route-specific selection, action resolution, or action observations.

For a matched handoff, the dispatcher constructs the existing public HTTP `Pipeline` with the exact resolved middleware sequence, an invocation-local `RequestHandlerInterface` action terminal, and the mandatory guarded policy. It calls that Pipeline with the effective request.

The terminal captures the original `RouteDispatchState` and its `RouteMatch`, not a new match from downstream input. On entry it MUST verify `RouteDispatchState::fromRequest($effectiveRequest) === $capturedState` and then call the captured state's `assertRequest($effectiveRequest)`. Only successful validation admits action observations and invocation.

The terminal calls `ActionInvoker::dispatch($match, $effectiveRequest)` once and maps the result through `ActionResponseMapper::map()`. The request is the effective request provided by the last delegating middleware. An empty selection executes the same terminal behavior.

Ordinary middleware MAY short-circuit with its own `ResponseInterface`; the dispatcher returns it unchanged and MUST NOT resolve or invoke the action on that path. Middleware MAY decorate a delegated response as it unwinds. Middleware execution failures after successful admission propagate unchanged to the existing outer error boundary.

A matched route MUST NOT execute the HTTP-owned system fallback. No nested UnitOfWork, additional error boundary, second Pipeline implementation, or independent invocation guard is permitted.

Explicit membership of the same service in global and route-specific composition represents two execution scopes. It MUST NOT be silently deduplicated across those scopes.

### Container-owned lifecycle

The existing Foundation Container owns shared and non-shared middleware construction and reuse. Non-shared route middleware is resolved for each applicable chain construction. Shared stateful middleware MUST comply with the canonical stateful/reset policy.

The route-specific dispatcher MUST NOT resolve non-selected middleware or retain resolved sequences and terminal handlers between invocations. The guarantee concerns dispatcher-owned resolution only. Independent canonical lifecycle consumers, including `ResetOrchestrator`, retain their existing service resolution semantics; a shared reset-tagged middleware MAY be resolved by canonical reset even when not selected for the route.

Canonical reset discovery MUST NOT be filtered by matched route or executed middleware. No executed-instance registry is introduced.

## Action resolution and invocation (MUST)

### Invokable service admission

`HandlerResolver::resolve(string $actionId): object` resolves the exact action id exclusively through its immutable registration lookup. An unmapped id MUST NOT be interpreted as a class-string, function, arbitrary method, or container service id.

The resolver MUST verify exact explicit registration through `Container::serviceIds()` before calling `Container::get()`. Container construction, factories, aliases, sharing, and lifecycle remain Foundation-owned. No direct action construction, recursive autowiring, callable cache, or additional service locator is permitted.

The resolved value MUST be an application-owned invokable object with an actual public non-static `__invoke()` method. Inherited public non-static invocation methods are valid. Closures, magic `__call()` substitutes, non-object results, by-reference parameters, variadic parameters, union types, and intersection types are rejected. Untyped and named-type parameters are admitted at the signature boundary; argument-source compatibility is checked separately.

Signature validation reflects the actual invocation method without calling it. Reflection state is invocation-local and MUST NOT be cached.

An absent registration fails with `REASON_HANDLER_UNMAPPED`; missing explicit service registration or container lookup failure uses `REASON_HANDLER_UNAVAILABLE`; unsupported service or invocation shape uses `REASON_HANDLER_INVALID`.

### Format-neutral ports and HTTP adaptation

The canonical contracts-level API remains:

```php
public function resolve(
    string $actionId,
    RouteMatch $match,
    array $context = [],
): array;

public function invoke(
    string $actionId,
    array $arguments = [],
): mixed;
```

`ArgumentResolverInterface::resolve()` requires `$actionId === $match->handler()`, resolves its own explicitly registered action, and delegates to the shared argument resolution implementation without a request. The optional safe context map is ignored for baseline source selection and MUST NOT carry PSR-7 objects or override handler registration, request-injection policy, route parameters, or service admission.

`ActionInvokerInterface::invoke()` resolves the action through `HandlerResolver`, validates the supplied named arguments, and invokes it without automatically resolving or injecting additional values.

The HTTP-specific package-internal adaptation APIs are:

```php
public function resolveForAction(
    object $action,
    RouteMatch $match,
    ?ServerRequestInterface $request,
): array;

public function dispatch(
    RouteMatch $match,
    ServerRequestInterface $request,
): mixed;
```

`ActionInvoker::dispatch()` resolves the action exactly once from `$match->handler()`, passes the same object to `ArgumentResolver::resolveForAction()`, and invokes that same instance. The same-instance guarantee applies to combined HTTP dispatch, not independently executed calls of the format-neutral ports.

### Argument resolution precedence

The shared resolver reflects only the admitted `__invoke()` signature and produces an `array<string, mixed>` of named arguments. Each declared parameter follows this precedence:

1. Explicitly enabled request injection.
2. Explicitly enabled matched route parameter by exact parameter name.
3. Explicitly allowlisted concrete container service type.
4. Declared PHP parameter default.
5. Deterministic resolution failure.

Request injection requires `http_app.arguments.allow_request = true`, an explicitly supplied HTTP request, and the exact `ServerRequestInterface` named type. The resolver MUST NOT obtain requests from globals, ContextStore, or the container.

Route parameter injection requires `http_app.arguments.allow_route_params = true` and an exact matching key in `RouteMatch::parameters()`. Untyped, `mixed`, and `string` parameters are eligible. The canonical encoded string is preserved without URL decoding, scalar coercion, model binding, or parameter-name normalization. A selected but incompatible route parameter fails with `REASON_ARGUMENT_INVALID` rather than falling through to a service or default.

Concrete service injection requires an exact non-builtin named type in `http_app.arguments.allow_container_types`. Membership uses the exact `ReflectionNamedType::getName()` value without case folding or relative-name expansion. Interfaces, abstract classes, enums, traits, and unsupported relative type names are not eligible. Class eligibility is checked without reflective construction.

The type itself MUST be explicitly registered in `Container::serviceIds()` and resolution MUST use `Container::get()` with that exact id. The returned object MUST satisfy the declared concrete type. An application-owned factory definition MAY supply the instance. A selected but unavailable or incompatible service fails with `REASON_ARGUMENT_UNRESOLVABLE`, not a PHP default.

Declared defaults remain authoritative when no preceding source applies. Nullable declarations alone MUST NOT cause inferred null injection. Unavailable required arguments and default-reflection failures use `REASON_ARGUMENT_UNRESOLVABLE`.

Route metadata, query parameters, parsed body values, headers, cookies, and arbitrary context entries are not argument sources.

### Named invocation

Both invocation entrypoints reuse one resolved-action invocation implementation. The action MUST remain callable and satisfy the admitted signature. All supplied argument keys MUST be string names of declared parameters; unknown names, numeric keys, and missing required arguments fail with `REASON_ARGUMENT_INVALID`. Omitted optional parameters use their declared PHP defaults.

The canonical invocation is direct named argument unpacking under `strict_types=1`:

```php
$action(...$arguments)
```

`ReflectionMethod::invokeArgs()`, `call_user_func_array()`, scalar-coercing adapters, and a second PHP parameter type system MUST NOT be used.

Handler admission, handler resolution, and argument resolution failure prevent invocation. No failed resolution or invocation is retried. The admitted action is invoked exactly once per admitted terminal entry after successful resolution. The invoker returns the declared action value unchanged and propagates native invocation type errors and action-thrown `Throwable` unchanged. It MUST NOT map HTTP responses, normalize errors, or report failures.

## Action response mapping (MUST)

`ActionResponseMapper::map(mixed $value): ResponseInterface` uses the effective HTTP-owned `ResponseFactoryInterface` and `StreamFactoryInterface` bindings.

- An existing `ResponseInterface` passes through unchanged, without inspection of its status, headers, or body by the mapper, independently of representation flags.
- A string requires `http_app.response.html.enabled = true` and produces status `200`, `Content-Type: text/html; charset=utf-8`, and the exact application-provided string body. This is explicit HTML output; escaping untrusted data remains the action owner's responsibility.
- Null, bool, int, and array values require `http_app.response.json.enabled = true` and produce status `200`, `Content-Type: application/json`, and the exact `StableJsonEncoder::encodeStable()` output, including its final LF and canonical list/map distinction. An empty PHP array encodes as `[]`.
- Floats (including NaN and INF), arbitrary objects, resources, closures, and other unsupported values fail with `REASON_RESPONSE_UNSUPPORTED`.
- Disabled representation mapping fails with `REASON_RESPONSE_DISABLED`, without silently choosing another representation.
- Invalid nested json-like values, encoder failures, and response factory, stream factory, header or body construction failures use `REASON_RESPONSE_MAPPING_FAILED`; admitted `HttpAppDispatchException` failures propagate unchanged.

No partially constructed response may be returned. Mapping MUST NOT retain unsafe previous exceptions or automatically serialize DTOs, invoke `JsonSerializable` or `__toString()`, perform content negotiation, inspect `ContextKeys::HTTP_RESPONSE_FORMAT`, or introduce another serializer, HTML renderer, or Problem Details renderer.

Application response values remain separate from diagnostic redaction. Successful application response bodies MUST NOT be automatically redacted by HttpApp. Neither response bodies nor return values may be logged by the mapper.

## Error handling boundary (MUST)

`HttpNotFoundException` and `HttpMethodNotAllowedException` are final `RuntimeException` classes with private constructors and fixed safe reason messages.

### Application route miss

```php
public const string ERROR_CODE = 'CORETSIA_HTTP_404';
public static function notFound(): self;
public function errorCode(): string;
public function reason(): string;
```

The exact reason and exception message are `http-app-not-found`. No caller-controlled message, raw matching input, handler id, or previous Throwable is accepted as custom exception state.

### Application method mismatch

```php
public const string ERROR_CODE = 'CORETSIA_HTTP_405';
public static function methodNotAllowed(array $allowedMethods): self;
public function errorCode(): string;
public function reason(): string;
public function allowedMethods(): array;
```

The exact reason and exception message are `http-app-method-not-allowed`. Custom state contains only the immutable validated list of allowed methods.

The list MUST be a non-empty PHP list of at most nine exact strings from the closed vocabulary `GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `TRACE`, and `CONNECT`. It MUST already be strictly ascending in byte-order `strcmp` order, without duplicates. No sorting, normalization, or repair is permitted. Invalid input fails with `InvalidArgumentException('http-app-allowed-methods-invalid')` and does not retain rejected values or previous exceptions.

### Canonical mapper contribution

`HttpAppProblemMapper` implements the existing `ExceptionMapperInterface` and is registered through `ReservedTags::ERROR_MAPPER` (`error.mapper`), service id `HttpAppProblemMapper::class`, priority `800`, metadata `[]`.

Its exact API is:

```php
public function map(
    Throwable $throwable,
    ?ErrorHandlingContext $context = null,
): ?ErrorDescriptor;
```

It maps only the two HttpApp-owned route exceptions. Other throwables return null from this mapper.

The descriptor uses the exception's fixed error code and reason as `code` and `message`, `ErrorSeverity::Error`, status `404` or `405`, and respectively empty extensions or exactly `['allowed_methods' => $throwable->allowedMethods()]`.

The existing `ErrorDescriptor` field set, extension resource bounds, semantic-key rejection, and safe derivation requirements remain authoritative. Canonical error mapping, redaction, reconstruction, and eligible reporting belong to the existing ErrorHandler.

For each caught downstream Throwable, the outer Problem Details boundary MUST invoke the effective `ErrorHandlerInterface::handle()` exactly once, without retrying normalization, adaptation, or rendering. Only `platform/problem-details` admits the exact `allowed_methods` extension for HTTP `405` and constructs the validated `Allow` header by joining the ordered list with `', '`. The public JSON retains exactly `status`, `title`, and `type`. Missing or invalid required 405 metadata produces the fixed safe emergency 500 response without Allow; arbitrary extension publication or arbitrary header mapping is forbidden. See [ErrorDescriptor SSoT](./error-descriptor.md) and [Problem Details SSoT](./problem-details.md).

### Dispatch failures

`HttpAppDispatchException` is a final `RuntimeException` with a private constructor and a closed fixed-reason admission API:

```php
public static function withReason(string $reason): self;
public function reason(): string;
```

Its message and custom state contain only an admitted reason token:

```text
http-app-handler-unmapped
http-app-handler-unavailable
http-app-handler-invalid
http-app-argument-unresolvable
http-app-argument-invalid
http-app-response-unsupported
http-app-response-disabled
http-app-response-mapping-failed
http-app-route-state-invalid
http-app-route-input-changed
http-app-route-middleware-invalid
http-app-route-middleware-unavailable
```

Unsupported reasons fail with `InvalidArgumentException('http-app-dispatch-reason-invalid')`. Rejected identifiers, argument or return values, reflection diagnostics, raw routing inputs, and previous resolution exceptions MUST NOT enter custom exception state.

HttpApp-owned dispatch failures and HTTP-owned continuation failures are distinct internal server failures, not application 404/405 decisions. The HttpApp mapper MUST NOT consume them. They remain subject to the canonical internal failure mapping through the existing outer error boundary; no duplicate normalization, rendering, redaction, or reporting is permitted.

## Action observability (MUST)

The invocation-local action terminal of `ActionDispatchMiddleware` owns the action observation lifecycle. Observations begin only after successful original-state identity and routing-input validation at terminal entry. Matching success, reserved handoff, routing failures, route-specific admission, middleware construction and execution, and short-circuits or failures before terminal entry do not produce action observations.

An admitted lifecycle covers handler resolution, argument resolution, action invocation, and response mapping. Handler and argument failures after admitted entry remain part of this lifecycle. Guarded continuation enforcement prevents duplicate observations.

The canonical signal identities are:

```text
http.action
http.action_total
http.action_duration_ms
http.action.completed
```

The span starts with only `method` and receives completion attributes `outcome` and `status`. Both metrics use exactly `method`, `status`, `outcome`; the counter increments by `1` and the duration observes integer milliseconds. The completion event is PSR-3 `debug` with the same exact producer fields.

Method values use exact membership in the nine canonical methods or `OTHER` for unavailable or unsupported observations. `returned` denotes dispatch and mapping returning a `ResponseInterface`, independently of its status. `threw` denotes dispatch or mapping failure. Status is the safely observed integer `100..599` for `returned`, or `unknown` when inadmissible or unavailable, and always `unknown` for `threw`.

The three `http_app.tracing.enabled`, `http_app.metrics.enabled`, and `http_app.logging.enabled` flags independently control their respective signals. Stopwatch measurement occurs only for enabled metrics and stops before response status observation and completion emission, only for a valid positive start token. Successful measurements in `0..4294967295` are eligible; unavailable measurements are omitted rather than replaced with synthetic zero.

Each eligible signal is attempted once, independently failure-isolated, using the effective observability bindings, including existing Foundation noop services. Every successfully started span receives exactly one `end()` attempt through `finally`; no `recordException()`, Throwable-derived attributes, or retries are permitted. Observation failures MUST NOT change the returned response, replace the primary Throwable, or suppress another otherwise eligible signal.

The exact signal schemas, bounded values, timing and completion rules are governed by [Observability SSoT](./observability.md#http-action-observability-policy). Producer field admission and sink enrichment remain governed by [Logging Policy SSoT](./logging-policy.md).

Routing matching observations and finalized HTTP request observations are independent. A returned action response can subsequently be decorated or replaced through the outer error and finalization boundaries. Action observations MUST NOT predict the eventual HTTP status or rewrite a completed action outcome because of a later continuation failure. Continuation enforcement has no dedicated spans, metrics, or logs.

## Security and redaction (MUST)

Application middleware is trusted application PHP code. Admission of a middleware service does not establish that its policy or response is authorization-safe.

Protected application-response producers, including response caches, MUST NOT return protected route content before the applicable authorization policy has been enforced. Authorization MUST precede any applicable cache-hit or other short-circuit response that could bypass it. Route-specific authorization cannot protect content returned earlier by global middleware; applications MUST arrange effective middleware order accordingly.

Any middleware that rewrites routing inputs MUST execute before `RouterMiddleware` and before every applicable admission control whose decision depends on the rewritten inputs. Pre-match rewriting does not authorize bypassing maintenance, trusted-host, HTTPS, security, or request-context policies. It MUST NOT invalidate a previously enforced admission decision. No new rewriting middleware or automatic rematching mechanism is provided by HttpApp.

Producer diagnostics MUST be safe by construction. Raw request paths, hosts, queries, headers, cookies, bodies, matched parameter values, route metadata, configured admission lists, handler or service identifiers, reflection signatures, arguments, action return values, credentials, private customer data, and raw Throwable messages MUST NOT be exported. Diagnostic values remain limited to their explicitly admitted bounded schemas, fixed error codes, and safe reason tokens.

`allowed_methods` is bounded owner-defined protocol metadata, not a general header map or diagnostic export channel. Shared redaction remains owned by the canonical error and logging boundaries; HttpApp MUST NOT introduce a separate sensitive-data classifier or generic redactor.

## Context, UnitOfWork, and reset (MUST)

All reusable HttpApp services are stateless or retain immutable configuration and dependencies only. Requests, routing handoffs, reflection objects, resolved actions, arguments, responses, route middleware sequences, local pipelines, terminal closures, continuations, spans, and timer state remain invocation-local. Runtime-only terminal objects MUST NOT enter declarative definitions or generated artifacts.

HttpApp does not write ContextKeys. `ContextKeys::PATH_TEMPLATE` remains exclusively managed by Routing. Request attributes MUST NOT be copied into ContextStore; no ContextStore, ContextAccessor, or ContextWriter storage for requests, actions, arguments, response values, or negotiation is permitted. Canonical Logging enrichment remains Logging-owned.

HttpApp participates in the existing HTTP UnitOfWork and MUST NOT create nested UnitOfWork boundaries, response finalization, reset hooks, or additional lifecycle services. HttpApp contributes no `ResetInterface` implementation or `kernel.reset` tag. Canonical Kernel/Foundation reset remains authoritative and independent of route selection.

Application-owned actions and middleware retain their registered shared/non-shared semantics and existing stateful/reset obligations. Continuation state is invocation-local, released on expiration, and never registered for reset. No route-dependent reset filtering or executed-middleware tracking is permitted.

## Enforcement rails

Verification MUST cover:

- explicit module participation, exact declarative contribution identities, effective role binding validation, mandatory outermost Problem Details placement, and source/compiled definition parity;
- closed configuration shapes, exact identifier admission, duplicate rejection, immutable effective configuration consumption, and canonical graph completeness without eager service resolution;
- artifact-backed matching and ordered metadata hydration, canonical reserved delegation and 404/405 classification, exact method-list admission and safe Allow-header adaptation;
- original handoff identity and routing-input preservation before selection and terminal entry, global and route-specific guarded execution, rejection of replacement or temporary mutation before downstream execution, repeated and expired continuation rejection, and release of invocation-local references;
- explicit route middleware admission and resolved-instance rejection, original ordering, short-circuit responses, unchanged middleware Throwable propagation, response decoration, and no implicit action or fallback execution;
- exact invokable service and argument policies, same-instance HTTP dispatch, non-shared action resolution once per admitted terminal entry, native named invocation, and deterministic response mapping;
- bounded action observations, independent flags and failure isolation, no duplicate observations, canonical request/error observation separation, and repeated-request lifecycle isolation.

End-to-end verification MUST use the actual artifact-backed Routing implementation and canonical HTTP entrypoint, source and compiled runtime graphs, effective application module composition, and isolated temporary consumer configuration. A fake router or standalone middleware test does not replace this evidence.

## Non-goals

- Implicit action discovery, FQCN or method-name fallback, controller construction, model binding, or arbitrary request-data argument injection.
- A second container, router, middleware registry, route artifact schema, artifact reader or compiler, or middleware execution engine.
- Request rewriting implementation, automatic rematching, host-based routing extensions, implicit HEAD/OPTIONS expansion, or ownership of system endpoints.
- New JSON serializers, template rendering, content negotiation, automatic DTO serialization, or duplicate error processing.
- Application transaction management, cross-request idempotency, authorization enforcement inferred from middleware admission, or sandboxing of trusted application code.
- Additional ContextKeys, generic middleware observations, new metric label keys, reset orchestration, or a nested UnitOfWork.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Routing and HttpApp Contracts SSoT](./routing-and-http-app-contracts.md)
- [Routing Artifact SSoT](./routing-artifact.md)
- [Reserved Path Matching SSoT](./reserved-path-matching.md)
- [HTTP Middleware Catalog SSoT](./http-middleware-catalog.md)
- [HTTP Middleware Wiring SSoT](./http-middleware-wiring.md)
- [HTTP System Endpoints SSoT](./http-system-endpoints.md)
- [Middleware Context Keys Map SSoT](./middleware-context-keys-map.md)
- [Config Roots Registry SSoT](./config-roots.md)
- [Config and env SSoT](./config-and-env.md)
- [Tags SSoT](./tags.md)
- [DI Tags and Middleware Ordering SSoT](./di-tags-and-middleware-ordering.md)
- [Runtime Container Definitions SSoT](./runtime-container-definitions.md)
- [Modules and Manifests SSoT](./modules-and-manifests.md)
- [Application Dependency Sync SSoT](./application-dependency-sync.md)
- [Json-like Runtime Values SSoT](./json-like-runtime-values.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Logging Policy SSoT](./logging-policy.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [Errors Boundary SSoT](./errors-boundary.md)
- [ErrorDescriptor SSoT](./error-descriptor.md)
- [Problem Details SSoT](./problem-details.md)
- [Sensitive Data Redaction SSoT](./sensitive-data-redaction.md)
- [ContextStore Lifecycle SSoT](./context-lifecycle.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [Reset Tags SSoT](./reset-tags.md)
- [Architecture Dependencies](../architecture/DEPENDENCIES.md)
- [ADR-0005: Routing and HttpApp ports](../adr/ADR-0005-routing-httpapp-ports.md)
- [ADR-0041: Platform HTTP runtime pipeline wiring](../adr/ADR-0041-platform-http-runtime-pipeline-wiring.md)
- [ADR-0042: System route table and reserved-path matching](../adr/ADR-0042-system-route-table-and-reserved-path-matching.md)
