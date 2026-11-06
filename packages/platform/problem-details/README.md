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

# coretsia/platform-problem-details

`platform/problem-details` is the HTTP Problem Details adaptation and JSON rendering package for Coretsia. It converts the canonical, format-neutral `ErrorDescriptor` into a fixed, safe RFC9457 response through an explicitly selected PSR-15 error-boundary middleware.

Scope: deterministic `application/problem+json` responses with RFC7807-compatible `type`, `title`, and `status` members; bounded HTTP status adaptation; fixed emergency HTTP `500` fallback; reuse of the canonical `ErrorHandlerInterface`; and declarative registration in the existing HTTP middleware slot.

Out of scope: exception normalization, additional exception mappers, error reporting, independent redaction, HTML error pages, JSON/HTML content negotiation, customizable public error details, PSR response or stream factory implementations, HTTP pipeline execution, observability backends, Kernel UnitOfWork lifecycle, and reset orchestration.

This README is a consumer-oriented package summary. `docs/ssot/problem-details.md` is authoritative for the exact HTTP representation, failure, and disclosure policies.

## Package identity

- Path: `packages/platform/problem-details`
- Package id: `platform/problem-details`
- Composer name: `coretsia/platform-problem-details`
- Module id: `platform.problem-details`
- Namespace: `Coretsia\Platform\ProblemDetails\*` (PSR-4: `src/`)
- Kind: runtime
- Config root: `problem_details`

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions MUST NOT be used.

## Dependency policy

The package declares these production Composer dependencies:

- PHP `^8.4`
- `coretsia/core-contracts`
- `coretsia/core-foundation`
- `coretsia/platform-errors`
- `psr/http-message` `^2.0`
- `psr/http-factory` `^1.0`
- `psr/http-server-middleware` `^1.0`
- `psr/http-server-handler` `^1.0`

The package's `composer.json` is authoritative for exact internal Coretsia dependency constraints. The only direct Coretsia production-source dependencies are `core/contracts` and `core/foundation`, as governed by `docs/architecture/DEPENDENCIES.md`. The declared PSR dependencies supply interfaces, not a concrete HTTP server or a PSR-7/PSR-17 implementation.

`core/contracts` owns `ErrorHandlerInterface` and the format-neutral `ErrorDescriptor`. `core/foundation` provides `StableJsonEncoder`, canonical declarative container definitions, and the reserved HTTP middleware tag. `platform/errors` is a required runtime Composer/module dependency, supplying the canonical handler binding and its existing `platform.redaction` dependency. This runtime requirement does not authorize imports of concrete Errors or Redaction implementation classes.

Production source MUST NOT import concrete `platform/errors`, `platform/redaction`, or `platform/http` implementations, `core/kernel` classes, or logging, tracing, and metrics implementations. The package does not own a second serializer, mapper registry, reporter, redactor, response factory, HTTP middleware catalog, or HTTP pipeline.

## Runtime responsibilities

The package exposes the following runtime components:

- `ProblemDetails`: final readonly HTTP presentation model accepting only an error status and deriving its public type and title internally;
- `Adapter\ErrorDescriptorToProblemDetailsAdapter`: stateless adaptation of the canonical descriptor's optional HTTP status hint;
- `Renderer\ProblemDetailsJsonRenderer`: stateless rendering through the existing Foundation `StableJsonEncoder`;
- `Http\Middleware\HttpErrorHandlingMiddleware`: PSR-15 boundary for downstream `Throwable` handling and PSR response construction;
- `Module\ProblemDetailsModule`: runtime module identity, config-root identity, and provider declaration;
- `Provider\ProblemDetailsServiceProvider`: the sole declarative provider of the three runtime services and one HTTP middleware tag.

The canonical flow for a caught downstream failure is:

```text
Throwable
  → ErrorHandlerInterface::handle() (platform/errors)
  → ErrorDescriptor (core/contracts)
  → ErrorDescriptorToProblemDetailsAdapter
  → ProblemDetails
  → ProblemDetailsJsonRenderer
  → PSR response and stream factories (compatible HTTP target)
```

`ProblemDetails` is a public HTTP presentation model, not a second normalized exception descriptor. The adapter does not map raw `Throwable` objects, select exception mappers, or reinterpret the descriptor's message and extensions. Normalization, shared redaction, and eligible error reporting stay with `platform/errors`.

## Module selection and DI

Installing `coretsia/platform-problem-details` does not activate `HttpErrorHandlingMiddleware`. A compatible HTTP target MUST explicitly select `platform.problem-details` through its target-local Kernel `ModulePlan`. An unselected target receives neither the package's middleware binding nor its tag registration.

The module requires:

```text
core.foundation
platform.errors
```

`platform.errors` in turn requires the existing `platform.redaction` runtime module. The compatible HTTP target MUST provide concrete services for the following PSR factory ports and the canonical handler binding must be available:

```text
Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface
Psr\Http\Message\ResponseFactoryInterface
Psr\Http\Message\StreamFactoryInterface
```

`ProblemDetailsServiceProvider` declares exactly three class services through the Foundation `ContainerDefinitionBuilder`: the adapter, renderer, and `HttpErrorHandlingMiddleware`. Middleware constructor arguments use canonical service references. The provider contributes exactly one `ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE` tag for the middleware, with priority `1000` and metadata `[]`:

```text
Tag:      http.middleware.system_pre
Service:  Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware
Priority: 1000
Metadata: []
```

The tag and its ordering and metadata semantics are owned by `platform/http`, not by this package. No package-local owner, path, or mode metadata is introduced. Kernel owns target-local module selection and provider ordering; source and compiled container definitions retain equivalent service and tag descriptors.

A missing handler or PSR factory binding MUST fail through the existing container composition/compilation boundary. The package does not silently create a concrete PSR factory or activate an HTTP runtime. Selection and the standalone middleware tests do not, by themselves, demonstrate execution in a complete HTTP pipeline.

## Configuration

The package owns the reserved `problem_details` config root. Its only canonical defaults file is `config/problem_details.php`, returning the empty root subtree:

```php
return [];
```

The existing `config/rules.php` declares a strict empty ruleset:

```php
return [
    'schemaVersion' => 1,
    'configRoot' => 'problem_details',
    'additionalKeys' => false,
    'keys' => [],
];
```

The existing Kernel `ConfigValidator` rejects unknown keys. There are no `debug`, `show_detail`, `show_detail_in_local`, `detail`, `instance`, `extensions`, renderer-selection, custom exception-mapping, or redaction-override configuration keys. Config loading does not enable the module or change application presets.

## RFC9457 response and deterministic JSON

RFC9457 is the normative Problem Details format. This package emits the RFC7807-compatible baseline of exactly three public fields, ordered as follows:

```json
{"status":404,"title":"Not Found","type":"about:blank"}
```

- `status`: an integer HTTP error status in the inclusive range `400..599`;
- `title`: the fixed English HTTP status title derived exclusively from `status`;
- `type`: the fixed URI reference `about:blank`.

`ProblemDetails::__construct(int $status)` does not accept an arbitrary public title, type, detail, instance, or extension map. Standard HTTP error statuses use the package's one canonical status/title mapping. An accepted non-standard error status uses `HTTP Error`; status `500` always uses `Internal Server Error`. There is no localization, caller-supplied title override, or custom type URI catalog.

The baseline omits `detail`, `instance`, arbitrary extension members, and all descriptor `code`, `message`, `severity`, and `schemaVersion` data. It also omits request and correlation identifiers, debug data, raw exception details, and diagnostic context. A redacted descriptor is not permission to expose its other fields.

`ProblemDetailsJsonRenderer` calls `StableJsonEncoder::encodeStableMap()` rather than introducing another serializer. JSON is compact, map keys are ordered deterministically, and every body has exactly one final LF (`\n`) and no additional formatting. Repeated rendering of the same accepted status produces identical bytes, independent of request or runtime state.

For every generated error response, the middleware sets:

```text
Content-Type: application/problem+json
Cache-Control: no-store
```

The actual HTTP response status MUST match the JSON `status` member. This package does not perform HTML content negotiation.

## HTTP status adaptation and safe fallback

`ErrorDescriptorToProblemDetailsAdapter` reads only `ErrorDescriptor.httpStatus()` as an optional transport hint. Integer hints in `400..599` are accepted; a `null` hint, an informational, success, or redirection hint, or another unsupported hint maps to HTTP `500` and `Internal Server Error`. The descriptor retains its own input validation policy.

`HttpErrorHandlingMiddleware::process()` returns a successful downstream response unchanged. When the downstream handler throws, the middleware calls `ErrorHandlerInterface::handle()` exactly once for the original `Throwable`, adapts the normalized descriptor, renders the public JSON, and uses the injected response and stream factories to construct the HTTP response.

If normalization, adaptation, or rendering fails, the middleware does not retry the handler or recursively call the renderer. It instead attempts a fixed emergency response with HTTP `500`, the same `Content-Type` and `Cache-Control` headers, and the following exact body followed by one LF:

```text
{"status":500,"title":"Internal Server Error","type":"about:blank"}
```

If PSR response creation, stream creation, header mutation, or body replacement fails, the middleware rethrows the **original downstream `Throwable`** instead of replacing it with the transport failure. Failure to construct a response must not be represented as successful error handling. No secondary normalization, logging, reporting, or instrumentation pass is triggered by the fallback.

## Deterministic examples

The following examples use synthetic status hints and contain no private information. Each displayed JSON body has exactly one final LF; Markdown code fences are not part of the response bytes.

An accepted standard error status hint `422` yields:

```text
HTTP 422
Content-Type: application/problem+json
Cache-Control: no-store

{"status":422,"title":"Unprocessable Content","type":"about:blank"}
```

