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

# coretsia/platform-http

`platform/http` is the Kernel-integrated PSR-7/15 HTTP request runtime for Coretsia Framework. It provides a deterministic PSR-15 middleware pipeline, an explicit PSR-17 response/stream factory selection contract, a safe pre-routing terminal fallback, HTTP-owned request policies, and final-response completion through the existing Kernel UnitOfWork and Foundation ports.

Scope: explicit target-local HTTP module participation and factory readiness, declarative HTTP DI bindings, nine reserved middleware slots, tagged discovery and manual middleware append lists, conditional request safety, PSR-15 response decoration, request-local inbound W3C trace context, HTTP error descriptors, correlation response headers, and bounded request-level observability after the completed pipeline.

Out of scope: SAPI request creation and response emission, a packaged front controller, route matching and system endpoints, application/distribution Composer installation and DI provider registration, optional backend implementations, exception normalization and HTTP error rendering, Kernel driver-matrix policy, additional reset orchestration, and future HTTP features.

This README is a consumer-oriented package summary. The normative wiring, lifecycle, and dependency contracts remain in the linked SSoT and ADR documents.

## Package identity

- Path: `packages/platform/http`
- Package id: `platform/http`
- Composer name: `coretsia/platform-http`
- Module id: `platform.http`
- Namespace: `Coretsia\Platform\Http\*` (PSR-4: `src/`)
- Kind: runtime
- Config root: `http`
- Module class: `Coretsia\Platform\Http\Module\HttpModule`
- Declarative DI provider: `Coretsia\Platform\Http\Provider\HttpServiceProvider`

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions MUST NOT be used.

Installing the Composer package makes its classes and metadata available; it does not select the `platform.http` module or activate HTTP handling. The consuming target must explicitly select `platform.http` through the existing Kernel module-selection process. The module declares only `core.foundation` and `core.kernel` as required runtime modules. Selection of optional Logging, Tracing, Metrics, or Problem Details modules is independent.

## Dependency policy

This package is runtime-safe and transport-neutral.

- Depends on:
  - `core/contracts`
  - `core/foundation`
  - `core/kernel`
  - `psr/http-message` (`^2.0`) for PSR-7 interfaces
  - `psr/http-factory` (`^1.0`) for PSR-17 interfaces
  - `psr/http-server-middleware` (`^1.0`) and `psr/http-server-handler` (`^1.0`) for PSR-15 interfaces
  - `psr/container` (`^2.0`) and `psr/log` (`^3.0`) for canonical container and logging ports
- Forbidden:
  - any `core/*` package other than `core/contracts`, `core/foundation`, and `core/kernel`
  - other `platform/*` packages
  - `integrations/*`
  - `enterprise/*`
  - `devtools/*`
  - `presets/*`

Production Composer requirements include PHP `^8.4` and the canonical release-line constraints for the three Coretsia core packages. The PSR requirements above are interface-only; `platform/http` MUST NOT require a concrete PSR-7/17 implementation or provide its own PSR message classes. The consuming application or distribution installs a compatible implementation. Neither HTTP bootstrap nor container compilation runs Composer.

`platform/http` MUST NOT import or consume repository machinery under `tools/**`. It consumes the existing Kernel runtime-driver resolver without owning the driver matrix and MUST NOT depend on concrete `platform/errors`, `platform/problem-details`, `platform/redaction`, `platform/logging`, `platform/tracing`, or `platform/metrics` implementations. Optional integrations use existing contracts and tags, not direct source dependencies or required runtime modules.

## Runtime responsibilities

`platform/http` provides:

- `HttpKernelInterface` as the PSR-15 HTTP request-handler entrypoint, implemented by `HttpKernel`;
- `HttpRuntimeEntrypointGuard` for selected-module, Kernel-driver, and effective PSR-17 factory readiness;
- `HttpServiceProvider` for canonical declarative definitions and tags, and `HttpServiceFactory` for canonical-container pipeline assembly;
- `HttpMiddlewareStackBuilder` and `Pipeline` for one deterministic middleware chain with invocation-local traversal;
- `FallbackRouterHandler` as the safe, routing-free terminal `RequestHandlerInterface`;
- six implemented `system_pre` middleware and their HTTP-owned request-safety policies;
- the four stateless HTTP response-finalization and request-observability services;
- `HttpExceptionMapper` for the three HTTP-owned safe failure types through the existing `error.mapper` tag.

