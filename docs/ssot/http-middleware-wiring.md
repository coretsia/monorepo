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

This document is the Single Source of Truth for the `platform/http` execution semantics of the existing nine HTTP middleware slots: canonical PSR-15 composition, deterministic tagged and manual service-id ordering, HTTP-owned tag metadata, response decoration, canonical terminal system-endpoint integration, outermost error-boundary placement, optional HTTP request identity preparation, application-local maintenance policy, HTTPS redirect policy, security-header finalization, and the boundary between middleware execution and `HttpKernel` final-response completion.

The implementation boundary is:

```text
packages/platform/http/src/Middleware/HttpMiddlewareStackBuilder.php
packages/platform/http/src/Middleware/Pipeline.php
packages/platform/http/src/Provider/HttpServiceFactory.php
packages/platform/http/src/Provider/HttpServiceProvider.php
packages/platform/http/src/Routing/FallbackResponses.php
packages/platform/http/src/Routing/FallbackRouter.php
packages/platform/http/src/Routing/FallbackRouterHandler.php
packages/platform/http/src/HttpKernel.php
packages/platform/http/src/RequestId/RequestIdPolicy.php
packages/platform/http/src/Maintenance/MaintenanceMessage.php
packages/platform/http/src/Maintenance/MaintenanceStateStore.php
packages/platform/http/src/Maintenance/MaintenanceMiddleware.php
packages/platform/http/src/Console/MaintenanceEnableCommand.php
packages/platform/http/src/Console/MaintenanceDisableCommand.php
packages/platform/http/src/Console/MaintenanceStatusCommand.php
packages/platform/http/src/HttpsRedirect/HttpsRedirectPolicy.php
packages/platform/http/src/Middleware/HttpsRedirectMiddleware.php
packages/platform/http/src/SecurityHeaders/SecurityHeadersPolicy.php
packages/platform/http/src/Response/HttpResponseFinalizer.php
packages/platform/http/src/Observability/HttpRequestMetricsRecorder.php
packages/platform/http/src/Observability/HttpAccessLogWriter.php
packages/platform/http/src/Observability/HttpRequestSpanRecorder.php
```

This document specifies HTTP wiring and completion behavior, not a new tag registry, container, UnitOfWork implementation, error-normalization pipeline, or HTTP error renderer.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

A selected HTTP target MUST execute one deterministic PSR-15 middleware chain when Kernel UnitOfWork begin and pre-pipeline request preparation succeed.

The runtime MUST preserve applicable response decorators for ordinary short-circuit responses. Request-level observability MUST consume the successfully finalized response or the canonical fatal completion result, independently of terminal system-endpoint decision observability.

Uncaught failures during request preparation, pipeline execution, response finalization, or final response status validation MUST follow the canonical fatal completion path.

The runtime MUST reuse Foundation tagged-service discovery and declarative DI, Kernel-owned UnitOfWork hooks, and the optional canonical Problem Details boundary without introducing another observer pipeline or request lifecycle.

## Authority and source-of-truth boundaries (MUST)

- `docs/ssot/tags.md` owns the reserved HTTP middleware tag names. `Coretsia\Foundation\Tag\ReservedTags` is their sole code-level identifier registry.
- `docs/ssot/http-middleware-catalog.md` owns middleware taxonomy, catalog placement, optional owner participation, and cross-cutting HTTP policy. This document owns the concrete composition and execution semantics of those slots.
- `docs/ssot/http-system-endpoints.md` owns canonical terminal system-endpoint dispatch, reserved endpoint fallback responses, guarded development-only diagnostics, unmatched response semantics, and endpoint-decision measurement boundaries. This document owns their integration into the canonical PSR-15 pipeline and declarative HTTP service graph.
- `docs/ssot/di-tags-and-middleware-ordering.md` owns `TagRegistry::all()` ordering and Foundation's first-registration-wins tag deduplication.
- `docs/ssot/runtime-container-definitions.md` owns declarative container operations, service binding precedence, and source/compiled container construction semantics.
- `docs/ssot/uow-and-reset-contracts.md` and `docs/ssot/uow-outcome-policy.md` own Kernel UnitOfWork and completion contracts. HTTP consumes those contracts without implementing its own hooks or reset orchestration.
- `docs/ssot/context-keys.md` owns the canonical context key registry and writer ownership. `docs/ssot/context-store.md` owns shared context access, controlled mutation through the contracts-level ports, and structural safe-write validation.
- `docs/ssot/context-lifecycle.md` owns context lifetime and canonical reset boundaries. `docs/ssot/time-ids-and-duration.md` owns Foundation identifier generation and correlation id semantics.
- This document owns HTTP-specific request-id configuration, trusted ingress requirements, identifier selection and validation, maintenance runtime-state consumption and execution policy, HTTPS redirect execution policy, and canonical response-header application.
- `docs/ssot/artifacts-and-fingerprint.md` owns Kernel-generated artifact and fingerprint exclusion policy. Maintenance runtime state MUST remain independent of generated artifacts.
- `docs/ssot/observability.md` owns canonical metric and span names, allowed labels, and disclosure policy. `docs/ssot/problem-details.md` owns canonical error response adaptation and rendering.
- `docs/architecture/DEPENDENCIES.md` governs compile-time package dependencies. HTTP wiring MUST NOT import concrete implementations from optional `platform/*` runtime packages.

## Core invariants (MUST)

- `platform/http` MUST reuse the existing nine `ReservedTags::HTTP_MIDDLEWARE_*` identifiers without adding aliases, competing tag names, or another slot registry.
- The effective target-local `http.middleware` configuration contains exactly nine manual append lists. These lists MUST NOT replace tagged discovery or introduce skeleton/application defaults for middleware supplied by optional packages.
- A middleware service MUST participate in at most one effective HTTP slot. Repetition within its own slot follows first-occurrence semantics; participation in different slots is invalid.
- Tag ordering, middleware instance resolution, PSR-15 traversal, and HTTP request completion have separate owners. None may recreate another owner's registry or lifecycle.
- The resolved pipeline and HTTP completion services MUST have equivalent construction and execution semantics in source and compiled containers.
- Per-request requests, responses, selected request identity evidence, Throwables, traversal positions, timing tokens, and span handles MUST remain invocation-local, not shared mutable DI service state.
- HTTP request identity preparation MUST execute after successful Kernel UnitOfWork begin and before the entire PSR-15 chain when enabled.
- `RequestIdPolicy` MUST operate outside all nine HTTP middleware slots.
- HTTP request identity MUST remain independent of Kernel-owned correlation and UnitOfWork identity.
- Static maintenance middleware participation MUST remain independent of application-local runtime maintenance activation.
- Maintenance runtime state MUST NOT be cached in shared HTTP services or materialized into compiled configuration and container definitions.
- Maintenance state access MUST reuse the existing Kernel-owned runtime path context and Foundation filesystem locking and stable JSON primitives.
- Maintenance decisions MUST remain independent of canonical final-response completion, ContextStore ownership, and Kernel lifecycle and reset responsibility.

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

`Pipeline` MUST execute the resolved `MiddlewareInterface` sequence through PSR-15 `RequestHandlerInterface`, using invocation-local traversal state and the selected terminal handler. Pipeline traversal MUST NOT discover services, sort tags, manage UnitOfWork, or store a mutable cursor across invocations.

`FallbackRouterHandler` is the canonical terminal `RequestHandlerInterface`. It MUST delegate the request exactly once to `FallbackRouter` and return a non-null dispatched response unchanged. A non-matching dispatch result MUST delegate to `FallbackResponses::notFound()`, preserving the safe empty-body HTTP `404` response.

