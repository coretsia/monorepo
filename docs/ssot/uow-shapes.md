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

# UnitOfWork Shapes SSoT

## Scope

This document is the Single Source of Truth for Coretsia Kernel UnitOfWork shape contracts.

It defines the canonical format-neutral field sets, field meanings, exported array shapes, json-like payload policy, deterministic normalization rules, export boundary rules, safety/redaction constraints, and DTO boundary for:

```text
Coretsia\Kernel\Runtime\UnitOfWorkContext
Coretsia\Kernel\Runtime\UnitOfWorkResult
```

The implementation paths are:

```text
framework/packages/core/kernel/src/Runtime/Internal/JsonLikeShapeNormalizer.php
framework/packages/core/kernel/src/Runtime/UnitOfWorkContext.php
framework/packages/core/kernel/src/Runtime/UnitOfWorkResult.php
```

The validation exception paths are:

```text
framework/packages/core/kernel/src/Runtime/Exception/UnitOfWorkContextInvalidException.php
framework/packages/core/kernel/src/Runtime/Exception/UnitOfWorkResultInvalidException.php
```

This document complements:

```text
docs/ssot/json-like-runtime-values.md
docs/ssot/uow-outcome-policy.md
docs/ssot/uow-and-reset-contracts.md
docs/ssot/error-descriptor.md
docs/ssot/time-ids-and-duration.md
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/dto-policy.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Canonical authority

`core/kernel` owns UnitOfWork runtime shapes.

This document is the canonical human-readable field reference for UnitOfWork context and result shapes.

Runtime code, platform adapters, hooks, generated artifacts, tests, and documentation MUST treat this document as the canonical UnitOfWork shape authority.

`docs/ssot/json-like-runtime-values.md` owns the reusable baseline json-like runtime value model, baseline scalar/type rejection, baseline recursive deterministic normalization, and baseline path/reason diagnostics.

`docs/ssot/uow-outcome-policy.md` owns lifecycle invariants and outcome mapping policy.

This document owns the UoW-specific structure and safety rules of the shapes that carry context and result data.

This document owns:

- `attributes` root map policy;
- `extensions` root map policy;
- exported `error` map policy;
- unsafe metadata key policy;
- `attributesMaxDepth`;
- `attributesMaxKeys`;
- UoW exception codes and reason tokens;
- mapping baseline json-like failures to UoW-specific failures.

## Ownership

The owner package is:

```text
core/kernel
```

The package path is:

```text
framework/packages/core/kernel/
```

The Composer package is:

```text
coretsia/core-kernel
```

The module id is:

```text
core.kernel
```

The config root is:

```text
kernel
```

## Package dependency policy

`core/kernel` MAY depend on:

```text
core/contracts
core/foundation
```

Kernel UoW shape normalization consumes the Foundation-owned baseline json-like normalizer:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

through the Kernel-owned wrapper:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

Kernel MUST NOT duplicate the baseline recursive json-like walker.

`core/kernel` MUST NOT depend on:

```text
platform/*
integrations/*
Psr\Http\Message\*
Psr\Http\Server\*
```

Kernel UnitOfWork shapes MUST remain format-neutral.

They MUST NOT expose:

- PSR-7 request or response objects;
- PSR-15 middleware or handler objects;
- Symfony HTTP objects;
- CLI command objects;
- queue message objects;
- scheduler job objects;
- transport-specific response objects;
- container objects;
- service instances;
- runtime wiring objects.

## Shape purpose

UnitOfWork shapes provide a stable format-neutral boundary for one runtime unit of work.

The canonical conceptual flow is:

```text
UnitOfWorkContext -> runtime execution -> UnitOfWorkResult
```

The context shape represents safe input metadata known at the beginning of the unit of work.

The result shape represents safe completion metadata known at the end of the unit of work.

Both shapes are safe structural data carriers.

They are not transport output models.

They are not observability events.

They are not HTTP responses.

They are not CLI output payloads.

They are not DTO-marker classes by default.

## Runtime type vocabulary

The canonical UnitOfWork type values are:

```text
http
cli
queue
scheduler
```

The values are stable lowercase ASCII tokens.

The values MUST be compared byte-for-byte.

The values MUST NOT be translated, localized, title-cased, or transport-object-derived.

No other UnitOfWork type value is canonical in this epic.

Adding a new type requires direct update of this SSoT and the corresponding contract tests.

## Outcome vocabulary

The canonical UnitOfWork outcome values are:

```text
success
handled_error
fatal_error
```

The values are stable lowercase ASCII tokens.

The values MUST be compared byte-for-byte.

Outcome mapping rules for HTTP and CLI are owned by:

```text
docs/ssot/uow-outcome-policy.md
```

This document references the outcome tokens only as a field vocabulary for `UnitOfWorkResult`.

## UnitOfWorkContext

`UnitOfWorkContext` is the canonical Kernel runtime shape for the beginning of a UnitOfWork.

It is format-neutral and MUST NOT contain transport objects.

### Canonical field set

The canonical logical fields are:

```text
uowId
type
startedAt
correlationId
attributes
```

No additional canonical context fields exist in this epic.

Runtime adapters MAY derive adapter-specific safe attributes inside `attributes`.

Runtime adapters MUST NOT add top-level fields to the exported context shape.

### Exported array key order

When exported as a PHP array shape, `UnitOfWorkContext` MUST use this deterministic top-level key order:

```text
attributes
correlationId
startedAt
type
uowId
```

This order follows byte-order `strcmp` for the current exported field set.

The exported array shape MUST remain stable and contract-tested.

### Field reference

| field           | type                  | required | meaning                                                     |
|-----------------|-----------------------|----------|-------------------------------------------------------------|
| `attributes`    | `array<string,mixed>` | yes      | Safe json-like metadata map for the UnitOfWork context.     |
| `correlationId` | `string`              | yes      | Safe correlation id. ULID format is recommended.            |
| `startedAt`     | `int`                 | yes      | Wall-clock start timestamp in Unix epoch milliseconds.      |
| `type`          | `string`              | yes      | UnitOfWork type token: `http`, `cli`, `queue`, `scheduler`. |
| `uowId`         | `string`              | yes      | Stable UnitOfWork id. ULID format is recommended.           |

### `uowId`

`uowId` is a stable safe id for one UnitOfWork.

`uowId` MUST be a non-empty string.

ULID format is recommended.

`uowId` SHOULD be generated by the canonical Foundation id generator:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

`uowId` MUST NOT be derived from:

- credentials;
- passwords;
- secrets;
- tokens;
- authorization headers;
- cookies;
- session ids;
- raw request bodies;
- raw response bodies;
- raw headers;
- raw SQL;
- queue payloads;
- private customer data;
- direct user identifiers such as email, phone, full name, username, or external account identifiers.

`uowId` MUST NOT be used as a metric label.

### `type`

`type` identifies the runtime kind of the UnitOfWork.

Valid values are exactly:

```text
http
cli
queue
scheduler
```

`type` MUST be a non-empty string from the canonical vocabulary.

`type` MAY be used as a safe operation value for observability where the owning metric/span policy allows it.

### `startedAt`

`startedAt` is the wall-clock start timestamp.

It MUST be an integer Unix epoch timestamp in milliseconds.

The timestamp MUST be UTC.

`startedAt` SHOULD be captured from the canonical runtime clock:

```text
Psr\Clock\ClockInterface
```

`startedAt` MUST NOT be used to calculate canonical duration.

System wall time may move forward or backward.

Duration measurement MUST use:

```text
Coretsia\Foundation\Time\Stopwatch
```

### `correlationId`

`correlationId` is a safe correlation identifier.

`correlationId` MUST be a non-empty string.

ULID format is recommended.

`correlationId` MAY be used for log or tracing correlation according to observability policy.

`correlationId` MUST NOT be used as a metric label.

`correlationId` MUST NOT expose direct user identifiers, secrets, tokens, session ids, authorization values, raw payloads, raw SQL, or private customer data.

### `attributes`

`attributes` is a safe json-like metadata map.

The baseline json-like value model is owned by:

```text
docs/ssot/json-like-runtime-values.md
```

The root `attributes` value MUST be a map with string keys.

The root map requirement is Kernel-owned UoW policy.

A non-empty list MUST NOT be used as the root `attributes` value.

An empty array represents the empty attributes map at this shape boundary.

`attributes` MUST be normalized before crossing Kernel hook/export boundaries.

Baseline recursive normalization MUST be delegated to:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

through:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

`attributes` MUST obey the configured defensive limits:

```text
kernel.uow.attributes.max_depth
kernel.uow.attributes.max_keys
```

The default limits are:

| key                               | default |
|-----------------------------------|---------|
| `kernel.uow.attributes.max_depth` | `10`    |
| `kernel.uow.attributes.max_keys`  | `200`   |

Limit failures MUST be deterministic and safe.

Diagnostics MAY include only safe structural metadata such as path, reason code, and type name.

Diagnostics MUST NOT include raw attribute values.

## UnitOfWorkResult

`UnitOfWorkResult` is the canonical Kernel runtime shape for the completion of a UnitOfWork.

It is format-neutral and MUST NOT contain transport objects.

### Canonical field set

The canonical logical fields are:

```text
uowId
type
correlationId
startedAt
finishedAt
durationMs
outcome
error
extensions
```

`error` is optional.

No additional canonical result fields exist in this epic.

Runtime adapters MAY derive adapter-specific safe metadata inside `extensions`.

Runtime adapters MUST NOT add top-level fields to the exported result shape.

### Exported array key order

When exported as a PHP array shape and `error` is present, `UnitOfWorkResult` MUST use this deterministic top-level key order:

```text
correlationId
durationMs
error
extensions
finishedAt
outcome
startedAt
type
uowId
```

When `error` is absent, the `error` key SHOULD be omitted.

The remaining keys MUST keep deterministic byte-order `strcmp` order:

```text
correlationId
durationMs
extensions
finishedAt
outcome
startedAt
type
uowId
```

The exported array shape MUST remain stable and contract-tested.

### Field reference

| field           | type                  | required | meaning                                                     |
|-----------------|-----------------------|----------|-------------------------------------------------------------|
| `correlationId` | `string`              | yes      | Safe correlation id copied from the context.                |
| `durationMs`    | `int`                 | yes      | Canonical non-negative duration in integer milliseconds.    |
| `error`         | `array<string,mixed>` | no       | Normalized json-like exported error map.                    |
| `extensions`    | `array<string,mixed>` | yes      | Safe json-like completion metadata map.                     |
| `finishedAt`    | `int`                 | yes      | Wall-clock completion timestamp in Unix epoch milliseconds. |
| `outcome`       | `string`              | yes      | Outcome token: `success`, `handled_error`, `fatal_error`.   |
| `startedAt`     | `int`                 | yes      | Wall-clock start timestamp copied from the context.         |
| `type`          | `string`              | yes      | UnitOfWork type copied from the context.                    |
| `uowId`         | `string`              | yes      | UnitOfWork id copied from the context.                      |

### `uowId`

`uowId` MUST match the originating context `uowId`.

It MUST remain a safe non-empty string.

It MUST NOT be used as a metric label.

### `type`

`type` MUST match the originating context `type`.

It MUST be one of:

```text
http
cli
queue
scheduler
```

### `correlationId`

`correlationId` MUST match the originating context `correlationId`.

It MUST remain a safe non-empty string.

It MUST NOT be used as a metric label.

### `startedAt`

`startedAt` MUST match the originating context `startedAt`.

It is a wall-clock timestamp in Unix epoch milliseconds.

It MUST NOT be used as the canonical duration source.

### `finishedAt`

`finishedAt` is the wall-clock completion timestamp.

It MUST be an integer Unix epoch timestamp in milliseconds.

The timestamp MUST be UTC.

`finishedAt` SHOULD be captured from the canonical runtime clock:

```text
Psr\Clock\ClockInterface
```

Because wall-clock time is not monotonic, consumers MUST NOT rely on:

```text
finishedAt >= startedAt
```

Consumers MUST NOT derive duration from:

```text
finishedAt - startedAt
```

### `durationMs`

`durationMs` is the canonical duration field.

It MUST be an integer.

It MUST be greater than or equal to zero.

The canonical exported unit is milliseconds.

`durationMs` MUST be measured from the canonical monotonic timing source:

```text
Coretsia\Foundation\Time\Stopwatch
```

`durationMs` MUST NOT be calculated from wall-clock timestamps.

`durationMs` MUST NOT be represented as float seconds.

`durationMs` MAY be used as a metric value or span/log field according to owner observability policy.

`durationMs` MUST NOT be used as a metric label unless a future observability owner explicitly allows such a label.

### `outcome`

`outcome` is the canonical completion category.

Valid values are exactly:

```text
success
handled_error
fatal_error
```

Outcome mapping policy is defined by:

```text
docs/ssot/uow-outcome-policy.md
```

`outcome` MAY be used as a safe metric label only where the metric-specific catalog allows it.

### `error`

`error` is optional.

Internal Kernel runtime code MAY represent `error` as:

```text
Coretsia\Contracts\Observability\Errors\ErrorDescriptor
```

Before crossing any Kernel hook/export boundary, `error` MUST be normalized to a json-like exported error map.

No object instance MAY cross the hook/export boundary.

The exported error map MUST be safe and json-like.

The recommended exported error map is:

```text
ErrorDescriptor::toArray()
```

The exported error map MUST NOT contain:

- raw Throwable objects;
- raw exception messages when unsafe;
- stack traces;
- transport objects;
- request objects;
- response objects;
- PSR-7 objects;
- CLI command objects;
- queue message objects;
- raw headers;
- raw cookies;
- raw authorization values;
- raw session identifiers;
- raw tokens;
- raw request payloads;
- raw response payloads;
- raw SQL;
- private customer data;
- service instances;
- closures;
- resources.

If no error exists, the `error` key SHOULD be omitted from the exported result shape.

### `extensions`

`extensions` is a safe json-like metadata map for completion-side data.

The baseline json-like value model is owned by:

```text
docs/ssot/json-like-runtime-values.md
```

The root `extensions` value MUST be a map with string keys.

The root map requirement is Kernel-owned UoW policy.

A non-empty list MUST NOT be used as the root `extensions` value.

An empty array represents the empty extensions map at this shape boundary.

`extensions` MUST be normalized before crossing Kernel hook/export boundaries.

Baseline recursive normalization MUST be delegated to:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

through:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

`extensions` MUST NOT contain:

- stack traces;
- raw exception objects;
- raw exception messages when unsafe;
- raw request payloads;
- raw response payloads;
- raw queue payloads;
- raw SQL;
- raw headers;
- raw cookies;
- raw authorization values;
- raw session identifiers;
- raw tokens;
- PII;
- private customer data;
- direct user identifiers.

Safe metadata MAY include:

```text
safe ids
stable enums
counts
lengths
hashes
bounded safe status/category tokens
```

## Json-like value model

`UnitOfWorkContext.attributes`, `UnitOfWorkResult.extensions`, and exported `UnitOfWorkResult.error` maps MUST be json-like.

The reusable baseline json-like runtime value model is owned by:

```text
docs/ssot/json-like-runtime-values.md
```

The canonical baseline implementation is:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

The canonical baseline exception is:

```text
Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException
```

This document intentionally repeats the allowed and forbidden shape summary for UoW readability, but it does not own the reusable baseline json-like model.

Allowed scalar values are:

```text
null
bool
int
string
```

Forbidden scalar values are:

```text
float
NAN
INF
-INF
```

Allowed containers are:

```text
list<value>
array<string,value>
```

Where `value` recursively means any value accepted by the baseline json-like runtime value model.

Forbidden values at any nesting depth include:

- floats;
- `NAN`;
- `INF`;
- `-INF`;
- PHP objects;
- enum objects;
- closures;
- resources;
- streams;
- filesystem handles;
- unsupported PHP value types;
- service instances;
- runtime wiring objects;
- executable validators;
- throwable instances;
- request objects;
- response objects;
- PSR-7 objects;
- vendor SDK objects.

If a future owner needs decimal values, they MUST be represented as strings with a documented format.

Kernel owns the UoW-specific restrictions layered on top of this baseline model:

- root map policy for `attributes`;
- root map policy for `extensions`;
- exported `error` map policy;
- unsafe metadata key rejection;
- `attributesMaxDepth`;
- `attributesMaxKeys`;
- UoW-specific exception mapping.

## Root map policy

Root map policy is Kernel-owned UoW policy.

The root value for `attributes` MUST be a map.

The root value for `extensions` MUST be a map.

The exported `error` value, when present, MUST be a non-empty map.

A non-empty root list is invalid for `attributes`, `extensions`, and exported `error`.

An empty array is interpreted as an empty map at the semantic boundary for `attributes` and `extensions`.

An empty exported `error` map is invalid.

Nested lists are valid and preserve order according to the baseline json-like model.

Nested maps are valid only when all keys are strings.

Non-string keys in maps are invalid according to the baseline json-like model.

## Deterministic normalization

When a UnitOfWork shape crosses a Kernel hook/export boundary, it MUST be normalized deterministically.

Baseline recursive normalization is owned by:

```text
docs/ssot/json-like-runtime-values.md
```

The canonical baseline implementation is:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

Baseline recursive normalization rules include:

- maps are sorted recursively by string key using byte-order `strcmp`;
- lists preserve caller-supplied order;
- locale MUST NOT affect ordering;
- normalization MUST NOT call `setlocale`;
- normalization MUST NOT rely on `LC_ALL`;
- normalization MUST NOT rely on filesystem traversal order;
- normalization MUST NOT rely on Composer package order;
- normalization MUST NOT rely on PHP hash-map insertion side effects.

Kernel owns the UoW-specific normalization boundary:

- deciding when `attributes` cross a Kernel hook/export boundary;
- deciding when `extensions` cross a Kernel hook/export boundary;
- deciding when exported `error` maps cross a Kernel hook/export boundary;
- preserving deterministic top-level exported shape key order;
- applying UoW-specific root map, unsafe-key, limit, and exception mapping policy.

Top-level exported shape key order MUST follow the exported order defined in this document.

Nested maps inside `attributes`, `extensions`, and exported `error` maps MUST be recursively sorted by the Foundation baseline normalizer.

## Runtime normalizer boundary

Kernel centralizes UoW-specific shape validation in an internal runtime helper:

```text
framework/packages/core/kernel/src/Runtime/Internal/JsonLikeShapeNormalizer.php
```

The canonical internal wrapper is:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

This helper is an implementation detail of `core/kernel`.

It is not a public API, not a DI service, and not a transport extension point.

It MUST NOT be exposed through:

```text
framework/packages/core/kernel/PUBLIC_API.md
```

The Kernel wrapper MUST delegate baseline json-like validation and recursive deterministic normalization to:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

The Kernel wrapper MUST catch baseline failures from:

```text
Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException
```

and map them to UoW-specific exceptions and reason tokens.

The Kernel wrapper owns only UoW-specific rules:

- root map validation for `attributes`;
- root map validation for `extensions`;
- non-empty exported `error` map validation;
- optional depth and key-count limits for `attributes`;
- deterministic unsafe-key rejection for known unsafe metadata keys;
- safe string and safe single-line string checks;
- UoW-specific exception reason mapping;
- UoW-safe diagnostics without raw values.

The Kernel wrapper MUST NOT duplicate the baseline recursive json-like walker owned by Foundation.

## Float, NAN, and INF rejection

Floats are forbidden at any nesting depth in:

```text
UnitOfWorkContext.attributes
UnitOfWorkResult.extensions
UnitOfWorkResult.error
```

This includes:

```text
NAN
INF
-INF
```

The baseline float rejection policy is owned by:

```text
docs/ssot/json-like-runtime-values.md
```

The canonical baseline reason token is:

```text
json-like-float-forbidden
```

Kernel MUST map baseline float failures to UoW-specific reason tokens:

```text
uow-context-attributes-float-forbidden
uow-result-float-forbidden
```

When a forbidden float is found, Kernel MUST fail deterministically.

Failure diagnostics MAY include:

```text
path-to-value
stable reason code
safe type name
```

Failure diagnostics MUST NOT include the raw float value.

Invalid diagnostic examples:

```text
attributes.duration = 1.25
extensions.ratio = INF
extensions.value = NAN
```

Valid diagnostic examples:

```text
attributes.duration: uow-context-attributes-float-forbidden
extensions.items[3].ratio: uow-result-float-forbidden
```

## Path notation for diagnostics

Safe diagnostics MAY use path-to-value notation.

Baseline path construction is owned by:

```text
docs/ssot/json-like-runtime-values.md
```

Recommended notation:

```text
attributes.a.b[3].c
extensions.items[0].status
```

Path notation MUST identify structure only.

Path notation MUST NOT expose raw values.

Path notation MUST NOT expose unsafe raw map keys.

If a key itself is unsafe to print, diagnostics MUST use a stable safe placeholder.

Examples:

```text
attributes[<key>]
extensions[<key>]
extensions.safe.nested[<key>]
```

Kernel unsafe metadata key rejection MUST preserve safe structural parent paths and hide unsafe leaf key segments.

### Exception diagnostic path hardening

`UnitOfWorkContextInvalidException` and `UnitOfWorkResultInvalidException` MUST store and expose only safe diagnostic paths.

The exception `path()` accessor MUST NOT return an unsafe raw path.

When a supplied path is empty, the exception message MUST contain only the stable reason token.

When a supplied path is safe, the exception message MAY include:

```text
<reason>: value at <safe-path>
```

When a supplied path is unsafe, the exception MUST replace it with the stable placeholder:

```text
<path>
```

The placeholder is intentionally generic. It MUST NOT include the unsafe key, raw value, absolute path, SQL fragment, token, cookie, authorization value, session id, PII, stack trace, or environment-specific data.

Safe diagnostic paths MUST be structural only. They MAY include:

```text
attributes.safeKey
attributes.items[0]
attributes[<key>]
extensions.safeKey
extensions.items[0]
extensions[<key>]
error.extensions.safeKey
```

Safe diagnostic paths MUST NOT include:

- control characters;
- absolute local paths;
- path traversal;
- stream wrappers;
- raw secret-like key names;
- SQL-like fragments;
- raw values.

## Shape validation failures

Kernel shape validation failures MUST be deterministic and safe.

`UnitOfWorkContext` validation failures MUST use:

```text
Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException
CORETSIA_UOW_CONTEXT_INVALID
```

`UnitOfWorkResult` validation failures MUST use:

```text
Coretsia\Kernel\Runtime\Exception\UnitOfWorkResultInvalidException
CORETSIA_UOW_RESULT_INVALID
```

`UnitOfWorkResultInvalidException` MUST be used when Kernel rejects:

- an invalid `UnitOfWorkResult` scalar field;
- an invalid `UnitOfWorkResult.extensions` payload;
- an invalid exported `error` map derived from `ErrorDescriptor`.

Baseline json-like failures are detected by:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

and represented as:

```text
Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException
```

Kernel MUST map baseline json-like failures to UoW-specific validation failures.

The canonical context attributes mapping is:

```text
json-like-float-forbidden             -> uow-context-attributes-float-forbidden
json-like-object-forbidden            -> uow-context-attributes-object-forbidden
json-like-closure-forbidden           -> uow-context-attributes-closure-forbidden
json-like-resource-forbidden          -> uow-context-attributes-resource-forbidden
json-like-map-key-must-be-string      -> uow-context-attributes-map-key-must-be-string
json-like-type-forbidden              -> uow-context-attributes-type-forbidden
```

The canonical result mapping is:

```text
json-like-float-forbidden             -> uow-result-float-forbidden
json-like-object-forbidden            -> uow-result-object-forbidden
json-like-closure-forbidden           -> uow-result-closure-forbidden
json-like-resource-forbidden          -> uow-result-resource-forbidden
json-like-map-key-must-be-string      -> uow-result-map-key-must-be-string
json-like-type-forbidden              -> uow-result-type-forbidden
```

### Context validation reason tokens

`UnitOfWorkContextInvalidException` MUST expose the following stable public reason constants and MUST reject unknown reason strings deterministically:

```text
uow-context-invalid
uow-context-uow-id-invalid
uow-context-type-invalid
uow-context-started-at-invalid
uow-context-correlation-id-invalid
uow-context-attributes-root-map-required
uow-context-attributes-max-depth-invalid
uow-context-attributes-max-keys-invalid
uow-context-attributes-max-depth-exceeded
uow-context-attributes-max-keys-exceeded
uow-context-attributes-map-key-invalid
uow-context-attributes-unsafe-key
uow-context-attributes-string-invalid
uow-context-attributes-float-forbidden
uow-context-attributes-object-forbidden
uow-context-attributes-closure-forbidden
uow-context-attributes-resource-forbidden
uow-context-attributes-map-key-must-be-string
uow-context-attributes-type-forbidden
```

### Result validation reason tokens

`UnitOfWorkResultInvalidException` MUST expose the following stable public reason constants and MUST reject unknown reason strings deterministically:

```text
uow-result-invalid
uow-result-uow-id-invalid
uow-result-type-invalid
uow-result-correlation-id-invalid
uow-result-started-at-invalid
uow-result-finished-at-invalid
uow-result-duration-ms-invalid
uow-result-outcome-invalid
uow-result-extensions-root-map-required
uow-result-extensions-unsafe-key
uow-result-error-map-empty
uow-result-error-map-required
uow-result-map-key-invalid
uow-result-string-invalid
uow-result-float-forbidden
uow-result-object-forbidden
uow-result-closure-forbidden
uow-result-resource-forbidden
uow-result-map-key-must-be-string
uow-result-type-forbidden
```

Unknown reason strings MUST fail with `InvalidArgumentException`.

This exception-level whitelist is part of the Kernel UoW safety policy. Runtime code SHOULD use the public reason constants instead of inline string literals.

Validation diagnostics MAY include only:

```text
path-to-value
stable reason code
safe type name
```

Validation diagnostics MUST NOT include:

- rejected raw values;
- payloads;
- secrets;
- raw SQL;
- authorization data;
- cookies;
- tokens;
- session ids;
- stack traces;
- PII;
- absolute local paths;
- environment-specific data.

## Limits

Limits are Kernel-owned UoW-specific policy.

Kernel configuration defines defensive limits for `UnitOfWorkContext.attributes`:

```text
kernel.uow.attributes.max_depth
kernel.uow.attributes.max_keys
```

Defaults:

```text
kernel.uow.attributes.max_depth = 10
kernel.uow.attributes.max_keys = 200
```

`max_depth` MUST be an integer greater than zero.

`max_keys` MUST be an integer greater than zero.

`max_depth` limits recursive nesting depth for `attributes`.

`max_keys` limits the total number of map keys in `attributes`.

Limit checks MUST be deterministic.

Limit diagnostics MUST be safe and MUST NOT dump raw values.

This epic does not introduce separate config limits for `UnitOfWorkResult.extensions`.

`extensions` still MUST be json-like, normalized, and safe.

A future owner MAY introduce extension limits only by updating this SSoT, config defaults, config rules, and contract tests.

## Safety and redaction

UnitOfWork shapes MUST be safe-by-design.

`attributes` and `extensions` MUST NOT contain:

- authorization headers;
- cookies;
- session ids;
- tokens;
- passwords;
- credentials;
- private keys;
- raw headers;
- raw request payloads;
- raw response payloads;
- raw queue messages;
- raw SQL;
- stack traces;
- raw Throwable objects;
- PII;
- private customer data;
- direct user identifiers such as email, phone, full name, username, or external account identifiers;
- absolute local paths;
- environment-specific bytes.

Allowed safe metadata includes:

```text
safe ids
stable enums
counts
lengths
hashes
bounded safe status/category tokens
```

Safe derivations such as `hash(value)` and `len(value)` MUST NOT allow reconstruction of sensitive values.

Runtime owners MUST prefer omission over unsafe emission.

## Hook and export boundary

Kernel hooks and adapters receive exported array shapes, not shape objects.

Before hooks receive context data:

```text
UnitOfWorkContext -> normalized array $ctx
```

Before hooks, adapters, artifacts, or other export consumers receive result data:

```text
UnitOfWorkResult -> normalized array $result
```

No object instance MAY cross the hook/export boundary.

Forbidden boundary values include:

- `UnitOfWorkContext` object;
- `UnitOfWorkResult` object;
- `ErrorDescriptor` object;
- Throwable object;
- request object;
- response object;
- PSR-7 object;
- CLI command object;
- queue message object;
- service instance;
- closure;
- resource.

The boundary representation MUST be json-like.

## ErrorDescriptor boundary

`UnitOfWorkResult.error` MAY be held internally as:

```text
Coretsia\Contracts\Observability\Errors\ErrorDescriptor
```

The object MUST be normalized before export.

The exported representation MUST be a json-like map.

The exported representation MUST follow `ErrorDescriptor` redaction and extension constraints.

The exported representation MUST NOT require Kernel consumers to depend on HTTP, CLI, queue, scheduler, or problem-details output models.

## DTO boundary

`UnitOfWorkContext` and `UnitOfWorkResult` are canonical Kernel runtime shapes/value objects.

They are NOT DTO-marker classes by default.

DTO gates apply only to explicitly marked DTO transport classes.

A class is a DTO only when explicitly marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

`UnitOfWorkContext` and `UnitOfWorkResult` MUST NOT be treated as DTOs merely because they have structured fields, constructor parameters, accessors, or exported array representations.

Their invariants are governed by this SSoT and Kernel contract tests.

## Observability policy

This SSoT introduces no metrics, spans, or logs.

Future runtime owners MAY derive observability data from UnitOfWork shapes only when the emitted data complies with:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
```

Safe candidate values:

```text
type -> operation value where owner policy allows it
outcome -> outcome label where metric catalog allows it
durationMs -> metric value or span/log field
```

Forbidden metric labels include:

```text
uowId
correlationId
request_id
user_id
tenant_id
```

IDs MUST NOT be metric labels.

`attributes` and `extensions` MUST NOT be copied wholesale into logs, spans, or metric labels.

## Config policy

This SSoT introduces no new config root.

The existing config root is:

```text
kernel
```

The defaults file is:

```text
framework/packages/core/kernel/config/kernel.php
```

The rules file is:

```text
framework/packages/core/kernel/config/rules.php
```

The defaults file MUST return the `kernel` subtree only and MUST NOT repeat the root wrapper.

Valid shape:

```php
return [
    'uow' => [
        'attributes' => [
            'max_depth' => 10,
            'max_keys' => 200,
        ],
    ],
];
```

Invalid shape:

```php
return [
    'kernel' => [
        'uow' => [
            'attributes' => [
                'max_depth' => 10,
                'max_keys' => 200,
            ],
        ],
    ],
];
```

This SSoT MUST NOT introduce generic json-like config keys.

The following config keys MUST NOT be introduced here:

```text
kernel.json_like.*
foundation.json_like.*
foundation.serialization.json_like.*
```

Baseline json-like runtime value policy is not configurable by Kernel.

## Safe examples

### Context

```php
[
    'attributes' => [
        'method' => 'GET',
        'pathHash' => 'sha256:example',
        'routeName' => 'user.show',
    ],
    'correlationId' => '01HX7Y6V1A2B3C4D5E6F7G8H9J',
    'startedAt' => 1760000000000,
    'type' => 'http',
    'uowId' => '01HX7Y6V1A2B3C4D5E6F7G8H8',
]
```

### Successful result

```php
[
    'correlationId' => '01HX7Y6V1A2B3C4D5E6F7G8H9J',
    'durationMs' => 12,
    'extensions' => [
        'responseSizeBytes' => 512,
    ],
    'finishedAt' => 1760000000012,
    'outcome' => 'success',
    'startedAt' => 1760000000000,
    'type' => 'http',
    'uowId' => '01HX7Y6V1A2B3C4D5E6F7G8H8',
]
```

### Handled error result

```php
[
    'correlationId' => '01HX7Y6V1A2B3C4D5E6F7G8H9J',
    'durationMs' => 8,
    'error' => [
        'code' => 'config.validation_failed',
        'extensions' => [
            'root' => 'kernel',
            'violationCount' => 1,
        ],
        'httpStatus' => null,
        'message' => 'Configuration validation failed.',
        'schemaVersion' => 1,
        'severity' => 'warning',
    ],
    'extensions' => [],
    'finishedAt' => 1760000000008,
    'outcome' => 'handled_error',
    'startedAt' => 1760000000000,
    'type' => 'cli',
    'uowId' => '01HX7Y6V1A2B3C4D5E6F7G8H8',
]
```

## Unsafe examples

The following values are invalid because they expose unsafe data or violate json-like policy.

```php
[
    'attributes' => [
        'authorization' => 'Bearer ...',
    ],
]
```

```php
[
    'attributes' => [
        'cookie' => 'session=...',
    ],
]
```

```php
[
    'attributes' => [
        'rawBody' => '{"email":"person@example.com"}',
    ],
]
```

```php
[
    'extensions' => [
        'sql' => 'SELECT * FROM users WHERE token = ...',
    ],
]
```

```php
[
    'extensions' => [
        'stacktrace' => '...',
    ],
]
```

```php
[
    'extensions' => [
        'durationSeconds' => 0.25,
    ],
]
```

The last example is invalid because floats are forbidden. Use integer milliseconds instead.

## Contract enforcement evidence

Expected Kernel contract enforcement includes:

```text
framework/packages/core/kernel/tests/Contract/UnitOfWorkContextShapeContractTest.php
framework/packages/core/kernel/tests/Contract/UnitOfWorkContextAttributesAreJsonLikeContractTest.php
framework/packages/core/kernel/tests/Contract/UnitOfWorkResultShapeContractTest.php
framework/packages/core/kernel/tests/Contract/UnitOfWorkResultExtensionsAreJsonLikeContractTest.php
framework/packages/core/kernel/tests/Contract/KernelJsonLikePolicyMatchesFoundationContractTest.php
framework/packages/core/kernel/tests/Contract/KernelConfigSubtreeShapeContractTest.php
```

These tests are expected to verify:

- context field set and exported key order;
- result field set and exported key order;
- context `attributes` json-like policy;
- result `extensions` json-like policy;
- valid context attributes normalize to the same baseline recursive shape as `JsonLikeNormalizer`;
- valid result extensions normalize to the same baseline recursive shape as `JsonLikeNormalizer`;
- Kernel delegates baseline json-like validation and normalization to Foundation;
- Kernel preserves UoW-specific root map policy for `attributes`;
- Kernel preserves UoW-specific root map policy for `extensions`;
- Kernel preserves exported `error` map policy;
- Kernel preserves unsafe metadata key policy;
- float, NAN, INF, and -INF rejection;
- object, closure, resource, map-key, and type failure mapping;
- deterministic recursive map ordering;
- list order preservation;
- safe diagnostics without raw values;
- unsafe metadata keys do not leak raw key names in diagnostic paths;
- `UnitOfWorkContextInvalidException` reason strings are whitelisted;
- `UnitOfWorkResultInvalidException` reason strings are whitelisted;
- unknown UoW exception reason strings fail deterministically;
- unsafe diagnostic paths are replaced with `<path>`;
- exception `path()` accessors never expose unsafe raw paths;
- `UnitOfWorkContext` validation failures use `CORETSIA_UOW_CONTEXT_INVALID`;
- `UnitOfWorkResult` validation failures use `CORETSIA_UOW_RESULT_INVALID`;
- `kernel.uow.attributes.max_depth`;
- `kernel.uow.attributes.max_keys`;
- `config/kernel.php` returns subtree only;
- no `@*` directive keys exist in Kernel defaults.

Outcome mapping policy is enforced separately by:

```text
framework/packages/core/kernel/tests/Contract/OutcomeMappingStabilityContractTest.php
```

## Non-goals

This SSoT does not define:

- HTTP response construction;
- HTTP status code selection;
- PSR-7 or PSR-15 integration;
- CLI command execution;
- queue message execution;
- scheduler job execution;
- UnitOfWork lifecycle executor implementation;
- reusable baseline json-like runtime value model;
- baseline recursive json-like normalizer implementation;
- baseline json-like reason token ownership;
- Foundation json-like exception ownership;
- generic redaction engine;
- unsafe metadata key denylist in Foundation;
- transport/request payload semantics;
- hook dispatcher implementation;
- reset orchestrator implementation;
- reset tag discovery;
- reset tag constants;
- platform adapter implementation;
- problem-details formatting;
- CLI output formatting;
- generated artifact schemas;
- logging backend schema;
- tracing backend schema;
- metric backend schema.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Json-like Runtime Values SSoT](./json-like-runtime-values.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [ErrorDescriptor SSoT](./error-descriptor.md)
- [Time, IDs, and Duration SSoT](./time-ids-and-duration.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [DTO Policy](./dto-policy.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
