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

# HTTP System Endpoints SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: platform/http
```

## Scope

This document is the Single Source of Truth for Coretsia terminal HTTP system-endpoint behavior, including active endpoint dispatch, reserved endpoint fallback responses, method and path eligibility, deterministic response construction, and endpoint-decision observability.

The implementation boundary is:

```text
packages/platform/http/src/Routing/FallbackRouter.php
packages/platform/http/src/Routing/FallbackResponses.php
packages/platform/http/src/Routing/FallbackRouterHandler.php
packages/platform/http/src/Exception/IntentionalErrorException.php
packages/platform/http/src/Error/HttpExceptionMapper.php
packages/platform/http/src/Provider/HttpServiceProvider.php
```

Declaration ownership, registry status, path matching, middleware ordering, error normalization, response finalization, and Kernel lifecycle retain their respective canonical authorities.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Provide deterministic terminal system endpoints and safe fallback responses for explicitly supported reserved endpoint capabilities through the canonical HTTP pipeline, without introducing general application routing or competing system-route ownership.

## Authority and ownership (MUST)

[System Route Table SSoT](./system-route-table.md) owns the declaration registry, canonical patterns, semantic owners, declaration statuses, and registry versioning. [Reserved Path Matching SSoT](./reserved-path-matching.md) owns concrete path normalization, matching, and invalid-input semantics.

`platform/http` owns the terminal dispatcher, deterministic fallback response construction, and integration with the canonical PSR-15 terminal handler:

- `FallbackRouter` owns explicit endpoint selection and endpoint-decision metric emission.
- `FallbackResponses` owns deterministic terminal response construction for matched endpoints and unmatched requests.
- `FallbackRouterHandler` implements `RequestHandlerInterface` and delegates to these two services.

The terminal handler MUST invoke `FallbackRouter::dispatch()` exactly once. It MUST return a non-null dispatched response unchanged or delegate unmatched response construction to `FallbackResponses::notFound()`. Throwables MUST propagate unchanged.

An `active` declaration identifies a materialized owner capability; it does not imply unconditional runtime exposure or authorization. A `reserved` declaration protects ownership without establishing that an owner endpoint is available. HTTP-owned fallback responses for reserved declarations do not materialize or activate the declared owner's endpoint.

## Terminal precedence and owner claims (MUST)

The terminal handler executes only when the canonical PSR-15 pipeline delegates to it. Middleware that returns a response without delegating MUST prevent terminal execution. Response decorators, the explicitly selected error boundary, and `HttpKernel` final-response completion retain their canonical positions and responsibilities.

An earlier middleware controlled by the declared owner MAY provide an explicitly authorized endpoint response. The terminal fallback MUST NOT replace that response or independently claim the owner's capability.

Only the owner declared by the canonical registry MAY implement or claim its reserved endpoint, subject to the applicable owner access policy. Neither earlier middleware precedence nor a terminal fallback response authorizes a competing application route, including a competing exact root route.

## Method and path eligibility (MUST)

Terminal dispatch MUST claim only the exact case-sensitive method `GET`. All other methods, including `HEAD`, `OPTIONS`, and differently cased method values, MUST return a non-matching dispatch result before URI access or reserved-path matching.

For an eligible method, `FallbackRouter::dispatch(ServerRequestInterface $request): ?ResponseInterface` MUST:

1. Obtain the original encoded URI path from `$request->getUri()->getPath()`.
2. Invoke `ReservedPathMatcher::match()` exactly once with that path.
3. Use the returned canonical declaration's `pattern`, `owner`, `type`, and `status` to select explicitly implemented endpoint behavior.
4. Return `null` when no declaration matches or the matched declaration has no explicitly implemented terminal behavior.

The dispatcher MUST NOT maintain a second declaration list, repeat slash normalization or prefix matching, decode or re-encode paths, or reinterpret the effective path between matching and dispatch. Query strings MUST NOT participate in endpoint matching.

The matcher owns normalization of empty paths and repeated or missing leading slashes. Case, trailing-slash distinctions, and opaque percent-encoded octets MUST retain their canonical matching semantics. An encoded alias MUST NOT become a matching declaration through decoding.

Only an `InvalidArgumentException` raised by the matcher with the fixed diagnostic `http-reserved-path-invalid` MUST be treated as a non-matching dispatch result. The catch boundary MUST cover only the matcher invocation; URI access, clock access, response construction, and endpoint execution MUST remain outside it. Unrelated exceptions, including those carrying the same diagnostic outside the matcher invocation, MUST propagate unchanged.

## Module participation and declarative DI (MUST)

`HttpServiceProvider` MUST provide shared, stateless services with explicit required dependencies and equivalent source and compiled container semantics:

- `FallbackResponses`: `ResponseFactoryInterface` and `StreamFactoryInterface`.
- `FallbackRouter`: `FallbackResponses`, `ClockInterface`, `MeterPortInterface`, and the validated `http.metrics.enabled` boolean.
- `FallbackRouterHandler`: `FallbackRouter` and `FallbackResponses`.

The canonical `RequestHandlerInterface` alias MUST resolve to `FallbackRouterHandler`. Clock, meter, and PSR-17 factory references MUST consume the effective container bindings. The provider MUST preserve the canonical Foundation clock binding and application-owned binding overrides without constructing a separate clock or PSR implementation.

Endpoint services MUST NOT discover modules, probe container services, resolve dependencies through a service locator, or introduce endpoint-specific configuration keys. Direct production source dependencies remain governed by [Architecture Dependencies](../architecture/DEPENDENCIES.md); other platform implementations participate only through canonical contracts and provider composition.

## Active terminal system endpoints (MUST)

The following behavior applies only to the explicitly supported exact declarations owned by `platform/http` with status `active`. Owner or status alone MUST NOT select a handler for an additional declaration.

All responses constructed for matched endpoints MUST set `Cache-Control: no-store` with that exact value. The specified status, content type, and body bytes describe the terminal response before middleware response decoration and canonical finalization.

### Home endpoint

`GET /` MUST return:

- HTTP status `200`;
- `Content-Type: text/html; charset=utf-8`;
- exact body `<h1>Hello Coretsia</h1>` followed by one LF (`\n`).

The empty path and repeated-slash root paths such as `///` normalize to the canonical exact root declaration. The declaration MUST NOT be interpreted as a wildcard.

