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

# coretsia/core-foundation

`core/foundation` is the **Foundation runtime** package for the Coretsia Framework monorepo.

**Scope:** PSR-11 DI container runtime, deterministic service tags, canonical discovery ordering, stable diagnostics serialization, and reset orchestration for long-running runtimes.

**Out of scope:** kernel lifecycle execution, HTTP middleware stack implementation, CLI command execution, platform adapters, integrations, and tooling-only behavior.

## Package identity (Prelude rules)

- **Path:** `framework/packages/core/foundation`
- **Package id:** `core/foundation`
- **Composer name:** `coretsia/core-foundation`
- **Module id:** `core.foundation`
- **Namespace:** `Coretsia\Foundation\*` (PSR-4: `src/`)
- **Kind:** runtime

Monorepo versioning is **repo-wide only** via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions **MUST NOT** be used.

## Dependency policy (Phase 1)

This package is runtime-safe and intentionally small:

- **Depends on:**
  - `core/contracts`
  - `psr/container`
- **Forbidden:**
  - `platform/*`
  - `integrations/*`
  - `devtools/*`

`core/foundation` MUST NOT depend on Phase 0 tooling packages such as `devtools/internal-toolkit` or `devtools/cli-spikes`.

## Runtime responsibilities

This package provides the baseline runtime mechanisms used by higher-level packages:

- PSR-11 container runtime.
- Deterministic container build behavior from caller-supplied providers.
- Canonical tag registry for service discovery lists.
- Canonical deterministic ordering rule: `priority DESC, id ASC`.
- Reset orchestration through the effective Foundation reset discovery tag.
- Stable JSON encoding for diagnostics and runtime-safe artifacts.

`core/kernel` owns lifecycle trigger points.

`core/foundation` owns the reusable runtime mechanisms that kernel and platform packages consume.

## Configuration

The package owns the `foundation` configuration root.

Defaults live in:

```text
framework/packages/core/foundation/config/foundation.php
```

The defaults file MUST return the subtree only and MUST NOT repeat the root key.

Valid shape:

```php
return [
    'container' => [
        'autowire_concrete' => true,
        'allow_reflection_for_concrete' => true,
    ],
    'reset' => [
        'tag' => 'kernel.reset',
    ],
];
```

Invalid shape:

```php
return [
    'foundation' => [
        'container' => [],
    ],
];
```

Runtime code reads from the global configuration under `foundation.*`.

Canonical keys introduced by this package:

- `foundation.container.autowire_concrete`
- `foundation.container.allow_reflection_for_concrete`
- `foundation.reset.tag`

`foundation.reset.tag` defines the effective reset discovery tag.
The reserved default value is `kernel.reset`.

Tag discovery and reset orchestration MUST NOT be feature-disabled through config.
Empty discovery lists are represented by empty-list semantics only.

## DI container

The package provides a PSR-11-compatible container implementation.

Container behavior is deterministic:

- Provider order is supplied by the caller.
- `ContainerBuilder` MUST preserve caller-supplied provider order exactly.
- `ContainerBuilder` MUST NOT globally sort providers by FQCN.
- Later container bindings override earlier container bindings deterministically.
- Tag dedupe remains independent: `TagRegistry` keeps the first occurrence per `(tag, serviceId)`.

Autowiring is strict:

- Interfaces MUST NOT be autowired.
- Missing `config['foundation']` MUST fail deterministically.
- Missing `config['foundation']['container']` MUST fail deterministically.

## Tags and deterministic discovery

`Coretsia\Foundation\Tag\TagRegistry` is the single source of truth for tagged service discovery lists.

Canonical ordering:

```text
priority DESC, id ASC
```

Ordering MUST be locale-independent and use byte-order string comparison (`strcmp`).

Dedupe policy:

- For the same `(tag, serviceId)`, the first occurrence wins.
- Consumers MUST NOT re-sort `TagRegistry->all($tag)` output.
- Consumers MUST NOT apply a different dedupe rule.

Reserved Foundation-owned tags:

- `kernel.reset`
- `kernel.stateful`

HTTP middleware tags such as `http.middleware.app` are owned by `platform/http`; Foundation provides the registry and deterministic ordering mechanism only.

## Reset orchestration

`Coretsia\Foundation\Runtime\Reset\ResetOrchestrator` executes reset discipline for services discovered through the effective Foundation reset discovery tag.

The orchestrator MUST:

- read the effective discovery tag from Foundation configuration/wiring;
- default to `kernel.reset`;
- obtain the discovery list only through `TagRegistry->all($effectiveResetTag)`;
- resolve services through PSR-11 container access;
- call `Coretsia\Contracts\Runtime\ResetInterface::reset()` exactly once per resolved service per reset cycle;
- be safely callable when the discovery list is empty;
- preserve the exact `TagRegistry` order in legacy/base mode;
- never rely on reflection/autowire during reset execution;
- never emit stdout or stderr.

`core/kernel` MUST call only the reset orchestrator and MUST NOT enumerate tagged reset services directly.

## Stable diagnostics and serialization

`core/foundation` provides stable serialization primitives for diagnostics and runtime-safe outputs.

Diagnostics MUST be safe by construction:

- MUST NOT dump service instances.
- MUST NOT dump constructor arguments.
- MUST NOT dump reflection data.
- MUST NOT include arbitrary tag metadata values.
- MUST NOT leak secrets, tokens, cookies, authorization headers, env values, PII, or absolute local paths.

Stable JSON output MUST:

- sort map keys recursively by `strcmp`;
- preserve list order;
- use LF-only line endings;
- end with a final newline;
- reject floats, objects, resources, closures, and non-string map keys.

Redaction of caller-owned payloads remains the caller's responsibility.
The encoder itself does not inspect environment variables.

## Observability

This package does not emit telemetry directly.

Foundation diagnostics are structural snapshots only. They MUST be deterministic and redaction-safe.

Default noop observability or logger bindings are not introduced by this package in epic `1.200.0`.

## Errors

This package defines Foundation runtime exceptions for container behavior:

- `Coretsia\Foundation\Container\Exception\ContainerException`
  - canonical error code: `CORETSIA_CONTAINER_ERROR`
- `Coretsia\Foundation\Container\Exception\NotFoundException`
  - canonical error code: `CORETSIA_CONTAINER_NOT_FOUND`

Reset misuse is a deterministic hard-fail.
The canonical reset-specific exception shape is introduced by the later reset enhancement epic.

Higher-level error mapping is owned by higher layers.

## Security / Redaction

`core/foundation` MUST NOT leak sensitive runtime data.

Forbidden in diagnostics:

- environment values;
- credentials;
- secrets;
- tokens;
- private keys;
- authorization headers;
- cookies;
- raw request or response payloads;
- raw queue messages;
- raw SQL;
- raw config payloads;
- constructor arguments;
- service instances;
- arbitrary tag metadata;
- reflection dumps;
- absolute local paths;
- private customer data / PII.

Allowed diagnostic information is limited to safe structural metadata such as service ids, tag names, priorities, schema versions, and safe derivations such as `hash(value)` / `len(value)` for potentially sensitive strings.

Runtime owners MUST prefer omission over unsafe emission.

## References

- `docs/ssot/tags.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/http-middleware-catalog.md`
- `docs/ssot/di-tags-and-middleware-ordering.md`
- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/roadmap/ROADMAP.md`
