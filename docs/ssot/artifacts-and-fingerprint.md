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

# Artifacts and Fingerprint Behavior (SSoT)

This document is the canonical SSoT for Kernel-owned artifact production behavior, deterministic fingerprint input behavior, fingerprint exclusions, and cache verification linkage.

It intentionally does not redefine the global artifact envelope, artifact header fields, deterministic serialization law, or artifact registry rows. Those are owned by `docs/ssot/artifacts.md`.

## Goal

A single Kernel-owned SSoT defines how `core/kernel` produces, fingerprints, writes, reads, validates, and verifies Kernel-owned artifacts without duplicating the global artifact registry or envelope law.

## Authority Boundary (MUST)

This document owns only Kernel-side behavior for:

- Kernel artifact production orchestration;
- Kernel artifact output path policy;
- Kernel artifact builder responsibilities;
- Kernel fingerprint input construction;
- Kernel fingerprint exclusion policy;
- Kernel cache verification linkage;
- Kernel artifact/fingerprint/cache service wiring constraints.

This document **MUST NOT** redefine:

- the canonical artifact envelope shape;
- canonical artifact header fields;
- canonical artifact registry rows;
- global deterministic serialization law;
- global observability metric catalog or label allowlist;
- ownership of non-Kernel artifacts such as `routes@1`.

Those rules remain owned by their canonical SSoT documents.

## Invariants (MUST)

- Kernel-owned artifacts **MUST** remain compatible with the global artifact envelope and deterministic serialization law defined by `docs/ssot/artifacts.md`.
- Kernel-owned artifact identities **MUST** use the canonical registry entries from `docs/ssot/artifacts.md`.
- Kernel artifact production **MUST NOT** introduce alternative envelope forms.
- Kernel artifact production **MUST NOT** redefine header semantics.
- Kernel artifact production **MUST NOT** add registry rows in this document.
- Kernel artifact production **MUST NOT** produce artifacts owned by other packages.
- `routes@1` **MUST NOT** be produced by `core/kernel`; it is owned by `platform/routing`.
- Kernel artifacts **MUST** be deterministic and rerun-no-diff for the same logical inputs.
- Kernel artifacts **MUST NOT** embed timestamps, absolute paths, hostnames, usernames, process ids, raw env values, secrets, PII, raw payloads, raw SQL, stack traces, mtimes, permissions, owners, or filesystem-order-dependent bytes.
- Kernel fingerprint input **MUST** be safe, deterministic, and derived only from already-resolved Kernel inputs.
- Kernel cache verification **MUST** compare deterministic expected artifacts against existing artifacts without mutating artifact files.

## Kernel-Owned Artifact Set (MUST)

This document may reference Kernel-owned artifact identities already defined by `docs/ssot/artifacts.md`:

- `module-manifest@1`
- `config@1`
- `container@1`

This document does not redefine their registry rows.

Kernel artifact production in this epic materializes the following PHP artifact basenames:

- `module-manifest.php`
- `config.php`
- `container.php`

The basename `routes.php` is intentionally not a Kernel artifact basename.

## Artifact Output Path Policy (MUST)

Kernel artifact output paths are derived from:

- `BootstrapConfig::skeletonRoot()`;
- `BootstrapConfig::appTarget()->value`;
- `kernel.artifacts.cache_dir`;
- the canonical Kernel artifact basename.

Kernel-owned artifact files **MUST** be materialized under:

```text
<skeletonRoot>/var/cache/<appTarget>/
```

Kernel artifact relative paths therefore use this shape:

```text
var/cache/<appTarget>/<artifact-basename>
```

Examples:

```text
var/cache/web/module-manifest.php
var/cache/web/config.php
var/cache/web/container.php
```

### `kernel.artifacts.cache_dir` Policy (MUST)

The `kernel.artifacts.cache_dir` value:

- **MUST** be `BootstrapConfig::skeletonRoot()`-relative;
- **MUST** be a relative-safe path;
- **MUST NOT** be absolute;
- **MUST NOT** contain `..`;
- **MUST NOT** contain a `skeleton/` prefix;
- **MUST NOT** contain host-specific or monorepo-only path fragments;
- **MUST NOT** be used to relocate Kernel artifacts outside the canonical `var/cache` subtree in this epic.