### Ping endpoint

`GET /ping` MUST return HTTP status `200` with `Content-Type: application/json`.

The exact JSON map contains only:

- `ok`: boolean `true`;
- `ts`: integer Unix timestamp in seconds.

For example, a clock returning the Unix instant `1700000000` produces:

```text
{"ok":true,"ts":1700000000}
```

The JSON body MUST be compact and terminated by exactly one LF. Each claimed ping request MUST obtain its logical timestamp exactly once through the injected `Psr\Clock\ClockInterface::now()->getTimestamp()` and pass that value to `FallbackResponses::ping()`. The endpoint MUST NOT cache the timestamp, call native `time()`, construct a clock, or derive the timestamp from request data.

Foundation owns the canonical `ClockInterface` binding to `SystemClock`. An explicit test-owned binding may supply `FrozenClock` for deterministic assertions. Clock policy remains governed by [Time, IDs, and Duration SSoT](./time-ids-and-duration.md).

### Liveness endpoint

`GET /_alive` MUST return:

- HTTP status `200`;
- `Content-Type: text/plain; charset=utf-8`;
- exact body `ALIVE` without a trailing newline.

The dispatcher MUST reuse `SystemRouteTable::ALIVE_PATH` as the canonical liveness identifier. Terminal matching follows the canonical matcher and is independent of the exact maintenance bypass policy.

### Intentional error endpoint

`GET /error` MUST throw `IntentionalErrorException::intentional()` without constructing a terminal response.

The exception message MUST be exactly `CORETSIA_HTTP_INTENTIONAL_ERROR: http-intentional-error`, composed from the fixed code and reason token. The exception MUST NOT accept caller-supplied messages, request data, or a previous Throwable.

`HttpExceptionMapper` MUST map the exception to the canonical format-neutral `ErrorDescriptor` with:

- `code`: `CORETSIA_HTTP_INTENTIONAL_ERROR`;
- `message`: `http-intentional-error`;
- `severity`: `ErrorSeverity::Error`;
- `httpStatus`: integer `500`;
- `extensions`: `[]`.

The HTTP provider MUST contribute this mapper through `ReservedTags::ERROR_MAPPER` (`error.mapper`) at priority `100` with empty metadata. The canonical error handler owns normalization, shared redaction, and eligible reporting according to [Errors Boundary SSoT](./errors-boundary.md).

