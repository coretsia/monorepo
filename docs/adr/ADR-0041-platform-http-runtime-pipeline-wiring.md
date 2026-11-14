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

An HTTP target needs one PSR-7/15 request-processing boundary with deterministic middleware ordering, a safe pre-routing terminal handler, and Kernel-owned UnitOfWork completion. Foundation already owns the tag registry and declarative container definitions; Kernel already owns the UnitOfWork and runtime-driver compatibility matrix. Reimplementing any of those mechanisms in `platform/http` would introduce competing ordering, state, or lifecycle semantics.

The nine reserved `http.middleware.*` slots separate system, application, and route request processing from response decoration. A conventional sequence that puts `_post` middleware after the request slots would skip decorators when a request-phase middleware returns early. Conversely, treating response decorators as HTTP request-completion observers would expose intermediate responses and obscure the final outcome. Optional Problem Details handling must remain a selected, outermost exception boundary rather than an HTTP-owned normalization implementation.

The runtime also needs concrete PSR-17 response and stream factories, but its production dependencies cannot force one PSR-7 implementation on every application. Container compilation must be deterministic even when several implementations are installed. Configuration-driven selection and executable readiness are different boundaries: declarative definitions may describe classes that are unavailable in the current Composer installation, while the owning HTTP entrypoint must reject an unexecutable selection before constructing `HttpKernel` or its pipeline.

Request-level metrics, access logging, span completion, and correlation response headers must observe the final response after the entire PSR-15 chain, including an error response returned by an outermost boundary. The HTTP runtime has no route matching or route-template handoff in this baseline. The Logging sink already defines a producer-neutral PSR-3 admission, redaction, and serialization boundary, and must not be recreated by an HTTP logging implementation.

## Decision

### Decision 1: Use one canonical PSR-15 pipeline with outer response decorators

`platform/http` owns `HttpMiddlewareStackBuilder`, `HttpServiceFactory`, and `Pipeline`. The builder consumes the existing nine `ReservedTags::HTTP_MIDDLEWARE_*` identifiers and the effective target-local configuration; `HttpServiceFactory` resolves ordered service ids through the canonical `Psr\Container\ContainerInterface` and verifies `MiddlewareInterface` before constructing the PSR-15 pipeline. `Pipeline` keeps only immutable construction-time middleware and the terminal handler; traversal state is local to each `handle()` invocation.

The logical request-phase order is `system_pre`, `system`, `app_pre`, `app`, `route_pre`, `route`, and terminal handling. The actual outer-to-inner PSR-15 chain is:

```text
[selected priority-1000 Problem Details boundary]
  -> system_post
  -> app_post
  -> route_post
  -> system_pre
  -> system
  -> app_pre
  -> app
  -> route_pre
  -> route
  -> terminal RequestHandlerInterface
```

The pre-routing terminal is `FallbackRouterHandler`, returning a safe empty-body HTTP `404` through the selected PSR-17 factories. It does not match routes, implement system endpoints, inspect URLs, or infer route metadata.

`TagRegistry::all()` remains the sole tagged-discovery and ordering authority: priority descending, then service id ascending by byte-order comparison. Each slot appends configured manual service ids after its tagged entries and preserves their configured order. The first effective occurrence within one slot wins; participation in multiple effective slots is rejected. HTTP does not create another sorter, registry, deduplication service, or container-specific middleware resolver. HTTP metadata and conditional participation remain defined in `docs/ssot/http-middleware-wiring.md`.

### Decision 2: Reserve the selected error boundary outside every decorator

`HttpErrorHandlingMiddleware` is contributed exclusively by a compatible, explicitly selected `platform.problem-details` module under `ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE` at priority `1000` with metadata exactly `[]`. That priority is reserved: no other `system_pre` registration may displace it. Its special outermost placement is a composition rule, not a new tag or a general permission to reorder system middleware.

When downstream handling returns a response normally, entered post decorators unwind in the order `route_post`, `app_post`, `system_post`, and the selected error boundary passes the result through. If a request-phase middleware short-circuits, those already-entered decorators still run; a post decorator that itself short-circuits is decorated only by wrappers already entered outside it. If downstream handling throws, ordinary post decorators are not assumed to receive a response. A selected outermost Problem Details boundary may convert the Throwable after the decorators unwind without a response; that generated response proceeds directly to `HttpKernel` completion, not through a second decorator pass.

