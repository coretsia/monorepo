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

# Tag Registry (SSoT)

This document is the canonical registry for reserved DI tags.

## Goal

A single SSoT defines reserved DI tags, ownership, and naming rules so discovery and wiring stay deterministic and conflict-free.

## Invariants (MUST)

- This registry **MUST** be the canonical source for reserved DI tag names.
- Every reserved tag **MUST** have exactly one owner `package_id`.
- Shared ownership is forbidden.
- Only the owner epic may introduce or modify a reserved tag entry.
- Registry rows **MAY** exist independently of whether the semantic owner package currently registers runtime services for that tag.
- Every framework-reserved DI tag identifier MUST be declared in `Coretsia\Foundation\Tag\ReservedTags`.
- Framework packages MUST NOT define additional code-level registries for framework-reserved DI tag identifiers.
- `ReservedTags` owns tag identifier strings only.
- Runtime semantics, metadata schema, discovery, ordering, dispatch, validation, and consumer behavior remain owned by the semantic owner package declared in this SSoT.
- Non-owner packages **MAY** use existing reserved tags, but **MUST NOT** redefine competing semantics or competing meta-schema for the same tag.
- Raw literal tag strings are allowed in docs, tests, and fixtures for readability, but **MUST NOT** be the preferred runtime-code pattern.

## Naming Rules (MUST)

- Tag names **MUST** use dot-separated tokens.
- Tag names **MUST** be lowercase.
- Digits and `_` are allowed inside a token.
- Whitespace is forbidden.
- Canonical regex:

```text
^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$
```

## Ownership Model (MUST)

For every reserved tag, this SSoT declares a semantic owner package.

`Coretsia\Foundation\Tag\ReservedTags` owns the tag identifier string.

The semantic owner package owns:

- metadata schema, if any
- runtime consumer behavior
- discovery semantics
- dispatch/execution semantics, if any
- validation semantics

Non-owner packages:

- **MAY** use existing reserved tags
- **MUST NOT** define competing public tag APIs for the same tag
- **MUST NOT** define competing meta keys or competing semantics for the same tag

## Reserved Prefixes (MUST)

Resolution is single-choice. The first matching rule wins. Exact-match rules and more-specific rules take precedence over broader prefix rules.

| pattern             | owner package_id  | notes                                                              |
|---------------------|-------------------|--------------------------------------------------------------------|
| `kernel.reset`      | `core/foundation` | Reserved canonical default reset-discovery tag name.               |
| `kernel.stateful`   | `core/foundation` | Fixed enforcement marker.                                          |
| `kernel.hook.*`     | `core/kernel`     | Kernel lifecycle hook namespace.                                   |
| `kernel.*`          | `core/kernel`     | Applies to all other `kernel.*` tags not captured by earlier rows. |
| `http.middleware.*` | `platform/http`   | Canonical HTTP middleware slot namespace.                          |
| `cli.*`             | `platform/cli`    | Canonical CLI discovery namespace.                                 |
| `error.*`           | `platform/errors` | Canonical error discovery namespace.                               |
| `health.*`          | `platform/health` | Reserved now; owner package may be introduced later.               |

## Reserved Tag Registry (MUST)

Stability enum is single-choice:

- `stable`
- `experimental`
- `deprecated`

