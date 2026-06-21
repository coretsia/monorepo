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

# ContextStore lifecycle SSoT

## Purpose

This document is the Single Source of Truth for runtime `ContextStore` lifecycle usage across Coretsia runtime boundaries.

It defines the lifecycle invariant:

```text
1 Unit of Work = 1 logical context
```

A unit of work may be an HTTP request, CLI command invocation, worker job, queue message, scheduler tick, or another owner-defined runtime boundary.

The lifecycle model exists to guarantee that:

- base context keys are written at the beginning of each unit of work;
- runtime owners may enrich context only through canonical safe keys;
- context values never leak from one unit of work into the next;
- Foundation reset orchestration clears unit-of-work-local state after every unit of work;
- ContextStore contents are not automatically valid observability output.

## Source-of-truth boundaries

This document owns only lifecycle usage policy.

It does not own the canonical context key registry. The key registry is owned by:

```text
docs/ssot/context-keys.md
```

It does not own the `ContextStore`, `ContextBag`, or `ContextStorePolicy` implementation/write-policy details. Those are owned by:

```text
docs/ssot/context-store.md
```

It does not own observability export rules, metric naming, metric label allowlists, span naming, or redaction policy. Those are owned by:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
```

It does not own HTTP middleware slot taxonomy, slot ownership, slot ordering, or middleware catalog contents. Those are owned by:

```text
docs/ssot/http-middleware-catalog.md
```

It does not own runtime hook/reset contract shapes. Those are owned by:

```text
docs/ssot/uow-and-reset-contracts.md
```

This document may repeat canonical key names and middleware slot names only to explain lifecycle flow.

It MUST NOT redefine:

- full context key registry semantics;
- full `ContextStorePolicy` write validation semantics;
- HTTP middleware slot ownership;
- HTTP middleware ordering;
- HTTP middleware implementation details;
- Kernel runtime implementation details;
- platform HTTP implementation details.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Core lifecycle invariant

Runtime context is unit-of-work-local.

The canonical invariant is:

```text
1 UoW = 1 logical context
```

`ContextStore` values MUST NOT leak across units of work.

At the start of each unit of work, the runtime owner MUST establish a fresh logical context.

At or after the end of each unit of work, the runtime owner MUST trigger Foundation reset orchestration before the next unit of work can observe stale state.

The next unit of work MUST NOT see any `ContextStore` values written by the previous unit of work.

## Unit of Work lifecycle

The canonical lifecycle is:

1. Kernel runtime begins a unit of work.
2. Kernel runtime writes base context keys.
3. Runtime owners may enrich context with safe canonical keys.
4. Runtime readers read context through the contracts accessor port.
5. Kernel runtime finishes the unit of work.
6. Kernel runtime runs after-UoW lifecycle handling.
7. Kernel runtime calls Foundation reset orchestration.
8. `ContextStore::reset()` clears all context.
9. The next unit of work starts with no previous context values.

The canonical read port is:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
```

Runtime readers SHOULD depend on the context accessor port instead of the concrete store.

Runtime writers MUST write only through Foundation-owned controlled mutation APIs and only with keys accepted by `ContextStorePolicy`.

## Base context keys

Kernel runtime owns base context key writes at begin-UoW.

At begin-UoW, Kernel runtime MUST write:

```text
correlation_id
uow_id
uow_type
```

These keys are unit-of-work-local.

They MUST be cleared by `ContextStore::reset()` after the unit of work.

`correlation_id` is correlation-safe but remains subject to observability export policy.

`uow_id` is a safe opaque unit-of-work id.

`uow_type` is a stable runtime category such as:

```text
http
cli
worker
queue
scheduler
```

or another owner-defined safe category.

Kernel runtime MUST NOT write secrets, raw transport objects, request payloads, response payloads, raw headers, cookies, Authorization values, tokens, or session identifiers into `ContextStore`.

## Runtime enrichment writes

Runtime owners MAY enrich context after base keys are established.

Runtime enrichment is allowed only when all of the following are true:

- the key is declared by `Coretsia\Foundation\Context\ContextKeys`;
- the value is accepted by `ContextStorePolicy`;
- the value is unit-of-work-local;
- the value is safe for in-process runtime context;
- the value does not violate security/redaction rules;
- the write belongs to the responsible owner category.

Runtime enrichment MUST NOT create ad hoc context keys.

No new context keys may be introduced outside:

```text
Coretsia\Foundation\Context\ContextKeys
docs/ssot/context-keys.md
```