`FallbackRouter` consumes the canonical Foundation-owned system-route declarations through `ReservedPathMatcher` and implements only the explicitly supported system-endpoint dispatch behavior defined by `docs/ssot/http-system-endpoints.md`. It delegates the canonical `dev_only` prefix declaration to `DebugRouter`, which consumes `DebugGuard` and the four diagnostic handlers. These services execute within the same terminal handler, outside all nine middleware slots. Their eligibility, authorization, unmatched and forbidden semantics MUST follow that SSoT. The dispatcher MUST NOT implement general application routing, route admission, or general application endpoint authorization.

Terminal execution MUST occur only when the preceding middleware chain delegates to the terminal handler. Earlier middleware responses MUST retain precedence over terminal dispatch.

Terminal responses remain subject to applicable response decorators and canonical `HttpKernel` final-response completion. Terminal Throwables MUST propagate unchanged to the existing error and lifecycle boundaries.

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
http.maintenance.enabled
http.proxy.enabled
http.context.enrich.enabled
http.hosts.enabled
http.https_redirect.enabled
http.request.json.enabled
```

Unknown keys, extra metadata fields, unsupported toggle names, or missing/non-boolean effective toggle values MUST be rejected. A disabled toggle excludes that tagged entry from the effective chain; it MUST NOT change the declarative provider definitions or tag registrations. Metadata and reserved error-boundary constraints MUST be validated even for disabled entries.

The baseline `system_pre` tag contributions are:

- `TraceContextMiddleware` at priority `930`, conditional on `http.tracing.enabled`;
- `MaintenanceMiddleware` at priority `900`, conditional on `http.maintenance.enabled`;
- `TrustedProxyMiddleware` at priority `880`, conditional on `http.proxy.enabled`;
- `RequestContextMiddleware` at priority `870`, conditional on `http.context.enrich.enabled`;
- `TrustedHostMiddleware` at priority `860`, conditional on `http.hosts.enabled`;
- `HttpsRedirectMiddleware` at priority `850`, conditional on `http.https_redirect.enabled`;
- `RequestBodySizeLimitMiddleware` at priority `830`, unconditional;
- `JsonBodyParserMiddleware` at priority `800`, conditional on `http.request.json.enabled`.

The optional priority-`1000` boundary is governed by explicit module selection, not by an HTTP toggle. Other optional middleware owners contribute their own registrations only under their applicable activation policy. Future-only catalog reservations are not executable registrations.

`http.middleware.auto.*` flags MUST NOT exist. `http.correlation.enabled`, `http.metrics.enabled`, and `http.access_log.enabled` govern their respective HTTP completion services; their inclusion in the metadata allowlist MUST NOT be interpreted as creating correlation, metrics, or access-log middleware.

`http.request_id.enabled` and `http.security_headers.enabled` are not middleware participation toggles and MUST NOT be included in the HTTP middleware tag metadata allowlist.

`http.maintenance.enabled` controls tagged maintenance middleware participation. A manually appended `MaintenanceMiddleware` MUST also respect its injected feature enablement. Disabled middleware MUST delegate without accessing maintenance state or producing maintenance-decision observations.

Runtime maintenance activation MUST NOT change declarative middleware registrations, tag metadata, or effective pipeline construction.

`http.https_redirect.enabled` controls tagged HTTPS redirect participation. A manually appended `HttpsRedirectMiddleware` MUST also respect its injected policy enablement. A disabled policy MUST delegate without performing redirect-decision observability.

Request identity preparation MUST NOT reserve or consume a middleware slot, priority, tag registration, or manual append-list entry.

## Declarative composition and container parity (MUST)

`HttpServiceProvider` MUST register HTTP-owned services, middleware tags, and the pipeline construction definition through the canonical Foundation declarative DI operations. `HttpMiddlewareStackBuilder` consumes the final `TagRegistry` and validated HTTP configuration; `HttpServiceFactory` constructs `Pipeline` from effective service ids resolved through the final canonical container.

- Source and compiled containers MUST retain equivalent middleware service definitions, tag metadata and priorities, effective ordering, factory bindings, and terminal-handler resolution.
- `HttpServiceProvider` MUST require the existing Foundation-owned `Psr\Clock\ClockInterface` service without registering an HTTP-owned clock or replacing the canonical binding.
- `HttpServiceProvider` MUST register one shared immutable `FallbackResponses` service with explicit references to the canonical `ResponseFactoryInterface` and `StreamFactoryInterface` services.
- `HttpServiceProvider` MUST register shared immutable `DebugGuard`, `DebugHeadersHandler`, `DebugModuleListHandler`, `DebugConfigHandler`, `DebugCacheHandler`, and `DebugRouter` services independently of debug activation, with the mandatory explicit dependencies defined by `docs/ssot/http-system-endpoints.md`.
- `DebugGuard` MUST be constructed through `HttpServiceFactory::debugGuard()` from the effective `ConfigRepositoryInterface`, Kernel-owned `RuntimeDiagnosticsInput` and `ModulePlan`, and the shared `TrustedProxyPolicy`. Policy construction MUST validate the debug settings even when disabled. Configuration-sensitive policy input MUST NOT be serialized as compiled definition literals.
- The header and config handlers MUST consume the required canonical Redaction ports through `core/contracts`. HTTP MUST NOT import concrete Redaction implementation classes. Guard eligibility MUST require explicit selection of Problem Details in the runtime module plan; installation alone is insufficient.
- `DebugRouter` MUST consume the shared guard and diagnostic handlers, `MeterPortInterface`, PSR-3 `LoggerInterface`, and the validated `http.metrics.enabled` and `http.access_log.enabled` booleans.
- `HttpServiceProvider` MUST register one shared immutable `FallbackRouter` service with explicit references to `FallbackResponses`, `ClockInterface`, `MeterPortInterface`, and `DebugRouter`, together with the validated `http.metrics.enabled` boolean.
- `FallbackRouterHandler` MUST consume the shared `FallbackRouter` and `FallbackResponses` services through mandatory non-nullable constructor dependencies. The canonical `RequestHandlerInterface` alias MUST resolve to `FallbackRouterHandler`.
- Source and compiled containers MUST preserve equivalent terminal service definitions and shared dependency identities, including final application-owned clock and PSR-17 factory binding overrides under the existing canonical binding and readiness policies.
- Terminal endpoint service registration MUST remain independent of observability enablement. These services MUST NOT participate in middleware tags, manual append lists, middleware priorities or toggle metadata, Kernel lifecycle hooks, ContextStore ownership, or reset registration. Debug activation MUST NOT alter middleware participation or ordering.
- `HttpServiceProvider` MUST register one shared `RequestIdPolicy` through canonical declarative DI independently of `http.request_id.enabled`.
- `HttpKernel` and `HttpResponseFinalizer` MUST consume the same `RequestIdPolicy` instance as a mandatory non-nullable constructor dependency.
- `HttpServiceProvider` MUST require `Coretsia\Contracts\Context\ContextWriterInterface` and `Coretsia\Contracts\Context\ContextAccessorInterface`. Both ports MUST resolve to the same shared Foundation-owned context store in source and compiled containers. HTTP MUST NOT require the concrete store or register or replace either port binding.
- `RequestContextMiddleware` MUST receive `ContextWriterInterface`, the validated `http.context.enrich.enabled` flag, and the validated `http.proxy.enabled` flag in that constructor order. It MUST write only validated HTTP-owned `CLIENT_IP`, `SCHEME`, `HOST`, `PATH`, and `USER_AGENT` values through the writer. Disabled enrichment MUST delegate without writing; unavailable values are omitted and optional write failures MUST NOT weaken the context policy or prevent downstream handling. It MUST NOT consume context reads or lifecycle APIs.
- `RequestIdPolicy` MUST receive `ContextWriterInterface`, `ContextAccessorInterface`, the explicitly selected Foundation `UlidGenerator::class` service through `IdGeneratorInterface`, and the validated request-id enablement, header, upstream-acceptance, and maximum-length settings, in that constructor order.
- Source and compiled containers MUST preserve equivalent effective request-id policy configuration. Within each container, `HttpKernel` and `HttpResponseFinalizer` MUST consume the same shared `RequestIdPolicy` instance, which MUST consume that container's canonical context ports and `UlidGenerator` service.
- Request-id policy registration and constructor injection MUST remain unconditional. An absent policy dependency, nullable dependency, fallback policy instance, or alternative enablement mechanism MUST NOT be supported.
- `HttpServiceProvider` MUST register shared immutable `SecurityHeadersPolicy` and `HttpsRedirectPolicy` services independently of feature enablement.
- `HttpResponseFinalizer` MUST consume the shared `SecurityHeadersPolicy` as a mandatory non-nullable constructor dependency without a default or fallback instance.
- `HttpsRedirectMiddleware` MUST consume the shared `HttpsRedirectPolicy`, canonical PSR-17 response factory, meter, PSR-3 logger, and validated HTTP proxy and observability settings through explicit constructor injection.
- `HttpServiceProvider` MUST require the existing Kernel-owned `RuntimePathContext` service and MUST NOT register, replace, construct, or alias it.
- `HttpServiceProvider` MUST require the Foundation-owned `ScopedFileLock` service and register one shared immutable `MaintenanceStateStore` through canonical declarative DI, using explicit runtime path-context and scoped-lock service references.
- `MaintenanceMiddleware` MUST consume the shared `MaintenanceStateStore`, canonical PSR-17 `ResponseFactoryInterface` and `StreamFactoryInterface`, `MeterPortInterface`, PSR-3 `LoggerInterface`, and validated maintenance and observability settings through mandatory explicit constructor dependencies.
- Maintenance middleware and state-store service registration MUST remain independent of static feature enablement and dynamic runtime activation.
- The complete effective `http.maintenance` configuration map MUST be structurally validated independently of feature enablement.
- The three maintenance command services MUST consume the same shared `MaintenanceStateStore` as the middleware within their canonical container. They MUST be registered through `ReservedTags::CLI_COMMAND` independently of HTTP maintenance feature enablement.
- Maintenance command resolution MUST NOT require executable HTTP response factories, middleware pipeline construction, or `HttpKernel` resolution.
- Maintenance definition production MUST NOT inspect filesystem state, resolve maintenance command services, embed resolved application-root paths, or materialize active maintenance state into service descriptors.
- Source and compiled containers MUST preserve equivalent maintenance dependencies, configuration, tag metadata, and runtime-state consumption semantics.
- Structural validation MUST apply to the complete effective `http.security_headers` and `http.https_redirect` configuration maps independently of feature enablement.
- Semantic security-header policy validation MUST execute when the mandatory shared policy is constructed, independently of enablement.
- Enabled HTTPS redirect policy validation MUST execute during canonical middleware resolution before HTTP UnitOfWork execution. Disabled redirect middleware MUST NOT require eager policy resolution solely for semantic validation.
- Declarative provider definition production MUST NOT instantiate these policies or introduce another configuration-validation lifecycle.
- Canonical Foundation service and alias precedence, including later-binding-wins semantics, MUST remain unchanged. HTTP MUST NOT introduce a second definition-conflict policy.
- The selected target's `http.factory` and effective PSR-17 bindings MUST be validated through the HTTP-owned entrypoint readiness boundary before resolving executable HTTP services. Installed but unselected implementations MUST NOT alter the selected factory or provide an implicit fallback.
- The selected HTTP target and effective module participation MUST govern optional middleware provider registration. An unselected optional module MUST NOT contribute a middleware merely because its package is installed.
- Runtime-only closure definitions, filesystem discovery, reflection-based factory selection, alternate containers, observer registries, or another middleware pipeline MUST NOT be introduced.

## Maintenance policy (MUST)

### Static participation and runtime activation

`MaintenanceMiddleware` owns application-local HTTP maintenance decisions.

The effective `http.maintenance` configuration map contains exactly two required keys with the following defaults:

```php
[
    'enabled' => true,
    'retry_after_default_seconds' => 60,
]
```

`enabled` MUST be a boolean.

`retry_after_default_seconds` MUST be an integer within the inclusive range `1..86400`.

These settings control target-local middleware participation and the default retry delay. They MUST NOT represent active runtime maintenance state.

All participating HTTP targets using the same application root and filesystem MUST consume the same application-local maintenance activation. A disabled HTTP target MUST continue normal request processing regardless of the shared runtime flag.

Disabled middleware MUST delegate immediately without accessing maintenance state.

### Runtime state ownership and access

`MaintenanceStateStore` owns maintenance state access for participating HTTP middleware and maintenance commands.

The state directory MUST be resolved through the existing Kernel-owned `RuntimePathContext::applicationRoot()` using the following fixed application-root-relative paths:

```text
var/maintenance/enabled
var/maintenance/message.json
var/maintenance/maintenance.lock
```

The presence of a regular `enabled` flag determines active maintenance. The optional message file MUST NOT independently activate maintenance.

The application root MUST be a fully qualified absolute local path identifying an existing accessible application directory. Relative and drive-relative application roots MUST fail through the maintenance state-unavailable boundary. State-changing operations MAY create missing runtime directory components beneath the application root but MUST NOT create the application root itself.

The runtime state directory MUST be operator-controlled and MUST NOT be writable by untrusted users or processes. The `var` and `var/maintenance` components MUST be real directories, not symlinks. Required flag and lock-anchor entries MUST be regular files and MUST NOT be followed if they are symlinks.

The state store MUST reuse Foundation `ScopedFileLock` for cooperating shared reads and exclusive mutations, and the canonical `StableJsonEncoder` and `StableJsonDecoder` primitives for maintenance message serialization and reconstruction.

Maintenance state evaluation MUST observe current filesystem state on each participating request without retaining a mutable runtime snapshot in shared services.

Confirmed absence of the runtime state directory or enabled flag represents inactive maintenance. Failed filesystem inspection MUST NOT be interpreted as confirmed absence.

An invalid optional message MUST NOT deactivate an existing maintenance flag. Unsafe optional message entries MUST be classified as invalid during active-state reads without following symlinks or reading non-regular files. Inactive-state reads and disable operations MUST NOT inspect retained optional message entries. Enable operations MAY inspect retained optional message entries under the exclusive lock but MUST NOT follow, read, replace, or remove unsafe entries.

Application-root resolution, directory validation, flag and lock-anchor validation, and unsuccessful required filesystem operations MUST fail through the fixed `MaintenanceStateUnavailableException` boundary.

Mutable maintenance state MUST remain outside compiled configuration and container definitions. The `var/maintenance` application fingerprint exclusion MUST be preserved according to `docs/ssot/artifacts-and-fingerprint.md`. The configured artifact cache directory MUST NOT overlap the maintenance runtime state directory.

Maintenance activation and deactivation MUST NOT require configuration recompilation or reconstruction of shared HTTP runtime services.

### Public maintenance message

`MaintenanceMessage` owns the immutable version-1 public message schema.

The optional message file MUST contain a JSON object with exactly the required `schemaVersion` and `message` fields and, when supplied, the optional `retryAfterSeconds` field.

- `schemaVersion` MUST be integer `1`.
- `message` MUST contain non-empty valid UTF-8 text of at most 1024 bytes, without control characters, Unicode line separators, or leading/trailing whitespace.
- `retryAfterSeconds`, when present, MUST be an integer within the inclusive range `1..86400`.

Unknown fields, duplicate object keys, unsupported schema versions, invalid field types, malformed JSON, and JSON input exceeding 8192 bytes MUST be rejected.

Canonical message publication MUST use Foundation stable JSON encoding with deterministic key ordering and exactly one final LF.

Invalid message diagnostics MUST NOT expose rejected values or original JSON decoder diagnostics.

The message is intentionally public response content and MUST NOT contain sensitive information.

### Request execution and liveness bypass

`MaintenanceMiddleware` MUST execute in the canonical `system_pre` position at priority `900`, after `TraceContextMiddleware` and before trusted-proxy interpretation, request-context enrichment, trusted-host validation, HTTPS redirect decisions, and request-body processing.

Confirmed inactive maintenance state MUST delegate to the downstream handler without maintenance-decision observations.

Active maintenance MUST prevent downstream request processing, except for the exact case-sensitive URI path:

```text
/_alive
```

The bypass MUST compare the PSR-7 URI path exactly. Query strings MUST NOT affect path matching. Trailing slashes, additional path segments, different casing, and percent-encoded substitutes MUST NOT match. Debug paths MUST NOT receive an automatic bypass.

The exact liveness bypass MUST remain available when maintenance state is unavailable.

A bypassed request MUST invoke the downstream handler exactly once and return its response unchanged or propagate its original Throwable.

Maintenance MUST NOT implement a system endpoint, create a synthetic liveness response, or guarantee a downstream HTTP status.

### Maintenance response construction

Active maintenance on a non-bypassed request MUST return a response constructed through the existing PSR-17 response and stream factories with:

```text
status: 503
Content-Type: text/plain; charset=utf-8
Cache-Control: no-store
Retry-After: <decimal seconds>
```

A valid public message with an explicit `retryAfterSeconds` MUST override the configured default retry delay. A valid message without that field MUST use the configured default.

Missing or invalid optional message state, including malformed, oversized, unreadable, or unsafe message entries, MUST use the configured default retry delay and the generic body.

Unavailable maintenance state MUST fail closed using the same generic response and default retry delay, except for the exact liveness bypass.

For methods other than the exact `HEAD` method, the response body MUST contain the validated public message followed by exactly one LF, or the generic body `Service Unavailable` followed by exactly one LF.

For `HEAD`, the body MUST be empty while preserving the maintenance status and headers.

Response construction MUST use canonical immutable PSR-7 mutations and consume each successfully returned `ResponseInterface`.

Response-construction failures MUST propagate through the existing PSR-15 Throwable path. Maintenance MUST NOT generate a replacement error response or introduce another error boundary.

### Maintenance-decision observability

Maintenance decisions MUST be observed independently of final HTTP request completion.

The canonical `http.maintenance_total` counter and fixed maintenance log events MUST follow the enablement, completed-decision, bounded-context, disclosure, and failure-isolation requirements in `docs/ssot/observability.md`.

A completed served decision MUST be observed only after successful maintenance response construction. A completed bypassed decision MUST be observed before invoking the downstream handler.

Applicable invalid-message and unavailable-state warning logs MUST remain independent of successful response construction and downstream execution.

Disabled middleware and confirmed inactive maintenance state MUST NOT emit maintenance-decision observations.

Maintenance observability MUST NOT introduce additional spans, ContextStore writes, completion observers, lifecycle hooks, or reset services.

### Final-response completion

Successfully returned maintenance responses MUST follow applicable outer post-decorator traversal and the canonical `HttpKernel` final-response completion sequence.

Enabled correlation, request-id, and security-header policies MUST remain applicable to the returned maintenance response.

A successfully finalized HTTP `503` response MUST retain the canonical `handled_error` request outcome.

Subsequent post-decorator, response-finalization, or Kernel completion failures MUST preserve the existing Throwable precedence and completion semantics. An already completed maintenance decision MUST NOT be retried or reclassified because of a subsequent failure.

Maintenance MUST NOT introduce another UnitOfWork, ContextStore mutation boundary, reset attempt, or response-finalization pipeline.

## HTTPS redirect policy (MUST)

### Participation and scheme resolution

`HttpsRedirectMiddleware` owns optional HTTP-to-HTTPS redirect decisions using the immutable shared `HttpsRedirectPolicy`.

Redirect eligibility MUST use the exact effective scheme and request method.

When `http.proxy.enabled=true`, the middleware MUST accept an effective proxy scheme only from `TrustedProxyMiddleware::ATTRIBUTE` containing an array with a non-empty string `client_ip` and a `scheme` equal to `http` or `https`.

Otherwise, the middleware MUST use the PSR-7 request URI scheme.

Canonical forwarding-header interpretation, client IP validation, and immediate-peer trust remain owned by `TrustedProxyPolicy` and `TrustedProxyMiddleware`.

HTTPS redirect MUST NOT independently parse raw forwarding headers, consume ContextKeys, depend on ContextStore enrichment, or trust the reserved proxy attribute when proxy participation is disabled.

Only effective `http` is eligible for redirection.

### Redirect configuration and destination authority

`http.https_redirect.enabled` defaults to `false`.

`http.https_redirect.status` MUST be either integer `307` or `308` and defaults to `308`.

`http.https_redirect.allow_methods` defaults to `['GET', 'HEAD']` and MUST contain only unique canonical uppercase method values from:

- `GET`;
- `HEAD`;
- `POST`;
- `PUT`;
- `PATCH`;
- `DELETE`;
- `OPTIONS`.

An empty method allowlist MUST permit no redirects.

`http.https_redirect.target_authority` defaults to an empty string. An enabled policy MUST require a non-empty explicitly configured authority.

Every non-empty configured authority MUST satisfy the existing canonical authority grammar owned by `TrustedHostPolicy`, independently of redirect enablement.

A non-empty target authority MUST consist of a canonical DNS name, IPv4 address, or bracketed IPv6 address, optionally followed by an explicit port. It MUST NOT contain a scheme prefix, userinfo, path, query, or fragment.

The destination authority MUST NOT be derived from the request URI, Host header, forwarded host, ContextStore, or `http.hosts.allowed`.

### Location construction and response execution

The Location value MUST consist exclusively of `https://`, the validated configured target authority, the original encoded request path, and the original encoded query when non-empty.

