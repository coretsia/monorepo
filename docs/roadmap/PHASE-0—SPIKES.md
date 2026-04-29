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

## PHASE 0 — SPIKES (Prototype найризиковіших частин, Non-product doc)

### 0.10.0 Spikes boundary decision: tools-only vs devtools package (MUST) [DOC]

---
type: docs
phase: 0
epic_id: "0.10.0"
owner_path: "docs/roadmap/phase0/"

goal: "Lock a single canonical boundary for Phase 0 spikes: tools-only spikes, with a single devtools exception."
provides:
- "Single-choice boundary: spikes live under framework/tools/spikes/**"
- "Single-choice exception: determinism helpers live only in coretsia/internal-toolkit"
- "Clear forbidden runtime imports policy for spikes"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.20.0 — packaging strategy locked (layer/slug conventions exist)
  - PRELUDE.30.0 — framework/ and docs/ exist; repo has canonical tooling entrypoints

- Required deliverables (exact paths):
  - `docs/architecture/PACKAGING.md` — package identity law to reference in boundary doc

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (doc-only)

Forbidden:

- N/A

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [x] `docs/roadmap/phase0/00_3-spikes-boundary.md` — canonical boundary decision (single-choice) + MUST/MUST NOT rules

#### Modifies

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (doc-only)

### Tests (MUST)

N/A

### DoD (MUST)

- [x] The boundary doc MUST include:
  - [x] “Examples” section with 3–4 minimal valid/invalid examples (copy-pastable), including:
    - [x] One invalid example: moving a spike implementation into a runtime package under `framework/packages/**`
      (e.g. a spike class placed under `framework/packages/core/*` or `framework/packages/platform/*`)
      MUST be shown as a boundary violation, even if the CLI can dispatch it.
- [x] Deliverables complete (creates), paths exact
- [x] Boundary is **single-choice** (no “either/or”):
  - [x] Spikes MUST live under `framework/tools/spikes/**`
  - [x] Spike implementations MUST NOT live under `framework/packages/**` (`production/runtime` area).
  - [x] Spikes MUST NOT `require|include` any sources from `framework/packages/**/src/**` (no path-based imports).
  - [x] Spikes MAY depend on exactly one **Coretsia internal** tooling library:
    - [x] `coretsia/internal-toolkit` (composer dependency), and MUST access it via Composer autoload (namespace-based).
  - [x] Spikes MAY use third-party dev tooling dependencies (e.g., PHPUnit) via the tooling workspace,
    but MUST NOT depend on Coretsia runtime packages (`core/*`, `platform/*`, `integrations/*`) at compile-time.