Runtime enrichment MUST NOT use `ContextStore` as a generic request dump, debug bag, payload cache, session store, auth token store, or transport object registry.

## HTTP context enrichment

HTTP context enrichment is optional and owner-controlled.

If enabled by a future HTTP owner, HTTP request context enrichment MAY write only declared safe HTTP keys, including:

```text
client_ip
scheme
host
path
user_agent
request_id
http_response_format
```

HTTP enrichment MUST NOT write:

- raw headers;
- raw cookies;
- Authorization values;
- tokens;
- session ids;
- request bodies;
- response bodies;
- raw query strings;
- raw payloads;
- transport request objects;
- transport response objects.

`path` MAY exist as in-process context when deliberately written by the HTTP owner.

`path` MUST NOT include the query string.

`path` MUST NOT be exported as a raw value to logs, spans, metrics, public diagnostics, generated artifacts, or error descriptor extensions.

When path-like data is needed for observability, owners SHOULD use one of:

```text
path_template
hash(value)
len(value)
```

subject to the owning observability policy.

## Routing/auth/tenancy context enrichment

Routing owners MAY write the Phase 1 routing context key:

```text
path_template
```

`path_template` is preferred over raw `path` for low-cardinality diagnostics.

`path_template` MUST NOT include concrete user-controlled path parameter values.

Auth owners MAY write the Phase 2 auth context key:

```text
actor_id
```

`actor_id` MUST be an opaque safe id.

`actor_id` MUST NOT be an email address, phone number, full name, username, external account id, token, session id, or another direct user identifier.

Tenancy owners MAY write the Phase 6+ tenancy context key:

```text
tenant_id
```

`tenant_id` MUST be an opaque safe id.

`tenant_id` MUST NOT expose customer names, domains, billing ids, external account ids, tokens, private customer data, or tenancy credentials.

These routing/auth/tenancy keys may be reserved in `ContextKeys` before concrete writers exist.

Reserved key presence prevents name drift; it does not imply that a writer is implemented.

## Safe values and hard bans

Context values MUST obey the safe-value model from:

```text
docs/ssot/context-store.md
```

Allowed values are JSON-safe deterministic values only:

```text
null
bool
int
string
list<value>
array<string,value>
```

Floats are forbidden, including:

```text
NaN
INF
-INF
```

Objects, closures, resources, non-string map keys, raw transport objects, service instances, and runtime wiring objects are forbidden.

ContextStore MUST NOT contain:

- Authorization values;
- cookies;
- session ids;
- tokens in any form;
- raw request payloads;
- raw response payloads;
- raw headers, except explicitly allowlisted safe metadata represented through canonical keys;
- raw SQL;
- credentials;
- passwords;
- private keys;
- private customer data;
- direct user identifiers beyond explicitly safe ids introduced by owner SSoTs.

Allowed context values are limited to:

- safe opaque ids;
- normalized request metadata;
- safe bounded strings;
- `hash(value)` or `len(value)` when the raw value would be unsafe;
- other values explicitly allowed by the owning SSoT and `ContextStorePolicy`.

Writers MUST prefer omission over unsafe storage.

## Observability export boundary

Presence of a key in `ContextStore` MUST NOT be interpreted as permission to export it to observability.

ContextStore is in-process runtime state.

