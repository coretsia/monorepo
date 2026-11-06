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

# Problem Details SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: platform/problem-details
```

## Scope

This document is the Single Source of Truth for the `platform.problem-details` HTTP error representation: canonical `ErrorDescriptor` adaptation, fixed RFC9457 JSON members, RFC7807-compatible baseline behavior, deterministic serialization, PSR-15 Throwable handling, safe emergency responses, explicit module activation, and public disclosure restrictions.

The implementation boundary is:

```text
packages/platform/problem-details/src/ProblemDetails.php
packages/platform/problem-details/src/Adapter/ErrorDescriptorToProblemDetailsAdapter.php
packages/platform/problem-details/src/Renderer/ProblemDetailsJsonRenderer.php
packages/platform/problem-details/src/Http/Middleware/HttpErrorHandlingMiddleware.php
packages/platform/problem-details/src/Module/ProblemDetailsModule.php
packages/platform/problem-details/src/Provider/ProblemDetailsServiceProvider.php
packages/platform/problem-details/config/problem_details.php
packages/platform/problem-details/config/rules.php
```

This SSoT defines HTTP presentation policy. It MUST NOT redefine the format-neutral `ErrorDescriptor` field schema, the canonical Throwable normalization and reporting pipeline, the shared redaction rules, the Foundation JSON encoder contract, or the HTTP middleware slot taxonomy. Those remain owned by their respective SSoTs.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Provide a deterministic, fail-closed `application/problem+json` response for caught downstream HTTP failures, using the existing canonical error handler exactly once and exposing only safe-by-construction fields.

The baseline MUST operate without a second exception mapper, error reporter, redaction engine, serializer, HTTP pipeline, observability backend, or reset lifecycle.

## Authority and ownership (MUST)

`core/contracts` owns `ErrorHandlerInterface`, the format-neutral `ErrorDescriptor`, and `ErrorHandlingContext`. `platform/errors` owns canonical Throwable-to-`ErrorDescriptor` mapping, mandatory descriptor redaction, and eligible error reporting. `platform/redaction` supplies the shared redaction implementation through the existing `platform.errors` module dependency.

`platform/problem-details` owns only HTTP-specific adaptation, the immutable `ProblemDetails` presentation model, deterministic JSON rendering, and its PSR-15 error-boundary middleware. It MUST consume the existing `ErrorHandlerInterface` binding and MUST NOT construct an alternative error handler, mapper registry, fallback exception mapper, reporter, redactor, or exception hierarchy.

`platform/http` is the semantic owner of HTTP middleware execution, slot ordering, tag metadata semantics, request/response lifecycle, and pipeline integration. `platform/problem-details` MUST NOT create a competing HTTP middleware catalog, runtime pipeline, tag identifier, route registry, or transport lifecycle.

The canonical ownership flow is:

```text
Downstream Throwable
  → ErrorHandlerInterface::handle() (platform/errors)
  → ErrorDescriptor (core/contracts)
  → ErrorDescriptorToProblemDetailsAdapter (platform/problem-details)
  → ProblemDetails (platform/problem-details)
  → ProblemDetailsJsonRenderer (platform/problem-details)
  → PSR response and stream factories (compatible HTTP target)
