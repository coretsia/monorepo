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

# Reset tags SSoT

## Purpose

This document is the Single Source of Truth for reset-specific semantics around already-canonical Foundation reset tags.

It defines how long-running runtimes use the effective Foundation reset discovery tag and the `kernel.stateful` enforcement marker to prevent mutable state from leaking across Unit of Work boundaries.

The core reset discipline is:

```text
stateful service
→ implements ResetInterface
→ tagged with kernel.stateful
→ discoverable through the effective Foundation reset discovery tag
→ reset after every UoW
```

This document also owns the Foundation reset semantics introduced by epic `1.250.0`:

```text
foundation.reset.priority.enabled
foundation.reset.group.default
reset tag meta keys: priority, group
base reset mode
enhanced priority/group reset mode
deterministic reset failure codes
summary-only enhanced reset observability
```

## Source-of-truth boundaries

This document owns reset-specific semantics for already-canonical tags.

It owns the reset-specific meta schema for the effective Foundation reset discovery tag:

```text
priority
group
```

It owns the reset-specific config keys:

```text
foundation.reset.tag
foundation.reset.priority.enabled
foundation.reset.group.default
```

It owns the reset-specific deterministic failure mapping introduced by `1.250.0`.

It owns reset-specific observability usage introduced by `1.250.0`.

It does not own the canonical span naming policy, canonical metrics catalog, metric-specific catalog labels, or the global observability label allowlist. Those are owned by:

```text
docs/ssot/observability.md
```

Reset-specific observability usage is:

```text
span name: foundation.reset
metrics: foundation.reset_total, foundation.reset_duration_ms
metric labels: outcome
```

The reset span name is validated by canonical span naming policy.

Reset metric names and metric-specific labels are registered in the canonical metrics catalog.

It does not own the canonical reserved tag registry. Tag names, ownership rows, reserved prefixes, tag naming rules, and framework-reserved DI tag identifier code-level registry rules are owned by:

```text
docs/ssot/tags.md
```

The canonical code-level registry for framework-reserved DI tag identifier strings is:

```text
Coretsia\Foundation\Tag\ReservedTags
```

This document MUST NOT redeclare reserved tag registry rows from `docs/ssot/tags.md`.

This document MUST NOT introduce additional code-level registries for framework-reserved DI tag identifiers.

It does not own general tag discovery ordering, dedupe behavior, or consumer obligations. Those are owned by:

```text
docs/ssot/di-tags-and-middleware-ordering.md
```

It does not own reset or hook contract shapes. Those are owned by:

```text
docs/ssot/uow-and-reset-contracts.md
```

It does not own ContextStore lifecycle usage. That is owned by:

```text
docs/ssot/context-lifecycle.md
```

It does not own ContextStore safe-write policy or value validation. That is owned by:

```text
docs/ssot/context-store.md
```