Observability export remains governed by:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
```

The following context keys are forbidden as metric labels under the baseline policy:

```text
correlation_id
request_id
tenant_id
user_id
path
```

Metric labels MUST remain within the canonical allowlist from `docs/ssot/observability.md`.

Raw request data MUST NOT be emitted to logs, metrics, span names, span attributes, public diagnostics, generated artifacts, or error descriptor extensions.

Forbidden observability output includes:

- raw path;
- raw query;
- headers;
- cookies;
- request bodies;
- response bodies;
- auth identifiers;
- session identifiers;
- tokens;
- raw SQL;
- private customer data;
- high-cardinality user-controlled values.

Safe ids MAY appear in logs or tracing correlation only when the relevant owner policy allows that use.

Safe ids MUST NOT be metric labels unless a future observability SSoT explicitly permits them.

## Reset execution

Foundation reset uses the effective reset discovery tag configured by:

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

In this document, `kernel.reset` is used only as the reserved default-name shorthand.

Runtime consumers MUST NOT enumerate reset tags directly.

Runtime consumers MUST use the single Foundation reset executor:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

### Who triggers reset?

Reset is triggered by Kernel runtime exactly once per unit of work.

The expected lifecycle is:

1. Kernel runtime runs `AfterUoW` hooks discovered through:

```text
kernel.hook.after_uow
```

2. Kernel runtime calls the single Foundation reset executor:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

Kernel runtime MUST NOT iterate `kernel.reset` tagged services by itself.

Kernel runtime MUST NOT use `kernel.stateful` as an execution mechanism.

`kernel.stateful` is an enforcement marker, not the runtime reset discovery mechanism.

### Reset formula

The lifecycle formula is:

```text
KernelRuntime afterUoW phase
→ ResetOrchestrator::resetAll()
→ effective reset discovery tag from foundation.reset.tag
→ reserved default value: kernel.reset (`ReservedTags::KERNEL_RESET`)
→ ContextStore::reset()
→ next UoW starts without previous values
```

### What does ResetOrchestrator do?

`ResetOrchestrator` discovers resettable services through `TagRegistry` using the effective Foundation reset discovery tag:

```text
foundation.reset.tag
```

with reserved default:

```text
kernel.reset
```

Code-level identifier:

```text
Coretsia\Foundation\Tag\ReservedTags::KERNEL_RESET
```

For each discovered service id, `ResetOrchestrator` resolves the service through the PSR-11 container and calls:

```text
Coretsia\Contracts\Runtime\ResetInterface::reset()
```

Reset execution ordering MUST be deterministic.

Execution ordering is deterministic and single-choice:

- when `foundation.reset.priority.enabled=false`, reset executes in the exact order returned by:

```text
TagRegistry->all($effectiveResetTag)
```

with canonical discovery order:

```text
priority DESC, id ASC
```

- when `foundation.reset.priority.enabled=true`, reset executes in the planned enhanced reset order:

```text
priority DESC, group ASC, serviceId ASC
```

Consumers MUST NOT invent alternate ordering.

Failure semantics MUST be deterministic.

A service discovered for reset that does not implement `ResetInterface` is misuse and MUST hard-fail safely.

Failure messages MUST be safe and MUST NOT include payloads, paths, headers, cookies, Authorization values, tokens, session ids, secrets, or raw context values.

Reset MUST still run if an `after-uow` hook throws.

The original failure MUST be surfaced only after the reset attempt completes.

If after-UoW handling fails and reset also fails, the owning Kernel runtime must surface failure deterministically according to its own later runtime policy.

This document does not define concrete Kernel exception aggregation behavior.

### What does reset clear?

Typical stateful services discovered through the effective reset discovery tag include:

- `Coretsia\Foundation\Context\ContextStore`;
- long-running buffers or queues when introduced by owner packages;
- deferred dispatch queues when introduced by owner packages;
- observability batch processors or buffers when introduced by owner packages.

`ContextStore::reset()` MUST clear context between units of work.

### What must never happen

The following states are policy violations:

- a unit of work starts and can observe previous unit-of-work `ContextStore` values;
- a stateful service keeps per-request payloads, headers, cookies, Authorization values, tokens, session ids, or secrets in memory after reset;
- reset execution prints, logs, traces, exports, or serializes raw context data;
- Kernel runtime directly iterates `kernel.reset` services instead of using `ResetOrchestrator`;
- runtime execution depends on `kernel.stateful` as a reset execution list.

## HTTP middleware slot taxonomy reference

The canonical HTTP middleware slot taxonomy is owned by:

```text
docs/ssot/http-middleware-catalog.md
```

This document references the slot taxonomy only to explain lifecycle examples.

It MUST NOT redefine slot ownership, slot ordering, middleware placement, or catalog contents.

The canonical slot tag names are:

```text
http.middleware.system_pre
http.middleware.system
http.middleware.system_post
http.middleware.app_pre
http.middleware.app
http.middleware.app_post
http.middleware.route_pre
http.middleware.route
http.middleware.route_post
```

The corresponding framework-reserved DI tag identifiers are declared in:

```text
Coretsia\Foundation\Tag\ReservedTags
```

Runtime package source MUST use `ReservedTags::*` for these identifiers.

These names are TagRegistry tag names.

The config namespace:

```text
http.middleware.auto
http.middleware.auto.*
```

is not a tag namespace.

`http.middleware.auto` and `http.middleware.auto.*` MUST NOT be used as TagRegistry tag names.

Rationale: the `http.middleware.auto.*` namespace is a configuration-key namespace under the `http.*` config subtree. It must not collide with the `http.middleware.*` tag namespace.

## Examples

### Example A: valid HTTP request UoW

1. Kernel runtime begins an HTTP request unit of work.
2. Kernel runtime writes base keys:

```text
correlation_id
uow_id
uow_type
```

3. `uow_type` is set to a stable safe value such as:

```text
http
```

4. HTTP middleware enriches only safe declared context keys, for example:

```text
client_ip
scheme
host
path
user_agent
request_id
http_response_format
```

5. Routing may later write:

```text
path_template
```

6. Middleware and runtime readers read context through:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
```

