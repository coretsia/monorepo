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

# Errors Boundary SSoT

## Scope

This document is the Single Source of Truth for the Coretsia error normalization responsibility boundary, forbidden dependency directions, runtime adapter ownership, mapper discovery policy, and canonical high-level error flow.

This document governs the boundary between:

```text
core/contracts
platform/errors
platform/problem-details
HTTP/CLI/Worker runtime adapters
```

It complements:

```text
docs/ssot/error-descriptor.md
docs/ssot/observability-and-errors.md
docs/ssot/observability.md
docs/ssot/tags.md
docs/ssot/dto-policy.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Coretsia has exactly one canonical error normalization flow:

```text
Throwable → ErrorDescriptor → runtime adapters
```

No HTTP, CLI, worker, logging, tracing, or problem-details adapter may invent a competing normalized error shape.

## Responsibility boundary

### `core/contracts`

`core/contracts` owns contracts-level error ports and descriptor models.

It defines:

```text
Coretsia\Contracts\Observability\Errors\ErrorDescriptor
Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface
Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface
Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface
Coretsia\Contracts\Observability\Errors\ErrorHandlingContext
Coretsia\Contracts\Observability\Errors\ErrorSeverity
```

`core/contracts` MUST define only:

- stable ports;
- descriptor shape;
- context shape;
- enum vocabulary;
- format-neutral invariants;
- redaction and safety requirements at the contracts boundary.

`core/contracts` MUST NOT implement:

- exception mapper registries;
- concrete error handlers;
- HTTP middleware;
- CLI rendering;
- worker rendering;
- RFC7807/problem-details rendering;
- logging backends;
- tracing backends;
- metrics backends;
- DI registration;
- runtime discovery;
- generated runtime artifacts.

### `platform/errors`

`platform/errors` owns the reference runtime error normalization implementation.

It is expected to provide:

- an `ErrorHandlerInterface` implementation;
- mapper registry behavior;
- mapper discovery through the reserved `error.mapper` tag;
- safe fallback mapping when no specific mapper applies;
- noop-safe or fail-safe reporter integration, where applicable.

`platform/errors` MUST NOT redefine the `ErrorDescriptor` field schema.

`platform/errors` MUST return the canonical contracts `ErrorDescriptor`.

`platform/errors` MAY define implementation-specific mapper ordering and fallback policy, but the final normalized output MUST remain a canonical `ErrorDescriptor`.

### `platform/problem-details`

`platform/problem-details` owns HTTP problem-details adaptation.

It MAY adapt `ErrorDescriptor` to RFC7807 or another HTTP-specific representation.

It MUST treat `ErrorDescriptor.httpStatus` as an optional HTTP status hint only.

It MUST NOT require `core/contracts` to depend on:

- PSR-7;
- framework HTTP request objects;
- framework HTTP response objects;
- concrete middleware implementations;
- RFC7807-specific classes.

When installed, `platform/problem-details` is expected to wire `HttpErrorHandlingMiddleware` into:

```text
http.middleware.system_pre
```

with priority:

```text
1000
```

This is runtime policy, not a `core/contracts` dependency.

### HTTP, CLI, and Worker adapters

Runtime adapters own presentation only.

Adapters MAY render a normalized `ErrorDescriptor` into transport-specific output.

Adapters MUST NOT create alternative normalized descriptor shapes.

Adapters MUST NOT leak raw throwable details, stack traces, headers, cookies, request bodies, response bodies, raw SQL, credentials, tokens, private customer data, profile payloads, or absolute local paths.

## Canonical flow

The canonical high-level flow is:

```text
Throwable
  ↓
ExceptionMapperInterface implementations
  ↓
ErrorDescriptor
  ↓
ErrorHandlerInterface / ErrorReporterPortInterface
  ↓
Runtime adapter
  ↓