The package does not create a separate module resolver, DI layer, middleware registry, exception handler, request context store, or UnitOfWork lifecycle.

## PSR-17 factory selection

The validated, target-local `http.factory` value is the sole explicit factory selector. Its default is `external`; the exact allowed values are:

```text
external
nyholm
guzzlehttp
laminas
slim
```

`HttpServiceProvider` consumes the effective Phase B `http` configuration subtree through `ContainerDefinitionContext::configRoot('http')`. For each built-in selection, it contributes only the corresponding concrete class-service definition or definitions and aliases for `Psr\Http\Message\ResponseFactoryInterface` and `Psr\Http\Message\StreamFactoryInterface` through the existing `ContainerDefinitionBuilder`:

- `nyholm`: `nyholm/psr7`; `Nyholm\Psr7\Factory\Psr17Factory` provides both factory interfaces as one shared service.
- `guzzlehttp`: `guzzlehttp/psr7`; `GuzzleHttp\Psr7\HttpFactory` provides both factory interfaces as one shared service.
- `laminas`: `laminas/laminas-diactoros`; `Laminas\Diactoros\ResponseFactory` and `Laminas\Diactoros\StreamFactory` provide two distinct services.
- `slim`: `slim/psr7`; `Slim\Psr7\Factory\ResponseFactory` and `Slim\Psr7\Factory\StreamFactory` provide two distinct services.

Built-in choices supply both canonical PSR-17 interface aliases without a consumer-owned DI provider for those bindings. Their class names are fixed package-owned descriptors, not configurable FQCNs. Only the selected implementation participates in definition production. Several compatible Composer packages may be installed at once without changing the selected implementation or enabling a fallback.

`http.factory=external` is the explicit application/distribution-owned binding mode. `platform/http` contributes no concrete factory class definitions or replacement aliases in this mode. It still requires both effective PSR-17 interface services; the consumer must provide compatible declarative bindings, using either one implementation or separate implementations as appropriate. This package does not prescribe how a consuming application registers its providers.

There is no `auto` selector, Composer/vendor scan, runtime provider discovery, reflection-based fallback construction, arbitrary configured factory-class selection, second container, or package-local PSR message/factory implementation. A selected factory may be described in declarative definitions even before its concrete Composer classes are available; executability is checked separately.

Canonical source and compiled containers preserve equivalent selected definitions, aliases, class identities, and validation semantics. The target-local `http.factory` value participates in the existing configuration-derived compiled container and artifact fingerprint/invalidation behavior. No factory wiring exists exclusively at runtime.

PSR-17 factory selection supplies response and stream construction dependencies; it does not create a PSR-7 server request from SAPI globals, select a transport adapter, emit an HTTP response, or provide a front controller.

## HTTP readiness boundary

After canonical provider composition and **before** resolving `HttpKernelInterface` or constructing an executable HTTP middleware pipeline, the owning HTTP entrypoint must invoke `HttpRuntimeEntrypointGuard` with the effective `ConfigRepositoryInterface`, selected `ModulePlan`, explicit owner-supplied `RuntimeDriverContributions`, and final canonical `ContainerInterface`.

The guard verifies that `platform.http` is selected and delegates the Kernel driver matrix to the existing `RuntimeDriverResolver`. Caller-supplied contributions are preserved, including selected Worker contributions; empty contributions are appropriate only when none exist. HTTP does not redefine Kernel driver compatibility or derive contributions from package installation.

For built-in factory selections, readiness requires that the exact selected factory classes are Composer-autoloadable, that both effective PSR-17 interface services resolve through the canonical container and implement the required interfaces, and that their concrete classes match the explicit selection. For `external`, the final services must implement the two PSR-17 interfaces without a built-in concrete-class identity restriction.

