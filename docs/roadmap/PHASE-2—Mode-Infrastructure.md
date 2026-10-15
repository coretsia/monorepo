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

- [ ] the only container-backed discovery mechanism for normal commands is DI tag `cli.command`
- [ ] the exact ultra-early `doctor` invocation is an entrypoint-owned exception and does not perform discovery
- [ ] once the normal operations host exists, `doctor` MUST appear as a tagged descriptor for `list` and `help`
- [ ] CLI MUST NOT read any `cli.commands` registry list.
- [ ] Consumer MUST NOT re-sort or re-dedupe TagRegistry output.

- [ ] External package commands:
  - [ ] CLI catalog MUST discover commands contributed by other enabled packages through `cli.command`.
  - [ ] CLI MUST NOT special-case `platform/worker`.
  - [ ] CLI MUST NOT depend on `platform/worker` at compile time.
  - [ ] Worker command discovery, when tested, MUST happen through the same generic `cli.command` mechanism as all other package commands.

### Command identity ownership (MUST)

- [ ] command name MUST be declared by the command class
- [ ] every tagged command class MUST expose:
  - [ ] `public const string NAME`
  - [ ] `public const string SUMMARY`
  - [ ] `public const string GROUP`
  - [ ] `public const bool HIDDEN`
  - [ ] `public const array ARGUMENTS`
  - [ ] `public const array OPTIONS`
- [ ] `CommandInterface::name()` MUST return the same value as tag metadata `name`
- [ ] command provider MUST reference command class constants when tagging commands
- [ ] command provider MUST NOT invent command names as unrelated string literals
- [ ] command name MUST match:
  - [ ] `\A[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)*\z`
- [ ] every `cli.command` service id MUST be the exact command class FQCN
- [ ] the service-id class MUST exist and implement `Coretsia\Contracts\Cli\Command\CommandInterface`
- [ ] `CommandCatalog` MUST read command constants through the service-id class without resolving or instantiating the service

### `cli.command` tag metadata schema (MUST)

- [ ] exact required keys:
  - [ ] `name`
  - [ ] `summary`
  - [ ] `group`
  - [ ] `hidden`
  - [ ] `arguments`
  - [ ] `options`
- [ ] unknown metadata keys MUST hard-fail deterministically
- [ ] metadata key `priority` MUST be forbidden
- [ ] canonicalized base tag metadata MUST equal the command class constants before `CommandOverrides` is applied
- [ ] `CommandOverrides` MAY modify only the catalog-view fields `summary|hidden|group`; it MUST NOT mutate command identity, arguments, options, service id, or original tag metadata
- [ ] metadata MUST NOT contain closures, objects, resources, floats, raw config values, runtime filesystem path values, runtime endpoints, env values, secrets, tokens, or runtime payloads
- [ ] argument and option names or summaries MAY describe owner-domain path inputs; runtime path values and path defaults MUST NOT be stored in tag metadata

### Command priority policy (MUST)

- [ ] command tags MUST NOT define `priority`
- [ ] command routing MUST NOT use priority
- [ ] duplicate command names across distinct tagged service ids MUST hard-fail deterministically
- [ ] repeated registrations of the same `(tag, serviceId)` follow the existing Foundation TagRegistry first-wins policy and are not observable as separate catalog entries
- [ ] reserved command names MUST hard-fail for external commands:
  - [ ] `doctor`
  - [ ] `help`
  - [ ] `list`
- [ ] reserved names are allowed only for the built-in command service ids registered by `platform/cli`
- [ ] the reserved `doctor` identity MUST match the command used by the ultra-early entrypoint
- [ ] CLI consumer MUST preserve TagRegistry order
- [ ] CLI consumer MUST NOT re-sort TagRegistry output
- [ ] CLI consumer MUST NOT silently de-dupe TagRegistry output
- [ ] every `cli.command` TaggedService returned by `TagRegistry::all()` MUST have actual priority `0`
- [ ] a non-zero `TaggedService::priority()` in canonical discovery output hard-fails before descriptor construction
- [ ] CLI MUST NOT normalize, ignore, or overwrite a non-zero priority
- [ ] registrations already discarded by TagRegistry first-wins MUST NOT be reconstructed or revalidated by CLI

### Lazy command discovery (MUST)

- [ ] `CommandCatalog` MUST build descriptors from `cli.command` tag metadata
- [ ] `CommandCatalog` MUST NOT instantiate command services while building the catalog
- [ ] `list` MUST be renderable from descriptors without instantiating all command services
- [ ] `help` MUST be renderable from descriptors without instantiating all command services
- [ ] command service MUST be resolved only when the selected command is dispatched
- [ ] on dispatch, resolved service MUST implement `Coretsia\Contracts\Cli\Command\CommandInterface`
- [ ] on dispatch, resolved command `name()` MUST match descriptor/tag metadata `name`
- [ ] mismatch MUST hard-fail with `InvalidCommandTagMetaException`

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
- [ ] CLI output MUST use `Coretsia\Contracts\Security\SensitiveDataRedactorInterface`.
- [ ] CLI MUST NOT define package-local redaction engine/policy classes.
- [ ] CLI MAY define CLI-domain output formatting rules, but baseline sensitive data classification and redacted output generation belong to `platform/redaction`.

- [ ] `packages/platform/cli/src/Output/CliOutputPolicy.php`
  - [x] immutable readonly value object
  - [ ] created only from validated config
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

- [ ] `packages/platform/cli/src/Output/TerminalCapabilitiesDetector.php`
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
  - [ ] tests can replace it with deterministic capabilities

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

- [ ] `packages/platform/cli/src/Output/FormattedOutput.php`
  - [x] stdout and stderr payloads MUST NOT end with CR or LF
  - [x] final-newline ownership belongs exclusively to `ConsoleOutputWriter`
  - [x] immutable readonly value
  - [x] contains:
    - [x] `stdoutBytes: string`
    - [x] `stderrBytes: string`
  - [x] contains no streams
  - [ ] contains no ANSI when effective color is disabled
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

- [ ] `packages/platform/cli/src/Output/Ansi/AnsiDecorator.php`
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
  - [ ] MUST NOT add ANSI to redirected output in `auto` mode
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
- [ ] `packages/platform/cli/resources/schema/cli_output@1.json`
  - [x] JSON Schema Draft 2020-12 for the CLI output envelope and `text|json|error` record shapes
  - [ ] production output pipeline enforces float rejection, sensitive-data redaction, and deterministic JSON serialization
  - [ ] Used by `JsonOutputSchemaContractTest.php` as the single source of truth
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
  - [ ] production JSON maps use canonical Foundation `StableJsonEncoder` key ordering

Catalog:
- [ ] `packages/platform/cli/src/Catalog/CommandCatalog.php` — builds deterministic catalog from `cli.command` tags
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
  - [ ] reserved names MUST be allowed only for built-in service ids registered by `platform/cli`
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
- [ ] `packages/platform/cli/src/Kernel/KernelOpsRequestResolver.php`
  - [x] `public function resolve(InputInterface $input): KernelOpsRequest`
  - [x] invalid scalar target shape throws `CliInputInvalidException`
  - [ ] is used only by built-in Kernel operation commands
  - [ ] consumes only the already structurally validated `InputInterface`
  - [x] requires the `target` option to be present exactly once as a non-empty scalar value
  - [x] validates only non-empty safe token shape
  - [ ] rejects an absent `--target` inside command dispatch before invoking `KernelOpsInterface`
  - [x] the descriptor's `value = required` requires a value when the option is supplied; it does not independently require option presence
  - [x] MUST NOT introduce a second required-option-presence validator or extend the generic option metadata schema solely for Kernel targets
  - [x] constructs `KernelOpsRequest(appTarget)`
  - [ ] relies on `CommandInputValidator` and the selected descriptor to reject undeclared, missing-value, or repeated options before command service resolution
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

- [ ] `packages/platform/cli/src/Exception/CliBootstrapException.php`
  - [x] code `CORETSIA_CLI_BOOTSTRAP_FAILED`
  - [x] code-first deterministic public message
  - [x] exposes only stable safe reason tokens
  - [ ] used after Composer autoload by `CliEntrypointPathsResolver`
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

- [ ] `packages/platform/cli/src/Exception/CliConfigInvalidException.php`
  - [x] code `CORETSIA_CLI_CONFIG_INVALID`
  - [x] code-first deterministic public message
  - [x] exposes only stable safe reason tokens
  - [ ] used by `CliOutputPolicy`, `CliServiceFactory`, and `CommandOverrides`
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
- [ ] `docs/adr/ADR-0034-cli-tag-first-command-catalog.md`
  - [ ] MUST capture:
    - [ ] tag-first discovery via `cli.command`
    - [ ] reserved built-in command names (`doctor`, `help`, `list`)
    - [ ] kernel ops consumption only through `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
    - [ ] deterministic output + redaction policy

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

- [ ] `packages/framework/README.md`
  - [ ] document the baseline Composer dependency graph
  - [ ] document `coretsia/core-kernel` and `coretsia/platform-cli` as explicit framework requirements
  - [ ] document `coretsia/platform-redaction` as a transitive dependency of CLI
  - [ ] distinguish physical Composer installation from Kernel runtime module selection
  - [ ] preserve explicit DependencySync ownership of additional mode-required packages
  - [ ] do not describe the framework metapackage as a runtime module or module-selection mechanism

- [ ] `packages/applications/skeleton/README.md`
  - [ ] document the updated framework baseline containing Kernel and CLI
  - [ ] document the canonical Skeleton CLI invocation:
    - [ ] `php coretsia list`
    - [ ] `php coretsia help`
    - [ ] `php coretsia doctor`
    - [ ] `php coretsia config:validate --target=web`
    - [ ] explain that the project-root `coretsia` is a thin wrapper around the installed Composer binary
    - [ ] document `php vendor/bin/coretsia <command>` as the direct equivalent invocation
    - [ ] distinguish the Skeleton project-root wrapper from the monorepo-root wrapper
    - [ ] document the fixed safe bootstrap failure when the Composer binary is unavailable
    - [ ] do not require an additional CLI installation, alias, or configuration step after baseline Composer installation
  - [ ] document Redaction as the transitive CLI runtime dependency
  - [ ] preserve `coretsia/framework` as the only project-owned Coretsia baseline root
  - [ ] document `micro` as the default canonical preset
  - [ ] distinguish baseline package installation from Console ModulePlan selection
  - [ ] preserve explicit dependency synchronization for additional selected targets and modules
  - [ ] update canonical multi-target examples to include the CLI and Redaction module dependency closure
  - [ ] do not introduce automatic DependencySync execution during create-project or runtime boot

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

- [ ] `packages/platform/cli/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` — complete rewrite
  - [ ] replace the Phase-0 static `CliModule::providers()` call with instance-based module metadata verification
  - [ ] remove assertions for deleted `CliServiceProvider::id()` and `CliServiceProvider::factories()` APIs
  - [ ] verify `CliServiceProvider` implements `ServiceProviderInterface` and `ContainerDefinitionProviderInterface`
  - [ ] verify `CliModule::providers()` returns the canonical `CliServiceProvider::class` through the module instance
  - [ ] preserve output-free loading assertions for `config/cli.php` and `config/rules.php`
  - [ ] replace legacy `cli.commands` list assertions with the canonical `cli.commands.overrides` map shape
  - [ ] replace legacy `cli.output.format` assertions with `cli.output.format_default` and the `adaptive|json|table|plain` allowed-value set
  - [ ] verify the canonical adaptive, color, and table-width config nodes
  - [ ] remove all Phase-0 `MODE`, static-provider, command-registry, and configurable-redaction assumptions

- [ ] `packages/platform/cli/tests/Contract/CommandsDoNotWriteToStdoutTest.php` — complete rewrite
  - [ ] scans every production command under `src/Command`
  - [ ] rejects:
    - [ ] `echo`
    - [ ] `print`
    - [ ] `printf`
    - [ ] `var_dump`
    - [ ] `print_r`
    - [ ] `error_log`
    - [ ] direct `STDOUT|STDERR`
    - [ ] `php://stdout|php://stderr|php://output`
  - [ ] excludes tests and fixtures
  - [ ] does not forbid writes inside the explicit `ConsoleOutputWriter`

- [ ] `packages/core/kernel/tests/Support/AppBuilder.php`
  - [ ] extend the existing test manifest and provider fixtures with the CLI-to-Redaction dependency closure
  - [ ] reuse the canonical Kernel module resolver without introducing an alternate test-only module planner

- [ ] `packages/core/kernel/tests/Integration/BootMicroPresetTest.php`
  - [ ] update the expected enabled module set for the canonical `micro` preset
  - [ ] verify CLI and Redaction dependency closure without weakening compiled-artifact validation

- [ ] `packages/core/kernel/tests/Integration/BootExpressPresetTest.php`
  - [ ] update the expected enabled module set for the canonical `express` preset
  - [ ] preserve deterministic ModulePlan and artifact assertions

- [ ] `packages/core/kernel/tests/Integration/ModePresetSchemaValidatorEnforcesMicroAndExpressRulesTest.php`
  - [ ] update canonical preset module expectations
  - [ ] preserve required/modules disjointness and schema validation
  - [ ] verify Worker remains selected only by the intended canonical presets

- [ ] `packages/core/kernel/tests/Unit/ProjectPackagePlannerTest.php`
  - [ ] update assertions affected by the canonical preset closure
  - [ ] preserve independent per-target ModulePlan and project-wide Composer union semantics

- [ ] `packages/core/kernel/tests/Unit/Config/ConfigValidatorRejectsInvalidCliCommandsTest.php`
  - [ ] replace legacy `cli.commands` list validation cases with the canonical `cli.commands.overrides` map shape
  - [ ] verify rejection of invalid static `commands|overrides` node types
  - [ ] preserve exact structural violation-path, reason, expected-type, and actual-type assertions
  - [ ] leave dynamic command-name and override-value validation to `CommandOverrides`

- [ ] `packages/core/kernel/tests/Unit/Config/ConfigValidatorRejectsInvalidCliOutputFormatTest.php`
  - [ ] replace legacy `cli.output.format` assertions with `cli.output.format_default`
  - [ ] preserve wrong-type and unsupported-value validation coverage
  - [ ] verify the canonical `adaptive|json|table|plain` allowed-value set

- [ ] `packages/core/kernel/tests/Unit/Config/ConfigValidatorDiagnosticsAreSafeAndDeterministicTest.php`
  - [ ] replace legacy command-list, `output.format`, and `output.redaction.enabled` fixtures with invalid values under the new CLI config schema
  - [ ] update exact expected violation paths and deterministic ordering
  - [ ] preserve assertions that rejected config values, secrets, and filesystem paths never appear in diagnostics

- [ ] `packages/platform/worker/tests/Integration/WorkerCompileArtifactBootE2ETest.php`
  - [ ] remove `mode` from the expected `commandMetadata()` return shape
  - [ ] remove `$commandClass::MODE` access from the metadata fixture
  - [ ] preserve exact six-key `cli.command` metadata parity across source and compiled artifact boot
  - [ ] preserve the existing Worker-only custom preset, artifact compilation, verification, and runtime boot assertions
  - [ ] MUST NOT add `platform.cli` or `platform.redaction` as mandatory roots to the Worker-only test preset
  - [ ] MUST NOT weaken the canonical artifact and deterministic tag-order assertions

- [ ] `docs/ssot/tags.md`
  - [ ] define that every `cli.command` service id is the exact command class FQCN
  - [ ] define lazy command-constant validation without service resolution
  - [ ] preserve owner `platform/cli` for `cli.command`
  - [ ] define exact metadata keys:
    - [ ] `name`
    - [ ] `summary`
    - [ ] `group`
    - [ ] `hidden`
    - [ ] `arguments`
    - [ ] `options`
  - [ ] forbid:
    - [ ] `priority`
    - [ ] `mode`
    - [ ] unknown metadata keys
  - [ ] define deterministic TagRegistry order
  - [ ] define duplicate command-name failure across distinct service ids, while preserving TagRegistry first-wins behavior for repeated registrations of the same service id
  - [ ] define reserved external names `doctor|help|list`
  - [ ] document that command-owner packages depend only on contracts-level CLI ports
  - [ ] `cli.command` registrations use actual tag priority `0`
  - [ ] non-zero priority is invalid independently of metadata contents

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

- [ ] `docs/ssot/modes.md`
  - [ ] document `platform.cli` as a default selectable module in all four canonical presets
  - [ ] document `platform.redaction` as a transitive runtime module dependency of CLI
  - [ ] preserve `required` versus `modules` exclusion semantics
  - [ ] preserve per-target module selection and deterministic dependency closure
  - [ ] clarify that canonical presets are runtime policies, not Composer package distributions
  - [ ] update examples of the effective canonical module sets

- [ ] `docs/ssot/application-dependency-sync.md`
  - [ ] document the updated Kernel and CLI baseline distribution
  - [ ] update the Composer baseline dependency graph and transitive Redaction installation
  - [ ] update canonical `micro` and multi-target ModulePlan examples
  - [ ] preserve explicit target selection, Composer root ownership, and plan/review/apply semantics
  - [ ] preserve the separation between physically installed packages and enabled runtime modules
  - [ ] do not introduce implicit dependency synchronization during application boot

- [ ] `packages/platform/cli/README.md` MUST include:
  - [ ] existing README is replaced completely
  - [ ] remove historical descriptions:
    - [ ] Phase 0
    - [ ] kernel-free CLI
    - [ ] config-based command registry
    - [ ] FQCN command lists
    - [ ] zero-argument command constructors
    - [ ] package-local redaction
    - [ ] monorepo-only launcher layout
  - [ ] Errors and exit codes
  - [ ] Canonical CLI invocation:
    - [ ] `coretsia` is the single canonical executable name
    - [ ] document `php coretsia <command>` for the monorepo root
    - [ ] document `php coretsia <command>` for Composer-installed Coretsia Skeleton projects
    - [ ] document `php vendor/bin/coretsia <command>` as the direct Composer binary invocation
    - [ ] document `vendor/bin/coretsia <command>` where the Composer binary is directly executable
    - [ ] distinguish the monorepo-root wrapper, Skeleton project-root wrapper, Composer binary proxy, and packaged CLI implementation
    - [ ] explain that the Skeleton wrapper forwards without performing application boot or command parsing
    - [ ] do not introduce or document framework-owned `c|cli` executable aliases
    - [ ] all supported invocations use the same packaged CLI implementation
  - [ ] Security and redaction
  - [ ] Kernel target and configured-preset behavior
  - [ ] Determinism
  - [ ] Providing commands from another package
  - [ ] Owner-package arguments, options, services, and domain validation
  - [ ] Configuration:
    - [ ] exact default shape
    - [ ] config consumers
    - [ ] global-option precedence
    - [ ] adaptive format behavior
    - [ ] color behavior
    - [ ] no configurable redaction/UoW/observability toggles
  - [ ] Context and UoW:
    - [ ] one UoW per normal command
    - [ ] doctor exception
    - [ ] context read/write ownership
    - [ ] reset ownership
  - [ ] Observability:
    - [ ] span and metric names
    - [ ] allowed labels and attributes
    - [ ] failure isolation
  - [ ] Colors:
    - [ ] `auto|always|never`
    - [ ] JSON is ANSI-free
    - [ ] fixed internal semantic palette
    - [ ] no arbitrary configured ANSI codes

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0034-cli-tag-first-command-catalog.md`

- [ ] `docs/architecture/DEPENDENCIES.md`
  - [x] update the existing `platform/cli` row in the canonical direct PHP source dependency matrix
  - [x] exact `platform/cli` `depends_on` value:
    - [x] `core/contracts, core/foundation, core/kernel`
  - [x] `platform/redaction` MUST NOT be added to the `platform/cli` source dependency row
  - [x] preserve the distinction between direct PHP source permissions and runtime-only Composer installation dependencies
  - [x] `coretsia/platform-redaction` remains a direct production Composer requirement of `coretsia/platform-cli`, backed by the declared `platform.redaction` runtime module dependency
  - [x] runtime-only Composer installation edges MUST NOT grant PHP source-level dependency permissions
  - [ ] `platform/cli` production source MUST consume redaction only through `Coretsia\Contracts\Security\SensitiveDataRedactorInterface` and MUST NOT import concrete `Coretsia\Platform\Redaction\*` implementation classes
  - [x] preserve canonical `strcmp` package-row ordering
  - [x] generated Deptrac ruleset for `platform.cli` MUST allow only `core.contracts`, `core.foundation`, and `core.kernel`
  - [x] generated Deptrac ruleset MUST NOT allow `platform.redaction` as a PHP source dependency of `platform.cli`
  - [x] preserve validation through the existing `composer arch` pipeline without introducing a new gate, allowlist, or dependency SSoT

### Cross-cutting (MUST)

#### Context & UoW

- [ ] Normal command UoW:
  - [ ] every normal command executes through exactly one `KernelRuntimeInterface` UoW
  - [ ] canonical UoW type is `cli`
  - [ ] UoW begins after descriptor validation and before lazy command resolution
  - [ ] command resolution, command-name verification, execution, and exit-code validation occur inside the same UoW callback
  - [ ] one command invocation MUST NOT create nested CLI UoWs
  - [ ] `doctor` is the only pre-host and pre-UoW command path
- [ ] Safe UoW attributes supplied by `CommandRunner`:
  - [ ] canonical command operation id
  - [ ] effective CLI output format
  - [ ] no raw argv
  - [ ] no positional argument values
  - [ ] no option values
  - [ ] no output records
  - [ ] no filesystem paths
  - [ ] no endpoints
  - [ ] no payloads or secrets
- [ ] Context writes:
  - [ ] platform/cli performs no direct `ContextStore` writes
  - [ ] `CommandRunner` passes safe UoW inputs to `KernelRuntimeInterface`
  - [ ] KernelRuntime owns base ContextStore writes:
    - [ ] `ContextKeys::CORRELATION_ID`
    - [ ] `ContextKeys::UOW_ID`
    - [ ] `ContextKeys::UOW_TYPE`
  - [ ] `cli_output_format` is not introduced as a ContextStore key
  - [ ] `CommandRunner` passes the following safe UoW attributes:
    - [ ] `operation` = canonical command name
    - [ ] `output_format` = resolved `json|table|plain`
  - [ ] UoW attributes remain lifecycle/hook payload data and MUST NOT be written into ContextStore by platform/cli
- [ ] Context reads:
  - [ ] platform/cli reads runtime context only through `ContextAccessorInterface`
  - [ ] `CommandRunner` consumes the following Kernel-owned values only for optional safe log correlation:
    - [ ] `ContextKeys::CORRELATION_ID`
    - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE` is written and owned by KernelRuntime; `CommandRunner` does not need to read it because the UoW type is explicitly supplied as `UnitOfWorkType::CLI`
  - [ ] optional identifiers MUST be captured inside the UoW callback before the canonical KernelRuntime reset
  - [ ] missing, invalid, or unavailable identifiers MUST be omitted from CLI logs
  - [ ] failures of optional context reads MUST NOT alter command execution, UoW cleanup, exit codes, or primary exception precedence
  - [ ] `output_format` is supplied explicitly to `CommandRunner` and passed as a UoW attribute; it is not read from ContextAccessorInterface
  - [ ] CLI MUST NOT create or write additional ContextStore keys
  - [ ] commands and formatters MUST NOT require these values to produce their domain result
