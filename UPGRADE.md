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

The root `composer.json` `repositories` block is managed only by:

```bash
composer sync:repos
```

During upgrades (or rebases), treat manual edits of the root `repositories` block as invalid and expect CI/pre-commit to fail on drift.

### 3) Lock determinism (must not drift)

The monorepo root `composer.lock` is committed. Consumer applications own their own `composer.lock`.
CI uses `composer install` and fails on root workspace lock drift.
If your upgrade changes dependencies, regenerate the affected lock intentionally and commit it where applicable.

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
composer install
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

### From v0.7.0 to v0.8.0

#### Compatibility

- No stable API compatibility guarantee is provided for `0.x` development snapshots.
- This release introduces new Kernel mode, module selection, application dependency synchronization, source-host operations, and sensitive-data redaction boundaries.
- Review custom mode preset sources, module planning integrations, runtime package metadata, and diagnostic/output consumers that depend on previous development-surface behavior.

#### Mode presets and module selection

Kernel mode resolution now separates the two canonical phases:

- Phase A resolves namespace-bound mode policy and per-target module overrides into an immutable `ModuleSelection`.
- Phase B resolves installed Composer module metadata and the selected module policy into a deterministic `ModulePlan`.

The legacy preset selection model is replaced by the required/modules policy.

Review custom mode preset sources and integrations that construct or inspect mode selection state.

Canonical framework presets and owner-defined custom presets must follow their explicit namespace and ownership rules. Custom preset names must not redefine canonical framework preset identities.

Module selection and module planning are distinct boundaries. Do not infer application module selection solely from installed Composer packages or reconstruct `ModulePlan` directly from preset source data.

#### Application dependency synchronization

Consumer application dependency synchronization is now an explicit Kernel-owned operation separate from runtime boot.

The shipped consumer adapter supports:

```bash
php bin/dependency-sync.php plan --target=web
php bin/dependency-sync.php review --target=web
php bin/dependency-sync.php apply --target=web
```

Replace `web` with the intended application target. Multiple targets may be selected explicitly through repeated `--target` options.

- `plan` computes installation requirements without performing synchronization.
- `review` validates the proposed synchronization without intentionally changing Composer state.
- `apply` performs authorized Composer effects and verifies the resulting installed state.

Dependency synchronization uses a versioned release installation catalog to resolve runtime module identities to Composer packages.

The physical Composer dependency union is project-wide, but module selection, exclusions, and runtime conflicts remain target-local.

Only explicitly tracked DependencySync-managed Coretsia root requirements may be reconciled automatically. Project-owned Coretsia requirements and protected third-party roots retain their ownership.

Composer scripts, plugins, broad dependency updates, and installed-vendor repair require their respective explicit authorizations.

Effectful synchronization uses project locking, durable recovery records, and fresh-process installed verification. A failed Composer operation is not guaranteed to roll back `composer.json`, `composer.lock`, and `vendor/` atomically.

Do not invoke dependency synchronization during HTTP, CLI application, Worker, or other runtime boot.

#### Kernel source-host operations

Kernel now exposes explicit-target source-host operations for configuration, cache, and module workflows.

The new contracts include:

```text
KernelOpsInterface
KernelOpsRequest
OpsResult
KernelOpsFailedException
```

Source-host operations reuse canonical bootstrap, mode and module resolution, configuration loading, fingerprinting, artifact compilation, and cache verification pipelines.

They do not require an existing generated runtime artifact generation.

Integrations that previously assembled equivalent source-host operations directly from internal Kernel services should review the new public boundary.

Operations must preserve explicit application-target selection, safe deterministic result shapes, and the existing context and UnitOfWork ownership rules.

Kernel source-host services are not substitutes for compiled runtime service definitions.

#### Mode preset PHP source output

Synchronous ordinary PHP output produced while loading mode preset sources is now contained and discarded.

Mode preset sources must not rely on `echo`, `print`, or other ordinary PHP output reaching a caller-owned output buffer or transport.

Source loading remains a trusted PHP execution boundary, not an execution sandbox.

Source execution and output-buffer cleanup failures retain the existing generic invalid-source failure boundary without exposing captured output.

Direct stream writes, process termination, deferred output, and arbitrary output-buffer manipulation remain outside the ordinary output-containment guarantee.

#### Sensitive-data redaction

The new shared runtime package is:

```text
coretsia/platform-redaction
```

Its runtime module identity is:

```text
platform.redaction
```

The module requires `core.foundation` and is not automatically enabled merely because its Composer package is installed.

Eligible consumers requiring shared sensitive-data redaction must explicitly select the module through their owning runtime module policy and use the contracts-level `SensitiveDataRedactorInterface`.

Direct known-sensitive string values use `redactValue()` with an explicit `RedactionKind` and `RedactionContext`.

Recursive json-like diagnostic/output values use `redactJsonLike()` where the destination owner permits the shared redaction boundary.

Unclassified values may remain unchanged. A classifier non-match is not evidence of diagnostic safety; known-sensitive values still require explicit classification, omission, or owner-owned safe derivation.

`Placeholder` is the default disclosure mode. `Length`, `Hash`, and `HashAndLength` require explicit owner approval for the metadata disclosed.

One recursive redaction operation applies the same selected disclosure mode to every sensitive branch it replaces.

For boundaries with heterogeneous disclosure requirements, prefer omission or `Placeholder`, or split separately governed values into distinct operations.

Canonical redacted summaries do not automatically authorize emission to `ErrorDescriptor` extensions, logs, spans, metrics, or other diagnostic sinks. Existing owner-defined schemas, semantic-key restrictions, resource budgets, and non-reconstruction requirements remain authoritative.

Lower-layer Core producers and Kernel Ops retain their existing safe-by-construction policies and must not acquire an upward dependency on `platform/redaction`.

#### Json-like normalization limits

`JsonLikeNormalizationLimits` now supports an optional fourth constructor argument:

```php
?int $maxTotalStringBytes = null
```

The aggregate budget counts the byte lengths of string values and string map keys using `strlen()` during the existing deterministic normalization traversal.

A non-null aggregate limit must be positive. Zero is invalid.

Omitting the argument or passing `null` preserves existing normalization behavior without an aggregate string-byte cap.

Existing callers using the original three constructor arguments do not require migration.

Callers that opt into the aggregate budget must handle the additional stable failure reason:

```text
json-like-total-string-bytes-exceeded
```

Existing depth, node, map-key-type, individual-string-byte, and diagnostic-path contracts remain authoritative.

#### Runtime package configuration

Runtime packages without a reserved configuration root are no longer required to provide:

```text
config/
CONFIG_ROOT
configRoot()
extra.coretsia.defaultsConfigPath
```

A runtime package that owns a configuration root must still provide its canonical defaults file and declarative rules file:

```text
config/<root>.php
config/rules.php
```

Optional configuration deprecations remain package-owned.

`defaultsConfigPath`, when declared, identifies the canonical package-relative defaults file for the reserved configuration root.

Runtime module identity, dependency and conflict edges, provider declarations, and optional default-config metadata remain authoritative under validated Composer `extra.coretsia` metadata.

Package-local runtime module helpers are not runtime discovery sources and are not required to implement the application/user `ModuleInterface`.

Do not create placeholder configuration files or metadata for config-free runtime packages.

#### Migration steps

1. Review the `v0.8.0` `CHANGELOG.md` section and relevant changed runtime contracts and documentation.
2. Update custom mode presets and module selection integrations to the namespace-bound required/modules policy and Phase A/Phase B separation.
3. Review application targets, module inclusion/exclusion rules, and Composer requirements before adopting explicit dependency synchronization.
4. Use the consumer dependency-sync plan and review operations before authorizing apply, and preserve recovery receipts when synchronization requires manual recovery.
5. Review custom Kernel source-host integrations for the new explicit-target operations interface and safe result contracts.
6. Remove assumptions that ordinary PHP output from mode preset source files is forwarded to caller-owned output buffers.
7. Adopt `SensitiveDataRedactorInterface` only in eligible runtime consumers and preserve owner-owned diagnostic schemas, omission policies, and safe-by-construction output.
8. Review custom normalization-limit construction if opting into the new aggregate string-byte budget.
9. Remove placeholder configuration requirements from config-free runtime packages while retaining canonical config-root ownership for configuration-bearing packages.
10. Update Composer dependencies to the published target release line and refresh dependency locks intentionally.
11. Run the canonical validation or consumer installation checks appropriate to the upgraded project.

### From v0.6.0 to v0.7.0

#### Compatibility

- No stable API compatibility guarantee is provided for `0.x` development snapshots.
- This release establishes explicit repository, package, distribution, and consumer application ownership boundaries.

#### Repository topology