The baseline value is:

```text
var/cache
```

## Artifact Production Responsibilities (MUST)

Kernel artifact production is split into narrow services.

### `ArtifactCompiler`

`ArtifactCompiler` owns Kernel artifact production orchestration.

It **MUST**:

- call `ConfigKernel` once for the supplied compile inputs;
- build the deterministic fingerprint input through `ConfigFingerprintInputBuilder`;
- calculate the fingerprint through `FingerprintCalculator`;
- build expected Kernel artifact envelopes through Kernel artifact builders;
- resolve Kernel artifact paths through `ArtifactPathResolver`;
- write Kernel artifacts through `ArtifactWriter`;
- return only safe summary data.

It **MUST NOT**:

- read existing generated artifacts;
- decide cache clean/dirty state;
- reuse existing artifact files;
- emit stdout or stderr;
- expose raw config values, raw env values, secrets, absolute paths, or raw payload bytes;
- trigger reset orchestration;
- start or complete a UnitOfWork.

### `ArtifactPathResolver`

`ArtifactPathResolver` owns Kernel artifact path policy.

It **MUST**:

- accept only Kernel-owned artifact basenames;
- reject `routes.php`;
- reject absolute artifact cache paths;
- reject path traversal;
- reject `skeleton/`-prefixed cache directories;
- keep diagnostics stable and safe.

It **MUST NOT**:

- read files;
- write files;
- validate artifact envelope schemas;
- calculate fingerprints.

### Kernel Artifact Builders

Kernel artifact builders produce artifact envelopes for Kernel-owned artifact identities.

Kernel artifact builders **MUST** use `ArtifactEnvelopeFactory` for envelope construction.

Kernel artifact builders **MUST NOT**:

- manually redefine the top-level envelope shape;
- read files;
- write files;
- calculate fingerprints;
- resolve artifact paths;
- validate persisted artifact files.

The Kernel artifact builders are:

- `ModuleManifestBuilder`
- `CompiledConfigBuilder`
- `StubContainerBuilder`

### Platform-Owned Config Data Linkage (MUST)

The `config@1` artifact preserves the full merged global config payload as data.

This may include config key namespaces owned by packages outside `core/kernel`, such as:

```text
http.middleware.*
http.middleware.auto.*
```

`core/kernel` MUST preserve these values when they are present in the merged config payload.

`core/kernel` MUST NOT interpret these values as `platform/http` middleware semantics.

`core/kernel` MUST NOT validate these values against platform middleware catalogs.

`core/kernel` MUST NOT import `platform/http`.

`core/kernel` MUST NOT depend on `platform/http`.

Downstream packages such as `platform/http` MAY consume these fields from the compiled `config@1` artifact without reading source config files.

The presence of platform-owned config key namespaces in `config@1` is data preservation only. It does not transfer semantic ownership of those keys to `core/kernel`.

Fingerprint exclusions remain owned by the Kernel fingerprint exclusion policy in this document. Platform-owned config keys preserved inside `config@1` MUST NOT create special-case fingerprint exclusions.

### `ArtifactEnvelopeFactory`

`ArtifactEnvelopeFactory` is the Kernel-owned service that assembles Kernel artifact envelopes.

It **MUST**:

- create envelopes compatible with the global envelope law;
- use stable artifact names and schema versions from the canonical artifact registry;
- use stable generator ids;
- avoid timestamps, absolute paths, hostnames, usernames, process ids, and runtime-specific bytes.

It **MUST NOT**:

- write files;
- read files;
- calculate fingerprints;
- validate existing artifacts;
- redefine global envelope or header semantics.

### `ArtifactWriter`

`ArtifactWriter` owns Kernel artifact file writing.

It **MUST**:

- write deterministic PHP artifact bytes produced by `StablePhpArrayDumper`;
- normalize output to LF-only bytes with exactly one final newline;
- perform atomic per-file writes;
- clean up temporary files on write failure where possible;
- keep diagnostics safe and stable.

