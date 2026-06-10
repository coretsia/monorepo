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

# Config Merge Order (SSoT)

## Scope

This document is the canonical narrative for the active Phase B config merge order.

It defines the deterministic order in which normalized config value sources are folded into the final global config by:

```text
framework/packages/core/kernel/src/Config/ConfigKernel.php
```

It complements the matrix form owned by:

```text
docs/ssot/config-precedence-matrix.md
```

This document is narrative-first. The precedence matrix is table-first. Both documents MUST describe the same active Phase B order.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Config merge order must be deterministic, explainable, and safe.

The merge pipeline must make it clear:

- which source is weaker;
- which source is stronger;
- when directives are normalized;
- when directives are applied;
- when env overlays are generated;
- when semantic validation runs;
- which behaviors are active Phase B behavior;
- which behaviors are reserved for later epics.

## Canonical authority

`ConfigKernel` owns orchestration of the active Phase B pipeline.

`ConfigMerger` owns only the deterministic two-node merge operation.

Config loaders own loading and per-file normalization for their source category.

The canonical runtime implementation paths are:

```text
framework/packages/core/kernel/src/Config/ConfigKernel.php
framework/packages/core/kernel/src/Config/ConfigMerger.php
framework/packages/core/kernel/src/Config/DirectiveProcessor.php
framework/packages/core/kernel/src/Config/Loaders/PackageDefaultsConfigLoader.php
framework/packages/core/kernel/src/Config/Loaders/SkeletonConfigLoader.php
framework/packages/core/kernel/src/Config/Loaders/EnvironmentOverlayLoader.php
framework/packages/core/kernel/src/Config/ConfigRulesLoader.php
framework/packages/core/kernel/src/Config/ConfigValidator.php
```

Config root ownership is defined by:

```text
docs/ssot/config-roots.md
```

Config/env policy is defined by:

```text
docs/ssot/config-and-env.md
```

Directive examples are defined by:

```text
docs/ssot/config-directives.md
```

## Source-of-truth boundaries

This document owns:

- the active Phase B merge-order narrative;
- aggregate `roots.php` versus split `<root>.php` behavior;
- skeleton shared versus skeleton environment versus app shared versus app environment precedence;
- directive timing relative to merge;
- env overlay timing relative to file config;
- validation timing relative to final merge;
- Phase B safe provenance handoff points needed by downstream artifact fingerprinting:
  - `envOverlayMappings`;
  - `configSourceFiles`;
- reserved/future status for CLI/runtime overrides.

This document does not own:

- config root ownership;
- config rules DSL;
- config validation implementation;
- directive enum implementation;
- directive type validation implementation;
- env value coercion implementation;
- source discovery implementation;
- artifact fingerprint calculation;
- generated artifact schemas;
- config explain trace format;
- observability naming.

## Core invariant

Lower rank is weaker.

Higher rank is stronger.

A higher-rank source overrides or mutates a lower-rank source according to the normalized config payload and merge semantics.

The active Phase B merge pipeline MUST fold sources from weakest to strongest.

`ConfigKernel` MUST fold normalized merge entries through `ConfigMerger`.

`ConfigKernel` MUST NOT merge config values manually with ad hoc array logic.

`ConfigMerger` MUST NOT invent source precedence.

Loaders MUST NOT invent source precedence.

`ConfigValidator` MUST NOT invent source precedence.

`ConfigExplainer` MUST NOT invent source precedence.

## Active Phase B rank order

The active Phase B order is:

| Rank | Source category                 | Source path / mechanism                                             |
|-----:|---------------------------------|---------------------------------------------------------------------|
|   10 | Package defaults                | package `config/<root>.php`                                         |
|  100 | Skeleton shared aggregate       | `skeleton/config/roots.php`                                         |
|  101 | Skeleton shared split root      | `skeleton/config/<root>.php`                                        |
|  200 | Skeleton environment aggregate  | `skeleton/config/environments/<appEnv>/roots.php`                   |
|  201 | Skeleton environment split root | `skeleton/config/environments/<appEnv>/<root>.php`                  |
|  300 | App shared aggregate            | `skeleton/apps/<appTarget>/config/roots.php`                        |
|  301 | App shared split root           | `skeleton/apps/<appTarget>/config/<root>.php`                       |
|  400 | App environment aggregate       | `skeleton/apps/<appTarget>/config/environments/<appEnv>/roots.php`  |
|  401 | App environment split root      | `skeleton/apps/<appTarget>/config/environments/<appEnv>/<root>.php` |
|  500 | Env overlays                    | ruleset-derived or explicit env overlay mappings                    |

The rank numbers are intentionally spaced to leave room for future explicitly introduced layers.

A future layer MUST NOT become active by implication.

A future layer becomes active only when its owner epic updates this SSoT, the precedence matrix, runtime code, and tests.

## Phase B pipeline narrative

The active Phase B pipeline is:

```text
1. Load package-owned config rulesets.
2. Load optional explicit rulesets supplied by a future user/module mechanism.
3. Build the effective ruleset list.
4. Load package defaults.
5. Load skeleton/app config files and safe config source-file metadata.
6. Build env overlays from rulesets / explicit mappings and the immutable env snapshot.
7. Preserve the exact resolved env overlay mappings.
8. Build deterministic merge entries.
9. Fold merge entries through ConfigMerger from weakest to strongest.
10. Build effective per-path source traces while folding merge entries.
11. Run semantic validation after final global config is built.
12. Build config explain output only when requested by the caller.
13. Return final config plus safe Phase B provenance metadata.
```

The active Phase B invariant is:

```text
directives before merge
env overlays after file config
validation after final merge
CLI/runtime overrides reserved/future
```

Rulesets are validation and env-overlay metadata.

Rulesets are not config value sources.

Rulesets MUST NOT participate in config value precedence.

## Package defaults

Package defaults are the weakest active Phase B config value source.

Package defaults are loaded only from package-owned files:

```text
config/<root>.php
```

Package defaults MUST NOT use:

```text
config/roots.php
```

Package default files MUST return only the subtree for `<root>`.

Example valid package default file:

```text
framework/packages/core/kernel/config/kernel.php
```

Valid shape:

```php
return [
    'boot' => [
        'default_env' => 'prod',
    ],
];
```

Invalid shape:

```php
return [
    'kernel' => [
        'boot' => [
            'default_env' => 'prod',
        ],
    ],
];
```

Package default source candidates are supplied by the Kernel config-location source builder.

Package default loading MUST NOT scan arbitrary package directories.

## Skeleton and app file layers

Skeleton/app config has four active file layers:

```text
skeleton shared
skeleton environment
app shared
app environment
```

Their relative strength is:

```text
skeleton shared
  < skeleton environment
  < app shared
  < app environment
```

The full source category order is:

```text
package defaults
  < skeleton shared
  < skeleton environment
  < app shared
  < app environment
  < env overlays
```

## Aggregate `roots.php`

`roots.php` is the aggregate root-map file for one config layer.

A `roots.php` file returns a global root map.

Example:

```php
return [
    'kernel' => [
        'boot' => [
            'default_env' => 'prod',
        ],
    ],
    'http' => [
        'middleware' => [
            'app' => [
                'AuthMiddleware',
            ],
        ],
    ],
];
```

Users MAY place user-owned/custom roots in `roots.php`.

A custom root loaded from `roots.php` is merged as config data.

If the custom root has no loaded ruleset, it is not semantically validated and is reported as:

```text
user_owned
unvalidated
```

## Split `<root>.php`

`<root>.php` is the split root-subtree file for one config root.

A split root file returns only the subtree for that root.

Example file:

```text
skeleton/config/kernel.php
```

Valid shape:

```php
return [
    'boot' => [
        'default_env' => 'dev',
    ],
];
```

Invalid shape:

```php
return [
    'kernel' => [
        'boot' => [
            'default_env' => 'dev',
        ],
    ],
];
```

