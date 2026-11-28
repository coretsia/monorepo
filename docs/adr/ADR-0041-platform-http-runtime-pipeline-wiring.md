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

# ADR-0041: Platform HTTP runtime pipeline and wiring

```yaml
adrVersion: 1
status: pre-accepted
owner: platform/http
```

## Context

An HTTP target needs one PSR-7/15 request-processing boundary with deterministic middleware ordering, a canonical terminal system-endpoint handler with a safe unmatched response, and Kernel-owned UnitOfWork completion. Foundation already owns the tag registry and declarative container definitions; Kernel already owns the UnitOfWork and runtime-driver compatibility matrix. Reimplementing any of those mechanisms in `platform/http` would introduce competing ordering, state, or lifecycle semantics.

The nine reserved `http.middleware.*` slots separate system, application, and route request processing from response decoration. A conventional sequence that puts `_post` middleware after the request slots would skip decorators when a request-phase middleware returns early. Conversely, treating response decorators as HTTP request-completion observers would expose intermediate responses and obscure the final outcome. Problem Details handling remains a selected, outermost exception boundary rather than an HTTP-owned normalization implementation. Its participation is mandatory when HttpApp is enabled in the effective target module plan and otherwise remains optional.

Application route matching and action dispatch are separate runtime responsibilities. Successful application matching must preserve ordinary downstream global middleware participation, while final action dispatch must consume the request-local routing handoff and execute any explicitly selected route-specific middleware before invoking the action.

Both global and invocation-local route-specific compositions use the same public HTTP Pipeline implementation. Application dispatch requires guarded continuations that preserve routing handoff identity and routing inputs throughout downstream delegation, without introducing another middleware engine, UnitOfWork, or reset lifecycle.

The runtime also needs concrete PSR-17 response and stream factories, but its production dependencies cannot force one PSR-7 implementation on every application. Container compilation must be deterministic even when several implementations are installed. Configuration-driven selection and executable readiness are different boundaries: declarative definitions may describe classes that are unavailable in the current Composer installation, while the owning HTTP entrypoint must reject an unexecutable selection before constructing `HttpKernel` or its pipeline.

When enabled, HTTP request identity must be established after successful Kernel UnitOfWork begin and before the entire PSR-15 chain executes.

The independently enabled correlation, request-id, and security-header policies must be applied after the entire PSR-15 chain returns, including an error response returned by an outermost boundary. Request-level metrics, access logging, and span completion must consume the successfully finalized response or the canonical fatal completion result. Canonical terminal system-endpoint matching and dispatch are independent of general application routing and do not establish a route-template handoff contract. Endpoint decisions require separate bounded observability without changing final-response measurement semantics. The Logging sink already defines a producer-neutral PSR-3 admission, redaction, and serialization boundary, and must not be recreated by an HTTP logging implementation.

The HTTP runtime also needs application-root-local maintenance activation that can change independently of compiled configuration and process lifetime. Static middleware participation belongs to effective target-local configuration; mutable maintenance state belongs to the HTTP runtime and must be shared by cooperating HTTP and CLI processes without introducing another filesystem-locking or serialization mechanism.

## Decision

### Decision 1: Use one canonical PSR-15 pipeline with outer response decorators

`platform/http` owns `HttpMiddlewareStackBuilder`, `HttpServiceFactory`, and the explicitly public `Coretsia\Platform\Http\Middleware\Pipeline` composition API. The builder consumes the existing nine `ReservedTags::HTTP_MIDDLEWARE_*` identifiers and the effective target-local configuration. `HttpServiceFactory` assembles the reusable global Pipeline from the effective service-id sequence, canonical terminal handler, and effective ModulePlan participation.

Both global and invocation-local route-specific composition reuse `Pipeline::resolveServices()` for ordered middleware resolution through the canonical `Psr\Container\ContainerInterface` and `MiddlewareInterface` admission. The Pipeline retains only its immutable construction-time middleware sequence, terminal handler, and explicit continuation policy. Traversal and continuation state belong to each `handle()` invocation.

The stack builder and runtime factory remain HTTP-internal composition services. Consumers of the public Pipeline API do not acquire access to HTTP-internal runtime wiring.

The logical request-phase order is `system_pre`, `system`, `app_pre`, `app`, `route_pre`, `route`, and terminal handling. The actual outer-to-inner PSR-15 chain is:

```text
[selected priority-1000 Problem Details boundary, mandatory with HttpApp]
  -> system_post
  -> app_post
  -> route_post
  -> system_pre
  -> system
  -> app_pre
  -> app (including RouterMiddleware when HttpApp is enabled)
  -> route_pre
  -> ordinary route middleware
  -> [ActionDispatchMiddleware when HttpApp is enabled]
  -> terminal RequestHandlerInterface
```

The final application dispatch contribution belongs to the existing route slot; it does not introduce a tenth middleware slot or replace the HTTP-owned terminal binding.

Global route_pre and route middleware retain their ordinary participation semantics. Global route_post decorators enter before matching and are not matched-route-only middleware. Route-specific response decorators participate through the invocation-local sequence selected by HttpApp.

The canonical terminal is `FallbackRouterHandler`, exposed through the existing `RequestHandlerInterface` alias. It delegates exactly once to the shared `FallbackRouter` and returns a dispatched response unchanged or delegates unmatched response construction to `FallbackResponses`, preserving the safe empty-body HTTP `404`.

`FallbackRouter` consumes the canonical declarations through `ReservedPathMatcher` and implements only explicitly supported system endpoints, reserved endpoint fallback responses, and delegation to `DebugRouter` for the canonical `dev_only` prefix `/_debug/`. `FallbackResponses` owns deterministic response construction through the selected PSR-17 factories and canonical Foundation JSON serialization.

`HttpServiceProvider` registers these services through canonical declarative DI with explicit mandatory dependencies, including the existing Foundation-owned clock and canonical meter port. Source and compiled containers preserve equivalent shared service identities and effective binding precedence.

Terminal dispatch occurs only when the middleware chain delegates to the terminal handler. Earlier middleware responses retain precedence. Returned terminal responses and original Throwables follow the existing decorator, error-boundary, and `HttpKernel` completion semantics.

The canonical terminal behavior is defined by `docs/ssot/http-system-endpoints.md`. The terminal remains independent of application route matching, route-specific middleware admission, action resolution, action invocation, and application authorization.

When HttpApp is enabled, the final application dispatch contribution delegates explicit reserved-path handoffs to this existing terminal. Successfully matched application routes instead execute through the HttpApp-owned invocation-local action terminal. Missing or invalid application routing state does not authorize implicit terminal fallback.

Without HttpApp, the ordinary global chain retains its existing terminal delegation behavior without introducing application routing state.

`DebugRouter` claims only the exact case-sensitive `GET` requests for `/_debug/headers`, `/_debug/modules`, `/_debug/config`, and `/_debug/cache` using the original URI path. The exact `/_debug` declaration and unsupported paths remain unmatched. `DebugGuard` requires enabled debug configuration, the authoritative frozen `local` or `dev` environment from the Kernel-owned `RuntimeDiagnosticsInput`, and explicitly selected `platform.problem-details` in the runtime `ModulePlan`. Ineligible requests remain unmatched; eligible denied requests throw the fixed `DebugForbiddenException` for canonical normalization and selected Problem Details rendering.

The guard owns exact client-IP, trusted-proxy, token-digest, operation and config-key admission for these diagnostics only. The handlers consume canonical redaction ports, module plan, config provenance and boot-selected generation status, without source discovery or artifact inspection. Redaction is a required runtime module dependency of HTTP; HTTP consumes its contracts-level ports rather than concrete Redaction implementation classes.

