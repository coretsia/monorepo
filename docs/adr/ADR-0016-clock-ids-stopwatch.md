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

# ADR-0016: Clock, IDs, and Stopwatch

## Status

Accepted.

## Context

Coretsia runtime packages need one stable way to obtain current time, safe runtime ids, and duration measurements.

The Foundation package already owns baseline runtime mechanisms for:

```text
DI container
configuration root foundation.*
reset orchestration
context store
correlation id generation/provider wiring
```

Epic `1.210.0` introduced the canonical Foundation ULID source:

```text
Coretsia\Foundation\Id\UlidGenerator
```

That generator is the single ULID implementation source in the codebase.

Epic `1.210.0` also introduced:

```text
Coretsia\Foundation\Id\CorrelationIdGenerator
Coretsia\Foundation\Observability\CorrelationIdProvider
```

`CorrelationIdGenerator` delegates to `UlidGenerator` directly and must not implement ULID logic independently.

Runtime packages now need a broader Foundation baseline for:

- PSR-20 clock access;
- deterministic test clocks;
- generic runtime id generation;
- optional UUID generation;
- float-free duration measurement.

Without a canonical Foundation source, higher layers would duplicate clock logic, id generation, and timing code. That would create nondeterministic formats, timezone drift, metric naming drift, and unsafe high-cardinality observability labels.

This ADR records the decision introduced by epic `1.220.0`.

## Decision

Foundation introduces canonical runtime services for time, ids, and duration:

```text
Coretsia\Foundation\Clock\SystemClock
Coretsia\Foundation\Clock\FrozenClock
Coretsia\Foundation\Id\IdGeneratorInterface
Coretsia\Foundation\Id\UuidGenerator
Coretsia\Foundation\Time\Stopwatch
```

Foundation also adapts the existing canonical ULID generator for generic runtime id generation:

```text
Coretsia\Foundation\Id\UlidGenerator
```

The normative SSoT is:

```text
docs/ssot/time-ids-and-duration.md
```

No new package is introduced.

No new `core/contracts` port is introduced.

The PSR-20 boundary is used for clock access:

```text
Psr\Clock\ClockInterface
```

## Decision 1: Foundation owns the canonical clock binding

Foundation provides:

```text
Coretsia\Foundation\Clock\SystemClock
```

`SystemClock` implements:

```text
Psr\Clock\ClockInterface
```

`SystemClock::now()` MUST return:

```text
DateTimeImmutable
```

The returned time MUST be in UTC.

`SystemClock` MUST NOT assert monotonicity.

System time may move forward or backward because wall-clock time is controlled by the operating system and host environment.

Runtime code that needs duration measurement MUST use `Stopwatch`, not wall-clock differences.

The Foundation provider MUST bind:

```text
Psr\Clock\ClockInterface
```

to:

```text
Coretsia\Foundation\Clock\SystemClock
```

## Decision 2: FrozenClock is deterministic test infrastructure

Foundation provides:

```text
Coretsia\Foundation\Clock\FrozenClock
```

`FrozenClock` implements:

```text
Psr\Clock\ClockInterface
```

`FrozenClock::now()` MUST return a deterministic `DateTimeImmutable` value.

Repeated calls to `FrozenClock::now()` MUST return the same logical instant unless the test owner deliberately constructs or replaces the clock with a different instant.

`FrozenClock` exists for tests, fixtures, and deterministic test/support scenarios.

FrozenClock is test/support infrastructure and is not selected through runtime config in this epic.

This epic does not introduce:

```text
foundation.clock.*
```

The baseline runtime `ClockInterface` binding remains:

```text
Psr\Clock\ClockInterface -> Coretsia\Foundation\Clock\SystemClock
```

## Decision 3: Foundation owns generic runtime id generation

Foundation introduces:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

The canonical method shape is:

```text
generate(): string
```

`IdGeneratorInterface` represents generic safe runtime id generation.

Generated ids MUST be safe opaque identifiers.

Generated ids MUST NOT be derived from:

- secrets;
- credentials;
- raw payloads;
- direct user identifiers;
- cookies;
- session ids;
- authorization values;
- raw headers;
- raw request bodies;
- raw response bodies;
- raw SQL;
- private customer data.

The default generic runtime id generator is selected through:

```text
foundation.ids.default
```

Supported values are:

```text
ulid
uuid
```

The default is:

```text
ulid
```

## Decision 4: ULID remains the default runtime id format

The existing canonical ULID generator remains:

```text
Coretsia\Foundation\Id\UlidGenerator
```

