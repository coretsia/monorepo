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

**Scope:** Kernel module metadata, Kernel service provider wiring, Kernel-owned format-neutral UnitOfWork context/result shapes, UnitOfWork type and outcome vocabularies, json-like shape normalization, and canonical UnitOfWork outcome/lifecycle policy.

**Out of scope:** HTTP response construction, HTTP status-code selection, PSR-7/PSR-15 integration, CLI command execution, CLI output rendering, platform adapters, integrations, observability exporters, concrete lifecycle executor implementation, reset discovery implementation, and tooling-only behavior.

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
- Internal json-like shape normalization through:
  - `Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer`
- Deterministic validation failures for UnitOfWork shapes:
  - `CORETSIA_UOW_CONTEXT_INVALID`
  - `CORETSIA_UOW_RESULT_INVALID`

Foundation owns reusable runtime mechanisms such as ids, clocks, stopwatch, context storage, deterministic tags, and reset orchestration.

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

Allowed values:

```text
null
bool
int
string
list<value>
array<string,value>
```

Forbidden values:

```text
float
NaN
INF
-INF
object
Closure
resource
non-string map key
```

`attributes` are normalized by:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer::normalizeContextAttributes()
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

Allowed values:

```text
null
bool
int
string
list<value>
array<string,value>
```

Forbidden values:

```text
float
NaN
INF
-INF
object
Closure
resource
non-string map key
```

`extensions` are normalized by:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer::normalizeResultExtensions()
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

No `ErrorDescriptor` object instance MAY cross the hook/export boundary.

No Throwable object MAY cross the hook/export boundary.

Invalid exported error maps MUST fail with:

```text
CORETSIA_UOW_RESULT_INVALID
```

## Json-like shape normalization

Kernel centralizes UnitOfWork json-like validation and deterministic normalization in:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

This class is internal and is not public API.

It provides:

```text
normalizeContextAttributes(array $attributes, int $maxDepth, int $maxKeys): array
normalizeResultExtensions(array $extensions): array
normalizeExportedErrorMap(array $error): array
```

It enforces:

- root map validation;
- recursive json-like validation;
- float, `NaN`, `INF`, and `-INF` rejection;
- object, closure, and resource rejection;
- string-keyed maps only;
- list order preservation;
- recursive map sorting by byte-order `strcmp`;
- safe path diagnostics without raw values;
- context `max_depth`;
- context `max_keys`;
- deterministic unsafe-key rejection for known unsafe metadata keys.

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

## Errors

This package defines Kernel UnitOfWork validation exceptions:

- `Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException`
  - canonical error code: `CORETSIA_UOW_CONTEXT_INVALID`
- `Coretsia\Kernel\Runtime\Exception\UnitOfWorkResultInvalidException`
  - canonical error code: `CORETSIA_UOW_RESULT_INVALID`

Exception messages MUST be deterministic and safe.

Exception messages MAY include only:

```text
path-to-value
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

The internal json-like normalizer includes deterministic unsafe-key guards for known unsafe metadata keys.

This guard is not a PII detector.

It is a deterministic denylist for obvious policy-key names.

## Public API

Package-local public API evidence is maintained in:

```text
framework/packages/core/kernel/PUBLIC_API.md
```

That file is the source used by the Kernel public API gate.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Kernel package source](https://github.com/coretsia/monorepo/tree/main/framework/packages/core/kernel)
- [Packaging strategy](https://github.com/coretsia/monorepo/blob/main/docs/architecture/PACKAGING.md)
- [UnitOfWork Shapes SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/uow-shapes.md)
- [UnitOfWork Outcome Policy SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/uow-outcome-policy.md)
