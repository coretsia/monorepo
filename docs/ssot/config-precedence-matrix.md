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

```yaml
ssotVersion: 1
status: pre-stable
owner: core/kernel
```

## Scope

This document defines two independent precedence contracts: Bootstrap Phase A source resolution and ConfigKernel Phase B configuration merging.

The Phase A source matrix is owned by `BootstrapSourceResolver` and does not use ConfigKernel ranks. The existing Phase B rank matrix remains owned by `ConfigKernel`.

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
packages/core/kernel/src/Config/ConfigKernel.php
packages/core/kernel/src/Config/ConfigMerger.php
packages/core/kernel/src/Config/Explain/ConfigExplainer.php
packages/core/kernel/src/Config/Loaders/PackageDefaultsConfigLoader.php
packages/core/kernel/src/Config/Loaders/ApplicationConfigLoader.php
packages/core/kernel/src/Config/Loaders/EnvironmentOverlayLoader.php
```

The source type vocabulary is represented by:

```text
packages/core/contracts/src/Config/ConfigSourceType.php
```

## Core invariant

Lower rank is weaker.

Higher rank is stronger.

A stronger source overrides or mutates a weaker source according to normalized config payloads and merge semantics.

Ranks MUST be explicit.

Runtime code MUST NOT infer precedence from source type alone.

`ConfigSourceType` is vocabulary.

Concrete precedence is recorded on each `ConfigValueSource`.

## Bootstrap Phase A source precedence

Bootstrap Phase A has its own logical-key source precedence. It is resolved before the ConfigKernel Phase B rank matrix below and MUST NOT be implemented as additional Phase B merge ranks.

### Frozen appEnv selection

Initial `appEnv` selection uses the following order, strongest first:

| Priority | Source | Condition |
|---------:|--------------------------------------|-------------------------------------------------------|
| 1 | Explicit `BootstrapInput::appEnv()` | Present and valid |
| 2 | Process `CORETSIA_BOOT_APP_ENV` | Explicit `BootstrapEnvSourcePolicy::AllowSystem` only |
| 3 | `.env.local` `CORETSIA_BOOT_APP_ENV` | Present and valid |
| 4 | `.env` `CORETSIA_BOOT_APP_ENV` | Present and valid |
| 5 | `config/app.php` `appEnv` | Present and valid |
| 6 | `kernel.boot.default_env` | Package fallback |

The process environment MUST NOT select appEnv merely because the subsequently selected production-like environment defaults to `allow_system`.

Explicit `BootstrapInput::appEnv()` wins over a different lower-priority process value without reporting a false mismatch.

After selecting appEnv, Phase A freezes that value. It then loads `.env.<appEnv>` and `.env.<appEnv>.local`. Their `CORETSIA_BOOT_APP_ENV` values are validation-only and MUST exactly match the frozen value. They MUST NOT trigger another environment selection.

### Source policy selection

The effective `BootstrapEnvSourcePolicy` is selected exactly once, after appEnv selection:

1. Explicit `BootstrapInput::envSourcePolicy()`.
2. `kernel.env.source_policy.default_production` for `prod` and `production`.
3. `kernel.env.source_policy.default_local` for every other appEnv.

`strict_dotenv` ignores all process environment values, including unknown `CORETSIA_BOOT_*` keys. It does not use process fallback.

`allow_system` permits process bootstrap overrides and gives process environment values precedence over dotenv. Under this policy, unknown or invalid process `CORETSIA_BOOT_*` entries are rejected. Both policies retain ordinary dotenv values in the immutable environment repository according to their respective precedence rules.

### Normal bootstrap source precedence

For normalized bootstrap override values other than the frozen appEnv and source policy, the effective source order is:

| Priority | Source | Behavior |
|---------:|----------------------------------------|---------------------------------------------------------------------|
| 1 | Explicit target-local `BootstrapInput` | Only applicable explicit `preset`, `debug`, and `artifactsCacheDir` |
| 2 | Process `CORETSIA_BOOT_*` | Permitted only under `allow_system` |
| 3 | `.env.<appEnv>.local` | Environment-specific highest dotenv layer |
| 4 | `.env.<appEnv>` | Environment-specific dotenv layer |
| 5 | `.env.local` | Base local dotenv layer |
| 6 | `.env` | Base dotenv layer |
| 7 | `config/app.php` | Bootstrap-only PHP source |
| 8 | `kernel.boot.*` | Applicable package defaults |

Every permitted layer MUST be parsed and validated before it is merged. A valid higher-priority value MUST NOT conceal invalid data in a lower-priority layer.

For `activeTargets`, the higher-priority non-empty list completely replaces the lower-priority list. Absence retains the previous value; explicit `[]` is invalid. The project-wide `['web']` fallback is applied only by DependencySync when no configured membership exists.

For `presets`, priority applies separately to each `presets[target]` entry. A higher-priority entry replaces only the same target. A target-specific preset takes precedence over a merged global `preset`, irrespective of which source supplied the global fallback.

For module overrides, priority applies independently to `moduleOverrides[target].include` and `moduleOverrides[target].exclude`. Replacing `web.include` MUST NOT replace `web.exclude` or either field of `worker`. An explicit `[]` clears only the corresponding list. An empty JSON object `{}` contributes no entries.

`debug` and `artifactsCacheDir` use scalar replacement. They are project-wide source values; explicit target-local `BootstrapInput` values are applied only when constructing the target-local `BootstrapConfig`.

`BootstrapSourceSnapshot` retains the winning safe provenance for each logical scalar, `presets[target]`, and `moduleOverrides[target].include/exclude`. Provenance, raw JSON bytes, absolute application-root identity, inactive target-specific values, and unrelated environment keys MUST NOT enter the canonical installation verification identity.

This precedence contract is implemented by:

```text
packages/core/kernel/src/Boot/BootstrapSourceResolver.php
packages/core/kernel/src/Boot/BootstrapConfigResolver.php
packages/core/kernel/src/Boot/BootstrapOverridesLoader.php
```

The Phase B ranks below are unchanged and MUST NOT be applied to bootstrap-only fields.

## Active Phase B rank matrix

| Rank | Active | Source category                         | Source type                           | Source path / mechanism                                    | Notes                                                                                               |
|-----:|--------|-----------------------------------------|---------------------------------------|------------------------------------------------------------|-----------------------------------------------------------------------------------------------------|
|   10 | yes    | Package defaults                        | `ConfigSourceType::PackageDefault`    | package `config/<root>.php`                                | Weakest active config value source. Package defaults MUST NOT use `config/roots.php`.               |
|  100 | yes    | Application-root shared aggregate       | `ConfigSourceType::ApplicationConfig` | `config/roots.php`                                         | Aggregate root-map file for shared application-root config.                                         |
|  101 | yes    | Application-root shared split root      | `ConfigSourceType::ApplicationConfig` | `config/<root>.php`                                        | Split root-subtree file; stronger than same-layer aggregate.                                        |
|  200 | yes    | Application-root environment aggregate  | `ConfigSourceType::ApplicationConfig` | `config/environments/<appEnv>/roots.php`                   | Aggregate root-map file for environment-specific application-root config.                           |
|  201 | yes    | Application-root environment split root | `ConfigSourceType::ApplicationConfig` | `config/environments/<appEnv>/<root>.php`                  | Split root-subtree file; stronger than same-layer aggregate.                                        |
|  300 | yes    | App-target shared aggregate             | `ConfigSourceType::AppConfig`         | `apps/<appTarget>/config/roots.php`                        | Aggregate root-map file for shared app-target config.                                               |
|  301 | yes    | App-target shared split root            | `ConfigSourceType::AppConfig`         | `apps/<appTarget>/config/<root>.php`                       | Split root-subtree file; stronger than same-layer aggregate.                                        |
|  400 | yes    | App-target environment aggregate        | `ConfigSourceType::AppConfig`         | `apps/<appTarget>/config/environments/<appEnv>/roots.php`  | Aggregate root-map file for environment-specific app-target config.                                 |
|  401 | yes    | App-target environment split root       | `ConfigSourceType::AppConfig`         | `apps/<appTarget>/config/environments/<appEnv>/<root>.php` | Split root-subtree file; strongest file-config layer.                                               |
|  500 | yes    | Env overlays                            | `ConfigSourceType::Env`               | ruleset-derived or explicit env overlay mapping            | Strongest active Phase B config source. Applies only where mapping exists and env value is present. |

## Compact active order

The active Phase B order is:

```text
package defaults
  < application-root shared aggregate
  < application-root shared split root
  < application-root environment aggregate
  < application-root environment split root
  < app-target shared aggregate
  < app-target shared split root
  < app-target environment aggregate
  < app-target environment split root
  < env overlays
