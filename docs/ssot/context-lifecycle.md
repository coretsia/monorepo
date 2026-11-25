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

```yaml
ssotVersion: 1
status: pre-stable
owner: core/foundation
```

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
- successful canonical reset prevents unit-of-work-local context values from leaking into the next unit of work;
- Kernel runtime triggers Foundation reset orchestration exactly once for each UnitOfWork that crosses the reset-responsibility boundary;
- ContextStore contents are not automatically valid observability output.

## Source-of-truth boundaries

This document owns only lifecycle usage policy.

It does not own the canonical context key registry. The key registry is owned by:

```text
docs/ssot/context-keys.md
Coretsia\Contracts\Context\ContextKeys
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

It does not own HTTP-specific request-id configuration, identifier validation, upstream acceptance, generation, or response-header policy. Those are owned by:

```text
docs/ssot/http-middleware-wiring.md
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

The complementary runtime-boundary invariant is:

```text
1 KernelRuntime boundary
=
0 or 1 active UoW
```

Two overlapping UnitOfWork lifecycles MUST NOT share one `KernelRuntime` boundary and mutate or read the same UnitOfWork-local `ContextStore` state.

`ContextStore` values MUST NOT leak across units of work.

At the start of each unit of work, the runtime owner MUST establish a fresh logical context.

Kernel runtime MUST trigger Foundation reset orchestration exactly once for each UnitOfWork that crosses the reset-responsibility boundary.

A failed begin attempt before that boundary or a rejected overlapping UnitOfWork start MUST NOT trigger a new reset.

The next unit of work MUST NOT see any `ContextStore` values written by the previous unit of work.

## Unit of Work lifecycle

For a UnitOfWork that reaches after-UoW lifecycle handling and whose canonical reset succeeds, the canonical context lifecycle is:

1. Kernel runtime begins a unit of work.
2. Kernel runtime writes base context keys through `ContextWriterInterface`.
3. Runtime owners may enrich context with safe canonical keys through `ContextWriterInterface`.
4. Runtime context readers read approved individual keys through `ContextAccessorInterface`, including restricted HTTP owner-local request identity verification.
5. Kernel runtime finishes the unit of work.
6. Kernel runtime runs after-UoW lifecycle handling.
7. Kernel runtime calls Foundation reset orchestration.
8. `ContextStore::reset()` clears all context.
9. The next unit of work starts with no previous context values.

The next UnitOfWork MUST NOT start until the current lifecycle has completed its reset responsibility.

Completion of reset responsibility requires completion of the canonical reset attempt. It does not establish successful context cleanup when reset execution fails.

A failed reset MUST NOT leave the Kernel runtime boundary permanently reserved.

Runtime-boundary release and failure precedence remain governed by `docs/ssot/uow-outcome-policy.md`.

A failed reset MUST NOT be treated as proof of successful context cleanup.