- [ ] Reset discipline:
  - [ ] reset is triggered only by the canonical KernelRuntime lifecycle
  - [ ] platform/cli MUST NOT enumerate `kernel.reset`
  - [ ] platform/cli MUST NOT call `ResetOrchestrator` directly
  - [ ] platform/cli introduces no shared mutable service in the baseline
  - [ ] `CommandOutputBuffer` is invocation-local and is not a container singleton
  - [ ] immutable catalog, descriptor, and output-policy objects do not require reset
  - [ ] any future shared mutable service must implement `ResetInterface` and satisfy canonical stateful-service policy

#### Observability (policy-compliant)

- [ ] Ownership:
  - [ ] `CommandRunner` owns CLI command execution observability
  - [ ] `KernelOpsFacade` owns Kernel operation observability
  - [ ] built-in Kernel operation commands and CLI transport services MUST NOT emit duplicate Kernel-operation spans or metrics
  - [ ] formatters and writers MUST NOT emit command lifecycle metrics
- [ ] Command span:
  - [ ] name: `cli.command`
  - [ ] exactly one span-start attempt per selected normal command execution
  - [ ] the span begins before the Kernel UoW call and finishes after its complete lifecycle
  - [ ] a successfully started span MUST be ended exactly once
  - [ ] failed tracing MUST NOT prevent command execution, alter exit codes, or trigger command retries
  - [ ] safe attributes:
    - [ ] `operation`
    - [ ] `outcome`
    - [ ] `format`
    - [ ] `exit_code`
  - [ ] `operation` is the canonical command name
  - [ ] `exit_code` is a span attribute only
  - [ ] no arguments, options, paths, endpoints, payloads, or config values
- [ ] Command metrics:
  - [ ] `cli.command_total`
    - [ ] labels: `operation|outcome`
  - [ ] `cli.command_duration_ms`
    - [ ] labels: `operation|outcome`
  - [ ] duration measured through Foundation `Stopwatch`
  - [ ] metric values use integer milliseconds
  - [ ] `format|exit_code|app_target|preset|correlation_id|uow_id` are forbidden metric labels
- [ ] Canonical outcomes:
  - [ ] thrown exception uses canonical process exit code `1` for span and log attributes
  - [ ] exit code `0` → `success`
  - [ ] non-zero returned exit code → `failure`
  - [ ] thrown exception → `failure`
  - [ ] CLI command outcomes are based on the returned process exit code or thrown Throwable, not on Kernel UoW lifecycle outcome
  - [ ] a valid non-zero command return is a CLI command `failure` even when KernelRuntime records successful completion of the UoW callback
  - [ ] CLI MUST NOT override Kernel-owned UoW outcomes or emit corrective `kernel.uow` telemetry
- [ ] Logs:
  - [ ] one safe command completion/failure summary
  - [ ] safe fields:
    - [ ] `operation`
    - [ ] `outcome`
    - [ ] `exit_code`
  - [ ] MAY include `correlation_id|uow_id`
  - [ ] MUST NOT include raw argv, arguments, option values, command output, paths, endpoints, payloads, env/config values, tokens, exception stack traces, or previous throwable messages
- [ ] Failure isolation:
  - [ ] tracer start, attribute-update, and span-end failures MUST NOT alter command exit code
  - [ ] meter counter and duration-observation failures MUST NOT alter command exit code
  - [ ] logger failure MUST NOT alter command exit code
  - [ ] Foundation Stopwatch start/stop failures MUST NOT alter command exit code
  - [ ] optional ContextAccessorInterface read failures MUST NOT alter command exit code
  - [ ] an unavailable duration uses integer `0`
  - [ ] observability failures MUST NOT prevent or repeat command execution
  - [ ] observability failures MUST NOT replace the primary command exception
  - [ ] observability failures MUST NOT change Kernel-owned after-hook, reset, or failure-precedence semantics
  - [ ] failed observability attempts MUST NOT trigger recursive reporting through the same failing port
- [ ] Doctor:
  - [ ] ultra-early doctor does not require tracer, meter, logger, context, or UoW services
  - [ ] doctor emits no normal `cli.command` runtime span
  - [ ] doctor diagnostics are safe by construction through a fixed allowlist
  - [ ] ultra-early doctor MUST NOT invoke `SensitiveDataRedactorInterface`

### Security / Redaction (MUST)

- [ ] Redaction is mandatory:
  - [ ] every normal command output passes through mandatory defense-in-depth redaction
  - [ ] ultra-early doctor is the sole exception and is safe by construction
  - [ ] no config key disables it
  - [ ] no global option disables it
  - [ ] no owner-package command can bypass final CLI defense-in-depth redaction
  - [ ] `OutputFormatter` always receives `SensitiveDataRedactorInterface`
  - [ ] owner-package commands MUST produce safe-by-construction output before CLI redaction
  - [ ] known-sensitive values MUST be omitted, safely derived, or explicitly classified by their owning producer
  - [ ] a shared redaction classifier non-match MUST NOT be treated as authorization to emit raw values
  - [ ] successful redaction MUST NOT widen CLI-owned output schemas or authorize arbitrary diagnostic metadata
  - [ ] non-placeholder hash and length disclosure modes are not enabled by the baseline CLI output boundary
- [ ] CLI MUST NOT leak:
  - [ ] dotenv or env values
  - [ ] raw config values
  - [ ] tokens
  - [ ] authorization headers
  - [ ] cookies or session ids
  - [ ] raw SQL
  - [ ] payloads
  - [ ] filesystem paths unless explicitly owner-approved output contract permits a safe relative path
  - [ ] exception stack traces by default
- [ ] Allowed diagnostics:
  - [ ] stable reason tokens
  - [ ] bounded operation ids
  - [ ] integer counts and lengths
  - [ ] owner-approved safe hash derivations only where non-reconstruction requirements are satisfied
  - [ ] correlation and UoW ids where policy permits
- [ ] Color safety:
  - [ ] config cannot contain arbitrary ANSI escape sequences
  - [ ] JSON output is always ANSI-free
  - [ ] owner-package output values MUST NOT be interpreted as ANSI control sequences

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
  - [ ] `packages/platform/cli/tests/Unit/ParsedCliInvocationTest.php`
    - [ ] contains exactly one parsed `ArgvInput` and nullable global `formatOverride|colorOverride`
    - [ ] preserves command-facing arguments and options without reconstructing raw argv
    - [ ] global `format|color` tokens are absent from the contained command input
    - [ ] is immutable and retains no streams, catalog, config repository, descriptor, or command service

  - [ ] `packages/platform/cli/tests/Unit/CommandCatalogRejectsNonZeroTagPriorityTest.php`
    - [ ] actual non-zero `TaggedService::priority()` hard-fails with `InvalidCommandTagMetaException`
    - [ ] both positive and negative non-zero priorities are rejected
    - [ ] a forbidden metadata `priority` key is not required to trigger rejection
    - [ ] priority `0` remains valid
    - [ ] the catalog neither overwrites priority nor re-sorts TagRegistry output
    - [ ] no command service is instantiated during validation

  - [ ] `packages/platform/cli/tests/Unit/CommandOutputBufferRejectsAnsiAndControlBytesTest.php`
    - [ ] NUL, ESC, ANSI sequences, forbidden C0/C1 characters, and DEL in string values fail before record insertion
    - [ ] CRLF and CR in permitted string values normalize to LF
    - [ ] LF and TAB remain permitted in text and JSON-like string values
    - [ ] error messages remain safe single-line strings
    - [ ] valid non-ASCII UTF-8 values are not rejected because of UTF-8 continuation bytes
    - [ ] malformed UTF-8 fails closed
    - [ ] rejected values do not produce partial output or disclose offending content

  - [ ] `packages/platform/cli/tests/Unit/CliEntrypointPathsResolverTest.php`
    - [ ] resolves the application root from a valid canonical Composer binary layout
    - [ ] resolves the application root from an explicit canonical monorepo wrapper input
    - [ ] validates and normalizes the already-selected readable Composer autoload path
    - [ ] conflicting application-root sources fail with `CliBootstrapException`
    - [ ] unsupported or unresolved launcher layouts fail deterministically
    - [ ] results do not depend on the current working directory
    - [ ] performs no recursive parent-directory scanning or application-config loading
    - [ ] failure diagnostics disclose no absolute paths or raw launcher values

  - [ ] `packages/platform/cli/tests/Unit/CommandRunnerResolvesOnlySelectedServiceTest.php`
    - [ ] resolves exactly the selected `CommandDescriptor::serviceId()` through `ContainerInterface`
    - [ ] never enumerates container services or resolves unrelated tagged commands
    - [ ] command construction happens inside the Kernel UoW callback
    - [ ] passes the command-facing `InputInterface` and invocation-local `OutputInterface` unchanged
    - [ ] rejects a resolved service that does not implement `CommandInterface`
    - [ ] rejects a different command class returned under the selected descriptor's service id, even when it implements `CommandInterface` and returns the expected command name
    - [ ] rejects a subclass or decorator returned under a tagged command class service id
    - [ ] accepts the exact command class registered under its own FQCN
    - [ ] rejects a resolved command whose `name()` differs from the selected descriptor
    - [ ] unrelated command constructors are never invoked

  - [ ] `packages/platform/cli/tests/Unit/CommandTagSchemaTest.php`
    - [ ] accepts the canonical exact six-key metadata schema
    - [ ] validates the canonical `group` token pattern
    - [ ] rejects empty, uppercase, whitespace-containing, and control-character-containing groups
    - [ ] requires a non-empty safe summary and boolean hidden flag
    - [ ] validates argument descriptors with exact `name|summary|required|variadic` keys
    - [ ] rejects duplicate argument names, required arguments after optional arguments, and non-final variadic arguments
    - [ ] validates option descriptors with exact `name|summary|value|repeatable` keys
    - [ ] rejects duplicate option names and invalid `none|required|optional` value declarations
    - [ ] permits `repeatable = true` only with `value = required`
    - [ ] rejects reserved CLI-global option names `format|color`
    - [ ] permits owner-domain option names such as `mode|preset`
    - [ ] rejects non-json-like metadata and invalid static safe-text shapes
    - [ ] does not introduce CLI-local sensitive-value, path, or endpoint classification
    - [ ] leaves priority-key, unknown-key, name-regex, and class-constant mismatch cases to their dedicated tests

  - [ ] `packages/platform/cli/tests/Unit/CommandCatalogDeterminismTest.php`
    - [ ] identical canonical TagRegistry input produces identical descriptor order and values
    - [ ] catalog order matches `TagRegistry::all(ReservedTags::CLI_COMMAND)` exactly
    - [ ] catalog performs no secondary sorting or deduplication
    - [ ] repeated registration of the same service id follows Foundation first-wins semantics
    - [ ] applies validated `CommandOverrides` exactly once without changing descriptor order
    - [ ] overrides affect only the catalog-view `summary|hidden|group` fields
    - [ ] repeated `descriptors()` and `require()` calls do not resolve command services or mutate catalog state

  - [ ] `packages/platform/cli/tests/Unit/TableFormatterHonorsConfiguredMaxWidthTest.php`
    - [ ] uses the configured `cli.output.table.max_width` rather than detected terminal width
    - [ ] rendered table lines respect the configured visible-width limit
    - [ ] every physical output line, including headings, separators, wrapped rows, and group labels, stays within the configured `max_width`
    - [ ] nested `commands` lists render as readable command rows rather than one serialized JSON value
    - [ ] nested `groups` maps render as separate readable group sections without losing command entries
    - [ ] long command names and summaries wrap across physical table rows without truncation, ellipsis insertion, character loss, or reordering
    - [ ] wrapped cell content can be reconstructed exactly from its physical row fragments
    - [ ] narrow valid widths do not cause uncontrolled expansion
    - [ ] width calculation ignores formatter-owned ANSI decoration
    - [ ] color-disabled rendering is ANSI-free and deterministic
    - [ ] identical normalized records and width settings produce identical output
    - [ ] output contains no trailing CR or LF before `ConsoleOutputWriter` processing

  - [ ] `packages/platform/cli/tests/Unit/CommandRunnerObservabilityFailureIsolationTest.php`
    - [ ] tracer failure does not change a successfully returned command exit code
    - [ ] meter failure does not change a successfully returned command exit code
    - [ ] logger failure does not change a successfully returned command exit code
    - [ ] observability failures do not suppress command execution or cause a second command execution
    - [ ] when the command throws, the original command Throwable remains the primary failure
    - [ ] observability failures do not replace or expose the primary Throwable
    - [ ] Kernel-owned UoW cleanup and reset behavior remains authoritative
    - [ ] tracer start failure does not prevent the selected command from executing once
    - [ ] span attribute-update failure does not prevent the span-end attempt
    - [ ] span-end failure does not change the command result or primary Throwable
    - [ ] the real Foundation `Stopwatch` produces a non-negative integer duration during successful command execution
    - [ ] Stopwatch start and stop calls remain protected by CLI-owned Throwable isolation and an integer `0` fallback
    - [ ] Stopwatch failure isolation MUST NOT require mocking, subclassing, or replacing the final Foundation `Stopwatch`
    - [ ] the failure-handling structure MAY be verified through source-level contract assertions without introducing a new Stopwatch abstraction
    - [ ] metric counter failure does not prevent the independent duration-observation attempt
    - [ ] optional context read failures omit correlation identifiers without affecting execution
    - [ ] no observability failure causes a nested UoW or retry

  - [ ] `packages/platform/cli/tests/Unit/ArgvInputParserTest.php`
    - [ ] repeated bare flags fail
    - [ ] mixed bare and valued occurrences of one option fail
    - [ ] repeated valued occurrences preserve insertion order
    - [ ] end-of-options marker prevents subsequent tokens from being parsed as options
    - [ ] literal `--format=json` and `--color=always` after `--` remain positional arguments
    - [ ] those positional tokens do not populate `ParsedCliInvocation::formatOverride` or `colorOverride`
    - [ ] recognized global options before `--` remain absent from command-facing input

  - [ ] `packages/platform/cli/tests/Unit/CommandOutputBufferAggregateLimitsTest.php`
    - [ ] exactly `1024` records are accepted when the aggregate byte budget permits
    - [ ] record `1025` is rejected before insertion
    - [ ] the exact aggregate byte boundary is accepted
    - [ ] aggregate byte boundary plus one is rejected
    - [ ] rejected records are never partially retained
    - [ ] failure messages contain no record values or payload fragments

  - [ ] `packages/platform/cli/tests/Unit/CliErrorHandlerTest.php`
    - [ ] maps `KernelOpsFailedException` only through its stable public code/reason contract
    - [ ] mapped `ErrorDescriptor` contains no Throwable message, class, file, line, stack trace, previous Throwable, or filesystem path metadata

  - [ ] `packages/platform/cli/tests/Unit/ExceptionRendererDoesNotExposeThrowableMetadataTest.php`
    - [ ] renderer consumes only `ErrorDescriptor`, never a raw Throwable
    - [ ] rendered output contains no Throwable message, class, file, line, stack trace, previous Throwable, or filesystem path metadata

  - [ ] `packages/platform/cli/tests/Unit/CommandOutputBufferRejectsControlCharactersInMapKeysTest.php`
    - [ ] map keys containing CR, LF, TAB, DEL, or other forbidden control characters fail before finalization
    - [ ] map keys containing CRLF or CR are rejected without line-ending normalization
    - [ ] valid non-ASCII UTF-8 map keys preserve their original bytes and canonical Foundation ordering
    - [ ] valid multiline and tab-containing string values remain supported
    - [ ] Unicode C1 control characters are rejected without rejecting valid UTF-8 continuation bytes
    - [ ] malformed UTF-8 fails before record insertion
    - [ ] no rejected map key or payload value appears in failure diagnostics

  - [ ] `packages/platform/cli/tests/Unit/CommandCatalogRejectsNonClassServiceIdTest.php`
    - [ ] a non-class service id hard-fails before descriptor construction
    - [ ] no command service is resolved

  - [ ] `packages/platform/cli/tests/Unit/CommandCatalogRejectsMetadataConstantMismatchTest.php`
    - [ ] tag metadata differing from command constants hard-fails
    - [ ] command constants are read without service construction

  - [ ] `packages/platform/cli/tests/Unit/CommandOutputBufferDiscardTest.php`
    - [ ] clears all pre-error records
    - [ ] remains writable before finalization
    - [ ] rejects discard after finalization

  - [ ] `packages/platform/cli/tests/Unit/KernelOpsRequestResolverTest.php`
    - [ ] consumes only the already validated `InputInterface`
    - [ ] accepts exactly one non-empty scalar `target`
    - [ ] has no `CommandDescriptor` dependency
    - [ ] does not validate canonical Kernel target values

  - [ ] `packages/platform/cli/tests/Unit/CliServiceFactoryReadsValidatedCliRootTest.php`
    - [ ] every config-dependent factory method reads only the complete `cli` root
    - [ ] each config-dependent factory method calls `ConfigRepositoryInterface::get('cli')` exactly once
    - [ ] no factory method reads an individual `cli.*` path

  - [ ] `packages/platform/cli/tests/Unit/OutputFormatterUsesSensitiveDataRedactorTest.php`
    - [ ] formatter receives `SensitiveDataRedactorInterface`
    - [ ] formatter does not instantiate or resolve a concrete redactor
    - [ ] no CLI-local classifier, policy, hasher, or pattern registry is constructed
    - [ ] owner-supplied values are passed through the shared redaction boundary without redacting CLI record envelope keys
    - [ ] redaction uses the fixed `cli.output` scope and `Placeholder` mode
    - [ ] raw sensitive fixture values selected for redaction do not reach rendered output
    - [ ] redacted maps retain canonical ordering provided by the shared redaction runtime
    - [ ] text and error-message records retain their required string shape after redaction

  - [ ] `packages/platform/cli/tests/Unit/CommandTagSchemaRejectsPriorityMetadataKeyTest.php`
    - [ ] `priority` key hard-fails deterministically

  - [ ] `packages/platform/cli/tests/Unit/CommandTagSchemaRejectsUnknownKeysTest.php`
    - [ ] unknown metadata keys hard-fail deterministically

  - [ ] `packages/platform/cli/tests/Unit/CommandTagSchemaValidatesNameRegexTest.php`
    - [ ] invalid command names hard-fail deterministically

  - [ ] `packages/platform/cli/tests/Unit/CommandCatalogDoesNotInstantiateCommandsForListTest.php`
    - [ ] catalog can build descriptors from tag metadata without resolving command services

  - [ ] `packages/platform/cli/tests/Unit/CommandCatalogRejectsDuplicateNamesTest.php`
    - [ ] two distinct tagged service ids declaring the same command name hard-fail deterministically
    - [ ] repeated registration of the same service id follows the canonical TagRegistry first-wins policy

  - [ ] `packages/platform/cli/tests/Unit/CommandCatalogRejectsReservedExternalNamesTest.php`
    - [ ] external `doctor` hard-fails
    - [ ] external `help` hard-fails
    - [ ] external `list` hard-fails
    - [ ] built-in `doctor`, `help`, and `list` are allowed only for platform/cli built-in service ids
    - [ ] reserved-name validation uses the canonical immutable built-in service-id map
    - [ ] no tagged command service is instantiated during collision detection

  - [ ] `packages/platform/cli/tests/Unit/CommandRunnerValidatesCommandNameMatchesDescriptorTest.php`
    - [ ] descriptor name and command `name()` mismatch hard-fails with `InvalidCommandTagMetaException`

  - [ ] `packages/platform/cli/tests/Unit/CliOutputPolicyTest.php`
    - [ ] accepts complete default config
    - [ ] rejects unsupported format token
    - [ ] rejects unsupported color token
    - [ ] rejects invalid table width

  - [ ] `packages/platform/cli/tests/Unit/FormatResolverTest.php`
    - [ ] explicit format overrides configured default
    - [ ] adaptive interactive resolves to configured interactive format
    - [ ] adaptive non-interactive resolves to configured non-interactive format
    - [ ] performs no CI env detection

  - [ ] `packages/platform/cli/tests/Unit/ColorResolverTest.php`
    - [ ] explicit color overrides configured default
    - [ ] auto requires both interactive output and ANSI support
    - [ ] interactive stdout with `ansiSupported = false`, including redirected stderr capabilities, disables auto color
    - [ ] non-interactive stdout disables auto color regardless of ANSI support
    - [ ] never disables color
    - [ ] always enables color for text formats
    - [ ] JSON always disables color

  - [ ] `packages/platform/cli/tests/Unit/CommandRunnerObservabilityTest.php`
    - [ ] performs exactly one `cli.command` span-start attempt
    - [ ] starts the span before entering KernelRuntime UoW
    - [ ] ends a successfully started span after KernelRuntime UoW completion
    - [ ] span attributes contain exactly `operation|format|outcome|exit_code` on successful final update
    - [ ] emits exactly one `cli.command_total` counter increment
    - [ ] emits exactly one `cli.command_duration_ms` observation
    - [ ] metric labels contain exactly `operation|outcome`
    - [ ] metric duration is a non-negative integer measured through Foundation Stopwatch
    - [ ] output format and exit code are not metric labels
    - [ ] returned exit code `0` produces outcome `success`
    - [ ] valid non-zero returned exit codes produce outcome `failure` without remapping
    - [ ] thrown command failure records `exit_code = 1`
    - [ ] attempts one safe command completion/failure log
    - [ ] log fields contain only `operation|outcome|exit_code` and optional safe `correlation_id|uow_id`
    - [ ] optional context identifiers are captured inside the UoW callback before reset
    - [ ] no command arguments, option values, output records, paths, endpoints, or payloads enter telemetry
    - [ ] does not duplicate `kernel.uow` or `kernel.operation` telemetry

