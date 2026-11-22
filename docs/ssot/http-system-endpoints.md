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

This document is the Single Source of Truth for Coretsia terminal HTTP system-endpoint behavior, including active endpoint dispatch, reserved endpoint fallback responses, guarded development-only diagnostics, method and path eligibility, deterministic response construction, and endpoint-decision observability.

The implementation boundary is:

```text
packages/platform/http/src/Routing/FallbackRouter.php
packages/platform/http/src/Routing/FallbackResponses.php
packages/platform/http/src/Routing/FallbackRouterHandler.php
packages/platform/http/src/Debug/DebugGuard.php
packages/platform/http/src/Debug/DebugRouter.php
packages/platform/http/src/Debug/DebugHeadersHandler.php
packages/platform/http/src/Debug/DebugModuleListHandler.php
packages/platform/http/src/Debug/DebugConfigHandler.php
packages/platform/http/src/Debug/DebugCacheHandler.php
packages/platform/http/src/Exception/IntentionalErrorException.php
packages/platform/http/src/Exception/DebugForbiddenException.php
packages/platform/http/src/Error/HttpExceptionMapper.php
packages/platform/http/src/Provider/HttpServiceFactory.php
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

- `FallbackRouter` owns canonical declaration selection and ordinary system-endpoint decision metric emission.
- `DebugRouter` owns exact development endpoint dispatch and debug-decision observability; `DebugGuard` owns eligibility and authorization, and the four diagnostic handlers own bounded public projections.
- `FallbackResponses` owns deterministic terminal response construction for matched endpoints and unmatched requests.
- `FallbackRouterHandler` implements `RequestHandlerInterface` and delegates to `FallbackRouter` and `FallbackResponses`.

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
- `DebugGuard`: constructed by `HttpServiceFactory::debugGuard()` from `ConfigRepositoryInterface`, `RuntimeDiagnosticsInput`, `ModulePlan`, and the shared `TrustedProxyPolicy`.
- `DebugHeadersHandler`: `SensitiveDataRedactorInterface` and `FallbackResponses`.
- `DebugModuleListHandler`: `ModulePlan` and `FallbackResponses`.
- `DebugConfigHandler`: `ConfigRepositoryInterface`, `DebugGuard`, `SensitiveDataRedactorInterface`, `SensitiveKeyClassifierInterface`, and `FallbackResponses`.
- `DebugCacheHandler`: `RuntimeDiagnosticsInput` and `FallbackResponses`.
- `DebugRouter`: `DebugGuard`, all four diagnostic handlers, `MeterPortInterface`, `LoggerInterface`, and the validated `http.metrics.enabled` and `http.access_log.enabled` booleans.
- `FallbackRouter`: `FallbackResponses`, `ClockInterface`, `MeterPortInterface`, the validated `http.metrics.enabled` boolean, and `DebugRouter`.
- `FallbackRouterHandler`: `FallbackRouter` and `FallbackResponses`.

The canonical `RequestHandlerInterface` alias MUST resolve to `FallbackRouterHandler`. Clock, meter, and PSR-17 factory references MUST consume the effective container bindings. The provider MUST preserve the canonical Foundation clock binding and application-owned binding overrides without constructing a separate clock or PSR implementation.

Endpoint services MUST NOT discover modules, probe container services, resolve dependencies through a service locator, or introduce configuration outside the explicitly defined endpoint policy. Kernel owns the mandatory runtime seeds; HTTP MUST NOT construct replacement seeds or consume bootstrap-only services. The required Redaction runtime dependency supplies the canonical redaction and sensitive-key classification ports independently of debug activation. Direct production source dependencies remain governed by [Architecture Dependencies](../architecture/DEPENDENCIES.md); other platform implementations participate only through canonical contracts and provider composition.

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

## Development-only terminal endpoints (MUST)

The exact `/_debug` and prefix `/_debug/` declarations MUST retain status `dev_only` and owner `platform/http`. Only the canonical slash-terminated prefix declaration permits delegation from `FallbackRouter` to `DebugRouter`. The latter MUST claim only the exact case-sensitive method `GET` and original URI paths:

- `/_debug/headers`;
- `/_debug/modules`;
- `/_debug/config`;
- `/_debug/cache`.

The exact reserved root, unknown operations, missing or repeated leading slashes, trailing slashes, case variants, and encoded aliases MUST remain unmatched even when the canonical matcher recognizes a reservation.

### Eligibility and access policy

All of the following MUST hold for debug eligibility:

- `http.debug.endpoints.enabled` is `true`;
- the Kernel-owned immutable `RuntimeDiagnosticsInput::appEnv()` value is exactly `local` or `dev`;
- the runtime `ModulePlan` contains enabled `platform.problem-details`.

The environment MUST come from Kernel's resolved bootstrap environment in source mode or validated `config@1` metadata in artifact-only mode. Process environment, merged configuration defaults, subsequent generation selection, and installed but unselected packages MUST NOT override eligibility. Ineligible requests MUST remain unmatched without throwing a forbidden exception.

For an eligible recognized operation, `DebugGuard` MUST require the enabled operation, an allowlisted canonical client IP, a non-empty configured token digest, and exactly one valid `X-Debug-Token` header value. Header-name comparison is case-insensitive. The token MUST consist of exactly 64 lowercase hexadecimal characters representing 32 cryptographically secure random bytes. Only its lowercase SHA-256 digest belongs in configuration. The guard MUST compare the configured digest to the SHA-256 digest of the supplied token using `hash_equals()`. Missing, malformed, duplicate, or incorrect tokens MUST NOT authorize access.

Client identity MUST reuse `TrustedProxyPolicy::canonicalIp()` and exact address equality:

- the immediate peer MUST be a valid canonical `REMOTE_ADDR`;
- an untrusted peer MUST be authorized only by its own address, ignoring forwarding headers and proxy metadata;
- a configured trusted peer requires enabled `http.proxy.enabled` processing and a valid canonical `client_ip` from the request attribute owned by `TrustedProxyMiddleware`;
- missing or invalid peer or client identity, missing validated metadata for a trusted peer, or disabled proxy processing for a trusted peer MUST deny access without falling back to the peer address;
- the resulting client address MUST be in the exact debug IP allowlist.

### Configuration policy

The effective target-local configuration has the following defaults:

```php
'debug' => [
    'endpoints' => [
        'enabled' => false,
    ],
    'allowlist' => ['127.0.0.1', '::1'],
    'token_sha256' => '',
    'paths' => [
        'enabled' => ['headers', 'modules', 'config', 'cache'],
    ],
    'config' => [
        'allowlist' => [],
    ],
],
```

- `http.debug.allowlist` MUST be a list of at most 64 unique canonical IPv4 or IPv6 addresses. CIDRs, wildcards, hostnames, ports, duplicate addresses, and noncanonical spellings are invalid. An empty list denies all clients.
- `http.debug.token_sha256` MUST be empty or exactly 64 lowercase hexadecimal characters. An empty digest denies access.
- `http.debug.paths.enabled` MUST be a list of unique operation names from `headers`, `modules`, `config`, and `cache`. An empty list denies all recognized eligible operations.
- `http.debug.config.allowlist` MUST be a list of at most 64 unique logical key prefixes. Both prefixes and requested keys MUST consist of a root matching `[a-z][a-z0-9_]{0,63}` followed by zero or more dot-separated segments matching `[A-Za-z_][A-Za-z0-9_]{0,63}`, with a total limit of 256 bytes. A prefix authorizes only the exact key and dot-separated descendants. An empty list denies config diagnostics.

Structural configuration validation and mandatory shared guard semantic validation MUST apply independently of debug enablement. Invalid values MUST NOT be silently normalized or accepted. No configuration value-disclosure toggle is permitted.

### Forbidden error

A recognized eligible operation denied by the guard or the config selector and sensitive-key policy MUST throw `DebugForbiddenException::forbidden()` with the exact message `CORETSIA_HTTP_DEBUG_FORBIDDEN: http-debug-forbidden`. The exception MUST NOT accept caller-supplied messages, request data, or a previous Throwable.

The existing `HttpExceptionMapper` contribution through `error.mapper` at priority `100` with empty metadata MUST map this exception to:

- `code`: `CORETSIA_HTTP_DEBUG_FORBIDDEN`;
- `message`: `http-debug-forbidden`;
- `severity`: `ErrorSeverity::Error`;
- `httpStatus`: integer `403`;
- `extensions`: `[]`.

The canonical error handler MUST own normalization, redaction, and eligible reporting. The explicitly selected Problem Details boundary MUST render the normal RFC9457 response with HTTP status `403`, `Content-Type: application/problem+json`, `Cache-Control: no-store`, and exactly the following JSON body followed by one LF:

```json
{"status":403,"title":"Forbidden","type":"about:blank"}
```

The public response MUST NOT disclose the internal code, reason, guard decision details, credentials, configuration, or request data. Existing error-boundary failure and original Throwable preservation policies remain applicable. No alternative mapper or renderer is permitted.

### Diagnostic response contracts

All successful debug responses MUST use HTTP status `200`, `Content-Type: application/json`, `Cache-Control: no-store`, and the canonical compact stable JSON encoding with exactly one final LF.

#### Headers

`GET /_debug/headers` MUST return exactly one `headers` map:

- `accept`, `content-type`, and `user-agent`: boolean presence flags;
- `authorization`, `cookie`, `set-cookie`, and `x-debug-token`: `null` when absent or a canonical Placeholder redaction summary when present.

Header presence MUST be determined case-insensitively. The corresponding summary kinds are `authorization`, `cookie`, `cookie`, and `token`. For example, the summary for a present Authorization header is:

```json
{"hash":null,"kind":"authorization","length":null,"mode":"placeholder","redacted":true,"schemaVersion":1}
```

The handler MUST use the canonical redactor with `RedactionMode::Placeholder` without supplying actual header bytes. No header values, arbitrary header names, sensitive lengths, hashes, or cardinalities may be disclosed.

#### Modules

`GET /_debug/modules` MUST return exactly a `modules` list containing the unique enabled module ids from the runtime `ModulePlan` in ascending byte order. Excluded and unselected modules MUST be absent. The handler MUST NOT discover Composer packages or manifests.

#### Config

`GET /_debug/config?key=<logical-path>` MUST accept exactly one literal selector in the original URI query. Encoded, repeated, malformed, array-valued, additional, and unauthorized selectors MUST be forbidden. The entire `http.debug` subtree and any sensitive key segment classified by the canonical `SensitiveKeyClassifierInterface` MUST be denied before repository value access.

An authorized key absent from the repository MUST remain unmatched without a completed debug decision. For an existing key, the handler MUST return exactly the following two-member map (example):

```json
{"source":{"precedence":0,"redacted":false,"type":"package_default"},"type":"int"}
```

- `type`: exactly one of `null`, `bool`, `int`, `string`, `list`, or `map`;
- `source`: `null` when effective source metadata is unavailable, or a map containing exactly non-negative integer `precedence`, boolean `redacted`, and the canonical `ConfigSourceType` string `type`.

The source type MUST be one of `package_default`, `application_config`, `app_config`, `dotenv`, `env`, `cli`, `runtime`, or `generated_artifact`.

The repository's `sourceOf()` MUST provide the canonical effective source in source-host and artifact-only runtimes, using hydrated metadata from the validated config artifact in the latter. The handler MUST NOT expose raw config values, logical key echoes, source ids, file paths, lengths, hashes, or collection counts. Only the bounded projection may enter the canonical redactor and stable JSON encoder; the Placeholder redaction result MUST exactly match that projection.

#### Cache

`GET /_debug/cache` MUST return exactly one `artifacts` map:

```json
{"artifacts":{"config":"loaded","container":"loaded","generation-manifest":"loaded","module-manifest":"loaded"}}
```

All four fixed identifiers MUST have status `loaded` only when Kernel has successfully validated and loaded the boot-selected generation. Otherwise all four MUST have status `unavailable`. The handler MUST consume only the immutable `RuntimeDiagnosticsInput` generation status, without reading the current pointer, scanning or inspecting artifact payloads, rediscovering generation identity, disclosing paths, generation ids, fingerprints, or hashes, or writing filesystem state.

## Unmatched requests (MUST)

An unsupported method, valid unmatched path, invalid matcher input, matched declaration without explicitly supported terminal behavior, ineligible debug request, or missing authorized config key MUST produce a non-matching dispatch result. When the terminal handler receives that result, it MUST return HTTP status `404` with exactly an empty body, without exception normalization.

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
- the maintenance bypass MUST NOT bypass unrelated middleware policies;
- development-only endpoint paths MUST NOT receive a maintenance bypass.

Terminal liveness normalization MUST NOT expand maintenance bypass eligibility. Maintenance response construction, state handling, and observability remain owned by the maintenance middleware according to [HTTP Middleware Wiring SSoT](./http-middleware-wiring.md).

## Endpoint-decision observability (MUST)

Ordinary system-endpoint decision observability is owned by `platform/http` through the terminal `FallbackRouter`, not an HTTP middleware. Metric names, types, allowed labels, and disclosure remain governed by [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md).

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

Unsupported methods, invalid matcher inputs, unmatched paths, debug operations, and requests short-circuited before terminal dispatch MUST NOT produce `http.system_endpoint_total` observations.

Meter failures MUST be isolated and MUST NOT be retried, replace a completed response, suppress the intentional Throwable, or interfere with HTTP request completion. An increment attempt does not establish successful backend recording; the canonical noop meter remains valid.

Endpoint-decision labels MUST NOT contain raw paths, queries, headers, credentials, request identifiers, exception messages, traces, configuration, or environment values.

### Debug-decision observability

`DebugRouter` MUST observe completed eligible debug decisions separately through `http.debug_total`, with exactly `operation` (`headers`, `modules`, `config`, or `cache`) and `outcome` (`allowed` or `blocked`). When `http.metrics.enabled` is `true`, each completed decision MUST attempt exactly one counter increment with value `1`. When `http.access_log.enabled` is `true`, each completed blocked decision MUST also attempt exactly one PSR-3 warning with event `http.debug.blocked` and only the producer-supplied context field `operation`.

Allowed decisions complete only after successful response construction. A blocked decision completes immediately before throwing or rethrowing the canonical forbidden exception. Unmatched or ineligible requests, missing authorized config keys, earlier middleware short-circuits, and unrelated handler or response construction failures MUST NOT produce completed debug-decision observations.

Counter emission controlled by `http.metrics.enabled` and warning emission controlled by `http.access_log.enabled` MUST remain independently enabled and failure-isolated without retries. Debug decisions MUST NOT be duplicated through `http.system_endpoint_total`. Exact measurement and disclosure policy is governed by [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md).

## Finalized HTTP request observability (MUST)

Endpoint decisions are independent of finalized HTTP request outcomes. A completed decision MUST NOT be invalidated or reclassified by subsequent middleware response decoration, response finalization, or request completion failures.

`HttpKernel` retains coordination of the canonical HTTP request metrics `http.request_total` and `http.request_duration_ms`, the `http.request` span, and the `http.request.completed` access log event. These retain their existing enablement, validated method, final status, duration, outcome, and correlation identity semantics.

The canonical finalized request outcome values remain:

```text
success
handled_error
fatal_error
```

A successfully finalized HTTP `200` response has outcome `success`. A successfully finalized HTTP `403`, `404`, `501`, `503`, or rendered HTTP `500` response has outcome `handled_error`.

An uncaught failure during request preparation, pipeline execution, response finalization, or final response status validation has outcome `fatal_error` with status `unknown`.

A subsequent Kernel completion or reset failure MUST NOT retroactively reclassify or repeat already recorded HTTP completion observations. Canonical failure precedence remains governed by [UnitOfWork Outcome Policy SSoT](./uow-outcome-policy.md).

Endpoint decisions MUST NOT replace or reinterpret these outcomes or the fixed `route_template = unknown` access-log value. The terminal services MUST NOT emit request counters, duration metrics, additional spans, or request completion logs. The fixed blocked debug warning is a decision event, not a request completion event.

## Security and redaction (MUST)

Ordinary endpoint bodies MUST contain only their specified fixed public values and, for ping, the injected integer timestamp. Reserved fallback diagnostics MUST NOT disclose actual owner installation or module selection, configuration, environment, backend availability, raw paths, query strings, headers, credentials, or request payloads. Debug bodies MUST contain only the explicitly bounded projections defined above.

Invalid matcher input MUST NOT be published through responses or endpoint observability. Declaration status and normalized path matching MUST NOT be interpreted as endpoint authorization.

Canonical shared error, logging, and diagnostic redaction remains governed by [Sensitive Data Redaction SSoT](./sensitive-data-redaction.md). Header and config diagnostics MUST reuse the canonical redaction ports in Placeholder mode. Redaction failure or incompatible output MUST fail closed through the existing error boundary. Terminal services MUST NOT introduce an alternative redactor or authorize disclosure of raw values. Debug endpoints MUST be restricted to private development access; raw tokens MUST NOT enter configuration, artifacts, logs, or metrics, and diagnostic responses MUST NOT be shared-cached.

## Context, UnitOfWork, and reset (MUST)

`FallbackRouter`, `FallbackResponses`, `FallbackRouterHandler`, `DebugRouter`, `DebugGuard`, and all four diagnostic handlers MUST remain final readonly stateless services. Request data, selected declarations, timestamps, responses, and Throwables MUST remain invocation-local.

`HttpKernel` MUST preserve one canonical Kernel UnitOfWork lifecycle per HTTP invocation and attempt the matching UnitOfWork completion exactly once after every successful begin, following applicable response finalization and HTTP completion observability.

Terminal endpoint services MUST NOT start or finish another UnitOfWork, read or write ContextStore, introduce context keys or request attributes, or retain mutable request-local state.

No terminal service may implement `ResetInterface`, register `kernel.reset` or `kernel.stateful`, participate in lifecycle hooks, or introduce another HTTP execution boundary.

## Enforcement rails

Integration tests MUST verify exact response bytes, content types, cache policy, canonical matching, unsupported methods, and precedence through the actual HTTP runtime in both source and compiled container modes.

Verification MUST cover deterministic injected-clock behavior, owner-independent reserved fallback responses, exact maintenance bypass, canonical error mapping and reporting independently of public error rendering, original Throwable propagation, immutable response mutation validation, and isolated metric failures.

Endpoint decision tests MUST preserve the canonical request-completion evidence, bounded labels, and one UnitOfWork per request without additional reset or lifecycle registrations.

Debug verification MUST cover the frozen runtime environment, explicit module selection, exact paths and guard restrictions, malformed configuration even when disabled, canonical proxy identity, exact safe projections, source and artifact-runtime parity, canonical forbidden mapping and rendering, boot-selected generation stability, and independently isolated completed-decision observations and failure paths.

## Non-goals

- General application routing, route-template analysis, route admission, or general application endpoint authorization.
- A competing system-route registry, reserved-path matcher, module discovery mechanism, or owner availability probe.
- Health checks, metrics rendering, backend collection, or activation of reserved owner capabilities.
- An alternative PSR implementation, clock binding, JSON serializer, error handler, error renderer, or redaction policy.
- Additional configuration roots or keys outside the defined endpoint policy, middleware registrations, context ownership, UnitOfWork boundaries, or reset orchestration.

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
- [Config and env SSoT](./config-and-env.md)
- [Artifact Generations SSoT](./artifact-generations.md)
- [ContextStore Lifecycle SSoT](./context-lifecycle.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [UnitOfWork Outcome Policy SSoT](./uow-outcome-policy.md)
- [Architecture Dependencies](../architecture/DEPENDENCIES.md)
- [ADR-0041: Platform HTTP runtime pipeline wiring](../adr/ADR-0041-platform-http-runtime-pipeline-wiring.md)
- [ADR-0042: System route table and reserved-path matching](../adr/ADR-0042-system-route-table-and-reserved-path-matching.md)
