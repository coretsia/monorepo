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

# coretsia/core-foundation

`core/foundation` is the **Foundation runtime** package for the Coretsia Framework monorepo.

**Scope:** PSR-11 DI container runtime, deterministic service tags, canonical discovery ordering, canonical json-like runtime value normalization, stable diagnostics serialization, runtime context storage, correlation id baseline services, PSR-20 clock binding, canonical runtime id generators, float-free duration measurement, and reset orchestration for long-running runtimes.

**Out of scope:** kernel lifecycle execution, HTTP middleware stack implementation, CLI command execution, platform adapters, integrations, HTTP correlation header extraction/injection policy, logs/traces/metrics exporters, and tooling-only behavior.

## Package identity

- **Path:** `framework/packages/core/foundation`
- **Package id:** `core/foundation`
- **Composer name:** `coretsia/core-foundation`
- **Module id:** `core.foundation`
- **Namespace:** `Coretsia\Foundation\*` (PSR-4: `src/`)
- **Kind:** runtime

Monorepo versioning is **repo-wide only** via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions **MUST NOT** be used.

## Dependency policy (Phase 1)

This package is runtime-safe and intentionally small:

- **Depends on:**
  - `core/contracts`
  - `psr/clock`
  - `psr/container`
  - `psr/log`
- **Forbidden:**
  - `platform/*`
  - `integrations/*`
  - `devtools/*`

`psr/log` is used only for the baseline `Psr\Log\LoggerInterface` noop binding.

`psr/clock` is used for the baseline `Psr\Clock\ClockInterface` binding.

`core/foundation` MUST NOT depend on Phase 0 tooling packages such as `devtools/internal-toolkit` or `devtools/cli-spikes`.

## Runtime responsibilities

This package provides the baseline runtime mechanisms used by higher-level packages:

- PSR-11 container runtime.
- Deterministic container build behavior from caller-supplied providers.
- Canonical tag registry for service discovery lists.
- Canonical deterministic ordering rule: `priority DESC, id ASC`.
- Foundation runtime context storage through `ContextStore`.
- Immutable context snapshots through `ContextBag`.
- Context safe-write validation against the public `Coretsia\Contracts\Context\ContextKeys` registry.
- Always-on context safe-write validation through `ContextStorePolicy`.
- Canonical json-like runtime value normalization through `JsonLikeNormalizer`.
- Path-aware safe json-like normalization failures through `JsonLikeNormalizationException`.
- Correlation id generation through the canonical ULID generator.
- Correlation id reading through the contracts correlation id provider port.
- PSR-20 runtime clock binding through `Psr\Clock\ClockInterface`.
- Baseline UTC system clock through `SystemClock`.
- Deterministic frozen clock support through `FrozenClock`.
- Generic safe runtime id generation through `IdGeneratorInterface`.
- Canonical ULID default id generation through `UlidGenerator`.
- Optional UUID id generation through `UuidGenerator`.
- Float-free duration measurement through `Stopwatch`.
- Reset orchestration through the effective Foundation reset discovery tag.
- Stable JSON encoding for diagnostics and runtime-safe artifacts through `StableJsonEncoder`, backed by `JsonLikeNormalizer`.

`core/kernel` owns lifecycle trigger points.

`core/foundation` owns the reusable runtime mechanisms that kernel and platform packages consume.

## Configuration

The package owns the `foundation` configuration root.

Defaults live in:

```text
framework/packages/core/foundation/config/foundation.php
```

The defaults file MUST return the subtree only and MUST NOT repeat the root key.

Valid shape:

```php
return [
    'container' => [
        'autowire_concrete' => true,
        'allow_reflection_for_concrete' => true,
    ],
    'ids' => [
        'default' => 'ulid',
    ],
    'reset' => [
        'tag' => 'kernel.reset',
    ],
];
```

Invalid shape:

```php
return [
    'foundation' => [
        'container' => [],
    ],
];
```

Runtime code reads from the global configuration under `foundation.*`.

Canonical Foundation config keys documented by this package:

- `foundation.container.autowire_concrete`
- `foundation.container.allow_reflection_for_concrete`
- `foundation.ids.default`
- `foundation.reset.tag`

`foundation.ids.default` selects only the default generic runtime id generator:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

Allowed values:

```text
ulid
uuid
```

Default value:

```text
ulid
```

`foundation.ids.default` MUST NOT affect:

```text
Coretsia\Foundation\Id\CorrelationIdGenerator
Coretsia\Foundation\Observability\CorrelationIdProvider
```

`correlation_id` remains ULID-backed according to epic `1.210.0`.

This package does not introduce runtime clock config.

The runtime clock binding is fixed to:

```text
Psr\Clock\ClockInterface -> Coretsia\Foundation\Clock\SystemClock
```

`foundation.reset.tag` defines the effective reset discovery tag.

The reserved default value is `kernel.reset`.

Tag discovery and reset orchestration MUST NOT be feature-disabled through config.

