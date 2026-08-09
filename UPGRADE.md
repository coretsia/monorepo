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

# Upgrade Guide

This document contains migration notes between versions of the Coretsia monorepo.

## Policy (normative)

- Version source of truth is the monorepo git tag: `vMAJOR.MINOR.PATCH`.
- Per-package independent versions MUST NOT be used.
- Breaking changes in stable public API require a major version bump.
- The `0.x` development snapshot line does not provide stable API compatibility and may introduce breaking development-surface changes between minor releases.
- Deprecations follow: warn in N, remove in N+2.
- If an upgrade changes runtime behavior, shapes, invariants, determinism, or boundaries, the change MUST be reflected in SSoT docs under `docs/ssot/**`.

## Canonical invariants you must assume during upgrades

### 1) Config subtree rule (cemented)

`config/<name>.php` returns a subtree (no wrapper repeating the root key).

Runtime reads from global config under that root key (e.g. `foundation.container.*`).

If you previously relied on wrapper roots in config files, you must adjust your config files accordingly.

### 2) Managed Composer repositories (must not drift)

`repositories` blocks are managed only by:

```bash
composer sync:repos
```

During upgrades (or rebases), treat manual edits of `repositories` as invalid and expect CI/pre-commit to fail on drift.

### 3) Lock determinism (must not drift)

Lock files are committed for root/framework/skeleton. CI uses `composer install` and fails on lock drift.
If your upgrade changes dependencies, regenerate locks intentionally and commit them.

### 4) Determinism & redaction baseline

Tooling/gates/artifacts must remain deterministic and must not leak secrets/PII.
Expect upgrades to reject:

- timestamps, absolute paths, env-specific bytes in artifacts/diagnostics
- raw `.env` values, tokens, Authorization/cookies/session ids, raw payloads, raw SQL

## Upgrading (checklist)

1. Pick the target tag

- Decide the target `vMAJOR.MINOR.PATCH` you upgrade to.

2. Read release notes

- Review `CHANGELOG.md` entries between your current tag and the target tag.

3. Review SSoT deltas

- Scan `docs/ssot/**` changes in that range, focusing on:
  - shapes / outcomes / invariants
  - config rules and reserved namespaces (e.g. `@*` directives policy if relevant)
  - runtime driver composition rules (if your app uses long-running runtimes)

4. Sync managed repositories (if needed)

```bash
composer sync:check
```

5. Install dependencies using locks

```bash
composer install:all
```

6. Run the canonical rails

```bash
composer ci
```

7. Search for deprecations

- Search your app for `@deprecated` usage and follow migration notes.
- Remember: removals happen in `N+2` by policy.

## v0.x development snapshots

The `0.x` line is a development snapshot line. These tags are useful for early package publication, integration testing, and external smoke checks, but they are not stable support lines.

### From v0.5.0 to v0.6.0

#### Compatibility

- No stable API compatibility guarantee is provided for `0.x` development snapshots.
- This release changes Worker, runtime-driver, UnitOfWork, artifact, and context development boundaries.

#### Breaking changes

- Legacy HTTP runtime-driver booleans are replaced by the canonical `kernel.runtime.http_driver` selector.
- Worker-owned `TaskFactoryInternalInterface`, `QueueTaskFactory`, and `HttpTaskFactory` are removed. External task integrations now use the contracts-owned `WorkerTaskSourceInterface`, `WorkerTaskInterface`, `WorkerTaskSourceContextInterface`, and `WorkerTaskType`.
- Worker task sources are registered through the canonical `worker.task_source` tag with exactly one `task_type` metadata entry. A selected type with zero or multiple matching sources is a startup failure.
- `platform/worker` no longer provides synthetic, queue, or generic HTTP task sources. Transport acquisition and HTTP request/response handling belong to adapter packages.
- Low-level KernelRuntime lifecycle integration now uses `UnitOfWorkHandle`; started/finished timing tokens are no longer exported.
- Runtime boot artifacts now use immutable fingerprint-addressed active generations instead of independently mutable flat artifacts.
- ContextStore and ContextBag now enforce bounded object-free JSON-like values.

#### Notes

- `coretsia/platform-worker` enters the public split-package release track in `v0.6.0`.
- Worker now uses a persistent supervisor and guardian-held generation fence; Coretsia contains supervisor death by cleaning the owned worker generation before releasing its lifecycle lock.
- `max_requests` counts real acquired task attempts. Task sources must use cooperatively interruptible transport-owned waiting rather than synthetic tasks, busy polling, or `sleep`/`usleep` idle loops.
- Successful tasks use `complete(result)` and execution failures use `fail(originalThrowable)`; a `complete()` failure must not automatically invoke `fail()`.
- The `0.6` release line uses workspace version `0.6.x-dev` and public internal package constraints `^0.6.0`.

