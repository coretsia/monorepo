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

# Cache Verification Semantics (SSoT)

This document is the canonical SSoT for Kernel-owned artifact cache verification semantics.

It defines how existing Kernel artifact files are classified as clean, dirty, invalid, or failure during verification.

It intentionally does not redefine the global artifact envelope, artifact header fields, artifact registry rows, deterministic serialization law, Kernel artifact production rules, or Kernel fingerprint construction rules.

## Goal

A single SSoT defines Kernel cache verification semantics so cache state decisions are deterministic, safe, reproducible, and independent from filesystem metadata such as mtimes, permissions, and owners.

## Authority Boundary (MUST)

This document owns only Kernel cache verification semantics for existing Kernel-owned artifacts.

This document owns:

- verification input/output semantics;
- per-artifact clean/dirty/invalid classification;
- verification outcome precedence;
- missing artifact semantics;
- invalid PHP/envelope/header/payload semantics;
- fingerprint mismatch semantics;
- deterministic byte comparison semantics;
- filesystem metadata non-semantics;
- safe verification result shape constraints.

This document **MUST NOT** redefine:

- the canonical artifact envelope shape;
- canonical artifact header fields;
- canonical artifact registry rows;
- global deterministic serialization law;
- Kernel artifact production behavior;
- Kernel fingerprint input construction behavior;
- Kernel fingerprint exclusion policy;
- global observability metrics catalog;
- ownership of non-Kernel artifacts.

Those rules remain owned by their canonical SSoT documents.

## Invariants (MUST)

- Cache verification **MUST** be deterministic for the same logical inputs and existing artifact bytes.
- Cache verification **MUST** classify each expected Kernel artifact independently.
- Cache verification **MUST** return a deterministic aggregate outcome.
- Cache verification **MUST NOT** write artifacts.
- Cache verification **MUST NOT** repair artifacts.
- Cache verification **MUST NOT** mutate existing artifact files.
- Cache verification **MUST NOT** update mtimes.
- Cache verification **MUST NOT** rely on mtimes, ctimes, permissions, owners, inode ids, directory ordering, hostnames, usernames, process ids, or filesystem-specific metadata for clean/dirty/invalid decisions.
- Cache verification **MUST NOT** expose absolute paths, raw artifact payloads, raw config values, raw env values, secrets, PII, raw SQL, PHP warning text, stack traces, previous throwable messages, or raw fingerprint input.
- Cache verification **MUST** use safe relative artifact paths in public result data.
- Cache verification **MUST** treat observability as best-effort and non-semantic.

## Expected Kernel Artifact Set (MUST)

Kernel cache verification verifies the Kernel-owned artifact files materialized by Kernel artifact production.

The expected artifact basenames are:

- `module-manifest.php`
- `config.php`
- `container.php`

The expected artifact identities are references to registry entries owned by `docs/ssot/artifacts.md`:

- `module-manifest@1`
- `config@1`
- `container@1`

This document does not redefine those registry rows.

The artifact `routes@1` is not verified by Kernel cache verification because `routes@1` is owned by `platform/routing`.

## Verification Inputs (MUST)

Cache verification consumes already-supplied resolved inputs.

The verifier may receive:

- resolved `BootstrapConfig`;
- resolved `ModulePlan`;
- `EnvRepositoryInterface`;
- Kernel config subtree;
- explicit package default source candidates;
- explicit package rules source candidates;
- split roots;
- explicit rule sources;
- explicit env overlay mappings;
- mode preset source candidates.

Cache verification **MUST NOT**:

- resolve `BootstrapConfig`;
- resolve `ModulePlan`;
- build `EnvRepositoryInterface`;
- run Bootstrap Phase A;
- run module discovery;
- scan arbitrary package directories;
- scan arbitrary app targets;
- enumerate arbitrary dotenv files.

## Verification Process (MUST)

Cache verification follows this semantic sequence:

1. Run `ConfigKernel::compile(...)` for the supplied resolved inputs.
2. Build deterministic fingerprint input through `ConfigFingerprintInputBuilder`.
3. Calculate the current fingerprint through `FingerprintCalculator`.
4. Build expected Kernel artifact envelopes in memory.
5. Dump expected artifact bytes in memory.
6. Resolve expected Kernel artifact paths.
7. For each expected artifact:
   - check whether the existing file exists;
   - read existing bytes and returned PHP array;
   - validate existing envelope/header/payload schema;
   - compare stored fingerprint with the current fingerprint;
   - compare existing normalized bytes with expected bytes.
8. Return safe deterministic per-artifact results and aggregate counts.

Cache verification **MUST NOT** write expected artifacts to disk during verification.

## Existing Artifact Read Semantics (MUST)

Existing PHP artifacts are read through a narrow artifact reader.

The reader **MUST**:

- read existing artifact bytes;
- normalize read bytes by converting CRLF and CR to LF;
- parse the PHP-returned value in isolated include behavior;
- reject artifact files that emit output;
- reject non-array returned values;
- convert filesystem/include/parse failures into deterministic safe reason tokens.