- Contract:
  - [ ] `packages/platform/cli/tests/Contract/CliModuleMetadataContractTest.php`
    - [ ] `CliModule` exposes the canonical module identity, package identity, Composer package, runtime kind, and config root
    - [ ] `MODULE_ID = platform.cli`
    - [ ] `PACKAGE_ID = platform/cli`
    - [ ] `COMPOSER_PACKAGE = coretsia/platform-cli`
    - [ ] `KIND = runtime`
    - [ ] `CONFIG_ROOT = cli`
    - [ ] instance metadata methods agree with their declared constants
    - [ ] instance `providers()` returns exactly `CliServiceProvider::class`
    - [ ] no Phase-0 static-only provider API remains
    - [ ] module metadata access performs no config reads, command discovery, service resolution, or application boot

  - [ ] `packages/platform/cli/tests/Contract/CliDoesNotBindGlobalErrorHandlerPortContractTest.php`
    - [ ] `CliErrorHandler` implements `ErrorHandlerInterface`
    - [ ] `CliApplication` receives `CliErrorHandler` under its concrete service id
    - [ ] `CliServiceProvider` does not alias `ErrorHandlerInterface`

  - [ ] `packages/platform/cli/tests/Contract/CliCommandOutputBufferIsInvocationLocalContractTest.php`
    - [ ] `CommandOutputBuffer` is absent from source-host service registration
    - [ ] `CommandOutputBuffer` is absent from canonical runtime definitions
    - [ ] `CliApplication` creates exactly one buffer per invocation
    - [ ] no buffer state is retained between `CliApplication::run()` calls
    - [ ] the same invocation-local buffer is cleared and reused for error rendering

  - [ ] `packages/platform/cli/tests/Contract/CliDoesNotImplementConfigPipelineContractTest.php`
    - [ ] no direct package config-file reads
    - [ ] no CLI-local loader, merger, validator, directive processor, or repository
    - [ ] only `ConfigRepositoryInterface` is consumed

  - [ ] `packages/platform/worker/tests/Contract/WorkerCommandMetadataConstantsTest.php`
    - [ ] remove all `MODE` assertions
    - [ ] assert exactly `NAME|SUMMARY|GROUP|HIDDEN|ARGUMENTS|OPTIONS`
    - [ ] assert arrays and scalar metadata types

  - [ ] `packages/platform/worker/tests/Contract/WorkerServiceProviderCliCommandTaggingTest.php`
    - [ ] remove `mode` from expected metadata shape
    - [ ] assert exact six-key metadata
    - [ ] preserve `cli.command` tag assertions

  - [ ] `packages/platform/cli/tests/Contract/CliReusesFoundationJsonLikePrimitivesContractTest.php`
    - [ ] CLI introduces no independent JSON-like structural normalizer
    - [ ] CLI introduces no independent recursive map sorter
    - [ ] JSON output uses the canonical Foundation StableJsonEncoder
    - [ ] CLI-owned control-byte and presentation validation remain separate

  - [ ] `packages/platform/cli/tests/Contract/CliComposerRuntimeDependenciesContractTest.php`
    - [ ] direct production Composer requirements include `coretsia/core-contracts`, `coretsia/core-foundation`, `coretsia/core-kernel`, and `coretsia/platform-redaction`
    - [ ] all internal Coretsia Composer requirements use the canonical release-line `publicConstraint`
    - [ ] `coretsia/platform-redaction` is required for runtime module installation, not direct PHP source access
    - [ ] `extra.coretsia.requires` contains exactly `core.kernel` and `platform.redaction`
    - [ ] `bin/coretsia` is declared as the packaged Composer binary
    - [ ] no command-owner package dependency is introduced solely for discovery

  - [ ] `packages/platform/cli/tests/Contract/CliHasSingleProductionOutputSinkContractTest.php`
    - [ ] after Composer autoload succeeds, only `ConsoleOutputWriter.php` writes stdout/stderr
    - [ ] binary may construct and pass stdout/stderr streams
    - [ ] binary may write directly to stderr only for the fixed pre-autoload failure
    - [ ] `TerminalCapabilitiesDetector` may inspect streams but never writes them
    - [ ] no other production class references output sinks

  - [ ] `packages/platform/cli/tests/Contract/JsonOutputSchemaContractTest.php`
    - [ ] MUST load schema from `packages/platform/cli/resources/schema/cli_output@1.json` (no inline schema duplication)

  - [ ] `packages/platform/cli/tests/Contract/CliDoesNotReferenceKernelCompileInternalsContractTest.php`
    - [ ] scans `packages/platform/cli/src`
    - [ ] rejects imports and FQCN references to:
      - [ ] `ModulePlanResolver`
      - [ ] `ModuleResolution`
      - [ ] `ContainerProviderPlan`
      - [ ] `ContainerProviderPlanResolver`
      - [ ] `ManifestReaderInterface`
      - [ ] `ComposerManifestReader`
      - [ ] `ArtifactCompiler`
      - [ ] `FingerprintCalculator`
      - [ ] `CacheVerifier`
      - [ ] `ArtifactGenerationLocator`
      - [ ] `ArtifactGenerationPublisher`
      - [ ] `ArtifactGenerationValidator`
      - [ ] `ArtifactPathResolver`
      - [ ] `PhpArtifactReader`
      - [ ] `ArtifactRuntimeBooter`
      - [ ] `ArtifactRuntimeInput`
    - [ ] CLI command, catalog, runner, formatter, and renderer implementations do not inspect Kernel `current` or generation manifests
    - [ ] CLI does not construct Kernel generation directories or artifact paths
    - [ ] CLI does not directly include or read generated Kernel `config.php` or `container.php`
    - [ ] allows `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface` throughout platform/cli Kernel-operation adapters
    - [ ] allows `KernelOpsHostInput` and `KernelOpsHostBooter` only in `src/Bootstrap/CliHostBootstrap.php`
    - [ ] rejects every other `Coretsia\Kernel\Ops\*` reference
    - [ ] does not forbid Kernel-owned artifact access behind `KernelOpsInterface`

  - [ ] `packages/platform/cli/tests/Contract/CliServiceProviderSeparatesSourceOnlyKernelOpsWiringContractTest.php`
    - [ ] `CliServiceProvider` implements both provider interfaces
    - [ ] source container contains `KernelOpsRequestResolver`
    - [ ] source container contains all six Kernel operation commands
    - [ ] each Kernel operation command receives `KernelOpsInterface` through constructor injection
    - [ ] no command imports or resolves `Coretsia\Kernel\Ops\KernelOpsFacade`
    - [ ] source container contains their `cli.command` tags
    - [ ] `define()` contains generic CLI infrastructure
    - [ ] `define()` contains `CliErrorHandler` and `ExceptionRenderer`
    - [ ] `define()` contains no `KernelOpsInterface` and no Kernel operation command services or tags
    - [ ] canonical runtime definitions contain no Kernel operation command services or tags
    - [ ] canonical runtime definitions contain no `KernelOpsRequestResolver`
    - [ ] canonical runtime definitions contain no `KernelOpsHostInput` or `KernelOpsHostBooter`

  - [ ] `packages/platform/cli/tests/Contract/CliConfigSubtreeShapeContractTest.php`
    - [ ] config returns subtree only
    - [ ] no root repetition
    - [ ] exact default keys
    - [ ] no closures, objects, resources, floats, or env reads

  - [ ] `packages/platform/cli/tests/Contract/CliConfigRulesCoverAllDefaultsContractTest.php`
    - [ ] every default key has a rule
    - [ ] no rule-owned key is missing from defaults
    - [ ] unknown keys are rejected

  - [ ] `packages/platform/cli/tests/Contract/CliRedactionCannotBeDisabledContractTest.php`
    - [ ] no redaction enable/disable config key
    - [ ] no redaction bypass option
    - [ ] OutputFormatter requires `SensitiveDataRedactorInterface`

  - [ ] `packages/platform/cli/tests/Contract/CliHasNoPackageLocalRedactionImplementationContractTest.php`
    - [ ] `packages/platform/cli/src/Redaction/` does not exist
    - [ ] `packages/platform/cli/src/Output/Redaction/` does not exist
    - [ ] `RedactionEngine.php` is absent
    - [ ] `RedactionPolicy.php` is absent
    - [ ] production source defines no CLI-local:
      - [ ] sensitive-key classifier
      - [ ] sensitive-value classifier
      - [ ] redaction policy
      - [ ] redaction hasher
      - [ ] pattern registry
    - [ ] `OutputFormatter` depends only on `SensitiveDataRedactorInterface`
    - [ ] no platform/cli class imports or instantiates `DefaultSensitiveDataRedactor`

  - [ ] `packages/platform/cli/tests/Contract/CliDoesNotWriteContextOrResetDirectlyContractTest.php`
    - [ ] no direct ContextStore writes
    - [ ] no ResetOrchestrator dependency
    - [ ] no reset tag enumeration

