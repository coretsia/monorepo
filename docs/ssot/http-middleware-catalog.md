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

# HTTP Middleware Catalog SSoT

## Scope

This document is the Single Source of Truth for Coretsia HTTP middleware slot taxonomy, baseline HTTP middleware catalog placement, cross-cutting runtime invariants, noop-safe observability expectations, context and unit-of-work policy, redaction rules, and ownership boundaries introduced by epic `1.190.0`.

This document locks the canonical HTTP middleware taxonomy under the existing reserved tags:

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

This document also anchors cross-cutting runtime invariants to existing SSoT documents:

```text
docs/ssot/tags.md
docs/ssot/config-roots.md
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/uow-and-reset-contracts.md
```

Epic `1.190.0` is coordination and policy only.

It creates this SSoT catalog snapshot and does not create or modify runtime package files.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Runtime-wide HTTP cross-cutting behavior must have one stable policy baseline before concrete runtime package epics add or revise HTTP, logging, tracing, metrics, problem-details, health, routing, auth, session, tenancy, uploads, or other runtime integrations.

This document defines:

- canonical HTTP middleware slot taxonomy;
- canonical baseline HTTP middleware catalog entries;
- middleware owner boundaries;
- optional package registration policy;
- cross-cutting context and UoW invariants;
- noop-safe observability expectations;
- redaction and privacy invariants;
- rate-limit placement rules;
- default wiring rules through reserved DI tags.

This document does not implement middleware classes, package config, DI providers, discovery algorithms, compiled artifacts, runtime modules, skeleton defaults, HTTP request handling, routing, sessions, auth, problem-details rendering, health endpoints, tracing backends, metrics backends, or logging backends.

## Coordination boundary

Epic `1.190.0` is a docs-only coordination epic.

It introduces no:

- ADR;
- DI tags;
- config roots;
- config keys;
- generated artifacts;
- runtime package code;
- middleware implementations;
- service providers;
- package modules;
- skeleton config;
- exception taxonomy;
- metric label keys.

Concrete package files for the following packages MUST be created or modified only by their owning package epics:

```text
framework/packages/platform/http/**
framework/packages/platform/logging/**
framework/packages/platform/tracing/**
framework/packages/platform/metrics/**
framework/packages/platform/problem-details/**
framework/packages/platform/health/**
framework/packages/platform/session/**
framework/packages/platform/auth/**
framework/packages/platform/security/**
framework/packages/platform/routing/**
framework/packages/core/foundation/**
framework/packages/core/kernel/**
```

This document may reference future or optional owners only as policy and catalog context.

Those references are not compile-time dependencies and are not implementation requirements for `platform/http`.

## Required source SSoTs

This document is anchored by the following existing sources:

```text
docs/ssot/tags.md
docs/ssot/config-roots.md
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/uow-and-reset-contracts.md
docs/architecture/PACKAGING.md
docs/roadmap/phase0/00_2-dependency-table.md
```

The tag registry remains the canonical owner of reserved DI tag names.

The config roots registry remains the canonical owner of reserved config roots and config defaults authority.

The observability SSoT remains the canonical owner of span names, metric names, metric label allowlist, and redaction law.

The UoW and reset contracts SSoT remains the canonical owner of reset and hook contracts policy.

The packaging document remains the canonical owner of package identity, namespace, Composer naming, publishable-unit, and runtime package shape rules.

The dependency table remains the canonical owner of compile-time package edges for its declared scope.

## Contract and port references

The runtime policy in this document may use the following existing ports and interfaces:

```text
Psr\Log\LoggerInterface
Psr\Http\Server\MiddlewareInterface
Coretsia\Contracts\Context\ContextAccessorInterface
Coretsia\Contracts\Observability\Tracing\TracerPortInterface
Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface
Coretsia\Contracts\Observability\Metrics\MeterPortInterface
Coretsia\Contracts\Observability\CorrelationIdProviderInterface
Coretsia\Contracts\Runtime\ResetInterface
```

