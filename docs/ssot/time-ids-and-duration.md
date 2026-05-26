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

# Time, IDs, and Duration SSoT

## Scope

This document is the Single Source of Truth for Coretsia Foundation runtime time access, id generation, and duration measurement policy.

This document governs runtime services introduced by epic `1.220.0` under:

```text
framework/packages/core/foundation/src/Clock/
framework/packages/core/foundation/src/Id/
framework/packages/core/foundation/src/Time/
```

It also governs the Foundation-owned configuration key:

```text
foundation.ids.default
```

This epic does not introduce `foundation.clock.*`.

The runtime clock binding is fixed to:

```text
Psr\Clock\ClockInterface -> Coretsia\Foundation\Clock\SystemClock
```

It complements:

```text
docs/ssot/config-roots.md
docs/ssot/config-and-env.md
docs/ssot/context-keys.md
docs/ssot/context-store.md
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/uow-and-reset-contracts.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Runtime packages need one canonical place for obtaining:

- current time;
- deterministic-format safe ids;
- duration measurements expressed as integer milliseconds.

The Foundation time/id/duration baseline exists to prevent:

- duplicated time sources;
- duplicated id generation logic;
- drift between ULID/UUID formats;
- float-based duration measurement;
- nondeterministic or timezone-dependent time formatting;
- accidental use of high-cardinality ids as metric labels;
- correlation id format drift.

## Ownership

The owner package is:

```text
core/foundation
```

The implementation paths are:

```text
framework/packages/core/foundation/src/Clock/
framework/packages/core/foundation/src/Id/
framework/packages/core/foundation/src/Time/
```

The defaults file is owned by `core/foundation`:

```text
framework/packages/core/foundation/config/foundation.php
```

The rules file is owned by `core/foundation`:

```text
framework/packages/core/foundation/config/rules.php
```

No new package is introduced for time, ids, or duration utilities.

## Package dependency policy

`core/foundation` MAY depend on:

```text
psr/clock
```

`core/foundation` MUST NOT depend on:

```text
platform/*
integrations/*
```

This epic introduces no new `core/contracts` port.

The PSR-20 clock boundary is:

```text
Psr\Clock\ClockInterface
```

## Config root policy

This document introduces no new config root.

The existing config root remains:

```text
foundation
```

The defaults file MUST return the `foundation` subtree only and MUST NOT repeat the root wrapper.

Valid shape:

```php
return [
    'ids' => [
        'default' => 'ulid',
    ],
];
```

Invalid shape:

```php
return [
    'foundation' => [
        'ids' => [
            'default' => 'ulid',
        ],
    ],
];
```

Runtime code reads default id generator selection from merged global configuration under:

```text
foundation.ids.default
```

Runtime code MUST NOT read `foundation.clock.*` in this epic.

## Canonical config keys

This SSoT defines the following Foundation-owned config key.

| Key                      | Default | Allowed values | Meaning                                      |
|--------------------------|---------|----------------|----------------------------------------------|
| `foundation.ids.default` | `ulid`  | `ulid`, `uuid` | Selects the default `IdGeneratorInterface`.  |

This epic does not introduce:

```text
foundation.clock.*
```

The canonical runtime clock binding is fixed to:

```text
Psr\Clock\ClockInterface -> Coretsia\Foundation\Clock\SystemClock
```

`foundation.ids.default` selects only:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

It MUST NOT affect:

```text
Coretsia\Foundation\Id\CorrelationIdGenerator
Coretsia\Foundation\Observability\CorrelationIdProvider
```

The following config keys MUST NOT be introduced by this epic:

```text
foundation.clock.*
foundation.correlation.*
foundation.request_id.*
foundation.time.*
foundation.duration.*
```

## Config validation policy

`framework/packages/core/foundation/config/rules.php` MUST enforce:

```text
foundation.ids.default ∈ {ulid, uuid}
```

The rules file MUST reject unknown keys under:

```text
foundation.ids.*
```

The rules file MUST reject any float value under:

```text
foundation.ids.*
```

including nested float values if nested values are ever introduced.

Float rejection includes:

```text
NaN
INF
-INF
```

where representable in validation fixtures.

Failure messages MUST be deterministic and safe.

Failure messages MUST NOT dump raw config values.

Failure messages MAY include only safe metadata such as:

```text
config path
stable reason code
safe type name
```

No numeric config values are introduced by this epic.

This epic does not introduce `foundation.clock.*`, so clock binding validation is not config-driven.

## Clock policy

The canonical runtime clock binding is:

```text
Psr\Clock\ClockInterface
```

SystemClock is the baseline runtime `ClockInterface` binding.

The Foundation provider MUST bind:

```text
Psr\Clock\ClockInterface -> Coretsia\Foundation\Clock\SystemClock
```

This binding is not selected through runtime config in this epic.

This epic MUST NOT introduce:

```text
foundation.clock.*
```

Foundation provides the system implementation:

```text
Coretsia\Foundation\Clock\SystemClock
```

`SystemClock` MUST implement:

```text
Psr\Clock\ClockInterface
```

`SystemClock::now()` MUST return:

```text
DateTimeImmutable
```

The returned time MUST be in UTC.

`SystemClock` MUST NOT assert monotonicity.

System time may jump forward or backward because it is controlled by the operating system and host environment.

Runtime code that needs duration measurement MUST use `Stopwatch`, not `SystemClock` differences.

## Frozen clock policy

Foundation provides a deterministic frozen clock:

```text
Coretsia\Foundation\Clock\FrozenClock
```

`FrozenClock` MUST implement:

```text
Psr\Clock\ClockInterface
```

`FrozenClock::now()` MUST return a deterministic `DateTimeImmutable` value.

Repeated calls to `FrozenClock::now()` MUST return the same logical instant unless the test owner deliberately constructs or replaces the frozen clock with a different instant.

`FrozenClock` exists for tests, fixtures, and deterministic test/support scenarios.

FrozenClock is test/support infrastructure and is not selected through runtime config in this epic.

`FrozenClock` MUST NOT be selected by any `foundation.clock.*` config key.

This epic does not introduce a runtime clock driver config key.

## ID generation policy

Foundation provides one canonical runtime id abstraction:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

The canonical method shape is:

```text
generate(): string
```

Generated ids MUST be safe opaque identifiers.

Generated ids MUST NOT be derived from secrets, credentials, raw payloads, direct user identifiers, cookies, sessions, authorization values, raw headers, raw request bodies, raw response bodies, raw SQL, or private customer data.

Supported id generators are a code-level capability.

Runtime default selection is controlled only through:

```text
foundation.ids.default
```

The supported default id generator values are:

```text
ulid
uuid
```

The default value is:

```text
ulid
```

## ULID policy

Foundation already owns the canonical ULID implementation:

```text
Coretsia\Foundation\Id\UlidGenerator
```

`UlidGenerator` is the single ULID implementation source in the codebase.

Other Foundation services that need ULID format MUST delegate to `UlidGenerator`.

Other packages MUST NOT introduce another ULID implementation.

Canonical ULID format is uppercase Crockford Base32:

```text
/\A[0-9A-HJKMNP-TV-Z]{26}\z/
```

ULIDs generated by Foundation MUST be uppercase.

ULID strings are deterministic-format values.

ULID values themselves are entropy-based and MUST NOT be treated as deterministic content.

## UUID policy

Foundation provides an optional UUID generator:

```text
Coretsia\Foundation\Id\UuidGenerator
```

`UuidGenerator` MUST generate canonical textual UUID values.

Canonical UUID format is lowercase hexadecimal with hyphens:

```text
/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/
```

`UuidGenerator` MAY be selected as the default runtime id generator through:

```text
foundation.ids.default = uuid
```

UUID support MUST NOT introduce a second ULID implementation.

UUID support MUST NOT alter correlation id generation.

## Default id generator binding

The Foundation provider MUST bind:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

to the configured default generator:

| `foundation.ids.default` | Binding target                         |
|--------------------------|----------------------------------------|
| `ulid`                   | `Coretsia\Foundation\Id\UlidGenerator` |
| `uuid`                   | `Coretsia\Foundation\Id\UuidGenerator` |

The default binding is:

```text
Coretsia\Foundation\Id\IdGeneratorInterface -> Coretsia\Foundation\Id\UlidGenerator
```

`foundation.ids.default` MUST NOT be interpreted as a feature flag.

It selects only the default id generator service for generic runtime ids such as future `uow_id` or other safe ids.

## Correlation id boundary

Correlation id behavior is governed by epic `1.210.0`.

Foundation provides:

```text
Coretsia\Foundation\Id\CorrelationIdGenerator
Coretsia\Foundation\Observability\CorrelationIdProvider
```

`CorrelationIdGenerator` MUST delegate to:

```text
Coretsia\Foundation\Id\UlidGenerator
```

`CorrelationIdGenerator` MUST NOT depend on:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

`CorrelationIdGenerator` MUST NOT switch to UUID when:

```text
foundation.ids.default = uuid
```

`CorrelationIdProvider` is a read provider.

It reads only the current value stored under the canonical context key:

```text
correlation_id
```

The canonical Foundation correlation id format accepted by `CorrelationIdProvider` is uppercase ULID-like Crockford Base32:

```text
/\A[0-9A-HJKMNP-TV-Z]{26}\z/
```

`CorrelationIdProvider` MUST return the current context correlation id only when the stored value is a string matching that canonical format.

`CorrelationIdProvider` MUST return `null` when the context value is absent, empty, non-string, lowercase, mixed-case, malformed, token-like, cookie-like, SQL-like, URL-like, path-like, header-like, control-character-containing, or otherwise unsafe.

`CorrelationIdProvider` MUST NOT normalize unsafe input.

`CorrelationIdProvider` MUST NOT uppercase, trim, rewrite, remove, replace, or store context values while reading.

`CorrelationIdProvider` MUST NOT generate ids as a side effect of reading.

It MUST NOT be affected by `foundation.ids.default`.

`correlation_id` remains ULID-backed per `1.210.0`.

The following config keys MUST NOT be introduced:

```text
foundation.correlation.*
```

## Request id boundary

This epic does not define HTTP request id policy.

This epic does not define request id header extraction.

This epic does not define request id header injection.

This epic does not define HTTP propagation policy.

This epic only provides reusable id generation services.

HTTP request id ownership belongs to a later HTTP owner epic.

The following config keys MUST NOT be introduced by this epic:

```text
foundation.request_id.*
http.request_id.*
```

## Stopwatch policy

Foundation provides the canonical runtime stopwatch:

```text
Coretsia\Foundation\Time\Stopwatch
```

`Stopwatch` is canonical Foundation runtime infrastructure.

It MUST be resolvable whenever `core/foundation` is enabled.

`Stopwatch` MUST NOT be feature-disabled through config.

Absence of duration measurement in a consumer is represented by:

```text
consumer does not call Stopwatch
```

It MUST NOT be represented by disabling `Stopwatch`.

## Stopwatch API

`Stopwatch::start()` MUST return a monotonic timestamp token as an integer number of nanoseconds from:

```php
hrtime(true)
```

Canonical shape:

```text
start(): int
```

`Stopwatch::stop(int $startedAt)` MUST return duration in integer milliseconds.

Canonical shape:

```text
stop(int $startedAt): int
```

`$startedAt` MUST be a positive Stopwatch token returned by `start()`.

`Stopwatch` does not track issued token provenance.

Runtime enforcement is limited to positive-token validation and elapsed-time calculation.

Non-positive tokens MUST fail deterministically with:

```text
Coretsia\Foundation\Time\Exception\StopwatchInvalidStateException
```

The exception message MUST be stable and safe, and MUST NOT contain the raw token value.

Elapsed duration MUST be computed from:

```php
hrtime(true) - $startedAt
```

If the elapsed duration is negative or zero, `stop()` MUST return:

```text
0
```

If the elapsed duration is positive, it MUST be converted to integer milliseconds with:

```php
intdiv($durationNs, 1_000_000)
```

`Stopwatch` MUST NOT use:

```text
microtime(true)
```

because it returns a float.

`Stopwatch` MUST NOT expose floats.

`Stopwatch` MUST NOT format durations using locale-sensitive APIs.

`Stopwatch` MUST NOT depend on wall-clock timezone.

## Duration value model

Canonical duration values are integer milliseconds.

Canonical field suffix:

```text
_duration_ms
```

Canonical semantic name:

```text
durationMs
```

Duration values MUST be:

```text
int
>= 0
```

Duration values MUST be used as metric values, span attributes, log fields, or exported safe structural values according to owner policy.

Duration values MUST NOT be used as metric labels unless a future observability owner explicitly allows such a label in the canonical label allowlist.

## Observability policy

This epic introduces no spans.

This epic introduces no metrics.

This epic introduces no logs.

Consumers MAY use Foundation clock/id/stopwatch services to produce observability signals only when those signals comply with:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
```

Duration metric names SHOULD use `_duration_ms` suffix where applicable.

Duration values are values only.

IDs MUST NOT be metric labels.

The following ids MUST NOT be used as metric labels:

```text
correlation_id
uow_id
request_id
ULID
UUID
```

IDs MAY appear in logs or spans only when the owner policy allows safe ids and the emission does not expose secrets, direct user identifiers, raw transport data, or private customer data.

`correlation_id` MAY be used for logs/tracing correlation under the baseline observability policy.

Only canonical Foundation correlation ids accepted by `CorrelationIdProvider` may be used for correlation.

Malformed or unsafe context values stored under `correlation_id` resolve to `null` at the provider boundary and MUST NOT be normalized or emitted through logs, spans, metrics, traces, diagnostics, or generated artifacts by the provider.

`correlation_id` MUST NOT be used as a metric label.

## Security and redaction

Time, id, and duration services MUST NOT leak sensitive runtime data.

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

Runtime owners MUST prefer omission over unsafe emission.

## Error policy

This SSoT permits deterministic Foundation exceptions for time/id failures.

Optional exception types:

```text
Coretsia\Foundation\Time\Exception\StopwatchInvalidStateException
Coretsia\Foundation\Id\Exception\IdGenerationFailedException
```

If introduced, canonical error codes are:

```text
CORETSIA_STOPWATCH_INVALID_STATE
CORETSIA_ID_GENERATION_FAILED
```

Exception messages MUST be deterministic and safe.

Exception messages MUST NOT contain:

- raw entropy bytes;
- raw payloads;
- secrets;
- tokens;
- authorization values;
- cookies;
- private customer data;
- absolute local paths.

## DI wiring policy

The Foundation provider MUST bind:

```text
Psr\Clock\ClockInterface
```

to:

```text
Coretsia\Foundation\Clock\SystemClock
```

The Foundation provider MUST register:

```text
Coretsia\Foundation\Time\Stopwatch
```

The Foundation provider MUST register:

```text
Coretsia\Foundation\Id\UlidGenerator
Coretsia\Foundation\Id\UuidGenerator
```

The Foundation provider MUST bind:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

to the configured default selected by:

```text
foundation.ids.default
```

Provider wiring MUST be explicit and MUST NOT rely on concrete-class autowiring for baseline Foundation services.

Provider wiring MUST NOT create a second ULID implementation.

Provider wiring MUST NOT make correlation id generation depend on `IdGeneratorInterface`.

## Factory policy

`Coretsia\Foundation\Provider\FoundationServiceFactory` MAY contain stateless helpers for Foundation clock service construction and default id generator selection.

Clock construction is not runtime-config-selected in this epic.

Default id generator selection is controlled only by:

```text
foundation.ids.default
```

The factory MUST NOT keep mutable runtime state:

- no static snapshots;
- no caches;
- no buffers;
- no retained container instance;
- no retained config payload.

The caller owns the config snapshot passed into factory methods.

Factory failures MUST be deterministic and safe.

## Context and UoW policy

This epic does not introduce context reads.

This epic does not introduce context writes.

This epic does not change reset discipline.

Future kernel integration MAY use:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
Coretsia\Foundation\Time\Stopwatch
Psr\Clock\ClockInterface
```

for unit-of-work ids, timings, and timestamp needs.

That integration is owned by the kernel epic.

## Platform integration policy

This epic does not implement platform integrations.

`platform/http` MAY use `Stopwatch` for middleware timings in a later owner epic.

`platform/http` MAY use `IdGeneratorInterface` for `request_id` when request id policy is introduced by the HTTP owner epic.

`platform/cli` MAY use `ClockInterface` for deterministic timestamps where required by CLI-owned outputs.

This epic does not implement:

- HTTP middleware;
- CLI command behavior;
- worker lifecycle;
- scheduler lifecycle;
- queue adapters;
- request id propagation;
- correlation header extraction;
- correlation header injection.

## Artifact policy

This epic introduces no artifacts.

Time/id/duration services MUST NOT generate architecture artifacts, runtime artifacts, diagnostics artifacts, cache artifacts, or compiled config artifacts.

Any exported values produced by future consumers MUST remain governed by the relevant owner SSoT.

## Acceptance scenarios

### Clock

When runtime code resolves:

```text
Psr\Clock\ClockInterface
```

then it receives the canonical Foundation clock.

When `SystemClock::now()` is called, it returns a `DateTimeImmutable` in UTC.

### IDs

When `foundation.ids.default` is `ulid`, then:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

resolves to the canonical ULID generator.

When `foundation.ids.default` is `uuid`, then:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

resolves to the UUID generator.

Changing `foundation.ids.default` MUST NOT change correlation id generation.

### Stopwatch

When a duration is measured through `Stopwatch`, then the returned value is:

```text
int
>= 0
```

and represents integer milliseconds.

No float duration value is produced.

## Verification evidence

Expected verification includes:

```text
framework/packages/core/foundation/tests/Unit/UlidFormatTest.php
framework/packages/core/foundation/tests/Unit/StopwatchDurationIsNonNegativeTest.php
framework/packages/core/foundation/tests/Unit/FrozenClockReturnsDeterministicNowTest.php
framework/packages/core/foundation/tests/Contract/SystemClockReturnsUtcDateTimeImmutableContractTest.php
framework/packages/core/foundation/tests/Contract/UuidFormatContractTest.php
framework/packages/core/foundation/tests/Contract/FoundationConfigRejectsFloatValuesInIdsContractTest.php
framework/packages/core/foundation/tests/Integration/DefaultIdGeneratorResolvesFromConfigTest.php
framework/packages/core/foundation/tests/Integration/FoundationClockAndStopwatchBindingsTest.php
framework/packages/core/foundation/tests/Integration/FoundationIdsDefaultDoesNotAffectCorrelationIdTest.php
framework/packages/core/foundation/tests/Integration/CorrelationIdProviderReadsContextStoreTest.php
framework/packages/core/foundation/tests/Integration/CorrelationIdProviderRejectsUnsafeCorrelationIdsTest.php
```

These tests are expected to verify:

- ULID format remains canonical;
- UUID format is canonical;
- `SystemClock::now()` returns `DateTimeImmutable` in UTC;
- `FrozenClock::now()` is deterministic;
- `Stopwatch` returns non-negative integer milliseconds;
- `foundation.ids.*` rejects float values;
- no `foundation.clock.*` config key is introduced;
- `IdGeneratorInterface` resolves according to `foundation.ids.default`;
- `foundation.ids.default=uuid` does not affect `CorrelationIdGenerator`;
- `foundation.ids.default=uuid` does not imply `CorrelationIdProviderInterface` switches to UUID;
- `CorrelationIdProvider` returns only canonical uppercase ULID-like correlation ids;
- `CorrelationIdProvider` returns `null` for absent, empty, non-string, lowercase, malformed, token-like, cookie-like, SQL-like, URL-like, path-like, header-like, or otherwise unsafe context values;
- `CorrelationIdProvider` does not generate, normalize, rewrite, remove, replace, or store correlation ids as a side effect of reading.

## Non-goals

This SSoT does not define:

- a new package;
- a new `core/contracts` port;
- HTTP request id policy;
- HTTP header extraction policy;
- HTTP header injection policy;
- platform HTTP writers;
- platform CLI integration;
- kernel lifecycle implementation;
- a second ULID implementation;
- feature toggles for `Stopwatch`;
- feature toggles for clock services;
- feature toggles for id generation services;
- `foundation.clock.*` config keys;
- `foundation.correlation.*` config keys;
- `foundation.request_id.*` config keys;
- metric labels containing ids;
- generated artifacts.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Config Roots Registry](./config-roots.md)
- [Config and env SSoT](./config-and-env.md)
- [Context Keys SSoT](./context-keys.md)
- [Context Store SSoT](./context-store.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
