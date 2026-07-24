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

# ADR-0028: Kernel Artifacts, Fingerprint, and Cache Verification

```yaml
adrVersion: 1
status: pre-accepted
owner: core/kernel
```

Artifact location is resolved during Bootstrap Phase A and is consumed through `BootstrapConfig::artifactsCacheDir()`.

The artifact fingerprint binds the canonical compiled `DefinitionGraph` used to produce the REAL `container@1` artifact.

Kernel-produced `container@1` artifacts use the REAL compiled-container payload emitted through `ContainerCompiler` and `CompiledContainerBuilder`. Transitional stub payloads are invalid.

Kernel artifact production publishes one immutable fingerprint-addressed generation and activates it through one locked `current` pointer replacement.

`CacheVerifier` validates and compares the selected immutable generation rather than independently verifying mutable flat artifact files.

Artifact-only runtime and Worker generation-root consumption are outside this ADR and retain their existing explicit-path contracts.

## Context

Coretsia needs a deterministic Kernel-owned artifact pipeline for materializing runtime-independent cache artifacts from already-resolved Kernel inputs.

The Kernel artifact work must support:

- deterministic artifact production;
- stable artifact envelope generation;
- deterministic PHP artifact byte emission;
- fingerprint-based cache identity;
- safe fingerprint input construction;
- explicit generated/operational skeleton exclusions;
- cache verification with clean, dirty, and invalid semantics;
- provider/factory wiring that registers services without executing artifact work.

The following SSoT documents constrain this decision:

```text
docs/ssot/artifacts.md
docs/ssot/artifact-generations.md
docs/ssot/artifacts-and-fingerprint.md
docs/ssot/cache-verify.md
```

The key design tension is that artifact generation, fingerprint calculation, and cache verification are related but must remain separate operations.

Artifact production must materialize and atomically select one complete expected generation.

Cache verification must locate the selected immutable generation and compare it with the expected generation rebuilt in memory.

Fingerprint calculation must derive from safe deterministic input, not from generated artifact files, mtimes, permissions, host data, or runtime object identity.

Provider and factory registration must register services only. Registration must not compile config, resolve boot/module state, write artifacts, read artifacts, calculate fingerprints, verify cache, start UnitOfWork, invoke reset, or emit output.

## Decision 1: Use the global artifact envelope and registry as authority

Kernel artifacts use the canonical artifact envelope, header fields, deterministic serialization law, and registry rows defined by:

```text
docs/ssot/artifacts.md
```

This ADR does not redefine:

- the artifact envelope shape;
- artifact header fields;
- artifact registry rows;
- global deterministic serialization law.

The Kernel artifact identities referenced by this decision are:

```text
module-manifest@1
config@1
container@1
artifact-generation@1
```

The `routes@1` artifact is not Kernel-owned and is not produced or verified by this Kernel artifact pipeline.

## Decision 2: Publish one immutable Kernel-owned artifact generation

Kernel artifact production builds exactly three runtime artifact envelopes:

```text
module-manifest@1
config@1
container@1
```

Their canonical PHP basenames are:

```text
module-manifest.php
config.php
container.php
```

The three runtime artifacts form one immutable `ArtifactPublicationSet`.

The publication set is finalized as one generation containing exactly four files:

```text
module-manifest.php
config.php
container.php
generation-manifest.php
```

`generation-manifest.php` is the canonical `artifact-generation@1` envelope derived from the three-artifact publication set.

The artifact root remains:

```text
<skeletonRoot>/<artifactsCacheDir>/<appTarget>
```

The active generation storage layout is:

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

The package fallback remains:

```text
kernel.boot.default_artifacts_cache_dir = var/cache
```

Artifact cache directory resolution precedence remains:

1. `BootstrapInput::artifactsCacheDir()`;
2. `skeleton/config/app.php` `artifactsCacheDir`;
3. `kernel.boot.default_artifacts_cache_dir`.

The canonical resolved value remains:

```text
BootstrapConfig::artifactsCacheDir()
```

`ArtifactCompiler`, `CacheVerifier`, and `ArtifactPathResolver` MUST consume the same resolved `BootstrapConfig`.

They MUST NOT resolve artifact location from:

```text
ConfigKernel Phase B merged config
compiled config@1
kernel.artifacts.cache_dir
```

`kernel.artifacts.cache_dir` is not a supported config key.