These references do not create new contracts.

This document does not change their public method surfaces.

## Compile-time dependency policy

This coordination document introduces no compile-time dependencies.

Compile-time dependencies are enforced per owning package.

For `platform/http`, the baseline compile-time policy is:

- `platform/http` MUST NOT depend on other runtime packages under:
  - `platform/*`
  - `enterprise/*`
  - `integrations/*`
- allowed compile-time inputs for `platform/http` are limited to:
  - `core/*`
  - `core/contracts`
  - PSR interfaces

Optional middleware owners listed in this catalog MUST NOT become class-string defaults inside `platform/http` config.

Optional package middleware MUST be registered by the optional package itself when installed.

## Tag taxonomy

HTTP middleware participation is tag-based.

The canonical HTTP middleware taxonomy is single-choice:

```text
system/app/route
```

The complete canonical slot set is:

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

No other HTTP middleware slot taxonomy is allowed.

The following legacy tags are forbidden:

```text
http.middleware.user_before_routing
http.middleware.user
http.middleware.user_after_routing
```

These legacy names MUST NOT appear as current tag names in contracts, SSoT docs, defaults, runtime code, generated artifacts, package providers, tests, or gates.

A future document may mention them only as forbidden legacy terminology.

## Kernel and reset tags

The following existing reserved tags are part of the cross-cutting runtime policy:

```text
kernel.reset
kernel.stateful
kernel.hook.before_uow
kernel.hook.after_uow
```

Consumers MUST NOT enumerate reset tags directly as an alternative reset protocol.

Runtime owners must reset all stateful services deterministically through the owning Foundation reset discovery mechanism.

`kernel.stateful` is the fixed enforcement marker for stateful services.

Runtime execution MUST NOT depend on `kernel.stateful`; it is enforcement-only.

Reset execution is expected to use the effective Foundation reset discovery tag, whose reserved default is:

```text
kernel.reset
```

## Default wiring policy

Default HTTP middleware wiring is via tags.

No skeleton config is required for baseline middleware participation.

Canonical tag-based registration and discovery remain the source of truth for middleware participation.

Later compiled config artifacts MAY materialize or augment middleware slot lists for runtime consumption by `platform/http`.

Compiled artifacts MUST NOT redefine:

- slot taxonomy;
- tag ownership;
- package ownership;
- default optional package participation;
- forbidden legacy tag policy.

Deterministic ordering and discovery-consumption rules are Foundation-owned and MUST remain compatible with this taxonomy when the corresponding owner epic introduces them.

## Platform HTTP defaults policy

`platform/http/config/http.php` MUST contain only middleware shipped by `platform/http`.

Optional or external package middleware class strings MUST NOT be referenced by default inside `platform/http` config.

Optional packages MAY be documented in this catalog as conditional entries using this policy wording:

```text
if installed, it SHOULD register itself
```

Optional packages are responsible for their own registration, toggles, package config, DI providers, and runtime dependencies.

## Noop-safe observability baseline

The runtime baseline is noop-safe.

The following services MUST be injectable and MUST NOT throw merely because no backend is configured or installed:

```text
Psr\Log\LoggerInterface
Coretsia\Contracts\Observability\Tracing\TracerPortInterface
Coretsia\Contracts\Observability\Metrics\MeterPortInterface
```

A `NullLogger` fallback is allowed.

No-op tracing and metrics implementations are allowed and expected.

Noop-safe behavior does not mean user code exceptions are swallowed.

For example, `TracerPortInterface::inSpan()` may re-throw the original throwable from the callback according to the tracing contract policy.

Observability implementations MUST preserve redaction policy even when implemented as no-op adapters.

## Context policy

Context access and context enrichment MUST remain safe and semantic.

Baseline context reads may use the following semantic keys:

```text
correlation_id
uow_id
uow_type
```

