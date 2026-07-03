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

# ADR-0019: Enhanced reset for long-running services

## Status

Accepted.

## Context

Coretsia runtimes need deterministic reset behavior for long-running PHP processes.

The baseline reset mechanism already exists in Foundation:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
Coretsia\Foundation\Tag\TagRegistry
Coretsia\Contracts\Runtime\ResetInterface
```

Reset discovery is controlled by the effective Foundation reset discovery tag:

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

Kernel runtime must call the single Foundation reset executor:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator::resetAll()
```

Kernel runtime must not enumerate reset tags directly.

The canonical reset discovery list comes from:

```text
TagRegistry->all($effectiveResetTag)
```

`TagRegistry::all()` already returns a deterministic discovery list sorted as:

```text
priority DESC, id ASC
```

Therefore base reset order is not insertion order. Base reset order is exactly the output of:

```text
TagRegistry->all($effectiveResetTag)
```

Long-running runtimes need stronger reset planning for stateful services. Some services must be reset before others even when they share the same discovery tag. The required enhanced ordering model is:

```text
priority DESC
group ASC by strcmp()
serviceId ASC by strcmp()
```

The runtime also needs safe reset observability:

```text
foundation.reset
foundation.reset_total
foundation.reset_duration_ms
```

Observability must remain summary-only and must not leak service internals, raw payloads, headers, cookies, Authorization values, tokens, session ids, secrets, raw context values, or absolute paths.

This ADR records the decision introduced by epic `1.250.0`.

## Decision

Foundation introduces enhanced reset planning and typed deterministic reset failures while keeping the existing reset entrypoint stable.

The stable public entrypoint remains:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

The enhanced planner/executor is:

```text
Coretsia\Foundation\Runtime\Reset\PriorityResetOrchestrator
```

The reset contract remains unchanged:

```text
Coretsia\Contracts\Runtime\ResetInterface
```

The canonical reset method remains:

```text
reset(): void
```

Enhanced reset is controlled by:

```text
foundation.reset.priority.enabled
```

The 1.250.0 default is:

```text
foundation.reset.priority.enabled = true
```

This flag controls only enhanced priority/group reset planning.

It MUST NOT disable reset discovery.

It MUST NOT disable reset orchestration.

It MUST NOT be interpreted as:

```text
foundation.reset.enabled
```

No reset feature-disable switch is introduced.

## Decision 1: ResetOrchestrator remains the stable public entrypoint

`Coretsia\Foundation\Runtime\Reset\ResetOrchestrator` remains the single runtime reset executor that Kernel runtime is allowed to use.

Kernel runtime MUST call:

```text
ResetOrchestrator::resetAll()
```

Kernel runtime MUST NOT enumerate the reset discovery tag or the stateful marker directly.

The relevant reserved tag identifiers are:

```text
Coretsia\Foundation\Tag\ReservedTags::KERNEL_RESET
Coretsia\Foundation\Tag\ReservedTags::KERNEL_STATEFUL
```

Kernel runtime MUST use the Foundation reset executor boundary only.

`ResetOrchestrator` owns mode selection:

```text
foundation.reset.priority.enabled=false → base mode
foundation.reset.priority.enabled=true  → enhanced mode
```

In enhanced mode, `ResetOrchestrator` delegates planning and execution to:

```text
Coretsia\Foundation\Runtime\Reset\PriorityResetOrchestrator
```

`PriorityResetOrchestrator` intentionally does not know about:

```text
foundation.reset.priority.enabled
```

Mode selection belongs to `ResetOrchestrator` and Foundation wiring.

## Decision 2: Reset orchestration cannot be feature-disabled

Reset orchestration is baseline runtime safety infrastructure.

Foundation MUST NOT introduce:

```text
foundation.reset.enabled
foundation.reset.observability.enabled
```

`ResetOrchestrator::resetAll()` MAY be a deterministic noop only when the effective reset discovery list is empty.

The following remains always true when `core/foundation` is enabled:

```text
effective reset discovery tag
→ TagRegistry->all($effectiveResetTag)
→ ResetOrchestrator::resetAll()
```