| tag                           | owner package_id  | purpose                                     | stability      | notes                                                   |
|-------------------------------|-------------------|---------------------------------------------|----------------|---------------------------------------------------------|
| `cli.command`                 | `platform/cli`    | CLI command discovery.                      | `stable`       | Canonical CLI discovery tag.                            |
| `error.mapper`                | `platform/errors` | Error mapper discovery.                     | `stable`       | Canonical discovery point for error mapping components. |
| `health.check`                | `platform/health` | Health check discovery.                     | `experimental` | Reserved baseline row; owner package is future-facing.  |
| `http.middleware.app`         | `platform/http`   | Main application middleware slot.           | `stable`       | Canonical Phase 0+ taxonomy.                            |
| `http.middleware.app_post`    | `platform/http`   | Post-application middleware slot.           | `stable`       | Canonical Phase 0+ taxonomy.                            |
| `http.middleware.app_pre`     | `platform/http`   | Pre-application middleware slot.            | `stable`       | Canonical Phase 0+ taxonomy.                            |
| `http.middleware.route`       | `platform/http`   | Main route-scoped middleware slot.          | `stable`       | Canonical Phase 0+ taxonomy.                            |
| `http.middleware.route_post`  | `platform/http`   | Post-route middleware slot.                 | `stable`       | Canonical Phase 0+ taxonomy.                            |
| `http.middleware.route_pre`   | `platform/http`   | Pre-route middleware slot.                  | `stable`       | Canonical Phase 0+ taxonomy.                            |
| `http.middleware.system`      | `platform/http`   | Main system middleware slot.                | `stable`       | Canonical Phase 0+ taxonomy.                            |
| `http.middleware.system_post` | `platform/http`   | Post-system middleware slot.                | `stable`       | Canonical Phase 0+ taxonomy.                            |
| `http.middleware.system_pre`  | `platform/http`   | Pre-system middleware slot.                 | `stable`       | Canonical Phase 0+ taxonomy.                            |
| `kernel.hook.after_uow`       | `core/kernel`     | Post-unit-of-work lifecycle hook discovery. | `stable`       | Canonical kernel lifecycle hook.                        |
| `kernel.hook.before_uow`      | `core/kernel`     | Pre-unit-of-work lifecycle hook discovery.  | `stable`       | Canonical kernel lifecycle hook.                        |
| `kernel.reset`                | `core/foundation` | Reset-capable service discovery.            | `stable`       | Reserved canonical default reset-discovery tag name.    |
| `kernel.stateful`             | `core/foundation` | Stateful-service enforcement marker.        | `stable`       | Fixed enforcement marker.                               |

## Forbidden Non-canonical Tags (MUST)

The following non-canonical tags are forbidden and **MUST NOT** be introduced anywhere:

- `http.middleware.user_before_routing`
- `http.middleware.user`
- `http.middleware.user_after_routing`

Rationale:

- Canonical Phase 0+ HTTP middleware taxonomy is single-choice: `system/app/route`.
- Any new epic mentioning `http.middleware.user*` **MUST** treat it only as forbidden non-canonical terminology.
- `http.middleware.user*` names **MUST NOT** appear as current tag names anywhere in contracts, SSoT, defaults, or gates.

## Runtime Usage Rule (MUST)

Runtime code in framework packages MUST use `Coretsia\Foundation\Tag\ReservedTags::*` for framework-reserved DI tags.

Raw literal tag strings are allowed in docs, tests, fixtures, and config defaults where readability or user-owned configuration is the subject.

Runtime package source MUST use `Coretsia\Foundation\Tag\ReservedTags::*` as the only code-level identifier registry for framework-reserved DI tags.

Runtime package source MUST NOT define additional code-level registries for framework-reserved DI tag identifiers.

Custom/user tags are outside this reserved registry unless explicitly promoted to framework-reserved status through this SSoT.

## Contributor Rule (MUST)

- A non-owner epic **MAY** say `N/A (uses existing <tag>)`.
- A non-owner epic **MUST NOT** introduce or freeze a competing meta-schema for that tag.
- If the owner meta-schema is not cemented yet, contributor epics **MUST** say `meta per owner schema` or equivalent wording instead of inventing alternative keys.

## Introducing a New Tag (MUST)

Introducing a new reserved tag requires both of the following in the owner epic:

1. add registry row in `docs/ssot/tags.md`
2. add public constant in `Coretsia\Foundation\Tag\ReservedTags`
3. document semantic owner behavior in the owner package or SSoT

A new tag is not complete unless both artifacts exist.

## Non-tag Metadata Rule (MUST)

- PHP attributes, including DTO marker attributes, are **NOT** DI tags.
- PHP attributes **MUST NOT** be registered in this tag registry.
- DI tags and PHP attributes are orthogonal mechanisms and **MUST NOT** be conflated.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