The reader **MUST NOT**:

- validate artifact schemas;
- calculate fingerprints;
- compare expected and existing bytes;
- emit logs, spans, metrics, stdout, or stderr;
- expose raw path strings, absolute paths, PHP warning text, emitted output, returned payloads, stack traces, or previous throwable messages.

Schema validation belongs to the artifact schema validator.

Clean/dirty/invalid orchestration belongs to the cache verifier.

## Schema Validation Semantics (MUST)

Existing artifact schema validation **MUST** validate by:

- canonical envelope structure;
- canonical header semantics;
- expected artifact name;
- expected schema version;
- Kernel-owned payload schema.

Existing artifact schema validation **MUST NOT** rely on PHP class identity as the source of truth.

Invalid envelope, invalid header, invalid schema version, invalid artifact name, or invalid payload shape **MUST** classify the artifact as invalid.

## Per-Artifact Statuses (MUST)

Each expected artifact receives exactly one status:

- `clean`
- `dirty`
- `invalid`

No other per-artifact status is allowed in the baseline verification result.

### `clean`

An artifact is clean only when all of the following are true:

- the expected artifact file exists;
- the existing PHP artifact can be read;
- the existing PHP artifact returns an array;
- the existing artifact emits no output;
- the existing envelope/header/payload schema is valid;
- the stored artifact fingerprint equals the current fingerprint;
- the existing LF-normalized bytes exactly equal the expected deterministic bytes.

### `dirty`

An artifact is dirty when the existing artifact is safe to classify as out of date rather than structurally invalid.

Dirty reasons include:

- missing expected artifact file;
- stored fingerprint mismatch;
- deterministic byte mismatch.

Dirty artifacts are candidates for regeneration by artifact production.

Cache verification itself **MUST NOT** regenerate them.

### `invalid`

An artifact is invalid when the existing artifact exists or is attempted to be read but cannot be safely accepted as a valid artifact.

Invalid reasons include:

- unreadable file;
- invalid PHP artifact;
- PHP include/read failure;
- emitted output from artifact file;
- non-array PHP return value;
- invalid envelope;
- invalid header;
- invalid artifact name;
- invalid schema version;
- invalid payload schema;
- any unexpected throwable during existing artifact read or schema validation.

Invalid artifacts are not clean and are not merely cleanly out-of-date.

## Per-Artifact Reasons (MUST)

Baseline per-artifact reasons are:

- `ok`
- `missing`
- `changed`
- `fingerprint_mismatch`
- `invalid`

Reason semantics:

| Reason                 | Status    | Meaning                                                                        |
|------------------------|-----------|--------------------------------------------------------------------------------|
| `ok`                   | `clean`   | Existing artifact is schema-valid, fingerprint-matching, and byte-identical.   |
| `missing`              | `dirty`   | Expected artifact file does not exist.                                         |
| `changed`              | `dirty`   | Existing artifact bytes differ from expected deterministic bytes.              |
| `fingerprint_mismatch` | `dirty`   | Existing artifact stores a fingerprint different from the current fingerprint. |
| `invalid`              | `invalid` | Existing artifact cannot be safely accepted as a valid artifact.               |

No reason may expose raw exception text, raw PHP warning text, raw artifact payloads, raw paths, or absolute paths.

## Missing Artifact Semantics (MUST)

A missing expected artifact file **MUST** be classified as:

```text
status = dirty
reason = missing
```

A missing file is not invalid.

Rationale:

- missing generated cache artifacts are expected in cold-cache scenarios;
- the correct remediation is artifact production;
- verification does not write or repair artifacts.

## Fingerprint Mismatch Semantics (MUST)

After successful read and schema validation, the existing artifact header fingerprint is compared to the current fingerprint.

If the stored fingerprint does not equal the current fingerprint, the artifact **MUST** be classified as:

```text
status = dirty
reason = fingerprint_mismatch
```

Fingerprint mismatch is not invalid when the existing artifact schema is valid.

Rationale:

- the artifact is structurally valid;
- it was produced for different logical inputs;
- regeneration is required.

## Byte Comparison Semantics (MUST)

After successful read, schema validation, and fingerprint match, existing bytes are compared to expected bytes.

Both sides **MUST** be compared as LF-normalized bytes.

If the bytes differ, the artifact **MUST** be classified as:

```text
status = dirty
reason = changed
```

Byte mismatch is not invalid when the existing artifact schema is valid.

Rationale:

- byte mismatch indicates artifact drift;
- deterministic artifact production should restore rerun-no-diff bytes;
- verification does not repair the artifact.

## Filesystem Metadata Non-Semantics (MUST)

Cache verification **MUST NOT** use any of the following for clean/dirty/invalid classification:

- file mtime;
- file ctime;
- file permissions;
- file owner;
- file group;
- inode id;
- device id;
- directory entry order;
- filesystem traversal order.

Only expected artifact presence, existing artifact readability/parsing, schema validity, stored fingerprint, and deterministic byte equality are semantic.

