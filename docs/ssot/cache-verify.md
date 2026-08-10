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

```yaml
ssotVersion: 1
status: pre-stable
owner: core/kernel
```

This document is the canonical SSoT for Kernel-owned artifact cache verification semantics.

It defines how existing Kernel artifact files are classified as clean, dirty, invalid, or failure during verification.

It intentionally does not redefine the global artifact envelope, artifact header fields, artifact registry rows, deterministic serialization law, Kernel artifact production rules, or Kernel fingerprint construction rules.

## Goal

A single SSoT defines generation-aware Kernel cache verification semantics so cache state decisions are deterministic, safe, reproducible, concurrency-safe, and independent from filesystem metadata such as mtimes, permissions, and owners.

## Authority Boundary (MUST)

This document owns only Kernel cache verification semantics for existing Kernel-owned artifacts.

This document owns:

- verification input/output semantics;
- current-generation location linkage;
- selected-generation validation linkage;
- generation-level missing, invalid, stale, and exact-byte semantics;
- per-artifact clean/dirty/invalid classification;
- verification outcome precedence;
- missing artifact semantics;
- invalid PHP/envelope/header/payload semantics;
- fingerprint mismatch semantics;
- deterministic byte comparison semantics;
- filesystem metadata non-semantics;
- safe verification result shape constraints.

This document MUST NOT redefine:

- the canonical artifact envelope shape;
- canonical artifact header fields;
- canonical artifact registry rows;
- global deterministic serialization law;
- Kernel artifact production behavior;
- Kernel fingerprint input construction behavior;
- Kernel fingerprint exclusion policy;
- global observability metrics catalog;
- ownership of non-Kernel artifacts.

The canonical artifact envelope, artifact header fields, artifact registry rows, and global deterministic serialization law remain owned by:

```text
docs/ssot/artifacts.md
```

All other excluded rules remain owned by their respective canonical SSoT documents.

## Invariants (MUST)

- Cache verification MUST be deterministic for the same logical inputs and existing artifact bytes.
- Cache verification MUST compile the expected runtime container graph before calculating the current artifact fingerprint.
- The current fingerprint MUST include the safe deterministic `containerGraph` bucket.
- The same compiled `DefinitionGraph` MUST be used for both current fingerprint construction and expected REAL `container@1` construction.
- Cache verification MUST use the same `RuntimeContainerGraphCompiler` production path as artifact production.
- Cache verification MUST locate the selected generation through `ArtifactGenerationLocator`.
- Current-generation location MUST occur under the shared generation lock.
- The selected generation MUST pass `ArtifactGenerationValidator` before comparison.
- Cache verification MUST include `generation-manifest.php`.
- Generation byte comparison MUST use exact persisted bytes without newline normalization.
- Lock failure MUST remain an operation failure and MUST NOT be converted into an invalid-cache result.
- Cache verification MUST return one result entry for each of the four finalized generation files.
- Missing current state, invalid selected state, and generation-id mismatch MAY produce uniform classifications across all four entries.
- Exact byte drift is classified per generation file.
- Cache verification MUST return a deterministic aggregate outcome.
- Cache verification MUST NOT write artifacts.
- Cache verification MUST NOT repair artifacts.
- Cache verification MUST NOT mutate existing artifact files.
- Cache verification MUST NOT update mtimes.
- Cache verification MUST NOT rely on mtimes, ctimes, permissions, owners, inode ids, directory ordering, hostnames, usernames, process ids, or filesystem-specific metadata for clean/dirty/invalid decisions.
- Cache verification MUST NOT expose absolute paths, raw artifact payloads, raw config values, raw env values, secrets, PII, raw SQL, PHP warning text, stack traces, previous throwable messages, or raw fingerprint input.
- Cache verification MUST use safe relative artifact paths in public result data.
- Cache verification MUST treat observability as best-effort and non-semantic.

## Expected Kernel Generation Set (MUST)