The debug services are shared declarative terminal dependencies, not middleware. They do not participate in the nine slots, alter the error boundary, bypass maintenance or other earlier middleware, construct an additional routing registry, or introduce another lifecycle or response-finalization boundary.

`TagRegistry::all()` remains the sole tagged-discovery and ordering authority: priority descending, then service id ascending by byte-order comparison. Each slot appends ordinary configured manual service ids after ordinary tagged entries and preserves their configured order. The first effective occurrence within one slot wins; participation in multiple effective global slots is rejected.

The canonical outermost error-boundary role and final application action dispatch role follow explicit HTTP-owned execution-placement rules. These rules preserve the relative order of ordinary tagged and manual entries without changing Foundation's discovery algorithm.

HTTP does not create another sorter, registry, deduplication service, or container-specific middleware resolver. HTTP metadata admission, conditional participation, and canonical execution-role placement remain defined in `docs/ssot/http-middleware-wiring.md`.

Foundation retains ownership of declarative service registration, ReservedTags, TagRegistry, DeterministicOrder, canonical container resolution, and shared/non-shared service lifecycle.

Middleware-producing packages contribute their own MiddlewareInterface implementations through Foundation declarative DI. Implementing middleware or registering a canonical middleware contribution does not require a dependency on `platform/http` solely for registration, discovery, or ordering.

PSR-15 execution, HTTP-specific metadata admission, feature-toggle evaluation, effective slot composition, and continuation enforcement remain owned by `platform/http`. Foundation does not introduce PSR-7, PSR-15, or PSR-17 production dependencies, a middleware-specific registration SPI, or a middleware execution engine to support contribution reuse.

`MaintenanceMiddleware` participates in `http.middleware.system_pre` at priority `900` with metadata `['toggle' => 'http.maintenance.enabled']`. It executes after `TraceContextMiddleware` and before trusted-proxy interpretation, request-context enrichment, trusted-host validation, HTTPS redirect decisions, and request-body processing.

Active maintenance short-circuits downstream request processing with an HTTP `503` response, except for the exact case-sensitive URI path `/_alive`, which delegates to the downstream handler. Maintenance does not implement the liveness endpoint or determine its response status.

`HttpsRedirectMiddleware` participates in `http.middleware.system_pre` at priority `850` with metadata `['toggle' => 'http.https_redirect.enabled']`. It consumes the canonical trusted-proxy request-local scheme metadata when enabled and available, otherwise the direct request URI scheme. Redirect destinations use an explicitly configured, validated target authority rather than request-derived authority values. Redirect eligibility, Location construction, and decision observability are governed by `docs/ssot/http-middleware-wiring.md` and `docs/ssot/observability.md`.

### Decision 2: Reserve the selected error boundary outside every decorator

`HttpErrorHandlingMiddleware` is contributed exclusively by a compatible, explicitly selected `platform.problem-details` module under `ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE` at priority `1000` with metadata exactly `[]`. That priority is reserved: no other `system_pre` registration may displace it. Its special outermost placement is a composition rule, not a new tag or a general permission to reorder system middleware.

The canonical service id and runtime class identity are `Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware`.

When `ModulePlan::hasEnabledModule('platform.http-app')` is true, `HttpServiceFactory` must require that canonical service id at the actual outermost position of the effective middleware sequence.

After the sequence is resolved, the factory must validate the already resolved instances: the canonical error-boundary id must resolve to its exact canonical runtime class, and that class must not participate under another service id. This HttpApp-specific identity validation does not perform another container lookup or import the concrete Problem Details implementation into HTTP.

Missing required outermost participation or incompatible resolved role identity fails with `InvalidArgumentException('http-middleware-app-error-boundary-invalid')`. Generic middleware resolution and MiddlewareInterface admission retain the independent failure semantics of `Pipeline::resolveServices()`.

Without HttpApp, canonical error-boundary declaration validation remains applicable, but participation is optional and the HttpApp-specific resolved error-boundary identity check does not apply.

When downstream handling returns a response normally, entered post decorators unwind in the order `route_post`, `app_post`, `system_post`, and the selected error boundary passes the result through. If a request-phase middleware short-circuits, those already-entered decorators still run; a post decorator that itself short-circuits is decorated only by wrappers already entered outside it. If downstream handling throws, ordinary post decorators are not assumed to receive a response. A selected outermost Problem Details boundary may convert the Throwable after the decorators unwind without a response; that generated response proceeds directly to `HttpKernel` completion, not through a second decorator pass.

For targets without HttpApp, absence of the selected Problem Details module permits ordinary HTTP composition without this error boundary. Composer installation alone does not activate the contribution. `platform/http` propagates uncaught Throwables without creating another `ErrorHandlerInterface`, mapper registry, renderer, redactor, or emergency response policy. Canonical normalization and public HTTP adaptation retain their existing independent owners.

### Decision 3: Make HttpKernel the sole HTTP request lifecycle and completion coordinator

`HttpKernel` implements the existing `HttpKernelInterface` / PSR-15 request-handler contract and consumes the contracts-level `KernelRuntimeInterface`. It invokes `beginUnitOfWork('http')` before the pipeline and attempts exactly one matching `afterUnitOfWork()` with the returned handle after request-level completion. Kernel remains the sole owner of UoW hooks, context lifetime, and reset; HTTP does not register a competing lifecycle or reset orchestrator.

`Coretsia\Platform\Http\RequestId\RequestIdPolicy` owns optional HTTP request identity preparation. `HttpKernel` invokes `RequestIdPolicy::initialize()` after successful Kernel UnitOfWork begin and before entering the entire PSR-15 pipeline.

Request identity preparation operates outside middleware because the selected identifier must already be available when the outermost error boundary, response decorators, and request-phase middleware execute. Middleware execution and short-circuit behavior must not determine whether an enabled request identity is established.

`RequestIdPolicy` is a mandatory non-nullable shared dependency of both `HttpKernel` and `HttpResponseFinalizer`. `HttpServiceProvider` registers the policy through canonical Foundation declarative DI and wires the same shared policy instance into both services regardless of feature enablement. The `http.request_id.enabled` setting exclusively controls request identity preparation and response-header application, not service registration, dependency injection, or middleware participation.

`HttpKernel` invokes the shared policy at the pre-pipeline identity preparation boundary regardless of feature enablement. The disabled policy performs no request identity preparation.

The policy consumes mandatory `Coretsia\Contracts\Context\ContextWriterInterface` and `Coretsia\Contracts\Context\ContextAccessorInterface` dependencies, in that constructor order. It writes only `ContextKeys::REQUEST_ID` through the writer and reads back only that key through the accessor solely for final-response identity verification. The selected identifier MUST NOT be changed or removed during verification. Disabled identity preparation and read-back do not access either port.

`RequestContextMiddleware` consumes the writer only and writes only validated HTTP-owned `CLIENT_IP`, `SCHEME`, `HOST`, `PATH`, and `USER_AGENT` values under the existing enrichment policy. `HttpServiceProvider` requires both context ports and supplies them through canonical declarative service references. Both resolve to the same shared Foundation-owned `ContextStore` in source and compiled containers. HTTP MUST NOT require, register, or downcast to the concrete store, redefine either binding, or execute context clearing or reset. See `docs/ssot/context-store.md`.