```

The grouped order is:

```text
package defaults
  < application-root shared
  < application-root environment
  < app-target shared
  < app-target environment
  < env overlays
```

## Source type matrix

| Source type                           | Active categories                                                                   | Effective precedence source                                                                                          |
|---------------------------------------|-------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|
| `ConfigSourceType::PackageDefault`    | Package defaults                                                                    | Package default source candidate metadata, normalized by `PackageDefaultsConfigLoader` and folded by `ConfigKernel`. |
| `ConfigSourceType::ApplicationConfig` | Application-root shared aggregate/root, application-root environment aggregate/root | Application-root config entry metadata and safe `configSourceFiles` metadata produced by `ApplicationConfigLoader`.  |
| `ConfigSourceType::AppConfig`         | App-target shared aggregate/root, app-target environment aggregate/root             | App-target config entry metadata and safe `configSourceFiles` metadata produced by `ApplicationConfigLoader`.        |
| `ConfigSourceType::Env`               | Env overlays                                                                        | Env overlay source metadata produced by `EnvironmentOverlayLoader`.                                                  |

`ConfigSourceType` MUST NOT be used as the only source of precedence.

The same source type can appear at multiple ranks.

Example:

```text
ConfigSourceType::ApplicationConfig at rank 100
ConfigSourceType::ApplicationConfig at rank 101
ConfigSourceType::ApplicationConfig at rank 200
ConfigSourceType::ApplicationConfig at rank 201
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