Artifact-root ownership is split from generation-layout ownership:

```text
BootstrapArtifactsCacheDir
└─ validates the Bootstrap Phase A cache-directory domain

ArtifactPathResolver
└─ derives the final artifact root

ArtifactGenerationPathResolver
├─ generations directory
├─ staging directory
├─ finalized generation directory
├─ generation artifact paths
├─ current
└─ generation.lock
```

Production compilation MUST NOT dual-write the legacy flat layout.

Artifact-only runtime and Worker generation-root consumption are outside this decision. Their existing explicit-path runtime contracts remain unchanged.

## Decision 3: Keep artifact production and cache verification separate

Kernel artifact production orchestration is owned by `ArtifactCompiler`.

Transactional generation publication is owned by `ArtifactGenerationPublisher`.

Kernel cache verification is owned by `CacheVerifier`.

Current-generation location and validation are owned by:

```text
ArtifactGenerationLocator
ArtifactGenerationValidator
```

`ArtifactCompiler`:

1. compiles config;
2. compiles the canonical runtime container graph;
3. calculates the graph-bound fingerprint;
4. builds the three runtime envelopes;
5. dumps their canonical exact bytes;
6. constructs one `ArtifactPublicationSet`;
7. delegates filesystem publication to `ArtifactGenerationPublisher`.

`ArtifactCompiler` MUST NOT:

- independently write the three production artifact files;
- read `current`;
- locate an active generation;
- decide cache clean/dirty/invalid state;
- repair finalized generations;
- start UnitOfWork;
- invoke reset orchestration.

`ArtifactGenerationPublisher` MAY read and validate an existing finalized generation only to implement content-addressed generation reuse.

It MUST NOT use an existing generation unless:

- the finalized generation is valid;
- all four finalized files exactly match the staged generation bytes.

`CacheVerifier`:

1. rebuilds the expected publication set and generation manifest in memory;
2. locates the selected generation through `ArtifactGenerationLocator`;
3. compares generation identity and exact bytes;
4. reports deterministic cache state.

`CacheVerifier` MUST NOT:

- write artifacts;
- repair artifacts;
- mutate finalized generations;
- replace `current`;
- update mtimes;
- call artifact writer methods.

This separation prevents verification from silently changing the state it reports.

## Decision 4: Build expected artifacts and graph identity through narrow services

Kernel artifact graph compilation, fingerprint construction, payload/envelope production, byte emission, and writing are split across narrow services:

```text
RuntimeContainerGraphCompiler
ContainerCompiler
ContainerGraphCompletenessValidator
ContainerGraphFingerprintBucketBuilder
ConfigFingerprintInputBuilder
FingerprintCalculator
ModuleManifestBuilder
CompiledConfigBuilder
CompiledContainerBuilder
ArtifactEnvelopeFactory
StablePhpArrayDumper
ArtifactPublicationSet
ArtifactGenerationManifestBuilder
ArtifactGenerationManifestValidator
ArtifactGenerationValidator
ArtifactGenerationPathResolver
ArtifactGenerationLock
ArtifactGenerationPublisher
ArtifactGenerationLocator
ArtifactWriter
ArtifactPathResolver
```

`RuntimeContainerGraphCompiler` owns production provider-definition collection, ordered definition-set merging, low-level graph compilation, and final graph-completeness validation.

`ContainerCompiler` owns deterministic normalization of one ordered `ContainerDefinitionSet` into one canonical `DefinitionGraph`.

`ContainerGraphFingerprintBucketBuilder` owns construction of the safe deterministic fingerprint bucket for that graph.

`ConfigFingerprintInputBuilder` owns inclusion of the graph bucket in the complete Kernel fingerprint input.

`CompiledContainerBuilder` owns wrapping the same compiled `DefinitionGraph` payload in the canonical Kernel artifact envelope.

The artifact builders produce Kernel artifact envelopes for the Kernel-owned artifact identities.

`ArtifactEnvelopeFactory` is the Kernel service responsible for assembling Kernel artifact envelopes.

`StablePhpArrayDumper` emits deterministic PHP artifact bytes.

`ArtifactPublicationSet` binds the three canonical runtime artifact envelopes and exact byte strings to one shared generation id.

`ArtifactGenerationManifestBuilder` derives the canonical `artifact-generation@1` envelope.

`ArtifactGenerationValidator` validates one staged or finalized generation as a complete filesystem unit.

