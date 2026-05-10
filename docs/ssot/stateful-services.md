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

# Stateful services SSoT

## Purpose

This document is the Single Source of Truth for Coretsia stateful-service policy.

It defines the policy layer above reset-specific tag semantics:

```text
services are stateless by default
stateful service → ResetInterface + kernel.stateful + effective reset discovery tag
```

The goal is to make it impossible to merge a stateful service without explicit reset discipline.

The core invariant is:

```text
no mutable per-UoW state may leak across UoW boundaries
```

A Unit of Work, abbreviated as UoW, may be an HTTP request, CLI command invocation, worker job, queue message, scheduler tick, or another owner-defined runtime boundary.

## Source-of-truth boundaries

This document owns stateful-service policy.

It does not own reset-tag semantics. Effective reset discovery tag rules, reserved default reset tag naming, `kernel.stateful` reset-specific invariant, reset diagnostics safety, and reset tag usage boundaries are owned by:

```text
docs/ssot/reset-tags.md
```

It does not own reset or hook contract shapes. Those are owned by:

```text
docs/ssot/uow-and-reset-contracts.md
```

It does not own ContextStore lifecycle usage. That is owned by:

```text
docs/ssot/context-lifecycle.md
```

It does not own ContextStore safe-write policy, safe value model, or context reset implementation. Those are owned by:

```text
docs/ssot/context-store.md
```

It does not own the canonical reserved tag registry. Tag names, tag ownership rows, reserved prefixes, and tag naming rules are owned by:

```text
docs/ssot/tags.md
```

This document MUST NOT redefine:

- reserved tag registry rows;
- tag ownership;
- general `TagRegistry` ordering;
- general tag dedupe policy;
- `ResetInterface` method shape;
- `ResetOrchestrator` implementation internals;
- ContextStore lifecycle policy;
- Kernel runtime implementation;
- platform HTTP implementation;
- platform CLI implementation;
- future enhanced reset implementation from epic `1.250.0`.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Definition

### Stateless by default

Services are stateless by default.

Stateless by default means:

```text
no per-UoW memory
no retained request data
no retained job/message data
no implicit mutable caches
no hidden process-local request singleton state
```

A stateless service may use:

- constructor-injected dependencies;
- immutable configuration;
- immutable constants;
- pure helper methods;
- local variables that disappear after a method returns.

A stateless service MUST NOT retain mutable unit-of-work-local values across calls in the same PHP process.

### Stateful

A service is stateful when it retains mutable in-memory state across calls within the same PHP process.

Examples of stateful behavior include:

- an in-memory event queue;
- an identity map;
- a memoized per-UoW cache;
- a deferred dispatch queue;
- accumulated diagnostics;
- a mutable current-request holder;
- a mutable current-job holder;
- a mutable buffer that is reused between calls.

A service is still stateful if the retained state is private and not exposed through public methods.

A service is still stateful if the retained state is held in:

- object properties;
- static properties;
- static local variables;
- global variables;
- closures capturing mutable values;
- process-level registries;
- singleton instances.

## Default rule: stateless by default

New runtime services MUST be designed as stateless unless there is a documented need for mutable in-process state.

A service MUST NOT introduce retained mutable state as an optimization unless the owning package also introduces reset discipline.

A contributor adding retained mutable state MUST answer all of the following:

1. What state is retained?
2. Why can it not be a local variable?
3. Is the state per-UoW, per-process, or immutable?
4. How is the state cleared after each UoW?
5. Which service implements `ResetInterface`?
6. Which service is tagged with `kernel.stateful`?
7. Which service is discoverable through the effective Foundation reset discovery tag?
8. Which gate, test, or static rule prevents drift?

If these questions cannot be answered, the service MUST remain stateless.

## Stateful service requirements

Any service that retains mutable per-UoW state MUST:

1. implement:

```text
Coretsia\Contracts\Runtime\ResetInterface
```

2. be explicitly tagged with the fixed enforcement marker:

```text
kernel.stateful
```

3. be discoverable through the effective Foundation reset discovery tag resolved from:

```text
foundation.reset.tag
```

4. be reset after every UoW through:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

5. make reset behavior deterministic;
6. avoid leaking secrets, payloads, raw transport data, or direct user identifiers through stored state or diagnostics;
7. document any transient secret-bearing state explicitly.

With the reserved default Foundation configuration, a stateful service is typically registered with both tags:

```text
kernel.stateful
kernel.reset
```

When `foundation.reset.tag` is changed, the reset discovery tag is the resolved effective tag value, not necessarily the literal `kernel.reset`.

## Reset discipline

Stateful services MUST participate in reset discipline.

Reset discipline means:

```text
UoW finishes
→ Kernel runtime triggers Foundation reset orchestration
→ ResetOrchestrator discovers services through the effective reset discovery tag
→ ResetInterface::reset() is called
→ next UoW cannot observe previous mutable state
```

The effective reset discovery tag is resolved from:

```text
foundation.reset.tag
```

The reserved default value is:

```text
kernel.reset
```

Kernel runtime MUST trigger reset after every UoW.

