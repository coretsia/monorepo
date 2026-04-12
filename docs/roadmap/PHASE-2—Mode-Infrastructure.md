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

### 2.10.0 Mode presets: SSoT format + packaging enforcement gate (MUST) [DOC/TOOLING]

---
type: tools
phase: 2
epic_id: "2.10.0"
owner_path: "framework/tools/gates/"

goal: "Зацементувати формат mode preset файлів (SSoT) та примусово забезпечити пакування: framework ships presets, skeleton ships none by default."
provides:
- "SSoT: формат mode preset файлів як runtime policy inputs (НЕ skeleton defaults)"
- "SSoT: meaning modes (`micro|express|hybrid|enterprise`) + інваріанти для валідатора"
- "Packaging policy + proof: skeleton MUST NOT ship `skeleton/config/modes/*` by default"
- "Gate/CI enforcement: deterministic failure + stable diagnostics"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/modes.md"
- "docs/architecture/PACKAGING.md"
- "docs/ssot/config-roots.md"   # for subtree rule reference (already exists)
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required deliverables (exact paths):
  - `docs/ssot/modes.md` — exists; updated here
  - `docs/architecture/PACKAGING.md` — exists; updated here
  - `framework/tools/spikes/_support/ConsoleOutput.php` — canonical writer for tooling/gates (allowlisted)
  - `coretsia/devtools-internal-toolkit` installed for tools runtime (Path normalization; no duplication)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- none (tools + docs only)

Forbidden:
- runtime imports from `framework/packages/**` by path (`require/include` with `packages/.../src/...` literals)

### Entry points / integration points (MUST)

- CI / gates chain:
  - `framework/tools/gates/package_compliance_gate.php` MUST execute this gate (central chain)
