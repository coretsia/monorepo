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

# ADR-0007: Validation ports

## Status

Accepted.

## Context

Epic `1.130.0` introduces stable validation contracts under:

```text
framework/packages/core/contracts/src/Validation/
```

Validation must be usable across HTTP, CLI, worker, scheduler, queue consumer, and custom runtime boundaries without coupling `core/contracts` to transport-specific APIs or runtime implementations.

The contracts package must remain a pure library boundary.

It must not depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- framework HTTP runtime packages
- framework CLI runtime packages
- worker runtime packages
- queue vendor clients
- scheduler vendor clients
- concrete validator implementations
- concrete exception mapper implementations
- concrete problem-details renderers
- concrete service container implementations
- framework tooling packages
- generated architecture artifacts

The detailed normative policy for this ADR is defined by:

```text
docs/ssot/validation-contracts.md
```

The existing tag registry already reserves the runtime discovery tag used for error mapping:

```text
docs/ssot/tags.md
```

Relevant existing tag:

```text
error.mapper
```

This tag is existing runtime policy owned by `platform/errors`, not a tag introduced by `core/contracts`.

The existing normalized error mapping target is:

```text
Coretsia\Contracts\Observability\Errors\ErrorDescriptor
```

`ErrorDescriptor` exists as a format-neutral contracts descriptor introduced by epic `1.90.0`.

## Decision

Coretsia will introduce validation contracts as format-neutral contracts in `core/contracts`.

The contracts introduced by epic `1.130.0` define:

- `ValidatorInterface`
- `ValidationResult`
- `Violation`
- `ValidationException`

The contracts package defines only stable ports, result/descriptor shapes, deterministic exception semantics, and boundary policy.

It does not implement:

- validator logic;
- validation rule execution;
- validation rule discovery;
- request validation middleware;
- form validation;
- object graph validation;
- schema validation engines;
- exception mapper implementation;
- mapper registry behavior;
- HTTP problem-details rendering;
- CLI rendering;
- worker failure rendering;
- DI discovery;
- runtime service registration;
- config defaults;
- config rules;
- generated artifacts.

## Validation port decision

`ValidatorInterface` is the canonical format-neutral validation port.

The canonical interface shape is:

```text
validate(mixed $input, array $rules = [], array $context = []): ValidationResult
```

The validation port intentionally does not require:

- PSR-7 request objects;
- PSR-7 response objects;
- framework HTTP request objects;
- framework HTTP response objects;
- CLI concrete input/output objects;
- queue vendor message objects;
- worker vendor context objects;
- scheduler vendor context objects;
- concrete service container objects.

The `$input` argument is runtime-owned input.

The contracts package does not define the input payload shape, request body shape, object graph shape, schema language, or rule language.

This keeps validation usable across HTTP, CLI, worker, scheduler, queue, and custom runtime boundaries.

Concrete validator implementation belongs to runtime owner packages such as future `platform/validation`.

## ValidationResult decision

`ValidationResult` is the canonical contracts-level validation result shape.

It is not a DTO-marker class by default.

It exposes:

```text
schemaVersion
success
violations
```

The canonical result schema version is:

```text
1
```

`ValidationResult` exposes violations as an ordered list.

The concrete stable order of violations is runtime-owned, but the result shape preserves the order supplied by the validation owner.

A successful result contains no violations.

A failed result contains at least one violation.

`ValidationResult` must not carry raw input payload values.

## Violation decision

`Violation` is the canonical contracts-level validation violation descriptor shape.

It is not a DTO-marker class by default.

A violation exposes structural diagnostics only.

It must not contain the raw invalid value.

The canonical violation fields are:

```text
schemaVersion
path
code
rule
index
message
meta
```

The shape supports deterministic ordering through these sort keys:

```text
path
code
rule
index
```

Runtime owners may sort violations deterministically by these fields.

The contracts package does not define validation execution order, rule priority, or validator implementation order.

`meta` is the canonical metadata field for validation violations.

This epic does not introduce a parallel `extensions` field on `Violation`.

Violation metadata must be json-like, float-free, deterministic, and safe-only.

Violation metadata must not contain secrets, PII, raw input values, raw request data, raw queue messages, raw SQL, credentials, tokens, private customer data, or absolute local paths.

## ValidationException decision

`ValidationException` is the canonical contracts-level exception for failed validation.

It must expose this deterministic error code:

```text
CORETSIA_VALIDATION_FAILED
```

The deterministic validation error code is a stable string code, not the PHP native integer exception code.

The canonical code must be exposed as:

```text
ValidationException::CODE
```

The exception should also expose:

```text
errorCode(): string
```

`ValidationException` carries a failed `ValidationResult`.

It must not be constructed with a successful validation result.

The exception message must remain safe and generic.

Recommended canonical message:

```text
Validation failed.
```

The exception must not expose raw input values, raw request data, raw queue messages, raw SQL, credentials, tokens, private customer data, or absolute local paths.

## Error mapping decision

Validation error mapping is runtime-owned.

The runtime expectation is:

```text
ValidationException → ErrorDescriptor
```

The normalized HTTP status hint is:

```text
422
```

This is a transport hint only.

Non-HTTP runtimes may ignore the HTTP status hint.

A runtime mapper discovered through the existing `error.mapper` tag may map `ValidationException` to `ErrorDescriptor` using:

