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

```yaml
ssotVersion: 1
status: pre-stable
owner: core/contracts
```

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
schemaVersion
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
schemaVersion
severity
```

This order follows byte-order `strcmp` for the current exported field set.

The exported array shape MUST remain stable and contract-tested.

## Field reference

| field           | type                  | required | meaning                                                                |
|-----------------|-----------------------|----------|------------------------------------------------------------------------|
| `code`          | `string`              | yes      | Stable machine-readable normalized error code.                         |
| `extensions`    | `array<string,mixed>` | no       | Safe json-like extension map for deterministic non-transport metadata. |
| `httpStatus`    | `int\|null`           | no       | Optional HTTP status hint only.                                        |
| `message`       | `string`              | yes      | Safe human-readable message.                                           |
| `schemaVersion` | `int`                 | yes      | Stable descriptor schema version.                                      |
| `severity`      | `string`              | yes      | Stable normalized severity value.                                      |

## `schemaVersion`

`schemaVersion` is the stable descriptor schema version.

The initial canonical schema version is:

```text
1
```

`schemaVersion` MUST be a positive integer.

`schemaVersion` MUST be exported by `ErrorDescriptor::toArray()`.

`schemaVersion` MUST change only when descriptor shape compatibility changes.

Adding safe optional extension keys does not require a schema version bump.

Removing, renaming, or changing the meaning of canonical descriptor fields requires a schema version bump and policy review.

## Constructor shape

The canonical constructor shape is:

```php
public function __construct(
    string $code,
    string $message,
    ErrorSeverity $severity = ErrorSeverity::Error,
    ?int $httpStatus = null,
    array $extensions = [],
)
```

Only `code` and `message` are required constructor parameters.

`severity` MUST default to `ErrorSeverity::Error`.

`httpStatus` MUST default to `null`.

`extensions` MUST default to an empty map.

`extensions` MUST NOT be a non-empty list at the root.

## Constructor input normalization policy

`code` and `message` constructor input MUST be validated exactly as supplied.

`ErrorDescriptor` MUST NOT trim, collapse, lowercase, uppercase, or otherwise remove whitespace from `code` or `message` before validation.

`code` MUST match the canonical safe error code grammar and therefore rejects whitespace by grammar.

`message` MUST be a non-empty safe single-line string and MUST NOT contain leading or trailing whitespace.

## Accessor shape

The canonical accessor shape is:

```php
schemaVersion(): int
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

### Extension semantic-key policy

`ErrorDescriptor` performs fail-closed recursive semantic-key validation for extension maps at every nesting depth.

For policy comparison, extension keys are normalized by:

1. ASCII lowercasing;
2. removal of `_`, `-`, and `.`.

For example:

```text
access_token
access-token
access.token
accessToken
-> accesstoken
```

Known forbidden extension channels include:

- authorization and authentication data;
- headers and cookies;
- sessions and session identifiers;
- tokens and API keys;
- passwords, secrets, credentials, and private keys;
- DSNs and connection strings;
- raw, request, and response bodies or payloads;
- SQL, query, and statement data;
- stack traces, throwables, and raw exception data;
- profile and persistence payloads;
- private customer identifiers and private customer data;
- local-path channels;
- environment- and host-specific metadata;
- request and correlation identifiers.

Request and correlation identifiers MAY be safe runtime-context values, but they do not belong to `ErrorDescriptor.extensions`.

`correlation_id` and `request_id` remain owned by their respective runtime context policies. Error correlation MUST use the dedicated runtime/error handling context channels rather than duplicating those identifiers into the normalized descriptor.

In particular:

- `correlationId` belongs to `ErrorHandlingContext` when supplied to the error handling boundary;
- runtime packages MAY read `correlation_id` or `request_id` through `ContextAccessorInterface` when their owner policy permits;
- neither `correlationId` nor `requestId` MAY be copied into `ErrorDescriptor.extensions`.

Semantic-key rejection is based on exact normalized token matches.

It MUST NOT use substring matching. Therefore safe owner-approved derivation channels remain possible:

```text
token         -> forbidden
tokenHash     -> allowed safe-derivation channel
tokenLength   -> allowed safe-derivation channel

rawSql        -> forbidden
rawSqlHash    -> allowed safe-derivation channel
```

An allowed key does not make an arbitrary raw value safe.

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

## Extensions resource bounds

