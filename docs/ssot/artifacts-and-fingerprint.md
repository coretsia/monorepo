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

```yaml
ssotVersion: 1
status: pre-stable
owner: core/kernel
```

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
- Kernel artifact/fingerprint/container-compile/cache service wiring constraints.

This document MUST NOT redefine:

- the canonical artifact envelope shape;
- canonical artifact header fields;
- canonical artifact registry rows;
- global deterministic serialization law;
- global observability metric catalog or label allowlist;
- ownership of non-Kernel artifacts such as `routes@1`.

Those rules remain owned by their canonical SSoT documents.

## Invariants (MUST)

- Kernel-owned artifacts MUST remain compatible with the global artifact envelope and deterministic serialization law defined by `docs/ssot/artifacts.md`.
- Kernel-owned artifact identities MUST use the canonical registry entries from `docs/ssot/artifacts.md`.
- Kernel artifact production MUST NOT introduce alternative envelope forms.
- Kernel artifact production MUST NOT redefine header semantics.
- Kernel artifact production MUST NOT add registry rows in this document.
- Kernel artifact production MUST NOT produce artifacts owned by other packages.
- `routes@1` MUST NOT be produced by `core/kernel`; it is owned by `platform/routing`.
- Kernel artifacts MUST be deterministic and rerun-no-diff for the same logical inputs.
- Kernel artifact location MUST be resolved during Bootstrap Phase A.
- `BootstrapConfig::artifactsCacheDir()` MUST be the only resolved artifact cache directory consumed by Kernel artifact path services.
- ConfigKernel Phase B and compiled `config@1` MUST NOT re-resolve or override artifact location.
- The resolved artifact output location itself MUST NOT be serialized into Bootstrap fingerprint identity or configured fingerprint policy solely because it is the selected output directory.
- Changing only `BootstrapConfig::artifactsCacheDir()`, while all separately fingerprinted config and source inputs remain unchanged, MUST NOT change artifact fingerprint identity or generated artifact bytes.
- Changing `kernel.boot.default_artifacts_cache_dir` in package config is also a fingerprinted config-source change and MAY therefore change the fingerprint through normal config provenance.
- Kernel artifacts MUST NOT embed timestamps, absolute paths, hostnames, usernames, process ids, raw env values, secrets, PII, raw payloads, raw SQL, stack traces, mtimes, permissions, owners, or filesystem-order-dependent bytes.
- Kernel fingerprint input MUST be safe, deterministic, and derived only from already-resolved Kernel inputs.
- Kernel fingerprint input MUST include a safe deterministic bucket for the canonical compiled runtime `DefinitionGraph`.
- The container-graph bucket MUST be derived from `DefinitionGraph::toArray()`, stable JSON encoding, and SHA-256.
- The raw compiled graph MUST NOT be duplicated inside fingerprint input.
- `ArtifactCompiler` and `CacheVerifier` MUST obtain the graph through the same `RuntimeContainerGraphCompiler` production path.
- The same canonical `DefinitionGraph` MUST be used for both fingerprint construction and REAL `container@1` envelope construction within one operation.
- Any semantic compiled-container graph change MUST change artifact fingerprint identity.
- Repeated compilation producing the same canonical graph MUST produce the same graph bucket and artifact fingerprint.
- Kernel cache verification MUST compare deterministic expected artifacts against existing artifacts without mutating artifact files.

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

Kernel artifact output paths are derived exclusively from:

- `BootstrapConfig::skeletonRoot()`;
- `BootstrapConfig::artifactsCacheDir()`;
- `BootstrapConfig::appTarget()->value`;
- the canonical Kernel artifact basename.

The canonical absolute shape is:

```text
<skeletonRoot>/<artifactsCacheDir>/<appTarget>/<artifact-basename>
```

The canonical skeleton-relative shape is:

```text
<artifactsCacheDir>/<appTarget>/<artifact-basename>
```

With the package fallback, the default paths are:

```text
var/cache/web/module-manifest.php
var/cache/web/config.php
var/cache/web/container.php
```

With an application override, valid paths may instead be:

```text
var/artifacts_cache/web/module-manifest.php
var/artifacts_cache/web/config.php
var/artifacts_cache/web/container.php
```

### Bootstrap Phase A Resolution (MUST)

Artifact cache directory resolution precedence is:

