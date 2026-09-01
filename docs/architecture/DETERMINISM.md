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

# Coretsia Determinism Contract

## Purpose

This document defines the production determinism contract of Coretsia.

Determinism is required where identical semantic inputs must produce identical semantic results, identities, diagnostics, ordering, or generated artifact bytes.

The contract does not require the entire runtime to be free from entropy.

The governing rule is:

```
deterministic domains
MUST NOT observe uncontrolled entropy
```

Intentional runtime entropy is permitted when it cannot influence deterministic semantic results.

Examples include:

- process identifiers;
- clocks and durations;
- operating-system scheduling;
- security random values and credentials;
- transport timing;
- transport-correlation identifiers.

---

# Normative sources

This document is the umbrella architecture contract for production determinism across Coretsia.

It defines the cross-cutting determinism model, terminology, domain boundaries, and guarantees that span multiple packages and subsystems.

Package- and subsystem-specific normative truth remains owned by the canonical SSoT documents registered in the [SSoT Index](../ssot/INDEX.md).

Where this document summarizes a subsystem contract, the corresponding SSoT remains authoritative for its exact shapes, precedence rules, schemas, failure classifications, and ownership boundaries.

The primary normative sources for this contract are:

### Canonical values and ordering

- [JSON-like Runtime Values SSoT](../ssot/json-like-runtime-values.md);
- [DI Tags and Middleware Ordering SSoT](../ssot/di-tags-and-middleware-ordering.md);
- [Runtime Container Definitions SSoT](../ssot/runtime-container-definitions.md).

### Configuration

- [Config and env SSoT](../ssot/config-and-env.md);
- [Config Merge Order](../ssot/config-merge-order.md);
- [Config Precedence Matrix](../ssot/config-precedence-matrix.md);
- [Config Roots Registry](../ssot/config-roots.md).

### Modules, fingerprints, generations, and artifacts

- [Modules and manifests SSoT](../ssot/modules-and-manifests.md);
- [Artifacts and Fingerprint Behavior](../ssot/artifacts-and-fingerprint.md);
- [Artifact Generations](../ssot/artifact-generations.md);
- [Artifact Header and Schema Registry](../ssot/artifacts.md);
- [Cache Verification Semantics](../ssot/cache-verify.md);
- [Compiled Container Payload and Artifact-Only Boot Semantics](../ssot/compiled-container.md).

### Runtime diagnostics and entropy boundaries

- [ErrorDescriptor SSoT](../ssot/error-descriptor.md);
- [Errors Boundary SSoT](../ssot/errors-boundary.md);
- [Observability and Errors SSoT](../ssot/observability-and-errors.md);
- [Runtime Drivers SSoT](../ssot/runtime-drivers.md);
- [Time, IDs, and Duration SSoT](../ssot/time-ids-and-duration.md).

### Worker

- [Worker Process Bootstrap SSoT](../ssot/worker-process-bootstrap.md);
- [Worker Task Sources SSoT](../ssot/worker-task-sources.md).

Architecture Decision Records explain the rationale behind individual design decisions but do not replace the current SSoT.

Executable tests provide evidence that implementations satisfy these contracts; test names and test organization are not themselves normative architecture.

---

# Scope and terminology

## Semantic input

A semantic input is information that the owning contract explicitly defines as affecting a result.

Examples include:

- effective configuration;
- enabled modules;
- module dependencies and conflicts;
- declared provider order where that order is semantic;
- container definitions;
- tracked environment inputs;
- declared fingerprint source files;
- selected runtime-driver configuration;
- worker task type.

A change to a fingerprint-relevant semantic input MUST be observable in the resulting fingerprint.

---

## Irrelevant physical state

Physical state is not semantic unless a contract explicitly says otherwise.

Examples include:

- absolute temporary root;
- current process id;
- filesystem creation order;
- filesystem enumeration order;
- file mtime;
- file permissions when permissions are not part of the semantic contract;
- host-specific absolute paths;
- locale-specific ordering;
- random staging names.