Without explicit module selection, no Problem Details middleware is contributed merely because its Composer package exists. `platform/http` propagates uncaught Throwables without creating another `ErrorHandlerInterface`, mapper registry, renderer, redactor, or emergency response policy. Canonical normalization and public HTTP adaptation retain their existing independent owners.

### Decision 3: Make HttpKernel the sole HTTP request lifecycle and completion coordinator

`HttpKernel` implements the existing `HttpKernelInterface` / PSR-15 request-handler contract and consumes the contracts-level `KernelRuntimeInterface`. It invokes `beginUnitOfWork('http')` before the pipeline and attempts exactly one matching `afterUnitOfWork()` with the returned handle after request-level completion. Kernel remains the sole owner of UoW hooks, context lifetime, and reset; HTTP does not register a competing lifecycle or reset orchestrator.

The fixed completion order is:

```text
KernelRuntimeInterface::beginUnitOfWork('http')
  -> request-local Stopwatch start and optional http.request span start
  -> complete PSR-15 Pipeline::handle()
  -> HttpResponseFinalizer::finalize() for a returned response
  -> derive final HTTP status and outcome
  -> request-local Stopwatch stop when a valid token exists
  -> HttpRequestMetricsRecorder::record()
  -> HttpAccessLogWriter::write()
  -> HttpRequestSpanRecorder::finish()
  -> KernelRuntimeInterface::afterUnitOfWork() and Kernel-owned reset
  -> return the finalized ResponseInterface or rethrow the primary Throwable
```

An optional timing or observability failure cannot suppress the pipeline, replace a completed response, or replace an earlier primary Throwable. `afterUnitOfWork()` is attempted after every successful begin, including fatal paths. A failure during Kernel completion remains authoritative when there is no earlier primary Throwable. `HttpKernel` never returns an intermediate response after finalization fails.

### Decision 4: Split final-response mutation and observability into four stateless services

`HttpResponseFinalizer`, `HttpRequestMetricsRecorder`, `HttpAccessLogWriter`, and `HttpRequestSpanRecorder` are separate HTTP-owned, stateless services wired directly into `HttpKernel` through Foundation declarative DI. None implements or is tagged as PSR-15 middleware; none participates in any of the nine HTTP middleware slots. No observer interface, registry, hook, extra response pipeline, per-request observer cache, or additional reset service is introduced.

`HttpResponseFinalizer` consumes the existing `CorrelationIdProviderInterface` and validated `http.correlation.enabled` / `http.correlation.header_name` settings. It applies the Kernel-owned correlation value to the completed immutable PSR-7 response when enabled, including ordinary short-circuit and Problem Details responses. It does not create another correlation identity or modify the response body, status, or unrelated headers.

`HttpRequestMetricsRecorder` consumes the existing `MeterPortInterface` and emits only the canonical `http.request_total` and `http.request_duration_ms` measurements with approved bounded dimensions. `HttpAccessLogWriter` consumes the existing `Psr\Log\LoggerInterface`, and `HttpRequestSpanRecorder` consumes the existing `TracerPortInterface` and an invocation-local `SpanInterface` handle. Optional unselected observability modules retain Foundation noop bindings; the four HTTP services do not introduce backend implementations or source dependencies on optional platform observability packages.

Only a successfully finalized response provides the canonical final HTTP status. A final status below `400` maps to `success`; `400..599` maps to `handled_error`. An uncaught pipeline or finalization Throwable maps to `fatal_error` and fixed observability status `unknown`, even if an intermediate response existed. `HttpKernel` supplies one bounded method, status, outcome, and non-negative duration to all request-level recorders; metrics and logging must not infer them from a middleware-local result.

### Decision 5: Keep timing and tracing handles request-local

`HttpKernel::handle()` alone retains the Foundation `Stopwatch` start token, any `SpanInterface` returned from `HttpRequestSpanRecorder::start()`, the current response, canonical completion fields, and the primary Throwable. These values are never stored in shared service properties, mutable singleton registries, or process-global context. A valid Stopwatch token is stopped at most once; unavailable timing yields a fixed safe zero duration. A successfully started span is ended once after final outcome attributes are set, including failure paths.

