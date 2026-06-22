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

# ADR-0015: ContextBag, ContextStore, and CorrelationId

## Status

Accepted.

## Runtime diagnostics hardening follow-up note

Epic `1.277.0` hardens Foundation context diagnostics and correlation id reads.

Canonical live context diagnostics policy is now owned by:

```text
docs/ssot/context-store.md
docs/ssot/context-keys.md
```

Context invalid-key diagnostics expose stable runtime-style diagnostics through:

```text
ContextInvalidKeyException::reason()
ContextInvalidKeyException::safeKey()
```

`reason()` returns a stable context key rejection reason token.

`safeKey()` returns only a conservative safe key segment, `<key>`, or `null`.

Unsafe rejected keys MUST NOT appear raw in exception messages.

Unsafe rejected keys are represented by:

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

Unsafe complete paths are represented by:

```text
<path>
```

Unsafe map keys inside otherwise safe value paths are represented by:

```text
[<key>]
```

Context exception messages MUST be constructed only from stable reason tokens and safe diagnostic segments.

Rejected raw values, unsafe raw keys, unsafe raw paths, raw map keys, object dumps, stack traces, credentials, tokens, cookies, authorization values, session ids, raw SQL, absolute local paths, and environment-specific bytes MUST NOT appear in context exception messages.

Canonical live correlation id read-side policy is owned by:

```text
docs/ssot/time-ids-and-duration.md
```

`CorrelationIdProvider` returns the current context correlation id only when the stored value is a string matching the canonical Foundation uppercase ULID-like format:

```text
/\A[0-9A-HJKMNP-TV-Z]{26}\z/
```

Malformed, empty, non-string, lowercase, token-like, cookie-like, SQL-like, URL-like, path-like, header-like, control-character-containing, or otherwise unsafe context values resolve to `null`.

`CorrelationIdProvider` MUST NOT normalize, uppercase, trim, rewrite, remove, replace, store, log, trace, emit, or generate correlation ids as a side effect of reading.

Historical wording in this ADR that allows generic `key` or `path-to-value` diagnostics should now be read as allowing only safe diagnostic segments under the 1.277.0 context diagnostics policy.

## Context

Coretsia runtimes need one stable way to read unit-of-work-local context across Foundation, Kernel, HTTP, logging, tracing, metrics, and future platform packages.

The contracts layer already defines a format-neutral context read port:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
```

The canonical accessor shape is:

```text
has(string $key): bool
get(string $key): mixed
```

`get()` deliberately has no default parameter. Callers use `has()` to distinguish an absent key from a present key whose value is `null`.

The contracts layer also defines:

```text
Coretsia\Contracts\Runtime\ResetInterface
Coretsia\Contracts\Observability\CorrelationIdProviderInterface
```

Foundation owns deterministic reset/tag infrastructure:

```text
Coretsia\Foundation\Tag\ReservedTags::KERNEL_RESET
Coretsia\Foundation\Tag\ReservedTags::KERNEL_STATEFUL
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
Coretsia\Foundation\Tag\TagRegistry
```

The corresponding reserved tag strings are:

```text
kernel.reset
kernel.stateful
```

The runtime needs a single mutable context store that is reset between units of work. Without a single store, package-local context stores would create drift, stale state leaks, and inconsistent reads between logging, tracing, HTTP, and kernel lifecycle code.

The runtime also needs a safe correlation id mechanism. The correlation id must be format-stable, safe to use for correlation, and not coupled to HTTP headers, transport propagation, or platform packages.

This ADR records the decision introduced by epic `1.210.0`.

## Decision

The runtime context model uses one public key registry and one Foundation-owned mutable store:

```text
Coretsia\Contracts\Context\ContextKeys
Coretsia\Foundation\Context\ContextBag
Coretsia\Foundation\Context\ContextStore
Coretsia\Foundation\Context\ContextStorePolicy
Coretsia\Foundation\Id\UlidGenerator
Coretsia\Foundation\Id\CorrelationIdGenerator
Coretsia\Foundation\Observability\CorrelationIdProvider
```

The normative SSoTs are:

```text
docs/ssot/context-keys.md
docs/ssot/context-store.md
```

## Decision 1: ContextKeys is the canonical public key registry

`Coretsia\Contracts\Context\ContextKeys` is the canonical public context key identifier registry.

It defines stable key vocabulary only.

Importing `ContextKeys` does not grant write ownership over context values.

The key registry is documented by:

```text
docs/ssot/context-keys.md
```

`ContextStorePolicy` MUST allow writes only for keys declared in `ContextKeys`.

Read-only consumers MAY import `ContextKeys` to avoid raw string key drift.

Unknown keys MUST be rejected deterministically.

Keys beginning with `@` MUST be rejected deterministically.

The `@*` namespace remains reserved for config directives and MUST NOT be used by runtime context keys.

The active baseline keys are:

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

The reserved future keys are:

```text
request_id
path_template
http_response_format
actor_id
tenant_id
```

Reserved future keys are allowed to exist in the registry to prevent naming drift, but concrete writers MAY be absent until the responsible owner package implements them.

## Decision 2: ContextStore is the single mutable runtime store

`Coretsia\Foundation\Context\ContextStore` is the single Foundation-owned mutable context store.

It MUST implement:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
Coretsia\Contracts\Runtime\ResetInterface
```