When `platform.problem-details` is explicitly selected in the HTTP target, its canonical outer error boundary MUST adapt the normalized descriptor through the RFC9457 Problem Details pipeline. A successfully constructed error response MUST use HTTP status `500`, `Content-Type: application/problem+json`, `Cache-Control: no-store`, and the following exact JSON body followed by one LF:

```text
{"status":500,"title":"Internal Server Error","type":"about:blank"}
```

Only those three RFC7807-compatible public members are allowed. The exception code, reason, trace, request data, and configuration MUST NOT appear in the public representation.

Without the selected Problem Details boundary, the original intentional Throwable MUST propagate through the canonical HTTP execution and completion boundary. HTTP MUST NOT synthesize a replacement error renderer. Error normalization, emergency response behavior, and original Throwable preservation remain governed by [Problem Details SSoT](./problem-details.md) and [Errors Boundary SSoT](./errors-boundary.md).

## Reserved endpoint fallback responses (MUST)

The terminal dispatcher MUST return the following fallback responses only for the explicitly supported canonical declarations whose owner, pattern, type, and status `reserved` match the corresponding behavior.

HTTP status `501` indicates that the reserved endpoint capability has not produced an owner-controlled response before terminal fallback. It MUST NOT be interpreted as proof that the owner's Composer package is missing, that its module is unselected, or that its backend is unavailable.

Neither installation nor selection of `platform.metrics` alone activates an HTTP metrics endpoint. The dispatcher MUST NOT discover owner availability through package installation, `ModulePlan`, container probing, renderer availability, or backend capability checks. A declaration that is not `reserved` MUST NOT receive a reserved endpoint fallback response.

### Health endpoint family

`GET /health`, `GET /health/`, and descendants matched by the canonical slash-terminated health prefix declaration owned by `platform/health` MUST return:

- HTTP status `501`;
- `Content-Type: application/json`;
- exact body `{"error":"owner missing"}` followed by one LF;
- `Cache-Control: no-store`.

The canonical segment-prefix boundary MUST be preserved. Paths such as `/healthz`, `/healthz/ready`, and `/health%2Fready` do not match the health endpoint family.

### Metrics endpoint

`GET /metrics` matched by the canonical exact declaration owned by `platform/metrics` MUST return:

- HTTP status `501`;
- `Content-Type: text/plain; charset=utf-8`;
- exact body `owner missing` followed by one LF;
- `Cache-Control: no-store`.

The exact declaration MUST NOT be treated as a prefix. `/metrics/` and descendant paths remain unmatched. The selected meter implementation does not change these dispatch semantics.

## Development-only reservations and unmatched requests (MUST)

The development-only exact `/_debug` and prefix `/_debug/` declarations remain reserved with status `dev_only`. The terminal dispatcher MUST NOT implement, delegate, activate, or authorize development endpoints for these declarations.

An unsupported method, valid unmatched path, invalid matcher input, or matched declaration without explicitly supported terminal behavior MUST produce a non-matching dispatch result. When the terminal handler receives that result, it MUST return HTTP status `404` with exactly an empty body.

The unmatched response MUST NOT receive endpoint-specific headers from `FallbackResponses`, including `Content-Type`, `Cache-Control`, or `Allow`. Terminal dispatch MUST NOT implement implicit HEAD aliases, automatic OPTIONS handling, HTTP `405` responses, or a separate error representation for unmatched requests. Canonical response finalization remains applicable.

## Deterministic response construction (MUST)

`FallbackResponses` MUST construct responses and streams through the injected PSR-17 factories. It MUST delegate JSON maps to the canonical Foundation `StableJsonEncoder::encodeStableMap()` with byte-order map sorting, list-order preservation, compact encoding, and exactly one final LF. It MUST NOT introduce another serializer or unsupported JSON payload values.

Every immutable PSR-7 response mutation MUST consume its returned value and validate that it implements `ResponseInterface`. Incompatible mutation results MUST raise `RuntimeException` with the fixed diagnostic `http-fallback-response-invalid`, including for unmatched response construction.

Original factory and mutation Throwables MUST propagate unchanged. The diagnostic MUST NOT contain response implementation names, payloads, or raw exception data.