Baseline safe context writes may use the following semantic keys:

```text
correlation_id
uow_id
uow_type
```

HTTP request context enrichment may use the following request-safe semantic keys:

```text
client_ip
scheme
host
path
user_agent
```

These keys are safe for in-process request context only.

They are not automatically safe for logs, metrics, spans, debug output, health output, generated artifacts, or error descriptor extensions.

Raw `path` MUST NOT be emitted to logs, metrics, span names, metric labels, public diagnostics, or generated artifacts.

If path-like information is needed for observability output, use:

```text
path_template
hash(value)
len(value)
```

and only when allowed by the owner policy.

The following keys are reserved for later safe use only and are not baseline writers in this epic:

```text
request_id
path_template
http_response_format
actor_id
tenant_id
```

Concrete `Coretsia\Foundation\Context\ContextKeys` constants are introduced and locked only by the owning Foundation epic.

This coordination epic MUST NOT require that class before the owner package is reviewed and fixed.

## Unit-of-work and reset discipline

Runtime owners MUST prevent unit-of-work-local mutable state from leaking across repeated runtime boundaries.

Stateful services MUST implement:

```text
Coretsia\Contracts\Runtime\ResetInterface
```

Stateful services MUST be tagged with:

```text
kernel.stateful
```

as the fixed enforcement marker.

Stateful services MUST also be discoverable through Foundation reset discovery.

The reserved default effective reset discovery tag is:

```text
kernel.reset
```

Runtime execution MUST NOT depend on `kernel.stateful`.

`kernel.stateful` exists for enforcement and gates.

Reset orchestration, ordering, error handling, diagnostics, and discovery are Foundation-owned.

Before-UoW and after-UoW hook discovery use:

```text
kernel.hook.before_uow
kernel.hook.after_uow
```

Hook execution is Kernel-owned.

Hook implementations MUST NOT expose raw transport data, raw payloads, credentials, tokens, private customer data, or absolute local paths through diagnostics.

## Observability policy

The baseline canonical HTTP span is:

```text
http.request
```

The baseline canonical HTTP metrics are:

```text
http.request_total
http.request_duration_ms
```

Allowed labels for these baseline HTTP metrics are single-choice:

```text
method
status
outcome
```

The global metric label allowlist remains:

```text
method
status
driver
operation
table
outcome
```

No new metric label keys are introduced by this document.

The following labels remain forbidden under the baseline policy:

```text
field
path
property
request_id
correlation_id
tenant_id
user_id
```

If cross-runtime metrics need to represent unit-of-work type, normalization MUST use the existing allowlisted label:

```text
uow_type -> operation
```

when safe and owner-approved.

Metric labels MUST be safe, bounded-cardinality scalar values.

Metric labels MUST NOT contain:

- raw path;
- raw query;
- headers;
- cookies;
- request body;
- response body;
- auth identifiers;
- session identifiers;
- tokens;
- raw SQL;
- profile payloads;
- arbitrary user identifiers;
- private customer data.

## Rate-limit observability policy

Early pre-identity throttling and identity-aware throttling are distinct middleware roles.

They MUST remain distinguishable in code, docs, tests, and runtime wiring.

This distinction MUST NOT introduce new metric label keys unless `docs/ssot/observability.md` is directly updated.

Any distinction between early and identity-aware rate limiting MUST be encoded through one of:

- metric name choice;
- event name;
- internal wiring;
- safe owner-defined diagnostics.

The distinction MUST NOT be encoded through forbidden or high-cardinality metric labels.

## Logging policy

HTTP access logging is structured and redaction-first.

A structured access log MAY be emitted per request by the owning middleware.

Access logs MUST NOT emit:

- raw path;
- raw query;
- headers;
- cookies;
- request body;
- response body;
- auth identifiers;
- session identifiers;
- tokens;
- raw SQL;
- secrets;
- private customer data;
- unsafe payloads.

