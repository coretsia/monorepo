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

# Artifact Generations (SSoT)

```yaml
ssotVersion: 1
status: pre-stable
owner: core/kernel
```

This document is the canonical SSoT for Kernel immutable artifact generation identity, generation storage layout, publication-set invariants, `artifact-generation@1`, staging paths, finalized generation paths, and generation cache-control files.

It intentionally does not redefine the global artifact envelope, global artifact header fields, deterministic serialization law, or global artifact registry structure.

Those are owned by:

```text
docs/ssot/artifacts.md
```

It intentionally does not redefine Kernel fingerprint input construction, fingerprint exclusions, current flat-layout compiler behavior, or cache verification classification.

Those remain owned by:

```text
docs/ssot/artifacts-and-fingerprint.md
docs/ssot/cache-verify.md
```

## Goal

A single Kernel-owned SSoT defines the final immutable generation storage model before the production artifact writer is migrated.

The model must ensure that:

- one generation id identifies one complete Kernel artifact publication set;
- finalized generation contents are immutable;
- generation metadata validates exact runtime artifact bytes;
- staging randomness remains outside semantic identity;
- generation paths remain outside artifacts and diagnostics;
- mutable publication control state is not misclassified as generated artifact data;
- the existing production compiler can remain on the flat layout until a later explicit migration.

## Authority Boundary (MUST)

This document owns:

- generation id semantics;
- generation directory layout;
- finalized generation artifact basenames;
- staging directory shape;
- staging suffix domain;
- publication-set membership;
- publication-set fingerprint invariants;
- publication-set canonical-byte invariants;
- `artifact-generation@1` payload semantics;
- generation manifest header restrictions;
- generation manifest artifact metadata semantics;
- generation-specific path resolver behavior;
- `current` classification;
- `generation.lock` classification;
- immutable finalized-generation policy;
- current production writer boundary before generation-aware publication is activated.

This document MUST NOT redefine:

- the global artifact envelope;
- general artifact header field semantics;
- general deterministic map/list normalization;
- the global artifact registry table structure;
- `module-manifest@1` payload semantics;
- `config@1` payload semantics;
- `container@1` payload semantics;
- Kernel fingerprint input buckets;
- Kernel fingerprint exclusion policy;
- cache verification outcome classification;
- compiled-container runtime boot semantics;
- Bootstrap Phase A artifact-cache-directory resolution;
- generation retention policy;
- garbage collection policy;
- stale staging cleanup implementation;
- operating-system lock implementation.

## Invariants (MUST)

- A generation id MUST equal the shared Kernel artifact fingerprint.
- A generation id MUST be a lowercase 64-character SHA-256.
- One finalized generation MUST correspond to exactly one generation id.
- One generation publication set MUST contain exactly three runtime artifacts.
- All three publication-set envelope fingerprints MUST be identical.
- Publication-set bytes MUST be canonical deterministic PHP artifact bytes.
- A finalized generation MUST contain exactly four generation artifacts.
- Finalized generation artifacts MUST NOT be modified in place.
- `artifact-generation@1` MUST describe exactly the three runtime artifacts.
- `artifact-generation@1` MUST NOT describe itself.
- `artifact-generation@1` MUST NOT contain paths.
- `artifact-generation@1` MUST NOT contain `_meta.requires`.
- Staging randomness MUST NOT affect fingerprint identity or artifact bytes.
- `current` MUST be classified as a cache-control file.
- `generation.lock` MUST be classified as a cache-control file.
- `current` and `generation.lock` MUST NOT be registered as artifacts.
- Generation path diagnostics MUST NOT expose path values.
- The production compiler MUST remain on the existing flat layout until generation-aware publication is explicitly activated.

## Terminology

### Artifact root

The already-resolved Kernel artifact output root:

```text
<artifact-root>
```

Its derivation remains owned by `ArtifactPathResolver` and Bootstrap Phase A policy.

### Generation id

The lowercase 64-character SHA-256 shared artifact fingerprint.

### Publication set

The immutable in-memory set containing the canonical bytes and validated envelopes for:

```text
module-manifest.php
config.php
container.php
```

### Staging generation

A not-yet-finalized generation materialized under a unique staging directory.

### Finalized generation

An immutable generation directory named exactly by the generation id.

### Generation manifest

The Kernel-owned `artifact-generation@1` artifact stored as:

```text
generation-manifest.php
```

### Cache-control file

Mutable or operational filesystem state used to coordinate or select generated cache data, but which is not itself an artifact envelope or artifact registry identity.