The canonical read and mutation ports are separate:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
Coretsia\Contracts\Context\ContextWriterInterface
```

Runtime context consumers outside Foundation MUST use the appropriate contracts-level port, not concrete `ContextStore`. Both ports alias the same shared Foundation-owned store. Writers MUST set or remove only keys assigned to their semantic owner boundary, with values accepted by `ContextStorePolicy`. Neither port grants additional ownership, access to snapshots, bulk mutation, clearing, or reset execution.

The HTTP request identity owner `Coretsia\Platform\Http\RequestId\RequestIdPolicy` MUST write `ContextKeys::REQUEST_ID` through `ContextWriterInterface` and read back only that key through `ContextAccessorInterface` solely for final-response identity verification. This is owner-local verification, not permission to read unrelated context keys or change the selected identifier. See `docs/ssot/context-store.md`.

## Base context keys

Kernel runtime owns base context key writes at begin-UoW through `ContextWriterInterface`.

At begin-UoW, Kernel runtime MUST write:

```text
correlation_id
uow_id
uow_type
```

These keys are unit-of-work-local.

They MUST be cleared by `ContextStore::reset()` after the unit of work.

For the canonical Kernel runtime, reset responsibility starts only after these base context keys have been written successfully.

If Kernel runtime fails before these keys are written, Foundation reset orchestration MUST NOT be invoked for that failed begin attempt.

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

- the key is declared by `Coretsia\Contracts\Context\ContextKeys`;
- the value is accepted by `ContextStorePolicy`;
- the value is unit-of-work-local;
- the value is safe for in-process runtime context;
- the value does not violate security/redaction rules;
- the write belongs to the responsible owner category.

Runtime enrichment MUST NOT create ad hoc context keys.

No new context keys may be introduced outside the canonical key registry:

```text
docs/ssot/context-keys.md
Coretsia\Contracts\Context\ContextKeys
```

Runtime enrichment MUST NOT use `ContextStore` as a generic request dump, debug bag, payload cache, session store, auth token store, or transport object registry.

## HTTP request identity preparation

HTTP request identity preparation is optional and controlled by the HTTP request-id policy.

When enabled, `Coretsia\Platform\Http\RequestId\RequestIdPolicy` MUST select and write:

```text
request_id
```

Request identity preparation MUST occur after successful Kernel begin-UoW, including before-UoW lifecycle handling, and before the entire PSR-15 pipeline executes.

It MUST NOT be implemented as middleware-based context enrichment.

The selected request identifier MUST remain available throughout the HTTP pipeline and eligible after-UoW lifecycle handling on conforming execution paths.

Subsequent middleware, terminal handlers, and lifecycle hooks MUST NOT modify, replace, or remove the selected request identifier.

After the entire PSR-15 pipeline returns a response, HTTP response finalization MUST verify the current owner-local request identity against the invocation-local selected identifier before applying the configured request-id response header.

This verification MUST use the owner-local `ContextAccessorInterface` read-back of `ContextKeys::REQUEST_ID` defined by `docs/ssot/context-store.md`.

If request identity preparation or response finalization fails after successful Kernel begin-UoW, the HTTP runtime MUST use its canonical fatal-error completion path.

Kernel runtime remains responsible for eligible after-UoW lifecycle handling and Foundation reset orchestration.

When request identity preparation is disabled, the policy MUST NOT read or write `request_id` and MUST NOT mutate the request-id response header.

HTTP request identity MUST NOT become part of the Kernel-owned base context or UnitOfWork result payload.

Successful canonical Foundation reset MUST clear `request_id` together with all other unit-of-work-local context keys after eligible after-UoW lifecycle handling.

HTTP request identity preparation and response finalization MUST NOT introduce additional reset execution, lifecycle hooks, or middleware registrations.

The owner-specific request-id policy is governed by:

```text
docs/ssot/http-middleware-wiring.md
```

## HTTP context enrichment

HTTP middleware-based context enrichment is optional and owner-controlled.

The implemented HTTP request context enrichment owner:

```text
Coretsia\Platform\Http\Middleware\RequestContextMiddleware
```

MAY write only its declared safe HTTP context keys:

```text
client_ip
scheme
host
path
user_agent
```

The middleware-based context enrichment owner MUST NOT write, replace, or remove `request_id`.

HTTP request identity preparation is performed independently by `RequestIdPolicy` before PSR-15 execution.

`http_response_format` remains reserved for future HTTP response negotiation ownership. Its presence in the canonical registry does not imply an implemented writer.

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

The implemented Routing-owned context key is:

```text
path_template
```

`Coretsia\Platform\Routing\Runtime\CompiledRouter` is the sole authorized semantic writer of `ContextKeys::PATH_TEMPLATE`. It MUST remove the previous value through `ContextWriterInterface` at the start of every matching operation, before input validation or route evaluation. On a successful match it MUST set the complete validated declared template through that port before completion observability. On a non-match the key MUST remain absent. Unexpected matching or context mutation failures propagate without completed-match metrics or logs.

`path_template` is preferred over raw `path` for low-cardinality diagnostics. It MUST NOT include concrete user-controlled path parameter values or become a metric label. Routing MUST NOT read context, mutate other keys, or introduce its own UnitOfWork, context store, or reset boundary. Successful canonical Foundation reset clears the key together with all other context values. See `docs/ssot/routing-artifact.md`.

The reserved auth context key is:

```text
actor_id
```

`actor_id` MUST be an opaque safe id.

`actor_id` MUST NOT be an email address, phone number, full name, username, external account id, token, session id, or another direct user identifier.

The reserved tenancy context key is:

```text
tenant_id
```

`tenant_id` MUST be an opaque safe id.

`tenant_id` MUST NOT expose customer names, domains, billing ids, external account ids, tokens, private customer data, or tenancy credentials.

The auth and tenancy keys are reserved in `ContextKeys`.

Reserved key presence prevents name drift; it does not grant write ownership or imply that a concrete writer is implemented.

Writing these keys requires an explicitly authorized owner boundary and compliance with the canonical context key policy.

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

Writers MUST NOT store unsafe context values.

Writers MUST omit unsafe values when omission is permitted by the owning context policy. If a required context write cannot be performed safely, failure MUST follow the owning policy's canonical failure semantics.

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

Safe ids MUST NOT be metric labels unless `docs/ssot/observability.md` explicitly permits the corresponding label key.

## Reset execution

Foundation reset uses the effective reset discovery tag configured by:

```text
foundation.reset.tag
```

The reserved default value is:

```text
kernel.reset
```

The canonical code-level identifier for this Coretsia-reserved DI tag is:

```text
Coretsia\Foundation\Tag\ReservedTags::KERNEL_RESET
```

In this document, `kernel.reset` is used only as the reserved default-name shorthand.

Runtime consumers MUST NOT enumerate reset tags directly.

Kernel runtime MUST execute UnitOfWork reset through the single Foundation reset executor:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

Runtime adapters, including the HTTP runtime and Routing, MUST NOT execute an independent UnitOfWork reset or invoke the Foundation reset executor directly.

### Who triggers reset?

Reset is triggered by Kernel runtime exactly once per UnitOfWork that crossed the reset-responsibility boundary.

The reset-responsibility boundary is crossed only after Kernel runtime has written the base `ContextStore` keys successfully.

A failed begin attempt before base context keys are written MUST NOT trigger reset.

For a UnitOfWork that reaches after-phase eligibility, the canonical reset lifecycle is:

1. Kernel runtime runs `AfterUoW` hooks discovered through:

```text
kernel.hook.after_uow
```

2. Kernel runtime calls the single Foundation reset executor:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

If a UnitOfWork crosses the reset-responsibility boundary but fails before after-phase eligibility, such as on a before-uow hook failure, Kernel runtime MUST skip after-uow hooks and call `ResetOrchestrator::resetAll()` exactly once.

Kernel runtime MUST NOT iterate `kernel.reset` tagged services by itself.

Kernel runtime MUST NOT use `kernel.stateful` as an execution mechanism.

`kernel.stateful` is an enforcement marker, not the runtime reset discovery mechanism.

### Reset formula

For a UnitOfWork that reaches after-phase eligibility and whose canonical reset succeeds, the lifecycle formula is:

```text
KernelRuntime writes base ContextStore keys successfully
→ before/around/body/after UnitOfWork lifecycle
→ KernelRuntime afterUoW phase
→ ResetOrchestrator::resetAll()
→ effective reset discovery tag from foundation.reset.tag
→ reserved default value: kernel.reset (`ReservedTags::KERNEL_RESET`)
→ ContextStore::reset()
→ next UoW starts without previous values
```

For a UnitOfWork that fails after the reset-responsibility boundary but before after-phase eligibility, and whose canonical reset succeeds:

```text
KernelRuntime writes base ContextStore keys successfully
→ before-uow failure
→ no afterUoW phase
→ ResetOrchestrator::resetAll()
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

