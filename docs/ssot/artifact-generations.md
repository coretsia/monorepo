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

It intentionally does not redefine Kernel fingerprint input construction, fingerprint exclusions, compiled-container payload and hydration semantics, or cache verification classification.

Those remain owned by:

```text
docs/ssot/artifacts-and-fingerprint.md
docs/ssot/cache-verify.md
```

## Goal

A single Kernel-owned SSoT defines the active immutable generation storage, publication, location, and validation model.

The model ensures that:

- one generation id identifies one complete Kernel artifact publication set;
- finalized generation contents are immutable;
- generation metadata validates exact runtime artifact bytes;
- staging randomness remains outside semantic identity;
- generation paths remain outside artifacts and diagnostics;
- mutable publication control state is not misclassified as generated artifact data;
- production publication activates one complete generation through one locked pointer replacement;
- concurrent publishers cannot expose mixed artifact outputs;
- cache verification reads one selected immutable generation;
- artifact-only runtime and proc Worker children consume one complete generation selected through `current`.

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
- active generation publication protocol;
- shared/exclusive generation lock semantics;
- current-pointer encoding and replacement semantics;
- existing-generation reuse rules;
- current generation location and validation semantics;
- production publication, verification, and runtime generation-selection boundary.

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
- generation retention count or policy;
- finalized-generation garbage collection;
- background stale-generation cleanup;
- runtime seed hydration and compiled-container construction semantics;
- Worker task lifecycle and process-supervision semantics.

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
- Production compilation MUST publish only through `ArtifactGenerationPublisher`.
- Production compilation MUST NOT dual-write the legacy flat layout.
- The selected generation MUST change only through locked replacement of `current`.
- Readers MUST locate and validate `current` while holding the shared generation lock.
- The publisher MUST finalize, reuse, and switch `current` while holding the exclusive generation lock.
- An existing generation MUST NOT be reused unless it is valid and exactly byte-identical to the staged generation.
- Cache verification MUST include all four finalized generation files.
- Artifact-only runtime MUST accept only an artifact root and select one valid generation through `current`.
- Proc Worker children MUST receive one skeleton-root-relative artifact root and MUST NOT receive independent artifact paths.

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
StablePhpArrayDumper::dumpEnvelope(envelope)
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

`ArtifactPathResolver` MUST derive the final artifact root from:

```text
BootstrapConfig::skeletonRoot()
BootstrapConfig::artifactsCacheDir()
BootstrapConfig::appTarget()->value
```

Its supported API is root-only:

```text
relativeCacheDirectory()
artifactRoot()
```

Production compilation, cache verification, artifact-only runtime boot, and Worker factory wiring MUST cross into generation-owned path resolution only through `artifactRoot()`.

Generation-specific paths MUST be resolved through:

```text
ArtifactGenerationPathResolver
ArtifactGeneration
```

`ArtifactPathResolver` MUST NOT expose:

- flat runtime artifact paths;
- individual `module-manifest.php`, `config.php`, or `container.php` helpers;
- `current` or `generation.lock` paths;
- generation-directory or staging-directory paths.

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

Filesystem identity and byte-to-file comparison are owned by `ArtifactGenerationValidator`.

## `ArtifactGenerationValidator` Boundary (MUST)

`ArtifactGenerationValidator` validates one complete staged or finalized generation.

It MUST validate:

- generation directory identity;
- the immediate `generations` parent directory;
- staging-name identity when validating staging;
- finalized basename equality with the generation id;
- absence of generation-directory and parent-directory symlink substitution;
- the exact four required generation files;
- absence of file symlink substitution;
- regular readable file semantics;
- the canonical `artifact-generation@1` envelope;
- equality of manifest `generationId` and generation id;
- exact declared runtime artifact byte lengths;
- exact runtime artifact SHA-256 values;
- expected runtime artifact names;
- expected runtime artifact schema versions;
- equality of every runtime envelope fingerprint with the generation id;
- equality of the generation-manifest envelope fingerprint with the generation id.

The exact required finalized files are:

```text
config.php
container.php
generation-manifest.php
module-manifest.php
```

No additional directory entry is allowed.

The generation manifest records hashes for exactly the three runtime artifacts and does not hash itself.

The validator MUST NOT:

- repair a generation;
- mutate a generation;
- select `current`;
- calculate a replacement fingerprint;
- expose filesystem paths in exceptions.

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

The exact pointer bytes are:

```text
<generation-id>\n
```

The file MUST contain exactly:

- 64 lowercase hexadecimal generation-id bytes;
- one final LF byte;
- no other bytes.

`current` MUST be read only while the shared generation lock is held.

`current` MUST be replaced only while the exclusive generation lock is held.

The pointer replacement source MUST be a fully written durable same-directory temporary file.

`current` MUST NOT be a symlink.

A successful `current` replacement is the publication commit point.

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

The lock file is persistent.

It MUST NOT be removed between operations.

The lock API is:

```php
public function shared(string $artifactRoot, Closure $operation): mixed;
public function exclusive(string $artifactRoot, Closure $operation): mixed;
```

Shared lock users include:

```text
ArtifactGenerationLocator
CacheVerifier through ArtifactGenerationLocator
```

The exclusive lock user is:

```text
ArtifactGenerationPublisher
```

Lock acquisition, unlock, and close failures MUST use:

```text
CORETSIA_ARTIFACT_GENERATION_PUBLISH_FAILED: lock-failed
```

The exception MUST NOT contain the artifact root, lock path, OS warning text, or previous throwable message.

## Active Publication Protocol (MUST)

`ArtifactGenerationPublisher` owns the active production publication protocol.

The protocol is:

1. receive one validated immutable `ArtifactPublicationSet`;
2. resolve one unique staging directory;
3. write `module-manifest.php` as exact bytes;
4. write `config.php` as exact bytes;
5. write `container.php` as exact bytes;
6. fully write, flush, `fsync()`, and close every runtime artifact handle;
7. derive the canonical `artifact-generation@1` envelope;
8. write `generation-manifest.php` as exact bytes;
9. fully write, flush, `fsync()`, and close its handle;
10. validate the complete staged generation;
11. acquire the exclusive generation lock;
12. resolve `generations/<generation-id>`;
13. if it exists, validate it and require exact equality of all four staged and finalized files;
14. otherwise rename staging to the finalized directory;
15. validate the finalized generation;
16. write a durable temporary `current` pointer;
17. replace `current` while the exclusive lock remains held;
18. release the lock;
19. clean handled staging and pointer-temporary state;
20. leave previous finalized generations in place.

The publication protocol MUST NOT:

- write production flat artifacts;
- dual-write flat and generation layouts;
- mutate a finalized generation;
- activate staging;
- activate an invalid generation;
- reuse an existing generation with different bytes;
- repair an invalid finalized generation in place;
- delete the previous finalized generation after a successful switch.

Existing-generation reuse requires exact equality of:

```text
module-manifest.php
config.php
container.php
generation-manifest.php
```

A matching directory name without valid and equal contents is a generation conflict.

## Publication Failure Reasons (MUST)

Publication failures use:

```text
CORETSIA_ARTIFACT_GENERATION_PUBLISH_FAILED
```

Allowed safe reasons are exactly:

```text
lock-failed
staging-create-failed
write-failed
sync-failed
generation-invalid
generation-conflict
pointer-write-failed
pointer-switch-failed
cleanup-failed
```

No failure message may contain paths, ids, temporary names, raw bytes, OS warning text, or previous throwable messages.

## Current Generation Location (MUST)

`ArtifactGenerationLocator` owns current-generation location.

It MUST:

1. acquire the shared generation lock;
2. resolve `<artifact-root>/current`;
3. reject a symlinked pointer;
4. return `null` when the pointer does not exist;
5. require one regular readable pointer file;
6. read exact pointer bytes;
7. require exactly `<64 lowercase hex>\n`;
8. construct the selected `ArtifactGeneration`;
9. validate the complete selected generation;
10. return it only after validation succeeds.