## Generation Identity (MUST)

The canonical generation id domain is:

```text
/\A[a-f0-9]{64}\z/
```

The generation id MUST:

- be a string;
- contain exactly 64 bytes;
- contain only lowercase `a` through `f` and digits `0` through `9`;
- equal the shared fingerprint of all publication-set envelopes;
- equal the fingerprint of the `artifact-generation@1` envelope;
- equal the `generationId` payload field;
- equal the finalized generation directory basename.

The generation id MUST NOT:

- be normalized from uppercase;
- be trimmed;
- contain a `sha256:` prefix;
- contain separators;
- contain whitespace;
- contain staging randomness;
- contain path data;
- contain timestamps;
- contain process-specific data.

Invalid generation ids fail with a stable path-free reason.

## Kernel Generation Publication Set (MUST)

The publication set contains exactly:

```text
module-manifest.php
config.php
container.php
```

Its immutable logical state is:

```text
generation id
module-manifest.php canonical bytes
config.php canonical bytes
container.php canonical bytes
```

The constructor boundary MUST validate each supplied envelope using the canonical Kernel artifact schema validator.

The expected identities are:

| basename              | artifact identity   |
| --------------------- | ------------------- |
| `module-manifest.php` | `module-manifest@1` |
| `config.php`          | `config@1`          |
| `container.php`       | `container@1`       |

All three envelopes MUST have the same fingerprint.

That common fingerprint MUST be accepted by the generation-id value object.

For each artifact:

```text
supplied bytes
```

MUST exactly equal:

```text
StablePhpArrayDumper::dumpStableEnvelope(envelope)
```

An empty byte string is invalid.

A non-canonical byte representation is invalid even when it parses to an equivalent PHP array.

The publication set MUST retain only:

- immutable generation identity;
- canonical artifact bytes.

The constructor envelopes are validation inputs and need not remain retained publication state.

The canonical exported artifact-byte map order is:

```text
config.php
container.php
module-manifest.php
```

## Final Storage Layout (MUST)

The final target storage layout is:

```text
<artifact-root>/
├─ generations/
│  └─ <generation-id>/
│     ├─ module-manifest.php
│     ├─ config.php
│     ├─ container.php
│     └─ generation-manifest.php
├─ current
└─ generation.lock
```

The artifact root contains:

```text
generations/
current
generation.lock
```

The cache-control files are not children of a finalized generation.

### Generations directory

The generations directory is:

```text
<artifact-root>/generations
```

Its canonical basename is:

```text
generations
```

### Finalized generation directory

The finalized generation directory is:

```text
<artifact-root>/generations/<generation-id>
```

The final path component MUST exactly equal the generation id.

The immediate parent path component MUST exactly equal:

```text
generations
```

### Finalized generation contents

A finalized generation contains exactly:

```text
module-manifest.php
config.php
container.php
generation-manifest.php
```

The canonical basename owner is:

```text
ArtifactGeneration
```

`generation-manifest.php` MUST NOT be accepted as a legacy flat-layout artifact basename.

### Immutability

After finalization, generation contents MUST NOT change.

A writer MUST NOT:

- rewrite a finalized artifact;
- replace a finalized artifact;
- append to a finalized artifact;
- add an artifact to a finalized generation;
- remove an artifact from a finalized generation;
- change `generation-manifest.php`;
- place temporary files inside a finalized generation;
- use the same generation id for different bytes.

A different artifact set requires a different fingerprint and therefore a different generation id.

## Staging Layout (MUST)

The canonical staging path is:

```text
<artifact-root>/generations/.staging-<generation-id>-<random-suffix>
```

The staging prefix is:

```text
.staging-
```

The random suffix domain is:

```text
/\A[a-f0-9]{32}\z/
```

The production suffix generator uses:

```text
16 random bytes
```

encoded through:

```text
bin2hex(...)
```

The random suffix:

- MUST be lowercase hexadecimal;
- MUST contain exactly 32 characters;
- MUST be validated when supplied explicitly;
- MUST be generated independently for each staging attempt;
- MUST NOT enter any artifact envelope;
- MUST NOT enter any artifact payload;
- MUST NOT enter artifact fingerprint input;
- MUST NOT enter generation manifest metadata;
- MUST NOT enter generated artifact bytes;
- MUST NOT be exposed through diagnostics.

A failure to generate staging randomness MUST fail with a stable path-free reason.

## Generation Path Domain (MUST)

