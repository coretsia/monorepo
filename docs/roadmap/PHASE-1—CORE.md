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

## PHASE 1 — CORE (повний core/*: contracts + foundation + kernel + baseline platform invariants where required, Non-product doc)

### 1.10.0 Tag registry (SSoT): reserved tags + naming rules (MUST) [DOC]

---
type: docs
phase: 1
epic_id: "1.10.0"
owner_path: "docs/ssot/tags.md"

goal: "A single SSoT defines reserved DI tags and naming rules so discovery/wiring stays deterministic and conflict-free."
provides:
- "Reserved tag registry with explicit ownership"
- "Naming rules for tags (stable, parse-friendly, deterministic)"
- "Policy: tags MUST be declared as constants by the owner package"

tags_introduced:
- "cli.command"
- "http.middleware.system_pre"
- "http.middleware.system"
- "http.middleware.system_post"
- "http.middleware.app_pre"
- "http.middleware.app"
- "http.middleware.app_post"
- "http.middleware.route_pre"
- "http.middleware.route"
- "http.middleware.route_post"
- "kernel.reset"
- "kernel.stateful"
- "kernel.hook.before_uow"
- "kernel.hook.after_uow"
- "error.mapper"
- "health.check"

config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/INDEX.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index exists and must link to this registry

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

- [x] `docs/ssot/tags.md` — SSoT registry:
  - [x] naming rules (single-choice): dot-separated tokens; lowercase; digits and `_` are allowed inside a token; no whitespace
    - [x] canonical regex: `^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$`
  - [x] Registry rows можуть існувати до появи owner package; константи вводяться лише в owner epic.
  - [x] Інші пакети MAY мати local mirror constants лише коли owner package є forbidden compile-time dep;
    ці константи не є public API і MUST дорівнювати canonical string (перевіряється gate’ом).
  - [x] ownership rule (single-choice):
    - [x] every tag has exactly ONE owner package_id
    - [x] only the owner epic may introduce/modify a tag entry
    - [x] “shared ownership” is forbidden
  - [x] reserved prefixes (single-choice; baseline):
    - [x] `http.middleware.*` → owner: `platform/http`
    - [x] `kernel.hook.*` → owner: `core/kernel`
    - [x] `kernel.reset` → owner: `core/foundation` (reserved canonical default reset-discovery tag name)
    - [x] `kernel.stateful` → owner: `core/foundation` (fixed enforcement marker)
    - [x] `kernel.*` (крім вищих винятків) → owner: `core/kernel`
    - [x] `cli.*` → owner: `platform/cli`
    - [x] `error.*` → owner: `platform/errors`
    - [x] `health.*` → owner: `platform/health` (future)
  - [x] reserved tags (baseline; MUST be present as registry rows):
    - [x] HTTP middleware slots (owner: `platform/http`):
      - [x] `http.middleware.system_pre`
      - [x] `http.middleware.system`
      - [x] `http.middleware.system_post`
      - [x] `http.middleware.app_pre`
      - [x] `http.middleware.app`
      - [x] `http.middleware.app_post`
      - [x] `http.middleware.route_pre`
      - [x] `http.middleware.route`
      - [x] `http.middleware.route_post`
    - [x] Kernel lifecycle:
      - [x] `kernel.reset` → owner: `core/foundation`
      - [x] `kernel.stateful` → owner: `core/foundation`
      - [x] `kernel.hook.before_uow` → owner: `core/kernel`
      - [x] `kernel.hook.after_uow` → owner: `core/kernel`
    - [x] Errors discovery (owner: `platform/errors`):
      - [x] `error.mapper`
    - [x] Health discovery (owner: `platform/health` future):
      - [x] `health.check`
    - [x] CLI discovery (owner: `platform/cli`):
      - [x] `cli.command`
  - [x] forbidden / legacy tags (cemented):
    - [x] The following legacy tags MUST NOT be introduced anywhere:
      - [x] `http.middleware.user_before_routing`
      - [x] `http.middleware.user`
      - [x] `http.middleware.user_after_routing`
    - [x] Rationale: canonical Phase 0+ taxonomy is single-choice: `system/app/route`.
    - [x] Any new epic mentioning `http.middleware.user*` MUST treat it only as legacy/renamed terminology and MUST NOT use it as a current tag name anywhere in contracts, SSoT, defaults, or gates.
  - [x] registry table: `tag` | `owner package_id` | `purpose` | `stability` | `notes`
    - [x] stability enum (single-choice): `stable|experimental|deprecated`
  - [x] rule (single-choice): every reserved tag MUST be declared as a constant in the owner package (usually `src/Provider/Tags.php`)
  - [x] ownership split (single-choice):
    - [x] for every reserved tag, the owner package exclusively owns:
      - [x] the registry row in `docs/ssot/tags.md`
      - [x] the canonical public constant
      - [x] the canonical meta-schema (if any)
      - [x] the canonical consumer/discovery semantics
    - [x] non-owner packages MAY use existing reserved tags, but MUST NOT redefine competing meta keys or semantics
  - [x] runtime usage rule (single-choice):
    - [x] if the owner package is an allowed compile-time dependency, runtime code MUST use the owner public tag constant
    - [x] if the owner package is a forbidden compile-time dependency, runtime code MAY define a package-local mirror constant
    - [x] such local mirror constants:
      - [x] MUST be package-internal only
      - [x] MUST equal the canonical tag string exactly
      - [x] MUST NOT be treated as public API
      - [x] MUST be verified by tooling gates for string equality
    - [x] raw literal tag strings are allowed in docs/tests/fixtures for readability, but MUST NOT be the preferred runtime-code pattern
  - [x] contributor rule (single-choice):
    - [x] a non-owner epic MAY say `N/A (uses existing <tag>)`
    - [x] a non-owner epic MUST NOT introduce or freeze a competing meta-schema for that tag
    - [x] if owner meta-schema is not cemented yet, contributor epics MUST say `meta per owner schema` (or equivalent) instead of inventing alternative keys
  - [x] temporal clarification (single-choice):
    - [x] a registry row MAY exist before the owner package becomes tag-aware in runtime
    - [x] the canonical owner public constant becomes mandatory in the owner epic that introduces the corresponding tag-aware mode/entrypoint
    - [x] until then, non-owner packages MAY still reference the canonical tag only under the usage rule:
      - [x] owner public constant when the owner package is an allowed compile-time dependency
      - [x] package-local mirror constant when the owner package is a forbidden compile-time dependency
    - [x] non-owner packages MUST NOT define competing public APIs or competing meta-schema for the same tag
  - [x] rule (single-choice): introducing a new tag requires:
    - [x] adding a registry row in `docs/ssot/tags.md` by the owner epic
    - [x] adding a constant in the owner package
  - [x] Non-tag metadata rule:
    - [x] PHP attributes (including DTO marker attributes) are NOT DI tags and MUST NOT be registered in this tag registry.
    - [x] DI tags and PHP attributes are orthogonal mechanisms and MUST NOT be conflated.

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/tags.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (doc-only; enforcement may be introduced later as a gate)

### Tests (MUST)

N/A

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] Preconditions satisfied (no forward references)
- [x] Registry is single-choice (no “either/or” naming rules)
- [x] Ownership rules are explicit (no ambiguous “shared” ownership)

---

### 1.20.0 Config roots registry (SSoT): reserved roots + ownership (MUST) [DOC]

---
type: docs
phase: 1
epic_id: "1.20.0"
owner_path: "docs/ssot/config-roots.md"

goal: "A single SSoT defines reserved config roots, ownership, and invariants so config stays predictable across packages."
provides:
- "Reserved config roots registry with explicit ownership"
- "Invariant: config/<name>.php returns subtree (no repeated root wrapper)"
- "Policy: only the owning package defines defaults + rules for its root"

tags_introduced: []
config_roots_introduced:
- "cli"
- "foundation"
- "kernel"
- "http"
- "logging"
- "metrics"
- "tracing"
- "problem_details"

artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/INDEX.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index exists and must link to this registry

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

- [x] `docs/ssot/config-roots.md` — SSoT registry:
  - [x] invariants (single-choice):
    - [x] `config/<name>.php` returns subtree (no repeated root wrapper)
    - [x] defaults live in the owning package only
    - [x] `config/rules.php` is owned by the owning package only
    - [x] config roots and DTO policy are orthogonal:
      - [x] configuration ownership is defined exclusively by config roots registry
      - [x] PHP attributes MUST NOT replace package-owned config defaults/rules
  - [x] Example (cemented):
    - [x] File: `config/foundation.php`
      - [x] MUST return the subtree (without repeating the root key), e.g. it returns `['container' => [...], ...]`, not `['foundation' => [...]]`.
    - [x] Runtime reads from the global config under the root key, e.g. `foundation.container.*`.
  - [x] registry table: `root` | `owner package_id` | `defaults file` | `rules file` | `notes`
    - [x] `cli` | `platform/cli` | `framework/packages/platform/cli/config/cli.php` | `framework/packages/platform/cli/config/rules.php` | Phase 0 locked root from 0.130.0
    - [x] `foundation` | `core/foundation` | `framework/packages/core/foundation/config/foundation.php` | `framework/packages/core/foundation/config/rules.php` | runtime core root
    - [x] `kernel` | `core/kernel` | `framework/packages/core/kernel/config/kernel.php` | `framework/packages/core/kernel/config/rules.php` | runtime kernel root
    - [x] `http` | `platform/http` | `framework/packages/platform/http/config/http.php` | `framework/packages/platform/http/config/rules.php` | platform HTTP root
    - [x] `logging` | `platform/logging` | `framework/packages/platform/logging/config/logging.php` | `framework/packages/platform/logging/config/rules.php` | platform logging root
    - [x] `metrics` | `platform/metrics` | `framework/packages/platform/metrics/config/metrics.php` | `framework/packages/platform/metrics/config/rules.php` | platform metrics root
    - [x] `tracing` | `platform/tracing` | `framework/packages/platform/tracing/config/tracing.php` | `framework/packages/platform/tracing/config/rules.php` | platform tracing root
    - [x] `problem_details` | `platform/problem-details` | `framework/packages/platform/problem-details/config/problem_details.php` | `framework/packages/platform/problem-details/config/rules.php` | platform problem-details root
  - [x] initial registry rows introduced by this epic: `cli,foundation,kernel,http,logging,metrics,tracing,problem_details`
  - [x] this registry MAY be extended only by later owner epics via direct modification of `docs/ssot/config-roots.md`
  - [x] later additions MUST update the canonical registry rows directly and MUST NOT leave parallel “future reserved identifier” notes in roadmap epics

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/config-roots.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (doc-only; enforcement is via package compliance + config rules epics)

### Tests (MUST)

N/A

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] Preconditions satisfied (no forward references)
- [x] Ownership is explicit per root (no ambiguous “shared defaults”)

---

### 1.30.0 Artifact header & schema registry (SSoT) (MUST) [DOC]

---
type: docs
phase: 1
epic_id: "1.30.0"
owner_path: "docs/ssot/artifacts.md"

goal: "A single SSoT defines artifact envelope, header, and schema versioning so all generated artifacts are deterministic and verifiable."
provides:
- "Canonical artifact envelope shape (meta + payload) and header fields"
- "Registry of artifact names and schema versions (owner + format law)"
- "Invariant: artifacts MUST be rerun-no-diff and MUST NOT embed timestamps/PII"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced:
- "container@1"
- "config@1"
- "module-manifest@1"
- "routes@1"

adr: none
ssot_refs:
- "docs/ssot/INDEX.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index exists and must link to this registry

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

- [x] `docs/ssot/artifacts.md` — SSoT:
  - [x] Artifact envelope (single-choice; applies to ALL artifacts regardless of file encoding):
    - [x] top-level object MUST be `{ "_meta": <header>, "payload": <schema-specific> }`
  - [x] Header fields (single-choice):
    - [x] `name` (string)
    - [x] `schemaVersion` (int)
    - [x] `fingerprint` (string; deterministic)
    - [x] `generator` (string; stable id, MUST NOT include build timestamps/absolute paths)
    - [x] `requires` (optional; deterministic; e.g. min runtime version)
  - [x] Deterministic serialization law (single-choice; applies to any JSON-like bodies/headers AND to codegen that materializes map ordering):
    - [x] Maps/objects MUST be normalized by sorting keys ascending by byte-order (`strcmp`) recursively at every nesting level.
    - [x] Lists/arrays MUST preserve order (MUST NOT be sorted).
    - [x] List-vs-map classification MUST use `array_is_list(...)` for ANY array value.
    - [x] Empty array rule (cemented):
      - [x] `[]` MUST be treated as a list in serialized form.
      - [x] Rationale: PHP cannot represent empty-map vs empty-list distinction using arrays; `array_is_list([]) === true`.
    - [x] Encoding flags MUST be deterministic:
      - [x] unescaped slashes and unicode
      - [x] no locale-dependent behavior
    - [x] Artifacts MUST be rerun-no-diff and MUST NOT embed timestamps, absolute paths, or environment-specific bytes.
  - [x] Tooling helper library `coretsia/devtools-internal-toolkit` is Phase 0 tooling-only and MUST NOT become a mandatory runtime dependency.
  - [x] Runtime packages (core/*, platform/*) that generate/consume artifacts MUST implement the deterministic laws locally (or via runtime-owned shared code),
    but MUST match the same laws (byte-order key sorting, list order preserved, locale-independent behavior).
  - [x] Serialized `[]` is byte-wise identical for both “empty list” and “empty map” intents (PHP limitation).
  - [x] Therefore:
    - [x] Producers MUST serialize `[]` exactly as `[]`.
    - [x] Consumers MUST interpret `[]` according to the schema/context (list-required vs map-required).
  - [x] invariants:
    - [x] no timestamps, no environment-dependent bytes, no secrets/PII
    - [x] stable sorting rules for maps/arrays when serializing/codegen
    - [x] rerun-no-diff required for any artifact generator
  - [x] registry table: `artifact name` | `schemaVersion` | `owner package_id` | `path shape` | `notes`
  - [x] baseline registry entries:
    - [x] `container@1` (owner: `core/kernel`) — compiled container artifact
    - [x] `config@1` (owner: `core/kernel`) — compiled config artifact (FUTURE: may be introduced later)
    - [x] `module-manifest@1` (owner: `core/kernel`) — ModulePlan-derived enabled/disabled/optionalMissing + deterministic topo order; envelope `{_meta,payload}`; no timestamps/abs paths. (FUTURE: may be introduced later)
    - [x] `routes@1` (owner: `platform/routing`) — route table artifact; schema and ownership belong to `platform/routing`, and contracts do not own artifact generation. (FUTURE: may be introduced later)
  - [x] Artifact payload rule:
    - [x] payload MAY be derived from descriptors/results/DTO-like models, but artifacts are canonical serialized shapes and MUST NOT depend on PHP object identity/class semantics at runtime.
  - [x] artifact readers/consumers MUST validate by schema/header semantics, not by PHP class type checks

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/artifacts.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (doc-only; enforcement is via determinism jobs + artifact readers)

### Tests (MUST)

N/A

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] Envelope + header format is single-choice and deterministic by design
- [x] Registry entries include explicit owner and schemaVersion
- [x] Deterministic serialization law is unambiguous (`array_is_list` applies to any array; empty array cemented)

---

### 1.40.0 Observability naming/labels allowlist (SSoT) (MUST) [DOC]

---
type: docs
phase: 1
epic_id: "1.40.0"
owner_path: "docs/ssot/observability.md"

goal: "A single SSoT defines observability naming and label allowlist to prevent metric/log/span drift and PII leaks."
provides:
- "Metric naming rules + label allowlist (single-choice)"
- "Span naming rules (single-choice)"
- "Redaction invariants for logs/metrics/spans"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/INDEX.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index exists and must link to this registry

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

- [x] `docs/ssot/observability.md` — SSoT:
  - [x] naming rules:
    - [x] spans: `<domain>.<operation>` (e.g. `http.request`)
    - [x] metrics: `<domain>.<metric>_{total|duration_ms|...}` (e.g. `http.request_total`)
  - [x] label allowlist (single-choice; reserved baseline):
    - [x] `method,status,driver,operation,table,outcome`
  - [x] forbidden data:
    - [x] raw path, raw query, headers/cookies/body, auth/session ids, tokens, raw SQL
  - [x] forbidden label keys:
    - [x] `field`
    - [x] `path`
    - [x] `property`
    - [x] `request_id`
    - [x] `correlation_id`
    - [x] `tenant_id`
    - [x] `user_id`
  - [x] allowed patterns:
    - [x] `hash(value)` / `len(value)` / safe ids
  - [x] baseline canonical events:
    - [x] span `http.request`
    - [x] metrics `http.request_total` + `http.request_duration_ms` (labels: `method,status,outcome` only)

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/observability.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (doc-only; enforcement via gates/contract tests in runtime packages)

### Tests (MUST)

N/A

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] Allowlist is explicit and single-choice
- [x] Redaction policy is explicit (must-not-leak list)

---

### 1.50.0 Tooling baseline + arch-rails + public API gates (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.50.0"
owner_path: "framework/tools/"

goal: "It is impossible to merge changes that violate SSoT laws (deps, forbidden skeleton defaults, contracts-only ports, kernel public surface, observability naming, deterministic artifacts, and specialized DTO rails)."
provides:
- "CI rails: gates + arch (deptrac) + determinism checks"
- "System-level gates for forbidden defaults and public API boundaries"
- "Deterministic generator policy (rerun-no-diff) with fixture locks"
- "Extensible deterministic gate rail for specialized policy gates (including DTO policy gates)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/tags.md"
- "docs/ssot/config-roots.md"
- "docs/ssot/artifacts.md"
- "docs/ssot/observability.md"
- "docs/architecture/PACKAGING.md"
- "docs/roadmap/phase0/00_2-dependency-table.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.30.0 — repo baseline CI + composer entrypoints exist (or equivalent CI baseline)
  - 0.20.0 — Phase 0 tooling rails bootstrap + ConsoleOutput + ErrorCodes exist (gates output policy baseline)
  - 0.40.0 — internal-toolkit + anti-dup gate exists (`framework/tools/gates/internal_toolkit_no_dup_gate.php`)
  - 1.10.0 — Tag registry SSoT exists (`docs/ssot/tags.md`)
  - 1.20.0 — Config roots registry SSoT exists (`docs/ssot/config-roots.md`)
  - 1.30.0 — Artifacts registry SSoT exists (`docs/ssot/artifacts.md`)
  - 1.40.0 — Observability SSoT exists (`docs/ssot/observability.md`)

- Required deliverables (exact paths):
  - `.github/workflows/ci.yml` — CI baseline exists (owned/extended here)
  - `framework/composer.json` — workspace scripts baseline exists, including the canonical `test` script consumed by CI
  - `framework/tools/testing/phpunit.xml` — tooling test harness exists
  - `composer.json` — repo-root scripts exist (`setup|ci|test`) (PRELUDE.30.0)
  - `framework/tools/build/sync_composer_repositories.php` — managed repositories sync + `--check` exists (PRELUDE.30.0)
  - `composer.lock` — committed (PRELUDE.30.0)
  - `framework/composer.lock` — committed (PRELUDE.30.0)
  - `skeleton/composer.lock` — committed (PRELUDE.30.0)
  - `framework/tools/spikes/_support/bootstrap.php` — canonical tools bootstrap (CWD-independent)
  - `framework/tools/spikes/_support/ConsoleOutput.php` — only allowlisted stdout/stderr writer for tooling rails
  - `framework/tools/spikes/_support/ErrorCodes.php` — deterministic codes registry used by gates/rails
  - `docs/ssot/tags.md` — tag naming + reserved tags source
  - `docs/ssot/config-roots.md` — config invariants source
  - `docs/ssot/artifacts.md` — artifact envelope/header + schema source
  - `docs/ssot/observability.md` — naming/labels allowlist source

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (tools)

Forbidden:

- N/A

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- CLI:
  - `composer ci` → runs gates + arch rails + tests
- Gate commands policy:
  - every gate created under `framework/tools/gates/*_gate.php` MUST be separately invokable via a dedicated composer script named `<kebab-name>:gate`
  - command name derivation is single-choice:
    - [x] strip suffix `_gate.php`
    - [x] replace `_` with `-`
    - [x] append `:gate`
  - repo-root `composer.json` MUST expose mirror scripts delegating to `framework/composer.json`
  - workspace `framework/composer.json` MUST map each dedicated `*:gate` script directly to the corresponding `tools/gates/*_gate.php`
  - CI `gates` job MUST prefer invoking named composer `*:gate` scripts, not raw `php tools/gates/*.php` paths, for gates owned by this and later epics
- Artifacts:
  - arch job publishes dep graph artifacts (dot/svg/html) (CI artifact upload)

### Gate (baseline) — No skeleton bundle defaults (MUST)

- Script: `framework/tools/gates/no_skeleton_bundles_default_gate.php`
- Purpose: skeleton MUST NOT ship `skeleton/config/bundles/*.php` by default (bundles are framework defaults + optional app override only).
- Output policy: line1 CODE; line2+ diagnostics (repo-relative paths), sorted `strcmp`.
- CI: MUST run in `gates` job before tests; deterministic rerun-no-diff.

### Gate (baseline) — No skeleton HTTP defaults (MUST)

- Script: `framework/tools/gates/no_skeleton_http_default_gate.php`
- Purpose: skeleton MUST NOT ship `skeleton/config/http.php` by default
  (HTTP defaults are framework/package-owned; skeleton file is app-override only).
- Output policy: line1 CODE; line2+ diagnostics (repo-relative paths), sorted `strcmp`.
- CI: MUST run in `gates` job before tests; deterministic rerun-no-diff.

### Gate (baseline) — No skeleton mode presets defaults (MUST)

- Script: `framework/tools/gates/no_skeleton_mode_presets_default_gate.php`
- Purpose: skeleton MUST NOT ship `skeleton/config/modes/*.php` by default
  (mode presets are framework defaults; skeleton mode files are app-override only).
- Output policy: line1 CODE; line2+ diagnostics (repo-relative paths), sorted `strcmp`.
- CI: MUST run in `gates` job before tests; deterministic rerun-no-diff.

### Gate (baseline) — No skeleton modules default (MUST)

- Script: `framework/tools/gates/no_skeleton_modules_default_gate.php`
- Purpose: skeleton MUST NOT ship any parallel module-selection file:
  - `skeleton/config/modules.php`
  - `skeleton/apps/*/config/modules.php`
    (module selection is kernel-owned and resolved only via preset files + composer metadata).
- Output policy: line1 CODE; line2+ diagnostics (repo-relative paths), sorted `strcmp`.
- CI: MUST run in `gates` job before tests; deterministic rerun-no-diff.

### Deliverables (MUST)

#### Creates

- [x] `framework/tools/gates/cross_cutting_contract_gate.php`
  - [x] MUST enforce at minimum:
    - [x] `kernel.stateful` ⇒ service implements `Coretsia\Contracts\Runtime\ResetInterface`
    - [x] `kernel.stateful` ⇒ service is also discoverable through the effective Foundation reset discovery tag resolved from wiring/config
  - [x] MAY additionally enforce forbidden ContextStore / ContextKeys usage once the owning Foundation/Kernel evidence exists
  - [x] if the required owner-package evidence is not present yet, the gate MUST behave as deterministic no-op
- [x] `framework/tools/gates/kernel_public_api_gate.php`
  - [x] this rail MUST exist as a standalone gate script because every created gate MUST be invokable via its own `<command>:gate` composer script
  - [x] optional phpstan/static-analysis rules MAY exist later only as supplemental enforcement, not as a replacement for the gate script
  - [x] If the owning kernel public-surface contract test/package is not present yet, the gate MUST behave as deterministic no-op.
  - [x] Once `core/kernel` public API evidence exists, this rail MUST enforce it without changing output policy.
- [x] `framework/tools/gates/no_skeleton_http_default_gate.php`
- [x] `framework/tools/gates/no_skeleton_mode_presets_default_gate.php`
- [x] `framework/tools/gates/no_skeleton_modules_default_gate.php`
- [x] `framework/tools/gates/no_skeleton_bundles_default_gate.php`
- [x] `framework/tools/gates/contracts_only_ports_gate.php`
  - [x] deterministic scope:
    - [x] scans `framework/packages/**/src/**/*.php`
    - [x] excludes `**/tests/**`, `**/fixtures/**`, `**/vendor/**`
    - [x] output format follows the canonical Phase 0 gate policy
  - [x] MUST fail if a package outside `framework/packages/core/contracts/src/**` declares public ports using canonical port naming/placement:
    - [x] `*PortInterface.php`
    - [x] `src/**/Port/**`
  - [x] MUST NOT fail on ordinary package-internal interfaces that are not presented as cross-package ports
  - [x] diagnostics MUST contain only normalized relative paths + fixed reason tokens
- [x] `framework/tools/gates/tag_constant_mirror_gate.php`
  - [x] validates that:
    - [x] owner tag constants equal the canonical strings from `docs/ssot/tags.md`
    - [x] allowed package-local mirror constants equal the canonical strings exactly
  - [x] temporal owner-constant rule (single-choice):
    - [x] the gate MUST NOT fail only because a registry row exists before the owner public constant is introduced
    - [x] until the owner epic makes the canonical public constant mandatory per `1.10.0` temporal clarification, the gate MUST:
      - [x] verify any existing owner constant, if present, against the canonical string
      - [x] verify any allowed package-local mirror constant against the canonical string
    - [x] once the owner public constant becomes mandatory in the owner epic, absence of that constant MUST fail deterministically
  - [x] local mirror constants are allowed only when the owner package is a forbidden compile-time dependency
  - [x] package-local mirror constants MUST be treated as internal convenience only, not public API
  - [x] the gate MUST also fail if a package attempts to define a competing owner-like constant for a tag it does not own
  - [x] output format follows the canonical Phase 0 gate policy
- [x] `framework/tools/policies/tag_owner_constants.php`
  - [x] declares deterministic owner constant policy for reserved DI tags
  - [x] supports `constant_required=true|false` temporal enforcement
  - [x] maps every `docs/ssot/tags.md` registry row to owner package, expected path, and expected constant name
- [x] `framework/tools/gates/observability_naming_gate.php`
  - [x] MUST enforce at minimum:
    - [x] metric names follow the canonical form from `docs/ssot/observability.md`
    - [x] label keys are limited to the allowlist:
      - [x] `method,status,driver,operation,table,outcome`
    - [x] forbidden label keys fail deterministically:
      - [x] `field`
      - [x] `path`
      - [x] `property`
      - [x] `request_id`
      - [x] `correlation_id`
      - [x] `tenant_id`
      - [x] `user_id`
  - [x] output format follows the canonical Phase 0 gate policy
  - [x] diagnostics MUST contain only normalized relative paths + fixed reason tokens
- [x] `framework/tools/gates/artifact_header_schema_gate.php` — validates the canonical artifact envelope `{ "_meta", "payload" }`
  - [x] required `_meta` fields (`name`, `schemaVersion`, `fingerprint`, `generator`) in generated artifacts
  - [x] forbids timestamps, absolute paths, and environment-specific bytes in generated artifacts
  - [x] MUST validate kernel-owned PHP artifacts that return arrays (e.g. `module-manifest.php`, `config.php`, `container.php`); the gate MUST NOT assume JSON-only artifacts.
  - [x] Validation target is the canonical returned top-level envelope `{ "_meta": <header>, "payload": <schema-specific> }`, regardless of whether the artifact is serialized as JSON or emitted as a PHP file returning an array.
  - [x] temporal artifact-materialization rule (single-choice):
    - [x] the gate MUST NOT fail only because an artifact registry row exists before the owner epic materializes that artifact in runtime/build output
    - [x] if a matching generated artifact file is present, the gate MUST validate it deterministically against the canonical envelope/header/schema rules
    - [x] if no matching artifact file is present yet, the gate MUST behave as a deterministic no-op for that artifact type
    - [x] once an owner epic introduces artifact generation as a required deliverable, malformed produced artifacts MUST fail deterministically

- [x] `framework/tools/testing/deptrac.yaml`
- [x] `framework/tools/testing/deptrac.allowlist.yaml`
- [x] `framework/tools/build/deptrac_generate.php`

Tooling baseline configs
- [x] `framework/tools/cs/ecs.php` — code style baseline (or equivalent)
- [x] `framework/tools/phpstan/phpstan.neon` — static analysis baseline

#### Modifies

- [x] `.github/workflows/ci.yml` — MUST include rails jobs:
  - [x] `gates` (Linux): runs gate scripts deterministically
  - [x] `arch` (Linux): deptrac generate (rerun-no-diff) + deptrac analyze + artifact upload
  - [x] `test` (Linux): `composer -d framework install` → `composer -d framework test`
  - [x] `unit+contract` (Linux): runs unit/contract suites when they exist
  - [x] `integration-fast` (Linux): fast integration suites when they exist
  - [x] `integration-slow` (Linux): slow integration suites when they exist
  - [x] `spikes` (Linux): keep Phase 0 spike rails if still applicable
  - [x] `determinism` (Linux+Windows): rerun-no-diff checks (may remain separate workflow or be moved into `ci.yml`)
  - [x] DTO specialized gates MAY run inside `gates` job or dedicated grouped step:
    - [x] `composer -d framework dto:gate`
- [x] `framework/tools/testing/phpunit.xml` — ensure canonical monorepo PHPUnit settings
- [x] `skeleton/phpunit.xml` — N/A for 1.50.0; skeleton PHPUnit is not consumed by CI yet.
- [x] `framework/tools/spikes/_support/ErrorCodes.php`
  - [x] adds `CORETSIA_NO_SKELETON_HTTP_DEFAULT_FORBIDDEN`
  - [x] adds `CORETSIA_NO_SKELETON_HTTP_DEFAULT_GATE_FAILED`
  - [x] adds `CORETSIA_NO_SKELETON_BUNDLES_DEFAULT_FORBIDDEN`
  - [x] adds `CORETSIA_NO_SKELETON_BUNDLES_DEFAULT_GATE_FAILED`
  - [x] adds `CORETSIA_NO_SKELETON_MODE_PRESETS_DEFAULT_FORBIDDEN`
  - [x] adds `CORETSIA_NO_SKELETON_MODE_PRESETS_DEFAULT_GATE_FAILED`
  - [x] adds `CORETSIA_NO_SKELETON_MODULES_DEFAULT_FORBIDDEN`
  - [x] adds `CORETSIA_NO_SKELETON_MODULES_DEFAULT_GATE_FAILED`
  - [x] adds `CORETSIA_CONTRACTS_ONLY_PORTS_FORBIDDEN`
  - [x] adds `CORETSIA_CONTRACTS_ONLY_PORTS_GATE_FAILED`
  - [x] adds `CORETSIA_TAG_CONSTANT_MIRROR_DRIFT`
  - [x] adds `CORETSIA_TAG_CONSTANT_MIRROR_GATE_FAILED`
  - [x] adds `CORETSIA_OBSERVABILITY_NAMING_DRIFT`
  - [x] adds `CORETSIA_OBSERVABILITY_NAMING_GATE_FAILED`
  - [x] adds `CORETSIA_ARTIFACT_HEADER_SCHEMA_DRIFT`
  - [x] adds `CORETSIA_ARTIFACT_HEADER_SCHEMA_GATE_FAILED`
  - [x] adds `CORETSIA_CROSS_CUTTING_CONTRACT_DRIFT`
  - [x] adds `CORETSIA_CROSS_CUTTING_CONTRACT_GATE_FAILED`
  - [x] adds `CORETSIA_KERNEL_PUBLIC_API_DRIFT`
  - [x] adds `CORETSIA_KERNEL_PUBLIC_API_GATE_FAILED`

- [x] `composer.json` — add repo-root mirror scripts (delegates to framework):
  - [x] `cross-cutting-contract:gate` → `@composer --no-interaction --working-dir=framework run-script cross-cutting-contract:gate --`
  - [x] `kernel-public-api:gate` → `@composer --no-interaction --working-dir=framework run-script kernel-public-api:gate --`
  - [x] `no-skeleton-http-default:gate` → `@composer --no-interaction --working-dir=framework run-script no-skeleton-http-default:gate --`
  - [x] `no-skeleton-mode-presets-default:gate` → `@composer --no-interaction --working-dir=framework run-script no-skeleton-mode-presets-default:gate --`
  - [x] `no-skeleton-modules-default:gate` → `@composer --no-interaction --working-dir=framework run-script no-skeleton-modules-default:gate --`
  - [x] `no-skeleton-bundles-default:gate` → `@composer --no-interaction --working-dir=framework run-script no-skeleton-bundles-default:gate --`
  - [x] `contracts-only-ports:gate` → `@composer --no-interaction --working-dir=framework run-script contracts-only-ports:gate --`
  - [x] `tag-constant-mirror:gate` → `@composer --no-interaction --working-dir=framework run-script tag-constant-mirror:gate --`
  - [x] `observability-naming:gate` → `@composer --no-interaction --working-dir=framework run-script observability-naming:gate --`
  - [x] `artifact-header-schema:gate` → `@composer --no-interaction --working-dir=framework run-script artifact-header-schema:gate --`

- [x] `framework/composer.json` — add workspace gate scripts:
  - [x] `cross-cutting-contract:gate` → `@php tools/gates/cross_cutting_contract_gate.php`
  - [x] `kernel-public-api:gate` → `@php tools/gates/kernel_public_api_gate.php`
  - [x] `no-skeleton-http-default:gate` → `@php tools/gates/no_skeleton_http_default_gate.php`
  - [x] `no-skeleton-mode-presets-default:gate` → `@php tools/gates/no_skeleton_mode_presets_default_gate.php`
  - [x] `no-skeleton-modules-default:gate` → `@php tools/gates/no_skeleton_modules_default_gate.php`
  - [x] `no-skeleton-bundles-default:gate` → `@php tools/gates/no_skeleton_bundles_default_gate.php`
  - [x] `contracts-only-ports:gate` → `@php tools/gates/contracts_only_ports_gate.php`
  - [x] `tag-constant-mirror:gate` → `@php tools/gates/tag_constant_mirror_gate.php`
  - [x] `observability-naming:gate` → `@php tools/gates/observability_naming_gate.php`
  - [x] `artifact-header-schema:gate` → `@php tools/gates/artifact_header_schema_gate.php`

#### Artifacts / outputs (if applicable)

- [x] Writes (CI artifacts only):
  - [x] dep graph artifacts (dot/svg/html) uploaded by `arch` job

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

N/A (tooling output only; must be secret-safe)

#### Security / Redaction

- [x] gates output MUST NOT print secrets; only paths + reasons (deterministic)
- [x] Gate output policy (MUST; aligned with Phase 0 rails):
  - [x] Any `framework/tools/gates/*.php` gate MUST:
    - [x] load `framework/tools/spikes/_support/bootstrap.php` before scanning
    - [x] emit output ONLY via `framework/tools/spikes/_support/ConsoleOutput.php`
    - [x] follow the canonical Phase 0 format:
      - [x] Line 1: deterministic `CODE` only
      - [x] Line 2+: stable diagnostics (`<scan-root-relative-path>: <reason>`) sorted by `strcmp`
    - [x] MUST NOT use `echo|print|var_dump|print_r|printf|error_log` or direct stdout/stderr sinks

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] Fixture locks promoted to rails (tests reference spike fixtures):
  - [x] `framework/tools/tests/Contract/SpikeDeptracYamlMatchesFixtureContractTest.php`
  - [x] `framework/tools/tests/Contract/SpikeDeptracAllowlistPolicyContractTest.php`
  - [x] `framework/tools/tests/Contract/SpikeDeptracCycleDetectionContractTest.php`
  - [x] `framework/tools/tests/Contract/SpikeWorkspacePackageIndexMatchesFixtureContractTest.php`
  - [x] `framework/tools/tests/Contract/SpikeComposerRepositoriesSyncManagedOnlyContractTest.php`
  - [x] `framework/tools/tests/Contract/SpikeComposerRepositoriesSyncWritesBackupsContractTest.php`
  - [x] `framework/tools/tests/Contract/SpikeWorkspaceSyncLockContractTest.php`

### Tests (MUST)

- Contract:
  - [x] fixture-lock contract tests listed above
- Integration:
  - [x] gates smoke run in CI job `gates`
  - [x] deptrac generate + analyze in CI job `arch`
- Gates/Arch:
  - [x] deptrac denies forbidden edges (e.g. platform → integrations)
  - [x] contracts-only ports gate blocks ports outside `core/contracts`
  - [x] skeleton defaults gates block forbidden default files
  - [x] no-skeleton-modules-default gate blocks `skeleton/config/modules.php`
  - [x] observability naming gate blocks label/name drift
  - [x] artifact header/schema gate blocks non-canonical `_meta` and non-deterministic bytes
  - [x] tag constant mirror gate blocks drift between canonical tag strings and local mirror constants

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] deps/forbidden respected (deptrac; no cycles)
- [x] Determinism: generator rerun-no-diff (arch job)
- [x] CI contains at minimum:
  - [x] `gates` job
  - [x] `arch` job (deptrac generate + analyze + artifacts upload)
  - [x] `test` job runs `composer -d framework test`
  - [x] `framework/composer.json` MUST expose the `test` script consumed by CI
- [x] Fixture-lock tests exist and fail deterministically when rules are violated
- [x] `0.40.0` internal-toolkit no-dup gate is preserved as an immutable rail.
- [x] `0.80.0` deptrac generator spike is promoted to production locks: deterministic yaml + allowlist policy + cycle detection (fixture-lock tests).
- [x] `0.100.0` workspace spike is promoted to production locks: managed-only sync + backups + lock contract tests.
- [x] When a PR adds `skeleton/config/http.php` to the default skeleton, then `no_skeleton_http_default_gate.php` fails deterministically.
- [x] When a PR adds `skeleton/config/modules.php` to the default skeleton, then `no_skeleton_modules_default_gate.php` fails deterministically.
- [x] When a PR adds `skeleton/apps/web/config/modules.php` or any `skeleton/apps/*/config/modules.php`,
  then `no_skeleton_modules_default_gate.php` fails deterministically.
- [x] Prelude rails preserved (MUST):
  - [x] CI still runs `php framework/tools/build/sync_composer_repositories.php --check` BEFORE any `composer install`
  - [x] CI uses `composer install` (NOT update) and MUST NOT modify any `composer.lock`
  - [x] Lock drift check remains enforced (job fails if any lock changed)
  - [x] Canonical repo-root entrypoints remain valid: `composer setup|ci|test`
- [x] Tooling rail supports specialized sub-gates without changing output policy or CI invariants

---

### 1.50.1 DTO Policy + Compliance Rail (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.50.1"
owner_path: "framework/tools/gates/"

goal: "Establish a canonical DTO policy rail: explicit DTO marker, DTO SSoT, CI entrypoints, deterministic aggregate execution, and shared error-code wiring for specialized DTO gates."
provides:
- "Canonical DTO SSoT and boundary vocabulary (DTO vs VO vs Descriptor vs Result)"
- "Canonical DTO marker package (`coretsia/core-dto-attribute`)"
- "Deterministic DTO rail entrypoint (`composer dto:gate`) integrated into CI"
- "Shared DTO error codes and deterministic aggregate runner for specialized DTO enforcement gates"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/dto-policy.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.20.0 — Phase 0 tooling rails bootstrap + output/error-code policy exist
  - 0.30.0 — spikes boundary gate infrastructure exists (`tools/gates/` pattern)
  - 0.40.0 — internal-toolkit exists (for deterministic path normalization if needed)
  - 1.50.0 — tooling baseline + arch-rails exist (CI jobs for deterministic gates)

- Required deliverables (exact paths):
  - `framework/tools/gates/` — gates directory exists
  - `framework/tools/spikes/_support/ConsoleOutput.php` — canonical output writer
  - `framework/tools/spikes/_support/ErrorCodes.php` — error codes registry
  - `framework/tools/spikes/_support/bootstrap.php` — tools bootstrap
  - `framework/packages/` — package tree exists (scan root)
  - `.github/workflows/ci.yml` — CI baseline exists
  - `composer.json` — repo-root scripts exist
  - `framework/composer.json` — workspace scripts exist
  - `docs/ssot/INDEX.md` — SSoT index exists and this epic appends `docs/ssot/dto-policy.md`

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

- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Policy scope (MUST)

- DTO policy scope is **explicit opt-in only**.
- A class is treated as DTO only if it is explicitly marked with:
  - `#[Coretsia\Dto\Attribute\Dto]`
- Classes without the DTO marker MUST NOT be analyzed by DTO gates.
- Absence of DTO marker means the class is outside DTO policy and may be a VO, descriptor, result model, shape model, runtime service, or any other non-DTO class.
- This rail defines **Simple transport DTO policy** only.
- This rail MUST NOT impose DTO rules on:
  - kernel shapes that intentionally carry invariants/normalization
  - contracts VOs
  - contracts descriptors/results/shapes
  - runtime services/models
  - artifact builders / config builders / container builders
  - any unmarked class

### Canonical DTO policy (single-choice) (MUST)

A compliant DTO in Coretsia Phase 1 is:

- explicitly marked with `#[Coretsia\Dto\Attribute\Dto]`
- declared as `final class`
- contains only instance properties
- every property has an explicit type declaration
- every property is `public`
- has no behavior except optional `__construct(...)`
- has no business logic
- has no I/O
- has no service dependencies
- has no inheritance-based extension model

A compliant DTO in this rail is **not**:

- a domain entity
- a service
- a policy object
- a validator
- a stateful runtime object
- a behavior-rich VO
- a descriptor/result/shape class by default
- a kernel runtime orchestrator
- an artifact/config/container builder

### Entry points / integration points (MUST)

- CLI:
  - `composer dto:gate` → repo-root aggregate DTO rail entrypoint
  - `composer -d framework dto:gate` → workspace DTO rail entrypoint
- CI:
  - DTO rail MUST be executed in Phase 1 arch rails, preferably as part of existing `gates` job

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/gates/dto_gate.php` — deterministic aggregate runner for DTO rail:
  - [ ] runs `dto_marker_consistency_gate.php`
  - [ ] runs `dto_no_logic_gate.php`
  - [ ] runs `dto_shape_gate.php`
  - [ ] preserves deterministic execution order
  - [ ] stops with non-zero exit code if any sub-gate fails
  - [ ] MUST NOT rewrite/merge sub-gate diagnostics into an alternative format
  - [ ] MUST preserve Phase 0 gate output policy
  - [ ] aggregate runner MUST stop on first failing sub-gate
  - [ ] aggregate runner MUST pass through the exact output of the first failing sub-gate unchanged
  - [ ] aggregate runner MUST NOT concatenate outputs of multiple failing sub-gates into a new composite report
  - [ ] if all sub-gates pass, exit 0 and print nothing
  - [ ] aggregate runner is supplemental and MUST NOT replace per-gate command entrypoints
  - [ ] each specialized DTO gate created by later epics MUST also be registered as its own `<command>:gate` composer script at repo root and in `framework/composer.json`

- [ ] `framework/packages/core/dto-attribute/composer.json` — DTO marker package:
  - [ ] package name: `coretsia/core-dto-attribute`
  - [ ] package kind: library-only marker package
  - [ ] no runtime deps
  - [ ] PSR-4:
    - [ ] `Coretsia\Dto\Attribute\` → `src/Attribute/`

- [ ] `framework/packages/core/dto-attribute/src/Attribute/Dto.php` — canonical DTO marker:
  - [ ] `#[Attribute(Attribute::TARGET_CLASS)]`
  - [ ] empty marker attribute
  - [ ] no parameters in Phase 1
  - [ ] no runtime behavior

- [ ] `framework/packages/core/dto-attribute/README.md` — usage and policy note:
  - [ ] explains explicit opt-in
  - [ ] explains that marking a class as DTO subjects it to DTO gates
  - [ ] explains that DTO is a narrow transport shape, not a general-purpose VO model

- [ ] `framework/packages/core/dto-attribute/tests/Contract/AttributeExistsTest.php`

- [ ] `docs/ssot/dto-policy.md` — canonical DTO SSoT:
  - [ ] already has canonical vocabulary
  - [ ] defines what a DTO is in Coretsia
  - [ ] states explicit opt-in policy
  - [ ] states attribute-first marker strategy
  - [ ] states that Phase 1 uses attribute-only detection
  - [ ] defines canonical DTO rules:
    - [ ] final class
    - [ ] public typed instance properties
    - [ ] no static properties
    - [ ] no inheritance
    - [ ] no traits
    - [ ] no interfaces
    - [ ] no methods except optional constructor
  - [ ] states what DTO is not:
    - [ ] not a domain entity
    - [ ] not a service
    - [ ] not a stateful runtime object
    - [ ] not a rich VO/shape class
  - [ ] provides compliant and non-compliant examples
  - [ ] explicitly states that unmarked classes are outside DTO gate scope
  - [ ] `## Canonical vocabulary`
    - [ ] DTO — explicit transport class, opt-in via marker, enforced by DTO gates
    - [ ] VO — value object with behavior/invariants allowed; outside DTO gate scope unless explicitly marked
    - [ ] Descriptor — canonical structured model used for cross-package/runtime boundaries; not automatically DTO
    - [ ] Result/Shape/Context model — structured contract/runtime payload; not automatically DTO
  - [ ] `## Scope rule`
    - [ ] unmarked classes are outside DTO gate scope
    - [ ] contracts VOs, descriptors, result models, artifact payload models, config trace models, and runtime services MUST NOT be treated as DTOs unless explicitly marked

- [ ] `framework/tools/tests/Integration/DtoGateAggregateRunnerTest.php` — proves aggregate runner order and failure propagation

#### Modifies

- [ ] `composer.json` — add mirror scripts (delegates to framework):
  - [ ] `dto:gate` → `@composer --no-interaction --working-dir=framework run-script dto:gate --`
- [ ] `framework/composer.json` — add gate script
  - [ ] `dto:gate` → `@php tools/gates/dto_gate.php`

- [ ] `.github/workflows/ci.yml` — add DTO rail execution:
  - [ ] runs after install and before tests
  - [ ] may run inside existing `gates` job

- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_DTO_GATE_FAILED`
  - [ ] `CORETSIA_DTO_MARKER_VIOLATION`
  - [ ] `CORETSIA_DTO_NO_LOGIC_VIOLATION`
  - [ ] `CORETSIA_DTO_SHAPE_VIOLATION`
  - [ ] `CORETSIA_DTO_GATE_SCAN_FAILED`

- [ ] `framework/composer.json` — require marker package for workspace autoload visibility:
  - [ ] add `coretsia/core-dto-attribute`

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/dto-policy.md`

#### Configuration (keys + defaults)

N/A

- [ ] This epic intentionally has **no config root** and **no runtime/workspace config toggles**.
- [ ] DTO policy is hardcoded in gates + documented in SSoT.
- [ ] Alternative DTO models (e.g. getters/withers/private-readonly DTOs/interface marker fallback) are out of scope for this rail and require a future epic/ADR.

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

- [ ] Deterministic top-level error codes reserved for DTO rail:
  - [ ] `CORETSIA_DTO_GATE_FAILED` — aggregate rail orchestration failed before any sub-gate could produce canonical diagnostics
    - [ ] MUST NOT be used for normal policy violations detected by specialized DTO gates
  - [ ] `CORETSIA_DTO_MARKER_VIOLATION` — marker consistency gate found violations
  - [ ] `CORETSIA_DTO_NO_LOGIC_VIOLATION` — no-logic gate found violations
  - [ ] `CORETSIA_DTO_SHAPE_VIOLATION` — shape gate found violations
  - [ ] `CORETSIA_DTO_GATE_SCAN_FAILED` — a DTO gate failed to initialize or scan

#### Security / Redaction

- [ ] DTO rail MUST NOT leak class contents, property values, constructor body text, or method bodies.
- [ ] Diagnostics contain only normalized relative paths and fixed reason tokens.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/tools/tests/Integration/DtoGateAggregateRunnerTest.php`
  - [ ] proves specialized gates are invoked in deterministic order
  - [ ] proves non-zero exit propagates
  - [ ] proves aggregate runner does not invent a second diagnostics format

- [ ] `framework/packages/core/dto-attribute/tests/Contract/AttributeExistsTest.php`

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/core/dto-attribute/tests/Contract/AttributeExistsTest.php`
- Integration:
  - [ ] `framework/tools/tests/Integration/DtoGateAggregateRunnerTest.php`
- Gates/Arch:
  - [ ] `.github/workflows/ci.yml` runs `composer -d framework dto:gate`

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] DTO rail is deterministic and integrated into CI
- [ ] Aggregate runner exists and invokes specialized gates in deterministic order
- [ ] Error codes are registered in `ErrorCodes.php`
- [ ] `docs/ssot/dto-policy.md` exists and is linked
- [ ] Marker package `coretsia/core-dto-attribute` exists with minimal deps
- [ ] DTO detection is attribute-only and explicit-opt-in
- [ ] Unmarked classes are outside DTO rail scope
- [ ] `README.md` of marker package explains usage and scope
- [ ] `dto_gate.php` preserves single-gate output contract by forwarding first failing sub-gate output verbatim

---

### 1.50.2 DTO Marker Consistency Gate (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.50.2"
owner_path: "framework/tools/gates/"

goal: "Ensure there is exactly one canonical DTO marker model in the monorepo and that explicitly marked DTOs use that marker consistently."
provides:
- "Deterministic marker-consistency gate for DTO opt-in policy"
- "Enforcement that Phase 1 uses exactly one DTO marker strategy"
- "Clear rejection of legacy/custom DTO marker mechanisms"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/dto-policy.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.20.0 — Phase 0 tooling rails bootstrap + output/error-code policy exist
  - 0.30.0 — spikes boundary gate infrastructure exists (`tools/gates/` pattern)
  - 0.40.0 — internal-toolkit exists
  - 1.50.0 — tooling baseline + arch-rails exist
  - 1.50.1 — DTO Policy + Compliance Rail exists (marker package + SSoT + aggregate entrypoint)

- Required deliverables (exact paths):
  - `framework/tools/gates/` — gates directory exists
  - `framework/tools/spikes/_support/ConsoleOutput.php` — canonical output writer
  - `framework/tools/spikes/_support/ErrorCodes.php` — error codes registry
  - `framework/tools/spikes/_support/bootstrap.php` — tools bootstrap
  - `framework/packages/core/dto-attribute/src/Attribute/Dto.php` — canonical DTO marker exists
  - `docs/ssot/dto-policy.md` — canonical DTO policy exists
  - `framework/packages/` — package tree exists (scan root)

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

- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Policy scope (MUST)

- Only `Coretsia\Dto\Attribute\Dto` is the canonical DTO marker in Phase 1.
- Interface markers are forbidden in Phase 1.
- Alternative/local/custom DTO marker attributes are forbidden.
- Classes without the canonical marker are ignored by DTO gates.
- Alias imports are allowed only if they resolve to canonical `Coretsia\Dto\Attribute\Dto`.
- The canonical marker declaration itself is exempt from custom-marker rejection:
  - `framework/packages/core/dto-attribute/src/Attribute/Dto.php`
- The gate MUST NEVER report the canonical marker class as:
  - `custom-dto-marker-class`
  - `multiple-dto-marker-strategies`
- The gate MUST distinguish:
  - the single allowed canonical marker declaration
  - non-canonical/local/custom DTO marker declarations

### Entry points / integration points (MUST)

- CLI:
  - `composer dto:gate` → aggregate entrypoint invokes this gate
  - `dto-marker-consistency:gate`
- CI:
  - MUST be executed as part of DTO rail inside Phase 1 tooling rails

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/gates/dto_marker_consistency_gate.php` — deterministic marker consistency gate:
  - [ ] scans `framework/packages/**/src/**/*.php`
  - [ ] excludes `**/tests/**`, `**/fixtures/**`, `**/vendor/**`
  - [ ] token-based analysis only
  - [ ] detects DTO marker usage only via canonical attribute `#[Coretsia\Dto\Attribute\Dto]`
  - [ ] allows imported alias only if alias resolves to canonical FQCN
  - [ ] forbids custom attribute classes intended as DTO markers inside monorepo
  - [ ] forbids legacy interface markers such as `DtoInterface`
  - [ ] forbids simultaneous support for multiple DTO marker strategies
  - [ ] output format follows Phase 0 gate policy:
    - [ ] line 1: `CORETSIA_DTO_MARKER_VIOLATION` if any violation exists
    - [ ] line 2+: `<scan-root-relative-normalized-path>: <reason-code>`
    - [ ] diagnostics sorted by normalized path using `strcmp`
    - [ ] if multiple violations exist in one file, each violation gets its own line
    - [ ] if no violations, exit 0 and print nothing
    - [ ] output only through `ConsoleOutput`
  - [ ] runtime roots vs scan root:
    - [ ] `$toolsRootRuntime = realpath(__DIR__ . '/..')`
    - [ ] default scan root is `$toolsRootRuntime . '/..'`
    - [ ] `--path=<dir>` overrides only scan root
    - [ ] bootstrap is always loaded from `$toolsRootRuntime . '/spikes/_support/bootstrap.php'`
  - [ ] error handling:
    - [ ] missing/unreadable bootstrap → `CORETSIA_DTO_GATE_SCAN_FAILED`
    - [ ] internal scanning/parsing failure → `CORETSIA_DTO_GATE_SCAN_FAILED`
    - [ ] any uncaught exception → same code, exit 1

- [ ] `framework/tools/tests/Integration/DtoMarkerConsistencyGateTest.php`

#### Modifies

- [ ] `composer.json` — add repo-root mirror script:
  - [ ] `dto-marker-consistency:gate` → `@composer --no-interaction --working-dir=framework run-script dto-marker-consistency:gate --`
- [ ] `framework/composer.json` — add workspace gate script:
  - [ ] `dto-marker-consistency:gate` → `@php tools/gates/dto_marker_consistency_gate.php`

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

- [ ] Deterministic top-level error codes:
  - [ ] `CORETSIA_DTO_MARKER_VIOLATION`
  - [ ] `CORETSIA_DTO_GATE_SCAN_FAILED`
- [ ] Fixed reason codes:
  - [ ] `non-canonical-dto-marker`
  - [ ] `legacy-dto-interface-marker`
  - [ ] `custom-dto-marker-class`
  - [ ] `multiple-dto-marker-strategies`

#### Security / Redaction

- [ ] Gate MUST NOT leak attribute bodies, class contents, or method/property text.
- [ ] Diagnostics contain only normalized relative paths and fixed reason tokens.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/tools/tests/Integration/DtoMarkerConsistencyGateTest.php`:
  - [ ] canonical marker usage passes
  - [ ] alias import resolving to canonical marker passes
  - [ ] custom DTO marker attribute fails with `custom-dto-marker-class`
  - [ ] legacy interface marker fails with `legacy-dto-interface-marker`
  - [ ] mixed marker strategy in same synthetic tree fails with `multiple-dto-marker-strategies`
  - [ ] `--path` override works on synthetic tree
  - [ ] missing bootstrap triggers `CORETSIA_DTO_GATE_SCAN_FAILED`

### Tests (MUST)

- Integration:
  - [ ] `framework/tools/tests/Integration/DtoMarkerConsistencyGateTest.php`

### DoD (MUST)

- [ ] Deliverables complete (creates), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] Exactly one DTO marker strategy exists in Phase 1
- [ ] Gate is deterministic and integrated via DTO rail
- [ ] Any future alternate marker requires ADR + SSoT update + gate update

---

### 1.50.3 DTO No-Logic Gate (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.50.3"
owner_path: "framework/tools/gates/"

goal: "Ensure explicitly marked DTOs contain no executable behavior beyond trivial construction and remain pure transport objects."
provides:
- "Deterministic no-logic gate for explicitly marked DTOs"
- "Enforcement that DTO construction remains trivial and transport-only"
- "Clear rejection of normalization, validation, branching, and non-trivial constructor behavior inside DTOs"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/dto-policy.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.20.0 — Phase 0 tooling rails bootstrap + output/error-code policy exist
  - 0.30.0 — spikes boundary gate infrastructure exists (`tools/gates/` pattern)
  - 0.40.0 — internal-toolkit exists
  - 1.50.0 — tooling baseline + arch-rails exist
  - 1.50.1 — DTO Policy + Compliance Rail exists
  - 1.50.2 — DTO Marker Consistency Gate exists

- Required deliverables (exact paths):
  - `framework/tools/gates/` — gates directory exists
  - `framework/tools/spikes/_support/ConsoleOutput.php` — canonical output writer
  - `framework/tools/spikes/_support/ErrorCodes.php` — error codes registry
  - `framework/tools/spikes/_support/bootstrap.php` — tools bootstrap
  - `framework/packages/core/dto-attribute/src/Attribute/Dto.php` — canonical DTO marker exists
  - `docs/ssot/dto-policy.md` — canonical DTO policy exists
  - `framework/packages/` — package tree exists (scan root)

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

- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Policy scope (MUST)

- DTO may define no methods except optional `__construct`.
- If `__construct` exists, it may only:
  - assign constructor parameters to `$this-><property>`
  - use property promotion
- Constructor MUST NOT:
  - call functions
  - call instance methods
  - call static methods
  - allocate non-trivial objects
  - contain control flow
  - contain loops
  - contain `try/catch`
  - contain `throw`
  - perform I/O
- Examples of forbidden constructor behavior include:
  - normalization
  - validation
  - `trim(...)`
  - `strtolower(...)`
  - `filter_var(...)`
  - creating nested services
  - branching
  - exception throwing for business rules
- trivial assignment means exactly `$this->property = $parameter;`
- right-hand side MUST be a direct constructor parameter variable, not an expression
- left-hand side MUST be a direct instance property of the same DTO
- null-coalescing, ternary, concatenation, arithmetic, array literals, casts, and any computed expression are forbidden in DTO constructor body

### Entry points / integration points (MUST)

- CLI:
  - `composer dto:gate` → aggregate entrypoint invokes this gate
  - `dto-no-logic:gate`
- CI:
  - MUST be executed as part of DTO rail inside Phase 1 tooling rails

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/gates/dto_no_logic_gate.php` — deterministic no-logic gate:
  - [ ] scans `framework/packages/**/src/**/*.php`
  - [ ] excludes `**/tests/**`, `**/fixtures/**`, `**/vendor/**`
  - [ ] analyzes only explicitly marked DTO classes
  - [ ] token-based analysis only
  - [ ] allowed constructor patterns:
    - [ ] property promotion only
    - [ ] trivial assignments `$this->prop = $param;`
  - [ ] fixed reason codes:
    - [ ] `disallowed-method` — DTO method other than `__construct` exists
    - [ ] `constructor-calls-function`
    - [ ] `constructor-calls-method`
    - [ ] `constructor-static-call`
    - [ ] `constructor-control-flow`
    - [ ] `constructor-loop`
    - [ ] `constructor-try-catch`
    - [ ] `constructor-throw`
    - [ ] `constructor-new-object`
    - [ ] `constructor-nontrivial-body`
  - [ ] output format follows Phase 0 gate policy:
    - [ ] line 1: `CORETSIA_DTO_NO_LOGIC_VIOLATION` if any violation exists
    - [ ] line 2+: `<scan-root-relative-normalized-path>: <reason-code>`
    - [ ] diagnostics sorted by normalized path using `strcmp`
    - [ ] if multiple violations exist in one file, each violation gets its own line
    - [ ] if no violations, exit 0 and print nothing
    - [ ] output only through `ConsoleOutput`
  - [ ] runtime roots vs scan root:
    - [ ] `$toolsRootRuntime = realpath(__DIR__ . '/..')`
    - [ ] default scan root is `$toolsRootRuntime . '/..'`
    - [ ] `--path=<dir>` overrides only scan root
    - [ ] bootstrap is always loaded from `$toolsRootRuntime . '/spikes/_support/bootstrap.php'`
  - [ ] error handling:
    - [ ] missing/unreadable bootstrap → `CORETSIA_DTO_GATE_SCAN_FAILED`
    - [ ] internal scanning/parsing failure → `CORETSIA_DTO_GATE_SCAN_FAILED`
    - [ ] any uncaught exception → same code, exit 1

- [ ] `framework/tools/tests/Integration/DtoNoLogicGateTest.php`

#### Modifies

- [ ] `composer.json` — add repo-root mirror script:
  - [ ] `dto-no-logic:gate` → `@composer --no-interaction --working-dir=framework run-script dto-no-logic:gate --`
- [ ] `framework/composer.json` — add workspace gate script:
  - [ ] `dto-no-logic:gate` → `@php tools/gates/dto_no_logic_gate.php`

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

- [ ] Deterministic top-level error codes:
  - [ ] `CORETSIA_DTO_NO_LOGIC_VIOLATION`
  - [ ] `CORETSIA_DTO_GATE_SCAN_FAILED`
- [ ] Fixed reason codes:
  - [ ] `disallowed-method`
  - [ ] `constructor-calls-function`
  - [ ] `constructor-calls-method`
  - [ ] `constructor-static-call`
  - [ ] `constructor-control-flow`
  - [ ] `constructor-loop`
  - [ ] `constructor-try-catch`
  - [ ] `constructor-throw`
  - [ ] `constructor-new-object`
  - [ ] `constructor-nontrivial-body`

#### Security / Redaction

- [ ] Gate MUST NOT leak class contents, property values, constructor body text, or method bodies.
- [ ] Diagnostics contain only normalized relative paths and fixed reason tokens.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/tools/tests/Integration/DtoNoLogicGateTest.php`:
  - [ ] DTO with no constructor passes
  - [ ] DTO with promoted public typed properties passes
  - [ ] DTO with trivial assignment constructor passes
  - [ ] DTO with extra method fails with `disallowed-method`
  - [ ] constructor with `trim(...)` fails with `constructor-calls-function`
  - [ ] constructor with `$this->helper()` fails with `constructor-calls-method`
  - [ ] constructor with `self::normalize()` fails with `constructor-static-call`
  - [ ] constructor with `if`/`match` fails with `constructor-control-flow`
  - [ ] constructor with loop fails with `constructor-loop`
  - [ ] constructor with `try/catch` fails with `constructor-try-catch`
  - [ ] constructor with `throw` fails with `constructor-throw`
  - [ ] constructor with `new DateTimeImmutable(...)` fails with `constructor-new-object`
  - [ ] `--path` override works on synthetic tree
  - [ ] missing bootstrap triggers `CORETSIA_DTO_GATE_SCAN_FAILED`

### Tests (MUST)

- Integration:
  - [ ] `framework/tools/tests/Integration/DtoNoLogicGateTest.php`

### DoD (MUST)

- [ ] Deliverables complete (creates), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] Gate is deterministic and integrated via DTO rail
- [ ] DTO constructor semantics are narrow and enforceable
- [ ] DTO behavior cannot drift into VO/service territory without failing the gate

---

### 1.50.4 DTO Shape Gate (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.50.4"
owner_path: "framework/tools/gates/"

goal: "Ensure explicitly marked DTOs follow the canonical structural shape: final class, no inheritance/traits/interfaces, and public typed instance properties only."
provides:
- "Deterministic structural DTO shape gate"
- "Enforcement of narrow Phase 1 DTO class form"
- "Clear rejection of inheritance, traits, interfaces, static state, and non-public or untyped properties in DTOs"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/dto-policy.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.20.0 — Phase 0 tooling rails bootstrap + output/error-code policy exist
  - 0.30.0 — spikes boundary gate infrastructure exists (`tools/gates/` pattern)
  - 0.40.0 — internal-toolkit exists
  - 1.50.0 — tooling baseline + arch-rails exist
  - 1.50.1 — DTO Policy + Compliance Rail exists
  - 1.50.2 — DTO Marker Consistency Gate exists

- Required deliverables (exact paths):
  - `framework/tools/gates/` — gates directory exists
  - `framework/tools/spikes/_support/ConsoleOutput.php` — canonical output writer
  - `framework/tools/spikes/_support/ErrorCodes.php` — error codes registry
  - `framework/tools/spikes/_support/bootstrap.php` — tools bootstrap
  - `framework/packages/core/dto-attribute/src/Attribute/Dto.php` — canonical DTO marker exists
  - `docs/ssot/dto-policy.md` — canonical DTO policy exists
  - `framework/packages/` — package tree exists (scan root)

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

- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Policy scope (MUST)

- Gate scope is explicit opt-in only.
- A class is treated as DTO only if it is explicitly marked with:
  - `#[Coretsia\Dto\Attribute\Dto]`
- Classes without the DTO marker MUST NOT be analyzed by this gate.
- A compliant Phase 1 DTO shape:
  - is a class (not trait/interface/enum)
  - is `final`
  - is not `abstract`
  - does not `extends`
  - does not `implements`
  - does not use traits
  - declares no static properties
  - declares only typed instance properties
  - declares only public properties
  - allows promoted properties only if they are public and typed

### Entry points / integration points (MUST)

- CLI:
  - `composer dto:gate` → aggregate entrypoint invokes this gate
  - `dto-shape:gate`
- CI:
  - MUST be executed as part of DTO rail inside Phase 1 tooling rails

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/gates/dto_shape_gate.php` — deterministic shape gate:
  - [ ] scans `framework/packages/**/src/**/*.php`
  - [ ] excludes `**/tests/**`, `**/fixtures/**`, `**/vendor/**`
  - [ ] analyzes only explicitly marked DTO classes
  - [ ] token-based analysis only
  - [ ] validates:
    - [ ] DTO is a class, not interface/trait/enum
    - [ ] DTO is `final`
    - [ ] DTO is not `abstract`
    - [ ] DTO does not `extends`
    - [ ] DTO does not `implements`
    - [ ] DTO does not `use` traits
    - [ ] DTO does not declare static properties
    - [ ] every declared property is typed
    - [ ] every declared property is public
    - [ ] promoted properties are public and typed
  - [ ] fixed reason codes:
    - [ ] `not-final`
    - [ ] `abstract-class`
    - [ ] `extends-class`
    - [ ] `implements-interface`
    - [ ] `uses-trait`
    - [ ] `static-property`
    - [ ] `untyped-property`
    - [ ] `non-public-property`
  - [ ] output format follows Phase 0 gate policy:
    - [ ] line 1: `CORETSIA_DTO_SHAPE_VIOLATION` if any violation exists
    - [ ] line 2+: `<scan-root-relative-normalized-path>: <reason-code>`
    - [ ] diagnostics sorted by normalized path using `strcmp`
    - [ ] if multiple violations exist in one file, each violation gets its own line
    - [ ] if no violations, exit 0 and print nothing
    - [ ] output only through `ConsoleOutput`
  - [ ] runtime roots vs scan root:
    - [ ] `$toolsRootRuntime = realpath(__DIR__ . '/..')`
    - [ ] default scan root is `$toolsRootRuntime . '/..'`
    - [ ] `--path=<dir>` overrides only scan root
    - [ ] bootstrap is always loaded from `$toolsRootRuntime . '/spikes/_support/bootstrap.php'`
  - [ ] error handling:
    - [ ] missing/unreadable bootstrap → `CORETSIA_DTO_GATE_SCAN_FAILED`
    - [ ] internal scanning/parsing failure → `CORETSIA_DTO_GATE_SCAN_FAILED`
    - [ ] any uncaught exception → same code, exit 1

- [ ] `framework/tools/tests/Integration/DtoShapeGateTest.php`

#### Modifies

- [ ] `composer.json` — add repo-root mirror script:
  - [ ] `dto-shape:gate` → `@composer --no-interaction --working-dir=framework run-script dto-shape:gate --`
- [ ] `framework/composer.json` — add workspace gate script:
  - [ ] `dto-shape:gate` → `@php tools/gates/dto_shape_gate.php`

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

- [ ] Deterministic top-level error codes:
  - [ ] `CORETSIA_DTO_SHAPE_VIOLATION`
  - [ ] `CORETSIA_DTO_GATE_SCAN_FAILED`
- [ ] Fixed reason codes:
  - [ ] `not-final`
  - [ ] `abstract-class`
  - [ ] `extends-class`
  - [ ] `implements-interface`
  - [ ] `uses-trait`
  - [ ] `static-property`
  - [ ] `untyped-property`
  - [ ] `non-public-property`

#### Security / Redaction

- [ ] Gate MUST NOT leak class contents, property values, constructor body text, or method bodies.
- [ ] Diagnostics contain only normalized relative paths and fixed reason tokens.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/tools/tests/Integration/DtoShapeGateTest.php`:
  - [ ] compliant DTO with public typed properties passes
  - [ ] compliant DTO with public promoted typed properties passes
  - [ ] abstract DTO fails with `abstract-class`
  - [ ] non-final DTO fails with `not-final`
  - [ ] DTO extending another class fails with `extends-class`
  - [ ] DTO implementing interface fails with `implements-interface`
  - [ ] DTO using trait fails with `uses-trait`
  - [ ] DTO with static property fails with `static-property`
  - [ ] DTO with untyped property fails with `untyped-property`
  - [ ] DTO with non-public property fails with `non-public-property`
  - [ ] unmarked class is ignored
  - [ ] `--path` override works on synthetic tree
  - [ ] missing bootstrap triggers `CORETSIA_DTO_GATE_SCAN_FAILED`

### Tests (MUST)

- Integration:
  - [ ] `framework/tools/tests/Integration/DtoShapeGateTest.php`

### DoD (MUST)

- [ ] Deliverables complete (creates), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] Gate is deterministic and integrated via DTO rail
- [ ] Shape policy is narrow, explicit, and enforceable
- [ ] DTOs cannot drift into inheritance/trait/interface/static-state models without failing the gate

---

### 1.60.0 Package Compliance Gates (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.60.0"
owner_path: "framework/tools/gates/"

goal: "It is impossible to create/merge a non-canonical package; the gate fails deterministically with a clear reason."
provides:
- "Enforceable SSoT rules for package shape (path/composer/psr-4/metadata/config/rules/README/contracts)"
- "Module metadata enforcement for runtime packages"
- "Deterministic errors for non-compliant packages"
- "Single-choice grandfathering via an explicit allowlist (must shrink over time)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/architecture/PACKAGING.md"
- "docs/ssot/config-roots.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.20.0 — packaging strategy locked (`docs/architecture/PACKAGING.md`)
  - 1.20.0 — Config roots registry exists (ownership + invariants)
  - 1.50.0 — tooling rails exist (CI runs gates)

- Required deliverables (exact paths):
  - `docs/architecture/PACKAGING.md` — single-choice path/composer/namespace mapping source
  - `docs/ssot/config-roots.md` — config invariants source used by compliance rules

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (tools)

Forbidden:

- N/A

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- CLI:
  - `composer package-scaffold:sync -- <path>` → repo-root mirror scaffold sync runner
  - `composer package-scaffold:check -- <path>` → repo-root mirror scaffold check runner
  - `composer -d framework package-scaffold:sync -- <path>` → workspace scaffold sync runner
  - `composer -d framework package-scaffold:check -- <path>` → workspace scaffold check runner
  - `composer package-compliance:gate -- <path>` → repo-root mirror gate runner
  - `composer -d framework package-compliance:gate -- <path>` → workspace gate runner

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/build/sync_package_scaffold.php` — package scaffold sync tool:
  - [ ] scans packages under `framework/packages/*/*`
  - [ ] supports apply mode and `--check`
  - [ ] exact-canonical sync is allowed only for:
    - [ ] `LICENSE`
    - [ ] `NOTICE`
  - [ ] create-if-missing only (MUST NOT overwrite existing user-owned content):
    - [ ] `README.md`
    - [ ] `tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
    - [ ] runtime-only scaffold files/directories
  - [ ] MUST NOT rewrite editable package code/docs/config once present
  - [ ] MUST be rerun-no-diff
- [ ] `framework/tools/gates/package_compliance_gate.php` — read-only package compliance gate:
  - [ ] Allowlist application (single-choice):
    - [ ] `package_compliance_allowlist.php` is the ONLY grandfathering mechanism
    - [ ] allowlisted package_ids MAY be exempt only from explicitly gated strict rules
    - [ ] allowlist loading and matching MUST be deterministic
  - [ ] scans publishable packages under `framework/packages/*/*`
  - [ ] deterministic iteration/order only (`strcmp`, locale-independent)
  - [ ] MUST NOT create, modify, or delete files
  - [ ] MUST fail on missing required package scaffold artifacts
  - [ ] MUST fail on missing or drifted canonical legal files
  - [ ] MUST validate package shape by package kind (`library|runtime`) from `composer.json > extra.coretsia.kind`
  - [ ] Required artifacts for every package:
    - [ ] `composer.json`
    - [ ] `README.md`
    - [ ] `LICENSE`
    - [ ] `NOTICE`
    - [ ] `src/`
    - [ ] `tests/Contract/`
    - [ ] `tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] Canonical legal files (single-choice; exact match required):
    - [ ] package `LICENSE` MUST be byte-identical to repo root `LICENSE`
    - [ ] package `NOTICE` MUST be byte-identical to repo root `NOTICE`
  - [ ] `composer.json` checks for every package:
    - [ ] valid JSON object
    - [ ] `"name"` MUST equal `coretsia/<layer>-<slug>`
    - [ ] `"type"` MUST equal `library`
    - [ ] `"license"` MUST equal `Apache-2.0`
    - [ ] `autoload.psr-4` MUST contain the canonical package root namespace mapped to `src/`
    - [ ] `extra.coretsia.kind` MUST exist and be exactly `library` or `runtime`
  - [ ] Runtime package checks (`extra.coretsia.kind = runtime`):
    - [ ] `src/Module/` exists
    - [ ] `src/Provider/` exists
    - [ ] `src/Module/<StudlySlug>Module.php` exists
    - [ ] `src/Provider/<StudlySlug>ServiceProvider.php` exists
    - [ ] `config/` exists
    - [ ] `config/<slug>.php` exists
    - [ ] `config/rules.php` exists
    - [ ] `extra.coretsia.moduleId` MUST equal `<layer>.<slug>`
    - [ ] `extra.coretsia.moduleClass` MUST equal canonical runtime module FQCN
    - [ ] `extra.coretsia.providers` MUST include canonical runtime provider FQCN
    - [ ] `extra.coretsia.defaultsConfigPath` MUST equal `config/<slug>.php`
  - [ ] Package identity / slug checks:
    - [ ] package path MUST match canonical `framework/packages/<layer>/<slug>/`
    - [ ] forbidden slugs: `app|modules|shared`
    - [ ] reserved slugs: `kernel`, `observability`
      - [ ] reserved means these slugs are blocked for arbitrary new packages
      - [ ] reserved slugs MAY be used only by canonical owner packages/paths defined by roadmap/SSoT
  - [ ] README checks:
    - [ ] `README.md` MUST include at minimum:
      - [ ] `## Observability`
      - [ ] `## Errors`
      - [ ] `## Security / Redaction`
  - [ ] Runtime config shape checks (`extra.coretsia.kind = runtime`):
    - [ ] `config/<slug>.php` MUST exist at the canonical path
    - [ ] `config/<slug>.php` MUST return a plain array subtree (no wrapper root)
    - [ ] `config/rules.php` MUST exist
    - [ ] `config/rules.php` MUST return a plain array
  - [ ] Runtime metadata enforcement is part of package compliance (single-choice):
    - [ ] canonical runtime metadata rules MUST be enforced directly by `package_compliance_gate.php`
    - [ ] `module_descriptor_metadata_gate.php` is not created by this epic
    - [ ] runtime metadata MUST NOT be defined or validated in multiple independent gates in this epic
  - [ ] Library package checks (`extra.coretsia.kind = library`):
    - [ ] runtime-only files/directories MUST NOT be required
    - [ ] runtime-only metadata MUST NOT be required
  - [ ] Gate output / diagnostics:
    - [ ] read-only only; no writes
    - [ ] deterministic diagnostics only
    - [ ] relative paths only; no absolute paths
    - [ ] non-zero exit on compliance violations
- [ ] `framework/tools/gates/package_compliance_allowlist.php` — explicit grandfathering list (single-choice):
  - [ ] lists canonical package_ids `<layer>/<slug>` temporarily exempt from strict rules
  - [ ] MUST be deterministic and sorted by `strcmp`
  - [ ] MUST contain data only
  - [ ] MUST NOT contain validation logic, derivation rules, or package-shape policy
  - [ ] policy: allowlist MUST ONLY shrink over time; additions require an explicit epic/ADR justification

- [ ] `framework/tools/tests/Integration/PackageComplianceGateAcceptsGoodFixtureTest.php`
- [ ] `framework/tools/tests/Integration/PackageComplianceGateRejectsBadFixtureTest.php`
- [ ] `framework/tools/tests/Integration/SyncPackageScaffoldCreatesMissingFilesTest.php`
- [ ] `framework/tools/tests/Integration/SyncPackageScaffoldCheckRejectsDriftTest.php`

#### Modifies

- [ ] `framework/tools/build/new-package.php`
  - [ ] MUST continue to create the canonical baseline package scaffold
  - [ ] MUST create required package artifacts for new packages:
    - [ ] `composer.json`
    - [ ] `README.md`
    - [ ] `src/`
    - [ ] `tests/Contract/`
    - [ ] `tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] for `kind=runtime`, MUST create required runtime scaffold artifacts:
    - [ ] `src/Module/<StudlySlug>Module.php`
    - [ ] `src/Provider/<StudlySlug>ServiceProvider.php`
    - [ ] `config/<slug>.php`
    - [ ] `config/rules.php`
  - [ ] MUST ensure `LICENSE` and `NOTICE` are present in newly created packages
  - [ ] MUST invoke `framework/tools/build/sync_package_scaffold.php` for the newly created package after scaffold creation
  - [ ] MUST NOT duplicate canonical scaffold-sync policy internally
  - [ ] package scaffold completion logic MUST remain single-choice in `sync_package_scaffold.php`
- [ ] `composer.json` — add repo-root mirror scripts:
  - [ ] `package-scaffold:sync` → `@composer --no-interaction --working-dir=framework run-script package-scaffold:sync --`
  - [ ] `package-scaffold:check` → `@composer --no-interaction --working-dir=framework run-script package-scaffold:check --`
  - [ ] `package-compliance:gate` → `@composer --no-interaction --working-dir=framework run-script package-compliance:gate --`
- [ ] `framework/composer.json` — add workspace scripts:
  - [ ] `package-scaffold:sync` → `@php tools/build/sync_package_scaffold.php`
  - [ ] `package-scaffold:check` → `@php tools/build/sync_package_scaffold.php --check`
  - [ ] `package-compliance:gate` → `@php tools/gates/package_compliance_gate.php`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Security / Redaction

- [ ] gate output MUST NOT contain secrets; only package path + mismatch reason

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] Rejects missing required files deterministically (e.g. missing `config/rules.php`)
- [ ] Rejects invalid composer/path mapping deterministically
- [ ] Allowlist is loaded deterministically and applied consistently
- [ ] Fixtures used by gate integration tests are committed and deterministic:
  - [ ] `framework/tools/tests/Fixtures/package_good/**`
  - [ ] `framework/tools/tests/Fixtures/package_bad/**`
- [ ] `sync_package_scaffold.php --check` rejects missing canonical legal files deterministically
- [ ] `sync_package_scaffold.php --check` rejects drifted `LICENSE` / `NOTICE` deterministically
- [ ] `sync_package_scaffold.php` creates missing required scaffold files without rewriting existing user-owned content

### Tests (MUST)

- Integration:
  - [ ] `framework/tools/tests/Integration/SyncPackageScaffoldCreatesMissingFilesTest.php`
  - [ ] `framework/tools/tests/Integration/SyncPackageScaffoldCheckRejectsDriftTest.php`
  - [ ] `framework/tools/tests/Integration/PackageComplianceGateAcceptsGoodFixtureTest.php`
  - [ ] `framework/tools/tests/Integration/PackageComplianceGateRejectsBadFixtureTest.php`

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] Gate enforces (single-choice; MUST match `docs/architecture/PACKAGING.md`):
  - [ ] canonical path: `framework/packages/<layer>/<slug>/`
  - [ ] canonical composer name mapping (per PACKAGING):
    - [ ] `composer=coretsia/<layer>-<slug>`
  - [ ] forbidden slugs: `app|modules|shared`
  - [ ] reserved slugs: `kernel`, `observability`
    - [ ] reserved means these slugs are blocked for arbitrary new packages
    - [ ] they MAY be used only by the canonical owner packages/paths defined by roadmap/SSoT
    - [ ] example: existing canonical package `framework/packages/core/kernel/` remains valid and MUST NOT fail package-compliance because of the reserved-slug rule
  - [ ] runtime packages require module metadata:
    - [ ] `extra.coretsia.moduleId`
    - [ ] `extra.coretsia.moduleClass`
    - [ ] `extra.coretsia.providers`
    - [ ] `extra.coretsia.defaultsConfigPath`
    - [ ] `extra.coretsia.moduleId` MUST equal canonical `<layer>.<slug>` derived from package path
    - [ ] `extra.coretsia.moduleClass` MUST point to canonical `src/Module/<StudlySlug>Module.php`
  - [ ] config files return plain arrays (no wrapper root)
  - [ ] if `kind=runtime` then required skeleton files exist (shape enforcement):
    - [ ] `src/Module/<StudlySlug>Module.php`
    - [ ] `src/Provider/<StudlySlug>ServiceProvider.php`
    - [ ] `config/<slug>.php`
    - [ ] `config/rules.php`
    - [ ] `README.md` (must include: Observability / Errors / Security-Redaction)
  - [ ] Single source of truth (MUST):
    - [ ] All package identity rules enforced by this gate MUST be derived from `docs/architecture/PACKAGING.md` (no ad-hoc alternative rules)
- [ ] Clear deterministic error messages
- [ ] PSR-4 autoload mapping is canonical and matches `docs/architecture/PACKAGING.md`
- [ ] Allowlist exists and is the ONLY grandfathering mechanism; additions are forbidden without an explicit epic/ADR
- [ ] library-only support packages (e.g. contracts support, marker attributes, pure support libraries) are allowed without runtime module metadata when explicitly canonical in PACKAGING/epic ownership
  - [ ] example: `framework/packages/core/dto-attribute/`

---

### 1.70.0 Contracts: Module / Descriptor / Manifest + ModePreset ports (MUST) [CONTRACTS]

---
type: package
phase: 1
epic_id: "1.70.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Kernel can build a deterministic ModulePlan from composer metadata + preset files using only stable contracts ports and VOs."
provides:
- "Canonical moduleId format `<layer>.<slug>` and module descriptor VO"
- "Ports for reading installed module manifests and loading mode presets (format-neutral)"
- "Hard contracts boundary: no filesystem scan required; no runtime wiring in contracts"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0001-module-descriptor-manifest-modepreset-ports.md"
ssot_refs:
- "docs/ssot/modules-and-manifests.md"
- "docs/ssot/modes.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists
  - PRELUDE.20.0 — packaging strategy locked (layer/slug conventions exist)
  - PRELUDE.30.0 — repo skeleton + composer roots + monorepo PHPUnit harness exist
  - 0.100.0 — workspace package-index prototype exists (canonical `{layer, slug, path, composerName, psr4, kind, moduleClass?}` shape is cemented)

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists
  - `docs/ssot/modules-and-manifests.md` — SSoT entry exists or will be created by this epic
  - `docs/ssot/modes.md` — SSoT entry exists or will be created by this epic
  - `framework/composer.json` — workspace root exists
  - `framework/tools/testing/phpunit.xml` — monorepo PHPUnit harness exists

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none (defines ports/VO)

#### Lock sources (Phase 0 spikes) (MUST)

- 0.100.0 — workspace package-index builder shape and deterministic ordering rules (byte-order `strcmp`)
- 0.20.0 — no-secrets policy baseline (contracts MUST NOT force storing raw secrets)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (contracts define ports/VO)

Forbidden:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*` (contracts MUST remain format-neutral)
- any vendor concretes (PDO/Redis/S3/Prometheus namespaces) in contracts ports

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- CLI (future owner `platform/cli`):
  - `coretsia debug:modules` → uses `ManifestReaderInterface` + `ModePresetLoaderInterface`
- Artifacts:
  - consumed later by kernel artifact pipeline (not owned here)

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/Module/ModuleInterface.php` — module contract (`descriptor(): ModuleDescriptor`)
- [ ] `framework/packages/core/contracts/src/Module/ModuleId.php` — moduleId VO (validation + normalization rules; no locale)
- [ ] `framework/packages/core/contracts/src/Module/ModuleDescriptor.php` — descriptor VO (schemaVersion + metadata + runtime fields)
  - [ ] only scalar/json-like fields
  - [ ] no closures/resources/objects in exported metadata
  - [ ] deterministic map ordering expectation for any exported array-like methods
- [ ] `framework/packages/core/contracts/src/Module/ManifestReaderInterface.php` — port: read installed module descriptors
- [ ] `framework/packages/core/contracts/src/Module/ModePresetInterface.php` — port: preset shape accessor
- [ ] `framework/packages/core/contracts/src/Module/ModePresetLoaderInterface.php` — port: load preset by name
- [ ] `framework/packages/core/contracts/src/Module/Capability/` — marker interfaces folder (no logic)
- [ ] `framework/packages/core/contracts/src/Module/Capability/CapabilityInterface.php`

- [ ] `docs/adr/ADR-0001-module-descriptor-manifest-modepreset-ports.md`
- [ ] `docs/ssot/modules-and-manifests.md` — moduleId format, descriptor schemaVersion policy, “metadata-only discovery” MUST include:
  - [ ] Descriptor boundary clarification:
    - [ ] `ModuleDescriptor` is a contracts descriptor/VO, not a DTO-marker class by default
    - [ ] descriptor invariants are governed by contracts shape rules, not DTO gate rules
- [ ] `docs/ssot/modes.md` — preset format + meaning (micro/express/hybrid/enterprise)
- [ ] `docs/adr/INDEX.md` — ADR navigation entrypoint (append-only index)
  - [ ] register `docs/adr/ADR-0001-module-descriptor-manifest-modepreset-ports.md`

- [ ] `framework/packages/core/contracts/tests/Unit/ModuleIdFormatTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ModuleDescriptorSchemaVersionTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ModuleDescriptorIdIsDerivedFromLayerAndSlugTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ContractsDoNotDependOnPlatformTest.php`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/modules-and-manifests.md`
  - [ ] `docs/ssot/modes.md`

#### Package skeleton (if type=package)

N/A (kind=library; no module/provider/config/rules required for `core/contracts`)

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

#### Security / Redaction

- [ ] Contracts contain no secret values and MUST NOT leak secrets from composer metadata / env.

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (contracts-only; proven by contract tests listed below)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/core/contracts/tests/Unit/ModuleIdFormatTest.php`
- Contract:
  - [ ] `framework/packages/core/contracts/tests/Contract/ModuleDescriptorSchemaVersionTest.php`
  - [ ] `framework/packages/core/contracts/tests/Contract/ModuleDescriptorIdIsDerivedFromLayerAndSlugTest.php`
  - [ ] `framework/packages/core/contracts/tests/Contract/ContractsDoNotDependOnPlatformTest.php`

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] No forbidden deps in contracts (static scan/deptrac)
- [ ] Tests green
- [ ] Docs match contracts shape (`modules-and-manifests.md`, `modes.md`)
- [ ] Non-goals / out of scope:
  - [ ] Module discovery is NOT implemented in contracts (Kernel `ComposerManifestReader` is responsible).
  - [ ] No runtime wiring / DI tags in contracts.
  - [ ] No HTTP/PSR-7 types in contracts (format-neutral hard rule).
- [ ] Runtime expectation (policy, NOT deps):
  - [ ] Kernel implements `ManifestReaderInterface` using the Phase 0 workspace package-index shape (0.100.0) as the canonical source of `{layer, slug}`.
  - [ ] Composer metadata is allowed only for OPTIONAL fields (e.g. `moduleClass`), but MUST NOT affect `moduleId` derivation.
- [ ] Determinism invariants (cemented; Phase 0 alignment):
  - [ ] Any API returning a *set/list* of modules/descriptors MUST define stable ordering:
    - [ ] sort by `moduleId` ascending using byte-order (`strcmp`), locale-independent
  - [ ] `moduleId` derivation MUST be purely metadata-based:
    - [ ] derived from `{layer, slug}` only (no filesystem scan requirement in contracts)
    - [ ] MUST NOT rely on locale/`setlocale`/`LC_ALL`
- [ ] Lock-source alignment:
  - [ ] module identity rules MUST NOT contradict Phase 0 workspace package-index fields and ordering (0.100.0)

---

### 1.80.0 Contracts: Config + Env + source tracking + directives invariants (MUST) [CONTRACTS]

---
type: package
phase: 1
epic_id: "1.80.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Kernel can merge config deterministically and produce a safe explain trace that uses only stable source types and never prints secret values."
provides:
- "Format-neutral ports for config/env access (no filesystem coupling)"
- "Stable source-tracking model (ConfigValueSource / ConfigSourceType)"
- "Cemented directives allowlist + invariants for deterministic merge/validation"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0002-config-env-source-tracking-directives-invariants.md"
ssot_refs:
- "docs/ssot/config-and-env.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists
  - PRELUDE.20.0 — packaging strategy locked (composer/namespace mapping is single-choice)
  - PRELUDE.30.0 — repo skeleton + composer roots + monorepo PHPUnit harness exist
  - 0.70.0 — PayloadNormalizer + FloatPolicy prototype is cemented (json-like model; floats forbidden)
  - 0.90.0 — Two-phase config merge + directives + safe explain prototype is cemented
  - 0.60.0 — tracked_env semantics (missing vs empty) are cemented in spikes
  - 1.50.1 — DTO policy SSoT exists (`docs/ssot/dto-policy.md`)

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists
  - `framework/composer.json` — tooling workspace root exists
  - `framework/tools/testing/phpunit.xml` — monorepo PHPUnit harness exists
  - `docs/ssot/dto-policy.md` — canonical DTO vocabulary source for descriptor/result/shape/model terminology
  - `docs/adr/INDEX.md` — ADR index exists and this epic only appends its ADR registration

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none (defines ports/VO)

#### Lock sources (Phase 0 spikes) (MUST)

- 0.90.0 — directives allowlist + reserved namespace guard + exclusive-level rule + error precedence + empty-array rule
- 0.70.0 — json-like value constraints MUST include float-forbidden
- 0.60.0 — missing vs empty MUST be distinguishable for env-derived inputs

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (contracts define ports/VO)

Forbidden:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- vendor concretes in contracts ports

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- CLI (future owner `platform/cli`):
  - `coretsia config:debug|config:validate|config:compile` → uses these ports

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/Config/ConfigRepositoryInterface.php`
- [ ] `framework/packages/core/contracts/src/Config/ConfigLoaderInterface.php`
- [ ] `framework/packages/core/contracts/src/Config/MergeStrategyInterface.php`
- [ ] `framework/packages/core/contracts/src/Config/ConfigValidatorInterface.php`
- [ ] `framework/packages/core/contracts/src/Config/ConfigSourceType.php`
- [ ] `framework/packages/core/contracts/src/Config/ConfigValueSource.php`
- [ ] `framework/packages/core/contracts/src/Config/ConfigDirective.php` — directives allowlist as enum (append/prepend/remove/merge/replace)
- [ ] `framework/packages/core/contracts/src/Env/EnvRepositoryInterface.php`
- [ ] `framework/packages/core/contracts/src/Env/EnvValue.php` — VO to represent env lookup result (missing vs present; empty string is present)
- [ ] `framework/packages/core/contracts/src/Env/EnvPolicy.php`

- [ ] `docs/adr/ADR-0002-config-env-source-tracking-directives-invariants.md`
- [ ] `docs/ssot/config-and-env.md` — env policy precedence + directives allowlist + exclusive-level rule + safe explain trace contract
  - [ ] In this document, `descriptor/result/shape/model` terminology follows `docs/ssot/dto-policy.md`; these models are not DTO-marker classes unless explicitly marked.

Tests:
- [ ] `framework/packages/core/contracts/tests/Contract/EnvPolicyPrecedenceContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/EnvMissingVsEmptyIsDistinctContractTest.php`

- [ ] `framework/packages/core/contracts/tests/Contract/ConfigDirectiveInvariantsContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/DirectivesAllowlistMatchesPhase0ConfigMergeLockContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ConfigDirectiveErrorPrecedenceMatchesPhase0LockContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ConfigDirectiveEmptyArrayRuleIsCementedContractTest.php`

- [ ] `framework/packages/core/contracts/tests/Contract/ConfigSourceTypeIsStableContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ConfigSourceTypeEnumMatchesPhase0PrecedenceLockContractTest.php`

- [ ] `framework/packages/core/contracts/tests/Contract/ConfigValueSourceShapeContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ConfigTraceModelNeverContainsRawValuesContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ConfigTraceOrderingIsDeterministicContractTest.php`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/config-and-env.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0002-config-env-source-tracking-directives-invariants.md`

#### Package skeleton (if type=package)

N/A (kind=library; no module/provider/config/rules required for `core/contracts`)

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

- (future) config artifact is written by kernel; not owned here

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Errors

- [ ] Unknown `@directive` MUST fail validation with a deterministic code BEFORE merge (documented in `docs/ssot/config-and-env.md`).

#### Security / Redaction

- [ ] Explain/source tracking MUST NOT require storing raw values (contracts-level rule)
- [ ] MUST NOT leak `.env` values, passwords, tokens.
- [ ] Implementation outputs may use `hash(value)` / `len(value)` only (never print raw values).
- [ ] Config/Env trace models are canonical contracts shapes, not DTO-marker classes by default

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] Contract evidence locks Phase 0 invariants:
  - [ ] directives allowlist + reserved `@*` namespace guard are cemented (0.90.0)
  - [ ] exclusive-level rule + error precedence are cemented (0.90.0)
  - [ ] float-forbidden json-like model is enforced at contracts boundary (0.70.0)
  - [ ] config trace never stores raw values; ordering is deterministic (0.90.0)
  - [ ] env missing vs empty is distinguishable (0.60.0 / 0.70.0 policy alignment)

### Tests (MUST)

- Contract:
  - [ ] tests listed in Creates

### DoD (MUST)

- [ ] Contracts compile without forbidden deps
- [ ] Contract tests green
- [ ] `docs/ssot/config-and-env.md` exists and matches contracts invariants
- [ ] Directives allowlist cannot expand without ADR + updated locks
- [ ] Lock source (Phase 0):
  - [ ] Directives semantics + precedence + explain trace are SSoT-locked and MUST match Phase 0 spikes:
    - [ ] 0.90.0 — config_merge directive semantics + explain ordering + reserved namespace guard
    - [ ] 0.70.0 — float-forbidden json-like model
  - [ ] Any expansion of directives/source-types MUST require ADR update + SSoT update + contract test update (non-optional)
- [ ] Non-goals / out of scope:
  - [ ] No merge/loader/explain implementation here (Kernel config engine owns it).
  - [ ] No secrets in explain/trace (contracts define metadata only: type/path/keyPath).
  - [ ] Directives allowlist MUST NOT expand without ADR + updated spike locks.

---

### 1.90.0 Contracts: Observability + ErrorDescriptor + Health + Profiling ports (MUST) [CONTRACTS]

---
type: package
phase: 1
epic_id: "1.90.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Runtime packages can depend on stable observability/errors/health/profiling ports while remaining format-neutral (no PSR-7 leakage) and noop-safe."
provides:
- "Noop-safe observability ports (tracing/metrics/correlation) usable by any runtime"
- "Format-neutral ErrorDescriptor model + error mapping ports"
- "Health check ports for `/health*` implementations (platform-owned later)"
- "Vendor-agnostic profiling ports usable by any UoW (HTTP/CLI/Worker), without contract changes later"

tags_introduced: []  # tags are reserved in 1.10.0 Tag registry (SSoT); contracts do not introduce DI tags.
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0003-observability-errordescriptor-health-profiling-ports.md"
ssot_refs:
- "docs/ssot/observability.md"
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/profiling-ports.md"
- "docs/ssot/tags.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists
  - PRELUDE.20.0 — packaging strategy locked (composer/namespace mapping is single-choice)
  - PRELUDE.30.0 — repo skeleton + composer roots + monorepo PHPUnit harness exist
  - 1.10.0 — Tag registry exists (`docs/ssot/tags.md`)
  - 1.40.0 — Observability naming/labels allowlist exists (`docs/ssot/observability.md`)
  - 1.50.1 — DTO policy SSoT exists (`docs/ssot/dto-policy.md`)

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists (PRELUDE.10.0)
  - `docs/ssot/tags.md` — tags registry exists (1.10.0)
  - `docs/ssot/observability.md` — naming/labels allowlist exists (1.40.0)
  - `framework/composer.json` — tooling workspace root exists (PRELUDE.30.0)
  - `framework/tools/testing/phpunit.xml` — monorepo PHPUnit harness exists (PRELUDE.30.0)
  - `docs/ssot/dto-policy.md` — canonical DTO vocabulary source for descriptor/result/shape/model terminology
  - `docs/adr/INDEX.md` — ADR index exists and this epic only appends its ADR registration

- Required config roots/keys:
  - none

- Required tags:
  - none (contracts do not declare DI tags; runtime discovery tags are reserved in `docs/ssot/tags.md`)

- Required contracts / ports:
  - none (defines ports/VO)

#### Lock sources (Phase 0 spikes) (MUST)

- 0.70.0 — PayloadNormalizer + FloatPolicy:
  - json-like модель без float/NaN/INF (float-forbidden policy)
  - list vs map детермінізм (list order preserved; maps stable key order)
- 0.90.0 — Two-phase config merge + directives + safe explain:
  - директиви allowlist: `@append`, `@prepend`, `@remove`, `@merge`, `@replace`
  - reserved namespace guard: будь-який `@*` поза allowlist — hard fail
  - exclusive-level rule + error precedence (unknown `@*` має пріоритет)
  - empty-array rule (`[]`) контекстно трактуємо як list або map
  - explain trace: deterministic ordering + redaction (no values)
- 0.60.0 — Fingerprint safe explain + tracked_env semantics:
  - missing vs empty MUST be distinguishable (детермінізм присутності)
- 0.20.0 — Phase 0 rails: no-secrets output policy як базова безпекова норма

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (defines ports/VO)

Forbidden:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- vendor concretes (PDO/Redis/S3/Prometheus namespaces)

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- Runtime discovery (policy, future owners):
  - `error.mapper` → `platform/errors` discovers `ExceptionMapperInterface`
  - `health.check` → `platform/health` discovers `HealthCheckInterface`
- HTTP (policy):
  - HTTP adapters (e.g. problem-details) MUST adapt `ErrorDescriptor` to RFC7807 without PSR-7 leakage into contracts
- Profiling hooks (policy, future owners):
  - Implementations may be wired via kernel hook tags (reserved in `docs/ssot/tags.md`): `kernel.hook.before_uow` / `kernel.hook.after_uow`

### Deliverables (MUST)

#### Creates

Observability:
- [ ] `framework/packages/core/contracts/src/Observability/CorrelationIdProviderInterface.php`

Tracing:
- [ ] `framework/packages/core/contracts/src/Observability/Tracing/TracerPortInterface.php`
- [ ] `framework/packages/core/contracts/src/Observability/Tracing/SpanInterface.php`
- [ ] `framework/packages/core/contracts/src/Observability/Tracing/ContextPropagationInterface.php`
- [ ] `framework/packages/core/contracts/src/Observability/Tracing/SpanExporterInterface.php`
- [ ] `framework/packages/core/contracts/src/Observability/Tracing/SamplerInterface.php`
- [ ] `framework/packages/core/contracts/src/Observability/Tracing/SamplingDecision.php`

Metrics:
- [ ] `framework/packages/core/contracts/src/Observability/Metrics/MeterPortInterface.php`
- [ ] `framework/packages/core/contracts/src/Observability/Metrics/MetricsRendererInterface.php`

Errors:
- [ ] `framework/packages/core/contracts/src/Observability/Errors/ErrorReporterPortInterface.php`
- [ ] `framework/packages/core/contracts/src/Observability/Errors/ExceptionMapperInterface.php`
- [ ] `framework/packages/core/contracts/src/Observability/Errors/ErrorHandlerInterface.php`
- [ ] `framework/packages/core/contracts/src/Observability/Errors/ErrorSeverity.php` — severity enum (stable)
- [ ] `framework/packages/core/contracts/src/Observability/Errors/ErrorDescriptor.php`
  - [ ] extensions strictly json-like
  - [ ] stable field set
  - [ ] no raw throwable payload
- [ ] `framework/packages/core/contracts/src/Observability/Errors/ErrorHandlingContext.php`

Health:
- [ ] `framework/packages/core/contracts/src/Observability/Health/HealthCheckInterface.php`
- [ ] `framework/packages/core/contracts/src/Observability/Health/HealthStatus.php`

Profiling:
- [ ] `framework/packages/core/contracts/src/Observability/Profiling/ProfilerPortInterface.php`
- [ ] `framework/packages/core/contracts/src/Observability/Profiling/ProfileArtifact.php`
- [ ] `framework/packages/core/contracts/src/Observability/Profiling/ProfileExporterInterface.php`

Documentation:
- [ ] `docs/adr/ADR-0003-observability-errordescriptor-health-profiling-ports.md`
- [ ] `docs/ssot/observability-and-errors.md` — ports overview, ErrorDescriptor format-neutral rule, redaction rules
  - [ ] Json-like payload rules (cemented)
    - [ ] Any json-like payload exposed by contracts (e.g. `ErrorDescriptor.extensions`, profiling metadata envelopes, etc.) MUST follow Phase 0 json-like rules:
      - [ ] Allowed scalars: `string|int|bool|null`
      - [ ] Floats are FORBIDDEN everywhere (including `NaN`, `INF`, `-INF`)
      - [ ] Containers:
        - [ ] lists preserve order
        - [ ] maps are key-ordered deterministically (byte-order), locale-independent
      - [ ] Implementations MUST NOT log/print raw payload values; only `hash(value)` / `len(value)` is allowed for diagnostics.
  - [ ] In this document, `descriptor/result/shape/model` terminology follows `docs/ssot/dto-policy.md`; these models are not DTO-marker classes unless explicitly marked.
- [ ] `docs/ssot/profiling-ports.md` — profiling policy + invariants (payload opaque)

Tests:
- [ ] `framework/packages/core/contracts/tests/Contract/ErrorDescriptorShapeContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsAreJsonLikeContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ErrorDescriptorHttpStatusIsOptionalContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ErrorDescriptorSeverityEnumContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/MetricsRendererInterfaceShapeContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/SpanExporterInterfaceShapeContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/SamplerInterfaceShapeContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ProfilingContractsShapeContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ProfilingContractsDoNotDependOnPsr7ContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ContractsDoNotReferencePsr7ContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ErrorDescriptorFieldSetIsStableContractTest.php`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/observability-and-errors.md`
  - [ ] `docs/ssot/profiling-ports.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0003-observability-errordescriptor-health-profiling-ports.md`

#### Package skeleton (if type=package)

N/A (kind=library; no module/provider/config/rules required for `core/contracts`)

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A (contracts-only; tags are runtime discovery policy and are reserved in `docs/ssot/tags.md`)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [ ] Contracts remain format-neutral; label allowlist is policy-level (`docs/ssot/observability.md`).
- [ ] Metric label keys MUST stay within allowlist: `method,status,driver,operation,table,outcome`.
- [ ] Spans/logs MUST NOT contain PII/secrets.
- [ ] Profiling: `ProfileArtifact.payload` is opaque and MUST NEVER be logged or used as metric label.

#### Errors

- [ ] `ErrorDescriptor` is format-neutral; `httpStatus` is an optional hint only; `extensions` are json-like only.

#### Security / Redaction

- [ ] MUST NOT leak: auth/cookies/session ids/tokens/raw SQL/payload/profile payload
- [ ] Allowed (implementation-side): `hash(value)` / `len(value)` / safe ids

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/packages/core/contracts/tests/Contract/ContractsDoNotReferencePsr7ContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ProfilingContractsDoNotDependOnPsr7ContractTest.php`

### Tests (MUST)

- Contract:
  - [ ] tests listed above

### DoD (MUST)

- [ ] Contracts remain format-neutral (no PSR-7)
- [ ] ErrorDescriptor stable and contract-tested
- [ ] Profiling ports stable and contract-tested
- [ ] Docs exist and match ports model
- [ ] Runtime expectation (policy, NOT deps):
  - [ ] `platform/logging|tracing|metrics` provide noop-safe implementations.
  - [ ] `platform/errors` provides `ErrorHandlerInterface` implementation + mapper registry (discovery via tag `error.mapper`).
  - [ ] `platform/problem-details` adapts `ErrorDescriptor` → RFC7807 and wires `HttpErrorHandlingMiddleware` into `http.middleware.system_pre` (priority 1000) — when installed.
  - [ ] Health checks are discovered via tag `health.check`.
  - [ ] Profiling is implemented in `platform/profiling` (Phase 6+) and wired via kernel hook tags.
- [ ] `ErrorDescriptor` is a canonical descriptor model, not a DTO-marker class by default
- [ ] descriptor shape rules are enforced by contracts tests, not DTO gates

---

### 1.95.0 Contracts: ContextAccessorInterface (MUST) [CONTRACTS]

---
type: package
phase: 1
epic_id: "1.95.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Introduce a minimal contracts read-port for context access without a Foundation dependency: ContextAccessorInterface."
provides:
- "Coretsia\\Contracts\\Context\\ContextAccessorInterface with cemented signature `get(string $key): mixed` (no default parameter)."
- "A single stable API for optional dependency usage in cross-cutting packages (database/secrets/health/logging/tracing/metrics)."

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
  - PRELUDE.30.0 — repo skeleton exists; composer roots exist
  - 1.70.0 — базовий contracts baseline існує (core/contracts уже використовується іншими contracts-епіками)

- Required deliverables (exact paths):
  - N/A

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- none (contracts package)

Forbidden:
- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/Context/ContextAccessorInterface.php`
  - [ ] MUST:
    - [ ] namespace: `Coretsia\Contracts\Context`
    - [ ] signature: `public function get(string $key): mixed;`
    - [ ] MUST NOT: default параметр, сеттери, мутація, storage details
- [ ] `framework/packages/core/contracts/tests/Contract/ContextAccessorInterfaceShapeContractTest.php`
  - [ ] MUST assert:
    - [ ] method exists
    - [ ] parameter name/type = `string $key`
    - [ ] return type = `mixed`
    - [ ] no extra public methods introduced accidentally

#### Modifies

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

- Contract test proving signature stability.

### Tests (MUST)

- [ ] `framework/packages/core/contracts/tests/Contract/ContextAccessorInterfaceShapeContractTest.php`

### DoD (MUST)

- [ ] Deliverables complete (creates), paths exact
- [ ] No runtime dependencies added
- [ ] Signature is cemented and covered by contract test
- [ ] Context accessor is a runtime read port, not a DTO/result/descriptor model

---

### 1.100.0 Errors SSoT: contracts ↔ platform boundary + ErrorDescriptor shape (MUST) [DOC]

---
type: docs
phase: 1
epic_id: "1.100.0"
owner_path: "docs/ssot/errors-boundary.md"

goal: "There is exactly one canonical error normalization flow (HTTP/CLI/Worker) and exactly one canonical ErrorDescriptor shape so no adapter invents divergent fields."
provides:
- "Cemented responsibility boundary: contracts define VO/ports; platform provides reference implementations/adapters"
- "Forbidden dependency directions to prevent cycles"
- "Canonical high-level flow: Throwable → ErrorDescriptor → runtime adapters"
- "Single human-readable SSoT for ErrorDescriptor fields + mapping hints + redaction rules"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/errors-boundary.md"
- "docs/ssot/error-descriptor.md"
- "docs/ssot/tags.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists
  - PRELUDE.30.0 — framework/ workspace exists
  - 1.10.0 — Tag registry exists (`docs/ssot/tags.md`)
  - 1.90.0 — Observability/errors contracts exist (ErrorDescriptor + ports)
  - 0.70.0 — Phase 0 json-like / float-forbidden policy is cemented (used as normative payload rule)

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists
  - `docs/ssot/tags.md` — tags registry exists
  - `docs/ssot/observability-and-errors.md` — contracts payload/redaction policy exists (1.90.0)
  - `framework/packages/core/contracts/tests/Contract/ErrorDescriptorShapeContractTest.php` — enforcement evidence (contracts)
  - `framework/packages/core/contracts/tests/Contract/ErrorDescriptorHttpStatusIsOptionalContractTest.php` — enforcement evidence (contracts)

- Required config roots/keys:
  - none

- Required tags:
  - `error.mapper` — mapper discovery tag (runtime-side)

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
  - `Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface`
  - `Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (doc-only)

Forbidden:

- N/A

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- Runtime policy:
  - `platform/errors` implements error normalization and mapper registry (discovery via `error.mapper`)
  - `platform/problem-details` (HTTP adapter) renders RFC7807 from ErrorDescriptor (contracts remain format-neutral)

### Deliverables (MUST)

#### Creates

- [ ] `docs/ssot/errors-boundary.md` — boundary + forbidden deps + canonical flow
- [ ] `docs/ssot/error-descriptor.md` — fields + rules + examples (no secrets)
  - [ ] Extensions payload constraints (cemented)
    - [ ] `ErrorDescriptor.extensions` MUST be json-like and MUST follow Phase 0 float-forbidden policy:
      - [ ] floats (including NaN/INF) are forbidden at any nesting depth
      - [ ] extensions MUST be safe-by-design: MUST NOT contain raw headers/cookies/auth/session/token/payload/sql
      - [ ] examples in this document MUST NOT contain secrets/PII
  - [ ] `ErrorDescriptor` is a canonical descriptor shape for normalized errors
  - [ ] it is NOT automatically a DTO under DTO marker policy
  - [ ] DTO gates apply only to explicitly marked DTO transport classes
  - [ ] `docs/ssot/error-descriptor.md` is the single human-readable field reference for `ErrorDescriptor`
  - [ ] `docs/ssot/observability-and-errors.md` remains ports/boundary/redaction overview and MUST NOT redefine a competing field-by-field `ErrorDescriptor` schema

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/errors-boundary.md`
  - [ ] `docs/ssot/error-descriptor.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Security / Redaction

- [ ] MUST NOT leak secrets/PII in errors/logs/spans
- [ ] MUST NOT leak secrets/PII in examples

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (doc-only; shape is enforced by existing contract tests)

### Tests (MUST)

N/A (doc-only), but MUST reference enforcement evidence in:

- [ ] `framework/packages/core/contracts/tests/Contract/ContractsDoNotReferencePsr7ContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ErrorDescriptorShapeContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ErrorDescriptorHttpStatusIsOptionalContractTest.php`

Future runtime evidence (NOT a precondition of this doc epic; referenced once available):

- [ ] `framework/packages/platform/errors/tests/Contract/ErrorHandlerNeverThrowsContractTest.php`

### DoD (MUST)

- [ ] Docs exist and match contracts model (`ErrorDescriptor`)
- [ ] No contradictions with tags registry and dep-law
- [ ] No duplicate alternative shapes elsewhere
- [ ] Runtime expectation (policy, NOT deps):
  - [ ] `platform/errors` implements error normalization + mapper registry (discovery via tag `error.mapper`).
  - [ ] `platform/problem-details` wires `HttpErrorHandlingMiddleware` into `http.middleware.system_pre` with priority `1000`.

---

### 1.110.0 Contracts: Routing + HttpApp ports (MUST) [CONTRACTS]

---
type: package
phase: 1
epic_id: "1.110.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Routing and http-app can be implemented without changing contracts and without making contracts HTTP-aware."
provides:
- "Format-neutral routing boundary (VOs + ports) with no PSR-7 leakage"
- "HttpApp invocation ports (action invoker + argument resolver) independent of request type"
- "Stable contracts for future route compilation (artifact owned by runtime)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0005-routing-httpapp-ports.md"
ssot_refs:
- "docs/ssot/routing-and-http-app-contracts.md"
- "docs/ssot/tags.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists
  - PRELUDE.20.0 — packaging strategy locked (composer/namespace mapping is single-choice)
  - PRELUDE.30.0 — repo skeleton + composer roots + monorepo PHPUnit harness exist
  - 1.10.0 — Tag registry exists and contains the canonical HTTP middleware slot tag names (`docs/ssot/tags.md`)

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists (PRELUDE.10.0)
  - `docs/ssot/tags.md` — tags registry exists (1.10.0)
  - `docs/adr/INDEX.md` — ADR index exists and this epic only appends its ADR registration
  - `framework/composer.json` — tooling workspace root exists (PRELUDE.30.0)
  - `framework/tools/testing/phpunit.xml` — monorepo PHPUnit harness exists (PRELUDE.30.0)

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none (defines)

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- HTTP (future owners):
  - `platform/http-app` provides RouterMiddleware and wires it into `http.middleware.app` (canonical taxonomy `system/app/route`)
- CLI (future owner):
  - `coretsia routes:compile` (routing owner) consumes `RouteProviderInterface`

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/Routing/RouteDefinition.php`
  - [ ] safe scalar/json-like fields only
  - [ ] deterministic order for multi-route provider outputs
- [ ] `framework/packages/core/contracts/src/Routing/RouteMatch.php`
  - [ ] safe scalar/json-like fields only
  - [ ] deterministic order for multi-route provider outputs
- [ ] `framework/packages/core/contracts/src/Routing/RouterInterface.php`
- [ ] `framework/packages/core/contracts/src/Routing/RouteProviderInterface.php`
- [ ] `framework/packages/core/contracts/src/HttpApp/ActionInvokerInterface.php`
- [ ] `framework/packages/core/contracts/src/HttpApp/ArgumentResolverInterface.php`

- [ ] `docs/adr/ADR-0005-routing-httpapp-ports.md`
- [ ] `docs/ssot/routing-and-http-app-contracts.md` — boundary rules + examples (no PSR-7)

- [ ] `framework/packages/core/contracts/tests/Contract/RoutingContractsDoNotUsePsr7Test.php`
- [ ] `framework/packages/core/contracts/tests/Contract/HttpAppContractsAreFormatNeutralTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/RouteProviderInterfaceShapeContractTest.php`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/routing-and-http-app-contracts.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0005-routing-httpapp-ports.md`

#### Package skeleton (if type=package)

N/A (kind=library; no module/provider/config/rules required for `core/contracts`)

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

- (future) routes artifact is owned by routing runtime package, not by contracts

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [ ] Route template (not raw path) is canonical routing metadata/context and MAY be used for matching/debugging/span context.
- [ ] It MUST NOT become a metric label unless `docs/ssot/observability.md` explicitly extends the label allowlist.

#### Security / Redaction

- [ ] Contracts VOs MUST NOT include raw headers/cookies/body/payloads (format-neutral boundary)

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/packages/core/contracts/tests/Contract/RoutingContractsDoNotUsePsr7Test.php`
- [ ] `framework/packages/core/contracts/tests/Contract/HttpAppContractsAreFormatNeutralTest.php`

### Tests (MUST)

- Contract:
  - [ ] tests listed above

### DoD (MUST)

- [ ] Ports exist + tested
- [ ] Docs exist
- [ ] No PSR-7 leakage in contracts
- [ ] Runtime expectation (policy, NOT deps):
  - [ ] `platform/routing` compiles routes artifact and sets canonical routing metadata/context key `path_template` after match.
  - [ ] Any concrete Foundation constant or key-carrier class for that context key is owned and introduced only by the corresponding Foundation epic.
  - [ ] `platform/http-app` supplies RouterMiddleware and wires it into the app middleware slot at priority `100`.
    - [ ] Slot/tag naming MUST be explicit: canonical tag is `http.middleware.app`.
    - [ ] Legacy `http.middleware.user` MUST be treated only as deprecated/renamed terminology and MUST NOT appear as a current tag name in contracts, SSoT, defaults, or gates.
- [ ] Middleware slot alignment (cemented):
  - [ ] Router middleware wiring policy MUST use the canonical slot taxonomy `system/app/route`, with canonical tag names reserved in `docs/ssot/tags.md`.
  - [ ] Any mention of legacy `http.middleware.user*` MUST be treated as deprecated/renamed and MUST NOT appear in new contracts/SSoT
- [ ] `RouteDefinition` and `RouteMatch` are contracts routing shapes/descriptors, not DTO-marker classes by default

---

### 1.120.0 Contracts: ResetInterface + UoW hooks (MUST) [CONTRACTS]

---
type: package
phase: 1
epic_id: "1.120.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Any long-running runtime can enforce state cleanup between units of work using only contracts ports and deterministic DI tags."
provides:
- "Minimal reset API for long-running runtimes"
- "UoW hook boundary (before/after) without PSR-7 coupling"
- "Policy linkage to canonical kernel tags (`kernel.reset`, `kernel.hook.*`) reserved in Tag registry SSoT"

tags_introduced: []  # tags are reserved in 1.10.0 Tag registry (SSoT); contracts do not introduce DI tags.
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0006-reset-interface-uow-hooks.md"
ssot_refs:
- "docs/ssot/uow-and-reset-contracts.md"
- "docs/ssot/tags.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists
  - PRELUDE.20.0 — packaging strategy locked (composer/namespace mapping is single-choice)
  - PRELUDE.30.0 — repo skeleton + composer roots + monorepo PHPUnit harness exist
  - 1.10.0 — Tag registry exists (`docs/ssot/tags.md`)

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists (PRELUDE.10.0)
  - `docs/ssot/tags.md` — tags registry exists (1.10.0)
  - `docs/adr/INDEX.md` — ADR index exists and this epic only appends its ADR registration
  - `framework/composer.json` — tooling workspace root exists (PRELUDE.30.0)
  - `framework/tools/testing/phpunit.xml` — monorepo PHPUnit harness exists (PRELUDE.30.0)

- Required config roots/keys:
  - none

- Required tags:
  - none (contracts do not declare DI tags)

- Required contracts / ports:
  - none (defines)

#### Lock sources (Phase 0 spikes) (MUST)

- 0.70.0 — PayloadNormalizer + FloatPolicy:
  - json-like модель без float/NaN/INF (float-forbidden policy)
  - list vs map детермінізм (list order preserved; maps stable key order)
- 0.90.0 — Two-phase config merge + directives + safe explain:
  - директиви allowlist: `@append`, `@prepend`, `@remove`, `@merge`, `@replace`
  - reserved namespace guard: будь-який `@*` поза allowlist — hard fail
  - exclusive-level rule + error precedence (unknown `@*` має пріоритет)
  - empty-array rule (`[]`) контекстно трактуємо як list або map
  - explain trace: deterministic ordering + redaction (no values)
- 0.60.0 — Fingerprint safe explain + tracked_env semantics:
  - missing vs empty MUST be distinguishable (детермінізм присутності)
- 0.20.0 — Phase 0 rails: no-secrets output policy як базова безпекова норма

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- Kernel/runtime (policy):
  - services discovered through the effective Foundation reset tag are reset deterministically
  - hooks discovered via `kernel.hook.before_uow` / `kernel.hook.after_uow` (tag strings reserved in `docs/ssot/tags.md`)

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/Runtime/ResetInterface.php`
- [ ] `framework/packages/core/contracts/src/Runtime/Hook/BeforeUowHookInterface.php`
- [ ] `framework/packages/core/contracts/src/Runtime/Hook/AfterUowHookInterface.php`

- [ ] `docs/adr/ADR-0006-reset-interface-uow-hooks.md`
- [ ] `docs/ssot/uow-and-reset-contracts.md` — rules: format-neutral hooks, reset discipline, tags used (refer to tag registry)

- [ ] `framework/packages/core/contracts/tests/Contract/ResetInterfaceIsMinimalContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/HookInterfacesDoNotDependOnPlatformTest.php`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/uow-and-reset-contracts.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0006-reset-interface-uow-hooks.md`

#### Package skeleton (if type=package)

N/A (kind=library; no module/provider/config/rules required for `core/contracts`)

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A (contracts-only)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/packages/core/contracts/tests/Contract/ResetInterfaceIsMinimalContractTest.php`

### Tests (MUST)

- Contract:
  - [ ] tests listed above

### DoD (MUST)

- [ ] Ports exist + tested
- [ ] Docs exist
- [ ] No forbidden deps
- [ ] Runtime expectation (policy, NOT deps):
  - [ ] Foundation reset orchestrator calls `reset()` on services discovered through the effective Foundation reset tag.
  - [ ] Kernel executes hooks via tags `kernel.hook.before_uow` / `kernel.hook.after_uow` in deterministic order.
- [ ] Acceptance scenario (policy intent):
  - [ ] When a worker processes two jobs, stateful services are reset between them and hooks run deterministically.

---

### 1.130.0 Contracts: Validation ports (MUST) [CONTRACTS]

---
type: package
phase: 1
epic_id: "1.130.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Validation can be used across runtimes without HTTP coupling and maps deterministically to normalized errors."
provides:
- "Format-neutral validation ports/VOs"
- "Deterministic ValidationException code for error mapping (ErrorDescriptor 422 via runtime mapper)"
- "SSoT boundary: no validator implementation in contracts"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0007-validation-ports.md"
ssot_refs:
- "docs/ssot/validation-contracts.md"
- "docs/ssot/tags.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists
  - PRELUDE.20.0 — packaging strategy locked (composer/namespace mapping is single-choice)
  - PRELUDE.30.0 — repo skeleton + composer roots + monorepo PHPUnit harness exist
  - 1.10.0 — Tag registry exists (`docs/ssot/tags.md`)
  - 1.90.0 — ErrorDescriptor model exists (mapping target)

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists (PRELUDE.10.0)
  - `docs/ssot/tags.md` — tags registry exists (1.10.0)
  - `docs/adr/INDEX.md` — ADR index exists and this epic only appends its ADR registration
  - `framework/composer.json` — tooling workspace root exists (PRELUDE.30.0)
  - `framework/tools/testing/phpunit.xml` — monorepo PHPUnit harness exists (PRELUDE.30.0)

- Required config roots/keys:
  - none

- Required tags:
  - `error.mapper` — runtime maps ValidationException to ErrorDescriptor

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor` — mapping target (policy)

#### Lock sources (Phase 0 spikes) (MUST)

- 0.70.0 — PayloadNormalizer + FloatPolicy:
  - json-like модель без float/NaN/INF (float-forbidden policy)
  - list vs map детермінізм (list order preserved; maps stable key order)
- 0.90.0 — Two-phase config merge + directives + safe explain:
  - директиви allowlist: `@append`, `@prepend`, `@remove`, `@merge`, `@replace`
  - reserved namespace guard: будь-який `@*` поза allowlist — hard fail
  - exclusive-level rule + error precedence (unknown `@*` має пріоритет)
  - empty-array rule (`[]`) контекстно трактуємо як list або map
  - explain trace: deterministic ordering + redaction (no values)
- 0.60.0 — Fingerprint safe explain + tracked_env semantics:
  - missing vs empty MUST be distinguishable (детермінізм присутності)
- 0.20.0 — Phase 0 rails: no-secrets output policy як базова безпекова норма

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- `Psr\Http\Message\*`
- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- Runtime expectation (policy):
  - `platform/validation` provides validator + mapper for `ValidationException` → `ErrorDescriptor(422)`

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/Validation/ValidatorInterface.php`
- [ ] `framework/packages/core/contracts/src/Validation/ValidationResult.php`
  - [ ] contains deterministic violation collection shape
  - [ ] exposes violations as ordered list shape
  - [ ] carries no raw input payload values

- [ ] `framework/packages/core/contracts/src/Validation/Violation.php`
  - [ ] Violation fields are scalar/json-like only
  - [ ] meta/extensions float-forbidden
  - [ ] meta must be json-like / float-free / safe only
  - [ ] no raw input values
  - [ ] stable order of violations is runtime concern, but shape must support deterministic sort keys:
    - [ ] `path`
    - [ ] `code`
    - [ ] `rule`
    - [ ] `index`
- [ ] `framework/packages/core/contracts/src/Validation/ValidationException.php`

- [ ] `docs/adr/ADR-0007-validation-ports.md`
- [ ] `docs/ssot/validation-contracts.md` — shape + deterministic exception code + mapping notes
  - [ ] `ValidationResult` and `Violation` are contracts result/descriptor shapes, not DTO-marker classes by default

- [ ] `framework/packages/core/contracts/tests/Contract/ValidationContractsTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ValidationExceptionHasDeterministicCodeTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/ValidationViolationShapeIsSafeContractTest.php`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/validation-contracts.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0007-validation-ports.md`

#### Package skeleton (if type=package)

N/A (kind=library; no module/provider/config/rules required for `core/contracts`)

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Errors

- [ ] `ValidationException` MUST use deterministic code `CORETSIA_VALIDATION_FAILED`.
- [ ] Runtime maps it via `error.mapper` to `ErrorDescriptor` with HTTP status hint `422` (format-neutral; adapter-owned).

#### Security / Redaction

- [ ] Violations meta MUST be json-like only; MUST NOT include secrets/PII by default (policy note)

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] `framework/packages/core/contracts/tests/Contract/ValidationExceptionHasDeterministicCodeTest.php`

### Tests (MUST)

- Contract:
  - [ ] tests listed above

### DoD (MUST)

- [ ] Ports exist + tested
- [ ] Docs exist
- [ ] No forbidden deps

---

### 1.140.0 Contracts: Filesystem ports (MUST) [CONTRACTS]

---
type: package
phase: 1
epic_id: "1.140.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "All filesystem operations in platform/integrations use the same DiskInterface contract without leaks."
provides:
- "Single filesystem port `DiskInterface`"
- "Stable boundary for platform/filesystem and integrations drivers"
- "No vendor concretes in contracts"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0008-filesystem-ports.md"
ssot_refs:
- "docs/ssot/filesystem-contracts.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists
  - PRELUDE.20.0 — packaging strategy locked (composer/namespace mapping is single-choice)
  - PRELUDE.30.0 — repo skeleton + composer roots + monorepo PHPUnit harness exist

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists (PRELUDE.10.0)
  - `docs/adr/INDEX.md` — ADR index exists and this epic only appends its ADR registration
  - `framework/composer.json` — tooling workspace root exists (PRELUDE.30.0)
  - `framework/tools/testing/phpunit.xml` — monorepo PHPUnit harness exists (PRELUDE.30.0)

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none (defines)

#### Lock sources (Phase 0 spikes) (MUST)

- 0.70.0 — PayloadNormalizer + FloatPolicy:
  - json-like модель без float/NaN/INF (float-forbidden policy)
  - list vs map детермінізм (list order preserved; maps stable key order)
- 0.90.0 — Two-phase config merge + directives + safe explain:
  - директиви allowlist: `@append`, `@prepend`, `@remove`, `@merge`, `@replace`
  - reserved namespace guard: будь-який `@*` поза allowlist — hard fail
  - exclusive-level rule + error precedence (unknown `@*` має пріоритет)
  - empty-array rule (`[]`) контекстно трактуємо як list або map
  - explain trace: deterministic ordering + redaction (no values)
- 0.60.0 — Fingerprint safe explain + tracked_env semantics:
  - missing vs empty MUST be distinguishable (детермінізм присутності)
- 0.20.0 — Phase 0 rails: no-secrets output policy як базова безпекова норма

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- vendor concretes

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/Filesystem/DiskInterface.php`

- [ ] `docs/adr/ADR-0008-filesystem-ports.md`
- [ ] `docs/ssot/filesystem-contracts.md` — ports + invariants

- [ ] `framework/packages/core/contracts/tests/Contract/FilesystemDiskInterfaceShapeContractTest.php`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/filesystem-contracts.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0008-filesystem-ports.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Security / Redaction

- [ ] Path strings are treated as sensitive in logs (policy note; implemented in platform/filesystem)

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (contracts-only; proven by contract test)

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/core/contracts/tests/Contract/FilesystemDiskInterfaceShapeContractTest.php`

### DoD (MUST)

- [ ] Contract exists + tested
- [ ] Docs exist
- [ ] Runtime expectation (policy, NOT deps):
  - [ ] `DiskInterface` is used by `platform/filesystem`, `platform/session`, `platform/uploads`, `platform/lock`.
  - [ ] Drivers live in `integrations/*` and MUST implement this contracts interface.
- [ ] Non-goals / out of scope:
  - [ ] Path safety policy is owned by `platform/filesystem` (not contracts).

---

### 1.150.0 Contracts: Database + Migrations ports (MUST) [CONTRACTS]

---
type: package
phase: 1
epic_id: "1.150.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "platform/database and platform/migrations (and driver packages like `platform/database-driver-*`) interoperate via stable contracts without exposing vendor types, enabling deterministic migrations without driver coupling."
provides:
- "Driver-agnostic DB ports (driver/connection/result) with no vendor concretes (PDO) in contracts"
- "Single migration contract that depends only on contracts DB connection"
- "Policy reminders: no raw SQL in logs/labels; migrations tooling must not log raw SQL"
- "SqlQuery contract (opaque-ish value object) used by platform/database and migrations to pass SQL+bindings without `__toString()` risk"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0009-database-and-migrations-ports.md"
ssot_refs:
- "docs/ssot/database-contracts.md"
- "docs/ssot/migrations-contracts.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists
  - PRELUDE.20.0 — packaging strategy locked (composer/namespace mapping is single-choice)
  - PRELUDE.30.0 — repo skeleton + composer roots + monorepo PHPUnit harness exist

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists (PRELUDE.10.0)
  - `docs/adr/INDEX.md` — ADR index exists and this epic only appends its ADR registration
  - `framework/composer.json` — tooling workspace root exists (PRELUDE.30.0)
  - `framework/tools/testing/phpunit.xml` — monorepo PHPUnit harness exists (PRELUDE.30.0)

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none (defines)

#### Lock sources (Phase 0 spikes) (MUST)

- 0.70.0 — PayloadNormalizer + FloatPolicy:
  - json-like модель без float/NaN/INF (float-forbidden policy)
  - list vs map детермінізм (list order preserved; maps stable key order)
- 0.90.0 — Two-phase config merge + directives + safe explain:
  - директиви allowlist: `@append`, `@prepend`, `@remove`, `@merge`, `@replace`
  - reserved namespace guard: будь-який `@*` поза allowlist — hard fail
  - exclusive-level rule + error precedence (unknown `@*` має пріоритет)
  - empty-array rule (`[]`) контекстно трактуємо як list або map
  - explain trace: deterministic ordering + redaction (no values)
- 0.60.0 — Fingerprint safe explain + tracked_env semantics:
  - missing vs empty MUST be distinguishable (детермінізм присутності)
- 0.20.0 — Phase 0 rails: no-secrets output policy як базова безпекова норма

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- vendor concretes (PDO)

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- Runtime expectation (policy):
  - `platform/database` depends only on these contracts ports; driver packages (e.g. `platform/database-driver-*`) implement them.
  - `platform/migrations` executes migrations implementing `MigrationInterface`.

### Deliverables (MUST)

#### Creates

Database:
- [ ] `framework/packages/core/contracts/src/Database/SqlQueryInterface.php`
- [ ] `framework/packages/core/contracts/src/Database/SqlQuery.php` — immutable contracts query model for migrations/low-level calls
  - [ ] `SqlQuery` is an immutable contracts VO and is outside DTO marker policy by default
- [ ] `framework/packages/core/contracts/src/Database/DatabaseDriverInterface.php` MUST:
  - [ ] MUST treat `$config` as **secrets-allowed** driver-owned config (connection-scoped)
  - [ ] MUST treat `$tuning` as **NO-secrets** driver tuning (driver default + per-connection override)
  - [ ] MUST NOT read global config directly (driver is pure function of inputs)
  - [ ] `id(): string`
    - [ ] MUST be non-empty logical driver id (stable across runs)
    - [ ] MUST match regex: `^[a-z][a-z0-9_-]*$` (deterministic, vendor-neutral)
    - [ ] Canonical allowlist for platform-supported drivers is owned by future `platform/database` config SSoT, NOT by contracts.
    - [ ] Contracts lock only the generic logical driver-id shape/regex and must not enumerate the platform-supported driver set.
  - [ ] `connect(string $connectionName, array $config, array $tuning): ConnectionInterface`
    - [ ] `config` is driver-owned allowlisted (`database.connections.<name>.config`)
    - [ ] `$tuning` is EFFECTIVE driver tuning (NO secrets):
      - [ ] computed by platform/database as merge of:
        - [ ] driver defaults: `database.drivers.<driverId>.tuning`
        - [ ] per-connection override: `database.connections.<name>.tuning` (optional)
      - [ ] driver MUST treat `$tuning` as final input (no global reads)
    - [ ] PDO canonical options are NOT passed via contract surface as PDO attrs (they remain canonical string-key map inside config/tuning; driver may read them from `config.pdo_options`)
    - [ ] для multi-connection `DatabaseDriverInterface::connect(...)` вже приймає `$connectionName`, тож реалізація проста: драйвер зберігає name/driverId у connection object.
- [ ] `framework/packages/core/contracts/src/Database/ConnectionInterface.php` MUST:
  - [ ] `execute(SqlQueryInterface $query): QueryResultInterface`
  - [ ] `beginTransaction(): void`
  - [ ] `commit(): void`
  - [ ] `rollBack(): void`
  - [ ] `dialect(): SqlDialectInterface` *(critical for migrations + compiler parity; no vendor types)*
  - [ ] `ConnectionInterface::name(): string` — connection name (`main`, `analytics`, …)
    - [ ] MUST be non-empty
    - [ ] MUST equal the `$connectionName` argument passed into `DatabaseDriverInterface::connect(...)`
  - [ ] `ConnectionInterface::driverId(): string` — logical driver id
    - [ ] MUST be non-empty
    - [ ] MUST equal `DatabaseDriverInterface::id()` of the driver instance that produced this connection
    - [ ] MUST match regex: `^[a-z][a-z0-9_-]*$`
    - [ ] Platform-supported allowlist is enforced by `platform/database` config rules (not contracts)
- [ ] `framework/packages/core/contracts/src/Database/QueryResultInterface.php`
  - [ ] query results expose canonical scalar-only DB value domain (`int|string|bool|null`)
  - [ ] contracts never expose float values
- [ ] `framework/packages/core/contracts/src/Database/SqlDialectInterface.php`
  - [ ] Bridge для driver-specific SQL діалекту: відповідає за відмінності типу limit/offset (SQL Server), returning/identity, boolean literals, etc. Це робить інтеграції “реальними”.

Migrations:
- [ ] `framework/packages/core/contracts/src/Migrations/MigrationInterface.php`

Documentation:
- [ ] `docs/adr/ADR-0009-database-and-migrations-ports.md`
- [ ] `docs/ssot/database-contracts.md` — contracts + policy notes
  - [ ] `DbValue` MUST be `int|string|bool|null` (NO float anywhere)
  - [ ] `DbRow` MUST be `array<string, DbValue>`
  - [ ] `DbRows` MUST be `list<DbRow>`
  - [ ] `SqlQueryInterface`:
    - [ ] `sql(): string`
    - [ ] `bindings(): list<DbValue>` *(order preserved; MUST match placeholder order; no associative maps here)*
  - [ ] Add the `name()` / `driverId()` invariants above
  - [ ] Explicitly state: driver id is logical id (NOT vendor driver name like `sqlsrv`)
- [ ] `docs/ssot/migrations-contracts.md` — rules + determinism notes

Tests:
- [ ] `framework/packages/core/contracts/tests/Contract/SqlQueryShapeContractTest.php` (asserts no `__toString` leakage + shape types)
- [ ] `framework/packages/core/contracts/tests/Contract/DatabaseContractsShapeContractTest.php` MUST assert that `ConnectionInterface` exposes:
  - [ ] `driverId()` is non-empty and matches regex
  - [ ] `name()` is non-empty
- [ ] `framework/packages/core/contracts/tests/Contract/MigrationInterfaceShapeContractTest.php`
- [ ] `framework/packages/core/contracts/tests/Contract/DatabaseContractsNeverExposeFloatTypeContractTest.php`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/database-contracts.md`
  - [ ] `docs/ssot/migrations-contracts.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0009-database-and-migrations-ports.md`

#### Package skeleton (if type=package)

N/A (kind=library; no module/provider/config/rules required for `core/contracts`)

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Security / Redaction

- [ ] raw SQL MUST NOT be logged/used as metric label (policy note; implemented in platform/database)
- [ ] migrations tooling MUST NOT log raw SQL (policy note)
- [ ] Database result values exposed by contracts MUST NOT use `float` anywhere.
  - Integers → `int` (when representable safely)
  - Decimals / floats / bigints → `string`
  - Booleans → `bool`
  - NULL → `null`

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (contracts-only; proven by contract tests)

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/core/contracts/tests/Contract/DatabaseContractsShapeContractTest.php`
  - [ ] `framework/packages/core/contracts/tests/Contract/MigrationInterfaceShapeContractTest.php`

### DoD (MUST)

- [ ] Contracts exist + tested
- [ ] Docs exist
- [ ] No vendor concretes in contracts
- [ ] Runtime expectation (policy, NOT deps):
  - [ ] `platform/database` uses only these ports; driver packages (e.g. `platform/database-driver-*`) implement them.
  - [ ] `platform/migrations` executes `MigrationInterface` migrations; discovery/CLI is runtime-owned.

---

### 1.160.0 Contracts: RateLimit ports (MUST) [CONTRACTS]

---
type: package
phase: 1
epic_id: "1.160.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Rate limiting can be implemented and stores swapped without changing public APIs and without high-cardinality leakage."
provides:
- "Rate limit store + decision model ports/VOs"
- "Store swap safety (in-memory ↔ redis integration later) without API changes"
- "Policy constraints for observability + redaction of keys"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0011-ratelimit-ports.md"
ssot_refs:
- "docs/ssot/rate-limit-contracts.md"
- "docs/ssot/observability.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists
  - PRELUDE.20.0 — packaging strategy locked (composer/namespace mapping is single-choice)
  - PRELUDE.30.0 — repo skeleton + composer roots + monorepo PHPUnit harness exist
  - 1.40.0 — Observability allowlist exists (`docs/ssot/observability.md`)

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists (PRELUDE.10.0)
  - `docs/ssot/observability.md` — label allowlist exists (1.40.0)
  - `docs/adr/INDEX.md` — ADR index exists and this epic only appends its ADR registration
  - `framework/composer.json` — tooling workspace root exists (PRELUDE.30.0)
  - `framework/tools/testing/phpunit.xml` — monorepo PHPUnit harness exists (PRELUDE.30.0)

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none (defines)

#### Lock sources (Phase 0 spikes) (MUST)

- 0.70.0 — PayloadNormalizer + FloatPolicy:
  - json-like модель без float/NaN/INF (float-forbidden policy)
  - list vs map детермінізм (list order preserved; maps stable key order)
- 0.90.0 — Two-phase config merge + directives + safe explain:
  - директиви allowlist: `@append`, `@prepend`, `@remove`, `@merge`, `@replace`
  - reserved namespace guard: будь-який `@*` поза allowlist — hard fail
  - exclusive-level rule + error precedence (unknown `@*` має пріоритет)
  - empty-array rule (`[]`) контекстно трактуємо як list або map
  - explain trace: deterministic ordering + redaction (no values)
- 0.60.0 — Fingerprint safe explain + tracked_env semantics:
  - missing vs empty MUST be distinguishable (детермінізм присутності)
- 0.20.0 — Phase 0 rails: no-secrets output policy як базова безпекова норма

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- `platform/*`
- `integrations/*`
- vendor concretes
- `Psr\Http\Message\*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- Runtime expectation (policy):
  - `platform/http` RateLimit middleware may use these ports when enabled (`http.rate_limit.early.enabled`, `http.rate_limit.enabled`)

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/RateLimit/RateLimitStoreInterface.php`
- [ ] `framework/packages/core/contracts/src/RateLimit/RateLimitState.php`
- [ ] `framework/packages/core/contracts/src/RateLimit/RateLimitDecision.php`
- [ ] `framework/packages/core/contracts/src/RateLimit/RateLimitKeyHasherInterface.php`

- [ ] `docs/adr/ADR-0011-ratelimit-ports.md`
- [ ] `docs/ssot/rate-limit-contracts.md` — invariants (no correlation_id/request_id in keys; no raw path labels)
  - [ ] rate-limit decision/state models are contracts models, not DTO-marker classes by default

- [ ] `framework/packages/core/contracts/tests/Contract/RateLimitContractsShapeContractTest.php`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/rate-limit-contracts.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0011-ratelimit-ports.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [ ] labels only from allowlist; no user_id/correlation_id/request_id/tenant_id as labels
- [ ] Keys MUST NOT be logged; only `hash/len` if needed (implementation-side).
- [ ] No correlation_id/request_id in keys; no raw path labels.

#### Security / Redaction

- [ ] do not log keys/tokens/ids; only `hash/len` if needed (implementation-side)

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (contracts-only; proven by contract test)

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/core/contracts/tests/Contract/RateLimitContractsShapeContractTest.php`

### DoD (MUST)

- [ ] Contracts exist + tested
- [ ] Docs exist
- [ ] Runtime expectation (policy, NOT deps):
  - [ ] `platform/http` RateLimit middleware uses these ports when enabled (`http.rate_limit.early.enabled`, `http.rate_limit.enabled`).
  - [ ] Identity-aware key building MUST prefer `actor_id` (safe) then `client_ip`.

---

### 1.170.0 Contracts: Mail port (MUST) [CONTRACTS]

---
type: package
phase: 1
epic_id: "1.170.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Mail can be implemented with swappable transports without changing app code or leaking recipients/body into logs."
provides:
- "Vendor-agnostic mail ports (mailer/transport/message)"
- "Async-safe shape (queue later) without contract changes"
- "Hard redaction policy: recipients/body/credentials never logged"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0012-mail-port.md"
ssot_refs:
- "docs/ssot/mail-contracts.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists
  - PRELUDE.20.0 — packaging strategy locked (composer/namespace mapping is single-choice)
  - PRELUDE.30.0 — repo skeleton + composer roots + monorepo PHPUnit harness exist

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists (PRELUDE.10.0)
  - `docs/adr/INDEX.md` — ADR index exists and this epic only appends its ADR registration
  - `framework/composer.json` — tooling workspace root exists (PRELUDE.30.0)
  - `framework/tools/testing/phpunit.xml` — monorepo PHPUnit harness exists (PRELUDE.30.0)

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none (defines)

#### Lock sources (Phase 0 spikes) (MUST)

- 0.70.0 — PayloadNormalizer + FloatPolicy:
  - json-like модель без float/NaN/INF (float-forbidden policy)
  - list vs map детермінізм (list order preserved; maps stable key order)
- 0.90.0 — Two-phase config merge + directives + safe explain:
  - директиви allowlist: `@append`, `@prepend`, `@remove`, `@merge`, `@replace`
  - reserved namespace guard: будь-який `@*` поза allowlist — hard fail
  - exclusive-level rule + error precedence (unknown `@*` має пріоритет)
  - empty-array rule (`[]`) контекстно трактуємо як list або map
  - explain trace: deterministic ordering + redaction (no values)
- 0.60.0 — Fingerprint safe explain + tracked_env semantics:
  - missing vs empty MUST be distinguishable (детермінізм присутності)
- 0.20.0 — Phase 0 rails: no-secrets output policy як базова безпекова норма

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- vendor concretes
- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/Mail/MailerInterface.php`
- [ ] `framework/packages/core/contracts/src/Mail/MailTransportInterface.php`
- [ ] `framework/packages/core/contracts/src/Mail/MailMessage.php`
  - [ ] message data shape MUST NOT be logged raw
  - [ ] mail message model is a contracts transport model but is outside DTO marker policy unless explicitly marked
- [ ] `framework/packages/core/contracts/src/Mail/MailException.php`

- [ ] `docs/adr/ADR-0012-mail-port.md`
- [ ] `docs/ssot/mail-contracts.md` — ports + redaction rules

- [ ] `framework/packages/core/contracts/tests/Contract/MailContractsShapeContractTest.php`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/mail-contracts.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0012-mail-port.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Security / Redaction

- [ ] MUST NOT leak recipients/body/credentials; only counts + safe hashes (implementation-side)
- [ ] MUST NOT leak recipients, message body, or credentials in logs/errors/metrics.
- [ ] On transport failure, error reporting MUST NOT log recipients or body (only safe counts/hashes).

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (contracts-only; proven by contract test)

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/core/contracts/tests/Contract/MailContractsShapeContractTest.php`

### DoD (MUST)

- [ ] Contracts exist + tested
- [ ] Docs exist
- [ ] Runtime expectation (policy, NOT deps):
  - [ ] `platform/mail` uses these contracts; transports live in `integrations/*`.
  - [ ] Credentials/secrets are resolved via the secrets port (never printed).

---

### 1.180.0 Contracts: Secrets port (MUST) [CONTRACTS]

---
type: package
phase: 1
epic_id: "1.180.0"
owner_path: "framework/packages/core/contracts/"

package_id: "core/contracts"
composer: "coretsia/core-contracts"
kind: library

goal: "Any package can request secrets via SecretsResolverInterface without any risk of secret values leaking to logs/metrics/outputs."
provides:
- "Single contracts secrets port (resolver interface)"
- "Hard policy: never log/emit secret values; only refs/hashes/lengths (implementation-side)"
- "Decouples secret backend choice (platform + integrations)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0013-secrets-port.md"
ssot_refs:
- "docs/ssot/secrets-contracts.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — docs baseline exists
  - PRELUDE.20.0 — packaging strategy locked (composer/namespace mapping is single-choice)
  - PRELUDE.30.0 — repo skeleton + composer roots + monorepo PHPUnit harness exist

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists (PRELUDE.10.0)
  - `docs/adr/INDEX.md` — ADR index exists and this epic only appends its ADR registration
  - `framework/composer.json` — tooling workspace root exists (PRELUDE.30.0)
  - `framework/tools/testing/phpunit.xml` — monorepo PHPUnit harness exists (PRELUDE.30.0)

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none (defines)

#### Lock sources (Phase 0 spikes) (MUST)

- 0.70.0 — PayloadNormalizer + FloatPolicy:
  - json-like модель без float/NaN/INF (float-forbidden policy)
  - list vs map детермінізм (list order preserved; maps stable key order)
- 0.90.0 — Two-phase config merge + directives + safe explain:
  - директиви allowlist: `@append`, `@prepend`, `@remove`, `@merge`, `@replace`
  - reserved namespace guard: будь-який `@*` поза allowlist — hard fail
  - exclusive-level rule + error precedence (unknown `@*` має пріоритет)
  - empty-array rule (`[]`) контекстно трактуємо як list або map
  - explain trace: deterministic ordering + redaction (no values)
- 0.60.0 — Fingerprint safe explain + tracked_env semantics:
  - missing vs empty MUST be distinguishable (детермінізм присутності)
- 0.20.0 — Phase 0 rails: no-secrets output policy як базова безпекова норма

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none

Forbidden:

- vendor concretes
- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- Runtime expectation (policy):
  - debug outputs MUST redact and MUST NOT print resolved secret values

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/contracts/src/Secrets/SecretsResolverInterface.php`

- [ ] `docs/adr/ADR-0013-secrets-port.md`
- [ ] `docs/ssot/secrets-contracts.md` — usage rules + redaction requirements

- [ ] `framework/packages/core/contracts/tests/Contract/SecretsResolverInterfaceShapeContractTest.php`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/secrets-contracts.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0013-secrets-port.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Security / Redaction

- [ ] MUST NOT leak secret values anywhere (logs/spans/metrics/stdout/http debug)
- [ ] Allowed: `ref`, `hash(value)` (without printing value), `len(value)`

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (contracts-only; proven by contract test)

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/core/contracts/tests/Contract/SecretsResolverInterfaceShapeContractTest.php`

### DoD (MUST)

- [ ] Contract exists + tested
- [ ] Docs exist
- [ ] Downstream packages can rely on this port without new deps
- [ ] Runtime expectation (policy, NOT deps):
  - [ ] `platform/secrets` binds a resolver (Null/Env/Vault/…); downstream packages depend ONLY on `SecretsResolverInterface`.
  - [ ] Debug outputs (CLI/HTTP debug endpoints) MUST redact and MUST NOT print resolved secret values.

---

### 1.190.0 Cross-cutting baseline runtime invariants + HTTP middleware taxonomy enforcement (MUST) [DOC]

---
type: docs
phase: 1
epic_id: "1.190.0"
owner_path: "docs/ssot/http-middleware-catalog.md"

goal: "Lock runtime-wide cross-cutting invariants (Context/UoW, noop-safe observability, redaction) and cement the canonical HTTP middleware slots taxonomy (system/app/route) as the only allowed model."
provides:
- "Canonical cross-cutting invariants (Context/UoW, observability, redaction)"
- "Canonical HTTP middleware slots model (system/app/route) with ownership rules"
- "Noop-safe observability baseline: tracer/meter/logger never throw"
- "Baseline HTTP cross-cutting middleware catalog (platform/http-owned items only; optional packages are documented as conditional)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/tags.md"
- "docs/ssot/config-roots.md"
- "docs/ssot/observability.md"
- "docs/ssot/observability-and-errors.md"
- "docs/ssot/uow-and-reset-contracts.md"
- "docs/ssot/http-middleware-catalog.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.10.0 — repo docs/legal baseline exists
  - PRELUDE.20.0 — packaging strategy locked (`docs/architecture/PACKAGING.md`)
  - PRELUDE.30.0 — repo skeleton + composer/CI entrypoints exist (`framework/`, `skeleton/`, CI baseline)
  - PRELUDE.50.0 — dependency table SSoT exists (`docs/roadmap/phase0/00_2-dependency-table.md`)
  - 1.10.0 — Tag registry exists (reserved tags + ownership)
  - 1.20.0 — Config roots registry exists (reserved roots + ownership)
  - 1.40.0 — Observability SSoT exists (naming + label allowlist)
  - 1.50.0 — Tooling rails exist (CI runs gates/arch + contract suites)
  - 1.90.0 — Observability/errors contracts exist (ports/VOs; format-neutral)
  - 1.95.0 — ContextAccessorInterface exists
  - 1.120.0 — Reset/UoW contracts exist (ResetInterface + hooks)

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists and this epic appends its registrations here
  - `docs/ssot/tags.md`
  - `docs/ssot/config-roots.md`
  - `docs/ssot/observability.md`
  - `docs/ssot/observability-and-errors.md`
  - `docs/ssot/uow-and-reset-contracts.md`
  - `docs/architecture/PACKAGING.md`
  - `docs/roadmap/phase0/00_2-dependency-table.md`
  - `framework/composer.json`
  - `framework/tools/testing/phpunit.xml`

- Required config roots/keys:
  - none

- Required tags (from tag registry SSoT):
  - http.middleware.system_pre
  - http.middleware.system
  - http.middleware.system_post
  - http.middleware.app_pre
  - http.middleware.app
  - http.middleware.app_post
  - http.middleware.route_pre
  - http.middleware.route
  - http.middleware.route_post
  - kernel.reset
  - kernel.stateful
  - kernel.hook.before_uow
  - kernel.hook.after_uow

- Required contracts / ports:
  - `Psr\Log\LoggerInterface`
  - `Psr\Http\Server\MiddlewareInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (coordination epic; deps are enforced per owning package)

Forbidden:

- (invariant; enforced per-package via deptrac):
  - `platform/http` MUST NOT depend on any other runtime packages (`platform/*`, `enterprise/*`, `integrations/*`)
  - allowed compile-time inputs for `platform/http` are limited to `core/*`, `core/contracts`, and PSR interfaces

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
  - `Psr\Http\Server\MiddlewareInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Runtime expectations (policy, NOT deps) (MUST)

- Noop-safe baseline:
  - `Psr\Log\LoggerInterface` MUST be injectable and MUST NOT throw (NullLogger fallback allowed)
  - `TracerPortInterface` MUST be injectable and MUST NOT throw
  - `MeterPortInterface` MUST be injectable and MUST NOT throw

- Discovery / wiring is via tags (registry owner: `1.10.0`) — single-choice taxonomy:
  - `http.middleware.system_pre|system|system_post`
  - `http.middleware.app_pre|app|app_post`
  - `http.middleware.route_pre|route|route_post`
  - `kernel.reset`
  - `kernel.hook.before_uow`
  - `kernel.hook.after_uow`

- Forbidden legacy tags (cemented):
  - `http.middleware.user_before_routing`
  - `http.middleware.user`
  - `http.middleware.user_after_routing`

### Entry points / integration points (MUST)

- HTTP:
  - This epic documents the baseline catalog and locks coordination/policy invariants only.
  - Concrete platform-owned baseline pieces are implemented exclusively by their owning package epics.
  - Canonical middleware slots (SSoT) — system/app/route taxonomy (MUST match Phase 0 spikes slot keys):

    - Slot `http.middleware.system_pre`:
      - prio 950  `platform/http` → `\Coretsia\Http\Middleware\CorrelationIdMiddleware::class` (auto)
      - prio 940  `platform/http` → `\Coretsia\Http\Middleware\RequestIdMiddleware::class` (auto if `http.request_id.enabled`)
      - prio 930  `platform/http` → `\Coretsia\Http\Middleware\TraceContextMiddleware::class` (auto)
      - prio 920  `platform/http` → `\Coretsia\Http\Middleware\HttpMetricsMiddleware::class` (auto)
      - prio 910  `platform/http` → `\Coretsia\Http\Middleware\AccessLogMiddleware::class` (auto)
      - prio 900  `platform/http` → `\Coretsia\Http\Maintenance\MaintenanceMiddleware::class` (auto if enabled)
      - prio 880  `platform/http` → `\Coretsia\Http\Middleware\TrustedProxyMiddleware::class` (auto if `http.proxy.enabled`)
      - prio 870  `platform/http` → `\Coretsia\Http\Middleware\RequestContextMiddleware::class` (auto if `http.context.enrich.enabled`)
      - prio 860  `platform/http` → `\Coretsia\Http\Middleware\TrustedHostMiddleware::class` (auto if `http.hosts.enabled`)
      - prio 850  `platform/http` → `\Coretsia\Http\Middleware\HttpsRedirectMiddleware::class` (auto if `http.https_redirect.enabled`)
      - prio 840  `platform/http` → `\Coretsia\Http\Middleware\CorsMiddleware::class` (auto if `http.cors.enabled`)
      - prio 830  `platform/http` → `\Coretsia\Http\Middleware\RequestBodySizeLimitMiddleware::class` (auto)
      - prio 820  `platform/http` → `\Coretsia\Http\Middleware\MethodOverrideMiddleware::class` (auto if `http.method_override.enabled`)
      - prio 810  `platform/http` → `\Coretsia\Http\Middleware\ContentNegotiationMiddleware::class` (auto if `http.negotiation.enabled`)
      - prio 800  `platform/http` → `\Coretsia\Http\Middleware\JsonBodyParserMiddleware::class` (auto if `http.request.json.enabled`)
      - prio 790  `platform/http` → `\Coretsia\Http\Middleware\EarlyRateLimitMiddleware::class` (auto if `http.rate_limit.early.enabled`)
      - prio 580  `platform/http` → `\Coretsia\Http\Debug\DebugEndpointsMiddleware::class` (OPTIONAL SHAPE, dev-only; must be opt-in)

      - (optional packages; NOT referenced by `platform/http` defaults; they self-register if installed):
        - prio 1000 `platform/problem-details` → `\Coretsia\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware::class` (if installed, it SHOULD register itself)
        - prio 600  `platform/health` → `\Coretsia\Health\Http\Middleware\HealthEndpointsMiddleware::class` (if installed, it SHOULD register itself)
        - prio 590  `platform/metrics` → `\Coretsia\Metrics\Http\Middleware\MetricsEndpointMiddleware::class` (if installed, it SHOULD register itself)

    - Slot `http.middleware.system`:
      - (reserved; empty by default)

    - Slot `http.middleware.system_post`:
      - prio 900  `platform/http` → `\Coretsia\Http\Middleware\CacheHeadersMiddleware::class` (auto if `http.cache_headers.enabled`)
      - prio 800  `platform/http` → `\Coretsia\Http\Middleware\EtagMiddleware::class` (auto if `http.etag.enabled`)
      - prio 700  `platform/http` → `\Coretsia\Http\Middleware\CompressionMiddleware::class` (auto if `http.compression.enabled`)
      - prio 600  `platform/http` → `\Coretsia\Http\Middleware\SecurityHeadersMiddleware::class` (auto if `http.security_headers.enabled`)

      - (optional packages; NOT referenced by `platform/http` defaults; they self-register if installed):
        - prio 650  `platform/streaming` → `\Coretsia\Streaming\Http\Middleware\DisableBufferingMiddleware::class` (if installed, it SHOULD register itself)
        - prio 500  `devtools/dev-tools` → `\Coretsia\DevTools\Http\Middleware\DebugbarMiddleware::class` (if installed, dev-only, it SHOULD register itself)

    - Slot `http.middleware.app_pre`:
      - prio 150  `platform/http` → `\Coretsia\Http\Middleware\RateLimitMiddleware::class` (auto if `http.rate_limit.enabled`; identity-aware placement)

      - (optional packages; NOT referenced by `platform/http` defaults; they self-register if installed):
        - prio 500  `enterprise/tenancy` → `\Coretsia\Tenancy\Http\Middleware\TenantContextMiddleware::class` (if installed, it SHOULD register itself)
        - prio 350  `platform/async` → `\Coretsia\Async\Http\Middleware\RequestTimeoutMiddleware::class` (if installed, it SHOULD register itself)
        - prio 300  `platform/session` → `\Coretsia\Session\Http\Middleware\SessionMiddleware::class` (if installed, it SHOULD register itself)
        - prio 200  `platform/auth` → `\Coretsia\Auth\Http\Middleware\AuthMiddleware::class` (if installed, it SHOULD register itself)
        - prio 100  `platform/security` → `\Coretsia\Security\Http\Middleware\CsrfMiddleware::class` (if installed, it SHOULD register itself)
        - prio 80   `platform/uploads` → `\Coretsia\Uploads\Http\Middleware\MultipartFormDataMiddleware::class` (if installed, it SHOULD register itself)
        - prio 50   `platform/inbox` → `\Coretsia\Inbox\Http\Middleware\IdempotencyKeyMiddleware::class` (if installed, it SHOULD register itself)

    - Slot `http.middleware.app`:
      - (optional packages; NOT referenced by `platform/http` defaults; they self-register if installed):
        - prio 100  `platform/http-app` → `\Coretsia\HttpApp\Middleware\RouterMiddleware::class` (if installed, it SHOULD register itself)

    - Slot `http.middleware.app_post`:
      - (reserved; empty by default)

    - Slot `http.middleware.route_pre`:
      - (reserved; empty by default)

    - Slot `http.middleware.route`:
      - (reserved; empty by default)

    - Slot `http.middleware.route_post`:
      - (reserved; empty by default)

  - Opt-in middlewares (MUST NOT be auto-wired):
    - `platform/auth`:
      - `\Coretsia\Auth\Http\Middleware\RequireAuthMiddleware::class`
      - `\Coretsia\Auth\Http\Middleware\RequireAbilityMiddleware::class`

- Kernel hooks/tags:
  - consumer MUST NOT enumerate reset tags directly (reset all stateful services; deterministic)
  - `kernel.hook.before_uow`, `kernel.hook.after_uow`

- Note on external owners (non-binding in this epic):
  - If `platform/problem-details` is present, it SHOULD register a top-level error handling middleware in `http.middleware.system_pre` with the highest priority.
  - This epic does NOT introduce or require that package.

- Artifacts:
  - (policy only here; concrete artifacts are owned by their dedicated runtime epics, e.g. `core/kernel` for kernel artifacts and `platform/routing` for `routes@1`)

### Deliverables (MUST)

#### Creates

- [ ] `docs/ssot/http-middleware-catalog.md` — canonical catalog reference (slot/priority/owner/toggle), single-choice MUST include:
  - [ ] Early pre-identity throttling and identity-aware throttling are distinct middleware roles:
    - [ ] `\Coretsia\Http\Middleware\EarlyRateLimitMiddleware::class` → `http.middleware.system_pre`
    - [ ] `\Coretsia\Http\Middleware\RateLimitMiddleware::class` → `http.middleware.app_pre`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register `docs/ssot/http-middleware-catalog.md`

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

- Default HTTP middleware wiring is via tags (no skeleton config required):
  - `http.middleware.system_pre|system|system_post`
  - `http.middleware.app_pre|app|app_post`
  - `http.middleware.route_pre|route|route_post`

- Clarification (single-choice): tag-based registration/discovery remains the canonical source for middleware participation.
- Later compiled config artifacts MAY materialize and/or augment middleware slot lists for runtime consumption by `platform/http`, but MUST NOT redefine slot taxonomy or ownership from `docs/ssot/tags.md`.
- Deterministic ordering and discovery-consumption rules are Foundation-owned and MUST remain compatible with this taxonomy when the corresponding owner epic introduces them.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] semantic context keys:
    - [ ] `correlation_id`
    - [ ] `uow_id`
    - [ ] `uow_type`
- [ ] Context writes (safe only):
  - [ ] semantic context keys:
    - [ ] `correlation_id`
    - [ ] `uow_id`
    - [ ] `uow_type`
  - [ ] request-safe semantic keys only:
    - [ ] `client_ip`
    - [ ] `scheme`
    - [ ] `host`
    - [ ] `path`
    - [ ] `user_agent`
  - [ ] reserved later safe keys (introduced only by their owner epics + `ContextKeys` SSoT; not baseline writers here):
    - [ ] `request_id`
    - [ ] `path_template`
    - [ ] `http_response_format`
    - [ ] `actor_id`
    - [ ] `tenant_id`
- [ ] Reset discipline:
  - [ ] stateful services implement `ResetInterface`
  - [ ] stateful services MUST be tagged `kernel.stateful` as the fixed enforcement marker
  - [ ] stateful services MUST also be discoverable through Foundation reset discovery (effective reset tag; reserved default `kernel.reset`)
  - [ ] runtime execution MUST NOT depend on `kernel.stateful`; it is enforcement-only

- [ ] Concrete `Coretsia\Foundation\Context\ContextKeys` constants are introduced and locked only by the owning Foundation epic; this coordination epic MUST NOT require that class before the owner package is reviewed and fixed.

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `http.request` (attrs safe; per `docs/ssot/observability.md`)
- [ ] Metrics:
  - [ ] `http.request_total` (labels: `method,status,outcome` only)
  - [ ] `http.request_duration_ms` (labels: `method,status,outcome` only)
- [ ] Label normalization applied (if needed):
  - [ ] `uow_type -> operation` (for cross-runtime metrics if used)
- [ ] Rate-limit observability policy:
  - [ ] early and identity-aware rate limiting MUST remain distinguishable in code/docs/tests
  - [ ] no new metric label keys are introduced for this distinction unless separately allowed by observability SSoT
  - [ ] any distinction between early vs identity-aware rate limiting MUST be encoded via metric name choice, event name, or internal wiring — not via forbidden high-cardinality labels
- [ ] Logs:
  - [ ] structured access log per request; MUST NOT emit raw path, raw query, headers, cookies, body, auth/session ids, tokens, or raw SQL
  - [ ] if path-like information is needed, use `path_template` or `hash(value)` / `len(value)`, never raw `path`
  - [ ] redaction applied; no secrets/PII/raw payloads

#### Errors

- Exceptions introduced:
  - N/A (no new exception taxonomy in this epic)
- [ ] Policy anchors:
  - [ ] error taxonomy is defined in `core/contracts` contracts/descriptor models and related contracts epics
  - [ ] concrete error flow is implemented by `platform/errors`
  - [ ] error rendering is owned by `platform/problem-details` middleware (outermost wrapper)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] auth/cookies/session ids/tokens/raw SQL/raw payload
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids

### Canonical baseline catalog rules (MUST)

- `platform/http/config/http.php` MUST contain ONLY middlewares shipped by `platform/http`.
- Optional/external packages MUST NOT be referenced by class-string defaults inside `platform/http` config.
- Optional/external packages MAY be documented in `docs/ssot/http-middleware-catalog.md` as “if installed, they SHOULD register themselves”.
- `platform/http` MUST treat early and identity-aware rate limiting as two distinct middleware responsibilities.
- A single middleware class MUST NOT be auto-wired into both `http.middleware.system_pre` and `http.middleware.app_pre`.
- Early rate limiting and identity-aware rate limiting MUST use distinct config toggles.

### Canonical baseline placement (single-choice) (MUST)

- Rate limit middleware placement is single-choice and non-negotiable:
  - `\Coretsia\Http\Middleware\EarlyRateLimitMiddleware::class` MAY be registered ONLY into `http.middleware.system_pre`.
  - It MUST NOT be present in `http.middleware.system|system_post|app_pre|app|app_post|route_pre|route|route_post`.
  - Rationale: early anonymous/IP/infra throttling must happen before app identity context is available and before deeper app work is performed.

  - `\Coretsia\Http\Middleware\RateLimitMiddleware::class` MAY be registered ONLY into `http.middleware.app_pre`.
  - It MUST NOT be present in `http.middleware.system_pre|system|system_post|app|app_post|route_pre|route|route_post`.
  - Rationale: identity-aware decisions must run after app-level identity context is available.

### Verification (TEST EVIDENCE) (MUST when applicable)

- Future owner-package evidence (reference only; NOT owned by this epic):
  - [ ] `framework/packages/core/foundation/tests/Contract/ContextKeysAreStableContractTest.php`
  - [ ] `framework/packages/platform/tracing/tests/Contract/W3CPropagationDeterministicContractTest.php`
  - [ ] `framework/packages/platform/metrics/tests/Contract/NoopNeverThrowsContractTest.php`
  - [ ] `framework/packages/platform/logging/tests/Integration/CorrelationIdIsAlwaysPresentInLogsTest.php`

### Tests (MUST)

- Gates/Arch:
  - [ ] CI runs `framework/tools/gates/cross_cutting_contract_gate.php` (from 1.50.0 rails)
- Referenced owner-package contract/integration tests are evidence inputs for this coordination policy, but they are owned by their respective package epics, not by this epic.

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] No forward references in Preconditions
- [ ] Middleware taxonomy is single-choice (system/app/route)
- [ ] platform/http defaults do not reference optional package class names
- [ ] Noop-safe invariant is explicitly locked by this epic; concrete contract evidence is owned by the relevant package epics.
- [ ] Observability policy matches `docs/ssot/observability.md`
- [ ] Cross-cutting policy is anchored by referenced owner-package contract/integration evidence (noop never throws; keys stable; propagation deterministic).
- [ ] Middleware catalog snapshot exists and is stable as reference for later epics
- [ ] Does NOT implement business features (routing/http-app/session/auth/…); only baseline cross-cutting invariants + SSoT catalog snapshot.
- [ ] Does NOT introduce new metric label keys outside the allowlist (`method,status,driver,operation,table,outcome`) defined in `docs/ssot/observability.md`.
- [ ] Does NOT log/export secrets or PII (only `hash(value)` / `len(value)` / safe ids).
- [ ] Expected runtime acceptance outcome is documented: when the Micro preset handles a request, correlation/tracing/metrics/log envelope is present and noop implementations never throw; concrete proof is owned by future package epics.
- [ ] Rate limiting ambiguity is removed:
  - [ ] early anonymous/IP throttling uses `EarlyRateLimitMiddleware`
  - [ ] identity-aware throttling uses `RateLimitMiddleware`
  - [ ] no single middleware class is listed in both `system_pre` and `app_pre`
  - [ ] config toggles for early vs identity-aware rate limiting are distinct
- [ ] This epic is coordination/policy-only for cross-cutting invariants and catalog snapshot.
- [ ] Concrete package files for `platform/http`, `platform/logging`, `platform/tracing`, and `platform/metrics` MUST be created/modified only by their owning package epics.

---

### 1.200.0 Foundation: DI Container + Tags + DeterministicOrder + Reset orchestration (MUST) [IMPL]

---
type: package
phase: 1
epic_id: "1.200.0"
owner_path: "framework/packages/core/foundation/"

package_id: "core/foundation"
composer: "coretsia/core-foundation"
kind: runtime
module_id: "core.foundation"

goal: "Всі runtime-пакети можуть реєструвати сервіси/теги детерміновано і бути reset-safe у long-running runtime без дублювання сортування/registry логіки."
provides:
- "PSR-11 DI container із детермінованим build/compile та строгими правилами autowire (без autowire інтерфейсів)."
- "Єдиний TagRegistry + єдине правило сортування DeterministicOrder (priority DESC, id ASC) для всіх runtime списків."
- "Reset orchestration for the effective Foundation reset discovery tag (`foundation.reset.tag`), where the reserved default name is `kernel.reset` (kernel only calls orchestrator)."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md"
ssot_refs:
- "docs/ssot/di-tags-and-middleware-ordering.md"
- "docs/ssot/http-middleware-catalog.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.20.0 — packaging strategy locked
  - PRELUDE.30.0 — composer roots + monorepo test harness exist
  - 1.10.0 — Tag registry SSoT exists (`docs/ssot/tags.md`)
  - 1.20.0 — Config roots registry SSoT exists (`docs/ssot/config-roots.md`)
  - 1.120.0 — reset contracts exist (`Coretsia\Contracts\Runtime\ResetInterface`)
  - 1.190.0 — canonical HTTP middleware catalog SSoT exists (`docs/ssot/http-middleware-catalog.md`)

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/` — provides runtime ports (`ResetInterface`, hooks, etc.)
  - `docs/ssot/http-middleware-catalog.md` — canonical HTTP catalog referenced by `docs/ssot/di-tags-and-middleware-ordering.md`

- Required config roots/keys:
  - `foundation.*` — config root MUST exist (canonical config policy applies: `config/foundation.php` returns subtree, runtime reads under `foundation.*`).
  - `foundation.container.*` — MUST exist for strict container behavior:
    - `Container::canAutowire` is strict: if `config['foundation']` or `config['foundation']['container']` is missing → MUST throw `ContainerException` deterministically.

- Required tags:
  - N/A

- Required contracts / ports:
  - `Psr\Container\ContainerInterface` — PSR-11 container port.
  - `Psr\Container\NotFoundExceptionInterface` — PSR-11.
  - `Psr\Container\ContainerExceptionInterface` — PSR-11.
  - `Coretsia\Contracts\Runtime\ResetInterface` — stateful reset contract.
  - `Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface` — (referenced as an example integration surface).
  - `Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface` — (referenced as an example integration surface).

- Out of scope (legacy non-goals; enforced by deps + deliverables discipline):
  - Kernel lifecycle implementation (owned by `core/kernel`, not here).
  - Introducing new ports outside `core/contracts`.
  - Implementing HTTP middleware stack (only documents ordering + tags usage).

- Acceptance scenario (legacy):
  - When multiple services are tagged with the same tag and mixed priorities, then `TagRegistry->all($tag)` always returns the same deterministic order across runs/OS.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`

Forbidden:

- `platform/*`
- `integrations/*`
- `devtools/*`
  - includes (non-exhaustive):
    - `devtools/internal-toolkit`
    - `devtools/cli-spikes`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Container\ContainerInterface`
  - `Psr\Container\NotFoundExceptionInterface`
  - `Psr\Container\ContainerExceptionInterface`
- Contracts:
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface`
  - `Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder`
  - `Coretsia\Foundation\Tag\TagRegistry`

### Entry points / integration points (MUST)

- CLI:
  - Phase 0 note (IMPORTANT):
    - Phase 0 `platform/cli` (epic 0.130.0) є kernel-free і використовує лише config registry `cli.commands`.
    - В Phase 0 **немає** tag-based discovery для команд (тобто `cli.command` не читається).
  - Phase 1+ note (future/kernel-backed CLI):
    - Tag-based discovery команд (`cli.command`) можливий лише у режимі CLI, який працює поверх kernel/container (Phase 1+).
    - `core/foundation` надає механізм TagRegistry + deterministic ordering, але **не є owner** тегу `cli.command`.

- HTTP:
  - middleware slots/tags (taxonomy is cemented; reserved names; implementation owner is a future package):
    - **Owner:** `platform/http`.
      Initial package support may appear in Phase 1; broader HTTP implementation expands in later HTTP epics.
      Until the corresponding HTTP implementation is present, these tag names are **reserved** and MUST NOT be redefined by any other package.
    - canonical slots (cemented):
      - `http.middleware.system_pre`
      - `http.middleware.system`
      - `http.middleware.system_post`
      - `http.middleware.app_pre`
      - `http.middleware.app`
      - `http.middleware.app_post`
      - `http.middleware.route_pre`
      - `http.middleware.route`
      - `http.middleware.route_post`
  - Ordering (single-choice): `priority DESC, id ASC` via `Coretsia\Foundation\Discovery\DeterministicOrder`
  - Consumption rule (cemented):
    - middleware stacks MUST be composed from `TagRegistry->all(<slotTag>)` outputs
    - consumers MUST NOT re-sort and MUST NOT apply a different dedupe rule

- Kernel hooks/tags (reset discipline):

  **Reset discovery ownership (single-choice; cemented):**
  - Foundation owns reset discovery through configuration key:
    - `foundation.reset.tag`
  - The reserved default value is:
    - `kernel.reset`
  - `kernel.reset` is therefore the **default tag name**, not a kernel-owned contract.
  - `core/kernel` MUST NOT know, read, or hardcode the reset discovery tag name.

  **Fixed enforcement marker (single-choice; cemented):**
  - `kernel.stateful` is a fixed, non-configurable marker tag used only by CI/static-analysis rails.
  - Runtime execution MUST NOT depend on `kernel.stateful`.

  **Canonical discovery list authority (single-choice; cemented):**
  - `Coretsia\Foundation\Tag\TagRegistry::all(string $tag): list<TaggedService>`
    is the single source of truth for discovery lists.
  - Ordering of any discovery list is owned by Foundation and is always:
    - `priority DESC, id ASC`
    - string comparison MUST be byte-order / locale-independent (`strcmp`)
  - Consumers MUST NOT re-sort and MUST NOT apply a different dedupe rule.

  **Reset execution source-of-truth (single-choice; cemented):**
  - `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator` is the only runtime executor used by `core/kernel`.
  - `ResetOrchestrator` MUST:
    1) read the effective discovery tag from Foundation configuration/wiring (`foundation.reset.tag`, default `kernel.reset`)
    2) obtain the discovery list via `TagRegistry->all($effectiveResetTag)`
    3) resolve services via PSR-11 container
    4) execute reset deterministically
    5) call `ResetInterface::reset()` exactly once per resolved service per reset cycle

  **Reset execution ordering (single-choice; cemented):**
  - Legacy/base mode:
    - reset executor MUST iterate services in the EXACT order returned by:
      - `TagRegistry->all($effectiveResetTag)`
    - MUST NOT parse reset meta
    - MUST NOT apply any additional sorting
  - Enhanced mode (epic 1.250.0):
    - reset executor MAY compute a deterministic reset plan from supported meta keys
    - execution order becomes:
      1) `priority` DESC
      2) `group` ASC (`strcmp`, normalized)
      3) `serviceId` ASC (`strcmp`)
    - when enhanced mode is disabled, behavior MUST remain EXACT legacy/base mode

  **Kernel integration rule (single-choice; cemented):**
  - `core/kernel` MUST NOT enumerate tagged reset services directly.
  - `core/kernel` MUST call ONLY:
    - `ResetOrchestrator::resetAll(): void`
  - reset trigger point is:
    - after each UoW, after after-uow hooks complete

  **Stateful-services invariant (single-choice; cemented):**
  - If a service is stateful, it MUST be explicitly tagged:
    - `kernel.stateful`
  - Any service tagged `kernel.stateful` MUST:
    - implement `Coretsia\Contracts\Runtime\ResetInterface`
    - also be tagged with the effective Foundation reset discovery tag (`foundation.reset.tag`, default `kernel.reset`)
  - Kernel does NOT consume `kernel.stateful` at runtime; the marker exists only for enforceability rails.

  **Canonical reset flow (conceptual pseudo-code):**

```php
  beginUow()
  run before_uow hooks
  handle UoW
  run after_uow hooks
  resetOrchestrator.resetAll()
  endUow()
```

- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/foundation/src/Module/FoundationModule.php` — runtime module
- [ ] `framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php` — DI wiring entrypoint
- [ ] `framework/packages/core/foundation/src/Provider/FoundationServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/core/foundation/README.md` — package docs (Observability / Errors / Security-Redaction)

Container:
- [ ] `framework/packages/core/foundation/src/Container/Container.php` — PSR-11 container runtime
- [ ] `framework/packages/core/foundation/src/Container/ContainerBuilder.php` — deterministic container build from providers
  - [ ] MUST preserve the caller-supplied provider order exactly (single-choice):
    - [ ] `ContainerBuilder` MUST NOT re-sort providers
    - [ ] upstream owner (later kernel/module-plan boot) MUST supply a deterministic provider list
    - [ ] rationale:
      - [ ] keeps DI override semantics aligned with deterministic module/provider order
      - [ ] keeps TagRegistry dedupe (“first wins”) deterministic without imposing an arbitrary global FQCN sort
  - [ ] Container definition collision policy (single-choice; cemented):
    - [ ] for the same service id / interface binding, the later provider definition overrides the earlier one deterministically
    - [ ] this rule applies to container bindings/definitions only
    - [ ] tag dedupe remains independent and unchanged:
      - [ ] `TagRegistry` keeps first occurrence per `(tag, serviceId)`
  - [ ] Rationale:
    - [ ] makes TagRegistry dedupe (“first wins”) deterministic across OS/runs
- [ ] `framework/packages/core/foundation/src/Container/ServiceProviderInterface.php` — provider contract
- [ ] `framework/packages/core/foundation/src/Container/Exception/ContainerException.php` — implements PSR-11 ContainerExceptionInterface
- [ ] `framework/packages/core/foundation/src/Container/Exception/NotFoundException.php` — implements PSR-11 NotFoundExceptionInterface
- [ ] `framework/packages/core/foundation/src/Container/ContainerDiagnostics.php` — deterministic diagnostics snapshot (services + tags)
  - [ ] ContainerDiagnostics / runtime artifacts often require **byte-stable JSON**.
  - [ ] MUST be safe by construction:
    - [ ] MUST NOT dump service instances, constructor args, or reflection data
    - [ ] MUST NOT include tag meta values (meta can contain arbitrary user data)
      - [ ] allowed: tag name, service id, priority
      - [ ] forbidden: serializing `meta` values
  - [ ] MUST provide deterministic export:
    - [ ] `toArray(): array` — normalized structure (maps already sorted; lists stable)
    - [ ] `toJson(): string` — uses `Coretsia\Foundation\Serialization\StableJsonEncoder`
      - [ ] because it uses `StableJsonEncoder`, the serialized JSON MUST end with a final newline
  - [ ] Determinism:
    - [ ] ordering MUST be locale-independent (`strcmp`)
    - [ ] output MUST be rerun-no-diff across OS

Serialization:
- [ ] `framework/packages/core/foundation/src/Serialization/StableJsonEncoder.php` — deterministic JSON encoder (runtime-safe)
  - [ ] Purpose:
    - [ ] produce stable JSON bytes for diagnostics/artifacts
    - [ ] prevent “accidental json_encode drift” (key order, whitespace, newline, floats)
  - [ ] Inputs (single-choice; cemented):
    - [ ] accepts only JSON-safe deterministic types:
      - [ ] `null|bool|int|string|list|array<string, value>`
    - [ ] MUST reject:
      - [ ] `float` (incl. `NaN`, `INF`, `-INF`)
      - [ ] `resource`, `object`, `Closure`
      - [ ] non-string map keys (incl. int keys outside list semantics)
    - [ ] Note:
      - [ ] callable-ness is NOT treated as a standalone runtime type check here
      - [ ] plain strings remain plain strings even if PHP could call them as function names
  - [ ] Output (single-choice; cemented):
    - [ ] maps: keys sorted by `strcmp` at **every** nesting level
    - [ ] lists: preserve order, no implicit reorder
    - [ ] LF-only
    - [ ] MUST end with a final newline
    - [ ] MUST NOT leak secrets (encoder itself must not inspect env; redaction is caller responsibility)

Tags + deterministic order:
- [ ] `framework/packages/core/foundation/src/Tag/TagRegistry.php` — add/list tagged services (deterministic)
- [ ] `framework/packages/core/foundation/src/Tag/TaggedService.php` — VO `{id, priority, meta}`
- [ ] `framework/packages/core/foundation/src/Discovery/DeterministicOrder.php` — canonical sort rule (priority DESC, id ASC)

### TagRegistry API (cemented)

`Coretsia\Foundation\Tag\TagRegistry` is the single source of truth for tagged service discovery.

#### Data model

- Tag name: `string` (e.g. `kernel.reset`, `http.middleware.app`, `cli.command`)
- Service id: `string` (PSR-11 container service id)
- Tagged item: `Coretsia\Foundation\Tag\TaggedService`:
  - `id: string` (service id)
  - `priority: int`
  - `meta: array<string, mixed>` (optional)

#### Methods (single-choice; cemented)

- `add(string $tag, string $serviceId, int $priority = 0, array $meta = []): void`
- `all(string $tag): list<TaggedService>` (semantic contract; NOT ids)
  - ordering MUST be deterministic via `DeterministicOrder`:
    - `priority DESC, id ASC` (`strcmp`, locale-independent)

#### Dedupe policy (single-choice; cemented)

- If the same `serviceId` is added multiple times for the same `tag`,
  TagRegistry MUST keep the **first occurrence** (“first wins”) deterministically.
- Rationale: prevents accidental double-registration while keeping stable results across OS/runs.

Reset orchestration:
- [ ] `framework/packages/core/foundation/src/Runtime/Reset/ResetOrchestrator.php`
  - [ ] Uses:
    - [ ] `Psr\Container\ContainerInterface` — resolve services by id
    - [ ] `Coretsia\Foundation\Tag\TagRegistry` — source of truth for the effective reset discovery list
    - [ ] `Coretsia\Contracts\Runtime\ResetInterface` — invoked contract
  - [ ] Must:
    - [ ] read the effective reset discovery tag from Foundation wiring/config (`foundation.reset.tag`, default `kernel.reset`)
    - [ ] obtain the reset discovery list ONLY via `TagRegistry->all($effectiveResetTag)`
    - [ ] call `reset()` exactly once per service per invocation
    - [ ] be safely callable when the discovery list is empty
    - [ ] **MUST NOT re-sort** `TagRegistry->all(...)` output in legacy/base mode
    - [ ] never rely on reflection/autowire during reset execution
    - [ ] never emit stdout/stderr
    - [ ] never leak secrets (no dumping service instances / constructor args)
    - [ ] deterministically hard-fail on reset-tag misuse.
    - [ ] Before `1.250.0`, tests MUST lock behavior only (hard-fail, deterministic, safe).
    - [ ] From `1.250.0` onward, the canonical failure becomes:
      - [ ] `ResetException(code=CORETSIA_RESET_SERVICE_NOT_RESETTABLE, message="reset-not-resettable")`

- [ ] `framework/packages/core/foundation/src/Provider/Tags.php` — constants:
  - [ ] `public const KERNEL_RESET = 'kernel.reset';` - reserved canonical default reset tag name
  - [ ] `public const KERNEL_STATEFUL = 'kernel.stateful';` - fixed reserved enforcement marker

Configuration:
- [ ] `framework/packages/core/foundation/config/foundation.php` — config subtree under `foundation`
- [ ] `framework/packages/core/foundation/config/rules.php` — enforces shape

Documentation:
- [ ] `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- [ ] `docs/ssot/di-tags-and-middleware-ordering.md` — MUST explain:
  - [ ] This document MUST NOT redefine tag ownership or registry rows from `docs/ssot/tags.md`.
  - [ ] Canonical HTTP middleware catalog ownership and slot contents live in `docs/ssot/http-middleware-catalog.md`.
  - [ ] This document owns only:
    - [ ] discovery consumption rules,
    - [ ] deterministic ordering rule,
    - [ ] dedupe rule,
    - [ ] consumer obligations (`MUST NOT re-sort`, `MUST NOT re-dedupe`).
  - [ ] canonical middleware slots/tags (cemented):
    - [ ] `http.middleware.system_pre`
    - [ ] `http.middleware.system`
    - [ ] `http.middleware.system_post`
    - [ ] `http.middleware.app_pre`
    - [ ] `http.middleware.app`
    - [ ] `http.middleware.app_post`
    - [ ] `http.middleware.route_pre`
    - [ ] `http.middleware.route`
    - [ ] `http.middleware.route_post`
  - [ ] canonical sort rule (single-choice): `priority DESC, id ASC`
    - [ ] implemented by `Coretsia\Foundation\Discovery\DeterministicOrder`
    - [ ] ordering MUST be locale-independent (`strcmp`, byte-order)
  - [ ] dedupe policy (single-choice; cemented): “first wins”
    - [ ] implemented by `Coretsia\Foundation\Tag\TagRegistry`
    - [ ] consumers MUST treat `TagRegistry->all($tag)` output as canonical:
      - [ ] MUST NOT re-sort
      - [ ] MUST NOT apply a different dedupe rule
  - [ ] priority bands guidance + reference to the canonical HTTP catalog SSoT:
    - [ ] `docs/ssot/http-middleware-catalog.md`
  - [ ] `framework/tools/spikes/fixtures/http_middleware_catalog.php` MAY be cited only as a Phase 0 lock-source/alignment input, NOT as SSoT

Tests:
- [ ] `framework/packages/core/foundation/tests/Unit/ContainerDoesNotAutowireInterfacesTest.php`
- [ ] `framework/packages/core/foundation/tests/Unit/DeterministicOrderSortRuleTest.php`
- [ ] `framework/packages/core/foundation/tests/Contract/DeterministicOrderSortContractTest.php`
- [ ] `framework/packages/core/foundation/tests/Contract/FoundationConfigSubtreeShapeContractTest.php`
- [ ] `framework/packages/core/foundation/tests/Integration/TagRegistryReturnsDeterministicOrderTest.php`
- [ ] `framework/packages/core/foundation/tests/Integration/TagRegistryDedupeFirstWinsTest.php`
- [ ] `framework/packages/core/foundation/tests/Integration/ResetOrchestratorInvokesResetExactlyOncePerServiceTest.php`
- [ ] `framework/packages/core/foundation/tests/Integration/ResetOrchestratorRejectsTaggedNonResettableServiceTest.php`
  - [ ] before `1.250.0`: locks deterministic hard-fail behavior only
  - [ ] from `1.250.0` onward: MUST also assert `ResetException(code=CORETSIA_RESET_SERVICE_NOT_RESETTABLE, message="reset-not-resettable")`
- [ ] `framework/packages/core/foundation/tests/Integration/ResetOrchestratorUsesConfiguredResetTagTest.php`
- [ ] `framework/packages/core/foundation/tests/Integration/ContainerBuilderProviderOrderIsDeterministicTest.php`
  - [ ] asserts `ContainerBuilder` preserves the caller-supplied deterministic provider order exactly
  - [ ] MUST NOT assert global re-sorting by provider FQCN
- [ ] `framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsJsonIsDeterministicContractTest.php`
  - [ ] asserts stable bytes for the same input container snapshot (sorted map keys at all levels, LF-only, final newline)
- [ ] `framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsDoesNotLeakSecretsContractTest.php`
  - [ ] asserts diagnostics never includes env values/tokens/Authorization/Cookie-like keys and never dumps constructor args/instances
- [ ] `framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsDoesNotContainAbsolutePathsContractTest.php`
  - [ ] asserts no `/home/`, `/Users/`, `(?i)\b[A-Z]:(\\|/)`, `\\server\share` patterns appear in serialized diagnostics
- [ ] `framework/packages/core/foundation/tests/Contract/StableJsonEncoderRejectsFloatValuesContractTest.php`
  - [ ] asserts `float/NaN/INF/-INF` are rejected deterministically and messages do not contain raw values
- [ ] `framework/packages/core/foundation/tests/Contract/StableJsonEncoderRejectsNonJsonLikeValuesContractTest.php`
  - [ ] asserts `object/resource/Closure/non-string` map keys are rejected deterministically
- [ ] `framework/packages/core/foundation/tests/Contract/StableJsonEncoderSortsMapKeysRecursivelyContractTest.php`
  - [ ] asserts maps are sorted recursively by `strcmp` and lists preserve order
- [ ] `framework/packages/core/foundation/tests/Unit/ContainerCanAutowireIsStrictOnMissingConfigTest.php`
  - [ ] asserts:
    - [ ] missing `config['foundation']` OR missing `config['foundation']['container']`
      → throws `ContainerException` deterministically
- [ ] `framework/packages/core/foundation/tests/Integration/ContainerBuilderLaterBindingOverridesEarlierBindingTest.php`
  - [ ] asserts later provider binding replaces earlier one deterministically

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/di-tags-and-middleware-ordering.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/core/foundation/composer.json` - має відповідати `coretsia/core-foundation`
  - [ ] MUST require runtime package:
    - [ ] `psr/container`
- [ ] `framework/packages/core/foundation/src/Module/FoundationModule.php`
- [ ] `framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php`
- [ ] `framework/packages/core/foundation/config/foundation.php`
- [ ] `framework/packages/core/foundation/config/rules.php`
- [ ] `framework/packages/core/foundation/README.md` — package docs (Observability / Errors / Security-Redaction / Determinism)
  - [ ] Observability section MUST describe only the bindings actually introduced by this epic
  - [ ] default noop observability/logger bindings are introduced later by `1.205.0` and MUST be documented there once implemented

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/core/foundation/config/foundation.php`
- [ ] Keys (dot):
  - [ ] `foundation.container.autowire_concrete` = true
  - [ ] `foundation.container.allow_reflection_for_concrete` = true
  - [ ] `foundation.reset.tag` = "kernel.reset"
    - [ ] runtime-effective reset discovery tag
    - [ ] reserved default value is `kernel.reset`
    - [ ] consumers outside Foundation MUST NOT read or hardcode this key/string
- [ ] Rules:
  - [ ] `framework/packages/core/foundation/config/rules.php` enforces shape

- IMPORTANT:
  - Tag discovery and reset orchestration are baseline runtime safety mechanisms in Foundation.
  - They MUST NOT be feature-disabled via config.
  - No feature flags such as `foundation.tags.enabled` or `foundation.reset.enabled` are allowed.
  - If no services are registered under the effective reset tag, `ResetOrchestrator::resetAll()` is a deterministic noop by empty-list semantics only.

- Single-choice runtime invariant (cemented):
  - `Coretsia\Foundation\Tag\TagRegistry` is always available when `core/foundation` is enabled.
  - `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator` is always available when `core/foundation` is enabled.
  - Empty discovery lists are represented by empty-list semantics, NOT by disabling the subsystem.

#### Wiring / DI tags (when applicable)

- [ ] Foundation constants for already-canonical tags:
  - [ ] `framework/packages/core/foundation/src/Provider/Tags.php`
  - [ ] constants:
    - [ ] `KERNEL_RESET`
    - [ ] `KERNEL_STATEFUL`
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Foundation\Tag\TagRegistry`
  - [ ] registers: `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`
  - [ ] registers: `Coretsia\Foundation\Discovery\DeterministicOrder` (optional as stateless utility service)

- Foundation-owned providers that register stateful services MUST tag them with the effective reset tag resolved from `foundation.reset.tag`, not with a hardcoded string.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads/writes:
  - N/A
- [ ] Reset discipline:
  - [ ] stateful services implement `Coretsia\Contracts\Runtime\ResetInterface`
  - [ ] tagged with the effective Foundation reset discovery tag (`foundation.reset.tag`, default `kernel.reset`)
  - [ ] fixed enforcement marker `kernel.stateful` for enforcement rails
  - [ ] runtime execution MUST NOT depend on `kernel.stateful`

#### Observability (policy-compliant)

- Spans/Metrics:
  - N/A (foundation itself may stay minimal)
- [ ] Logs:
  - [ ] diagnostics output MUST NOT include secrets/PII (redaction if ever added)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Foundation\Container\Exception\ContainerException` — errorCode `CORETSIA_CONTAINER_ERROR`
    - [ ] `Container::canAutowire` strict: якщо `config['foundation']` або `config['foundation']['container']` відсутні → `ContainerException`
  - [ ] `Coretsia\Foundation\Container\Exception\NotFoundException` — errorCode `CORETSIA_CONTAINER_NOT_FOUND`
- Mapping:
  - N/A (higher layers adapt/mapping if needed)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] env values, secrets, tokens (especially in diagnostics)
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` for potentially sensitive strings in diagnostics (if ever output)
- [ ] `core/foundation` MUST provide a single canonical encoder to avoid drift across packages.

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Referenced enforcement rails (NOT owned by this epic)

- `framework/tools/gates/cross_cutting_contract_gate.php` — cross-cutting contract gate (referenced for traceability; ownership is tooling/gates epics).
- phpstan rule: “tagged/stateful services MUST implement ResetInterface” — referenced; owned by static-analysis rails epics.

#### Required policy tests matrix

- [ ] if effective reset discovery is used → `framework/packages/core/foundation/tests/Integration/ResetOrchestratorInvokesResetExactlyOncePerServiceTest.php`
- [ ] If determinism promised → `framework/packages/core/foundation/tests/Contract/DeterministicOrderSortContractTest.php`
- [ ] if effective reset discovery is used → `framework/packages/core/foundation/tests/Integration/ResetOrchestratorRejectsTaggedNonResettableServiceTest.php` (tag misuse is deterministic hard-fail)
- [ ] if reset discovery tag is config-resolved → `framework/packages/core/foundation/tests/Integration/ResetOrchestratorUsesConfiguredResetTagTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/core/foundation/tests/Unit/ContainerDoesNotAutowireInterfacesTest.php`
  - [ ] `framework/packages/core/foundation/tests/Unit/DeterministicOrderSortRuleTest.php`
- Contract:
  - [ ] `framework/packages/core/foundation/tests/Contract/DeterministicOrderSortContractTest.php`
  - [ ] `framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsJsonIsDeterministicContractTest.php`
  - [ ] `framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsDoesNotLeakSecretsContractTest.php`
  - [ ] `framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsDoesNotContainAbsolutePathsContractTest.php`
- Integration:
  - [ ] `framework/packages/core/foundation/tests/Integration/TagRegistryReturnsDeterministicOrderTest.php`
  - [ ] `framework/packages/core/foundation/tests/Integration/ResetOrchestratorInvokesResetExactlyOncePerServiceTest.php`
  - [ ] `framework/packages/core/foundation/tests/Integration/TagRegistryDedupeFirstWinsTest.php`
  - [ ] `framework/packages/core/foundation/tests/Integration/ResetOrchestratorUsesConfiguredResetTagTest.php`
    - [ ] asserts `ResetOrchestrator` discovers resettable services through `foundation.reset.tag`, not a hardcoded `kernel.reset`
- Gates/Arch:
  - [ ] `framework/tools/gates/cross_cutting_contract_gate.php` (referenced)
  - [ ] phpstan rule: “tagged/stateful services MUST implement ResetInterface” (referenced; owned elsewhere)
- [ ] `framework/packages/core/foundation/tests/Contract/FoundationConfigSubtreeShapeContractTest.php`
  - [ ] MUST fail if `framework/packages/core/foundation/config/foundation.php` returns repeated root:
    - [ ] ✅ returns subtree keys such as: `['container' => [...], 'reset' => [...], ...]`
    - [ ] ❌ forbidden: `['foundation' => [...]]`
  - [ ] MUST NOT require an exact final namespace set for the `foundation` subtree.
  - [ ] Additive namespaces introduced by later `core/foundation` epics are allowed.
  - [ ] The contract MUST only assert:
    - [ ] subtree-only return shape (no repeated root)
    - [ ] no reserved `@*` keys
    - [ ] absence of namespaces explicitly forbidden by the current epic
- [ ] If strict autowire config is promised → `framework/packages/core/foundation/tests/Unit/ContainerCanAutowireIsStrictOnMissingConfigTest.php`

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: rerun-no-diff (ordering/registry behavior)
- [ ] Docs updated:
  - [ ] `framework/packages/core/foundation/README.md`
  - [ ] `docs/ssot/di-tags-and-middleware-ordering.md`
  - [ ] `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- [ ] Typical consumers (when enabled in presets/bundles):
  - [ ] `platform/http` (owner package; implementation expands across later HTTP epics) composes middleware stacks from DI tags `http.middleware.*` and MUST consume discovery lists via `TagRegistry->all(<slotTag>)` (canonical ordering + dedupe).
  - [ ] `core/kernel` triggers reset discipline via foundation reset orchestration: services discovered through the effective Foundation reset tag are reset after each UoW.
- [ ] Discovery / wiring happens via tags (examples; tag owners are the respective packages):
  - [ ] `kernel.reset` — reserved canonical default reset-discovery tag name for `foundation.reset.tag` (owner: `core/foundation`)
  - [ ] `kernel.reset` is NOT a kernel-owned runtime feature; Foundation resolves the effective reset discovery tag through `foundation.reset.tag`
  - [ ] `kernel.stateful` — enforcement marker (global; owner: `core/foundation`)
  - [ ] HTTP middleware slots (cemented taxonomy; **future owner: `platform/http`**):
    - [ ] `http.middleware.system_pre`
    - [ ] `http.middleware.system`
    - [ ] `http.middleware.system_post`
    - [ ] `http.middleware.app_pre`
    - [ ] `http.middleware.app`
    - [ ] `http.middleware.app_post`
    - [ ] `http.middleware.route_pre`
    - [ ] `http.middleware.route`
    - [ ] `http.middleware.route_post`
  - [ ] `cli.command`, `error.mapper`, `health.check` — typical cross-package discovery tags (owners: `platform/cli`, `platform/errors`, `platform/health`, etc.)
- [ ] **Locale independence (single-choice):**
  - [ ] Будь-яке сортування в `core/foundation` MUST бути **locale-independent**.
  - [ ] Sorting MUST використовувати **byte-order** порівняння (`strcmp`) і MUST NOT покладатися на `setlocale(...)`, `LC_ALL`, ICU-collation тощо.
- [ ] **Single canonical ordering rule (cemented):**
  - [ ] `DeterministicOrder` MUST реалізовувати єдине правило: **priority DESC, id ASC**.
  - [ ] `TagRegistry->all($tag)` MUST повертати список **у цьому порядку** завжди (rerun-stable).
- [ ] **Diagnostics serialization stability (MUST):**
  - [ ] Якщо `ContainerDiagnostics` серіалізує структури у JSON/рядок — байти MUST бути стабільні:
    - [ ] maps: ключі відсортовані на кожному рівні за `strcmp`
    - [ ] lists: порядок збережено, без неявних reorder
  - [ ] Diagnostics MUST NOT включати secrets/PII/Authorization/Cookie/env values.
- [ ] **Reserved namespace parity (from config-merge spikes) (MUST):**
  - [ ] Будь-який ключ, що починається з `@`, є **reserved**.
  - [ ] `foundation` config subtree MUST NOT містити `@*` ключів на будь-якій глибині.
  - [ ] `framework/packages/core/foundation/config/rules.php` MUST enforce: `@*` → hard fail.
- [ ] `core/foundation` (runtime) MUST NOT залежати від Phase 0 devtools/tooling пакетів:
  - [ ] Forbidden deps: `devtools/*` (включно `devtools/internal-toolkit`, `devtools/cli-spikes`)
  - [ ] Rationale: Phase 0 tooling libs і gates — tools-only; runtime не має тягнути їх як compile-time deps.
- [ ] **Deterministic ordering authority (cemented):**
  - [ ] `TagRegistry->all($tag)` is canonical for tag discovery lists:
    - [ ] ordering is `priority DESC, id ASC` via `DeterministicOrder`
    - [ ] consumers MUST NOT re-sort or apply different dedupe rules
  - [ ] Reset execution ordering is owned by the Foundation reset executor:
    - [ ] when enhanced reset is disabled → MUST execute in EXACT `TagRegistry->all($effectiveResetTag)` order, where `$effectiveResetTag` is resolved from `foundation.reset.tag` (reserved default `kernel.reset`)
    - [ ] when enhanced reset is enabled (1.250.0) → MUST execute in deterministic planned order: `priority DESC, group ASC, serviceId ASC`
- [ ] `StableJsonEncoder` semantics are locked directly by contract tests, not only indirectly through ContainerDiagnostics

---

### 1.205.0 Foundation: Noop Observability + Logger baseline bindings (MUST) [IMPL]

---
type: package
phase: 1
epic_id: "1.205.0"
owner_path: "framework/packages/core/foundation/"

package_id: "core/foundation"
composer: "coretsia/core-foundation"
kind: runtime
module_id: "core.foundation"

goal: "Будь-який runtime може безпечно резолвити observability/logging порти з contracts ще до встановлення platform/* імплементацій."
provides:
- "Noop implementations for observability ports (MUST NOT throw)."
- "Default DI bindings so containers can always resolve these ports."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/observability-and-errors.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.200.0 — foundation package skeleton + DI provider exist
  - 1.90.0 — observability/errors/profiling contracts exist

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface`
  - `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`
  - `Psr\Log\LoggerInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Tracing\SpanInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface`

- Required deliverables:
  - `framework/packages/core/contracts/` — observability ports exist
  - `framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php` — provider exists and is extended here
  - `framework/packages/core/foundation/README.md` — package README exists and is extended here

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`

Allowed PSR interface deps:
- `psr/log` (for `Psr\Log\LoggerInterface`)

Forbidden:
- `platform/*`
- `integrations/*`
- `devtools/*`

### Rules (MUST)

- Noop implementations MUST NOT throw under any input.
- Noop implementations MUST NOT emit stdout/stderr.
- Noop implementations MUST NOT record/store payloads, headers, tokens, raw SQL, or PII.
- Coretsia-owned noop implementations whose contract explicitly requires json-like structured data MUST obey that policy.
  - floats are forbidden only on those contract-defined json-like payload surfaces.
- `Psr\Log\LoggerInterface` is an explicit exception:
  - `Coretsia\Foundation\Logging\NoopLogger` MUST accept any PSR-3 context and ignore it without validation, storage, or emission.
- `Coretsia\Contracts\Observability\Tracing\SpanInterface` is NOT a standalone root DI binding in this epic.
  - `Coretsia\Foundation\Observability\Tracing\NoopSpan` is obtained from `TracerPortInterface`, not resolved directly from the container.
- Bindings MUST be override-friendly:
  - platform packages MAY replace these defaults by re-binding interfaces in their own providers.
  - this relies on the `1.200.0` container definition collision policy:
    later provider binding for the same service id/interface id overrides earlier binding deterministically

### Deliverables (MUST)

#### Creates

Logging:
- [ ] `framework/packages/core/foundation/src/Logging/NoopLogger.php`
  - [ ] implements `Psr\Log\LoggerInterface`

Tracing:
- [ ] `framework/packages/core/foundation/src/Observability/Tracing/NoopTracer.php`
  - [ ] implements `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
- [ ] `framework/packages/core/foundation/src/Observability/Tracing/NoopSpan.php`
  - [ ] implements `Coretsia\Contracts\Observability\Tracing\SpanInterface`
- [ ] `framework/packages/core/foundation/src/Observability/Tracing/NoopContextPropagation.php`
  - [ ] implements `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`

Metrics:
- [ ] `framework/packages/core/foundation/src/Observability/Metrics/NoopMeter.php`
  - [ ] implements `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

Errors:
- [ ] `framework/packages/core/foundation/src/Observability/Errors/NoopErrorReporter.php`
  - [ ] implements `Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface`

Profiling:
- [ ] `framework/packages/core/foundation/src/Observability/Profiling/NoopProfiler.php`
  - [ ] implements `Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface`

#### Modifies

- [ ] `framework/packages/core/foundation/composer.json`
  - [ ] add runtime requirement:
    - [ ] `psr/log`
- [ ] `framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php`
  - [ ] binds:
    - [ ] `Psr\Log\LoggerInterface` → `Coretsia\Foundation\Logging\NoopLogger`
    - [ ] `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` → `...NoopTracer`
    - [ ] `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` → `...NoopMeter`
    - [ ] `Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface` → `...NoopErrorReporter`
    - [ ] `Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface` → `...NoopProfiler`
    - [ ] `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface` → `...NoopContextPropagation`
      - [ ] invariant: noop implementation MUST NOT throw; MUST NOT emit stdout/stderr; MUST NOT log raw headers.

- [ ] `framework/packages/core/foundation/README.md`
  - MUST mention: "Foundation provides noop bindings; platform packages override them."

### Tests (MUST)

Contract:
- [ ] `framework/packages/core/foundation/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [ ] MUST cover: NoopLogger/NoopTracer/NoopMeter/NoopErrorReporter/NoopProfiler do not throw
  - [ ] MUST also cover: `NoopContextPropagation` does not throw
  - [ ] MUST also cover: `NoopTracer` returns a noop-safe span (`NoopSpan`) that does not throw on its no-op operations
  - [ ] MUST include one case where `NoopLogger` receives arbitrary PSR-3 context and ignores it safely
  - [ ] MUST assert: no stdout/stderr sinks in these implementations (token-scan or behavioral)
Integration:
- [ ] `framework/packages/core/foundation/tests/Integration/FoundationResolvesNoopObservabilityBindingsTest.php`
  - [ ] asserts container resolves:
    - [ ] `Psr\Log\LoggerInterface`
    - [ ] `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
    - [ ] `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
    - [ ] `Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface`
    - [ ] `Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface`
    - [ ] `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`
  - [ ] without any `platform/*` packages installed

### DoD (MUST)

- [ ] Container can resolve all listed ports without platform/* packages installed
- [ ] Noop implementations never throw
- [ ] No payload/secrets/PII are stored or emitted
- [ ] Deptrac green: no forbidden deps
- [ ] Any runtime that references `ContextPropagationInterface` (e.g. platform/http TraceContextMiddleware) MUST have a resolvable default binding in the baseline (Foundation), otherwise “feature toggles default true” becomes unsafe.

---

### 1.210.0 Foundation: ContextBag + ContextStore + CorrelationId (MUST) [IMPL]

---
type: package
phase: 1
epic_id: "1.210.0"
owner_path: "framework/packages/core/foundation/"

package_id: "core/foundation"
composer: "coretsia/core-foundation"
kind: runtime
module_id: "core.foundation"

goal: "Всі runtime шари читають контекст однаково через `ContextAccessorInterface`, а ContextStore гарантовано очищається між UoW."
provides:
- "Єдиний ContextStore для всього runtime з immutable view (ContextBag) і контрольованою мутацією."
- "Канонічні ContextKeys (SSoT) + contract test на стабільність ключів."
- "CorrelationId generator/provider для гарантованого correlation per UoW."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0015-context-bag-context-store-correlation-id.md"
ssot_refs:
- "docs/ssot/context-store.md"
- "docs/ssot/context-keys.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.90.0 — observability contracts exist (`Coretsia\Contracts\Observability\CorrelationIdProviderInterface`)
  - 1.95.0 — `Coretsia\Contracts\Context\ContextAccessorInterface` exists
  - 1.190.0 — canonical HTTP middleware catalog SSoT exists (`docs/ssot/http-middleware-catalog.md`)
  - 1.200.0 — reset/tag infrastructure exists (`kernel.reset`, ResetOrchestrator, TagRegistry).

- Single-source ULID rule (cemented) (MUST)
  - `Coretsia\Foundation\Id\UlidGenerator` is introduced in this epic as the single canonical ULID implementation.
  - `CorrelationIdGenerator` MUST delegate to `UlidGenerator` and MUST NOT implement ULID logic independently.

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/` — provides `CorrelationIdProviderInterface`, `ResetInterface`.

- Required config roots/keys:
  - `foundation.*` — baseline config root exists.
  - This epic does NOT introduce or require dedicated `foundation.context.*` or `foundation.correlation.*` runtime keys.

- Required tags:
  - effective reset discovery tag = `foundation.reset.tag` (reserved default `kernel.reset`)
    - `ContextStore` is stateful and MUST be tagged with the effective reset discovery tag
  - `kernel.stateful`
    - `ContextStore` MUST be explicitly marked stateful for enforceability rails

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface` — correlation id read port.
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — canonical runtime read port for context access.
  - `Coretsia\Contracts\Runtime\ResetInterface` — reset contract for ContextStore.

- Hard rules (legacy; cemented):
  - ContextStore MUST NOT store secrets or sensitive payload material:
    - tokens (any form), session ids, cookies, Authorization, raw request/response bodies, raw headers.
  - ContextStore MUST NOT store direct user identifiers:
    - email, phone, full name, external account identifiers (unless explicitly introduced later as safe ids).
  - Request metadata keys (e.g. `client_ip`, `user_agent`, `host`, `path`) are allowed ONLY if:
    - the key is declared in `Coretsia\Foundation\Context\ContextKeys`
    - and the value obeys ContextStorePolicy (JSON-safe types, no floats, no objects).
  - For potentially sensitive request metadata (e.g. `client_ip`):
    - writers SHOULD prefer normalization or hashing when feasible (policy guidance),
    - but the key presence itself is still controlled strictly by `ContextKeys`.

- Acceptance scenario (legacy):
  - When one UoW finishes, then `ContextStore::reset()` runs and the next UoW starts with an empty store except new base keys set by kernel.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`

Forbidden:

- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextStore`
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- CLI:
  - N/A

- HTTP:
  - (integration point; writers are in owner packages; owner package is `platform/http`, with initial package support appearing in Phase 1 and broader HTTP implementation expanding later)
  - HTTP layer MAY write only safe keys to ContextStore strictly via `ContextKeys` allowlist + `ContextStorePolicy`.
  - The authoritative mapping “Middleware → ContextKeys written/read” MUST live in:
    - `docs/ssot/middleware-context-keys-map.md` (reference-only map; MUST NOT redefine lists)
  - The canonical middleware catalog / slot ownership reference is:
    - `docs/ssot/http-middleware-catalog.md`
  - `framework/tools/spikes/fixtures/http_middleware_catalog.php` MAY be cited only as a Phase 0 lock/alignment input, NOT as SSoT.

- Kernel hooks/tags:
  - Foundation reset orchestration — ContextStore is tagged with the effective Foundation reset tag (`foundation.reset.tag`, default `kernel.reset`); reset is executed after every UoW.

- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

Context keys + store:
- [ ] `framework/packages/core/foundation/src/Context/ContextKeys.php`
  - [ ] canonical keys (Phase 0 list + reserved future list)
- [ ] `framework/packages/core/foundation/src/Context/ContextBag.php`
  - [ ] immutable snapshot view
- [ ] `framework/packages/core/foundation/src/Context/ContextStore.php`
  - [ ] mutable store
  - [ ] implements `Coretsia\Contracts\Context\ContextAccessorInterface`
  - [ ] implements `Coretsia\Contracts\Runtime\ResetInterface`
  - [ ] MUST implement `ContextAccessorInterface::get(string $key): mixed` exactly
  - [ ] MUST NOT add a default parameter to `get(...)`
- [ ] `framework/packages/core/foundation/src/Context/ContextStorePolicy.php`
  - [ ] safe-write allowlist + guards (no secrets/PII)

### ContextStore value model (single-choice; cemented)

- [ ] `ContextStore` MUST accept only JSON-safe, deterministic value types:
  - [ ] Scalars:
    - [ ] `null`, `bool`, `int`, `string`
  - [ ] Arrays:
    - [ ] lists: `list<value>`
    - [ ] maps: `array<string, value>` (string keys only)
- [ ] Forbidden everywhere (including nested):
  - [ ] `float` (incl. `NaN`, `INF`, `-INF`)
  - [ ] `resource`, `object`, `Closure`
  - [ ] non-string map keys
- [ ] Note:
  - [ ] callable-ness is NOT treated as a standalone ContextStore type rule
  - [ ] plain strings remain valid strings even if PHP could interpret some of them as callable names
- [ ] Deterministic failure semantics (single-choice):
  - [ ] On forbidden value/type, throw deterministic exception (or deterministic equivalent).
  - [ ] Exception message MUST be safe:
    - [ ] MUST NOT include the raw value
    - [ ] MAY include only a stable path-to-value (e.g. `a.b[3].c`)
- [ ] **Context key allowlist (single-choice; cemented):**
  - [ ] `ContextStorePolicy` MUST allow writes ONLY for keys declared in `Coretsia\Foundation\Context\ContextKeys`.
  - [ ] Any attempt to write a key not present in `ContextKeys` MUST fail deterministically.
  - [ ] Rationale: prevents uncontrolled key sprawl; `ContextKeys` remains the only SSoT.

Correlation id:
- [ ] `framework/packages/core/foundation/src/Id/CorrelationIdGenerator.php` — stable-format correlation id generation (format-deterministic; value is entropy-based)
  - [ ] MUST receive `Coretsia\Foundation\Id\UlidGenerator` via constructor injection.
  - [ ] MUST delegate generation to `UlidGenerator` and MUST NOT implement ULID logic independently.
  - [ ] MUST NOT post-process the generated value in a way that can create format drift.
  - [ ] `correlation_id` MUST be an opaque safe id with a deterministic string format.
    - [ ] Canonical format: **ULID** (Crockford Base32), 26 chars:
      - [ ] matches: `/\A[0-9A-HJKMNP-TV-Z]{26}\z/`
    - [ ] Output normalization:
      - [ ] MUST be uppercase (as above) to avoid case-drift across implementations
  - [ ] **Single-source ULID rule (cemented):**
    - [ ] `CorrelationIdGenerator` MUST NOT implement ULID independently.
    - [ ] It MUST delegate ULID generation to `Coretsia\Foundation\Id\UlidGenerator` to prevent format drift.

- [ ] `framework/packages/core/foundation/src/Id/UlidGenerator.php` — canonical ULID generator (single source)
  - [ ] **Single-source ULID rule (cemented):**
    - [ ] this generator is the only ULID implementation in the codebase
    - [ ] `CorrelationIdGenerator` MUST delegate to it

- [ ] `framework/packages/core/foundation/src/Observability/CorrelationIdProvider.php` — implements `CorrelationIdProviderInterface`

Wiring evidence (in provider):
- [ ] `framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php` — binds store/accessor/provider
  and tags `ContextStore` with the effective Foundation reset discovery tag resolved from
  `foundation.reset.tag` (reserved default `kernel.reset`)

Documentation:
- [ ] `docs/adr/ADR-0015-context-bag-context-store-correlation-id.md`
- [ ] `docs/ssot/context-store.md` — single store, immutable bag, safe writes, reset між UoW
- [ ] `docs/ssot/context-keys.md` — canonical key registry only:
  - [ ] defines canonical key names, meanings, safe-value notes, and owner/lifecycle notes
  - [ ] MAY name high-level writer categories only:
    - [ ] kernel
    - [ ] http
    - [ ] routing
    - [ ] auth
    - [ ] tenancy
  - [ ] MUST NOT contain the per-middleware `FQCN -> ContextKeys written/read` matrix
  - [ ] the detailed middleware-to-keys reference map is owned only by `1.230.0` in:
    - [ ] `docs/ssot/middleware-context-keys-map.md`

Tests:
- [ ] `framework/packages/core/foundation/tests/Unit/CorrelationIdGeneratorDelegatesToUlidGeneratorTest.php`
- [ ] `framework/packages/core/foundation/tests/Unit/ContextBagImmutabilityTest.php`
- [ ] `framework/packages/core/foundation/tests/Unit/CorrelationIdFormatTest.php`
- [ ] `framework/packages/core/foundation/tests/Contract/ContextKeysAreStableContractTest.php`
- [ ] `framework/packages/core/foundation/tests/Contract/CorrelationIdFormatContractTest.php`
- [ ] `framework/packages/core/foundation/tests/Contract/ContextAccessorSignatureContractTest.php`
- [ ] `framework/packages/core/foundation/tests/Integration/ContextStoreResetClearsContextTest.php`
- [ ] `framework/packages/core/foundation/tests/Integration/ContextStoreSafeWriteGuardBlocksForbiddenKeysTest.php`
- [ ] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsAtPrefixedKeysTest.php`
  - [ ] writing key `"@foo"` MUST fail deterministically
- [ ] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsFloatValuesTest.php`
  - [ ] rejects nested float
  - [ ] rejects `NaN`, `INF`, `-INF`
  - [ ] error message MUST NOT contain the raw value
  - [ ] message MAY contain only path-to-value
- [ ] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsUnknownKeysTest.php`
  - [ ] writing key `"unknown_key"` MUST fail deterministically
  - [ ] message MUST be safe (no values)
- [ ] `framework/packages/core/foundation/tests/Integration/ContextStoreIsTaggedKernelStatefulTest.php`
- [ ] `framework/packages/core/foundation/tests/Integration/ContextStoreIsTaggedWithEffectiveResetTagTest.php`
  - [ ] asserts provider wiring tags `ContextStore` using `foundation.reset.tag`
- [ ] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsObjectValuesTest.php`
  - [ ] rejects any `object` anywhere (incl. nested)
  - [ ] message MUST be safe (no raw value), MAY include only path-to-value

- [ ] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsResourceValuesTest.php`
  - [ ] rejects any `resource` anywhere
  - [ ] message MUST be safe (no raw value), MAY include only path-to-value

- [ ] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsNonStringMapKeysTest.php`
  - [ ] rejects maps with any non-string key (including `int` keys) anywhere
  - [ ] message MUST be safe (no raw value), MAY include only path-to-value

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/context-store.md`
  - [ ] `docs/ssot/context-keys.md`
- [ ] `framework/packages/core/foundation/README.md` — documents ContextStore usage + redaction rules (if referenced)
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0015-context-bag-context-store-correlation-id.md`

#### Configuration (keys + defaults)

N/A

- [ ] Policy:
  - [ ] Transport-specific correlation header extraction/injection policy is owned by `platform/http`, not by `core/foundation`.
  - [ ] `core/foundation` owns only correlation id generation and provider binding.
  - [ ] `ContextStore` is baseline runtime infrastructure and MUST NOT be feature-disabled via config
  - [ ] correlation id provisioning is baseline runtime infrastructure and MUST NOT be feature-disabled via config
  - [ ] absence of optional writers/readers is represented by “no writes/no reads”, NOT by disabling foundation context services
  - [ ] `ContextStorePolicy` safe-write guard is baseline runtime safety and MUST always be enabled.
  - [ ] It MUST NOT be feature-disabled via config.

#### Wiring / DI tags (when applicable)

- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Foundation\Context\ContextStore` (singleton)
  - [ ] binds: `Coretsia\Contracts\Context\ContextAccessorInterface` → `ContextStore`
  - [ ] registers: `Coretsia\Foundation\Observability\CorrelationIdProvider`
  - [ ] binds: `Coretsia\Contracts\Observability\CorrelationIdProviderInterface` → `CorrelationIdProvider`

  - [ ] adds tag: `<effective reset tag>` priority `<int>` meta `<optional>` for `ContextStore`
  - [ ] adds tag: `kernel.stateful` priority `0` meta `{}` for `ContextStore`

- [ ] Policy (cemented):
  - [ ] `ContextStore` is stateful and therefore MUST be both:
    - [ ] tagged `kernel.stateful`
    - [ ] tagged with the effective Foundation reset discovery tag (`foundation.reset.tag`, reserved default `kernel.reset`)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
  - [ ] request keys when present: `CLIENT_IP|SCHEME|HOST|PATH|USER_AGENT`
  - [ ] reserved future keys: `REQUEST_ID|PATH_TEMPLATE|HTTP_RESPONSE_FORMAT|ACTOR_ID|TENANT_ID`
- [ ] Context writes (safe only):
  - [ ] base keys: `correlation_id`, `uow_id`, `uow_type`
  - [ ] request/app safe keys only (no headers/cookies/body)
- [ ] Reset discipline:
  - [ ] `ContextStore` implements `Coretsia\Contracts\Runtime\ResetInterface`
  - [ ] `ContextStore` is discovered for reset only through the effective Foundation reset discovery tag (`foundation.reset.tag`, default `kernel.reset`)

#### Observability (policy-compliant)

- Spans/Metrics:
  - N/A (foundation minimal)
- [ ] Logs:
  - [ ] MUST NOT include Authorization/Cookie/session id/tokens/payload/raw SQL
  - [ ] `correlation_id` is safe and may be logged (not as metric label)

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException` — errorCode `CORETSIA_CONTEXT_WRITE_FORBIDDEN` (optional)
  - [ ] `Coretsia\Foundation\Context\Exception\ContextInvalidKeyException` — errorCode `CORETSIA_CONTEXT_INVALID_KEY` (optional)
- Mapping note:
  - Foundation context exceptions (if enabled) are mapped/adapted in higher layers
    (e.g., `platform/errors`) — no duplicate mappers here.

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] session ids, tokens, cookies, Authorization headers, request bodies
- [ ] Allowed:
  - [ ] safe ids: `correlation_id`, `uow_id`, `actor_id` (policy: never email/phone)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If Context writes exist → `framework/packages/core/foundation/tests/Integration/ContextStoreSafeWriteGuardBlocksForbiddenKeysTest.php`
- [ ] if effective reset discovery is used → `framework/packages/core/foundation/tests/Integration/ContextStoreResetClearsContextTest.php`
- [ ] If key stability is promised → `framework/packages/core/foundation/tests/Contract/ContextKeysAreStableContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/core/foundation/tests/Unit/ContextBagImmutabilityTest.php`
  - [ ] `framework/packages/core/foundation/tests/Unit/CorrelationIdFormatTest.php`
- Contract:
  - [ ] `framework/packages/core/foundation/tests/Contract/ContextKeysAreStableContractTest.php`
  - [ ] `framework/packages/core/foundation/tests/Contract/CorrelationIdFormatContractTest.php`
  - [ ] `framework/packages/core/foundation/tests/Contract/ContextAccessorSignatureContractTest.php`
- Integration:
  - [ ] `framework/packages/core/foundation/tests/Integration/ContextStoreResetClearsContextTest.php`
  - [ ] `framework/packages/core/foundation/tests/Integration/ContextStoreSafeWriteGuardBlocksForbiddenKeysTest.php`
- Gates/Arch:
  - [ ] phpstan/gates enforce no stateful without reset (referenced; owned elsewhere)

### DoD (MUST)

- [ ] Key list matches SSoT and is contract-tested
- [ ] Reset clears context deterministically
- [ ] No secrets/PII can be written by default (guard or discipline + docs)
- [ ] Docs updated:
  - [ ] `docs/ssot/context-store.md`
  - [ ] `docs/ssot/context-keys.md`
  - [ ] `docs/adr/ADR-0015-context-bag-context-store-correlation-id.md`
- [ ] Kernel lifecycle (Phase 1 runtime integration):
  - [ ] In `1.280.0` KernelRuntime MUST set base keys in ContextStore at beginUoW:
    `correlation_id`, `uow_id`, `uow_type`.
  - [ ] In `1.280.0` KernelRuntime MUST execute reset orchestration after UoW
    via `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`.
- [ ] Typical readers:
  - [ ] `platform/logging` and `platform/tracing` MAY read `Coretsia\Contracts\Context\ContextAccessorInterface`, but any export to `logs/spans/metrics` remains governed by `docs/ssot/observability.md`
  - [ ] raw `path`, raw query, headers, cookies, Authorization, tokens, and payloads MUST NOT be exported even if present in `ContextStore`
  - [ ] use `path_template` or `hash(value)` / `len(value)` when path-like observability data is needed
  - [ ] `platform/tracing` MAY read correlation/uow keys for trace enrichment.
- [ ] Typical writers (HTTP layer; owners are HTTP packages/middlewares):
  - [ ] HTTP middlewares MAY write only safe keys (no headers/cookies/body/payload).
  - [ ] The canonical key list + writers (high-level) are documented in SSoT:
    - [ ] `docs/ssot/context-keys.md`
    - [ ] canonical middleware catalog reference: `docs/ssot/http-middleware-catalog.md`
    - [ ] `framework/tools/spikes/fixtures/http_middleware_catalog.php` MAY be used only as a Phase 0 lock/alignment input, NOT as the primary reference
- [ ] Context keys MUST NOT start with `@`.
- [ ] `ContextStorePolicy` MUST reject any write attempt to a key that starts with `@` deterministically.
- [ ] Rationale: `@*` namespace reserved for config directives (Phase 0 config_merge semantics); runtime context keys must never collide.
- [ ] `ContextStore` MUST reject `float` values anywhere (including nested structures if supported):
  - [ ] reject any `float`
  - [ ] reject `NaN`, `INF`, `-INF` explicitly
- [ ] Failure MUST be deterministic and MUST NOT reveal raw values:
  - [ ] throw `ContextWriteForbiddenException` (or deterministic equivalent)
  - [ ] message MAY include лише шлях до значення (e.g. `a.b[3].c`)
  - [ ] message MUST NOT include the value itself
- [ ] **No unknown ContextKeys (cemented):**
  - [ ] `ContextStore` MUST reject any key not declared in `ContextKeys` deterministically.

---

### 1.220.0 Foundation: Clock + IDs + Stopwatch (MUST) [IMPL]

---
type: package
phase: 1
epic_id: "1.220.0"
owner_path: "framework/packages/core/foundation/"

package_id: "core/foundation"
composer: "coretsia/core-foundation"
kind: runtime
module_id: "core.foundation"

goal: "Всі runtime пакети отримують час/ідентифікатори/тривалості з одного місця через DI, без дублювання і без nondeterministic форматів."
provides:
- "Один Clock (PSR-20) через DI (system clock + frozen clock for tests)."
- "Канонічні генератори ідентифікаторів (ULID default; UUID optional)."
- "Deterministic Stopwatch для durationMs (int ms, non-negative)."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0016-clock-ids-stopwatch.md"
ssot_refs:
- "docs/ssot/time-ids-and-duration.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.20.0 — packaging strategy locked.
  - 1.210.0 — canonical `UlidGenerator` already exists and remains the single ULID source

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/` — baseline ports; no new ports introduced here.

- Required config roots/keys:
  - `foundation.*` — runtime reads `foundation.clock.*` and `foundation.ids.*`.

- Required contracts / ports:
  - `Psr\Clock\ClockInterface` — PSR-20.

- Non-goals (legacy):
  - No new “utility” package (everything lives in foundation).
  - No floats in configs/artifacts (durationMs and sampling policy are ints).
  - Does not define HTTP request_id policy (future epic), only provides generators.
  - Contract rule (MUST):
    - `SystemClock` MUST return `DateTimeImmutable` in UTC.
    - Monotonicity MUST NOT be asserted (system time may jump).

- Acceptance scenario (legacy):
  - When a duration is measured for a metric, then it is always expressed as integer milliseconds and never negative.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`

Forbidden:

- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Clock\ClockInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Clock\SystemClock`
  - `Coretsia\Foundation\Time\Stopwatch`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

Clock:
- [ ] `framework/packages/core/foundation/src/Clock/SystemClock.php` — implements `Psr\Clock\ClockInterface`
- [ ] `framework/packages/core/foundation/src/Clock/FrozenClock.php` — test clock (fixtures)

IDs:
- [ ] `framework/packages/core/foundation/src/Id/UuidGenerator.php` — concrete generator
- [ ] `framework/packages/core/foundation/src/Id/IdGeneratorInterface.php` — canonical Foundation abstraction for runtime id generation

Stopwatch:
- [ ] `framework/packages/core/foundation/src/Time/Stopwatch.php` — float-free stopwatch
  - [ ] `start(): int` returns a monotonic timestamp token in **nanoseconds** from `hrtime(true)`
  - [ ] `stop(int $startedAt): int` returns `durationMs` as **int milliseconds**:
    - [ ] computed as `max(0, intdiv(hrtime(true) - $startedAt, 1_000_000))`
  - [ ] MUST NOT use `microtime(true)` (float)
  - [ ] MUST be non-negative and deterministic-format (int ms)

Documentation:
- [ ] `docs/adr/ADR-0016-clock-ids-stopwatch.md`
- [ ] `docs/ssot/time-ids-and-duration.md` — durationMs=int, ULID default, usage guidance

Tests:
- [ ] `framework/packages/core/foundation/tests/Unit/UlidFormatTest.php`
- [ ] `framework/packages/core/foundation/tests/Unit/StopwatchDurationIsNonNegativeTest.php`
- [ ] `framework/packages/core/foundation/tests/Unit/FrozenClockReturnsDeterministicNowTest.php`
- [ ] `framework/packages/core/foundation/tests/Contract/SystemClockReturnsUtcDateTimeImmutableContractTest.php`
- [ ] `framework/packages/core/foundation/tests/Contract/UuidFormatContractTest.php`
- [ ] `framework/packages/core/foundation/tests/Integration/DefaultIdGeneratorResolvesFromConfigTest.php`
- [ ] `framework/packages/core/foundation/tests/Contract/FoundationConfigRejectsFloatValuesInClockAndIdsContractTest.php`
  - [ ] asserts `framework/packages/core/foundation/config/rules.php` rejects:
    - [ ] any float under `foundation.clock.*` or `foundation.ids.*` (including nested)
    - [ ] explicit `NaN`, `INF`, `-INF` (if representable in fixtures)
  - [ ] failure MUST be deterministic and message MUST be safe (no dumping raw values)

#### Modifies

- [ ] `framework/packages/core/foundation/composer.json`
  - [ ] add runtime requirement:
    - [ ] `psr/clock`
- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/time-ids-and-duration.md`
- [ ] `framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php` — binds Clock/Stopwatch/Id generator via DI (wiring evidence)
- [ ] `framework/packages/core/foundation/src/Provider/FoundationServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/core/foundation/config/foundation.php`
- [ ] `framework/packages/core/foundation/config/rules.php`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0016-clock-ids-stopwatch.md`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/core/foundation/config/foundation.php`
- [ ] Keys (dot):
  - [ ] `foundation.clock.driver` = "system"
  - [ ] `foundation.ids.default` = "ulid"
- [ ] Rules:
  - [ ] `framework/packages/core/foundation/config/rules.php` MUST also enforce allowed values:
    - [ ] `foundation.clock.driver` ∈ {`system`}
    - [ ] `foundation.ids.default` ∈ {`ulid`, `uuid`}

- [ ] Policy:
  - [ ] Supported ID generators are a code-level capability, not runtime feature flags.
  - [ ] Runtime selection is done only through `foundation.ids.default`.
  - [ ] `foundation.ids.default` selects only `Coretsia\Foundation\Id\IdGeneratorInterface`.
  - [ ] It MUST NOT affect `Coretsia\Foundation\Id\CorrelationIdGenerator` or
    `Coretsia\Foundation\Observability\CorrelationIdProvider`;
    `correlation_id` remains ULID-backed per `1.210.0`.
  - [ ] `Stopwatch` is canonical Foundation runtime infrastructure and MUST be resolvable whenever `core/foundation` is enabled
  - [ ] duration measurement absence in a consumer is represented by “consumer does not call Stopwatch”, NOT by disabling Stopwatch itself

#### Wiring / DI tags (when applicable)

- [ ] ServiceProvider wiring evidence:
  - [ ] binds: `Psr\Clock\ClockInterface` → `Coretsia\Foundation\Clock\SystemClock`
  - [ ] registers: `Coretsia\Foundation\Time\Stopwatch`
  - [ ] binds: `Coretsia\Foundation\Id\IdGeneratorInterface` → resolved default generator selected by `foundation.ids.default`
    - [ ] `ulid` → `Coretsia\Foundation\Id\UlidGenerator`
    - [ ] `uuid` → `Coretsia\Foundation\Id\UuidGenerator`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads/writes/Reset discipline:
  - N/A

#### Observability (policy-compliant)

- Spans:
  - N/A
- [ ] Metrics:
  - [ ] duration values used only as values (not labels); `_duration_ms` naming
- [ ] Logs:
  - [ ] ids may appear in logs/spans, not metric labels
- HARD RULE:
  - No ids (`correlation_id`, `uow_id`, `request_id`, ULID/UUID) are allowed as metric labels.
  - Durations are values only; use `_duration_ms` naming and integer milliseconds.

#### Errors

- [ ] Exceptions introduced:
  - [ ] `Coretsia\Foundation\Time\Exception\StopwatchInvalidStateException` — errorCode `CORETSIA_STOPWATCH_INVALID_STATE` (optional)
  - [ ] `Coretsia\Foundation\Id\Exception\IdGenerationFailedException` — errorCode `CORETSIA_ID_GENERATION_FAILED` (optional)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] secrets in id generation (never derive ids from secret values)
- [ ] Allowed:
  - [ ] ULID/UUID as safe ids; not as metric labels

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] If non-negative duration promised → `framework/packages/core/foundation/tests/Unit/StopwatchDurationIsNonNegativeTest.php`
- [ ] If clock determinism in tests needed → `framework/packages/core/foundation/tests/Unit/FrozenClockReturnsDeterministicNowTest.php`
- [ ] If UUID generator is supported → `framework/packages/core/foundation/tests/Contract/UuidFormatContractTest.php`
- [ ] If float-free `foundation.clock.*` / `foundation.ids.*` config is promised → `framework/packages/core/foundation/tests/Contract/FoundationConfigRejectsFloatValuesInClockAndIdsContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/core/foundation/tests/Unit/UlidFormatTest.php`
  - [ ] `framework/packages/core/foundation/tests/Unit/StopwatchDurationIsNonNegativeTest.php`
  - [ ] `framework/packages/core/foundation/tests/Unit/FrozenClockReturnsDeterministicNowTest.php`
- Contract:
  - [ ] `framework/packages/core/foundation/tests/Contract/SystemClockReturnsUtcDateTimeImmutableContractTest.php`
  - [ ] `framework/packages/core/foundation/tests/Contract/UuidFormatContractTest.php`
  - [ ] `framework/packages/core/foundation/tests/Contract/FoundationConfigRejectsFloatValuesInClockAndIdsContractTest.php`
- Integration:
  - [ ] `framework/packages/core/foundation/tests/Integration/DefaultIdGeneratorResolvesFromConfigTest.php`
    - [ ] asserts `IdGeneratorInterface` resolves to `UlidGenerator` when `foundation.ids.default=ulid`
    - [ ] asserts `IdGeneratorInterface` resolves to `UuidGenerator` when `foundation.ids.default=uuid`
    - [ ] MUST NOT assert or imply that `CorrelationIdProviderInterface` switches to UUID when `foundation.ids.default=uuid`
- Gates/Arch:
  - N/A

### DoD (MUST)

- [ ] One canonical clock via DI (PSR-20)
- [ ] One canonical id default (ULID) without duplicates
- [ ] Stopwatch returns int ms, deterministic and non-negative
- [ ] Docs updated:
  - [ ] `docs/ssot/time-ids-and-duration.md`
  - [ ] `docs/adr/ADR-0016-clock-ids-stopwatch.md`
- [ ] Kernel uses:
  - [ ] Clock + Stopwatch for UoW timings (`*_duration_ms`) and deterministic timing measurements.
  - [ ] ID generators for `uow_id` and (where applicable) other safe ids.
- [ ] `platform/http` may use:
  - [ ] Stopwatch for middleware timings.
  - [ ] ID generators for `request_id` (when request-id policy is enabled by the HTTP epic).
- [ ] `platform/cli` may use:
  - [ ] Clock for deterministic timestamps where required by tooling/outputs.
- [ ] Runtime time/ids APIs MUST be float-free:
  - [ ] any numeric config values introduced by this epic under `foundation.clock.*` and `foundation.ids.*` MUST be `int` (never float)
- [ ] `framework/packages/core/foundation/config/rules.php` MUST enforce:
  - [ ] reject any float values under `foundation.clock.*` and `foundation.ids.*` (where numeric keys exist)
- [ ] IDs MUST be deterministic-format strings:
  - [ ] ULID/UUID string formats are validated by contract tests
- [ ] Stopwatch duration MUST be:
  - [ ] `int`
  - [ ] `>= 0`
  - [ ] stable across OS (no locale/timezone formatting inside core logic)

---

### 1.230.0 ContextStore lifecycle usage (SSoT) (MUST) [DOC]

---
type: docs
phase: 1
epic_id: "1.230.0"
owner_path: "docs/ssot/context-lifecycle.md"

goal: "В будь-якому runtime (HTTP/CLI/worker) контекст формується однаково і ніколи не протікає між UoW."
provides:
- "Єдина lifecycle модель: 1 UoW = 1 контекст; після UoW контекст завжди очищається."
- "Хто/коли встановлює base keys (kernel) та хто додає request/app safe keys."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/context-lifecycle.md"
- "docs/ssot/middleware-context-keys-map.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.190.0 — canonical HTTP middleware catalog SSoT exists (`docs/ssot/http-middleware-catalog.md`)
  - 1.210.0 — ContextKeys/ContextStore exist and are canonical.

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists and this epic appends its registrations here
  - `docs/ssot/context-keys.md` — canonical key list (referenced by this doc).
  - `docs/ssot/context-store.md` — single store + safe writes policy (referenced by this doc).

- Required tags:
  - effective Foundation reset discovery tag (`foundation.reset.tag`, default `kernel.reset`) — reset discipline mechanism described here.

- Required contracts / ports:
  - `Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface` — kernel lifecycle hook surface (conceptual).
  - `Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface` — kernel lifecycle hook surface (conceptual).
  - `Coretsia\Contracts\Runtime\ResetInterface` — reset contract for stateful services.

- Non-goals (legacy):
  - Doc-only: MUST NOT duplicate any Kernel runtime implementation details (owned by `core/kernel`).
  - Doc-only: MUST NOT duplicate any HTTP stack implementation details (owned by future `platform/http` epics).
  - No new ContextKeys outside `ContextKeys` (HARD RULE).

- Acceptance scenario (legacy):
  - When a request finishes, then Foundation reset orchestration runs via
    `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator` using the effective reset discovery tag
    resolved from `foundation.reset.tag` (reserved default `kernel.reset`),
    and the next request does not see any previous ContextStore values.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- N/A (doc-only)

Forbidden:

- N/A (doc-only)

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface`
  - `Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface`
- Foundation stable APIs (conceptual):
  - `Coretsia\Foundation\Context\ContextStore`
  - `Coretsia\Foundation\Context\ContextKeys`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - documents which middleware writes which keys (by owner + slot), per catalog
- Kernel hooks/tags:
  - consumer MUST NOT enumerate reset tags directly (always after UoW)
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `docs/ssot/context-lifecycle.md` — includes:
  - [ ] beginUoW writes: `correlation_id`, `uow_id`, `uow_type`
  - [ ] HTTP enrichment writes (if enabled): `client_ip`, `scheme`, `host`, `path`, `user_agent`, `request_id`, `http_response_format`
  - [ ] routing writes (Phase 1): `path_template`
  - [ ] auth writes (Phase 2): `actor_id` (safe id)
  - [ ] tenancy writes (Phase 6+): `tenant_id` (safe id)
  - [ ] hard bans: session id / tokens / cookies / Authorization / payloads
  - [ ] Presence of a key in ContextStore MUST NOT be interpreted as permission to export it into observability.
  - [ ] In particular, raw `path` MAY exist as in-process context, but MUST NOT be emitted to logs/spans/metrics; observability export remains governed by `docs/ssot/observability.md`.
  - [ ] `docs/ssot/context-lifecycle.md` MUST include an “Examples” section (single-choice):
    - [ ] Example A (valid): HTTP request UoW
      - [ ] beginUoW writes base keys (`correlation_id`, `uow_id`, `uow_type`)
      - [ ] middleware enriches ONLY safe keys
      - [ ] afterUoW triggers Foundation reset orchestration via `ResetOrchestrator` using the effective reset discovery tag (reserved default name: `kernel.reset`), and ContextStore becomes empty for the next UoW
    - [ ] Example B (valid): CLI command UoW
      - [ ] same base keys
      - [ ] no HTTP-only keys written
      - [ ] reset still runs after UoW
    - [ ] Example C (invalid): Context leak across UoW
      - [ ] previous request key visible in the next request → MUST be described as policy violation
    - [ ] Example D (invalid): secret/PII in ContextStore
      - [ ] Authorization/cookies/session id/payload written → MUST be described as hard-ban violation
  - [ ] `docs/ssot/context-lifecycle.md` MUST reference the canonical HTTP middleware slot taxonomy already owned by the HTTP/tags SSoT.
  - [ ] This document MAY repeat slot names only for lifecycle examples and MUST NOT redefine ownership, ordering, or slot semantics.
    - [ ] slots (cemented, tag names; reserved for TagRegistry usage):
      - [ ] `http.middleware.system_pre`
      - [ ] `http.middleware.system`
      - [ ] `http.middleware.system_post`
      - [ ] `http.middleware.app_pre`
      - [ ] `http.middleware.app`
      - [ ] `http.middleware.app_post`
      - [ ] `http.middleware.route_pre`
      - [ ] `http.middleware.route`
      - [ ] `http.middleware.route_post`
    - [ ] **Config namespace (NOT a tag):** `http.middleware.auto.*` is a configuration-key namespace under the `http.*` config subtree.
      - [ ] `http.middleware.auto` / `http.middleware.auto.*` MUST NOT be used as TagRegistry tag names.
      - [ ] Rationale: avoid collisions/confusion with the `http.middleware.*` tag namespace.

  ## Reset execution (mechanism)

  **Important:** Foundation reset uses the effective reset discovery tag configured by `foundation.reset.tag`.
  The reserved default value is `kernel.reset`.
  In this document, `kernel.reset` is used only as the default-name shorthand.

  ### Who triggers reset?

  Reset is triggered by **Kernel runtime** exactly once per Unit of Work (UoW):

  1) Kernel runs `AfterUoW` hooks (`kernel.hook.after_uow`).
  2) Kernel calls the **single** executor:
  - `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`

  Kernel MUST NOT iterate `kernel.reset` tagged services by itself.

  ### What does ResetOrchestrator do?

  `ResetOrchestrator`:
  - discovers resettable services via `TagRegistry` using the effective Foundation reset discovery tag (`foundation.reset.tag`, reserved default `kernel.reset`)
  - resolves them via the PSR-11 container by service id
  - calls `ResetInterface::reset()` on each service
  - execution ordering is deterministic (single-choice):
    - if enhanced reset is **disabled** → executes in EXACT `TagRegistry->all($effectiveResetTag)` order
      (`priority DESC, id ASC`)
    - if enhanced reset is **enabled** (1.250.0) → executes in planned order:
      `priority DESC, group ASC, serviceId ASC`
  - failure semantics are deterministic:
    - misuse (service not implementing `ResetInterface`) MUST hard-fail safely
    - messages MUST be safe (no payloads/paths/secrets)

  - Reset MUST still run if an `after-uow` hook throws.
  - The failure is surfaced only after the reset attempt completes.

  ### What does it reset (examples)?

  Typical services discovered through the effective Foundation reset discovery tag (reserved default `kernel.reset`):
  - `ContextStore` (clears context between UoW)
  - long-running buffers/queues (e.g., deferred dispatch queues)
  - observability batch processors/buffers (only if enabled and present)

  ### What must never happen

  - a UoW starts and can observe previous UoW ContextStore values
  - a stateful service keeps per-request payload/headers/tokens in memory after reset
  - reset execution prints/logs payloads, headers, cookies, Authorization, or secrets

- [ ] `docs/ssot/middleware-context-keys-map.md` — table: middleware FQCN → ContextKeys written/read (reference-only)
  - [ ] MUST explicitly state its SSoT linkage:
    - [ ] the canonical middleware list/slot ownership/order reference is:
      - [ ] `docs/ssot/http-middleware-catalog.md`
    - [ ] `framework/tools/spikes/fixtures/http_middleware_catalog.php` MAY be cited only as a Phase 0 lock/alignment input, NOT as SSoT
    - [ ] the table MUST NOT re-declare middleware lists; it is a reference map only:
      - [ ] `Middleware FQCN → ContextKeys written/read`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/context-lifecycle.md`
  - [ ] `docs/ssot/middleware-context-keys-map.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads:
  - N/A (doc defines lifecycle; runtime reads happen elsewhere)
- [ ] Context writes (safe only):
  - [ ] base UoW keys (kernel)
  - [ ] request/app safe keys (middlewares/packages)
- [ ] Reset discipline:
  - [ ] always via `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`
    using the effective reset discovery tag resolved from `foundation.reset.tag`
    (reserved default `kernel.reset`)

#### Observability (policy-compliant)

- [ ] Logs:
  - [ ] explicitly bans secrets/PII in ContextStore; only safe ids + hash/len guidance

#### Errors

N/A (doc-only)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] session ids/tokens/cookies/Authorization/payloads
- [ ] Allowed:
  - [ ] safe ids + `hash(value)` / `len(value)` outside metric labels
- [ ] Hard bans (single-choice; enforceable) — ContextStore MUST NOT contain:
  - [ ] `Authorization`, cookies, session ids, tokens (any form),
  - [ ] raw request/response payloads,
  - [ ] raw headers (except allowlisted safe ones like `User-Agent` as a safe string, if policy allows),
  - [ ] anything that can identify a user beyond a safe id (Phase 2+ owns those ids).
- [ ] Allowed values are only:
  - [ ] safe ids (opaque correlation/uow ids),
  - [ ] normalized request metadata (scheme/host/path) WITHOUT query string (unless hashed),
  - [ ] `hash(value)` / `len(value)` when needed, never raw secret.

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] N/A (doc-only); MUST reference enforcement rails:
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelRuntimeAlwaysResetsAfterUowTest.php`
  - [ ] `framework/tools/gates/cross_cutting_contract_gate.php`

### Tests (MUST)

- Unit/Contract/Integration:
  - N/A
- Gates/Arch:
  - [ ] referenced by enforcement rails (see Verification)

### DoD (MUST)

- [ ] Docs exist and match `ContextKeys` SSoT + middleware catalog
- [ ] No contradictory rules vs kernel/http epics
- [ ] Enforcement rails reference (MUST):
  - [ ] Kernel reset invariant MUST be enforced by an integration test (example path):
    - [ ] `framework/packages/core/kernel/tests/Integration/KernelRuntimeAlwaysResetsAfterUowTest.php`
  - [ ] Cross-cutting gate MUST validate “no forbidden ContextKeys writes” (example gate path):
    - [ ] `framework/tools/gates/cross_cutting_contract_gate.php`
  - [ ] The doc MUST explicitly describe what the gate/test checks (single-choice):
    - [ ] afterUoW → `ResetOrchestrator::resetAll()` runs → ContextStore is empty for the next UoW.

---

### 1.240.0 Stateful services policy (SSoT) (MUST) [DOC]

---
type: docs
phase: 1
epic_id: "1.240.0"
owner_path: "docs/ssot/stateful-services.md"

goal: "Неможливо замержити stateful сервіс без reset дисципліни, і жоден stateful сервіс не протікає між UoW."
provides:
- "Rule: stateless by default; if state is introduced → implement `ResetInterface` and tag the service with the effective Foundation reset discovery tag (`foundation.reset.tag`, default `kernel.reset`)."
- "`kernel.stateful` remains the fixed enforcement marker."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/stateful-services.md"
- "docs/ssot/reset-tags.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.200.0 — `kernel.reset` / `kernel.stateful` constants + reset orchestration exist.

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists and this epic appends its registrations here
  - `framework/packages/core/contracts/src/Runtime/ResetInterface.php` (by package contract) — stateful reset contract.

- Required tags:
  - effective reset discovery tag = `foundation.reset.tag` (default `kernel.reset`) — mandatory for stateful services
  - `kernel.stateful` — fixed marker for enforcement

- Enforcement rails (MUST):
  - Any stateful service MUST be explicitly tagged `kernel.stateful` (mandatory marker; cemented).
  - Tooling/static analysis MUST fail CI if a service is tagged `kernel.stateful`
    but does NOT implement `Coretsia\Contracts\Runtime\ResetInterface`.
  - CI MUST fail if a service tagged `kernel.stateful` is not also discoverable through the effective Foundation reset discovery tag.
  - This check MAY be implemented by integration/wiring tests or compile-time gates against resolved Foundation config.
  - Static analysis alone MUST NOT be the only required mechanism for this rule, because the effective reset tag is config-resolved.
  - Kernel MUST run reset after every UoW; long-running runtimes rely on this invariant.

- Non-goals (legacy):
  - Does not define which services are stateful in platform packages (owned by those epics).
  - Does not duplicate kernel/foundation reset implementation.

- Acceptance scenario (legacy):
  - When a service caches identity/event queue in-memory, then it must implement
    `Coretsia\Contracts\Runtime\ResetInterface`, be tagged with `kernel.stateful`,
    and also be tagged with the effective Foundation reset discovery tag resolved from
    `foundation.reset.tag` (reserved default `kernel.reset`);
    it is then cleared after each UoW.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- N/A (doc-only)

Forbidden:

- N/A (doc-only)

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Runtime\ResetInterface`
- Foundation stable APIs (conceptual):
  - `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`
  - `Coretsia\Foundation\Provider\Tags::KERNEL_RESET`
  - `Coretsia\Foundation\Provider\Tags::KERNEL_STATEFUL`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - N/A
- Kernel hooks/tags:
  - consumer MUST NOT enumerate reset tags directly (mandatory for stateful)
  - `kernel.stateful` (marker for enforcement)
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [ ] `docs/ssot/stateful-services.md` — rules + examples:
  - [ ] effective reset tag = `foundation.reset.tag`, default=`kernel.reset`
  - [ ] `kernel.stateful ⇒ implements ResetInterface && tagged with <effective reset tag>`
  - [ ] examples of stateful: identity store, deferred queues, caches with per-UoW state
  - [ ] examples of forbidden state: static globals, request singletons without reset
  - [ ] MUST include:
    - [ ] “Definition” section (single-choice):
      - [ ] *stateless by default* means: no per-UoW memory, no retained request data, no implicit caches.
      - [ ] *stateful* means: service retains mutable in-memory state across calls within the same process.
    - [ ] “Examples” section with 3–5 minimal examples (copy-pastable):
      - [ ] valid: in-memory queue that is cleared on reset (`ResetInterface` + effective reset tag; default example reset discovery tag is Foundation-owned and opaque to the consumer)
      - [ ] valid: memoized cache that is cleared on reset
      - [ ] invalid: static global cache (`static $x`) persisting across UoW
      - [ ] invalid: request singleton without reset discipline
      - [ ] invalid: stateful service tagged `kernel.stateful` but missing `ResetInterface`

- [ ] `docs/ssot/reset-tags.md` — reset semantics and usage rules for already-canonical tags
  - [ ] MUST NOT redefine tag ownership or registry rows from `docs/ssot/tags.md`.
  - [ ] Owns only reset-specific semantics:
    - [ ] effective reset discovery tag
    - [ ] reserved default name
    - [ ] `kernel.stateful` invariant
    - [ ] redaction/safety rules for reset-related diagnostics
  - [ ] effective reset tag = `foundation.reset.tag`, default=`kernel.reset`
  - [ ] kernel.stateful => implements ResetInterface && tagged with the effective reset discovery tag (`foundation.reset.tag`, default `kernel.reset`)

# Reset discipline: effective reset discovery tag + `kernel.stateful`

This document is Single Source of Truth (SSoT) for reset discipline in long-running runtimes.

## Terminology

- **UoW (Unit of Work)**: one logical runtime cycle (HTTP request, CLI command, queue job, worker task).
- **Reset discipline**: invariant that **no mutable state leaks across UoW boundaries** in the same PHP process.

## Effective reset discovery tag

- Foundation reset discovery is controlled by:
  - `foundation.reset.tag`
- The reserved default value is:
  - `kernel.reset`
- Therefore `kernel.reset` is the canonical default tag name, not a separately owned kernel lifecycle feature.

### Required contract

Any service discovered through the effective reset discovery tag MUST implement:

- `Coretsia\Contracts\Runtime\ResetInterface`

### `kernel.stateful` rule

If a service is tagged `kernel.stateful`, then it MUST:

- implement `Coretsia\Contracts\Runtime\ResetInterface`
- also be tagged with the effective Foundation reset discovery tag (`foundation.reset.tag`, reserved default `kernel.reset`)

Kernel does not use `kernel.stateful` at runtime.

## Security / redaction rules

Reset-related logs/diagnostics MUST NOT include:

- Authorization headers, cookies, tokens, session ids
- request/response payloads
- absolute paths
- raw endpoints (socket paths, host:port), unless hashed

Allowed:
- stable counts (`services_count`)
- outcomes (`ok|failed`)
- normalized group ids (if present)

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/stateful-services.md`
  - [ ] `docs/ssot/reset-tags.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] secrets in memory beyond UoW (if unavoidable, must be explicitly documented and wiped on reset)
- [ ] Allowed:
  - [ ] safe ids + hash/len patterns

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] N/A (doc-only), but MUST reference enforcement rails:
  - [ ] `framework/tools/gates/cross_cutting_contract_gate.php`
  - [ ] phpstan rule: “stateful/tagged services MUST implement ResetInterface” (referenced; owned elsewhere)

### Tests (MUST)

- Unit/Contract/Integration:
  - N/A
- Gates/Arch:
  - [ ] enforced via gates/phpstan (referenced)

### DoD (MUST)

- [ ] Docs exist and are referenced by runtime packages READMEs
- [ ] CI rails exist/are referenced for enforcement (gate/phpstan)
- [ ] A stateful service MUST NOT retain secrets/PII beyond a UoW.
  - [ ] If a secret must exist transiently, it MUST be wiped during reset and MUST be documented explicitly.
- [ ] Reset MUST be deterministic.
- [ ] Stateful services SHOULD make repeated reset calls on already-clean state safe/idempotent where feasible.
- [ ] This MUST NOT be interpreted as “reset can never throw”:
  - [ ] orchestrator failure semantics remain fail-fast on service exception, as owned by `1.250.0`
- [ ] Error reporting MUST be safe:
  - [ ] MUST NOT include absolute paths, payloads, tokens, headers in exception messages.
- [ ] Enforcement rails (MUST reference):
  - [ ] CI MUST fail if a service is tagged `kernel.stateful` but does not implement `Coretsia\Contracts\Runtime\ResetInterface`.
  - [ ] CI MUST fail if a stateful service is missing discovery through the effective Foundation reset tag.
    - [ ] enforcement may be via integration/wiring tests or compile-time gates against resolved config
  - [ ] Reference enforcement mechanisms (example):
    - `framework/tools/gates/cross_cutting_contract_gate.php`
    - phpstan rule: “kernel.stateful ⇒ implements ResetInterface” (owned elsewhere; referenced here)

---

### 1.250.0 Enhanced Reset Mechanism for Long-Running Services (MUST) [IMPL]

---
type: package
phase: 1
epic_id: "1.250.0"
owner_path: "framework/packages/core/foundation/"

package_id: "core/foundation"
composer: "coretsia/core-foundation"
kind: runtime
module_id: "core.foundation"

goal: "Reset відбувається детерміновано: higher priority reset first; спостережувано і без витоків."
provides:
- "Deterministic reset ordering з пріоритетами/групами для long-running середовищ."
- "Observability reset процесу (spans/metrics/logs) без PII."
- "Backward compatibility: без meta порядок ідентичний попередньому deterministic reset."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0019-enhanced-reset-long-running.md"
ssot_refs:
- "docs/ssot/reset-tags.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.200.0 — базовий `ResetOrchestrator` + `kernel.reset` дисципліна існують.
  - 1.205.0 — noop-safe observability/logger baseline bindings exist in Foundation.
  - 1.240.0 — reset discipline SSoT exists (`docs/ssot/reset-tags.md`)
  - Observability ports (noop-safe) існують у `core/contracts` (якщо вмикаються тут).

- Required config roots/keys:
  - `foundation.reset.*` — reset конфіг root існує (див. keys нижче).

- Required tags:
  - effective Foundation reset discovery tag (`foundation.reset.tag`, default `kernel.reset`) — tag meta semantics extended by this epic

- Required contracts / ports:
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Psr\Log\LoggerInterface` (optional; noop-safe)
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional; noop-safe)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional; noop-safe)

- Non-goals (MUST):
  - This epic MUST NOT change `ResetInterface`.
  - This epic MUST NOT introduce new metric label keys (тільки SSoT allowlist).
  - This epic MUST NOT add per-service verbose logs (summary-only).

- Safety / determinism constraints (single-choice; cemented):
  - Sorting MUST be locale-independent:
    - MUST NOT rely on `setlocale`, `LC_ALL`, collation.
    - MUST use byte-order comparisons (`strcmp`) for strings.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`

Forbidden:
- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

- Kernel lifecycle:
  - reset is triggered by kernel runtime for long-running loops (exact trigger remains owned by 1.200.0).

### Deliverables (MUST)

#### Creates

- [ ] `docs/adr/ADR-0019-enhanced-reset-long-running.md`

Implementation:
- [ ] `framework/packages/core/foundation/src/Runtime/Reset/PriorityResetOrchestrator.php`
  - [ ] Deterministic ordering algorithm (single-choice; cemented):
    - [ ] Collect resettable services from the effective Foundation reset discovery list in `TagRegistry` (`foundation.reset.tag`, default `kernel.reset`)
    - [ ] If `foundation.reset.priority.enabled=false`:
      - [ ] MUST preserve legacy deterministic order exactly (no re-sorting).
    - [ ] If `foundation.reset.priority.enabled=true`:
      - [ ] For each service resolve `priority` and `group` deterministically (see Tag meta parsing).
      - [ ] Sort keys (single-choice):
        1) `priority` DESC (higher first)
        2) `group` ASC by `strcmp` on normalized group id
        3) `serviceId` ASC by `strcmp` (container service id string)
      - [ ] Sorting MUST be stable and deterministic across OS/PHP builds.
  - [ ] Execution semantics (single-choice):
    - [ ] Reset is performed sequentially in sorted order (no parallelism).
    - [ ] On first thrown exception from a resettable service:
      - [ ] MUST stop processing (fail-fast) and surface deterministic failure semantics (see Errors).
      - [ ] MUST emit observability summary with outcome=failed (noop-safe ports allowed).

- [ ] `framework/packages/core/foundation/src/Runtime/Reset/ResetGroup.php`
  - [ ] Value object for normalized group id.

- [ ] `framework/packages/core/foundation/src/Runtime/Reset/ResetPriority.php`
  - [ ] Value object for validated priority int.

Errors (deterministic, code-first):
- [ ] `framework/packages/core/foundation/src/Runtime/Reset/ResetErrorCodes.php`
  - [ ] MUST define string codes (cemented):
    - [ ] `CORETSIA_RESET_META_INVALID`
    - [ ] `CORETSIA_RESET_SERVICE_NOT_RESETTABLE`
    - [ ] `CORETSIA_RESET_SERVICE_FAILED`
    - [ ] `CORETSIA_RESET_OBSERVABILITY_FAILED` (only for internal/noop-port failures; MUST remain safe)

- [ ] `framework/packages/core/foundation/src/Runtime/Reset/ResetException.php`
  - [ ] MUST carry deterministic string code from `ResetErrorCodes`:
    - [ ] `code(): string`

Tests:
- [ ] `framework/packages/core/foundation/tests/Integration/PriorityResetOrderDeterministicTest.php`
- [ ] `framework/packages/core/foundation/tests/Integration/ResetGroupWorksTest.php`
- [ ] `framework/packages/core/foundation/tests/Integration/PriorityResetBackCompatWhenDisabledTest.php`
- [ ] `framework/packages/core/foundation/tests/Integration/PriorityResetMetaParsingRejectsInvalidTest.php`
- [ ] `framework/packages/core/foundation/tests/Integration/ResetOrderingIsLocaleIndependentTest.php`
  - [ ] MUST set a non-trivial locale (if available) and still assert identical ordering.
- [ ] `framework/packages/core/foundation/tests/Integration/PriorityResetIgnoresMetaWhenDisabledTest.php`
  - [ ] with `foundation.reset.priority.enabled=false`, invalid meta MUST NOT fail
  - [ ] ordering MUST equal legacy (`TagRegistry->all(...)` order) exactly
- [ ] `framework/packages/core/foundation/tests/Integration/PriorityResetIgnoresUnknownMetaKeysWhenEnabledTest.php`
  - [ ] meta contains extra keys (e.g. `{"priority": 10, "group": "default", "x": "y", "debug": ["a"=>1]}`)
  - [ ] MUST NOT fail because of unknown keys
  - [ ] ordering MUST be computed only from (`priority`,`group`,`serviceId`)
- [ ] `framework/packages/core/foundation/tests/Integration/PriorityResetUsesConfiguredResetTagTest.php`
- [ ] `framework/packages/core/foundation/tests/Integration/PriorityResetFailsFastOnFirstServiceExceptionTest.php`
  - [ ] asserts first thrown service exception stops further reset processing
  - [ ] asserts deterministic `ResetException(code=CORETSIA_RESET_SERVICE_FAILED, message="reset-service-failed")`
  - [ ] asserts summary-only observability path remains safe

#### Modifies

- [ ] `docs/ssot/reset-tags.md` — extend reset SSoT with enhanced reset semantics:
  - [ ] `foundation.reset.priority.enabled`
  - [ ] `foundation.reset.group.default`
  - [ ] supported meta keys: `priority`, `group`
  - [ ] disabled-mode behavior = exact legacy order, no meta parsing
  - [ ] enabled-mode behavior = deterministic planned order
  - [ ] deterministic reset error codes introduced by this epic
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0019-enhanced-reset-long-running.md`
- [ ] `framework/packages/core/foundation/src/Runtime/Reset/ResetOrchestrator.php`
  - [ ] MUST remain the stable public entrypoint used by `core/kernel` (no kernel changes).
  - [ ] **No feature-disable switch (cemented; inherited from 1.200.0):**
    - [ ] reset discovery and reset orchestration are baseline runtime safety mechanisms and MUST NOT be disabled via config
    - [ ] `ResetOrchestrator::resetAll()` MAY be a deterministic noop only when the effective discovery list is empty
    - [ ] `foundation.reset.priority.enabled` controls only enhanced ordering/meta planning behavior and MUST NOT disable baseline reset orchestration itself
  - [ ] MUST delegate deterministically (single-choice):
    - [ ] if `foundation.reset.priority.enabled=false`:
      - [ ] execute legacy mode: iterate EXACT `TagRegistry->all(<effective reset tag>)` order, where the effective tag comes from Foundation wiring/config (`foundation.reset.tag`, default `kernel.reset`)
      - [ ] ignore meta completely (no parsing/validation)
    - [ ] if `foundation.reset.priority.enabled=true`:
      - [ ] delegate planning/execution to `PriorityResetOrchestrator`
  - [ ] MUST reject tag misuse deterministically:
    - [ ] if a service discovered through the effective Foundation reset discovery tag
        resolved from `foundation.reset.tag` (reserved default `kernel.reset`)
        resolves to an instance that does NOT implement `ResetInterface`
      → MUST throw `ResetException(code=CORETSIA_RESET_SERVICE_NOT_RESETTABLE, message="reset-not-resettable")`

- [ ] `framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php`
  - [ ] registers/binds `Coretsia\Foundation\Runtime\Reset\PriorityResetOrchestrator`
  - [ ] keeps `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator` as the stable public entrypoint and injects enhanced reset collaborators deterministically

- [ ] `framework/packages/core/foundation/src/Provider/FoundationServiceFactory.php`
  - [ ] deterministic factory wiring for `PriorityResetOrchestrator` and its reset-planning collaborators
  - [ ] MUST NOT keep mutable runtime state

- [ ] `framework/packages/core/foundation/config/foundation.php`
  - [ ] MUST follow canonical config policy:
    - [ ] file returns the subtree (MUST NOT repeat the root key `foundation`).

- [ ] `framework/packages/core/foundation/config/rules.php`
  - [ ] MUST enforce shape and defaults for keys below.
  - `foundation.reset.group.default` має проходити той самий regex, що й `group meta` (інакше буде “конфіг валідний, але runtime падає”)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/core/foundation/config/foundation.php`

- [ ] Keys (dot):
  - **Base reset key (from 1.200.0; reiterated for completeness):**
    - [ ] `foundation.reset.tag` = "kernel.reset"
  - **Enhanced reset keys (introduced/owned by 1.250.0; single-choice; cemented):**
    - [ ] `foundation.reset.priority.enabled` = true
    - [ ] `foundation.reset.group.default` = "default"
      - MUST match `/\A[a-z0-9][a-z0-9._-]*\z/`

- [ ] Tag meta parsing rules (single-choice; cemented):
  - If `foundation.reset.priority.enabled=false`:
    - reset execution MUST preserve legacy order exactly
    - orchestrator MUST NOT parse or validate reset meta at all

  - If `foundation.reset.priority.enabled=true`:
    - reset planning MUST treat `TaggedService.priority` as the BASE priority
    - supported meta keys are:
      - `priority` (optional override)
      - `group` (optional normalized group id)
    - `priority`:
      - accepted: `int` OR `string` matching `/\A-?\d+\z/`
      - normalized: cast to int
      - if absent: use `TaggedService.priority`
      - invalid → deterministic `ResetException(code=CORETSIA_RESET_META_INVALID, message="reset-meta-invalid")`
    - `group`:
      - accepted: `string`
      - normalization:
        - trim ASCII whitespace
        - if empty OR absent → use `foundation.reset.group.default`
        - validate against `/\A[a-z0-9][a-z0-9._-]*\z/`
      - invalid → deterministic `ResetException(code=CORETSIA_RESET_META_INVALID, message="reset-meta-invalid")`

    - Orchestrator MUST read and validate ONLY:
      - `priority`, `group`
    - Any other meta keys MUST be ignored.

#### Wiring / DI tags (when applicable)

- [ ] Tag meta support:
  - [ ] the effective Foundation reset discovery tag meta supports:
    - [ ] `priority` (int|string-int; default falls back to `TaggedService.priority`)
    - [ ] `group` (string; normalized; default `foundation.reset.group.default`)
  - [ ] Backward compat:
    - [ ] when meta not provided OR priority disabled, ordering preserves previous deterministic behavior.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [ ] Spans (noop-safe):
  - [ ] `foundation.reset`
    - attrs (single-choice; cemented): `services_count`, `groups_count`, `outcome`
- [ ] Metrics (noop-safe; MUST NOT add new label keys):
  - [ ] `foundation.reset_total` (labels: `outcome`)
  - [ ] `foundation.reset_duration_ms` (labels: `outcome`)
- [ ] Logs (optional; summary-only):
  - [ ] MUST NOT log per-service internals/payloads/stack traces by default.
  - [ ] Allowed: counts + outcome + (optional) group counts.

#### Errors

- [ ] Deterministic code mapping (single-choice; cemented):
  - [ ] invalid tag meta → `CORETSIA_RESET_META_INVALID`
  - [ ] resolved service does NOT implement `ResetInterface` → `CORETSIA_RESET_SERVICE_NOT_RESETTABLE`
  - [ ] first reset failure (service throws) → `CORETSIA_RESET_SERVICE_FAILED`
  - [ ] internal observability port failure (must remain safe) → `CORETSIA_RESET_OBSERVABILITY_FAILED`
- [ ] Exception messages MUST be stable + safe:
  - [ ] MUST NOT contain absolute paths
  - [ ] MUST NOT contain secrets/PII/payloads
  - [ ] allowed: fixed tokens only (e.g. `reset-meta-invalid`, `reset-not-resettable`, `reset-service-failed`)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] service internals, secrets, payloads, absolute machine paths
- [ ] Allowed:
  - [ ] counts/outcome, normalized group ids

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] Deterministic ordering with priority/group:
  - `framework/packages/core/foundation/tests/Integration/PriorityResetOrderDeterministicTest.php`
- [ ] Group behavior:
  - `framework/packages/core/foundation/tests/Integration/ResetGroupWorksTest.php`
- [ ] Backward compat when disabled:
  - `framework/packages/core/foundation/tests/Integration/PriorityResetBackCompatWhenDisabledTest.php`
- [ ] Deterministic invalid-meta rejection:
  - `framework/packages/core/foundation/tests/Integration/PriorityResetMetaParsingRejectsInvalidTest.php`
- [ ] Locale independence:
  - `framework/packages/core/foundation/tests/Integration/ResetOrderingIsLocaleIndependentTest.php`
- [ ] Enhanced reset config shape lock:
  - [ ] `framework/packages/core/foundation/tests/Contract/FoundationEnhancedResetConfigShapeContractTest.php`

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/core/foundation/tests/Contract/FoundationEnhancedResetConfigShapeContractTest.php`
    - [ ] asserts `foundation.reset.priority.enabled` is bool
    - [ ] asserts `foundation.reset.group.default` matches `/\A[a-z0-9][a-z0-9._-]*\z/`
    - [ ] asserts invalid values fail deterministically with safe messages
- Integration:
  - [ ] `framework/packages/core/foundation/tests/Integration/PriorityResetOrderDeterministicTest.php`
  - [ ] `framework/packages/core/foundation/tests/Integration/ResetGroupWorksTest.php`
  - [ ] `framework/packages/core/foundation/tests/Integration/PriorityResetBackCompatWhenDisabledTest.php`
  - [ ] `framework/packages/core/foundation/tests/Integration/PriorityResetMetaParsingRejectsInvalidTest.php`
  - [ ] `framework/packages/core/foundation/tests/Integration/ResetOrderingIsLocaleIndependentTest.php`

### DoD (MUST)

- [ ] Deterministic reset order (priority DESC, group ASC, serviceId ASC) when enabled
- [ ] Backward compatible:
  - [ ] when `foundation.reset.priority.enabled=false` → legacy order preserved exactly
- [ ] Safe observability:
  - [ ] spans/metrics/logs do not leak secrets/PII
  - [ ] no new metric label keys introduced
- [ ] Deterministic error codes exist and are used (`ResetErrorCodes`)
- [ ] Tests green
- [ ] Out of scope:
  - [ ] MUST NOT change `ResetInterface`
  - [ ] MUST NOT introduce plugin systems/extensibility
- [ ] `docs/ssot/reset-tags.md` updated and aligned with implementation/tests

---

### 1.260.0 Runtime drivers & long-running composition matrix (SSoT) (MUST) [DOC]

---
type: docs
phase: 1
epic_id: "1.260.0"
owner_path: "docs/ssot/runtime-drivers.md"

goal: "Є один документ, який однозначно відповідає “що можна разом увімкнути”, і всі runtime entrypoints/commands підпорядковуються цим правилам через Kernel guard."
provides:
- "Єдине SSoT правило сумісності між HTTP runtime drivers та background drivers."
- "Явний перелік allowed vs conflict комбінацій (deterministic fail when conflict)."
- "Default safety policy: що вимкнено за замовчуванням і що може працювати паралельно."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/runtime-drivers.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - This SSoT document is the normative input for the future implementation epic `1.350.0` (`RuntimeDriverGuard`).
  - No implementation prerequisite is required for this doc-only epic.

- Required deliverables (exact paths):
  - `docs/ssot/INDEX.md` — SSoT index entrypoint exists and this epic appends its registrations here

- Required config keys (canonical matrix inputs; some are introduced by later owner epics):
  - `kernel.runtime.frankenphp.enabled`
  - `kernel.runtime.swoole.enabled`
  - `kernel.runtime.roadrunner.enabled`
  - `worker.enabled`
  - `worker.task_type`

- Note:
  - `worker.*` is introduced later by `1.360.0` (`platform/worker`).
  - In this doc these keys are normative future guard inputs, not a prerequisite that the `worker` root already exists at `1.260.0` time.

- Non-goals (MUST):
  - Doc MUST NOT describe guard implementation details (owned by guard epic).
  - Doc MUST NOT describe runtime adapters implementation details (owned by integrations 20.x).

- Determinism constraints (single-choice; cemented):
  - The matrix MUST be single-source-of-truth (no “either/or” альтернатив).
  - Conflicts MUST produce deterministic failure semantics (same code + same ordering of diagnostics everywhere).

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- none (doc-only)

Forbidden:
- N/A

### Entry points / integration points (MUST)

- CLI:
  - referenced by: `coretsia worker:start` (1.360.0)
- HTTP:
  - referenced by runtime entrypoints `frankenphp.php|swoole.php|roadrunner.php` (20.x)
- Kernel guard:
  - MUST treat this document as normative SSoT for compatibility decisions.

### Deliverables (MUST)

#### Creates

- [ ] `docs/ssot/runtime-drivers.md` — MUST include (single-choice; cemented):

  - Terminology / IDs (single-choice):

    - HTTP / background driver details (single-choice; cemented)
      - `http.classic`
        - Enabled when:
          - `kernel.runtime.frankenphp.enabled=false`
          - `kernel.runtime.swoole.enabled=false`
          - `kernel.runtime.roadrunner.enabled=false`
          - NOT (`worker.enabled=true && worker.task_type='http'`)
        - Conflicts:
          - none by itself
        - May run alongside:
          - `bg.worker_queue`

      - `http.frankenphp`
        - Enabled when:
          - `kernel.runtime.frankenphp.enabled=true`
        - Conflicts:
          - any other `http.*` driver enabled at the same time
          - `worker.enabled=true && worker.task_type='http'`
        - May run alongside:
          - `bg.worker_queue`
        - Notes:
          - artifact-only boot required (`module-manifest.php`, `config.php`, `container.php`, `routes.php`)
          - UoW boundary must be enforced per request; reset exactly once per UoW via kernel runtime

      - `http.swoole`
        - Enabled when:
          - `kernel.runtime.swoole.enabled=true`
        - Conflicts:
          - any other `http.*` driver enabled at the same time
          - `worker.enabled=true && worker.task_type='http'`
        - May run alongside:
          - `bg.worker_queue`
        - Notes:
          - artifact-only boot required
          - long-running loop must not leak context/state across requests

      - `http.roadrunner`
        - Enabled when:
          - `kernel.runtime.roadrunner.enabled=true`
        - Conflicts:
          - any other `http.*` driver enabled at the same time
          - `worker.enabled=true && worker.task_type='http'`
        - May run alongside:
          - `bg.worker_queue`
        - Notes:
          - artifact-only boot required
          - long-running loop must not leak context/state across requests

      - `http.worker`
        - Enabled when:
          - `worker.enabled=true && worker.task_type='http'`
        - Conflicts:
          - any other `http.*` driver enabled at the same time
        - Notes:
          - this is an HTTP runtime mode, not a background driver

      - `bg.worker_queue`
        - Enabled when:
          - `worker.enabled=true && worker.task_type='queue'`
        - Conflicts:
          - none at the matrix level
        - May run alongside:
          - `http.classic`
          - `http.frankenphp`
          - `http.swoole`
          - `http.roadrunner`
        - Notes:
          - this is a background driver, not an HTTP driver

    - Reserved future IDs (NOT part of the current Phase 1 guard inputs):
      - `bg.queue`
      - `bg.scheduler`

  - Hard rules (single-choice; MUST):
    - Exactly ONE HTTP driver may be active at a time.
    - Background drivers MAY run alongside any HTTP driver.
    - `http.worker` conflicts with any other `http.*` driver (mutual exclusion).

  - Default safety policy (single-choice):
    - `kernel.runtime.*.enabled` defaults to `false`
    - `worker.enabled` defaults to `false`
    - `worker.task_type` default MUST be safe (and MUST NOT implicitly enable an HTTP driver)

  - Missing-key policy before `1.360.0` (single-choice):
    - absence of `worker.enabled` MUST be treated as `false`
    - absence of `worker.task_type` MUST NOT activate any runtime driver
    - missing `worker.*` root by itself MUST NOT be treated as invalid config

  - Compatibility matrix:
    - MUST include an explicit table of ✅/❌ for:
      - (each http.*) × (each bg.*)
      - and (each pair of http.*) to show mutual exclusion
    - MUST include concrete examples (copy-pastable) for:
      - ✅ `http.roadrunner` + `bg.worker_queue`
      - ✅ `http.swoole` + `bg.worker_queue`
      - ✅ `http.frankenphp` + `bg.worker_queue`
      - ✅ `http.classic` + `bg.worker_queue`
      - ❌ `http.roadrunner` + `http.worker`
      - ❌ `http.frankenphp` + `http.worker`
      - ❌ `http.swoole` + `http.worker`

  - Deterministic enforcement contract (doc-as-SSoT; single-choice):
    - The guard MUST decide the active drivers by evaluating config keys only (no environment probing).
    - On conflict, the guard MUST fail deterministically:
      - deterministic error `CODE` (string) is the primary failure semantic
      - diagnostics (if any) MUST be stable and sorted lexicographically (byte-order; `strcmp`) by driver id.
    - Non-classic HTTP drivers (`http.frankenphp`, `http.swoole`, `http.roadrunner`, `http.worker`)
      require `platform.http` to be enabled in `ModulePlan`.
    - Missing required module for the selected HTTP driver MUST fail with:
      - `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`


  - Error codes (single-choice; cemented names in doc):
    - The doc MUST name the canonical guard error codes used for matrix violations (no free-form messages):
      - `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`
      - `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`
    - The guard MAY include minimal safe diagnostics (driver ids only), but MUST NOT include secrets/PII.

  - References to enforcement tests (required):
    - MUST reference the exact test paths that prove default + conflict behavior.

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/runtime-drivers.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] N/A (doc-only), but MUST reference enforcement tests (exact paths):
  - [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixDefaultClassicIsAllowedTest.php`
  - [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsRoadrunnerPlusWorkerHttpTest.php`

### Tests (MUST)

- Unit/Contract/Integration:
  - N/A
- Gates/Arch:
  - [ ] enforced by guard + tool tests (referenced)

### DoD (MUST)

- [ ] Док існує і узгоджений з guard правилами (SSoT)
- [ ] Док однозначний (single-choice; no “either/or”)
- [ ] Док посилається на відповідні конфіг ключі `kernel.runtime.*.enabled` і `worker.*`
- [ ] Док містить compatibility matrix + concrete examples
- [ ] Док фіксує deterministic enforcement contract (stable code + stable diagnostics ordering)
- [ ] Док посилається на E2E тест-матрицю (tooling integration tests)

---

### 1.270.0 Kernel: UnitOfWork Shapes Pack (Context + Result + Outcome + SSoT invariants) (MUST) [IMPL+DOC]

---
type: package
phase: 1
epic_id: "1.270.0"
owner_path: "framework/packages/core/kernel/"

package_id: "core/kernel"
composer: "coretsia/core-kernel"
kind: runtime
module_id: "core.kernel"

goal: "Kernel визначає стабільні format-neutral shapes (ctx+result) та канонічну SSoT outcome policy; усе зацементовано contract locks, і в kernel немає PSR-7/15."
provides:
- "SSoT shape для UnitOfWorkContext (json-like, format-neutral)"
- "SSoT shape для UnitOfWorkResult (json-like extensions, format-neutral)"
- "Outcome tokens: success|handled_error|fatal_error"
- "SSoT інваріанти UoW lifecycle (begin/after/reset exactly-once) і outcome mapping policy (HTTP/CLI)"
- "Guard для ctx.attributes (json-like + limits) + safety policy (no secrets/PII/stacktrace)"
- "Hooks contract: hooks отримують array $ctx/$result derived from shapes (no objects)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr:
- "docs/adr/ADR-0021-unit-of-work-context-shape.md"
- "docs/adr/ADR-0022-unit-of-work-result-outcome-policy.md"

ssot_refs:
- "docs/ssot/uow-shapes.md"
- "docs/ssot/uow-outcome-policy.md"
---

## Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - (Phase 1 sequencing) none — this epic is upstream for 1.280.0 (UoW shapes/policy)
  - PRELUDE.20.0 — packaging strategy locked
  - PRELUDE.30.0 — repo workspaces + test harness exist
  - 1.20.0 — Config roots registry exists and includes root `kernel` owned by `core/kernel`
  - 1.90.0 — observability/errors contracts exist (`Coretsia\Contracts\Observability\Errors\ErrorDescriptor`)
  - 1.200.0 — Foundation reset orchestration exists (referenced by the lifecycle invariant only)
  - 1.220.0 — canonical clock/ids/stopwatch services exist in Foundation

- Terminology note (MUST): config root vs config key namespaces
  - Config root for Kernel is **`kernel`** (file: `framework/packages/core/kernel/config/kernel.php`).
  - Any dotted prefixes like `kernel.uow.*`, `kernel.runtime.*`, `kernel.modules.*`, `kernel.config.*`, `kernel.artifacts.*`, `kernel.fingerprint.*`
    are **config key namespaces**, not separate roots.
  - `config/<name>.php` MUST return subtree for `<name>` (no wrapper array repeating the root key).

- Required deliverables (exact paths):
  - `docs/architecture/PACKAGING.md`
  - `framework/tools/testing/phpunit.xml`
  - `docs/ssot/INDEX.md`
  - `framework/packages/core/foundation/` — `UlidGenerator` (optional), `ClockInterface` binding (optional)

- Required config roots/keys:
  - none (all `kernel.uow.*` keys are introduced by this epic)

- Required tags:
  - none

- Required contracts / ports:
  - `Coretsia\Contracts\Observability\Errors\ErrorDescriptor` (optional, for `UnitOfWorkResult.error`)

### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/*`
- `Psr\Http\Message\*`
- `Psr\Http\Server\*`

### Uses ports (API surface, NOT deps) (optional)

- Foundation stable APIs:
  - `Coretsia\Foundation\Id\IdGeneratorInterface` (default generator for `uowId`; ULID recommended by default)
  - `Psr\Clock\ClockInterface` (startedAt/finishedAt)
  - `Coretsia\Foundation\Time\Stopwatch` (durationMs; if used)

## Entry points / integration points (MUST)

- Kernel hooks/tags:
  - before hooks receive `array $ctx` derived from UnitOfWorkContext (no objects; json-like only)
  - after hooks receive `array $result` derived from UnitOfWorkResult (no objects; json-like only)
- Export boundary rule (single-choice; cemented):
  - If `UnitOfWorkResult.error` is represented internally using `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`,
    Kernel MUST normalize it to a json-like exported error shape before passing `$result` to hooks/adapters/artifacts.
  - No object instance MAY cross the hook/export boundary.
- CLI/HTTP:
  - N/A (kernel-owned shapes; adapters attach **safe** attributes / derive outcome using policy)
- SSoT:
  - `docs/ssot/uow-outcome-policy.md` — єдина канонічна policy (lifecycle+mapping)
- Artifacts:
  - N/A

### Reset discipline invariant (MUST)

- `core/foundation` owns reset discovery through `foundation.reset.tag` (reserved default `kernel.reset`).
- This epic cements only the lifecycle invariant:
  - afterUoW completed → `ResetOrchestrator.resetAll()` MUST run exactly once → next UoW starts clean.
- 1.270.0 MUST NOT introduce reset-tag constants and MUST NOT depend on reset tag naming.
- This epic (**contracts+docs**) MUST **cement the lifecycle invariant** only:
  - once the after-phase is entered, `ResetOrchestrator.resetAll()` MUST run exactly once even if an after-uow hook throws, and the next UoW MUST start clean.
- **Trigger point owner**: `core/kernel` runtime (implemented in **1.280.0**), but policy/invariant is defined here in SSoT:
  - `docs/ssot/uow-outcome-policy.md` MUST include (single-choice) the invariant:
    - “after hooks → ResetOrchestrator.resetAll() → endUoW”
- **Implementation detail boundaries (cemented):**
  - 1.270.0 MUST NOT introduce/reset-tag constants.
  - 1.270.0 MUST NOT reference TagRegistry enumeration logic (that remains in `core/foundation` orchestrator).
- **Canonical executor name (cemented):**
  - `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`
  - (if any old typo exists in docs: “ResetOrcestrator” MUST be corrected to “ResetOrchestrator”.)

**Conceptual flow (SSoT-level, no code dependency):**

```
beginUow()
before_uow hooks
run external runtime (http/cli/queue/...)
after_uow hooks
ResetOrchestrator.resetAll()   // единственный reset trigger
endUow()
```

## Deliverables (exact paths only) (MUST)

### Creates

- [ ] `framework/packages/core/kernel/config/kernel.php` — adds `kernel.uow.attributes.*` defaults
- [ ] `framework/packages/core/kernel/config/rules.php` — enforces shape
- [ ] `framework/packages/core/kernel/src/Module/KernelModule.php` (runtime)
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php` (runtime)
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/core/kernel/README.md` — includes: Observability / Errors / Security-Redaction

Context:
- [ ] `framework/packages/core/kernel/src/Runtime/UnitOfWorkType.php` — enum-like: `http|cli|queue|scheduler`
- [ ] `framework/packages/core/kernel/src/Runtime/UnitOfWorkContext.php` — VO `{uowId,type,startedAt,correlationId,attributes}`
  - [ ] MUST validate `attributes` as json-like (float-forbidden; no objects/resources; deterministic path-safe failures)
  - [ ] MUST enforce `kernel.uow.attributes.max_depth` and `kernel.uow.attributes.max_keys`
  - [ ] MUST fail with `CORETSIA_UOW_CONTEXT_INVALID` using safe diagnostics only
- [ ] `framework/packages/core/kernel/src/Runtime/Exception/UnitOfWorkContextInvalidException.php` — errorCode `CORETSIA_UOW_CONTEXT_INVALID`

Result + outcome:
- [ ] `framework/packages/core/kernel/src/Runtime/Outcome.php` — enum-like outcome strings: `success|handled_error|fatal_error`
- [ ] `framework/packages/core/kernel/src/Runtime/UnitOfWorkResult.php` — VO `{uowId,type,correlationId,startedAt,finishedAt,durationMs,outcome,error?,extensions}`
  - [ ] MUST validate `extensions` as json-like (float-forbidden; no objects/resources; deterministic path-safe failures)
  - [ ] MUST reject unsafe values deterministically before export to hooks/adapters/artifacts
  - [ ] `extensions` MUST be json-like only
  - [ ] `error?` MAY be represented internally as `Coretsia\Contracts\Observability\Errors\ErrorDescriptor` (optional)
  - [ ] any exported/hook/artifact representation MUST normalize `error` to a json-like error map before crossing the kernel boundary

Docs:
- [ ] `docs/adr/ADR-0021-unit-of-work-context-shape.md`
- [ ] `docs/adr/ADR-0022-unit-of-work-result-outcome-policy.md`
- [ ] `docs/ssot/uow-outcome-policy.md` — (закріплено/розширено)
  - [ ] lifecycle invariants: begin/after/reset exactly-once
  - [ ] outcome mapping policy (HTTP/CLI) — **exact rules**:
    - [ ] HTTP:
      - [ ] `< 400` ⇒ `success`
      - [ ] `>= 400` ⇒ `handled_error`
      - [ ] uncaught exception ⇒ `fatal_error`
    - [ ] CLI:
      - [ ] `exitCode = 0` ⇒ `success`
      - [ ] `exitCode != 0` (без uncaught exceptions) ⇒ `handled_error`
      - [ ] uncaught exception ⇒ `fatal_error`
  - [ ] заборона включення stacktrace/payload/PII у `result.extensions`
  - [ ] (терміни) `success|handled_error|fatal_error` як стабільні токени

- [ ] `docs/ssot/uow-shapes.md` — canonical shapes:
  - [ ] UnitOfWorkContext fields + types:
    - [ ] `uowId` : string (stable id; ULID recommended)
    - [ ] `type`  : string enum `http|cli|queue|scheduler`
    - [ ] `startedAt` : int (unix epoch milliseconds, UTC)
    - [ ] `correlationId` : string (safe id; ULID recommended)
    - [ ] `attributes` : json-like map (float-forbidden; normalized)
  - [ ] UnitOfWorkResult fields + types:
    - [ ] `uowId` : string
    - [ ] `type`  : string
    - [ ] `correlationId` : string
    - [ ] `startedAt`  : int (unix epoch milliseconds, UTC) MUST match ctx.startedAt
    - [ ] `finishedAt` : int (unix epoch milliseconds, UTC)
      - [ ] wall-clock completion timestamp captured at end of UoW
      - [ ] because wall clock is not monotonic, consumers MUST NOT rely on `finishedAt >= startedAt`
      - [ ] `durationMs` is the only canonical duration source of truth
    - [ ] `durationMs` : int
      - [ ] canonical exported unit is integer milliseconds
      - [ ] MUST be measured from the canonical monotonic timing source (`Stopwatch`)
      - [ ] MUST NOT use `finishedAt - startedAt` as the source of truth for duration
      - [ ] MUST be non-negative
    - [ ] `outcome` : string enum `success|handled_error|fatal_error`
    - [ ] `error`? :
      - [ ] internal kernel representation MAY use `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
      - [ ] canonical exported shape passed to hooks/adapters/artifacts MUST be a normalized json-like error map
      - [ ] no object instance MAY cross the export boundary
    - [ ] export boundary rule:
      - [ ] internal runtime code MAY hold `error` as `ErrorDescriptor`
      - [ ] exported shapes passed to hooks/adapters/artifacts MUST carry only a normalized json-like error map
      - [ ] no object instance MAY cross the export boundary
    - [ ] `extensions` : json-like map (float-forbidden; normalized)
  - [ ] json-like rules for attributes/extensions (float-forbidden + normalization)
  - [ ] safety/redaction rules for attributes/extensions (no secrets/PII/stacktrace)
  - [ ] DTO policy boundary (cemented):
    - [ ] `UnitOfWorkContext` and `UnitOfWorkResult` are canonical kernel runtime shapes/VOs.
    - [ ] They are NOT DTO-marker classes by default.
    - [ ] DTO gates apply only to explicitly marked DTO transport classes.

### Modifies (config)

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/uow-outcome-policy.md`
  - [ ] `docs/ssot/uow-shapes.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0021-unit-of-work-context-shape.md`
  - [ ] `docs/adr/ADR-0022-unit-of-work-result-outcome-policy.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/core/kernel/src/Module/KernelModule.php` (runtime)
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php` (runtime)
- [ ] `framework/packages/core/kernel/config/kernel.php`  # returns subtree (no repeated root)
- [ ] `framework/packages/core/kernel/config/rules.php`
- [ ] `framework/packages/core/kernel/README.md`
- [ ] `framework/packages/core/kernel/composer.json`

## Configuration (keys + defaults) (MUST)

- Files:
  - [ ] `framework/packages/core/kernel/config/kernel.php`
- Keys (dot):
  - [ ] `kernel.uow.attributes.max_depth` = 10
  - [ ] `kernel.uow.attributes.max_keys`  = 200
- Rules:
  - [ ] `framework/packages/core/kernel/config/rules.php` enforces shape for:
    - [ ] `kernel.uow.attributes.max_depth` int>0
    - [ ] `kernel.uow.attributes.max_keys`  int>0

## Cross-cutting (MUST)

### Context & UoW

- [ ] `UnitOfWorkContext.attributes` MUST NOT contain:
  - [ ] Authorization/Cookie/session id/tokens/raw payload/raw SQL
- [ ] Allowed:
  - [ ] safe ids, enums, counts, lengths, hashes
- [ ] `UnitOfWorkResult.extensions` MUST NOT contain:
  - [ ] stacktrace, raw payload, PII, tokens

### Observability (policy-compliant)

- [ ] Policy link:
  - [ ] metrics labels use `operation=uow_type`, `outcome` (терміни закріплені; реалізація — в runtimes)

### Errors

- [ ] Exceptions introduced:
  - [ ] `UnitOfWorkContextInvalidException` — `CORETSIA_UOW_CONTEXT_INVALID`
- [ ] `ErrorDescriptor` in result:
  - [ ] optional, format-neutral, extensions json-like only

### Security / Redaction

- [ ] MUST NOT leak via ctx.attributes / result.extensions:
  - [ ] tokens/session/cookies/raw payload/raw SQL/stacktrace/PII
- [ ] Allowed:
  - [ ] safe meta only

### Phase 0 parity: json-like policy (MUST)

Цей епік MUST бути сумісним із зацементованими json-like інваріантами з PHASE 0 SPIKES:
- 0.70.0 (PayloadNormalizer + StableJsonEncoder + float-forbidden policy)
- 0.20.0 (output-free бізнес-логіка; deterministic errors; no secrets/PII)

#### Json-like definition (single-choice; cemented)

`UnitOfWorkContext.attributes` і `UnitOfWorkResult.extensions` MUST бути **json-like**:

- Allowed scalars: `null|bool|int|string`
- Forbidden scalars: `float` (включно `NaN`, `INF`, `-INF`)
- Allowed containers: `array` тільки як:
  - list (`array_is_list($value) === true`) — порядок елементів зберігається
  - map  (`array_is_list($value) === false`) — ключі тільки `string` (no int keys)

Objects/Resources/Closures/Enums (як objects) — FORBIDDEN.

#### Normalization invariant for exported shapes (single-choice)

Коли shape експортується назовні kernel (hooks / adapters / artifacts), він MUST бути нормалізований детерміновано:

- maps: ключі сортуються **на кожному рівні** за byte-order (`strcmp`)
- lists: порядок **не змінюється**
- locale MUST NOT впливати (no `setlocale`, no `LC_ALL` reliance)

#### Float/NaN/INF rejection (cemented)

Якщо в `attributes` або `extensions` знайдено `float|NaN|INF`:
- MUST бути детермінована відмова (без друку значень)
- diagnostics MAY містити тільки *path-to-value* (наприклад `a.b[3].c`) і MUST NOT містити raw value

#### Safety / Redaction parity

`attributes/extensions` MUST NOT містити:
- tokens/session/cookies/Authorization
- raw payload/raw SQL
- stacktrace/PII

Allowed: safe ids/enums/counts/lengths/hashes.

## Verification (TEST EVIDENCE) (MUST when applicable)

### Contract / snapshot locks

- [ ] Context shape lock:
  - [ ] `framework/packages/core/kernel/tests/Contract/UnitOfWorkContextShapeContractTest.php`
- [ ] Kernel config subtree shape lock:
  - [ ] `framework/packages/core/kernel/tests/Contract/KernelConfigSubtreeShapeContractTest.php`
- [ ] Context attributes json-like + limits:
  - [ ] `framework/packages/core/kernel/tests/Contract/UnitOfWorkContextAttributesAreJsonLikeContractTest.php`
- [ ] Result shape lock:
  - [ ] `framework/packages/core/kernel/tests/Contract/UnitOfWorkResultShapeContractTest.php`
- [ ] `framework/packages/core/kernel/tests/Contract/UnitOfWorkResultExtensionsAreJsonLikeContractTest.php`
  - [ ] asserts `extensions` reject `float|NaN|INF|-INF`
  - [ ] asserts `extensions` reject objects/resources
  - [ ] asserts diagnostics are safe and contain no raw values
- [ ] Outcome mapping stability snapshot (policy lock):
  - [ ] `framework/packages/core/kernel/tests/Contract/OutcomeMappingStabilityContractTest.php`

> NOTE: `OutcomeMappingStabilityContractTest` є **єдиним** контрактом, що цементує правила з `docs/ssot/uow-outcome-policy.md`.

## Tests (MUST)

Contract:
- [ ] `framework/packages/core/kernel/tests/Contract/UnitOfWorkContextShapeContractTest.php`
- [ ] `framework/packages/core/kernel/tests/Contract/UnitOfWorkContextAttributesAreJsonLikeContractTest.php`
- [ ] `framework/packages/core/kernel/tests/Contract/UnitOfWorkResultShapeContractTest.php`
- [ ] `framework/packages/core/kernel/tests/Contract/UnitOfWorkResultExtensionsAreJsonLikeContractTest.php`
- [ ] `framework/packages/core/kernel/tests/Contract/OutcomeMappingStabilityContractTest.php`
- [ ] `framework/packages/core/kernel/tests/Contract/KernelConfigSubtreeShapeContractTest.php`
  - [ ] MUST fail if `framework/packages/core/kernel/config/kernel.php` returns repeated root:
    - [ ] ✅ subtree only
    - [ ] ❌ `['kernel' => [...]]`
  - [ ] MUST fail if any `@*` key exists under returned subtree (any depth)
  - [ ] additive namespaces introduced by later kernel epics are allowed

## DoD (MUST)

- [ ] Context shape stable + contract-tested
- [ ] Result/outcome shape stable + contract-tested
- [ ] Outcome tokens stable: `success|handled_error|fatal_error`
- [ ] Attributes guard is implemented and prevents non-json-like values / overflows deterministically
- [ ] No PSR-7/15 leakage
- [ ] `docs/ssot/uow-outcome-policy.md` відповідає реальним правилам, які зафіксовані `OutcomeMappingStabilityContractTest`
- [ ] Нема дублювання “як саме” у коді — лише правила/інваріанти в SSoT; runtimes реалізують mapping.
- [ ] Non-goals / out of scope:
  - [ ] Kernel не будує HTTP response і не визначає статус-коди (це adapters/platform-layer).
  - [ ] Kernel не логує stacktrace у `result.extensions`.
- [ ] Adapters invariants:
  - [ ] `platform/http` може додавати http-specific attributes (safe only), але **shape залишається kernel-owned**
  - [ ] `platform/cli` може додавати cli-specific attributes (safe only), але **shape залишається kernel-owned**
- [ ] When a UoW is started, then it contains `uowId`, `type`, `startedAt`, `correlationId`, and json-like `attributes`.
- [ ] When a CLI command returns exit code 2 without uncaught exceptions, then the UoW outcome is `handled_error`.
- [ ] `UnitOfWorkContext` and `UnitOfWorkResult` are kernel-owned runtime shapes/VOs, not DTO-marker classes by default

---

### 1.280.0 Kernel: KernelRuntime (UoW SPI, no PSR-7) (MUST) [IMPL]

---
type: package
phase: 1
epic_id: "1.280.0"
owner_path: "framework/packages/core/kernel/"

package_id: "core/kernel"
composer: "coretsia/core-kernel"
kind: runtime
module_id: "core.kernel"

goal: "Будь-який runtime (HTTP/CLI/worker) може виконувати UoW через KernelRuntime без протікання контексту/стану між UoW."
provides:
- "Канонічний UoW orchestrator: begin → hooks → external runtime → after → reset"
- "Однаковий lifecycle для HTTP/CLI/Queue/Scheduler без PSR-7/15 у kernel"
- "Гарантія: afterUnitOfWork() викликається завжди; reset рівно 1 раз (long-running safe)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0020-kernel-runtime-uow-spi.md"
ssot_refs:
- "docs/ssot/uow-outcome-policy.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.10.0 — Tag registry SSoT exists (`core/kernel` is the owner package that declares canonical constants for `kernel.hook.before_uow` and `kernel.hook.after_uow`)
  - 1.20.0 — Config roots registry exists and includes root `kernel` owned by `core/kernel`
  - 1.200.0 — Foundation tag/reset infrastructure exists (`TagRegistry`, `ResetOrchestrator`, deterministic ordering).
  - 1.205.0 — Foundation noop observability + logger baseline bindings exist, so KernelRuntime observability remains safely resolvable without `platform/*` packages installed.
  - 1.210.0 — `ContextStore` + `ContextKeys` exist and are canonical for runtime context.
  - 1.220.0 — canonical clock/ids/stopwatch bindings exist in Foundation.
  - 1.270.0 — UnitOfWork shapes are canonical and provide the exported ctx/result model for hooks.

- Terminology note (MUST): config root vs config key namespaces
  - Config root for Kernel is **`kernel`** (file: `framework/packages/core/kernel/config/kernel.php`).
  - Any dotted prefixes like `kernel.uow.*`, `kernel.runtime.*`, `kernel.modules.*`, `kernel.config.*`, `kernel.artifacts.*`, `kernel.fingerprint.*`
    are **config key namespaces**, not separate roots.
  - `config/<name>.php` MUST return subtree for `<name>` (no wrapper array repeating the root key).

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/` — Hook interfaces + ResetInterface (ports)
  - `framework/packages/core/foundation/` — ContextStore + ContextKeys + ResetOrchestrator + DeterministicOrder + TagRegistry

- Required config roots/keys:
  - none (all `kernel.runtime.*` keys are introduced by this epic)

- Required tags:
  - reset discipline is consumed only through `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`; kernel MUST NOT depend on reset tag naming

- Required contracts / ports:
  - `Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface` — before hooks
  - `Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface` — after hooks
  - `Coretsia\Contracts\Runtime\ResetInterface` — reset discipline
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface` — canonical correlation id source for UoW base context
  - `Psr\Log\LoggerInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

- Hook invocation ordering (MUST)
  - KernelRuntime/HookInvoker MUST obtain hook lists ONLY via:
    - `Coretsia\Foundation\Tag\TagRegistry::all(<tag>)`
  - Kernel MUST invoke hooks **exactly in the order returned by TagRegistry**.
  - Kernel MUST NOT:
    - re-sort hooks,
    - dedupe hooks,
    - apply its own “priority” rules.
  - Rationale: deterministic ordering policy is owned by Foundation (`DeterministicOrder` + TagRegistry).

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- `Psr\Http\Server\*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface`
  - `Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface`
  - `Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextStore`
  - `Coretsia\Foundation\Context\ContextKeys`
  - `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`
  - `Coretsia\Foundation\Tag\TagRegistry`
  - `Psr\Clock\ClockInterface` (via foundation binding)
  - `Coretsia\Foundation\Time\Stopwatch`
  - `Coretsia\Foundation\Id\IdGeneratorInterface`

### Entry points / integration points (MUST)

- Kernel hooks/tags:
  - `kernel.hook.before_uow` (before-uow hooks discovery tag; ordering owned by Foundation TagRegistry)
  - `kernel.hook.after_uow`  (after-uow hooks discovery tag; ordering owned by Foundation TagRegistry)
  - consumer MUST NOT enumerate reset tags directly (reset discipline tag; owned by `core/foundation`)

- Platform/HTTP (owner: `platform/http`):
  - MUST wrap each request into a UoW:
    - `beginUnitOfWork(type=http, ...)` before handling
    - `afterUnitOfWork(...)` in `finally` (always)

- Platform/CLI (owner: `platform/cli`):
  - MUST wrap each command into a UoW (type=cli),
    except explicitly defined ultra-early flows (if any) that are declared out-of-scope.

- Long-running adapters (worker/scheduler; owners: platform adapters):
  - MUST execute each job/tick as its own UoW via KernelRuntime (no cross-UoW leakage).

Build-time commands (explicit non-goal for KernelRuntime):
- `config:compile` and `cache:verify` are build-time concerns and MUST NOT require UoW lifecycle.
  (Their entrypoint ownership and integration are defined in 1.330.0, not here.)

- Hook payload boundary (cemented):
  - before/after hooks receive normalized exported arrays derived from kernel-owned shapes.
  - Kernel MUST NOT pass DTO-marker objects to hooks.
  - `UnitOfWorkContext` / `UnitOfWorkResult` remain kernel-owned runtime shapes, not DTO-marker classes by default.

### Reset discipline integration (MUST)

- Kernel MUST NOT know the reset discovery tag name (`foundation.reset.tag` / `kernel.reset`).
- Kernel MUST NOT enumerate reset services directly.
- Kernel MUST ONLY call `ResetOrchestrator::resetAll(): void`.
- Tag name resolution remains entirely in Foundation.
- **Kernel MUST NOT treat `kernel.reset` as a kernel feature.** It is a discovery tag owned by `core/foundation`.
- **Kernel MUST NOT enumerate/reset services itself.**
  - Forbidden in `core/kernel` runtime code:
    - `TagRegistry->all('kernel.reset')`
    - resolving resettable services by tag in kernel
- **Kernel MUST ONLY trigger reset via Foundation executor:**
  - `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator::resetAll(): void`
- **Exact trigger point (single-choice; cemented):**
  - KernelRuntime MUST call `resetAll()` **exactly once per UoW**, **after**:
    1) after-uow hooks (`kernel.hook.after_uow`)
    2) and after outcome/result normalization is complete
  - Reset MUST run even when the external runtime throws (long-running safety).
  - Reset MUST also run even if an `after-uow` hook throws.
  - `KernelRuntime` MUST guarantee `ResetOrchestrator::resetAll()` exactly once per UoW via `try/finally` semantics around the whole after-phase.

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/kernel/src/Runtime/KernelRuntimeInterface.php` — UoW begin/after API (no PSR-7)
- [ ] `framework/packages/core/kernel/src/Runtime/KernelRuntime.php` — orchestrator: hooks + reset + base context keys
- [ ] `framework/packages/core/kernel/src/Runtime/Hook/HookInvoker.php` — invokes hooks in **exact TagRegistry order** (kernel MUST NOT re-sort/dedupe/priority)
- [ ] `framework/packages/core/kernel/src/Runtime/Hook/HookContextNormalizer.php` — ensures ctx/result payload is json-like
  - [ ] MUST normalize known internal kernel result objects before generic object rejection.
  - [ ] In particular, if `UnitOfWorkResult.error` is internally represented as `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`, it MUST be exported as a json-like error map.
  - [ ] After normalization, no object instances may remain in the hook payload.
- [ ] `framework/packages/core/kernel/src/Runtime/Exception/KernelRuntimeException.php` — errorCode `CORETSIA_KERNEL_RUNTIME_ERROR`
- [ ] `framework/packages/core/kernel/src/Provider/Tags.php` — tag constants owner
- [ ] `docs/adr/ADR-0020-kernel-runtime-uow-spi.md`

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0020-kernel-runtime-uow-spi.md`
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php` — registers/binds runtime services + hook invoker
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/core/kernel/README.md` — documents:
  - [ ] KernelRuntime lifecycle (`begin → hooks → external runtime → after → reset`)
  - [ ] reset boundary (`core/kernel` calls only `ResetOrchestrator::resetAll()`)
  - [ ] no-PSR-7/15 invariant in kernel runtime
  - [ ] hook discovery via `kernel.hook.before_uow` / `kernel.hook.after_uow`
- [ ] `framework/packages/core/kernel/composer.json`
  - [ ] add runtime requirement:
    - [ ] `psr/log`

#### Configuration (keys + defaults)

N/A

- [ ] Policy:
  - [ ] `kernel.hook.before_uow` and `kernel.hook.after_uow` are kernel-owned canonical tag names
  - [ ] they MUST NOT be configurable via runtime config
  - [ ] `HookInvoker` MUST use the kernel-owned constants, not config-provided tag strings
  - [ ] `KernelRuntime` and hook discovery are baseline kernel infrastructure and MUST NOT be feature-disabled via config.
  - [ ] Absence of hooks is represented by empty `TagRegistry` results, NOT by disabling hook execution subsystem.

#### Wiring / DI tags (when applicable)

- [ ] Kernel constants for already-canonical hook tags:
  - [ ] `framework/packages/core/kernel/src/Provider/Tags.php`
  - [ ] constants:
    - [ ] `KERNEL_HOOK_BEFORE_UOW = 'kernel.hook.before_uow'`
    - [ ] `KERNEL_HOOK_AFTER_UOW  = 'kernel.hook.after_uow'`
    - [ ] These constants are the canonical public owner constants for the reserved hook tags.
    - [ ] Any package that is allowed to depend on `core/kernel` and needs these tags in runtime code MUST use:
      - [ ] `Coretsia\Kernel\Provider\Tags::KERNEL_HOOK_BEFORE_UOW`
      - [ ] `Coretsia\Kernel\Provider\Tags::KERNEL_HOOK_AFTER_UOW`
    - [ ] Raw literal tag strings remain allowed only in docs/tests/fixtures for readability and MUST NOT be the preferred runtime-code pattern.
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `Coretsia\Kernel\Runtime\KernelRuntime`
  - [ ] registers: `Coretsia\Kernel\Runtime\Hook\HookInvoker`
  - [ ] binds: `Coretsia\Kernel\Runtime\KernelRuntimeInterface` → `KernelRuntime`

- [ ] `KernelRuntime` MUST receive `ResetOrchestrator` via DI (constructor injection).
- [ ] `KernelRuntime` MUST receive `Coretsia\Contracts\Observability\CorrelationIdProviderInterface` via DI for canonical `correlation_id` provisioning.
- [ ] `KernelRuntime` MUST receive `\Psr\Log\LoggerInterface` via DI for lifecycle summary logs.
- [ ] `KernelRuntime` MUST receive `\Coretsia\Contracts\Observability\Tracing\TracerPortInterface` via DI for canonical `kernel.uow` span emission.
- [ ] `KernelRuntime` MUST receive `\Coretsia\Contracts\Observability\Metrics\MeterPortInterface` via DI for canonical `kernel.uow_total` / `kernel.uow_duration_ms` metrics.
- [ ] `KernelRuntime` MUST receive the default Foundation id generator via DI for canonical `uow_id` generation.
- [ ] `KernelRuntime` MUST receive `Coretsia\Foundation\Time\Stopwatch` via DI for canonical `durationMs` measurement.
- [ ] `core/kernel` MUST NOT define `KERNEL_RESET` constants.
  - [ ] Tag constant lives in `core/foundation`: `Coretsia\Foundation\Provider\Tags::KERNEL_RESET`.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- [ ] Context writes (safe only):
  - [ ] `ContextKeys::CORRELATION_ID` (safe id)
  - [ ] `ContextKeys::UOW_ID` (safe id)
  - [ ] `ContextKeys::UOW_TYPE` (`http|cli|queue|scheduler`)
  - [ ] `correlation_id` MUST come only from `CorrelationIdProviderInterface`
  - [ ] `uow_id` MUST come only from the default Foundation id generator
  - [ ] `durationMs` MUST be measured only via `Stopwatch` and exported as non-negative `int`
- [ ] Reset discipline:
  - [ ] reset executed via `ResetOrchestrator` against the effective Foundation reset discovery tag (default `kernel.reset`), opaque to kernel
  - [ ] no secrets/PII/session ids written into ContextStore

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `kernel.uow` (attrs: `operation=uow_type`, `outcome`)
- [ ] Metrics:
  - [ ] `kernel.uow_total` (labels: `operation`, `outcome`)
  - [ ] `kernel.uow_duration_ms` (labels: `operation`, `outcome`)
- [ ] Logs:
  - [ ] lifecycle summary only (no payloads, no env values)
- [ ] Label normalization applied (if needed):
  - [ ] `uow_type → operation`

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/core/kernel/src/Runtime/Exception/KernelRuntimeException.php` — errorCode `CORETSIA_KERNEL_RUNTIME_ERROR`
- [ ] Mapping:
  - [ ] kernel does not map to HTTP; mapping done by `platform/errors` + adapters

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] dotenv values, tokens, cookies, payloads
- [ ] Allowed:
  - [ ] safe ids (`correlation_id`, `uow_id`), `hash/len` for sensitive debug strings (if ever logged)

### Phase 0 parity: deterministic orchestration + output-free runtime (MUST)

KernelRuntime MUST наслідувати зацементовані інваріанти PHASE 0 SPIKES rails:
- 0.20.0: output bypass заборонений у бізнес-логіці
- 0.70.0: json-like normalization (float-forbidden) для payload’ів, які віддаються hooks/adapters

#### Output-free invariant (single-choice)

`core/kernel` runtime code MUST NOT писати в stdout/stderr:
- `echo/print/var_dump/print_r/printf/error_log`
- `fwrite(STDOUT|STDERR, ...)`, `php://stdout|stderr|output`

Уся людиночитна діагностика — лише в `platform/*` шарі.
Kernel повертає/кидає детерміновані винятки/результати.

#### Deterministic ordering invariant (single-choice)

Будь-яке впорядкування (hooks, warnings, trace records) MUST бути:
- byte-order (`strcmp`) і deterministic
- locale-independent (no reliance on OS locale)

#### Json-like export invariant (single-choice)

Будь-які payload’и, що передаються в hooks (`array $ctx/$result`), MUST:
- бути json-like (див. 1.270.0 parity)
- бути нормалізовані: map keys sorted recursively; list order preserved
- forbidden: floats (NaN/INF), objects/resources

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] Hook ordering invariant:
  - [ ] `framework/packages/core/kernel/tests/Unit/HookInvokerDeterministicOrderTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelRuntimeInvokesHooksInDeterministicOrderTest.php`

- [ ] Hook payload normalization invariant:
  - [ ] `framework/packages/core/kernel/tests/Unit/HookContextNormalizerRejectsNonJsonLikeValuesTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelRuntimeExportsNormalizedHookPayloadsTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelRuntimeWritesBaseContextKeysAtBeginUowTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelRuntimeUsesCorrelationIdProviderAndDefaultIdGeneratorTest.php`

- [ ] Reset boundary + exactly-once semantics:
  - [ ] `framework/packages/core/kernel/tests/Contract/KernelDoesNotEnumerateResetDiscoveryTagTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelRuntimeResetHappensAfterAfterUowHooksTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelRuntimeAlwaysResetsAfterUowTest.php`

- [ ] PSR-7/15-free public API:
  - [ ] `framework/packages/core/kernel/tests/Contract/KernelPublicApiDoesNotExposePsr7Test.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` via existing test infra (if present)

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/core/kernel/tests/Unit/HookInvokerDeterministicOrderTest.php` (asserts: **preserves TagRegistry order**; no additional sorting)
  - [ ] `framework/packages/core/kernel/tests/Unit/HookContextNormalizerRejectsNonJsonLikeValuesTest.php`
    - [ ] rejects floats / `NaN` / `INF` / `-INF`
    - [ ] rejects objects / resources
    - [ ] failure MUST be deterministic and MUST NOT leak raw values
  - [ ] `framework/packages/core/kernel/tests/Unit/HookContextNormalizerNormalizesErrorDescriptorTest.php`
- Contract:
  - [ ] `framework/packages/core/kernel/tests/Contract/KernelPublicApiDoesNotExposePsr7Test.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/KernelRuntimeDoesNotWriteToStdoutTest.php`
    - [ ] token-scan `framework/packages/core/kernel/src/Runtime/**` and `src/Provider/**`
    - [ ] MUST fail on `echo|print|var_dump|print_r|printf|error_log`
    - [ ] MUST fail on `STDOUT|STDERR`, `php://stdout`, `php://stderr`, `php://output`
    - [ ] tests/fixtures are excluded
  - [ ] `framework/packages/core/kernel/tests/Contract/KernelDoesNotEnumerateResetDiscoveryTagTest.php`
    - [ ] MUST fail if `core/kernel/src/**` contains any direct reset-discovery knowledge, including:
      - [ ] string literal `kernel.reset`
      - [ ] reads of config key `foundation.reset.tag`
      - [ ] references to `Coretsia\Foundation\Provider\Tags::KERNEL_RESET`
      - [ ] direct reset-service enumeration via `TagRegistry`
    - [ ] Allowed boundary:
      - [ ] dependency on `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`
      - [ ] calling only `ResetOrchestrator::resetAll(): void`
- Integration:
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelRuntimeInvokesHooksInDeterministicOrderTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelRuntimeResetHappensAfterAfterUowHooksTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelRuntimeAlwaysResetsAfterUowTest.php` MUST assert:
    - [ ] after-uow hooks ran **before** reset trigger
      - [ ] reset trigger ran **exactly once per UoW**
      - [ ] reset trigger runs in exception path (try/finally semantics)
    - [ ] when an `after-uow` hook throws, reset still runs exactly once before the failure is surfaced
  - [ ] `framework/packages/core/kernel/tests/Integration/KernelRuntimeExportsNormalizedHookPayloadsTest.php`
    - [ ] maps recursively sorted by `strcmp`
    - [ ] list order preserved
    - [ ] exported ctx/result passed to hooks are json-like only
    - [ ] if `UnitOfWorkResult.error` exists internally as `ErrorDescriptor`, hooks MUST receive a normalized json-like `error` map, never an object
- [ ] `framework/packages/core/kernel/tests/Integration/KernelRuntimeWritesBaseContextKeysAtBeginUowTest.php`
  - [ ] asserts `ContextStore` contains `correlation_id`, `uow_id`, `uow_type` before external runtime body is executed
- [ ] `framework/packages/core/kernel/tests/Integration/KernelRuntimeUsesCorrelationIdProviderAndDefaultIdGeneratorTest.php`
  - [ ] asserts `correlation_id` comes from `CorrelationIdProviderInterface`
  - [ ] asserts `uow_id` comes from the default Foundation `IdGeneratorInterface`

- Gates/Arch:
  - [ ] PSR-7/15-free gate for core/kernel (existing)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: deterministic hook invocation
- [ ] Docs updated:
  - [ ] README
  - [ ] ADR present
- [ ] Дає канонічний **UoW orchestrator**: begin → hooks → external runtime (http/cli/worker) → after → reset.
- [ ] Гарантує **однаковий lifecycle** для HTTP/CLI/Queue/Scheduler без PSR-7/15 у kernel.
- [ ] Гарантує: `afterUnitOfWork()` викликається завжди і reset відбувається рівно 1 раз (long-running safe).
- [ ] Non-goals / out of scope:
  - [ ] Kernel НЕ реалізує HTTP pipeline / middleware (це `platform/http`).
  - [ ] Kernel НЕ робить feature-specific flush логіки (events/outbox/audit тощо) — тільки hooks.
  - [ ] Kernel НЕ залежить від `platform/*` і НЕ тягне PSR-7/15.
- [ ] Usually present when enabled in presets/bundles:
  - [ ] `platform/http` обгортає кожен request у UoW типу `http` (begin/after).
  - [ ] `platform/cli` обгортає кожну команду у UoW типу `cli` (крім ultra-early doctor).
  - [ ] observability ports are resolvable in the micro preset via Foundation noop bindings from `1.205.0`; later `platform/logging|tracing|metrics` packages MAY override these bindings when enabled in a preset/bundle.
- [ ] Discovery / wiring via kernel-owned tags:
  - [ ] `kernel.hook.before_uow`
  - [ ] `kernel.hook.after_uow`
- [ ] Reset integration boundary:
  - [ ] `core/kernel` MUST NOT own, resolve, enumerate, or hardcode `kernel.reset`
  - [ ] `core/kernel` MUST call ONLY:
    - [ ] `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator::resetAll(): void`
- [ ] When an exception happens in HTTP runtime but is handled into an error response, then `afterUnitOfWork()` still runs and ContextStore is reset before the next request.
- [ ] Kernel MUST NOT care whether enhanced reset (1.250.0) is enabled.
  - [ ] Kernel always calls `ResetOrchestrator::resetAll()`.
  - [ ] Ordering + meta parsing is entirely owned by `core/foundation`.
- [ ] Base context keys are written at `beginUoW` from canonical providers only (`correlation_id` via `CorrelationIdProviderInterface`, `uow_id` via Foundation default `IdGeneratorInterface`)

---

### 1.290.0 HTTP Kernel long-running safety harness (MUST) [IMPL]

---
type: package
phase: 1
epic_id: "1.290.0"
owner_path: "framework/packages/platform/http/"

package_id: "platform/http"
composer: "coretsia/platform-http"
kind: runtime
module_id: "platform.http"

goal: "Один і той самий process може обробити 100 sequential requests без leak, а reset виконується детерміновано після кожного request."
provides:
- "`platform/http` long-running safe: HttpKernel/handler викликається багато разів у одному процесі без протікання стану/контексту."
- "Harness/integration tests на sequential requests + reset discipline via Foundation reset orchestration (`ResetOrchestrator`; default discovery tag is `kernel.reset`)."
- "Optional span attribute `long_running=true` (НЕ metric label)."
- "Consumers of this harness (policy): `integrations/runtime-frankenphp`, `integrations/runtime-swoole`, `integrations/runtime-roadrunner`, and `platform/worker` when `worker.task_type=http`."
- "No extra runtime toggles required: protection/harness is activated by using a long-running HTTP runtime adapter; no middleware/endpoint changes."

runtime_invariant:
- "Long-running safety is a consequence of the standard KernelRuntime + Foundation reset flow."
- "It MUST NOT be controlled by a dedicated `enabled=false` style flag in Foundation."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0018-kernel-long-running-safety-harness.md"
ssot_refs:
- "docs/ssot/reset-tags.md"
- "docs/ssot/context-lifecycle.md"
- "docs/ssot/context-store.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.10.0 — Tag registry SSoT exists (`platform/http` is the owner package that declares canonical constants for reserved `http.middleware.*` tags)
  - 1.20.0 — Config roots registry SSoT exists and reserves root `http` for `platform/http`
  - 1.95.0 — `Coretsia\Contracts\Context\ContextAccessorInterface` exists for context inspection (if used).
  - 1.190.0 — canonical HTTP middleware taxonomy + catalog SSoT exists (`docs/ssot/http-middleware-catalog.md`)
  - 1.200.0 — Foundation reset orchestration exists, and reset discovery runtime truth is `foundation.reset.tag` (reserved default `kernel.reset`)
  - 1.205.0 — Foundation noop observability + logger baseline bindings exist, so optional long-running safety instrumentation remains safe without platform observability packages installed.
  - 1.210.0 — `ContextStore` + canonical context implementation exist.
  - 1.230.0 — context lifecycle SSoT exists (`docs/ssot/context-lifecycle.md`)
  - 1.240.0 — reset/stateful services SSoT exists (`docs/ssot/reset-tags.md`)
  - 1.280.0 — `core/kernel` provides `KernelRuntime`, which wraps each request as a UoW and executes the canonical after/reset flow.

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/` — `ResetInterface`, tracer/meter ports (optional).
  - `framework/packages/core/foundation/` — context accessor.
  - `framework/packages/core/kernel/` — UoW lifecycle.
  - `docs/ssot/http-middleware-catalog.md` — canonical slot taxonomy + baseline placement reference used by `platform/http`

- Required tags:
  - reset discipline is provided by Foundation/Kernel runtime; `platform/http` relies on it but MUST NOT enumerate reset tags directly

- Required contracts / ports:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Server\RequestHandlerInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` (optional; noop-safe)
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` (optional; noop-safe)

- Non-goals (legacy):
  - No process manager / worker pool (owned by other epics or integrations).
  - No middleware stack / endpoint changes.

- Acceptance scenario (legacy):
  - When running `LongRunningHttpKernelDoesNotLeakStateTest`, then 100 sequential requests succeed and memory/context invariants hold.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- `integrations/*`
- (cemented) `platform/http` MUST NOT depend on other `platform/*` runtime packages
  - `core/*` dependencies are allowed (e.g. `core/kernel`, `core/foundation`, `core/contracts`)

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Server\RequestHandlerInterface`
  - `Psr\Log\LoggerInterface` (optional)
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

### Entry points / integration points (MUST)

- CLI:
  - N/A
- HTTP:
  - N/A (uses existing stack; adds harness/probe)
- Kernel hooks/tags:
  - relies on Foundation reset orchestration via `ResetOrchestrator`
- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

Package skeleton (because `platform/http` does not exist yet):
- [ ] `framework/packages/platform/http/composer.json`
  - [ ] MUST require runtime packages:
    - [ ] psr/http-message
    - [ ] psr/http-server-handler
- [ ] `framework/packages/platform/http/src/Module/HttpModule.php`
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceProvider.php`
- [ ] `framework/packages/platform/http/src/Provider/Tags.php` — owner constants for canonical HTTP middleware slot tags
- [ ] `framework/packages/platform/http/src/Provider/HttpServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/http/README.md` (Observability / Errors / Security-Redaction)

HTTP tag constants:
- [ ] `framework/packages/platform/http/src/Provider/Tags.php`
  - [ ] `public const HTTP_MIDDLEWARE_SYSTEM_PRE = 'http.middleware.system_pre';`
  - [ ] `public const HTTP_MIDDLEWARE_SYSTEM = 'http.middleware.system';`
  - [ ] `public const HTTP_MIDDLEWARE_SYSTEM_POST = 'http.middleware.system_post';`
  - [ ] `public const HTTP_MIDDLEWARE_APP_PRE = 'http.middleware.app_pre';`
  - [ ] `public const HTTP_MIDDLEWARE_APP = 'http.middleware.app';`
  - [ ] `public const HTTP_MIDDLEWARE_APP_POST = 'http.middleware.app_post';`
  - [ ] `public const HTTP_MIDDLEWARE_ROUTE_PRE = 'http.middleware.route_pre';`
  - [ ] `public const HTTP_MIDDLEWARE_ROUTE = 'http.middleware.route';`
  - [ ] `public const HTTP_MIDDLEWARE_ROUTE_POST = 'http.middleware.route_post';`

Test-only harness/support:
- [ ] `framework/packages/platform/http/tests/Support/LongRunningSafetyProbe.php` — helper used only by tests
  - [ ] MUST NOT change production behavior (no hooks, no middleware injection)
  - [ ] MUST NOT emit stdout/stderr output
  - [ ] MUST NOT log request payloads/headers/tokens
  - [ ] MAY inspect ContextStore via `ContextAccessorInterface` for invariants only

Docs:
- [ ] `docs/adr/ADR-0018-kernel-long-running-safety-harness.md`
- [ ] `docs/architecture/http-long-running.md` — rules: no statics, reset discipline, adapter notes

Configuration:
- [ ] `framework/packages/platform/http/config/http.php`
- [ ] `framework/packages/platform/http/config/rules.php`

Tests:
- [ ] `framework/packages/platform/http/tests/Integration/LongRunning100SequentialRequestsNoLeakTest.php`
- [ ] `framework/packages/platform/http/tests/Integration/ResetIsCalledBetweenRequestsTest.php`
- [ ] `framework/packages/platform/http/tests/Contract/HttpConfigSubtreeShapeContractTest.php`
  - [ ] MUST fail if `config/http.php` repeats root (`['http'=>...]` is forbidden; subtree only)
  - [ ] MUST fail if any key starting with `@` exists under returned subtree (any depth)

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0018-kernel-long-running-safety-harness.md`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/http/config/http.php`
- [ ] Keys (dot):
  - [ ] `http.long_running.span_attr_enabled` = true
    - [ ] controls only optional span attribute emission
    - [ ] MUST NOT enable/disable long-running safety, reset discipline, or harness behavior
    - [ ] long-running safety remains unconditional when a long-running HTTP runtime adapter is used
  - [ ] `http.middleware.auto.enabled` = true
- [ ] Feature toggles referenced by platform/http-owned middleware (must exist; defaults owned by `platform/http`):
  - [ ] `http.request_id.enabled`
  - [ ] `http.proxy.enabled`
  - [ ] `http.context.enrich.enabled`
  - [ ] `http.hosts.enabled`
  - [ ] `http.https_redirect.enabled`
  - [ ] `http.cors.enabled`
  - [ ] `http.method_override.enabled`
  - [ ] `http.negotiation.enabled`
  - [ ] `http.request.json.enabled`
  - [ ] `http.rate_limit.early.enabled`
  - [ ] `http.rate_limit.enabled`
  - [ ] `http.cache_headers.enabled`
  - [ ] `http.etag.enabled`
  - [ ] `http.compression.enabled`
  - [ ] `http.security_headers.enabled`
  - [ ] `http.maintenance.enabled`
  - [ ] `http.debug.endpoints.enabled`
  - [ ] alignment rule:
    - [ ] this list MUST cover every `platform/http`-owned middleware in `docs/ssot/http-middleware-catalog.md` whose placement is conditional on an `if enabled` rule
  - [ ] Clarification:
    - [ ] these keys are seeded here as `platform/http`-owned configuration surface only
    - [ ] `1.290.0` MUST NOT imply that every listed toggle already has a full middleware/runtime implementation in this epic
    - [ ] concrete behavior for individual HTTP features is introduced by later HTTP epics
  - [ ] Canonical split alignment (locked by the HTTP middleware taxonomy):
    - [ ] `http.rate_limit.early.enabled` controls the canonical `EarlyRateLimitMiddleware` in `http.middleware.system_pre`
    - [ ] `http.rate_limit.enabled` controls the canonical `RateLimitMiddleware` in `http.middleware.app_pre`
- [ ] Rules:
  - [ ] `framework/packages/platform/http/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Owner tag constants only:
  - [ ] `framework/packages/platform/http/src/Provider/Tags.php` declares all canonical `http.middleware.*` slot tag constants
- [ ] This epic does NOT yet require runtime tag consumption/wiring; it only establishes owner-package constants per `1.10.0`
- [ ] These constants are the canonical public owner constants for the reserved `http.middleware.*` slot tags.
- [ ] Any package that is allowed to depend on `platform/http` and needs HTTP middleware slot tags in runtime code MUST use the owner constants from `platform/http`, not local mirror constants.
- [ ] Raw literal tag strings remain allowed only in docs/tests/fixtures for readability and MUST NOT be the preferred runtime-code pattern.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] via `Coretsia\Contracts\Context\ContextAccessorInterface` (for leak detection / invariants)
- Context writes (safe only):
  - N/A
- [ ] Reset discipline:
  - [ ] relies on Foundation reset orchestration between requests

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] optional attribute `long_running=true` (attribute only, not label)
- Metrics/Logs:
  - N/A

#### Errors

N/A

#### Security / Redaction

- [ ] Long-running harness MUST enforce “no leak” without capturing secrets:
  - [ ] MUST NOT dump ContextStore values (only key presence/absence or safe ids)
  - [ ] MUST NOT include absolute paths in assertion messages
  - [ ] MUST NOT include raw headers/cookies/Authorization in test failure output
  - [ ] Allowed diagnostics: key names, stable counters, `hash(value)` / `len(value)` when necessary

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [ ] if effective reset discovery is used → `framework/packages/platform/http/tests/Integration/ResetIsCalledBetweenRequestsTest.php`
- [ ] If long-running no-leak promised → `framework/packages/platform/http/tests/Integration/LongRunning100SequentialRequestsNoLeakTest.php`

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/platform/http/tests/Contract/HttpConfigSubtreeShapeContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/http/tests/Integration/LongRunning100SequentialRequestsNoLeakTest.php`
  - [ ] `framework/packages/platform/http/tests/Integration/ResetIsCalledBetweenRequestsTest.php`
- [ ] Add policy/contract assertions inside existing integration tests:
  - [ ] `LongRunning100SequentialRequestsNoLeakTest` MUST assert:
    - ContextStore is empty (or contains only base keys) at the start of each request
    - After request → reset executed → no previous request keys remain
  - [ ] `ResetIsCalledBetweenRequestsTest` MUST assert:
    - [ ] `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator::resetAll()` is invoked exactly once per request UoW (single-choice) OR
    - [ ] reset orchestration is observed after each request and before the next beginUoW
      (if exact call count is not directly observable)
- Gates/Arch:
  - N/A

### DoD (MUST)

- [ ] tests green
- [ ] no new deps introduced
- [ ] docs complete:
  - [ ] `docs/architecture/http-long-running.md`
  - [ ] `docs/adr/ADR-0018-kernel-long-running-safety-harness.md`

---

### 1.300.0 Kernel boot: Bootstrap Phase A (env policy + minimal inputs) (MUST) [IMPL]

---
type: package
phase: 1
epic_id: "1.300.0"
owner_path: "framework/packages/core/kernel/"

package_id: "core/kernel"
composer: "coretsia/core-kernel"
kind: runtime
module_id: "core.kernel"

goal: "Kernel може зробити Phase A boot deterministic і safe на будь-якому середовищі, навіть без жодних skeleton config файлів."
provides:
- "Bootstrap Phase A: env policy + dotenv + minimal overrides (optional)"
- "Boot without skeleton/config/* (bare skeleton safe)"
- "Deterministic precedence: strict_dotenv vs allow_system"
- "Deterministic app target selection (`web|api|console|worker`) as a minimal boot input"
- "Selected app target resolves the app root under `skeleton/apps/<app>/` without filesystem scanning"
- "App target selection is entrypoint-owned input; it is NOT inferred by probing `skeleton/apps/*`"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0023-kernel-bootstrap-phase-a.md"
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.20.0 — Config roots registry exists and includes root `kernel` owned by `core/kernel`
  - 1.80.0 — config/env contracts exist (`EnvRepositoryInterface`, `EnvPolicy`)
  - 1.270.0 — core/kernel package skeleton exists (`KernelModule`, `KernelServiceProvider`, `KernelServiceFactory`, `config/kernel.php`, `config/rules.php`)

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/` — `EnvRepositoryInterface`, `EnvPolicy`

- Terminology note (MUST): config root vs config key namespaces
  - Config root for Kernel is **`kernel`** (file: `framework/packages/core/kernel/config/kernel.php`).
  - Any dotted prefixes like `kernel.uow.*`, `kernel.runtime.*`, `kernel.modules.*`, `kernel.config.*`, `kernel.artifacts.*`, `kernel.fingerprint.*`
    are **config key namespaces**, not separate roots.
  - `config/<name>.php` MUST return subtree for `<name>` (no wrapper array repeating the root key).

- Required contracts / ports:
  - `Coretsia\Contracts\Env\EnvRepositoryInterface`
  - `Coretsia\Contracts\Env\EnvPolicy`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/*`

#### Uses ports (API surface, NOT deps) (optional)

- Foundation stable APIs:
  - `Psr\Clock\ClockInterface`

### Entry points / integration points (MUST)

- CLI:
  - `coretsia doctor|config:debug|config:compile` → owner `platform/cli` (calls Phase A)
- Artifacts:
  - N/A

### Reset discipline (kernel.reset) — NOT USED in Phase A boot (MUST)

- Bootstrap Phase A MUST **NOT** trigger reset and MUST **NOT** interact with:
  - `kernel.reset` tag
  - `TagRegistry`
  - `ResetOrchestrator`
- Rationale (cemented boundary):
  - reset is a **UoW lifecycle** concern (KernelRuntime 1.280.0), not a boot concern.

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/kernel/src/Boot/DotenvLoader.php` — loads dotenv according to EnvPolicy (safe)
- [ ] `framework/packages/core/kernel/src/Boot/EnvRepositoryBuilder.php` — builds EnvRepositoryInterface (policy precedence)
- [ ] `framework/packages/core/kernel/src/Boot/BootstrapOverridesLoader.php` — reads optional bootstrap-only overrides:
  - [ ] `skeleton/config/app.php`
  - [ ] this file is a Phase A bootstrap-only input, NOT a reserved config root, and MUST NOT participate in Phase B config merge
  - [ ] allowed override scope is single-choice:
    - [ ] `appEnv`
    - [ ] `preset`
    - [ ] `debug`
  - [ ] `skeleton/config/modules.php` MUST NOT be read here
  - [ ] module enable/disable composition is resolved only by `1.310.0` ModulePlan from preset files + composer metadata
- [ ] `framework/packages/core/kernel/src/Boot/BootstrapConfig.php` — VO `{appEnv,preset,debug,envPolicy,...}`
- [ ] `framework/packages/core/kernel/src/Boot/Exception/BootstrapException.php` — errorCode `CORETSIA_BOOTSTRAP_FAILED`
- [ ] `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`
- [ ] `framework/packages/core/kernel/config/kernel.php` — adds boot/env policy defaults
- [ ] `framework/packages/core/kernel/config/rules.php` — enforces shape
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php`
  - [ ] registers Phase A boot services:
    - [ ] `DotenvLoader`
    - [ ] `EnvRepositoryBuilder`
    - [ ] `BootstrapOverridesLoader`
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php`
  - [ ] deterministic factory wiring for Phase A boot services
  - [ ] MUST NOT keep mutable runtime state

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/core/kernel/config/kernel.php`
- [ ] Keys (dot):
  - [ ] `kernel.boot.default_env` = "local"
  - [ ] `kernel.boot.default_preset` = "micro"
  - [ ] `kernel.boot.default_debug` = false
  - [ ] `kernel.env.policy.default_local` = "strict_dotenv"
  - [ ] `kernel.env.policy.default_production` = "allow_system"
  - [ ] `kernel.env.dotenv.files` = [".env",".env.local",".env.<env>",".env.<env>.local"]
- [ ] Rules:
  - [ ] `framework/packages/core/kernel/config/rules.php` enforces shape

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [ ] Logs:
  - [ ] boot errors log only keys + hashes/len; never dotenv values

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] `.env` values, secrets, tokens
- [ ] Allowed:
  - [ ] dotenv file names (normalized), hash/len (never raw)

### Phase 0 parity: safe deterministic failures (MUST)

Bootstrap Phase A MUST дотримуватись PHASE 0 SPIKES safety/determinism принципів (0.20.0):

- MUST NOT leak:
  - dotenv values
  - absolute machine paths
  - OS error messages that may contain paths/secrets

#### Error message policy (single-choice)

Усі Bootstrap винятки/повідомлення MUST бути:
- стабільні (fixed reason tokens)
- без абсолютних шляхів
- без `KEY=VALUE` patterns для секретів (TOKEN/PASSWORD/AUTH/COOKIE)

Allowed diagnostics:
- normalized dotenv *file names* (без абсолютних шляхів)
- `hash(value)` / `len(value)` (тільки якщо друкує platform/cli, не kernel)

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] Redaction does not leak:
  - [ ] `framework/packages/core/kernel/tests/Integration/BootstrapDotenvRespectedUnderStrictPolicyTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/BootstrapSystemEnvOverridesDotenvUnderAllowSystemPolicyTest.php`

### Tests (MUST)

- Integration:
  - [ ] `framework/packages/core/kernel/tests/Integration/BootstrapWorksWithoutAnySkeletonConfigFilesTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/BootstrapDotenvRespectedUnderStrictPolicyTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/BootstrapSystemEnvOverridesDotenvUnderAllowSystemPolicyTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/KernelRuntimeDoesNotWriteToStdoutTest.php`
    - [ ] token-scan `framework/packages/core/kernel/src/Runtime/**` and `src/Provider/**`
    - [ ] MUST fail on `echo|print|var_dump|print_r|printf|error_log`
    - [ ] MUST fail on `STDOUT|STDERR`, `php://stdout`, `php://stderr`, `php://output`
    - [ ] tests/fixtures are excluded

### DoD (MUST)

- [ ] Phase A boot succeeds without skeleton config
- [ ] Env policy precedence matches SSoT and is tested
- [ ] Bootstrap Phase A includes deterministic app target selection:
  - [ ] selected app is an explicit input (`web|api|console|worker`)
  - [ ] selected app root resolves to `skeleton/apps/<app>/`
  - [ ] bootstrap MUST NOT scan `skeleton/apps/*` to auto-detect the app
- [ ] No secret leakage in error paths
- [ ] Non-goals / out of scope:
  - [ ] Phase A НЕ читає merged config через ConfigKernel (Phase B).
    Phase A uses only EnvRepository + optional bootstrap overrides; internal defaults MUST be deterministic
    and MUST match the package defaults declared later under `kernel.boot.*` / `kernel.env.*` keys.
  - [ ] Phase A не сканує filesystem packages; module discovery — лише через composer metadata (епік module plan).

---

### 1.310.0 Kernel: Module Plan (discovery + presets + graph + policies) (MUST) [IMPL]

---
type: package
phase: 1
epic_id: "1.310.0"
owner_path: "framework/packages/core/kernel/"

package_id: "core/kernel"
composer: "coretsia/core-kernel"
kind: runtime
module_id: "core.kernel"

goal: "Для однакових inputs (composer metadata + preset file) kernel завжди будує один і той самий ModulePlan з детермінованими правилами missing/conflicts і стабільними кодами."
provides:
- "Metadata-only discovery of installed modules via composer installed metadata (no runtime scans)"
- "Preset load order: skeleton override → framework defaults"
- "Deterministic ModulePlan: enabled/disabled/optionalMissing/warnings + topo order + cycle detection"
- "Deterministic policy: conflicts → hard fail; required missing → hard fail; optional missing → warning"
- "Stable deterministic error codes for CI/CLI/debug for conflicts/missing"
- "ModulePlan warnings contain optionalMissing as non-fatal"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr:
- "docs/adr/ADR-0024-kernel-module-plan-resolution.md"
- "docs/adr/ADR-0025-kernel-conflicts-optional-missing-policy.md"

ssot_refs:
- "docs/ssot/modules-and-manifests.md"
- "docs/ssot/modes.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.20.0 — Config roots registry exists and includes root `kernel` owned by `core/kernel`
  - 1.70.0 — module contracts exist (`ManifestReaderInterface`, `ModePresetLoaderInterface`, `ModePresetInterface`, `ModuleDescriptor`)
  - 1.300.0 — Bootstrap Phase A визначає preset + env inputs

- Terminology note (MUST): config root vs config key namespaces
  - Config root for Kernel is **`kernel`** (file: `framework/packages/core/kernel/config/kernel.php`).
  - Any dotted prefixes like `kernel.uow.*`, `kernel.runtime.*`, `kernel.modules.*`, `kernel.config.*`, `kernel.artifacts.*`, `kernel.fingerprint.*`
    are **config key namespaces**, not separate roots.
  - `config/<name>.php` MUST return subtree for `<name>` (no wrapper array repeating the root key).

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/` — module contracts (ManifestReader/ModePreset)

- Required contracts / ports:
  - `Coretsia\Contracts\Module\ManifestReaderInterface`
  - `Coretsia\Contracts\Module\ModePresetLoaderInterface`
  - `Coretsia\Contracts\Module\ModePresetInterface`
  - `Coretsia\Contracts\Module\ModuleDescriptor`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/*`
- `integrations/*` (kernel reads metadata; does not import integration code)

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Module\ManifestReaderInterface`
  - `Coretsia\Contracts\Module\ModePresetLoaderInterface`
  - `Coretsia\Contracts\Module\ModePresetInterface`
  - `Coretsia\Contracts\Module\ModuleDescriptor`
- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - `coretsia debug:modules` → `platform/cli` `.../DebugModulesCommand.php` (reads ModulePlan)
  - `coretsia config:compile` → uses ModulePlan as artifacts input
- Artifacts:
  - writes (via artifacts epic): `skeleton/var/cache/<appId>/module-manifest.php`
- Policy surface:
  - missing/conflict classification MUST be performed only here (kernel-owned), surfaced by adapters later

### Deliverables (MUST)

#### Creates

Discovery + preset loading + graph:
- [ ] `framework/packages/core/kernel/src/Module/ComposerManifestReader.php` — ManifestReaderInterface via composer metadata
- [ ] `framework/packages/core/kernel/src/Module/FilesystemModePresetLoader.php` — load: skeleton override → framework default
- [ ] `framework/packages/core/kernel/src/Module/ModePresetSchemaValidator.php` — schemaVersion/name/list-like + micro/express rules
- [ ] `framework/packages/core/kernel/src/Module/ModuleGraphResolver.php` — preset required/optional/disabled + requires/conflicts + classification hooks
- [ ] `framework/packages/core/kernel/src/Module/TopologicalSorter.php` — deterministic topo sort + cycle detection
- [ ] `framework/packages/core/kernel/src/Module/ModulePlan.php` — stable payload shape for artifacts/debug (+ warnings storage)
- [ ] `framework/packages/core/kernel/src/Module/ModulePlanResolver.php` — Plan resolution entrypoint (single brain):
  - [ ] Single-choice module selection inputs:
    - [ ] `BootstrapConfig.preset` selected in Phase A
    - [ ] mode files (`skeleton/config/modes/*.php` override → framework defaults)
    - [ ] composer metadata discovery
    - [ ] selected app target from Phase A (`BootstrapConfig.app`) MAY affect app-root resolution for app-local config/bootstrap,
      but MUST NOT introduce a parallel module-selection source
    - [ ] forbidden parallel module-selection paths:
      - [ ] `skeleton/config/modules.php`
      - [ ] `skeleton/apps/*/config/modules.php`
  - [ ] orchestrates:
    - [ ] preset load (skeleton override → framework default)
    - [ ] app-root resolution from selected app target (`skeleton/apps/<app>/`)
    - [ ] schema validation
    - [ ] composer metadata discovery
    - [ ] graph resolve + topo sort
    - [ ] returns a deterministic `ModulePlan` (same inputs → same plan)

- [ ] `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
- [ ] `docs/adr/ADR-0025-kernel-conflicts-optional-missing-policy.md`

Framework default presets:
- [ ] `framework/packages/core/kernel/resources/modes/micro.php`
- [ ] `framework/packages/core/kernel/resources/modes/express.php`
- [ ] `framework/packages/core/kernel/resources/modes/hybrid.php`
- [ ] `framework/packages/core/kernel/resources/modes/enterprise.php`

Deterministic exceptions (resolution + policy):
- [ ] `framework/packages/core/kernel/src/Module/Exception/ModePresetNotFoundException.php` — `CORETSIA_MODE_PRESET_NOT_FOUND`
- [ ] `framework/packages/core/kernel/src/Module/Exception/ModePresetInvalidException.php` — `CORETSIA_MODE_PRESET_INVALID`
- [ ] `framework/packages/core/kernel/src/Module/Exception/ModuleCycleDetectedException.php` — `CORETSIA_MODULE_CYCLE_DETECTED`
- [ ] `framework/packages/core/kernel/src/Module/Exception/ModuleConflictException.php` — `CORETSIA_MODULE_CONFLICT`
- [ ] `framework/packages/core/kernel/src/Module/Exception/ModuleRequiredMissingException.php` — `CORETSIA_MODULE_REQUIRED_MISSING`

Warnings (non-fatal):
- [ ] `framework/packages/core/kernel/src/Module/Warning/ModuleOptionalMissingWarning.php` — `CORETSIA_MODULE_OPTIONAL_MISSING`

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
  - [ ] `docs/adr/ADR-0025-kernel-conflicts-optional-missing-policy.md`
- [ ] `framework/packages/core/kernel/config/kernel.php` — adds modules/modes config defaults
- [ ] `framework/packages/core/kernel/config/rules.php` — enforces shape
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php` — registers:
  - [ ] `ModulePlanResolver`
  - [ ] `ComposerManifestReader`
  - [ ] `FilesystemModePresetLoader`
  - [ ] `ModePresetSchemaValidator`
  - [ ] `ModuleGraphResolver`
  - [ ] `TopologicalSorter`
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).

### Configuration (keys + defaults) (MUST)

- Files:
  - [ ] `framework/packages/core/kernel/config/kernel.php`
- Keys (dot):
  - [ ] `kernel.modules.discovery.source` = "composer"
  - [ ] `kernel.modes.defaults_path` = "framework/packages/core/kernel/resources/modes"
  - [ ] `kernel.modes.overrides_path` = "skeleton/config/modes"
  - [ ] `kernel.modes.schema_version` = 1
- Rules:
  - [ ] `framework/packages/core/kernel/config/rules.php` enforces shape

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [ ] Metrics:
  - [ ] `kernel.modules_resolve_total` (labels: `outcome`)
  - [ ] `kernel.modules_resolve_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] warnings about optionalMissing/conflicts redacted (only moduleIds)
  - [ ] no paths/secrets

#### Errors

- [ ] Deterministic exception codes:
  - [ ] `CORETSIA_MODE_PRESET_NOT_FOUND`
  - [ ] `CORETSIA_MODE_PRESET_INVALID`
  - [ ] `CORETSIA_MODULE_CYCLE_DETECTED`
  - [ ] `CORETSIA_MODULE_CONFLICT`
  - [ ] `CORETSIA_MODULE_REQUIRED_MISSING`
- [ ] Deterministic warning code:
  - [ ] `CORETSIA_MODULE_OPTIONAL_MISSING`
- [ ] Mapping:
  - [ ] adapters/CLI map later via `platform/errors`

### Phase 0 parity: deterministic ordering + safe diagnostics (MUST)

ModulePlan MUST бути детермінованим у сенсі PHASE 0 SPIKES:

- будь-які списки/плани/графи/попередження:
  - stable sort by byte-order (`strcmp`)
  - locale-independent

#### Safe diagnostics invariant (single-choice)

В diagnostics (exceptions/warnings) MUST NOT бути:
- абсолютних шляхів
- фрагментів filesystem layout
- секретів/PII

Allowed:
- moduleId, presetName, stable reason tokens, stable error code.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] Deterministic topo/cycle:
  - [ ] `framework/packages/core/kernel/tests/Unit/GraphCycleDetectionTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/TopologicalSorterDeterministicOrderTest.php`
- [ ] Stable plan shape:
  - [ ] `framework/packages/core/kernel/tests/Contract/ModulePlanShapeContractTest.php`
- [ ] `framework/packages/core/kernel/tests/Contract/ModulePlanWarningsAreDeterministicallySortedContractTest.php`
  - [ ] asserts `optionalMissing` and any warnings collections are sorted deterministically by canonical key using byte-order `strcmp`
  - [ ] asserts locale does not affect ordering
- [ ] Deterministic behaviors (missing/conflicts policy):
  - [ ] `framework/packages/core/kernel/tests/Integration/OptionalMissingDoesNotFailTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/RequiredMissingFailsDeterministicallyTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/ModuleConflictsFailDeterministicallyTest.php`

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/core/kernel/tests/Unit/GraphCycleDetectionTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/TopologicalSorterDeterministicOrderTest.php`
- Contract:
  - [ ] `framework/packages/core/kernel/tests/Contract/ModulePlanShapeContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/ModulePlanWarningsAreDeterministicallySortedContractTest.php`
- Integration:
  - [ ] `framework/packages/core/kernel/tests/Integration/ModePresetAppliesRequiredOptionalDisabledTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/ModePresetSchemaValidatorEnforcesMicroAndExpressRulesTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/OptionalMissingDoesNotFailTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/RequiredMissingFailsDeterministicallyTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/ModuleConflictsFailDeterministicallyTest.php`

### DoD (MUST)

- [ ] Module discovery is metadata-only (no runtime scans)
- [ ] Mode preset load order correct + tested (skeleton override → framework default)
- [ ] Deterministic ModulePlan (stable ordering; rerun produces identical plan)
- [ ] Policy implemented + tested:
  - [ ] conflicts → hard fail (`CORETSIA_MODULE_CONFLICT`)
  - [ ] required missing → hard fail (`CORETSIA_MODULE_REQUIRED_MISSING`)
  - [ ] optional missing → warning (`CORETSIA_MODULE_OPTIONAL_MISSING`) and does not fail
- [ ] Failures are deterministic across runs/OS
- [ ] `platform/cli` surfaces these failures deterministically in JSON output
- [ ] Presets (micro/express/hybrid/enterprise) існують як framework defaults у `core/kernel/resources/modes/*.php`
- [ ] CLI `debug:modules` (owner `platform/cli`) показує ModulePlan
- [ ] Express preset у Phase 0/1 може падати на required missing — це очікувано
- [ ] Non-goals / out of scope:
  - [ ] Жодного filesystem scan `framework/packages/**` у runtime.
  - [ ] Kernel не інстанціює module classes для discovery; only metadata.
  - [ ] Не включає bundles (окремий ADR/Phase 6+).
- [ ] When a preset lists an optional module that is not installed yet,
  then ModulePlan contains it in `optionalMissing` (as warning) and does not fail.
- [ ] When a preset requires a module not installed, then kernel throws
  `CORETSIA_MODULE_REQUIRED_MISSING` deterministically.
- [ ] Any preset that enables `core/kernel` runtime MUST also enable `core/foundation`.
  - [ ] Rationale: KernelRuntime requires Foundation services (ContextStore/TagRegistry/ResetOrchestrator).
- [ ] Kernel Module metadata (via ModulePlan) MUST reflect this as a **required module dependency**:
  - [ ] `core.kernel` requires `core.foundation`
- [ ] `core/kernel` still MUST NOT own `kernel.reset`; it only triggers `ResetOrchestrator` at runtime.

---

### 1.320.0 Kernel: ConfigKernel Phase B + SSoT Docs (directives + merge order + precedence) (MUST) [IMPL+DOC]

---
type: package
phase: 1
epic_id: "1.320.0"
owner_path: "framework/packages/core/kernel/"

package_id: "core/kernel"
composer: "coretsia/core-kernel"
kind: runtime
module_id: "core.kernel"

goal: "Kernel детерміновано збирає фінальний config + safe explain trace (без секретів) і має канонічні SSoT-доки (directives приклади + merge order + precedence matrix), що відповідають реалізації."
provides:
- "Deterministic merge defaults/overrides/overlays"
- "Directives per-file before merge: @append/@prepend/@remove/@merge/@replace"
- "Safe explain trace (source tracking without secrets)"
- "Reserved namespaces guard"
- "SSoT docs: directives examples (без дублювання правил) + merge order + precedence matrix (відтворювані без читання коду)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr:
- "docs/adr/ADR-0026-config-kernel-merge-directives-reserved-namespaces.md"

ssot_refs:
- "docs/ssot/config-directives.md"
- "docs/ssot/config-merge-order.md"
- "docs/ssot/config-precedence-matrix.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.20.0 — Config roots registry exists and includes root `kernel` owned by `core/kernel`
  - 1.80.0 — config/env contracts exist (`ConfigRepositoryInterface`, `ConfigLoaderInterface`, `MergeStrategyInterface`, `ConfigValidatorInterface`, `ConfigSourceType`, `ConfigValueSource`, `EnvRepositoryInterface`, `EnvPolicy`)
  - 1.310.0 — ModulePlan used to load package defaults from enabled modules
  - 1.300.0 — Env repository/policy inputs for overlays

- Terminology note (MUST): config root vs config key namespaces
  - Config root for Kernel is **`kernel`** (file: `framework/packages/core/kernel/config/kernel.php`).
  - Any dotted prefixes like `kernel.uow.*`, `kernel.runtime.*`, `kernel.modules.*`, `kernel.config.*`, `kernel.artifacts.*`, `kernel.fingerprint.*`
    are **config key namespaces**, not separate roots.
  - `config/<name>.php` MUST return subtree for `<name>` (no wrapper array repeating the root key).

- Required deliverables (exact paths):
  - `framework/tools/spikes/config_merge/tests/fixtures/scenarios.php` — spike fixtures for compatibility locks

- Required contracts / ports:
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface`
  - `Coretsia\Contracts\Config\ConfigLoaderInterface`
  - `Coretsia\Contracts\Config\MergeStrategyInterface`
  - `Coretsia\Contracts\Config\ConfigValidatorInterface`
  - `Coretsia\Contracts\Config\ConfigSourceType`
  - `Coretsia\Contracts\Config\ConfigValueSource`
  - `Coretsia\Contracts\Env\EnvRepositoryInterface`
  - `Coretsia\Contracts\Env\EnvPolicy`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/*`
- `devtools/*` (включно `devtools/internal-toolkit`, `devtools/cli-spikes`)
- `framework/tools/**` (runtime code MUST NOT залежати від spikes/tools; дозволено тільки test-time fixtures reads у Contract tests)
- `Coretsia\Tools\Spikes\*` (runtime import forbidden; test-only)

#### Uses ports (API surface, NOT deps) (optional)

- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - `coretsia config:validate|config:debug|config:compile` → owner `platform/cli`
- Artifacts:
  - writes (via artifacts epic): `skeleton/var/cache/<appId>/config.php`
- Docs:
  - SSoT docs MUST match implementation + tests (no “creative” divergence)

### Deliverables (MUST)

#### Creates

Kernel config core:
- [ ] `framework/packages/core/kernel/src/Config/ConfigKernel.php` — orchestrates loaders + directives + merge + validate + explain
- [ ] `framework/packages/core/kernel/src/Config/ConfigMerger.php` — deterministic merge
- [ ] `framework/packages/core/kernel/src/Config/DirectiveProcessor.php` — per-file directive processing + typing rules
- [ ] `framework/packages/core/kernel/src/Config/Explain/ConfigExplainer.php` — source tracking (no secrets)
- [ ] `framework/packages/core/kernel/src/Config/Validation/ConfigNamespaceGuard.php` — reserved namespaces guard

Loaders:
- [ ] `framework/packages/core/kernel/src/Config/Loaders/PackageDefaultsConfigLoader.php`
- [ ] `framework/packages/core/kernel/src/Config/Loaders/SkeletonConfigLoader.php`
- [ ] `framework/packages/core/kernel/src/Config/Loaders/EnvironmentOverlayLoader.php`

Errors:
- [ ] `framework/packages/core/kernel/src/Config/Exception/ConfigInvalidException.php` — `CORETSIA_CONFIG_INVALID`
- [ ] `framework/packages/core/kernel/src/Config/Exception/ConfigReservedNamespaceException.php` — `CORETSIA_CONFIG_RESERVED_NAMESPACE_USED`
- [ ] `framework/packages/core/kernel/src/Config/Exception/ConfigDirectiveMixedLevelException.php` — `CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL`
- [ ] `framework/packages/core/kernel/src/Config/Exception/ConfigDirectiveTypeMismatchException.php` — `CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH`

SSoT docs (canonical):
- [ ] `docs/adr/ADR-0026-config-kernel-merge-directives-reserved-namespaces.md`
- [ ] `docs/ssot/config-directives.md` — examples for @append/@prepend/@remove/@merge/@replace (no rule duplication)
- [ ] `docs/ssot/config-merge-order.md` — merge order narrative (directives before merge; overlays win; validate after merge)
- [ ] `docs/ssot/config-precedence-matrix.md` — precedence matrix (sourceType + priority)

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/config-directives.md`
  - [ ] `docs/ssot/config-merge-order.md`
  - [ ] `docs/ssot/config-precedence-matrix.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0026-config-kernel-merge-directives-reserved-namespaces.md`
- [ ] `framework/packages/core/kernel/config/kernel.php` — adds config kernel keys
- [ ] `framework/packages/core/kernel/config/rules.php` — enforces shape
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php`
  - [ ] registers:
    - [ ] `ConfigKernel`
    - [ ] `ConfigMerger`
    - [ ] `DirectiveProcessor`
    - [ ] `ConfigExplainer`
    - [ ] `ConfigNamespaceGuard`
    - [ ] `PackageDefaultsConfigLoader`
    - [ ] `SkeletonConfigLoader`
    - [ ] `EnvironmentOverlayLoader`

- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php`
  - [ ] deterministic factory wiring for ConfigKernel services
  - [ ] MUST NOT keep mutable runtime state

### Configuration (keys + defaults) (MUST)

- Files:
  - [ ] `framework/packages/core/kernel/config/kernel.php`
- Keys (dot):
  - [ ] `kernel.config.forbidden_top_level_roots` = ["coretsia","_internal"]
    - Optional hardening (MAY, if you truly need it later)
    - MUST NOT include "kernel" or "foundation" because apps must be able to configure those roots.
- Rules:
  - [ ] `framework/packages/core/kernel/config/rules.php` enforces shape

- `ConfigKernel` and explain capability are baseline kernel facilities and MUST NOT be feature-disabled via config.
- Whether explain is produced is decided by the caller/entrypoint, not by a runtime feature flag.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `kernel.config.merge`
  - [ ] `kernel.config.explain`
- [ ] Metrics:
  - [ ] `kernel.config_merge_total` (labels: `outcome`)
  - [ ] `kernel.config_merge_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] only safe metadata: normalized file paths, key paths, directive names; no values

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] dotenv values, passwords, tokens, DSNs
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` only (printed by CLI layer), never raw
  - [ ] normalized file paths + key paths + directive names (no raw values)

### Phase 0 parity: directives + precedence + explain (MUST)

Цей епік MUST бути семантично еквівалентним PHASE 0 SPIKE:
- 0.90.0 Two-phase config merge + directives + explain prototype

#### Directive allowlist (cemented)
Allowlist MUST бути EXACT:
- `@append`, `@prepend`, `@remove`, `@merge`, `@replace`

#### Directive allowlist ownership (cemented)

The directive allowlist is kernel-owned and MUST NOT be configurable via runtime config.
The canonical set is fixed and exact:

- `@append`
- `@prepend`
- `@remove`
- `@merge`
- `@replace`

#### Reserved namespace guard for "@*" (cemented; single-choice)
Будь-який key, що починається з `@`, є RESERVED тільки для directives.

- дозволені тільки allowlist directives
- будь-який інший `@*` key MUST fail детерміновано

#### Exclusive-level rule (cemented)
Якщо map на певному рівні містить хоча б один `@*` key:
- на цьому рівні MUST бути **тільки directives keys**
- MUST бути **рівно один** directive key

#### Typing rules (cemented; incl empty-array rule)
- `@append|@prepend|@remove`: value MUST be list
- `@merge`: value MUST be map
- `@replace`: value може бути scalar/list/map

Empty array rule (cemented):
- `[]` приймається як порожній контейнер і трактується контекстом:
  - list directives → empty list
  - `@merge` → empty map

Classification (cemented):
- non-empty: `array_is_list` визначає list vs map
- locale MUST NOT впливати

#### Two-phase semantics (cemented)
- Phase A (per-file): parse/allowlist/type-validate/normalize directives
- Phase B (merge-time): apply normalized directives коли base вже відомий

#### Error precedence (cemented; single-choice)
- якщо існує unknown `@*` → MUST fail as RESERVED NAMESPACE violation
- else якщо порушено exclusive-level (mixing або multi-directive) → MUST fail as MIXED LEVEL
- else якщо type mismatch → MUST fail as TYPE MISMATCH

#### Error codes alignment (cemented; single-choice)

Kernel ConfigKernel MUST використовувати ті самі code strings, що в spike (0.90.0):

- `CORETSIA_CONFIG_RESERVED_NAMESPACE_USED`
- `CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL`
- `CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH`

Ці коди є “line-of-truth” для contract locks і CI.

Будь-яка майбутня зміна semantics MUST супроводжуватись оновленням spike fixtures + contract locks в тому ж PR.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] Directives semantics:
  - [ ] `framework/packages/core/kernel/tests/Unit/DirectivesAppendRemoveListLikeOnlyTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/DirectivesMergeMapLikeOnlyTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/DirectivesExclusiveLevelTest.php`

- [ ] Precedence + explain:
  - [ ] `framework/packages/core/kernel/tests/Integration/ConfigPrecedenceMatrixTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/ConfigExplainSmokeIntegrationTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/ConfigExplainShowsPackageDefaultWhenNoSkeletonOverridesTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/ConfigExplainReturnsStableSourceTypesTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/ReservedNamespaceWriteGuardTest.php`

- [ ] Spike compatibility locks:
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeConfigMergeCompatibilityContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeConfigExplainTraceCompatibilityContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeConfigExplainTraceIsSafeContractTest.php`

- [ ] Docs are verified by matching tests (no separate “doc tests” required, but docs MUST be consistent):
  - [ ] `docs/ssot/config-directives.md` verified by directive unit tests above
  - [ ] `docs/ssot/config-merge-order.md` + `docs/ssot/config-precedence-matrix.md` verified by `ConfigPrecedenceMatrixTest`

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/core/kernel/tests/Unit/DirectivesAppendRemoveListLikeOnlyTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/DirectivesMergeMapLikeOnlyTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/DirectivesExclusiveLevelTest.php`

- Contract:
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeConfigMergeCompatibilityContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeConfigExplainTraceCompatibilityContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeConfigExplainTraceIsSafeContractTest.php`

- Integration:
  - [ ] `framework/packages/core/kernel/tests/Integration/ConfigPrecedenceMatrixTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/ConfigExplainSmokeIntegrationTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/ConfigExplainShowsPackageDefaultWhenNoSkeletonOverridesTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/ConfigExplainReturnsStableSourceTypesTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/ReservedNamespaceWriteGuardTest.php`

### DoD (MUST)

- [ ] Directives semantics match SSoT + unit tests
- [ ] Precedence matrix data-driven test green
- [ ] Explain trace safe + stable source types
- [ ] Reserved namespaces are enforced and tested
- [ ] Fully compatible with spike semantics (0.90.0) — confirmed by contract locks
- [ ] Any semantic change requires updating spike fixtures + locks in same PR
- [ ] `docs/ssot/config-directives.md`:
  - [ ] examples відповідають `framework/packages/core/kernel/src/Config/DirectiveProcessor.php`
  - [ ] показує типові use-cases: додати middleware у список, змінити map, прибрати значення
  - [ ] не дублює правила (rules — у коді/SSoT, doc — приклади)
- [ ] `docs/ssot/config-merge-order.md` + `docs/ssot/config-precedence-matrix.md`:
  - [ ] відповідають сценаріям `ConfigPrecedenceMatrixTest`
  - [ ] фіксують порядок: directives per-file before merge, env overlays win, validate після merge
  - [ ] пояснюють: defaults (packages) vs overrides (skeleton) vs overlays (env)
  - [ ] if “runtime overrides” are mentioned, the doc MUST explicitly mark them as reserved/future and NOT part of the active Phase 1 merge pipeline
  - [ ] мають explicit Non-goals / out of scope (1–5 bullets)
- [ ] Example guarantee:
  - [ ] When skeleton override uses `@append` to add a middleware class into `http.middleware.system_pre`,
    then the final merged config contains it in deterministic order and explain shows `directive_applied`.
- [ ] Integration expectations:
  - [ ] `platform/http` споживає merged config (через `config.php` artifact) для:
    - [ ] `http.middleware.auto.*`, `http.middleware.<slot>` lists, toggles (middleware catalog).
  - [ ] `platform/cli config:debug` читає explain trace і друкує тільки redacted value (hash/len) або без value.
- [ ] Non-goals / out of scope (minimum):
  - [ ] Kernel не друкує секрети/values; це робить лише CLI шар з редекцією.
  - [ ] Не вводить нові правила precedence/directives — лише реалізує/документує SSoT.
- [ ] Reset discipline (kernel.reset) — NOT USED in ConfigKernel (MUST)
  - [ ] ConfigKernel Phase B MUST NOT trigger reset.
  - [ ] Config compilation/validation/explain MUST be deterministic and safe without relying on UoW lifecycle.
  - [ ] Rationale: config pipeline is build-time / compile-time; reset is runtime UoW lifecycle (1.280.0).

---

### 1.330.0 Kernel: Artifacts (manifest + config) + fingerprint + cache:verify core (MUST) [IMPL]

---
type: package
phase: 1
epic_id: "1.330.0"
owner_path: "framework/packages/core/kernel/"

package_id: "core/kernel"
composer: "coretsia/core-kernel"
kind: runtime
module_id: "core.kernel"

goal: "`coretsia config:compile` → rerun no diff; `coretsia cache:verify` → dirty=false одразу після compile."
provides:
- "Deterministic artifacts pipeline: module manifest + compiled config + stub container artifact"
- "`routes@1` remains owned by `platform/routing` and is NOT emitted by `core/kernel`."
- "Deterministic fingerprint (cross-OS) + safe explain for cache:verify"
- "Kernel-owned PayloadNormalizer + fingerprint/file-listing pipeline, reusing the canonical Foundation StableJsonEncoder (not from spikes)"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []  # registry rows were introduced by `1.30.0`; this epic materializes kernel-owned artifacts deterministically
adr: "docs/adr/ADR-0028-kernel-artifacts-fingerprint-cache-verify.md"
ssot_refs:
- "docs/ssot/artifacts-and-fingerprint.md" # kernel artifact production + fingerprint behavior
- "docs/ssot/cache-verify.md"
- "docs/ssot/artifacts.md"                 # global artifact law
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.20.0 — Config roots registry exists and includes root `kernel` owned by `core/kernel`
  - 1.30.0 — global artifact envelope/header/schema registry exists (`docs/ssot/artifacts.md`)
  - 1.70.0 — module contracts exist (`ManifestReaderInterface`)
  - 1.80.0 — config contracts exist (`ConfigRepositoryInterface`)
  - 1.200.0 — canonical Foundation `StableJsonEncoder` exists and is reusable by kernel artifact production
  - 1.310.0 — ModulePlan feeds module-manifest builder
  - 1.320.0 — ConfigKernel feeds compiled config builder

- Terminology note (MUST): config root vs config key namespaces
  - Config root for Kernel is **`kernel`** (file: `framework/packages/core/kernel/config/kernel.php`).
  - Any dotted prefixes like `kernel.uow.*`, `kernel.runtime.*`, `kernel.modules.*`, `kernel.config.*`, `kernel.artifacts.*`, `kernel.fingerprint.*`
    are **config key namespaces**, not separate roots.
  - `config/<name>.php` MUST return subtree for `<name>` (no wrapper array repeating the root key).

- Required deliverables (exact paths):
  - `framework/tools/spikes/fixtures/repo_min/**` — fingerprint golden fixtures
  - `framework/tools/spikes/fixtures/payloads_min/payloads.php` — payload/json golden fixtures
  - NOTE (cemented): `framework/tools/spikes/fixtures/**` використовуються тільки як test-time data для Contract locks.
  - Runtime code MUST NOT читати `framework/tools/**` або залежати від spikes.

- Required contracts / ports:
  - `Coretsia\Contracts\Module\ManifestReaderInterface`
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/*`
- `integrations/*`
- `devtools/*` (включно `devtools/internal-toolkit`, `devtools/cli-spikes`)
- `framework/tools/**` (runtime)
- `Coretsia\Tools\Spikes\*` (runtime import forbidden; test-only)

#### Uses ports (API surface, NOT deps) (optional)

- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder`
  - `Coretsia\Foundation\Clock\SystemClock` (via PSR-20 binding)

### Entry points / integration points (MUST)

- CLI:
  - `coretsia config:compile` → `platform/cli` `.../ConfigCompileCommand.php`
  - `coretsia cache:verify`  → `platform/cli` `.../CacheVerifyCommand.php`
- Artifacts:
  - writes:
    - `skeleton/var/cache/<appId>/module-manifest.php`
    - `skeleton/var/cache/<appId>/config.php`
    - `skeleton/var/cache/<appId>/container.php` (stub; REAL in 1.340.0)
  - reads (verify):
    - validates header + payload schema for the same files

- `routes@1` is owned and emitted only by `platform/routing`.
- `core/kernel` MUST NOT emit a stub or placeholder `routes.php` artifact.

### Phase 0 parity: artifacts + fingerprint + payload/json determinism (MUST)

Цей епік є production-реалізацією зацементованих прототипів PHASE 0 SPIKES, тому MUST бути compat-parity із:
- 0.50.0 Deterministic file IO policy (EOL normalization, LF writes, warning-silent, no paths in messages)
- 0.60.0 Fingerprint determinism prototype (stable listing, symlink hard-fail, safe explain)
- 0.70.0 PayloadNormalizer + StableJsonEncoder (map ksort, list preserve, float-forbidden)
- 0.20.0 rails safety (output-free бізнес-логіка; no secrets/PII; deterministic errors)

#### Hard boundary: no Phase 0 tooling deps in runtime (MUST)
`core/kernel` runtime MUST NOT:
- залежати від `devtools/*` (включно `devtools/internal-toolkit`, `devtools/cli-spikes`)
- імпортувати `Coretsia\Tools\Spikes\*`
- читати `framework/tools/**` у runtime execution

Spike fixtures дозволені **тільки** в test-time Contract tests як compatibility locks (читання fixtures як data).

## Required invariants (cemented; single-choice)

### A) Stable JSON bytes (artifacts) (MUST)
The canonical stable JSON encoder is `Coretsia\Foundation\Serialization\StableJsonEncoder`, and kernel artifacts MUST reuse it without redefining the byte rules.

### B) Float forbidden policy (MUST)
Artifacts payload normalization MUST reject:
- any `float`
- `NaN`, `INF`, `-INF`

Failure MUST be deterministic and MUST NOT include raw value.
Diagnostics MAY include only path-to-value token (e.g. `a.b[3].c`) and MUST NOT include the value itself.

### C) Deterministic file listing + symlink hard-fail (MUST)
DeterministicFileLister MUST:
- NOT follow symlinks
- detect symlink before recursion (`isLink()` or equivalent)
- on first symlink → deterministic fail (no partial results)
- emit stable ordering of normalized relative paths (forward slashes) sorted by `strcmp`
- MUST NOT rely on OS locale

### D) EOL normalization + LF-only writes (MUST)
ArtifactWriter (і/або file helpers) MUST:
- normalize EOL (`\r\n` and `\r` → `\n`) for hashing/comparison inputs
- write text artifacts as LF-only and MUST ensure final newline
- produce stable bytes: no timestamps, no tool versions, no absolute paths
- cache:verify compares **content bytes only** (normalized LF bytes); mtime/permissions are NOT compared

### D.1) Bytes-only artifact semantics (no mtime/permissions) (MUST)
`cache:verify` MUST treat artifacts as **bytes-only**:
- File `mtime/ctime` MUST be ignored (not part of dirty/clean semantics).
- File permissions/ownership MUST be ignored (not part of dirty/clean semantics).

### E) Warning-silent IO + no path leaks (MUST)
Будь-які filesystem warnings/notices MUST бути перехоплені і конвертовані в deterministic exception:
- exception message MUST NOT містити absolute paths або input path string
- allowed: fixed reason tokens only

### F) Safe explain (cache:verify) (MUST)
Fingerprint/config verify explain MUST NOT містити:
- dotenv values
- raw payloads / SQL
- absolute paths

Allowed:
- normalized relative paths
- key names
- `hash(value)` / `len(value)` (друк — тільки platform/cli)

## Compatibility locks (MUST)
Цей епік MUST додати Contract tests, які блокують семантичний drift від PHASE 0 fixtures:
- payload/json:
  - використовує `framework/tools/spikes/fixtures/payloads_min/payloads.php`
  - доводить deterministic normalization + stable JSON bytes + float-forbidden
- fingerprint:
  - використовує `framework/tools/spikes/fixtures/repo_min/**`
  - доводить stable file listing order + symlink forbidden + EOL-stable hashing
- golden hash (або hash-of-buckets) MUST збігатися з очікуваннями, зафіксованими для Phase 0 алгоритму

### Deliverables (MUST)

#### Creates

Artifacts core:
- [ ] All kernel-owned artifacts MUST use the canonical envelope:
  - [ ] top-level shape: `{ "_meta": <header>, "payload": <schema-specific> }`
  - [ ] this applies even when the artifact is emitted as a PHP file returning an array
- [ ] `framework/packages/core/kernel/src/Artifacts/ArtifactWriter.php` — atomic write + permissions + stable bytes
- [ ] `framework/packages/core/kernel/src/Artifacts/PayloadNormalizer.php` — map ksort, list preserve, forbids floats (NaN/INF)
- [ ] `framework/packages/core/kernel/src/Artifacts/Php/StablePhpArrayDumper.php` — stable PHP emission
- [ ] `framework/packages/core/kernel/src/Artifacts/Header/ArtifactHeader.php` — canonical artifact header:
  - [ ] `name`
  - [ ] `schemaVersion`
  - [ ] `fingerprint`
  - [ ] `generator`
  - [ ] optional `requires`
  - [ ] no timestamps

Fingerprint:
- [ ] `framework/packages/core/kernel/src/Artifacts/Fingerprint/DeterministicFileLister.php` — stable listing + symlink forbidden
- [ ] `framework/packages/core/kernel/src/Artifacts/Fingerprint/FingerprintCalculator.php` — sha256 over deterministic inputs
- [ ] `framework/packages/core/kernel/src/Artifacts/Fingerprint/FingerprintExplainer.php` — safe diff explain (hashes + key names)

Builders:
- [ ] `framework/packages/core/kernel/src/Artifacts/Builders/ModuleManifestBuilder.php`
- [ ] `framework/packages/core/kernel/src/Artifacts/Builders/CompiledConfigBuilder.php`
- [ ] `framework/packages/core/kernel/src/Artifacts/Builders/StubContainerBuilder.php`

Verification:
- [ ] `framework/packages/core/kernel/src/Artifacts/Verifier/CacheVerifier.php` — compares **artifact content bytes only** (normalized LF); ignores mtime/permissions; validates artifact headers + payload schema

Errors:
- [ ] `framework/packages/core/kernel/src/Artifacts/Exception/ArtifactWriteFailedException.php` — `CORETSIA_ARTIFACT_WRITE_FAILED`
- [ ] `framework/packages/core/kernel/src/Artifacts/Exception/ArtifactInvalidException.php` — `CORETSIA_ARTIFACT_INVALID`
- [ ] `framework/packages/core/kernel/src/Artifacts/Exception/FingerprintSymlinkForbiddenException.php` — `CORETSIA_FINGERPRINT_SYMLINK_FORBIDDEN`
- [ ] `framework/packages/core/kernel/src/Artifacts/Exception/JsonFloatForbiddenException.php` — `CORETSIA_JSON_FLOAT_FORBIDDEN`

Docs:
- [ ] `docs/adr/ADR-0028-kernel-artifacts-fingerprint-cache-verify.md`
- [ ] `docs/ssot/artifacts-and-fingerprint.md` - kernel artifact production + fingerprint behavior
  - [ ] MUST NOT redefine the canonical artifact envelope, header fields, or artifact registry rows from `docs/ssot/artifacts.md`.
  - [ ] Owns only kernel-side production rules for artifacts, fingerprint behavior, exclusions, and verification linkage.
- [ ] `docs/ssot/cache-verify.md`

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/artifacts-and-fingerprint.md`
  - [ ] `docs/ssot/cache-verify.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0028-kernel-artifacts-fingerprint-cache-verify.md`
- [ ] `framework/packages/core/kernel/config/kernel.php` — adds artifacts/fingerprint keys
- [ ] `framework/packages/core/kernel/config/rules.php` — enforces shape
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php`
  - [ ] registers:
    - [ ] `ArtifactWriter`
    - [ ] `PayloadNormalizer`
    - [ ] `StablePhpArrayDumper`
    - [ ] `ArtifactHeader`
    - [ ] `DeterministicFileLister`
    - [ ] `FingerprintCalculator`
    - [ ] `FingerprintExplainer`
    - [ ] `ModuleManifestBuilder`
    - [ ] `CompiledConfigBuilder`
    - [ ] `StubContainerBuilder`
    - [ ] `CacheVerifier`
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php`
  - [ ] deterministic factory wiring for artifact/fingerprint/verify services
  - [ ] MUST NOT keep mutable runtime state

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/core/kernel/config/kernel.php`
- [ ] Keys (dot):
  - [ ] `kernel.artifacts.cache_dir` = "skeleton/var/cache"
  - [ ] `kernel.fingerprint.ignore_prefixes` = ["skeleton/var"]
  - [ ] `kernel.fingerprint.env.tracked_keys` = ["APP_ENV","APP_DEBUG","APP_PRESET"]
- [ ] Rules:
  - [ ] `framework/packages/core/kernel/config/rules.php` enforces shape

- Artifacts pipeline and fingerprint verification are baseline kernel facilities and MUST NOT be feature-disabled via config.
- Artifact schema versions are owner-locked by artifact code/SSoT and MUST NOT be runtime-configurable.
- Fingerprint MUST reuse the canonical dotenv files list from `kernel.env.dotenv.files` defined by Phase A boot; no duplicate dotenv-files list is allowed under `kernel.fingerprint.*`.

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `skeleton/var/cache/<appId>/module-manifest.php` (schemaVersion, deterministic bytes)
  - [ ] `skeleton/var/cache/<appId>/config.php` (schemaVersion, deterministic bytes)
  - [ ] `skeleton/var/cache/<appId>/container.php` (stub)
- [ ] Reads:
  - [ ] validates header + payload schema

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Fingerprint explain MUST NOT include env values; only key names + hashes/len.
- [ ] No session ids/tokens stored or printed.

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `kernel.artifacts.write`
  - [ ] `kernel.fingerprint.calculate`
- [ ] Metrics:
  - [ ] `kernel.artifact_write_total` (labels: `outcome`)
  - [ ] `kernel.artifact_write_duration_ms` (labels: `outcome`)
  - [ ] `kernel.fingerprint_total` (labels: `outcome`)
  - [ ] `kernel.fingerprint_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] safe paths (normalized) + counts; no secrets

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] auth/cookies/session ids/tokens/raw payload/raw SQL
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] Artifact/header/payload determinism:
  - [ ] `framework/packages/core/kernel/tests/Integration/ArtifactsRerunNoDiffTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/ArtifactsHeaderShapeContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/KernelPhpArtifactsUseCanonicalEnvelopeContractTest.php`
    - [ ] asserts kernel-owned PHP artifacts (`module-manifest.php`, `config.php`, `container.php`) return the canonical top-level envelope `{ "_meta", "payload" }`
    - [ ] asserts no artifact-specific alternative top-level shape exists
  - [ ] `framework/packages/core/kernel/tests/Contract/PayloadNormalizerDeterministicOrderTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/KernelArtifactsReuseFoundationStableJsonEncoderContractTest.php`
- [ ] Fingerprint cross-OS invariants:
  - [ ] `framework/packages/core/kernel/tests/Contract/FingerprintPathSeparatorContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/FingerprintFileListingOrderContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/FingerprintExplainerDeterminismContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/FingerprintIgnoresSkeletonVarTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/CacheVerifyIgnoresMtimeAndPermissionsTest.php`
- [ ] Spike locks:
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintGoldenHashLockTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintExplainSafetyLockTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintSymlinkForbiddenLockTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintPathNormalizationCrossOsLockTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeStableJsonEncodingLockTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikePayloadNormalizerDeterministicOrderLockTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeJsonFloatForbiddenLockTest.php`

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/core/kernel/tests/Contract/ArtifactsHeaderShapeContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/KernelPhpArtifactsUseCanonicalEnvelopeContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/PayloadNormalizerDeterministicOrderTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/KernelArtifactsReuseFoundationStableJsonEncoderContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/FingerprintPathSeparatorContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/FingerprintFileListingOrderContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/FingerprintExplainerDeterminismContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintGoldenHashLockTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintExplainSafetyLockTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintSymlinkForbiddenLockTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintPathNormalizationCrossOsLockTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeStableJsonEncodingLockTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikePayloadNormalizerDeterministicOrderLockTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/SpikeJsonFloatForbiddenLockTest.php`
- Unit:
  - [ ] `framework/packages/core/kernel/tests/Unit/FingerprintInstalledManifestNormalizationTest.php`
- Integration:
  - [ ] `framework/packages/core/kernel/tests/Integration/ArtifactsRerunNoDiffTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/FingerprintIgnoresSkeletonVarTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/CacheVerifyIgnoresMtimeAndPermissionsTest.php`
    - [ ] touching only mtime MUST keep `dirty=false`
    - [ ] changing only permissions/ownership metadata MUST keep `dirty=false`
    - [ ] bytes unchanged remains the only clean/dirty criterion

### DoD (MUST)

- [ ] Rerun no diff for generated artifacts
- [ ] Cross-OS deterministic invariants verified by contract tests
- [ ] cache:verify clean immediately after compile
- [ ] Docs explain middleware linkage + fingerprint exclusions
- [ ] Spike fixtures lock production invariants
- [ ] Artifacts live only in `skeleton/var/cache/<appId>/*` and are written atomically (no partial writes).
- [ ] `platform/http` будує middleware stack з compiled config:
  - [ ] lists `http.middleware.<slot>` + auto toggles `http.middleware.auto.*`
  - [ ] tagged middlewares (`http.middleware.*`) + compiled lists
  - [ ] dedupe policy: “first wins” (middleware catalog)
- [ ] When only `skeleton/var/maintenance/*` changes, then fingerprint remains unchanged and `cache:verify` stays clean.
- [ ] Reset discipline (kernel.reset) — NOT USED in artifacts/fingerprint/cache:verify (MUST)
  - [ ] Artifacts pipeline MUST NOT invoke reset and MUST NOT require UoW lifecycle.
  - [ ] Cache verification MUST remain pure (read/compare) and deterministic:
    - [ ] no dependency on `ResetOrchestrator` execution
  - [ ] Rationale: artifacts/fingerprint are build-time concerns; reset is runtime UoW boundary enforcement.

---

### 1.340.0 Kernel: Container compile (REAL) + `container.php` artifact (MUST) [IMPL]

---
type: package
phase: 1
epic_id: "1.340.0"
owner_path: "framework/packages/core/kernel/"

package_id: "core/kernel"
composer: "coretsia/core-kernel"
kind: runtime
module_id: "core.kernel"

goal: "Однаковий набір enabled modules + compiled config → завжди однаковий `container.php` bytes."
provides:
- "REAL deterministic compiled container artifact (replaces Phase 0 stub)"
- "Artifact-only production boot policy (hard fail without container.php)"
- "Deterministic definition graph without closures/timestamps/randomness"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []  # registry row for `container@1` is introduced in `1.30.0`; `1.330.0` materializes the stub artifact; this epic upgrades stub -> REAL semantics
adr: "docs/adr/ADR-0029-kernel-container-compile-artifact.md"
ssot_refs:
- "docs/ssot/compiled-container.md" # compiled-container-specific payload + boot semantics
- "docs/ssot/artifacts.md"          # global artifact law
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.20.0 — Config roots registry exists and includes root `kernel` owned by `core/kernel`
  - 1.30.0 — global artifact envelope/header/schema registry exists (`docs/ssot/artifacts.md`)
  - 1.200.0 — Foundation-owned reset discovery wiring exists and must be preserved by the compiled container; runtime truth remains `foundation.reset.tag` (reserved default `kernel.reset`)
  - 1.250.0 — if enhanced reset ordering is enabled, compiled container MUST preserve Foundation-owned reset wiring without re-implementing reset ordering/meta semantics
  - 1.330.0 — artifacts pipeline + header/schema infra + writer present

- Terminology note (MUST): config root vs config key namespaces
  - Config root for Kernel is **`kernel`** (file: `framework/packages/core/kernel/config/kernel.php`).
  - Any dotted prefixes like `kernel.uow.*`, `kernel.runtime.*`, `kernel.modules.*`, `kernel.config.*`, `kernel.artifacts.*`, `kernel.fingerprint.*`
    are **config key namespaces**, not separate roots.
  - `config/<name>.php` MUST return subtree for `<name>` (no wrapper array repeating the root key).

- Required deliverables (exact paths):
  - `framework/packages/core/foundation/` — ContainerBuilder + TagRegistry + DeterministicOrder

- Required tags:
  - effective Foundation reset discovery tag resolved from `foundation.reset.tag`
    (reserved default `kernel.reset`) — discovered services implement `ResetInterface` (policy)

- Required contracts / ports:
  - `Coretsia\Contracts\Runtime\ResetInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:
- `core/contracts`
- `core/foundation`

Forbidden:
- `platform/*`
- `devtools/*` (включно `devtools/internal-toolkit`, `devtools/cli-spikes`)
- `framework/tools/**` (runtime)
- `Coretsia\Tools\Spikes\*` (runtime import forbidden; test-only)

#### Uses ports (API surface, NOT deps) (optional)

- Foundation stable APIs:
  - `Coretsia\Foundation\Container\ContainerBuilder`
  - `Coretsia\Foundation\Discovery\DeterministicOrder`
  - `Coretsia\Foundation\Tag\TagRegistry`

### Entry points / integration points (MUST)

- CLI:
  - `coretsia config:compile` → owner `platform/cli` triggers compiled container builder (Phase 1)
- Artifacts:
  - writes: `skeleton/var/cache/<appId>/container.php` (REAL)

### Phase 0 parity: deterministic artifacts discipline (container.php) (MUST)

Цей епік активує REAL `container.php` artifact і MUST наслідувати ті самі “deterministic bytes” інваріанти,
які зацементовані Phase 0 rails (0.20.0) та artifacts policy (узгоджено з 1.330.0).

#### Hard boundary: no Phase 0 tooling deps in runtime (MUST)
`core/kernel` runtime MUST NOT:
- залежати від `devtools/*` (включно `devtools/internal-toolkit`, `devtools/cli-spikes`)
- імпортувати `Coretsia\Tools\Spikes\*`
- читати `framework/tools/**` у runtime execution
  (Spike fixtures допускаються тільки в test-time locks.)

## Required invariants for compiled container (cemented; single-choice)

### A) Deterministic graph ordering (MUST)
Будь-які порядки у definition graph MUST бути стабільні і locale-independent:
- service ids: sort by byte-order (`strcmp`)
- parameters/arguments maps: sort keys by byte-order recursively
- tag discovery lists / tagged-service lists:
  - MUST preserve the canonical Foundation tag semantics exactly
  - discovery order MUST remain:
    - `priority DESC, id ASC`
  - dedupe MUST remain:
    - first-wins per `(tag, serviceId)`
  - compiler/runtime MUST NOT invent an alternative tag ordering or dedupe rule
- no reliance on filesystem iteration order, reflection order, or locale

### B) Stable serialization (MUST)
`container.php` bytes MUST be stable:
- LF-only line endings + final newline
- no timestamps, no random ids, no environment-dependent metadata
- no absolute machine paths embedded
- no closures / anonymous functions serialized into artifact payload

### C) Error policy (MUST)
Будь-які помилки компіляції/читання artifact MUST бути:
- deterministic (stable code-first semantics)
- без абсолютних шляхів
- без OS error messages, що можуть містити paths/secrets
  Allowed: fixed reason tokens + deterministic error code only.

### D) Output-free invariant (MUST)
`core/kernel` runtime code MUST NOT писати в stdout/stderr.
Уся людиночитна діагностика — тільки у `platform/*`.
Kernel повертає/кидає deterministic exceptions.

## Verification locks (MUST)
Епік MUST мати Contract/Integration tests, які цементують:
- identical inputs → identical `container.php` bytes (rerun-no-diff)
- header shape/schema is stable (без timestamps)
- factory builds runtime container from artifact deterministically
- missing artifact hard-fails with deterministic code `CORETSIA_CONTAINER_ARTIFACT_MISSING` (без path leaks)

### Deliverables (MUST)

#### Creates

Compiler:
- [ ] `framework/packages/core/kernel/src/Container/ContainerCompiler.php` — builds deterministic definition graph (json-like)
  - [ ] MUST preserve the caller-supplied deterministic provider/module order exactly
  - [ ] MUST NOT globally re-sort providers before applying definition override semantics
  - [ ] MUST preserve the canonical Foundation binding-collision rule exactly:
    - [ ] for the same service id / interface binding, the later provider definition overrides the earlier one deterministically
    - [ ] compiler output MUST NOT invent a different override policy from `core/foundation`
  - [ ] if compiled output materializes tag registrations / discovery lists, it MUST preserve Foundation tag semantics exactly:
    - [ ] dedupe remains first-wins per `(tag, serviceId)`
    - [ ] final discovery order remains the canonical Foundation order
    - [ ] compiler/runtime MUST NOT introduce a second tag-merge policy
  - [ ] Rationale:
    - [ ] compiled-container semantics MUST remain aligned with `core/foundation` `ContainerBuilder`
    - [ ] later binding overrides earlier binding deterministically
  - [ ] MUST reject any closure / anonymous-function based definition or factory deterministically before artifact write
  - [ ] rejection MUST surface:
    - [ ] `ContainerCompileFailedException`
    - [ ] code: `CORETSIA_CONTAINER_COMPILE_FAILED`
    - [ ] message: `container-compile-failed`
  - [ ] diagnostics MUST NOT include:
    - [ ] closure dumps
    - [ ] source code snippets
    - [ ] absolute paths
    - [ ] raw config values
- [ ] `framework/packages/core/kernel/src/Container/CompiledContainerFactory.php` — builds runtime Container from artifact
- [ ] `framework/packages/core/kernel/src/Artifacts/Builders/CompiledContainerBuilder.php` — writes artifact with standard header
  - [ ] MUST reuse the canonical kernel artifact envelope introduced in 1.330.0:
    - [ ] top-level shape: `{ "_meta": <header>, "payload": <compiled-container-payload> }`
  - [ ] MUST NOT introduce a container-specific top-level artifact shape.

Definition shapes:
- [ ] `framework/packages/core/kernel/src/Container/Definition/ServiceDefinition.php`
- [ ] `framework/packages/core/kernel/src/Container/Definition/ParameterBag.php`
- [ ] `framework/packages/core/kernel/src/Container/Definition/DefinitionGraph.php` — stable graph, no closures

DTO policy boundary:
- [ ] `ServiceDefinition`, `ParameterBag`, and `DefinitionGraph` are kernel container compilation models/shapes.
- [ ] They are NOT DTO-marker classes by default.
- [ ] DTO gates apply only to explicitly marked DTO transport classes.

Errors:
- [ ] `framework/packages/core/kernel/src/Container/Exception/ContainerCompileFailedException.php` — `CORETSIA_CONTAINER_COMPILE_FAILED`
- [ ] `framework/packages/core/kernel/src/Container/Exception/ContainerArtifactMissingException.php` — `CORETSIA_CONTAINER_ARTIFACT_MISSING`

Docs:
- [ ] `docs/adr/ADR-0029-kernel-container-compile-artifact.md`
- [ ] `docs/ssot/compiled-container.md` - compiled-container-specific payload + boot semantics
  - [ ] MUST NOT redefine the global artifact envelope, header fields, or artifact registry rows from `docs/ssot/artifacts.md`.
  - [ ] Owns only compiled-container payload shape, compile rules, and artifact-only boot policy.

#### Modifies

- [ ] `docs/ssot/INDEX.md` — register:
  - [ ] `docs/ssot/compiled-container.md`
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0029-kernel-container-compile-artifact.md`
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php`
  - [ ] production/runtime container creation MUST use `CompiledContainerFactory`
  - [ ] missing compiled artifact MUST fail with `ContainerArtifactMissingException` (`CORETSIA_CONTAINER_ARTIFACT_MISSING`)
  - [ ] runtime MUST NOT silently fall back to a non-artifact container in production mode
  - [ ] Artifact-only boot clarification (single-choice; cemented):
    - [ ] after this epic, all Phase 1 runtime boot paths covered by Kernel/AppBuilder/smoke tests MUST use compiled-artifact boot
    - [ ] missing compiled artifact MUST hard-fail deterministically with `CORETSIA_CONTAINER_ARTIFACT_MISSING`
    - [ ] no implicit non-artifact fallback exists in the runtime boot paths covered by this epic
    - [ ] any future developer-mode fallback requires a separate epic/ADR and MUST NOT be implied here
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php`
  - [ ] registers:
    - [ ] `ContainerCompiler`
    - [ ] `CompiledContainerFactory`
    - [ ] `CompiledContainerBuilder`

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `skeleton/var/cache/<appId>/container.php` — REAL compiled container artifact (same path as the earlier stub; semantics upgraded from stub → REAL), schemaVersion, deterministic bytes
- [ ] Reads:
  - [ ] validates header + payload schema for same file
  - [ ] validation MUST assert the same canonical top-level envelope `{ "_meta", "payload" }` used by other kernel-owned artifacts.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Reset discipline preserved:
  - [ ] all services discovered through the effective Foundation reset discovery tag
    resolved from `foundation.reset.tag` (reserved default `kernel.reset`)
    implement `ResetInterface` (gates)
  - [ ] tag lists deterministic (`DeterministicOrder`)

#### Observability (policy-compliant)

- [ ] Metrics:
  - [ ] `kernel.container_compile_total` (labels: `outcome`)
  - [ ] `kernel.container_compile_duration_ms` (labels: `outcome`)
- [ ] Logs:
  - [ ] compile summary only (counts), no secrets, no raw config dumps

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] any secrets embedded into compiled payload (only references/ids allowed)

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] Deterministic container bytes:
  - [ ] `framework/packages/core/kernel/tests/Contract/CompiledContainerIsDeterministicTest.php`
- [ ] Header shape:
  - [ ] `framework/packages/core/kernel/tests/Contract/ContainerArtifactHeaderShapeContractTest.php`
- [ ] Runtime factory from artifact:
  - [ ] `framework/packages/core/kernel/tests/Integration/CompiledContainerFactoryBuildsContainerFromArtifactTest.php`
- [ ] Missing artifact hard-fail:
  - [ ] `framework/packages/core/kernel/tests/Integration/ArtifactOnlyBootFailsDeterministicallyWhenContainerArtifactMissingTest.php`

### Tests (MUST)

- Contract:
  - [ ] `framework/packages/core/kernel/tests/Contract/ContainerArtifactHeaderShapeContractTest.php`
  - [ ] `framework/packages/core/kernel/tests/Contract/CompiledContainerIsDeterministicTest.php`
- Integration:
  - [ ] `framework/packages/core/kernel/tests/Integration/CompiledContainerFactoryBuildsContainerFromArtifactTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/ArtifactOnlyBootResolvesResetOrchestratorTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/ArtifactOnlyBootFailsDeterministicallyWhenContainerArtifactMissingTest.php`
    - [ ] asserts code `CORETSIA_CONTAINER_ARTIFACT_MISSING`
    - [ ] asserts no silent fallback to non-artifact container in production mode
    - [ ] asserts no absolute path leak in the surfaced failure
  - [ ] `framework/packages/core/kernel/tests/Integration/CompiledContainerRejectsClosureDefinitionsDeterministicallyTest.php`
    - [ ] asserts code `CORETSIA_CONTAINER_COMPILE_FAILED`
    - [ ] asserts fixed message token `container-compile-failed`
    - [ ] asserts no absolute paths, closure dumps, source snippets, or raw config values leak into the surfaced failure
  - [ ] `framework/packages/core/kernel/tests/Integration/CompiledContainerPreservesLaterBindingOverridesTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/CompiledContainerPreservesTagDedupeFirstWinsTest.php`

### DoD (MUST)

- [ ] REAL `container.php` produced in Phase 1 pipeline
- [ ] rerun no diff
- [ ] deterministic hard-fail when artifact missing (production policy)
- [ ] docs complete
- [ ] Phase 1 artifact-based runtime boot paths hard-fail without `container.php` with deterministic code:
  - [ ] `CORETSIA_CONTAINER_ARTIFACT_MISSING`
- [ ] No implicit non-artifact fallback exists in the boot paths covered by this epic.
- [ ] When the container is compiled twice without changes, then `git diff` is empty and `cache:verify` stays clean.
- [ ] Reset discipline in artifact-only boot (MUST)
  - [ ] effective reset tag resolution remains Foundation-owned and MUST NOT be duplicated in compiler/artifact code
  - [ ] When REAL container artifact becomes active (this epic scope), the compiled container MUST include Foundation reset infrastructure so runtime can trigger reset:
    - [ ] `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator` must be resolvable from the container
    - [ ] `Coretsia\Foundation\Tag\TagRegistry` must be resolvable (as ResetOrchestrator dependency)
  - [ ] **Kernel MUST still not enumerate `kernel.reset`.**
    - [ ] KernelRuntime triggers reset ONLY by calling `ResetOrchestrator::resetAll()` obtained via DI/container.
  - [ ] **No semantic duplication:**
    - [ ] Container compiler/artifact builder MUST NOT re-implement reset ordering/meta parsing.
    - [ ] That remains in `core/foundation` (1.200.0 / 1.250.0).
- [ ] Add/extend an integration test in `core/kernel` (or wherever your artifact boot tests live) asserting:
  - [ ] artifact-only boot can resolve `ResetOrchestrator`
  - [ ] KernelRuntime can execute a UoW and triggers reset once per UoW

---

### 1.350.0 Kernel: RuntimeDriverGuard + Runtime driver matrix E2E locks (MUST) [IMPL+TOOLING]

---
type: package
phase: 1
epic_id: "1.350.0"
owner_path: "framework/packages/core/kernel/"

package_id: "core/kernel"
composer: "coretsia/core-kernel"
kind: runtime
module_id: "core.kernel"

goal: "В будь-якому entrypoint’і система або працює в допустимій комбінації драйверів, або падає deterministic з одним і тим самим кодом; матриця сумісності цементована E2E fixtures/tests і неможливо змержити зміну, що ламає правила."
provides:
- "Єдиний deterministic guard для runtime-композиції (HTTP driver detection + conflict rules)"
- "Deterministic error codes для CLI/entrypoints"
- "Модульні вимоги: non-classic HTTP driver → requires platform.http enabled"
- "E2E locks матриці сумісності runtime drivers (allowed/forbidden combos) через fixture apps"
- "Proof: RuntimeDriverGuard реально викликається та блокує конфліктні комбінації"
- "Default classic http + queue OK без adapters"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr:
- "docs/adr/ADR-0027-runtime-driver-guard.md"

ssot_refs:
- "docs/ssot/runtime-drivers.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.20.0 — Config roots registry exists and includes root `kernel` owned by `core/kernel`
  - 1.260.0 — runtime drivers SSoT exists (`docs/ssot/runtime-drivers.md`)
  - 1.310.0 — `ModulePlan` is resolvable and can be provided by the caller when module checks are required
  - 1.320.0 — merged config available via `Coretsia\Contracts\Config\ConfigRepositoryInterface`

- Terminology note (MUST): config root vs config key namespaces
  - Config root for Kernel is **`kernel`** (file: `framework/packages/core/kernel/config/kernel.php`).
  - Any dotted prefixes like `kernel.uow.*`, `kernel.runtime.*`, `kernel.modules.*`, `kernel.config.*`, `kernel.artifacts.*`, `kernel.fingerprint.*`
    are **config key namespaces**, not separate roots.
  - `config/<name>.php` MUST return subtree for `<name>` (no wrapper array repeating the root key).

- Required contracts / ports:
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface`

- Required kernel types/services (provided by 1.310.0):
  - `Coretsia\Kernel\Module\ModulePlan` (input for module-compat checks)
  - `Coretsia\Kernel\Module\ModulePlanResolver` (caller-resolved; guard MUST NOT re-resolve presets/metadata internally)

- Required deliverables (exact paths):
  - `framework/tools/tests/Fixtures/RuntimeDriverMatrix/*` — fixture apps (for E2E matrix locks)

#### Compile-time deps (deptrac-enforceable) (MUST)

Kernel (runtime) depends on:
- `core/contracts`
- `core/foundation`

Kernel forbidden:
- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- `Psr\Http\Server\*`

Tooling (E2E tests):
- N/A (tooling tests; no deptrac constraints beyond repo norms)

#### Uses ports (API surface, NOT deps) (optional)

- Foundation stable APIs:
  - N/A (RuntimeDriverGuard is pure; it MUST NOT require container services like TagRegistry)

### Entry points / integration points (MUST)

Kernel guard callers:
- CLI:
  - indirectly: `coretsia worker:start` (owner `platform/worker`) MUST call guard
- HTTP:
  - entrypoints (`frankenphp.php|swoole.php|roadrunner.php`) call guard pre-boot
- Artifacts:
  - reads config via ConfigRepositoryInterface; may resolve ModulePlan if needed

E2E matrix:
- CLI:
  - tests may invoke entrypoints or a minimal bootstrap runner (per wiring)
- Artifacts:
  - fixtures may use cache dirs under `framework/tools/tests/Fixtures/*/var/cache/...`

### Deliverables (MUST)

#### Creates

Drivers model:
- [ ] `framework/packages/core/kernel/src/Runtime/Driver/HttpDriver.php` — enum-like `http.classic|http.frankenphp|http.swoole|http.roadrunner|http.worker`
- [ ] `framework/packages/core/kernel/src/Runtime/Driver/BackgroundDriver.php` — enum-like `bg.worker_queue`
- [ ] `framework/packages/core/kernel/src/Runtime/Driver/RuntimeDrivers.php` — VO `{httpDriver, backgroundDrivers[]}`

- [ ] Canonical driver ids stored/returned by the guard MUST match `docs/ssot/runtime-drivers.md` exactly.
- [ ] The guard MUST NOT use shortened aliases (`classic`, `roadrunner`, `worker_queue`) in runtime payloads, diagnostics, sorting, or E2E assertions.

Guard:
- [ ] `framework/packages/core/kernel/src/Runtime/Driver/RuntimeDriverGuard.php` — main service:
  - [ ] `detect(ConfigRepositoryInterface $cfg): RuntimeDrivers`
  - [ ] `assertCompatible(ConfigRepositoryInterface $cfg): void`
  - [ ] `assertHttpDriverCompatibleWithModules(ConfigRepositoryInterface $cfg, ModulePlan $plan): void`
  - [ ] separation of responsibilities is single-choice:
    - [ ] `detect(...)` and `assertCompatible(...)` are config-only and MUST NOT inspect `ModulePlan`
    - [ ] `assertHttpDriverCompatibleWithModules(...)` is the only method that may validate `platform.http` / module-plan compatibility
    - [ ] runtime driver selection MUST therefore remain derivable from config alone, exactly as locked by `docs/ssot/runtime-drivers.md`
  - [ ] when producing conflict / invalid-config diagnostics, guard MUST use canonical driver ids from `docs/ssot/runtime-drivers.md` exactly
  - [ ] guard MUST NOT emit shortened aliases such as `classic`, `roadrunner`, `worker_queue`
  - [ ] any diagnostics list of active/conflicting drivers MUST be sorted by canonical driver id using byte-order `strcmp`
  - [ ] any diagnostics list of required moduleIds MUST also be sorted by `strcmp`

Errors:
- [ ] `framework/packages/core/kernel/src/Runtime/Driver/Exception/RuntimeDriverConflictException.php`
  - [ ] code: `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`
  - [ ] reason tokens (stable, message-safe): e.g. `multiple_http_drivers`, `worker_http_conflicts_with_http_driver`
- [ ] `framework/packages/core/kernel/src/Runtime/Driver/Exception/RuntimeDriverInvalidConfigException.php`
  - [ ] code: `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`
  - [ ] reason tokens (stable): e.g. `requires_platform_http_module`
  - [ ] config shape / unknown-key enforcement is owned by config rules validation, NOT by `RuntimeDriverGuard`
  - [ ] `RuntimeDriverGuard` MUST remain matrix-focused and MUST NOT become a second source of truth for generic config validation

MUST:
- [ ] Guard unit/E2E tests assert these exact CODE strings (line-of-truth).
- [ ] No other driver-matrix codes are introduced to avoid drift from `docs/ssot/runtime-drivers.md`.

Docs:
- [ ] `docs/adr/ADR-0027-runtime-driver-guard.md` — decision record (scope, invariants, deterministic codes)
- [ ] `docs/architecture/runtime-driver-guard.md` — API + callers + deterministic codes
  - [ ] API surface + callers + error codes
  - [ ] MUST NOT duplicate the canonical matrix/rules (those live in `docs/ssot/runtime-drivers.md`)
  - [ ] **Scope note (MUST):** This document is an architecture overview (API surface + callers + deterministic error codes).
    - [ ] The **single canonical source** for the runtime-drivers compatibility matrix and decision rules is:
    - [ ] `docs/ssot/runtime-drivers.md`.
    - [ ] Any behavioral change MUST update:
      - [ ] 1) `docs/ssot/runtime-drivers.md` (rules/matrix),
      - [ ] 2) Kernel unit/integration locks (guard codes),
      - [ ] 3) E2E matrix fixtures/tests under `framework/tools/tests/Fixtures/RuntimeDriverMatrix/*`.
    - [ ] This document MUST NOT duplicate the matrix/rules (to avoid drift).

#### Modifies

- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0027-runtime-driver-guard.md`
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php` — registers RuntimeDriverGuard
- [ ] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/core/kernel/config/kernel.php`
  - [ ] add defaults:
    - [ ] `kernel.runtime.frankenphp.enabled` = false
    - [ ] `kernel.runtime.swoole.enabled` = false
    - [ ] `kernel.runtime.roadrunner.enabled` = false
- [ ] `framework/packages/core/kernel/config/rules.php`
  - [ ] enforce bool shape for:
    - [ ] `kernel.runtime.frankenphp.enabled`
    - [ ] `kernel.runtime.swoole.enabled`
    - [ ] `kernel.runtime.roadrunner.enabled`

#### Creates (Tooling fixtures + E2E tests)

Fixture wiring:
- [ ] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/ClassicHttpApp/`
- [ ] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/RoadrunnerHttpApp/`
- [ ] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/FrankenphpHttpApp/`
- [ ] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/SwooleHttpApp/`
- [ ] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/WorkerQueueApp/`
- [ ] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/WorkerHttpApp/`

Tests:
- [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllowsRoadrunnerPlusWorkerQueueTest.php`
- [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsRoadrunnerPlusWorkerHttpTest.php`
- [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllowsFrankenphpPlusWorkerQueueTest.php`
- [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsSwoolePlusWorkerHttpTest.php`
- [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixDefaultClassicIsAllowedTest.php`
- [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllowsSwoolePlusWorkerQueueTest.php`
- [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsFrankenphpPlusWorkerHttpTest.php`
- [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllowsClassicPlusWorkerQueueTest.php`
- [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsWorkerHttpWithoutPlatformHttpModuleTest.php`

### Configuration inputs (guard inputs) (MUST)

RuntimeDriverGuard MUST read only the canonical configuration keys defined in:

- `docs/ssot/runtime-drivers.md`

This epic MUST NOT introduce alternative key names or guard-only aliases.

Missing-key behavior before `1.360.0` (single-choice; cemented):
- If `worker.enabled` is absent, the guard MUST treat it as `false`.
- If `worker.task_type` is absent, the guard MUST NOT activate any worker-derived runtime driver.
- Absence of the `worker.*` root alone MUST NOT produce `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`.
- Rationale:
  - `worker` root is introduced later by `1.360.0`;
    until then, guard behavior must remain deterministic and safe-by-default.

The only intentional repetition in this epic is the concrete default wiring added to `framework/packages/core/kernel/config/kernel.php`; behavioral truth remains `docs/ssot/runtime-drivers.md`.

Any behavioral/key change MUST update:
1) `docs/ssot/runtime-drivers.md` (canonical keys + rules),
2) Kernel unit/integration locks (guard codes),
3) E2E matrix fixtures/tests under `framework/tools/tests/Fixtures/RuntimeDriverMatrix/*`.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [ ] Logs:
  - [ ] failures log only active driver names + deterministic code (no env values)
- [ ] Metrics/Spans:
  - [ ] optional; not required (kept minimal)

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] full config dumps, env values, file paths
- [ ] Allowed:
  - [ ] driver names, moduleIds, deterministic code

### Phase 0 parity: deterministic guard semantics + safe failures (MUST)

RuntimeDriverGuard MUST відповідати rails-політикам детермінізму/безпечних повідомлень PHASE 0 (0.20.0):
- deterministic ordering (byte-order; locale-independent)
- deterministic error codes (code-first)
- no secrets/PII, no full config dumps
- no stdout/stderr output з kernel

#### Output-free invariant (MUST)
`core/kernel` runtime MUST NOT писати в stdout/stderr (echo/print/var_dump/print_r/printf/error_log, STDOUT/STDERR writes).
Діагностика для користувача — тільки в `platform/*`.

#### Deterministic decision model (single-choice; cemented)
- detect() MUST produce the same `RuntimeDrivers` for the same config (no environment probing beyond declared inputs)
- assertCompatible() MUST fail deterministically з одним і тим самим кодом для тієї самої конфіг-комбінації
- ordering у будь-яких списках активних драйверів MUST бути стабільний (byte-order)
- ordering/diagnostics MUST use the canonical SSoT driver ids exactly:
  - `http.classic`
  - `http.frankenphp`
  - `http.swoole`
  - `http.roadrunner`
  - `http.worker`
  - `bg.worker_queue`

#### Safe error messages (cemented)
Exceptions MUST NOT містити:
- абсолютні шляхи
- env values
- повні dumps конфігу
  Allowed:
- driver names, moduleIds, deterministic code, fixed reason tokens

## E2E matrix fixtures/tests: determinism rules (MUST)
Fixture apps під `framework/tools/tests/Fixtures/RuntimeDriverMatrix/*`:
- MUST бути cross-OS stable (paths normalized у тестах; не порівнювати `\` vs `/` буквально)
- MUST NOT покладатися на locale/environment ordering
- MUST мати стабільні cache dirs (без timestamps/random suffix у шляхах)

Matrix tests MUST:
- assert deterministic error codes (line-of-truth)
- не перевіряти тексти повідомлень як user-facing output (тільки codes + мінімальні reason tokens якщо потрібно)

### Verification (TEST EVIDENCE) (MUST when applicable)

Kernel unit detection/conflicts:
- [ ] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardDetectsClassicWhenNoAdaptersEnabledTest.php`
- [ ] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardTreatsMissingWorkerKeysAsDisabledTest.php`
- [ ] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardDetectsRoadrunnerWhenEnabledTest.php`
- [ ] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardRejectsMultipleHttpDriversTest.php`
- [ ] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardRejectsWorkerHttpWithRoadrunnerTest.php`

Kernel integration module plan check:
- [ ] `framework/packages/core/kernel/tests/Integration/RuntimeDriverGuardChecksModulePlanForPlatformHttpTest.php`
  - [ ] asserts exact code `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`
    when a non-classic HTTP driver is selected but `platform.http` is absent from `ModulePlan`
- [ ] Additional matrix locks to match `docs/ssot/runtime-drivers.md` examples:
  - [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllowsSwoolePlusWorkerQueueTest.php`
  - [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsFrankenphpPlusWorkerHttpTest.php`
  - [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllowsClassicPlusWorkerQueueTest.php`

E2E matrix deterministic locks:
- [ ] Matrix tests assert deterministic codes:
  - [ ] `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT` (conflict combos)

### Tests (MUST)

Kernel:
- Unit:
  - [ ] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardDetectsClassicWhenNoAdaptersEnabledTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardTreatsMissingWorkerKeysAsDisabledTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardDetectsRoadrunnerWhenEnabledTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardRejectsMultipleHttpDriversTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardRejectsWorkerHttpWithRoadrunnerTest.php`
  - [ ] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardConflictDiagnosticsAreDeterministicallySortedTest.php`
    - [ ] asserts diagnostics use only canonical ids from `docs/ssot/runtime-drivers.md`
    - [ ] forbids shortened aliases such as `classic`, `roadrunner`, `worker_queue`
    - [ ] asserts active/conflicting drivers are sorted by canonical id using byte-order `strcmp`
- Integration:
  - [ ] `framework/packages/core/kernel/tests/Integration/RuntimeDriverGuardChecksModulePlanForPlatformHttpTest.php`
    - [ ] asserts `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`
- Gates/Arch:
  - [ ] Kernel stays PSR-7/15 free (existing gate)

Tooling:
- Integration:
  - [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllowsRoadrunnerPlusWorkerQueueTest.php`
  - [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsRoadrunnerPlusWorkerHttpTest.php`
  - [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllowsFrankenphpPlusWorkerQueueTest.php`
  - [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsSwoolePlusWorkerHttpTest.php`
  - [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixDefaultClassicIsAllowedTest.php`
  - [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllowsSwoolePlusWorkerQueueTest.php`
  - [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsFrankenphpPlusWorkerHttpTest.php`
  - [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllowsClassicPlusWorkerQueueTest.php`
  - [ ] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsWorkerHttpWithoutPlatformHttpModuleTest.php`

### DoD (MUST)

- [ ] Kernel guard:
  - [ ] Guard exists + wired in core/kernel
  - [ ] Deterministic conflict detection tests green
  - [ ] Rules match `docs/ssot/runtime-drivers.md`
  - [ ] No forbidden deps (no PSR-7/15, no platform/*)
  - [ ] Це “єдиний мозок”. Ніхто (ні `platform/worker`, ні runtime adapters) не має мати своїх правил — вони всі викликають Kernel guard.
- [ ] Tooling matrix locks:
  - [ ] Matrix tests cover allowed + forbidden combos
  - [ ] Failing combos assert deterministic error codes from guard:
    - [ ] `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT` for conflict combos
  - [ ] Failing invalid-config combos assert deterministic error code:
    - [ ] `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`
  - [ ] Tests green in CI
  - [ ] Цементує матрицю сумісності runtime drivers через E2E тести.
  - [ ] Доводить, що guard реально викликається і блокує конфліктні комбінації deterministic.
  - [ ] Доводить “default не заважає”: classic http + queue OK без adapters.
  - [ ] Tests run against fixture apps with different config/modules enablement.
- [ ] Non-goals / out of scope:
  - [ ] Не реалізує runtime adapters і не реалізує worker pool (це інші епіки).
  - [ ] Не вводить PSR-7/15 в kernel (HARD RULE).
  - [ ] Tooling не тестує продуктивність; тільки конфіг/guard/boot поведінку.
- [ ] Concrete lock example:
  - [ ] When enabling `kernel.runtime.roadrunner.enabled=true` + `worker.enabled=true && worker.task_type=http`, then:
    - [ ] `RuntimeDriverGuard` fails with `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT` before starting any long-running loop, and
    - [ ] E2E test asserts the same deterministic code.
- [ ] Reset discipline (kernel.reset) — boundary rule (MUST)
  - [ ] If this epic introduces any runtime entrypoint/factory/loop:
    - [ ] it MUST delegate UoW lifecycle to KernelRuntime (1.280.0)
    - [ ] it MUST NOT call TagRegistry for `kernel.reset`
    - [ ] it MUST NOT trigger reset anywhere except via KernelRuntime’s standard flow
  - [ ] If this epic is build-time only:
    - [ ] reset is N/A (same rule as 1.330.0)

---

### 1.360.0 Long-Running Runtime: Worker Manager & Application Worker (MUST) [IMPL]

---
type: package
phase: 1
epic_id: "1.360.0"
owner_path: "framework/packages/platform/worker/"

package_id: "platform/worker"
composer: "coretsia/platform-worker"
kind: runtime
module_id: "platform.worker"

goal: "Розробник може однією командою запустити пул воркерів, які обробляють багато UoW поспіль, а стан гарантовано очищується між UoW."
provides:
- "Long-running worker pool (master + N children) для багаторазової обробки UoW без restart PHP."
- "Deterministic process model + safety (max_requests, graceful shutdown) з unix (pcntl) та cross-platform (proc_open) drivers."
- "Reset discipline between UoW via Foundation reset orchestration (`ResetOrchestrator`) + hooks before/after UoW."

tags_introduced: []
config_roots_introduced:
- "worker"

artifacts_introduced: []
adr: "docs/adr/ADR-0017-worker-manager-application-worker.md"
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.10.0 — Tag registry SSoT exists (local mirror constant for `cli.command` is allowed here only because `platform/cli` is a forbidden compile-time dependency)
  - 1.20.0 — Config roots registry SSoT exists
  - This epic introduces root `worker` owned by `platform/worker` and MUST register it in `docs/ssot/config-roots.md`
  - 1.200.0 — `Coretsia\Foundation\Serialization\StableJsonEncoder` exists and is canonical for runtime JSON bytes.
  - 1.200.0 — TagRegistry + DeterministicOrder + reset orchestration exist.
  - 1.205.0 — Foundation noop observability + logger baseline bindings exist, so worker runtime can safely resolve logger / optional observability ports before `platform/*` implementations are installed.
  - 1.280.0 — `core/kernel` provides the canonical UoW wrapper + reset execution policy via `KernelRuntime`.
  - 1.350.0 — `Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard` exists and is the only allowed runtime-composition guard here.

- Guard enforcement (MUST):
  - Before starting the pool, `coretsia worker:start` MUST invoke
    `Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard` and reject conflicting runtime combinations deterministically.
  - If `worker.task_type=http`, `worker:start` MUST also enforce module compatibility through
    `RuntimeDriverGuard::assertHttpDriverCompatibleWithModules(...)`.
  - Missing `platform.http` in `ModulePlan` MUST fail with:
    - `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`
  - This failure MUST happen before `RequestHandlerInterface` resolution.

- Isolation policy (single-choice; cemented):
  - When `worker.enabled=true`, other long-running integrations/adapters MUST NOT become active implicitly.
  - If `worker.task_type=http`, the application preset MUST provide an HTTP handling stack by registering
    a `Psr\Http\Server\RequestHandlerInterface` implementation in the container.
    - Typical future provider: `platform/http` (Phase 2+), but worker MUST NOT require it as a compile-time dependency.

- Hook tags ownership note (MUST):
  - `kernel.hook.before_uow` and `kernel.hook.after_uow` are OWNED by `core/kernel`.
  - This epic MUST NOT consume them directly via `TagRegistry`.
  - This epic MUST NOT define or rename them.
  - The only allowed lifecycle entrypoint here is:
    - `Coretsia\Kernel\Runtime\KernelRuntimeInterface`
  - Rationale:
    - hook discovery/order and reset trigger semantics are kernel-owned;
      `platform/worker` only supplies tasks to the canonical UoW runtime.

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/` — `ResetInterface`, hooks, correlation provider port.
  - `framework/packages/core/foundation/` — deterministic order + context accessor (if used).
  - `framework/packages/core/kernel/` — UoW lifecycle + reset policy.

- Required config roots/keys:
  - `worker.*` — worker pool settings.

- Required tags:
  - CLI command discovery (IMPORTANT PHASE NOTE):
    - `platform/worker` contributes CLI commands via the tag `cli.command` **only in kernel/container-backed CLI mode (Phase 1+)**.
    - Phase 0 CLI base (0.130.0) is config-only (`cli.commands`) and does NOT consume `cli.command`;
      therefore `worker:*` commands are out of scope for Phase 0 CLI base.
  - `cli.command` string value MUST be exactly `cli.command` (cemented).

- Hook/reset lifecycle boundary (MUST):
  - `kernel.hook.before_uow` and `kernel.hook.after_uow` are owned and consumed by `core/kernel`.
  - `platform/worker` MUST NOT discover or invoke these tags directly via `TagRegistry`.
  - `platform/worker` MUST execute each task as a UoW only through `Coretsia\Kernel\Runtime\KernelRuntimeInterface`.
  - Reset remains transitive through:
    - `platform/worker` → `KernelRuntime` → `ResetOrchestrator`

- Required kernel lifecycle entrypoint (single-choice):
  - `Coretsia\Kernel\Runtime\KernelRuntimeInterface` — the only allowed entrypoint for executing each task as a UoW.
  - Hook interfaces are kernel-owned lifecycle internals here and are NOT direct compile-time API requirements for `platform/worker`.

- Required contracts / ports:
  - `Psr\Container\ContainerInterface`
  - `Coretsia\Kernel\Runtime\KernelRuntimeInterface`
  - `Psr\Log\LoggerInterface`
  - (http task mode) `Psr\Http\Message\ServerRequestInterface`, `Psr\Http\Message\ResponseInterface`, `Psr\Http\Server\RequestHandlerInterface`
  - (CLI) Contract-level command & IO ports from `core/contracts` (the same ports used by `platform/cli` discovery),
    so `platform/worker` can contribute `cli.command` implementations without depending on `platform/cli`.

- Non-goals (legacy):
  - Not a RoadRunner replacement (in-framework pool only).
  - No assumption pcntl exists on all OS (driver abstraction).
  - No new cross-package ports (TaskFactory is internal to this package).

- Acceptance scenario (legacy):
  - When I run `coretsia worker:start`, then a master spawns N workers, and each worker can process many tasks sequentially with reset after each task.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`
- `core/kernel`

Forbidden:

- `integrations/*`
- `platform/cli`
- `platform/http`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Container\ContainerInterface`
  - `Psr\Log\LoggerInterface`
  - `Psr\Http\Message\ServerRequestInterface`
  - `Psr\Http\Message\ResponseInterface`
  - `Psr\Http\Server\RequestHandlerInterface`
- Contracts:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Discovery\DeterministicOrder`

### Entry points / integration points (MUST)

- CLI:
  - **Phase note (cemented):** these commands exist only in **kernel/container-backed CLI mode (Phase 1+)** via tag discovery (`cli.command`).
    - Phase 0 CLI base (0.130.0) is config-only (`cli.commands`) and does NOT consume `cli.command`.
  - `coretsia worker:start` → `platform/worker` `src/Console/WorkerStartCommand.php`
  - `coretsia worker:stop` → `platform/worker` `src/Console/WorkerStopCommand.php`
  - `coretsia worker:status` → `platform/worker` `src/Console/WorkerStatusCommand.php`

- HTTP:
  - N/A (worker can execute HTTP tasks internally; does not add endpoints)

- Kernel hooks/tags:
  - consumed transitively only through `Coretsia\Kernel\Runtime\KernelRuntimeInterface`
  - `platform/worker` MUST NOT discover or invoke `kernel.hook.before_uow` directly
  - `platform/worker` MUST NOT discover or invoke `kernel.hook.after_uow` directly
  - consumer MUST NOT enumerate reset tags directly

- Artifacts:
  - reads: `skeleton/var/cache/<appId>/container.php` (optional; compiled container)
  - writes: `skeleton/var/tmp/worker.pid`
  - writes: `skeleton/var/tmp/worker.sock` — only when resolved `worker.control.transport = unix`
  - writes: `skeleton/var/tmp/worker.state.json`
  - writes: `skeleton/var/tmp/worker.stop`

### Deliverables (MUST)

#### Creates

Package skeleton:
- [ ] `framework/packages/platform/worker/src/Module/WorkerModule.php`
- [ ] `framework/packages/platform/worker/src/Provider/WorkerServiceProvider.php`
- [ ] `framework/packages/platform/worker/src/Provider/WorkerServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [ ] `framework/packages/platform/worker/README.md` (Observability / Errors / Security-Redaction)

Implementation:
- [ ] `framework/packages/platform/worker/src/Manager/WorkerManager.php`
- [ ] `framework/packages/platform/worker/src/Manager/Driver/WorkerManagerDriverInterface.php`
- [ ] `framework/packages/platform/worker/src/Manager/Driver/PcntlWorkerManagerDriver.php`
- [ ] `framework/packages/platform/worker/src/Manager/Driver/ProcWorkerManagerDriver.php`
- [ ] `framework/packages/platform/worker/src/Worker/ApplicationWorker.php`
- [ ] `framework/packages/platform/worker/src/Internal/TaskFactoryInternalInterface.php`
- [ ] `framework/packages/platform/worker/src/Task/HttpTaskFactory.php`
- [ ] `framework/packages/platform/worker/src/Task/QueueTaskFactory.php`
- [ ] `framework/packages/platform/worker/src/Communication/WorkerSocketServer.php`
- [ ] `framework/packages/platform/worker/src/Console/WorkerStartCommand.php`
- [ ] `framework/packages/platform/worker/src/Console/WorkerStopCommand.php`
- [ ] `framework/packages/platform/worker/src/Console/WorkerStatusCommand.php`
- [ ] `framework/packages/platform/worker/src/Runtime/WorkerStateStore.php`
- [ ] `framework/packages/platform/worker/src/Runtime/WorkerPoolSpec.php`
- [ ] `framework/packages/platform/worker/src/Runtime/WorkerPoolState.php`

Docs:
- [ ] `docs/adr/ADR-0017-worker-manager-application-worker.md`
- [ ] `docs/architecture/worker.md` — MUST include:
  - [ ] process model (master + N workers) and driver selection (`pcntl` vs `proc_open`)
  - [ ] reset discipline between UoW is achieved only transitively via `Coretsia\Kernel\Runtime\KernelRuntimeInterface`
    (`begin -> before hooks -> task -> after hooks -> ResetOrchestrator::resetAll()`).
  - [ ] `platform/worker` MUST NOT call hooks or `ResetOrchestrator` directly.
  - [ ] safety limits (`max_requests`, graceful shutdown, stop timeout)
  - [ ] ops notes (pid/state files, control transport, redaction rules)

Configuration:
- [ ] `framework/packages/platform/worker/config/worker.php`
- [ ] `framework/packages/platform/worker/config/rules.php`

Wiring / tags constants:
- [ ] `framework/packages/platform/worker/src/Provider/Tags.php` — local tag constants to avoid typos
  (allowed because `platform/cli` is a forbidden compile-time dependency here):
  - [ ] `public const CLI_COMMAND = 'cli.command';`
  - [ ] Policy:
    - [ ] The canonical string value is cemented (`cli.command`).
    - [ ] This local mirror constant is allowed only because `platform/cli` is a forbidden compile-time dependency here, per `docs/ssot/tags.md`.
    - [ ] `framework/tools/gates/tag_constant_mirror_gate.php` MUST verify that the local mirror constant equals the canonical string exactly.
    - [ ] `framework/packages/platform/worker/src/Provider/Tags.php` is package-internal convenience only and MUST NOT be treated as public API.

Artifacts (runtime outputs):
- [ ] `skeleton/var/tmp/worker.pid`
- [ ] `skeleton/var/tmp/worker.sock` — only when resolved `worker.control.transport = unix`
- [ ] `skeleton/var/tmp/worker.state.json`
- [ ] `skeleton/var/tmp/worker.stop`

Tests:
- [ ] `framework/packages/platform/worker/tests/Unit/WorkerManagerLifecycleTest.php`
- [ ] `framework/packages/platform/worker/tests/Unit/SocketProtocolDoesNotLeakPayloadTest.php`
- [ ] `framework/packages/platform/worker/tests/Integration/WorkerHandlesMultipleTasksSequentiallyTest.php`
- [ ] `framework/packages/platform/worker/tests/Integration/MaxRequestsTriggersRecycleTest.php`
- [ ] `framework/packages/platform/worker/tests/Integration/Worker/LongRunningWorkerSmokeTest.php`
- [ ] `framework/packages/platform/worker/tests/Fixtures/WorkerApp/config/modes/micro.php`
  - [ ] fixture app MUST express module enable/disable through a mode override, not through `config/modules.php`
- [ ] `framework/packages/platform/worker/tests/Integration/WorkerStartRejectsHttpTaskWithoutPlatformHttpModuleTest.php`

Add contract-style tests (Phase 0 aligned):
- [ ] `framework/packages/platform/worker/tests/Contract/WorkerStateJsonContainsNoAbsolutePathsTest.php`
  - [ ] asserts no `/home/`, `/Users/`, `(?i)\b[A-Z]:(\\|/)`, `\\server\share` patterns
- [ ] `framework/packages/platform/worker/tests/Contract/WorkerStateJsonDoesNotContainRawEndpointTest.php`
  - [ ] asserts raw socket path / raw host:port are absent; only hashes allowed
- [ ] `framework/packages/platform/worker/tests/Contract/WorkerStateJsonIsDeterministicTest.php`
  - [ ] asserts stable JSON bytes for the same inputs (key order + LF + final newline)
- [ ] `framework/packages/platform/worker/tests/Contract/WorkerRuntimeDoesNotWriteToStdoutTest.php`
  - [ ] mandatory contract lock because stdout/stderr direct writes are a hard policy ban for runtime source scope
  - [ ] asserts no stdout/stderr sinks are used in runtime source scope (token-based scan; tests/fixtures excluded)

Add config contract tests (policy rails):
- [ ] `framework/packages/platform/worker/tests/Contract/WorkerConfigSubtreeShapeContractTest.php`
  - [ ] MUST fail if `config/worker.php` repeats root (`['worker'=>...]` is forbidden; subtree only)
  - [ ] MUST fail if any `@*` key exists under returned subtree (any depth)
- [ ] `framework/packages/platform/worker/tests/Contract/WorkerConfigRejectsAbsolutePathsContractTest.php`
  - [ ] rejects `/home/...`, `/Users/...`, `(?i)\b[A-Z]:(\\|/)`, `\\server\share` patterns
- [ ] `framework/packages/platform/worker/tests/Contract/WorkerConfigRejectsFloatValuesContractTest.php`
  - [ ] rejects any float anywhere under `worker.*` subtree (safe deterministic messages)

- [ ] `framework/packages/platform/worker/tests/Integration/WorkerStartRejectsRuntimeDriverConflictTest.php`
  - [ ] asserts `worker:start` invokes `RuntimeDriverGuard`
  - [ ] asserts conflict is surfaced with:
    - [ ] `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`
- [ ] `framework/packages/platform/worker/tests/Integration/WorkerHttpTaskRequiresRequestHandlerTest.php`
  - [ ] covers the later DI/runtime failure only after runtime-driver/module compatibility has already passed
  - [ ] asserts `worker.task_type=http` hard-fails deterministically when `Psr\Http\Server\RequestHandlerInterface` is not resolvable

#### Modifies

- [ ] deptrac expectations updated (if needed) — (exact path owned elsewhere; referenced only)
- [ ] `docs/ssot/config-roots.md`
  - [ ] add canonical registry row:
    - [ ] `worker` | `platform/worker` | `framework/packages/platform/worker/config/worker.php` | `framework/packages/platform/worker/config/rules.php` | long-running worker runtime root
  - [ ] once this row is added, the earlier “future reserved identifier: worker” note from `1.20.0` is considered resolved and MUST NOT remain as a second active source of truth
- [ ] `docs/adr/INDEX.md` — register:
  - [ ] `docs/adr/ADR-0017-worker-manager-application-worker.md`

#### Package skeleton (if type=package)

- [ ] `framework/packages/platform/worker/composer.json`
  - [ ] MUST require runtime packages:
    - [ ] `psr/container`
    - [ ] `psr/log`
    - [ ] `psr/http-message`
    - [ ] `psr/http-server-handler`
- [ ] `framework/packages/platform/worker/src/Module/WorkerModule.php`
- [ ] `framework/packages/platform/worker/src/Provider/WorkerServiceProvider.php`
- [ ] `framework/packages/platform/worker/config/worker.php`
- [ ] `framework/packages/platform/worker/config/rules.php`
- [ ] `framework/packages/platform/worker/README.md`

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `framework/packages/platform/worker/config/worker.php`
- [ ] Keys (dot):
  - [ ] `worker.enabled` = false
  - [ ] `worker.workers` = 4
  - [ ] `worker.max_requests` = 1000
  - [ ] `worker.task_type` = "queue"  # allowed: "http" | "queue"
  - [ ] `worker.socket_path` = "skeleton/var/tmp/worker.sock"
  - [ ] `worker.pid_path` = "skeleton/var/tmp/worker.pid"
  - [ ] `worker.driver` = "auto" # "pcntl"|"proc"
  - [ ] `worker.control.transport` = "auto" # "unix"|"tcp"
  - [ ] `worker.tcp.host` = "127.0.0.1"
  - [ ] `worker.tcp.port` = 9327
  - [ ] `worker.state_path` = "skeleton/var/tmp/worker.state.json"
  - [ ] `worker.stop_flag_path` = "skeleton/var/tmp/worker.stop"
  - [ ] `worker.stop_timeout_ms` = 3000
- [ ] Rules:
  - [ ] `framework/packages/platform/worker/config/rules.php` enforces shape
  - [ ] **Reserved namespace parity (cemented):**
    - [ ] any key starting with `@` under `worker` subtree (any depth) MUST hard-fail
  - [ ] **No absolute paths (cemented):**
    - [ ] `worker.socket_path`, `worker.pid_path`, `worker.state_path`, `worker.stop_flag_path` MUST be relative paths (reject POSIX/Windows/UNC absolute forms deterministically)
  - [ ] **No floats (cemented):**
    - [ ] numeric keys under `worker.*` MUST be `int` only (reject float/NaN/INF)
  - [ ] **Explicit enum / bounds rules (cemented):**
    - [ ] `worker.enabled` MUST be `bool`
    - [ ] `worker.workers` MUST be `int > 0`
    - [ ] `worker.max_requests` MUST be `int > 0`
    - [ ] `worker.task_type` MUST be exactly one of: `http|queue`
    - [ ] `worker.driver` MUST be exactly one of: `auto|pcntl|proc`
    - [ ] `worker.control.transport` MUST be exactly one of: `auto|unix|tcp`
    - [ ] `worker.tcp.host` MUST be a non-empty string
    - [ ] `worker.tcp.port` MUST be an `int` in range `1..65535`
    - [ ] `worker.stop_timeout_ms` MUST be `int >= 0`

- [ ] Auto-resolution rules (single-choice; cemented):
  - [ ] `worker.driver=auto` resolves deterministically:
    - [ ] `pcntl` when `pcntl_fork` is available and the platform is not Windows
    - [ ] otherwise `proc`
  - [ ] `worker.control.transport=auto` resolves deterministically:
    - [ ] `unix` when the resolved driver is `pcntl` and unix domain sockets are supported
    - [ ] otherwise `tcp`
  - [ ] when the resolved transport is `tcp`, `worker.tcp.port` MUST be an explicit fixed port in range `1..65535`
  - [ ] `worker.tcp.port = 0` is forbidden because it makes endpoint identity and `worker.state.json` non-deterministic across runs

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (uses global `cli.command`)
- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `WorkerManager`, `ApplicationWorker`, task factories, communication services
  - [ ] tags worker CLI command services with canonical tag `cli.command`
  - [ ] command-name metadata/schema for `cli.command` is owned only by `platform/cli`
  - [ ] this epic MUST NOT define a competing `cli.command` meta contract
  - [ ] until the owner `platform/cli` epic cements tag metadata semantics, the only hard requirement here is the canonical tag string equality (`cli.command`) and successful command discovery through the CLI owner implementation

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `skeleton/var/tmp/worker.pid`
  - [ ] `skeleton/var/tmp/worker.sock` — only when resolved `worker.control.transport = unix`
  - [ ] `skeleton/var/tmp/worker.state.json` serialization MUST be produced via:
    - [ ] `Coretsia\Foundation\Serialization\StableJsonEncoder`
  - [ ] Rationale:
    - [ ] prevents duplicated “stable json” implementations across runtime packages
    - [ ] guarantees identical byte rules (sorted keys, LF-only, final newline, float rejection)
  - [ ] Direct `json_encode(...)` usage is FORBIDDEN for this artifact unless it is wrapped to provide identical canonical behavior.
  - [ ] `skeleton/var/tmp/worker.state.json` — MUST NOT contain secrets; store only redacted/hashed endpoint identifiers (e.g., `hash(socket_path)`).
  - [ ] `skeleton/var/tmp/worker.state.json` MUST have a cemented schema (single-choice):
    - [ ] `version` = 1
    - [ ] `pid` (int)
    - [ ] `worker_count` (int)
    - [ ] `driver_requested` ("auto"|"pcntl"|"proc")
    - [ ] `driver` ("pcntl"|"proc") — RESOLVED driver (no "auto" here)
    - [ ] `control_transport_requested` ("auto"|"unix"|"tcp")
    - [ ] `control_transport` ("unix"|"tcp") — RESOLVED transport (no "auto" here)
    - [ ] `endpoint_hash` (string) — MUST be `sha256(<endpoint_identifier>)`, never raw socket path/host/port
      - [ ] `<endpoint_identifier>` canonicalization (single-choice; cemented):
        - [ ] if `control_transport` = `unix`:
          - [ ] `unix:` + `<socket_path>` EXACTLY as configured (no realpath, no absolute-path expansion)
        - [ ] if `control_transport` = `tcp`:
          - [ ] `tcp:` + `<host>` + `:` + `<port>`
          - [ ] `<port>` MUST be the RESOLVED port actually in use (int), but MUST NOT be written raw anywhere (hash only)
      - [ ] Hash encoding (single-choice):
        - [ ] lowercase hex of sha256 digest
    - [ ] `started_at` is FORBIDDEN (no timestamps)
    - [ ] `env` is FORBIDDEN
  - [ ] JSON encoding MUST be stable:
    - [ ] key ordering MUST be deterministic (byte-order) at all nesting levels
    - [ ] output MUST be LF-only and end with final newline
  - [ ] The file MUST NOT contain:
    - [ ] raw socket path, raw tcp host/port, absolute paths, tokens, payloads
  - [ ] `skeleton/var/tmp/worker.stop`
- [ ] Reads:
  - [ ] `skeleton/var/cache/<appId>/container.php` (optional)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [ ] Context reads:
  - [ ] `ContextKeys::CORRELATION_ID`
  - [ ] `ContextKeys::UOW_ID`
  - [ ] `ContextKeys::UOW_TYPE`
- [ ] Context writes:
  - N/A directly in `platform/worker`
  - [ ] worker passes the UoW type (`http|queue`) into `Coretsia\Kernel\Runtime\KernelRuntimeInterface`;
    KernelRuntime owns base `ContextStore` writes
- [ ] Reset discipline:
  - [ ] each task is executed as a separate UoW via `Coretsia\Kernel\Runtime\KernelRuntimeInterface`
  - [ ] after each task, reset happens only through the standard KernelRuntime flow:
    - [ ] begin → hooks → task → after → `ResetOrchestrator::resetAll()`
  - [ ] worker MUST NOT know or enumerate the reset discovery tag
  - [ ] worker MUST NOT call `ResetOrchestrator::resetAll()` directly
  - [ ] worker enablement controls only the worker runtime loop
  - [ ] it MUST NOT disable or redefine Foundation reset infrastructure

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `worker.process` (attrs: worker_id, pid, outcome)
  - [ ] `worker.task` (attrs: operation, outcome)
- [ ] Metrics:
  - [ ] `worker.process_total` (labels: `status`)
  - [ ] `worker.task_total` (labels: `operation`, `outcome`)
  - [ ] `worker.task_duration_ms` (labels: `operation`, `outcome`)
- [ ] Logs:
  - [ ] start/stop/restart summary only; no payload; socket path hashed

- Label/attribute normalization (MUST):
  - resolved task operation id → metric label `operation` and span attribute `operation`
  - resolved task type → ContextStore key `uow_type` ONLY (MUST NOT be a metric label)

- Redaction reminders:
  - No payload logging anywhere (stdout/stderr/log files/socket protocol).
  - Any socket/control endpoint info MUST be redacted/hashed in logs/state dumps.

#### Errors

- [ ] Exceptions introduced:
  - [ ] `framework/packages/platform/worker/src/Exception/WorkerException.php` — errorCodes:
    - [ ] `CORETSIA_WORKER_START_FAILED`
    - [ ] `CORETSIA_WORKER_FORK_FAILED`
    - [ ] `CORETSIA_WORKER_COMMUNICATION_FAILED`

- [ ] Runtime driver conflicts / invalid runtime composition:
  - [ ] MUST be enforced by `Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard` (owner: guard epic; SSoT: 1.260.0).
  - [ ] `platform/worker` MUST NOT introduce its own “driver conflict” error codes to avoid drift.
  - [ ] On guard failure, worker:start MUST surface the guard’s deterministic codes (single-choice; cemented by SSoT):
    - [ ] `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`
    - [ ] `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`

- [ ] `WorkerException` messages MUST be stable and MUST NOT include:
  - [ ] absolute paths (POSIX/Windows/UNC),
  - [ ] raw socket paths, raw tcp endpoints,
  - [ ] payload fragments, headers, tokens.

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] raw socket path, any payload
- [ ] Allowed:
  - [ ] pid, worker_id, operation, outcome, `hash(socket_path)`
- [ ] Worker runtime MUST NOT write to stdout/stderr directly:
  - [ ] forbidden: `echo`, `print`, `var_dump`, `print_r`, `printf`, `error_log`
  - [ ] forbidden: `php://stdout|php://stderr|php://output`, `STDOUT|STDERR` writes
- [ ] Allowed channels:
  - [ ] Logs via `Psr\Log\LoggerInterface` only (redacted, no payload).
  - [ ] CLI output only via the CLI output abstraction (no raw sinks).
- [ ] Socket/control protocol MUST be payload-free:
  - [ ] MUST NOT transmit raw task payloads over control channel
  - [ ] MUST NOT log raw payloads (ever)
  - [ ] operation identifiers MUST be safe strings; any identifiers that resemble paths/endpoints MUST be hashed.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] deterministic auto-resolution:
  - [ ] `framework/packages/platform/worker/tests/Unit/WorkerAutoDriverAndTransportResolutionTest.php`
- [ ] tcp port zero is forbidden deterministically:
  - [ ] `framework/packages/platform/worker/tests/Contract/WorkerConfigRejectsTcpPortZeroContractTest.php`

#### Required policy tests matrix

- [ ] if effective reset discovery is used → `framework/packages/platform/worker/tests/Integration/WorkerHandlesMultipleTasksSequentiallyTest.php`
- [ ] If redaction exists → `framework/packages/platform/worker/tests/Unit/SocketProtocolDoesNotLeakPayloadTest.php`

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `framework/packages/platform/worker/tests/Fixtures/WorkerApp/config/modes/micro.php`
  - [ ] fixture app expresses module enable/disable through a mode override, never through `config/modules.php`

### Tests (MUST)

- Unit:
  - [ ] `framework/packages/platform/worker/tests/Unit/WorkerManagerLifecycleTest.php`
  - [ ] `framework/packages/platform/worker/tests/Unit/SocketProtocolDoesNotLeakPayloadTest.php`
  - [ ] `framework/packages/platform/worker/tests/Unit/WorkerAutoDriverAndTransportResolutionTest.php`
- Contract:
  - [ ] `framework/packages/platform/worker/tests/Contract/WorkerStateJsonContainsNoAbsolutePathsTest.php`
  - [ ] `framework/packages/platform/worker/tests/Contract/WorkerStateJsonDoesNotContainRawEndpointTest.php`
  - [ ] `framework/packages/platform/worker/tests/Contract/WorkerStateJsonIsDeterministicTest.php`
  - [ ] `framework/packages/platform/worker/tests/Contract/WorkerConfigSubtreeShapeContractTest.php`
  - [ ] `framework/packages/platform/worker/tests/Contract/WorkerConfigRejectsAbsolutePathsContractTest.php`
  - [ ] `framework/packages/platform/worker/tests/Contract/WorkerConfigRejectsFloatValuesContractTest.php`
  - [ ] `framework/packages/platform/worker/tests/Contract/WorkerRuntimeDoesNotWriteToStdoutTest.php`
  - [ ] `framework/packages/platform/worker/tests/Contract/WorkerStateJsonSchemaVersionIsFixedContractTest.php`
    - [ ] asserts `version = 1`
    - [ ] asserts field name remains exactly `version`
  - [ ] `framework/packages/platform/worker/tests/Contract/WorkerConfigRejectsTcpPortZeroContractTest.php`
- Integration:
  - [ ] `framework/packages/platform/worker/tests/Integration/WorkerHandlesMultipleTasksSequentiallyTest.php`
  - [ ] `framework/packages/platform/worker/tests/Integration/MaxRequestsTriggersRecycleTest.php`
  - [ ] `framework/packages/platform/worker/tests/Integration/Worker/LongRunningWorkerSmokeTest.php`
  - [ ] `framework/packages/platform/worker/tests/Integration/WorkerStartRejectsRuntimeDriverConflictTest.php`
    - [ ] asserts failure is surfaced from `RuntimeDriverGuard`
    - [ ] asserts exact code `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`
    - [ ] asserts failure happens before `Psr\Http\Server\RequestHandlerInterface` resolution
    - [ ] asserts worker pool/process start side effects do not occur before this failure
  - [ ] `framework/packages/platform/worker/tests/Integration/WorkerStartRejectsHttpTaskWithoutPlatformHttpModuleTest.php`
    - [ ] asserts exact code `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`
    - [ ] asserts failure happens before `Psr\Http\Server\RequestHandlerInterface` resolution
    - [ ] asserts worker pool/process start side effects do not occur before this failure
  - [ ] `framework/packages/platform/worker/tests/Integration/WorkerHttpTaskRequiresRequestHandlerTest.php`
- Gates/Arch:
  - [ ] deptrac expectations updated (if needed)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Observability policy satisfied (names + label allowlist + redaction)
- [ ] Tests: unit + contract + integration + E2E pass
- [ ] Docs updated:
  - [ ] `docs/architecture/worker.md`
  - [ ] `framework/packages/platform/worker/README.md`
  - [ ] `docs/adr/ADR-0017-worker-manager-application-worker.md`
  - [ ] `docs/ssot/config-roots.md`

---

### 1.370.0 Framework: AppBuilder + boot smoke suite (MUST) [TOOLING]

---
type: skeleton
phase: 1
epic_id: "1.370.0"
owner_path: "framework/packages/core/kernel/tests/"

goal: "Є 2 smoke тести: micro boot стабільно працює, express — стабільно падає з очікуваним кодом (до Phase 2)."
provides:
- "Test harness для boot сценаріїв (micro/express) без вимоги skeleton/config/modes/*.php"
- "Locks очікувань Phase 0: micro OK; express deterministic fail (required missing)"
- "CI proof: integration-fast executes these invariants"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.310.0 — framework default presets exist in `resources/modes/*.php`
  - 1.310.0 — deterministic code `CORETSIA_MODULE_REQUIRED_MISSING` exists
  - 1.340.0 — REAL compiled container artifact + artifact-only boot policy exist

- Terminology note (MUST): config root vs config key namespaces
  - Config root for Kernel is **`kernel`** (file: `framework/packages/core/kernel/config/kernel.php`).
  - Any dotted prefixes like `kernel.uow.*`, `kernel.runtime.*`, `kernel.modules.*`, `kernel.config.*`, `kernel.artifacts.*`, `kernel.fingerprint.*`
    are **config key namespaces**, not separate roots.
  - `config/<name>.php` MUST return subtree for `<name>` (no wrapper array repeating the root key).

- Required deliverables (exact paths):
  - `framework/packages/core/kernel/resources/modes/*.php` — presets

- Required contracts / ports:
  - `Coretsia\Contracts\Module\ModePresetLoaderInterface`
  - `Coretsia\Contracts\Module\ManifestReaderInterface`

#### Compile-time deps (deptrac-enforceable) (MUST)

N/A (tests/tooling)

### Entry points / integration points (MUST)

- CLI:
  - `composer test` / CI `integration-fast` executes these tests

### Deliverables (MUST)

#### Creates

- [ ] `framework/packages/core/kernel/tests/Support/AppBuilder.php` — helper to boot fixture apps deterministically
  - [ ] MUST prepare the same compiled artifacts the runtime expects after `1.340.0` before asserting boot behavior:
    - [ ] `module-manifest.php`
    - [ ] `config.php`
    - [ ] `container.php`
  - [ ] MUST NOT bypass artifact-only boot policy in production-like paths
  - [ ] express smoke MAY fail during compile/boot pipeline, but MUST fail with deterministic code `CORETSIA_MODULE_REQUIRED_MISSING`
- [ ] `framework/packages/core/kernel/tests/Integration/BootMicroPresetTest.php`
- [ ] `framework/packages/core/kernel/tests/Integration/BootExpressPresetTest.php`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Security / Redaction

- [ ] Tests MUST NOT print secrets; use deterministic error codes only.

### Phase 0 parity: deterministic boot smoke locks (MUST)

Boot smoke suite MUST бути детермінований і safety-compliant (узгоджено з Phase 0 rails 0.20.0):

#### Output-free invariant (MUST)
Тести/хелпери (`AppBuilder`, boot harness) MUST NOT друкувати секрети або runtime details.
Assertions MUST базуватись на deterministic error codes (не на stdout/stderr текстах).

#### No path leaks (MUST)
Будь-які exception messages, які тест читає/асертає, MUST NOT містити:
- абсолютні machine paths
- OS-dependent error messages з paths
  Allowed: deterministic code + fixed reason tokens.

#### Deterministic ordering (MUST)
Якщо harness збирає списки (modules, presets, warnings), він MUST:
- sort by byte-order (`strcmp`)
- be locale-independent

#### Compatibility lock intent (MUST)
Ці 2 smoke тести є “цементом” очікувань до Phase 2:
- Micro boot: success deterministic
- Express boot: deterministic fail з `CORETSIA_MODULE_REQUIRED_MISSING`
  Тести MUST бути rerun-stable (same inputs → same result), без залежності від локального середовища.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] Smoke suite:
  - [ ] `framework/packages/core/kernel/tests/Integration/BootMicroPresetTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/BootExpressPresetTest.php`

### Tests (MUST)

- Integration:
  - [ ] `framework/packages/core/kernel/tests/Integration/BootMicroPresetTest.php`
  - [ ] `framework/packages/core/kernel/tests/Integration/BootExpressPresetTest.php`

### DoD (MUST)

- [ ] Micro boot test green
- [ ] Express boot asserts deterministic failure code until Phase 2 cutline
- [ ] Smoke harness remains valid after artifact-only boot policy:
  - [ ] fixture app is compiled first
  - [ ] boot then uses compiled artifacts instead of bypassing the real runtime path
- [ ] Дає тестовий harness для boot сценаріїв (micro/express) без вимоги існування `skeleton/config/modes/*.php`.
- [ ] Цементує очікування: micro boot OK, express boot може deterministic fail до Phase 2 cutline.
- [ ] Non-goals / out of scope
  - [ ] Не реалізує HTTP runtime; лише boot.
- [ ] Є 2 smoke тести, що доводять: micro boot стабільно працює, express — стабільно падає з очікуваним кодом (до Phase 2).
- [ ] When booting Express fixture in Phase 0, then it fails with `CORETSIA_MODULE_REQUIRED_MISSING` deterministically.

---

## 1.380.0 Cyclic Dependencies Gate (package level) (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.380.0"
owner_path: "framework/tools/gates/"

goal: "Automatically detect and block cyclic dependencies between packages at compile time."
provides:
- "Deterministic cyclic dependency detection via deptrac or direct graph analysis"
- "Single canonical gate that fails on cycles with stable error code"
- "Integration into CI rails to prevent merging with cycles"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.30.0 — spikes boundary enforcement gate exists (tools/gates/ infrastructure)
  - 0.40.0 — internal-toolkit exists (for stable JSON if needed)
  - 0.80.0 — deptrac generator prototype exists (provides cycle detection logic)
  - 1.50.0 — tooling baseline + arch-rails exist (CI jobs for gates)

- Required deliverables (exact paths):
  - `framework/tools/gates/` — gates directory exists
  - `framework/tools/spikes/_support/ConsoleOutput.php` — canonical output writer
  - `framework/tools/spikes/_support/ErrorCodes.php` — error codes registry
  - `framework/tools/spikes/_support/bootstrap.php` — tools bootstrap

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `devtools/internal-toolkit` (for Path normalization)
- (no runtime deps)

Forbidden:

- `core/*`
- `platform/*`
- `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- Composer:
  - `composer cyclic-dependencies:gate` — runs the cyclic dependencies gate (root + workspace mirror)
- CI:
  - MUST be executed in Phase 1 arch rails, e.g., before `spike:test` or in dedicated `arch` job.

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/gates/cyclic_dependencies_gate.php` — deterministic gate:
  - [ ] MUST reuse the canonical dependency-graph inputs already promoted by `0.80.0` + `1.50.0`
  - [ ] MUST NOT introduce an independent second package-graph implementation that can drift from arch rails
  - [ ] detects/report cycles deterministically from the canonical graph source
  - [ ] if a cycle is found, prints `CORETSIA_CYCLIC_DEPENDENCY_DETECTED` on line 1
  - [ ] next lines: list of cycle paths in stable order (sorted by package id, then cycle representation)
  - [ ] uses `ConsoleOutput` for output, follows Phase 0 gate output policy
  - [ ] supports `--path` override for testing on synthetic trees
  - [ ] exit code 0 on pass, 1 on fail

#### Modifies

- [ ] `composer.json` — add mirror scripts (delegates to framework):
  - [ ] `cyclic-dependencies:gate` → `@composer --no-interaction --working-dir=framework run-script cyclic-dependencies:gate --`
- [ ] `framework/composer.json` — add gate script
  - [ ] `cyclic-dependencies:gate` → `@php tools/gates/cyclic_dependencies_gate.php`
- [ ] `.github/workflows/ci.yml` — add gate execution to `arch` job or a new `gate` job
- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_CYCLIC_DEPENDENCY_DETECTED`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A

#### Observability (policy-compliant)

- [ ] Output is stable and safe:
  - [ ] line1: CODE only
  - [ ] line2+: package ids and cycle edges only (no absolute paths, no secrets)

#### Errors

- [ ] Deterministic error code: `CORETSIA_CYCLIC_DEPENDENCY_DETECTED`

#### Security / Redaction

- [ ] MUST NOT leak any package contents, only package ids.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [ ] Integration test in `framework/tools/tests/Integration/CyclicDependenciesGateTest.php`:
  - [ ] creates a synthetic tree with a cycle, runs gate with `--path`, asserts failure with expected code.
  - [ ] creates a tree without cycles, asserts pass.

### Tests (MUST)

- Integration:
  - [ ] `framework/tools/tests/Integration/CyclicDependenciesGateTest.php`

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Gate is deterministic and integrated into CI
- [ ] Error code registered and used
- [ ] Verification test exists and passes

---

## 1.390.0 Deprecated API Gate (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.390.0"
owner_path: "framework/tools/gates/"

goal: "Prevent usage of deprecated APIs in new code by scanning for `@deprecated` docblocks and failing if referenced outside allowed contexts."
provides:
- "Deterministic detection of deprecated symbol usage"
- "Configurable allowlist for legacy exceptions"
- "Integration into CI to block merging with new deprecation usage"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.30.0 — spikes boundary gate infrastructure exists
  - 0.40.0 — internal-toolkit exists
  - 1.50.0 — tooling baseline exists

- Required deliverables (exact paths):
  - `framework/tools/gates/` — gates directory
  - `framework/tools/spikes/_support/ConsoleOutput.php`
  - `framework/tools/spikes/_support/ErrorCodes.php`
  - `framework/tools/spikes/_support/bootstrap.php`

- Required config roots/keys:
  - none

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `devtools/internal-toolkit` (Path)

Forbidden:

- `core/*`, `platform/*`, `integrations/*`

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- Composer:
  - `composer deprecated-api:gate`
- CI: executed in arch rails.

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/config/deprecated_allowlist.php` — optional tooling allowlist for approved legacy exceptions
- [ ] `framework/tools/gates/deprecated_api_gate.php` — deterministic gate:
  - [ ] scans `framework/packages/**/src/**/*.php` (excluding tests/fixtures)
  - [ ] builds a map of deprecated symbols (classes, methods, functions) by parsing `@deprecated` in docblocks
  - [ ] scans all other PHP files under `framework/packages/**/src/` for usage of those symbols
  - [ ] token-based detection (like 0.30.0)
  - [ ] if any usage found, prints `CORETSIA_DEPRECATED_API_USAGE_DETECTED` + list of files with offending lines (repo-relative paths)
  - [ ] supports the exact optional allowlist path `framework/tools/config/deprecated_allowlist.php` to ignore known legacy usages
  - [ ] output follows Phase 0 gate policy

#### Modifies

- [ ] `composer.json` — add mirror scripts (delegates to framework):
  - [ ] `deprecated-api:gate` → `@composer --no-interaction --working-dir=framework run-script deprecated-api:gate --`
- [ ] `framework/composer.json` — add gate script
  - [ ] `deprecated-api:gate` → `@php tools/gates/deprecated_api_gate.php`
- [ ] `.github/workflows/ci.yml` — add gate to arch job
- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register `CORETSIA_DEPRECATED_API_USAGE_DETECTED`

### Cross-cutting

#### Observability

- [ ] Output contains only repo-relative paths and symbol names; no code snippets.

#### Errors

- [ ] Deterministic code: `CORETSIA_DEPRECATED_API_USAGE_DETECTED`

#### Security / Redaction

- [ ] No leakage of code contents; only paths and symbol names.

### Verification

- [ ] Integration test: create synthetic package with a deprecated class and a usage outside allowlist; gate fails.

### Tests

- [ ] `framework/tools/tests/Integration/DeprecatedApiGateTest.php`

### DoD

- [ ] Gate implemented and integrated
- [ ] Error code registered
- [ ] Verification test exists

---

## 1.400.0 Composer Audit Gate (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.400.0"
owner_path: "framework/tools/gates/"

goal: "Automatically run `composer audit` on all audit-capable install roots in the monorepo (repo root, `framework/`, `skeleton/`) and fail if any vulnerabilities are found."
provides:
- "Deterministic vulnerability scan via composer audit for audit-capable install roots only."
- "Stable output parsing to avoid false positives from varying output formats"
- "Integration into CI to block vulnerable dependencies"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.100.0 — workspace sync exists (ensures composer.json are managed)
  - 1.50.0 — tooling baseline exists

- Required deliverables:
  - `framework/composer.json`, `skeleton/composer.json`, root `composer.json` exist.
  - `composer` available in CI.

#### Compile-time deps

N/A (gate runs composer as external process)

Forbidden:

- `core/*`, etc. (not relevant)

### Entry points / integration points (MUST)

- Composer:
  - `composer composer-audit:gate`
- CI: run in security job or arch job.

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/gates/composer_audit_gate.php` — deterministic gate:
  - [ ] locates audit-capable install roots only:
    - [ ] repo root
    - [ ] `framework/`
    - [ ] `skeleton/`
  - [ ] package manifests under `framework/packages/**` MUST NOT be audited directly by this gate
  - [ ] for each, runs `composer audit --format=json` in the corresponding directory
  - [ ] parses JSON output, checks for `advisories` count > 0
  - [ ] if any advisories, prints `CORETSIA_COMPOSER_AUDIT_FAILED` + list of affected packages and advisories (sanitized)
  - [ ] uses `ConsoleOutput` for output
  - [ ] if `composer` command fails (not found), prints `CORETSIA_COMPOSER_AUDIT_SCAN_FAILED`
  - [ ] supports `--path` override for testing

#### Modifies

- [ ] `composer.json` — add mirror scripts (delegates to framework):
  - [ ] `composer-audit:gate` → `@composer --no-interaction --working-dir=framework run-script composer-audit:gate --`
- [ ] `framework/composer.json` — add gate script
  - [ ] `composer-audit:gate` → `@php tools/gates/composer_audit_gate.php`
- [ ] `.github/workflows/ci.yml` — add audit step
- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_COMPOSER_AUDIT_FAILED`
  - [ ] `CORETSIA_COMPOSER_AUDIT_SCAN_FAILED`

### Cross-cutting

#### Observability

- [ ] Output contains only package names and advisory IDs; no full advisory details (to avoid noise).

#### Errors

- [ ] Deterministic codes for vulnerability found and scan failure.

#### Security / Redaction

- [ ] `composer audit` output may contain URLs; we strip to only advisory IDs and package names.

### Verification

- [ ] Integration test MUST NOT call live Packagist or live advisory services.
- [ ] `framework/tools/tests/Fixtures/ComposerAudit/audit_clean.json` — captured clean output fixture
- [ ] `framework/tools/tests/Fixtures/ComposerAudit/audit_with_advisories.json` — captured advisory output fixture
- [ ] `framework/tools/tests/Fixtures/ComposerAudit/audit_scan_failed.json` — captured process-failure fixture
- [ ] `framework/tools/tests/Integration/ComposerAuditGateTest.php` MUST use mocked process output / fixtures only and assert deterministic codes for:
  - [ ] advisory found → `CORETSIA_COMPOSER_AUDIT_FAILED`
  - [ ] scan failure → `CORETSIA_COMPOSER_AUDIT_SCAN_FAILED`

### Tests

- [ ] `framework/tools/tests/Integration/ComposerAuditGateTest.php` (mocks composer output).

### DoD

- [ ] Gate implemented and integrated
- [ ] Error codes registered
- [ ] Verification test exists (even if mocked)

---

## 1.410.0 Unified Code Style Gate (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.410.0"
owner_path: "framework/tools/gates/"

goal: "Enforce a single coding style across all packages using PHP CS Fixer with a deterministic configuration."
provides:
- "Deterministic code style check via PHP CS Fixer"
- "Canonical `.php-cs-fixer.dist.php` config at repo root"
- "CI gate that fails on style violations"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.50.0 — tooling baseline exists
  - PHP CS Fixer installed as dev dependency (root `composer.json` require-dev)

- Required deliverables:
  - `composer.json` (root) with `friendsofphp/php-cs-fixer` in require-dev.

#### Compile-time deps

N/A

### Entry points / integration points (MUST)

- Composer:
  - `composer cs:check` — dry-run check
  - `composer cs:fix` — apply fixes (local use only)
  - `code-style:gate`
- CI:
  - `composer cs:check` runs in arch job.

### Deliverables (MUST)

#### Creates

- [ ] `.php-cs-fixer.dist.php` — canonical config at repo root:
  - [ ] ruleset based on PSR-12 with additional rules (strict types, no unused imports, etc.)
  - [ ] config is deterministic (no random ordering)
- [ ] `framework/tools/gates/code_style_gate.php` — deterministic gate:
  - [ ] runs `php-cs-fixer fix --dry-run --diff --format=json` from repo root
  - [ ] parses JSON output; if files are modified, prints `CORETSIA_CODE_STYLE_VIOLATION` + list of files
  - [ ] uses `ConsoleOutput`
  - [ ] if php-cs-fixer not available, prints `CORETSIA_CODE_STYLE_GATE_SCAN_FAILED`
  - [ ] supports `--path` override for testing (but config still from repo root)

#### Modifies

- [ ] `composer.json` — add mirror scripts (delegates to framework):
  - [ ] `cs:check` → `@composer --no-interaction --working-dir=framework run-script cs:check --`
  - [ ] `cs:fix` → `@composer --no-interaction --working-dir=framework run-script cs:fix --`
  - [ ] `code-style:gate` → `@composer --no-interaction --working-dir=framework run-script code-style:gate --`
- [ ] `framework/composer.json` — add gate script
  - [ ] `cs:check` → `php-cs-fixer fix --dry-run --diff --format=json`
  - [ ] `cs:fix` → `php-cs-fixer fix`
  - [ ] `code-style:gate` → `@php tools/gates/code_style_gate.php`
- [ ] `.github/workflows/ci.yml` — add `cs:check` step
- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_CODE_STYLE_VIOLATION`
  - [ ] `CORETSIA_CODE_STYLE_GATE_SCAN_FAILED`

### Cross-cutting

#### Observability

- [ ] Output lists only file paths (repo-relative) that need fixing.

#### Errors

- [ ] Deterministic codes.

#### Security / Redaction

- [ ] No code contents printed; only paths.

### Verification

- [ ] Integration test: create a file with style violation, run gate with `--path` to a temp repo, assert failure.

### Tests

- [ ] `framework/tools/tests/Integration/CodeStyleGateTest.php`

### DoD

- [ ] Config created
- [ ] gate implemented
- [ ] CI integrated.

---

## 1.420.0 Test Coverage Gate (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.420.0"
owner_path: "framework/tools/gates/"

goal: "Ensure that critical packages have sufficient test coverage by analyzing PHPUnit coverage reports and enforcing thresholds."
provides:
- "Deterministic coverage threshold enforcement"
- "Configurable per-package coverage minimums"
- "CI gate that fails if coverage drops below allowed levels"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.50.0 — tooling baseline exists
  - PHPUnit with coverage enabled (pcov or xdebug) available in CI
  - `core/*` packages exist

- Required deliverables:
  - `framework/tools/testing/phpunit.xml` with coverage configuration.

#### Compile-time deps

N/A

### Entry points / integration points (MUST)

- Composer:
  - `composer coverage:test` — runs tests with coverage and generates clover XML
  - `composer coverage:gate` — runs the coverage gate
- CI:
  - after tests, run coverage gate.

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/gates/coverage_gate.php` — deterministic gate:
  - [ ] reads `clover.xml` from `framework/var/phpunit/coverage/`
  - [ ] parses coverage metrics per file/class
  - [ ] compares against thresholds defined in `framework/tools/config/coverage.php`
  - [ ] thresholds: per-package or per-directory minimum line coverage percentage
  - [ ] if any package below threshold, prints `CORETSIA_COVERAGE_BELOW_THRESHOLD` + list of packages with current coverage
  - [ ] uses `ConsoleOutput`
  - [ ] if coverage file missing, prints `CORETSIA_COVERAGE_GATE_SCAN_FAILED`
  - [ ] supports `--path` override for testing

- [ ] `framework/tools/config/coverage.php` — tooling-local coverage thresholds config (NOT a runtime config root)

#### Modifies

- [ ] `composer.json` — add mirror scripts (delegates to framework):
  - [ ] `coverage:test` → `@composer --no-interaction --working-dir=framework run-script coverage:test --`
  - [ ] `coverage:gate` → `@composer --no-interaction --working-dir=framework run-script coverage:gate --`
- [ ] `framework/composer.json` — add gate script
  - [ ] `coverage:test` → `vendor/bin/phpunit -c tools/testing/phpunit.xml --coverage-clover var/phpunit/coverage/clover.xml`
  - [ ] `coverage:gate` → `@php tools/gates/coverage_gate.php`
- [ ] `.github/workflows/ci.yml` — after `test` job, run coverage gate
- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_COVERAGE_BELOW_THRESHOLD`
  - [ ] `CORETSIA_COVERAGE_GATE_SCAN_FAILED`

### Cross-cutting

#### Observability

- [ ] Output: package name and current coverage percentage.

#### Errors

- [ ] Deterministic codes.

#### Security / Redaction

- [ ] No secrets; only package names and percentages.

### Verification

- [ ] Integration test: generate mock clover.xml with low coverage, run gate, assert failure.

### Tests

- [ ] `framework/tools/tests/Integration/CoverageGateTest.php`

### DoD

- [ ] Gate implemented
- [ ] config created
- [ ] CI integrated.

---

## 1.430.0 SOLID Architecture Enforcer Gate (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.430.0"
owner_path: "framework/tools/gates/"

goal: "Enforce architectural rules (e.g., layering, dependency direction) using deptrac with a canonical configuration."
provides:
- "Deterministic deptrac analysis with predefined layers and rules"
- "Integration into CI to block violations"
- "Single source of truth for architecture rules"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- docs/ssot/architecture-layers.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.80.0 — deptrac generator prototype exists
  - 1.50.0 — tooling baseline exists
  - `docs/ssot/architecture-layers.md` defines allowed dependencies.

- Required deliverables:
  - `framework/tools/testing/deptrac.yaml` — canonical deptrac config generated/owned by `1.50.0`

#### Compile-time deps

Depends on:

- `devtools/internal-toolkit` (for path normalization)

Forbidden:

- none

### Entry points / integration points (MUST)

- Composer:
  - `composer deptrac:analyze` — runs deptrac analysis
  - `composer architecture:gate` — runs the architecture gate
- CI:
  - run in arch job.

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/gates/architecture_gate.php` — deterministic gate:
  - [ ] MUST analyze the canonical deptrac config owned by `1.50.0` (`framework/tools/testing/deptrac.yaml`)
  - [ ] MUST NOT introduce a second architecture-rules/config brain
  - [ ] captures output, parses for violations
  - [ ] if violations found, prints `CORETSIA_ARCHITECTURE_VIOLATION` + list of violations (normalized paths, rule names)
  - [ ] uses `ConsoleOutput`
  - [ ] if deptrac not available, prints `CORETSIA_ARCHITECTURE_GATE_SCAN_FAILED`
  - [ ] supports `--path` override

#### Modifies

- [ ] `composer.json` — add mirror scripts (delegates to framework):
  - [ ] `deptrac:generate` → `@composer --no-interaction --working-dir=framework run-script deptrac:generate --`
  - [ ] `deptrac:analyze` → `@composer --no-interaction --working-dir=framework run-script deptrac:analyze --`
  - [ ] `architecture:gate` → `@composer --no-interaction --working-dir=framework run-script architecture:gate --`
- [ ] `framework/composer.json` — add gate script
  - [ ] `deptrac:generate` → `@php tools/gates/architecture_gate.php`
  - [ ] `deptrac:analyze` → `deptrac analyze tools/testing/deptrac.yaml`
  - [ ] `architecture:gate` → `@php tools/gates/architecture_gate.php`
- [ ] `.github/workflows/ci.yml` — add architecture analysis
- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_ARCHITECTURE_VIOLATION`
  - [ ] `CORETSIA_ARCHITECTURE_GATE_SCAN_FAILED`

### Cross-cutting

#### Observability

- [ ] Output: list of violations with file paths and rule descriptions.

#### Errors

- [ ] Deterministic codes.

#### Security / Redaction

- [ ] No code contents; only paths and rule names.

### Verification

- [ ] Integration test: create synthetic code with layer violation, run gate, assert failure.

### Tests

- [ ] `framework/tools/tests/Integration/ArchitectureGateTest.php`

### DoD

- [ ] Gate implemented, CI integrated.

---

## 1.440.0 Secret Leakage Gate (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.440.0"
owner_path: "framework/tools/gates/"

goal: "Prevent accidental commits of secrets (API keys, passwords, tokens) by scanning the entire repository with Gitleaks or a custom scanner."
provides:
- "Deterministic secret scanning"
- "Canonical configuration for allowed false positives"
- "CI gate that fails if any secret-like pattern is detected"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.50.0 — tooling baseline exists
  - Gitleaks installed in CI (or use a custom PHP scanner)

- Required deliverables:
  - `.gitleaks.toml` config file.

#### Compile-time deps

N/A (external tool)

### Entry points / integration points (MUST)

- Composer:
  - `composer secret-leakage:gate` — runs secret scan
- CI:
  - run in security job.

### Deliverables (MUST)

#### Creates

- [ ] `.gitleaks.toml` — canonical config with rules for detecting secrets, and allowlist for false positives (e.g., test keys)
- [ ] `framework/tools/gates/secret_leakage_gate.php` — deterministic gate:
  - [ ] runs `gitleaks detect --source=. --config=.gitleaks.toml --no-git` (or `--no-git` to scan working directory)
  - [ ] parses JSON output; if any leak, prints `CORETSIA_SECRET_LEAK_DETECTED` + list of files and findings (sanitized)
  - [ ] if gitleaks not available, prints `CORETSIA_SECRET_GATE_SCAN_FAILED`
  - [ ] uses `ConsoleOutput`
  - [ ] supports `--path` override

#### Modifies

- [ ] `composer.json` — add mirror scripts (delegates to framework):
  - [ ] `secret-leakage:gate` → `@composer --no-interaction --working-dir=framework run-script secret-leakage:gate --`
- [ ] `framework/composer.json` — add gate script
  - [ ] `secret-leakage:gate` → `@php tools/gates/secret_leakage_gate.php`
- [ ] `.github/workflows/ci.yml` — add secret scan step
- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_SECRET_LEAK_DETECTED`
  - [ ] `CORETSIA_SECRET_GATE_SCAN_FAILED`

### Cross-cutting

#### Observability

- [ ] Output: list of files and finding descriptions (redacted to avoid printing actual secrets).

#### Errors

- [ ] Deterministic codes.

#### Security / Redaction

- [ ] The gate must redact secrets from output; Gitleaks output already redacts, but we also ensure we don't print raw matches.

### Verification

- [ ] Integration test: create a file with a fake secret (e.g., `API_KEY=sk_live_123`), run gate, assert failure.

### Tests

- [ ] `framework/tools/tests/Integration/SecretLeakageGateTest.php` (may mock gitleaks output)

### DoD

- [ ] Gate implemented, config created, CI integrated.

---

## 1.450.0 PR Size / Focus Gate (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.450.0"
owner_path: ".github/workflows/"

goal: "Prevent overly large or unfocused pull requests by analyzing changed files and failing if limits exceeded."
provides:
- "Deterministic PR size check"
- "Configurable thresholds (max files, max lines, max packages touched)"
- "GitHub Actions check that runs on PRs"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - GitHub Actions workflows exist (0.110.0)
  - `jq` or similar available in runner for parsing.

#### Compile-time deps

N/A

### Entry points / integration points (MUST)

- Composer:
  - `pr-size:gate`
- GitHub Actions:
  - workflow `pr-size-check.yml` triggered on pull_request.

### Deliverables (MUST)

#### Creates

- [ ] `.github/workflows/pr-size-check.yml` — workflow:
  - [ ] runs on `pull_request` (synchronize, opened, reopened)
  - [ ] uses `actions/github-script` or `git diff --stat` to compute changed files and lines
  - [ ] compares against thresholds defined in the workflow `env` block (single-choice; line-of-truth for Phase 1):
    - [ ] `MAX_FILES`
    - [ ] `MAX_LINES`
    - [ ] `MAX_PACKAGES`
  - [ ] if thresholds exceeded, fails with message listing the counts and which thresholds were violated
  - [ ] optional local fallback script (`framework/tools/gates/pr_size_gate.php`) MUST use the same threshold names/defaults
- [ ] `framework/tools/gates/pr_size_gate.php` — fallback local script (optional) to run same check locally via composer.

#### Modifies

- [ ] `composer.json` — add mirror scripts (delegates to framework):
  - [ ] `pr-size:gate` → `@composer --no-interaction --working-dir=framework run-script pr-size:gate --`
- [ ] `framework/composer.json` — add gate script
  - [ ] `pr-size:gate` → `@php tools/gates/pr_size_gate.php`
- [ ] `.github/workflows/ci.yml` — ensure PR size check runs (or separate workflow)
- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register `CORETSIA_PR_SIZE_EXCEEDED` (if local script used)

### Cross-cutting

#### Observability

- [ ] Output: counts and thresholds.

#### Errors

- [ ] Deterministic exit code (non-zero) and message.

#### Security / Redaction

- [ ] No secrets; only file paths and counts.

### Verification

- [ ] Integration test: create a PR with many files, assert check fails.

### Tests

- [ ] (Manual verification in CI; local script test in `framework/tools/tests/Integration/PrSizeGateTest.php`)

### DoD

- [ ] Workflow created and integrated.

---

## 1.460.0 CLI Performance Gate (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.460.0"
owner_path: "framework/tools/gates/"

goal: "Benchmark execution time of key CLI commands on a pinned benchmark runner and fail only there if performance degrades beyond threshold."
provides:
- "Deterministic performance benchmarking of CLI commands"
- "Baseline timings stored in SSoT"
- "CI gate that compares current timings against baseline"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.130.0 — CLI base exists
  - 0.140.0 — cli-spikes commands exist (for testing)
  - 1.50.0 — tooling baseline exists

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

- [ ] `framework/tools/config/performance.php` — tooling-local performance benchmark config:
  - [ ] list of commands to benchmark (e.g., `coretsia list`, `coretsia help`, `coretsia spike:fingerprint`)
  - [ ] threshold multiplier (e.g., 1.2 = 20% slower allowed)
  - [ ] baseline file path `framework/tools/config/performance.baseline.json`
  - [ ] baseline MUST be tied to the pinned benchmark environment / runner class
- [ ] `framework/tools/gates/performance_gate.php` — deterministic gate:
  - [ ] runs each command multiple times (e.g., 3) and takes median execution time
  - [ ] compares against baseline (if exists) or creates baseline if not
  - [ ] if any command exceeds baseline * threshold, prints `CORETSIA_PERFORMANCE_DEGRADED` + details
  - [ ] uses `ConsoleOutput`
  - [ ] supports `--update-baseline` flag to update baseline after intentional improvements
- [ ] `framework/tools/config/performance.baseline.json` — initial baseline (committed)

#### Modifies

- [ ] `composer.json` — add mirror scripts (delegates to framework):
  - [ ] `performance:gate` → `@composer --no-interaction --working-dir=framework run-script performance:gate --`
- [ ] `framework/composer.json` — add gate script
  - [ ] `performance:gate` → `@php tools/gates/performance_gate.php`
- [ ] `.github/workflows/performance-benchmark.yml` — dedicated pinned-runner workflow/job for the performance gate
- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_PERFORMANCE_DEGRADED`
  - [ ] `CORETSIA_PERFORMANCE_GATE_SCAN_FAILED`

### Cross-cutting

#### Observability

- [ ] Output: command name, baseline time, current time, threshold.

#### Errors

- [ ] Deterministic codes.

#### Security / Redaction

- [ ] No secrets; only command names and timings.

### Verification

- [ ] Integration test: mock slow command, run gate, assert failure.

### Tests

- [ ] `framework/tools/tests/Integration/PerformanceGateTest.php`

### DoD

- [ ] Gate implemented, baseline created, CI integrated.

---

## 1.470.0 Atomic Transaction Gate (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.470.0"
owner_path: "framework/tools/gates/"

goal: "Ensure that all file write operations in tools follow the atomic write pattern (temp → rename) to prevent corruption."
provides:
- "Static analysis of tools code to detect unsafe file writes"
- "Deterministic failure on direct writes without backup/rename"
- "Integration into CI to block unsafe file operations"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.30.0 — spikes boundary gate infrastructure exists
  - 0.50.0 — DeterministicFile helper exists (canonical atomic writer)
  - 0.100.0 — workspace sync demonstrates atomic pattern

- Required deliverables:
  - `framework/tools/spikes/_support/DeterministicFile.php` — canonical deterministic file helper and reference pattern for safe tools-side writes.

#### Compile-time deps

N/A

### Entry points / integration points (MUST)

- Composer:
  - `composer atomic-write:gate` — runs atomic write gate
- CI:
  - run in arch job.

### Deliverables (MUST)

#### Creates

- [ ] `framework/tools/gates/atomic_write_gate.php` — deterministic gate:
  - [ ] scans `framework/tools/**/*.php` (excluding tests/fixtures)
  - [ ] token-based detection of:
    - [ ] direct `file_put_contents`, `fopen+write`, `fwrite` to non-temp paths
    - [ ] any write that does not use `DeterministicFile` methods (which are assumed atomic) or explicit temp+rename pattern
  - [ ] if unsafe write found, prints `CORETSIA_ATOMIC_WRITE_VIOLATION` + list of files and line numbers
  - [ ] uses `ConsoleOutput`
  - [ ] supports `--path` override

#### Modifies

- [ ] `composer.json` — add mirror scripts (delegates to framework):
  - [ ] `atomic-write:gate` → `@composer --no-interaction --working-dir=framework run-script atomic-write:gate --`
- [ ] `framework/composer.json` — add gate script
  - [ ] `atomic-write:gate` → `@php tools/gates/atomic_write_gate.php`
- [ ] `.github/workflows/ci.yml` — add atomic gate
- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register `CORETSIA_ATOMIC_WRITE_VIOLATION`

### Cross-cutting

#### Observability

- [ ] Output: list of offending files with repo-relative paths.

#### Errors

- [ ] Deterministic code.

#### Security / Redaction

- [ ] No file contents; only paths.

### Verification

- [ ] Integration test: create a PHP file with unsafe `file_put_contents`, run gate with `--path`, assert failure.

### Tests

- [ ] `framework/tools/tests/Integration/AtomicWriteGateTest.php`

### DoD

- [ ] Gate implemented, CI integrated, error code registered.