It does not own concrete implementation internals of:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
Coretsia\Foundation\Runtime\Reset\PriorityResetOrchestrator
Coretsia\Foundation\Tag\TagRegistry
```

This document MUST NOT redefine:

- reserved tag registry rows;
- reserved tag ownership;
- general `TagRegistry` ordering;
- general tag dedupe policy;
- `ResetInterface` method shape;
- Kernel runtime lifecycle implementation;
- observability label allowlist outside reset-specific usage;
- production observability backend implementations.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Terminology

A Unit of Work, abbreviated as UoW, is one logical runtime cycle.

Examples include:

```text
HTTP request
CLI command invocation
queue job
worker task
scheduler tick
custom runtime boundary
```

Reset discipline is the invariant that mutable in-memory state MUST NOT leak from one UoW into the next UoW in the same PHP process.

The effective Foundation reset discovery tag is the tag value resolved from:

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

The fixed stateful-service enforcement marker is:

```text
kernel.stateful
```

The canonical code-level identifier for this framework-reserved DI tag is:

```text
Coretsia\Foundation\Tag\ReservedTags::KERNEL_STATEFUL
```

Legacy/base reset mode is reset execution with enhanced priority/group planning disabled:

```text
foundation.reset.priority.enabled = false
```

Enhanced reset mode is reset execution with enhanced priority/group planning enabled:

```text
foundation.reset.priority.enabled = true
```

The supported reset tag meta keys are:

```text
priority
group
```

All other reset tag meta keys are unknown to reset planning and MUST be ignored by enhanced reset mode.

## Foundation reset config keys

Foundation reset discovery is controlled by:

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

Enhanced reset planning is controlled by:

```text
foundation.reset.priority.enabled
```

The default value introduced by epic `1.250.0` is:

```text
true
```

This flag controls only enhanced priority/group reset planning.

It MUST NOT disable reset discovery.

It MUST NOT disable reset orchestration.

It MUST NOT be interpreted as:

```text
foundation.reset.enabled
```

No reset feature-disable switch exists.

The default enhanced reset group is controlled by:

```text
foundation.reset.group.default
```

The default value introduced by epic `1.250.0` is:

```text
default
```

The value MUST match:

```text
/\A[a-z0-9][a-z0-9._-]*\z/
```

This is the same normalized group id shape used by reset tag meta `group`.

This prevents config-valid/runtime-fail drift.

The following config keys MUST NOT be introduced by reset policy:

```text
foundation.reset.enabled
foundation.reset.observability.enabled
foundation.reset.priority.default
foundation.reset.group.enabled
```

Unknown keys under `foundation.reset.*` MUST be rejected by Foundation config rules.

## Effective reset discovery tag

Foundation reset discovery is controlled by:

```text
foundation.reset.tag
```

The resolved value is the effective Foundation reset discovery tag.

Foundation reset execution MUST discover resettable services through:

```text
Coretsia\Foundation\Tag\TagRegistry::all($effectiveResetTag)
```

Runtime reset execution MUST NOT hardcode `kernel.reset` when an effective tag has already been resolved from Foundation configuration.

Runtime reset execution MUST NOT discover resettable services through:

```text
kernel.stateful
```

`kernel.stateful` is not the runtime reset execution list.

## Reserved default reset tag

The reserved default reset discovery tag value is:

```text
kernel.reset
```

`kernel.reset` is the canonical default tag name for reset-capable service discovery.

`kernel.reset` is owned by `core/foundation` in the canonical tag registry.

The canonical code-level identifier for this framework-reserved DI tag is:

```text
Coretsia\Foundation\Tag\ReservedTags::KERNEL_RESET
```

`kernel.reset` is not a direct instruction for consumers to enumerate tagged services themselves.

Runtime consumers MUST NOT iterate `kernel.reset` services directly.

Runtime consumers MUST use the single Foundation reset executor:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

When documentation says `kernel.reset`, it is shorthand for the reserved default effective reset discovery tag value.

When runtime code needs the effective reset discovery tag, it MUST use the Foundation-owned config/wiring resolver.

## ResetOrchestrator entrypoint

Runtime reset execution goes through:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

`ResetOrchestrator` is the stable public reset entrypoint used by Kernel runtime.

Kernel runtime MUST call:

```text
ResetOrchestrator::resetAll()
```

Kernel runtime MUST NOT enumerate:

```text
kernel.reset
kernel.stateful
```

or any configured reset tag directly.

`ResetOrchestrator` owns mode selection:

```text
foundation.reset.priority.enabled=false → base mode
foundation.reset.priority.enabled=true  → enhanced mode
```

In enhanced mode, `ResetOrchestrator` delegates planning and execution to:

```text
Coretsia\Foundation\Runtime\Reset\PriorityResetOrchestrator
```

`PriorityResetOrchestrator` MUST NOT own mode selection.

`PriorityResetOrchestrator` intentionally does not know about:

```text
foundation.reset.priority.enabled
```

Mode selection belongs to `ResetOrchestrator` and Foundation wiring.

## Reset orchestration cannot be feature-disabled

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

## ResetInterface requirement

Any service discovered through the effective Foundation reset discovery tag MUST implement:

```text
Coretsia\Contracts\Runtime\ResetInterface
```

The canonical reset method is:

```text
reset(): void
```

Reset implementations own their own mutable state.

Reset implementations MUST NOT require transport-specific objects, request objects, response objects, queue messages, worker context objects, scheduler context objects, concrete container objects, or runtime-specific payloads.

Reset implementations MUST NOT expose raw UoW payloads, raw request data, raw queue messages, credentials, tokens, private customer data, or absolute local paths through diagnostics.

Reset implementations SHOULD make repeated reset calls on already-clean state safe and idempotent where feasible.

This MUST NOT be interpreted as “reset can never throw”.

Concrete reset failure semantics are owned by Foundation reset orchestration.

## `kernel.stateful` enforcement marker

`kernel.stateful` is a fixed enforcement marker.

It is owned by `core/foundation`.

The canonical code-level identifier for this framework-reserved DI tag is:

```text
Coretsia\Foundation\Tag\ReservedTags::KERNEL_STATEFUL
```

It exists so CI, static analysis, and wiring checks can detect services that retain mutable in-memory state.

`kernel.stateful` MUST NOT be used as the runtime reset execution list.

Kernel runtime MUST NOT use `kernel.stateful` to decide which services to reset.

Foundation reset orchestration MUST NOT use `kernel.stateful` as a replacement for the effective Foundation reset discovery tag.

If a service is tagged:

```text
kernel.stateful
```

then it MUST also satisfy both requirements:

```text
implements Coretsia\Contracts\Runtime\ResetInterface
discoverable through the effective Foundation reset discovery tag
```

With the reserved default configuration, this means a stateful service is typically tagged with both:

```text
kernel.stateful
kernel.reset
```

When `foundation.reset.tag` is changed, the second tag is the resolved effective reset discovery tag, not necessarily the literal `kernel.reset`.

## Runtime usage boundary

Foundation is responsible for the reusable reset executor.

Consumers MUST NOT:

- enumerate `kernel.reset` services directly;
- enumerate `kernel.stateful` services as a reset list;
- reconstruct reset discovery from class names;
- reconstruct reset discovery from container service ids;
- reconstruct reset discovery from provider internals;
- reconstruct reset discovery from package metadata;
- use reflection as a competing reset discovery mechanism;
- apply a competing reset ordering rule;
- apply a competing reset meta parser.

The expected lifecycle relationship is:

```text
Kernel after-UoW phase
→ ResetOrchestrator::resetAll()
→ TagRegistry->all($effectiveResetTag)
→ ResetInterface::reset()
→ next UoW starts without previous mutable state
```

## Reset ordering modes

### Base mode

Legacy/base mode is selected when:

```text
foundation.reset.priority.enabled = false
```

In base mode, reset execution MUST preserve the exact list order returned by:

```text
TagRegistry->all($effectiveResetTag)
```

It MUST NOT apply additional sorting.

It MUST NOT apply additional dedupe rules.

It MUST NOT parse tag meta.

It MUST NOT validate tag meta.

Invalid reset tag meta MUST NOT fail reset in disabled mode.

Because `TagRegistry::all()` already returns canonical deterministic discovery order:

```text
priority DESC, id ASC
```

Base reset order is not insertion order.

Base reset order means exact output of:

```text
TagRegistry->all($effectiveResetTag)
```

### Enhanced mode

Enhanced mode is selected when:

```text
foundation.reset.priority.enabled = true
```

Enhanced mode MUST build a reset plan from:

```text
TagRegistry->all($effectiveResetTag)
```

Enhanced mode MUST order reset execution by:

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

## Enhanced reset tag meta

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

### `priority` meta

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

### `group` meta

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

Default group substitution is planner responsibility.

`ResetGroup` value objects MUST NOT know about Foundation config.

## Default reset group behavior

The default reset group is:

```text
foundation.reset.group.default
```

The default value is:

```text
default
```

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

## Deterministic reset failures

Foundation reset owns deterministic reset failure codes through:

```text
Coretsia\Foundation\Runtime\Reset\ResetErrorCodes
```

Foundation reset owns typed reset failures through:

```text
Coretsia\Foundation\Runtime\Reset\ResetException
```

`ResetException` carries a stable machine-readable string code:

```text
code(): string
```

The deterministic reset error codes are:

```text
CORETSIA_RESET_META_INVALID
CORETSIA_RESET_SERVICE_NOT_RESETTABLE
CORETSIA_RESET_SERVICE_FAILED
CORETSIA_RESET_OBSERVABILITY_FAILED
```

The deterministic mapping is:

| Failure                                                | Code                                    | Message                      |
|--------------------------------------------------------|-----------------------------------------|------------------------------|
| invalid reset tag meta                                 | `CORETSIA_RESET_META_INVALID`           | `reset-meta-invalid`         |
| discovered service does not implement `ResetInterface` | `CORETSIA_RESET_SERVICE_NOT_RESETTABLE` | `reset-not-resettable`       |
| service resolution or reset execution throws           | `CORETSIA_RESET_SERVICE_FAILED`         | `reset-service-failed`       |
| internal observability port failure                    | `CORETSIA_RESET_OBSERVABILITY_FAILED`   | `reset-observability-failed` |

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

## Tagged non-resettable services

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

## Reset execution fail-fast policy

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

No failure aggregation is introduced by reset policy.

No retry policy is introduced by reset policy.

No timeout policy is introduced by reset policy.

## Enhanced reset observability

Enhanced reset MAY emit summary observability through existing noop-safe ports:

```text
Coretsia\Contracts\Observability\Tracing\TracerPortInterface
Coretsia\Contracts\Observability\Metrics\MeterPortInterface
Psr\Log\LoggerInterface
```

Foundation reset MUST NOT require new production observability classes when existing ports/noops are sufficient.

Tests SHOULD use in-test fake tracer, meter, and logger implementations with safe captured events.

Reset-specific observability usage is summary-only.

Reset span name:

```text
foundation.reset
```

The reset span name is validated by canonical span naming policy in:

```text
docs/ssot/observability.md
```

Allowed span attributes:

```text
services_count
groups_count
outcome
```

Reset metric names:

```text
foundation.reset_total
foundation.reset_duration_ms
```

Reset metric names and metric-specific labels are owned by the canonical metrics catalog in:

```text
docs/ssot/observability.md
```

Allowed reset metric labels:

```text
outcome
```

Allowed `outcome` values:

```text
ok
failed
```

No other metric label keys are introduced by enhanced reset.

The following MUST NOT be reset metric labels:

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

Logs, if emitted, MUST be summary-only.

Allowed log context:

```text
services_count
groups_count
outcome
```

Logs MUST NOT include per-service internals, service dumps, tag meta payloads, stack traces, raw config, raw context, or raw unit-of-work payloads by default.

## Observability failure policy

Internal observability port failures MUST remain safe.

If observability fails when no reset failure is already being surfaced, enhanced reset MAY surface:

```text
ResetException(code=CORETSIA_RESET_OBSERVABILITY_FAILED, message="reset-observability-failed")
```

If reset already failed, observability failure MUST NOT hide the reset failure.

The primary reset failure remains the deterministic reset exception for the reset problem.

## Enforcement rails

This document is doc-only.

It defines policy that MUST be enforced by owner package tests, integration checks, gates, and static analysis.

Expected enforcement rails include:

```text
framework/tools/gates/reserved_tags_registry_gate.php
framework/tools/gates/cross_cutting_contract_gate.php
framework/tools/gates/observability_span_naming_gate.php
framework/tools/gates/observability_metric_catalog_gate.php
phpstan rule: kernel.stateful ⇒ implements ResetInterface
```

`reserved_tags_registry_gate.php` enforces that framework-reserved DI tag identifiers are declared in `Coretsia\Foundation\Tag\ReservedTags` and that runtime package source does not define additional code-level registries for those identifiers.

`observability_span_naming_gate.php` validates reset span naming policy for `foundation.reset`.

`observability_metric_catalog_gate.php` validates that reset metric emissions use names and metric-specific labels registered in the canonical metrics catalog.

CI MUST fail if a service is tagged:

```text
kernel.stateful
```

but does not implement:

```text
Coretsia\Contracts\Runtime\ResetInterface
```

CI MUST fail if a service tagged `kernel.stateful` is not also discoverable through the effective Foundation reset discovery tag.

This check MAY be implemented by integration or wiring tests against resolved Foundation configuration.

Static analysis alone MUST NOT be the only required mechanism for effective-reset-tag discovery, because the effective reset tag is config-resolved from:

```text
foundation.reset.tag
```

The enforcement model is:

```text
kernel.stateful
→ implements ResetInterface
→ tagged with effective reset discovery tag
→ ResetOrchestrator can reset it after every UoW
```

Expected enhanced reset test evidence includes:

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
framework/packages/core/foundation/tests/Integration/PriorityResetEmitsSafeSummaryObservabilityTest.php
framework/packages/core/foundation/tests/Integration/ResetOrchestratorRejectsTaggedNonResettableServiceTest.php
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
- enhanced reset observability does not emit service ids, tag meta payloads, raw service payloads, or raw exception text through span attributes, metric labels, or log context;
- no new metric label keys are introduced;
- reset span name `foundation.reset` follows canonical span naming policy;
- reset metric names and metric-specific labels are registered in the canonical metrics catalog in `docs/ssot/observability.md`;
- `foundation.reset.group.default` config shape matches runtime group meta shape;
- `foundation.reset.enabled` is not introduced;
- `foundation.reset.observability.enabled` is not introduced.

## Security / redaction rules

Reset-related diagnostics MUST be safe.

Reset-related logs, exceptions, diagnostics, traces, metrics, generated artifacts, and public error surfaces MUST NOT include:

- Authorization headers;
- cookies;
- tokens;
- session ids;
- credentials;
- passwords;
- private keys;
- request payloads;
- response payloads;
- raw headers;
- raw queue messages;
- raw worker payloads;
- raw SQL;
- private customer data;
- direct user identifiers;
- absolute local paths;
- raw endpoints such as socket paths or `host:port` values unless hashed.

Allowed enhanced reset summary diagnostics are limited to:

```text
services_count
groups_count
ok|failed outcome
stable reset error code
stable fixed reset message token
```

Normalized group ids MAY be used for ordering and group-count calculation.

Normalized group ids MUST NOT be emitted as metric labels by enhanced reset.

Metric labels MUST remain governed by the canonical observability SSoTs.

Reset metric names and metric-specific labels MUST remain registered in the canonical metrics catalog in:

```text
docs/ssot/observability.md
```

Enhanced reset MUST NOT introduce metric label keys beyond:

```text
outcome
```

Reset diagnostics MUST prefer omission over unsafe emission.

## Code-level tag identifier note

Examples in this document may show raw tag strings to document canonical values and serialized/tag-registry payload shapes.

Runtime package source MUST use `Coretsia\Foundation\Tag\ReservedTags::*` as the only code-level identifier registry for framework-reserved DI tag identifiers.

## Examples

### Valid: default reset discovery tag

A stateful service is resettable and uses the reserved default reset discovery tag:

```text
service: App\Runtime\DeferredEventQueue
implements: Coretsia\Contracts\Runtime\ResetInterface
tags:
  - kernel.stateful
  - kernel.reset