1. explicit `BootstrapInput::artifactsCacheDir()`;
2. bootstrap-only `skeleton/config/app.php` `artifactsCacheDir`;
3. package fallback `kernel.boot.default_artifacts_cache_dir`.

The resolved result is stored in:

```text
BootstrapConfig::artifactsCacheDir()
```

`kernel.boot.default_artifacts_cache_dir` is a package fallback only.

It is not a Phase B runtime selector and must not be re-read by artifact path consumers after `BootstrapConfig` has been resolved.

The following are not artifact location sources:

```text
ConfigKernel Phase B merged config
compiled config@1
kernel.artifacts.cache_dir
```

`kernel.artifacts.cache_dir` MUST NOT be introduced.

Even when `kernel.boot.default_artifacts_cache_dir` is preserved inside merged or compiled config as ordinary config data, artifact services MUST NOT use that copy to resolve artifact paths.

### Application Override (MUST)

The application-level override belongs only to:

```text
skeleton/config/app.php
```

Its key is:

```text
artifactsCacheDir
```

Example:

```php
return [
    'artifactsCacheDir' => 'var/artifacts_cache',
];
```

This file is a Bootstrap Phase A input only.

It MUST NOT participate in ConfigKernel Phase B merge.

### Artifact Cache Directory Domain (MUST)

The resolved artifact cache directory:

- MUST be a non-empty valid UTF-8 string;
- MUST be relative to `BootstrapConfig::skeletonRoot()`;
- MUST use `/` separators;
- MUST be no longer than 480 bytes;
- MUST NOT be absolute;
- MUST NOT contain whitespace or control characters;
- MUST NOT contain `:`, `\`, or `//`;
- MUST NOT contain empty, `.` or `..` path segments;
- MUST NOT end a path segment with `.`;
- MUST NOT contain Windows-invalid path component characters;
- MUST NOT use Windows reserved device names;
- MUST NOT use any of these forbidden top-level segments as the artifact root:

```text
.git
.github
apps
config
public
resources
skeleton
src
tests
vendor
```

The directory represents a dedicated generated-output root.

Valid examples include:

```text
var/cache
var/artifacts_cache
var/runtime/coretsia
storage/coretsia/artifacts
```

Invalid examples include:

```text
/cache
C:\cache
../cache
var/../cache
var//cache
skeleton/var/cache
config/artifacts
apps/artifacts
public/cache
vendor/generated
```

The declarative config rule for:

```text
kernel.boot.default_artifacts_cache_dir
```

validates the generic `relative-safe-path` shape.

The stricter portable artifact-root domain is validated by Bootstrap Phase A through `BootstrapArtifactsCacheDir`.

### `ArtifactPathResolver` Boundary (MUST)

`ArtifactPathResolver` consumes only the already resolved:

```text
BootstrapConfig::artifactsCacheDir()
```

It MUST:

- accept only Kernel-owned artifact basenames;
- reject `routes.php`;
- construct paths under `<skeletonRoot>/<artifactsCacheDir>/<appTarget>/`;
- enforce a maximum final skeleton-relative artifact path length of 512 bytes;
- ensure the final artifact path remains under the resolved artifact cache directory;
- keep `ArtifactPathInvalidException` diagnostics stable and safe.

It MUST NOT:

- read Kernel config;
- read `kernel.boot.default_artifacts_cache_dir`;
- read `skeleton/config/app.php`;
- resolve bootstrap defaults;
- resolve application overrides;
- read files;
- write files;
- validate artifact envelope schemas;
- calculate fingerprints.

## Artifact Production Responsibilities (MUST)

Kernel artifact production is split into narrow services.

### `ArtifactCompiler`

`ArtifactCompiler` owns Kernel artifact production orchestration.

It MUST:

- receive one resolved `ModuleResolution`;
- derive the current `ModulePlan` from that resolution;
- call `ConfigKernel` once for the supplied compile inputs;
- compile one production runtime container graph through `RuntimeContainerGraphCompiler`;
- pass the compiled `DefinitionGraph` to `ConfigFingerprintInputBuilder`;
- build fingerprint input containing the safe container-graph bucket;
- calculate the graph-bound fingerprint through `FingerprintCalculator`;
- build expected Kernel artifact envelopes only after fingerprint calculation;
- build the REAL `container@1` envelope from the same compiled `DefinitionGraph`;
- resolve Kernel artifact paths through `ArtifactPathResolver`;
- write Kernel artifacts through `ArtifactWriter`;
- return only safe summary data.

