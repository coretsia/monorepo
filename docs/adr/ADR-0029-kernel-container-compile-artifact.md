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

# ADR-0029: Kernel compiled container artifact

## Status

Accepted.

## Context

Epic `1.340.0` defines the REAL Kernel-owned compiled-container artifact payload for the existing `container@1` artifact identity.

The global artifact registry contains the Kernel-owned `container@1` artifact identity. That registry, the canonical artifact envelope, canonical header fields, and deterministic serialization law are owned by:

```text
docs/ssot/artifacts.md
```

Current Kernel-produced `container@1` artifacts use REAL compiled-container semantics:

```text
kind = compiled
compiled = true
```

The unsupported transitional stub payload shape is invalid for production artifact-only runtime boot:

```text
kind = stub
compiled = false
```

The live payload law and artifact-only runtime boot semantics are owned by:

```text
docs/ssot/compiled-container.md
```

Kernel artifact production, fingerprint behavior, artifact writing, and cache verification linkage remain owned by:

```text
docs/ssot/artifacts-and-fingerprint.md
docs/ssot/cache-verify.md
```

Foundation container ordering, binding collision behavior, tag ordering, and tag dedupe semantics remain Foundation-owned and are referenced by compiled-container semantics rather than redefined here.

## Decision

### Decision 1: Keep `container@1` in this epic

This epic keeps the existing artifact identity:

```text
container@1
```

This epic does not introduce:

```text
container@2
```

`1.340.0` defines the REAL `container@1` payload schema for the registered `container@1` artifact identity.

A future `container@2` is required only if a later change needs to preserve the REAL `container@1` payload contract while introducing an incompatible compiled-container payload format.

### Decision 2: Reject the unsupported transitional stub payload

The unsupported transitional payload shape is:

```text
kind = stub
compiled = false
```

It is not a supported production runtime container format.

Kernel-produced `container@1` artifacts use REAL compiled-container semantics:

```text
kind = compiled
compiled = true
```

Production artifact-only runtime boot must reject the unsupported transitional stub payload.

### Decision 3: Define the first REAL `container@1` payload schema in SSoT

The first REAL `container@1` payload schema is defined by:

```text
docs/ssot/compiled-container.md
```

This ADR records the architectural decision to introduce that REAL payload.

It does not duplicate the full payload schema.

The SSoT owns:

- top-level payload fields;
- service definition schema;
- service definition lifecycle semantics;
- compiled alias lifecycle semantics;
- parameter bag schema;
- alias schema;
- tag schema;
- closure/callable rejection semantics;
- artifact-only runtime boot inputs;
- missing/invalid artifact failure semantics;
- unsupported stub rejection semantics.

### Decision 4: Use descriptor-based, closure-free compile input

Compiled-container input is descriptor-based and closure-free.

The selected compile model is:

```text
descriptor stream
  -> ContainerCompiler
  -> DefinitionGraph
  -> CompiledContainerBuilder
  -> container@1 artifact envelope
```

`ContainerCompiler` consumes explicit deterministic descriptors and produces a deterministic `DefinitionGraph`.

The descriptor stream order is caller-owned and semantically significant.

`ContainerCompiler` must not globally sort providers, modules, or descriptors before applying binding collision semantics.

The compiled graph must preserve Foundation-aligned semantics:

- later service binding overrides earlier service binding for the same service id;
- later alias binding overrides earlier alias binding for the same alias;
- later parameter binding overrides earlier parameter binding for the same parameter name;
- tag duplicate handling remains first-wins per `(tag, serviceId)`;
- tag discovery order remains `priority DESC, id ASC`.

The compiled graph must contain deterministic schema data only.

It must not contain:

- closures;
- anonymous functions;
- callable objects;
- raw PHP callable arrays as runtime payload data;
- object instances;
- resources;
- reflection objects;
- source snippets;
- absolute paths;
- raw env values;
- raw config values;
- secrets;
- timestamps;
- process-specific bytes.

