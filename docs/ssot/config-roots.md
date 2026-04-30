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

# Config Roots Registry (SSoT)

This document is the canonical registry for reserved configuration roots.

## Goal

A single SSoT defines reserved config roots, ownership, defaults-file authority, rules-file authority, and declarative config ruleset invariants so configuration stays predictable across packages.

## Invariants (MUST)

- `config/<name>.php` **MUST** return the subtree for that root and **MUST NOT** repeat the root wrapper.
- Defaults for a config root **MUST** live in the owning package only.
- `config/rules.php` for a config root **MUST** be owned by the owning package only.
- Package `config/rules.php` files **MUST** return plain declarative ruleset arrays.
- Package `config/rules.php` files **MUST NOT** return callables, closures, objects, service instances, executable validators, or runtime wiring objects.
- Package-owned rules files define validation rules as data only.
- Runtime validation logic is kernel-owned and **MUST** be implemented by `ConfigKernel` / `ConfigValidator` using contracts such as `ConfigValidatorInterface`.
- Configuration ownership is defined exclusively by this config roots registry.
- Config roots and DTO policy are orthogonal mechanisms.
- PHP attributes **MUST NOT** replace package-owned config defaults or package-owned config rules.
- Each reserved config root **MUST** have exactly one owner `package_id`.
- Shared ownership of config defaults or rules is forbidden.
- This registry **MAY** be extended only by later owner epics via direct modification of this file.
- Later additions **MUST** update the canonical registry rows directly and **MUST NOT** leave parallel “future reserved identifier” notes in roadmap epics.

## Cemented Example (MUST)

### File: `config/foundation.php`

The defaults file **MUST** return the subtree without repeating the root key.

Valid shape:

```php
return [
    'container' => [
        // ...
    ],
    // ...
];
```

Invalid shape:

```php
return [
    'foundation' => [
        'container' => [
            // ...
        ],
    ],
];
```

Runtime code reads from the global config under the root key. Example:

```text
foundation.container.*
```

## Declarative Ruleset Files (MUST)

Package `config/rules.php` files define validation rules as declarative data.

A valid rules file returns a plain array:

```php
return [
    'container' => [
        'type' => 'map',
        'required' => false,
    ],
];
```

An invalid rules file returns executable behavior:

```php
return static function (array $config): void {
    // invalid
};
```

Invalid rule values include:

- callables
- closures
- objects
- service instances
- container references
- executable validators
- resources
- filesystem handles
- runtime wiring objects

Ruleset files are input data for `ConfigKernel` / `ConfigValidator`; they are consumed through contracts and are not validators themselves.

## Ownership Model (MUST)

For every reserved config root, the owning package exclusively owns:

- the canonical registry row in this document
- the defaults file for that root
- the rules file for that root
- the semantics and invariants for values stored under that root

Non-owner packages:

- **MAY** read existing config roots according to published owner semantics
- **MUST NOT** define competing defaults for an existing reserved root
- **MUST NOT** define competing `config/rules.php` authority for an existing reserved root
- **MUST NOT** redefine ownership of an existing reserved root

## Reserved Config Roots (MUST)

| root              | owner package_id           | defaults file                                                            | rules file                                                     | notes                             |
|-------------------|----------------------------|--------------------------------------------------------------------------|----------------------------------------------------------------|-----------------------------------|
| `cli`             | `platform/cli`             | `framework/packages/platform/cli/config/cli.php`                         | `framework/packages/platform/cli/config/rules.php`             | Phase 0 locked root from 0.130.0. |
| `foundation`      | `core/foundation`          | `framework/packages/core/foundation/config/foundation.php`               | `framework/packages/core/foundation/config/rules.php`          | Runtime core root.                |
| `kernel`          | `core/kernel`              | `framework/packages/core/kernel/config/kernel.php`                       | `framework/packages/core/kernel/config/rules.php`              | Runtime kernel root.              |
| `http`            | `platform/http`            | `framework/packages/platform/http/config/http.php`                       | `framework/packages/platform/http/config/rules.php`            | Platform HTTP root.               |
| `logging`         | `platform/logging`         | `framework/packages/platform/logging/config/logging.php`                 | `framework/packages/platform/logging/config/rules.php`         | Platform logging root.            |
| `metrics`         | `platform/metrics`         | `framework/packages/platform/metrics/config/metrics.php`                 | `framework/packages/platform/metrics/config/rules.php`         | Platform metrics root.            |
| `tracing`         | `platform/tracing`         | `framework/packages/platform/tracing/config/tracing.php`                 | `framework/packages/platform/tracing/config/rules.php`         | Platform tracing root.            |
| `problem_details` | `platform/problem-details` | `framework/packages/platform/problem-details/config/problem_details.php` | `framework/packages/platform/problem-details/config/rules.php` | Platform problem-details root.    |

## Initial Rows Introduced by This Epic (MUST)

This epic introduces the following initial canonical registry rows:

- `cli`
- `foundation`
- `kernel`
- `http`
- `logging`
- `metrics`
- `tracing`
- `problem_details`

## Rules for Later Extensions (MUST)

Introducing a new reserved config root requires direct modification of this file by the future owner epic.

A later owner epic **MUST**:

1. add or update the canonical registry row in `docs/ssot/config-roots.md`
2. define the owner package defaults file for that root
3. define the owner package rules file for that root

Parallel placeholder notes are not a substitute for updating the canonical registry.

## Non-goals / Clarifications (MUST)

- This registry governs reserved config roots, ownership, defaults-file authority, rules-file authority, and declarative rules-file authority.
- This registry does not define config merge implementation.
- This registry does not define config validation implementation.
- This registry does not define config artifact schema.
- This registry does not turn DTO attributes into configuration ownership.
- PHP attributes and configuration roots are orthogonal and **MUST NOT** be conflated.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Config and env SSoT](./config-and-env.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