It MUST NOT:

- read existing generated artifacts;
- decide cache clean/dirty state;
- reuse existing artifact files;
- emit stdout or stderr;
- expose raw config values, raw env values, secrets, absolute paths, or raw payload bytes;
- trigger reset orchestration;
- discover container providers or modules implicitly;
- accept a raw container descriptor iterable;
- compile a second graph between fingerprint construction and REAL `container@1` envelope construction;
- use provider fallback for `container.php`;
- start or complete a UnitOfWork.

### Kernel Artifact Builders

Kernel artifact builders produce artifact envelopes for Kernel-owned artifact identities.

Kernel artifact builders MUST use `ArtifactEnvelopeFactory` for envelope construction.

Kernel artifact builders MUST NOT:

- manually redefine the top-level envelope shape;
- read files;
- write files;
- calculate fingerprints;
- resolve artifact paths;
- validate persisted artifact files.

The Kernel artifact builders are:

- `ModuleManifestBuilder`
- `CompiledConfigBuilder`
- `CompiledContainerBuilder`

`ContainerCompiler` is not an artifact builder. It owns deterministic normalization of one ordered `ContainerDefinitionSet` into one canonical `DefinitionGraph`.

`RuntimeContainerGraphCompiler` owns production provider-plan resolution, provider-definition collection, ordered set merging, low-level graph compilation, and final graph-completeness validation.

`CompiledContainerBuilder` receives the compiled `DefinitionGraph` and wraps its deterministic payload in the canonical Kernel artifact envelope.

`CompiledContainerBuilder` MUST emit a REAL `container@1` payload.

The `container@1` payload MUST use:

```text
kind = compiled
compiled = true
```

The `container@1` payload MUST contain the canonical compiled-container map fields:

```text
aliases
parameters
services
tags
```

`CompiledContainerBuilder` MUST NOT emit the unsupported transitional stub payload:

```text
kind = stub
compiled = false
```

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

It MUST:

- create envelopes compatible with the global envelope law;
- use stable artifact names and schema versions from the canonical artifact registry;
- use stable generator ids;
- avoid timestamps, absolute paths, hostnames, usernames, process ids, and runtime-specific bytes.

It MUST NOT:

- write files;
- read files;
- calculate fingerprints;
- validate existing artifacts;
- redefine global envelope or header semantics.

### `ArtifactWriter`

`ArtifactWriter` owns Kernel artifact file writing.

It MUST:

- write deterministic PHP artifact bytes produced by `StablePhpArrayDumper`;
- normalize output to LF-only bytes with exactly one final newline;
- perform atomic per-file writes;
- clean up temporary files on write failure where possible;
- keep diagnostics safe and stable.

It MUST NOT:

- calculate fingerprints;
- validate artifact schemas;
- read existing generated artifacts for cache verification;
- expose absolute paths or raw artifact payloads in diagnostics.

### `StablePhpArrayDumper`

`StablePhpArrayDumper` owns deterministic PHP array emission for Kernel artifact files.

It MUST:

- emit PHP files that return a single array expression;
- preserve the received canonical envelope without wrapping it in another root key;
- use LF-only output;
- emit exactly one final newline;
- avoid generated comments, timestamps, tool versions, absolute paths, hostnames, usernames, and process-specific bytes;
- use Kernel/Foundation json-like normalization rules before emission.

It MUST NOT:

- validate artifact envelope semantics;
- calculate fingerprints;
- read or write files.

### `PhpArtifactReader`

`PhpArtifactReader` owns safe reading and parsing of existing Kernel PHP artifact files for cache verification.

It MUST:

- read existing artifact bytes;
- LF-normalize read bytes for byte comparison;
- parse PHP-returned arrays using isolated include behavior;
- reject emitted output from artifact files;
- convert read/include/parse failures into deterministic safe reason tokens.

It MUST NOT:

- resolve artifact paths;
- build expected artifacts;
- validate artifact schemas;
- calculate fingerprints;
- compare expected and current bytes;
- emit logs, spans, metrics, stdout, or stderr.

### `ArtifactSchemaValidator`