Inbound `TraceContextMiddleware` reuses `ContextPropagationInterface` to extract and validate W3C traceparent context on the PSR-15 request side. The current `TracerPortInterface` cannot accept an explicit parent context when starting a span, so extraction does **not** establish automatic parent-child linkage for `http.request`. Any future parent-context support requires a deliberate tracing-contract decision, not an HTTP-local propagation store, second tracer, or changed Kernel UoW lifecycle.

### Decision 6: Keep HTTP completion logging producer-owned and sink-neutral

`HttpAccessLogWriter` is the producer of the static `http.request.completed` event through the canonical PSR-3 `LoggerInterface`. It receives only the bounded completion method, status, outcome, integer-millisecond duration, and optional Kernel-owned correlation id from `HttpKernel`. It emits at most one safe completion record for an enabled request, with `route_template='unknown'` fixed in this routing-free baseline.

There is no route matching, route metadata extraction, inferred path template, request-attribute handoff, or new route-template registry in `platform/http`. The writer never reconstructs a route from a raw URI, path, query, handler result, `ContextStore`, or an original immutable request. It does not log request/response headers, cookies, credentials, bodies, raw exception messages, or arbitrary request context.

`docs/ssot/logging-policy.md` remains authoritative for the producer-neutral sink contract: accepted context shapes, canonical admission, redaction, formatting, and failure behavior. The HTTP producer must supply safe values; the sink does not derive or reinterpret HTTP status/outcome relationships. `HttpAccessLogWriter` does not import concrete `platform/logging` classes, create another logging pipeline or redaction owner, or bypass the Foundation noop logger when Logging is unselected.

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

The selected configuration and ordered canonical definitions are source/compiled container inputs. Changing `http.factory` changes the applicable factory definitions and effective aliases, and must participate in the existing configuration-derived container artifact fingerprint/invalidation semantics. Source and compiled containers must preserve equivalent service identity, factory compatibility, tag metadata, effective middleware order, and terminal resolution; there is no runtime-only factory path or second container.

### Decision 9: Preserve owner-supplied Kernel runtime-driver contributions

`HttpRuntimeEntrypointGuard` is HTTP-owned and accepts the selected target's `ModulePlan`, canonical `ConfigRepositoryInterface`, final effective canonical container, and explicit caller-supplied `RuntimeDriverContributions`. It delegates unchanged to the existing `RuntimeDriverResolver::resolve($config, $contributions)` and preserves Kernel's canonical driver matrix errors. It does not infer implementation availability from a driver value, reinterpret Worker contributions, or extend the Kernel matrix.

An empty `RuntimeDriverContributions::fromDrivers([], [])` is valid only when the owning entrypoint has no additional selected driver contributions. In particular, a selected Worker HTTP driver must not be silently discarded by replacing contributions with an empty set. Module participation, Kernel driver-matrix compatibility, PSR-17 effective factory readiness, and transport-adapter/executable readiness are distinct checks with distinct owners. Transport-specific checks remain with the consuming HTTP entrypoint or integration.

This owner-local guard avoids a generic `RuntimeEntrypointGuard`, a new `RuntimeCapability::HTTP` marker, a second capability registry, or new Kernel-to-platform package knowledge.

### Decision 10: Preserve error, security, and state ownership boundaries

HTTP-owned bad-body, invalid-JSON, and trusted-host exceptions are contributed to the existing canonical error-mapper tag through the contracts-level `ExceptionMapperInterface`. That does not make `platform/http` a second error-normalization owner. A selected outermost Problem Details middleware remains responsible for converting captured Throwables into safe HTTP responses through the canonical handler and renderer; unhandled failures retain their original Throwable precedence through `HttpKernel` completion.

`HttpKernel`, `Pipeline`, the stack builder, and the dedicated finalization services have no shared mutable request state or additional `kernel.reset` registrations. Optional logging, metrics, span, and timer failures are isolated from the HTTP result and from Kernel completion precedence. Raw request values, credentials, exception diagnostics, route parameters, and arbitrary runtime context must not be exported into metric labels, access logs, public error bodies, or span attributes merely because they were visible during request processing.