An empty request path MUST be normalized to `/`.

The normalized path MUST begin with `/` and MUST NOT begin with `//`.

Path characters MUST conform to RFC 3986 `pchar` or `/`. Query characters MUST conform to RFC 3986 `pchar`, `/`, or `?`.

Every percent-encoded sequence MUST contain exactly two hexadecimal digits. Accepted encoded components MUST NOT be decoded, re-encoded, normalized, or reconstructed from parsed request parameters.

Raw whitespace, control characters, non-ASCII bytes, backslashes, raw fragment delimiters (`#`), malformed percent encoding, and a raw query delimiter (`?`) within the path component MUST cause the redirect to be skipped.

Request userinfo, fragments, and source ports MUST NOT be copied into Location. Ports are supplied exclusively by the configured destination authority.

An ineligible scheme, disallowed method, or invalid Location component MUST delegate to the downstream handler without constructing a redirect response.

An eligible redirect MUST use the canonical PSR-17 response factory and immutable PSR-7 `withHeader()` application of Location. The mutation result MUST implement `ResponseInterface`.

A non-response Location mutation result MUST fail with `RuntimeException` and the fixed reason `http-https-redirect-response-invalid`.

A successfully constructed redirect MUST return without invoking the downstream handler.

Response construction and mutation failures MUST propagate through the canonical PSR-15 error boundary or uncaught HTTP failure path.