Users MAY split custom roots into dedicated `<root>.php` files when the root name is part of the deterministic split-root candidate list supplied to `SkeletonConfigLoader`.

`SkeletonConfigLoader` MUST NOT scan arbitrary config directories to discover split root files.

## Aggregate versus split root precedence

At the same layer, aggregate `roots.php` is weaker than split `<root>.php`.

Example:

```text
skeleton/config/roots.php          rank 100
skeleton/config/kernel.php         rank 101
```

The same same-layer rule applies to:

```text
skeleton/config/environments/<appEnv>/roots.php
skeleton/config/environments/<appEnv>/<root>.php

skeleton/apps/<appTarget>/config/roots.php
skeleton/apps/<appTarget>/config/<root>.php

skeleton/apps/<appTarget>/config/environments/<appEnv>/roots.php
skeleton/apps/<appTarget>/config/environments/<appEnv>/<root>.php
```

## Aggregate versus split example

Aggregate file:

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

Split root file at the same layer:

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

The split root file overrides only the paths it touches.

Untouched aggregate values survive.

## Environment-specific precedence

Environment-specific skeleton config is stronger than shared skeleton config.

Example:

```text
skeleton/config/roots.php                              rank 100
skeleton/config/kernel.php                             rank 101
skeleton/config/environments/local/roots.php           rank 200
skeleton/config/environments/local/kernel.php          rank 201
```

For the same config path, the environment-specific layer wins over the shared layer.

## App-specific precedence

App shared config is stronger than skeleton environment config.

App environment config is stronger than app shared config.

Example:

```text
skeleton/config/environments/local/kernel.php                         rank 201
skeleton/apps/admin/config/kernel.php                                 rank 301
skeleton/apps/admin/config/environments/local/kernel.php              rank 401
```

For the same config path:

```text
skeleton environment < app shared < app environment
```

## Directives before merge

Config directives are processed per file before merge.

Per-file directive processing includes directive namespace and directive payload shape normalization.

The canonical directive keys are:

```text
@append
@prepend
@remove
@merge
@replace
```

Directive normalization happens before a file payload becomes a merge entry.

Directive application happens during merge, when the previous/base value is known.

Directive payload type validation and merge-time base compatibility are separate checks.

A directive payload can be valid by itself and still fail during merge if the existing lower-rank/base value has an incompatible container kind.

Missing base values are accepted and interpreted as empty containers by directive context.

Existing base values with the wrong container kind fail deterministically with:

```text
CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH
```

Base compatibility failures use stable reason tokens:

```text
list-directive-base-must-be-list
merge-directive-base-must-be-map
```

This means:

```text
file payload
→ DirectiveProcessor
→ normalized merge entry
→ ConfigKernel folds entry through ConfigMerger
→ ConfigMerger applies normalized directives
```

`DirectiveProcessor` MUST NOT apply directives to previous/base config values.

`ConfigMerger` applies normalized directives because it receives both:

```text
base
patch
```

## Directive timing example

Lower-rank effective config:

```php
[
    'http' => [
        'middleware' => [
            'app' => [
                'AuthMiddleware',
            ],
        ],
    ],
]
```

Higher-rank file payload:

```php
return [
    'middleware' => [
        'app' => [
            '@append' => [
                'ControllerMiddleware',
            ],
        ],
    ],
];
```

The file is first normalized as a per-file payload.

Later, during merge, the normalized directive is applied against the previous list.

Effective result:

```php
[
    'http' => [
        'middleware' => [
            'app' => [
                'AuthMiddleware',
                'ControllerMiddleware',
            ],
        ],
    ],
]
```

## Env overlays after file config

Env overlays are generated after config rulesets are loaded and after file config sources are available for the Phase B pipeline.

Env overlays are stronger than all active file config layers.

Env overlay generation is allowlisted by:

- loaded declarative config rulesets;
- optional explicit env overlay mappings supplied by a future user/module mapping mechanism.

Unknown env vars MUST NOT create config keys automatically.

User-owned/custom roots without rulesets MUST NOT receive env overlays automatically.

