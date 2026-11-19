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

Scope: explicit target-local HTTP module participation and factory readiness, declarative HTTP DI bindings, nine reserved middleware slots, tagged discovery and manual middleware append lists, conditional request safety, optional HTTP request identity preparation, PSR-15 response decoration, request-local inbound W3C trace context, HTTP error descriptors, independently enabled correlation, request-id, and security-header response finalization, application-root-local maintenance mode and maintenance CLI commands, optional policy-based HTTPS redirects, bounded maintenance-decision, redirect-decision, and final-request observability, and canonical system-route declarations with concrete reserved-path matching.

Out of scope: SAPI request creation and response emission, a packaged front controller, general application route matching and system endpoint implementations, application/distribution Composer installation and DI provider registration, optional backend implementations, exception normalization and HTTP error rendering, Kernel driver-matrix policy, additional reset orchestration, and future HTTP features.

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
- `SystemRouteTable` as the canonical HTTP-owned versioned system-route declaration registry;
- `ReservedPathMatcher` for stateless concrete reserved-path matching against the canonical declarations;
- eight implemented `system_pre` middleware and their HTTP-owned request-safety, maintenance, and HTTPS redirect policies;
- `MaintenanceStateStore` and `MaintenanceMessage` for application-root-local maintenance activation and validated public message state;
- `MaintenanceEnableCommand`, `MaintenanceDisableCommand`, and `MaintenanceStatusCommand` for application-local maintenance operations through canonical CLI command registration;
- `RequestIdPolicy` for optional HTTP request identity preparation and owner-local final-response identity verification outside the PSR-15 middleware chain;
- `SecurityHeadersPolicy` for immutable configured security-header values and deterministic application through `HttpResponseFinalizer`;
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

## System route declarations and reserved-path matching

`Coretsia\Platform\Http\System\SystemRouteTable` is the canonical HTTP-owned versioned registry of system-route declarations and reserved path ownership.

`SystemRouteTable::entries()` returns the immutable canonical declaration list. Each entry contains exactly `owner`, `pattern`, `status`, and `type`. `SystemRouteTable::SCHEMA_VERSION` identifies the canonical declaration set, and `SystemRouteTable::ALIVE_PATH` identifies the exact liveness path `/_alive`.

Declaration status identifies reserved ownership independently of runtime endpoint availability:

- `active` identifies a materialized system-endpoint capability without implying unconditional runtime exposure;
- `reserved` protects a route declaration without asserting that an endpoint implementation exists;
- `dev_only` protects a development-only route declaration without activating its endpoint or authorizing access.

Each declaration identifies its semantic owner package. Endpoint implementation, module participation, runtime exposure, and access policy remain separate responsibilities.

`Coretsia\Platform\Http\System\ReservedPathMatcher` consumes `SystemRouteTable::entries()` as its only declaration source and exposes the canonical static API:

```php
public static function match(string $path): ?array
```

The input is an encoded concrete URI path component. The matcher returns the complete matching canonical declaration or `null` for a valid non-matching path.

The matcher normalizes an empty path to `/`, ensures exactly one leading slash, collapses consecutive slashes, and preserves trailing slashes, case, and valid percent-encoded octets without decoding them.

Matching semantics are:

- `exact` matches the complete normalized path using strict case-sensitive equality;
- `prefix` matches the complete slash-terminated canonical pattern as a segment-prefix boundary;
- the exact `/` declaration reserves only the normalized root path;
- `/health` and `/health/` are distinct exact and prefix declarations; `/healthz` does not match either declaration.

Invalid concrete path input raises `InvalidArgumentException` with the fixed diagnostic `http-reserved-path-invalid`.

Concrete reserved-path matching identifies reserved ownership only. It does not implement general application routing, route-template overlap analysis, route admission, endpoint dispatch, endpoint activation, authorization, or response construction.

The routing-free `FallbackRouterHandler` does not invoke `ReservedPathMatcher` and returns the safe empty `404` response. The registry and matcher introduce no additional runtime wiring, mutable state, ContextStore participation, observability, or reset registration.

Maintenance liveness bypass remains independent of reserved-path normalization and compares the original PSR-7 URI path directly against `SystemRouteTable::ALIVE_PATH`.

