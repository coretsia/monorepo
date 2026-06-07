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

# Config Precedence Matrix (SSoT)

## Scope

This document is the canonical precedence matrix for ConfigKernel Phase B.

It is the matrix form of the merge-order narrative owned by:

```text
docs/ssot/config-merge-order.md
```

Both documents MUST describe the same active Phase B order.

This document is table-first. `config-merge-order.md` is narrative-first.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

The precedence matrix exists to make config source order explicit, testable, and explainable.

It defines:

- source category;
- source type;
- priority/rank;
- active versus reserved/future status;
- file shape;
- override behavior;
- user-owned/custom root behavior;
- env overlay projection examples.

## Canonical authority

`ConfigKernel` owns orchestration of the active Phase B order.

`ConfigMerger` owns only the deterministic two-node merge operation.

`ConfigExplainer` consumes effective source precedence/order metadata after merge.

The canonical runtime implementation paths are:

```text
framework/packages/core/kernel/src/Config/ConfigKernel.php
framework/packages/core/kernel/src/Config/ConfigMerger.php
framework/packages/core/kernel/src/Config/Explain/ConfigExplainer.php
framework/packages/core/kernel/src/Config/Loaders/PackageDefaultsConfigLoader.php
framework/packages/core/kernel/src/Config/Loaders/SkeletonConfigLoader.php
framework/packages/core/kernel/src/Config/Loaders/EnvironmentOverlayLoader.php
```

The source type vocabulary is represented by:

```text
framework/packages/core/contracts/src/Config/ConfigSourceType.php
```

## Core invariant

Lower rank is weaker.

Higher rank is stronger.

A stronger source overrides or mutates a weaker source according to normalized config payloads and merge semantics.

Ranks MUST be explicit.

Runtime code MUST NOT infer precedence from source type alone.

`ConfigSourceType` is vocabulary.

Concrete precedence is recorded on each `ConfigValueSource`.

## Active and reserved rank matrix

| Rank | Active | Source category                 | Source type                        | Source path / mechanism                                             | Notes                                                                                               |
|-----:|--------|---------------------------------|------------------------------------|---------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------|
|   10 | yes    | Package defaults                | `ConfigSourceType::PackageDefault` | package `config/<root>.php`                                         | Weakest active config value source. Package defaults MUST NOT use `config/roots.php`.               |
|   50 | no     | Preset/mode overlays            | reserved/future                    | reserved/future                                                     | Reserved for future explicit preset/mode overlay epic. Not active Phase 1 behavior.                 |
|  100 | yes    | Skeleton shared aggregate       | `ConfigSourceType::SkeletonConfig` | `skeleton/config/roots.php`                                         | Aggregate root-map file for shared skeleton config.                                                 |
|  101 | yes    | Skeleton shared split root      | `ConfigSourceType::SkeletonConfig` | `skeleton/config/<root>.php`                                        | Split root-subtree file; stronger than same-layer aggregate.                                        |
|  200 | yes    | Skeleton environment aggregate  | `ConfigSourceType::SkeletonConfig` | `skeleton/config/environments/<appEnv>/roots.php`                   | Aggregate root-map file for environment-specific skeleton config.                                   |
|  201 | yes    | Skeleton environment split root | `ConfigSourceType::SkeletonConfig` | `skeleton/config/environments/<appEnv>/<root>.php`                  | Split root-subtree file; stronger than same-layer aggregate.                                        |
|  300 | yes    | App shared aggregate            | `ConfigSourceType::AppConfig`      | `skeleton/apps/<appTarget>/config/roots.php`                        | Aggregate root-map file for shared app config.                                                      |
|  301 | yes    | App shared split root           | `ConfigSourceType::AppConfig`      | `skeleton/apps/<appTarget>/config/<root>.php`                       | Split root-subtree file; stronger than same-layer aggregate.                                        |
|  400 | yes    | App environment aggregate       | `ConfigSourceType::AppConfig`      | `skeleton/apps/<appTarget>/config/environments/<appEnv>/roots.php`  | Aggregate root-map file for environment-specific app config.                                        |
|  401 | yes    | App environment split root      | `ConfigSourceType::AppConfig`      | `skeleton/apps/<appTarget>/config/environments/<appEnv>/<root>.php` | Split root-subtree file; strongest file-config layer.                                               |
|  500 | yes    | Env overlays                    | `ConfigSourceType::Env`            | ruleset-derived or explicit env overlay mapping                     | Strongest active Phase B config source. Applies only where mapping exists and env value is present. |