Such state MUST NOT change deterministic identity or generated artifact bytes.

---

## Semantic order

Not every list may be sorted.

If order is defined by the owning contract, that order is semantic and MUST be preserved.

Examples include:

- explicitly ranked configuration layers;
- caller-supplied JSON-like lists;
- module-declared provider order.

Coretsia MUST NOT canonicalize semantic order merely to make output appear deterministic.

---

## Set-shaped order

If input order is not semantic, Coretsia MUST derive a canonical order before the value reaches a deterministic output boundary.

Canonical ordering uses explicit contract rules rather than incidental filesystem, hash-map, discovery, or registration order.

---

# General rules

Production deterministic domains follow these rules:

1. Unordered map-like values are canonicalized before identity or serialization boundaries.
2. Ordered semantic lists preserve their contract-defined order.
3. Set-shaped discovery and resolution use explicit deterministic ordering.
4. Deterministic output MUST NOT depend on timestamps, process state, host state, locale, random values, or absolute filesystem locations.
5. Diagnostics in deterministic domains MUST have stable classification and canonical identifier ordering.
6. Diagnostic output MUST remain safe and MUST NOT expose raw secrets, credentials, payloads, environment values, or host-specific absolute paths.
7. Observability is best-effort and MUST NOT change deterministic semantic behavior.
8. Runtime entropy MAY be used for operational purposes only when it remains outside deterministic identity and classification boundaries.

---

# Determinism domains

## Canonical JSON-like values

Foundation defines the baseline canonical JSON-like value model.

Accepted values are:

- `null`;
- `bool`;
- `int`;
- `string`;
- lists of accepted values;
- string-keyed maps of accepted values.

The baseline model rejects:

- floats, including `NaN`, `INF`, and `-INF`;
- resources;
- objects and closures;
- non-string map keys.

Canonicalization rules are:

```
map
→ recursively canonicalize values
→ keys sorted by byte-order strcmp

list
→ recursively canonicalize values
→ caller-supplied order preserved
```

Canonical ordering MUST NOT depend on locale.

Foundation deterministic discovery ordering uses the explicit rule:

```
priority DESC
then
id ASC by byte-order strcmp
```

Stable JSON serialization uses the canonical JSON-like representation and stable encoder options.

Diagnostics emitted by canonicalization boundaries expose stable reason/path information only and MUST NOT expose rejected raw values or sensitive runtime state.

---

## Configuration

Kernel configuration is compiled from an explicit canonical source set.

`ConfigKernel` is an orchestration boundary. It does not discover package directories, infer source locations, read arbitrary environment state, or invent source precedence.

Configuration precedence is semantic.

The invariant is:

```
same declared configuration sources
+
same effective allowed environment
+
same precedence

↓

same effective configuration
+
same source attribution
+
same validation result
```

Configuration merge rules include:

- lower-rank input is the base;
- higher-rank input is the patch;
- maps merge recursively;
- map keys are canonicalized by byte-order string comparison;
- higher-rank scalar and list values replace lower-rank values;
- list order is preserved unless an explicit directive changes that list;
- environment overlays participate only through their explicit configured precedence.

Physical enumeration order MUST NOT invent precedence.

Configuration diagnostics MUST preserve stable classification, safe context, and deterministic ordering.

Irrelevant environment noise MUST NOT alter the effective configuration or artifact fingerprint.

---

## Module resolution

Module resolution treats set-shaped module inputs canonically.

For the same semantic module graph:

```
manifest permutation A
manifest permutation B
manifest permutation C

↓

identical ModulePlan
```

Module identity, dependency closure, optional-missing state, conflicts, warnings, and topological ordering MUST be derived deterministically.

Topological ordering MUST satisfy dependency order first and use canonical deterministic tie-breaking when several modules are simultaneously eligible.

Module diagnostics expose canonical module identifiers and MUST NOT depend on discovery order or filesystem paths.

Application target metadata MUST NOT accidentally change module selection where it is defined only as output metadata.

---