Kernel runtime MUST NOT enumerate reset-tagged services directly.

Kernel runtime MUST call the single Foundation reset executor:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

Runtime reset execution MUST NOT use `kernel.stateful` as the reset execution list.

`kernel.stateful` is an enforcement marker, not a runtime discovery mechanism.

## Tagging requirements

The fixed enforcement marker is:

```text
kernel.stateful
```

The effective reset discovery tag is resolved from:

```text
foundation.reset.tag
```

The reserved default effective reset discovery tag is:

```text
kernel.reset
```

The canonical stateful-service rule is:

```text
kernel.stateful
⇒ implements Coretsia\Contracts\Runtime\ResetInterface
&& discoverable through the effective Foundation reset discovery tag
```

A service tagged `kernel.stateful` but missing `ResetInterface` is invalid.

A service tagged `kernel.stateful` but missing discovery through the effective Foundation reset discovery tag is invalid.

A service implementing `ResetInterface` but not retaining state is allowed, but SHOULD be avoided unless the reset capability has a clear owner reason.

A service retaining per-UoW state but not tagged `kernel.stateful` is invalid.

A service retaining per-UoW state but not discoverable through the effective reset discovery tag is invalid.

## Runtime usage boundary

Runtime code MUST NOT use `kernel.stateful` for reset execution.

Runtime code MUST NOT manually enumerate:

```text
kernel.reset
kernel.stateful
```

Runtime code MUST NOT reconstruct reset discovery from:

- service class names;
- container service ids;
- provider internals;
- package metadata;
- reflection;
- naming conventions;
- inheritance patterns.

Runtime reset execution MUST go through:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

This document does not define `ResetOrchestrator` internals.

Reset-specific execution rules are owned by:

```text
docs/ssot/reset-tags.md
```

## Determinism and idempotency

Reset MUST be deterministic.

For the same service state and same reset sequence, reset behavior SHOULD be stable across operating systems and runs.

Reset implementations MUST NOT depend on:

- process locale;
- filesystem traversal order;
- nondeterministic map ordering;
- raw current time for cleanup decisions;
- random values for cleanup decisions.

Stateful services SHOULD make repeated reset calls on already-clean state safe and idempotent where feasible.

This MUST NOT be interpreted as:

```text
reset can never throw
```

Reset may throw if cleanup fails or if a service detects an invalid internal state.

Reset failure semantics are owned by Foundation reset orchestration and later reset-planning epics.

Error reporting MUST remain safe and deterministic.

## Forbidden state

The following patterns are forbidden for per-UoW state:

- static global caches that persist across UoW;
- static local variables used as request/job memory;
- process-wide mutable registries containing request/job data;
- request singletons without reset discipline;
- current-user/current-tenant/current-request holders without reset discipline;
- raw request/response object retention after UoW;
- raw queue message retention after UoW;
- raw worker payload retention after UoW;
- retained Authorization values;
- retained cookies;
- retained session ids;
- retained tokens;
- retained raw headers;
- retained raw payloads;
- retained direct user identifiers;
- retained private customer data.

Static immutable constants are not stateful.

Immutable shared metadata is not stateful when it cannot contain per-UoW data and cannot mutate across calls.

Process-level caches MAY exist only when they are explicitly documented as non-UoW state and cannot contain request, job, tenant, actor, payload, token, or secret data.

## Security / redaction

A stateful service MUST NOT retain secrets or PII beyond a UoW.

If transient secret-bearing state is unavoidable, the service MUST:

1. document the state explicitly;
2. keep the value in memory for the shortest possible scope;
3. wipe or remove the value during `reset()`;
4. avoid exposing the value in logs, exceptions, metrics, traces, generated artifacts, diagnostics, or error descriptors.

Stateful services MUST NOT retain:

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

Allowed safe diagnostics include:

```text
services_count
items_count
ok|failed outcome
stable reason code
stable service id when safe
hash(value)
len(value)
```

Diagnostics MUST prefer omission over unsafe emission.

Metric labels remain governed by the canonical observability SSoTs and MUST NOT be expanded by this document.

## Examples

### Valid: in-memory queue cleared on reset

```php
<?php

declare(strict_types=1);

namespace App\Runtime;

use Coretsia\Contracts\Runtime\ResetInterface;

final class DeferredEventQueue implements ResetInterface
{
    /**
     * @var list<object>
     */
    private array $events = [];

    public function push(object $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<object>
     */
    public function drain(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    public function reset(): void
    {
        $this->events = [];
    }
}
```

Conceptual registration:

```text
service: App\Runtime\DeferredEventQueue
implements: Coretsia\Contracts\Runtime\ResetInterface
tags:
  - kernel.stateful
  - <effective reset discovery tag>
```

With the reserved default Foundation configuration, `<effective reset discovery tag>` is:

```text
kernel.reset
```

This is valid.

The service retains mutable state and clears it on reset.

### Valid: memoized per-UoW cache cleared on reset

