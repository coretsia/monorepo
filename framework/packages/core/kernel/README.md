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

# coretsia/core-kernel

`core/kernel` is the **Kernel runtime** package for the Coretsia Framework monorepo.

**Scope:** Kernel module metadata, Kernel service provider wiring, Kernel-owned format-neutral UnitOfWork context/result shapes, UnitOfWork type and outcome vocabularies, UoW-specific json-like shape policy through a Foundation-backed internal wrapper, and canonical UnitOfWork outcome/lifecycle policy.

**Out of scope:** reusable baseline json-like runtime value model ownership, generic redaction engine, HTTP response construction, HTTP status-code selection, PSR-7/PSR-15 integration, CLI command execution, CLI output rendering, platform adapters, integrations, observability exporters, concrete lifecycle executor implementation, reset discovery implementation, and tooling-only behavior.

## Package identity

- **Path:** `framework/packages/core/kernel`
- **Package id:** `core/kernel`
- **Composer name:** `coretsia/core-kernel`
- **Module id:** `core.kernel`
- **Namespace:** `Coretsia\Kernel\*` (PSR-4: `src/`)
- **Kind:** runtime
- **Config root:** `kernel`

Monorepo versioning is **repo-wide only** via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions **MUST NOT** be used.

## Dependency policy

This package is runtime-safe and format-neutral.

- **Depends on:**
  - `core/contracts`
  - `core/foundation`
- **Forbidden:**
  - `platform/*`
  - `integrations/*`
  - `Psr\Http\Message\*`
  - `Psr\Http\Server\*`

`core/kernel` MUST NOT depend on transport implementation packages.

Kernel UnitOfWork shapes MUST remain format-neutral and MUST NOT expose transport objects.

Kernel UoW shape normalization consumes the Foundation-owned baseline json-like normalizer:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

through the Kernel-owned internal wrapper:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

Kernel MUST NOT duplicate the baseline recursive json-like walker.

## Runtime responsibilities

This package provides the Kernel baseline runtime layer:

- Kernel module metadata through `Coretsia\Kernel\Module\KernelModule`.
- Kernel service provider registration through `Coretsia\Kernel\Provider\KernelServiceProvider`.
- Stateless Kernel service factory/wiring helper through `Coretsia\Kernel\Provider\KernelServiceFactory`.
- Kernel configuration defaults and validation rules under the `kernel` config root.
- Canonical UnitOfWork type tokens:
  - `http`
  - `cli`
  - `queue`
  - `scheduler`
- Canonical UnitOfWork outcome tokens:
  - `success`
  - `handled_error`
  - `fatal_error`
- Canonical UnitOfWork context shape:
  - `Coretsia\Kernel\Runtime\UnitOfWorkContext`
- Canonical UnitOfWork result shape:
  - `Coretsia\Kernel\Runtime\UnitOfWorkResult`
- Internal UoW-specific json-like shape wrapper through:
  - `Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer`
- Foundation-owned baseline json-like value validation and recursive deterministic normalization consumed through:
  - `Coretsia\Foundation\Serialization\JsonLikeNormalizer`
- Deterministic validation failures for UnitOfWork shapes:
  - `CORETSIA_UOW_CONTEXT_INVALID`
  - `CORETSIA_UOW_RESULT_INVALID`

Foundation owns reusable runtime mechanisms such as ids, clocks, stopwatch, context storage, deterministic tags, and reset orchestration.

Foundation also owns the reusable baseline json-like runtime value model.

Kernel owns only the UoW-specific layer on top of that model: root map policy, unsafe metadata key policy, attributes limits, exported error map policy, and UoW exception mapping.

Platform packages own transport adapters.

## Configuration

The package owns the `kernel` configuration root.

Defaults live in:

```text
framework/packages/core/kernel/config/kernel.php
```