`UlidGenerator` remains the single ULID implementation source in the codebase.

`UlidGenerator` MUST continue to generate uppercase Crockford Base32 ULID strings matching:

```text
/\A[0-9A-HJKMNP-TV-Z]{26}\z/
```

`UlidGenerator` MAY implement:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

so it can serve as the default generic runtime id generator.

That adaptation MUST NOT change the ULID format.

That adaptation MUST NOT introduce another ULID implementation.

Other Foundation ids that need ULID format MUST delegate to `UlidGenerator`.

## Decision 5: UUID is optional generic id generation

Foundation introduces:

```text
Coretsia\Foundation\Id\UuidGenerator
```

`UuidGenerator` MUST generate canonical textual UUID values.

Canonical UUID format is lowercase hexadecimal with hyphens:

```text
/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/
```

`UuidGenerator` MAY implement:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

`UuidGenerator` MAY be selected as the generic default runtime id generator through:

```text
foundation.ids.default = uuid
```

UUID support MUST NOT introduce another ULID implementation.

UUID support MUST NOT alter correlation id generation.

## Decision 6: Correlation id remains ULID-backed and isolated from default id selection

Correlation id behavior remains governed by epic `1.210.0`.

Foundation correlation services are:

```text
Coretsia\Foundation\Id\CorrelationIdGenerator
Coretsia\Foundation\Observability\CorrelationIdProvider
```

`CorrelationIdGenerator` MUST continue to receive:

```text
Coretsia\Foundation\Id\UlidGenerator
```

through constructor injection.

`CorrelationIdGenerator` MUST continue to delegate directly to `UlidGenerator`.

`CorrelationIdGenerator` MUST NOT depend on:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

`CorrelationIdGenerator` MUST NOT switch to UUID when:

```text
foundation.ids.default = uuid
```

`CorrelationIdProvider` remains a read provider.

`CorrelationIdProvider` MUST NOT generate ids as a side effect of reading.

`CorrelationIdProvider` MUST NOT be affected by `foundation.ids.default`.

No `foundation.correlation.*` config keys are introduced.

## Decision 7: Foundation owns float-free duration measurement

Foundation introduces:

```text
Coretsia\Foundation\Time\Stopwatch
```

`Stopwatch` is the canonical Foundation runtime duration service.

`Stopwatch` MUST be resolvable whenever `core/foundation` is enabled.

`Stopwatch` MUST NOT be feature-disabled through config.

Absence of duration measurement in a consumer is represented by:

```text
consumer does not call Stopwatch
```

It MUST NOT be represented by disabling `Stopwatch`.

## Decision 8: Stopwatch uses integer nanosecond tokens and integer milliseconds

`Stopwatch::start()` MUST return a monotonic timestamp token as integer nanoseconds from:

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

`Stopwatch` MUST NOT expose float durations.

`Stopwatch` MUST NOT format durations using locale-sensitive APIs.

`Stopwatch` MUST NOT depend on wall-clock timezone.

## Decision 9: Foundation config selects only default id behavior

This epic introduces Foundation config keys under the existing root:

```text
foundation
```

The defaults file remains:

```text
framework/packages/core/foundation/config/foundation.php
```

It MUST return the subtree only and MUST NOT repeat the root wrapper.

Canonical key:

```text
foundation.ids.default
```

Default:

```text
foundation.ids.default = ulid
```

Allowed values:

```text
foundation.ids.default ∈ {ulid, uuid}
```

This epic does not introduce:

```text
foundation.clock.*
```

The runtime clock binding is fixed:

```text
Psr\Clock\ClockInterface -> Coretsia\Foundation\Clock\SystemClock
```

`SystemClock` is the baseline runtime `ClockInterface` binding.

`FrozenClock` is test/support infrastructure and is not selected through runtime config in this epic.

`foundation.ids.default` selects only:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

`foundation.ids.default` MUST NOT affect:

```text
Coretsia\Foundation\Id\CorrelationIdGenerator
Coretsia\Foundation\Observability\CorrelationIdProvider
```

The following config keys are explicitly not introduced:

```text
foundation.clock.*
foundation.correlation.*
foundation.request_id.*
foundation.time.*
foundation.duration.*
```

## Decision 10: Config rules reject float values under ids

The Foundation rules file remains:

```text
framework/packages/core/foundation/config/rules.php
```

It MUST enforce allowed values for:

```text
foundation.ids.default
```

It MUST reject unknown keys under:

```text
foundation.ids.*
```

