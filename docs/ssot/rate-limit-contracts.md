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

# Rate Limit Contracts SSoT

## Scope

This document is the Single Source of Truth for Coretsia rate limit contracts, rate limit store port semantics, rate limit decision and state model semantics, key hashing policy, observability constraints, redaction rules, and runtime ownership boundaries.

This document governs contracts introduced by epic `1.160.0` under:

```text
framework/packages/core/contracts/src/RateLimit/
```

The canonical rate limit contracts introduced by this epic are:

```text
Coretsia\Contracts\RateLimit\RateLimitStoreInterface
Coretsia\Contracts\RateLimit\RateLimitState
Coretsia\Contracts\RateLimit\RateLimitDecision
Coretsia\Contracts\RateLimit\RateLimitKeyHasherInterface
```

The implementation paths are:

```text
framework/packages/core/contracts/src/RateLimit/RateLimitStoreInterface.php
framework/packages/core/contracts/src/RateLimit/RateLimitState.php
framework/packages/core/contracts/src/RateLimit/RateLimitDecision.php
framework/packages/core/contracts/src/RateLimit/RateLimitKeyHasherInterface.php
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Rate limiting must be expressible through stable contracts-level ports and value objects so future runtime packages can implement rate limiting and swap stores without changing public APIs.

The contracts introduced by this epic define only:

- a rate limit store port;
- a rate limit key hashing port;
- a safe rate limit state model;
- a safe rate limit decision model;
- key redaction and observability constraints;
- store-swap safety between in-memory, Redis-backed, or other future implementations;
- dependency and ownership boundaries for future runtime packages.

The contracts package MUST NOT implement rate limiting behavior, HTTP middleware, request inspection, identity resolution, IP parsing, Redis integration, in-memory store behavior, distributed locking, clock sources, policy loading, config defaults, config rules, DI registration, generated artifacts, observability emitters, or error mapping.

## Phase 0 lock-source alignment

This SSoT preserves the following Phase 0 invariants:

- `0.20.0` no-secrets output policy applies to rate limit keys, diagnostics, logs, spans, metrics, CLI output, health output, and worker output.
- `0.60.0` missing vs empty MUST remain distinguishable when runtime owners build key material or policy inputs.
- `0.70.0` json-like payloads forbid floats, including `NaN`, `INF`, and `-INF`.
- `0.70.0` lists preserve order and maps use deterministic key ordering when json-like metadata is exposed.
- `0.90.0` safe diagnostics MUST NOT expose raw values and MAY expose only safe derivations such as `hash(value)` or `len(value)`.

Epic `1.160.0` itself introduces no rate limit implementation, no middleware implementation, no Redis implementation, no in-memory store implementation, no config root, no generated artifact, and no DI tag.

## Contract boundary

Rate limit contracts are format-neutral and transport-neutral.

They define stable ports, value objects, and boundary policy only.

The contracts package MUST NOT implement:

- rate limit middleware;
- early rate limit middleware;
- request parsing;
- response rendering;
- header generation;
- identity resolution;
- client IP resolution;
- actor resolution;
- policy registry behavior;
- rate limit policy loading;
- rate limit config loading;
- key building from transport objects;
- concrete store behavior;
- in-memory bucket storage;
- Redis storage;
- database storage;
- distributed locks;
- backend connection management;
- backend driver discovery;
- time source implementation;
- retry header rendering;
- HTTP status rendering;
- exception mapping;
- observability emission;
- DI registration;
- runtime discovery;
- config defaults;
- config rules;
- generated artifacts;
- package providers.

Runtime owner packages implement concrete behavior later.

## Contract package dependency policy

Rate limit contracts MUST remain dependency-free beyond PHP itself.

They MUST NOT depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- `Psr\Http\Message\RequestInterface`
- `Psr\Http\Message\ResponseInterface`
- Redis concrete APIs
- Predis concrete APIs
- Relay concrete APIs
- PDO concrete APIs
- database concrete APIs
- cache concrete APIs
- lock concrete APIs
- clock concrete APIs
- concrete service container implementations
- concrete middleware implementations
- concrete logger implementations
- concrete tracing implementations
- concrete metrics implementations
- framework tooling packages
- generated architecture artifacts
- vendor-specific runtime clients

Runtime packages MAY depend on `core/contracts`.

`core/contracts` MUST NOT depend back on runtime packages.

Allowed direction:

```text
platform/http → core/contracts
platform/rate-limit → core/contracts
platform/cache → core/contracts
integrations/* rate limit stores → core/contracts
application runtime packages → core/contracts
```

Forbidden direction:

```text
core/contracts → platform/http
core/contracts → platform/rate-limit
core/contracts → platform/cache
core/contracts → integrations/*
```

## DTO terminology boundary

This document uses the terms `contract`, `port`, `value object`, `result`, `state`, `decision`, `shape`, and `runtime boundary` according to:

```text
docs/ssot/dto-policy.md
```

`RateLimitState` is an immutable contracts value object.

`RateLimitDecision` is an immutable contracts value object.

They are not DTO-marker classes by default.

Rate limit interfaces introduced by epic `1.160.0` are contracts interfaces.

They are not DTO-marker classes.

DTO gates apply only to classes explicitly marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Rate limit contracts introduced by epic `1.160.0` MUST NOT be treated as DTOs unless a future owner epic explicitly opts them into DTO policy.

## Rate limit key sensitivity

Rate limit keys are sensitive by default.

A rate limit key MAY be derived from:

- authenticated actor identity;
- client IP;
- route template;
- operation name;
- policy id;
- application-defined rate limit dimension;
- runtime-owned safe policy metadata.

A rate limit key MUST be treated as internal key material.

A rate limit key MUST NOT be treated as:

- a metric label;
- a span name fragment;
- a log message;
- an error descriptor extension;
- a health output field;
- a CLI output field;
- a public API field;
- a deterministic generated artifact identity.

Raw rate limit keys MUST NOT be logged, printed, traced, exported, rendered, or copied into diagnostics.

Safe diagnostics MAY expose only derived information such as:

```text
hash(key)
len(key)
operation
outcome
```

Safe derivations MUST NOT expose raw key material or allow reconstruction of raw key material.

## Rate limit key material rules

Runtime owners are responsible for constructing rate limit key material.

The contracts package does not construct keys.

Rate limit key material MUST be non-empty.

Rate limit key material MUST be deterministic for the same logical rate limit subject and policy.

Rate limit key material MUST NOT contain:

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
- private keys;
- passwords;
- raw SQL;
- raw queue messages;
- raw worker payloads;
- profile payloads;
- private customer data;
- absolute local paths;
- timestamps;
- random values;
- process ids;
- host machine identifiers.

Raw path values MUST NOT be used as rate limit keys.

Route templates or owner-approved operation names MAY be used as bounded policy dimensions when they are safe and stable.

Examples of safe route or operation dimensions:

```text
route:api.users.show
route_template:/users/{id}
operation:login
operation:password-reset
policy:auth.login
```

Examples of unsafe key material:

```text
/path/with/user/123
/users/123?token=secret
request_id:01HX...
correlation_id:01HX...
authorization:Bearer ...
session:...
```

## Identity-aware key building policy

Identity-aware key building is runtime-owned.

When identity-aware keying is available, runtime owners MUST prefer:

```text
actor_id
```

over:

```text
client_ip
```

The `actor_id` value used for keying MUST be safe, stable, non-empty, and implementation-owned.

`actor_id` is key material only.

`actor_id` MUST NOT become a metric label under the baseline observability policy.

If no safe actor id is available, runtime owners MAY fall back to `client_ip`.

`client_ip` is key material only.

`client_ip` MUST NOT become a metric label under the baseline observability policy.

Runtime owners MUST hash or otherwise safely derive key material before storage or diagnostics according to this SSoT.

`user_id`, `tenant_id`, `request_id`, and `correlation_id` MUST NOT be introduced as baseline metric labels.

`request_id` and `correlation_id` MUST NOT be rate limit key material.

If a future owner requires tenant-aware rate limit policy, that owner MUST define safe tenant keying semantics in its own SSoT before introducing runtime behavior.

## Hashed key policy

`RateLimitKeyHasherInterface` is the contracts-level port for transforming sensitive rate limit key material into a stable storage key.

The hashed key is still internal implementation data.

A hashed key MUST NOT become a metric label.

A hashed key MUST NOT be logged or printed by default.

A hashed key MAY be used by rate limit store implementations as a backend storage identifier.

If diagnostics are needed for a hashed key, implementations SHOULD expose only:

```text
hash(keyHash)
len(keyHash)
```

or omit the key entirely.

A key hasher implementation MUST be deterministic for the same input and policy.

A key hasher implementation MUST NOT expose the raw input key through exceptions, logs, metrics, spans, health output, CLI output, or unsafe debug output.

The concrete hashing algorithm is runtime-owned.

The contracts package MUST NOT mandate SHA-256, HMAC, SipHash, Redis key prefixes, namespace prefixes, salt storage, secret management, or rotation behavior.

If a keyed hash or secret salt is used, secret ownership and rotation are runtime-owned.

## Rate limit key hasher interface

`RateLimitKeyHasherInterface` is the canonical contracts-level key hashing port.

The implementation path is:

```text
framework/packages/core/contracts/src/RateLimit/RateLimitKeyHasherInterface.php
```

The canonical interface shape is:

```text
hash(string $key): string
```

`hash()` receives sensitive non-empty rate limit key material and returns a non-empty deterministic storage key.

The PHPDoc shape for `hash()` input MUST be:

```php
@param non-empty-string $key
```

The PHPDoc shape for `hash()` output MUST be:

```php
@return non-empty-string
```

`hash()` MUST NOT return an empty string.

`hash()` MUST NOT return raw key material unchanged.

`hash()` MUST NOT expose raw key material in exception messages.

`RateLimitKeyHasherInterface` MUST NOT expose:

- concrete hashing algorithms;
- secret providers;
- random sources;
- clock sources;
- Redis clients;
- request objects;
- response objects;
- PSR-7 objects;
- runtime identity objects;
- framework context objects;
- vendor hashing objects;
- service container objects.

## Rate limit state model

`RateLimitState` is the canonical immutable contracts model for the effective rate limit state after a store operation or state lookup.

The implementation path is:

```text
framework/packages/core/contracts/src/RateLimit/RateLimitState.php
```

`RateLimitState` MUST be immutable.

`RateLimitState` MUST be final.

`RateLimitState` MUST be readonly.

`RateLimitState` MUST be outside DTO marker policy by default.

`RateLimitState` MUST expose only safe scalar state.

It MUST NOT expose:

- raw rate limit keys;
- hashed rate limit keys;
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
- backend object handles;
- backend connection metadata;
- Redis keys;
- Redis scripts;
- lock tokens;
- vendor response objects;
- service instances;
- runtime wiring objects.

### RateLimitState logical fields

The canonical logical fields are:

```text
schemaVersion
limit
remaining
resetAfterSeconds
windowSeconds
```

Field meanings:

| field               | type | meaning                                                                    |
|---------------------|------|----------------------------------------------------------------------------|
| `schemaVersion`     | int  | Stable rate limit state schema version.                                    |
| `limit`             | int  | Maximum allowed cost or hit count in the current window.                   |
| `remaining`         | int  | Remaining cost or hit count after the relevant store operation.            |
| `resetAfterSeconds` | int  | Number of seconds until the current window or bucket is expected to reset. |
| `windowSeconds`     | int  | Effective rate limit window size in seconds.                               |

The canonical state schema version is:

```text
1
```

`schemaVersion` MUST be a positive integer.

`limit` MUST be an integer in this range:

```text
int<1,max>
```

`remaining` MUST be an integer in this range:

```text
int<0,max>
```

`remaining` MUST NOT be greater than `limit`.

`resetAfterSeconds` MUST be an integer in this range:

```text
int<0,max>
```

`windowSeconds` MUST be an integer in this range:

```text
int<1,max>
```

`RateLimitState` MUST NOT use floats for durations, rates, limits, counters, or reset values.

If a future owner needs fractional rates or decimal durations, they MUST be represented through an explicitly documented string format or a future contracts model.

### RateLimitState accessor shape

The canonical accessor shape is:

```text
schemaVersion(): int
limit(): int
remaining(): int
resetAfterSeconds(): int
windowSeconds(): int
toArray(): array<string,mixed>
```

The PHPDoc return shape for `limit()` MUST be:

```php
@return int<1,max>
```

The PHPDoc return shape for `remaining()` MUST be:

```php
@return int<0,max>
```

The PHPDoc return shape for `resetAfterSeconds()` MUST be:

```php
@return int<0,max>
```

The PHPDoc return shape for `windowSeconds()` MUST be:

```php
@return int<1,max>
```

### RateLimitState exported shape

`RateLimitState::toArray()` MUST return a deterministic scalar/json-like shape.

The canonical exported top-level key order is:

```text
limit
remaining
resetAfterSeconds
schemaVersion
windowSeconds
```

The exported state shape MUST NOT expose PHP objects, service instances, runtime wiring objects, closures, resources, filesystem handles, absolute paths, secrets, raw key material, hashed key material, actor ids, client IPs, or implementation-owned backend objects.

## Rate limit decision model

`RateLimitDecision` is the canonical immutable contracts model for a rate limit allow/deny decision.

The implementation path is:

```text
framework/packages/core/contracts/src/RateLimit/RateLimitDecision.php
```

`RateLimitDecision` MUST be immutable.

`RateLimitDecision` MUST be final.

`RateLimitDecision` MUST be readonly.

`RateLimitDecision` MUST be outside DTO marker policy by default.

`RateLimitDecision` MUST expose only safe decision state.

It MUST NOT expose:

- raw rate limit keys;
- hashed rate limit keys;
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
- backend object handles;
- backend connection metadata;
- lock tokens;
- vendor response objects;
- service instances;
- runtime wiring objects.

### RateLimitDecision logical fields

The canonical logical fields are:

```text
schemaVersion
allowed
retryAfterSeconds
reason
state
```

Field meanings:

| field               | type           | meaning                                                               |
|---------------------|----------------|-----------------------------------------------------------------------|
| `schemaVersion`     | int            | Stable rate limit decision schema version.                            |
| `allowed`           | bool           | Whether the operation is allowed by the effective rate limit policy.  |
| `retryAfterSeconds` | int\|null      | Suggested retry delay when denied, or null when no delay is required. |
| `reason`            | string\|null   | Optional safe bounded owner-defined reason or outcome.                |
| `state`             | RateLimitState | Safe effective state after the decision.                              |

The canonical decision schema version is:

```text
1
```

`schemaVersion` MUST be a positive integer.

`allowed` MUST be a boolean.

`retryAfterSeconds` MUST be either:

```text
null
```

or an integer in this range:

```text
int<0,max>
```

When `allowed` is `true`, `retryAfterSeconds` SHOULD be `null`.

When `allowed` is `false`, `retryAfterSeconds` SHOULD be a non-null integer derived from safe state or backend policy.

A retry delay of `0` means retry may be attempted immediately according to owner policy.

`reason` MUST be either:

```text
null
```

or a safe non-empty string.

`reason` MUST be bounded-cardinality and owner-defined.

The canonical safe `reason` string grammar is:

```text
^[A-Za-z][A-Za-z0-9_.:-]*$
```

`reason` MUST start with an ASCII letter.

`reason` MAY contain ASCII letters, digits, `_`, `.`, `:`, and `-`.

`reason` MUST NOT contain whitespace or control characters.

`reason` MUST NOT contain raw key material, hashed key material, actor ids, client IPs, user ids, tenant ids, request ids, correlation ids, raw paths, raw queries, headers, cookies, tokens, credentials, backend diagnostics, connection strings, Redis keys, Redis commands, absolute local paths, or unsafe payloads.

`state` MUST be a `RateLimitState`.

`RateLimitDecision` MUST NOT use floats for retry durations, rates, counters, or limits.

Constructor input for `reason` MUST be validated exactly as supplied.

`RateLimitDecision` MUST NOT trim, lowercase, uppercase, collapse, or otherwise remove whitespace from `reason` before validation.

A `reason` value that contains leading whitespace, trailing whitespace, inner whitespace, or control characters MUST be rejected.

### RateLimitDecision accessor shape

The canonical accessor shape is:

```text
schemaVersion(): int
allowed(): bool
isAllowed(): bool
retryAfterSeconds(): ?int
reason(): ?string
state(): RateLimitState
toArray(): array<string,mixed>
```

The PHPDoc return shape for `retryAfterSeconds()` MUST be:

```php
@return int<0,max>|null
```

The PHPDoc return shape for `reason()` MUST be:

```php
@return non-empty-string|null
```

### RateLimitDecision exported shape

`RateLimitDecision::toArray()` MUST return a deterministic scalar/json-like shape.

The canonical exported top-level key order is:

```text
allowed
reason
retryAfterSeconds
schemaVersion
state
```

`state` MUST export the safe `RateLimitState::toArray()` shape.

The exported decision shape MUST NOT expose PHP objects other than by safe nested exported shape, service instances, runtime wiring objects, closures, resources, filesystem handles, absolute paths, secrets, raw key material, hashed key material, actor ids, client IPs, or implementation-owned backend objects.

## Rate limit store interface

`RateLimitStoreInterface` is the canonical contracts-level rate limit store port.

The implementation path is:

```text
framework/packages/core/contracts/src/RateLimit/RateLimitStoreInterface.php
```

The store port exists so runtime owners can swap backing implementations without changing public APIs.

Possible future store implementations include:

```text
in-memory
Redis-backed
database-backed
cache-backed
external-service-backed
```

Those implementations are runtime-owned and outside `core/contracts`.

The canonical interface shape is:

```text
name(): string
consume(string $keyHash, int $limit, int $windowSeconds, int $cost = 1): RateLimitDecision
state(string $keyHash, int $limit, int $windowSeconds): RateLimitState
reset(string $keyHash): void
```

### Store name

`name()` returns the stable safe store implementation name.

The PHPDoc shape for `name()` output MUST be:

```php
@return non-empty-string
```

The returned name MUST be stable, safe, non-empty, and bounded-cardinality.

The returned name MAY be used by runtime owners as a bounded diagnostics value or as an allowed `driver` metric label value when observability policy allows it.

Examples of safe store names:

```text
memory
redis
database
cache
external
```

The returned name MUST NOT contain:

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
- hostnames;
- DSNs;
- connection strings;
- credentials;
- tokens;
- environment-specific identifiers;
- backend object metadata;
- Redis key prefixes that include runtime data;
- cache pool names that include tenant or environment data;
- database table names that include tenant or environment data.

`name()` MUST NOT imply store discovery semantics.

`name()` MUST NOT introduce DI tag semantics.

`name()` MUST NOT introduce config roots, config keys, backend selection policy, or runtime wiring behavior.

### Store key parameter

`$keyHash` is the storage key produced by `RateLimitKeyHasherInterface`.

The PHPDoc shape for `$keyHash` MUST be:

```php
@param non-empty-string $keyHash
```

`$keyHash` MUST be non-empty.

`$keyHash` is still internal store data.

`$keyHash` MUST NOT be logged, printed, traced, exported as a metric label, copied into error descriptor extensions, exposed through health output, rendered in CLI output, or returned from decision/state models.

Store implementations MAY use `$keyHash` as a backend key.

Store implementations MUST NOT expose `$keyHash` through unsafe diagnostics.

### Store limit and window parameters

`$limit` is the maximum allowed cost or hit count for the effective window.

The PHPDoc shape for `$limit` MUST be:

```php
@param int<1,max> $limit
```

`$windowSeconds` is the effective rate limit window size in seconds.

The PHPDoc shape for `$windowSeconds` MUST be:

```php
@param int<1,max> $windowSeconds
```

`$cost` is the cost consumed by one operation.

The PHPDoc shape for `$cost` MUST be:

```php
@param int<1,max> $cost
```

`$limit`, `$windowSeconds`, and `$cost` MUST be positive integers.

They MUST NOT be floats.

If weighted limits are needed, cost MUST be represented as an integer.

If fractional costs or token refill rates are needed, a future owner MUST define a dedicated contracts model.

### `consume()`

`consume()` atomically consumes `$cost` units from the rate limit represented by `$keyHash`, `$limit`, and `$windowSeconds` according to implementation-owned backend semantics.

`consume()` returns a `RateLimitDecision`.

A returned allowed decision means the operation MAY proceed according to runtime owner policy.

A returned denied decision means the operation SHOULD be rejected or delayed according to runtime owner policy.

`consume()` MUST return safe decision/state models and MUST NOT return backend-specific objects.

Atomicity details are implementation-owned.

For distributed stores, locking, scripts, transactions, monotonic clocks, and race handling are runtime-owned.

### `state()`

`state()` returns the current safe state for the rate limit represented by `$keyHash`, `$limit`, and `$windowSeconds` without consuming additional cost.

`state()` returns a `RateLimitState`.

Implementation-owned stores MAY compute missing state as an empty or full bucket according to owner policy.

The contracts package does not define backend initialization behavior.

### `reset()`

`reset()` clears or resets the store state for `$keyHash` according to implementation-owned backend semantics.

`reset()` returns `void`.

Runtime owners MAY use `reset()` for tests, administrative behavior, or policy-owned reset flows.

`reset()` MUST NOT expose the raw key or hashed key through diagnostics.

### Store interface restrictions

`RateLimitStoreInterface` MUST NOT expose:

- raw key material;
- key builder objects;
- request objects;
- response objects;
- PSR-7 objects;
- middleware objects;
- Redis clients;
- Redis commands;
- Redis scripts;
- database connections;
- cache pool objects;
- lock objects;
- clock objects;
- vendor store objects;
- service container objects;
- runtime wiring objects;
- streams;
- resources;
- closures;
- iterators;
- generators.

The store interface MUST NOT require callers to pass runtime policy arrays, config repositories, request contexts, or transport objects.

Runtime owners adapt those concerns before calling the store.

## Store-swap safety

Rate limit store implementations MUST be swappable without public API changes.

The contract surface MUST NOT encode:

- Redis key conventions;
- Redis script names;
- Redis connection names;
- cache pool names;
- database table names;
- vendor command options;
- lock implementation details;
- in-memory bucket classes;
- backend-specific TTL metadata;
- driver-specific exception classes.

A future in-memory implementation and a future Redis-backed implementation MUST both be able to implement `RateLimitStoreInterface`.

Differences in exact timing, race handling, and atomicity guarantees are implementation-owned and MUST be documented by the runtime owner.

The contracts package defines only the stable port and safe result models.

## Runtime ownership policy

Rate limiting runtime behavior is expected to be owned by future runtime packages.

`platform/http` RateLimit middleware MAY use these contracts when enabled by runtime-owned configuration such as:

```text
http.rate_limit.early.enabled
http.rate_limit.enabled
```

These config paths are runtime policy only.

Epic `1.160.0` introduces no config roots and no config keys.

Early HTTP rate limiting and application HTTP rate limiting are separate runtime concerns.

The canonical HTTP middleware taxonomy remains owned by:

```text
docs/ssot/tags.md
```

A future `platform/http` owner is expected to decide:

- whether rate limiting is enabled;
- which middleware slot is used;
- whether early rate limiting runs before routing;
- whether application rate limiting runs after routing;
- how request input is adapted into key material;
- how actor identity is resolved;
- how client IP is resolved;
- how route templates or operation names are selected;
- how retry headers are rendered;
- how HTTP status `429` is rendered;
- how errors are mapped;
- how store implementations are selected;
- how backend failures are handled.

This is documented runtime policy only.

Epic `1.160.0` does not create or modify `platform/http`.

## Config policy

Epic `1.160.0` introduces no config roots and no config keys.

The contracts package MUST NOT require package config files for rate limit contracts.

No files under package `config/` are introduced by this epic.

Possible future runtime config paths may include:

```text
http.rate_limit.early.enabled
http.rate_limit.enabled
```

These paths are documented here only as future runtime policy context.

They are not config roots or config keys introduced by `core/contracts`.

Rate limit policies, windows, costs, store selection, key builders, fail-open/fail-closed behavior, response headers, and backend configuration are runtime configuration concerns.

Future runtime owner packages MAY introduce rate limit config only through their own owner epics and the config roots registry process.

## DI tag policy

Epic `1.160.0` introduces no DI tags.

The contracts package MUST NOT declare public rate limit tag constants.

The contracts package MUST NOT define package-local mirror constants for rate limit tags.

The contracts package MUST NOT define rate limit tag metadata keys, store discovery semantics, key hasher discovery semantics, middleware priority semantics, or policy discovery semantics.

If a future runtime owner needs rate limit DI tags, that owner MUST introduce them through:

```text
docs/ssot/tags.md
```

according to tag registry rules.

## Artifact policy

Epic `1.160.0` introduces no artifacts.

The contracts package MUST NOT generate:

- rate limit artifacts;
- rate limit policy artifacts;
- rate limit key artifacts;
- store artifacts;
- middleware artifacts;
- route rate limit artifacts;
- backend state artifacts;
- runtime lifecycle artifacts.

A future runtime owner MAY introduce generated rate limit artifacts only through its own owner epic and the artifact registry process.

Any future artifact MUST follow:

```text
docs/ssot/artifacts.md
```

## Observability and diagnostics policy

Rate limit contracts do not define observability signals.

Runtime implementations MAY emit logs, spans, metrics, or profiling signals around rate limiting only when those signals follow:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
docs/ssot/profiling-ports.md
```

Metric labels MUST remain within the canonical allowlist from:

```text
docs/ssot/observability.md
```

Epic `1.160.0` introduces no new metric label keys.

The baseline allowed metric label keys are:

```text
method
status
driver
operation
table
outcome
```

For rate limiting, runtime owners MAY use only safe, bounded-cardinality values for allowed labels.

Reasonable safe label usage MAY include:

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

only when these values are safe, stable, bounded, and owner-approved.

Rate limit implementations MUST NOT use the following as metric labels:

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
- policy value that is user-controlled or high-cardinality.

The label keys `request_id`, `correlation_id`, `tenant_id`, and `user_id` remain forbidden under the baseline policy.

Raw path labels are forbidden.

Route template labels are not introduced by this epic.

If a future owner wants route template labels, that owner MUST update the observability SSoT explicitly.

### Safe diagnostics

Safe implementation diagnostics MAY expose:

```text
hash(key)
len(key)
hash(keyHash)
len(keyHash)
operation
outcome
driver
limit
windowSeconds
cost
remaining
resetAfterSeconds
retryAfterSeconds
reason
```

only when values are safe for the target output and do not create high-cardinality leakage.

`reason` MAY be exposed only when it is the bounded safe `RateLimitDecision::reason()` value.

`reason` MUST NOT be emitted as a metric label key. Runtime owners MAY map a bounded reason/outcome to the allowlisted `outcome` label only when owner-approved.

Runtime owners MUST prefer omission over unsafe emission.

Safe derivations MUST NOT expose raw key material or allow reconstruction of sensitive values.

## Error and exception policy

Epic `1.160.0` does not introduce a rate limit exception hierarchy.

Rate limit denial is represented by `RateLimitDecision`, not by a required contracts exception.

Runtime failure handling is implementation-owned.

Concrete implementations MAY throw owner-defined exceptions.

Owner-defined exceptions and diagnostics MUST NOT expose:

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

The contracts package MUST NOT implement rate limit exception mapping.

The contracts package MUST NOT require `ErrorDescriptor` construction inside rate limit contracts.

For HTTP adapters, a denied `RateLimitDecision` MAY be adapted to HTTP status:

```text
429
```

This is a transport hint owned by HTTP runtime adapters, not a dependency or behavior implemented by `core/contracts`.

## Contract surface restrictions

Rate limit public method signatures MUST NOT contain:

- `Psr\Http\Message\*`;
- `Psr\Http\Message\RequestInterface`;
- `Psr\Http\Message\ResponseInterface`;
- `Coretsia\Platform\*`;
- `Coretsia\Integrations\*`;
- Redis concrete classes;
- Predis concrete classes;
- Relay concrete classes;
- PDO concrete classes;
- database concrete classes;
- cache concrete classes;
- lock concrete classes;
- clock concrete classes;
- vendor clients;
- vendor command objects;
- resources;
- closures;
- streams;
- iterators;
- generators;
- concrete service container objects;
- runtime wiring objects.

Rate limit public method signatures introduced by this SSoT MUST use only:

- PHP built-in scalar/array/null/void types;
- contracts-level rate limit types introduced by this epic.

The only rate-limit-specific object types allowed in public method signatures introduced by this SSoT are:

```text
Coretsia\Contracts\RateLimit\RateLimitState
Coretsia\Contracts\RateLimit\RateLimitDecision
```

## What this epic MUST NOT create

Epic `1.160.0` MUST NOT create:

```text
framework/packages/platform/http/*
framework/packages/platform/rate-limit/*
framework/packages/platform/cache/*
framework/packages/integrations/*
config/*.php
provider/module wiring files
rate limit middleware implementation
early rate limit middleware implementation
rate limit policy loader
rate limit policy registry
rate limit key builder implementation
in-memory store implementation
Redis store implementation
database store implementation
backend connection factory
distributed lock implementation
rate limit exception mapper
DI tag constants
artifact files
```

These are runtime-owned concerns for future owner epics.

## Acceptance scenario

When a future runtime package applies rate limiting:

1. the runtime owner determines the applicable rate limit policy through runtime-owned policy/config behavior;
2. the runtime owner builds non-empty sensitive key material from safe policy dimensions;
3. identity-aware key building prefers `actor_id` when available and falls back to `client_ip` when needed;
4. `correlation_id` and `request_id` are not used as key material;
5. raw request path and raw query are not used as key material;
6. the runtime owner hashes key material through `RateLimitKeyHasherInterface`;
7. the runtime owner passes only the resulting `$keyHash`, positive `$limit`, positive `$windowSeconds`, and positive `$cost` to `RateLimitStoreInterface::consume()`;
8. the store returns a `RateLimitDecision`;
9. the decision exposes only safe scalar state and no key material;
10. a denied decision may be adapted by HTTP runtime code to status `429`;
11. no raw key, hashed key, actor id, client IP, request id, correlation id, raw path, or raw query becomes a metric label;
12. no raw key or hashed key is emitted through unsafe diagnostics;
13. the concrete store can be changed from in-memory to Redis-backed without changing the contracts API;
14. no platform or integration package is required by `core/contracts`.

This acceptance scenario is policy intent.

The concrete middleware, key builder, store implementation, policy registry, config, error mapping, response rendering, headers, fail-open/fail-closed behavior, clocks, locks, and observability integration are runtime-owned.

## Verification evidence

Contracts-level enforcement evidence for this epic includes:

```text
framework/packages/core/contracts/tests/Contract/RateLimitContractsShapeContractTest.php
```

This test is expected to verify:

- `RateLimitStoreInterface` exists;
- `RateLimitStoreInterface` is an interface;
- `RateLimitStoreInterface` exposes the canonical method surface;
- `name()` returns `string`;
- `name()` has PHPDoc `@return non-empty-string`;
- `consume()` accepts `string $keyHash`, `int $limit`, `int $windowSeconds`, and `int $cost = 1`;
- `consume()` returns `RateLimitDecision`;
- `state()` accepts `string $keyHash`, `int $limit`, and `int $windowSeconds`;
- `state()` returns `RateLimitState`;
- `reset()` accepts `string $keyHash` and returns `void`;
- `RateLimitKeyHasherInterface` exists;
- `RateLimitKeyHasherInterface` exposes `hash(string $key): string`;
- rate limit interfaces do not depend on platform packages;
- rate limit interfaces do not depend on integration packages;
- rate limit interfaces do not depend on `Psr\Http\Message\*`;
- rate limit interfaces do not depend on Redis or vendor concretes;
- rate limit interfaces do not expose streams, resources, iterators, generators, closures, or backend objects;
- `RateLimitState` exists;
- `RateLimitState` is final;
- `RateLimitState` is readonly;
- `RateLimitState` exposes the canonical accessor surface;
- `RateLimitState::toArray()` exposes deterministic safe keys;
- `RateLimitDecision` exists;
- `RateLimitDecision` is final;
- `RateLimitDecision` is readonly;
- `RateLimitDecision` exposes the canonical accessor surface;
- `RateLimitDecision` exposes `allowed(): bool`;
- `RateLimitDecision` exposes `reason(): ?string`;
- `RateLimitDecision::reason()` has PHPDoc `@return non-empty-string|null`;
- `RateLimitDecision::toArray()` exposes deterministic safe keys in canonical order, including `reason`;
- `RateLimitState` and `RateLimitDecision` are not DTO-marker classes by default;
- rate limit contracts do not declare DI tag constants;
- rate limit contracts do not introduce config or artifact concepts;
- rate limit contracts never expose `float` as an accepted value or returned result value.

Architecture gates are expected to verify that `core/contracts` does not introduce forbidden compile-time dependencies.

## Non-goals

This SSoT does not define:

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
- distributed lock behavior;
- clock implementation;
- monotonic time policy;
- backend failure policy;
- fail-open/fail-closed policy;
- rate limit policy schema;
- rate limit config roots;
- rate limit config defaults;
- rate limit config rules;
- rate limit DI tags;
- rate limit generated artifacts;
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

## Cross-references

- [SSoT Index](./INDEX.md)
- [Observability Naming and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [ErrorDescriptor SSoT](./error-descriptor.md)
- [DTO Policy](./dto-policy.md)
- [Config Roots Registry](./config-roots.md)
- [Tag Registry](./tags.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