HTTP request identity remains independent of Kernel-owned correlation and UnitOfWork identity. Generated request identifiers use the existing Foundation-owned `UlidGenerator` through `IdGeneratorInterface`.

The normative request-id configuration, trusted ingress requirements, identifier validation, and response finalization policy are defined by `docs/ssot/http-middleware-wiring.md`.

The fixed completion order when request preparation, pipeline execution, and response finalization succeed is:

```text
KernelRuntimeInterface::beginUnitOfWork('http')
  -> request-local Stopwatch start and optional http.request span start
  -> RequestIdPolicy::initialize() and invocation-local identity selection
  -> complete PSR-15 Pipeline::handle()
  -> HttpResponseFinalizer::finalize() with the selected request identifier
  -> derive final HTTP status and outcome
  -> request-local Stopwatch stop when a valid token exists
  -> HttpRequestMetricsRecorder::record()
  -> HttpAccessLogWriter::write()
  -> HttpRequestSpanRecorder::finish()
  -> KernelRuntimeInterface::afterUnitOfWork() and Kernel-owned reset
  -> return the finalized ResponseInterface or rethrow the primary Throwable
```

An uncaught failure before request identity preparation prevents both identity preparation and pipeline execution. Failed identity preparation prevents pipeline execution, and an uncaught pipeline failure prevents response finalization.

These failures follow the canonical fatal completion path and do not bypass eligible Kernel completion or reset responsibility.

An optional timing or observability failure cannot suppress the pipeline, replace a completed response, or replace an earlier primary Throwable. `afterUnitOfWork()` is attempted after every successful begin, including fatal paths. A failure during Kernel completion remains authoritative when there is no earlier primary Throwable. `HttpKernel` never returns an intermediate response after finalization fails.

### Decision 4: Split final-response mutation and observability into four stateless services

`HttpResponseFinalizer`, `HttpRequestMetricsRecorder`, `HttpAccessLogWriter`, and `HttpRequestSpanRecorder` are separate HTTP-owned, stateless services wired directly into `HttpKernel` through Foundation declarative DI. None implements or is tagged as PSR-15 middleware; none participates in any of the nine HTTP middleware slots. No observer interface, registry, hook, extra response pipeline, per-request observer cache, or additional reset service is introduced.

`HttpResponseFinalizer` consumes the existing `CorrelationIdProviderInterface`, validated `http.correlation.enabled` / `http.correlation.header_name` settings, the mandatory shared `RequestIdPolicy`, and the mandatory shared `SecurityHeadersPolicy`.

It applies the enabled Kernel-owned correlation response-header policy before the independently enabled HTTP-owned request-id response-header policy.

Before applying the request-id header, the finalizer verifies exact equality between the initially selected invocation-local request identifier and the current `ContextKeys::REQUEST_ID` value obtained through `RequestIdPolicy`.

Missing, invalid, or substituted enabled request identity causes deterministic fatal completion without regenerating or repairing context.

After successful identity-header finalization, the finalizer applies the independently enabled security-header policy in its fixed canonical mutation order. Disabled identity-header application must not bypass security-header application.

`SecurityHeadersPolicy` is an immutable shared configuration dependency registered through canonical Foundation declarative DI independently of feature enablement. It owns validated security-header values and their application order; response mutation belongs exclusively to `HttpResponseFinalizer`.

All enabled response-header policies apply to normally returned responses, request-phase and post-decorator short-circuit responses, HTTPS redirects, returned maintenance responses, mapped Problem Details responses, and emergency responses returned by the selected outermost error boundary.

Security-header application follows outermost error conversion because ordinary post decorators cannot decorate a response constructed after their downstream Throwable has unwound the middleware chain.

The finalizer uses immutable PSR-7 response mutation, consuming the preceding successful response mutation result. Configured non-empty security headers replace matching response headers case-insensitively. Empty optional settings leave application-provided headers unchanged.

The finalizer does not generate identities or modify the response body, status, or unrelated headers. Its deterministic mutation sequence does not establish a PSR-7 serialized header iteration or transport wire-order guarantee.

`HttpRequestMetricsRecorder` consumes the existing `MeterPortInterface` and emits only the canonical `http.request_total` and `http.request_duration_ms` measurements with approved bounded dimensions. `HttpAccessLogWriter` consumes the existing `Psr\Log\LoggerInterface`, and `HttpRequestSpanRecorder` consumes the existing `TracerPortInterface` and an invocation-local `SpanInterface` handle. Optional unselected observability modules retain Foundation noop bindings; the four HTTP services do not introduce backend implementations or source dependencies on optional platform observability packages.

Only a successfully finalized response provides the canonical final HTTP status. A final status below `400` maps to `success`; `400..599` maps to `handled_error`. An uncaught request identity preparation, pipeline, or finalization Throwable maps to `fatal_error` and fixed observability status `unknown`, even if an intermediate response existed. `HttpKernel` supplies one bounded method, status, outcome, and non-negative duration to all request-level recorders; metrics and logging must not infer them from a middleware-local result.

### Decision 5: Keep timing and tracing handles request-local

`HttpKernel::handle()` alone retains the Foundation `Stopwatch` start token, any `SpanInterface` returned from `HttpRequestSpanRecorder::start()`, the initially selected request identifier used for final-response identity verification, the current response, canonical completion fields, and the primary Throwable. These values are never stored in shared service properties, mutable singleton registries, or process-global context. A valid Stopwatch token is stopped at most once; unavailable timing yields a fixed safe zero duration. A successfully started span is ended once after final outcome attributes are set, including failure paths.

Inbound `TraceContextMiddleware` reuses `ContextPropagationInterface` to extract and validate W3C traceparent context on the PSR-15 request side. The current `TracerPortInterface` cannot accept an explicit parent context when starting a span, so extraction does **not** establish automatic parent-child linkage for `http.request`. Any future parent-context support requires a deliberate tracing-contract decision, not an HTTP-local propagation store, second tracer, or changed Kernel UoW lifecycle.

### Decision 6: Keep HTTP completion logging producer-owned and sink-neutral

`HttpAccessLogWriter` is the producer of the static `http.request.completed` event through the canonical PSR-3 `LoggerInterface`. It receives only the bounded completion method, status, outcome, integer-millisecond duration, and optional Kernel-owned correlation id from `HttpKernel`. It emits at most one safe completion record for an enabled request, with `route_template='unknown'` fixed independently of terminal system-endpoint dispatch.

There is no general application route matching, route metadata extraction, inferred path template, request-attribute handoff, or route-template registry in `platform/http`. Canonical system-endpoint dispatch does not supply route-template metadata to the access-log writer. The writer never reconstructs a route from a raw URI, path, query, handler result, `ContextStore`, or an original immutable request. It does not log request/response headers, cookies, credentials, bodies, raw exception messages, or arbitrary request context.

`docs/ssot/logging-policy.md` remains authoritative for the producer-neutral sink contract: accepted context shapes, canonical admission, redaction, formatting, and failure behavior. The HTTP producer must supply safe values; the sink does not derive or reinterpret HTTP status/outcome relationships. `HttpAccessLogWriter` does not import concrete `platform/logging` classes, create another logging pipeline or redaction owner, or bypass the Foundation noop logger when Logging is unselected.

`HttpsRedirectMiddleware` owns redirect-decision observations independently of final HTTP request completion. It uses the existing meter and PSR-3 logger through the respective `http.metrics.enabled` and `http.access_log.enabled` settings. Redirect decisions do not establish the final HTTP request outcome or introduce another completion observer. Canonical metric names, bounded event fields, enablement, failure isolation, and redaction requirements remain defined by `docs/ssot/observability.md`.