- [x] Spikes MUST NOT import runtime code `core/*`, `platform/*`, `integrations/*`
- [x] Exception is explicit (single-choice; scoped):
  - [x] Canonical *determinism primitives* for tooling MUST live only in `coretsia/internal-toolkit`:
    - [x] slug casing helpers (`Slug::*`)
    - [x] path normalization helpers (`Path::*`)
    - [x] stable JSON encoding helpers (`Json::*`)
  - [x] Clarification (cemented): this exception is CLOSED to only slug/path/json primitives.
    - [x] Deterministic file IO helpers (e.g. Phase 0 `DeterministicFile` for EOL/LF normalization and safe writes)
      are NOT part of the internal-toolkit primitive set and MAY live under `framework/tools/spikes/_support/**`
      as Phase 0 rails infrastructure, as long as they do not duplicate `Slug::*`, `Path::*`, or `Json::*`.
  - [x] Tooling MUST NOT duplicate these primitives anywhere under `framework/tools/**`.
  - [x] Non-primitive Phase 0 rails infrastructure is explicitly NOT part of this exception and MAY live under
    `framework/tools/spikes/_support/**` (e.g., ErrorCodes registry, deterministic exception carrier,
    CI rails runner, gates scripts), as long as it respects the “no runtime imports / no path-imports from packages/**/src/**” rules.
- [x] CLI exception is explicit (does NOT change spikes boundary):
  - [x] CLI runtime package `coretsia/cli` MAY exist as UX entrypoint.
  - [x] Phase 0 spike command IMPLEMENTATIONS MUST NOT live in any production runtime package.
    - [x] Spike commands MUST live in a devtools-only package (`coretsia/cli-spikes`, epic 0.140.0) or tools-only scripts.
  - [x] Production safety invariant:
    - [x] Installing only `coretsia/cli` MUST NOT include doctor/spike/deptrac/workspace command classes in the package.
  - [x] This exception does NOT allow spikes to move out of `framework/tools/spikes/**`.
  - [x] Spikes remain tools-only; CLI only dispatches/executes them and reads fixtures.
  - [x] This boundary decision does not require 0.120/0.130/0.140 to be implemented; it only defines the rule.
- [x] Out of scope:
  - [x] This epic MUST NOT introduce production runtime behavior (Phase 0 spikes/tooling only)
  - [x] This epic MUST NOT introduce plugin systems / extensibility frameworks
  - [x] This epic MUST NOT depend on `core/kernel` (unless explicitly stated in the epic)

---

### 0.20.0 Spikes sandbox scaffolding + CI rails (MUST) [TOOLING]

---
type: tools
phase: 0
epic_id: "0.20.0"
owner_path: "framework/tools/spikes/"

goal: "Provide a stable spikes sandbox + CI rails ensuring deterministic execution across Linux/Windows."
provides:
- "Single sandbox root for all spikes: framework/tools/spikes/**"
- "Determinism suite rails (Linux + Windows) with rerun-no-diff policy"
- "Shared error codes, fixtures, and safe output policy (no secrets)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.10.0 — spikes boundary is spec-locked
  - PRELUDE.30.0 — repo skeleton + CI entrypoints exist

- Required deliverables (exact paths):
  - `.github/workflows/ci.yml` — CI workflow exists to extend with spikes rails
  - `framework/composer.json` — tooling workspace root exists
  - `framework/tools/testing/phpunit.xml` — monorepo harness exists (spikes add their own config)

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- `core/*` (explicitly includes `core/contracts`)
- `platform/*`
- `integrations/*`
- `devtools/cli-spikes`
  - (Phase 0 spike implementations MUST NOT depend on devtools command packs; cli-spikes dispatches into tools-only spikes, never the other way around)
- `framework/packages/**/src/**`
  - (spikes sandbox MUST NOT import runtime code nor path-import tooling libs; internal-toolkit is used via Composer autoload only)

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- Composer:
  - `composer spike:test` — runs spikes suite
  - `composer spike:test:determinism` — runs determinism suite (Linux + Windows)

- CI:
  - job `spikes` (Linux): runs `composer spike:test`
  - job `determinism` (Linux+Windows): runs `composer spike:test:determinism`

- Artifacts:
  - reads: `framework/tools/spikes/fixtures/**`
  - writes: test temp dirs only (no repo writes except committed fixtures)

### Deliverables (MUST)

#### Creates

- [x] `framework/tools/spikes/README.md` — Phase 0 spikes rules + how to run locally/CI
- [x] `framework/tools/spikes/phpunit.spikes.xml` — dedicated PHPUnit config for spikes:
  - [x] MUST set `bootstrap` to `_support/bootstrap.php` (single-choice; config-file-relative, CWD-independent).
  - [x] Rationale: the same config MUST work when invoked either from repo root
    (`phpunit -c framework/tools/spikes/phpunit.spikes.xml`) or from `framework/` workspace
    (`phpunit -c tools/spikes/phpunit.spikes.xml`) without path rewrites.
  - [x] MUST NOT write any caches/artifacts into the git worktree during `spike:test` or `spike:test:determinism`.
  - [x] MUST NOT configure any report outputs that write files into the repo (junit/coverage/testdox/html/xml).
  - [x] Cache invariant (single-choice; cross-OS enforceable):
    - [x] PHPUnit result cache MUST be fully disabled.
    - [x] The canonical mechanism is single-choice:
      - [x] `phpunit` MUST be invoked with `--do-not-cache-result`.
    - [x] `phpunit.spikes.xml` MUST NOT declare any cache directory/file paths (no `.phpunit.result.cache`, no `.phpunit.cache`, no `cacheDirectory` pointing into the repo).
- [x] `framework/tools/spikes/fixtures/` — base fixtures namespace
  - [x] `framework/tools/spikes/fixtures/repo_min/.gitkeep` — inputs for fingerprint/config spikes
  - [x] `framework/tools/spikes/fixtures/payloads_min/.gitkeep` — inputs for payload spike
  - [x] `framework/tools/spikes/fixtures/deptrac_min/.gitkeep` — inputs for deptrac spike
  - [x] `framework/tools/spikes/fixtures/workspace_min/.gitkeep` — inputs for workspace spike
- [x] `framework/tools/spikes/fixtures/http_middleware_catalog.php` — SSoT fixture: canonical middleware catalog
- [x] `framework/tools/spikes/_support/ConsoleOutput.php` — stable console writer (no secrets):
  - [x] This file is the ONLY allowlisted stdout/stderr writer for Phase 0 tooling rails:
    - [x] under `framework/tools/spikes/**`
    - [x] and under `framework/tools/gates/**`
  - [x] Rationale: Phase 0 gates and determinism runner require diagnostics output, but spikes business logic MUST remain output-free.
  - [x] Reserved usage (single-choice):
    - [x] ONLY Phase 0 rails code MAY use it:
      - [x] gates under `framework/tools/gates/**` (when they need to emit diagnostics)
      - [x] `framework/tools/spikes/_support/DeterminismRunner.php`
      - [x] spikes bootstrap/runner diagnostics (not business logic)
  - [x] Spike business logic MUST NOT use `ConsoleOutput` (enforced separately by `SpikeModulesDoNotUseConsoleOutputTest`).
  - [x] MUST NOT print secrets/PII; diagnostics are code + normalized repo-relative paths only.
- [x] `framework/tools/spikes/_support/ErrorCodes.php` — deterministic string error codes shared across spikes
  - [x] MUST define initial registry content (cemented in this epic):
    - [x] `CORETSIA_DETERMINISM_GIT_REQUIRED`
    - [x] `CORETSIA_DETERMINISM_WORKTREE_DIRTY`
    - [x] `CORETSIA_DETERMINISM_RERUN_FAILED`
    - [x] `CORETSIA_SPIKES_BOOTSTRAP_AUTOLOAD_MISSING`
    - [x] `CORETSIA_SPIKES_FIXTURE_PATH_INVALID`
    - [x] `CORETSIA_REPO_TEXT_POLICY_VIOLATION`
    - [x] `CORETSIA_REPO_TEXT_POLICY_SCAN_FAILED`
  - [x] Spikes output policy error codes MUST be registered:
    - [x] `CORETSIA_SPIKES_OUTPUT_BYPASS_DETECTED`
    - [x] `CORETSIA_SPIKES_OUTPUT_GATE_SCAN_FAILED`
  - [x] MUST expose a single read API used by spikes/gates:
    - [x] `has(string $code): bool`
    - [x] `all(): array` (cemented stable order):
      - [x] MUST return `list<string>` sorted ascending by byte-order (`strcmp`)
- [x] `framework/tools/spikes/_support/DeterministicException.php` — canonical deterministic exception carrier (string code):
  - [x] MUST carry a deterministic string error code from `framework/tools/spikes/_support/ErrorCodes.php`
  - [x] MUST expose:
    - [x] `code(): string` (returns the deterministic string code)
  - [x] Construction rules (single-choice; enforceable):
    - [x] if the provided `$code` is not registered in `ErrorCodes`, the constructor MUST throw (developer error)
    - [x] exception message MUST be stable + safe:
      - [x] MUST NOT contain absolute paths
      - [x] MUST NOT contain dotenv values, tokens, passwords, raw payloads
      - [x] allowed content: short fixed reason + safe ids / normalized repo-relative paths provided by the caller
  - [x] Rationale: spikes/gates/runner share a single deterministic “code-first” failure semantic; no ad-hoc RuntimeException("CODE") patterns.
- [x] `framework/tools/gates/repo_text_normalization_gate.php`
- [x] `framework/tools/gates/spikes_output_gate.php` — deterministic spikes output bypass gate:
  - [x] Scan scope (single-choice; paths are scan-root-relative):
    - [x] include:
      - [x] `spikes/**/*.php`
      - [x] `gates/**/*.php`
    - [x] exclude (single-choice; matched against scan-root-relative paths):
      - [x] `spikes/tests/**`
      - [x] `spikes/fixtures/**`
      - [x] `gates/**/tests/**`
      - [x] `gates/**/fixtures/**`
  - [x] Runtime root vs scan root (single-choice; CWD-independent):
    - [x] Let `$toolsRootRuntime = realpath(__DIR__ . '/..')`
      - [x] Rationale: gate file lives under `<toolsRootRuntime>/gates/`, so `/..` is the canonical runtime tools root.
    - [x] Let `$scanRoot` be:
      - [x] default: `$toolsRootRuntime`
      - [x] override: `realpath(--path=<dir>)` (scan-only; MUST NOT affect bootstrap discovery/loading)
  - [x] Bootstrap loading (single-choice; required):
    - [x] Let `$bootstrap = $toolsRootRuntime . '/spikes/_support/bootstrap.php'`.
    - [x] The gate MUST `require_once $bootstrap` BEFORE scanning.
    - [x] If `$bootstrap` is missing/unreadable:
      - [x] MUST `require_once $toolsRootRuntime . '/spikes/_support/ConsoleOutput.php'` (local tools-only include).
      - [x] MUST emit `CORETSIA_SPIKES_OUTPUT_GATE_SCAN_FAILED` on line 1 via `ConsoleOutput` (no echo/print).
      - [x] MUST exit `1`.
      - [x] MUST NOT leak absolute paths.
    - [x] NOTE (cemented): if the bootstrap file exists but the bootstrap terminates the process
      (e.g. Composer autoload missing), the bootstrap’s own deterministic output/code is authoritative.
      The gate MUST NOT attempt to override it.
  - [x] Path normalization (single-choice; required):
    - [x] For every scanned file, the gate MUST compute a **scan-root-relative** normalized path:
      - [x] forward slashes only (`/`)
      - [x] no `.` segments
    - [x] Excludes and allowlists MUST be matched against this scan-root-relative path.
    - [x] Diagnostics MUST print this scan-root-relative path.
  - [x] Allowed output in Phase 0 tooling is single-choice:
    - [x] only `spikes/_support/ConsoleOutput.php` MAY contain direct stdout/stderr sinks and output constructs
    - [x] the gate MUST ignore bypass detections inside this allowlisted file
    - [x] any direct output constructs detected in any other scanned file MUST fail the gate
  - [x] The gate MUST detect output bypasses (minimum set; token-based; comments/strings ignored):
    - [x] language constructs: `echo`, `print`
    - [x] function calls (global or qualified):
      `var_dump`, `print_r`, `printf`, `vprintf`, `error_log`
    - [x] stdout/stderr stream writes (single-choice; token-based; statement-local):
      - [x] Any call to `fwrite(...)`, `fputs(...)`, or `fprintf(...)` MUST be treated as an output bypass
        ONLY IF the argument list contains (outside comments/strings) at least one of:
        - [x] `STDOUT`
        - [x] `STDERR`
    - [x] direct output sinks (single-choice; token-based; context-free):
      - [x] any occurrence of these exact string literal values MUST be treated as an output bypass
        (except inside the allowlisted `spikes/_support/ConsoleOutput.php`):
        - `php://stdout`, `php://stderr`, `php://output`
        - (optional) `php://fd/1`, `php://fd/2`
  - [x] Call detection rules (single-choice; enforceable):
    - [x] Matching MUST be token-based:
      - [x] detect `T_STRING` OR `T_NAME_QUALIFIED` OR `T_NAME_FULLY_QUALIFIED`
      - [x] take the **last segment** as the function name for qualified names (e.g. `\var_dump` → `var_dump`)
      - [x] MUST ignore if immediately preceded (ignoring whitespace) by `T_OBJECT_OPERATOR` or `T_DOUBLE_COLON`
      - [x] MUST ignore if immediately preceded (ignoring whitespace) by `T_FUNCTION`
      - [x] MUST require that the next non-whitespace token after the name is `(` to treat it as a call
    - [x] NOTE (cemented): `exit` / `die` are NOT treated as output bypass signals by this gate.
  - [x] Deterministic CODE mapping (single-choice):
    - [x] If at least one bypass is detected → MUST print `CORETSIA_SPIKES_OUTPUT_BYPASS_DETECTED` on line 1.
    - [x] If scanning fails due to an internal error/exception → MUST print `CORETSIA_SPIKES_OUTPUT_GATE_SCAN_FAILED` on line 1.
  - [x] Output MUST be stable and safe:
    - [x] Line 1: print deterministic error `CODE` only.
    - [x] Line 2+ (only when bypasses are detected): print stable minimal diagnostics lines:
      - [x] format: `<scan-root-relative-normalized-path>: output-bypass`
      - [x] ordering: sorted by `<scan-root-relative-normalized-path>` using byte-order comparison (`strcmp`), no locale.
  - [x] Output emission (single-choice):
    - [x] All output MUST be emitted via the runtime `ConsoleOutput` loaded from:
      - [x] `$toolsRootRuntime . '/spikes/_support/ConsoleOutput.php'`
    - [x] The gate MUST NOT use `echo`/`print`/`var_dump`/`print_r`/`printf`/direct stdout/stderr sinks in its own source.
  - [x] CLI args (single-choice):
    - [x] default scan root: `$toolsRootRuntime`
    - [x] `--path=<dir>` overrides scan root ONLY (`$scanRoot`), but bootstrap/output remain runtime-root based
  - [x] Exit code policy (single-choice):
    - [x] exit `0` on pass (print nothing)
    - [x] exit `1` on fail
- [x] `framework/tools/spikes/_support/FixtureRoot.php` — deterministic fixture root resolver
  - [x] API (single-choice; cemented):
    - [x] `rootDir(): string`
      - [x] returns an absolute canonical path to `framework/tools/spikes/fixtures` resolved from `__DIR__` (no CWD reliance).
    - [x] `path(string $relative): string`
      - [x] returns an absolute canonical path under `rootDir()` for a **fixtures-root-relative** path
        (e.g. `repo_min/skeleton/.env`).
      - [x] MUST reject unsafe/invalid `$relative` deterministically via `DeterministicException`:
        - [x] MUST reject any parent traversal (`..` segments) → code `CORETSIA_SPIKES_FIXTURE_PATH_INVALID`
        - [x] MUST reject absolute paths (single-choice minimum set):
          - [x] POSIX absolute (`/...`)
          - [x] Windows rooted (`\...`)
          - [x] Windows drive-letter (`(?i)\b[A-Z]:(\\|/)`)
          - [x] Windows UNC (`\\` prefix)
        - [x] MUST reject empty string and `.` segments as path parts.
      - [x] Failure mechanism (single-choice):
        - [x] MUST throw `DeterministicException` with code `CORETSIA_SPIKES_FIXTURE_PATH_INVALID`
        - [x] exception message MUST be exactly: `fixture-path-invalid`
  - [x] Safety (single-choice; enforceable):
    - [x] MUST NOT print/emit output.
    - [x] MUST NOT embed absolute paths in exception messages (message contains only fixed reason tokens).
- [x] `framework/tools/spikes/fingerprint/.gitkeep` — spike module folder (scaffold)
- [x] `framework/tools/spikes/payload/.gitkeep` — spike module folder (scaffold)
- [x] `framework/tools/spikes/deptrac/.gitkeep` — spike module folder (scaffold)
- [x] `framework/tools/spikes/config_merge/.gitkeep` — spike module folder (scaffold)
- [x] `framework/tools/spikes/workspace/.gitkeep` — spike module folder (scaffold)
- [x] `framework/tools/spikes/_support/bootstrap.php` — canonical tools rails bootstrap (spikes + gates):
  - [x] MUST load Composer autoload deterministically (ordered fallback; single-choice), and MUST be CWD-independent:
    - [x] Let `$bootstrapDir = __DIR__` (directory of this bootstrap file).
    - [x] Let `$frameworkRoot = realpath($bootstrapDir . '/../../..')` (resolves to `framework/`).
    - [x] Let `$repoRoot = realpath($frameworkRoot . '/..')`.
    - [x] Candidate autoload paths (single-choice order):
      1) [x] `$frameworkRoot . '/vendor/autoload.php'`   (equivalent of `framework/vendor/autoload.php`)
      2) [x] `$repoRoot . '/vendor/autoload.php'`       (equivalent of `vendor/autoload.php`)
    - [x] MUST NOT perform any directory probing beyond checking the two candidate paths above in order.
    - [x] MUST `require_once` the first readable candidate; if none exist:
      - [x] MUST fail deterministically WITHOUT leaking absolute paths.
      - [x] Failure mechanism (single-choice; enforceable):
        - [x] MUST `require_once $bootstrapDir . '/ConsoleOutput.php'` (local tools-path include only).
        - [x] MUST emit exactly:
          - [x] Line 1: `CORETSIA_SPIKES_BOOTSTRAP_AUTOLOAD_MISSING`
          - [x] Line 2: `autoload-missing`
        - [x] MUST exit `1`.
- [x] `framework/tools/spikes/_support/DeterminismRunner.php` — deterministic rerun-no-diff orchestrator (single canonical mechanism):
  - [x] MUST create a fresh runner temp root for each run and expose it to child processes:
    - [x] env var `CORETSIA_SPIKES_TMP` MUST be set for `composer spike:test` runs
    - [x] the temp root MUST be outside the repo and MUST be removed before each worktree cleanliness check
  - [x] MUST run the same Phase 0 rails entrypoint twice to avoid drift:
    - [x] run #1: `composer spike:test`
    - [x] run #2: `composer spike:test`
  - [x] MUST execute both runs with the same explicit env and working directory (no implicit cwd assumptions).
  - [x] MUST require `git` availability deterministically:
    - [x] if `git` executable is not available OR `git status --porcelain` cannot be executed → MUST fail with `CORETSIA_DETERMINISM_GIT_REQUIRED`
    - [x] this failure MUST be deterministic and MUST NOT include absolute paths in the message
  - [x] asserts clean worktree before run #1, between runs, and after run #2 (`git status --porcelain` MUST be empty)
  - [x] The runner MAY use a temp dir for its own intermediate files, but MUST remove it before each worktree check.
  - [x] any repo writes (tracked/untracked) → deterministic failure
  - [x] Deterministic failure code mapping (single-choice; cemented):
    - [x] If git is missing / cannot run `git status --porcelain` → `CORETSIA_DETERMINISM_GIT_REQUIRED`
    - [x] If worktree is dirty at start OR becomes dirty between runs OR is dirty after run #2 → `CORETSIA_DETERMINISM_WORKTREE_DIRTY`
    - [x] If run #1 exits non-zero OR run #2 exits non-zero (including PHPUnit failures) → `CORETSIA_DETERMINISM_RERUN_FAILED`
    - [x] If the runner itself throws/encounters an internal error while orchestrating runs → `CORETSIA_DETERMINISM_RERUN_FAILED`
  - [x] Failure output format (single-choice; safe):
    - [x] Line 1: deterministic `CODE` only (one of `CORETSIA_DETERMINISM_*`)
    - [x] Next lines (optional): stable short reason tokens only (no absolute paths, no env values, no captured output)
      - [x] examples: `worktree-dirty`, `run1-nonzero`, `run2-nonzero`, `git-required`
    - [x] Output MUST be emitted via `framework/tools/spikes/_support/ConsoleOutput.php` only.
  - [x] Exit code policy (single-choice):
    - [x] exit `0` on success
    - [x] exit `1` on any failure

- [x] `framework/tools/spikes/tests/ErrorCodesRegistryIsConsistentTest.php` — registry cement:
  - [x] all codes are unique
  - [x] all codes are UPPER_SNAKE (A–Z, 0–9, `_`)
  - [x] (optional but recommended) codes used by DeterminismRunner/Gates exist in registry
  - [x] MUST assert that DeterminismRunner codes exist in registry (hard fail if removed)
  - [x] MUST assert `ErrorCodes::all()` is sorted by byte-order (`strcmp`) and contains no duplicates
- [x] `framework/tools/spikes/tests/SpikesOutputGateDetectsBypassTest.php` — contract evidence:
  - [x] MUST cement CLI override mode:
    - [x] runs the gate in default mode (no `--path`) on the real repo scan root (`framework/tools`)
    - [x] runs the gate in `--path=<tempScanRoot>` mode against a synthetic scan root that contains:
      - [x] `<tempScanRoot>/spikes/**/*.php`
      - [x] `<tempScanRoot>/gates/**/*.php`
      - [x] (the same excludes apply under `<tempScanRoot>` as in default mode)
    - [x] NOTE (cemented): in `--path` mode the gate scans `<tempScanRoot>` BUT still loads bootstrap/ConsoleOutput
      from the real repo runtime tools root (derived from the gate file location). The synthetic tree is scan-only.
  - [x] given a temp php file containing `echo` → gate MUST fail with `CORETSIA_SPIKES_OUTPUT_BYPASS_DETECTED`
  - [x] given a temp php file containing `var_dump` → gate MUST fail with `CORETSIA_SPIKES_OUTPUT_BYPASS_DETECTED`
  - [x] given a temp php file writing via `ConsoleOutput` only → gate MUST pass
  - [x] given a temp php file under `gates/` containing `echo` → gate MUST fail with `CORETSIA_SPIKES_OUTPUT_BYPASS_DETECTED`
  - [x] given a temp php file containing `fwrite(STDOUT, "x");` → gate MUST fail with `CORETSIA_SPIKES_OUTPUT_BYPASS_DETECTED`
  - [x] given a temp php file containing `file_put_contents('php://stderr', "x");` → gate MUST fail with `CORETSIA_SPIKES_OUTPUT_BYPASS_DETECTED`
  - [x] given a temp php file containing `error_log("x");` → gate MUST fail with `CORETSIA_SPIKES_OUTPUT_BYPASS_DETECTED`
- [x] `framework/tools/spikes/tests/SpikeModulesDoNotUseConsoleOutputTest.php` — contract:
  - [x] token-based scan of `framework/tools/spikes/**` excluding:
    - [x] `framework/tools/spikes/_support/**`
    - [x] `framework/tools/spikes/tests/**`
    - [x] `framework/tools/spikes/fixtures/**`
  - [x] MUST fail if any spike module references `ConsoleOutput` (class name or FQCN) or writes to stdout/stderr
  - [x] Rationale: output authority is owned by CLI OutputInterface; spikes are pure computation
- [x] `framework/tools/spikes/tests/SpikeModulesDoNotCallExitOrDieTest.php` — contract:
  - [x] token-based scan of `framework/tools/spikes/**` excluding:
    - [x] `framework/tools/spikes/_support/**`
    - [x] `framework/tools/spikes/tests/**`
    - [x] `framework/tools/spikes/fixtures/**`
  - [x] MUST fail if any spike module uses process-termination constructs:
    - [x] `exit`
    - [x] `die`
  - [x] Rationale: spike business logic must be exception-driven (`DeterministicException`) and MUST NOT
    bypass rails/CLI semantics via early process termination.
- [x] `framework/tools/spikes/tests/DeterministicExceptionIsSafeTest.php` — contract evidence:
  - [x] creating `DeterministicException` with any registered code MUST be possible
  - [x] the exception message MUST NOT contain:
    - [x] any absolute path fragments (`C:\`, `\\server\share\`, `/home/`, `/Users/`)
      - [x] the exception message MUST NOT contain:
        - [x] Windows drive-letter absolute path fragments (single-choice minimum set):
          - [x] `(?i)\b[A-Z]:(\\|/)`
        - [x] Windows UNC absolute fragments:
          - [x] `\\server\share\`-like prefix (`\\` followed by a non-whitespace segment)
        - [x] POSIX home-like absolute fragments (cemented minimum set):
          - [x] `/home/`
          - [x] `/Users/`
    - [x] dotenv-like patterns (`KEY=VALUE`) for typical secret keys (`TOKEN`, `PASSWORD`, `AUTH`, `COOKIE`)
- [x] `framework/tools/spikes/tests/FixtureRootRejectsParentTraversalTest.php` — contract evidence:
  - [x] calling `FixtureRoot::path('../x')` MUST throw `DeterministicException` with code `CORETSIA_SPIKES_FIXTURE_PATH_INVALID`
  - [x] calling `FixtureRoot::path('/etc/passwd')` MUST throw `DeterministicException` with code `CORETSIA_SPIKES_FIXTURE_PATH_INVALID`
  - [x] calling `FixtureRoot::path('C:\\x')` MUST throw `DeterministicException` with code `CORETSIA_SPIKES_FIXTURE_PATH_INVALID`
  - [x] exception message MUST be exactly `fixture-path-invalid`
  - [x] exception message MUST NOT contain the provided input string

#### Modifies

- [x] `.github/workflows/ci.yml` — add `spikes` and `determinism` jobs (Linux + Windows)
  - [x] Windows rails MUST enforce deterministic Git checkout/runtime settings (single-choice):
    - [x] `git config --global core.autocrlf false`
    - [x] `git config --global core.symlinks true`
  - [x] Windows job MUST run spikes under a workspace that allows symlink creation.
    - [x] Rationale: some Phase 0 spikes hard-fail on symlink presence and the CI environment MUST be able to create symlinks
      so those invariants can be tested without skips (enforced by later epics).
- [x] `composer.json` — add root entrypoints:
  - [x] `spike:test`
    - [x] MUST invoke phpunit with `--do-not-cache-result` (single canonical flag)
  - [x] `spike:test:determinism`
- [x] `composer.json` — root scripts MUST delegate into `framework/` workspace (single-choice; CWD-independent):
  - [x] Delegation mechanism (single-choice; cemented):
    - [x] Root scripts MUST call Composer with explicit working directory:
      - [x] `@composer --working-dir=framework <script>`
    - [x] Rationale: guarantees stable behavior when invoked from repo root across Linux/Windows and avoids duplicating
      the “real” implementation in two composer.json files.
  - [x] Root scripts mapping (single-choice; examples are normative for the delegation shape):
    - [x] `spike:test`              → `@composer --working-dir=framework spike:test`
    - [x] `spike:test:determinism`  → `@composer --working-dir=framework spike:test:determinism`
    - [x] `spike:output:gate`       → `@composer --working-dir=framework spike:output:gate`
- [x] `framework/composer.json` — add workspace scripts used by root delegation:
  - [x] `spike:test`
  - [x] `spike:test:determinism`
  - [x] MUST add cemented `autoload-dev` PSR-4 mapping for spikes (single-choice):
    - [x] `Coretsia\\Tools\\Spikes\\` → `tools/spikes/`
  - [x] PSR-4 path/namespace case-safety (single-choice; cross-OS enforceable):
    - [x] For any class under `framework/tools/spikes/<dir>/...`, the namespace segment after
      `Coretsia\Tools\Spikes\` MUST match `<dir>` exactly as it exists on disk (case-sensitive).
    - [x] Canonical examples (cemented):
      - [x] `framework/tools/spikes/_support/ConsoleOutput.php`
        → `Coretsia\Tools\Spikes\_support\ConsoleOutput`
      - [x] `framework/tools/spikes/config_merge/DirectiveProcessor.php`
        → `Coretsia\Tools\Spikes\config_merge\DirectiveProcessor`
      - [x] `framework/tools/spikes/fingerprint/FingerprintCalculator.php`
        → `Coretsia\Tools\Spikes\fingerprint\FingerprintCalculator`
      - [x] `framework/tools/spikes/workspace/ComposerRepositoriesSync.php`
        → `Coretsia\Tools\Spikes\workspace\ComposerRepositoriesSync`
    - [x] Rationale: Linux filesystems are case-sensitive; this prevents autoload drift.
- [x] `composer.json` — wire determinism runner:
  - [x] `spike:test:determinism` MUST execute DeterminismRunner (not “just phpunit twice” ad-hoc)
- [x] `framework/composer.json` — workspace script mirrors root delegation:
  - [x] `spike:test:determinism` MUST execute DeterminismRunner (single canonical mechanism)
- [x] `composer.json` — add root script:
  - [x] `spike:output:gate` → `@composer --working-dir=framework spike:output:gate`
- [x] `framework/composer.json` — add workspace mirror script:
  - [x] `spike:output:gate` → `@php tools/gates/spikes_output_gate.php`
- [x] Rails integration (single-choice, non-optional):
  - [x] `spike:test` MUST execute `spike:output:gate` before running spikes PHPUnit suite.
  - [x] Any additional Phase 0 gates introduced by later epics MUST run before `spike:output:gate`.
  - [x] This epic defines only the local invariant (`spike:output:gate` → phpunit) and MUST NOT freeze the full chain.
  - [x] The canonical full Phase 0 chain is defined by epic 0.40.0 (as an enforced ordering contract).

#### Configuration (keys + defaults)

- [x] Files:
  - [x] `framework/tools/spikes/phpunit.spikes.xml`
  - [x] `composer.json`
  - [x] `framework/composer.json`

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

N/A (fixtures only; no runtime artifacts)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [x] Logs/output:
  - [x] stable output only
  - [x] MUST NOT print secrets/PII (dotenv values, tokens, passwords)

#### Errors

- [x] Spikes use shared `framework/tools/spikes/_support/ErrorCodes.php`
- [x] Error output policy: code string + short reason (no secrets)
- [x] Gate diagnostics output format is canonical (SSoT) for Phase 0 gates:
  - [x] Line 1: the deterministic error `CODE` only
  - [x] Next lines: stable, minimal diagnostics in canonical form:
    - [x] `<scan-root-relative-normalized-path>: <short-reason>`
    - [x] OR (only when no target path exists): `<short-reason>`
  - [x] Ordering MUST be stable:
    - [x] diagnostics are sorted by `<tools-root-relative-normalized-path>` using byte-order comparison (`strcmp`), no locale.
  - [x] Output MUST NOT include:
    - [x] absolute paths
    - [x] file contents / captured output
    - [x] secrets/PII
- [x] `--path=<dir>` scanning mode semantics (single-choice):
  - [x] `<dir>` overrides the **scan root** only.
  - [x] Reported paths MUST be normalized **scan-root-relative** (forward slashes).
  - [x] Rationale: the same gate output is stable across default and `--path` synthetic scan trees.
- [x] Determinism runner uses shared error codes from `framework/tools/spikes/_support/ErrorCodes.php`:
  - [x] `CORETSIA_DETERMINISM_GIT_REQUIRED`
  - [x] `CORETSIA_DETERMINISM_WORKTREE_DIRTY`
  - [x] `CORETSIA_DETERMINISM_RERUN_FAILED`
- [x] Exit code policy for Phase 0 rails (single-choice; enforceable):
  - [x] `0` — pass
  - [x] `1` — fail (any deterministic error code, including *_SCAN_FAILED)
  - [x] Rationale: the deterministic string `CODE` is the single source of failure semantics; exit codes stay binary.

#### Security / Redaction

- [x] MUST NOT leak: dotenv values, credentials, tokens/cookies/Authorization, raw payloads, raw SQL
- [x] Allowed: `hash(value)` / `len(value)` / safe normalized relative paths

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] CI job `determinism` proves cross-OS determinism:
  - [x] Linux + Windows runs `composer spike:test:determinism`
  - [x] re-run produces no file diffs
- [x] Output/Error policy is centralized and non-bypassable (enforced):
  - [x] `composer spike:output:gate` MUST fail on any direct output bypass in `framework/tools/spikes/**`
  - [x] Any bypass is treated as a test failure via `SpikesOutputGateDetectsBypassTest`
- [x] `composer spike:test:determinism` is proven non-declarative:
  - [x] it MUST fail if any repo writes occur during spike tests
  - [x] it MUST fail if the working tree is dirty at start or becomes dirty between runs

### Tests (MUST)

- Unit:
  - N/A (owned by individual spike epics)

- Integration:
  - [x] `framework/tools/spikes/**/tests/*` executed via `framework/tools/spikes/phpunit.spikes.xml`

- Gates/Arch (CI rails in this epic):
  - [x] `.github/workflows/ci.yml` jobs MUST exist and be green:
    - [x] `spikes` (Linux): runs `composer spike:test`
    - [x] `determinism` (Linux + Windows): runs `composer spike:test:determinism`
  - [x] Deptrac/arch enforcement is explicitly out of scope for 0.20.0

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] Determinism suite green on Linux + Windows
- [x] Spikes do not import runtime code (`core/*`, `platform/*`, `integrations/*`)
- [x] Docs: `framework/tools/spikes/README.md` explains how to run spikes
- [x] Out of scope:
  - [x] This epic MUST NOT introduce production runtime behavior (Phase 0 spikes/tooling only)
  - [x] This epic MUST NOT introduce plugin systems / extensibility frameworks
  - [x] This epic MUST NOT depend on `core/kernel` (unless explicitly stated in the epic)
- [x] Spikes MUST NOT import runtime code from `framework/packages/**/src/**` via any path-based mechanism.
  - [x] Spikes MUST NOT `require|require_once|include|include_once` any sources from `framework/packages/**` (including `devtools/internal-toolkit`).
  - [x] `coretsia/internal-toolkit` MAY be used only via Composer autoload (namespace-based), never via path imports.
- [x] Canonical bootstrap is enforced (phpunit.spikes.xml uses `_support/bootstrap.php`)
- [x] Determinism runner is the single canonical mechanism for rerun-no-diff (`spike:test:determinism`)
- [x] Error codes registry test exists and is green
- [x] Spike business logic does not write output:
  - [x] `SpikeModulesDoNotUseConsoleOutputTest` exists and is green

---

### 0.30.0 Spikes boundary enforcement gate (MUST) [TOOLING]

---
type: tools
phase: 0
epic_id: "0.30.0"
owner_path: "framework/tools/gates/"

goal: "Enforce Phase 0 spikes boundary by failing fast on forbidden imports/paths in framework/tools/spikes/**."
provides:
- "Deterministic boundary gate for spikes source files (no runtime imports)"
- "Deterministic error codes for boundary violations"
- "CI-local parity: same gate command used everywhere"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.10.0 — spikes boundary decision is spec-locked
  - 0.20.0 — spikes sandbox root exists (`framework/tools/spikes/**`)

- Required deliverables (exact paths):
  - `framework/tools/spikes/` — spikes root exists
  - `framework/tools/spikes/_support/ErrorCodes.php` — shared deterministic codes exist
  - `framework/tools/gates/` — gates root exists (or is created by this epic)
  - `framework/tools/spikes/_support/bootstrap.php` — tools rails bootstrap exists (used by gates)
  - `framework/tools/spikes/_support/ConsoleOutput.php` — canonical rails stdout/stderr writer exists (gates MUST use it)

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- `core/*`
- `platform/*`
- `integrations/*`
- `devtools/cli-spikes`
- Path-based imports from `framework/packages/**` are forbidden by policy and enforced by the gate (no exceptions)

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- Composer:
  - `composer spike:gate` — runs the spikes boundary gate (MUST be runnable locally)
- CI:
  - MUST be executed in Phase 0 rails (either standalone or as part of `spike:test`)

### Deliverables (MUST)

#### Creates

- [x] `framework/tools/gates/spikes_boundary_gate.php` — deterministic boundary gate:
  - [x] scans (single-choice; paths are scan-root-relative):
    - [x] include:
      - [x] `spikes/**/*.php`
      - [x] `gates/**/*.php`
    - [x] exclude (single-choice; matched against scan-root-relative paths):
      - [x] `spikes/tests/**`
      - [x] `spikes/fixtures/**`
      - [x] `gates/**/tests/**`
      - [x] `gates/**/fixtures/**`
  - [x] Normative rationale:
    - [x] `_support/**` is tooling rails code and MUST be included in boundary enforcement (it MUST NOT import runtime code either).
    - [x] `gates/**` are Phase 0 tooling rails and MUST follow the same “no runtime imports / no path-based imports” boundary.
    - [x] Only tests/fixtures are excluded to prevent false positives (strings/FQCNs as data).
  - [x] Fixtures may legitimately contain fully-qualified names as data (e.g. `\Coretsia\Foundation\X::class`) and MUST NOT be treated as boundary violations by this gate by construction (excluded scope).
  - [x] MUST fail on forbidden imports/usages of namespaces:
    - [x] `Coretsia\Contracts\*`
    - [x] `Coretsia\Foundation\*`
    - [x] `Coretsia\Kernel\*`
    - [x] `Coretsia\Core\*`
    - [x] `Coretsia\Platform\*`
    - [x] `Coretsia\Integrations\*`
    - [x] `Coretsia\Devtools\CliSpikes\*`
      - [x] (spikes MUST NOT depend on command packs; boundary is dispatch-only from cli-spikes into tools/spikes)
  - [x] The gate MUST detect forbidden namespace usage in these forms (not exhaustive, but minimum required):
    - [x] `use Coretsia\Kernel\...;` (and other forbidden roots)
    - [x] `new \Coretsia\Kernel\...`
    - [x] `\Coretsia\Kernel\...\:\:` (static calls)
  - [x] Forbidden namespace roots matching MUST be token-based and cemented (single-choice):
    - [x] Namespace violations are detected only from PHP tokens (never from raw text).
    - [x] The gate MUST treat ANY occurrence of a forbidden root as a violation when it appears in a qualified name token:
      - [x] `T_NAME_QUALIFIED` OR `T_NAME_FULLY_QUALIFIED` OR `T_NAME_RELATIVE`
      - [x] The token value MUST be normalized before matching:
        - [x] strip a leading `\` if present
      - [x] A violation is recorded if the normalized token value matches any forbidden root (single-choice):
        - [x] it is EXACTLY equal to the root (no trailing `\`), OR
        - [x] it starts with the root + `\`
      - [x] Forbidden roots (cemented):
        - [x] `Coretsia\Contracts`
        - [x] `Coretsia\Foundation`
        - [x] `Coretsia\Kernel`
        - [x] `Coretsia\Core`
        - [x] `Coretsia\Platform`
        - [x] `Coretsia\Integrations`
        - [x] `Coretsia\Devtools\CliSpikes`
    - [x] This closes all bypass forms by construction, including (non-exhaustive):
      - [x] `use ...`, `new ...`, `extends ...`, `implements ...`, `instanceof ...`, `catch (...)`
      - [x] parameter/property/return types, union/intersection types
      - [x] attributes `#[...]`
      - [x] `Foo\Bar::class` and any `T_DOUBLE_COLON` usage
    - [x] The gate MUST ignore `T_COMMENT`, `T_DOC_COMMENT`, and all string literal tokens for namespace matching.
  - [x] Stable sorting is required:
    - [x] the list of violations MUST be sorted by normalized **scan-root-relative** path using `strcmp` (byte-order).
  - [x] The gate MAY ignore string-based reflection usages (out of scope) but MUST be consistent.
  - [x] MUST fail on forbidden path usage (single-choice; token-based, statement-local):
    - [x] The gate MUST inspect every `require|require_once|include|include_once` statement in scanned scope.
    - [x] For each statement, the gate MUST analyze the argument expression tokens up to the statement terminator (`;`)
      and extract **all** string literal tokens (`T_CONSTANT_ENCAPSED_STRING`) that appear inside the argument expression
      (parentheses and concatenation allowed).
    - [x] The gate MUST build a *static literal candidate* by concatenating those extracted string-literal fragments
      in source order (single-choice). Non-literal parts (e.g. `__DIR__`, variables, function calls) are ignored for
      concatenation; the goal is deterministic detection of literal path fragments.
    - [x] A forbidden-path violation is recorded if (single-choice):
      - [x] the concatenated static literal candidate matches the forbidden-path definition below, OR
      - [x] **any** individual extracted string-literal fragment matches the same forbidden-path definition.
    - [x] Rationale: closes the common bypass `require __DIR__ . '/../packages/.../src/...'` while staying deterministic
      and strictly token-based.
  - [x] Runtime roots vs scan root (single-choice; required):
    - [x] The gate MUST separate:
      - [x] `$toolsRootRuntime` — where the gate code + bootstrap live (derived from the gate file location)
      - [x] `$scanRoot` — what filesystem subtree is scanned for violations
    - [x] CWD-independence (single-choice; required):
      - [x] Let `$toolsRootRuntime = realpath(__DIR__ . '/..')`.
    - [x] `--path=<dir>` semantics (single-choice; cemented):
      - [x] If `--path=<dir>` is provided:
        - [x] `$scanRoot = realpath(<dir>)`
      - [x] Else:
        - [x] `$scanRoot = $toolsRootRuntime`
      - [x] `--path` MUST NOT affect bootstrap discovery/loading.
      - [x] Rationale: `--path` is scan-only to support synthetic trees in tests.
    - [x] Bootstrap (single-choice; required):
      - [x] Let `$bootstrap = $toolsRootRuntime . '/spikes/_support/bootstrap.php'`.
      - [x] The gate MUST `require_once $bootstrap` BEFORE scanning.
      - [x] If `$bootstrap` is missing/unreadable:
        - [x] MUST `require_once $toolsRootRuntime . '/spikes/_support/ConsoleOutput.php'` (local tools-only include).
        - [x] MUST print `CORETSIA_SPIKES_BOUNDARY_SCAN_FAILED` on line 1 via `ConsoleOutput` (no echo/print).
        - [x] MUST exit `1`.
        - [x] MUST NOT leak absolute paths.
      - [x] NOTE (cemented): if the bootstrap file exists but terminates the process
        (e.g. Composer autoload missing), the bootstrap’s deterministic output/code is authoritative.
        The gate MUST NOT attempt to override it.
    - [x] Scan root (single-choice; required):
      - [x] The gate MUST scan under `$scanRoot`:
        - [x] `$scanRoot . '/spikes/**/*.php'`
        - [x] `$scanRoot . '/gates/**/*.php'`
      - [x] Excludes and all diagnostics paths are **scan-root-relative** (forward slashes).
  - [x] Output format + emission (single-choice; SSoT):
    - [x] The gate MUST follow the canonical Phase 0 gate output format:
      - [x] Line 1: the deterministic error `CODE` only
      - [x] Next lines: `<scan-root-relative-normalized-path>: <short-reason>` sorted by path using `strcmp`
    - [x] The gate MUST emit output via `framework/tools/spikes/_support/ConsoleOutput.php` only.
  - [x] Deterministic CODE selection (single-choice; cemented):
    - [x] If scanning fails due to an internal error/exception → print `CORETSIA_SPIKES_BOUNDARY_SCAN_FAILED` on line 1.
    - [x] Else if at least one forbidden namespace import/usage is detected → print `CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_IMPORT` on line 1.
    - [x] Else if at least one forbidden path include/require is detected → print `CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_PATH` on line 1.
    - [x] Else → pass (exit 0) and print nothing.
  - [x] Diagnostics emission semantics (single-choice; cemented):
    - [x] If CODE is printed (fail), the gate MUST print diagnostics lines for ALL detected violations
      (both forbidden-import and forbidden-path if both exist), sorted by
      `<scan-root-relative-normalized-path>` via `strcmp` (byte-order), no locale.
    - [x] Diagnostics lines MUST remain minimal reason tokens only:
      - [x] `forbidden-import:<root>`
      - [x] `forbidden-path`
  - [x] MUST print deterministic error code + minimal safe diagnostics (no secrets)
  - [x] Definition (single-choice; cemented): “reference/import of `framework/packages/**/src/**`” means:
    - [x] The gate MUST evaluate each extracted static-literal candidate and each individual string fragment via a
      normalized form (single-choice):
      - [x] Let `$raw` be the candidate or fragment (without surrounding quotes).
      - [x] Let `$norm = str_replace('\\', '/', $raw)` (only this normalization; no path resolution).
    - [x] A forbidden-path violation is recorded if (single-choice):
      - [x] `$norm` contains directory segments `packages/` AND `/src/` (in that order).
    - [x] Notes:
      - [x] The match MAY include `framework/` prefix, `../` segments, or any additional parts; only the `packages/` and `/src/`
        directory segments are semantically relevant.
      - [x] No exceptions: ANY include/require statement whose literal parts match the definition above MUST be treated
        as a boundary violation, including references into `framework/packages/devtools/internal-toolkit/**`.