- when `foundation.reset.priority.enabled=true`, reset executes in the enhanced reset order:

```text
priority DESC, group ASC, serviceId ASC
```

Consumers MUST NOT invent alternate ordering.

Failure semantics MUST be deterministic.

A service discovered for reset that does not implement `ResetInterface` is misuse and MUST hard-fail safely.

Failure messages MUST be safe and MUST NOT include payloads, paths, headers, cookies, Authorization values, tokens, session ids, secrets, or raw context values.

Reset MUST still run if an `after-uow` hook throws.

The original failure MUST be surfaced only after the reset attempt completes.

If after-UoW handling fails and reset also fails, the owning Kernel runtime MUST apply the canonical lifecycle failure-precedence policy from:

```text
docs/ssot/uow-outcome-policy.md
```

This document does not redefine Kernel lifecycle failure-precedence behavior.

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
- two UnitsOfWork are active on the same `KernelRuntime` boundary and can mutate or read the same UnitOfWork-local `ContextStore` state;
- a rejected overlapping UnitOfWork start resets or mutates the currently active UnitOfWork context;
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

The corresponding Coretsia-reserved DI tag identifiers are declared in:

```text
Coretsia\Foundation\Tag\ReservedTags
```

Runtime package source MUST use `ReservedTags::*` for these identifiers.