Missing selected implementations, missing effective bindings, incompatible services, and mismatched built-in identities fail deterministically before HTTP construction or request execution. Canonical container completeness and resolution own the dependency-graph failure semantics. Foundation's later-binding-wins service/alias policy is unchanged: an overriding binding is not rejected merely for being later, but a final binding that violates the configured built-in identity is rejected at HTTP readiness. No other installed factory is substituted.

HTTP module participation, Kernel driver compatibility, effective factory readiness, and transport-specific executable readiness are distinct checks. Transport adapters and their readiness remain the owning HTTP entrypoint's responsibility.

## PSR-15 pipeline and middleware wiring

`HttpMiddlewareStackBuilder` consumes the final Foundation `TagRegistry` and effective `http.middleware` configuration. It reuses exactly the nine existing `ReservedTags::HTTP_MIDDLEWARE_*` identifiers:

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

The logical request-phase order is `system_pre`, `system`, `app_pre`, `app`, `route_pre`, `route`, followed by the terminal handler. `_post` slots are PSR-15 response decorators, not final-response observers. The actual outer-to-inner middleware chain is:

```text
[selected priority-1000 Problem Details error boundary]
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

Within each slot, `TagRegistry::all()` supplies tagged service ids ordered by priority descending and service id ascending (byte-order). Configured manual class-string ids from `http.middleware.<slot>` are appended after tagged entries in their original list order. The first occurrence of an effective id within a slot wins; effective cross-slot duplication is invalid. The nine manual append lists default to empty, and they do not replace tagged discovery.

Manual entries are exact canonical DI service ids. They may resolve through explicit definitions or Foundation-permitted concrete-class autowiring; an explicit registration is not required just to list an id. `HttpServiceFactory` resolves every effective middleware id through the final `Psr\Container\ContainerInterface` and requires a `Psr\Http\Server\MiddlewareInterface` instance. Missing/unresolvable services or incompatible instances fail deterministically through canonical resolution and HTTP instance validation. HTTP does not independently inspect service definitions, infer autowiring eligibility, instantiate classes by reflection, or create another resolution policy.

HTTP tag metadata is exactly `[]` or a single-key map `['toggle' => '<validated HTTP boolean path>']`. The builder validates permitted metadata, effective toggles, service ids, manual list shapes, and cross-slot participation. Conditional participation excludes disabled tagged middleware at stack assembly; it does not remove declarative registrations. There are no `http.middleware.auto.*` flags.

An explicitly selected, compatible `platform.problem-details` module may contribute `HttpErrorHandlingMiddleware` to `system_pre` at reserved priority `1000` with metadata `[]`. The builder places it outermost, outside every post decorator; no other service may occupy or displace the reserved position. Its presence is governed by explicit module selection, not Composer installation alone. `platform/http` never constructs that external middleware or a replacement error renderer.

`Pipeline` executes each entered PSR-15 middleware through the canonical handler contract with no shared mutable traversal cursor. When a request-phase middleware returns a response early, the outer post decorators already entered still decorate it, unwinding in `route_post`, `app_post`, `system_post` order. A post decorator may itself short-circuit, in which case only decorators already outside it execute on return. A downstream `Throwable` propagates unchanged through ordinary middleware; if the selected outer error boundary converts it to a response, that response does not pass through the decorators again. `HttpKernel` still finalizes it.

The baseline terminal `FallbackRouterHandler` constructs a safe `404` with an empty body using the canonical PSR-17 factories. It provides no route matching, route metadata, reserved-path dispatch, or system endpoint behavior.

## Configuration

The HTTP config root is:

```text
http
```

The defaults file is:

```text
config/http.php
```

It returns the `http` subtree only.

It MUST NOT wrap the subtree in a repeated root key such as:

```php
['http' => [...]]
```

Baseline defaults include:

```php
[
    'factory' => 'external',
    'middleware' => [
        'system_pre' => [],
        'system' => [],
        'system_post' => [],
        'app_pre' => [],
        'app' => [],
        'app_post' => [],
        'route_pre' => [],
        'route' => [],
        'route_post' => [],
    ],
    'correlation' => [
        'enabled' => true,
        'header_name' => 'X-Correlation-Id',
    ],
    'tracing' => [
        'enabled' => true,
    ],
    'metrics' => [
        'enabled' => true,
    ],
    'access_log' => [
        'enabled' => true,
    ],
    'proxy' => [
        'enabled' => false,
        'trusted_proxies' => [],
    ],
    'context' => [
        'enrich' => [
            'enabled' => true,
        ],
    ],
    'hosts' => [
        'enabled' => false,
        'allowed' => [],
    ],
    'request' => [
        'max_body_bytes' => 1048576,
        'json' => [
            'enabled' => true,
            'max_depth' => 32,
        ],
    ],
]
```

Important config rules:

- `http.factory` is `external` by default and accepts only `external`, `nyholm`, `guzzlehttp`, `laminas`, or `slim`; installed packages never determine the choice implicitly.
- `http.middleware.<slot>` contains manually appended middleware class-string service ids, not replacements for Foundation `TagRegistry` registrations. All nine lists default to `[]`.
- `http.correlation.enabled=true` enables the completed-response correlation header; `http.correlation.header_name=X-Correlation-Id` is the default header name. Kernel owns the correlation identity.
- `http.tracing.enabled=true` controls both inbound `TraceContextMiddleware` participation and the optional `http.request` span.
- `http.metrics.enabled=true` and `http.access_log.enabled=true` control request-level completion observability outside the PSR-15 pipeline.
- `http.proxy.enabled=false` by default; `http.proxy.trusted_proxies` is an empty list of trusted immediate-peer IP/CIDR entries.
- `http.context.enrich.enabled=true` enables bounded request-derived enrichment through existing `ContextKeys`.
- `http.hosts.enabled=false` by default; `http.hosts.allowed` is an empty host allowlist.
- `http.request.max_body_bytes=1048576` by default; the validated byte budget must be from `1` to `16777216`.
- `http.request.json.enabled=true` enables eligible JSON parsing; `http.request.json.max_depth=32` by default and must be from `1` to `512`.
- Unknown configuration keys, invalid values, and reserved `@*` directives are rejected through the existing Phase B configuration rules. HTTP-owned policies enforce additional semantic header, host, and proxy constraints.
- No `http.middleware.auto.*` flags, concrete implementation class names, route matching, transport adapters, or future-only HTTP feature defaults are introduced.

The six implemented baseline `system_pre` registrations, in canonical priority order, are:

- `TraceContextMiddleware` — `930`, `['toggle' => 'http.tracing.enabled']`.
- `TrustedProxyMiddleware` — `880`, `['toggle' => 'http.proxy.enabled']`.
- `RequestContextMiddleware` — `870`, `['toggle' => 'http.context.enrich.enabled']`.
- `TrustedHostMiddleware` — `860`, `['toggle' => 'http.hosts.enabled']`.
- `RequestBodySizeLimitMiddleware` — `830`, unconditional metadata `[]`.
- `JsonBodyParserMiddleware` — `800`, `['toggle' => 'http.request.json.enabled']`.

`ConfigKernel` Phase B supplies the validated target-local `http` subtree to canonical declarative DI through `ContainerDefinitionContext`. Other cataloged HTTP-owned middleware entries are future-only reservations, not implementations or default registrations. Optional middleware owners control their own module selection and DI contributions.

## HttpKernel and UnitOfWork lifecycle

`HttpKernel::handle()` accepts one `Psr\Http\Message\ServerRequestInterface` and coordinates exactly one complete PSR-15 pipeline execution. It calls `KernelRuntimeInterface::beginUnitOfWork('http')` before the pipeline and attempts one matching `afterUnitOfWork()` with the exact handle after HTTP completion. The Kernel owns hook execution, context lifetime, and reset; HTTP never registers a second reset pipeline or implements UnitOfWork internals.

The fixed completion sequence after successful begin is:

```text
KernelRuntimeInterface::beginUnitOfWork('http')
  -> request-local Stopwatch start and optional http.request span start
  -> complete PSR-15 Pipeline::handle()
  -> HttpResponseFinalizer::finalize() for a returned response
  -> final HTTP status and outcome derivation
  -> optional request-local Stopwatch stop
  -> HttpRequestMetricsRecorder::record()
  -> HttpAccessLogWriter::write()
  -> HttpRequestSpanRecorder::finish()
  -> KernelRuntimeInterface::afterUnitOfWork() and Kernel-owned reset
  -> return finalized ResponseInterface or rethrow primary Throwable