```text
code: CORETSIA_VALIDATION_FAILED
message: Validation failed.
httpStatus: 422
```

This mapping is policy intent, not a `core/contracts` implementation.

The contracts package must not implement a mapper for `ValidationException`.

The contracts package must not implement mapper discovery or mapper registry behavior.

The contracts package must not require `ErrorDescriptor` construction inside `ValidationException`.

## DI tag decision

Epic `1.130.0` introduces no DI tags.

The relevant existing reserved tag is:

```text
error.mapper
```

That tag is owned by:

```text
platform/errors
```

The contracts package may reference `error.mapper` in documentation as runtime policy.

It must not declare public tag constants for `error.mapper`.

It must not define a package-local mirror constant for `error.mapper`.

It must not define competing tag metadata keys, mapper priority semantics, or mapper registry semantics.

## Config decision

Epic `1.130.0` introduces no config roots and no config keys.

The contracts package must not add package config defaults or config rules for validation contracts.

Validation rules for this epic are not package config rules.

Validation rule language and validation rule execution are implementation-owned.

Future runtime owner packages may introduce validation config only through their own owner epics and the config roots registry process.

## Artifact decision

Epic `1.130.0` introduces no artifacts.

The contracts package must not generate validation artifacts, rule artifacts, schema artifacts, error-mapping artifacts, problem-details artifacts, or runtime lifecycle artifacts.

Future runtime owners may introduce artifacts only through their own owner epics and the artifact registry process.

## Json-like payload decision

Validation metadata follows the Phase 0 json-like policy:

- allowed scalars are `string`, `int`, `bool`, and `null`;
- floats are forbidden, including `NaN`, `INF`, and `-INF`;
- lists preserve order;
- maps are ordered deterministically by byte-order `strcmp`;
- raw payload values must not be printed or logged;
- diagnostics may expose only safe derivations such as `hash(value)` or `len(value)`.

This applies to:

```text
Violation.meta
ValidatorInterface context metadata when represented as array data
mapper-owned safe validation metadata
```

## DTO boundary decision

`ValidationResult` and `Violation` are contracts result/descriptor shapes.

They are not DTO-marker classes by default.

DTO policy remains explicit opt-in only through:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Contract tests enforce validation shape policy directly.

DTO gates do not own these models unless a future epic explicitly opts them into DTO policy.

## Security and redaction decision

Validation contracts must not require storing secrets.

Validation results, violations, exception messages, mapper metadata, diagnostics, logs, spans, metrics, health output, CLI output, and worker failure output must not expose:

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
- environment-specific bytes.

Safe metadata may expose structural or derived information such as:

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
violationCount
```

Safe derivations must not expose raw values or allow reconstruction of sensitive values.

## Consequences

Positive consequences:

- Validation can be used by HTTP, CLI, worker, scheduler, queue, and custom runtimes through one contracts surface.
- `core/contracts` stays independent of PSR-7, platform packages, and integration packages.
- Validation failures get a deterministic machine-readable code.
- Runtime error mappers can map validation failures to normalized `ErrorDescriptor` output.
- HTTP status `422` remains adapter-owned policy, not a hard HTTP dependency in contracts.
- Validation violation shapes can support deterministic ordering without making contracts own validation execution.
- Validation diagnostics are safe-by-design and do not expose raw input values.

Trade-offs:

- Contracts do not provide a concrete validator implementation.
- Contracts do not define a validation rule language.
- Contracts do not provide request validation middleware.
- Contracts do not provide a `ValidationException` mapper implementation.
- Runtime owner packages must implement validation execution and mapping later.

## Rejected alternatives

### Put PSR-7 request/response objects in validation contracts

Rejected.

PSR-7 would make validation contracts HTTP-specific and would leak transport concerns into non-HTTP runtimes.

### Make ValidationException construct ErrorDescriptor directly

Rejected.

`ErrorDescriptor` mapping is runtime-owned.

`ValidationException` should expose deterministic validation failure semantics only.

Concrete mapping, mapper ordering, fallback policy, and transport adaptation belong to runtime owner packages.

### Put validator implementation into contracts

Rejected.

Validator implementation requires rule parsing, rule execution, runtime integration, optional reflection behavior, configuration policy, error mapping, and possibly transport adaptation.

Those responsibilities belong to runtime owner packages, not `core/contracts`.

### Store raw invalid values in Violation

Rejected.

Raw invalid values may contain secrets, PII, request bodies, raw queue payloads, credentials, tokens, or private customer data.

Validation violations expose structural diagnostics only.

### Introduce validation DI tags in contracts

Rejected.

This epic does not need a new validation discovery tag.

Error mapping uses the existing reserved `error.mapper` tag owned by `platform/errors`.

The contracts package is not the owner of that tag.

### Introduce validation config roots in contracts

Rejected.

Validation config ownership is runtime/platform policy.

This contracts epic introduces no config roots and no package config files.

### Generate validation schema artifacts from contracts

Rejected.

Generated validation artifacts require owner-defined schema semantics, source discovery, deterministic serialization, and runtime integration.

Those responsibilities belong to future owner packages.

## Non-goals

This ADR does not implement:

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

## Related SSoT

- `docs/ssot/validation-contracts.md`
- `docs/ssot/tags.md`
- `docs/ssot/error-descriptor.md`
- `docs/ssot/errors-boundary.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/dto-policy.md`

## Related epic

- `1.130.0 Contracts: Validation ports`
