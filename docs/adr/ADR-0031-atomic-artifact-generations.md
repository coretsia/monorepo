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

# ADR-0031: Atomic Artifact Generations

```yaml
adrVersion: 1
status: pre-accepted
owner: core/kernel
```

## Context

Coretsia produces three Kernel-owned runtime artifacts:

```text
module-manifest.php
config.php
container.php
```

The three artifact envelopes share one deterministic artifact fingerprint.

That shared fingerprint binds the selected module plan, compiled config, canonical compiled runtime container graph, and other safe deterministic fingerprint inputs into one logical artifact-set identity.

Publishing those artifacts as independent files directly below the resolved artifact root cannot make the complete three-file set atomically visible.

Independent per-file atomic writes can expose a mixed generation after interruption or concurrent publication.

The active storage model therefore uses immutable artifact generations.

Production compilation now:

- builds the three canonical runtime artifacts in memory;
- creates one `ArtifactPublicationSet`;
- writes one complete staging generation;
- validates the staged generation;
- finalizes or reuses one fingerprint-addressed immutable generation;
- selects it through one locked `current` pointer replacement.

Production cache verification now locates and validates the selected generation under a shared lock.

The active production layout is:

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

The three runtime artifact envelopes continue to share one deterministic artifact fingerprint.

That fingerprint remains the generation id and binds the selected module plan, compiled config, canonical compiled runtime container graph, and other safe deterministic fingerprint inputs into one logical artifact-set identity.

This decision governs active production publication, cache verification, artifact-only runtime selection, and proc Worker generation-root handoff.

Artifact-only runtime and Worker children consume the same authoritative generation layout and MUST NOT accept independent artifact paths.

## Decision

### Decision 1: A generation id is the shared artifact fingerprint

Coretsia defines one immutable generation identifier:

```text
generation id = artifact fingerprint
```

The generation id MUST be exactly:

```text
lowercase 64-character SHA-256
```

Its canonical lexical domain is:

```text
/\A[a-f0-9]{64}\z/
```

The generation id MUST NOT:

- use an uppercase representation;
- include a prefix such as `sha256:`;
- include whitespace;
- be shortened;
- be expanded;
- be normalized from an invalid representation;
- be calculated from artifact paths;
- include staging randomness.

All Kernel artifacts in one publication set MUST expose the same fingerprint.

### Decision 2: One immutable publication set contains exactly three runtime artifacts

The Kernel generation publication set contains exactly:

```text
module-manifest.php
config.php
container.php
```

The in-memory publication set contains:

```text
generation id / fingerprint
canonical module-manifest.php bytes
canonical config.php bytes
canonical container.php bytes
```

Before the publication set is accepted:

- `module-manifest.php` MUST be a valid `module-manifest@1` envelope;
- `config.php` MUST be a valid `config@1` envelope;
- `container.php` MUST be a valid `container@1` envelope;
- all three envelope fingerprints MUST be identical;
- the common fingerprint MUST be a valid generation id;
- each byte string MUST exactly equal the canonical deterministic PHP emission of its corresponding envelope.

The generation manifest is not part of this three-artifact publication input.

It is derived from that publication set.

### Decision 3: Finalized generations use fingerprint-addressed directories

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

The finalized generation directory is:

```text
<artifact-root>/generations/<generation-id>
```

Its basename MUST exactly equal the generation id.

Its parent basename MUST exactly equal:

```text
generations
```

A finalized generation directory contains exactly four generation artifacts:

```text
module-manifest.php
config.php
container.php
generation-manifest.php
```

The generation directory name is content-addressed by the shared artifact fingerprint.

### Decision 4: Finalized generations are immutable

After a generation has been finalized under:

```text
generations/<generation-id>/
```

its artifact files MUST NOT be modified in place.

A publisher MUST NOT:

- rewrite one artifact inside a finalized generation;
- replace one artifact inside a finalized generation;
- append metadata to one artifact;
- mutate `generation-manifest.php`;
- reuse the same generation id for different artifact bytes;
- place temporary files inside a finalized generation;
- repair a finalized generation in place.

If the logical artifact inputs change, the shared fingerprint changes and publication targets another generation directory.

If the logical artifact inputs do not change, canonical generation artifact bytes remain identical.