- [x] `framework/tools/spikes/tests/SpikesBoundaryGateDetectsForbiddenImportsTest.php` — contract evidence:
  - [x] creates a temp tools root and asserts deterministic behavior via `--path=<tempToolsRoot>`:
    - [x] writes a PHP file under `<tempToolsRoot>/spikes/...` importing a forbidden namespace → gate MUST fail deterministically
    - [x] writes a PHP file under `<tempToolsRoot>/spikes/...` importing internal-toolkit (`\Coretsia\Devtools\InternalToolkit\...`) → gate MUST pass
    - [x] writes a PHP file under `<tempToolsRoot>/spikes/...` containing `instanceof \Coretsia\Foundation\X` (or `extends \Coretsia\Kernel\X`)
      → gate MUST fail with `CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_IMPORT`
    - [x] writes a PHP file under `<tempToolsRoot>/spikes/...` containing a forbidden type-hint
      (e.g. `function f(\Coretsia\Foundation\X $x): void {}`) → gate MUST fail with `CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_IMPORT`
    - [x] writes a PHP file under `<tempToolsRoot>/spikes/...` containing `use Coretsia\Foundation;`
      → gate MUST fail with `CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_IMPORT`
    - [x] writes a PHP file under `<tempToolsRoot>/spikes/...` containing `require 'framework/packages/' . 'core/x/src/y.php';`
      → gate MUST fail with `CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_PATH`
    - [x] writes a PHP file under `<tempToolsRoot>/spikes/...` containing `require 'framework/packages/devtools/internal-toolkit/src/Json.php';`
      → gate MUST fail with `CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_PATH`
    - [x] writes a PHP file under `<tempToolsRoot>/spikes/fixtures/...` containing `\Coretsia\Foundation\X::class`
      → gate MUST PASS (fixtures are excluded)
    - [x] writes a PHP file under `<tempToolsRoot>/spikes/...` containing: `require __DIR__ . '/../packages/core/x/src/y.php';`
      → gate MUST fail with `CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_PATH`
    - [x] writes a PHP file under `<tempToolsRoot>/spikes/...` containing: `require __DIR__ . '\\..\\packages\\core\\x\\src\\y.php';`
      → gate MUST fail with `CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_PATH`
    - [x] writes a PHP file under `<tempToolsRoot>/gates/...` importing a forbidden namespace
      → gate MUST fail with `CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_IMPORT`
    - [x] NOTE (cemented): `--path` overrides scan root only. Bootstrap/ConsoleOutput/ErrorCodes are loaded from the real
      repo runtime tools root (derived from the gate file location). The temp tree does not need a runnable bootstrap.

#### Modifies

- [x] `composer.json` — add root script:
  - [x] `spike:gate` → `@composer --working-dir=framework spike:gate`
- [x] `framework/composer.json` — add workspace mirror script:
  - [x] `spike:gate` → `@php tools/gates/spikes_boundary_gate.php`
- [x] `composer.json` OR `framework/composer.json` — enforcement integration:
  - [x] `spike:test` MUST execute `spike:gate` before running spikes phpunit suite
  - [x] `spike:gate` MUST be first in the Phase 0 spikes rails chain
  - [x] Existing gates introduced earlier (if any) MUST remain in the chain (they MUST NOT be removed by this epic)
- [x] `framework/tools/spikes/_support/ErrorCodes.php` — register boundary gate error codes:
  - [x] `CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_IMPORT`
  - [x] `CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_PATH`
  - [x] `CORETSIA_SPIKES_BOUNDARY_SCAN_FAILED`

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [x] Output is stable and safe:
  - [x] MUST NOT print secrets/PII
  - [x] MAY print normalized relative paths of offending files only

#### Errors

- [x] Deterministic error codes (owned by spikes ErrorCodes registry):
  - [x] `CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_IMPORT`
  - [x] `CORETSIA_SPIKES_BOUNDARY_FORBIDDEN_PATH`
  - [x] `CORETSIA_SPIKES_BOUNDARY_SCAN_FAILED`

#### Security / Redaction

- [x] MUST NOT leak: dotenv values, credentials, tokens/cookies/Authorization, raw payloads
- [x] Allowed: safe normalized relative paths + fixed codes + short reasons

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] `SpikesBoundaryGateDetectsForbiddenImportsTest` proves the gate is real:
  - [x] MUST fail on forbidden namespace import
  - [x] MUST pass on internal-toolkit import

### Tests (MUST)

- Unit / Integration:
  - N/A

- Contract:
  - [x] `framework/tools/spikes/tests/SpikesBoundaryGateDetectsForbiddenImportsTest.php`

- Gates/Arch:
  - [x] `composer spike:gate` exists and is executed by rails (standalone or pre-step in `spike:test`)

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] Gate exists, deterministic, and enforced (not optional)
- [x] Contract test proves failure/passing behavior
- [x] Forbidden deps respected (no runtime imports)

---

### 0.40.0 `coretsia/internal-toolkit` (MUST) [TOOLING]

---
type: package
phase: 0
epic_id: "0.40.0"
owner_path: "framework/packages/devtools/internal-toolkit/"

package_id: "devtools/internal-toolkit"
composer: "coretsia/devtools-internal-toolkit"
kind: library

goal: "Provide a single deterministic helper library for tooling and prevent duplication under framework/tools/**."
provides:
- "Canonical determinism helpers: slug casing, path normalization, stable JSON encoding"
- "Anti-duplication gate for framework/tools/** (including spikes)"
- "Golden vectors cement determinism invariants (Linux/Windows)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.30.0 — framework workspace exists
  - 0.10.0 — boundary decision locked (tooling helpers must be in internal-toolkit)
  - 0.20.0 — spikes sandbox + Phase 0 rails exist (spike scripts/gates entrypoints exist)
  - 0.30.0 — spikes boundary enforcement gate exists (`spike:gate`)

- Required deliverables (exact paths):
  - `framework/composer.json` — package workspace root exists
  - `framework/tools/spikes/_support/ErrorCodes.php` — Phase 0 deterministic error codes registry exists
  - `framework/tools/gates/` — gates root exists (or is created earlier in Phase 0)
  - `framework/tools/spikes/_support/bootstrap.php` — tools rails bootstrap exists (used by gates)
  - `framework/tools/spikes/_support/ConsoleOutput.php` — canonical rails stdout/stderr writer exists (gates MUST use it)

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- `core/*`
- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- Tooling gate:
  - `php framework/tools/gates/internal_toolkit_no_dup_gate.php` — deterministic failure if duplicates detected
- Library usage policy (invariant):
  - `coretsia/internal-toolkit` is used by tooling (`framework/tools/**`, spikes, gates, build scripts)
  - It MUST NOT be required by runtime presets/modules as a mandatory runtime dependency
- Composer:
  - `composer toolkit:gate` — runs the anti-duplication gate (MUST be runnable locally)

### Deliverables (MUST)

#### Creates

- [x] Package skeleton (MUST; exact paths):
  - [x] `framework/packages/devtools/internal-toolkit/composer.json`
    - [x] MUST define PSR-4 autoload:
      - [x] `Coretsia\\Devtools\\InternalToolkit\\` → `src/`
    - [x] MUST NOT require any runtime packages (`core/*`, `platform/*`, `integrations/*`)
  - [x] `framework/packages/devtools/internal-toolkit/README.md`
    - [x] MUST state scope: tooling-only deterministic helpers (slug/path/json) + anti-dup gate contract
  - [x] (when applicable) PHPUnit config for this package tests:
    - [x] `framework/packages/devtools/internal-toolkit/phpunit.xml` OR `phpunit.dist.xml`
      (use the repo’s canonical convention; this epic must not introduce a new convention)
- [x] `framework/packages/devtools/internal-toolkit/src/Slug.php` — deterministic slug casing helpers
  - [x] `toStudly(string $slug): string`
  - [x] `toSnake(string $slug): string`
- [x] `framework/packages/devtools/internal-toolkit/src/Path.php` — deterministic path normalization
  - [x] `normalizeRelative(string $absOrRelPath, string $repoRoot): string`
    - [x] MUST return a repo-relative normalized path (forward slashes)
    - [x] MUST NOT return `..` segments
    - [x] MUST NOT return an absolute path outside `repoRoot`
- [x] `framework/packages/devtools/internal-toolkit/src/Json.php` — stable JSON encoder
  - [x] `encodeStable(array $value): string` (single-choice; cemented):
    - [x] MUST normalize maps by sorting keys ascending by byte-order (`strcmp`) at every nesting level.
    - [x] MUST preserve list order (lists are NOT sorted).
    - [x] MUST classify list vs map using `array_is_list(...)` (single-choice).
    - [x] Empty array rule (cemented):
      - [x] `[]` MUST be treated as a list and encoded as JSON `[]`.
      - [x] Rationale: PHP cannot represent an “empty map” vs “empty list” distinction using arrays alone.
    - [x] MUST encode using:
      - [x] `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR`
    - [x] MUST NOT rely on locale (`setlocale`, `LC_ALL`) for ordering.
- [x] `framework/tools/gates/internal_toolkit_no_dup_gate.php` — blocks duplicates in `framework/tools/**`
  - [x] Anti-duplication policy is **symbol-ownership based** (single-choice):
    - [x] The following determinism symbols are owned by `coretsia/internal-toolkit` and MUST NOT be declared anywhere under `framework/tools/**`:
      - [x] `Coretsia\Devtools\InternalToolkit\Slug::{toStudly,toSnake}`
      - [x] `Coretsia\Devtools\InternalToolkit\Path::normalizeRelative`
      - [x] `Coretsia\Devtools\InternalToolkit\Json::encodeStable`
    - [x] A “duplication” is defined as any PHP file under `framework/tools/**` that declares:
      - [x] any function named exactly: `toStudly`, `toSnake`, `normalizeRelative`, `encodeStable`, OR
      - [x] any class method named exactly: `toStudly`, `toSnake`, `normalizeRelative`, `encodeStable`
      - [x] (Exception) thin wrappers explicitly allowlisted below.
  - [x] Stable JSON usage is enforced (single-choice; NOT optional):
    - [x] Direct calls to `json_encode(...)` anywhere under `framework/tools/**` are FORBIDDEN.
    - [x] Rationale: tooling JSON must use `\Coretsia\Devtools\InternalToolkit\Json::encodeStable(...)`
      for deterministic flags/encoding.
    - [x] Allowlisted exception files: none (single-choice; cemented in 0.40.0).
      - [x] Later epics MAY extend the allowlist only by explicitly modifying this gate as a deliverable
        (no implicit/forward references).
    - [x] Call detection MUST be token-based:
      - [x] detect `T_STRING` OR `T_NAME_QUALIFIED` OR `T_NAME_FULLY_QUALIFIED`
      - [x] take the last segment as the function name for qualified names (e.g. `\json_encode` → `json_encode`)
      - [x] MUST ignore if immediately preceded (ignoring whitespace) by `T_OBJECT_OPERATOR` or `T_DOUBLE_COLON`
      - [x] MUST require the next non-whitespace token after the name is `(` to treat it as a call
    - [x] Scan scope exclusions (single-choice):
      - [x] MUST exclude:
        - [x] `framework/tools/**/tests/**`
        - [x] `framework/tools/**/fixtures/**`
    - [x] Allowlist handling (single-choice; enforceable):
      - [x] Since the allowlist is empty in 0.40.0, ANY `json_encode(...)` call under `framework/tools/**` MUST fail the gate.
  - [x] Thin wrappers are allowed (single-choice) only if all conditions are true:
    - [x] File path matches allowlist:
      - [x] `spikes/*/StableJsonEncoder.php`
        - [x] Path is evaluated as scan-root-relative.
        - [x] With default scanRoot=framework/tools this matches framework/tools/spikes/*/StableJsonEncoder.php; with --path=<dir> it matches <scanRoot>/spikes/*/StableJsonEncoder.php.
    - [x] The file MUST NOT call `json_encode` (directly).
    - [x] The wrapper method MAY be named encodeStable, and the wrapper file MUST contain a direct call-token pattern to:
      - [x] `\Coretsia\Devtools\InternalToolkit\Json::encodeStable(` (token-based presence rule; no body validation)
      - [x] The gate MUST NOT attempt to “understand” function bodies beyond the allowlist rules above (no heuristic delegation detection).
- [x] `framework/tools/gates/internal_toolkit_no_dup_gate.php` MUST:
  - [x] scan `framework/tools/**/*.php` (including spikes and gates)
  - [x] Scan scope exclusions (single-choice; reduces false positives while keeping enforceability):
    - [x] MUST exclude:
      - [x] `**/tests/**`
      - [x] `**/fixtures/**`
    - [x] Rationale: gates enforce production tooling code; tests/fixtures may legitimately contain symbol-name strings or helper methods without representing duplicated canonical implementations.
  - [x] use token-based inspection (`token_get_all`) to detect forbidden declarations/calls deterministically
  - [x] fail with a stable error code and stable diagnostics:
    - [x] print code
    - [x] print a stable list of offending **scan-root-relative** normalized file paths + matched symbol name
    - [x] MUST NOT print code fragments or file contents
  - [x] Deterministic CODE selection (single-choice; cemented):
    - [x] If scanning fails due to an internal error/exception → print `CORETSIA_TOOLKIT_DUP_GATE_SCAN_FAILED` on line 1.
    - [x] Else if at least one forbidden `json_encode(...)` call is detected (outside allowlist) → print `CORETSIA_TOOLKIT_JSON_ENCODE_FORBIDDEN` on line 1.
    - [x] Else if at least one forbidden symbol duplication is detected → print `CORETSIA_TOOLKIT_DUPLICATION_DETECTED` on line 1.
    - [x] Else → pass (exit 0) and print nothing.
  - [x] Diagnostics emission semantics (single-choice; cemented):
    - [x] If the gate fails (any CODE printed), it MUST print diagnostics lines for ALL detected violations
      (both `json_encode` calls and symbol duplications), sorted by `<scan-root-relative-normalized-path>` via `strcmp`.
    - [x] The selected CODE remains precedence-based (scan-failed > json-forbidden > duplication),
      but diagnostics are exhaustive to avoid “fix one → discover another” churn.
  - [x] Diagnostics lines (line 2+), if any, MUST be stable and minimal:
    - [x] format: `<scan-root-relative-normalized-path>: <matched-symbol>`
    - [x] For json violations, `<matched-symbol>` MUST be exactly `json_encode`.
  - [x] Runtime roots vs scan root (single-choice; required):
    - [x] The gate MUST separate:
      - [x] `$toolsRootRuntime` — where the gate code + bootstrap live (derived from the gate file location)
      - [x] `$scanRoot` — what filesystem subtree is scanned for duplicates/forbidden calls
    - [x] CWD-independence (single-choice; required):
      - [x] Let `$toolsRootRuntime = realpath(__DIR__ . '/..')`.
    - [x] `--path=<dir>` semantics (single-choice; cemented):
      - [x] If `--path=<dir>` is provided:
        - [x] `$scanRoot = realpath(<dir>)`
      - [x] Else:
        - [x] `$scanRoot = $toolsRootRuntime`
      - [x] `--path` MUST NOT affect bootstrap discovery/loading.
    - [x] Bootstrap (single-choice; required):
      - [x] Let `$bootstrap = $toolsRootRuntime . '/spikes/_support/bootstrap.php'`.
      - [x] The gate MUST `require_once $bootstrap` BEFORE scanning,
        so it can use autoloaded `Coretsia\Devtools\InternalToolkit\*` and `ConsoleOutput`.
      - [x] If `$bootstrap` is missing/unreadable:
        - [x] MUST `require_once $toolsRootRuntime . '/spikes/_support/ConsoleOutput.php'` (local tools-only include).
        - [x] MUST print `CORETSIA_TOOLKIT_DUP_GATE_SCAN_FAILED` on line 1 via `ConsoleOutput` (no echo/print).
        - [x] MUST exit `1`.
        - [x] MUST NOT leak absolute paths.
      - [x] NOTE (cemented): if the bootstrap file exists but terminates the process
        (e.g. Composer autoload missing), the bootstrap’s deterministic output/code is authoritative.
      - [x] The gate MUST NOT attempt to override it.
    - [x] Scan root (single-choice; required):
      - [x] The gate MUST scan under `$scanRoot` (excluding `**/tests/**` and `**/fixtures/**`):
        - [x] `$scanRoot . '/**/*.php'`
      - [x] Diagnostics paths MUST be **scan-root-relative** (forward slashes), sorted by path via `strcmp`.
  - [x] Output format + emission (single-choice; SSoT):
    - [x] Line 1: deterministic `CODE` only
    - [x] Next lines: `<scan-root-relative-normalized-path>: <matched-symbol>` sorted by path via `strcmp`
    - [x] Output MUST be emitted via `framework/tools/spikes/_support/ConsoleOutput.php` only.
    - [x] Exit code policy (single-choice):
      - [x] exit `0` on pass
      - [x] exit `1` on fail