It MUST implement `get()` exactly as:

```text
get(string $key): mixed
```

It MUST NOT add a default parameter to `get()`.

`ContextStore` MUST distinguish absent keys from present `null` values through:

```text
has(string $key): bool
```

`ContextStore::reset()` MUST clear stored context deterministically between units of work.

## Decision 3: ContextBag is an immutable snapshot view

`Coretsia\Foundation\Context\ContextBag` is the immutable snapshot view of context values.

A `ContextBag` snapshot MUST NOT observe later mutations to `ContextStore`.

A `ContextBag` MUST provide read-only access.

Recommended public shape:

```text
has(string $key): bool
get(string $key): mixed
all(): array<string,mixed>
```

If `ContextBag` implements `ContextAccessorInterface`, it MUST use the same `get(string $key): mixed` signature and MUST NOT add a default parameter.

`ContextBag::all()` MUST return a copy of snapshot data.

## Decision 4: ContextStorePolicy is the always-on safe-write guard

`Coretsia\Foundation\Context\ContextStorePolicy` validates all writes.

It MUST validate:

- canonical key allowlist;
- reserved `@*` namespace rejection;
- JSON-safe deterministic values;
- nested arrays;
- deterministic safe failure messages.

The policy MUST NOT be feature-disabled through config.

No `foundation.context.*` config keys are introduced.

No `foundation.correlation.*` config keys are introduced.

## Decision 5: ContextStore accepts only JSON-safe deterministic values

`ContextStore` accepts only:

```text
null
bool
int
string
list<value>
array<string,value>
```

The value model is recursive.

Forbidden everywhere, including nested structures:

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

Finite floats are forbidden.

Objects are forbidden even if they are stringable.

Closures are forbidden as objects.

Resources and filesystem handles are forbidden.

Service instances and runtime wiring objects are forbidden.

Callable-ness is not a standalone ContextStore type rule.

Plain strings remain valid strings even when PHP could interpret them as callable names.

Example valid string:

```text
strlen
```

## Decision 6: Failure semantics are deterministic and safe

Forbidden writes MUST fail deterministically.

Failure messages MUST NOT include raw values.

Failure messages MAY include only stable safe metadata:

```text
key
path-to-value
reason code
safe type name
```

Recommended Foundation context exceptions are:

```text
Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException
Coretsia\Foundation\Context\Exception\ContextInvalidKeyException
```

If introduced, their canonical error codes are:

```text
CORETSIA_CONTEXT_WRITE_FORBIDDEN
CORETSIA_CONTEXT_INVALID_KEY
```

Foundation context exceptions are mapped or adapted by higher layers later.

This ADR does not introduce platform error mappers.

## Decision 7: Correlation id generation uses one canonical ULID source

`Coretsia\Foundation\Id\UlidGenerator` is introduced as the single canonical ULID implementation.

No second ULID implementation may be introduced.

`Coretsia\Foundation\Id\CorrelationIdGenerator` MUST receive `UlidGenerator` through constructor injection.

`CorrelationIdGenerator` MUST delegate generation to `UlidGenerator`.

