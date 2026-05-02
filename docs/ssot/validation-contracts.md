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

# Validation Contracts SSoT

## Scope

This document is the Single Source of Truth for Coretsia validation contracts, validation result shapes, violation descriptor shapes, validation exception semantics, deterministic validation error mapping policy, and validation redaction rules.

This document governs contracts introduced by epic `1.130.0` under:

```text
framework/packages/core/contracts/src/Validation/
```

It complements:

```text
docs/ssot/tags.md
docs/ssot/error-descriptor.md
docs/ssot/errors-boundary.md
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/dto-policy.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Validation must be usable across HTTP, CLI, worker, scheduler, queue consumer, and custom runtime boundaries without coupling validation contracts to transport-specific APIs.

The contracts introduced by this epic define only:

- a format-neutral validation port;
- a deterministic validation result shape;
- a safe validation violation descriptor shape;
- a deterministic validation exception code for runtime error mapping;
- policy linkage to the existing reserved `error.mapper` DI tag.

The contracts package MUST NOT implement validator logic, validation rule execution, exception mapper implementation, mapper discovery, DI registration, HTTP middleware, request parsing, response rendering, config defaults, config rules, providers, or generated artifacts.

## Phase 0 lock-source alignment

This SSoT preserves the following Phase 0 invariants:

- `0.20.0` no-secrets output policy applies to validation diagnostics.
- `0.60.0` presence-sensitive behavior MUST NOT collapse distinct states when future owners validate optional, missing, empty, or null values.
- `0.70.0` json-like payloads MUST forbid floats, including `NaN`, `INF`, and `-INF`.
- `0.70.0` lists preserve order and maps use deterministic key ordering.
- `0.90.0` safe diagnostics MUST NOT expose raw values and MAY expose only safe derivations such as `hash(value)` or `len(value)`.

Epic `1.130.0` itself introduces no validator implementation, no validation rule language, no generated artifact, and no DI tag.

## Contract boundary

Validation contracts are format-neutral.

They define stable ports, result shapes, violation descriptor shapes, exception semantics, and redaction invariants only.

The contracts package MUST NOT implement:

- validation rule parsing;
- validation rule execution;
- validation rule discovery;
- request validation middleware;
- form validation;
- schema validation engines;
- object graph validators;
- attribute scanners;
- reflection-based validation engines;
- exception mapper registries;
- concrete exception mappers;
- HTTP problem-details rendering;
- CLI rendering;
- worker failure rendering;
- DI registration;
- runtime discovery;
- config defaults;
- config rules;
- generated validation artifacts.

Runtime owner packages implement concrete validation behavior later.

## Contract package dependency policy

Validation contracts MUST remain dependency-free beyond PHP itself and other contracts-level types already owned by `core/contracts`.

They MUST NOT depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- framework HTTP runtime packages
- framework CLI runtime packages
- worker runtime packages
- queue vendor clients
- scheduler vendor clients
- concrete service container implementations
- concrete middleware implementations
- concrete validator implementations
- concrete problem-details renderers
- concrete logger implementations
- concrete tracing implementations
- concrete metrics implementations
- vendor-specific runtime clients
- framework tooling packages
- generated architecture artifacts

Runtime packages MAY depend on `core/contracts`.

`core/contracts` MUST NOT depend back on runtime packages.

Allowed direction:

```text
platform/validation → core/contracts
platform/errors → core/contracts
platform/problem-details → core/contracts
platform/http → core/contracts
platform/cli → core/contracts
worker runtime package → core/contracts
scheduler runtime package → core/contracts
queue runtime package → core/contracts
```

Forbidden direction:

```text
core/contracts → platform/validation
core/contracts → platform/errors
core/contracts → platform/problem-details
core/contracts → platform/http
core/contracts → platform/cli
core/contracts → worker runtime package
core/contracts → scheduler runtime package
core/contracts → queue runtime package
```

## DTO terminology boundary

This document uses the terms `contract`, `port`, `descriptor`, `result`, and `shape` according to:

```text
docs/ssot/dto-policy.md
```

`ValidationResult` is a contracts result shape.

`Violation` is a contracts violation descriptor shape.

They are not DTO-marker classes by default.

DTO gates apply only to classes explicitly marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Validation classes introduced by epic `1.130.0` MUST NOT be treated as DTOs unless a future owner epic explicitly opts them into DTO policy.

## Json-like value model

Any json-like payload exposed by validation contracts MUST contain only:

- `null`
- `bool`
- `int`
- `string`
- list of allowed values
- map with string keys and allowed values

Floating-point values are forbidden.

The following values MUST NOT appear in exported json-like validation contract shapes:

- floats
- `NaN`
- `INF`
- `-INF`
- PHP objects
- closures
- resources
- streams
- filesystem handles
- service instances
- runtime wiring objects
- executable validators
- throwable instances
- request objects
- response objects
- PSR-7 objects
- vendor SDK objects

If a future owner needs decimal values, they MUST be represented as strings with a documented format.

## Json-like container determinism

Lists preserve order.

Maps MUST be ordered deterministically by string key using byte-order `strcmp`.

Map ordering MUST be locale-independent.

Implementations that expose json-like maps SHOULD normalize map ordering recursively before export, serialization, rendering, or diagnostics.

An empty array is context-dependent:

- if the contract location requires a list, `[]` is treated as an empty list;
- if the contract location requires a map, `[]` is treated as an empty map at the semantic boundary;
- serialized PHP array representations may not preserve empty list vs empty map distinction.

Contracts MUST document the expected context for any ambiguous empty array field.

## Safe string policy

Validation contract fields intended for diagnostics MUST be safe strings.

Safe diagnostic strings MUST NOT contain:

- NUL bytes;
- CR or LF;
- unsafe ASCII control characters;
- raw request bodies;
- raw response bodies;
- raw queue payloads;
- raw SQL;
- credentials;
- tokens;
- cookies;
- authorization headers;
- session identifiers;
- private customer data;
- absolute local paths.

Validation contract fields SHOULD use stable ASCII-compatible identifiers when they are machine-readable codes, rule names, or paths.

## Validator port

`ValidatorInterface` is the canonical contracts-level validation boundary.

The implementation path is:

```text
framework/packages/core/contracts/src/Validation/ValidatorInterface.php
```

The canonical interface shape is:

```text
validate(mixed $input, array $rules = [], array $context = []): ValidationResult
```

`validate()` validates an implementation-owned input value against implementation-owned validation rules and safe validation context.

The `$input` argument is runtime input.

It MAY be any value required by a concrete runtime owner.

The contracts package does not constrain `$input` to a transport format, object model, array shape, request body shape, or schema language.

The `$input` argument MUST remain ephemeral at the contracts boundary.

Validator implementations MUST NOT copy raw input values into:

- `ValidationResult`;
- `Violation`;
- `ValidationException`;
- `ErrorDescriptor`;
- logs;
- spans;
- metrics;
- health output;
- CLI output;
- worker failure output;
- unsafe diagnostics.

The `$rules` argument is implementation-owned rule data.

The contracts package does not define a validation rule language.

When `$rules` is represented as array data, it SHOULD be json-like and safe.

The `$rules` argument MUST NOT require executable validators, closures, service instances, container references, request objects, response objects, PSR-7 objects, vendor SDK objects, or runtime wiring objects at the contracts boundary.

The `$context` argument is optional safe validation context metadata.

The context MUST NOT contain raw request data, raw headers, raw cookies, raw bodies, credentials, tokens, raw SQL, queue vendor messages, worker vendor contexts, service container objects, or private customer data.

The validation port MUST NOT require:

- PSR-7 request objects;
- PSR-7 response objects;
- framework HTTP request objects;
- framework HTTP response objects;
- CLI concrete input/output objects;
- queue vendor message objects;
- worker vendor context objects;
- scheduler vendor context objects;
- concrete service container objects;
- platform classes;
- integration classes.

Concrete validation behavior belongs to runtime owner packages.

## Validation result

`ValidationResult` is the canonical contracts-level validation result shape.

The implementation path is:

```text
framework/packages/core/contracts/src/Validation/ValidationResult.php
```

`ValidationResult` is an immutable contracts result shape.

It is not a DTO-marker class by default.

The canonical result schema version is:

```text
1
```

### ValidationResult logical fields

The canonical logical fields are:

```text
schemaVersion
success
violations
```

Field meanings:

| field           | meaning                                                 |
|-----------------|---------------------------------------------------------|
| `schemaVersion` | Stable validation result schema version.                |
| `success`       | Whether validation completed without violations.        |
| `violations`    | Ordered list of validation violation descriptor shapes. |

### ValidationResult field rules

`schemaVersion` MUST be a positive integer.

`success` MUST be a boolean.

A successful result MUST contain an empty violations list.

A failed result MUST contain at least one violation.

`violations` MUST be a list of `Violation` instances at the PHP boundary.

The exported `violations` value MUST be a list of deterministic `Violation::toArray()` shapes.

`ValidationResult` MUST NOT contain raw input payload values.

`ValidationResult` MUST NOT contain request objects, response objects, PSR-7 objects, service instances, closures, resources, executable validators, or runtime wiring objects.

### ValidationResult accessor shape

The canonical accessor shape is:

```text
schemaVersion(): int
success(): self
failure(array $violations): self
isSuccess(): bool
isFailure(): bool
violations(): array
toArray(): array<string,mixed>
```

`success()` returns a successful validation result with no violations.

`failure()` returns a failed validation result and MUST reject an empty violation list.

`violations()` returns the ordered list of `Violation` instances.

### ValidationResult violation ordering

`ValidationResult` exposes violations as an ordered list.

The concrete stable order of violations is runtime-owned.

The result shape MUST preserve the ordered violation list supplied by the validation owner unless a future SSoT explicitly moves sorting responsibility into `ValidationResult`.

Runtime validation owners MUST produce deterministic violation order.

Recommended runtime ordering is:

```text
path ascending using byte-order strcmp
code ascending using byte-order strcmp
rule ascending using byte-order strcmp, with null treated as empty string
index ascending as integer, with null sorted before any integer
```

The `Violation` shape supports these deterministic sort keys directly.

### ValidationResult exported order

When exported as a PHP array shape, `ValidationResult` SHOULD use deterministic top-level key ordering by byte-order `strcmp`:

```text
schemaVersion
success
violations
```

Contract tests cement this order as part of the validation result shape contract.

## Validation violation

`Violation` is the canonical contracts-level validation violation descriptor.

The implementation path is:

```text
framework/packages/core/contracts/src/Validation/Violation.php
```

`Violation` is an immutable safe descriptor shape.

It is not a DTO-marker class by default.

The canonical violation schema version is:

```text
1
```

A violation MUST expose structural validation diagnostics only.

A violation MUST NOT contain the raw invalid value.

### Violation logical fields

The canonical logical fields are:

```text
schemaVersion
path
code
rule
index
message
meta
```

Field meanings:

| field           | meaning                                                           |
|-----------------|-------------------------------------------------------------------|
| `schemaVersion` | Stable violation schema version.                                  |
| `path`          | Safe logical validation path.                                     |
| `code`          | Stable machine-readable validation violation code.                |
| `rule`          | Optional stable rule identifier.                                  |
| `index`         | Optional deterministic owner-provided ordinal or sort tiebreaker. |
| `message`       | Optional safe human-readable message.                             |
| `meta`          | Safe json-like metadata map.                                      |

`meta` is the canonical metadata field name for validation violations.

This epic MUST NOT introduce a parallel `extensions` field on `Violation`.

Documentation may use the word “extensions” descriptively, but the canonical contract field is:

```text
meta
```

### Violation sort-key support

The violation shape MUST support deterministic ordering by exposing these stable sort keys:

```text
path
code
rule
index
```

Runtime owners MAY sort violations by these fields.

The contracts package does not define validation execution order.

The contracts package does not define a global rule priority schema.

### Violation field rules

`schemaVersion` MUST be a positive integer.

`path` MUST be a safe logical path string.

`path` MAY be an empty string to represent the root input.

`path` MUST NOT be an absolute filesystem path.

`path` MUST NOT contain raw input values.

`path` SHOULD use a stable owner-defined notation such as:

```text
email
profile.name
items[].sku
addresses[0].postalCode
```

`code` MUST be a non-empty stable machine-readable validation code.

`code` MUST NOT contain raw input values.

`code` SHOULD be ASCII-compatible and stable across runtime boundaries.

Recommended code shape:

```text
^[A-Z][A-Z0-9_]*$
```

Valid examples:

```text
VALIDATION_REQUIRED
VALIDATION_INVALID_TYPE
VALIDATION_TOO_SHORT
VALIDATION_INVALID_FORMAT
```

`rule` MAY be null.

When present, `rule` MUST be a safe stable rule identifier.

`rule` MUST NOT contain executable validator names that expose implementation internals when those names are unstable or sensitive.

`index` MAY be null.

When present, `index` MUST be a non-negative integer.

`index` is an owner-provided deterministic ordinal or tiebreaker.

`index` MUST NOT be random, timestamp-derived, process-derived, or host-derived.

`message` MAY be null.

When present, `message` MUST be safe human-readable text.

`message` MUST NOT contain raw input values, raw SQL, raw payloads, credentials, tokens, private customer data, absolute local paths, CR, or LF.

`meta` MUST be a json-like map.

The root `meta` value MUST be a map with string keys.

A non-empty list MUST NOT be used as the root `meta` value.

An empty array represents an empty metadata map at this contract boundary.

`meta` maps MUST be ordered deterministically by string key using byte-order `strcmp`.

Nested maps inside `meta` MUST be ordered recursively.

Lists inside `meta` MUST preserve order.

`meta` MUST be safe-only.

### Violation forbidden data

A violation MUST NOT expose:

- raw input values;
- raw request data;
- raw response data;
- raw queue messages;
- raw worker payloads;
- raw SQL;
- headers;
- cookies;
- authorization headers;
- session identifiers;
- credentials;
- tokens;
- private keys;
- private customer data;
- absolute local paths;
- request objects;
- response objects;
- PSR-7 objects;
- queue vendor message objects;
- worker vendor context objects;
- service container objects;
- service instances;
- executable validators;
- closures;
- resources.

Safe metadata MAY expose only structural or derived information such as:

```text
expectedType
actualType
minLength
maxLength
minItems
maxItems
allowedFormat
hash(value)
len(value)
```

Safe derivations MUST NOT expose raw values or allow reconstruction of sensitive values.

### Violation accessor shape

The canonical accessor shape is:

```text
schemaVersion(): int
path(): string
code(): string
rule(): ?string
index(): ?int
message(): ?string
meta(): array<string,mixed>
toArray(): array<string,mixed>
```

### Violation exported order

When exported as a PHP array shape, `Violation` SHOULD use deterministic top-level key ordering by byte-order `strcmp`.

When all optional fields are present, the canonical exported key order is:

```text
code
index
message
meta
path
rule
schemaVersion
```

Optional fields with null values SHOULD be omitted from the exported shape.

When optional fields are omitted, the remaining exported key order MUST stay deterministic.

Contract tests cement this order as part of the violation shape contract.

## Validation exception

`ValidationException` is the canonical contracts-level exception for failed validation.

The implementation path is:

```text
framework/packages/core/contracts/src/Validation/ValidationException.php
```

`ValidationException` MUST use this deterministic validation error code:

```text
CORETSIA_VALIDATION_FAILED
```

The deterministic validation error code MUST be exposed as:

```text
ValidationException::CODE
```

The exception SHOULD expose this accessor:

```text
errorCode(): string
```

`errorCode()` MUST return:

```text
CORETSIA_VALIDATION_FAILED
```

The PHP native exception integer code returned by `Throwable::getCode()` is not the canonical validation error code.

The canonical validation error code is the stable string code defined by `ValidationException::CODE`.

This avoids coupling the contracts-level machine code to PHP native integer exception code semantics.

`ValidationException` SHOULD extend `RuntimeException`.

`ValidationException` MUST carry a `ValidationResult`.

The validation result carried by `ValidationException` MUST be a failed result.

A `ValidationException` MUST NOT be constructed with a successful validation result.

The exception message MUST be safe and generic.

Recommended canonical message:

```text
Validation failed.
```

The exception message MUST NOT contain raw input values, raw request data, raw queue messages, raw SQL, credentials, tokens, private customer data, or absolute local paths.

`ValidationException` MUST NOT create, own, or require `ErrorDescriptor`.

Mapping to `ErrorDescriptor` is runtime-owned.

## Error mapping policy

Runtime validation error mapping is owned by runtime/platform packages.

The runtime expectation for this epic is:

```text
ValidationException → ErrorDescriptor
```

The expected normalized HTTP status hint is:

```text
422
```

This status is a transport hint only.

Non-HTTP runtimes MAY ignore the HTTP status hint.

A future `platform/validation` or `platform/errors` owner implementation is expected to provide a mapper for `ValidationException`.

Mapper discovery is runtime policy owned by `platform/errors` through the existing reserved tag:

```text
error.mapper
```

The contracts package MAY reference `error.mapper` in documentation as runtime policy.

The contracts package MUST NOT declare `error.mapper` as a public constant.

The contracts package MUST NOT own mapper registry semantics.

The contracts package MUST NOT define competing metadata keys or competing semantics for `error.mapper`.

A runtime mapper SHOULD map `ValidationException` to an `ErrorDescriptor` using:

```text
code: CORETSIA_VALIDATION_FAILED
message: Validation failed.
httpStatus: 422
```

Mapper-owned `ErrorDescriptor.extensions` MAY include safe validation metadata such as violation count or safe exported violations.

Mapper-owned extensions MUST NOT include raw input values.

Mapper-owned extensions MUST obey the `ErrorDescriptor.extensions` json-like and redaction policy.

The contracts package does not define the concrete mapper class, mapper ordering, fallback policy, or problem-details representation.

## DI tag policy

Epic `1.130.0` introduces no DI tags.

The relevant existing reserved tag is:

| tag            | owner package_id  | validation usage                                     |
|----------------|-------------------|------------------------------------------------------|
| `error.mapper` | `platform/errors` | runtime discovery point for validation error mappers |

`core/contracts` is not the owner of `error.mapper`.

`core/contracts` MUST NOT define a public tag API for `error.mapper`.

`core/contracts` MUST NOT define a package-local mirror constant for `error.mapper`.

`core/contracts` MUST NOT define competing tag metadata keys.

`core/contracts` MUST NOT define competing mapper priority semantics.

If owner metadata schema is not cemented yet, contributor packages MUST treat metadata as:

```text
meta per owner schema
```

Raw literal tag strings are allowed in docs, tests, and fixtures for readability according to the tag registry.

Runtime code MUST follow the runtime usage rule from:

```text
docs/ssot/tags.md
```

## Config policy

Epic `1.130.0` introduces no config roots and no config keys.

The contracts package MUST NOT require package config files for validation contracts.

No files under package `config/` are introduced by this epic.

Future runtime owner packages MAY introduce validation config roots or config keys only through their own owner epics and the config roots registry process.

Validation rules for this epic are not package config rules.

Validation rule execution is implementation-owned and outside `core/contracts`.

## Artifact policy

Epic `1.130.0` introduces no artifacts.

The contracts package MUST NOT generate validation artifacts, rule artifacts, schema artifacts, error-mapping artifacts, problem-details artifacts, or runtime lifecycle artifacts.

A future runtime owner MAY introduce generated artifacts only through its own owner epic and the artifact registry process.

## Observability and diagnostics policy

Validation contracts do not define observability signals.

Runtime implementations MAY emit logs, spans, metrics, or profiling signals around validation only when those signals follow:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
```