## Provider and container ordering

Provider ordering distinguishes semantic order from set-shaped order.

The canonical provider plan is:

```
topological module order
then
module-declared provider order
```

Module-declared provider order is semantic and MUST be preserved.

Container definitions, parameters, aliases, tags, and other set-shaped structures MUST be emitted in their canonical contract order.

Runtime-driver conflict identifiers and equivalent diagnostic sets MUST use canonical deterministic ordering.

Equivalent semantic container inputs MUST produce identical canonical compiled-container bytes.

---

## Fingerprint identity

Kernel artifact fingerprints are deterministic application identities.

The fingerprint pipeline is:

```
canonical fingerprint input
↓
JSON-like normalization
↓
StableJsonEncoder
↓
SHA-256
↓
lowercase 64-character hexadecimal fingerprint
```

`FingerprintCalculator` does not discover inputs. It consumes the already-built canonical fingerprint input.

Fingerprint calculation MUST NOT directly include:

- raw config values outside their canonical fingerprint representation;
- raw environment values;
- secrets;
- absolute paths;
- timestamps;
- mtimes;
- permissions;
- filesystem owners;
- hostnames;
- process identifiers;
- random bytes;
- locale-dependent bytes.

Observability failures MUST NOT change the resulting fingerprint.

---

## Fingerprint file inputs

Filesystem input to a fingerprint is explicitly declared.

Deterministic file listing MUST NOT be used as implicit framework discovery for:

- modules;
- configuration roots;
- application targets;
- installed package sets;
- unknown configuration files;
- arbitrary dotenv files;
- filesystem-derived framework state.

For declared fingerprint inputs:

- returned paths are root-relative;
- path separators are normalized to `/`;
- ordering uses byte-order string comparison;
- OS locale does not affect ordering;
- absolute input paths are not exported as identity;
- symlinks are not followed as fingerprint content and forbidden symlink cases fail deterministically.

The same declared semantic files MUST therefore produce the same fingerprint regardless of physical enumeration order.

---

## Fingerprint sensitivity

Determinism requires both invariance and sensitivity.

### Invariance

```
same semantic application
+
different irrelevant physical state

↓

same fingerprint
```

### Sensitivity

```
fingerprint-relevant semantic change

↓

different fingerprint
```

Fingerprint-relevant changes include, according to the owning contracts:

- effective configuration;
- enabled module graph;
- relevant module/provider/container definitions;
- tracked environment input;
- declared relevant source content.

A fingerprint that is stable but fails to change for a relevant semantic change does not satisfy this contract.

---

## Generation identity

A Kernel artifact generation is an immutable identity boundary.

The generation id is exactly:

```
artifact generation id
=
lowercase 64-character SHA-256 artifact fingerprint
```

Every artifact in one generation shares that fingerprint identity.

A generation publication set contains canonical artifact bytes and MUST reject inconsistent or mixed fingerprints.

For the same semantic compile:

```
same fingerprint
↓
same generation id
```

Generation publication uses an immutable generation directory and a locked `current` pointer transition.

Publication MUST ensure that runtime readers observe either:

- the previously valid current generation; or
- the newly complete and validated generation.

They MUST NOT observe a partially published generation as current.

Publishing an already identical generation MAY reuse the existing immutable generation rather than rewrite it.

Random temporary or staging names are operational entropy only and MUST NOT affect generation identity or artifact content.

Concurrent publication of the same semantic generation MUST converge on a valid complete generation.

---

## Artifact reproducibility

Compiled Kernel artifacts are deterministic byte outputs.

The canonical generation contains:

- `config.php`;
- `container.php`;
- `module-manifest.php`;
- `generation-manifest.php`.

For identical semantic application state:

```
different physical root
+
different relevant file creation order
+
different irrelevant filesystem state

↓

same fingerprint
+
same generation id
+
same artifact bytes
```

Artifact serialization is canonical.

The stable PHP-array serializer:

- serializes normalized data rather than arbitrary executable PHP;
- uses deterministic map ordering;
- preserves semantic list order;
- produces stable string representation.