Empty discovery lists are represented by empty-list semantics only.

This package does not introduce context or correlation feature toggles.

The following keys MUST NOT be introduced by this epic:

```text
foundation.clock.*
foundation.context.*
foundation.correlation.*
foundation.json_like.*
foundation.serialization.json_like.*
foundation.request_id.*
foundation.time.*
foundation.duration.*
```

Json-like runtime value normalization is baseline runtime infrastructure and MUST NOT be feature-disabled through configuration.

`ContextStore`, `ContextStorePolicy`, `SystemClock`, `Stopwatch`, `UlidGenerator`, `UuidGenerator`, `IdGeneratorInterface`, `CorrelationIdGenerator`, and `CorrelationIdProvider` are baseline runtime infrastructure and MUST NOT be feature-disabled through configuration.

Absence of optional writers/readers is represented by:

```text
no writes
no reads
```

It MUST NOT be represented by disabling Foundation context services.

## DI container

The package provides a PSR-11-compatible container implementation.

Container behavior is deterministic:

- Provider order is supplied by the caller.
- `ContainerBuilder` MUST preserve caller-supplied provider order exactly.
- `ContainerBuilder` MUST NOT globally sort providers by FQCN.
- Later container bindings override earlier container bindings deterministically.
- Tag dedupe remains independent: `TagRegistry` keeps the first occurrence per `(tag, serviceId)`.

Explicit container definitions are shared by default.

A shared definition is resolved once per container instance and cached for subsequent `get()` calls.

A non-shared definition must be marked explicitly by container builder metadata.

Concrete-class autowire resolutions are also cached.

This default is intentional: Foundation container wiring favors stable runtime service identity.

Services that require a fresh instance per resolution must opt out explicitly.

Autowiring is strict:

- Interfaces MUST NOT be autowired.
- Missing `config['foundation']` MUST fail deterministically.
- Missing `config['foundation']['container']` MUST fail deterministically.

`Container::has($id)` is intentionally not a pure metadata-only check for unregistered concrete class ids.

Its behavior is:

```text
invalid id                  → false
known definition/instance   → true
unknown non-class id        → false
unbound interface/abstract  → false
unregistered concrete class → strict concrete-class autowire check
```

For unregistered existing concrete class ids, `Container::has($id)` evaluates the same strict policy as `Container::canAutowire($id)`.

If `foundation.container` is missing or invalid, `Container::has(SomeConcrete::class)` MAY throw `Coretsia\Foundation\Container\Exception\ContainerException` instead of returning `false`.

This is Coretsia-specific strict behavior. Integration code that needs an exception-free PSR-11 presence probe SHOULD catch `Psr\Container\ContainerExceptionInterface` around `has()` and apply its own fallback policy.

Foundation MUST NOT introduce hidden container defaults inside `has()` to make malformed configuration appear valid.

Baseline Foundation services are registered explicitly by the provider and MUST remain resolvable without relying on concrete-class autowiring.

## Tags and deterministic discovery

`Coretsia\Foundation\Tag\ReservedTags` is the canonical code-level registry for framework-reserved DI tag identifiers.

It owns tag strings only. Runtime semantics remain owned by the semantic owner packages declared in `docs/ssot/tags.md`.

`Coretsia\Foundation\Tag\TagRegistry` is the single source of truth for tagged service discovery lists.

Canonical ordering:

```text
priority DESC, id ASC
```

Ordering MUST be locale-independent and use byte-order string comparison (`strcmp`).

Dedupe policy:

- For the same `(tag, serviceId)`, the first occurrence wins.
- Consumers MUST NOT re-sort `TagRegistry->all($tag)` output.
- Consumers MUST NOT apply a different dedupe rule.

Reserved Foundation-owned tags:

- `ReservedTags::KERNEL_RESET`
- `ReservedTags::KERNEL_STATEFUL`

`kernel.reset` is the reserved default reset-discovery tag.

`kernel.stateful` is an enforcement marker for stateful services. Runtime reset execution MUST NOT use `kernel.stateful` as the reset discovery list.

HTTP middleware tags such as `http.middleware.app` are owned by `platform/http`; Foundation provides the registry and deterministic ordering mechanism only.

## Reset orchestration

`Coretsia\Foundation\Runtime\Reset\ResetOrchestrator` executes reset discipline for services discovered through the effective Foundation reset discovery tag.

The orchestrator MUST:

- read the effective discovery tag from Foundation configuration/wiring;
- default to `kernel.reset`;
- obtain the discovery list only through `TagRegistry->all($effectiveResetTag)`;
- resolve services through PSR-11 container access;
- call `Coretsia\Contracts\Runtime\ResetInterface::reset()` exactly once per resolved service per reset cycle;
- be safely callable when the discovery list is empty;
- preserve the exact `TagRegistry` order in base mode;
- never rely on reflection/autowire during reset execution;
- never emit stdout or stderr.

Reset services MAY throw arbitrary exceptions while clearing mutable state.