Diagnostics MUST NOT expose:

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
- raw queue messages;
- raw worker payloads;
- raw SQL;
- profile payloads;
- private customer data;
- absolute local paths.

Safe implementation diagnostics MAY use:

```text
hash(value)
len(value)
type(value)
violationCount
```

Raw values MUST NOT be printed or logged.

Metric labels MUST remain within the canonical allowlist from:

```text
docs/ssot/observability.md
```

Epic `1.130.0` introduces no new metric label keys.

Validation paths, field names, property names, user ids, tenant ids, request ids, and correlation ids MUST NOT become metric labels under the baseline policy.

## Security and redaction

Validation contracts MUST NOT require storing secrets.

Validation violations MUST NOT leak raw invalid values.

Validation diagnostics MUST NOT expose raw unit-of-work data.

Runtime owners MUST prefer omission over unsafe emission.

Any safe derived diagnostic MUST be deterministic and non-reversible where applicable.

Validation implementations MUST NOT include secrets or PII in violation `meta` by default.

Validation implementations MUST NOT copy raw input payloads into exception messages, violation messages, violation metadata, error descriptor extensions, logs, spans, metrics, health output, CLI output, or worker failure output.

## Acceptance scenario

When an HTTP runtime, CLI command, worker job, scheduler tick, queue consumer, or custom runtime boundary validates input:

1. the runtime owner calls a `ValidatorInterface` implementation;
2. the validator returns a `ValidationResult`;
3. if validation succeeds, the result contains no violations;
4. if validation fails, the result contains an ordered list of safe `Violation` descriptors;
5. no violation contains raw input values;
6. a runtime owner MAY throw `ValidationException` with deterministic code `CORETSIA_VALIDATION_FAILED`;
7. a runtime mapper discovered through `error.mapper` maps `ValidationException` to `ErrorDescriptor` with HTTP status hint `422`;
8. transport adapters render the normalized error without requiring PSR-7 types inside `core/contracts`.

This acceptance scenario is policy intent.

The concrete validator implementation, rule engine, mapper implementation, problem-details rendering, CLI rendering, worker failure rendering, and diagnostics are runtime-owned.

## Verification evidence

Contracts-level enforcement evidence for this epic includes:

```text
framework/packages/core/contracts/tests/Contract/ValidationContractsTest.php
framework/packages/core/contracts/tests/Contract/ValidationExceptionHasDeterministicCodeTest.php
framework/packages/core/contracts/tests/Contract/ValidationViolationShapeIsSafeContractTest.php
```

These tests are expected to verify:

- `ValidatorInterface` exists and exposes the canonical format-neutral validation method;
- validation contracts do not depend on platform packages;
- validation contracts do not depend on integration packages;
- validation contracts do not depend on `Psr\Http\Message\*`;
- `ValidationResult` exposes an ordered violation list shape;
- `ValidationResult` carries no raw input payload values;
- `Violation` exposes safe scalar/json-like fields only;
- `Violation` exposes deterministic sort-key support through `path`, `code`, `rule`, and `index`;
- `Violation::meta()` is json-like, float-free, safe-only, and deterministic;
- `Violation` rejects floats, objects, closures, resources, and unsafe metadata;
- `ValidationException` exposes deterministic code `CORETSIA_VALIDATION_FAILED`;
- validation result and violation classes are contracts result/descriptor shapes, not DTO-marker classes by default.

Architecture gates are expected to verify that `core/contracts` does not introduce forbidden compile-time dependencies.

## Non-goals

This SSoT does not define:

- concrete validator implementation;
- concrete validation rule language;
- concrete schema language;
- validation attributes;
- reflection scanning;
- form validation implementation;
- request validation middleware;
- object graph validation implementation;
- validation rule discovery;
- validation rule config roots;
- validation config defaults;
- package validation rules;
- executable validators;
- validator DI registration;
- DI tag constants in `core/contracts`;
- error mapper implementation;
- mapper registry implementation;
- mapper priority algorithm;
- problem-details rendering;
- HTTP status rendering;
- CLI output rendering;
- worker failure rendering;
- generated validation artifacts;
- generated validation schema artifacts;
- localization;
- translation;
- persistence validation;
- database constraint validation;
- frontend validation schema generation.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Tag Registry](./tags.md)
- [ErrorDescriptor SSoT](./error-descriptor.md)
- [Errors Boundary SSoT](./errors-boundary.md)
- [Observability Naming and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [DTO Policy](./dto-policy.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