Package defaults are weaker than all application-root, app-target, and env overlay sources.

## Application-root shared aggregate/root

Application-root shared aggregate config is active at rank `100`.

```text
config/roots.php
```

Application-root shared split root config is active at rank `101`.

```text
config/<root>.php
```

At the same layer:

```text
application-root shared aggregate < application-root shared split root
```

## Application-root environment aggregate/root

Application-root environment aggregate config is active at rank `200`.

```text
config/environments/<appEnv>/roots.php
```

Application-root environment split root config is active at rank `201`.

```text
config/environments/<appEnv>/<root>.php
```

At the same layer:

```text
application-root environment aggregate < application-root environment split root
```

Application-root environment config is stronger than application-root shared config.

## App-target shared aggregate/root

App-target shared aggregate config is active at rank `300`.

```text
apps/<appTarget>/config/roots.php
```

App-target shared split root config is active at rank `301`.

```text
apps/<appTarget>/config/<root>.php
```

At the same layer:

```text
app-target shared aggregate < app-target shared split root
```

App-target shared config is stronger than application-root environment config.

## App-target environment aggregate/root

App-target environment aggregate config is active at rank `400`.

```text
apps/<appTarget>/config/environments/<appEnv>/roots.php
```

App-target environment split root config is active at rank `401`.

```text
apps/<appTarget>/config/environments/<appEnv>/<root>.php
```

At the same layer:

```text
app-target environment aggregate < app-target environment split root
```

App-target environment config is stronger than app-target shared config.

App-target environment split root config is the strongest active file-config layer.

## Env overlays

Env overlays are active at rank `500`.

Env overlays use:

```text
ConfigSourceType::Env
```

Env overlays are generated only from:

- ruleset-derived mappings for env-overlay-capable scalar rule types;
- explicitly supplied env overlay mappings.

A valid ruleset leaf does not imply an env overlay mapping. Validation-only rule types that are not env-overlay-capable do not participate in automatic env overlay generation.

Env overlays are stronger than all file config sources.

Unknown env vars MUST NOT create config keys automatically.

Env overlays apply only where:

1. a ruleset-derived or explicit env overlay mapping exists;
2. an env value exists in the immutable `EnvRepositoryInterface` snapshot;
3. the env value can be coerced according to the mapping's supported scalar type.

User-owned/custom roots without rulesets MUST NOT receive env overlays automatically.

## Aggregate versus split root behavior

`roots.php` is the aggregate root-map file for a config layer.

`<root>.php` is the split root-subtree file for one config root.

At the same layer, split root files are stronger than aggregate root-map files.

This rule applies to all file-config layers:

| Aggregate rank | Split root rank | Layer                        |
|---------------:|----------------:|------------------------------|
|            100 |             101 | Application-root shared      |
|            200 |             201 | Application-root environment |
|            300 |             301 | App-target shared            |
|            400 |             401 | App-target environment       |

