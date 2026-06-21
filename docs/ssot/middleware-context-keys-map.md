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

# Middleware → ContextKeys map

## Purpose

This document is the reference-only map from HTTP middleware FQCNs to canonical `ContextKeys` that the middleware may write or read.

It exists to prevent drift between:

- HTTP middleware responsibilities;
- canonical context key names;
- context lifecycle policy;
- observability/redaction boundaries.

This document is not the HTTP middleware catalog.

It is only a context-key usage map:

```text
Middleware FQCN → ContextKeys written/read
```

## Source-of-truth boundaries

This document owns only the middleware-to-context-key reference map.

The canonical context key registry is owned by:

```text
docs/ssot/context-keys.md
Coretsia\Foundation\Context\ContextKeys
```

The canonical `ContextStore` write policy and safe-value model are owned by:

```text
docs/ssot/context-store.md
```

The canonical HTTP middleware list, slot ownership, slot ordering, optional owner participation, and placement policy are owned by:

```text
docs/ssot/http-middleware-catalog.md
```

The lifecycle rules for when context is created, enriched, reset, and cleared are owned by:

```text
docs/ssot/context-lifecycle.md
```

Observability export rules remain owned by:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
```

This document MUST NOT redefine:

- canonical context key names;
- `ContextStorePolicy` validation semantics;
- middleware slot ownership;
- middleware ordering;
- middleware catalog membership;
- package registration rules;
- HTTP request id policy;
- HTTP correlation header extraction/injection policy;
- runtime implementation details.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Canonical references

Canonical references:

```text
docs/ssot/context-keys.md
docs/ssot/context-store.md
docs/ssot/context-lifecycle.md
docs/ssot/http-middleware-catalog.md
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
```

The Phase 0 fixture:

```text
framework/tools/spikes/fixtures/http_middleware_catalog.php
```

MAY be used only as a Phase 0 lock/alignment input.

It is not the SSoT for middleware slot ownership, slot contents, runtime discovery, middleware placement, or context-key usage.

The canonical middleware list/slot ownership/order reference is:

```text
docs/ssot/http-middleware-catalog.md
```

The middleware slot strings shown in this reference map are documentation values.

Runtime package source MUST use the corresponding framework-reserved DI tag identifier constants from:

```text
Coretsia\Foundation\Tag\ReservedTags
```

This document MUST NOT introduce additional code-level registries for framework-reserved DI tag identifiers.

## Table: Middleware FQCN → ContextKeys written/read

The table below is a reference map only.

A row does not redefine catalog membership or slot placement. If a row conflicts with `docs/ssot/http-middleware-catalog.md`, the catalog wins for slot/catalog questions and this document must be corrected.

`Writes` and `Reads` list only canonical context key names from:

```text
Coretsia\Foundation\Context\ContextKeys
```

`—` means no canonical ContextKeys write/read is owned by this map.

| Middleware FQCN                                                                        | Slot                          | Owner                      | Writes                                              | Reads                                                                 | Notes                                                                                                                                                               |
|----------------------------------------------------------------------------------------|-------------------------------|----------------------------|-----------------------------------------------------|-----------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `\Coretsia\Platform\Http\Middleware\CorrelationIdMiddleware::class`                    | `http.middleware.system_pre`  | `platform/http`            | `—`                                                 | `correlation_id`                                                      | Base `correlation_id` is Kernel begin-UoW context. HTTP correlation header extraction/injection policy is not defined by this document.                             |
| `\Coretsia\Platform\Http\Middleware\RequestIdMiddleware::class`                        | `http.middleware.system_pre`  | `platform/http`            | `request_id`                                        | `correlation_id`                                                      | Writes only a safe request id when the HTTP request-id policy is enabled by its owner epic. MUST NOT affect `correlation_id`.                                       |
| `\Coretsia\Platform\Http\Middleware\TraceContextMiddleware::class`                     | `http.middleware.system_pre`  | `platform/http`            | `—`                                                 | `correlation_id`, `request_id`, `uow_id`                              | Reads safe ids only when tracing owner policy allows. MUST NOT export ids as metric labels.                                                                         |
| `\Coretsia\Platform\Http\Middleware\HttpMetricsMiddleware::class`                      | `http.middleware.system_pre`  | `platform/http`            | `—`                                                 | `uow_type`, `path_template`                                           | MAY read safe low-cardinality context for owner-approved metrics. MUST NOT use ids or raw `path` as metric labels.                                                  |
| `\Coretsia\Platform\Http\Middleware\AccessLogMiddleware::class`                        | `http.middleware.system_pre`  | `platform/http`            | `—`                                                 | `correlation_id`, `request_id`, `uow_id`, `uow_type`, `path_template` | MAY read safe ids and route template for structured logs. MUST NOT log raw `path`, query, headers, cookies, Authorization, tokens, session ids, or payloads.        |
| `\Coretsia\Platform\Http\Maintenance\MaintenanceMiddleware::class`                     | `http.middleware.system_pre`  | `platform/http`            | `—`                                                 | `—`                                                                   | No canonical ContextKeys usage.                                                                                                                                     |
| `\Coretsia\Platform\Http\Middleware\TrustedProxyMiddleware::class`                     | `http.middleware.system_pre`  | `platform/http`            | `—`                                                 | `—`                                                                   | May normalize transport-level proxy data for later HTTP processing, but this map does not assign ContextKeys writes to it.                                          |
| `\Coretsia\Platform\Http\Middleware\RequestContextMiddleware::class`                   | `http.middleware.system_pre`  | `platform/http`            | `client_ip`, `scheme`, `host`, `path`, `user_agent` | `—`                                                                   | Canonical HTTP request context enrichment writer. `path` MUST NOT include query string and MUST NOT be exported raw to observability.                               |
| `\Coretsia\Platform\Http\Middleware\TrustedHostMiddleware::class`                      | `http.middleware.system_pre`  | `platform/http`            | `—`                                                 | `host`                                                                | MAY read canonical `host` if owner implementation validates against context. MUST NOT write raw authority/header payloads.                                          |
| `\Coretsia\Platform\Http\Middleware\HttpsRedirectMiddleware::class`                    | `http.middleware.system_pre`  | `platform/http`            | `—`                                                 | `scheme`, `host`                                                      | MAY read normalized request metadata. MUST NOT write transport objects or headers into ContextStore.                                                                |
| `\Coretsia\Platform\Http\Middleware\CorsMiddleware::class`                             | `http.middleware.system_pre`  | `platform/http`            | `—`                                                 | `—`                                                                   | MUST NOT write raw Origin or header values into ContextStore.                                                                                                       |
| `\Coretsia\Platform\Http\Middleware\RequestBodySizeLimitMiddleware::class`             | `http.middleware.system_pre`  | `platform/http`            | `—`                                                 | `—`                                                                   | MUST NOT write request bodies or raw payload metadata into ContextStore.                                                                                            |
| `\Coretsia\Platform\Http\Middleware\MethodOverrideMiddleware::class`                   | `http.middleware.system_pre`  | `platform/http`            | `—`                                                 | `—`                                                                   | MUST NOT write raw method override headers or body fields into ContextStore.                                                                                        |
| `\Coretsia\Platform\Http\Middleware\ContentNegotiationMiddleware::class`               | `http.middleware.system_pre`  | `platform/http`            | `http_response_format`                              | `—`                                                                   | MAY write a stable bounded response format category such as `html`, `json`, or `problem_json`.                                                                      |
| `\Coretsia\Platform\Http\Middleware\JsonBodyParserMiddleware::class`                   | `http.middleware.system_pre`  | `platform/http`            | `—`                                                 | `—`                                                                   | MUST NOT write parsed request bodies or raw payloads into ContextStore.                                                                                             |
| `\Coretsia\Platform\Http\Middleware\EarlyRateLimitMiddleware::class`                   | `http.middleware.system_pre`  | `platform/http`            | `—`                                                 | `client_ip`, `scheme`, `host`                                         | Pre-identity throttling MAY read safe request metadata. MUST NOT write counters, tokens, or raw forwarding chains into ContextStore.                                |
| `\Coretsia\Platform\Http\Debug\DebugEndpointsMiddleware::class`                        | `http.middleware.system_pre`  | `platform/http`            | `—`                                                 | `—`                                                                   | Dev-only/opt-in shape. Any diagnostics MUST obey redaction policy.                                                                                                  |
| `\Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware::class` | `http.middleware.system_pre`  | `platform/problem-details` | `—`                                                 | `correlation_id`, `request_id`, `uow_id`                              | Optional owner. MAY read safe ids for error correlation only when problem-details policy allows. MUST NOT expose raw context values.                                |
| `\Coretsia\Platform\Health\Http\Middleware\HealthEndpointsMiddleware::class`           | `http.middleware.system_pre`  | `platform/health`          | `—`                                                 | `—`                                                                   | Optional owner. Health output MUST NOT dump ContextStore values.                                                                                                    |
| `\Coretsia\Platform\Metrics\Http\Middleware\MetricsEndpointMiddleware::class`          | `http.middleware.system_pre`  | `platform/metrics`         | `—`                                                 | `—`                                                                   | Optional owner. Metrics endpoint MUST NOT expose ContextStore values as labels or payload.                                                                          |
| `\Coretsia\Platform\Http\Middleware\CacheHeadersMiddleware::class`                     | `http.middleware.system_post` | `platform/http`            | `—`                                                 | `—`                                                                   | No canonical ContextKeys usage.                                                                                                                                     |
| `\Coretsia\Platform\Http\Middleware\EtagMiddleware::class`                             | `http.middleware.system_post` | `platform/http`            | `—`                                                 | `—`                                                                   | MUST NOT write response bodies, hashes of unsafe payloads, or ETag values into ContextStore unless a future SSoT introduces an explicit safe key.                   |
| `\Coretsia\Platform\Http\Middleware\CompressionMiddleware::class`                      | `http.middleware.system_post` | `platform/http`            | `—`                                                 | `—`                                                                   | No canonical ContextKeys usage.                                                                                                                                     |
| `\Coretsia\Platform\Http\Middleware\SecurityHeadersMiddleware::class`                  | `http.middleware.system_post` | `platform/http`            | `—`                                                 | `—`                                                                   | MUST NOT write raw headers into ContextStore.                                                                                                                       |
| `\Coretsia\Platform\Streaming\Http\Middleware\DisableBufferingMiddleware::class`       | `http.middleware.system_post` | `platform/streaming`       | `—`                                                 | `—`                                                                   | Optional owner. No canonical ContextKeys usage.                                                                                                                     |
| `\Coretsia\Devtools\DevTools\Http\Middleware\DebugbarMiddleware::class`                | `http.middleware.system_post` | `devtools/dev-tools`       | `—`                                                 | `correlation_id`, `request_id`, `uow_id`, `uow_type`                  | Dev-only/opt-in shape. MAY read safe ids/categories for diagnostics. MUST NOT expose secrets, payloads, raw headers, cookies, Authorization, tokens, or raw `path`. |
| `\Coretsia\Enterprise\Tenancy\Http\Middleware\TenantContextMiddleware::class`          | `http.middleware.app_pre`     | `enterprise/tenancy`       | `tenant_id`                                         | `host`                                                                | Optional owner. `tenant_id` MUST be an opaque safe id. MUST NOT expose customer name, domain, billing id, external account id, token, or private customer data.     |
| `\Coretsia\Platform\Async\Http\Middleware\RequestTimeoutMiddleware::class`             | `http.middleware.app_pre`     | `platform/async`           | `—`                                                 | `—`                                                                   | Optional owner. MUST NOT write timeout state, timers, or payload references into ContextStore.                                                                      |
| `\Coretsia\Platform\Session\Http\Middleware\SessionMiddleware::class`                  | `http.middleware.app_pre`     | `platform/session`         | `—`                                                 | `—`                                                                   | Optional owner. MUST NOT write session ids, session payloads, cookies, or tokens into ContextStore.                                                                 |
| `\Coretsia\Platform\Auth\Http\Middleware\AuthMiddleware::class`                        | `http.middleware.app_pre`     | `platform/auth`            | `actor_id`                                          | `—`                                                                   | Optional owner. `actor_id` MUST be an opaque safe id and MUST NOT be email, username, phone, full name, external account id, token, or session id.                  |
| `\Coretsia\Platform\Http\Middleware\RateLimitMiddleware::class`                        | `http.middleware.app_pre`     | `platform/http`            | `—`                                                 | `actor_id`, `tenant_id`, `client_ip`                                  | Identity-aware throttling MAY read safe identity/request context. MUST NOT write counters, bucket ids, tokens, or raw identifiers into ContextStore.                |
| `\Coretsia\Platform\Security\Http\Middleware\CsrfMiddleware::class`                    | `http.middleware.app_pre`     | `platform/security`        | `—`                                                 | `—`                                                                   | Optional owner. MUST NOT write CSRF tokens into ContextStore.                                                                                                       |
| `\Coretsia\Platform\Uploads\Http\Middleware\MultipartFormDataMiddleware::class`        | `http.middleware.app_pre`     | `platform/uploads`         | `—`                                                 | `—`                                                                   | Optional owner. MUST NOT write file payloads, upload temp paths, filenames, or multipart payloads into ContextStore.                                                |
| `\Coretsia\Platform\Inbox\Http\Middleware\IdempotencyKeyMiddleware::class`             | `http.middleware.app_pre`     | `platform/inbox`           | `—`                                                 | `correlation_id`, `request_id`, `actor_id`, `tenant_id`               | Optional owner. MUST NOT write raw idempotency keys into ContextStore unless a future SSoT introduces a safe hashed key.                                            |
| `\Coretsia\Platform\HttpApp\Middleware\RouterMiddleware::class`                        | `http.middleware.app`         | `platform/http-app`        | `path_template`                                     | `path`                                                                | Optional owner. Writes low-cardinality route/path template. MUST NOT include concrete user-controlled path parameter values.                                        |
| `\Coretsia\Platform\Auth\Http\Middleware\RequireAuthMiddleware::class`                 | route/policy opt-in           | `platform/auth`            | `—`                                                 | `actor_id`                                                            | Opt-in middleware. MUST NOT write auth credentials, tokens, sessions, or raw identity data into ContextStore.                                                       |
| `\Coretsia\Platform\Auth\Http\Middleware\RequireAbilityMiddleware::class`              | route/policy opt-in           | `platform/auth`            | `—`                                                 | `actor_id`, `tenant_id`                                               | Opt-in middleware. MUST NOT write ability names, permission payloads, tokens, sessions, or raw identity data into ContextStore.                                     |

## Notes

### Context write rules

Middleware MUST write only keys declared by:

```text
Coretsia\Foundation\Context\ContextKeys
```

Middleware MUST NOT create ad hoc ContextKeys.

Middleware writes MUST pass:

```text
Coretsia\Foundation\Context\ContextStorePolicy
```

Middleware MUST prefer omission over unsafe context storage.

### Read does not imply export permission

A middleware reading a ContextKey does not gain permission to export that key to logs, spans, metrics, errors, public diagnostics, or generated artifacts.

Observability export remains governed by:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
```

