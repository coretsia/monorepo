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

# Context Store SSoT

## Scope

This document is the Single Source of Truth for Coretsia Foundation runtime context storage, immutable context snapshots, controlled context mutation, safe-write policy, value validation, reset discipline, and correlation id storage boundaries.

This document governs the Foundation runtime implementation introduced under:

```text
framework/packages/core/foundation/src/Context/
framework/packages/core/foundation/src/Id/
framework/packages/core/foundation/src/Observability/CorrelationIdProvider.php
```

It complements:

```text
docs/ssot/context-keys.md
docs/ssot/config-and-env.md
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/tags.md
docs/ssot/uow-and-reset-contracts.md
docs/ssot/http-middleware-catalog.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Runtime layers must read context through one stable format-neutral access port, while Foundation owns one mutable runtime context store that is reset between units of work.

The Foundation context model provides:

- one runtime `ContextStore`;
- one immutable snapshot view, `ContextBag`;
- one safe-write policy, `ContextStorePolicy`;
- one canonical key registry, `ContextKeys`;
- one canonical ULID source, `UlidGenerator`;
- one correlation id generator delegating to the ULID source;
- one correlation id provider reading the current context.

## Ownership

The context store implementation is owned by package:

```text
core/foundation
```

The canonical implementation files are:

```text
framework/packages/core/foundation/src/Context/ContextKeys.php
framework/packages/core/foundation/src/Context/ContextBag.php
framework/packages/core/foundation/src/Context/ContextStore.php
framework/packages/core/foundation/src/Context/ContextStorePolicy.php
framework/packages/core/foundation/src/Id/UlidGenerator.php
framework/packages/core/foundation/src/Id/CorrelationIdGenerator.php
framework/packages/core/foundation/src/Observability/CorrelationIdProvider.php
```

`core/foundation` depends on `core/contracts` for stable ports and reset contracts.

`core/foundation` MUST NOT depend on:

```text
platform/*
integrations/*
```

## Contract ports

`ContextStore` MUST implement:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
Coretsia\Contracts\Runtime\ResetInterface
```

The canonical `ContextAccessorInterface` shape is:

```text
has(string $key): bool
get(string $key): mixed
```

`ContextStore` MUST implement `get()` exactly as:

```text
get(string $key): mixed
```

`ContextStore` MUST NOT add a default parameter to `get()`.

`CorrelationIdProvider` MUST implement:

```text
Coretsia\Contracts\Observability\CorrelationIdProviderInterface
```

The canonical provider method is:

```text
correlationId(): ?string
```

The provider reads the current correlation id from context. It does not define HTTP header names, propagation format, request objects, response objects, or transport policy.

## Single store rule

There MUST be exactly one Foundation-owned `ContextStore` instance per built container.

The DI provider MUST register the same object instance for:

```text
Coretsia\Foundation\Context\ContextStore
Coretsia\Contracts\Context\ContextAccessorInterface
```

Runtime packages that read context MUST depend on:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
```

Runtime packages MUST NOT create independent context stores for the same runtime boundary.

A second store would make context reads non-deterministic and could leak or lose unit-of-work-local data.

## ContextStore responsibilities

`ContextStore` is the mutable Foundation-owned context store.

It MUST:

- store only values accepted by `ContextStorePolicy`;
- allow writes only for keys declared by `ContextKeys`;
- distinguish an absent key from a present key with `null` through `has()`;
- return stored values through `get(string $key): mixed`;
- expose an immutable snapshot through `ContextBag`;
- clear all stored values through `reset()`;
- be resettable between units of work;
- avoid emitting diagnostics, stdout, stderr, logs, metrics, traces, or artifacts.

`ContextStore` MUST NOT:

- store secrets;
- store raw transport objects;
- store direct user identifiers;
- store raw headers;
- store raw cookies;
- store raw request bodies;
- store raw response bodies;
- store raw SQL;
- expose mutable internal arrays through read APIs;
- depend on platform packages or integration packages.

## ContextBag responsibilities

`ContextBag` is an immutable snapshot view of context values.

A `ContextBag` snapshot MUST NOT observe later mutations to `ContextStore`.

A `ContextBag` MUST provide read-only access.

Recommended public shape:

```text
has(string $key): bool
get(string $key): mixed
all(): array<string,mixed>
```

If `ContextBag` implements `ContextAccessorInterface`, it MUST implement `get()` exactly as:

```text
get(string $key): mixed
```

with no default parameter.

`ContextBag::all()` MUST return a copy of snapshot data.

Consumers MUST NOT be able to mutate the stored snapshot by mutating the returned array.

## ContextStorePolicy responsibilities

`ContextStorePolicy` is the always-on safety guard for context writes.

It MUST validate:

- key allowlist;
- reserved `@*` key namespace rejection;
- value model;
- nested value model;
- deterministic safe failure messages.

`ContextStorePolicy` MUST allow writes only for keys declared by:

```text
Coretsia\Foundation\Context\ContextKeys
```

Any attempt to write an unknown key MUST fail deterministically.

Any attempt to write a key beginning with `@` MUST fail deterministically.

The failure message MUST NOT include raw values.

The failure message MAY include:

```text
key
path-to-value
stable reason code
safe type name
```

## Key allowlist

`ContextStore` and `ContextStorePolicy` MUST use `ContextKeys` as the only key allowlist.

The canonical key registry is owned by:

```text
docs/ssot/context-keys.md
```

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

Reserved future keys MAY be accepted by the store because they are declared in `ContextKeys`, but their concrete writers MAY be absent until later owner epics.

## Reserved `@*` namespace

Context keys MUST NOT start with `@`.

The `@*` namespace is reserved for config directives.

This rule aligns runtime context with:

```text
docs/ssot/config-and-env.md
```

Examples of forbidden context keys:

```text
@foo
@append
@replace
@env
```

Such keys MUST fail deterministically before any value is stored.

## Value model

`ContextStore` MUST accept only JSON-safe deterministic value types.

Allowed scalar values:

```text
null
bool
int
string
```

Allowed compound values:

```text
list<value>
array<string,value>
```

Where `value` recursively means any value accepted by this model.

## Array model

PHP arrays are interpreted as either lists or maps.

A PHP array is a list when:

```text
array_is_list($value) === true
```

List indexes are allowed to be integers because the list shape is ordered by position.

A PHP array is a map when:

```text
array_is_list($value) === false
```

Map keys MUST be strings.

A map with any non-string key MUST be rejected.

Nested lists and nested maps MUST obey the same rules recursively.

List order MUST be preserved.

Map ordering is not a semantic guarantee of ContextStore reads, but any future exported diagnostic representation MUST use deterministic key ordering by byte-order `strcmp`.

## Empty arrays

An empty PHP array is allowed.

At the ContextStore boundary, an empty array MAY be treated as an empty list for PHP storage purposes.

Consumers MUST NOT rely on empty array list-vs-map distinction unless a future typed model explicitly introduces that distinction.

## Forbidden values

The following values are forbidden everywhere, including nested structures:

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

Objects are forbidden even if they are stringable.

Closures are forbidden as objects.

Resources are forbidden.

Streams and filesystem handles are forbidden.

Service instances and runtime wiring objects are forbidden.

Floating-point values are forbidden even when finite.

If a future owner needs decimal data, it MUST be represented as a string with a documented safe format.

## Callable strings

Callable-ness is not a standalone ContextStore type rule.

Plain strings remain valid strings even if PHP could interpret them as callable names.

Example valid string:

```text
strlen
```

`ContextStorePolicy` MUST NOT reject a string merely because it names a PHP function, method, class, or callable-like value.

## Deterministic failure semantics

Forbidden writes MUST fail deterministically.

Implementations SHOULD use Foundation context exceptions such as:

```text
Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException
Coretsia\Foundation\Context\Exception\ContextInvalidKeyException
```

If those exceptions are introduced, their canonical error codes are:

```text
CORETSIA_CONTEXT_WRITE_FORBIDDEN
CORETSIA_CONTEXT_INVALID_KEY
```

Failure messages MUST be safe.

Failure messages MUST NOT include raw values.

Failure messages MAY include only stable metadata such as:

```text
key
path-to-value
reason code
safe type name
```

Examples of allowed message fragments:

```text
context key is not declared: unknown_key
context key uses reserved namespace: @foo
context value rejected at metadata.items[3].count: float
context value rejected at payload.actor: object
```

Examples of forbidden message fragments:

```text
secret-token-value
user@example.com
Authorization: Bearer ...
raw request body
```

## Path-to-value notation

When an error identifies a nested value, it MAY include a stable path-to-value.

Recommended path notation:

```text
metadata.items[3].count
```

Rules:

- map segments use dot notation;
- list indexes use bracket notation;
- the path MUST NOT include raw values;
- the path MUST be deterministic for the same input shape.

## Forbidden sensitive data

ContextStore MUST NOT store secrets or sensitive payload material.

Forbidden sensitive data includes:

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

Safe ids MAY be stored only when the key policy explicitly allows them.

Safe ids MUST be opaque and MUST NOT encode direct user identifiers, customer names, domains, tokens, credentials, or raw payload data.

## Request metadata

Request metadata MAY be stored only through declared context keys and accepted safe values.

Allowed request metadata keys are governed by:

```text
docs/ssot/context-keys.md
```

Potentially sensitive request metadata such as `client_ip` SHOULD be normalized or hashed by writers when feasible.

The existence of request metadata keys MUST NOT be interpreted as permission to store raw headers, cookies, Authorization values, request bodies, response bodies, raw query strings, or session ids.

Raw `path` is allowed only as in-process context when deliberately written by an owner.

Raw `path` MUST NOT be emitted to logs, spans, metrics, public diagnostics, generated artifacts, or error descriptor extensions.

When path-like observability data is needed, owners SHOULD use:

```text
path_template
hash(value)
len(value)
```

## Correlation id storage

`correlation_id` is a safe opaque id for correlation use.

The canonical generated format is ULID:

```text
/\A[0-9A-HJKMNP-TV-Z]{26}\z/
```

Generated correlation ids MUST be uppercase.

`CorrelationIdGenerator` MUST receive:

```text
Coretsia\Foundation\Id\UlidGenerator
```

through constructor injection.

`CorrelationIdGenerator` MUST delegate generation to `UlidGenerator`.

`CorrelationIdGenerator` MUST NOT implement ULID logic independently.

`CorrelationIdGenerator` MUST NOT post-process generated ULIDs in a way that can create format drift.

## Single-source ULID rule

`Coretsia\Foundation\Id\UlidGenerator` is the single canonical ULID implementation in the codebase.

No second ULID implementation may be introduced.

Any Foundation or runtime code needing a generated ULID MUST use `UlidGenerator` directly or through an explicit delegating wrapper such as `CorrelationIdGenerator`.

## CorrelationIdProvider

`CorrelationIdProvider` reads the current correlation id from context.

It MUST implement:

```text
Coretsia\Contracts\Observability\CorrelationIdProviderInterface
```

It MUST return:

```text
non-empty-string|null
```

It SHOULD return `null` when no valid current correlation id is available.

It MUST NOT generate a new correlation id as a side effect of reading.

Generation belongs to the unit-of-work owner, such as the later Kernel runtime integration.

It MUST NOT define HTTP header extraction, HTTP header injection, or propagation policy.

Transport-specific correlation policy is owned by platform packages.

## Unit-of-work lifecycle

All ContextStore state is unit-of-work-local.

A unit of work may be:

```text
HTTP request
CLI command invocation
worker job
queue message
scheduler tick
custom runtime boundary
```

At begin-UoW, a later Kernel runtime integration MUST set base keys:

```text
correlation_id
uow_id
uow_type
```

At or after the end of a unit of work, reset orchestration MUST clear ContextStore before the next unit of work can observe stale context.

The acceptance scenario is:

1. one unit of work starts;
2. owner code writes safe context keys;
3. runtime packages read context through `ContextAccessorInterface`;
4. the unit of work finishes;
5. Foundation reset orchestration calls `ContextStore::reset()`;
6. the next unit of work starts with an empty store except new base keys set by Kernel.

## Reset discipline

`ContextStore` MUST implement:

```text
Coretsia\Contracts\Runtime\ResetInterface
```

`ContextStore::reset()` MUST clear all stored context deterministically.

Calling `reset()` SHOULD be idempotent.

`reset()` MUST NOT require transport objects, request objects, response objects, CLI input/output objects, queue messages, worker contexts, scheduler contexts, or service container objects.

`reset()` MUST NOT emit stdout or stderr.

`reset()` MUST NOT log raw context data.

## DI wiring

Foundation provider wiring MUST register:

```text
Coretsia\Foundation\Context\ContextStore
Coretsia\Contracts\Context\ContextAccessorInterface
Coretsia\Foundation\Id\UlidGenerator
Coretsia\Foundation\Id\CorrelationIdGenerator
Coretsia\Foundation\Observability\CorrelationIdProvider
Coretsia\Contracts\Observability\CorrelationIdProviderInterface
```

The `ContextStore` concrete service id and the `ContextAccessorInterface` binding MUST point to the same object instance.

The `CorrelationIdProvider` concrete service id and the `CorrelationIdProviderInterface` binding SHOULD point to the same object instance.

Foundation provider wiring SHOULD use explicit instances or factories.

Foundation provider wiring MUST NOT rely on concrete-class autowiring for baseline context services.

Baseline context services MUST remain resolvable even when concrete autowiring is disabled by Foundation container config.

## DI tags

`ContextStore` is stateful.

`ContextStore` MUST be tagged with:

```text
kernel.stateful
```

`ContextStore` MUST also be tagged with the effective Foundation reset discovery tag.

The effective Foundation reset discovery tag is resolved from:

```text
foundation.reset.tag
```

The reserved default is:

```text
kernel.reset
```

Provider tag wiring MUST use the same effective reset tag resolver used by `ResetOrchestrator`.

Provider tag wiring MUST NOT duplicate reset tag validation logic.

Runtime reset execution MUST discover ContextStore through the effective reset discovery tag, not through `kernel.stateful`.

`kernel.stateful` is an enforcement marker.

## Configuration policy

This epic introduces no config root and no config keys.

The following config keys MUST NOT be introduced by this epic:

```text
foundation.context.*
foundation.correlation.*
```

ContextStore is baseline runtime infrastructure.

ContextStore MUST NOT be feature-disabled through config.

Correlation id generation and provider wiring are baseline runtime infrastructure.

They MUST NOT be feature-disabled through config.

Absence of optional writers/readers is represented by:

```text
no writes
no reads
```

It MUST NOT be represented by disabling Foundation context services.

`ContextStorePolicy` is baseline runtime safety.

It MUST always be enabled and MUST NOT be feature-disabled through config.

## Artifact policy

This epic introduces no artifacts.

ContextStore MUST NOT write generated artifacts.

ContextStore MUST NOT serialize raw context into generated artifacts.

A future owner MAY introduce safe artifacts only through its own artifact SSoT and owner epic.

## HTTP boundary

HTTP writing is not implemented by this epic.

HTTP layer packages MAY later write only safe keys declared in `ContextKeys` and accepted by `ContextStorePolicy`.

HTTP writers MUST NOT write:

- raw headers;
- raw cookies;
- Authorization values;
- tokens;
- session ids;
- request bodies;
- response bodies;
- raw query strings;
- raw payloads.

Transport-specific correlation header extraction and injection policy is owned by `platform/http`, not by `core/foundation`.

The canonical middleware catalog reference is:

```text
docs/ssot/http-middleware-catalog.md
```

The detailed middleware-to-context-key map is not introduced by this epic and is owned only by a later HTTP owner epic.

## Observability boundary

Context values MAY be read by logging and tracing owners through:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
```

Any export to logs, spans, metrics, errors, profiling, health output, public diagnostics, or generated artifacts remains governed by:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
```

`correlation_id` is safe for correlation use and MAY be logged or used for tracing correlation according to owner policy.

`correlation_id` MUST NOT be used as a metric label under the baseline observability policy.

The following keys MUST NOT be metric labels under the baseline policy:

```text
correlation_id
request_id
tenant_id
user_id
path
```

Metric labels MUST remain within the canonical allowlist from `docs/ssot/observability.md`.

## Errors and exception mapping

Foundation context exceptions, if introduced, are Foundation runtime exceptions.

Higher layers may map or adapt them later.

This epic MUST NOT introduce platform error mappers.

This epic MUST NOT duplicate mapping behavior owned by future `platform/errors` or other platform packages.

Context exception messages MUST be safe and deterministic.

Context exception messages MUST NOT expose raw context values.

## Security and redaction

ContextStore MUST prefer rejection over unsafe storage.

ContextStore MUST NOT leak:

- `.env` values;
- passwords;
- credentials;
- tokens;
- private keys;
- cookies;
- Authorization headers;
- session identifiers;
- auth identifiers;
- request bodies;
- response bodies;
- raw payloads;
- raw SQL;
- profile payloads;
- private customer data;
- direct user identifiers;
- absolute local paths.

Allowed safe diagnostics MAY use:

```text
hash(value)
len(value)
type(value)
path-to-value
```

Safe diagnostics MUST NOT allow reconstruction of raw values.

Redaction MUST NOT be bypassed because a sink is internal.

## Determinism

ContextStore behavior MUST be deterministic for the same sequence of writes, reads, snapshots, and resets.

Deterministic requirements:

- stable key allowlist;
- stable value validation;
- stable forbidden-value rejection;
- stable safe error messages;
- stable reset behavior;
- stable snapshot immutability;
- stable absence/null distinction through `has()`.

Runtime behavior MUST NOT depend on:

- process locale;
- filesystem casing;
- filesystem traversal order;
- host platform;
- timestamps, except generated id entropy/time content inside ULID generation;
- random values, except generated id entropy inside ULID generation.

Generated correlation id values are entropy-based, but their string format MUST remain deterministic.

## Verification evidence

Expected verification includes:

```text
framework/packages/core/foundation/tests/Unit/ContextBagImmutabilityTest.php
framework/packages/core/foundation/tests/Unit/CorrelationIdGeneratorDelegatesToUlidGeneratorTest.php
framework/packages/core/foundation/tests/Unit/CorrelationIdFormatTest.php
framework/packages/core/foundation/tests/Contract/ContextKeysAreStableContractTest.php
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
```

These tests are expected to verify:

- key list stability;
- `get(string $key): mixed` signature stability;
- snapshot immutability;
- reset clears context;
- unknown keys are rejected;
- `@*` keys are rejected;
- floats, `NaN`, `INF`, and `-INF` are rejected;
- objects are rejected;
- resources are rejected;
- non-string map keys are rejected;
- error messages do not expose raw values;
- ContextStore is tagged with `kernel.stateful`;
- ContextStore is tagged with the effective Foundation reset discovery tag;
- correlation id format is stable;
- `CorrelationIdGenerator` delegates to `UlidGenerator`.

## Acceptance scenario

When a long-running runtime processes two units of work in the same process:

1. the first unit of work begins;
2. Kernel owner code sets `correlation_id`, `uow_id`, and `uow_type`;
3. optional runtime owners write only declared safe context keys;
4. readers access context through `ContextAccessorInterface`;
5. the first unit of work finishes;
6. reset orchestration discovers `ContextStore` through the effective Foundation reset tag;
7. `ContextStore::reset()` clears all context;
8. the second unit of work begins;
9. the second unit of work observes no stale context from the first unit of work;
10. Kernel owner code sets fresh base keys for the second unit of work.

## Non-goals

This SSoT does not define:

- concrete Kernel runtime lifecycle implementation;
- concrete HTTP middleware writers;
- middleware-to-key FQCN matrix;
- HTTP correlation header extraction;
- HTTP correlation header injection;
- transport-specific propagation policy;
- platform logging implementation;
- platform tracing implementation;
- platform metrics implementation;
- platform error mapping;
- generated artifacts;
- config roots;
- config keys;
- feature toggles;
- skeleton defaults;
- business context models;
- user profile models;
- session storage;
- auth identity storage;
- tenancy implementation.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Context Keys SSoT](./context-keys.md)
- [Config and env SSoT](./config-and-env.md)
- [HTTP Middleware Catalog SSoT](./http-middleware-catalog.md)
- [Observability Naming and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [Tag Registry](./tags.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