The priority flag controls only whether reset execution uses base ordering or enhanced planning.

## Decision 3: Base mode preserves exact TagRegistry order

When enhanced reset planning is disabled:

```text
foundation.reset.priority.enabled = false
```

`ResetOrchestrator` MUST execute services in the exact order returned by:

```text
TagRegistry->all($effectiveResetTag)
```

It MUST NOT apply additional sorting.

It MUST NOT apply additional dedupe rules.

It MUST NOT parse tag meta.

It MUST NOT validate tag meta.

Invalid reset tag meta MUST NOT fail reset in disabled mode.

Base mode is defined strictly as the exact `TagRegistry::all($effectiveResetTag)` output order.

Because `TagRegistry::all()` already sorts discovery lists as:

```text
priority DESC, id ASC
```

Base mode means exact registry output order, not insertion order.

## Decision 4: Enhanced mode uses priority, group, and service id ordering

When enhanced reset planning is enabled:

```text
foundation.reset.priority.enabled = true
```

`PriorityResetOrchestrator` MUST build a reset plan from:

```text
TagRegistry->all($effectiveResetTag)
```

The planned order MUST be:

```text
1. priority DESC
2. group ASC by strcmp() on normalized group id
3. serviceId ASC by strcmp()
```

Sorting MUST be deterministic across operating systems and PHP builds.

Sorting MUST NOT use:

```text
setlocale
LC_ALL
locale-aware collation
natural sort
case folding
```

String comparison MUST use byte-order comparison:

```php
strcmp($left, $right)
```

Reset execution MUST be sequential.

No parallel reset execution is introduced.

## Decision 5: TaggedService priority is the base priority

Enhanced reset planning MUST treat:

```text
TaggedService.priority
```

as the base priority.

If tag meta contains a supported `priority` key, that value overrides the base priority.

Effective priority is:

```text
meta.priority ?? TaggedService.priority
```

The base priority is trusted because it is already normalized by `TaggedService`.

The meta priority is untrusted and MUST be parsed through enhanced reset meta rules.

## Decision 6: Supported reset meta keys are limited to priority and group

Enhanced reset planning reads and validates only these tag meta keys:

```text
priority
group
```

Any other meta keys MUST be ignored.

Unknown meta keys MUST NOT fail reset planning.

Example valid meta with ignored keys:

```php
[
    'priority' => 10,
    'group' => 'default',
    'x' => 'y',
    'debug' => ['a' => 1],
]
```

The effective ordering MUST be computed only from:

```text
priority
group
serviceId
```

## Decision 7: Reset priority meta parsing is deterministic

The accepted `priority` meta values are:

```text
int
string matching /\A-?\d+\z/
```

Examples of valid priority values:

```text
10
"10"
-10
"-10"
"0"
```

Examples of invalid priority values:

```text
" 10"
"10 "
"+10"
"1.0"
"01x"
null
true
false
1.5
[]
```

Valid string priority values are normalized by casting to int.

If `priority` is absent, the planner uses:

```text
TaggedService.priority
```

Invalid priority meta MUST fail deterministically with:

```text
ResetException(code=CORETSIA_RESET_META_INVALID, message="reset-meta-invalid")
```

## Decision 8: Reset group meta parsing is deterministic

The accepted `group` meta value is:

```text
string
```

Group normalization uses ASCII whitespace trimming only:

```php
trim($value, " \t\n\r\f\v")
```

The normalized group id MUST match:

```text
/\A[a-z0-9][a-z0-9._-]*\z/
```

If `group` is absent, the planner uses:

```text
foundation.reset.group.default
```

If `group` is present but ASCII-empty after trimming, the planner uses:

```text
foundation.reset.group.default
```

Examples that use the default group:

```php
[]
['group' => '']
['group' => '   ']
["group" => "\t\n"]
```

Examples of valid explicit group ids:

```text
default
cache
queue
db.primary
tenant-cache
worker_1
a
0
```

Examples of invalid explicit group ids:

```text
Default
 cache
cache 
.cache
-cache
_cache
cache/group
cache group
cache:group
Україна
```

Invalid group meta MUST fail deterministically with:

```text
ResetException(code=CORETSIA_RESET_META_INVALID, message="reset-meta-invalid")
```

`ResetGroup` does not know about Foundation config.

Default substitution is planner responsibility.

## Decision 9: Default reset group is Foundation config

Enhanced reset introduces:

```text
foundation.reset.group.default
```

The default value is:

```text
default
```

The config value MUST match the same group id shape used by reset tag meta:

```text
/\A[a-z0-9][a-z0-9._-]*\z/
```

This prevents config-valid/runtime-fail drift.

This key affects only entries without an explicit non-empty group.

It does not give special priority to the default group.

It only supplies the group value used before sorting.

Example with:

```text
foundation.reset.group.default = default
```

and equal effective priority:

| serviceId   | meta.group | effective group |
|-------------|------------|-----------------|
| app.queue   | queue      | queue           |
| app.cache   | cache      | cache           |
| app.context | absent     | default         |

Enhanced group ordering is:

```text
cache
default
queue
```

If:

```text
foundation.reset.group.default = queue
```

then `app.context` uses group `queue`, and services with the same priority are sorted by:

```text
cache
queue
```

with service id as the final tie-breaker inside the same group.

## Decision 10: Reset failures use typed deterministic exceptions

Foundation reset introduces deterministic error code registry:

```text
Coretsia\Foundation\Runtime\Reset\ResetErrorCodes
```

Foundation reset introduces typed reset exception:

```text
Coretsia\Foundation\Runtime\Reset\ResetException
```

`ResetException` carries a stable machine-readable string code:

```text
code(): string
```

`ResetException` exposes stable runtime-style diagnostics through:

```text
code()
errorCode()
reason()
withoutPrevious()
```

`code()` and `errorCode()` return the same stable reset error code.

`reason()` returns the stable safe reason token.

A surfaced reset failure MAY preserve the original previous throwable for in-process programmatic chaining.

Reset span exception recording MUST NOT receive the raw previous throwable chain.

When a reset failure is recorded into a span, Foundation reset orchestration MUST record a sanitized `ResetException` copy produced by:

```text
ResetException::withoutPrevious()
```

The sanitized copy preserves the reset code, error code, reason, and message, but does not preserve the previous throwable.

The deterministic reset error codes are:

```text
CORETSIA_RESET_META_INVALID
CORETSIA_RESET_SERVICE_NOT_RESETTABLE
CORETSIA_RESET_SERVICE_FAILED
```

The deterministic mapping is:

| Failure                                                | Code                                    | Message                      |
|--------------------------------------------------------|-----------------------------------------|------------------------------|
| invalid reset tag meta                                 | `CORETSIA_RESET_META_INVALID`           | `reset-meta-invalid`         |
| discovered service does not implement `ResetInterface` | `CORETSIA_RESET_SERVICE_NOT_RESETTABLE` | `reset-not-resettable`       |
| service resolution or reset execution throws           | `CORETSIA_RESET_SERVICE_FAILED`         | `reset-service-failed`       |

Exception messages MUST be fixed safe tokens.

Exception messages MUST NOT include:

- service internals;
- service object dumps;
- tag meta payloads;
- raw config payloads;
- raw context values;
- request payloads;
- response payloads;
- queue payloads;
- headers;
- cookies;
- Authorization values;
- tokens;
- session ids;
- credentials;
- private customer data;
- absolute local paths;
- stack traces by default.

## Decision 11: Tagged non-resettable services fail deterministically

Any service discovered through the effective Foundation reset discovery tag MUST implement:

```text
Coretsia\Contracts\Runtime\ResetInterface
```

If a discovered service does not implement `ResetInterface`, reset MUST fail deterministically with:

```text
ResetException(code=CORETSIA_RESET_SERVICE_NOT_RESETTABLE, message="reset-not-resettable")
```

This applies in both modes:

```text
foundation.reset.priority.enabled=false
foundation.reset.priority.enabled=true
```

The failure is tag misuse.

## Decision 12: Reset execution is fail-fast

Reset execution is sequential and fail-fast.

On the first reset service failure, reset processing MUST stop.

A service failure means either:

```text
container resolution for the service throws
```

or:

```text
ResetInterface::reset() throws
```

The surfaced reset failure MUST be:

```text
ResetException(code=CORETSIA_RESET_SERVICE_FAILED, message="reset-service-failed")
```

The original throwable MAY be carried as `previous`.

The surfaced message MUST remain the fixed safe token.

No failure aggregation is introduced by this epic.

No retry policy is introduced by this epic.

No timeout policy is introduced by this epic.

## Decision 13: Observability is summary-only and noop-safe

Enhanced reset MAY emit summary observability through existing noop-safe ports:

```text
Coretsia\Contracts\Observability\Tracing\TracerPortInterface
Coretsia\Contracts\Observability\Metrics\MeterPortInterface
Psr\Log\LoggerInterface
```

Foundation already provides noop-safe baseline implementations.

This epic MUST NOT introduce new production observability classes if existing ports/noops are sufficient.

Tests SHOULD use in-test fake tracer, meter, and logger implementations with safe captured events.

Enhanced reset span:

```text
foundation.reset
```

Allowed span attributes:

```text
services_count
groups_count
outcome
```

Enhanced reset metrics:

```text
foundation.reset_total
foundation.reset_duration_ms
```

These metric names are registered in the canonical metrics catalog in:

```text
docs/ssot/observability.md
```

Allowed metric labels:

```text
outcome
```

Allowed `outcome` values:

```text
ok
failed
```

Logs, if emitted, MUST be summary-only.

Allowed log context:

```text
services_count
groups_count
outcome
```

Logs MUST NOT include per-service internals, service dumps, tag meta payloads, stack traces, raw config, raw context, or raw unit-of-work payloads by default.

## Decision 14: Observability failures are failure-silent

Internal observability port failures MUST remain failure-silent.

Reset observability failures, including logger, tracer, meter, span finalization, span exception recording, and `Stopwatch` start/stop failures, MUST NOT change reset discovery, reset ordering, reset execution, reset success, or reset failure precedence.

If reset execution succeeds and reset observability emission fails, reset remains successful.

If reset execution fails and reset observability emission also fails, the primary reset failure remains surfaced.

If reset orchestration fails with an unexpected non-`ResetException` throwable, reset observability MUST emit outcome `failed` and MUST rethrow the original throwable unchanged.

Unexpected non-`ResetException` throwables MUST NOT be wrapped only to repair telemetry state.

Unexpected non-`ResetException` throwables MUST NOT be recorded as raw span exceptions, log context, metric labels, or exported reset diagnostics.

When reset duration cannot be measured, reset observability MAY collapse duration to `0` or omit the timing signal according to owner policy.

The unavailable timer sentinel MUST NOT be passed to `Stopwatch::stop()`.

## Decision 15: Config defaults and rules are Foundation-owned

The Foundation defaults file remains:

```text
framework/packages/core/foundation/config/foundation.php
```

It MUST return only the `foundation` subtree.

It MUST NOT repeat the root wrapper:

```php
return ['foundation' => [...]];
```

The reset defaults are:

```php
use Coretsia\Foundation\Tag\ReservedTags;

'reset' => [
    'tag' => ReservedTags::KERNEL_RESET,
    'priority' => [
        'enabled' => true,
    ],
    'group' => [
        'default' => 'default',
    ],
],
```

The Foundation rules file remains:

```text
framework/packages/core/foundation/config/rules.php
```

It MUST enforce:

```text
foundation.reset.tag: non-empty-string-no-ws
foundation.reset.priority.enabled: bool
foundation.reset.group.default: reset-group-id
```

Unknown keys under `foundation.reset.*` MUST be rejected by config rules.

The following config keys are not introduced:

```text
foundation.reset.enabled
foundation.reset.observability.enabled
foundation.reset.priority.default
foundation.reset.group.enabled
```

## Decision 16: Foundation provider/factory wiring is explicit and stateless

Foundation provider wiring MUST keep:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

as the stable public reset entrypoint.

Foundation provider wiring MUST register or bind:

```text
Coretsia\Foundation\Runtime\Reset\PriorityResetOrchestrator
```