`ArtifactGenerationLock` owns persistent shared/exclusive coordination through `generation.lock`.

`ArtifactGenerationPublisher` owns transactional generation publication and the locked `current` pointer switch.

`ArtifactGenerationLocator` reads and validates the selected generation under a shared lock.

`ArtifactWriter` remains the narrow per-file primitive for exact durable writes, controlled temporary files, and same-directory replacement. It does not own cross-file publication.

`ArtifactPathResolver` owns the artifact root. `ArtifactGenerationPathResolver` owns generation-specific paths below that root.

This split keeps graph production, graph identity, fingerprint construction, envelope construction, byte emission, path resolution, and file writing independently testable.

## Decision 5: Use REAL compiled-container semantics for `container@1`

Kernel artifact production now materializes `container@1` as a REAL compiled-container artifact.

The `container@1` payload is produced from canonical provider definitions selected through one `ModuleResolution`:

```text
ModuleResolution + compiled Phase-B config
  -> RuntimeContainerGraphCompiler
      -> ContainerProviderPlanResolver
      -> ordered provider definitions
      -> ContainerDefinitionSet::merge(...)
      -> ContainerCompiler
      -> DefinitionGraph
      -> ContainerGraphCompletenessValidator
  -> CompiledContainerBuilder
  -> container@1 envelope
```

Production callers do not supply a raw descriptor iterable.

The same canonical `DefinitionGraph` produced by this flow is also bound into the artifact fingerprint before artifact envelopes are built.

The REAL `container@1` payload uses:

```text
kind = compiled
compiled = true
```

The payload contains deterministic compiled-container maps:

```text
aliases
parameters
services
tags
```

Transitional stub payloads are invalid as current `container@1` artifacts:

```text
kind = stub
compiled = false
```

The REAL compiled-container payload remains compatible with `container@1`; no `container@2` schema version is introduced by this decision.

## Decision 6: Bind the compiled container graph into fingerprint input

Fingerprint input construction is owned by `ConfigFingerprintInputBuilder`.

The builder consumes already-resolved or already-produced inputs:

- `BootstrapConfig`;
- `ModulePlan`;
- the canonical compiled runtime `DefinitionGraph`;
- `ConfigKernel::compile(...)` result;
- explicit config source candidate arrays;
- `EnvRepositoryInterface` source metadata;
- Kernel config subtree.

The supplied `DefinitionGraph` must be the exact graph returned by `RuntimeContainerGraphCompiler` for the current artifact operation.

`ConfigFingerprintInputBuilder` delegates graph identity construction to:

```text
ContainerGraphFingerprintBucketBuilder
```

The graph bucket has the following safe deterministic shape:

```php
[
    'schemaVersion' => 1,
    'sha256' => '<lowercase sha256>',
    'serviceCount' => int,
    'aliasCount' => int,
    'parameterCount' => int,
    'tagCount' => int,
]
```

`ContainerGraphFingerprintBucketBuilder` must:

1. obtain the canonical graph representation through `DefinitionGraph::toArray()`;
2. encode that representation through canonical stable JSON encoding;
3. calculate SHA-256 over the stable JSON bytes;
4. expose only bounded safe summary counts.

The summary counts mean:

- `serviceCount` — number of canonical service definitions;
- `aliasCount` — number of canonical aliases;
- `parameterCount` — number of canonical parameters;
- `tagCount` — number of canonical tag names, not the total number of tag registrations.

The raw graph is not duplicated inside fingerprint input. Fingerprint input contains only the safe graph bucket.

Provider-plan metadata and provider class names must not be added as separate fingerprint fields.

Class and factory identities already present inside canonical service definitions remain semantic graph data and are therefore covered by the graph SHA-256.

The graph bucket must not contain:

- provider instances;
- runtime service instances;
- runtime containers;
- runtime seeds;
- filesystem paths;
- artifact paths;
- source paths;
- process-specific object identity.

Any semantic graph change must change artifact fingerprint identity, including changes to:

- service class;
- factory class;
- factory method;
- service reference;
- parameter value;
- alias target;
- effective tag priority;
- shared lifecycle flag.

Two graph compilations producing the same canonical `DefinitionGraph::toArray()` value must produce the same graph bucket and the same artifact fingerprint.

