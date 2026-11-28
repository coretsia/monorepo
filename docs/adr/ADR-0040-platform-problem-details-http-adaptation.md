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

# ADR-0040: Platform Problem Details HTTP adaptation

```yaml
adrVersion: 1
status: pre-accepted
owner: platform/problem-details
```

## Context

`core/contracts` defines the format-neutral `ErrorHandlerInterface` and `ErrorDescriptor`. The selected `platform.errors` runtime module owns canonical Throwable normalization, mapper dispatch, mandatory shared redaction, and eligible error reporting. Those responsibilities must remain independent of the HTTP response format. An HTTP adapter must not reinterpret raw exceptions or make `ErrorDescriptor` depend on PSR HTTP types.

HTTP clients need a deterministic, bounded, and safe error representation. A normalized descriptor cannot be returned directly because its message and extensions are not a public-disclosure contract. The canonical HTTP middleware taxonomy places the Problem Details boundary outermost through its `http.middleware.system_pre` contribution. Participation is mandatory when `platform.http-app` is enabled and otherwise optional. `platform/http` remains the owner of middleware execution and pipeline ordering.

RFC9457 provides the normative Problem Details format. Its `type`, `title`, and `status` members support the required RFC7807-compatible baseline without enabling arbitrary public diagnostics, content negotiation, or custom problem-type catalogs. Foundation already provides stable JSON encoding and declarative service definitions; Kernel already provides explicit target-local module selection and source/compiled container composition.

The HTTP target must provide compatible PSR response and stream factories. Package installation alone does not establish effective HTTP middleware participation. Actual outermost placement is governed by `docs/ssot/http-middleware-wiring.md` and must be verified through the canonical HTTP runtime.

## Decision

### Decision 1: Reuse the canonical error handler and keep HTTP presentation separate

`platform/problem-details` consumes the existing `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface` binding supplied by the selected `platform.errors` module. For each caught downstream `Throwable`, its PSR-15 middleware invokes `ErrorHandlerInterface::handle()` exactly once and consumes only the returned normalized `ErrorDescriptor`.

`platform/errors` remains the sole owner of Throwable mapping, mapper discovery, fallback descriptor selection, mandatory redaction, and optional reporting. The HTTP adapter must not create another `ErrorHandler`, exception mapper registry, reporter, redactor, or exception hierarchy. It must not alter the contracts-level `ErrorDescriptor` schema or add HTTP types to `core/contracts`.

The transport-specific `ProblemDetails` model is necessary because a public HTTP error representation has stricter disclosure rules than a format-neutral diagnostic descriptor. It is not a second normalized error model and cannot be used to replace internal error handling.

### Decision 2: Adopt a fixed RFC9457 representation with RFC7807-compatible members

`ProblemDetails` is a `final readonly` HTTP response model. Its public constructor accepts only an integer error status in `400..599`. Its public representation contains exactly the three members:

```text
status: accepted HTTP error status
title: canonical English HTTP error title derived from status
type: about:blank
```

The public JSON map is emitted with keys in deterministic order `status`, `title`, `type`. The problem type is the fixed URI reference `about:blank`, with no user-defined type URI or title override. No `detail`, `instance`, arbitrary extension, correlation identifier, descriptor code, message, severity, or debug member is admitted.

`ErrorDescriptorToProblemDetailsAdapter::map(ErrorDescriptor $descriptor): ProblemDetails` uses only `ErrorDescriptor::httpStatus()` as an optional transport hint for the public model. Integer hints in `400..599` are accepted; a null, informational, success, redirection, or other unsupported hint produces status `500` with title `Internal Server Error`. The descriptor's own input validation remains authoritative and is not relaxed by the adapter.

The adapter separately exposes `allowHeader(ErrorDescriptor $descriptor): ?string`. It returns `null` when `httpStatus()` is not exactly `405`, regardless of extension metadata. For status `405`, it consumes only `extensions.allowed_methods`.

