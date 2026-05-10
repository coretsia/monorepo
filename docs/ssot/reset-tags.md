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

## Source-of-truth boundaries

This document owns only reset-specific semantics for already-canonical tags.

It does not own the canonical reserved tag registry. Tag names, ownership rows, reserved prefixes, and tag naming rules are owned by:

```text
docs/ssot/tags.md
```

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

This document MUST NOT redefine:

- reserved tag registry rows;
- reserved tag ownership;
- general `TagRegistry` ordering;
- general tag dedupe policy;
- `ResetInterface` method shape;
- `ResetOrchestrator` implementation internals;
- Kernel runtime lifecycle implementation;
- future enhanced reset implementation from epic `1.250.0`.

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

The fixed stateful-service enforcement marker is:

```text
kernel.stateful
```

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

`kernel.reset` is not a direct instruction for consumers to enumerate tagged services themselves.

Runtime consumers MUST NOT iterate `kernel.reset` services directly.

Runtime consumers MUST use the single Foundation reset executor:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

When documentation says `kernel.reset`, it is shorthand for the reserved default effective reset discovery tag value.

When runtime code needs the effective reset discovery tag, it MUST use the Foundation-owned config/wiring resolver.

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

Concrete reset failure semantics are owned by Foundation reset orchestration and future reset-planning epics.

## `kernel.stateful` enforcement marker

`kernel.stateful` is a fixed enforcement marker.

It is owned by `core/foundation`.

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

Runtime reset execution goes through:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

Kernel runtime is responsible for triggering reset after every UoW.

Foundation is responsible for the reusable reset executor.

Consumers MUST NOT:

- enumerate `kernel.reset` services directly;
- enumerate `kernel.stateful` services as a reset list;
- reconstruct reset discovery from class names;
- reconstruct reset discovery from container service ids;
- reconstruct reset discovery from provider internals;
- reconstruct reset discovery from package metadata;
- use reflection as a competing reset discovery mechanism;
- apply a competing reset ordering rule.

The expected lifecycle relationship is:

```text
Kernel after-UoW phase
→ ResetOrchestrator::resetAll()
→ TagRegistry->all($effectiveResetTag)
→ ResetInterface::reset()
→ next UoW starts without previous mutable state
```

## Discovery ordering boundary

Baseline reset discovery uses the exact list returned by:

```text
TagRegistry->all($effectiveResetTag)
```

In legacy/base mode, reset execution MUST preserve the exact `TagRegistry` order.

The canonical baseline discovery order is:

```text
priority DESC, id ASC
```

Consumers MUST NOT re-sort or re-dedupe the reset discovery list.

Enhanced reset planning from a later owner epic MAY introduce additional reset-specific ordering semantics.

Until that later owner epic is active, reset-specific consumers MUST NOT invent alternate ordering.

## Enforcement rails

This document is doc-only.

It defines policy that MUST be enforced by owner package tests, integration checks, gates, and static analysis.

Expected enforcement rails include:

```text
framework/tools/gates/cross_cutting_contract_gate.php
phpstan rule: kernel.stateful ⇒ implements ResetInterface
```

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

Allowed reset diagnostics include:

```text
services_count
ok|failed outcome
stable service id when safe
stable tag name
stable reason code
normalized group id if introduced by a later reset-planning owner
hash(value)
len(value)
```

Metric labels MUST remain governed by the canonical observability SSoTs.

Reset diagnostics MUST prefer omission over unsafe emission.

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
- enhanced reset planner implementation;
- reset failure aggregation implementation;
- reset exception taxonomy;
- reset retry policy;
- reset timeout policy;
- platform HTTP reset behavior;
- platform CLI reset behavior;
- worker loop implementation;
- scheduler loop implementation;
- queue consumer implementation;
- concrete stateful service inventory for platform packages;
- config root registration;
- generated artifacts.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Tag Registry](./tags.md)
- [DI Tags and Middleware Ordering SSoT](./di-tags-and-middleware-ordering.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [ContextStore lifecycle SSoT](./context-lifecycle.md)
- [Context Store SSoT](./context-store.md)
- [Observability Naming and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