The dependency boundary prohibits direct production imports of optional concrete `platform/errors`, `platform/problem-details`, `platform/redaction`, `platform/logging`, `platform/tracing`, and `platform/metrics` implementation classes. Optional integration uses existing contracts, tag contributions from selected modules, and Foundation noop fallbacks. Compile-time import rules remain governed by `docs/architecture/DEPENDENCIES.md`.

## Consequences

### Positive consequences

- One deterministic PSR-15 chain preserves ordinary short-circuit response decorators and keeps error conversion outside their response-unwind path.
- `HttpKernel` observes only finalized response state, including selected Problem Details responses, without introducing another observer pipeline.
- Distinct stateless completion services preserve single-purpose DI boundaries, independent failure isolation, and Kernel-owned UoW/reset semantics.
- Explicit PSR-17 selection supports one shared factory or two distinct factories while avoiding a mandatory vendor implementation.
- Canonical declarative definitions and later-binding-wins semantics remain identical in source and compiled containers.
- Entry-point readiness rejects missing or mismatched selected implementations before HTTP request construction without modifying Kernel driver resolution.
- HTTP access-log events reuse the producer-neutral logging sink and its redaction policy without importing concrete Logging implementations.

### Trade-offs

- The consuming HTTP application or distribution must install compatible PSR-7/17 packages and provide complete bindings for `external` selection.
- A built-in selection whose vendor classes are not autoloadable can be represented declaratively but cannot pass HTTP executable readiness.
- Middleware that short-circuits within a post slot can prevent inner middleware from running, as required by standard PSR-15 semantics.
- Inbound traceparent extraction cannot automatically establish a request span's parent without a future change to the tracing port contract.
- The baseline has no routing-derived access-log template: `route_template` remains `unknown` until a separate explicit integration contract exists.

## Rejected alternatives

### Introduce a generic RuntimeEntrypointGuard or new HTTP capability registry

Rejected because Kernel already owns the runtime-driver matrix while platform-specific executability belongs to the owning entrypoint. Another generic guard or capability marker would conflate driver validity with module, factory, or transport availability.

### Add a separate middleware registry, sorter, or duplicate deduplication layer

Rejected because `ReservedTags`, `TagRegistry::all()`, and `HttpMiddlewareStackBuilder` already define discovery, ordering, and effective slot participation. A parallel registry would risk different source/compiled ordering and break first-occurrence semantics.

### Place response decorators after request-phase middleware or run an observer pipeline

Rejected because post middleware would be skipped on ordinary early returns and a second completion chain could observe intermediate responses. The selected topology retains PSR-15 response unwinding; `HttpKernel` alone owns final-response observability.

### Let middleware or individual recorders own UnitOfWork and reset hooks

Rejected because Kernel already provides `beginUnitOfWork()` / `afterUnitOfWork()` and reset discipline. A duplicate lifecycle would risk stale context, double completion, and changed failure precedence.

### Require one concrete PSR-7/17 package or infer the best installed implementation

Rejected because a mandatory implementation or vendor scan would make installed Composer packages silently influence runtime selection. Arbitrary configured factory FQCNs, `auto` selection, reflection-based fallback construction, package-local PSR message/factory implementations, and Kernel-owned provider selection are likewise excluded.

### Reject every later factory binding or invent an HTTP definition-conflict policy

Rejected because Foundation's canonical later-binding-wins behavior must remain intact. HTTP verifies the resulting selected class identity and PSR-17 contracts at readiness; it does not override provider precedence.

### Let external selection register fallback factories or dictate application providers

Rejected because `external` means both PSR-17 interface bindings are owned by the consuming application or distribution. A package-owned replacement or prescribed registration mechanism would contradict that explicit boundary.

### Infer routing metadata from HTTP requests for completion logging

Rejected because raw paths, request attributes, and untrusted route parameters are not reliable canonical route templates. The safe producer value is `route_template='unknown'` until routing supplies an explicit contract.

### Write HTTP access logs through a separate logging backend or redactor

