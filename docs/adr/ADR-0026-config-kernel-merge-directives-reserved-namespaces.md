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

# ADR-0026: Config Kernel Merge, Directives, and Reserved Namespaces

## Status

Accepted.

## Context

Coretsia config Phase B needs a deterministic and explainable pipeline for combining package defaults, skeleton config, application config, and environment overlays.

The framework must support:

- package-owned default config;
- skeleton-level shared config;
- skeleton-level environment config;
- application-level shared config;
- application-level environment config;
- env overlays generated from an immutable env snapshot;
- user-owned/custom config roots;
- per-file config directives;
- reserved framework namespaces;
- safe diagnostics and explain output.

The config pipeline must avoid implicit source precedence, directory scanning, raw env reads, unsafe diagnostics, and hidden merge behavior.

The following policies already exist and constrain the decision:

```text
docs/ssot/config-and-env.md
docs/ssot/config-roots.md
```

The implementation is split across dedicated runtime components:

```text
framework/packages/core/kernel/src/Config/ConfigKernel.php
framework/packages/core/kernel/src/Config/DirectiveProcessor.php
framework/packages/core/kernel/src/Config/ConfigMerger.php
framework/packages/core/kernel/src/Config/Validation/ConfigNamespaceGuard.php
```

The central design tension is that config directives are syntactically present in files, but their effect depends on the previous/base value. Therefore directives cannot be fully applied while reading an individual file. They must first be normalized per file and then applied during merge, when the base value is known.

Another design tension is namespace safety. Coretsia needs reserved top-level namespaces and reserved directive keys, while still allowing user-owned/custom config roots. Unknown user roots must not be rejected just because the framework does not own them.

## Decision

Coretsia accepts a two-stage Phase B config model:

```text
per-file normalization
→ deterministic Phase B merge
```

`ConfigKernel` is the orchestration boundary for Phase B.

`DirectiveProcessor` owns per-file directive namespace and type processing.

`ConfigMerger` owns deterministic merge semantics and applies normalized directives at merge time.

`ConfigNamespaceGuard` owns reserved namespace protection before semantic config validation.

`ConfigValidator` validates only the final merged global config and only for roots with loaded rulesets.

`ConfigExplainer` receives source traces after merge and produces safe explain metadata without raw values.

## Detailed decision

### 1. ConfigKernel is orchestration-only

`ConfigKernel` MUST orchestrate Phase B but MUST NOT become a loader, validator, merger, directive processor, or explainer.

It coordinates:

```text
ConfigRulesLoader
PackageDefaultsConfigLoader
SkeletonConfigLoader
EnvironmentOverlayLoader
ConfigMerger
ConfigValidator
ConfigExplainer
```

`ConfigKernel` MUST receive explicit source candidates and deterministic path lists from the Kernel config-location source builder.

`ConfigKernel` MUST NOT:

- infer package filesystem paths from `ModulePlanEntry`;
- scan package directories;
- scan skeleton/app config directories;
- read `$_ENV`;
- read `$_SERVER`;
- call `getenv()`;
- read `skeleton/config/app.php`;
- invent source precedence;
- mutate `ConfigNamespaceGuard`, `DirectiveProcessor`, or `ConfigMerger`;
- reconfigure `ConfigNamespaceGuard` from the final merged config during the same pipeline run.

### 2. Reserved namespace guard runs before semantic validation

`ConfigNamespaceGuard` MUST run before semantic config validation.

It protects:

```text
coretsia
_internal
@*
```

The forbidden top-level root list is provided through Kernel config wiring:

```text
kernel.config.forbidden_top_level_roots
```

The guard MUST NOT reject user-owned/custom top-level roots solely because they are not framework-owned.

Unknown/custom roots are allowed unless they violate global safety rules.

Unknown `@*` keys are reserved namespace violations.

Directive mixed-level violations are rejected before directive type mismatch validation.

### 3. Directives are normalized per file

Config directives are processed per file before merge.

The canonical directive keys are:

```text
@append
@prepend
@remove
@merge
@replace
```