```

`ProblemDetails` is a transport-specific response model, not a replacement for `ErrorDescriptor`. Neither `ErrorDescriptor` nor `ErrorHandlerInterface` may gain a PSR HTTP dependency because of this adapter.

## Module selection and declarative DI (MUST)

The runtime module id is `platform.problem-details`, the package id is `platform/problem-details`, and the Composer name is `coretsia/platform-problem-details`. The package namespace is `Coretsia\Platform\ProblemDetails`.

Physical package installation alone MUST NOT activate `HttpErrorHandlingMiddleware`. The module MUST be explicitly selected in the target-local Kernel `ModulePlan` for a compatible HTTP target. An unselected target MUST NOT receive the problem-details middleware service or its HTTP middleware tag contribution.

The module requires `core.foundation` and `platform.errors` through canonical module composition. `platform.errors` supplies its existing `platform.redaction` dependency. A compatible HTTP target MUST provide services for the PSR `ResponseFactoryInterface` and `StreamFactoryInterface` ports before the selected middleware can construct responses. Missing required services MUST fail through the existing container composition or compilation boundary; the package MUST NOT silently supply an ad-hoc transport factory.

The selected `ProblemDetailsServiceProvider` MUST declaratively register only `ErrorDescriptorToProblemDetailsAdapter`, `ProblemDetailsJsonRenderer`, and `HttpErrorHandlingMiddleware` as its class services. Its required service references are:

```text
Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface
Psr\Http\Message\ResponseFactoryInterface
Psr\Http\Message\StreamFactoryInterface
```

The provider MUST contribute exactly one existing `ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE` registration for `HttpErrorHandlingMiddleware` at priority `1000`. The tag value is `http.middleware.system_pre`; tag metadata MUST be `[]` unless `platform/http` defines another normative metadata schema. No package-local `owner`, `path`, or `mode` metadata is authorized.

The provider's canonical declarative definitions MUST yield equivalent source and compiled service bindings and tag descriptors. Provider ordering and target-local module selection remain Kernel-owned. There is no parallel imperative container graph, runtime closure, or mutable provider registry.

Production-source imports from concrete `platform/errors`, `platform/redaction`, `platform/http`, `core/kernel`, logging, tracing, or metrics implementations MUST NOT be introduced. Direct source dependencies remain limited to `core/contracts`, `core/foundation`, and the explicitly declared PSR interfaces. The `platform/errors` Composer/module edge is a runtime binding requirement, not permission to import its concrete classes.

## Configuration and ownership (MUST)

The reserved package-owned config root is `problem_details`. Its only canonical defaults path is `config/problem_details.php`, returning exactly the empty root subtree `[]` without a second root wrapper.

The strict `config/rules.php` policy is:

```php
return [
    'schemaVersion' => 1,
    'configRoot' => 'problem_details',
    'additionalKeys' => false,
    'keys' => [],
];
```

The existing Kernel configuration validator MUST reject every unknown key, including `debug`, `show_detail`, `show_detail_in_local`, `detail`, `instance`, `extensions`, `renderer`, custom exception mapping, and redaction overrides. The baseline has no environment-derived disclosure policy, executable validators, configuration callbacks, serializer selector, or public debug switch.

This package MUST NOT create another config root, change application presets, or activate itself through configuration or installation side effects.

## Canonical HTTP response schema (MUST)

RFC9457 is the normative Problem Details format. The baseline intentionally uses only the three members supported by RFC7807-compatible clients:

```json
{
  "status": 404,
  "title": "Not Found",
  "type": "about:blank"
}
```

The canonical response-model map is exactly:

```text
status: integer in 400..599
title: fixed English HTTP error title derived from status
type: "about:blank"
```

The map MUST contain these three keys in stable JSON key order and no additional public members.

`ProblemDetails` MUST be `final readonly`. Public construction MUST accept only the integer HTTP error status, never a caller-supplied `title`, `type`, `detail`, `instance`, or arbitrary text or extension map. The status is the sole input to title derivation. The `type` URI reference MUST always be exactly `about:blank`.

`detail`, `instance`, arbitrary extension members, descriptor `code`, `message`, `severity`, `schemaVersion`, request identifiers, correlation identifiers, and debug metadata MUST NOT appear in the public representation. A future disclosure policy requires separately approved ownership and security acceptance tests; redaction alone is not authorization to expose data.

The `status` member MUST equal the actual HTTP response status returned by the middleware. Generated error responses MUST set `Content-Type: application/problem+json` and `Cache-Control: no-store` with those exact values. The baseline does not perform HTML content negotiation.

## Accepted status hints and title mapping (MUST)

`ErrorDescriptor.httpStatus` is an optional transport hint only. The adapter MUST accept integers in the inclusive `400..599` range. `null`, informational, success, redirection, or otherwise unsupported hints MUST produce the safe HTTP `500` fallback. The canonical descriptor may itself reject values outside its own format-neutral HTTP hint contract; adaptation MUST NOT weaken that contract.

`ProblemDetails` MUST reject direct construction with statuses outside `400..599`. For accepted standard HTTP errors, titles MUST use the following fixed English phrases, consistent with the supported RFC9110 status semantics:

```text
400 Bad Request
401 Unauthorized
402 Payment Required
403 Forbidden
404 Not Found
405 Method Not Allowed
406 Not Acceptable
407 Proxy Authentication Required
408 Request Timeout
409 Conflict
410 Gone
411 Length Required
412 Precondition Failed
413 Content Too Large
414 URI Too Long
415 Unsupported Media Type
416 Range Not Satisfiable
417 Expectation Failed
421 Misdirected Request
422 Unprocessable Content
423 Locked
424 Failed Dependency
425 Too Early
426 Upgrade Required
428 Precondition Required
429 Too Many Requests
431 Request Header Fields Too Large
451 Unavailable For Legal Reasons
500 Internal Server Error
501 Not Implemented
502 Bad Gateway
503 Service Unavailable
504 Gateway Timeout
505 HTTP Version Not Supported
506 Variant Also Negotiates
507 Insufficient Storage
508 Loop Detected
510 Not Extended
511 Network Authentication Required
```

An accepted error status absent from this fixed standard-title set, including `418`, `430`, `512`, and `599`, MUST use exactly `HTTP Error`. The safe fallback status `500` MUST use exactly `Internal Server Error`.

There MUST be one canonical status/title implementation mapping, owned by `ProblemDetails`. The adapter, renderer, middleware, configuration, and test utilities MUST NOT introduce a second runtime lookup table or derive titles from `ErrorDescriptor.message`, `ErrorDescriptor.code`, request data, `Throwable`, locale, environment, or application configuration.

## Deterministic JSON serialization (MUST)

`ProblemDetailsJsonRenderer::render(ProblemDetails $problem): string` MUST delegate to the existing `StableJsonEncoder::encodeStableMap()` and MUST NOT implement a second serializer or use ad-hoc `json_encode()` as an alternative production writer.

The renderer MUST preserve the Foundation stable-JSON contract: recursive map-key sorting by byte-order comparison, list-order preservation, compact JSON encoding, and exactly one final LF (`\n`). The baseline Problem Details representation has no lists or nested maps. Its exact canonical byte order is:

```text
{"status":404,"title":"Not Found","type":"about:blank"}
```

The line above is terminated by one LF; there is no leading whitespace, indentation, trailing space, or second newline in the generated body. Repeated rendering of the same accepted status MUST yield identical bytes, independent of descriptor insertion order, request, locale, clock, randomness, or process state.

`StableJsonEncoder` remains the single authority for JSON-safe value validation. Unsupported floats (including NaN and infinity), objects, closures, resources, malformed map keys, and invalid encoding MUST NOT be coerced into public output. The fixed `ProblemDetails` model MUST admit only its safe scalar fields, so raw nested payloads cannot reach the renderer through its public API.

The renderer MUST NOT construct PSR responses, inspect application context, write output streams, emit diagnostics, record metrics or spans, log, or retain mutable state.

## PSR-15 Throwable boundary (MUST)

`HttpErrorHandlingMiddleware` MUST implement PSR-15 `MiddlewareInterface` and delegate successful downstream processing unchanged. It MUST catch downstream `Throwable` at the outer HTTP error boundary without installing a global PHP exception handler or altering HTTP pipeline execution ownership.

For each caught downstream `Throwable`, it MUST invoke the canonical `ErrorHandlerInterface::handle()` exactly once with that original object. Only the returned normalized `ErrorDescriptor` may be adapted into `ProblemDetails`. Neither an adapter failure nor a renderer failure may cause a second `handle()` call or another exception-mapping, reporting, or redaction pass.

When normalization, adaptation, and rendering succeed, the middleware MUST request a PSR response with the resulting problem status, create a stream from the exact rendered body, and return the response with the required `Content-Type`, `Cache-Control`, and body. The status in the JSON and in the HTTP response MUST agree.

The middleware MUST NOT read or forward raw request headers, cookies, body, query secrets, exception messages, traces, previous exceptions, arbitrary descriptor extensions, or runtime context metadata. No response is constructed on the successful downstream path.

## Failure isolation and emergency response (MUST)

Failure of `ErrorHandlerInterface`, adaptation, or rendering MUST select a fixed safe emergency representation, without retrying the handler or invoking a failed renderer recursively. Its exact body is the following literal JSON object followed by one LF:

```text
{"status":500,"title":"Internal Server Error","type":"about:blank"}
```

The emergency HTTP response MUST use status `500`, `Content-Type: application/problem+json`, and `Cache-Control: no-store`. The body MUST be the fixed canonical byte sequence, not dynamically serialized error information, a diagnostic message, or an alternate JSON encoding implementation.

Both ordinary and emergency responses MUST use the injected PSR response and stream factories. If response creation, stream creation, response header mutation, or body replacement fails, the middleware MUST preserve and rethrow the **original downstream `Throwable`**. It MUST NOT substitute a factory, renderer, adapter, or handler failure as the primary application error or claim a successfully constructed response when no valid response exists.

The emergency path MUST NOT create a second error reporter, log, metric, span, warning event, reset hook, recursive HTTP response, or alternative exception hierarchy. Error reporting remains entirely within the canonical handler's existing responsibility and failure isolation.

## Security and redaction (MUST)

Public Problem Details MUST be safe by construction. The adapter consumes only the validated `httpStatus` hint. It MUST NOT forward `ErrorDescriptor.message`, `code`, `severity`, `extensions`, `schemaVersion`, raw Throwable or previous-exception details, stack traces, request or response payloads, or arbitrary diagnostic context.

Secrets, credentials, authorization headers, cookies, session identifiers, tokens, passwords, SQL, absolute local paths, private customer data, profile payloads, and other PII MUST NOT be exposed in public JSON. Public `detail` and `instance` remain omitted even if a descriptor has already passed mandatory shared redaction.

`platform/errors` remains authoritative for descriptor normalization and mandatory use of the shared redactor. `platform/problem-details` MUST NOT re-redact, classify sensitive keys independently, hash or truncate arbitrary data for public output, or introduce a second redaction policy. Safe error responses MUST rely on the restricted output schema rather than treating redacted input as implicitly publishable.

## Context, UnitOfWork, observability, and reset (MUST)

Kernel remains the owner of UnitOfWork lifecycle, correlation identity, and reset orchestration. The package MUST NOT read or write `ContextStore`, create `ContextKeys`, derive public identifiers from runtime context, or start, finish, or reset a UnitOfWork.

`ProblemDetails`, the adapter, renderer, provider, and middleware MUST NOT own mutable request-local registries, static output caches, reset state, or `ResetInterface` implementations. No `kernel.reset` or other lifecycle tag may be contributed.

The package MUST NOT introduce new spans, metrics, logs, error-event streams, tracing or metrics backends, exporters, or warning diagnostics. Any existing platform error reporting, timing, spans, metrics, and logs remain owned by `platform/errors` and its canonical collaborators. Repeated independent requests MUST preserve identical public disclosure rules without leaking state from earlier failures.

## Deterministic examples

The following responses use synthetic, non-sensitive inputs. Every shown body ends in exactly one LF; the displayed code fences are examples, not additional response bytes.

A canonical descriptor with `httpStatus = 422` produces:

```text
HTTP 422
Content-Type: application/problem+json
Cache-Control: no-store

