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

# Config and env SSoT

## Scope

This document is the Single Source of Truth for Coretsia config/env contracts, env lookup semantics, config directive invariants, source tracking, safe explain traces, config validation result shapes, and declarative config ruleset policy.

This document governs contracts introduced by epic `1.80.0` under:

```text
framework/packages/core/contracts/src/Config/
framework/packages/core/contracts/src/Env/
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Contract boundary

Config/env contracts are format-neutral.

They define stable ports, enums, value objects, result shapes, and invariants only.

The contracts package MUST NOT implement:

- filesystem scanning;
- config file discovery;
- config file parsing;
- `.env` file parsing;
- process env discovery;
- Composer metadata discovery;
- config merge execution;
- config validation execution;
- explain trace rendering;
- generated config artifact writing;
- DI registration;
- CLI command behavior.

The Kernel owner package is responsible for concrete config loading, merge, validation, explain trace generation, and artifact production.

## Contract package dependency policy

The config/env contracts MUST remain format-neutral.

They MUST NOT depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- PDO concrete APIs
- Redis concrete APIs
- S3 concrete APIs
- Prometheus concrete APIs
- vendor-specific runtime clients
- framework tooling packages
- generated architecture artifacts

## DTO terminology boundary

This document uses the terms `descriptor`, `result`, `shape`, and `model` according to:

```text
docs/ssot/dto-policy.md
```

Config/env contract classes are not DTO-marker classes by default.

A future owner epic MAY explicitly opt a class into DTO policy. Until such an epic exists, config/env VOs, result objects, and shape wrappers are governed by this SSoT and contracts shape rules, not DTO gate rules.

## Env repository port

`EnvRepositoryInterface` is a contracts port for reading env-derived values.

It MUST expose env lookup results without prescribing the implementation source.

An env repository implementation MAY read from:

- process environment;
- `.env` parser output;
- generated config artifact;
- in-memory test source;
- another future owner-defined source.

The contracts package MUST NOT implement a concrete env repository.

## Env value model

`EnvValue` is the canonical contracts model for an env lookup result.

It MUST distinguish:

```text
missing
present empty string
present non-empty string
```

An empty string is present.

The following states are distinct:

| state                    | present | value     |
|--------------------------|---------|-----------|
| missing                  | false   | N/A       |
| present empty string     | true    | `""`      |
| present non-empty string | true    | `"value"` |

Env lookup code MUST NOT collapse empty string into missing.

Env lookup code MUST NOT use PHP truthiness to determine presence.

Invalid presence checks include:

```php
if ($value) {
    // invalid for env presence
}
```

Valid presence checks MUST use explicit `EnvValue` presence state.

## Env policy

`EnvPolicy` defines how env-derived inputs are interpreted by config-owning implementations.

The canonical policy names are:

```text
required
optional
defaulted
```

Policy meaning:

| policy      | missing behavior                           | present empty string behavior |
|-------------|--------------------------------------------|-------------------------------|
| `required`  | validation failure                         | present value                 |
| `optional`  | missing value remains missing              | present value                 |
| `defaulted` | safe default may be used by implementation | present value wins            |

A present env value always takes precedence over a default, including an empty string.

A missing env value MAY use a default only when the policy is `defaulted`.

A missing env value MUST fail validation when the policy is `required`.

A missing env value MUST remain missing when the policy is `optional`.

## Config repository port

`ConfigRepositoryInterface` is a contracts port for reading merged global config.

It MUST NOT prescribe storage format.

It MUST NOT expose raw source traces unless those traces use safe contracts shapes.

It MUST NOT require filesystem paths.

## Config loader port

`ConfigLoaderInterface` is a contracts port for loading config input into a contract-defined config shape.

It MUST remain format-neutral.

It MUST NOT require callers to pass filesystem paths.

It MUST NOT expose whether the source came from PHP, JSON, YAML, Composer metadata, generated artifact, env source, or another implementation source.

## Merge strategy port

`MergeStrategyInterface` is a contracts port for deterministic config merge behavior.

The contracts package defines directive invariants and safe shape requirements.

The Kernel owner package implements merge execution.

## Config validator port

`ConfigValidatorInterface` is a contracts port for validating merged global config against loaded declarative rulesets.

It MUST validate using data-only ruleset models.

It MUST NOT expose package-specific callable validators.

It MUST NOT require executable validators in package `config/rules.php` files.

It MUST return `ConfigValidationResult`.

## Config ruleset model

`ConfigRuleset` is a contracts shape wrapper for validated declarative rules data.

It MUST represent rules data only.

It MUST NOT contain:

- callables;
- closures;
- executable validators;
- service instances;
- container references;
- runtime wiring objects;
- filesystem handles;
- resources.

Package `config/rules.php` files MUST return plain declarative ruleset arrays.

Package `config/rules.php` files MUST NOT return callable, closure, object, or executable validator values.

Runtime validation logic is Kernel-owned and MUST consume rules through contracts and kernel implementation.

## Config validation result

`ConfigValidationResult` is an immutable contracts result.

It MUST expose:

- success/failure state;
- deterministic list of violations.

Violation ordering MUST be deterministic.

Violations SHOULD be ordered by:

```text
root ascending using byte-order strcmp
path ascending using byte-order strcmp
reason ascending using byte-order strcmp
```

## Config validation violation

`ConfigValidationViolation` is an immutable safe violation shape.

It MUST expose:

```text
root
path
reason
expected?
actualType?
```

It MUST NOT contain raw config values.

The optional `expected` field MUST be safe, stable, and non-sensitive.

The optional `actualType` field MUST describe type only, not value.

Examples of safe `actualType` values:

```text
null
bool
int
string
list
map
float
object
resource
callable
unknown
```

The violation shape MUST NOT expose:

- raw config values;
- raw env values;
- secrets;
- credentials;
- tokens;
- request bodies;
- response bodies;
- cookies;
- authorization headers;
- private customer data;
- absolute local paths.

## JSON-like value model

Config/env contract models that export or validate JSON-like data MAY contain only:

- `null`
- `bool`
- `int`
- `string`
- list of allowed values
- map with string keys and allowed values

Floating-point values are forbidden.

The following values MUST NOT appear in exported config/env contract shapes:

- floats
- PHP objects
- closures
- resources
- streams
- filesystem handles
- service instances
- runtime wiring objects
- executable validators

If a future owner needs decimal values, they MUST be represented as strings with a documented format.

## Config source type

`ConfigSourceType` defines the stable source type vocabulary for source tracking.

The canonical source type values are:

```text
package_defaults
application_config
environment
runtime_override
generated_artifact
```

Meaning:

| source type          | meaning                                                                   |
|----------------------|---------------------------------------------------------------------------|
| `package_defaults`   | package-owned default config data                                         |
| `application_config` | application-owned config override data                                    |
| `environment`        | env-derived config input                                                  |
| `runtime_override`   | runtime-provided override from a future owner-defined source              |
| `generated_artifact` | generated config artifact source or compiled config source representation |

Source type values MUST be lowercase ASCII strings.

Source type values MUST be compared byte-for-byte.

Source type handling MUST NOT depend on locale, filesystem casing, or translated labels.

Any expansion of source types requires:

- ADR update;
- SSoT update;
- contract test update.

## Config value source

`ConfigValueSource` is the canonical contracts source-tracking model.

It MUST identify where a config value came from without storing the raw value.

It MAY expose safe metadata such as:

```text
type
root
path
keyPath
sourceId
precedence
redacted
```

The `precedence` field is explicit metadata of a concrete source trace entry.

`precedence` MUST NOT be inferred from `ConfigSourceType` alone.

`ConfigSourceType` defines source vocabulary only. It does not define merge order by itself.

It MUST NOT expose:

- raw config value;
- raw env value;
- raw `.env` value;
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

`path` and `sourceId` MUST be safe logical identifiers.

They MUST NOT be absolute local filesystem paths.

## Safe explain trace contract

Explain/source traces MUST NOT require storing raw values.

A safe explain trace MAY include:

- source type;
- logical root;
- logical key path;
- source precedence rank;
- directive name;
- safe expected type;
- safe actual type;
- redaction marker;
- stable hash of a value;
- stable length of a value.

A safe explain trace MUST NOT include:

- raw config values;
- raw env values;
- secrets;
- credentials;
- tokens;
- private keys;
- cookies;
- authorization headers;
- request bodies;
- response bodies;
- private customer data;
- absolute local paths;
- timestamps;
- random values;
- host machine identity.

Implementation outputs MAY use:

```text
hash(value)
len(value)
```

Implementation outputs MUST NOT print raw values.

## Safe trace ordering

Config trace ordering MUST be deterministic.

Contract-level safe trace entries SHOULD be ordered by:

```text
root ascending using byte-order strcmp
keyPath ascending using byte-order strcmp
precedence ascending as integer
path ascending using byte-order strcmp
sourceId ascending using byte-order strcmp, with null treated as empty string
```

This is the contracts-level safe equivalent of the Phase 0 `0.90.0` explain trace ordering:

```text
keyPath ascending
precedenceRank ascending
sourceFile ascending
```

At the contracts boundary:

- `root` + `keyPath` represent the logical config key path;
- `precedence` represents the explicit source trace precedence rank;
- `path` and `sourceId` are safe logical source identifiers and MUST NOT be absolute local filesystem paths.

Trace ordering MUST NOT depend on:

- filesystem traversal order;
- Composer package ordering;
- PHP hash-map insertion side effects;
- process locale;
- host platform;
- timestamps;
- random values.

## Config directive allowlist

The canonical directive names are:

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

`ConfigDirective` MUST represent only this allowlist.

The directive allowlist MUST NOT expand without:

- ADR update;
- SSoT update;
- contract test update;
- lock-source review against Phase 0 config merge semantics.

## Reserved directive namespace

The `@*` namespace is reserved for config directives.

Any config key beginning with `@` is interpreted as a directive candidate.

If the key is not one of the allowed directive keys, validation MUST fail before merge.

Unknown `@directive` keys MUST NOT be treated as normal config keys.

Examples of forbidden unknown directive keys:

```text
@set
@delete
@override
@custom
@env
```

## Directive syntax

A directive level is a map containing a directive key.

A valid directive level MUST contain exactly one allowed directive key.

Valid examples:

```php
[
    '@append' => ['a'],
]
```

```php
[
    '@replace' => [
        'enabled' => true,
    ],
]
```

Invalid examples:

```php
[
    '@append' => ['a'],
    '@remove' => ['b'],
]
```

```php
[
    '@merge' => [
        'a' => 1,
    ],
    'b' => 2,
]
```

## Exclusive-level rule

A config map level MUST NOT mix directive keys and regular keys.

A level with an allowed directive key MUST contain only that directive key.

A level with a regular config key MUST NOT contain directive keys.

This rule is evaluated before merge.

## Directive payload model

Directive payloads MUST follow the JSON-like value model.

Directive payloads MUST NOT contain:

- floats;
- PHP objects;
- closures;
- resources;
- executable validators;
- service instances;
- runtime wiring objects.

Directive payloads MUST NOT contain secrets or raw env values.

## Empty-array directive rule

An empty array used as a directive payload is explicit.

It MUST NOT be treated as missing.

Directive empty-array behavior is locked as follows:

| directive | empty-array behavior                |
|-----------|-------------------------------------|
| `append`  | deterministic no-op                 |
| `prepend` | deterministic no-op                 |
| `remove`  | deterministic no-op                 |
| `merge`   | deterministic no-op                 |
| `replace` | replaces target with an empty array |

This rule is part of the Phase 0 config merge lock-source alignment.

## Directive error precedence

Directive validation errors MUST be deterministic.

When multiple directive problems exist, the first reported category MUST follow this precedence:

1. unknown or otherwise forbidden reserved `@*` directive key;
2. exclusive-level violation;
3. directive payload shape violation;
4. JSON-like value violation.

Unknown `@directive` MUST fail validation before merge.

The first category is the contracts-level equivalent of the Phase 0 `0.90.0` reserved namespace guard:

```text
CORETSIA_CONFIG_RESERVED_NAMESPACE_USED
```

The Kernel implementation MAY report multiple violations, but ordering MUST remain deterministic.

## Directive validation codes

Unknown directive validation MUST produce a deterministic violation code before merge.

The canonical contract code for an unknown or otherwise forbidden reserved `@*` directive key is:

```text
CONFIG_DIRECTIVE_UNKNOWN
```

This code maps to the Phase 0 `0.90.0` lock-source category:

```text
CORETSIA_CONFIG_RESERVED_NAMESPACE_USED
```

The canonical contract code for an exclusive-level directive violation is:

```text
CONFIG_DIRECTIVE_EXCLUSIVE_LEVEL_VIOLATION
```

This code maps to the Phase 0 `0.90.0` lock-source category:

```text
CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL
```

The canonical contract code for an invalid directive payload shape is:

```text
CONFIG_DIRECTIVE_INVALID_PAYLOAD
```

This code maps to the Phase 0 `0.90.0` lock-source category:

```text
CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH
```

The canonical contract code for a forbidden JSON-like value inside directive payload is:

```text
CONFIG_DIRECTIVE_INVALID_JSON_LIKE_VALUE
```

This code maps to the Phase 0 `0.70.0` lock-source category:

```text
CORETSIA_JSON_FLOAT_FORBIDDEN
```

Validation codes MUST be stable ASCII strings.

Validation codes MUST NOT contain raw config keys, raw values, env values, filesystem paths, or environment-specific bytes.

## Config merge ownership

The contracts package does not implement config merge.

The Kernel config engine owns concrete merge behavior.

Contracts define only:

- directive allowlist;
- directive syntax invariants;
- source type vocabulary;
- safe source-tracking model;
- validation result shape;
- validation violation shape;
- env missing vs empty semantics.

## Config artifact ownership

A future config artifact MAY be written by the Kernel owner package.

Config artifacts are not owned by `core/contracts`.

This SSoT does not define config artifact schema.

## Security and redaction

Config/env contracts MUST NOT require storing secrets.

Config/env contracts MUST NOT leak:

- `.env` values;
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

Secret-backed runtime behavior belongs to owner packages, not to contracts shapes.

## Non-goals

This SSoT does not define:

- config merge implementation;
- config file loading implementation;
- env loading implementation;
- filesystem scanning;
- `.env` parsing;
- concrete config source paths;
- concrete config artifact schema;
- DI tags;
- runtime service registration;
- CLI command behavior;
- package-specific validation logic;
- executable validators.
