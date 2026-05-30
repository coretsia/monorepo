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

**Scope:** Kernel module metadata, Kernel service provider wiring, Bootstrap Phase A minimal boot-input resolution, deterministic app target selection, dotenv/system env source precedence, immutable env repository snapshot construction, Kernel-owned `KernelRuntime` implementation, hook invocation, Kernel-owned format-neutral UnitOfWork context/result shapes, UnitOfWork type and outcome vocabularies, UoW-specific json-like shape policy through a Foundation-backed internal wrapper, normalized hook payload production, canonical UnitOfWork lifecycle policy, and safe lifecycle summary observability.

**Out of scope:** full ConfigKernel Phase B config discovery/merge/directives/explain behavior, module planning, preset file parsing, public bootstrap orchestration facade ownership, public bootstrap aggregate result ownership, reusable baseline json-like runtime value model ownership, generic redaction engine, HTTP response construction, HTTP status-code selection, PSR-7/PSR-15 integration, CLI command execution, CLI output rendering, platform adapters, integrations, observability exporters/backends, reset discovery implementation, and tooling-only behavior.

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
- Kernel-owned UnitOfWork lifecycle implementation through `Coretsia\Kernel\Runtime\KernelRuntime`.
- Contracts-level runtime port binding:
  - `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
  - bound by DI to `Coretsia\Kernel\Runtime\KernelRuntime`
- Kernel hook invocation through `Coretsia\Kernel\Runtime\Hook\HookInvoker`.
- Kernel hook payload normalization through `Coretsia\Kernel\Runtime\Hook\HookContextNormalizer`.
- Kernel-owned lifecycle hook discovery tag constants through `Coretsia\Kernel\Provider\Tags`.
- Kernel configuration defaults and validation rules under the `kernel` config root.
- Bootstrap Phase A public input/config API:
  - `Coretsia\Kernel\Boot\AppTarget`
  - `Coretsia\Kernel\Boot\BootstrapInput`
  - `Coretsia\Kernel\Boot\BootstrapConfig`
  - `Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy`
  - `Coretsia\Kernel\Boot\Exception\BootstrapException`
- Bootstrap Phase A internal implementation services registered through DI:
  - `Coretsia\Kernel\Boot\BootstrapConfigResolver`
  - `Coretsia\Kernel\Boot\BootstrapOverridesLoader`
  - `Coretsia\Kernel\Boot\DotenvLoader`
  - `Coretsia\Kernel\Boot\EnvRepositoryBuilder`
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
- Deterministic Kernel runtime failures:
  - `Coretsia\Kernel\Runtime\Exception\KernelRuntimeException`
  - `CORETSIA_KERNEL_RUNTIME_ERROR`
- Safe lifecycle summary observability emitted by `KernelRuntime`:
  - span: `kernel.uow`
  - metrics: `kernel.uow_total`, `kernel.uow_duration_ms`
  - log message: `kernel.uow`
  - allowed labels/context keys: `operation`, `outcome`, `duration_ms` for logs

Foundation owns reusable runtime mechanisms such as ids, clocks, stopwatch, context storage, deterministic tags, and reset orchestration.

Foundation also owns the reusable baseline json-like runtime value model.

Kernel owns only the UoW-specific layer on top of that model: root map policy, unsafe metadata key policy, attributes limits, exported error map policy, and UoW exception mapping.

Platform packages own transport adapters.

## Bootstrap Phase A

Bootstrap Phase A is the minimal deterministic Kernel boot-input phase.

It resolves only:

```text
skeletonRoot
appTarget
appEnv
preset
debug
envSourcePolicy
appRoot
immutable EnvRepositoryInterface snapshot
```

Phase A is not a full config merge phase.

Full config file discovery, merge, directives, validation, explain output, and environment overlays are owned by ConfigKernel Phase B.

Phase A MAY read only one bootstrap-only skeleton config file:

```text
skeleton/config/app.php
```

This file is not a config root and MUST NOT participate in ConfigKernel Phase B merge.

Phase A MUST NOT read:

```text
skeleton/config/all.php
skeleton/config/<root>.php
skeleton/config/environments/**
skeleton/apps/<appTarget>/config/**
```

Application target selection is explicit and entrypoint-owned.

Canonical app targets are:

```text
web
api
console
worker
```

The selected app root is derived deterministically as:

```text
skeletonRoot/apps/<appTarget>
```

Phase A MUST NOT scan `skeleton/apps/*` to infer the selected app.

`BootstrapConfig` is a resolved immutable value object only. It MUST NOT resolve defaults, read `skeleton/config/app.php`, parse dotenv files, read system env, or build an env repository.

`BootstrapConfigResolver` is internal and owns only Phase A config resolution:

```text
explicit BootstrapInput
→ bootstrap-only overrides from skeleton/config/app.php
→ package defaults from kernel.boot.* and kernel.env.*
→ BootstrapConfig
```

`EnvRepositoryBuilder` is internal and owns only immutable env repository snapshot construction.

`BootstrapEnvSourcePolicy` controls dotenv/system env source precedence:

```text
strict_dotenv
allow_system
```

It is intentionally separate from `Coretsia\Contracts\Env\EnvPolicy`, which remains a missing-value policy only.

Kernel does not introduce public `Bootstrapper` or public `BootstrapResult` in this package. Entrypoint and platform owners compose the explicit Phase A services through DI until a future owner epic requires a stable public orchestration facade.

## KernelRuntime SPI

The external runtime SPI is owned by `core/contracts`:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

The concrete implementation is owned by `core/kernel`:

```text
Coretsia\Kernel\Runtime\KernelRuntime
```

`Coretsia\Kernel\Runtime\KernelRuntime` is the `core/kernel` implementation bound to the contracts port by DI.

Platform, worker, scheduler, queue, and custom runtime adapters MUST depend on:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

Adapters MUST NOT typehint, construct, or directly depend on:

```text
Coretsia\Kernel\Runtime\KernelRuntime
```

The high-level lifecycle API is:

```text
runUnitOfWork(string $type, callable $body, array $attributes = []): mixed
```

`runUnitOfWork()` returns the external body return value.

It MUST NOT return the exported UnitOfWork result array.

Low-level adapters that need exported context/result arrays MAY use:

```text
beginUnitOfWork(string $type, array $attributes = []): array
afterUnitOfWork(array $context, string $outcome, ?Throwable $error = null, array $extensions = []): array
```

Low-level adapters MUST execute their external body only after successful `beginUnitOfWork()`.

Low-level adapters that need the exported result array MUST use `afterUnitOfWork()`.

## KernelRuntime lifecycle

The canonical high-level lifecycle is:

```text
begin UnitOfWork
write base ContextStore keys
invoke before-uow hooks
run external runtime body
build UnitOfWork result
invoke after-uow hooks
ResetOrchestrator.resetAll()
surface result or primary failure
```

The conceptual shorthand is:

```text
begin → hooks → external runtime → after → reset
```

`KernelRuntime` writes these base context keys before the external runtime body is executed:

```text
Coretsia\Foundation\Context\ContextKeys::CORRELATION_ID
Coretsia\Foundation\Context\ContextKeys::UOW_ID
Coretsia\Foundation\Context\ContextKeys::UOW_TYPE
```

Before hooks receive the normalized exported UnitOfWork context array.

After hooks receive the normalized exported UnitOfWork context array and normalized exported UnitOfWork result array.

No `UnitOfWorkContext`, `UnitOfWorkResult`, `ErrorDescriptor`, `Throwable`, transport object, service object, closure, or resource may cross the hook/export boundary.

## Hook discovery

Kernel lifecycle hooks are discovered through Kernel-owned tags.

The canonical public owner constants are:

```text
Coretsia\Kernel\Provider\Tags::KERNEL_HOOK_BEFORE_UOW
Coretsia\Kernel\Provider\Tags::KERNEL_HOOK_AFTER_UOW
```

Their values are:

```text
kernel.hook.before_uow
kernel.hook.after_uow
```

Runtime code that is allowed to depend on `core/kernel` and needs these tags SHOULD use the constants instead of raw literal strings.

Hook services are resolved by `HookInvoker` from Foundation `TagRegistry` entries.

`HookInvoker` preserves the exact order returned by `TagRegistry::all()`.

It MUST NOT re-sort hooks.

It MUST NOT dedupe hooks.

It MUST NOT apply custom priority rules.

Hook service ids are resolved through PSR-11 container lookup.

A before hook must implement:

```text
Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface
```

An after hook must implement:

```text
Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface
```

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
    'boot' => [
        'default_env' => 'local',
        'default_preset' => 'micro',
        'default_debug' => false,
    ],
    'env' => [
        'source_policy' => [
            'default_local' => 'strict_dotenv',
            'default_production' => 'allow_system',
        ],
        'dotenv' => [
            'files' => [
                '.env',
                '.env.local',
                '.env.<env>',
                '.env.<env>.local',
            ],
        ],
    ],
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

| key                                           | default                                                    |
|-----------------------------------------------|------------------------------------------------------------|
| `kernel.boot.default_env`                     | `"local"`                                                  |
| `kernel.boot.default_preset`                  | `"micro"`                                                  |
| `kernel.boot.default_debug`                   | `false`                                                    |
| `kernel.env.source_policy.default_local`      | `"strict_dotenv"`                                          |
| `kernel.env.source_policy.default_production` | `"allow_system"`                                           |
| `kernel.env.dotenv.files`                     | `[".env", ".env.local", ".env.<env>", ".env.<env>.local"]` |
| `kernel.uow.attributes.max_depth`             | `10`                                                       |
| `kernel.uow.attributes.max_keys`              | `200`                                                      |

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

Kernel hooks and low-level adapters receive exported array shapes, not shape objects.

Before hooks receive context data as:

```text
UnitOfWorkContext -> normalized array $context
```

After hooks receive context/result data as:

```text
UnitOfWorkContext -> normalized array $context
UnitOfWorkResult -> normalized array $result
```

The contracts hook signatures are:

```text
BeforeUowHookInterface::beforeUow(array $context): void
AfterUowHookInterface::afterUow(array $context, array $result): void
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

Kernel runtime code consumes reset only through:

```text
ResetOrchestrator::resetAll()
```

`core/kernel` MUST NOT enumerate reset-tagged services directly.

`core/kernel` MUST NOT call `ResetInterface::reset()` directly on discovered services.

`core/kernel` MUST NOT define `KERNEL_RESET` constants.

The reset discovery tag is owned by `core/foundation`.

The canonical lifecycle position is:

```text
after-uow hooks → ResetOrchestrator.resetAll()
```

For every UnitOfWork lifecycle that reaches reset responsibility, `KernelRuntime` MUST call `ResetOrchestrator::resetAll()` exactly once.

If an earlier primary failure exists and reset also fails, the earlier primary failure remains surfaced.

If no earlier primary failure exists and reset fails, `KernelRuntime` surfaces a safe `KernelRuntimeException` with reason:

```text
kernel-runtime-reset-failed
```

## No PSR-7/15 runtime boundary

Kernel runtime APIs are format-neutral.

`core/kernel` MUST NOT expose or require:

```text
Psr\Http\Message\*
Psr\Http\Server\*
```

Kernel runtime code MUST NOT depend on platform HTTP request/response objects, PSR-7 request/response objects, PSR-15 middleware/handler objects, CLI command objects, queue vendor messages, scheduler vendor contexts, or integration package objects.

PSR logger and PSR container usage are allowed implementation dependencies:

```text
Psr\Container\ContainerInterface
Psr\Log\LoggerInterface
```

The PSR-7/15 ban is specific to transport APIs, not every `Psr\*` namespace.

## Observability

`KernelRuntime` emits safe lifecycle summary observability through injected ports.

The concrete exporters/backends remain out of scope for `core/kernel`.

KernelRuntime receives these observability dependencies through DI:

```text
Psr\Log\LoggerInterface
Coretsia\Contracts\Observability\Tracing\TracerPortInterface
Coretsia\Contracts\Observability\Metrics\MeterPortInterface
```

The canonical span name is:

```text
kernel.uow
```

The canonical metrics are:

```text
kernel.uow_total
kernel.uow_duration_ms
```

The lifecycle summary log message is:

```text
kernel.uow
```

Allowed labels/attributes for span and metrics are:

```text
operation
outcome
```

`operation` is the normalized UnitOfWork type.

For an HTTP UnitOfWork:

```text
operation = http
```

`outcome` is the normalized UnitOfWork outcome token.

The lifecycle summary log context contains only:

```text
duration_ms
operation
outcome
```

Lifecycle summary observability MUST NOT include:

- raw `uowId`;
- raw `correlationId`;
- raw context arrays;
- raw hook payloads;
- raw transport payloads;
- raw Throwable messages;
- stack traces;
- tokens;
- cookies;
- headers;
- raw SQL;
- local absolute paths.

Observability port failures MUST NOT replace primary KernelRuntime lifecycle failures.

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

Bootstrap Phase A public API symbols are:

```text
Coretsia\Kernel\Boot\AppTarget
Coretsia\Kernel\Boot\BootstrapConfig
Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy
Coretsia\Kernel\Boot\BootstrapInput
Coretsia\Kernel\Boot\Exception\BootstrapException
```

Bootstrap Phase A implementation helpers are internal and MUST NOT be listed as public API:

```text
Coretsia\Kernel\Boot\BootstrapConfigResolver
Coretsia\Kernel\Boot\BootstrapOverridesLoader
Coretsia\Kernel\Boot\DotenvLoader
Coretsia\Kernel\Boot\EnvRepositoryBuilder
Coretsia\Kernel\Boot\ArrayEnvRepository
```

`Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer` is internal implementation detail.

It MUST NOT be listed as Kernel public API.

Kernel public API consumers MUST use `UnitOfWorkContext` and `UnitOfWorkResult`, not the internal normalizer.

Runtime adapters MUST use the contracts-level runtime port:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface
```

Runtime adapters MUST NOT typehint or construct the concrete implementation directly:

```text
Coretsia\Kernel\Runtime\KernelRuntime
```

The concrete implementation is resolved through DI binding in `core/kernel`.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Kernel package source](https://github.com/coretsia/monorepo/tree/main/framework/packages/core/kernel)
- [Packaging strategy](https://github.com/coretsia/monorepo/blob/main/docs/architecture/PACKAGING.md)
- [Json-like Runtime Values SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/json-like-runtime-values.md)
- [UnitOfWork Shapes SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/uow-shapes.md)
- [UnitOfWork Outcome Policy SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/uow-outcome-policy.md)
- [UoW and Reset Contracts SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/uow-and-reset-contracts.md)
- [ADR-0020: Kernel runtime UnitOfWork SPI](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0020-kernel-runtime-uow-spi.md)
- [ADR-0023: Kernel Bootstrap Phase A](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0023-kernel-bootstrap-phase-a.md)