{"status":422,"title":"Unprocessable Content","type":"about:blank"}
```

An accepted non-standard `httpStatus = 430` produces:

```text
HTTP 430
Content-Type: application/problem+json
Cache-Control: no-store

{"status":430,"title":"HTTP Error","type":"about:blank"}
```

A descriptor with `httpStatus = null`, an informational or redirection hint, or a failure in the canonical handler, adapter, or renderer produces the safe fallback when PSR factories remain available:

```text
HTTP 500
Content-Type: application/problem+json
Cache-Control: no-store

{"status":500,"title":"Internal Server Error","type":"about:blank"}
```

If the PSR factories cannot construct this response, the original downstream `Throwable` is rethrown instead of a successful response being returned. Successful downstream requests pass through without generated Problem Details output.

## Enforcement rails

Verification MUST establish:

- exact `ProblemDetails` immutability, constructor status-only API, fixed `about:blank` type, fixed canonical status/title mapping, and rejection of arbitrary public titles or members;
- deterministic rendering, exact `application/problem+json` content type, fixed map ordering, final LF, stable repeated bytes, and rejection of unsafe JSON values by the canonical encoder;
- accepted 4xx/5xx hints, non-standard 4xx/5xx titles, null and non-error fallback to `500`, and the absence of descriptor messages, extension values, or request data in public output;
- successful PSR-15 pass-through, exactly one canonical handler call per caught downstream `Throwable`, response/body status parity, and safe `no-store` response headers;
- fixed emergency response bytes after handler, adapter, or renderer failure; no recursive rendering or duplicate reporting; original Throwable precedence if PSR response or stream construction fails;
- strict empty `problem_details` defaults and configuration rules, rejection of unknown disclosure or renderer keys, and canonical config-root ownership;
- explicit target-local module selection, unselected target isolation, required canonical handler and PSR factory services, exactly one priority-`1000` middleware tag with metadata `[]`, and source/compiled container definition parity;
- absence of ContextStore mutation, UnitOfWork ownership, additional logging, tracing, metrics, reset hooks, mutable registries, or forbidden concrete package imports.

The corresponding package unit, contract, and integration acceptance tests MUST use synthetic `ErrorDescriptor` and `Throwable` fixtures, deterministic PSR doubles, and independent failure scenarios. Security tests MUST include private-looking descriptor messages and extensions, raw request payload and exception data, repeated requests, safe emergency output, and verification that no `detail`, `instance`, or debug-disclosure mode is enabled by default.

A standalone middleware test or source/compiled DI test with synthetic factories does not prove full outermost HTTP pipeline integration. Such verification MUST remain open until a compatible `platform/http` runtime can execute and order the selected middleware. Missing Composer bootstrap, unavailable HTTP runtime, or unexecuted gates MUST NOT be reported as successful end-to-end verification.

## Non-goals

- An alternative `ErrorDescriptor` schema, exception-mapping registry, canonical error handler, error reporter, or redaction engine.
- HTML error pages, content negotiation between JSON and HTML, custom problem-type URI catalogs, user-defined titles, or public `detail`, `instance`, and extension disclosure.
- New serializers, renderer interfaces or registries, arbitrary HTTP status/title overrides, localized error titles, or configuration-driven output policy.
- An HTTP pipeline owner, middleware registry, route catalog, response factory implementation, or transport-server adapter.
- Runtime ContextStore access, correlation-id generation, UnitOfWork lifecycle management, stateful caches, or reset hooks.
- Additional metrics, spans, logs, error-event streams, observability exporters, persistent telemetry, or recursive self-instrumentation.

## Cross-references

- [SSoT Index](./INDEX.md)
- [ErrorDescriptor SSoT](./error-descriptor.md) — canonical format-neutral descriptor fields and HTTP status-hint contract.
- [Errors Boundary SSoT](./errors-boundary.md) — canonical Throwable normalization, error mapping, reporting ownership, and runtime adapter law.
- [Observability and Errors SSoT](./observability-and-errors.md) — error observability and failure-isolation boundaries.
- [Sensitive Data Redaction SSoT](./sensitive-data-redaction.md) — mandatory shared redaction and disclosure restrictions.
- [HTTP Middleware Catalog SSoT](./http-middleware-catalog.md) — existing system-pre slot, priority, selection policy, and middleware execution ownership.
- [Tag Registry](./tags.md) — reserved middleware tag identifier and owner.
- [Config Roots Registry](./config-roots.md) — reserved `problem_details` root.
- [Runtime Container Definitions SSoT](./runtime-container-definitions.md) — declarative service descriptions and source/compiled parity.
- [Modules and manifests SSoT](./modules-and-manifests.md) — module metadata and target-local ModulePlan selection.
- [Architecture Dependencies](../architecture/DEPENDENCIES.md) — allowed compile-time package dependency directions.
