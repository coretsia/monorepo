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

# HTTP Middleware Wiring SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: platform/http
```

## Scope

This document is the Single Source of Truth for the `platform/http` execution semantics of the existing nine HTTP middleware slots: canonical PSR-15 composition, deterministic tagged and manual service-id ordering, HTTP-owned tag metadata, response decoration, outermost error-boundary placement, and the boundary between middleware execution and `HttpKernel` final-response completion.

The implementation boundary is:

```text
packages/platform/http/src/Middleware/HttpMiddlewareStackBuilder.php
packages/platform/http/src/Middleware/Pipeline.php
packages/platform/http/src/Provider/HttpServiceFactory.php
packages/platform/http/src/Provider/HttpServiceProvider.php
packages/platform/http/src/HttpKernel.php
packages/platform/http/src/Response/HttpResponseFinalizer.php
packages/platform/http/src/Observability/HttpRequestMetricsRecorder.php
packages/platform/http/src/Observability/HttpAccessLogWriter.php
packages/platform/http/src/Observability/HttpRequestSpanRecorder.php
```

This document specifies HTTP wiring and completion behavior, not a new tag registry, container, UnitOfWork implementation, error-normalization pipeline, or HTTP error renderer.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

A selected HTTP target MUST execute one deterministic PSR-15 middleware chain, preserve applicable response decorators for ordinary short-circuit responses, and observe the final HTTP result only after the entire chain has returned.

The runtime MUST reuse Foundation tagged-service discovery and declarative DI, Kernel-owned UnitOfWork hooks, and the optional canonical Problem Details boundary without introducing another observer pipeline or request lifecycle.

## Authority and source-of-truth boundaries (MUST)

- `docs/ssot/tags.md` owns the reserved HTTP middleware tag names. `Coretsia\Foundation\Tag\ReservedTags` is their sole code-level identifier registry.
- `docs/ssot/http-middleware-catalog.md` owns middleware taxonomy, catalog placement, optional owner participation, and cross-cutting HTTP policy. This document owns the concrete composition and execution semantics of those slots.
- `docs/ssot/di-tags-and-middleware-ordering.md` owns `TagRegistry::all()` ordering and Foundation's first-registration-wins tag deduplication.
- `docs/ssot/runtime-container-definitions.md` owns declarative container operations, service binding precedence, and source/compiled container construction semantics.
- `docs/ssot/uow-and-reset-contracts.md` and `docs/ssot/uow-outcome-policy.md` own Kernel UnitOfWork and completion contracts. HTTP consumes those contracts without implementing its own hooks or reset orchestration.
- `docs/ssot/observability.md` owns canonical metric and span names, allowed labels, and disclosure policy. `docs/ssot/problem-details.md` owns canonical error response adaptation and rendering.
- `docs/architecture/DEPENDENCIES.md` governs compile-time package dependencies. HTTP wiring MUST NOT import concrete implementations from optional `platform/*` runtime packages.

## Core invariants (MUST)

- `platform/http` MUST reuse the existing nine `ReservedTags::HTTP_MIDDLEWARE_*` identifiers without adding aliases, competing tag names, or another slot registry.
- The effective target-local `http.middleware` configuration contains exactly nine manual append lists. These lists MUST NOT replace tagged discovery or introduce skeleton/application defaults for middleware supplied by optional packages.
- A middleware service MUST participate in at most one effective HTTP slot. Repetition within its own slot follows first-occurrence semantics; participation in different slots is invalid.
- Tag ordering, middleware instance resolution, PSR-15 traversal, and HTTP request completion have separate owners. None may recreate another owner's registry or lifecycle.
- The resolved pipeline and HTTP completion services MUST have equivalent construction and execution semantics in source and compiled containers.
- Per-request requests, responses, Throwables, traversal positions, timing tokens, and span handles MUST remain invocation-local, not shared mutable DI service state.

## Canonical nine-slot taxonomy (MUST)

The only HTTP middleware slots are:

```text
http.middleware.system_pre
http.middleware.system
http.middleware.system_post
http.middleware.app_pre
http.middleware.app
http.middleware.app_post
http.middleware.route_pre
http.middleware.route
http.middleware.route_post
```

The `system`, `app`, and `route` groups define the request-processing phases. Each group's `_pre` slot precedes its main request slot. Each `_post` slot is a PSR-15 response decorator, not a Kernel final-response observer.

`platform/http` MUST use the corresponding `ReservedTags::HTTP_MIDDLEWARE_*` constants in production registrations and discovery. This document neither declares new tag identifiers nor changes the ownership of existing registrations.

## Canonical PSR-15 topology (MUST)

The logical request-phase sequence is:

```text
system_pre -> system -> app_pre -> app -> route_pre -> route -> terminal
```

To preserve response decoration even when a request-phase middleware returns early, `HttpMiddlewareStackBuilder` MUST construct the following exact outer-to-inner chain of *effective* middleware service ids:

```text
[priority-1000 Problem Details error boundary, when selected]
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

Within every slot, multiple middleware execute in their effective slot order. The post slots are entered before the request-phase slots because they wrap the request chain. For a normally returned `ResponseInterface`, their response decoration unwinds in the reverse order:

```text
terminal or ordinary short-circuit response
  -> route_post
  -> app_post
  -> system_post
  -> [selected error boundary passes through]
  -> HttpKernel final-response completion
```

The diagrams express nesting and return flow; they MUST NOT be interpreted as authorization to re-sort the nine reserved tags or move a post decorator into a request-phase slot.

`Pipeline` MUST execute the resolved `MiddlewareInterface` sequence through PSR-15 `RequestHandlerInterface`, using invocation-local traversal state and the selected terminal handler. The baseline terminal fallback returns a safe empty-body HTTP `404` before routing integration. Pipeline traversal MUST NOT discover services, sort tags, manage UnitOfWork, or store a mutable cursor across invocations.

## Outermost error boundary (MUST)

`Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware` is the only recognized priority-`1000` error boundary. It MUST be registered under `ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE` with priority `1000` and metadata exactly `[]`, only when `platform.problem-details` is explicitly selected in a compatible HTTP target.

- The priority-`1000` position is reserved; no other `system_pre` service may have priority `1000` or greater.
- The designated boundary MUST NOT be registered in another HTTP slot, at another priority, with toggle metadata, or through a manual append list.
- Package installation alone MUST NOT select `platform.problem-details` or contribute its middleware to an unselected target.
- When selected, the boundary MUST be outside all response decorators and request-phase middleware, despite its registration using the existing `system_pre` tag.
- When not selected, downstream Throwables MUST propagate according to ordinary PSR-15 semantics; HTTP MUST NOT synthesize a replacement boundary.

Error normalization remains owned by the canonical `ErrorHandlerInterface` flow; `platform/problem-details` owns descriptor adaptation and HTTP rendering. `platform/http` MUST NOT construct another handler, mapper registry, error renderer, or fallback presentation policy.

## Tagged discovery and manual append lists (MUST)

For each of the nine slots, `HttpMiddlewareStackBuilder` MUST enumerate tagged services exclusively through `TagRegistry::all(ReservedTags::HTTP_MIDDLEWARE_*)`. Foundation returns entries sorted by:

```text
priority DESC, service id ASC (byte-order strcmp)
```

`TagRegistry` retains the first registration for each `(tag, serviceId)` pair; subsequent registrations of that pair do not replace its priority or metadata. HTTP consumers MUST preserve this discovery order and MUST NOT add a competing sort or tag registry.

The effective `http.middleware.<slot>` list is a manually configured append list for that same slot. Its entries MUST be a list of valid middleware class-strings, interpreted as exact canonical DI service ids. For each slot, effective tagged entries come first, followed by manual ids in configured list order.

- The first effective occurrence of a service id within a slot wins. A repeated tagged/manual or manual/manual occurrence in the same slot MUST NOT execute twice.
- An effective service id already participating in a different slot MUST be rejected, including duplicates across tagged and manual origins. Disabled tagged entries do not participate in the effective chain.
- Tagged service ids and manual class-string ids MUST satisfy their respective HTTP validation policies; malformed ids, unknown slots, malformed lists, or invalid metadata MUST fail deterministically.
- Manual ids MUST NOT require an explicitly registered definition merely to be listed. The canonical container decides whether an explicit binding or Foundation-permitted concrete-class autowiring can resolve them.
- `HttpMiddlewareStackBuilder` MUST NOT instantiate, reflect over, or probe candidate middleware services. `HttpServiceFactory` resolves each effective id through the existing `Psr\Container\ContainerInterface` and requires a `MiddlewareInterface` instance before constructing `Pipeline`.

## HTTP tag metadata and conditional participation (MUST)

HTTP middleware tag metadata has exactly two allowed shapes:

```php
[]
['toggle' => 'http.tracing.enabled']
```

The non-empty form MUST contain exactly the single string key `toggle`, whose value is one of the existing validated boolean configuration paths:

```text
http.correlation.enabled
http.tracing.enabled
http.metrics.enabled
http.access_log.enabled
http.proxy.enabled
http.context.enrich.enabled
http.hosts.enabled
http.request.json.enabled
```

Unknown keys, extra metadata fields, unsupported toggle names, or missing/non-boolean effective toggle values MUST be rejected. A disabled toggle excludes that tagged entry from the effective chain; it MUST NOT change the declarative provider definitions or tag registrations. Metadata and reserved error-boundary constraints MUST be validated even for disabled entries.

The baseline `system_pre` tag contributions are:

- `TraceContextMiddleware` at priority `930`, conditional on `http.tracing.enabled`;
- `TrustedProxyMiddleware` at priority `880`, conditional on `http.proxy.enabled`;
- `RequestContextMiddleware` at priority `870`, conditional on `http.context.enrich.enabled`;
- `TrustedHostMiddleware` at priority `860`, conditional on `http.hosts.enabled`;
- `RequestBodySizeLimitMiddleware` at priority `830`, unconditional;
- `JsonBodyParserMiddleware` at priority `800`, conditional on `http.request.json.enabled`.

The optional priority-`1000` boundary is governed by explicit module selection, not by an HTTP toggle. Other optional middleware owners contribute their own registrations only under their applicable activation policy. Future-only catalog reservations are not executable registrations.

`http.middleware.auto.*` flags MUST NOT exist. `http.correlation.enabled`, `http.metrics.enabled`, and `http.access_log.enabled` govern their respective HTTP completion services; their inclusion in the metadata allowlist MUST NOT be interpreted as creating correlation, metrics, or access-log middleware.

## Declarative composition and container parity (MUST)

`HttpServiceProvider` MUST register HTTP-owned services, middleware tags, and the pipeline construction definition through the canonical Foundation declarative DI operations. `HttpMiddlewareStackBuilder` consumes the final `TagRegistry` and validated HTTP configuration; `HttpServiceFactory` constructs `Pipeline` from effective service ids resolved through the final canonical container.

- Source and compiled containers MUST retain equivalent middleware service definitions, tag metadata and priorities, effective ordering, factory bindings, and terminal-handler resolution.
- Canonical Foundation service and alias precedence, including later-binding-wins semantics, MUST remain unchanged. HTTP MUST NOT introduce a second definition-conflict policy.
- The selected target's `http.factory` and effective PSR-17 bindings MUST be validated through the HTTP-owned entrypoint readiness boundary before resolving executable HTTP services. Installed but unselected implementations MUST NOT alter the selected factory or provide an implicit fallback.
- The selected HTTP target and effective module participation MUST govern optional middleware provider registration. An unselected optional module MUST NOT contribute a middleware merely because its package is installed.
- Runtime-only closure definitions, filesystem discovery, reflection-based factory selection, alternate containers, observer registries, or another middleware pipeline MUST NOT be introduced.

## HttpKernel lifecycle and final-response completion (MUST)

`HttpKernel` is the sole HTTP request lifecycle and final-response coordinator. It consumes the existing `KernelRuntimeInterface::beginUnitOfWork('http')` and matching `afterUnitOfWork()` hooks with the exact returned handle. Kernel owns hook execution, context lifetime, and reset discipline; HTTP MUST NOT reimplement them.

After a successful UnitOfWork begin, the canonical completion sequence is:

```text
KernelRuntimeInterface::beginUnitOfWork('http')
  -> optional request-local Stopwatch start and http.request span start
  -> complete PSR-15 Pipeline::handle() (including any selected error boundary)
  -> HttpResponseFinalizer::finalize() for the returned response
  -> derive final status and outcome
  -> optional Stopwatch stop
  -> HttpRequestMetricsRecorder::record()
  -> HttpAccessLogWriter::write()
  -> HttpRequestSpanRecorder::finish()
  -> KernelRuntimeInterface::afterUnitOfWork() and Kernel-owned reset
  -> return final ResponseInterface or rethrow primary Throwable
```

A response returned by the selected outermost error boundary is a completed pipeline response and MUST pass through the same `HttpResponseFinalizer` and request-level observability sequence as any successful or short-circuit response. The finalizer owns optional application of the configured Kernel correlation response header through immutable PSR-7 `withHeader()`.

Only the status of the successfully finalized response determines the HTTP result: statuses below `400` are `success`; finalized `400..599` responses are `handled_error`. An uncaught pipeline or response-finalization Throwable is `fatal_error`, with observability status fixed to `unknown`. A merely intermediate pipeline response MUST NOT be reported as final if response finalization fails.

`HttpKernel` MUST derive one bounded request method, final status, outcome, and non-negative duration for all enabled request-level recorders. Metrics and access logging MUST consume these final values, not an intermediate response, raw request data, or a middleware-local outcome. Recording failures MUST be isolated so that they do not replace the final response or primary Throwable. `afterUnitOfWork()` MUST still be attempted once after every successful begin, following request-level completion and before Kernel-owned reset.

## Dedicated HTTP completion services (MUST)

The four HTTP-owned services are distinct, stateless DI services and MUST NOT be registered in any of the nine HTTP middleware slots:

- `HttpResponseFinalizer` owns correlation response-header finalization through the existing `CorrelationIdProviderInterface`.
- `HttpRequestMetricsRecorder` owns final-response request metrics through `MeterPortInterface`, using only approved bounded labels.
- `HttpAccessLogWriter` owns at most one safe access-log operation through the existing PSR-3 `LoggerInterface`, without exporting raw request values.
- `HttpRequestSpanRecorder` owns the request-local `http.request` span start and finish through `TracerPortInterface` and `SpanInterface`.

These services MUST NOT become PSR-15 response decorators, create a parallel post-response observer chain, or retain request-local response/span/timer state in shared services. Inbound `TraceContextMiddleware` remains a separate PSR-15 concern and does not create a second tracing lifecycle.

## Short-circuit and Throwable semantics (MUST)

- A request-phase middleware MAY return a `ResponseInterface` without invoking its next handler. All post decorators already entered outside that middleware MUST still decorate that ordinary returned response in canonical unwind order.
- A post decorator MAY itself short-circuit. Only post decorators already entered outside that point may decorate the returned response; middleware not entered MUST NOT execute.
- A downstream `Throwable` MUST propagate unchanged through the ordinary PSR-15 chain. Post decorators MUST NOT be assumed to receive a `ResponseInterface` when their downstream handler throws.
- When an explicitly selected outermost Problem Details boundary captures a downstream Throwable, its resulting response is produced outside the ordinary post-decorator unwind. It MUST still pass through `HttpKernel` final-response completion.
- If the boundary cannot produce a response and propagates a `Throwable`, `HttpKernel` MUST retain the primary Throwable and preserve Kernel completion precedence. HTTP middleware composition MUST NOT add another exception normalization or rendering path.

## Security and state invariants (MUST)

Final-response metrics, access logs, and span attributes MUST use safe, bounded canonical fields. Raw paths, query strings, headers, cookies, credentials, request or response bodies, arbitrary context, and exception messages MUST NOT be added merely because a middleware or response was observed.

`Pipeline`, `HttpMiddlewareStackBuilder`, the four completion services, and `HttpKernel` MUST NOT retain mutable per-request state or install additional reset handlers. Source and compiled execution MUST preserve response identity, ordinary short-circuit decoration, and original Throwable precedence under the same effective configuration.

## Enforcement rails

Integration verification MUST cover deterministic per-slot ordering and deduplication, cross-slot failures, exact metadata and toggles, selected/unselected priority-`1000` boundary placement, returned short-circuit response unwind, exception conversion as a separate path, final-response completion and outcome classification, and source/compiled container parity.

Tests MUST distinguish an intermediate middleware result from the completed `HttpKernel` result and MUST NOT treat optional observability failures as permission to change responses or primary Throwables.

## Non-goals

This SSoT does not introduce or redefine:

- reserved tags, priority registries, config roots, or `http.middleware.auto.*` flags;
- route matching, application endpoints, transport-specific request creation, or response emission;
- a second DI container, middleware resolver, observer pipeline, lifecycle hook registry, or reset implementation;
- a replacement canonical error handler, `ErrorDescriptor` model, Problem Details renderer, or diagnostic redaction boundary;
- metrics aggregation, logging backend, tracing exporter, span-parent linkage contract, or mutable request cache;
- optional middleware implementations or automatic activation based on installed packages.

## Cross-references

- [SSoT Index](./INDEX.md)
- [HTTP Middleware Catalog SSoT](./http-middleware-catalog.md)
- [Tag Registry](./tags.md)
- [DI Tags and Middleware Ordering SSoT](./di-tags-and-middleware-ordering.md)
- [Runtime Container Definitions (SSoT)](./runtime-container-definitions.md)
- [Config and env SSoT](./config-and-env.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [UnitOfWork Outcome Policy SSoT](./uow-outcome-policy.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Problem Details SSoT](./problem-details.md)
- [Compile-time package dependencies](../architecture/DEPENDENCIES.md)