## Compact active order

The active Phase B order is:

```text
package defaults
  < skeleton shared aggregate
  < skeleton shared split root
  < skeleton environment aggregate
  < skeleton environment split root
  < app shared aggregate
  < app shared split root
  < app environment aggregate
  < app environment split root
  < env overlays
```

The grouped order is:

```text
package defaults
  < skeleton shared
  < skeleton environment
  < app shared
  < app environment
  < env overlays
```

## Source type matrix

| Source type                        | Active categories                                                   | Effective precedence source                                                                                          |
|------------------------------------|---------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|
| `ConfigSourceType::PackageDefault` | Package defaults                                                    | Package default source candidate metadata, normalized by `PackageDefaultsConfigLoader` and folded by `ConfigKernel`. |
| `ConfigSourceType::SkeletonConfig` | Skeleton shared aggregate/root, skeleton environment aggregate/root | Skeleton config entry metadata produced by `SkeletonConfigLoader`.                                                   |
| `ConfigSourceType::AppConfig`      | App shared aggregate/root, app environment aggregate/root           | App config entry metadata produced by `SkeletonConfigLoader`.                                                        |
| `ConfigSourceType::Env`            | Env overlays                                                        | Env overlay source metadata produced by `EnvironmentOverlayLoader`.                                                  |

`ConfigSourceType` MUST NOT be used as the only source of precedence.

The same source type can appear at multiple ranks.

Example:

```text
ConfigSourceType::SkeletonConfig at rank 100
ConfigSourceType::SkeletonConfig at rank 101
ConfigSourceType::SkeletonConfig at rank 200
ConfigSourceType::SkeletonConfig at rank 201
```

The concrete source rank is authoritative.

## Package defaults

Package defaults are active at rank `10`.

Package default files use this shape:

```text
config/<root>.php
```

Package defaults MUST return the subtree for `<root>`.

Valid package default shape:

```php
return [
    'boot' => [
        'default_env' => 'prod',
    ],
];
```

Invalid package default shape:

```php
return [
    'kernel' => [
        'boot' => [
            'default_env' => 'prod',
        ],
    ],
];
```

Package defaults MUST NOT use:

```text
config/roots.php
```

Package defaults are weaker than all skeleton, app, and env overlay sources.

## Preset/mode overlays

Preset/mode overlays are reserved at rank `50`.

They are listed in this matrix for forward compatibility and documentation continuity.

They are NOT active Phase 1 ConfigKernel behavior.

A future preset/mode overlay epic MUST update:

```text
docs/ssot/config-merge-order.md
docs/ssot/config-precedence-matrix.md
runtime implementation
integration tests
```

before preset/mode overlays become active.

No runtime code may treat rank `50` as active until that future epic explicitly introduces it.

## Skeleton shared aggregate/root

Skeleton shared aggregate config is active at rank `100`.

```text
skeleton/config/roots.php
```

Skeleton shared split root config is active at rank `101`.

```text
skeleton/config/<root>.php
```

At the same layer:

```text
skeleton shared aggregate < skeleton shared split root
```

## Skeleton environment aggregate/root

Skeleton environment aggregate config is active at rank `200`.

```text
skeleton/config/environments/<appEnv>/roots.php
```

Skeleton environment split root config is active at rank `201`.

```text
skeleton/config/environments/<appEnv>/<root>.php
```

At the same layer:

```text
skeleton environment aggregate < skeleton environment split root
```

Skeleton environment config is stronger than skeleton shared config.

## App shared aggregate/root

App shared aggregate config is active at rank `300`.

```text
skeleton/apps/<appTarget>/config/roots.php
```

App shared split root config is active at rank `301`.

```text
skeleton/apps/<appTarget>/config/<root>.php
```

At the same layer:

```text
app shared aggregate < app shared split root
```

App shared config is stronger than skeleton environment config.

## App environment aggregate/root

App environment aggregate config is active at rank `400`.

```text
skeleton/apps/<appTarget>/config/environments/<appEnv>/roots.php
```

App environment split root config is active at rank `401`.

```text
skeleton/apps/<appTarget>/config/environments/<appEnv>/<root>.php
```

At the same layer:

```text
app environment aggregate < app environment split root
```