`ErrorDescriptor.extensions` MUST be bounded during the same recursive traversal used for validation and deterministic normalization.

The mandatory canonical limits are:

```text
maximum container depth                  8
maximum total map-value/list-item nodes  256
maximum bytes per map key/string value   4096
maximum aggregate map-key/string bytes   65536
```

The root `extensions` map has container depth `1`.

Every map entry consumes one node.

Every list item consumes one node.

Container nodes are bounded through their parent entry or list item and through the mandatory depth limit.

Individual string and aggregate string-byte budgets apply to both:

- map keys;
- string values.

PHP array identity is not part of the `ErrorDescriptor` contract.

Implementations are not required to identify a recursive PHP array by object-style identity. Instead, mandatory depth and node budgets MUST stop recursive or cyclic traversal before it can become unbounded.

A recursive array MUST result in controlled `InvalidArgumentException` behavior, not process-level memory exhaustion.

Any extension resource-budget violation MUST fail closed with:

```text
Invalid error descriptor extensions.
```

Budget failure diagnostics MUST NOT expose:

- the rejected value;
- the rejected map key;
- a raw path through the payload;
- byte contents;
- secret material.

`ErrorDescriptor` MUST NOT:

- truncate strings;
- drop map entries;
- drop list items;
- reduce nesting;
- partially normalize an oversized payload;
- silently replace excessive data with placeholders.

Resource-budget limits are fixed contracts-level invariants. They MUST NOT be configurable or disabled by runtime configuration.

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

Absolute local-path strings MUST be rejected recursively regardless of the extension key under which they appear.

The path policy is platform-independent and covers at minimum:

- POSIX-rooted paths;
- Windows drive-rooted paths;
- Windows rooted and UNC paths;
- `file://` representations of absolute local paths.

`ErrorDescriptor` MUST NOT silently rewrite, trim, redact, mask, hash, or replace an unsafe extension value.

### Producer responsibility

`ErrorDescriptor` is a fail-closed contract boundary, not a secret scanner, SQL parser, PII detector, or taint-analysis engine.

Producers MUST derive safe metadata before constructing `ErrorDescriptor`.

Producers MUST NOT fully materialize potentially unbounded source data merely to pass it as candidate `ErrorDescriptor.extensions`.

When extension metadata is derived from external payloads, plugin output, callback-provided structures, decoded transport data, or another potentially unbounded source, the owning producer MUST apply its source/input bounds before or during materialization.

The `ErrorDescriptor` resource budget bounds descriptor validation and normalization work. It does not retroactively bound memory already allocated by a producer before descriptor construction.

An allowed extension key does not authorize copying arbitrary raw producer values.

Raw values MUST NOT be passed to `ErrorDescriptor` merely so that `ErrorDescriptor` can hash, truncate, redact, mask, or otherwise sanitize them.

The canonical flow is:

```text
raw producer value
-> producer-owned safe derivation
-> hash / length / count / stable category
-> ErrorDescriptor
```

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
    'schemaVersion' => 1,
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
    'schemaVersion' => 1,
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
    'schemaVersion' => 1,
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
framework/packages/core/contracts/tests/Contract/ContractsDoNotReferencePsr7ContractTest.php
framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsAreJsonLikeContractTest.php
framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsAreBoundedContractTest.php
framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsEnforceRedactionContractTest.php
framework/packages/core/contracts/tests/Contract/ErrorDescriptorFieldSetIsStableContractTest.php
framework/packages/core/contracts/tests/Contract/ErrorDescriptorHttpStatusIsOptionalContractTest.php
framework/packages/core/contracts/tests/Contract/ErrorDescriptorShapeContractTest.php
```

The extension json-like and float-forbidden policy is enforced by:

```text
framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsAreJsonLikeContractTest.php
```

The extension depth, node-count, individual-string, aggregate-string, recursive-array, and budget-diagnostic requirements are enforced by:

```text
framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsAreBoundedContractTest.php
```

The semantic extension-key, absolute-path, safe-derivation, recursive-policy, and fail-safe diagnostic requirements are enforced by:

```text
framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsEnforceRedactionContractTest.php
```

The exported field set and deterministic top-level key order are enforced by:

```text
framework/packages/core/contracts/tests/Contract/ErrorDescriptorFieldSetIsStableContractTest.php
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
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [DTO Policy](./dto-policy.md)
- [SSoT Index](./INDEX.md)
