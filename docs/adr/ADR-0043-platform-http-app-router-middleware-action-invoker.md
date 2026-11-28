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

# ADR-0043: Platform HTTP App router middleware and action invoker

```yaml
adrVersion: 1
status: pre-accepted
owner: platform/http-app
```

## Context

Coretsia needs one explicit application dispatch boundary inside the canonical HTTP runtime. A successful route match identifies an application action but does not authorize immediate execution: the remaining global middleware and the route-specific middleware selected by the matched route must retain their respective admission and execution responsibilities.

Routing owns the compiled route table and format-neutral matching. Foundation owns declarative container definitions, tagged-service registration and resolution. HTTP owns PSR-15 composition, the effective PSR-17 factories, system endpoint fallback, and the request lifecycle. Errors and Problem Details own normalization and public HTTP error presentation.

Application dispatch must reuse these boundaries while admitting only explicitly registered actions and argument sources. It must preserve the selected route and the routing inputs across subsequent middleware delegations, prevent repeated action terminal entry, and keep request-local state out of reusable services.

## Decision

### Decision 1: Give application dispatch one runtime owner and preserve format-neutral contracts

`platform/http-app` owns application matching integration, request-local routing handoff, route-specific middleware selection, action resolution, argument resolution, invocation, action response mapping, application routing exception classification, and action observations.

The existing `RouterInterface`, `RouteDefinition`, `RouteMatch`, `ArgumentResolverInterface`, and `ActionInvokerInterface` remain the format-neutral contracts. HttpApp implements the existing action and argument ports without adding PSR-7 requests, PSR-7 responses, PSR-15 middleware, or HTTP runtime classes to their signatures. HTTP-specific request injection and response adaptation belong to HttpApp, not `core/contracts` or `core/foundation`.

The `platform.http-app` runtime module participates only when explicitly selected in the effective target module plan. Composer installation alone does not activate dispatch. Its required module identities are:

```text
core.foundation
platform.errors
platform.http
platform.problem-details
platform.routing
```

Each module requirement has a matching direct production Composer requirement. Direct Coretsia PHP source dependencies are limited to `core/contracts`, `core/foundation`, and `platform/http`. The HTTP source dependency is justified by consumption of its explicitly public `Coretsia\Platform\Http\Middleware\Pipeline` composition API, including `resolveServices()`. HttpApp does not consume HTTP-internal stack builders, factories, entrypoint guards, or HttpKernel internals.

Routing, Errors, and Problem Details are required runtime-only Composer and module dependencies. These edges provide the effective router and artifact readiness, canonical error handling, and outer HTTP error boundary without allowing direct PHP source dependencies on their implementations. HttpApp does not depend directly on Kernel. HTTP, Routing, Errors, and Problem Details do not acquire reverse dependencies on HttpApp; HTTP and Routing remain independent of each other.

### Decision 2: Use closed configuration records and the effective configuration snapshot

The package-owned `http_app` root admits handlers through `http_app.handlers`, a list of closed records with exactly two required non-empty strings:

```php
'handlers' => [
    [
        'action_id' => 'hello.show:detail',
        'service_id' => 'hello.action',
    ],
],
```

The logical action id follows the Routing identifier grammar `\A[A-Za-z_][A-Za-z0-9_.:-]*\z` and the 256-byte limit. Comparisons are exact; duplicate action ids are rejected, even when their service ids agree. Different action ids may explicitly refer to the same service id. The service id follows Foundation's canonical declarative service-id admission through `ContainerValueReference::service()` with additional rejection of ASCII control bytes.

Logical identifiers are configuration values, not dynamic structural map keys. `HandlerResolver` retains an immutable internal action-id-to-service-id lookup, not a registry of services or callables. Core configuration source-path, declarative map-key, and typed-reference grammars remain authoritative. Escaping or encoding identifiers into structural keys, or widening these grammars solely for application dispatch, is not permitted.

`http_app.arguments.allow_container_types` admits an explicit list of unique canonical class-name strings. `http_app.route_middleware` admits an explicit list of unique canonical middleware service ids. The three lists default to `[]`. The remaining keys and exact defaults are owned by `docs/ssot/http-app-dispatch.md`; feature flags are exact booleans.

