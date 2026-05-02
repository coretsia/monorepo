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

# ADR-0001: Module descriptor, manifest reader, and mode preset contracts

## Status

Accepted.

## Context

Coretsia needs a stable contracts surface that allows the future Kernel owner package to build a deterministic module plan without coupling `core/contracts` to runtime package implementations, filesystem scanning, platform code, integrations, HTTP abstractions, or vendor-specific infrastructure.

The Phase 0 workspace package-index prototype cemented the canonical package metadata shape:

```text
{layer, slug, path, composerName, psr4, kind, moduleClass?}
```

The Kernel must be able to derive module identity from this metadata in a deterministic way. Contracts must define the stable ports and value objects used by the Kernel and other runtime owners, but contracts must not implement discovery, filesystem traversal, Composer scanning, dependency resolution, DI wiring, or mode preset storage.

Coretsia also needs canonical mode preset contracts for the public mode vocabulary:

```text
micro
express
hybrid
enterprise
```

The contracts must remain format-neutral. A mode preset may later be stored as PHP, JSON, YAML, generated artifact, Composer metadata, or another owner-defined representation, but contracts must not expose that storage format.

## Decision

Introduce module and mode contracts under:

```text
framework/packages/core/contracts/src/Module/
```

The contracts introduced by epic `1.70.0` define:

- `ModuleId`
- `ModuleDescriptor`
- `ModuleManifest`
- `ModuleInterface`
- `ManifestReaderInterface`
- `ModePresetInterface`
- `ModePresetLoaderInterface`
- `Capability/CapabilityInterface`

The canonical module id format is:

```text
<layer>.<slug>
```

The module id is derived only from package-index metadata fields:

```text
layer
slug
```

Composer/package-index metadata may provide optional descriptor input fields such as `composerName`, `psr4`, `kind`, or `moduleClass`, but these fields must not affect module identity.

Only fields explicitly defined by the descriptor exported shape are exposed by `ModuleDescriptor::toArray()`.

Contracts define metadata-only discovery semantics.

`ModuleManifest` is the contracts-level deterministic wrapper for installed module descriptors.

It rejects duplicate module ids, exposes stable lookup by module id, and returns module descriptors sorted by module id using byte-order `strcmp`.

`ManifestReaderInterface` is a port for reading the installed `ModuleManifest`; it does not prescribe how the implementation discovers or loads the manifest.

The Kernel owner package is responsible for implementing concrete manifest reading later. A future Kernel implementation may use the Phase 0 workspace package-index shape as its canonical metadata source, but this implementation is not part of `core/contracts`.

Mode presets are represented by stable contracts only.

`ModePresetInterface` exposes schema version, preset name, description, required module ids, optional module ids, explicitly disabled module ids, a compatibility module id projection, feature bundle policy knobs, metadata, and a deterministic exported scalar/json-like shape.

`ModePresetLoaderInterface` lists available preset names, checks preset availability, loads presets by name, and provides a nullable `tryLoad()` convenience method.

The mode preset contracts do not expose their storage format.

## Determinism

Any contract API returning a set or list of module descriptors must define stable ordering:

```text
sort by moduleId ascending using byte-order strcmp
```

This ordering is locale-independent and must not depend on:

- filesystem traversal order
- Composer package declaration order
- PHP hash-map insertion side effects
- `setlocale`
- `LC_ALL`
- platform-specific path casing

Any exported map-like data must be deterministic:

- string keys sorted recursively by byte-order `strcmp`
- list order preserved
- no timestamps
- no absolute paths
- no environment-specific bytes
- no secrets or raw environment values

## Contracts boundary

`core/contracts` must not depend on:

- `platform/*`
- `integrations/*`
- PSR-7 / HTTP message interfaces
- vendor-specific concrete APIs such as PDO, Redis, S3, Prometheus, or similar infrastructure namespaces
- runtime package implementations
- tooling packages
- generated architecture artifacts

The contracts package defines ports and value objects only.

## Descriptor boundary

`ModuleDescriptor` is a contracts descriptor/value object.

It is not a DTO-marker class by default.

Descriptor invariants are governed by the contracts shape rules in:

```text
docs/ssot/modules-and-manifests.md
```

They are not governed by DTO gate rules unless a future owner epic explicitly opts descriptor classes into the DTO marker policy.

The scalar/json-like field restriction applies to the exported descriptor shape, not to every internal field of the value object.

`ModuleDescriptor` may use internal VO fields such as `ModuleId`, but its canonical exported shape must expose those values only as deterministic scalar/json-like fields.

## Security and redaction

Contracts must not force or encourage storage of secrets.

Module descriptors and mode presets must not expose:

- raw `.env` values
- credentials
- tokens
- private keys
- cookies
- authorization headers
- request or response bodies
- private customer data
- absolute local paths

If implementations need secret-backed behavior, they must model it through runtime owner packages and redacted references, not through `core/contracts` descriptor metadata.

## Consequences

Positive consequences:

- Kernel can depend on stable contracts without depending on platform or integration packages.
- Module planning can be deterministic across operating systems.
- Mode presets can evolve independently from storage format.
- Contracts stay format-neutral and runtime-safe.
- Module identity remains stable because it is derived from `{layer, slug}` only.

Trade-offs:

- Contracts do not provide discovery implementation.
- Contracts do not validate actual Composer metadata at runtime.
- Contracts do not define DI wiring or Kernel boot behavior.
- A later Kernel owner epic must implement `ManifestReaderInterface`.

## Non-goals

This ADR does not implement:

- Kernel module discovery
- Composer manifest reading
- filesystem scanning
- package-index generation
- DI container wiring
- module boot lifecycle
- CLI commands such as `coretsia debug:modules`
- mode preset storage format
- generated Kernel artifacts

## Related SSoT

- `docs/ssot/modules-and-manifests.md`
- `docs/ssot/modes.md`

## Related epic

- `1.70.0 Contracts: Module / Descriptor / Manifest + ModePreset ports`
