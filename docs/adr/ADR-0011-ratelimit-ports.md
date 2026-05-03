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

# ADR-0011: Rate limit ports

## Status

Accepted.

## Context

Epic `1.160.0` introduces stable rate limit contracts under:

```text
framework/packages/core/contracts/src/RateLimit/
```

Runtime packages need a shared rate limiting boundary that allows HTTP middleware, application runtimes, and future backend integrations to use rate limiting without coupling public APIs to a concrete store implementation.

Rate limiting must support store replacement without API changes.

Expected future store implementations may include:

```text
in-memory
Redis-backed
database-backed
cache-backed
external-service-backed
```

These implementations have different operational characteristics, atomicity guarantees, TTL behavior, failure modes, and backend-specific APIs. Those differences must not leak into `core/contracts`.

The contracts package must remain a pure library boundary.

It must not depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- Redis concrete APIs
- Predis concrete APIs
- Relay concrete APIs
- PDO concrete APIs
- cache concrete APIs
- lock concrete APIs
- clock concrete APIs
- vendor concrete clients
- concrete middleware implementations
- generated architecture artifacts

The detailed normative policy for this ADR is defined by:

```text
docs/ssot/rate-limit-contracts.md
```

Rate limit observability and redaction behavior must also follow:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
docs/ssot/dto-policy.md
```

## Decision

Coretsia will introduce rate limit contracts as format-neutral contracts in `core/contracts`.

The contracts introduced by epic `1.160.0` define:

- `RateLimitStoreInterface`
- `RateLimitState`
- `RateLimitDecision`
- `RateLimitKeyHasherInterface`

The contracts package defines only:

- the stable store port;
- the stable key hashing port;
- safe state and decision models;
- store-swap boundary policy;
- key sensitivity and redaction constraints;
- observability constraints for future runtime owners.

The contracts package does not implement:

- HTTP middleware;
- early HTTP middleware;
- key building from requests;
- identity resolution;
- client IP resolution;
- policy loading;
- config loading;
- in-memory store behavior;
- Redis-backed store behavior;
- distributed locking;
- clock behavior;
- retry header rendering;
- HTTP `429` rendering;
- exception mapping;
- observability emission;
- DI registration;
- generated artifacts.

## Store port decision

`RateLimitStoreInterface` is the canonical contracts-level rate limit store port.

The canonical interface shape is:

```text
name(): string
consume(string $keyHash, int $limit, int $windowSeconds, int $cost = 1): RateLimitDecision
state(string $keyHash, int $limit, int $windowSeconds): RateLimitState
reset(string $keyHash): void
```

`name()` returns a stable safe bounded store implementation name such as `memory` or `redis`.

It may be used by runtime owners as a safe `driver` value only when observability policy allows it.

It must not expose connection names, DSNs, hostnames, tenant ids, user ids, request ids, correlation ids, key material, environment-specific identifiers, backend object metadata, or runtime wiring details.

The store accepts a hashed internal storage key, not raw key material.

The store returns safe contracts models only.

The store interface must not expose backend-specific concepts such as:

- Redis clients;
- Redis commands;
- Redis scripts;
- Redis key conventions;
- cache pools;
- database connections;
- lock objects;
- clock objects;
- backend-specific TTL metadata;
- vendor response objects;
- service container objects.

This keeps the store surface stable while allowing future runtime owners to implement different backends.

## Store-swap decision

Rate limit stores must be swappable without public API changes.

The contract surface intentionally does not encode:

- Redis key prefixes;
- Lua script names;
- Redis connection names;
- cache pool names;
- database table names;
- backend lock semantics;
- in-memory bucket classes;
- driver-specific exceptions;
- backend-specific timing metadata.

A future in-memory implementation and a future Redis-backed implementation must both be able to implement the same `RateLimitStoreInterface`.

Exact atomicity behavior, race handling, clock selection, distributed consistency, backend failure policy, and timing precision are implementation-owned and must be documented by the runtime owner.

## Key hashing decision

`RateLimitKeyHasherInterface` is the canonical contracts-level port for converting sensitive rate limit key material into an internal storage key.

The canonical interface shape is:

```text
hash(string $key): string
```

The input key is sensitive key material.

The returned key hash is still internal implementation data.

The contracts package does not mandate:

- SHA-256;
- HMAC;
- SipHash;
- Redis key prefixes;
- namespace prefixes;
- secret salt storage;
- secret rotation;
- key versioning.

Concrete hashing policy is runtime-owned.

If a keyed hash or secret salt is used, secret ownership and rotation are runtime-owned.

## Raw key redaction decision

Raw rate limit keys must never be public diagnostic values.

Raw keys must not be used as:

- metric labels;
- span name fragments;
- log messages;
- error descriptor extensions;
- health output fields;
- CLI output fields;
- public API fields;
- generated artifact identities.

Safe diagnostics may expose only derived information such as:

```text
hash(key)
len(key)
hash(keyHash)
len(keyHash)
```

Safe derivations must not expose raw key material or allow reconstruction of key material.

A hashed storage key is still internal data and must not become a metric label or public diagnostic value.

## Key material decision

Rate limit key material is runtime-owned.

The contracts package does not build keys.

Runtime owners may build key material from safe policy dimensions such as:

```text
actor_id
client_ip
route_template
operation
policy_id
```

Identity-aware key building must prefer:

```text
actor_id
```

over:

```text
client_ip
```

when a safe actor id is available.

`actor_id` and `client_ip` are key material only. They must not become baseline metric labels.

Rate limit key material must not contain:

- `correlation_id`;
- `request_id`;
- raw request path;
- raw query string;
- raw headers;
- raw cookies;
- raw body;
- authorization headers;
- bearer tokens;
- session identifiers;
- CSRF tokens;
- raw auth tokens;
- credentials;
- raw SQL;
- profile payloads;
- private customer data;
- absolute local paths;
- timestamps;
- random values;
- process ids;
- host machine identifiers.

Route templates or owner-approved operation names may be used as bounded policy dimensions when they are safe and stable.

Raw request paths must not be used as key material.

## State and decision model decision

`RateLimitState` is the canonical immutable contracts model for safe effective rate limit state.

Its logical fields are:

```text
schemaVersion
limit
remaining
resetAfterSeconds
windowSeconds
```

`RateLimitDecision` is the canonical immutable contracts model for allow/deny results.

Its logical fields are:

```text
schemaVersion
allowed
retryAfterSeconds
reason
state
```

The concrete contracts model restricts `reason` to a safe bounded ASCII token grammar defined by the Rate Limit Contracts SSoT.

`reason` is an optional safe bounded owner-defined reason or outcome.

It must not expose raw keys, hashed keys, actor ids, client IPs, user ids, tenant ids, request ids, correlation ids, raw paths, raw queries, headers, cookies, tokens, credentials, backend diagnostics, connection strings, Redis keys, Redis commands, absolute local paths, or unsafe payloads.

Both models are contracts value objects.

They are not DTO-marker classes by default.

They must expose only safe scalar state and deterministic exported array shapes.

They must not expose:

- raw keys;
- hashed keys;
- actor ids;
- client IPs;
- user ids;
- tenant ids;
- request ids;
- correlation ids;
- raw paths;
- raw queries;
- headers;
- cookies;
- tokens;
- credentials;
- backend handles;
- Redis keys;
- Redis scripts;
- lock tokens;
- vendor response objects;
- service instances;
- runtime wiring objects.

Rate limit limits, counters, windows, reset durations, costs, and retry durations are integers.

Floats are not part of the rate limit contracts boundary.

## Observability decision

Rate limit contracts introduce no observability signals and no metric label keys.

Future runtime implementations may emit logs, spans, metrics, or profiling signals only when they follow:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
docs/ssot/profiling-ports.md
```