Foundation reset wiring MUST use the same effective reset tag resolver for:

```text
ContextStore reset tag registration
ResetOrchestrator construction
```

Foundation provider wiring MUST NOT change:

```text
ContextStore tags
TagRegistry builder-owned instance semantics
noop observability bindings
Clock/IDs/Stopwatch unrelated bindings
```

`FoundationServiceFactory` MAY contain stateless helpers for reset service construction:

```text
resetPriorityEnabled(array $foundationConfig): bool
defaultResetGroup(array $foundationConfig): string
priorityResetOrchestrator(...)
resetOrchestrator(...)
```

The factory MUST NOT keep mutable runtime state:

- no static snapshots;
- no caches;
- no buffers;
- no retained container instance;
- no retained config payload.

The caller owns the config snapshot passed into factory methods.

## Consequences

### Positive

Reset behavior remains available through one stable public entrypoint.

Kernel runtime does not need to change its reset call site.

Base mode remains available and preserves exact `TagRegistry` order.

Enhanced mode gives deterministic priority/group reset ordering for long-running runtimes.

Ordering is independent of locale and operating system collation.

Tag meta parsing is narrow and deterministic.

Unknown meta keys can coexist with future non-reset tag metadata without breaking enhanced reset.

Failure semantics become code-first and testable.

Reset observability is safe, low-cardinality, and noop-compatible.

No new metric label keys are introduced beyond the existing allowlist.

Config defaults and runtime validation use the same reset group id shape.

### Negative / trade-offs

Default enhanced mode is enabled in 1.250.0, so tests that need base ordering must explicitly set:

```php
'priority' => ['enabled' => false]
```

`foundation.reset.group.default` affects services with absent or ASCII-empty group meta, which can change ordering among equal-priority services.

Enhanced reset is fail-fast and does not aggregate all reset failures.

There is no reset retry policy.

There is no reset timeout policy.

Observability is summary-only, so per-service reset timings are intentionally not emitted.

`ResetInterface` remains minimal and cannot express ordered dependencies directly.

## Rejected alternatives

### Alternative 1: Let Kernel runtime enumerate reset tags directly

Rejected.

Kernel runtime must use the single Foundation reset executor.

Direct tag enumeration would duplicate reset discovery, ordering, validation, and failure semantics.

The accepted design keeps:

```text
ResetOrchestrator::resetAll()
```

as the stable runtime boundary.

### Alternative 2: Treat base order as insertion order

Rejected.

`TagRegistry::all()` already returns canonical deterministic discovery order.

Base mode means exact `TagRegistry->all($effectiveResetTag)` output.

It does not mean provider insertion order.

### Alternative 3: Make enhanced reset a separate Kernel entrypoint

Rejected.

Kernel runtime should not change to a new reset API.

The accepted design keeps the same `ResetOrchestrator::resetAll()` entrypoint and moves mode selection into Foundation wiring.

### Alternative 4: Disable reset through config

Rejected.

Reset orchestration is baseline runtime safety infrastructure.

Disabling reset would allow mutable unit-of-work-local state to leak across units of work.

The accepted design allows only enhanced planning to be switched off.

### Alternative 5: Parse all tag meta keys

Rejected.

`TaggedService::meta` is a generic `array<string,mixed>` payload.

Reset planning owns only:

```text
priority
group
```

Parsing unknown keys would create coupling to unrelated future tag metadata.

The accepted design ignores unknown meta keys.

### Alternative 6: Use locale-aware group sorting

Rejected.

Locale-aware sorting can vary across systems and runtime configuration.

The accepted design uses `strcmp()` byte-order comparison.

### Alternative 7: Add per-service verbose reset logs

Rejected.

Per-service verbose logs increase leakage risk and cardinality risk.

The accepted design emits summary-only observability.

### Alternative 8: Add new production observability implementations

Rejected.

Foundation already has noop-safe observability/logger baseline bindings.

The accepted design uses existing ports/noops and test-local fakes.

### Alternative 9: Change ResetInterface

Rejected.

`ResetInterface` is already a format-neutral contracts boundary.

Changing it would create cross-package compatibility risk.