`HttpAppServiceProvider::define()` is the single declarative wiring source. It reads the effective `http_app` subtree through `ContainerDefinitionContext::configRoot()` and performs semantic validation through the same validators used by `HandlerResolver`, `ArgumentResolver`, and `RouteMiddlewareSelector`. Structural validation remains the responsibility of the canonical declarative ruleset. Configured values are not resolved as services at definition time.

The provider supplies the existing effective read-only `ConfigRepositoryInterface` by canonical service reference to the three resolvers. Each consumes its required list once during construction and retains the validated immutable lookup or allowlist. The lists and dynamic lookup maps are not copied into declarative constructor argument maps or container parameters. Validated flags are passed explicitly as constructor values. Source and compiled definitions therefore consume the same authoritative target-local configuration semantics without a second configuration loader or mutable registry.

### Decision 3: Declare deferred service dependencies and reuse Foundation Container

Every configured handler service id, allowlisted container argument type, and admitted route middleware service id is declared through `ContainerDefinitionBuilder::requireService()`. Kernel owns final production graph completeness validation for these dependencies. The source `ContainerDefinitionApplier` does not replace that validation, and HttpApp does not add eager container lookup or an independent graph validator.

A missing configured binding fails canonical graph completeness even when its service is not selected by the current route. An unmapped logical action id, a registered service whose construction fails, or an incompatible resolved instance fails at dispatch time. Deferred resolution is not an exemption from graph completeness.

Foundation Container remains the sole owner of explicit registration, factory invocation, aliases, shared/non-shared service lifecycle, and resolution. HttpApp checks exact membership in `Container::serviceIds()` before resolving explicitly admitted application services. `Container::has()` alone is insufficient because it can admit concrete-class autowiring. HttpApp neither constructs controllers directly nor implements an additional autowire mechanism, service locator, or resolved-instance cache.

### Decision 4: Separate application matching from final action dispatch

HttpApp contributes two independent middleware definitions through the existing Foundation declarative registration API and `ReservedTags`:

- `RouterMiddleware` — `ReservedTags::HTTP_MIDDLEWARE_APP` (`http.middleware.app`), priority `100`, metadata `[]`;
- `ActionDispatchMiddleware` — `ReservedTags::HTTP_MIDDLEWARE_ROUTE` (`http.middleware.route`), priority `-1000`, metadata `[]`.

`RouterMiddleware` calls the effective `RouterInterface::match()` once with the exact method, encoded URI path, and URI host, converting only the empty host to `null`. It neither resolves actions nor selects route middleware. On a successful match it attaches an immutable request-local `RouteDispatchState` and delegates once, returning the downstream response unchanged.

The state is carried under `RouteDispatchState::ATTRIBUTE`, exactly `coretsia.http_app.route_dispatch`. It holds the original routing inputs and a nullable `RouteMatch`. A non-null match identifies an application route; null denotes explicit reserved-path delegation only. The matching middleware rejects any pre-existing attribute, including an explicitly present null. The state retains no request object and exposes no diagnostic export of raw inputs.

The HTTP composition boundary validates the canonical contribution pair, slot, priority, empty metadata, effective module participation, and exact resolved runtime class identities. The matched request continues through remaining app, route_pre, and ordinary route middleware. HTTP places `ActionDispatchMiddleware` last in the effective route slot, after all ordinary tagged and manual entries, regardless of their numerical priorities. The global composition and its ordinary middleware are preserved; the HTTP-owned terminal handler remains unchanged.

`ActionDispatchMiddleware` obtains the handoff through `RouteDispatchState::fromRequest()` and validates the routing inputs through `assertRequest()` before selection. Missing, malformed, or inconsistent state is a dispatch failure, not a request to match again. Routing remains the owner of `routes@1` compilation, publication, hydration, and matching; HttpApp consumes the effective artifact-backed router without creating another router or artifact reader.

### Decision 5: Select route-specific middleware through existing metadata and compose it locally