`CorrelationIdGenerator` MUST NOT implement ULID entropy or encoding logic independently.

`CorrelationIdGenerator` MUST NOT post-process generated values in a way that can create format drift.

The canonical correlation id format is uppercase ULID:

```text
/\A[0-9A-HJKMNP-TV-Z]{26}\z/
```

Generated correlation ids are entropy-based, but their string format is deterministic.

## Decision 8: CorrelationIdProvider reads context and does not generate

`Coretsia\Foundation\Observability\CorrelationIdProvider` implements:

```text
Coretsia\Contracts\Observability\CorrelationIdProviderInterface
```

It reads the current correlation id from context.

It returns:

```text
non-empty-string|null
```

It SHOULD return `null` when no valid current correlation id is available.

It MUST NOT generate a correlation id as a side effect of reading.

Generation belongs to Kernel runtime at begin-UoW.

The provider MUST NOT define:

- HTTP header names;
- HTTP extraction policy;
- HTTP injection policy;
- W3C propagation policy;
- request objects;
- response objects;
- transport-specific behavior.

Transport-specific correlation policy is owned by platform packages.

## Decision 9: Provider wiring uses one ContextStore instance

Foundation provider wiring MUST register exactly one `ContextStore` object instance per built container.

The following service ids MUST point to the same object instance:

```text
Coretsia\Foundation\Context\ContextStore
Coretsia\Contracts\Context\ContextAccessorInterface
```

Foundation provider wiring MUST register:

```text
Coretsia\Foundation\Id\UlidGenerator
Coretsia\Foundation\Id\CorrelationIdGenerator
Coretsia\Foundation\Observability\CorrelationIdProvider
Coretsia\Contracts\Observability\CorrelationIdProviderInterface
```

The `CorrelationIdProvider` concrete service id and the `CorrelationIdProviderInterface` binding SHOULD point to the same object instance.

Foundation provider wiring SHOULD use explicit instances or factories.

Foundation provider wiring MUST NOT rely on concrete-class autowiring for baseline context services.

Baseline context services MUST remain resolvable even when Foundation concrete autowiring is disabled.

## Decision 10: ContextStore is stateful and reset-discovered

`ContextStore` is stateful.

It MUST be tagged with:

```text
kernel.stateful
```

Runtime package source MUST use:

```text
Coretsia\Foundation\Tag\ReservedTags::KERNEL_STATEFUL
```

It MUST also be tagged with the effective Foundation reset discovery tag.

The effective reset discovery tag is read from:

```text
foundation.reset.tag
```

The reserved default value is:

```text
kernel.reset
```

The canonical code-level identifier for this framework-reserved DI tag is:

```text
Coretsia\Foundation\Tag\ReservedTags::KERNEL_RESET
```

Provider tag wiring MUST use the same effective reset tag resolver used by `ResetOrchestrator`.

Provider tag wiring MUST NOT duplicate reset tag validation logic.

Runtime reset execution MUST discover `ContextStore` through the effective Foundation reset discovery tag, not through `kernel.stateful`.

`kernel.stateful` remains an enforcement marker.

## Consequences

### Positive