It MUST NOT:

- repair `current`;
- repair generations;
- fall back to another generation;
- scan for a newest generation;
- select staging;
- compile replacement artifacts.

## Production Publication, Verification, and Runtime Consumption Boundary (MUST)

Production compilation writes only immutable generations.

Production cache verification reads only the generation selected through `current`.

Artifact-only runtime boot receives an explicit skeleton root from its runtime host and exactly one artifact-location input, the artifact root, then consumes one validated generation selected through `current`.

The active production boundary is:

```text
ArtifactCompiler
  -> ArtifactGenerationPublisher
  -> immutable generation
  -> current

CacheVerifier
  -> ArtifactGenerationLocator
  -> ArtifactGenerationValidator
  -> selected immutable generation
  -> expected identity and exact-byte comparison

ArtifactRuntimeBooter
  -> ArtifactRuntimeInput.artifactRoot
  -> ArtifactGenerationLocator
  -> selected immutable generation
  -> exact read of all four generation files
  -> runtime hydration

WorkerChildCommandBuilder
  -> --coretsia-worker-artifact-root=<relative-safe-path>
  -> PcntlWorkerProcessDriver | ProcWorkerProcessDriver
  -> bin/coretsia-worker
  -> fresh child ArtifactRuntimeBooter
  -> current generation selection
```

Production compilation MUST NOT create:

```text
<artifact-root>/module-manifest.php
<artifact-root>/config.php
<artifact-root>/container.php
```

as active flat production artifacts.

Artifact-only runtime and Worker children MUST NOT accept individual artifact paths.

`ArtifactRuntimeBooter` MUST NOT:

- scan `generations/` for a newest candidate;
- fall back to another generation;
- select a staging directory;
- repair `current`;
- repair a finalized generation;
- combine files from multiple generations.

Detailed runtime seed hydration and compiled-container construction remain owned by `docs/ssot/compiled-container.md`.

Detailed Worker child parsing and process lifecycle remain owned by `docs/architecture/worker.md`.

The generation classes are production infrastructure governed by this SSoT.

### Recycled process-child generation selection (MUST)

Every PCNTL and proc child spawn MUST perform an independent artifact-only runtime boot.

This rule applies to:

```text
initial process-child spawn
supervisor-owned replacement spawn after max-request exit
```

For every spawn, `WorkerChildCommandBuilder` MUST pass exactly one artifact-location argument:

```text
--coretsia-worker-artifact-root=<relative-safe-path>
```

This restriction applies only to artifact-location inputs.

The driver MAY additionally pass bounded process-bootstrap and readiness arguments for:

```text
worker index
worker count
max requests
task type
resolved process driver
readiness port
readiness token
```

It MUST NOT pass independent module-manifest, config, container, generation-directory, or individual artifact-file paths.

The child MUST:

1. resolve the supplied artifact root against its explicit skeleton root;
2. locate `current`;
3. validate the selected finalized generation;
4. read exact snapshots of all four generation files;
5. hydrate the runtime container from that generation;
6. resolve and validate Worker runtime services;
7. emit the exact internal readiness frame;
8. enter `ApplicationWorker`.

A recycled process child MUST NOT inherit:

- the previous child’s selected generation id;
- the previous child’s artifact file handles;
- previously read artifact bytes;
- the previous child’s runtime container;
- the previous child’s readiness state;
- the previous child’s generation-local runtime state.

Worker slot continuity does not pin artifact generation identity.

If `current` changes between two child generations, the replacement child MUST boot the generation selected by `current` at replacement startup.

If the selected generation is missing, invalid, incomplete, or fails runtime boot, the replacement MUST fail readiness and the supervisor MUST apply its deterministic child-failure policy.

The proc process host MUST NOT cache or select artifact generations on behalf of children. The PCNTL forked child MUST replace the supervisor process image before artifact selection or runtime-container construction.

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
lock-failed
staging-create-failed
write-failed
sync-failed
generation-invalid
generation-conflict
pointer-write-failed
pointer-switch-failed
cleanup-failed
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

