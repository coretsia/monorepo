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
- Registry rows **MAY** exist before the owner package becomes tag-aware in runtime.
- The canonical owner public constant becomes mandatory in the owner epic that introduces the corresponding tag-aware mode or entrypoint.
- Every reserved tag **MUST** be declared as a public constant by the owner package, usually in `src/Provider/Tags.php`.
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

For every reserved tag, the owner package exclusively owns:

- the registry row in this document
- the canonical public constant
- the canonical meta-schema, if any
- the canonical consumer and discovery semantics

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

## Forbidden / Legacy Tags (MUST)

The following legacy tags are cemented as forbidden and **MUST NOT** be introduced anywhere:

- `http.middleware.user_before_routing`
- `http.middleware.user`
- `http.middleware.user_after_routing`

Rationale:

- Canonical Phase 0+ HTTP middleware taxonomy is single-choice: `system/app/route`.
- Any new epic mentioning `http.middleware.user*` **MUST** treat it only as legacy or renamed terminology.
- Legacy `http.middleware.user*` names **MUST NOT** appear as current tag names anywhere in contracts, SSoT, defaults, or gates.

## Runtime Usage Rule (MUST)

- If the owner package is an allowed compile-time dependency, runtime code **MUST** use the owner public tag constant.
- If the owner package is a forbidden compile-time dependency, runtime code **MAY** define a package-local mirror constant.
- A package-local mirror constant:
  - **MUST** be package-internal only
  - **MUST** equal the canonical tag string exactly
  - **MUST NOT** be treated as public API
  - **MUST** be verified by tooling gates for string equality

## Local Mirror Constants (MUST)

A reserved registry row may exist before the owner package exposes its canonical constant in runtime.

Until the owner epic introduces the tag-aware runtime entrypoint, non-owner packages may still reference the canonical tag only under the runtime usage rule above:

- owner public constant when the owner package is an allowed compile-time dependency
- package-local mirror constant when the owner package is a forbidden compile-time dependency

Local mirror constants are a compatibility mechanism only. They do not create ownership, public API, or schema authority outside the owner package.

## Contributor Rule (MUST)

- A non-owner epic **MAY** say `N/A (uses existing <tag>)`.
- A non-owner epic **MUST NOT** introduce or freeze a competing meta-schema for that tag.
- If the owner meta-schema is not cemented yet, contributor epics **MUST** say `meta per owner schema` or equivalent wording instead of inventing alternative keys.

## Introducing a New Tag (MUST)

Introducing a new reserved tag requires both of the following in the owner epic:

1. add a registry row in `docs/ssot/tags.md`
2. add the canonical public constant in the owner package

A new tag is not complete unless both artifacts exist.

## Non-tag Metadata Rule (MUST)

- PHP attributes, including DTO marker attributes, are **NOT** DI tags.
- PHP attributes **MUST NOT** be registered in this tag registry.
- DI tags and PHP attributes are orthogonal mechanisms and **MUST NOT** be conflated.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