### Redirect-decision observability

Redirect-decision observations MUST follow `docs/ssot/observability.md`.

A completed redirected decision requires successful response construction and Location mutation. A completed skipped decision MUST be recorded before downstream handler invocation.

The `http.metrics.enabled` and `http.access_log.enabled` settings independently control the canonical redirect-decision counter and PSR-3 info event.

Disabled or bypassed middleware MUST NOT emit redirect-decision observations. Failed redirect-response construction or Location mutation MUST NOT emit a completed decision.

Optional metric and log failures MUST be isolated independently without retries.

Redirect decisions MUST NOT establish the final HTTP request status or outcome, introduce additional spans, write ContextStore values, register reset services, or create another request lifecycle.

## HTTP request identity policy (MUST)

### Configuration and activation

The HTTP request identity policy is configured exclusively through the existing `http` config root:

| Config key                             | Type   | Default          | Meaning                                                                    |
|----------------------------------------|--------|------------------|----------------------------------------------------------------------------|
| `http.request_id.enabled`              | bool   | `false`          | Enables HTTP request identity preparation and response-header application. |
| `http.request_id.header_name`          | string | `'X-Request-Id'` | Header name used for upstream acceptance and response-header application.  |
| `http.request_id.accept_from_upstream` | bool   | `false`          | Permits acceptance of validated identifiers from trusted ingress.          |
| `http.request_id.max_length`           | int    | `128`            | Maximum accepted identifier byte length; inclusive range `26..128`.        |