### Decision 5: Staging directories are operational and non-semantic

Before finalization, publication uses a staging directory with this shape:

```text
<artifact-root>/generations/.staging-<generation-id>-<random-suffix>
```

The random suffix is:

```text
32 lowercase hexadecimal characters
```

It is generated from:

```text
16 random bytes
```

The random suffix exists only to prevent staging-name collisions.

It MUST NOT enter:

- generation id calculation;
- artifact fingerprint input;
- an artifact envelope;
- an artifact payload;
- generated artifact bytes;
- generation manifest metadata;
- diagnostics;
- observability labels.

Two staging attempts for the same generation may use different staging suffixes while producing identical finalized generation bytes.

### Decision 6: Introduce `artifact-generation@1`

Coretsia introduces one Kernel-owned artifact identity:

```text
artifact-generation@1
```

Its canonical PHP basename is:

```text
generation-manifest.php
```

Its owner is:

```text
core/kernel
```

Its envelope MUST use the global canonical artifact envelope.

Its header MUST use:

```text
name = artifact-generation
schemaVersion = 1
fingerprint = <generation-id>
generator = core/kernel/artifacts
```

`artifact-generation@1` has no `requires` extension point.

The header MUST NOT contain:

```text
requires
```

The generation manifest payload is:

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

The payload records the exact canonical bytes of the three runtime artifacts.

The generation manifest MUST NOT include an entry for itself.

This avoids a circular byte-hash dependency.

### Decision 7: Generation metadata is exact and path-free

The `artifacts` map contains exactly:

```text
config.php
container.php
module-manifest.php
```

Every artifact entry contains exactly:

```text
bytes
sha256
```

`bytes` is the positive byte length of the canonical PHP artifact bytes.

`sha256` is the lowercase 64-character SHA-256 of those exact bytes.

The payload generation id MUST equal:

```text
_meta.fingerprint
```

No generation manifest field may contain:

- artifact root;
- generation directory;
- staging directory;
- absolute path;
- skeleton-relative path;
- configured cache directory;
- source path;
- temporary path;
- lock path;
- current-pointer path;
- hostname;
- username;
- process id;
- timestamp;
- filesystem metadata.

### Decision 8: `current` is a cache-control file, not an artifact

The path:

```text
<artifact-root>/current
```

is a cache-control file.

It is not:

- an artifact envelope;
- an artifact registry entry;
- part of `artifact-generation@1`;
- part of generation fingerprint identity;
- part of generation artifact bytes;
- stored inside a finalized generation directory.

Its semantic role is to select one finalized generation id.

It MUST NOT select a generation through an absolute or relative filesystem path.

Its exact bytes are:

```text
<64 lowercase hexadecimal generation id>\n
```

The pointer MUST contain exactly 65 bytes:

- exactly 64 lowercase hexadecimal characters;
- exactly one final LF byte;
- no prefix;
- no additional whitespace;
- no additional newline;
- no path.

Readers MUST read and validate `current` while holding the shared generation lock.

The publisher MUST replace `current` while holding the exclusive generation lock.

The replacement source MUST be a fully written same-directory temporary file whose handle has passed full write, `fflush()`, `fsync()`, and close checks.

The successful pointer replacement is the generation publication commit point.

### Decision 9: `generation.lock` is a cache-control file, not an artifact

The path:

```text
<artifact-root>/generation.lock
```

is a cache-control file used by generation publication coordination.

It is not:

- an artifact envelope;
- an artifact registry entry;
- part of `artifact-generation@1`;
- part of generation fingerprint identity;
- part of generation artifact bytes;
- stored inside a finalized generation directory.

Lock-file contents, filesystem metadata, and operating-system lock state are operational state.

They MUST NOT affect:

- generation id;
- artifact fingerprint;
- artifact bytes;
- generation manifest bytes;
- cache semantic identity.

`generation.lock` is persistent cache-control state.

It MUST NOT be deleted between operations.

The lock API is:

```php
public function shared(string $artifactRoot, Closure $operation): mixed;
public function exclusive(string $artifactRoot, Closure $operation): mixed;
```

Lock policy is:

- `ArtifactGenerationLocator` and generation-aware verification use the shared lock;
- `ArtifactGenerationPublisher` uses the exclusive lock for finalization, reuse, and pointer replacement;
- lock acquisition, unlock, or close failure uses deterministic `lock-failed`;
- lock exceptions MUST NOT expose the lock path, artifact root, operating-system warning text, or previous throwable messages.

### Decision 10: Artifact-root ownership remains in `ArtifactPathResolver`

`ArtifactPathResolver` remains responsible for deriving the Kernel artifact root from resolved Bootstrap Phase A state.

Generation-specific paths are delegated to:

```text
ArtifactGenerationPathResolver
```

The ownership split is:

```text
ArtifactPathResolver
└─ artifact root

ArtifactGenerationPathResolver
├─ generations directory
├─ finalized generation directory
├─ generation artifact paths
├─ staging directory
├─ current
└─ generation.lock
```

Generation path resolution MUST NOT:

- read config;
- resolve Bootstrap defaults;
- read source files;
- write files;
- select the current generation;
- calculate fingerprints;
- parse artifacts;
- validate artifact payloads.

### Decision 11: Paths remain runtime state outside artifact identity

Generation paths are normalized runtime filesystem state.

Returned generation paths use:

```text
/
```

as the deterministic separator representation.

Supported canonical root forms include:

```text
/path/to/artifacts
C:/path/to/artifacts
//server/share/path/to/artifacts
```

Path validation rejects:

- empty paths;
- leading or trailing whitespace;
- control characters;
- URL-like values;
- malformed drive prefixes;
- malformed UNC roots;
- triple-leading separators;
- duplicate separators;
- `.` segments;
- `..` segments;
- path traversal;
- paths exceeding the generation path bound.

Trailing `/` separators are normalized away except for the Unix root `/` and Windows drive roots such as `C:/`.

Exception diagnostics MUST use stable reason tokens.

They MUST NOT expose supplied or normalized paths.

### Decision 12: Atomic generation publication is the active production writer model

`ArtifactGenerationPublisher` owns the complete publication transaction.

The publication sequence is:

1. create one unique staging directory under `generations/`;
2. write `module-manifest.php`;
3. write `config.php`;
4. write `container.php`;
5. fully flush and `fsync()` each artifact file handle;
6. derive and write `generation-manifest.php`;
7. fully flush and `fsync()` the manifest file handle;
8. validate the complete staged generation;
9. acquire the exclusive generation lock;
10. resolve the final fingerprint-addressed generation;
11. if it already exists, validate it;
12. require exact equality between all four existing and staged generation files;
13. otherwise rename staging to the finalized generation directory;
14. validate the finalized generation;
15. write an exact temporary `current` pointer;
16. flush and `fsync()` the pointer file handle;
17. replace `current` while the exclusive lock remains held;
18. clean remaining staging or temporary state;
19. preserve all previously finalized generations.

A staged generation MUST NOT become selectable before successful complete validation.

A publisher MUST NOT reuse an existing generation solely because its directory name matches the generation id.

Reuse requires:

- successful generation validation;
- exact equality of `module-manifest.php`;
- exact equality of `config.php`;
- exact equality of `container.php`;
- exact equality of `generation-manifest.php`.

A conflicting or invalid existing generation MUST fail with `generation-conflict`.

### Decision 13: Production compiler, verifier, artifact-only runtime, and Worker use generations

`ArtifactCompiler` MUST publish only through `ArtifactGenerationPublisher`.

It MUST NOT independently write production flat artifacts.

`CacheVerifier` MUST:

- rebuild the expected publication set and manifest in memory;
- locate `current` through `ArtifactGenerationLocator`;
- validate the selected generation;
- compare generation identity;
- compare all four exact byte sequences.

`ArtifactRuntimeBooter` MUST:

- receive `skeletonRoot` and one artifact root through `ArtifactRuntimeInput`, with no individual artifact paths;
- locate `current` through `ArtifactGenerationLocator`;
- require one valid selected generation;
- read exact bytes and envelopes for all four generation files;
- validate generation manifest metadata and all envelope fingerprints;
- hydrate runtime state only from that selected generation;
- build the container from the already-read `container@1` envelope.

`ProcWorkerProcessDriver` MUST provide the proc Worker launcher with exactly one skeleton-root-relative artifact-location argument:

```text
--coretsia-worker-artifact-root=<relative-safe-path>
```

It MUST NOT provide independent module-manifest, config, or container artifact paths.

This restriction applies only to artifact-location inputs.

Bounded child-bootstrap and readiness arguments remain separate process inputs.

The production path MUST NOT dual-write or consume the legacy flat layout.

Every proc child spawn performs an independent artifact-generation selection.

This includes a replacement child created by supervisor-owned max-request recycle.

A recycled proc child MUST:

```text
receive exactly one artifact-location input
-> locate current
-> validate the selected immutable generation
-> hydrate runtime state from that generation
-> emit readiness
-> enter the task loop
```

A replacement child MUST NOT inherit the previous child’s selected generation, runtime container, artifact snapshots, or generation-local state.

If `current` changes between the original child and its replacement, the replacement boots the generation selected by `current` at replacement startup.

The generation publication, location, validation, and runtime-selection classes are production infrastructure governed by this ADR.

## Consequences

### Positive consequences

- One generation id identifies one complete Kernel artifact set.
- The generation directory is content-addressed by the existing shared artifact fingerprint.
- Finalized generation files are immutable.
- Production publication switches a complete artifact set through one locked pointer replacement.
- Concurrent compilers cannot expose mixed artifact outputs through `current`.
- `generation-manifest.php` records exact byte length and SHA-256 metadata for every runtime artifact.
- The generation manifest remains free of paths and environment-specific data.
- Staging randomness cannot change deterministic artifact identity or bytes.
- Existing identical generations are reused only after validation and exact byte equality.
- Previous finalized generations remain available after later publications.
- `current` and `generation.lock` remain operational cache-control state rather than artifact schemas.
- Cache verification reads one selected immutable generation rather than three independently mutable files.
- The global artifact envelope and registry remain authoritative.
- Kernel artifact-root ownership remains separate from generation-layout ownership.
- Artifact-only runtime and proc Worker children consume one validated generation selected through `current`.

### Trade-offs

- Publication requires directory-level orchestration rather than three independent writes.
- Publication requires durable exact-byte writes and validation before activation.
- Publication requires shared/exclusive cross-process coordination.
- Disk usage may include multiple immutable generations.
- Retention and garbage-collection policy require separate design.
- Stale staging cleanup beyond the current operation requires separate policy.
- The generation manifest introduces a fourth generated file that must be validated and compared.
- Strict path and symlink validation rejects filesystem layouts that a permissive writer might otherwise accept.
- Artifact-only runtime performs an additional exact consumed-snapshot read after location so the bytes used for hydration are the bytes validated by runtime.

### Operational consequences

The production compiler now writes only the generation layout.

The production verifier now verifies only the generation selected by `current`.

The production boundary is:

```text
ArtifactCompiler
  -> publishes immutable generations
  -> switches current

CacheVerifier
  -> locates and compares the selected generation

ArtifactRuntimeBooter
  -> receives artifact root
  -> locates current
  -> consumes one validated generation

ProcWorkerProcessDriver
  -> passes one skeleton-root-relative artifact root for every spawn
  -> child invokes ArtifactRuntimeBooter
  -> child selects and validates current
```

A successful publication leaves previous finalized generations in place.

A failed operation before successful `current` replacement MUST NOT activate an incomplete generation.

Finalized generation contents remain immutable after selection.

## Rejected Alternatives

### Alternative 1: Continue overwriting the three flat artifact files

Rejected.

Independent file replacement cannot make the complete three-file artifact set atomically visible.

A process interruption can expose artifacts from different fingerprints.

### Alternative 2: Rely only on per-file atomic rename

Rejected.

Per-file atomic rename protects each file from partial bytes.

It does not protect the cross-file generation invariant.

### Alternative 3: Put the fingerprint in each filename

Example:

```text
module-manifest.<fingerprint>.php
config.<fingerprint>.php
container.<fingerprint>.php
```

Rejected.

This duplicates generation identity across every filename, weakens generation-directory ownership, complicates selection, and does not provide one canonical generation manifest location.

### Alternative 4: Include `generation-manifest.php` in its own artifact map

Rejected.

The manifest would need to record the hash of bytes that themselves contain that hash.

That creates a circular byte-identity dependency.