`FallbackResponses` MUST NOT construct alternative PSR implementations, maintenance responses, or Problem Details responses. It MUST NOT independently add Date, Server, Content-Length, correlation-id, request-id, or security headers. `HttpKernel` and the canonical response finalizer retain response identity and security-header application responsibility according to [HTTP Middleware Wiring SSoT](./http-middleware-wiring.md).

## Maintenance precedence (MUST)

Maintenance middleware participation and runtime activation remain independent of terminal dispatch. When maintenance is active or its runtime state is unavailable, maintenance short-circuits with its canonical HTTP `503` response unless the exact original URI path is `SystemRouteTable::ALIVE_PATH`.

The bypass MUST compare the original URI path directly and case-sensitively, without calling the matcher or normalizing the path. The bypass is independent of the HTTP method and delegates to the downstream handler.

Consequently:

- exact `GET /_alive` MAY reach the terminal liveness response during active or unavailable maintenance state, provided no other middleware short-circuits;
- an unsupported method on the exact liveness path bypasses maintenance but remains unmatched by terminal dispatch;
- `//_alive`, `_alive`, `/_alive/`, case variants, and encoded aliases MUST NOT become eligible for maintenance bypass;
- a query string does not affect path comparison;
- the maintenance bypass MUST NOT bypass unrelated middleware policies.

Terminal liveness normalization MUST NOT expand maintenance bypass eligibility. Maintenance response construction, state handling, and observability remain owned by the maintenance middleware according to [HTTP Middleware Wiring SSoT](./http-middleware-wiring.md).

## Endpoint-decision observability (MUST)

Endpoint-decision observability is owned by `platform/http` through the terminal `FallbackRouter`, not an HTTP middleware. Metric names, types, allowed labels, and disclosure remain governed by [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md).

The canonical metric is:

```text
http.system_endpoint_total
```

This metric is a `counter` and MUST use exactly these canonical label combinations:

- `operation` = one of `home`, `ping`, `alive`; `status` = integer `200`; `outcome` = `served`.
- `operation` = one of `health`, `metrics`; `status` = integer `501`; `outcome` = `owner_unavailable`.
- `operation` = `error`; `status` = string `unknown`; `outcome` = `intentional_error`.

No other label keys, values, or combinations are permitted.

`FallbackRouter` MUST attempt exactly one `MeterPortInterface::increment()` call with value `1` per completed claimed endpoint decision when `http.metrics.enabled` is `true`. The counter MUST NOT be emitted when this configuration value is `false`.

The `served` and `owner_unavailable` decisions require successful response construction, including all required immutable response mutations. Clock and response construction failures MUST NOT be recorded as completed decisions.

When `http.metrics.enabled` is `true`, `FallbackRouter` MUST attempt the counter increment for the `intentional_error` decision immediately before throwing the intentional exception. Its status is `unknown` because the terminal dispatcher has not produced an HTTP response.

Unsupported methods, invalid matcher inputs, unmatched paths, development-only reservations, and requests short-circuited before terminal dispatch MUST NOT produce endpoint-decision metrics.

Meter failures MUST be isolated and MUST NOT be retried, replace a completed response, suppress the intentional Throwable, or interfere with HTTP request completion. An increment attempt does not establish successful backend recording; the canonical noop meter remains valid.

Endpoint-decision labels MUST NOT contain raw paths, queries, headers, credentials, request identifiers, exception messages, traces, configuration, or environment values.

## Finalized HTTP request observability (MUST)

Endpoint decisions are independent of finalized HTTP request outcomes. A completed decision MUST NOT be invalidated or reclassified by subsequent middleware response decoration, response finalization, or request completion failures.

`HttpKernel` retains coordination of the canonical HTTP request metrics `http.request_total` and `http.request_duration_ms`, the `http.request` span, and the `http.request.completed` access log event. These retain their existing enablement, validated method, final status, duration, outcome, and correlation identity semantics.

The canonical finalized request outcome values remain:

```text
success
handled_error
fatal_error
```

A successfully finalized HTTP `200` response has outcome `success`. A successfully finalized HTTP `404`, `501`, `503`, or rendered HTTP `500` response has outcome `handled_error`.

An uncaught failure during request preparation, pipeline execution, response finalization, or final response status validation has outcome `fatal_error` with status `unknown`.