Kernel cache verification verifies the immutable generation selected by:

```text
<artifact-root>/current
```

The expected finalized generation basenames are exactly:

```text
module-manifest.php
config.php
container.php
generation-manifest.php
```

Each expected basename MUST be interpreted through the canonical artifact identity and schema assignment defined by:

```text
docs/ssot/artifacts.md
```

This document references those canonical assignments only to define the verification set. It does not restate or redefine artifact registry rows.

The expected in-memory publication input contains the three runtime artifacts.

The expected generation manifest is derived from that publication set.

`routes@1` is not verified by Kernel cache verification because it is owned by `platform/routing`.

## Verification Inputs (MUST)

Cache verification consumes already-supplied resolved inputs.

The verifier receives:

- resolved `BootstrapConfig`;
- one resolved `ModuleResolution`;
- `EnvRepositoryInterface`;
- Kernel config subtree;
- explicit package default source candidates;
- explicit package rules source candidates;
- split roots;
- explicit rule sources;
- explicit env overlay mappings;
- mode preset source candidates.

The verifier derives the current `ModulePlan` only through:

```php
$moduleResolution->plan()
```

The verifier produces the expected runtime `DefinitionGraph` internally through `RuntimeContainerGraphCompiler`.

A raw descriptor iterable and a caller-supplied replacement `DefinitionGraph` are not supported verifier inputs.

Cache verification MUST NOT:

- resolve `BootstrapConfig`;
- perform a second module resolution;
- replace the `ModulePlan` contained in the supplied `ModuleResolution`;
- build `EnvRepositoryInterface`;
- run Bootstrap Phase A;
- run module discovery;
- scan arbitrary package directories;
- scan arbitrary app targets;
- enumerate arbitrary dotenv files.

## Verification Process (MUST)

Cache verification follows this semantic sequence:

1. derive the current `ModulePlan` from the supplied `ModuleResolution`;
2. run `ConfigKernel::compile(...)` exactly once for the supplied resolved inputs;
3. inspect `$compiledConfig['validation']` returned by that call;
4. when validation is failed, throw `ConfigInvalidException::fromValidationResult($compiledConfig['validation'])`;
5. compile one canonical runtime `DefinitionGraph` through `RuntimeContainerGraphCompiler`;
6. build deterministic fingerprint input through `ConfigFingerprintInputBuilder`;
7. calculate the expected graph-bound generation id through `FingerprintCalculator`;
8. build the expected three runtime artifact envelopes;
9. build the expected REAL `container@1` envelope from the same `DefinitionGraph`;
10. dump the three expected exact runtime artifact byte strings;
11. construct the expected `ArtifactPublicationSet`;
12. derive and dump the expected `artifact-generation@1` envelope;
13. locate `current` through `ArtifactGenerationLocator`;
14. classify an absent pointer as missing;
15. classify an invalid pointer or invalid selected generation as invalid;
16. compare the selected generation id with the expected generation id;
17. read and compare all four exact generation byte strings;
18. return safe deterministic results, generation identity fields, and aggregate counts.

The validation assertion reuses the result from the single `ConfigKernel::compile(...)` invocation.

`CacheVerifier` MUST NOT invoke `ConfigValidator` or otherwise perform config validation again.

The validation assertion MUST occur before `RuntimeContainerGraphCompiler`, fingerprint-input construction, fingerprint calculation, current-generation location, artifact reads, or artifact comparisons.

`CacheVerifier` MUST NOT catch or downgrade the resulting `ConfigInvalidException`.

`ArtifactGenerationLocator` performs current pointer reading while holding the shared generation lock.

`ArtifactGenerationValidator` validates the selected generation before `CacheVerifier` performs expected-byte comparison.

Cache verification MUST NOT write expected artifacts to disk.

Cache verification MUST NOT compile another graph after fingerprint construction.

The semantic ordering remains:

```text
compiled config
  -> container graph
  -> fingerprint input
  -> generation id
  -> runtime envelopes
  -> ArtifactPublicationSet
  -> expected generation manifest
  -> located selected generation
  -> exact byte comparison
```

## Existing Artifact Read Semantics (MUST)

Existing generation files are read through `PhpArtifactReader`.

For generation-aware verification the reader MUST support:

- exact byte reads without CRLF/CR normalization;
- non-executing canonical decoding of one already-read exact byte snapshot through `StablePhpArrayParser`;
- deterministic rejection of malformed or non-canonical serialization with `artifact-serialization-invalid`;
- deterministic conversion of filesystem read failures.

`CacheVerifier` uses exact-byte reads for comparison.

LF-normalized reads are not part of generation-aware clean/dirty semantics.

The reader MUST NOT:

- execute, evaluate, include, or require generated artifact bytes;
- resolve generation paths;
- locate `current`;
- validate generation or artifact schemas;
- calculate fingerprints;
- compare expected and existing bytes;
- emit logs, spans, metrics, stdout, or stderr;
- expose raw paths, warning text, source fragments, serialized bytes, decoded payloads, stack traces, or previous throwable messages.

Generation validation belongs to `ArtifactGenerationValidator`.

Clean/dirty/invalid orchestration belongs to `CacheVerifier`.

## Schema Validation Semantics (MUST)

The selected generation MUST pass generation-level validation before expected-byte comparison.

Generation-level validation includes:

- current pointer syntax and non-symlink status;
- finalized directory identity;
- exact required file set;
- non-symlink regular file status;
- `artifact-generation@1` schema;
- manifest generation id;
- declared byte lengths;
- declared SHA-256 values;
- runtime artifact names;
- runtime artifact schema versions;
- equality of all envelope fingerprints with the generation id.

An invalid pointer or invalid selected generation produces an invalid cache result.

The verifier MUST NOT downgrade structural or cryptographic generation invalidity to ordinary byte drift.

## Per-Artifact Statuses (MUST)

Each expected generation file receives exactly one status:

- `clean`
- `dirty`
- `invalid`

No other per-file status is allowed in the verification result.

### `clean`

An expected generation file is clean only when:

- `current` exists and is valid;
- the selected generation is valid;
- the selected generation id equals the expected generation id;
- that file's exact persisted bytes equal its expected exact bytes.

### `dirty`

Dirty state represents a structurally valid cache that is absent, stale, or byte-drifted.

Dirty reasons are:

- `current` does not exist;
- selected generation id differs from the expected generation id;
- one selected generation file differs from its expected exact bytes.

Cache verification itself MUST NOT regenerate or repair dirty state.

### `invalid`

Invalid state means `current` or the selected generation cannot be safely accepted.

Invalid causes include:

- malformed pointer bytes;
- pointer symlink substitution;
- unreadable pointer;
- missing selected generation directory;
- invalid generation directory identity;
- generation or parent-directory symlink substitution;
- missing or additional generation files;
- artifact-file symlink substitution;
- invalid or non-canonical artifact serialization;
- invalid generation manifest;
- byte-length mismatch;
- SHA-256 mismatch;
- artifact identity mismatch;
- schema-version mismatch;
- envelope fingerprint mismatch.

Unexpected infrastructure failures that prevent a normal verification result, including generation-lock failure, remain operation failures rather than invalid-cache classifications.

## Per-Artifact Reasons (MUST)

Baseline per-artifact reasons are:

- `ok`
- `missing`
- `changed`
- `fingerprint_mismatch`
- `invalid`

Reason semantics:

| Reason                 | Status    | Meaning                                                                |
|------------------------|-----------|------------------------------------------------------------------------|
| `ok`                   | `clean`   | Selected generation is expected and this file is exact-byte identical. |
| `missing`              | `dirty`   | No current generation is selected.                                     |
| `changed`              | `dirty`   | This selected generation file differs from expected exact bytes.       |
| `fingerprint_mismatch` | `dirty`   | Selected valid generation id differs from the expected generation id.  |
| `invalid`              | `invalid` | Current pointer or selected generation cannot be safely validated.     |