Rejected because `LoggerInterface` and the producer-neutral Logging sink already own admission, redaction, and output. A direct `platform/logging` implementation dependency or parallel log pipeline would duplicate policy and weaken package boundaries.

### Store request spans, Stopwatch tokens, or response state in shared services

Rejected because request completion and reentrant pipeline handling require invocation-local state. Shared mutable caches or extra reset registrations would violate the existing lifecycle model.

## Non-goals

This decision does not implement routing or system endpoints, request-from-globals transport adapters, or SAPI response emission. It does not perform application/distribution Composer package installation or introduce a new driver matrix, HTTP capability registry, alternate DI container, PSR request/response implementation, provider-discovery mechanism, runtime factory fallback, or additional middleware tags. It does not define a second error normalization, Problem Details renderer, redaction owner, logging backend, metrics collector, span-parent linkage mechanism, observer registry, UnitOfWork lifecycle, or reset implementation. It does not infer route templates or implement security headers, CORS, uploads, rate limiting, or other future HTTP middleware behavior.

## Verification

Verification must cover the exact outer-to-inner pipeline order, priority-`1000` selected/unselected error boundary, tagged priority and manual append behavior, same-slot deduplication, cross-slot rejection, toggle metadata validation, normal and early-return decorator unwind, and Throwable precedence. It must separately verify that outer error conversion completes through `HttpKernel` finalization, not through an additional post-decorator traversal.

Source and compiled container tests must compare service definitions, effective aliases, factory sharing/distinction, final provider overrides, middleware tag metadata and ordering, terminal handling, and HTTP lifecycle outcomes. Factory readiness tests must distinguish declarative selection from missing autoloadable classes, missing external ports, incompatible implementations, and overwritten built-in identities; they must confirm failure before UnitOfWork, `HttpKernel`, terminal construction, and pipeline execution. Completion tests must cover safe final-response metrics/logging/span handling, independent optional failures, primary Throwable precedence, and the fixed routing-free `route_template` value.

## Related SSoT

- [HTTP Middleware Wiring SSoT](../ssot/http-middleware-wiring.md)
- [HTTP Middleware Catalog SSoT](../ssot/http-middleware-catalog.md)
- [Tag Registry](../ssot/tags.md)
- [DI Tags and Middleware Ordering SSoT](../ssot/di-tags-and-middleware-ordering.md)
- [Runtime Container Definitions (SSoT)](../ssot/runtime-container-definitions.md)
- [Config and env SSoT](../ssot/config-and-env.md)
- [Runtime Drivers SSoT](../ssot/runtime-drivers.md)
- [UoW and Reset Contracts SSoT](../ssot/uow-and-reset-contracts.md)
- [UnitOfWork Outcome Policy SSoT](../ssot/uow-outcome-policy.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](../ssot/observability.md)
- [Logging Policy SSoT](../ssot/logging-policy.md)
- [Problem Details SSoT](../ssot/problem-details.md)
- [Artifacts and Fingerprint Behavior](../ssot/artifacts-and-fingerprint.md)

## Related ADRs

- [ADR-0014: DI container, tags, deterministic ordering, and reset orchestration](./ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md)
- [ADR-0020: Kernel runtime UnitOfWork SPI](./ADR-0020-kernel-runtime-uow-spi.md)
- [ADR-0022: UnitOfWork result and outcome policy](./ADR-0022-unit-of-work-result-outcome-policy.md)
- [ADR-0027: Runtime driver resolution and compatibility matrix](./ADR-0027-runtime-driver-resolution.md)
- [ADR-0029: Kernel compiled container artifact](./ADR-0029-kernel-container-compile-artifact.md)
- [ADR-0030: Canonical Runtime Container Definitions](./ADR-0030-canonical-runtime-container-definitions.md)
- [ADR-0037: Platform logging PSR-3 backend and sink safety](./ADR-0037-platform-logging-psr3.md)
- [ADR-0038: Platform tracing W3C traceparent propagation](./ADR-0038-platform-tracing-w3c-propagation.md)
- [ADR-0040: Platform Problem Details HTTP adaptation](./ADR-0040-platform-problem-details-http-adaptation.md)