Enhanced reset planning belongs to Foundation tag/config/orchestrator semantics, not to the reset contract shape.

### Alternative 10: Put reset priority/group config in core/contracts

Rejected.

Contracts define `ResetInterface`.

Runtime reset discovery, ordering, tags, config, providers, and observability are Foundation-owned runtime concerns.

The accepted design keeps enhanced reset implementation in:

```text
core/foundation
```

## Security and redaction

Reset diagnostics MUST be safe.

Reset code MUST NOT emit or expose:

- service instances;
- constructor arguments;
- raw tag meta payloads;
- raw config payloads;
- raw context values;
- raw request bodies;
- raw response bodies;
- raw queue messages;
- raw worker payloads;
- raw scheduler payloads;
- raw headers;
- cookies;
- Authorization values;
- tokens;
- session ids;
- credentials;
- passwords;
- private keys;
- raw SQL;
- private customer data;
- direct user identifiers;
- absolute local paths;
- stack traces by default.

Allowed reset diagnostics are limited to:

```text
services_count
groups_count
outcome
stable fixed error code
stable fixed message token
normalized group id where owner policy permits
```

Reset diagnostics MUST prefer omission over unsafe emission.

## Observability impact

Enhanced reset introduces one span name:

```text
foundation.reset
```

Allowed span attributes are:

```text
services_count
groups_count
outcome
```

Enhanced reset introduces two metric names:

```text
foundation.reset_total
foundation.reset_duration_ms
```

These metric names are registered in the canonical metrics catalog in:

```text
docs/ssot/observability.md
```

Allowed metric labels are:

```text
outcome
```

No other metric label keys are introduced.

The following MUST NOT be metric labels:

```text
service_id
group
tag
exception
error_code
correlation_id
uow_id
request_id
path
host
driver
```

unless a later owner SSoT explicitly allows them for that signal.

Logs are optional and summary-only.