The generation manifest therefore records exactly the three runtime artifacts and not itself.

### Alternative 5: Include artifact paths in `artifact-generation@1`

Rejected.

Paths are output materialization state.

Including them would make artifact bytes environment-dependent and would allow relocation to change generation metadata.

### Alternative 6: Include staging randomness in generation identity

Rejected.

The same logical artifact set must always have the same generation id and generated bytes.

Randomness is collision-avoidance state only.

### Alternative 7: Register `current` as an artifact

Rejected.

`current` is mutable selection state.

It does not describe immutable generated runtime data and must not use the artifact envelope.

### Alternative 8: Register `generation.lock` as an artifact

Rejected.

The lock is publication-coordination state.

Its contents and filesystem state are operational and non-semantic.

### Alternative 9: Allow runtime callers to supply individual artifact paths

Rejected.

Independent module-manifest, config, and container path inputs would allow callers to construct a mixed-generation runtime set.

The accepted runtime boundary receives one artifact-root input instead of independent artifact-file paths and selects one complete generation through `current`.

### Alternative 10: Dual-write both storage layouts

Rejected.

Dual writing would create two competing production storage models and unclear cache authority.

Production publication must have one explicit authoritative storage model.

## Validation and Testing Expectations

The implementation must prove at least:

- generation ids accept exactly lowercase 64-character SHA-256 values;
- uppercase generation ids are rejected;
- 63-character and 65-character generation ids are rejected;
- non-hex generation ids are rejected;
- Unix artifact roots resolve deterministically;
- Windows drive roots normalize to `/` separators;
- canonical UNC roots normalize deterministically;
- malformed UNC roots are rejected;
- finalized generation paths use the exact generation id;
- staging paths use a 32-character lowercase hexadecimal random suffix;
- `current` resolves directly below the artifact root;
- `generation.lock` resolves directly below the artifact root;
- publication sets reject every mixed-fingerprint position;
- publication sets reject byte strings that do not match canonical envelope bytes;
- the generation manifest has the exact top-level envelope;
- the generation manifest header has no `requires`;
- generation payload keys use canonical byte-order;
- artifact names use canonical byte-order;
- a fourth artifact is rejected;
- a missing artifact is rejected;
- an additional artifact-entry field is rejected;
- path-like fields are rejected;
- payload generation id must equal the envelope fingerprint;
- production compilation does not write the flat layout;
- publication writes all three runtime artifacts and the generation manifest before pointer activation;
- every staged file is written as exact bytes with durable flush/sync semantics;
- staged generations are validated before finalization;
- invalid or incomplete staged generations are rejected;
- identical existing generations are validated and reused;
- an existing generation with the same id but different bytes is rejected;
- `current` accepts exactly `<64 lowercase hex>\n`;
- malformed, unreadable, or symlinked pointers are rejected;
- selected generation symlinks are rejected;
- selected generation hash or byte-length mismatch is rejected;
- shared readers and exclusive publishers cannot expose mixed generations;
- failed publication before the pointer commit leaves the previous `current` selected;
- staging and pointer temporary files are removed after handled failures;
- cache verification includes `generation-manifest.php`;
- cache verification does not repair invalid generations;
- artifact-only runtime accepts only an artifact root and selects `current` through `ArtifactGenerationLocator`;
- artifact-only runtime validates and consumes all four files from one selected generation;
- every proc Worker child receives one artifact-root argument and cannot receive independent artifact paths;
- every recycled proc child performs a fresh `current` lookup and boots one independently validated generation;
- a recycled proc child does not inherit the previous child’s selected generation or runtime artifact snapshots.

## Related SSoT

- `docs/ssot/artifact-generations.md`
- `docs/ssot/artifacts.md`
- `docs/ssot/artifacts-and-fingerprint.md`
- `docs/ssot/cache-verify.md`
- `docs/ssot/compiled-container.md`
- `docs/ssot/runtime-container-definitions.md`

## Related ADRs

- `docs/adr/ADR-0017-persistent-worker-supervisor-application-worker.md`
- `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`
- `docs/adr/ADR-0028-kernel-artifacts-fingerprint-cache-verify.md`
- `docs/adr/ADR-0029-kernel-container-compile-artifact.md`
- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md`