- [x] `framework/tools/spikes/tests/InternalToolkitNoDupGateDetectsDuplicationTest.php` — contract evidence:
  - [x] given a temp scan root containing a PHP file under `tools/**` declaring a forbidden symbol name
    (e.g. `function encodeStable(...) {}` or a class method `toSnake`) → gate MUST fail with
    `CORETSIA_TOOLKIT_DUPLICATION_DETECTED`
  - [x] given a temp scan root containing an allowlisted thin wrapper at
    `spikes/<any>/StableJsonEncoder.php` delegating to
    `\Coretsia\Devtools\InternalToolkit\Json::encodeStable(...)` and containing no direct `json_encode` call
    → gate MUST pass
  - [x] MUST run the gate in `--path=<tempDir>` mode (cement CLI override behavior)
  - [x] given a temp scan root containing a PHP file under `tools/**` that calls `json_encode(...)`
    → gate MUST fail with `CORETSIA_TOOLKIT_JSON_ENCODE_FORBIDDEN`
  - [x] NOTE (cemented): `--path` is scan-only. The gate always loads bootstrap/ConsoleOutput from the real repo
    runtime tools root (derived from the gate file location). The temp tree is only scanned.

#### Modifies

- [x] `composer.json` — add root script:
  - [x] `toolkit:gate` → `@composer --no-interaction --working-dir=framework run-script toolkit:gate --`
- [x] `framework/composer.json` — add workspace mirror script:
  - [x] `toolkit:gate` → `@php tools/gates/internal_toolkit_no_dup_gate.php`
- [x] `framework/composer.json` — ensure tooling can autoload the package:
  - [x] MUST add `coretsia/devtools-internal-toolkit` to `require-dev` (single-choice; Phase 0 tooling dependency)
  - [x] Rationale: spikes/gates run via Composer autoload and MUST be able to import `Coretsia\Devtools\InternalToolkit\*`
- [x] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [x] `CORETSIA_TOOLKIT_DUPLICATION_DETECTED`
  - [x] `CORETSIA_TOOLKIT_DUP_GATE_SCAN_FAILED`
  - [x] `CORETSIA_TOOLKIT_JSON_ENCODE_FORBIDDEN`
- [x] `composer.json` OR `framework/composer.json` — enforcement integration:
  - [x] `spike:test` MUST execute gates before spikes phpunit in this exact order (single-choice):
    1) `spike:gate`          (introduced in 0.30.0)
    2) `toolkit:gate`        (introduced in 0.40.0)
    3) `spike:output:gate`   (introduced in 0.20.0)
    4) `phpunit -c framework/tools/spikes/phpunit.spikes.xml`
  - [x] Gate chain extensibility rule (single-choice; cemented):
    - [x] Later Phase 0 epics MAY insert additional gates into `spike:test` ONLY under these constraints:
      - [x] `spike:gate` MUST remain the first gate.
      - [x] `spike:output:gate` MUST remain the last gate immediately before PHPUnit.
      - [x] Any new gate MUST be inserted between `spike:gate` and `spike:output:gate`.
    - [x] Rationale: boundary must fail fast; output bypass must be enforced right before running tests.

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

N/A (library; no logging side-effects)

#### Errors

- [x] Deterministic exceptions/messages for invalid inputs (if any), without leaking absolute paths
- [x] Anti-dup gate error codes MUST be registered:
  - [x] `CORETSIA_TOOLKIT_DUPLICATION_DETECTED`
  - [x] `CORETSIA_TOOLKIT_DUP_GATE_SCAN_FAILED`

#### Security / Redaction

- [x] Path normalization MUST NOT return `..` segments or absolute paths outside repoRoot

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] Golden vectors MUST include (cemented):
  - [x] Slug casing:
    - [x] `psr-7`
    - [x] `oauth2`
    - [x] `token123` (numeric tokens)
    - [x] `double--dash` (double dashes)
  - [x] Path normalization:
    - [x] Windows separators: `a\b\c`
    - [x] mixed separators: `a/b\c`
    - [x] redundant segments: `./a/b`, `a//b`
    - [x] drive letters (Windows): `C:\repo\framework\...`
  - [x] Stable JSON:
    - [x] map key ordering is stable (nested maps too)
    - [x] list order preserved
    - [x] UTF-8/unicode strings preserved deterministically

### Tests (MUST)

- Unit:
  - [x] `framework/packages/devtools/internal-toolkit/tests/Unit/SlugToStudlyGoldenVectorsTest.php`
  - [x] `framework/packages/devtools/internal-toolkit/tests/Unit/PathNormalizeRelativeGoldenVectorsTest.php`
- Contract:
  - [x] `framework/packages/devtools/internal-toolkit/tests/Contract/JsonEncodeStableContractTest.php`
- Integration / Gates:
  - N/A (gate is a tooling script; invoked by CI/scripts)

### DoD (MUST)

- [x] Deliverables complete (creates), paths exact
- [x] Gate blocks any duplicated slug/path/json logic under `framework/tools/**`
- [x] Toolkit is the only canonical helper for tooling determinism
- [x] Out of scope:
  - [x] This epic MUST NOT introduce production runtime behavior (Phase 0 spikes/tooling only)
  - [x] This epic MUST NOT introduce plugin systems / extensibility frameworks
  - [x] This epic MUST NOT depend on `core/kernel` (unless explicitly stated in the epic)

---

### 0.50.0 Deterministic file IO policy for spikes (MUST) [TOOLING]

---
type: tools
phase: 0
epic_id: "0.50.0"
owner_path: "framework/tools/spikes/_support/"

goal: "Provide a single canonical deterministic file IO helper for spikes (EOL normalization, LF writes, stable hashing)."
provides:
- "Canonical EOL normalization to LF for content hashing/comparison"
- "Canonical LF-only writes with final newline for all spike-generated text artifacts"
- "Shared helper to prevent per-spike micro-implementations (fingerprint/deptrac/config/workspace)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.20.0 — spikes sandbox exists (`framework/tools/spikes/**`)

- Required deliverables (exact paths):
  - `framework/tools/spikes/_support/` — support folder exists (or is created by 0.20.0)
  - `framework/tools/spikes/_support/ErrorCodes.php` — shared error codes exist

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- `core/*`
- `platform/*`
- `integrations/*`
- `framework/packages/**/src/**`
  - (No exceptions) Spikes tooling code MUST NOT `require|require_once|include|include_once` any sources from `framework/packages/**`
    (including `framework/packages/devtools/internal-toolkit/**`).
  - `coretsia/internal-toolkit` MAY be used only via Composer autoload (namespace-based) **if** the executing tooling environment
    has it installed as a Composer dependency. This epic itself MUST NOT require it.

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [x] `framework/tools/spikes/_support/DeterministicFile.php` — canonical helper:
  - [x] `readTextNormalizedEol(string $path): string`
    - [x] normalizes `\r\n` and `\r` to `\n`
  - [x] `hashSha256NormalizedEol(string $path): string`
    - [x] hashes normalized content (LF) to avoid OS EOL drift
  - [x] `writeTextLf(string $path, string $content): void`
    - [x] writes LF-only endings
    - [x] MUST ensure final newline
    - [x] MUST create parent directories deterministically if they do not exist (no timestamps/random names; directory creation is idempotent)
  - [x] `writeBytesExact(string $path, string $bytes): void` (single-choice; required for backups/copies):
    - [x] MUST write bytes **exactly as provided** (NO EOL normalization, NO final newline injection)
    - [x] MUST create parent directories deterministically if they do not exist
    - [x] MUST be usable for “side-by-side backup bytes must match exactly” invariants
  - [x] MUST NOT emit absolute paths in exception messages (single-choice, enforceable):
    - [x] Exceptions/messages MUST NOT include the input `$path` string at all.
    - [x] Allowed diagnostics (if any): a fixed error code + a short reason only (no paths).
    - [x] If a caller needs a display path, it MUST be provided separately by the caller (already normalized & safe), not derived inside `DeterministicFile`.
  - [x] MUST be warning-silent (single-choice; enforceable):
    - [x] The helper MUST NOT emit any PHP warnings/notices during IO (no stderr noise).
    - [x] Implementation MUST wrap each filesystem operation in a temporary error handler (single-choice):
      - [x] capture any warning/notice emitted by the operation
      - [x] convert it into a `DeterministicException` with the appropriate deterministic code
      - [x] MUST NOT propagate the original PHP warning message (it may contain paths and OS-specific text).
    - [x] After the operation, the original error handler MUST be restored deterministically (no global side-effects).
  - [x] Error handling MUST be deterministic and code-first:
    - [x] On any read failure (`readTextNormalizedEol`, `hashSha256NormalizedEol`) the helper MUST throw
      `framework/tools/spikes/_support/DeterministicException` with code `CORETSIA_SPIKES_IO_READ_FAILED`.
    - [x] On any write failure (`writeTextLf`, `writeBytesExact`) the helper MUST throw
      `framework/tools/spikes/_support/DeterministicException` with code `CORETSIA_SPIKES_IO_WRITE_FAILED`.
    - [x] Exception messages MUST be stable and MUST NOT include:
      - [x] the input `$path` string (neither absolute nor relative)
      - [x] OS error messages that include paths

- [x] `framework/tools/spikes/tests/DeterministicFileEolNormalizationContractTest.php` — cement:
  - [x] proves read normalization (`\r\n` and `\r` → `\n`)
  - [x] proves hashing stability for CRLF/LF equivalent content
  - [x] proves write guarantees LF + final newline
  - [x] proves deterministic failure semantics:
    - [x] reading a missing file MUST throw `DeterministicException` with code `CORETSIA_SPIKES_IO_READ_FAILED`
    - [x] the exception message MUST NOT contain the missing filename/path
  - [x] proves byte-exact write for backups:
    - [x] `writeBytesExact` writes bytes unchanged (input bytes == file bytes)
    - [x] the exception message for a failing `writeBytesExact` MUST NOT contain the target filename/path

#### Modifies

- [x] `framework/tools/spikes/_support/ErrorCodes.php` — register deterministic IO error codes:
  - [x] `CORETSIA_SPIKES_IO_READ_FAILED`
  - [x] `CORETSIA_SPIKES_IO_WRITE_FAILED`

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

N/A

#### Errors

- [x] Deterministic error codes (if needed by implementation):
  - [x] `CORETSIA_SPIKES_IO_READ_FAILED`
  - [x] `CORETSIA_SPIKES_IO_WRITE_FAILED`

#### Security / Redaction

- [x] MUST NOT leak: file contents, secrets, absolute machine paths

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] `DeterministicFileEolNormalizationContractTest` fails if EOL normalization is removed

### Tests (MUST)

- Unit / Integration / Gates/Arch:
  - N/A
- Contract:
  - [x] `framework/tools/spikes/tests/DeterministicFileEolNormalizationContractTest.php`

### DoD (MUST)

- [x] Deliverables complete (creates), paths exact
- [x] DeterministicFile is the single canonical helper for EOL normalization + LF writes in spikes
- [x] Contract test is green and enforces behavior

---

### 0.60.0 Fingerprint determinism prototype (MUST) [TOOLING]

---
type: tools
phase: 0
epic_id: "0.60.0"
owner_path: "framework/tools/spikes/fingerprint/"

goal: "Prototype a cross-OS deterministic fingerprint with safe explain (no secrets)."
provides:
- "Deterministic file listing + symlink hard-fail policy"
- "Single sha256 fingerprint for fixed inputs with safe explain buckets"
- "Golden hash invariants validated on Linux/Windows"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.20.0 — spikes sandbox + fixtures/support exist
  - 0.40.0 — internal-toolkit exists
  - 0.50.0 — canonical DeterministicFile helper exists (EOL normalization + LF writes)

- Required deliverables (exact paths):
  - `framework/tools/spikes/fixtures/repo_min/` — repo_min fixture root exists
  - `framework/tools/spikes/fixtures/http_middleware_catalog.php` — middleware catalog fixture exists
  - `framework/tools/spikes/_support/ErrorCodes.php` — shared deterministic codes exist

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `devtools/internal-toolkit`

Forbidden:

- `core/*`
- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- CLI:
  - `coretsia spike:fingerprint` → `framework/packages/devtools/cli-spikes/src/Command/SpikeFingerprintCommand.php` (via 0.140.0 on top of 0.130.0; available only if `coretsia/cli-spikes` is installed)

- Artifacts:
  - reads: `framework/tools/spikes/fixtures/repo_min/**`

### Deliverables (MUST)

#### Creates

- [x] `framework/tools/spikes/fingerprint/DeterministicFileLister.php` — MUST:
  - [x] normalize paths via `Coretsia\Devtools\InternalToolkit\Path::normalizeRelative(...)` (repo-root based)
  - [x] emit stable lexicographic order (byte-order; no locale-dependent sorting)
  - [x] hard-fail on any symlink with deterministic code `CORETSIA_FINGERPRINT_SYMLINK_FORBIDDEN`
  - [x] Symlink detection & traversal policy is single-choice (cemented):
    - [x] The lister MUST NOT follow symlinks during traversal.
    - [x] Implementation MUST use `RecursiveDirectoryIterator` without `FOLLOW_SYMLINKS` (or any equivalent follow mode).
    - [x] A symlink violation MUST be detected via `SplFileInfo::isLink()` (or equivalent) BEFORE recursing into an entry.
    - [x] On first encountered symlink, the lister MUST throw `DeterministicException` with code `CORETSIA_FINGERPRINT_SYMLINK_FORBIDDEN`
      (no partial results, no best-effort continuation).
  - [x] emit stable lexicographic order (byte-order; no locale-dependent sorting):
    - [x] sorting MUST be implemented via `strcmp` on normalized repo-relative paths only
- [x] `framework/tools/spikes/fingerprint/StableJsonEncoder.php` — thin wrapper over `InternalToolkit\Json::encodeStable`
- [x] `framework/tools/spikes/fingerprint/FingerprintCalculator.php` — computes a single sha256 from canonical buckets:
  - [x] `code`: deterministic file list + sha256 over file contents with EOL normalized to LF:
    - [x] For each matched code file: compute `sha256` over normalized-LF content via
      `DeterministicFile::hashSha256NormalizedEol()` (single canonical helper).
    - [x] Combine per-file hashes in stable normalized repo-relative path order (byte-order; `strcmp`).
  - [x] `config`: deterministic hash of config PHP files with EOL normalized to LF:
    - [x] For each matched config file: compute `sha256` via `DeterministicFile::hashSha256NormalizedEol()`.
    - [x] Combine per-file hashes in stable normalized repo-relative path order (byte-order; `strcmp`).
  - [x] `dotenv`: deterministic hash of `.env*` files (single-choice; no parsing in this bucket):
    - [x] for each matched `.env*` file: compute `sha256` over normalized-LF raw bytes
      (via `DeterministicFile::hashSha256NormalizedEol()`)
    - [x] combine per-file hashes in stable file path order (byte-order on normalized repo-relative paths)
    - [x] NEVER print dotenv values; explain may list file paths only (repo-relative)
  - [x] `tracked_env`: allowlist is loaded from `framework/tools/spikes/fixtures/repo_min/tracked_env_allowlist.php`:
    - [x] only allowlisted env key names are admitted
    - [x] values are hashed into fingerprint (never printed)
    - [x] allowlist evaluation MUST be deterministic and independent of locale
    - [x] `tracked_env` value source and missing semantics MUST be cemented (single-choice):
      - [x] Source: values MUST be read from the process environment only via `getenv($key)` (canonical source).
      - [x] Missing vs empty MUST be distinguishable and deterministic:
        - [x] if `getenv($key) === false` then the key is treated as “missing”
        - [x] if `getenv($key) === ''` then the key is treated as “present-empty”
      - [x] Fingerprint input for each allowlisted key MUST include:
        - [x] the key name
        - [x] a presence marker (`0` for missing, `1` for present)
        - [x] `sha256(value)` only when present (value itself MUST NEVER be printed)
      - [x] Keys are processed in deterministic allowlist order (byte-order; no locale).
  - [x] `schema_versions`: fixed schema/version constants included as inputs
  - [x] Repo-root definition for this spike (single-choice; cemented):
    - [x] For Phase 0 fingerprint spike, “repoRoot” MUST mean the fixture repo root:
      - [x] `FixtureRoot::path('repo_min/skeleton')`
    - [x] All “repo-relative” normalized paths produced by the lister/explainer MUST be relative to this fixture root
      (forward slashes), not to the real Git repo root.
    - [x] `expected_paths.txt` MUST contain paths relative to this fixture root and MUST match the lister output exactly.
  - [x] Fingerprint composition algorithm (single-choice; cemented):
    - [x] The final fingerprint MUST be computed from an ordered map of bucket digests:
      - [x] Build `buckets: map<string,string>` where keys are bucket names and values are hex sha256 digests.
      - [x] Bucket keys MUST be sorted ascending by byte-order (`strcmp`) before encoding.
    - [x] Encode the buckets map using `StableJsonEncoder` (which delegates to `InternalToolkit\Json::encodeStable`).
    - [x] Compute the final fingerprint as `sha256( encodedBucketsJson )` and output it as lowercase hex.
    - [x] Rationale: prevents ad-hoc concatenation formats and locks a single cross-OS deterministic scheme.
- [x] `framework/tools/spikes/fingerprint/FingerprintExplainer.php` — MUST:
  - [x] list changed file paths as normalized repo-relative paths only
  - [x] list changed tracked env keys by name only (no values)
  - [x] NEVER print dotenv values; only safe forms are allowed (`hash(value)`, `len(value)`)
- [x] Fixtures under `framework/tools/spikes/fixtures/repo_min/`:
  - [x] `framework/tools/spikes/fixtures/repo_min/skeleton/config/app.php`
  - [x] `framework/tools/spikes/fixtures/repo_min/skeleton/config/modules.php`
  - [x] `framework/tools/spikes/fixtures/repo_min/skeleton/config/http.php`
  - [x] `framework/tools/spikes/fixtures/repo_min/skeleton/apps/web/config/app.php`
  - [x] `framework/tools/spikes/fixtures/repo_min/skeleton/apps/web/config/http.php`
  - [x] `framework/tools/spikes/fixtures/repo_min/skeleton/.env`
  - [x] `framework/tools/spikes/fixtures/repo_min/skeleton/.env.local`
  - [x] `framework/tools/spikes/fixtures/repo_min/skeleton/.env.local.local`
  - [x] `framework/tools/spikes/fixtures/repo_min/expected_paths.txt`
  - [x] `framework/tools/spikes/fixtures/repo_min/tracked_env_allowlist.php` — cemented allowlist (single source of truth):
    - [x] returns `list<string>` of allowed env key names for `tracked_env`
    - [x] order MUST be deterministic (byte-order; no locale)
    - [x] MUST be unique:
      - [x] duplicate env key names are forbidden and MUST fail tests deterministically
- [x] Fingerprint hashing MUST be EOL-stable across OS:
  - [x] file content hashing MUST normalize EOL to `\n` before hashing
  - [x] spike MUST use `framework/tools/spikes/_support/DeterministicFile::hashSha256NormalizedEol()` (single canonical helper)
- [x] Any text fixtures used as inputs to hashing (e.g. `expected_paths.txt`) MUST be LF-only and end with a final newline

