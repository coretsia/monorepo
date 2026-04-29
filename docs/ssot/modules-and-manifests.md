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

# Modules and manifests SSoT

## Scope

This document is the Single Source of Truth for Coretsia module identity, module descriptor shape policy, manifest reader semantics, and deterministic module descriptor ordering.

This document governs contracts introduced by epic `1.70.0` under:

```text
framework/packages/core/contracts/src/Module/
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Module identity

A Coretsia module id is a stable string in this canonical format:

```text
<layer>.<slug>
```

Examples:

```text
core.kernel
platform.cli
presets.micro
integrations.redis
enterprise.audit
```

The module id MUST be derived only from package metadata fields:

```text
layer
slug
```

The canonical derivation is:

```text
moduleId = layer + "." + slug
```

Composer/package-index metadata MAY provide optional descriptor input fields such as:

```text
composerName
psr4
kind
moduleClass
```

Only fields explicitly defined by the descriptor exported shape MAY be exported by `ModuleDescriptor::toArray()`.

The package-index `kind` field maps to the exported descriptor field:

```text
packageKind
```

The `psr4` field is package-index/build metadata unless a future SSoT explicitly promotes it into the descriptor exported shape.

Filesystem paths MUST NOT be used to derive module identity at runtime.

## Module id validation

A module id MUST satisfy all of the following rules:

- it contains exactly one dot separator between layer and slug;
- layer is non-empty;
- slug is non-empty;
- layer uses lowercase ASCII letters and digits with hyphen-free normalized package-layer spelling;
- slug uses lowercase ASCII letters, digits, and hyphen-separated words;
- it contains no whitespace;
- it contains no path separators;
- it contains no namespace separators;
- it contains no leading or trailing dot;
- it is not locale-dependent.

The canonical validation pattern is:

```text
^[a-z][a-z0-9]*\.[a-z][a-z0-9]*(?:-[a-z0-9]+)*$
```

The validation rule is intentionally ASCII-only and locale-independent.

Implementations MUST NOT rely on:

- `setlocale`
- `LC_ALL`
- locale-sensitive lowercasing
- filesystem path casing

## Runtime module layer policy

The canonical package layers are defined by the packaging law.

Runtime module descriptors MAY be defined for runtime-relevant packages in these layers:

```text
core
platform
integrations
presets
enterprise
```

Tooling-only packages MUST NOT be described as runtime modules.

In particular, packages under the tooling layer are not runtime modules.

## Package-index lock-source alignment

The Phase 0 workspace package-index prototype cemented this metadata shape:

```text
{layer, slug, path, composerName, psr4, kind, moduleClass?}
```

Module identity rules in this document MUST NOT contradict that shape.

The following fields are identity inputs:

```text
layer
slug
```

The following fields are optional descriptor inputs only:

```text
composerName
psr4
kind
moduleClass
```

The `path` field is tooling/build metadata. It MUST NOT be required by contracts consumers at runtime and MUST NOT be exported as a runtime module descriptor path.

## Descriptor boundary

`ModuleDescriptor` is a contracts descriptor/value object.

It is not a DTO-marker class by default.

Descriptor invariants are governed by this SSoT and contracts shape rules, not DTO gate rules.

A future epic MAY explicitly opt a descriptor into DTO policy, but until such an epic exists, `ModuleDescriptor` MUST be treated as a contracts VO.

The canonical exported descriptor shape is the array returned by:

```text
ModuleDescriptor::toArray()
```

The scalar/json-like field restriction applies to the exported descriptor shape, not to all internal VO implementation fields.

Internal VO fields, such as `ModuleId`, MAY exist inside `ModuleDescriptor`.

Internal VO fields MUST be exported only through stable scalar/json-like descriptor fields such as:

```text
moduleId
layer
slug
```

The exported descriptor shape MUST NOT expose PHP object identity, service instances, runtime wiring objects, or implementation-only VO instances.

## Descriptor schema version

A module descriptor MUST expose a schema version.

The initial descriptor schema version is:

```text
1
```

Schema version policy:

- schema version MUST be an integer;
- schema version MUST be positive;
- schema version MUST change only when descriptor shape compatibility changes;
- adding optional metadata keys does not necessarily require a schema version bump;
- changing module id derivation requires a schema version bump and a major policy review;
- removing or changing the meaning of required descriptor fields requires a schema version bump.

## Descriptor required fields

A module descriptor MUST have these logical fields:

```text
schemaVersion
moduleId
layer
slug
```

The descriptor MUST satisfy this invariant:

```text
moduleId === layer + "." + slug
```

The `moduleId` MUST be derived from `layer` and `slug`.

The descriptor MUST NOT accept an independently conflicting module id.

## Descriptor optional fields

A descriptor MAY expose optional metadata fields, including:

```text
composerName
packageKind
moduleClass
capabilities
metadata
```

Optional fields MUST NOT affect module id derivation.

Optional fields MUST NOT be required for a package to have a valid module identity.

## Descriptor exported shape

The canonical exported descriptor shape is `ModuleDescriptor::toArray()`.

The exported descriptor shape MUST contain only deterministic scalar/json-like values.

Allowed exported descriptor value types are:

- `null`
- `bool`
- `int`
- `string`
- list of allowed values
- map with string keys and allowed values

The exported descriptor shape MUST NOT contain:

- PHP objects
- closures
- resources
- streams
- filesystem handles
- service instances
- runtime wiring objects
- implementation-only VO instances

For `ModuleDescriptor`, the exported descriptor shape MUST include these fields:

```text
schemaVersion
moduleId
layer
slug
composerName
packageKind
moduleClass
capabilities
metadata
```

The canonical top-level exported key order for `ModuleDescriptor::toArray()` is deterministic and sorted by byte-order `strcmp`:

```text
capabilities
composerName
layer
metadata
moduleClass
moduleId
packageKind
schemaVersion
slug
```

Tests MUST treat this key order as part of the descriptor shape contract.

The `moduleId`, `layer`, and `slug` fields MUST be exported as strings.

The `schemaVersion` field MUST be exported as an integer.

The `composerName`, `packageKind`, and `moduleClass` fields MUST be exported as either non-empty strings or `null`.

The `capabilities` field MUST be exported as a deterministic list of strings.

Capabilities are a stable string set at descriptor-export level:

- duplicate capability strings MUST be collapsed;
- each capability MUST be a non-empty string;
- capability strings MUST NOT contain CR or LF;
- exported capabilities MUST be sorted by byte-order `strcmp`.

The `metadata` field MUST be exported as a deterministic metadata map.

The exported descriptor shape MUST be stable across operating systems, process locales, filesystem ordering, Composer package ordering, and repeated runs.

## Descriptor metadata shape

Descriptor metadata MUST be JSON-like and deterministic.

The root `metadata` value MUST be a map with string keys.

An empty metadata map is allowed.

A non-empty list MUST NOT be used as the root metadata value.

Allowed metadata value types are:

- `null`
- `bool`
- `int`
- `string`
- list of allowed metadata values
- map with string keys and allowed metadata values

Descriptor metadata MUST NOT contain:

- floating-point values
- PHP objects
- closures
- resources
- streams
- filesystem handles
- service instances
- runtime wiring objects

Floating-point values are intentionally forbidden in descriptor metadata.

If a future owner needs decimal values, they MUST be represented as strings with a documented format.

Descriptor metadata MUST NOT be used to expose sensitive or environment-specific values, including:

- absolute paths
- raw environment values
- credentials
- tokens
- private keys
- request bodies
- response bodies
- cookies
- authorization headers
- private customer data

Descriptor metadata validation is structural.

Secret, path, and customer-data redaction rules are security policy requirements. They MUST be enforced by the producing owner package before descriptor construction.

## Exported array ordering

Any descriptor method that exports an array-like map MUST return deterministic output.

Map ordering rule:

```text
sort string keys recursively by byte-order strcmp
```

List ordering rule:

```text
preserve list order when the list order has semantic meaning
```

Set ordering rule:

```text
sort stable string sets by byte-order strcmp
```

The exported descriptor shape MUST be stable across operating systems.

## Manifest reader port

`ManifestReaderInterface` is a contracts port for reading installed module descriptors.

It MUST expose installed module descriptors without prescribing the implementation source.

A manifest reader implementation MAY read from:

- Composer metadata
- generated package index
- generated Kernel artifact
- framework-owned preset files
- another future owner-defined source

A manifest reader implementation MUST NOT be required by contracts to perform filesystem scanning.

The contracts package MUST NOT implement a concrete manifest reader.

## Manifest reader ordering

Any API returning multiple module descriptors MUST return them sorted by:

```text
moduleId ascending using byte-order strcmp
```

This ordering MUST be stable across:

- Linux
- Windows
- macOS
- different filesystems
- different Composer repository ordering
- different process locales

## Metadata-only discovery

Module discovery is metadata-only from the perspective of contracts.

Contracts define the shape and port semantics. They do not define or require:

- filesystem scan
- package directory traversal
- Composer file parsing
- runtime DI registration
- Kernel boot sequence
- module lifecycle execution

The Kernel owner package is responsible for implementing concrete module discovery later.

## Module interface

`ModuleInterface` is a minimal module contract.

A module implementing this interface MUST expose:

```text
descriptor(): ModuleDescriptor
```

`ModuleInterface` MUST NOT require runtime boot, DI registration, HTTP integration, filesystem access, or side effects.

## Capability marker policy

The module capability namespace is:

```text
Coretsia\Contracts\Module\Capability
```

`CapabilityInterface` is a marker interface.

Capability marker interfaces MUST NOT contain logic.

Capability marker interfaces SHOULD be used only to express stable, contracts-level capabilities.

## Contracts dependency policy

The module contracts MUST remain format-neutral.

They MUST NOT depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- PDO concrete APIs
- Redis concrete APIs
- S3 concrete APIs
- Prometheus concrete APIs
- vendor-specific runtime clients
- framework tooling packages
- generated architecture artifacts

## Security and redaction

Contracts MUST NOT require storing secrets.

Descriptor and manifest metadata MUST NOT expose:

- `.env` values
- credentials
- tokens
- private keys
- cookies
- authorization headers
- request or response bodies
- private customer data
- absolute local paths

Secret-backed runtime behavior belongs to runtime owner packages, not to contracts descriptors.

## Non-goals

This SSoT does not define:

- Kernel module discovery implementation
- Composer manifest reader implementation
- filesystem scanning
- generated artifact schema for Kernel module plans
- DI tags
- runtime service registration
- module boot lifecycle
- CLI debug command behavior
- HTTP behavior
- PSR-7 integration