`MaintenanceMiddleware` owns maintenance-decision observations independently of redirect decisions and final HTTP request completion. It uses the existing meter and PSR-3 logger through `http.metrics.enabled` and `http.access_log.enabled`. Completed maintenance decisions and safe state-failure warnings follow `docs/ssot/observability.md` without introducing additional spans, completion observers, or lifecycle hooks.

`FallbackRouter` owns terminal system-endpoint decision observability through `MeterPortInterface`. The validated `http.metrics.enabled` setting controls the canonical `http.system_endpoint_total` counter with exactly the `operation`, `status`, and `outcome` labels.

When metrics are enabled, completed response decisions trigger a counter increment attempt after successful response construction. The intentional error decision triggers a counter increment attempt immediately before throwing, with status `unknown`. Emission failures are isolated without retries, replacement of a completed response, or suppression of the intentional Throwable.

Ordinary system-endpoint decisions remain independent of subsequent response decoration, finalization, request completion, and Kernel lifecycle outcomes. They do not introduce another completion observer, access-log event, request span, or reset boundary.

`DebugRouter` owns separate debug-decision observability. The validated `http.metrics.enabled` setting controls `http.debug_total` with exactly the `operation` and `outcome` labels. Operations are `headers`, `modules`, `config`, and `cache`; outcomes are `allowed` and `blocked`. When enabled, each completed eligible decision attempts exactly one increment with value `1`. Independently, `http.access_log.enabled` controls the PSR-3 warning event `http.debug.blocked` with exactly the producer-supplied field `operation` for a blocked decision.

An allowed decision completes after successful diagnostic response construction; a blocked decision completes before the fixed forbidden exception propagates. Unmatched or ineligible requests, missing authorized config keys, and unrelated handler or response-construction failures do not complete debug decisions. Meter and logger failures are isolated independently without retries. Debug decisions are not duplicated through `http.system_endpoint_total` and remain independent of subsequent response finalization and request completion. They do not introduce additional request spans, route metadata, completion observers, or lifecycle ownership.

Canonical decision observability remains defined by `docs/ssot/observability.md` and `docs/ssot/http-system-endpoints.md`.

### Decision 7: Use declarative, target-local PSR-17 factory selection

The validated Phase B `http` configuration subtree is supplied to `HttpServiceProvider::define()` through `ContainerDefinitionContext::configRoot('http')`. `http.factory` is the one explicit selector; its exact allowed values are `external`, `nyholm`, `guzzlehttp`, `laminas`, and `slim`, with default `external`. It does not accept `auto`, arbitrary class names, installation paths, or implementation-specific constructor expressions.

`HttpServiceProvider` uses the following fixed package-owned class-string mapping:

```text
nyholm     ResponseFactoryInterface -> Nyholm\Psr7\Factory\Psr17Factory
           StreamFactoryInterface   -> Nyholm\Psr7\Factory\Psr17Factory

guzzlehttp ResponseFactoryInterface -> GuzzleHttp\Psr7\HttpFactory
           StreamFactoryInterface   -> GuzzleHttp\Psr7\HttpFactory

laminas    ResponseFactoryInterface -> Laminas\Diactoros\ResponseFactory
           StreamFactoryInterface   -> Laminas\Diactoros\StreamFactory

slim       ResponseFactoryInterface -> Slim\Psr7\Factory\ResponseFactory
           StreamFactoryInterface   -> Slim\Psr7\Factory\StreamFactory
```

Nyholm and Guzzle each use one shared concrete factory service for both PSR-17 interfaces. Laminas and Slim use two distinct concrete services. For built-in selections, the provider contributes only the selected class-service definition or definitions and the two corresponding canonical aliases. Installed but unselected implementations do not change definitions or selection.

`http.factory=external` is explicitly application/distribution-owned: `platform/http` contributes no concrete factory classes or replacement aliases, but still requires both effective `ResponseFactoryInterface` and `StreamFactoryInterface` services. The consuming application or distribution selects, installs, and registers compatible implementations through its existing declarative DI; this decision does not prescribe its provider-registration mechanism. No concrete PSR-7/17 library is a mandatory production dependency of `platform/http`. Its external protocol dependencies are the PSR-7/15/17 interfaces and the existing PSR Container and Logging contracts, alongside its declared Coretsia core dependencies.

### Decision 8: Separate canonical container composition from executable HTTP readiness

`ContainerDefinitionBuilder`, `ContainerDefinitionContext`, Foundation service/alias operations, and canonical completeness validation remain the only container-definition system. Definition production is deterministic and does not inspect installed vendor packages, instantiate a factory, or probe `class_exists()`. The canonical later-binding-wins policy remains authoritative for repeated service and alias definitions; `platform/http` does not reject a collision merely because it occurred.

After the canonical target container has been composed, but **before** resolving `HttpKernelInterface` or constructing its executable middleware pipeline, the owning HTTP entrypoint must invoke `HttpRuntimeEntrypointGuard`. The guard checks explicit `platform.http` participation and delegates Kernel driver-matrix validation. For built-in `http.factory` selections it then checks that the configured implementation classes are Composer-autoloadable, resolves both final effective PSR-17 interface bindings through the existing container, verifies the corresponding contracts, and verifies the **exact** selected concrete classes. `external` accepts any compatible effective application-owned bindings without applying built-in identity constraints.

A later override is permissible under Foundation DI precedence; it becomes an HTTP readiness failure only when the final effective implementation violates the configured built-in selection or required PSR-17 contract. Missing external bindings fail through canonical container completeness or resolution, rather than a separate HTTP dependency-graph validator. Missing selected vendor classes, incompatible services, and built-in identity mismatches fail deterministically before HTTP UnitOfWork execution. No implicit alternative provider is consulted, no installed package is treated as a selection, and Kernel does not select a PSR-7/17 implementation.

After effective PSR-17 factory validation, the guard resolves `Coretsia\Contracts\Routing\RoutingArtifactReadinessInterface` and invokes `assertReady()` only when `platform.routing` is enabled in the effective target `ModulePlan`. Missing or invalid bindings and owner-artifact validation failures prevent HTTP construction. If Routing is not enabled, neither the port nor `routes.php` is required. HTTP does not implement Routing artifact validation or application matching; it introduces no direct dependency on `platform/routing`. See `docs/ssot/routing-artifact.md`.

The selected configuration and ordered canonical definitions are source/compiled container inputs. Changing `http.factory` changes the applicable factory definitions and effective aliases, and must participate in the existing configuration-derived container artifact fingerprint/invalidation semantics. Source and compiled containers must preserve equivalent service identity, factory compatibility, tag metadata, effective middleware order, and terminal resolution; there is no runtime-only factory path or second container.

### Decision 9: Preserve owner-supplied Kernel runtime-driver contributions

`HttpRuntimeEntrypointGuard` is HTTP-owned and accepts the selected target's `ModulePlan`, canonical `ConfigRepositoryInterface`, final effective canonical container, and explicit caller-supplied `RuntimeDriverContributions`. It delegates unchanged to the existing `RuntimeDriverResolver::resolve($config, $contributions)` and preserves Kernel's canonical driver matrix errors. It does not infer implementation availability from a driver value, reinterpret Worker contributions, or extend the Kernel matrix.