`RouteDefinition::metadata()` and `RouteMatch::metadata()` are the canonical selection surfaces. The member `http_app_middleware` contains an ordered list of service ids explicitly admitted in `http_app.route_middleware`. An absent member or an explicit empty list selects no middleware; an explicitly present null, malformed list, duplicate, forbidden id, or unadmitted id fails closed. Admission-list order does not define execution order.

Routing preserves the existing `RouteGroup` inheritance and duplicate-key rejection policy and the semantic list order through `routes@1` serialization and hydration. It does not interpret metadata as executable instructions. Metadata contains identifiers, not middleware instances, executable values, or raw request data.

`RouteMiddlewareSelector` performs selection only. The dispatcher verifies explicit registration for all selected ids and delegates ordered service resolution and `MiddlewareInterface` admission to the public `Pipeline::resolveServices()` API. Each selected id is resolved once per chain construction. The dispatcher rejects the canonical RouterMiddleware, ActionDispatchMiddleware, and HttpErrorHandlingMiddleware roles both by selection id and by exact resolved runtime class, including alias-based access. The selector owns the forbidden-role identity list; no second policy registry or concrete Problem Details import is necessary.

After complete chain admission, `ActionDispatchMiddleware` constructs an invocation-local instance of the HTTP-owned `Pipeline` using the exact declared order and an invocation-local `RequestHandlerInterface` action terminal. No sorting, implicit deduplication, silent skipping, or alternative PSR-15 execution engine is permitted. Explicit selection of a service also present in global composition represents a separate execution scope.

The terminal captures the original state and match, verifies exact state identity and unchanged routing inputs, and then dispatches the action with the effective request. The empty selection follows the same terminal behavior. A matched application route does not invoke the HTTP-owned system fallback.

Middleware may deny or otherwise short-circuit by returning its own response without delegation. Such a response passes through the dispatcher unchanged; no action resolution or observation occurs on that path. Selection and service admission failures prevent execution, and middleware-thrown `Throwable` after admission propagates unchanged to the outer boundary. Middleware may decorate the response on unwind. These middleware operations are outside the action observation boundary.

### Decision 6: Require guarded HTTP continuations and preserve original routing identity

Both the global HTTP Pipeline when HttpApp participates and the invocation-local route-specific Pipeline must use the existing HTTP-owned guarded continuation policy:

```php
guardedContinuation: true,
protectedRequestAttributes: [RouteDispatchState::ATTRIBUTE],
protectRoutingInputs: true,
```

The global composition enables this policy according to the effective module plan, not an additional feature flag. Without HttpApp participation the global Pipeline uses unguarded mode with no protected attributes or routing-input protection.

Each middleware invocation may short-circuit or enter its continuation at most once while `process()` is active. Entry is consumed before validation and downstream execution, including when either throws. The continuation expires in `finally` when `process()` returns or throws, releases its downstream handler and captured values, and cannot be cloned. Repeated or expired entry fails before any further downstream execution. This establishes at-most-once action terminal entry without a second action invocation guard.

The guarded Pipeline captures protected attribute values already present at middleware entry. The canonical matching boundary may introduce the previously absent handoff; after introduction, every subsequent delegation must preserve the exact key and original object identity, not merely an equivalent value. With routing-input protection enabled, the exact method, encoded URI path, and URI host returned by the request are captured and compared at every guarded continuation where the protected attribute is present. This comparison uses the raw tuple; `RouteDispatchState` separately applies its empty-host-to-null convention.

Replacement, removal, or routing-input mutation fails before the next middleware or terminal executes. Mutation followed by restoration in a later middleware is not permitted. Terminal identity and input validation remain mandatory final dispatch-boundary checks, not substitutes for continuation protection.

HTTP owns the fixed continuation failure reasons and their `LogicException` behavior. HttpApp owns invalid handoff and changed-input dispatch exceptions. Neither failure family is a routing miss or method mismatch; both remain canonical internal server failures when propagated to the outer boundary. No recursive dispatch, automatic rematching, process-global routing storage, second ownership token, or continuation registry is permitted.

Guarded execution does not roll back application side effects already completed, guarantee cross-request idempotency or retry safety, or sandbox deliberately hostile PHP code.