It **MUST NOT**:

- calculate fingerprints;
- validate artifact schemas;
- read existing generated artifacts for cache verification;
- expose absolute paths or raw artifact payloads in diagnostics.

### `StablePhpArrayDumper`

`StablePhpArrayDumper` owns deterministic PHP array emission for Kernel artifact files.

It **MUST**:

- emit PHP files that return a single array expression;
- preserve the received canonical envelope without wrapping it in another root key;
- use LF-only output;
- emit exactly one final newline;
- avoid generated comments, timestamps, tool versions, absolute paths, hostnames, usernames, and process-specific bytes;
- use Kernel/Foundation json-like normalization rules before emission.

It **MUST NOT**:

- validate artifact envelope semantics;
- calculate fingerprints;
- read or write files.

### `PhpArtifactReader`

`PhpArtifactReader` owns safe reading and parsing of existing Kernel PHP artifact files for cache verification.

It **MUST**:

- read existing artifact bytes;
- LF-normalize read bytes for byte comparison;
- parse PHP-returned arrays using isolated include behavior;
- reject emitted output from artifact files;
- convert read/include/parse failures into deterministic safe reason tokens.

It **MUST NOT**:

- resolve artifact paths;
- build expected artifacts;
- validate artifact schemas;
- calculate fingerprints;
- compare expected and current bytes;
- emit logs, spans, metrics, stdout, or stderr.

### `ArtifactSchemaValidator`

`ArtifactSchemaValidator` owns validation of existing artifact envelope/header/payload schemas for Kernel cache verification.

It **MUST** validate existing artifacts by:

- canonical envelope structure;
- canonical header semantics;
- Kernel-owned artifact name and schema version;
- Kernel-owned payload schema.

It **MUST NOT**:

- produce artifacts;
- write artifacts;
- calculate fingerprints;
- infer artifact ownership outside the canonical artifact registry.

## Fingerprint Input Behavior (MUST)

`ConfigFingerprintInputBuilder` owns construction of deterministic safe fingerprint input for Kernel artifacts.

It consumes only already-resolved inputs:

- resolved `BootstrapConfig`;
- resolved `ModulePlan`;
- `ConfigKernel::compile(...)` result;
- explicit source candidate arrays supplied to `ConfigKernel`;
- `EnvRepositoryInterface` source metadata;
- Kernel config subtree.

It **MUST NOT**:

- resolve `BootstrapConfig`;
- resolve `ModulePlan`;
- re-run preset resolution;
- re-run config discovery;
- re-run config merging;
- re-run env loading;
- read process env directly;
- scan package directories arbitrarily;
- scan app targets arbitrarily;
- enumerate arbitrary dotenv files;
- emit spans, metrics, logs, stdout, or stderr.

Fingerprint input **MUST** be deterministic for the same logical inputs.

Fingerprint input **MUST NOT** contain:

- raw config values;
- raw env values;
- secrets;
- absolute paths;
- timestamps;
- mtimes;
- file permissions;
- file owners;
- hostnames;
- process-specific bytes;
- raw SQL;
- raw payloads;
- stack traces.

Raw value influence **MAY** be represented only through safe deterministic metadata such as:

- hash;
- length;
- json-like type;
- safe source id;
- safe relative path;
- safe root;
- safe key path;
- safe count.

## Fingerprint Coverage (MUST)

Kernel artifact fingerprints **MUST** cover deterministic identity and provenance inputs needed to decide whether Kernel artifacts are current.

Fingerprint input **MUST** include safe deterministic representation of:

- Bootstrap identity;
- ModulePlan identity;
- compiled config roots;
- compiled config value fingerprints;
- config source metadata;
- config ownership metadata;
- config validation summary;
- validation subject metadata;
- explicit source candidates;
- split roots;
- canonical dotenv candidates;
- env overlay mappings;
- env source metadata;
- fingerprint policy.

Fingerprint input **MUST NOT** include `kernel.fingerprint.env.tracked_keys`.

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

It **MUST NOT** be duplicated under `kernel.fingerprint.*`.