- [x] Tests:
  - [x] `framework/tools/spikes/fingerprint/tests/FingerprintGoldenHashTest.php`
  - [x] `framework/tools/spikes/fingerprint/tests/PathSeparatorNormalizationTest.php`
  - [x] `framework/tools/spikes/fingerprint/tests/FileListingDeterministicOrderTest.php`
  - [x] `framework/tools/spikes/fingerprint/tests/SymlinkForbiddenTest.php`
  - [x] `framework/tools/spikes/fingerprint/tests/FingerprintExplainIsSafeTest.php`
  - [x] `framework/tools/spikes/fingerprint/tests/RerunSameInputsSameHashTest.php`
  - [x] `framework/tools/spikes/fingerprint/tests/HttpMiddlewareCatalogIsFullyUsedInHttpConfigFixturesTest.php`
  - [x] `framework/tools/spikes/fingerprint/tests/TrackedEnvAllowlistIsCementedTest.php`
  - [x] `framework/tools/spikes/fingerprint/tests/TrackedEnvAllowlistHasNoDuplicatesTest.php`
  - [x] `framework/tools/spikes/fingerprint/tests/TrackedEnvBucketMissingVsEmptyIsDeterministicTest.php`
    - [x] sets an allowlisted key to missing and to empty string in two runs and asserts:
      - [x] hashes differ (missing != empty)
      - [x] each scenario is deterministic across reruns/OS

#### Modifies

- [x] `framework/tools/spikes/_support/ErrorCodes.php` — register fingerprint error codes:
  - [x] `CORETSIA_FINGERPRINT_SYMLINK_FORBIDDEN`

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [x] Explain output contains no dotenv values; only hashes + key names

#### Errors

- [x] Deterministic exception codes:
  - [x] `CORETSIA_FINGERPRINT_SYMLINK_FORBIDDEN`
  - [x] other fixed IO/invalid-input codes (if needed) via `framework/tools/spikes/_support/ErrorCodes.php`

#### Security / Redaction

- [x] MUST NOT leak dotenv values; only key names and hashes
- [x] MUST NOT print absolute paths; only normalized repo-relative paths

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] Golden hash test must pass on Linux + Windows with identical sha256 for the same inputs

### Tests (MUST)

- Unit / Contract:
  - N/A
- Integration:
  - [x] `framework/tools/spikes/fingerprint/tests/*` executed via `framework/tools/spikes/phpunit.spikes.xml`

### DoD (MUST)

- [x] Windows CI symlink precondition (single-choice; no skips):
  - [x] The Windows CI environment MUST support creating symlinks in the workspace.
  - [x] `SymlinkForbiddenTest` MUST NOT skip; if symlink creation is not supported, the test MUST hard-fail.
  - [x] CI configuration MUST ensure symlinks are enabled for checkout/runtime (e.g. git symlink support + runner policy).
- [x] Same inputs → same fingerprint on Linux/Windows
- [x] Explain is deterministic + safe
- [x] Middleware catalog is deterministic and single-source-of-truth:
  - [x] `framework/tools/spikes/fixtures/http_middleware_catalog.php` defines:
    - [x] all slot keys
    - [x] ordered FQCN lists per slot
  - [x] `framework/tools/spikes/fixtures/repo_min/skeleton/config/http.php` MUST be derived from the catalog
    - [x] MUST `require`/`include` the catalog (no duplicated lists)
    - [x] MUST include all slots:
      - [x] `http.middleware.system_pre`
      - [x] `http.middleware.system`
      - [x] `http.middleware.system_post`
      - [x] `http.middleware.app_pre`
      - [x] `http.middleware.app`
      - [x] `http.middleware.app_post`
      - [x] `http.middleware.route_pre`
      - [x] `http.middleware.route`
      - [x] `http.middleware.route_post`
    - [x] Canonical Phase 0 slot taxonomy is system/app/route; legacy keys (user_*, etc.) are not used in Phase 0 spikes.
  - [x] A test MUST fail if any slot is missing or diverges from the catalog
- [x] Out of scope:
  - [x] This epic MUST NOT introduce production runtime behavior (Phase 0 spikes/tooling only)
  - [x] This epic MUST NOT introduce plugin systems / extensibility frameworks
  - [x] This epic MUST NOT depend on `core/kernel` (unless explicitly stated in the epic)
- [x] tracked_env allowlist is single-source-of-truth:
  - [x] removing or bypassing `tracked_env_allowlist.php` MUST break tests
- [x] Sorting invariant:
  - [x] MUST NOT rely on `LC_ALL=C` or OS locale settings
  - [x] sorting is implemented via byte-order comparisons (`strcmp` / `SORT_STRING`) only

---

### 0.70.0 PayloadNormalizer + StableJsonEncoder prototype (MUST) [TOOLING]

---
type: tools
phase: 0
epic_id: "0.70.0"
owner_path: "framework/tools/spikes/payload/"

goal: "Cement deterministic payload normalization and stable JSON encoding with a strict float-forbidden policy."
provides:
- "Deterministic normalization (map ksort; list order preserved)"
- "Stable JSON encoding via internal-toolkit"
- "Deterministic float/NaN/INF rejection with fixed error code"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.20.0 — spikes sandbox exists
  - 0.40.0 — internal-toolkit exists

- Required deliverables (exact paths):
  - `framework/tools/spikes/fixtures/payloads_min/` — payload fixture root exists
  - `framework/tools/spikes/_support/ErrorCodes.php` — shared codes exist

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `devtools/internal-toolkit`

Forbidden:

- `core/*`
- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A (phpunit-only spike)

### Deliverables (MUST)

#### Creates

- [x] `framework/tools/spikes/payload/PayloadNormalizer.php` — deterministic normalization for json-like arrays
- [x] `framework/tools/spikes/payload/StableJsonEncoder.php` — wrapper over `InternalToolkit\Json::encodeStable`
- [x] `framework/tools/spikes/payload/FloatPolicy.php` — forbids float/NaN/INF anywhere in payload (`CORETSIA_JSON_FLOAT_FORBIDDEN`):
  - [x] MUST reject any `float` value at any nesting depth (maps/lists)
  - [x] MUST reject `NaN`, `INF`, `-INF` explicitly (not only `is_float`)
  - [x] MUST NOT emit any stdout/stderr output (spike business logic is output-free by Phase 0 rails policy).
  - [x] MUST NOT expose raw payload values on failure.
  - [x] Minimal diagnostics are allowed (single-choice) ONLY via deterministic failure carrier:
    - [x] If failing, it MUST throw `framework/tools/spikes/_support/DeterministicException`
      with code `CORETSIA_JSON_FLOAT_FORBIDDEN`.
    - [x] The exception message MAY include only the *path-to-value* (e.g. `a.b[3].c`) where a float/NaN/INF was found.
    - [x] The exception message MUST NOT include the value itself.

Fixtures:
- [x] `framework/tools/spikes/fixtures/payloads_min/payloads.php` — mixed map/list payloads + forbidden floats scenario
  - [x] SHOULD include one sample derived from HTTP middleware config arrays (no floats)
  - [x] large nested lists/maps to stress normalization + stable encoding

Tests:
- [x] `framework/tools/spikes/payload/tests/PayloadNormalizerDeterministicOrderTest.php`
- [x] `framework/tools/spikes/payload/tests/StableJsonEncoderContractTest.php`
- [x] `framework/tools/spikes/payload/tests/FloatPolicyTest.php`
- [x] `framework/tools/spikes/payload/tests/RerunNoDiffTest.php`

#### Modifies

- [x] `framework/tools/spikes/_support/ErrorCodes.php` — register payload/json error codes:
  - [x] `CORETSIA_JSON_FLOAT_FORBIDDEN`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Errors

- [x] deterministic code: `CORETSIA_JSON_FLOAT_FORBIDDEN`

#### Security / Redaction

- [x] failing tests MUST NOT print raw payload values; only minimal diagnostics

#### Observability / Context & UoW

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] `RerunNoDiffTest` proves byte-identical output for same payload
- [x] `FloatPolicyTest` proves float rejection is deterministic

### Tests (MUST)

- Unit / Contract:
  - N/A
- Integration:
  - [x] `framework/tools/spikes/payload/tests/*` executed via `framework/tools/spikes/phpunit.spikes.xml`

### DoD (MUST)

- [x] deterministic normalization + stable json across OS
- [x] floats forbidden and enforced by tests
- [x] Out of scope:
  - [x] This epic MUST NOT introduce production runtime behavior (Phase 0 spikes/tooling only)
  - [x] This epic MUST NOT introduce plugin systems / extensibility frameworks
  - [x] This epic MUST NOT depend on `core/kernel` (unless explicitly stated in the epic)
- [x] `FloatPolicyTest` includes explicit cases for `NaN`, `INF`, `-INF` (nested too)

---

### 0.80.0 Deptrac generator prototype (MUST) [TOOLING]

---
type: tools
phase: 0
epic_id: "0.80.0"
owner_path: "framework/tools/spikes/deptrac/"

goal: "Prototype a deterministic/idempotent deptrac config generator with allowlist policy and graph artifacts."
provides:
- "Deterministic deptrac.yaml generation (rerun-no-diff)"
- "Allowlist policy validation (tests/tools only; never src)"
- "Deterministic graph artifacts (dot/svg/html) without absolute paths"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.20.0 — spikes sandbox exists
  - 0.40.0 — internal-toolkit exists
  - 0.50.0 — canonical DeterministicFile helper exists (EOL normalization + LF writes)

- Required deliverables (exact paths):
  - `framework/tools/spikes/fixtures/deptrac_min/` — deptrac fixtures exist
  - `framework/tools/spikes/_support/ErrorCodes.php` — shared codes exist

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `devtools/internal-toolkit`

Forbidden:

- `core/*`
- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- CLI:
  - `coretsia deptrac:graph` → `framework/packages/devtools/cli-spikes/src/Command/DeptracGraphCommand.php` (via 0.140.0 on top of 0.130.0; available only if `coretsia/cli-spikes` is installed)
- Artifacts:
  - writes: temp output dirs during tests (no repo writes except committed fixtures)

### Deliverables (MUST)

#### Creates

- [x] `framework/tools/spikes/deptrac/DeptracGenerate.php` — MUST:
  - [x] read package-index from fixture/mock under `framework/tools/spikes/fixtures/deptrac_min/`
  - [x] generate deterministic `deptrac.yaml` (rerun-no-diff)
- [x] `framework/tools/spikes/deptrac/GraphArtifactBuilder.php` — MUST:
  - [x] produce deterministic graph artifacts in formats: `dot`, `svg`, `html`
  - [x] MUST NOT embed absolute machine paths (relative/normalized only)
  - [x] outputs MUST NOT embed timestamps, tool versions, or absolute machine paths
  - [x] MUST NOT execute external binaries (Graphviz/dot or any shell commands)
  - [x] `svg` and `html` MUST be produced by pure PHP as a deterministic report (NOT a layout engine):
    - [x] `svg`/`html` content MUST be a stable representation (e.g. node/edge tables, adjacency list)
    - [x] MUST NOT attempt auto-layout or embed environment-dependent metadata
  - [x] Stable ordering (cemented; single-choice):
    - [x] Nodes MUST be emitted in ascending byte-order by node id/name using `strcmp`
    - [x] Edges MUST be emitted in ascending byte-order by `(from,to)` pair using `strcmp` on a canonical key like `from."->".to`
    - [x] Any per-node adjacency lists MUST also be sorted by target node id using `strcmp`