`ArtifactSchemaValidator` owns validation of existing artifact envelope/header/payload schemas for Kernel cache verification.

It MUST validate existing artifacts by:

- canonical envelope structure;
- canonical header semantics;
- Kernel-owned artifact name and schema version;
- Kernel-owned payload schema.

It MUST NOT:

- produce artifacts;
- write artifacts;
- calculate fingerprints;
- infer artifact ownership outside the canonical artifact registry.

For `container@1`, `ArtifactSchemaValidator` MUST validate the REAL compiled-container payload shape:

```text
aliases
compiled
kind
parameters
services
tags
```

The `container@1` payload MUST satisfy:

```text
kind = compiled
compiled = true
```

The validator MUST reject unsupported transitional stub payloads:

```text
kind = stub
compiled = false
```

The validator MUST validate `aliases`, `parameters`, `services`, and `tags` as deterministic maps. Empty maps are valid.

Compiled service definitions MUST include the canonical lifecycle field:

```text
shared
```

Container tag entries MUST be ordered by:

```text
priority DESC, id ASC
```

Duplicate service ids inside the same tag list MUST be rejected.

## Fingerprint Input Behavior (MUST)

`ConfigFingerprintInputBuilder` owns construction of deterministic safe fingerprint input for Kernel artifacts.

It consumes only already-resolved or already-produced inputs:

- resolved `BootstrapConfig`;
- resolved `ModulePlan`;
- canonical compiled runtime `DefinitionGraph`;
- `ConfigKernel::compile(...)` result;
- explicit source candidate arrays supplied to `ConfigKernel`;
- `EnvRepositoryInterface` source metadata;
- Kernel config subtree.

It MUST NOT:

- resolve `BootstrapConfig`;
- resolve `ModulePlan`;
- resolve `ModuleResolution`;
- compile or recompile the runtime container graph;
- instantiate container definition providers;
- re-run preset resolution;
- re-run config discovery;
- re-run config merging;
- re-run env loading;
- read process env directly;
- scan package directories arbitrarily;
- scan app targets arbitrarily;
- enumerate arbitrary dotenv files;
- emit spans, metrics, logs, stdout, or stderr.

Fingerprint input MUST be deterministic for the same logical inputs.

Fingerprint input MUST NOT contain:

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

Raw value influence MAY be represented only through safe deterministic metadata such as:

- hash;
- length;
- json-like type;
- safe source id;
- safe relative path;
- safe root;
- safe key path;
- safe count.

## Container Graph Fingerprint Bucket (MUST)

`ContainerGraphFingerprintBucketBuilder` owns deterministic safe identity construction for one compiled runtime `DefinitionGraph`.

Its public operation is:

```php
/**
 * @return array{
 *     schemaVersion: int,
 *     sha256: string,
 *     serviceCount: int,
 *     aliasCount: int,
 *     parameterCount: int,
 *     tagCount: int
 * }
 */
public function build(DefinitionGraph $graph): array;
```

The builder MUST:

1. obtain the canonical graph representation through `DefinitionGraph::toArray()`;
2. stable-JSON-encode that canonical representation;
3. calculate lowercase SHA-256 over the stable JSON bytes;
4. expose only bounded safe structural counts.

The bucket MUST have exactly this semantic shape:

```php
[
    'schemaVersion' => 1,
    'sha256' => '<lowercase 64-character sha256>',
    'serviceCount' => int,
    'aliasCount' => int,
    'parameterCount' => int,
    'tagCount' => int,
]
```

Count semantics are:

- `serviceCount` equals the number of entries in the canonical `services` map;
- `aliasCount` equals the number of entries in the canonical `aliases` map;
- `parameterCount` equals the number of entries in the canonical `parameters` map;
- `tagCount` equals the number of canonical tag names in the `tags` map.

`tagCount` does not mean the total number of tagged-service registrations.

All counts MUST be bounded safe non-negative integers.

The graph bucket MUST NOT include:

- the raw graph payload;
- provider-plan entries;
- provider class names as separate metadata;
- provider instances;
- runtime service instances;
- runtime container instances;
- runtime seeds;
- source paths;
- artifact paths;
- filesystem paths;
- process-specific object identity.

Provider-plan metadata is not semantic graph identity after definitions have been compiled.