```

This is valid when the effective Foundation reset discovery tag resolves to the reserved default:

```text
kernel.reset
```

The service is marked for enforcement and discoverable by reset orchestration.

### Valid: configured effective reset discovery tag

A runtime uses a non-default effective reset discovery tag:

```text
foundation.reset.tag = app.runtime.reset
```

A stateful service is registered as:

```text
service: App\Runtime\PerUowMemoizedLookup
implements: Coretsia\Contracts\Runtime\ResetInterface
tags:
  - kernel.stateful
  - app.runtime.reset
```

This is valid.

`kernel.stateful` remains the enforcement marker.

`app.runtime.reset` is the effective reset discovery tag used by `ResetOrchestrator`.

### Valid: enhanced reset meta

A runtime registers reset services with enhanced reset meta:

```php
$tagRegistry->add(
    tag: 'kernel.reset',
    serviceId: 'app.queue',
    priority: 0,
    meta: [
        'priority' => '100',
        'group' => 'queue',
    ],
);
```

In enhanced mode, this resolves to:

```text
effective priority: 100
effective group: queue
```

### Valid: unknown meta keys ignored in enhanced mode

```php
$tagRegistry->add(
    tag: 'kernel.reset',
    serviceId: 'app.cache',
    priority: 0,
    meta: [
        'priority' => 100,
        'group' => 'cache',
        'x' => 'y',
        'debug' => ['a' => 1],
    ],
);
```

This is valid.

Enhanced reset planning MUST compute ordering only from:

```text
priority
group
serviceId
```

Unknown keys MUST be ignored.

### Valid: disabled mode ignores invalid meta

```text
foundation.reset.priority.enabled = false
```

A reset tag entry may contain invalid enhanced reset meta:

```php
[
    'priority' => 'not-an-int',
    'group' => [],
]
```

Disabled mode MUST NOT parse or validate this meta.

Disabled mode MUST preserve exact:

```text
TagRegistry->all($effectiveResetTag)
```

order.

### Valid: enhanced reset ordering

Given:

| serviceId   | TaggedService.priority | meta.priority | meta.group | Effective priority | Effective group |
|-------------|------------------------|---------------|------------|--------------------|-----------------|
| app.queue   | 0                      | 100           | queue      | 100                | queue           |
| app.cache   | 0                      | 100           | cache      | 100                | cache           |
| app.context | 0                      | absent        | absent     | 0                  | default         |
| app.low     | 0                      | absent        | aaa        | 0                  | aaa             |

Enhanced order is:

```text
app.cache
app.queue
app.low
app.context
```

because ordering is:

```text
priority DESC
group ASC by strcmp()
serviceId ASC by strcmp()
```

### Invalid: stateful marker without ResetInterface

```text
service: App\Runtime\RequestState
implements: none
tags:
  - kernel.stateful
  - kernel.reset