- Integration:
  - [ ] `packages/platform/cli/tests/Integration/CoretsiaRepositoryWrapperIsCwdIndependentTest.php`
    - [ ] repo-root `coretsia` works when invoked outside the repository working directory
    - [ ] the wrapper derives its application root exclusively from its own repository-relative location
    - [ ] the packaged CLI selects the repository-root `vendor/autoload.php`
    - [ ] equivalent invocations from different working directories produce the same exit code and normalized output
    - [ ] no Skeleton source-directory root is substituted for the repository application root
    - [ ] no recursive application-root discovery is introduced

  - [ ] `packages/platform/cli/tests/Integration/PreAutoloadFailureIsFixedAndPathSafeTest.php`
    - [ ] missing Composer autoload produces exactly `CORETSIA_CLI_BOOTSTRAP_FAILED: autoload-unavailable`
    - [ ] unreadable Composer autoload produces the same fixed diagnostic
    - [ ] conflicting pre-autoload selection inputs produce the same fixed diagnostic
    - [ ] missing Skeleton `vendor/bin/coretsia` produces the same fixed diagnostic
    - [ ] unreadable Skeleton `vendor/bin/coretsia` produces the same fixed diagnostic
    - [ ] Skeleton wrapper failure occurs before attempting to require the missing or unreadable Composer binary
    - [ ] failure writes only to stderr with exactly one trailing LF and exits with code `1`
    - [ ] stdout remains empty
    - [ ] no application classes are required to render the pre-autoload failure
    - [ ] no PHP warning, fatal-error output, absolute path, raw launcher input, Throwable detail, or stack trace is emitted

  - [ ] `packages/platform/cli/tests/Integration/NormalCommandExecutesExactlyOneKernelUowTest.php`
    - [ ] successful normal command execution invokes `KernelRuntimeInterface::runUnitOfWork()` exactly once
    - [ ] the selected command executes inside that UoW callback
    - [ ] command failure does not cause another UoW invocation
    - [ ] command resolution and exit-code validation occur inside the same UoW
    - [ ] no nested CLI-owned UoW or manual reset orchestration is introduced
    - [ ] canonical UoW type and attributes remain covered by `CliCommandUowAttributesAreOwnedByCommandRunnerTest.php`

  - [ ] `packages/platform/cli/tests/Integration/UltraEarlyDoctorUsesSafeFixedOutputPipelineTest.php`
    - [ ] exact `coretsia doctor` uses the ultra-early runner
    - [ ] the same `DoctorCommand` implementation is used by the normal catalog
    - [ ] output uses fixed `plain` format with color disabled
    - [ ] output contains only allowlisted doctor record types and scalar values
    - [ ] output is written through `ConsoleOutputWriter`
    - [ ] no Kernel container, configuration, observability, context, or shared redaction service is required
    - [ ] injected environment secret sentinels are never emitted
    - [ ] no raw environment value, absolute path, command line, loaded-extension list, phpinfo output, or Throwable detail is emitted
    - [ ] unsupported doctor arguments and options fail through the fixed safe pipeline

  - [ ] `packages/platform/cli/tests/Integration/JsonFormatSuppressesAnsiWhenColorAlwaysTest.php`
    - [ ] `--format=json --color=always` produces valid JSON without ANSI sequences
    - [ ] JSON output remains ANSI-free for successful and failed command outcomes
    - [ ] effective color is disabled before JSON rendering
    - [ ] `AnsiDecorator` cannot decorate the JSON document
    - [ ] JSON rendering contains no terminal-dependent formatting
    - [ ] stdout contains the canonical schema envelope and stderr remains empty
    - [ ] final output contains exactly one writer-owned trailing LF

  - [ ] `packages/platform/cli/tests/Integration/NonInteractiveAdaptiveOutputUsesPlainFormatTest.php`
    - [ ] configured `adaptive` format resolves to the configured non-interactive format
    - [ ] the baseline non-interactive default resolves to `plain`
    - [ ] stdout interactivity comes only from the injected `TerminalCapabilities`
    - [ ] no CI environment variable or terminal-width inference changes the format
    - [ ] an explicit valid `--format` overrides adaptive selection
    - [ ] command-facing input does not receive the resolved format as an owner option

  - [ ] `packages/platform/cli/tests/Integration/ExternalPackageCommandExitCodeIsPreservedTest.php`
    - [ ] external command exit code `0` is preserved
    - [ ] external command non-zero exit codes, including `2`, `3`, and `255`, are preserved without Kernel-specific remapping
    - [ ] a valid non-zero return is not converted into an uncaught exception
    - [ ] returned exit codes outside `0..255` fail through `CliCommandFailedException`
    - [ ] CLI-global parsing and uncaught command exceptions retain their own failure boundary

  - [ ] `packages/platform/cli/tests/Integration/ConfigCompileRendersGenerationAwareOpsResultTest.php`
    - [ ] `config:compile` renders the published generation id returned by `KernelOpsInterface`
    - [ ] renders the explicit app target and Kernel-provided effective preset
    - [ ] renders exactly the four Kernel-provided artifact identities and basenames
    - [ ] preserves the deterministic order of the Kernel-provided artifact list
    - [ ] does not synthesize generation paths or `generations/current/<basename>` values
    - [ ] does not receive or render raw config, Composer metadata, or artifact payloads
    - [ ] CLI performs no post-compile artifact verification or runtime boot

  - [ ] `packages/platform/cli/tests/Integration/ConfigHashRendersGenerationIdTest.php`
    - [ ] `config:hash` renders the safe expected `generation_id` returned by Kernel
    - [ ] renders the explicit app target and Kernel-provided effective preset
    - [ ] does not present the expected generation id as a published or current generation
    - [ ] does not render synthetic artifact locations
    - [ ] does not publish artifacts or inspect `current`
    - [ ] no CLI-local fingerprint calculation is invoked

  - [ ] `packages/platform/cli/tests/Integration/CacheVerifyRendersFourGenerationArtifactsTest.php`
    - [ ] `cache:verify` renders exactly four Kernel-provided artifact verification entries
    - [ ] preserves the Kernel-provided artifact identities, basenames, statuses, and safe reasons
    - [ ] renders expected and nullable current generation ids without synthesizing paths
    - [ ] renders Kernel-provided byte counts when available
    - [ ] state `clean` produces exit code `0`
    - [ ] state `dirty` produces exit code `2`
    - [ ] state `invalid` produces exit code `3`
    - [ ] all three states use the same Kernel OpsResult presentation path
    - [ ] CLI does not independently read artifact files or reinterpret verification states

  - [ ] `packages/platform/cli/tests/Integration/CommandInputValidatorRejectsUnknownOptionBeforeResolutionTest.php`
    - [ ] an option absent from the selected descriptor throws `CliInputInvalidException`
    - [ ] rejection occurs before the selected command service is resolved
    - [ ] the selected command constructor and `run()` are not invoked
    - [ ] no owner-package domain validation is invoked for structurally invalid input
    - [ ] unknown option values are not echoed in diagnostics
    - [ ] invalid input returns exit code `1`

  - [ ] `packages/platform/cli/tests/Integration/CommandInputValidatorPreservesRepeatableOptionOrderTest.php`
    - [ ] an owner-declared repeatable required-value option accepts multiple `--name=value` occurrences
    - [ ] parsed values reach the owner-package command as an ordered `list<string>`
    - [ ] descriptor validation preserves the original occurrence order
    - [ ] the command receives each value exactly once
    - [ ] repeated non-repeatable options are rejected before command service resolution
    - [ ] no last-value-wins behavior is introduced

  - [ ] `packages/platform/cli/tests/Integration/GlobalFormatOptionIsNotPassedToPackageCommandTest.php`
    - [ ] CLI-global `--format` is consumed before owner-package input construction
    - [ ] CLI-global `--color` is consumed before owner-package input construction
    - [ ] neither global option appears in `InputInterface::tokens()` or normalized command options
    - [ ] owner-package arguments and declared domain options remain unchanged
    - [ ] explicit global format and color selections affect presentation only
    - [ ] external command execution does not modify Kernel Bootstrap preset selection

  - [ ] `packages/platform/cli/tests/Integration/DoctorBypassesKernelOperationsHostTest.php`
    - [ ] exact `coretsia doctor` does not invoke `KernelOpsHostBooter`
    - [ ] does not construct a source operations container or command catalog
    - [ ] does not invoke `KernelRuntimeInterface::runUnitOfWork()`
    - [ ] does not resolve Kernel Ops, module discovery, or generated runtime artifacts
    - [ ] does not require UoW hooks or reset orchestration
    - [ ] other normal CLI commands continue through the canonical Kernel operations host

  - [ ] `packages/platform/cli/tests/Integration/CoretsiaBinaryListCommandTest.php`
    - [ ] packaged `coretsia list` reaches normal source-host catalog dispatch
    - [ ] a Composer-generated `vendor/bin/coretsia` PHP proxy in a consumer application reaches the packaged CLI implementation
    - [ ] the shipped Skeleton project-root `coretsia` delegates through that Composer binary proxy
    - [ ] `php coretsia list` and `php vendor/bin/coretsia list` produce identical normalized output and exit codes
    - [ ] both invocations resolve the same consumer application root rather than the monorepo or installed package directory
    - [ ] both invocations preserve equivalent command-facing arguments and global options
    - [ ] the Skeleton wrapper preserves Composer proxy variables used for autoload and application-root resolution
    - [ ] the Skeleton wrapper works when invoked through an absolute path from another current working directory
    - [ ] Composer binary invocation remains supported independently of the optional project-root convenience wrapper
    - [ ] no second catalog, command parser, configuration pipeline, or Kernel operations host is constructed
    - [ ] renders built-in command descriptors in deterministic groups
    - [ ] preserves the canonical TagRegistry descriptor order within each group
    - [ ] does not require grouped presentation order to equal one flat TagRegistry order across different groups
    - [ ] grouping MUST NOT mutate, re-sort, or re-dedupe the underlying CommandCatalog
    - [ ] respects catalog-view hidden-command metadata
    - [ ] explicit `--format=table` renders every visible built-in and enabled external command as a readable entry in its descriptor-defined group
    - [ ] table output contains every visible command name and complete catalog-view summary exactly once, without rendering hidden commands
    - [ ] long command summaries remain recoverable in full after wrapping, without truncation or ellipsis insertion
    - [ ] every physical table output line respects the configured `cli.output.table.max_width`, including wrapped rows and group headings
    - [ ] table presentation preserves canonical descriptor order within each group and does not alter the underlying catalog or JSON output
    - [ ] does not instantiate unrelated command services
    - [ ] exits with code `0` on successful listing
    - [ ] does not require compiled application runtime artifacts
    - [ ] an explicit Console-target module exclusion of `platform.cli` prevents normal CLI command execution
    - [ ] `CliHostBootstrap` verifies the existing Kernel-owned `ModulePlan` before resolving `CliApplication`
    - [ ] Foundation concrete-class autowiring MUST NOT bypass the excluded CLI module
    - [ ] excluded CLI fails with `CORETSIA_CLI_HOST_BOOT_FAILED`, reason `host-boot-failed`, and exit code `1`
    - [ ] no command catalog is constructed and no command service is resolved after the exclusion is detected
    - [ ] no CLI UoW is entered
    - [ ] no second ModulePlan, provider-registration path, or fallback CLI container is created

  - [ ] `packages/platform/cli/tests/Integration/CoretsiaBinaryHelpCommandTest.php`
    - [ ] packaged `coretsia help` renders general command help from catalog descriptors
    - [ ] `coretsia help <command>` renders the selected descriptor's arguments and options
    - [ ] unknown command help requests produce the canonical safe input failure
    - [ ] help does not instantiate the described command service
    - [ ] help output follows the selected global format and color policy
    - [ ] explicit `--format=table` renders every visible command descriptor in general help as a readable row containing its group, name, and complete summary
    - [ ] general help excludes hidden commands and preserves canonical TagRegistry descriptor order
    - [ ] long command summaries remain recoverable in full after wrapping, without truncation or ellipsis insertion
    - [ ] every physical general-help table output line respects the configured `cli.output.table.max_width`
    - [ ] command-specific help in table format renders declared arguments and options as readable rows rather than truncated serialized JSON values
    - [ ] table presentation does not alter descriptor metadata, command discovery, or structured JSON output
    - [ ] successful help exits with code `0`

  - [ ] `packages/platform/cli/tests/Integration/ReservedCommandNamesCollisionRejectedTest.php`
    - [ ] an enabled external package contributing reserved command name `help` causes deterministic catalog rejection
    - [ ] collision is detected from tag metadata without constructing the external command
    - [ ] external services cannot replace the built-in reserved command identity
    - [ ] catalog construction failure follows the fixed host-bootstrap error boundary
    - [ ] no conflicting service id, class name, or command metadata is exposed
    - [ ] the existing dedicated unit test covers the complete `doctor|help|list` reserved-name set

  - [ ] `packages/platform/cli/tests/Integration/NormalCommandsBootKernelOperationsHostTest.php`
    - [ ] obtains the canonical Console ModulePlan from the Kernel source operations host
    - [ ] normal command dispatch succeeds only when `platform.cli` is enabled
    - [ ] explicit Console exclusion of `platform.cli` fails before `CliApplication` resolution
    - [ ] concrete-class autowiring cannot bypass the module-selection requirement
    - [ ] exclusion failure produces `CORETSIA_CLI_HOST_BOOT_FAILED`, reason `host-boot-failed`, and exit code `1`
    - [ ] no second module resolver, ModulePlan, or fallback CLI container is constructed

  - [ ] `packages/platform/cli/tests/Integration/KernelCommandsRequireExplicitTargetTest.php`
    - [ ] a missing `--target` is rejected by `KernelOpsRequestResolver`
    - [ ] an empty `--target=` is rejected during structural validation
    - [ ] neither invalid invocation calls `KernelOpsInterface`
    - [ ] no CLI-owned Kernel target or preset inference is introduced

  - [ ] `packages/platform/cli/tests/Integration/CliDefaultMicroCreateProjectBootTest.php`
    - [ ] verify the packaged skeleton baseline and canonical `micro` selection
    - [ ] verify source-host boot with CLI, Kernel, and Redaction available
    - [ ] verify that normal command discovery does not require generated runtime artifacts
    - [ ] verify that no automatic DependencySync or second module planner executes

  - [ ] `packages/platform/cli/tests/Integration/CliHostResolvesCanonicalSensitiveDataRedactorTest.php`
    - [ ] the composed source host resolves exactly one `SensitiveDataRedactorInterface`
    - [ ] `OutputFormatter` receives the contracts port
    - [ ] no platform/cli class imports or instantiates `DefaultSensitiveDataRedactor`

  - [ ] `packages/platform/cli/tests/Integration/CliRequiresExplicitConsoleModuleSelectionTest.php`
    - [ ] installing `platform.cli` without selecting it for Console does not implicitly enable its provider
    - [ ] explicitly selecting `platform.cli` resolves `platform.redaction` through canonical Kernel ModulePlan dependencies
    - [ ] missing installed redaction dependency fails during module resolution
    - [ ] no CLI-owned provider fallback or second ModulePlan is constructed

  - [ ] `packages/platform/cli/tests/Integration/CliJsonPayloadEnvelopeIsNotRedactedTest.php`
    - [ ] a safe JSON record retains its data under the CLI-owned `payload` field
    - [ ] sensitive structural keys inside the owner-supplied JSON value are still redacted
    - [ ] the CLI record envelope is reconstructed without passing its `payload` field to the shared classifier
    - [ ] original record order and canonical payload ordering are preserved

  - [ ] `packages/platform/cli/tests/Integration/CliSensitiveTextRetainsStringRecordShapeTest.php`
    - [ ] a classified sensitive text value is not emitted unchanged
    - [ ] a structured redaction summary cannot violate the required text record shape
    - [ ] safe placeholder rendering does not disclose hash or length metadata

  - [ ] `packages/platform/cli/tests/Integration/JsonFormatterAndConsoleWriterOwnOneFinalNewlineTest.php`
    - [ ] canonical encoder framing does not produce duplicate final LF bytes
    - [ ] non-empty JSON output ends with exactly one writer-owned LF
    - [ ] JSON output contains no ANSI escape sequences

  - [ ] `packages/platform/cli/tests/Integration/KernelCommandsRejectUndeclaredModeAndPresetOptionsTest.php`
    - [ ] Kernel commands do not declare `mode` or `preset`
    - [ ] both options fail before command service resolution

  - [ ] `packages/platform/cli/tests/Integration/CliUsesMergedValidatedConfigurationTest.php`
    - [ ] skeleton override changes effective CLI output policy
    - [ ] invalid Console-host CLI config fails in the existing ConfigKernel validation pipeline before command dispatch
    - [ ] unknown `cli.mode.*` keys are rejected through canonical ConfigValidator rules
    - [ ] legacy FQCN registry-list `cli.commands` is rejected rather than interpreted as command discovery configuration
    - [ ] canonical `cli.commands.overrides` map remains valid
    - [ ] rejected configuration never reaches `CommandCatalog` or command service resolution
    - [ ] host validation failure produces the fixed `CORETSIA_CLI_HOST_BOOT_FAILED` diagnostic rather than a synthetic Kernel OpsResult
    - [ ] invalid configuration values and filesystem paths are not disclosed
    - [ ] CliServiceFactory does not re-run validation

  - [ ] `packages/platform/worker/tests/Integration/WorkerProviderSourceDefinitionsParityTest.php`
    - [ ] remove `MODE` arguments and `mode` expected fields
    - [ ] preserve source/definition metadata parity for the final six-key schema

  - [ ] `packages/platform/cli/tests/Integration/CliConfigChangesAffectOutputPolicyTest.php`
    - [ ] `cli.output.format_default` changes the effective format when no explicit global `--format` option is supplied
    - [ ] `cli.output.adaptive.interactive` changes the effective format only when adaptive format is selected and stdout is interactive
    - [ ] `cli.output.adaptive.non_interactive` changes the effective format only when adaptive format is selected and stdout is non-interactive
    - [ ] explicit `--format` takes precedence over the configured format default
    - [ ] non-adaptive effective formats do not depend on adaptive interactive/non-interactive mappings
    - [ ] `cli.output.color_default` changes the effective color decision when no explicit global `--color` option is supplied
    - [ ] explicit `--color` takes precedence over the configured color default
    - [ ] effective JSON format disables ANSI regardless of the configured color default
    - [ ] `cli.output.table.max_width` changes the rendered table's deterministic visible-width limit
    - [ ] table width does not affect plain or JSON output
    - [ ] all five output settings originate from the merged and validated `cli` subtree
    - [ ] no resolver or formatter reads configuration directly or invents missing defaults

  - [ ] `packages/platform/cli/tests/Integration/CliCommandUowAttributesAreOwnedByCommandRunnerTest.php`
    - [ ] UoW type is `cli`
    - [ ] canonical command name is passed as `operation`
    - [ ] effective format is passed as `output_format`
    - [ ] effective format is not added to ContextStore
    - [ ] platform/cli performs no direct context writes
    - [ ] `runUnitOfWork()` receives exactly the `operation|output_format` attribute map
    - [ ] UoW type is supplied through `UnitOfWorkType::CLI`
    - [ ] command resolution, class/name verification, execution, and exit-code validation occur inside the UoW callback
    - [ ] raw argv, positional arguments, option values, and output records are absent from UoW attributes
    - [ ] KernelRuntime remains the sole owner of the three base ContextStore writes
    - [ ] no new ContextStore key is introduced for output format
    - [ ] KernelRuntime reset remains authoritative after successful and failed execution

  - [ ] `packages/platform/cli/tests/Integration/CliRejectsInvalidCommandOverridesDeterministicallyTest.php`
    - [ ] ConfigValidator rejects a non-map `cli.commands.overrides`
    - [ ] CommandOverrides rejects invalid dynamic command-name keys
    - [ ] CommandOverrides rejects invalid override value types
    - [ ] CommandOverrides rejects unknown fields
    - [ ] CommandOverrides rejects unknown command names
    - [ ] invalid dynamic overrides detected during CommandCatalog construction fail `CliApplication` resolution
    - [ ] construction-time failures use the fixed `CORETSIA_CLI_HOST_BOOT_FAILED` diagnostic rather than attempting to invoke `CliErrorHandler` before `CliApplication::run()`
    - [ ] no invalid override name, value, command metadata, or Throwable detail appears in the host-bootstrap diagnostic

  - [ ] `packages/platform/cli/tests/Integration/ExternalPackageCommandWithOwnerServiceDispatchTest.php`
    - [ ] command is contributed by an enabled non-CLI package
    - [ ] command resolves an owner-package service
    - [ ] platform/cli imports no owner-package class
    - [ ] command is discovered, validated, resolved, and dispatched through generic infrastructure

  - [ ] `packages/platform/cli/tests/Integration/ExternalPackageCommandMayDeclareDomainModeOptionTest.php`
    - [ ] external command declares `mode` in its own `OPTIONS`
    - [ ] descriptor validation accepts it
    - [ ] value reaches the external command unchanged
    - [ ] it does not affect Kernel preset selection

  - [ ] `packages/platform/cli/tests/Integration/ExternalTaggedCommandIsLazyDiscoveredTest.php`
    - [ ] external tagged command appears in catalog
    - [ ] command constructor is not called during catalog/list/help descriptor build
    - [ ] command constructor is called only on dispatch

  - [ ] `packages/platform/cli/tests/Integration/WorkerCommandMetadataCompatibilityTest.php`
    - [ ] when `platform.worker` is enabled, worker command tag metadata passes `CommandTagSchema`
    - [ ] worker command service ids are discovered through generic `cli.command`
    - [ ] `platform/cli` production source still does not import `Coretsia\Platform\Worker\*`
    - [ ] worker command metadata uses empty `ARGUMENTS` and `OPTIONS`
    - [ ] undeclared options are rejected before Worker command resolution
    - [ ] `--target` is rejected because Worker commands do not declare it
    - [ ] CLI-global `--format` is consumed before Worker command input is constructed
    - [ ] worker command metadata contains no `mode` key
    - [ ] worker command classes contain no `MODE` constant
    - [ ] CLI-global `--color` is consumed before Worker command input is constructed

  - [ ] `packages/platform/cli/tests/Integration/ConfigCompileDelegatesToKernelOpsPortTest.php`
    - [ ] asserts exactly one `KernelOpsInterface` compile call
    - [ ] asserts exactly one explicit app target is passed
    - [ ] asserts the command does not resolve any Kernel compile-time service
    - [ ] asserts no Composer metadata reader is touched by CLI
    - [ ] asserts no artifact compiler is resolved by CLI

  - [ ] `packages/platform/cli/tests/Integration/CacheVerifyDelegatesToKernelOpsPortTest.php`
    - [ ] asserts exactly one `KernelOpsInterface` verify call
    - [ ] asserts exactly one explicit app target is passed
    - [ ] asserts the command does not resolve any Kernel compile-time service
    - [ ] asserts no Composer metadata reader is touched by CLI
    - [ ] asserts no cache verifier is resolved by CLI

  - [ ] `packages/platform/cli/tests/Integration/DebugModulesDelegatesToKernelOpsPortTest.php`
    - [ ] exactly one `debugModules()` call
    - [ ] no module-resolution service is resolved by CLI

  - [ ] `packages/platform/cli/tests/Integration/ConfigValidateDelegatesToKernelOpsPortTest.php`
    - [ ] exactly one `validateConfig()` call
    - [ ] no ConfigKernel or module-resolution service is resolved by CLI

  - [ ] `packages/platform/cli/tests/Integration/ConfigDebugDelegatesToKernelOpsPortTest.php`
    - [ ] exactly one `debugConfig()` call
    - [ ] only safe `OpsResult` data reaches output

  - [ ] `packages/platform/cli/tests/Integration/ConfigHashDelegatesToKernelOpsPortTest.php`
    - [ ] exactly one `hashConfig()` call
    - [ ] no fingerprint or module-resolution service is resolved by CLI

  - [ ] `packages/platform/cli/tests/Integration/ExternalTaggedCommandDiscoveryTest.php`
    - [ ] proves commands from an enabled non-CLI package are discovered through `cli.command`
    - [ ] MUST NOT rely on filesystem scanning
    - [ ] MUST NOT use a `cli.commands` registry list

  - [ ] `packages/platform/cli/tests/Integration/WorkerCommandsAreDiscoverableWhenWorkerPackageEnabledTest.php`
    - [ ] enables `platform.worker` in a composed test fixture/app
    - [ ] asserts `worker:start`, `worker:stop`, `worker:status`, and `worker:health` appear in the command catalog
    - [ ] asserts discovery happens via `cli.command`
    - [ ] MUST NOT require `platform/cli` compile-time dependency on `platform/worker`

  - [ ] `packages/platform/cli/tests/Integration/WorkerStartDispatchesThroughCommandCatalogTest.php`
    - [ ] dispatches `worker:start` through `CliApplication` / `CommandCatalog`
    - [ ] uses a safe fake worker manager or fake command handler
    - [ ] MUST NOT start real worker processes
    - [ ] MUST NOT fork, call `proc_open`, or open sockets
    - [ ] preserves the foreground blocking and WorkerSupervisor lifecycle semantics of `worker:start`
    - [ ] explicitly acknowledges that immediate readiness rendering from the previous output implementation is not preserved by the buffered-output contract
    - [ ] readiness records remain invocation-local and are rendered only after `WorkerSupervisor::run()` returns, normally at shutdown
    - [ ] a fake supervisor emitting readiness before return MUST NOT cause premature stdout or stderr writes
    - [ ] no unredacted direct-stream or secondary output channel is introduced

### DoD (MUST)

- [ ] normal commands are discovered only through `cli.command`
- [ ] Kernel operation commands require explicit `--target`
- [ ] built-in Kernel operation commands reject undeclared `--mode` and `--preset`
- [ ] effective preset comes only from Kernel `OpsResult`
- [ ] each Kernel command makes exactly one `KernelOpsInterface` call
- [ ] platform/cli infrastructure and built-in Kernel commands never read Kernel artifacts or `current`
- [ ] output is deterministic and redacted
- [ ] external package commands remain lazy and package-agnostic
- [ ] a new enabled package can contribute and dispatch a command without any platform/cli production-source change
- [ ] external commands may declare owner-specific arguments and options without entering KernelOpsInterface
- [ ] `doctor` is the only pre-host command path
- [ ] config defaults and rules are exact and synchronized
- [ ] only CliServiceFactory reads `cli.*` configuration
- [ ] normal command execution uses exactly one Kernel UoW
- [ ] platform/cli performs no direct ContextStore writes or reset orchestration
- [ ] command observability is emitted through injected ports
- [ ] observability failures do not affect command exit semantics
- [ ] redaction cannot be disabled
- [ ] `OutputFormatter` consumes only `SensitiveDataRedactorInterface`.
- [ ] `platform/cli` defines no package-local redaction engine, policy, classifier, hasher, or pattern registry.
- [ ] `packages/platform/cli/src/Redaction/` is absent.
- [ ] `packages/platform/cli/src/Output/Redaction/` is absent.
- [ ] adaptive format depends only on explicit terminal capabilities
- [ ] color policy is `auto|always|never`
- [ ] JSON output is always ANSI-free
- [ ] configurable ANSI palettes are not introduced
- [ ] every legacy Phase-0 production file listed under `Deletes` is absent
- [ ] every retained legacy path listed as a complete rewrite contains no Phase-0 behavior
- [ ] no compatibility fallback reads `cli.commands` as an FQCN list
- [ ] no command is instantiated through reflection or `new $fqcn`
- [ ] Worker command metadata matches the final six-key schema
- [ ] after autoload, `ConsoleOutputWriter` is the only production output sink; the fixed pre-autoload failure is the sole exception
- [ ] effective format is a UoW attribute, not a ContextStore key
- [ ] all new metric and span names are registered in observability SSoT
- [ ] `CliApplication` owns Throwable handling for normal-path parsing, global-option resolution, catalog selection, descriptor validation, command resolution, execution, formatting, and redaction
- [ ] construction-time catalog and dependency failures remain owned by `CliHostBootstrap`
- [ ] unexpected Throwables escaping `CliApplication`, including final output-sink failures, remain guarded by the packaged entrypoint's fixed uncaught-Throwable boundary
- [ ] no second error handler, recursive rendering attempt, or fallback CLI execution path is introduced
- [ ] pre-application host failures are handled by `CliHostBootstrap`
- [ ] pre-autoload failures are handled by the fixed binary fallback
- [ ] `CommandRunner` owns execution lifecycle but not error rendering
- [ ] formatter/redactor failure uses one non-recursive fixed fallback
- [ ] command output records and final stdout/stderr bytes have explicit immutable shapes
- [ ] built-in command metadata is complete and exact
- [ ] generic external-package command tests use explicit fixture files
- [ ] Worker compatibility tests use the composed Worker package fixture/application
- [ ] Worker production code and Worker tests use the same six-key command metadata schema
- [ ] built-in Kernel operation commands invoke `KernelOpsInterface` directly without a CLI-owned forwarding façade
- [ ] `KernelOpsRequestResolver` is a source-host-only input adapter and has no `CommandDescriptor` dependency
- [ ] `CommandOutputBuffer` is invocation-local and absent from container definitions
- [ ] `platform/cli` does not bind the global `ErrorHandlerInterface` service id
- [ ] redacted output maps retain canonical ordering from the shared redaction runtime without CLI-local recursive sorting
- [ ] the source host resolves exactly one `SensitiveDataRedactorInterface` implementation
- [ ] command failures clear and reuse the same invocation-local `CommandOutputBuffer`
- [ ] every `cli.command` service id is the exact command class FQCN
- [ ] all four canonical presets select `platform.cli` by default and preserve their existing Core and Worker policies
- [ ] `platform.redaction` is enabled through the canonical `platform.cli` module dependency closure, without duplicate preset roots
- [ ] `coretsia/framework` directly requires the baseline Kernel and CLI packages
- [ ] `coretsia/platform-cli` declares the corresponding Composer and runtime module dependencies for Redaction
- [ ] `composer create-project coretsia/skeleton` installs the baseline Composer graph without custom repository declarations or implicit dependency synchronization
- [ ] the default `micro` preset resolves the intended Console ModulePlan from the installed baseline
- [ ] additional mode-required packages remain subject to explicit DependencySync
- [ ] canonical Kernel preset and consumer documentation examples match the new module sets
- [ ] existing Kernel boot, preset schema, package planning, and consumer installation tests remain valid
- [ ] the released baseline is independently installable from published Composer packages

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

goal: "Надати `coretsia workflow:run <workflow>` як детермінований config-defined composite execution поверх tag-first CommandCatalog, з окремим canonical CLI UoW для кожного step, одним фінальним redacted render і стабільними suggestions для невідомих command names."
provides:
- "Config-defined workflows without filesystem discovery, templates, macros, environment interpolation, or reflective command construction"
- "Every workflow step resolved through the final tag-first CommandCatalog"
- "Every workflow step executed through the existing CommandRunner with exactly one Kernel CLI UoW"
- "One platform-owned composite-command boundary for `workflow:run`, without a nested outer CLI UoW"
- "Fail-fast sequential workflow execution with ordered step results"
- "Deterministic unknown-command suggestions derived only from the final CommandCatalog"
- "One final output formatting and mandatory defense-in-depth redaction pass"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "docs/adr/ADR-XXXX-cli-composite-workflows.md"
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
- a second output or formatting pipeline.

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

- `docs/adr/ADR-XXXX-cli-composite-workflows.md`
- `docs/adr/INDEX.md`
- `docs/ssot/tags.md`
- `docs/ssot/observability.md`

No command-owner package may be modified solely to make its commands workflow-compatible.

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

The workflow command returns that exact first non-zero exit code.

If all steps return `0`, the workflow command returns `0`.

A Throwable from a step MUST propagate unchanged through `WorkflowRunner` and enter the existing `CliApplication` error boundary.

`WorkflowRunner` MUST NOT catch, wrap, downgrade, or convert unexpected command Throwables into a completed workflow result.

### Composite command boundary (MUST)

2.30.0 defines one Kernel CLI UoW around every normal command and forbids nested CLI UoWs.

This epic narrows the earlier 2.30.0 exception wording.

After this epic:

- `doctor` remains the only pre-autoload/pre-host command path;
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

- MUST be a non-empty trimmed single-line string;
- MUST contain no NUL, CR, LF, ESC, or unsafe control bytes;
- MUST be at most 256 bytes.

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
- maximum repeatable values per option: 32.

Unknown keys hard-fail deterministically.

Floats, objects, resources, closures, and Throwables are forbidden.

Workflow names are exposed in `workflow:list` using byte-order `strcmp` order.

Workflow step order is preserved exactly as declared.

### Deliverables (MUST)

#### Creates

Workflow model:
- [ ] `packages/platform/cli/src/Workflow/WorkflowDefinition.php`
  - [ ] immutable readonly value
  - [ ] contains canonical name, summary, and ordered non-empty step list
  - [ ] no config repository, services, closures, or runtime state

- [ ] `packages/platform/cli/src/Workflow/WorkflowStep.php`
  - [ ] immutable readonly value
  - [ ] contains command name, ordered arguments, and normalized option map
  - [ ] no command object, descriptor, service id, or container

- [ ] `packages/platform/cli/src/Workflow/WorkflowSchema.php`
  - [ ] stateless deep validator and normalizer for dynamic workflow definitions
  - [ ] enforces the exact shapes and bounds in this epic
  - [ ] performs no command discovery or service resolution
  - [ ] throws only `WorkflowDefinitionInvalidException`
  - [ ] public diagnostics contain only stable reason tokens

- [ ] `packages/platform/cli/src/Workflow/WorkflowCatalog.php`
  - [ ] immutable finalized workflow catalog
  - [ ] consumes normalized definitions from `WorkflowSchema`
  - [ ] receives the final `CommandCatalog`
  - [ ] validates every configured step command exists
  - [ ] validates every step command is `normal`, not `composite`
  - [ ] an unknown configured step command throws `WorkflowDefinitionInvalidException`
    - [ ] reason `workflow-command-unknown`
  - [ ] a configured composite step throws `WorkflowDefinitionInvalidException`
    - [ ] reason `workflow-command-composite`
  - [ ] these failures expose no arguments, option values, service ids, paths, or workflow definition payload
  - [ ] performs no command service resolution
  - [ ] sorts workflow names by byte-order `strcmp`
  - [ ] preserves step order
  - [ ] canonical APIs:
    - [ ] `public function definitions(): array`
    - [ ] `public function require(string $name): WorkflowDefinition`
  - [ ] `require()` validates the requested lookup token before map access:
    - [ ] same workflow-name regex
    - [ ] maximum 64 bytes
    - [ ] no control bytes
  - [ ] an invalid lookup token throws `WorkflowNotFoundException` without exposing the token
  - [ ] an unknown but valid lookup token MAY be exposed by `WorkflowNotFoundException`