Factory behavior is represented through deterministic schema data such as class references, service ids, method names, service references, parameter references, and scalar/list/map arguments.

Factory behavior is not represented by serialized PHP callables or closures.

### Decision 5: Use artifact-only production runtime boot

Production runtime boot paths covered by this epic use compiled-artifact boot.

The selected production boot policy is:

```text
container.php must exist
container.php must be a valid REAL container@1 artifact
runtime boot builds the Foundation container from artifact-owned inputs
```

Production runtime boot must use:

```text
CompiledContainerFactory
```

Production runtime boot must not silently fall back to provider-based container construction when `container.php` is missing or invalid.

Production runtime boot must not compile a new container.

Production runtime boot must not read source config files.

Production runtime boot must not run source config discovery.

Production runtime boot must not run module discovery.

Production runtime boot must not calculate fingerprints.

Production runtime boot must not write artifacts.

Production runtime boot must not mutate existing artifacts.

### Decision 6: Runtime boot inputs are `container@1` plus already-read/validated `config@1`

Runtime boot uses exactly these artifact-owned inputs:

```text
container@1
already-read/validated config@1 payload
```

`container@1` provides compiled service definitions, aliases, parameters, and tags.

The already-read and already-validated `config@1` payload provides the runtime Foundation container config snapshot.

`CompiledContainerFactory` receives the `config@1` payload from its caller.

Reading, parsing, and schema-validating `config@1` remain owned by Kernel artifact reading/schema infrastructure outside `CompiledContainerFactory`.

`CompiledContainerFactory` must not read source config files and must not run source config discovery.

### Decision 7: Keep provider-based construction only outside production artifact-only boot

Provider-based container construction remains allowed only for:

- compile-time artifact production;
- test scaffolding;
- explicitly documented non-production paths outside this epic.

It is not a production runtime fallback for missing or invalid `container.php`.

Any future developer-mode fallback requires a separate epic and ADR.

This epic must not imply such a fallback.

### Decision 8: Missing and invalid artifact failures are deterministic

If the required `container.php` artifact is missing during artifact-only production runtime boot, boot must fail with:

```text
CORETSIA_CONTAINER_ARTIFACT_MISSING
container-artifact-missing
```

If `container.php` exists but cannot be accepted as a production REAL `container@1` artifact, boot must fail with:

```text
CORETSIA_CONTAINER_ARTIFACT_INVALID
container-artifact-invalid
```

Invalid artifact failure covers unreadable, read-failed, return-type-invalid, envelope-invalid, header-invalid, schema-version-invalid, payload-invalid, schema-invalid, legacy-stub, and non-REAL `container@1` artifacts.

Failure diagnostics must not expose:

- absolute paths;
- configured path strings;
- raw artifact payloads;
- raw config values;
- raw env values;
- PHP warning text;
- OS error messages;
- closure dumps;
- source snippets;
- stack traces;
- throwable messages;
- previous throwable messages.

## Consequences

### Positive consequences

- `container@1` keeps a stable artifact identity while replacing the transitional stub semantics with the first REAL compiled-container payload.
- Production runtime boot becomes explicit and deterministic.
- Missing or invalid `container.php` artifacts fail hard instead of silently switching to a different runtime construction mode.
- Compiled-container input is constrained to deterministic descriptor data instead of runtime closures or PHP callable payloads.
- Service definitions, aliases, parameters, and tags are represented as artifact schema data.
- The compiled container can be validated by schema semantics rather than PHP object identity.
- The compiled container remains compatible with the global artifact envelope and registry law.
- The REAL payload law is centralized in `docs/ssot/compiled-container.md`.

### Trade-offs

- Production runtime boot requires `container.php` to exist.
- A cold cache without generated artifacts is no longer a valid production runtime boot state.
- Provider-based fallback is intentionally unavailable in production paths covered by this epic.
- Developer-mode fallback, if ever needed, must be designed explicitly in a later epic/ADR.
- Compile input must be transformed into deterministic descriptors before it can become a compiled artifact.
- Runtime closures and raw PHP callable arrays cannot cross into the compiled graph.