Env overlays win only where all of the following are true:

1. a ruleset-derived or explicit env overlay mapping exists;
2. the env value exists in the immutable `EnvRepositoryInterface` snapshot;
3. the env value can be coerced according to the declared scalar rule type.

Env overlays MUST NOT read:

```text
$_ENV
$_SERVER
getenv()
```

Env overlays consume only the immutable environment snapshot produced by Bootstrap Phase A.

## Env overlay projection example

Config path:

```text
kernel.boot.default_env
```

Canonical env name:

```text
KERNEL_BOOT_DEFAULT_ENV
```

Projection rules:

```text
ASCII letters are uppercased
. maps to _
- maps to _
_ is preserved
ASCII digits are preserved
```

## Env overlay precedence example

File config result before env overlay:

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

If no env overlay mapping exists for `kernel.boot.default_env`, the env var MUST NOT affect config.

## Safe Phase B provenance for artifact fingerprinting

`ConfigKernel::compile(...)` returns the final merged config payload and safe Phase B provenance metadata needed by downstream artifact/fingerprint stages.

The public `config` result is the final merged global config payload.

The following returned fields are safe provenance metadata:

```text
envOverlayMappings
configSourceFiles
owners
sources
explain
```

`envOverlayMappings` MUST be the exact resolved mapping list produced by `EnvironmentOverlayLoader` from loaded rulesets plus explicit mappings.

`configSourceFiles` MUST be the safe source-file metadata produced by `SkeletonConfigLoader` for skeleton/app config candidates.

`configSourceFiles` metadata MAY include only:

```text
layer
kind
root
sourceId
path
exists
readable
hash
len
```

`hash`, when present, MUST be `sha256` over LF-normalized file bytes.

`len`, when present, MUST be the byte length of the LF-normalized file bytes used for hashing.

Missing expected skeleton/app config candidates MUST be represented as:

```text
exists=false
readable=false
```

Unreadable existing skeleton/app config candidates MUST either fail according to the loader policy or be represented as:

```text
exists=true
readable=false
```

Safe provenance metadata MUST NOT include:

- raw config values;
- raw env values;
- dotenv values;
- raw source file contents;
- compiled payload bytes;
- absolute filesystem paths;
- timestamps;
- mtimes;
- permissions;
- filesystem owners;
- hostnames;
- user names;
- process ids;
- random bytes;
- previous throwable messages.

`ConfigKernel` MUST NOT calculate artifact fingerprints.

`ConfigKernel` MUST NOT hash compiled artifact envelopes.

Downstream artifact/fingerprint stages MAY consume this safe provenance metadata, but they own fingerprint input construction and artifact fingerprint calculation.

## Validation after final merge

Semantic validation runs only after the final merged global config is built.

Validation MUST NOT run against individual file payloads as the final semantic config validation step.

Validation MUST NOT run before env overlays are merged.

Only roots with loaded rulesets are semantically validated.

Framework-owned roots with loaded package-owned rulesets are validated strictly according to owner package rules.

Module-owned roots with loaded module-owned rulesets are validated strictly when module-owned rules exist.

User-owned/custom roots without rulesets are accepted as config data and marked as:

```text
user_owned
unvalidated
```

Validation diagnostics MUST be deterministic and safe.

Validation diagnostics MUST NOT expose:

- raw config values;
- raw env values;
- secrets;
- tokens;
- DSNs;
- cookies;
- headers;
- raw SQL;
- payloads;
- absolute local paths;
- stack traces;
- previous throwable messages.

## Explain after final merge and validation

Config explain output is generated only when requested by the caller or entrypoint.

Explain generation happens after:

```text
final merge
semantic validation
validation subject calculation
effective source trace construction
```

Explain output MUST NOT control merge behavior.

Explain output MUST NOT be feature-disabled through config.

Explain is a baseline kernel capability, but whether it is produced is decided by the caller.

## Determinism rules

The active Phase B pipeline MUST be deterministic.

The same inputs MUST produce the same final config and explain trace, excluding observability durations.

