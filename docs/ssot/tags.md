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

```yaml
ssotVersion: 1
status: pre-stable
owner: repo
```

This document is the canonical registry for reserved DI tags.

## Goal

A single SSoT defines reserved DI tags, ownership, and naming rules so discovery and wiring stay deterministic and conflict-free.

## Invariants (MUST)

- This registry MUST be the canonical source for reserved DI tag names.
- Every reserved tag MUST have exactly one owner `package_id`.
- Shared ownership is forbidden.
- Only the owner epic may introduce or modify a reserved tag entry.
- Registry rows MAY exist independently of whether the semantic owner package currently registers runtime services for that tag.
- Every Coretsia-reserved DI tag identifier MUST be declared in `Coretsia\Foundation\Tag\ReservedTags`.
- Runtime packages MUST NOT define additional code-level registries for Coretsia-reserved DI tag identifiers.
- `ReservedTags` owns tag identifier strings only.
- Runtime semantics, metadata schema, discovery, ordering, dispatch, validation, and consumer behavior remain owned by the semantic owner package declared in this SSoT.
- Non-owner packages MAY use existing reserved tags, but MUST NOT redefine competing semantics or competing meta-schema for the same tag.
- Raw literal tag strings are allowed in docs, tests, and fixtures for readability, but MUST NOT be the preferred runtime-code pattern.

## Naming Rules (MUST)

- Tag names MUST use dot-separated tokens.
- Tag names MUST be lowercase.
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

- MAY use existing reserved tags
- MUST NOT define competing public tag APIs for the same tag
- MUST NOT define competing meta keys or competing semantics for the same tag

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
| `worker.*`          | `platform/worker` | Worker task-source extension namespace.                            |

## Reserved Tag Registry (MUST)

Stability enum is single-choice:

- `stable`
- `experimental`
- `deprecated`

| tag                           | owner package_id  | purpose                                     | stability      | notes                                                       |
|-------------------------------|-------------------|---------------------------------------------|----------------|-------------------------------------------------------------|
| `cli.command`                 | `platform/cli`    | CLI command discovery.                      | `stable`       | Canonical CLI discovery tag.                                |
| `error.mapper`                | `platform/errors` | Error mapper discovery.                     | `stable`       | Canonical discovery point for error mapping components.     |
| `health.check`                | `platform/health` | Health check discovery.                     | `experimental` | Reserved baseline row; owner package is future-facing.      |
| `http.middleware.app`         | `platform/http`   | Main application middleware slot.           | `stable`       | Canonical HTTP middleware taxonomy.                         |
| `http.middleware.app_post`    | `platform/http`   | Post-application middleware slot.           | `stable`       | Canonical HTTP middleware taxonomy.                         |
| `http.middleware.app_pre`     | `platform/http`   | Pre-application middleware slot.            | `stable`       | Canonical HTTP middleware taxonomy.                         |
| `http.middleware.route`       | `platform/http`   | Main route-scoped middleware slot.          | `stable`       | Canonical HTTP middleware taxonomy.                         |
| `http.middleware.route_post`  | `platform/http`   | Post-route middleware slot.                 | `stable`       | Canonical HTTP middleware taxonomy.                         |
| `http.middleware.route_pre`   | `platform/http`   | Pre-route middleware slot.                  | `stable`       | Canonical HTTP middleware taxonomy.                         |
| `http.middleware.system`      | `platform/http`   | Main system middleware slot.                | `stable`       | Canonical HTTP middleware taxonomy.                         |
| `http.middleware.system_post` | `platform/http`   | Post-system middleware slot.                | `stable`       | Canonical HTTP middleware taxonomy.                         |
| `http.middleware.system_pre`  | `platform/http`   | Pre-system middleware slot.                 | `stable`       | Canonical HTTP middleware taxonomy.                         |
| `kernel.hook.after_uow`       | `core/kernel`     | Post-unit-of-work lifecycle hook discovery. | `stable`       | Canonical kernel lifecycle hook.                            |
| `kernel.hook.before_uow`      | `core/kernel`     | Pre-unit-of-work lifecycle hook discovery.  | `stable`       | Canonical kernel lifecycle hook.                            |
| `kernel.reset`                | `core/foundation` | Reset-capable service discovery.            | `stable`       | Reserved canonical default reset-discovery tag name.        |
| `kernel.stateful`             | `core/foundation` | Stateful-service enforcement marker.        | `stable`       | Fixed enforcement marker.                                   |
| `worker.task_source`          | `platform/worker` | Worker task-source discovery.               | `experimental` | Exact-one selection; metadata per `worker-task-sources.md`. |

## `cli.command` Contract (MUST)

`cli.command` is owned by `platform/cli`; `Coretsia\Foundation\Tag\ReservedTags::CLI_COMMAND` supplies only its canonical identifier.

Enabled command-owner packages MAY contribute command services through this tag without a platform/cli dependency on the contributing package.