It MUST reject any float value under:

```text
foundation.ids.*
```

including nested float values if nested values are introduced later.

Float rejection includes:

```text
NaN
INF
-INF
```

where representable in validation fixtures.

Failure messages MUST be deterministic and safe.

Failure messages MUST NOT dump raw config values.

No numeric config values are introduced by this epic.

This epic does not introduce `foundation.clock.*`, so clock binding validation is not config-driven.

## Decision 11: Provider wiring is explicit

The Foundation provider MUST explicitly register or bind:

```text
Psr\Clock\ClockInterface
Coretsia\Foundation\Clock\SystemClock
Coretsia\Foundation\Time\Stopwatch
Coretsia\Foundation\Id\UlidGenerator
Coretsia\Foundation\Id\UuidGenerator
Coretsia\Foundation\Id\IdGeneratorInterface
```

`IdGeneratorInterface` MUST resolve according to:

```text
foundation.ids.default
```

Mapping:

| `foundation.ids.default` | Binding target                         |
|--------------------------|----------------------------------------|
| `ulid`                   | `Coretsia\Foundation\Id\UlidGenerator` |
| `uuid`                   | `Coretsia\Foundation\Id\UuidGenerator` |

Provider wiring MUST NOT rely on concrete-class autowiring for baseline Foundation time/id/duration services.

Provider wiring MUST NOT create a second ULID implementation.

Provider wiring MUST NOT make `CorrelationIdGenerator` depend on `IdGeneratorInterface`.

## Decision 12: FoundationServiceFactory remains stateless

`Coretsia\Foundation\Provider\FoundationServiceFactory` MAY contain stateless helpers for default id generator selection and other Foundation service construction that needs merged config.

Clock construction is not runtime-config-selected in this epic and may remain direct provider wiring.

The factory MUST NOT keep mutable runtime state:

- no static snapshots;
- no caches;
- no buffers;
- no retained container instance;
- no retained config payload.

The caller owns the config snapshot passed into factory methods.

Factory failures MUST be deterministic and safe.

## Consequences

### Positive

Runtime packages can resolve one canonical PSR-20 clock.

Tests can use deterministic frozen clocks.

Generic runtime id generation is centralized behind `IdGeneratorInterface`.

ULID remains the default id format.

UUID is available as an optional generic id format.

Correlation id behavior remains stable and ULID-backed.

Duration measurements are integer milliseconds.

Duration measurement is float-free.

Metric naming can consistently use `_duration_ms`.

Provider wiring remains explicit and deterministic.

Configuration stays under the existing `foundation` root.

### Negative / trade-offs

Clock selection is not configurable in this epic.

`SystemClock` is always the baseline runtime `ClockInterface` binding.

`FrozenClock` is test/support infrastructure and is not runtime-config-selectable in this epic.

UUID support adds a second id format, but not a second ULID implementation.

`foundation.ids.default` does not affect correlation ids, which may surprise callers expecting global id format switching.

Durations are millisecond precision even though `hrtime(true)` provides nanosecond tokens.

`Stopwatch` is always-on baseline infrastructure and cannot be disabled through config.

## Rejected alternatives

### Alternative 1: Introduce a new utility package

Rejected.

Clock, id, and duration services are Foundation runtime primitives.

Introducing a separate utility package would add another dependency boundary without providing ownership clarity.

The accepted design keeps these services in:

```text
core/foundation
```

### Alternative 2: Add a new contracts-level id port

Rejected.

This epic does not introduce a new `core/contracts` port.

`IdGeneratorInterface` is Foundation-owned because it is a concrete Foundation runtime abstraction for default runtime id generation.

A future cross-package contract may be introduced only by a dedicated contracts epic.

### Alternative 3: Use `microtime(true)` for durations

Rejected.

`microtime(true)` returns a float.

Foundation duration measurement must be float-free.

The accepted design uses:

```text
hrtime(true)
```

and returns integer milliseconds.

### Alternative 4: Measure durations by subtracting DateTime values

Rejected.

Wall-clock time can jump due to OS or host environment changes.

Duration measurement must use monotonic time tokens from `hrtime(true)`.

### Alternative 5: Make Stopwatch configurable or feature-disabled

Rejected.

`Stopwatch` is baseline Foundation runtime infrastructure.

Consumers that do not need timing simply do not call it.

A config toggle would create unnecessary runtime branches and inconsistent service availability.

### Alternative 6: Make `foundation.ids.default` control correlation ids

Rejected.