An accepted non-standard error status hint `430` yields:

```text
HTTP 430
Content-Type: application/problem+json
Cache-Control: no-store

{"status":430,"title":"HTTP Error","type":"about:blank"}
```

A `null` or non-error status hint, or a failed handler, adapter, or renderer with working PSR factories, yields:

```text
HTTP 500
Content-Type: application/problem+json
Cache-Control: no-store

{"status":500,"title":"Internal Server Error","type":"about:blank"}
```

If response or stream construction fails, there is no generated Problem Details response; the original downstream `Throwable` propagates. A successful downstream request does not pass through the error adapter or renderer.

## Context and UnitOfWork

This package does not read or write `ContextStore`, introduce new `ContextKeys`, retain request-scoped mutable state, or derive public identifiers from correlation or request context. It does not start, finish, or reset a UnitOfWork.

Kernel remains the owner of UnitOfWork lifecycle, correlation identity, and reset orchestration. Foundation retains its context/reset infrastructure. The presentation model, adapter, renderer, provider, and middleware introduce no reset services, `ResetInterface` implementation, `kernel.reset` tag, mutable registry, or process-lifetime response cache.

## Observability

`platform/problem-details` is an HTTP presentation adapter, not an observability exporter. It introduces no metric names, recording calls, spans, tracing backend, logging pipeline, error-event stream, persistent telemetry, or self-instrumentation. Its renderer and middleware do not log or re-report rendering failures.

The canonical `platform/errors` handler remains responsible for existing eligible error reporting, mapping diagnostics, redaction, timing, and optional observability collaborators. Invoking that handler once does not authorize this package to instrument the same failure again.

## Errors

`platform/errors` owns Throwable normalization and its canonical mapper/fallback policies. `platform/problem-details` consumes only the resulting descriptor; it does not register another exception mapper, reporter, error-handler implementation, or error exception hierarchy.

HTTP-specific status adaptation is deterministic. A null or unsupported status hint maps to safe HTTP `500`. Failure of the canonical handler, adapter, or renderer selects the fixed emergency representation if the injected PSR factories can construct a response. If they cannot, the original downstream `Throwable` takes precedence over every error generated during HTTP response construction.

Successful requests are returned unchanged. Neither normal nor emergency rendering invokes `ErrorHandlerInterface::handle()` a second time.

## Security / Redaction

Public Problem Details is safe by construction. The adapter forwards only the allowed HTTP status hint and must never serialize raw `Throwable` messages, stack traces, previous exceptions, `ErrorDescriptor.message`, descriptor extensions, request headers, cookies, authorization values, session identifiers, request/response bodies, raw SQL, credentials, tokens, absolute local paths, private customer data, or other PII.

Mandatory shared descriptor redaction remains owned by `platform/errors` through the existing `platform.redaction` dependency. This package does not implement an alternative redactor, sensitive-key classifier, hashing engine, configurable disclosure policy, or diagnostic masking layer. Redacted values are not automatically approved for publication; `detail`, `instance`, and arbitrary extensions remain absent from the public schema.

For security vulnerabilities, report privately using the package's unmodified [Security Policy](./SECURITY.md). Do not file a public issue containing a suspected vulnerability or sensitive reproduction data.

## Non-goals

- HTML error pages, JSON/HTML content negotiation, localized titles, custom problem types, or arbitrary public `detail`, `instance`, and extension fields.
- A second Throwable handler, exception mapper registry, error reporter, redaction engine, serializer, or response-format registry.
- Concrete PSR-7/PSR-17 implementations, HTTP server adapters, route registration, middleware pipeline execution, or ownership of HTTP tag semantics.
- Debug disclosure modes, environment-derived error messages, request/correlation identifiers in public output, or diagnostic payload forwarding.
- ContextStore access, UnitOfWork lifecycle hooks, reset orchestration, mutable registries, telemetry backends, spans, metrics, logging, or persistence.
- Claims of full outermost HTTP pipeline integration without an executable compatible `platform/http` runtime.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Problem Details SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/problem-details.md)
- [ErrorDescriptor SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/error-descriptor.md)
- [Errors Boundary SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/errors-boundary.md)
- [HTTP Middleware Catalog SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/http-middleware-catalog.md)
- [Observability and Errors SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability-and-errors.md)
- [Sensitive Data Redaction SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/sensitive-data-redaction.md)
- [Modules and Manifests SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/modules-and-manifests.md)
- [Runtime Container Definitions SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/runtime-container-definitions.md)
- [Config Roots Registry](https://github.com/coretsia/monorepo/blob/main/docs/ssot/config-roots.md)
- [Tags Registry](https://github.com/coretsia/monorepo/blob/main/docs/ssot/tags.md)
- [ADR-0040: Platform Problem Details HTTP adaptation](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0040-platform-problem-details-http-adaptation.md)
- [Package Dependencies](https://github.com/coretsia/monorepo/blob/main/docs/architecture/DEPENDENCIES.md)
