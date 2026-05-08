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

# Context Keys SSoT

## Scope

This document is the Single Source of Truth for Coretsia runtime context key names, meanings, writer categories, lifecycle notes, and safe-value policy.

This document governs the canonical key registry implemented by:

```text
framework/packages/core/foundation/src/Context/ContextKeys.php
```

It complements:

```text
docs/ssot/context-store.md
docs/ssot/config-and-env.md
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/tags.md
docs/ssot/uow-and-reset-contracts.md
```

The detailed per-middleware mapping from middleware FQCN to written/read context keys is intentionally out of scope for this document.

That reference map is owned by a later HTTP owner epic and MUST live only in:

```text
docs/ssot/middleware-context-keys-map.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Runtime packages need one stable, format-neutral key vocabulary for reading and writing safe unit-of-work context.

The ContextKeys registry exists to prevent:

- ad hoc context key sprawl;
- accidental storage of secrets, raw transport data, or private customer data;
- duplicate names for the same runtime concept;
- unbounded or high-cardinality observability dimensions;
- drift between Foundation, Kernel, HTTP, logging, tracing, and future runtime packages.

## Ownership

The key registry is owned by package:

```text
core/foundation
```

The runtime implementation path is:

```text
framework/packages/core/foundation/src/Context/ContextKeys.php
```

`Coretsia\Foundation\Context\ContextKeys` is the only runtime source for canonical context key constants.

Any new key requires:

- this SSoT update;
- `ContextKeys` update;
- stable contract test update;
- redaction and lifecycle review.

## Context key rules

Context keys MUST be stable lowercase ASCII strings.

Context keys MUST use `snake_case`.

Context keys MUST NOT be empty.

Context keys MUST NOT contain whitespace.

Context keys MUST NOT contain transport-specific prefixes.

Context keys MUST NOT contain package-specific prefixes.

Context keys MUST NOT start with `@`.

The `@*` namespace is reserved for config directives according to:

```text
docs/ssot/config-and-env.md
```

Runtime context keys MUST never collide with config directive syntax.

## Context key allowlist rule

`ContextStorePolicy` MUST allow writes only for keys declared in:

```text
Coretsia\Foundation\Context\ContextKeys
```

Any attempt to write a key not declared in `ContextKeys` MUST fail deterministically.

Any attempt to write a key starting with `@` MUST fail deterministically.

The failure message MUST be safe and MUST NOT contain raw context values.

## Writer categories

This document may name only high-level writer categories.

Allowed writer categories are:

```text
kernel
http
routing
auth
tenancy
```

This document MUST NOT contain the per-middleware `FQCN -> ContextKeys written/read` matrix.

Concrete middleware ownership and slot taxonomy are governed by:

```text
docs/ssot/http-middleware-catalog.md
```

The middleware-to-context-key matrix is owned only by:

```text
docs/ssot/middleware-context-keys-map.md
```

## Safe value model

All values written under canonical context keys MUST obey `ContextStorePolicy`.

Allowed values are JSON-safe deterministic values only:

- `null`
- `bool`
- `int`
- `string`
- list of allowed values
- map with string keys and allowed values

Forbidden values include:

- floats, including `NaN`, `INF`, and `-INF`
- objects
- closures
- resources
- non-string map keys
- raw transport objects
- service instances
- runtime wiring objects

Callable-ness is not a standalone ContextStore type rule.

Plain strings remain valid strings even if PHP could interpret some of them as callable names.

Example of a valid string value:

```text
strlen
```

## Forbidden data

ContextStore MUST NOT store secrets or sensitive payload material.

Forbidden data includes:

- tokens in any form;
- session ids;
- cookies;
- Authorization headers;
- raw request bodies;
- raw response bodies;
- raw headers;
- raw SQL;
- credentials;
- passwords;
- private keys;
- private customer data;
- direct user identifiers such as email, phone, full name, or external account identifiers.

A future owner epic MAY introduce explicitly safe identifiers only by updating this SSoT and contract tests.

## Request metadata policy

Request metadata keys are allowed only when the key is declared in `ContextKeys` and the value obeys `ContextStorePolicy`.

Potentially sensitive request metadata, such as `client_ip`, SHOULD be normalized or hashed by writers when feasible.

The presence of a request metadata key in this registry does not allow raw headers, raw cookies, request bodies, response bodies, authorization values, tokens, or session identifiers.

## Canonical active keys

The following keys are active in the Phase 1 Foundation context baseline.

| Constant         | Key              | Meaning                                                                                | Writer category | Lifecycle                                                                            | Safe-value notes                                                                                                                                 |
|------------------|------------------|----------------------------------------------------------------------------------------|-----------------|--------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------|
| `CORRELATION_ID` | `correlation_id` | Safe opaque correlation id for joining runtime diagnostics within a unit of work.      | `kernel`        | Set at begin-UoW; cleared by `ContextStore::reset()`.                                | Non-empty uppercase ULID string. Safe for logs/tracing correlation. MUST NOT be used as a metric label under baseline observability policy.      |
| `UOW_ID`         | `uow_id`         | Safe opaque unit-of-work id.                                                           | `kernel`        | Set at begin-UoW; cleared by `ContextStore::reset()`.                                | Non-empty safe id string. MUST NOT encode raw payload data or direct user identifiers.                                                           |
| `UOW_TYPE`       | `uow_type`       | Stable unit-of-work category.                                                          | `kernel`        | Set at begin-UoW; cleared by `ContextStore::reset()`.                                | Non-empty stable string such as `http`, `cli`, `worker`, `queue`, `scheduler`, or another owner-defined safe category.                           |
| `CLIENT_IP`      | `client_ip`      | Client network address metadata when an owner chooses to expose a safe representation. | `http`          | Written during HTTP request context preparation; cleared by `ContextStore::reset()`. | SHOULD be normalized or hashed when feasible. MUST NOT include headers or raw forwarding chains.                                                 |
| `SCHEME`         | `scheme`         | Request scheme metadata.                                                               | `http`          | Written during HTTP request context preparation; cleared by `ContextStore::reset()`. | Stable lowercase value such as `http` or `https`.                                                                                                |
| `HOST`           | `host`           | Request host metadata when safe for runtime context.                                   | `http`          | Written during HTTP request context preparation; cleared by `ContextStore::reset()`. | MUST NOT include credentials, userinfo, headers, cookies, or raw authority payloads.                                                             |
| `PATH`           | `path`           | Request path metadata when an owner deliberately writes it for runtime context.        | `http`          | Written during HTTP request context preparation; cleared by `ContextStore::reset()`. | MUST NOT include query string. MUST NOT be exported to logs, spans, or metrics as raw path. Prefer `path_template` when available.               |
| `USER_AGENT`     | `user_agent`     | User-agent metadata when an owner deliberately writes it for runtime context.          | `http`          | Written during HTTP request context preparation; cleared by `ContextStore::reset()`. | MAY be high-cardinality and privacy-sensitive. Writers SHOULD prefer omission, normalization, hashing, or length-only diagnostics when feasible. |

## Reserved future keys

The following keys are reserved for future owner integration.

They are part of the canonical registry to prevent name drift, but their concrete writers MAY be absent until later owner epics.

| Constant               | Key                    | Meaning                                                                                  | Future writer category | Lifecycle                                                                       | Safe-value notes                                                                                                                    |
|------------------------|------------------------|------------------------------------------------------------------------------------------|------------------------|---------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------|
| `REQUEST_ID`           | `request_id`           | Safe request id distinct from correlation id when a transport owner needs both concepts. | `http`                 | Future HTTP lifecycle; cleared by `ContextStore::reset()`.                      | Safe opaque id only. MUST NOT be a session id, token, cookie, or direct user identifier.                                            |
| `PATH_TEMPLATE`        | `path_template`        | Stable route/path template suitable for low-cardinality diagnostics.                     | `routing`              | Future routing lifecycle; cleared by `ContextStore::reset()`.                   | Preferred over raw `path` for observability. MUST NOT include concrete user-controlled path parameters.                             |
| `HTTP_RESPONSE_FORMAT` | `http_response_format` | Stable response format category selected by an HTTP owner.                               | `http`                 | Future HTTP response negotiation lifecycle; cleared by `ContextStore::reset()`. | Stable bounded string such as `html`, `json`, `problem_json`, or another owner-defined safe category.                               |
| `ACTOR_ID`             | `actor_id`             | Safe actor id when auth introduces an explicitly non-PII actor identifier.               | `auth`                 | Future auth lifecycle; cleared by `ContextStore::reset()`.                      | MUST be an opaque safe id. MUST NOT be email, phone, full name, username, external account id, token, or session id.                |
| `TENANT_ID`            | `tenant_id`            | Safe tenant id when tenancy introduces an explicitly non-PII tenant identifier.          | `tenancy`              | Future tenancy lifecycle; cleared by `ContextStore::reset()`.                   | MUST be an opaque safe id. MUST NOT expose customer name, domain, billing id, external account id, token, or private customer data. |

## Observability policy

Context keys MAY be read by logging and tracing owner packages through:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
```

