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

# Modes SSoT

## Scope

This document is the Single Source of Truth for Coretsia mode names, mode preset meaning, mode preset contract semantics, and deterministic mode preset loading policy.

This document governs contracts introduced by epic `1.70.0` under:

```text
framework/packages/core/contracts/src/Module/
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Canonical mode names

Coretsia defines these canonical mode names:

```text
micro
express
hybrid
enterprise
```

Mode names are lowercase ASCII strings.

Mode names MUST be compared byte-for-byte.

Mode name handling MUST NOT depend on:

- locale
- `setlocale`
- `LC_ALL`
- filesystem casing
- translated display labels

Display labels MAY use uppercase or title case in documentation or UI, but contract-level mode names remain lowercase.

## Mode meaning

### `micro`

`micro` is the minimal runtime mode.

It emphasizes:

- small services
- APIs
- focused CLI-oriented workloads
- minimal runtime surface
- low framework ceremony

### `express`

`express` is the application mode for conventional web workflows.

It emphasizes:

- HTTP application structure
- routing
- validation
- persistence-oriented workflows
- typical web application concerns

### `hybrid`

`hybrid` is the mode for mixed synchronous and asynchronous systems.

It emphasizes:

- background processing
- queues
- events
- scheduler-oriented behavior
- more complex business flows

### `enterprise`

`enterprise` is the mode for larger systems with stricter operational and platform requirements.

It emphasizes:

- advanced operational concerns
- larger deployment surfaces
- stricter architecture boundaries
- broader platform integration
- enterprise-grade governance and observability

## Mode preset

A mode preset is a deterministic module-selection profile.

A mode preset MUST have a canonical preset name.

A mode preset MAY contain:

- module ids
- package selectors
- capability requirements
- metadata needed by the Kernel owner package
- human-readable description

A mode preset MUST NOT contain:

- secrets
- raw environment values
- absolute paths
- closures
- resources
- runtime service instances
- HTTP request or response objects
- vendor-specific runtime clients

## Mode preset format neutrality

Contracts define mode preset shape and loading semantics only.

Contracts MUST NOT require a concrete source format.

A mode preset MAY later be stored as:

- PHP file
- JSON file
- YAML file
- generated artifact
- Composer metadata
- another owner-defined source

The storage format is outside `core/contracts`.

## Mode preset ownership

Framework-owned mode presets are framework policy.

Project-specific overrides are user-owned and belong outside contracts.

Contracts MUST NOT define skeleton override resolution.

Contracts MUST NOT implement mode preset discovery.

Contracts MUST NOT register mode presets in DI.

## ModePresetInterface policy

`ModePresetInterface` is a contracts accessor for a loaded mode preset.

It MUST expose deterministic preset data without leaking the source format.

The interface shape is:

```text
name(): string
description(): ?string
moduleIds(): list<ModuleId>
metadata(): array<string,mixed>
```

The canonical preset name constants are:

```text
MICRO = micro
EXPRESS = express
HYBRID = hybrid
ENTERPRISE = enterprise
```

`moduleIds()` returns contract-level `ModuleId` value objects.

This is an internal contracts API shape, not an exported scalar descriptor shape.

Any implementation returning module ids MUST return them in deterministic order unless a future SSoT explicitly defines semantic list order.

Module id list ordering rule:

```text
sort by moduleId value ascending using byte-order strcmp unless the API explicitly documents semantic list order
```

`metadata()` MUST return deterministic JSON-like preset metadata.

Allowed metadata value types are:

- `null`
- `bool`
- `int`
- `string`
- list of allowed metadata values
- map with string keys and allowed metadata values

Preset metadata MUST NOT contain:

- floating-point values
- PHP objects
- closures
- resources
- streams
- filesystem handles
- service instances
- runtime wiring objects

Map ordering rule:

```text
sort string keys recursively by byte-order strcmp
```

## ModePresetLoaderInterface policy

`ModePresetLoaderInterface` is a contracts port for loading a mode preset by name.

The interface shape is:

```text
load(string $name): ModePresetInterface
```

The loader input MUST be a canonical lowercase mode preset name.

The loader MUST NOT require callers to pass a filesystem path.

The loader MUST NOT expose whether the preset came from PHP, JSON, YAML, Composer metadata, generated artifact, or another implementation source.

A concrete loader implementation belongs to a future owner package.

## Canonical preset names and compatibility

The canonical names are reserved:

```text
micro
express
hybrid
enterprise
```

Future mode names MAY be introduced only through an SSoT update.

Changing the meaning of an existing canonical mode requires roadmap and SSoT review.

Removing an existing canonical mode is a breaking policy change.

## Determinism

Mode preset loading MUST be deterministic.

The same preset source and the same installed package metadata MUST produce the same logical preset result.

Mode preset behavior MUST NOT depend on:

- filesystem traversal order
- Composer package declaration order
- process locale
- environment-specific paths
- timestamps
- random values
- host machine identity

## Security and redaction

Mode presets MUST NOT contain secrets.

Mode preset diagnostics and exported metadata MUST NOT expose:

- `.env` values
- credentials
- tokens
- private keys
- cookies
- authorization headers
- request bodies
- response bodies
- private customer data
- absolute local paths

## Contracts dependency policy

Mode preset contracts MUST remain format-neutral.

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

## Non-goals

This SSoT does not define:

- concrete preset file format
- preset file paths
- skeleton override resolution
- Kernel mode compilation
- generated Kernel module plan artifact
- CLI command behavior
- DI tags
- runtime service registration
- HTTP middleware behavior