An empty `RuntimeDriverContributions::fromDrivers([], [])` is valid only when the owning entrypoint has no additional selected driver contributions. In particular, a selected Worker HTTP driver must not be silently discarded by replacing contributions with an empty set. Module participation, Kernel driver-matrix compatibility, PSR-17 effective factory readiness, conditional Routing artifact readiness, and transport-adapter/executable readiness are distinct checks with distinct owners. Transport-specific checks remain with the consuming HTTP entrypoint or integration.

This owner-local guard avoids a generic `RuntimeEntrypointGuard`, a new `RuntimeCapability::HTTP` marker, a second capability registry, or new Kernel-to-platform package knowledge.

### Decision 10: Preserve error, security, and state ownership boundaries

HTTP-owned bad-body, invalid-JSON, and trusted-host exceptions are contributed to the existing canonical error-mapper tag through the contracts-level `ExceptionMapperInterface`. That does not make `platform/http` a second error-normalization owner. A selected outermost Problem Details middleware remains responsible for converting captured Throwables into safe HTTP responses through the canonical handler and renderer; unhandled failures retain their original Throwable precedence through `HttpKernel` completion.

`HttpKernel`, `Pipeline`, the stack builder, `RequestIdPolicy`, `SecurityHeadersPolicy`, `HttpsRedirectPolicy`, `HttpsRedirectMiddleware`, `MaintenanceMiddleware`, `MaintenanceStateStore`, and the dedicated finalization services have no shared mutable request state or additional `kernel.reset` registrations. Optional logging, metrics, span, and timer failures are isolated from the HTTP result and from Kernel completion precedence. Raw request values, credentials, exception diagnostics, route parameters, and arbitrary runtime context must not be exported into metric labels, access logs, public error bodies, or span attributes merely because they were visible during request processing.

`RequestIdPolicy` does not participate in middleware discovery, lifecycle hook registration, or reset discovery. It does not establish authenticated identity, authorization information, or upstream trust.

The selected request identifier must remain unchanged throughout the HTTP UnitOfWork on conforming execution paths and is cleared by successful canonical Foundation reset. Neither selected request identifiers nor rejected upstream values are added to HTTP completion logs, metrics, or span attributes.

The dependency boundary prohibits direct production imports of optional concrete `platform/errors`, `platform/problem-details`, `platform/redaction`, `platform/logging`, `platform/tracing`, and `platform/metrics` implementation classes. Optional integration uses existing contracts, tag contributions from selected modules, and Foundation noop fallbacks. Compile-time import rules remain governed by `docs/architecture/DEPENDENCIES.md`.

The HTTP runtime does not depend on concrete HttpApp implementations or require the HttpApp Composer package or runtime module. Canonical application role identities are validated as exact composition identities without introducing reverse implementation dependencies.

HttpApp consumes only the explicitly public HTTP Pipeline composition API. Middleware-producing packages do not consume HTTP-internal composition or execution services merely to register middleware contributions.

### Decision 11: Separate maintenance middleware participation from application-local runtime activation

`http.maintenance.enabled` controls static middleware participation for the effective HTTP target. It defaults to `true` and does not represent active maintenance state. `http.maintenance.retry_after_default_seconds` supplies the configured default retry delay.

`MaintenanceStateStore` owns application-root-local maintenance state access for HTTP and CLI. It resolves the existing Kernel-owned `RuntimePathContext::applicationRoot()` and uses the fixed runtime state directory `var/maintenance` beneath that root, independently of the current working directory or selected application target.

The directory contains the regular `enabled` flag, optional version-1 `message.json`, and persistent `maintenance.lock` anchor. The presence of the regular flag activates maintenance. The optional public message does not control activation.

The state store reuses Foundation `ScopedFileLock` for cooperating shared reads and exclusive mutations, `StableJsonEncoder` for canonical message serialization, and `StableJsonDecoder` for strict message reconstruction. It owns safe filesystem validation and same-directory file publication without introducing another locking or JSON serialization subsystem.

Maintenance state is evaluated on each participating request and is not cached in shared runtime services. Confirmed inactive state delegates normally. Invalid optional message state retains active maintenance and uses the generic response. Unavailable maintenance state fails closed, except for the exact `/_alive` downstream bypass.

`HttpServiceProvider` registers the shared state store and middleware through canonical Foundation declarative DI with explicit dependencies on the existing runtime path context, Foundation lock service, selected PSR-17 factories, observability ports, and validated configuration. It does not resolve maintenance state or embed application-root paths during definition production.

The maintenance commands `maintenance:enable`, `maintenance:disable`, and `maintenance:status` consume the same shared state store through canonical CLI command registration. Their availability follows explicit `platform.http` module participation in the Console target, independently of HTTP maintenance middleware enablement.

Maintenance activation is shared by participating HTTP targets using the same application root and filesystem. It does not suspend CLI or Worker execution and does not introduce target-specific maintenance state.

Runtime maintenance mutations do not modify compiled configuration, container definitions, or generated artifacts. The existing `var/maintenance` fingerprint exclusion and Kernel artifact ownership remain governed by `docs/ssot/artifacts-and-fingerprint.md`.

Successfully returned maintenance responses follow applicable post-decorator traversal and canonical `HttpKernel` response finalization. Response-construction failures and downstream Throwables retain the existing outermost error-boundary and Kernel completion semantics. Maintenance introduces no additional ContextStore writes, UnitOfWork lifecycle, or reset responsibility.

### Decision 12: Compose application matching and final action dispatch as canonical paired roles

`platform/http-app` owns concrete application matching integration and action dispatch. The canonical contributions are:

```text
Service:  Coretsia\Platform\HttpApp\Middleware\RouterMiddleware
Tag:      http.middleware.app
Priority: 100
Metadata: []

Service:  Coretsia\Platform\HttpApp\Middleware\ActionDispatchMiddleware
Tag:      http.middleware.route
Priority: -1000
Metadata: []
```

The corresponding Foundation tag identifiers are `ReservedTags::HTTP_MIDDLEWARE_APP` and `ReservedTags::HTTP_MIDDLEWARE_ROUTE`.

`HttpMiddlewareStackBuilder` validates the exact role ids, slots, priorities, and empty metadata. Neither role may participate through a manual append list, another slot, toggle metadata, or an incompatible priority. Incomplete paired participation is invalid.

`RouterMiddleware` remains in ordinary effective app-slot order. It is a non-terminal matching contribution: successful application matching and explicit reserved-path recognition attach the immutable request-local routing handoff and delegate through the remaining global middleware. Matching does not resolve or invoke the action.

`ActionDispatchMiddleware` is extracted from ordinary route-slot entries and placed after every ordinary effective tagged and manually appended route middleware, including ordinary tagged entries with priorities below `-1000`. This is an execution-role placement rule, not a different discovery order.

`HttpServiceFactory` derives application dispatch participation from `ModulePlan::hasEnabledModule('platform.http-app')`. Both canonical role ids must be present when enabled and absent otherwise. Composer installation, class availability, manual configuration, or feature toggles do not substitute for effective module participation.

The factory resolves the effective middleware sequence once through `Pipeline::resolveServices()` and validates the already resolved application role identities. Each canonical role id must resolve to its exact canonical runtime class, and that class must not participate under another service id.

A matched application handoff selects and admits the ordered route-specific middleware through HttpApp-owned policy, then constructs an invocation-local Pipeline with the captured action terminal. A reserved handoff delegates to the existing HTTP-owned terminal without route-specific middleware selection, action execution, or action observations.

Ordinary global and selected route-specific middleware retain PSR-15 short-circuit and response-unwind behavior. Successful matching does not bypass remaining ordinary global middleware. Matched action dispatch does not fall through to the system endpoint terminal.