Safe ids MAY be used for owner-approved correlation in logs/traces.

Safe ids MUST NOT be metric labels under the baseline policy.

Raw `path` MAY exist as in-process context, but MUST NOT be emitted raw to logs, spans, metrics, public diagnostics, generated artifacts, or error descriptor extensions.

### HTTP request context enrichment

The canonical HTTP request context enrichment writer in this map is:

```text
\Coretsia\Platform\Http\Middleware\RequestContextMiddleware::class
```

It MAY write:

```text
client_ip
scheme
host
path
user_agent
```

`path` MUST NOT include query string.

`client_ip` SHOULD be normalized or hashed when feasible.

`user_agent` MAY be high-cardinality and privacy-sensitive. Writers SHOULD prefer omission, normalization, hashing, or length-only diagnostics when feasible.

### Request id boundary

`request_id` is distinct from `correlation_id`.

`request_id` MAY be written only by the owning HTTP request-id policy.

This document does not define:

- request-id generation policy;
- request-id header extraction;
- request-id header injection;
- request-id propagation.

### Correlation id boundary

`correlation_id` is a base UoW key.

It is not selected by HTTP request-id policy.

It is not selected by middleware context enrichment.

HTTP middleware MAY read it for owner-approved correlation behavior, but MUST NOT replace the Kernel-owned begin-UoW lifecycle policy.