- Every `cli.command` service id MUST be the exact, case-sensitive command-class FQCN. The class MUST exist, be concrete, implement `Coretsia\Contracts\Cli\Command\CommandInterface`, and be registered under that same class FQCN as its service id. Aliases and unrelated service ids are invalid.
- Each command class MUST expose typed public constants `string NAME`, `string SUMMARY`, `string GROUP`, `bool HIDDEN`, `array ARGUMENTS`, and `array OPTIONS`. The owning provider MUST derive tag metadata from those constants; command identity MUST NOT be separately invented in the provider.
- The tag metadata map MUST contain exactly the following six keys:

```text
name
summary
group
hidden
arguments
options
```

- `name`, `summary`, and `group` are strings; `hidden` is a boolean; `arguments` and `options` are ordered lists of descriptor maps. Argument descriptors use exactly `name|summary|required|variadic`; option descriptors use exactly `name|summary|value|repeatable`. All entries MUST satisfy the owner-defined schema in `CommandTagSchema`.
- Top-level tag metadata keys `priority` and `mode`, and every other unknown metadata key, are forbidden. `mode` as an owner-defined command option inside `options` is distinct from a top-level tag metadata key and MUST NOT affect Kernel preset selection.
- Before any command service is resolved or instantiated, the catalog MUST validate the class FQCN, `CommandInterface` implementation, exact metadata schema, public typed command constants, and canonical equality of tag metadata with class constants. Class reflection/autoloading for validation does not authorize service resolution. Catalog-view overrides MAY change only `summary`, `group`, or `hidden` after base validation; they MUST NOT change command identity or original metadata.
- `CommandInterface::name()` MUST match the descriptor's `name` when the selected command service is lazily resolved for dispatch. The normal catalog MUST NOT instantiate services for discovery, `list`, or `help`.
- `TagRegistry::all(ReservedTags::CLI_COMMAND)` defines the canonical deterministic order: actual tag priority descending, then service id ascending under byte-order `strcmp`. The CLI consumer MUST preserve this output without re-sorting or re-deduplicating it.
- All `cli.command` registrations MUST use actual `TaggedService::priority() === 0`. A non-zero actual priority MUST fail before descriptor construction, independently of metadata contents, and MUST NOT be normalized, ignored, or overwritten. The actual priority field is separate from the forbidden `priority` metadata key.
- `TagRegistry` uses first-wins semantics for repeated registrations of the same `(tag, serviceId)`; later registrations are discarded and MUST NOT be reconstructed or revalidated by the CLI consumer. Two distinct service ids declaring the same command `name` MUST fail deterministically, not silently override one another.
- `doctor`, `help`, and `list` are reserved command names. Only the exact built-in command service ids registered by `platform/cli` MAY use them; external packages MUST NOT claim these names. The normal catalog includes the same `doctor` identity used by the ultra-early doctor path.
- Command-owner packages MUST consume CLI command, input, and output functionality only through `Coretsia\Contracts\Cli\Command\CommandInterface`, `Coretsia\Contracts\Cli\Input\InputInterface`, and `Coretsia\Contracts\Cli\Output\OutputInterface`. They MUST NOT depend on concrete `platform/cli` implementations for command contribution or execution.

## Forbidden Non-canonical Tags (MUST)

The following non-canonical tags are forbidden and MUST NOT be introduced anywhere:

- `http.middleware.user_before_routing`
- `http.middleware.user`
- `http.middleware.user_after_routing`

Rationale:

- Canonical HTTP middleware taxonomy is single-choice: `system/app/route`.
- Any new epic mentioning `http.middleware.user*` MUST treat it only as forbidden non-canonical terminology.
- `http.middleware.user*` names MUST NOT appear as current tag names anywhere in contracts, SSoT, defaults, or gates.

## Runtime Usage Rule (MUST)

Runtime package source MUST use `Coretsia\Foundation\Tag\ReservedTags::*` for Coretsia-reserved DI tags.

Raw literal tag strings are allowed in docs, tests, fixtures, and config defaults where readability or user-owned configuration is the subject.

Runtime package source MUST use `Coretsia\Foundation\Tag\ReservedTags::*` as the only code-level identifier registry for Coretsia-reserved DI tags.

Runtime package source MUST NOT define additional code-level registries for Coretsia-reserved DI tag identifiers.

Custom/user DI tags are outside this reserved registry unless explicitly promoted to Coretsia-reserved status through this SSoT.

## Contributor Rule (MUST)

- A non-owner epic MAY say `N/A (uses existing <tag>)`.
- A non-owner epic MUST NOT introduce or freeze a competing meta-schema for that tag.
- If the owner meta-schema is not cemented yet, contributor epics MUST say `meta per owner schema` or equivalent wording instead of inventing alternative keys.

## Introducing a New Tag (MUST)

Introducing a new reserved tag requires both of the following in the owner epic:

1. add registry row in `docs/ssot/tags.md`
2. add public constant in `Coretsia\Foundation\Tag\ReservedTags`
3. document semantic owner behavior in the owner package or SSoT

A new tag is not complete unless both artifacts exist.

## Non-tag Metadata Rule (MUST)

- PHP attributes, including DTO marker attributes, are NOT DI tags.
- PHP attributes MUST NOT be registered in this tag registry.
- DI tags and PHP attributes are orthogonal mechanisms and MUST NOT be conflated.

## Cross-references

- [SSoT Index](./INDEX.md)