The `http.request_id` subtree MUST be a required map containing exactly these four required keys. Additional keys, reserved `@*` directives, and invalid value types MUST be rejected through canonical config validation.

The `enabled` and `accept_from_upstream` values MUST be booleans without coercion. The `header_name` value MUST be a non-empty whitespace-free string. The `max_length` value MUST be an integer without coercion.

Structural configuration validation MUST apply regardless of whether request identity preparation is enabled.

The configured header name MUST be a non-empty ASCII HTTP header token, contain at most 128 bytes, and match the following complete-name grammar case-insensitively:

```text
\A(?:[A-Za-z0-9]+-)*request-id\z
```

Unrelated HTTP header names and correlation-id header names MUST NOT be accepted.

`http.request_id.max_length` MUST be an integer within the inclusive range `26..128`.

`RequestIdPolicy` MUST validate semantic constructor settings even when request identity preparation is disabled. Invalid semantic header-name or maximum-length configuration MUST fail during policy construction, before HTTP UnitOfWork execution, with `InvalidArgumentException` and the applicable fixed reason:

```text
http-request-id-header-invalid
http-request-id-max-length-invalid
```

`http.request_id.enabled` is the sole request identity policy activation mechanism. It MUST NOT control service registration, dependency injection, middleware participation, or reset discovery.

Request-id enablement and correlation response-header enablement are independent. Neither policy enables, disables, replaces, or generates the identity owned by the other.

### Ownership and shared dependencies

The HTTP request identity owner is:

```text
Coretsia\Platform\Http\RequestId\RequestIdPolicy
```

`RequestIdPolicy` MUST be a shared immutable policy service with no mutable request-local state.

It MUST receive mandatory `ContextWriterInterface` and `ContextAccessorInterface` constructor dependencies, in that order, through the existing Foundation-owned shared bindings. It MUST NOT construct, alias, or register another context store.

`RequestIdPolicy` is the sole authorized HTTP runtime writer of `ContextKeys::REQUEST_ID` and MUST write it exclusively through `ContextWriterInterface`.

Its read access through `ContextAccessorInterface` MUST be limited to owner-local read-back of `ContextKeys::REQUEST_ID` solely for final-response identity verification. Both context ports remain governed by `docs/ssot/context-store.md`.

The policy MUST NOT read or modify Kernel-owned `ContextKeys::CORRELATION_ID`, `ContextKeys::UOW_ID`, or `ContextKeys::UOW_TYPE`.

Production request-id generation MUST use the existing Foundation-owned `UlidGenerator::class` service through `IdGeneratorInterface`. The declarative HTTP dependency MUST explicitly select `UlidGenerator::class`.

Changes to the generic Foundation default id-generator selection MUST NOT change HTTP request-id generation format.

The policy MUST NOT introduce another generator contract, ULID implementation, entropy source, generation fallback, identity provider, or request-local registry.

### Trusted upstream acceptance

Upstream identifier acceptance is disabled by default.

Enabling `http.request_id.accept_from_upstream` requires an explicitly trusted ingress deployment boundary.

The trusted ingress boundary MUST remove arbitrary client-supplied request-id headers or replace them with owner-approved safe opaque identifiers.

Untrusted direct-client ingress MUST keep upstream acceptance disabled.

Header presence, header name, identifier syntax, and identifier format MUST NOT be interpreted as proof of upstream trust or semantic value safety.

The request-id policy MUST NOT perform per-request sender authentication, trusted-proxy detection, authorization, or authenticated identity resolution.

Accepted upstream identifiers are not guaranteed to be globally unique.

### Request identity preparation

For each HTTP invocation that reaches the pre-pipeline identity preparation boundary, `HttpKernel` MUST invoke exactly once:

```text
RequestIdPolicy::initialize()
```

The invocation MUST occur after successful Kernel UnitOfWork begin, including eligible before-UoW handling, and before entering the entire PSR-15 pipeline, including any selected outermost error boundary and response decorators.

The policy MUST be invoked regardless of `http.request_id.enabled`.

An earlier uncaught request processing failure after successful Kernel UnitOfWork begin MUST prevent both identity preparation and pipeline execution and follow the canonical fatal completion path.

When disabled, `initialize()` MUST return `null` immediately without reading request headers, accessing either context port, or generating an identifier.

When enabled and upstream acceptance is disabled, the policy MUST generate a local identifier without reading the configured request-id request header.

When upstream acceptance is enabled, the policy MUST inspect only the configured case-insensitive PSR-7 request header through `ServerRequestInterface::getHeader()`.

An upstream identifier is accepted only when the effective PSR-7 header values contain exactly one string value that satisfies both:

- byte length within the inclusive range `1..http.request_id.max_length`;
- complete-value grammar:

```text
\A[A-Za-z0-9._-]+\z
```

Validation MUST use ASCII matching without Unicode or case-insensitive matching modifiers.

The single string value MUST be extracted without assuming a specific array index.

The policy MUST NOT use `getHeaderLine()`, concatenate multiple header values, coerce non-string values, trim, normalize, decode, or change the case of the accepted identifier.

The policy validates effective PSR-7 header values. It does not claim access to original wire-level header bytes or pre-PSR-7 normalization state.

Missing, empty, multiple, comma-separated, whitespace-containing, control-character-containing, non-ASCII, non-string, oversized, or otherwise invalid header values MUST NOT be accepted.

Missing or invalid upstream input MUST select one generated replacement. It MUST NOT introduce an HTTP rejection response solely because the supplied request identifier is invalid.

A valid accepted upstream identifier MUST remain unchanged.

Generated identifiers MUST be canonical uppercase 26-character ULIDs matching:

```text
\A[0-7][0-9A-HJKMNP-TV-Z]{25}\z
```

Invalid generator output MUST fail with:

```text
http-request-id-generation-invalid
```

through `RuntimeException`.

The generator MUST NOT be invoked when a valid upstream identifier is accepted.

When request identity preparation is enabled, the canonical generator MUST be invoked exactly once if upstream acceptance is disabled or if upstream input is missing or invalid.

Generator failures, invalid generator output, and unsuccessful identity selection MUST NOT write `ContextKeys::REQUEST_ID`.

Unexpected PSR-7 header-access failures and Foundation generator or context writer failures MUST propagate unchanged through canonical HTTP fatal completion.

After successful enabled identity selection, the policy MUST write the selected identifier exactly once through its `ContextWriterInterface` dependency:

```php
$this->context->set(ContextKeys::REQUEST_ID, $requestId)
```

and return exactly that identifier.

The original PSR-7 request MUST remain unmodified.

`HttpKernel` MUST retain the returned identifier only as invocation-local evidence for final-response identity verification.

The selected identifier MUST remain available through the PSR-15 pipeline, response finalization, and eligible after-UoW lifecycle handling on conforming execution paths.

Subsequent middleware, terminal handlers, and lifecycle hooks MUST NOT modify, replace, or remove the selected identifier.

Test-only removal or replacement of `ContextKeys::REQUEST_ID` is permitted exclusively for deterministic verification of identity-integrity failures. This does not grant additional production context mutation permissions.

### Final-response identity verification

After the entire PSR-15 pipeline returns a response, `HttpKernel` MUST pass the initially selected invocation-local identifier to `HttpResponseFinalizer`.

The finalizer MUST apply the enabled correlation response-header policy before request-id response-header finalization.

When correlation response-header application is disabled or the correlation provider returns `null` or an invalid identifier, correlation finalization MUST leave the response unchanged and MUST NOT skip enabled request-id identity verification or response-header application.

The finalizer MUST obtain the current request identity through `RequestIdPolicy::current()`.

When disabled, `current()` MUST return `null` without accessing the context accessor.

When enabled, `current()` MUST read only `ContextKeys::REQUEST_ID` through `ContextAccessorInterface::get()` and apply the same identifier syntax and byte-length validation used for upstream acceptance.

Missing, non-string, empty, oversized, or otherwise invalid current identity MUST fail deterministically.

The current identifier and the initially selected invocation-local identifier MUST match exactly using strict equality.

Syntactically valid replacement values MUST NOT be accepted merely because they satisfy identifier validation.

Missing, invalid, or substituted enabled context identity, a missing invocation-local selected identity when enabled, or an inconsistent selected identity when disabled MUST fail with `RuntimeException` and the fixed reason:

```text
http-request-id-context-invalid
```

The finalizer MUST NOT repair context, regenerate identity, normalize the current identifier, or emit a substituted identifier.

When both identifiers are `null`, request-id response-header finalization is not applicable. This condition MUST NOT bypass enabled security-header application.

When both identifiers are non-null and strictly equal, the finalizer MUST apply the validated configured header name and exact selected identifier through immutable PSR-7 `ResponseInterface::withHeader()`.

This operation MUST replace any existing value for the configured request-id response header, not append another value.

The finalizer MUST consume the response returned by preceding correlation-header finalization and MUST preserve the response status, body, and unrelated headers.

When request identity is disabled, application-owned response headers MUST NOT be added, removed, or modified by the request-id policy.

A request-id header mutation returning an object that does not implement `ResponseInterface` MUST fail with `RuntimeException` and the fixed reason:

```text
http-request-id-response-invalid
```

PSR-7 mutation failures MUST propagate unchanged.

A correlation-header finalization failure MUST prevent subsequent request-id response-header mutation.

Both independently enabled identity-header policies apply to successfully returned normal responses, request-phase short-circuit responses, post-decorator short-circuit responses, and handled-error responses returned by the selected outermost error boundary.

### Failure, reset, and disclosure boundaries

Uncaught request identity preparation, pipeline, and response-finalization failures MUST use the existing `HttpKernel` fatal completion path.

Request identity policy failures MUST NOT introduce another error normalization path, exception mapper, Problem Details renderer, rejection outcome, or fallback response.

Policy-authored exception messages MUST contain only fixed safe reason tokens. Rejected identifiers, selected identifiers, raw header contents, and dependency exception messages MUST NOT be exported through request-id diagnostics.

Identity preparation or finalization failure after successful Kernel UnitOfWork begin MUST NOT bypass the existing after-UoW phase or canonical reset responsibility.

Successful Foundation reset MUST clear `ContextKeys::REQUEST_ID` together with all other UnitOfWork-local context values.

HTTP MUST NOT directly execute reset, discover reset tags, retry reset, or invoke `ContextWriterInterface::remove()` for request identity cleanup. HTTP MUST NOT access concrete store clearing or reset operations.

`RequestIdPolicy` MUST NOT implement `MiddlewareInterface` or `ResetInterface`.

It MUST NOT participate in lifecycle hook registration or be registered under any `http.middleware.*`, `kernel.reset`, `kernel.stateful`, or effective Foundation reset discovery tag.

The selected request identifier MUST NOT be added to Kernel UnitOfWork context or result payloads.

The policy MUST NOT consume logger, meter, or tracer ports.

Request identifiers and rejected upstream values MUST NOT be added to existing HTTP completion logs, metrics, span attributes, span names, or error descriptor extensions.

## Security-header finalization (MUST)

`SecurityHeadersPolicy` owns immutable configuration-based response security-header values. `HttpResponseFinalizer` exclusively owns their application to completed pipeline responses.

`http.security_headers.enabled` defaults to `false` and MUST operate independently of correlation, request-id, and HTTPS redirect enablement.

The policy MUST expose only enabled, non-empty header values in the following fixed application order:

| Order | Response header           | Configuration key                              | Default value |
|-------|---------------------------|------------------------------------------------|---------------|
| 1     | `X-Content-Type-Options`  | `http.security_headers.x_content_type_options` | `nosniff`     |
| 2     | `X-Frame-Options`         | `http.security_headers.x_frame_options`        | `deny`        |
| 3     | `Referrer-Policy`         | `http.security_headers.referrer_policy`        | `no-referrer` |
| 4     | `Permissions-Policy`      | `http.security_headers.permissions_policy`     | empty         |
| 5     | `Content-Security-Policy` | `http.security_headers.csp`                    | empty         |

The policy MUST accept only `nosniff` for `x_content_type_options` and `deny` or `sameorigin` for `x_frame_options`. Frame-option response values MUST be emitted as `DENY` or `SAMEORIGIN`.

Supported `referrer_policy` values are:

- `no-referrer`;
- `no-referrer-when-downgrade`;
- `origin`;
- `origin-when-cross-origin`;
- `same-origin`;
- `strict-origin`;
- `strict-origin-when-cross-origin`;
- `unsafe-url`.

Non-empty `permissions_policy` and `csp` values MUST contain at most 4096 bytes, use printable ASCII only, and contain no leading or trailing whitespace.

Invalid policy configuration MUST fail through fixed safe diagnostic tokens without disclosing configured values.

A disabled policy MUST expose an empty header map.

Security-header application MUST execute after successful correlation and request-id finalization. Identity-header verification or mutation failure MUST prevent all subsequent security-header mutations.

Each configured non-empty security header MUST be applied through exactly one immutable PSR-7 `withHeader()` operation, consuming the response returned by the preceding successful mutation.

Each mutation result MUST implement `ResponseInterface`. A non-response result MUST fail with `RuntimeException` and the fixed reason `http-security-header-response-invalid`.

Mutation failures MUST propagate unchanged through canonical HTTP fatal completion and MUST prevent subsequent header applications.

Configured non-empty values MUST replace existing matching response-header values case-insensitively. Empty optional settings MUST NOT remove or modify existing application-provided headers.

Security-header application MUST preserve the response status, body, and unrelated headers.

The canonical mutation order MUST NOT be interpreted as a guarantee of PSR-7 serialized header iteration or transport wire order.

Enabled security-header application MUST cover every successfully returned pipeline response, including normal responses, request-phase and post-decorator short-circuits, HTTPS redirects, returned maintenance responses, mapped Problem Details responses, and emergency responses.

An uncaught pipeline Throwable MUST NOT cause the finalizer to synthesize a response.