### Decision 7: Resolve explicitly admitted actions and map their return values at the HTTP boundary

A handler is an explicitly registered application-owned invokable object with an actual public non-static `__invoke()` method. Closures, function or method-name handlers, magic `__call()` substitutes, implicit FQCN fallback, by-reference parameters, variadics, union types, and intersection types are not admitted.

The shared argument resolver uses only these sources, in order:

1. Explicitly enabled injection of the exact `ServerRequestInterface` named type when an HTTP request is supplied.
2. Explicitly enabled matched route parameter by exact parameter name, preserving the encoded string for untyped, `mixed`, or `string` parameters.
3. Explicitly allowlisted concrete class type, registered under its exact type id and resolved through Foundation Container.
4. Declared PHP parameter default.
5. Deterministic resolution failure.

An incompatible selected route parameter or unavailable selected service fails instead of falling through to another source. The resolver does not infer null from nullable declarations or consume arbitrary context, query, body, header, cookie, or route metadata values. The format-neutral `ArgumentResolverInterface::resolve()` does not obtain a transport request through its optional context map.

The combined HTTP `ActionInvoker::dispatch()` resolves the handler once and uses the same instance for argument resolution and invocation. The standalone format-neutral `invoke()` accepts supplied named arguments and does not implicitly resolve additional values. Both entrypoints use strict direct named argument unpacking, not reflection-based coercion. The invoker preserves return values and action-thrown `Throwable`; it does not perform transport mapping or error reporting.

`ActionResponseMapper` owns HTTP adaptation through the effective HTTP-owned PSR-17 factories. Existing `ResponseInterface` values pass through unchanged. With the corresponding mapping flag enabled, strings produce explicit application-owned HTML (`200`, `text/html; charset=utf-8`), and null, bool, int, and array values produce JSON (`200`, `application/json`) exclusively through Foundation `StableJsonEncoder::encodeStable()`. The canonical encoder owns the bytes, list/map distinction, nested value admission, and final LF. Unsupported values, disabled representations, and mapping failures fail closed; no second serializer, DTO auto-serialization, or content negotiation is introduced. Escaping untrusted HTML remains the action owner's responsibility.

### Decision 8: Classify application misses through the canonical error boundary

The existing scalar `RouterInterface` boundary includes method enumeration through:

```php
public function allowedMethods(
    string $path,
    ?string $host = null,
): array;
```

The Routing implementation enumerates unique declared methods whose complete matching domains admit the encoded path, including placeholder requirements, in ascending byte-order `strcmp` order. Invalid, non-canonical, reserved, and unmatched paths return `[]`. Host-based matching is not supported by `routes@1`. Enumeration does not perform another observed route match, mutate context, or add implicit HEAD or OPTIONS methods.

After a null match, `RouterMiddleware` consults Foundation `ReservedPathMatcher`. For reserved paths it attaches the explicit reserved state and delegates; `ActionDispatchMiddleware` forwards that state to the HTTP-owned terminal without selecting route middleware, resolving an action, or emitting action observations. The system endpoint and terminal fallback remain HTTP-owned.

For ordinary non-reserved misses, malformed or empty method tokens produce `HttpNotFoundException` without enumeration. A syntactically valid unmatched method, including a valid extension method, invokes `allowedMethods()` once. An empty list produces `HttpNotFoundException`; a non-empty admitted list produces `HttpMethodNotAllowedException`. Canonical invalid-path rejection produces not found, while unexpected infrastructure failures propagate unchanged. No method normalization, implicit method expansion, or automatic rematching is performed.

`HttpAppProblemMapper` contributes one `ExceptionMapperInterface` service through `ReservedTags::ERROR_MAPPER` (`error.mapper`), priority `800`, metadata `[]`. It maps only the two HttpApp-owned routing exceptions to descriptors with severity `ErrorSeverity::Error`, fixed code and reason, and HTTP status hint:

- `CORETSIA_HTTP_404`, `http-app-not-found`, status `404`, extensions `[]`;
- `CORETSIA_HTTP_405`, `http-app-method-not-allowed`, status `405`, extensions containing the validated `allowed_methods` list.