Any export to logs, spans, metrics, error descriptors, profiling, or health output remains governed by:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
```

The presence of a key in ContextStore does not make that key valid as a metric label.

The baseline observability policy forbids metric label keys such as:

```text
correlation_id
request_id
tenant_id
user_id
path
```

`correlation_id` is safe for correlation use and MAY be logged or used for tracing correlation according to owner policy, but it MUST NOT be emitted as a metric label under the baseline policy.

Raw `path`, raw query, headers, cookies, Authorization values, tokens, session ids, and payloads MUST NOT be exported even if a future owner accidentally attempts to write them.

When path-like observability data is needed, owners SHOULD use:

```text
path_template
hash(value)
len(value)
```

## Reset lifecycle

All context values are unit-of-work-local unless a future SSoT explicitly states otherwise.

`ContextStore` MUST implement:

```text
Coretsia\Contracts\Runtime\ResetInterface
```

`ContextStore::reset()` MUST clear stored context deterministically.

`ContextStore` MUST be tagged with the effective Foundation reset discovery tag:

```text
foundation.reset.tag
```

The reserved default effective reset tag is:

```text
kernel.reset
```

`ContextStore` MUST also be tagged as:

```text
kernel.stateful
```

## Kernel lifecycle note

Kernel runtime integration is owned by a later Phase 1 runtime epic.

That later owner MUST set the base keys at begin-UoW:

```text
correlation_id
uow_id
uow_type
```

That later owner MUST execute Foundation reset orchestration after the unit of work.

This document defines the key vocabulary and lifecycle policy only.

## HTTP lifecycle note

HTTP writers are owned by HTTP packages and middlewares.

HTTP writers MAY write only safe keys declared in this registry and accepted by `ContextStorePolicy`.

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

The canonical middleware catalog reference is:

```text
docs/ssot/http-middleware-catalog.md
```

The detailed middleware-to-key map is not introduced by this epic.

## Stability contract

The canonical key list is contract-tested.

The following changes require an explicit SSoT and test update:

- adding a key;
- removing a key;
- renaming a key;
- changing a key meaning;
- changing a writer category;
- changing a lifecycle rule;
- changing safe-value notes;
- moving a reserved future key into active owner usage.

## Non-goals

This SSoT does not define:

- `ContextStore` implementation details;
- `ContextBag` implementation details;
- concrete HTTP middleware writers;
- middleware-to-key FQCN matrix;
- HTTP correlation header extraction;
- HTTP correlation header injection;
- transport-specific propagation policy;
- logging backend behavior;
- tracing backend behavior;
- metrics backend behavior;
- error mapper behavior;
- generated artifacts;
- config roots;
- config keys;
- feature toggles.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Config and env SSoT](./config-and-env.md)
- [HTTP Middleware Catalog SSoT](./http-middleware-catalog.md)
- [Observability Naming and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [Tag Registry](./tags.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