Provider class names are not independently fingerprinted. Class names and factory class identities that occur inside canonical service definitions remain covered because they are part of `DefinitionGraph::toArray()`.

The following semantic changes MUST change the graph SHA-256 and the complete Kernel artifact fingerprint:

- service class;
- factory class;
- factory method;
- service reference;
- parameter value;
- alias target;
- effective tag priority;
- shared lifecycle flag.

Inputs that compile to the same canonical `DefinitionGraph::toArray()` value MUST produce the same graph bucket regardless of non-semantic insertion or source-object identity differences.

`ConfigFingerprintInputBuilder` MUST include the resulting bucket under:

```text
containerGraph
```

The raw `DefinitionGraph` MUST NOT be included as another fingerprint-input bucket.

`observabilityMetadata.bucketNames` MUST include:

```text
containerGraph
```

Observability metadata MAY expose the safe bucket name, SHA-256, and bounded counts where allowed by the observability SSoT.

Observability MUST NOT expose the raw graph, raw service definitions, raw parameters, provider instances, runtime instances, or paths.

## Fingerprint Coverage (MUST)

Kernel artifact fingerprints MUST cover deterministic identity and provenance inputs needed to decide whether Kernel artifacts are current.

Fingerprint input MUST include safe deterministic representation of:

- Bootstrap identity;
- ModulePlan identity;
- canonical compiled runtime container graph identity through the `containerGraph` bucket;
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

The container-graph bucket binds all three Kernel-owned artifact envelopes to the canonical runtime graph used to build the REAL `container@1` payload.

A graph semantic change therefore invalidates:

```text
module-manifest.php
config.php
container.php
```

through their shared artifact fingerprint, even when config and module selection remain unchanged.

`Bootstrap identity` includes semantic bootstrap selections such as app target, environment, preset, debug mode, and env source policy.

It MUST NOT include:

```text
BootstrapConfig::artifactsCacheDir()
```

Artifact cache directory is an output materialization location, not semantic artifact identity.

Changing only the resolved `BootstrapConfig::artifactsCacheDir()` value, while all separately fingerprinted config and source inputs remain unchanged, MUST NOT change the fingerprint.

Changing the package fallback `kernel.boot.default_artifacts_cache_dir` is not a location-only change, because that edit also changes fingerprinted package and compiled config input.

Fingerprint input MUST NOT include `kernel.fingerprint.env.tracked_keys`.

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

It MUST NOT be duplicated under `kernel.fingerprint.*`.

## Fingerprint Exclusion Policy (MUST)

The Kernel fingerprint exclusion policy is configured through:

```text
kernel.fingerprint.skeleton_ignore_prefixes
```

Values are `BootstrapConfig::skeletonRoot()`-relative prefixes.

The configured baseline exclusion is:

```text
var/maintenance
```

The resolved artifact cache directory is a mandatory effective exclusion:

```text
BootstrapConfig::artifactsCacheDir()
```

The effective exclusion list is:

```text
kernel.fingerprint.skeleton_ignore_prefixes
+ BootstrapConfig::artifactsCacheDir()
```

The effective list MUST be normalized, deterministically sorted, and deduplicated before source traversal.

The resolved artifact cache directory MUST be excluded even when it is not listed in `kernel.fingerprint.skeleton_ignore_prefixes`.

The mandatory exclusion applies only to the currently resolved artifact cache directory.

A previously selected artifact cache directory is not retained automatically as a mandatory exclusion after relocation.

After changing artifact location, callers or deployment tooling MUST either:

- remove stale generated artifacts from the previous directory; or
- keep the previous directory as an explicit `kernel.fingerprint.skeleton_ignore_prefixes` entry when it may remain under a fingerprinted skeleton-local directory candidate.

Adding the previous directory to configured fingerprint policy is itself a fingerprint-policy change and therefore changes fingerprint input deterministically.

### Exclusion Rules (MUST)

`skeleton_ignore_prefixes` values:

- MUST be relative-safe paths;
- MUST NOT be absolute paths;
- MUST NOT contain `..`;
- MUST NOT contain empty path segments;
- MUST NOT contain whitespace;
- MUST NOT contain a `skeleton/` prefix;
- MUST be normalized before use;
- MUST be sorted deterministically;
- MUST be deduplicated deterministically;
- configured `kernel.fingerprint.skeleton_ignore_prefixes` values MUST be included in fingerprint input under fingerprint policy.