Directive processing MUST:

- reserve the `@*` namespace;
- reject unsupported `@*` keys;
- enforce that a directive level contains exactly one directive key;
- reject mixed directive/config-key levels;
- validate directive payload shape;
- normalize the directive node into a canonical one-key map.

`DirectiveProcessor` MUST NOT apply directives to previous/base config values.

It does not know the previous/base value.

### 4. Directives are applied by ConfigMerger during merge

`ConfigMerger` applies normalized directives only during merge, when both nodes are available:

```text
base
patch
```

This enables deterministic behavior for:

```text
@append
@prepend
@remove
@merge
@replace
```

`ConfigMerger` MUST NOT discover config sources or source precedence.

`ConfigMerger` receives only two nodes and returns the merged node.

Directive type mismatch is split into two deterministic categories:

1. directive payload type mismatch, detected during per-file directive processing;
2. merge-time base type mismatch, detected by `ConfigMerger` when the previous/base value is known.

`DirectiveProcessor` MUST validate directive payload shape.

`ConfigMerger` MUST validate existing base compatibility for normalized directives.

Missing base values are accepted and interpreted by directive context as empty list/map bases.

Existing base values with incompatible container kinds MUST fail deterministically with:

```text
CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH
```

Base mismatch reason tokens are:

```text
list-directive-base-must-be-list
merge-directive-base-must-be-map
```

### 5. Source precedence is explicit and owned by ConfigKernel

Lower rank is weaker.

Higher rank is stronger.

The active Phase B rank order is documented by:

```text
docs/ssot/config-merge-order.md
docs/ssot/config-precedence-matrix.md
```

The current active order is:

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

`ConfigKernel` MUST fold normalized merge entries through `ConfigMerger` in this active order.

### 6. Package defaults use only config/<root>.php

Package defaults MUST be loaded only from package-owned files:

```text
config/<root>.php
```

Package defaults MUST NOT use:

```text
config/roots.php
```

Package default files return only the subtree for `<root>`.

Package defaults are weaker than skeleton, app, and env overlay config sources.

### 7. roots.php is the aggregate root-map file for skeleton/app layers

For skeleton and app config layers, `roots.php` is the aggregate root-map file.

Examples:

```text
skeleton/config/roots.php
skeleton/config/environments/<appEnv>/roots.php
skeleton/apps/<appTarget>/config/roots.php
skeleton/apps/<appTarget>/config/environments/<appEnv>/roots.php
```

A `roots.php` file returns a global root map.

### 8. <root>.php is the split root-subtree file

A split root file returns only the subtree for that root.

Examples:

```text
skeleton/config/kernel.php
skeleton/config/environments/<appEnv>/kernel.php
skeleton/apps/<appTarget>/config/kernel.php
skeleton/apps/<appTarget>/config/environments/<appEnv>/kernel.php
```

At the same layer, split root files are stronger than aggregate `roots.php` files.

### 9. Env overlays are generated after file config sources are prepared

Env overlays are generated from:

- the immutable `EnvRepositoryInterface` snapshot produced by Bootstrap Phase A;
- loaded declarative config rulesets;
- optional explicit env overlay mappings.

Env overlays MUST NOT read process/global env directly.

Unknown env vars MUST NOT create config keys.

User-owned/custom roots without rulesets MUST NOT receive env overlays automatically.

Env overlays are stronger than all file config sources.

### 10. Validation runs only after final merge

Semantic config validation runs only after the final merged global config is built.

Validation is ruleset-driven.

Only roots with loaded rulesets are validated.

Framework-owned roots with loaded rulesets are validated strictly.

Module-owned roots with loaded rulesets are validated strictly.

User-owned/custom roots without rulesets are not rejected solely because they have no ruleset.

They are marked as:

```text
user_owned
unvalidated
```

### 11. Explain is a baseline capability, not a feature flag

Config explain is a baseline kernel facility.

Explain output is produced only when requested by the caller/entrypoint.

Explain MUST NOT be feature-disabled through runtime config.