If path-like information is needed, use:

```text
path_template
hash(value)
len(value)
```

Raw values MUST NOT be printed or logged.

Producers MUST prefer omission over unsafe emission.

## Error policy

This epic introduces no exception taxonomy.

Error taxonomy is defined by `core/contracts` descriptor models and related contracts SSoTs.

Concrete error flow is runtime-owned by:

```text
platform/errors
```

HTTP problem-details rendering is owned by:

```text
platform/problem-details
```

If `platform/problem-details` is installed, it SHOULD register an outermost HTTP error handling middleware in:

```text
http.middleware.system_pre
```

with the highest priority.

This document does not introduce or require `platform/problem-details`.

## Security and redaction policy

Runtime cross-cutting behavior MUST NOT leak:

- `.env` values;
- passwords;
- credentials;
- tokens;
- private keys;
- cookies;
- authorization headers;
- session identifiers;
- auth identifiers;
- request bodies;
- response bodies;
- raw payloads;
- raw SQL;
- profile payloads;
- private customer data;
- absolute local paths.

Allowed safe diagnostics MAY use:

```text
hash(value)
len(value)
safe ids
```

Safe ids MUST be explicitly non-sensitive, stable, and bounded-cardinality.

Safe derivations MUST NOT allow reconstruction of raw values.

Redaction MUST NOT be bypassed merely because a sink is internal.

## Canonical middleware catalog rules

The HTTP middleware catalog is single-choice.

A middleware class listed as `platform/http` owned is a baseline platform HTTP responsibility.

A middleware class listed under an optional owner is conditional and MUST be registered by that optional package itself when installed.

A single middleware class MUST NOT be auto-wired into both:

```text
http.middleware.system_pre
http.middleware.app_pre
```

Early rate limiting and identity-aware rate limiting MUST use distinct middleware classes and distinct config toggles.

Early rate limiting is pre-identity.

Identity-aware rate limiting is app-level and must run only after app-level identity context is available.

## Canonical baseline placement

Rate limit middleware placement is fixed.

`\Coretsia\Platform\Http\Middleware\EarlyRateLimitMiddleware::class` MAY be registered only into:

```text
http.middleware.system_pre
```

It MUST NOT be present in:

```text
http.middleware.system
http.middleware.system_post
http.middleware.app_pre
http.middleware.app
http.middleware.app_post
http.middleware.route_pre
http.middleware.route
http.middleware.route_post
```

Rationale:

Early anonymous, IP, and infrastructure throttling must happen before application identity context is available and before deeper application work is performed.

`\Coretsia\Platform\Http\Middleware\RateLimitMiddleware::class` MAY be registered only into:

```text
http.middleware.app_pre
```

It MUST NOT be present in:

```text
http.middleware.system_pre
http.middleware.system
http.middleware.system_post
http.middleware.app
http.middleware.app_post
http.middleware.route_pre
http.middleware.route
http.middleware.route_post
```

Rationale:

Identity-aware throttling must run after application-level identity context is available.

## Slot: `http.middleware.system_pre`

System-pre middleware runs before the main system, application, and route middleware stages.

### Platform HTTP baseline entries