Changing the exclusion policy MUST change the fingerprint input.

The mandatory resolved artifact cache directory is an effective traversal exclusion only.

It MUST NOT be serialized into Bootstrap fingerprint identity or configured fingerprint policy solely because it is the selected output directory.

Changing only the resolved `BootstrapConfig::artifactsCacheDir()` value does not change fingerprint identity when all separately fingerprinted config and source inputs remain identical.

### Exclusion Application (MUST)

`skeleton_ignore_prefixes` apply only to skeleton-local directory candidate traversal.

When a directory candidate is inside `BootstrapConfig::skeletonRoot()`, ignored skeleton-relative subtrees MUST be skipped before recursive traversal and before symlink inspection.

This means ignored generated/operational subtrees:

- are not included in content hashes;
- are not counted as fingerprint files;
- are not traversed;
- cannot make fingerprint construction fail merely because ignored contents contain symlinks.

`skeleton_ignore_prefixes` MUST NOT apply to explicit dotenv candidates.

`DeterministicFileLister` MUST remain policy-free.

It may accept a caller-supplied skip callback, but it MUST NOT know about Kernel config, skeleton roots, `BootstrapConfig::artifactsCacheDir()`, or any specific default artifact directory.

## Fingerprint Calculation (MUST)

`FingerprintCalculator` owns calculation of the Kernel artifact fingerprint from normalized fingerprint input.

It MUST:

- normalize fingerprint input according to canonical json-like byte rules;
- calculate a deterministic digest over stable bytes;
- return a stable lowercase fingerprint string;
- expose only safe observability metadata.

It MUST NOT:

- read files directly;
- write artifacts;
- resolve BootstrapConfig;
- resolve ModulePlan;
- compile config;
- run cache verification;
- expose raw fingerprint input in logs, spans, metrics, exceptions, or output.

## Fingerprint Explain Behavior (MUST)

`FingerprintExplainer` owns safe explain and diff representations for fingerprint input.

It MAY expose safe metadata such as:

- bucket names;
- the `containerGraph` bucket name;
- the safe container-graph SHA-256;
- bounded container-graph service, alias, parameter, and tag counts;
- safe key paths;
- safe relative paths;
- safe source types;
- hashes;
- lengths;
- counts;
- validation reason tokens;
- fingerprint policy entries such as skeleton ignore prefixes.

It MUST NOT expose:

- raw config values;
- raw env values;
- dotenv values;
- secrets;
- absolute paths;
- raw payloads;
- raw `DefinitionGraph` payloads;
- raw compiled service definitions;
- raw compiled parameter values;
- provider or runtime instances;
- raw SQL;
- throwable messages;
- stack traces;
- host-specific bytes.

Fingerprint explain output is diagnostic metadata only. It MUST NOT change fingerprint calculation semantics.

## Cache Verification Behavior (MUST)

`CacheVerifier` owns Kernel artifact cache verification.

It MUST:

- receive one resolved `ModuleResolution`;
- derive the current `ModulePlan` from that resolution;
- run `ConfigKernel::compile(...)` once for the supplied resolved inputs;
- compile one production runtime container graph through `RuntimeContainerGraphCompiler`;
- pass that graph to `ConfigFingerprintInputBuilder`;
- compute fingerprint input containing the safe `containerGraph` bucket;
- calculate the current graph-bound fingerprint;
- build expected Kernel artifact envelopes in memory only after fingerprint calculation;
- build the expected REAL `container@1` envelope from the same compiled `DefinitionGraph`;
- dump expected artifact bytes in memory;
- resolve expected artifact paths;
- read existing artifact bytes and returned arrays;
- validate existing artifact schema;
- compare stored artifact fingerprint to the current graph-bound fingerprint;
- compare expected bytes to existing normalized bytes;
- return safe clean/dirty/invalid/failure summary data.

It MUST NOT:

- write artifacts;
- repair artifacts;
- mutate existing artifact files;
- update mtimes;
- accept a raw container descriptor iterable;
- compile a second graph between fingerprint construction and expected `container@1` construction;
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

Both services MUST use the same semantic production sequence:

```text
ConfigKernel::compile(...)
  -> RuntimeContainerGraphCompiler::compile(...)
  -> ConfigFingerprintInputBuilder::build(...)
  -> FingerprintCalculator::calculate(...)
  -> artifact envelope construction
```