Runtime packages can read context through a stable contracts-level port:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
```

Foundation owns one mutable context store and one reset discipline.

Context snapshots are immutable through `ContextBag`.

Key names are controlled by `ContextKeys`, preventing uncontrolled key sprawl.

Unsafe values are rejected at write time.

Unknown keys are rejected deterministically.

The `@*` namespace remains reserved for config directives.

Correlation id format is stable.

ULID generation has a single source of truth.

Provider wiring remains safe when concrete autowiring is disabled.

ContextStore participates in reset orchestration and does not leak between units of work.

### Negative / trade-offs

Every new context key requires an SSoT update and contract-test update.

Arbitrary package-local keys are forbidden.

Floats are forbidden even when finite.

Objects and stringable objects are forbidden.

The store cannot be used as a general-purpose metadata bag.

HTTP header extraction and propagation remain out of scope, so platform packages own that behavior.

Kernel begin-UoW base context writes are owned by Kernel runtime.

## Rejected alternatives

### Alternative 1: Allow arbitrary context keys

Rejected.

Arbitrary keys would create uncontrolled key sprawl, naming drift, accidental high-cardinality fields, and increased risk of storing secrets or raw payloads.

The accepted design uses `ContextKeys` as the only allowlist.

### Alternative 2: Put defaults into `get()`

Rejected.

`ContextAccessorInterface::get(string $key): mixed` is locked without a default parameter.

Callers use `has()` to distinguish absent values from present `null`.

### Alternative 3: Use multiple package-local context stores

Rejected.

Multiple stores would make reads inconsistent between packages and could leak or lose unit-of-work-local context.

The accepted design requires one `ContextStore` instance per built container and binds the accessor interface to that same instance.

### Alternative 4: Generate correlation ids inside CorrelationIdProvider reads

Rejected.

Read access must not mutate runtime context.

Generation belongs to the unit-of-work owner.

The accepted provider only reads the current correlation id and returns `null` when absent.

### Alternative 5: Implement ULID logic in CorrelationIdGenerator

Rejected.

Duplicating ULID logic creates format drift risk.

The accepted design makes `UlidGenerator` the single canonical ULID implementation, and `CorrelationIdGenerator` delegates to it.

### Alternative 6: Use class-string autowire for baseline context services

Rejected.

Baseline services must resolve even when `foundation.container.autowire_concrete` or `foundation.container.allow_reflection_for_concrete` is disabled.

The accepted wiring uses explicit instances or factories.

### Alternative 7: Introduce `foundation.context.*` or `foundation.correlation.*` toggles

Rejected.

ContextStore, ContextStorePolicy, correlation id generation, and correlation id provider wiring are baseline runtime infrastructure.

They MUST NOT be feature-disabled through config.

Absence of optional writers/readers is represented by no writes/no reads.

## Security and redaction

ContextStore MUST prefer rejection over unsafe storage.

ContextStore MUST NOT store or expose:

- tokens;
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
- direct user identifiers;
- absolute local paths.

Safe ids may be stored only when the key policy explicitly allows them.

Safe ids MUST be opaque and MUST NOT encode email, phone, full name, username, customer name, domain, external account id, token, credential, or raw payload data.

## Observability impact

`correlation_id` is safe for correlation use.

It MAY be read by logging and tracing owner packages through:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
```

`correlation_id` MUST NOT be used as a metric label under the baseline observability policy.

Raw `path`, raw query, headers, cookies, Authorization values, tokens, session ids, and payloads MUST NOT be exported even if present in `ContextStore`.

When path-like observability data is needed, owners SHOULD use:

```text
path_template
hash(value)
len(value)
```