Correlation id behavior is already cemented by epic `1.210.0`.

Changing correlation id format through generic id config would create format drift and break the single-source ULID rule.

The accepted design keeps `CorrelationIdGenerator` directly coupled to `UlidGenerator`.

### Alternative 7: Introduce `foundation.correlation.*`

Rejected.

Correlation id generation/provider wiring is baseline Foundation runtime infrastructure from epic `1.210.0`.

No correlation config keys are introduced by this epic.

### Alternative 8: Use ids as metric labels

Rejected.

IDs are high-cardinality values.

The baseline observability policy forbids ids as metric labels.

IDs may appear in logs or spans only when owner policy allows safe id emission.

### Alternative 9: Make request id policy part of this epic

Rejected.

Request id extraction, injection, propagation, and HTTP writer behavior are transport-specific.

That policy belongs to a later HTTP owner epic.

This epic only provides reusable id generation services.

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

## Observability impact

This epic introduces no spans.

This epic introduces no metrics.

This epic introduces no logs.

Consumers MAY use Foundation clock/id/stopwatch services to produce observability signals only when those signals comply with:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
```

Duration metric names SHOULD use:

```text
_duration_ms
```

where applicable.

Duration values are values only.

IDs MUST NOT be metric labels.

Forbidden metric labels include:

```text
correlation_id
uow_id
request_id
ULID
UUID
```

IDs MAY appear in logs or spans only when the owner policy allows safe ids and the emission does not expose secrets, direct user identifiers, raw transport data, or private customer data.

`correlation_id` MAY be used for logs/tracing correlation under the baseline observability policy.

`correlation_id` MUST NOT be used as a metric label.

## Runtime lifecycle impact

This epic does not implement Kernel lifecycle behavior.

This epic does not introduce context reads.

This epic does not introduce context writes.

This epic does not change reset discipline.

A later Kernel runtime integration MAY use:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
Coretsia\Foundation\Time\Stopwatch
Psr\Clock\ClockInterface
```

for unit-of-work ids, timings, and timestamp needs.

Kernel lifecycle ownership remains outside this ADR.

## Platform impact

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

## Artifact impact

This epic introduces no artifacts.

Time/id/duration services MUST NOT generate architecture artifacts, runtime artifacts, diagnostics artifacts, cache artifacts, or compiled config artifacts.

Any exported values produced by future consumers remain governed by the relevant owner SSoT.

## Boundaries

This ADR does not introduce:

- a new package;
- a new `core/contracts` port;
- HTTP request id policy;
- HTTP header extraction policy;
- HTTP header injection policy;
- platform HTTP writers;
- platform CLI integration;
- kernel lifecycle implementation;
- a second ULID implementation;
- floats in config or artifacts;
- ids as metric labels;
- feature toggles for `Stopwatch`;
- feature toggles for id generation services;
- feature toggles for clock services;
- `foundation.clock.*` config keys;
- `foundation.correlation.*` config keys;
- `foundation.request_id.*` config keys.

## Verification

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
```

Verification MUST prove:

- ULID format remains canonical uppercase Crockford Base32;
- UUID format is canonical lowercase textual UUID with hyphens;
- `SystemClock::now()` returns `DateTimeImmutable` in UTC;
- monotonicity is not asserted for `SystemClock`;
- `FrozenClock::now()` is deterministic;
- `Stopwatch::start()` returns an integer token;
- `Stopwatch::stop()` returns integer milliseconds;
- `Stopwatch::stop()` never returns a negative value;
- `Stopwatch` does not use `microtime(true)`;
- no `foundation.clock.*` config key is introduced;
- `foundation.ids.default` accepts only `ulid` and `uuid`;
- floats under `foundation.ids.*` are rejected;
- `IdGeneratorInterface` resolves to `UlidGenerator` when `foundation.ids.default=ulid`;
- `IdGeneratorInterface` resolves to `UuidGenerator` when `foundation.ids.default=uuid`;
- `foundation.ids.default=uuid` does not affect `CorrelationIdGenerator`;
- `foundation.ids.default=uuid` does not imply `CorrelationIdProviderInterface` switches to UUID.

## Related SSoT

- `docs/ssot/time-ids-and-duration.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/config-and-env.md`
- `docs/ssot/context-keys.md`
- `docs/ssot/context-store.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/uow-and-reset-contracts.md`

## Related ADRs

- `docs/adr/ADR-0015-context-bag-context-store-correlation-id.md`

## Related epic

- `1.220.0 Foundation: Clock + IDs + Stopwatch`