Generation-specific path APIs receive an already-resolved artifact root.

They MUST NOT resolve Bootstrap configuration or application overrides.

Returned paths MUST use:

```text
/
```

as the separator representation.

Canonical accepted root forms include:

```text
/var/cache/web
C:/cache/web
//server/share/cache/web
```

Backslashes in accepted Windows paths are normalized to `/`.

A UNC root MUST:

- start with exactly two separators;
- contain a non-empty server component;
- contain a non-empty share component.

Generation path validation MUST reject:

- empty strings;
- leading whitespace;
- trailing whitespace;
- control characters;
- URL-like strings;
- malformed drive prefixes;
- malformed UNC prefixes;
- triple-leading separators;
- duplicate separators outside the canonical UNC prefix;
- empty interior path segments;
- `.` segments;
- `..` segments;
- path traversal;
- non-canonical trailing `/.`;
- non-canonical trailing `/..`;
- paths longer than 4096 bytes.

The Unix root:

```text
/
```

is preserved.

A Windows drive root such as:

```text
C:/
```

is preserved.

Other trailing separators are removed before child paths are derived.

## `ArtifactGeneration` Model (MUST)

`ArtifactGeneration` is an immutable validated finalized-generation model.

It contains:

```text
generation id
generation directory
module-manifest path
config path
container path
generation-manifest path
```

The four artifact paths MUST be derived from the validated generation directory.

Callers MUST NOT supply the four child paths independently.

The object MUST reject a generation directory when:

- its basename differs from the generation id;
- its parent basename differs from `generations`;
- its path is invalid;
- a derived child path exceeds the path bound.

The object MUST NOT:

- read files;
- write files;
- calculate fingerprints;
- parse artifacts;
- select `current`;
- expose paths in exception messages.

## `ArtifactGenerationPathResolver` Boundary (MUST)

`ArtifactGenerationPathResolver` owns only paths below the artifact root.

It resolves:

```text
generations/
generations/<generation-id>/
generations/<generation-id>/module-manifest.php
generations/<generation-id>/config.php
generations/<generation-id>/container.php
generations/<generation-id>/generation-manifest.php
generations/.staging-<generation-id>-<random-suffix>
current
generation.lock
```

It MUST:

- validate the artifact-root path domain;
- validate explicit staging suffixes;
- generate staging suffixes from 16 random bytes;
- construct an immutable `ArtifactGeneration`;
- keep diagnostics path-free.

It MUST NOT:

- read config;
- resolve Bootstrap defaults;
- read files;
- write files;
- create directories;
- acquire locks;
- read `current`;
- select generations;
- calculate fingerprints;
- build artifact envelopes;
- validate artifact payloads.

## `ArtifactPathResolver` Delegation (MUST)

`ArtifactPathResolver` remains the owner of the final Kernel artifact root.

It derives that root from:

```text
BootstrapConfig::skeletonRoot()
BootstrapConfig::artifactsCacheDir()
BootstrapConfig::appTarget()->value
```

Generation-specific path derivation MUST be delegated to:

```text
ArtifactGenerationPathResolver
```

The legacy flat-layout methods remain available until generation-aware publication is explicitly activated:

```text
module-manifest.php
config.php
container.php
```

The compatibility method:

```text
cacheDirectory()
```

continues to return the artifact root.

The generation manifest basename MUST NOT be added to the legacy flat-layout basename allowlist.

## `artifact-generation@1` Identity (MUST)

The canonical artifact identity is:

```text
artifact-generation@1
```

Its owner is:

```text
core/kernel
```

Its canonical PHP basename is:

```text
generation-manifest.php
```

The global artifact registry row is owned by:

```text
docs/ssot/artifacts.md
```

This document owns the artifact-specific header restrictions and payload semantics.

## Generation Manifest Envelope (MUST)

The top-level envelope is exactly:

```php
[
    '_meta' => [
        'fingerprint' => '<generation-id>',
        'generator' => 'core/kernel/artifacts',
        'name' => 'artifact-generation',
        'schemaVersion' => 1,
    ],
    'payload' => [
        // artifact-generation@1 payload
    ],
]
```

The envelope contains exactly:

```text
_meta
payload
```

The header contains exactly:

```text
fingerprint
generator
name
schemaVersion
```

The header MUST NOT contain:

```text
requires
```

Header semantics are:

```text
name = artifact-generation
schemaVersion = 1
fingerprint = generation id
generator = core/kernel/artifacts
```