Metric labels must remain within the canonical allowlist:

```text
method
status
driver
operation
table
outcome
```

For rate limiting, reasonable safe label values may include bounded values such as:

```text
operation=consume
operation=state
operation=reset
outcome=allowed
outcome=denied
outcome=error
driver=memory
driver=redis
```

only when those values are safe, stable, bounded, and owner-approved.

Rate limit implementations must not use the following as metric labels:

- raw key;
- hashed key;
- key hash;
- actor id;
- client IP;
- user id;
- tenant id;
- request id;
- correlation id;
- route parameter;
- raw path;
- raw query;
- header value;
- cookie value;
- token;
- session identifier;
- user-controlled policy value.

The forbidden baseline label keys remain forbidden:

```text
request_id
correlation_id
tenant_id
user_id
```

Route template labels are not introduced by this epic.

If a future owner wants route template labels, that owner must update the observability SSoT explicitly.

## Runtime ownership decision

Rate limiting runtime behavior is expected to be owned by future runtime packages.

`platform/http` rate limit middleware may use these contracts when enabled by runtime-owned configuration such as:

```text
http.rate_limit.early.enabled
http.rate_limit.enabled
```

These config paths are runtime policy only.

Epic `1.160.0` introduces no config roots and no config keys.

A future runtime owner is expected to decide:

- whether rate limiting is enabled;
- where middleware is installed;
- whether early rate limiting runs before routing;
- whether application rate limiting runs after routing;
- how request data is adapted into key material;
- how actor identity is resolved;
- how client IP is resolved;
- how route templates or operation names are selected;
- how retry headers are rendered;
- how HTTP status `429` is rendered;
- how store implementations are selected;
- how backend failures are handled;
- whether runtime behavior is fail-open or fail-closed.

This ADR records policy intent only.

It does not create or modify `platform/http`.

## DI tag decision

Epic `1.160.0` introduces no DI tags.

The contracts package must not declare public rate limit tag constants.

The contracts package must not define package-local mirror constants for rate limit tags.

The contracts package must not define:

- store discovery semantics;
- key hasher discovery semantics;
- rate limit tag metadata keys;
- middleware priority semantics;
- policy discovery semantics.

If a future runtime owner needs rate limit DI tags, that owner must introduce them through:

```text
docs/ssot/tags.md
```

according to the tag registry rules.

## Config decision

Epic `1.160.0` introduces no config roots and no config keys.

The contracts package must not add package config defaults or config rules for rate limiting.

No files under package `config/` are introduced by this epic.

Rate limit policies, windows, costs, store selection, key builder selection, response headers, backend configuration, and fail-open or fail-closed behavior are runtime configuration concerns.

Future runtime owner packages may introduce rate limit config only through their own owner epics and the config roots registry process.

## Artifact decision

Epic `1.160.0` introduces no artifacts.

The contracts package must not generate:

- rate limit artifacts;
- rate limit policy artifacts;
- rate limit key artifacts;
- store artifacts;
- middleware artifacts;
- route rate limit artifacts;
- backend state artifacts;
- runtime lifecycle artifacts.

Future runtime owners may introduce generated rate limit artifacts only through their own owner epics and the artifact registry process.

Any future artifact must follow:

```text
docs/ssot/artifacts.md
```

## Error handling decision

Epic `1.160.0` does not introduce a rate limit exception hierarchy.

Rate limit denial is represented by `RateLimitDecision`, not by a required contracts exception.

Runtime failure handling is implementation-owned.

Concrete implementations may throw owner-defined exceptions, but those exceptions and diagnostics must not expose:

- raw key material;
- hashed key material;
- actor ids;
- client IPs;
- user ids;
- tenant ids;
- request ids;
- correlation ids;
- raw paths;
- raw queries;
- headers;
- cookies;
- tokens;
- credentials;
- backend connection strings;
- Redis keys;
- Redis commands containing key material;
- vendor object dumps;
- absolute local paths;
- stack traces by default.

If rate limit errors are normalized later, mapping to `ErrorDescriptor` is owned by runtime error mapping packages.

The contracts package must not implement rate limit exception mapping.

For HTTP adapters, a denied `RateLimitDecision` may be adapted to HTTP status:

```text
429
```

This is adapter-owned transport policy, not a `core/contracts` dependency.

## DTO boundary decision

`RateLimitState` and `RateLimitDecision` are contracts value objects.

They are not DTO-marker classes by default.

DTO policy remains explicit opt-in only through:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Contract tests enforce the rate limit contracts surface directly.

DTO gates do not own these models unless a future epic explicitly opts them into DTO policy.

## Consequences

Positive consequences:

- Runtime packages can use one stable rate limit API.
- Store implementations can be replaced without public API changes.
- `core/contracts` remains independent of HTTP, Redis, cache, database, and platform implementations.
- Rate limit decisions and state remain safe to expose at the contracts boundary.
- Key material is treated as sensitive by design.
- Observability labels remain low-cardinality and policy-compliant.
- Future HTTP adapters can map denied decisions to HTTP `429` without making contracts HTTP-specific.

Trade-offs:

- Contracts do not define a concrete rate limiting algorithm.
- Contracts do not define exact atomicity guarantees.
- Contracts do not define Redis behavior.
- Contracts do not define in-memory behavior.
- Contracts do not define fail-open or fail-closed behavior.
- Contracts do not define middleware placement.
- Runtime owners must implement key building, policy loading, backend behavior, and response rendering later.

## Rejected alternatives

### Put HTTP request objects in rate limit contracts

Rejected.

HTTP request objects would make the contracts HTTP-specific and would leak transport concerns into non-HTTP runtimes.

The contracts boundary must accept scalar key hashes and return safe state or decision models only.

### Put PSR-7 types in rate limit contracts

Rejected.

PSR-7 would make rate limit contracts depend on HTTP abstractions and would prevent reuse from CLI, worker, scheduler, queue, or custom runtime boundaries.

Transport-specific request adaptation belongs to runtime owners.

### Put Redis APIs in the store contract

Rejected.

Redis is a possible future implementation, not the contracts boundary.

Encoding Redis concepts into the public contract would make in-memory, database-backed, cache-backed, or external-service-backed stores second-class or impossible without API changes.

### Make raw rate limit keys public diagnostics

Rejected.

Raw keys may contain sensitive or high-cardinality material.

Keys must not be logged, printed, traced, exported as metric labels, copied into error descriptor extensions, or exposed through health or CLI output.

Diagnostics may use only safe derivations such as `hash(value)` or `len(value)`.

### Make hashed keys metric labels

Rejected.

Hashed keys are still high-cardinality internal store identifiers.

They must not become metric labels.

Metrics must use only safe bounded labels from the observability allowlist.

### Use request id or correlation id as key material

Rejected.

Request and correlation identifiers are per-request values and create high-cardinality, unstable keying behavior.

They are explicitly forbidden as rate limit key material.

They are also forbidden as baseline metric label keys.

### Introduce rate limit config in contracts

Rejected.

Configuration belongs to runtime owner packages.

This epic introduces no config roots and no config keys.

Future runtime owners may introduce rate limit configuration through the config roots registry process.

### Introduce rate limit DI tags in contracts

Rejected.

Store discovery, key hasher discovery, middleware wiring, and policy discovery are runtime-owned.

If a future owner needs DI tags, that owner must introduce them through the tag registry.

### Put middleware implementation into this PR

Rejected.

Middleware needs HTTP runtime ownership, request adaptation, response rendering, middleware slot selection, configuration, error handling, and observability integration.

Those responsibilities belong to future runtime owner packages, not `core/contracts`.

## Non-goals

This ADR does not implement:

- concrete rate limit implementation;
- concrete HTTP middleware;
- concrete early HTTP middleware;
- concrete key builder;
- concrete identity resolver;
- concrete client IP resolver;
- concrete store implementation;
- in-memory bucket behavior;
- Redis command behavior;
- Redis Lua script behavior;
- database-backed rate limit behavior;
- cache-backed rate limit behavior;
- external-service-backed rate limit behavior;
- distributed lock behavior;
- clock implementation;
- monotonic time policy;
- backend failure policy;
- fail-open or fail-closed policy;
- rate limit policy schema;
- rate limit config roots;
- rate limit config defaults;
- rate limit config rules;
- rate limit DI tags;
- generated artifacts;
- response header rendering;
- HTTP `429` response rendering;
- exception hierarchy;
- exception mapper;
- metrics schema;
- tracing schema;
- logging backend behavior;
- `platform/http` implementation;
- `platform/rate-limit` implementation;
- `integrations/*` rate limit store implementation.

## Related SSoT

- `docs/ssot/rate-limit-contracts.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/error-descriptor.md`
- `docs/ssot/dto-policy.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/tags.md`
- `docs/ssot/artifacts.md`

## Related epic

- `1.160.0 Contracts: RateLimit ports`