Fixtures:
- [x] `framework/tools/spikes/fixtures/deptrac_min/` — includes:
  - [x] at least 2 packages + 1 cycle scenario
  - [x] allowlist fixture scenario: tests/** only

- [x] All generated text artifacts MUST be LF-only and end with a final newline:
  - [x] `deptrac.yaml` generated bytes MUST be written via `DeterministicFile::writeTextLf()`
  - [x] graph artifacts (`dot`, `svg`, `html`) MUST be written via `DeterministicFile::writeTextLf()`
- [x] Any comparisons against expected_* fixtures (if present) MUST be EOL-stable:
  - [x] normalization to LF is mandatory before hashing/comparing

Tests:
- [x] `framework/tools/spikes/deptrac/tests/DeptracGeneratedConfigIsDeterministicTest.php`
- [x] `framework/tools/spikes/deptrac/tests/DeptracDetectsCycleTest.php`
- [x] `framework/tools/spikes/deptrac/tests/DeptracAllowlistPolicyTest.php`
- [x] `framework/tools/spikes/deptrac/tests/RerunGeneratorNoDiffTest.php`
- [x] `framework/tools/spikes/deptrac/tests/GraphArtifactBuilderDoesNotInvokeExternalProcessFunctionsTest.php` fails if process-exec functions are introduced:
  - [x] `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`
- [x] `framework/tools/spikes/deptrac/tests/GraphArtifactsContainNoAbsolutePathsTest.php`
  - [x] asserts generated `dot/svg/html` contain no absolute paths, including:
    - [x] POSIX absolute (`/home/...`, `/Users/...`) patterns
    - [x] Windows drive-letter absolute (`(?i)\b[A-Z]:(\\|/)...`) patterns
    - [x] Windows UNC absolute (`\\server\share\...`) patterns

#### Modifies

- [x] `framework/tools/spikes/_support/ErrorCodes.php` — register deptrac spike error codes:
  - [x] `CORETSIA_DEPTRAC_CYCLE_DETECTED`
  - [x] `CORETSIA_DEPTRAC_ALLOWLIST_INVALID`
  - [x] `CORETSIA_DEPTRAC_GRAPH_ARTIFACT_INVALID`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Errors

- [x] deterministic codes for cycle detection / invalid allowlist via `framework/tools/spikes/_support/ErrorCodes.php`

#### Security / Redaction

- [x] artifacts MUST NOT embed absolute machine paths (normalize to relative paths)

#### Observability / Context & UoW

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] `DeptracGeneratedConfigIsDeterministicTest` + `RerunGeneratorNoDiffTest` prove rerun-no-diff
- [x] `DeptracAllowlistPolicyTest` proves src/** cannot be allowlisted

### Tests (MUST)

- Unit / Contract:
  - N/A
- Integration:
  - [x] `framework/tools/spikes/deptrac/tests/*` executed via `framework/tools/spikes/phpunit.spikes.xml`

### DoD (MUST)

- [x] deterministic/idempotent generator
- [x] allowlist policy enforced
- [x] graph artifacts produced deterministically
- [x] Out of scope:
  - [x] This epic MUST NOT introduce production runtime behavior (Phase 0 spikes/tooling only)
  - [x] This epic MUST NOT introduce plugin systems / extensibility frameworks
  - [x] This epic MUST NOT depend on `core/kernel` (unless explicitly stated in the epic)
- [x] `GraphArtifactBuilderDoesNotInvokeExternalProcessFunctionsTest` fails if process-exec functions are introduced:
  - [x] `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, `popen`

---

### 0.90.0 Two-phase config merge + directives + explain prototype (MUST) [TOOLING]

---
type: tools
phase: 0
epic_id: "0.90.0"
owner_path: "framework/tools/spikes/config_merge/"

goal: "Cement deterministic two-phase config merge, directive semantics, and safe explain trace."
provides:
- "Precedence order invariant: app > module > skeleton > defaults"
- "Directive semantics: per-file parse/validate/normalize (allowlist + typing), applied deterministically at merge-time"
- "Deterministic + safe explain trace without leaking secret values"
- "Two-phase merge semantics cemented: Phase A = directive parse/validate/normalize; Phase B = merge by precedence with directive application; explain derived deterministically"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.20.0 — spikes sandbox + fixtures/support exist
  - 0.40.0 — internal-toolkit exists

- Required deliverables (exact paths):
  - `framework/tools/spikes/fixtures/repo_min/` — minimal repo fixtures exist
  - `framework/tools/spikes/fixtures/http_middleware_catalog.php` — middleware catalog exists
  - `framework/tools/spikes/_support/ErrorCodes.php` — shared codes exist

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `devtools/internal-toolkit`

Forbidden:

- `core/*`
- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- CLI:
  - `coretsia spike:config:debug --key=<dot.key>` → `framework/packages/devtools/cli-spikes/src/Command/SpikeConfigDebugCommand.php` (via 0.140.0 on top of 0.130.0; available only if `coretsia/cli-spikes` is installed)

### Deliverables (MUST)

#### Creates

- [x] `framework/tools/spikes/config_merge/DirectiveProcessor.php` — directives allowlist + typing + exclusive-level rule
  - [x] Allowed directives (allowlist, cemented): `@append`, `@prepend`, `@remove`, `@merge`, `@replace`
  - [x] Reserved namespace guard (single-choice):
    - [x] Any config key that starts with `@` is reserved for directives only.
    - [x] Only the directive allowlist keys are permitted (`@append`, `@prepend`, `@remove`, `@merge`, `@replace`).
    - [x] Any other `@*` key MUST fail deterministically with `CORETSIA_CONFIG_RESERVED_NAMESPACE_USED`
      (even if it appears “as data”).
    - [x] Directive keys MUST NOT appear as normal data keys in the final merged config output.
  - [x] Error precedence (single-choice; cemented):
    - [x] If any unknown `@*` key exists at any level → MUST fail with `CORETSIA_CONFIG_RESERVED_NAMESPACE_USED`
      (even if the same level also violates mixed-level or multi-directive rules).
    - [x] Else if the exclusive-level rule is violated (mixed directive + normal keys OR multiple directives at one level)
      → MUST fail with `CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL`.
    - [x] Else if a directive application encounters a container kind mismatch (list vs map vs scalar rules)
      → MUST fail with `CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH`.
  - [x] Exclusive-level rule (single-choice):
    - [x] If a map contains any `@*` key at a given level, then:
      - [x] ALL keys at that level MUST be directives from the allowlist (no mixing with normal keys)
      - [x] EXACTLY ONE directive key is allowed at that level (multiple directives at the same level MUST fail)
    - [x] Failure code mapping for exclusive-level violations (single-choice):
      - [x] both cases MUST fail with `CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL`:
        - [x] mixing directive keys with normal keys at the same level
        - [x] using multiple directive keys at the same level (e.g. `@append` + `@remove`)
  - [x] Typing rules (single-choice; with empty-array rule cemented):
    - [x] `@append`, `@prepend`, `@remove`: value MUST be a list
      - [x] empty array `[]` is allowed and treated as an empty list
    - [x] `@merge`: value MUST be a map
      - [x] empty array `[]` is allowed and treated as an empty map (see empty-array rule below)
    - [x] `@replace`: value MAY be any scalar/list/map
  - [x] List-vs-map classification is single-choice (cemented; empty-array rule explicit):
    - [x] For non-empty arrays:
      - [x] A "list" MUST be detected via `array_is_list($value) === true`
      - [x] A "map" MUST be detected via `array_is_list($value) === false`
    - [x] Empty array rule (cemented, because PHP cannot distinguish empty list vs empty map):
      - [x] `[]` MUST be accepted as a valid empty container for BOTH “list-required” and “map-required” contexts.
      - [x] The required context determines interpretation:
        - [x] for list directives → interpret `[]` as empty list
        - [x] for `@merge` → interpret `[]` as empty map
  - [x] Directive semantics (single-choice; normative):
    - [x] Two-phase semantics (single-choice; normative):
      - [x] Phase A (per-file): directives are parsed, allowlisted, and type-validated, producing a normalized representation (no base value required).
      - [x] Phase B (merge-time): the normalized directive representation is applied deterministically when merging higher-precedence config over the lower-precedence base value.
      - [x] Rationale: list directives (`@append/@prepend/@remove`) require a resolved base list; therefore they MUST be applied only when the base is known during merge.
    - [x] `@append` (list):
      - [x] Requires the target value (after Phase B resolution) to be a list, otherwise MUST fail with `CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH`.
      - [x] Semantics: result = `base_list` + `directive_list` (preserve order; no implicit dedup).
    - [x] `@prepend` (list):
      - [x] Requires the target value to be a list, otherwise MUST fail with `CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH`.
      - [x] Semantics: result = `directive_list` + `base_list` (preserve order; no implicit dedup).
    - [x] `@remove` (list):
      - [x] Requires the target value to be a list, otherwise MUST fail with `CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH`.
      - [x] Semantics: remove items from `base_list` using strict equality (`===`) matching against each item in `directive_list`;
        removal is applied in the order of `directive_list` and is idempotent.
    - [x] `@merge` (map):
      - [x] Requires the target value to be a map, otherwise MUST fail with `CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH`.
      - [x] Semantics: shallow merge by keys where directive keys override base keys at the same level.
      - [x] Nested maps are merged recursively; lists are replaced (no implicit list merge) unless a list directive is used.
    - [x] `@replace` (any):
      - [x] Semantics: replaces the target value entirely with the directive value (scalar/list/map).
  - [x] Missing-base semantics (single-choice; cemented):
    - [x] When applying a directive at merge-time (Phase B), if the lower-precedence/base value is missing at the target key:
      - [x] for list directives `@append`, `@prepend`, `@remove` → base is treated as an empty list `[]`
      - [x] for map directive `@merge` → base is treated as an empty map `[]` (interpreted as map by the empty-array rule)
      - [x] for `@replace` → base is ignored (replacement is unconditional)
    - [x] Type-mismatch semantics remain strict for non-empty containers:
      - [x] If the base value exists and is a non-empty array of the wrong kind (list vs map), it MUST fail with `CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH`.
    - [x] Unknown directive key (any `@*` not in the allowlist) MUST fail deterministically with
      `CORETSIA_CONFIG_RESERVED_NAMESPACE_USED` (single-choice; no separate code).
- [x] `framework/tools/spikes/config_merge/ConfigMerger.php` — deterministic merge runner for scenarios
  - [x] Deterministic map key ordering is single-choice (cemented):
    - [x] Any intermediate and final "map" structures produced by merge MUST be normalized by sorting keys
      using byte-order comparison (`strcmp`) at each map level.
    - [x] Lists MUST preserve element order and MUST NOT be re-sorted.
    - [x] Sorting MUST be locale-independent and MUST NOT rely on environment (`LC_ALL`, `setlocale`, etc.).
- [x] `framework/tools/spikes/config_merge/ConfigExplainer.php` — deterministic + safe trace (`sourceType,file,keyPath,directiveApplied?`)
  - [x] Explain trace ordering (single-choice; cemented):
    - [x] The explainer MUST produce a deterministic list of trace records.
    - [x] Trace records MUST be sorted by:
      1) `keyPath` ascending by byte-order (`strcmp`)
      2) then `precedenceRank` ascending (lower-precedence first), where precedence is fixed as:
         `defaults < skeleton < module < app` (or the exact precedence model used by the spike)
      3) then `sourceFile` ascending by byte-order (`strcmp`)
    - [x] No locale-dependent ordering is allowed.

Fixtures:
- [x] `framework/tools/spikes/config_merge/tests/fixtures/scenarios.php` — 15–30 scenarios matrix (precedence + directives + reserved namespace guard)
  - [x] MUST include middleware-slot scenarios (keys are cemented):
    - [x] `http.middleware.system_pre`
    - [x] `http.middleware.system`
    - [x] `http.middleware.system_post`
    - [x] `http.middleware.app_pre`
    - [x] `http.middleware.app`
    - [x] `http.middleware.app_post`
    - [x] `http.middleware.route_pre`
    - [x] `http.middleware.route`
    - [x] `http.middleware.route_post`
    - [x] `http.middleware.auto` (bool)
  - [x] MUST include directive case scenarios (cemented examples):
    - [x] `@append` adds `Debugbar` middleware into a slot list
    - [x] `@remove` removes `Maintenance` middleware from a slot list
    - [x] `@prepend` adds `ErrorHandling` middleware at the beginning of a slot list
    - [x] `@replace` sets `http.middleware.auto` => false (scalar replace scenario)
  - [x] Scenarios MUST include reserved namespace guard cases:
    - [x] `["@foo" => "bar"]` anywhere in a config map MUST fail with `CORETSIA_CONFIG_RESERVED_NAMESPACE_USED`
    - [x] `["@append" => [...]]` MUST be accepted only when typing rules are satisfied (list directive)

Tests:
- [x] `framework/tools/spikes/config_merge/tests/ConfigPrecedenceMatrixDataDrivenTest.php`
- [x] `framework/tools/spikes/config_merge/tests/DirectivesTypingRulesTest.php`
  - [x] empty-array rule is cemented:
    - [x] `@append` with `[]` MUST be accepted (empty list)
    - [x] `@merge` with `[]` MUST be accepted (empty map by rule)
- [x] `framework/tools/spikes/config_merge/tests/ExplainTraceDeterministicTest.php`
- [x] `framework/tools/spikes/config_merge/tests/ExplainTraceSafeNoSecretsTest.php`
- [x] `framework/tools/spikes/config_merge/tests/RerunNoDiffTest.php`

#### Modifies

- [x] `framework/tools/spikes/_support/ErrorCodes.php` — register config merge error codes:
  - [x] `CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL`
  - [x] `CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH`
  - [x] `CORETSIA_CONFIG_RESERVED_NAMESPACE_USED`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [x] Explain trace MUST be safe (no secrets/PII)

#### Errors

- [x] deterministic exception codes (via `framework/tools/spikes/_support/ErrorCodes.php`) for:
  - [x] unknown directive
  - [x] mixed directive+normal keys at same level
  - [x] type mismatch (list-only vs map-only)

#### Security / Redaction

- [x] Never print secret values; only key names, hashes/len when needed

#### Context & UoW

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] data-driven precedence matrix test proves ordering invariant
- [x] explain trace tests prove determinism + no secrets

### Tests (MUST)

- Unit / Contract:
  - N/A
- Integration:
  - [x] `framework/tools/spikes/config_merge/tests/*` executed via `framework/tools/spikes/phpunit.spikes.xml`

### DoD (MUST)

- [x] precedence confirmed by data-driven test
- [x] directives per-file before merge confirmed by tests
- [x] explain trace deterministic + safe
- [x] middleware scenarios exist and cover append/remove/replace flows
- [x] Out of scope:
  - [x] This epic MUST NOT introduce production runtime behavior (Phase 0 spikes/tooling only)
  - [x] This epic MUST NOT introduce plugin systems / extensibility frameworks
  - [x] This epic MUST NOT depend on `core/kernel` (unless explicitly stated in the epic)
- [x] Canonical paths are cemented (exact paths only):
  - [x] `framework/tools/spikes/config_merge/**` is the only valid location
  - [x] PascalCase variants (e.g. `framework/tools/spikes/ConfigMerge/**`) MUST NOT exist

---

### 0.100.0 Composer workspace SPOF prototype: package-index + repositories sync (MUST) [TOOLING]

---
type: tools
phase: 0
epic_id: "0.100.0"
owner_path: "framework/tools/spikes/workspace/"

goal: "Prototype deterministic package-index + safe/idempotent composer repositories sync with backups."
provides:
- "Deterministic package-index (SPOF for many packages)"
- "Managed-only repositories sync (user-owned entries untouched) + backups"
- "New-package atomic workflow prototype on fixture tree"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.20.0 — spikes sandbox + fixtures/support exist
  - 0.40.0 — internal-toolkit exists
  - 0.50.0 — canonical DeterministicFile helper exists (EOL normalization + LF writes)

- Required deliverables (exact paths):
  - `framework/tools/spikes/fixtures/workspace_min/` — workspace fixtures root exists
  - `framework/tools/spikes/_support/ErrorCodes.php` — shared codes exist

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `devtools/internal-toolkit`

Forbidden:

- `core/*`
- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- CLI:
  - `coretsia workspace:sync --dry-run` → `framework/packages/devtools/cli-spikes/src/Command/WorkspaceSyncDryRunCommand.php` (via 0.140.0 on top of 0.130.0; available only if `coretsia/cli-spikes` is installed)
  - `coretsia workspace:sync --apply` → `framework/packages/devtools/cli-spikes/src/Command/WorkspaceSyncApplyCommand.php` (via 0.140.0 on top of 0.130.0; available only if `coretsia/cli-spikes` is installed)

- Artifacts:
  - reads: golden fixtures under `framework/tools/spikes/fixtures/workspace_min/expected_*`
  - writes: test temp output dirs only (no repo writes; fixtures are read-only)

### Deliverables (MUST)

#### Creates

- [x] `framework/tools/spikes/workspace/PackageIndexBuilder.php` — MUST:
  - [x] scan pattern: `framework/packages/*/*/composer.json` (on fixture tree)
  - [x] emit deterministic package-index entries with shape:
    - [x] `{slug, layer, path, composerName, psr4, kind, moduleClass?}`
  - [x] Entry key insertion order is cemented (single-choice) to guarantee deterministic bytes and strict comparisons:
    - [x] `slug`, `layer`, `path`, `composerName`, `psr4`, `kind`, `moduleClass` (if present)
  - [x] Stable ordering is single-choice:
    - [x] package-index entries MUST be sorted by normalized `path` ascending using byte-order comparison (`strcmp`)
- [x] `framework/tools/spikes/workspace/WorkspacePolicy.php` — MUST:
  - [x] define canonical managed marker: `coretsia_managed=true`
  - [x] define managed repositories block invariants (single-choice):
    - [x] `composer.json.repositories` MUST be a JSON list (`list<map>`) when present
    - [x] A “managed entry” is any repository item map where `coretsia_managed === true`
    - [x] If any managed entry exists, then ALL managed entries MUST form a single contiguous block inside the repositories list
      - [x] If managed entries are interleaved with user-owned entries → MUST fail with `CORETSIA_WORKSPACE_MANAGED_BLOCK_INVALID`
    - [x] User-owned repository entries (not managed) MUST be preserved deterministically:
      - [x] their relative order MUST NOT change
      - [x] their item maps MUST NOT be normalized/re-key-sorted (key insertion order preserved as parsed)
      - [x] values MUST remain identical (no semantic changes)
    - [x] Note (non-normative): the canonical encoder MAY rewrite whitespace globally; rerun-no-diff is guaranteed once the file is already in canonical format.
    - [x] Canonical key insertion order for **managed repository entry maps** is cemented (single-choice):
      - [x] required keys order: `type`, `url`, `coretsia_managed`
      - [x] if optional keys exist (e.g. `options`), they MUST appear between `url` and `coretsia_managed`:
        - [x] `type`, `url`, `options`, `coretsia_managed`
      - [x] When rebuilding managed entries, the sync MUST emit keys in the canonical order above.
      - [x] User-owned entries remain untouched (original key order preserved as parsed).
    - [x] The managed block output order is deterministic (single-choice):
      - [x] managed entries MUST be sorted by normalized `url` ascending using byte-order comparison (`strcmp`)
      - [x] URL normalization for sorting is single-choice: replace `\` with `/` (no other normalization)
      - [x] If `url` is missing or not a string on a managed entry → MUST fail with `CORETSIA_WORKSPACE_MANAGED_BLOCK_INVALID`
  - [x] define deterministic ordering rules for managed repositories block
- [x] `framework/tools/spikes/workspace/ComposerRepositoriesSync.php` — MUST:
  - [x] правильна точка виклику `WorkspacePolicy::rebuildManagedRepositoriesBlockIfPresent($composerJson)` — так гарантовано виконується “when present” без ризику додавання нового ключа або фейлу через null
  - [x] update ONLY entries marked with `coretsia_managed=true`
  - [x] leave user-owned entries untouched
  - [x] MUST parse input `composer.json` with `JSON_THROW_ON_ERROR` into associative arrays to preserve input key insertion order
  - [x] MUST validate managed repositories block invariants as defined in `WorkspacePolicy`
    - [x] any invariant violation → fail with `CORETSIA_WORKSPACE_MANAGED_BLOCK_INVALID`
  - [x] MUST rebuild ONLY the contiguous managed repositories block:
    - [x] managed entries are re-sorted according to `WorkspacePolicy` rules
    - [x] user-owned entries outside the managed block are preserved as-is and in the same order
  - [x] MUST write the updated JSON using the single canonical mechanism:
    - [x] `ComposerJsonCanonicalizer::encodeCanonical(...)`
  - [x] MUST write files via `DeterministicFile::writeTextLf()` (single canonical writer)
  - [x] Backups are mandatory before any on-disk change (single-choice):
    - [x] If (and only if) the synced output bytes differ from the current file bytes, the sync MUST:
      1) [x] read the current file bytes as-is (no normalization)
      2) [x] write a deterministic side-by-side backup first (per the naming policy: `.bak`, `.bak.1`, ...)
      - [x] the backup MUST be written via `DeterministicFile::writeBytesExact($backupPath, $originalBytes)`
      3) [x] then write the updated `composer.json` (canonical format) via `DeterministicFile::writeTextLf()`
    - [x] If there is no change (idempotent run), the sync MUST NOT create a new backup file.
    - [x] Backup bytes MUST match the exact pre-sync bytes (no normalization or rewriting).
- [x] `framework/tools/spikes/workspace/ComposerJsonCanonicalizer.php` — single canonical composer.json writer:
  - [x] MUST expose: `encodeCanonical(array $composerJson): string`
  - [x] MUST implement exactly the canonical encoder pipeline defined in `Cross-cutting → Security / Redaction`
  - [x] MUST NOT reorder keys globally; it operates on the already-prepared associative arrays (insertion order preserved)
  - [x] MUST fail deterministically with `CORETSIA_WORKSPACE_COMPOSER_JSON_ENCODE_FAILED` if canonicalization invariant is violated
- [x] `framework/tools/spikes/workspace/NewPackageWorkflow.php` — atomic workflow prototype (fixture tree):
  - [x] MUST implement a single deterministic mechanism to model “new package creation” on the fixture workspace:
    - [x] prepares package directory skeleton under `framework/packages/<layer>/<slug>/`
    - [x] ensures the new package has a minimal `composer.json` (deterministic bytes; LF + final newline)
    - [x] updates package-index via `PackageIndexBuilder` (no ad-hoc scanning logic)
    - [x] updates managed repositories blocks via `ComposerRepositoriesSync` (managed-only; user-owned untouched)
  - [x] Atomicity policy (single-choice; enforceable in tests):
    - [x] workflow MUST build changes in a temp dir and apply via a deterministic rename-only swap per changed file:
      - move existing target aside to a deterministic backup path (`rename(target -> backup)`)
      - install new bytes with exactly one deterministic move (`rename(staged -> target)`)
      - MUST NOT perform any in-place writes to workspace files during apply or rollback
        (this is required for cross-platform correctness; Windows may not support atomic replace via single rename into an existing target)
    - [x] on failure, the fixture tree MUST remain unchanged after rollback:
      - rollback restores the original pre-run bytes using rename/move only (no byte rewrites)
      - any newly created artifacts (e.g. new package directory / created layer dir) are removed
  - [x] MUST NOT call `json_encode(...)` directly (uses `ComposerJsonCanonicalizer::encodeCanonical(...)` only).
- [x] No implicit CWD (single-choice; required for determinism):
  - [x] All workspace spike components (`PackageIndexBuilder`, `ComposerRepositoriesSync`, `NewPackageWorkflow`, etc.)
    MUST NOT rely on the current working directory.
  - [x] Each component MUST accept an explicit root path argument (e.g. `frameworkRoot` or `repoRoot`) and resolve all
    filesystem paths from that root only.
  - [x] Tests MUST exercise execution from a non-root working directory to prove this invariant (optional but recommended).

Fixtures:
- [x] `framework/tools/spikes/fixtures/workspace_min/framework/composer.json`
- [x] `framework/tools/spikes/fixtures/workspace_min/skeleton/composer.json`
- [x] `framework/tools/spikes/fixtures/workspace_min/framework/packages/**/composer.json`
- [x] `framework/tools/spikes/fixtures/workspace_min/expected_package_index.php`
- [x] `framework/tools/spikes/fixtures/workspace_min/expected_composer_framework.json`
- [x] `framework/tools/spikes/fixtures/workspace_min/expected_composer_skeleton.json`
- [x] Fixtures `expected_composer_framework.json` and `expected_composer_skeleton.json` MUST be LF-only and end with final newline

Tests:
- [x] `framework/tools/spikes/workspace/tests/PackageIndexDeterministicTest.php`
- [x] `framework/tools/spikes/workspace/tests/ComposerSyncUpdatesManagedOnlyTest.php`
- [x] `framework/tools/spikes/workspace/tests/ComposerSyncIdempotentNoDiffTest.php`
- [x] `framework/tools/spikes/workspace/tests/ComposerSyncWritesBackupsTest.php`
- [x] `framework/tools/spikes/workspace/tests/NewPackageAtomicWorkflowPrototypeTest.php`
- [x] `framework/tools/spikes/workspace/tests/ComposerJsonCanonicalFormatTest.php`
  - [x] asserts output is LF-only and ends with final newline
  - [x] asserts output is pretty-printed with 2 spaces
  - [x] asserts non-managed keys ordering is preserved (input order == output order)
  - [x] asserts managed repositories block ordering matches `WorkspacePolicy`
- [x] `framework/tools/spikes/workspace/tests/NoImplicitCwdEvidenceTest.php`

#### Modifies

- [x] `framework/tools/spikes/_support/ErrorCodes.php` — register workspace spike error codes:
  - [x] `CORETSIA_WORKSPACE_MANAGED_BLOCK_INVALID`
  - [x] `CORETSIA_WORKSPACE_BACKUP_PATH_MISSING` semantics (cemented):
    - [x] MUST be used when the target `composer.json` file to be synced does not exist at the expected path
      (there is nothing to backup and nothing to update).
    - [x] MUST NOT be used for “cannot write backup” IO failures (those MUST fail with parse/encode/write codes as applicable).
  - [x] `CORETSIA_WORKSPACE_COMPOSER_JSON_PARSE_FAILED`
  - [x] `CORETSIA_WORKSPACE_COMPOSER_JSON_ENCODE_FAILED`
- [x] `framework/tools/gates/internal_toolkit_no_dup_gate.php` — extend allowlist (single-choice; explicit):
  - [x] allow exactly one exception file that MAY call `json_encode(...)`:
    - [x] `spikes/workspace/ComposerJsonCanonicalizer.php`
      - [x] Path is evaluated as **tools-root-relative**.
      - [x] In the real repo (default tools root = `framework/tools`) this resolves to:
        `framework/tools/spikes/workspace/ComposerJsonCanonicalizer.php`.
  - [x] This exception is path-bound and MUST NOT be generalized to any other file.
- [x] `framework/tools/spikes/tests/InternalToolkitNoDupGateDetectsDuplicationTest.php` — extend contract evidence for the 0.100 allowlist:
  - [x] given a temp scan root containing an allowlisted file at
    `spikes/workspace/ComposerJsonCanonicalizer.php` that calls `json_encode(...)`
    → gate MUST pass (path-bound allowlist exception added by this epic).

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Errors

- [x] deterministic codes for invalid managed block / missing backups path / parse failures

#### Security / Redaction

- [x] Never output full composer.json content on error; only safe diagnostics:
  - [x] path MUST be normalized repo-relative (forward slashes), MUST NOT be absolute
  - [x] short fixed reason (no file contents)
- [x] Generated JSON/text outputs MUST be deterministic on disk:
  - [x] Composer JSON canonical byte format (single-choice):
    - [x] Encoding: UTF-8, no BOM
    - [x] Newlines: LF-only (`\n`) + final newline (file MUST end with `\n`)
    - [x] Pretty format: JSON MUST be pretty-printed with **2 spaces** indentation.
      - [x] The canonical indentation width is **2** and MUST NOT rely on PHP `JSON_PRETTY_PRINT` indentation width.
    - [x] Escaping policy: MUST use unescaped slashes and unicode (`/` and UTF-8 chars MUST NOT be escaped)
    - [x] Ordering policy (critical to “managed-only”):
      - [x] For all non-managed parts of composer.json, key order MUST be preserved as read from input JSON (no global `ksort`)
      - [x] Only the managed repositories block is rebuilt/reordered deterministically according to `WorkspacePolicy` (and ONLY that block)
    - [x] Canonical encoder pipeline (single-choice; deterministic and 2-spaces):
      1) [x] Encode with PHP JSON encoder (stable bytes, 4-space pretty baseline):
      - [x] MUST encode with:
        - [x] `json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)`
      2) [x] Normalize EOL to LF:
      - [x] replace `\r\n` and `\r` with `\n`
      3) [x] Rewrite indentation **only at the beginning of each line** (no touching JSON string contents):
      - [x] For each line, let `N` be the count of leading space characters before the first non-space char.
      - [x] `N` MUST be divisible by 4 (otherwise: deterministic failure `CORETSIA_WORKSPACE_COMPOSER_JSON_ENCODE_FAILED`).
      - [x] Replace that leading prefix with exactly `N / 2` spaces.
      - [x] This operation is applied line-by-line and MUST NOT modify any non-leading characters.
      4) [x] Ensure final newline:
      - [x] if the output does not end with `\n`, append exactly one `\n`
    - [x] No extra noise: MUST NOT add timestamps, tool versions, or absolute paths into JSON
  - [x] MUST be written via `DeterministicFile::writeTextLf()` to prevent OS EOL drift
- [x] Backups naming MUST be deterministic (single-choice):
  - [x] Backup location is single-choice:
    - [x] backups MUST be written in the same directory as the target file (side-by-side with the target `composer.json`)
    - [x] backup file name is derived from the target file name by appending the deterministic suffix (`.bak`, `.bak.1`, ...)
  - [x] First backup uses suffix `.bak`
  - [x] If `.bak` already exists, use `.bak.1`, then `.bak.2`, … (smallest available N)
  - [x] MUST NOT use timestamps/random suffixes

#### Observability / Context & UoW

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] `ComposerSyncIdempotentNoDiffTest` proves rerun-no-diff
- [x] `ComposerSyncUpdatesManagedOnlyTest` proves user-owned repos untouched
- [x] `ComposerSyncWritesBackupsTest` proves backups written before changes

### Tests (MUST)

- Unit / Contract:
  - N/A
- Integration:
  - [x] `framework/tools/spikes/workspace/tests/*` executed via `framework/tools/spikes/phpunit.spikes.xml`

### DoD (MUST)

- [x] deterministic package-index
- [x] sync deterministic + idempotent
- [x] user-owned repositories untouched
- [x] backups always written before changes
- [x] Out of scope:
  - [x] This epic MUST NOT introduce production runtime behavior (Phase 0 spikes/tooling only)
  - [x] This epic MUST NOT introduce plugin systems / extensibility frameworks
  - [x] This epic MUST NOT depend on `core/kernel` (unless explicitly stated in the epic)

---

### 0.110.0 Publishing rails (GitHub/Packagist) (MUST) [TOOLING]

---
type: tools
phase: 0
epic_id: "0.110.0"
owner_path: ".github/workflows/"

goal: "Establish a deterministic and repeatable release process: Git tags → GitHub Release, with Packagist publishing
policy documented."
provides:
- "Canonical release triggers and invariants (tag format, release artifacts policy)"
- "Minimal GitHub Actions release workflow rails (no secrets leakage, reproducible steps)"
- "Deterministic split-plan evidence artifact (dry-run only): plan+hash, rerun-no-diff verified in CI"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced:
- "ci/split-plan.json (workflow artifact; deterministic bytes; rerun-no-diff evidence)"
- "ci/split-plan.sha256 (workflow artifact; sha256(split-plan.json))"

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — legal/docs baseline exists
  - PRELUDE.20.0 — packaging strategy is locked (publishable units defined)

- Required deliverables (exact paths):
  - `CHANGELOG.md` — release notes source exists
  - `UPGRADE.md` — upgrade notes policy entrypoint exists
  - `CONTRIBUTING.md` — contributor workflow exists

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (repo-level tooling)

Forbidden:

- N/A

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- CI (GitHub Actions):
  - workflow: `.github/workflows/release.yml`
  - triggers:
    - `push` tags matching `v*` (canonical release tag)
    - `workflow_dispatch` (manual dry-run / validation mode)

### Deliverables (MUST)

#### Creates

- [x] `.github/workflows/release.yml` — release rails (tag → GitHub Release)
- [x] `docs/guides/releasing.md` — canonical release procedure (human process + invariants)

- [x] `.github/scripts/split-plan.php` — deterministic split plan generator (dry-run; no push)
- [x] `.github/scripts/split-plan.schema.md` — normative shape/spec for split-plan.json (normative; single-choice; schema is the source of truth)

#### Modifies

- [x] `CONTRIBUTING.md` — add “Release process” section that points to `docs/guides/releasing.md`
- [x] `README.md` — add “Releasing” link to `docs/guides/releasing.md`

#### Configuration (keys + defaults)

- [x] Workflow invariants (documented in `docs/guides/releasing.md`, reflected in workflow):
  - [x] Tag format: `vMAJOR.MINOR.PATCH`
  - [x] Source of release notes: `CHANGELOG.md` (human-maintained)
  - [x] Publishing MUST be source-only (no built artifacts) in Phase 0 / early phases
- [ ] Packagist: split repositories (coretsia/<layer>-<slug>) MUST be connected in Packagist via GitHub integration (auto-update enabled); no manual publish step in the canonical procedure (blocked until first public evidence).

#### Wiring / DI tags

N/A

#### Artifacts / outputs (MUST)

- CI evidence artifacts (workflow artifacts; MUST NOT be committed):
  - `ci/split-plan.json` — deterministic split plan manifest (normative schema: `.github/scripts/split-plan.schema.md`)
  - `ci/split-plan.sha256` — sha256 of `ci/split-plan.json`

##### split-plan.json shape (normative; single-choice)

The only normative shape/spec is locked in:
- `.github/scripts/split-plan.schema.md`

This epic MUST NOT duplicate the schema fields inline.
Any change to `ci/split-plan.json` shape MUST be done by updating the schema doc (and the generator) together.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [x] Workflow logs MUST NOT print secrets/tokens
- [x] Workflow output MUST be deterministic (no timestamps/randomness in generated text)

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] tokens, cookies, session ids, private keys, `.env` contents
- [x] Allowed:
  - [x] `hash(value)` / `len(value)` for diagnostics, if needed

#### Context & UoW

N/A

#### Errors

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] Add a workflow “dry-run” mode (via `workflow_dispatch`) that validates (single-choice):
  - [x] Tag format: `vMAJOR.MINOR.PATCH`
    - [x] Source of tag in `push` tag workflow: the ref name (single canonical source)
    - [x] Source of tag in `workflow_dispatch`: the explicit input parameter (single canonical source)
  - [x] Required files exist:
    - [x] `CHANGELOG.md`
    - [x] `docs/guides/releasing.md`
    - [x] `UPGRADE.md`
    - [x] `CONTRIBUTING.md`
    - [x] `.github/scripts/split-plan.php`
    - [x] `.github/scripts/split-plan.schema.md`
  - [x] This validation MUST fail if any required item is missing/invalid.
- [x] Add a workflow dry-run step that generates `ci/split-plan.json` (single-choice):
  - [x] Package discovery MUST be deterministic:
    - [x] scan `framework/packages/*/*/composer.json` and derive `<layer>/<slug>` from path
    - [x] validate `composer.json:name` MUST equal `coretsia/<layer>-<slug>`
    - [x] sort packages lexicographically by `package_id`
  - [x] Rerun-no-diff MUST be proven in a single workflow run:
    - [x] generate split plan twice (`split-plan.A.json`, `split-plan.B.json`)
    - [x] byte-compare (or sha256 compare) MUST match; otherwise fail
  - [x] `ci/split-plan.json` MUST conform to the normative schema in `.github/scripts/split-plan.schema.md` (single source of truth)
  - [x] Upload artifacts:
    - [x] `ci/split-plan.json` (use the first output as canonical)
    - [x] `ci/split-plan.sha256`

### Tests (MUST)

- Integration:
  - [x] GitHub Actions workflow exists and can be triggered manually (`workflow_dispatch`) to validate required release docs/files
- Unit / Contract / Gates:
  - N/A

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] Preconditions satisfied (no forward references)
- [x] Release workflow does **not** depend on framework tooling (`framework/*`) or runtime artifacts
- [x] Release process is documented and linked from README + CONTRIBUTING
- [x] `docs/guides/releasing.md` MUST explicitly define Packagist publishing policy (single-choice):
  - [x] **Automatic publishing is REQUIRED (policy):** Packagist MUST be configured to auto-update from split repositories on tag push (blocked until public evidence).
  - [x] Trigger mechanism: `push` tag `vMAJOR.MINOR.PATCH` → GitHub Release → Packagist auto-update (via Packagist GitHub integration)
  - [x] Invariants:
    - [x] Publishing MUST be source-only (no built artifacts)
    - [x] Repo tag is the single source of version truth
    - [x] Manual “Update” in Packagist UI MUST NOT be required for a normal release
- [x] Security: workflow logs do not leak secrets; no `.env`/tokens printed
- [x] Out of scope:
  - [x] This epic MUST NOT introduce production runtime behavior (Phase 0 spikes/tooling only)
  - [x] This epic MUST NOT introduce plugin systems / extensibility frameworks
  - [x] This epic MUST NOT depend on `core/kernel` (unless explicitly stated in the epic)
- [x] Split plan rails exist (dry-run only):
  - [x] `ci/split-plan.json` + `ci/split-plan.sha256` are produced as workflow artifacts
  - [x] rerun-no-diff is enforced (double generation in one run must match byte-for-byte)
- [ ] Packagist integration checkbox remains `[ ]` with status: blocked until first public release
  - [ ] No claim of Packagist verification is allowed without public evidence (tag appears in Packagist without manual Update)

---

### 0.120.0 CLI ports in `coretsia/contracts` (MUST) [CONTRACTS]

---
type: package
phase: 0
epic_id: "0.120.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Introduce canonical CLI ports in coretsia/contracts to avoid package-local cross-package interfaces."
provides:
- "CLI command port (CommandInterface)"
- "CLI output port (OutputInterface) for deterministic/redaction-enforced output adapters"
- "CLI input port (InputInterface) for raw tokens without freezing parsing semantics"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.20.0 — packaging strategy locked
  - core/contracts package exists (composer + autoload)

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/composer.json`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- `platform/*`
- `integrations/*`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [x] `framework/packages/core/contracts/src/Cli/Input/InputInterface.php` — raw tokens only (no parsing semantics frozen):
  - [x] `tokens(): array` (returns `list<string>`)
- [x] `framework/packages/core/contracts/src/Cli/Output/OutputInterface.php` — deterministic/redaction-safe output port:
  - [x] `text(string $text): void`
  - [x] `json(array $payload): void`
  - [x] `error(string $code, string $message): void` (no secrets; output impl enforces redaction)
- [x] `framework/packages/core/contracts/src/Cli/Command/CommandInterface.php` — command port for `coretsia/cli`:
  - [x] `name(): string`
  - [x] `run(InputInterface $input, OutputInterface $output): int`

#### Modifies

N/A

### Cross-cutting

N/A

### Tests (MUST)

N/A (contracts-only port types)

### DoD (MUST)

- [x] Deliverables complete (creates), paths exact
- [x] No forbidden deps introduced into core/contracts

---

### 0.130.0 Minimal `coretsia` CLI base (prod-safe) (MUST) [TOOLING]

---
type: package
phase: 0
epic_id: "0.130.0"
owner_path: "framework/packages/platform/cli/"

package_id: "platform/cli"
composer: "coretsia/platform-cli"
kind: runtime
module_id: "platform.cli"

goal: "Provide a production-safe CLI base `coretsia ...` with config-based command registry (no devtools command classes shipped)."
provides:
- "Single UX entrypoint `coretsia ...`"
- "Config-based command registry `cli.commands` (list<FQCN>)"
- "Deterministic safe output + deterministic error codes (CLI-owned, no secrets)"
- "No `core/kernel` dependency (Phase 0 CLI base)"

tags_introduced: []
config_roots_introduced: ["cli"]
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.30.0 — Phase 0 workspace layout exists (repo has `framework/`; `skeleton/` MAY exist)
  - 0.120.0 — CLI ports exist in `coretsia/contracts` (`CommandInterface`, `InputInterface`, `OutputInterface`)

- Required deliverables (exact paths):
  - none (`skeleton/` MAY exist; if absent → treated as empty overlay (no error))

- Required config roots/keys:
  - none (this epic introduces `cli` root and defaults)

- Required tags:
  - none

- Required contracts / ports:
  - `Coretsia\Contracts\Cli\Command\CommandInterface` — command port
  - `Coretsia\Contracts\Cli\Output\OutputInterface` — output port (commands MUST NOT print directly)
  - `Coretsia\Contracts\Cli\Input\InputInterface` — input port (raw tokens)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`

Forbidden:

- `core/kernel`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- Repo entrypoint (canonical, cross-OS; single-choice):
  - `php coretsia ...`
  - Rationale: works on Linux/Windows and does not depend on executable bits / shebang.

- Framework launcher (implementation detail; still runnable):
  - `php framework/bin/coretsia ...`

- Optional convenience (non-normative; allowed on Unix only):
  - `./coretsia ...` if the file is executable.

- CLI behavior:
  - `php coretsia` (no args) → shows help + available commands
  - `php coretsia help [command]` → help/details (built-in)

- Config registry:
  - `cli.commands` → list of command FQCNs (may be empty)
  - Commands MUST implement `Coretsia\Contracts\Cli\Command\CommandInterface`

- Devtools preset loading (deterministic allowlisted preset; NOT a plugin system):
  - If package `coretsia/devtools-cli-spikes` is installed, CLI MUST load and merge its preset config subtree into `cli` defaults.
  - If the package is not installed, CLI MUST proceed without error (base remains usable).

  - “Installed” detection is single-choice and deterministic:
    - CLI MUST determine installation via `Composer\InstalledVersions::isInstalled('coretsia/devtools-cli-spikes')`.
    - If `Composer\InstalledVersions` is not available, CLI MUST treat this as “not installed” (Phase 0 assumption).

  - Preset source is single-choice (derived from the selected autoload file; no probing):
    - The launcher (`framework/bin/coretsia`) MUST select the autoload file via the ordered fallback and then pass the
      resolved absolute `$autoloadFile` path into `Coretsia\Platform\Cli\Application` (single canonical handoff).
      - Canonical mechanism (single-choice): `Application` MUST accept `$autoloadFile` as a constructor argument
        (or an explicit setter called exactly once before `run()`), and MUST NOT attempt to rediscover it.
    - Let `$vendorDir = dirname($autoloadFile)`.
    - Preset file path MUST be exactly: `$vendorDir . '/coretsia/devtools-cli-spikes/config/cli.php'`.

  - Failure semantics are deterministic + safe (no absolute paths leaked):
    - If `InstalledVersions::isInstalled(...) === true` AND the preset file is missing or not readable
      → MUST be a deterministic CLI error `CORETSIA_CLI_CONFIG_INVALID` with a short fixed reason token:
      `cli-spikes-preset-missing`.
    - If the preset file exists but does not return a valid `cli` subtree (shape violation)
      → MUST be a deterministic CLI error `CORETSIA_CLI_CONFIG_INVALID` with reason token:
      `cli-spikes-preset-invalid`.

### Deliverables (MUST)

#### Creates

Package skeleton:
- [x] `framework/packages/platform/cli/composer.json`
- [x] `framework/packages/platform/cli/src/Module/CliModule.php` — minimal placeholder (no kernel boot)
- [x] `framework/packages/platform/cli/src/Provider/CliServiceProvider.php` — minimal placeholder
- [x] `framework/packages/platform/cli/src/Provider/CliServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [x] `framework/packages/platform/cli/config/cli.php` — defaults (commands empty)
- [x] `framework/packages/platform/cli/config/rules.php` — shape validation
- [x] `framework/packages/platform/cli/README.md` — must include: Observability / Errors / Security-Redaction
- [x] `framework/packages/platform/cli/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`

Core implementation:
- [x] `framework/packages/platform/cli/src/Application.php` — minimal CLI runtime:
  - [x] loads config deterministically
    - [x] loads package defaults from `framework/packages/platform/cli/config/cli.php`
    - [x] deterministic devtools preset merge (allowlisted; NOT a plugin system):
      - [x] if package `coretsia/devtools-cli-spikes` is installed, MUST merge its `config/cli.php` subtree into `cli` defaults
      - [x] if absent, base CLI remains usable (no error)
    - [x] merge skeleton overrides from `skeleton/config/cli.php` IF PRESENT
      - [x] if file (or directory) is absent → empty overlay;
      - [x] if present but unreadable/invalid → `CORETSIA_CLI_CONFIG_INVALID` / `cli-subtree-invalid`
    - [x] Merge algorithm is single-choice and cemented:
      - [x] `cli.commands` (list<FQCN>) uses **append-unique** preserving first occurrence order:
        - [x] sources are applied in fixed order: defaults → preset → skeleton
        - [x] duplicates are removed deterministically by keeping the first occurrence
      - [x] all other `cli.*` keys are merged deterministically with higher-precedence values overriding lower-precedence values;
        lists (except `cli.commands`) are replaced (no implicit list merge)
  - [x] builds command catalog from `cli.commands`
  - [x] Built-in command name reservation (single-choice; cemented):
    - [x] The names `help` and `list` are reserved for built-in commands.
    - [x] If a configured command returns `name()` equal to `help` or `list`,
      CLI MUST fail deterministically with `CORETSIA_CLI_COMMAND_INVALID` and a short reason token:
      `reserved-command-name`.
    - [x] Rationale: avoids ambiguous UX and prevents config from shadowing built-ins in a non-obvious way.
  - [x] Command instantiation policy is single-choice (cemented for Phase 0; no DI):
    - [x] For each FQCN in `cli.commands`, CLI MUST instantiate via `new $fqcn()` (zero-arg constructor).
    - [x] If the class has a non-public constructor or requires args → deterministic error `CORETSIA_CLI_COMMAND_INVALID`.
    - [x] Rationale: Phase 0 CLI is kernel-free; no container / autowiring / service locator is permitted here.
  - [x] supports built-in `help` and `list` behavior
  - [x] Failure handling boundary (single-choice; cemented):
    - [x] `Application` MUST catch `CliExceptionInterface` and render **exactly**:
      - line1: `exception->code()`
      - line2: `exception->reason()`
      - via `CliOutput` (redaction rules still apply)
    - [x] `Application` MUST return exit code `1` for any caught `CliExceptionInterface`
    - [x] Any other `\Throwable` MUST bubble to the launcher catch-all (launcher prints `CORETSIA_CLI_UNCAUGHT_EXCEPTION` + `uncaught-exception`)
  - [x] CLI config merge order is deterministic (single-choice):
    - [x] Start from package defaults (`framework/packages/platform/cli/config/cli.php` subtree)
    - [x] If present, merge devtools preset subtree (`coretsia/devtools-cli-spikes/config/cli.php`) second
    - [x] Merge skeleton subtree (`skeleton/config/cli.php`) last
  - [x] Root path resolution is single-choice and deterministic (no directory probing search):
    - [x] `launcherDir` is the directory of `framework/bin/coretsia`
    - [x] `frameworkRoot` MUST be `realpath(launcherDir . '/..')`
    - [x] `repoRoot` MUST be `realpath(frameworkRoot . '/..')`
    - [x] `skeletonRoot` MUST be `repoRoot . '/skeleton'`
    - [x] If `frameworkRoot` or `repoRoot` resolution fails it MUST be a deterministic CLI error (no absolute paths leaked).
    - [x] якщо skeletonRoot не існує → трактувати як empty overlay (no error) і НЕ ламати запуск базового CLI
  - [x] Phase 0 layout assumption (explicit; single-choice):
    - [x] This CLI base is scoped to the Coretsia monorepo layout in Phase 0.
    - [x] It MUST assume `framework/` and (if present) `skeleton/` are siblings under a common repo root (as derived by the launcher path rules).
    - [x] Running the CLI in arbitrary external project layouts (e.g. vendor-only installs) is out of scope for Phase 0
      and MUST NOT be implied by this epic.
- [x] `framework/packages/platform/cli/src/Output/CliOutput.php` — implements `Coretsia\Contracts\Cli\Output\OutputInterface`:
  - [x] MUST be deterministic + safe (single-choice; cemented in Phase 0):
    - [x] All emitted output MUST end with a single `\n`.
    - [x] MUST NOT emit absolute paths (Windows drive/UNC, `/home/`, `/Users/`) in any error rendering.
  - [x] Redaction policy (single-choice; cemented):
    - [x] Redaction is enabled by default via `cli.output.redaction.enabled = true`.
    - [x] When enabled, the output layer MUST redact values for “secret-like” keys in BOTH text and JSON rendering.
    - [x] Secret-like keys match (case-insensitive) the following substrings:
      `TOKEN`, `PASSWORD`, `PASS`, `SECRET`, `AUTH`, `COOKIE`, `SESSION`, `KEY`, `PRIVATE`.
    - [x] Text redaction rules (deterministic):
      - [x] For patterns like `KEY=VALUE` where `KEY` is secret-like → replace `VALUE` with `<redacted>`.
      - [x] For patterns like `Authorization: ...` → replace the value part with `<redacted>`.
      - [x] Redaction MUST NOT depend on locale/regex engine flags beyond case-insensitive matching.
    - [x] JSON redaction rules (single-choice; deterministic):
      - [x] Before JSON normalization/encoding, the output layer MUST recursively traverse the payload:
        - [x] If a node is a map (associative array): for every key that is secret-like → replace its value with the string `<redacted>`.
        - [x] The replacement MUST happen regardless of the original value type (scalar/list/map).
        - [x] Lists are traversed element-by-element; if elements contain maps, the same key-based redaction applies.
      - [x] Redaction MUST NOT remove keys and MUST NOT change list lengths; only values are replaced.
      - [x] The placeholder MUST be exactly `<redacted>` (ASCII, stable).
  - [x] JSON output determinism (single-choice; cemented):
    - [x] `json(array $payload)` MUST apply JSON redaction first (if enabled), then normalize maps by sorting keys
      ascending by byte-order (`strcmp`) recursively.
    - [x] Lists MUST preserve order and MUST NOT be sorted.
    - [x] JSON encoding MUST use:
      - [x] `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR`
    - [x] JSON output MUST be a single line + trailing `\n` (no pretty print in Phase 0).
- [x] `framework/packages/platform/cli/src/Input/CliInput.php` — implements `Coretsia\Contracts\Cli\Input\InputInterface`
- [x] `framework/packages/platform/cli/src/Command/HelpCommand.php` (built-in)
- [x] `framework/packages/platform/cli/src/Command/ListCommand.php` (built-in)
- [x] `framework/packages/platform/cli/src/Exception/CliExceptionInterface.php` — internal deterministic failure surface:
  - [x] `code(): string` (CLI-owned code from `ErrorCodes`)
  - [x] `reason(): string` (short fixed token; MUST be stable; MUST NOT contain paths/secrets)
- [x] `framework/packages/platform/cli/src/Exception/CliException.php` — base exception (`\RuntimeException`) implementing `CliExceptionInterface`
- [x] `framework/packages/platform/cli/src/Exception/CliConfigInvalidException.php` — `CORETSIA_CLI_CONFIG_INVALID` (+ reason tokens)
- [x] `framework/packages/platform/cli/src/Exception/CliCommandInvalidException.php` — `CORETSIA_CLI_COMMAND_INVALID` (+ reason tokens)
- [x] `framework/packages/platform/cli/src/Exception/CliCommandClassMissingException.php` — `CORETSIA_CLI_COMMAND_CLASS_MISSING` (+ reason tokens)
- [x] `framework/packages/platform/cli/src/Exception/CliCommandFailedException.php` — command-level failure (MAY implement `CliExceptionInterface` if used for deterministic rendering)
- [x] `framework/packages/platform/cli/src/Error/ErrorCodes.php` — CLI-owned deterministic codes registry (NOT spikes registry):
  - [x] `CORETSIA_CLI_COMMAND_CLASS_MISSING`
  - [x] `CORETSIA_CLI_COMMAND_INVALID`
  - [x] `CORETSIA_CLI_CONFIG_INVALID`
  - [x] `CORETSIA_CLI_UNCAUGHT_EXCEPTION`
  - [x] `all(): list<string>` MUST return codes sorted by `strcmp` (contract-test friendly)
  - [x] Invariant: `CORETSIA_CLI_UNCAUGHT_EXCEPTION` is **launcher-only** (catch-all), NOT thrown by domain logic

Launcher + repo entrypoint:
- [x] `coretsia` — repo-root entrypoint (thin wrapper; single-choice):
  - [x] MUST be a PHP file (no extension required).
  - [x] MUST delegate to `framework/bin/coretsia` as the single canonical launcher implementation.
  - [x] MUST NOT implement its own autoload probing or error rendering beyond delegation.
  - [x] MUST exit with the same exit code as the framework launcher.

- [x] `framework/bin/coretsia` — entry launcher:
  - [x] MUST load composer autoload deterministically (ordered fallback; single-choice):
    1) `framework/vendor/autoload.php`
    2) `vendor/autoload.php`
  - [x] MUST NOT perform directory probing beyond the ordered fallback above.
  - [x] MUST set strict runtime defaults (single-choice):
    - [x] `error_reporting(E_ALL)`
    - [x] `ini_set('display_errors', '0')`
  - [x] MUST delegate execution to `Coretsia\Platform\Cli\Application`.
  - [x] Exit semantics are single-choice (cemented):
    - [x] the launcher MUST call `exit($exitCode)` where `$exitCode` is the `Application` result.
  - [x] MUST be exception-safe at top-level (single-choice; required):
    - [x] The launcher MUST wrap the whole boot + run flow in `try { ... } catch (\Throwable $e) { ... }`.
    - [x] On ANY caught throwable, the launcher MUST print a minimal deterministic error (no exception text/trace):
      - [x] Line 1: `CORETSIA_CLI_UNCAUGHT_EXCEPTION`
      - [x] Line 2: `uncaught-exception`
    - [x] The launcher MUST NOT print:
      - [x] `$e->getMessage()`, stack traces, or any file/line info (may contain absolute paths).
    - [x] Exit code MUST be `1`.
  - [x] Boot failure semantics (single-choice; deterministic + safe):
    - [x] If no autoload file is found → exit `1` and print a minimal deterministic error:
      - [x] `CORETSIA_CLI_UNCAUGHT_EXCEPTION` (line 1)
      - [x] short fixed reason (line 2), MUST NOT include absolute paths.

Skeleton config:
- [x] `skeleton/config/cli.php` — user overrides zone (OPTIONAL file; may be absent):
  - [x] `cli.commands` = `[]` (empty by default)
  - [x] `cli.output.format` = `text` - може перекривати defaults пакета
  - [x] `cli.output.redaction.enabled` = `true` - може перекривати defaults пакета
  - [x] якщо файл відсутній — трактувати як empty overlay (no error)

Tests:
- [x] `framework/packages/platform/cli/tests/Integration/CliBootHelpWorksWithEmptyCommandsTest.php`
- [x] `framework/packages/platform/cli/tests/Integration/CliRejectsMissingCommandClassDeterministicallyTest.php`
- [x] `framework/packages/platform/cli/tests/Integration/OutputRedactionDoesNotLeakTest.php`
- [x] `framework/packages/platform/cli/tests/Contract/CommandsDoNotWriteToStdoutTest.php`
  - [x] Contract test MUST be a token-based scan of `framework/packages/platform/cli/src/Command/**/*.php`.
  - [x] MUST fail if any command class contains direct output constructs or direct stdout/stderr sinks:
    - [x] `echo`, `print`
    - [x] `var_dump`, `print_r`, `printf`, `vprintf`, `fprintf`, `error_log`
    - [x] `fwrite(STDOUT|STDERR, ...)`, `fputs(STDOUT|STDERR, ...)`
    - [x] `php://stdout`, `php://stderr`, `php://output` used in `file_put_contents` / `fopen` / `popen`
  - [x] MUST ignore occurrences inside comments/strings (token-based; comments/strings excluded).
