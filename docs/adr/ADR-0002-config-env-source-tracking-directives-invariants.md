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

# ADR-0002: Config, env, source tracking, and directive invariants

## Status

Accepted.

## Context

Coretsia needs stable contracts that allow the future Kernel config engine to load, merge, validate, and explain configuration deterministically without coupling `core/contracts` to filesystem layout, package implementations, platform code, integrations, HTTP abstractions, or vendor-specific infrastructure.

Phase 0 cemented several config/env invariants:

- tracked env semantics distinguish missing values from present empty strings;
- config merge is directive-aware and deterministic;
- directive names are allowlisted;
- the `@*` namespace is reserved;
- directive errors have deterministic precedence;
- empty-array directive behavior is locked;
- JSON-like payloads are deterministic and floats are forbidden;
- explain/source traces must be safe and must not contain raw secret values.

Contracts introduced by epic `1.80.0` define ports and value objects only. The Kernel owner package is responsible for concrete loading, merge, validation, source tracking, explain output, and artifact production.

## Decision

`ConfigLoaderInterface` loads config into a read-only `ConfigRepositoryInterface`, not into a loose array.

`ConfigRepositoryInterface` exposes merged config access, full-tree access, safe key-level source lookup, and deterministic explain traces.

`EnvRepositoryInterface` exposes explicit env presence checks, canonical `EnvValue` lookup, present-value enumeration for runtime owner code, and safe source lookup.

`MergeStrategyInterface` defines deterministic binary config node merge. Multi-layer merge is Kernel-owned and may fold the binary operation over explicit source precedence.

Introduce config/env contracts under:

```text
framework/packages/core/contracts/src/Config/
framework/packages/core/contracts/src/Env/
```

The contracts introduced by epic `1.80.0` define:

- `ConfigRepositoryInterface`
- `ConfigLoaderInterface`
- `MergeStrategyInterface`
- `ConfigValidatorInterface`
- `ConfigSourceType`
- `ConfigValueSource`
- `ConfigDirective`
- `ConfigValidationResult`
- `ConfigValidationViolation`
- `ConfigRuleset`
- `EnvRepositoryInterface`
- `EnvValue`
- `EnvPolicy`

The contracts package defines shape, safety, and deterministic invariants only.

It does not implement:

- filesystem scanning;
- Composer package discovery;
- config file loading from disk;
- env file loading;
- config merge execution;
- config validation execution;
- explain trace rendering;
- config artifact writing;
- DI wiring;
- CLI commands.

## Config and env boundary

Config/env contracts must remain format-neutral.

They must not require a concrete source format such as:

- PHP file;
- JSON file;
- YAML file;
- generated artifact;
- Composer metadata;
- `.env` file;
- process environment;
- runtime override store.

Concrete loaders and repositories belong to future owner packages.

## Config rules boundary

Config validation is contract-driven but kernel-implemented.

Package `config/rules.php` files define declarative ruleset arrays only.

Config rules files must not define executable validation logic.

They must not return:

- callable validators;
- closures;
- objects;
- service instances;
- container-aware validators;
- runtime executable validators.

Runtime validation logic belongs to the Kernel config engine and consumes declarative rules through contracts.

## Env semantics

Env-derived values must distinguish:

- missing value;
- present value with non-empty string;
- present value with empty string.

An empty string is present.

Env resolution must not collapse empty string into missing.

This is required for deterministic config behavior and for correct override semantics.

## Directive allowlist

The only allowed config directives are:

```text
append
prepend
remove
merge
replace
```

Directive keys use the `@` prefix:

```text
@append
@prepend
@remove
@merge
@replace
```

Any other `@*` key is reserved and must fail validation before merge.

The directive allowlist must not expand without:

- ADR update;
- SSoT update;
- contract test update;
- lock-source review against Phase 0 config merge semantics.

## Deterministic directive errors

Directive validation must produce deterministic errors before merge.

Error precedence is:

1. unknown or otherwise forbidden reserved `@*` directive key;
2. exclusive-level violation;
3. directive payload shape violation;
4. JSON-like value violation.