`ConfigFingerprintInputBuilder` must not resolve `BootstrapConfig`, resolve `ModulePlan`, compile the container graph, re-run config discovery, re-run module discovery, re-run env loading, scan arbitrary package directories, scan arbitrary app targets, or enumerate arbitrary dotenv files.

Fingerprint input must not contain:

- raw config values;
- raw env values;
- secrets;
- absolute paths;
- timestamps;
- mtimes;
- permissions;
- owners;
- hostnames;
- process-specific bytes;
- raw SQL;
- raw payloads;
- stack traces.

Raw value influence may be represented only through safe deterministic metadata such as hashes, lengths, safe source ids, safe relative paths, safe roots, safe key paths, and safe counts.

`BootstrapConfig::artifactsCacheDir()` is an output materialization location, not semantic artifact identity.

It must not be included in the serialized Bootstrap fingerprint identity or in configured fingerprint policy solely because it is the selected output directory.

Changing only the resolved `BootstrapConfig::artifactsCacheDir()` value, while all separately fingerprinted config and source inputs remain unchanged, must not change the artifact fingerprint or generated artifact bytes.

Changing `kernel.boot.default_artifacts_cache_dir` in package config is not such a location-only change. It also changes fingerprinted config and source provenance and may therefore change the fingerprint through the normal compiled-config fingerprint path.

## Decision 7: Keep dotenv coverage derived, not duplicated

The fingerprint policy must not introduce:

```text
kernel.fingerprint.env.tracked_keys
```

Env fingerprint coverage is derived from:

- resolved BootstrapConfig values;
- canonical `kernel.env.dotenv.files` templates;
- resolved dotenv candidate names and file metadata;
- env overlay mappings;
- `EnvRepositoryInterface` source metadata.

The canonical dotenv files list remains owned by:

```text
kernel.env.dotenv.files
```

It must not be duplicated under `kernel.fingerprint.*`.

## Decision 8: Exclude generated and operational skeleton paths from fingerprint traversal

The fingerprint exclusion policy is configured through:

```text
kernel.fingerprint.skeleton_ignore_prefixes
```

The configured baseline exclusion is:

```text
var/maintenance
```

The resolved artifact cache directory is not duplicated in `kernel.fingerprint.skeleton_ignore_prefixes`.

Instead, `ConfigFingerprintInputBuilder` must add:

```text
BootstrapConfig::artifactsCacheDir()
```

as a mandatory effective traversal exclusion.

The effective traversal exclusions are:

```text
configured kernel.fingerprint.skeleton_ignore_prefixes
+ resolved BootstrapConfig::artifactsCacheDir()
```

The effective list must be normalized, deterministically sorted, and deduplicated before source traversal.

The configured exclusion policy remains part of fingerprint input.

The mandatory resolved artifact cache directory does not become fingerprint identity. Its purpose is only to prevent generated artifact output from being read as fingerprint source input.

Therefore:

```text
artifact output appears under resolved artifact cache directory
→ fingerprint unchanged

only artifactsCacheDir changes
→ fingerprint unchanged
```

Ignored skeleton-relative subtrees are skipped before recursive traversal and before symlink inspection.

Therefore ignored generated/operational subtrees:

- are not included in content hashes;
- are not counted as fingerprint files;
- are not traversed;
- cannot make fingerprint construction fail merely because ignored contents contain symlinks.

`DeterministicFileLister` remains policy-free.

It may receive a caller-supplied skip callback, but it does not know about Kernel config, skeleton roots, `BootstrapConfig::artifactsCacheDir()`, or any specific default artifact directory.

## Decision 9: Calculate fingerprint from stable normalized bytes

Fingerprint calculation is owned by `FingerprintCalculator`.

The calculator normalizes fingerprint input according to canonical json-like byte rules and calculates a deterministic digest over stable bytes.

It does not read files directly, write artifacts, resolve boot/module/config inputs, run cache verification, or expose raw fingerprint input in diagnostics.

## Decision 10: Verify one selected immutable generation

Cache verification uses the following semantic sequence:

1. compile config for the supplied resolved inputs;
2. compile one production runtime container graph through `RuntimeContainerGraphCompiler`;
3. build deterministic fingerprint input, including the container-graph bucket;
4. calculate the current graph-bound fingerprint;
5. build the expected three runtime artifact envelopes in memory;
6. dump their canonical exact bytes;
7. construct the expected `ArtifactPublicationSet`;
8. derive the expected `artifact-generation@1` envelope and exact manifest bytes;
9. locate `current` through `ArtifactGenerationLocator` under a shared generation lock;
10. classify an absent `current` pointer as missing;
11. classify an invalid pointer or invalid selected generation as invalid;
12. compare the selected generation id with the expected generation id;
13. compare the exact bytes of all four generation files;
14. return safe deterministic per-artifact results.

The selected generation MUST be validated before byte comparison.

Generation validation includes:

- finalized directory identity;
- exact required file set;
- rejection of generation-directory, parent-directory, artifact-file, and pointer symlink substitution;
- generation manifest schema;
- manifest generation id;
- declared byte lengths;
- declared SHA-256 values;
- runtime artifact names and schema versions;
- equality of all envelope fingerprints with the generation id.

The graph MUST NOT be recompiled between fingerprint construction and expected `container@1` envelope construction.

`ArtifactCompiler` and `CacheVerifier` MUST use the same semantic production sequence:

```text
compiled config
  -> container graph
  -> fingerprint input
  -> fingerprint
  -> runtime artifact envelopes
  -> ArtifactPublicationSet
```

Cache verification does not use mtimes, ctimes, permissions, owners, inode ids, device ids, directory entry order, or filesystem traversal order as cache identity.

Generation verification uses exact persisted bytes. CRLF/CR normalization MUST NOT be applied to generation byte comparison.

## Decision 11: Classify each expected generation file as clean, dirty, or invalid

The verification result contains exactly four expected generation entries:

```text
module-manifest.php
config.php
container.php
generation-manifest.php
```

Each entry receives exactly one status:

```text
clean
dirty
invalid
```

An entry is `clean` only when:

- `current` selects a valid immutable generation;
- the selected generation id equals the expected generation id;
- the persisted exact bytes equal the expected exact bytes.

An entry is `dirty` when:

- no `current` pointer exists;
- the selected valid generation id differs from the expected generation id;
- its exact persisted bytes differ from expected bytes.

An entry is `invalid` when `current` or the selected generation cannot be safely validated.

Invalid state includes:

- malformed pointer bytes;
- pointer symlink substitution;
- missing selected generation directory;
- invalid generation directory identity;
- unexpected generation files;
- missing required generation files;
- artifact-file symlink substitution;
- unreadable or invalid PHP artifacts;
- invalid generation manifest;
- byte-length mismatch;
- SHA-256 mismatch;
- artifact identity or schema mismatch;
- envelope fingerprint mismatch.

## Decision 12: Treat an absent current pointer as dirty

When `<artifact-root>/current` does not exist, all four expected generation entries are classified as:

```text
status = dirty
reason = missing
```

An absent current pointer is not invalid.

It represents a cold or not-yet-published cache state.

Verification reports the state and does not create a generation or pointer.

## Decision 13: Treat selected generation-id mismatch as dirty

When `current` selects a valid immutable generation but its generation id differs from the expected graph-bound fingerprint, all four expected generation entries are classified as:

```text
status = dirty
reason = fingerprint_mismatch
```

This state is not invalid because the selected generation is structurally and cryptographically valid.

It is stale for the supplied logical inputs.

## Decision 14: Treat exact byte mismatch as dirty

When the selected generation id equals the expected generation id but one persisted generation file differs from its expected exact bytes, that entry is classified as:

```text
status = dirty
reason = changed
```

Exact byte comparison applies to:

```text
module-manifest.php
config.php
container.php
generation-manifest.php
```

Byte normalization is not part of generation-aware verification.

A valid generation whose bytes differ from the deterministically rebuilt expected bytes is stale or drifted and must be regenerated by artifact production.

## Decision 15: Use deterministic aggregate outcome precedence

The aggregate verification outcome is one of:

```text
clean
dirty
invalid
failure
```

For a completed verification result over expected artifacts:

1. any invalid artifact makes the aggregate outcome `invalid`;
2. otherwise, any dirty or missing artifact makes the aggregate outcome `dirty`;
3. otherwise, the aggregate outcome is `clean`.

`failure` is reserved for operation failure before a normal verification result can be safely completed.

## Decision 16: Keep result data safe

Verification result data may include safe metadata:

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

Verification result data must not include:

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

## Decision 17: Register artifact/fingerprint/container-compile/generation/cache services as factories only