For one operation, the `DefinitionGraph` passed into `ConfigFingerprintInputBuilder` MUST be the same graph passed into `CompiledContainerBuilder`.

Neither service may rebuild, mutate, substitute, or independently normalize another graph between those two uses.

## Provider and Factory Wiring (MUST)

Kernel provider/factory wiring MUST register artifact, fingerprint, compiler, and verifier services as factories only.

`ContainerGraphFingerprintBucketBuilder` MUST be registered as a compile-host factory service and passed into `ConfigFingerprintInputBuilder`.

It MUST NOT be part of canonical runtime provider definitions or the compiled runtime graph.

Provider/factory wiring MUST NOT:

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
- start artifact/fingerprint/container-compile/cache spans;
- emit artifact/fingerprint/container-compile/cache metrics;
- write artifact/fingerprint/container-compile/cache logs.

Artifact/fingerprint/container-compile/cache services that emit observability MUST receive non-null dependencies through public ports/interfaces only:

- `TracerPortInterface`;
- `MeterPortInterface`;
- `LoggerInterface`;
- `Stopwatch`.

Provider/factory wiring MUST NOT decide whether an observability dependency is real or Noop.

Provider/factory wiring MUST NOT instantiate Noop observability implementations directly.

Default real-vs-Noop binding is owned by the application/foundation composition layer.

## Observability Linkage (MUST)

Artifact, fingerprint, container compilation, and cache verification observability MUST comply with the canonical observability naming, metric catalog, label allowlist, and redaction law.

Safe fingerprint-input observability metadata MUST list `containerGraph` as a fingerprint bucket name.

The presence of that bucket name is diagnostic metadata only and MUST NOT create an alternative fingerprint calculation path.

This document does not own the global metrics catalog.

Any artifact/fingerprint/container-compile/cache metrics emitted by Kernel services MUST be registered in `docs/ssot/observability.md`.

Artifact/fingerprint/container-compile/cache metrics MUST use only safe bounded labels. For the baseline Kernel artifact/fingerprint/container-compile/cache services, the only allowed metric label is:

```text
outcome
```

Artifact/fingerprint/container-compile/cache spans and logs MUST NOT expose:

- raw paths;
- raw config values;
- raw env values;
- artifact payload bytes;
- secrets;
- PII;
- raw SQL;
- stack traces;
- throwable messages.

Observability failures MUST NOT change artifact writing, fingerprint calculation, container compilation, or cache verification semantics. Services that emit observability MUST catch observability adapter failures.

## Bootstrap and Config Linkage (MUST)

The `kernel` config root is owned by `core/kernel`.

The package fallback for Bootstrap Phase A artifact location is:

```text
kernel.boot.default_artifacts_cache_dir
```

The configured fingerprint exclusion policy remains:

```text
kernel.fingerprint.skeleton_ignore_prefixes
```

These are key namespaces under the existing `kernel` root, not independent config roots.

There is no:

```text
kernel.artifacts.*
```

subtree in the current Kernel config contract.

Application-level artifact cache directory override belongs only to:

```text
skeleton/config/app.php artifactsCacheDir
```

The resolved artifact location belongs to:

```text
BootstrapConfig::artifactsCacheDir()
```

ConfigKernel Phase B and compiled `config@1` are not artifact location resolution sources.

The defaults file for the `kernel` root returns only the `kernel` subtree.

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
- `container@1` is not a deterministic stub artifact in current Kernel artifact production.
- Kernel-produced `container@1` artifacts use the REAL compiled-container payload shape: `kind = compiled` and `compiled = true`.
- Stub container payloads (`kind = stub`, `compiled = false`) are invalid for current Kernel-produced `container@1` artifacts.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Compiled Container Payload and Artifact-Only Boot Semantics](./compiled-container.md)
- [Cache Verification Behavior](./cache-verify.md)
- [Config Roots Registry](./config-roots.md)
- [Config and env SSoT](./config-and-env.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Kernel Bootstrap Phase A ADR](../adr/ADR-0023-kernel-bootstrap-phase-a.md)
- [Kernel Artifacts, Fingerprint, and Cache Verification ADR](../adr/ADR-0028-kernel-artifacts-fingerprint-cache-verify.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