Route-specific selection, explicit service admission, protected routing handoff, argument resolution, action invocation, and response mapping are governed by `docs/ssot/http-app-dispatch.md`.

Both compositions execute inside the existing HttpKernel invocation and Kernel UnitOfWork. Invocation-local composition introduces no shared Pipeline binding, terminal override, nested lifecycle, independent finalization, or reset executor.

HTTP recognizes the exact canonical role identities without importing concrete HttpApp or Problem Details classes. No reverse PHP source, Composer, or module dependency on `platform/http-app` is introduced.

### Decision 13: Reuse the public Pipeline with explicit continuation and routing-input policies

`Coretsia\Platform\Http\Middleware\Pipeline` is the public immutable PSR-15 composition API implementing `RequestHandlerInterface`. Its construction and service-resolution surface is:

```php
/**
 * @param list<MiddlewareInterface> $middleware
 * @param list<non-empty-string> $protectedRequestAttributes
 */
public function __construct(
    array $middleware,
    RequestHandlerInterface $terminal,
    bool $guardedContinuation,
    array $protectedRequestAttributes,
    bool $protectRoutingInputs,
);

/**
 * @param list<non-empty-string> $serviceIds
 * @return list<MiddlewareInterface>
 */
public static function resolveServices(
    ContainerInterface $container,
    array $serviceIds,
): array;

public function handle(ServerRequestInterface $request): ResponseInterface;
```

All constructor arguments are mandatory and explicit. The middleware sequence must be an actual ordered list of MiddlewareInterface instances. An empty list is valid.

`resolveServices()` validates the complete supplied service-id list before resolution, resolves each entry through the supplied canonical container in its original order, and requires a MiddlewareInterface instance for every resolved value. It performs no discovery, sorting, deduplication, independent construction, or consumer-specific admission. Explicit registration requirements for route-specific middleware remain HttpApp-owned.

The protected-attribute policy accepts an actual list of unique non-empty string attribute names. Unguarded execution requires an empty protected-attribute list and disabled routing-input protection. Enabled routing-input protection requires a non-empty protected-attribute list.

The shared global Pipeline uses the continuation policy determined by effective HttpApp participation:

- without HttpApp: `guardedContinuation = false`, `protectedRequestAttributes = []`, `protectRoutingInputs = false`;
- with HttpApp: `guardedContinuation = true`, `protectedRequestAttributes = ['coretsia.http_app.route_dispatch']`, `protectRoutingInputs = true`.

The invocation-local route-specific Pipeline uses the same implementation with guarded execution, `[RouteDispatchState::ATTRIBUTE]`, and routing-input protection enabled.

Ordinary execution preserves PSR-15 nesting and delegation without imposing guarded single-entry or protected-input constraints.

Each guarded middleware invocation receives an invocation-local downstream continuation that permits zero or one entry. Entry is consumed before protected-value validation or downstream execution and is not restored after validation failure or a downstream Throwable.

At each guarded middleware entry, the Pipeline captures configured protected attributes that are present in the incoming request, using `array_key_exists()` semantics, including explicitly present null values. Every captured key and strictly identical value must be preserved during delegation. An equivalent but distinct routing state object is not an admissible replacement.

Previously absent protected attributes may be introduced. The canonical matching contribution owns introduction of the HttpApp routing handoff.

Routing-input protection is an explicit additional policy, independent of generic protected-attribute enforcement. When enabled and at least one configured protected attribute is present at middleware entry, the continuation captures the exact request method, encoded URI path, and URI host returned by the request.

Every subsequent guarded delegation with captured routing inputs must preserve those exact values without decoding, normalization, trimming, or case folding. A changed input must be rejected before entering the next middleware or terminal. Delegating modified routing inputs and restoring them later is not permitted.

The final HttpApp dispatch and action-terminal checks remain additional boundary validation. Terminal-only routing-input validation is insufficient because intermediate middleware may make security or admission decisions using the modified request.

Guarded continuation failures use fixed LogicException messages:

```text
http-pipeline-continuation-expired
http-pipeline-continuation-reused
http-pipeline-protected-attribute-changed
http-pipeline-protected-routing-input-changed
```

The continuation expires through `finally` when its owning middleware process invocation returns or throws, including ordinary short-circuits. Expiration releases the downstream handler reference, captured protected values, and captured routing inputs. Continuations are not cloneable and cannot enter downstream execution after expiration.

All traversal, continuation, and captured request state remains invocation-local. Reusable Pipeline services do not retain executed chains or request data.

This policy permits at most one framework-owned action terminal entry per canonical matched HTTP invocation. It does not provide application transaction rollback, cross-request idempotency, retry safety, or a sandbox against deliberately hostile PHP code.

The Pipeline remains independent of routing implementations, HttpKernel lifecycle, configuration, module discovery, error mapping, ContextStore, and reset orchestration. All returned responses and propagated Throwables retain the existing outer error-boundary and canonical HTTP completion semantics.

## Consequences

### Positive consequences

- One deterministic PSR-15 chain preserves ordinary short-circuit response decorators and keeps error conversion outside their response-unwind path.
- Canonical terminal system endpoints reuse Foundation-owned reserved-path matching, explicit shared dependencies, deterministic response construction, and the existing error boundary without introducing general application routing or another route registry.
- Terminal endpoint decisions remain independently observable without changing finalized HTTP request status, outcome, or Kernel lifecycle semantics.
- Application matching preserves ordinary downstream global middleware participation, and action dispatch occupies the canonical final effective route position.
- Global and invocation-local route-specific middleware share one public Pipeline and middleware service-resolution implementation.
- Guarded continuations preserve the routing handoff and routing inputs across middleware boundaries and prevent repeated or expired downstream entry without another request lifecycle.
- Middleware producers retain independent Foundation-owned registration and service-resolution boundaries without acquiring unnecessary HTTP implementation dependencies.
- `HttpKernel` observes only finalized response state, including selected Problem Details responses, without introducing another observer pipeline.
- When enabled, HTTP request identity is available before middleware execution and remains independent of middleware ordering, Kernel correlation identity, and correlation response-header enablement.
- Exact invocation-local identity verification prevents substituted context identifiers from being emitted as finalized request-id response headers.
- Independently enabled security headers apply to every successfully returned pipeline response, including mapped and emergency responses produced by the selected outermost error boundary.
- HTTPS redirects use validated explicit destination authorities and canonical proxy scheme metadata without introducing another proxy trust or request context policy.
- Application-local maintenance activation and public message updates take effect without rebuilding compiled configuration, reconstructing the HTTP pipeline, or restarting application processes.
- Maintenance short-circuits downstream request processing while preserving request identity preparation, the selected outermost error boundary, applicable response decorators, final-response hardening, and Kernel-owned completion.
- HTTP and CLI share one filesystem-backed maintenance state owner without introducing another runtime-state registry.
- Distinct stateless completion services preserve single-purpose DI boundaries, independent failure isolation, and Kernel-owned UoW/reset semantics.
- Explicit PSR-17 selection supports one shared factory or two distinct factories while avoiding a mandatory vendor implementation.
- Canonical declarative definitions and later-binding-wins semantics remain identical in source and compiled containers.
- Entry-point readiness rejects missing or mismatched selected implementations before HTTP request construction without modifying Kernel driver resolution.
- HTTP access-log events reuse the producer-neutral logging sink and its redaction policy without importing concrete Logging implementations.

### Trade-offs