`allowed_methods` is bounded owner-defined protocol metadata: a non-empty PHP list of at most nine exact declared method strings, unique and strictly ascending by `strcmp`. The admissible vocabulary is `GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `TRACE`, and `CONNECT`. Invalid values are rejected rather than repaired. The descriptor schema and its generic validation remain Contracts-owned.

The selected HttpApp module requires canonical Problem Details participation. HTTP composition must admit the exact `HttpErrorHandlingMiddleware` service and runtime class as the actual outermost effective middleware through its `http.middleware.system_pre` contribution, priority `1000`, metadata `[]`. A substitute or incorrectly placed boundary fails composition.

The outer boundary invokes the canonical ErrorHandler once for a caught downstream failure. `platform/errors` owns normalization, mandatory redaction, and eligible reporting. `platform/problem-details` owns the status-only public Problem Details model and independently validates the required 405 `allowed_methods` extension to construct the HTTP `Allow` header using `, ` as the separator. Invalid required 405 metadata leads to the fixed emergency 500 response without Allow. The extension is not published as a public JSON member or treated as an arbitrary response-header map. HttpApp neither renders errors nor processes them twice.

### Decision 9: Observe admitted action execution independently of routing and request completion

Action observations start only after the invocation-local terminal has successfully validated the captured routing handoff and inputs. They cover handler resolution, argument resolution, invocation, and response mapping. Successful route matching alone does not admit an action observation; reserved delegation, selection failures, middleware denials, and middleware failures before terminal entry produce none.

HttpApp owns the `http.action` span, `http.action_total` counter, `http.action_duration_ms` observation, and PSR-3 debug event `http.action.completed`. The exact bounded `method`, `status`, and `outcome` schemas are defined by `docs/ssot/observability.md` and `docs/ssot/logging-policy.md`. A returned response is `returned` regardless of its status class; a primary failure inside the action boundary is `threw`. Unavailable status is `unknown`. These signals describe the action boundary, not the later finalized HTTP response or Routing match observation.

Tracing, metrics, and logging flags are independent. The existing Foundation Stopwatch measures the action boundary for metrics; unavailable or invalid duration is omitted rather than fabricated. Each signal failure is isolated from the response and primary Throwable. A successfully started span receives one end attempt through `finally`; no raw exception data is recorded. A later middleware continuation failure does not retry the action or revise completed observations.

### Decision 10: Preserve independent registration, trusted middleware policy, and lifecycle ownership

Middleware-producing packages register contributions independently through Foundation declarative definitions and `ReservedTags`. Foundation owns tagged-service discovery and deterministic ordering, while HTTP owns effective composition and the PSR-15 execution API. Implementing or registering `MiddlewareInterface` does not require a dependency on `platform/http`; consuming its public runtime API does. Foundation does not own an HTTP execution engine, middleware registry, or PSR-7/15/17 execution contracts.

Applications must arrange trusted global and route-specific middleware so that applicable authorization precedes any protected response-cache hit or other short-circuit that could bypass it. Route-specific authorization cannot protect a response returned earlier by global middleware. Routing-input rewriting belongs before matching and before applicable admission decisions that depend on those inputs. Guarded dispatch is not an authorization engine. Diagnostics must be safe by construction and must not expose raw request data, action or service identifiers, arguments, results, or arbitrary metadata.

Reusable HttpApp services are stateless or retain immutable configuration and dependencies only. Requests, routing state, selected sequences, reflection objects, actions, arguments, responses, local pipelines, continuations, spans, and timing values remain invocation-local. Runtime-only terminals do not enter declarative definitions or generated artifacts. Routing owns `ContextKeys::PATH_TEMPLATE`; HttpApp does not write ContextKeys or store requests or route state in ContextStore.

Foundation Container owns shared and non-shared service lifecycles. Dispatcher-owned lazy resolution applies only to selected route middleware. Independent canonical lifecycle consumers, including `ResetOrchestrator`, may resolve shared reset-tagged middleware even when not selected for a route. Reset discovery is not filtered by matched route or executed middleware, and no executed-instance registry is maintained.

The existing HTTP UnitOfWork, response finalization, and Kernel/Foundation reset orchestration remain authoritative. HttpApp contributes no nested UnitOfWork, additional context mechanism, reset hook, or duplicate error processing.

## Consequences

### Positive consequences

- One explicit, deterministic application dispatch boundary preserves the ownership of Routing, HTTP, Foundation, Errors, and Problem Details.
- Closed registration records and canonical graph completeness make configured service dependencies verifiable without eager construction.
- Existing route metadata and the public HTTP Pipeline allow ordered application middleware without competing registries or execution mechanisms.
- Original routing identity and single-entry continuation enforcement prevent repeated action terminal entry and cross-middleware routing-input drift within the canonical invocation.
- Bounded action observations distinguish admitted action execution from routing decisions and finalized request outcomes.
- Source and compiled runtime composition use the same configuration, dependency, and lifecycle policies.

### Trade-offs

- Applications must explicitly register actions, eligible argument services, and route middleware; implicit controller discovery and arbitrary argument injection are not supported.
- Selected middleware chains must be fully admitted before execution. A missing or incompatible required service fails closed even when a preceding selected middleware could have short-circuited.
- Middleware participating in guarded dispatch cannot delegate repeatedly or retain a usable continuation beyond its invocation.
- Application routing inputs cannot be rewritten after matching; protected cache and authorization ordering remain application responsibilities.
- The selected runtime requires Routing, Errors, Problem Details, HTTP, and Foundation to be composed together; package installation alone is insufficient.

## Rejected alternatives

### Treat logical action ids as class names or container service ids

Implicit fallback would bypass explicit registration and expose Container autowire behavior as an application dispatch policy. The logical identifier lookup and service resolution have different owners and must remain separate.

### Use dynamic handler-map keys or widen Core configuration grammars

The closed registration list expresses logical identifiers as data while preserving canonical config-path, declarative map-key, and typed-reference admission. A second configuration representation or copied dynamic map would weaken source/compiled consistency.

### Resolve configured services eagerly or add an HttpApp graph validator

Service dependency completeness and successful runtime instance admission are different guarantees. The canonical required-service declarations provide graph validation without transferring service construction or lifecycle ownership to HttpApp.

### Invoke actions directly from matching middleware

Early invocation would bypass remaining app, route_pre, ordinary route, and selected route-specific middleware. The request-local handoff preserves matching decisions until the canonical final dispatch contribution admits execution.

### Introduce a middleware registry, Foundation HTTP engine, or independent route execution loop

Foundation already owns declarative registration and tagged discovery, and HTTP provides the public Pipeline and ordered service resolution API. A second registry or execution loop would duplicate ordering, admission, continuation behavior, and lifecycle responsibility.

### Store routing state globally or validate only at the terminal

Global storage would duplicate context and reset mechanisms. Terminal-only checks would allow intermediate middleware to act on changed routing inputs before restoration. Per-delegation identity and input protection with a final dispatch-boundary check is required.

### Add a separate action invocation guard or permit repeated continuation entry

At-most-once terminal entry is a composition property shared by global and route-specific middleware. A local invocation guard would not protect intermediate middleware or the original routing handoff. The existing HTTP-owned continuation policy owns this guarantee.

### Render routing exceptions locally or enumerate methods through repeated observed matches

Local rendering would duplicate normalization, redaction, reporting, and public error adaptation. Repeated matches would duplicate routing observations and context mutation. The scalar enumeration port and canonical ErrorHandler/Problem Details boundary keep these concerns separate.

### Filter reset discovery by route selection or maintain an executed-middleware registry

Dispatcher-owned lazy selection does not change independent canonical service resolution and reset obligations. A route-dependent reset mechanism would transfer lifecycle policy to the dispatcher.

## Non-goals

- Implicit handler discovery, controller autowiring, model binding, or arbitrary request argument injection.
- A second container, router, route artifact schema, middleware registry, execution engine, invocation guard, or serializer.
- System endpoint execution, host-based routing, automatic rematching, implicit HEAD/OPTIONS expansion, or request rewriting implementation.
- Alternative error normalization, response finalization, context storage, UnitOfWork, or reset orchestration.
- Generic middleware observations, new metric label keys, or export of arbitrary action metadata.
- Transaction rollback, cross-request idempotency, retry guarantees, or sandboxing of application PHP code.

## Verification evidence

The decision is enforced through the dispatch, routing, HTTP composition, error adaptation, and lifecycle tests. Verification must cover:

- explicit handler registration, argument-source admission, strict named invocation, deterministic response mapping, and source/compiled declarative graph completeness;
- exact effective middleware roles, final route contribution placement, mandatory outermost Problem Details participation, and preserved ordinary middleware order;
- ordered artifact-backed route middleware selection, resolved-instance admission, denial and Throwable propagation, and same-instance action dispatch;
- original handoff identity, routing-input preservation at every guarded delegation, single-entry and expired continuation failures, and release of captured invocation-local values;
- canonical reserved delegation, 404/405 mapping, deterministic Allow construction, and emergency response behavior;
- action observation entry, bounded signal schemas, independent flags, failure isolation, and repeated-request lifecycle isolation, including independent canonical reset resolution.

End-to-end verification must exercise the actual artifact-backed router, effective HTTP entrypoint, and canonical runtime composition. Standalone middleware tests do not replace that evidence.

## Related SSoT

- `docs/ssot/http-app-dispatch.md` — exact application dispatch, configuration, handoff, middleware, invocation, and lifecycle policy.
- `docs/ssot/routing-and-http-app-contracts.md` and `docs/ssot/routing-artifact.md` — format-neutral ports and compiled route semantics.
- `docs/ssot/reserved-path-matching.md` and `docs/ssot/http-system-endpoints.md` — reserved ownership and system endpoint delegation.
- `docs/ssot/http-middleware-catalog.md` and `docs/ssot/http-middleware-wiring.md` — canonical HTTP composition and ordering.
- `docs/ssot/di-tags-and-middleware-ordering.md` and `docs/ssot/tags.md` — Foundation registration, discovery, and reserved tags.
- `docs/ssot/runtime-container-definitions.md` and `docs/ssot/compiled-container.md` — declarative graph requirements and compiled runtime composition.
- `docs/ssot/config-roots.md`, `docs/ssot/config-and-env.md`, and `docs/ssot/config-directives.md` — configuration ownership, source paths, and merge policy.
- `docs/ssot/modules-and-manifests.md` and `docs/ssot/application-dependency-sync.md` — module selection and Composer requirements.
- `docs/ssot/json-like-runtime-values.md` — Foundation stable JSON serialization and value admission.
- `docs/ssot/errors-boundary.md`, `docs/ssot/error-descriptor.md`, and `docs/ssot/problem-details.md` — normalized error ownership and HTTP adaptation.
- `docs/ssot/observability.md`, `docs/ssot/logging-policy.md`, and `docs/ssot/observability-and-errors.md` — canonical observation and diagnostic policies.
- `docs/ssot/middleware-context-keys-map.md`, `docs/ssot/context-lifecycle.md`, `docs/ssot/uow-and-reset-contracts.md`, and `docs/ssot/reset-tags.md` — context and lifecycle boundaries.
- `docs/architecture/DEPENDENCIES.md` — production source dependency boundaries.

## Related ADRs

- `docs/adr/ADR-0004-foundation-json-like-runtime-values.md`
- `docs/adr/ADR-0005-routing-httpapp-ports.md`
- `docs/adr/ADR-0006-reset-interface-uow-hooks.md`
- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/adr/ADR-0018-platform-routing-compiled-routes.md`
- `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
- `docs/adr/ADR-0026-config-kernel-merge-directives-reserved-namespaces.md`
- `docs/adr/ADR-0029-kernel-container-compile-artifact.md`
- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md`
- `docs/adr/ADR-0036-platform-errors-errorhandler-exception-mapper.md`
- `docs/adr/ADR-0040-platform-problem-details-http-adaptation.md`
- `docs/adr/ADR-0041-platform-http-runtime-pipeline-wiring.md`
- `docs/adr/ADR-0042-system-route-table-and-reserved-path-matching.md`