- [ ] `packages/platform/cli/src/Workflow/WorkflowStepInputFactory.php`
  - [ ] stateless structured-input adapter
  - [ ] creates the existing normalized CLI `InputInterface` implementation from one `WorkflowStep`
  - [ ] MUST NOT use shell parsing or shell escaping
  - [ ] MUST NOT read raw argv, config, env, CWD, or filesystem
  - [ ] constructs the normalized `InputInterface` directly; it MUST NOT reparse the generated tokens
  - [ ] constructs canonical command-facing `tokens()` only as the stable token projection of the structured step:
    - [ ] command name first
    - [ ] option keys in byte-order `strcmp` order
    - [ ] `true` as `--name`
    - [ ] `string` as `--name=value`
    - [ ] `list<string>` as repeated `--name=value` tokens preserving list order
    - [ ] when positional arguments exist, one `--` marker before the first argument
    - [ ] positional arguments in declared order
  - [ ] CLI-global `format|color` tokens are never generated
  - [ ] the existing `CommandInputValidator` validates normalized arguments and options, not by reparsing `tokens()`
  - [ ] performs only deterministic structured-input construction
  - [ ] MUST NOT invoke `CommandInputValidator`
  - [ ] MUST NOT perform descriptor, argument, option, required-value, or repeatability validation
  - [ ] MUST NOT implement a second argument/option validation policy

- [ ] `packages/platform/cli/src/Workflow/WorkflowStepResult.php`
  - [ ] immutable readonly value
  - [ ] contains exactly:
    - [ ] `index: int`
    - [ ] `command: string`
    - [ ] `exitCode: int`
    - [ ] `records: list<normalized output record>`
  - [ ] contains no arguments, options, raw tokens, command object, service id, or paths added by workflow infrastructure

- [ ] `packages/platform/cli/src/Workflow/WorkflowResult.php`
  - [ ] immutable readonly value
  - [ ] contains exactly:
    - [ ] workflow name
    - [ ] `success|failure` outcome
    - [ ] final exit code
    - [ ] ordered executed-step results
  - [ ] contains no unexecuted synthetic steps
  - [ ] exports one recursively normalized json-like map

- [ ] `packages/platform/cli/src/Workflow/WorkflowRunner.php`
  - [ ] stateless and re-entrant
  - [ ] receives:
    - [ ] final `CommandCatalog`
    - [ ] existing `CommandInputValidator`
    - [ ] existing `CommandRunner`
    - [ ] `WorkflowStepInputFactory`
  - [ ] for every step:
    - [ ] obtains the normal descriptor from the final `CommandCatalog`
    - [ ] constructs structured input through `WorkflowStepInputFactory`
    - [ ] invokes the existing `CommandInputValidator` exactly once
    - [ ] invokes `CommandRunner` only after successful validation
  - [ ] creates one fresh local `CommandOutputBuffer` per step
  - [ ] MUST NOT resolve command services directly
  - [ ] MUST NOT invoke `CommandInterface::run()` directly
  - [ ] MUST NOT invoke `KernelRuntimeInterface` directly
  - [ ] MUST NOT invoke hooks or reset orchestration
  - [ ] passes the same effective top-level output format to every step `CommandRunner` call
  - [ ] finalizes every successfully completed child output buffer exactly once
  - [ ] never finalizes the child buffer of a throwing step
  - [ ] preserves child record order
  - [ ] stops on the first non-zero returned exit code
  - [ ] MUST NOT catch Throwables for translation, wrapping, observability, or downgrade
  - [ ] MAY catch a step Throwable only to discard the current invocation-local child buffer
  - [ ] after discard, MUST rethrow the exact same Throwable instance
  - [ ] if a step throws, no completed `WorkflowResult` is produced
  - [ ] records from earlier completed steps are not rendered as partial workflow output
  - [ ] no child output from the failed workflow invocation reaches the final formatter
  - [ ] if a step throws:
    - [ ] the current child buffer is not appended to a completed `WorkflowResult`
    - [ ] the current child buffer is discarded before propagation
    - [ ] no partial child batch is formatted or written
    - [ ] no later step is resolved
    - [ ] the original Throwable propagates unchanged

Composite execution:
- [ ] `packages/platform/cli/src/Runner/CompositeCommandInterface.php`
  - [ ] internal platform-only interface
  - [ ] canonical API:
    - [ ] `public function name(): string`
    - [ ] `public function run(InputInterface $input, OutputInterface $output, string $outputFormat): int`
  - [ ] `name()` MUST return the exact command `NAME` constant
  - [ ] `run()` receives:
    - [ ] validated `InputInterface`
    - [ ] invocation-local `OutputInterface`
    - [ ] effective format `json|table|plain`
  - [ ] `run()` returns one portable process exit code in `0..255`
  - [ ] the interface MUST NOT extend `CommandInterface`
  - [ ] one service MUST NOT implement both `CommandInterface` and `CompositeCommandInterface`
  - [ ] no external package extension semantics

Commands:
- [ ] `packages/platform/cli/src/Command/WorkflowListCommand.php`
  - [ ] normal `CommandInterface` command
  - [ ] receives only `WorkflowCatalog`
  - [ ] renders safe workflow names and summaries
  - [ ] does not expose step arguments or option values
  - [ ] metadata:
    - [ ] `NAME = 'workflow:list'`
    - [ ] `SUMMARY = 'List configured CLI workflows.'`
    - [ ] `GROUP = 'workflow'`
    - [ ] `HIDDEN = false`
    - [ ] `ARGUMENTS = []`
    - [ ] `OPTIONS = []`

- [ ] `packages/platform/cli/src/Command/WorkflowRunCommand.php`
  - [ ] implements internal `CompositeCommandInterface`
  - [ ] does not implement `Coretsia\Contracts\Cli\Command\CommandInterface`
  - [ ] `name()` returns `self::NAME`
  - [ ] implements the exact `CompositeCommandInterface::run(...)` signature
  - [ ] exposes the exact six public command metadata constants:
    - [ ] `NAME`
    - [ ] `SUMMARY`
    - [ ] `GROUP`
    - [ ] `HIDDEN`
    - [ ] `ARGUMENTS`
    - [ ] `OPTIONS`
  - [ ] receives only `WorkflowCatalog` and `WorkflowRunner`
  - [ ] requires one workflow argument
  - [ ] writes exactly one json-like `WorkflowResult` record on completed execution
  - [ ] returns the exact workflow final exit code
  - [ ] metadata:
    - [ ] `NAME = 'workflow:run'`
    - [ ] `SUMMARY = 'Run one configured CLI workflow.'`
    - [ ] `GROUP = 'workflow'`
    - [ ] `HIDDEN = false`
    - [ ] `ARGUMENTS` contains exactly:
      - [ ] `name = workflow`
      - [ ] `summary = Workflow name.`
      - [ ] `required = true`
      - [ ] `variadic = false`
    - [ ] `OPTIONS = []`

Suggestions:
- [ ] `packages/platform/cli/src/UX/SmartSuggestor.php`
  - [ ] stateless
  - [ ] consumes only final visible `CommandDescriptor` names from `CommandCatalog`
  - [ ] excludes hidden commands
  - [ ] performs byte-wise lowercase ASCII comparison
  - [ ] uses `levenshtein()` only on already validated ASCII command names
  - [ ] maximum accepted distance:
    - [ ] command length `1..8` → `2`
    - [ ] command length `9+` → `3`
  - [ ] sorts candidates by:
    - [ ] distance ascending
    - [ ] final CommandCatalog order ascending for ties
  - [ ] returns at most three command names
  - [ ] performs no option, argument, workflow, filesystem, or package suggestions
  - [ ] contains no mutable cache

Errors:
- [ ] `packages/platform/cli/src/Exception/WorkflowDefinitionInvalidException.php`
  - [ ] code `CORETSIA_CLI_WORKFLOW_DEFINITION_INVALID`
  - [ ] code-first deterministic public message
  - [ ] exposes only stable reason tokens
  - [ ] contains no definition values, arguments, options, paths, or previous Throwable message

- [ ] `packages/platform/cli/src/Exception/WorkflowNotFoundException.php`
  - [ ] code `CORETSIA_CLI_WORKFLOW_NOT_FOUND`
  - [ ] fixed safe reason `workflow-not-found`
  - [ ] MAY expose only the already validated workflow-name token
  - [ ] contains no configured definition or step data

Docs:
- [ ] `docs/adr/ADR-XXXX-cli-composite-workflows.md`
  - [ ] records the owner-only composite-command exception
  - [ ] records one UoW per workflow step and no outer workflow UoW
  - [ ] records fail-fast sequential execution
  - [ ] records no replay/artifact scope
  - [ ] records no nested workflows or composite steps

#### Modifies

- [ ] `packages/platform/cli/src/Output/CommandOutputBuffer.php`
  - [ ] preserve the existing `finalize()` and `discard()` APIs
  - [ ] broaden the authorized `discard()` callers to exactly:
    - [ ] the existing `CliApplication` top-level error boundary
    - [ ] `WorkflowRunner` for one throwing invocation-local child step
  - [ ] no other production caller may invoke `discard()`
  - [ ] `discard()` remains forbidden after finalization

- [ ] `packages/platform/cli/config/cli.php`
  - [ ] add only `cli.workflows.definitions = []`
  - [ ] preserve all 2.30.0 output and command override defaults unchanged

- [ ] `packages/platform/cli/config/rules.php`
  - [ ] add exact static `workflows.definitions` boundary
  - [ ] preserve `additionalKeys = false` at every static schema-owned level
  - [ ] dynamic keys are allowed only under `cli.commands.overrides` and `cli.workflows.definitions`

- [ ] `packages/platform/cli/src/Catalog/CommandDescriptor.php`
  - [ ] add internal derived `executionKind: normal|composite`
  - [ ] value is not exported or configurable

- [ ] `packages/platform/cli/src/Catalog/CommandTagSchema.php`
  - [ ] preserve the exact six metadata keys
  - [ ] preserve external `CommandInterface` requirement
  - [ ] allow `CompositeCommandInterface` only for exact built-in allowlisted service ids
  - [ ] validates the allowlisted composite class constants against the same exact six-key metadata schema
  - [ ] the composite exception changes only the execution interface requirement, not metadata validation
  - [ ] reject external composite implementations deterministically

- [ ] `packages/platform/cli/src/Catalog/CommandCatalog.php`
  - [ ] add `public function find(string $name): ?CommandDescriptor`
  - [ ] `find()` performs no service resolution
  - [ ] preserve final catalog order

- [ ] `packages/platform/cli/src/Runner/CommandRunner.php`
  - [ ] preserve the normal command path exactly
  - [ ] add exact composite branch described in this epic
  - [ ] remain stateless and re-entrant
  - [ ] preserve output, exit-code, interface, name, and observability validation
  - [ ] MUST NOT create an outer UoW for `WorkflowRunCommand`

- [ ] `packages/platform/cli/src/Output/CommandOutputBatch.php`
  - [ ] add a read-only ordered `records()` accessor
  - [ ] accessor returns the already normalized immutable record list
  - [ ] no mutable reference is exposed

- [ ] `packages/platform/cli/src/Application/CliApplication.php`
  - [ ] unknown command selection uses `CommandCatalog::find()`
  - [ ] writes the canonical unknown-command error record
  - [ ] obtains suggestions only through `SmartSuggestor`
  - [ ] when suggestions exist, appends one json-like record:
    - [ ] `suggestions => list<string>`
  - [ ] suggestions pass through the same final formatter and redactor
  - [ ] no suggestion data enters logs, metrics, or UoW attributes

- [ ] `packages/platform/cli/src/Diagnostics/CliErrorHandler.php`
  - [ ] add known safe mappings for:
    - [ ] `WorkflowDefinitionInvalidException`
    - [ ] `WorkflowNotFoundException`
  - [ ] preserve each exception’s stable public error code
  - [ ] use fixed safe public messages
  - [ ] MUST NOT include workflow definitions, step data, arguments, option values, service ids, paths, or previous Throwable messages
  - [ ] returned descriptor extensions remain empty

- [ ] `packages/platform/cli/src/Provider/CliServiceFactory.php`
  - [ ] remains the only `cli.*` config consumer
  - [ ] reads the complete validated `cli` root through the existing helper
  - [ ] constructs normalized workflow definitions through `WorkflowSchema`
  - [ ] performs no command service resolution

- [ ] `packages/platform/cli/src/Provider/CliServiceProvider.php`
  - [ ] registers/defines workflow services in the same source/definition split as 2.30.0
  - [ ] tags `WorkflowListCommand` and `WorkflowRunCommand` with exact six-key metadata
  - [ ] tag priority is actual `0`
  - [ ] references command constants only
  - [ ] does not conditionally register commands from config

- [ ] `packages/platform/cli/README.md`
  - [ ] document exact workflow config shape
  - [ ] document fail-fast behavior
  - [ ] document one UoW per step
  - [ ] replace the earlier “doctor is the only pre-UoW command” wording with:
    - [ ] `doctor` is the only pre-host command
    - [ ] `workflow:run` is the only source-host composite command without an outer UoW
    - [ ] every normal command and every workflow step has exactly one UoW
  - [ ] document no nested workflows
  - [ ] document no `--mode|--preset` workflow semantics
  - [ ] document deterministic suggestions
  - [ ] explicitly state replay persistence is not provided

- [ ] `docs/ssot/tags.md`
  - [ ] preserve exact six-key metadata
  - [ ] document the single platform-owned composite service-id exception
  - [ ] keep composite capability out of tag metadata
  - [ ] external command services still require `CommandInterface`

- [ ] `docs/ssot/observability.md`
  - [ ] retain existing `cli.command` names and labels
  - [ ] document that `workflow:run` emits one top-level command observation without a Kernel UoW
  - [ ] each workflow step emits its own normal `cli.command` observation inside its own UoW
  - [ ] workflow name, step arguments, and option values are forbidden metric labels
  - [ ] step arguments and option values are forbidden span attributes
  - [ ] `CommandRunner` remains the sole owner of both normal and composite CLI command lifecycle observability
  - [ ] `WorkflowRunner` MUST NOT emit duplicate step or workflow lifecycle signals
  - [ ] `WorkflowRunCommand` MUST NOT emit lifecycle signals
  - [ ] one user workflow invocation emits:
    - [ ] one top-level `cli.command` observation for `workflow:run`
    - [ ] zero or more normal `cli.command` observations, one for each executed step
  - [ ] unexecuted steps emit no observability

- [ ] `docs/adr/INDEX.md`
  - [ ] register ADR-XXXX-cli-composite-workflows.md

### Output, redaction, context, and state (MUST)

- [ ] Workflow infrastructure MUST NOT write stdout or stderr.
- [ ] Final workflow output MUST pass through the existing mandatory `OutputFormatter` redaction boundary exactly once.
- [ ] The existing `OutputFormatter` remains the sole workflow and suggestion rendering consumer of `SensitiveDataRedactorInterface`.
- [ ] `WorkflowRunCommand`, `WorkflowRunner`, `WorkflowCatalog`, `WorkflowSchema`, `SmartSuggestor`, and workflow result DTOs MUST NOT:
  - [ ] receive `SensitiveDataRedactorInterface`
  - [ ] resolve a redactor
  - [ ] instantiate a concrete redactor
  - [ ] define a redaction classifier, policy, hasher, or pattern registry
- [ ] Workflow results, suggestions, and workflow error records MUST NOT bypass the existing 2.30.0 output/redaction pipeline.
- [ ] Child command batches MUST NOT be redacted separately.
- [ ] This epic introduces no prompts, confirmations, previews, or separate interactive diagnostic-output path.
- [ ] Child step batches MUST NOT be formatted separately.
- [ ] Child step batches MUST NOT be written directly.
- [ ] Workflow infrastructure MUST NOT persist output.
- [ ] Workflow infrastructure MUST NOT log arguments, options, child records, paths, payloads, or workflow definition values.
- [ ] All 2.30.0 context, UoW, reset, observability, and redaction rules remain normative except for the explicitly documented missing outer UoW around `workflow:run`.
- [ ] The `CommandRunner` composite branch owns the top-level `workflow:run` lifecycle observation.
- [ ] `WorkflowRunCommand` and `WorkflowRunner` MUST NOT receive:
  - [ ] `ContextAccessorInterface`
  - [ ] `ContextKeys`
  - [ ] `TracerPortInterface`
  - [ ] `MeterPortInterface`
  - [ ] `LoggerInterface`
  - [ ] `Stopwatch`
- [ ] `WorkflowRunCommand` and `WorkflowRunner` MUST NOT emit command lifecycle spans, metrics, or logs.
- [ ] Top-level `workflow:run` observability MUST NOT:
  - [ ] synthesize a correlation id
  - [ ] synthesize a UoW id
  - [ ] read correlation or UoW ids left by a completed step
  - [ ] aggregate step correlation or UoW ids
- [ ] Each normal step retains the existing 2.30.0 context policy inside its own Kernel UoW.
- [ ] Step correlation ids and UoW ids MUST NOT be included in `WorkflowStepResult` or `WorkflowResult`.
- [ ] Top-level workflow observability MAY use only:
  - [ ] operation `workflow:run`;
  - [ ] outcome;
  - [ ] final exit code as a bounded span/log attribute under existing policy.
  - [ ] Top-level `workflow:run` observability uses no context-derived fields.
- [ ] Workflow name MUST NOT be a metric label.
- [ ] Platform CLI MUST NOT write `ContextStore` directly.
- [ ] Every step UoW is created and completed only by the existing `CommandRunner` and `KernelRuntimeInterface` path.
- [ ] Reset occurs after each step through the canonical Kernel UoW lifecycle.
- [ ] No workflow object, result, child buffer, or current-step state is stored in a shared service.
- [ ] All workflow services are immutable/stateless except invocation-local child buffers.
- [ ] No workflow service implements `ResetInterface`.

### Tests (MUST)

- Unit:
  - [ ] `packages/platform/cli/tests/Unit/WorkflowRunnerUsesFreshChildBufferPerStepTest.php`
  - [ ] `packages/platform/cli/tests/Unit/WorkflowRunnerPropagatesThrowableUnchangedTest.php`
  - [ ] `packages/platform/cli/tests/Unit/WorkflowCatalogRejectsUnknownCommandTest.php`
  - [ ] `packages/platform/cli/tests/Unit/WorkflowCatalogRejectsCompositeStepTest.php`

  - [ ] `packages/platform/cli/tests/Unit/WorkflowStepInputFactoryCanonicalProjectionTest.php`
    - [ ] option keys use `strcmp` order
    - [ ] bare flags use `true`
    - [ ] repeated values preserve list order
    - [ ] `--` separates positional arguments
    - [ ] false and null config values are rejected by `WorkflowSchema`

  - [ ] `packages/platform/cli/tests/Unit/WorkflowRunnerDiscardsThrowingChildBufferTest.php`
    - [ ] the throwing child buffer is discarded
    - [ ] the same Throwable instance is rethrown
    - [ ] no completed workflow result is emitted

  - [ ] `packages/platform/cli/tests/Unit/WorkflowSchemaTest.php`
    - [ ] accepts the exact canonical shape
    - [ ] rejects unknown keys, macros, templates, floats, unsafe strings, invalid option values, and all bounds violations

  - [ ] `packages/platform/cli/tests/Unit/WorkflowCatalogDeterminismTest.php`
    - [ ] workflow names are `strcmp` sorted
    - [ ] step order is preserved
    - [ ] no command service is instantiated

  - [ ] `packages/platform/cli/tests/Unit/WorkflowRunnerStopsOnFirstNonZeroExitCodeTest.php`
    - [ ] exact first non-zero code is returned
    - [ ] later steps are not resolved

  - [ ] `packages/platform/cli/tests/Unit/SmartSuggestorTest.php`
    - [ ] fixed thresholds
    - [ ] maximum three results
    - [ ] hidden commands excluded
    - [ ] catalog-order tie-break

  - [ ] `packages/platform/cli/tests/Unit/CommandRunnerCompositeExecutionTest.php`
    - [ ] no outer Kernel UoW for `workflow:run`
    - [ ] normal commands remain unchanged

- Contract:
  - [ ] `packages/platform/cli/tests/Contract/WorkflowConfigShapeContractTest.php`
  - [ ] `packages/platform/cli/tests/Contract/WorkflowCommandsUseExactTagMetadataTest.php`
  - [ ] `packages/platform/cli/tests/Contract/OnlyWorkflowRunMayUseCompositeCommandInterfaceTest.php`
  - [ ] `packages/platform/cli/tests/Contract/WorkflowInfrastructureDoesNotWriteToStdoutTest.php`

  - [ ] `packages/platform/cli/tests/Contract/WorkflowUxIntroducesNoRedactionBoundaryContractTest.php`
    - [ ] workflow and suggestion classes do not depend on a concrete redactor
    - [ ] workflow and suggestion classes do not receive `SensitiveDataRedactorInterface`
    - [ ] no workflow-local or suggestion-local classifier, policy, hasher, or pattern registry exists
    - [ ] no prompt, confirmation, preview, or separate diagnostics renderer is introduced
    - [ ] the existing `OutputFormatter` remains the sole final redaction boundary

  - [ ] `packages/platform/cli/tests/Contract/WorkflowEpicIntroducesNoReplayArtifactTest.php`
    - [ ] no replay classes, config keys, artifact registry entry, or replay SSoT exists