Foundation reset orchestration maps reset failures to stable `ResetException` instances.

`ResetException` exposes runtime-style diagnostics through:

```text
code()
errorCode()
reason()
```

`code()` and `errorCode()` return the same stable reset error code.

`reason()` returns the stable safe reset reason token.

A surfaced reset failure MAY preserve the original previous throwable for in-process programmatic chaining.

Reset observability MUST NOT record that raw previous throwable chain.

When a reset failure is recorded into a span, Foundation reset orchestration uses a sanitized reset failure copy through:

```text
ResetException::withoutPrevious()
```

The sanitized copy preserves the reset code, error code, reason, and message, but does not preserve the previous throwable.

Reset observability MUST remain summary-only and MUST NOT expose raw service exceptions, previous throwable messages, stack traces, service ids, service instances, tag metadata values, raw context values, credentials, tokens, cookies, authorization values, session ids, raw SQL, object dumps, local absolute paths, or environment-specific bytes.

`Stopwatch` failures during reset observability MUST NOT change reset discovery, reset ordering, reset execution, reset success, or reset failure precedence.

`core/kernel` MUST call only the reset orchestrator and MUST NOT enumerate tagged reset services directly.

`ContextStore` is stateful and MUST be tagged with both:

```text
kernel.stateful
<effective reset discovery tag>
```

The effective reset discovery tag is resolved from:

```text
foundation.reset.tag
```

The reserved default is:

```text
kernel.reset
```

Provider wiring MUST use the same effective reset tag resolver as `ResetOrchestrator`.

Provider wiring MUST NOT duplicate reset tag validation logic.

`ContextStore` MUST be discovered for reset through the effective reset discovery tag, not through `kernel.stateful`.

Stateful-service policy is governed by:

```text
docs/ssot/stateful-services.md
docs/ssot/reset-tags.md
```

Any Foundation service that retains mutable per-UoW state MUST follow the stateful-service policy:

```text
kernel.stateful
⇒ implements Coretsia\Contracts\Runtime\ResetInterface
&& discoverable through the effective Foundation reset discovery tag
```

`kernel.stateful` is an enforcement marker only.

Runtime reset execution MUST continue to use `ResetOrchestrator` and the effective Foundation reset discovery tag.

## Runtime context

Foundation provides one mutable runtime context store:

```text
Coretsia\Foundation\Context\ContextStore
```

`ContextStore` is unit-of-work-local state.

It implements:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
Coretsia\Contracts\Runtime\ResetInterface
```

The canonical read signature is:

```php
public function get(string $key): mixed
```

`ContextStore` MUST NOT add a default parameter to `get()`.

`ContextStore` exposes controlled mutation through write APIs and an immutable snapshot through:

```text
Coretsia\Foundation\Context\ContextBag
```

`ContextBag` is a point-in-time immutable snapshot. It MUST NOT observe later mutations to `ContextStore`.

`ContextBag::all()` and `ContextStore::all()` return copies and MUST NOT expose mutable internal arrays.

## Context accessor binding

The Foundation provider registers one `ContextStore` instance.

The same object instance is registered for:

```text
Coretsia\Foundation\Context\ContextStore
Coretsia\Contracts\Context\ContextAccessorInterface
```

Runtime readers SHOULD depend on:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
```

Runtime code MUST NOT create independent context stores for the same runtime boundary.

Creating more than one context store for the same container would make context reads non-deterministic and could leak or lose unit-of-work-local data.

## Context keys

The canonical public context key registry is:

```text
Coretsia\Contracts\Context\ContextKeys
```

The governing SSoT is:

```text
docs/ssot/context-keys.md
```

`ContextKeys` defines stable key identifiers only.

Importing `ContextKeys` does not grant write ownership over context values.

`ContextStorePolicy` MUST allow writes only for keys declared in `ContextKeys`.

Unknown context keys MUST be rejected deterministically.

Context keys MUST NOT start with `@`.

The `@*` namespace is reserved for config directives.

Baseline active keys:

```text
correlation_id
uow_id
uow_type
client_ip
scheme
host
path
user_agent
```

Reserved future keys:

```text
request_id
path_template
http_response_format
actor_id
tenant_id
```

Reserved future keys MAY be present in `ContextKeys` to prevent name drift, even when their concrete writers are introduced later by owner packages.

## Context safe-write policy

`Coretsia\Foundation\Context\ContextStorePolicy` is the always-on write guard for `ContextStore`.

`ContextStore` MUST call:

```text
ContextStorePolicy::assertCanWrite()
```

for every write.

`ContextStorePolicy` owns context-specific write policy:

- allowlist enforcement against `Coretsia\Contracts\Context\ContextKeys`;
- empty context key rejection;
- reserved `@*` key rejection;
- unknown context key rejection;
- mapping json-like value failures to context write failures.

Baseline value-shape validation is delegated to:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

`ContextStorePolicy` MUST NOT duplicate the recursive baseline json-like value walker.