### Auth/session boundary

Auth middleware MAY write only safe opaque `actor_id`.

Session middleware MUST NOT write:

```text
session_id
session payload
cookies
tokens
Authorization
```

Authentication and authorization middleware MUST NOT write:

```text
email
phone
full_name
username
external account id
access_token
refresh_token
credentials
```

### Tenancy boundary

Tenancy middleware MAY write only safe opaque `tenant_id`.

It MUST NOT write:

```text
customer name
domain
billing id
external account id
tenant credential
private customer data
```

### Routing boundary

Routing middleware MAY write:

```text
path_template
```

It MUST NOT write concrete route parameter values into `path_template`.

`path_template` is preferred over raw `path` for low-cardinality diagnostics.

### Reserved slots

The following slots are reserved or empty by default in the canonical catalog:

```text
http.middleware.system
http.middleware.app_post
http.middleware.route_pre
http.middleware.route
http.middleware.route_post
```

This document does not add middleware entries to those slots.

If later owner epics add middleware to those slots, this map MUST be updated only for their ContextKeys written/read behavior.

## Non-goals

This document does not define:

- the canonical HTTP middleware catalog;
- middleware priorities;
- middleware ordering;
- middleware activation defaults;
- optional package registration behavior;
- HTTP middleware implementation;
- HTTP pipeline implementation;
- request-id generation policy;
- correlation header extraction/injection policy;
- tracing backend behavior;
- metrics backend behavior;
- logging backend behavior;
- auth implementation;
- session implementation;
- tenancy implementation;
- routing implementation;
- generated artifacts;
- config roots;
- config keys;
- feature toggles;
- new ContextKeys.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Context Keys SSoT](./context-keys.md)
- [Context Store SSoT](./context-store.md)
- [ContextStore lifecycle SSoT](./context-lifecycle.md)
- [HTTP Middleware Catalog SSoT](./http-middleware-catalog.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [DI Tags and Middleware Ordering SSoT](./di-tags-and-middleware-ordering.md)
