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

# ErrorDescriptor SSoT

## Scope

This document is the Single Source of Truth for the human-readable `ErrorDescriptor` field reference, field meanings, field rules, mapping hints, extension payload constraints, safe examples, and DTO boundary.

This document governs:

```text
Coretsia\Contracts\Observability\Errors\ErrorDescriptor
```

The implementation path is:

```text
framework/packages/core/contracts/src/Observability/Errors/ErrorDescriptor.php
```

It complements:

```text
docs/ssot/errors-boundary.md
docs/ssot/observability-and-errors.md
docs/ssot/observability.md
docs/ssot/dto-policy.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Canonical authority

`ErrorDescriptor` is the canonical descriptor shape for normalized errors.

This document is the single human-readable field reference for `ErrorDescriptor`.

`docs/ssot/observability-and-errors.md` remains the ports, boundary, payload, and redaction overview.

`docs/ssot/observability-and-errors.md` MUST NOT redefine a competing field-by-field `ErrorDescriptor` schema.

Runtime adapters, platform packages, docs, and tests MUST treat this document as the canonical field reference.

## Descriptor purpose

`ErrorDescriptor` represents a normalized, format-neutral error.

It is the stable output of error normalization.

The canonical flow is:

```text
Throwable → ErrorDescriptor → runtime adapters
```

`ErrorDescriptor` is not:

- a raw exception wrapper;
- an HTTP problem-details model;
- a PSR-7 model;
- a CLI output model;
- a worker failure payload model;
- a logger event model;
- a tracing span model;
- a metrics label set;
- a DTO-marker class by default.

## DTO boundary

`ErrorDescriptor` is a canonical descriptor model.

It is NOT automatically a DTO under DTO marker policy.

DTO gates apply only to explicitly marked DTO transport classes.

A class is a DTO only when explicitly marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

`ErrorDescriptor` MUST NOT be treated as a DTO merely because it has a structured shape, constructor, fields, accessors, or an exported array representation.

`ErrorDescriptor` shape rules are enforced by contracts tests and this SSoT, not by DTO gates.

## Canonical field set

The canonical logical fields are:

```text
code
message
severity
httpStatus
extensions
```

No additional canonical fields exist in `ErrorDescriptor`.

Runtime adapters MUST NOT invent extra normalized descriptor fields.

Transport-specific fields belong to transport-specific output models derived from `ErrorDescriptor`.

## Exported array key order

When exported as a PHP array shape, `ErrorDescriptor` MUST use this deterministic top-level key order:

```text
code
extensions
httpStatus
message
severity
```

This order follows byte-order `strcmp` for the current field set.

The exported array shape MUST remain stable and contract-tested.

## Field reference

| field        | type                  | required | meaning                                                                |
|--------------|-----------------------|----------|------------------------------------------------------------------------|
| `code`       | `string`              | yes      | Stable machine-readable normalized error code.                         |
| `extensions` | `array<string,mixed>` | no       | Safe json-like extension map for deterministic non-transport metadata. |
| `httpStatus` | `int\|null`           | no       | Optional HTTP status hint only.                                        |
| `message`    | `string`              | yes      | Safe human-readable message.                                           |
| `severity`   | `string`              | yes      | Stable normalized severity value.                                      |

## Constructor shape

The canonical constructor shape is:

```php
public function __construct(
    string $code,
    string $message,
    ErrorSeverity $severity,
    ?int $httpStatus = null,
    array $extensions = [],
)
```

`httpStatus` MUST default to `null`.

`extensions` MUST default to an empty map.

`extensions` MUST NOT be a non-empty list at the root.

## Accessor shape

The canonical accessor shape is:

```php
code(): string
message(): string
severity(): ErrorSeverity
httpStatus(): ?int
extensions(): array
toArray(): array
```

Accessors MUST NOT expose raw throwable objects, transport objects, request objects, response objects, PSR-7 objects, vendor SDK objects, service instances, closures, resources, or runtime wiring objects.

## `code`

`code` is a stable machine-readable normalized error code.

It MUST be a non-empty safe single-line string.

It MUST be ASCII-compatible.

It MUST start with a letter.

It MAY contain letters, digits, underscore, dot, colon, or hyphen after the first character.

The current contracts validation pattern is:

```text
^[A-Za-z][A-Za-z0-9_.:-]*$
```

`code` MUST NOT contain:

- raw exception messages;
- raw paths;
- raw payload values;
- raw SQL;
- headers;
- cookies;
- credentials;
- tokens;
- private customer data;
- environment-specific bytes;
- CR or LF.

### Code mapping hints

Runtime owner packages SHOULD use stable package or domain prefixes.

Valid examples:

```text
core.internal_error
config.validation_failed
http.not_found
database.query_failed
worker.message_rejected
```

Invalid examples:

```text
/tmp/app/cache/failure
SELECT * FROM users
token-expired-for-user-123
123.invalid
```

## `message`

`message` is a safe human-readable message.

It MUST be a non-empty safe single-line string.

It MUST be suitable for user-facing or operator-facing contexts.

It MUST NOT contain:

- secrets;
- raw payloads;
- raw exception messages when unsafe;
- raw SQL;
- raw headers;
- raw cookies;
- request bodies;
- response bodies;
- credentials;
- tokens;
- session identifiers;
- private customer data;
- absolute local paths;
- CR or LF.

Valid examples:

```text
Unexpected internal error.
Configuration validation failed.
Requested resource was not found.
Operation is temporarily unavailable.
```

Invalid examples:

```text
SQL failed: SELECT * FROM users WHERE token = ...
Could not read C:\Users\Example\project\.env
Authorization failed for Bearer ...
Raw request body was ...
```

## `severity`

`severity` is the normalized error severity.

The canonical values are defined by:

```text
Coretsia\Contracts\Observability\Errors\ErrorSeverity
```

Canonical severity values:

```text
info
warning
error
critical
```

Severity values MUST be stable lowercase ASCII strings.

Severity values MUST be compared byte-for-byte.

Severity values MUST NOT depend on locale, translated labels, vendor logger levels, or transport-specific status codes.

`ErrorSeverity` is not a logger-level enum.

Runtime loggers MAY map severity to logger-specific levels.

Logger-specific levels such as `debug`, `notice`, `alert`, or `emergency` are not canonical `ErrorDescriptor` severity values unless a future SSoT explicitly promotes them.

### Severity mapping hints

Suggested mapping policy:

| condition                                      | severity   |
|------------------------------------------------|------------|
| Expected recoverable or informational outcome. | `info`     |
| Degraded behavior or validation boundary.      | `warning`  |
| Failed operation requiring error handling.     | `error`    |
| Severe system failure or unsafe continuation.  | `critical` |

These hints do not replace runtime owner policy.

## `httpStatus`

`httpStatus` is an optional HTTP status hint.

It MUST be either:

```text
null
```

or an integer in the inclusive range:

```text
100..599
```

`httpStatus` MUST NOT make `core/contracts` depend on HTTP packages, PSR-7, framework HTTP objects, or problem-details renderers.

Non-HTTP runtimes MAY ignore `httpStatus`.

HTTP adapters MAY use `httpStatus` when converting `ErrorDescriptor` into RFC7807/problem-details or another HTTP-specific representation.

`httpStatus` is not the canonical error category.

The canonical error category is `code`.

### HTTP status mapping hints

Suggested mapping policy:

| condition                       | httpStatus |
|---------------------------------|------------|
| No HTTP context.                | `null`     |
| Validation failure.             | `400`      |
| Authentication required.        | `401`      |
| Authorization denied.           | `403`      |
| Resource not found.             | `404`      |
| Conflict with current state.    | `409`      |
| Rate limit exceeded.            | `429`      |
| Unexpected server failure.      | `500`      |
| Temporary upstream unavailable. | `503`      |

These hints are adapter policy, not a transport dependency.

## `extensions`

`extensions` is a safe json-like extension map for deterministic non-transport metadata.

The root value MUST be a map with string keys.

A non-empty list MUST NOT be used as the root `extensions` value.

An empty array represents the empty extension map at this contract boundary.

Extension map keys MUST be:

- strings;
- non-empty;
- safe single-line values.

Extension values MUST follow the json-like payload policy in this document.

`extensions` MUST NOT be used as a dump for raw exception, request, response, database, queue, or profiler data.

## Extensions payload constraints

`ErrorDescriptor.extensions` MUST be json-like and MUST follow the Phase 0 float-forbidden policy.

Allowed scalar values:

```text
null
bool
int
string
```

Allowed container values:

```text
list of allowed values
map with string keys and allowed values
```

Forbidden at any nesting depth:

- floats;
- `NaN`;
- `INF`;
- `-INF`;
- PHP objects;
- closures;
- resources;
- streams;
- filesystem handles;
- service instances;
- runtime wiring objects;
- executable validators;
- throwable instances;
- request objects;
- response objects;
- PSR-7 objects;
- vendor SDK objects.

Floats are forbidden at any nesting depth.

Decimal values, if needed, MUST be represented as strings with an owner-documented format.

## Extensions redaction constraints

`extensions` MUST be safe-by-design.

`extensions` MUST NOT contain:

- raw headers;
- raw cookies;
- raw authorization data;
- raw auth identifiers;
- raw session identifiers;
- raw tokens;
- raw request payloads;
- raw response payloads;
- raw body values;
- raw SQL;
- raw profile payloads;
- raw persistence payloads;
- credentials;
- passwords;
- private keys;
- private customer data;
- absolute local paths;
- environment-specific bytes.

Safe diagnostics MAY use:

```text
hash(value)
len(value)
```

Safe derivations MUST NOT expose raw values or allow reconstruction of sensitive values.

## Extensions determinism

Extension maps MUST be ordered deterministically by string key using byte-order `strcmp`.

Nested maps MUST be ordered recursively by string key using byte-order `strcmp`.

Lists MUST preserve semantic order.

Ordering MUST NOT depend on:

- filesystem traversal order;
- Composer package order;
- PHP hash-map insertion side effects;
- process locale;
- host platform;
- timestamps;
- random values.

## Safe examples

### Internal error

```php
new ErrorDescriptor(
    code: 'core.internal_error',
    message: 'Unexpected internal error.',
    severity: ErrorSeverity::Error,
    httpStatus: 500,
)
```

Exported shape:

```php
[
    'code' => 'core.internal_error',
    'extensions' => [],
    'httpStatus' => 500,
    'message' => 'Unexpected internal error.',
    'severity' => 'error',
]
```

### Validation error with safe extensions

```php
new ErrorDescriptor(
    code: 'config.validation_failed',
    message: 'Configuration validation failed.',
    severity: ErrorSeverity::Warning,
    httpStatus: 400,
    extensions: [
        'reason' => 'CONFIG_DIRECTIVE_UNKNOWN',
        'root' => 'app',
        'violationCount' => 2,
    ],
)
```

Exported shape:

```php
[
    'code' => 'config.validation_failed',
    'extensions' => [
        'reason' => 'CONFIG_DIRECTIVE_UNKNOWN',
        'root' => 'app',
        'violationCount' => 2,
    ],
    'httpStatus' => 400,
    'message' => 'Configuration validation failed.',
    'severity' => 'warning',
]
```

### Non-HTTP worker error

```php
new ErrorDescriptor(
    code: 'worker.message_rejected',
    message: 'Worker message was rejected.',
    severity: ErrorSeverity::Error,
    httpStatus: null,
    extensions: [
        'operation' => 'consume',
        'outcome' => 'rejected',
    ],
)
```

Exported shape:

```php
[
    'code' => 'worker.message_rejected',
    'extensions' => [
        'operation' => 'consume',
        'outcome' => 'rejected',
    ],
    'httpStatus' => null,
    'message' => 'Worker message was rejected.',
    'severity' => 'error',
]
```

## Unsafe examples

The following examples are invalid because they expose unsafe data:

```php
new ErrorDescriptor(
    code: 'database.query_failed',
    message: 'Query failed: SELECT * FROM users WHERE token = ...',
    severity: ErrorSeverity::Error,
)
```

```php
new ErrorDescriptor(
    code: 'http.auth_failed',
    message: 'Authorization failed.',
    severity: ErrorSeverity::Warning,
    extensions: [
        'authorization' => 'Bearer ...',
    ],
)
```

```php
new ErrorDescriptor(
    code: 'http.bad_request',
    message: 'Invalid request.',
    severity: ErrorSeverity::Warning,
    extensions: [
        'headers' => [
            'cookie' => '...',
        ],
    ],
)
```

```php
new ErrorDescriptor(
    code: 'profile.failed',
    message: 'Profiling failed.',
    severity: ErrorSeverity::Error,
    extensions: [
        'payload' => 'raw-profile-payload',
    ],
)
```

```php
new ErrorDescriptor(
    code: 'metrics.failed',
    message: 'Metrics failed.',
    severity: ErrorSeverity::Error,
    extensions: [
        'durationSeconds' => 0.25,
    ],
)
```

The last example is invalid because floats are forbidden. Use an integer unit or documented string decimal instead.

## Transport adaptation

Runtime adapters MAY derive transport-specific output from `ErrorDescriptor`.

Transport-specific output MUST NOT become the canonical descriptor shape.

### HTTP

HTTP adapters MAY derive RFC7807/problem-details.

They MAY map:

```text
code → type or extension field
message → title or detail according to adapter policy
httpStatus → status
extensions → safe extension members
```

HTTP adapters MUST NOT require `ErrorDescriptor` to contain PSR-7 objects, request objects, response objects, headers, or problem-details objects.

### CLI

CLI adapters MAY render `code`, `message`, and safe selected metadata.

CLI adapters MUST NOT render raw extensions blindly.

### Worker

Worker adapters MAY render `code`, `message`, `severity`, and safe selected metadata into failure results.

Worker adapters MUST NOT attach raw queue messages or payloads.

## Observability use

Logs, spans, and metrics MAY use safe derived data from `ErrorDescriptor`.

Metrics MUST still follow:

```text
docs/ssot/observability.md
```

Metric labels MUST use only allowlisted label keys.

`ErrorDescriptor.extensions` MUST NOT be copied wholesale into metric labels.

`ErrorDescriptor.extensions` MUST NOT be copied wholesale into span attributes unless every value is safe, bounded, and allowed by observability redaction policy.

## Contract enforcement evidence

Current contracts-level enforcement evidence includes:

```text
framework/packages/core/contracts/tests/Contract/ErrorDescriptorShapeContractTest.php
framework/packages/core/contracts/tests/Contract/ErrorDescriptorHttpStatusIsOptionalContractTest.php
framework/packages/core/contracts/tests/Contract/ContractsDoNotReferencePsr7ContractTest.php
```

The extension json-like and float-forbidden policy is enforced by:

```text
framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsAreJsonLikeContractTest.php
```

## Non-goals

This SSoT does not define:

- concrete exception mapper classes;
- mapper registry implementation;
- HTTP problem-details JSON formatting;
- CLI output formatting;
- worker failure formatting;
- localization;
- translation;
- logging backend schema;
- tracing backend schema;
- metric schema;
- storage format;
- generated artifact format.

## Cross-references

- [Errors Boundary SSoT](./errors-boundary.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [Observability Naming and Labels Allowlist](./observability.md)
- [DTO Policy](./dto-policy.md)
- [SSoT Index](./INDEX.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