- Integration:
  - [ ] `packages/platform/cli/tests/Integration/CliErrorHandlerMapsWorkflowExceptionsSafelyTest.php`
  - [ ] `packages/platform/cli/tests/Integration/WorkflowRunAggregatesOrderedStepRecordsTest.php`
  - [ ] `packages/platform/cli/tests/Integration/WorkflowRunRejectsModeAndPresetOptionsTest.php`
  - [ ] `packages/platform/cli/tests/Integration/WorkflowRunKernelOpsStepsUseDefinitionTargetTest.php`
  - [ ] `packages/platform/cli/tests/Integration/UnknownCommandSuggestionsUseFinalCatalogTest.php`
  - [ ] `packages/platform/cli/tests/Integration/WorkflowDefinitionsRequireNoFilesystemScanningTest.php`
  - [ ] `packages/platform/cli/tests/Integration/WorkflowRunExecutesEveryStepThroughCommandRunnerTest.php`

  - [ ] `packages/platform/cli/tests/Integration/WorkflowRunFinalOutputIsRedactedOnceTest.php`
    - [ ] completed workflow output invokes the shared redaction port exactly once
    - [ ] child step batches are not redacted separately
    - [ ] raw sensitive fixture values from child records are absent from final output
    - [ ] no workflow-specific redactor is resolved
    - [ ] record order remains unchanged after redaction

  - [ ] `packages/platform/cli/tests/Integration/WorkflowRunUsesOneKernelUowPerStepTest.php`
    - [ ] no outer workflow UoW
    - [ ] no nested UoW
    - [ ] reset completes after every step

### DoD (MUST)

- [ ] Workflows are loaded only from validated `cli.workflows.definitions`.
- [ ] No workflow filesystem discovery exists.
- [ ] No templates, macros, environment interpolation, shell commands, retries, branches, or parallel execution exist.
- [ ] Workflow names are deterministic and `strcmp` sorted.
- [ ] Step order is preserved.
- [ ] Every step command comes from the final tag-first `CommandCatalog`.
- [ ] No command service is resolved during workflow catalog construction.
- [ ] `workflow:run` has no outer Kernel UoW.
- [ ] Every workflow step has exactly one normal Kernel CLI UoW.
- [ ] No nested workflow/composite step is allowed.
- [ ] The first non-zero step code stops execution and is returned unchanged.
- [ ] Step Throwables propagate unchanged to the existing CLI error boundary.
- [ ] Workflow child output is aggregated in order and formatted/redacted once.
- [ ] Workflow and suggestion infrastructure introduces no second redaction or rendering model.
- [ ] No prompt, confirmation, preview, or separate interactive diagnostics pipeline exists in this epic.
- [ ] The shared redaction port is consumed only through the existing final `OutputFormatter`.
- [ ] Suggestions are deterministic, bounded, catalog-backed, and exclude hidden commands.
- [ ] No replay artifact, storage, path config, recorder, or replay SSoT is introduced.
- [ ] All unit, contract, integration, architecture, ECS, PHPStan, and package-compliance checks pass.

---

### 2.50.0 Target-aware skeleton HTTP front controllers + deterministic smoke (MUST) [IMPL]

---
type: skeleton
phase: 2
epic_id: "2.50.0"
owner_path: "packages/applications/skeleton/apps/"

goal: "Надати стабільні target-aware HTTP front controllers для skeleton web/api та deterministic `composer serve` / `composer smoke:http` flow до появи platform/http; front-controller paths залишаються незмінними, а тимчасовий 503 fallback пізніше замінюється реальним HTTP runtime bootstrap."
provides:
- "Real stable HTTP entrypoints: `packages/applications/skeleton/apps/web/public/index.php` and `packages/applications/skeleton/apps/api/public/index.php`."
- "Explicit canonical target ownership: each front controller hardcodes exactly one target, `web` or `api`."
- "Shared non-public HTTP bootstrap seam that currently emits deterministic 503 and is replaced internally when platform/http becomes available."
- "Target-aware dev server command using `--target=web|api`."
- "Pure-PHP socket-based HTTP smoke checker with byte-exact body verification and no cURL dependency."
- "Deterministic smoke orchestration: start one server, wait for readiness, execute one checker, and terminate the server."
- "No request reflection, superglobal capture, request headers, cookies, request bodies, paths, env values, or process diagnostics in public output."
- "Repo-root Composer entrypoints with CWD-independent script behavior."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/architecture/PACKAGING.md"
- "docs/ssot/modes.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required deliverables:
  - `packages/applications/skeleton/apps/web/public/` exists
  - `packages/applications/skeleton/apps/api/public/` exists
  - repo-root `composer.json` exists

- Existing packaging enforcement retained unchanged:
  - `tools/gates/no_skeleton_http_default_gate.php`

- Gate boundary:
  - the gate forbids only repo-root `packages/applications/skeleton/config/http.php`
  - application front controllers under `packages/applications/skeleton/apps/<http-app>/public/` are not HTTP config defaults
  - this epic MUST NOT add an allowlist or exception to the gate

- New tooling boundary:
  - `tools/http/serve` does not exist before this epic and is created here
  - `tools/http/smoke` does not exist before this epic and is created here
  - `tools/http/smoke-http` does not exist before this epic and is created here
  - source files imported from another project are implementation inputs only
  - imported implementations MUST be adapted completely to this epic and MUST NOT define compatibility requirements

- Canonical target tokens already exist:
  - `web`
  - `api`
  - `console`
  - `worker`

- Scope boundary:
  - this epic implements HTTP entrypoints only for `web|api`
  - `console` remains owned by platform/cli
  - `worker` remains owned by the worker runtime

### Entry points / integration points (MUST)

- Repo-root Composer scripts:
  - `composer serve`
    - delegates to `@php tools/http/serve`
    - accepts script arguments after `--`
    - canonical target option: `--target=web|api`
  - `composer smoke:http`
    - delegates to `@php tools/http/smoke http`
    - accepts script arguments after `--`
    - canonical target option: `--target=web|api`

- Target ownership:
  - `serve --target=web` selects `packages/applications/skeleton/apps/web/public`
  - `serve --target=api` selects `packages/applications/skeleton/apps/api/public`
  - `smoke http --target=<target>` starts and verifies the same target
  - target selection changes only the selected skeleton docroot
  - the public front controller independently declares the same canonical target
  - no command-line value is passed into the front controller as runtime target state

- Unsupported targets:
  - `console|worker` fail deterministically for `serve`
  - `console|worker` fail deterministically for `smoke http`
  - no HTTP entrypoint is created for those targets by this epic

### Deterministic CLI contract (MUST)

The following rules apply to `tools/http/serve`, `tools/http/smoke`, and `tools/http/smoke-http`:

- long options use only `--name=value` form
- boolean flags use only `--name`
- split forms such as `--port 8080` are rejected
- unknown options are rejected
- duplicate options are rejected
- missing option values are rejected
- empty option values are rejected
- option names are ASCII case-sensitive
- positional arguments are forbidden except the exact `http` command accepted by `tools/http/smoke`
- exact command layouts are:
  - `tools/http/serve [options]`
  - `tools/http/smoke http [options]`
  - `tools/http/smoke-http [options]`
- for `tools/http/smoke`, `http` must be the first argument after the script path
- options may appear in any order after the command token
- options before `http` are rejected
- additional positional arguments are rejected
- no option value is read from env or config

Host model:

- accepted `--host` values are exactly:
  - `localhost`
  - `127.0.0.1`
  - `0.0.0.0`
- every other hostname or IPv4 literal is rejected
- IPv6 host syntax is out of scope for this epic
- whitespace, control bytes, URI syntax, ports, paths, and user-info are forbidden inside `--host`

Canonical host derivation:

- requested `localhost`:
  - bind host = `127.0.0.1`
  - connect host = `127.0.0.1`
- requested `127.0.0.1`:
  - bind host = `127.0.0.1`
  - connect host = `127.0.0.1`
- requested `0.0.0.0`:
  - bind host = `0.0.0.0`
  - connect host = `127.0.0.1`

Usage:

- `serve` binds the PHP built-in server to the canonical bind host
- `serve` readiness probes use the canonical connect host
- `smoke` passes the canonical bind host to `serve`
- `smoke` readiness and cleanup probes use the canonical connect host
- `smoke` passes the canonical connect host to `smoke-http`
- `smoke-http` always connects through the canonical connect host
- `smoke-http` uses the canonical connect host in the HTTP `Host` field
- `localhost` is never passed to sockets or the PHP built-in server after canonicalization

Port validation:

- ASCII decimal digits only
- range `1..65535`
- no sign, whitespace, decimal point, exponent, or leading/trailing data

Timeout validation:

- ASCII decimal digits only
- range `1..60`
- default is exactly `5` seconds
- applies to `tools/http/smoke` readiness and cleanup waits
- applies to `tools/http/smoke-http` connect and response reads
- timeout measurement uses monotonic `hrtime(true)`
- wall-clock time, timezone, and system date do not participate
- each bounded phase receives one total deadline
- reading a partial chunk does not reset or extend a deadline
- `smoke` readiness polling interval is exactly `50` milliseconds
- `smoke` cleanup-port polling interval is exactly `50` milliseconds

Process result:

- successful finite commands exit with status `0`
- deterministic command failure exits with status `1`
- successful `serve` remains active until externally terminated
- every explicit command failure writes exactly one JSON line to stderr:
  - `{"schema":1,"code":"<CODE>"}\n`
- no command writes a PHP warning, stack trace, previous exception message, usage dump, or child-process output

Process execution and ephemeral IPC contract:

- platform null sink is:
  - `NUL` when `PHP_OS_FAMILY === 'Windows'`
  - `/dev/null` on every other supported OS
- every `proc_open` command uses an argument vector
- no child process inherits parent terminal streams implicitly
- stdout/stderr pipes are not used for readiness, control, or child diagnostics
- temporary IPC paths and contents never appear in stdout, stderr, HTTP output, or exceptions

`proc_open` options:

- on Windows:
  - `bypass_shell = true`
  - `suppress_errors = true`
- on every other supported OS:
  - no platform-specific process option is required
- on Windows, every `proc_open` call explicitly uses `bypass_shell = true`
- command arrays MUST NOT rely on implicit `cmd.exe` behavior

Bounded process termination:

- every termination deadline uses monotonic `hrtime(true)`
- process status is polled every `50` milliseconds
- graceful termination phase:
  - requests normal process termination
  - waits for at most the selected timeout
- hard termination phase runs only if the process remains active:
  - on POSIX, requests signal `9`
  - on Windows, repeats `proc_terminate` using native process termination
  - waits for at most one additional selected-timeout interval
- no process wait resets or extends its current deadline
- `proc_close` is called only after the process is observed inactive
- a process that remains active after the hard-termination deadline is a deterministic cleanup failure

`smoke` ephemeral control directory:

- before starting `serve`, `smoke` creates one private directory under `sys_get_temp_dir()`
- directory basename is exactly:
  - `coretsia-http-smoke-` followed by `bin2hex(random_bytes(16))`
- the generated suffix is internal collision-avoidance state only and does not participate in observable output
- directory creation uses exclusive semantics
- directory permissions are `0700` where the platform supports Unix permission modes
- an existing path, symlink, or failed directory creation maps to `CORETSIA_SMOKE_INTERNAL_FAILURE`
- the directory contains only:
  - `ready`
  - `ready.tmp`
  - `stop`
  - `stop.tmp`
- these files are ephemeral runtime IPC files, not generated artifacts
- `smoke` owns creation and final removal of the directory
- failure to remove owned IPC files or the directory after process startup maps to `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`
- failure to remove the owned control directory before any child process was successfully started maps to `CORETSIA_SMOKE_INTERNAL_FAILURE`
- control-directory cleanup is idempotent; repeated invocation performs no duplicate output and does not change an already selected failure unless cleanup itself fails

Atomic IPC file publication:

- `ready.tmp` and `stop.tmp` are created with exclusive-create semantics
- publication sequence is exact:
  1. confirm that both temporary and final destination paths are absent
  2. open the temporary path as a new regular file
  3. write all expected bytes using a complete-write loop
  4. flush the stream
  5. close the stream
  6. atomically rename the temporary file to its final filename in the same directory
- partial writes are never accepted
- rename never replaces an existing destination
- temporary and final paths must remain regular non-symlink paths
- readiness publication failure maps inside `serve` to `CORETSIA_SERVE_INTERNAL_FAILURE`
- stop publication failure inside `smoke` skips orderly stop and proceeds to bounded hard termination
- temporary IPC bytes are never read until publication rename has completed

Readiness IPC:

- `serve` receives the control directory through internal option `--control-dir=<absolute-path>`
- when no `--control-dir` is provided, direct interactive `serve` behavior remains unchanged
- when `--control-dir` is provided:
  - `--quiet` is required
  - the directory must already exist
  - the directory must not be a symlink
  - `ready`, `ready.tmp`, `stop`, and `stop.tmp` must initially be absent
- after built-in-server TCP readiness, `serve` publishes `ready` through the exact atomic IPC file-publication contract
- readiness bytes are exactly:
  - `CORETSIA_SERVE_READY target=<target> bind=<bind-host>:<port> url=http://<connect-host>:<port>\n`
- readiness is established only when:
  - `ready` exists
  - `ready` is a regular file
  - its complete contents equal the expected readiness bytes
  - the serve wrapper is still active
  - the post-readiness TCP probe succeeds
- malformed, oversized, unexpected, or symlinked readiness state maps to `CORETSIA_SMOKE_SERVER_START_FAILED`

Stop IPC:

- cleanup publishes `stop` with exact bytes `CORETSIA_SERVE_STOP\n` through the atomic IPC file-publication contract
- while active, `serve` polls for `stop` every `50` milliseconds
- `serve` accepts stop only when:
  - `stop` is a regular file
  - its contents equal `CORETSIA_SERVE_STOP\n` byte-for-byte
- valid stop state triggers orderly built-in-server termination and serve-wrapper exit status `0`
- malformed or symlinked stop state maps to `CORETSIA_SERVE_INTERNAL_FAILURE`
- EOF and stdin bytes have no serve-control semantics

Child descriptors:

- `serve` → PHP built-in server:
  - stdin = platform null sink
  - stdout = platform null sink
  - stderr = platform null sink
- `smoke` → `serve`:
  - stdin = platform null sink
  - stdout = platform null sink
  - stderr = platform null sink
- `smoke` → `smoke-http`:
  - stdin = platform null sink
  - stdout = platform null sink
  - stderr = platform null sink

Unexpected failure containment:

- each CLI script has one top-level failure boundary
- expected validation, process, socket, and response failures map to their documented codes
- every other caught `Throwable` maps to a script-local internal failure code:
  - `tools/http/serve` → `CORETSIA_SERVE_INTERNAL_FAILURE`
  - `tools/http/smoke` → `CORETSIA_SMOKE_INTERNAL_FAILURE`
  - `tools/http/smoke-http` → `CORETSIA_SMOKE_HTTP_INTERNAL_FAILURE`
- previous Throwable objects are not retained after mapping
- Throwable messages, classes, traces, files, and line numbers are never copied into output
- PHP warnings produced by filesystem, process, stream, or socket operations are suppressed or converted inside the top-level failure boundary
- any temporary error handler is restored before command termination

### Skeleton HTTP packaging boundary (MUST)

- HTTP-facing skeleton apps MAY ship their canonical executable front controller:
  - `packages/applications/skeleton/apps/web/public/index.php`
  - `packages/applications/skeleton/apps/api/public/index.php`

- A front controller:
  - is an application entrypoint
  - is not a config root
  - MUST NOT own module selection
  - MUST NOT own runtime-driver selection
  - MUST NOT embed environment-specific HTTP configuration

- Default HTTP config remains forbidden:
  - `packages/applications/skeleton/config/http.php` MUST remain absent
  - the existing `no_skeleton_http_default_gate.php` remains unchanged
  - no path-specific allowlist is introduced

### Deliverables (MUST)

#### Creates

- [ ] Before implementation, re-review this epic against the current runtime-driver ownership boundary: `RuntimeDriverResolver` remains Kernel matrix-only; owner packages/adapters own their package/module prerequisites, adapter/transport/executable readiness, and `RuntimeDriverContributions` carry selected canonical drivers only.

- [ ] `skeleton/bootstrap/HttpFrontController.php`
  - [ ] declares final class `Coretsia\Skeleton\Bootstrap\HttpFrontController`
  - [ ] file is outside every public docroot
  - [ ] requiring the file only declares the class
  - [ ] requiring the file emits no output, headers, status, warnings, or side effects
  - [ ] exact typed public constant:
    - [ ] `public const string BOOT_NOT_READY_BODY`
    - [ ] exact value: `{"schema":1,"code":"CORETSIA_HTTP_BOOT_NOT_READY","message":"boot not ready"}\n`
  - [ ] public static entrypoint:
    - [ ] `public static function run(string $target): void`
  - [ ] accepts exactly:
    - [ ] `web`
    - [ ] `api`
  - [ ] any other target throws fixed `LogicException('http-front-controller-target-invalid')`
  - [ ] target validation occurs before any status, header, or output side effect
  - [ ] current Phase-2 behavior:
    - [ ] removes `X-Powered-By`
    - [ ] sets status `503`
    - [ ] sets `Content-Type: application/json; charset=utf-8`
    - [ ] sets `Cache-Control: no-store`
    - [ ] sets `Content-Length` from exact body bytes
    - [ ] outputs `BOOT_NOT_READY_BODY` exactly once
  - [ ] body is ASCII-only, LF-only, and ends with exactly one LF
  - [ ] performs no JSON encoding at request time
  - [ ] reads no superglobals
  - [ ] reads no config or env
  - [ ] requires no vendor autoloader or HTTP runtime
  - [ ] performs no request reflection
  - [ ] contains no timestamp, path, host, port, target, header, cookie, query, body, process, or exception data in output
  - [ ] is the stable bootstrap seam whose internal fallback is replaced by platform/http in Phase 3

- [ ] `packages/applications/skeleton/apps/web/public/index.php`
  - [ ] real stable web front controller
  - [ ] CWD-independent require of `skeleton/bootstrap/HttpFrontController.php`
  - [ ] calls exactly:
    - [ ] `HttpFrontController::run('web')`
  - [ ] contains no target inference
  - [ ] reads no superglobals
  - [ ] contains no fallback response implementation of its own
  - [ ] contains no executable code after the `run()` call

- [ ] `packages/applications/skeleton/apps/api/public/index.php`
  - [ ] real stable API front controller
  - [ ] CWD-independent require of `skeleton/bootstrap/HttpFrontController.php`
  - [ ] calls exactly:
    - [ ] `HttpFrontController::run('api')`
  - [ ] contains no target inference
  - [ ] reads no superglobals
  - [ ] contains no fallback response implementation of its own
  - [ ] contains no executable code after the `run()` call