## Aggregate versus split root example

Same-layer aggregate file:

```text
config/roots.php
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
config/kernel.php
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

| Rank | Source                                          |
|-----:|-------------------------------------------------|
|  100 | `config/roots.php`                              |
|  101 | `config/kernel.php`                             |
|  200 | `config/environments/local/roots.php`           |
|  201 | `config/environments/local/kernel.php`          |
|  300 | `apps/api/config/roots.php`                     |
|  301 | `apps/api/config/kernel.php`                    |
|  400 | `apps/api/config/environments/local/roots.php`  |
|  401 | `apps/api/config/environments/local/kernel.php` |

For the same config path, the effective order is:

```text
application-root shared
  < application-root environment
  < app-target shared
  < app-target environment
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
- an explicitly supplied env overlay mapping.

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

## Artifact fingerprint provenance implications

`ConfigKernel::compile(...)` MUST expose safe Phase B provenance metadata needed by downstream artifact fingerprinting.

This metadata includes:

```text
envOverlayMappings
configSourceFiles
```

`envOverlayMappings` MUST be the exact resolved mapping list produced by `EnvironmentOverlayLoader`.

`configSourceFiles` MUST be produced by `ApplicationConfigLoader` for application-root/app-target config candidates.

`configSourceFiles` MUST distinguish:

```text
application-root shared config files
application-root environment config files
app-target shared config files
app-target environment config files
user-owned/custom split-root files
```

`configSourceFiles` MUST NOT change precedence.

`configSourceFiles` MUST NOT participate in merge semantics.

`configSourceFiles` is provenance metadata only.

Downstream artifact/fingerprint stages MAY use `configSourceFiles` content hashes and lengths as fingerprint input.

Downstream artifact/fingerprint stages MUST NOT require `ConfigKernel` to calculate artifact fingerprints.

`ConfigSourceType` values are vocabulary only.

Effective precedence comes from the concrete source entry.

## Matrix consistency requirements

This document MUST stay consistent with:

```text
docs/ssot/config-merge-order.md
packages/core/kernel/src/Config/ConfigKernel.php
packages/core/kernel/src/Config/Loaders/ApplicationConfigLoader.php
packages/core/kernel/src/Config/Loaders/EnvironmentOverlayLoader.php
packages/core/kernel/src/Config/Explain/ConfigExplainer.php
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
config/roots.php beats config/kernel.php
```

Correct precedence:

```text
config/roots.php < config/kernel.php
```

### Invalid: env var creates config key without mapping

Invalid behavior:

```text
SOME_RANDOM_ENV_VAR creates some.random.env.var
```

Unknown env vars MUST NOT create config keys.

### Invalid: source type alone decides precedence

Invalid behavior:

```text
ConfigSourceType::ApplicationConfig always has one fixed precedence
```

Correct behavior:

```text
ConfigSourceType::ApplicationConfig may appear at ranks 100, 101, 200, and 201.
Concrete ConfigValueSource precedence is authoritative.
```

## Enforcement rails

Expected tests include:

```text
ConfigPrecedenceMatrixTest.php
ConfigExplainReturnsStableSourceTypesTest.php
ConfigAggregateAndSplitFilesMergeOrderTest.php
ConfigEnvironmentSpecificOverlaysPrecedenceTest.php
packages/core/kernel/tests/Integration/UserOwnedConfigRootsAreMergedButNotCoretsiaValidatedTest.php
```

Tests SHOULD verify:

- the active rank matrix;
- package defaults are weaker than application-root/app-target/env sources;
- aggregate `roots.php` is weaker than same-layer split `<root>.php`;
- application-root shared is weaker than application-root environment;
- application-root environment is weaker than app-target shared;
- app-target shared is weaker than app-target environment;
- app-target environment is weaker than env overlays;
- env projection is deterministic;
- unknown env vars do not create config keys;
- user-owned/custom roots without rules are merged and marked unvalidated;
- explain output uses stable source types and source ranks;
- `configSourceFiles` is safe provenance metadata and does not affect rank order;
- `envOverlayMappings` returned by `ConfigKernel::compile(...)` match the resolved env overlay mappings.

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
- CLI command behavior.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Config and env SSoT](./config-and-env.md)
- [Config Roots Registry](./config-roots.md)
- [Config Directives Examples](./config-directives.md)
- [Config Merge Order](./config-merge-order.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