HTTP response / CLI output / worker failure result / logs / spans / metrics
```

The stable normalization point is:

```text
ErrorDescriptor
```

Transport-specific representations MUST be derived from `ErrorDescriptor`.

Transport-specific representations MUST NOT become the canonical internal error model.

## Mapper discovery

Exception mapper discovery is runtime-owned by `platform/errors`.

The canonical reserved discovery tag is:

```text
error.mapper
```

The tag is registered in:

```text
docs/ssot/tags.md
```

The contracts package MAY reference `error.mapper` in documentation as runtime policy.

The contracts package MUST NOT declare this tag as a public constant.

The contracts package MUST NOT own mapper registry semantics.

Non-owner packages using the tag MUST follow the tag registry rules and MUST NOT redefine competing semantics or competing metadata schema for `error.mapper`.

## Forbidden dependency directions

### Contracts dependency law

`core/contracts` MUST NOT depend on:

- `platform/*`;
- `integrations/*`;
- `Psr\Http\Message\*`;
- framework HTTP packages;
- framework CLI packages;
- worker runtime packages;
- concrete logger implementations;
- concrete tracing implementations;
- concrete metrics implementations;
- concrete exception mapper registries;
- concrete problem-details renderers;
- vendor SDKs;
- generated architecture artifacts.

### Platform dependency law

Runtime platform packages MAY depend on `core/contracts`.

Runtime platform packages MUST NOT force `core/contracts` to depend back on platform packages.

Allowed direction:

```text
platform/errors → core/contracts
platform/problem-details → core/contracts
platform/http → core/contracts
platform/cli → core/contracts
worker runtime package → core/contracts
```

Forbidden direction:

```text
core/contracts → platform/errors
core/contracts → platform/problem-details
core/contracts → platform/http
core/contracts → platform/cli
core/contracts → worker runtime package
```

### Adapter dependency law

Adapters MAY adapt from contracts to transport-specific output.

Adapters MUST NOT push transport-specific types into contracts.

Forbidden examples:

```text
ErrorDescriptor contains PSR-7 request
ErrorDescriptor contains PSR-7 response
ErrorDescriptor contains problem-details object
ErrorDescriptor contains HTTP headers
ErrorDescriptor contains CLI output object
ExceptionMapperInterface requires HTTP request
ErrorHandlerInterface requires PSR-7 response
```

## ErrorDescriptor authority

The single human-readable field reference for `ErrorDescriptor` is:

```text
docs/ssot/error-descriptor.md
```

`docs/ssot/observability-and-errors.md` remains the ports, boundary, payload, and redaction overview.

`docs/ssot/observability-and-errors.md` MUST NOT redefine a competing field-by-field `ErrorDescriptor` schema.

Runtime packages, adapters, tests, and documentation MUST reference `docs/ssot/error-descriptor.md` for the canonical field meanings and examples.

## Runtime adapter policy

Runtime adapters MUST preserve the canonical descriptor semantics.

### HTTP adapter policy

HTTP adapters MAY map:

```text
ErrorDescriptor → RFC7807/problem-details
```

HTTP adapters MAY use `httpStatus` when present.

HTTP adapters MUST define safe fallback status behavior when `httpStatus` is null.

HTTP adapters MUST NOT expose unsafe `extensions`.

HTTP adapters MUST NOT emit raw throwable data.

### CLI adapter policy

CLI adapters MAY map:

```text
ErrorDescriptor → CLI diagnostic output
```

CLI output MUST remain safe.

CLI output MUST NOT include raw stack traces, secrets, raw payloads, raw SQL, credentials, tokens, cookies, private customer data, or absolute local paths unless a future explicit debug policy defines a safe gated mode.

### Worker adapter policy

Worker adapters MAY map:

```text
ErrorDescriptor → worker failure result
```

Worker failure output MUST remain safe.

Worker failure output MUST NOT expose raw message payloads, queue vendor objects, credentials, tokens, profile payloads, raw SQL, or private customer data.

## Redaction boundary

Error normalization MUST follow the global observability redaction policy from:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
```

Errors, logs, spans, metrics, health output, CLI output, and worker output MUST NOT leak:

- `.env` values;
- passwords;
- credentials;
- tokens;
- private keys;
- cookies;
- authorization headers;
- session identifiers;
- request bodies;
- response bodies;
- raw SQL;
- raw profile payloads;
- raw persistence payloads;
- private customer data;
- absolute local paths.

Safe diagnostics MAY use:

```text
hash(value)
len(value)
```

Raw values MUST NOT be printed, logged, traced, exported, or rendered.

## Throwable handling

Mappers MAY inspect `Throwable` internally.

Mappers MUST NOT expose raw `Throwable` objects through `ErrorDescriptor`.

Mappers MUST NOT expose stack traces by default.

Mappers MUST NOT place raw exception messages into `ErrorDescriptor.message` when those messages may contain secrets, raw paths, raw SQL, raw payload values, tokens, credentials, private customer data, or environment-specific bytes.

Mappers SHOULD convert implementation-specific exceptions into stable error codes.

## Stability requirements

The canonical error normalization flow MUST remain stable across runtime boundaries.

The same logical error category SHOULD map to the same stable error code regardless of whether the runtime boundary is HTTP, CLI, worker, scheduler, or another unit of work.

Transport-specific differences belong to adapters, not to `ErrorDescriptor`.

## Verification evidence

This document is doc-only.

Current contracts-level enforcement evidence includes:

```text
framework/packages/core/contracts/tests/Contract/ContractsDoNotReferencePsr7ContractTest.php
framework/packages/core/contracts/tests/Contract/ErrorDescriptorShapeContractTest.php
framework/packages/core/contracts/tests/Contract/ErrorDescriptorHttpStatusIsOptionalContractTest.php
```

Future runtime evidence may include:

```text
framework/packages/platform/errors/tests/Contract/ErrorHandlerNeverThrowsContractTest.php
```

That future runtime evidence is not a precondition for this SSoT document.

## Non-goals

This SSoT does not define:

- concrete mapper registry implementation;
- mapper priority algorithm;
- concrete fallback error handler;
- concrete HTTP middleware class shape;
- concrete CLI renderer;
- concrete worker failure renderer;
- problem-details JSON field formatting;
- logging backend implementation;
- tracing backend implementation;
- metrics backend implementation;
- DI provider code;
- generated artifacts.

## Cross-references

- [ErrorDescriptor SSoT](./error-descriptor.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [Observability Naming and Labels Allowlist](./observability.md)
- [Tag Registry](./tags.md)
- [DTO Policy](./dto-policy.md)
- [SSoT Index](./INDEX.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