- [ ] `tools/http/serve`
  - [ ] new pure-PHP executable script
  - [ ] strict argument parser
  - [ ] CWD-independent repo-root discovery through `__DIR__`
  - [ ] accepts exactly:
    - [ ] `--target=<target>`
    - [ ] `--host=<host>`
    - [ ] `--port=<port>`
    - [ ] `--quiet`
    - [ ] `--control-dir=<absolute-path>` as an orchestration-only IPC option
  - [ ] defaults:
    - [ ] `target = web`
    - [ ] `host = 127.0.0.1`
    - [ ] `port = 8080`
    - [ ] `quiet = false`
    - [ ] `control-dir = null`
  - [ ] `--control-dir` rules:
    - [ ] path must be absolute
    - [ ] path must already exist as a directory
    - [ ] path must not be a symlink
    - [ ] path must contain none of the reserved IPC files before startup
    - [ ] option is valid only together with `--quiet`
    - [ ] invalid control-directory state maps to `CORETSIA_SERVE_INVALID_ARGUMENT`
    - [ ] rejected path is never included in failure output
  - [ ] MUST NOT accept:
    - [ ] `--app`
    - [ ] `--docroot`
    - [ ] `--router`
    - [ ] env overrides
    - [ ] arbitrary PHP binary
    - [ ] arbitrary child command
  - [ ] target mapping is exact:
    - [ ] `web` → `packages/applications/skeleton/apps/web/public`
    - [ ] `api` → `packages/applications/skeleton/apps/api/public`
  - [ ] target validation occurs after syntactic argument parsing:
    - [ ] `web|api` are accepted HTTP targets
    - [ ] `console|worker` fail with `CORETSIA_SERVE_TARGET_NOT_HTTP`
    - [ ] every other target fails with `CORETSIA_SERVE_TARGET_INVALID`
  - [ ] target comparison is exact ASCII and case-sensitive
  - [ ] router is always `<resolved-docroot>/index.php`
  - [ ] missing docroot fails with `CORETSIA_SERVE_DOCROOT_MISSING`
  - [ ] missing front controller fails with `CORETSIA_SERVE_FRONT_CONTROLLER_MISSING`
  - [ ] invalid arguments fail with `CORETSIA_SERVE_INVALID_ARGUMENT`
  - [ ] occupied port fails with `CORETSIA_SERVE_PORT_UNAVAILABLE`
  - [ ] port availability is checked before `proc_open` through a temporary bind to the canonical bind host and selected port
  - [ ] the temporary socket is closed before the PHP built-in server is started
  - [ ] failure of the preflight bind maps only to `CORETSIA_SERVE_PORT_UNAVAILABLE`
  - [ ] readiness polling connects through the canonical connect host
  - [ ] child startup failure fails with `CORETSIA_SERVE_START_FAILED`
  - [ ] unexpected post-readiness child exit fails with `CORETSIA_SERVE_CHILD_EXITED`
  - [ ] every other unexpected failure fails with `CORETSIA_SERVE_INTERNAL_FAILURE`
  - [ ] starts the PHP built-in server through `proc_open`
  - [ ] after `proc_open`, polls both:
    - [ ] child process status
    - [ ] TCP reachability of the canonical connect host and selected port
  - [ ] fixed startup timeout is exactly `5` seconds
  - [ ] readiness polling uses a fixed sleep interval of `50` milliseconds
  - [ ] child exit before TCP readiness maps to `CORETSIA_SERVE_START_FAILED`
  - [ ] startup timeout maps to `CORETSIA_SERVE_START_FAILED`
  - [ ] partial readiness diagnostics are never emitted
  - [ ] the readiness line is emitted only after a TCP connection succeeds
  - [ ] command is an argument vector using exactly:
    - [ ] `PHP_BINARY`
    - [ ] `-n`
    - [ ] `-d`
    - [ ] `expose_php=0`
    - [ ] `-d`
    - [ ] `display_errors=0`
    - [ ] `-d`
    - [ ] `html_errors=0`
    - [ ] `-d`
    - [ ] `log_errors=0`
    - [ ] `-S`
    - [ ] `<bind-host>:<port>`
    - [ ] `-t`
    - [ ] resolved target docroot
    - [ ] resolved target `index.php`
  - [ ] `-n` prevents ambient `php.ini`, scanned INI files, `auto_prepend_file`, and `auto_append_file` from changing the stub server
  - [ ] built-in-server child environment is inherited only after removing every environment key whose ASCII case-insensitive name equals `PHP_CLI_SERVER_WORKERS`
  - [ ] environment values are not used to select target, host, port, docroot, router, response, or process count
  - [ ] the built-in server always remains a single owned child process
  - [ ] MUST NOT use shell interpolation or a command string
  - [ ] child working directory is the repo root
  - [ ] child stdin/stdout/stderr use the exact platform-null descriptor contract
  - [ ] retains the child process handle
  - [ ] registers shutdown cleanup
  - [ ] forwards/handles supported termination signals where available
  - [ ] terminates and closes the child on shutdown
  - [ ] does not create a server log
  - [ ] does not include child output in diagnostics
  - [ ] normal mode without `--control-dir` prints exactly these readiness bytes:
    - [ ] `CORETSIA_SERVE_READY target=<target> bind=<bind-host>:<port> url=http://<connect-host>:<port>\n`
  - [ ] `--quiet` suppresses readiness stdout
  - [ ] when `--control-dir` is present, readiness is always published through the exact atomic ready-file protocol
  - [ ] `--quiet` does not suppress ready-file publication
  - [ ] `--quiet` suppresses only the readiness line; it does not suppress deterministic failure output
  - [ ] after readiness, the parent remains alive while the child server is running
  - [ ] while active, the parent polls:
    - [ ] built-in-server child status
    - [ ] the optional control-directory stop file
  - [ ] when no control directory exists, stdin has no command semantics
  - [ ] when a control directory exists, only the exact atomic stop-file protocol requests orderly shutdown
  - [ ] valid stop state:
    - [ ] is never written to stdout or stderr
    - [ ] triggers termination and closure of the built-in-server child
    - [ ] causes the serve wrapper to exit with status `0`
    - [ ] produces no failure payload
  - [ ] built-in-server child shutdown uses the bounded process-termination contract with timeout `5`
  - [ ] failure to terminate the built-in-server child after the hard-termination phase:
    - [ ] emits `CORETSIA_SERVE_INTERNAL_FAILURE`
    - [ ] exits with status `1`
  - [ ] unexpected child termination after readiness:
    - [ ] emits `{"schema":1,"code":"CORETSIA_SERVE_CHILD_EXITED"}\n` to stderr
    - [ ] exits with status `1`
  - [ ] orderly shutdown requested through the valid atomic stop-file protocol or a supported parent termination signal exits without failure output
  - [ ] failure output is one JSON line on stderr:
    - [ ] `{"schema":1,"code":"<CODE>"}\n`
  - [ ] failure output contains no host, port, path, process id, command, exception message, or child output

- [ ] `tools/http/smoke`
  - [ ] pure-PHP deterministic smoke orchestrator
  - [ ] accepts exactly:
    - [ ] positional command `http`
    - [ ] `--target=<target>`
    - [ ] `--host=<host>`
    - [ ] `--port=<port>`
    - [ ] `--timeout=<seconds>`
  - [ ] defaults:
    - [ ] `target = web`
    - [ ] `host = 127.0.0.1`
    - [ ] `port = 8080`
    - [ ] `timeout = 5`
  - [ ] target validation occurs after syntactic argument parsing:
    - [ ] `web|api` are accepted HTTP targets
    - [ ] `console|worker` fail with `CORETSIA_SMOKE_TARGET_NOT_HTTP`
    - [ ] every other target fails with `CORETSIA_SMOKE_TARGET_INVALID`
  - [ ] target comparison is exact ASCII and case-sensitive
  - [ ] rejects `all|cli|db|config` and unknown commands deterministically
  - [ ] creates one private ephemeral control directory before process startup
  - [ ] immediately after successful directory creation, registers one idempotent cleanup boundary
  - [ ] the cleanup boundary is valid before any child process exists
  - [ ] every path after control-directory creation executes that cleanup boundary
  - [ ] starts exactly one serve child through an argument-vector `proc_open` command:
    - [ ] `PHP_BINARY`
    - [ ] repo-root `tools/http/serve`
    - [ ] `--target=<target>`
    - [ ] `--host=<bind-host>`
    - [ ] `--port=<port>`
    - [ ] `--quiet`
    - [ ] `--control-dir=<absolute-control-directory>`
  - [ ] MUST NOT invoke the serve child through Composer
  - [ ] serve-child stdin/stdout/stderr use the platform null sink
  - [ ] expected readiness bytes are constructed exactly as:
    - [ ] `CORETSIA_SERVE_READY target=<target> bind=<bind-host>:<port> url=http://<connect-host>:<port>\n`
  - [ ] readiness polling checks:
    - [ ] serve-wrapper process status
    - [ ] existence and type of the `ready` file
    - [ ] exact byte contents of the `ready` file
  - [ ] readiness-file polling uses a fixed `50`-millisecond interval
  - [ ] readiness-file reading has a fixed `512`-byte budget
  - [ ] child exit before valid readiness maps to `CORETSIA_SMOKE_SERVER_START_FAILED`
  - [ ] malformed, truncated, unexpected, oversized, or symlinked readiness state maps to `CORETSIA_SMOKE_SERVER_START_FAILED`
  - [ ] after exact readiness-file validation, one TCP probe must succeed against the canonical connect host and selected port
  - [ ] failure of the post-readiness TCP probe maps to `CORETSIA_SMOKE_SERVER_START_FAILED`
  - [ ] readiness timeout maps to `CORETSIA_SMOKE_SERVER_NOT_READY`
  - [ ] TCP reachability without the exact owned readiness file never establishes server ownership
  - [ ] executes exactly one checker child through an argument-vector `proc_open` command:
    - [ ] `PHP_BINARY`
    - [ ] repo-root `tools/http/smoke-http`
    - [ ] `--host=<connect-host>`
    - [ ] `--port=<port>`
    - [ ] `--timeout=<timeout>`
  - [ ] checker stdin/stdout/stderr use the exact platform-null descriptor contract
  - [ ] no checker output pipe is created
  - [ ] checker exit status `0` means smoke success
  - [ ] any non-zero checker exit status maps to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] checker `proc_open` failure maps to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] checker child has a parent-enforced total process deadline of exactly:
    - [ ] `(2 * timeout) + 2` seconds
  - [ ] the deadline uses monotonic `hrtime(true)`
  - [ ] checker process status is polled every `50` milliseconds
  - [ ] checker timeout:
    - [ ] calls `proc_terminate`
    - [ ] waits for at most one additional `timeout` interval
    - [ ] maps to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] checker termination uses the bounded process-termination contract
  - [ ] checker remaining active after hard termination still maps to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] a checker that remains active after bounded hard termination:
    - [ ] prevents successful smoke completion
    - [ ] does not prevent serve-wrapper cleanup from executing
    - [ ] maps the pending smoke result to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] checker termination failure never bypasses serve-wrapper cleanup
  - [ ] checker process handle is closed only after the checker is inactive
  - [ ] checker timeout does not bypass serve cleanup
  - [ ] serve-wrapper `proc_open` failure maps to `CORETSIA_SMOKE_SERVER_START_FAILED`
  - [ ] post-readiness serve-wrapper exit before checker completion maps to `CORETSIA_SMOKE_HTTP_FAILED`
  - [ ] retains the serve-process handle
  - [ ] immediately after successful `proc_open`, attaches the serve-process handle to the already-registered cleanup boundary
  - [ ] serve-wrapper `proc_open` failure removes the owned control directory before emitting `CORETSIA_SMOKE_SERVER_START_FAILED`
  - [ ] cleanup executes for every post-spawn path:
    - [ ] readiness success
    - [ ] readiness timeout
    - [ ] serve-wrapper early exit
    - [ ] checker startup failure
    - [ ] checker failure
    - [ ] successful checker completion
    - [ ] unexpected caught failure
  - [ ] cleanup publishes `stop` with exact bytes `CORETSIA_SERVE_STOP\n` through the shared atomic IPC file-publication contract
  - [ ] if `stop.tmp` or `stop` already exists, or atomic publication otherwise fails:
    - [ ] orderly stop is skipped
    - [ ] bounded process termination begins
    - [ ] the publication failure itself is not emitted separately
  - [ ] cleanup waits up to the selected timeout for orderly wrapper termination
  - [ ] if the wrapper remains active, cleanup applies the bounded process-termination contract
  - [ ] failure to make the wrapper inactive after hard termination maps to `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`
  - [ ] no process output pipes exist
  - [ ] cleanup closes every process handle only after the corresponding process is inactive
  - [ ] cleanup captures the final serve-wrapper exit status before closing its process handle
  - [ ] after an established readiness handshake, serve-wrapper exit status must be exactly `0`
  - [ ] a non-zero serve-wrapper exit during cleanup maps to `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`
  - [ ] cleanup removes `ready`, `ready.tmp`, `stop`, and `stop.tmp`
  - [ ] cleanup removes the owned control directory
  - [ ] port-release verification is performed only when the exact readiness handshake was previously completed
  - [ ] when readiness was never established:
    - [ ] a reachable port is treated as externally owned
    - [ ] cleanup does not wait for that port to close
    - [ ] cleanup does not classify that external listener as an orphan
  - [ ] when readiness was established:
    - [ ] cleanup polls the canonical connect host and selected port for at most the selected timeout
    - [ ] the port must become unreachable
  - [ ] cleanup never waits without a fixed deadline
  - [ ] server cleanup failure occurs when any of the following is true:
    - [ ] the serve wrapper remains active after bounded termination attempts
    - [ ] an owned, readiness-confirmed port remains reachable
    - [ ] after an established readiness handshake, the serve wrapper exits with a non-zero status
    - [ ] any owned `ready`, `ready.tmp`, `stop`, or `stop.tmp` path cannot be removed
    - [ ] the owned control directory cannot be removed
  - [ ] server cleanup failure maps to `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`
  - [ ] cleanup failure takes precedence over every pending success or failure result
  - [ ] when cleanup succeeds, the previously selected success or failure result is preserved
  - [ ] smoke writes its final failure payload only after cleanup has completed
  - [ ] successful smoke completion requires:
    - [ ] the exact readiness handshake was completed
    - [ ] checker exited with status `0`
    - [ ] serve wrapper is no longer active
    - [ ] serve wrapper exited with status `0`
    - [ ] the readiness-confirmed port is no longer reachable
    - [ ] every owned IPC file and the control directory were removed
  - [ ] silent on success
  - [ ] deterministic failure codes:
    - [ ] `CORETSIA_SMOKE_INVALID_ARGUMENT`
    - [ ] `CORETSIA_SMOKE_TARGET_NOT_HTTP`
    - [ ] `CORETSIA_SMOKE_TARGET_INVALID`
    - [ ] `CORETSIA_SMOKE_SERVER_START_FAILED`
    - [ ] `CORETSIA_SMOKE_SERVER_NOT_READY`
    - [ ] `CORETSIA_SMOKE_HTTP_FAILED`
    - [ ] `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`
    - [ ] `CORETSIA_SMOKE_INTERNAL_FAILURE`
  - [ ] failure output is exactly one JSON line on stderr:
    - [ ] `{"schema":1,"code":"<CODE>"}\n`
  - [ ] child stdout/stderr and child failure payloads are not forwarded
  - [ ] no profiles, env overrides, cURL, database checks, CLI checks, JUnit, GitHub annotations, colors, progress output, or dynamic logs remain

- [ ] `tools/http/smoke-http`
  - [ ] pure-PHP checker only
  - [ ] MUST NOT start, stop, or manage a server
  - [ ] MUST NOT call `proc_open`
  - [ ] MUST NOT use ext-curl, cURL, HTTP clients, or external executables
  - [ ] CWD-independent repo-root discovery through `__DIR__`
  - [ ] loads `skeleton/bootstrap/HttpFrontController.php`
  - [ ] reads only `HttpFrontController::BOOT_NOT_READY_BODY`
  - [ ] MUST NOT call `HttpFrontController::run()`
  - [ ] accepts exactly:
    - [ ] `--host=<host>`
    - [ ] `--port=<port>`
    - [ ] `--timeout=<seconds>`
  - [ ] defaults:
    - [ ] host `127.0.0.1`
    - [ ] port `8080`
    - [ ] timeout `5`
  - [ ] request path is fixed to `/`
  - [ ] `--url`, `--path`, query, fragment, user-info, scheme selection, and arbitrary endpoint selection are unsupported
  - [ ] opens connection through `stream_socket_client`
  - [ ] connection establishment has one total `<timeout>`-second deadline
  - [ ] connection refusal, DNS-free connect failure, or connect timeout maps to `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
  - [ ] request write failure or incomplete request write maps to `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
  - [ ] after a successful request write, response reading has a new total `<timeout>`-second deadline
  - [ ] response-read timeout maps to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] socket warnings and platform diagnostics are never emitted
  - [ ] writes exact request:
    - [ ] `GET / HTTP/1.1`
    - [ ] deterministic `Host`
    - [ ] `Connection: close`
    - [ ] empty body
  - [ ] exact request bytes terminate with `\r\n\r\n`
  - [ ] `Host` is emitted exactly as `<connect-host>:<port>`
  - [ ] the original requested token `localhost` or `0.0.0.0` never appears in request bytes after canonicalization
  - [ ] no additional request headers are emitted
  - [ ] reads response with a fixed total response budget of `65536` bytes
  - [ ] status line plus complete header section, including terminating `\r\n\r\n`, has a separate maximum of `16384` bytes
  - [ ] exceeding either the header-section budget or total response budget maps to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] EOF before a complete HTTP header section maps to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] the header section must end with exact `\r\n\r\n`
  - [ ] status line grammar is exactly:
    - [ ] `HTTP/1.1 SP <three ASCII decimal digits> [SP <reason phrase>] CRLF`
  - [ ] reason phrase:
    - [ ] may be empty
    - [ ] otherwise contains only ASCII HTAB, SP, and visible bytes `0x21..0x7E`
  - [ ] status line contains no NUL, ESC, bare CR, bare LF, or another C0 control byte
  - [ ] malformed version, separators, status digits, or reason phrase maps to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] malformed status or header lines map to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] obsolete folded header lines are rejected
  - [ ] each header line grammar is exactly:
    - [ ] `<field-name>:<optional-field-value>\r\n`
  - [ ] `field-name` is immediately followed by `:`
  - [ ] whitespace before `:` is forbidden
  - [ ] optional leading and trailing field-value whitespace consists only of ASCII SP or HTAB
  - [ ] an empty field value is syntactically valid unless the specific required-header contract rejects it
  - [ ] a header line without `:` maps to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] header names must be non-empty ASCII HTTP token values
  - [ ] header values containing CR, LF, NUL, ESC, or another C0 control byte other than horizontal tab are rejected
  - [ ] required singleton headers occur exactly once:
    - [ ] `Content-Type`
    - [ ] `Cache-Control`
    - [ ] `Content-Length`
  - [ ] missing or duplicate required singleton headers map to `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
  - [ ] forbidden headers occur zero times:
    - [ ] `Set-Cookie`
    - [ ] `Location`
    - [ ] `X-Powered-By`
    - [ ] `Transfer-Encoding`
  - [ ] any forbidden-header occurrence maps to `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
  - [ ] `Content-Length`:
    - [ ] contains ASCII decimal digits only
    - [ ] contains no sign, whitespace, comma, decimal point, or exponent
    - [ ] is parsed without integer overflow
    - [ ] does not exceed `65536 - <status-and-header-section-byte-length>`
    - [ ] the subtraction is checked before use and cannot underflow
    - [ ] response reading never allocates a buffer based solely on the received `Content-Length`
  - [ ] body length must equal the parsed `Content-Length`
  - [ ] trailing bytes beyond `Content-Length` map to `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
  - [ ] parses status line and headers deterministically
  - [ ] header names are compared ASCII case-insensitively
  - [ ] header values are compared byte-exactly after removing only optional leading and trailing ASCII SP/HTAB
  - [ ] internal whitespace is preserved
  - [ ] repeated header fields are never comma-joined before validation
  - [ ] failure validation order is exact:
    1. connection establishment and request write
    2. total response byte budget and header-section framing
    3. status-line grammar and individual header-line grammar
    4. required singleton header cardinality and forbidden-header absence
    5. `Content-Length` syntax, overflow, and response-budget validation
    6. body framing against the parsed `Content-Length`
    7. expected status code
    8. required header values
    9. expected body bytes
  - [ ] the first failed stage determines the only emitted failure code
  - [ ] exact mappings:
    - [ ] stage 1 → `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
    - [ ] stages 2, 3, 5, or 6 → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] stage 4 or 8 → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] stage 7 → `CORETSIA_SMOKE_HTTP_STATUS_MISMATCH`
    - [ ] stage 9 → `CORETSIA_SMOKE_HTTP_BODY_MISMATCH`
  - [ ] asserts:
    - [ ] status exactly `503`
    - [ ] `Content-Type` exactly `application/json; charset=utf-8`
    - [ ] `Cache-Control` exactly `no-store`
    - [ ] `Content-Length` value is exactly the canonical unsigned base-10 ASCII representation of `strlen(BOOT_NOT_READY_BODY)`
    - [ ] the expected `Content-Length` contains no leading zero
    - [ ] numeric equality with a non-canonical representation is insufficient
    - [ ] no `Set-Cookie`
    - [ ] no `Location`
    - [ ] no `X-Powered-By`
    - [ ] no `Transfer-Encoding`
    - [ ] body equals `BOOT_NOT_READY_BODY` byte-for-byte
    - [ ] body ends with exactly one LF
  - [ ] does not accept URL, path, query, arbitrary expected status, regex, headers, body, method, request headers, or payload file
  - [ ] silent on success
  - [ ] deterministic failure codes:
    - [ ] `CORETSIA_SMOKE_HTTP_INVALID_ARGUMENT`
    - [ ] `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
    - [ ] `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] `CORETSIA_SMOKE_HTTP_STATUS_MISMATCH`
    - [ ] `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] `CORETSIA_SMOKE_HTTP_BODY_MISMATCH`
    - [ ] `CORETSIA_SMOKE_HTTP_INTERNAL_FAILURE`
  - [ ] failure output is one JSON line on stderr:
    - [ ] `{"schema":1,"code":"<CODE>"}\n`
  - [ ] failure output contains no URL, host, port, response body, response header value, path, exception message, or socket diagnostic

#### Modifies

- [ ] repo-root `composer.json` — add scripts:
  - [ ] `serve` → `@php tools/http/serve`
  - [ ] `smoke:http` → `@php tools/http/smoke http`
  - [ ] script arguments remain pass-through after Composer `--`

- [ ] `docs/architecture/PACKAGING.md`
  - [ ] register stable HTTP entrypoints:
    - [ ] `packages/applications/skeleton/apps/web/public/index.php`
    - [ ] `packages/applications/skeleton/apps/api/public/index.php`
  - [ ] document explicit front-controller target ownership
  - [ ] document shared non-public `HttpFrontController` bootstrap seam
  - [ ] document that console and worker use non-HTTP host entrypoints owned by their respective epics
  - [ ] document that HTTP front-controller paths remain stable when Phase 3 replaces the temporary fallback

### Verification (TEST EVIDENCE) (MUST)

- [ ] `composer serve -- --target=web`
  - [ ] selects `packages/applications/skeleton/apps/web/public`
  - [ ] serves the web front controller
  - [ ] returns deterministic 503 fallback

- [ ] `composer serve -- --target=api`
  - [ ] selects `packages/applications/skeleton/apps/api/public`
  - [ ] serves the API front controller
  - [ ] returns the same deterministic 503 fallback

- [ ] `composer smoke:http -- --target=web`
  - [ ] starts one server
  - [ ] verifies exact response
  - [ ] terminates the server
  - [ ] succeeds silently

- [ ] `composer smoke:http -- --target=api`
  - [ ] starts one server
  - [ ] verifies exact response
  - [ ] terminates the server
  - [ ] succeeds silently

- [ ] direct `tools/http/smoke-http` without a server:
  - [ ] fails with `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
  - [ ] starts no server

- [ ] occupied serve port:
  - [ ] fails with `CORETSIA_SERVE_PORT_UNAVAILABLE`
  - [ ] emits only exact deterministic JSON failure output

### Tests (MUST)