Explain output MUST NOT affect merge, validation, or env overlay behavior.

Explain output MUST NOT expose raw config values or raw env values.

## Consequences

### Positive consequences

This decision gives Coretsia:

- deterministic config merge order;
- explicit source precedence;
- clear separation between file normalization and merge-time behavior;
- precise separation between directive payload validation and merge-time base compatibility;
- safe reserved namespace enforcement;
- support for custom/user-owned config roots;
- explainable effective source attribution;
- env overlays that cannot create arbitrary config keys;
- validation that does not reject custom roots without rules;
- safe diagnostics without raw values.

This keeps per-file directive normalization independent from source precedence while preventing silent data loss when a directive is applied to an incompatible existing value.

### Negative consequences

This decision increases the number of moving parts:

```text
ConfigKernel
DirectiveProcessor
ConfigMerger
ConfigNamespaceGuard
ConfigRulesLoader
ConfigValidator
ConfigExplainer
loaders
```

The design requires strong integration tests because correctness emerges from the connection between the components.

The design also requires explicit config-location source builders. Runtime code cannot rely on directory scanning as a shortcut.

### Neutral consequences

Directives are not a general-purpose scripting system.

They are limited config merge operators.

Future directive expansion requires updating contracts, runtime validation, merge behavior, examples, and tests.

## Alternatives considered

### Alternative 1: Apply directives during file loading

Rejected.

A file loader does not know the previous/base value. Applying directives during loading would either require hidden state or incomplete semantics.

This would also blur the boundary between loading and merging.

### Alternative 2: Let ConfigMerger parse arbitrary @* keys directly

Rejected.

The reserved directive namespace must be checked before semantic validation and before merge-time behavior.

Allowing `ConfigMerger` to discover arbitrary `@*` shapes would weaken diagnostic precedence and duplicate directive classification logic.

### Alternative 3: Reject all unknown config roots

Rejected.

Coretsia must allow user-owned/custom roots.

A root without a framework ruleset is not automatically invalid. It can still be loaded, merged, explained, fingerprinted, and compiled by later stages.

### Alternative 4: Use config/all.php as the aggregate file

Rejected.

The chosen aggregate file name is:

```text
roots.php
```

This better communicates that the file returns a global root map, not an arbitrary complete config blob.

### Alternative 5: Treat env vars as automatic config keys

Rejected.

Automatic env-to-config expansion would make config shape implicit and unsafe.

Env overlays must be allowlisted by rulesets or explicit mappings.

### Alternative 6: Put observability in ConfigMerger or loaders

Rejected.

Config merge and explain observability boundaries belong to `ConfigKernel`.

Loaders, `DirectiveProcessor`, `ConfigMerger`, `ConfigValidator`, and `ConfigExplainer` must remain focused and should not emit config merge/explain lifecycle metrics/spans.

## Invariants

The following invariants are accepted by this ADR.

### Namespace invariants

- `coretsia` is reserved as a forbidden top-level root.
- `_internal` is reserved as a forbidden top-level root.
- `@*` is reserved for directive processing.
- Unknown `@*` keys are reserved namespace violations.
- User-owned/custom roots are allowed unless they violate global safety rules.

### Directive invariants

- Directives are normalized per file.
- Directives are applied during merge.
- Directive payload type mismatch is detected during per-file directive processing.
- Merge-time base type mismatch is detected during merge when the previous/base value is known.
- Missing base values are accepted and interpreted by directive context as empty list/map bases.
- Existing base values with incompatible container kinds fail with `CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH`.
- Directive levels contain exactly one directive key.
- Directive levels do not mix directive keys with normal config keys.
- Directive payload and base compatibility diagnostics must not expose raw values.

### Merge invariants

- Lower rank is weaker.
- Higher rank is stronger.
- Map merge preserves untouched weaker keys.
- Scalar replacement replaces the lower-rank value.
- List replacement preserves list order unless a directive changes it.
- Directives mutate or replace according to normalized directive semantics.

### Validation invariants

