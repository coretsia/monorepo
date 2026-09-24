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

```yaml
ssotVersion: 1
status: pre-stable
owner: core/contracts
```

## Scope

This document is the Single Source of Truth for Coretsia mode names, mode preset meaning, mode preset contract semantics, and deterministic mode preset loading policy.

This document governs contracts introduced by epic `1.70.0` under:

```text
packages/core/contracts/src/Module/
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Canonical mode and preset names

Coretsia defines these canonical mode and preset names:

```text
micro
express
hybrid
enterprise
```

These names are reserved for Coretsia-owned canonical presets.

Owner-defined custom preset names are non-canonical names.

Owner-defined custom preset names MUST NOT use Coretsia canonical preset names.

Mode and preset names are lowercase ASCII strings.

Mode and preset names MUST be compared byte-for-byte.

Mode and preset name handling MUST NOT depend on:

- locale
- `setlocale`
- `LC_ALL`
- filesystem casing
- translated display labels

Display labels MAY use uppercase or title case in documentation or UI, but contract-level mode and preset names remain lowercase.

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

The Coretsia-owned `express` preset is the conventional HTTP/web application mode.

The canonical PHP resources declare only the currently implemented runtime-module subset of the intended mode policy in `docs/architecture/STRUCTURE.md`. `platform.http` joins every applicable resource when its runtime descriptor is implemented; an unimplemented future module is not a selected root.

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

## Preset policy and schema

`ModePreset` is a policy source; it is not `ModuleSelection` or `ModulePlan`. The canonical mode schema version is **1**. Each PHP preset payload has exactly these top-level keys:

```text
schemaVersion
name
description
required
modules
featureBundles
metadata
```

- `required`: list of non-excludable, canonical runtime `ModuleId` strings required by that mode.
- `modules`: list of selectable, canonical runtime `ModuleId` strings that an application may explicitly exclude.
- `featureBundles`: `array<string,mixed>` deterministic JSON-like policy map; `metadata` is a deterministic JSON-like map.
- Both collections are lists, have unique ids within each raw input, and have an empty intersection. Duplicate ids, non-canonical input spelling, path-like metadata, malformed schema, or unexpected top-level keys fail deterministically through `ModePresetInvalidException`.
- The Kernel constructor independently rejects associative/duplicate/overlapping module-id collections before canonicalizing their order by byte-order `strcmp`.

Preset names are non-empty lowercase ASCII tokens of at most 64 bytes, beginning with `[a-z]` and containing only `[a-z0-9-]`. Descriptions are `null` or non-empty safe strings of at most 512 bytes, without control characters or path-like content. `featureBundles` and `metadata` maps have maximum nesting depth 16, maximum 256 keys per map and maximum 1024-byte strings, with safe path-free keys/values. Values are restricted to `null`, `bool`, `int`, `string`, lists or maps of those values; floats, objects, closures and resources are forbidden. String map keys are recursively sorted by byte-order `strcmp`, while list order is preserved.

`ModePresetInterface` exposes exactly `schemaVersion(): int`, `name(): string`, `description(): ?string`, `required(): list<ModuleId>`, `modules(): list<ModuleId>`, `featureBundles(): array<string,mixed>`, `metadata(): array<string,mixed>`, and `toArray(): array<string,mixed>`. Its canonical name constants are `MICRO`, `EXPRESS`, `HYBRID`, and `ENTERPRISE`. Its exported shape uses exactly the seven keys shown above and exports module ids as strings, not PHP objects. Source payloads may not contain secrets, absolute paths, closures, resources, or runtime services.

## Contract format neutrality

`ModePresetInterface` and `ModePresetLoaderInterface` describe value and loading contracts, not a storage format. The Kernel's current canonical and custom sources are PHP files under their separately owned roots; contracts do not expose those paths, parsing mechanisms, implementation sources, or namespace-selection enum as public method parameters. The Kernel alone owns concrete source validation and loading.

## Canonical and custom namespace ownership

`PresetNamespaceResolver` classifies a safe requested name without filesystem access: `micro`, `express`, `hybrid`, and `enterprise` are canonical; other safe names are custom. The four reserved canonical names derive from the existing `ModePresetInterface` constants.

- Canonical mode presets belong to the Kernel package, under `<kernelPackageRoot>/<kernel.modes.defaults_path>/<name>.php`, normally `packages/core/kernel/resources/modes/`. They are resolved only by `CanonicalPresetSource`.
- Custom mode presets belong to the application, under `<applicationRoot>/<kernel.modes.overrides_path>/<name>.php`, normally `<applicationRoot>/config/modes/`. They are resolved only by `CustomPresetSource`.
- An application MUST NOT create a reserved canonical preset filename in its configured custom source directory. Regular files and symbolic links, including dangling links, fail with `CanonicalPresetOverrideException` even when another preset is selected.
- Namespace ownership is determined by name, not candidate existence. A missing custom file never falls back to the Kernel directory; a canonical name never loads an application file.
- Both configured source directories must remain within their resolved owning root, and existing individual files must remain within their bound directory. Directory escapes and dangling/external file links fail with safe `ModePresetInvalidException`. A valid missing custom directory still permits a declared fingerprint candidate.

A `ModePresetLoaderInterface` instance is bound to exactly one owner-defined source and keeps its existing four public methods: `listNames()`, `has(string $name)`, `load(string $name)`, `tryLoad(string $name)`. Methods never search the other namespace; `load()` throws `ModePresetNotFoundException` only for a genuinely absent in-namespace entry, and `tryLoad()` returns `null` only for a genuinely absent entry. Existing boundary-invalid or unreadable entries fail safely instead of being treated as missing. `listNames()` is unique and `strcmp`-sorted.

`ModePresetLoaderFactory::sourceCandidateFor()` emits one deterministic fingerprint candidate for the selected namespace, independently of file existence and without executing preset PHP. Its fields are `path`, `filesystemPath`, `sourceId`, `precedence`; the canonical candidate has source id `core/kernel:<defaults_path>/<name>.php` and precedence `10`, while a custom candidate has source id `application:<overrides_path>/<name>.php` and precedence `20`. Precedence is source identity metadata, not fallback order. An invalid existing namespace-owned entry fails before fingerprint reading.

## Effective runtime selection

For the selected app target, `BootstrapConfig::moduleOverrides()` holds immutable `ResolvedModuleOverrides` (`include`/`exclude` lists of canonical `ModuleId` objects); missing input is the empty value. The Phase A bootstrap loader validates all raw per-target shapes, preserves source duplicates, and the resolver rejects malformed/non-canonical ids, duplicates and include/exclude overlap using safe `BootstrapException::REASON_OVERRIDES_INVALID`.

`ModuleSelectionFactory` takes the loaded `ModePreset` plus resolved overrides. It rejects `required ∩ exclude`, and computes `roots = ((required ∪ modules ∪ include) − exclude)` and `excluded = exclude`. Output sets are immutable unique strcmp-sorted lists with no intersection. It does not consult an installed manifest or traverse the dependency graph. The `ModuleSelection` is compile-host-only, consumed by Phase B, and is neither a runtime seed nor part of the public contracts API.

`ModePreset != ModuleSelection != ModulePlan`.

## Determinism and security

Preset names are safe lowercase ASCII tokens and are compared byte-for-byte. Schema validation rejects unsafe strings, invalid list shapes and disallowed JSON-like values. Source discovery never executes PHP during candidate inspection, never searches the other namespace and never leaks filesystem paths, raw source payloads, credentials or previous exception messages. Logical preset loading and set normalization use byte-order `strcmp`, not filesystem traversal order, locale, or Composer declaration order.

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
- `devtools/*` packages
- repository tooling under `tools/**`
- generated architecture artifacts

## Non-goals

This SSoT does not define:

- Composer package installation or dependency synchronization;
- future runtime package implementation or independent runtime-module descriptors;
- provider/DI graph compilation or HTTP middleware behavior;
- CLI command behavior or runtime service registration;
- an additional public namespace enum or a second preset storage format;
- an additional artifact identity or schema version.