- Contract:
  - [ ] `tools/tests/Contract/RepoRootHttpSmokeComposerScriptsContractTest.php`
    - [ ] repo-root `composer.json` contains exact `serve` script:
      - [ ] `@php tools/http/serve`
    - [ ] repo-root `composer.json` contains exact `smoke:http` script:
      - [ ] `@php tools/http/smoke http`
    - [ ] no duplicate or alternative serve/smoke script entry exists
    - [ ] scripts contain no shell operator, platform-specific executable, env assignment, or CWD-dependent path

  - [ ] `tools/tests/Contract/HttpSmokeEphemeralIpcContractTest.php`
    - [ ] smoke/serve IPC does not rely on non-blocking `proc_open` pipes
    - [ ] no `stream_select()` call receives a process pipe
    - [ ] readiness uses only the atomic ready-file protocol
    - [ ] orderly stop uses only the atomic stop-file protocol
    - [ ] IPC directory and filenames match the exact contract
    - [ ] IPC paths are absent from public diagnostics
    - [ ] temporary IPC state is not classified as a generated artifact

  - [ ] `tools/tests/Contract/HttpFrontControllerFallbackContractTest.php`
    - [ ] class file can be required without producing output or headers
    - [ ] class is final
    - [ ] exact constant value is independently asserted as:
      - [ ] `{"schema":1,"code":"CORETSIA_HTTP_BOOT_NOT_READY","message":"boot not ready"}\n`
    - [ ] constant is ASCII-only
    - [ ] constant contains LF only
    - [ ] constant ends with exactly one LF
    - [ ] constant contains no target, timestamp, host, port, path, request, process, or exception data
    - [ ] `run('invalid')` throws exactly `LogicException('http-front-controller-target-invalid')`
    - [ ] invalid-target exception contains no rejected target value

  - [ ] `tools/tests/Contract/HttpSkeletonFrontControllersDeclareCanonicalTargetsTest.php`
    - [ ] web front controller calls shared bootstrap with exactly `web`
    - [ ] api front controller calls shared bootstrap with exactly `api`
    - [ ] both use CWD-independent paths
    - [ ] neither front controller reads superglobals
    - [ ] neither duplicates fallback response bytes
    - [ ] no HTTP front controller exists under console or worker skeleton apps

  - [ ] `tools/tests/Contract/SmokeHttpIsSocketCheckerOnlyTest.php`
    - [ ] rejects ext-curl and cURL symbols
    - [ ] rejects `proc_open`
    - [ ] rejects env reads
    - [ ] rejects server-start, URL, path, query, regex, preview, arbitrary-header, and expected-payload options
    - [ ] checker reads the canonical bootstrap body constant

- Integration:
  - [ ] `tools/tests/Integration/ServeSmokeAndSmokeHttpCliContractTest.php`
    - [ ] split option forms are rejected
    - [ ] duplicate options are rejected
    - [ ] unknown options are rejected
    - [ ] empty values are rejected
    - [ ] invalid host, port, and timeout values are rejected
    - [ ] `localhost` canonicalizes to `127.0.0.1`
    - [ ] `0.0.0.0` uses bind host `0.0.0.0` and connect host `127.0.0.1`
    - [ ] `console|worker` map to the exact target-not-HTTP codes
    - [ ] unknown targets map to the exact target-invalid codes
    - [ ] every failure exits with status `1`
    - [ ] every failure emits exactly one safe JSON line
    - [ ] the shared option grammar is verified independently for all three scripts
    - [ ] `serve` and `smoke` reject invalid target case variants
    - [ ] `smoke-http` rejects `--url` and `--path`
    - [ ] every script works from a non-repository current working directory
    - [ ] hostile or similarly named env values do not alter defaults or parsing
    - [ ] internal failure output contains only the script-local internal code

  - [ ] `tools/tests/Integration/ServeLifecycleControlTest.php`
    - [ ] serve emits readiness only after TCP reachability
    - [ ] exact atomic stop-file protocol terminates the wrapper and built-in server
    - [ ] orderly control shutdown exits with status `0`
    - [ ] orderly control shutdown emits no failure output
    - [ ] selected port is no longer reachable after successful shutdown
    - [ ] built-in-server stdin uses the platform null sink
    - [ ] canonical `ready` and valid `stop` files are regular files rather than symlinks
    - [ ] pre-existing reserved IPC files or symlinks cause `CORETSIA_SERVE_INVALID_ARGUMENT`
    - [ ] malformed or symlinked `stop` state causes `CORETSIA_SERVE_INTERNAL_FAILURE`
    - [ ] smoke-side readiness validation rejects any non-canonical ready-file state before TCP ownership confirmation
    - [ ] control-directory paths never appear in output
    - [ ] `serve` does not delete the caller-owned control directory
    - [ ] after orderly serve shutdown, the control directory contains no unexpected paths beyond the reserved IPC filenames
    - [ ] the test owner can remove all remaining reserved IPC files and the control directory
    - [ ] atomic `ready` file contains exactly one LF-terminated readiness line
    - [ ] atomic `ready` file uses canonical bind and connect hosts
    - [ ] direct serve without `--control-dir` emits the same readiness bytes to stdout
    - [ ] unexpected post-readiness child exit maps to `CORETSIA_SERVE_CHILD_EXITED`

  - [ ] `tools/tests/Integration/ServeIgnoresAmbientPhpConfigurationTest.php`
    - [ ] runs with a synthetic hostile `PHPRC`
    - [ ] runs with a synthetic hostile `PHP_INI_SCAN_DIR`
    - [ ] hostile INI attempts to configure:
      - [ ] `auto_prepend_file`
      - [ ] `auto_append_file`
      - [ ] `display_errors`
      - [ ] `expose_php`
    - [ ] runs with `PHP_CLI_SERVER_WORKERS=4`
    - [ ] response remains the exact canonical fallback
    - [ ] no prepend/append bytes appear
    - [ ] no `X-Powered-By` appears
    - [ ] `PHP_CLI_SERVER_WORKERS` does not change readiness, response, or shutdown behavior
    - [ ] orderly shutdown leaves no listener on the selected port
    - [ ] cleanup leaves no IPC file or control directory

  - [ ] `tools/tests/Integration/HttpStubSmokeTest.php`
    - [ ] matrix:
      - [ ] target `web`
      - [ ] target `api`
    - [ ] invokes `tools/http/smoke http --target=<target>`
    - [ ] verifies status, required headers, forbidden headers, content length, and exact body bytes
    - [ ] verifies silent success
    - [ ] verifies server process is terminated after the smoke run
    - [ ] verifies every owned IPC file and the smoke control directory are removed after the smoke run
    - [ ] every normal success and checker-failure path performs cleanup
    - [ ] final output is emitted only after cleanup
    - [ ] cleanup failure overrides an earlier success or failure result with `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`

  - [ ] `tools/tests/Integration/SmokeHttpDoesNotManageServerTest.php`
    - [ ] without a running server, `smoke-http` fails with `CORETSIA_SMOKE_HTTP_NOT_REACHABLE`
    - [ ] no server process is started
    - [ ] no log or temporary diagnostic file is created

  - [ ] `tools/tests/Integration/SmokeHttpFailureMappingTest.php`
    - [ ] uses a synthetic local socket responder with fixed response fixtures
    - [ ] malformed status line → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] incomplete header section → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] response over `65536` bytes → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] status/header section over `16384` bytes → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] `Content-Length` larger than the remaining total response budget → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] wrong status → `CORETSIA_SMOKE_HTTP_STATUS_MISMATCH`
    - [ ] missing or duplicate required header → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] forbidden header → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] malformed `Content-Length` → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] body-length mismatch → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] wrong body bytes → `CORETSIA_SMOKE_HTTP_BODY_MISMATCH`
    - [ ] every failure output contains no raw response data
    - [ ] missing `Content-Length` → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] duplicate `Content-Length` → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] syntactically malformed single `Content-Length` → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] numerically correct but non-canonical `Content-Length` representation → `CORETSIA_SMOKE_HTTP_HEADER_MISMATCH`
    - [ ] whitespace before a header colon → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] missing header colon → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`
    - [ ] header mismatch takes precedence over body comparison
    - [ ] status mismatch takes precedence over header-value and body mismatch
    - [ ] response framing failure takes precedence over every semantic mismatch
    - [ ] response-read timeout → `CORETSIA_SMOKE_HTTP_RESPONSE_INVALID`

  - [ ] `tools/tests/Integration/ServePortUnavailableDeterministicTest.php`
    - [ ] starts `tools/http/serve` on an already occupied port
    - [ ] asserts `CORETSIA_SERVE_PORT_UNAVAILABLE`
    - [ ] asserts exact one-line JSON failure output
    - [ ] asserts no child output, path, host, port, PID, command, or exception diagnostic is exposed

  - [ ] `tools/tests/Integration/SmokeOccupiedPortDoesNotEstablishReadinessTest.php`
    - [ ] starts an unrelated synthetic listener on the selected port
    - [ ] invokes `tools/http/smoke http`
    - [ ] external TCP reachability is not accepted as smoke readiness
    - [ ] smoke never invokes `smoke-http` against the unrelated listener
    - [ ] failure maps to `CORETSIA_SMOKE_SERVER_START_FAILED`
    - [ ] the unrelated listener remains active and is not treated as an owned orphan
    - [ ] cleanup does not wait for the unrelated listener to terminate

### DoD (MUST)

- [ ] Web and API have real stable public front-controller paths.
- [ ] Each front controller declares exactly one canonical target.
- [ ] No target is inferred from path, request, env, config, host, or command-line runtime data.
- [ ] `HttpFrontController` is outside public docroots.
- [ ] The temporary 503 response is owned by the shared HTTP bootstrap seam.
- [ ] `_boot_not_ready_payload.php` does not exist.
- [ ] Web and API return the same byte-exact fallback body.
- [ ] The fallback body is encoded statically and not generated at request time.
- [ ] Status is exactly 503.
- [ ] Required headers are deterministic.
- [ ] Cookies, redirects, `X-Powered-By`, request reflection, and dynamic diagnostics are absent.
- [ ] No superglobal is read by the Phase-2 fallback.
- [ ] `serve` and `smoke http` use canonical `--target`.
- [ ] `serve` accepts only HTTP targets `web|api`.
- [ ] `console|worker` are rejected deterministically and receive no HTTP entrypoints.
- [ ] `smoke` starts exactly one server and always terminates it.
- [ ] `smoke-http` is a checker only and never starts a server.
- [ ] No cURL or external HTTP client dependency remains.
- [ ] No env-controlled smoke behavior remains.
- [ ] No profiles, arbitrary expectations, dynamic logs, previews, colors, JUnit, or GitHub annotations remain.
- [ ] Success paths are silent except for the optional deterministic `serve` readiness line.
- [ ] Failure output is a fixed one-line JSON shape containing only schema and code.
- [ ] Port collision produces `CORETSIA_SERVE_PORT_UNAVAILABLE`.
- [ ] All scripts are CWD-independent.
- [ ] Child process commands use argument vectors without shell interpolation.
- [ ] A successful smoke run leaves no serve wrapper or PHP built-in server process.
- [ ] Cleanup has fixed deadlines and reports `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED` instead of waiting indefinitely.
- [ ] No default skeleton `config/http.php` is introduced.
- [ ] HTTP front controllers are classified as executable app entrypoints, not default HTTP configuration
- [ ] `tools/gates/no_skeleton_http_default_gate.php` remains unchanged
- [ ] No gate allowlist or front-controller exception is introduced
- [ ] Front-controller paths can remain unchanged when platform/http replaces the temporary fallback.
- [ ] `smoke`, `smoke-http`, and `serve` are created by this epic rather than treated as existing Coretsia files.
- [ ] Imported source code establishes no compatibility surface.
- [ ] CLI option grammar, host, port, timeout, duplicate-option, and exit-status behavior are deterministic.
- [ ] `serve` emits readiness only after the target port accepts connections.
- [ ] `smoke` suppresses all child diagnostics and emits only its own deterministic failure payload.
- [ ] Exact fallback bytes are independently protected by a contract test rather than verified only through the shared constant.
- [ ] HTTP response parsing has a fixed byte budget and rejects malformed or ambiguous framing.
- [ ] `smoke` accepts readiness only from the exact serve-wrapper readiness handshake plus TCP reachability.
- [ ] A pre-existing listener cannot be mistaken for the smoke-owned PHP server.
- [ ] Port-release verification is performed only for a readiness-confirmed owned server.
- [ ] Every post-spawn smoke path performs bounded cleanup before final output.
- [ ] Cleanup failure has deterministic precedence over every prior smoke result.
- [ ] Built-in-server stdin uses the platform null sink and serve orchestration control exists only through atomic filesystem IPC.
- [ ] Process descriptor mappings and platform null sinks are exact and cross-OS.
- [ ] Every CLI script has a safe internal-failure code and one top-level failure boundary.
- [ ] Socket connect, request-write, response-read, parsing, status, header, and body failures have exact precedence.
- [ ] Repo-root Composer serve and HTTP-smoke entrypoints are protected by a contract test.
- [ ] `docs/architecture/PACKAGING.md` documents the final target-aware front-controller and bootstrap ownership model.
- [ ] Cross-OS orchestration does not depend on non-blocking `proc_open` pipes or `stream_select()` over process descriptors.
- [ ] Smoke/serve readiness and stop control use private atomic ephemeral filesystem IPC.
- [ ] Every owned IPC file and directory is removed after success or failure.
- [ ] Built-in-server execution ignores ambient php.ini, scanned INI files, prepend/append files, and `PHP_CLI_SERVER_WORKERS`.
- [ ] Windows process creation explicitly bypasses `cmd.exe`.
- [ ] The checker child has a parent-enforced monotonic deadline.
- [ ] Required-header cardinality is validated before parsing and applying `Content-Length`.
- [ ] HTTP failure precedence is executable without circular or unavailable input dependencies.
- [ ] IPC cleanup is registered immediately after control-directory creation, before process startup.
- [ ] Atomic IPC publication uses exclusive temporary files, complete writes, flush, close, and same-directory rename.
- [ ] Serve, checker, and wrapper termination have bounded graceful and hard-termination phases.
- [ ] Successful smoke completion requires serve-wrapper exit status `0`.
- [ ] A non-zero wrapper exit during cleanup maps to `CORETSIA_SMOKE_SERVER_CLEANUP_FAILED`.
- [ ] Total HTTP response budget includes status line, headers, separator, and body.
- [ ] `Content-Length` cannot exceed the remaining total response budget.
- [ ] No stale stdin-pipe or readiness-output terminology remains after adoption of filesystem IPC.
- [ ] The shared atomic IPC publication contract is referenced rather than partially duplicated in smoke cleanup.
- [ ] Server cleanup failure triggers include process state, wrapper exit status, owned port state, and IPC removal.
- [ ] IPC directory deletion remains owned by `smoke`, not by `serve`.
- [ ] `tools/http/smoke` accepts only the exact layout `smoke http [options]`.
- [ ] HTTP `Host` uses the canonical connect host rather than the original requested host token.
- [ ] HTTP header-line colon and whitespace grammar is deterministic.
- [ ] Expected `Content-Length` uses one canonical decimal byte representation.
- [ ] All contract and integration tests pass.

---

### 2.60.0 CLI Performance Gate (MUST) [TOOLING]

---
type: tools
phase: 2
epic_id: "2.60.0"
owner_path: "tools/gates/"

goal: "Benchmark execution time of key CLI commands on a pinned benchmark runner and fail only there if performance degrades beyond threshold."
provides:
- "Deterministic performance benchmarking of CLI commands"
- "Baseline timings stored in SSoT"
- "CI gate that compares current timings against baseline"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/sensitive-data-redaction.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.50.0 — tooling baseline exists
  - 2.30.0 — tag-first Platform CLI exists

- Required deliverables:
  - `coretsia` CLI executable.

#### Compile-time deps

N/A

### Entry points / integration points (MUST)

- Composer:
  - `composer performance:gate` — runs performance benchmarks
- CI:
  - run only in a dedicated pinned benchmark job
  - MUST NOT gate generic shared-runner CI, because timings there are not deterministic

### Deliverables (MUST)

#### Creates

- [ ] `tools/config/performance.php` — tooling-local performance benchmark config:
  - [ ] list of commands to benchmark (e.g., `coretsia list`, `coretsia help`, `coretsia config:validate --target=console`)
  - [ ] threshold multiplier (e.g., 1.2 = 20% slower allowed)
  - [ ] baseline file path `tools/config/performance.baseline.json`
  - [ ] baseline MUST be tied to the pinned benchmark environment / runner class

- [ ] `tools/gates/performance_gate.php` — deterministic gate:
  - [ ] runs each command multiple times (e.g., 3) and takes median execution time
  - [ ] benchmark cases are declared as:
    - [ ] one canonical safe benchmark id
    - [ ] one fixed ordered token list used only for process execution
  - [ ] diagnostics identify a benchmark only by its canonical safe benchmark id
  - [ ] diagnostics MUST NOT reconstruct, join, quote, or print the raw command line
  - [ ] child stdout and stderr are captured and discarded
  - [ ] child stdout and stderr MUST NOT be inherited by the gate process
  - [ ] child stdout and stderr MUST NOT be copied into diagnostics
  - [ ] command execution MUST NOT use shell interpolation
  - [ ] benchmark child environment is an explicit bounded allowlist
  - [ ] inherited environment values MUST NOT be rendered or copied into diagnostics
  - [ ] compares against baseline (if exists) or creates baseline if not
  - [ ] if any command exceeds baseline * threshold, prints `CORETSIA_PERFORMANCE_DEGRADED` + details
  - [ ] uses `ConsoleOutput`
  - [ ] supports `--update-baseline` flag to update baseline after intentional improvements
  - [ ] MUST resolve the tools root deterministically from the executing gate file.
  - [ ] MUST load `tools/support/bootstrap.php` before scanning.
  - [ ] If bootstrap is missing or unreadable:
    - [ ] MUST attempt to load `tools/support/ConsoleOutput.php`
    - [ ] MUST print the gate scan-failed code using `ConsoleOutput::codeWithDiagnostics($code, [])`
    - [ ] MUST exit with code `1`
  - [ ] MUST use `Coretsia\Tools\Support\ConsoleOutput::codeWithDiagnostics()` for all non-empty diagnostics output.
  - [ ] MUST NOT use `echo`, `print`, `var_dump`, `print_r`, `printf`, direct `STDOUT`, or direct `STDERR` for diagnostics.
  - [ ] MUST load `tools/support/ErrorCodes.php` when available.
  - [ ] MUST resolve error code constants from `ErrorCodes` when defined.
  - [ ] MUST keep deterministic fallback string codes when `ErrorCodes` is unavailable.
  - [ ] MUST use two code classes when applicable:
    - [ ] violation/finding code
    - [ ] scan-failed/tooling-failed code
  - [ ] MUST suppress warnings/notices around filesystem probing where existing gates do so, to avoid output pollution.
  - [ ] MUST wrap scanning/parsing logic in `try/catch`.
  - [ ] On unexpected throwable:
    - [ ] MUST emit the scan-failed code through `ConsoleOutput::codeWithDiagnostics($code, [])`
    - [ ] MUST exit with code `1`
  - [ ] On pass:
    - [ ] MUST emit no output
    - [ ] MUST exit with code `0`
  - [ ] On violation/finding:
    - [ ] MUST emit only the deterministic violation/finding code and sorted diagnostics
    - [ ] MUST exit with code `1`
  - [ ] Diagnostics MUST be:
    - [ ] deduplicated
    - [ ] sorted by byte-order `strcmp`
    - [ ] stable across OS/filesystem order/locale
    - [ ] free of raw argv, reconstructed command lines, child stdout/stderr, env values, absolute paths, raw payloads, source snippets, secrets, tokens, credentials, stack traces, and exception messages.

- [ ] `tools/config/performance.baseline.json` — initial baseline (committed)

#### Modifies

- [ ] `composer.json` — add mirror scripts (delegates to framework):
  - [ ] `performance:gate` → `@composer --no-interaction --working-dir=framework run-script performance:gate --`

- [ ] `framework/composer.json` — add gate script
  - [ ] `performance:gate` → `@php tools/gates/performance_gate.php`
  - [ ] add to `gates`

- [ ] `.github/workflows/performance-benchmark.yml` — dedicated pinned-runner workflow/job for the performance gate

- [ ] `tools/support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_PERFORMANCE_DEGRADED`
  - [ ] `CORETSIA_PERFORMANCE_GATE_SCAN_FAILED`

- [ ] add command `performance:gate` in `docs/guides/commands.md`
- [ ] update command `composer gates` in `docs/guides/commands.md`

### Cross-cutting

#### Observability

- [ ] Gate output is tooling diagnostics, not runtime observability.
- [ ] Output contains only:
  - [ ] canonical benchmark id
  - [ ] baseline time
  - [ ] current time
  - [ ] threshold
- [ ] Output MUST NOT contain:
  - [ ] raw argv
  - [ ] a reconstructed command line
  - [ ] child stdout or stderr
  - [ ] environment values
  - [ ] paths
  - [ ] payloads
  - [ ] secrets or credentials

#### Errors

- [ ] Deterministic codes.

#### Security / Redaction

- [ ] Gate diagnostics are safe by construction.
- [ ] The gate does not depend on runtime `SensitiveDataRedactorInterface` or `platform/redaction`.
- [ ] Allowed diagnostic fields are limited to:
  - [ ] deterministic gate code
  - [ ] canonical benchmark id
  - [ ] baseline time
  - [ ] current time
  - [ ] threshold
- [ ] Forbidden diagnostic data:
  - [ ] raw argv
  - [ ] reconstructed command lines
  - [ ] child stdout or stderr
  - [ ] environment values
  - [ ] absolute paths
  - [ ] payloads
  - [ ] secrets
  - [ ] tokens
  - [ ] credentials
  - [ ] Throwable messages
  - [ ] stack traces

### Verification

- [ ] Integration test: mock slow command, run gate, assert failure.

### Tests

- [ ] `tools/tests/Integration/PerformanceGateTest.php`
  - [ ] executes a deterministic fake slow command
  - [ ] asserts the degradation code and canonical benchmark id
  - [ ] asserts stable diagnostic ordering
  - [ ] asserts the expected non-zero exit code

- [ ] `tools/tests/Integration/PerformanceGateDoesNotLeakChildProcessDataTest.php`
  - [ ] child command writes fake secrets, env-like values, paths, payloads, and argv-like text to stdout and stderr
  - [ ] gate captures and discards both streams
  - [ ] none of the fake values appears in gate diagnostics
  - [ ] raw command tokens are not joined or rendered
  - [ ] only code, canonical benchmark id, timings, and threshold are emitted

### DoD

- [ ] Gate implemented, baseline created, CI integrated.
- [ ] Diagnostics expose only deterministic code, canonical benchmark id, timings, and threshold.
- [ ] Diagnostics never expose raw argv, reconstructed command lines, child output, env values, absolute paths, payloads, or secrets.
- [ ] The gate remains safe by construction and requires no runtime redaction service.