```

`HttpKernel` retains response, Throwable, timing token, optional `SpanInterface` handle, and canonical method/status/outcome/duration only in invocation-local variables. No request state is retained in shared container services. A valid Stopwatch token is stopped at most once; unavailable timing yields a safe integer zero duration.

A successfully finalized response with status below `400` is `success`; finalized `400..599` responses are `handled_error`, including normal short-circuits and responses returned by the outer error boundary. An uncaught pipeline or finalization Throwable is `fatal_error`, with fixed observability status `unknown` even if an intermediate response had been produced. Only the successfully finalized response supplies the final status. HTTP observations use one bounded method (`GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `TRACE`, `CONNECT`, or `OTHER`), status, outcome, and non-negative duration.

Timing and optional observability failures are independently isolated and cannot replace the final response or earlier primary Throwable. A primary pipeline/finalization Throwable remains authoritative over later completion failures; otherwise Kernel completion failure is preserved. The matching Kernel `afterUnitOfWork()` is attempted after every successful begin, including fatal HTTP completion paths.

## Observability

`HttpServiceProvider` registers four separate, stateless HTTP-owned completion services with explicit constructor dependencies and injects them into `HttpKernel`. None is PSR-15 middleware, appears in the nine HTTP slots, creates another lifecycle hook, or registers independent reset logic.