The legacy framework-centered repository layout has been replaced:

```text
framework/packages/**  → packages/**
framework/tools/**     → tools/**
framework/var/**       → var/**
```

The previous root `framework/` and `skeleton/` workspace directories are no longer part of the canonical repository topology.

The root `composer.json` and `composer.lock` now own the development workspace. Repository tooling runs through the root Composer scripts.

Do not retain tooling scripts or local Composer repositories that depend on the previous framework-local or skeleton-local development workspaces.

#### Public distributions

The new public Composer distributions are:

```text
coretsia/framework
coretsia/skeleton
```

Install the baseline framework runtime in an existing PHP project through:

```bash
composer require coretsia/framework:^0.7
```

Create a new application from the public skeleton through:

```bash
composer create-project coretsia/skeleton my-app "^0.7"
```

These commands use public Composer packages rather than local monorepo path repositories.

#### Consumer application ownership

Consumer applications own their own:

```text
composer.json
composer.lock
vendor/
config/
apps/
var/
```

Runtime packages must resolve application configuration and state from the consumer application root, not from the monorepo source skeleton.

Do not depend on sibling `framework/` or `skeleton/` directories in consumer applications.

#### Runtime and process boundaries

Kernel runtime-driver matrix resolution now uses `RuntimeDriverResolver` instead of the previous Kernel entrypoint compatibility boundary.

Kernel owns runtime-driver selection and conflict policy. Worker module participation and Worker-specific runtime prerequisites belong to the Worker-owned entrypoint boundary.

Review integrations that depend directly on the previous entrypoint compatibility behavior and migrate them to the corresponding canonical owner.

`KernelRuntime` now enforces single-active `UnitOfWork` ownership. Overlapping low-level lifecycle operations are rejected deterministically, and ownership is released only after the required reset cleanup boundary.

Do not depend on overlapping `UnitOfWork` lifecycles or assume that a finished operation releases its ownership before reset cleanup.

#### Artifacts and process bootstrap

Kernel-generated PHP artifacts are parsed as strict canonical data. Arbitrary PHP syntax is not accepted as an artifact representation.

Consumers of generated artifacts must use the canonical artifact verification and runtime boot boundaries rather than executing artifact files directly.

Compilation and verification reuse their canonical module-resolution and configuration-source inputs. Do not independently rediscover or reconstruct these inputs between compilation, fingerprinting, artifact production, and cache verification.

Supervisor-to-Guardian and Guardian-to-ProcHost bootstrap use authenticated child-launch handshakes.

Custom process-host or Worker lifecycle integrations must not depend on the previous reserve-close-rebind bootstrap behavior.

#### Repository tooling and determinism

Obsolete Phase 0 spike implementations and compatibility rails have been removed. Use the canonical Foundation, Kernel, Worker, and repository-tooling implementations instead of former spike entrypoints.

The repository-wide license-header gate now validates supported source, markup, configuration, metadata, and generated artifact formats.

New or modified files and generators must preserve the canonical license-header and text-normalization policies.

Production determinism is governed by `DETERMINISM.md` and the applicable subsystem SSoT documents.

Equivalent semantic application inputs must not acquire different fingerprints, generation identities, or artifact bytes solely because of irrelevant physical repository layout differences.

Clocks, process identifiers, scheduling, security randomness, and transport correlation remain outside deterministic semantic boundaries.

#### Release line

The `0.7` release line uses workspace version `0.7.x-dev` and public internal package constraints `^0.7.0`.

#### Migration steps

1. Review the `v0.7.0` `CHANGELOG.md` section and relevant changed SSoT documents.
2. Migrate repository tooling and development commands to the root Composer workspace.
3. Replace obsolete repository-relative paths with the canonical `packages/**`, `tools/**`, and `var/**` paths.
4. Remove monorepo-only Composer path repositories from consumer application manifests.
5. Update internal Coretsia dependencies to the target release line and refresh dependency locks intentionally.
6. Migrate integrations that depend on the previous Kernel runtime-driver entrypoint boundary, overlapping `UnitOfWork` lifecycles, executable generated artifacts, or legacy Worker process-bootstrap behavior.
7. Replace obsolete Phase 0 spike entrypoints with their canonical production or repository-tooling equivalents.
8. Review custom source and artifact generators for canonical license headers, text normalization, and production determinism requirements.
9. Run the canonical repository validation or consumer installation checks appropriate to the upgraded project.

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