```

This is invalid.

A service tagged `kernel.stateful` MUST implement:

```text
Coretsia\Contracts\Runtime\ResetInterface
```

### Invalid: ResetInterface but not discoverable by reset

```text
service: App\Runtime\PerUowCache
implements: Coretsia\Contracts\Runtime\ResetInterface
tags:
  - kernel.stateful
```

This is invalid.

The service is marked as stateful but is not discoverable through the effective Foundation reset discovery tag.

It may leak state across UoW boundaries.

### Invalid: using `kernel.stateful` as runtime reset list

```php
foreach ($tagRegistry->all('kernel.stateful') as $taggedService) {
    $container->get($taggedService->id())->reset();
}
```

This is forbidden.

`kernel.stateful` is an enforcement marker.

Runtime reset execution MUST use `ResetOrchestrator` and the effective Foundation reset discovery tag.

### Invalid: direct `kernel.reset` enumeration by Kernel runtime

```php
foreach ($tagRegistry->all('kernel.reset') as $taggedService) {
    $container->get($taggedService->id())->reset();
}
```

This is forbidden for Kernel runtime.

Kernel runtime MUST call:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

It MUST NOT enumerate reset tags directly.

### Invalid: enhanced priority meta

The following priority meta values are invalid in enhanced mode:

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

They MUST fail with:

```text
ResetException(code=CORETSIA_RESET_META_INVALID, message="reset-meta-invalid")
```

### Invalid: enhanced group meta

The following group meta values are invalid in enhanced mode:

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
[]
true
1
```

They MUST fail with:

```text
ResetException(code=CORETSIA_RESET_META_INVALID, message="reset-meta-invalid")
```

## Non-goals

This SSoT does not define:

- new DI tags;
- new reserved tag rows;
- new tag owners;
- tag registry table contents;
- general `TagRegistry` ordering;
- general `TagRegistry` dedupe policy;
- `ResetInterface` method shape;
- concrete Kernel runtime lifecycle implementation;
- concrete Foundation reset orchestrator internals;
- reset failure aggregation implementation;
- reset retry policy;
- reset timeout policy;
- platform HTTP reset behavior;
- platform CLI reset behavior;
- worker loop implementation;
- scheduler loop implementation;
- queue consumer implementation;
- concrete stateful service inventory for platform packages;
- config root registration;
- generated artifacts;
- new metric label keys;
- production observability backend implementations;
- plugin systems;
- reset extensibility systems.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Tag Registry](./tags.md)
- [DI Tags and Middleware Ordering SSoT](./di-tags-and-middleware-ordering.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [ContextStore lifecycle SSoT](./context-lifecycle.md)
- [Context Store SSoT](./context-store.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [ADR-0019: Enhanced reset for long-running services](../adr/ADR-0019-enhanced-reset-long-running.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
