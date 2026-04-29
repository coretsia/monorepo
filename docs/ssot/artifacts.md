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

# Artifact Header and Schema Registry (SSoT)

This document is the canonical SSoT for artifact envelope shape, artifact header fields, deterministic serialization law, and artifact schema registry.

## Goal

A single SSoT defines artifact envelope, header, and schema versioning so all generated artifacts are deterministic and verifiable.

## Invariants (MUST)

- All artifacts **MUST** use the same logical envelope shape regardless of file encoding.
- Artifact generators **MUST** be rerun-no-diff.
- Artifacts **MUST NOT** embed timestamps, absolute paths, environment-dependent bytes, secrets, or PII.
- Artifact identity is the pair `name@schemaVersion`.
- Registry entries **MUST** declare explicit ownership.
- Header semantics and validation rules are defined by this registry and by the owning artifact schema.
- Artifact readers and consumers **MUST** validate by schema and header semantics, not by PHP class type checks.
- Artifact payloads **MAY** be derived from descriptors, results, or DTO-like models, but the serialized artifact is the canonical runtime-independent shape.
- Artifacts **MUST NOT** depend on PHP object identity or PHP class semantics at runtime.

## Artifact Envelope (MUST)

This envelope law applies to **all** artifacts, regardless of whether the final encoding is JSON, PHP, or any other artifact encoding.

The top-level object **MUST** be:

```json
{
  "_meta": {
    "name": "example",
    "schemaVersion": 1,
    "fingerprint": "deterministic-fingerprint",
    "generator": "owner/stable-generator-id",
    "requires": {
      "runtime": ">=1.0"
    }
  },
  "payload": {}
}
```

Top-level shape is single-choice:

- `"_meta"` contains the canonical header
- `"payload"` contains the schema-specific body

No alternative envelope forms are allowed.

## Header Fields (MUST)

The canonical header fields are:

- `name` — string
- `schemaVersion` — int
- `fingerprint` — string; deterministic
- `generator` — string; stable generator id and **MUST NOT** include build timestamps or absolute paths
- `requires` — optional; deterministic; for example minimum runtime version or compatible capability floor

### Header Semantics (MUST)

- `name` **MUST** equal the canonical artifact name from the registry row.
- `schemaVersion` **MUST** equal the canonical schema version from the registry row.
- `fingerprint` **MUST** be deterministic for the same logical inputs.
- `generator` **MUST** be stable for the same generator lineage and **MUST NOT** encode environment-specific bytes.
- `requires`, when present, **MUST** be deterministic and schema-relevant only.

## Deterministic Serialization Law (MUST)

This law applies to any JSON-like artifact body or header and also to any code generation step that materializes map ordering.

- Maps and objects **MUST** be normalized by sorting keys ascending by byte-order `strcmp` recursively at every nesting level.
- Lists and arrays **MUST** preserve order and **MUST NOT** be sorted.
- List-vs-map classification **MUST** use `array_is_list(...)` for **any** array value.
- Encoding flags **MUST** be deterministic.
- Serialized output **MUST** use unescaped slashes and unescaped unicode.
- Serialization and code generation **MUST NOT** depend on locale.
- Artifacts **MUST** be rerun-no-diff and **MUST NOT** embed timestamps, absolute paths, or environment-specific bytes.

## Empty Array Rule (Cemented) (MUST)

- `[]` **MUST** be treated as a list in serialized form.
- Producers **MUST** serialize `[]` exactly as `[]`.
- Consumers **MUST** interpret `[]` according to schema and context, for example whether the location requires a list or a map.

Rationale:

- PHP cannot represent empty-map vs empty-list distinction using arrays.
- `array_is_list([]) === true`.

Therefore serialized `[]` is byte-wise identical for both “empty list” and “empty map” intents. This is a cemented PHP limitation, not a per-artifact choice.

## Tooling Boundary and Runtime Law (MUST)

- `coretsia/devtools-internal-toolkit` is a Phase 0 tooling-only helper library.
- It **MUST NOT** become a mandatory runtime dependency.
- Runtime packages under `core/*` and `platform/*` that generate or consume artifacts **MUST** implement the deterministic laws locally, or via runtime-owned shared code.
- Runtime implementations **MUST** still match the same laws exactly:
  - byte-order key sorting for maps and objects
  - preserved list order
  - `array_is_list(...)` classification for any array value
  - locale-independent behavior
  - rerun-no-diff output

## Artifact Registry (MUST)

Registry table columns are single-choice:

- `artifact name`
- `schemaVersion`
- `owner package_id`
- `path shape`
- `notes`

Path shape records the stable owner-defined artifact basename family. It does not grant ownership to contracts packages and does not imply that filesystem location is globally shared across owners.

| artifact name     | schemaVersion | owner package_id   | path shape                                 | notes                                                                                                                                                                                |
|-------------------|--------------:|--------------------|--------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `container`       |           `1` | `core/kernel`      | `container.<owner-defined-encoding>`       | Compiled container artifact.                                                                                                                                                         |
| `config`          |           `1` | `core/kernel`      | `config.<owner-defined-encoding>`          | Compiled config artifact. FUTURE: may be introduced later.                                                                                                                           |
| `module-manifest` |           `1` | `core/kernel`      | `module-manifest.<owner-defined-encoding>` | ModulePlan-derived enabled/disabled/optionalMissing + deterministic topo order; envelope `{ "_meta", "payload" }`; no timestamps or absolute paths. FUTURE: may be introduced later. |
| `routes`          |           `1` | `platform/routing` | `routes.<owner-defined-encoding>`          | Route table artifact; schema and ownership belong to `platform/routing`, and contracts do not own artifact generation. FUTURE: may be introduced later.                              |

## Baseline Registry Entries Introduced by This Epic (MUST)

This epic introduces the following canonical artifact identities:

- `container@1`
- `config@1`
- `module-manifest@1`
- `routes@1`

## Artifact Payload Rule (MUST)

- Payloads **MAY** be derived from descriptors, results, or DTO-like models.
- The artifact payload is the canonical serialized shape.
- Artifact payloads **MUST NOT** depend on PHP object identity.
- Artifact payloads **MUST NOT** depend on PHP class type semantics at runtime.

## Reader and Consumer Rule (MUST)

Artifact readers and consumers:

- **MUST** validate by artifact header semantics and schema semantics
- **MUST NOT** rely on PHP class type checks
- **MUST NOT** treat in-memory object class identity as the source of truth for artifact validity

## Non-goals / Clarifications (MUST)

- This registry defines the global artifact envelope and deterministic serialization law.
- Owner-specific payload schemas may be detailed in later owner epics, but they **MUST** remain compatible with the global envelope and deterministic law defined here.
- Contracts packages do not gain ownership of artifact generation merely by exposing related contracts.
- Artifact format law is global; payload semantics are owner-scoped.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