#### Migration steps

1. Review the `v0.6.0` `CHANGELOG.md` section and relevant changed SSoT documents.
2. Replace legacy HTTP runtime-driver booleans with:

```text
kernel.runtime.http_driver
```

3. Replace Worker task-factory integrations with:

```text
WorkerTaskSourceInterface
WorkerTaskInterface
WorkerTaskSourceContextInterface
WorkerTaskType
```

Do not retain compatibility wrappers around:

```text
TaskFactoryInternalInterface
QueueTaskFactory
HttpTaskFactory
```

4. Register each production task source through:

```text
worker.task_source
```

with exactly one metadata key:

```text
task_type: queue | http
```

Ensure the selected task type resolves exactly one source.

5. Ensure `WorkerTaskSourceInterface::receive()` performs real transport-owned waiting and remains cooperatively interruptible. Do not use synthetic/no-op tasks, busy polling, `sleep`/`usleep`, or `null` to represent a temporarily empty queue.
6. Review task settlement behavior:

```text
receive
→ Kernel UnitOfWork
→ execute
→ complete on success

receive
→ Kernel UnitOfWork failure
→ fail
```

Do not call `fail()` solely because `complete()` failed.

7. Migrate low-level UnitOfWork integrations to `UnitOfWorkHandle` and remove dependencies on exported started/finished timing tokens. Use `durationMs` where public elapsed timing is required.
8. Stop reconstructing runtime boot from independently selected artifact files. Consume the canonical validated active generation.
9. Review ContextStore and ContextBag writes for object-free JSON-like values that remain within the configured/canonical resource limits.
10. If Worker deployment automation assumed the previous short-lived manager lifecycle, update it for the persistent supervisor model. External service managers still own restart policy; Coretsia owns active-generation containment while the guardian remains alive.
11. Run the canonical workspace synchronization and verification flow:

```bash
composer setup
composer ci
```

### From v0.4.0 to v0.5.0

#### Compatibility

- No stable API compatibility guarantee is provided for `0.x` development snapshots.

#### Notes

- Review `CHANGELOG.md` for the `v0.5.0` Core Kernel package publication details.
- This release introduces publish-ready `coretsia/core-kernel` split package coverage.
- Public `ContextKeys` moved to `core/contracts` as the canonical contract-level context key vocabulary.
- Foundation remains responsible for context storage, write validation, reset behavior, and default observability bindings.
- Kernel remains responsible for base UnitOfWork context writes and operation-boundary observability.
- The framework default `express` preset now treats `platform.http` as a required module.
- Until `platform.http` is available in the installed module manifest, resolving or booting the Express fixture is expected to fail deterministically with `CORETSIA_MODULE_REQUIRED_MISSING`.
- Use `micro` for the current Phase 1 kernel boot smoke path when HTTP platform support is not installed.

#### Migration steps

1. Review `CHANGELOG.md`.
2. If your workspace consumes split packages, refresh dependency locks intentionally.
3. If your application, tests, or integration code imports Foundation-owned context key identifiers, update them to the contract-level `ContextKeys` symbol from `core/contracts`.
4. Do not replace Foundation context storage or reset behavior with contract-level code; only the public key vocabulary moved.
5. If your application or test fixture selects the `express` preset, either:
   - ensure `platform.http` is available in the installed module manifest; or
   - use `micro` until HTTP platform support is available.
6. Run:

```bash
composer setup
composer ci
```

### From v0.2.0 to v0.3.0

#### Compatibility

- No stable API compatibility guarantee is provided for `0.x` development snapshots.

#### Notes

- Review `CHANGELOG.md` for the `v0.3.0` package publication details.
- This release introduced additional publish-ready split packages.

#### Migration steps

1. Review `CHANGELOG.md`.
2. Refresh dependency locks intentionally if your workspace consumes split packages.
3. Run:

```bash
composer setup
composer ci
```

### From v0.1.0 to v0.2.0

#### Compatibility

- No stable API compatibility guarantee is provided for `0.x` development snapshots.

#### Notes

- Review `CHANGELOG.md` for the `v0.2.0` contracts baseline and first public split package details.
- This release introduced the first publish-ready split package and the Phase 1 contracts baseline.

#### Migration steps

1. Review `CHANGELOG.md`.
2. Refresh dependency locks intentionally if your workspace consumes split packages.
3. Run:

```bash
composer setup
composer ci
```

## Entry template

> Add a new section per upgrade jump you want to document.

### From X.Y.Z to A.B.C

#### Breaking changes

- *TBD*

#### Deprecations

- *TBD* (warn in N, remove in N+2)

#### Migration steps

1. *TBD*

#### Notes

- *TBD* (include only deterministic, non-sensitive diagnostics; avoid raw values/paths)