App environment config is stronger than app shared config.

App environment split root config is the strongest active file-config layer.

## Env overlays

Env overlays are active at rank `500`.

Env overlays use:

```text
ConfigSourceType::Env
```

Env overlays are generated only from:

- loaded declarative config rulesets;
- optional explicit env overlay mappings supplied by a future user/module mapping mechanism.

Env overlays are stronger than all file config sources.

Unknown env vars MUST NOT create config keys automatically.

Env overlays apply only where:

1. a ruleset-derived or explicit env overlay mapping exists;
2. an env value exists in the immutable `EnvRepositoryInterface` snapshot;
3. the env value can be coerced according to the declared scalar rule type.

User-owned/custom roots without rulesets MUST NOT receive env overlays automatically.

## Aggregate versus split root behavior

`roots.php` is the aggregate root-map file for a config layer.

`<root>.php` is the split root-subtree file for one config root.

At the same layer, split root files are stronger than aggregate root-map files.

This rule applies to all file-config layers:

| Aggregate rank | Split root rank | Layer                |
|---------------:|----------------:|----------------------|
|            100 |             101 | Skeleton shared      |
|            200 |             201 | Skeleton environment |
|            300 |             301 | App shared           |
|            400 |             401 | App environment      |

## Aggregate versus split root example

Same-layer aggregate file:

```text
skeleton/config/roots.php
```

```php
return [
    'kernel' => [
        'boot' => [
            'default_env' => 'prod',
            'default_app' => 'main',
        ],
    ],
];
```

Same-layer split root file:

```text
skeleton/config/kernel.php
```

```php
return [
    'boot' => [
        'default_env' => 'dev',
    ],
];
```

Effective result:

```php
[
    'kernel' => [
        'boot' => [
            'default_app' => 'main',
            'default_env' => 'dev',
        ],
    ],
]
```

The split root file overrides the touched path.

Untouched aggregate paths survive.

## Environment and app precedence example

Example source stack:

| Rank | Source                                                   |
|-----:|----------------------------------------------------------|
|  100 | `skeleton/config/roots.php`                              |
|  101 | `skeleton/config/kernel.php`                             |
|  200 | `skeleton/config/environments/local/roots.php`           |
|  201 | `skeleton/config/environments/local/kernel.php`          |
|  300 | `skeleton/apps/api/config/roots.php`                     |
|  301 | `skeleton/apps/api/config/kernel.php`                    |
|  400 | `skeleton/apps/api/config/environments/local/roots.php`  |
|  401 | `skeleton/apps/api/config/environments/local/kernel.php` |

For the same config path, the effective order is:

```text
skeleton shared
  < skeleton environment
  < app shared
  < app environment
```

## Env overlay projection examples

Config path projection to env name is deterministic.

| Config path                               | Env name                                  |
|-------------------------------------------|-------------------------------------------|
| `kernel.boot.default_env`                 | `KERNEL_BOOT_DEFAULT_ENV`                 |
| `kernel.modules.discovery.source`         | `KERNEL_MODULES_DISCOVERY_SOURCE`         |
| `kernel.some-key.enabled`                 | `KERNEL_SOME_KEY_ENABLED`                 |
| `custom_app.feature_flags.search-enabled` | `CUSTOM_APP_FEATURE_FLAGS_SEARCH_ENABLED` |

Projection rules:

```text
ASCII letters are uppercased
. maps to _
- maps to _
_ is preserved
ASCII digits are preserved
```

Projection is locale-independent.

Unknown env vars do not create config keys.

## Env overlay precedence example

File config before env overlay:

```php
[
    'kernel' => [
        'boot' => [
            'default_env' => 'prod',
        ],
    ],
]
```

Env snapshot contains:

```text
KERNEL_BOOT_DEFAULT_ENV=local
```

A loaded ruleset allows env overlay for:

```text
kernel.boot.default_env
```

Final effective result:

```php
[
    'kernel' => [
        'boot' => [
            'default_env' => 'local',
        ],
    ],
]
```

If no env overlay mapping exists for `kernel.boot.default_env`, then `KERNEL_BOOT_DEFAULT_ENV` MUST NOT affect config.

## User-owned/custom roots

User-owned/custom roots may be loaded from:

- aggregate `roots.php`;
- split `<root>.php` files when the root name is included in the deterministic split-root candidate list.