No reason may expose raw exception text, raw PHP warning text, raw artifact payloads, raw paths, or absolute paths.

## Missing Current Generation Semantics (MUST)

When `<artifact-root>/current` does not exist, all four expected generation entries MUST be classified as:

```text
status = dirty
reason = missing
```

An absent pointer is not invalid.

The verifier MUST NOT scan `generations/` for a fallback generation.

The verifier MUST NOT create `current`.

Rationale:

- cold caches may legitimately have no selected generation;
- publication is the correct remediation;
- verification does not write or repair cache state.

## Fingerprint Mismatch Semantics (MUST)

After successful current-generation location and validation, the selected generation id is compared with the expected graph-bound generation id.

If they differ, all four expected generation entries MUST be classified as:

```text
status = dirty
reason = fingerprint_mismatch
```

Generation-id mismatch is not invalid because the selected generation is structurally and cryptographically valid.

It was produced for different logical inputs and requires a new publication.

A semantic compiled-container graph change is a logical-input change and therefore changes the expected generation id.

## Byte Comparison Semantics (MUST)

Exact persisted bytes are compared only after:

- successful current pointer validation;
- successful selected-generation validation;
- selected generation-id equality with the expected generation id.

Both sides MUST be compared as exact byte strings.

No newline normalization is allowed.

If one file differs, that file MUST be classified as:

```text
status = dirty
reason = changed
```

Exact comparison applies to:

```text
module-manifest.php
config.php
container.php
generation-manifest.php
```

A byte mismatch is not invalid after the selected generation has already passed generation validation.

It represents deterministic drift relative to the rebuilt expected bytes.

## Filesystem Metadata Non-Semantics (MUST)

Cache verification MUST NOT use any of the following for clean/dirty/invalid classification:

- file mtime;
- file ctime;
- file permissions;
- file owner;
- file group;
- inode id;
- device id;
- directory entry order;
- filesystem traversal order.

Only current-pointer presence and validity, selected-generation validity, generation-id equality, and exact generation-file byte equality are cache-verification semantics.

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

For successfully completed verification over expected generation files:

1. If any artifact is invalid, aggregate outcome MUST be `invalid`.
2. Else if any artifact is dirty or missing, aggregate outcome MUST be `dirty`.
3. Else aggregate outcome MUST be `clean`.

`failure` is reserved for verification operation failure before a normal clean/dirty/invalid result can be safely completed.

Generation-lock acquisition, unlock, or close failure is an operation failure.

It MUST NOT be converted into:

```text
status = invalid
reason = invalid
```

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

A normal completed verification result MUST NOT set multiple state flags to true.

## Generation Identity Result Fields (MUST)

Every completed verification result contains:

- `expectedGenerationId` — the generation id rebuilt from the current resolved inputs;
- `currentGenerationId` — the id of the valid generation selected through `current`, or `null`.

`expectedGenerationId` MUST be a lowercase SHA-256 string.

`currentGenerationId` MUST:

- equal the selected valid generation id for a clean result;
- preserve the selected valid generation id when it differs from `expectedGenerationId`;
- be `null` when `current` is absent;
- be `null` when the pointer or selected generation is invalid.

The generation identity fields MUST NOT expose filesystem paths or raw fingerprint input.

## Counts (MUST)

Verification result counts MUST be deterministic and safe.

Result counts are:

- `expected_artifact_count`
- `existing_artifact_count`
- `missing_artifact_count`
- `dirty_artifact_count`
- `invalid_artifact_count`

Counts MUST NOT depend on directory enumeration order or unexpected filesystem metadata.

Counts MUST be bounded safe integers.

## Result Safety (MUST)

Verification result data MAY include:

- schema version;
- aggregate outcome;
- boolean state flags;
- safe expected generation id;
- nullable safe current generation id;
- safe artifact name;
- safe artifact basename;
- safe skeleton-relative artifact path;
- a stable logical current-generation diagnostic path;
- safe status token;
- safe reason token;
- expected byte count;
- existing byte count or null;
- safe explain entries;
- bounded counts.

A result path of the form:

```text
<artifactsCacheDir>/<appTarget>/generations/current/<basename>
```

is a logical stable diagnostic identifier.

It is not a physical filesystem path.

`current` remains a pointer file, not a directory.

Callers MUST NOT use verification result paths for runtime artifact loading.

Verification result data MUST NOT include:

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

Explain entries MAY include:

- artifact basename;
- skeleton-relative artifact path;
- reason token.

Explain entries MUST NOT include:

- absolute paths;
- raw artifact bytes;
- raw artifact payloads;
- raw config values;
- raw env values;
- exception messages;
- stack traces;
- host-specific bytes.

A clean artifact MUST have an empty explain entry list.

A non-clean artifact MAY include a safe explain entry with its basename, safe relative path, and reason token.

## Verification and Production Boundary (MUST)

Cache verification and artifact production remain separate responsibilities.

Cache verification:

- rebuilds the expected publication set and generation manifest in memory;
- locates the selected generation under a shared lock;
- validates the selected generation;
- compares generation identity and exact bytes;
- returns safe cache state.

Cache verification MUST NOT:

- write artifacts;
- repair artifacts;
- mutate finalized generations;
- replace `current`;
- call artifact writer methods.

Artifact production:

- builds one `ArtifactPublicationSet`;
- delegates publication to `ArtifactGenerationPublisher`;
- may validate and read an existing content-addressed generation only for exact reuse;
- does not decide cache clean/dirty/invalid state.

Artifact production and cache verification share:

- supplied `ModuleResolution` semantics;
- Phase-B config compilation semantics;
- `RuntimeContainerGraphCompiler`;
- `ContainerGraphFingerprintBucketBuilder`;
- `ConfigFingerprintInputBuilder`;
- `FingerprintCalculator`;
- Kernel runtime artifact builders;
- `StablePhpArrayDumper`;
- `ArtifactPublicationSet`;
- `ArtifactGenerationManifestBuilder`.

They execute the same deterministic expected-generation pipeline independently.

Neither may use an alternative graph-production, fingerprint, envelope, or byte-emission algorithm.

Artifact-only runtime boot MUST use `ArtifactGenerationLocator` and `ArtifactGenerationValidator` for current-generation selection and validation, but it MUST NOT invoke `CacheVerifier` or rebuild the expected generation.

An absent or invalid current generation is a runtime boot failure at the artifact-runtime boundary; it is not a `dirty` cache result because runtime boot does not perform cache classification.

## Observability Semantics (MUST)

Cache verification observability is best-effort and non-semantic.

Safe observability metadata MAY identify `containerGraph` as a fingerprint bucket name.

It MUST NOT expose the raw `DefinitionGraph`, raw service definitions, raw parameters, provider instances, runtime instances, or graph source paths.

Observability failures MUST NOT alter verification classification, result data, or exception precedence.

Cache verification observability MUST NOT expose:

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

Cache verification metrics, if emitted, MUST comply with the global observability SSoT.

This document does not own the global metrics catalog.

## Provider Registration Non-Semantics (MUST)

Registering cache-verification and generation services in the provider MUST NOT locate, validate, publish, or verify a generation.

Provider registration MUST NOT:

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
- This document does not define artifact-runtime boot failure taxonomy or Worker child boot semantics.
- This document does not require cache verification during normal provider registration.
- This document does not make filesystem mtimes, permissions, or owners semantic.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Artifact Generations](./artifact-generations.md)
- [Kernel Artifacts and Fingerprint Behavior](./artifacts-and-fingerprint.md)
- [Compiled Container Payload and Artifact-Only Boot Semantics](./compiled-container.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