- The consuming HTTP application or distribution must install compatible PSR-7/17 packages and provide complete bindings for `external` selection.
- A built-in selection whose vendor classes are not autoloadable can be represented declaratively but cannot pass HTTP executable readiness.
- Middleware that short-circuits within a post slot can prevent inner middleware from running, as required by standard PSR-15 semantics.
- Effective HttpApp participation requires the canonical matching and final dispatch pair, mandatory outermost Problem Details participation, and guarded continuation execution.
- Middleware participating in guarded application composition must not retry or retain downstream continuations. Routing-input rewriting must occur before matching and before every applicable admission decision that depends on those inputs.
- Guarded continuation failures do not imply rollback of action side effects or application-level idempotency.
- Inbound traceparent extraction cannot automatically establish a request span's parent without a future change to the tracing port contract.
- Terminal system-endpoint dispatch does not establish application route-template metadata; the canonical access-log value remains `route_template='unknown'`.
- Participating HTTP and CLI processes require compatible filesystem access to the application-local maintenance directory and shared lock anchor. Scoped lock operations may block while another cooperating operation holds an incompatible lock.
- Maintenance state is local to cooperating processes using the same application root and filesystem; it does not provide distributed synchronization or crash-transactional durability.

## Rejected alternatives

### Introduce a generic RuntimeEntrypointGuard or new HTTP capability registry

Rejected because Kernel already owns the runtime-driver matrix while platform-specific executability belongs to the owning entrypoint. Another generic guard or capability marker would conflate driver validity with module, factory, or transport availability.

### Add a separate middleware registry, sorter, or duplicate deduplication layer

Rejected because `ReservedTags`, `TagRegistry::all()`, and `HttpMiddlewareStackBuilder` already define discovery, ordering, and effective slot participation. A parallel registry would risk different source/compiled ordering and break first-occurrence semantics.

### Execute the action directly from matching middleware

Rejected because terminal action execution from the app slot bypasses remaining ordinary global application and route middleware. Application matching and final action dispatch must remain separate canonical roles.

### Introduce a second route-specific middleware engine or move PSR-15 execution into Foundation

Rejected because the public HTTP Pipeline already owns ordered middleware composition, service-instance admission, traversal, and continuation enforcement. Foundation owns registration, discovery, ordering primitives, and canonical container resolution without requiring transport execution dependencies.

### Validate routing inputs only at the application action terminal

Rejected because intermediate global or route-specific middleware could consume changed routing inputs and restore them before terminal entry. The existing guarded Pipeline must preserve protected handoff identity and routing inputs at every subsequent guarded delegation.

### Introduce reverse HttpApp dependencies from HTTP

Rejected because optional application dispatch participates through effective module composition and exact canonical role identities. HTTP does not require concrete HttpApp classes, Composer dependencies, or runtime module dependencies to assemble its global pipeline.

### Place response decorators after request-phase middleware or run an observer pipeline

Rejected because post middleware would be skipped on ordinary early returns and a second completion chain could observe intermediate responses. The selected topology retains PSR-15 response unwinding; `HttpKernel` alone owns final-response observability.

### Apply all-response security headers through ordinary post middleware

Rejected because an ordinary post decorator cannot receive a response constructed by the outermost error boundary after downstream Throwable propagation. Moving security-header application into that boundary would also make HTTP hardening depend on optional Problem Details participation.

The canonical `HttpResponseFinalizer` applies configured security headers after the complete pipeline returns and before final-response observability, without another middleware slot, decorator traversal, or completion lifecycle.

### Let middleware or individual recorders own UnitOfWork and reset hooks

Rejected because Kernel already provides `beginUnitOfWork()` / `afterUnitOfWork()` and reset discipline. A duplicate lifecycle would risk stale context, double completion, and changed failure precedence.

### Prepare request identity through PSR-15 middleware

Rejected because request identity must be established before every entered middleware, including the outermost error boundary and response decorators. Middleware placement and short-circuit behavior must not control identity availability.

The existing `HttpKernel` lifecycle and the shared `RequestIdPolicy` provide the preparation boundary without introducing another middleware slot, priority, tag registration, or request lifecycle.

### Require one concrete PSR-7/17 package or infer the best installed implementation

Rejected because a mandatory implementation or vendor scan would make installed Composer packages silently influence runtime selection. Arbitrary configured factory FQCNs, `auto` selection, reflection-based fallback construction, package-local PSR message/factory implementations, and Kernel-owned provider selection are likewise excluded.

### Reject every later factory binding or invent an HTTP definition-conflict policy

Rejected because Foundation's canonical later-binding-wins behavior must remain intact. HTTP verifies the resulting selected class identity and PSR-17 contracts at readiness; it does not override provider precedence.

### Let external selection register fallback factories or dictate application providers

Rejected because `external` means both PSR-17 interface bindings are owned by the consuming application or distribution. A package-owned replacement or prescribed registration mechanism would contradict that explicit boundary.

### Infer routing metadata from HTTP requests for completion logging

Rejected because raw paths, request attributes, and untrusted route parameters are not reliable canonical route templates. System-endpoint decisions do not provide an application route-template handoff. The canonical producer value remains `route_template='unknown'`.

### Write HTTP access logs through a separate logging backend or redactor

Rejected because `LoggerInterface` and the producer-neutral Logging sink already own admission, redaction, and output. A direct `platform/logging` implementation dependency or parallel log pipeline would duplicate policy and weaken package boundaries.

### Store request spans, Stopwatch tokens, or response state in shared services

Rejected because request completion and reentrant pipeline handling require invocation-local state. Shared mutable caches or extra reset registrations would violate the existing lifecycle model.

### Encode active maintenance state in compiled configuration or shared process memory

Rejected because runtime activation must remain observable across cooperating HTTP and CLI processes without recompiling configuration, rebuilding the container, or restarting long-running processes.

The effective target-local configuration controls middleware participation and the default retry delay. Application-local filesystem state independently controls runtime activation.

## Non-goals

This decision does not implement concrete application route matching algorithms, route artifact ownership, application route admission or authorization, route-specific middleware selection policy, action resolution or invocation, request-from-globals transport adapters, or SAPI response emission. It does not perform application/distribution Composer package installation or introduce a new driver matrix, HTTP capability registry, alternate DI container, PSR request/response implementation, provider-discovery mechanism, runtime factory fallback, or additional middleware tags. It does not define a second error normalization, Problem Details renderer, redaction owner, logging backend, metrics collector, span-parent linkage mechanism, observer registry, UnitOfWork lifecycle, or reset implementation. It does not infer route templates or implement CORS, uploads, or rate limiting. It does not implement HSTS, CSP nonce generation, request-dependent security headers, TLS termination, or transport-level HTTPS enforcement.

Maintenance does not implement system endpoints, distributed maintenance-state synchronization, crash-transactional filesystem updates, target-specific activation, CLI or Worker suspension, or another generated-artifact lifecycle.

## Verification

Verification must cover the exact outer-to-inner pipeline order, priority-`1000` selected/unselected error boundary, tagged priority and manual append behavior, same-slot deduplication, cross-slot rejection, toggle metadata validation, normal and early-return decorator unwind, and Throwable precedence. It must separately verify that outer error conversion completes through `HttpKernel` finalization, not through an additional post-decorator traversal.

Application composition verification must cover exact canonical role declarations, paired participation, effective ModulePlan consistency, resolved role identity validation without duplicate service resolution, mandatory outermost Problem Details participation with HttpApp, and optional participation without it.