The required method metadata must be an actual non-empty list of at most nine exact strings belonging to `GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `TRACE`, or `CONNECT`. Entries must already be unique and strictly ascending by byte-order `strcmp`. The vocabulary defines membership, not ordering. The adapter must not trim, uppercase, coerce, sort, deduplicate, infer implicit methods, or otherwise repair the list.

Missing, null, empty, malformed, oversized, duplicate-containing, unsorted, or unsupported method metadata fails with `InvalidArgumentException('problem-details-allow-invalid')` without exporting rejected values. The validated list is joined using exactly `', '`; for example, `['GET', 'POST']` produces `GET, POST`.

The validated Allow value is the sole descriptor-extension-derived HTTP header. It is not a public model member and `allowed_methods` is never emitted in the JSON body. No general descriptor-to-header mapping or configurable header registry is permitted. Routing supplies declared method information; HttpApp owns application method-mismatch classification and descriptor mapping; Problem Details owns independent metadata validation and HTTP Allow-header materialization.

Standard accepted HTTP errors use the fixed English status/title mapping owned solely by `ProblemDetails`. An accepted non-standard status uses exactly `HTTP Error`; `500` uses exactly `Internal Server Error`. The adapter and renderer do not maintain another title table, and neither title nor type depends on Throwable content, request data, locale, environment, or configuration. The canonical mapping is specified by `docs/ssot/problem-details.md`.

### Decision 3: Reuse Foundation stable JSON encoding without a renderer abstraction

`ProblemDetailsJsonRenderer` delegates to `StableJsonEncoder::encodeStableMap()` and emits `application/problem+json`. The existing encoder owns canonical map-key ordering, compact serialization, safe-value validation, and exactly one final LF. Rendering the same status repeatedly yields identical bytes without application context, clocks, randomness, or mutable state.

For a synthetic status `422`, the HTTP body is exactly the following JSON object followed by one LF:

```text
{"status":422,"title":"Unprocessable Content","type":"about:blank"}
```

This package does not introduce an alternative serializer, renderer interface, renderer registry, custom JSON writer, or output policy selector. The existing `ProblemDetails` model cannot carry floats, nested arbitrary payloads, objects, or raw diagnostic values into the renderer.

### Decision 4: Provide one PSR-15 Throwable boundary with deterministic failure precedence

`HttpErrorHandlingMiddleware` implements the existing PSR-15 `MiddlewareInterface`. Successful downstream handling passes its response through unchanged. When downstream handling throws, the middleware calls the canonical handler once, adapts the descriptor, renders the public body, and constructs an HTTP response using the injected PSR response and stream factories.

The actual HTTP status must equal the JSON `status` member. Generated errors have `Content-Type: application/problem+json` and `Cache-Control: no-store` with those exact values.

The middleware obtains the model, rendered body, and optional validated Allow value through the adapter and renderer. The adapted status, body, and Allow value are committed together only after all operations succeed. A successfully adapted `405` response must set `Allow` to the exact validated value. Every other generated response, including emergency `500`, must contain no Allow header; a pre-existing factory-supplied Allow header must be removed.

The middleware does not inspect or forward request headers, cookies, bodies, raw exceptions, diagnostic context, or arbitrary descriptor extensions. The admitted `allowed_methods` member is consumed exclusively for the validated 405 Allow value.

If the handler, adapter, or renderer fails, including missing or invalid required 405 metadata, the middleware does not retry normalization, adaptation, or rendering. A failed adaptation must not produce a 405 without a valid Allow header or commit a partially adapted response. It instead attempts one fixed emergency HTTP `500` response with the exact body below, including one final LF:

```text
{"status":500,"title":"Internal Server Error","type":"about:blank"}
```

The emergency response uses the same content type and no-store cache policy as an ordinary generated error and has no Allow header. The body is a fixed byte sequence rather than a second serialization implementation. If response creation, stream creation, header mutation, or body replacement fails, the original downstream `Throwable` is rethrown; collaborator or transport failures must not replace it or be reported as a successful response.

No rendering failure initiates another exception-mapping, error-reporting, redaction, logging, tracing, or metrics pass. The middleware does not install a global PHP exception handler or take ownership of the HTTP runtime lifecycle.

### Decision 5: Activate only through a compatible target-local module plan

The runtime module is `platform.problem-details`, with package id `platform/problem-details`, Composer package `coretsia/platform-problem-details`, namespace `Coretsia\Platform\ProblemDetails`, and config root `problem_details`.

The target-local Kernel `ModulePlan` must explicitly select `platform.problem-details` in a compatible HTTP target. Installing the Composer package without selecting the module must not register or activate its middleware. An unselected target receives neither the middleware service nor its tag contribution.

When `platform.http-app` is selected, its required runtime dependencies include `platform.problem-details`. The HTTP composition boundary must require actual outermost participation of the canonical `HttpErrorHandlingMiddleware` service and validate its exact resolved runtime class identity without resolving it again. When HttpApp is absent, Problem Details participation remains optional under its existing activation policy. Canonical declaration validation remains applicable; HttpApp-specific resolved error-boundary identity validation is conditional on effective HttpApp participation.

Module requirements are `core.foundation` and `platform.errors`. The latter provides the canonical error handler and brings the existing `platform.redaction` module requirement. Production source may depend on `core/contracts`, `core/foundation`, and the declared PSR HTTP interfaces, but must not import concrete `platform/errors`, `platform/redaction`, or `platform/http` implementations, `core/kernel` classes, or logging, tracing, and metrics backends. The Composer/module dependency on `platform.errors` is a runtime binding requirement, not a source-import permission. Exact compile-time package import permissions remain governed by `docs/architecture/DEPENDENCIES.md`.

A selected compatible HTTP target must provide `ResponseFactoryInterface` and `StreamFactoryInterface`. Missing required factory or handler bindings must fail through existing container validation or compilation, not trigger a package-owned fallback factory or implicit runtime activation.

### Decision 6: Contribute declarative bindings and reuse the platform/http-owned middleware slot

`ProblemDetailsServiceProvider` declares exactly three class services: `ErrorDescriptorToProblemDetailsAdapter`, `ProblemDetailsJsonRenderer`, and `HttpErrorHandlingMiddleware`. It requires the existing `ErrorHandlerInterface`, PSR `ResponseFactoryInterface`, and PSR `StreamFactoryInterface` bindings, and wires constructor dependencies through canonical `ContainerValueReference::service()` references.

The provider contributes exactly one existing `ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE` registration for `HttpErrorHandlingMiddleware` at priority `1000`, using metadata `[]`. The slot identifier is `http.middleware.system_pre`; its semantic owner is `platform/http`, as defined in `docs/ssot/http-middleware-catalog.md` and `docs/ssot/tags.md`. No package-local mirror tag, owner/path/mode metadata, competing middleware catalog, or pipeline implementation is introduced. The contribution must use the canonical service id and exact empty metadata; HTTP owns its outermost placement.

Provider definitions are the sole declarative wiring source. Kernel preserves target-local provider order and composes equivalent source and compiled service definitions and tag descriptors. No runtime closures, service locators, parallel imperative container graphs, or mutable provider registries are required.

### Decision 7: Keep the configuration root empty and disclosure policy closed

The reserved `problem_details` configuration root has one canonical defaults file, `config/problem_details.php`, returning exactly `[]`. The declarative rules file specifies `schemaVersion = 1`, `configRoot = problem_details`, `additionalKeys = false`, and `keys = []`. The existing Kernel configuration validator rejects unknown keys.

There is no `debug`, `show_detail`, `show_detail_in_local`, raw exception disclosure, custom title, renderer selection, type registry, mapper override, redaction override, or environment-derived disclosure switch. The package does not add a root, introduce a second config validator, modify target presets, or use configuration to activate middleware.

### Decision 8: Preserve security, observability, context, and reset ownership

The public JSON body is safe by construction: only its validated status, canonical title, and fixed type are admitted. The separately validated 405 Allow value is bounded protocol metadata, not a public diagnostic field or permission to forward arbitrary extensions. Shared redaction in `platform/errors` remains mandatory for descriptor normalization, but successful redaction alone does not authorize exposing descriptor messages, extensions, request payloads, personal information, stack traces, credentials, or local paths. `platform/problem-details` neither re-redacts nor invents a sensitive-key classifier or hashing scheme.

Kernel owns UnitOfWork lifecycle, correlation identity, and reset orchestration; Foundation retains its context and reset mechanisms. The model, adapter, renderer, provider, and middleware do not read or write `ContextStore`, derive public request identifiers, create mutable request-local registries, install reset hooks, or contribute `kernel.reset` registrations.

No new logging pipeline, metric, span, error-event stream, exporter, tracer lifecycle, or reporter is introduced. Existing diagnostics and eligible reporting remain within the canonical error handler. Repeated independent requests cannot disclose earlier request state through this package.

### Decision 9: Keep public formats closed and verify canonical HTTP integration

HTML error pages, JSON/HTML content negotiation, custom problem-type URI catalogs, localized or caller-supplied titles, public `detail` or `instance`, arbitrary extensions, and configurable disclosure are outside the public presentation policy. None may be enabled implicitly through existing descriptor fields.

Standalone middleware tests and source/compiled definition parity checks validate package-local behavior but do not establish actual outermost execution. Integration verification must cover canonical HTTP ordering, application 404/405 error flow, exact Allow-header materialization, invalid required 405 metadata producing emergency 500 without Allow, original-Throwable precedence, final response completion, and repeated-request isolation through the canonical HTTP runtime.

## Consequences

### Positive consequences

- one canonical Throwable normalization and reporting owner serves HTTP clients without another exception-mapping pipeline;
- RFC9457 JSON responses retain the fixed RFC7807-compatible baseline and never expose arbitrary diagnostic fields;
- status/title mapping, stable JSON bytes, emergency fallback, and original-Throwable precedence are deterministic;
- explicit module selection and a single existing priority-`1000` tag preserve HTTP slot ownership and unselected-target isolation;
- declarative service definitions preserve source/compiled container equivalence without runtime closures or service locators;
- the package remains stateless and does not add a second redaction, telemetry, context, or reset owner.

### Trade-offs

- only the safe `type`, `title`, and `status` members are public; applications cannot configure detailed error text or custom problem types in this baseline;
- an HTTP target must explicitly select the module and supply compatible response and stream factories;
- when PSR response construction fails, the original downstream Throwable propagates instead of a Problem Details response;
- the HTTP composition boundary owns actual outermost placement and effective participation checks; package-local middleware verification does not replace runtime integration verification;
- status 405 requires valid bounded method metadata; invalid or missing metadata produces the fixed emergency 500 instead of an incomplete 405 response.

## Rejected alternatives

### Return `ErrorDescriptor` directly as a public HTTP response

Rejected because format-neutral descriptor messages and extensions are not authorized public error fields. Transport output needs its own fixed disclosure schema without changing canonical error contracts.

### Introduce an HTTP-specific exception handler or mapper registry

Rejected because `platform/errors` already owns one canonical normalization and mapper-dispatch pipeline. A second handler would duplicate reporting and fallback decisions and could normalize the same Throwable more than once.

### Add a renderer interface, serializer registry, or ad-hoc JSON writer

Rejected because one stateless renderer and the existing Foundation stable encoder provide the required format and byte determinism. Additional abstractions would introduce an unsupported selection and serialization path.

### Copy redacted descriptor messages or allow public debug overrides

Rejected because redaction is not permission to publish arbitrary diagnostics. Debug switches, `detail`, `instance`, extension passthrough, and custom public titles expand the disclosure surface beyond the fixed schema.

### Activate middleware on package installation or place it in platform/http defaults

Rejected because target-local module selection controls runtime participation. Package installation does not imply a compatible HTTP target or PSR factory bindings, and `platform/http` must not import optional package middleware by default.

### Introduce another HTTP middleware tag or package-owned tag metadata schema

Rejected because `ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE` and its owner-defined semantics already exist. Owner/path/mode metadata or a competing slot catalog would split middleware ordering authority.

### Retry the handler, render the rendering failure, or replace the original Throwable

Rejected because retries may duplicate normalization and reporting, recurse into a failed renderer, or disclose secondary diagnostics. The fixed emergency body and original-Throwable precedence preserve fail-closed behavior.

### Add a ContextStore, observability pipeline, or reset lifecycle to rendering

Rejected because presentation is stateless and must not own UnitOfWork, correlation, reporting, spans, metrics, logs, or state cleanup. Existing runtime owners already define those boundaries.

### Introduce HTML responses or richer problem metadata in the baseline

Rejected because HTML rendering, content negotiation, and non-baseline public metadata require separately owned presentation and disclosure policies rather than implicit access to internal error details.

## Non-goals

This decision does not establish a second `ErrorDescriptor` schema, exception hierarchy, canonical error handler, reporter, mapper registry, serializer, redaction engine, telemetry backend, middleware slot owner, or HTTP pipeline. It does not introduce HTML error pages, content negotiation, custom type catalogs, arbitrary public fields, diagnostic disclosure switches, request context storage, UnitOfWork hooks, mutable registries, or reset services. It does not introduce arbitrary descriptor-to-header mapping or transfer HTTP composition ownership to Problem Details.

## Related SSoT

- `docs/ssot/problem-details.md`
- `docs/ssot/error-descriptor.md`
- `docs/ssot/errors-boundary.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/sensitive-data-redaction.md`
- `docs/ssot/http-middleware-catalog.md`
- `docs/ssot/http-middleware-wiring.md`
- `docs/ssot/http-app-dispatch.md`
- `docs/ssot/tags.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/modules-and-manifests.md`
- `docs/ssot/runtime-container-definitions.md`

## Related ADRs

- `docs/adr/ADR-0003-observability-errordescriptor-health-profiling-ports.md`
- `docs/adr/ADR-0005-routing-httpapp-ports.md`
- `docs/adr/ADR-0010-sensitive-data-redaction-boundary.md`
- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
- `docs/adr/ADR-0029-kernel-container-compile-artifact.md`
- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md`
- `docs/adr/ADR-0036-platform-errors-errorhandler-exception-mapper.md`
- `docs/adr/ADR-0041-platform-http-runtime-pipeline-wiring.md`
- `docs/adr/ADR-0043-platform-http-app-router-middleware-action-invoker.md`