### Publication protocol

- first runtime artifact write failure;
- second runtime artifact write failure;
- third runtime artifact write failure;
- generation-manifest write failure;
- durable flush failure;
- durable sync failure;
- final-directory rename failure;
- current-pointer temporary write failure;
- current-pointer switch failure;
- exclusive-lock acquisition failure;
- previous `current` remains selected for failures before pointer commit;
- previous finalized generation remains valid;
- incomplete generation is never selected;
- handled staging state is removed;
- mixed generation outputs are absent.

### Existing-generation reuse

- valid identical generation reuse;
- exact equality of all four generation files;
- same generation id with different runtime bytes is rejected;
- same generation id with different manifest bytes is rejected;
- invalid existing generation is rejected as a conflict.

### Locator and validator

- exact `<64 lowercase hex>\n` pointer acceptance;
- malformed pointer rejection;
- pointer symlink rejection;
- generation-directory symlink rejection;
- `generations` parent symlink rejection;
- artifact-file symlink rejection;
- unexpected file rejection;
- missing required file rejection;
- manifest hash mismatch rejection;
- manifest byte-length mismatch rejection;
- artifact fingerprint mismatch rejection.

### Production publication and verification boundary

- production compiler does not write active flat artifacts;
- active state changes through `current`;
- cache verification includes all four finalized generation files;
- concurrent publishers never expose mixed artifact generations;
- artifact-only runtime selects `current` through `ArtifactGenerationLocator`;
- artifact-only runtime validates and consumes all four files from one selected generation;
- Worker child input contains one artifact root and no individual artifact paths;
- each recycled process child performs a fresh `current` lookup;
- replacement children consume one complete independently validated generation;
- replacement children do not inherit the previous process generation’s artifact snapshots;
- the proc process host does not cache or select artifact generations.

## Non-goals / Clarifications (MUST)

- This document does not define generation retention count or policy.
- This document does not define finalized-generation garbage collection.
- This document does not define crash recovery beyond handled-operation semantics.
- This document does not define cross-process retry timing.
- This document does not define background stale-staging cleanup.
- This document does not redefine runtime seed hydration or compiled-container construction.
- This document does not redefine Worker task execution, supervision, or control-channel lifecycle.
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
framework/packages/core/kernel/src/Artifacts/ArtifactWriter.php
framework/packages/core/kernel/src/Artifacts/Compiler/ArtifactCompiler.php
framework/packages/core/kernel/src/Artifacts/Exception/ArtifactGenerationPublishException.php
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactGeneration.php
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactGenerationId.php
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactGenerationLock.php
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactGenerationLocator.php
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactGenerationManifestBuilder.php
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactGenerationManifestValidator.php
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactGenerationPathResolver.php
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactGenerationPublisher.php
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactGenerationValidator.php
framework/packages/core/kernel/src/Artifacts/Generation/ArtifactPublicationSet.php
framework/packages/core/kernel/src/Artifacts/Paths/ArtifactPathResolver.php
framework/packages/core/kernel/src/Artifacts/Php/PhpArtifactReader.php
framework/packages/core/kernel/src/Artifacts/Verifier/ArtifactSchemaValidator.php
framework/packages/core/kernel/src/Artifacts/Verifier/CacheVerifier.php
framework/packages/core/kernel/src/Boot/ArtifactRuntimeBooter.php
framework/packages/core/kernel/src/Boot/ArtifactRuntimeInput.php
framework/packages/core/kernel/src/Boot/Exception/ArtifactRuntimeBootException.php
framework/packages/platform/worker/bin/coretsia-worker
framework/packages/platform/worker/src/Process/Driver/ProcWorkerProcessDriver.php
framework/packages/platform/worker/src/Process/Proc/WorkerProcProcessHostClient.php
framework/packages/platform/worker/src/Provider/WorkerServiceFactory.php
framework/packages/core/kernel/src/Provider/KernelServiceFactory.php
framework/packages/core/kernel/src/Provider/KernelServiceProvider.php
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