Observability remains governed by:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
```

## Runtime lifecycle impact

This ADR does not change Kernel lifecycle trigger ownership.

Kernel runtime remains responsible for deciding when reset is triggered.

The expected lifecycle remains:

```text
Unit of Work finishes
→ Kernel runtime after-UoW phase
→ ResetOrchestrator::resetAll()
→ effective reset discovery tag from foundation.reset.tag
→ base or enhanced reset execution
→ next Unit of Work starts without previous mutable state
```

Reset MUST be attempted as part of the runtime lifecycle for long-running processes.

The exact after-UoW exception aggregation policy remains owned by Kernel runtime.

## Platform impact

This ADR does not introduce platform-specific reset behavior.

It does not implement:

- HTTP middleware reset behavior;
- CLI command reset behavior;
- queue worker loop behavior;
- scheduler loop behavior;
- vendor runtime adapters;
- platform error mappers.

Platform packages use the same Foundation reset entrypoint through Kernel/runtime integration.

## Artifact impact

This ADR introduces no generated artifacts.

Enhanced reset planning MUST NOT generate:

- reset plans as runtime artifacts;
- reset diagnostics artifacts;
- tag artifacts;
- service-order artifacts;
- compiled config artifacts.

Any future exported diagnostics remain governed by owner SSoTs.

## Boundaries

This ADR does not introduce:

- new contracts;
- changes to `ResetInterface`;
- new DI tags;
- new reserved tag names;
- new tag ownership rows;
- new config roots;
- `foundation.reset.enabled`;
- `foundation.reset.observability.enabled`;
- reset retry policy;
- reset timeout policy;
- reset failure aggregation;
- platform-specific reset implementations;
- middleware behavior;
- queue worker behavior;
- scheduler behavior;
- generated artifacts;
- plugin systems;
- reset extensibility systems.

## Verification

Expected verification includes:

```text
framework/packages/core/foundation/tests/Contract/FoundationEnhancedResetConfigShapeContractTest.php
framework/packages/core/foundation/tests/Integration/PriorityResetOrderDeterministicTest.php
framework/packages/core/foundation/tests/Integration/ResetGroupWorksTest.php
framework/packages/core/foundation/tests/Integration/PriorityResetBackCompatWhenDisabledTest.php
framework/packages/core/foundation/tests/Integration/PriorityResetMetaParsingRejectsInvalidTest.php
framework/packages/core/foundation/tests/Integration/ResetOrderingIsLocaleIndependentTest.php
framework/packages/core/foundation/tests/Integration/PriorityResetIgnoresMetaWhenDisabledTest.php
framework/packages/core/foundation/tests/Integration/PriorityResetIgnoresUnknownMetaKeysWhenEnabledTest.php
framework/packages/core/foundation/tests/Integration/PriorityResetUsesConfiguredResetTagTest.php
framework/packages/core/foundation/tests/Integration/PriorityResetFailsFastOnFirstServiceExceptionTest.php
framework/packages/core/foundation/tests/Integration/ResetOrchestratorRejectsTaggedNonResettableServiceTest.php
framework/packages/core/foundation/tests/Unit/ResetExceptionRuntimeShapeTest.php
framework/packages/core/foundation/tests/Integration/PriorityResetRecordsSanitizedFailureExceptionTest.php
framework/packages/core/foundation/tests/Integration/PriorityResetObservabilityFailurePrecedenceTest.php
```

Verification MUST prove:

- `ResetOrchestrator` remains the stable public reset entrypoint;
- Kernel-facing reset entrypoint remains `ResetOrchestrator::resetAll()`;
- `ResetInterface` remains unchanged;
- `foundation.reset.priority.enabled` defaults to `true`;
- `foundation.reset.priority.enabled=false` preserves exact `TagRegistry->all($effectiveResetTag)` order;
- disabled mode ignores tag meta completely;
- invalid tag meta does not fail reset in disabled mode;
- enhanced mode delegates to `PriorityResetOrchestrator`;
- enhanced mode orders by `priority DESC`, `group ASC`, `serviceId ASC`;
- enhanced mode uses `TaggedService.priority` as base priority;
- `meta.priority` overrides `TaggedService.priority`;
- valid string priority values normalize to int;
- invalid priority meta fails with `CORETSIA_RESET_META_INVALID`;
- absent group uses `foundation.reset.group.default`;
- ASCII-empty group uses `foundation.reset.group.default`;
- invalid group meta fails with `CORETSIA_RESET_META_INVALID`;
- unknown meta keys are ignored in enhanced mode;
- ordering remains locale-independent;
- discovered non-resettable services fail with `CORETSIA_RESET_SERVICE_NOT_RESETTABLE`;
- first reset service failure stops further processing;
- service failure surfaces `CORETSIA_RESET_SERVICE_FAILED`;
- enhanced reset emits summary-only observability with safe labels/attributes;
- no new metric label keys are introduced;
- `foundation.reset.group.default` config shape matches runtime group meta shape;
- `foundation.reset.enabled` is not introduced;
- `foundation.reset.observability.enabled` is not introduced.
- `ResetException::errorCode()` matches `ResetException::code()`;
- `ResetException::reason()` exposes the stable safe reason token;
- `ResetException::withoutPrevious()` preserves code, errorCode, reason, and message;
- `ResetException::withoutPrevious()` strips previous throwable chains;
- span exception recording receives only sanitized reset failure copies;
- observability failure does not replace a primary reset failure;
- reset observability does not leak raw previous throwable messages or stack traces;
- unexpected non-`ResetException` reset failures emit outcome `failed` and are rethrown unchanged;
- unexpected non-`ResetException` reset failures are not recorded as raw reset diagnostics.

## Related SSoT

- `docs/ssot/reset-tags.md`
- `docs/ssot/stateful-services.md`
- `docs/ssot/context-lifecycle.md`
- `docs/ssot/uow-and-reset-contracts.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/di-tags-and-middleware-ordering.md`
- `docs/ssot/tags.md`
- `docs/ssot/config-and-env.md`
- `docs/ssot/config-roots.md`

## Related ADRs

- `docs/adr/ADR-0015-context-bag-context-store-correlation-id.md`
- `docs/adr/ADR-0016-clock-ids-stopwatch.md`

## Related epic

- `1.250.0 Enhanced Reset Mechanism for Long-Running Services`