The generation fingerprint MUST satisfy the generation-id SHA-256 domain.

## Generation Manifest Payload (MUST)

The payload is exactly:

```php
[
    'artifacts' => [
        'config.php' => [
            'bytes' => int,
            'sha256' => string,
        ],
        'container.php' => [
            'bytes' => int,
            'sha256' => string,
        ],
        'module-manifest.php' => [
            'bytes' => int,
            'sha256' => string,
        ],
    ],
    'generationId' => string,
    'schemaVersion' => 1,
]
```

The payload contains exactly:

```text
artifacts
generationId
schemaVersion
```

No additional payload fields are allowed.

The canonical payload key order is:

```text
artifacts
generationId
schemaVersion
```

### `generationId`

`generationId` MUST:

- be a lowercase 64-character SHA-256;
- equal `_meta.fingerprint`;
- equal every publication-set envelope fingerprint;
- equal the finalized generation directory basename.

### `schemaVersion`

The payload field MUST be:

```text
schemaVersion = 1
```

It MUST equal the envelope header schema version.

### `artifacts`

The artifact map contains exactly:

```text
config.php
container.php
module-manifest.php
```

The canonical artifact key order is:

```text
config.php
container.php
module-manifest.php
```

The map MUST NOT contain:

```text
generation-manifest.php
routes.php
current
generation.lock
```

The map MUST NOT omit any required runtime artifact.

## Artifact Metadata Entries (MUST)

Each artifact metadata entry contains exactly:

```text
bytes
sha256
```

The canonical entry-key order is:

```text
bytes
sha256
```

### `bytes`

`bytes` MUST:

- be an integer;
- be greater than zero;
- equal the byte length of the corresponding canonical PHP artifact bytes.

No arbitrary schema-level upper bound is introduced by `artifact-generation@1`.

Normal PHP integer representation and upstream artifact production constraints remain applicable.

### `sha256`

`sha256` MUST:

- be a string;
- match `/\A[a-f0-9]{64}\z/`;
- equal the SHA-256 of the corresponding canonical PHP artifact bytes.

Hash input is the exact byte string written for the corresponding artifact.

It is not:

- the parsed payload;
- the parsed envelope;
- stable JSON;
- a filesystem path;
- filesystem metadata.

## Generation Manifest Self-Exclusion (MUST)

`generation-manifest.php` MUST NOT appear inside its own `artifacts` map.

The generation manifest is derived from the three-artifact publication set.

Self-exclusion prevents circular byte-hash identity.

The generation manifest envelope fingerprint still equals the generation id.

Its own byte hash is not part of the `artifact-generation@1` payload.

## No-Path Law (MUST)

The generation manifest MUST NOT contain:

```text
path
paths
artifactRoot
artifact-root
generationDirectory
generation-directory
stagingDirectory
staging-directory
sourcePath
absolutePath
relativePath
currentPath
generationLockPath
```

This prohibition applies semantically, not only to those exact field names.

No envelope extension or nested map may be used to export path state.

`_meta.requires` is forbidden for `artifact-generation@1`.

The exact payload and header shapes enforce this law.

## `ArtifactGenerationManifestBuilder` Boundary (MUST)

The builder receives one validated immutable `ArtifactPublicationSet`.

It MUST:

1. use the publication-set fingerprint as generation id;
2. calculate each `bytes` value from the canonical artifact byte string;
3. calculate each `sha256` from the same byte string;
4. emit the exact three-artifact map;
5. set payload `generationId` to the publication-set fingerprint;
6. set payload `schemaVersion` to `1`;
7. construct the envelope through `ArtifactEnvelopeFactory::artifactGeneration(...)`.

It MUST NOT:

- read files;
- write files;
- resolve paths;
- generate staging suffixes;
- select `current`;
- calculate the shared artifact fingerprint;
- include generation-manifest metadata for itself;
- include paths;
- include `requires`.

## `ArtifactEnvelopeFactory` Generation Boundary (MUST)

`ArtifactEnvelopeFactory` defines:

```text
ARTIFACT_GENERATION = artifact-generation
SCHEMA_VERSION_ARTIFACT_GENERATION = 1
```

Its generation-envelope operation MUST:

- validate the fingerprint through the generation-id domain;
- use the canonical generation artifact name;
- use schema version `1`;
- set `requires` to absent;
- use the canonical stable Kernel artifact generator id;
- normalize the envelope through the shared payload normalizer.

The operation MUST NOT accept a `requires` argument.