`ContextStorePolicy` MUST NOT store or return the normalized value produced by `JsonLikeNormalizer`.

`ContextStorePolicy` remains a validation boundary only.

Allowed values are json-like runtime values:

```text
null
bool
int
string
list<value>
array<string,value>
```

Forbidden values include:

```text
float
NAN
INF
-INF
object
Closure
resource
non-string map key
unsupported PHP value type
```

Callable-ness is not a standalone ContextStore type rule.

Plain strings remain valid strings even if PHP could interpret them as callable names.

Example valid string:

```text
strlen
```

Context write failures MUST be deterministic.

Context invalid-key diagnostics expose stable runtime-style diagnostics through:

```text
ContextInvalidKeyException::reason()
ContextInvalidKeyException::safeKey()
```

`reason()` returns a stable context key rejection reason token.

`safeKey()` returns only a conservative safe key segment, `<key>`, or `null`.

Unsafe rejected keys MUST NOT appear raw in exception messages.

Safe rejected keys may remain visible only when they match the conservative safe-key diagnostic policy.

Unsafe rejected keys are represented by the stable placeholder:

```text
<key>
```

Context write-forbidden diagnostics expose stable runtime-style diagnostics through:

```text
ContextWriteForbiddenException::reason()
ContextWriteForbiddenException::safePath()
```

`reason()` returns a stable context write-forbidden reason token.

`safePath()` returns only a conservative safe path-to-value segment, `<path>`, or `null`.

Write-forbidden messages contain only the stable reason token and, when present, a safe path segment.

Unsafe write paths are represented by the stable placeholder:

```text
<path>
```

Unsafe map keys inside otherwise safe value paths are represented by:

```text
[<key>]
```

Context exception messages MUST NOT include rejected raw values, unsafe raw keys, unsafe raw paths, raw map keys, object dumps, stack traces, credentials, tokens, cookies, authorization values, session ids, raw SQL, absolute local paths, or environment-specific bytes.

## Json-like runtime values

Foundation owns the canonical baseline runtime json-like value normalizer:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

The canonical baseline exception is:

```text
Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException
```

The governing SSoT is:

```text
docs/ssot/json-like-runtime-values.md
```

`JsonLikeNormalizer` accepts only:

```text
null
bool
int
string
list<value>
array<string,value>
```

`JsonLikeNormalizer` rejects:

```text
float
NAN
INF
-INF
object
Closure
resource
non-string map key
unsupported PHP value type
```

Maps are normalized recursively by byte-order `strcmp`.

Lists preserve caller-supplied order.

Empty arrays are preserved as `[]`.

Failures are path-aware and safe. Diagnostics include only:

```text
CORETSIA_JSON_LIKE_INVALID
safe path-to-value
stable reason token
```

Diagnostics MUST NOT include raw values, object class names, resource ids, secrets, raw payloads, raw SQL, local paths, or environment-specific bytes.

`StableJsonEncoder` uses `JsonLikeNormalizer` before `json_encode()`.

`ContextStorePolicy` uses `JsonLikeNormalizer` for value validation.

Higher runtime packages, including Kernel, MAY consume `JsonLikeNormalizer` through their own domain-specific wrappers when package dependency rules allow a dependency on `core/foundation`.

Foundation does not introduce:

- UoW-specific root map policy;
- unsafe metadata key denylist;
- transport/request payload semantics;
- DTO policy;
- generic redaction engine;
- Kernel exception mapping.

## Context security / redaction

`ContextStore` MUST NOT store secrets or sensitive payload material.

Forbidden context data includes:

- tokens in any form;
- session ids;
- cookies;
- Authorization headers;
- credentials;
- passwords;
- private keys;
- raw request bodies;
- raw response bodies;
- raw headers;
- raw SQL;
- profile payloads;
- private customer data;
- direct user identifiers such as email, phone, full name, username, or external account identifiers.

Request metadata keys such as `client_ip`, `user_agent`, `host`, and `path` are allowed only when declared in `ContextKeys` and when values obey `ContextStorePolicy`.

Potentially sensitive request metadata such as `client_ip` SHOULD be normalized or hashed by writers when feasible.

Raw `path` is allowed only as in-process context when deliberately written by an owner.

Raw `path` MUST NOT be emitted to logs, spans, metrics, public diagnostics, generated artifacts, or error descriptor extensions.

When path-like observability data is needed, owners SHOULD use:

```text
path_template
hash(value)
len(value)
```

## Correlation id baseline

Foundation provides the canonical ULID source:

```text
Coretsia\Foundation\Id\UlidGenerator
```

`UlidGenerator` is the single ULID implementation source in the codebase.

Generated ULIDs use uppercase Crockford Base32 format:

```text
/\A[0-9A-HJKMNP-TV-Z]{26}\z/
```

Foundation also provides:

```text
Coretsia\Foundation\Id\CorrelationIdGenerator
```