`KernelServiceProvider` registers artifact, fingerprint, graph-bucket, generation, compiler, and verifier services as factories only.

Generation factory registrations include:

```text
ArtifactGenerationPathResolver
ArtifactGenerationManifestBuilder
ArtifactGenerationManifestValidator
ArtifactGenerationLock
ArtifactGenerationValidator
ArtifactGenerationPublisher
ArtifactGenerationLocator
```

`ContainerGraphFingerprintBucketBuilder` and generation publication/verification orchestration services are compile-host services and MUST NOT enter the compiled runtime graph.

Registration happens after ConfigKernel Phase B service registrations and before Kernel runtime service registrations.

Provider registration must not:

- write artifacts;
- read artifacts;
- calculate fingerprints;
- run cache verification;
- resolve BootstrapConfig;
- resolve ModulePlan;
- build EnvRepositoryInterface;
- run `ConfigKernel::compile(...)`;
- invoke ResetOrchestrator;
- start UnitOfWork;
- emit stdout or stderr;
- start artifact/fingerprint/container-compile/cache spans;
- emit artifact/fingerprint/container-compile/cache metrics;
- write artifact/fingerprint/container-compile/cache logs.

## Decision 18: Keep factory methods as wiring-only construction methods

`KernelServiceFactory` owns artifact/fingerprint/container-compile/generation/cache service construction.

Artifact factory methods must be static construction/wiring methods only.

They must not:

- write files;
- read generated artifacts;
- calculate fingerprints;
- run cache verification;
- resolve bootstrap/config/module plans;
- retain the container;
- retain mutable config snapshots;
- depend on ResetOrchestrator;
- keep mutable runtime state.

## Decision 19: Wire observability through public ports only

Artifact/fingerprint/container-compile/cache services that emit observability receive non-null dependencies:

```text
TracerPortInterface
MeterPortInterface
LoggerInterface
Stopwatch
```

Observability is wired only into:

```text
ArtifactWriter
FingerprintCalculator
ContainerCompiler
CacheVerifier
```

The factory must resolve observability dependencies from public ports/interfaces only.

The factory must not instantiate `NoopLogger`, `NoopMeter`, `NoopTracer`, or other observability implementations directly.

The factory must not decide whether an observability dependency is real or Noop.

Default real-vs-Noop binding belongs to the application/foundation composition layer.

## Security and redaction

Kernel artifact, fingerprint, and cache verification code must prefer safe tokens, omission, hashes, lengths, counts, and safe relative paths over raw values.

It must not expose:

- raw config values;
- raw env values;
- dotenv values;
- credentials;
- tokens;
- private keys;
- cookies;
- authorization headers;
- request or response bodies;
- raw queue payloads;
- raw SQL;
- raw artifact payloads;
- raw fingerprint input;
- absolute paths;
- local usernames;
- hostnames;
- process ids;
- stack traces;
- throwable messages;
- PHP warning text;
- private customer data;
- PII.

Safe diagnostics may include:

```text
safe artifact names
safe basenames
safe relative paths
safe status tokens
safe reason tokens
safe counts
safe hashes
safe lengths
safe source ids
safe key paths
```

## Observability impact

This ADR introduces Kernel artifact, fingerprint, and cache verification observability boundaries.

Runtime metrics, spans, and logs must comply with:

```text
docs/ssot/observability.md
docs/ssot/artifacts-and-fingerprint.md
docs/ssot/cache-verify.md
```

Observability failures must not alter artifact writing, fingerprint calculation, or cache verification semantics.

Services that emit observability must catch observability adapter failures.

Artifact/fingerprint/container-compile/cache observability must not expose raw paths, raw payloads, raw config values, raw env values, secrets, PII, raw SQL, stack traces, throwable messages, previous throwable messages, or raw fingerprint input.

Provider registration and factory wiring must not start spans, emit metrics, or write logs.

## Runtime lifecycle impact

Artifact production and cache verification are explicit operations.

They are not part of normal provider registration.

They are not part of KernelRuntime UnitOfWork lifecycle startup.

They must not invoke ResetOrchestrator.

They must not start or complete a UnitOfWork.

Later CLI or build tooling may call these services explicitly, but the services themselves remain Kernel-owned runtime services and keep their deterministic and safety boundaries.

## Consequences

### Positive

Kernel artifacts now have a deterministic production boundary.