- Scan root:
  - Repo-root anchored (MUST NOT rely on CWD)

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/gates/no_default_skeleton_modes_gate.php`
  - [ ] Output policy (MUST):
    - [ ] writer: `framework/tools/spikes/_support/ConsoleOutput.php` only (no echo/print/fwrite)
    - [ ] line1: deterministic CODE
    - [ ] line2+: **<root>-relative** paths (forward slashes), sorted `strcmp`
    - [ ] when CODE=`OK`: MUST output only the single line `OK`
  - [ ] Input (MUST):
    - [ ] accepts optional `--root=<path>` to override scan root (used by tests)
    - [ ] default scan root = repo-root (auto-detected)
  - [ ] Scan policy (MUST):
    - [ ] scan target is `<root>/skeleton/config/modes/`
    - [ ] violation = **any file** under `skeleton/config/modes/` (not only `*.php`)
  - [ ] Path normalization (MUST):
    - [ ] normalize to forward slashes
    - [ ] DO NOT emit absolute paths
    - [ ] MUST compute `<root>-relative` via the canonical `coretsia/devtools-internal-toolkit` Path normalizer
  - [ ] Deterministic CODE (single-choice):
    - [ ] `CORETSIA_PACKAGING_SKELETON_DEFAULT_MODES_PRESENT` when violations exist
    - [ ] `OK` when none
  - [ ] Process exit status (MUST):
    - [ ] exit(0) when CODE=`OK`
    - [ ] exit(1) when CODE=`CORETSIA_PACKAGING_SKELETON_DEFAULT_MODES_PRESENT`

- [ ] `framework/tools/tests/Unit/Gates/NoDefaultSkeletonModesGateTest.php`
  - [ ] MUST execute gate with `--root` pointing to fixture root (NOT the real repo root)
  - [ ] MUST assert:
    - [ ] stable CODE token
    - [ ] stable path listing order (`strcmp`)
    - [ ] forward-slash normalization
    - [ ] repo-root-relative output only (no drive letters, no leading `/`)

- [ ] `framework/tools/tests/Fixtures/SkeletonDefault/`
  - [ ] `framework/tools/tests/Fixtures/SkeletonDefault/README.md`
  - [ ] Fixture representing default skeleton (NO `skeleton/config/modes/` directory)

- [ ] `framework/tools/tests/Fixtures/SkeletonWithModes/`
  - [ ] `framework/tools/tests/Fixtures/SkeletonWithModes/README.md`
  - [ ] `framework/tools/tests/Fixtures/SkeletonWithModes/skeleton/config/modes/micro.php`
  - [ ] Negative fixture: any file under `skeleton/config/modes/` triggers failure

#### Modifies

- [ ] `docs/ssot/modes.md` (single-choice, validator-ready):
  - [ ] preset file schema (keys + required/optional + scalar/list/map typing)
  - [ ] meaning modes + invariants
  - [ ] explicit: presets are runtime inputs; skeleton override is user-owned; skeleton ships none by default
  - [ ] moduleId format `<layer>.<slug>` + allowed chars rule

- [ ] `docs/architecture/PACKAGING.md`:
  - [ ] add “Mode presets packaging” section:
    - [ ] framework ships presets under `framework/packages/core/kernel/resources/modes/*.php`
    - [ ] skeleton ships **no** `skeleton/config/modes/*` by default
    - [ ] user MAY add `skeleton/config/modes/*.php` as override (user-owned)

- [ ] `framework/tools/gates/package_compliance_gate.php`
  - [ ] include `no_default_skeleton_modes_gate.php` in deterministic chain order

### Tests (MUST)

- Unit:
  - [ ] `framework/tools/tests/Unit/Gates/NoDefaultSkeletonModesGateTest.php`

### DoD (MUST)

- [ ] Gate fails if any file exists under `skeleton/config/modes/` in default skeleton
- [ ] Gate output deterministic across OS/runs (ordering + normalization + no abs paths)
- [ ] `docs/ssot/modes.md` unambiguous + validator-ready
- [ ] PACKAGING doc states framework vs skeleton shipping rules
- [ ] Cutline impact: blocks Phase 2 cutline until packaging policy + enforcement are green

---

### 2.20.0 Kernel fixtures for mode presets (SHOULD) [IMPL]

---
type: package
phase: 2
epic_id: "2.20.0"
owner_path: "framework/packages/core/kernel/"

package_id: "core/kernel"
composer: "coretsia/core-kernel"
kind: runtime
module_id: "core.kernel"

goal: "Додати kernel-owned fixture trees для deterministic boot/e2e тестів режимів без потреби в skeleton overrides."
provides:
- "Fixture trees для Micro/Express/Hybrid/Enterprise (kernel-owned)"
- "Test-ready scaffolding to validate mode preset behavior deterministically"
- "No change to packaging policy: skeleton ships no default modes"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/modes.md"
- "docs/ssot/config-roots.md"   # subtree rule for fixture config files
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 2.10.0 — modes SSoT + packaging enforcement gate

- Required deliverables (exact paths):
  - `framework/packages/core/kernel/resources/modes/micro.php`
  - `framework/packages/core/kernel/resources/modes/express.php`
  - `framework/packages/core/kernel/resources/modes/hybrid.php`
  - `framework/packages/core/kernel/resources/modes/enterprise.php`
  - `docs/ssot/modes.md`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- none (fixtures only)

Forbidden:
- none

### Deliverables (MUST)

#### Creates

Kernel-owned fixture trees (tests-only; deterministic content; LF-only):

- [ ] `framework/packages/core/kernel/tests/Fixtures/_POLICY.md`
  - [ ] MUST state:
    - [ ] fixtures are tests-only
    - [ ] LF-only, final newline
    - [ ] no absolute paths, no machine-specific bytes
    - [ ] MUST NOT ship `config/modes/*` anywhere inside fixtures
    - [ ] config files follow subtree rule (no root wrapper)

- [ ] `framework/packages/core/kernel/tests/Fixtures/MicroApp/`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/MicroApp/README.md`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/MicroApp/config/modules.php`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/MicroApp/config/kernel.php` (optional minimal subtree)

- [ ] `framework/packages/core/kernel/tests/Fixtures/ExpressApp/`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/ExpressApp/README.md`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/ExpressApp/config/modules.php`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/ExpressApp/config/kernel.php` (optional minimal subtree)

- [ ] `framework/packages/core/kernel/tests/Fixtures/HybridApp/`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/HybridApp/README.md`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/HybridApp/config/modules.php`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/HybridApp/config/kernel.php` (optional minimal subtree)

- [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/README.md`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/modules.php`
  - [ ] `framework/packages/core/kernel/tests/Fixtures/EnterpriseApp/config/kernel.php` (optional minimal subtree)

#### Modifies

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] Optional lightweight test:
  - [ ] `framework/packages/core/kernel/tests/Unit/ModePresetResourcesExistAndReturnArrayTest.php`
  - [ ] MUST only assert file presence + `is_array(require ...)`

- [ ] `framework/packages/core/kernel/tests/Unit/ModeFixturesDoNotShipModeOverridesTest.php`
  - [ ] Assert: no `tests/Fixtures/**/config/modes/*` present

- [ ] `framework/packages/core/kernel/tests/Unit/ModeFixtureConfigFilesReturnArrayTest.php`
  - [ ] For each fixture app dir:
    - [ ] assert `config/modules.php` exists and `is_array(require ...)`
    - [ ] if `config/kernel.php` exists, assert `is_array(require ...)`
  - [ ] MUST NOT assert any machine-specific values; presence + type only

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/core/kernel/tests/Unit/ModePresetResourcesExistAndReturnArrayTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/ModeFixturesDoNotShipModeOverridesTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/ModeFixtureConfigFilesReturnArrayTest.php`

### DoD (MUST)

- [ ] Fixture trees exist (paths exact)
- [ ] Fixtures deterministic (LF-only, no machine-specific content)
- [ ] Fixtures do not include any `config/modes/*`
- [ ] Skeleton default `skeleton/config/modes/*` still forbidden (enforced by 2.10.0 gate)

---

### 2.25.0 Kernel ops façade for CLI (MUST) [IMPL]

---
type: package
phase: 2
epic_id: "2.25.0"
owner_path: "framework/packages/core/kernel/"

package_id: "core/kernel"
composer: "coretsia/core-kernel"
kind: runtime
module_id: "core.kernel"

goal: "Надати platform/cli стабільні kernel-owned операції (validate/debug/compile/hash/verify) без дублювання алгоритмів у CLI."
provides:
- "Kernel-owned Ops façade: high-level deterministic operations over existing kernel services"
- "Stable result DTOs (json-like) safe for platform rendering (no raw values/secrets/abs paths)"
- "Single source of truth for cache:verify/config compile flows"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []     # uses existing kernel artifacts; does not introduce new artifact schemas
adr: none
ssot_refs:
- "docs/ssot/cache-verify.md"
- "docs/ssot/artifacts.md"
- "docs/ssot/artifacts-and-fingerprint.md"
- "docs/ssot/config-and-env.md"
- "docs/ssot/modes.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Kernel artifacts pipeline exists and is wired:
  - `ConfigKernel` (Phase 1) available for validate/debug flows
  - Artifact builders/writer available for compile flow
  - `CacheVerifier` available for verify flow
  - `FingerprintCalculator` available for hash flow
- Mode truth is kernel-owned and callable:
  - `kernel.modes.*` defaults exist
  - `Coretsia\Contracts\Module\ModePresetLoaderInterface` is available and can load `mode`
- Compiled container runtime is present:
  - kernel services are resolvable from compiled container (artifact-only production boot policy preserved)

- Required contracts / ports (exact FQCNs) (MUST)
  - `Coretsia\Contracts\Module\ModePresetLoaderInterface`
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface`
  - `Coretsia\Contracts\Config\ConfigValidatorInterface`

- Cross-package deliverable (embedded into 2.25) — new ports introduced in `core/contracts`
  - `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
  - `Coretsia\Contracts\Kernel\Ops\OpsResult`
  - `Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException`

- Boundary note (single-choice):
  - this epic intentionally co-introduces the public contracts port required by the same kernel capability
  - the ONLY allowed cross-package deliverables outside `framework/packages/core/kernel/` in this epic are:
    - `framework/packages/core/contracts/src/Kernel/Ops/KernelOpsInterface.php`
    - `framework/packages/core/contracts/src/Kernel/Ops/OpsResult.php`
    - `framework/packages/core/contracts/src/Kernel/Ops/Exception/KernelOpsFailedException.php`
  - this epic MUST NOT introduce unrelated `core/contracts` surface beyond the Kernel Ops port

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/*`
- `integrations/*`

### Entry points / integration points (MUST)

- Public service for platform/cli:
  - resolved from compiled container; no stdout/stderr; deterministic exceptions/codes

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/Kernel/Ops/KernelOpsInterface.php`
  - [ ] Methods (single-choice; deterministic; no stdout/stderr):
    - [ ] `validateConfig(string $mode): OpsResult`
    - [ ] `debugConfig(string $mode): OpsResult`
    - [ ] `compileConfig(string $mode): OpsResult`
    - [ ] `hashConfig(string $mode): OpsResult`
    - [ ] `verifyCache(string $mode): OpsResult`

- [ ] `framework/packages/core/contracts/src/Kernel/Ops/OpsResult.php`
  - [ ] Immutable DTO / readonly:
    - [ ] `operation: string`
    - [ ] `mode: string`
    - [ ] `outcome: string` (`success|handled_error|fatal_error`)
    - [ ] `data: array` (json-like; no floats; maps normalized/sorted recursively; list order preserved)
  - [ ] MUST NOT include raw values; only safe tokens, hashes/len, relpaths

- [ ] `framework/packages/core/contracts/src/Kernel/Ops/Exception/KernelOpsFailedException.php`
  - [ ] Deterministic code-first; message safe (no secrets/abs paths)
  - [ ] Intended for catch/handling in `platform/*` without `Coretsia\Kernel\*` imports

- [ ] `framework/packages/core/kernel/src/Ops/KernelOpsFacade.php`
  - [ ] MUST `implements Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
  - [ ] MUST return `Coretsia\Contracts\Kernel\Ops\OpsResult` (contracts DTO; no kernel-local duplicate DTO)
  - [ ] MUST throw `Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException` (contracts exception; no kernel-local duplicate exception)
  - [ ] MUST delegate to existing kernel components (ConfigKernel, artifact builders/writer, CacheVerifier, FingerprintCalculator)
  - [ ] MUST NOT print; MUST NOT leak raw config/env values; MUST NOT leak absolute paths
  - [ ] Results MUST be json-like (no floats; no objects/resources)

#### Modifies

- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php`
  - [ ] register `KernelOpsFacade`
  - [ ] bind `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface::class` → `KernelOpsFacade::class` (explicit binding; no interface autowire)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/core/kernel/tests/Unit/KernelOpsFacadeReturnsJsonLikeResultsTest.php`
    - [ ] MUST assert deep “json-like” invariants for `OpsResult->data`:
      - [ ] allowed scalar types: null|bool|int|string
      - [ ] arrays only; no objects/resources
      - [ ] floats forbidden (hard-fail)
      - [ ] maps are recursively key-sorted (`strcmp`) by the producer (kernel), lists preserve order
  - [ ] `framework/packages/core/kernel/tests/Unit/KernelOpsFacadeDoesNotLeakAbsolutePathsTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/KernelOpsFacadeImplementsContractsPortTest.php` *(new; verifies interface + exception type path)*

### DoD (MUST)

- [ ] platform/cli can call ops without re-implementing kernel algorithms
- [ ] ops results are deterministic, json-like, safe for rendering
- [ ] no stdout/stderr, no secrets, no absolute paths

---

### 2.30.0 Platform CLI — Tag-first Command Catalog + Kernel ops façade (MUST) [IMPL]

---
type: package
phase: 2
epic_id: "2.30.0"
owner_path: "framework/packages/platform/cli/"

package_id: "platform/cli"
composer: "coretsia/platform-cli"
kind: runtime
module_id: "platform.cli"

goal: "`coretsia config:compile --mode=express` і `coretsia cache:verify --mode=express` працюють детерміновано, а `coretsia doctor` не витікає секретів і не друкує non-deterministic шум."
provides:
- "Command discovery SSoT: DI tag `cli.command` + deterministic tag order from Foundation TagRegistry"
- 'Kernel ops consumption: validate/debug/compile/hash/verify via contracts port `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface` (kernel provides implementation; no duplication in CLI)'
- "Mode-aware ops without CLI-owned mode state (`--mode` is explicit override input only)"
- "Safe output (deterministic JSON/table/plain) + redaction (no secrets/PII)"
- "Reserved names implemented (`help`, `list`) and enforced"

tags_introduced: []          # uses existing reserved tag(s)
config_roots_introduced: []  # `cli` root exists (owner platform/cli)
artifacts_introduced: []     # no CLI-owned artifacts in this epic
adr: "docs/adr/ADR-0030-cli-tag-first-command-catalog.md"
ssot_refs:
- "docs/ssot/tags.md"
- "docs/ssot/modes.md"
- "docs/ssot/observability.md"
- "docs/ssot/cache-verify.md"
- "docs/ssot/artifacts.md"
- "docs/ssot/artifacts-and-fingerprint.md"
- "docs/ssot/context-keys.md"
- "docs/ssot/context-store.md"
---

### Canonical mode ownership (single-choice) (MUST)

- Mode truth is kernel-owned runtime input:
  - `kernel.modes.default`
  - `kernel.modes.allowed`
- CLI MUST NOT define any `cli.mode.*`.

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 2.25.0 — Kernel ops façade exists **as a contracts port implementation**:
    - `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface` is bound in container to kernel implementation

- Required deliverables (exact paths):
  - `docs/ssot/tags.md` contains reserved tag `cli.command` (owner `platform/cli`)
  - `framework/packages/platform/cli/config/cli.php` (subtree file)
  - `framework/packages/platform/cli/config/rules.php`
  - kernel mode defaults/allowed exist under `kernel.modes.*`
  - kernel compiled container + TagRegistry ordering is cemented (Foundation)

- Required contracts / ports (exact FQCNs):
  - `Coretsia\Contracts\Cli\Input\InputInterface`
  - `Coretsia\Contracts\Cli\Output\OutputInterface`
  - `Coretsia\Contracts\Cli\Command\CommandInterface`
  - `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
  - `Coretsia\Contracts\Module\ModePresetLoaderInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface`
  - `Coretsia\Contracts\Config\ConfigValidatorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface` (only if any stateful CLI services are introduced)
- Foundation context API (NOT contracts):
  - `Coretsia\Foundation\Context\ContextStore`
  - `Coretsia\Foundation\Context\ContextKeys`
  - `Coretsia\Foundation\Observability\CorrelationIdProvider`

> **Policy (single-choice):** `platform/cli` MUST consume kernel operations ONLY via `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface` and MUST NOT reference any `Coretsia\Kernel\Ops\*` symbols. Other `Coretsia\Kernel\*` public APIs MAY be used only where required by this epic’s declared compile-time dependency on `core/kernel` (boot/UoW runtime orchestration), but MUST NOT duplicate kernel algorithms.

### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:
- `integrations/*`
- external console frameworks / parsers
- filesystem scanning for discovery (commands/workflows)

### Entry points / integration points (MUST)

- CLI entrypoint (packaged):
  - `framework/packages/platform/cli/bin/coretsia` (declared in composer.json `"bin"`)
- Monorepo canonical wrappers (Prelude compliance):
  - repo-root `coretsia` and `framework/bin/coretsia` MUST remain the canonical entrypoints
  - wrappers MAY delegate to `vendor/bin/coretsia` but MUST be CWD-independent

- Commands:
  - `coretsia doctor`
  - `coretsia list` (built-in)
  - `coretsia help` (built-in)
  - `coretsia debug:modules`
  - `coretsia config:validate`
  - `coretsia config:debug`
  - `coretsia config:compile`
  - `coretsia config:hash`
  - `coretsia cache:verify`

### Command discovery (tag-first, deterministic) (MUST)

- ONLY discovery mechanism: DI tag `cli.command`.
- CLI MUST NOT read any `cli.commands` registry list.
- Consumer MUST NOT re-sort or re-dedupe TagRegistry output.

### Deliverables (exact paths only) (MUST)

#### Creates

- [ ] `framework/packages/platform/cli/src/Provider/CliServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).

Entrypoint + application:
- [ ] `framework/packages/platform/cli/bin/coretsia`
  - [ ] PHP executable (`#!/usr/bin/env php`), reads `argv`, delegates to `CliApplication`.
  - [ ] MUST NOT do heavy boot itself; only bootstrap minimal kernel container and run dispatcher.
- [ ] `framework/packages/platform/cli/src/Application/CliApplication.php`
  - [ ] Owns top-level flow: parse argv → resolve command → run inside kernel UoW → render output → map exit codes deterministically.

Input:
- [ ] `framework/packages/platform/cli/src/Input/ArgvInput.php`
  - [ ] Concrete `InputInterface` implementation; deterministic parse rules (no locale-dependent behavior).
- [ ] `framework/packages/platform/cli/src/Input/ArgvInputParser.php`
  - [ ] Minimal parser (no external libs): `<command> [--key=val] [--flag] [args...]`; stable precedence rules.

Output:
- [ ] `framework/packages/platform/cli/src/Output/ConsoleOutput.php`
  - [ ] Concrete `OutputInterface` implementation; owns stream writing; commands MUST use this, not `echo`.
- [ ] `framework/packages/platform/cli/src/Output/FormatResolver.php`

Output (deterministic + redacted):
- [ ] `framework/packages/platform/cli/src/Output/OutputFormatter.php`
  - [ ] SHOULD be stateless transformer; if implemented as multi-step accumulator/buffer (`begin/add/flush`) → MUST be `kernel.stateful + kernel.reset`.
  - [ ] MUST consume canonical redaction services from top-level `framework/packages/platform/cli/src/Redaction/RedactionEngine.php` and `framework/packages/platform/cli/src/Redaction/RedactionPolicy.php`.
  - [ ] `framework/packages/platform/cli/src/Output/Redaction/*` MUST NOT exist in this package.
- [ ] `framework/packages/platform/cli/src/Output/Formatter/JsonFormatter.php` — stable schema `schema, meta, data`
  - [ ] Stateless formatter: produces deterministic JSON (stable key order, stable schema envelope, no runtime caches).
- [ ] `framework/packages/platform/cli/src/Output/Formatter/TableFormatter.php` — safe table output
  - [ ] Stateless table renderer: no cross-run width/column memory; compute from current payload only.
- [ ] `framework/packages/platform/cli/src/Output/Formatter/PlainFormatter.php` — safe plain output
  - [ ] Stateless plain renderer; no hidden global formatting state.

Output schema:
- [ ] `framework/packages/platform/cli/resources/schema/cli_output@1.json`
  - [ ] JSON Schema for rendered CLI payloads (no floats; no secrets; deterministic shape)
  - [ ] Used by `JsonOutputSchemaContractTest.php` as the single source of truth

Catalog:
- [ ] `framework/packages/platform/cli/src/Catalog/CommandCatalog.php` — builds deterministic catalog from `cli.command` tags
  - [ ] MUST be stateless *or* frozen immutable map; if you add lazy cache that can vary per-UoW (mode/format/interactive/overrides) → MUST become `kernel.stateful + kernel.reset` (+ `ResetInterface`).
- [ ] `framework/packages/platform/cli/src/Catalog/CommandTagSchema.php` — validates `cli.command` tag meta shape at runtime (dev-safe; throws deterministic error)
  - [ ] Stateless validator: `validate(meta): void`; no “last error” fields; unknown keys hard-fail deterministically.
- [ ] `framework/packages/platform/cli/src/Catalog/CommandOverrides.php` — applies `cli.commands.overrides` from config
  - [ ] Stateless overlay applier: input catalog+overrides → output catalog/view; MUST NOT introduce new commands/aliases.
- [ ] `framework/packages/platform/cli/src/Catalog/CommandDescriptor.php` — canonical descriptor DTO (name/summary/args/options/security/mode policy)
  - [ ] Stateless immutable DTO (readonly). No derived caches; safe to share.

Kernel ops adapter:
- [ ] `framework/packages/platform/cli/src/Kernel/CliKernelFacade.php`
  - [ ] MUST depend on `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface` (NOT `Coretsia\Kernel\Ops\KernelOpsFacade`)
  - [ ] MUST NOT implement hashing/verify algorithms locally
  - [ ] MUST surface only safe `OpsResult` data to presentation layer

Mode resolver:
- [ ] `framework/packages/platform/cli/src/Mode/ModeResolver.php`
  - [ ] validates via kernel-owned truth (prefer `ModePresetLoaderInterface->load($mode)`)

Runner + diagnostics:
- [ ] `framework/packages/platform/cli/src/Runner/CommandRunner.php`
  - [ ] Executes `CommandInterface::run()` inside kernel UoW wrapper, creates spans, catches and renders exceptions via `ErrorHandlerInterface`.
- [ ] `framework/packages/platform/cli/src/Diagnostics/ExceptionRenderer.php`
  - [ ] Stable, secret-free diagnostics payload generator (no absolute paths, no stack traces by default).

Redaction:
- [ ] `framework/packages/platform/cli/src/Redaction/RedactionEngine.php`
  - [ ] Stateless service:
    - [ ] `redact(mixed $value, array $patterns): mixed`
    - [ ] MUST NOT keep caches/state between calls
  - [ ] Policy (MUST):
    - [ ] strings: apply patterns, replace matches with `"<redacted>"` (or token), preserve determinism
    - [ ] arrays: recurse; maps keep key order as input; lists preserve order
    - [ ] objects/resources: forbidden → throw `RedactionViolationException`
    - [ ] floats: forbidden → throw `RedactionViolationException`

- [ ] `framework/packages/platform/cli/src/Redaction/RedactionPolicy.php`
  - [ ] Pure helpers for:
    - [ ] “json-like” checks (no floats, no objects/resources)
    - [ ] safe diagnostics shaping (hash/len only; no raw secrets)

Built-in commands:
- [ ] `framework/packages/platform/cli/src/Command/ListCommand.php`
  - [ ] Lists commands from `CommandCatalog` deterministically; honors `hidden`, supports groups.
  - [ ] MUST be reserved name `list` (so “reserved” is actually implemented).
- [ ] `framework/packages/platform/cli/src/Command/HelpCommand.php`
- [ ] `framework/packages/platform/cli/src/Command/DoctorCommand.php` — ultra-early checks (no kernel boot)
  - [ ] Stateless orchestrator; any per-run diagnostics collection MUST be local (if extracted into a service collector → that collector becomes resettable).
- [ ] `framework/packages/platform/cli/src/Command/DebugModulesCommand.php` — prints module plan/manifest summary
  - [ ] Stateless orchestrator; no memoization of module plan across runs.
- [ ] `framework/packages/platform/cli/src/Command/ConfigValidateCommand.php` — kernel validate only (mode aware)
  - [ ] Stateless; delegates to kernel; mode is explicit input only.
- [ ] `framework/packages/platform/cli/src/Command/ConfigDebugCommand.php` — explain trace + redacted value/summary (mode aware)
  - [ ] Stateless; no cached traces; redaction is per payload.
- [ ] `framework/packages/platform/cli/src/Command/ConfigCompileCommand.php` — delegates compile artifacts to kernel (mode aware)
  - [ ] Stateless; no CLI-owned artifact/cache state.
- [ ] `framework/packages/platform/cli/src/Command/ConfigHashCommand.php` — prints kernel fingerprint for selected mode (no new cache)
  - [ ] Stateless; no fingerprint caching.
- [ ] `framework/packages/platform/cli/src/Command/CacheVerifyCommand.php` — delegates verify to kernel (mode aware; uses kernel dirty reasons)
  - [ ] Stateless; reports kernel results; no local “last verify outcome”.

Tag owner constants:
- [ ] `framework/packages/platform/cli/src/Provider/Tags.php`
  - [ ] `CLI_COMMAND = 'cli.command'`

- [ ] `docs/adr/ADR-0030-cli-tag-first-command-catalog.md`
  - [ ] MUST capture:
    - [ ] tag-first discovery via `cli.command`
    - [ ] reserved built-in command names (`help`, `list`)
    - [ ] kernel ops consumption only through `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
    - [ ] deterministic output + redaction policy

Errors:
- [ ] `framework/packages/platform/cli/src/Exception/CliCommandFailedException.php` (`CORETSIA_CLI_COMMAND_FAILED`)
  - [ ] Stateless exception wrapper for command execution; MUST keep payload secret-free and deterministic.
- [ ] `framework/packages/platform/cli/src/Exception/InvalidCommandTagMetaException.php` (`CORETSIA_CLI_INVALID_COMMAND_META`)
  - [ ] Stateless schema/registry violation exception; error details limited to names/serviceIds (no secrets/paths).
- [ ] `framework/packages/platform/cli/src/Exception/RedactionViolationException.php` (`CORETSIA_CLI_REDACTION_VIOLATION`)
  - [ ] Stateless redaction policy violation exception; MUST NOT contain raw sensitive values (hash/len only).

#### Modifies

- [ ] `framework/packages/platform/cli/composer.json`
  - [ ] MUST declare `"bin": ["bin/coretsia"]`

- [ ] `framework/packages/platform/cli/src/Provider/CliServiceProvider.php`
  - [ ] MUST register `CliKernelFacade` with explicit dependency on `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface::class`
  - [ ] MUST NOT import/reference any `Coretsia\Kernel\Ops\*` symbols anywhere; kernel ops are consumed only via `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface`
  - [ ] MUST register app/runner/catalog/services
  - [ ] MUST tag all built-in commands with `Tags::CLI_COMMAND` + deterministic meta
  - [ ] MUST register `RedactionEngine`

- [ ] `framework/packages/platform/cli/config/cli.php`
  - [ ] MUST NOT contain `cli.mode.*`
  - [ ] MUST NOT contain `cli.commands` registry list
  - [ ] keys (baseline):
    - [ ] `cli.enabled` = true
    - [ ] `cli.commands.overrides` = []
    - [ ] `cli.output.format_default` = 'adaptive'
    - [ ] `cli.output.adaptive.mapping.interactive` = 'table'
    - [ ] `cli.output.adaptive.mapping.ci` = 'json'
    - [ ] `cli.output.adaptive.mapping.default` = 'plain'
    - [ ] `cli.redaction.enabled` = true
    - [ ] `cli.redaction.patterns` = ['/password/i','/secret/i','/token/i','/key/i']
    - [ ] `cli.uow.enabled` = true

- [ ] `framework/packages/platform/cli/config/rules.php`
  - [ ] MUST hard-fail deterministically if:
    - [ ] any `cli.mode.*` exists
    - [ ] `cli.commands` exists AND is a list (legacy registry list)
    - [ ] `cli.commands` exists AND contains any key other than `overrides`
  - [ ] MUST validate overrides shape:
    - [ ] `cli.commands.overrides` is a map keyed by `commandName` (same regex as tag schema)
    - [ ] allowlisted override keys: `summary|hidden|group`
    - [ ] unknown keys hard-fail
    - [ ] override for unknown command hard-fails

- [ ] `framework/packages/platform/cli/README.md`
  - [ ] MUST include: Observability / Errors / Security-Redaction / Mode usage / Determinism / Providing CLI commands

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0030-cli-tag-first-command-catalog.md`

### Cross-cutting (MUST)

#### Context & UoW

- [ ] Reads from ContextStore:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
  - [ ] `ContextKeys::CLI_OUTPUT_FORMAT`
- [ ] Writes to ContextStore (safe only):
  - [ ] `ContextKeys::CLI_OUTPUT_FORMAT`
- [ ] Reset discipline (only if any stateful CLI services are introduced):
  - [ ] all stateful services implement `ResetInterface`
  - [ ] all stateful services tagged `kernel.reset`
  - [ ] all stateful services marked `kernel.stateful`
- [ ] CLI MUST execute commands inside kernel UoW wrapper once command resolution enters runtime execution.
- [ ] Exception (single-choice): `doctor` MAY perform ultra-early pre-kernel diagnostics before UoW boot, but any step that touches container-resolved runtime services MUST switch to the normal kernel UoW pipeline.
- [ ] CLI MUST NOT enumerate `kernel.reset` directly

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `cli.command` (attrs: `operation`, `outcome`, `mode`, `format` MAY include safe tokens; avoid high-cardinality)
  - [ ] `cli.kernel.op` (attrs: `operation`, `mode`, `outcome`)   // validate/debug/compile/hash/verify
- [ ] Metrics (names follow `<domain>.<metric>_*`):
  - [ ] `cli.command_total` (labels: `operation`, `outcome`)
  - [ ] `cli.command_duration_ms` (labels: `operation`, `outcome`)
- [ ] Label normalization applied (if needed):
  - [ ] `kind/op/uow_type→operation`
- [ ] Logs:
  - [ ] command start/finish summary (no secrets)
  - [ ] kernel operation summary (no secrets; hash/len only)
> No `mode`/`format` as metric labels (not in allowlist). If needed, include as span attrs only.

### Security / Redaction (MUST)

- [ ] MUST NOT leak:
  - [ ] dotenv values, raw config values for redacted keys, tokens/Authorization/Cookie/session ids, raw SQL/payload
- [ ] Allowed diagnostics:
  - [ ] safe ids (`correlation_id`, `uow_id`) + `hash(value)` / `len(value)` + stable reason tokens

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/cli/tests/Unit/ModeResolverTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/CommandTagSchemaTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/CommandCatalogDeterminismTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/RedactionEngineTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/ArgvInputParserTest.php`

- Contract:
  - [ ] `framework/packages/platform/cli/tests/Contract/CommandsDoNotWriteToStdoutTest.php`
  - [ ] `framework/packages/platform/cli/tests/Contract/JsonOutputSchemaContractTest.php`
    - [ ] MUST load schema from `framework/packages/platform/cli/resources/schema/cli_output@1.json` (no inline schema duplication)

- Integration:
  - [ ] `framework/packages/platform/cli/tests/Integration/TaggedCommandDiscoveryTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/DoctorDoesNotLeakSecretsTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/CliRejectsCliModeKeysInConfigDeterministicallyTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/CliRejectsLegacyCommandRegistryDeterministicallyTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/CliRejectsInvalidCommandOverridesDeterministicallyTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/CoretsiaBinaryListCommandTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/CoretsiaBinaryHelpCommandTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/ReservedCommandNamesCollisionRejectedTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/ConfigCompileDelegatesToKernelOpsPortTest.php` *(rename from …FacadeTest)*
  - [ ] `framework/packages/platform/cli/tests/Integration/CacheVerifyDelegatesToKernelOpsPortTest.php` *(rename from …FacadeTest)*

### DoD (MUST)

- [ ] `cli.command` is the only discovery SSoT; consumer does not re-sort
- [ ] CLI delegates ops ONLY to `Coretsia\Contracts\Kernel\Ops\KernelOpsInterface` (no algorithm duplication in CLI)
- [ ] Mode truth is kernel-owned; no `cli.mode.*`
- [ ] Rules reject legacy registry keys deterministically
- [ ] Reserved built-in names `help` and `list` are both implemented and protected from tagged-service collisions
- [ ] Observability complies with allowlist (no forbidden labels)
- [ ] Redaction enabled by default; tests prove no leaks
- [ ] Determinism: rerun `config:compile` produces no diff in kernel artifacts

---

### 2.40.0 Platform CLI: Workflows + Advanced UX (SHOULD) [IMPL]

---
type: package
phase: 2
epic_id: "2.40.0"
owner_path: "framework/packages/platform/cli/"

package_id: "platform/cli"
composer: "coretsia/platform-cli"
kind: runtime
module_id: "platform.cli"

goal: "`coretsia workflow:run verify --mode=enterprise` запускає deterministic pipeline поверх існуючих команд і дає стабільний редактований output (CI-friendly)."
provides:
- "Workflows як lightweight orchestration поверх існуючих команд (без runtime filesystem scanning)"
- "Help + suggestions базуються на CommandCatalog (tag-first)"
- "Replay (policy-gated): redacted recording only, default disabled (FILE = artifact, SSoT-registered)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced:
- "cli_replay@1"

adr: none
ssot_refs:
- "docs/ssot/tags.md"
- "docs/ssot/modes.md"
- "docs/ssot/observability.md"
- "docs/ssot/artifacts.md"
- "docs/ssot/artifacts-and-fingerprint.md"
- "docs/ssot/cli-replay.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 2.30.0 — tag-first CommandCatalog + rules forbidding legacy registries

- Required deliverables:
  - `framework/packages/platform/cli/src/Catalog/CommandCatalog.php`
  - `framework/packages/platform/cli/config/cli.php`
  - `framework/packages/platform/cli/config/rules.php`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:
- `integrations/*`
- runtime filesystem scanning for workflow discovery
- reflective command discovery outside `CommandCatalog`

### Deliverables (MUST)

#### Creates

Workflows core (no FS scanning; config-only):
- [ ] `framework/packages/platform/cli/src/Workflow/WorkflowDefinition.php`
- [ ] `framework/packages/platform/cli/src/Workflow/WorkflowRunner.php`
- [ ] `framework/packages/platform/cli/src/Workflow/WorkflowResult.php`
- [ ] `framework/packages/platform/cli/src/Workflow/WorkflowSchema.php`
- [ ] `framework/packages/platform/cli/src/Workflow/WorkflowStep.php`
- [ ] `framework/packages/platform/cli/src/Workflow/WorkflowDefinitionLoader.php`
- [ ] `framework/packages/platform/cli/src/Command/WorkflowListCommand.php`
- [ ] `framework/packages/platform/cli/src/Command/WorkflowRunCommand.php`

UX:
- [ ] `framework/packages/platform/cli/src/UX/SmartSuggestor.php`

Replay (FILE storage introduces artifact; MUST be redacted-only):
- [ ] `framework/packages/platform/cli/src/UX/Replay/ReplayRecord.php`
- [ ] `framework/packages/platform/cli/src/UX/Replay/ReplaySchema.php`
- [ ] `framework/packages/platform/cli/src/UX/Replay/CommandRecorder.php`
  - [ ] MUST use `framework/packages/platform/cli/src/Redaction/RedactionEngine.php` for all stored payloads
  - [ ] MUST enforce “json-like” (no floats/objects/resources) before write; violations hard-fail deterministically
- [ ] `framework/packages/platform/cli/src/UX/Replay/FileReplayStorage.php`

Replay SSoT (artifact compliance):
- [ ] `docs/ssot/cli-replay.md`
  - [ ] defines payload schema for `cli_replay@1` (no secrets, no abs paths, no floats)
- [ ] `docs/ssot/artifacts.md` (register `cli_replay@1` owner `platform/cli`)

Errors:
- [ ] `framework/packages/platform/cli/src/Exception/WorkflowFailedException.php` (`CORETSIA_CLI_WORKFLOW_FAILED`)
- [ ] `framework/packages/platform/cli/src/Exception/WorkflowDefinitionInvalidException.php` (`CORETSIA_CLI_WORKFLOW_INVALID`)

#### Modifies

- [ ] `framework/packages/platform/cli/config/cli.php` — add keys + defaults:
  - [ ] `cli.workflows.enabled` = false
  - [ ] `cli.workflows.definitions` = []
  - [ ] `cli.ux.suggestions_enabled` = true
  - [ ] `cli.ux.replay.enabled` = false
  - [ ] `cli.ux.replay.path` = 'var/replay/cli'   # repo/app-relative; NO absolute defaults

- [ ] `framework/packages/platform/cli/config/rules.php`
  - [ ] validate workflows schema deterministically (unknown keys hard-fail)
  - [ ] forbid any scanning paths/templates/macros
  - [ ] validate replay config (path must be relative, no traversal `..`)

- [ ] `framework/packages/platform/cli/src/Provider/CliServiceProvider.php`
  - [ ] register workflow + UX services
  - [ ] tag Workflow commands + Help (if present) with `Tags::CLI_COMMAND`

- [ ] `framework/packages/platform/cli/README.md`
  - [ ] Workflows / UX / Replay artifact policy / Security-Redaction

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/cli-replay.md`
- [ ] `docs/ssot/artifacts.md` — add registry row:
  - [ ] `cli_replay@1` (owner `platform/cli`) — purpose + schema rules + deterministic bytes policy

### Cross-cutting (MUST)

- [ ] Workflows execute existing commands via CommandCatalog resolution (no reflection, no FS scanning)
- [ ] Workflows MUST execute steps via existing CLI runtime pipeline:
  - [ ] resolve command via `CommandCatalog`
  - [ ] execute via `Runner/CommandRunner` (so UoW/observability/error handling stays centralized)
  - [ ] MUST NOT call `CommandInterface::run()` directly from workflow code
- [ ] Replay file format MUST follow artifacts envelope rules (`_meta` + `payload`) and stable JSON bytes:
  - [ ] maps: recursive key sort `strcmp`
  - [ ] lists preserve order
  - [ ] LF-only + final newline
  - [ ] floats forbidden
  - [ ] NO secrets/PII/abs paths (store only hashes/len + safe tokens)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/cli/tests/Unit/SmartSuggestorTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/WorkflowRunnerTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/WorkflowSchemaTest.php`
  - [ ] `framework/packages/platform/cli/tests/Unit/ReplaySchemaTest.php`
- Contract:
  - [ ] `framework/packages/platform/cli/tests/Contract/ReplayArtifactDoesNotLeakTest.php`
  - [ ] `framework/packages/platform/cli/tests/Contract/ReplayArtifactDeterministicBytesTest.php`
- Integration:
  - [ ] `framework/packages/platform/cli/tests/Integration/WorkflowRunUsesCommandRunnerPipelineTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/WorkflowConfigRejectsScanningPatternsDeterministicallyTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/ReplayConfigRejectsTraversalPathDeterministicallyTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/WorkflowRunExecutesStepsTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/SuggestionsForTyposTest.php`
  - [ ] `framework/packages/platform/cli/tests/Integration/ReplayStoresRedactedOnlyTest.php`

### DoD (MUST)

- [ ] No filesystem scanning for workflow discovery
- [ ] Workflow/replay config rules deterministically reject scanning patterns and traversal paths
- [ ] Workflows respect mode and redaction policy
- [ ] Replay is redacted-only, default disabled, artifact SSoT-registered
- [ ] Replay artifact proof exists:
  - [ ] no secrets / no abs paths
  - [ ] rerun-no-diff stable bytes
- [ ] Tests pass (unit + integration)

---

### 2.50.0 Front Controller stub + deterministic smoke (MUST) [IMPL]

---
type: skeleton
phase: 2
epic_id: "2.50.0"
owner_path: "skeleton/apps/web/public/"

goal: "`composer serve` піднімає dev server і повертає контрольований deterministic 503 (“boot not ready”) до готовності HTTP runtime."
provides:
- "Live smoke path (`composer serve`) before full `platform/http` integration"
- "Policy: superglobals read only in front controller; immediately converted to local vars/args"
- "Stub response deterministic + redacted (no request reflection, no headers/cookies/body leaks)"
- "Pure-PHP smoke checker (no external curl dependency)"
- "Repo-root entrypoints (Prelude compliance); scripts CWD-independent"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/architecture/PACKAGING.md"  # entrypoints + skeleton policy reference
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Required deliverables:
  - `skeleton/apps/web/public/` exists
  - repo-root `composer.json` exists (canonical entrypoints live at repo root)

### Entry points / integration points (MUST)

- Repo-root composer scripts:
  - `composer serve`
  - `composer smoke:http`

### Deliverables (MUST)

#### Creates

- [ ] `skeleton/apps/web/public/_boot_not_ready_payload.php`
  - [ ] returns **string** with stable JSON bytes (single source):
    - [ ] exact body: `{"schema":1,"code":"CORETSIA_HTTP_BOOT_NOT_READY","message":"boot not ready"}\n`
    - [ ] MUST be ASCII-safe, LF-only, final newline
    - [ ] MUST NOT include timestamps/paths/request data

- [ ] `skeleton/apps/web/public/index.php`
  - [ ] MUST `require _boot_not_ready_payload.php` and echo returned bytes as-is (no encoding at runtime)
  - [ ] MAY set `Content-Length` computed from those bytes (deterministic)
  - [ ] MUST:
    - [ ] read superglobals only once → local vars
    - [ ] return HTTP 503
    - [ ] output JSON body deterministically
  - [ ] Body rule (single-choice for this stub):
    - [ ] MUST output bytes returned by `_boot_not_ready_payload.php` exactly as-is
    - [ ] MUST include final `\n` (already part of the payload bytes)

- [ ] `framework/bin/serve`
  - [ ] pure PHP script; CWD-independent repo-root discovery via `__DIR__`
  - [ ] starts PHP built-in server via `proc_open` (allowed here)
  - [ ] MUST accept `--host`, `--port`, `--docroot`
  - [ ] MUST fail deterministically if port is unavailable:
    - [ ] CODE: `CORETSIA_SERVE_PORT_UNAVAILABLE`
  - [ ] Process management (MUST):
    - [ ] MUST keep handle to the PHP built-in server process
    - [ ] MUST terminate child process on shutdown (including when parent is terminated by tests)
  - [ ] Output (MUST):
    - [ ] default: no stdout/stderr noise (redirect child stdout/stderr to null or a deterministic log sink)

- [ ] `framework/bin/smoke-http`
  - [ ] pure PHP smoke check via `stream_socket_client` / HTTP request text
  - [ ] MUST accept `--url` OR `--host/--port`
  - [ ] MUST assert:
    - [ ] status = 503
    - [ ] body JSON matches expected schema EXACTLY (no “contains” heuristics)
  - [ ] MUST be silent on success; deterministic CODE on failure
  - [ ] MUST accept `--expected-payload=<path>`:
    - [ ] file is a PHP script returning expected body bytes (string)
    - [ ] comparison is byte-exact (incl final newline)
  - [ ] default expected payload (if omitted):
    - [ ] `<docroot>/_boot_not_ready_payload.php`
  - [ ] default docroot resolution (single-choice):
    - [ ] if `--expected-payload` is omitted, default docroot MUST resolve to repo-root `skeleton/apps/web/public`
    - [ ] this resolution MUST be CWD-independent and based on the script location (`__DIR__`)

- [ ] `framework/tools/tests/Integration/HttpStubSmokeTest.php`
  - [ ] starts server with `framework/bin/serve` (fixed default port; overrideable)
  - [ ] runs `framework/bin/smoke-http`
  - [ ] asserts deterministic behavior and payload

- [ ] `framework/tools/tests/Integration/ServePortUnavailableDeterministicTest.php`
  - [ ] starts `framework/bin/serve` on an already-occupied port
  - [ ] asserts deterministic failure code `CORETSIA_SERVE_PORT_UNAVAILABLE`
  - [ ] asserts no noisy stdout/stderr diagnostics beyond the deterministic failure payload

#### Modifies

- [ ] repo-root `composer.json` — add scripts:
  - [ ] `serve` → `@php framework/bin/serve`
  - [ ] `smoke:http` → `@php framework/bin/smoke-http`

### Verification (TEST EVIDENCE) (MUST)

- [ ] `composer serve` + `composer smoke:http`:
  - [ ] status = 503
  - [ ] JSON body equals expected stable bytes (incl final newline)

- [ ] `framework/bin/serve` on occupied port:
  - [ ] fails with deterministic code `CORETSIA_SERVE_PORT_UNAVAILABLE`
  - [ ] remains quiet except for deterministic failure output

### Tests (MUST)

- Integration:
  - [ ] `framework/tools/tests/Integration/HttpStubSmokeTest.php`
  - [ ] `framework/tools/tests/Integration/ServePortUnavailableDeterministicTest.php`

### DoD (MUST)

- [ ] Entry points are repo-root composer scripts (Prelude compliance)
- [ ] Stub response never leaks secrets/headers/cookies/body/request reflection
- [ ] Smoke scripts deterministic and cross-OS friendly (no curl)
- [ ] No default skeleton `config/http.php` introduced
- [ ] Port collision produces deterministic failure code (not noisy logs)

---

### 2.60.0 Devtools CLI-spikes: migrate to tag-first `cli.command` (MUST) [IMPL]

---
type: package
phase: 2
epic_id: "2.60.0"
owner_path: "framework/packages/devtools/cli-spikes/"

package_id: "devtools/cli-spikes"
composer: "coretsia/devtools-cli-spikes"
kind: library

goal: "Зробити devtools commands сумісними з tag-first CLI (2.30.0) без legacy `cli.commands` registry list."
provides:
- "Tagged command services via `cli.command` (no config registry list)"
- "Preserves spikes boundary: commands only dispatch to `framework/tools/spikes/**`"
- "Removes dependence on legacy CLI discovery (`cli.commands` key)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/tags.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Reserved tag exists:
  - `cli.command` owner `platform/cli` in `docs/ssot/tags.md`

- Tag meta MUST be compatible with `framework/packages/platform/cli/src/Catalog/CommandTagSchema.php`:
  - required: `name`
  - optional allowlist: `summary|aliases|hidden|group`
  - unknown keys MUST NOT be used

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `core/kernel` (devtools may remain kernel-free; it only provides commands that dispatch to tools)
- `platform/*` imports by PHP namespace
- raw literal tag strings as the preferred runtime-code pattern when a package-local mirror constant can be used under the tag registry usage rule

### Deliverables (MUST)

#### Modifies

- [ ] `framework/packages/devtools/cli-spikes/config/cli.php`
  - [ ] MUST NOT add `cli.commands` list
  - [ ] MUST remain subtree file (no wrapper root)

- [ ] `framework/packages/devtools/cli-spikes/composer.json`
  - [ ] MUST preserve the dev-only/library packaging model from `0.140.0`
  - [ ] MAY expose provider metadata if required by existing package-compliance rules
  - [ ] MUST NOT convert this package into a new runtime package
  - [ ] MUST NOT introduce a runtime `module_id`
  - [ ] MUST NOT require a runtime module skeleton just to participate in CLI tag-based discovery

- [ ] `framework/packages/devtools/cli-spikes/src/Provider/CliSpikesServiceProvider.php` (or existing provider)
  - [ ] register each command as DI service
  - [ ] tag each service with package-local mirror constant `Tags::CLI_COMMAND` + deterministic meta:
    - [ ] `name` required, optional `summary|aliases|hidden|group`
    - [ ] MUST NOT write stdout/stderr

- [ ] Tests:
  - [ ] update any Phase0 integration/contract tests that asserted legacy `cli.commands` registry semantics
  - [ ] preserve existing Phase0 output/dispatch invariants after migration:
    - [ ] existing `CommandsDoNotWriteToStdoutTest` (or its canonical successor) MUST remain green
    - [ ] existing tools-only dispatch/bootstrap tests MUST remain green
    - [ ] migration to tag-first discovery MUST NOT weaken OutputInterface-only rendering policy

### Creates (if missing)

- [ ] `framework/packages/devtools/cli-spikes/src/Provider/Tags.php`
  - [ ] package-local mirror constant only:
    - [ ] `CLI_COMMAND = 'cli.command'`
  - [ ] MUST be package-internal only
  - [ ] MUST NOT be treated as public API
- [ ] `framework/packages/devtools/cli-spikes/src/Provider/CliSpikesServiceProvider.php` (only if not existing)
- [ ] `framework/packages/devtools/cli-spikes/tests/Integration/CommandsAreTaggedWithCliCommandTagTest.php`

### DoD (MUST)

- [ ] No usage of legacy `cli.commands` registry list
- [ ] Package remains dev-only library as in `0.140.0`; no runtime module skeleton is introduced here
- [ ] Commands discoverable via TagRegistry in platform/cli
- [ ] Existing Phase 0 output/dispatch invariants remain intact after discovery migration:
  - [ ] commands still dispatch only to tools/spikes
  - [ ] user-visible output still goes only through CLI `OutputInterface`
  - [ ] no bootstrap/runtime drift into production package semantics