Security-header policies and their application MUST NOT introduce HSTS, CSP nonce generation, request-dependent values, middleware participation, additional observability, ContextStore mutation, or reset responsibility.

## HttpKernel lifecycle and final-response completion (MUST)

`HttpKernel` is the sole HTTP request lifecycle and final-response coordinator. It consumes the existing `KernelRuntimeInterface::beginUnitOfWork('http')` and matching `afterUnitOfWork()` hooks with the exact returned handle. Kernel owns hook execution, context lifetime, and reset discipline; HTTP MUST NOT reimplement them.

After a successful UnitOfWork begin, the canonical completion sequence when request preparation, pipeline execution, and response finalization succeed is:

```text
KernelRuntimeInterface::beginUnitOfWork('http')
  -> optional request-local Stopwatch start and http.request span start
  -> RequestIdPolicy::initialize() and invocation-local identity selection
  -> complete PSR-15 Pipeline::handle() (including any selected error boundary)
  -> HttpResponseFinalizer::finalize() with the selected request identifier
  -> derive final status and outcome
  -> optional Stopwatch stop
  -> HttpRequestMetricsRecorder::record()
  -> HttpAccessLogWriter::write()
  -> HttpRequestSpanRecorder::finish()
  -> KernelRuntimeInterface::afterUnitOfWork() and Kernel-owned reset
  -> return final ResponseInterface or rethrow primary Throwable
```

A response returned by the selected outermost error boundary is a completed pipeline response and MUST pass through the same `HttpResponseFinalizer` and request-level observability sequence as any normally returned or short-circuit response.

Ordinary terminal system-endpoint decisions are observed by `FallbackRouter` through the canonical meter port. `DebugRouter` separately observes completed eligible debug decisions through `http.debug_total` and the fixed blocked warning `http.debug.blocked`, with independent metric and logging enablement and failure isolation. Both policies are governed by `docs/ssot/http-system-endpoints.md` and `docs/ssot/observability.md`. These decisions MUST NOT establish the final HTTP status or request outcome, replace request-level observability, or introduce another lifecycle boundary. Debug decisions MUST NOT be duplicated through `http.system_endpoint_total`.

The finalizer owns independently enabled application of the Kernel-owned correlation response header, the HTTP-owned request-id response header, and configured security headers through immutable PSR-7 `withHeader()`.

Correlation response-header finalization MUST precede request-id identity verification and response-header finalization. Security-header application MUST follow successful identity-header finalization and precede final HTTP status derivation and request-level observability.

Only the status of the successfully finalized response determines the HTTP result: statuses below `400` are `success`; finalized `400..599` responses are `handled_error`. An uncaught request identity preparation, pipeline, or response-finalization Throwable is `fatal_error`, with observability status fixed to `unknown`. A merely intermediate pipeline response MUST NOT be reported as final if response finalization fails.

`HttpKernel` MUST derive one bounded request method, final status, outcome, and non-negative duration for all enabled request-level recorders. Metrics and access logging MUST consume these final values, not an intermediate response, raw request data, or a middleware-local outcome. Recording failures MUST be isolated so that they do not replace the final response or primary Throwable. `afterUnitOfWork()` MUST still be attempted once after every successful begin, following request-level completion and before Kernel-owned reset.

If request identity preparation fails after successful UnitOfWork begin, the PSR-15 pipeline and response finalization MUST NOT execute.

If the pipeline fails without returning a response, response-header finalization MUST NOT execute.

A successful pipeline return does not guarantee a successfully completed HTTP invocation. An uncaught finalization failure MUST prevent returning an intermediate response.

A subsequent Kernel completion or reset failure MUST prevent returning a finalized response when that failure is authoritative under canonical failure precedence.

HTTP completion observations recorded before Kernel completion MUST NOT be retroactively reclassified or emitted again because reset fails. Kernel completion and reset failure precedence remain governed by `docs/ssot/uow-outcome-policy.md`.

A failed reset attempt MUST NOT be treated as proof of successful context cleanup.

## Dedicated HTTP completion services (MUST)

The four HTTP-owned services are distinct, stateless DI services and MUST NOT be registered in any of the nine HTTP middleware slots:

- `HttpResponseFinalizer` owns independently enabled correlation, request-id, and security-header response finalization through the existing `CorrelationIdProviderInterface`, mandatory shared `RequestIdPolicy`, and mandatory shared `SecurityHeadersPolicy`, including exact invocation-local request identity verification and deterministic security-header mutation order.
- `HttpRequestMetricsRecorder` owns final-response request metrics through `MeterPortInterface`, using only approved bounded labels.
- `HttpAccessLogWriter` owns at most one safe access-log operation through the existing PSR-3 `LoggerInterface`, without exporting raw request values.
- `HttpRequestSpanRecorder` owns the request-local `http.request` span start and finish through `TracerPortInterface` and `SpanInterface`.

`RequestIdPolicy` is a separate HTTP request identity preparation and owner-local verification policy dependency. It is not a fifth completion-observability service or an additional completion stage.

`SecurityHeadersPolicy` is a separate immutable configuration dependency of `HttpResponseFinalizer`, not a completion service, middleware registration, observer, or additional completion stage.

These services MUST NOT become PSR-15 response decorators, create a parallel post-response observer chain, or retain request-local response/span/timer state in shared services. Inbound `TraceContextMiddleware` remains a separate PSR-15 concern and does not create a second tracing lifecycle.

## Short-circuit and Throwable semantics (MUST)

- A request-phase middleware MAY return a `ResponseInterface` without invoking its next handler. All post decorators already entered outside that middleware MUST still decorate that ordinary returned response in canonical unwind order.
- A post decorator MAY itself short-circuit. Only post decorators already entered outside that point may decorate the returned response; middleware not entered MUST NOT execute.
- A downstream `Throwable` MUST propagate unchanged through the ordinary PSR-15 chain. Post decorators MUST NOT be assumed to receive a `ResponseInterface` when their downstream handler throws.
- When an explicitly selected outermost Problem Details boundary captures a downstream Throwable, its resulting response is produced outside the ordinary post-decorator unwind. It MUST still pass through `HttpKernel` final-response completion.
- If the boundary cannot produce a response and propagates a `Throwable`, `HttpKernel` MUST retain the primary Throwable and preserve Kernel completion precedence. HTTP middleware composition MUST NOT add another exception normalization or rendering path.

## Security and state invariants (MUST)

Final-response metrics, access logs, and span attributes MUST use safe, bounded canonical fields. Raw paths, query strings, headers, cookies, credentials, request or response bodies, arbitrary context, and exception messages MUST NOT be added merely because a middleware or response was observed.

`Pipeline`, `HttpMiddlewareStackBuilder`, `RequestIdPolicy`, `SecurityHeadersPolicy`, `HttpsRedirectPolicy`, `HttpsRedirectMiddleware`, `MaintenanceMiddleware`, `MaintenanceStateStore`, the four completion services, and `HttpKernel` MUST NOT retain mutable per-request state or install additional reset handlers. Source and compiled execution MUST preserve response identity, ordinary short-circuit decoration, and original Throwable precedence under the same effective configuration.

Maintenance runtime state MUST remain filesystem-backed and application-root-local. Maintenance services MUST NOT read or write ContextKeys or ContextStore, introduce request-local attributes, or reset runtime maintenance state at UnitOfWork completion. Maintenance-decision diagnostics MUST NOT expose raw runtime state contents or filesystem details; only the bounded fields defined by `docs/ssot/observability.md` are permitted.

## Enforcement rails

Integration verification MUST cover deterministic per-slot ordering and deduplication, cross-slot failures, exact metadata and toggles, selected/unselected priority-`1000` boundary placement, returned short-circuit response unwind, exception conversion as a separate path, final-response completion and outcome classification, and source/compiled container parity.