These names are TagRegistry tag names.

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

4. After successful Kernel begin-UoW and before PSR-15 execution, the HTTP request identity policy, when enabled, selects and writes:

```text
request_id
```

5. HTTP middleware enriches only its declared safe context keys:

```text
client_ip
scheme
host
path
user_agent
```

6. If application route matching is invoked, `CompiledRouter` removes stale `PATH_TEMPLATE` and publishes the complete declared template only on success through `ContextWriterInterface`. This does not imply that Routing is middleware or that every HTTP request invokes application routing. Reserved future context keys are not populated without an explicitly authorized owner.
7. Authorized runtime context readers read context through:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
```

8. After the entire PSR-15 pipeline returns a response, HTTP response finalization applies enabled identity-header policies. Request-id verification reads only `REQUEST_ID` through `ContextAccessorInterface` as defined by `docs/ssot/context-store.md`.
9. Logs, metrics, spans, and errors follow observability SSoTs and do not treat ContextStore presence as export permission.
10. Kernel runtime executes eligible after-UoW lifecycle handling.
11. Kernel runtime triggers Foundation reset orchestration through `ResetOrchestrator`.
12. `ResetOrchestrator` uses the effective reset discovery tag from `foundation.reset.tag`, whose reserved default is `kernel.reset` (`ReservedTags::KERNEL_RESET`).
13. `ContextStore::reset()` clears all context, including `request_id` and `path_template` when present.
14. The next unit of work cannot observe previous request values.

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

It defines policy that MUST be enforced by owner package tests and repository gates.

Enforcement rails include:

```text
packages/core/kernel/tests/Integration/KernelRuntimeAlwaysResetsAfterUowTest.php
tools/gates/cross_cutting_contract_gate.php
```

The Kernel integration test MUST prove the runtime reset invariant:

```text
afterUoW
→ ResetOrchestrator::resetAll()
→ ContextStore is empty for the next UoW
```

The cross-cutting contract gate MUST validate forbidden context writes and lifecycle-policy drift where that is statically enforceable.

The enforcement rails SHOULD cover at least:

- context key registry references use the canonical `Coretsia\Contracts\Context\ContextKeys`;
- runtime context consumers outside Foundation use the appropriate contracts-level ports without concrete store dependencies;
- HTTP request identity preparation occurs after successful Kernel begin-UoW and before PSR-15 execution;
- HTTP request identity remains unchanged throughout conforming request execution;
- HTTP request identity read-back uses `ContextAccessorInterface` and is restricted to `REQUEST_ID`;
- Routing removes stale `PATH_TEMPLATE` before matching and writes the complete declared template only on success through `ContextWriterInterface`;
- successful canonical reset clears HTTP request identity after eligible after-UoW lifecycle handling;
- no raw secrets, headers, cookies, Authorization values, tokens, session ids, or payloads are written to `ContextStore`;
- raw `path` is not exported as a metric label or raw observability value;
- runtime reset uses `ResetOrchestrator`;
- runtime reset does not enumerate `kernel.reset` services directly;
- reset is attempted exactly once for each UnitOfWork that crosses the reset-responsibility boundary;
- successful canonical reset clears all UnitOfWork-local context values.

Concrete enforcement is owned by the corresponding owner packages and repository tooling.

## Non-goals

This SSoT does not define:

- concrete Kernel runtime implementation;
- concrete HTTP middleware writers;
- middleware-to-key FQCN matrix;
- HTTP middleware slot ownership;
- HTTP middleware ordering;
- owner-specific HTTP request-id configuration, validation, upstream acceptance, generation, and response-header policy;
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
- [HTTP Middleware Wiring SSoT](./http-middleware-wiring.md)
- [Routing Artifact SSoT](./routing-artifact.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [Tag Registry](./tags.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [Middleware → ContextKeys map](./middleware-context-keys-map.md)