- Validation runs after final merge.
- Validation only applies to roots with loaded rulesets.
- User-owned/custom roots without rulesets are not rejected only because they have no rules.
- Unvalidated custom roots are reported as `user_owned` and `unvalidated`.

### Env overlay invariants

- Env overlays use only immutable `EnvRepositoryInterface` snapshots.
- Env overlays do not read global/process env directly.
- Unknown env vars do not create config keys.
- Env values are coerced according to declarative rules.

### Explain invariants

- Explain is caller-controlled.
- Explain is not a runtime feature flag.
- Explain contains safe metadata only.
- Explain does not contain raw config or env values.

## Security and diagnostics

Diagnostics MUST NOT expose:

- raw config values;
- raw env values;
- dotenv values;
- passwords;
- tokens;
- DSNs;
- cookies;
- headers;
- raw SQL;
- request payloads;
- stack traces;
- previous throwable messages;
- absolute local paths.

Diagnostics MAY expose:

- stable reason tokens;
- safe config dot paths;
- directive names;
- source type;
- source id;
- source precedence/order;
- normalized relative source paths;
- package/module/root ownership metadata.

## Enforcement

Expected enforcement belongs to runtime tests, integration tests, and SSoT consistency gates.

Coverage SHOULD include:

- reserved top-level roots;
- unknown/custom roots;
- unknown `@*` keys;
- mixed directive level violations;
- directive payload type mismatch violations;
- merge-time directive base type mismatch violations;
- `@append`;
- `@prepend`;
- `@remove`;
- `@merge`;
- `@replace`;
- package defaults weaker than skeleton/app/env sources;
- aggregate `roots.php` weaker than same-layer split `<root>.php`;
- skeleton shared weaker than skeleton environment;
- skeleton environment weaker than app shared;
- app shared weaker than app environment;
- env overlays strongest when mapped and present;
- unknown env vars ignored;
- validation after final merge;
- explain output safety;
- no raw config/env values in diagnostics.

## Compatibility

This ADR is compatible with:

```text
docs/ssot/config-and-env.md
docs/ssot/config-roots.md
docs/ssot/config-directives.md
docs/ssot/config-merge-order.md
docs/ssot/config-precedence-matrix.md
docs/ssot/observability.md
```

This ADR requires runtime consistency with:

```text
framework/packages/core/kernel/src/Config/ConfigKernel.php
framework/packages/core/kernel/src/Config/DirectiveProcessor.php
framework/packages/core/kernel/src/Config/ConfigMerger.php
framework/packages/core/kernel/src/Config/Validation/ConfigNamespaceGuard.php
framework/packages/core/kernel/src/Config/ConfigRulesLoader.php
framework/packages/core/kernel/src/Config/ConfigValidator.php
framework/packages/core/kernel/src/Config/Explain/ConfigExplainer.php
framework/packages/core/kernel/src/Config/Loaders/PackageDefaultsConfigLoader.php
framework/packages/core/kernel/src/Config/Loaders/SkeletonConfigLoader.php
framework/packages/core/kernel/src/Config/Loaders/EnvironmentOverlayLoader.php
```

## Follow-up work

Required follow-up:

- update `docs/adr/INDEX.md`;
- keep `docs/ssot/config-directives.md` aligned with directive examples;
- keep `docs/ssot/config-merge-order.md` aligned with active Phase B order;
- keep `docs/ssot/config-precedence-matrix.md` aligned with active rank matrix;
- add/maintain integration tests for merge order and explain output.

Future reserved work:

- CLI/runtime overrides;
- map/list env overlay syntax;
- preset/mode overlays;
- additional directives, if explicitly accepted by a later ADR and SSoT update.

## Cross-references

- [ADR Index](./INDEX.md)
- [Config and env SSoT](../ssot/config-and-env.md)
- [Config Roots Registry](../ssot/config-roots.md)
- [Config Directives Examples](../ssot/config-directives.md)
- [Config Merge Order](../ssot/config-merge-order.md)
- [Config Precedence Matrix](../ssot/config-precedence-matrix.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](../ssot/observability.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