The first category maps to the Phase 0 `0.90.0` reserved namespace guard:

```text
CORETSIA_CONFIG_RESERVED_NAMESPACE_USED
```

This precedence is part of the contract-level invariant.

## Exclusive-level rule

A config map level must not mix directive keys and regular config keys.

A directive level must contain only one allowed directive key.

Invalid examples:

```php
[
    '@merge' => [
        'a' => 1,
    ],
    'b' => 2,
]
```

```php
[
    '@append' => ['a'],
    '@remove' => ['b'],
]
```

## Empty-array directive rule

An empty array used as a directive payload is explicit and must not be treated as a missing payload.

The Kernel config engine must handle empty directive payloads deterministically.

For append, prepend, remove, and merge, an empty payload is a deterministic no-op.

For replace, an empty payload replaces the target with an empty array.

## JSON-like value model

Config contract models that export or validate JSON-like data may contain only:

- `null`;
- `bool`;
- `int`;
- `string`;
- list of allowed values;
- map with string keys and allowed values.

Floating-point values are forbidden.

Objects, closures, resources, streams, filesystem handles, service instances, and runtime wiring objects are forbidden in exported config/env contract shapes.

## Source tracking and explain safety

Source tracking must not require storing raw config values or raw env values.

`ConfigValueSource` includes `schemaVersion`, `directive`, and metadata-only `meta` fields.

It keeps explicit `root`, `sourceId`, `precedence`, and `redacted` fields so explain trace ordering and redaction semantics remain contract-level and deterministic.

`ConfigSourceType` is vocabulary only and MUST NOT define merge precedence.

A config value source may expose safe metadata such as:

- source type;
- root;
- path;
- key path;
- source identifier;
- precedence rank;
- redaction marker.

It must not expose:

- raw config values;
- raw env values;
- raw `.env` values;
- passwords;
- credentials;
- tokens;
- private keys;
- cookies;
- authorization headers;
- request bodies;
- response bodies;
- private customer data;
- absolute local paths.

Implementation-owned explain outputs may use safe derived metadata such as:

```text
hash(value)
len(value)
```

They must never print raw values.

Contract-level safe trace ordering maps Phase 0 explain ordering to safe contracts metadata.

Phase 0 explain ordering is:

```text
keyPath ascending
precedenceRank ascending
sourceFile ascending
```

At the contracts boundary, the safe ordering equivalent is:

```text
root ascending
keyPath ascending
precedence ascending
path ascending
sourceId ascending
```

`path` and `sourceId` are safe logical identifiers only. They must not expose absolute local filesystem paths.

## DTO policy boundary

Config/env `descriptor`, `result`, `shape`, and `model` terminology follows:

```text
docs/ssot/dto-policy.md
```

These contracts are not DTO-marker classes by default.

A future owner epic may explicitly opt a class into DTO policy. Until then, config/env model classes are contracts VOs/results/shapes governed by `docs/ssot/config-and-env.md`.

## Consequences

Positive consequences:

- Kernel can implement deterministic config merge against stable contracts.
- Env missing vs empty behavior is locked before runtime implementation.
- Explain/source traces can be safe by construction.
- Directives cannot drift silently from Phase 0 semantics.
- Contracts stay format-neutral and runtime-safe.

Trade-offs:

- Contracts do not implement config loading.
- Contracts do not implement merge or validation.
- Contracts do not define concrete config file paths.
- A later Kernel owner epic must implement the config engine.

## Non-goals

This ADR does not implement:

- config merge engine;
- config loader implementation;
- env repository implementation;
- filesystem scanning;
- `.env` parsing;
- config validation execution;
- explain trace rendering;
- config artifact writing;
- DI tags;
- runtime service registration;
- CLI commands such as `coretsia config:debug`, `coretsia config:validate`, or `coretsia config:compile`.

## Related SSoT

- `docs/ssot/config-and-env.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/dto-policy.md`

## Related epic

- `1.80.0 Contracts: Config + Env + source tracking + directives invariants`