Canonical declaration and versioning requirements are governed by the [System Route Table SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/system-route-table.md). Concrete path normalization, matching, and consumer boundaries are governed by the [Reserved Path Matching SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/reserved-path-matching.md).

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
    'request_id' => [
        'enabled' => false,
        'header_name' => 'X-Request-Id',
        'accept_from_upstream' => false,
        'max_length' => 128,
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
    'maintenance' => [
        'enabled' => true,
        'retry_after_default_seconds' => 60,
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
    'security_headers' => [
        'enabled' => false,
        'x_content_type_options' => 'nosniff',
        'x_frame_options' => 'deny',
        'referrer_policy' => 'no-referrer',
        'permissions_policy' => '',
        'csp' => '',
    ],
    'https_redirect' => [
        'enabled' => false,
        'status' => 308,
        'allow_methods' => ['GET', 'HEAD'],
        'target_authority' => '',
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
- `http.request_id.enabled=false` by default; enabling it activates HTTP request identity preparation and completed-response request-id header application, independently of correlation response-header enablement.
- `http.request_id.header_name=X-Request-Id` is the default configured request-id header name used for both upstream acceptance and response-header application.
- `http.request_id.accept_from_upstream=false` by default; enabling it requires an explicitly trusted ingress boundary.
- `http.request_id.max_length=128` by default and must be an integer within the inclusive range `26..128`, measured in bytes for accepted upstream identifiers.
- `http.request_id.enabled` controls request identity preparation and response-header application only. It does not activate middleware, change declarative service registrations, alter middleware ordering, or introduce an additional reset lifecycle.
- `http.tracing.enabled=true` controls both inbound `TraceContextMiddleware` participation and the optional `http.request` span.
- `http.metrics.enabled=true` and `http.access_log.enabled=true` independently control request-level completion observability, HTTPS redirect-decision observations, and maintenance-decision observations.
- `http.maintenance.enabled=true` by default; it controls target-local maintenance middleware participation independently of the application-local runtime flag.
- `http.maintenance.retry_after_default_seconds=60` by default and must be an integer within the inclusive range `1..86400`. A valid public message may supply an explicit retry-delay override within the same range.
- `http.proxy.enabled=false` by default; `http.proxy.trusted_proxies` is an empty list of trusted immediate-peer IP/CIDR entries.
- `http.context.enrich.enabled=true` enables bounded request-derived enrichment through existing `ContextKeys`.
- `http.hosts.enabled=false` by default; `http.hosts.allowed` is an empty host allowlist.
- `http.security_headers.enabled=false` by default; enabling it activates canonical security-header application through `HttpResponseFinalizer`, independently of middleware participation.
- `http.https_redirect.enabled=false` by default; enabling it activates tagged HTTPS redirect participation.
- `http.https_redirect.status=308` by default and accepts only integer `307` or `308`.
- `http.https_redirect.allow_methods=['GET', 'HEAD']` by default and accepts only unique supported canonical uppercase methods. An empty list permits no redirects.
- `http.https_redirect.target_authority` defaults to an empty string and requires an explicitly configured canonical authority when redirect is enabled.
- `http.request.max_body_bytes=1048576` by default; the validated byte budget must be from `1` to `16777216`.
- `http.request.json.enabled=true` enables eligible JSON parsing; `http.request.json.max_depth=32` by default and must be from `1` to `512`.
- Unknown configuration keys, invalid values, and reserved `@*` directives are rejected through the existing Phase B configuration rules. HTTP-owned policies enforce additional semantic header, host, and proxy constraints.
- No `http.middleware.auto.*` flags, concrete implementation class names, route matching, transport adapters, or future-only HTTP feature defaults are introduced.

The eight implemented baseline `system_pre` registrations, in canonical priority order, are:

- `TraceContextMiddleware` — `930`, `['toggle' => 'http.tracing.enabled']`.
- `MaintenanceMiddleware` — `900`, `['toggle' => 'http.maintenance.enabled']`.
- `TrustedProxyMiddleware` — `880`, `['toggle' => 'http.proxy.enabled']`.
- `RequestContextMiddleware` — `870`, `['toggle' => 'http.context.enrich.enabled']`.
- `TrustedHostMiddleware` — `860`, `['toggle' => 'http.hosts.enabled']`.
- `HttpsRedirectMiddleware` — `850`, `['toggle' => 'http.https_redirect.enabled']`.
- `RequestBodySizeLimitMiddleware` — `830`, unconditional metadata `[]`.
- `JsonBodyParserMiddleware` — `800`, `['toggle' => 'http.request.json.enabled']`.

`ConfigKernel` Phase B supplies the validated target-local `http` subtree to canonical declarative DI through `ContainerDefinitionContext`. Other cataloged HTTP-owned middleware entries are future-only reservations, not implementations or default registrations. Optional middleware owners control their own module selection and DI contributions.

## Maintenance middleware

`MaintenanceMiddleware` provides application-root-local HTTP maintenance short-circuiting through the shared immutable `MaintenanceStateStore`.

It participates in `http.middleware.system_pre` at priority `900`, after `TraceContextMiddleware` and before trusted-proxy, request-context enrichment, trusted-host, HTTPS redirect, and request-body processing.

Tagged participation is controlled by `http.maintenance.enabled`, which defaults to `true`. The feature toggle does not itself activate maintenance.

Disabled maintenance middleware also delegates without accessing runtime state when explicitly included through a manual append list. Same-slot tagged and manual participation is deduplicated by the canonical middleware stack builder.

### Runtime activation and state

Maintenance activation is determined from the current application-local runtime flag on each participating request.

`MaintenanceStateStore` resolves the application root through the existing Kernel-owned `RuntimePathContext` and uses the fixed paths:

```text
var/maintenance/enabled
var/maintenance/message.json
var/maintenance/maintenance.lock
```

- `enabled` is the regular activation flag. Its presence activates maintenance; existing regular flag contents are not interpreted. Newly published flags contain zero bytes.
- `message.json` is the optional public maintenance message.
- `maintenance.lock` is the persistent Foundation scoped-lock anchor shared by cooperating HTTP and CLI operations.

The state store reuses Foundation `ScopedFileLock` and stable JSON primitives. Runtime state paths are not configurable and are not resolved relative to the current working directory.

The application root must be an existing accessible directory identified by a fully qualified absolute local path. The runtime state directory must be operator-controlled, and its `var` and `var/maintenance` components must be real directories, not symlinks. Participating HTTP and CLI processes require compatible filesystem permissions for directory inspection, optional message reading, and read/write opening of the shared lock anchor.

Runtime activation is shared by participating web and api targets using the same application root and filesystem. Each target independently controls static middleware participation through its effective `http.maintenance.enabled` configuration.

The runtime flag does not suspend CLI command execution or Worker processing.

Runtime maintenance mutations do not require `config:compile`, container rebuilding, or application process restart. The `var/maintenance` fingerprint exclusion and existing Kernel artifact ownership must be preserved. The configured artifact cache directory must not overlap the maintenance runtime state directory.

### Public maintenance message

The optional message uses the version-1 JSON object schema:

```json
{
  "schemaVersion": 1,
  "message": "Scheduled maintenance.",
  "retryAfterSeconds": 300
}
```

`schemaVersion` must be integer `1`, and `message` must be a string. Both fields are required. `retryAfterSeconds` is optional and must be omitted when no explicit retry-delay override is supplied.

The message must contain non-empty valid UTF-8 text of at most 1024 bytes without control characters, Unicode line separators, or leading/trailing whitespace.

An explicit retry delay must be an integer within the inclusive range `1..86400`.

Unknown fields, duplicate object keys, unsupported schema versions, invalid field types, malformed JSON, and JSON input exceeding 8192 bytes are rejected.

The message is serialized through canonical Foundation stable JSON encoding.

Maintenance message text is intentionally public HTTP response content. It must not contain secrets, credentials, personal data, internal paths, or other sensitive values.

### Maintenance response and liveness bypass

Confirmed inactive maintenance delegates to the downstream handler without maintenance-decision observations.

Active maintenance short-circuits ordinary requests before downstream middleware and terminal application handling, including HTTPS redirects.

The maintenance response has:

- HTTP status `503`;
- `Content-Type: text/plain; charset=utf-8`;
- `Cache-Control: no-store`;
- `Retry-After` containing the selected retry delay in decimal seconds;
- the validated public message followed by exactly one LF, or `Service Unavailable` followed by exactly one LF.

A valid message with an explicit `retryAfterSeconds` overrides `http.maintenance.retry_after_default_seconds`. A valid message without an override uses the configured default.

The exact `HEAD` request method returns an empty body with the same status and headers.

A missing, malformed, oversized, unreadable, or otherwise invalid optional message uses the generic response and configured default retry delay without deactivating maintenance.

Unavailable maintenance state fails closed using the same generic response and retry delay, rather than assuming inactive state.

The only automatic bypass is the exact case-sensitive comparison of the original PSR-7 URI `getPath()` value against `SystemRouteTable::ALIVE_PATH` (`/_alive`). Maintenance does not invoke `ReservedPathMatcher` or normalize the path. Query strings do not affect this comparison. Missing or repeated leading slashes, trailing slashes, additional segments, different casing, percent-encoded substitutes, and debug paths do not receive an automatic bypass.

The exact liveness bypass remains available when maintenance state is unavailable. It delegates exactly once to the downstream handler and returns its response unchanged or propagates its original Throwable.

Maintenance does not implement the liveness endpoint or guarantee its response status.

Successfully returned maintenance responses remain subject to applicable post decorators and canonical `HttpKernel` correlation, request-id, and security-header finalization. A successfully finalized `503` response retains the canonical `handled_error` outcome.

Response-construction and mutation failures retain the existing outermost error-boundary and Kernel Throwable precedence. Maintenance does not introduce another error pipeline, ContextStore mutation boundary, UnitOfWork, or reset operation.

### Maintenance commands

The maintenance commands are available when `platform.cli` and `platform.http` are selected for the Console target.

Run the commands through the canonical CLI launcher from the application root:

```bash
php coretsia maintenance:enable
php coretsia maintenance:enable --message="Scheduled maintenance."
php coretsia maintenance:enable --message="Scheduled maintenance." --retry-after=300
php coretsia maintenance:status --format=json
php coretsia maintenance:disable
```

The commands consume the same application-local state store as participating HTTP middleware. Command availability and execution are independent of HTTP middleware feature enablement and do not require executable HTTP response factories.

`maintenance:enable` accepts the optional non-repeatable `--message` and `--retry-after` options. `--retry-after` is allowed only together with `--message` and must contain a canonical positive decimal integer in `1..86400`, without signs, leading zeroes, whitespace, fractions, or exponent notation.

The maintenance commands accept no positional arguments or target overrides such as `--target`.

Do not supply sensitive values through command-line message arguments because shell history and process inspection may expose them.

Successful enablement and disablement return exit code `0` with the respective structured payload:

```json
{"enabled":true}
```

```json
{"enabled":false}
```

The status payload contains:

- `enabled`: the current runtime flag state;
- `message_state`: `ignored` when inactive, or `absent`, `valid`, or `invalid` when active;
- `retry_after_seconds`: present only when an active valid message supplies an explicit retry delay.

Status reports runtime activation, not effective middleware participation for any individual HTTP target. It does not expose public message contents or filesystem paths.

Inactive state and active state with an absent or valid message return exit code `0`. Active state with an invalid message returns exit code `1` while retaining the structured status payload. Unavailable state returns exit code `1` and a fixed safe diagnostic instead of a fabricated status snapshot.

Structured JSON outputs remain inside the canonical `coretsia.cli-output@1` envelope.

Enable and disable operations are logically idempotent. Disablement removes the activation flag while retaining the optional message file. Enabling without a message removes a retained regular message file. Repeated identical enablement preserves existing flag and message files.

Filesystem operations may block while another cooperating operation holds an incompatible scoped lock. A reported operation failure does not guarantee rollback of already completed filesystem mutations. Check `maintenance:status` after an unsuccessful state-changing command.

Maintenance state is local to cooperating processes using the same application root and filesystem. It does not provide distributed state synchronization or crash-transactional durability.

Canonical maintenance execution and state boundaries are governed by the [HTTP Middleware Wiring SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/http-middleware-wiring.md).

## HTTPS redirect middleware

`HttpsRedirectMiddleware` provides optional HTTP-to-HTTPS redirects through the immutable shared `HttpsRedirectPolicy`.

It participates in `http.middleware.system_pre` at priority `850`, after maintenance, trusted-proxy, request-context, and trusted-host processing. Tagged participation is controlled by `http.https_redirect.enabled`. A maintenance response short-circuit prevents HTTPS redirect execution.

The middleware also respects disabled policy behavior when explicitly included through a manual append list. Same-slot tagged and manual participation is deduplicated by the canonical middleware stack builder.

Only the effective `http` scheme and explicitly allowed request methods are eligible for redirection.

When proxy participation is enabled, the middleware consumes the canonical request-local metadata supplied by `TrustedProxyMiddleware`. Missing or invalid metadata falls back to the direct PSR-7 URI scheme. Redirect decisions do not depend on ContextStore enrichment.

Supported redirect statuses are `307` and `308`. The method allowlist accepts `GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, and `OPTIONS` as unique canonical uppercase values.

The destination authority must be configured explicitly. It accepts canonical DNS names, IPv4 addresses, bracketed IPv6 addresses, and optional explicit ports through the existing trusted-host authority grammar.

For example, override the following values in the target-local `http` configuration subtree:

```php
[
    'https_redirect' => [
        'enabled' => true,
        'status' => 308,
        'allow_methods' => ['GET', 'HEAD'],
        'target_authority' => 'secure.example:8443',
    ],
]
```

Location uses the configured authority and the original encoded request path and query. Empty paths become `/`. Request userinfo, fragments, source ports, and request-derived authorities are not copied into Location.

Malformed encoded URI components, raw whitespace, control characters, non-ASCII bytes, backslashes, and ambiguous path prefixes cause the redirect to be skipped.

A completed redirect response bypasses downstream request handling. Skipped decisions delegate to the downstream handler. Response construction failures retain canonical HTTP error-boundary behavior.

HTTPS redirect eligibility and authority validation remain governed by [HTTP Middleware Wiring SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/http-middleware-wiring.md).

## Security-header finalization

`HttpResponseFinalizer` applies optional configured security headers after successful correlation and request-id finalization, independently of middleware participation.

`SecurityHeadersPolicy` is its mandatory shared immutable configuration dependency. Security-header application does not occupy any of the nine middleware slots or require Problem Details module selection.

`SecurityHeadersPolicy` exposes an ordered map of configured non-empty header values when enabled. `HttpResponseFinalizer` applies these values in the following fixed mutation order:

1. `X-Content-Type-Options` — default configured value: `nosniff`.
2. `X-Frame-Options` — default configured value: `deny`.
3. `Referrer-Policy` — default configured value: `no-referrer`.
4. `Permissions-Policy` — default configured value: empty string.
5. `Content-Security-Policy` — default configured value: empty string.

Security headers are disabled by default.

For example, enable them through target-local HTTP configuration overrides:

```php
[
    'security_headers' => [
        'enabled' => true,
        'x_frame_options' => 'sameorigin',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'permissions_policy' => 'geolocation=()',
        'csp' => "default-src 'self'",
    ],
]
```

The effective merged configuration retains the remaining required defaults.

Supported frame options are `deny` and `sameorigin`, emitted as `DENY` and `SAMEORIGIN`.

Optional Permissions-Policy and CSP values accept empty strings. Non-empty values are limited to 4096 bytes of printable ASCII without leading or trailing whitespace.

Configured non-empty values replace matching existing response-header values case-insensitively through immutable PSR-7 `withHeader()` operations.

Empty optional settings preserve existing application-provided headers. A disabled policy does not perform security-header mutations.

The finalizer applies security headers to every successfully returned pipeline response, including ordinary responses, request-phase and post-decorator short-circuits, HTTPS redirects, returned maintenance responses, and mapped or emergency responses produced by the selected outermost error boundary.

Response status, body, Location, Content-Type, Cache-Control, and unrelated headers remain unchanged by security-header application.

The deterministic mutation sequence does not guarantee serialized PSR-7 header iteration or transport wire order.

Mutation failures propagate through the canonical HTTP fatal completion path. The finalizer does not synthesize fallback responses or execute another middleware traversal.

## HTTP request identity

`Coretsia\Platform\Http\RequestId\RequestIdPolicy` owns optional HTTP request identity preparation and final-response identity verification.

HTTP request identity is distinct from Kernel-owned correlation and UnitOfWork identities:

- `ContextKeys::REQUEST_ID` identifies the HTTP request when request identity preparation is enabled.
- `ContextKeys::CORRELATION_ID` carries the Kernel-owned correlation identity.
- `ContextKeys::UOW_ID` identifies the Kernel-owned UnitOfWork.

HTTP request identity does not replace or modify Kernel-owned identities. Disabling either HTTP identity-header policy does not disable the other policy or Kernel identity generation.

`HttpServiceProvider` registers one shared `RequestIdPolicy` through canonical Foundation declarative DI, independently of `http.request_id.enabled`. Both `HttpKernel` and `HttpResponseFinalizer` require the same policy instance as a mandatory non-nullable constructor dependency without a fallback or default instance.

The policy consumes the existing shared Foundation-owned `ContextStore` and the explicitly selected Foundation `UlidGenerator` service through `IdGeneratorInterface`. Changes to `foundation.ids.default` do not change HTTP request-id generation.

### Trusted upstream acceptance

Upstream request identifier acceptance is disabled by default.

Enabling `http.request_id.accept_from_upstream` requires an explicitly trusted ingress boundary that removes arbitrary client-supplied request-id headers or replaces them with owner-approved safe opaque identifiers.

Untrusted direct-client ingress must keep upstream acceptance disabled.

Header presence and identifier syntax do not establish upstream trust, semantic value safety, authenticated identity, or uniqueness. `RequestIdPolicy` does not perform per-request sender authentication, trusted-proxy detection, authorization, or authenticated identity resolution.

When request identity preparation is enabled:

- With upstream acceptance disabled, the policy generates an identifier without reading the configured request-id request header.
- With upstream acceptance enabled, the policy inspects the configured PSR-7 header through `ServerRequestInterface::getHeader()`.
- Exactly one valid string header value may be accepted.
- Missing or invalid upstream input is replaced by one locally generated Foundation ULID without rejecting the request solely because of the invalid identifier.
- A valid accepted identifier is preserved exactly and does not cause identifier generation.

Generated identifiers are canonical uppercase 26-character ULIDs.

### Identifier and header-name validation

The configured header name must be a non-empty ASCII HTTP header token of at most 128 bytes and match the following complete-name grammar case-insensitively:

```text
\A(?:[A-Za-z0-9]+-)*request-id\z
```

Unrelated HTTP header names and correlation-id header names are not accepted.

An accepted upstream identifier must have a byte length within the inclusive range `1..http.request_id.max_length` and match the following complete-value grammar without Unicode or case-insensitive matching modifiers:

```text
\A[A-Za-z0-9._-]+\z
```

Empty, multiple, non-string, comma-separated, whitespace-containing, control-character-containing, non-ASCII, oversized, or otherwise invalid effective header values are not accepted.

The policy does not assume a specific header-value array index, join multiple values, coerce non-string values, trim, normalize, decode, or change identifier case. Validation applies to effective PSR-7 header values, not original wire-level header bytes.

Generated identifiers must match the canonical ULID grammar:

```text
\A[0-7][0-9A-HJKMNP-TV-Z]{25}\z
```

The complete `http.request_id` configuration is structurally validated regardless of feature enablement. Semantic header-name and maximum-length validation also applies during policy construction when request identity preparation is disabled.

Invalid semantic configuration and invalid generator output fail deterministically using fixed safe reason tokens.

### Request identity ownership and preservation

`HttpKernel` invokes its mandatory shared `RequestIdPolicy::initialize()` after successful Kernel UnitOfWork begin and before the entire PSR-15 pipeline executes, including any selected outermost error boundary and post decorators.

The policy is invoked regardless of `http.request_id.enabled`. When disabled, `initialize()` immediately returns `null` without reading request headers, accessing `ContextStore`, or generating an identifier.

`RequestIdPolicy` is the sole authorized HTTP runtime writer of `ContextKeys::REQUEST_ID`. After successful enabled identity selection, it writes the selected identifier exactly once to the existing shared `ContextStore` and returns that exact value to `HttpKernel`.

`HttpKernel` retains the selected identifier only in an invocation-local variable and passes it to `HttpResponseFinalizer`.

Subsequent middleware, terminal handlers, and lifecycle hooks must not modify, replace, or remove the selected identifier. The policy may read back only its own `ContextKeys::REQUEST_ID` for final-response identity verification under the narrow exception defined by the [Context Store SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/context-store.md).

Other runtime context consumers use the canonical `ContextAccessorInterface`.

Request identity preparation is not PSR-15 middleware and does not occupy any of the nine middleware slots. It cannot be bypassed by a request-phase or post-decorator short-circuit response.

When disabled, the policy does not read request headers, access `ContextStore`, generate an identifier, or apply a request-id response header. Application-owned response headers remain unchanged by the disabled request-id policy.

The normative configuration, trust, validation, lifecycle, and response-header requirements are defined in the [HTTP Middleware Wiring SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/http-middleware-wiring.md).

## HttpKernel and UnitOfWork lifecycle

`HttpKernel::handle()` accepts one `Psr\Http\Message\ServerRequestInterface` and coordinates at most one PSR-15 pipeline execution per invocation. The pipeline executes exactly once when Kernel UnitOfWork begin and pre-pipeline request preparation succeed. It calls `KernelRuntimeInterface::beginUnitOfWork('http')` before the pipeline and attempts one matching `afterUnitOfWork()` with the exact handle after HTTP completion. The Kernel owns hook execution, context lifetime, and reset; HTTP never registers a second reset pipeline or implements UnitOfWork internals.

The fixed completion sequence after successful begin, when request preparation, pipeline execution, and response finalization succeed, is:

```text
KernelRuntimeInterface::beginUnitOfWork('http')
  -> request-local Stopwatch start and optional http.request span start
  -> RequestIdPolicy::initialize() and invocation-local identity selection
  -> complete PSR-15 Pipeline::handle()
  -> HttpResponseFinalizer::finalize() with the selected request identifier
  -> final HTTP status and outcome derivation
  -> optional request-local Stopwatch stop
  -> HttpRequestMetricsRecorder::record()
  -> HttpAccessLogWriter::write()
  -> HttpRequestSpanRecorder::finish()
  -> KernelRuntimeInterface::afterUnitOfWork() and Kernel-owned reset
  -> return finalized ResponseInterface or rethrow primary Throwable
```

`HttpKernel` retains the selected request identifier, response, Throwable, timing token, optional `SpanInterface` handle, and canonical method/status/outcome/duration only in invocation-local variables. HTTP-owned shared runtime services do not retain mutable request-local state. The selected request identifier is stored in the canonical Foundation-owned `ContextStore` until successful UnitOfWork reset. A valid Stopwatch token is stopped at most once; unavailable timing yields a safe integer zero duration.

A successfully finalized response with status below `400` is `success`; finalized `400..599` responses are `handled_error`, including normal short-circuits and responses returned by the outer error boundary. An uncaught request identity preparation, pipeline, or finalization Throwable is `fatal_error`, with fixed observability status `unknown` even if an intermediate response had been produced. Only the successfully finalized response supplies the final status. HTTP observations use one bounded method (`GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `TRACE`, `CONNECT`, or `OTHER`), status, outcome, and non-negative duration.

Timing and optional observability failures are independently isolated and cannot replace the final response or earlier primary Throwable. A primary request identity preparation, pipeline, or finalization Throwable remains authoritative over later completion failures; otherwise Kernel completion failure is preserved. The matching Kernel `afterUnitOfWork()` is attempted after every successful begin, including fatal HTTP completion paths.

An uncaught request processing failure before identity preparation prevents both identity preparation and pipeline execution. Failed request identity preparation prevents pipeline execution. An uncaught pipeline Throwable prevents response finalization. An uncaught response-finalization Throwable prevents returning an intermediate response. An authoritative Kernel completion or reset failure prevents returning the finalized response without retroactively reclassifying or repeating HTTP completion observations.

Sequential HTTP UnitOfWork isolation is verified by `HttpKernelInvokesKernelRuntimeUowHooksTest::testLongRunningSequentialRequestsPreserveIsolationAcrossOutcomes()`.

The integration regression executes two independent sequences of 1000 deterministic sequential HTTP invocations:

- `http.request_id.enabled=false`;
- `http.request_id.enabled=true`.

Each sequence runs in one PHP process using the same concrete `HttpKernel`, `KernelRuntime`, `Pipeline`, mandatory shared `RequestIdPolicy`, `ContextStore`, and Foundation `ResetOrchestrator` instances throughout the sequence.

Each sequence exercises four outcomes, 250 times each:

- Successful HTTP `200` responses with Kernel outcome `success`.
- Completed HTTP `503` responses with Kernel outcome `handled_error`.
- Uncaught pipeline Throwables with Kernel outcome `fatal_error`.
- Correlation-header finalization Throwables with Kernel outcome `fatal_error`.

Both sequences enable correlation response-header finalization and verify exactly 750 correlation-header mutation attempts per sequence.

The enabled request-id sequence verifies exactly 500 request-id header mutation attempts. The disabled request-id sequence verifies zero request-id header mutation attempts and zero request identifier generation calls.

Each invocation uses a new synthetic PSR-7 request with deterministic UnitOfWork and correlation identities and validated request-local HTTP metadata. Enabled request identity uses distinct deterministic test-only generated identifiers.

The regression verifies current-request context enrichment through the existing `RequestContextMiddleware`, request identity availability before middleware enrichment and during eligible after-UoW hooks when enabled, request identity absence when disabled, canonical Kernel after-UoW outcome delivery, exact finalized response identities, successful reset execution, and subsequent request recovery after exceptional HTTP completion.

Request-id response-header mutation is not attempted after pipeline failure or correlation-header finalization failure.

`KernelRuntime` and Foundation remain the sole owners of UnitOfWork lifecycle, reset responsibility, effective reset discovery, and reset execution. HTTP does not introduce another reset coordinator, lifecycle hook registry, or request-local state container. After successful canonical reset in either sequence, the tested canonical `ContextKeys`, including `ContextKeys::REQUEST_ID`, are absent and registered resettable-service state is cleared. A reset attempt or failed reset does not establish successful cleanup.

This regression verifies isolation of the examined request-local context and registered resettable services only. It does not prove the absence of unregistered process-wide mutable state or memory leaks, nor does it certify transport-adapter readiness, process recycling, or Worker HTTP task-source integration. The canonical enforcement requirements are defined in the [HTTP Middleware Wiring SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/http-middleware-wiring.md).

## Observability

`HttpServiceProvider` registers four separate, stateless HTTP-owned completion services with explicit constructor dependencies and injects them into `HttpKernel`. None is PSR-15 middleware, appears in the nine HTTP slots, creates another lifecycle hook, or registers independent reset logic.

- `HttpResponseFinalizer` uses `CorrelationIdProviderInterface`, the mandatory shared `RequestIdPolicy`, and the mandatory shared `SecurityHeadersPolicy` to apply independently enabled response-header policies to the fully returned immutable `ResponseInterface`. Correlation-header finalization precedes request-id verification and response-header application, followed by security-header application. The finalizer neither trusts arbitrary inbound correlation headers nor generates or replaces either identity.
- `HttpRequestMetricsRecorder` uses `MeterPortInterface` to increment `http.request_total` and observe `http.request_duration_ms`, with only the canonical `method`, `status`, and `outcome` labels and integer-millisecond duration. It does not aggregate or export metrics.
- `HttpAccessLogWriter` uses the canonical PSR-3 `LoggerInterface` to emit at most one bounded `http.request.completed` record when enabled. It consumes the final method, status, outcome, duration, optional validated Kernel-owned correlation id, and fixed `route_template='unknown'`. It does not read routes or infer templates from raw URIs, request attributes, or `ContextStore`.
- `HttpRequestSpanRecorder` uses `TracerPortInterface` to start and finish the request-local `http.request` span through the returned `SpanInterface`. It sets only safe, bounded method/status/outcome attributes; it does not record raw HTTP Throwables or retain span handles in service properties.

`TraceContextMiddleware` separately owns inbound W3C `traceparent` extraction through the existing `ContextPropagationInterface`. Accepted trace context remains request-local in the PSR-7 `coretsia.http.trace_context` attribute and is not written to a tracing `ContextStore`. The current `TracerPortInterface` has no explicit parent-context argument, so inbound extraction does not guarantee automatic parent-child linkage for the `http.request` span.

For request-id response-header application, `HttpResponseFinalizer` reads the current identifier through `RequestIdPolicy::current()` and requires strict equality with the identifier initially selected by `HttpKernel`.

Missing, invalid, or substituted enabled context identity, a missing invocation-local selected identity when enabled, or an inconsistent selected identity when disabled causes deterministic fatal completion with `http-request-id-context-invalid`. The finalizer does not repair context or regenerate identity.

When both identifiers are `null`, request-id response-header application is not applicable, but enabled security-header application still proceeds. When both are non-null and strictly equal, the finalizer applies the exact selected value through `ResponseInterface::withHeader()`, replacing any existing value for the configured request-id response header.

Disabled correlation response-header application, or an absent or invalid correlation identifier returned by the correlation provider, does not skip enabled request-id finalization. A correlation-header finalization failure prevents subsequent request-id response-header mutation.

A correlation or request-id finalization failure prevents subsequent security-header mutations.

`RequestIdPolicy` is a mandatory shared policy dependency, not a fifth completion service, additional observer, middleware registration, resettable service, or lifecycle hook.

The canonical Foundation noop-safe logging, tracing, and metrics ports preserve HTTP execution when optional backends are not selected. HTTP uses no second logging pipeline, redaction owner, recording tracer, metrics exporter, or external lifecycle telemetry. Kernel-owned UnitOfWork and operation observations remain separate from the HTTP-owned `http.request` span and the two HTTP request metrics.

Selected request identifiers and rejected upstream identifier values are not added to HTTP request metrics, metric labels, access-log context, request span attributes, span names, or error descriptor extensions.

Request identity preparation and verification do not introduce additional metrics, spans, logging events, or observability dependencies.

### Maintenance decisions

Enabled `MaintenanceMiddleware` observes maintenance decisions independently of final HTTP request completion.

- `http.metrics.enabled` controls the `http.maintenance_total` counter with exactly the `outcome` label.
- Allowed outcome values are `served` and `bypassed`.
- `http.access_log.enabled` controls the fixed maintenance decision and state-failure log events.
- The PSR-3 info event `http.maintenance.served` has exactly the producer-supplied context fields `outcome = served` and integer `status = 503`.
- The PSR-3 warning event `http.maintenance.message_invalid` has exactly the producer-supplied context field `reason = invalid-message`.
- The PSR-3 warning event `http.maintenance.state_unavailable` has exactly the producer-supplied context field `reason = state-unavailable`.

When metrics are enabled, the middleware attempts exactly one counter increment with value `1` per completed decision. When access logging is enabled, it attempts exactly one applicable log emission for each served decision or warning condition. Canonical logging admission, context enrichment, and redaction remain unchanged.

A completed served decision is recorded only after successful maintenance response construction and required immutable response mutations.

A completed bypassed decision is recorded before downstream execution. Bypassed decisions do not emit the served log event.

Invalid-message and unavailable-state warnings are independent of successful response construction and downstream execution, including liveness bypass.

Disabled maintenance middleware and confirmed inactive maintenance state do not produce maintenance observations.

Metric and log failures are isolated independently without retries or duplicate emissions. Maintenance decisions do not introduce additional spans or determine the final HTTP request outcome.

Maintenance-decision observations contain no public message text, message-file contents, retry-delay values, filesystem paths, raw request data, permissions, filesystem diagnostics, or Throwable diagnostics.

Maintenance observability remains governed by the [Observability SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability.md).

### HTTPS redirect decisions

Enabled `HttpsRedirectMiddleware` observes completed redirect decisions independently of final HTTP request completion.

- `http.metrics.enabled` controls the `http.https_redirect_total` counter with exactly the `outcome` label.
- `http.access_log.enabled` controls the PSR-3 info event `http.https_redirect.decided`.
- Allowed outcome values are `redirected` and `skipped`.
- Redirected log records additionally contain the configured integer status `307` or `308`.
- Skipped log records do not contain a status field.

Redirected decisions are recorded only after successful response construction and Location mutation. Skipped decisions are recorded before downstream handler execution.

Disabled or bypassed middleware and failed redirect-response construction or Location mutation do not emit completed decisions.

Metric and log failures are isolated independently without retries or duplicate emissions. Redirect observability does not introduce additional spans or determine the final HTTP request outcome.

Redirect-decision observations contain no raw URL, path, query, authority, Location, request headers, cookies, credentials, URL-derived hashes or lengths, or exception diagnostics.

## Errors

The HTTP-owned `HttpExceptionMapper` implements the existing `ExceptionMapperInterface` and is contributed under `ReservedTags::ERROR_MAPPER` (`error.mapper`) at priority `100` with metadata `[]`. It maps only:

- `BadJsonException` to HTTP `400` with `CORETSIA_HTTP_BAD_JSON`;
- `BodyTooLargeException` to HTTP `413` with `CORETSIA_HTTP_PAYLOAD_TOO_LARGE`;
- `TrustedHostRejectedException` to HTTP `400` with `CORETSIA_HTTP_TRUSTED_HOST_REJECTED`.

Other Throwables return `null` from this mapper. Descriptor codes and messages are fixed and safe; raw parser errors, rejected hosts, request bodies, and untrusted header values are not exported. The canonical Errors subsystem owns exception normalization and mapper resolution; an explicitly selected Problem Details integration owns safe HTTP error adaptation and rendering. Without that outer boundary, downstream Throwables propagate. HTTP does not construct an independent `ErrorHandlerInterface`, exception registry, emergency response renderer, or replacement normalization path.

## Security / Redaction

HTTP request safety uses the selected and validated request policies: immediate-peer trust for forwarded metadata, bounded and canonical host admission, bounded body-size verification (including actual stream bytes), and JSON decoding only for eligible content types within the configured byte and depth limits. `RequestContextMiddleware` alone writes validated request-derived `CLIENT_IP`, `SCHEME`, `HOST`, `PATH`, and `USER_AGENT` to the existing request-local `ContextStore`; those values are not automatically exportable to observability sinks.

`RequestIdPolicy` alone writes `ContextKeys::REQUEST_ID` when enabled. Accepted or generated identifiers must remain safe opaque values and must not encode credentials, tokens, cookies, session ids, direct user identifiers, or raw payload data.

Request identity remains separate from generic middleware context enrichment. It is not exported as Kernel UnitOfWork context or result payload data. Successful canonical Foundation reset clears it together with all other UnitOfWork-local context values after eligible after-UoW handling.

HTTP does not directly execute reset, discover reset tags, retry reset, or separately remove request identity from `ContextStore`. A failed reset does not establish successful context cleanup.

Request, response, and exception data must not disclose raw URLs, query strings, headers, cookies, Authorization values, credentials, tokens, payloads, parsed bodies, request-derived identifiers, private context, stack traces, or exception messages through public errors, metrics, access logs, or spans. HTTP emits only safe, bounded maintenance-decision, redirect-decision, and request-completion fields. Canonical PSR-3 Logging owns shared sink-side redaction and serialization; HTTP does not instantiate another redactor or depend directly on a concrete Logging implementation.

A non-seekable inbound request body must arrive complete and unread from its owning transport adapter; already consumed bytes cannot be recovered by HTTP middleware. Accepted bounded body verification and JSON parsing preserve downstream body availability without an unbounded buffering layer, spooling subsystem, or shared request cache. HTTP-owned policies do not rely on superglobals or mutable process-global request state.

## Non-goals

This package does not provide:

- SAPI front controllers, request-from-globals adapters, response emitters, browser-serving instructions, or deployment transport selection;
- general application route matching, routing artifacts, application route admission, system endpoint implementations or dispatch, or a route-template extraction/handoff contract;
- automatic module activation, Composer installation, arbitrary PSR factory-class configuration, provider scanning, or factory fallbacks;
- consumer-specific DI provider registration, application target setup, or alternate container compilation;
- CORS, content negotiation, rate limiting, session, authentication, multipart upload, or debugging middleware;
- maintenance-specific system endpoint implementation, target-specific runtime activation, distributed maintenance-state synchronization, crash-transactional filesystem updates, or CLI and Worker suspension;
- HSTS, CSP nonce generation, request-dependent security-header values, TLS termination, or transport-level HTTPS enforcement;
- request identity preparation through PSR-15 middleware, authenticated identity resolution, or automatic upstream trust detection;
- separate exception normalization, Problem Details rendering, logging backend/redactor, metrics exporter, tracing parent-context registry, or observability observer pipeline;
- any independent Kernel UnitOfWork, reset orchestration, runtime driver matrix, or mutable request-state registry.

## References

- [Coretsia](https://coretsia.dev/)
- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Platform HTTP package source](https://github.com/coretsia/monorepo/tree/main/packages/platform/http)
- [HTTP Middleware Wiring SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/http-middleware-wiring.md)
- [HTTP Middleware Catalog SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/http-middleware-catalog.md)
- [System Route Table SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/system-route-table.md)
- [Reserved Path Matching SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/reserved-path-matching.md)
- [Context Keys SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/context-keys.md)
- [Context Store SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/context-store.md)
- [DI Tags and Middleware Ordering SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/di-tags-and-middleware-ordering.md)
- [Runtime Container Definitions SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/runtime-container-definitions.md)
- [Config and env SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/config-and-env.md)
- [UoW and Reset Contracts SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/uow-and-reset-contracts.md)
- [UnitOfWork Outcome Policy SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/uow-outcome-policy.md)
- [Observability SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability.md)
- [Logging Policy SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/logging-policy.md)
- [Problem Details SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/problem-details.md)
- [Artifacts and Fingerprint Behavior SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/artifacts-and-fingerprint.md)
- [ADR-0041: Platform HTTP runtime pipeline and wiring](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0041-platform-http-runtime-pipeline-wiring.md)
- [Compile-time package dependencies](https://github.com/coretsia/monorepo/blob/main/docs/architecture/DEPENDENCIES.md)