### Operational consequences

Artifact production must run before production artifact-only runtime boot.

Cache verification may classify `container.php` as missing, dirty, invalid, or clean according to Kernel cache verification semantics, but verification itself must not repair or write artifacts.

Artifact production writes artifacts.

Artifact-only runtime boot reads artifacts.

Those responsibilities are intentionally separate.

## Rejected Alternatives

### Alternative 1: Introduce `container@2` immediately

Rejected.

`1.330.0` did not define a stable production compiled-container payload. It created a transitional stub payload under the already-registered `container@1` identity.

Introducing `container@2` for the first REAL compiled-container payload would incorrectly treat the transitional stub as if it were a stable production payload contract.

The selected design keeps `container@1` and defines its first REAL payload schema in `1.340.0`.

### Alternative 2: Preserve the legacy stub payload as a supported production runtime format

Rejected.

The stub payload cannot build a real runtime container from compiled service definitions, aliases, parameters, and tags.

Keeping it as a supported production runtime format would make `container@1` ambiguous and would weaken artifact-only boot semantics.

The selected design treats the stub as transitional and unsupported for production runtime boot.

### Alternative 3: Serialize provider closures or PHP callables into the artifact

Rejected.

Closures, anonymous functions, callable objects, raw callable arrays, reflection metadata, source snippets, and runtime callable payloads are not deterministic compiled-container schema data.

They are not suitable for stable artifact bytes, schema validation, or safe diagnostics.

The selected design uses descriptor-based, closure-free compile input.

### Alternative 4: Re-run providers as a production runtime fallback

Rejected.

Provider fallback would make production runtime behavior depend on non-artifact source state and would blur the boundary between compile-time artifact production and production runtime boot.

The selected design requires production boot to use the compiled artifact and fail deterministically when the artifact is missing or invalid.

### Alternative 5: Let `CompiledContainerFactory` read source config files

Rejected.

Runtime config snapshot input is `config@1`.

Reading source config files during compiled-container runtime boot would violate artifact-only boot and duplicate config compilation responsibilities.

The selected design requires the caller to pass an already-read and already-validated `config@1` payload.

## Validation and Testing Expectations

This decision should be locked by tests covering:

- identical compiled-container inputs produce identical `container.php` bytes;
- REAL `container@1` payload uses `kind = compiled` and `compiled = true`;
- legacy `kind = stub`, `compiled = false` payloads are rejected for production runtime boot;
- missing `container.php` fails with `CORETSIA_CONTAINER_ARTIFACT_MISSING`;
- invalid `container.php` fails with `CORETSIA_CONTAINER_ARTIFACT_INVALID`;
- `CompiledContainerFactory` builds a runtime Foundation container from a REAL `container@1` artifact and an already-read/validated `config@1` payload;
- runtime boot does not read source config files;
- runtime boot does not run module discovery;
- runtime boot does not run provider fallback;
- runtime boot does not compile a new container;
- descriptor-based compile input rejects closures and callable payloads before artifact write;
- compiled aliases remain non-shared delegation wrappers;
- service definition `shared` lifecycle is preserved;
- Foundation tag ordering and first-wins dedupe are preserved.

## Related SSoT

- `docs/ssot/compiled-container.md`
- `docs/ssot/artifacts.md`
- `docs/ssot/artifacts-and-fingerprint.md`
- `docs/ssot/cache-verify.md`
- `docs/ssot/observability.md`
- `docs/ssot/di-tags-and-middleware-ordering.md`
- `docs/ssot/reset-tags.md`

## Related ADR

- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/adr/ADR-0019-enhanced-reset-long-running.md`
- `docs/adr/ADR-0020-kernel-runtime-uow-spi.md`
- `docs/adr/ADR-0028-kernel-artifacts-fingerprint-cache-verify.md`

## Related epic

- `1.330.0 Kernel: Artifacts + fingerprint + cache verify`
- `1.340.0 Kernel: Container compile (REAL) + container.php artifact`
