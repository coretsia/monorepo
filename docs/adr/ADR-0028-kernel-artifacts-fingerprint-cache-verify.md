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

## Status

Accepted.

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
docs/ssot/artifacts-and-fingerprint.md
docs/ssot/cache-verify.md
```

The key design tension is that artifact generation, fingerprint calculation, and cache verification are related but must remain separate operations.

Artifact production must write expected artifacts.

Cache verification must read existing artifacts and compare them with expected in-memory artifacts.

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
```

The `routes@1` artifact is not Kernel-owned and is not produced or verified by this Kernel artifact pipeline.

## Decision 2: Produce only Kernel-owned artifact files

Kernel artifact production materializes only the Kernel-owned PHP artifact files:

```text
module-manifest.php
config.php
container.php
```

The artifacts are materialized under the application target cache directory:

```text
<skeletonRoot>/var/cache/<appTarget>/
```

The corresponding skeleton-relative shape is:

```text
var/cache/<appTarget>/<artifact-basename>
```

The Kernel artifact path policy is owned by `ArtifactPathResolver`.

The path resolver accepts only Kernel-owned artifact basenames and must reject non-Kernel artifact basenames such as `routes.php`.

## Decision 3: Keep artifact production and cache verification separate

Kernel artifact production is owned by `ArtifactCompiler`.

Kernel cache verification is owned by `CacheVerifier`.

`ArtifactCompiler` writes expected Kernel artifacts.

`CacheVerifier` reads existing Kernel artifacts, rebuilds expected artifacts in memory, and reports cache state.

`ArtifactCompiler` must not:

- read existing generated artifacts;
- decide cache clean/dirty/invalid state;
- reuse generated artifact files;
- repair artifact files;
- start UnitOfWork;
- invoke reset orchestration.

`CacheVerifier` must not:

- write artifacts;
- repair artifacts;
- mutate existing artifact files;
- update mtimes;
- call artifact writer methods.

This separation prevents a verification operation from silently changing the state it reports.

## Decision 4: Build expected artifacts from narrow builder services

Kernel artifact payload/envelope production is split across narrow services:

```text
ModuleManifestBuilder
CompiledConfigBuilder
StubContainerBuilder
ArtifactEnvelopeFactory
StablePhpArrayDumper
ArtifactWriter
ArtifactPathResolver
```

The builders produce Kernel artifact envelopes for the Kernel-owned artifact identities.

`ArtifactEnvelopeFactory` is the Kernel service responsible for assembling Kernel artifact envelopes.

`StablePhpArrayDumper` emits deterministic PHP artifact bytes.

`ArtifactWriter` writes deterministic artifact files.

This split keeps envelope construction, byte emission, path resolution, and file writing independently testable.

## Decision 5: Use a deterministic stub for `container@1` in this epic

This epic materializes `container@1` as a deterministic stub artifact.

The stub exists to reserve and verify the Kernel-owned container artifact slot before real compiled-container implementation.

Later compiled-container work may either:

- remain compatible with `container@1`, or
- introduce a new schema version if the compiled payload is incompatible.

The stub must still use the canonical artifact envelope and deterministic serialization law.

## Decision 6: Build fingerprint input from already-resolved Kernel inputs

Fingerprint input construction is owned by `ConfigFingerprintInputBuilder`.

The builder consumes already-resolved inputs such as:

- `BootstrapConfig`;
- `ModulePlan`;
- `ConfigKernel::compile(...)` result;
- explicit config source candidate arrays;
- `EnvRepositoryInterface` source metadata;
- Kernel config subtree.

It must not resolve BootstrapConfig, resolve ModulePlan, re-run config discovery, re-run module discovery, re-run env loading, scan arbitrary package directories, scan arbitrary app targets, or enumerate arbitrary dotenv files.

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

The baseline exclusions are:

```text
var/cache
var/maintenance
```

These exclusions are skeleton-root-relative.

They prevent generated and operational skeleton-local paths from affecting Kernel artifact fingerprints.

Ignored skeleton-relative subtrees are skipped before recursive traversal and before symlink inspection.

Therefore ignored generated/operational subtrees:

- are not included in content hashes;
- are not counted as fingerprint files;
- are not traversed;
- cannot make fingerprint construction fail merely because ignored contents contain symlinks.

`DeterministicFileLister` remains policy-free. It may receive a caller-supplied skip callback, but it does not know about Kernel config, skeleton roots, or `var/cache`.

## Decision 9: Calculate fingerprint from stable normalized bytes

Fingerprint calculation is owned by `FingerprintCalculator`.

The calculator normalizes fingerprint input according to canonical json-like byte rules and calculates a deterministic digest over stable bytes.

It does not read files directly, write artifacts, resolve boot/module/config inputs, run cache verification, or expose raw fingerprint input in diagnostics.

## Decision 10: Verify cache by schema, fingerprint, and bytes only

Cache verification uses the following semantic sequence:

1. compile config for the supplied resolved inputs;
2. build deterministic fingerprint input;
3. calculate current fingerprint;
4. build expected Kernel artifact envelopes in memory;
5. dump expected deterministic artifact bytes in memory;
6. resolve expected artifact paths;
7. read existing artifacts;
8. validate existing artifact envelope/header/payload schema;
9. compare stored fingerprint to current fingerprint;
10. compare LF-normalized existing bytes to expected bytes.

Cache verification does not use mtimes, ctimes, permissions, owners, inode ids, device ids, directory entry order, or filesystem traversal order as cache semantics.

Only presence, readability/parsing, schema validity, stored fingerprint, and deterministic byte equality are semantic.

## Decision 11: Classify each artifact as clean, dirty, or invalid

Each expected artifact receives exactly one status:

```text
clean
dirty
invalid
```

An artifact is `clean` only when it exists, can be read, returns an array, emits no output, validates by schema/header/payload, stores the current fingerprint, and has byte-identical deterministic output.

An artifact is `dirty` when it is missing, stores a different fingerprint, or has deterministic byte drift.

An artifact is `invalid` when it cannot be safely accepted as a valid artifact because of unreadability, invalid PHP, emitted output, non-array return, invalid envelope, invalid header, invalid artifact name, invalid schema version, invalid payload schema, or unexpected read/schema-validation failure.

## Decision 12: Treat missing artifacts as dirty, not invalid

Missing expected artifacts are classified as:

```text
status = dirty
reason = missing
```

Missing artifacts are not invalid.

Rationale:

- cold caches may legitimately have no generated artifacts;
- regeneration is the correct remediation;
- verification reports state and does not write or repair files.

## Decision 13: Treat schema-valid fingerprint mismatch as dirty

When an existing artifact is readable and schema-valid but stores a fingerprint different from the current fingerprint, it is classified as:

```text
status = dirty
reason = fingerprint_mismatch
```

This is not invalid because the artifact is structurally valid. It is simply stale for the current logical inputs.

## Decision 14: Treat schema-valid byte mismatch as dirty

When an existing artifact is readable, schema-valid, and fingerprint-matching but its LF-normalized bytes differ from expected deterministic bytes, it is classified as:

```text
status = dirty
reason = changed
```

This is not invalid because the artifact is structurally valid. The cache has byte drift and should be regenerated by artifact production.

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

## Decision 17: Register artifact/fingerprint/cache services as factories only

`KernelServiceProvider` registers artifact, fingerprint, compiler, and verifier services as factories only.

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
- start artifact/fingerprint/cache spans;
- emit artifact/fingerprint/cache metrics;
- write artifact/fingerprint/cache logs.

## Decision 18: Keep factory methods as wiring-only construction methods

`KernelServiceFactory` owns artifact/fingerprint/cache service construction.

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

Artifact/fingerprint/cache services that emit observability receive non-null dependencies:

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

Artifact/fingerprint/cache observability must not expose raw paths, raw payloads, raw config values, raw env values, secrets, PII, raw SQL, stack traces, throwable messages, previous throwable messages, or raw fingerprint input.

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

Fingerprint calculation is explicit, safe, and independent from generated artifact files.

Cache verification can report `clean`, `dirty`, and `invalid` without mutating the cache.

Cold-cache missing artifacts are handled as dirty rather than invalid.

Invalid artifacts are separated from stale artifacts.

Provider registration remains side-effect-free.

Factory wiring remains stateless and does not decide real-vs-Noop observability.

Generated artifacts remain compatible with the global artifact envelope and deterministic serialization law.

`routes@1` remains owned by `platform/routing`, avoiding cross-owner artifact drift.

### Trade-offs

Cache verification rebuilds expected artifacts in memory instead of reusing generated files.

Verification depends on the same deterministic builders used by production.

A malformed existing artifact is invalid rather than silently ignored.

Filesystem metadata is intentionally ignored even when it could be useful for ad hoc debugging.

The `container@1` artifact is a deterministic stub until compiled-container work lands.

Observability emits only safe summaries, so raw debugging context must be obtained through controlled local investigation, not runtime logs or metrics.

## Non-goals

This ADR does not define:

- the global artifact envelope shape;
- artifact header fields;
- artifact registry rows;
- `routes@1` production;
- platform routing artifact behavior;
- a real compiled container payload;
- CLI command UX;
- command output formatting;
- automatic artifact generation during provider registration;
- automatic cache verification during provider registration;
- generated artifact repair during verification;
- filesystem mtime/permission/owner based cache semantics;
- broader package artifact ownership outside `core/kernel`.

## Related SSoT

- `docs/ssot/artifacts.md`
- `docs/ssot/artifacts-and-fingerprint.md`
- `docs/ssot/cache-verify.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/observability.md`

## Related implementation

- `framework/packages/core/kernel/src/Artifacts/ArtifactEnvelopeFactory.php`
- `framework/packages/core/kernel/src/Artifacts/ArtifactWriter.php`
- `framework/packages/core/kernel/src/Artifacts/Builders/ModuleManifestBuilder.php`
- `framework/packages/core/kernel/src/Artifacts/Builders/CompiledConfigBuilder.php`
- `framework/packages/core/kernel/src/Artifacts/Builders/StubContainerBuilder.php`
- `framework/packages/core/kernel/src/Artifacts/Compiler/ArtifactCompiler.php`
- `framework/packages/core/kernel/src/Artifacts/Fingerprint/ConfigFingerprintInputBuilder.php`
- `framework/packages/core/kernel/src/Artifacts/Fingerprint/DeterministicFileLister.php`
- `framework/packages/core/kernel/src/Artifacts/Fingerprint/FingerprintCalculator.php`
- `framework/packages/core/kernel/src/Artifacts/Fingerprint/FingerprintExplainer.php`
- `framework/packages/core/kernel/src/Artifacts/Paths/ArtifactPathResolver.php`
- `framework/packages/core/kernel/src/Artifacts/Php/PhpArtifactReader.php`
- `framework/packages/core/kernel/src/Artifacts/Php/StablePhpArrayDumper.php`
- `framework/packages/core/kernel/src/Artifacts/Verifier/ArtifactSchemaValidator.php`
- `framework/packages/core/kernel/src/Artifacts/Verifier/CacheVerifier.php`
- `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php`
- `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php`

## Related epic

- `1.330.0 Kernel: Artifacts (manifest + config) + fingerprint + cache:verify core`