- [x] `framework/packages/platform/cli/tests/Contract/CliConfigSubtreeShapeAndMergeSemanticsTest.php`
  - [x] MUST fail if any `config/cli.php` returns a repeated root key (e.g. `['cli' => ...]`) instead of the `cli` subtree.
  - [x] MUST prove `cli.commands` merge strategy is **append-unique** with stable first-occurrence order:
    - [x] merge order is defaults → preset → skeleton
    - [x] duplicates are removed deterministically by keeping the first occurrence
    - [x] resulting list order is therefore fully determined by the merge order above

#### Modifies

- [x] `composer.json` — registers `coretsia` script entrypoint (repo root)

#### Configuration (keys + defaults)

- [x] Files:
  - [x] `framework/packages/platform/cli/config/cli.php`
  - [x] `skeleton/config/cli.php`
- [x] Config file shape (single-choice; SSoT):
  - [x] `framework/packages/platform/cli/config/cli.php` MUST return the `cli` subtree (NO repeated root key):
    - [x] ✅ returns: `['commands' => [...], 'output' => [...]]`
    - [x] ❌ forbidden: `['cli' => ['commands' => ...]]`
  - [x] `skeleton/config/cli.php` MUST return the `cli` subtree (NO repeated root key).
  - [x] Any preset merge (e.g. `coretsia/devtools-cli-spikes/config/cli.php`) MUST also return the `cli` subtree (NO repeated root key).