| priority | owner package_id | middleware class                                                           | registration                            |
|----------|------------------|----------------------------------------------------------------------------|-----------------------------------------|
| `950`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\CorrelationIdMiddleware::class`        | auto                                    |
| `940`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\RequestIdMiddleware::class`            | auto if `http.request_id.enabled`       |
| `930`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\TraceContextMiddleware::class`         | auto                                    |
| `920`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\HttpMetricsMiddleware::class`          | auto                                    |
| `910`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\AccessLogMiddleware::class`            | auto                                    |
| `900`    | `platform/http`  | `\Coretsia\Platform\Http\Maintenance\MaintenanceMiddleware::class`         | auto if enabled                         |
| `880`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\TrustedProxyMiddleware::class`         | auto if `http.proxy.enabled`            |
| `870`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\RequestContextMiddleware::class`       | auto if `http.context.enrich.enabled`   |
| `860`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\TrustedHostMiddleware::class`          | auto if `http.hosts.enabled`            |
| `850`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\HttpsRedirectMiddleware::class`        | auto if `http.https_redirect.enabled`   |
| `840`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\CorsMiddleware::class`                 | auto if `http.cors.enabled`             |
| `830`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\RequestBodySizeLimitMiddleware::class` | auto                                    |
| `820`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\MethodOverrideMiddleware::class`       | auto if `http.method_override.enabled`  |
| `810`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\ContentNegotiationMiddleware::class`   | auto if `http.negotiation.enabled`      |
| `800`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\JsonBodyParserMiddleware::class`       | auto if `http.request.json.enabled`     |
| `790`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\EarlyRateLimitMiddleware::class`       | auto if `http.rate_limit.early.enabled` |
| `580`    | `platform/http`  | `\Coretsia\Platform\Http\Debug\DebugEndpointsMiddleware::class`            | optional shape, dev-only, opt-in only   |

### Optional package entries

Optional package entries MUST NOT be referenced by `platform/http` defaults.

If installed, each optional owner SHOULD register itself.

| priority | owner package_id           | middleware class                                                                       | registration                            |
|----------|----------------------------|----------------------------------------------------------------------------------------|-----------------------------------------|
| `1000`   | `platform/problem-details` | `\Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware::class` | if installed, it SHOULD register itself |
| `600`    | `platform/health`          | `\Coretsia\Platform\Health\Http\Middleware\HealthEndpointsMiddleware::class`           | if installed, it SHOULD register itself |
| `590`    | `platform/metrics`         | `\Coretsia\Platform\Metrics\Http\Middleware\MetricsEndpointMiddleware::class`          | if installed, it SHOULD register itself |

## Slot: `http.middleware.system`

This slot is reserved.

It is empty by default.

## Slot: `http.middleware.system_post`

System-post middleware runs after the system and application processing stage according to owner pipeline semantics.

### Platform HTTP baseline entries

| priority | owner package_id | middleware class                                                      | registration                            |
|----------|------------------|-----------------------------------------------------------------------|-----------------------------------------|
| `900`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\CacheHeadersMiddleware::class`    | auto if `http.cache_headers.enabled`    |
| `800`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\EtagMiddleware::class`            | auto if `http.etag.enabled`             |
| `700`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\CompressionMiddleware::class`     | auto if `http.compression.enabled`      |
| `600`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\SecurityHeadersMiddleware::class` | auto if `http.security_headers.enabled` |

### Optional package entries

Optional package entries MUST NOT be referenced by `platform/http` defaults.

If installed, each optional owner SHOULD register itself.

| priority | owner package_id     | middleware class                                                                 | registration                                      |
|----------|----------------------|----------------------------------------------------------------------------------|---------------------------------------------------|
| `650`    | `platform/streaming` | `\Coretsia\Platform\Streaming\Http\Middleware\DisableBufferingMiddleware::class` | if installed, it SHOULD register itself           |
| `500`    | `devtools/dev-tools` | `\Coretsia\Devtools\DevTools\Http\Middleware\DebugbarMiddleware::class`          | if installed, dev-only, it SHOULD register itself |

## Slot: `http.middleware.app_pre`

Application-pre middleware runs before main application middleware and after system-pre middleware.

This slot is where identity-aware app-level middleware may run.

### Platform HTTP baseline entries

| priority | owner package_id | middleware class                                                | registration                                                |
|----------|------------------|-----------------------------------------------------------------|-------------------------------------------------------------|
| `150`    | `platform/http`  | `\Coretsia\Platform\Http\Middleware\RateLimitMiddleware::class` | auto if `http.rate_limit.enabled`; identity-aware placement |

### Optional package entries

Optional package entries MUST NOT be referenced by `platform/http` defaults.

If installed, each optional owner SHOULD register itself.

| priority | owner package_id     | middleware class                                                                | registration                            |
|----------|----------------------|---------------------------------------------------------------------------------|-----------------------------------------|
| `500`    | `enterprise/tenancy` | `\Coretsia\Enterprise\Tenancy\Http\Middleware\TenantContextMiddleware::class`   | if installed, it SHOULD register itself |
| `350`    | `platform/async`     | `\Coretsia\Platform\Async\Http\Middleware\RequestTimeoutMiddleware::class`      | if installed, it SHOULD register itself |
| `300`    | `platform/session`   | `\Coretsia\Platform\Session\Http\Middleware\SessionMiddleware::class`           | if installed, it SHOULD register itself |
| `200`    | `platform/auth`      | `\Coretsia\Platform\Auth\Http\Middleware\AuthMiddleware::class`                 | if installed, it SHOULD register itself |
| `100`    | `platform/security`  | `\Coretsia\Platform\Security\Http\Middleware\CsrfMiddleware::class`             | if installed, it SHOULD register itself |
| `80`     | `platform/uploads`   | `\Coretsia\Platform\Uploads\Http\Middleware\MultipartFormDataMiddleware::class` | if installed, it SHOULD register itself |
| `50`     | `platform/inbox`     | `\Coretsia\Platform\Inbox\Http\Middleware\IdempotencyKeyMiddleware::class`      | if installed, it SHOULD register itself |

## Slot: `http.middleware.app`

Application middleware is the main application-level middleware slot.

### Optional package entries

Optional package entries MUST NOT be referenced by `platform/http` defaults.

If installed, each optional owner SHOULD register itself.

| priority | owner package_id    | middleware class                                                | registration                            |
|----------|---------------------|-----------------------------------------------------------------|-----------------------------------------|
| `100`    | `platform/http-app` | `\Coretsia\Platform\HttpApp\Middleware\RouterMiddleware::class` | if installed, it SHOULD register itself |

## Slot: `http.middleware.app_post`

This slot is reserved.

It is empty by default.

## Slot: `http.middleware.route_pre`

This slot is reserved.

It is empty by default.

## Slot: `http.middleware.route`

This slot is reserved.

It is empty by default.

## Slot: `http.middleware.route_post`

This slot is reserved.

It is empty by default.

## Opt-in middleware

The following middleware MUST NOT be auto-wired by default:

```text
\Coretsia\Platform\Auth\Http\Middleware\RequireAuthMiddleware::class
\Coretsia\Platform\Auth\Http\Middleware\RequireAbilityMiddleware::class
```

These middleware classes are owned by:

```text
platform/auth
```

They are route or policy opt-in concerns and must be registered only through explicit owner-approved mechanisms.

They MUST NOT be platform HTTP defaults.

## Optional owner policy

Optional package catalog entries are non-binding for `platform/http`.

An optional owner MAY register its middleware when installed.

An optional owner MUST obey:

- the canonical slot taxonomy;
- tag registry ownership;
- observability redaction law;
- config roots ownership;
- package dependency rules;
- no raw secrets or PII output;
- deterministic ordering rules defined by the owning discovery mechanism.

Optional owners MUST NOT require `platform/http` to reference their middleware class strings in its defaults.

## Config policy

Epic `1.190.0` introduces no config roots and no config keys.

The HTTP config root is already reserved by:

```text
docs/ssot/config-roots.md
```

with owner:

```text
platform/http
```

Possible config keys referenced by this catalog are owner-policy references only.

They do not create config defaults or rules in this epic.

Concrete config defaults and config rules are owned by the corresponding runtime package epics.

## Artifact policy

Epic `1.190.0` introduces no artifacts.

Concrete artifacts are owned by their dedicated runtime epics.

Examples:

```text
core/kernel
platform/routing
```

A later compiled artifact MAY materialize middleware slot lists for runtime consumption.

Such an artifact MUST NOT redefine:

- HTTP middleware slot taxonomy;
- package ownership;
- tag ownership;
- optional package registration policy;
- redaction rules.

Generated artifacts MUST NOT contain secrets, credentials, tokens, cookies, raw request data, raw SQL, private customer data, or unsafe diagnostics.

## Gates and verification policy

This coordination epic expects existing CI rails to run:

```text
framework/tools/gates/cross_cutting_contract_gate.php
```

Referenced owner-package tests are evidence inputs for this coordination policy.

They are not owned by this epic.

Future owner-package evidence may include:

```text
framework/packages/core/foundation/tests/Contract/ContextKeysAreStableContractTest.php
framework/packages/platform/tracing/tests/Contract/W3CPropagationDeterministicContractTest.php
framework/packages/platform/metrics/tests/Contract/NoopNeverThrowsContractTest.php
framework/packages/platform/logging/tests/Integration/CorrelationIdIsAlwaysPresentInLogsTest.php
```

Those tests are reference-only here.

They MUST be introduced, owned, and maintained by their respective package epics.

## Runtime acceptance scenario

When the Micro preset handles an HTTP request:

1. the request enters the canonical HTTP middleware taxonomy through `system_pre`;
2. correlation id handling is available;
3. trace context handling is available;
4. metrics emission is available;
5. structured access logging is available;
6. noop tracing, metrics, and logging fallbacks never throw merely because a backend is absent;
7. early pre-identity throttling, if enabled, is handled by `EarlyRateLimitMiddleware` in `http.middleware.system_pre`;
8. identity-aware throttling, if enabled, is handled by `RateLimitMiddleware` in `http.middleware.app_pre`;
9. raw path, raw query, headers, cookies, body, auth/session ids, tokens, raw SQL, secrets, and PII are not logged or exported;
10. context and unit-of-work state do not leak across repeated runtime boundaries;
11. concrete proof is provided by the owning runtime package epics.

This scenario is expected runtime policy.

It is not implemented by this docs-only epic.

## What this epic MUST NOT create

Epic `1.190.0` MUST NOT create or modify:

```text
framework/packages/platform/http/config/http.php
framework/packages/platform/http/config/rules.php
framework/packages/platform/http/src/**
framework/packages/platform/logging/src/**
framework/packages/platform/tracing/src/**
framework/packages/platform/metrics/src/**
framework/packages/platform/problem-details/src/**
framework/packages/core/foundation/src/**
framework/packages/core/kernel/src/**
skeleton/**
config/*.php
```

It MUST NOT implement:

- HTTP middleware classes;
- HTTP pipeline builder;
- runtime tag discovery;
- Foundation reset orchestration;
- Kernel hook execution;
- tracing backend;
- metrics backend;
- logging backend;
- problem-details middleware;
- health endpoint middleware;
- route middleware;
- auth middleware;
- session middleware;
- tenancy middleware;
- generated artifacts;
- business features.

## Non-goals

This SSoT does not define:

- concrete HTTP runtime implementation;
- concrete middleware class implementation;
- routing implementation;
- route collection implementation;
- HTTP app implementation;
- session implementation;
- auth implementation;
- tenancy implementation;
- uploads implementation;
- inbox implementation;
- problem-details renderer;
- health endpoint implementation;
- metrics endpoint implementation;
- tracing backend implementation;
- logging backend implementation;
- config defaults;
- config validation rules;
- DI provider implementation;
- compiled middleware artifact schema;
- skeleton application config;
- exception taxonomy;
- new metric labels.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Tag Registry](./tags.md)
- [Config Roots Registry](./config-roots.md)
- [Observability Naming and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [Packaging strategy](../architecture/PACKAGING.md)
- [Phase 0 dependency table](../roadmap/phase0/00_2-dependency-table.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
