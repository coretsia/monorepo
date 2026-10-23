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

## PHASE 2 — Mode Infrastructure & CLI (Non-product doc)

### 2.10.0 Mode preset PHP source execution hardening (MUST) [IMPL]

---
type: package
phase: 2
epic_id: "2.10.0"
owner_path: "packages/core/kernel/"

package_id: "core/kernel"
composer: "coretsia/core-kernel"
kind: runtime
module_id: "core.kernel"

goal: "Посилити існуючий Kernel-owned PHP mode-preset source-execution boundary containment та discard incidental ordinary buffered PHP output без зміни прийнятих ModePreset, ModuleSelection, ModulePlan і error-taxonomy semantics."

provides:
- "Ordinary buffered PHP output containment for the existing namespace-bound mode-preset source execution path"
- "Preservation of existing preset validation and error-taxonomy semantics while incidental source output is contained and discarded"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: docs/adr/ADR-0024-kernel-module-plan-resolution.md
ssot_refs:
- docs/ssot/modes.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - N/A

- Required deliverables (exact paths):
  - `packages/core/contracts/src/Module/ModePresetInterface.php` — existing format-neutral preset value contract returned by the loader.
  - `packages/core/contracts/src/Module/ModePresetLoaderInterface.php` — existing loader API whose `load()` / `tryLoad()` semantics remain unchanged.
  - `packages/core/kernel/src/Module/FilesystemModePresetLoader.php` — existing one-source-bound PHP preset loader hardened by this epic.
  - `packages/core/kernel/src/Module/ModePresetSchemaValidator.php` — existing payload-validation boundary invoked after successful source execution.
  - `packages/core/kernel/src/Module/Preset/PresetSourceInterface.php` — existing single-source abstraction consumed by the loader.
  - `packages/core/kernel/src/Module/Exception/ModePresetInvalidException.php` — existing generic invalid-source boundary reused without modification.
  - `packages/core/kernel/src/Module/Exception/ModePresetNotFoundException.php` — existing missing-source boundary whose semantics remain unchanged.
  - `docs/ssot/modes.md` — canonical mode/preset loading-policy SSoT.
  - `docs/architecture/DEPENDENCIES.md` — canonical direct-dependency matrix used by this epic's compile-time dependency constraints.
  - `packages/core/kernel/tests/Support/ModeInfrastructureTestSupport.php` — existing package-local integration-test support reused by the new output-containment test.
  - `docs/adr/ADR-0024-kernel-module-plan-resolution.md` — existing related architecture decision referenced by this epic but not modified.

- Required config roots/keys:
  - N/A

- Required tags:
  - N/A

- Required contracts / ports:
  - `Coretsia\Contracts\Module\ModePresetInterface` — preset value returned by the existing loader API.
  - `Coretsia\Contracts\Module\ModePresetLoaderInterface` — existing `load()` / `tryLoad()` API surface preserved by this epic.

- Accepted architectural invariants:
  - `PresetNamespaceResolver` is the sole name-based namespace selector and MUST NOT probe the filesystem.
  - canonical names resolve only to `PresetNamespace::Canonical`.
  - non-canonical safe names resolve only to `PresetNamespace::Custom`.
  - `CanonicalPresetSource` and `CustomPresetSource` are disjoint source owners.
  - canonical names never load application preset files.
  - custom names never fall back to Kernel canonical resources.
  - application-owned canonical-name files are rejected; they do not override canonical resources.
  - there is no first-existing-file-wins policy.
  - there is no canonical/custom payload merge.
  - `FilesystemModePresetLoader` is bound to exactly one `PresetSourceInterface`.
  - accepted Phase-A flow remains:

```text
BootstrapConfig::preset()
        +
PresetNamespaceResolver
        ↓
PresetNamespace
        ↓
ModePresetLoaderFactory::createFor(
    BootstrapConfig,
    PresetNamespace
)
        ↓
namespace-bound PresetSourceInterface
        ↓
FilesystemModePresetLoader
        ↓
ModePresetSchemaValidator
        ↓
ModePreset
        +
ResolvedModuleOverrides
        ↓
ModuleSelectionFactory
        ↓
ModuleSelection
```

- this epic MUST NOT change `ModuleSelection`, `ResolvedModuleOverrides`, `ModuleSelectionFactory`, `ModuleGraphResolver`, `ModulePlan`, DependencySync planning law, installation-catalog semantics, artifact identities, or Composer dependency synchronization.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation` — existing `core/kernel` package dependency; this epic introduces no new dependency edge

Forbidden:

- any materialized layered package not listed in the `core/kernel` `depends_on` cell of `docs/architecture/DEPENDENCIES.md`
- any new package dependency edge introduced by this epic

#### Uses ports (API surface, NOT deps)

- Contracts:
  - `Coretsia\Contracts\Module\ModePresetInterface`
  - `Coretsia\Contracts\Module\ModePresetLoaderInterface`

### Entry points / integration points (MUST)

- Other compile-host integration points:
  - `Coretsia\Kernel\Module\Preset\PresetNamespaceResolver` → resolves requested preset name to the already accepted canonical/custom namespace.
  - `Coretsia\Kernel\Module\ModePresetLoaderFactory::createFor(...)` → creates one namespace-bound `FilesystemModePresetLoader`.
  - `Coretsia\Kernel\Module\FilesystemModePresetLoader::load(string)` → existing preset-loading entrypoint hardened by this epic.
  - `Coretsia\Kernel\Module\FilesystemModePresetLoader::tryLoad(string)` → existing nullable loading entrypoint; existing-but-invalid source MUST still fail rather than be translated to absence.
  - `Coretsia\Kernel\Module\ModePresetSchemaValidator::validate(string, mixed)` → existing raw source-payload validation boundary.

- Notes:
  - no new public runtime entrypoint is introduced.
  - no new CLI, HTTP route, Kernel tag, discovery tag, or artifact read/write entrypoint is introduced.
  - namespace selection remains a loader-construction concern and MUST NOT be added to `ModePresetLoaderInterface` method signatures.

### Deliverables (MUST)

#### Creates

- [x] `packages/core/kernel/tests/Integration/ModePresetLoaderOutputContainmentTest.php` — proves ordinary buffered-output containment, supported cleanup and preservation of existing validation/error semantics.

#### Modifies

- [x] `packages/core/kernel/src/Module/FilesystemModePresetLoader.php` — add ordinary buffered-output containment around the existing source execution path while preserving current namespace-bound loading, validation and error-taxonomy behavior:
  - [x] retain the existing static local `require` closure.
  - [x] record the current caller output-buffer level before creating loader-owned buffering state.
  - [x] install the existing temporary PHP error handler before output-buffer initialization and retain the existing rule that diagnostics are not delegated to a previously installed handler.
  - [x] execute loader-owned output-buffer initialization and source `require` under the same generic invalid-source execution boundary.
  - [x] start exactly one loader-owned discard buffer using a side-effect-free output handler that returns an empty string and a fixed implementation-local chunk size of `8192` bytes.
  - [x] ordinary source output MUST be discarded incrementally during execution; the loader MUST NOT retain unbounded ordinary output solely because of its containment buffer until final cleanup.
  - [x] output-buffer initialization failure or a callback-handled diagnostic raised during initialization maps to the existing generic invalid-source failure.
  - [x] keep the temporary loader error handler installed through the complete output-buffer cleanup attempt so cleanup diagnostics cannot reach or invoke the caller-owned previous handler.
  - [x] restore the previous error handler in an outer `finally` only after the output-buffer cleanup attempt has completed or failed.
  - [x] for supported source behavior that neither depends on nor deliberately inspects, flushes, closes, replaces, or otherwise manipulates PHP output-buffer state, does not terminate or abort the current PHP process outside normal Throwable control flow, and does not defer output beyond source execution, synchronous ordinary PHP output routed through the loader-owned buffer MUST NOT reach the caller or any pre-existing caller-owned outer output buffer.
  - [x] ordinary buffered source output MUST NOT enter deterministic diagnostics.
  - [x] output produced during otherwise successful source execution is discarded and MUST NOT by itself change preset validity, ErrorCode, reason token or payload-validation precedence.
  - [x] source execution Throwable or callback-handled PHP diagnostic retains the existing generic invalid-source semantics; buffered output produced before that failure is discarded during cleanup.
  - [x] in `finally`, best-effort close and discard only output-buffer levels above the recorded caller level; final cleanup discards any residual bytes not already consumed by the loader-owned discard handler.
  - [x] best-effort cleanup MUST stop if `ob_end_clean()` returns `false` or raises a Throwable through the temporary loader error handler; either condition marks cleanup as failed and cleanup MUST NOT spin indefinitely.
  - [x] successful supported cleanup restores exactly the recorded caller output-buffer level.
  - [x] if cleanup finishes with an output-buffer level different from the recorded caller level, fail through the existing generic invalid-source boundary; the loader MUST NOT claim recovery of caller-owned buffering deliberately destroyed by arbitrary source PHP.
  - [x] after successful source execution and successful output cleanup, pass the returned payload to `ModePresetSchemaValidator` exactly as before.
  - [x] do not add fallback, merging, namespace selection, ModuleSelection interpretation, Composer state access, or a second source path.

- [x] `docs/ssot/modes.md` — extend the existing `Determinism and security` / `Security and redaction` policy with synchronous ordinary buffered PHP output containment/discard and the trusted-PHP/non-sandbox boundary; supported preset source semantics MUST NOT depend on loader-internal output-buffer state, terminate/abort the loading process, or rely on deferred output, and ordinary output presence alone MUST NOT redefine preset validity or existing failure taxonomy.

#### Package skeleton (if type=package)

N/A — `core/kernel` is an existing runtime package. This epic does not create or restructure its package skeleton.

#### Configuration (keys + defaults)

N/A — this epic introduces and modifies no configuration root, key or default.

#### Wiring / DI tags (when applicable)

N/A — no tag ownership, tag consumption, ServiceProvider registration, DI identity or reserved-tag change is introduced.

#### Artifacts / outputs (if applicable)

N/A — this epic does not create, modify, read or write a versioned runtime artifact contract.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

N/A — this epic introduces or modifies no span, metric, log event, label, attribute, or observability outcome.

#### Errors

- Exceptions introduced:
  - N/A

- [x] Existing error taxonomy retained:
  - [x] unreadable namespace-owned source → existing `CORETSIA_MODE_PRESET_INVALID` / `REASON_PRESET_INVALID`.
  - [x] source execution Throwable → existing `CORETSIA_MODE_PRESET_INVALID` / `REASON_PRESET_INVALID`.
  - [x] callback-handled PHP diagnostic → existing `CORETSIA_MODE_PRESET_INVALID` / `REASON_PRESET_INVALID`.
  - [x] output-buffer initialization or unrecoverable cleanup failure → existing `CORETSIA_MODE_PRESET_INVALID` / `REASON_PRESET_INVALID`.
  - [x] these failures are execution-boundary failures only; they MUST NOT be translated into payload/schema-specific reason tokens.
  - [x] ordinary buffered output presence alone → no failure and no reason-token change.
  - [x] payload structural/type/schema/safety violation → existing most-specific validator reason, regardless of previously discarded ordinary buffered output.
  - [x] absent namespace-owned source → existing `ModePresetNotFoundException` / `tryLoad() === null` semantics as applicable.
  - [x] no new ErrorCode, exception type, reason token or output-specific failure precedence is introduced.

- Mapping:
  - N/A — reuse the existing ModePreset/ModuleResolution exception boundary.

#### Security / Redaction

- [x] retain the existing safe `ModePresetInvalidException` diagnostic contract.
- [x] for supported source behavior defined above, buffered ordinary source output MUST NOT:
  - [x] reach caller-owned ordinary output buffers.
  - [x] appear in `ModePresetInvalidException::errorCode()`.
  - [x] appear in `ModePresetInvalidException::reason()`.
  - [x] appear in `ModePresetInvalidException::getMessage()`.
  - [x] appear in `ModePresetInvalidException::context()`.
- [x] callback-handled PHP diagnostic text MUST remain suppressed from deterministic diagnostics.
- [x] source execution Throwable translation MUST preserve the existing loader behavior and MUST NOT introduce new Throwable chaining merely for output isolation.
- [x] preset PHP remains trusted Kernel/application code; the loader MUST NOT claim arbitrary-PHP sandboxing.
- [x] source behavior that depends on, inspects, flushes, closes, replaces, or otherwise manipulates PHP output-buffer state, manipulates error-handler ownership, writes directly to `STDOUT`, `STDERR`, or equivalent process streams, terminates/aborts the PHP process outside normal Throwable control flow, or schedules deferred output remains outside the supported ordinary-output containment guarantee.
- [x] arbitrary filesystem/network/process/global-state side effects remain possible because preset PHP is trusted executable code; this epic does not sandbox or prevent them, but their presence alone does not disable ordinary buffered-output containment when the source otherwise stays within the supported execution model.

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [x] If Context writes exist → N/A; this epic performs no Context writes.
- [x] If `kernel.reset` used → N/A; this epic introduces no resettable service or reset tag.
- [x] If metrics/spans/logs exist → N/A; this epic introduces no observability emitter.
- [x] If redaction exists → `packages/core/kernel/tests/Integration/ModePresetLoaderOutputContainmentTest.php`
  - [x] buffered source output does not reach caller-owned output buffers.
  - [x] when an independent source-execution or payload-validation failure produces `ModePresetInvalidException`, buffered source output does not appear in `errorCode()`, `reason()`, `getMessage()` or `context()`.
  - [x] existing PHP diagnostic and Throwable-message no-leak behavior remains unchanged under the new output-buffer wrapper.

#### Test harness / fixtures (when integration is needed)

- [x] reuse `packages/core/kernel/tests/Support/ModeInfrastructureTestSupport.php` for temporary roots, preset payloads, source writing and loader construction.
- [x] create output-producing preset PHP files only under per-test temporary roots; no committed fixture application is introduced.
- [x] caller-owned output-buffer and error-handler fixtures MUST be restored in `finally` so a failed assertion cannot contaminate the PHPUnit process.
- [x] per-test temporary roots MUST be removed in `finally` through `ModeInfrastructureTestSupport::remove()`.

### Tests (MUST)

- Unit:
  - N/A — the changed behavior exists only at the filesystem source-execution boundary and is verified through integration coverage.

- Contract:
  - N/A — no public contract, ErrorCode, exception type, or stable reason-token taxonomy is changed.

- Integration:
  - [x] `packages/core/kernel/tests/Integration/ModePresetLoaderOutputContainmentTest.php`
    - [x] a silent valid source retains the existing successful loading semantics and leaves the caller output-buffer level and previous error handler unchanged.
    - [x] otherwise-valid source emitting `echo 'unexpected-output'` still loads successfully and the emitted bytes do not reach any caller-owned outer output buffer.
    - [x] otherwise-valid source emitting at least three separate ordinary-output writes of `8192` bytes each still loads successfully; every chunk is discarded, no emitted bytes reach the caller-owned outer buffer, and the caller output-buffer level is restored.
    - [x] output-producing success restores the caller output-buffer level.
    - [x] whitespace/newline-only output is discarded and does not change successful loading.
    - [x] source emits a sensitive fixture marker before returning an otherwise-valid payload with invalid `schemaVersion`; the marker is absent from the thrown exception `errorCode()`, `reason()`, `getMessage()` and `context()`, and from any caller-owned outer output buffer, while `ModePresetInvalidException::REASON_SCHEMA_VERSION_INVALID` is preserved.
    - [x] source emits ordinary output and then a callback-handleable PHP diagnostic containing a sensitive fixture marker; neither value escapes, the diagnostic retains the existing generic invalid-source failure semantics, and the previously installed caller error handler is not invoked during source execution.
    - [x] the callback-handled PHP diagnostic fixture marker is absent from `ModePresetInvalidException::errorCode()`, `reason()`, `getMessage()` and `context()`, and the translated exception keeps `getPrevious() === null`.
    - [x] output followed by Throwable does not escape output, retains the existing generic invalid-source failure semantics, does not expose the raw Throwable message, and keeps `getPrevious() === null`.
    - [x] source-execution failure after buffered output restores the caller output-buffer level.
    - [x] output-producing existing source passed to `tryLoad()` returns the loaded preset and does not emit its ordinary buffered output to the caller.
    - [x] caller-owned outer buffer remains open.
    - [x] caller-owned outer-buffer content survives supported success/failure paths.
    - [x] previous caller-owned error handler is restored after supported success/failure paths; an explicit diagnostic triggered after the loader returns is handled by the restored caller handler.
    - [x] tests MUST NOT claim stable source-visible output-buffer state or containment under source-owned `ob_*` inspection/manipulation, direct `STDOUT`/`STDERR` writes, destruction of caller-owned buffers, deliberate source-owned error-handler manipulation, source-driven process termination/abort, or deferred output.
    - [x] tests MUST NOT claim sandboxing or prevention of arbitrary filesystem/network/process/global-state side effects performed by trusted preset PHP.

  - [x] existing namespace regression coverage remains green:
    - [x] `PresetNamespaceResolverTest`.
    - [x] `ModePresetLoaderUsesCanonicalSourceOnlyTest`.
    - [x] `ModePresetLoaderUsesCustomSourceOnlyTest`.
    - [x] `ModePresetLoaderRejectsCanonicalShadowingTest`.
    - [x] `ModePresetLoaderDoesNotMergeOverrideWithDefaultTest`.
    - [x] canonical name never loads application source.
    - [x] custom name never loads Kernel canonical source.
    - [x] no cross-namespace fallback.
    - [x] no source merge.
    - [x] no filesystem-derived namespace selection.

- Gates/Arch:
  - [x] deptrac update: N/A — no dependency edge changes.
  - [x] new gate: N/A — no new gate is introduced.
  - [x] architecture analysis confirms no new dependency edge from `core/kernel`.
  - [x] production package code modified by this epic introduces no dependency on repository tooling under `tools/**`.

- Repository validation:
  - [x] run using the actual complete repository checkout and existing root Composer scripts.
  - [x] `composer test`
  - [x] `composer gates`
  - [x] `composer arch`
  - [x] `composer quality`
  - [x] `composer ci`
  - [x] do not reconstruct missing validation tooling from an incomplete review snapshot.
  - [x] do not introduce new Composer scripts merely for this epic if existing aggregate commands already provide the required coverage.

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact.
- [x] Preconditions satisfied (no forward references).
- [x] `type=package`, `package_id=core/kernel`, `composer=coretsia/core-kernel`, `kind=runtime`, `module_id=core.kernel` are correct.
- [x] deps/forbidden respected; deptrac remains green and no package dependency cycle is introduced.
- [x] Verification tests present where applicable.
- [x] `FilesystemModePresetLoader` remains bound to exactly one source.
- [x] ordinary buffered PHP output is contained and discarded without making output presence itself a preset-validation failure.
- [x] loader-owned containment discards ordinary output incrementally with a fixed non-zero chunk size and does not accumulate unbounded ordinary source output until cleanup.
- [x] callback-handled PHP diagnostics do not escape preset execution.
- [x] original caller output-buffer state is restored for supported source behavior.
- [x] previous error-handler state is restored for supported source behavior.
- [x] source-execution and loader-owned cleanup diagnostics are not delegated to the caller-owned previous error handler.
- [x] loader does not claim arbitrary-PHP sandboxing.
- [x] containment guarantees cover synchronous ordinary buffered output during supported source execution only; they do not cover source-driven process termination/abort, deferred output, direct process streams, or deliberate output-buffer/error-handler manipulation.
- [x] the loader does not sandbox or prevent arbitrary filesystem/network/process/global-state side effects performed by trusted preset PHP.
- [x] supported preset source semantics do not depend on loader-internal PHP output-buffer state.
- [x] Kernel-owned and application-owned preset PHP is documented as trusted PHP code rather than sandboxed input.
- [x] buffered ordinary source output does not reach caller-owned output buffers or `ModePresetInvalidException` deterministic diagnostics.
- [x] existing PHP diagnostic and Throwable-message no-leak behavior remains unchanged.
- [x] existing payload-validation reason semantics remain unchanged when ordinary buffered output was produced before the returned payload.
- [x] `docs/ssot/modes.md` remains the single mode/preset SSoT and documents the new source-execution/output-isolation contract without creating a competing SSoT.
- [x] no public API signature change is introduced.
- [x] no config root/key/default change is introduced.
- [x] no new tag is introduced.
- [x] no artifact identity/schema is introduced or modified.
- [x] no new ErrorCode is introduced.
- [x] no new `ModePresetInvalidException` reason token is introduced; ordinary buffered output presence alone does not create a failure, while independent existing source-execution or payload-validation failures retain their existing reasons.
- [x] no Composer command/script is introduced solely by this epic.
- [x] no new gate is introduced.
- [x] ordinary buffered output presence alone does not alter successful `load()` / `tryLoad()` results.
- [x] ordinary buffered output preceding an invalid returned payload does not alter the validator-selected failure reason.
- [x] Docs updated:
  - [x] `docs/ssot/modes.md`
- [x] complete repository `composer ci` is green.

---

### 2.20.0 Kernel ops façade for CLI (MUST) [IMPL]

---
type: package
phase: 2
epic_id: "2.20.0"
owner_path: "packages/core/kernel/"

package_id: "core/kernel"
composer: "coretsia/core-kernel"
kind: runtime
module_id: "core.kernel"

goal: "Надати platform/cli стабільні Kernel-owned операції для explicit app target із preset, визначеним Bootstrap Phase A configuration."
provides:
- "Kernel-owned Ops façade over existing compile-host services"
- "Explicit app-target input for every module-aware operation"
- "Single preset source: BootstrapConfigResolver resolves presets[appTarget], global preset, or package default"
- "Generation-aware compile result for one atomically selected immutable generation"
- "Graph-bound expected generation ID calculation without artifact writes"
- "Current-generation verification across all four generation files"
- "Stable json-like result DTOs safe for platform rendering"
- "At most one ModuleResolution snapshot per invocation; exactly one for each successful module-aware operation"
- "No direct artifact, fingerprint, module, provider, or filesystem orchestration in platform/cli"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []     # uses existing kernel artifacts; does not introduce new artifact schemas
adr: "docs/adr/ADR-0023-kernel-bootstrap-phase-a.md"
ssot_refs:
- "docs/ssot/cache-verify.md"
- "docs/ssot/artifacts.md"
- "docs/ssot/artifacts-and-fingerprint.md"
- "docs/ssot/artifact-generations.md"
- "docs/ssot/compiled-container.md"
- "docs/ssot/runtime-container-definitions.md"
- "docs/ssot/modules-and-manifests.md"
- "docs/ssot/config-and-env.md"
- "docs/ssot/modes.md"
- "docs/ssot/observability.md"
- "docs/ssot/context-keys.md"
- "docs/ssot/context-store.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Kernel operation dependencies exist and are wired:
  - `ConfigKernel` is available for validate/debug flows;
  - `ArtifactCompiler` is available for immutable-generation publication;
  - `CacheVerifier` is available for current-generation verification;
  - `KernelArtifactOperation` is available as the canonical compile-host input-preparation and routing entrypoint for artifact compile and cache verify;
  - `ConfigSourceLocationBuilder` is available as the canonical `ConfigSourceSet` builder;
  - `ModuleResolutionOrchestrator` is available as the canonical owner of one installed-manifest + `ModulePlan` snapshot.
  - `RuntimeContainerGraphCompiler` is available for canonical graph production;
  - `ConfigFingerprintInputBuilder` is available for graph-bound fingerprint input construction;
  - `FingerprintCalculator` is available only for hashing an already-built canonical fingerprint input;

- Canonical generation infrastructure exists:
  - `ArtifactGenerationPublisher` publishes one immutable generation and atomically replaces `current`;
  - `ArtifactGenerationLocator` resolves and validates the generation selected by `current`;
  - `CacheVerifier` compares the expected generation against all four selected-generation files;
  - no operation selects a generation by scanning `generations/`.

- Kernel source/operations-host boot:
  - MUST resolve compile-host and source-runtime services without an existing artifact generation;
  - `config:compile` MUST work when no artifact root or `current` pointer exists;
  - `config:hash` MUST neither require nor read `current`;
  - `cache:verify` MUST classify an absent `current` generation as a completed dirty/missing result;
  - MUST remain separate from `ArtifactRuntimeBooter`;
  - MUST NOT become a source fallback for HTTP or Worker production runtime.

- Existing Kernel configuration pipeline:
  - [x] `KernelServiceProvider` and `KernelServiceFactory` already wire:
    - [x] Bootstrap Phase A services
    - [x] `EnvRepositoryBuilder`
    - [x] `ModulePlanResolver`
    - [x] `ConfigKernel`
    - [x] existing config loaders
    - [x] `ConfigMerger`
    - [x] `ConfigValidator`
    - [x] `ConfigExplainer`
    - [x] artifact compilation and verification services
  - [x] `ConfigKernel` remains the sole Config Phase B orchestration entrypoint
  - [x] `ArtifactCompiler` remains the compile and publication orchestrator
  - [x] `CacheVerifier` remains the current-generation verification orchestrator
  - [x] this epic introduces no separate public config-location capability or prerequisite epic
  - [x] this epic introduces no second compile/verify input-preparation path; existing `KernelArtifactOperation` remains the canonical owner of compile/verify input preparation
  - [x] no public source-plan API or source-plan DTO is introduced
  - [x] no parallel config loader, merger, validator, explainer, or repository implementation is introduced

- Compiled application runtime is not a dependency of Kernel Ops:
  - `KernelOpsFacade`, `KernelOpsInterface`, `KernelOpsHostInput`, and `KernelOpsExecutionServices` are dedicated source-operations-host-only wiring ids;
  - none of those ids is a canonical compile-host service-list entry or production runtime seed id;
  - source-host wiring for those ids is owned exclusively by `KernelOpsHostBooter`;
  - none of those ids may enter canonical runtime definitions, compiled runtime graphs, or compiled-runtime references;
  - Kernel operations MUST NOT boot the application through `ArtifactRuntimeBooter`.

- Required contracts / ports (exact FQCNs) (MUST)
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Context\ContextKeys`
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Psr\Log\LoggerInterface`
  - `Psr\Container\ContainerInterface`
  - `Coretsia\Foundation\Time\Stopwatch`

- Cross-package deliverable (embedded into 2.20.0) — new ports introduced in `core/contracts`
  - `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
  - `Coretsia\Contracts\Kernel\Ops\KernelOpsRequest`
  - `Coretsia\Contracts\Kernel\Ops\OpsResult`
  - `Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException`

- Boundary note (single-choice):
  - this epic intentionally co-introduces the public contracts port required by the same kernel capability
  - the ONLY allowed cross-package deliverables outside `packages/core/kernel/` in this epic are:
    - `packages/core/contracts/src/Kernel/Ops/KernelOpsInterface.php`
    - `packages/core/contracts/src/Kernel/Ops/KernelOpsRequest.php`
    - `packages/core/contracts/src/Kernel/Ops/OpsResult.php`
    - `packages/core/contracts/src/Kernel/Ops/Exception/KernelOpsFailedException.php`
    - `packages/core/contracts/README.md`
    - `packages/core/contracts/tests/Contract/KernelOpsContractsShapeContractTest.php`
    - `docs/ssot/observability.md`
    - `docs/ssot/runtime-container-definitions.md`
    - `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`
  - this epic MUST NOT introduce unrelated `core/contracts` surface beyond the Kernel Ops port

### Kernel operation target and preset ownership (MUST)

Every operation receives:

```php
final readonly class KernelOpsRequest
{
    public function __construct(
        string $appTarget,
    );

    public function appTarget(): string;
}
```

Canonical targets:

```text
web
api
console
worker
```

`KernelOpsFacade` MUST construct target Bootstrap input without a preset override:

```php
new BootstrapInput(
    applicationRoot: $applicationRoot,
    appTarget: AppTarget::fromString($request->appTarget()),
    preset: null,
);
```

Effective preset ownership remains:

```text
applicationRoot/config/app.php presets[appTarget]
→ applicationRoot/config/app.php preset
→ kernel.boot.default_preset
```

Kernel Ops MUST NOT accept, infer, or synthesize a preset override.

`OpsResult::preset()` returns the nullable effective preset. It MUST be non-null for every successful result. For façade-owned validate/debug/modules/hash flows, `preset` becomes exposable only after the operation's `ModuleResolutionOrchestrator::resolve()` completes successfully; a handled `ModuleResolutionException` MUST use `preset = null` even when the preceding `BootstrapConfig` already contains an effective preset candidate. For `compileConfig()` / `verifyCache()`, the value MUST come from the successful `KernelArtifactOperation` result produced from the same internally prepared `BootstrapConfig`; a handled delegated failure before that result is returned MUST use `preset = null`. `KernelOpsFacade` MUST NOT independently validate the preset or re-run Bootstrap Phase A only to recover it.

The operations host uses `AppTarget::Console` only for CLI service composition. It MUST NOT replace the operation target.

### Kernel operations orchestration ownership (MUST)

`platform/cli` is a transport and presentation layer for Kernel operations.

CLI command classes MUST NOT directly orchestrate module resolution, provider planning, container definition collection, config compilation, artifact compilation, fingerprint calculation, or cache verification.

Canonical command-side flow:

```text
DebugModulesCommand
ConfigValidateCommand
ConfigDebugCommand
ConfigCompileCommand
ConfigHashCommand
CacheVerifyCommand
    -> Coretsia\Contracts\Kernel\Ops\KernelOpsInterface
```

Canonical Kernel-side operation flow:

```text
KernelOpsFacade
    -> KernelOpsRequest(appTarget)
    -> BootstrapInput(
           appTarget = explicit request target,
           preset = null
       )

debugModules()
    -> existing BootstrapConfigResolver
    -> existing ModuleResolutionOrchestrator::resolve()
    -> one ModuleResolution

validateConfig() / debugConfig() / hashConfig()
    -> existing BootstrapConfigResolver
    -> existing EnvRepositoryBuilder
    -> existing ModuleResolutionOrchestrator::resolve()
    -> one ModuleResolution
    -> existing ConfigSourceLocationBuilder
    -> existing ConfigKernel Phase B
    -> operation-specific existing Kernel service

compileConfig() / verifyCache()
    -> existing KernelArtifactOperation
    -> existing BootstrapConfigResolver
    -> existing EnvRepositoryBuilder
    -> existing ModuleResolutionOrchestrator::resolve()
    -> one ModuleResolution
    -> existing ConfigSourceLocationBuilder
    -> existing ArtifactCompiler / CacheVerifier

    -> safe generation-aware OpsResult
```

`KernelOpsFacade` composes existing Kernel services. It does not provide a new config loading or repository implementation.

Operation-specific ownership:

```text
validateConfig()
    -> ConfigKernel validation

debugConfig()
    -> ConfigKernel safe explain output

debugModules()
    -> safe ModuleResolution and ModulePlan summary

compileConfig()
    -> KernelArtifactOperation::compile()
    -> ArtifactCompiler
    -> ArtifactGenerationPublisher
    -> published ArtifactGeneration

hashConfig()
    -> ConfigKernel
    -> RuntimeContainerGraphCompiler
    -> ConfigFingerprintInputBuilder
    -> FingerprintCalculator
    -> expected generation ID
    -> no writes and no current-generation read

verifyCache()
    -> KernelArtifactOperation::verify()
    -> CacheVerifier
    -> expected generation reconstruction
    -> current-generation location and validation
    -> four-file generation comparison
```

For one invocation of any target-aware Kernel operation:

- `ModuleResolutionOrchestrator::resolve()` MUST be invoked at most once per operation that requires installed module resolution;
- for `validateConfig()`, `debugConfig()`, `debugModules()`, and `hashConfig()`, that invocation is owned by `KernelOpsFacade`;
- for `compileConfig()` and `verifyCache()`, that invocation is owned exclusively by `KernelArtifactOperation`; `KernelOpsFacade` MUST NOT resolve a second `ModuleResolution`;
- `KernelOpsFacade` MUST NOT invoke `ModulePlanResolver` or `ManifestReaderInterface` directly; installed-manifest reading and Phase-B graph resolution remain owned by `ModuleResolutionOrchestrator`;
- `ManifestReaderInterface::read()` MUST NOT be invoked again after the operation's single `ModuleResolutionOrchestrator::resolve()` invocation returns;
- the same `ModuleResolution` instance MUST be supplied by the owning orchestration layer to every downstream component that requires module or provider context;
- the same `ModuleResolution::plan()` instance MUST be supplied to ConfigKernel and fingerprint-input construction;
- `RuntimeContainerGraphCompiler` MUST receive that same `ModuleResolution`;
- `RuntimeContainerGraphCompiler` owns `ContainerProviderPlanResolver` invocation and provider-definition collection;
- neither `KernelOpsFacade` nor `KernelArtifactOperation` MUST resolve a separate provider plan before invoking `ArtifactCompiler`, `CacheVerifier`, or `RuntimeContainerGraphCompiler`;
- provider definitions MUST be collected exactly once in canonical provider-plan order for each graph-producing operation;
- `ModuleResolution` and `ContainerProviderPlan` MUST remain compile-time values and MUST NOT be exported into artifacts;
- `ModulePlan` MUST NOT contain provider class lists;
- `KernelOpsInterface` and `OpsResult` MUST NOT expose `ModuleResolution`, `ContainerProviderPlan`, provider instances, raw Composer metadata, raw config/env values, or absolute paths.

`ArtifactCompiler` and `CacheVerifier` receive an already-resolved `ModuleResolution` and MUST NOT depend on `ModulePlanResolver`, `ManifestReaderInterface`, or `ComposerManifestReader`.

`RuntimeContainerGraphCompiler` owns `ContainerProviderPlanResolver` and consumes the supplied `ModuleResolution`.

`FingerprintCalculator` receives only the already-built canonical fingerprint input. It MUST NOT compile config, compile a container graph, resolve modules, plan providers, locate generations, or read artifacts.

Kernel Ops results MUST be safe by construction. `core/kernel` MUST NOT depend on `platform/redaction` or `SensitiveDataRedactorInterface` to make an unsafe `OpsResult` suitable for CLI rendering.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/*`
- `integrations/*`

### Entry points / integration points (MUST)

- Public Kernel operations service for `platform/cli`:
  - resolved only from the dedicated Kernel source/operations host container;
  - source-host wiring for `KernelOpsExecutionServices`, `KernelOpsFacade`, and `KernelOpsInterface` is owned by `KernelOpsHostBooter`;
  - `KernelServiceProvider::register()` MUST NOT register `KernelOpsExecutionServices`, `KernelOpsFacade`, or `KernelOpsInterface` into generic source/compile-host containers;
  - MUST NOT enter canonical runtime definitions produced by `KernelServiceProvider::define()`;
  - MUST NOT be exported into compiled container artifacts;
  - `platform/cli` MUST invoke Kernel operations only through `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`;
  - `KernelOpsFacade` remains an internal implementation even though the dedicated source-operations host owns and resolves its shared instance;
  - performs no stdout/stderr writes;
  - exposes deterministic safe exceptions and result DTOs.

#### Configuration (MUST)

- [x] This epic introduces no `kernel.operation.*` config subtree.
- [x] Kernel Ops uses the existing Foundation/Kernel package seed configuration for compile-host service construction and operation policy.
- [x] Every target-aware operation resolves its explicit-target Bootstrap Phase A state through the existing pipeline.
- [x] Configuration-aware operations additionally resolve env and Config Phase-B state through the existing pipelines; `debugModules()` does not execute Config Phase B.
- [x] The validated console-host Phase-B configuration is the final source-host runtime/provider/command configuration; it MUST NOT become bootstrap, mode, module, artifact, verification, or fingerprint policy input for target-aware Kernel Ops execution.
- [x] Effective preset is resolved exclusively through `BootstrapConfigResolver`.
- [x] `KernelOpsFacade` MUST NOT read `cli.*`.
- [x] Observability, context access, and UoW participation MUST NOT be controlled by Kernel Ops feature flags.
- [x] No config key may disable result safety, safe-shape-by-construction rules, tracing, metrics, or context boundary rules.

#### Existing configuration pipeline reuse (MUST)

- [x] Kernel Ops reuses the existing Bootstrap Phase A and ConfigKernel Phase B implementations.
- [x] Existing ownership remains unchanged:
  - [x] `BootstrapConfigResolver` owns Bootstrap Phase A resolution, including effective preset selection
  - [x] `EnvRepositoryBuilder` owns immutable env snapshot construction
  - [x] `ModuleResolutionOrchestrator::resolve()` owns mode-preset/module selection, one installed-manifest snapshot, and the corresponding `ModulePlan`
  - [x] `ModulePlanResolver::resolve()` remains the pure resolver used internally by `ModuleResolutionOrchestrator`
  - [x] `ConfigSourceLocationBuilder` owns canonical `ConfigSourceSet` construction
  - [x] `ConfigKernel` owns config loading, directives, merge, validation, and explain
  - [x] `RuntimeContainerGraphCompiler` owns provider planning and graph compilation
  - [x] `KernelArtifactOperation` owns canonical compile/verify input preparation and routing from `BootstrapInput`
  - [x] `ArtifactCompiler` owns artifact generation and publication
  - [x] `CacheVerifier` owns current-generation verification
- [x] Kernel Ops MUST NOT introduce:
  - [x] a second Config Phase-B package-defaults loader
  - [x] a second Config Phase-B application-config loader
  - [x] a rules loader
  - [x] a config merger
  - [x] a config validator
  - [x] a config explainer
  - [x] a second `ConfigRepositoryInterface` implementation
  - [x] a parallel config source-plan DTO
  - [x] a second mode-preset resolver
  - [x] a separate config-location epic
- [x] `ConfigSourceLocationBuilder` remains the sole canonical owner of target config-source location construction.
- [x] Every successful configuration-aware Kernel Ops invocation uses exactly one `ConfigSourceSet` built by `ConfigSourceLocationBuilder`.
- [x] Every configuration-aware invocation builds at most one `ConfigSourceSet`; a handled error MAY terminate before `ConfigSourceLocationBuilder::build()` is reached.
- [x] `KernelOpsFacade` owns that construction for validate/debug/hash flows, while `KernelArtifactOperation` owns it for compile/verify flows.
- [x] `KernelOpsFacade` MUST NOT reconstruct `ConfigSourceSet` from separate source arrays or introduce a parallel config-source model.
- [x] The operations-host `ConfigRepositoryInterface` represents the validated console-host configuration.
- [x] It MAY be consumed by console-host package factories and services.
- [x] It MUST NOT be treated as the target configuration for a Kernel operation whose request target is `web|api|worker`.
- [x] Every configuration-aware Kernel operation resolves and compiles configuration for its explicit request target through the existing Phase A and Phase B pipeline.
- [x] `debugModules()` resolves only the target-specific Bootstrap Phase A state and `ModuleResolution`; it MUST NOT execute Config Phase B.

### Deliverables (MUST)

#### Creates

- [x] `packages/core/contracts/src/Kernel/Ops/KernelOpsRequest.php`
  - [x] readonly value object
  - [x] contains only `appTarget: string`
  - [x] rejects empty, multiline, or control-byte input
  - [x] performs no Kernel-specific target validation
  - [x] contains no mode, preset, paths, config, or artifact state

- [x] `packages/core/contracts/src/Kernel/Ops/KernelOpsInterface.php`
  - [x] Methods (single-choice; deterministic; no stdout/stderr):
    - [x] `validateConfig(KernelOpsRequest $request): OpsResult`
    - [x] `debugConfig(KernelOpsRequest $request): OpsResult`
    - [x] `compileConfig(KernelOpsRequest $request): OpsResult`
    - [x] `hashConfig(KernelOpsRequest $request): OpsResult`
    - [x] `verifyCache(KernelOpsRequest $request): OpsResult`
    - [x] `debugModules(KernelOpsRequest $request): OpsResult`
  - [x] is a narrow port for Kernel-owned operations only
  - [x] MUST NOT become a generic CLI command bus
  - [x] MUST NOT acquire worker, migration, database, queue, storage, or integration-owned methods

- [x] `packages/core/contracts/src/Kernel/Ops/OpsResult.php`
  - [x] Immutable DTO / readonly:
    - [x] `public const int SCHEMA_VERSION = 1`
    - [x] `schemaVersion(): int` always returns `SCHEMA_VERSION`
    - [x] `operation: string`
    - [x] `appTarget: ?string` — canonical accepted app target
    - [x] `appTarget` MUST be non-null for every successful operation result
    - [x] `appTarget` MAY be null only for `handled_error` produced before canonical app-target validation completes
    - [x] rejected raw app-target input MUST NOT be copied into `OpsResult`
    - [x] `preset: ?string` — effective preset resolved by `BootstrapConfigResolver`
    - [x] `preset` MUST be non-null for every successful operation result
    - [x] `preset` MUST be null for `handled_error` produced before successful `ModuleResolution` completes, including `ModuleResolutionException`
    - [x] façade-owned `handled_error` produced after successful `ModuleResolution` MAY preserve the same validated effective preset
    - [x] `compileConfig()` / `verifyCache()` `handled_error` produced before `KernelArtifactOperation` returns its successful metadata MUST use `preset = null`
    - [x] `outcome: string` (`success|handled_error`)
    - [x] `success` requires a non-null effective preset
    - [x] `handled_error` represents an expected, safely classified operation rejection
    - [x] unexpected internal failures MUST throw `KernelOpsFailedException`
    - [x] `reason: ?string` — stable safe reason token for `handled_error`
    - [x] `reason` MUST be null when `outcome = success`
    - [x] `reason` MUST be non-null when `outcome = handled_error`
    - [x] Kernel Ops MUST reuse an existing safe Kernel reason token when the handled rejection originates from an existing Kernel exception/result; it MUST NOT rename the same condition into a second Ops-specific reason vocabulary
    - [x] `data: array` — json-like; no floats; maps recursively `strcmp`-sorted; list order preserved
    - [x] `fatal_error` MUST NOT be represented both as a result and an exception
  - [x] MUST NOT include raw values or absolute filesystem paths
  - [x] MAY include only safe tokens, canonical ids, basenames, counts, hashes, lengths, and the `ConfigExplainer`-normalized repo-relative or logical source paths explicitly allowed for `debugConfig()`
  - [x] `compileConfig()` success data contains exactly:
    - [x] `'artifacts' => list<array{basename: string, identity: string}>`
    - [x] `'generation_id' => string`
  - [x] `compileConfig()` artifact list order and values are exactly:
    - [x] `identity = module-manifest@1`, `basename = module-manifest.php`
    - [x] `identity = config@1`, `basename = config.php`
    - [x] `identity = container@1`, `basename = container.php`
    - [x] `identity = artifact-generation@1`, `basename = generation-manifest.php`
  - [x] `compileConfig()` data contains no filesystem paths
  - [x] `hashConfig()` success data contains exactly:
    - [x] `'generation_id' => string`
  - [x] `hashConfig()` performs no artifact writes or current-generation reads
  - [x] `verifyCache()` success data contains exactly:
    - [x] `'artifacts' => list<array{basename: string, existing_byte_count: int|null, expected_byte_count: int, name: string, reason: string, status: string}>`
    - [x] `'current_generation_id' => string|null`
    - [x] `'expected_generation_id' => string`
    - [x] `'state' => 'clean'|'dirty'|'invalid'`
  - [x] artifact `name` values are projected unchanged from `CacheVerifier` and are exactly `module-manifest|config|container|artifact-generation`
  - [x] `KernelOpsFacade` MUST NOT synthesize versioned artifact identities for `verifyCache()`
  - [x] `verifyCache()` preserves the deterministic artifact order returned by `CacheVerifier`; `KernelOpsFacade` MUST NOT reorder the list solely to match `compileConfig()`
  - [x] artifact `status` values are exactly `clean|dirty|invalid`
  - [x] artifact `reason` values are exactly `ok|missing|changed|fingerprint_mismatch|invalid`
  - [x] `verifyCache()` data contains no filesystem paths
  - [x] `validateConfig()` data contains only validation status and counts:
    - [x] `counts.unvalidated_root_count` => int
    - [x] `counts.validated_root_count` => int
    - [x] `counts.violation_count` => int
    - [x] `valid` => bool
  - [x] `debugConfig()` success data contains exactly:
    - [x] `explain` => `<the non-null safe explain result returned by ConfigKernel>`
  - [x] `debugConfig()` MAY preserve repo-relative or logical source paths only when they are already normalized by `ConfigExplainer`
  - [x] `KernelOpsFacade` MUST normalize `OpsResult::data` through the existing `Coretsia\Foundation\Serialization\JsonLikeNormalizer`; it MUST NOT introduce a second recursive json-like normalizer
  - [x] `KernelOpsFacade` MUST NOT remove, reconstruct, enrich, or join explain fields with raw config, env, filesystem, or Composer data
  - [x] absolute filesystem paths remain forbidden
  - [x] `debugModules()` contains only safe ModulePlan summary:
    - [x] `'enabled' => list<string>`
    - [x] `'excluded' => list<string>`
    - [x] `'topological_order' => list<string>`
  - [x] `debugModules()` MUST NOT expose legacy selection state such as `disabled`, `optional_missing`, or `warnings`
  - [x] `debugModules()` MUST NOT call `ModulePlan::toArray()` directly because the current exported shape contains `modules` entries with Composer-owned package metadata

- [x] `packages/core/contracts/src/Kernel/Ops/Exception/KernelOpsFailedException.php`
  - [x] deterministic code-first public exception
  - [x] `public const string ERROR_CODE = 'CORETSIA_KERNEL_OPS_FAILED'`
  - [x] `errorCode(): string` returns exactly `ERROR_CODE`
  - [x] allowed stable reasons are exactly:
    - [x] `operation-failed`
    - [x] `host-boot-failed`
  - [x] `reason(): string` returns the selected stable reason
  - [x] public message is exactly `ERROR_CODE . ': ' . reason`
  - [x] `operation-failed` is the public classification for unexpected `KernelOpsFacade` implementation failures
  - [x] `host-boot-failed` is the public classification for failures crossing the `KernelOpsHostBooter::boot()` boundary
  - [x] MUST NOT retain a previous Throwable
  - [x] Intended for catch/handling in `platform/*` without depending on internal `Coretsia\Kernel\*` exception types

- [x] `packages/core/kernel/src/Ops/KernelOpsHostSeedConfigLoader.php`
  - [x] internal readonly/stateless helper
  - [x] source class docblock MUST contain `@internal`
  - [x] constructor receives exactly one `ComposerPackageInstallPathResolver`
  - [x] resolves package identities through `FoundationModule::COMPOSER_PACKAGE` and `KernelModule::COMPOSER_PACKAGE`; package names MUST NOT be duplicated as independent string policy
  - [x] canonical API:
    - [x] `public function load(): array`
  - [x] loads exactly:
    - [x] `coretsia/core-foundation/config/foundation.php`
    - [x] `coretsia/core-kernel/config/kernel.php`
  - [x] resolves package install roots only through the existing `ComposerPackageInstallPathResolver`
  - [x] validates that both files return map arrays
  - [x] validates each resolved config file with `is_file()` and `is_readable()` before loading
  - [x] loads each file through one internal warning-safe require helper
  - [x] PHP warnings/notices produced during file loading MUST be converted to a deterministic internal failure and MUST NOT reach stdout/stderr
  - [x] restores the previous PHP error handler in `finally`
  - [x] MUST NOT expose the resolved package root or config filesystem path through an exception message
  - [x] returns exactly:
    - [x] `'foundation' => <the map returned by foundation.php>`
    - [x] `'kernel' => <the map returned by kernel.php>`
  - [x] MUST NOT flatten, recursively merge, or cross-merge the two package configuration subtrees
  - [x] reads no skeleton, application, environment, preset, or generated-artifact files
  - [x] performs no ConfigKernel Phase-B loading, directives, merge, validation, explain, fingerprint, graph, or artifact operations

- [x] `packages/core/kernel/src/Ops/KernelOpsExecutionServices.php`
  - [x] internal readonly wiring value; not a public service-locator API
  - [x] source class docblock MUST contain `@internal`
  - [x] contains the exact baseline `kernel` configuration subtree used for target-aware Kernel operations
  - [x] contains the exact operation-service instances required by `KernelOpsFacade`:
    - [x] `BootstrapConfigResolver`
    - [x] `EnvRepositoryBuilder`
    - [x] `ModuleResolutionOrchestrator`
    - [x] `ConfigKernel`
    - [x] `RuntimeContainerGraphCompiler`
    - [x] `ConfigFingerprintInputBuilder`
    - [x] `FingerprintCalculator`
    - [x] `ConfigSourceLocationBuilder`
    - [x] `KernelArtifactOperation`
  - [x] is materialized at most once per final source-operations host through one shared host-owned factory
  - [x] operation-service construction uses the exact baseline Foundation/Kernel seed configuration, never the final console-host Phase-B `kernel.*` configuration
  - [x] operation-service construction uses one dedicated internal `ContainerBuilder`
  - [x] the dedicated builder applies the same preserved Foundation/Kernel baseline provider class list
  - [x] before any operation service is resolved, the dedicated builder overrides the baseline Foundation observability defaults with the exact final source-host instances of:
    - [x] `LoggerInterface`
    - [x] `TracerPortInterface`
    - [x] `MeterPortInterface`
    - [x] Foundation `Stopwatch`
  - [x] target-operation services MUST therefore retain their existing native Kernel observability through the final source-host ports and MUST NOT be pinned to seed-stage Noop observability implementations
  - [x] the dedicated execution container is discarded after the required service instances are captured and MUST NOT be exposed or retained
  - [x] the dedicated execution container MUST NOT resolve `KernelOpsFacade` or `KernelOpsInterface`
  - [x] performs no Kernel operation itself
  - [x] MUST NOT contain the final source-host `ContainerInterface`
  - [x] MUST NOT contain the console-host `ConfigRepositoryInterface`

- [x] `packages/core/kernel/src/Ops/KernelOpsFacade.php`
  - [x] MUST `implements Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
  - [x] source class docblock MUST contain `@internal`; callers depend on `KernelOpsInterface`, not the concrete façade
  - [x] constructor receives the exact `KernelOpsHostInput` seeded by `KernelOpsHostBooter`
  - [x] constructor receives operation services explicitly:
    - [x] `BootstrapConfigResolver`
    - [x] `EnvRepositoryBuilder`
    - [x] `ModuleResolutionOrchestrator`
    - [x] `ConfigKernel`
    - [x] `RuntimeContainerGraphCompiler`
    - [x] `ConfigFingerprintInputBuilder`
    - [x] `FingerprintCalculator`
    - [x] `ConfigSourceLocationBuilder`
    - [x] `KernelArtifactOperation`
  - [x] constructor additionally receives the exact baseline `kernel` configuration subtree carried by `KernelOpsExecutionServices`
  - [x] `hashConfig()` MUST pass that exact baseline `kernel` configuration to `ConfigFingerprintInputBuilder`
  - [x] `KernelOpsFacade` MUST NOT obtain Kernel operation policy from the console-host `ConfigRepositoryInterface` or from final source-host container configuration
  - [x] uses `KernelOpsHostInput::applicationRoot()` as the sole application root for every target-specific `BootstrapInput`
  - [x] constructor receives:
    - [x] `ContextAccessorInterface`
    - [x] `CorrelationIdProviderInterface`
    - [x] `TracerPortInterface`
    - [x] `MeterPortInterface`
    - [x] `LoggerInterface`
    - [x] Foundation `Stopwatch`
  - [x] MUST NOT instantiate noop logger, tracer, or meter directly
  - [x] MUST NOT read observability services from a service locator
  - [x] MUST NOT derive the application root from CWD, argv, environment variables, artifacts, or target config
  - [x] MUST return `Coretsia\Contracts\Kernel\Ops\OpsResult` (contracts DTO; no kernel-local duplicate DTO)
  - [x] MUST throw `Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException` (contracts exception; no kernel-local duplicate exception)
  - [x] maps `expectedGenerationId` and `currentGenerationId` into `OpsResult`
  - [x] maps `KernelArtifactOperation::compile()` / `verify()` `effectivePreset` into `OpsResult::preset()` without performing another Bootstrap Phase-A resolution
  - [x] removes every lower-level artifact `path` field
  - [x] preserves `ConfigExplainer`-normalized repo-relative or logical source paths only inside `debugConfig().data.explain`
  - [x] MUST delegate to existing Kernel components:
    - [x] `BootstrapConfigResolver`
    - [x] `EnvRepositoryBuilder`
    - [x] `ModuleResolutionOrchestrator::resolve()`
    - [x] `ConfigSourceLocationBuilder`
    - [x] `ConfigKernel`
    - [x] `RuntimeContainerGraphCompiler`
    - [x] `ConfigFingerprintInputBuilder`
    - [x] `FingerprintCalculator`
    - [x] `KernelArtifactOperation` for `compileConfig()` / `verifyCache()`
  - [x] `compileConfig()` MUST NOT invoke `ArtifactCompiler` directly
  - [x] `verifyCache()` MUST NOT invoke `CacheVerifier` directly
  - [x] MUST NOT print; MUST NOT leak raw config/env values; MUST NOT leak absolute paths
  - [x] Results MUST be json-like (no floats; no objects/resources)
  - [x] MUST own Kernel-side orchestration for:
    - [x] `validateConfig()`
    - [x] `debugConfig()`
      - [x] `KernelOpsFacade` MUST inspect the `ConfigValidationResult` returned by the single `ConfigKernel::compile(..., explain: true)` call
      - [x] failed validation MUST be mapped to safe `handled_error` with the existing `config-validation-failed` reason token
      - [x] `KernelOpsFacade` MUST NOT call `ConfigValidator` or repeat validation
    - [x] `debugModules()`
    - [x] `compileConfig()`
    - [x] `hashConfig()`
      - [x] failed config validation maps to safe `handled_error`
      - [x] container graph and fingerprint calculation occur only after successful validation
      - [x] passes the exact baseline `kernel` configuration and the same target-specific source-provenance inputs required by the existing compile and verify fingerprint pipeline
      - [x] MUST NOT define a separate hash-only config interpretation
      - [x] `KernelOpsFacade` MUST inspect the `ConfigValidationResult` returned by the single `ConfigKernel::compile()` call
      - [x] failed validation MUST be mapped to `handled_error` before graph compilation
      - [x] `KernelOpsFacade` MUST NOT call `ConfigValidator` or repeat validation
    - [x] `verifyCache()`
  - [x] `validateConfig()`, `debugConfig()`, `debugModules()`, and `hashConfig()` MUST use `ModuleResolutionOrchestrator::resolve()` for installed module resolution
  - [x] `KernelOpsFacade` MUST NOT invoke `ModulePlanResolver` or `ManifestReaderInterface` directly
  - [x] those façade-owned operations MUST invoke `ModuleResolutionOrchestrator::resolve()` at most once per operation
  - [x] `compileConfig()` and `verifyCache()` MUST NOT invoke `ModuleResolutionOrchestrator::resolve()` in `KernelOpsFacade`; their single module-resolution snapshot remains owned by `KernelArtifactOperation`
  - [x] canonical safe operation ids:
    - [x] `config.validate`
    - [x] `config.debug`
    - [x] `config.compile`
    - [x] `config.hash`
    - [x] `cache.verify`
    - [x] `modules.debug`
  - [x] observability ownership:
    - [x] creates one `kernel.operation` span per operation
    - [x] span attributes are limited to:
      - [x] `operation`
      - [x] `app_target`, only after canonical app-target validation completes
      - [x] `preset`, only after the same façade-owned operation has completed `ModuleResolution` successfully or a successful `KernelArtifactOperation` result has returned `effectivePreset`
      - [x] `outcome`
    - [x] `preset` is omitted for `ModuleResolutionException` and every failure that occurs before safe preset eligibility is established
    - [x] observability MUST NOT independently validate a preset or re-run Bootstrap Phase A to make `preset` available
    - [x] `app_target` is omitted when canonical app-target validation did not complete
    - [x] observability MUST NOT trigger Bootstrap Phase A only to recover `preset`
    - [x] observability outcomes are exactly `success|handled_error|failure`
    - [x] emits `kernel.operation_total`
      - [x] labels: `operation|outcome`
    - [x] emits `kernel.operation_duration_ms`
      - [x] labels: `operation|outcome`
    - [x] duration is measured only through Foundation `Stopwatch`
    - [x] metric values are integer-based; floats are forbidden
    - [x] `app_target` and `preset` MUST NOT be metric labels
    - [x] generation ids, fingerprints, artifact names, paths, counts, and exception messages MUST NOT be metric labels
    - [x] generation ids and fingerprints MUST NOT be span attributes
    - [x] observability failures MUST NOT alter operation result or exception semantics
  - [x] logging:
    - [x] emits only a safe completion/failure summary
    - [x] safe fields are limited to `operation|app_target|preset|outcome|correlation_id|uow_id`
    - [x] `app_target` MAY be logged only after canonical app-target validation completes
    - [x] `preset` MAY be logged only under the same safe preset eligibility rule used by `OpsResult` and `kernel.operation` span attributes
    - [x] the rejected raw app-target input MUST NOT be logged
    - [x] MAY include safe `correlation_id` when available through `CorrelationIdProviderInterface`
    - [x] MAY include safe `uow_id` when available through `ContextAccessorInterface`
    - [x] MUST NOT log raw config, env values, Composer metadata, artifact data, paths, generation ids, fingerprints, previous throwable messages, or stack traces
  - [x] context reads:
    - [x] `correlation_id` is obtained only through `CorrelationIdProviderInterface::correlationId()`
    - [x] `KernelOpsFacade` MUST NOT read `ContextKeys::CORRELATION_ID` directly
    - [x] `uow_id` is read only through `ContextAccessorInterface` using `ContextKeys::UOW_ID`
    - [x] reads no other context keys through `ContextAccessorInterface`
    - [x] values returned by `ContextAccessorInterface` are treated as `mixed`
    - [x] `uow_id` MAY be copied into log context only when the retrieved value is a non-empty safe id string containing no whitespace or control bytes
    - [x] missing, null, non-string, or unsafe `uow_id` values are treated as unavailable and MUST be omitted
    - [x] `CorrelationIdProviderInterface::correlationId()` failure and `ContextAccessorInterface::has()` / `get()` failure MUST be isolated and MUST NOT alter operation result or exception semantics
  - [x] context writes:
    - [x] MUST NOT write `ContextStore` directly
    - [x] MUST NOT create correlation ids or UoW ids
    - [x] MUST NOT replace `uow_type`
  - [x] UoW boundary:
    - [x] assumes the caller may already execute inside a canonical Kernel UoW
    - [x] MUST NOT create a nested UoW
    - [x] MUST NOT invoke `KernelRuntimeInterface`
    - [x] MUST NOT invoke hooks or reset orchestration
    - [x] MUST NOT enumerate `kernel.reset`
  - [x] a structurally valid `KernelOpsRequest::appTarget()` that is not a canonical `AppTarget` and therefore reaches `KernelOpsFacade` MUST return:
    - [x] `outcome = handled_error`
    - [x] `reason = bootstrap-invalid-app-target`
    - [x] `appTarget = null`
    - [x] `preset = null`
    - [x] the rejected raw app-target input MUST NOT appear in the result, logs, span attributes, or metric labels
  - [x] handled Kernel-owned rejection mapping:
    - [x] `BootstrapException` → `handled_error` with the exact existing `BootstrapException::reason()` token
    - [x] `ModuleResolutionException` → `handled_error` with the exact existing `ModuleResolutionException::reason()` token
    - [x] `ConfigInvalidException` → `handled_error` with the exact existing `ConfigInvalidException::reason()` token
    - [x] `ConfigReservedNamespaceException` → `handled_error` with the exact existing `ConfigReservedNamespaceException::reason()` token
    - [x] `ConfigDirectiveMixedLevelException` → `handled_error` with the exact existing `ConfigDirectiveMixedLevelException::reason()` token
    - [x] `ConfigDirectiveTypeMismatchException` → `handled_error` with the exact existing `ConfigDirectiveTypeMismatchException::reason()` token
    - [x] a failed `ConfigValidationResult` handled directly by façade-owned validate/debug/hash flow uses the existing `config-validation-failed` reason token without constructing a second validation pipeline
    - [x] exception context, previous Throwable, and exception message MUST NOT be copied into `OpsResult`
  - [x] unexpected implementation failures MUST throw `KernelOpsFailedException` with stable reason `operation-failed`
  - [x] for façade-owned graph production, MUST pass the returned `ModuleResolution` directly to `RuntimeContainerGraphCompiler`; for `compileConfig()` / `verifyCache()`, the internally resolved `ModuleResolution` is passed unchanged by `KernelArtifactOperation` to `ArtifactCompiler` / `CacheVerifier`
  - [x] MUST NOT invoke `ContainerProviderPlanResolver` separately from `RuntimeContainerGraphCompiler`
  - [x] MUST NOT invoke `ManifestReaderInterface::read()` directly or introduce a second manifest-read path after the operation's single `ModuleResolutionOrchestrator::resolve()` invocation
  - [x] the owning orchestration layer MUST use the same `ModuleResolution::plan()` instance throughout the complete operation; for `compileConfig()` / `verifyCache()` that ownership remains inside `KernelArtifactOperation`
  - [x] MUST NOT collect provider definitions directly
  - [x] the graph-producing owner MUST pass the same operation-scoped `ModuleResolution` instance to `RuntimeContainerGraphCompiler`
  - [x] provider planning and definition collection remain owned by `RuntimeContainerGraphCompiler`
  - [x] MUST NOT expose `ModuleResolution`, `ContainerProviderPlan`, provider instances, raw Composer metadata, or absolute paths through `KernelOpsInterface` or `OpsResult`
  - [x] `compileConfig()` MUST return the generation id produced by `ArtifactCompiler`
  - [x] `compileConfig()` MUST report all four generation files
  - [x] `hashConfig()` MUST use the same config, graph, and fingerprint-input pipeline as compile/verify
  - [x] `hashConfig()` MUST NOT invoke `ArtifactCompiler`, `ArtifactGenerationPublisher`, `ArtifactGenerationLocator`, or `CacheVerifier`
  - [x] `verifyCache()` MUST preserve Kernel clean/dirty/invalid classification
  - [x] no operation may call `ArtifactRuntimeBooter`
  - [x] every successful target-aware operation resolves exactly:
    - [x] one target-specific `BootstrapConfig`
    - [x] one `ModuleResolution`
  - [x] every target-aware operation resolves at most one target-specific `BootstrapConfig` and at most one `ModuleResolution`; a handled error MAY terminate before either value is available
  - [x] every successful configuration-aware operation resolves exactly one immutable `EnvRepositoryInterface`:
    - [x] `validateConfig()`
    - [x] `debugConfig()`
    - [x] `compileConfig()`
    - [x] `hashConfig()`
    - [x] `verifyCache()`
  - [x] every configuration-aware operation invokes `EnvRepositoryBuilder` and `ConfigSourceLocationBuilder::build()` at most once; a handled error MAY terminate before either invocation
  - [x] when `ConfigSourceLocationBuilder::build()` is reached, the call is owned by `KernelOpsFacade` for validate/debug/hash and by `KernelArtifactOperation` for compile/verify
  - [x] `debugModules()`:
    - [x] MUST NOT invoke `EnvRepositoryBuilder`
    - [x] MUST NOT invoke `ConfigSourceLocationBuilder`
    - [x] MUST NOT invoke `ConfigKernel`
    - [x] MUST NOT load Config Phase-B package config, config rules, target application config, dotenv, or generated artifacts
    - [x] MAY read `applicationRoot/config/app.php` through `BootstrapConfigResolver`
    - [x] MAY load the selected canonical or application-owned mode preset through the existing module-resolution pipeline
  - [x] invokes only existing production APIs:
    - [x] `validateConfig()` and `debugConfig()` invoke the existing `ConfigKernel`
    - [x] `compileConfig()` invokes the existing `KernelArtifactOperation::compile()`
    - [x] `verifyCache()` invokes the existing `KernelArtifactOperation::verify()`
    - [x] `hashConfig()` uses the same existing ConfigKernel → graph → fingerprint pipeline without publication
  - [x] façade-owned operations pass the exact target-specific `BootstrapConfig`, `EnvRepositoryInterface`, `ModuleResolution::plan()`, `ModuleResolution`, and `ConfigSourceSet` to the existing Kernel services as applicable
  - [x] `compileConfig()` and `verifyCache()` pass only the canonical target-specific `BootstrapInput` to `KernelArtifactOperation`
  - [x] MUST NOT construct package paths or source-candidate arrays directly
  - [x] source-candidate preparation MUST NOT:
    - [x] include or parse config files
    - [x] execute package or skeleton config loaders directly
    - [x] process directives
    - [x] merge config values
    - [x] validate config
    - [x] build explain output
    - [x] create a second repository
    - [x] read the Composer manifest a second time
  - [x] façade-owned operations pass all prepared arguments unchanged to the existing Kernel services
  - [x] MUST NOT substitute the console-host `ConfigRepositoryInterface` for target-specific Phase B compilation
  - [x] `compileConfig()` and `verifyCache()` MUST delegate canonical `BootstrapInput` → Bootstrap → env → `ModuleResolution` → `ConfigSourceSet` preparation and routing to the existing `KernelArtifactOperation`
  - [x] `KernelOpsFacade` MUST NOT resolve `BootstrapConfig`, `EnvRepositoryInterface`, `ModuleResolution`, or `ConfigSourceSet` separately for those two operations
  - [x] successful `KernelArtifactOperation::compile()` / `verify()` results MUST additionally expose `effectivePreset`, taken from the same internally prepared `BootstrapConfig`
  - [x] obtaining `effectivePreset` MUST NOT cause a second Bootstrap Phase-A resolution or a second `ModuleResolutionOrchestrator::resolve()` invocation

- [x] `packages/core/kernel/src/Ops/KernelOpsSourceDefinitionProviderAdapter.php`
  - [x] internal readonly source-host adapter
  - [x] source class docblock MUST contain `@internal`
  - [x] implements:
    - [x] `ServiceProviderInterface`
    - [x] `ContainerDefinitionProviderInterface`
  - [x] wraps exactly one `ContainerDefinitionProviderInterface` used by `KernelOpsHostBooter` when the canonical provider does not implement `ServiceProviderInterface`
  - [x] `register()` contributes this adapter through `ContainerBuilder::registerDefinitionProvider($this)`
  - [x] `define()` delegates unchanged to the wrapped `ContainerDefinitionProviderInterface`
  - [x] MUST NOT modify, reorder, enrich, filter, or duplicate the wrapped provider definitions
  - [x] MUST NOT introduce source-only services, aliases, parameters, or tags of its own
  - [x] MUST NOT become part of `ContainerProviderPlan`
  - [x] MUST NOT enter canonical runtime definitions or compiled artifacts

- [x] `packages/core/kernel/src/Ops/KernelOpsHostInput.php`
  - [x] public `core/kernel` package API value
  - [x] MUST be listed in `packages/core/kernel/PUBLIC_API.md`
  - [x] readonly `applicationRoot`
  - [x] validates `applicationRoot` as a non-empty safe single-line string without filesystem normalization
  - [x] preserves the exact accepted `applicationRoot` value for every target-specific `BootstrapInput`
  - [x] contains no target, preset, config, or artifact paths
  - [x] performs no filesystem reads
  - [x] the exact instance is seeded into the source operations container

- [x] `packages/core/kernel/src/Ops/KernelOpsHostBooter.php`
  - [x] public stateless zero-constructor boot façade
  - [x] public `core/kernel` package API façade
  - [x] MUST be listed in `packages/core/kernel/PUBLIC_API.md`
  - [x] canonical API:
    - [x] `public function boot(KernelOpsHostInput $input): ContainerInterface`
  - [x] may be constructed directly before any container exists
  - [x] requires no generated artifacts or `current`
  - [x] never calls `ArtifactRuntimeBooter`
  - [x] bootstrap seed stage:
    - [x] creates one seed `ContainerBuilder`
    - [x] obtains the exact Foundation and Kernel seed configuration only through `KernelOpsHostSeedConfigLoader`
    - [x] constructs `KernelOpsHostSeedConfigLoader` with the existing `KernelServiceFactory::composerPackageInstallPathResolver()` construction path
    - [x] obtains the baseline provider list from `new FoundationModule()->providers()` followed by `new KernelModule()->providers()`
    - [x] preserves exact module-declared provider order
    - [x] the current declared baseline resolves to `FoundationServiceProvider` followed by `KernelServiceProvider`
    - [x] MUST NOT duplicate the Foundation/Kernel provider registry as a hard-coded provider list
    - [x] instantiates each module-declared baseline provider class exactly once for the seed builder in that order
    - [x] validates every instantiated baseline provider as both `ServiceProviderInterface` and `ContainerDefinitionProviderInterface` before any provider `register()` method is invoked
    - [x] supplies the validated provider instances to `ContainerBuilder::registerProviders()` as one declarative-capable provider batch
    - [x] builds one seed container
    - [x] preserves the exact loaded Foundation/Kernel seed configuration and the exact module-declared baseline provider class list for later `KernelOpsExecutionServices` construction
    - [x] MUST NOT capture seed-container operation-service instances for target-aware Kernel Ops execution
    - [x] MUST NOT discover or register external package providers during the seed stage
  - [x] resolves the console source-host state through existing Kernel services:
    - [x] one console-target `BootstrapConfig`
    - [x] one immutable `EnvRepositoryInterface`
    - [x] one console `ModuleResolution`
    - [x] one console `ConfigSourceSet` built exactly once by `ConfigSourceLocationBuilder` from that same `BootstrapConfig` and `ModuleResolution`
    - [x] one ConfigKernel Phase B result for the console host
    - [x] after the single `ConfigKernel::compile()` invocation, inspect `$compiledConfig['validation']`
    - [x] if `validation->isFailure()`, `throw ConfigInvalidException::fromValidationResult($compiledConfig['validation'])`
    - [x] this is an assertion over the validation result already produced by `ConfigKernel`; it MUST NOT invoke `ConfigValidator` or repeat validation
    - [x] the assertion MUST occur before:
      - [x] `ArrayConfigRepository` construction
      - [x] final `ContainerBuilder` creation
      - [x] enabled-provider instantiation or registration
    - [x] one validated console-host `ConfigRepositoryInterface`
    - [x] one canonical provider plan
  - [x] creates the console-host `ConfigRepositoryInterface` from the validated Phase B config result using the existing Kernel repository implementation
  - [x] MUST NOT introduce another repository implementation
  - [x] MUST NOT treat source-host config compilation as a Kernel operation command
  - [x] MUST NOT emit `kernel.operation` observability for host construction
  - [x] final source-host stage:
    - [x] creates a separate final `ContainerBuilder`
    - [x] uses the validated complete source configuration for final source-runtime, provider, and command composition
    - [x] MUST NOT reconstruct Kernel operation services or Kernel Ops operation policy from the final console-host configuration
  - [x] final provider application:
    - [x] uses the canonical `ContainerProviderPlan`
    - [x] instantiates enabled providers in exact provider-plan order
    - [x] every provider selected by `ContainerProviderPlan` MUST implement `ContainerDefinitionProviderInterface`
    - [x] `KernelOpsHostBooter` MUST instantiate and validate the complete ordered provider sequence before any provider `register()` method is invoked
    - [x] a provider that also implements `ServiceProviderInterface` is supplied unchanged to source-host registration
    - [x] a definition-only provider is wrapped exactly once in `KernelOpsSourceDefinitionProviderAdapter`
    - [x] definition-only providers remain source-host-capable through that adapter and MUST NOT be rejected solely because they do not implement `ServiceProviderInterface`
    - [x] the resulting source-provider sequence consists only of objects implementing both `ServiceProviderInterface` and `ContainerDefinitionProviderInterface`
    - [x] that resulting sequence is supplied to `ContainerBuilder::registerProviders()` as one canonical declarative batch in exact `ContainerProviderPlan` order
    - [x] each canonical provider contribution is collected exactly once
    - [x] the final builder applies exactly one complete provider definition set
    - [x] before installing or seeding host-owned wiring, validates the resulting `TagRegistry`
    - [x] no provider-contributed tag may target a host-owned or source-operations-host-only id that will be replaced or seeded by the host:
      - [x] `BootstrapConfig`
      - [x] `EnvRepositoryInterface`
      - [x] `ConfigRepositoryInterface`
      - [x] `ModulePlan`
      - [x] `RuntimePathContext`
      - [x] `KernelOpsHostBooter`
      - [x] `KernelOpsHostInput`
      - [x] `KernelOpsHostSeedConfigLoader`
      - [x] `KernelOpsExecutionServices`
      - [x] `KernelOpsSourceDefinitionProviderAdapter`
      - [x] `KernelOpsFacade`
      - [x] `KernelOpsInterface`
    - [x] conflicting tags cause deterministic safe host-boot failure; Kernel Ops MUST NOT silently remove or rewrite provider-contributed tags
    - [x] after that provider batch is applied and before final `build()`, installs the canonical host-owned source wiring:
      - [x] `RuntimePathContext` factory through the existing `KernelServiceFactory::runtimePathContext()` construction path
      - [x] one shared `KernelOpsExecutionServices` factory using the preserved baseline Foundation/Kernel configuration and provider class list plus the final source-host observability ports
      - [x] one shared `KernelOpsFacade` factory through `KernelServiceFactory`
      - [x] one `KernelOpsInterface` factory returning the exact shared `KernelOpsFacade` instance
    - [x] Kernel Ops host-owned factories are installed only by `KernelOpsHostBooter`
    - [x] those host-owned factories deterministically replace any same-id definition contributed by an enabled provider
    - [x] then seeds the exact host-owned instances:
      - [x] `KernelOpsHostInput`
      - [x] console `BootstrapConfig`
      - [x] `EnvRepositoryInterface`
      - [x] `ConfigRepositoryInterface`
      - [x] resolved `ModulePlan`
    - [x] host-owned seeded instances deterministically replace any same-id source definition
    - [x] `KernelOpsHostBooter` MUST NOT directly construct or seed a duplicate `RuntimePathContext`
    - [x] builds the final source-operations container only after host-owned factories and instances are installed
    - [x] before `boot()` returns, resolves `KernelOpsInterface` exactly once from the final source-operations container as a host-boot preflight
    - [x] that preflight MUST materialize the shared `KernelOpsExecutionServices`, `KernelOpsFacade`, and `KernelOpsInterface` wiring without invoking any Kernel operation
    - [x] any failure while resolving that preflight MUST remain inside the public `boot()` boundary and MUST be mapped to `KernelOpsFailedException` with stable reason `host-boot-failed`
    - [x] the successfully preflighted `KernelOpsInterface` remains the same shared instance returned by subsequent container resolution
    - [x] no imperative-only module-provider lane exists
    - [x] no second provider plan or provider discovery path exists
    - [x] no package is special-cased by FQCN
  - [x] returned container can resolve:
    - [x] `KernelOpsInterface`
    - [x] `KernelRuntimeInterface`
    - [x] canonical logger, tracer, meter, context accessor, correlation-id provider, and stopwatch
    - [x] source-host-only services contributed through `register()` by enabled dual-interface providers
    - [x] commands contributed through enabled package providers
  - [x] host boot itself MUST NOT:
    - [x] require package config files directly
    - [x] derive package config or rules paths
    - [x] introduce a separate source-candidate DTO or config subsystem
    - [x] load, merge, validate, or explain config outside the existing ConfigKernel pipeline
    - [x] implement config merge or validation
    - [x] create a command UoW
    - [x] write runtime context values
    - [x] execute a command
    - [x] publish or verify artifacts
    - [x] load generated compiled-container definitions or generated container artifacts
    - [x] applying enabled providers through their source `define()` methods is required and is not artifact-runtime boot
  - [x] every failure crossing the public `boot()` boundary MUST be mapped to `KernelOpsFailedException` with stable reason `host-boot-failed`
  - [x] public host-boot failures MUST NOT expose or retain the underlying Throwable
  - [x] public host-boot failures MUST NOT expose absolute paths, config values, env values, provider instances, PHP warning text, or previous Throwable messages

#### Modifies

- [x] `packages/core/kernel/src/Artifacts/Operation/KernelArtifactOperation.php`
  - [x] preserve the existing canonical `compile(BootstrapInput $input)` and `verify(BootstrapInput $input)` APIs
  - [x] preserve existing internal Bootstrap → env → `ModuleResolution` → `ConfigSourceSet` preparation ownership
  - [x] successful `compile()` and `verify()` results additionally contain `effectivePreset: non-empty-string`
  - [x] `effectivePreset` is taken from the same prepared `BootstrapConfig`
  - [x] existing delegated `ArtifactCompiler` / `CacheVerifier` result fields and semantics remain unchanged
  - [x] obtaining `effectivePreset` MUST NOT execute Bootstrap Phase A, env construction, module resolution, or config-source construction a second time
  - [x] the additional `effectivePreset` metadata MUST NOT expose `BootstrapConfig`, `EnvRepositoryInterface`, `ModuleResolution`, `ConfigSourceSet`, raw config/env values, or introduce additional filesystem paths

- [x] `packages/core/kernel/src/Provider/KernelServiceFactory.php`
  - [x] add deterministic construction for `KernelOpsExecutionServices`
  - [x] `KernelOpsExecutionServices` construction receives:
    - [x] the preserved baseline Foundation/Kernel configuration
    - [x] the preserved baseline Foundation/Kernel provider class list
    - [x] the final source-host `LoggerInterface`
    - [x] the final source-host `TracerPortInterface`
    - [x] the final source-host `MeterPortInterface`
    - [x] the final source-host Foundation `Stopwatch`
  - [x] constructs one dedicated internal baseline `ContainerBuilder`
  - [x] applies the preserved baseline provider class list in canonical order
  - [x] replaces baseline observability defaults with the exact supplied final source-host observability instances before resolving any operation service
  - [x] builds the dedicated execution container exactly once
  - [x] resolves exactly the operation services declared by `KernelOpsExecutionServices`
  - [x] discards the dedicated execution container after constructing `KernelOpsExecutionServices`
  - [x] MUST NOT use the final console-host `ConfigRepositoryInterface` or final Phase-B `kernel.*` configuration for target-operation service construction
  - [x] add deterministic construction for `KernelOpsFacade`
  - [x] resolve the exact shared `KernelOpsExecutionServices`
  - [x] inject the baseline `kernel` configuration and exact operation-service instances from `KernelOpsExecutionServices` into `KernelOpsFacade`
  - [x] inject the seeded `KernelOpsHostInput` into `KernelOpsFacade`
  - [x] inject `ContextAccessorInterface`
  - [x] inject `CorrelationIdProviderInterface`
  - [x] inject `TracerPortInterface`
  - [x] inject `MeterPortInterface`
  - [x] inject `LoggerInterface`
  - [x] inject Foundation `Stopwatch`
  - [x] factory construction MUST NOT execute module resolution, config compilation, fingerprint calculation, artifact writing, or cache verification
  - [x] MUST NOT let `KernelOpsFacade` resolve operation services through `ContainerInterface`
  - [x] MUST NOT read CLI configuration
  - [x] MUST NOT construct noop observability implementations
  - [x] MUST NOT execute observability during service construction

- [x] `packages/core/kernel/src/Container/ContainerGraphCompletenessValidator.php`
  - [x] preserve the existing canonical compile-host service-id set unchanged
  - [x] add one separate canonical source-operations-host-only forbidden-runtime set containing:
    - [x] `KernelOpsHostBooter`
    - [x] `KernelOpsHostInput`
    - [x] `KernelOpsHostSeedConfigLoader`
    - [x] `KernelOpsExecutionServices`
    - [x] `KernelOpsSourceDefinitionProviderAdapter`
    - [x] `KernelOpsFacade`
    - [x] `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
  - [x] none of those ids may be added to production `RuntimeContainerSeedIds`
  - [x] combine the existing compile-host set and the new source-operations-host-only set only for runtime-graph rejection; the canonical compile-host classification itself remains unchanged
  - [x] apply the new forbidden-runtime set at every enforcement point already used for compile-host exclusion:
    - [x] service and alias binding ids
    - [x] service construction class
    - [x] alias targets
    - [x] service-method factory service ids
    - [x] tagged service ids
    - [x] required service ids
    - [x] nested service-value references
  - [x] preserve the existing compile-host/runtime graph boundary semantics

- [x] `packages/core/kernel/tests/Integration/KernelArtifactOperationUsesCanonicalCompileInputsTest.php`
  - [x] assert `compile()` returns `effectivePreset` from the same prepared `BootstrapConfig`
  - [x] assert `verify()` returns `effectivePreset` from the same prepared `BootstrapConfig`
  - [x] assert exposing `effectivePreset` does not add a second Bootstrap Phase-A resolution
  - [x] assert exposing `effectivePreset` does not add a second manifest read or `ModuleResolutionOrchestrator::resolve()` invocation

- [x] `packages/core/kernel/PUBLIC_API.md`
  - [x] add `Coretsia\Kernel\Ops\KernelOpsHostBooter`
  - [x] add `Coretsia\Kernel\Ops\KernelOpsHostInput`
  - [x] MUST NOT list `KernelOpsFacade`
  - [x] MUST NOT list `KernelOpsExecutionServices`
  - [x] MUST NOT list `KernelOpsHostSeedConfigLoader`
  - [x] MUST NOT list `KernelOpsSourceDefinitionProviderAdapter`
  - [x] replace the existing conditional wording that artifact, fingerprint, container-compilation, and cache-verification services remain internal only until a dedicated public artifact/cache/kernel-ops façade or contract exists
  - [x] explicitly state that those lower-level compile-host services remain internal after this epic; platform consumers access Kernel operations only through `KernelOpsInterface` and the public source-host boot API

- [x] `packages/core/contracts/README.md`
  - [x] register `Coretsia\Contracts\Kernel\Ops` as the contracts-owned transport-neutral Kernel operations boundary
  - [x] document `KernelOpsInterface`, `KernelOpsRequest`, `OpsResult`, and `KernelOpsFailedException`
  - [x] state that operation orchestration, DI wiring, config/module discovery, artifact I/O, observability execution, and source-host boot remain `core/kernel` implementation responsibilities

- [x] `packages/core/kernel/README.md`
  - [x] document `Coretsia\Kernel\Ops\KernelOpsHostBooter` and `Coretsia\Kernel\Ops\KernelOpsHostInput` in the package Public API section
  - [x] state that operation invocation itself is exposed through `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
  - [x] replace the existing statement that transport and CLI owners directly construct `BootstrapInput` and delegate to `KernelArtifactOperation`; `platform/cli` MUST invoke Kernel operations through `KernelOpsInterface`
  - [x] preserve `KernelArtifactOperation` as the internal canonical compile/verify input-preparation owner behind `KernelOpsFacade`
  - [x] keep `KernelOpsFacade` and all source-host wiring helpers explicitly internal

- [x] `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`
  - [x] preserve `KernelArtifactOperation` as the internal canonical `BootstrapInput` → compile/verify input-preparation owner
  - [x] update the CLI-specific handoff and examples so `platform/cli` resolves `KernelOpsInterface` through the dedicated Kernel source-operations host and MUST NOT call `KernelArtifactOperation` directly
  - [x] document the canonical CLI handoff as `platform/cli` → `KernelOpsInterface` → internal `KernelOpsFacade`; only `compileConfig()` / `verifyCache()` continue through `KernelArtifactOperation`
  - [x] keep `KernelArtifactOperation`, `ArtifactCompiler`, and `CacheVerifier` internal; this epic MUST NOT promote compile-host implementation services to public API

- [x] `docs/ssot/runtime-container-definitions.md`
  - [x] preserve the existing compile-host service-id classification unchanged
  - [x] register `KernelOpsHostBooter`, `KernelOpsHostInput`, `KernelOpsHostSeedConfigLoader`, `KernelOpsExecutionServices`, `KernelOpsSourceDefinitionProviderAdapter`, `KernelOpsFacade`, and `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface` as source-operations-host-only symbols/service ids
  - [x] explicitly state that none of those source-operations-host-only ids is a production runtime seed id
  - [x] explicitly forbid those source-operations-host-only implementation classes and service ids from canonical runtime definitions, compiled runtime graphs, and compiled-runtime references
  - [x] document that `ContainerProviderPlan` eligibility remains defined solely by `ContainerDefinitionProviderInterface`
  - [x] document that source-operations hosting MAY adapt a definition-only provider to `ServiceProviderInterface` only for one-batch source registration
  - [x] the source adapter MUST delegate the canonical `define()` contribution unchanged and MUST NOT alter provider order or production eligibility
  - [x] this adaptation MUST NOT introduce a second provider-discovery or provider-planning path

- [x] `docs/ssot/observability.md`
  - [x] register canonical span `kernel.operation`
  - [x] register counter `kernel.operation_total`
  - [x] register observation `kernel.operation_duration_ms`
  - [x] metric labels are exactly `operation|outcome`
  - [x] allowed `operation` values:
    - [x] `config.validate`
    - [x] `config.debug`
    - [x] `config.compile`
    - [x] `config.hash`
    - [x] `cache.verify`
    - [x] `modules.debug`
  - [x] allowed outcome values:
    - [x] `success`
    - [x] `handled_error`
    - [x] `failure`
  - [x] `preset` span attribute is emitted only after successful façade-owned `ModuleResolution` or from a successful `KernelArtifactOperation` result
  - [x] `preset` span attribute is omitted for `ModuleResolutionException` and every earlier failure; observability MUST NOT independently validate a preset or re-run Bootstrap Phase A to make it available
  - [x] `app_target` span attribute is omitted when canonical app-target validation did not complete
  - [x] `app_target|preset` are allowed only as bounded span attributes
  - [x] generation ids, fingerprints, artifact identities, paths, config values, and exception messages are forbidden labels and attributes
  - [x] existing lower-level Kernel observability remains emitted by its current owners through the same final source-host observability ports
  - [x] `kernel.operation` is an aggregate façade lifecycle and MUST NOT replace or suppress existing lower-level Kernel telemetry

### Cross-cutting (MUST)

#### Context & UoW

- [x] `KernelOpsFacade` is UoW-neutral:
  - [x] works both with and without an already-active caller-owned UoW
  - [x] MUST NOT invoke `KernelRuntimeInterface`
  - [x] MUST NOT begin, finish, or nest a UoW
  - [x] MUST NOT invoke lifecycle hooks
  - [x] MUST NOT invoke reset orchestration
  - [x] MUST NOT enumerate `kernel.reset`
- [x] Context reads:
  - [x] `correlation_id` is obtained only through `CorrelationIdProviderInterface`
  - [x] `KernelOpsFacade` MUST NOT read `ContextKeys::CORRELATION_ID` directly
  - [x] `uow_id` is read only through `ContextAccessorInterface` using `ContextKeys::UOW_ID`
  - [x] no other context key is read through `ContextAccessorInterface`
  - [x] missing, null, non-string, unsafe, or failed `uow_id` reads are treated as unavailable
  - [x] correlation-provider failure is treated as unavailable correlation context
  - [x] only the provider-returned `correlation_id` and validated safe `uow_id` MAY be used for log correlation
  - [x] `correlation_id|uow_id` MUST NOT become `kernel.operation` span attributes or metric labels
  - [x] operation results and exception semantics MUST NOT depend on context availability
- [x] Context writes:
  - [x] `KernelOpsFacade` MUST NOT import or resolve `ContextStore`
  - [x] MUST NOT create correlation ids or UoW ids
  - [x] MUST NOT replace `uow_type`
  - [x] MUST NOT write operation target, preset, generation id, or fingerprint into runtime context
- [x] State and reset:
  - [x] `KernelOpsFacade` is stateless
  - [x] `KernelOpsHostBooter` is stateless
  - [x] no operation result, module resolution, config result, generation, or verification state is cached across calls
  - [x] neither service implements `ResetInterface`
  - [x] neither service is tagged `kernel.stateful` or `kernel.reset`

#### Observability

- [x] Ownership:
  - [x] `KernelOpsFacade` owns Kernel-operation observability
  - [x] lower-level operation services MUST NOT emit duplicate `kernel.operation` lifecycle spans
  - [x] existing lower-level module, config, graph, fingerprint, artifact, and cache observability remains owned by those existing services
  - [x] those lower-level services MUST use the canonical final source-host observability ports supplied through `KernelOpsExecutionServices`
  - [x] only the aggregate `kernel.operation` lifecycle is newly owned by `KernelOpsFacade`
  - [x] seed-stage Foundation Noop observability bindings MUST NOT suppress existing lower-level Kernel telemetry for target-aware Kernel Ops
- [x] Span:
  - [x] name: `kernel.operation`
  - [x] exactly one span per Kernel Ops invocation
  - [x] safe attributes:
    - [x] `operation`
    - [x] `app_target` only after canonical app-target validation completes
    - [x] `preset` only after the same façade-owned operation has completed `ModuleResolution` successfully or a successful `KernelArtifactOperation` result has returned `effectivePreset`
    - [x] `outcome`
  - [x] `preset` is omitted for `ModuleResolutionException` and every failure that occurs before safe preset eligibility is established
  - [x] span construction MUST NOT independently validate a preset or re-run Bootstrap Phase A to make `preset` available
  - [x] when canonical app-target validation did not complete, the `app_target` attribute is omitted
  - [x] generation ids, fingerprints, artifact identities, paths, config values, env values, and exception messages are forbidden span attributes
- [x] Metrics:
  - [x] `kernel.operation_total`
    - [x] labels exactly `operation|outcome`
  - [x] `kernel.operation_duration_ms`
    - [x] labels exactly `operation|outcome`
  - [x] duration is measured through Foundation `Stopwatch`
  - [x] duration is emitted as integer milliseconds
  - [x] allowed outcomes:
    - [x] `success`
    - [x] `handled_error`
    - [x] `failure`
  - [x] `app_target|preset|generation_id|fingerprint|correlation_id|uow_id` MUST NOT be metric labels
- [x] Outcome mapping:
  - [x] successful `OpsResult` → `success`
  - [x] `OpsResult::outcome() === handled_error` → `handled_error`
  - [x] thrown exception → `failure`
- [x] expected outcome classification:
  - [x] valid validation result → `success`
  - [x] invalid configuration → `handled_error`
  - [x] unsupported target or invalid preset selection → `handled_error`
  - [x] completed cache verification, including `clean|dirty|invalid`, → `success`
  - [x] unexpected thrown failure → observability `failure` and `KernelOpsFailedException`
- [x] Logging:
  - [x] emits at most one safe completion or failure summary
  - [x] safe fields:
    - [x] `operation`
    - [x] canonical `app_target`, only after app-target validation completes
    - [x] resolved `preset`, only under the same safe preset eligibility rule used by `OpsResult` and the `kernel.operation` span
    - [x] `outcome`
  - [x] rejected raw app-target input MUST NOT be logged
  - [x] MAY include `correlation_id|uow_id` when safely available
  - [x] MUST NOT log raw config, env values, Composer metadata, artifacts, paths, generation ids, fingerprints, exception messages, previous throwables, or stack traces
- [x] Failure isolation:
  - [x] tracer, meter, or logger failure MUST NOT alter a successful `OpsResult`
  - [x] observability failure MUST NOT replace the primary Kernel operation exception
  - [x] observability failure MUST NOT trigger operation retry

### Security / Result safety (MUST)

- [x] Kernel Ops results are safe by construction.
- [x] Every successful or handled-error `OpsResult` is already safe at the `KernelOpsInterface` boundary.
- [x] `OpsResult` MUST NOT require formatter-side, transport-side, or late redaction to become safe for rendering.
- [x] CLI defense-in-depth redaction MAY process an `OpsResult` after transport mapping, but MUST NOT be relied upon to remove:
  - [x] raw Kernel config or env values
  - [x] Composer metadata
  - [x] artifact payloads
  - [x] filesystem paths
  - [x] Throwable messages or traces
- [x] `core/kernel` MUST NOT depend on:
  - [x] `platform/redaction`
  - [x] `SensitiveDataRedactorInterface`
  - [x] CLI output or formatter classes
- [x] `OpsResult` MAY expose only:
  - [x] stable reason and outcome tokens
  - [x] canonical operation, target, preset, artifact, and generation identifiers
  - [x] safe basenames
  - [x] integer counts and lengths
  - [x] safe hashes
  - [x] recursively normalized json-like maps and lists
- [x] `OpsResult` MUST NOT expose:
  - [x] raw config or env values
  - [x] dotenv values
  - [x] Composer metadata
  - [x] provider instances or class lists
  - [x] absolute filesystem paths
  - [x] relative filesystem paths except `ConfigExplainer`-normalized repo-relative or logical source paths inside `debugConfig().data.explain`
  - [x] artifact payloads or PHP source
  - [x] tokens, credentials, headers, cookies, SQL, or arbitrary payloads
  - [x] Throwable objects, messages, traces, or previous exceptions
- [x] `KernelOpsFailedException`:
  - [x] is code-first
  - [x] exposes only a stable safe reason through its public message and domain fields
  - [x] MUST NOT retain a previous Throwable (`getPrevious() === null`)
  - [x] public message and domain fields MUST NOT contain wrapped Throwable messages, filesystem paths, config values, fingerprints, or generation data
- [x] Safety MUST NOT be configurable:
  - [x] no config key disables result normalization
  - [x] no config key enables raw diagnostics
  - [x] no debug mode exposes unsafe values

### Tests (MUST)

- Unit:
  - [x] `packages/core/kernel/tests/Unit/KernelOpsHostSeedConfigLoaderIsWarningSafeTest.php`
    - [x] missing Foundation seed config produces a deterministic internal failure without PHP warning output
    - [x] an injected temporary Kernel package root whose config file emits a PHP warning produces a deterministic internal failure without PHP warning output
    - [x] temporary package roots are supplied through `ComposerPackageInstallPathResolver` explicit `installRoots`; the test does not mutate process-global Composer installed metadata
    - [x] Throwable emitted by a seed config file is not exposed verbatim
    - [x] absolute package/config paths are absent from the public failure surface
    - [x] the previous PHP error handler is restored

  - [x] `packages/core/kernel/tests/Unit/KernelOpsFacadeReturnsJsonLikeResultsTest.php`
    - [x] MUST assert deep “json-like” invariants for `OpsResult->data`:
      - [x] allowed scalar types: null|bool|int|string
      - [x] arrays only; no objects/resources
      - [x] floats forbidden (hard-fail)
      - [x] maps are recursively key-sorted (`strcmp`) by the producer (kernel), lists preserve order

  - [x] `packages/core/kernel/tests/Unit/KernelOpsResultIsSafeWithoutLateRedactionTest.php`
    - [x] covers every successful and handled-error operation result shape
    - [x] uses raw sensitive fixture values and absolute-path fixtures in lower-level fake inputs
    - [x] asserts none reaches the returned `OpsResult`
    - [x] asserts no `SensitiveDataRedactorInterface` service is resolved or invoked
    - [x] asserts result safety before any CLI formatter or output pipeline is involved

  - [x] `packages/core/kernel/tests/Unit/KernelOpsFacadeObservabilityTest.php`
    - [x] emits exactly one `kernel.operation` span
    - [x] emits exactly one total metric
    - [x] emits exactly one duration metric
    - [x] uses the canonical operation id
    - [x] labels are limited to `operation|outcome`
    - [x] no generation id, fingerprint, path, or raw value reaches observability

  - [x] `packages/core/kernel/tests/Unit/KernelOpsFailedExceptionIsSafeTest.php`
    - [x] previous Throwable is not retained
    - [x] public message and domain fields contain no previous Throwable message, filesystem path, or raw value
    - [x] `errorCode()` is exactly `CORETSIA_KERNEL_OPS_FAILED`
    - [x] reasons are limited to `operation-failed|host-boot-failed`
    - [x] public message is exactly `CORETSIA_KERNEL_OPS_FAILED: <reason>`

  - [x] `packages/core/kernel/tests/Unit/KernelOpsFacadeContextBoundaryTest.php`
    - [x] obtains `correlation_id` only through `CorrelationIdProviderInterface`
    - [x] MUST NOT read `ContextKeys::CORRELATION_ID` through `ContextAccessorInterface`
    - [x] reads only `ContextKeys::UOW_ID` through `ContextAccessorInterface`
    - [x] performs no context writes
    - [x] missing context values do not fail the operation
    - [x] null, non-string, whitespace-containing, or control-byte `uow_id` values are omitted from log context
    - [x] a throwing `CorrelationIdProviderInterface::correlationId()` does not alter the operation result or primary exception
    - [x] a throwing `ContextAccessorInterface::has()` or `get()` does not alter the operation result or primary exception
    - [x] `correlation_id|uow_id` never become `kernel.operation` span attributes or metric labels

  - [x] `packages/core/kernel/tests/Unit/KernelOpsObservabilityFailureDoesNotChangeOutcomeTest.php`
    - [x] tracer failure does not change successful result
    - [x] meter failure does not change successful result
    - [x] logger failure does not replace the operation result or primary exception

- Integration:
  - [x] `packages/core/kernel/tests/Integration/KernelOpsPublicOperationsE2ETest.php`
  - [x] `packages/core/kernel/tests/Integration/KernelOpsHostBootsWithoutCurrentGenerationTest.php`
    - [x] returned container resolves `KernelOpsInterface`
    - [x] `KernelOpsInterface` and `KernelOpsFacade` resolve to the exact same shared instance
    - [x] resolving either id performs no Kernel operation

  - [x] `packages/core/kernel/tests/Integration/KernelOpsHostRejectsTagsForHostOwnedWiringTest.php`
    - [x] a provider-contributed tag targeting `KernelOpsFacade` fails source-host boot before final container build
    - [x] a provider-contributed tag targeting `RuntimePathContext` fails source-host boot before final container build
    - [x] the conflict crosses the public boundary only as safe `KernelOpsFailedException`
    - [x] non-conflicting external command/service tags remain visible unchanged through the final `TagRegistry`

  - [x] `packages/core/kernel/tests/Integration/KernelOpsHandledErrorsPreserveCanonicalKernelReasonsTest.php`
    - [x] `BootstrapException` reason is projected unchanged
    - [x] missing/invalid target preset produced by `ModuleResolutionOrchestrator` preserves the exact `ModuleResolutionException::reason()`
    - [x] `ModuleResolutionException` produced before successful module resolution returns `preset = null`
    - [x] the rejected preset candidate is absent from the `KernelOpsFacade` completion/failure log and `kernel.operation` span attributes; existing lower-level `ModuleResolutionOrchestrator` safe logging remains unchanged
    - [x] `ConfigInvalidException` reason is projected unchanged
    - [x] `ConfigReservedNamespaceException` reason is projected unchanged
    - [x] `ConfigDirectiveMixedLevelException` reason is projected unchanged
    - [x] `ConfigDirectiveTypeMismatchException` reason is projected unchanged
    - [x] failed validation uses `config-validation-failed`
    - [x] failed validation returned by `debugConfig()` maps to `handled_error` with `config-validation-failed` without invoking `ConfigValidator` a second time
    - [x] successful operations return `reason = null`
    - [x] no exception context, message, previous Throwable, or filesystem path reaches `OpsResult`

  - [x] `packages/core/kernel/tests/Integration/KernelServiceProviderDoesNotRegisterKernelOpsOutsideOpsHostTest.php`
    - [x] a normal Foundation + Kernel source container built only through `FoundationServiceProvider` and `KernelServiceProvider` does not register:
      - [x] `KernelOpsExecutionServices`
      - [x] `KernelOpsFacade`
      - [x] `KernelOpsInterface`
    - [x] `KernelOpsHostBooter` remains the sole owner of those source-operations-host registrations
    - [x] existing non-Ops Kernel source services remain resolvable unchanged

  - [x] `packages/core/kernel/tests/Integration/KernelOpsOperationsRequireExplicitAppTargetTest.php`
    - [x] structurally valid but non-canonical raw target produces `handled_error` with `reason = bootstrap-invalid-app-target`
    - [x] returned `OpsResult::appTarget()` is null
    - [x] returned `OpsResult::preset()` is null
    - [x] rejected raw target is absent from `OpsResult::data`, logs, span attributes, and metric labels

  - [x] `packages/core/kernel/tests/Integration/KernelOpsUsesHostApplicationRootForTargetBootstrapTest.php`
    - [x] the exact accepted `KernelOpsHostInput::applicationRoot()` value is passed unchanged into target-specific `BootstrapInput`
    - [x] no `realpath()`, CWD resolution, separator rewriting, or filesystem normalization occurs in `KernelOpsHostInput`

  - [x] `packages/core/kernel/tests/Integration/KernelOpsHostBootFailureIsSafeTest.php`
    - [x] invalid console-host configuration crosses `boot()` only as `KernelOpsFailedException`
    - [x] provider instantiation or source-registration failure crosses `boot()` only as `KernelOpsFailedException`
    - [x] `KernelOpsExecutionServices`, `KernelOpsFacade`, or `KernelOpsInterface` factory-resolution failure occurs during the `boot()` preflight and crosses `boot()` only as `KernelOpsFailedException`
    - [x] public reason is exactly `host-boot-failed`
    - [x] `getPrevious() === null`
    - [x] public message contains no absolute path, config value, provider class, PHP warning text, or previous Throwable message
    - [x] host boot emits no stdout/stderr diagnostics containing internal failure data

  - [x] `packages/core/kernel/tests/Integration/KernelOpsTargetOperationsDoNotUseConsoleHostPhaseBKernelConfigTest.php`
    - [x] console-host Phase-B `kernel.*` overrides do not alter `web|api|worker` Bootstrap, mode, module, artifact, verification, or fingerprint policy
    - [x] `KernelOpsFacade` receives the exact operation services constructed by the shared `KernelOpsExecutionServices` factory from baseline Foundation/Kernel configuration
    - [x] those operation services are not constructed from final console-host Phase-B `kernel.*` configuration
    - [x] `hashConfig()` uses the same baseline `kernel` fingerprint policy as `compileConfig()`
    - [x] `hashConfig()` and `compileConfig()` produce the same generation id for the same target inputs

  - [x] `packages/core/kernel/tests/Integration/KernelOpsExecutionServicesUseFinalSourceObservabilityBindingsTest.php`
    - [x] the final source host uses non-Noop `LoggerInterface`, `TracerPortInterface`, and `MeterPortInterface` bindings supplied through canonical provider composition
    - [x] `KernelOpsExecutionServices` operation services receive those exact final source-host observability instances
    - [x] seed-stage Foundation Noop logger, tracer, and meter instances are not retained by target-operation services
    - [x] the final source-host Foundation `Stopwatch` instance is reused
    - [x] operation-service construction still uses the baseline Foundation/Kernel configuration rather than console-host Phase-B `kernel.*`
    - [x] constructing `KernelOpsExecutionServices` executes no module resolution, ConfigKernel compilation, graph compilation, fingerprint calculation, artifact publication, or cache verification

  - [x] `packages/core/kernel/tests/Integration/KernelOpsDebugConfigPreservesSafeExplainPathsTest.php`
    - [x] preserves `ConfigExplainer`-normalized repo-relative or logical source paths
    - [x] rejects absolute filesystem paths
    - [x] preserves list order and recursively `strcmp`-sorts maps through `KernelOpsFacade` producer normalization before `OpsResult` validation

  - [x] `packages/core/kernel/tests/Integration/KernelOpsCacheVerifyReportsAllFourGenerationFilesTest.php`
    - [x] result data contains exactly `artifacts|current_generation_id|expected_generation_id|state`
    - [x] artifact entries contain exactly `basename|existing_byte_count|expected_byte_count|name|reason|status`
    - [x] artifact names preserve the deterministic order returned by `CacheVerifier`
    - [x] no artifact entry contains a path

  - [x] `packages/core/kernel/tests/Integration/KernelOpsHashReturnsExpectedGenerationIdWithoutWritesTest.php`
    - [x] result data contains exactly `generation_id`

  - [x] `packages/core/kernel/tests/Integration/KernelOpsCompileReportsAllFourGenerationFilesTest.php`
    - [x] result data contains exactly `artifacts|generation_id`
    - [x] artifact entries contain exactly `basename|identity`
    - [x] artifact identities and basenames match the canonical order

  - [x] `packages/core/kernel/tests/Integration/KernelOpsHostSupportsDefinitionOnlyProviderThroughSourceAdapterTest.php`
    - [x] a canonical `ContainerProviderPlan` may contain a provider implementing only `ContainerDefinitionProviderInterface`
    - [x] the definition-only provider is instantiated exactly once
    - [x] it is wrapped exactly once in `KernelOpsSourceDefinitionProviderAdapter`
    - [x] its `define()` contribution is collected exactly once
    - [x] no synthetic `register()` requirement is imposed on the wrapped provider
    - [x] its canonical service, alias, parameter, and tag definitions remain visible through the final source operations container
    - [x] provider order remains exactly the `ContainerProviderPlan` order
    - [x] exactly one complete provider definition set is applied

  - [x] `packages/core/kernel/tests/Integration/KernelOpsHostRejectsInvalidConsoleConfigBeforeFinalProviderRegistrationTest.php`
    - [x] `ConfigKernel` produces exactly one failed `ConfigValidationResult`
    - [x] `KernelOpsHostBooter` converts it through `ConfigInvalidException::fromValidationResult(...)`
    - [x] `ConfigValidator` is not invoked a second time
    - [x] no final source-host provider is instantiated or registered
    - [x] no final source operations container is built

  - [x] `packages/core/kernel/tests/Integration/KernelOpsCompileInvalidConfigDoesNotPublishTest.php`
    - [x] `ConfigKernel` produces exactly one failed `ConfigValidationResult`
    - [x] the operation owner converts that existing failed result into `ConfigInvalidException::fromValidationResult(...)`
    - [x] `ConfigValidator` is not invoked a second time
    - [x] KernelOpsFacade maps the failure safely
    - [x] KernelOpsFacade performs no duplicate validation

  - [x] `packages/core/kernel/tests/Integration/KernelOpsHashInvalidConfigDoesNotBuildGraphTest.php`
    - [x] `ConfigKernel` produces exactly one failed `ConfigValidationResult`
    - [x] `KernelOpsFacade` maps that existing failed result directly to safe `handled_error`
    - [x] `ConfigInvalidException` construction is not required for the façade-owned hash flow
    - [x] `ConfigValidator` is not invoked a second time
    - [x] `RuntimeContainerGraphCompiler` is not invoked
    - [x] `ConfigFingerprintInputBuilder` is not invoked
    - [x] `FingerprintCalculator` is not invoked
    - [x] KernelOpsFacade performs no duplicate validation

  - [x] `packages/core/kernel/tests/Integration/KernelOpsVerifyInvalidConfigDoesNotReadCurrentTest.php`
    - [x] `ConfigKernel` produces exactly one failed `ConfigValidationResult`
    - [x] the operation owner converts that existing failed result into `ConfigInvalidException::fromValidationResult(...)`
    - [x] `ConfigValidator` is not invoked a second time
    - [x] KernelOpsFacade maps the failure safely
    - [x] KernelOpsFacade performs no duplicate validation

  - [x] `packages/core/kernel/tests/Integration/KernelOpsReusesCanonicalConfigPipelineTest.php`
    - [x] uses one Bootstrap Phase A resolution
    - [x] uses one `ModuleResolution`
    - [x] uses one canonical config-location input set
    - [x] delegates loading, merge, validation, and explain to existing `ConfigKernel`
    - [x] KernelOpsFacade performs no package-path or config-file discovery

  - [x] `packages/core/kernel/tests/Integration/KernelOpsHostComposesEnabledProvidersInCanonicalPlanOrderTest.php`
    - [x] seed and final source-host builders are distinct instances
    - [x] seed builder obtains providers from `new FoundationModule()->providers()` followed by `new KernelModule()->providers()`
    - [x] current module-declared order resolves to `FoundationServiceProvider` followed by `KernelServiceProvider`
    - [x] module-declared provider class strings are instantiated exactly once before seed registration
    - [x] both baseline instances implement `ServiceProviderInterface` and `ContainerDefinitionProviderInterface`
    - [x] no duplicate hard-coded baseline provider registry exists in Kernel Ops
    - [x] final builder receives the validated complete source configuration
    - [x] canonical Kernel Ops host-owned factories are installed after the single provider batch is applied and before the final container is built
    - [x] host-owned `RuntimePathContext`, `KernelOpsExecutionServices`, `KernelOpsFacade`, and `KernelOpsInterface` wiring deterministically replaces any same-id enabled-provider definition
    - [x] exact host-owned source values are seeded after provider definition application and before the final container is built
    - [x] host-owned factories and seeded instances deterministically replace any same-id source definition
    - [x] `KernelOpsInterface` still resolves to the exact shared `KernelOpsFacade` instance
    - [x] canonical providers are instantiated in `ContainerProviderPlan` order
    - [x] source adaptation does not create a second canonical provider instance or change that order
    - [x] every enabled provider implements `ContainerDefinitionProviderInterface`
    - [x] providers already implementing `ServiceProviderInterface` are supplied unchanged
    - [x] definition-only providers are wrapped exactly once in `KernelOpsSourceDefinitionProviderAdapter`
    - [x] every object submitted to the final `ContainerBuilder::registerProviders()` batch implements both required source-registration interfaces
    - [x] exactly one complete provider batch is submitted
    - [x] exactly one complete provider definition set is applied before host-owned instance seeding
    - [x] no imperative-only provider lane exists
    - [x] external tagged commands are visible through the final `TagRegistry`

  - [x] `packages/core/kernel/tests/Integration/KernelOpsRunsInsideExistingCallerUowWithoutNestingTest.php`
    - [x] an arbitrary caller-owned UoW remains the only UoW
    - [x] the test does not require or instantiate platform/cli
    - [x] KernelOpsFacade does not call KernelRuntime
    - [x] KernelOpsFacade does not trigger reset directly

  - [x] `packages/core/kernel/tests/Integration/KernelOpsCompileUsesSingleModuleResolutionSnapshotTest.php`
    - [x] `KernelOpsFacade` does not invoke `ModuleResolutionOrchestrator::resolve()` for `compileConfig()`
    - [x] `KernelArtifactOperation` invokes `ModuleResolutionOrchestrator::resolve()` exactly once
    - [x] Composer manifest is read exactly once
    - [x] the returned `ModuleResolution` is passed unchanged from `KernelArtifactOperation` to `ArtifactCompiler`
    - [x] provider planning occurs only inside `RuntimeContainerGraphCompiler`
    - [x] the same `ModulePlan` instance is supplied to downstream compilation
    - [x] no second manifest read occurs during provider definition collection
    - [x] `ArtifactCompiler` does not resolve module services itself
    - [x] `effectivePreset` comes from the same prepared `BootstrapConfig`
    - [x] obtaining `effectivePreset` causes no second Bootstrap Phase-A resolution

  - [x] `packages/core/kernel/tests/Integration/KernelOpsHashUsesSingleModuleResolutionSnapshotTest.php`
    - [x] one module-resolution snapshot is used for the complete hash operation
    - [x] no second manifest read occurs
    - [x] `FingerprintCalculator` does not resolve modules itself

  - [x] `packages/core/kernel/tests/Integration/KernelOpsCacheVerifyUsesSingleModuleResolutionSnapshotTest.php`
    - [x] `KernelOpsFacade` does not invoke `ModuleResolutionOrchestrator::resolve()` for `verifyCache()`
    - [x] `KernelArtifactOperation` invokes `ModuleResolutionOrchestrator::resolve()` exactly once
    - [x] Composer manifest is read exactly once
    - [x] the returned `ModuleResolution` is passed unchanged from `KernelArtifactOperation` to `CacheVerifier`
    - [x] provider planning consumes that same snapshot
    - [x] the same plan is supplied to verification inputs
    - [x] `CacheVerifier` does not resolve modules itself
    - [x] `effectivePreset` comes from the same prepared `BootstrapConfig`
    - [x] obtaining `effectivePreset` causes no second Bootstrap Phase-A resolution

  - [x] `packages/core/kernel/tests/Integration/KernelOpsValidateConfigUsesSingleModuleResolutionSnapshotTest.php`
    - [x] one `ModuleResolution` is used for the complete validation operation
    - [x] Composer manifest is read exactly once
    - [x] the same `ModulePlan` is supplied to `ConfigKernel`
    - [x] no provider or manifest discovery is repeated

  - [x] `packages/core/kernel/tests/Integration/KernelOpsDebugConfigUsesSingleModuleResolutionSnapshotTest.php`
    - [x] one `ModuleResolution` is used for the complete debug operation
    - [x] Composer manifest is read exactly once
    - [x] the same `ModulePlan` is supplied to `ConfigKernel`
    - [x] safe explain output does not expose raw config or env values

- Contract:
  - [x] `packages/core/kernel/tests/Contract/KernelOpsHasNoRedactionOrCliDependencyContractTest.php`
    - [x] rejects `SensitiveDataRedactorInterface`
    - [x] rejects `platform/redaction` package and namespace references
    - [x] rejects redaction-service resolution
    - [x] rejects `Coretsia\Platform\*`
    - [x] rejects CLI formatter and output classes

  - [x] `packages/core/kernel/tests/Contract/KernelOpsSourceHostServicesAreNotRuntimeDefinitionsContractTest.php`
    - [x] existing canonical compile-host service-id assertions remain unchanged
    - [x] locks the complete source-operations-host-only forbidden-runtime set
    - [x] asserts none of those ids is a production `RuntimeContainerSeedId`
    - [x] rejects each source-host-only symbol as a runtime service id or alias id
    - [x] rejects each source-host-only implementation class as a service construction class even when registered under another id
    - [x] rejects alias targets, service-method factory references, tags, required-service references, and nested service references to source-host-only ids
    - [x] asserts none enters generated runtime definition descriptors

  - [x] `packages/core/contracts/tests/Contract/KernelOpsContractsShapeContractTest.php`
    - [x] locks the exact six-method `KernelOpsInterface` surface
    - [x] locks `KernelOpsRequest` to explicit `appTarget` only
    - [x] locks constructor-level rejection of empty, multiline, and control-byte `appTarget` input before any `KernelOpsInterface` invocation
    - [x] locks `OpsResult::SCHEMA_VERSION === 1`
    - [x] locks `OpsResult` outcome and nullable-reason semantics
    - [x] locks the public `KernelOpsFailedException` error-code/reason API
    - [x] rejects `Coretsia\Kernel\*`, `Coretsia\Platform\*`, filesystem, container-builder, and transport implementation dependencies from the contracts-owned port

  - [x] `packages/core/kernel/tests/Contract/KernelOpsDoesNotDuplicateConfigPipelineContractTest.php`
    - [x] rejects Kernel Ops-local implementations of:
      - [x] `ConfigLoaderInterface`
      - [x] `MergeStrategyInterface`
      - [x] `ConfigValidatorInterface`
    - [x] rejects Kernel Ops-local config merger, rules loader, directive processor, validator, and explainer classes
    - [x] rejects direct `require|include` of package, application, environment, preset, or generated config files from `src/Ops`, except `KernelOpsHostSeedConfigLoader`
    - [x] `KernelOpsHostSeedConfigLoader` MAY load only the two declared Foundation/Kernel seed config files resolved through `ComposerPackageInstallPathResolver`
    - [x] all other Kernel Ops classes MUST NOT directly `require|include` config files
    - [x] rejects a second `ConfigRepositoryInterface` implementation
    - [x] rejects `ConfigSourcePlan` or equivalent parallel config model
    - [x] `KernelOpsFacade` allows only orchestration calls into the existing Bootstrap, module, ConfigKernel, graph, fingerprint, artifact, and verification services
    - [x] `KernelOpsHostBooter` MAY additionally compose the existing container, module-provider, provider-plan, and seed-config infrastructure required to build the source operations host

  - [x] `packages/core/kernel/tests/Contract/KernelOpsDoesNotWriteContextOrControlUowContractTest.php`
    - [x] `KernelOpsFacade` has no direct `ContextStore` write
    - [x] `KernelOpsFacade` has no `KernelRuntimeInterface` dependency
    - [x] `KernelOpsFacade` has no `ResetOrchestrator`
    - [x] `KernelOpsFacade` performs no `kernel.reset` discovery
    - [x] `KernelOpsHostBooter` MAY compose a container that provides `KernelRuntimeInterface`, but MUST NOT resolve or invoke it
    - [x] `KernelOpsHostBooter` MUST NOT begin a UoW, invoke lifecycle hooks, invoke reset orchestration, or enumerate `kernel.reset`

### DoD (MUST)

- [x] `KernelOpsRequest` contains only explicit app target
- [x] effective preset is resolved exclusively by BootstrapConfigResolver
- [x] each successful module-aware operation uses exactly one `ModuleResolution`; every invocation performs module resolution at most once
- [x] compile returns the actually published generation id
- [x] hash performs no writes or current read
- [x] verify reports four artifacts and `clean|dirty|invalid`
- [x] Ops results contain no raw values or absolute filesystem paths
- [x] Every `OpsResult` is safe before any CLI formatter or defense-in-depth redactor receives it.
- [x] Kernel Ops result safety does not depend on late redaction.
- [x] only `debugConfig().data.explain` may contain `ConfigExplainer`-normalized repo-relative or logical source paths
- [x] Kernel Ops remains dedicated source-operations-host-only and does not enter generic compile-host wiring or compiled application runtime
- [x] Kernel Ops introduces no config subtree
- [x] Kernel Ops emits canonical span/metrics through injected ports
- [x] observability failures never alter operation semantics
- [x] Kernel Ops reads only safe context values and performs no context writes
- [x] Kernel Ops never creates nested UoW or triggers reset directly
- [x] source operations host never submits a mixed declarative/imperative provider batch
- [x] source operations host rejects invalid console-host configuration before final provider registration
- [x] source operations host preserves canonical `ContainerProviderPlan` eligibility and adapts definition-only providers without changing their definitions or order
- [x] `KernelOpsHostBooter` can be constructed before any container exists
- [x] Kernel Ops has no CLI, formatter, ANSI, or redaction dependency
- [x] `OpsResult` outcomes are exactly `success|handled_error`
- [x] observability outcomes are exactly `success|handled_error|failure`
- [x] target-operation services use baseline Kernel operation policy together with final source-host observability ports; seed-stage Noop observability implementations are not retained

---

### 2.25.0 Sensitive data redaction boundary (MUST) [CONTRACTS+IMPL+DOC]

---
type: package
phase: 2
epic_id: "2.25.0"
owner_path: "packages/platform/redaction/"

package_id: "platform/redaction"
composer: "coretsia/platform-redaction"
kind: runtime
module_id: "platform.redaction"

goal: "Надати до 2.30.0 єдиний config-free deterministic sensitive-data redaction port і default platform implementation для string та json-like diagnostic/output values, з canonical classification, traversal, summary, hashing і fail-closed semantics; producer-owned safe-by-construction shapes залишаються обов’язковими."
provides:
- "Exact contracts-level redaction port for explicitly sensitive string values and recursively processed json-like diagnostic/output values."
- "Immutable RedactionContext, RedactionKind, RedactionMode, and RedactedValue contracts with exact deterministic shapes."
- "Default config-free platform redactor with canonical key classification, value classification, traversal precedence, placeholder, byte-length, and domain-separated SHA-256 summary policies."
- "Fixed resource limits and deterministic fail-closed behavior for invalid or unsafe redaction input."
- "One shared SSoT for platform/cli and later eligible runtime diagnostic/output consumers instead of duplicate generic redaction engines or mutable policy registries; lower-layer Core safe-by-construction guards remain owner-local."
- "Policy: redaction is defense in depth and MUST NOT replace producer-owned safe-by-construction diagnostic shapes."
- "Boundary: Kernel Ops results remain safe by construction and MUST NOT consume this redaction package or port."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-0010-sensitive-data-redaction-boundary.md"
ssot_refs:
- "docs/ssot/sensitive-data-redaction.md"
- "docs/ssot/json-like-runtime-values.md"
- "docs/ssot/modules-and-manifests.md"
- "docs/ssot/application-dependency-sync.md"
- "docs/ssot/error-descriptor.md"
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/observability.md"
- "docs/ssot/secrets-contracts.md"
- "docs/ssot/config-and-env.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.90.0 — observability and ErrorDescriptor contracts exist for SSoT alignment.
  - 1.100.0 — error and diagnostic safety policy exists.
  - 1.180.0 — contracts secrets port exists for secret-reference policy alignment.
  - 1.200.0 — Foundation declarative DI/container baseline exists.
  - 1.275.0 — Foundation json-like normalization and stable JSON encoding primitives exist.

- Repository packaging baseline:
  - repo-root `LICENSE` and `NOTICE` MUST exist before this epic is implemented because `docs/architecture/PACKAGING.md` defines them as the canonical legal SSoT for layered packages
  - if either canonical root legal file is absent, implementation MUST stop and the repository packaging baseline MUST be repaired outside this epic first
  - this epic MUST NOT promote an arbitrary existing package copy into the canonical legal SSoT or synthesize root legal files as part of `platform/redaction`

- Repository architecture-tooling baseline:
  - canonical architecture generators referenced by repo-root Composer scripts and architecture SSoT MUST exist before this epic is implemented
  - required generator entrypoints include:
    - `tools/build/package_index.php`
    - `tools/build/installation_catalog.php`
    - `tools/build/deptrac_generate.php`
  - if the canonical repository tooling baseline is absent, implementation MUST stop and that baseline MUST be repaired outside this epic first
  - this epic MAY regenerate derived architecture artifacts but MUST NOT synthesize, replace, or redefine the canonical repository generator/gate implementations

- Adjacent boundary, not an implementation dependency:
  - 2.20.0 Kernel Ops results remain safe by construction.
  - `core/kernel` MUST NOT consume `SensitiveDataRedactorInterface`.
  - this epic MUST NOT modify Kernel Ops production source or result DTOs.

- Required deliverables (exact paths):
  - `packages/core/contracts/src/Observability/Errors/ErrorDescriptor.php`
  - `packages/core/contracts/src/Secrets/SecretsResolverInterface.php`
  - `packages/core/foundation/src/Serialization/JsonLikeNormalizer.php`
  - `packages/core/foundation/src/Serialization/JsonLikeNormalizationLimits.php`
  - `packages/core/foundation/src/Serialization/Exception/JsonLikeNormalizationException.php`
  - `packages/core/foundation/src/Serialization/StableJsonEncoder.php`
  - `packages/core/foundation/src/Container/ServiceProviderInterface.php`
  - `packages/core/foundation/src/Container/ContainerBuilder.php`
  - `packages/core/foundation/src/Container/Definition/ContainerDefinitionProviderInterface.php`
  - `packages/core/foundation/src/Container/Definition/ContainerDefinitionBuilder.php`
  - `packages/core/foundation/src/Container/Definition/ContainerDefinitionContext.php`
  - `packages/core/foundation/src/Container/Definition/ContainerValueReference.php`

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - defines redaction contracts in `core/contracts`

#### Compile-time deps (deptrac-enforceable) (MUST)

Contracts additions depend on:
- none

`platform/redaction` depends directly on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `core/kernel`
- every other `platform/*` package
- `integrations/*`
- `enterprise/*`
- `devtools/*`
- `psr/log`
- observability ports
- context and UoW ports
- reset orchestration
- vendor HTTP/database/mail/auth/secrets SDK concretes

Required contracts:
- `Coretsia\Contracts\Security\SensitiveDataRedactorInterface`
- `Coretsia\Contracts\Security\RedactionContext`
- `Coretsia\Contracts\Security\RedactionKind`
- `Coretsia\Contracts\Security\RedactionMode`
- `Coretsia\Contracts\Security\RedactedValue`
- `Coretsia\Contracts\Security\Exception\RedactionException`

Required Foundation APIs:
- `Coretsia\Foundation\Serialization\JsonLikeNormalizer`
- `Coretsia\Foundation\Serialization\JsonLikeNormalizationLimits`
- `Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException`
- `Coretsia\Foundation\Serialization\StableJsonEncoder`
- declarative Foundation container-definition APIs

`platform/redaction` MUST NOT introduce a logger, tracer, meter, error reporter, context accessor, Kernel runtime, reset orchestrator, or service-locator dependency.

### Entry points / integration points (MUST)

- Runtime DI:
  - when module `platform.redaction` is enabled, its provider contributes exactly one `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` binding;
  - the binding points to `Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor`;
  - the binding is contributed through the canonical dual-interface service provider;
  - the package MUST NOT auto-enable itself from Composer presence, app environment, debug mode, config, or service availability;
  - no service locator or runtime implementation selection exists.

- Module composition:
  - this epic does not modify framework-default mode presets;
  - package-level tests validate module/provider metadata and explicit Foundation provider application only; Kernel-owned module enablement and provider-plan selection are not reimplemented inside this package;
  - consumer modules own their dependency edge on `platform.redaction`;
  - 2.30.0 `platform.cli` MUST require module `platform.redaction` through canonical module metadata;
  - absence of a required redaction module MUST fail during module planning rather than during `OutputFormatter` resolution.

- Direct string redaction:
  - consumers that already know a string value is sensitive call `redactValue(...)` with an explicit `RedactionKind`.
  - callers MUST NOT pass an unclassified known-sensitive string through generic value detection.

- Recursive json-like redaction:
  - consumer diagnostic/output boundaries whose owner policy requires this shared defense-in-depth layer call `redactJsonLike(...)`.
  - boundaries defined as safe by construction, including Kernel Ops, remain independent from this port.
  - key and value classification, recursive traversal, and summary generation remain owned by `platform/redaction`.
  - successful redaction does not grant destination-boundary admissibility; consumer-owned schema, semantic-key, path, cardinality, and resource-limit policies remain authoritative.

- Package owners:
  - producer packages MUST emit safe-by-construction shapes.
  - omission or a stable reason token is preferred when the original value is not operationally necessary.
  - redaction is a final defense-in-depth boundary, not permission to transport arbitrary raw values.

- Kernel Ops:
  - `core/kernel` does not consume this port.
  - `OpsResult` is already safe before CLI receives it.

- Artifacts:
  - generated artifacts MUST NOT rely on runtime redaction as permission to include raw config, env, payload, credential, or secret values.

### Deliverables (MUST)

#### Creates

Contracts:
- [x] `packages/core/contracts/src/Security/Exception/RedactionException.php`
  - [x] final contracts-level port failure
  - [x] extends `RuntimeException`
  - [x] public typed constants:
    - [x] `public const string ERROR_CODE = 'CORETSIA_REDACTION_FAILED'`
    - [x] `public const string REASON_INPUT_INVALID = 'input-invalid'`
    - [x] `public const string REASON_INPUT_LIMIT_EXCEEDED = 'input-limit-exceeded'`
    - [x] `public const string REASON_SENSITIVE_MAP_KEY = 'sensitive-map-key'`
    - [x] `public const string REASON_OUTPUT_INVALID = 'output-invalid'`
    - [x] `public const string REASON_INTERNAL_FAILURE = 'internal-failure'`
  - [x] exact private reason allowlist is defined from those constants only
  - [x] implements the exact error code, reason allowlist, named constructors, and message contract defined under `Cross-cutting → Errors`
  - [x] constructor is private
  - [x] instances are created only through the exact named constructors
  - [x] exact named constructors:
    - [x] `public static function inputInvalid(): self`
    - [x] `public static function inputLimitExceeded(): self`
    - [x] `public static function sensitiveMapKey(): self`
    - [x] `public static function outputInvalid(): self`
    - [x] `public static function internalFailure(): self`
  - [x] exact accessors:
    - [x] `public function errorCode(): string`
    - [x] `public function reason(): string`
  - [x] custom exception state stores only the stable reason
  - [x] does not accept or retain a previous Throwable
  - [x] the exception message and custom exception state contain no rejected value, map key, scope, hash, length, path, pattern, class name, resource id, or payload fragment
  - [x] PHP `Throwable` stack-trace storage is not redefined or sanitized by this package; existing Core error/observability boundary policy remains authoritative and raw stack traces MUST NOT be exported through diagnostic/output sinks

- [x] `packages/core/contracts/src/Security/SensitiveDataRedactorInterface.php`
  - [x] exact API:
    - [x] `public function redactValue(string $value, RedactionKind $kind, RedactionContext $context): RedactedValue`
    - [x] `public function redactJsonLike(mixed $value, RedactionContext $context): mixed`
  - [x] `redactJsonLike()` return is restricted by PHPDoc to recursively json-like `null|bool|int|string|array`
  - [x] `redactValue()` requires an explicit owner-selected `RedactionKind`
  - [x] `redactJsonLike()` uses only the canonical platform key/value classification policy
  - [x] no callback, mutable policy registry, config argument, logger, observability, ContextStore, or service-locator surface
  - [x] no platform implementation type appears in the contracts API
  - [x] both methods declare through PHPDoc:
    - [x] `@throws \Coretsia\Contracts\Security\Exception\RedactionException`
  - [x] no platform-local exception type appears in the contracts API
  - [x] neither method writes stdout/stderr
  - [x] neither method may return the original string value or complete json-like branch once that value or branch has been selected for redaction

- [x] `packages/core/contracts/src/Security/RedactionContext.php`
  - [x] final readonly value object
  - [x] `public const int SCHEMA_VERSION = 1`
  - [x] constructor:
    - [x] `string $scope`
    - [x] `RedactionMode $mode = RedactionMode::Placeholder`
  - [x] exact accessors:
    - [x] `schemaVersion(): int`
    - [x] `scope(): string`
    - [x] `mode(): RedactionMode`
  - [x] `scope`:
    - [x] is a stable operation/output-boundary identifier
    - [x] matches `\A[a-z][a-z0-9]*(?:[._:-][a-z0-9]+)*\z`
    - [x] is at most 128 bytes
    - [x] rejects empty, multiline, NUL, ESC, and control-byte values
  - [x] invalid scope throws exactly `InvalidArgumentException('redaction-context-scope-invalid')`
  - [x] constructor validation is limited to the exact syntax, byte bound, and control-byte policy
  - [x] callers MUST NOT construct scopes from paths, endpoints, user/tenant ids, tokens, field values, request ids, correlation ids, or other high-cardinality runtime data
  - [x] semantic high-cardinality policy is caller-owned and documented in SSoT; the value object MUST NOT inspect runtime context to infer it
  - [x] examples of valid scopes: `cli.output`, `logging.record`, `http.problem-detail`
  - [x] contains no raw redacted value or runtime ContextStore dependency

- [x] `packages/core/contracts/src/Security/RedactionKind.php`
  - [x] string-backed enum with exactly:
    - [x] `Unknown = 'unknown'`
    - [x] `Secret = 'secret'`
    - [x] `SecretReference = 'secret-reference'`
    - [x] `Credential = 'credential'`
    - [x] `Authorization = 'authorization'`
    - [x] `Cookie = 'cookie'`
    - [x] `SessionId = 'session-id'`
    - [x] `Token = 'token'`
    - [x] `Payload = 'payload'`
    - [x] `Sql = 'sql'`
    - [x] `Pii = 'pii'`
    - [x] `EnvValue = 'env-value'`
    - [x] `LocalPath = 'local-path'`
  - [x] contains identifiers only
  - [x] contains no key/value classification, hashing, config, or presentation logic

- [x] `packages/core/contracts/src/Security/RedactionMode.php`
  - [x] string-backed enum with exactly:
    - [x] `Placeholder = 'placeholder'`
    - [x] `Length = 'length'`
    - [x] `Hash = 'hash'`
    - [x] `HashAndLength = 'hash-and-length'`
  - [x] `Placeholder` is the default
  - [x] `Length|Hash|HashAndLength` require explicit owner selection through `RedactionContext`
  - [x] one `RedactionContext::mode()` applies uniformly to every branch selected for redaction during one `redactJsonLike()` call
  - [x] the redactor MUST NOT silently downgrade, upgrade, or replace the selected mode per `RedactionKind`, field, branch, or classifier result
  - [x] callers MAY use a non-placeholder mode with `redactJsonLike()` only when the owner/boundary policy explicitly permits that same metadata disclosure for every sensitive branch that may be selected during that call
  - [x] when a recursive boundary may contain branches with different disclosure requirements, omission or `Placeholder` is mandatory unless the owner splits those values into separately governed redaction operations
  - [x] per-kind or per-branch disclosure-policy registries are not introduced by this epic
  - [x] contains no `raw|none|disabled|passthrough|debug` mode
  - [x] debug or environment state MUST NOT change the selected disclosure mode

- [x] `packages/core/contracts/src/Security/RedactedValue.php`
  - [x] final readonly value object
  - [x] `public const int SCHEMA_VERSION = 1`
  - [x] constructor receives exactly:
    - [x] `RedactionKind $kind`
    - [x] `RedactionMode $mode`
    - [x] `?int $length`
    - [x] `?string $hash`
  - [x] exact accessors:
    - [x] `schemaVersion(): int`
    - [x] `kind(): RedactionKind`
    - [x] `mode(): RedactionMode`
    - [x] `length(): ?int`
    - [x] `hash(): ?string`
    - [x] `toArray(): array`
    - [x] exact PHPDoc:
      - [x] `@return array{hash: ?string, kind: string, length: ?int, mode: string, redacted: true, schemaVersion: 1}`
  - [x] `toArray()` returns exactly these `strcmp`-ordered keys:
    - [x] `hash`
    - [x] `kind`
    - [x] `length`
    - [x] `mode`
    - [x] `redacted`
    - [x] `schemaVersion`
  - [x] exported values:
    - [x] `redacted = true`
    - [x] `schemaVersion = 1`
    - [x] enum fields are exported through their string values
  - [x] mode invariants:
    - [x] `placeholder` → `length = null`, `hash = null`
    - [x] `length` → non-negative `length`, `hash = null`
    - [x] `hash` → `length = null`, required `hash`
    - [x] `hash-and-length` → non-negative `length`, required `hash`
  - [x] hash matches `\Asha256:[a-f0-9]{64}\z`
  - [x] every invalid constructor combination throws exactly `InvalidArgumentException('redacted-value-shape-invalid')`
  - [x] negative lengths throw the same fixed exception
  - [x] malformed hashes throw the same fixed exception
  - [x] exception messages contain no hash value or other constructor input
  - [x] MUST NOT retain the raw value, raw bytes, context, path, source metadata, or previous Throwable

Package scaffold:
- [x] `packages/platform/redaction/composer.json`
  - [x] `name = coretsia/platform-redaction`
  - [x] `type = library`
  - [x] `license = Apache-2.0`
  - [x] requires exactly:
    - [x] `php: ^8.4`
    - [x] `coretsia/core-contracts: ^0.7.0`
    - [x] `coretsia/core-foundation: ^0.7.0`
  - [x] MUST NOT require:
    - [x] `coretsia/core-kernel`
    - [x] another platform package
    - [x] `psr/log`
    - [x] a vendor SDK
  - [x] PSR-4:
    - [x] `Coretsia\Platform\Redaction\` → `src/`
  - [x] autoload-dev:
    - [x] `Coretsia\Platform\Redaction\Tests\` → `tests/`
  - [x] `extra.coretsia` exact metadata:
    - [x] `kind = runtime`
    - [x] `moduleId = platform.redaction`
    - [x] `moduleClass = Coretsia\Platform\Redaction\Module\RedactionModule`
    - [x] `providers = [Coretsia\Platform\Redaction\Provider\RedactionServiceProvider]`
    - [x] `requires = [core.foundation]`
    - [x] `conflicts = []`
    - [x] no `defaultsConfigPath`

- [x] `packages/platform/redaction/LICENSE`
  - [x] byte-identical to the canonical monorepo-root `LICENSE`

- [x] `packages/platform/redaction/NOTICE`
  - [x] byte-identical to the canonical monorepo-root `NOTICE`

- [x] `packages/platform/redaction/SECURITY.md`

- [x] `packages/platform/redaction/README.md`
  - [x] package purpose and ownership
  - [x] includes the canonical `## Observability`, `## Errors`, and `## Security / Redaction` package-policy sections
  - [x] direct string versus recursive json-like entrypoints
  - [x] cross-package consumers use `SensitiveDataRedactorInterface`; `SensitiveKeyClassifier`, `SensitiveValueClassifier`, and `StableRedactionHasher` are package-internal implementation details
  - [x] examples use synthetic values only
  - [x] consumers explicitly construct bounded `RedactionContext`
  - [x] omission and safe-by-construction output are preferred
  - [x] redaction is defense in depth
  - [x] hash and length modes are not declassification
  - [x] recursive non-placeholder mode selection is boundary-wide: the same mode applies to every branch selected during one `redactJsonLike()` call
  - [x] heterogeneous boundaries use omission or `Placeholder` unless owner policy explicitly permits the same metadata disclosure for every potentially selected branch
  - [x] package has no config, disable switch, logger, runtime `ContextStore`/`ContextAccessorInterface`, UoW, or reset dependency; `RedactionContext` remains an explicit immutable method argument
  - [x] points to `docs/ssot/sensitive-data-redaction.md` for canonical policy

Module and provider:
- [x] `packages/platform/redaction/src/Module/RedactionModule.php`
  - [x] final class
  - [x] public typed constants:
    - [x] `public const string MODULE_ID = 'platform.redaction'`
    - [x] `public const string PACKAGE_ID = 'platform/redaction'`
    - [x] `public const string COMPOSER_PACKAGE = 'coretsia/platform-redaction'`
    - [x] `public const string KIND = 'runtime'`
  - [x] exact instance methods:
    - [x] `public function id(): string`
    - [x] `public function packageId(): string`
    - [x] `public function composerPackage(): string`
    - [x] `public function kind(): string`
    - [x] `public function providers(): array`
  - [x] `providers()` declares `@return list<class-string<ServiceProviderInterface>>`
  - [x] `providers()` returns only `RedactionServiceProvider::class`
  - [x] no `CONFIG_ROOT`
  - [x] no `configRoot()`
  - [x] no config reads, service resolution, filesystem access, or runtime work

- [x] `packages/platform/redaction/src/Provider/RedactionServiceProvider.php`
  - [x] final class
  - [x] implements `ServiceProviderInterface`
  - [x] implements `ContainerDefinitionProviderInterface`
  - [x] `register()` calls `assertDefinitionProviderRegistrationAllowed()`
  - [x] `register()` delegates through `registerDefinitionProvider($this)`
  - [x] `define()` is the single wiring source
  - [x] declarative constructor dependencies use the existing Foundation `ContainerValueReference::service()` primitive; the package introduces no second service-reference representation
  - [x] definitions are declarative and contain no closures or runtime objects
  - [x] no config root is read
  - [x] no tags are introduced

Implementation:
- [x] `packages/platform/redaction/src/Redaction/DefaultSensitiveDataRedactor.php`
  - [x] final class
  - [x] implements the exact `SensitiveDataRedactorInterface`
  - [x] constructor receives exactly:
    - [x] `SensitiveKeyClassifier`
    - [x] `SensitiveValueClassifier`
    - [x] `StableRedactionHasher`
  - [x] `redactValue()`:
    - [x] validates the input string through `JsonLikeNormalizer::normalize()` using the same fixed `JsonLikeNormalizationLimits(32, 10000, 65536, 1048576)`
    - [x] the normalized result remains a string byte-identical to the input
    - [x] individual-string limit failure maps to `input-limit-exceeded`
    - [x] no package-local direct-string size limiter or duplicate `strlen($value) > 65536` guard is introduced
    - [x] `Placeholder` exposes neither length nor hash
    - [x] `Length` uses byte-oriented `strlen`
    - [x] `Hash` delegates to `StableRedactionHasher::hashString()` using the exact input string bytes
    - [x] `HashAndLength` exposes both byte length and `hashString()` over the same exact input string bytes
  - [x] `redactJsonLike()` input stage:
    - [x] normalizes input through `JsonLikeNormalizer::normalize()`
    - [x] uses one immutable `JsonLikeNormalizationLimits(32, 10000, 65536, 1048576)`
    - [x] depth, node, individual-string-byte, and aggregate-string-byte semantics are exactly the Foundation normalizer semantics
    - [x] structural resource accounting remains Foundation-owned; the redactor introduces no parallel depth, node, string, or aggregate-byte counters
    - [x] forbidden input type or structurally invalid input maps to `input-invalid`
    - [x] input depth/node/individual-string/aggregate-string limit violation maps to `input-limit-exceeded`
    - [x] semantic traversal receives only the normalized json-like value returned by Foundation
    - [x] semantic traversal MUST NOT repeat Foundation-owned forbidden-type, map-key-type, depth, node, individual-string-byte, or aggregate-string-byte validation
  - [x] complete sensitive branch summary materialization:
    - [x] `Placeholder` replaces the complete branch without materializing branch-summary bytes, calculating a disclosed summary length, hashing, or invoking `StableJsonEncoder` for branch-summary materialization
    - [x] `Length|Hash|HashAndLength` materialize canonical branch bytes exactly once
    - [x] a string branch uses its exact string bytes as branch-summary bytes
    - [x] a non-string branch uses `StableJsonEncoder::encodeStable()`
    - [x] branch-summary materialization relies on the Foundation-owned `StableJsonEncoder` contract that stable JSON ends with one encoder-owned final LF
    - [x] exactly that encoder-owned final LF is removed from non-string branch-summary bytes
    - [x] the redactor MUST NOT introduce a second final-LF validation or framing policy
    - [x] `Length` counts branch-summary bytes only; the hash representation discriminator is not included in disclosed length
    - [x] a string branch hashes through `StableRedactionHasher::hashString()`
    - [x] a non-string branch hashes through `StableRedactionHasher::hashJsonLike()`
    - [x] `HashAndLength` reuses the same branch-summary bytes for its length and hash inputs; only the hasher-owned representation discriminator differs between string and non-string domains
    - [x] encoder failure while representing an input branch maps to `input-invalid`
  - [x] traversal:
    - [x] after complete Foundation input normalization succeeds, validate each structural map key reached by semantic traversal against the redaction-specific control-byte policy immediately before classification
    - [x] keys nested only inside a complete branch already selected for replacement are not traversed solely for this redaction-specific policy
    - [x] within input that already passed Foundation type and resource validation, a reached map key containing an ASCII C0 control byte or DEL (`0x7F`) fails with `input-invalid`
    - [x] Foundation depth, node, individual-string-byte, aggregate-string-byte, and map-key-type failures retain precedence over this redaction-specific control-byte check
    - [x] classify the original structural map key through `SensitiveKeyClassifier` first
    - [x] a sensitive structural key replaces its complete associated branch with one `RedactedValue::toArray()`
    - [x] when `SensitiveKeyClassifier::classify()` returns `null`, classify the original key through `SensitiveValueClassifier` before semantic traversal of its associated normalized value begins
    - [x] any non-null value-classifier result for such a map key fails with `sensitive-map-key`
    - [x] only a map key unclassified by both classifiers recurses into its associated value
    - [x] string leaves are then classified through `SensitiveValueClassifier`
    - [x] null, bool, and int leaves pass through unchanged unless their owning key is sensitive
    - [x] list order is preserved
    - [x] map keys are preserved and maps remain recursively `strcmp` sorted
    - [x] unsupported/non-json-like input is never coerced, reflected, inspected for object state, or passed through `__toString()`; Foundation rejects it before semantic traversal
    - [x] normalized string values and map keys are inspected only by the documented key/value classifiers
    - [x] normalized non-string sensitive branches are serialized only through `StableJsonEncoder::encodeStable()` for the documented `Length|Hash|HashAndLength` branch-summary materialization; no other input serialization occurs
    - [x] JSON, URLs, base64, JWT payloads, SQL, and provider payloads are not decoded or semantically parsed
  - [x] output stage:
    - [x] normalizes the completed result with the same fixed limits
    - [x] fixed limits are private class constants and are not constructor parameters or config values
    - [x] output depth/node/individual-string/aggregate-string limit violation maps to `output-invalid`
    - [x] returns the normalized value
    - [x] no whole-result `StableJsonEncoder` pass is performed; stable JSON serialization remains consumer-owned
  - [x] failure handling:
    - [x] returns neither unchanged input nor a partial result
    - [x] a `Coretsia\Contracts\Security\Exception\RedactionException` produced by an explicit stage mapping propagates with its original allowlisted reason and MUST NOT be remapped to `internal-failure`
    - [x] every other unexpected Throwable becomes safe `internal-failure`
  - [x] stateless; never retains input, result, current path, classifier decision, or previous failure

- [x] `packages/platform/redaction/src/Redaction/SensitiveKeyClassifier.php`
  - [x] final class
  - [x] stateless
  - [x] package-internal implementation service; documented `@internal`
  - [x] package-internal API:
    - [x] `public function classify(string $key): ?RedactionKind`
  - [x] classification uses a temporary canonical key:
    - [x] ASCII lowercase only
    - [x] removes ASCII `-`, `_`, `.`, and space separators
    - [x] no locale-sensitive case conversion
    - [x] no Unicode normalization
  - [x] original key is never modified
  - [x] uses only the immutable exact alias table defined in `docs/ssot/sensitive-data-redaction.md`
  - [x] supported groups are exactly:
    - [x] `secret-reference`
    - [x] `secret`
    - [x] `credential`
    - [x] `authorization`
    - [x] `cookie`
    - [x] `session-id`
    - [x] `token`
    - [x] `payload`
    - [x] `sql`
    - [x] `pii`
    - [x] `env-value`
    - [x] `local-path`
  - [x] unknown keys return `null`
  - [x] no config, env, mutable registry, learned state, or runtime regex loading

- [x] `packages/platform/redaction/src/Redaction/SensitiveValueClassifier.php`
  - [x] final class
  - [x] stateless
  - [x] package-internal implementation service; documented `@internal`
  - [x] package-internal API:
    - [x] `public function classify(string $value): ?RedactionKind`
  - [x] exact precedence:
    - [x] `authorization`
    - [x] `cookie`
    - [x] `credential`
    - [x] `token`
    - [x] `local-path`
    - [x] `pii`
  - [x] unkeyed values are never automatically classified as:
    - [x] `secret`
    - [x] `secret-reference`
    - [x] `session-id`
    - [x] `payload`
    - [x] `sql`
    - [x] `env-value`
  - [x] those kinds require either a sensitive structural key or explicit `redactValue()` owner classification
  - [x] baseline high-confidence recognition covers exactly:
    - [x] complete direct `Bearer` authorization values
    - [x] complete non-empty `Authorization:` or `Proxy-Authorization:` header-like values regardless of the authorization scheme
    - [x] complete `Cookie:` or `Set-Cookie:` header-like values
    - [x] credential-bearing URI values matching the exact SSoT grammar
    - [x] JWT-shaped three-segment tokens matching the exact conservative SSoT grammar
    - [x] AWS access-key-shaped `AKIA|ASIA` values
    - [x] prefixed token values beginning exactly with `sk_|tok_` followed by `8..512` ASCII characters from `[A-Za-z0-9_-]`
    - [x] Windows drive/UNC and `file://` absolute local-path forms defined by the SSoT
    - [x] email-address-shaped values
  - [x] unclassified values return `null`
  - [x] MUST NOT use entropy scoring or classify every long string as a token
  - [x] MUST NOT classify arbitrary numeric strings as phone numbers
  - [x] MUST NOT perform network calls, read config/env, decode payloads, or load mutable patterns
  - [x] no locale-dependent classification

- [x] `packages/platform/redaction/src/Redaction/StableRedactionHasher.php`
  - [x] final class
  - [x] stateless
  - [x] package-internal implementation service; documented `@internal`
  - [x] package-internal API:
    - [x] `public function hashString(string $bytes, RedactionKind $kind, RedactionContext $context): string`
    - [x] `public function hashJsonLike(string $bytes, RedactionKind $kind, RedactionContext $context): string`
  - [x] both public package-internal methods delegate to one private SHA-256 implementation; no duplicate hashing algorithm exists
  - [x] exact representation discriminators:
    - [x] `string`
    - [x] `json-like`
  - [x] SHA-256 input is exactly:
    - [x] `"coretsia.redaction@1\0" . $context->scope() . "\0" . $kind->value . "\0" . $representation . "\0" . $bytes`
  - [x] `hashString()` uses representation `string`
  - [x] `hashJsonLike()` uses representation `json-like`
  - [x] output is exactly `sha256:` followed by 64 lowercase hexadecimal characters
  - [x] same scope, kind, representation, and bytes always produce the same hash
  - [x] changing scope, kind, or representation changes the hash domain
  - [x] no salt, timestamp, hostname, process id, random bytes, env value, or machine-specific input participates
  - [x] hashing MUST NOT be documented as encryption or proof that low-entropy input is non-sensitive

Docs:
- [x] `docs/adr/ADR-0010-sensitive-data-redaction-boundary.md`
  - [x] records one contracts port plus one default platform implementation
  - [x] records that cross-package consumers depend on `SensitiveDataRedactorInterface`; classifiers and hashing helpers remain package-internal implementation services rather than extension points
  - [x] records config-free, stateless, fail-closed policy
  - [x] records placeholder as the default disclosure mode
  - [x] records explicit owner selection for length/hash summaries
  - [x] records domain-separated SHA-256
  - [x] records that redaction does not replace safe-by-construction producer shapes
  - [x] records that lower-layer Core boundary-specific validation, rejection, omission, and safe derivation remain owner-local and MUST NOT acquire an upward dependency on `platform/redaction`
  - [x] records reuse of Foundation json-like normalization/resource-budget primitives and rejects a package-local structural normalizer or parallel resource-budget walker
  - [x] records the optional Foundation `maxTotalStringBytes` extension as a domain-neutral structural resource-budget primitive with backward-compatible `null` semantics; `platform/redaction` MUST NOT own a parallel aggregate-byte counter
  - [x] records reuse of the Foundation stable-JSON framing contract only for non-string sensitive-branch summary materialization; whole-result stable JSON serialization remains consumer-owned and no final whole-result encoding pass is introduced
  - [x] records `platform.redaction` as the single owner of shared generic semantic redaction; future `platform/security` remains a distinct security capability and MUST NOT introduce a competing generic redaction engine
  - [x] rejects:
    - [x] duplicate generic package-local redaction engines in consumers that are allowed to depend on the shared port
    - [x] config-driven classifiers
    - [x] mutable policy registries
    - [x] raw/debug/passthrough modes
    - [x] redactor-owned observability
    - [x] semantic payload parsing

- [x] `docs/ssot/sensitive-data-redaction.md`
  - [x] exact public contract signatures and immutable result shapes
  - [x] exact contracts-level failure type `Coretsia\Contracts\Security\Exception\RedactionException`
  - [x] exact error code, reason allowlist, private construction, and named constructors
  - [x] exact `RedactionKind` and `RedactionMode` values
  - [x] exact `RedactionContext` scope syntax and bounds
  - [x] exact `RedactedValue::toArray()` shape and mode invariants
  - [x] exact key canonicalization procedure
  - [x] exact immutable key alias table:
    - [x] `secret-reference = secretref|secretreference|keyref`
    - [x] `secret = secret|secrets|secretvalue|password|passwords|passwd|pwd|clientsecret|privatekey|privatekeys|secretkey`
    - [x] `credential = credential|credentials|dsn|connectionstring`
    - [x] `authorization = authorization|authorizationdata|authorizationheader|auth|authdata|proxyauthorization|proxyauthorizationheader`
    - [x] `cookie = cookie|cookies|setcookie`
    - [x] `session-id = session|sessionid|sessionidentifier|sessionidentifiers`
    - [x] `token = token|tokens|accesstoken|refreshtoken|idtoken|bearertoken|apikey|xapikey|accesskey|csrf|csrftoken|xsrf|xsrftoken`
    - [x] `payload = payload|rawpayload|body|rawbody|header|headers|rawheader|rawheaders|query|rawquery|querystring|requestheader|requestheaders|rawrequestheader|rawrequestheaders|responseheader|responseheaders|rawresponseheader|rawresponseheaders|requestbody|rawrequestbody|requestpayload|rawrequestpayload|responsebody|rawresponsebody|responsepayload|rawresponsepayload|providerpayload|rawproviderpayload`
    - [x] `sql = sql|rawsql|sqlquery|rawsqlquery|sqlbindings|sqlstatement`
    - [x] `pii = email|emailaddress|phone|phonenumber|username|firstname|lastname|fullname|dateofbirth|birthdate|dob`
    - [x] `env-value = envvalue|rawenvvalue`
    - [x] `local-path = localpath|filepath|absolutepath|workingdirectory|cwd`
  - [x] ambiguous structural `query|rawquery|querystring` channels are classified as opaque `payload`, not `sql`
  - [x] SQL classification is structural or owner-explicit only: an explicitly SQL-shaped structural key such as `sql|rawsql|sqlquery|rawsqlquery|sqlbindings|sqlstatement` selects `RedactionKind::Sql`, and known-sensitive scalar SQL uses explicit `redactValue(..., RedactionKind::Sql, ...)`
  - [x] generic `statement` has no automatic shared kind because the structural key alone does not establish SQL semantics; SQL statement branches use `sql_statement` or explicit owner classification
  - [x] generic `bindings` has no automatic shared kind because Core also uses that vocabulary for non-SQL DI/container/config semantics; SQL binding branches use an explicit SQL-shaped key such as `sql_bindings` or remain subject to their owner-defined safe-by-construction policy
  - [x] `SensitiveValueClassifier` MUST NOT infer `RedactionKind::Sql` from unkeyed string content; classifier non-match is not declassification
  - [x] raw header collections are classified as complete `payload` branches; nested traversal is not relied upon to discover authorization, cookie, or other sensitive header values
  - [x] owner-specific forbidden diagnostic channels MUST NOT be imported mechanically from `ErrorDescriptor`, Kernel UoW, observability, or another lower-layer denylist into the shared classifier vocabulary
  - [x] a lower-layer key being forbidden does not by itself establish a canonical `RedactionKind`
  - [x] `authidentifier|authidentifiers|userid|tenantid|requestid|correlationid|customer|customerdata|privatecustomerdata` have no automatic shared kind solely because an owner-specific sink policy forbids them
  - [x] generic `address|path|rawpath|directory` keys have no automatic shared kind because the key alone does not establish PII or local-filesystem semantics
  - [x] generic `env|environment|dotenv` keys have no automatic shared kind because the key alone does not establish a raw environment value
  - [x] existing Core source/provenance vocabulary and safe environment-variable-name metadata MUST NOT be reclassified as `EnvValue` solely from an `env`, `environment`, or `dotenv` structural key
  - [x] `EnvValue` structural classification requires the explicit canonical aliases `envvalue|rawenvvalue`; an owner-known raw env value may always use explicit `redactValue(..., RedactionKind::EnvValue, ...)`
  - [x] when such a value requires redaction, its owner uses explicit `redactValue()` with `RedactionKind::Unknown` or another semantically correct kind; destination-boundary rejection remains authoritative
  - [x] exact value-classifier precedence and deterministic pattern definitions
  - [x] every value pattern matches the complete classification candidate
  - [x] for every kind except `local-path`, the classification candidate is the exact input string
  - [x] `local-path` alone may use the documented temporary candidate with leading ASCII space/tab bytes removed
  - [x] classifier syntax tokens, separators, case folding, and explicit character classes are ASCII-defined
  - [x] opaque suffix or remainder bytes are not decoded, Unicode-normalized, or semantically interpreted and are governed only by their grammar-specific byte exclusions
  - [x] exact value grammars:
    - [x] authorization:
      - [x] direct form:
        - [x] ASCII case-insensitive `Bearer`
        - [x] followed by one or more ASCII space/tab bytes
        - [x] followed by a non-empty value containing no CR/LF
      - [x] an unkeyed direct `Basic ...` string is not automatically classified as authorization because `Basic` is ambiguous ordinary text
      - [x] Basic authorization remains classified through the complete `Authorization:` / `Proxy-Authorization:` header-like form, an authorization structural key, or explicit owner classification through `redactValue(..., RedactionKind::Authorization, ...)`
      - [x] header-like form:
        - [x] ASCII case-insensitive `Authorization:|Proxy-Authorization:`
        - [x] followed by optional ASCII space/tab bytes
        - [x] followed by a non-empty value containing no CR/LF
        - [x] the header-like form does not parse or restrict the authorization scheme
    - [x] cookie:
      - [x] ASCII case-insensitive `Cookie:|Set-Cookie:`
      - [x] followed by optional ASCII space/tab bytes
      - [x] followed by a non-empty value containing no CR/LF
    - [x] credential-bearing URI:
      - [x] the complete candidate contains only visible ASCII bytes `0x21..0x7E`
      - [x] ASCII scheme matches `[A-Za-z][A-Za-z0-9+.-]*`
      - [x] scheme is followed by `://`
      - [x] userinfo ends at the first `@` occurring before any `/`, `?`, or `#`
      - [x] the first `:` inside userinfo separates a non-empty user component from a non-empty password component
      - [x] the user component contains no `:`, `@`, `/`, `?`, or `#`
      - [x] the password component contains no `@`, `/`, `?`, or `#`; additional `:` bytes are allowed
      - [x] a non-empty authority component follows `@`
      - [x] optional path, query, or fragment bytes may follow the authority
      - [x] no percent-decoding, URI parsing, hostname validation, or credential decoding is performed
    - [x] JWT:
      - [x] exactly three non-empty segments separated by exactly two `.`
      - [x] the first segment begins exactly with ASCII `eyJ`
      - [x] every segment contains only ASCII `[A-Za-z0-9_-]`
      - [x] `=` padding is not accepted
      - [x] the complete input is matched; segments are not base64url-decoded or JSON-decoded
      - [x] the `eyJ` requirement is a conservative high-confidence discriminator only and MUST NOT be treated as complete JWT validation
    - [x] AWS access key:
      - [x] exact prefix `AKIA|ASIA`
      - [x] followed by exactly 16 uppercase ASCII alphanumeric characters
    - [x] prefixed token:
      - [x] exact prefix `sk_|tok_`
      - [x] followed by `8..512` ASCII `[A-Za-z0-9_-]` characters
      - [x] generic `token_...` values are not automatically classified from value content alone; an owner-known token uses a token structural key or explicit `redactValue(..., RedactionKind::Token, ...)`
    - [x] local path:
      - [x] classification uses a temporary candidate with leading ASCII space/tab bytes removed; the original value bytes are never modified
      - [x] UNC path beginning `\\`
      - [x] Windows drive path beginning `[A-Za-z]:\` or `[A-Za-z]:/`
      - [x] ASCII case-insensitive `file://` absolute-path form where `file://` is followed by `/` or by a non-empty authority followed by `/` or `\`
      - [x] a generic single-backslash-rooted value beginning `\` is not automatically classified as `local-path`; it is ambiguous with PHP FQCN/class-like values
      - [x] a generic forward-slash-rooted value beginning `/` is not automatically classified as `local-path`; it is ambiguous with safe route templates and transport paths
      - [x] an actual POSIX or single-backslash Windows rooted local filesystem path requires an explicit local-path structural key or explicit owner classification through `redactValue(..., RedactionKind::LocalPath, ...)`
    - [x] email:
      - [x] exactly one `@`
      - [x] local part is non-empty and contains only ASCII `[A-Za-z0-9.!#$%&'*+/=?^_{}|~-]`
      - [x] local part does not begin or end with `.`
      - [x] local part contains no consecutive `..`
      - [x] domain contains at least two non-empty labels separated by `.`
      - [x] every domain label matches `[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?`
      - [x] no ASCII whitespace, control byte, Unicode byte sequence, or trailing `.` is accepted
  - [x] near-miss strings remain unclassified
  - [x] exact key-first recursive traversal algorithm
  - [x] Foundation input normalization, structural type rejection, and resource-limit validation complete before semantic key/value traversal begins
  - [x] after Foundation normalization succeeds, redaction-specific C0/DEL validation runs immediately before classification for each map key reached by semantic traversal; keys nested only inside a complete branch already selected for replacement are not traversed solely for this policy, and any earlier Foundation failure retains its canonical reason precedence
  - [x] a sensitive structural key MUST NOT bypass Foundation input validity or resource-limit enforcement for its associated branch
  - [x] exact failure policy for a map key unclassified by `SensitiveKeyClassifier` but classified by `SensitiveValueClassifier`
  - [x] exact byte-length and stable JSON branch-summary rules
  - [x] `Placeholder` performs no branch-byte materialization or hashing
  - [x] `Length|Hash|HashAndLength` materialize canonical branch bytes exactly once
  - [x] stable-JSON framing and final-LF semantics remain Foundation-owned for non-string sensitive-branch summary materialization; redaction strips the canonical encoder-owned final LF from those branch-summary bytes only and performs no whole-result stable JSON validation
  - [x] exact domain-separated SHA-256 input and output
  - [x] exact hash representation domains are `string` and `json-like`
  - [x] direct strings and recursively redacted string branches use the `string` domain
  - [x] recursively redacted non-string branches use the `json-like` domain
  - [x] the representation discriminator participates in hashing only and does not contribute to disclosed `Length`
  - [x] structurally different string/non-string values with identical branch-summary bytes MUST use distinct SHA-256 preimages through the `string|json-like` representation discriminator
  - [x] representation-domain separation does not claim collision-freedom beyond the SHA-256 contract
  - [x] fixed limits:
    - [x] depth `32`
    - [x] nodes `10000`
    - [x] individual string bytes `65536`
    - [x] aggregate string bytes `1048576`
  - [x] exact fail-closed reason mapping
  - [x] exact allowed redacted summary fields
  - [x] explicit prohibition of decoding or semantically parsing payloads
  - [x] omission-first and safe-by-construction producer policy
  - [x] warning that hash and length summaries may remain sensitive metadata
  - [x] `Length|Hash|HashAndLength` are disclosure metadata, not proof of sink safety or non-reconstructability
  - [x] applicable consumer/owner policy remains authoritative for non-placeholder disclosure
  - [x] `Length|Hash|HashAndLength` MUST be selected only when that owner policy explicitly permits the disclosed metadata for every branch to which the selected mode may apply
  - [x] one `redactJsonLike()` call applies one mode uniformly to all classified branches; the redactor performs no per-kind or per-branch automatic downgrade, upgrade, or fallback
  - [x] if any branch that may be selected requires stricter treatment because it is low-entropy, reconstructable, or otherwise sensitive, the recursive call MUST use omission or `Placeholder`, or the owner MUST split the separately governed value into another redaction operation
  - [x] successful redaction does not grant destination-boundary admissibility; stricter consumer schema, semantic-key, path, cardinality, and resource-limit policies remain authoritative

#### Modifies

- [x] `packages/core/foundation/src/Serialization/JsonLikeNormalizationLimits.php`
  - [x] add optional `?int $maxTotalStringBytes = null` as the fourth constructor/public readonly field
  - [x] constructor PHPDoc declares `$maxTotalStringBytes` as `int<1, max>|null`
  - [x] non-null `maxTotalStringBytes` MUST be positive
  - [x] invalid value throws exactly `InvalidArgumentException('json-like-normalization-max-total-string-bytes-invalid')`
  - [x] `null` preserves the existing unbounded-aggregate behavior for existing callers

- [x] `packages/core/foundation/src/Serialization/JsonLikeNormalizer.php`
  - [x] update class/method PHPDoc so the optional limits model explicitly includes aggregate string-byte accounting
  - [x] when `maxTotalStringBytes !== null`, count every string value, including a root string, and every string map key exactly once during the existing recursive traversal
  - [x] existing individual-string byte validation runs before aggregate accounting for that same string value or map key
  - [x] only a string that passed the individual-string byte limit consumes aggregate string bytes
  - [x] existing depth, node, map-key-type, and individual-string failure precedence remains authoritative
  - [x] the existing `normalizeMap()` bulk remaining-node precheck remains before map-key-type validation, sorting, per-key string validation, and aggregate accounting
  - [x] when the direct map-entry count cannot fit the remaining node budget, `REASON_MAX_NODES_EXCEEDED` is thrown at the map path before any key-specific validation or aggregate accounting
  - [x] after the bulk map-node precheck succeeds, all map-key types are validated before sorting or per-key byte accounting
  - [x] for each sorted string map key, existing individual-string validation remains before `consumeNode()`, while aggregate-string accounting occurs only after that map entry successfully consumes its node
  - [x] for list/map values, the parent item node remains consumed before value-level individual-string and aggregate-string accounting, preserving the existing traversal order
  - [x] for a root string, individual-string validation precedes aggregate-string accounting
  - [x] map-key aggregate accounting follows the existing canonical `strcmp` traversal order after map-key type validation and sorting
  - [x] aggregate-limit comparison is overflow-safe and occurs before incrementing the accumulated byte count
  - [x] aggregate overflow throws `JsonLikeNormalizationException::REASON_TOTAL_STRING_BYTES_EXCEEDED`
  - [x] no second aggregate-budget traversal is introduced
  - [x] existing behavior is unchanged when `maxTotalStringBytes === null`

- [x] `packages/core/foundation/src/Serialization/Exception/JsonLikeNormalizationException.php`
  - [x] add `public const string REASON_TOTAL_STRING_BYTES_EXCEEDED = 'json-like-total-string-bytes-exceeded'`
  - [x] include `REASON_TOTAL_STRING_BYTES_EXCEEDED` in the exact internal reason allowlist

- [x] `packages/core/foundation/tests/Contract/JsonLikeNormalizationLimitsContractTest.php`
  - [x] exact public readonly property order becomes `maxDepth`, `maxNodes`, `maxStringBytes`, `maxTotalStringBytes`
  - [x] `maxTotalStringBytes` defaults to `null` when the fourth constructor argument is omitted
  - [x] preserve constructor compatibility when the fourth argument is omitted
  - [x] reject zero and negative aggregate limits with the exact fixed exception

- [x] `packages/core/foundation/tests/Contract/JsonLikeNormalizerContractTest.php`
  - [x] aggregate budget counts every map key and string value exactly once
  - [x] root string participates in the aggregate budget
  - [x] exact aggregate-byte boundary is accepted and boundary plus one byte is rejected
  - [x] when the same string exceeds both `maxStringBytes` and the remaining `maxTotalStringBytes`, the existing `REASON_STRING_BYTES_EXCEEDED` reason wins
  - [x] aggregate accounting does not change existing depth, node, map-key-type, or individual-string failure precedence
  - [x] the existing bulk map-node precheck still wins before map-key-type, key-string, or aggregate validation when the direct map entries cannot fit the remaining node budget
  - [x] when a later map key would exceed both the remaining node budget and aggregate-string budget, `REASON_MAX_NODES_EXCEEDED` wins
  - [x] after the bulk map-node precheck has succeeded, when a later string map key exceeds its individual-string limit, `REASON_STRING_BYTES_EXCEEDED` still wins before that key's per-entry node or aggregate accounting
  - [x] list/map value node-budget failure still occurs before aggregate accounting for that value
  - [x] map aggregate-limit failure is independent from PHP map insertion order
  - [x] aggregate overflow exposes only `json-like-total-string-bytes-exceeded`
  - [x] aggregate overflow reports the same canonical safe path model as every existing normalization-limit failure
  - [x] root-string aggregate overflow reports path `value`
  - [x] aggregate overflow caused by an unsafe map key reports the sanitized `[<key>]` path form and exposes neither the raw key nor its bytes in the exception message or `path()`
  - [x] reason-vocabulary assertion includes exact `REASON_TOTAL_STRING_BYTES_EXCEEDED = 'json-like-total-string-bytes-exceeded'`

- [x] `packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsEnforceRedactionContractTest.php`
  - [x] a canonical `RedactedValue::toArray()` does not make an otherwise forbidden `ErrorDescriptor` extension key admissible
  - [x] a canonical `Placeholder` `RedactedValue::toArray()` is structurally admissible under an otherwise safe owner-defined extension key when all existing `ErrorDescriptor` bounds are satisfied
  - [x] non-placeholder disclosure safety remains producer-owned and is not established merely by `RedactedValue` shape validity

- [x] `packages/core/contracts/README.md`
  - [x] document the Security redaction contracts
  - [x] document exact direct-string and json-like entrypoints
  - [x] clarify that the contracts package owns no implementation or classifier policy

- [x] `packages/core/foundation/README.md`
  - [x] document the optional aggregate string-byte normalization budget

- [x] `docs/ssot/error-descriptor.md`
  - [x] record that canonical `RedactedValue::toArray()` remains recursively subject to the existing semantic-key, absolute-local-path, and resource-budget invariants
  - [x] a forbidden extension key remains forbidden even when its value is a canonical redacted summary
  - [x] the redacted-summary shape is owned by `docs/ssot/sensitive-data-redaction.md`; this document owns only `ErrorDescriptor` admissibility
  - [x] structural acceptance of a canonical redacted summary does not override the existing producer-owned safe-derivation and non-reconstruction requirements
  - [x] `Length|Hash|HashAndLength` are not automatically admissible merely because the summary shape is valid; deterministic SHA-256 MUST NOT be treated as non-reversible for low-entropy input

- [x] `docs/ssot/json-like-runtime-values.md`
  - [x] add optional `maxTotalStringBytes` to the canonical normalization-limit model
  - [x] preserve positive-integer requirements for `maxDepth`, `maxNodes`, and `maxStringBytes`
  - [x] `maxTotalStringBytes` is either `null` or a positive integer; `null` means that no aggregate-string-byte limit is imposed by that limits instance
  - [x] `0` MUST NOT represent an unlimited or disabled aggregate budget
  - [x] define aggregate accounting as byte-oriented `strlen()` accounting over every encountered string map key and string value exactly once, including a root string
  - [x] map keys do not consume nodes but do consume aggregate string bytes
  - [x] map-key aggregate accounting follows canonical `strcmp` traversal order
  - [x] individual-string byte validation precedes aggregate accounting for that same string or map key
  - [x] a string rejected by the individual-string limit does not consume aggregate string bytes
  - [x] adding aggregate accounting does not reorder existing depth, node, map-key-type, or individual-string failure precedence
  - [x] add exact `json-like-total-string-bytes-exceeded` failure reason
  - [x] aggregate-string-byte failures use the existing canonical safe diagnostic-path contract; the new limit introduces no separate path-rendering policy
  - [x] existing normalization behavior remains unchanged when `maxTotalStringBytes === null`

- [x] `docs/ssot/observability-and-errors.md`
  - [x] add the shared redaction mechanism
  - [x] preserve safe-by-construction as the primary requirement
  - [x] forbid relying on late redaction to legitimize unsafe diagnostic shapes
  - [x] preserve the existing prohibition on exporting raw stack traces; `platform/redaction` owns safe exception messages/custom state, not PHP Throwable-trace sanitization
  - [x] preserve existing owner-specific safe derivations such as `hash(value)`, `len(value)`, counts, and stable categories; canonical `RedactedValue` summaries are an additional shared representation for eligible consumers, not a migration requirement for lower-layer Core producers
  - [x] classifier non-match MUST NOT be treated as authorization to emit a raw value to an observability or diagnostic sink
  - [x] point `ErrorDescriptor` redacted-summary admissibility to `docs/ssot/error-descriptor.md` without redefining its field-by-field extension schema
  - [x] reporter payloads and other diagnostic extensions may use canonical redacted summaries only where their owner schema and boundary policy permit them

- [x] `docs/ssot/observability.md`
  - [x] point generic redaction mechanics to `docs/ssot/sensitive-data-redaction.md`; this document retains sink naming, schema, type, cardinality, and allowlist authority
  - [x] canonical redacted summaries are admissible in logs, spans, and span events only where the owner-defined sink schema and boundary policy permit their shape
  - [x] `Hash|HashAndLength` summaries remain subject to the existing deterministic, non-reversible, policy-approved safe-derivation requirement; canonical redaction shape validity does not waive that rule
  - [x] `Length|Hash|HashAndLength` disclosure remains owner-approved metadata and MUST NOT become automatically admissible merely because `platform/redaction` produced it
  - [x] existing span/event attribute allowlists remain authoritative; redaction does not introduce new attribute keys or widen an existing span schema
  - [x] metrics remain allowlist-only
  - [x] canonical redacted summary maps MUST NOT be emitted as metric label values
  - [x] redaction MUST NOT permit arbitrary metric labels
  - [x] raw payloads, headers, cookies, tokens, SQL, env values, provider payloads, and absolute paths remain forbidden span attributes
  - [x] record that `platform/redaction` emits no baseline logs, spans, or metrics

- [x] `docs/ssot/secrets-contracts.md`
  - [x] raw resolved secret values are never diagnostic-safe
  - [x] preserve the existing owner-approved safe-reference policy: a stable secret reference MAY remain directly observable only when its owner already classifies it as safe
  - [x] unsafe or deployment-sensitive secret references require omission or a safe derivation; `SecretReference` is the explicit shared redaction kind for eligible runtime consumers
  - [x] omission is preferred
  - [x] where a resolved secret value or unsafe reference summary is unavoidable, consumers allowed to depend on `platform/redaction` use the shared port with explicit kind and context; lower-layer Core owners retain owner-owned safe derivations and MUST NOT introduce an upward dependency
  - [x] preserve the existing non-reconstruction requirement: `Length|Hash|HashAndLength` MAY be selected only when owner policy explicitly permits disclosure of that metadata for the value class
  - [x] deterministic SHA-256 MUST NOT be treated as non-reversible for low-entropy secrets; when that requirement cannot be established, omission or `Placeholder` remains mandatory

- [x] `docs/ssot/config-and-env.md`
  - [x] raw env values MUST NOT reach diagnostics
  - [x] explain/source traces may expose only safe provenance metadata
  - [x] preserve the distinction between a raw env value and safe source/provenance metadata such as the canonical `env|dotenv` source vocabulary or an owner-approved environment-variable name
  - [x] an `env`, `environment`, or `dotenv` structural key alone MUST NOT imply `RedactionKind::EnvValue`
  - [x] redaction does not permit raw configuration trees or env dumps
  - [x] omission is preferred when a summary is unnecessary

- [x] `docs/ssot/application-dependency-sync.md`
  - [x] register `platform.redaction` in the current committed installation-catalog identity table
  - [x] record `coretsia/platform-redaction` with dependency edge `core.foundation`

- [x] `docs/ssot/INDEX.md`
  - [x] register `docs/ssot/sensitive-data-redaction.md`

- [x] `docs/adr/INDEX.md`
  - [x] register `docs/adr/ADR-0010-sensitive-data-redaction-boundary.md`

- [x] `docs/architecture/PACKAGING.md`
  - [x] align runtime metadata with `docs/ssot/modules-and-manifests.md`: `defaultsConfigPath` is optional
  - [x] when present, `defaultsConfigPath` follows the canonical `config/<root>.php` config-root contract
  - [x] require `config/`, a defaults file, and `config/rules.php` only for runtime packages that own a config root
  - [x] a config-free runtime package MUST NOT be required to create placeholder config files or placeholder `defaultsConfigPath` metadata

- [x] `docs/architecture/STRUCTURE.md`
  - [x] make the canonical runtime-package `config/` subtree conditional on the package owning a config root
  - [x] make module `defaults config path` conditional on `defaultsConfigPath` being declared
  - [x] preserve `config/<root>.php`, `config/rules.php`, and optional deprecations for runtime packages that own config
  - [x] explicitly allow config-free runtime packages without `config/`, `CONFIG_ROOT`, `configRoot()`, or `defaultsConfigPath`
  - [x] align the layered runtime-package template with the current Composer-metadata-driven runtime: remove the stale `(ModuleInterface)` requirement from the package `src/Module/` template
  - [x] package-local runtime module helpers such as `FoundationModule`, `KernelModule`, and `RedactionModule` are not runtime discovery sources and are not required to implement `Coretsia\Contracts\Module\ModuleInterface`
  - [x] runtime module identity, dependency/conflict edges, provider planning metadata, and optional default-config metadata remain authoritative under validated `extra.coretsia`; Kernel discovery MUST NOT instantiate module classes to derive them
  - [x] replace the stale `src/Module/*Module.php exports: id/version/deps/providers; defaults config path` convention with the current package-local helper contract
  - [x] package-local runtime module helpers MAY mirror stable package metadata through `id()`, `packageId()`, `composerPackage()`, `kind()`, and `providers()`; a config-owning package MAY additionally expose its owner-local `configRoot()`
  - [x] package-local runtime module helpers MUST NOT be documented as the source of package version, runtime dependency/conflict edges, `defaultsConfigPath`, or provider-planning metadata; those values remain Composer-metadata-owned
  - [x] keep the separate application/user `ModuleInterface` guidance unchanged
  - [x] register `coretsia/platform-redaction` in the planned full package catalog as the shared generic redaction runtime package
  - [x] keep planned `platform/security` distinct from generic redaction ownership; it MUST NOT define a competing generic redaction engine or mutable classifier registry

- [x] repo-root `composer.json`
  - [x] include `coretsia/platform-redaction: 0.7.x-dev` in workspace `require-dev`
  - [x] add the canonical managed path repository:
    - [x] `type = path`
    - [x] `url = packages/platform/redaction`
    - [x] `options.symlink = true`
    - [x] `options.reference = config`
    - [x] `options.versions.coretsia/platform-redaction = 0.7.x-dev`
    - [x] `coretsia_managed = true`
  - [x] preserve canonical managed-repository ordering

- [x] `docs/architecture/DEPENDENCIES.md`
  - [x] add `platform/redaction` to the canonical direct dependency matrix
  - [x] exact direct dependency row:
    - [x] `platform/redaction` → `core/contracts, core/foundation`
  - [x] preserve canonical `strcmp` package-row ordering
  - [x] keep the matrix aligned with package Composer dependencies and generated deptrac policy

- [x] `packages/core/kernel/resources/packaging/installation-catalog.php`
  - [x] regenerate through canonical `tools/build/installation_catalog.php`
  - [x] include `platform.redaction` → `coretsia/platform-redaction`
  - [x] catalog edge is exactly `requires = [core.foundation]`
  - [x] catalog `conflicts = []`

- [x] `tools/testing/package-index.php`
  - [x] regenerate through canonical `tools/build/package_index.php`

- [x] `tools/testing/deptrac.yaml`
  - [x] regenerate through canonical `tools/build/deptrac_generate.php`
  - [x] include `packages/platform/redaction/src`
  - [x] enforce only the allowed `core/contracts` and `core/foundation` edges

#### Configuration (keys + defaults)

This package introduces no config root and no config files.

The following files MUST NOT exist:

```text
packages/platform/redaction/config/redaction.php
packages/platform/redaction/config/rules.php
```

`RedactionModule` has no `CONFIG_ROOT` and no `configRoot()` method.

The following keys and equivalent aliases are forbidden:

```text
redaction.enabled
redaction.mode
redaction.disable
redaction.policy
redaction.patterns
redaction.hash_algorithm
security.redaction.enabled
foundation.redaction.enabled
cli.redaction.enabled
```

Rules:
- once `platform.redaction` is enabled by module composition, its redaction behavior cannot be disabled or bypassed through config, env, debug/app environment, service availability, or another runtime switch
- the default mode is selected explicitly by `RedactionContext`, not global config
- classifier vocabulary and precedence are SSoT-owned code policy
- no runtime-regex registry is loaded from config
- no env variable alters redaction behavior
- no debug/app environment alters redaction behavior
- future extensibility or custom policy packs require a separate epic

#### Wiring / DI tags (when applicable)

Tags introduced:
- none

`RedactionServiceProvider::define()` contributes in this exact order:

```text
SensitiveKeyClassifier
SensitiveValueClassifier
StableRedactionHasher
DefaultSensitiveDataRedactor
SensitiveDataRedactorInterface alias
```

Exact declarative wiring:

- [x] class service:
  - [x] `Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier`
- [x] class service:
  - [x] `Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier`
- [x] class service:
  - [x] `Coretsia\Platform\Redaction\Redaction\StableRedactionHasher`
- [x] class service:
  - [x] id and class: `Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor`
  - [x] constructor arguments are exact typed declarative service references, in order:
    - [x] `ContainerValueReference::service(SensitiveKeyClassifier::class)`
    - [x] `ContainerValueReference::service(SensitiveValueClassifier::class)`
    - [x] `ContainerValueReference::service(StableRedactionHasher::class)`
  - [x] literal FQCN strings MUST NOT be used in place of `ContainerValueReference::service(...)`
- [x] alias:
  - [x] `Coretsia\Contracts\Security\SensitiveDataRedactorInterface`
  - [x] → `Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor`

Additional rules:

- when `RedactionServiceProvider` is applied for an enabled `platform.redaction` module, exactly one `SensitiveDataRedactorInterface` binding exists
- when the module is not enabled, the provider is not applied and the package contributes no redactor binding
- no factory service is required
- no closures exist in canonical definitions
- no config parameters exist
- no tags exist
- all concrete package service definitions are shared; the `SensitiveDataRedactorInterface` alias remains the canonical non-shared delegation wrapper and resolves the shared `DefaultSensitiveDataRedactor` target
- all registered services are stateless and safe to share
- source registration and declarative definitions are semantically identical

#### Artifacts / outputs (if applicable)

N/A.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [x] Redaction services read no `ContextStore` values.
- [x] Redaction services write no context values.
- [x] Redaction services do not receive `ContextAccessorInterface`.
- [x] `RedactionContext` is an explicit immutable method argument and is unrelated to runtime `ContextStore`.
- [x] Redaction services do not create or participate in a Kernel UoW.
- [x] Redaction services do not receive `KernelRuntimeInterface`.
- [x] `DefaultSensitiveDataRedactor`, both classifiers, and the hasher are stateless.
- [x] No service implements `ResetInterface`.
- [x] No service is tagged `kernel.stateful`.
- [x] No service is tagged `kernel.reset`.
- [x] No static mutable cache, learned classifier state, or previous-input state exists.
- [x] Any future stateful/custom policy capability requires a separate epic.

#### Observability

- [x] No spans are emitted.
- [x] No metrics are emitted.
- [x] No logs are emitted.
- [x] No observability port is injected.
- [x] No logger is injected.
- [x] Redaction input, classification decisions, hashes, lengths, kinds, scopes, failures, and rejected values are not self-observed.
- [x] The caller MAY observe only its own already-safe operation outcome under the caller-owned observability policy.
- [x] A redaction failure throws `RedactionException`; the redactor does not log and does not return a fallback.

#### Errors

- [x] `Coretsia\Contracts\Security\Exception\RedactionException`
  - [x] extends `RuntimeException`
  - [x] constructor is private
  - [x] construction is allowed only through the exact named constructors
  - [x] error code: `CORETSIA_REDACTION_FAILED`
  - [x] `public const string ERROR_CODE = 'CORETSIA_REDACTION_FAILED'`
  - [x] exact public typed reason constants:
    - [x] `public const string REASON_INPUT_INVALID = 'input-invalid'`
    - [x] `public const string REASON_INPUT_LIMIT_EXCEEDED = 'input-limit-exceeded'`
    - [x] `public const string REASON_SENSITIVE_MAP_KEY = 'sensitive-map-key'`
    - [x] `public const string REASON_OUTPUT_INVALID = 'output-invalid'`
    - [x] `public const string REASON_INTERNAL_FAILURE = 'internal-failure'`
  - [x] exact public message:
    - [x] `CORETSIA_REDACTION_FAILED: <reason>`
  - [x] exposes:
    - [x] `errorCode(): string`
    - [x] `reason(): string`
  - [x] allows only:
    - [x] `input-invalid`
    - [x] `input-limit-exceeded`
    - [x] `sensitive-map-key`
    - [x] `output-invalid`
    - [x] `internal-failure`
  - [x] named constructors:
    - [x] `public static function inputInvalid(): self`
    - [x] `public static function inputLimitExceeded(): self`
    - [x] `public static function sensitiveMapKey(): self`
    - [x] `public static function outputInvalid(): self`
    - [x] `public static function internalFailure(): self`
  - [x] does not accept or retain a previous Throwable
  - [x] its message and custom exception state contain no raw value, map key, scope, hash, length, pattern, path, class name, resource id, or payload fragment
  - [x] stack-trace export remains governed by the existing Core error/observability boundary policy; this package introduces no independent Throwable-trace redaction mechanism

Exception mapping:

- input stage:
  - unsupported input type or structurally invalid json-like input → `input-invalid`
  - input normalization depth/node/individual-string/aggregate-string limit violation → `input-limit-exceeded`
  - direct `redactValue()` Foundation individual-string limit violation → `input-limit-exceeded`

- semantic traversal / branch-summary stage:
  - map key unclassified by `SensitiveKeyClassifier` but classified by `SensitiveValueClassifier` → `sensitive-map-key`
  - stable encoding failure while materializing a normalized sensitive branch → `input-invalid`

- output stage:
  - output normalization depth/node/individual-string/aggregate-string limit violation → `output-invalid`

- implementation invariant:
  - any unexpected implementation failure not covered by the explicit stage mappings → `internal-failure`

A `Coretsia\Contracts\Security\Exception\RedactionException` produced by an explicit stage mapping MUST preserve its original allowlisted reason and MUST NOT be remapped to `internal-failure`.

Every other caught Throwable is converted to `internalFailure()` without copying or retaining the original Throwable.

#### Security / Redaction

- [x] Redaction is defense in depth, not source-data authorization.
- [x] Producer-owned safe-by-construction shapes remain mandatory.
- [x] Omission is preferred when no summary is required.
- [x] Placeholder-only is the baseline default.
- [x] Length and hash disclosure require explicit `RedactionContext` mode.
- [x] `redactJsonLike()` applies that selected mode uniformly to every classified branch in the call; no hidden per-kind disclosure policy exists inside the redactor.
- [x] A non-placeholder recursive mode is valid only when owner policy permits that disclosure for every sensitive branch the boundary may contain; otherwise omission or `Placeholder` remains mandatory.
- [x] Hashes and lengths remain potentially sensitive correlation metadata.
- [x] No output mode returns the original value of a branch selected for redaction.
- [x] No failure returns unchanged or partially redacted input.
- [x] No rejected or redacted raw value appears in `RedactionException` messages or custom state, logs, spans, metrics, diagnostics, or provider definitions.
- [x] Throwable stack traces are not a redaction output surface and MUST NOT be exported through consumer diagnostic/output sinks under the existing Core error-boundary policy.

The no-leak guarantee applies when sensitivity is established through:
- explicit owner classification passed to `redactValue()`
- structural-key classification by `SensitiveKeyClassifier`
- string-value classification by `SensitiveValueClassifier`

For every such classified branch, the original classified value or complete classified branch MUST NOT be returned or copied into output.

Classifier non-match is not declassification. The baseline classifiers are intentionally deterministic and non-exhaustive; producer-owned safe-by-construction shapes remain mandatory.

Allowed redacted summary fields are exactly, in byte-order `strcmp` key order:

```text
hash
kind
length
mode
redacted
schemaVersion
```

No timestamp, host, process id, request id, user id, tenant id, path, original field value, preview, prefix, suffix, or partially masked raw value is allowed.

### Canonical fixture matrix (MUST)

Tests use only synthetic values.

Key-classification fixtures:

```text
password             -> secret
secret_ref           -> secret-reference
clientSecret         -> secret
credentials          -> credential
Authorization        -> authorization
proxy-authorization  -> authorization
authorization_header -> authorization
Cookie               -> cookie
session_id           -> session-id
access_token         -> token
tokens               -> token
x-api-key            -> token
request_body         -> payload
raw_payload          -> payload
request_payload      -> payload
provider_payload     -> payload
headers              -> payload
query_string         -> payload
sql                  -> sql
raw_sql              -> sql
sql_query            -> sql
sql_bindings         -> sql
sql_statement        -> sql
email_address        -> pii
env_value            -> env-value
raw_env_value        -> env-value
absolute_path        -> local-path
```

Value-classification fixtures:

```text
Bearer synthetic-token-value
-> authorization

Authorization: Bearer synthetic-token-value
-> authorization

Authorization: Digest synthetic-digest-material
-> authorization

Proxy-Authorization: Basic c3ludGhldGljOnZhbHVl
-> authorization

Cookie: session=synthetic-session
-> cookie

mysql://synthetic-user:synthetic-password@example.test/database
-> credential

eyJhbGciOiJub25lIn0.eyJzdWIiOiJzeW50aGV0aWMifQ.signature
-> token

AKIA1234567890ABCDEF
-> token

tok_synthetic_example
-> token

C:\synthetic\project\.env
-> local-path

\\synthetic-server\share\secret.txt
-> local-path

file:///home/synthetic/project/.env
-> local-path

file://synthetic-server/share/secret.txt
-> local-path

synthetic-user@example.test
-> pii
```

Non-sensitive controls:

```text
\Coretsia\Foundation\ExampleService
Basic plan
Basic configuration
success
handled-error
module-id
artifact-generation
0.7.0
a.b.c
42
true
token_bucket_capacity
token_generation_mode
null
relative/safe-logical-id
```

Required assertions:

- every original string value or complete branch selected for redaction is absent from redacted output
- structural key labels classified by `SensitiveKeyClassifier` remain unchanged while their complete associated branch is replaced
- a map key classified by `SensitiveValueClassifier` is never returned because redaction fails with `sensitive-map-key`
- rejected or redacted raw fixture values are absent from exception messages
- after complete Foundation input normalization succeeds, sensitive structural-key classification wins over recursive semantic traversal of its associated branch
- sensitive structural keys do not bypass Foundation input type or resource-limit validation
- non-sensitive controls remain unchanged
- lists preserve order
- maps are recursively `strcmp` sorted
- repeated calls produce identical normalized PHP shapes; whole-result byte serialization is not owned or required by the redactor
- placeholder mode exposes neither length nor hash
- length mode exposes byte length only
- hash mode exposes domain-separated hash only
- hash-and-length exposes both
- changing scope changes the hash domain
- changing kind changes the hash domain
- changing representation changes the hash domain
- the same scope, kind, representation, and bytes produce the same hash
- raw fixtures do not appear in logs, spans, metrics, or diagnostics because the package emits none

### Tests (MUST)

- Contracts:
  - [x] `packages/core/contracts/tests/Contract/SensitiveDataRedactorInterfaceShapeContractTest.php`
    - [x] exact two public methods
    - [x] exact parameter and return types
    - [x] both methods document `Coretsia\Contracts\Security\Exception\RedactionException`
    - [x] no platform-local exception appears in the interface contract
    - [x] no implementation-package dependency

  - [x] `packages/core/contracts/tests/Contract/RedactionExceptionShapeContractTest.php`
    - [x] exact FQCN belongs to `core/contracts`
    - [x] exact error code is `CORETSIA_REDACTION_FAILED`
    - [x] exact `ERROR_CODE` constant
    - [x] exact five public `REASON_*` constants and values
    - [x] `ERROR_CODE` and every public `REASON_*` constant are typed exactly `string`
    - [x] exact public message is `CORETSIA_REDACTION_FAILED: <reason>`
    - [x] constructor is private
    - [x] every named constructor maps to exactly one allowlisted reason
    - [x] unknown reasons cannot be constructed
    - [x] native exception code remains `0`
    - [x] no previous Throwable is retained
    - [x] no raw fixture value appears in the message

  - [x] `packages/core/contracts/tests/Contract/RedactionContextShapeContractTest.php`
    - [x] exact fields and accessors
    - [x] default placeholder mode
    - [x] scope regex and byte bound
    - [x] invalid syntax, excessive byte length, whitespace, multiline, NUL, ESC, and control bytes throw exactly `redaction-context-scope-invalid`
    - [x] documents that runtime/high-cardinality semantic values are forbidden caller inputs but are not inferred by the value object

  - [x] `packages/core/contracts/tests/Contract/RedactionEnumsContractTest.php`
    - [x] exact `RedactionKind` cases and values
    - [x] exact `RedactionMode` cases and values
    - [x] no raw/disabled mode

  - [x] `packages/core/contracts/tests/Contract/RedactedValueShapeContractTest.php`
    - [x] exact six-key `toArray()` shape
    - [x] recursive json-like compatibility
    - [x] exact mode invariants
    - [x] every invalid mode/length/hash combination throws exactly `redacted-value-shape-invalid`
    - [x] malformed hash and negative-length inputs are absent from exception messages
    - [x] no original value storage

- Package contracts:
  - [x] `packages/platform/redaction/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
    - [x] module and provider are loadable
    - [x] module metadata matches composer metadata
    - [x] module has no config root
    - [x] provider construction has no side effects

  - [x] `packages/platform/redaction/tests/Contract/RedactionModuleComposerMetadataContractTest.php`
    - [x] exact package name, namespace, module id, provider, requires, and conflicts
    - [x] no `defaultsConfigPath`
    - [x] exact direct Composer dependencies

  - [x] `packages/platform/redaction/tests/Contract/RedactionProviderDefinitionsContainNoClosuresContractTest.php`
    - [x] exact service-definition order
    - [x] `DefaultSensitiveDataRedactor` has exactly three typed `service` constructor-reference descriptors in the documented order
    - [x] no constructor dependency is encoded as a literal FQCN string
    - [x] no closures or runtime objects
    - [x] exact interface alias
    - [x] no config parameters or tags

  - [x] `packages/platform/redaction/tests/Contract/RedactionProviderSourceDefinitionsParityTest.php`
    - [x] source-mode registration and declarative definitions resolve the same services
    - [x] applying the provider produces exactly one redactor binding
    - [x] source and declarative application produce the same binding

  - [x] `packages/platform/redaction/tests/Contract/RedactionPackageHasNoConfigSurfaceContractTest.php`
    - [x] no config directory
    - [x] no config root in module metadata
    - [x] no disable or runtime policy keys
    - [x] no env reads

  - [x] `packages/platform/redaction/tests/Contract/RedactionRuntimeHasNoContextObservabilityOrResetDependencyContractTest.php`
    - [x] no ContextStore or ContextAccessor
    - [x] no KernelRuntimeInterface
    - [x] no ResetInterface
    - [x] no reset/stateful tags
    - [x] no logger, tracer, meter, or reporter
    - [x] no stdout/stderr writes

  - [x] `packages/platform/redaction/tests/Contract/RedactionDoesNotExposeRawValuesContractTest.php`
    - [x] complete canonical fixture matrix
    - [x] output contains no original value or complete branch selected for redaction
    - [x] exception messages contain no rejected or redacted raw fixture value
    - [x] preserved structural key labels are not treated as leaked redacted values

  - [x] `packages/platform/redaction/tests/Contract/RedactionOutputIsDeterministicContractTest.php`
    - [x] same input/context produces the same recursively normalized result
    - [x] map order is canonical
    - [x] list order is preserved
    - [x] hash output is stable

- Unit:
  - [x] `packages/platform/redaction/tests/Unit/SensitiveKeyClassifierTest.php`
    - [x] exact canonicalization
    - [x] exact key vocabulary
    - [x] every alias listed in `docs/ssot/sensitive-data-redaction.md` is covered
    - [x] aliases map to exactly the documented `RedactionKind`
    - [x] case and separator variants
    - [x] no locale dependence
    - [x] unknown keys return null
    - [x] generic `bindings` remains unclassified because the shared key alone does not establish SQL semantics
    - [x] `sql_bindings` classifies exactly as `RedactionKind::Sql`
    - [x] generic `statement` remains unclassified because the shared key alone does not establish SQL semantics
    - [x] `sql_statement` classifies exactly as `RedactionKind::Sql`
    - [x] owner-specific forbidden-but-unclassified identifiers remain `null` unless they are present in the canonical shared alias table
    - [x] `auth_identifier`, `user_id`, `tenant_id`, `request_id`, and `correlation_id` are not assigned a shared `RedactionKind` merely because another Core boundary forbids them
    - [x] `address`, `path`, `raw_path`, and `directory` remain unclassified because their structural key alone does not establish PII or local-filesystem semantics
    - [x] `env`, `environment`, and `dotenv` remain unclassified because their structural key alone does not establish a raw environment value
    - [x] `env_value` and `raw_env_value` classify exactly as `RedactionKind::EnvValue`

  - [x] `packages/platform/redaction/tests/Unit/SensitiveValueClassifierTest.php`
    - [x] exact high-confidence patterns
    - [x] covers every SSoT-defined baseline pattern class
    - [x] precedence collisions resolve according to the exact documented order
    - [x] exact precedence
    - [x] non-sensitive controls remain unclassified
    - [x] no entropy or broad long-string heuristic
    - [x] generic `token_...` technical identifiers such as `token_bucket_capacity` and `token_generation_mode` remain unclassified
    - [x] near-miss fixtures for every pattern remain unclassified
    - [x] ordinary text such as `Basic plan` and `Basic configuration` remains unclassified
    - [x] `Authorization: Basic synthetic-value` remains classified as `authorization`
    - [x] forward-slash-rooted route-template values such as `/health`, `/users/{id}`, and `/api/v1/items` remain unclassified
    - [x] generic `/...` classification is not used as a substitute for owner-known POSIX local-path semantics
    - [x] FQCN-shaped values such as `\Coretsia\Foundation\ExampleService` remain unclassified
    - [x] generic single-backslash-rooted classification is not used as a substitute for owner-known Windows local-path semantics
    - [x] credential-bearing URI near-misses with empty user, empty password, missing `@`, missing authority, ASCII whitespace, or a control byte remain unclassified
    - [x] JWT near-misses with an empty segment, extra segment, `=` padding, a non-base64url ASCII byte, or a first segment not beginning with `eyJ` remain unclassified
    - [x] ordinary dotted values such as `0.7.0` and `a.b.c` remain unclassified
    - [x] email near-misses with whitespace, control bytes, consecutive local-part dots, a leading/trailing local-part dot, a leading/trailing domain-label hyphen, a single domain label, or Unicode bytes remain unclassified
    - [x] no automatic unkeyed classification exists for `secret|secret-reference|session-id|payload|sql|env-value`
    - [x] SQL-shaped unkeyed strings remain unclassified; owner-known SQL requires an SQL structural key or explicit `redactValue(..., RedactionKind::Sql, ...)`

  - [x] `packages/platform/redaction/tests/Unit/StableRedactionHasherTest.php`
    - [x] exact domain-separated input
    - [x] exact NUL separators and `coretsia.redaction@1` prefix
    - [x] direct string bytes are hashed unchanged inside the `string` representation domain
    - [x] exact fixed-vector representation-domain separation:
      - [x] scope `cli.output`
      - [x] kind `secret`
      - [x] bytes `null`
      - [x] `string` domain → `sha256:5766c080857ef006b1bd51448aed5fc55727f2e11216fa7961cc2b07fee19151`
      - [x] `json-like` domain → `sha256:7057aa75ab01ec4fb0e07ed6fc8ad0bde295c759a85919c960ef3b92fa28d567`
      - [x] the two fixed-vector digests differ because their canonical preimages differ
    - [x] the test establishes exact representation-domain framing for the fixed vector and MUST NOT be documented as a general SHA-256 collision-freedom guarantee
    - [x] no salt, time, host, process, env, or random input participates
    - [x] exact `sha256:<64-lower-hex>` output
    - [x] scope, kind, and representation-domain separation
    - [x] byte-oriented behavior

  - [x] `packages/platform/redaction/tests/Unit/DefaultSensitiveDataRedactorValueTest.php`
    - [x] explicit string redaction for every kind
    - [x] no raw string is retained or returned

  - [x] `packages/platform/redaction/tests/Unit/DefaultSensitiveDataRedactorJsonLikeTraversalTest.php`
    - [x] key-first branch redaction
    - [x] key-first semantic traversal begins only after complete Foundation input normalization succeeds
    - [x] a sensitive structural key does not hide a forbidden type or input-limit violation inside its associated branch
    - [x] one sensitive key replaces its complete value branch with one redacted summary
    - [x] complete non-string branch summaries use normalized stable JSON without the encoder-owned final LF
    - [x] recursive value classification
    - [x] list preservation
    - [x] canonical map ordering
    - [x] non-sensitive scalar preservation
    - [x] no JSON, URL, base64, JWT payload, SQL, or provider-payload decoding occurs

  - [x] `packages/platform/redaction/tests/Unit/DefaultSensitiveDataRedactorModesTest.php`
    - [x] exact placeholder, length, hash, and hash-and-length shapes
    - [x] one `RedactionContext` mode is applied uniformly to every branch selected during one `redactJsonLike()` call
    - [x] no classifier kind triggers an implicit mode downgrade, upgrade, or fallback
    - [x] placeholder mode does not encode or hash a complete sensitive branch
    - [x] a synthetic non-string sensitive branch containing malformed UTF-8 is safely replaced in placeholder mode
    - [x] the same branch fails with `input-invalid` when length or hash requires stable JSON byte materialization
    - [x] hash-and-length materializes and hashes one canonical byte representation
    - [x] under the same scope and kind, sensitive string `"null"` and sensitive scalar `null` produce different hashes
    - [x] representation separation does not alter disclosed branch-summary byte length

  - [x] `packages/platform/redaction/tests/Unit/DefaultSensitiveDataRedactorRejectsSensitiveMapKeysTest.php`
    - [x] an unclassified map key that `SensitiveValueClassifier` classifies fails with `sensitive-map-key` before semantic traversal of its associated normalized value begins
    - [x] an `Authorization: Bearer ...` header-like string used as a map key fails with `sensitive-map-key` before semantic traversal of its associated normalized value begins
    - [x] an `Authorization: Digest ...` header-like string used as a map key fails with `sensitive-map-key` before semantic traversal of its associated normalized value begins
    - [x] key value is absent from exception diagnostics

  - [x] `packages/platform/redaction/tests/Unit/DefaultSensitiveDataRedactorLimitsTest.php`
    - [x] exact depth, node, individual-string-byte, and aggregate-string-byte limits
    - [x] limit failures expose only the stable reason
    - [x] direct `redactValue()` delegates the `65536` individual-string-byte limit to `JsonLikeNormalizer` and maps the Foundation limit failure to `input-limit-exceeded`
    - [x] direct-string limit enforcement contains no second package-local byte-limit guard
    - [x] output expansion beyond the fixed output budget fails with `output-invalid`

  - [x] `packages/platform/redaction/tests/Unit/DefaultSensitiveDataRedactorFailureStageMappingTest.php`
    - [x] unsupported input type → `input-invalid`
    - [x] sensitive branch-summary stable encoding failure → `input-invalid`
    - [x] input normalization limit → `input-limit-exceeded`
    - [x] map key unclassified by `SensitiveKeyClassifier` but classified by `SensitiveValueClassifier` → `sensitive-map-key`
    - [x] output normalization resource-limit failure → `output-invalid`
    - [x] every explicitly mapped `RedactionException` preserves its documented allowlisted reason and is not remapped to `internal-failure`
    - [x] no failure returns unchanged input or a partial result

  - [x] `packages/platform/redaction/tests/Unit/DefaultSensitiveDataRedactorFailsClosedTest.php`
    - [x] Foundation input normalization rejects floats, objects, closures, resources, and non-string map keys before semantic traversal; the redactor maps those failures to `input-invalid`
    - [x] no package-local duplicate forbidden-type or map-key-type validator exists
    - [x] a Foundation-valid, within-budget map key reached by semantic traversal and containing an ASCII C0 control byte or DEL (`0x7F`) fails with `input-invalid`
    - [x] a C0/DEL map key nested only inside a complete branch already selected for replacement is not traversed solely for this policy
    - [x] when the same candidate map key causes a Foundation resource-limit failure before semantic traversal, the canonical Foundation limit mapping to `input-limit-exceeded` wins over the later C0/DEL policy
    - [x] rejected map keys are absent from exception messages
    - [x] returns no unchanged input or partial result
    - [x] preserves no previous Throwable

- Integration:
  - [x] `packages/platform/redaction/tests/Integration/RedactionServiceProviderWiresDefaultRedactorTest.php`
    - [x] canonical source container resolves `SensitiveDataRedactorInterface`
    - [x] resolved service is `DefaultSensitiveDataRedactor`
    - [x] classifiers and hasher are injected in exact order
    - [x] repeated resolution returns the same shared stateless service
    - [x] redaction works without config, `ContextStore`, `ContextAccessorInterface`, Kernel runtime, or observability services; callers still pass the explicit immutable `RedactionContext` method argument

  - [x] `packages/platform/redaction/tests/Integration/RedactionProviderRequiresExplicitApplicationTest.php`
    - [x] package/autoload presence alone does not mutate a fresh Foundation `ContainerBuilder`
    - [x] constructing `RedactionModule` alone does not mutate a fresh Foundation `ContainerBuilder`
    - [x] explicitly applying the provider declared by `RedactionModule::providers()` contributes exactly one `SensitiveDataRedactorInterface` binding
    - [x] no config, debug, app environment, or Composer-presence side effect applies the provider automatically
    - [x] the test does not emulate Kernel `ModulePlan` or provider-plan resolution

- Gates / architecture:
  - [x] package index regenerated and green
  - [x] canonical direct dependency matrix includes `platform/redaction` and matches Composer/deptrac edges
  - [x] installation catalog regenerated and green
  - [x] deptrac generated and green
  - [x] package compliance green for the canonical config-free runtime-package shape
  - [x] package scaffold check green and does not require or synthesize `config/` when `defaultsConfigPath` is absent
  - [x] `sync:check` green
  - [x] `release-line:workspace:check` and `release-line:public-constraints:check` green
  - [x] `package-publish-safety:gate` green
  - [x] package PHPUnit configuration gate green
  - [x] contracts-only ports gate green
  - [x] ECS and PHPStan green

### DoD (MUST)

- [x] `SensitiveDataRedactorInterface` exposes exactly `redactValue()` and `redactJsonLike()`.
- [x] The public redaction failure type belongs to `core/contracts`; consumers need no dependency on the concrete platform implementation to catch redaction failures.
- [x] `RedactionKind` and `RedactionMode` contain exactly the canonical enum values.
- [x] `RedactionContext` has exact bounded scope and mode semantics.
- [x] `RedactedValue` has the exact deterministic six-key exported shape.
- [x] No mode or config option returns the original value of a branch selected for redaction.
- [x] Placeholder is the default mode.
- [x] Length is byte length.
- [x] Hashing uses the exact domain-separated SHA-256 contract.
- [x] After complete Foundation input normalization succeeds, recursive semantic traversal uses exact key-first classification precedence; sensitive structural keys do not bypass input type or resource-limit validation.
- [x] Lists preserve order and maps are recursively `strcmp` sorted.
- [x] Direct-string and recursive json-like resource limits are enforced through the shared Foundation normalization primitive; `platform/redaction` contains no parallel byte-limit guard.
- [x] Invalid input and internal failures fail closed.
- [x] No complete or partial original value is returned after failure.
- [x] Map keys unclassified by `SensitiveKeyClassifier` but classified by `SensitiveValueClassifier` fail deterministically with `sensitive-map-key`.
- [x] Every `RedactionException` exposes only `CORETSIA_REDACTION_FAILED` and an allowlisted reason.
- [x] `RedactionContext` invariant violations expose only `InvalidArgumentException('redaction-context-scope-invalid')`.
- [x] `RedactedValue` invariant violations expose only `InvalidArgumentException('redacted-value-shape-invalid')`.
- [x] No exception message contains rejected constructor input, redaction input, field keys, hashes, paths, payload fragments, or previous Throwable messages.
- [x] Concrete runtime services are stateless and shared; the `SensitiveDataRedactorInterface` alias is a canonical non-shared delegation wrapper and preserves the lifecycle of its shared `DefaultSensitiveDataRedactor` target.
- [x] No ContextStore, UoW, reset, logging, tracing, metrics, or reporter dependency exists.
- [x] No config root, config files, disable toggle, runtime pattern registry, or env-controlled behavior exists.
- [x] `RedactionModule` and Composer metadata match exactly.
- [x] Composer requires only PHP, `core/contracts`, and `core/foundation`.
- [x] Package `LICENSE` and `NOTICE` are byte-identical to the canonical monorepo-root legal files.
- [x] Explicit application of the provider declared by `RedactionModule` contributes exactly one `SensitiveDataRedactorInterface` binding to the default implementation.
- [x] Package presence or `RedactionModule` construction alone contributes no provider or redactor binding; enabled/disabled module-plan semantics remain Kernel-owned.
- [x] Provider source registration and declarative definitions are semantically identical.
- [x] Cross-package consumers use `SensitiveDataRedactorInterface`; classifiers and `StableRedactionHasher` remain package-internal implementation details.
- [x] Redaction does not replace producer-owned safe-by-construction diagnostic shapes.
- [x] Classifier non-match is not declassification and MUST NOT authorize transporting arbitrary raw diagnostic/output data.
- [x] Existing lower-layer Core boundary-specific validation, rejection, omission, hashing, and safe-derivation guards remain owner-local and are not rewritten to depend on `platform/redaction`.
- [x] Foundation-owned json-like type validation, map-key-type validation, normalization, and structural resource accounting are reused from `core/foundation`; `platform/redaction` contains no parallel validator, normalizer, or resource-budget walker for those Foundation-owned concerns.
- [x] The post-normalization C0/DEL check for map keys reached by semantic traversal before classification is a redaction-specific output-safety policy and MUST NOT become a second json-like structural-validity, resource-budget, or whole-input pre-scan model.
- [x] `StableJsonEncoder` is used only when canonical bytes of a non-string sensitive branch are required by `Length|Hash|HashAndLength`; whole-result serialization remains consumer-owned.
- [x] Canonical redacted summaries do not bypass stricter consumer-boundary policy, including `ErrorDescriptor` semantic-key, absolute-local-path, and resource-budget invariants.
- [x] Kernel Ops remains independent from `platform/redaction`.
- [x] No Phase 3–6 production package is modified by this epic.
- [x] No future roadmap epic is rewritten as an implementation deliverable of this package.
- [x] Docs updated:
  - [x] `packages/core/contracts/README.md`
  - [x] `packages/core/foundation/README.md`
  - [x] `packages/platform/redaction/README.md`
  - [x] `packages/platform/redaction/SECURITY.md`
  - [x] `docs/architecture/PACKAGING.md`
  - [x] `docs/architecture/STRUCTURE.md`
  - [x] `docs/architecture/DEPENDENCIES.md`
  - [x] `docs/ssot/sensitive-data-redaction.md`
  - [x] `docs/ssot/json-like-runtime-values.md`
  - [x] `docs/ssot/application-dependency-sync.md`
  - [x] `docs/ssot/error-descriptor.md`
  - [x] `docs/ssot/observability-and-errors.md`
  - [x] `docs/ssot/observability.md`
  - [x] `docs/ssot/secrets-contracts.md`
  - [x] `docs/ssot/config-and-env.md`
  - [x] `docs/ssot/INDEX.md`
  - [x] `docs/adr/ADR-0010-sensitive-data-redaction-boundary.md`
  - [x] `docs/adr/INDEX.md`
- [x] All contract, unit, integration, architecture, package, ECS, and PHPStan checks pass.
- [x] Non-goals remain:
  - [x] secret resolution
  - [x] Vault/AWS/GCP integrations
  - [x] request-body inspection or semantic payload parsing
  - [x] SQL parsing
  - [x] mail-body rewriting
  - [x] AI guardrail or PII rewriting
  - [x] configurable/custom policy packs
  - [x] stateful classifier learning
  - [x] partially masked previews

---

### 2.30.0 Platform CLI — Tag-first Command Catalog + Kernel ops consumption (MUST) [IMPL]

---
type: package
phase: 2
epic_id: "2.30.0"
owner_path: "packages/platform/cli/"

package_id: "platform/cli"
composer: "coretsia/platform-cli"
kind: runtime
module_id: "platform.cli"

goal: "Переписати platform/cli як tag-first source-host command runtime: детерміновано відкривати команди enabled packages, виконувати normal commands через один Kernel UoW і надавати Kernel-owned config/module/cache operations через KernelOpsInterface з preset, визначеним Bootstrap configuration."
provides:
- "Command discovery SSoT: DI tag `cli.command` + deterministic tag order from Foundation TagRegistry"
- 'Kernel ops consumption: config validate/debug/compile/hash, module debug, and cache verify via `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`'
- "Explicit app-target selection for every Kernel operation command"
- "Generation-aware compile/hash/verify rendering without direct artifact reads"
- "Exact command argument/option metadata schema"
- "Descriptor-driven structural input validation before command service resolution"
- "Per-invocation buffered command output followed by one deterministic render"
- "Effective preset rendering from Kernel-owned OpsResult"
- "Safe-by-construction command output with deterministic JSON/table/plain rendering and shared defense-in-depth redaction; classifier non-matches do not authorize sensitive data disclosure"
- "Reserved names implemented (`doctor`, `help`, `list`) and enforced"
- "Package-agnostic command discovery: any enabled package may contribute lazy `cli.command` services without platform/cli source changes or compile-time dependency on the command owner."

tags_introduced: [] # `cli.command` already exists; this epic implements owner-side catalog and dispatch

config_roots_introduced: []  # `cli` root already exists
artifacts_introduced: []     # CLI owns no artifact schema

adr: "docs/adr/ADR-0034-cli-tag-first-command-catalog.md"

ssot_refs:
- "docs/ssot/tags.md"
- "docs/ssot/modes.md"
- "docs/ssot/observability.md"
- "docs/ssot/sensitive-data-redaction.md"
- "docs/ssot/cache-verify.md"
- "docs/ssot/artifacts.md"
- "docs/ssot/artifacts-and-fingerprint.md"
- "docs/ssot/artifact-generations.md"
- "docs/ssot/compiled-container.md"
- "docs/ssot/runtime-container-definitions.md"
- "docs/ssot/context-keys.md"
- "docs/ssot/context-store.md"
---

### Existing package replacement boundary (MUST)

The existing Phase-0 implementation is not a compatibility baseline.

This epic replaces:

- config-based FQCN command registration;
- direct PHP config loading in the CLI application;
- zero-argument command construction;
- any package-local redaction implementation, sensitive-key classifier, sensitive-value classifier, policy registry, hasher, or pattern registry;
- direct output writing from the command-facing output implementation;
- the combined parser/input implementation;
- the local error-code registry;
- the Phase-0 exception hierarchy.

No compatibility adapters, aliases, deprecated wrappers, dual dispatch paths, or fallback reads from the legacy `cli.commands` list may remain.

Files listed under `Deletes` MUST be absent after implementation.

Files listed as complete rewrites under `Modifies` retain only their paths and public package role, not their current implementation.

### Canonical target and preset ownership (MUST)

Kernel operation commands require:

```text
--target=web|api|console|worker
```

CLI MUST NOT infer the target.

Built-in Kernel operation commands declare only:

```text
--target=<appTarget>
```

They do not declare `--mode` or `--preset`, so descriptor-driven input validation rejects those options for Kernel operations.

Other package commands MAY declare options named `mode` or `preset` when those names have owner-package domain semantics.

Such options MUST NOT affect Kernel Bootstrap preset selection unless the command explicitly invokes a separate Kernel contract that permits it.

The operation request contains only `appTarget`.

Kernel resolves the effective preset through:

```text
presets[appTarget]
→ global preset
→ package default preset
```

CLI renders the effective preset returned by `OpsResult`.

### Baseline installation and preset composition (MUST)

- `coretsia/framework` owns the Composer-installable baseline dependency graph.
- The canonical baseline includes Kernel and CLI, with Redaction installed through the CLI dependency closure.
- `composer create-project coretsia/skeleton` installs the baseline through the existing project-owned `coretsia/framework` requirement.
- `kernel.boot.default_preset = micro` remains the default runtime selection policy.
- Installing a Composer package MUST NOT automatically enable its runtime module.
- Canonical presets select runtime module roots; they are not Composer packages or independently versioned distributions.
- `micro`, `express`, `hybrid`, and `enterprise` MUST reuse the existing Kernel preset loader, ModuleSelection, ModulePlan, and release installation catalog.
- Additional mode-required packages remain owned by explicit `ProjectDependencySync` plan/review/apply operations.
- No preset-specific Composer metapackages, second installation catalog, Composer dependency resolver, or implicit runtime-boot dependency synchronization may be introduced.
- The released `coretsia/framework` baseline MUST be installable with the corresponding published CLI and Redaction package versions before consumer create-project compatibility is claimed.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 2.20.0 — Kernel ops façade exists **as a contracts port implementation**:
    - `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface` is bound in container to kernel implementation
  - 2.25.0 — Sensitive data redaction boundary exists:
    - `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` exists

- Installation, module-selection, and source-host wiring postconditions established by this epic:
  - `platform.cli` is selected for `AppTarget::Console` through canonical Bootstrap Phase A preset and module-override resolution
  - the default `micro` preset selects `platform.cli` without requiring an application-owned preset override
  - explicit Console module exclusions remain authoritative; excluding `platform.cli` causes normal CLI host boot to fail closed
  - selecting `platform.cli` resolves its declared `core.kernel` and `platform.redaction` module requirements through the canonical Kernel ModulePlan
  - `platform.redaction` is installed in the consumer Composer graph before source-host module resolution
  - exactly one `SensitiveDataRedactorInterface` binding is contributed by the selected redaction provider before CLI services are resolved
  - `platform/cli` consumes only the contracts port and does not import or instantiate the concrete redactor
  - Composer package presence MUST NOT implicitly enable CLI or Redaction modules
  - CLI MUST NOT construct a second ModulePlan, append providers manually, or register a fallback redactor to compensate for missing module selection

- Required deliverables (exact paths):
  - `docs/ssot/tags.md` contains reserved tag `cli.command` (owner `platform/cli`)
  - `packages/platform/cli/config/cli.php` (subtree file)
  - `packages/platform/cli/config/rules.php`
  - kernel mode defaults/allowed exist under `kernel.modes.*`
  - Foundation declarative definition application and TagRegistry ordering are cemented
  - Kernel source/operations-host boot can run before generated artifacts exist
  - source-host boot reuses the canonical Bootstrap Phase A, config-location, and ConfigKernel Phase B capabilities
  - platform/cli and Kernel Ops MUST NOT introduce a parallel config discovery, loading, merge, or validation pipeline
  - artifact-only application runtime boot remains separate from CLI operations boot

- Required contracts / ports (exact FQCNs):
  - `Coretsia\Contracts\Cli\Input\InputInterface`
  - `Coretsia\Contracts\Cli\Output\OutputInterface`
  - `Coretsia\Contracts\Cli\Command\CommandInterface`
  - `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
  - `Coretsia\Contracts\Kernel\Ops\KernelOpsRequest`
  - `Coretsia\Contracts\Kernel\Ops\OpsResult`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlingContext`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface`
  - `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
  - `Coretsia\Contracts\Security\SensitiveDataRedactorInterface`
  - `Coretsia\Contracts\Context\ContextKeys`
  - `Psr\Container\ContainerInterface`
  - `Psr\Log\LoggerInterface`
- Context boundary:
  - CLI reads canonical context values only through `ContextAccessorInterface`
  - `ContextKeys` belongs to `core/contracts`
  - CLI MUST NOT import or resolve `ContextStore`
  - CLI MUST NOT resolve `CorrelationIdProvider` or create correlation ids
- Allowed Kernel host-bootstrap APIs:
  - `Coretsia\Kernel\Ops\KernelOpsHostInput`
  - `Coretsia\Kernel\Ops\KernelOpsHostBooter`
- Required Foundation and Kernel runtime APIs:
  - `Coretsia\Foundation\Tag\TagRegistry`
  - `Coretsia\Foundation\Tag\ReservedTags`
  - `Coretsia\Foundation\Time\Stopwatch`
  - `Coretsia\Kernel\Runtime\UnitOfWorkType`

- `ResetInterface` is not a baseline CLI dependency because no shared mutable CLI service is introduced.
- A future shared mutable service MUST add `ResetInterface` in the epic that introduces that service.

- External package command contract:
  - any enabled package MAY contribute command services through `cli.command`
  - the command class MUST implement `Coretsia\Contracts\Cli\Command\CommandInterface`
  - the owner package contributes the service and tag through its own provider
  - the owner package owns command arguments, options, domain validation, dependencies, and execution semantics
  - external commands depend only on contracts-level CLI input/output APIs, not platform/cli concrete classes
  - platform/cli MUST NOT import or compile-time depend on a command-owner package solely for discovery or dispatch
  - Worker commands are one compatibility fixture, not a special discovery path
  - migration, database, queue, storage, integration, and application commands MUST use the same mechanism
  - `CommandInterface::run(InputInterface $input, OutputInterface $output): int` returns the owner-selected process exit code
  - portable command exit codes MUST be integers from `0` through `255`

> Built-in Kernel operation commands consume Kernel operations only through `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`.
>
> `CliHostBootstrap` is the only platform/cli production class allowed to import:
>
> - `Coretsia\Kernel\Ops\KernelOpsHostInput`
> - `Coretsia\Kernel\Ops\KernelOpsHostBooter`
>
> All commands, providers, catalogs, validators, runners, formatters, and renderers MUST remain independent of concrete Kernel Ops classes.

### Cross-package modification boundary (MUST)

The only files outside `packages/platform/cli/` that this epic may create or modify are:

- `packages/core/contracts/src/Cli/Command/CommandInterface.php`
- `packages/core/contracts/src/Cli/Input/InputInterface.php`
- `packages/platform/worker/src/Console/WorkerStartCommand.php`
- `packages/platform/worker/src/Console/WorkerStopCommand.php`
- `packages/platform/worker/src/Console/WorkerStatusCommand.php`
- `packages/platform/worker/src/Console/WorkerHealthCommand.php`
- `packages/platform/worker/src/Provider/WorkerServiceProvider.php`
- `packages/platform/worker/tests/Contract/WorkerCommandMetadataConstantsTest.php`
- `packages/platform/worker/tests/Contract/WorkerServiceProviderCliCommandTaggingTest.php`
- `packages/platform/worker/tests/Integration/WorkerCompileArtifactBootE2ETest.php`
- `packages/platform/worker/tests/Integration/WorkerProviderSourceDefinitionsParityTest.php`
- `docs/adr/ADR-0034-cli-tag-first-command-catalog.md`
- `docs/ssot/tags.md`
- `docs/ssot/observability.md`
- `docs/adr/INDEX.md`
- `coretsia`
- `composer.lock`
- `.github/split-publish-packages.json`
- `packages/core/kernel/resources/packaging/installation-catalog.php`
- `tools/testing/package-index.php`
- `tools/testing/deptrac.yaml`
- `tools/tests/Integration/DependencySyncConsumerProjectTest.php`
- `tools/tests/Integration/DependencySyncFreshProcessVerificationTest.php`
- `packages/core/kernel/resources/modes/micro.php`
- `packages/core/kernel/resources/modes/express.php`
- `packages/core/kernel/resources/modes/hybrid.php`
- `packages/core/kernel/resources/modes/enterprise.php`
- `packages/framework/composer.json`
- `packages/framework/README.md`
- `packages/applications/skeleton/README.md`
- `packages/applications/skeleton/coretsia`
- `packages/core/kernel/tests/Integration/BootMicroPresetTest.php`
- `packages/core/kernel/tests/Integration/BootExpressPresetTest.php`
- `packages/core/kernel/tests/Integration/ModePresetSchemaValidatorEnforcesMicroAndExpressRulesTest.php`
- `packages/core/kernel/tests/Support/AppBuilder.php`
- `packages/core/kernel/tests/Unit/ProjectPackagePlannerTest.php`
- `packages/core/kernel/tests/Unit/Config/ConfigValidatorRejectsInvalidCliCommandsTest.php`
- `packages/core/kernel/tests/Unit/Config/ConfigValidatorRejectsInvalidCliOutputFormatTest.php`
- `packages/core/kernel/tests/Unit/Config/ConfigValidatorDiagnosticsAreSafeAndDeterministicTest.php`
- `docs/ssot/modes.md`
- `docs/ssot/application-dependency-sync.md`
- `docs/architecture/DEPENDENCIES.md`

No other command-owner package may be modified solely to implement the CLI host.

Framework distribution and canonical Kernel preset changes are permitted only for the explicitly defined baseline installation and module-selection policy.

Kernel module planning, Composer reconciliation, release installation catalog generation, and application dependency synchronization implementations MUST remain unchanged.

Existing integration fixtures and assertions affected by the new canonical preset module closure MUST be updated without weakening their runtime, dependency, and artifact invariants.

Generated repository artifacts MAY change only through their canonical generators and Composer dependency synchronization.

The epic MUST NOT introduce a second package index, installation catalog, dependency graph, or repository metadata generator.

### Kernel operations consumption boundary (MUST)

`platform/cli` is a transport, command-routing, and presentation layer.

Generic command flow:

```text
parsed command input
→ separate CLI-global format|color
→ CommandCatalog
→ CommandDescriptor
→ CommandInputValidator
→ Kernel UnitOfWork
→ lazy command service resolution inside the UoW callback
→ Coretsia\Contracts\Cli\Command\CommandInterface
→ owner-package services
→ CommandOutputBuffer
→ deterministic formatter
→ console writer
```

Built-in Kernel operation command flow:

```text
Kernel operation command
→ KernelOpsRequestResolver
→ KernelOpsRequest(appTarget)
→ Coretsia\Contracts\Kernel\Ops\KernelOpsInterface
→ safe OpsResult
→ CommandOutputBuffer
```

`KernelOpsRequestResolver` and `KernelOpsInterface` apply only to the built-in Kernel operation commands.

External package commands MUST NOT be routed through `KernelOpsInterface` unless they are explicitly invoking a Kernel-owned operation.

CLI commands, providers, catalog services, runners, formatters, and renderers MUST NOT orchestrate or resolve Kernel compile-time internals.

Module resolution, provider planning, config compilation, runtime graph compilation, generation publication, fingerprint calculation, current-generation location, and cache verification are Kernel-owned operations.

`OpsResult` is already safe by construction. CLI redaction is defense in depth for CLI-owned output and diagnostics; it MUST NOT be used to sanitize raw Kernel config, env, Composer metadata, or artifact payloads.

### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Composer installation additionally requires `platform/redaction`, but PHP source-level redaction consumption remains contracts-only. Deptrac MUST NOT interpret this Composer requirement as permission to import the platform-owned redaction implementation.

Forbidden:

- command-owner packages in platform/cli production source, including:
  - `platform/worker`
  - migration or database packages
  - queue or scheduler packages
  - `integrations/*`
- external console frameworks / parsers
- filesystem scanning for command discovery

- direct use from `platform/cli` production source of:
  - `Coretsia\Kernel\Module\ModulePlanResolver`
  - `Coretsia\Kernel\Module\ModuleResolution`
  - `Coretsia\Kernel\Container\Provider\ContainerProviderPlan`
  - `Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver`
  - `Coretsia\Contracts\Module\ManifestReaderInterface`
  - `Coretsia\Kernel\Module\ComposerManifestReader`
  - `Coretsia\Kernel\Artifacts\Compiler\ArtifactCompiler`
  - `Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator`
  - `Coretsia\Kernel\Artifacts\Verifier\CacheVerifier`
  - `Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLocator`
  - `Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPublisher`
  - `Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationValidator`
  - `Coretsia\Kernel\Artifacts\Paths\ArtifactPathResolver`
  - `Coretsia\Kernel\Artifacts\Php\PhpArtifactReader`
  - `Coretsia\Kernel\Boot\ArtifactRuntimeBooter`
  - `Coretsia\Kernel\Boot\ArtifactRuntimeInput`

`platform/cli` infrastructure and built-in Kernel operation commands MUST NOT:

- read Kernel `current`;
- parse Kernel generation manifests;
- resolve Kernel generation directories;
- include Kernel PHP artifact files;
- construct Kernel artifact paths;
- boot the compiled application runtime;
- inspect Kernel `config.php` or `container.php` directly.

All Kernel generation access initiated by built-in Kernel commands occurs behind `KernelOpsInterface`.

Commands contributed by other packages MAY access owner-package files or artifacts through owner-owned services and contracts. They MUST NOT access Kernel artifact internals except through an explicit Kernel public port.

These symbols belong to Kernel-side operation orchestration. Their presence in CLI commands, providers, runners, catalog services, formatters, or renderers is an architecture violation.

- Integration tests MAY enable `platform.worker` only through a composed test fixture/app.
- `platform/cli` production source MUST NOT import external command-owner namespaces solely for command discovery or dispatch.
- owner-package classes MAY appear only in composed integration tests.

### Entry points / integration points (MUST)

- CLI entrypoint (packaged):
  - `packages/platform/cli/bin/coretsia` (declared in composer.json `"bin"`)
- Monorepo canonical entrypoint (Prelude compliance):
  - repo-root `coretsia` MUST remain the only canonical monorepo wrapper
  - the wrapper MUST delegate to `packages/platform/cli/bin/coretsia` through a fixed repository-relative path
  - the wrapper MUST be independent of the current working directory
- Canonical CLI executable naming:
  - `coretsia` is the single canonical public CLI executable name
  - monorepo invocation: `php coretsia <command>` through the monorepo-root wrapper
  - Composer-installed Skeleton invocation: `php coretsia <command>` through the project-root Skeleton wrapper
  - direct Composer binary invocation: `php vendor/bin/coretsia <command>`
  - executable Composer binary invocation: `vendor/bin/coretsia <command>` on supported Unix environments
  - the monorepo-root and Skeleton project-root wrappers are distinct files with the same public executable name
  - the Skeleton wrapper delegates only through its installed Composer binary proxy
  - the Composer binary proxy and Skeleton wrapper MUST reach the same packaged CLI implementation
  - the packaged Composer binary MUST resolve the consumer application's root rather than the installed framework package or monorepo root
  - a Skeleton wrapper invocation MUST preserve Composer-proxy application-root resolution without introducing a conflicting explicit-root source
  - CLI MUST NOT introduce additional canonical executable names such as `c` or `cli`
  - short names MAY be configured by users as local shell aliases but are not framework entrypoints
  - no second CLI runtime, parser, catalog, bootstrap pipeline, or command dispatch implementation may be introduced for executable naming
- CLI has two explicit boot paths.

#### Ultra-early doctor path

```text
bin/coretsia
→ Composer autoload
→ src/Bootstrap/Entrypoint/cli.php
→ exact command token is doctor
→ UltraEarlyDoctorRunner
→ the same dependency-free DoctorCommand used by the normal catalog
→ no KernelOpsHostBooter
→ no command catalog
→ no module discovery
→ no generated artifacts
```

`doctor` is the only command allowed to bypass the normal tag-backed catalog dispatch path.

`DoctorCommand` MUST also be registered and tagged in the source operations host so that `list` and `help` describe the same command metadata used by the ultra-early path.

#### Normal CLI path

```text
bin/coretsia
→ Composer autoload
→ src/Bootstrap/Entrypoint/cli.php
→ CliHostBootstrap
→ KernelOpsHostInput
→ KernelOpsHostBooter
→ source operations container for AppTarget::Console
→ CommandCatalog
→ descriptor selection
→ structural input validation
→ Kernel UnitOfWork
→ lazy command resolution inside the UoW callback
→ command execution
```

The normal CLI path:

- MUST work without an artifact root or `current`;
- MUST NOT call `ArtifactRuntimeBooter`;
- MUST NOT boot from `container.php`;
- MUST NOT use generated config as source-host configuration;
- MUST compose enabled external package providers through Kernel-owned module/provider planning;
- MUST require `platform.cli` in the resolved Console ModulePlan; absence fails closed instead of bypassing module selection or constructing a separate CLI container;
- MUST obtain `platform.redaction` through the canonical dependency closure of `platform.cli`, not through CLI-owned provider registration;
- MUST NOT read Composer metadata from platform/cli;
- MUST NOT become a fallback for HTTP or Worker production runtime.
- `KernelOpsHostBooter::boot()` performs canonical Console source-config compilation and validation before `CliApplication` resolution and command dispatch.
- Invalid Console-host configuration fails at the host-bootstrap boundary with `CORETSIA_CLI_HOST_BOOT_FAILED`, reason `host-boot-failed`, and exit code `1`.
- This failure occurs before `config:validate --target=<target>` can invoke `KernelOpsInterface`, regardless of the requested target.
- `config:validate valid=false` → exit code `1` applies only when Console-host boot succeeds and Kernel Ops returns a handled validation result.
- CLI MUST NOT bypass the Console-host validation gate or introduce a second config-loading pipeline to handle this case.

- Commands:
  - `coretsia doctor`
  - `coretsia list`
  - `coretsia help [<command>]`
  - `coretsia debug:modules --target=<target>`
  - `coretsia config:validate --target=<target>`
  - `coretsia config:debug --target=<target>`
  - `coretsia config:compile --target=<target>`
  - `coretsia config:hash --target=<target>`
  - `coretsia cache:verify --target=<target>`

### Command discovery (tag-first, deterministic) (MUST)

- [x] the only container-backed discovery mechanism for normal commands is DI tag `cli.command`
- [x] the exact ultra-early `doctor` invocation is an entrypoint-owned exception and does not perform discovery
- [x] once the normal operations host exists, `doctor` MUST appear as a tagged descriptor for `list` and `help`
- [x] CLI MUST NOT read any `cli.commands` registry list.
- [x] Consumer MUST NOT re-sort or re-dedupe TagRegistry output.

- [x] External package commands:
  - [x] CLI catalog MUST discover commands contributed by other enabled packages through `cli.command`.
  - [x] CLI MUST NOT special-case `platform/worker`.
  - [x] CLI MUST NOT depend on `platform/worker` at compile time.
  - [x] Worker command discovery, when tested, MUST happen through the same generic `cli.command` mechanism as all other package commands.

### Command identity ownership (MUST)

- [x] command name MUST be declared by the command class
- [x] every tagged command class MUST expose:
  - [x] `public const string NAME`
  - [x] `public const string SUMMARY`
  - [x] `public const string GROUP`
  - [x] `public const bool HIDDEN`
  - [x] `public const array ARGUMENTS`
  - [x] `public const array OPTIONS`
- [x] `CommandInterface::name()` MUST return the same value as tag metadata `name`
- [x] command provider MUST reference command class constants when tagging commands
- [x] command provider MUST NOT invent command names as unrelated string literals
- [x] command name MUST match:
  - [x] `\A[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)*\z`
- [x] every `cli.command` service id MUST be the exact command class FQCN
- [x] the service-id class MUST exist and implement `Coretsia\Contracts\Cli\Command\CommandInterface`
- [x] `CommandCatalog` MUST read command constants through the service-id class without resolving or instantiating the service

### `cli.command` tag metadata schema (MUST)

- [x] exact required keys:
  - [x] `name`
  - [x] `summary`
  - [x] `group`
  - [x] `hidden`
  - [x] `arguments`
  - [x] `options`
- [x] unknown metadata keys MUST hard-fail deterministically
- [x] metadata key `priority` MUST be forbidden
- [x] canonicalized base tag metadata MUST equal the command class constants before `CommandOverrides` is applied
- [x] `CommandOverrides` MAY modify only the catalog-view fields `summary|hidden|group`; it MUST NOT mutate command identity, arguments, options, service id, or original tag metadata
- [x] metadata MUST NOT contain closures, objects, resources, floats, raw config values, runtime filesystem path values, runtime endpoints, env values, secrets, tokens, or runtime payloads
- [x] argument and option names or summaries MAY describe owner-domain path inputs; runtime path values and path defaults MUST NOT be stored in tag metadata

### Command priority policy (MUST)

- [x] command tags MUST NOT define `priority`
- [x] command routing MUST NOT use priority
- [x] duplicate command names across distinct tagged service ids MUST hard-fail deterministically
- [x] repeated registrations of the same `(tag, serviceId)` follow the existing Foundation TagRegistry first-wins policy and are not observable as separate catalog entries
- [x] reserved command names MUST hard-fail for external commands:
  - [x] `doctor`
  - [x] `help`
  - [x] `list`
- [x] reserved names are allowed only for the built-in command service ids registered by `platform/cli`
- [x] the reserved `doctor` identity MUST match the command used by the ultra-early entrypoint
- [x] CLI consumer MUST preserve TagRegistry order
- [x] CLI consumer MUST NOT re-sort TagRegistry output
- [x] CLI consumer MUST NOT silently de-dupe TagRegistry output
- [x] every `cli.command` TaggedService returned by `TagRegistry::all()` MUST have actual priority `0`
- [x] a non-zero `TaggedService::priority()` in canonical discovery output hard-fails before descriptor construction
- [x] CLI MUST NOT normalize, ignore, or overwrite a non-zero priority
- [x] registrations already discarded by TagRegistry first-wins MUST NOT be reconstructed or revalidated by CLI

### Lazy command discovery (MUST)

- [x] `CommandCatalog` MUST build descriptors from `cli.command` tag metadata
- [x] `CommandCatalog` MUST NOT instantiate command services while building the catalog
- [x] `list` MUST be renderable from descriptors without instantiating all command services
- [x] `help` MUST be renderable from descriptors without instantiating all command services
- [x] command service MUST be resolved only when the selected command is dispatched
- [x] on dispatch, resolved service MUST implement `Coretsia\Contracts\Cli\Command\CommandInterface`
- [x] on dispatch, resolved command `name()` MUST match descriptor/tag metadata `name`
- [x] mismatch MUST hard-fail with `InvalidCommandTagMetaException`

### Kernel operation command exit codes (MUST)

These mappings apply only to built-in Kernel operation commands.

Commands contributed by other packages own their domain result-to-exit-code mapping through the shared command contract. `platform/cli` MUST NOT reinterpret owner-package outcomes except for global parsing, bootstrap, and uncaught execution failures.

```text
0
operation completed with a positive result

1
invalid command input, invalid config, handled Kernel operation failure,
or `KernelOpsFailedException`

2
cache verification completed with state `dirty`

3
cache verification completed with state `invalid`
```

Specific mapping:

```text
config:validate valid=true  -> 0
config:validate valid=false -> 1

config:compile success -> 0
config:hash success    -> 0

cache:verify clean   -> 0
cache:verify dirty   -> 2
cache:verify invalid -> 3
```

CLI MUST NOT derive these states by inspecting artifact files.

It maps only the safe `OpsResult` returned by Kernel.

### Deliverables (exact paths only) (MUST)

#### Deletes

Note: This section defines the initial Phase-0 cleanup. Files also listed under `Creates` or `Modifies` MUST be recreated or rewritten according to their final specifications; only files not reintroduced by those sections MUST remain absent.

- [x] `tools/bin/coretsia`

Legacy production files:
- [x] `packages/platform/cli/src/Application.php`
  - [x] remove config loading, root inference, FQCN registry, reflection, zero-argument command construction, and legacy dispatch
  - [x] no compatibility wrapper or class alias remains

- [x] `packages/platform/cli/src/Input/CliInput.php`
  - [x] replaced by `ArgvInput` and `ArgvInputParser`

- [x] `packages/platform/cli/src/Output/CliOutput.php`
  - [x] replaced by buffer, formatters, redactor integration, and `ConsoleOutputWriter`

- [x] `packages/platform/cli/src/Output/TrackedOutput.php`
  - [x] no mutable error-tracking decorator remains

- [x] `packages/platform/cli/src/Error/ErrorCodes.php`
  - [x] error codes move to owning exception classes
  - [x] no global CLI error-code registry remains

- [x] `packages/platform/cli/src/Command/ListCommand.php`
- [x] `packages/platform/cli/src/Command/HelpCommand.php`

- [x] `packages/platform/cli/src/Exception/CliCommandClassMissingException.php`
- [x] `packages/platform/cli/src/Exception/CliCommandFailedException.php`
- [x] `packages/platform/cli/src/Exception/CliCommandInvalidException.php`
- [x] `packages/platform/cli/src/Exception/CliConfigInvalidException.php`
- [x] `packages/platform/cli/src/Exception/CliException.php`
- [x] `packages/platform/cli/src/Exception/CliExceptionInterface.php`
  - [x] legacy Phase-0 exception hierarchy is removed
  - [x] no compatibility aliases remain

Legacy tests and fixtures:
- [x] `packages/platform/cli/tests/Contract/CliConfigSubtreeShapeAndMergeSemanticsTest.php`
- [x] `packages/platform/cli/tests/Integration/ApplicationDispatchIntegrationTest.php`
- [x] `packages/platform/cli/tests/Integration/CliBootHelpWorksWithEmptyCommandsTest.php`
- [x] `packages/platform/cli/tests/Integration/CliRejectsMissingCommandClassDeterministicallyTest.php`
- [x] `packages/platform/cli/tests/Integration/OutputRedactionDoesNotLeakTest.php`
- [x] `packages/platform/cli/tests/Fake/FakeWorkspaceSyncApplyCommand.php`
- [x] `packages/platform/cli/tests/Fake/FakeWorkspaceSyncDryRunCommand.php`
- [x] `packages/platform/cli/tests/Fixtures/LeakCommand.php`
- [x] `packages/platform/cli/tests/Fixtures/LeakCommand.prepend.php`
  - [x] replaced by tag-backed catalog, output-pipeline, security, and external-package fixtures defined by this epic

- [x] `packages/platform/cli/src/Provider/CliServiceFactory.php`
  - [x] `final class CliServiceFactory {}`
- [x] `packages/platform/cli/src/Provider/CliServiceProvider.php`
  - [x] `final class CliServiceProvider {}`

- [x] `packages/platform/cli/tests/Contract/CommandsDoNotWriteToStdoutTest.php`
  - [x] `public function testNoopDoesNotThrow(): void { self::assertTrue(true); }`
- [x] `packages/platform/cli/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [x] `public function testNoopDoesNotThrow(): void { self::assertTrue(true); }`

#### Creates

- [x] `packages/applications/skeleton/coretsia`
  - [x] thin project-root wrapper for `php coretsia <command>`
  - [x] PHP executable with `#!/usr/bin/env php` and `declare(strict_types=1)`
  - [x] follows the canonical Coretsia PHP file-header conventions
  - [x] resolves the Composer binary only as `__DIR__ . '/vendor/bin/coretsia'`
  - [x] verifies that the Composer binary exists and is readable before requiring it
  - [x] missing or unreadable Composer binary:
    - [x] writes exactly `CORETSIA_CLI_BOOTSTRAP_FAILED: autoload-unavailable` followed by one LF to stderr
    - [x] exits with code `1`
    - [x] MUST NOT emit PHP warnings, fatal-error details, filesystem paths, or stack traces
  - [x] delegates using `$exitCode = require __DIR__ . '/vendor/bin/coretsia';`
  - [x] preserves the original CLI `argv` without modifying the launcher token, arguments, or options
  - [x] preserves Composer binary-proxy variables and their scope for the packaged CLI entrypoint
  - [x] if the delegated binary unexpectedly returns, propagate its exit code only when it is an integer within `0..255`
  - [x] a missing, non-integer, or out-of-range delegated return value MUST fail closed with exit code `1`
  - [x] MUST NOT cast `null`, `false`, or another invalid delegated return value to a successful exit code
  - [x] the fallback MUST NOT invoke another CLI runtime, renderer, or bootstrap pipeline
  - [x] the packaged CLI binary remains responsible for normal execution, diagnostics, and process termination
  - [x] MUST NOT introduce an additional application-root resolver or set monorepo-specific bootstrap variables
  - [x] MUST NOT perform command parsing, configuration loading, Kernel boot, catalog construction, formatting, or rendering
  - [x] MUST NOT depend on the current working directory

Redaction:
- [x] CLI output MUST use `Coretsia\Contracts\Security\SensitiveDataRedactorInterface`.
- [x] CLI MUST NOT define package-local redaction engine/policy classes.
- [x] CLI MAY define CLI-domain output formatting rules, but baseline sensitive data classification and redacted output generation belong to `platform/redaction`.

- [x] `packages/platform/cli/src/Output/CliOutputPolicy.php`
  - [x] immutable readonly value object
  - [x] created only from validated config
  - [x] contains:
    - [x] `formatDefault: string`
    - [x] `interactiveFormat: string`
    - [x] `nonInteractiveFormat: string`
    - [x] `colorDefault: string`
    - [x] `tableMaxWidth: int`
  - [x] validates canonical tokens defensively
  - [x] contains no config repository
  - [x] contains no streams
  - [x] contains no terminal detection
  - [x] contains no runtime global-option overrides
  - [x] contains no redaction toggle
  - [x] contains no mutable state

- [x] `packages/platform/cli/src/Output/TerminalCapabilities.php`
  - [x] immutable readonly runtime-input value
  - [x] contains:
    - [x] `interactive: bool`
    - [x] `ansiSupported: bool`
  - [x] created exactly once for each normal-path CLI invocation; the ultra-early `doctor` path does not require terminal capability detection
  - [x] terminal probing occurs only at the packaged entrypoint/bootstrap boundary
  - [x] formatters and commands MUST NOT call `stream_isatty()` directly
  - [x] formatters and commands MUST NOT perform Windows terminal probing directly
  - [x] tests can inject deterministic capabilities
  - [x] contains no config and no output policy
  - [x] `interactive` means stdout is interactive
  - [x] `ansiSupported` is true only when ANSI is safe for both stdout and stderr

- [x] `packages/platform/cli/src/Output/TerminalCapabilitiesDetector.php`
  - [x] is the only platform/cli service allowed to probe terminal capabilities
  - [x] receives explicit stdout and stderr stream resources from the entrypoint
  - [x] stdout determines adaptive-format interactivity
  - [x] ANSI support requires both output streams to accept ANSI safely
  - [x] detects:
    - [x] interactive output
    - [x] ANSI support
  - [x] returns immutable `TerminalCapabilities`
  - [x] MUST NOT read CLI config
  - [x] MUST NOT read command options
  - [x] MUST NOT read CI environment variables
  - [x] MUST NOT retain streams
  - [x] tests can replace it with deterministic capabilities

- [x] `packages/platform/cli/src/Bootstrap/CliEntrypointPaths.php`
  - [x] immutable readonly value object
  - [x] contains:
    - [x] normalized Composer autoload path
    - [x] normalized application root (repository root for monorepo execution, consumer project root for Composer-installed execution)
  - [x] performs no filesystem traversal or resolution
  - [x] exposes no public diagnostic rendering

- [x] `packages/platform/cli/src/Bootstrap/CliEntrypointPathsResolver.php`
  - [x] pure entrypoint-layout resolver
  - [x] unresolved or conflicting post-autoload layout throws `CliBootstrapException`
  - [x] canonical API:
    - [x] `public function resolve(string $launcherFile, string $autoloadPath, ?string $composerBinDir, ?string $explicitApplicationRoot): CliEntrypointPaths`
  - [x] receives an already-selected readable autoload path
  - [x] validates and normalizes that path
  - [x] explicit repository application root is supplied by the canonical monorepo-root wrapper
  - [x] otherwise the consumer application root is derived only from the canonical Composer binary layout
  - [x] exactly one application-root source may be active
  - [x] supports:
    - [x] Composer binary proxy variables
    - [x] monorepo canonical wrapper layout
  - [x] independent of current working directory
  - [x] MUST NOT recursively scan arbitrary parent directories
  - [x] MUST NOT read application config
  - [x] unresolved layout throws a deterministic path-safe CLI bootstrap exception

- [x] `packages/platform/cli/src/Bootstrap/UltraEarlyDoctorRunner.php`
  - [x] used only when the exact command token is `doctor`
  - [x] creates invocation-local `ArgvInput`
  - [x] creates invocation-local `CommandOutputBuffer`
  - [x] invokes the same `DoctorCommand` class registered in the normal catalog
  - [x] uses fixed `plain` output
  - [x] forces color disabled
  - [x] renders only allowlisted doctor record types and scalar values
  - [x] writes through `ConsoleOutputWriter`
  - [x] requires no Kernel container, configuration, UoW, tracer, meter, logger, context, or redaction service
  - [x] MUST NOT expose:
    - [x] raw environment values
    - [x] absolute paths
    - [x] complete loaded-extension lists
    - [x] phpinfo output
    - [x] command lines
    - [x] stack traces
  - [x] returns a deterministic exit code
  - [x] canonical API:
    - [x] `public function run(array $argv, ConsoleOutputWriter $writer): int`
  - [x] accepts only the exact invocation:
    - [x] `coretsia doctor`
  - [x] additional arguments or options fail deterministically through the fixed doctor output pipeline

- [x] `packages/platform/cli/src/Bootstrap/CliHostBootstrap.php`
  - [x] is the only platform/cli class allowed to import `KernelOpsHostInput` and `KernelOpsHostBooter`
  - [x] boots the source operations container
  - [x] resolves the already-built Kernel-owned `ModulePlan` from the returned source container
  - [x] requires `ModulePlan::app()` to equal `console`
  - [x] requires `ModulePlan::hasEnabledModule('platform.cli')` before resolving `CliApplication`
  - [x] an absent or explicitly excluded `platform.cli` fails closed even when Foundation concrete-class autowiring is enabled
  - [x] MUST NOT construct another ModulePlan, invoke module resolution, alter module selection, or register fallback CLI services
  - [x] resolves `CliApplication` from that container only after successful ModulePlan verification
  - [x] contains no command discovery, parsing, formatting, or Kernel operation logic
  - [x] receives the one invocation-local `TerminalCapabilities` instance created at the entrypoint boundary
  - [x] terminal capability detection is separate from command parsing and domain logic
  - [x] passes capabilities into the resolved `CliApplication`
  - [x] canonical entrypoint API:
    - [x] `public function run(string $applicationRoot, array $argv, TerminalCapabilities $capabilities, ConsoleOutputWriter $writer): int`
  - [x] constructs `KernelOpsHostInput` internally from explicit `applicationRoot`
  - [x] boots one source operations container
  - [x] resolves `CliApplication`
  - [x] passes `argv|capabilities|writer` as invocation-local values
  - [x] directly constructs the zero-constructor `KernelOpsHostBooter`
  - [x] calls exactly one `KernelOpsHostBooter::boot()` invocation
  - [x] does not expect `KernelOpsHostBooter` to be resolved from the container it creates
  - [x] MUST NOT register raw argv, streams, writer, capabilities, or output buffer as shared container services
  - [x] catches failures from:
    - [x] `KernelOpsHostBooter::boot()`
    - [x] source-container construction
    - [x] resolved Console ModulePlan verification
    - [x] `CliApplication` resolution
  - [x] writes one fixed safe diagnostic through `ConsoleOutputWriter`:
    - [x] code `CORETSIA_CLI_HOST_BOOT_FAILED`
    - [x] reason `host-boot-failed`
  - [x] returns exit code `1`
  - [x] MUST NOT invoke `ErrorHandlerInterface` because the CLI application container may not exist
  - [x] MUST NOT expose Throwable message, class, trace, provider id, config value, or path

Entrypoint + application:
- [x] `packages/platform/cli/bin/coretsia`
  - [x] before using any package class:
    - [x] select autoload only from Composer binary-proxy input or canonical Coretsia wrapper input
    - [x] reject conflicting sources
    - [x] require the selected autoload file exactly once
  - [x] missing, conflicting, or unreadable autoload uses the sole pre-autoload fallback:
    - [x] writes `CORETSIA_CLI_BOOTSTRAP_FAILED: autoload-unavailable` directly to stderr
    - [x] exits with code `1`
    - [x] emits no path, Throwable message, class, or trace
  - [x] direct stderr use is forbidden after Composer autoload succeeds
  - [x] PHP executable (`#!/usr/bin/env php`)
  - [x] reads raw `argv` and forwards it unchanged to the package-owned entrypoint
  - [x] requires `src/Bootstrap/Entrypoint/cli.php` only after successful Composer autoload
  - [x] requires the entrypoint file to return a callable
  - [x] passes the original `__FILE__` launcher path, selected autoload path, Composer bin directory, explicit application root, raw `argv`, and stdout/stderr streams
  - [x] preserves Composer binary-proxy variables and canonical wrapper application-root input
  - [x] MUST NOT resolve the application root inline
  - [x] MUST NOT import or instantiate CLI `@internal` classes
  - [x] contains no command discovery, parsing, formatting, or Kernel operation logic
  - [x] unexpected entrypoint loading or invocation failures fail closed with exit code `1` without introducing another output sink
  - [x] propagates only integer exit codes in `0..255`; otherwise exits with code `1`
  - [x] direct `fwrite(STDOUT|STDERR)` is forbidden after successful Composer autoload
  - [x] MUST NOT depend on the deleted `ErrorCodes` registry

- [x] `packages/platform/cli/src/Bootstrap/Entrypoint/cli.php`
  - [x] package-owned closure-return composition root under `src/`
  - [x] follows the canonical Coretsia PHP file-header conventions
  - [x] returns one callable accepting explicit launcher, autoload, Composer bin directory, application-root, argv, stdout, and stderr inputs
  - [x] receives the original executable path; MUST NOT substitute its own `__FILE__`
  - [x] MUST NOT infer the application root from CWD or scan parent directories
  - [x] uses the same canonical CLI classes and contracts as the original packaged executable
  - [x] performs only the exact ultra-early `doctor` command-token check
  - [x] for exact `doctor`, invokes `UltraEarlyDoctorRunner` without resolving `CliEntrypointPathsResolver`, Kernel, catalog, or generated artifacts
  - [x] for every other command, resolves the canonical application root through `CliEntrypointPathsResolver`
  - [x] detects terminal capabilities using `TerminalCapabilitiesDetector` with the explicit stdout and stderr streams
  - [x] delegates normal execution to `CliHostBootstrap`
  - [x] constructs no parallel CLI runtime, parser, catalog, bootstrap pipeline, or command dispatcher
  - [x] catches `CliBootstrapException` before generic `Throwable`
  - [x] renders only the canonical bootstrap exception code and reason through `ConsoleOutputWriter`
  - [x] owns the final post-autoload uncaught Throwable boundary
  - [x] uncaught failure writes exactly:
    - [x] `CORETSIA_CLI_UNCAUGHT_EXCEPTION: uncaught-exception`
  - [x] all post-autoload diagnostics use `ConsoleOutputWriter`
  - [x] returns exit code `1` for caught bootstrap or uncaught execution failures
  - [x] MUST NOT render Throwable messages, classes, traces, previous throwables, config values, or filesystem paths
  - [x] MUST NOT depend on the deleted `ErrorCodes` registry

- [x] `packages/platform/cli/src/Application/CliApplication.php`
  - [x] owns the normal post-bootstrap command flow:
    - [x] create one invocation-local `CommandOutputBuffer`
    - [x] parse into `ParsedCliInvocation`
    - [x] resolve effective format
    - [x] resolve effective color
    - [x] select `CommandDescriptor`
    - [x] perform descriptor-driven structural validation
    - [x] call `CommandRunner`
    - [x] `CommandRunner` lazily resolves and executes the selected command
    - [x] finalize one `CommandOutputBatch`
    - [x] format and redact once through `OutputFormatter`
    - [x] write one `FormattedOutput`
    - [x] return the command exit code
  - [x] MUST NOT read `ConfigRepositoryInterface` directly
  - [x] MUST NOT write ContextStore directly
  - [x] MUST NOT create correlation or UoW ids
  - [x] MUST NOT perform terminal capability probing
  - [x] MUST NOT retain previous command, output batch, format, color, or exit code
  - [x] construction-time `CommandCatalog` and `CommandOverrides` failures occur before `CliApplication::run()` and belong to the `CliHostBootstrap` failure boundary
  - [x] command selection failures from `CommandCatalog::require()` during `run()` remain owned by the normal `CliApplication` error boundary
  - [x] both `RedactionViolationException` and `CliOutputFormatException` enter the fixed non-recursive render fallback
  - [x] canonical API:
    - [x] `public function run(array $argv, TerminalCapabilities $capabilities, ConsoleOutputWriter $writer): int`
  - [x] constructor receives:
    - [x] `ArgvInputParser`
    - [x] `CommandCatalog`
    - [x] `CommandInputValidator`
    - [x] `FormatResolver`
    - [x] `ColorResolver`
    - [x] `CommandRunner`
    - [x] `CliErrorHandler`
    - [x] `ExceptionRenderer`
    - [x] `OutputFormatter`
  - [x] owns the complete normal-path error boundary:
    - [x] creates an invocation-local `CommandOutputBuffer` before normal parsing begins
    - [x] catches failures from:
      - [x] argv parsing
      - [x] global-option resolution
      - [x] catalog selection
      - [x] descriptor validation
      - [x] lazy service resolution
      - [x] command execution
    - [x] passes the Throwable to `CliErrorHandler`
    - [x] calls `CommandOutputBuffer::discard()` after a thrown failure
    - [x] passes the resulting `ErrorDescriptor` and the same cleared buffer to `ExceptionRenderer`
    - [x] finalizes exactly one normalized error record
    - [x] returns exit code `1`
  - [x] error rendering format:
    - [x] uses the resolved output format when resolution completed successfully
    - [x] otherwise uses fixed `plain` format with color disabled
    - [x] malformed `--format` or `--color` MUST NOT be reused during error rendering
  - [x] if `CliErrorHandler`, `ExceptionRenderer`, formatter, or redactor fails:
    - [x] write one fixed safe plain diagnostic through `ConsoleOutputWriter`
    - [x] code: `CORETSIA_CLI_RENDER_FAILURE`
    - [x] reason: `render-failure`
    - [x] return exit code `1`
    - [x] do not retry formatting or redaction

Input:
- [x] `packages/platform/cli/src/Input/ParsedCliInvocation.php`
  - [x] immutable readonly value
  - [x] contains:
    - [x] `ArgvInput commandInput`
    - [x] nullable `formatOverride: string`
    - [x] nullable `colorOverride: string`
  - [x] contains no streams, config repository, catalog, descriptor, or command service

- [x] `packages/platform/cli/src/Input/ArgvInput.php`
  - [x] Concrete `InputInterface` implementation; deterministic parse rules (no locale-dependent behavior).
  - [x] MUST implement expanded `InputInterface`
  - [x] MUST expose:
    - [x] raw tokens
    - [x] command name
    - [x] positional arguments
    - [x] normalized options
    - [x] option lookup by name
    - [x] boolean flags
  - [x] `tokens()` contains only command-facing tokens
  - [x] launcher token and CLI-global options are absent
  - [x] MUST NOT expose parser internals
  - [x] MUST NOT require commands to depend on `platform/cli` concrete classes

- [x] `packages/platform/cli/src/Input/ArgvInputParser.php`
  - [x] canonical API:
    - [x] `public function parse(array $argv): ParsedCliInvocation`
  - [x] structural parsing failures throw `CliInputInvalidException`
  - [x] removes launcher token before command parsing
  - [x] CLI-global `format|color` options are recognized only before the `--` end-of-options marker
  - [x] removes only recognized CLI-global `format|color` options from command-facing tokens and options
  - [x] preserves recognized global option values only in `ParsedCliInvocation`
  - [x] tokens following `--` are positional arguments, even when their literal text is `--format=...` or `--color=...`
  - [x] positional tokens following `--` MUST NOT be interpreted as CLI-global or command-owner options
  - [x] Minimal parser (no external libs): `<command> [--key=val] [--flag] [args...]`; stable precedence rules.
  - [x] repeated bare flags hard-fail
  - [x] mixing bare `--name` and valued `--name=value` forms of the same option hard-fails deterministically
  - [x] repeated valued `--name=value` forms preserve `list<string>` order
  - [x] the parser MUST NOT silently replace a previously parsed boolean value with a scalar or list, or vice versa
  - [x] MUST parse deterministic syntax:
    - [x] `<command>`
    - [x] positional arguments
    - [x] `--key=value`
    - [x] `--flag`
    - [x] repeated `--key=value` into `list<string>`
  - [x] MUST reject malformed option names deterministically
  - [x] MUST normalize option names deterministically
  - [x] MUST NOT use locale-dependent parsing
  - [x] MUST NOT read environment variables
  - [x] MUST NOT perform filesystem scanning
  - [x] preserve repeated options as ordered lists; never silently apply last-value-wins
  - [x] support `--` as the deterministic end-of-options marker
  - [x] parse syntax only; descriptor-specific validation belongs to `CommandInputValidator`
  - [x] separates CLI-global options from command-facing options:
    - [x] `--format=<adaptive|json|table|plain>`
    - [x] `--color=<auto|always|never>`
  - [x] preserves both global tokens for their resolvers
  - [x] command-facing `InputInterface::options()` MUST NOT expose recognized CLI-global `format|color` options
  - [x] command-facing `InputInterface::tokens()` and `arguments()` MAY contain literal `--format=...` or `--color=...` tokens when supplied after `--`
  - [x] repeated global options hard-fail
  - [x] empty global option values hard-fail

- [x] `packages/platform/cli/src/Input/CommandInputValidator.php`
  - [x] `public function validate(CommandDescriptor $descriptor, ArgvInput $input): void`
  - [x] receives selected `CommandDescriptor` and parsed `ArgvInput`
  - [x] validates arguments and options before resolving the command service
  - [x] structural descriptor/input mismatches throw `CliInputInvalidException`
  - [x] rejects unknown options
  - [x] rejects missing required option values
  - [x] rejects repeated non-repeatable options
  - [x] rejects unsupported positional arguments
  - [x] validates required/optional/variadic argument counts
  - [x] performs structural validation only
  - [x] validates only structure declared by the selected command descriptor
  - [x] MUST NOT validate Kernel or owner-package domain semantics
  - [x] MUST NOT instantiate the command service
  - [x] `value = none` accepts only a bare boolean flag
  - [x] `value = required` accepts only `--name=value`
  - [x] `value = optional` accepts a bare flag or one scalar value
  - [x] repeatable required-value options must arrive as `list<string>`

Output:
- [x] `packages/platform/cli/src/Output/CommandOutputBuffer.php`
  - [x] implements `OutputInterface`
  - [x] created as a new local instance for one `CliApplication::run()` invocation
  - [x] preserves `text()`, `json()`, and `error()` records in call order
  - [x] MUST NOT write stdout/stderr
  - [x] MUST NOT be a shared container service
  - [x] local per-invocation state does not require `kernel.stateful`
  - [x] `finalize(): CommandOutputBatch`
  - [x] `discard(): void`
  - [x] `discard()` clears every buffered record and leaves the buffer writable
  - [x] `discard()` MUST NOT be called after finalization
  - [x] `discard()` is used only by the `CliApplication` error boundary
  - [x] buffer cannot be written after finalization
  - [x] enforces fixed per-invocation limits:
    - [x] maximum buffered records: `1024`
    - [x] maximum total buffered record bytes: `1048576`
  - [x] counts each normalized record using the byte length of its canonical Foundation `StableJsonEncoder::encodeStable()` representation
  - [x] performs aggregate accounting incrementally when a record is added
  - [x] rejects the next record before insertion when either limit would be exceeded
  - [x] a buffer-limit violation fails closed with one stable CLI-owned safe reason and MUST NOT emit partial buffered output
  - [x] MUST NOT introduce a separate JSON serializer or recursive structural resource-budget walker
  - [x] every `text|json|error` call normalizes immediately
  - [x] normalizes `CRLF|CR` to `LF` in text, error-message, and JSON-like string values only
  - [x] `error()` messages MUST remain non-empty single-line strings after normalization
  - [x] a normalized LF in an error message MUST be rejected before record insertion, not silently removed or replaced
  - [x] permitted LF characters in `text()` and JSON-like string values remain unaffected
  - [x] map keys are never rewritten or line-ending-normalized by CLI
  - [x] rejects NUL and ESC characters
  - [x] normalized string values reject C0/C1 control characters and DEL except normalized LF and TAB
  - [x] map keys reject all C0/C1 control characters and DEL, including CR, LF, and TAB, before shared redaction
  - [x] control-character validation MUST distinguish Unicode code points from raw UTF-8 continuation bytes
  - [x] valid UTF-8 text and map keys, including non-ASCII characters, MUST NOT be rejected merely because their encoded bytes contain values in `0x80..0x9F`
  - [x] malformed UTF-8 fails closed before record insertion through the required Foundation `StableJsonEncoder::encodeStable()` accounting path
  - [x] validates incoming JSON-like values through Foundation `JsonLikeNormalizer` using explicit bounded limits
  - [x] applies CLI-owned line-ending normalization and presentation-specific control-character rejection to string values without introducing map-key rewriting
  - [x] reuses Foundation `JsonLikeNormalizer` after CLI string transformation for final resource validation and deterministic recursive map ordering
  - [x] MUST NOT implement a second JSON-like structural validator, resource-budget walker, recursive map sorter, or map-key collision resolver
  - [x] owner-package output cannot inject raw ANSI sequences

- [x] `packages/platform/cli/src/Output/CommandOutputBatch.php`
  - [x] immutable finalized ordered record list
  - [x] exact record shapes:
    - [x] text:
      - [x] `type = text`
      - [x] `text: string`
    - [x] json:
      - [x] `type = json`
      - [x] `payload: json-like array`
    - [x] error:
      - [x] `type = error`
      - [x] `code: non-empty safe token`
      - [x] `message: non-empty safe single-line string`
  - [x] contains only values normalized through the canonical Foundation json-like boundary
  - [x] relies on Foundation for rejection of floats, objects, resources, closures, Throwables, and invalid map keys
  - [x] preserves the canonical Foundation map ordering and original list and record order
  - [x] contains no streams or formatter state

- [x] `packages/platform/cli/src/Output/FormattedOutput.php`
  - [x] stdout and stderr payloads MUST NOT end with CR or LF
  - [x] final-newline ownership belongs exclusively to `ConsoleOutputWriter`
  - [x] immutable readonly value
  - [x] contains:
    - [x] `stdoutBytes: string`
    - [x] `stderrBytes: string`
  - [x] contains no streams
  - [x] contains no ANSI when effective color is disabled
  - [x] stores stdout and stderr bytes without trailing CR or LF

- [x] `packages/platform/cli/src/Output/ConsoleOutputWriter.php`
  - [x] invocation-local final output sink
  - [x] constructor receives explicit stdout and stderr stream resources
  - [x] canonical API:
    - [x] `public function write(FormattedOutput $output): void`
  - [x] writes only already-formatted bytes
  - [x] owns deterministic final-newline policy
  - [x] writes exactly one final newline for each non-empty stream payload
  - [x] retains no previous output
  - [x] commands MUST NOT receive this service

- [x] `packages/platform/cli/src/Output/FormatResolver.php`
  - [x] `public function resolve(?string $explicitFormat, TerminalCapabilities $capabilities): string`
  - [x] consumes:
    - [x] nullable explicit global format token
    - [x] `CliOutputPolicy`
    - [x] `TerminalCapabilities`
  - [x] precedence:
    - [x] explicit `--format`
    - [x] `cli.output.format_default`
  - [x] when effective token is not `adaptive`, returns it unchanged
  - [x] when effective token is `adaptive`:
    - [x] interactive terminal → configured interactive format
    - [x] non-interactive terminal → configured non-interactive format
  - [x] allowed effective values: `json|table|plain`
  - [x] returns no `adaptive` token after resolution
  - [x] MUST NOT inspect CI environment variables
  - [x] MUST NOT inspect command-owner options
  - [x] MUST NOT read config directly
  - [x] deterministic for the same policy, global token, and terminal capabilities

- [x] `packages/platform/cli/src/Output/ColorResolver.php`
  - [x] `public function resolve(?string $explicitColor, TerminalCapabilities $capabilities, string $effectiveFormat): bool`
  - [x] consumes:
    - [x] nullable explicit global color token
    - [x] `CliOutputPolicy`
    - [x] `TerminalCapabilities`
    - [x] effective output format
  - [x] precedence:
    - [x] explicit `--color`
    - [x] `cli.output.color_default`
  - [x] resolves:
    - [x] `always` → enabled
    - [x] `never` → disabled
    - [x] `auto` → enabled only when output is interactive and ANSI is supported
  - [x] JSON format always forces color disabled
  - [x] MUST NOT read config directly
  - [x] MUST NOT inspect command-owner options
  - [x] MUST NOT expose terminal details to commands
  - [x] returns one boolean effective color decision

- [x] `packages/platform/cli/src/Output/Ansi/AnsiDecorator.php`
  - [x] owns the fixed semantic ANSI mapping
  - [x] semantic roles:
    - [x] heading
    - [x] success
    - [x] warning
    - [x] error
    - [x] muted
  - [x] applies ANSI only when effective color is enabled
  - [x] MUST NOT decorate JSON
  - [x] MUST NOT inspect config
  - [x] MUST NOT expose raw ANSI codes to commands
  - [x] MUST NOT allow user-defined escape sequences
  - [x] MUST NOT add ANSI to redirected output in `auto` mode
  - [x] output with color disabled is byte-stable and escape-free

Output (deterministic + redacted):
- [x] `packages/platform/cli/src/Output/OutputFormatter.php`
  - [x] MUST be a stateless one-call transformer
  - [x] MUST NOT implement `begin|add|flush` accumulation
  - [x] MUST NOT be tagged `kernel.stateful` or `kernel.reset`
  - [x] MUST consume `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` for sensitive output summaries.
  - [x] `packages/platform/cli/src/Output/Redaction/*` MUST NOT exist in this package.
  - [x] `packages/platform/cli/src/Redaction/*` MUST NOT exist in this package.
  - [x] receives effective format and effective color as explicit invocation inputs
  - [x] MUST NOT read CLI config
  - [x] MUST NOT read ContextStore
  - [x] redaction is always applied as defense in depth
  - [x] constructs one immutable `RedactionContext` with scope `cli.output` and mode `RedactionMode::Placeholder`
  - [x] extracts owner-supplied text values, JSON payload values, and error messages from normalized records before shared redaction
  - [x] MUST NOT pass the CLI record envelope or its structural `payload` field into `redactJsonLike()`
  - [x] passes the extracted values through the shared `SensitiveDataRedactorInterface` without introducing CLI-local classification or hashing
  - [x] reconstructs the original CLI record envelopes after redaction, preserving record order and canonical record types
  - [x] converts a structured redaction summary replacing a text or error-message string into one fixed safe placeholder string
  - [x] MUST NOT restore the original value when redaction changes its type or shape
  - [x] MUST NOT redact an already-encoded JSON document
  - [x] relies on the shared redactor for deterministic recursive json-like normalization and map ordering
  - [x] validates only the CLI-owned record envelope and presentation-specific string constraints after redaction
  - [x] preserves list and record order after redaction
  - [x] redaction MUST NOT introduce invalid map keys, floats, objects, resources, or control bytes
  - [x] redaction cannot be disabled by config or command option
  - [x] formatter/redactor failures map to deterministic safe CLI failure
  - [x] redactor failure is wrapped as `RedactionViolationException`
  - [x] concrete formatter failure is wrapped as `CliOutputFormatException`
  - [x] neither wrapper copies the previous Throwable message
  - [x] `RedactionViolationException` contains only:
    - [x] code `CORETSIA_CLI_REDACTION_VIOLATION`
    - [x] reason `output-redaction-failed`
  - [x] `CliOutputFormatException` contains only:
    - [x] code `CORETSIA_CLI_OUTPUT_FORMAT_FAILED`
    - [x] reason `output-format-failed`
  - [x] neither exception copies the previous Throwable message
  - [x] canonical API:
    - [x] `public function format(CommandOutputBatch $batch, ?string $commandName, int $exitCode, string $format, bool $color): FormattedOutput`
  - [x] command name is null for failures that occur before descriptor selection
  - [x] delegates to exactly one concrete formatter
  - [x] JSON output:
    - [x] writes one document to stdout
    - [x] leaves stderr empty
  - [x] plain/table output:
    - [x] text and json-derived records go to stdout
    - [x] error records go to stderr

- [x] `packages/platform/cli/src/Output/Formatter/JsonFormatter.php` — stable schema `schema, meta, data`
  - [x] Stateless formatter: produces deterministic JSON (stable key order, stable schema envelope, no runtime caches).
  - [x] MUST reuse Foundation `StableJsonEncoder::encodeStableMap()` for the complete root JSON object
  - [x] MUST remove exactly the encoder-owned final LF before constructing `FormattedOutput`
  - [x] MUST NOT introduce a separate JSON encoder, recursive key sorter, or serialization policy
  - [x] `ConsoleOutputWriter` remains the sole owner of the final output newline
  - [x] MUST NOT emit ANSI escape sequences
  - [x] MUST NOT emit terminal-width-dependent output
  - [x] MUST ignore effective color even when global color is `always`
  - [x] `meta.command` is `string|null`
  - [x] null is used only when descriptor selection did not complete
  - [x] produces stream payloads without trailing CR or LF

- [x] `packages/platform/cli/src/Output/Formatter/TableFormatter.php` — safe table output
  - [x] Stateless table renderer: no cross-run width/column memory; compute from current payload only.
  - [x] constructor receives configured `maxWidth`
  - [x] honors `cli.output.table.max_width`
  - [x] width handling is deterministic and does not query terminal width
  - [x] MAY use `AnsiDecorator` only when color is enabled
  - [x] visible-width calculation MUST ignore ANSI escape bytes
  - [x] produces stream payloads without trailing CR or LF

- [x] `packages/platform/cli/src/Output/Formatter/PlainFormatter.php` — safe plain output
  - [x] Stateless plain renderer; no hidden global formatting state.
  - [x] MAY use `AnsiDecorator` only when color is enabled
  - [x] color-disabled output is byte-stable
  - [x] produces stream payloads without trailing CR or LF

Output schema:
- [x] `packages/platform/cli/resources/schema/cli_output@1.json`
  - [x] JSON Schema Draft 2020-12 for the CLI output envelope and `text|json|error` record shapes
  - [x] production output pipeline enforces float rejection, sensitive-data redaction, and deterministic JSON serialization
  - [x] Used by `JsonOutputSchemaContractTest.php` as the single source of truth
  - [x] exact top-level shape:
    - [x] `schema = coretsia.cli-output@1`
    - [x] `meta`
    - [x] `data`
  - [x] `meta` exact keys:
    - [x] `command`
    - [x] `outcome`
    - [x] `exit_code`
  - [x] schema requires `outcome` to be consistent with `meta.exit_code`:
    - [x] `0` → `success`
    - [x] non-zero → `failure`
  - [x] `data.records` is an ordered array of structurally validated output records
  - [x] unknown envelope and record-shape keys are rejected; JSON payload maps remain extensible
  - [x] production JSON maps use canonical Foundation `StableJsonEncoder` key ordering

Catalog:
- [x] `packages/platform/cli/src/Catalog/CommandCatalog.php` — builds deterministic catalog from `cli.command` tags
  - [x] is one frozen immutable catalog built from final tag-registry input and validated command overrides
  - [x] contains no mutable cache and requires no reset
  - [x] MUST consume only `ReservedTags::CLI_COMMAND`
  - [x] MUST build descriptors from tag metadata
  - [x] MUST preserve TagRegistry order
  - [x] MUST NOT re-sort discovery output
  - [x] MUST NOT silently de-dupe discovery output
  - [x] duplicate command names across distinct tagged service ids MUST hard-fail with `InvalidCommandTagMetaException`
  - [x] reserved external command name collisions MUST hard-fail:
    - [x] `doctor`
    - [x] `help`
    - [x] `list`
  - [x] reserved names MUST be allowed only for built-in service ids registered by `platform/cli`
  - [x] MUST NOT instantiate command services while building descriptors
  - [x] MUST NOT depend on `platform/worker`
  - [x] MUST NOT use filesystem scanning
  - [x] MUST NOT read `cli.commands` registry list
  - [x] rejects every tagged command whose actual tag priority is not `0`
  - [x] constructor receives:
    - [x] `TagRegistry`
    - [x] `CommandTagSchema`
    - [x] `CommandOverrides`
    - [x] validated `cli.commands.overrides` map
    - [x] immutable reserved built-in service-id map
  - [x] consumes exactly `TagRegistry::all(ReservedTags::CLI_COMMAND)`
  - [x] validates actual priority and metadata for every tagged service
  - [x] builds the complete base descriptor map
  - [x] applies `CommandOverrides` exactly once after base validation
  - [x] canonical API:
    - [x] `public function descriptors(): array`
    - [x] `public function require(string $name): CommandDescriptor`
  - [x] `require()` performs no command service resolution

- [x] `packages/platform/cli/src/Catalog/CommandTagSchema.php`
  - [x] validates `TaggedService::id()` as the exact command class FQCN
  - [x] validates `NAME|SUMMARY|GROUP|HIDDEN|ARGUMENTS|OPTIONS` against the canonicalized tag metadata without resolving the command service
  - [x] validates exact required metadata keys:
    - [x] `name`
    - [x] `summary`
    - [x] `group`
    - [x] `hidden`
    - [x] `arguments`
    - [x] `options`
  - [x] validates canonical command-name regex
  - [x] `group` MUST be a non-empty string matching `\A[a-z][a-z0-9-]*\z`
  - [x] `summary` MUST be non-empty static safe text satisfying canonical Foundation tag-metadata constraints
  - [x] `hidden` MUST be bool
  - [x] rejects `priority` and unknown keys
  - [x] validates ordered argument descriptors:
    - [x] exact keys `name|summary|required|variadic`
    - [x] unique names
    - [x] required before optional
    - [x] variadic only last
  - [x] `arguments` and `options` MUST be lists, not maps
  - [x] argument descriptor:
    - [x] `name` matches `\A[a-z][a-z0-9-]*\z`
    - [x] `summary` is non-empty safe text
    - [x] `required` is bool
    - [x] `variadic` is bool
  - [x] option descriptor:
    - [x] `name` matches `\A[a-z][a-z0-9-]*\z`
    - [x] `summary` is non-empty safe text
    - [x] `value` is `none|required|optional`
    - [x] `repeatable` is bool
  - [x] `repeatable = true` is allowed only with `value = required`
  - [x] metadata contains no default runtime values
  - [x] validates ordered option descriptors:
    - [x] exact keys `name|summary|value|repeatable`
    - [x] `value = none|required|optional`
    - [x] unique names
    - [x] repeated non-repeatable option is invalid
    - [x] `format|color` are the baseline reserved global option names
    - [x] `help`, `mode`, `preset`, and other names remain available to owner packages unless introduced as global options by a separate CLI contract
  - [x] rejects closures, objects, resources, floats, non-json-like metadata, and violations of the canonical static metadata shape and safe-text constraints
  - [x] command owners MUST NOT contribute runtime filesystem path values, runtime endpoints, env values, secrets, tokens, or runtime payloads through static command metadata
  - [x] structural metadata validation MUST NOT be treated as sensitive-data classification or authorization to disclose owner-supplied values
  - [x] MUST NOT introduce a CLI-local sensitive-key classifier, sensitive-value classifier, path classifier, endpoint classifier, or pattern registry
  - [x] does not reject an argument or option merely because its name or summary describes a path
  - [x] throws `InvalidCommandTagMetaException`

- [x] `packages/platform/cli/src/Catalog/CommandOverrides.php`
  - [x] stateless overlay applier
  - [x] constructor has no catalog or config state
  - [x] invalid override structure or value type throws `CliConfigInvalidException`
  - [x] canonical API:
    - [x] `public function apply(array $baseDescriptors, array $overrides): array`
  - [x] returns one immutable overridden descriptor map
  - [x] validates every dynamic override key against canonical command-name regex
  - [x] rejects an override for an unknown command
  - [x] validates each override as a map
  - [x] allows exactly `summary|hidden|group`
  - [x] `summary` must be a non-empty safe string
  - [x] `hidden` must be bool
  - [x] `group` must match the same `\A[a-z][a-z0-9-]*\z` token schema as base command metadata
  - [x] group overrides MUST NOT introduce a separate group namespace, naming policy, or normalization algorithm
  - [x] unknown fields hard-fail deterministically
  - [x] cannot change:
    - [x] command name
    - [x] service id
    - [x] arguments
    - [x] options
    - [x] base tag metadata
  - [x] cannot introduce commands or aliases
  - [x] produces an immutable catalog view

- [x] `packages/platform/cli/src/Catalog/CommandDescriptor.php` — canonical immutable command descriptor
  - [x] Stateless immutable DTO (readonly). No derived caches; safe to share.
  - [x] MUST be internal CLI catalog DTO built from tag metadata
  - [x] MUST NOT be imported by external packages
  - [x] MUST include:
    - [x] service id
    - [x] name
    - [x] summary
    - [x] group
    - [x] hidden
    - [x] arguments
    - [x] options
  - [x] MUST be immutable / readonly
  - [x] MUST NOT contain command object instance
  - [x] MUST NOT contain closures
  - [x] MUST NOT contain unvalidated configuration values, secrets, runtime filesystem paths, endpoints, or runtime payloads
  - [x] MAY contain the validated catalog-view `summary|hidden|group` values produced by `CommandOverrides`
  - [x] MUST NOT retain the raw `cli.commands.overrides` configuration map
  - [x] MUST NOT expose the original override payload or bypass the canonical override-field validation

Kernel operation request resolver:
- [x] `packages/platform/cli/src/Kernel/KernelOpsRequestResolver.php`
  - [x] `public function resolve(InputInterface $input): KernelOpsRequest`
  - [x] invalid scalar target shape throws `CliInputInvalidException`
  - [x] is used only by built-in Kernel operation commands
  - [x] consumes only the already structurally validated `InputInterface`
  - [x] requires the `target` option to be present exactly once as a non-empty scalar value
  - [x] validates only non-empty safe token shape
  - [x] rejects an absent `--target` inside command dispatch before invoking `KernelOpsInterface`
  - [x] the descriptor's `value = required` requires a value when the option is supplied; it does not independently require option presence
  - [x] MUST NOT introduce a second required-option-presence validator or extend the generic option metadata schema solely for Kernel targets
  - [x] constructs `KernelOpsRequest(appTarget)`
  - [x] relies on `CommandInputValidator` and the selected descriptor to reject undeclared, missing-value, or repeated options before command service resolution
  - [x] does not import or receive `CommandDescriptor`
  - [x] does not import Kernel `AppTarget`
  - [x] does not load Bootstrap configuration or mode presets
  - [x] does not inspect artifacts or `current`
  - [x] semantic target validation belongs to Kernel Ops

Runner + diagnostics:
- [x] `packages/platform/cli/src/Runner/CommandRunner.php`
  - [x] `public function run(CommandDescriptor $descriptor, InputInterface $input, OutputInterface $output, string $outputFormat): int`
  - [x] service resolution and exit-code validation occur inside the UoW callback
  - [x] invalid exit code throws before `KernelRuntimeInterface::runUnitOfWork()` returns
  - [x] resolves the selected command through `ContainerInterface`
  - [x] executes `CommandInterface::run()` through `KernelRuntimeInterface`
  - [x] on Throwable:
    - [x] records only safe failure observability
    - [x] allows `KernelRuntimeInterface` to complete after/reset lifecycle
    - [x] rethrows the original Throwable
  - [x] MUST NOT depend on `ErrorHandlerInterface`
  - [x] MUST NOT depend on `ExceptionRenderer`
  - [x] MUST NOT render command failures
  - [x] MUST resolve command service only after catalog selects a descriptor
  - [x] resolved service MUST implement `CommandInterface`
  - [x] the resolved object's actual class MUST equal `CommandDescriptor::serviceId()` exactly
  - [x] a container binding returning a different command class, subclass, proxy, or decorator under the tagged service id MUST fail closed
  - [x] resolved command `name()` MUST equal descriptor name
  - [x] class-identity or command-name mismatch MUST throw `InvalidCommandTagMetaException`
  - [x] MUST NOT introduce a second service registry, command-owner lookup, or provider-origin resolver
  - [x] MUST pass parsed `InputInterface` to command
  - [x] MUST pass `OutputInterface` to command
  - [x] MUST NOT let commands parse raw argv through platform-specific classes
  - [x] MUST execute runtime commands inside kernel UoW wrapper
  - [x] MUST NOT instantiate all commands for `list` / `help`
  - [x] preserves the integer exit code returned by an owner-package command
  - [x] validates that the returned exit code is within `0..255`
  - [x] invalid return codes fail through `CliCommandFailedException`
  - [x] MUST NOT remap a valid owner-package exit code
  - [x] built-in Kernel operation commands perform their `OpsResult` mapping inside their own command implementation
  - [x] constructor receives:
    - [x] `KernelRuntimeInterface`
    - [x] `ContextAccessorInterface`
    - [x] `TracerPortInterface`
    - [x] `MeterPortInterface`
    - [x] `LoggerInterface`
    - [x] `Psr\Container\ContainerInterface`
    - [x] Foundation `Stopwatch`
  - [x] resolves only `CommandDescriptor::serviceId()` through `ContainerInterface`
  - [x] resolution happens only after:
    - [x] catalog selection
    - [x] global-option separation
    - [x] descriptor-driven input validation
  - [x] runner resolves only the selected `ListCommand` or `HelpCommand`
  - [x] those commands render descriptors without resolving any other command service
  - [x] MUST NOT enumerate container services
  - [x] MUST NOT resolve all tagged command services eagerly
  - [x] executes one selected command through one canonical UoW:
    - [x] UoW type: `cli`
    - [x] safe operation id: selected descriptor command name
    - [x] safe UoW attribute: effective output format
  - [x] MUST NOT:
    - [x] write ContextStore directly
    - [x] create correlation id
    - [x] create UoW id
    - [x] invoke hooks directly
    - [x] invoke reset orchestration directly
    - [x] enumerate reset tags
    - [x] pass raw argv/options/arguments into UoW attributes
  - [x] context reads for optional command-log correlation occur only inside the UoW callback, after KernelRuntime has written base context keys and before KernelRuntime resets ContextStore
  - [x] reads only through `ContextAccessorInterface::has()` and `get()`:
    - [x] `ContextKeys::CORRELATION_ID`
    - [x] `ContextKeys::UOW_ID`
  - [x] includes only non-empty safe identifier strings in the optional `correlation_id|uow_id` log fields
  - [x] missing, invalid, or unreadable context identifiers are omitted without changing command execution or its exit code
  - [x] MUST NOT read `ContextKeys::UOW_TYPE` merely to revalidate the already-selected `UnitOfWorkType::CLI`
  - [x] MUST NOT attempt to retrieve command UoW identifiers from ContextStore after `runUnitOfWork()` returns or throws
  - [x] MUST NOT copy the complete context, UoW attributes, or hook payloads into logs, spans, metrics, or diagnostics
  - [x] calls:
    - [x] `KernelRuntimeInterface::runUnitOfWork(UnitOfWorkType::CLI, ...)`
    - [x] attributes contain exactly `operation|output_format`
  - [x] MUST NOT write UoW attributes into ContextStore directly
  - [x] owns the `cli.command` execution-observability boundary independently of Kernel-owned `kernel.uow` and `kernel.operation` telemetry
  - [x] attempts exactly one `TracerPortInterface::startSpan('cli.command', ...)` per selected normal command execution
  - [x] starts the command span before entering `KernelRuntimeInterface::runUnitOfWork()`
  - [x] the command span covers the complete Kernel UoW invocation, including its after-hook and reset lifecycle
  - [x] initial span attributes contain only:
    - [x] `operation` = selected descriptor command name
    - [x] `format` = effective `json|table|plain`
  - [x] after UoW completion or failure, attempts one final span attribute update containing:
    - [x] `operation`
    - [x] `format`
    - [x] `outcome` = `success|failure`
    - [x] `exit_code` = returned process exit code or `1` for a thrown failure
  - [x] attempts `SpanInterface::end()` exactly once for a successfully started span, even when attribute updates fail
  - [x] a failed span start MUST NOT prevent Kernel UoW execution or trigger another span-start attempt
  - [x] MUST NOT use `TracerPortInterface::inSpan()` in a way that can suppress or repeat command execution when tracing fails
  - [x] uses Foundation `Stopwatch::start()` and `stop()` to measure the complete command UoW duration
  - [x] stopwatch failures are isolated and use a non-negative integer duration fallback of `0`
  - [x] attempts exactly one `MeterPortInterface::increment('cli.command_total', 1, $labels)` per normal command execution
  - [x] attempts exactly one `MeterPortInterface::observe('cli.command_duration_ms', $durationMs, $labels)` per normal command execution
  - [x] metric labels contain exactly `operation|outcome`
  - [x] counter and duration observation failures are isolated independently
  - [x] attempts one safe command completion/failure log through `LoggerInterface`
  - [x] command log fields contain only `operation|outcome|exit_code`, with optional previously captured safe `correlation_id|uow_id`
  - [x] every tracer, span, meter, logger, stopwatch, and optional context-read failure is isolated from the primary command result or Throwable
  - [x] command observability MUST NOT resolve or invoke `KernelOpsFacade`, Kernel observability internals, UoW hooks, or reset orchestration directly

- [x] `packages/platform/cli/src/Diagnostics/CliErrorHandler.php`
  - [x] implements `ErrorHandlerInterface`
  - [x] canonical API:
    - [x] `public function handle(Throwable $throwable, ?ErrorHandlingContext $context = null): ErrorDescriptor`
  - [x] uses only safe `operation|correlationId` context fields when present
  - [x] returned descriptor extensions are empty in the baseline
  - [x] converts known CLI and Kernel port exceptions into format-neutral `ErrorDescriptor`
  - [x] known mappings use only stable public codes and fixed safe messages
  - [x] known mappings include:
    - [x] `CliInputInvalidException`
    - [x] `CliConfigInvalidException`
    - [x] `InvalidCommandTagMetaException`
    - [x] `CliCommandFailedException`
    - [x] `KernelOpsFailedException`
  - [x] unknown Throwable maps to:
    - [x] code `CORETSIA_CLI_INTERNAL_ERROR`
    - [x] fixed safe message
    - [x] no raw extensions
  - [x] MUST NOT copy:
    - [x] Throwable message or class
    - [x] Throwable file or line
    - [x] stack trace
    - [x] previous Throwable
    - [x] filesystem path
    - [x] argv or option values
  - [x] performs no rendering, logging, redaction, or stream writes

- [x] `packages/platform/cli/src/Diagnostics/ExceptionRenderer.php`
  - [x] consumes `ErrorDescriptor`, not raw Throwable
  - [x] calls `OutputInterface::error($descriptor->code(), $descriptor->message())`
  - [x] baseline CLI output does not render:
    - [x] HTTP status
    - [x] severity internals
    - [x] arbitrary descriptor extensions
  - [x] MUST NOT include or render Throwable message, class, file, line, stack trace, previous Throwable, or filesystem path metadata

Errors:
- [x] `packages/platform/cli/src/Exception/CliInputInvalidException.php`
  - [x] code `CORETSIA_CLI_INPUT_INVALID`
  - [x] code-first deterministic public message
  - [x] exposes only stable safe reason tokens
  - [x] used by `ArgvInputParser`, `CommandInputValidator`, catalog selection, and `KernelOpsRequestResolver`
  - [x] MUST NOT contain argv values, option values, paths, or previous Throwable messages

- [x] `packages/platform/cli/src/Exception/CliBootstrapException.php`
  - [x] code `CORETSIA_CLI_BOOTSTRAP_FAILED`
  - [x] code-first deterministic public message
  - [x] exposes only stable safe reason tokens
  - [x] used after Composer autoload by `CliEntrypointPathsResolver`
  - [x] MUST NOT contain launcher paths, autoload paths, skeleton paths, or previous Throwable messages

- [x] `packages/platform/cli/src/Exception/InvalidCommandTagMetaException.php`
  - [x] code `CORETSIA_CLI_INVALID_COMMAND_META`
  - [x] Stateless schema/registry violation exception
  - [x] error details limited to names/serviceIds (no secrets/paths)

- [x] `packages/platform/cli/src/Exception/RedactionViolationException.php`
  - [x] Stateless redaction policy violation exception
  - [x] exposes only code `CORETSIA_CLI_REDACTION_VIOLATION`
  - [x] exposes fixed reason `output-redaction-failed`
  - [x] contains no raw value, hash, length, path, or previous Throwable message

- [x] `packages/platform/cli/src/Exception/CliOutputFormatException.php`
  - [x] code `CORETSIA_CLI_OUTPUT_FORMAT_FAILED`
  - [x] fixed reason `output-format-failed`
  - [x] contains no rendered bytes, raw records, paths, or previous message

- [x] `packages/platform/cli/src/Exception/CliCommandFailedException.php`
  - [x] code `CORETSIA_CLI_COMMAND_FAILED`
  - [x] code-first deterministic public message
  - [x] exposes fixed safe reason token
  - [x] previous throwable message is never included
  - [x] no dependency on deleted `CliException` or `ErrorCodes`

- [x] `packages/platform/cli/src/Exception/CliConfigInvalidException.php`
  - [x] code `CORETSIA_CLI_CONFIG_INVALID`
  - [x] code-first deterministic public message
  - [x] exposes only stable safe reason tokens
  - [x] used by `CliOutputPolicy`, `CliServiceFactory`, and `CommandOverrides`
  - [x] MUST NOT include raw config values, dynamic override values, paths, or previous Throwable messages
  - [x] no dependency on deleted `CliException` or `ErrorCodes`

Built-in commands:
- [x] `packages/platform/cli/src/Command/DoctorCommand.php` — ultra-early checks (no kernel boot)
  - [x] Stateless orchestrator; any per-run diagnostics collection MUST be local (if extracted into a service collector → that collector becomes resettable).
  - [x] public constructor requires no services
  - [x] performs only fixed allowlisted environment-capability checks
  - [x] MUST NOT read application config, dotenv values, modules, Composer installed metadata, or generated artifacts
  - [x] `NAME = doctor`
  - [x] `GROUP = core`
  - [x] `HIDDEN = false`
  - [x] `ARGUMENTS = []`
  - [x] `OPTIONS = []`
  - [x] `SUMMARY = 'Check CLI bootstrap and runtime prerequisites.'`

- [x] `packages/platform/cli/src/Command/DebugModulesCommand.php`
  - [x] constructs exactly one `KernelOpsRequest` and performs exactly one matching call directly through `KernelOpsInterface`
  - [x] delegates exactly one `debugModules()` operation
  - [x] passes exactly one explicit app target through `KernelOpsRequest`
  - [x] MUST NOT resolve `ModulePlan`, read Composer metadata, or construct `ModuleResolution` directly
  - [x] MUST NOT receive or render provider class names.
  - [x] `KernelOpsInterface` and `OpsResult` MUST NOT expose provider instances, provider class lists, or raw Composer provider metadata.
  - [x] stateless; no memoization of manifest, module plan, or provider plan across runs
  - [x] `NAME = debug:modules`
  - [x] `GROUP = debug`
  - [x] `HIDDEN = false`
  - [x] `ARGUMENTS = []`
  - [x] `OPTIONS` contains exactly one descriptor:
    - [x] `name = target`
    - [x] `value = required`
    - [x] `repeatable = false`
    - [x] `summary = Application target: web, api, console, or worker.`
  - [x] `SUMMARY = 'Show the resolved module plan for the configured target preset.'`
  - [x] a handled error with null preset MUST NOT render a synthetic preset value

- [x] `packages/platform/cli/src/Command/ConfigValidateCommand.php`
  - [x] constructs exactly one `KernelOpsRequest` and performs exactly one matching call directly through `KernelOpsInterface`
  - [x] passes exactly one explicit app target through `KernelOpsRequest`
  - [x] consumes only the returned safe `OpsResult`
  - [x] MUST NOT resolve ConfigKernel or module services directly
  - [x] stateless; no cached validation result
  - [x] `NAME = config:validate`
  - [x] `GROUP = config`
  - [x] `HIDDEN = false`
  - [x] `ARGUMENTS = []`
  - [x] `OPTIONS` contains exactly one descriptor:
    - [x] `name = target`
    - [x] `value = required`
    - [x] `repeatable = false`
    - [x] `summary = Application target: web, api, console, or worker.`
  - [x] `SUMMARY = 'Validate configuration for the configured target preset.'`
  - [x] a handled error with null preset MUST NOT render a synthetic preset value

- [x] `packages/platform/cli/src/Command/ConfigDebugCommand.php`
  - [x] constructs exactly one `KernelOpsRequest` and performs exactly one matching call directly through `KernelOpsInterface`
  - [x] passes exactly one explicit app target through `KernelOpsRequest`
  - [x] consumes only safe explain metadata returned in `OpsResult`
  - [x] MUST NOT receive raw config or env values
  - [x] MUST NOT run ConfigKernel or redaction over raw Kernel values locally
  - [x] stateless; no cached explain traces
  - [x] `NAME = config:debug`
  - [x] `GROUP = config`
  - [x] `HIDDEN = false`
  - [x] `ARGUMENTS = []`
  - [x] `OPTIONS` contains exactly one descriptor:
    - [x] `name = target`
    - [x] `value = required`
    - [x] `repeatable = false`
    - [x] `summary = Application target: web, api, console, or worker.`
  - [x] `SUMMARY = 'Show safe configuration resolution diagnostics for the configured target preset.'`
  - [x] a handled error with null preset MUST NOT render a synthetic preset value

- [x] `packages/platform/cli/src/Command/ConfigCompileCommand.php`
  - [x] constructs exactly one `KernelOpsRequest` and performs exactly one matching call directly through `KernelOpsInterface`
  - [x] passes exactly one explicit app target through `KernelOpsRequest`
  - [x] MUST NOT call `ModulePlanResolver::resolve()` or `ModulePlanResolver::resolveResolution()`
  - [x] MUST NOT read Composer metadata
  - [x] MUST NOT resolve or construct `ContainerProviderPlan`
  - [x] MUST NOT collect container definitions
  - [x] MUST NOT invoke `ArtifactCompiler` directly
  - [x] stateless; no CLI-owned module, provider, artifact, or cache state
  - [x] constructs exactly one `KernelOpsRequest`
  - [x] makes exactly one `compileConfig()` call
  - [x] renders:
    - [x] app target
    - [x] effective preset only when non-null
    - [x] published generation id
    - [x] four canonical artifact identities and basenames
  - [x] MUST NOT render synthetic `generations/current/<basename>` paths
  - [x] MUST NOT read `current` after the Kernel call
  - [x] MUST NOT verify or boot the published runtime locally
  - [x] `ConfigCompileCommand::SUMMARY` = 'Compile and atomically publish one runtime artifact generation.'
  - [x] `NAME = config:compile`
  - [x] `GROUP = config`
  - [x] `HIDDEN = false`
  - [x] `ARGUMENTS = []`
  - [x] `OPTIONS` contains exactly one descriptor:
    - [x] `name = target`
    - [x] `value = required`
    - [x] `repeatable = false`
    - [x] `summary = Application target: web, api, console, or worker.`
  - [x] a handled error with null preset MUST NOT render a synthetic preset value

- [x] `packages/platform/cli/src/Command/ConfigHashCommand.php`
  - [x] constructs exactly one `KernelOpsRequest` and performs exactly one matching call directly through `KernelOpsInterface`
  - [x] passes exactly one explicit app target through `KernelOpsRequest`
  - [x] MUST NOT resolve modules or calculate fingerprints locally
  - [x] MUST NOT invoke `FingerprintCalculator` directly
  - [x] renders only the safe expected generation-id result returned by Kernel
  - [x] stateless; no manifest, module-plan, provider-plan, or fingerprint cache
  - [x] renders the returned value as `generation_id`
  - [x] help/summary MUST state that the command calculates the expected generation ID
  - [x] MUST state that it does not write artifacts and does not inspect `current`
  - [x] `ConfigHashCommand::SUMMARY` = 'Calculate the expected graph-bound artifact generation ID for the configured target preset.'
  - [x] `NAME = config:hash`
  - [x] `GROUP = config`
  - [x] `HIDDEN = false`
  - [x] `ARGUMENTS = []`
  - [x] `OPTIONS` contains exactly one descriptor:
    - [x] `name = target`
    - [x] `value = required`
    - [x] `repeatable = false`
    - [x] `summary = Application target: web, api, console, or worker.`
  - [x] a handled error with null preset MUST NOT render a synthetic preset value

- [x] `packages/platform/cli/src/Command/CacheVerifyCommand.php`
  - [x] constructs exactly one `KernelOpsRequest` and performs exactly one matching call directly through `KernelOpsInterface`
  - [x] passes exactly one explicit app target through `KernelOpsRequest`
  - [x] MUST NOT call `ModulePlanResolver::resolve()` or `ModulePlanResolver::resolveResolution()`
  - [x] MUST NOT read Composer metadata
  - [x] MUST NOT construct or resolve `ContainerProviderPlan`
  - [x] MUST NOT invoke `CacheVerifier` directly
  - [x] renders Kernel-provided generation verification state:
    - [x] `clean`
    - [x] `dirty`
    - [x] `invalid`
  - [x] renders expected and nullable current generation ids
  - [x] renders all four artifact statuses and safe reasons
  - [x] renders byte counts when provided
  - [x] MUST NOT receive or render filesystem paths from lower-level CacheVerifier results
  - [x] stateless; no local manifest, module plan, provider plan, cache state, or last verification outcome
  - [x] `CacheVerifyCommand::SUMMARY` = 'Verify the current artifact generation against expected inputs.'
  - [x] `NAME = cache:verify`
  - [x] `GROUP = cache`
  - [x] `HIDDEN = false`
  - [x] `ARGUMENTS = []`
  - [x] `OPTIONS` contains exactly one descriptor:
    - [x] `name = target`
    - [x] `value = required`
    - [x] `repeatable = false`
    - [x] `summary = Application target: web, api, console, or worker.`
  - [x] a handled error with null preset MUST NOT render a synthetic preset value

- [x] `packages/platform/cli/src/Command/HelpCommand.php`
  - [x] existing Phase-0 implementation is replaced completely
  - [x] receives `CommandCatalog`
  - [x] renders general help from descriptors without resolving command services
  - [x] renders command-specific arguments and options from metadata
  - [x] unknown command fails deterministically
  - [x] exposes canonical command constants
  - [x] does not receive `list<string>` command names
  - [x] contains no generic “help unavailable” Phase-0 fallback
  - [x] does not reference Phase 0
  - [x] `NAME = help`
  - [x] `GROUP = core`
  - [x] `HIDDEN = false`
  - [x] `ARGUMENTS` contains exactly:
    - [x] `name = command`
    - [x] `summary = Command name.`
    - [x] `required = false`
    - [x] `variadic = false`
  - [x] `OPTIONS = []`
  - [x] `SUMMARY = 'Show general or command-specific help.'`

- [x] `packages/platform/cli/src/Command/ListCommand.php`
  - [x] existing Phase-0 implementation is replaced completely
  - [x] receives `CommandCatalog`
  - [x] renders descriptors without resolving command services
  - [x] excludes hidden commands
  - [x] groups deterministically while preserving canonical descriptor order within each group
  - [x] uses command metadata summaries
  - [x] exposes canonical command constants
  - [x] does not receive `list<string>` command names
  - [x] does not synthesize built-ins locally
  - [x] does not reference Phase 0
  - [x] `NAME = list`
  - [x] `GROUP = core`
  - [x] `HIDDEN = false`
  - [x] `ARGUMENTS = []`
  - [x] `OPTIONS = []`
  - [x] `SUMMARY = 'List available commands.'`

Docs:
- [x] `docs/adr/ADR-0034-cli-tag-first-command-catalog.md`
  - [x] MUST capture:
    - [x] tag-first discovery via `cli.command`
    - [x] reserved built-in command names (`doctor`, `help`, `list`)
    - [x] kernel ops consumption only through `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
    - [x] deterministic output + redaction policy

#### Modifies

- [x] `.github/split-publish-packages.json`
  - [x] add `platform/cli` to the canonical split-publish allowlist
  - [x] preserve `platform/redaction` as a separately publishable runtime dependency
  - [x] preserve all existing allowlisted packages and package-id ordering
  - [x] `composer package-publish-safety:gate` MUST pass

- [x] `packages/core/kernel/resources/modes/micro.php`
  - [x] preserve `required = [core.foundation, core.kernel]`
  - [x] set `modules = [platform.cli]`
  - [x] `platform.redaction` is enabled through the declared `platform.cli` module dependency

- [x] `packages/core/kernel/resources/modes/express.php`
  - [x] preserve `required = [core.foundation, core.kernel]`
  - [x] set `modules = [platform.cli]`
  - [x] `platform.redaction` is enabled through the declared `platform.cli` module dependency

- [x] `packages/core/kernel/resources/modes/hybrid.php`
  - [x] preserve `required = [core.foundation, core.kernel]`
  - [x] set `modules = [platform.cli, platform.worker]`
  - [x] preserve the existing Worker module selection
  - [x] `platform.redaction` is enabled through the declared `platform.cli` module dependency

- [x] `packages/core/kernel/resources/modes/enterprise.php`
  - [x] preserve `required = [core.foundation, core.kernel]`
  - [x] set `modules = [platform.cli, platform.worker]`
  - [x] preserve the existing Worker module selection
  - [x] `platform.redaction` is enabled through the declared `platform.cli` module dependency

- [x] `packages/framework/composer.json`
  - [x] preserve `coretsia/core-kernel` as the explicit baseline Kernel dependency
  - [x] add `coretsia/platform-cli` as a direct baseline dependency
  - [x] use the canonical release-line `publicConstraint` for both dependencies
  - [x] do not duplicate `coretsia/platform-redaction` as a direct framework dependency; it is required transitively by `coretsia/platform-cli`
  - [x] preserve Composer type `metapackage`
  - [x] do not introduce runtime code, configuration, autoload definitions, or installation scripts

- [x] `packages/framework/README.md`
  - [x] document the baseline Composer dependency graph
  - [x] document `coretsia/core-kernel` and `coretsia/platform-cli` as explicit framework requirements
  - [x] document `coretsia/platform-redaction` as a transitive dependency of CLI
  - [x] distinguish physical Composer installation from Kernel runtime module selection
  - [x] preserve explicit DependencySync ownership of additional mode-required packages
  - [x] do not describe the framework metapackage as a runtime module or module-selection mechanism

- [x] `packages/applications/skeleton/README.md`
  - [x] document the updated framework baseline containing Kernel and CLI
  - [x] document the canonical Skeleton CLI invocation:
    - [x] `php coretsia list`
    - [x] `php coretsia help`
    - [x] `php coretsia doctor`
    - [x] `php coretsia config:validate --target=web`
    - [x] explain that the project-root `coretsia` is a thin wrapper around the installed Composer binary
    - [x] document `php vendor/bin/coretsia <command>` as the direct equivalent invocation
    - [x] distinguish the Skeleton project-root wrapper from the monorepo-root wrapper
    - [x] document the fixed safe bootstrap failure when the Composer binary is unavailable
    - [x] do not require an additional CLI installation, alias, or configuration step after baseline Composer installation
  - [x] document Redaction as the transitive CLI runtime dependency
  - [x] preserve `coretsia/framework` as the only project-owned Coretsia baseline root
  - [x] document `micro` as the default canonical preset
  - [x] distinguish baseline package installation from Console ModulePlan selection
  - [x] preserve explicit dependency synchronization for additional selected targets and modules
  - [x] update canonical multi-target examples to include the CLI and Redaction module dependency closure
  - [x] do not introduce automatic DependencySync execution during create-project or runtime boot

- [x] `coretsia` — complete rewrite
  - [x] repository-root wrapper
  - [x] derives the repository application root exclusively from `__DIR__`
  - [x] supplies the explicit repository application root through `$GLOBALS['coretsia_application_root']`
  - [x] delegates Composer autoload selection to the packaged CLI executable
  - [x] MUST NOT use `packages/applications/skeleton` as the monorepo application root
  - [x] delegates to `packages/platform/cli/bin/coretsia`
  - [x] is independent of current working directory
  - [x] performs no command parsing or rendering

- [x] `packages/core/contracts/src/Cli/Input/InputInterface.php`
  - [x] method signatures remain unchanged
  - [x] document that `tokens()` exposes command-facing tokens only
  - [x] global `format|color` options MUST NOT reach owner-package commands

- [x] `packages/platform/cli/src/Module/CliModule.php` — complete rewrite
  - [x] follows the canonical module metadata shape
  - [x] constants:
    - [x] `MODULE_ID = 'platform.cli'`
    - [x] `PACKAGE_ID = 'platform/cli'`
    - [x] `COMPOSER_PACKAGE = 'coretsia/platform-cli'`
    - [x] canonical `KIND`
    - [x] `CONFIG_ROOT = 'cli'`
  - [x] instance methods:
    - [x] `id()`
    - [x] `packageId()`
    - [x] `composerPackage()`
    - [x] `kind()`
    - [x] `configRoot()`
    - [x] `providers()`
  - [x] `providers()` returns `CliServiceProvider::class`
  - [x] no static-only Phase-0 module API remains
  - [x] no config reads, command discovery, boot logic, or output logic

- [x] `packages/platform/cli/src/Provider/CliServiceFactory.php`
  - [x] stateless construction/wiring helper
  - [x] MUST NOT keep caches, output buffers, terminal state, current command, or last result
  - [x] reads CLI configuration only from the already-merged and validated `ConfigRepositoryInterface`
  - [x] every factory method receiving `ConfigRepositoryInterface` for CLI configuration reads the complete `cli` root through `cliConfigRoot()` exactly once
  - [x] factory methods receiving an already-constructed `CliOutputPolicy` MUST reuse that policy without reading `ConfigRepositoryInterface` again
  - [x] factory methods receiving the already-extracted `cli.commands.overrides` map MUST NOT re-read configuration
  - [x] no factory method reads individual `cli.*` paths
  - [x] MUST NOT introduce a second configuration cache, defaults source, merge pipeline, or validation pipeline
  - [x] canonical private helper:
    - [x] `/** @return array<string, mixed> */`
    - [x] `private static function cliConfigRoot(ConfigRepositoryInterface $config): array`
  - [x] `cliConfigRoot()`:
    - [x] requires `ConfigRepositoryInterface::has('cli')`
    - [x] reads only `ConfigRepositoryInterface::get('cli')`
    - [x] requires a string-keyed map
    - [x] rejects a list
    - [x] converts repository access failures into one deterministic safe container/config failure
    - [x] performs no defaults fallback
    - [x] performs no config file reads
  - [x] extracts the canonical CLI settings from the validated root:
    - [x] `commands.overrides` → structurally validated override map passed to `CommandCatalog`
    - [x] `output.format_default` → `CliOutputPolicy::formatDefault`
    - [x] `output.adaptive.interactive` → `CliOutputPolicy::interactiveFormat`
    - [x] `output.adaptive.non_interactive` → `CliOutputPolicy::nonInteractiveFormat`
    - [x] `output.color_default` → `CliOutputPolicy::colorDefault`
    - [x] `output.table.max_width` → `CliOutputPolicy::tableMaxWidth`
  - [x] constructs `CliOutputPolicy` using all five output settings without silently inventing defaults
  - [x] constructs `FormatResolver` using the policy's format default and both adaptive formats
  - [x] constructs `ColorResolver` using the policy's color default
  - [x] constructs `TableFormatter` using the policy's table maximum width
  - [x] passes the override map to `CommandCatalog`, which delegates dynamic key and value validation to `CommandOverrides::apply()`
  - [x] MUST NOT validate dynamic command names or override values in `CliServiceFactory`
  - [x] constructs one stateless `CommandOverrides` without config or catalog state
  - [x] package defaults remain owned exclusively by `config/cli.php`
  - [x] schema validation remains owned by `config/rules.php` and the existing ConfigKernel pipeline
  - [x] `CliServiceFactory` performs only defensive shape assertions required for safe construction
  - [x] MUST NOT read:
    - [x] `cli.enabled`
    - [x] `cli.uow.*`
    - [x] `cli.redaction.*`
    - [x] `cli.observability.*`
    - [x] raw env values
    - [x] raw argv
    - [x] terminal state
  - [x] constructs immutable `CliOutputPolicy`
  - [x] passes the validated `cli.commands.overrides` map to `CommandCatalog`
  - [x] `CommandCatalog` supplies that map to `CommandOverrides::apply()` exactly once
  - [x] constructs `FormatResolver` from `CliOutputPolicy`
  - [x] constructs `ColorResolver` from `CliOutputPolicy`
  - [x] constructs `TableFormatter` with configured `maxWidth`
  - [x] injects `SensitiveDataRedactorInterface` into `OutputFormatter`
  - [x] injects canonical Kernel runtime, observability, context, logger, and stopwatch dependencies into `CommandRunner`
  - [x] injects into `CommandRunner`:
    - [x] `KernelRuntimeInterface`
    - [x] `ContextAccessorInterface`
    - [x] `TracerPortInterface`
    - [x] `MeterPortInterface`
    - [x] `LoggerInterface`
    - [x] Foundation `Stopwatch`
  - [x] injects `ContainerInterface` into `CommandRunner`
  - [x] injects every constructor dependency declared by `CliApplication`
  - [x] specifically injects:
    - [x] `ArgvInputParser`
    - [x] `CommandCatalog`
    - [x] `CommandInputValidator`
    - [x] `FormatResolver`
    - [x] `ColorResolver`
    - [x] `CommandRunner`
    - [x] `CliErrorHandler` under its concrete service id
    - [x] `ExceptionRenderer`
    - [x] `OutputFormatter`
  - [x] MUST NOT make command execution conditional on observability availability
  - [x] MUST NOT silently invent defaults outside `config/cli.php`
  - [x] wires the package-owned `CliErrorHandler`
  - [x] MUST NOT instantiate logger, tracer, meter, or redactor implementations directly
  - [x] MUST NOT resolve command services during construction
  - [x] MUST NOT write stdout/stderr

- [x] `packages/platform/worker/src/Console/WorkerStartCommand.php`
  - [x] remove `public const string MODE`
  - [x] preserve `NAME|SUMMARY|GROUP|HIDDEN|ARGUMENTS|OPTIONS`
  - [x] no dependency on `platform/cli`

- [x] `packages/platform/worker/src/Console/WorkerStopCommand.php`
  - [x] remove `public const string MODE`
  - [x] preserve `NAME|SUMMARY|GROUP|HIDDEN|ARGUMENTS|OPTIONS`
  - [x] no dependency on `platform/cli`

- [x] `packages/platform/worker/src/Console/WorkerStatusCommand.php`
  - [x] remove `public const string MODE`
  - [x] preserve `NAME|SUMMARY|GROUP|HIDDEN|ARGUMENTS|OPTIONS`
  - [x] no dependency on `platform/cli`

- [x] `packages/platform/worker/src/Console/WorkerHealthCommand.php`
  - [x] remove `public const string MODE`
  - [x] preserve `NAME|SUMMARY|GROUP|HIDDEN|ARGUMENTS|OPTIONS`
  - [x] no dependency on `platform/cli`

- [x] `packages/platform/worker/src/Provider/WorkerServiceProvider.php`
  - [x] remove the `mode` argument from `commandMeta(...)`
  - [x] remove the `mode` return-shape field
  - [x] remove `'mode' => $mode`
  - [x] stop passing `Worker*Command::MODE`
  - [x] emitted command metadata contains exactly:
    - [x] `name`
    - [x] `summary`
    - [x] `group`
    - [x] `hidden`
    - [x] `arguments`
    - [x] `options`
  - [x] retain `ReservedTags::CLI_COMMAND`
  - [x] retain no compile-time dependency on `platform/cli`

- [x] `packages/core/contracts/src/Cli/Command/CommandInterface.php`
  - [x] preserve existing methods:
    - [x] `name(): string`
    - [x] `run(InputInterface $input, OutputInterface $output): int`
  - [x] tagged command classes MUST expose:
    - [x] `public const string NAME`
    - [x] `public const string SUMMARY`
    - [x] `public const string GROUP`
    - [x] `public const bool HIDDEN`
    - [x] `public const array ARGUMENTS`
    - [x] `public const array OPTIONS`
  - [x] remove any documentation requirement for `MODE`
  - [x] `name()` MUST return `self::NAME`
  - [x] portable process exit code range is `0..255`
  - [x] contract remains independent of `platform/cli`

- [x] `packages/platform/cli/composer.json` — complete rewrite of Phase-0 metadata
  - [x] remove description claims:
    - [x] `config-based command registry`
    - [x] `kernel-free in Phase 0`
  - [x] declare direct production Composer requirements for PHP source dependencies and runtime-only module installation:
    - [x] `php: ^8.4`
    - [x] `coretsia/core-contracts`: current release-line `publicConstraint`
    - [x] `coretsia/core-foundation`: current release-line `publicConstraint`
    - [x] `coretsia/core-kernel`: current release-line `publicConstraint`
    - [x] `coretsia/platform-redaction`: current release-line `publicConstraint` (runtime-only installation dependency; no PHP source permission)
    - [x] `psr/container: ^2.0`
    - [x] `psr/log: ^3.0`
  - [x] internal Coretsia constraints MUST follow the canonical `tools/release/release-line.json` `publicConstraint` and MUST NOT use an independently pinned historical minor version
  - [x] declare `"bin": ["bin/coretsia"]`
  - [x] preserve PSR-4 package namespace
  - [x] preserve:
    - [x] `moduleId`
    - [x] `moduleClass`
    - [x] `providers`
    - [x] `defaultsConfigPath`
  - [x] set `extra.coretsia.requires` exactly to:
    - [x] `core.kernel`
    - [x] `platform.redaction`
  - [x] MUST NOT require:
    - [x] `platform/worker`
    - [x] migration/database packages
    - [x] integrations solely for command discovery
  - [x] require `coretsia/platform-redaction` at the Composer level to guarantee installation of the declared runtime module dependency
  - [x] production source MUST consume only `SensitiveDataRedactorInterface` and MUST NOT import or instantiate `DefaultSensitiveDataRedactor`
  - [x] Composer package installation MUST NOT implicitly register or enable runtime modules

- [x] `packages/platform/cli/src/Provider/CliServiceProvider.php`
  - [x] existing placeholder Phase-0 provider is replaced completely
  - [x] legacy `id()` and static `factories()` placeholder API are removed
  - [x] implements:
    - [x] `ServiceProviderInterface`
    - [x] `ContainerDefinitionProviderInterface`
  - [x] follows the existing Kernel provider split:
    - [x] source-host-only wiring remains in `register()`
    - [x] runtime-representable generic CLI wiring is declared in `define()`
  - [x] `register()`:
    - [x] registers `KernelOpsRequestResolver` as a source-host-only stateless service
    - [x] registers the six Kernel operation command services with direct constructor dependencies on `KernelOpsInterface` and `KernelOpsRequestResolver`
    - [x] registers the six Kernel operation `cli.command` tags
    - [x] MUST NOT duplicate generic CLI services declared by `define()`
    - [x] delegates the runtime-representable contribution through:
      - [x] `$builder->registerDefinitionProvider($this)`
  - [x] `define()` is the single definition source for generic CLI infrastructure:
    - [x] generic CLI services constructed through static `CliServiceFactory` methods using canonical `ContainerDefinitionBuilder::classMethodFactory()`
    - [x] parser and validated input services
    - [x] `CommandCatalog`
    - [x] output policy, resolvers, and formatters
    - [x] `CommandRunner`
    - [x] `CliApplication`
    - [x] `CliErrorHandler`
    - [x] `ExceptionRenderer`
    - [x] `HelpCommand`
    - [x] `ListCommand`
    - [x] `DoctorCommand`
    - [x] their generic built-in `cli.command` tags
  - [x] `CliServiceFactory` is a static construction helper and MUST NOT be registered or instantiated as a container service
  - [x] constructor-free static factory dispatch MUST reuse Foundation declarative service-method and class-method factory primitives
  - [x] `CommandOutputBuffer` MUST NOT be registered by `register()` or `define()`
  - [x] `CliApplication` creates exactly one invocation-local `CommandOutputBuffer` per `run()` call
  - [x] `define()` MUST NOT reference or require:
    - [x] `KernelOpsInterface`
    - [x] `ConfigValidateCommand`
    - [x] `ConfigDebugCommand`
    - [x] `ConfigCompileCommand`
    - [x] `ConfigHashCommand`
    - [x] `CacheVerifyCommand`
    - [x] `DebugModulesCommand`
    - [x] `KernelOpsHostInput`
    - [x] `KernelOpsHostBooter`
  - [x] generic CLI infrastructure MAY enter canonical runtime definitions
  - [x] source-host-only Kernel operation commands MUST NOT enter canonical runtime definitions
  - [x] no second provider-discovery or imperative-provider planning mechanism is introduced
  - [x] MUST NOT import Kernel host booters or concrete Kernel Ops implementations
  - [x] consumes Kernel operations only through `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
  - [x] MUST register app/runner/catalog/services
  - [x] MUST NOT bind or implement that interface inside `platform/cli`
  - [x] MUST register built-in command services
  - [x] MUST tag all built-in commands with `ReservedTags::CLI_COMMAND`
  - [x] MUST tag built-in commands using command class constants:
    - [x] `NAME`
    - [x] `SUMMARY`
    - [x] `GROUP`
    - [x] `HIDDEN`
    - [x] `ARGUMENTS`
    - [x] `OPTIONS`
  - [x] MUST NOT invent built-in command names as unrelated string literals
  - [x] MUST NOT build `CommandCatalog` during provider registration
  - [x] MUST NOT instantiate command services during provider registration
  - [x] MUST NOT parse CLI input during provider registration
  - [x] MUST NOT inspect runtime command options during provider registration
  - [x] MUST NOT use filesystem scanning
  - [x] MUST NOT read `cli.commands` registry list
  - [x] MUST provide reserved built-in command service id map to catalog/factory:
    - [x] `doctor`
    - [x] `help`
    - [x] `list`
  - [x] source-host-only Kernel Ops dependencies:
    - [x] `KernelOpsInterface` is consumed only by the six source-host Kernel operation command services
    - [x] `KernelOpsRequestResolver` is registered only in `register()`
    - [x] these dependencies MUST NOT enter `define()` or its declarative `requireService()` set
    - [x] source-host command factories resolve the existing `KernelOpsInterface` binding without registering another implementation
  - [x] runtime-representable generic CLI dependencies:
    - [x] `KernelRuntimeInterface`
    - [x] `ContextAccessorInterface`
    - [x] `TracerPortInterface`
    - [x] `MeterPortInterface`
    - [x] `LoggerInterface`
    - [x] `SensitiveDataRedactorInterface`
    - [x] `ConfigRepositoryInterface`
    - [x] `Psr\Container\ContainerInterface`
    - [x] `TagRegistry`
    - [x] Foundation `Stopwatch`
    - [x] `define()` declares required generic service references through the canonical `ContainerDefinitionBuilder` where appropriate
    - [x] MUST NOT introduce a second dependency preflight or service-availability registry
    - [x] the canonical Foundation container remains responsible for service-reference resolution and failure
  - [x] configuration ownership:
    - [x] passes the same validated Console-host `ConfigRepositoryInterface` to `CliServiceFactory`
    - [x] MUST NOT construct another config repository
    - [x] MUST NOT load `config/cli.php` or `config/rules.php` directly
    - [x] MUST NOT expose CLI config values through command input
  - [x] registers `CliOutputPolicy`
  - [x] registers `FormatResolver`
  - [x] registers `ColorResolver`
  - [x] registers fixed ANSI decorator
  - [x] registers formatter services with explicit dependencies
  - [x] defines `CliErrorHandler` under its concrete service id through `define()`
  - [x] defines `ExceptionRenderer` through `define()`
  - [x] MUST NOT bind the global `ErrorHandlerInterface` service id to a CLI-specific implementation
  - [x] source-host wiring contains no config reads during provider registration
  - [x] all config reads remain in `CliServiceFactory`

- [x] `packages/platform/cli/config/cli.php`
  - [x] returns the `cli` subtree only
  - [x] MUST NOT repeat the root as `['cli' => ...]`
  - [x] contains only deterministic scalar/map/list defaults
  - [x] contains no closures, objects, resources, floats, env reads, terminal detection, or runtime values
  - [x] canonical dot keys:
    - [x] `cli.commands.overrides` = []
      - [x] this node permits dynamic map keys structurally
      - [x] `additionalKeys = true` applies only at this node
      - [x] dynamic key and value validation is outside config defaults and belongs to `CommandOverrides`
    - [x] `cli.output.format_default` = "adaptive"
    - [x] `cli.output.adaptive.interactive` = "table"
    - [x] `cli.output.adaptive.non_interactive` = "plain"
    - [x] `cli.output.color_default` = "auto"
    - [x] `cli.output.table.max_width` = 120
  - [x] MUST NOT contain:
    - [x] `cli.enabled`
    - [x] `cli.commands` as a registry list
    - [x] `cli.mode.*`
    - [x] `cli.uow.*`
    - [x] `cli.redaction.*`
    - [x] `cli.output.redaction.*`
    - [x] `cli.observability.*`
    - [x] `cli.output.colors.*`
    - [x] `cli.output.palette.*`

- [x] `packages/platform/cli/config/rules.php`
  - [x] returns a plain declarative ruleset array
  - [x] validates the `cli` subtree with `additionalKeys = false`
  - [x] `cli.commands`
    - [x] required map
    - [x] `additionalKeys = false`
    - [x] exact key: `overrides`
  - [x] `cli.commands.overrides`
    - [x] required map
    - [x] `additionalKeys = true`
    - [x] `ConfigValidator` validates only that the value is a map
    - [x] declarative rules leave dynamic map-key and value validation to `CommandOverrides`
    - [x] rules MUST NOT pretend to validate arbitrary command-name keys through undeclared static `keys`
  - [x] `cli.output`
    - [x] required map
    - [x] `additionalKeys = false`
    - [x] exact keys:
      - [x] `format_default`
      - [x] `adaptive`
      - [x] `color_default`
      - [x] `table`
  - [x] `cli.output.format_default`
    - [x] required string
    - [x] `allowedValues = adaptive|json|table|plain`
  - [x] `cli.output.adaptive`
    - [x] required map
    - [x] `additionalKeys = false`
    - [x] exact keys: `interactive|non_interactive`
  - [x] `cli.output.adaptive.interactive`
    - [x] required string
    - [x] `allowedValues = json|table|plain`
  - [x] `cli.output.adaptive.non_interactive`
    - [x] required string
    - [x] `allowedValues = json|table|plain`
  - [x] `cli.output.color_default`
    - [x] required string
    - [x] `allowedValues = auto|always|never`
  - [x] `cli.output.table`
    - [x] required map
    - [x] `additionalKeys = false`
    - [x] exact key: `max_width`
  - [x] `cli.output.table.max_width`
    - [x] required int
    - [x] `min = 40`
    - [x] `max = 240`
  - [x] schema rejects:
    - [x] registry-list `cli.commands`
    - [x] `cli.enabled`
    - [x] `cli.mode.*`
    - [x] `cli.uow.*`
    - [x] `cli.redaction.*`
    - [x] `cli.output.redaction.*`
    - [x] `cli.observability.*`
    - [x] color palettes or arbitrary ANSI values
    - [x] unknown static keys at every schema-owned level
    - [x] dynamic keys under `cli.commands.overrides` are the only structural exception

- [x] `packages/platform/cli/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — complete rewrite
  - [x] replace the Phase-0 static `CliModule::providers()` call with instance-based module metadata verification
  - [x] remove assertions for deleted `CliServiceProvider::id()` and `CliServiceProvider::factories()` APIs
  - [x] verify `CliServiceProvider` implements `ServiceProviderInterface` and `ContainerDefinitionProviderInterface`
  - [x] verify `CliModule::providers()` returns the canonical `CliServiceProvider::class` through the module instance
  - [x] preserve output-free loading assertions for `config/cli.php` and `config/rules.php`
  - [x] replace legacy `cli.commands` list assertions with the canonical `cli.commands.overrides` map shape
  - [x] replace legacy `cli.output.format` assertions with `cli.output.format_default` and the `adaptive|json|table|plain` allowed-value set
  - [x] verify the canonical adaptive, color, and table-width config nodes
  - [x] remove all Phase-0 `MODE`, static-provider, command-registry, and configurable-redaction assumptions

- [x] `packages/platform/cli/tests/Contract/CommandsDoNotWriteToStdoutTest.php` — complete rewrite
  - [x] scans every production command under `src/Command`
  - [x] rejects:
    - [x] `echo`
    - [x] `print`
    - [x] `printf`
    - [x] `var_dump`
    - [x] `print_r`
    - [x] `error_log`
    - [x] direct `STDOUT|STDERR`
    - [x] `php://stdout|php://stderr|php://output`
  - [x] excludes tests and fixtures
  - [x] does not forbid writes inside the explicit `ConsoleOutputWriter`

- [x] `packages/core/kernel/tests/Support/AppBuilder.php`
  - [x] extend the existing test manifest and provider fixtures with the CLI-to-Redaction dependency closure
  - [x] reuse the canonical Kernel module resolver without introducing an alternate test-only module planner

- [x] `packages/core/kernel/tests/Integration/BootMicroPresetTest.php`
  - [x] update the expected enabled module set for the canonical `micro` preset
  - [x] verify CLI and Redaction dependency closure without weakening compiled-artifact validation

- [x] `packages/core/kernel/tests/Integration/BootExpressPresetTest.php`
  - [x] update the expected enabled module set for the canonical `express` preset
  - [x] preserve deterministic ModulePlan and artifact assertions

- [x] `packages/core/kernel/tests/Integration/ModePresetSchemaValidatorEnforcesMicroAndExpressRulesTest.php`
  - [x] update canonical preset module expectations
  - [x] preserve required/modules disjointness and schema validation
  - [x] verify Worker remains selected only by the intended canonical presets

- [x] `packages/core/kernel/tests/Unit/ProjectPackagePlannerTest.php`
  - [x] update assertions affected by the canonical preset closure
  - [x] preserve independent per-target ModulePlan and project-wide Composer union semantics

- [x] `packages/core/kernel/tests/Unit/Config/ConfigValidatorRejectsInvalidCliCommandsTest.php`
  - [x] replace legacy `cli.commands` list validation cases with the canonical `cli.commands.overrides` map shape
  - [x] verify rejection of invalid static `commands|overrides` node types
  - [x] preserve exact structural violation-path, reason, expected-type, and actual-type assertions
  - [x] leave dynamic command-name and override-value validation to `CommandOverrides`

- [x] `packages/core/kernel/tests/Unit/Config/ConfigValidatorRejectsInvalidCliOutputFormatTest.php`
  - [x] replace legacy `cli.output.format` assertions with `cli.output.format_default`
  - [x] preserve wrong-type and unsupported-value validation coverage
  - [x] verify the canonical `adaptive|json|table|plain` allowed-value set

- [x] `packages/core/kernel/tests/Unit/Config/ConfigValidatorDiagnosticsAreSafeAndDeterministicTest.php`
  - [x] replace legacy command-list, `output.format`, and `output.redaction.enabled` fixtures with invalid values under the new CLI config schema
  - [x] update exact expected violation paths and deterministic ordering
  - [x] preserve assertions that rejected config values, secrets, and filesystem paths never appear in diagnostics

  - [x] `packages/platform/worker/tests/Contract/WorkerCommandMetadataConstantsTest.php`
    - [x] remove all `MODE` assertions
    - [x] assert exactly `NAME|SUMMARY|GROUP|HIDDEN|ARGUMENTS|OPTIONS`
    - [x] assert arrays and scalar metadata types

  - [x] `packages/platform/worker/tests/Contract/WorkerServiceProviderCliCommandTaggingTest.php`
    - [x] remove `mode` from expected metadata shape
    - [x] assert exact six-key metadata
    - [x] preserve `cli.command` tag assertions

  - [x] `packages/platform/worker/tests/Integration/WorkerProviderSourceDefinitionsParityTest.php`
    - [x] remove `MODE` arguments and `mode` expected fields
    - [x] preserve source/definition metadata parity for the final six-key schema

- [x] `packages/platform/worker/tests/Integration/WorkerCompileArtifactBootE2ETest.php`
  - [x] remove `mode` from the expected `commandMetadata()` return shape
  - [x] remove `$commandClass::MODE` access from the metadata fixture
  - [x] preserve exact six-key `cli.command` metadata parity across source and compiled artifact boot
  - [x] preserve the existing Worker-only custom preset, artifact compilation, verification, and runtime boot assertions
  - [x] MUST NOT add `platform.cli` or `platform.redaction` as mandatory roots to the Worker-only test preset
  - [x] MUST NOT weaken the canonical artifact and deterministic tag-order assertions

- [x] `docs/ssot/tags.md`
  - [x] define that every `cli.command` service id is the exact command class FQCN
  - [x] define lazy command-constant validation without service resolution
  - [x] preserve owner `platform/cli` for `cli.command`
  - [x] define exact metadata keys:
    - [x] `name`
    - [x] `summary`
    - [x] `group`
    - [x] `hidden`
    - [x] `arguments`
    - [x] `options`
  - [x] forbid:
    - [x] `priority`
    - [x] `mode`
    - [x] unknown metadata keys
  - [x] define deterministic TagRegistry order
  - [x] define duplicate command-name failure across distinct service ids, while preserving TagRegistry first-wins behavior for repeated registrations of the same service id
  - [x] define reserved external names `doctor|help|list`
  - [x] document that command-owner packages depend only on contracts-level CLI ports
  - [x] `cli.command` registrations use actual tag priority `0`
  - [x] non-zero priority is invalid independently of metadata contents

- [x] `docs/ssot/observability.md`
  - [x] register span `cli.command`
  - [x] register counter `cli.command_total`
  - [x] register observation `cli.command_duration_ms`
  - [x] owner is `platform/cli`
  - [x] labels are exactly `operation|outcome`
  - [x] `operation` is the canonical command name from the selected descriptor
  - [x] allowed outcomes are `success|failure`
  - [x] `format|exit_code|app_target|preset|correlation_id|uow_id` are forbidden metric labels
  - [x] `format|exit_code` may be bounded span attributes
  - [x] raw arguments, options, output records, paths, endpoints, config, and payloads are forbidden

- [x] `docs/ssot/modes.md`
  - [x] document `platform.cli` as a default selectable module in all four canonical presets
  - [x] document `platform.redaction` as a transitive runtime module dependency of CLI
  - [x] preserve `required` versus `modules` exclusion semantics
  - [x] preserve per-target module selection and deterministic dependency closure
  - [x] clarify that canonical presets are runtime policies, not Composer package distributions
  - [x] update examples of the effective canonical module sets

- [x] `docs/ssot/application-dependency-sync.md`
  - [x] document the updated Kernel and CLI baseline distribution
  - [x] update the Composer baseline dependency graph and transitive Redaction installation
  - [x] update canonical `micro` and multi-target ModulePlan examples
  - [x] preserve explicit target selection, Composer root ownership, and plan/review/apply semantics
  - [x] preserve the separation between physically installed packages and enabled runtime modules
  - [x] do not introduce implicit dependency synchronization during application boot

- [x] `packages/platform/cli/README.md` MUST include:
  - [x] existing README is replaced completely
  - [x] remove historical descriptions:
    - [x] Phase 0
    - [x] kernel-free CLI
    - [x] config-based command registry
    - [x] FQCN command lists
    - [x] zero-argument command constructors
    - [x] package-local redaction
    - [x] monorepo-only launcher layout
  - [x] Errors and exit codes
  - [x] Canonical CLI invocation:
    - [x] `coretsia` is the single canonical executable name
    - [x] document `php coretsia <command>` for the monorepo root
    - [x] document `php coretsia <command>` for Composer-installed Coretsia Skeleton projects
    - [x] document `php vendor/bin/coretsia <command>` as the direct Composer binary invocation
    - [x] document `vendor/bin/coretsia <command>` where the Composer binary is directly executable
    - [x] distinguish the monorepo-root wrapper, Skeleton project-root wrapper, Composer binary proxy, and packaged CLI implementation
    - [x] explain that the Skeleton wrapper forwards without performing application boot or command parsing
    - [x] do not introduce or document framework-owned `c|cli` executable aliases
    - [x] all supported invocations use the same packaged CLI implementation
  - [x] Security and redaction
  - [x] Kernel target and configured-preset behavior
  - [x] Determinism
  - [x] Providing commands from another package
  - [x] Owner-package arguments, options, services, and domain validation
  - [x] Configuration:
    - [x] exact default shape
    - [x] config consumers
    - [x] global-option precedence
    - [x] adaptive format behavior
    - [x] color behavior
    - [x] no configurable redaction/UoW/observability toggles
  - [x] Context and UoW:
    - [x] one UoW per normal command
    - [x] doctor exception
    - [x] context read/write ownership
    - [x] reset ownership
  - [x] Observability:
    - [x] span and metric names
    - [x] allowed labels and attributes
    - [x] failure isolation
  - [x] Colors:
    - [x] `auto|always|never`
    - [x] JSON is ANSI-free
    - [x] fixed internal semantic palette
    - [x] no arbitrary configured ANSI codes

- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0034-cli-tag-first-command-catalog.md`

- [x] `docs/architecture/DEPENDENCIES.md`
  - [x] update the existing `platform/cli` row in the canonical direct PHP source dependency matrix
  - [x] exact `platform/cli` `depends_on` value:
    - [x] `core/contracts, core/foundation, core/kernel`
  - [x] `platform/redaction` MUST NOT be added to the `platform/cli` source dependency row
  - [x] preserve the distinction between direct PHP source permissions and runtime-only Composer installation dependencies
  - [x] `coretsia/platform-redaction` remains a direct production Composer requirement of `coretsia/platform-cli`, backed by the declared `platform.redaction` runtime module dependency
  - [x] runtime-only Composer installation edges MUST NOT grant PHP source-level dependency permissions
  - [x] `platform/cli` production source MUST consume redaction only through `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` and MUST NOT import concrete `Coretsia\Platform\Redaction\*` implementation classes
  - [x] preserve canonical `strcmp` package-row ordering
  - [x] generated Deptrac ruleset for `platform.cli` MUST allow only `core.contracts`, `core.foundation`, and `core.kernel`
  - [x] generated Deptrac ruleset MUST NOT allow `platform.redaction` as a PHP source dependency of `platform.cli`
  - [x] preserve validation through the existing `composer arch` pipeline without introducing a new gate, allowlist, or dependency SSoT

### Cross-cutting (MUST)

#### Context & UoW

- [x] Normal command UoW:
  - [x] every normal command executes through exactly one `KernelRuntimeInterface` UoW
  - [x] canonical UoW type is `cli`
  - [x] UoW begins after descriptor validation and before lazy command resolution
  - [x] command resolution, command-name verification, execution, and exit-code validation occur inside the same UoW callback
  - [x] one command invocation MUST NOT create nested CLI UoWs
  - [x] `doctor` is the only pre-host and pre-UoW command path
- [x] Safe UoW attributes supplied by `CommandRunner`:
  - [x] canonical command operation id
  - [x] effective CLI output format
  - [x] no raw argv
  - [x] no positional argument values
  - [x] no option values
  - [x] no output records
  - [x] no filesystem paths
  - [x] no endpoints
  - [x] no payloads or secrets
- [x] Context writes:
  - [x] platform/cli performs no direct `ContextStore` writes
  - [x] `CommandRunner` passes safe UoW inputs to `KernelRuntimeInterface`
  - [x] KernelRuntime owns base ContextStore writes:
    - [x] `ContextKeys::CORRELATION_ID`
    - [x] `ContextKeys::UOW_ID`
    - [x] `ContextKeys::UOW_TYPE`
  - [x] `cli_output_format` is not introduced as a ContextStore key
  - [x] `CommandRunner` passes the following safe UoW attributes:
    - [x] `operation` = canonical command name
    - [x] `output_format` = resolved `json|table|plain`
  - [x] UoW attributes remain lifecycle/hook payload data and MUST NOT be written into ContextStore by platform/cli
- [x] Context reads:
  - [x] platform/cli reads runtime context only through `ContextAccessorInterface`
  - [x] `CommandRunner` consumes the following Kernel-owned values only for optional safe log correlation:
    - [x] `ContextKeys::CORRELATION_ID`
    - [x] `ContextKeys::UOW_ID`
  - [x] `ContextKeys::UOW_TYPE` is written and owned by KernelRuntime; `CommandRunner` does not need to read it because the UoW type is explicitly supplied as `UnitOfWorkType::CLI`
  - [x] optional identifiers MUST be captured inside the UoW callback before the canonical KernelRuntime reset
  - [x] missing, invalid, or unavailable identifiers MUST be omitted from CLI logs
  - [x] failures of optional context reads MUST NOT alter command execution, UoW cleanup, exit codes, or primary exception precedence
  - [x] `output_format` is supplied explicitly to `CommandRunner` and passed as a UoW attribute; it is not read from ContextAccessorInterface
  - [x] CLI MUST NOT create or write additional ContextStore keys
  - [x] commands and formatters MUST NOT require these values to produce their domain result
- [x] Reset discipline:
  - [x] reset is triggered only by the canonical KernelRuntime lifecycle
  - [x] platform/cli MUST NOT enumerate `kernel.reset`
  - [x] platform/cli MUST NOT call `ResetOrchestrator` directly
  - [x] platform/cli introduces no shared mutable service in the baseline
  - [x] `CommandOutputBuffer` is invocation-local and is not a container singleton
  - [x] immutable catalog, descriptor, and output-policy objects do not require reset
  - [x] any future shared mutable service must implement `ResetInterface` and satisfy canonical stateful-service policy

#### Observability (policy-compliant)

- [x] Ownership:
  - [x] `CommandRunner` owns CLI command execution observability
  - [x] `KernelOpsFacade` owns Kernel operation observability
  - [x] built-in Kernel operation commands and CLI transport services MUST NOT emit duplicate Kernel-operation spans or metrics
  - [x] formatters and writers MUST NOT emit command lifecycle metrics
- [x] Command span:
  - [x] name: `cli.command`
  - [x] exactly one span-start attempt per selected normal command execution
  - [x] the span begins before the Kernel UoW call and finishes after its complete lifecycle
  - [x] a successfully started span MUST be ended exactly once
  - [x] failed tracing MUST NOT prevent command execution, alter exit codes, or trigger command retries
  - [x] safe attributes:
    - [x] `operation`
    - [x] `outcome`
    - [x] `format`
    - [x] `exit_code`
  - [x] `operation` is the canonical command name
  - [x] `exit_code` is a span attribute only
  - [x] no arguments, options, paths, endpoints, payloads, or config values
- [x] Command metrics:
  - [x] `cli.command_total`
    - [x] labels: `operation|outcome`
  - [x] `cli.command_duration_ms`
    - [x] labels: `operation|outcome`
  - [x] duration measured through Foundation `Stopwatch`
  - [x] metric values use integer milliseconds
  - [x] `format|exit_code|app_target|preset|correlation_id|uow_id` are forbidden metric labels
- [x] Canonical outcomes:
  - [x] thrown exception uses canonical process exit code `1` for span and log attributes
  - [x] exit code `0` → `success`
  - [x] non-zero returned exit code → `failure`
  - [x] thrown exception → `failure`
  - [x] CLI command outcomes are based on the returned process exit code or thrown Throwable, not on Kernel UoW lifecycle outcome
  - [x] a valid non-zero command return is a CLI command `failure` even when KernelRuntime records successful completion of the UoW callback
  - [x] CLI MUST NOT override Kernel-owned UoW outcomes or emit corrective `kernel.uow` telemetry
- [x] Logs:
  - [x] one safe command completion/failure summary
  - [x] safe fields:
    - [x] `operation`
    - [x] `outcome`
    - [x] `exit_code`
  - [x] MAY include `correlation_id|uow_id`
  - [x] MUST NOT include raw argv, arguments, option values, command output, paths, endpoints, payloads, env/config values, tokens, exception stack traces, or previous throwable messages
- [x] Failure isolation:
  - [x] tracer start, attribute-update, and span-end failures MUST NOT alter command exit code
  - [x] meter counter and duration-observation failures MUST NOT alter command exit code
  - [x] logger failure MUST NOT alter command exit code
  - [x] Foundation Stopwatch start/stop failures MUST NOT alter command exit code
  - [x] optional ContextAccessorInterface read failures MUST NOT alter command exit code
  - [x] an unavailable duration uses integer `0`
  - [x] observability failures MUST NOT prevent or repeat command execution
  - [x] observability failures MUST NOT replace the primary command exception
  - [x] observability failures MUST NOT change Kernel-owned after-hook, reset, or failure-precedence semantics
  - [x] failed observability attempts MUST NOT trigger recursive reporting through the same failing port
- [x] Doctor:
  - [x] ultra-early doctor does not require tracer, meter, logger, context, or UoW services
  - [x] doctor emits no normal `cli.command` runtime span
  - [x] doctor diagnostics are safe by construction through a fixed allowlist
  - [x] ultra-early doctor MUST NOT invoke `SensitiveDataRedactorInterface`

### Security / Redaction (MUST)

- [x] Redaction is mandatory:
  - [x] every normal command output passes through mandatory defense-in-depth redaction
  - [x] ultra-early doctor is the sole exception and is safe by construction
  - [x] no config key disables it
  - [x] no global option disables it
  - [x] no owner-package command can bypass final CLI defense-in-depth redaction
  - [x] `OutputFormatter` always receives `SensitiveDataRedactorInterface`
  - [x] owner-package commands MUST produce safe-by-construction output before CLI redaction
  - [x] known-sensitive values MUST be omitted, safely derived, or explicitly classified by their owning producer
  - [x] a shared redaction classifier non-match MUST NOT be treated as authorization to emit raw values
  - [x] successful redaction MUST NOT widen CLI-owned output schemas or authorize arbitrary diagnostic metadata
  - [x] non-placeholder hash and length disclosure modes are not enabled by the baseline CLI output boundary
- [x] CLI MUST NOT leak:
  - [x] dotenv or env values
  - [x] raw config values
  - [x] tokens
  - [x] authorization headers
  - [x] cookies or session ids
  - [x] raw SQL
  - [x] payloads
  - [x] filesystem paths unless explicitly owner-approved output contract permits a safe relative path
  - [x] exception stack traces by default
- [x] Allowed diagnostics:
  - [x] stable reason tokens
  - [x] bounded operation ids
  - [x] integer counts and lengths
  - [x] owner-approved safe hash derivations only where non-reconstruction requirements are satisfied
  - [x] correlation and UoW ids where policy permits
- [x] Color safety:
  - [x] config cannot contain arbitrary ANSI escape sequences
  - [x] JSON output is always ANSI-free
  - [x] owner-package output values MUST NOT be interpreted as ANSI control sequences

### Tests (MUST)

- Test fixtures:
  - [x] `packages/platform/cli/tests/Fixture/ExternalCommand/ExternalOwnerService.php`
  - [x] `packages/platform/cli/tests/Fixture/ExternalCommand/ExternalModeCommand.php`
    - [x] implements `CommandInterface`
    - [x] declares owner-domain `mode` option
    - [x] declares an additional repeatable required-value `item` option
    - [x] receives repeated `item` values through contracts-level `InputInterface`
    - [x] preserves the received `list<string>` order for test assertions
    - [x] depends on `ExternalOwnerService`
    - [x] performs no Kernel preset selection or Kernel Ops invocation

  - [x] `packages/platform/cli/tests/Fixture/ExternalCommand/ExternalCommandServiceProvider.php`
    - [x] implements `ServiceProviderInterface` and `ContainerDefinitionProviderInterface`
    - [x] `register()` delegates through the canonical `ContainerBuilder::registerDefinitionProvider($this)` mechanism
    - [x] `define()` contributes `ExternalOwnerService` and `ExternalModeCommand` through canonical declarative container definitions
    - [x] tags `ExternalModeCommand::class` with `ReservedTags::CLI_COMMAND`
    - [x] uses the exact command class FQCN as its service id
    - [x] references `NAME|SUMMARY|GROUP|HIDDEN|ARGUMENTS|OPTIONS` command constants
    - [x] does not instantiate the command during provider registration or definition production
    - [x] does not introduce imperative-only provider registration, a second command registry, or a test-only command discovery mechanism

  - [x] `packages/platform/cli/tests/Fixture/ExternalCommand/ReservedHelpCommand.php`
    - [x] contributes external name `help` for deterministic collision testing

- Unit:
  - [x] `packages/platform/cli/tests/Unit/ParsedCliInvocationTest.php`
    - [x] contains exactly one parsed `ArgvInput` and nullable global `formatOverride|colorOverride`
    - [x] preserves command-facing arguments and options without reconstructing raw argv
    - [x] global `format|color` tokens are absent from the contained command input
    - [x] is immutable and retains no streams, catalog, config repository, descriptor, or command service

  - [x] `packages/platform/cli/tests/Unit/CommandCatalogRejectsNonZeroTagPriorityTest.php`
    - [x] actual non-zero `TaggedService::priority()` hard-fails with `InvalidCommandTagMetaException`
    - [x] both positive and negative non-zero priorities are rejected
    - [x] a forbidden metadata `priority` key is not required to trigger rejection
    - [x] priority `0` remains valid
    - [x] the catalog neither overwrites priority nor re-sorts TagRegistry output
    - [x] no command service is instantiated during validation

  - [x] `packages/platform/cli/tests/Unit/CommandOutputBufferRejectsAnsiAndControlBytesTest.php`
    - [x] NUL, ESC, ANSI sequences, forbidden C0/C1 characters, and DEL in string values fail before record insertion
    - [x] CRLF and CR in permitted string values normalize to LF
    - [x] LF and TAB remain permitted in text and JSON-like string values
    - [x] error messages remain safe single-line strings
    - [x] valid non-ASCII UTF-8 values are not rejected because of UTF-8 continuation bytes
    - [x] malformed UTF-8 fails closed
    - [x] rejected values do not produce partial output or disclose offending content

  - [x] `packages/platform/cli/tests/Unit/CliEntrypointPathsResolverTest.php`
    - [x] resolves the application root from a valid canonical Composer binary layout
    - [x] resolves the application root from an explicit canonical monorepo wrapper input
    - [x] validates and normalizes the already-selected readable Composer autoload path
    - [x] conflicting application-root sources fail with `CliBootstrapException`
    - [x] unsupported or unresolved launcher layouts fail deterministically
    - [x] results do not depend on the current working directory
    - [x] performs no recursive parent-directory scanning or application-config loading
    - [x] failure diagnostics disclose no absolute paths or raw launcher values

  - [x] `packages/platform/cli/tests/Unit/CommandRunnerResolvesOnlySelectedServiceTest.php`
    - [x] resolves exactly the selected `CommandDescriptor::serviceId()` through `ContainerInterface`
    - [x] never enumerates container services or resolves unrelated tagged commands
    - [x] command construction happens inside the Kernel UoW callback
    - [x] passes the command-facing `InputInterface` and invocation-local `OutputInterface` unchanged
    - [x] rejects a resolved service that does not implement `CommandInterface`
    - [x] rejects a different command class returned under the selected descriptor's service id, even when it implements `CommandInterface` and returns the expected command name
    - [x] rejects a subclass or decorator returned under a tagged command class service id
    - [x] accepts the exact command class registered under its own FQCN
    - [x] rejects a resolved command whose `name()` differs from the selected descriptor
    - [x] unrelated command constructors are never invoked

  - [x] `packages/platform/cli/tests/Unit/CommandTagSchemaTest.php`
    - [x] accepts the canonical exact six-key metadata schema
    - [x] validates the canonical `group` token pattern
    - [x] rejects empty, uppercase, whitespace-containing, and control-character-containing groups
    - [x] requires a non-empty safe summary and boolean hidden flag
    - [x] validates argument descriptors with exact `name|summary|required|variadic` keys
    - [x] rejects duplicate argument names, required arguments after optional arguments, and non-final variadic arguments
    - [x] validates option descriptors with exact `name|summary|value|repeatable` keys
    - [x] rejects duplicate option names and invalid `none|required|optional` value declarations
    - [x] permits `repeatable = true` only with `value = required`
    - [x] rejects reserved CLI-global option names `format|color`
    - [x] permits owner-domain option names such as `mode|preset`
    - [x] rejects non-json-like metadata and invalid static safe-text shapes
    - [x] does not introduce CLI-local sensitive-value, path, or endpoint classification
    - [x] leaves priority-key, unknown-key, name-regex, and class-constant mismatch cases to their dedicated tests

  - [x] `packages/platform/cli/tests/Unit/CommandCatalogDeterminismTest.php`
    - [x] identical canonical TagRegistry input produces identical descriptor order and values
    - [x] catalog order matches `TagRegistry::all(ReservedTags::CLI_COMMAND)` exactly
    - [x] catalog performs no secondary sorting or deduplication
    - [x] repeated registration of the same service id follows Foundation first-wins semantics
    - [x] applies validated `CommandOverrides` exactly once without changing descriptor order
    - [x] overrides affect only the catalog-view `summary|hidden|group` fields
    - [x] repeated `descriptors()` and `require()` calls do not resolve command services or mutate catalog state

  - [x] `packages/platform/cli/tests/Unit/TableFormatterHonorsConfiguredMaxWidthTest.php`
    - [x] uses the configured `cli.output.table.max_width` rather than detected terminal width
    - [x] rendered table lines respect the configured visible-width limit
    - [x] every physical output line, including headings, separators, wrapped rows, and group labels, stays within the configured `max_width`
    - [x] nested `commands` lists render as readable command rows rather than one serialized JSON value
    - [x] nested `groups` maps render as separate readable group sections without losing command entries
    - [x] long command names and summaries wrap across physical table rows without truncation, ellipsis insertion, character loss, or reordering
    - [x] wrapped cell content can be reconstructed exactly from its physical row fragments
    - [x] narrow valid widths do not cause uncontrolled expansion
    - [x] width calculation ignores formatter-owned ANSI decoration
    - [x] color-disabled rendering is ANSI-free and deterministic
    - [x] identical normalized records and width settings produce identical output
    - [x] output contains no trailing CR or LF before `ConsoleOutputWriter` processing

  - [x] `packages/platform/cli/tests/Unit/CommandRunnerObservabilityFailureIsolationTest.php`
    - [x] tracer failure does not change a successfully returned command exit code
    - [x] meter failure does not change a successfully returned command exit code
    - [x] logger failure does not change a successfully returned command exit code
    - [x] observability failures do not suppress command execution or cause a second command execution
    - [x] when the command throws, the original command Throwable remains the primary failure
    - [x] observability failures do not replace or expose the primary Throwable
    - [x] Kernel-owned UoW cleanup and reset behavior remains authoritative
    - [x] tracer start failure does not prevent the selected command from executing once
    - [x] span attribute-update failure does not prevent the span-end attempt
    - [x] span-end failure does not change the command result or primary Throwable
    - [x] the real Foundation `Stopwatch` produces a non-negative integer duration during successful command execution
    - [x] Stopwatch start and stop calls remain protected by CLI-owned Throwable isolation and an integer `0` fallback
    - [x] Stopwatch failure isolation MUST NOT require mocking, subclassing, or replacing the final Foundation `Stopwatch`
    - [x] the failure-handling structure MAY be verified through source-level contract assertions without introducing a new Stopwatch abstraction
    - [x] metric counter failure does not prevent the independent duration-observation attempt
    - [x] optional context read failures omit correlation identifiers without affecting execution
    - [x] no observability failure causes a nested UoW or retry

  - [x] `packages/platform/cli/tests/Unit/ArgvInputParserTest.php`
    - [x] repeated bare flags fail
    - [x] mixed bare and valued occurrences of one option fail
    - [x] repeated valued occurrences preserve insertion order
    - [x] end-of-options marker prevents subsequent tokens from being parsed as options
    - [x] literal `--format=json` and `--color=always` after `--` remain positional arguments
    - [x] those positional tokens do not populate `ParsedCliInvocation::formatOverride` or `colorOverride`
    - [x] recognized global options before `--` remain absent from command-facing input

  - [x] `packages/platform/cli/tests/Unit/CommandOutputBufferAggregateLimitsTest.php`
    - [x] exactly `1024` records are accepted when the aggregate byte budget permits
    - [x] record `1025` is rejected before insertion
    - [x] the exact aggregate byte boundary is accepted
    - [x] aggregate byte boundary plus one is rejected
    - [x] rejected records are never partially retained
    - [x] failure messages contain no record values or payload fragments

  - [x] `packages/platform/cli/tests/Unit/CliErrorHandlerTest.php`
    - [x] maps `KernelOpsFailedException` only through its stable public code/reason contract
    - [x] mapped `ErrorDescriptor` contains no Throwable message, class, file, line, stack trace, previous Throwable, or filesystem path metadata

  - [x] `packages/platform/cli/tests/Unit/ExceptionRendererDoesNotExposeThrowableMetadataTest.php`
    - [x] renderer consumes only `ErrorDescriptor`, never a raw Throwable
    - [x] rendered output contains no Throwable message, class, file, line, stack trace, previous Throwable, or filesystem path metadata

  - [x] `packages/platform/cli/tests/Unit/CommandOutputBufferRejectsControlCharactersInMapKeysTest.php`
    - [x] map keys containing CR, LF, TAB, DEL, or other forbidden control characters fail before finalization
    - [x] map keys containing CRLF or CR are rejected without line-ending normalization
    - [x] valid non-ASCII UTF-8 map keys preserve their original bytes and canonical Foundation ordering
    - [x] valid multiline and tab-containing string values remain supported
    - [x] Unicode C1 control characters are rejected without rejecting valid UTF-8 continuation bytes
    - [x] malformed UTF-8 fails before record insertion
    - [x] no rejected map key or payload value appears in failure diagnostics

  - [x] `packages/platform/cli/tests/Unit/CommandCatalogRejectsNonClassServiceIdTest.php`
    - [x] a non-class service id hard-fails before descriptor construction
    - [x] no command service is resolved

  - [x] `packages/platform/cli/tests/Unit/CommandCatalogRejectsMetadataConstantMismatchTest.php`
    - [x] tag metadata differing from command constants hard-fails
    - [x] command constants are read without service construction

  - [x] `packages/platform/cli/tests/Unit/CommandOutputBufferDiscardTest.php`
    - [x] clears all pre-error records
    - [x] remains writable before finalization
    - [x] rejects discard after finalization

  - [x] `packages/platform/cli/tests/Unit/KernelOpsRequestResolverTest.php`
    - [x] consumes only the already validated `InputInterface`
    - [x] accepts exactly one non-empty scalar `target`
    - [x] has no `CommandDescriptor` dependency
    - [x] does not validate canonical Kernel target values

  - [x] `packages/platform/cli/tests/Unit/CliServiceFactoryReadsValidatedCliRootTest.php`
    - [x] every config-dependent factory method reads only the complete `cli` root
    - [x] each config-dependent factory method calls `ConfigRepositoryInterface::get('cli')` exactly once
    - [x] no factory method reads an individual `cli.*` path

  - [x] `packages/platform/cli/tests/Unit/OutputFormatterUsesSensitiveDataRedactorTest.php`
    - [x] formatter receives `SensitiveDataRedactorInterface`
    - [x] formatter does not instantiate or resolve a concrete redactor
    - [x] no CLI-local classifier, policy, hasher, or pattern registry is constructed
    - [x] owner-supplied values are passed through the shared redaction boundary without redacting CLI record envelope keys
    - [x] redaction uses the fixed `cli.output` scope and `Placeholder` mode
    - [x] raw sensitive fixture values selected for redaction do not reach rendered output
    - [x] redacted maps retain canonical ordering provided by the shared redaction runtime
    - [x] text and error-message records retain their required string shape after redaction

  - [x] `packages/platform/cli/tests/Unit/CommandTagSchemaRejectsPriorityMetadataKeyTest.php`
    - [x] `priority` key hard-fails deterministically

  - [x] `packages/platform/cli/tests/Unit/CommandTagSchemaRejectsUnknownKeysTest.php`
    - [x] unknown metadata keys hard-fail deterministically

  - [x] `packages/platform/cli/tests/Unit/CommandTagSchemaValidatesNameRegexTest.php`
    - [x] invalid command names hard-fail deterministically

  - [x] `packages/platform/cli/tests/Unit/CommandCatalogDoesNotInstantiateCommandsForListTest.php`
    - [x] catalog can build descriptors from tag metadata without resolving command services

  - [x] `packages/platform/cli/tests/Unit/CommandCatalogRejectsDuplicateNamesTest.php`
    - [x] two distinct tagged service ids declaring the same command name hard-fail deterministically
    - [x] repeated registration of the same service id follows the canonical TagRegistry first-wins policy

  - [x] `packages/platform/cli/tests/Unit/CommandCatalogRejectsReservedExternalNamesTest.php`
    - [x] external `doctor` hard-fails
    - [x] external `help` hard-fails
    - [x] external `list` hard-fails
    - [x] built-in `doctor`, `help`, and `list` are allowed only for platform/cli built-in service ids
    - [x] reserved-name validation uses the canonical immutable built-in service-id map
    - [x] no tagged command service is instantiated during collision detection

  - [x] `packages/platform/cli/tests/Unit/CommandRunnerValidatesCommandNameMatchesDescriptorTest.php`
    - [x] descriptor name and command `name()` mismatch hard-fails with `InvalidCommandTagMetaException`

  - [x] `packages/platform/cli/tests/Unit/CliOutputPolicyTest.php`
    - [x] accepts complete default config
    - [x] rejects unsupported format token
    - [x] rejects unsupported color token
    - [x] rejects invalid table width

  - [x] `packages/platform/cli/tests/Unit/FormatResolverTest.php`
    - [x] explicit format overrides configured default
    - [x] adaptive interactive resolves to configured interactive format
    - [x] adaptive non-interactive resolves to configured non-interactive format
    - [x] performs no CI env detection

  - [x] `packages/platform/cli/tests/Unit/ColorResolverTest.php`
    - [x] explicit color overrides configured default
    - [x] auto requires both interactive output and ANSI support
    - [x] interactive stdout with `ansiSupported = false`, including redirected stderr capabilities, disables auto color
    - [x] non-interactive stdout disables auto color regardless of ANSI support
    - [x] never disables color
    - [x] always enables color for text formats
    - [x] JSON always disables color

  - [x] `packages/platform/cli/tests/Unit/CommandRunnerObservabilityTest.php`
    - [x] performs exactly one `cli.command` span-start attempt
    - [x] starts the span before entering KernelRuntime UoW
    - [x] ends a successfully started span after KernelRuntime UoW completion
    - [x] span attributes contain exactly `operation|format|outcome|exit_code` on successful final update
    - [x] emits exactly one `cli.command_total` counter increment
    - [x] emits exactly one `cli.command_duration_ms` observation
    - [x] metric labels contain exactly `operation|outcome`
    - [x] metric duration is a non-negative integer measured through Foundation Stopwatch
    - [x] output format and exit code are not metric labels
    - [x] returned exit code `0` produces outcome `success`
    - [x] valid non-zero returned exit codes produce outcome `failure` without remapping
    - [x] thrown command failure records `exit_code = 1`
    - [x] attempts one safe command completion/failure log
    - [x] log fields contain only `operation|outcome|exit_code` and optional safe `correlation_id|uow_id`
    - [x] optional context identifiers are captured inside the UoW callback before reset
    - [x] no command arguments, option values, output records, paths, endpoints, or payloads enter telemetry
    - [x] does not duplicate `kernel.uow` or `kernel.operation` telemetry

- Contract:
  - [x] `packages/platform/cli/tests/Contract/CliModuleMetadataContractTest.php`
    - [x] `CliModule` exposes the canonical module identity, package identity, Composer package, runtime kind, and config root
    - [x] `MODULE_ID = platform.cli`
    - [x] `PACKAGE_ID = platform/cli`
    - [x] `COMPOSER_PACKAGE = coretsia/platform-cli`
    - [x] `KIND = runtime`
    - [x] `CONFIG_ROOT = cli`
    - [x] instance metadata methods agree with their declared constants
    - [x] instance `providers()` returns exactly `CliServiceProvider::class`
    - [x] no Phase-0 static-only provider API remains
    - [x] module metadata access performs no config reads, command discovery, service resolution, or application boot

  - [x] `packages/platform/cli/tests/Contract/CliDoesNotBindGlobalErrorHandlerPortContractTest.php`
    - [x] `CliErrorHandler` implements `ErrorHandlerInterface`
    - [x] `CliApplication` receives `CliErrorHandler` under its concrete service id
    - [x] `CliServiceProvider` does not alias `ErrorHandlerInterface`

  - [x] `packages/platform/cli/tests/Contract/CliCommandOutputBufferIsInvocationLocalContractTest.php`
    - [x] `CommandOutputBuffer` is absent from source-host service registration
    - [x] `CommandOutputBuffer` is absent from canonical runtime definitions
    - [x] `CliApplication` creates exactly one buffer per invocation
    - [x] no buffer state is retained between `CliApplication::run()` calls
    - [x] the same invocation-local buffer is cleared and reused for error rendering

  - [x] `packages/platform/cli/tests/Contract/CliDoesNotImplementConfigPipelineContractTest.php`
    - [x] no direct package config-file reads
    - [x] no CLI-local loader, merger, validator, directive processor, or repository
    - [x] only `ConfigRepositoryInterface` is consumed

  - [x] `packages/platform/cli/tests/Contract/CliReusesFoundationJsonLikePrimitivesContractTest.php`
    - [x] CLI introduces no independent JSON-like structural normalizer
    - [x] CLI introduces no independent recursive map sorter
    - [x] JSON output uses the canonical Foundation StableJsonEncoder
    - [x] CLI-owned control-byte and presentation validation remain separate

  - [x] `packages/platform/cli/tests/Contract/CliComposerRuntimeDependenciesContractTest.php`
    - [x] direct production Composer requirements include `coretsia/core-contracts`, `coretsia/core-foundation`, `coretsia/core-kernel`, and `coretsia/platform-redaction`
    - [x] all internal Coretsia Composer requirements use the canonical release-line `publicConstraint`
    - [x] `coretsia/platform-redaction` is required for runtime module installation, not direct PHP source access
    - [x] `extra.coretsia.requires` contains exactly `core.kernel` and `platform.redaction`
    - [x] `bin/coretsia` is declared as the packaged Composer binary
    - [x] no command-owner package dependency is introduced solely for discovery

  - [x] `packages/platform/cli/tests/Contract/CliHasSingleProductionOutputSinkContractTest.php`
    - [x] after Composer autoload succeeds, only `ConsoleOutputWriter.php` writes stdout/stderr
    - [x] binary may construct and pass stdout/stderr streams
    - [x] binary may write directly to stderr only for the fixed pre-autoload failure
    - [x] `TerminalCapabilitiesDetector` may inspect streams but never writes them
    - [x] no other production class references output sinks

  - [x] `packages/platform/cli/tests/Contract/JsonOutputSchemaContractTest.php`
    - [x] MUST load schema from `packages/platform/cli/resources/schema/cli_output@1.json` (no inline schema duplication)

  - [x] `packages/platform/cli/tests/Contract/CliDoesNotReferenceKernelCompileInternalsContractTest.php`
    - [x] scans `packages/platform/cli/src`
    - [x] rejects imports and FQCN references to:
      - [x] `ModulePlanResolver`
      - [x] `ModuleResolution`
      - [x] `ContainerProviderPlan`
      - [x] `ContainerProviderPlanResolver`
      - [x] `ManifestReaderInterface`
      - [x] `ComposerManifestReader`
      - [x] `ArtifactCompiler`
      - [x] `FingerprintCalculator`
      - [x] `CacheVerifier`
      - [x] `ArtifactGenerationLocator`
      - [x] `ArtifactGenerationPublisher`
      - [x] `ArtifactGenerationValidator`
      - [x] `ArtifactPathResolver`
      - [x] `PhpArtifactReader`
      - [x] `ArtifactRuntimeBooter`
      - [x] `ArtifactRuntimeInput`
    - [x] CLI command, catalog, runner, formatter, and renderer implementations do not inspect Kernel `current` or generation manifests
    - [x] CLI does not construct Kernel generation directories or artifact paths
    - [x] CLI does not directly include or read generated Kernel `config.php` or `container.php`
    - [x] allows `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface` throughout platform/cli Kernel-operation adapters
    - [x] allows `KernelOpsHostInput` and `KernelOpsHostBooter` only in `src/Bootstrap/CliHostBootstrap.php`
    - [x] rejects every other `Coretsia\Kernel\Ops\*` reference
    - [x] does not forbid Kernel-owned artifact access behind `KernelOpsInterface`

  - [x] `packages/platform/cli/tests/Contract/CliServiceProviderSeparatesSourceOnlyKernelOpsWiringContractTest.php`
    - [x] `CliServiceProvider` implements both provider interfaces
    - [x] source container contains `KernelOpsRequestResolver`
    - [x] source container contains all six Kernel operation commands
    - [x] each Kernel operation command receives `KernelOpsInterface` through constructor injection
    - [x] no command imports or resolves `Coretsia\Kernel\Ops\KernelOpsFacade`
    - [x] source container contains their `cli.command` tags
    - [x] `define()` contains generic CLI infrastructure
    - [x] `define()` contains `CliErrorHandler` and `ExceptionRenderer`
    - [x] `define()` contains no `KernelOpsInterface` and no Kernel operation command services or tags
    - [x] canonical runtime definitions contain no Kernel operation command services or tags
    - [x] canonical runtime definitions contain no `KernelOpsRequestResolver`
    - [x] canonical runtime definitions contain no `KernelOpsHostInput` or `KernelOpsHostBooter`

  - [x] `packages/platform/cli/tests/Contract/CliConfigSubtreeShapeContractTest.php`
    - [x] config returns subtree only
    - [x] no root repetition
    - [x] exact default keys
    - [x] no closures, objects, resources, floats, or env reads

  - [x] `packages/platform/cli/tests/Contract/CliConfigRulesCoverAllDefaultsContractTest.php`
    - [x] every default key has a rule
    - [x] no rule-owned key is missing from defaults
    - [x] unknown keys are rejected

  - [x] `packages/platform/cli/tests/Contract/CliRedactionCannotBeDisabledContractTest.php`
    - [x] no redaction enable/disable config key
    - [x] no redaction bypass option
    - [x] OutputFormatter requires `SensitiveDataRedactorInterface`

  - [x] `packages/platform/cli/tests/Contract/CliHasNoPackageLocalRedactionImplementationContractTest.php`
    - [x] `packages/platform/cli/src/Redaction/` does not exist
    - [x] `packages/platform/cli/src/Output/Redaction/` does not exist
    - [x] `RedactionEngine.php` is absent
    - [x] `RedactionPolicy.php` is absent
    - [x] production source defines no CLI-local:
      - [x] sensitive-key classifier
      - [x] sensitive-value classifier
      - [x] redaction policy
      - [x] redaction hasher
      - [x] pattern registry
    - [x] `OutputFormatter` depends only on `SensitiveDataRedactorInterface`
    - [x] no platform/cli class imports or instantiates `DefaultSensitiveDataRedactor`

  - [x] `packages/platform/cli/tests/Contract/CliDoesNotWriteContextOrResetDirectlyContractTest.php`
    - [x] no direct ContextStore writes
    - [x] no ResetOrchestrator dependency
    - [x] no reset tag enumeration

- Integration:
  - [x] `packages/platform/cli/tests/Integration/CoretsiaRepositoryWrapperIsCwdIndependentTest.php`
    - [x] repo-root `coretsia` works when invoked outside the repository working directory
    - [x] the wrapper derives its application root exclusively from its own repository-relative location
    - [x] the packaged CLI selects the repository-root `vendor/autoload.php`
    - [x] equivalent invocations from different working directories produce the same exit code and normalized output
    - [x] no Skeleton source-directory root is substituted for the repository application root
    - [x] no recursive application-root discovery is introduced

  - [x] `packages/platform/cli/tests/Integration/PreAutoloadFailureIsFixedAndPathSafeTest.php`
    - [x] missing Composer autoload produces exactly `CORETSIA_CLI_BOOTSTRAP_FAILED: autoload-unavailable`
    - [x] unreadable Composer autoload produces the same fixed diagnostic
    - [x] conflicting pre-autoload selection inputs produce the same fixed diagnostic
    - [x] missing Skeleton `vendor/bin/coretsia` produces the same fixed diagnostic
    - [x] unreadable Skeleton `vendor/bin/coretsia` produces the same fixed diagnostic
    - [x] Skeleton wrapper failure occurs before attempting to require the missing or unreadable Composer binary
    - [x] failure writes only to stderr with exactly one trailing LF and exits with code `1`
    - [x] stdout remains empty
    - [x] no application classes are required to render the pre-autoload failure
    - [x] no PHP warning, fatal-error output, absolute path, raw launcher input, Throwable detail, or stack trace is emitted

  - [x] `packages/platform/cli/tests/Integration/NormalCommandExecutesExactlyOneKernelUowTest.php`
    - [x] successful normal command execution invokes `KernelRuntimeInterface::runUnitOfWork()` exactly once
    - [x] the selected command executes inside that UoW callback
    - [x] command failure does not cause another UoW invocation
    - [x] command resolution and exit-code validation occur inside the same UoW
    - [x] no nested CLI-owned UoW or manual reset orchestration is introduced
    - [x] canonical UoW type and attributes remain covered by `CliCommandUowAttributesAreOwnedByCommandRunnerTest.php`

  - [x] `packages/platform/cli/tests/Integration/UltraEarlyDoctorUsesSafeFixedOutputPipelineTest.php`
    - [x] exact `coretsia doctor` uses the ultra-early runner
    - [x] the same `DoctorCommand` implementation is used by the normal catalog
    - [x] output uses fixed `plain` format with color disabled
    - [x] output contains only allowlisted doctor record types and scalar values
    - [x] output is written through `ConsoleOutputWriter`
    - [x] no Kernel container, configuration, observability, context, or shared redaction service is required
    - [x] injected environment secret sentinels are never emitted
    - [x] no raw environment value, absolute path, command line, loaded-extension list, phpinfo output, or Throwable detail is emitted
    - [x] unsupported doctor arguments and options fail through the fixed safe pipeline

  - [x] `packages/platform/cli/tests/Integration/JsonFormatSuppressesAnsiWhenColorAlwaysTest.php`
    - [x] `--format=json --color=always` produces valid JSON without ANSI sequences
    - [x] JSON output remains ANSI-free for successful and failed command outcomes
    - [x] effective color is disabled before JSON rendering
    - [x] `AnsiDecorator` cannot decorate the JSON document
    - [x] JSON rendering contains no terminal-dependent formatting
    - [x] stdout contains the canonical schema envelope and stderr remains empty
    - [x] final output contains exactly one writer-owned trailing LF

  - [x] `../../packages/platform/cli/tests/Integration/NonInteractiveAdaptiveOutputUsesTableFormatTest.php`
    - [x] configured `adaptive` format resolves to the configured non-interactive format
    - [x] the baseline non-interactive default resolves to `plain`
    - [x] stdout interactivity comes only from the injected `TerminalCapabilities`
    - [x] no CI environment variable or terminal-width inference changes the format
    - [x] an explicit valid `--format` overrides adaptive selection
    - [x] command-facing input does not receive the resolved format as an owner option

  - [x] `packages/platform/cli/tests/Integration/ExternalPackageCommandExitCodeIsPreservedTest.php`
    - [x] external command exit code `0` is preserved
    - [x] external command non-zero exit codes, including `2`, `3`, and `255`, are preserved without Kernel-specific remapping
    - [x] a valid non-zero return is not converted into an uncaught exception
    - [x] returned exit codes outside `0..255` fail through `CliCommandFailedException`
    - [x] CLI-global parsing and uncaught command exceptions retain their own failure boundary

  - [x] `packages/platform/cli/tests/Integration/ConfigCompileRendersGenerationAwareOpsResultTest.php`
    - [x] `config:compile` renders the published generation id returned by `KernelOpsInterface`
    - [x] renders the explicit app target and Kernel-provided effective preset
    - [x] renders exactly the four Kernel-provided artifact identities and basenames
    - [x] preserves the deterministic order of the Kernel-provided artifact list
    - [x] does not synthesize generation paths or `generations/current/<basename>` values
    - [x] does not receive or render raw config, Composer metadata, or artifact payloads
    - [x] CLI performs no post-compile artifact verification or runtime boot

  - [x] `packages/platform/cli/tests/Integration/ConfigHashRendersGenerationIdTest.php`
    - [x] `config:hash` renders the safe expected `generation_id` returned by Kernel
    - [x] renders the explicit app target and Kernel-provided effective preset
    - [x] does not present the expected generation id as a published or current generation
    - [x] does not render synthetic artifact locations
    - [x] does not publish artifacts or inspect `current`
    - [x] no CLI-local fingerprint calculation is invoked

  - [x] `packages/platform/cli/tests/Integration/CacheVerifyRendersFourGenerationArtifactsTest.php`
    - [x] `cache:verify` renders exactly four Kernel-provided artifact verification entries
    - [x] preserves the Kernel-provided artifact identities, basenames, statuses, and safe reasons
    - [x] renders expected and nullable current generation ids without synthesizing paths
    - [x] renders Kernel-provided byte counts when available
    - [x] state `clean` produces exit code `0`
    - [x] state `dirty` produces exit code `2`
    - [x] state `invalid` produces exit code `3`
    - [x] all three states use the same Kernel OpsResult presentation path
    - [x] CLI does not independently read artifact files or reinterpret verification states

  - [x] `packages/platform/cli/tests/Integration/CommandInputValidatorRejectsUnknownOptionBeforeResolutionTest.php`
    - [x] an option absent from the selected descriptor throws `CliInputInvalidException`
    - [x] rejection occurs before the selected command service is resolved
    - [x] the selected command constructor and `run()` are not invoked
    - [x] no owner-package domain validation is invoked for structurally invalid input
    - [x] unknown option values are not echoed in diagnostics
    - [x] invalid input returns exit code `1`

  - [x] `packages/platform/cli/tests/Integration/CommandInputValidatorPreservesRepeatableOptionOrderTest.php`
    - [x] an owner-declared repeatable required-value option accepts multiple `--name=value` occurrences
    - [x] parsed values reach the owner-package command as an ordered `list<string>`
    - [x] descriptor validation preserves the original occurrence order
    - [x] the command receives each value exactly once
    - [x] repeated non-repeatable options are rejected before command service resolution
    - [x] no last-value-wins behavior is introduced

  - [x] `packages/platform/cli/tests/Integration/GlobalFormatOptionIsNotPassedToPackageCommandTest.php`
    - [x] CLI-global `--format` is consumed before owner-package input construction
    - [x] CLI-global `--color` is consumed before owner-package input construction
    - [x] neither global option appears in `InputInterface::tokens()` or normalized command options
    - [x] owner-package arguments and declared domain options remain unchanged
    - [x] explicit global format and color selections affect presentation only
    - [x] external command execution does not modify Kernel Bootstrap preset selection

  - [x] `packages/platform/cli/tests/Integration/DoctorBypassesKernelOperationsHostTest.php`
    - [x] exact `coretsia doctor` does not invoke `KernelOpsHostBooter`
    - [x] does not construct a source operations container or command catalog
    - [x] does not invoke `KernelRuntimeInterface::runUnitOfWork()`
    - [x] does not resolve Kernel Ops, module discovery, or generated runtime artifacts
    - [x] does not require UoW hooks or reset orchestration
    - [x] other normal CLI commands continue through the canonical Kernel operations host

  - [x] `packages/platform/cli/tests/Integration/CoretsiaBinaryListCommandTest.php`
    - [x] packaged `coretsia list` reaches normal source-host catalog dispatch
    - [x] a Composer-generated `vendor/bin/coretsia` PHP proxy in a consumer application reaches the packaged CLI implementation
    - [x] the shipped Skeleton project-root `coretsia` delegates through that Composer binary proxy
    - [x] `php coretsia list` and `php vendor/bin/coretsia list` produce identical normalized output and exit codes
    - [x] both invocations resolve the same consumer application root rather than the monorepo or installed package directory
    - [x] both invocations preserve equivalent command-facing arguments and global options
    - [x] the Skeleton wrapper preserves Composer proxy variables used for autoload and application-root resolution
    - [x] the Skeleton wrapper works when invoked through an absolute path from another current working directory
    - [x] Composer binary invocation remains supported independently of the optional project-root convenience wrapper
    - [x] no second catalog, command parser, configuration pipeline, or Kernel operations host is constructed
    - [x] renders built-in command descriptors in deterministic groups
    - [x] preserves the canonical TagRegistry descriptor order within each group
    - [x] does not require grouped presentation order to equal one flat TagRegistry order across different groups
    - [x] grouping MUST NOT mutate, re-sort, or re-dedupe the underlying CommandCatalog
    - [x] respects catalog-view hidden-command metadata
    - [x] explicit `--format=table` renders every visible built-in and enabled external command as a readable entry in its descriptor-defined group
    - [x] table output contains every visible command name and complete catalog-view summary exactly once, without rendering hidden commands
    - [x] long command summaries remain recoverable in full after wrapping, without truncation or ellipsis insertion
    - [x] every physical table output line respects the configured `cli.output.table.max_width`, including wrapped rows and group headings
    - [x] table presentation preserves canonical descriptor order within each group and does not alter the underlying catalog or JSON output
    - [x] does not instantiate unrelated command services
    - [x] exits with code `0` on successful listing
    - [x] does not require compiled application runtime artifacts
    - [x] an explicit Console-target module exclusion of `platform.cli` prevents normal CLI command execution
    - [x] `CliHostBootstrap` verifies the existing Kernel-owned `ModulePlan` before resolving `CliApplication`
    - [x] Foundation concrete-class autowiring MUST NOT bypass the excluded CLI module
    - [x] excluded CLI fails with `CORETSIA_CLI_HOST_BOOT_FAILED`, reason `host-boot-failed`, and exit code `1`
    - [x] no command catalog is constructed and no command service is resolved after the exclusion is detected
    - [x] no CLI UoW is entered
    - [x] no second ModulePlan, provider-registration path, or fallback CLI container is created

  - [x] `packages/platform/cli/tests/Integration/CoretsiaBinaryHelpCommandTest.php`
    - [x] packaged `coretsia help` renders general command help from catalog descriptors
    - [x] `coretsia help <command>` renders the selected descriptor's arguments and options
    - [x] unknown command help requests produce the canonical safe input failure
    - [x] help does not instantiate the described command service
    - [x] help output follows the selected global format and color policy
    - [x] explicit `--format=table` renders every visible command descriptor in general help as a readable row containing its group, name, and complete summary
    - [x] general help excludes hidden commands and preserves canonical TagRegistry descriptor order
    - [x] long command summaries remain recoverable in full after wrapping, without truncation or ellipsis insertion
    - [x] every physical general-help table output line respects the configured `cli.output.table.max_width`
    - [x] command-specific help in table format renders declared arguments and options as readable rows rather than truncated serialized JSON values
    - [x] table presentation does not alter descriptor metadata, command discovery, or structured JSON output
    - [x] successful help exits with code `0`

  - [x] `packages/platform/cli/tests/Integration/ReservedCommandNamesCollisionRejectedTest.php`
    - [x] an enabled external package contributing reserved command name `help` causes deterministic catalog rejection
    - [x] collision is detected from tag metadata without constructing the external command
    - [x] external services cannot replace the built-in reserved command identity
    - [x] catalog construction failure follows the fixed host-bootstrap error boundary
    - [x] no conflicting service id, class name, or command metadata is exposed
    - [x] the existing dedicated unit test covers the complete `doctor|help|list` reserved-name set

  - [x] `packages/platform/cli/tests/Integration/NormalCommandsBootKernelOperationsHostTest.php`
    - [x] obtains the canonical Console ModulePlan from the Kernel source operations host
    - [x] normal command dispatch succeeds only when `platform.cli` is enabled
    - [x] explicit Console exclusion of `platform.cli` fails before `CliApplication` resolution
    - [x] concrete-class autowiring cannot bypass the module-selection requirement
    - [x] exclusion failure produces `CORETSIA_CLI_HOST_BOOT_FAILED`, reason `host-boot-failed`, and exit code `1`
    - [x] no second module resolver, ModulePlan, or fallback CLI container is constructed

  - [x] `packages/platform/cli/tests/Integration/KernelCommandsRequireExplicitTargetTest.php`
    - [x] a missing `--target` is rejected by `KernelOpsRequestResolver`
    - [x] an empty `--target=` is rejected during structural validation
    - [x] neither invalid invocation calls `KernelOpsInterface`
    - [x] no CLI-owned Kernel target or preset inference is introduced

  - [x] `packages/platform/cli/tests/Integration/CliDefaultMicroCreateProjectBootTest.php`
    - [x] verify the packaged skeleton baseline and canonical `micro` selection
    - [x] verify source-host boot with CLI, Kernel, and Redaction available
    - [x] verify that normal command discovery does not require generated runtime artifacts
    - [x] verify that no automatic DependencySync or second module planner executes

  - [x] `packages/platform/cli/tests/Integration/CliHostResolvesCanonicalSensitiveDataRedactorTest.php`
    - [x] the composed source host resolves exactly one `SensitiveDataRedactorInterface`
    - [x] `OutputFormatter` receives the contracts port
    - [x] no platform/cli class imports or instantiates `DefaultSensitiveDataRedactor`

  - [x] `packages/platform/cli/tests/Integration/CliRequiresExplicitConsoleModuleSelectionTest.php`
    - [x] installing `platform.cli` without selecting it for Console does not implicitly enable its provider
    - [x] explicitly selecting `platform.cli` resolves `platform.redaction` through canonical Kernel ModulePlan dependencies
    - [x] missing installed redaction dependency fails during module resolution
    - [x] no CLI-owned provider fallback or second ModulePlan is constructed

  - [x] `packages/platform/cli/tests/Integration/CliJsonPayloadEnvelopeIsNotRedactedTest.php`
    - [x] a safe JSON record retains its data under the CLI-owned `payload` field
    - [x] sensitive structural keys inside the owner-supplied JSON value are still redacted
    - [x] the CLI record envelope is reconstructed without passing its `payload` field to the shared classifier
    - [x] original record order and canonical payload ordering are preserved

  - [x] `packages/platform/cli/tests/Integration/CliSensitiveTextRetainsStringRecordShapeTest.php`
    - [x] a classified sensitive text value is not emitted unchanged
    - [x] a structured redaction summary cannot violate the required text record shape
    - [x] safe placeholder rendering does not disclose hash or length metadata

  - [x] `packages/platform/cli/tests/Integration/JsonFormatterAndConsoleWriterOwnOneFinalNewlineTest.php`
    - [x] canonical encoder framing does not produce duplicate final LF bytes
    - [x] non-empty JSON output ends with exactly one writer-owned LF
    - [x] JSON output contains no ANSI escape sequences

  - [x] `packages/platform/cli/tests/Integration/KernelCommandsRejectUndeclaredModeAndPresetOptionsTest.php`
    - [x] Kernel commands do not declare `mode` or `preset`
    - [x] both options fail before command service resolution

  - [x] `packages/platform/cli/tests/Integration/CliUsesMergedValidatedConfigurationTest.php`
    - [x] skeleton override changes effective CLI output policy
    - [x] invalid Console-host CLI config fails in the existing ConfigKernel validation pipeline before command dispatch
    - [x] unknown `cli.mode.*` keys are rejected through canonical ConfigValidator rules
    - [x] legacy FQCN registry-list `cli.commands` is rejected rather than interpreted as command discovery configuration
    - [x] canonical `cli.commands.overrides` map remains valid
    - [x] rejected configuration never reaches `CommandCatalog` or command service resolution
    - [x] host validation failure produces the fixed `CORETSIA_CLI_HOST_BOOT_FAILED` diagnostic rather than a synthetic Kernel OpsResult
    - [x] invalid configuration values and filesystem paths are not disclosed
    - [x] CliServiceFactory does not re-run validation

  - [x] `packages/platform/cli/tests/Integration/CliConfigChangesAffectOutputPolicyTest.php`
    - [x] `cli.output.format_default` changes the effective format when no explicit global `--format` option is supplied
    - [x] `cli.output.adaptive.interactive` changes the effective format only when adaptive format is selected and stdout is interactive
    - [x] `cli.output.adaptive.non_interactive` changes the effective format only when adaptive format is selected and stdout is non-interactive
    - [x] explicit `--format` takes precedence over the configured format default
    - [x] non-adaptive effective formats do not depend on adaptive interactive/non-interactive mappings
    - [x] `cli.output.color_default` changes the effective color decision when no explicit global `--color` option is supplied
    - [x] explicit `--color` takes precedence over the configured color default
    - [x] effective JSON format disables ANSI regardless of the configured color default
    - [x] `cli.output.table.max_width` changes the rendered table's deterministic visible-width limit
    - [x] table width does not affect plain or JSON output
    - [x] all five output settings originate from the merged and validated `cli` subtree
    - [x] no resolver or formatter reads configuration directly or invents missing defaults

  - [x] `packages/platform/cli/tests/Integration/CliCommandUowAttributesAreOwnedByCommandRunnerTest.php`
    - [x] UoW type is `cli`
    - [x] canonical command name is passed as `operation`
    - [x] effective format is passed as `output_format`
    - [x] effective format is not added to ContextStore
    - [x] platform/cli performs no direct context writes
    - [x] `runUnitOfWork()` receives exactly the `operation|output_format` attribute map
    - [x] UoW type is supplied through `UnitOfWorkType::CLI`
    - [x] command resolution, class/name verification, execution, and exit-code validation occur inside the UoW callback
    - [x] raw argv, positional arguments, option values, and output records are absent from UoW attributes
    - [x] KernelRuntime remains the sole owner of the three base ContextStore writes
    - [x] no new ContextStore key is introduced for output format
    - [x] KernelRuntime reset remains authoritative after successful and failed execution

  - [x] `packages/platform/cli/tests/Integration/CliRejectsInvalidCommandOverridesDeterministicallyTest.php`
    - [x] ConfigValidator rejects a non-map `cli.commands.overrides`
    - [x] CommandOverrides rejects invalid dynamic command-name keys
    - [x] CommandOverrides rejects invalid override value types
    - [x] CommandOverrides rejects unknown fields
    - [x] CommandOverrides rejects unknown command names
    - [x] invalid dynamic overrides detected during CommandCatalog construction fail `CliApplication` resolution
    - [x] construction-time failures use the fixed `CORETSIA_CLI_HOST_BOOT_FAILED` diagnostic rather than attempting to invoke `CliErrorHandler` before `CliApplication::run()`
    - [x] no invalid override name, value, command metadata, or Throwable detail appears in the host-bootstrap diagnostic

  - [x] `packages/platform/cli/tests/Integration/ExternalPackageCommandWithOwnerServiceDispatchTest.php`
    - [x] command is contributed by an enabled non-CLI package
    - [x] command resolves an owner-package service
    - [x] platform/cli imports no owner-package class
    - [x] command is discovered, validated, resolved, and dispatched through generic infrastructure

  - [x] `packages/platform/cli/tests/Integration/ExternalPackageCommandMayDeclareDomainModeOptionTest.php`
    - [x] external command declares `mode` in its own `OPTIONS`
    - [x] descriptor validation accepts it
    - [x] value reaches the external command unchanged
    - [x] it does not affect Kernel preset selection

  - [x] `packages/platform/cli/tests/Integration/ExternalTaggedCommandIsLazyDiscoveredTest.php`
    - [x] external tagged command appears in catalog
    - [x] command constructor is not called during catalog/list/help descriptor build
    - [x] command constructor is called only on dispatch

  - [x] `packages/platform/cli/tests/Integration/WorkerCommandMetadataCompatibilityTest.php`
    - [x] when `platform.worker` is enabled, worker command tag metadata passes `CommandTagSchema`
    - [x] worker command service ids are discovered through generic `cli.command`
    - [x] `platform/cli` production source still does not import `Coretsia\Platform\Worker\*`
    - [x] worker command metadata uses empty `ARGUMENTS` and `OPTIONS`
    - [x] undeclared options are rejected before Worker command resolution
    - [x] `--target` is rejected because Worker commands do not declare it
    - [x] CLI-global `--format` is consumed before Worker command input is constructed
    - [x] worker command metadata contains no `mode` key
    - [x] worker command classes contain no `MODE` constant
    - [x] CLI-global `--color` is consumed before Worker command input is constructed

  - [x] `packages/platform/cli/tests/Integration/ConfigCompileDelegatesToKernelOpsPortTest.php`
    - [x] asserts exactly one `KernelOpsInterface` compile call
    - [x] asserts exactly one explicit app target is passed
    - [x] asserts the command does not resolve any Kernel compile-time service
    - [x] asserts no Composer metadata reader is touched by CLI
    - [x] asserts no artifact compiler is resolved by CLI

  - [x] `packages/platform/cli/tests/Integration/CacheVerifyDelegatesToKernelOpsPortTest.php`
    - [x] asserts exactly one `KernelOpsInterface` verify call
    - [x] asserts exactly one explicit app target is passed
    - [x] asserts the command does not resolve any Kernel compile-time service
    - [x] asserts no Composer metadata reader is touched by CLI
    - [x] asserts no cache verifier is resolved by CLI

  - [x] `packages/platform/cli/tests/Integration/DebugModulesDelegatesToKernelOpsPortTest.php`
    - [x] exactly one `debugModules()` call
    - [x] no module-resolution service is resolved by CLI

  - [x] `packages/platform/cli/tests/Integration/ConfigValidateDelegatesToKernelOpsPortTest.php`
    - [x] exactly one `validateConfig()` call
    - [x] no ConfigKernel or module-resolution service is resolved by CLI

  - [x] `packages/platform/cli/tests/Integration/ConfigDebugDelegatesToKernelOpsPortTest.php`
    - [x] exactly one `debugConfig()` call
    - [x] only safe `OpsResult` data reaches output

  - [x] `packages/platform/cli/tests/Integration/ConfigHashDelegatesToKernelOpsPortTest.php`
    - [x] exactly one `hashConfig()` call
    - [x] no fingerprint or module-resolution service is resolved by CLI

  - [x] `packages/platform/cli/tests/Integration/ExternalTaggedCommandDiscoveryTest.php`
    - [x] proves commands from an enabled non-CLI package are discovered through `cli.command`
    - [x] MUST NOT rely on filesystem scanning
    - [x] MUST NOT use a `cli.commands` registry list

  - [x] `packages/platform/cli/tests/Integration/WorkerCommandsAreDiscoverableWhenWorkerPackageEnabledTest.php`
    - [x] enables `platform.worker` in a composed test fixture/app
    - [x] asserts `worker:start`, `worker:stop`, `worker:status`, and `worker:health` appear in the command catalog
    - [x] asserts discovery happens via `cli.command`
    - [x] MUST NOT require `platform/cli` compile-time dependency on `platform/worker`

  - [x] `packages/platform/cli/tests/Integration/WorkerStartDispatchesThroughCommandCatalogTest.php`
    - [x] dispatches `worker:start` through `CliApplication` / `CommandCatalog`
    - [x] uses a safe fake worker manager or fake command handler
    - [x] MUST NOT start real worker processes
    - [x] MUST NOT fork, call `proc_open`, or open sockets
    - [x] preserves the foreground blocking and WorkerSupervisor lifecycle semantics of `worker:start`
    - [x] explicitly acknowledges that immediate readiness rendering from the previous output implementation is not preserved by the buffered-output contract
    - [x] readiness records remain invocation-local and are rendered only after `WorkerSupervisor::run()` returns, normally at shutdown
    - [x] a fake supervisor emitting readiness before return MUST NOT cause premature stdout or stderr writes
    - [x] no unredacted direct-stream or secondary output channel is introduced

### DoD (MUST)

- [x] normal commands are discovered only through `cli.command`
- [x] Kernel operation commands require explicit `--target`
- [x] built-in Kernel operation commands reject undeclared `--mode` and `--preset`
- [x] effective preset comes only from Kernel `OpsResult`
- [x] each Kernel command makes exactly one `KernelOpsInterface` call
- [x] platform/cli infrastructure and built-in Kernel commands never read Kernel artifacts or `current`
- [x] output is deterministic and redacted
- [x] external package commands remain lazy and package-agnostic
- [x] a new enabled package can contribute and dispatch a command without any platform/cli production-source change
- [x] external commands may declare owner-specific arguments and options without entering KernelOpsInterface
- [x] `doctor` is the only pre-host command path
- [x] config defaults and rules are exact and synchronized
- [x] only CliServiceFactory reads `cli.*` configuration
- [x] normal command execution uses exactly one Kernel UoW
- [x] platform/cli performs no direct ContextStore writes or reset orchestration
- [x] command observability is emitted through injected ports
- [x] observability failures do not affect command exit semantics
- [x] redaction cannot be disabled
- [x] `OutputFormatter` consumes only `SensitiveDataRedactorInterface`.
- [x] `platform/cli` defines no package-local redaction engine, policy, classifier, hasher, or pattern registry.
- [x] `packages/platform/cli/src/Redaction/` is absent.
- [x] `packages/platform/cli/src/Output/Redaction/` is absent.
- [x] adaptive format depends only on explicit terminal capabilities
- [x] color policy is `auto|always|never`
- [x] JSON output is always ANSI-free
- [x] configurable ANSI palettes are not introduced
- [x] every legacy Phase-0 production file listed under `Deletes` is absent
- [x] every retained legacy path listed as a complete rewrite contains no Phase-0 behavior
- [x] no compatibility fallback reads `cli.commands` as an FQCN list
- [x] no command is instantiated through reflection or `new $fqcn`
- [x] Worker command metadata matches the final six-key schema
- [x] after autoload, `ConsoleOutputWriter` is the only production output sink; the fixed pre-autoload failure is the sole exception
- [x] effective format is a UoW attribute, not a ContextStore key
- [x] all new metric and span names are registered in observability SSoT
- [x] `CliApplication` owns Throwable handling for normal-path parsing, global-option resolution, catalog selection, descriptor validation, command resolution, execution, formatting, and redaction
- [x] construction-time catalog and dependency failures remain owned by `CliHostBootstrap`
- [x] unexpected Throwables escaping `CliApplication`, including final output-sink failures, remain guarded by the packaged entrypoint's fixed uncaught-Throwable boundary
- [x] no second error handler, recursive rendering attempt, or fallback CLI execution path is introduced
- [x] pre-application host failures are handled by `CliHostBootstrap`
- [x] pre-autoload failures are handled by the fixed binary fallback
- [x] `CommandRunner` owns execution lifecycle but not error rendering
- [x] formatter/redactor failure uses one non-recursive fixed fallback
- [x] command output records and final stdout/stderr bytes have explicit immutable shapes
- [x] built-in command metadata is complete and exact
- [x] generic external-package command tests use explicit fixture files
- [x] Worker compatibility tests use the composed Worker package fixture/application
- [x] Worker production code and Worker tests use the same six-key command metadata schema
- [x] built-in Kernel operation commands invoke `KernelOpsInterface` directly without a CLI-owned forwarding façade
- [x] `KernelOpsRequestResolver` is a source-host-only input adapter and has no `CommandDescriptor` dependency
- [x] `CommandOutputBuffer` is invocation-local and absent from container definitions
- [x] `platform/cli` does not bind the global `ErrorHandlerInterface` service id
- [x] redacted output maps retain canonical ordering from the shared redaction runtime without CLI-local recursive sorting
- [x] the source host resolves exactly one `SensitiveDataRedactorInterface` implementation
- [x] command failures clear and reuse the same invocation-local `CommandOutputBuffer`
- [x] every `cli.command` service id is the exact command class FQCN
- [x] all four canonical presets select `platform.cli` by default and preserve their existing Core and Worker policies
- [x] `platform.redaction` is enabled through the canonical `platform.cli` module dependency closure, without duplicate preset roots
- [x] `coretsia/framework` directly requires the baseline Kernel and CLI packages
- [x] `coretsia/platform-cli` declares the corresponding Composer and runtime module dependencies for Redaction
- [x] `composer create-project coretsia/skeleton` installs the baseline Composer graph without custom repository declarations or implicit dependency synchronization
- [x] the default `micro` preset resolves the intended Console ModulePlan from the installed baseline
- [x] additional mode-required packages remain subject to explicit DependencySync
- [x] canonical Kernel preset and consumer documentation examples match the new module sets
- [x] existing Kernel boot, preset schema, package planning, and consumer installation tests remain valid
- [x] the released baseline is independently installable from published Composer packages

---

### 2.40.0 Platform CLI — Deterministic Workflows + Smart Suggestions (SHOULD) [IMPL]

---
type: package
phase: 2
epic_id: "2.40.0"
owner_path: "packages/platform/cli/"

package_id: "platform/cli"
composer: "coretsia/platform-cli"
kind: runtime
module_id: "platform.cli"

goal: "Надати `coretsia workflow:run <workflow>` як детермінований config-defined composite execution поверх tag-first CommandCatalog, з окремим canonical CLI UoW для кожного step, одним фінальним redacted render і стабільними suggestions для невідомих command names. Покращити human-readable CLI output через наявний output pipeline без зміни машинного JSON-контракту."
provides:
- "Config-defined workflows without filesystem discovery, templates, macros, environment interpolation, or reflective command construction"
- "Every workflow step resolved through the final tag-first CommandCatalog"
- "Every workflow step executed through the existing CommandRunner with exactly one Kernel CLI UoW"
- "One platform-owned composite-command boundary for `workflow:run`, without a nested outer CLI UoW"
- "Fail-fast sequential workflow execution with ordered step results"
- "Deterministic unknown-command suggestions derived only from the final CommandCatalog"
- "One final output formatting and mandatory defense-in-depth redaction pass"
- "Readable adaptive and table output for nested CLI results across supported terminal environments"
- "Human-readable ultra-early doctor diagnostics without Kernel host boot or a second output pipeline"
- "Preserved canonical coretsia.cli-output@1 JSON output for explicit machine-readable invocation"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-0035-cli-composite-workflows.md"
ssot_refs:
- "docs/ssot/tags.md"
- "docs/ssot/observability.md"
- "docs/ssot/sensitive-data-redaction.md"
- "docs/ssot/context-keys.md"
- "docs/ssot/context-store.md"
- "docs/ssot/stateful-services.md"
---

### Scope correction and replacement boundary (MUST)

This epic replaces the earlier incomplete 2.40.0 draft.

The implementation scope is single-choice:

- deterministic config-defined workflows;
- deterministic command-name suggestions;
- human-readable CLI output improvements through the existing formatters and output policy;
- human-readable ultra-early doctor diagnostics through the existing doctor path;
- no CLI replay persistence capability.

This epic MUST NOT introduce:

- `cli_replay@1` or `cli-replay@1`;
- `docs/ssot/cli-replay.md`;
- replay storage, recording, playback, retention, listing, or cleanup services;
- a replay path config key;
- a package-local redaction engine;
- command argument or option recording;
- raw argv persistence;
- output transcript persistence.
- an interactive prompt service;
- a confirmation service;
- a preview renderer;
- a workflow-specific diagnostics renderer;
- a workflow-specific redaction service;
- a suggestion-specific redaction service;
- a second output or formatting pipeline;
- machine-readable command schema generation, JSON Schema export, or reflection-derived command metadata;
- a generic CLI orchestration framework beyond the exact composite-command exception defined by this epic.

Replay is excluded because the previous text did not define one coherent capability boundary for executable replay versus output playback, redaction handoff, persistence trigger, storage identity, atomic publication, read validation, retention, or consumer semantics.

A future replay capability MUST be specified in a separate epic before any artifact identity or persistence API is introduced.

The previous example:

```text
coretsia workflow:run verify --mode=enterprise
```

is replaced by:

```text
coretsia workflow:run verify
```

`workflow:run` MUST NOT declare `--mode` or `--preset`.

A workflow definition contains the exact command arguments and command-owned options for every step. Kernel operation steps therefore carry their own explicit `target` option inside the workflow definition.

Workflow configuration MUST NOT modify Kernel Bootstrap preset selection.

### Dependencies (MUST)

#### Epic prerequisites (MUST)

- 2.20.0 — Kernel Ops façade and source-operations host exist.
- 2.25.0 — `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` exists and one implementation is available in the source host.
- 2.30.0 — tag-first CLI baseline exists, including:
  - `CommandCatalog`;
  - `CommandDescriptor`;
  - `CommandTagSchema`;
  - `ArgvInputParser` and normalized `InputInterface` implementation;
  - `CommandInputValidator`;
  - `CommandRunner`;
  - invocation-local `CommandOutputBuffer`;
  - immutable `CommandOutputBatch`;
  - `OutputFormatter`;
  - `CliApplication`;
  - exact six-key `cli.command` metadata;
  - one canonical Kernel CLI UoW for every normal command;
  - mandatory final output redaction.

#### Required contracts and runtime APIs (MUST)

- `Coretsia\Contracts\Cli\Command\CommandInterface`
- `Coretsia\Contracts\Cli\Input\InputInterface`
- `Coretsia\Contracts\Cli\Output\OutputInterface`
- `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
- `Coretsia\Foundation\Tag\ReservedTags`
- `Coretsia\Kernel\Runtime\UnitOfWorkType`
- `Psr\Container\ContainerInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- command-owner packages solely for workflow discovery or execution;
- `integrations/*` solely for workflow discovery or execution;
- external console frameworks or workflow engines;
- filesystem scanning for workflow definitions;
- filesystem scanning for commands;
- reflection-based command construction;
- `new $fqcn` command construction;
- direct Kernel artifact, module, provider-plan, config-compile, fingerprint, or cache-verification orchestration;
- direct `ContextStore` or reset orchestration access;
- package-local sensitive-data redaction implementation.

### Cross-package modification boundary (MUST)

The only files outside `packages/platform/cli/` that this epic may create or modify are:

- `docs/adr/ADR-0035-cli-composite-workflows.md`
- `docs/adr/INDEX.md`
- `docs/adr/ADR-0034-cli-tag-first-command-catalog.md`
- `docs/ssot/tags.md`
- `docs/ssot/observability.md`

No command-owner package may be modified solely to make its commands workflow-compatible.

### Existing runtime and presentation reuse (MUST)

This epic introduces only platform-owned workflow orchestration over the existing CommandRunner and Kernel UnitOfWork lifecycle.

It MUST NOT introduce an independent command dispatcher, Kernel lifecycle orchestrator, configuration-loading pipeline, command parser, output formatting pipeline, JSON serializer, redaction boundary, or terminal detector.

WorkflowRunner coordinates existing command executions; it does not replace or duplicate CommandRunner, KernelRuntimeInterface, or Kernel-owned lifecycle orchestration.

Ownership remains single-choice:

- Kernel owns UnitOfWork lifecycle, context, hooks, reset, module planning, configuration, and artifact operations.
- Foundation owns canonical JSON-like normalization, stable JSON encoding, and deterministic tag ordering.
- `CommandCatalog` owns final command discovery and descriptors.
- `CommandInputValidator` owns shared command-input normalization and structural validation.
- `CommandRunner` owns normal and composite command execution and command lifecycle observability.
- `WorkflowRunner` owns only ordered workflow-step orchestration.
- `CommandOutputBuffer` and `CommandOutputBatch` own normalized invocation-local output records.
- `OutputFormatter` owns final shared redaction and formatter selection.
- `JsonFormatter`, `TableFormatter`, and `PlainFormatter` retain their existing presentation responsibilities.
- `ConsoleOutputWriter` remains the sole final output sink.
- `TerminalCapabilitiesDetector` remains the only normal-path terminal capability detector.

Workflow infrastructure MUST NOT construct, invoke, or duplicate Kernel runtime internals directly.

Normal-path human-readable output improvements MUST operate on already normalized and redacted output records.

The ultra-early `doctor` path is the existing exception: it uses only fixed allowlisted boolean capability results, performs no shared redaction, and MUST NOT accept arbitrary diagnostic payloads.

Neither path may introduce a second JSON-like validator, recursive structural normalizer, redaction policy, or serialization contract.

### Workflow ownership and execution model (MUST)

`platform/cli` owns only workflow orchestration.

Command owners continue to own:

- command identity;
- arguments and options;
- structural metadata;
- domain validation;
- dependencies;
- command output;
- returned process exit code.

A workflow is an ordered list of existing command invocations.

A workflow MUST NOT:

- define PHP callbacks;
- define service ids;
- define command class names;
- define providers;
- define aliases;
- define environment-variable substitutions;
- define shell fragments;
- define working directories;
- define path scanning;
- define file globs;
- define templates or macros;
- define conditional expressions;
- define loops;
- define parallel branches;
- define retries;
- define continue-on-error policy;
- invoke another composite command.

Workflow execution is sequential and fail-fast.

Workflow execution is not transactional across steps.

WorkflowCatalog validates the complete configured workflow structure and all referenced command identities and execution kinds before the first step executes.

CommandInputValidator performs descriptor-dependent argument and option validation immediately before each individual step is dispatched.

A later step may therefore fail input validation after earlier steps have completed. This is part of the explicitly non-transactional, fail-fast workflow contract.

No independent workflow input validator or duplicate all-step input validation pipeline may be introduced.

A completed step may already have committed Kernel-owned or command-owner side effects before a later step fails or final workflow output rendering fails.

`WorkflowRunner` MUST NOT implement rollback, compensation, transactional coordination, or recovery orchestration.

Fail-fast execution guarantees only that no subsequent step is dispatched after the first non-zero step result or propagated Throwable.

A final output normalization, redaction, or formatting failure MUST NOT be represented as rollback of previously completed steps.

Canonical flow:

```text
workflow:run <workflow>
→ final WorkflowCatalog
→ ordered WorkflowDefinition
→ for each WorkflowStep
   → final CommandCatalog
   → normal CommandDescriptor
   → structured step input construction
   → existing CommandInputValidator
   → fresh child CommandOutputBuffer
   → existing CommandRunner
   → exactly one Kernel CLI UoW
   → lazy command service resolution
   → owner CommandInterface::run(...)
   → child CommandOutputBatch
→ ordered WorkflowResult
→ one top-level OutputInterface JSON-like record
→ one final OutputFormatter redaction + formatting pass
→ one ConsoleOutputWriter write
```

The first non-zero valid step exit code stops the workflow.

On completed workflow execution, `WorkflowRunCommand` returns the exact first non-zero step exit code.

If all steps return `0`, `WorkflowRunCommand` returns `0`.

These guarantees apply to completed workflow execution before the final CLI output boundary.

A subsequent parent output-buffer, normalization, redaction, formatting, or writer failure follows the existing `CliApplication` fail-closed behavior and MAY cause the final process exit code to be `1`.

Such an output failure MUST NOT be represented as a different workflow step result, a successful workflow completion, or rollback of previously completed steps.

A Throwable from a step MUST propagate unchanged through `WorkflowRunner` and enter the existing `CliApplication` error boundary.

`WorkflowRunner` MUST NOT catch, wrap, downgrade, or convert unexpected command Throwables into a completed workflow result.

#### CLI argv resource-bound investigation (MUST)

The existing `ArgvInputParser` remains the only normal CLI argv syntax parser.

CLI argv input has a distinct threat model from HTTP requests, Worker messages, and configuration sources. Existing Foundation JSON-like output bounds MUST NOT be treated as argv parsing bounds.

This epic MUST perform targeted adversarial validation of:

- large numbers of distinct options;
- large numbers of repeated valued options;
- large individual option and positional-argument values;
- malformed command names, option names, and option sequences;
- invalid UTF-8 in command names, option names, and option values;
- repeated CLI-global options and mixed bare/valued occurrences;
- deterministic error reasons and non-disclosure of rejected input.

Rules:

- Preserve the existing separation between syntax parsing, descriptor-driven structural validation, and command-owner domain validation.
- Preserve raw argument and option-value bytes where the current parser permits them; invalid UTF-8 in arbitrary option values MUST NOT automatically become a new parser-level rejection policy.
- Preserve existing safe `CliInputInvalidException` codes and reasons.
- Record parser execution time and memory behavior for increasing input sizes in a targeted non-gating adversarial spike.
- Correct confirmed algorithmic complexity defects within the existing parser.
- Performance measurements MUST NOT become machine-dependent wall-clock assertions in the normal CI suite.
- Do not introduce a generic input security framework, parser replacement, configurable argv limits, or additional parsing stages.
- Do not introduce new global argv count or byte limits solely by copying HTTP, Worker, configuration, or output resource limits.
- Any future numeric argv limit requires a separately justified CLI threat model and explicit compatibility analysis.

### Composite command boundary (MUST)

2.30.0 defines one Kernel CLI UoW around every normal command and forbids nested CLI UoWs.

This epic narrows the earlier 2.30.0 exception wording.

After this epic:

- `doctor` remains the only post-autoload, pre-Kernel-host command path;
- `workflow:run` is the only source-host command allowed to execute without one outer Kernel CLI UoW;
- `workflow:list` remains a normal command and receives exactly one CLI UoW;
- every other normal tagged command receives exactly one CLI UoW;
- every command executed as a workflow step receives exactly one CLI UoW;
- no workflow step may be composite.

`workflow:run` is a platform-owned composite orchestration command and therefore MUST NOT receive an outer Kernel CLI UoW around the complete workflow.

Every actual workflow step still executes through the existing normal `CommandRunner` path and receives exactly one Kernel CLI UoW.

This epic introduces one internal platform-only execution contract:

```text
Coretsia\Platform\Cli\Runner\CompositeCommandInterface
```

Rules:

- it is internal to `platform/cli`;
- it is not a cross-package extension point;
- external command-owner packages MUST NOT import or implement it;
- it is not added to `core/contracts`;
- only the exact built-in `WorkflowRunCommand` service id may use it in this epic;
- its execution method receives:
  - validated `InputInterface`;
  - invocation-local `OutputInterface`;
  - effective output format `json|table|plain`;
- it returns one portable process exit code in `0..255`;
- it MUST NOT start a Kernel UoW directly;
- it MUST NOT write stdout or stderr;
- it MUST NOT render final output.

`CommandTagSchema` continues to require `CommandInterface` for every external tagged command.

The only exception is an immutable owner-maintained allowlist containing the exact `WorkflowRunCommand` service id, which must implement `CompositeCommandInterface`.

Composite capability MUST NOT be declared in `cli.command` metadata.

Unknown metadata keys remain forbidden.

`CommandDescriptor` gains one internal derived execution kind:

```text
normal
composite
```

The execution kind:

- is derived from the validated service-id class;
- is not tag metadata;
- is not configurable;
- is not overrideable;
- is not exported to external packages;
- is not included in command output.

`CommandRunner` remains stateless and becomes re-entrant for sequential workflow-step calls.

For a normal descriptor, existing 2.30.0 behavior is unchanged.

For the exact composite descriptor:

- command resolution remains lazy;
- service/interface validation remains mandatory;
- descriptor-name verification remains mandatory;
- exit-code validation remains mandatory;
- execution occurs without an outer Kernel UoW;
- one top-level `cli.command` observability event is emitted for `workflow:run`;
- every step emits its own existing normal `cli.command` observability through `CommandRunner`;
- no nested UoW is created.

`WorkflowRunner` MUST reject every step whose descriptor execution kind is `composite` before that step is executed.

### Workflow configuration (MUST)

#### Canonical config shape (MUST)

`packages/platform/cli/config/cli.php` adds exactly:

```php
'workflows' => [
    'definitions' => [],
],
```

Canonical dot key:

```text
cli.workflows.definitions
```

No workflow feature flag is introduced.

An empty definitions map means no configured workflows.

The following keys MUST NOT be introduced:

```text
cli.workflows.enabled
cli.workflows.paths
cli.workflows.directories
cli.workflows.scan
cli.workflows.templates
cli.workflows.macros
cli.workflows.retries
cli.workflows.parallel
cli.workflows.continue_on_error
cli.ux.replay.*
cli.replay.*
```

#### Config rules (MUST)

`packages/platform/cli/config/rules.php` is modified so:

- `cli` remains a required map with `additionalKeys = false`;
- `cli.workflows` is a required map with `additionalKeys = false`;
- `cli.workflows` contains exactly `definitions`;
- `cli.workflows.definitions` is a required map;
- `cli.workflows.definitions` permits dynamic map keys structurally with `additionalKeys = true`;
- ConfigValidator validates only the map boundary at the dynamic definitions node;
- deep dynamic workflow validation belongs only to `WorkflowSchema`;
- no second ConfigKernel validator or callback-based rules are introduced.

#### Canonical workflow definition shape (MUST)

Example:

```php
'workflows' => [
    'definitions' => [
        'verify' => [
            'summary' => 'Validate and verify the web target.',
            'steps' => [
                [
                    'command' => 'config:validate',
                    'arguments' => [],
                    'options' => [
                        'target' => 'web',
                    ],
                ],
                [
                    'command' => 'cache:verify',
                    'arguments' => [],
                    'options' => [
                        'target' => 'web',
                    ],
                ],
            ],
        ],
    ],
],
```

Every workflow map value has exactly:

```text
summary
steps
```

`summary`:

- MUST be a non-empty trimmed single-line UTF-8 string;
- MUST contain no Unicode `Cc` control characters, U+2028, or U+2029;
- MUST be at most 256 bytes;
- MUST reuse `JsonLikeNormalizer::normalize()` for canonical JSON-like value normalization;
- MUST reuse `StableJsonEncoder::encodeStable()` to reject malformed UTF-8;
- MUST perform only the workflow-specific trimmed, single-line, byte-length, and forbidden-character checks;
- MUST NOT rely on Foundation JSON-like normalization or `CommandTagSchema::isSafeSummary()` alone to enforce the workflow-specific single-line policy;
- MUST NOT introduce a general-purpose Unicode validator, independent JSON-like normalizer, or second JSON encoder.

`steps`:

- MUST be a non-empty list;
- MUST contain at most 64 entries;
- MUST preserve declared order.

Every step has exactly:

```text
command
arguments
options
```

Workflow names MUST match:

```regex
\A[a-z][a-z0-9-]*\z
```

Command names MUST match `CommandInterface::COMMAND_NAME_PATTERN`.

Argument lists:

- MUST be lists;
- contain strings only;
- preserve declared order;
- contain no NUL, CR, LF, or unsafe control bytes.

Option maps:

- MUST be string-keyed maps;
- option names match `\A[a-z][a-z0-9-]*\z`;
- values are exactly `string|true|list<string>`;
- `true` represents one bare `--name` flag;
- `string` represents one `--name=value` option;
- `list<string>` represents repeated `--name=value` options in declared list order;
- repeatable-value lists MUST be non-empty;
- every repeatable value MUST be a non-empty string;
- a scalar string for a descriptor-declared repeatable required-value option is normalized into a one-element list by the shared `CommandInputValidator`;
- descriptor-dependent repeatability validation MUST NOT be duplicated in `WorkflowSchema` or `WorkflowStepInputFactory`;
- `false` and `null` are forbidden in workflow definitions;
- map keys are normalized in byte-order `strcmp` order;
- repeatable option lists preserve declared value order;
- `format` and `color` are forbidden because they remain CLI-global options;
- values contain no NUL, CR, LF, or unsafe control bytes.

Bounds are fixed and non-configurable:

- maximum workflow-name length: 64 bytes;
- maximum summary length: 256 bytes;
- maximum workflows: 128;
- maximum steps per workflow: 64;
- maximum arguments per step: 32;
- maximum options per step: 32;
- maximum scalar argument or option string: 512 bytes;
- maximum step command-name length: 512 bytes;
- maximum option-name length: 512 bytes;
- maximum repeatable values per option: 32.

Aggregate workflow-definition normalization bounds are fixed and non-configurable:

- maximum JSON-like container depth: 8;
- maximum JSON-like nodes across all definitions: 131072;
- maximum individual string or map-key length: 512 bytes;
- maximum total string bytes across all definitions, including map keys: 8388608 bytes (8 MiB).

Rules:

- These aggregate limits apply to the complete `cli.workflows.definitions` map, not independently to each workflow.
- Aggregate limits MUST be enforced even when every individual workflow, step, argument, and option satisfies its local shape and count bounds.
- Aggregate bounds take precedence when their limits are reached before individual count bounds.
- The existing `JsonLikeNormalizationLimits` MUST be reused without introducing an independent resource-budget implementation.
- Resource-budget violations MUST fail closed with `WorkflowDefinitionInvalidException` and the fixed reason `workflow-definition-invalid`.
- Resource-budget failures MUST NOT expose workflow names, command names, option names, values, or Foundation diagnostic paths.

Unknown keys hard-fail deterministically.

Floats, objects, resources, closures, and Throwables are forbidden.

Workflow names are exposed in `workflow:list` using byte-order `strcmp` order.

Workflow step order is preserved exactly as declared.

### Human-readable CLI output (MUST)

#### Adaptive format policy (MUST)

The existing output policy remains the single source of truth for normal CLI formatting.

The default adaptive policy changes to:

```php
'output' => [
    'format_default' => 'adaptive',
    'adaptive' => [
        'interactive' => 'table',
        'non_interactive' => 'table',
    ],
    'color_default' => 'auto',
    'table' => [
        'max_width' => 120,
    ],
],
```

Rules:

- `adaptive` MUST resolve to the configured interactive or non-interactive format through the existing `FormatResolver`.
- The default `non_interactive` format changes from `plain` to `table`.
- An explicit `--format=json|table|plain|adaptive` MUST override the configured default.
- Explicit `--format=json` MUST preserve the existing `coretsia.cli-output@1` machine-readable schema and canonical JSON encoding.
- Explicit `--format=plain` MUST preserve the existing deterministic plain-record semantics.
- No terminal-specific formatter or alternative CLI output entrypoint may be introduced.
- No Git Bash, PhpStorm, PowerShell, WSL, CI, or environment-variable heuristic may override the resolved output policy.
- Terminal capability detection continues to determine interactivity and ANSI safety, not the semantic structure of command results.
- Non-interactive output MAY contain deterministic tables by default. Machine consumers MUST explicitly request `--format=json`.
- Existing validated `cli.output.*` application overrides remain authoritative.

The output must remain deterministic for the same normalized records, output policy, format selection, and terminal capability inputs.

#### Nested table presentation (MUST)

The existing `TableFormatter` MUST render nested JSON-like values as readable structured sections instead of embedding serialized maps or lists inside individual table cells.

Rules:

- Reuse the existing normalized, redacted `CommandOutputBatch`.
- Preserve canonical Foundation map ordering and original list order.
- Render scalar map entries through the existing key/value table representation.
- Render non-empty nested maps as explicitly labeled nested sections.
- Render scalar lists as indexed rows under their owning section.
- Preserve the existing tabular rendering of homogeneous lists of maps when all displayed column values are scalar.
- Homogeneous lists of maps containing nested maps or lists MUST render those nested values as structured sections rather than serialized table cells.
- Heterogeneous lists containing maps or lists MUST render their entries as indexed structured sections in original list order.
- Mixed `text|json|error` child records MUST remain individually readable within workflow step results.
- No nested collection may be serialized into a compact JSON table cell.
- Preserve grouped command-catalog rendering used by `list`.
- Represent empty maps and lists explicitly without inventing values.
- Do not silently omit nested values.
- Do not flatten or rewrite original JSON-like keys in the source records.
- Do not introduce command-specific table renderers for Kernel operation payloads.
- Reuse existing deterministic cell escaping, visible-width calculation, ANSI decoration, and configured maximum table width.
- Keep recursive presentation bounded by the already normalized Foundation JSON-like input shape.
- Nested maps and lists MUST NOT fall back to compact JSON strings merely because they appear below the root level.
- JSON output MUST remain byte-for-byte compatible with the existing canonical encoder and envelope for equivalent records.

The existing table wrapping algorithm SHOULD prefer whitespace boundaries when a word fits within the available column width.

If no suitable whitespace boundary exists, wrapping MUST fall back to the existing grapheme-aware width algorithm.

Wrapping MUST NOT truncate, reorder, or change the content of cell values.

#### Ultra-early doctor presentation (MUST)

The exact `coretsia doctor` invocation remains dependency-free and uses the existing `DoctorCommand`, `UltraEarlyDoctorRunner`, `PlainFormatter`, and `ConsoleOutputWriter`.

`DoctorCommand` continues to produce the same five boolean diagnostic values:

```
cli_sapi
json_available
pcre_available
php_supported
ready
```

`UltraEarlyDoctorRunner` MUST:

- validate the existing exact diagnostic record shape and boolean value types;
- project only these validated booleans into fixed human-readable text;
- use fixed allowlisted labels and deterministic field order;
- preserve the existing readiness calculation and exit code;
- use the existing plain formatter and final console writer;
- keep ANSI disabled;
- reject additional arguments and options through the existing fixed error boundary;
- retain the existing safe fallback diagnostic on rendering failure.

Canonical successful presentation:

```
Coretsia CLI — Runtime diagnostics

PHP >= 8.4:     OK
CLI SAPI:       OK
JSON extension: OK
PCRE extension: OK

Status: READY
```

False capability values render `FAIL`. A false `ready` value renders `Status: NOT READY`.

The displayed status MUST be derived exclusively from the validated doctor result. It MUST NOT be recomputed from a second runtime probe.

The ultra-early path MUST NOT resolve Kernel, a command catalog, configuration, a shared redactor, or a new diagnostics renderer.

No environment values, local paths, host identifiers, extension inventories, PHP configuration dumps, arguments, or exception details may be emitted.

The exact `doctor` invocation remains the existing fixed early path; this change does not introduce `--format`, `--color`, or a second doctor execution mechanism.

#### Workflow output presentation (MUST)

Completed workflow results MUST use the same existing output formatters as normal commands.

Nested workflow results and ordered step records MUST be readable through the improved generic `TableFormatter`.

No workflow-specific result presenter, diagnostic formatter, output schema, or redaction stage may be introduced.

The machine-readable workflow result retains the existing `coretsia.cli-output@1` outer envelope under explicit `--format=json`.

A completed workflow is still written as one top-level JSON-like output record and passes through one final shared redaction and formatting boundary.

#### Canonical workflow result projection (MUST)

The completed `WorkflowResult` JSON-like payload contains exactly:

- `workflow: string` — the validated canonical workflow name;
- `outcome: success|failure` — derived from the final step exit code;
- `exit_code: int` — the completed workflow exit code in `0..255`;
- `steps: list<map>` — ordered executed-step results.

Every exported step map contains exactly:

- `index: int` — zero-based index in the original workflow definition;
- `command: string` — the canonical executed command name;
- `exit_code: int` — the returned step exit code;
- `records: list<map>` — the ordered projected child output records.

Rules:

- Step indices MUST reflect their original definition positions and MUST NOT be renumbered after fail-fast termination.
- A completed failure result MUST include the first non-zero step.
- Unexecuted steps MUST NOT appear in the result.
- All exported maps use the existing Foundation canonical map ordering.
- The child record projection follows `Nested child-record redaction compatibility (MUST)`.
- No additional workflow result metadata, timestamps, identifiers, arguments, options, or execution context fields may be introduced.
- The existing `coretsia.cli-output@1` outer envelope remains unchanged.

#### Nested child-record redaction compatibility (MUST)

The canonical `CommandOutputBatch::records()` representation remains unchanged inside `WorkflowStepResult`.

When `WorkflowResult` exports child records into the parent JSON-like workflow payload, it MUST apply one fixed structural projection:

- child `text` records retain the exact `text` and `type` keys;
- child `error` records retain the exact `code`, `message`, and `type` keys;
- child `json` records export their normalized content under `data` instead of the internal `payload` key;
- original nested JSON content keys and values remain unchanged before final redaction;
- child record order and original list order remain unchanged.

The structural `payload` key MUST NOT be embedded in exported workflow child JSON records.

The shared `SensitiveKeyClassifier` classifies `payload` as sensitive. Embedding the internal record envelope unchanged would cause the final `OutputFormatter` redaction pass to replace the entire child JSON payload with a redacted summary.

The `data` key is only the workflow result's structural representation of a child JSON record. It MUST NOT bypass the shared redactor or suppress sensitive-key classification inside the original child content.

The projection MUST:

- operate only on already normalized canonical child records;
- preserve every non-sensitive child output value;
- preserve shared redaction of sensitive nested keys and values;
- introduce no second JSON-like normalizer, serializer, redaction service, or output formatter;
- preserve the existing `coretsia.cli-output@1` outer envelope and normal-command record representation;
- remain bounded by the existing final parent output limits.

### Deliverables (MUST)

#### Creates

Workflow model:
- [x] `packages/platform/cli/src/Workflow/WorkflowDefinition.php`
  - [x] immutable readonly value
  - [x] contains canonical name, summary, and ordered non-empty step list
  - [x] no config repository, services, closures, or runtime state

- [x] `packages/platform/cli/src/Workflow/WorkflowStep.php`
  - [x] immutable readonly value
  - [x] contains command name, ordered arguments, and normalized option map
  - [x] no command object, descriptor, service id, or container

- [x] `packages/platform/cli/src/Workflow/WorkflowSchema.php`
  - [x] stateless deep validator and normalizer for dynamic workflow definitions
  - [x] enforces the exact shapes and bounds in this epic
  - [x] performs no command discovery or service resolution
  - [x] reuses `Coretsia\Foundation\Serialization\JsonLikeNormalizer::normalize()` for canonical JSON-like normalization and deterministic recursive map-key ordering
  - [x] supplies the existing `JsonLikeNormalizationLimits` with the exact workflow-definition aggregate bounds specified in this epic
  - [x] applies one bounded normalization pass to the complete `cli.workflows.definitions` map before constructing immutable workflow values
  - [x] performs bounded structural checks without recursively traversing arbitrary nested configuration values before the Foundation normalization boundary
  - [x] preserves Foundation byte-order `strcmp` ordering for workflow names and nested option-map keys
  - [x] preserves declared list order for workflow steps, arguments, and repeatable option values
  - [x] enforces the epic's fixed workflow-specific shapes, individual bounds, and aggregate resource limits before constructing immutable workflow values
  - [x] maps Foundation depth, node-count, individual-string-byte, and total-string-byte violations to `WorkflowDefinitionInvalidException` with reason `workflow-definition-invalid`
  - [x] MUST NOT introduce a second recursive validator, resource-budget walker, or map sorter
  - [x] validates summary UTF-8 through the existing Foundation stable JSON encoder
  - [x] explicitly rejects Unicode `Cc`, `Zl`, and `Zp` characters in workflow summaries
  - [x] preserves the workflow-specific summary policy independently of static command tag metadata validation
  - [x] maps malformed UTF-8 and forbidden summary characters to `WorkflowDefinitionInvalidException` with reason `workflow-definition-invalid`
  - [x] converts Foundation normalization failures into `WorkflowDefinitionInvalidException` with the fixed safe reason `workflow-definition-invalid`
  - [x] MUST NOT expose Foundation diagnostic paths, rejected keys, or rejected values
  - [x] MUST NOT introduce `sort`, `ksort`, `uksort`, `usort`, `array_multisort`, or an independent map-key sorting implementation
  - [x] MUST NOT reimplement Foundation JSON-like normalization or resource-budget traversal
  - [x] throws only `WorkflowDefinitionInvalidException`
  - [x] public diagnostics contain only stable reason tokens

- [x] `packages/platform/cli/src/Workflow/WorkflowCatalog.php`
  - [x] immutable finalized workflow catalog
  - [x] consumes normalized definitions from `WorkflowSchema`
  - [x] receives the final `CommandCatalog`
  - [x] is resolved lazily only when `workflow:list` or `workflow:run` is dispatched
  - [x] MUST NOT be an eager dependency of `CommandCatalog` or normal `CliApplication` construction
  - [x] structurally valid, Kernel-loadable workflow definitions that fail only WorkflowSchema deep validation MUST NOT prevent unrelated normal CLI commands from being discovered or dispatched
  - [x] invalid `cli.workflows` or `cli.workflows.definitions` map boundaries remain Kernel configuration validation failures and MUST fail normal Console host boot
  - [x] validates all configured workflow definitions and referenced command names and execution kinds before any workflow step executes
  - [x] workflow validation failures propagate through the existing CLI error boundary without introducing a separate diagnostics path
  - [x] validates every configured step command exists
  - [x] validates every step command is `normal`, not `composite`
  - [x] MUST NOT perform descriptor-dependent argument or option validation
  - [x] descriptor-dependent input normalization and validation remain owned exclusively by `CommandInputValidator`
  - [x] an unknown configured step command throws `WorkflowDefinitionInvalidException`
    - [x] reason `workflow-command-unknown`
  - [x] a configured composite step throws `WorkflowDefinitionInvalidException`
    - [x] reason `workflow-command-composite`
  - [x] these failures expose no arguments, option values, service ids, paths, or workflow definition payload
  - [x] performs no command service resolution
  - [x] preserves the canonical byte-order workflow-name ordering produced by `WorkflowSchema`
  - [x] MUST NOT independently re-sort or re-normalize workflow definitions
  - [x] preserves step order
  - [x] canonical APIs:
    - [x] `public function definitions(): array`
    - [x] `public function require(string $name): WorkflowDefinition`
  - [x] `require()` validates the requested lookup token before map access:
    - [x] same workflow-name regex
    - [x] maximum 64 bytes
    - [x] no control bytes
  - [x] an invalid lookup token throws `WorkflowNotFoundException` without exposing the token
  - [x] an unknown but valid lookup token throws `WorkflowNotFoundException` without exposing the token
  - [x] lookup failures MUST NOT include the requested workflow name in public or internal exception messages

- [x] `packages/platform/cli/src/Workflow/WorkflowStepInputFactory.php`
  - [x] stateless structured-input adapter
  - [x] creates the existing immutable `ArgvInput` implementation from one validated `WorkflowStep`, before descriptor-dependent normalization and structural validation
  - [x] MUST NOT use shell parsing or shell escaping
  - [x] MUST NOT read raw argv, config, env, CWD, or filesystem
  - [x] constructs the structured `ArgvInput` directly; the shared `CommandInputValidator::normalizeAndValidate()` performs subsequent descriptor-dependent normalization and validation without reparsing generated tokens
  - [x] constructs the structured `ArgvInput` without duplicating descriptor-dependent repeatable-option normalization
  - [x] preserves a single configured string value until the shared `CommandInputValidator::normalizeAndValidate()` boundary
  - [x] preserves configured repeatable lists in their declared order
  - [x] MUST NOT independently convert scalar values into repeatable lists based on command descriptors
  - [x] constructs canonical command-facing `tokens()` only as the stable token projection of the structured step:
    - [x] command name first
    - [x] option keys in byte-order `strcmp` order
    - [x] `true` as `--name`
    - [x] `string` as `--name=value`
    - [x] `list<string>` as repeated `--name=value` tokens preserving list order
    - [x] when positional arguments exist, one `--` marker before the first argument
    - [x] positional arguments in declared order
  - [x] CLI-global `format|color` tokens are never generated
  - [x] the existing `CommandInputValidator` validates normalized arguments and options, not by reparsing `tokens()`
  - [x] performs only deterministic structured-input construction
  - [x] MUST NOT invoke `CommandInputValidator`
  - [x] MUST NOT perform descriptor, argument, option, required-value, or repeatability validation
  - [x] MUST NOT implement a second argument/option validation policy

- [x] `packages/platform/cli/src/Workflow/WorkflowStepResult.php`
  - [x] immutable readonly value
  - [x] contains exactly:
    - [x] `index: int`
    - [x] `command: string`
    - [x] `exitCode: int`
    - [x] `records: list<normalized output record>`
  - [x] contains no arguments, options, raw tokens, command object, service id, or paths added by workflow infrastructure

- [x] `packages/platform/cli/src/Workflow/WorkflowResult.php`
  - [x] immutable readonly value
  - [x] contains exactly:
    - [x] workflow name
    - [x] `success|failure` outcome
    - [x] final exit code
    - [x] ordered executed-step results
  - [x] contains no unexecuted synthetic steps
  - [x] exports one JSON-like map composed only of validated workflow metadata and safe structural projections of already normalized child output records
  - [x] preserves canonical child records unchanged inside `WorkflowStepResult`
  - [x] projects nested child `json` record content from the internal `payload` field into the exported `data` field
  - [x] preserves nested child `text` and `error` record fields without structural rewriting
  - [x] preserves original nested content keys and values until mandatory final redaction
  - [x] delegates final JSON-like normalization and resource-limit enforcement to the existing parent `CommandOutputBuffer::json()` boundary
  - [x] MUST NOT implement a second recursive normalizer, resource-budget walker, JSON encoder, or redaction pass

- [x] `packages/platform/cli/src/Workflow/WorkflowRunner.php`
  - [x] stateless and re-entrant
  - [x] receives:
    - [x] final `CommandCatalog`
    - [x] existing `CommandInputValidator`
    - [x] existing `CommandRunner`
    - [x] `WorkflowStepInputFactory`
  - [x] for every step:
    - [x] obtains the normal descriptor from the final `CommandCatalog`
    - [x] constructs structured input through `WorkflowStepInputFactory`
    - [x] invokes `CommandInputValidator::normalizeAndValidate()` exactly once
    - [x] creates one fresh invocation-local `CommandOutputBuffer` only after successful step input normalization and validation
    - [x] passes the returned normalized `ArgvInput` and fresh child buffer to `CommandRunner`
    - [x] invokes `CommandRunner` only after successful validation
  - [x] a step input-validation Throwable propagates unchanged through the existing CLI error boundary
  - [x] a validation failure produces no completed `WorkflowResult`, including when earlier steps have completed
  - [x] records from previously completed steps MUST NOT be rendered as partial workflow output after validation failure
  - [x] no child buffer is created or finalized for a step rejected before dispatch
  - [x] no subsequent workflow step is resolved or dispatched after validation failure
  - [x] MUST NOT resolve command services directly
  - [x] MUST NOT invoke `CommandInterface::run()` directly
  - [x] MUST NOT invoke `KernelRuntimeInterface` directly
  - [x] MUST NOT invoke hooks or reset orchestration
  - [x] passes the same effective top-level output format to every step `CommandRunner` call
  - [x] finalizes every child output buffer exactly once when `CommandRunner` returns a valid exit code in `0..255`
  - [x] includes the first non-zero step and its completed child records in the final `WorkflowResult`
  - [x] never finalizes the child buffer of a throwing step
  - [x] preserves child record order
  - [x] stops on the first non-zero returned exit code
  - [x] never creates synthetic results for unexecuted subsequent steps
  - [x] MUST NOT catch Throwables for translation, wrapping, observability, or downgrade
  - [x] MAY catch a step Throwable only to discard the current invocation-local child buffer
  - [x] after discard, MUST rethrow the exact same Throwable instance
  - [x] if a step throws, no completed `WorkflowResult` is produced
  - [x] records from earlier completed steps are not rendered as partial workflow output
  - [x] no child output from the failed workflow invocation reaches the final formatter
  - [x] if a step throws:
    - [x] the current child buffer is not appended to a completed `WorkflowResult`
    - [x] the current child buffer is discarded before propagation
    - [x] no partial child batch is formatted or written
    - [x] no later step is resolved
    - [x] the original Throwable propagates unchanged

Composite execution:
- [x] `packages/platform/cli/src/Runner/CompositeCommandInterface.php`
  - [x] internal platform-only interface
  - [x] canonical API:
    - [x] `public function name(): string`
    - [x] `public function run(InputInterface $input, OutputInterface $output, string $outputFormat): int`
  - [x] `name()` MUST return the exact command `NAME` constant
  - [x] `run()` receives:
    - [x] validated `InputInterface`
    - [x] invocation-local `OutputInterface`
    - [x] effective format `json|table|plain`
  - [x] `run()` returns one portable process exit code in `0..255`
  - [x] the interface MUST NOT extend `CommandInterface`
  - [x] one service MUST NOT implement both `CommandInterface` and `CompositeCommandInterface`
  - [x] no external package extension semantics

Commands:
- [x] `packages/platform/cli/src/Command/WorkflowListCommand.php`
  - [x] normal `CommandInterface` command
  - [x] receives only `WorkflowCatalog`
  - [x] renders safe workflow names and summaries
  - [x] does not expose step arguments or option values
  - [x] metadata:
    - [x] `NAME = 'workflow:list'`
    - [x] `SUMMARY = 'List configured CLI workflows.'`
    - [x] `GROUP = 'workflow'`
    - [x] `HIDDEN = false`
    - [x] `ARGUMENTS = []`
    - [x] `OPTIONS = []`

- [x] `packages/platform/cli/src/Command/WorkflowRunCommand.php`
  - [x] implements internal `CompositeCommandInterface`
  - [x] does not implement `Coretsia\Contracts\Cli\Command\CommandInterface`
  - [x] `name()` returns `self::NAME`
  - [x] implements the exact `CompositeCommandInterface::run(...)` signature
  - [x] exposes the exact six public command metadata constants:
    - [x] `NAME`
    - [x] `SUMMARY`
    - [x] `GROUP`
    - [x] `HIDDEN`
    - [x] `ARGUMENTS`
    - [x] `OPTIONS`
  - [x] receives only `WorkflowCatalog` and `WorkflowRunner`
  - [x] requires one workflow argument
  - [x] writes exactly one json-like `WorkflowResult` record on completed execution
  - [x] returns the exact workflow final exit code
  - [x] metadata:
    - [x] `NAME = 'workflow:run'`
    - [x] `SUMMARY = 'Run one configured CLI workflow.'`
    - [x] `GROUP = 'workflow'`
    - [x] `HIDDEN = false`
    - [x] `ARGUMENTS` contains exactly:
      - [x] `name = workflow`
      - [x] `summary = Workflow name.`
      - [x] `required = true`
      - [x] `variadic = false`
    - [x] `OPTIONS = []`

Suggestions:
- [x] `packages/platform/cli/src/UX/SmartSuggestor.php`
  - [x] stateless
  - [x] consumes only final visible `CommandDescriptor` names from `CommandCatalog`
  - [x] excludes hidden commands
  - [x] performs byte-wise lowercase ASCII comparison
  - [x] uses `levenshtein()` only on already validated ASCII command names
  - [x] accepts suggestions only for syntactically valid ASCII command-name tokens
  - [x] performs no suggestion calculation when the unknown input exceeds 128 bytes
  - [x] excludes candidate names exceeding 128 bytes from distance calculation
  - [x] skips candidates whose length difference exceeds the applicable distance threshold before invoking `levenshtein()`
  - [x] performs no unbounded comparison or unbounded diagnostic rendering
  - [x] returns an empty suggestion list when input is invalid or outside the suggestion bounds
  - [x] maximum accepted Levenshtein distance is determined exclusively by the unknown input command token length, measured in ASCII bytes:
    - [x] unknown input length `1..8` → `2`
    - [x] unknown input length `9..128` → `3`
  - [x] candidate command-name length MUST NOT change the distance threshold
  - [x] the same threshold applies to every eligible candidate within one suggestion calculation
  - [x] sorts candidates by:
    - [x] distance ascending
    - [x] final CommandCatalog order ascending for ties
  - [x] performs deterministic bounded ranking without `usort`, `uksort`, `uasort`, `array_multisort`, or an independent recursive sorting helper
  - [x] MAY maintain a bounded top-three candidate list through stable insertion
  - [x] preserves final CommandCatalog order for equal-distance candidates
  - [x] MUST NOT mutate or reorder the original CommandCatalog descriptors
  - [x] returns at most three command names
  - [x] performs no option, argument, workflow, filesystem, or package suggestions
  - [x] contains no mutable cache

Errors:
- [x] `packages/platform/cli/src/Exception/WorkflowDefinitionInvalidException.php`
  - [x] code `CORETSIA_CLI_WORKFLOW_DEFINITION_INVALID`
  - [x] code-first deterministic public message
  - [x] accepts exactly these allowlisted public reasons:
    - [x] `workflow-definition-invalid` — invalid workflow names, schema shapes, types, keys, string safety, option values, or bounds
    - [x] `workflow-command-unknown` — referenced command is absent from the final `CommandCatalog`
    - [x] `workflow-command-composite` — referenced command has composite execution kind
  - [x] `WorkflowSchema` uses only `workflow-definition-invalid` for deep definition validation failures
  - [x] `WorkflowCatalog` uses only the two command-reference reasons for unknown or composite steps
  - [x] invalid or non-allowlisted reason tokens are rejected without echoing their values
  - [x] exposes no workflow names, command tokens, definition values, arguments, options, service ids, paths, or previous Throwable messages

- [x] `packages/platform/cli/src/Exception/WorkflowNotFoundException.php`
  - [x] code `CORETSIA_CLI_WORKFLOW_NOT_FOUND`
  - [x] fixed safe reason `workflow-not-found`
  - [x] MUST NOT expose the requested workflow-name token, including when syntactically valid
  - [x] public and internal exception messages contain only the fixed error code and safe reason
  - [x] contains no configured definition or step data

Docs:
- [x] `docs/adr/ADR-0035-cli-composite-workflows.md`
  - [x] records the owner-only composite-command exception
  - [x] records one UoW per workflow step and no outer workflow UoW
  - [x] records fail-fast sequential execution
  - [x] records no replay/artifact scope
  - [x] records no nested workflows or composite steps

#### Modifies

- [x] `packages/platform/cli/src/Output/Formatter/TableFormatter.php`
  - [x] render nested JSON-like maps and lists as readable structured sections
  - [x] preserve existing homogeneous-list table rendering only when displayed column values are scalar
  - [x] render homogeneous and heterogeneous lists containing nested collections as indexed structured sections
  - [x] preserve existing grouped command-catalog output
  - [x] render projected workflow child `text|json|error` records without embedding serialized collections in table cells
  - [x] reuse existing width calculation, cell escaping, ANSI decoration, and deterministic ordering
  - [x] prefer whitespace-aware wrapping without truncation
  - [x] introduce no command-specific or workflow-specific renderer

- [x] `packages/platform/cli/src/Bootstrap/UltraEarlyDoctorRunner.php`
  - [x] preserve existing exact five-boolean doctor payload validation
  - [x] project validated booleans into fixed human-readable diagnostic lines through one pure internal projection method
  - [x] keep the projection method independent of runtime probes, Kernel, container services, and terminal capabilities
  - [x] allow deterministic testing of successful and failed capability values without modifying PHP runtime globals or introducing injectable diagnostic services
  - [x] use existing `PlainFormatter` and `ConsoleOutputWriter`
  - [x] preserve fixed safe failure handling, ANSI suppression, and exit codes
  - [x] introduce no Kernel bootstrap, redactor, terminal detector, or new renderer

- [x] `packages/platform/cli/config/cli.php`
  - [x] add `cli.workflows.definitions = []`
  - [x] change only `cli.output.adaptive.non_interactive` from `plain` to `table`
  - [x] preserve all other existing output and command override defaults unchanged
  - [x] introduce no additional CLI output configuration keys

- [x] `packages/platform/cli/config/rules.php`
  - [x] add exact static `workflows.definitions` boundary
  - [x] preserve `additionalKeys = false` at every static schema-owned level
  - [x] dynamic keys are allowed only under `cli.commands.overrides` and `cli.workflows.definitions`
  - [x] update the existing rules-file documentation to describe both dynamic-key boundaries
  - [x] preserve structural-only ConfigValidator ownership for dynamic definitions
  - [x] keep `WorkflowSchema` as the sole owner of deep workflow definition validation
  - [x] introduce no additional ConfigValidator callbacks or custom rule processors

- [x] `packages/platform/cli/src/Catalog/CommandDescriptor.php`
  - [x] add internal derived `executionKind: normal|composite`
  - [x] derive execution kind only from the validated built-in service identity
  - [x] expose one canonical read-only accessor:
    - [x] `public function executionKind(): string`
    - [x] return value is exactly `normal|composite`
  - [x] `CommandRunner`, `WorkflowCatalog`, and `WorkflowRunner` MUST consume this accessor without independently reclassifying service ids
  - [x] preserve execution kind when `withCatalogView()` creates an immutable presentation override
  - [x] preserve command identity, arguments, options, and service id during catalog-view overrides
  - [x] execution kind is not tag metadata, configurable, overrideable, or included in command output

- [x] `packages/platform/cli/src/Catalog/CommandTagSchema.php`
  - [x] preserve the exact six metadata keys
  - [x] preserve external `CommandInterface` requirement
  - [x] allow `CompositeCommandInterface` only for exact built-in allowlisted service ids
  - [x] validates the allowlisted composite class constants against the same exact six-key metadata schema
  - [x] the composite exception changes only the execution interface requirement, not metadata validation
  - [x] preserve the canonical `CommandDescriptor::fromValidatedMetadata()` to `CommandTagSchema::canonicalMetadata()` validation path
  - [x] apply the same immutable built-in composite allowlist during canonical metadata revalidation
  - [x] do not bypass class-constant validation or construct composite descriptors from unchecked metadata
  - [x] preserve exact service-id, class identity, metadata, and command-name validation for both normal and composite descriptors
  - [x] reject external composite implementations deterministically

- [x] `packages/platform/cli/src/Catalog/CommandCatalog.php`
  - [x] add `public function find(string $name): ?CommandDescriptor`
  - [x] `find()` performs no service resolution
  - [x] preserve final catalog order

- [x] `packages/platform/cli/src/Runner/CommandRunner.php`
  - [x] preserve the normal command path exactly
  - [x] add exact composite branch described in this epic
  - [x] remain stateless and re-entrant
  - [x] preserve output, exit-code, interface, name, and observability validation
  - [x] reuse the existing lazy selected-command resolution and identity-validation policy for normal and composite descriptors
  - [x] the composite branch MUST verify:
    - [x] the resolved service implements `CompositeCommandInterface` and does not implement `CommandInterface`
    - [x] the resolved service's exact runtime class matches `CommandDescriptor::serviceId()`
    - [x] the resolved command's `name()` matches `CommandDescriptor::name()`
    - [x] the returned exit code is an integer in `0..255`
  - [x] invalid composite service identity or exit codes fail through the existing CLI exception boundary
  - [x] composite runtime validation MUST NOT introduce a second dispatcher, independent command-identity policy, or Kernel lifecycle implementation
  - [x] MUST NOT create an outer UoW for `WorkflowRunCommand`, including when composite service validation fails

- [x] `packages/platform/cli/src/Input/CommandInputValidator.php`
  - [x] introduce one canonical normalization-and-validation entrypoint:
    - [x] `public function normalizeAndValidate(CommandDescriptor $descriptor, ArgvInput $input): ArgvInput`
  - [x] move the existing repeatable-required-option scalar-to-list normalization from `CliApplication::normalizeRepeatableOptions()` into this shared boundary
  - [x] preserve the existing `validate()` API and structural validation rules
  - [x] normalize a single scalar value of a declared repeatable required-value option into a one-element ordered list
  - [x] preserve already normalized repeatable lists without changing their order
  - [x] reject invalid option values and repeated non-repeatable options through the existing validation policy
  - [x] return the normalized immutable `ArgvInput` only after successful structural validation
  - [x] perform no owner-domain validation or command service resolution
  - [x] introduce no second parser, validator, or normalization policy

- [x] `packages/platform/cli/src/Input/ArgvInputParser.php`
  - [x] eliminate quadratic array copying when accumulating repeated valued options
  - [x] append additional repeated values directly to the existing option list without retaining a separate temporary reference to that list
  - [x] preserve the existing first-occurrence scalar representation and subsequent ordered list representation
  - [x] preserve original option-value order and command-facing token order
  - [x] preserve existing repeated bare-flag, mixed bare/valued, and duplicate CLI-global option rejection
  - [x] preserve command-name and option-name syntax validation
  - [x] preserve existing `--` positional-argument semantics
  - [x] preserve safe deterministic `CliInputInvalidException` codes and reasons
  - [x] preserve raw string values without introducing implicit UTF-8 normalization or rejection
  - [x] introduce no new parser, generic input limiter, configuration key, or command-specific validation

- [x] `packages/platform/cli/src/Application/CliApplication.php`
  - [x] unknown command selection uses `CommandCatalog::find()`
  - [x] replace private `normalizeRepeatableOptions()` usage with the shared `CommandInputValidator::normalizeAndValidate()` entrypoint
  - [x] remove the redundant private repeatable-option normalization method
  - [x] pass the returned normalized `ArgvInput` to `CommandRunner`
  - [x] preserve one invocation-local `CommandOutputBuffer` for normal and composite dispatch
  - [x] preserve the existing `CommandRunner::run()` dispatch boundary and its argument order
  - [x] preserve discard-before-exception-rendering and exactly one finalization for the top-level output buffer
  - [x] preserve existing `CliCommandOutputBufferIsInvocationLocalContractTest` source and lifecycle invariants
  - [x] preserve existing validation-before-service-resolution ordering
  - [x] preserves `CliInputInvalidException::ERROR_CODE` and `REASON_COMMAND_UNKNOWN` for unknown commands
  - [x] emits the existing canonical unknown-command error record through `ExceptionRenderer`
  - [x] obtains suggestions only through `SmartSuggestor`
  - [x] suggestion calculation is best-effort and MUST NOT replace the original unknown-command exception
  - [x] suggestion calculation failures MUST NOT change the canonical unknown-command error code, reason, or exit code
  - [x] suggestion calculation failures produce no suggestion record and preserve the existing safe error-rendering path
  - [x] suggestion failures MUST NOT expose exception messages, candidate names, or internal catalog details
  - [x] when suggestions exist, appends one json-like record:
    - [x] `suggestions => list<string>`
  - [x] keeps error records on stderr in `plain|table` and inside the canonical JSON envelope in `json`
  - [x] preserves record ordering within each output stream without promising cross-stream write ordering
  - [x] suggestions pass through the same final formatter and redactor
  - [x] no suggestion data enters logs, metrics, or UoW attributes

- [x] `packages/platform/cli/src/Diagnostics/CliErrorHandler.php`
  - [x] add known safe mappings for:
    - [x] `WorkflowDefinitionInvalidException`
    - [x] `WorkflowNotFoundException`
  - [x] preserve each exception’s stable public error code
  - [x] use fixed safe public messages
  - [x] MUST NOT include workflow definitions, step data, arguments, option values, service ids, paths, or previous Throwable messages
  - [x] returned descriptor extensions remain empty

- [x] `packages/platform/cli/src/Provider/CliServiceFactory.php`
  - [x] remains the only `cli.*` config consumer
  - [x] reads the complete validated `cli` root through the existing helper
  - [x] constructs normalized workflow definitions through `WorkflowSchema`
  - [x] performs no command service resolution

- [x] `packages/platform/cli/src/Provider/CliServiceProvider.php`
  - [x] registers workflow infrastructure and commands through the existing declarative definition-provider mechanism
  - [x] preserves the existing source-only registration boundary for built-in Kernel operation commands
  - [x] defines workflow services with the existing `ContainerDefinitionBuilder` and canonical `CliServiceFactory` methods
  - [x] avoids duplicate source/runtime service registration
  - [x] tags `WorkflowListCommand` and `WorkflowRunCommand` with exact six-key metadata
  - [x] tag priority is actual `0`
  - [x] references command constants only
  - [x] does not conditionally register commands from config
  - [x] preserves source-container and compiled-definition parity

- [x] `packages/platform/cli/tests/Integration/NonInteractiveAdaptiveOutputUsesPlainFormatTest.php`
  - [x] rename to `NonInteractiveAdaptiveOutputUsesTableFormatTest.php`
  - [x] rename the test class consistently with the new filename
  - [x] update packaged-default assertions from non-interactive `plain` to `table`
  - [x] verify deterministic table rendering without ANSI for non-interactive output
  - [x] preserve explicit `--format=json` and canonical JSON envelope assertions
  - [x] preserve the separate injected-policy scenario with explicitly configured non-interactive `plain`
  - [x] preserve proof that injected terminal capabilities, not environment heuristics, determine adaptive policy selection

- [x] `packages/platform/cli/tests/Integration/UltraEarlyDoctorUsesSafeFixedOutputPipelineTest.php`
  - [x] replace assertions expecting JSON-decoded doctor stdout with exact human-readable output assertions
  - [x] preserve fixed boolean capability ownership and exit-code behavior
  - [x] preserve pre-host execution, ANSI suppression, safe errors, and sensitive-data non-disclosure
  - [x] preserve the identical `DoctorCommand` implementation used by the normal catalog
  - [x] update source-level assertions only where required by the fixed text projection

- [x] `packages/platform/cli/tests/Integration/DoctorBypassesKernelOperationsHostTest.php`
  - [x] replace JSON parsing of doctor stdout with assertions against the canonical human-readable readiness status
  - [x] preserve proof that invalid Kernel configuration cannot prevent the ultra-early doctor command from executing
  - [x] preserve the normal CLI source-host failure assertions

- [x] `packages/platform/cli/tests/Contract/CliConfigSubtreeShapeContractTest.php`
  - [x] update the exact default config shape to include `workflows.definitions`
  - [x] update the expected adaptive non-interactive default from `plain` to `table`
  - [x] preserve static JSON-like configuration and no-runtime-input assertions

- [x] `packages/platform/cli/tests/Contract/CliConfigRulesCoverAllDefaultsContractTest.php`
  - [x] preserve exact correspondence between every static CLI default and its ConfigRuleset definition
  - [x] add strict unknown-key rejection coverage for `cli.workflows`
  - [x] verify `cli.workflows` contains only the required `definitions` key
  - [x] verify dynamic workflow-name keys are allowed structurally under `cli.workflows.definitions`
  - [x] verify deep workflow definition validation remains outside ConfigValidator ownership
  - [x] preserve existing strict boundaries for `commands`, `output`, `adaptive`, and `table`
  - [x] preserve safe violation reasons without configuration values

- [x] `packages/platform/cli/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [x] update the exact CLI default and config-rule key assertions for the new `workflows` subtree
  - [x] update the expected adaptive non-interactive default to `table`
  - [x] preserve all existing module, provider, and output-policy assertions

- [x] `packages/platform/cli/tests/Contract/CliServiceProviderSeparatesSourceOnlyKernelOpsWiringContractTest.php`
  - [x] extend exact source-host command tag assertions with `WorkflowListCommand` and `WorkflowRunCommand`
  - [x] extend canonical runtime-definition service and tag assertions with both workflow commands
  - [x] preserve exact six-key metadata and priority-zero assertions
  - [x] preserve source-only Kernel Ops registration and constructor-injection assertions
  - [x] preserve the prohibition on Kernel Ops implementation services in generic compiled CLI definitions
  - [x] verify workflow infrastructure is registered through canonical declarative definitions without duplicate bindings

- [x] `packages/platform/cli/tests/Unit/FormatResolverTest.php`
  - [x] add coverage resolving the updated packaged adaptive defaults to `table` for both interactive and non-interactive capabilities
  - [x] preserve existing tests for explicit format overrides and application-configured adaptive mappings
  - [x] preserve the valid explicitly configured non-interactive `plain` scenario
  - [x] preserve no-environment-dependency assertions

- [x] `packages/platform/cli/tests/Unit/CliServiceFactoryReadsValidatedCliRootTest.php`
  - [x] update the canonical test configuration root to include `workflows.definitions = []`
  - [x] align the packaged-default test fixture with `adaptive.non_interactive = table`
  - [x] extend exact single-root-read coverage to every new workflow-related config-dependent factory
  - [x] preserve the existing `has('cli')` and `get('cli')` access pattern
  - [x] reject child-path reads, full-tree reads, and configuration source-metadata reads
  - [x] preserve fail-closed behavior for missing or structurally invalid CLI roots
  - [x] preserve no command service resolution during workflow catalog construction

- [x] `packages/platform/cli/tests/Unit/ArgvInputParserTest.php`
  - [x] preserve existing repeated-option, global-option, and end-of-options regression coverage
  - [x] add adversarial fixtures with large numbers of distinct command-owned options
  - [x] add adversarial fixtures with at least 20,000 repeated valued-option occurrences
  - [x] verify repeated values preserve their exact count and original order
  - [x] verify very large string values retain their exact bytes at the syntax-parsing boundary
  - [x] verify invalid UTF-8 command and option names fail through existing safe syntax errors
  - [x] verify invalid UTF-8 option values are not silently rewritten or rejected by an unrelated parser-level policy
  - [x] verify complex malformed inputs produce deterministic safe exception codes and reasons
  - [x] verify exception messages never contain rejected raw values or malformed tokens
  - [x] verify duplicate CLI-global options and mixed bare/valued occurrences retain existing rejection semantics
  - [x] preserve deterministic parser behavior across repeated invocations in the same process
  - [x] cover the repeated-valued-option accumulation regression without wall-clock CI thresholds
  - [x] introduce no new generic input validation or resource-budget testing framework

- [x] `packages/platform/cli/README.md`
  - [x] document exact workflow config shape
  - [x] document fail-fast behavior
  - [x] document one UoW per step
  - [x] replace the earlier “doctor is the only pre-UoW command” wording with:
    - [x] `doctor` is the only pre-host command
    - [x] `workflow:run` is the only source-host composite command without an outer UoW
    - [x] every normal command and every workflow step has exactly one UoW
  - [x] document no nested workflows
  - [x] document no `--mode|--preset` workflow semantics
  - [x] document deterministic suggestions
  - [x] document the updated adaptive non-interactive table default
  - [x] document explicit `--format=json` for machine-readable output
  - [x] document readable nested table rendering without changing JSON schemas
  - [x] document fixed human-readable ultra-early doctor diagnostics
  - [x] clarify that terminal detection controls adaptive policy selection and ANSI safety, not a separate output pipeline
  - [x] explicitly state replay persistence is not provided

- [x] `docs/ssot/tags.md`
  - [x] preserve exact six-key metadata
  - [x] document the single platform-owned composite service-id exception
  - [x] keep composite capability out of tag metadata
  - [x] external command services still require `CommandInterface`

- [x] `docs/ssot/observability.md`
  - [x] retain existing `cli.command` names and labels
  - [x] document that `workflow:run` emits one top-level command observation without a Kernel UoW
  - [x] each workflow step emits its own normal `cli.command` observation inside its own UoW
  - [x] workflow name, step arguments, and option values are forbidden metric labels
  - [x] step arguments and option values are forbidden span attributes
  - [x] `CommandRunner` remains the sole owner of both normal and composite CLI command lifecycle observability
  - [x] preserve the existing canonical `cli.command` span attributes for composite execution, including bounded `format`
  - [x] preserve exact `cli.command_total` and `cli.command_duration_ms` metric labels and one observation per top-level composite invocation
  - [x] document composite span lifetime without an outer Kernel UoW
  - [x] prohibit context-derived correlation and UoW identifiers in top-level composite logs and spans
  - [x] preserve best-effort telemetry and primary exception precedence
  - [x] `WorkflowRunner` MUST NOT emit duplicate step or workflow lifecycle signals
  - [x] `WorkflowRunCommand` MUST NOT emit lifecycle signals
  - [x] one user workflow invocation emits:
    - [x] one top-level `cli.command` observation for `workflow:run`
    - [x] zero or more normal `cli.command` observations, one for each executed step
  - [x] unexecuted steps emit no observability

- [x] `docs/adr/ADR-0034-cli-tag-first-command-catalog.md`
  - [x] document the explicit platform-owned composite-command exception to the normal one-UoW-per-command rule
  - [x] preserve existing normal command, Kernel Ops, and output ownership
  - [x] document reuse of the existing presentation pipeline for improved human-readable output
  - [x] preserve the canonical machine-readable JSON contract

- [x] `docs/adr/INDEX.md`
  - [x] register ADR-0035-cli-composite-workflows.md

### Output, redaction, context, and state (MUST)

- [x] Workflow infrastructure MUST NOT write stdout or stderr.
- [x] Final workflow output MUST pass through the existing mandatory `OutputFormatter` redaction boundary exactly once.
- [x] The existing `OutputFormatter` remains the sole workflow and suggestion rendering consumer of `SensitiveDataRedactorInterface`.
- [x] `WorkflowRunCommand`, `WorkflowRunner`, `WorkflowCatalog`, `WorkflowSchema`, `SmartSuggestor`, and workflow result DTOs MUST NOT:
  - [x] receive `SensitiveDataRedactorInterface`
  - [x] resolve a redactor
  - [x] instantiate a concrete redactor
  - [x] define a redaction classifier, policy, hasher, or pattern registry
- [x] Workflow results, suggestions, and workflow error records MUST NOT bypass the existing 2.30.0 output/redaction pipeline.
- [x] Child command batches MUST NOT be redacted separately.
- [x] Workflow result aggregation MUST remain subject to the existing `CommandOutputBuffer` and Foundation JSON-like normalization limits.
- [x] A completed workflow result that exceeds the existing parent output record or byte limits MUST fail closed through the existing CLI output error boundary.
- [x] No partial child output may be written when parent result normalization or buffering fails.
- [x] Workflow infrastructure MUST NOT implement a second aggregate-output resource-budget walker or JSON encoder.
- [x] Existing per-child buffer limits remain authoritative for each executed step.
- [x] The workflow's maximum step count MUST NOT be interpreted as permission to exceed the final parent output limits.
- [x] This epic introduces no prompts, confirmations, previews, or separate interactive diagnostic-output path.
- [x] Child step batches MUST NOT be formatted separately.
- [x] Child step batches MUST NOT be written directly.
- [x] Workflow infrastructure MUST NOT persist output.
- [x] Workflow infrastructure MUST NOT log arguments, options, child records, paths, payloads, or workflow definition values.
- [x] All 2.30.0 context, UoW, reset, observability, and redaction rules remain normative except for the explicitly documented missing outer UoW around `workflow:run`.
- [x] The `CommandRunner` composite branch owns the top-level `workflow:run` lifecycle observation.
- [x] `WorkflowRunCommand` and `WorkflowRunner` MUST NOT receive:
  - [x] `ContextAccessorInterface`
  - [x] `ContextKeys`
  - [x] `TracerPortInterface`
  - [x] `MeterPortInterface`
  - [x] `LoggerInterface`
  - [x] `Stopwatch`
- [x] `WorkflowRunCommand` and `WorkflowRunner` MUST NOT emit command lifecycle spans, metrics, or logs.
- [x] Top-level `workflow:run` observability MUST NOT:
  - [x] synthesize a correlation id
  - [x] synthesize a UoW id
  - [x] read correlation or UoW ids left by a completed step
  - [x] aggregate step correlation or UoW ids
- [x] Each normal step retains the existing 2.30.0 context policy inside its own Kernel UoW.
- [x] Step correlation ids and UoW ids MUST NOT be included in `WorkflowStepResult` or `WorkflowResult`.
- [x] Top-level `workflow:run` observability reuses the existing `CommandRunner` CLI command instrumentation lifecycle.
- [x] The composite `cli.command` span starts before workflow-step execution and ends after composite execution completes or throws.
- [x] Initial span attributes are exactly:
  - [x] `operation`
  - [x] `format`
- [x] Final span attributes are exactly:
  - [x] `operation`
  - [x] `format`
  - [x] `outcome`
  - [x] `exit_code`
- [x] `format` remains bounded to `json|table|plain`.
- [x] Composite execution emits exactly one top-level `cli.command_total` increment and one `cli.command_duration_ms` observation.
- [x] Metric labels remain exactly `operation|outcome`.
- [x] Top-level completion/failure logs contain only `operation`, `outcome`, and `exit_code`.
- [x] Top-level composite observability MUST NOT read or emit context-derived correlation or UoW identifiers.
- [x] Observability failures MUST NOT alter the primary command outcome or exception.
- [x] The composite branch MUST reuse the existing instrumentation ownership and MUST NOT introduce a second telemetry implementation or Kernel UoW.
- [x] Workflow name MUST NOT be a metric label.
- [x] Platform CLI MUST NOT write `ContextStore` directly.
- [x] Every step UoW is created and completed only by the existing `CommandRunner` and `KernelRuntimeInterface` path.
- [x] Reset occurs after each step through the canonical Kernel UoW lifecycle.
- [x] Shared `WorkflowCatalog` MAY retain immutable validated `WorkflowDefinition` and `WorkflowStep` values.
- [x] Shared workflow services MUST NOT retain invocation-local execution state, current-step state, child buffers, mutable results, or output batches.
- [x] `WorkflowRunner`, `WorkflowSchema`, and `WorkflowStepInputFactory` remain stateless and re-entrant.
- [x] `WorkflowCatalog`, `WorkflowDefinition`, and `WorkflowStep` remain immutable.
- [x] Child buffers and completed workflow results remain invocation-local.
- [x] No workflow service implements `ResetInterface`.

### Tests (MUST)

- Unit:
  - [x] `packages/platform/cli/tests/Unit/WorkflowRunnerUsesFreshChildBufferPerStepTest.php`
    - [x] every executed workflow step receives a distinct invocation-local `CommandOutputBuffer`
    - [x] child buffers are not reused across steps or workflow invocations
    - [x] a normally returned step finalizes its own child buffer exactly once
    - [x] records from one step never enter another step's child buffer
    - [x] child buffers are never stored in shared workflow services
    - [x] no child buffer writes directly to stdout or stderr
    - [x] no child batch is formatted or redacted independently

  - [x] `packages/platform/cli/tests/Unit/WorkflowCatalogRejectsUnknownCommandTest.php`
    - [x] a structurally valid workflow referencing an unknown command is rejected before any step executes
    - [x] command lookup uses only the final `CommandCatalog`
    - [x] an unknown referenced command throws `WorkflowDefinitionInvalidException`
    - [x] the exact public reason is `workflow-command-unknown`
    - [x] validation performs no command service resolution
    - [x] rejection exposes no unknown command token, arguments, options, service ids, or definition values
    - [x] no preceding or subsequent workflow step is dispatched

  - [x] `packages/platform/cli/tests/Unit/WorkflowCatalogRejectsCompositeStepTest.php`
    - [x] a workflow referencing `workflow:run` as a step is rejected before any step executes
    - [x] composite classification uses `CommandDescriptor::executionKind()`
    - [x] the catalog rejects every referenced descriptor classified as `composite`
    - [x] rejection throws `WorkflowDefinitionInvalidException`
    - [x] the exact public reason is `workflow-command-composite`
    - [x] no composite or normal command service is resolved during validation
    - [x] no workflow step is dispatched after rejection

  - [x] `packages/platform/cli/tests/Unit/TableFormatterNestedJsonLikePresentationTest.php`
    - [x] nested maps render as labeled sections instead of compact JSON cells
    - [x] scalar lists render as ordered indexed rows
    - [x] homogeneous lists of maps preserve existing table rendering
    - [x] homogeneous lists of maps with nested values render those values as structured sections
    - [x] heterogeneous lists of maps retain every entry and original list order
    - [x] mixed text, JSON, and error workflow records remain readable without embedded compact JSON
    - [x] nested workflow step records remain readable within the configured maximum table width
    - [x] empty collections remain visible
    - [x] canonical map and list ordering is preserved
    - [x] repeated rendering is byte-identical
    - [x] configured maximum width is respected
    - [x] no cell content is truncated or lost

  - [x] `packages/platform/cli/tests/Unit/CommandInputValidatorSharedNormalizationTest.php`
    - [x] a single repeatable required-value option is normalized into a one-element list
    - [x] multiple values preserve declared order
    - [x] non-repeatable options retain existing rejection behavior
    - [x] normal commands and workflow steps use the same normalization policy

  - [x] `packages/platform/cli/tests/Unit/CommandDescriptorCompositeKindPreservesOverridesTest.php`
    - [x] catalog-view overrides preserve derived execution kind
    - [x] execution kind cannot be configured or exported
    - [x] `executionKind()` returns the derived canonical `normal|composite` value
    - [x] normal, composite, catalog, and runner paths consume one shared execution-kind classification

  - [x] `packages/platform/cli/tests/Unit/SmartSuggestorInputBoundsTest.php`
    - [x] invalid or oversized input produces no suggestions
    - [x] oversized candidates are skipped
    - [x] length-difference pruning preserves canonical suggestion ranking

  - [x] `packages/platform/cli/tests/Unit/WorkflowStepInputFactoryCanonicalProjectionTest.php`
    - [x] option keys use `strcmp` order
    - [x] bare flags use `true`
    - [x] repeated values preserve list order
    - [x] `--` separates positional arguments
    - [x] false and null config values are rejected by `WorkflowSchema`

  - [x] `packages/platform/cli/tests/Unit/WorkflowRunnerDiscardsThrowingChildBufferTest.php`
    - [x] a Throwable from `CommandRunner` causes the current invocation-local child buffer to be discarded
    - [x] the throwing child buffer is never finalized
    - [x] the exact same Throwable instance is rethrown without wrapping or replacement
    - [x] no completed `WorkflowResult` is emitted
    - [x] records from previously completed steps are not rendered as partial workflow output
    - [x] no child batch reaches the final formatter or console writer
    - [x] no subsequent workflow step is resolved or dispatched
    - [x] `WorkflowRunner` emits no replacement error record or duplicate observability

  - [x] `packages/platform/cli/tests/Unit/WorkflowSchemaTest.php`
    - [x] accepts the exact canonical shape
    - [x] reuses Foundation JSON-like normalization for deterministic workflow-name and option-map ordering
    - [x] workflow names containing valid hyphens retain their original values and canonical byte-order position
    - [x] nested map-key ordering follows Foundation semantics without independent sorting
    - [x] workflow step, argument, and repeatable-option lists retain their original order
    - [x] Foundation normalization failures are translated to the fixed `workflow-definition-invalid` reason
    - [x] the complete workflow-definition map is normalized under one shared Foundation resource budget
    - [x] aggregate node-count overflow fails even when every individual workflow and step satisfies its local bounds
    - [x] aggregate total-string-byte overflow fails even when every individual string satisfies its own length bound
    - [x] excessive step command-name and option-name lengths are rejected
    - [x] excessive nested container depth is rejected before immutable workflow values are constructed
    - [x] deeply nested malformed definitions fail closed without unbounded recursive traversal
    - [x] aggregate budget failures use exactly `workflow-definition-invalid`
    - [x] budget failures expose no workflow names, command tokens, option keys, values, or Foundation diagnostic paths
    - [x] valid definitions within the aggregate limits preserve canonical workflow ordering and original step and repeatable-option ordering
    - [x] no independent resource-budget traversal or second normalization implementation is introduced
    - [x] Foundation diagnostic paths and rejected configuration values never appear in public workflow errors
    - [x] no second recursive normalizer or map sorter is introduced
    - [x] rejects unknown keys, macros, templates, floats, unsafe strings, invalid option values, and all bounds violations
    - [x] rejects workflow summaries containing Unicode `Cc`, `Zl`, or `Zp` characters
    - [x] explicitly rejects U+0085, U+2028, and U+2029
    - [x] rejects malformed UTF-8 through the existing Foundation stable JSON encoder
    - [x] enforces the exact trimmed, single-line, and 256-byte summary constraints
    - [x] verifies that Foundation JSON-like normalization alone is not treated as a Unicode safety validator
    - [x] verifies that static command tag metadata validation is not substituted for workflow-specific summary validation
    - [x] introduces no general-purpose Unicode validator or second JSON normalization implementation
    - [x] every deep-schema validation failure uses exactly `workflow-definition-invalid`
    - [x] unknown and composite command reasons are reserved exclusively for `WorkflowCatalog`
    - [x] non-allowlisted exception reasons are rejected
    - [x] public exception messages contain no definition values, command tokens, arguments, options, or paths
    - [x] repeated invalid inputs produce deterministic public error codes and reasons

  - [x] `packages/platform/cli/tests/Unit/WorkflowCatalogDeterminismTest.php`
    - [x] workflow names are `strcmp` sorted
    - [x] step order is preserved
    - [x] no command service is instantiated

  - [x] `packages/platform/cli/tests/Unit/WorkflowRunnerStopsOnFirstNonZeroExitCodeTest.php`
    - [x] exact first non-zero code is returned
    - [x] the non-zero step buffer is finalized exactly once
    - [x] the non-zero step and its records are included in the completed failure result
    - [x] earlier completed step results remain in original order
    - [x] later steps are not resolved

  - [x] `packages/platform/cli/tests/Unit/SmartSuggestorTest.php`
    - [x] fixed thresholds depend exclusively on unknown input token length
    - [x] input lengths `8` and `9` use maximum distances `2` and `3` respectively
    - [x] candidate length does not change the selected threshold
    - [x] candidates with different lengths are evaluated against the same invocation threshold
    - [x] maximum three results
    - [x] hidden commands excluded
    - [x] catalog-order tie-break

  - [x] `packages/platform/cli/tests/Unit/CommandRunnerCompositeExecutionTest.php`
    - [x] no outer Kernel UoW for `workflow:run`
    - [x] normal commands remain unchanged
    - [x] composite command services are resolved lazily through the existing container boundary
    - [x] a resolved service not implementing `CompositeCommandInterface` is rejected
    - [x] a service implementing both execution interfaces is rejected
    - [x] a resolved service whose exact class differs from the descriptor service id is rejected
    - [x] a resolved composite command whose `name()` differs from the descriptor name is rejected
    - [x] composite exit codes outside `0..255` are rejected through the existing failure policy
    - [x] composite identity and exit-code failures never start a Kernel UoW
    - [x] composite validation failures preserve the existing command observability and exception precedence
    - [x] composite execution uses the existing `cli.command` observability lifecycle
    - [x] initial and final span attributes preserve the canonical operation, format, outcome, and exit-code policy
    - [x] exactly one top-level counter increment and duration observation are attempted
    - [x] metric labels remain exactly operation and outcome
    - [x] top-level composite logs contain no correlation or UoW identifiers
    - [x] telemetry failures do not mask command return codes or original Throwables
    - [x] composite observability does not create a Kernel UoW or duplicate step observations

- Contract:
  - [x] `packages/platform/cli/tests/Contract/WorkflowCommandsUseExactTagMetadataTest.php`
    - [x] `WorkflowListCommand` and `WorkflowRunCommand` expose exactly the six canonical metadata constants
    - [x] both commands use exact six-key `cli.command` tag metadata
    - [x] both command tags have actual priority `0`
    - [x] command names are exactly `workflow:list` and `workflow:run`
    - [x] both commands belong to group `workflow` and are not hidden
    - [x] `workflow:list` declares no arguments or command-owned options
    - [x] `workflow:run` declares exactly one required, non-variadic `workflow` argument
    - [x] `workflow:run` declares no command-owned options
    - [x] metadata values match the corresponding command class constants
    - [x] no composite execution flag or additional metadata key is introduced
    - [x] both commands pass the canonical `CommandTagSchema` metadata validation path

  - [x] `packages/platform/cli/tests/Contract/OnlyWorkflowRunMayUseCompositeCommandInterfaceTest.php`
    - [x] `WorkflowRunCommand` implements the internal `CompositeCommandInterface`
    - [x] `WorkflowRunCommand` does not implement `CommandInterface`
    - [x] `WorkflowListCommand` remains a normal `CommandInterface` implementation
    - [x] the composite interface does not extend `CommandInterface`
    - [x] no command service implements both execution interfaces
    - [x] only the exact built-in `WorkflowRunCommand` service id is eligible for composite classification
    - [x] externally tagged composite implementations are rejected deterministically
    - [x] composite eligibility cannot be introduced through tag metadata or catalog overrides
    - [x] descriptor construction and canonical metadata revalidation enforce the same composite exception
    - [x] no cross-package composite command contract or extension point is introduced

  - [x] `packages/platform/cli/tests/Contract/WorkflowInfrastructureDoesNotWriteToStdoutTest.php`
    - [x] workflow orchestration, catalogs, schemas, input adapters, result DTOs, and suggestion infrastructure contain no direct stdout or stderr writes
    - [x] workflow commands write records only through their supplied `OutputInterface`
    - [x] workflow infrastructure does not instantiate or invoke `ConsoleOutputWriter`
    - [x] workflow infrastructure does not call `echo`, `print`, `printf`, `var_dump`, or equivalent direct-output mechanisms
    - [x] workflow infrastructure does not open or write to `php://stdout`, `php://stderr`, `STDOUT`, or `STDERR`
    - [x] child step batches are never rendered or written independently
    - [x] final console writing remains owned exclusively by the existing top-level CLI output boundary
    - [x] contract checks exclude the existing authorized `ConsoleOutputWriter` implementation

  - [x] `packages/platform/cli/tests/Contract/WorkflowUxIntroducesNoRedactionBoundaryContractTest.php`
    - [x] workflow and suggestion classes do not depend on a concrete redactor
    - [x] workflow and suggestion classes do not receive `SensitiveDataRedactorInterface`
    - [x] no workflow-local or suggestion-local classifier, policy, hasher, or pattern registry exists
    - [x] no prompt, confirmation, preview, or separate diagnostics renderer is introduced
    - [x] the existing `OutputFormatter` remains the sole final redaction boundary

  - [x] `packages/platform/cli/tests/Contract/WorkflowEpicIntroducesNoReplayArtifactTest.php`
    - [x] no replay classes, config keys, artifact registry entry, or replay SSoT exists

- Integration:
  - [x] `packages/platform/cli/tests/Integration/CliErrorHandlerMapsWorkflowExceptionsSafelyTest.php`
    - [x] `WorkflowDefinitionInvalidException` maps to `CORETSIA_CLI_WORKFLOW_DEFINITION_INVALID`
    - [x] `WorkflowNotFoundException` maps to `CORETSIA_CLI_WORKFLOW_NOT_FOUND`
    - [x] known workflow exceptions preserve their exact stable public error codes
    - [x] definition errors expose only allowlisted stable reason tokens
    - [x] workflow-not-found errors expose only the fixed safe reason
    - [x] valid and invalid unknown workflow-name tokens never appear in exception messages or final diagnostics
    - [x] unknown workflow lookup exposes exactly the fixed `workflow-not-found` reason
    - [x] unknown or malformed exception details do not enter public diagnostics
    - [x] error descriptors contain no workflow definitions, step arguments, option values, service ids, or paths
    - [x] previous Throwable messages and traces are never exposed
    - [x] returned `ErrorDescriptor` extensions remain empty
    - [x] workflow exceptions pass through the existing `CliApplication` and `ExceptionRenderer` error boundary
    - [x] explicit JSON error output preserves the canonical `coretsia.cli-output@1` envelope
    - [x] unrelated existing CLI exception mappings remain unchanged

  - [x] `packages/platform/cli/tests/Integration/WorkflowRunAggregatesOrderedStepRecordsTest.php`
    - [x] a valid multi-step workflow executes steps in declared order
    - [x] each returned step produces exactly one ordered `WorkflowStepResult`
    - [x] every step result retains its executed command name, index, and exit code
    - [x] each step retains its original ordered child output records
    - [x] child `text`, `json`, and `error` records remain distinguishable
    - [x] canonical child records remain unchanged inside `WorkflowStepResult`
    - [x] exported child JSON records project internal `payload` content under `data`
    - [x] completed workflow result contains exactly workflow, outcome, exit_code, and steps
    - [x] each exported step contains exactly index, command, exit_code, and records
    - [x] step indices are zero-based original definition indices
    - [x] all exported map keys follow canonical Foundation ordering
    - [x] no additional workflow metadata or execution-context fields are exported
    - [x] completed workflow results contain only executed steps
    - [x] all-zero step exits produce a successful workflow result
    - [x] a non-zero step produces a completed failure result containing that step and its records
    - [x] no synthetic results are created for unexecuted steps
    - [x] `WorkflowRunCommand` writes exactly one top-level JSON-like result record
    - [x] child output is not separately formatted or written
    - [x] explicit `--format=json` preserves the existing outer output envelope and deterministic record ordering

  - [x] `packages/platform/cli/tests/Integration/WorkflowRunRejectsModeAndPresetOptionsTest.php`
    - [x] `workflow:run` declares exactly one required positional workflow argument
    - [x] `--mode` is rejected as an unknown command-owned option
    - [x] `--preset` is rejected as an unknown command-owned option
    - [x] top-level `--target` is not implicitly forwarded to workflow steps
    - [x] invalid command-owned options fail through the existing `CommandInputValidator`
    - [x] option validation fails before composite command service resolution
    - [x] invalid invocation dispatches no workflow step
    - [x] valid CLI-global `--format` and `--color` handling remains unchanged
    - [x] rejection preserves canonical CLI input error codes and safe diagnostics
    - [x] no new workflow bootstrap preset selection or option inheritance is introduced

  - [x] `packages/platform/cli/tests/Integration/WorkflowRunKernelOpsStepsUseDefinitionTargetTest.php`
    - [x] Kernel operation steps receive their `target` option exclusively from the validated workflow definition
    - [x] different workflow steps MAY use different explicit application targets
    - [x] each target reaches the existing command-owned `InputInterface` unchanged
    - [x] existing `KernelOpsRequestResolver` and `KernelOpsInterface` remain responsible for Kernel operation requests
    - [x] workflow infrastructure does not construct Kernel Ops requests or invoke Kernel Ops directly
    - [x] workflow execution does not replace or modify Kernel Bootstrap preset selection
    - [x] a missing or invalid required target fails through the existing command-input or owner-validation boundary
    - [x] no command service is resolved when structural input validation fails
    - [x] target resolution is independent of the outer workflow command arguments and options

  - [x] `packages/platform/cli/tests/Integration/UnknownCommandSuggestionsUseFinalCatalogTest.php`
    - [x] unknown command selection preserves the existing `CliInputInvalidException` error code and reason
    - [x] suggestions are generated only after final `CommandCatalog` construction
    - [x] suggestion candidates reflect validated catalog presentation overrides
    - [x] hidden commands are excluded from suggestion candidates
    - [x] unknown command tokens are never treated as workflow names, filesystem paths, or service ids
    - [x] suggestions are ordered by distance and final catalog order for ties
    - [x] at most three visible command names are suggested
    - [x] invalid or out-of-bounds input produces no suggestions
    - [x] suggestions are omitted when no eligible candidate satisfies the configured fixed thresholds
    - [x] a non-empty suggestion list is emitted as one additional JSON-like output record
    - [x] suggestions pass through the existing final formatter and shared redactor
    - [x] the canonical JSON error envelope and normal stderr ownership remain unchanged
    - [x] command services are not resolved while calculating suggestions
    - [x] no suggestion data enters logs, metrics, or UnitOfWork attributes

  - [x] `packages/platform/cli/tests/Integration/WorkflowDefinitionsRequireNoFilesystemScanningTest.php`
    - [x] an empty validated `cli.workflows.definitions` map produces an empty workflow catalog
    - [x] workflow definitions are discovered only from the validated CLI configuration subtree
    - [x] a workflow-like file in the current working directory is not automatically discovered
    - [x] workflow-like files under unrelated project directories are ignored
    - [x] changing the working directory does not add or remove configured workflow definitions
    - [x] no workflow definition path, directory, glob, or scanning configuration is accepted
    - [x] valid configured workflows remain available without any workflow definition files
    - [x] unknown workflows are not resolved through filesystem fallback
    - [x] the test does not forbid existing Kernel configuration or application bootstrap filesystem access
    - [x] no second workflow discovery mechanism or filesystem scanner is introduced

  - [x] `packages/platform/cli/tests/Integration/WorkflowRunExecutesEveryStepThroughCommandRunnerTest.php`
    - [x] every successfully dispatched workflow step follows the existing normal `CommandRunner` execution path
    - [x] each step uses its final validated normal `CommandDescriptor`
    - [x] each step receives the normalized `ArgvInput` from the shared `CommandInputValidator`
    - [x] each step receives a fresh invocation-local `CommandOutputBuffer`
    - [x] each step receives the same effective output format as the top-level workflow invocation
    - [x] command-owner services are resolved lazily through the existing `CommandRunner` container boundary
    - [x] existing command service identity and command-name validation remain enforced
    - [x] existing command exit-code validation remains enforced
    - [x] the existing Kernel CLI UnitOfWork lifecycle is used for every executed normal step
    - [x] existing command lifecycle observability is emitted by `CommandRunner`, not `WorkflowRunner`
    - [x] workflow infrastructure never invokes `CommandInterface::run()` directly
    - [x] workflow infrastructure never invokes `KernelRuntimeInterface::runUnitOfWork()` directly
    - [x] no step bypasses command discovery, input validation, lifecycle handling, or lazy service resolution

  - [x] `packages/platform/cli/tests/Integration/WorkflowCatalogIsResolvedOnlyForWorkflowCommandsTest.php`
    - [x] normal `list`, `help`, and Kernel operation commands do not instantiate `WorkflowCatalog`
    - [x] deep-invalid but structurally valid and Kernel-loadable workflow definitions do not prevent unrelated normal commands from executing
    - [x] structurally invalid workflow configuration map boundaries fail Console host boot through the existing Kernel validation path
    - [x] `workflow:list` validates configured definitions when its service is resolved
    - [x] `workflow:run` validates configured definitions before dispatching any step
    - [x] invalid workflow definitions use the existing safe CLI error boundary
    - [x] no command service is resolved during `CommandCatalog` construction

  - [x] `packages/platform/cli/tests/Integration/CliDoctorHumanReadableOutputTest.php`
    - [x] exact doctor invocation produces fixed human-readable diagnostics
    - [x] isolated projection tests render false capability values as `FAIL`
    - [x] isolated projection tests render false readiness as `NOT READY`
    - [x] actual doctor execution preserves the exit code returned by `DoctorCommand`
    - [x] failure-state projection tests do not require changing PHP version, SAPI, or extension availability
    - [x] Kernel host, catalog, generated artifacts, and shared redaction are not required
    - [x] additional arguments and options fail through the existing fixed boundary

  - [x] `packages/platform/cli/tests/Integration/CliKernelOpsReadableNestedOutputTest.php`
    - [x] config validation counts are readable without embedded JSON objects
    - [x] module enabled, excluded, and topological-order lists remain visible
    - [x] no Kernel artifact internals are read by CLI
    - [x] explicit JSON output preserves the existing schema and data

  - [x] `packages/platform/cli/tests/Integration/WorkflowRunUsesSharedRepeatableOptionNormalizationTest.php`
    - [x] one repeatable required-value option reaches the owner command as a one-element list
    - [x] multiple repeatable values retain their configured order
    - [x] invalid options fail before command service resolution
    - [x] each successfully dispatched step receives exactly one Kernel CLI UoW
    - [x] a later step input-validation failure preserves the original CLI validation exception
    - [x] no child buffer or Kernel CLI UoW is created for the rejected step
    - [x] previously completed step records are not emitted as partial workflow output
    - [x] no completed `WorkflowResult` is produced after validation failure
    - [x] no subsequent step is resolved or dispatched

  - [x] `packages/platform/cli/tests/Integration/WorkflowRunParentOutputBoundsFailClosedTest.php`
    - [x] oversized aggregated workflow output fails through existing limits
    - [x] no partial child output is written
    - [x] no second output budget or formatting implementation is introduced

  - [x] `packages/platform/cli/tests/Integration/WorkflowRunFinalOutputIsRedactedOnceTest.php`
    - [x] completed workflow output invokes the shared redaction port exactly once
    - [x] child step batches are not redacted separately
    - [x] canonical child batches retain their original internal record shape
    - [x] exported child JSON records use `data` instead of the sensitive structural key `payload`
    - [x] non-sensitive child JSON values remain visible after final redaction
    - [x] sensitive nested keys and values are redacted through the existing shared policy
    - [x] raw sensitive fixture values from child records are absent from final output
    - [x] no workflow-specific redactor or redaction policy is resolved
    - [x] record order remains unchanged after redaction
    - [x] explicit JSON output preserves the existing `coretsia.cli-output@1` outer envelope

  - [x] `packages/platform/cli/tests/Integration/WorkflowRunUsesOneKernelUowPerStepTest.php`
    - [x] no outer workflow UoW
    - [x] every executed normal step enters exactly one Kernel CLI UoW through the existing `CommandRunner`
    - [x] no nested UoW
    - [x] reset completes after every step
    - [x] consecutive steps have distinct Kernel UoW identities
    - [x] a completed step's context values are not inherited by a subsequent step
    - [x] repeated workflow invocations through the same `CliApplication`, `CommandRunner`, and Kernel runtime in one process remain isolated
    - [x] a completed workflow invocation leaves no active Kernel UoW before the next invocation begins
    - [x] a throwing step completes the canonical Kernel after-UoW and reset lifecycle before a subsequent invocation
    - [x] a non-zero returned step exit code does not leave an active Kernel UoW
    - [x] invocation-local command input, output buffers, and result records are not reused between workflow invocations
    - [x] Kernel continues to reject an actual nested UnitOfWork through its existing lifecycle gate
    - [x] no workflow-specific reset implementation, outer UoW, or additional lifecycle coordinator is introduced

### DoD (MUST)

- [x] Workflows are loaded only from validated `cli.workflows.definitions`.
- [x] No workflow filesystem discovery exists.
- [x] No templates, macros, environment interpolation, shell commands, retries, branches, or parallel execution exist.
- [x] Workflow names are deterministic and `strcmp` sorted.
- [x] Step order is preserved.
- [x] Every step command comes from the final tag-first `CommandCatalog`.
- [x] No command service is resolved during workflow catalog construction.
- [x] `workflow:run` has no outer Kernel UoW.
- [x] Every workflow step has exactly one normal Kernel CLI UoW.
- [x] No nested workflow/composite step is allowed.
- [x] The first non-zero step code stops execution and is returned unchanged by `WorkflowRunCommand` on completed execution, subject to the existing final CLI output failure boundary.
- [x] Step Throwables propagate unchanged to the existing CLI error boundary.
- [x] Workflow child output is aggregated in order and formatted/redacted once.
- [x] Workflow and suggestion infrastructure introduces no second redaction or rendering model.
- [x] No prompt, confirmation, preview, or separate interactive diagnostics pipeline exists in this epic.
- [x] The shared redaction port is consumed only through the existing final `OutputFormatter`.
- [x] Suggestions are deterministic, bounded, catalog-backed, and exclude hidden commands.
- [x] No replay artifact, storage, path config, recorder, or replay SSoT is introduced.
- [x] Workflow steps reuse the canonical command-input normalization and validation boundary.
- [x] Single-valued repeatable required options are accepted consistently in normal and workflow execution.
- [x] Composite execution kind survives catalog presentation overrides.
- [x] Workflow result aggregation remains bounded by existing parent output limits.
- [x] Adaptive CLI output defaults to readable tables for both interactive and non-interactive terminal capabilities.
- [x] Nested JSON-like values render as readable sections in table mode without embedded compact JSON containers.
- [x] The exact `doctor` invocation produces fixed human-readable diagnostics without Kernel host boot.
- [x] Explicit JSON output preserves `coretsia.cli-output@1` and canonical machine-readable behavior.
- [x] No terminal-specific formatting heuristics or second output pipeline are introduced.
- [x] Git Bash, PhpStorm Git Bash, WSL, and Windows PowerShell smoke tests verify the intended default output behavior.
- [x] Immutable workflow definitions may be retained in shared catalogs, but invocation-local execution state is never shared.
- [x] Deep-invalid but structurally valid and Kernel-loadable workflow definitions do not block unrelated normal CLI commands; structural Kernel configuration failures remain fail-closed.
- [x] Workflow step execution is fail-fast but not transactional across completed steps.
- [x] Workflow configuration normalization reuses existing Foundation and CLI validation primitives without forbidden sorting or recursive normalization implementations.
- [x] Existing exact CLI config contract tests are updated for the new workflow subtree and adaptive defaults.
- [x] Existing ultra-early doctor integration tests are updated for the human-readable diagnostic contract.
- [x] Unknown-command suggestions preserve canonical error codes and existing stdout/stderr ownership.
- [x] All unit, contract, integration, architecture, ECS, PHPStan, and package-compliance checks pass.