- [x] Keys (dot):
  - [x] `cli.commands` = `[]`
  - [x] `cli.output.format` = `text`
  - [x] `cli.output.redaction.enabled` = `true`
- [x] Rules:
  - [x] `framework/packages/platform/cli/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

N/A (Phase 0 base: no tag-based discovery)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [x] Output is deterministic + safe:
  - [x] MUST NOT print secrets/PII (dotenv values, tokens, passwords, cookies, Authorization)

#### Errors

- [x] Every failing command prints:
  - [x] deterministic `code` string (CLI-owned registry)
  - [x] short reason (no secrets)
- [x] Exit code policy: `0` success, `!=0` error (stable)

#### Security / Redaction

- [x] MUST NOT leak: dotenv values, credentials, raw file contents
- [x] Allowed: `hash(value)` / `len(value)` / safe normalized paths

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] Integration tests fail if:
  - [x] CLI stops being executable
  - [x] output becomes unstable
  - [x] secrets appear in output
  - [x] missing command class stops being deterministic error
- [x] A contract/integration test MUST cement subtree shape + merge semantics:
  - [x] it MUST fail if any `config/cli.php` returns `['cli' => ...]`
  - [x] it MUST prove `cli.commands` append-unique behavior and stable ordering across sources

### Tests (MUST)

- Contract:
  - [x] `framework/packages/platform/cli/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
- Integration:
  - [x] `framework/packages/platform/cli/tests/Integration/*`

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] No forward refs (`core/kernel` forbidden)
- [x] `coretsia` works on clean clone (help/list)
- [x] Repo entrypoint exists:
  - [x] `coretsia` file exists in repo root and is runnable via `php coretsia`.
- [x] Exit semantics are cemented:
  - [x] `framework/bin/coretsia` exits with the `Application` exit code (no ambiguous “return” semantics).
- [x] Production safety:
  - [x] `coretsia/cli` ships with NO devtools/spike command classes
- [x] Output UX contract is cemented:
  - [x] deterministic errors and redaction
- [x] `cli.commands` merge semantics are single-choice and deterministic:
  - [x] Merge strategy: **append-unique** preserving first occurrence order.
  - [x] Duplicate FQCNs are removed deterministically (keep the first occurrence).
  - [x] Final list order is therefore defined exclusively by the fixed merge order (defaults → preset → skeleton).
- [x] Package use ports:
  - [x] `Coretsia\Contracts\Cli\Command\CommandInterface` uses `Coretsia\Contracts\Cli\Input\InputInterface` + `Coretsia\Contracts\Cli\Output\OutputInterface`
  - [x] no package-local CommandInterface/InputInterface/OutputInterface remain
- [x] Skeleton overrides override defaults for scalar/map keys; cli.commands is merged via append-unique (not replaced)

---

### 0.140.0 `coretsia/cli-spikes` Phase 0 command pack (require-dev) (MUST) [TOOLING]

---
type: package
phase: 0
epic_id: "0.140.0"
owner_path: "framework/packages/devtools/cli-spikes/"

package_id: "devtools/cli-spikes"
composer: "coretsia/devtools-cli-spikes"
kind: library

goal: "Provide Phase 0 spike command implementations for `coretsia` CLI without shipping them in production runtime."
provides:
- "`coretsia doctor` and Phase 0 spike commands as devtools-only classes"
- "Deterministic safe dispatch to tools-only spikes under `framework/tools/spikes/**`"
- "Config preset that registers command FQCNs into `cli.commands` when package is installed"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.130.0 — CLI base exists (CommandInterface + registry `cli.commands`)
  - 0.20.0 — spikes sandbox exists (`framework/tools/spikes/**`)
  - 0.60.0 — fingerprint spike exists
  - 0.80.0 — deptrac spike exists
  - 0.90.0 — config_merge spike exists
  - 0.100.0 — workspace spike exists

- Required deliverables (exact paths):
  - `framework/tools/spikes/_support/bootstrap.php` — spikes bootstrap exists
  - `framework/tools/spikes/_support/ErrorCodes.php` — spikes error codes registry exists
  - `framework/tools/spikes/fixtures/**` — fixtures exist (for commands that use them)

- Required config roots/keys:
  - `cli.commands` — must exist (provided by 0.130 defaults)
  - `cli.output.*` — must exist (redaction enabled by default)

- Required tags:
  - none

- Required contracts / ports:
  - `Coretsia\Contracts\Cli\Command\CommandInterface` — command port (from core/contracts)
  - `Coretsia\Contracts\Cli\Output\OutputInterface` — output port (from core/contracts)
  - `Coretsia\Contracts\Cli\Input\InputInterface` — input port (raw tokens)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `platform/cli`
- `devtools/internal-toolkit` (optional helpers)

Forbidden:

- `core/kernel`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- CLI (provided when this package is installed + config preset loaded):
  - `coretsia doctor` → `Coretsia\Devtools\CliSpikes\Command\DoctorCommand`
  - `coretsia spike:fingerprint` → `...SpikeFingerprintCommand`
  - `coretsia spike:config:debug --key=<dot.key>` → `...SpikeConfigDebugCommand`
  - `coretsia deptrac:graph` → `...DeptracGraphCommand`
  - `coretsia workspace:sync --dry-run` → `...WorkspaceSyncDryRunCommand`
  - `coretsia workspace:sync --apply` → `...WorkspaceSyncApplyCommand`

### Deliverables (MUST)

#### Creates

Package skeleton:
- [x] `framework/packages/devtools/cli-spikes/composer.json`
- [x] `framework/packages/devtools/cli-spikes/README.md` — must include: Observability / Errors / Security-Redaction

Config preset (single source of truth for registration):
- [x] `framework/packages/devtools/cli-spikes/config/cli.php` — returns subtree (no repeated root):
  - [x] sets `commands` list (FQCNs) for registration into `cli.commands`

Commands:
- [x] `framework/packages/devtools/cli-spikes/src/Command/DoctorCommand.php`
- [x] `framework/packages/devtools/cli-spikes/src/Command/SpikeFingerprintCommand.php`
- [x] `framework/packages/devtools/cli-spikes/src/Command/SpikeConfigDebugCommand.php`
- [x] `framework/packages/devtools/cli-spikes/src/Command/DeptracGraphCommand.php`
- [x] `framework/packages/devtools/cli-spikes/src/Command/WorkspaceSyncDryRunCommand.php`
- [x] `framework/packages/devtools/cli-spikes/src/Command/WorkspaceSyncApplyCommand.php`

Dispatch helpers (REQUIRED; single canonical mechanism):
- [x] `framework/packages/devtools/cli-spikes/src/Spikes/SpikesPaths.php` — resolves repo-root + fixture roots safely (no absolute path leaks):
  - [x] Root resolution MUST be single-choice and MUST NOT use directory probing/search:
    - [x] Let `$launcherPathRaw` be the first available source in this exact order:
      1) `$_SERVER['SCRIPT_FILENAME']` (preferred; most stable in PHP CLI)
      2) `$_SERVER['argv'][0]` (fallback)
    - [x] Let `$launcherPath = realpath($launcherPathRaw)`; if it fails → error reason `launcher-path-unresolvable`
  - [x] Returned display paths (if any) MUST be repo-relative normalized (forward slashes) only.
- [x] `framework/packages/devtools/cli-spikes/src/Spikes/SpikesBootstrap.php` — loads `framework/tools/spikes/_support/bootstrap.php` deterministically:
  - [x] MUST load exactly the path computed by `SpikesPaths` (no fallbacks, no probing).
  - [x] MUST use `require_once` for the tools bootstrap file.
  - [x] MUST NOT emit stdout/stderr; all user-visible output is owned by CLI `OutputInterface`.
- [x] `framework/packages/devtools/cli-spikes/src/Spikes/SpikesExitCodeMapper.php` — stable exit codes mapping (cemented):
  - [x] Phase 0 policy is single-choice and binary:
    - [x] `0` — success
    - [x] `1` — any failure (including deterministic spike failures and uncaught exceptions)
- [x] `framework/packages/devtools/cli-spikes/src/Spikes/SpikesBootstrapFailedException.php` — deterministic bootstrap failure carrier:
  - [x] MUST extend `\RuntimeException` (or `\Exception`) but MUST be used as a typed signal (single-choice).
  - [x] MUST expose: `reason(): string` returning one of the cemented reason tokens:
    - [x] `launcher-path-unresolvable`
    - [x] `framework-root-unresolvable`
    - [x] `repo-root-unresolvable`
    - [x] `spikes-bootstrap-missing`
    - [x] `composer-autoload-missing`
  - [x] The exception message MUST be exactly the `reason()` token (single-choice).
  - [x] Unknown reason token is a developer error and MUST NOT become a public runtime reason.
  - [x] MUST NOT include absolute paths or any dynamic OS error text.

Tests:
- [x] `framework/packages/devtools/cli-spikes/tests/Integration/DoctorCommandRunsTest.php`
- [x] `framework/packages/devtools/cli-spikes/tests/Integration/SpikeFingerprintGoldenOkTest.php`
- [x] `framework/packages/devtools/cli-spikes/tests/Integration/SpikeConfigDebugStableTraceTest.php`
- [x] `framework/packages/devtools/cli-spikes/tests/Integration/DeptracGraphRunsTest.php`
- [x] `framework/packages/devtools/cli-spikes/tests/Integration/WorkspaceSyncDryRunIsSafeTest.php`
- [x] `framework/packages/devtools/cli-spikes/tests/Integration/WorkspaceSyncApplyCommandTest.php`
- [x] `framework/packages/devtools/cli-spikes/tests/Contract/CliSpikesIsDevOnlyPolicyTest.php`
  - [x] asserts `framework/composer.json` contains `coretsia/devtools-cli-spikes` only in `require-dev` and not in `require`
- [x] `framework/packages/devtools/cli-spikes/tests/Contract/CommandsDoNotWriteToStdoutTest.php`
  - [x] token-based scan of `framework/packages/devtools/cli-spikes/src/Command/**/*.php`
  - [x] MUST fail if any command class contains direct output constructs or direct stdout/stderr sinks:
    - [x] `echo`, `print`
    - [x] `var_dump`, `print_r`, `printf`, `vprintf`, `fprintf`
    - [x] `fwrite(STDOUT|STDERR, ...)`, `fputs(STDOUT|STDERR, ...)`
    - [x] `php://stdout`, `php://stderr` used in `file_put_contents` / `fopen`
  - [x] MUST ignore occurrences inside comments/strings (token-based; same policy as platform/cli contract)

#### Modifies

- [x] `framework/composer.json` — enforce dev-only installation (single-choice):
  - [x] `coretsia/devtools-cli-spikes` MUST appear only under `require-dev`
  - [x] `coretsia/devtools-cli-spikes` MUST NOT appear under `require`

#### Configuration (keys + defaults)

- [x] Files:
  - [x] `framework/packages/devtools/cli-spikes/config/cli.php`
- [x] Keys (dot):
  - [x] `cli.commands` extends with:
    - [x] `DoctorCommand`
    - [x] `SpikeFingerprintCommand`
    - [x] `SpikeConfigDebugCommand`
    - [x] `DeptracGraphCommand`
    - [x] `WorkspaceSyncDryRunCommand`
    - [x] `WorkspaceSyncApplyCommand`

#### Wiring / DI tags (when applicable)

N/A (Phase 0: config registry only)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [x] Output MUST be deterministic and safe:
  - [x] MUST NOT print dotenv values/tokens/passwords/cookies/Authorization
  - [x] allowed diagnostics: normalized relative paths, code strings, hashes/len

#### Errors

- [x] Commands MUST forward spike error codes from `framework/tools/spikes/_support/ErrorCodes.php` where applicable
- [x] CLI failures outside spikes MUST use CLI base codes (0.130)

#### Security / Redaction

- [x] MUST NOT leak: `.env` values, raw payloads, raw composer.json contents
- [x] Allowed: `hash(value)` / `len(value)` / safe ids / normalized relative paths

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] Integration tests fail if commands stop dispatching to tools-only spikes
- [x] Integration tests fail if output leaks secrets
- [x] Removing `coretsia/cli-spikes/config/cli.php` MUST break command registration (cemented preset)

### Tests (MUST)

- Integration:
  - [x] `framework/packages/devtools/cli-spikes/tests/Integration/*`
- Unit/Contract:
  - N/A

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] No forward refs (all referenced spikes exist by prerequisites)
- [x] `coretsia/cli-spikes` is require-dev only by policy (not shipped in production)
- [x] Installing only `platform/cli` DOES NOT include these command classes
- [x] Commands dispatch to tools-only spikes (no duplicated spike logic in package)
- [x] Contract: `CommandsDoNotWriteToStdoutTest` exists and is green (devtools commands cannot bypass OutputInterface)
- [x] Package use ports:
  - [x] `Coretsia\Contracts\Cli\Command\CommandInterface` uses `Coretsia\Contracts\Cli\Input\InputInterface` + `Coretsia\Contracts\Cli\Output\OutputInterface`
  - [x] no package-local CommandInterface/InputInterface/OutputInterface remain
- [x] Tools-only execution boundary (single-choice dispatch policy):
  - [x] Commands MUST NOT implement spike business logic in this package.
  - [x] Commands MUST dispatch through exactly one canonical entrypoint into tools-only spikes:
    - [x] via `Coretsia\Devtools\CliSpikes\Spikes\SpikesBootstrap` (REQUIRED)
  - [x] Output authority is single-choice (no bypass):
    - [x] ALL user-visible output MUST be produced via CLI `OutputInterface` only.
    - [x] Tools-only spike code under `framework/tools/spikes/**` MUST NOT write to stdout/stderr at all
      (neither directly, nor via `ConsoleOutput`).
    - [x] `framework/tools/spikes/_support/ConsoleOutput.php` is reserved for **gates/runner diagnostics only**
      (CI rails / determinism runner / gate scripts), not for spike business logic.
  - [x] Commands MUST treat spike execution as pure computation:
    - [x] spikes return structured results (arrays/DTOs) or throw deterministic exceptions with stable codes
    - [x] commands render results/errors via `OutputInterface` (text/json/error) with redaction enforced by CLI
  - [x] Commands MUST NOT depend on capturing stdout/stderr from spikes for semantics (any such output is a bug).
  - [x] No duplicated spike logic:
    - [x] commands MUST NOT partially duplicate spike algorithms (no “helper re-implementation” per command)
  - [x] Failure propagation is single-choice:
    - [x] tools-only spikes MUST throw `framework/tools/spikes/_support/DeterministicException` for deterministic failures
    - [x] commands MUST render failures via `OutputInterface->error($code, $message)` and return `SpikesExitCodeMapper` result
  - [x] Bootstrap failure containment (single-choice; REQUIRED):
    - [x] `Coretsia\Devtools\CliSpikes\Spikes\SpikesBootstrap` MUST assume that the CLI launcher (0.130) has already loaded Composer autoload.
    - [x] `SpikesBootstrap` MUST NOT rely on the tools bootstrap failure-mode (`print + exit`) for user-visible errors.
    - [x] If `SpikesBootstrap` cannot safely guarantee that autoload is present (e.g., launcher path cannot be resolved),
      it MUST throw `SpikesBootstrapFailedException` BEFORE requiring the tools bootstrap, so the command can render via `OutputInterface->error(...)`.