A subsequent Kernel completion or reset failure MUST NOT retroactively reclassify or repeat already recorded HTTP completion observations. Canonical failure precedence remains governed by [UnitOfWork Outcome Policy SSoT](./uow-outcome-policy.md).

Endpoint decisions MUST NOT replace or reinterpret these outcomes or the fixed `route_template = unknown` access-log value. The terminal services MUST NOT emit request counters, duration metrics, additional spans, endpoint log events, or access logs.

## Security and redaction (MUST)

Endpoint bodies MUST contain only their specified fixed public values and, for ping, the injected integer timestamp. The fallback diagnostic MUST NOT disclose actual owner installation or module selection, configuration, environment, backend availability, raw paths, query strings, headers, credentials, or request payloads.

Invalid matcher input MUST NOT be published through responses or endpoint observability. Declaration status and normalized path matching MUST NOT be interpreted as endpoint authorization.

Canonical shared error and logging redaction remains governed by [Sensitive Data Redaction SSoT](./sensitive-data-redaction.md). Terminal services MUST NOT introduce an alternative redactor or a debug disclosure policy.

## Context, UnitOfWork, and reset (MUST)

`FallbackRouter`, `FallbackResponses`, and `FallbackRouterHandler` MUST remain final readonly stateless services. Request data, selected declarations, timestamps, responses, and Throwables MUST remain invocation-local.

`HttpKernel` MUST preserve one canonical Kernel UnitOfWork lifecycle per HTTP invocation and attempt the matching UnitOfWork completion exactly once after every successful begin, following applicable response finalization and HTTP completion observability.

Terminal endpoint services MUST NOT start or finish another UnitOfWork, read or write ContextStore, introduce context keys or request attributes, or retain mutable request-local state.

No terminal service may implement `ResetInterface`, register `kernel.reset` or `kernel.stateful`, participate in lifecycle hooks, or introduce another HTTP execution boundary.

## Enforcement rails

Integration tests MUST verify exact response bytes, content types, cache policy, canonical matching, unsupported methods, and precedence through the actual HTTP runtime in both source and compiled container modes.

Verification MUST cover deterministic injected-clock behavior, owner-independent reserved fallback responses, exact maintenance bypass, canonical error mapping and reporting independently of public error rendering, original Throwable propagation, immutable response mutation validation, and isolated metric failures.

Endpoint decision tests MUST preserve the canonical request-completion evidence, bounded labels, and one UnitOfWork per request without additional reset or lifecycle registrations.

## Non-goals

- General application routing, route-template analysis, route admission, endpoint authorization, or development endpoint implementation.
- A competing system-route registry, reserved-path matcher, module discovery mechanism, or owner availability probe.
- Health checks, metrics rendering, backend collection, or activation of reserved owner capabilities.
- An alternative PSR implementation, clock binding, JSON serializer, error handler, error renderer, or redaction policy.
- Additional configuration roots or keys, middleware registrations, context ownership, UnitOfWork boundaries, or reset orchestration.

## Cross-references

- [SSoT Index](./INDEX.md)
- [System Route Table SSoT](./system-route-table.md)
- [Reserved Path Matching SSoT](./reserved-path-matching.md)
- [Routing and HttpApp Contracts SSoT](./routing-and-http-app-contracts.md)
- [HTTP Middleware Wiring SSoT](./http-middleware-wiring.md)
- [HTTP Middleware Catalog SSoT](./http-middleware-catalog.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Metrics Policy SSoT](./metrics-policy.md)
- [Errors Boundary SSoT](./errors-boundary.md)
- [Problem Details SSoT](./problem-details.md)
- [Sensitive Data Redaction SSoT](./sensitive-data-redaction.md)
- [Time, IDs, and Duration SSoT](./time-ids-and-duration.md)
- [Runtime Container Definitions SSoT](./runtime-container-definitions.md)
- [ContextStore Lifecycle SSoT](./context-lifecycle.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [UnitOfWork Outcome Policy SSoT](./uow-outcome-policy.md)
- [Architecture Dependencies](../architecture/DEPENDENCIES.md)
- [ADR-0041: Platform HTTP runtime pipeline wiring](../adr/ADR-0041-platform-http-runtime-pipeline-wiring.md)
- [ADR-0042: System route table and reserved-path matching](../adr/ADR-0042-system-route-table-and-reserved-path-matching.md)