The defaults file MUST return the subtree only and MUST NOT repeat the root key.

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
            'attributes' => [],
        ],
    ],
];
```

Runtime code reads from the global configuration under `kernel.*`.

Canonical Kernel config keys:

| key                               | default |
|-----------------------------------|---------|
| `kernel.uow.attributes.max_depth` | `10`    |
| `kernel.uow.attributes.max_keys`  | `200`   |

This package does not introduce generic json-like configuration.

The following config keys MUST NOT be introduced by Kernel:

```text
kernel.json_like.*
foundation.json_like.*
foundation.serialization.json_like.*
```

Baseline json-like runtime value policy is owned by Foundation and is not configurable by Kernel.

Both values MUST be integers greater than zero.

This package does not introduce outcome mapping configuration.

Outcome mapping is canonical policy, not runtime configuration.

## UnitOfWork types

The canonical UnitOfWork type values are:

```text
http
cli
queue
scheduler
```

The implementation is:

```text
Coretsia\Kernel\Runtime\UnitOfWorkType
```

The class is intentionally enum-like instead of a native PHP enum because exported UnitOfWork shapes carry plain strings and no object instance may cross Kernel hook/export boundaries.

The tokens are stable lowercase ASCII values and MUST be compared byte-for-byte.

## Outcomes

The canonical UnitOfWork outcome values are:

```text
success
handled_error
fatal_error
```

The implementation is:

```text
Coretsia\Kernel\Runtime\Outcome
```

The class is intentionally enum-like instead of a native PHP enum because exported UnitOfWork shapes carry plain strings and no object instance may cross Kernel hook/export boundaries.

The tokens are stable lowercase ASCII values and MUST be compared byte-for-byte.

HTTP and CLI outcome mapping is governed by:

```text
docs/ssot/uow-outcome-policy.md
```

## UnitOfWorkContext

`Coretsia\Kernel\Runtime\UnitOfWorkContext` is the canonical Kernel runtime shape for the beginning of a UnitOfWork.

Canonical fields:

```text
uowId
type
startedAt
correlationId
attributes
```

`attributes` MUST be a json-like map.

The baseline json-like runtime value model is owned by:

```text
docs/ssot/json-like-runtime-values.md
```

The baseline normalizer is:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

Kernel owns the UoW-specific `attributes` root map policy.

A non-empty root list MUST NOT be used as `attributes`.

`attributes` are normalized by the Kernel-owned internal wrapper:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer::normalizeContextAttributes()
```

The wrapper delegates baseline recursive normalization to Foundation and preserves Kernel-owned policy:

```text
attributes root map policy
unsafe metadata key policy
attributes max_depth
attributes max_keys
UoW context exception mapping
```

Validation failures MUST use:

```text
Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException
CORETSIA_UOW_CONTEXT_INVALID
```

## UnitOfWorkResult

`Coretsia\Kernel\Runtime\UnitOfWorkResult` is the canonical Kernel runtime shape for the completion of a UnitOfWork.

Canonical fields:

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

`durationMs` is the canonical duration field.

It MUST be:

```text
int
>= 0
```

`durationMs` MUST be measured from the canonical monotonic timing source:

```text
Coretsia\Foundation\Time\Stopwatch
```

`durationMs` MUST NOT be calculated from:

```text
finishedAt - startedAt
```

`extensions` MUST be a json-like map.

The baseline json-like runtime value model is owned by:

```text
docs/ssot/json-like-runtime-values.md
```

The baseline normalizer is:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

Kernel owns the UoW-specific `extensions` root map policy.

A non-empty root list MUST NOT be used as `extensions`.

`extensions` are normalized by the Kernel-owned internal wrapper:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer::normalizeResultExtensions()
```

The wrapper delegates baseline recursive normalization to Foundation and preserves Kernel-owned policy:

```text
extensions root map policy
unsafe metadata key policy
UoW result exception mapping
```

Validation failures MUST use:

```text
Coretsia\Kernel\Runtime\Exception\UnitOfWorkResultInvalidException
CORETSIA_UOW_RESULT_INVALID
```

## ErrorDescriptor boundary

`UnitOfWorkResult.error` MAY be represented internally as:

```text
Coretsia\Contracts\Observability\Errors\ErrorDescriptor
```

Before crossing any Kernel hook/export boundary, the error MUST be normalized to a json-like exported error map.

The exported error map is normalized by:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer::normalizeExportedErrorMap()
```

The wrapper delegates baseline recursive normalization to:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

Kernel owns the exported `error` map policy, including the non-empty root map requirement and UoW result exception mapping.

No `ErrorDescriptor` object instance MAY cross the hook/export boundary.

No Throwable object MAY cross the hook/export boundary.

Invalid exported error maps MUST fail with:

```text
CORETSIA_UOW_RESULT_INVALID
```

## Json-like shape normalization

Kernel centralizes UoW-specific json-like shape policy in:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

This class is internal and is not public API.

It is not a DI service.

It is not a transport extension point.

It MUST NOT be exposed through:

```text
framework/packages/core/kernel/PUBLIC_API.md
```

It provides:

```text
normalizeContextAttributes(array $attributes, int $maxDepth, int $maxKeys): array
normalizeResultExtensions(array $extensions): array
normalizeExportedErrorMap(array $error): array
```

Baseline json-like value validation and recursive deterministic normalization are owned by Foundation:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

Baseline failures are represented by:

```text
Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException
```

`JsonLikeShapeNormalizer` MUST delegate baseline recursive normalization to Foundation.

`JsonLikeShapeNormalizer` owns only UoW-specific policy:

- root map validation for `attributes`;
- root map validation for `extensions`;
- non-empty root map validation for exported `error`;
- context `max_depth`;
- context `max_keys`;
- deterministic unsafe-key rejection for known unsafe metadata keys;
- safe string and safe single-line string checks;
- UoW-specific exception reason mapping;
- UoW-safe diagnostics without raw values.

`JsonLikeShapeNormalizer` MUST NOT duplicate the baseline recursive json-like walker.

Foundation owns:

- scalar acceptance;
- float, `NAN`, `INF`, and `-INF` rejection;
- object, closure, resource, and unsupported type rejection;
- string-keyed maps only;
- list order preservation;
- recursive map sorting by byte-order `strcmp`;
- baseline safe path diagnostics.

## Hook and export boundary

Kernel hooks and adapters receive exported array shapes, not shape objects.

Before hooks receive context data as:

```text
UnitOfWorkContext -> normalized array $ctx
```

After hooks receive result data as:

```text
UnitOfWorkResult -> normalized array $result
```

Nested `attributes`, `extensions`, and exported `error` maps MUST be baseline-normalized through `JsonLikeShapeNormalizer`, which delegates recursive normalization to Foundation.

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

## Reset lifecycle boundary

Foundation owns reset discovery and reset orchestration mechanics.

The canonical Foundation reset executor is:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

Kernel owns the future lifecycle trigger point.

The canonical conceptual lifecycle is:

```text
beginUow()
before_uow hooks
run external runtime (http/cli/queue/scheduler/...)
after_uow hooks
ResetOrchestrator.resetAll()
endUoW()
```

Once the after-phase is entered, `ResetOrchestrator.resetAll()` MUST run exactly once before `endUoW()`.

This applies even if an after-uow hook throws.

This package MUST NOT enumerate reset-tagged services directly.

Reset discovery remains Foundation-owned.

## Observability

This package does not emit telemetry directly.

It defines safe data shapes and stable vocabulary that future runtime owners may use for observability.

Potential safe observability values:

```text
type
outcome
durationMs
```

Metric labels MUST NOT include:

```text
uowId
correlationId
request_id
user_id
tenant_id
path
field
property
```

`durationMs` MAY be emitted as a metric value or span/log field according to owner policy.

`durationMs` MUST NOT be emitted as a metric label.

`attributes` and `extensions` MUST NOT be copied wholesale into logs, spans, metrics, diagnostics, or artifacts.

A value accepted by the baseline json-like model is not automatically safe for observability emission.

Kernel and platform owners remain responsible for target-boundary redaction and omission policy.

## Errors

This package defines Kernel UnitOfWork validation exceptions:

- `Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException`
  - canonical error code: `CORETSIA_UOW_CONTEXT_INVALID`
- `Coretsia\Kernel\Runtime\Exception\UnitOfWorkResultInvalidException`
  - canonical error code: `CORETSIA_UOW_RESULT_INVALID`

Baseline json-like failures from:

```text
Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException
```

are mapped locally by:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

to UoW-specific validation exceptions and reason tokens.

Exception messages MUST be deterministic and safe.

Exception messages MAY include only:

```text
safe path-to-value
stable reason code
safe type name
```

Exception messages MUST NOT include:

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

Higher-level error reporting, rendering, and transport mapping are owned by higher layers.

## Security / Redaction

`core/kernel` MUST NOT leak sensitive runtime data through UnitOfWork shapes.

Forbidden in `UnitOfWorkContext.attributes` and `UnitOfWorkResult.extensions`:

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
- direct user identifiers;
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

Runtime owners MUST prefer omission over unsafe emission.

The Kernel internal json-like shape wrapper includes deterministic unsafe-key guards for known unsafe metadata keys.

This guard is Kernel-owned UoW-specific policy.

It is not a PII detector.

It is a deterministic denylist for obvious policy-key names.

Foundation `JsonLikeNormalizer` does not own the unsafe metadata key denylist.

## Public API

Package-local public API evidence is maintained in:

```text
framework/packages/core/kernel/PUBLIC_API.md
```

That file is the source used by the Kernel public API gate.

`Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer` is internal implementation detail.

It MUST NOT be listed as Kernel public API.

Kernel public API consumers MUST use `UnitOfWorkContext` and `UnitOfWorkResult`, not the internal normalizer.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Kernel package source](https://github.com/coretsia/monorepo/tree/main/framework/packages/core/kernel)
- [Packaging strategy](https://github.com/coretsia/monorepo/blob/main/docs/architecture/PACKAGING.md)
- [Json-like Runtime Values SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/json-like-runtime-values.md)
- [UnitOfWork Shapes SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/uow-shapes.md)
- [UnitOfWork Outcome Policy SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/uow-outcome-policy.md)