Verification must preserve Foundation discovery ordering, ordinary tagged and manual append order, and final action dispatch placement after all ordinary route middleware. It must cover successful matching through downstream global middleware, invocation-local route-specific composition, ordinary short-circuits, reserved-path delegation, unchanged terminal ownership, and canonical HttpKernel completion.

Public Pipeline verification must cover exact constructor and service-resolution contracts, ordinary and guarded execution modes, explicit continuation policy admission, single-entry consumption before validation, repeated and expired entry rejection, strict protected-attribute presence and value identity, exact routing-input preservation across subsequent delegations, and expiration with release of captured references.

Routing-input mutation tests must reject changed method, encoded path, or host before intermediate downstream middleware entry, including attempts to restore those values later. Source and compiled composition must preserve equivalent continuation policies and effective canonical role participation. HTTP without HttpApp must retain ordinary Pipeline execution and existing error-boundary participation semantics.

Source and compiled container tests must compare service definitions, effective aliases, factory sharing/distinction, final provider overrides, middleware tag metadata and ordering, terminal handling, and HTTP lifecycle outcomes. Factory readiness tests must distinguish declarative selection from missing autoloadable classes, missing external ports, incompatible implementations, and overwritten built-in identities; they must confirm failure before UnitOfWork, `HttpKernel`, terminal construction, and pipeline execution. Completion tests must cover safe final-response metrics/logging/span handling, independent optional failures, primary Throwable precedence, and the fixed `route_template='unknown'` value independently of terminal system-endpoint dispatch.

Request identity verification must cover separate mandatory writer and accessor dependencies, shared Foundation context identity in source and compiled containers, restricted owner-local read-back, disabled policy behavior, trusted upstream acceptance, canonical Foundation ULID generation, pre-pipeline context availability, exact final-response identity verification, independent identity-header enablement, deterministic fatal completion, shared declarative policy wiring, and canonical UnitOfWork reset isolation.

Response hardening verification must cover independent security-header enablement, mandatory shared policy wiring, canonical header mutation order, immutable response chaining, case-insensitive replacement, preservation of unrelated headers and empty optional settings, strict policy validation, and fatal completion on response mutation failure.

Integration verification must cover security headers on normal, request-phase and post-decorator short-circuit, redirect, returned maintenance, mapped Problem Details, and emergency responses, including independence from correlation and request-id enablement.

HTTPS redirect verification must cover canonical priority and conditional participation, same-slot deduplication, trusted and untrusted proxy handoff, independence from ContextStore enrichment, explicit target authority validation, method and status policy, encoded Location safety, downstream execution semantics, bounded redirect-decision observability, and independent observability failure isolation.

Maintenance verification must cover exact priority and toggle metadata, static participation and dynamic activation independence, same-slot deduplication, application-root resolution, shared declarative state-store wiring, source/compiled container parity, strict version-1 message validation, safe filesystem operations, idempotent mutations, retained-message handling, and runtime-state visibility without rebuilding services.

Terminal integration verification must cover canonical system-endpoint eligibility and response contracts, reserved endpoint fallback semantics, earlier middleware precedence, unmatched empty-body `404` responses, mandatory shared endpoint dependencies, clock and PSR-17 binding overrides, canonical matcher consumption, intentional Throwable propagation, and independently isolated endpoint-decision observability. Endpoint decisions must not alter canonical response finalization, request-level observations, UnitOfWork completion, or reset discipline.

Debug integration verification must cover exact original-path dispatch, authoritative environment and selected-module eligibility, fail-closed IP and token admission, operation and config-key allowlists, bounded safe response projections, mandatory shared redaction dependencies, source/compiled runtime parity, boot-selected generation status, canonical forbidden normalization and public rendering, earlier middleware precedence, and independently isolated debug decisions without altering finalized request observations or UnitOfWork completion.

HTTP integration verification must cover deterministic maintenance responses, retry-delay selection, HEAD behavior, exact liveness bypass, invalid-message fallback, unavailable-state fail-closed behavior, precedence over downstream request processing, bounded decision observability, applicable post-decorator traversal, canonical finalization, Throwable precedence, and unchanged Kernel completion and reset discipline.

CLI integration verification must cover command discovery, safe structured output, application-root isolation, target-independent runtime activation, and unchanged Kernel fingerprint, generation identity, and generated artifact bytes across maintenance mutations.

Sequential HTTP isolation verification follows the requirements defined by `docs/ssot/http-middleware-wiring.md`.

## Related SSoT

- [HTTP Middleware Wiring SSoT](../ssot/http-middleware-wiring.md)
- [HTTP Middleware Catalog SSoT](../ssot/http-middleware-catalog.md)
- [HTTP System Endpoints SSoT](../ssot/http-system-endpoints.md)
- [HTTP Application Dispatch SSoT](../ssot/http-app-dispatch.md)
- [Routing and HttpApp Contracts SSoT](../ssot/routing-and-http-app-contracts.md)
- [Tag Registry](../ssot/tags.md)
- [DI Tags and Middleware Ordering SSoT](../ssot/di-tags-and-middleware-ordering.md)
- [Runtime Container Definitions (SSoT)](../ssot/runtime-container-definitions.md)
- [Config and env SSoT](../ssot/config-and-env.md)
- [Runtime Drivers SSoT](../ssot/runtime-drivers.md)
- [Routing Artifact SSoT](../ssot/routing-artifact.md)
- [UoW and Reset Contracts SSoT](../ssot/uow-and-reset-contracts.md)
- [Context Keys SSoT](../ssot/context-keys.md)
- [Context Store SSoT](../ssot/context-store.md)
- [Context Lifecycle SSoT](../ssot/context-lifecycle.md)
- [Time, IDs, and Duration SSoT](../ssot/time-ids-and-duration.md)
- [UnitOfWork Outcome Policy SSoT](../ssot/uow-outcome-policy.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](../ssot/observability.md)
- [Logging Policy SSoT](../ssot/logging-policy.md)
- [Problem Details SSoT](../ssot/problem-details.md)
- [Sensitive Data Redaction SSoT](../ssot/sensitive-data-redaction.md)
- [Artifacts and Fingerprint Behavior](../ssot/artifacts-and-fingerprint.md)

## Related ADRs

- [ADR-0014: DI container, tags, deterministic ordering, and reset orchestration](./ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md)
- [ADR-0015: ContextBag, ContextStore, and CorrelationId](./ADR-0015-context-bag-context-store-correlation-id.md)
- [ADR-0020: Kernel runtime UnitOfWork SPI](./ADR-0020-kernel-runtime-uow-spi.md)
- [ADR-0022: UnitOfWork result and outcome policy](./ADR-0022-unit-of-work-result-outcome-policy.md)
- [ADR-0027: Runtime driver resolution and compatibility matrix](./ADR-0027-runtime-driver-resolution.md)
- [ADR-0029: Kernel compiled container artifact](./ADR-0029-kernel-container-compile-artifact.md)
- [ADR-0030: Canonical Runtime Container Definitions](./ADR-0030-canonical-runtime-container-definitions.md)
- [ADR-0037: Platform logging PSR-3 backend and sink safety](./ADR-0037-platform-logging-psr3.md)
- [ADR-0038: Platform tracing W3C traceparent propagation](./ADR-0038-platform-tracing-w3c-propagation.md)
- [ADR-0040: Platform Problem Details HTTP adaptation](./ADR-0040-platform-problem-details-http-adaptation.md)
- [ADR-0043: Platform HTTP App router middleware and action invoker](./ADR-0043-platform-http-app-router-middleware-action-invoker.md)