## Fingerprint Exclusion Policy (MUST)

The Kernel fingerprint exclusion policy is configured through:

```text
kernel.fingerprint.skeleton_ignore_prefixes
```

Values are `BootstrapConfig::skeletonRoot()`-relative prefixes.

The baseline exclusions are:

```text
var/cache
var/maintenance
```

These exclusions exist to prevent generated and operational skeleton-local paths from affecting deterministic Kernel artifact fingerprints.

### Exclusion Rules (MUST)

`skeleton_ignore_prefixes` values:

- **MUST** be relative-safe paths;
- **MUST NOT** be absolute paths;
- **MUST NOT** contain `..`;
- **MUST NOT** contain empty path segments;
- **MUST NOT** contain whitespace;
- **MUST NOT** contain a `skeleton/` prefix;
- **MUST** be normalized before use;
- **MUST** be sorted deterministically;
- **MUST** be deduplicated deterministically;
- **MUST** be included in fingerprint input under fingerprint policy.

Changing the exclusion policy **MUST** change the fingerprint input.

### Exclusion Application (MUST)

`skeleton_ignore_prefixes` apply only to skeleton-local directory candidate traversal.

When a directory candidate is inside `BootstrapConfig::skeletonRoot()`, ignored skeleton-relative subtrees **MUST** be skipped before recursive traversal and before symlink inspection.

This means ignored generated/operational subtrees:

- are not included in content hashes;
- are not counted as fingerprint files;
- are not traversed;
- cannot make fingerprint construction fail merely because ignored contents contain symlinks.

`skeleton_ignore_prefixes` **MUST NOT** apply to explicit dotenv candidates.

`DeterministicFileLister` **MUST** remain policy-free. It may accept a caller-supplied skip callback, but it **MUST NOT** know about Kernel config, skeleton roots, or `var/cache`.

## Fingerprint Calculation (MUST)

`FingerprintCalculator` owns calculation of the Kernel artifact fingerprint from normalized fingerprint input.

It **MUST**:

- normalize fingerprint input according to canonical json-like byte rules;
- calculate a deterministic digest over stable bytes;
- return a stable lowercase fingerprint string;
- expose only safe observability metadata.

It **MUST NOT**:

- read files directly;
- write artifacts;
- resolve BootstrapConfig;
- resolve ModulePlan;
- compile config;
- run cache verification;
- expose raw fingerprint input in logs, spans, metrics, exceptions, or output.

## Fingerprint Explain Behavior (MUST)

`FingerprintExplainer` owns safe explain and diff representations for fingerprint input.

It **MAY** expose safe metadata such as:

- bucket names;
- safe key paths;
- safe relative paths;
- safe source types;
- hashes;
- lengths;
- counts;
- validation reason tokens;
- fingerprint policy entries such as skeleton ignore prefixes.

It **MUST NOT** expose:

- raw config values;
- raw env values;
- dotenv values;
- secrets;
- absolute paths;
- raw payloads;
- raw SQL;
- throwable messages;
- stack traces;
- host-specific bytes.

Fingerprint explain output is diagnostic metadata only. It **MUST NOT** change fingerprint calculation semantics.

## Cache Verification Behavior (MUST)

`CacheVerifier` owns Kernel artifact cache verification.

It **MUST**:

- compute the current deterministic fingerprint input from already-supplied resolved inputs;
- calculate the current fingerprint;
- build expected Kernel artifact envelopes in memory;
- dump expected artifact bytes in memory;
- resolve expected artifact paths;
- read existing artifact bytes and returned arrays;
- validate existing artifact schema;
- compare stored artifact fingerprint to current fingerprint;
- compare expected bytes to existing normalized bytes;
- return safe clean/dirty/invalid/failure summary data.

It **MUST NOT**:

- write artifacts;
- repair artifacts;
- mutate existing artifact files;
- update mtimes;
- rely on file mtimes, permissions, or owners for clean/dirty decisions;
- expose absolute paths, raw artifact payloads, raw config values, raw env values, or secrets.

### Verification Outcomes (MUST)