```php
<?php

declare(strict_types=1);

namespace App\Runtime;

use Coretsia\Contracts\Runtime\ResetInterface;

final class PerUowLookupCache implements ResetInterface
{
    /**
     * @var array<string, string>
     */
    private array $values = [];

    public function remember(string $key, string $value): void
    {
        $this->values[$key] = $value;
    }

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function reset(): void
    {
        $this->values = [];
    }
}
```

Conceptual registration:

```text
service: App\Runtime\PerUowLookupCache
implements: Coretsia\Contracts\Runtime\ResetInterface
tags:
  - kernel.stateful
  - <effective reset discovery tag>
```

This is valid only when the cache is per-UoW and is cleared after each UoW.

### Invalid: static global cache persisting across UoW

```php
<?php

declare(strict_types=1);

namespace App\Runtime;

final class StaticRequestCache
{
    /**
     * @var array<string, mixed>
     */
    private static array $cache = [];

    public function put(string $key, mixed $value): void
    {
        self::$cache[$key] = $value;
    }

    public function get(string $key): mixed
    {
        return self::$cache[$key] ?? null;
    }
}
```

This is invalid for per-UoW state.

The state persists across calls in the same PHP process and has no reset discipline.

A static mutable cache may leak data between UoW boundaries.

### Invalid: request singleton without reset discipline

```php
<?php

declare(strict_types=1);

namespace App\Runtime;

final class CurrentRequestHolder
{
    private ?object $request = null;

    public function set(object $request): void
    {
        $this->request = $request;
    }

    public function get(): ?object
    {
        return $this->request;
    }
}
```

This is invalid.

The service retains a request object and does not implement reset discipline.

Request objects, response objects, queue messages, worker payloads, headers, cookies, Authorization values, tokens, and session ids MUST NOT survive past a UoW.

### Invalid: `kernel.stateful` without ResetInterface

```php
<?php

declare(strict_types=1);

namespace App\Runtime;

final class RequestState
{
    private array $state = [];

    public function put(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }
}
```

Conceptual registration:

```text
service: App\Runtime\RequestState
implements: none
tags:
  - kernel.stateful
  - <effective reset discovery tag>
```

This is invalid.

A service tagged `kernel.stateful` MUST implement:

```text
Coretsia\Contracts\Runtime\ResetInterface
```

### Invalid: ResetInterface but missing effective reset discovery

```php
<?php

declare(strict_types=1);

namespace App\Runtime;

use Coretsia\Contracts\Runtime\ResetInterface;

final class IdentityMap implements ResetInterface
{
    private array $entities = [];

    public function reset(): void
    {
        $this->entities = [];
    }
}
```

Conceptual registration:

```text
service: App\Runtime\IdentityMap
implements: Coretsia\Contracts\Runtime\ResetInterface
tags:
  - kernel.stateful
```

This is invalid.

The service is marked as stateful, but it is not discoverable through the effective Foundation reset discovery tag.

It may leak state because `ResetOrchestrator` cannot discover it.

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

The minimum enforcement model is:

```text
stateful service
→ kernel.stateful
→ implements ResetInterface
→ tagged with effective reset discovery tag
→ ResetOrchestrator can reset it after every UoW
```

The gate or rule SHOULD also detect obvious forbidden state patterns where statically enforceable, including:

- stateful service missing `kernel.stateful`;
- `kernel.stateful` without `ResetInterface`;
- `kernel.stateful` without effective reset discovery;
- direct runtime enumeration of `kernel.stateful`;
- direct Kernel runtime enumeration of `kernel.reset`;
- static mutable per-UoW caches;
- retained request/response/transport objects;
- retained headers/cookies/Authorization/tokens/session ids;
- unsafe reset diagnostics.

## Runtime package README linkage

Runtime package READMEs that introduce stateful services SHOULD reference this document.

At minimum, a runtime package README SHOULD reference this document when the package contains any service that:

- implements `ResetInterface`;
- is tagged `kernel.stateful`;
- is discoverable through the effective reset discovery tag;
- retains mutable in-memory state;
- owns a per-UoW queue, cache, buffer, holder, registry, identity map, or deferred dispatch list.

README references are documentation linkage only.

They do not replace enforcement rails.

## Non-goals

This SSoT does not define:

- new DI tags;
- new reserved tag rows;
- new tag owners;
- full tag registry table contents;
- concrete Foundation reset orchestrator internals;
- concrete Kernel runtime lifecycle implementation;
- concrete platform HTTP stateful service inventory;
- concrete platform CLI stateful service inventory;
- worker loop implementation;
- scheduler loop implementation;
- queue consumer implementation;
- concrete reset failure aggregation;
- concrete reset retry policy;
- concrete reset timeout policy;
- reset exception taxonomy;
- reset telemetry schema;
- reset metric labels;
- package-specific state inventories;
- DI container registration API;
- generated artifacts;
- config roots;
- config keys.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Reset Tags SSoT](./reset-tags.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [ContextStore lifecycle SSoT](./context-lifecycle.md)
- [Context Store SSoT](./context-store.md)
- [Tag Registry](./tags.md)
- [DI Tags and Middleware Ordering SSoT](./di-tags-and-middleware-ordering.md)
- [Observability Naming and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