Kernel-owned text artifact writes use:

- LF-only line endings;
- exactly one final newline;
- atomic temporary-write/rename publication where supported.

Artifact content MUST NOT be augmented with:

- timestamps;
- tool execution times;
- hostnames;
- absolute paths;
- mtimes;
- permissions;
- filesystem owners;
- user names;
- process identifiers;
- random staging values.

mtime and non-semantic permission differences MUST NOT change artifact identity.

Exact canonical bytes, not approximate PHP runtime equivalence, define artifact reproducibility.

---

## Artifact publication and validation

Generation validation is performed as an immutable set.

The generation manifest validates canonical properties including:

- artifact names;
- schema shape;
- byte counts;
- hashes;
- generation identity;
- absence of unexpected generation members.

The `current` pointer resolves one complete immutable generation.

Mixed-generation artifacts, mixed fingerprints, hash mismatches, incomplete generations, unexpected artifacts, and forbidden symlink states are invalid.

---

## Runtime verification

`CacheVerifier` rebuilds the expected generation in memory and compares it against the exact immutable generation selected by `current`.

Its public semantic outcomes are stable classifications such as:

- `clean`;
- `dirty`;
- `invalid`;
- `failure`.

Artifact verification distinguishes canonical reasons such as:

- `ok`;
- `missing`;
- `changed`;
- `fingerprint_mismatch`;
- `invalid`.

The same semantic invalid state MUST produce the same public verification projection independent of irrelevant mutation or discovery order.

Artifact result ordering MUST be canonical.

Exact byte drift is semantic for artifact verification even when the changed PHP remains syntactically valid.

mtime and non-semantic permission changes alone MUST NOT make an otherwise identical generation dirty.

Observability failures MUST NOT change verifier behavior.

---

## Artifact-only runtime boot

Production artifact boot is deliberately separated from compilation.

Runtime boot selects exactly one immutable validated generation through the `current` pointer and builds the runtime container from that generation only.

Artifact-only runtime boot MUST NOT:

- read source configuration;
- run `ConfigKernel`;
- run module discovery;
- read Composer metadata as fallback;
- execute providers as fallback;
- compile a new container graph;
- calculate a fingerprint;
- write or repair artifacts;
- accept caller-selected individual artifact paths.

An invalid artifact generation fails as an artifact-runtime boot classification rather than silently rebuilding or repairing runtime state.

Runtime boot diagnostics MUST remain stable and safe and MUST NOT expose raw paths, raw configuration, artifact payloads, environment values, secrets, tokens, command lines, or previous throwable messages.

---

## Diagnostics

Deterministic diagnostics are a semantic projection, not an exception-object byte identity.

For the same invalid semantic state:

```
same invalid state

↓

same error classification
+
same reason
+
same safe message
+
same canonical identifiers
```

Where multiple identifiers are reported, their ordering MUST be canonical unless the owning contract defines another semantic precedence.

Where the production API intentionally exposes only one aggregate invalid classification, Coretsia MUST NOT invent nondeterministic low-level failure ordering merely for diagnostic detail.

Safe diagnostics take precedence over leaking implementation details.

---

## Worker semantics

Worker process execution contains legitimate operating-system entropy, but Worker semantic behavior remains deterministic.

Deterministic Worker surfaces include:

- lifecycle-state classification;
- unique task-source resolution and ambiguity classification;
- worker-slot ordering;
- generation selection;
- configuration-derived policy;
- driver selection and conflict classification;
- control-protocol schema and classification;
- shutdown/failure classification;
- safe persisted state projection.

---

## Worker task-source resolution

A worker task type resolves exactly one matching task source.

The semantic contract is:

```
zero matching sources
→ missing-source failure

exactly one matching source
→ that source is selected

more than one matching source
→ ambiguity failure
```

Coretsia does not silently choose a winner from an ambiguous task-source set.

For the same ambiguous source set, different registration/insertion permutations MUST produce the same public ambiguity diagnostic projection.