- `HttpResponseFinalizer` uses `CorrelationIdProviderInterface` to apply the configured correlation header to the fully returned immutable `ResponseInterface`, including ordinary short-circuit and selected Problem Details responses. It neither trusts arbitrary inbound correlation headers nor creates a second correlation identity.
- `HttpRequestMetricsRecorder` uses `MeterPortInterface` to increment `http.request_total` and observe `http.request_duration_ms`, with only the canonical `method`, `status`, and `outcome` labels and integer-millisecond duration. It does not aggregate or export metrics.
- `HttpAccessLogWriter` uses the canonical PSR-3 `LoggerInterface` to emit at most one bounded `http.request.completed` record when enabled. It consumes the final method, status, outcome, duration, optional validated Kernel-owned correlation id, and fixed `route_template='unknown'`. It does not read routes or infer templates from raw URIs, request attributes, or `ContextStore`.
- `HttpRequestSpanRecorder` uses `TracerPortInterface` to start and finish the request-local `http.request` span through the returned `SpanInterface`. It sets only safe, bounded method/status/outcome attributes; it does not record raw HTTP Throwables or retain span handles in service properties.

`TraceContextMiddleware` separately owns inbound W3C `traceparent` extraction through the existing `ContextPropagationInterface`. Accepted trace context remains request-local in the PSR-7 `coretsia.http.trace_context` attribute and is not written to a tracing `ContextStore`. The current `TracerPortInterface` has no explicit parent-context argument, so inbound extraction does not guarantee automatic parent-child linkage for the `http.request` span.

The canonical Foundation noop-safe logging, tracing, and metrics ports preserve HTTP execution when optional backends are not selected. HTTP uses no second logging pipeline, redaction owner, recording tracer, metrics exporter, or external lifecycle telemetry. Kernel-owned UnitOfWork and operation observations remain separate from the HTTP-owned `http.request` span and the two HTTP request metrics.

## Errors

The HTTP-owned `HttpExceptionMapper` implements the existing `ExceptionMapperInterface` and is contributed under `ReservedTags::ERROR_MAPPER` (`error.mapper`) at priority `100` with metadata `[]`. It maps only:

- `BadJsonException` to HTTP `400` with `CORETSIA_HTTP_BAD_JSON`;
- `BodyTooLargeException` to HTTP `413` with `CORETSIA_HTTP_PAYLOAD_TOO_LARGE`;
- `TrustedHostRejectedException` to HTTP `400` with `CORETSIA_HTTP_TRUSTED_HOST_REJECTED`.