## Aggregate Outcome Semantics (MUST)

The aggregate verification outcome is one of:

- `clean`
- `dirty`
- `invalid`
- `failure`

### Outcome Precedence (MUST)

Outcome precedence is:

```text
failure > invalid > dirty > clean
```

For successfully completed verification over expected artifacts:

1. If any artifact is invalid, aggregate outcome **MUST** be `invalid`.
2. Else if any artifact is dirty or missing, aggregate outcome **MUST** be `dirty`.
3. Else aggregate outcome **MUST** be `clean`.

`failure` is reserved for verification operation failure before a normal clean/dirty/invalid result can be safely completed.

## Boolean Result Flags (MUST)

Verification result flags are derived from the aggregate outcome.

For aggregate outcome `clean`:

```text
clean = true
dirty = false
invalid = false
```

For aggregate outcome `dirty`:

```text
clean = false
dirty = true
invalid = false
```

For aggregate outcome `invalid`:

```text
clean = false
dirty = false
invalid = true
```

A normal completed verification result **MUST NOT** set multiple state flags to true.

## Counts (MUST)

Verification result counts **MUST** be deterministic and safe.

Baseline counts are:

- `expected_artifact_count`
- `existing_artifact_count`
- `missing_artifact_count`
- `dirty_artifact_count`
- `invalid_artifact_count`

Counts **MUST NOT** depend on directory enumeration order or unexpected filesystem metadata.

Counts **MUST** be bounded safe integers.

## Result Safety (MUST)

Verification result data **MAY** include:

- schema version;
- aggregate outcome;
- boolean state flags;
- safe artifact name;
- safe artifact basename;
- safe skeleton-relative artifact path;
- safe status token;
- safe reason token;
- expected byte count;
- existing byte count or null;
- safe explain entries;
- bounded counts.

Verification result data **MUST NOT** include:

- absolute paths;
- target filesystem paths;
- raw artifact bytes;
- raw artifact payloads;
- raw config values;
- raw env values;
- secrets;
- PII;
- raw SQL;
- PHP warning text;
- stack traces;
- throwable messages;
- previous throwable messages;
- raw fingerprint input.

## Explain Entries (MUST)

Verification explain entries are safe per-artifact diagnostic metadata.

Explain entries **MAY** include:

- artifact basename;
- skeleton-relative artifact path;
- reason token.

Explain entries **MUST NOT** include:

- absolute paths;
- raw artifact bytes;
- raw artifact payloads;
- raw config values;
- raw env values;
- exception messages;
- stack traces;
- host-specific bytes.

A clean artifact **MUST** have an empty explain entry list.

A non-clean artifact **MAY** include a safe explain entry with its basename, safe relative path, and reason token.

## Verification and Production Boundary (MUST)

Cache verification and artifact production are separate responsibilities.

Cache verification:

- reads existing artifacts;
- builds expected artifacts in memory;
- compares fingerprints and bytes;
- returns safe cache state.

Cache verification **MUST NOT**:

- write artifacts;
- repair artifacts;
- call artifact writer methods;
- mutate existing artifact files.

Artifact production:

- writes expected artifacts;
- does not decide cache clean/dirty/invalid state;
- does not read existing generated artifacts for reuse.

## Observability Semantics (MUST)

Cache verification observability is best-effort and non-semantic.

Observability failures **MUST NOT** alter verification classification, result data, or exception precedence.

Cache verification observability **MUST NOT** expose:

- absolute paths;
- raw artifact bytes;
- raw artifact payloads;
- raw config values;
- raw env values;
- secrets;
- PII;
- raw SQL;
- PHP warning text;
- stack traces;
- throwable messages;
- previous throwable messages;
- raw fingerprint input.

Cache verification metrics, if emitted, **MUST** comply with the global observability SSoT.

This document does not own the global metrics catalog.

## Provider Registration Non-Semantics (MUST)

Registering cache verification services in the provider **MUST NOT** run cache verification.

Provider registration **MUST NOT**:

- read artifacts;
- write artifacts;
- calculate fingerprints;
- run `ConfigKernel::compile(...)`;
- resolve `BootstrapConfig`;
- resolve `ModulePlan`;
- build `EnvRepositoryInterface`;
- invoke reset orchestration;
- start a UnitOfWork;
- emit stdout or stderr;
- start cache verification spans;
- emit cache verification metrics;
- write cache verification logs.

## Non-goals / Clarifications (MUST)

- This document does not define the global artifact envelope.
- This document does not define artifact header fields.
- This document does not define artifact registry rows.
- This document does not define Kernel artifact production rules.
- This document does not define Kernel fingerprint input construction.
- This document does not define Kernel fingerprint exclusions.
- This document does not define platform-owned artifact verification.
- This document does not define `routes@1` verification.
- This document does not define how artifact production is triggered.
- This document does not require cache verification during normal provider registration.
- This document does not make filesystem mtimes, permissions, or owners semantic.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Kernel Artifacts and Fingerprint Behavior](./artifacts-and-fingerprint.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