## Generation Manifest Validation (MUST)

`ArtifactGenerationManifestValidator` delegates to the canonical Kernel artifact schema validator with the expected identity:

```text
artifact-generation@1
```

Validation MUST reject:

- invalid top-level envelope shape;
- invalid header shape;
- additional header fields;
- `_meta.requires`;
- wrong artifact name;
- wrong schema version;
- non-SHA-256 header fingerprint;
- invalid payload shape;
- additional payload fields;
- missing payload fields;
- non-canonical payload key order;
- generation id different from header fingerprint;
- invalid generation id;
- a fourth artifact;
- a missing artifact;
- non-canonical artifact key order;
- list-shaped artifact maps;
- additional artifact-entry fields;
- missing artifact-entry fields;
- non-positive byte counts;
- non-integer byte counts;
- invalid SHA-256 values;
- path fields.

The schema validator validates declared metadata shape and values.

It does not read filesystem artifacts or compare declared metadata with persisted files.

That later byte-to-file comparison belongs to publication verification or runtime selection work.

## Cache-Control Files (MUST)

### Classification table

| path basename     | classification     | artifact identity | immutable generation member |
| ----------------- | ------------------ | ----------------- | --------------------------- |
| `current`         | cache-control file | none              | no                          |
| `generation.lock` | cache-control file | none              | no                          |

Neither file may be registered in the global artifact registry.

Neither file uses the artifact envelope.

Neither file is part of the generation manifest artifact map.

### `current`

The canonical path is:

```text
<artifact-root>/current
```

Its semantic role is to select one finalized generation id.

It MUST NOT select a generation by storing:

- an absolute path;
- a relative path;
- a staging directory name;
- arbitrary JSON-like metadata;
- an artifact envelope.

It MUST NOT affect generation fingerprint or generated artifact bytes.

The exact byte encoding and atomic replacement mechanism remain deferred to the generation-aware writer implementation.

### `generation.lock`

The canonical path is:

```text
<artifact-root>/generation.lock
```

Its semantic role is publication coordination.

It MUST NOT:

- be registered as an artifact;
- use an artifact envelope;
- enter generation identity;
- enter generated bytes;
- enter generation manifest metadata;
- enter diagnostics as a raw path.

Its contents and filesystem metadata are non-semantic operational state.

## Target Publication Protocol (MUST When Activated)

A later generation-aware production writer MUST:

1. obtain one validated immutable publication set;
2. build one canonical `artifact-generation@1` envelope;
3. resolve one unique staging directory;
4. materialize the three runtime artifacts in staging;
5. materialize `generation-manifest.php` in staging;
6. validate the complete staged generation;
7. finalize the directory under `generations/<generation-id>`;
8. update `current` only after finalization succeeds;
9. coordinate conflicting publication attempts through `generation.lock`;
10. avoid exposing a partial generation as selected.

The final publication implementation MUST NOT mutate finalized generation contents.

Exact operating-system lock APIs, rename primitives, retry behavior, stale staging cleanup, and existing-generation reuse remain deferred to the generation-aware writer implementation.

## Current Production Boundary (MUST)

The generation model defined by this document is final target infrastructure.

Until generation-aware publication is explicitly activated, the production compiler continues to write:

```text
<artifact-root>/module-manifest.php
<artifact-root>/config.php
<artifact-root>/container.php
```

The production compiler MUST NOT yet write:

```text
<artifact-root>/generations/
<artifact-root>/current
<artifact-root>/generation.lock
```

The production compiler MUST NOT introduce dual-write behavior.

`ArtifactRuntimeBooter` continues to receive explicit artifact paths.

Before generation-aware runtime selection is explicitly activated, it MUST NOT:

- read `current`;
- discover generation directories;
- select a generation;
- scan `generations/`;
- fall back from one generation to another;
- compile replacement artifacts.

The generation classes are final target infrastructure and MUST NOT be treated as disposable transitional adapters.

## Diagnostics and Redaction (MUST)

Generation diagnostics MUST NOT expose:

- artifact roots;
- generation directories;
- staging directories;
- current paths;
- lock paths;
- configured cache directory values;
- skeleton roots;
- absolute paths;
- relative paths supplied by callers;
- artifact bytes;
- raw artifact payloads;
- staging suffixes;
- OS error messages;
- throwable messages;
- stack traces;
- hostnames;
- usernames;
- process ids.

Stable reason tokens MAY identify only the rejection category.

Examples include:

```text
artifact-generation-id-invalid
artifact-generation-directory-invalid
artifact-generation-root-invalid
artifact-generation-path-invalid
artifact-generation-staging-suffix-invalid
artifact-generation-staging-suffix-generation-failed
artifact-publication-set-envelope-invalid
artifact-publication-set-fingerprint-mismatch
artifact-publication-set-bytes-invalid
```

The reason token is diagnostic identity.

The rejected value is not.

## Validation and Testing Obligations (MUST)

The test suite MUST cover at least:

### Generation id

- valid lowercase 64-character SHA-256;
- uppercase rejection;
- 63-character rejection;
- 65-character rejection;
- non-hex rejection;
- prefixed-hash rejection;
- whitespace rejection.

### Generation paths

- Unix artifact root;
- Windows drive artifact root;
- backslash normalization;
- valid UNC artifact root;
- malformed UNC rejection;
- triple-leading-separator rejection;
- duplicate-separator rejection;
- dot-segment rejection;
- parent-segment rejection;
- current path;
- generation-lock path;
- exact finalized generation layout;
- exact generation artifact paths;
- valid 32-character staging suffix;
- invalid staging suffix rejection;
- generated staging suffix domain.

### Publication set

- exact artifact identity for every envelope;
- exact schema version for every envelope;
- mixed module-manifest fingerprint rejection;
- mixed config fingerprint rejection;
- mixed container fingerprint rejection;
- invalid common generation fingerprint rejection;
- empty byte rejection;
- non-canonical byte rejection;
- deterministic exported artifact-byte order.

### Generation manifest

- exact top-level envelope;
- exact header fields;
- absence of `requires`;
- exact payload fields;
- canonical payload order;
- exact three artifact names;
- canonical artifact order;
- exact entry fields;
- canonical entry order;
- correct byte counts;
- correct SHA-256 values;
- generation id equals header fingerprint;
- no generation-manifest self-entry;
- no path-like fields;
- fourth artifact rejection;
- missing artifact rejection;
- extra entry-key rejection;
- invalid byte count rejection;
- invalid hash rejection;
- `requires` header rejection.

### Current production boundary

- the current production compiler still resolves legacy flat artifact paths;
- `generation-manifest.php` is not accepted by the flat-layout basename resolver;
- production runtime boot does not discover or select generation directories.

## Non-goals / Clarifications (MUST)

- This document does not activate the generation-aware production writer.
- This document does not define generation retention count.
- This document does not define generation garbage collection.
- This document does not define stale staging cleanup timing.
- This document does not define crash-recovery implementation.
- This document does not define operating-system lock primitives.
- This document does not define current-file byte encoding.
- This document does not define cross-process retry timing.
- This document does not define runtime generation discovery.
- This document does not redefine artifact cache directory configuration.
- This document does not redefine Bootstrap Phase A.
- This document does not redefine fingerprint input.
- This document does not redefine `module-manifest@1`.
- This document does not redefine `config@1`.
- This document does not redefine `container@1`.
- This document does not make `current` an artifact.
- This document does not make `generation.lock` an artifact.
- This document does not permit paths in generation artifacts.
- This document does not permit generation manifest extensions through `requires`.
- This document does not introduce a second generation identifier distinct from the artifact fingerprint.

## Implementation Mapping

```text
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactGenerationId.php
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactGeneration.php
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactGenerationPathResolver.php
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactGenerationManifestBuilder.php
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactGenerationManifestValidator.php
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactPublicationSet.php
framework/packages/core/kernel/src/Artifacts/ArtifactEnvelopeFactory.php
framework/packages/core/kernel/src/Artifacts/Paths/ArtifactPathResolver.php
framework/packages/core/kernel/src/Artifacts/Verifier/ArtifactSchemaValidator.php
```

These implementation points do not change this document's authority boundary.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Kernel Artifacts and Fingerprint Behavior](./artifacts-and-fingerprint.md)
- [Cache Verification Behavior](./cache-verify.md)
- [Compiled Container Payload and Artifact-Only Boot Semantics](./compiled-container.md)
- [ADR-0028: Kernel Artifacts, Fingerprint, and Cache Verification](../adr/ADR-0028-kernel-artifacts-fingerprint-cache-verify.md)
- [ADR-0029: Kernel compiled container artifact](../adr/ADR-0029-kernel-container-compile-artifact.md)
- [ADR-0031: Atomic Artifact Generations](../adr/ADR-0031-atomic-artifact-generations.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