7. Logs, metrics, spans, and errors follow observability SSoTs and do not treat ContextStore presence as export permission.
8. After the unit of work, Kernel runtime triggers Foundation reset orchestration through `ResetOrchestrator`.
9. `ResetOrchestrator` uses the effective reset discovery tag from `foundation.reset.tag`, whose reserved default is `kernel.reset` (`ReservedTags::KERNEL_RESET`).
10. `ContextStore::reset()` clears all context.
11. The next unit of work cannot observe previous request values.

This is valid.

### Example B: valid CLI command UoW

1. Kernel runtime begins a CLI command unit of work.
2. Kernel runtime writes base keys:

```text
correlation_id
uow_id
uow_type
```

3. `uow_type` is set to a stable safe value such as:

```text
cli
```

4. No HTTP-only keys are written.
5. CLI runtime readers may read base context through `ContextAccessorInterface`.
6. After the unit of work, reset still runs through `ResetOrchestrator`.
7. `ContextStore::reset()` clears all context before the next CLI command, worker job, scheduler tick, or other unit of work.

This is valid.

### Example C: invalid context leak across UoW

1. HTTP request A writes:

```text
correlation_id
uow_id
uow_type
path
```

2. HTTP request A finishes.
3. Reset does not run, or `ContextStore::reset()` is skipped.
4. HTTP request B starts.
5. HTTP request B can observe request A values.

This is a policy violation.

A unit of work MUST NOT observe previous unit-of-work context values.

### Example D: invalid secret or PII in ContextStore

The following writes are forbidden:

```text
Authorization
Cookie
session_id
access_token
refresh_token
raw_request_body
raw_response_body
raw_headers
email
phone
full_name
```

Writing Authorization values, cookies, session ids, tokens, raw payloads, raw headers, or direct user identifiers to `ContextStore` is a hard-ban violation.

Writers MUST use safe opaque ids, omission, `hash(value)`, or `len(value)` when owner policy allows those safe representations.

## Enforcement rails

This document is doc-only.

It defines policy that must be enforced by owner package tests and gates.

Expected enforcement rails include:

```text
framework/packages/core/kernel/tests/Integration/KernelRuntimeAlwaysResetsAfterUowTest.php
framework/tools/gates/cross_cutting_contract_gate.php
```

The Kernel integration test MUST prove the runtime reset invariant:

```text
afterUoW
→ ResetOrchestrator::resetAll()
→ ContextStore is empty for the next UoW
```

The cross-cutting contract gate MUST validate forbidden context writes and lifecycle-policy drift where that is statically enforceable.

The gate/test should check at least:

- no forbidden ContextKeys writes;
- no raw secrets, headers, cookies, Authorization values, tokens, session ids, or payloads are written to ContextStore;
- raw `path` is not exported as a metric label or raw observability value;
- runtime reset uses `ResetOrchestrator`;
- runtime reset does not enumerate `kernel.reset` services directly;
- context reset occurs after every unit of work.

Concrete test/gate implementation is owned by the corresponding owner epics.

## Non-goals

This SSoT does not define:

- concrete Kernel runtime implementation;
- concrete HTTP middleware writers;
- middleware-to-key FQCN matrix;
- HTTP middleware slot ownership;
- HTTP middleware ordering;
- HTTP request id policy;
- HTTP correlation header extraction;
- HTTP correlation header injection;
- transport-specific propagation policy;
- platform logging implementation;
- platform tracing implementation;
- platform metrics implementation;
- platform error mapping;
- platform problem-details rendering;
- generated artifacts;
- config roots;
- config keys;
- feature toggles;
- session storage;
- auth identity storage;
- tenancy implementation;
- business context models;
- user profile models.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Context Keys SSoT](./context-keys.md)
- [Context Store SSoT](./context-store.md)
- [HTTP Middleware Catalog SSoT](./http-middleware-catalog.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [Tag Registry](./tags.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [Middleware → ContextKeys map](./middleware-context-keys-map.md)