---

## Worker child ordering

Supervisor-owned worker slots are keyed by deterministic worker index.

Different child insertion permutations MUST produce the same canonical worker-index order.

PID, process creation time, readiness timing, and operating-system scheduling MUST NOT redefine worker-slot ordering.

---

## Worker state and control protocol

Safe Worker state uses explicit validated schemas.

Persisted/public Worker state intentionally excludes sensitive or unstable runtime data such as:

- timestamps;
- environment values;
- raw socket paths;
- raw TCP endpoint details;
- absolute paths;
- payloads;
- headers;
- credentials and tokens.

Control request and response frames use validated canonical shapes.

Supervisor control credentials are security entropy and remain private.

Request identifiers are transport-correlation values, not deterministic semantic identities.

Security credentials and correlation ids MUST NOT alter lifecycle classification, task-source resolution, generation selection, or worker ordering.

---

# Intentional entropy

The following are permitted sources of nondeterminism when used only inside their owning operational boundaries:

- `SystemClock` and runtime timing;
- `hrtime()` deadlines and duration measurement;
- process identifiers;
- operating-system process scheduling;
- random security credentials;
- random temporary/staging names;
- transport timing;
- transport-correlation identifiers.

Such values are classified as either intentional entropy or non-semantic observability.

They MUST NOT leak into:

- artifact fingerprints;
- generation identity;
- canonical artifact bytes;
- module or provider ordering;
- deterministic config results;
- runtime failure classification;
- Worker task-source classification;
- canonical Worker slot ordering.

---

# Cross-platform contract

Production deterministic contracts apply across the supported Coretsia CI environments.

The same package test suite is executed on supported Ubuntu and Windows runners.

Cross-platform evidence includes fixed canonical vectors for:

- Kernel fingerprint SHA-256;
- compiled-container artifact byte SHA-256.

Therefore, for those canonical fixtures:

```
Ubuntu canonical output
=
Windows canonical output
```

Cross-platform determinism does not require equality of intentional entropy such as PIDs, timing values, random credentials, temporary paths, or scheduler behavior.

Filesystem and test rails normalize platform-sensitive behavior where required, including path handling, line endings, timezone, and supported symlink behavior.

---

# Evidence

Production determinism is enforced by package-owned unit, contract, integration, and end-to-end tests.

Executable evidence covers:

- Foundation JSON-like normalization and stable serialization;
- deterministic discovery ordering;
- Kernel configuration precedence and diagnostics;
- module graph permutation invariance;
- deterministic topological ordering;
- provider/container ordering;
- fingerprint invariance and sensitivity;
- fixed cross-platform fingerprint vectors;
- container graph fingerprint stability;
- fixed cross-platform compiled-container byte vectors;
- generation identity validation;
- identical-generation reuse;
- concurrent generation publication;
- physical-layout artifact reproducibility;
- exact artifact byte drift detection;
- mtime/permission irrelevance where non-semantic;
- immutable generation validation;
- runtime boot failure classification;
- deterministic verifier projections;
- runtime-driver conflict diagnostics;
- Worker task-source ambiguity permutations;
- Worker child-order permutations;
- deterministic Worker exception and lifecycle classifications.

The canonical framework package test suite runs on both Ubuntu and Windows CI.

Tests are executable evidence of this contract. Individual test names are not themselves part of the architecture contract and may change without changing the guarantees defined here.

---

# Non-goals

This contract does not require:

- identical process ids;
- identical runtime timestamps or durations;
- identical scheduler interleavings;
- identical security credentials;
- identical transport-correlation identifiers;
- identical temporary filesystem locations;
- elimination of randomness from security or atomic-publication mechanisms;
- sorting every list regardless of semantic meaning;
- a single global determinism runner for all Coretsia subsystems.

Repository/tooling generator idempotence is a separate determinism rail from production package determinism.

The production rule remains:

```
canonical semantic inputs
+
controlled entropy boundaries

↓

canonical semantic results
```
