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

# coretsia/platform-http-app

`platform/http-app` is the deterministic HTTP application dispatch package for the Coretsia Framework.

It integrates the effective Routing port into the canonical HTTP pipeline, admits explicitly registered invokable actions and argument sources, executes ordered route-specific PSR-15 middleware, maps action return values to HTTP responses, and classifies application route misses through the canonical error boundary.

Scope: application route matching integration, immutable request-local routing handoff, explicit handler and middleware admission, guarded application dispatch, named action invocation, deterministic response mapping, canonical 404/405 mapping, and bounded action observability.

Out of scope: route compilation and artifact ownership, HTTP transport and runtime-driver selection, system endpoint execution, global middleware composition ownership, container implementation, error normalization and public error rendering, context storage, UnitOfWork and reset orchestration, and observability backend implementation.

This README is a consumer-oriented package summary. [docs/ssot/http-app-dispatch.md](https://github.com/coretsia/monorepo/blob/main/docs/ssot/http-app-dispatch.md) is authoritative for the exact application dispatch policy.

## Package identity

- Path: `packages/platform/http-app`
- Package id: `platform/http-app`
- Composer name: `coretsia/platform-http-app`
- Module id: `platform.http-app`
- Namespace: `Coretsia\Platform\HttpApp\*` (PSR-4: `src/`)
- Kind: runtime
- Config root: `http_app`

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions MUST NOT be used.

## Dependency policy

- Direct Composer requirements:
  - PHP `^8.4`
  - `coretsia/core-contracts`
  - `coretsia/core-foundation`
  - `coretsia/platform-errors`
  - `coretsia/platform-http`
  - `coretsia/platform-problem-details`
  - `coretsia/platform-routing`
  - `psr/container` `^2.0`, `psr/log` `^3.0`
  - `psr/http-message` `^2.0`, `psr/http-factory` `^1.0`
  - `psr/http-server-handler` `^1.0`, `psr/http-server-middleware` `^1.0`
- Direct PHP source dependencies:
  - `core/contracts`
  - `core/foundation`
  - `platform/http`, restricted to the explicitly public `Pipeline` composition API
- Forbidden production implementation dependencies:
  - `core/kernel`;
  - concrete Routing, Errors, and Problem Details implementation classes;
  - HTTP-internal stack builders, runtime factories, entrypoint guards, fallback handlers, and HttpKernel internals;
  - other `platform/*` packages, `integrations/*`, `enterprise/*`, `devtools/*`, and repository tooling under `tools/**`.

The package's `composer.json` is authoritative for exact internal release constraints. Routing, Errors, and Problem Details are required runtime-only Composer and module dependencies. They supply the effective router and artifact readiness, canonical exception normalization and reporting, and mandatory outer HTTP error boundary, respectively. These edges do not grant direct PHP source access to their implementations. HTTP and Routing remain independent of each other; none of HTTP, Routing, Errors, or Problem Details depends on HttpApp.

Foundation owns declarative service definitions, reserved tags, tagged discovery, deterministic ordering, service resolution, and stable JSON serialization. The public HTTP `Pipeline` owns PSR-15 execution. Registering middleware through Foundation does not by itself require a dependency on `platform/http`. HttpApp consumes PSR interfaces and the effective HTTP-owned PSR-17 factory bindings; it does not require a concrete PSR-7/17 implementation or introduce transport execution types into Core contracts or Foundation.

## Runtime responsibilities

`platform/http-app` provides:

- `Module\HttpAppModule` — runtime module identity and provider declaration;
- `Provider\HttpAppServiceProvider` — canonical declarative wiring for source and compiled definitions;
- `Middleware\RouterMiddleware` — application route matching, reserved-path delegation, and deterministic 404/405 classification;
- `Routing\RouteDispatchState` — immutable request-local handoff between matching and dispatch;
- `Middleware\RouteMiddlewareSelector` — ordered selection from the matched route's metadata, subject to explicit configuration admission;
- `Middleware\ActionDispatchMiddleware` — final effective global route contribution, invocation-local route middleware composition, and action terminal execution;
- `Action\HandlerResolver`, `Action\ArgumentResolver`, and `Action\ActionInvoker` — explicit action admission, named argument resolution, and invocation;
- `Response\ActionResponseMapper` — deterministic HTTP adaptation of admitted action return values;
- `Error\HttpAppProblemMapper` — format-neutral descriptors for the two application routing exceptions;
- independent tracing, metrics, and safe action completion logging at the admitted action terminal.

The package does not provide a second router, container, middleware registry, serializer, error handler, or runtime lifecycle.

## Module activation

Composer installation alone does not activate application dispatch. The target-local effective `ModulePlan` MUST explicitly select `platform.http-app`. The module requires exactly:

```text
core.foundation
platform.errors
platform.http
platform.problem-details
platform.routing
```

Each required module has a matching direct production Composer requirement. The canonical module planner must satisfy the full dependency graph, including transitive Kernel and Redaction requirements. Select the application-owned modules that contribute routes, handlers, and middleware in the intended `web` or `api` target. An unselected HttpApp module contributes neither its canonical middleware pair nor its mapper; package installation does not implicitly enable it.

The selected HTTP target MUST have its intended runtime driver, compatible effective `ResponseFactoryInterface` and `StreamFactoryInterface` bindings, and a ready boot-selected Routing artifact (`routes@1`) coordinated with its Kernel generation. Routing owns compilation and artifact admission; HTTP owns entrypoint readiness and the terminal request handler. Select CLI and Routing in the command-owning Console target when compiling routes through the canonical Routing commands. The Console plan does not select the HTTP target's application services.

Canonical Problem Details participation is mandatory: `HttpErrorHandlingMiddleware` MUST be registered under its canonical service id with `ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE` (`http.middleware.system_pre`), priority `1000`, metadata `[]`, and its exact runtime class MUST occupy the actual outermost effective middleware position. The HTTP composition boundary rejects missing, substituted, or incorrectly placed required roles before request execution.

## Public contracts

The format-neutral ports remain owned by `core/contracts` under `Coretsia\Contracts\HttpApp`:

- `ArgumentResolverInterface::resolve(string $actionId, RouteMatch $match, array $context = []): array`;
- `ActionInvokerInterface::invoke(string $actionId, array $arguments = []): mixed`.

The provider binds these ports to `ArgumentResolver` and `ActionInvoker` through canonical aliases. Standalone argument resolution requires the action id to equal `RouteMatch::handler()` and does not consume PSR-7 requests from the optional context map. Standalone invocation uses explicitly supplied named arguments, without automatically resolving additional values or mapping the return value to HTTP.

`RouteDefinition`, `RouteMatch`, and `RouterInterface` remain Contracts-owned. Application middleware after matching can read `Coretsia\Platform\HttpApp\Routing\RouteDispatchState` through `fromRequest()` and `match()`. The framework owns production of this handoff. Package-internal resolvers, provider wiring, and HTTP adaptation methods are not additional application extension points.

## Configuration

The package owns the reserved `http_app` config root. The canonical `config/http_app.php` defaults file returns the root subtree only:

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

The declarative `config/rules.php` ruleset uses `schemaVersion: 1`, requires every canonical key, rejects additional keys in closed maps, and requires exact boolean flags. The three admission collections are lists. Structural validation belongs to the canonical configuration pipeline; semantic validation belongs to the provider and corresponding resolvers.

The provider consumes the effective target-local `http_app` subtree through `ContainerDefinitionContext::configRoot()`. The existing read-only `ConfigRepositoryInterface` is supplied to the three resolvers by canonical service references. They read their respective lists once at construction and retain immutable validated lookups or allowlists. Source and compiled definitions share the same configuration semantics; no dynamic lookup map is copied into declarative constructor maps or container parameters.

## Handler registration and deferred service completeness

`http_app.handlers` is a list of closed records containing exactly `action_id` and `service_id`, both non-empty strings. For example, a target-local configuration fragment for a synthetic application:

```php
'handlers' => [
    [
        'action_id' => 'catalog.show:detail',
        'service_id' => 'catalog.show.action',
    ],
],
```

The route's logical handler (`RouteMatch::handler()`) must equal the configured `action_id` exactly. The identifier grammar is `\A[A-Za-z_][A-Za-z0-9_.:-]*\z`, with a 256-byte limit. Duplicate action ids are rejected, even when their service ids agree. Distinct action ids may refer to the same service id. Service ids must satisfy Foundation's canonical declarative service-id policy and contain no ASCII control bytes.

Logical action identifiers remain configuration values, not structural configuration keys. `HandlerResolver` builds one private immutable action-id-to-service-id lookup. It contains identifiers only, not service instances or callables, and is not a separate service registry. The canonical config-path, declarative map-key, and typed-reference grammars remain unchanged.

Register application services explicitly in the owning module's canonical declarative definition provider. For example, within `define()`, assuming `ShowItemAction` is the application's autoloadable invokable class with no constructor dependencies:

```php
$definitions->classService('catalog.show.action', ShowItemAction::class);
```

`HttpAppServiceProvider::define()` validates handler records, concrete argument allowlists, and route middleware admission lists before declaring every configured service dependency through `ContainerDefinitionBuilder::requireService()`. Configured but missing bindings fail canonical Kernel production graph completeness validation, even for services not selected by a current route. Source definition application is not a substitute for final graph validation.

Required-service declarations do not instantiate application services. Actual handler and admitted argument service resolution remains lazy; route-specific middleware is resolved only for the selected chain by the dispatcher. Foundation Container owns construction, factory invocation, aliases, sharing, and lifecycle. Runtime admission checks `Container::serviceIds()` before `get()`; `has()` alone is insufficient because it can admit implicit concrete-class autowiring. Unmapped logical ids never fall back to class names or container ids. A graph-valid binding whose construction or instance admission fails is a dispatch-time failure, not a route miss.

## Application matching and terminal dispatch

The provider registers exactly two canonical middleware contributions through Foundation `ReservedTags` and the existing definition builder:

- `RouterMiddleware::class` — `ReservedTags::HTTP_MIDDLEWARE_APP` (`http.middleware.app`), priority `100`, metadata `[]`;
- `ActionDispatchMiddleware::class` — `ReservedTags::HTTP_MIDDLEWARE_ROUTE` (`http.middleware.route`), priority `-1000`, metadata `[]`.

HTTP validates the pair, effective module participation, exact slot, priority, metadata, and runtime class identities. Applications MUST NOT manually register substitutes for these framework roles.

`RouterMiddleware` calls the effective `RouterInterface::match()` exactly once with the exact method, encoded URI path, and URI host (empty host becomes null). A successful match attaches the immutable handoff and delegates instead of resolving or invoking the action. It does not select route middleware or map responses. Reserved paths receive an explicit reserved handoff and continue downstream.

The application dispatch portion of the effective HTTP composition is:

```text
canonical outer Problem Details boundary
  -> existing global middleware
  -> RouterMiddleware (app)
  -> remaining app middleware
  -> route_pre middleware
  -> ordinary route middleware
  -> ActionDispatchMiddleware (final effective route contribution)
       -> reserved handoff: existing HTTP-owned terminal
       -> matched handoff: ordered route-specific Pipeline
            -> action terminal
```

Ordinary global middleware before matching is preserved as well. The existing HTTP composition rules govern all slots, tagged order, manual entries, and response unwind. `ActionDispatchMiddleware` is placed after every ordinary effective route entry, including manual entries and tagged entries with lower numerical priorities. Final placement is not derived merely from priority `-1000`.

The dispatcher validates the handoff before selection. For a matched route it admits the complete route-specific sequence and constructs one invocation-local public HTTP `Pipeline` with an invocation-local action terminal. For a reserved handoff it delegates to the existing HTTP-owned terminal without selecting middleware or resolving an action. A matched route MUST NOT fall back to the system terminal, and invalid handoff state MUST NOT cause automatic rematching.

## Route-specific middleware

`http_app.route_middleware` admits explicitly registered middleware service ids. It is a list of unique non-empty canonical service-id strings, not a global middleware ordering configuration. For example, assuming the application has registered both services as PSR-15 middleware:

```php
'route_middleware' => [
    'catalog.route.decorate',
    'catalog.route.authorize',
],
```

The route-specific selection is declared through existing `RouteDefinition` metadata, preserved in the Routing artifact, and exposed by `RouteMatch`. For example:

```php
new RouteDefinition(
    name: 'catalog.show',
    methods: ['GET'],
    pathTemplate: '/catalog/{item}',
    handler: 'catalog.show:detail',
    metadata: [
        'http_app_middleware' => ['catalog.route.authorize', 'catalog.route.decorate'],
    ],
);
```

The exact metadata member is `http_app_middleware`. Its ordered list determines execution order; the admission-list order is irrelevant. An absent member or explicit empty list selects no middleware. Present null, malformed lists, duplicates, unadmitted ids, and forbidden roles fail closed. Metadata contains static identifiers, not instances, executable values, or raw request data. Existing `RouteGroup` metadata inheritance and duplicate-key rejection remain authoritative.

`RouteMiddlewareSelector` selects ids only. The dispatcher verifies explicit registration of every selected id and delegates ordered resolution and `MiddlewareInterface` admission to the public `Coretsia\Platform\Http\Middleware\Pipeline::resolveServices()` API. Each id is resolved once per chain construction. Canonical matching, dispatch, and outer Problem Details roles are forbidden by both service id and exact resolved runtime class, including aliases.

The entire selected chain must be admitted before any of its middleware executes. There is no sorting, silent skipping, implicit deduplication, or second middleware engine. Explicitly selecting a service also present in the global chain represents two execution scopes. Ordinary global middleware registration continues to use Foundation definitions and reserved tags; route-specific selection does not require a global middleware tag.

Selected middleware may short-circuit with its own `ResponseInterface` without delegating; the dispatcher preserves that response and does not resolve or invoke the action. Delegating middleware may decorate the response during unwind. Admission or construction failures prevent action execution; execution `Throwable` after successful admission propagates unchanged to the canonical outer error boundary. An empty selected sequence uses the same action terminal.

## Request-local handoff and guarded continuation

`RouteDispatchState::ATTRIBUTE` is exactly `coretsia.http_app.route_dispatch`. The immutable state contains the original method, encoded path, host (empty string normalized to null), and nullable `RouteMatch`, not the request object itself. Only an explicit reserved handoff has a null match. The matching middleware rejects any pre-existing key, including an explicit null value.

Downstream middleware may read the handoff using `RouteDispatchState::fromRequest($request)->match()`. The framework-owned attribute MUST NOT be removed, replaced, or overwritten, even with an equivalent state instance. The dispatcher calls `assertRequest()` before selection, and the action terminal verifies the original captured state identity and routing inputs again before action admission.

Both the effective global HTTP Pipeline when HttpApp participates and the invocation-local route-specific Pipeline MUST use the HTTP-owned guarded continuation policy:

```php
guardedContinuation: true,
protectedRequestAttributes: [RouteDispatchState::ATTRIBUTE],
protectRoutingInputs: true,
```

Global guarded execution follows effective module participation, not a separate feature flag. Without HttpApp the global Pipeline uses unguarded mode without protected attributes or routing-input protection.

- A guarded middleware may short-circuit, or delegate at most once while its `process()` invocation is active. Entry is consumed before validation and downstream execution, even if either throws.
- The continuation expires through `finally` when `process()` returns or throws, releases the downstream handler and captured values, and cannot be cloned. Middleware MUST NOT retain downstream continuation handlers beyond `process()`.
- Once the routing attribute is present, every subsequent global and route-specific delegation MUST preserve its exact key and original identity, and the exact raw method, encoded URI path, and URI host. The Pipeline compares those routing inputs without normalization before entering the next middleware or terminal.
- A changed routing input MUST NOT be delegated with the intention of restoring it later. The first invalid delegation is rejected; terminal validation is additional protection, not a substitute for per-continuation checks. Legitimate unrelated request attribute or parsed-body changes remain supported.
- Repeated or expired entry fails before further downstream execution. HTTP owns the continuation guard and fixed `LogicException` reasons. No second invocation guard or continuation registry is introduced.

This guarantees at-most-once framework-owned action terminal entry per canonical matched HTTP invocation. It does not guarantee transaction rollback, cross-request idempotency, retry safety, or protection against deliberately hostile PHP code. A violation after completed action execution does not undo side effects or revise completed action observations.

## Action invocation and argument resolution

An action is an explicitly registered application-owned object exposing an actual public non-static `__invoke()` method. Inherited public invocation methods are admitted. Closures, function-name or arbitrary method-string handlers, magic `__call()` fallback, by-reference parameters, variadic parameters, union types, and intersection types are not supported.

The HTTP dispatch boundary resolves the action once and uses the same instance for argument resolution and invocation. Supported argument sources, in precedence order, are:

1. The exact `ServerRequestInterface` named type when `http_app.arguments.allow_request` is enabled and an effective HTTP request is supplied.
2. A matched route parameter by exact declared parameter name when `http_app.arguments.allow_route_params` is enabled. Only untyped, `mixed`, and `string` parameters are admitted; the encoded string is preserved without decoding or scalar coercion.
3. An exact named concrete class type explicitly listed in `http_app.arguments.allow_container_types` and registered under that exact type id. The resolved value must satisfy the declared type. Interfaces, abstract classes, enums, traits, and unsupported relative type names are not eligible for container argument injection.
4. The declared PHP parameter default.
5. Deterministic argument resolution failure.

The allowlist is a unique list of canonical class-name strings. A selected but incompatible route parameter or unavailable or incompatible selected service fails instead of falling through. Nullable declarations alone do not supply null. Query values, parsed body, headers, cookies, route metadata, and arbitrary context entries are not automatic argument sources. No recursive constructor autowire policy or model binding is introduced.

The invoker validates named argument keys against the admitted signature, rejects unknown names and missing required arguments, and uses strict direct named unpacking (`$action(...$arguments)`). PHP defaults and native type enforcement remain authoritative. Action-thrown `Throwable` and native invocation failures propagate unchanged. Transport adaptation belongs to the separate response mapper.

## Response mapping

`ActionResponseMapper` consumes the effective HTTP-owned PSR-17 response and stream factories. Supported action return values are:

- `ResponseInterface`: returned unchanged by the mapper, independently of representation flags; ordinary middleware and HTTP finalization still apply.
- `string`: status `200`, `Content-Type: text/html; charset=utf-8`, exact application-owned string body, when `http_app.response.html.enabled` is true.
- `null`, `bool`, `int`, and valid JSON-like arrays: status `200`, `Content-Type: application/json`, serialized exclusively through Foundation `StableJsonEncoder::encodeStable()`, when `http_app.response.json.enabled` is true. The encoder owns list/map admission and deterministic bytes, including the final LF; an empty array is `[]`.

Both flags default to true. Floats, arbitrary objects, resources, closures, invalid nested JSON-like values, and disabled representations fail closed. No partially constructed response, implicit representation fallback, content negotiation, DTO serialization, `JsonSerializable` or `__toString()` adaptation, or HTML templating is provided. Escaping untrusted HTML remains action-owner responsibility.

## Context and UnitOfWork

Reusable HttpApp services are stateless or retain immutable configuration and dependencies only. Requests, routing handoffs, reflection objects, resolved actions and route middleware sequences, arguments, local pipelines, terminal handlers, continuations, responses, spans, and timer state remain invocation-local. Runtime-only terminal objects do not enter declarative definitions or artifacts.

HttpApp writes no ContextKeys. Routing owns `ContextKeys::PATH_TEMPLATE`; no request, action, argument, or response state is stored in ContextStore or process-global storage by HttpApp.

The existing HTTP UnitOfWork, response finalization, and canonical Kernel/Foundation reset lifecycle remain authoritative. HttpApp contributes no nested UnitOfWork, `ResetInterface` implementation, `kernel.reset` tag, or independent reset pass. Foundation owns shared/non-shared service semantics and existing application reset obligations.

Dispatcher-owned lazy resolution applies only to selected route-specific middleware. Independent lifecycle consumers, including `ResetOrchestrator`, retain their canonical resolution semantics; shared reset-tagged middleware may be resolved even when not selected for the current route. Reset discovery MUST NOT be filtered by the matched route or executed middleware. No executed-instance registry is maintained.

## Observability

The admitted action terminal owns action observations, distinct from Routing match observations and finalized HTTP request observations. Observations begin only after original handoff identity and routing-input validation succeeds, and cover handler resolution, argument resolution, invocation, and response mapping. Handler or argument failure after admission is part of the action lifecycle. A route match alone, reserved delegation, route-specific admission or construction, middleware execution, denial, or failure before terminal entry produces no action observation.

Canonical signal identities:

```text
http.action
http.action_total
http.action_duration_ms
http.action.completed
```

- Span `http.action`: initial attribute `method`; completion attributes `outcome` and `status`.
- Counter `http.action_total`: increment by `1`, labels `method`, `status`, `outcome`.
- Duration `http.action_duration_ms`: integer milliseconds from the Foundation Stopwatch, with the same labels.
- PSR-3 `debug` event `http.action.completed`: exact producer context fields `method`, `status`, `outcome`.

`method` is one of the nine declared HTTP methods (`GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `TRACE`, `CONNECT`) or `OTHER` for unsupported or unavailable observation. `outcome` is `returned` or `threw`; a returned response remains `returned` regardless of its status class. `status` is an admitted integer `100..599` for a returned response, or `unknown` when unavailable, invalid, or the outcome is `threw`.

The `http_app.tracing.enabled`, `http_app.metrics.enabled`, and `http_app.logging.enabled` flags independently control their signals and default to true. Metrics timing uses a valid positive start token, stops before status inspection and signal emission, and admits durations in `0..4294967295` milliseconds. Unavailable duration is omitted, not fabricated.

The effective tracer, meter, logger, and stopwatch are injected, including canonical noop bindings where applicable. Each eligible signal is attempted once and failure-isolated. A successfully started span receives exactly one `end()` attempt through `finally`. No `recordException()`, raw Throwable attributes, or retries are permitted. Signal failures MUST NOT replace the response or primary Throwable or suppress another eligible signal. Later middleware or finalization may change the HTTP response; completed action observations are not rewritten. Continuation enforcement introduces no separate signal family.

Exact observation schemas and log producer fields remain governed by the canonical Observability and Logging Policy SSoTs.

## Errors

For an ordinary application route miss, `RouterMiddleware` uses the existing `RouterInterface::allowedMethods()` boundary when the unmatched method is a syntactically valid HTTP token. Empty or malformed methods produce not found without enumeration. An empty allowed-method list produces 404; a non-empty admitted list produces 405. Valid extension methods are eligible for mismatch classification. Matching and enumeration remain exact and case-sensitive, with no implicit GET-to-HEAD expansion, automatic OPTIONS, or second observed match.

Reserved paths are classified through Foundation `ReservedPathMatcher` and delegated to HTTP-owned system endpoint behavior, not treated as ordinary application misses. Canonical invalid-path rejection produces not found; unexpected infrastructure failures propagate unchanged. System endpoint availability and response construction remain HTTP-owned.

Application routing exceptions are defined under `Coretsia\Platform\HttpApp\Exception`:

- `HttpNotFoundException`: code `CORETSIA_HTTP_404`, fixed reason `http-app-not-found`;
- `HttpMethodNotAllowedException`: code `CORETSIA_HTTP_405`, fixed reason `http-app-method-not-allowed`, with an immutable validated `allowedMethods()` list.

The allowed-method list is a non-empty list of at most nine exact declared method strings, strictly ascending by byte-order `strcmp` and without duplicates. Invalid lists are rejected, not repaired.

`HttpAppProblemMapper` contributes exactly one `ReservedTags::ERROR_MAPPER` (`error.mapper`) tag, priority `800`, metadata `[]`. It maps only the two routing exceptions to canonical `ErrorDescriptor` values with `ErrorSeverity::Error`, fixed code and reason, HTTP status `404` or `405`, and respectively empty extensions or the single validated `allowed_methods` extension. It returns null for other throwables.

The mandatory outer Problem Details middleware invokes the canonical ErrorHandler once for a caught downstream failure. Errors owns normalization, shared redaction, and eligible reporting. Problem Details independently validates required 405 metadata and owns construction of the HTTP `Allow` header by joining the admitted methods with `, ` (for example, `GET, POST`). The public JSON contains only `status`, `title`, and `type`, not `allowed_methods`. Invalid required 405 metadata produces the canonical emergency 500 without Allow; arbitrary descriptor extensions are not public header values. HttpApp does not render errors or normalize/report them a second time.

`HttpAppDispatchException` represents fixed safe handler, argument, response-mapping, route-state, routing-input, and route-middleware failures. Its custom state and message contain only a closed admitted reason token. Missing configured bindings remain canonical graph failures; unmapped actions and unavailable or incompatible instances remain dispatch failures.

HTTP-owned continuation violations are `LogicException` failures with fixed messages:

```text
http-pipeline-continuation-reused
http-pipeline-continuation-expired
http-pipeline-protected-attribute-changed
http-pipeline-protected-routing-input-changed
```

Dispatch and continuation failures remain canonical internal server failures, not 404/405. The outer boundary receives them without a second continuation mapper or a duplicate error processor. Ordinary middleware denial responses are not automatically converted into Problem Details.

## Security / Redaction

Application middleware is trusted application code. Middleware admission does not establish authorization safety. Protected response caches and other short-circuit response producers MUST NOT return protected content before applicable authorization has admitted the request. Route-specific authorization cannot protect responses returned earlier by global middleware.

Method or URI rewriting, when required by the application, MUST occur before matching and before every applicable admission control whose decision depends on the rewritten inputs. Pre-match placement does not authorize bypassing maintenance, trusted-host, HTTPS, security, or request-context policies. Rewriting MUST NOT invalidate an earlier admission decision. HttpApp supplies neither rewriting middleware nor automatic rematching.

Diagnostics MUST be safe by construction. The following values MUST NOT be exported through HttpApp logs, spans, metrics, or custom exception state:

- raw request paths, hosts, query strings, headers, cookies, and bodies;
- matched route parameters, route metadata, handler and service ids, and configured admission lists;
- reflection signatures, argument values, action return values, and response bodies;
- credentials, secrets, private customer data, raw Throwable messages, and stack traces.

Only admitted bounded observation fields, fixed error codes, and safe reason tokens are used for diagnostics. `allowed_methods` is bounded owner-defined protocol metadata, not a generic header map or diagnostic channel. Canonical Errors and Logging boundaries own shared redaction; HttpApp does not implement a second redactor or sensitive-data classifier. Application code remains responsible for safe output and authorization.

## Non-goals

This package does not provide:

- source route discovery, compilation, artifact publication, or host-based routing;
- transport adapters, front controllers, concrete PSR-7/17 factories, or system endpoint implementations;
- implicit handler discovery, controller fallback, automatic model binding, or arbitrary action argument injection;
- a second DI container, middleware registry, execution engine, invocation guard, or serializer;
- public error rendering, alternative normalization, or independent redaction;
- independent context storage, response finalization, UnitOfWork, or reset orchestration;
- application transaction rollback, idempotency, retry guarantees, or a security sandbox.

## References

- [HTTP Application Dispatch SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/http-app-dispatch.md)
- [ADR-0043: Platform HTTP App router middleware and action invoker](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0043-platform-http-app-router-middleware-action-invoker.md)
- [Dependency Policy](https://github.com/coretsia/monorepo/blob/main/docs/architecture/DEPENDENCIES.md)
- [Modules and Manifests SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/modules-and-manifests.md)
- [Config Roots Registry](https://github.com/coretsia/monorepo/blob/main/docs/ssot/config-roots.md)
- [Runtime Container Definitions SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/runtime-container-definitions.md)
- [Routing and HTTP App Contracts SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/routing-and-http-app-contracts.md)
- [Routing Artifact SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/routing-artifact.md)
- [HTTP Middleware Wiring SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/http-middleware-wiring.md)
- [HTTP Middleware Catalog SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/http-middleware-catalog.md)
- [HTTP System Endpoints SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/http-system-endpoints.md)
- [Reserved Path Matching SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/reserved-path-matching.md)
- [DI Tags and Middleware Ordering SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/di-tags-and-middleware-ordering.md)
- [Json-like Runtime Values SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/json-like-runtime-values.md)
- [ErrorDescriptor SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/error-descriptor.md)
- [Problem Details SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/problem-details.md)
- [Observability SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability.md#http-action-observability-policy)
- [Logging Policy SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/logging-policy.md)
- [UnitOfWork and Reset Contracts SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/uow-and-reset-contracts.md)
- [Reset Tags SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/reset-tags.md)