Missing expected artifact files are dirty.

Unreadable, invalid PHP, invalid envelope, invalid header, or invalid payload artifacts are invalid.

Stored fingerprint mismatch is dirty.

Byte mismatch is dirty.

Only exact schema-valid artifacts with matching stored fingerprint and matching deterministic bytes are clean.

## Compiler and Verifier Boundary (MUST)

`ArtifactCompiler` and `CacheVerifier` are intentionally separate.

`ArtifactCompiler`:

- writes expected Kernel artifacts;
- does not read existing generated artifacts;
- does not decide cache clean/dirty state.

`CacheVerifier`:

- reads existing Kernel artifacts;
- builds expected artifacts in memory;
- does not write artifacts.

Neither service may trigger reset orchestration or UnitOfWork lifecycle.

## Provider and Factory Wiring (MUST)

Kernel provider/factory wiring **MUST** register artifact, fingerprint, compiler, and verifier services as factories only.

Provider/factory wiring **MUST NOT**:

- write artifacts;
- read generated artifacts;
- calculate fingerprints;
- run cache verification;
- resolve `BootstrapConfig`;
- resolve `ModulePlan`;
- build `EnvRepositoryInterface`;
- run `ConfigKernel::compile(...)`;
- invoke `ResetOrchestrator`;
- start a UnitOfWork;
- emit stdout or stderr;
- start artifact/fingerprint/cache spans;
- emit artifact/fingerprint/cache metrics;
- write artifact/fingerprint/cache logs.

Artifact/fingerprint/cache services that emit observability **MUST** receive non-null dependencies through public ports/interfaces only:

- `TracerPortInterface`;
- `MeterPortInterface`;
- `LoggerInterface`;
- `Stopwatch`.

Provider/factory wiring **MUST NOT** decide whether an observability dependency is real or Noop.

Provider/factory wiring **MUST NOT** instantiate Noop observability implementations directly.

Default real-vs-Noop binding is owned by the application/foundation composition layer.

## Observability Linkage (MUST)

Artifact, fingerprint, and cache verification observability **MUST** comply with the canonical observability naming, metric catalog, label allowlist, and redaction law.

This document does not own the global metrics catalog.

Any artifact/fingerprint/cache metrics emitted by Kernel services **MUST** be registered in `docs/ssot/observability.md`.

Artifact/fingerprint/cache metrics **MUST** use only safe bounded labels. For the baseline Kernel artifact/fingerprint/cache services, the only allowed metric label is:

```text
outcome
```

Artifact/fingerprint/cache spans and logs **MUST NOT** expose:

- raw paths;
- raw config values;
- raw env values;
- artifact payload bytes;
- secrets;
- PII;
- raw SQL;
- stack traces;
- throwable messages.

Observability failures **MUST NOT** change artifact writing, fingerprint calculation, or cache verification semantics. Services that emit observability **MUST** catch observability adapter failures.

## Config Linkage (MUST)

The `kernel` config root is owned by `core/kernel`.

Kernel artifact and fingerprint config keys are subtrees under the `kernel` root, not independent config roots:

```text
kernel.artifacts.*
kernel.fingerprint.*
```

The defaults file for the `kernel` root returns only the `kernel` subtree.

Kernel artifact/fingerprint config rules are owned by the `core/kernel` package rules file.

This document does not redefine config root ownership.

## Non-goals / Clarifications (MUST)

- This document does not redefine artifact envelope shape.
- This document does not redefine artifact header fields.
- This document does not redefine the artifact registry.
- This document does not define `routes@1` production.
- This document does not define platform routing artifact behavior.
- This document does not define every future Kernel artifact payload schema.
- This document does not define the global observability metrics catalog.
- This document does not define config root ownership.
- This document does not make artifact generation part of runtime request lifecycle.
- This document does not require generated artifacts to be read during normal Kernel runtime service registration.
- `container@1` may be a deterministic stub artifact in this epic. Later compiled-container work may either remain compatible with `container@1` or introduce a new schema version.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Config Roots Registry](./config-roots.md)
- [Config and env SSoT](./config-and-env.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