Other Throwables return `null` from this mapper. Descriptor codes and messages are fixed and safe; raw parser errors, rejected hosts, request bodies, and untrusted header values are not exported. The canonical Errors subsystem owns exception normalization and mapper resolution; an explicitly selected Problem Details integration owns safe HTTP error adaptation and rendering. Without that outer boundary, downstream Throwables propagate. HTTP does not construct an independent `ErrorHandlerInterface`, exception registry, emergency response renderer, or replacement normalization path.

## Security / Redaction

HTTP request safety uses the selected and validated request policies: immediate-peer trust for forwarded metadata, bounded and canonical host admission, bounded body-size verification (including actual stream bytes), and JSON decoding only for eligible content types within the configured byte and depth limits. `RequestContextMiddleware` alone writes validated request-derived `CLIENT_IP`, `SCHEME`, `HOST`, `PATH`, and `USER_AGENT` to the existing request-local `ContextStore`; those values are not automatically exportable to observability sinks.

Request, response, and exception data must not disclose raw URLs, query strings, headers, cookies, Authorization values, credentials, tokens, payloads, parsed bodies, request-derived identifiers, private context, stack traces, or exception messages through public errors, metrics, access logs, or spans. HTTP emits safe, bounded completion fields only. Canonical PSR-3 Logging owns shared sink-side redaction and serialization; HTTP does not instantiate another redactor or depend directly on a concrete Logging implementation.

A non-seekable inbound request body must arrive complete and unread from its owning transport adapter; already consumed bytes cannot be recovered by HTTP middleware. Accepted bounded body verification and JSON parsing preserve downstream body availability without an unbounded buffering layer, spooling subsystem, or shared request cache. HTTP-owned policies do not rely on superglobals or mutable process-global request state.

## Non-goals

This package does not provide:

- SAPI front controllers, request-from-globals adapters, response emitters, browser-serving instructions, or deployment transport selection;
- route matching, routing artifacts, application routes, system endpoints, or a route-template extraction/handoff contract;
- automatic module activation, Composer installation, arbitrary PSR factory-class configuration, provider scanning, or factory fallbacks;
- consumer-specific DI provider registration, application target setup, or alternate container compilation;
- future-only request-id, maintenance, security-header, HTTPS redirect, CORS, content negotiation, rate limiting, session, authentication, multipart upload, or debugging middleware;
- separate exception normalization, Problem Details rendering, logging backend/redactor, metrics exporter, tracing parent-context registry, or observability observer pipeline;
- any independent Kernel UnitOfWork, reset orchestration, runtime driver matrix, or mutable request-state registry.

## References

- [Coretsia](https://coretsia.dev/)
- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Platform HTTP package source](https://github.com/coretsia/monorepo/tree/main/packages/platform/http)
- [HTTP Middleware Wiring SSoT](../../../docs/ssot/http-middleware-wiring.md)
- [HTTP Middleware Catalog SSoT](../../../docs/ssot/http-middleware-catalog.md)
- [DI Tags and Middleware Ordering SSoT](../../../docs/ssot/di-tags-and-middleware-ordering.md)
- [Runtime Container Definitions SSoT](../../../docs/ssot/runtime-container-definitions.md)
- [Config and env SSoT](../../../docs/ssot/config-and-env.md)
- [UoW and Reset Contracts SSoT](../../../docs/ssot/uow-and-reset-contracts.md)
- [UnitOfWork Outcome Policy SSoT](../../../docs/ssot/uow-outcome-policy.md)
- [Observability SSoT](../../../docs/ssot/observability.md)
- [Logging Policy SSoT](../../../docs/ssot/logging-policy.md)
- [Problem Details SSoT](../../../docs/ssot/problem-details.md)
- [Artifacts and Fingerprint Behavior SSoT](../../../docs/ssot/artifacts-and-fingerprint.md)
- [ADR-0041: Platform HTTP runtime pipeline and wiring](../../../docs/adr/ADR-0041-platform-http-runtime-pipeline-wiring.md)
- [Compile-time package dependencies](../../../docs/architecture/DEPENDENCIES.md)