Determinism requires:

- deterministic source candidate lists;
- deterministic source entry sorting;
- deterministic map key ordering;
- list order preservation unless a directive explicitly changes the list;
- deterministic env overlay projection;
- deterministic validation subject ordering;
- deterministic explain path ordering.

Returned artifacts and explain output MUST NOT include:

- wall-clock time;
- random ids;
- object ids;
- absolute filesystem paths;
- nondeterministic exception text.

## Reserved/future behavior

CLI/runtime overrides are reserved for a later epic.

CLI/runtime overrides are NOT active Phase 1 behavior.

Runtime overrides MUST NOT be silently added to the active Phase B pipeline.

A future CLI/runtime override epic MUST update:

```text
docs/ssot/config-merge-order.md
docs/ssot/config-precedence-matrix.md
runtime implementation
integration tests
```

before those overrides become active.

Map/list env overlay syntax is reserved for a later typed env syntax epic.

Preset/mode overlays are reserved unless explicitly activated by a future owner epic.

Runtime feature flags for enabling or disabling `ConfigKernel` or config explain are forbidden for this baseline.

## Invalid behavior examples

### Invalid: package aggregate defaults

Package defaults MUST NOT use:

```text
config/roots.php
```

Package defaults use only:

```text
config/<root>.php
```

### Invalid: split root file repeats root wrapper

Invalid `skeleton/config/kernel.php`:

```php
return [
    'kernel' => [
        'boot' => [
            'default_env' => 'dev',
        ],
    ],
];
```

Split root files return the subtree only.

### Invalid: env var creates unknown config path

Invalid behavior:

```text
SOME_RANDOM_ENV_VAR creates custom.some.random.env.var
```

Unknown env vars MUST NOT create config keys.

### Invalid: validation before env overlays

Invalid pipeline:

```text
file config
→ validation
→ env overlay
```

Validation MUST run only after the final merged global config is built.

### Invalid: CLI/runtime overrides active by convention

Invalid behavior:

```text
CLI --config kernel.boot.default_env=dev automatically becomes active Phase B source
```

CLI/runtime overrides are reserved/future and are not active Phase 1 behavior.

## Enforcement rails

Expected enforcement belongs to runtime tests, integration tests, and SSoT consistency gates.

Expected tests include:

```text
ConfigPrecedenceMatrixTest.php
ConfigAggregateAndSplitFilesMergeOrderTest.php
ConfigEnvironmentSpecificOverlaysPrecedenceTest.php
ConfigExplainShowsPackageDefaultWhenNoSkeletonOverridesTest.php
UserOwnedConfigRootsAreMergedButNotFrameworkValidatedTest.php
```

Tests SHOULD verify:

- package defaults are weaker than skeleton/app/env overlays;
- aggregate `roots.php` is weaker than split `<root>.php` at the same layer;
- skeleton shared is weaker than skeleton environment;
- skeleton environment is weaker than app shared;
- app shared is weaker than app environment;
- app environment is weaker than env overlays;
- directives are processed before merge and applied during merge;
- env overlays are generated only from known mappings;
- `ConfigKernel::compile(...)` returns the exact resolved `envOverlayMappings`;
- `ConfigKernel::compile(...)` returns safe `configSourceFiles`;
- `configSourceFiles` include LF-normalized `sha256` hash and length metadata when readable;
- `configSourceFiles` do not expose absolute paths or raw file contents;
- validation runs after final merge;
- CLI/runtime overrides are not active.

## Non-goals

This document does not define:

- the config rules DSL;
- the config validator implementation;
- config root ownership rows;
- directive type validation details;
- env value coercion details;
- package/module discovery implementation;
- bootstrap Phase A config;
- service provider wiring;
- generated artifact schemas;
- CLI command behavior;
- future runtime override semantics.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Config and env SSoT](./config-and-env.md)
- [Config Roots Registry](./config-roots.md)
- [Config Directives Examples](./config-directives.md)
- [Config Precedence Matrix](./config-precedence-matrix.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