Terminal integration verification MUST cover canonical system-endpoint dispatch, guarded development diagnostics and canonical forbidden error flow, unmatched empty-body `404` responses, earlier middleware precedence, exact maintenance liveness bypass without a debug bypass, mandatory shared endpoint service wiring, source/compiled container parity, effective clock and PSR-17 factory bindings, original Throwable propagation, and independent system-endpoint and debug-decision observability according to `docs/ssot/http-system-endpoints.md`.

Tests MUST distinguish an intermediate middleware result from the completed `HttpKernel` result and MUST NOT treat optional observability failures as permission to change responses or primary Throwables.

Response hardening verification MUST cover independent feature enablement, mandatory shared policy injection, deterministic immutable header mutation, case-insensitive replacement, preservation of unrelated response data, policy validation, all successfully returned pipeline response paths, and canonical fatal completion on mutation failure.

HTTPS redirect verification MUST cover exact registration and conditional participation, same-slot deduplication, actual trusted-proxy handoff, malformed metadata fallback, independence from context enrichment, explicit authority validation, method and status restrictions, encoded Location preservation and rejection, downstream invocation counts, and bounded independently isolated redirect-decision observations.

Maintenance verification MUST cover exact registration and metadata, conditional participation, disabled manual participation, same-slot deduplication, application-root-local state resolution, shared declarative dependencies, source/compiled container parity, strict message validation, safe filesystem operations, repeated runtime mutations without rebuilding services, and application-root isolation.

Maintenance HTTP integration verification MUST cover deterministic response construction, configured and message-specific retry delays, HEAD behavior, invalid-message fallback, unavailable-state fail-closed behavior, exact liveness delegation, precedence over downstream request middleware, applicable post-decorator traversal, independent maintenance-decision observability, canonical security-header finalization, original Throwable precedence, and unchanged Kernel reset discipline.

Maintenance CLI integration verification MUST cover canonical command discovery and execution, independent HTTP factory readiness, rejection of target overrides, safe structured output, runtime activation shared across participating HTTP targets, and unchanged Kernel fingerprint and generated artifacts.

Sequential HTTP UnitOfWork regression MUST exercise an independent sequence of 1000 deterministic sequential invocations for each request-id policy configuration:

- `http.request_id.enabled=false`;
- `http.request_id.enabled=true`.

Each sequence MUST execute in one PHP process through one shared `HttpKernel`, concrete `KernelRuntime`, `Pipeline`, mandatory shared `RequestIdPolicy`, `ContextStore`, and Foundation `ResetOrchestrator`, without rebuilding those runtime instances between invocations.

Verification MUST use the existing `RequestContextMiddleware`, deterministic test-only request identifier generation, and canonical Foundation reset discovery for registered test-only `ResetInterface` services.

It MUST NOT introduce another UnitOfWork lifecycle, context store, reset executor, or HTTP-specific reset wrapper.

Each independent sequence MUST cover exactly:

- 250 completed HTTP `200` responses (`success`);
- 250 completed HTTP `503` responses (`handled_error`);
- 250 uncaught pipeline Throwables (`fatal_error`);
- 250 response-finalization Throwables caused by correlation-header mutation failure (`fatal_error`).

Each outcome MUST be verified through the canonical Kernel after-UoW phase rather than inferred solely from HTTP status.

Both sequences MUST enable correlation response-header finalization and verify exactly 750 correlation-header mutation attempts per sequence.

Request-id response-header mutation MUST be attempted exactly 500 times in the enabled request-id sequence and zero times in the disabled request-id sequence.

Request-id response-header mutation MUST NOT occur after pipeline failure or correlation-header finalization failure.

An exceptional HTTP invocation MUST NOT prevent the next sequential invocation from executing its expected lifecycle when canonical reset succeeds.

During request processing, Kernel-owned UnitOfWork identity and validated request-local HTTP context enrichment MUST reflect the current invocation.

When request identity is enabled, the regression MUST verify:

- the current invocation identifier is present in `ContextKeys::REQUEST_ID` before middleware context enrichment;
- eligible after-UoW hooks observe the selected identifier before reset;
- successfully finalized responses contain the exact selected request identifier;
- subsequent invocations do not inherit a previous request identifier.

When request identity is disabled, `ContextKeys::REQUEST_ID` MUST remain absent throughout conforming execution paths, including before middleware enrichment and during eligible after-UoW hooks. Request-id response-header mutation and identifier generation MUST NOT occur.

After each successful canonical reset in either configuration, every canonical `ContextKeys` entry, including `ContextKeys::REQUEST_ID`, MUST be absent and each registered resettable test service MUST have its request-local state cleared.

Before subsequent HTTP enrichment, optional HTTP context enrichment keys from the preceding request MUST remain absent. Kernel-owned base keys and enabled HTTP request identity for the new UnitOfWork are established independently.

Identifier and header verification MUST use strict boolean predicates with fixed safe assertion messages. Actual request identifiers, raw header values, and complete context snapshots MUST NOT appear in assertion diagnostics.

For each UnitOfWork crossing the Kernel reset-responsibility boundary, verification MUST establish exactly one canonical reset attempt. A reset attempt MUST NOT be treated as successful cleanup when reset execution fails. Reset-responsibility boundaries, lifecycle failure precedence, effective reset discovery, and reset ordering remain Kernel/Foundation-owned. The regression establishes isolation only for examined canonical context keys and registered resettable-service state; it does not establish process-wide memory-leak freedom, cleanup of unregistered mutable state, or transport-adapter and Worker integration readiness.

Existing middleware ordering, outermost error-boundary placement, completed-response observability, Throwable precedence, and HTTP completion contracts MUST remain unchanged.

## Non-goals

This SSoT does not introduce or redefine:

- reserved tags, priority registries, config roots, or `http.middleware.auto.*` flags;
- general application route matching, application endpoints, route admission, general application endpoint authorization, routing delegation contracts, transport-specific request creation, or response emission;
- debugging middleware, debug-specific slots, priorities, or an alternative terminal dispatcher;
- a second DI container, middleware resolver, observer pipeline, lifecycle hook registry, or reset implementation;
- a replacement canonical error handler, `ErrorDescriptor` model, Problem Details renderer, or diagnostic redaction boundary;
- metrics aggregation, logging backend, tracing exporter, span-parent linkage contract, or mutable request cache;
- optional middleware implementations or automatic activation based on installed packages;
- system endpoint implementation through maintenance middleware, target-specific maintenance activation, distributed maintenance-state synchronization, or crash-transactional filesystem updates.

## Cross-references

- [SSoT Index](./INDEX.md)
- [HTTP Middleware Catalog SSoT](./http-middleware-catalog.md)
- [HTTP System Endpoints SSoT](./http-system-endpoints.md)
- [Tag Registry](./tags.md)
- [DI Tags and Middleware Ordering SSoT](./di-tags-and-middleware-ordering.md)
- [Runtime Container Definitions (SSoT)](./runtime-container-definitions.md)
- [Artifacts and Fingerprint Behavior SSoT](./artifacts-and-fingerprint.md)
- [Config and env SSoT](./config-and-env.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [Context Keys SSoT](./context-keys.md)
- [Context Store SSoT](./context-store.md)
- [Time, IDs, and Duration SSoT](./time-ids-and-duration.md)
- [Context Lifecycle SSoT](./context-lifecycle.md)
- [Reset Tags SSoT](./reset-tags.md)
- [Stateful Services SSoT](./stateful-services.md)
- [UnitOfWork Outcome Policy SSoT](./uow-outcome-policy.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Problem Details SSoT](./problem-details.md)
- [Compile-time package dependencies](../architecture/DEPENDENCIES.md)