`CorrelationIdGenerator` MUST receive `UlidGenerator` through constructor injection.

`CorrelationIdGenerator` MUST delegate generation to `UlidGenerator`.

`CorrelationIdGenerator` MUST NOT implement timestamp, entropy, or Crockford Base32 encoding logic independently.

`CorrelationIdGenerator` MUST NOT post-process generated values in a way that can create format drift.

## Correlation id provider

Foundation provides:

```text
Coretsia\Foundation\Observability\CorrelationIdProvider
```

It implements:

```text
Coretsia\Contracts\Observability\CorrelationIdProviderInterface
```

The provider reads the current correlation id from:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
```

using:

```text
Coretsia\Contracts\Context\ContextKeys::CORRELATION_ID
```

The provider returns the current context correlation id only when the stored value is a string matching the canonical Foundation correlation id format:

```text
/\A[0-9A-HJKMNP-TV-Z]{26}\z/
```

The provider returns `null` when the context value is absent, empty, non-string, lowercase, mixed-case, malformed, token-like, cookie-like, SQL-like, URL-like, path-like, header-like, control-character-containing, or otherwise unsafe.

It MUST NOT:

- generate a new correlation id as a side effect of reading;
- normalize stored context values;
- uppercase, trim, rewrite, remove, replace, or store context values while reading;
- leak malformed or unsafe context values through exceptions, logs, traces, metrics, diagnostics, or generated artifacts;
- define HTTP header extraction policy;
- define HTTP header injection policy.

Correlation id generation belongs to the unit-of-work owner.

Transport-specific correlation propagation belongs to platform packages.

## Clock baseline

Foundation provides the baseline runtime clock implementation:

```text
Coretsia\Foundation\Clock\SystemClock
```

`SystemClock` implements:

```text
Psr\Clock\ClockInterface
```

The Foundation provider binds:

```text
Psr\Clock\ClockInterface -> Coretsia\Foundation\Clock\SystemClock
Coretsia\Foundation\Clock\SystemClock -> same SystemClock instance
```

`SystemClock::now()` returns `DateTimeImmutable` in UTC.

`SystemClock` intentionally does not promise monotonicity. System time may jump because it is controlled by the operating system and host environment.

Runtime code that needs duration measurement MUST use `Stopwatch`, not differences between `SystemClock` values.

This package does not introduce:

```text
foundation.clock.*
```

Clock selection is not runtime-config-driven in this epic.

## Frozen clock

Foundation provides deterministic frozen clock support:

```text
Coretsia\Foundation\Clock\FrozenClock
```

`FrozenClock` implements:

```text
Psr\Clock\ClockInterface
```

`FrozenClock::now()` returns the same logical instant on every call.

`FrozenClock` is test/support infrastructure.

`FrozenClock` is not selected through runtime config in this epic.

`FrozenClock` is not registered as the default runtime `ClockInterface` binding by the Foundation provider.

## Runtime id generation

Foundation provides the canonical generic runtime id abstraction:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

The canonical method is:

```php
public function generate(): string
```

Generated ids are safe opaque deterministic-format strings.

Generated ids MUST NOT be derived from secrets, credentials, raw payloads, direct user identifiers, cookies, sessions, authorization values, raw headers, raw request bodies, raw response bodies, raw SQL, or private customer data.

Foundation provides the canonical ULID generator:

```text
Coretsia\Foundation\Id\UlidGenerator
```

`UlidGenerator` is the single ULID implementation source in the codebase.

Canonical ULID format:

```text
/\A[0-9A-HJKMNP-TV-Z]{26}\z/
```

Foundation also provides an optional UUID generator:

```text
Coretsia\Foundation\Id\UuidGenerator
```

Canonical UUID format:

```text
/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/
```

The default generic runtime id generator is selected only through:

```text
foundation.ids.default
```

Mapping:

| `foundation.ids.default` | `IdGeneratorInterface` binding target                |
|--------------------------|------------------------------------------------------|
| `ulid`                   | same `Coretsia\Foundation\Id\UlidGenerator` instance |
| `uuid`                   | same `Coretsia\Foundation\Id\UuidGenerator` instance |

The default is:

```text
ulid
```

`foundation.ids.default` MUST NOT affect correlation id generation.

`CorrelationIdGenerator` remains ULID-backed and delegates directly to `UlidGenerator`.

`CorrelationIdGenerator` MUST NOT depend on `IdGeneratorInterface`.

`CorrelationIdProvider` is a read provider and MUST NOT be affected by `foundation.ids.default`.

## Stopwatch

Foundation provides the canonical float-free duration service:

```text
Coretsia\Foundation\Time\Stopwatch
```

`Stopwatch` is baseline runtime infrastructure and MUST be resolvable whenever `core/foundation` is enabled.

`Stopwatch` MUST NOT be feature-disabled through config.

Absence of duration measurement in a consumer is represented by:

```text
consumer does not call Stopwatch
```

It MUST NOT be represented by disabling `Stopwatch`.

`Stopwatch::start()` returns a monotonic timestamp token as integer nanoseconds from:

```php
hrtime(true)
```

`Stopwatch::stop(int $startedAt)` returns duration in integer milliseconds.

`$startedAt` MUST be a positive Stopwatch token returned by `start()`.

`Stopwatch` does not track issued token provenance. Runtime enforcement is limited to positive-token validation and elapsed-time calculation.

Non-positive tokens fail deterministically with:

```text
Coretsia\Foundation\Time\Exception\StopwatchInvalidStateException
```

The exception message MUST be stable and safe and MUST NOT contain the raw token value.

Elapsed duration is computed from:

```php
hrtime(true) - $startedAt
```

If the elapsed duration is negative or zero, `stop()` returns:

```text
0
```

If the elapsed duration is positive, it is converted with:

```php
intdiv($durationNs, 1_000_000)
```

`Stopwatch` MUST NOT use:

```text
microtime(true)
```

`Stopwatch` MUST NOT expose float durations.

Canonical duration values are:

```text
int milliseconds
>= 0
```

Canonical field suffix:

```text
_duration_ms
```

Canonical semantic name:

```text
durationMs
```

## Context lifecycle

All `ContextStore` state is unit-of-work-local.

A unit of work may be:

```text
HTTP request
CLI command invocation
worker job
queue message
scheduler tick
custom runtime boundary
```

At begin-UoW, Kernel runtime MUST set base keys:

```text
correlation_id
uow_id
uow_type
```

At or after the end of a unit of work, reset orchestration MUST clear `ContextStore` before the next unit of work can observe stale context.

Acceptance scenario:

1. one unit of work starts;
2. owner code writes safe context keys;
3. runtime packages read context through `ContextAccessorInterface`;
4. the unit of work finishes;
5. Foundation reset orchestration calls `ContextStore::reset()`;
6. the next unit of work starts with an empty store except new base keys set by Kernel.

## Stable diagnostics and serialization

`core/foundation` provides stable serialization primitives for diagnostics and runtime-safe outputs.

Diagnostics MUST be safe by construction:

- MUST NOT dump service instances.
- MUST NOT dump constructor arguments.
- MUST NOT dump reflection data.
- MUST NOT include arbitrary tag metadata values.
- MUST NOT leak secrets, tokens, cookies, authorization headers, env values, PII, or absolute local paths.
- MUST NOT dump raw context values.

Stable JSON output is provided by:

```text
Coretsia\Foundation\Serialization\StableJsonEncoder
```

Baseline json-like validation and recursive deterministic normalization are delegated to:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

Stable JSON output MUST:

- sort map keys recursively by `strcmp`;
- preserve list order;
- use LF-only line endings;
- end with a final newline;
- reject floats, objects, resources, closures, unsupported PHP value types, and non-string map keys;
- preserve `stable-json-*` failure semantics;
- preserve `stable-json-encode-failed` for `json_encode()` failures.

`StableJsonEncoder` owns only encoder-specific behavior:

- `json_encode()` invocation;
- JSON flags;
- final LF;
- stable-json reason mapping.

`StableJsonEncoder` MUST NOT reintroduce a private recursive json-like walker.

Redaction of caller-owned payloads remains the caller's responsibility.

The encoder itself does not inspect environment variables.

Foundation container diagnostics are exported through:

```text
Coretsia\Foundation\Container\ContainerDiagnostics
```

Container diagnostics snapshots are deterministic and safe by construction.

Container diagnostics MAY include only:

```text
schema version
safe service id diagnostics
tag names
tag priorities
```

Normal FQCN service ids and conservative safe aliases may remain readable.

Suspicious, sensitive, unsafe, control-character-containing, URL-like, token-like, credential-like, password-like, secret-like, cookie-like, authorization-like, SQL-like, path-like, absolute-path-like, overlong, or otherwise non-readable service ids MUST NOT appear raw in diagnostics.

Unsafe service ids may be replaced with deterministic hash diagnostics using:

```text
hash:sha256:<hash>;len:<len>
```

The hash is the lowercase hexadecimal SHA-256 hash of the original service id bytes.

The length is the byte length of the original service id.

Container diagnostics MUST NOT include service instances, constructor arguments, reflection data, arbitrary tag metadata values, raw config payloads, environment values, credentials, tokens, cookies, authorization values, private customer data, raw SQL, or absolute local paths.

Container diagnostics remain introspection-only and MUST NOT be consumed as a runtime discovery source.

## Observability

This package does not own telemetry exporters or backends. Foundation runtime code may emit only safe summary observability through injected observability ports.

Foundation diagnostics are structural snapshots only. They MUST be deterministic and redaction-safe.

Foundation provides noop bindings; platform packages override them.

The Foundation baseline registers default noop bindings for:

- `Psr\Log\LoggerInterface`
- `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
- `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`
- `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- `Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface`
- `Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface`

The Foundation baseline also registers correlation id read services:

- `Coretsia\Foundation\Observability\CorrelationIdProvider`
- `Coretsia\Contracts\Observability\CorrelationIdProviderInterface`

These bindings exist so runtime packages can safely resolve observability, logging, context, and correlation ports before any `platform/*` implementation package is installed.

The noop implementations MUST NOT emit stdout/stderr, store raw payloads, store headers, store tokens, store raw SQL, or retain private customer data.

`Coretsia\Contracts\Observability\Tracing\SpanInterface` is intentionally not registered as a root container binding. Noop spans are obtained from `TracerPortInterface`.

`Coretsia\Contracts\Observability\Profiling\ProfilingSessionInterface` is intentionally not registered as a root container binding. Noop profiling sessions are obtained from `ProfilerPortInterface`.

Later platform providers MAY override these defaults by rebinding the same service ids/interfaces. Container collision policy remains deterministic: later bindings override earlier bindings.

`correlation_id` is safe for logs/tracing correlation when owner policy allows it.

`correlation_id` MUST NOT be used as a metric label under the baseline observability policy.

Generic safe ids are allowed as opaque ids for owner-approved logs or spans, but ids MUST NOT be used as metric labels.

The following values MUST NOT be metric labels:

```text
correlation_id
uow_id
request_id
ULID
UUID
```

Duration values are values only.

Duration metric names SHOULD use `_duration_ms` suffix where applicable.

`durationMs` is an integer millisecond value and MUST NOT be used as a metric label.

Raw `path`, raw query, headers, cookies, Authorization values, tokens, and payloads MUST NOT be exported even if present in `ContextStore`.

Foundation reset observability uses the canonical reset span and metrics:

```text
foundation.reset
foundation.reset_total
foundation.reset_duration_ms
```

Reset metric labels are limited to:

```text
outcome
```

Reset logs use the stable summary message:

```text
foundation.reset
```

Reset logs, metrics, spans, and span exception recording MUST remain summary-only.

Allowed reset observability diagnostics are limited to stable reset error code, stable reset reason token, summary service count, summary group count, reset outcome, and reset duration in milliseconds.

When reset execution fails, span exception recording may record only a sanitized `ResetException` copy created through:

```text
ResetException::withoutPrevious()
```

The recorded reset exception MUST NOT preserve previous throwable chains.

Reset observability MUST NOT leak raw previous throwable messages, stack traces, raw reset service exception messages, service ids, service instances, tag metadata values, raw context values, credentials, tokens, cookies, authorization values, session ids, raw SQL, object dumps, local absolute paths, or environment-specific bytes.

## Errors

This package defines Foundation runtime exceptions for container behavior:

- `Coretsia\Foundation\Container\Exception\ContainerException`
  - canonical error code: `CORETSIA_CONTAINER_ERROR`
- `Coretsia\Foundation\Container\Exception\NotFoundException`
  - canonical error code: `CORETSIA_CONTAINER_NOT_FOUND`

This package also defines Foundation context exceptions:

- `Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException`
  - canonical error code: `CORETSIA_CONTEXT_WRITE_FORBIDDEN`
- `Coretsia\Foundation\Context\Exception\ContextInvalidKeyException`
  - canonical error code: `CORETSIA_CONTEXT_INVALID_KEY`

This package also defines Foundation serialization exceptions:

- `Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException`
  - canonical error code: `CORETSIA_JSON_LIKE_INVALID`

Context exception messages MUST be deterministic and safe.

Context exception messages MUST NOT contain raw context values.

`ContextInvalidKeyException` exposes stable runtime-style diagnostics through:

```text
errorCode()
reason()
safeKey()
```

`safeKey()` returns only a conservative safe key segment, `<key>`, or `null`.

`ContextWriteForbiddenException` exposes stable runtime-style diagnostics through:

```text
errorCode()
reason()
safePath()
```

`safePath()` returns only a conservative safe path-to-value segment, `<path>`, or `null`.

Context invalid-key and write-forbidden messages are constructed only from stable reason tokens and safe diagnostic segments.

Previous throwable chains may be preserved for in-process programmatic chaining, but previous throwable messages MUST NOT be copied into context exception messages.

Json-like normalization exception messages MUST be deterministic and safe.

Json-like normalization exception messages MUST contain only the package error code, safe path-to-value, and stable reason token.

This package also defines Foundation time/id exceptions:

- `Coretsia\Foundation\Time\Exception\StopwatchInvalidStateException`
  - canonical error code: `CORETSIA_STOPWATCH_INVALID_STATE`
- `Coretsia\Foundation\Id\Exception\IdGenerationFailedException`
  - canonical error code: `CORETSIA_ID_GENERATION_FAILED`

Stopwatch and id generation exception messages MUST be deterministic and safe.

Stopwatch exception messages MUST NOT contain raw timing tokens.

ID generation exception messages MUST NOT contain raw entropy bytes, generated partial ids, host-specific values, or environment data.

Reset misuse and reset execution failure are deterministic hard-fails.

This package defines the reset-specific exception:

```text
Coretsia\Foundation\Runtime\Reset\ResetException
```

`ResetException` exposes stable runtime-style diagnostics through:

```text
code()
errorCode()
reason()
withoutPrevious()
```

`code()` and `errorCode()` return the same stable reset error code.

`reason()` returns the stable safe reset reason token.

`withoutPrevious()` returns a sanitized reset exception copy with the same code, error code, reason, and message, but without the previous throwable chain.

Higher-level error mapping is owned by higher layers.

## Security / Redaction

`core/foundation` MUST NOT leak sensitive runtime data.

Forbidden in diagnostics:

- environment values;
- credentials;
- secrets;
- tokens;
- private keys;
- authorization headers;
- cookies;
- raw request or response payloads;
- raw queue messages;
- raw SQL;
- raw config payloads;
- raw context values;
- constructor arguments;
- service instances;
- arbitrary tag metadata;
- reflection dumps;
- absolute local paths;
- private customer data / PII.

Forbidden in `ContextStore`:

- tokens;
- session ids;
- cookies;
- Authorization headers;
- credentials;
- raw request bodies;
- raw response bodies;
- raw headers;
- raw SQL;
- private customer data;
- direct user identifiers.

ID generation MUST NOT derive ids from:

- credentials;
- passwords;
- secrets;
- tokens;
- private keys;
- authorization headers;
- cookies;
- session ids;
- raw request bodies;
- raw response bodies;
- raw headers;
- raw SQL;
- raw queue messages;
- private customer data;
- direct user identifiers such as email, phone, full name, username, or external account identifiers.

Generated ids are safe opaque ids.

Safe opaque ids MUST NOT be treated as proof that related payloads are safe to emit.

Stopwatch tokens returned by `Stopwatch::start()` MUST NOT be exported to logs, metrics, traces, diagnostics, or artifacts.

Only the final `durationMs` value may be exported by owner packages according to observability policy.

Allowed diagnostic information is limited to safe structural metadata such as safe service id diagnostics, tag names, priorities, schema versions, conservative safe key segments, conservative safe path-to-value segments, stable placeholders, stable reason tokens, package-owned error codes, summary counts, durations, and safe derivations such as `hash(value)` / `len(value)` for potentially sensitive strings.

Stable diagnostic placeholders include:

```text
<key>
<path>
[<key>]
hash:sha256:<hash>;len:<len>
```

Json-like normalization diagnostics MUST NOT include rejected raw values, object class names, enum class names, resource ids, raw payloads, raw SQL, local absolute paths, or environment-specific bytes.

Context diagnostics MUST NOT include rejected raw values, unsafe raw rejected keys, unsafe raw paths, raw map keys, object dumps, stack traces, credentials, tokens, cookies, authorization values, session ids, raw SQL, absolute local paths, or environment-specific bytes.

Reset observability MUST NOT include raw previous throwable messages, stack traces, raw reset service exception messages, service ids, service instances, tag metadata values, raw context values, credentials, tokens, cookies, authorization values, session ids, raw SQL, object dumps, local absolute paths, or environment-specific bytes.

Container diagnostics MUST hash unsafe or suspicious service ids before export and MUST NOT expose raw unsafe service ids.

Allowed context information is limited to keys declared in `ContextKeys` with values accepted by `ContextStorePolicy`.

Runtime owners MUST prefer omission over unsafe emission.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Foundation package source](https://github.com/coretsia/monorepo/tree/main/framework/packages/core/foundation)
- [Packaging strategy](https://github.com/coretsia/monorepo/blob/main/docs/architecture/PACKAGING.md)
- [Roadmap](https://github.com/coretsia/monorepo/blob/main/docs/roadmap/ROADMAP.md)
- [Context Keys SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/context-keys.md)
- [Context Store SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/context-store.md)
- [Json-like Runtime Values SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/json-like-runtime-values.md)
- [Time, IDs, and Duration SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/time-ids-and-duration.md)
- [Stateful Services SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/stateful-services.md)
- [Reset Tags SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/reset-tags.md)
- [Tag Registry SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/tags.md)
- [Config Roots SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/config-roots.md)
- [Config and env SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/config-and-env.md)
- [HTTP Middleware Catalog SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/http-middleware-catalog.md)
- [DI Tags and Middleware Ordering SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/di-tags-and-middleware-ordering.md)
- [Observability Naming and Labels Allowlist](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability.md)
- [Observability and Errors SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability-and-errors.md)
- [UoW and Reset Contracts SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/uow-and-reset-contracts.md)
- [ADR-0014: DI container, tags, deterministic ordering, and reset orchestration](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md)
- [ADR-0015: ContextBag, ContextStore, and CorrelationId](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0015-context-bag-context-store-correlation-id.md)
- [ADR-0016: Clock, IDs, and Stopwatch](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0016-clock-ids-stopwatch.md)