Artifact output location is configurable during Bootstrap Phase A without introducing a dependency on ConfigKernel Phase B.

Compiler and verifier consume the same resolved artifact directory.

Relocating artifacts by changing only the resolved Bootstrap Phase A output location does not change artifact fingerprints or deterministic artifact bytes when all separately fingerprinted config and source inputs remain unchanged.

Changing the package fallback itself remains a fingerprinted config-source change.

Fingerprint calculation is explicit, safe, and independent from generated artifact files.

The artifact fingerprint now covers the canonical compiled runtime container graph.

Semantic changes to compiled services, factories, references, parameters, aliases, tags, or lifecycle flags invalidate all Kernel artifacts through the shared artifact fingerprint.

Compiler and verifier use the same graph-production and graph-fingerprint path.

The same canonical `DefinitionGraph` is used for both fingerprint identity and the REAL `container@1` payload.

Production publication now activates one complete immutable generation through one locked `current` pointer replacement.

Concurrent compilers cannot expose a mixed three-artifact production set.

A failed publication before the successful pointer replacement leaves the previously selected generation unchanged.

Existing content-addressed generations may be reused only after full validation and exact equality with the newly staged four-file generation.

Cache verification now validates and compares the selected immutable generation, including `generation-manifest.php`.

Cache verification can report `clean`, `dirty`, and `invalid` without mutating the cache.

An absent `current` pointer is handled as dirty rather than invalid.

Invalid artifacts are separated from stale artifacts.

Provider registration remains side-effect-free.

Factory wiring remains stateless and does not decide real-vs-Noop observability.

Generated artifacts remain compatible with the global artifact envelope and deterministic serialization law.

`routes@1` remains owned by `platform/routing`, avoiding cross-owner artifact drift.

### Trade-offs

Cache verification rebuilds expected artifacts in memory instead of reusing generated files.

Publication now requires staging directories, durable writes, generation validation, a persistent process-shared lock, and pointer replacement.

Multiple immutable generations may remain on disk because successful publication intentionally preserves previous generations.

Retention, garbage collection, and stale staging cleanup policy remain separate concerns.

Artifact-only runtime and Worker generation-root consumption remain outside this ADR.

Artifact cache relocation supports only portable, bounded, `skeletonRoot`-relative output directories.

Absolute paths and output locations inside source, config, public, dependency, or repository-owned roots are rejected.

Verification depends on the same deterministic builders used by production.

Any semantic compiled-container graph change invalidates the complete Kernel-owned artifact set because all Kernel artifact envelopes share one graph-bound fingerprint.

Graph fingerprint stability therefore depends on the canonical stability of `DefinitionGraph::toArray()` and stable JSON encoding.

A malformed pointer or invalid selected generation is invalid rather than silently ignored.

Filesystem metadata is intentionally ignored even when it could be useful for ad hoc debugging.

The `container@1` artifact now uses REAL compiled-container semantics. Empty compiled graphs are valid and are represented as empty deterministic maps for `aliases`, `parameters`, `services`, and `tags`.

Observability emits only safe summaries, so raw debugging context must be obtained through controlled local investigation, not runtime logs or metrics.

## Non-goals

This ADR does not define:

- the global artifact envelope shape;
- artifact header fields;
- artifact registry rows;
- `routes@1` production;
- platform routing artifact behavior;
- absolute or external artifact cache roots;
- artifact output outside `BootstrapConfig::skeletonRoot()`;
- artifact location resolution from ConfigKernel Phase B;
- artifact location resolution from compiled `config@1`;
- provider/runtime discovery as an implicit source for compiled-container payloads;
- automatic runtime fallback when `container.php` is missing or invalid;
- CLI command UX;
- command output formatting;
- automatic artifact generation during provider registration;
- automatic cache verification during provider registration;
- generated artifact repair during verification;
- filesystem mtime/permission/owner based cache semantics;
- artifact-only runtime discovery of `current`;
- artifact-only runtime hydration from one selected generation;
- Worker child artifact-root argument semantics;
- generation retention and garbage collection;
- automatic repair of invalid finalized generations;
- broader package artifact ownership outside `core/kernel`.

## Related SSoT

- `docs/ssot/artifacts.md`
- `docs/ssot/artifact-generations.md`
- `docs/ssot/artifacts-and-fingerprint.md`
- `docs/ssot/cache-verify.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/observability.md`