User-owned/custom roots are merged as deterministic config data.

If a custom root has no loaded ruleset, it is:

```text
merged
explained
fingerprinted by downstream artifact/fingerprint stages
compiled by downstream artifact stages
```

and marked as:

```text
user_owned
unvalidated
```

A user-owned/custom root without a ruleset MUST NOT be rejected solely because it has no ruleset.

A user-owned/custom root without a ruleset MUST NOT receive env overlays automatically.

A user-owned/custom root MAY receive env overlays only when one of the following exists:

- a matching loaded ruleset;
- an explicit env overlay mapping supplied by a future user/module mechanism.

## Explain source implications

Config explain output MUST record effective source metadata from concrete `ConfigValueSource` entries.

Explain output MAY expose safe metadata such as:

```text
source type
source id
source rank / precedence
source order
config dot path
directive name
safe logical source path
owner metadata
```

Explain output MUST NOT expose:

- raw config values;
- raw env values;
- secrets;
- tokens;
- DSNs;
- cookies;
- headers;
- raw SQL;
- payloads;
- stack traces;
- previous throwable messages;
- absolute local paths.

`ConfigSourceType` values are vocabulary only.

Effective precedence comes from the concrete source entry.

## Matrix consistency requirements

This document MUST stay consistent with:

```text
docs/ssot/config-merge-order.md
framework/packages/core/kernel/src/Config/ConfigKernel.php
framework/packages/core/kernel/src/Config/Explain/ConfigExplainer.php
```

If a rank changes, the following MUST be updated together:

```text
docs/ssot/config-merge-order.md
docs/ssot/config-precedence-matrix.md
ConfigKernel precedence constants / entry metadata
ConfigExplainer explain expectations
integration tests
```

## Invalid behavior examples

### Invalid: package defaults use aggregate file

Invalid package default source:

```text
package config/roots.php
```

Package defaults MUST use:

```text
config/<root>.php
```

### Invalid: same-layer aggregate beats split root

Invalid precedence:

```text
skeleton/config/roots.php beats skeleton/config/kernel.php
```

Correct precedence:

```text
skeleton/config/roots.php < skeleton/config/kernel.php
```

### Invalid: env var creates config key without mapping

Invalid behavior:

```text
SOME_RANDOM_ENV_VAR creates some.random.env.var
```

Unknown env vars MUST NOT create config keys.

### Invalid: preset/mode overlays active by convention

Invalid behavior:

```text
rank 50 preset/mode overlays are active because they are listed in this matrix
```

Rank `50` is reserved/future and inactive until a future epic explicitly activates it.

### Invalid: source type alone decides precedence

Invalid behavior:

```text
ConfigSourceType::SkeletonConfig always has one fixed precedence
```

Correct behavior:

```text
ConfigSourceType::SkeletonConfig may appear at ranks 100, 101, 200, and 201.
Concrete ConfigValueSource precedence is authoritative.
```

## Enforcement rails

Expected tests include:

```text
ConfigPrecedenceMatrixTest.php
ConfigExplainReturnsStableSourceTypesTest.php
ConfigAggregateAndSplitFilesMergeOrderTest.php
ConfigEnvironmentSpecificOverlaysPrecedenceTest.php
UserOwnedConfigRootsAreMergedButNotFrameworkValidatedTest.php
```

Tests SHOULD verify:

- the active rank matrix;
- package defaults are weaker than skeleton/app/env sources;
- preset/mode overlays are listed but inactive;
- aggregate `roots.php` is weaker than same-layer split `<root>.php`;
- skeleton shared is weaker than skeleton environment;
- skeleton environment is weaker than app shared;
- app shared is weaker than app environment;
- app environment is weaker than env overlays;
- env projection is deterministic;
- unknown env vars do not create config keys;
- user-owned/custom roots without rules are merged and marked unvalidated;
- explain output uses stable source types and source ranks.

## Non-goals

This document does not define:

- config rules DSL;
- semantic validation details;
- directive payload and merge-time base type validation;
- config file discovery implementation;
- package/module discovery implementation;
- env value coercion internals;
- config explain output schema;
- generated artifact schemas;
- CLI command behavior;
- future runtime override semantics.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Config and env SSoT](./config-and-env.md)
- [Config Roots Registry](./config-roots.md)
- [Config Directives Examples](./config-directives.md)
- [Config Merge Order](./config-merge-order.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