Any export to logs, spans, metrics, errors, profiling, health output, public diagnostics, or generated artifacts remains governed by:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
```

## Runtime lifecycle impact

All ContextStore values are unit-of-work-local.

Kernel runtime MUST set base keys at begin-UoW:

```text
correlation_id
uow_id
uow_type
```

At or after the end of a unit of work, Foundation reset orchestration MUST clear ContextStore before the next unit of work can observe stale context.

The accepted lifecycle is:

1. unit of work begins;
2. Kernel owner code sets base keys;
3. optional owner packages write only declared safe keys;
4. readers access context through `ContextAccessorInterface`;
5. unit of work finishes;
6. reset orchestration discovers `ContextStore` through the effective Foundation reset tag;
7. `ContextStore::reset()` clears context;
8. the next unit of work starts with no stale context.

## Boundaries

This ADR does not introduce:

- config roots;
- config keys;
- generated artifacts;
- package-level PHPUnit config;
- platform HTTP writers;
- HTTP header extraction;
- HTTP header injection;
- W3C propagation behavior;
- middleware-to-context-key FQCN matrix;
- logging backend behavior;
- tracing backend behavior;
- metrics backend behavior;
- platform error mappers;
- skeleton defaults;
- a second ULID implementation.

The detailed middleware-to-context-key map is out of scope for this ADR and is owned by:

```text
docs/ssot/middleware-context-keys-map.md
```

## Verification

Expected verification includes:

```text
framework/packages/core/foundation/tests/Unit/ContextBagImmutabilityTest.php
framework/packages/core/foundation/tests/Unit/CorrelationIdGeneratorDelegatesToUlidGeneratorTest.php
framework/packages/core/foundation/tests/Unit/CorrelationIdFormatTest.php
framework/packages/core/contracts/tests/Contract/ContextKeysAreStableContractTest.php
framework/packages/core/foundation/tests/Contract/CorrelationIdFormatContractTest.php
framework/packages/core/foundation/tests/Contract/ContextAccessorSignatureContractTest.php
framework/packages/core/foundation/tests/Integration/ContextStoreResetClearsContextTest.php
framework/packages/core/foundation/tests/Integration/ContextStoreSafeWriteGuardBlocksForbiddenKeysTest.php
framework/packages/core/foundation/tests/Integration/ContextStoreRejectsAtPrefixedKeysTest.php
framework/packages/core/foundation/tests/Integration/ContextStoreRejectsUnknownKeysTest.php
framework/packages/core/foundation/tests/Integration/ContextStoreRejectsFloatValuesTest.php
framework/packages/core/foundation/tests/Integration/ContextStoreRejectsObjectValuesTest.php
framework/packages/core/foundation/tests/Integration/ContextStoreRejectsResourceValuesTest.php
framework/packages/core/foundation/tests/Integration/ContextStoreRejectsNonStringMapKeysTest.php
framework/packages/core/foundation/tests/Integration/ContextStoreIsTaggedKernelStatefulTest.php
framework/packages/core/foundation/tests/Integration/ContextStoreIsTaggedWithEffectiveResetTagTest.php
framework/packages/core/foundation/tests/Contract/ContextInvalidKeyDiagnosticsAreSafeContractTest.php
framework/packages/core/foundation/tests/Contract/ContextWriteForbiddenDiagnosticsAreSafeContractTest.php
framework/packages/core/foundation/tests/Contract/ContextStorePolicyUsesJsonLikeNormalizerContractTest.php
framework/packages/core/foundation/tests/Integration/ContextStoreSafeWriteGuardBlocksForbiddenKeysTest.php
framework/packages/core/foundation/tests/Integration/CorrelationIdProviderReadsContextStoreTest.php
framework/packages/core/foundation/tests/Integration/CorrelationIdProviderRejectsUnsafeCorrelationIdsTest.php
```

Verification MUST prove:

- `ContextKeys` list matches SSoT;
- `ContextAccessorInterface::get(string $key): mixed` remains unchanged;
- `ContextBag` snapshots are immutable;
- `ContextStore::reset()` clears context;
- unknown keys are rejected;
- `@*` keys are rejected;
- floats, `NaN`, `INF`, and `-INF` are rejected;
- objects are rejected;
- resources are rejected;
- non-string map keys are rejected;
- callable strings remain accepted as strings;
- error messages do not expose raw values;
- `ContextStore` is tagged with `kernel.stateful` through `ReservedTags::KERNEL_STATEFUL`;
- `ContextStore` is tagged with the effective Foundation reset discovery tag;
- provider wiring uses the same `ContextStore` instance for concrete and accessor bindings;
- correlation id format is stable uppercase ULID;
- `CorrelationIdGenerator` delegates to `UlidGenerator`.
- unsafe rejected context keys are represented by `<key>`;
- safe rejected context keys may remain visible only under conservative safe-key rules;
- context invalid-key diagnostics expose stable `reason()` and safe `safeKey()`;
- context write-forbidden diagnostics expose stable `reason()` and safe `safePath()`;
- unsafe complete write paths are represented by `<path>`;
- unsafe map keys inside safe value paths are represented by `[<key>]`;
- rejected raw values do not appear in context write-forbidden messages;
- `CorrelationIdProvider` returns only canonical uppercase ULID-like correlation ids;
- `CorrelationIdProvider` returns `null` for malformed or unsafe context values;
- `CorrelationIdProvider` does not generate, normalize, mutate, log, trace, or emit malformed values.

## Related SSoT

- `docs/ssot/context-store.md`
- `docs/ssot/context-keys.md`
- `docs/ssot/config-and-env.md`
- `docs/ssot/http-middleware-catalog.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/tags.md`
- `docs/ssot/uow-and-reset-contracts.md`

## Related epic

- `1.210.0 Foundation: ContextBag + ContextStore + CorrelationId`
