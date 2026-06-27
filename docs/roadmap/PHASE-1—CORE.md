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
  - [x] rule (single-choice): every reserved tag MUST be declared as a constant in the owner package (usually `framework/packages/core/foundation/src/Tag/ReservedTags.php`)
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

### Gate — No runtime tooling artifacts (MUST)

- Script: `framework/tools/gates/no_runtime_tooling_artifacts_gate.php`
- Purpose: runtime packages MUST NOT import, require, execute, or read Phase 0/Phase 1 tooling code or tooling-generated architecture artifacts at runtime.
- This is a runtime-purity gate, not a second architecture dependency brain.
- It complements deptrac because deptrac catches namespace/class dependencies but does not reliably catch string-path reads, require/include paths, shell invocations, or accidental runtime consumption of tooling artifacts.

#### Scan scope (MUST)

The gate MUST scan runtime source/config only:

- `framework/packages/core/*/src`
- `framework/packages/core/*/config`
- `framework/packages/platform/*/src`
- `framework/packages/platform/*/config`
- `framework/packages/integrations/*/src`
- `framework/packages/integrations/*/config`
- `framework/packages/presets/*/src`
- `framework/packages/presets/*/config`
- `framework/packages/enterprise/*/src`
- `framework/packages/enterprise/*/config`

#### Exclusions (MUST)

The gate MUST exclude:

- `framework/packages/devtools/**`
- `**/tests/**`
- `**/fixtures/**`
- `**/vendor/**`
- `framework/tools/**` as scan input, because tools are not runtime packages

#### Forbidden evidence (MUST)

Runtime source/config MUST NOT contain:

- namespace imports or references:
  - `Coretsia\Tools\Spikes\`
  - `Coretsia\Devtools\`
- Composer/package references:
  - `devtools/internal-toolkit`
  - `devtools/cli-spikes`
  - `coretsia/devtools-internal-toolkit`
  - `coretsia/devtools-cli-spikes`
- runtime path reads/includes/execs involving:
  - `framework/tools/`
  - `tools/spikes/`
  - `tools/build/`
  - `tools/gates/`
  - `framework/var/arch`
- PHP include/require patterns that resolve into tooling paths
- shell command strings that execute tooling paths from runtime code

#### Allowed evidence (MUST)

The gate MAY allow:

- docs-only mentions outside scan scope
- tests/fixtures mentions outside runtime scan scope
- CI/tooling code under `framework/tools/**`
- generated architecture artifacts consumed by CI/tooling jobs only, never by runtime packages

#### Output policy (MUST)

- On violation:
  - line 1: `CORETSIA_RUNTIME_TOOLING_ARTIFACTS_VIOLATION`
  - line 2+: deterministic diagnostics
- On scanner failure:
  - line 1: `CORETSIA_RUNTIME_TOOLING_ARTIFACTS_GATE_FAILED`
- Diagnostics MUST be:
  - repo-relative
  - sorted by byte-order `strcmp`
  - stable across OSes
  - free of source snippets, raw file contents, absolute paths, secrets, environment values

### Deliverables (MUST)

#### Creates

- [x] `framework/tools/gates/cross_cutting_contract_gate.php`
  - [x] MUST enforce Kernel/Foundation reset discipline once the required owner-package evidence exists:
    - [x] `kernel.stateful` ⇒ service implements `Coretsia\Contracts\Runtime\ResetInterface`
    - [x] `kernel.stateful` ⇒ service is also discoverable through the effective Foundation reset discovery tag
  - [x] MUST resolve the effective Foundation reset discovery tag from available Foundation evidence:
    - [x] default reserved reset discovery tag is `kernel.reset`
    - [x] `ReservedTags::KERNEL_STATEFUL` MUST remain `kernel.stateful`
    - [x] `ReservedTags::KERNEL_RESET` MUST remain `kernel.reset`
    - [x] if `foundation.reset.tag` config evidence exists, that configured value is the effective reset discovery tag
    - [x] configured reset tag evidence MUST be validated deterministically
    - [x] invalid configured reset tag evidence MUST fail with stable reason token `foundation-reset-tag-invalid`
    - [x] the gate MUST NOT hardcode only `kernel.reset` when custom `foundation.reset.tag` evidence is present
    - [x] the gate MUST recognize local variables assigned from `FoundationServiceFactory::effectiveResetTag(...)` as effective reset tag evidence in service tagging code
  - [x] MUST inspect supported deterministic service tag evidence:
    - [x] container builder `->tag(...)` calls
    - [x] array definition `tags` metadata
    - [x] direct string tag literals
    - [x] `ReservedTags::KERNEL_STATEFUL`
    - [x] `ReservedTags::KERNEL_RESET`
    - [x] variables proven to originate from `FoundationServiceFactory::effectiveResetTag(...)`
  - [x] MUST preserve deterministic no-op behavior:
    - [x] if `Coretsia\Contracts\Runtime\ResetInterface` evidence is not present yet, the gate exits successfully
    - [x] if Foundation reserved tag evidence is not present yet, the gate exits successfully
    - [x] if Foundation owner-package evidence is not present yet, the gate exits successfully
    - [x] if Kernel owner-package evidence is not present yet, the gate exits successfully for kernel-specific checks
    - [x] no missing-future-package failure is allowed
  - [x] MUST enforce context boundary rules once owning evidence exists:
    - [x] direct `Coretsia\Foundation\Context\ContextStore` usage is forbidden outside explicit owner boundaries
    - [x] `Coretsia\Foundation\Context\ContextStore` remains allowed for Foundation service construction where explicitly required
    - [x] `Coretsia\Foundation\Context\ContextStore` remains allowed for Kernel-owned base UnitOfWork context writes
    - [x] legacy `Coretsia\Foundation\Context\ContextKeys` references are forbidden
    - [x] public `Coretsia\Contracts\Context\ContextKeys` references are allowed as stable public context key vocabulary
    - [x] importing `Coretsia\Contracts\Context\ContextKeys` MUST NOT imply mutable context ownership
  - [x] MUST emit stable reason tokens:
    - [x] `kernel-tags-drift`
    - [x] `foundation-reset-tag-invalid`
    - [x] `kernel-stateful-service-missing-reset-tag`
    - [x] `kernel-stateful-service-class-unresolved`
    - [x] `kernel-stateful-service-not-resettable`
    - [x] `forbidden-context-store-usage`
    - [x] `forbidden-context-keys-usage`
  - [x] diagnostics MUST be deterministic:
    - [x] repo/framework-relative paths only
    - [x] stable reason tokens
    - [x] duplicate diagnostics deduplicated deterministically
    - [x] diagnostics sorted by byte-order `strcmp`
    - [x] no raw config payloads
    - [x] no secrets
    - [x] no absolute paths
  - [x] MUST NOT create `framework/tools/gates/kernel_reset_discipline_gate.php`.

- [x] `framework/tools/gates/no_runtime_tooling_artifacts_gate.php`
  - [x] enforces the “No runtime tooling artifacts” gate policy above
  - [x] deterministic no-op when no runtime package scan roots exist
  - [x] uses `ConsoleOutput`
  - [x] uses deterministic reason tokens:
    - [x] `runtime-imports-tools-spikes`
    - [x] `runtime-imports-devtools`
    - [x] `runtime-references-devtools-package`
    - [x] `runtime-reads-framework-tools`
    - [x] `runtime-executes-tooling-path`
    - [x] `runtime-reads-architecture-artifact`
  - [x] MUST NOT duplicate deptrac layer rules
  - [x] MUST NOT parse `docs/roadmap/phase0/00_2-dependency-table.md`

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

- [x] `framework/tools/gates/reserved_tags_registry_gate.php`
  - [x] validates `docs/ssot/tags.md` as the canonical reserved tag registry source
  - [x] validates that every framework-reserved DI tag from `docs/ssot/tags.md` has a matching public constant in `Coretsia\Foundation\Tag\ReservedTags`
  - [x] validates that each `ReservedTags` public constant value equals the canonical reserved tag string exactly
  - [x] validates that each `ReservedTags` public constant name is derived deterministically from the canonical tag string
  - [x] fails deterministically when a reserved tag from `docs/ssot/tags.md` is missing from `ReservedTags`
  - [x] fails deterministically when a `ReservedTags` public constant has a mismatched value
  - [x] fails deterministically when `ReservedTags` exposes an extra public tag-like constant outside `docs/ssot/tags.md`
  - [x] fails deterministically when `ReservedTags` exposes an alias constant for an already-declared reserved tag string
  - [x] fails deterministically when package source defines an additional code-level registry for framework-reserved DI tag identifiers
  - [x] fails deterministically when package source defines a local constant whose value equals a framework-reserved DI tag string
  - [x] fails deterministically when package source defines a local constant that aliases `ReservedTags::*`
  - [x] treats `Coretsia\Foundation\Tag\ReservedTags` as the only code-level identifier registry for framework-reserved DI tags
  - [x] treats `Coretsia\Foundation\Tag\ReservedTags` as identifier-string ownership only, not runtime discovery ownership
  - [x] preserves runtime tagged-service discovery, ordering, and dedupe ownership in `Coretsia\Foundation\Tag\TagRegistry`
  - [x] outputs no text on success
  - [x] output format follows the canonical Phase 0 gate policy

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
  - [x] ensure the gate runs in the gates job before runtime package tests
  - [x] `gates` (Linux): runs gate scripts deterministically
  - [x] `arch` (Linux): deptrac generate (rerun-no-diff) + deptrac analyze + artifact upload
  - [x] `test` (Linux): `composer -d framework install` → `composer -d framework test`
  - [x] `unit+contract` (Linux): runs unit/contract suites when they exist
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
  - [x] adds `CORETSIA_RESERVED_TAGS_REGISTRY_DRIFT`
  - [x] adds `CORETSIA_RESERVED_TAGS_REGISTRY_GATE_FAILED`
  - [x] adds `CORETSIA_OBSERVABILITY_NAMING_DRIFT`
  - [x] adds `CORETSIA_OBSERVABILITY_NAMING_GATE_FAILED`
  - [x] adds `CORETSIA_ARTIFACT_HEADER_SCHEMA_DRIFT`
  - [x] adds `CORETSIA_ARTIFACT_HEADER_SCHEMA_GATE_FAILED`
  - [x] adds `CORETSIA_CROSS_CUTTING_CONTRACT_DRIFT`
  - [x] adds `CORETSIA_CROSS_CUTTING_CONTRACT_GATE_FAILED`
  - [x] adds `CORETSIA_KERNEL_PUBLIC_API_DRIFT`
  - [x] adds `CORETSIA_KERNEL_PUBLIC_API_GATE_FAILED`
  - [x] adds `CORETSIA_RUNTIME_TOOLING_ARTIFACTS_VIOLATION`
  - [x] adds `CORETSIA_RUNTIME_TOOLING_ARTIFACTS_GATE_FAILED`

- [x] `composer.json` — add repo-root mirror scripts (delegates to framework):
  - [x] `cross-cutting-contract:gate` → `@composer --no-interaction --working-dir=framework run-script cross-cutting-contract:gate --`
  - [x] `kernel-public-api:gate` → `@composer --no-interaction --working-dir=framework run-script kernel-public-api:gate --`
  - [x] `no-skeleton-http-default:gate` → `@composer --no-interaction --working-dir=framework run-script no-skeleton-http-default:gate --`
  - [x] `no-skeleton-mode-presets-default:gate` → `@composer --no-interaction --working-dir=framework run-script no-skeleton-mode-presets-default:gate --`
  - [x] `no-skeleton-modules-default:gate` → `@composer --no-interaction --working-dir=framework run-script no-skeleton-modules-default:gate --`
  - [x] `no-skeleton-bundles-default:gate` → `@composer --no-interaction --working-dir=framework run-script no-skeleton-bundles-default:gate --`
  - [x] `contracts-only-ports:gate` → `@composer --no-interaction --working-dir=framework run-script contracts-only-ports:gate --`
  - [x] `reserved-tags:gate` → `@composer --no-interaction --working-dir=framework run-script reserved-tags:gate --`
  - [x] `observability-naming:gate` → `@composer --no-interaction --working-dir=framework run-script observability-naming:gate --`
  - [x] `artifact-header-schema:gate` → `@composer --no-interaction --working-dir=framework run-script artifact-header-schema:gate --`
  - [x] `no-runtime-tooling-artifacts:gate` → `@composer --no-interaction --working-dir=framework run-script no-runtime-tooling-artifacts:gate --`

- [x] `framework/composer.json` — add workspace gate scripts:
  - [x] `cross-cutting-contract:gate` → `@php tools/gates/cross_cutting_contract_gate.php`
  - [x] `kernel-public-api:gate` → `@php tools/gates/kernel_public_api_gate.php`
  - [x] `no-skeleton-http-default:gate` → `@php tools/gates/no_skeleton_http_default_gate.php`
  - [x] `no-skeleton-mode-presets-default:gate` → `@php tools/gates/no_skeleton_mode_presets_default_gate.php`
  - [x] `no-skeleton-modules-default:gate` → `@php tools/gates/no_skeleton_modules_default_gate.php`
  - [x] `no-skeleton-bundles-default:gate` → `@php tools/gates/no_skeleton_bundles_default_gate.php`
  - [x] `contracts-only-ports:gate` → `@php tools/gates/contracts_only_ports_gate.php`
  - [x] `reserved-tags:gate` → `@php tools/gates/reserved_tags_registry_gate.php`
  - [x] `observability-naming:gate` → `@php tools/gates/observability_naming_gate.php`
  - [x] `artifact-header-schema:gate` → `@php tools/gates/artifact_header_schema_gate.php`
  - [x] `no-runtime-tooling-artifacts:gate` → `@php tools/gates/no_runtime_tooling_artifacts_gate.php`
  - [x] include `@no-runtime-tooling-artifacts:gate` in the canonical `gates` aggregate

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
  - [x] `framework/tools/tests/Integration/CrossCuttingContractGateTest.php`
    - [x] `kernel.stateful` service without `ResetInterface` fails deterministically
    - [x] `kernel.stateful` service without effective reset discovery tag fails deterministically
    - [x] custom `foundation.reset.tag` is respected when Foundation config evidence exists
    - [x] default `kernel.reset` is used when no custom Foundation reset tag is configured
    - [x] gate is deterministic no-op when required owner-package evidence is absent
    - [x] diagnostics do not contain absolute paths, raw config payloads, or secrets
  - [x] `framework/tools/tests/Integration/NoRuntimeToolingArtifactsGateTest.php`
    - [x] runtime source importing `Coretsia\Tools\Spikes\*` fails
    - [x] runtime source importing `Coretsia\Devtools\*` fails
    - [x] runtime config/source referencing `framework/tools/` fails
    - [x] runtime source requiring or including `tools/build/*` fails
    - [x] runtime source shelling out to `tools/gates/*` fails
    - [x] runtime source reading `framework/var/arch` fails
    - [x] docs/tests/fixtures mentions are ignored
    - [x] diagnostics are sorted and repo-relative
    - [x] diagnostics do not contain source snippets, absolute paths, raw file contents, env values, or secrets
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
- [x] No `kernel_reset_discipline_gate.php` exists.
- [x] Reset discipline remains enforced by `cross_cutting_contract_gate.php`.
- [x] Effective reset tag resolution is Foundation-owned and is not duplicated as a competing Kernel policy.
- [x] Runtime packages cannot consume tooling code or tooling artifacts.
- [x] `no_runtime_tooling_artifacts_gate.php` is part of the canonical gates rail.
- [x] The gate complements deptrac but does not duplicate deptrac rules or SSoT dependency parsing.

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

- [x] `framework/tools/gates/dto_gate.php` — deterministic aggregate runner for DTO rail:
  - [x] runs `dto_marker_consistency_gate.php`
  - [x] runs `dto_no_logic_gate.php`
  - [x] runs `dto_shape_gate.php`
  - [x] preserves deterministic execution order
  - [x] stops with non-zero exit code if any sub-gate fails
  - [x] MUST NOT rewrite/merge sub-gate diagnostics into an alternative format
  - [x] MUST preserve Phase 0 gate output policy
  - [x] aggregate runner MUST stop on first failing sub-gate
  - [x] aggregate runner MUST pass through the exact output of the first failing sub-gate unchanged
  - [x] aggregate runner MUST NOT concatenate outputs of multiple failing sub-gates into a new composite report
  - [x] if all sub-gates pass, exit 0 and print nothing
  - [x] aggregate runner is supplemental and MUST NOT replace per-gate command entrypoints
  - [x] each specialized DTO gate created by later epics MUST also be registered as its own `<command>:gate` composer script at repo root and in `framework/composer.json`

- [x] `framework/packages/core/dto-attribute/composer.json` — DTO marker package:
  - [x] package name: `coretsia/core-dto-attribute`
  - [x] package kind: library-only marker package
  - [x] no runtime deps
  - [x] PSR-4:
    - [x] `Coretsia\Dto\Attribute\` → `src/Attribute/`

- [x] `framework/packages/core/dto-attribute/src/Attribute/Dto.php` — canonical DTO marker:
  - [x] `#[Attribute(Attribute::TARGET_CLASS)]`
  - [x] empty marker attribute
  - [x] no parameters in Phase 1
  - [x] no runtime behavior

- [x] `framework/packages/core/dto-attribute/README.md` — usage and policy note:
  - [x] explains explicit opt-in
  - [x] explains that marking a class as DTO subjects it to DTO gates
  - [x] explains that DTO is a narrow transport shape, not a general-purpose VO model

- [x] `framework/packages/core/dto-attribute/tests/Contract/AttributeExistsTest.php`

- [x] `docs/ssot/dto-policy.md` — canonical DTO SSoT:
  - [x] already has canonical vocabulary
  - [x] defines what a DTO is in Coretsia
  - [x] states explicit opt-in policy
  - [x] states attribute-first marker strategy
  - [x] states that Phase 1 uses attribute-only detection
  - [x] defines canonical DTO rules:
    - [x] final class
    - [x] public typed instance properties
    - [x] no static properties
    - [x] no inheritance
    - [x] no traits
    - [x] no interfaces
    - [x] no methods except optional constructor
  - [x] states what DTO is not:
    - [x] not a domain entity
    - [x] not a service
    - [x] not a stateful runtime object
    - [x] not a rich VO/shape class
  - [x] provides compliant and non-compliant examples
  - [x] explicitly states that unmarked classes are outside DTO gate scope
  - [x] `## Canonical vocabulary`
    - [x] DTO — explicit transport class, opt-in via marker, enforced by DTO gates
    - [x] VO — value object with behavior/invariants allowed; outside DTO gate scope unless explicitly marked
    - [x] Descriptor — canonical structured model used for cross-package/runtime boundaries; not automatically DTO
    - [x] Result/Shape/Context model — structured contract/runtime payload; not automatically DTO
  - [x] `## Scope rule`
    - [x] unmarked classes are outside DTO gate scope
    - [x] contracts VOs, descriptors, result models, artifact payload models, config trace models, and runtime services MUST NOT be treated as DTOs unless explicitly marked

- [x] `framework/tools/tests/Integration/DtoGateAggregateRunnerTest.php` — proves aggregate runner order and failure propagation

#### Modifies

- [x] `composer.json` — add mirror scripts (delegates to framework):
  - [x] `dto:gate` → `@composer --no-interaction --working-dir=framework run-script dto:gate --`
- [x] `framework/composer.json` — add gate script
  - [x] `dto:gate` → `@php tools/gates/dto_gate.php`

- [x] `.github/workflows/ci.yml` — add DTO rail execution:
  - [x] runs after install and before tests
  - [x] may run inside existing `gates` job

- [x] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [x] `CORETSIA_DTO_GATE_FAILED`
  - [x] `CORETSIA_DTO_MARKER_VIOLATION`
  - [x] `CORETSIA_DTO_NO_LOGIC_VIOLATION`
  - [x] `CORETSIA_DTO_SHAPE_VIOLATION`
  - [x] `CORETSIA_DTO_GATE_SCAN_FAILED`

- [x] `framework/composer.json` — require marker package for workspace autoload visibility:
  - [x] add `coretsia/core-dto-attribute`

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/dto-policy.md`

#### Configuration (keys + defaults)

N/A

- [x] This epic intentionally has **no config root** and **no runtime/workspace config toggles**.
- [x] DTO policy is hardcoded in gates + documented in SSoT.
- [x] Alternative DTO models (e.g. getters/withers/private-readonly DTOs/interface marker fallback) are out of scope for this rail and require a future epic/ADR.

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

- [x] Deterministic top-level error codes reserved for DTO rail:
  - [x] `CORETSIA_DTO_GATE_FAILED` — aggregate rail orchestration failed before any sub-gate could produce canonical diagnostics
    - [x] MUST NOT be used for normal policy violations detected by specialized DTO gates
  - [x] `CORETSIA_DTO_MARKER_VIOLATION` — marker consistency gate found violations
  - [x] `CORETSIA_DTO_NO_LOGIC_VIOLATION` — no-logic gate found violations
  - [x] `CORETSIA_DTO_SHAPE_VIOLATION` — shape gate found violations
  - [x] `CORETSIA_DTO_GATE_SCAN_FAILED` — a DTO gate failed to initialize or scan

#### Security / Redaction

- [x] DTO rail MUST NOT leak class contents, property values, constructor body text, or method bodies.
- [x] Diagnostics contain only normalized relative paths and fixed reason tokens.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] `framework/tools/tests/Integration/DtoGateAggregateRunnerTest.php`
  - [x] proves specialized gates are invoked in deterministic order
  - [x] proves non-zero exit propagates
  - [x] proves aggregate runner does not invent a second diagnostics format

- [x] `framework/packages/core/dto-attribute/tests/Contract/AttributeExistsTest.php`

### Tests (MUST)

- Contract:
  - [x] `framework/packages/core/dto-attribute/tests/Contract/AttributeExistsTest.php`
- Integration:
  - [x] `framework/tools/tests/Integration/DtoGateAggregateRunnerTest.php`
- Gates/Arch:
  - [x] `.github/workflows/ci.yml` runs `composer -d framework dto:gate`

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] Preconditions satisfied (no forward references)
- [x] DTO rail is deterministic and integrated into CI
- [x] Aggregate runner exists and invokes specialized gates in deterministic order
- [x] Error codes are registered in `ErrorCodes.php`
- [x] `docs/ssot/dto-policy.md` exists and is linked
- [x] Marker package `coretsia/core-dto-attribute` exists with minimal deps
- [x] DTO detection is attribute-only and explicit-opt-in
- [x] Unmarked classes are outside DTO rail scope
- [x] `README.md` of marker package explains usage and scope
- [x] `dto_gate.php` preserves single-gate output contract by forwarding first failing sub-gate output verbatim

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

- [x] `framework/tools/gates/dto_marker_consistency_gate.php` — deterministic marker consistency gate:
  - [x] scans `framework/packages/**/src/**/*.php`
  - [x] excludes `**/tests/**`, `**/fixtures/**`, `**/vendor/**`
  - [x] token-based analysis only
  - [x] detects DTO marker usage only via canonical attribute `#[Coretsia\Dto\Attribute\Dto]`
  - [x] allows imported alias only if alias resolves to canonical FQCN
  - [x] forbids custom attribute classes intended as DTO markers inside monorepo
  - [x] forbids legacy interface markers such as `DtoInterface`
  - [x] forbids simultaneous support for multiple DTO marker strategies
  - [x] output format follows Phase 0 gate policy:
    - [x] line 1: `CORETSIA_DTO_MARKER_VIOLATION` if any violation exists
    - [x] line 2+: `<scan-root-relative-normalized-path>: <reason-code>`
    - [x] diagnostics sorted by normalized path using `strcmp`
    - [x] if multiple violations exist in one file, each violation gets its own line
    - [x] if no violations, exit 0 and print nothing
    - [x] output only through `ConsoleOutput`
  - [x] runtime roots vs scan root:
    - [x] `$toolsRootRuntime = realpath(__DIR__ . '/..')`
    - [x] default scan root is `$toolsRootRuntime . '/..'`
    - [x] `--path=<dir>` overrides only scan root
    - [x] bootstrap is always loaded from `$toolsRootRuntime . '/spikes/_support/bootstrap.php'`
  - [x] error handling:
    - [x] missing/unreadable bootstrap → `CORETSIA_DTO_GATE_SCAN_FAILED`
    - [x] internal scanning/parsing failure → `CORETSIA_DTO_GATE_SCAN_FAILED`
    - [x] any uncaught exception → same code, exit 1

- [x] `framework/tools/tests/Integration/DtoMarkerConsistencyGateTest.php`

#### Modifies

- [x] `composer.json` — add repo-root mirror script:
  - [x] `dto-marker-consistency:gate` → `@composer --no-interaction --working-dir=framework run-script dto-marker-consistency:gate --`
- [x] `framework/composer.json` — add workspace gate script:
  - [x] `dto-marker-consistency:gate` → `@php tools/gates/dto_marker_consistency_gate.php`

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

- [x] Deterministic top-level error codes:
  - [x] `CORETSIA_DTO_MARKER_VIOLATION`
  - [x] `CORETSIA_DTO_GATE_SCAN_FAILED`
- [x] Fixed reason codes:
  - [x] `non-canonical-dto-marker`
  - [x] `legacy-dto-interface-marker`
  - [x] `custom-dto-marker-class`
  - [x] `multiple-dto-marker-strategies`

#### Security / Redaction

- [x] Gate MUST NOT leak attribute bodies, class contents, or method/property text.
- [x] Diagnostics contain only normalized relative paths and fixed reason tokens.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] `framework/tools/tests/Integration/DtoMarkerConsistencyGateTest.php`:
  - [x] canonical marker usage passes
  - [x] alias import resolving to canonical marker passes
  - [x] custom DTO marker attribute fails with `custom-dto-marker-class`
  - [x] legacy interface marker fails with `legacy-dto-interface-marker`
  - [x] mixed marker strategy in same synthetic tree fails with `multiple-dto-marker-strategies`
  - [x] `--path` override works on synthetic tree
  - [x] missing bootstrap triggers `CORETSIA_DTO_GATE_SCAN_FAILED`

### Tests (MUST)

- Integration:
  - [x] `framework/tools/tests/Integration/DtoMarkerConsistencyGateTest.php`

### DoD (MUST)

- [x] Deliverables complete (creates), paths exact
- [x] Preconditions satisfied (no forward references)
- [x] Exactly one DTO marker strategy exists in Phase 1
- [x] Gate is deterministic and integrated via DTO rail
- [x] Any future alternate marker requires ADR + SSoT update + gate update

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

- [x] `framework/tools/gates/dto_no_logic_gate.php` — deterministic no-logic gate:
  - [x] scans `framework/packages/**/src/**/*.php`
  - [x] excludes `**/tests/**`, `**/fixtures/**`, `**/vendor/**`
  - [x] analyzes only explicitly marked DTO classes
  - [x] token-based analysis only
  - [x] allowed constructor patterns:
    - [x] property promotion only
    - [x] trivial assignments `$this->prop = $param;`
  - [x] fixed reason codes:
    - [x] `disallowed-method` — DTO method other than `__construct` exists
    - [x] `constructor-calls-function`
    - [x] `constructor-calls-method`
    - [x] `constructor-static-call`
    - [x] `constructor-control-flow`
    - [x] `constructor-loop`
    - [x] `constructor-try-catch`
    - [x] `constructor-throw`
    - [x] `constructor-new-object`
    - [x] `constructor-nontrivial-body`
  - [x] output format follows Phase 0 gate policy:
    - [x] line 1: `CORETSIA_DTO_NO_LOGIC_VIOLATION` if any violation exists
    - [x] line 2+: `<scan-root-relative-normalized-path>: <reason-code>`
    - [x] diagnostics sorted by normalized path using `strcmp`
    - [x] if multiple violations exist in one file, each violation gets its own line
    - [x] if no violations, exit 0 and print nothing
    - [x] output only through `ConsoleOutput`
  - [x] runtime roots vs scan root:
    - [x] `$toolsRootRuntime = realpath(__DIR__ . '/..')`
    - [x] default scan root is `$toolsRootRuntime . '/..'`
    - [x] `--path=<dir>` overrides only scan root
    - [x] bootstrap is always loaded from `$toolsRootRuntime . '/spikes/_support/bootstrap.php'`
  - [x] error handling:
    - [x] missing/unreadable bootstrap → `CORETSIA_DTO_GATE_SCAN_FAILED`
    - [x] internal scanning/parsing failure → `CORETSIA_DTO_GATE_SCAN_FAILED`
    - [x] any uncaught exception → same code, exit 1

- [x] `framework/tools/tests/Integration/DtoNoLogicGateTest.php`

#### Modifies

- [x] `composer.json` — add repo-root mirror script:
  - [x] `dto-no-logic:gate` → `@composer --no-interaction --working-dir=framework run-script dto-no-logic:gate --`
- [x] `framework/composer.json` — add workspace gate script:
  - [x] `dto-no-logic:gate` → `@php tools/gates/dto_no_logic_gate.php`

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

- [x] Deterministic top-level error codes:
  - [x] `CORETSIA_DTO_NO_LOGIC_VIOLATION`
  - [x] `CORETSIA_DTO_GATE_SCAN_FAILED`
- [x] Fixed reason codes:
  - [x] `disallowed-method`
  - [x] `constructor-calls-function`
  - [x] `constructor-calls-method`
  - [x] `constructor-static-call`
  - [x] `constructor-control-flow`
  - [x] `constructor-loop`
  - [x] `constructor-try-catch`
  - [x] `constructor-throw`
  - [x] `constructor-new-object`
  - [x] `constructor-nontrivial-body`

#### Security / Redaction

- [x] Gate MUST NOT leak class contents, property values, constructor body text, or method bodies.
- [x] Diagnostics contain only normalized relative paths and fixed reason tokens.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] `framework/tools/tests/Integration/DtoNoLogicGateTest.php`:
  - [x] DTO with no constructor passes
  - [x] DTO with promoted public typed properties passes
  - [x] DTO with trivial assignment constructor passes
  - [x] DTO with extra method fails with `disallowed-method`
  - [x] constructor with `trim(...)` fails with `constructor-calls-function`
  - [x] constructor with `$this->helper()` fails with `constructor-calls-method`
  - [x] constructor with `self::normalize()` fails with `constructor-static-call`
  - [x] constructor with `if`/`match` fails with `constructor-control-flow`
  - [x] constructor with loop fails with `constructor-loop`
  - [x] constructor with `try/catch` fails with `constructor-try-catch`
  - [x] constructor with `throw` fails with `constructor-throw`
  - [x] constructor with `new DateTimeImmutable(...)` fails with `constructor-new-object`
  - [x] `--path` override works on synthetic tree
  - [x] missing bootstrap triggers `CORETSIA_DTO_GATE_SCAN_FAILED`

### Tests (MUST)

- Integration:
  - [x] `framework/tools/tests/Integration/DtoNoLogicGateTest.php`

### DoD (MUST)

- [x] Deliverables complete (creates), paths exact
- [x] Preconditions satisfied (no forward references)
- [x] Gate is deterministic and integrated via DTO rail
- [x] DTO constructor semantics are narrow and enforceable
- [x] DTO behavior cannot drift into VO/service territory without failing the gate

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

- [x] `framework/tools/gates/dto_shape_gate.php` — deterministic shape gate:
  - [x] scans `framework/packages/**/src/**/*.php`
  - [x] excludes `**/tests/**`, `**/fixtures/**`, `**/vendor/**`
  - [x] analyzes only explicitly marked DTO classes
  - [x] token-based analysis only
  - [x] validates:
    - [x] DTO is a class, not interface/trait/enum
    - [x] DTO is `final`
    - [x] DTO is not `abstract`
    - [x] DTO does not `extends`
    - [x] DTO does not `implements`
    - [x] DTO does not `use` traits
    - [x] DTO does not declare static properties
    - [x] every declared property is typed
    - [x] every declared property is public
    - [x] promoted properties are public and typed
  - [x] fixed reason codes:
    - [x] `not-final`
    - [x] `abstract-class`
    - [x] `extends-class`
    - [x] `implements-interface`
    - [x] `uses-trait`
    - [x] `static-property`
    - [x] `untyped-property`
    - [x] `non-public-property`
  - [x] output format follows Phase 0 gate policy:
    - [x] line 1: `CORETSIA_DTO_SHAPE_VIOLATION` if any violation exists
    - [x] line 2+: `<scan-root-relative-normalized-path>: <reason-code>`
    - [x] diagnostics sorted by normalized path using `strcmp`
    - [x] if multiple violations exist in one file, each violation gets its own line
    - [x] if no violations, exit 0 and print nothing
    - [x] output only through `ConsoleOutput`
  - [x] runtime roots vs scan root:
    - [x] `$toolsRootRuntime = realpath(__DIR__ . '/..')`
    - [x] default scan root is `$toolsRootRuntime . '/..'`
    - [x] `--path=<dir>` overrides only scan root
    - [x] bootstrap is always loaded from `$toolsRootRuntime . '/spikes/_support/bootstrap.php'`
  - [x] error handling:
    - [x] missing/unreadable bootstrap → `CORETSIA_DTO_GATE_SCAN_FAILED`
    - [x] internal scanning/parsing failure → `CORETSIA_DTO_GATE_SCAN_FAILED`
    - [x] any uncaught exception → same code, exit 1

- [x] `framework/tools/tests/Integration/DtoShapeGateTest.php`

#### Modifies

- [x] `composer.json` — add repo-root mirror script:
  - [x] `dto-shape:gate` → `@composer --no-interaction --working-dir=framework run-script dto-shape:gate --`
- [x] `framework/composer.json` — add workspace gate script:
  - [x] `dto-shape:gate` → `@php tools/gates/dto_shape_gate.php`

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

- [x] Deterministic top-level error codes:
  - [x] `CORETSIA_DTO_SHAPE_VIOLATION`
  - [x] `CORETSIA_DTO_GATE_SCAN_FAILED`
- [x] Fixed reason codes:
  - [x] `not-final`
  - [x] `abstract-class`
  - [x] `extends-class`
  - [x] `implements-interface`
  - [x] `uses-trait`
  - [x] `static-property`
  - [x] `untyped-property`
  - [x] `non-public-property`

#### Security / Redaction

- [x] Gate MUST NOT leak class contents, property values, constructor body text, or method bodies.
- [x] Diagnostics contain only normalized relative paths and fixed reason tokens.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] `framework/tools/tests/Integration/DtoShapeGateTest.php`:
  - [x] compliant DTO with public typed properties passes
  - [x] compliant DTO with public promoted typed properties passes
  - [x] abstract DTO fails with `abstract-class`
  - [x] non-final DTO fails with `not-final`
  - [x] DTO extending another class fails with `extends-class`
  - [x] DTO implementing interface fails with `implements-interface`
  - [x] DTO using trait fails with `uses-trait`
  - [x] DTO with static property fails with `static-property`
  - [x] DTO with untyped property fails with `untyped-property`
  - [x] DTO with non-public property fails with `non-public-property`
  - [x] unmarked class is ignored
  - [x] `--path` override works on synthetic tree
  - [x] missing bootstrap triggers `CORETSIA_DTO_GATE_SCAN_FAILED`

### Tests (MUST)

- Integration:
  - [x] `framework/tools/tests/Integration/DtoShapeGateTest.php`

### DoD (MUST)

- [x] Deliverables complete (creates), paths exact
- [x] Preconditions satisfied (no forward references)
- [x] Gate is deterministic and integrated via DTO rail
- [x] Shape policy is narrow, explicit, and enforceable
- [x] DTOs cannot drift into inheritance/trait/interface/static-state models without failing the gate

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

- [x] `framework/tools/build/sync_package_scaffold.php` — package scaffold sync tool:
  - [x] scans packages under `framework/packages/*/*`
  - [x] supports apply mode and `--check`
  - [x] exact-canonical sync is allowed only for:
    - [x] `LICENSE`
    - [x] `NOTICE`
  - [x] create-if-missing only (MUST NOT overwrite existing user-owned content):
    - [x] `README.md`
    - [x] `tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
    - [x] runtime-only scaffold files/directories
  - [x] MUST NOT rewrite editable package code/docs/config once present
  - [x] MUST be rerun-no-diff
- [x] `framework/tools/gates/package_compliance_gate.php` — read-only package compliance gate:
  - [x] Allowlist application (single-choice):
    - [x] `package_compliance_allowlist.php` is the ONLY grandfathering mechanism
    - [x] allowlisted package_ids MAY be exempt only from explicitly gated strict rules
    - [x] allowlist loading and matching MUST be deterministic
  - [x] scans publishable packages under `framework/packages/*/*`
  - [x] deterministic iteration/order only (`strcmp`, locale-independent)
  - [x] MUST NOT create, modify, or delete files
  - [x] MUST fail on missing required package scaffold artifacts
  - [x] MUST fail on missing or drifted canonical legal files
  - [x] MUST validate package shape by package kind (`library|runtime`) from `composer.json > extra.coretsia.kind`
  - [x] Required artifacts for every package:
    - [x] `composer.json`
    - [x] `README.md`
    - [x] `LICENSE`
    - [x] `NOTICE`
    - [x] `src/`
    - [x] `tests/Contract/`
    - [x] `tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [x] Canonical legal files (single-choice; exact match required):
    - [x] package `LICENSE` MUST be byte-identical to repo root `LICENSE`
    - [x] package `NOTICE` MUST be byte-identical to repo root `NOTICE`
  - [x] `composer.json` checks for every package:
    - [x] valid JSON object
    - [x] `"name"` MUST equal `coretsia/<layer>-<slug>`
    - [x] `"type"` MUST equal `library`
    - [x] `"license"` MUST equal `Apache-2.0`
    - [x] `autoload.psr-4` MUST contain the canonical package root namespace mapped to `src/`
    - [x] `extra.coretsia.kind` MUST exist and be exactly `library` or `runtime`
  - [x] Runtime package checks (`extra.coretsia.kind = runtime`):
    - [x] `src/Module/` exists
    - [x] `src/Provider/` exists
    - [x] `src/Module/<StudlySlug>Module.php` exists
    - [x] `src/Provider/<StudlySlug>ServiceProvider.php` exists
    - [x] `config/` exists
    - [x] `config/<slug>.php` exists
    - [x] `config/rules.php` exists
    - [x] `extra.coretsia.moduleId` MUST equal `<layer>.<slug>`
    - [x] `extra.coretsia.moduleClass` MUST equal canonical runtime module FQCN
    - [x] `extra.coretsia.providers` MUST include canonical runtime provider FQCN
    - [x] `extra.coretsia.defaultsConfigPath` MUST equal `config/<slug>.php`
  - [x] Package identity / slug checks:
    - [x] package path MUST match canonical `framework/packages/<layer>/<slug>/`
    - [x] forbidden slugs: `app|modules|shared`
    - [x] reserved slugs: `kernel`, `observability`
      - [x] reserved means these slugs are blocked for arbitrary new packages
      - [x] reserved slugs MAY be used only by canonical owner packages/paths defined by roadmap/SSoT
  - [x] README checks:
    - [x] `README.md` MUST include at minimum:
      - [x] `## Observability`
      - [x] `## Errors`
      - [x] `## Security / Redaction`
  - [x] Runtime config shape checks (`extra.coretsia.kind = runtime`):
    - [x] `config/<slug>.php` MUST exist at the canonical path
    - [x] `config/<slug>.php` MUST return a plain array subtree (no wrapper root)
    - [x] `config/rules.php` MUST exist
    - [x] `config/rules.php` MUST return a plain array
  - [x] Runtime metadata enforcement is part of package compliance (single-choice):
    - [x] canonical runtime metadata rules MUST be enforced directly by `package_compliance_gate.php`
    - [x] `module_descriptor_metadata_gate.php` is not created by this epic
    - [x] runtime metadata MUST NOT be defined or validated in multiple independent gates in this epic
  - [x] Library package checks (`extra.coretsia.kind = library`):
    - [x] runtime-only files/directories MUST NOT be required
    - [x] runtime-only metadata MUST NOT be required
  - [x] Gate output / diagnostics:
    - [x] read-only only; no writes
    - [x] deterministic diagnostics only
    - [x] relative paths only; no absolute paths
    - [x] non-zero exit on compliance violations
- [x] `framework/tools/gates/package_compliance_allowlist.php` — explicit grandfathering list (single-choice):
  - [x] lists canonical package_ids `<layer>/<slug>` temporarily exempt from strict rules
  - [x] MUST be deterministic and sorted by `strcmp`
  - [x] MUST contain data only
  - [x] MUST NOT contain validation logic, derivation rules, or package-shape policy
  - [x] policy: allowlist MUST ONLY shrink over time; additions require an explicit epic/ADR justification

- [x] `framework/tools/tests/Integration/PackageComplianceGateAcceptsGoodFixtureTest.php`
- [x] `framework/tools/tests/Integration/PackageComplianceGateRejectsBadFixtureTest.php`
- [x] `framework/tools/tests/Integration/SyncPackageScaffoldCreatesMissingFilesTest.php`
- [x] `framework/tools/tests/Integration/SyncPackageScaffoldCheckRejectsDriftTest.php`

#### Modifies

- [x] `framework/tools/build/new-package.php`
  - [x] MUST continue to create the canonical baseline package scaffold
  - [x] MUST create required package artifacts for new packages:
    - [x] `composer.json`
    - [x] `README.md`
    - [x] `src/`
    - [x] `tests/Contract/`
    - [x] `tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [x] for `kind=runtime`, MUST create required runtime scaffold artifacts:
    - [x] `src/Module/<StudlySlug>Module.php`
    - [x] `src/Provider/<StudlySlug>ServiceProvider.php`
    - [x] `config/<slug>.php`
    - [x] `config/rules.php`
  - [x] MUST ensure `LICENSE` and `NOTICE` are present in newly created packages
  - [x] MUST invoke `framework/tools/build/sync_package_scaffold.php` for the newly created package after scaffold creation
  - [x] MUST NOT duplicate canonical scaffold-sync policy internally
  - [x] package scaffold completion logic MUST remain single-choice in `sync_package_scaffold.php`
- [x] `composer.json` — add repo-root mirror scripts:
  - [x] `package-scaffold:sync` → `@composer --no-interaction --working-dir=framework run-script package-scaffold:sync --`
  - [x] `package-scaffold:check` → `@composer --no-interaction --working-dir=framework run-script package-scaffold:check --`
  - [x] `package-compliance:gate` → `@composer --no-interaction --working-dir=framework run-script package-compliance:gate --`
- [x] `framework/composer.json` — add workspace scripts:
  - [x] `package-scaffold:sync` → `@php tools/build/sync_package_scaffold.php`
  - [x] `package-scaffold:check` → `@php tools/build/sync_package_scaffold.php --check`
  - [x] `package-compliance:gate` → `@php tools/gates/package_compliance_gate.php`
- [x] `framework/tools/spikes/_support/ErrorCodes.php` — register package compliance/scaffold codes:
  - [x] `CORETSIA_PACKAGE_SCAFFOLD_OUT_OF_SYNC`
  - [x] `CORETSIA_PACKAGE_SCAFFOLD_SYNC_FAILED`
  - [x] `CORETSIA_PACKAGE_COMPLIANCE_VIOLATION`
  - [x] `CORETSIA_PACKAGE_COMPLIANCE_GATE_FAILED`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Security / Redaction

- [x] gate output MUST NOT contain secrets; only package path + mismatch reason

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] Rejects missing required files deterministically (e.g. missing `config/rules.php`)
- [x] Rejects invalid composer/path mapping deterministically
- [x] Allowlist is loaded deterministically and applied consistently
- [x] Fixtures used by gate integration tests are committed and deterministic:
  - [x] `framework/tools/tests/Fixtures/package_good/**`
  - [x] `framework/tools/tests/Fixtures/package_bad/**`
- [x] `sync_package_scaffold.php --check` rejects missing canonical legal files deterministically
- [x] `sync_package_scaffold.php --check` rejects drifted `LICENSE` / `NOTICE` deterministically
- [x] `sync_package_scaffold.php` creates missing required scaffold files without rewriting existing user-owned content

### Tests (MUST)

- Integration:
  - [x] `framework/tools/tests/Integration/SyncPackageScaffoldCreatesMissingFilesTest.php`
  - [x] `framework/tools/tests/Integration/SyncPackageScaffoldCheckRejectsDriftTest.php`
  - [x] `framework/tools/tests/Integration/PackageComplianceGateAcceptsGoodFixtureTest.php`
  - [x] `framework/tools/tests/Integration/PackageComplianceGateRejectsBadFixtureTest.php`

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] Preconditions satisfied (no forward references)
- [x] Gate enforces (single-choice; MUST match `docs/architecture/PACKAGING.md`):
  - [x] canonical path: `framework/packages/<layer>/<slug>/`
  - [x] canonical composer name mapping (per PACKAGING):
    - [x] `composer=coretsia/<layer>-<slug>`
  - [x] forbidden slugs: `app|modules|shared`
  - [x] reserved slugs: `kernel`, `observability`
    - [x] reserved means these slugs are blocked for arbitrary new packages
    - [x] they MAY be used only by the canonical owner packages/paths defined by roadmap/SSoT
    - [x] example: existing canonical package `framework/packages/core/kernel/` remains valid and MUST NOT fail package-compliance because of the reserved-slug rule
  - [x] runtime packages require module metadata:
    - [x] `extra.coretsia.moduleId`
    - [x] `extra.coretsia.moduleClass`
    - [x] `extra.coretsia.providers`
    - [x] `extra.coretsia.defaultsConfigPath`
    - [x] `extra.coretsia.moduleId` MUST equal canonical `<layer>.<slug>` derived from package path
    - [x] `extra.coretsia.moduleClass` MUST point to canonical `src/Module/<StudlySlug>Module.php`
  - [x] config files return plain arrays (no wrapper root)
  - [x] if `kind=runtime` then required skeleton files exist (shape enforcement):
    - [x] `src/Module/<StudlySlug>Module.php`
    - [x] `src/Provider/<StudlySlug>ServiceProvider.php`
    - [x] `config/<slug>.php`
    - [x] `config/rules.php`
    - [x] `README.md` (must include: Observability / Errors / Security-Redaction)
  - [x] Single source of truth (MUST):
    - [x] All package identity rules enforced by this gate MUST be derived from `docs/architecture/PACKAGING.md` (no ad-hoc alternative rules)
- [x] Clear deterministic error messages
- [x] PSR-4 autoload mapping is canonical and matches `docs/architecture/PACKAGING.md`
- [x] Allowlist exists and is the ONLY grandfathering mechanism; additions are forbidden without an explicit epic/ADR
- [x] library-only support packages (e.g. contracts support, marker attributes, pure support libraries) are allowed without runtime module metadata when explicitly canonical in PACKAGING/epic ownership
  - [x] example: `framework/packages/core/dto-attribute/`

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

- [x] `framework/packages/core/contracts/src/Module/ModuleInterface.php` — module contract (`descriptor(): ModuleDescriptor`)
- [x] `framework/packages/core/contracts/src/Module/ModuleId.php` — moduleId VO (validation + normalization rules; no locale)
- [x] `framework/packages/core/contracts/src/Module/ModuleDescriptor.php` — descriptor VO (schemaVersion + metadata + runtime fields)
  - [x] exported descriptor shape contains only deterministic scalar/json-like values
    - [x] `ModuleDescriptor::toArray()` MUST NOT export PHP objects/resources/closures
    - [x] `ModuleDescriptor::toArray()` MAY export only `null|bool|int|string|list<...>|array<string,...>`
    - [x] floats are intentionally forbidden in descriptor metadata
    - [x] internal VO fields such as `ModuleId $id` are allowed and are not part of the exported descriptor shape
  - [x] exported metadata contains no closures/resources/objects/floats
  - [x] deterministic map ordering expectation for any exported array-like methods
- [x] `framework/packages/core/contracts/src/Module/ManifestReaderInterface.php` — port: read installed module manifest
- [x] `framework/packages/core/contracts/src/Module/ModePresetInterface.php` — port: preset shape accessor
- [x] `framework/packages/core/contracts/src/Module/ModePresetLoaderInterface.php` — port: load preset by name
- [x] `framework/packages/core/contracts/src/Module/ModuleManifest.php` — installed module manifest VO
  - [x] rejects duplicate module ids
  - [x] exposes deterministic module id ordering
  - [x] exports scalar/json-like manifest shape
- [x] `framework/packages/core/contracts/src/Module/Capability/` — marker interfaces folder (no logic)
- [x] `framework/packages/core/contracts/src/Module/Capability/CapabilityInterface.php`

- [x] `docs/adr/ADR-0001-module-descriptor-manifest-modepreset-ports.md`
- [x] `docs/ssot/modules-and-manifests.md` — moduleId format, descriptor schemaVersion policy, “metadata-only discovery” MUST include:
  - [x] Descriptor boundary clarification:
    - [x] `ModuleDescriptor` is a contracts descriptor/VO, not a DTO-marker class by default
    - [x] descriptor invariants are governed by contracts shape rules, not DTO gate rules
- [x] `docs/ssot/modes.md` — preset format + meaning (micro/express/hybrid/enterprise)
- [x] `docs/adr/INDEX.md` — ADR navigation entrypoint (append-only index)
  - [x] register `docs/adr/ADR-0001-module-descriptor-manifest-modepreset-ports.md`

- [x] `framework/packages/core/contracts/tests/Unit/ModuleIdFormatTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ModuleDescriptorSchemaVersionTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ModuleDescriptorIdIsDerivedFromLayerAndSlugTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ContractsDoNotDependOnPlatformTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ModuleManifestContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ModePresetInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ModePresetLoaderInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ManifestReaderInterfaceShapeContractTest.php`

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/modules-and-manifests.md`
  - [x] `docs/ssot/modes.md`

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

- [x] Contracts contain no secret values and MUST NOT leak secrets from composer metadata / env.

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (contracts-only; proven by contract tests listed below)

### Tests (MUST)

- Unit:
  - [x] `framework/packages/core/contracts/tests/Unit/ModuleIdFormatTest.php`
- Contract:
  - [x] `framework/packages/core/contracts/tests/Contract/ModuleDescriptorSchemaVersionTest.php`
  - [x] `framework/packages/core/contracts/tests/Contract/ModuleDescriptorIdIsDerivedFromLayerAndSlugTest.php`
  - [x] `framework/packages/core/contracts/tests/Contract/ContractsDoNotDependOnPlatformTest.php`
  - [x] `framework/packages/core/contracts/tests/Contract/ModuleManifestContractTest.php`
  - [x] `framework/packages/core/contracts/tests/Contract/ModePresetInterfaceShapeContractTest.php`
  - [x] `framework/packages/core/contracts/tests/Contract/ModePresetLoaderInterfaceShapeContractTest.php`
  - [x] `framework/packages/core/contracts/tests/Contract/ManifestReaderInterfaceShapeContractTest.php`

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] Preconditions satisfied (no forward references)
- [x] No forbidden deps in contracts (static scan/deptrac)
- [x] Tests green
- [x] Docs match contracts shape (`modules-and-manifests.md`, `modes.md`)
- [x] Non-goals / out of scope:
  - [x] Module discovery is NOT implemented in contracts (Kernel `ComposerManifestReader` is responsible).
  - [x] No runtime wiring / DI tags in contracts.
  - [x] No HTTP/PSR-7 types in contracts (format-neutral hard rule).
- [x] Runtime expectation (policy, NOT deps):
  - [x] Kernel implements `ManifestReaderInterface` using the Phase 0 workspace package-index shape (0.100.0) as the canonical source of `{layer, slug}`.
  - [x] Composer metadata is allowed only for OPTIONAL fields (e.g. `moduleClass`), but MUST NOT affect `moduleId` derivation.
- [x] Determinism invariants (cemented; Phase 0 alignment):
  - [x] Any API returning a *set/list* of modules/descriptors MUST define stable ordering:
    - [x] sort by `moduleId` ascending using byte-order (`strcmp`), locale-independent
  - [x] `moduleId` derivation MUST be purely metadata-based:
    - [x] derived from `{layer, slug}` only (no filesystem scan requirement in contracts)
    - [x] MUST NOT rely on locale/`setlocale`/`LC_ALL`
- [x] Lock-source alignment:
  - [x] module identity rules MUST NOT contradict Phase 0 workspace package-index fields and ordering (0.100.0)
- [x] Manifest reader returns deterministic `ModuleManifest`, not a loose descriptor list.
- [x] Mode preset required/optional/disabled sets are represented explicitly.
- [x] Mode preset loader supports deterministic listing, existence checks, strict load, and nullable try-load.

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

### Config rules contracts (MUST)

- Config validation is contract-driven but kernel-implemented.
- Package `config/rules.php` files define declarative rule arrays only.
- Config rules files MUST NOT define executable validators.
- Runtime validation MUST consume rules through contracts and kernel implementation.

### Deliverables (MUST)

#### Creates

- [x] `framework/packages/core/contracts/src/Config/ConfigRepositoryInterface.php`
  - [x] exposes key existence check
  - [x] exposes default-aware read access
  - [x] exposes full merged config tree
  - [x] exposes safe source lookup
  - [x] exposes deterministic safe explain trace
- [x] `framework/packages/core/contracts/src/Config/ConfigLoaderInterface.php`
  - [x] returns `ConfigRepositoryInterface`, not loose raw config array
- [x] `framework/packages/core/contracts/src/Config/MergeStrategyInterface.php`
  - [x] defines deterministic binary node merge boundary
- [x] `framework/packages/core/contracts/src/Config/ConfigValidatorInterface.php`
  - [x] validates a merged global config against loaded declarative rulesets
  - [x] MUST NOT expose package-specific callable validators
- [x] `framework/packages/core/contracts/src/Config/ConfigSourceType.php`
- [x] `framework/packages/core/contracts/src/Config/ConfigValueSource.php`
- [x] `framework/packages/core/contracts/src/Config/ConfigDirective.php` — directives allowlist as enum (append/prepend/remove/merge/replace)
- [x] `framework/packages/core/contracts/src/Env/EnvRepositoryInterface.php`
  - [x] distinguishes present empty string via `EnvValue`
  - [x] exposes `has()`, `get()`, `all()`, and safe `sourceOf()`
- [x] `framework/packages/core/contracts/src/Env/EnvValue.php` — VO to represent env lookup result (missing vs present; empty string is present)
- [x] `framework/packages/core/contracts/src/Env/EnvPolicy.php`

- [x] `framework/packages/core/contracts/src/Config/ConfigValidationResult.php`
  - [x] immutable result
  - [x] exposes schemaVersion in public shape
  - [x] exposes success/failure and deterministic violations

- [x] `framework/packages/core/contracts/src/Config/ConfigValidationViolation.php`
  - [x] immutable violation shape:
    - [x] `schemaVersion`
    - [x] `root`
    - [x] `path`
    - [x] `reason`
    - [x] optional safe `expected`
    - [x] optional safe `actualType`
  - [x] MUST NOT contain raw config values

- [x] `framework/packages/core/contracts/src/Config/ConfigRuleset.php`
  - [x] optional readonly DTO/shape wrapper for validated declarative rules
  - [x] exposes schemaVersion in public shape
  - [x] MUST represent rules data, not executable validation logic

- [x] `docs/adr/ADR-0002-config-env-source-tracking-directives-invariants.md`
- [x] `docs/ssot/config-and-env.md` — env policy precedence + directives allowlist + exclusive-level rule + safe explain trace contract
  - [x] In this document, `descriptor/result/shape/model` terminology follows `docs/ssot/dto-policy.md`; these models are not DTO-marker classes unless explicitly marked.

Tests:
- [x] `framework/packages/core/contracts/tests/Contract/EnvPolicyPrecedenceContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/EnvMissingVsEmptyIsDistinctContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ConfigDirectiveInvariantsContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/DirectivesAllowlistMatchesPhase0ConfigMergeLockContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ConfigDirectiveErrorPrecedenceMatchesPhase0LockContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ConfigDirectiveEmptyArrayRuleIsCementedContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ConfigSourceTypeIsStableContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ConfigSourceTypeEnumMatchesPhase0PrecedenceLockContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ConfigValueSourceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ConfigTraceModelNeverContainsRawValuesContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ConfigTraceOrderingIsDeterministicContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ConfigRulesetJsonLikeModelContractTest.php`
  - [x] locks float-forbidden JSON-like ruleset model against Phase 0 `0.70.0`
  - [x] rejects floats, `NAN`, `INF`, `-INF` at any nesting depth
  - [x] rejects executable/runtime values in declarative rulesets
  - [x] proves deterministic map key ordering and list order preservation
- [x] `framework/packages/core/contracts/tests/Contract/ConfigRepositoryInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ConfigLoaderInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/MergeStrategyInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/EnvRepositoryInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ConfigValidationShapeContractTest.php`

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/config-and-env.md`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0002-config-env-source-tracking-directives-invariants.md`
- [x] `docs/ssot/config-roots.md`
  - [x] `config/rules.php` MUST return a plain declarative ruleset array.
  - [x] `config/rules.php` MUST NOT return a callable, closure, object, or executable validator.
  - [x] Package-owned rules files define validation rules as data only.
  - [x] Runtime validation logic is kernel-owned and MUST be implemented by ConfigKernel / ConfigValidator.

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

- [x] Unknown `@directive` MUST fail validation with a deterministic code BEFORE merge (documented in `docs/ssot/config-and-env.md`).

#### Security / Redaction

- [x] Explain/source tracking MUST NOT require storing raw values (contracts-level rule)
- [x] MUST NOT leak `.env` values, passwords, tokens.
- [x] Implementation outputs may use `hash(value)` / `len(value)` only (never print raw values).
- [x] Config/Env trace models are canonical contracts shapes, not DTO-marker classes by default

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] Contract evidence locks Phase 0 invariants:
  - [x] directives allowlist + reserved `@*` namespace guard are cemented (0.90.0)
  - [x] exclusive-level rule + error precedence are cemented (0.90.0)
  - [x] float-forbidden json-like model is enforced at contracts boundary (0.70.0)
  - [x] config trace never stores raw values; ordering is deterministic (0.90.0)
  - [x] env missing vs empty is distinguishable (0.60.0 / 0.70.0 policy alignment)

### Tests (MUST)

- Contract:
  - [x] tests listed in Creates

### DoD (MUST)

- [x] Contracts compile without forbidden deps
- [x] Contract tests green
- [x] `docs/ssot/config-and-env.md` exists and matches contracts invariants
- [x] Directives allowlist cannot expand without ADR + updated locks
- [x] Lock source (Phase 0):
  - [x] Directives semantics + precedence + explain trace are SSoT-locked and MUST match Phase 0 spikes:
    - [x] 0.90.0 — config_merge directive semantics + explain ordering + reserved namespace guard
    - [x] 0.70.0 — float-forbidden json-like model
  - [x] Any expansion of directives/source-types MUST require ADR update + SSoT update + contract test update (non-optional)
- [x] Non-goals / out of scope:
  - [x] No merge/loader/explain implementation here (Kernel config engine owns it).
  - [x] No secrets in explain/trace. Source tracking metadata is limited to safe contract fields: type, root, sourceId, path, keyPath, directive, precedence, redacted, and metadata-only meta.
  - [x] Directives allowlist MUST NOT expand without ADR + updated spike locks.
- [x] Config repository exposes safe explain trace without raw values.
- [x] Config source type vocabulary distinguishes package defaults, skeleton config, app config, dotenv, process env, CLI overrides, runtime sources, and generated artifacts.
- [x] Config source tracking keeps explicit precedence metadata and does not infer precedence from source type.
- [x] Env repository keeps canonical `EnvValue` lookup and does not collapse empty string into missing.

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
- [x] `framework/packages/core/contracts/src/Observability/CorrelationIdProviderInterface.php`

Tracing:
- [x] `framework/packages/core/contracts/src/Observability/Tracing/TracerPortInterface.php`
- [x] `framework/packages/core/contracts/src/Observability/Tracing/SpanInterface.php`
- [x] `framework/packages/core/contracts/src/Observability/Tracing/ContextPropagationInterface.php`
- [x] `framework/packages/core/contracts/src/Observability/Tracing/SpanExporterInterface.php`
- [x] `framework/packages/core/contracts/src/Observability/Tracing/SamplerInterface.php`
- [x] `framework/packages/core/contracts/src/Observability/Tracing/SamplingDecision.php`

Metrics:
- [x] `framework/packages/core/contracts/src/Observability/Metrics/MeterPortInterface.php`
- [x] `framework/packages/core/contracts/src/Observability/Metrics/MetricsRendererInterface.php`

Errors:
- [x] `framework/packages/core/contracts/src/Observability/Errors/ErrorReporterPortInterface.php`
- [x] `framework/packages/core/contracts/src/Observability/Errors/ExceptionMapperInterface.php`
- [x] `framework/packages/core/contracts/src/Observability/Errors/ErrorHandlerInterface.php`
- [x] `framework/packages/core/contracts/src/Observability/Errors/ErrorSeverity.php` — severity enum (stable)
- [x] `framework/packages/core/contracts/src/Observability/Errors/ErrorDescriptor.php`
  - [x] extensions strictly json-like
  - [x] stable field set
  - [x] schemaVersion exported in public shape
  - [x] default severity is `ErrorSeverity::Error`
  - [x] no raw throwable payload
- [x] `framework/packages/core/contracts/src/Observability/Errors/ErrorHandlingContext.php`

Health:
- [x] `framework/packages/core/contracts/src/Observability/Health/HealthCheckInterface.php`
- [x] `framework/packages/core/contracts/src/Observability/Health/HealthCheckResult.php`
- [x] `framework/packages/core/contracts/src/Observability/Health/HealthStatus.php`

Profiling:
- [x] `framework/packages/core/contracts/src/Observability/Profiling/ProfilerPortInterface.php`
- [x] `framework/packages/core/contracts/src/Observability/Profiling/ProfilingSessionInterface.php`
- [x] `framework/packages/core/contracts/src/Observability/Profiling/ProfileArtifact.php`
- [x] `framework/packages/core/contracts/src/Observability/Profiling/ProfileExporterInterface.php`

Documentation:
- [x] `docs/adr/ADR-0003-observability-errordescriptor-health-profiling-ports.md`
- [x] `docs/ssot/observability-and-errors.md` — ports overview, ErrorDescriptor format-neutral rule, redaction rules
  - [x] Json-like payload rules (cemented)
    - [x] Any json-like payload exposed by contracts (e.g. `ErrorDescriptor.extensions`, profiling metadata envelopes, etc.) MUST follow Phase 0 json-like rules:
      - [x] Allowed scalars: `string|int|bool|null`
      - [x] Floats are FORBIDDEN everywhere (including `NaN`, `INF`, `-INF`)
      - [x] Containers:
        - [x] lists preserve order
        - [x] maps are key-ordered deterministically (byte-order), locale-independent
      - [x] Implementations MUST NOT log/print raw payload values; only `hash(value)` / `len(value)` is allowed for diagnostics.
  - [x] In this document, `descriptor/result/shape/model` terminology follows `docs/ssot/dto-policy.md`; these models are not DTO-marker classes unless explicitly marked.
- [x] `docs/ssot/profiling-ports.md` — profiling policy + invariants (payload opaque)

Tests:
- [x] `framework/packages/core/contracts/tests/Contract/ErrorDescriptorShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ErrorDescriptorExtensionsAreJsonLikeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ErrorDescriptorHttpStatusIsOptionalContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ErrorDescriptorSeverityEnumContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/MetricsRendererInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/SpanExporterInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/SpanInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/TracerPortInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/SamplerInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ProfilingContractsShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ProfilingSessionInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ProfilingContractsDoNotDependOnPsr7ContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ContractsDoNotReferencePsr7ContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ErrorDescriptorFieldSetIsStableContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/HealthCheckInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/HealthCheckResultShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ErrorPortsShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ErrorHandlingContextShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ErrorHandlingContextMetadataIsJsonLikeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/MeterPortInterfaceShapeContractTest.php`

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/observability-and-errors.md`
  - [x] `docs/ssot/profiling-ports.md`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0003-observability-errordescriptor-health-profiling-ports.md`

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

- [x] Contracts remain format-neutral; label allowlist is policy-level (`docs/ssot/observability.md`).
- [x] Metric label keys MUST stay within allowlist: `method,status,driver,operation,table,outcome`.
- [x] Spans/logs MUST NOT contain PII/secrets.
- [x] Profiling: `ProfileArtifact.payload` is opaque and MUST NEVER be logged or used as metric label.

#### Errors

- [x] `ErrorDescriptor` is format-neutral; `httpStatus` is an optional hint only; `extensions` are json-like only.

#### Security / Redaction

- [x] MUST NOT leak: auth/cookies/session ids/tokens/raw SQL/payload/profile payload
- [x] Allowed (implementation-side): `hash(value)` / `len(value)` / safe ids

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] `framework/packages/core/contracts/tests/Contract/ContractsDoNotReferencePsr7ContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ProfilingContractsDoNotDependOnPsr7ContractTest.php`

### Tests (MUST)

- Contract:
  - [x] tests listed above

### DoD (MUST)

- [x] Contracts remain format-neutral (no PSR-7)
- [x] ErrorDescriptor stable and contract-tested
- [x] Profiling ports stable and contract-tested
- [x] Docs exist and match ports model
- [x] Runtime expectation (policy, NOT deps):
  - [x] `platform/logging|tracing|metrics` provide noop-safe implementations.
  - [x] `platform/errors` provides `ErrorHandlerInterface` implementation + mapper registry (discovery via tag `error.mapper`).
  - [x] `platform/problem-details` adapts `ErrorDescriptor` → RFC7807 and wires `HttpErrorHandlingMiddleware` into `http.middleware.system_pre` (priority 1000) — when installed.
  - [x] Health checks are discovered via tag `health.check`.
  - [x] Profiling is implemented in `platform/profiling` (Phase 6+) and wired via kernel hook tags.
- [x] `ErrorDescriptor` is a canonical descriptor model, not a DTO-marker class by default
- [x] descriptor shape rules are enforced by contracts tests, not DTO gates

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
- "Coretsia\\Contracts\\Context\\ContextAccessorInterface with cemented signatures `has(string $key): bool` and `get(string $key): mixed` (no default parameter on `get`)."
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

- [x] `framework/packages/core/contracts/src/Context/ContextAccessorInterface.php`
  - [x] MUST:
    - [x] namespace: `Coretsia\Contracts\Context`
    - [x] signature: `public function has(string $key): bool;`
    - [x] signature: `public function get(string $key): mixed;`
    - [x] `get()` MUST NOT have a default parameter
    - [x] `has()` MUST distinguish key presence from a present `null` value
    - [x] MUST NOT: `all()`, default параметр, сеттери, мутація, storage details, full context snapshots
- [x] `framework/packages/core/contracts/tests/Contract/ContextAccessorInterfaceShapeContractTest.php`
  - [x] MUST assert:
    - [x] methods exist: `has`, `get`
    - [x] no `all()` method exists
    - [x] `has()` parameter name/type = `string $key`
    - [x] `has()` return type = `bool`
    - [x] `get()` parameter name/type = `string $key`
    - [x] `get()` return type = `mixed`
    - [x] `get()` has no default parameter
    - [x] no extra public methods introduced accidentally

#### Modifies

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] Contract test proving signature stability.

### Tests (MUST)

- [x] `framework/packages/core/contracts/tests/Contract/ContextAccessorInterfaceShapeContractTest.php`

### DoD (MUST)

- [x] Deliverables complete (creates), paths exact
- [x] No runtime dependencies added
- [x] Signatures are cemented and covered by contract tests
- [x] Context accessor is a runtime read port, not a DTO/result/descriptor model

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
  - `framework/packages/core/contracts/tests/Contract/ErrorDescriptorFieldSetIsStableContractTest.php` — exported field set/order evidence (contracts)
  - `framework/packages/core/contracts/tests/Contract/ErrorPortsShapeContractTest.php` — nullable mapper/context evidence (contracts)

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

- [x] `docs/ssot/errors-boundary.md` — boundary + forbidden deps + canonical flow
- [x] `docs/ssot/error-descriptor.md` — fields + rules + examples (no secrets)
  - [x] Extensions payload constraints (cemented)
    - [x] `ErrorDescriptor.extensions` MUST be json-like and MUST follow Phase 0 float-forbidden policy:
      - [x] floats (including NaN/INF) are forbidden at any nesting depth
      - [x] extensions MUST be safe-by-design: MUST NOT contain raw headers/cookies/auth/session/token/payload/sql
      - [x] examples in this document MUST NOT contain secrets/PII
  - [x] `ErrorDescriptor` is a canonical descriptor shape for normalized errors
  - [x] it is NOT automatically a DTO under DTO marker policy
  - [x] DTO gates apply only to explicitly marked DTO transport classes
  - [x] `docs/ssot/error-descriptor.md` is the single human-readable field reference for `ErrorDescriptor`
  - [x] `docs/ssot/observability-and-errors.md` remains ports/boundary/redaction overview and MUST NOT redefine a competing field-by-field `ErrorDescriptor` schema

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/errors-boundary.md`
  - [x] `docs/ssot/error-descriptor.md`
- [x] `docs/ssot/observability-and-errors.md` — delegate the field-by-field `ErrorDescriptor` schema to `docs/ssot/error-descriptor.md` and keep this document as the ports/boundary/redaction overview

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Security / Redaction

- [x] MUST NOT leak secrets/PII in errors/logs/spans
- [x] MUST NOT leak secrets/PII in examples

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (doc-only; shape is enforced by existing contract tests)

### Tests (MUST)

N/A (doc-only), but MUST reference enforcement evidence in:

- [x] `framework/packages/core/contracts/tests/Contract/ContractsDoNotReferencePsr7ContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ErrorDescriptorShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ErrorDescriptorHttpStatusIsOptionalContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ErrorDescriptorFieldSetIsStableContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ErrorPortsShapeContractTest.php`

Future runtime evidence (NOT a precondition of this doc epic; referenced once available):

- [x] `framework/packages/platform/errors/tests/Contract/ErrorHandlerNeverThrowsContractTest.php`

### DoD (MUST)

- [x] Docs exist and match contracts model (`ErrorDescriptor`)
- [x] No contradictions with tags registry and dep-law
- [x] No duplicate alternative shapes elsewhere
- [x] Runtime expectation (policy, NOT deps):
  - [x] `platform/errors` implements error normalization + mapper registry (discovery via tag `error.mapper`).
  - [x] `platform/problem-details` wires `HttpErrorHandlingMiddleware` into `http.middleware.system_pre` with priority `1000`.

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

- [x] `framework/packages/core/contracts/src/Routing/RouteDefinition.php`
  - [x] safe scalar/json-like fields only
  - [x] deterministic exported shape/order for descriptor maps
  - [x] schemaVersion exported in public shape
  - [x] methods normalized to uppercase, unique, sorted list
  - [x] pathTemplate requires leading `/`
- [x] `framework/packages/core/contracts/src/Routing/RouteMatch.php`
  - [x] safe scalar/json-like fields only
  - [x] deterministic exported shape/order for descriptor maps
  - [x] schemaVersion exported in public shape
  - [x] parameters are deterministic string map
  - [x] pathTemplate requires leading `/`
- [x] `framework/packages/core/contracts/src/Routing/RouterInterface.php`
- [x] `framework/packages/core/contracts/src/Routing/RouteProviderInterface.php`
- [x] `framework/packages/core/contracts/src/HttpApp/ActionInvokerInterface.php`
- [x] `framework/packages/core/contracts/src/HttpApp/ArgumentResolverInterface.php`

- [x] `docs/adr/ADR-0005-routing-httpapp-ports.md`
- [x] `docs/ssot/routing-and-http-app-contracts.md` — boundary rules + examples (no PSR-7)

- [x] `framework/packages/core/contracts/tests/Contract/RoutingContractsDoNotUsePsr7Test.php`
- [x] `framework/packages/core/contracts/tests/Contract/HttpAppContractsAreFormatNeutralTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/RouteProviderInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/RouteDefinitionShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/RouteMatchShapeContractTest.php`

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/routing-and-http-app-contracts.md`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0005-routing-httpapp-ports.md`

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

- [x] Route template (not raw path) is canonical routing metadata/context and MAY be used for matching/debugging/span context.
- [x] It MUST NOT become a metric label unless `docs/ssot/observability.md` explicitly extends the label allowlist.

#### Security / Redaction

- [x] Contracts VOs MUST NOT include raw headers/cookies/body/payloads (format-neutral boundary)

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] `framework/packages/core/contracts/tests/Contract/RoutingContractsDoNotUsePsr7Test.php`
- [x] `framework/packages/core/contracts/tests/Contract/HttpAppContractsAreFormatNeutralTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/RouteProviderInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/RouteDefinitionShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/RouteMatchShapeContractTest.php`

### Tests (MUST)

- Contract:
  - [x] tests listed above

### DoD (MUST)

- [x] Ports exist + tested
- [x] Docs exist
- [x] No PSR-7 leakage in contracts
- [x] Runtime expectation (policy, NOT deps):
  - [x] `platform/routing` compiles routes artifact and sets canonical routing metadata/context key `path_template` after match.
  - [x] Any concrete Foundation constant or key-carrier class for that context key is owned and introduced only by the corresponding Foundation epic.
  - [x] `platform/http-app` supplies RouterMiddleware and wires it into the app middleware slot at priority `100`.
    - [x] Slot/tag naming MUST be explicit: canonical tag is `http.middleware.app`.
    - [x] Legacy `http.middleware.user` MUST be treated only as deprecated/renamed terminology and MUST NOT appear as a current tag name in contracts, SSoT, defaults, or gates.
- [x] Middleware slot alignment (cemented):
  - [x] Router middleware wiring policy MUST use the canonical slot taxonomy `system/app/route`, with canonical tag names reserved in `docs/ssot/tags.md`.
  - [x] Any mention of legacy `http.middleware.user*` MUST be treated as deprecated/renamed and MUST NOT appear in new contracts/SSoT
- [x] `RouteDefinition` and `RouteMatch` are contracts routing shapes/descriptors, not DTO-marker classes by default

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

- [x] `framework/packages/core/contracts/src/Runtime/ResetInterface.php`
- [x] `framework/packages/core/contracts/src/Runtime/Hook/BeforeUowHookInterface.php`
- [x] `framework/packages/core/contracts/src/Runtime/Hook/AfterUowHookInterface.php`

- [x] `docs/adr/ADR-0006-reset-interface-uow-hooks.md`
- [x] `docs/ssot/uow-and-reset-contracts.md` — rules: format-neutral hooks, reset discipline, tags used (refer to tag registry)

- [x] `framework/packages/core/contracts/tests/Contract/ResetInterfaceIsMinimalContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/HookInterfacesDoNotDependOnPlatformTest.php`

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/uow-and-reset-contracts.md`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0006-reset-interface-uow-hooks.md`

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

- [x] `framework/packages/core/contracts/tests/Contract/ResetInterfaceIsMinimalContractTest.php`

### Tests (MUST)

- Contract:
  - [x] tests listed above

### DoD (MUST)

- [x] Ports exist + tested
- [x] Docs exist
- [x] No forbidden deps
- [x] Runtime expectation (policy, NOT deps):
  - [x] Foundation reset orchestrator calls `reset()` on services discovered through the effective Foundation reset tag.
  - [x] Kernel executes hooks via tags `kernel.hook.before_uow` / `kernel.hook.after_uow` in deterministic order.
- [x] Acceptance scenario (policy intent):
  - [x] When a worker processes two jobs, stateful services are reset between them and hooks run deterministically.

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
  - `error.mapper` — existing runtime discovery tag for documented ValidationException mapping policy

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
  - `platform/validation` provides validator behavior.
  - Runtime error mapping is discovered through `error.mapper` owned by `platform/errors`.
  - A future runtime mapper maps `ValidationException` → `ErrorDescriptor(422)`.

### Deliverables (MUST)

#### Creates

- [x] `framework/packages/core/contracts/src/Validation/ValidatorInterface.php`
- [x] `framework/packages/core/contracts/src/Validation/ValidationResult.php`
  - [x] contains deterministic violation collection shape
  - [x] exposes violations as ordered list shape
  - [x] carries no raw input payload values
- [x] `framework/packages/core/contracts/src/Validation/Violation.php`
  - [x] Violation fields are scalar/json-like only
  - [x] meta/extensions float-forbidden
  - [x] meta must be json-like / float-free / safe only
  - [x] no raw input values
  - [x] stable order of violations is runtime concern, but shape must support deterministic sort keys:
    - [x] `path`
    - [x] `code`
    - [x] `rule`
    - [x] `index`
- [x] `framework/packages/core/contracts/src/Validation/ValidationException.php`

- [x] `docs/adr/ADR-0007-validation-ports.md`
- [x] `docs/ssot/validation-contracts.md` — shape + deterministic exception code + mapping notes
  - [x] `ValidationResult` and `Violation` are contracts result/descriptor shapes, not DTO-marker classes by default

- [x] `framework/packages/core/contracts/tests/Contract/ValidationContractsTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ValidationExceptionHasDeterministicCodeTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ValidationViolationShapeIsSafeContractTest.php`

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/validation-contracts.md`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0007-validation-ports.md`

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

- [x] `ValidationException` MUST use deterministic code `CORETSIA_VALIDATION_FAILED`.
- [x] Runtime mapping policy is documented: `ValidationException` maps via `error.mapper` to `ErrorDescriptor` with HTTP status hint `422`.

#### Security / Redaction

- [x] Violations meta MUST be json-like only.
- [x] Violations MUST NOT include secrets/PII by default — policy documented.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] `framework/packages/core/contracts/tests/Contract/ValidationExceptionHasDeterministicCodeTest.php`

### Tests (MUST)

- Contract:
  - [x] tests listed above

### DoD (MUST)

- [x] Ports exist + tested
- [x] Docs exist
- [x] No forbidden deps

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

- [x] `framework/packages/core/contracts/src/Filesystem/DiskInterface.php`

- [x] `docs/adr/ADR-0008-filesystem-ports.md`
- [x] `docs/ssot/filesystem-contracts.md` — ports + invariants

- [x] `framework/packages/core/contracts/tests/Contract/FilesystemDiskInterfaceShapeContractTest.php`

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/filesystem-contracts.md`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0008-filesystem-ports.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Security / Redaction

- [x] Path strings are treated as sensitive in logs and diagnostics by default — policy documented.
- [x] Raw logical paths and file contents MUST NOT be logged, traced, exported as metric labels, or copied into unsafe diagnostics by default.

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (contracts-only; proven by contract test)

### Tests (MUST)

- Contract:
  - [x] `framework/packages/core/contracts/tests/Contract/FilesystemDiskInterfaceShapeContractTest.php`

### DoD (MUST)

- [x] Contract exists + tested
- [x] Docs exist
- [x] Runtime usage policy is documented:
  - [x] `DiskInterface` is the contracts boundary for future `platform/filesystem`, `platform/session`, `platform/uploads`, and `platform/lock`.
  - [x] Future `integrations/*` filesystem drivers MUST implement `DiskInterface`.
- [x] Non-goals / out of scope are documented:
  - [x] Path safety policy is owned by future `platform/filesystem`, not `core/contracts`.

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
- [x] `framework/packages/core/contracts/src/Database/SqlQueryInterface.php`
- [x] `framework/packages/core/contracts/src/Database/SqlQuery.php` — immutable contracts query model for migrations/low-level calls
  - [x] `SqlQuery` is an immutable contracts VO and is outside DTO marker policy by default
- [x] `framework/packages/core/contracts/src/Database/DatabaseDriverInterface.php` MUST:
  - [x] MUST treat `$config` as secrets-allowed driver-owned config (connection-scoped)
  - [x] MUST treat `$tuning` as NO-secrets driver tuning (driver default + per-connection override)
  - [x] MUST NOT read global config directly (driver is pure function of inputs)
  - [x] `id(): string`
    - [x] MUST be non-empty logical driver id (stable across runs)
    - [x] MUST match regex: `^[a-z][a-z0-9_-]*$` (deterministic, vendor-neutral)
    - [x] Canonical allowlist for platform-supported drivers is owned by future `platform/database` config SSoT, NOT by contracts.
    - [x] Contracts lock only the generic logical driver-id shape/regex and must not enumerate the platform-supported driver set.
  - [x] `connect(string $connectionName, array $config, array $tuning): ConnectionInterface`
    - [x] `config` is driver-owned allowlisted (`database.connections.<name>.config`)
    - [x] `$tuning` is EFFECTIVE driver tuning (NO secrets):
      - [x] computed by platform/database as merge of:
        - [x] driver defaults: `database.drivers.<driverId>.tuning`
        - [x] per-connection override: `database.connections.<name>.tuning` (optional)
      - [x] driver MUST treat `$tuning` as final input (no global reads)
    - [x] PDO canonical options are NOT passed via contract surface as PDO attrs (they remain canonical string-key map inside config/tuning; driver may read them from `config.pdo_options`)
    - [x] для multi-connection `DatabaseDriverInterface::connect(...)` вже приймає `$connectionName`, тож реалізація проста: драйвер зберігає name/driverId у connection object.
- [x] `framework/packages/core/contracts/src/Database/ConnectionInterface.php` MUST:
  - [x] `execute(SqlQueryInterface $query): QueryResultInterface`
  - [x] `beginTransaction(): void`
  - [x] `commit(): void`
  - [x] `rollBack(): void`
  - [x] `dialect(): SqlDialectInterface` *(critical for migrations + compiler parity; no vendor types)*
  - [x] `ConnectionInterface::name(): string` — connection name (`main`, `analytics`, …)
    - [x] MUST be non-empty
    - [x] MUST equal the `$connectionName` argument passed into `DatabaseDriverInterface::connect(...)`
  - [x] `ConnectionInterface::driverId(): string` — logical driver id
    - [x] MUST be non-empty
    - [x] MUST equal `DatabaseDriverInterface::id()` of the driver instance that produced this connection
    - [x] MUST match regex: `^[a-z][a-z0-9_-]*$`
    - [x] Platform-supported allowlist is enforced by `platform/database` config rules (not contracts)
- [x] `framework/packages/core/contracts/src/Database/QueryResultInterface.php`
  - [x] query results expose canonical scalar-only DB value domain (`int|string|bool|null`)
  - [x] contracts never expose float values
- [x] `framework/packages/core/contracts/src/Database/SqlDialectInterface.php`
  - [x] Bridge для driver-specific SQL діалекту: відповідає за відмінності типу limit/offset (SQL Server), returning/identity, boolean literals, etc. Це робить інтеграції “реальними”.

Migrations:
- [x] `framework/packages/core/contracts/src/Migrations/MigrationInterface.php`
  - [x] up(ConnectionInterface $connection): void
  - [x] down(ConnectionInterface $connection): void
  - [x] no metadata methods
  - [x] no discovery/order policy
  - [x] no migration runner/context/output dependency
  - [x] no PDO/vendor/platform/integration types

Documentation:
- [x] `docs/adr/ADR-0009-database-and-migrations-ports.md`
- [x] `docs/ssot/database-contracts.md` — contracts + policy notes
  - [x] `DbValue` MUST be `int|string|bool|null` (NO float anywhere)
  - [x] `DbRow` MUST be `array<string, DbValue>`
  - [x] `DbRows` MUST be `list<DbRow>`
  - [x] `SqlQueryInterface`:
    - [x] `sql(): string`
    - [x] `bindings(): list<DbValue>` *(order preserved; MUST match placeholder order; no associative maps here)*
  - [x] Add the `name()` / `driverId()` invariants above
  - [x] Explicitly state: driver id is logical id (NOT vendor driver name like `sqlsrv`)
- [x] `docs/ssot/migrations-contracts.md` — rules + determinism notes

Tests:
- [x] `framework/packages/core/contracts/tests/Contract/SqlQueryShapeContractTest.php` MUST assert:
  - [x] no `__toString` leakage
  - [x] canonical method shapes
  - [x] immutable/final/readonly value object shape
  - [x] binding order is preserved
  - [x] associative bindings are rejected
  - [x] float bindings are rejected
  - [x] nested arrays/objects/resources/closures are rejected
  - [x] empty SQL strings are rejected
  - [x] whitespace-only SQL strings are rejected
  - [x] multiline non-empty SQL strings are accepted
  - [x] raw SQL is not exposed through exception messages
  - [x] structural validation failures throw `InvalidArgumentException`
- [x] `framework/packages/core/contracts/tests/Contract/DatabaseContractsShapeContractTest.php` MUST assert that:
  - [x] `ConnectionInterface` exposes `name(): string`
  - [x] `ConnectionInterface` exposes `driverId(): string`
  - [x] a minimal fixture implementation can expose a non-empty `name()`
  - [x] a minimal fixture implementation can expose a non-empty regex-valid `driverId()`
  - [x] actual runtime driver implementations remain responsible for enforcing produced connection invariants
- [x] `framework/packages/core/contracts/tests/Contract/MigrationInterfaceShapeContractTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/DatabaseContractsNeverExposeFloatTypeContractTest.php`

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/database-contracts.md`
  - [x] `docs/ssot/migrations-contracts.md`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0009-database-and-migrations-ports.md`

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

- [x] raw SQL MUST NOT be logged/used as metric label (policy note; implemented in platform/database)
- [x] migrations tooling MUST NOT log raw SQL (policy note)
- [x] Database result values exposed by contracts MUST NOT use `float` anywhere.
  - [x] Integers → `int` (when representable safely)
  - [x] Decimals / floats / bigints → `string`
  - [x] Booleans → `bool`
  - [x] NULL → `null`

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (contracts-only; proven by contract tests)

### Tests (MUST)

- Contract:
  - [x] `framework/packages/core/contracts/tests/Contract/SqlQueryShapeContractTest.php`
  - [x] `framework/packages/core/contracts/tests/Contract/DatabaseContractsShapeContractTest.php`
  - [x] `framework/packages/core/contracts/tests/Contract/MigrationInterfaceShapeContractTest.php`
  - [x] `framework/packages/core/contracts/tests/Contract/DatabaseContractsNeverExposeFloatTypeContractTest.php`

### DoD (MUST)

- [x] Contracts exist + tested
- [x] Docs exist
- [x] No vendor concretes in contracts
- [x] Runtime expectation (policy, NOT deps):
  - [x] `platform/database` uses only these ports; driver packages (e.g. `platform/database-driver-*`) implement them.
  - [x] `platform/migrations` executes `MigrationInterface` migrations; discovery/CLI is runtime-owned.

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

- [x] `framework/packages/core/contracts/src/RateLimit/RateLimitStoreInterface.php`
- [x] `framework/packages/core/contracts/src/RateLimit/RateLimitState.php`
- [x] `framework/packages/core/contracts/src/RateLimit/RateLimitDecision.php`
- [x] `framework/packages/core/contracts/src/RateLimit/RateLimitKeyHasherInterface.php`

- [x] `docs/adr/ADR-0011-ratelimit-ports.md`
- [x] `docs/ssot/rate-limit-contracts.md` — invariants (no correlation_id/request_id in keys; no raw path labels)
  - [x] rate-limit decision/state models are contracts models, not DTO-marker classes by default

- [x] `framework/packages/core/contracts/tests/Contract/RateLimitContractsShapeContractTest.php`

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/rate-limit-contracts.md`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0011-ratelimit-ports.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [x] labels only from allowlist; no user_id/correlation_id/request_id/tenant_id as labels
- [x] Keys MUST NOT be logged; only `hash/len` if needed (implementation-side).
- [x] No correlation_id/request_id in keys; no raw path labels.

#### Security / Redaction

- [x] do not log keys/tokens/ids; only `hash/len` if needed (implementation-side)

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (contracts-only; proven by contract test)

### Tests (MUST)

- Contract:
  - [x] `framework/packages/core/contracts/tests/Contract/RateLimitContractsShapeContractTest.php`

### DoD (MUST)

- [x] Contracts exist + tested
- [x] Docs exist
- [x] Runtime expectation (policy, NOT deps):
  - [x] `platform/http` RateLimit middleware uses these ports when enabled (`http.rate_limit.early.enabled`, `http.rate_limit.enabled`).
  - [x] Identity-aware key building MUST prefer `actor_id` (safe) then `client_ip`.

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

- [x] `framework/packages/core/contracts/src/Mail/MailerInterface.php`
- [x] `framework/packages/core/contracts/src/Mail/MailTransportInterface.php`
- [x] `framework/packages/core/contracts/src/Mail/MailMessage.php`
  - [x] message data shape MUST NOT be logged raw
  - [x] mail message model is a contracts transport model but is outside DTO marker policy unless explicitly marked
- [x] `framework/packages/core/contracts/src/Mail/MailException.php`

- [x] `docs/adr/ADR-0012-mail-port.md`
- [x] `docs/ssot/mail-contracts.md` — ports + redaction rules

- [x] `framework/packages/core/contracts/tests/Contract/MailContractsShapeContractTest.php`

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/mail-contracts.md`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0012-mail-port.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Security / Redaction

- [x] Contract-level redaction policy is documented: raw recipients, subject, body, credentials, provider payloads, and provider responses MUST NOT be exposed through diagnostics.
- [x] `MailMessage::toArray()` exposes only safe deterministic shape data: counts, lengths, safe headers, and safe metadata; it MUST NOT expose raw recipients, raw subject, or raw body.
- [x] Runtime redaction expectation is documented as policy: future implementations MUST NOT leak recipients, message body, credentials, provider payloads, or provider responses in logs, errors, metrics, spans, health output, CLI output, or debug output.
- [x] Transport failure reporting policy is documented: future transports MUST redact provider, transport, queue, credential, recipient, subject, body, and backend details before reporting through `MailException` or runtime-owned error reporting.

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (contracts-only; proven by contract test)

### Tests (MUST)

- Contract:
  - [x] `framework/packages/core/contracts/tests/Contract/MailContractsShapeContractTest.php`

### DoD (MUST)

- [x] Contracts exist + tested
- [x] Docs exist
- [x] Runtime expectation is documented as policy, NOT implemented by this epic:
  - [x] Future `platform/mail` is documented as the expected runtime owner that uses these contracts.
  - [x] Future mail transports are documented as owner packages outside `core/contracts`, including future `integrations/*`.
  - [x] Future runtime owners are documented to resolve credentials/secrets through the canonical secrets port or owner-approved secret mechanism, never through mail contracts and never by printing/logging secrets.

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

- [x] `framework/packages/core/contracts/src/Secrets/SecretsResolverInterface.php`

- [x] `docs/adr/ADR-0013-secrets-port.md`
- [x] `docs/ssot/secrets-contracts.md` — usage rules + redaction requirements

- [x] `framework/packages/core/contracts/tests/Contract/SecretsResolverInterfaceShapeContractTest.php`

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/secrets-contracts.md`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0013-secrets-port.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Security / Redaction

- [x] MUST NOT leak secret values anywhere (logs/spans/metrics/stdout/http debug)
- [x] Allowed: `ref`, `hash(value)` (without printing value), `len(value)`

### Verification (TEST EVIDENCE) (MUST when applicable)

N/A (contracts-only; proven by contract test)

### Tests (MUST)

- Contract:
  - [x] `framework/packages/core/contracts/tests/Contract/SecretsResolverInterfaceShapeContractTest.php`

### DoD (MUST)

- [x] Contract exists + tested
- [x] Docs exist
- [x] Downstream packages can rely on this port without new deps
- [x] Runtime expectation (policy, NOT deps):
  - [x] `platform/secrets` binds a resolver (Null/Env/Vault/…); downstream packages depend ONLY on `SecretsResolverInterface`.
  - [x] Debug outputs (CLI/HTTP debug endpoints) MUST redact and MUST NOT print resolved secret values.

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
      - prio 950  `platform/http` → `\Coretsia\Platform\Http\Middleware\CorrelationIdMiddleware::class` (auto)
      - prio 940  `platform/http` → `\Coretsia\Platform\Http\Middleware\RequestIdMiddleware::class` (auto if `http.request_id.enabled`)
      - prio 930  `platform/http` → `\Coretsia\Platform\Http\Middleware\TraceContextMiddleware::class` (auto)
      - prio 920  `platform/http` → `\Coretsia\Platform\Http\Middleware\HttpMetricsMiddleware::class` (auto)
      - prio 910  `platform/http` → `\Coretsia\Platform\Http\Middleware\AccessLogMiddleware::class` (auto)
      - prio 900  `platform/http` → `\Coretsia\Platform\Http\Maintenance\MaintenanceMiddleware::class` (auto if enabled)
      - prio 880  `platform/http` → `\Coretsia\Platform\Http\Middleware\TrustedProxyMiddleware::class` (auto if `http.proxy.enabled`)
      - prio 870  `platform/http` → `\Coretsia\Platform\Http\Middleware\RequestContextMiddleware::class` (auto if `http.context.enrich.enabled`)
      - prio 860  `platform/http` → `\Coretsia\Platform\Http\Middleware\TrustedHostMiddleware::class` (auto if `http.hosts.enabled`)
      - prio 850  `platform/http` → `\Coretsia\Platform\Http\Middleware\HttpsRedirectMiddleware::class` (auto if `http.https_redirect.enabled`)
      - prio 840  `platform/http` → `\Coretsia\Platform\Http\Middleware\CorsMiddleware::class` (auto if `http.cors.enabled`)
      - prio 830  `platform/http` → `\Coretsia\Platform\Http\Middleware\RequestBodySizeLimitMiddleware::class` (auto)
      - prio 820  `platform/http` → `\Coretsia\Platform\Http\Middleware\MethodOverrideMiddleware::class` (auto if `http.method_override.enabled`)
      - prio 810  `platform/http` → `\Coretsia\Platform\Http\Middleware\ContentNegotiationMiddleware::class` (auto if `http.negotiation.enabled`)
      - prio 800  `platform/http` → `\Coretsia\Platform\Http\Middleware\JsonBodyParserMiddleware::class` (auto if `http.request.json.enabled`)
      - prio 790  `platform/http` → `\Coretsia\Platform\Http\Middleware\EarlyRateLimitMiddleware::class` (auto if `http.rate_limit.early.enabled`)
      - prio 580  `platform/http` → `\Coretsia\Platform\Http\Debug\DebugEndpointsMiddleware::class` (OPTIONAL SHAPE, dev-only; must be opt-in)

      - (optional packages; NOT referenced by `platform/http` defaults; they self-register if installed):
        - prio 1000 `platform/problem-details` → `\Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware::class` (if installed, it SHOULD register itself)
        - prio 600  `platform/health` → `\Coretsia\Platform\Health\Http\Middleware\HealthEndpointsMiddleware::class` (if installed, it SHOULD register itself)
        - prio 590  `platform/metrics` → `\Coretsia\Platform\Metrics\Http\Middleware\MetricsEndpointMiddleware::class` (if installed, it SHOULD register itself)

    - Slot `http.middleware.system`:
      - (reserved; empty by default)

    - Slot `http.middleware.system_post`:
      - prio 900  `platform/http` → `\Coretsia\Platform\Http\Middleware\CacheHeadersMiddleware::class` (auto if `http.cache_headers.enabled`)
      - prio 800  `platform/http` → `\Coretsia\Platform\Http\Middleware\EtagMiddleware::class` (auto if `http.etag.enabled`)
      - prio 700  `platform/http` → `\Coretsia\Platform\Http\Middleware\CompressionMiddleware::class` (auto if `http.compression.enabled`)
      - prio 600  `platform/http` → `\Coretsia\Platform\Http\Middleware\SecurityHeadersMiddleware::class` (auto if `http.security_headers.enabled`)

      - (optional packages; NOT referenced by `platform/http` defaults; they self-register if installed):
        - prio 650  `platform/streaming` → `\Coretsia\Platform\Streaming\Http\Middleware\DisableBufferingMiddleware::class` (if installed, it SHOULD register itself)
        - prio 500  `devtools/dev-tools` → `\Coretsia\Devtools\DevTools\Http\Middleware\DebugbarMiddleware::class` (if installed, dev-only, it SHOULD register itself)

    - Slot `http.middleware.app_pre`:
      - prio 150  `platform/http` → `\Coretsia\Platform\Http\Middleware\RateLimitMiddleware::class` (auto if `http.rate_limit.enabled`; identity-aware placement)

      - (optional packages; NOT referenced by `platform/http` defaults; they self-register if installed):
        - prio 500  `enterprise/tenancy` → `\Coretsia\Enterprise\Tenancy\Http\Middleware\TenantContextMiddleware::class` (if installed, it SHOULD register itself)
        - prio 350  `platform/async` → `\Coretsia\Platform\Async\Http\Middleware\RequestTimeoutMiddleware::class` (if installed, it SHOULD register itself)
        - prio 300  `platform/session` → `\Coretsia\Platform\Session\Http\Middleware\SessionMiddleware::class` (if installed, it SHOULD register itself)
        - prio 200  `platform/auth` → `\Coretsia\Platform\Auth\Http\Middleware\AuthMiddleware::class` (if installed, it SHOULD register itself)
        - prio 100  `platform/security` → `\Coretsia\Platform\Security\Http\Middleware\CsrfMiddleware::class` (if installed, it SHOULD register itself)
        - prio 80   `platform/uploads` → `\Coretsia\Platform\Uploads\Http\Middleware\MultipartFormDataMiddleware::class` (if installed, it SHOULD register itself)
        - prio 50   `platform/inbox` → `\Coretsia\Platform\Inbox\Http\Middleware\IdempotencyKeyMiddleware::class` (if installed, it SHOULD register itself)

    - Slot `http.middleware.app`:
      - (optional packages; NOT referenced by `platform/http` defaults; they self-register if installed):
        - prio 100  `platform/http-app` → `\Coretsia\Platform\HttpApp\Middleware\RouterMiddleware::class` (if installed, it SHOULD register itself)

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
      - `\Coretsia\Platform\Auth\Http\Middleware\RequireAuthMiddleware::class`
      - `\Coretsia\Platform\Auth\Http\Middleware\RequireAbilityMiddleware::class`

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

- [x] `docs/ssot/http-middleware-catalog.md` — canonical catalog reference (slot/priority/owner/toggle), single-choice MUST include:
  - [x] Early pre-identity throttling and identity-aware throttling are distinct middleware roles:
    - [x] `\Coretsia\Platform\Http\Middleware\EarlyRateLimitMiddleware::class` → `http.middleware.system_pre`
    - [x] `\Coretsia\Platform\Http\Middleware\RateLimitMiddleware::class` → `http.middleware.app_pre`

#### Modifies

- [x] `docs/ssot/INDEX.md` — register `docs/ssot/http-middleware-catalog.md`

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

- [x] Context reads:
  - [x] semantic context keys:
    - [x] `correlation_id`
    - [x] `uow_id`
    - [x] `uow_type`
- [x] Context writes (safe only):
  - [x] semantic context keys:
    - [x] `correlation_id`
    - [x] `uow_id`
    - [x] `uow_type`
  - [x] request-safe semantic keys only:
    - [x] `client_ip`
    - [x] `scheme`
    - [x] `host`
    - [x] `path`
    - [x] `user_agent`
  - [x] reserved later safe keys (introduced only by their owner epics + `ContextKeys` SSoT; not baseline writers here):
    - [x] `request_id`
    - [x] `path_template`
    - [x] `http_response_format`
    - [x] `actor_id`
    - [x] `tenant_id`
- [x] Reset discipline:
  - [x] stateful services implement `ResetInterface`
  - [x] stateful services MUST be tagged `kernel.stateful` as the fixed enforcement marker
  - [x] stateful services MUST also be discoverable through Foundation reset discovery (effective reset tag; reserved default `kernel.reset`)
  - [x] runtime execution MUST NOT depend on `kernel.stateful`; it is enforcement-only

- [x] Concrete `Coretsia\Contracts\Context\ContextKeys` constants are introduced and locked only by the owning Foundation epic; this coordination epic MUST NOT require that class before the owner package is reviewed and fixed.

#### Observability (policy-compliant)

- [x] Spans:
  - [x] `http.request` (attrs safe; per `docs/ssot/observability.md`)
- [x] Metrics:
  - [x] `http.request_total` (labels: `method,status,outcome` only)
  - [x] `http.request_duration_ms` (labels: `method,status,outcome` only)
- [x] Label normalization applied (if needed):
  - [x] `uow_type -> operation` (for cross-runtime metrics if used)
- [x] Rate-limit observability policy:
  - [x] early and identity-aware rate limiting MUST remain distinguishable in code/docs/tests
  - [x] no new metric label keys are introduced for this distinction unless separately allowed by observability SSoT
  - [x] any distinction between early vs identity-aware rate limiting MUST be encoded via metric name choice, event name, or internal wiring — not via forbidden high-cardinality labels
- [x] Logs:
  - [x] structured access log per request; MUST NOT emit raw path, raw query, headers, cookies, body, auth/session ids, tokens, or raw SQL
  - [x] if path-like information is needed, use `path_template` or `hash(value)` / `len(value)`, never raw `path`
  - [x] redaction applied; no secrets/PII/raw payloads

#### Errors

- Exceptions introduced:
  - N/A (no new exception taxonomy in this epic)
- [x] Policy anchors:
  - [x] error taxonomy is defined in `core/contracts` contracts/descriptor models and related contracts epics
  - [x] concrete error flow is implemented by `platform/errors`
  - [x] error rendering is owned by `platform/problem-details` middleware (outermost wrapper)

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] auth/cookies/session ids/tokens/raw SQL/raw payload
- [x] Allowed:
  - [x] `hash(value)` / `len(value)` / safe ids

### Canonical baseline catalog rules (MUST)

- `platform/http/config/http.php` MUST contain ONLY middlewares shipped by `platform/http`.
- Optional/external packages MUST NOT be referenced by class-string defaults inside `platform/http` config.
- Optional/external packages MAY be documented in `docs/ssot/http-middleware-catalog.md` as “if installed, they SHOULD register themselves”.
- `platform/http` MUST treat early and identity-aware rate limiting as two distinct middleware responsibilities.
- A single middleware class MUST NOT be auto-wired into both `http.middleware.system_pre` and `http.middleware.app_pre`.
- Early rate limiting and identity-aware rate limiting MUST use distinct config toggles.

### Canonical baseline placement (single-choice) (MUST)

- Rate limit middleware placement is single-choice and non-negotiable:
  - `\Coretsia\Platform\Http\Middleware\EarlyRateLimitMiddleware::class` MAY be registered ONLY into `http.middleware.system_pre`.
  - It MUST NOT be present in `http.middleware.system|system_post|app_pre|app|app_post|route_pre|route|route_post`.
  - Rationale: early anonymous/IP/infra throttling must happen before app identity context is available and before deeper app work is performed.

  - `\Coretsia\Platform\Http\Middleware\RateLimitMiddleware::class` MAY be registered ONLY into `http.middleware.app_pre`.
  - It MUST NOT be present in `http.middleware.system_pre|system|system_post|app|app_post|route_pre|route|route_post`.
  - Rationale: identity-aware decisions must run after app-level identity context is available.

### Verification (TEST EVIDENCE) (MUST when applicable)

- Future owner-package evidence (reference only; NOT owned by this epic):
  - [x] `framework/packages/core/contracts/tests/Contract/ContextKeysAreStableContractTest.php`
  - [x] `framework/packages/platform/tracing/tests/Contract/W3CPropagationDeterministicContractTest.php`
  - [x] `framework/packages/platform/metrics/tests/Contract/NoopNeverThrowsContractTest.php`
  - [x] `framework/packages/platform/logging/tests/Integration/CorrelationIdIsAlwaysPresentInLogsTest.php`

### Tests (MUST)

- Gates/Arch:
  - [x] CI runs `framework/tools/gates/cross_cutting_contract_gate.php` (from 1.50.0 rails)
- Referenced owner-package contract/integration tests are evidence inputs for this coordination policy, but they are owned by their respective package epics, not by this epic.

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] No forward references in Preconditions
- [x] Middleware taxonomy is single-choice (system/app/route)
- [x] platform/http defaults do not reference optional package class names
- [x] Noop-safe invariant is explicitly locked by this epic; concrete contract evidence is owned by the relevant package epics.
- [x] Observability policy matches `docs/ssot/observability.md`
- [x] Cross-cutting policy is anchored by referenced owner-package contract/integration evidence (noop never throws; keys stable; propagation deterministic).
- [x] Middleware catalog snapshot exists and is stable as reference for later epics
- [x] Does NOT implement business features (routing/http-app/session/auth/…); only baseline cross-cutting invariants + SSoT catalog snapshot.
- [x] Does NOT introduce new metric label keys outside the allowlist (`method,status,driver,operation,table,outcome`) defined in `docs/ssot/observability.md`.
- [x] Does NOT log/export secrets or PII (only `hash(value)` / `len(value)` / safe ids).
- [x] Expected runtime acceptance outcome is documented: when the Micro preset handles a request, correlation/tracing/metrics/log envelope is present and noop implementations never throw; concrete proof is owned by future package epics.
- [x] Rate limiting ambiguity is removed:
  - [x] early anonymous/IP throttling uses `EarlyRateLimitMiddleware`
  - [x] identity-aware throttling uses `RateLimitMiddleware`
  - [x] no single middleware class is listed in both `system_pre` and `app_pre`
  - [x] config toggles for early vs identity-aware rate limiting are distinct
- [x] This epic is coordination/policy-only for cross-cutting invariants and catalog snapshot.
- [x] Concrete package files for `platform/http`, `platform/logging`, `platform/tracing`, and `platform/metrics` MUST be created/modified only by their owning package epics.

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
  endUoW()
```

- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [x] `framework/packages/core/foundation/src/Module/FoundationModule.php` — runtime module
- [x] `framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php` — DI wiring entrypoint
- [x] `framework/packages/core/foundation/src/Provider/FoundationServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [x] `framework/packages/core/foundation/README.md` — package docs (Observability / Errors / Security-Redaction)

Container:
- [x] `framework/packages/core/foundation/src/Container/Container.php` — PSR-11 container runtime
- [x] `framework/packages/core/foundation/src/Container/ContainerBuilder.php` — deterministic container build from providers
  - [x] MUST preserve the caller-supplied provider order exactly (single-choice):
    - [x] `ContainerBuilder` MUST NOT re-sort providers
    - [x] upstream owner (later kernel/module-plan boot) MUST supply a deterministic provider list
    - [x] rationale:
      - [x] keeps DI override semantics aligned with deterministic module/provider order
      - [x] keeps TagRegistry dedupe (“first wins”) deterministic without imposing an arbitrary global FQCN sort
  - [x] Container definition collision policy (single-choice; cemented):
    - [x] for the same service id / interface binding, the later provider definition overrides the earlier one deterministically
    - [x] this rule applies to container bindings/definitions only
    - [x] tag dedupe remains independent and unchanged:
      - [x] `TagRegistry` keeps first occurrence per `(tag, serviceId)`
  - [x] Rationale:
    - [x] makes TagRegistry dedupe (“first wins”) deterministic across OS/runs
- [x] `framework/packages/core/foundation/src/Container/ServiceProviderInterface.php` — provider contract
- [x] `framework/packages/core/foundation/src/Container/Exception/ContainerException.php` — implements PSR-11 ContainerExceptionInterface
- [x] `framework/packages/core/foundation/src/Container/Exception/NotFoundException.php` — implements PSR-11 NotFoundExceptionInterface
- [x] `framework/packages/core/foundation/src/Container/ContainerDiagnostics.php` — deterministic diagnostics snapshot (services + tags)
  - [x] ContainerDiagnostics / runtime artifacts often require **byte-stable JSON**.
  - [x] MUST be safe by construction:
    - [x] MUST NOT dump service instances, constructor args, or reflection data
    - [x] MUST NOT include tag meta values (meta can contain arbitrary user data)
      - [x] allowed: tag name, service id, priority
      - [x] forbidden: serializing `meta` values
  - [x] MUST provide deterministic export:
    - [x] `toArray(): array` — normalized structure (maps already sorted; lists stable)
    - [x] `toJson(): string` — uses `Coretsia\Foundation\Serialization\StableJsonEncoder`
      - [x] because it uses `StableJsonEncoder`, the serialized JSON MUST end with a final newline
  - [x] Determinism:
    - [x] ordering MUST be locale-independent (`strcmp`)
    - [x] output MUST be rerun-no-diff across OS

Serialization:
- [x] `framework/packages/core/foundation/src/Serialization/StableJsonEncoder.php` — deterministic JSON encoder (runtime-safe)
  - [x] Purpose:
    - [x] produce stable JSON bytes for diagnostics/artifacts
    - [x] prevent “accidental json_encode drift” (key order, whitespace, newline, floats)
  - [x] Inputs (single-choice; cemented):
    - [x] accepts only JSON-safe deterministic types:
      - [x] `null|bool|int|string|list|array<string, value>`
    - [x] MUST reject:
      - [x] `float` (incl. `NaN`, `INF`, `-INF`)
      - [x] `resource`, `object`, `Closure`
      - [x] non-string map keys (incl. int keys outside list semantics)
    - [x] Note:
      - [x] callable-ness is NOT treated as a standalone runtime type check here
      - [x] plain strings remain plain strings even if PHP could call them as function names
  - [x] Output (single-choice; cemented):
    - [x] maps: keys sorted by `strcmp` at **every** nesting level
    - [x] lists: preserve order, no implicit reorder
    - [x] LF-only
    - [x] MUST end with a final newline
    - [x] MUST NOT leak secrets (encoder itself must not inspect env; redaction is caller responsibility)

Tags + deterministic order:
- [x] `framework/packages/core/foundation/src/Tag/TagRegistry.php` — add/list tagged services (deterministic)
- [x] `framework/packages/core/foundation/src/Tag/TaggedService.php` — VO `{id, priority, meta}`
- [x] `framework/packages/core/foundation/src/Discovery/DeterministicOrder.php` — canonical sort rule (priority DESC, id ASC)

### TagRegistry API (cemented)

- [x] `Coretsia\Foundation\Tag\TagRegistry` is the single source of truth for tagged service discovery.

#### Data model

- [x] Tag name: `string` (e.g. `kernel.reset`, `http.middleware.app`, `cli.command`)
- [x] Service id: `string` (PSR-11 container service id)
- [x] Tagged item: `Coretsia\Foundation\Tag\TaggedService`:
  - [x] `id: string` (service id)
  - [x] `priority: int`
  - [x] `meta: array<string, mixed>` (optional)

#### Methods (single-choice; cemented)

- [x] `add(string $tag, string $serviceId, int $priority = 0, array $meta = []): void`
- [x] `all(string $tag): list<TaggedService>` (semantic contract; NOT ids)
  - [x] ordering MUST be deterministic via `DeterministicOrder`:
    - [x] `priority DESC, id ASC` (`strcmp`, locale-independent)

#### Dedupe policy (single-choice; cemented)

- [x] If the same `serviceId` is added multiple times for the same `tag`,
  TagRegistry MUST keep the **first occurrence** (“first wins”) deterministically.
- [x] Rationale: prevents accidental double-registration while keeping stable results across OS/runs.

Reset orchestration:
- [x] `framework/packages/core/foundation/src/Runtime/Reset/ResetOrchestrator.php`
  - [x] Uses:
    - [x] `Psr\Container\ContainerInterface` — resolve services by id
    - [x] `Coretsia\Foundation\Tag\TagRegistry` — source of truth for the effective reset discovery list
    - [x] `Coretsia\Contracts\Runtime\ResetInterface` — invoked contract
  - [x] Must:
    - [x] read the effective reset discovery tag from Foundation wiring/config (`foundation.reset.tag`, default `kernel.reset`)
    - [x] obtain the reset discovery list ONLY via `TagRegistry->all($effectiveResetTag)`
    - [x] call `reset()` exactly once per service per invocation
    - [x] be safely callable when the discovery list is empty
    - [x] **MUST NOT re-sort** `TagRegistry->all(...)` output in legacy/base mode
    - [x] never rely on reflection/autowire during reset execution
    - [x] never emit stdout/stderr
    - [x] never leak secrets (no dumping service instances / constructor args)
    - [x] deterministically hard-fail on reset-tag misuse.
    - [x] Before `1.250.0`, tests MUST lock behavior only (hard-fail, deterministic, safe).
    - [x] `1.200.0` prepares the future `1.250.0` canonical reset failure by using the stable machine-readable failure message:
      - [x] `reset-not-resettable`
    - [x] Typed reset failure is intentionally deferred to `1.250.0`:
      - [x] `ResetException`
      - [x] `CORETSIA_RESET_SERVICE_NOT_RESETTABLE`
      - [x] `ResetException(code=CORETSIA_RESET_SERVICE_NOT_RESETTABLE, message="reset-not-resettable")`
    - [x] `1.200.0` tests MUST lock deterministic hard-fail behavior only and MUST NOT require the future typed exception class/code.

- [x] `framework/packages/core/foundation/src/Tag/ReservedTags.php` — constants:
  - [x] `public const KERNEL_RESET = 'kernel.reset';` - reserved canonical default reset tag name
  - [x] `public const KERNEL_STATEFUL = 'kernel.stateful';` - fixed reserved enforcement marker

Configuration:
- [x] `framework/packages/core/foundation/config/foundation.php` — config subtree under `foundation`
- [x] `framework/packages/core/foundation/config/rules.php` — enforces shape

Documentation:
- [x] `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- [x] `docs/ssot/di-tags-and-middleware-ordering.md` — MUST explain:
  - [x] This document MUST NOT redefine tag ownership or registry rows from `docs/ssot/tags.md`.
  - [x] Canonical HTTP middleware catalog ownership and slot contents live in `docs/ssot/http-middleware-catalog.md`.
  - [x] This document owns only:
    - [x] discovery consumption rules,
    - [x] deterministic ordering rule,
    - [x] dedupe rule,
    - [x] consumer obligations (`MUST NOT re-sort`, `MUST NOT re-dedupe`).
  - [x] canonical middleware slots/tags (cemented):
    - [x] `http.middleware.system_pre`
    - [x] `http.middleware.system`
    - [x] `http.middleware.system_post`
    - [x] `http.middleware.app_pre`
    - [x] `http.middleware.app`
    - [x] `http.middleware.app_post`
    - [x] `http.middleware.route_pre`
    - [x] `http.middleware.route`
    - [x] `http.middleware.route_post`
  - [x] canonical sort rule (single-choice): `priority DESC, id ASC`
    - [x] implemented by `Coretsia\Foundation\Discovery\DeterministicOrder`
    - [x] ordering MUST be locale-independent (`strcmp`, byte-order)
  - [x] dedupe policy (single-choice; cemented): “first wins”
    - [x] implemented by `Coretsia\Foundation\Tag\TagRegistry`
    - [x] consumers MUST treat `TagRegistry->all($tag)` output as canonical:
      - [x] MUST NOT re-sort
      - [x] MUST NOT apply a different dedupe rule
  - [x] priority bands guidance + reference to the canonical HTTP catalog SSoT:
    - [x] `docs/ssot/http-middleware-catalog.md`
  - [x] `framework/tools/spikes/fixtures/http_middleware_catalog.php` MAY be cited only as a Phase 0 lock-source/alignment input, NOT as SSoT

Tests:
- [x] `framework/packages/core/foundation/tests/Unit/ContainerDoesNotAutowireInterfacesTest.php`
- [x] `framework/packages/core/foundation/tests/Unit/DeterministicOrderSortRuleTest.php`
- [x] `framework/packages/core/foundation/tests/Contract/DeterministicOrderSortContractTest.php`
- [x] `framework/packages/core/foundation/tests/Contract/FoundationConfigSubtreeShapeContractTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/ContainerDefinitionsAreSharedByDefaultTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/TagRegistryReturnsDeterministicOrderTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/TagRegistryDedupeFirstWinsTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/ResetOrchestratorInvokesResetExactlyOncePerServiceTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/ResetOrchestratorRejectsTaggedNonResettableServiceTest.php`
  - [x] for `1.200.0`: locks deterministic hard-fail behavior and stable message `reset-not-resettable` only
  - [x] MUST NOT require `ResetException` or `CORETSIA_RESET_SERVICE_NOT_RESETTABLE` before `1.250.0`
  - [x] future typed-exception upgrade is deferred to `1.250.0` and tracked there as a `Modifies` item
- [x] `framework/packages/core/foundation/tests/Integration/ResetOrchestratorUsesConfiguredResetTagTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/ContainerBuilderProviderOrderIsDeterministicTest.php`
  - [x] asserts `ContainerBuilder` preserves the caller-supplied deterministic provider order exactly
  - [x] MUST NOT assert global re-sorting by provider FQCN
- [x] `framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsJsonIsDeterministicContractTest.php`
  - [x] asserts stable bytes for the same input container snapshot (sorted map keys at all levels, LF-only, final newline)
- [x] `framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsDoesNotLeakSecretsContractTest.php`
  - [x] asserts diagnostics never includes env values/tokens/Authorization/Cookie-like keys and never dumps constructor args/instances
- [x] `framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsDoesNotContainAbsolutePathsContractTest.php`
  - [x] asserts no `/home/`, `/Users/`, `(?i)\b[A-Z]:(\\|/)`, `\\server\share` patterns appear in serialized diagnostics
- [x] `framework/packages/core/foundation/tests/Contract/StableJsonEncoderRejectsFloatValuesContractTest.php`
  - [x] asserts `float/NaN/INF/-INF` are rejected deterministically and messages do not contain raw values
- [x] `framework/packages/core/foundation/tests/Contract/StableJsonEncoderRejectsNonJsonLikeValuesContractTest.php`
  - [x] asserts `object/resource/Closure/non-string` map keys are rejected deterministically
- [x] `framework/packages/core/foundation/tests/Contract/StableJsonEncoderSortsMapKeysRecursivelyContractTest.php`
  - [x] asserts maps are sorted recursively by `strcmp` and lists preserve order
- [x] `framework/packages/core/foundation/tests/Unit/ContainerCanAutowireIsStrictOnMissingConfigTest.php`
  - [x] asserts:
    - [x] missing `config['foundation']` OR missing `config['foundation']['container']`
      → throws `ContainerException` deterministically
- [x] `framework/packages/core/foundation/tests/Integration/ContainerBuilderLaterBindingOverridesEarlierBindingTest.php`
  - [x] asserts later provider binding replaces earlier one deterministically

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/di-tags-and-middleware-ordering.md`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`

#### Package skeleton (if type=package)

- [x] `framework/packages/core/foundation/composer.json` - має відповідати `coretsia/core-foundation`
  - [x] MUST require runtime package:
    - [x] `psr/container`
- [x] `framework/packages/core/foundation/src/Module/FoundationModule.php`
- [x] `framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php`
- [x] `framework/packages/core/foundation/config/foundation.php`
- [x] `framework/packages/core/foundation/config/rules.php`
- [x] `framework/packages/core/foundation/README.md` — package docs (Observability / Errors / Security-Redaction / Determinism)
  - [x] Observability section MUST describe only the bindings actually introduced by this epic
  - [x] default noop observability/logger bindings are introduced later by `1.205.0` and MUST be documented there once implemented

#### Configuration (keys + defaults)

- [x] Files:
  - [x] `framework/packages/core/foundation/config/foundation.php`
- [x] Keys (dot):
  - [x] `foundation.container.autowire_concrete` = true
  - [x] `foundation.container.allow_reflection_for_concrete` = true
  - [x] `foundation.reset.tag` = "kernel.reset"
    - [x] runtime-effective reset discovery tag
    - [x] reserved default value is `kernel.reset`
    - [x] consumers outside Foundation MUST NOT read or hardcode this key/string
- [x] Rules:
  - [x] `framework/packages/core/foundation/config/rules.php` enforces shape

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

- [x] Foundation constants for already-canonical tags:
  - [x] `framework/packages/core/foundation/src/Tag/ReservedTags.php`
  - [x] constants:
    - [x] `KERNEL_RESET`
    - [x] `KERNEL_STATEFUL`
- [x] `FoundationServiceProvider` реєструє:
  - [x] `TagRegistry::class` як instance з `$builder->tagRegistry()`
  - [x] `ResetOrchestrator::class` через `factory`
  - [x] does not register `DeterministicOrder::class`; it remains a non-instantiable static canonical ordering primitive
  - [x] `FoundationServiceProvider` implements `ServiceProviderInterface`;
  - [x] `FoundationServiceFactory` створює `ResetOrchestrator`;
  - [x] `DeterministicOrder::class` is intentionally not registered as a container service:
    - [x] it is a stateless static canonical ordering primitive
    - [x] it has no runtime dependencies, lifecycle, config, reset behavior, or mutable state
    - [x] registering it would incorrectly expose the canonical sort rule as a replaceable DI service/strategy
    - [x] behavior is locked by direct unit/contract tests and by `TagRegistry->all($tag)` integration tests

- Foundation-owned providers that register stateful services MUST tag them with the effective reset tag resolved from `foundation.reset.tag`, not with a hardcoded string.
- `kernel.stateful` is a marker tag name only:
  - it is not a container service
  - it is not consumed by `ResetOrchestrator`
  - it is not consumed by `core/kernel` at runtime
  - it exists for CI/static-analysis enforcement rails

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads/writes:
  - N/A
- [x] Reset discipline:
  - [x] `core/foundation` provides the runtime reset executor through `ResetOrchestrator`
  - [x] `ResetOrchestrator` requires discovered reset services to implement `Coretsia\Contracts\Runtime\ResetInterface`
  - [x] services are discovered through the effective Foundation reset discovery tag (`foundation.reset.tag`, default `kernel.reset`)
  - [x] fixed enforcement marker `kernel.stateful` is reserved through `Coretsia\Foundation\Tag\ReservedTags::KERNEL_STATEFUL`
  - [x] runtime execution does not depend on `kernel.stateful`
  - [x] architecture/static-analysis enforcement for “stateful services MUST implement ResetInterface and be reset-tagged” is provided by the external `cross_cutting_contract_gate.php` rail, not by a PHPStan custom rule in this epic

#### Observability (policy-compliant)

- Spans/Metrics:
  - N/A — `1.200.0` introduces no spans, metrics, or observability port bindings.
  - Foundation reset observability is deferred to `1.250.0` after noop-safe observability/logger bindings exist.
- Logs:
  - N/A — `1.200.0` introduces no logger binding and emits no runtime logs.
- Diagnostics:
  - [x] `ContainerDiagnostics` output MUST NOT include secrets/PII, service instances, constructor args, reflection data, raw config payloads, env values, Authorization/Cookie-like values, or tag meta.

#### Errors

- [x] Exceptions introduced:
  - [x] `Coretsia\Foundation\Container\Exception\ContainerException` — errorCode `CORETSIA_CONTAINER_ERROR`
    - [x] `Container::canAutowire` strict: якщо `config['foundation']` або `config['foundation']['container']` відсутні → `ContainerException`
  - [x] `Coretsia\Foundation\Container\Exception\NotFoundException` — errorCode `CORETSIA_CONTAINER_NOT_FOUND`
- Mapping:
  - N/A (higher layers adapt/mapping if needed)

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] env values, secrets, tokens (especially in diagnostics)
- [x] Allowed:
  - [x] `hash(value)` / `len(value)` for potentially sensitive strings in diagnostics (if ever output)
- [x] `core/foundation` MUST provide a single canonical encoder to avoid drift across packages.

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Referenced enforcement rails (NOT owned by this epic)

- `framework/tools/gates/cross_cutting_contract_gate.php` — referenced external architecture rail for `kernel.stateful` reset discipline; ownership remains in tooling/gates epics.
- Dedicated PHPStan rule is intentionally not introduced by `1.200.0`; detectable `kernel.stateful` reset-discipline violations are currently enforced by `cross_cutting_contract_gate.php`, not PHPStan.

#### Required policy tests matrix

- [x] if effective reset discovery is used → `framework/packages/core/foundation/tests/Integration/ResetOrchestratorInvokesResetExactlyOncePerServiceTest.php`
- [x] If determinism promised → `framework/packages/core/foundation/tests/Contract/DeterministicOrderSortContractTest.php`
- [x] if effective reset discovery is used → `framework/packages/core/foundation/tests/Integration/ResetOrchestratorRejectsTaggedNonResettableServiceTest.php` (tag misuse is deterministic hard-fail)
- [x] if reset discovery tag is config-resolved → `framework/packages/core/foundation/tests/Integration/ResetOrchestratorUsesConfiguredResetTagTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [x] `framework/packages/core/foundation/tests/Unit/ContainerDoesNotAutowireInterfacesTest.php`
  - [x] `framework/packages/core/foundation/tests/Unit/DeterministicOrderSortRuleTest.php`
- Contract:
  - [x] `framework/packages/core/foundation/tests/Contract/DeterministicOrderSortContractTest.php`
  - [x] `framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsJsonIsDeterministicContractTest.php`
  - [x] `framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsDoesNotLeakSecretsContractTest.php`
  - [x] `framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsDoesNotContainAbsolutePathsContractTest.php`
- Integration:
  - [x] `framework/packages/core/foundation/tests/Integration/TagRegistryReturnsDeterministicOrderTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/ResetOrchestratorInvokesResetExactlyOncePerServiceTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/TagRegistryDedupeFirstWinsTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/ResetOrchestratorUsesConfiguredResetTagTest.php`
    - [x] asserts `ResetOrchestrator` discovers resettable services through `foundation.reset.tag`, not a hardcoded `kernel.reset`
- Gates/Arch:
  - [x] `framework/tools/gates/cross_cutting_contract_gate.php` passes under `composer gates` as the referenced external architecture rail for `kernel.stateful` reset discipline; ownership remains outside this epic.
  - [x] Dedicated PHPStan rule is not introduced by `1.200.0`; the invariant is currently enforced by the cross-cutting gate, not PHPStan.
- [x] `framework/packages/core/foundation/tests/Contract/FoundationConfigSubtreeShapeContractTest.php`
  - [x] MUST fail if `framework/packages/core/foundation/config/foundation.php` returns repeated root:
    - [x] ✅ returns subtree keys such as: `['container' => [...], 'reset' => [...], ...]`
    - [x] ❌ forbidden: `['foundation' => [...]]`
  - [x] MUST NOT require an exact final namespace set for the `foundation` subtree.
  - [x] Additive namespaces introduced by later `core/foundation` epics are allowed.
  - [x] The contract MUST only assert:
    - [x] subtree-only return shape (no repeated root)
    - [x] no reserved `@*` keys
    - [x] absence of namespaces explicitly forbidden by the current epic
- [x] If strict autowire config is promised → `framework/packages/core/foundation/tests/Unit/ContainerCanAutowireIsStrictOnMissingConfigTest.php`

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] Preconditions satisfied (no forward references)
- [x] deps/forbidden respected (deptrac; no cycles)
- [x] Verification tests present where applicable
- [x] Determinism: rerun-no-diff (ordering/registry behavior)
- [x] Docs updated:
  - [x] `framework/packages/core/foundation/README.md`
  - [x] `docs/ssot/di-tags-and-middleware-ordering.md`
  - [x] `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- [x] Typical consumers (when enabled in presets/bundles):
  - [x] `platform/http` consumption is documented/reserved only; implementation is owned by later HTTP epics and must compose middleware stacks from DI tags `http.middleware.*` via `TagRegistry->all(<slotTag>)`
  - [x] `core/kernel` consumption is documented/reserved only; implementation is owned by later Kernel epics and must trigger reset through `ResetOrchestrator::resetAll()`
- [x] Discovery / wiring happens via tags (reserved/documented examples; tag owners are the respective packages):
  - [x] `kernel.reset` — reserved canonical default reset-discovery tag name for `foundation.reset.tag` (owner: `core/foundation`)
  - [x] `kernel.reset` is NOT a kernel-owned runtime feature; Foundation resolves the effective reset discovery tag through `foundation.reset.tag`
  - [x] `kernel.stateful` — enforcement marker reserved through `Coretsia\Foundation\Tag\ReservedTags::KERNEL_STATEFUL`; runtime reset execution does not depend on it
  - [x] HTTP middleware slots are cemented/reserved taxonomy; implementation owner is future `platform/http`:
    - [x] `http.middleware.system_pre`
    - [x] `http.middleware.system`
    - [x] `http.middleware.system_post`
    - [x] `http.middleware.app_pre`
    - [x] `http.middleware.app`
    - [x] `http.middleware.app_post`
    - [x] `http.middleware.route_pre`
    - [x] `http.middleware.route`
    - [x] `http.middleware.route_post`
  - [x] `cli.command`, `error.mapper`, `health.check` are typical cross-package discovery tags; owner packages are `platform/cli`, `platform/errors`, `platform/health`, etc.; `core/foundation` provides only the shared registry/order mechanism
- [x] **Locale independence (single-choice):**
  - [x] Будь-яке сортування в `core/foundation` MUST бути **locale-independent**.
  - [x] Sorting MUST використовувати **byte-order** порівняння (`strcmp`) і MUST NOT покладатися на `setlocale(...)`, `LC_ALL`, ICU-collation тощо.
- [x] **Single canonical ordering rule (cemented):**
  - [x] `DeterministicOrder` MUST реалізовувати єдине правило: **priority DESC, id ASC**.
  - [x] `TagRegistry->all($tag)` MUST повертати список **у цьому порядку** завжди (rerun-stable).
- [x] **Diagnostics serialization stability (MUST):**
  - [x] Якщо `ContainerDiagnostics` серіалізує структури у JSON/рядок — байти MUST бути стабільні:
    - [x] maps: ключі відсортовані на кожному рівні за `strcmp`
    - [x] lists: порядок збережено, без неявних reorder
  - [x] Diagnostics MUST NOT включати secrets/PII/Authorization/Cookie/env values.
- [x] **Reserved namespace parity (from config-merge spikes) (MUST):**
  - [x] Будь-який ключ, що починається з `@`, є **reserved**.
  - [x] `foundation` config subtree MUST NOT містити `@*` ключів на будь-якій глибині.
  - [x] `framework/packages/core/foundation/config/rules.php` MUST enforce: `@*` → hard fail.
- [x] `core/foundation` (runtime) MUST NOT залежати від Phase 0 devtools/tooling пакетів:
  - [x] Forbidden deps: `devtools/*` (включно `devtools/internal-toolkit`, `devtools/cli-spikes`)
  - [x] Rationale: Phase 0 tooling libs і gates — tools-only; runtime не має тягнути їх як compile-time deps.
- [x] **Deterministic ordering authority (cemented):**
  - [x] `TagRegistry->all($tag)` is canonical for tag discovery lists:
    - [x] ordering is `priority DESC, id ASC` via `DeterministicOrder`
    - [x] consumers MUST NOT re-sort or apply different dedupe rules
  - [x] Reset execution ordering is owned by the Foundation reset executor:
    - [x] legacy/base mode executes in EXACT `TagRegistry->all($effectiveResetTag)` order, where `$effectiveResetTag` is resolved from `foundation.reset.tag` (reserved default `kernel.reset`)
    - [x] enhanced reset ordering is deferred to `1.250.0` and tracked there as a `Modifies` item
- [x] `StableJsonEncoder` semantics are locked directly by contract tests, not only indirectly through ContainerDiagnostics

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
- [x] `framework/packages/core/foundation/src/Logging/NoopLogger.php`
  - [x] implements `Psr\Log\LoggerInterface`

Tracing:
- [x] `framework/packages/core/foundation/src/Observability/Tracing/NoopTracer.php`
  - [x] implements `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
- [x] `framework/packages/core/foundation/src/Observability/Tracing/NoopSpan.php`
  - [x] implements `Coretsia\Contracts\Observability\Tracing\SpanInterface`
- [x] `framework/packages/core/foundation/src/Observability/Tracing/NoopContextPropagation.php`
  - [x] implements `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`

Metrics:
- [x] `framework/packages/core/foundation/src/Observability/Metrics/NoopMeter.php`
  - [x] implements `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`

Errors:
- [x] `framework/packages/core/foundation/src/Observability/Errors/NoopErrorReporter.php`
  - [x] implements `Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface`

Profiling:
- [x] `framework/packages/core/foundation/src/Observability/Profiling/NoopProfiler.php`
  - [x] implements `Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface`
- [x] `framework/packages/core/foundation/src/Observability/Profiling/NoopProfilingSession.php`
  - [x] implements `Coretsia\Contracts\Observability\Profiling\ProfilingSessionInterface`

#### Modifies

- [x] `framework/packages/core/foundation/composer.json`
  - [x] add runtime requirement:
    - [x] `psr/log`
- [x] `framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php`
  - [x] binds:
    - [x] `Psr\Log\LoggerInterface` → `Coretsia\Foundation\Logging\NoopLogger`
    - [x] `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` → `...NoopTracer`
    - [x] `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` → `...NoopMeter`
    - [x] `Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface` → `...NoopErrorReporter`
    - [x] `Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface` → `...NoopProfiler`
    - [x] `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface` → `...NoopContextPropagation`
      - [x] invariant: noop implementation MUST NOT throw; MUST NOT emit stdout/stderr; MUST NOT log raw headers.

- [x] `framework/packages/core/foundation/README.md`
  - [x] MUST mention: "Foundation provides noop bindings; platform packages override them."

### Tests (MUST)

Contract:
- [x] `framework/packages/core/foundation/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php`
  - [x] MUST cover: NoopLogger/NoopTracer/NoopMeter/NoopErrorReporter/NoopProfiler do not throw
  - [x] MUST also cover: `NoopContextPropagation` does not throw
  - [x] MUST also cover: `NoopTracer` returns a noop-safe span (`NoopSpan`) that does not throw on its no-op operations
  - [x] MUST include one case where `NoopLogger` receives arbitrary PSR-3 context and ignores it safely
  - [x] MUST assert: no stdout/stderr sinks in these implementations (token-scan or behavioral)
Integration:
- [x] `framework/packages/core/foundation/tests/Integration/FoundationResolvesNoopObservabilityBindingsTest.php`
  - [x] asserts container resolves:
    - [x] `Psr\Log\LoggerInterface`
    - [x] `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
    - [x] `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
    - [x] `Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface`
    - [x] `Coretsia\Contracts\Observability\Profiling\ProfilerPortInterface`
    - [x] `Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface`
  - [x] without any `platform/*` packages installed

### DoD (MUST)

- [x] Container can resolve all listed ports without platform/* packages installed
- [x] Noop implementations never throw
- [x] No payload/secrets/PII are stored or emitted
- [x] Deptrac green: no forbidden deps
- [x] Any runtime that references `ContextPropagationInterface` (e.g. platform/http TraceContextMiddleware) MUST have a resolvable default binding in the baseline (Foundation), otherwise “feature toggles default true” becomes unsafe.

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
    - the key is declared in `Coretsia\Contracts\Context\ContextKeys`
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
  - `Coretsia\Contracts\Context\ContextKeys`

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
- [x] `framework/packages/core/contracts/src/Context/ContextKeys.php`
  - [x] canonical keys (Phase 0 list + reserved future list)
- [x] `framework/packages/core/foundation/src/Context/ContextBag.php`
  - [x] immutable snapshot view
- [x] `framework/packages/core/foundation/src/Context/ContextStore.php`
  - [x] mutable store
  - [x] implements `Coretsia\Contracts\Context\ContextAccessorInterface`
  - [x] implements `Coretsia\Contracts\Runtime\ResetInterface`
  - [x] MUST implement `ContextAccessorInterface::get(string $key): mixed` exactly
  - [x] MUST NOT add a default parameter to `get(...)`
- [x] `framework/packages/core/foundation/src/Context/ContextStorePolicy.php`
  - [x] safe-write allowlist + guards (no secrets/PII)

### ContextStore value model (single-choice; cemented)

- [x] `ContextStore` MUST accept only JSON-safe, deterministic value types:
  - [x] Scalars:
    - [x] `null`, `bool`, `int`, `string`
  - [x] Arrays:
    - [x] lists: `list<value>`
    - [x] maps: `array<string, value>` (string keys only)
- [x] Forbidden everywhere (including nested):
  - [x] `float` (incl. `NaN`, `INF`, `-INF`)
  - [x] `resource`, `object`, `Closure`
  - [x] non-string map keys
- [x] Note:
  - [x] callable-ness is NOT treated as a standalone ContextStore type rule
  - [x] plain strings remain valid strings even if PHP could interpret some of them as callable names
- [x] Deterministic failure semantics (single-choice):
  - [x] On forbidden value/type, throw deterministic exception (or deterministic equivalent).
  - [x] Exception message MUST be safe:
    - [x] MUST NOT include the raw value
    - [x] MAY include only a stable path-to-value (e.g. `a.b[3].c`)
- [x] **Context key allowlist (single-choice; cemented):**
  - [x] `ContextStorePolicy` MUST allow writes ONLY for keys declared in `Coretsia\Contracts\Context\ContextKeys`.
  - [x] Any attempt to write a key not present in `ContextKeys` MUST fail deterministically.
  - [x] Rationale: prevents uncontrolled key sprawl; `ContextKeys` remains the only SSoT.

Correlation id:
- [x] `framework/packages/core/foundation/src/Id/CorrelationIdGenerator.php` — stable-format correlation id generation (format-deterministic; value is entropy-based)
  - [x] MUST receive `Coretsia\Foundation\Id\UlidGenerator` via constructor injection.
  - [x] MUST delegate generation to `UlidGenerator` and MUST NOT implement ULID logic independently.
  - [x] MUST NOT post-process the generated value in a way that can create format drift.
  - [x] `correlation_id` MUST be an opaque safe id with a deterministic string format.
    - [x] Canonical format: **ULID** (Crockford Base32), 26 chars:
      - [x] matches: `/\A[0-9A-HJKMNP-TV-Z]{26}\z/`
    - [x] Output normalization:
      - [x] MUST be uppercase (as above) to avoid case-drift across implementations
  - [x] **Single-source ULID rule (cemented):**
    - [x] `CorrelationIdGenerator` MUST NOT implement ULID independently.
    - [x] It MUST delegate ULID generation to `Coretsia\Foundation\Id\UlidGenerator` to prevent format drift.

- [x] `framework/packages/core/foundation/src/Id/UlidGenerator.php` — canonical ULID generator (single source)
  - [x] **Single-source ULID rule (cemented):**
    - [x] this generator is the only ULID implementation in the codebase
    - [x] `CorrelationIdGenerator` MUST delegate to it

- [x] `framework/packages/core/foundation/src/Observability/CorrelationIdProvider.php` — implements `CorrelationIdProviderInterface`

Wiring evidence (in provider):
- [x] `framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php` — binds store/accessor/provider
  and tags `ContextStore` with the effective Foundation reset discovery tag resolved from
  `foundation.reset.tag` (reserved default `kernel.reset`)

Documentation:
- [x] `docs/adr/ADR-0015-context-bag-context-store-correlation-id.md`
- [x] `docs/ssot/context-store.md` — single store, immutable bag, safe writes, reset між UoW
- [x] `docs/ssot/context-keys.md` — canonical key registry only:
  - [x] defines canonical key names, meanings, safe-value notes, and owner/lifecycle notes
  - [x] MAY name high-level writer categories only:
    - [x] kernel
    - [x] http
    - [x] routing
    - [x] auth
    - [x] tenancy
  - [x] MUST NOT contain the per-middleware `FQCN -> ContextKeys written/read` matrix
  - [x] the detailed middleware-to-keys reference map is owned only by `1.230.0` in:
    - [x] `docs/ssot/middleware-context-keys-map.md`

Tests:
- [x] `framework/packages/core/foundation/tests/Unit/CorrelationIdGeneratorDelegatesToUlidGeneratorTest.php`
- [x] `framework/packages/core/foundation/tests/Unit/ContextBagImmutabilityTest.php`
- [x] `framework/packages/core/foundation/tests/Unit/CorrelationIdFormatTest.php`
- [x] `framework/packages/core/contracts/tests/Contract/ContextKeysAreStableContractTest.php`
- [x] `framework/packages/core/foundation/tests/Contract/CorrelationIdFormatContractTest.php`
- [x] `framework/packages/core/foundation/tests/Contract/ContextAccessorSignatureContractTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/ContextStoreResetClearsContextTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/ContextStoreSafeWriteGuardBlocksForbiddenKeysTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsAtPrefixedKeysTest.php`
  - [x] writing key `"@foo"` MUST fail deterministically
- [x] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsFloatValuesTest.php`
  - [x] rejects nested float
  - [x] rejects `NaN`, `INF`, `-INF`
  - [x] error message MUST NOT contain the raw value
  - [x] message MAY contain only path-to-value
- [x] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsUnknownKeysTest.php`
  - [x] writing key `"unknown_key"` MUST fail deterministically
  - [x] message MUST be safe (no values)
- [x] `framework/packages/core/foundation/tests/Integration/ContextStoreIsTaggedKernelStatefulTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/FoundationResolvesContextStoreBindingsTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/CorrelationIdProviderReadsContextStoreTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/ContextStoreIsTaggedWithEffectiveResetTagTest.php`
  - [x] asserts provider wiring tags `ContextStore` using `foundation.reset.tag`
- [x] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsObjectValuesTest.php`
  - [x] rejects any `object` anywhere (incl. nested)
  - [x] message MUST be safe (no raw value), MAY include only path-to-value

- [x] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsResourceValuesTest.php`
  - [x] rejects any `resource` anywhere
  - [x] message MUST be safe (no raw value), MAY include only path-to-value

- [x] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsNonStringMapKeysTest.php`
  - [x] rejects maps with any non-string key (including `int` keys) anywhere
  - [x] message MUST be safe (no raw value), MAY include only path-to-value

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/context-store.md`
  - [x] `docs/ssot/context-keys.md`
- [x] `framework/packages/core/foundation/README.md` — documents ContextStore usage + redaction rules (if referenced)
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0015-context-bag-context-store-correlation-id.md`

#### Configuration (keys + defaults)

N/A

- [x] Policy:
  - [x] Transport-specific correlation header extraction/injection policy is owned by `platform/http`, not by `core/foundation`.
  - [x] `core/foundation` owns only correlation id generation and provider binding.
  - [x] `ContextStore` is baseline runtime infrastructure and MUST NOT be feature-disabled via config
  - [x] correlation id provisioning is baseline runtime infrastructure and MUST NOT be feature-disabled via config
  - [x] absence of optional writers/readers is represented by “no writes/no reads”, NOT by disabling foundation context services
  - [x] `ContextStorePolicy` safe-write guard is baseline runtime safety and MUST always be enabled.
  - [x] It MUST NOT be feature-disabled via config.

#### Wiring / DI tags (when applicable)

- [x] ServiceProvider wiring evidence:
  - [x] registers: `Coretsia\Foundation\Context\ContextStore` (singleton)
  - [x] binds: `Coretsia\Contracts\Context\ContextAccessorInterface` → `ContextStore`
  - [x] registers: `Coretsia\Foundation\Observability\CorrelationIdProvider`
  - [x] binds: `Coretsia\Contracts\Observability\CorrelationIdProviderInterface` → `CorrelationIdProvider`
  - [x] adds tag: `<effective reset tag>` priority `<int>` meta `<optional>` for `ContextStore`
  - [x] adds tag: `kernel.stateful` priority `0` meta `{}` for `ContextStore`

- [x] Policy (cemented):
  - [x] `ContextStore` is stateful and therefore MUST be both:
    - [x] tagged `kernel.stateful`
    - [x] tagged with the effective Foundation reset discovery tag (`foundation.reset.tag`, reserved default `kernel.reset`)

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [x] Context reads:
  - [x] `ContextKeys::CORRELATION_ID`
  - [x] `ContextKeys::UOW_ID`
  - [x] `ContextKeys::UOW_TYPE`
  - [x] request keys when present: `CLIENT_IP|SCHEME|HOST|PATH|USER_AGENT`
  - [x] reserved future keys: `REQUEST_ID|PATH_TEMPLATE|HTTP_RESPONSE_FORMAT|ACTOR_ID|TENANT_ID`
- [x] Context writes (safe only):
  - [x] base keys: `correlation_id`, `uow_id`, `uow_type`
  - [x] request/app safe keys only (no headers/cookies/body)
- [x] Reset discipline:
  - [x] `ContextStore` implements `Coretsia\Contracts\Runtime\ResetInterface`
  - [x] `ContextStore` is discovered for reset only through the effective Foundation reset discovery tag (`foundation.reset.tag`, default `kernel.reset`)

#### Observability (policy-compliant)

- Spans/Metrics:
  - N/A (foundation minimal)
- [x] Logs:
  - [x] MUST NOT include Authorization/Cookie/session id/tokens/payload/raw SQL
  - [x] `correlation_id` is safe and may be logged (not as metric label)

#### Errors

- [x] Exceptions introduced:
  - [x] `Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException` — errorCode `CORETSIA_CONTEXT_WRITE_FORBIDDEN` (optional)
  - [x] `Coretsia\Foundation\Context\Exception\ContextInvalidKeyException` — errorCode `CORETSIA_CONTEXT_INVALID_KEY` (optional)
- Mapping note:
  - Foundation context exceptions (if enabled) are mapped/adapted in higher layers
    (e.g., `platform/errors`) — no duplicate mappers here.

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] session ids, tokens, cookies, Authorization headers, request bodies
- [x] Allowed:
  - [x] safe ids: `correlation_id`, `uow_id`, `actor_id` (policy: never email/phone)

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [x] If Context writes exist → `framework/packages/core/foundation/tests/Integration/ContextStoreSafeWriteGuardBlocksForbiddenKeysTest.php`
- [x] if effective reset discovery is used → `framework/packages/core/foundation/tests/Integration/ContextStoreResetClearsContextTest.php`
- [x] If key stability is promised → `framework/packages/core/contracts/tests/Contract/ContextKeysAreStableContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [x] `framework/packages/core/foundation/tests/Unit/ContextBagImmutabilityTest.php`
  - [x] `framework/packages/core/foundation/tests/Unit/CorrelationIdFormatTest.php`
- Contract:
  - [x] `framework/packages/core/contracts/tests/Contract/ContextKeysAreStableContractTest.php`
  - [x] `framework/packages/core/foundation/tests/Contract/CorrelationIdFormatContractTest.php`
  - [x] `framework/packages/core/foundation/tests/Contract/ContextAccessorSignatureContractTest.php`
- Integration:
  - [x] `framework/packages/core/foundation/tests/Integration/ContextStoreResetClearsContextTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/ContextStoreSafeWriteGuardBlocksForbiddenKeysTest.php`
- Gates/Arch:
  - [x] phpstan/gates enforce no stateful without reset (referenced; owned elsewhere)

### DoD (MUST)

- [x] Key list matches SSoT and is contract-tested
- [x] Reset clears context deterministically
- [x] No secrets/PII can be written by default (guard or discipline + docs)
- [x] Docs updated:
  - [x] `docs/ssot/context-store.md`
  - [x] `docs/ssot/context-keys.md`
  - [x] `docs/adr/ADR-0015-context-bag-context-store-correlation-id.md`
- [x] Kernel lifecycle handoff to `1.280.0` recorded:
  - [x] `1.280.0` KernelRuntime MUST implement begin-UoW base context writes: `correlation_id`, `uow_id`, `uow_type`.
  - [x] `1.280.0` KernelRuntime MUST execute reset orchestration after UoW via `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`.
  - [x] `1.210.0` owns only Foundation context infrastructure: `ContextStore`, `ContextBag`, `ContextKeys`, `ContextStorePolicy`, correlation id generation/provider wiring, reset tags, and resettable store behavior.
- [x] Typical readers:
  - [x] `platform/logging` and `platform/tracing` MAY read `Coretsia\Contracts\Context\ContextAccessorInterface`, but any export to `logs/spans/metrics` remains governed by `docs/ssot/observability.md`
  - [x] raw `path`, raw query, headers, cookies, Authorization, tokens, and payloads MUST NOT be exported even if present in `ContextStore`
  - [x] use `path_template` or `hash(value)` / `len(value)` when path-like observability data is needed
  - [x] `platform/tracing` MAY read correlation/uow keys for trace enrichment.
- [x] Typical writers (HTTP layer; owners are HTTP packages/middlewares):
  - [x] HTTP middlewares MAY write only safe keys (no headers/cookies/body/payload).
  - [x] The canonical key list + writers (high-level) are documented in SSoT:
    - [x] `docs/ssot/context-keys.md`
    - [x] canonical middleware catalog reference: `docs/ssot/http-middleware-catalog.md`
    - [x] `framework/tools/spikes/fixtures/http_middleware_catalog.php` MAY be used only as a Phase 0 lock/alignment input, NOT as the primary reference
- [x] Context keys MUST NOT start with `@`.
- [x] `ContextStorePolicy` MUST reject any write attempt to a key that starts with `@` deterministically.
- [x] Rationale: `@*` namespace reserved for config directives (Phase 0 config_merge semantics); runtime context keys must never collide.
- [x] `ContextStore` MUST reject `float` values anywhere (including nested structures if supported):
  - [x] reject any `float`
  - [x] reject `NaN`, `INF`, `-INF` explicitly
- [x] Failure MUST be deterministic and MUST NOT reveal raw values:
  - [x] throw `ContextWriteForbiddenException` (or deterministic equivalent)
  - [x] message MAY include лише шлях до значення (e.g. `a.b[3].c`)
  - [x] message MUST NOT include the value itself
- [x] **No unknown ContextKeys (cemented):**
  - [x] `ContextStore` MUST reject any key not declared in `ContextKeys` deterministically.

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
  - `foundation.*` — runtime reads `foundation.ids.*`.
  - This epic does not introduce `foundation.clock.*`; the runtime clock binding is fixed to `SystemClock`.

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
- [x] `framework/packages/core/foundation/src/Clock/SystemClock.php` — implements `Psr\Clock\ClockInterface`
- [x] `framework/packages/core/foundation/src/Clock/FrozenClock.php` — test clock (fixtures)

IDs:
- [x] `framework/packages/core/foundation/src/Id/UuidGenerator.php` — concrete generator
- [x] `framework/packages/core/foundation/src/Id/IdGeneratorInterface.php` — canonical Foundation abstraction for runtime id generation

Stopwatch:
- [x] `framework/packages/core/foundation/src/Time/Stopwatch.php` — float-free stopwatch
  - [x] `start(): int` returns a monotonic timestamp token in **nanoseconds** from `hrtime(true)`
  - [x] `stop(int $startedAt): int` returns `durationMs` as **int milliseconds**:
    - [x] `$startedAt` MUST be a positive Stopwatch token returned by `start()`
    - [x] `$startedAt` MUST be treated by callers as an opaque token returned by `start()`
    - [x] `stop()` MUST NOT retain token state or track issued tokens
    - [x] positive token provenance is a caller/API contract, not a runtime-tracked invariant
    - [x] non-positive tokens MUST fail deterministically with `StopwatchInvalidStateException`
    - [x] elapsed duration MUST be computed from `hrtime(true) - $startedAt`
    - [x] negative or zero elapsed duration MUST return `0`
    - [x] positive elapsed duration MUST be converted with `intdiv($durationNs, 1_000_000)`
  - [x] MUST NOT use `microtime(true)` (float)
  - [x] MUST be non-negative and deterministic-format (int ms)

Documentation:
- [x] `docs/adr/ADR-0016-clock-ids-stopwatch.md`
- [x] `docs/ssot/time-ids-and-duration.md` — durationMs=int, ULID default, usage guidance

Tests:
- [x] `framework/packages/core/foundation/tests/Unit/UlidFormatTest.php`
- [x] `framework/packages/core/foundation/tests/Unit/StopwatchDurationIsNonNegativeTest.php`
  - [x] `stop(start())` returns `int >= 0`
  - [x] `stop(PHP_INT_MAX)` returns `0`
  - [x] `stop(0)` throws `StopwatchInvalidStateException`
  - [x] `stop(-1)` throws `StopwatchInvalidStateException`
  - [x] exception message MUST NOT contain raw token values
- [x] `framework/packages/core/foundation/tests/Unit/FrozenClockReturnsDeterministicNowTest.php`
- [x] `framework/packages/core/foundation/tests/Contract/SystemClockReturnsUtcDateTimeImmutableContractTest.php`
- [x] `framework/packages/core/foundation/tests/Contract/UuidFormatContractTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/DefaultIdGeneratorResolvesFromConfigTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/FoundationClockAndStopwatchBindingsTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/FoundationIdsDefaultDoesNotAffectCorrelationIdTest.php`
- [x] `framework/packages/core/foundation/tests/Contract/FoundationConfigRejectsFloatValuesInIdsContractTest.php`
  - [x] asserts `framework/packages/core/foundation/config/rules.php` rejects:
    - [x] any float assigned to `foundation.ids.default`
    - [x] any unknown nested key under `foundation.ids.*`, including float-valued unknown keys
    - [x] any `foundation.clock.*` key because this epic does not introduce runtime clock config
    - [x] explicit `NaN`, `INF`, `-INF` where representable in fixtures
  - [x] failure MUST be deterministic and message MUST be safe (no dumping raw values)

#### Modifies

- [x] `framework/packages/core/foundation/composer.json`
  - [x] add runtime requirement:
    - [x] `psr/clock`
- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/time-ids-and-duration.md`
- [x] `framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php` — binds Clock/Stopwatch/Id generator via DI (wiring evidence)
- [x] `framework/packages/core/foundation/src/Provider/FoundationServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [x] `framework/packages/core/foundation/config/foundation.php`
- [x] `framework/packages/core/foundation/config/rules.php`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0016-clock-ids-stopwatch.md`

#### Configuration (keys + defaults)

- [x] Files:
  - [x] `framework/packages/core/foundation/config/foundation.php`
- [x] Keys (dot):
  - [x] `foundation.ids.default` = "ulid"
    - [x] allowed values: `ulid`, `uuid`
    - [x] selects only `Coretsia\Foundation\Id\IdGeneratorInterface`
    - [x] MUST NOT affect `CorrelationIdGenerator` or `CorrelationIdProvider`
- [x] Rules:
  - [x] `framework/packages/core/foundation/config/rules.php` MUST also enforce allowed values:
    - [x] `foundation.ids.default` ∈ {`ulid`, `uuid`}

- [x] Policy:
  - [x] Supported ID generators are a code-level capability, not runtime feature flags.
  - [x] Runtime selection is done only through `foundation.ids.default`.
  - [x] `foundation.ids.default` selects only `Coretsia\Foundation\Id\IdGeneratorInterface`.
  - [x] It MUST NOT affect `Coretsia\Foundation\Id\CorrelationIdGenerator` or
    `Coretsia\Foundation\Observability\CorrelationIdProvider`;
    `correlation_id` remains ULID-backed per `1.210.0`.
  - [x] `Stopwatch` is canonical Foundation runtime infrastructure and MUST be resolvable whenever `core/foundation` is enabled
  - [x] duration measurement absence in a consumer is represented by “consumer does not call Stopwatch”, NOT by disabling Stopwatch itself

#### Wiring / DI tags (when applicable)

- [x] ServiceProvider wiring evidence:
  - [x] binds: `Psr\Clock\ClockInterface` → `Coretsia\Foundation\Clock\SystemClock`
  - [x] registers: `Coretsia\Foundation\Time\Stopwatch`
  - [x] binds: `Coretsia\Foundation\Id\IdGeneratorInterface` → resolved default generator selected by `foundation.ids.default`
    - [x] `ulid` → `Coretsia\Foundation\Id\UlidGenerator`
    - [x] `uuid` → `Coretsia\Foundation\Id\UuidGenerator`

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads/writes/Reset discipline:
  - N/A

#### Observability (policy-compliant)

- Spans:
  - N/A
- [x] Metrics:
  - [x] duration values used only as values (not labels); `_duration_ms` naming
- [x] Logs:
  - [x] ids may appear in logs/spans, not metric labels
- HARD RULE:
  - No ids (`correlation_id`, `uow_id`, `request_id`, ULID/UUID) are allowed as metric labels.
  - Durations are values only; use `_duration_ms` naming and integer milliseconds.

#### Errors

- [x] Exceptions introduced:
  - [x] `Coretsia\Foundation\Time\Exception\StopwatchInvalidStateException` — errorCode `CORETSIA_STOPWATCH_INVALID_STATE` (optional)
  - [x] `Coretsia\Foundation\Id\Exception\IdGenerationFailedException` — errorCode `CORETSIA_ID_GENERATION_FAILED` (optional)

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] secrets in id generation (never derive ids from secret values)
- [x] Allowed:
  - [x] ULID/UUID as safe ids; not as metric labels

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [x] If non-negative duration promised → `framework/packages/core/foundation/tests/Unit/StopwatchDurationIsNonNegativeTest.php`
- [x] If clock determinism in tests needed → `framework/packages/core/foundation/tests/Unit/FrozenClockReturnsDeterministicNowTest.php`
- [x] If UUID generator is supported → `framework/packages/core/foundation/tests/Contract/UuidFormatContractTest.php`
- [x] If float-free `foundation.ids.*` config is promised and `foundation.clock.*` is forbidden → `framework/packages/core/foundation/tests/Contract/FoundationConfigRejectsFloatValuesInIdsContractTest.php`

#### Test harness / fixtures (when integration is needed)

N/A

### Tests (MUST)

- Unit:
  - [x] `framework/packages/core/foundation/tests/Unit/UlidFormatTest.php`
  - [x] `framework/packages/core/foundation/tests/Unit/StopwatchDurationIsNonNegativeTest.php`
  - [x] `framework/packages/core/foundation/tests/Unit/FrozenClockReturnsDeterministicNowTest.php`
- Contract:
  - [x] `framework/packages/core/foundation/tests/Contract/SystemClockReturnsUtcDateTimeImmutableContractTest.php`
  - [x] `framework/packages/core/foundation/tests/Contract/UuidFormatContractTest.php`
  - [x] `framework/packages/core/foundation/tests/Contract/FoundationConfigRejectsFloatValuesInIdsContractTest.php`
- Integration:
  - [x] `framework/packages/core/foundation/tests/Integration/FoundationClockAndStopwatchBindingsTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/FoundationIdsDefaultDoesNotAffectCorrelationIdTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/DefaultIdGeneratorResolvesFromConfigTest.php`
    - [x] asserts `IdGeneratorInterface` resolves to `UlidGenerator` when `foundation.ids.default=ulid`
    - [x] asserts `IdGeneratorInterface` resolves to `UuidGenerator` when `foundation.ids.default=uuid`
    - [x] MUST NOT assert or imply that `CorrelationIdProviderInterface` switches to UUID when `foundation.ids.default=uuid`
- Gates/Arch:
  - N/A

### DoD (MUST)

- [x] One canonical clock via DI (PSR-20)
- [x] One canonical id default (ULID) without duplicates
- [x] Stopwatch returns int ms, deterministic and non-negative
- [x] Docs updated:
  - [x] `docs/ssot/time-ids-and-duration.md`
  - [x] `docs/adr/ADR-0016-clock-ids-stopwatch.md`
- [x] Downstream lifecycle handoff recorded:
  - [x] Later `core/kernel` MAY use Clock + Stopwatch for UoW timings.
  - [x] Later `core/kernel` MAY use `IdGeneratorInterface` for `uow_id`.
  - [x] Later `platform/http` MAY use Stopwatch for middleware timings.
  - [x] Later `platform/http` MAY use `IdGeneratorInterface` for `request_id` when HTTP request-id policy exists.
  - [x] Later `platform/cli` MAY use Clock for deterministic timestamps where required.
- [x] Runtime time/ids APIs MUST be float-free:
  - [x] this epic introduces no numeric runtime config under time/id settings
  - [x] config validation MUST reject float values under `foundation.ids.*` if any nested values are added later
- [x] `framework/packages/core/foundation/config/rules.php` MUST enforce:
  - [x] reject any float values under `foundation.ids.*` if nested values are added later
  - [x] no `foundation.clock.*` config keys are introduced
- [x] IDs MUST be deterministic-format strings:
  - [x] ULID/UUID string formats are validated by contract tests
- [x] Stopwatch duration MUST be:
  - [x] `int`
  - [x] `>= 0`
  - [x] stable across OS (no locale/timezone formatting inside core logic)

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
  - `Coretsia\Contracts\Context\ContextKeys`

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

- [x] `docs/ssot/context-lifecycle.md` — includes:
  - [x] beginUoW writes: `correlation_id`, `uow_id`, `uow_type`
  - [x] HTTP enrichment writes (if enabled): `client_ip`, `scheme`, `host`, `path`, `user_agent`, `request_id`, `http_response_format`
  - [x] routing writes (Phase 1): `path_template`
  - [x] auth writes (Phase 2): `actor_id` (safe id)
  - [x] tenancy writes (Phase 6+): `tenant_id` (safe id)
  - [x] hard bans: session id / tokens / cookies / Authorization / payloads
  - [x] Presence of a key in ContextStore MUST NOT be interpreted as permission to export it into observability.
  - [x] In particular, raw `path` MAY exist as in-process context, but MUST NOT be emitted to logs/spans/metrics; observability export remains governed by `docs/ssot/observability.md`.
  - [x] `docs/ssot/context-lifecycle.md` MUST include an “Examples” section (single-choice):
    - [x] Example A (valid): HTTP request UoW
      - [x] beginUoW writes base keys (`correlation_id`, `uow_id`, `uow_type`)
      - [x] middleware enriches ONLY safe keys
      - [x] afterUoW triggers Foundation reset orchestration via `ResetOrchestrator` using the effective reset discovery tag (reserved default name: `kernel.reset`), and ContextStore becomes empty for the next UoW
    - [x] Example B (valid): CLI command UoW
      - [x] same base keys
      - [x] no HTTP-only keys written
      - [x] reset still runs after UoW
    - [x] Example C (invalid): Context leak across UoW
      - [x] previous request key visible in the next request → MUST be described as policy violation
    - [x] Example D (invalid): secret/PII in ContextStore
      - [x] Authorization/cookies/session id/payload written → MUST be described as hard-ban violation
  - [x] `docs/ssot/context-lifecycle.md` MUST reference the canonical HTTP middleware slot taxonomy already owned by the HTTP/tags SSoT.
  - [x] This document MAY repeat slot names only for lifecycle examples and MUST NOT redefine ownership, ordering, or slot semantics.
    - [x] slots (cemented, tag names; reserved for TagRegistry usage):
      - [x] `http.middleware.system_pre`
      - [x] `http.middleware.system`
      - [x] `http.middleware.system_post`
      - [x] `http.middleware.app_pre`
      - [x] `http.middleware.app`
      - [x] `http.middleware.app_post`
      - [x] `http.middleware.route_pre`
      - [x] `http.middleware.route`
      - [x] `http.middleware.route_post`
    - [x] **Config namespace (NOT a tag):** `http.middleware.auto.*` is a configuration-key namespace under the `http.*` config subtree.
      - [x] `http.middleware.auto` / `http.middleware.auto.*` MUST NOT be used as TagRegistry tag names.
      - [x] Rationale: avoid collisions/confusion with the `http.middleware.*` tag namespace.

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

- [x] `docs/ssot/middleware-context-keys-map.md` — table: middleware FQCN → ContextKeys written/read (reference-only)
  - [x] MUST explicitly state its SSoT linkage:
    - [x] the canonical middleware list/slot ownership/order reference is:
      - [x] `docs/ssot/http-middleware-catalog.md`
    - [x] `framework/tools/spikes/fixtures/http_middleware_catalog.php` MAY be cited only as a Phase 0 lock/alignment input, NOT as SSoT
    - [x] the table MUST NOT re-declare middleware lists; it is a reference map only:
      - [x] `Middleware FQCN → ContextKeys written/read`

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/context-lifecycle.md`
  - [x] `docs/ssot/middleware-context-keys-map.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads:
  - N/A (doc defines lifecycle; runtime reads happen elsewhere)
- [x] Context writes (safe only):
  - [x] base UoW keys (kernel)
  - [x] request/app safe keys (middlewares/packages)
- [x] Reset discipline:
  - [x] always via `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`
    using the effective reset discovery tag resolved from `foundation.reset.tag`
    (reserved default `kernel.reset`)

#### Observability (policy-compliant)

- [x] Logs:
  - [x] explicitly bans secrets/PII in ContextStore; only safe ids + hash/len guidance

#### Errors

N/A (doc-only)

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] session ids/tokens/cookies/Authorization/payloads
- [x] Allowed:
  - [x] safe ids + `hash(value)` / `len(value)` outside metric labels
- [x] Hard bans (single-choice; enforceable) — ContextStore MUST NOT contain:
  - [x] `Authorization`, cookies, session ids, tokens (any form),
  - [x] raw request/response payloads,
  - [x] raw headers (except allowlisted safe ones like `User-Agent` as a safe string, if policy allows),
  - [x] anything that can identify a user beyond a safe id (Phase 2+ owns those ids).
- [x] Allowed values are only:
  - [x] safe ids (opaque correlation/uow ids),
  - [x] normalized request metadata (scheme/host/path) WITHOUT query string (unless hashed),
  - [x] `hash(value)` / `len(value)` when needed, never raw secret.

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [x] N/A (doc-only); MUST reference enforcement rails:
  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeAlwaysResetsAfterUowTest.php`
  - [x] `framework/tools/gates/cross_cutting_contract_gate.php`

### Tests (MUST)

- Unit/Contract/Integration:
  - N/A
- Gates/Arch:
  - [x] referenced by enforcement rails (see Verification)

### DoD (MUST)

- [x] Docs exist and match `ContextKeys` SSoT + middleware catalog
- [x] No contradictory rules vs kernel/http epics
- [x] Enforcement rails reference (MUST):
  - [x] Kernel reset invariant MUST be enforced by an integration test (example path):
    - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeAlwaysResetsAfterUowTest.php`
  - [x] Cross-cutting gate MUST validate “no forbidden ContextKeys writes” (example gate path):
    - [x] `framework/tools/gates/cross_cutting_contract_gate.php`
  - [x] The doc MUST explicitly describe what the gate/test checks (single-choice):
    - [x] afterUoW → `ResetOrchestrator::resetAll()` runs → ContextStore is empty for the next UoW.

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
  - `Coretsia\Foundation\Tag\ReservedTags::KERNEL_RESET`
  - `Coretsia\Foundation\Tag\ReservedTags::KERNEL_STATEFUL`

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

- [x] `docs/ssot/stateful-services.md` — rules + examples:
  - [x] effective reset tag = `foundation.reset.tag`, default=`kernel.reset`
  - [x] `kernel.stateful ⇒ implements ResetInterface && tagged with <effective reset tag>`
  - [x] examples of stateful: identity store, deferred queues, caches with per-UoW state
  - [x] examples of forbidden state: static globals, request singletons without reset
  - [x] MUST include:
    - [x] “Definition” section (single-choice):
      - [x] *stateless by default* means: no per-UoW memory, no retained request data, no implicit caches.
      - [x] *stateful* means: service retains mutable in-memory state across calls within the same process.
    - [x] “Examples” section with 3–5 minimal examples (copy-pastable):
      - [x] valid: in-memory queue that is cleared on reset (`ResetInterface` + effective reset tag; default example reset discovery tag is Foundation-owned and opaque to the consumer)
      - [x] valid: memoized cache that is cleared on reset
      - [x] invalid: static global cache (`static $x`) persisting across UoW
      - [x] invalid: request singleton without reset discipline
      - [x] invalid: stateful service tagged `kernel.stateful` but missing `ResetInterface`

- [x] `docs/ssot/reset-tags.md` — reset semantics and usage rules for already-canonical tags
  - [x] MUST NOT redefine tag ownership or registry rows from `docs/ssot/tags.md`.
  - [x] Owns only reset-specific semantics:
    - [x] effective reset discovery tag
    - [x] reserved default name
    - [x] `kernel.stateful` invariant
    - [x] redaction/safety rules for reset-related diagnostics
  - [x] effective reset tag = `foundation.reset.tag`, default=`kernel.reset`
  - [x] kernel.stateful => implements ResetInterface && tagged with the effective reset discovery tag (`foundation.reset.tag`, default `kernel.reset`)

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

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/stateful-services.md`
  - [x] `docs/ssot/reset-tags.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] secrets in memory beyond UoW (if unavoidable, must be explicitly documented and wiped on reset)
- [x] Allowed:
  - [x] safe ids + hash/len patterns

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [x] N/A (doc-only), but MUST reference enforcement rails:
  - [x] `framework/tools/gates/cross_cutting_contract_gate.php`
  - [x] phpstan rule: “stateful/tagged services MUST implement ResetInterface” (referenced; owned elsewhere)

### Tests (MUST)

- Unit/Contract/Integration:
  - N/A
- Gates/Arch:
  - [x] enforcement rails referenced via gates/phpstan

### DoD (MUST)

- [x] Docs exist and are referenced by runtime packages READMEs
- [x] CI rails exist/are referenced for enforcement (gate/phpstan)
- [x] A stateful service MUST NOT retain secrets/PII beyond a UoW.
  - [x] If a secret must exist transiently, it MUST be wiped during reset and MUST be documented explicitly.
- [x] Reset MUST be deterministic.
- [x] Stateful services SHOULD make repeated reset calls on already-clean state safe/idempotent where feasible.
- [x] This MUST NOT be interpreted as “reset can never throw”:
  - [x] orchestrator failure semantics remain fail-fast on service exception, as owned by `1.250.0`
- [x] Error reporting MUST be safe:
  - [x] MUST NOT include absolute paths, payloads, tokens, headers in exception messages.
- [x] Enforcement rails (MUST reference):
  - [x] CI MUST fail if a service is tagged `kernel.stateful` but does not implement `Coretsia\Contracts\Runtime\ResetInterface`.
  - [x] CI MUST fail if a stateful service is missing discovery through the effective Foundation reset tag.
    - [x] enforcement may be via integration/wiring tests or compile-time gates against resolved config
  - [x] Reference enforcement mechanisms (example):
    - [x] `framework/tools/gates/cross_cutting_contract_gate.php`
    - [x] phpstan rule: “kernel.stateful ⇒ implements ResetInterface” (owned elsewhere; referenced here)

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
- "docs/ssot/observability.md"
- "docs/ssot/observability-and-errors.md"
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

Tooling:
- [x] `framework/tools/gates/observability_metric_catalog_gate.php`
  - [x] loads canonical metrics catalog from `docs/ssot/observability.md`
  - [x] fails deterministically if the canonical metrics catalog section is missing or unparseable
  - [x] rejects duplicate metric catalog rows
  - [x] rejects catalog rows with unsupported type values
  - [x] supported catalog metric types are exactly `counter` and `observe`
  - [x] rejects catalog labels outside the global label allowlist
  - [x] scans runtime package source only: `framework/packages/**/src/**/*.php`
  - [x] ignores docs/tests/tools/var/vendor
  - [x] treats a call as a meter emission only when the receiver is resolvable as `MeterPortInterface`
  - [x] validates metric names used in meter `increment()` / `observe()` calls against the canonical catalog
  - [x] rejects any runtime metric name absent from the canonical catalog
  - [x] catches singular/plural drift through catalog mismatch
  - [x] accepts metric names only as:
    - [x] direct string literals
    - [x] same-class private `const string` values accessed through `self::CONST`
  - [x] rejects concatenated, computed, inherited, external, global, or dynamically resolved metric names
  - [x] validates meter method compatibility with catalog type:
    - [x] `increment()` accepts only `counter` metrics
    - [x] `observe()` accepts only `observe` metrics
  - [x] validates label keys per metric against the canonical catalog row
  - [x] accepts labels only as:
    - [x] omitted labels argument
    - [x] direct array literals with string keys
    - [x] same-method local variables assigned to array literals with resolvable string keys
  - [x] label values may be dynamic
  - [x] label keys MUST be resolvable
  - [x] rejects unknown label keys for known metrics
  - [x] rejects non-resolvable dynamic label maps
  - [x] MUST NOT validate span names; span naming is owned by `observability_span_naming_gate.php`
  - [x] MUST NOT replace `observability_naming_gate.php` or `observability_span_naming_gate.php`; it complements both

- [x] `framework/tools/gates/observability_span_naming_gate.php`
  - [x] loads canonical span naming policy from `docs/ssot/observability.md`
  - [x] fails deterministically if canonical span naming policy is missing or unparseable
  - [x] scans runtime package source only: `framework/packages/**/src/**/*.php`
  - [x] ignores docs/tests/tools/var/vendor
  - [x] treats a call as a span emission only when the receiver is resolvable as `TracerPortInterface` and the method is `startSpan(...)` or `inSpan(...)`
  - [x] validates span names used in `startSpan(...)` and `inSpan(...)` calls against canonical span naming policy
  - [x] does not validate `currentSpan()` because it does not accept or emit a span name
  - [x] span names MUST use shape `<domain>.<singular_operation>`
  - [x] rejects malformed span names such as:
    - [x] `foundation..reset`
    - [x] `foundation.reset.total`
  - [x] rejects plural operation drift in span names, e.g. `foundation.resets`
  - [x] accepts valid singular span names, e.g. `foundation.reset`
  - [x] accepts span names only as:
    - [x] direct string literals
    - [x] same-class private `const string` values accessed through `self::CONST`
  - [x] rejects concatenated, computed, inherited, external, global, or dynamically resolved span names
  - [x] MUST NOT validate span names through the canonical metrics catalog
  - [x] MUST NOT replace `observability_naming_gate.php` or `observability_metric_catalog_gate.php`; it complements both

Docs:
- [x] `docs/adr/ADR-0019-enhanced-reset-long-running.md`

Implementation:
- [x] `framework/packages/core/foundation/src/Runtime/Reset/PriorityResetOrchestrator.php`
  - [x] Enhanced-mode deterministic ordering algorithm only:
    - [x] Collect resettable services from the effective Foundation reset discovery list in `TagRegistry` (`foundation.reset.tag`, default `kernel.reset`)
    - [x] This class does not know about `foundation.reset.priority.enabled`; mode selection is owned by `ResetOrchestrator`
    - [x] For each service resolve `priority` and `group` deterministically (see Tag meta parsing)
    - [x] Sort keys (single-choice):
      - [x] 1) `priority` DESC (higher first)
      - [x] 2) `group` ASC by `strcmp` on normalized group id
      - [x] 3) `serviceId` ASC by `strcmp` (container service id string)
    - [x] Sorting MUST be stable and deterministic across OS/PHP builds
  - [x] Execution semantics (single-choice):
    - [x] Reset is performed sequentially in sorted order (no parallelism)
    - [x] On first thrown exception from a resettable service:
      - [x] MUST stop processing (fail-fast) and surface deterministic failure semantics (see Errors)
      - [x] MUST emit observability summary with outcome=failed (noop-safe ports allowed)

- [x] `framework/packages/core/foundation/src/Runtime/Reset/ResetGroup.php`
  - [x] Value object for normalized group id.

- [x] `framework/packages/core/foundation/src/Runtime/Reset/ResetPriority.php`
  - [x] Value object for validated priority int.

Errors (deterministic, code-first):
- [x] `framework/packages/core/foundation/src/Runtime/Reset/ResetErrorCodes.php`
  - [x] MUST define string codes (cemented):
    - [x] `CORETSIA_RESET_META_INVALID`
    - [x] `CORETSIA_RESET_SERVICE_NOT_RESETTABLE`
    - [x] `CORETSIA_RESET_SERVICE_FAILED`

- [x] `framework/packages/core/foundation/src/Runtime/Reset/ResetException.php`
  - [x] MUST carry deterministic string code from `ResetErrorCodes`:
    - [x] `code(): string`

Tests:
- [x] `framework/packages/core/foundation/tests/Integration/PriorityResetOrderDeterministicTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/ResetGroupWorksTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/PriorityResetBackCompatWhenDisabledTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/PriorityResetMetaParsingRejectsInvalidTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/ResetOrderingIsLocaleIndependentTest.php`
  - [x] MUST set a non-trivial locale (if available) and still assert identical ordering.
- [x] `framework/packages/core/foundation/tests/Integration/PriorityResetIgnoresMetaWhenDisabledTest.php`
  - [x] with `foundation.reset.priority.enabled=false`, invalid meta MUST NOT fail
  - [x] ordering MUST equal legacy (`TagRegistry->all(...)` order) exactly
- [x] `framework/packages/core/foundation/tests/Integration/PriorityResetIgnoresUnknownMetaKeysWhenEnabledTest.php`
  - [x] meta contains extra keys (e.g. `{"priority": 10, "group": "default", "x": "y", "debug": ["a"=>1]}`)
  - [x] MUST NOT fail because of unknown keys
  - [x] ordering MUST be computed only from (`priority`,`group`,`serviceId`)
- [x] `framework/packages/core/foundation/tests/Integration/PriorityResetUsesConfiguredResetTagTest.php`
- [x] `framework/packages/core/foundation/tests/Integration/PriorityResetFailsFastOnFirstServiceExceptionTest.php`
  - [x] asserts first thrown service exception stops further reset processing
  - [x] asserts deterministic `ResetException(code=CORETSIA_RESET_SERVICE_FAILED, message="reset-service-failed")`
  - [x] asserts summary-only observability path remains safe
- [x] `framework/packages/core/foundation/tests/Integration/PriorityResetEmitsSafeSummaryObservabilityTest.php`
  - [x] optional but recommended
  - [x] span name `foundation.reset`
  - [x] attrs: `services_count`, `groups_count`, `outcome`
  - [x] metrics:
    - [x] `foundation.reset_total` with labels `outcome`
    - [x] `foundation.reset_duration_ms` with labels `outcome`
  - [x] no service ids/payloads/meta/raw exceptions in emitted labels/attrs

Tooling tests:
- [x] `framework/tools/tests/Contract/ObservabilityMetricCatalogGateTest.php`
  - [x] uses isolated temp repo fixtures generated inline by each data-provider case
  - [x] writes a minimal `docs/ssot/observability.md` catalog fixture
  - [x] writes minimal runtime package source fixtures under `framework/packages/core/foundation/src/*.php`
  - [x] installs a minimal gate harness under `framework/tools/**`
  - [x] avoids persistent fixture files for this gate because each case needs small, isolated catalog/runtime-source combinations
  - [x] accepts `foundation.reset_total`
  - [x] accepts `foundation.reset_duration_ms`
  - [x] accepts same-class private `const string` metric names accessed through `self::CONST`
  - [x] rejects `foundation.resets_total`
  - [x] rejects `foundation.resets_duration_ms`
  - [x] rejects `foundation.reset.total`
  - [x] rejects `foundation..reset_total`
  - [x] rejects runtime metric names absent from catalog
  - [x] rejects unknown labels for known metric
  - [x] rejects `increment('foundation.reset_duration_ms', ...)`
  - [x] rejects `observe('foundation.reset_total', ...)`
  - [x] rejects non-resolvable dynamic metric names
  - [x] rejects non-resolvable dynamic label maps
  - [x] rejects duplicate catalog metric rows
  - [x] rejects unsupported catalog metric type
  - [x] rejects catalog labels outside the global allowlist
  - [x] rejects missing/unparseable canonical metrics catalog section
  - [x] rejects named-argument `MeterPortInterface` calls as unparseable
- [x] `framework/tools/tests/Contract/ObservabilitySpanNamingGateTest.php`
  - [x] uses isolated temp repo fixtures generated inline by each data-provider case
  - [x] writes minimal runtime package source fixtures under `framework/packages/core/foundation/src/*.php`
  - [x] installs a minimal gate harness under `framework/tools/**`
  - [x] accepts valid singular span name `foundation.reset`
  - [x] accepts same-class private `const string` span names accessed through `self::CONST`
  - [x] rejects malformed span names:
    - [x] `foundation..reset`
    - [x] `foundation.reset.total`
  - [x] rejects plural span operation drift, e.g. `foundation.resets`
  - [x] rejects non-resolvable dynamic span names
  - [x] rejects concatenated or computed span names
  - [x] validates span names only for calls whose receiver is resolvable as `TracerPortInterface`
  - [x] does not require span names to exist in the canonical metrics catalog

#### Modifies

- [x] `docs/ssot/reset-tags.md`
  - [x] extend reset SSoT with enhanced reset semantics:
    - [x] `foundation.reset.priority.enabled`
    - [x] `foundation.reset.group.default`
    - [x] supported meta keys: `priority`, `group`
    - [x] disabled-mode behavior = exact legacy order, no meta parsing
    - [x] enabled-mode behavior = deterministic planned order
    - [x] deterministic reset error codes introduced by this epic
  - [x] reference canonical metrics catalog in `docs/ssot/observability.md`
  - [x] list reset-specific observability usage without redefining catalog ownership
  - [x] remove/update “future enhanced reset” wording now owned by epic 1.250.0
  - [x] document enhanced reset config/meta/order/error semantics
  - [x] add reset observability ownership split:
    - [x] reset span name: `foundation.reset`; validated by span naming policy
    - [x] reset metrics:
      - [x] `foundation.reset_total`
      - [x] `foundation.reset_duration_ms`
    - [x] reset metric names and metric-specific labels are owned by the canonical metrics catalog in `docs/ssot/observability.md`

- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0019-enhanced-reset-long-running.md`

- [x] `framework/packages/core/foundation/src/Runtime/Reset/ResetOrchestrator.php`
  - [x] MUST remain the stable public entrypoint used by `core/kernel` (no kernel changes)
  - [x] when `foundation.reset.priority.enabled=false`, MUST preserve legacy/base mode exactly:
    - [x] iterate EXACT `TagRegistry->all($effectiveResetTag)` order
    - [x] ignore reset meta completely
    - [x] keep compatibility with `1.200.0` behavior
  - [x] when `foundation.reset.priority.enabled=true`, MUST delegate to `PriorityResetOrchestrator`, which executes deterministic enhanced reset order:
    - [x] 1. `priority` DESC
    - [x] 2. `group` ASC by `strcmp` on normalized group id
    - [x] 3. `serviceId` ASC by `strcmp`
  - [x] MUST reject tagged non-resettable services with:
    - [x] `ResetException(code=CORETSIA_RESET_SERVICE_NOT_RESETTABLE, message="reset-not-resettable")`
  - [x] **No feature-disable switch (cemented; inherited from 1.200.0):**
    - [x] reset discovery and reset orchestration are baseline runtime safety mechanisms and MUST NOT be disabled via config
    - [x] `ResetOrchestrator::resetAll()` MAY be a deterministic noop only when the effective discovery list is empty
    - [x] `foundation.reset.priority.enabled` controls only enhanced ordering/meta planning behavior and MUST NOT disable baseline reset orchestration itself
  - [x] MUST delegate deterministically (single-choice):
    - [x] if `foundation.reset.priority.enabled=false`:
      - [x] execute legacy mode: iterate EXACT `TagRegistry->all(<effective reset tag>)` order, where the effective tag comes from Foundation wiring/config (`foundation.reset.tag`, default `kernel.reset`)
      - [x] ignore meta completely (no parsing/validation)
    - [x] if `foundation.reset.priority.enabled=true`:
      - [x] delegate planning/execution to `PriorityResetOrchestrator`
  - [x] MUST reject tag misuse deterministically:
    - [x] if a service discovered through the effective Foundation reset discovery tag resolved from `foundation.reset.tag` (reserved default `kernel.reset`) resolves to an instance that does NOT implement `ResetInterface`
      → MUST throw `ResetException(code=CORETSIA_RESET_SERVICE_NOT_RESETTABLE, message="reset-not-resettable")`

- [x] `framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php`
  - [x] registers/binds `Coretsia\Foundation\Runtime\Reset\PriorityResetOrchestrator`
  - [x] keeps `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator` as the stable public entrypoint and injects enhanced reset collaborators deterministically

- [x] `framework/packages/core/foundation/src/Provider/FoundationServiceFactory.php`
  - [x] deterministic factory wiring for `PriorityResetOrchestrator` and its reset-planning collaborators
  - [x] MUST NOT keep mutable runtime state

- [x] `framework/packages/core/foundation/config/foundation.php`
  - [x] MUST follow canonical config policy:
    - [x] file returns the subtree (MUST NOT repeat the root key `foundation`).

- [x] `framework/packages/core/foundation/config/rules.php`
  - [x] MUST enforce shape and defaults for keys below.
  - [x] `foundation.reset.group.default` має проходити той самий regex, що й `group meta` (інакше буде “конфіг валідний, але runtime падає”)

- [x] `framework/packages/core/foundation/tests/Integration/ResetOrchestratorRejectsTaggedNonResettableServiceTest.php`
  - [x] upgrade the `1.200.0` hard-fail assertion to the typed reset failure:
    - [x] `ResetException(code=CORETSIA_RESET_SERVICE_NOT_RESETTABLE, message="reset-not-resettable")`
  - [x] keep the stable message `reset-not-resettable`
  - [x] keep the deterministic fail-fast behavior for tagged non-resettable services

- [x] `docs/ssot/observability.md`
  - [x] extend Canonical metrics catalog
  - [x] define metric name shape `<domain>.<singular_operation>_<measure>`
  - [x] register:
    - [x] `foundation.reset_total` owner `core/foundation`, type `counter`, labels `outcome`
    - [x] `foundation.reset_duration_ms` owner `core/foundation`, type `observe`, labels `outcome`
  - [x] strengthen span naming policy:
    - [x] span names MUST use shape `<domain>.<singular_operation>`
    - [x] span `singular_operation` segment MUST be singular
    - [x] valid reset span name is `foundation.reset`
    - [x] plural span operation drift such as `foundation.resets` is forbidden
    - [x] span names MUST NOT be added to the canonical metrics catalog

- [x] `docs/ssot/observability-and-errors.md`
  - [x] align MeterPortInterface policy with canonical metrics catalog
  - [x] state that global label allowlist is necessary but not sufficient
  - [x] state that metric-specific catalog labels are authoritative
  - [x] state meter method compatibility with catalog type
  - [x] Add:
    - [x] `observability-naming:gate` enforces generic observability naming and global label policy.
    - [x] `observability-span-naming:gate` enforces `TracerPortInterface::startSpan(...)` span naming policy.
    - [x] `observability-metric-catalog:gate` enforces `MeterPortInterface` metric catalog registration, method/type compatibility, and metric-specific labels.

- [x] `docs/ssot/profiling-ports.md`
  - [x] Strengthen profiling metrics policy:
    - [x] profiling implementations MAY emit only bounded summary metrics
    - [x] metric names MUST be registered in the canonical metrics catalog in `docs/ssot/observability.md`
    - [x] metric labels MUST satisfy both the global label allowlist and the metric-specific catalog row

- [x] `docs/ssot/context-lifecycle.md`
  - [x] Clean up live SSoT wording now that enhanced reset is owned by epic `1.250.0`:
    - [x] replace future-style wording such as “if enhanced reset is enabled by epic `1.250.0`”
    - [x] use current runtime policy wording instead, for example:
      - [x] “if enhanced reset is enabled”
      - [x] or “when `foundation.reset.priority.enabled=true`”

- [x] `docs/ssot/stateful-services.md`
  - [x] Clean up live SSoT wording now that enhanced reset is no longer future work:
    - [x] replace “future enhanced reset implementation from epic `1.250.0`”
    - [x] with “enhanced reset implementation details are owned by `docs/ssot/reset-tags.md`”

- [x] `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
  - [x] Preserve ADR-0014 historical context, but add a supersession / follow-up note:
    - [x] ADR-0014 correctly described enhanced reset ordering as future/deferred work at the time of the `1.200.0` decision
    - [x] enhanced reset planning and execution semantics are now materialized by epic `1.250.0`
    - [x] canonical live policy is owned by `docs/ssot/reset-tags.md`
    - [x] ADR-0019 records the enhanced reset decision

- [x] `framework/tools/spikes/_support/ErrorCodes.php`
  - [x] add `CORETSIA_OBSERVABILITY_SPAN_NAMING_DRIFT`
  - [x] add `CORETSIA_OBSERVABILITY_SPAN_NAMING_GATE_FAILED`
  - [x] add `CORETSIA_OBSERVABILITY_METRIC_CATALOG_DRIFT`
  - [x] add `CORETSIA_OBSERVABILITY_METRIC_CATALOG_GATE_FAILED`
  - [x] keep registry sorted output deterministic via `all()`

- [x] `composer.json` — add repo-root mirror scripts:
  - [x] `observability-span-naming:gate` → `@composer --no-interaction --working-dir=framework run-script observability-span-naming:gate --`
  - [x] `observability-metric-catalog:gate` → `@composer --no-interaction --working-dir=framework run-script observability-metric-catalog:gate --`

- [x] `framework/composer.json` — add workspace gate scripts:
  - [x] `observability-span-naming:gate` → `@php tools/gates/observability_span_naming_gate.php`
  - [x] `observability-metric-catalog:gate` → `@php tools/gates/observability_metric_catalog_gate.php`
  - [x] keep observability gates ordered immediately after existing `@observability-naming:gate` in the aggregate `gates` script:
    - [x] `@observability-span-naming:gate`
    - [x] `@observability-metric-catalog:gate`

- [x] `docs/guides/commands.md`
  - [x] add `composer observability-metric-catalog:gate`
    - [x] document deterministic read-only behavior for metric catalog gate
    - [x] document metric catalog failure codes
    - [x] state that this gate is metrics-only
    - [x] state that `MeterPortInterface` metric catalog membership, method/type compatibility, and metric-specific labels are validated by `composer observability-metric-catalog:gate`
    - [x] state that span names are validated by `composer observability-span-naming:gate`
    - [x] state that span names MUST NOT be registered in the canonical metrics catalog
  - [x] add `composer observability-span-naming:gate`
    - [x] document deterministic read-only behavior for span naming gate
    - [x] document span naming failure codes
    - [x] state that this gate is span-only
    - [x] state that `TracerPortInterface::startSpan(...)` span names are validated by span naming policy, not by the metrics catalog
    - [x] state that metric catalog policy remains owned by `composer observability-metric-catalog:gate`
  - [x] update `composer gates` order

#### Configuration (keys + defaults)

- [x] Files:
  - [x] `framework/packages/core/foundation/config/foundation.php`
- [x] Keys (dot):
  - [x] Base reset key (from 1.200.0; reiterated for completeness):
    - [x] `foundation.reset.tag` = "kernel.reset"
  - [x] Enhanced reset keys (introduced/owned by 1.250.0; single-choice; cemented):
    - [x] `foundation.reset.priority.enabled` = true
    - [x] `foundation.reset.group.default` = "default"
      - [x] MUST match `/\A[a-z0-9][a-z0-9._-]*\z/`

- [x] Tag meta parsing rules (single-choice; cemented):
  - [x] If `foundation.reset.priority.enabled=false`:
    - [x] reset execution MUST preserve legacy order exactly
    - [x] orchestrator MUST NOT parse or validate reset meta at all

  - [x] If `foundation.reset.priority.enabled=true`:
    - [x] reset planning MUST treat `TaggedService.priority` as the BASE priority
    - [x] supported meta keys are:
      - [x] `priority` (optional override)
      - [x] `group` (optional normalized group id)
    - [x] `priority`:
      - [x] accepted: `int` OR `string` matching `/\A-?\d+\z/`
      - [x] normalized: cast to int
      - [x] if absent: use `TaggedService.priority`
      - [x] invalid → deterministic `ResetException(code=CORETSIA_RESET_META_INVALID, message="reset-meta-invalid")`
    - [x] `group`:
      - [x] accepted: `string`
      - [x] normalization:
        - [x] trim ASCII whitespace
        - [x] if empty OR absent → use `foundation.reset.group.default`
        - [x] validate against `/\A[a-z0-9][a-z0-9._-]*\z/`
      - [x] invalid → deterministic `ResetException(code=CORETSIA_RESET_META_INVALID, message="reset-meta-invalid")`

    - [x] Orchestrator MUST read and validate ONLY:
      - [x] `priority`, `group`
    - [x] Any other meta keys MUST be ignored.

#### Wiring / DI tags (when applicable)

- [x] Tag meta support:
  - [x] the effective Foundation reset discovery tag meta supports:
    - [x] `priority` (int|string-int; default falls back to `TaggedService.priority`)
    - [x] `group` (string; normalized; default `foundation.reset.group.default`)
  - [x] Backward compat:
    - [x] when meta not provided OR priority disabled, ordering preserves previous deterministic behavior.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [x] Spans (noop-safe):
  - [x] `foundation.reset`
    - [x] span name uses singular operation form `<domain>.<singular_operation>`
    - [x] attrs (single-choice; cemented): `services_count`, `groups_count`, `outcome`
- [x] Metrics (noop-safe; MUST NOT add new label keys):
  - [x] `foundation.reset_total` (labels: `outcome`)
  - [x] `foundation.reset_duration_ms` (labels: `outcome`)
- [x] Logs (optional; summary-only):
  - [x] MUST NOT log per-service internals/payloads/stack traces by default.
  - [x] Allowed: counts + outcome + (optional) group counts.

#### Errors

- [x] Deterministic code mapping (single-choice; cemented):
  - [x] invalid tag meta → `CORETSIA_RESET_META_INVALID`
  - [x] resolved service does NOT implement `ResetInterface` → `CORETSIA_RESET_SERVICE_NOT_RESETTABLE`
  - [x] first reset failure (service throws) → `CORETSIA_RESET_SERVICE_FAILED`
- [x] Exception messages MUST be stable + safe:
  - [x] MUST NOT contain absolute paths
  - [x] MUST NOT contain secrets/PII/payloads
  - [x] allowed: fixed tokens only (e.g. `reset-meta-invalid`, `reset-not-resettable`, `reset-service-failed`)

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] service internals, secrets, payloads, absolute machine paths
- [x] Allowed:
  - [x] counts/outcome, normalized group ids

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [x] Deterministic ordering with priority/group:
  - [x] `framework/packages/core/foundation/tests/Integration/PriorityResetOrderDeterministicTest.php`
- [x] Group behavior:
  - [x] `framework/packages/core/foundation/tests/Integration/ResetGroupWorksTest.php`
- [x] Backward compat when disabled:
  - [x] `framework/packages/core/foundation/tests/Integration/PriorityResetBackCompatWhenDisabledTest.php`
- [x] Deterministic invalid-meta rejection:
  - [x] `framework/packages/core/foundation/tests/Integration/PriorityResetMetaParsingRejectsInvalidTest.php`
- [x] Locale independence:
  - [x] `framework/packages/core/foundation/tests/Integration/ResetOrderingIsLocaleIndependentTest.php`
- [x] Enhanced reset config shape lock:
  - [x] `framework/packages/core/foundation/tests/Contract/FoundationEnhancedResetConfigShapeContractTest.php`

### Tests (MUST)

- Contract:
  - [x] `framework/packages/core/foundation/tests/Contract/FoundationEnhancedResetConfigShapeContractTest.php`
    - [x] asserts `foundation.reset.priority.enabled` is bool
    - [x] asserts `foundation.reset.group.default` matches `/\A[a-z0-9][a-z0-9._-]*\z/`
    - [x] asserts invalid values fail deterministically with safe messages
- Integration:
  - [x] `framework/packages/core/foundation/tests/Integration/PriorityResetOrderDeterministicTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/ResetGroupWorksTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/PriorityResetBackCompatWhenDisabledTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/PriorityResetMetaParsingRejectsInvalidTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/ResetOrderingIsLocaleIndependentTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/ResetOrchestratorRejectsTaggedNonResettableServiceTest.php`
    - [x] upgrades the `1.200.0` assertion from `RuntimeException(message="reset-not-resettable")`
      to `ResetException(code=CORETSIA_RESET_SERVICE_NOT_RESETTABLE, message="reset-not-resettable")`

### DoD (MUST)

- [x] Deterministic reset order (priority DESC, group ASC, serviceId ASC) when enabled
- [x] Backward compatible:
  - [x] when `foundation.reset.priority.enabled=false` → legacy order preserved exactly
- [x] Safe observability:
  - [x] spans/metrics/logs do not leak secrets/PII
  - [x] no new metric label keys introduced
  - [x] runtime span names emitted through `TracerPortInterface::startSpan(...)` follow canonical span naming policy
  - [x] plural span operation drift such as `foundation.resets` is rejected
  - [x] runtime metric names are registered in the canonical metrics catalog
  - [x] metric labels match metric-specific catalog rows
- [x] Deterministic error codes exist and are used (`ResetErrorCodes`)
- [x] Tests green
- [x] Out of scope:
  - [x] MUST NOT change `ResetInterface`
  - [x] MUST NOT introduce plugin systems/extensibility
- [x] `docs/ssot/reset-tags.md` updated and aligned with implementation/tests
- [x] `ResetOrchestratorRejectsTaggedNonResettableServiceTest.php` upgraded from `1.200.0` hard-fail-only behavior to typed `ResetException` behavior
- [x] Enhanced reset order is implemented when `foundation.reset.priority.enabled=true`:
  - [x] `priority DESC`
  - [x] `group ASC`
  - [x] `serviceId ASC`
- [x] Legacy/base reset order from `1.200.0` remains unchanged when `foundation.reset.priority.enabled=false`

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

- [x] `docs/ssot/runtime-drivers.md` — MUST include (single-choice; cemented):
  - [x] Terminology / IDs (single-choice):
    - [x] HTTP / background driver details (single-choice; cemented)
      - [x] `http.classic`
        - [x] Enabled when:
          - [x] `kernel.runtime.frankenphp.enabled=false`
          - [x] `kernel.runtime.swoole.enabled=false`
          - [x] `kernel.runtime.roadrunner.enabled=false`
          - [x] NOT (`worker.enabled=true && worker.task_type='http'`)
        - [x] Conflicts:
          - [x] none by itself
        - [x] May run alongside:
          - [x] `bg.worker_queue`
      - [x] `http.frankenphp`
        - [x] Enabled when:
          - [x] `kernel.runtime.frankenphp.enabled=true`
        - [x] Conflicts:
          - [x] any other `http.*` driver enabled at the same time
          - [x] `worker.enabled=true && worker.task_type='http'`
        - [x] May run alongside:
          - [x] `bg.worker_queue`
        - [x] Notes:
          - [x] artifact-only boot required (`module-manifest.php`, `config.php`, `container.php`, `routes.php`)
          - [x] UoW boundary must be enforced per request; reset exactly once per UoW via kernel runtime
      - [x] `http.swoole`
        - [x] Enabled when:
          - [x] `kernel.runtime.swoole.enabled=true`
        - [x] Conflicts:
          - [x] any other `http.*` driver enabled at the same time
          - [x] `worker.enabled=true && worker.task_type='http'`
        - [x] May run alongside:
          - [x] `bg.worker_queue`
        - [x] Notes:
          - [x] artifact-only boot required
          - [x] long-running loop must not leak context/state across requests
      - [x] `http.roadrunner`
        - [x] Enabled when:
          - [x] `kernel.runtime.roadrunner.enabled=true`
        - [x] Conflicts:
          - [x] any other `http.*` driver enabled at the same time
          - [x] `worker.enabled=true && worker.task_type='http'`
        - [x] May run alongside:
          - [x] `bg.worker_queue`
        - [x] Notes:
          - [x] artifact-only boot required
          - [x] long-running loop must not leak context/state across requests
      - [x] `http.worker`
        - [x] Enabled when:
          - [x] `worker.enabled=true && worker.task_type='http'`
        - [x] Conflicts:
          - [x] any other `http.*` driver enabled at the same time
        - [x] Notes:
          - [x] this is an HTTP runtime mode, not a background driver
      - [x] `bg.worker_queue`
        - [x] Enabled when:
          - [x] `worker.enabled=true && worker.task_type='queue'`
        - [x] Conflicts:
          - [x] none at the matrix level
        - [x] May run alongside:
          - [x] `http.classic`
          - [x] `http.frankenphp`
          - [x] `http.swoole`
          - [x] `http.roadrunner`
        - [x] Notes:
          - [x] this is a background driver, not an HTTP driver
    - [x] Reserved future IDs (NOT part of the current Phase 1 guard inputs):
      - [x] `bg.queue`
      - [x] `bg.scheduler`
  - [x] Hard rules (single-choice; MUST):
    - [x] Exactly ONE HTTP driver may be active at a time.
    - [x] Background drivers MAY run alongside any HTTP driver.
    - [x] `http.worker` conflicts with any other `http.*` driver (mutual exclusion).
  - [x] Default safety policy (single-choice):
    - [x] `kernel.runtime.*.enabled` defaults to `false`
    - [x] `worker.enabled` defaults to `false`
    - [x] `worker.task_type` default MUST be safe (and MUST NOT implicitly enable an HTTP driver)
  - [x] Missing-key policy before `1.360.0` (single-choice):
    - [x] absence of `worker.enabled` MUST be treated as `false`
    - [x] absence of `worker.task_type` MUST NOT activate any runtime driver
    - [x] missing `worker.*` root by itself MUST NOT be treated as invalid config
  - [x] Compatibility matrix:
    - [x] MUST include an explicit table of ✅/❌ for:
      - [x] (each http.*) × (each bg.*)
      - [x] and (each pair of http.*) to show mutual exclusion
    - [x] MUST include concrete examples (copy-pastable) for:
      - [x] ✅ `http.roadrunner` + `bg.worker_queue`
      - [x] ✅ `http.swoole` + `bg.worker_queue`
      - [x] ✅ `http.frankenphp` + `bg.worker_queue`
      - [x] ✅ `http.classic` + `bg.worker_queue`
      - [x] ❌ `http.roadrunner` + `http.worker`
      - [x] ❌ `http.frankenphp` + `http.worker`
      - [x] ❌ `http.swoole` + `http.worker`
  - [x] Deterministic enforcement contract (doc-as-SSoT; single-choice):
    - [x] The guard MUST decide the active drivers by evaluating config keys only (no environment probing).
    - [x] On conflict, the guard MUST fail deterministically:
      - [x] deterministic error `CODE` (string) is the primary failure semantic
      - [x] diagnostics (if any) MUST be stable and sorted lexicographically (byte-order; `strcmp`) by driver id.
    - [x] Non-classic HTTP drivers (`http.frankenphp`, `http.swoole`, `http.roadrunner`, `http.worker`)
      require `platform.http` to be enabled in `ModulePlan`.
    - [x] Missing required module for the selected HTTP driver MUST fail with:
      - [x] `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`
  - [x] Error codes (single-choice; cemented names in doc):
    - [x] The doc MUST name the canonical guard error codes used for matrix violations (no free-form messages):
      - [x] `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`
      - [x] `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`
    - [x] The guard MAY include minimal safe diagnostics (driver ids only), but MUST NOT include secrets/PII.
  - [x] References to enforcement tests (required):
    - [x] MUST reference the exact test paths that prove default + conflict behavior.

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/runtime-drivers.md`

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] N/A (doc-only), but MUST reference enforcement tests (exact paths):
  - [x] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixDefaultClassicIsAllowedTest.php`
  - [x] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsRoadrunnerPlusWorkerHttpTest.php`

### Tests (MUST)

- Unit/Contract/Integration:
  - N/A
- Gates/Arch:
  - [x] enforced by guard + tool tests (referenced)

### DoD (MUST)

- [x] Док існує і узгоджений з guard правилами (SSoT)
- [x] Док однозначний (single-choice; no “either/or”)
- [x] Док посилається на відповідні конфіг ключі `kernel.runtime.*.enabled` і `worker.*`
- [x] Док містить compatibility matrix + concrete examples
- [x] Док фіксує deterministic enforcement contract (stable code + stable diagnostics ordering)
- [x] Док посилається на E2E тест-матрицю (tooling integration tests)

---

### 1.265.0 Release-line package versioning + publish safety automation (MUST) [TOOLING+DOC]

---
type: tools
phase: 1
epic_id: "1.265.0"
owner_path: "framework/tools/"

goal: "Coretsia packages can use Packagist-safe internal dependency constraints generated from a single release-line SSoT, while monorepo development continues to resolve local package changes immediately through release-line path repository versions."
provides:
- "Machine-readable release-line SSoT for workspace dev versions and public package constraints."
- "Managed Composer path repository versions generated from release-line SSoT."
- "Workspace require-dev synchronization for internal coretsia/* packages."
- "Automated synchronization of package composer.json internal coretsia/* public constraints from release-line SSoT."
- "Baseline Packagist-safe policy for allowlisted/published package composer.json files."
- "Preparation path for publishing core/foundation before core/kernel development continues."

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "framework/tools/release/release-line.json"
- "docs/architecture/PACKAGING.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - PRELUDE.20.0 — canonical packaging strategy exists and defines package identity rules.
  - PRELUDE.30.0 — root/framework/skeleton Composer workspaces exist.
  - 0.110.0 — release rails and source-only release policy exist.
  - 1.50.0 — tooling rails baseline exists.
  - 1.250.0 — `core/foundation` long-running reset baseline exists and is ready for public package preparation.
  - 1.260.0 — runtime driver SSoT exists and the next runtime work can depend on stable release-line package behavior.

- Required deliverables (exact paths):
  - `composer.json` — root Composer workspace and canonical repo-root scripts.
  - `framework/composer.json` — framework tooling workspace and internal package `require-dev` root.
  - `skeleton/composer.json` — skeleton workspace.
  - `framework/tools/build/sync_composer_repositories.php` — managed repository block synchronizer.
  - `.github/split-publish-packages.json` — public split package allowlist.
  - `framework/packages/core/foundation/composer.json` — first runtime package with an internal Coretsia package dependency prepared for publication.

- Required config roots/keys:
  - none

- Required tags:
  - none

- Required contracts / ports:
  - none

- Release-line terminology (MUST):
  - `schemaVersion` in `framework/tools/release/release-line.json` is the schema version of the file, not the package release version.
  - `schemaVersion` MUST NOT be changed for ordinary release-line bumps such as `0.4 -> 0.5`.
  - `schemaVersion` changes only when the file structure or field semantics change.
  - Patch releases do not change `currentMinor`, `devVersion`, or `publicConstraint`.
  - Minor release-line bumps update only the release-line values:
    - `currentMinor`
    - `devVersion`
    - `publicConstraint`

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- none (tooling-only)

Forbidden:

- runtime packages MUST NOT depend on `framework/tools/release/*`
- runtime packages MUST NOT read `framework/tools/release/release-line.json`
- published package source MUST NOT depend on monorepo release tooling

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- CLI:
  - `composer sync:repos` → generates managed path repository blocks and `options.versions`.
  - `composer sync:check` → verifies managed path repository blocks are in sync.
  - `composer release-line:workspace:sync` → synchronizes `framework/composer.json` internal `coretsia/*` `require-dev` constraints to release-line `devVersion`.
  - `composer release-line:workspace:check` → verifies workspace internal `require-dev` constraints are in sync.
  - `composer release-line:public-constraints:sync` → synchronizes package `composer.json` internal `coretsia/*` constraints to release-line `publicConstraint`.
  - `composer release-line:public-constraints:check` → verifies package public internal constraints are in sync.
  - `composer package-publish-safety:gate` → validates Packagist-safe composer metadata for split-publish allowlisted packages.

- Packagist / split publishing:
  - `.github/split-publish-packages.json` remains the only public split publishing allowlist.
  - Packages in the allowlist MUST have Packagist-safe internal `coretsia/*` constraints before publication.

- Composer path repository integration:
  - root `composer.json` package wildcard path repository:
    - `framework/packages/*/*`
  - framework `composer.json` package wildcard path repository:
    - `packages/*/*`
  - skeleton `composer.json` package wildcard path repository:
    - `../framework/packages/*/*`

- Artifacts:
  - N/A

### Deliverables (MUST)

#### Creates

- [x] `framework/tools/release/release-line.json` — machine-readable release-line SSoT:
  - [x] `schemaVersion` = `coretsia.releaseLine.v1`
  - [x] `currentMinor` = current release minor, e.g. `0.4`
  - [x] `devVersion` = Composer workspace dev version, e.g. `0.4.x-dev`
  - [x] `publicConstraint` = public internal dependency constraint, e.g. `^0.4.0`

- [x] `framework/tools/release/sync_workspace_release_line.php` — synchronizes `framework/composer.json` internal `coretsia/*` `require-dev` constraints from `release-line.json`:
  - [x] reads `framework/tools/release/release-line.json`
  - [x] validates `schemaVersion`
  - [x] validates `currentMinor`, `devVersion`, and `publicConstraint` consistency
  - [x] discovers packages from `framework/packages/*/*/composer.json`
  - [x] validates discovered package names against canonical `coretsia/<layer>-<slug>` naming
  - [x] rewrites managed internal `coretsia/*` package constraints in `framework/composer.json` `require-dev` from discovered packages
  - [x] preserves `ext-*` requirements
  - [x] preserves external dev tooling requirements
  - [x] supports `--check`
  - [x] emits deterministic `OK` / error output
  - [x] writes deterministic JSON bytes with LF-only final newline
  - [x] creates backups only on apply-mode drift

- [x] `framework/tools/release/sync_package_public_constraints.php` — synchronizes package `composer.json` internal `coretsia/*` dependency constraints from `release-line.json`:
  - [x] reads `framework/tools/release/release-line.json`
  - [x] validates `schemaVersion`
  - [x] validates `currentMinor`, `devVersion`, and `publicConstraint` consistency
  - [x] discovers packages from `framework/packages/*/*/composer.json`
  - [x] scans all discovered packages, not only split-publish allowlisted packages
  - [x] validates discovered package names against canonical `coretsia/<layer>-<slug>` naming
  - [x] scans package `require` and `require-dev` sections
  - [x] rewrites only existing internal `coretsia/*` dependency constraints to release-line `publicConstraint`
  - [x] MUST NOT add missing dependencies
  - [x] MUST NOT rewrite external package constraints
  - [x] MUST NOT rewrite `php` or `ext-*` constraints
  - [x] MUST NOT rewrite `suggest`, `provide`, `replace`, or `conflict`
  - [x] MUST NOT add a package-local `version` field
  - [x] supports `--check`
  - [x] emits deterministic `OK` / error output
  - [x] writes deterministic JSON bytes with LF-only final newline
  - [x] creates backups only on apply-mode drift

- [x] `framework/tools/gates/package_publish_safety_gate.php` — validates Packagist-safe composer metadata for allowlisted split packages:
  - [x] reads `.github/split-publish-packages.json`
  - [x] resolves every allowlisted `package_id` to `framework/packages/<layer>/<slug>/composer.json`
  - [x] validates package name equals `coretsia/<layer>-<slug>`
  - [x] validates package `type` is `library`
  - [x] fails if package `composer.json` contains a manual `version` field
  - [x] fails if any internal `coretsia/*` dependency uses `dev-main`
  - [x] fails if any internal `coretsia/*` dependency uses `*`
  - [x] fails if any internal `coretsia/*` dependency uses an `@dev` constraint
  - [x] fails if any internal `coretsia/*` dependency uses an exact semver pin
  - [x] fails if an allowlisted package has an internal `coretsia/*` dependency whose package id is not present in `.github/split-publish-packages.json`
  - [x] future exact-pin exceptions require a dedicated policy change and are not part of this epic
  - [x] validates internal `coretsia/*` dependencies against release-line `publicConstraint`
  - [x] emits deterministic safe diagnostics
  - [x] `package-publish-safety:gate` MUST remain read-only and MUST NOT rewrite composer files.

#### Modifies

- [x] `framework/tools/build/sync_composer_repositories.php` — generate release-line package versions in managed path repositories:
  - [x] reads `framework/tools/release/release-line.json`
  - [x] discovers packages from `framework/packages/*/*/composer.json`
  - [x] validates discovered package names against canonical `coretsia/<layer>-<slug>` naming
  - [x] adds `options.reference = "config"` to package wildcard path repositories
  - [x] adds `options.versions` for every discovered package using release-line `devVersion`
  - [x] sorts `options.versions` by package name using `ksort(..., SORT_STRING)`
  - [x] keeps root/framework/skeleton managed repository blocks deterministic
  - [x] remains the only tool allowed to update managed `repositories` blocks

- [x] `composer.json` — add repo-root mirror scripts and release-line drift checks:
  - [x] `release-line:workspace:sync` → `@composer --no-interaction --working-dir=framework run-script release-line:workspace:sync --`
  - [x] `release-line:workspace:check` → `@composer --no-interaction --working-dir=framework run-script release-line:workspace:check --`
  - [x] `release-line:public-constraints:sync` → `@composer --no-interaction --working-dir=framework run-script release-line:public-constraints:sync --`
  - [x] `release-line:public-constraints:check` → `@composer --no-interaction --working-dir=framework run-script release-line:public-constraints:check --`
  - [x] `package-publish-safety:gate` → `@composer --no-interaction --working-dir=framework run-script package-publish-safety:gate --`
  - [x] add `@release-line:workspace:sync` to `setup` after `@sync:repos`
  - [x] add `@release-line:public-constraints:sync` to `setup` after `@release-line:workspace:sync`
  - [x] add `@release-line:workspace:check` to `ci` after `@sync:check`
  - [x] add `@release-line:public-constraints:check` to `ci` after `@release-line:workspace:check`
  - [x] MUST NOT add `@package-publish-safety:gate` directly to root `ci` because it is executed through aggregate `@gates`

- [x] `framework/composer.json` — add release-line scripts and publish-safety gate:
  - [x] `release-line:workspace:sync` → `@php tools/release/sync_workspace_release_line.php`
  - [x] `release-line:workspace:check` → `@php tools/release/sync_workspace_release_line.php --check`
  - [x] `release-line:public-constraints:sync` → `@php tools/release/sync_package_public_constraints.php`
  - [x] `release-line:public-constraints:check` → `@php tools/release/sync_package_public_constraints.php --check`
  - [x] `package-publish-safety:gate` → `@php tools/gates/package_publish_safety_gate.php`
  - [x] include `@package-publish-safety:gate` in aggregate `gates`
  - [x] do not include release-line drift checks in aggregate `gates`; they are executed by root `ci` before installs
  - [x] replace internal `coretsia/*` `require-dev` constraints from `dev-main` to release-line `devVersion`, e.g. `0.4.x-dev`
  - [x] keep `ext-*` requirements before internal package requirements
  - [x] keep external dev tooling requirements after internal package requirements

- [x] `composer.json` — managed `repositories` block:
  - [x] package wildcard repository `framework/packages/*/*` contains generated:
    - [x] `options.reference = "config"`
    - [x] `options.versions`

- [x] `framework/composer.json` — managed `repositories` block:
  - [x] package wildcard repository `packages/*/*` contains generated:
    - [x] `options.reference = "config"`
    - [x] `options.versions`

- [x] `skeleton/composer.json` — managed `repositories` block:
  - [x] package wildcard repository `../framework/packages/*/*` contains generated:
    - [x] `options.reference = "config"`
    - [x] `options.versions`

- [x] `.github/split-publish-packages.json` — add `core/foundation` when package metadata is Packagist-safe:
  - [x] `core/foundation`

- [x] `framework/packages/core/foundation/composer.json` — internal package dependency is synchronized by `sync_package_public_constraints.php`:
  - [x] `coretsia/core-contracts: dev-main` → release-line `publicConstraint`, e.g. `^0.4.0`

- [x] `docs/architecture/PACKAGING.md` — document release-line package policy:
  - [x] package `composer.json` MUST NOT contain a manual `version` field
  - [x] published / allowlisted packages MUST NOT require internal `coretsia/*` packages as `dev-main`
  - [x] published / allowlisted packages MUST use release-line public semver constraints for internal `coretsia/*` dependencies
  - [x] monorepo workspace uses path repositories plus generated `options.versions`
  - [x] `framework/tools/release/release-line.json` is the tooling SSoT for workspace dev version and public internal constraint
  - [x] Packagist package versions come from git tags, not package-local `composer.json` version fields

- [x] `docs/guides/packagist-split-publishing-guide.md` — document split publication precondition:
  - [x] before adding a package to `.github/split-publish-packages.json`, internal `coretsia/*` dependencies must be release-line public constraints
  - [x] `dev-main` internal dependencies are forbidden for allowlisted/published packages
  - [x] `version` fields are forbidden in package `composer.json`

- [x] `docs/guides/commands.md` — document release-line commands:
  - [x] `composer release-line:workspace:sync`
  - [x] `composer release-line:workspace:check`
  - [x] `composer release-line:public-constraints:sync`
  - [x] `composer release-line:public-constraints:check`
  - [x] `composer package-publish-safety:gate`
  - [x] document that `composer gates` includes `composer package-publish-safety:gate`
  - [x] document that `composer ci` runs release-line drift checks before installs:
    - [x] `composer release-line:workspace:check`
    - [x] `composer release-line:public-constraints:check`
  - [x] document that `composer setup` runs release-line apply steps before installs:
    - [x] `composer release-line:workspace:sync`
    - [x] `composer release-line:public-constraints:sync`
  - [x] describe deterministic behavior and check/apply distinction
  - [x] explain that workspace sync updates `framework/composer.json` internal `require-dev`
  - [x] explain that public constraints sync updates package `composer.json` internal `coretsia/*` dependencies

- [x] `docs/guides/releasing.md` — document release-line bump procedure:
  - [x] patch releases do not change `framework/tools/release/release-line.json`
  - [x] minor release-line bumps update `currentMinor`, `devVersion`, and `publicConstraint`
  - [x] `schemaVersion` is not changed for normal releases
  - [x] after changing release-line values, run:
    - [x] `composer sync:repos`
    - [x] `composer release-line:workspace:sync`
    - [x] `composer release-line:public-constraints:sync`
    - [x] `composer sync:check`
    - [x] `composer release-line:workspace:check`
    - [x] `composer release-line:public-constraints:check`
    - [x] `composer package-publish-safety:gate`

- [x] `framework/tools/spikes/_support/ErrorCodes.php` — register release-line and package publish safety tooling diagnostics:
  - [x] `CORETSIA_RELEASE_LINE_WORKSPACE_SYNC_FAILED`
  - [x] `CORETSIA_RELEASE_LINE_WORKSPACE_OUT_OF_SYNC`
  - [x] `CORETSIA_RELEASE_LINE_PUBLIC_CONSTRAINTS_SYNC_FAILED`
  - [x] `CORETSIA_RELEASE_LINE_PUBLIC_CONSTRAINTS_OUT_OF_SYNC`
  - [x] `CORETSIA_PACKAGE_PUBLISH_SAFETY_VIOLATION`
  - [x] `CORETSIA_PACKAGE_PUBLISH_SAFETY_GATE_FAILED`

#### Configuration (keys + defaults)

N/A

#### Wiring / DI tags (when applicable)

N/A

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

N/A

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [x] Managed repository versions:
  - [x] `composer sync:repos`
  - [x] `composer sync:check`
  - [x] must prove `options.versions` is generated from `framework/tools/release/release-line.json`

- [x] Workspace release-line require-dev sync:
  - [x] `composer release-line:workspace:sync`
  - [x] `composer release-line:workspace:check`
  - [x] must fail if any discovered internal `coretsia/*` package in `framework/composer.json` `require-dev` is not set to release-line `devVersion`

- [x] Composer validation:
  - [x] `composer validate:all`

- [x] CI:
  - [x] `composer ci`

- [x] Package publish safety:
  - [x] `composer package-publish-safety:gate`
  - [x] must fail if an allowlisted package contains a manual `version` field
  - [x] must fail if an allowlisted package requires internal `coretsia/*` as `dev-main`
  - [x] must fail if an allowlisted package requires internal `coretsia/*` as `*`
  - [x] must fail if an allowlisted package uses an internal `coretsia/*` exact pin without explicit policy allowance
  - [x] must fail if an allowlisted package internal constraint does not match release-line `publicConstraint`

- [x] Package public constraint sync:
  - [x] `composer release-line:public-constraints:sync`
  - [x] `composer release-line:public-constraints:check`
  - [x] must fail if any package `composer.json` internal `coretsia/*` dependency is not set to release-line `publicConstraint`
  - [x] must not modify external dependencies
  - [x] must not modify `php` or `ext-*` constraints
  - [x] must not add missing dependencies

#### Test harness / fixtures (when integration is needed)

- [x] Fixture or deterministic tool-level test SHOULD cover:
  - [x] `release-line.json` with `0.4` produces `0.4.x-dev` in workspace constraints
  - [x] `release-line.json` with `0.4` produces `^0.4.0` in package public internal constraints
  - [x] `release-line.json` with `0.5` would produce `0.5.x-dev` in workspace constraints without manual edits
  - [x] `release-line.json` with `0.5` would produce `^0.5.0` in package public internal constraints without manual edits
  - [x] invalid schemaVersion fails deterministically
  - [x] inconsistent `currentMinor` / `devVersion` / `publicConstraint` fails deterministically
  - [x] package composer name mismatch fails deterministically
  - [x] public constraints sync does not rewrite external dependencies
  - [x] public constraints sync does not add missing dependencies

### Tests (MUST)

- Gates/Arch:
  - [x] `composer sync:check`
  - [x] `composer release-line:workspace:check`
  - [x] `composer release-line:public-constraints:check`
  - [x] `composer validate:all`
  - [x] `composer package-publish-safety:gate`
  - [x] `composer ci`

- Tooling:
  - [x] `framework/tools/build/sync_composer_repositories.php --check`
  - [x] `framework/tools/release/sync_workspace_release_line.php --check`
  - [x] `framework/tools/release/sync_package_public_constraints.php --check`

### DoD (MUST)

- [x] `framework/tools/release/release-line.json` is the single tooling SSoT for:
  - [x] current release minor
  - [x] monorepo workspace dev version
  - [x] public internal package constraint
- [x] `schemaVersion` is documented as schema version, not release version.
- [x] `composer sync:repos` generates `options.versions` for all discovered packages in root/framework/skeleton managed path repositories.
- [x] `composer release-line:workspace:sync` updates internal `coretsia/*` `require-dev` constraints in `framework/composer.json`.
- [x] `composer release-line:workspace:check` fails on drift.
- [x] `composer release-line:public-constraints:sync` updates package `composer.json` internal `coretsia/*` constraints to release-line `publicConstraint`.
- [x] `composer release-line:public-constraints:check` fails on package public constraint drift.
- [x] `composer setup` applies managed repository sync, release-line workspace sync, and package public constraints sync before installs.
- [x] `composer ci` checks managed repository sync, release-line workspace sync, and package public constraints sync before installs.
- [x] `core-foundation` is prepared for split publication with:
  - [x] `coretsia/core-contracts: ^0.4.0`
  - [x] no package-local `version` field
  - [x] allowlist entry `core/foundation`
- [x] Published / allowlisted package composer metadata is Packagist-safe:
  - [x] no manual `version`
  - [x] no internal `coretsia/*: dev-main`
  - [x] no internal `coretsia/*: *`
  - [x] no internal `coretsia/*` exact pins
- [x] Local monorepo development still sees source changes immediately through path repository symlinks.
- [x] Public package versions remain tag-derived through split publishing / Packagist.
- [x] No runtime code reads release-line tooling files.
- [x] No package source depends on release-line tooling.
- [x] Allowlisted packages do not depend on non-allowlisted internal `coretsia/*` packages.
- [x] Determinism: rerun-no-diff for:
  - [x] `composer sync:repos`
  - [x] `composer release-line:workspace:sync`
  - [x] `composer release-line:public-constraints:sync`
- [x] `composer package-publish-safety:gate` is part of the normal gate chain.
- [x] CI fails if any allowlisted package is not Packagist-safe.

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
ResetOrchestrator.resetAll()   // єдиний reset trigger
endUoW()
```

## Deliverables (exact paths only) (MUST)

### Creates

- [x] `framework/packages/core/kernel/config/kernel.php` — adds `kernel.uow.attributes.*` defaults
- [x] `framework/packages/core/kernel/config/rules.php` — enforces shape
- [x] `framework/packages/core/kernel/src/Module/KernelModule.php` (runtime)
- [x] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php` (runtime)
- [x] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [x] `framework/packages/core/kernel/README.md` — includes: Observability / Errors / Security-Redaction

Runtime internals:
- [x] `framework/packages/core/kernel/src/Runtime/Internal/JsonLikeShapeNormalizer.php` — internal json-like normalizer/guard for UnitOfWork shapes; not public API
  - [x] `normalizeContextAttributes(array $attributes, int $maxDepth, int $maxKeys): array`
  - [x] `normalizeResultExtensions(array $extensions): array`
  - [x] `normalizeExportedErrorMap(array $error): array`

Context:
- [x] `framework/packages/core/kernel/src/Runtime/UnitOfWorkType.php` — enum-like: `http|cli|queue|scheduler`
- [x] `framework/packages/core/kernel/src/Runtime/UnitOfWorkContext.php` — VO `{uowId,type,startedAt,correlationId,attributes}`
  - [x] MUST validate `attributes` as json-like (float-forbidden; no objects/resources; deterministic path-safe failures)
  - [x] MUST enforce `kernel.uow.attributes.max_depth` and `kernel.uow.attributes.max_keys`
  - [x] MUST fail with `CORETSIA_UOW_CONTEXT_INVALID` using safe diagnostics only
- [x] `framework/packages/core/kernel/src/Runtime/Exception/UnitOfWorkContextInvalidException.php` — errorCode `CORETSIA_UOW_CONTEXT_INVALID`

Result + outcome:
- [x] `framework/packages/core/kernel/src/Runtime/Outcome.php` — enum-like outcome strings: `success|handled_error|fatal_error`
- [x] `framework/packages/core/kernel/src/Runtime/UnitOfWorkResult.php` — VO `{uowId,type,correlationId,startedAt,finishedAt,durationMs,outcome,error?,extensions}`
  - [x] MUST validate `extensions` as json-like (float-forbidden; no objects/resources; deterministic path-safe failures)
  - [x] MUST reject unsafe values deterministically before export to hooks/adapters/artifacts
  - [x] `extensions` MUST be json-like only
  - [x] `error?` MAY be represented internally as `Coretsia\Contracts\Observability\Errors\ErrorDescriptor` (optional)
  - [x] any exported/hook/artifact representation MUST normalize `error` to a json-like error map before crossing the kernel boundary
  - [x] MUST fail with `CORETSIA_UOW_RESULT_INVALID` using safe diagnostics only
- [x] `framework/packages/core/kernel/src/Runtime/Exception/UnitOfWorkResultInvalidException.php` — errorCode `CORETSIA_UOW_RESULT_INVALID`

Docs:
- [x] `docs/adr/ADR-0021-unit-of-work-context-shape.md`
- [x] `docs/adr/ADR-0022-unit-of-work-result-outcome-policy.md`
- [x] `docs/ssot/uow-outcome-policy.md` — (закріплено/розширено)
  - [x] lifecycle invariants: begin/after/reset exactly-once
  - [x] outcome mapping policy (HTTP/CLI) — **exact rules**:
    - [x] HTTP:
      - [x] `< 400` ⇒ `success`
      - [x] `>= 400` ⇒ `handled_error`
      - [x] uncaught exception ⇒ `fatal_error`
    - [x] CLI:
      - [x] `exitCode = 0` ⇒ `success`
      - [x] `exitCode != 0` (без uncaught exceptions) ⇒ `handled_error`
      - [x] uncaught exception ⇒ `fatal_error`
  - [x] заборона включення stacktrace/payload/PII у `result.extensions`
  - [x] (терміни) `success|handled_error|fatal_error` як стабільні токени

- [x] `docs/ssot/uow-shapes.md` — canonical shapes:
  - [x] UnitOfWorkContext fields + types:
    - [x] `uowId` : string (stable id; ULID recommended)
    - [x] `type`  : string enum `http|cli|queue|scheduler`
    - [x] `startedAt` : int (unix epoch milliseconds, UTC)
    - [x] `correlationId` : string (safe id; ULID recommended)
    - [x] `attributes` : json-like map (float-forbidden; normalized)
  - [x] UnitOfWorkResult fields + types:
    - [x] `uowId` : string
    - [x] `type`  : string
    - [x] `correlationId` : string
    - [x] `startedAt`  : int (unix epoch milliseconds, UTC) MUST match ctx.startedAt
    - [x] `finishedAt` : int (unix epoch milliseconds, UTC)
      - [x] wall-clock completion timestamp captured at end of UoW
      - [x] because wall clock is not monotonic, consumers MUST NOT rely on `finishedAt >= startedAt`
      - [x] `durationMs` is the only canonical duration source of truth
    - [x] `durationMs` : int
      - [x] canonical exported unit is integer milliseconds
      - [x] MUST be measured from the canonical monotonic timing source (`Stopwatch`)
      - [x] MUST NOT use `finishedAt - startedAt` as the source of truth for duration
      - [x] MUST be non-negative
    - [x] `outcome` : string enum `success|handled_error|fatal_error`
    - [x] `error`? :
      - [x] internal kernel representation MAY use `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`
      - [x] canonical exported shape passed to hooks/adapters/artifacts MUST be a normalized json-like error map
      - [x] no object instance MAY cross the export boundary
    - [x] export boundary rule:
      - [x] internal runtime code MAY hold `error` as `ErrorDescriptor`
      - [x] exported shapes passed to hooks/adapters/artifacts MUST carry only a normalized json-like error map
      - [x] no object instance MAY cross the export boundary
    - [x] `extensions` : json-like map (float-forbidden; normalized)
  - [x] json-like rules for attributes/extensions (float-forbidden + normalization)
  - [x] safety/redaction rules for attributes/extensions (no secrets/PII/stacktrace)
  - [x] DTO policy boundary (cemented):
    - [x] `UnitOfWorkContext` and `UnitOfWorkResult` are canonical kernel runtime shapes/VOs.
    - [x] They are NOT DTO-marker classes by default.
    - [x] DTO gates apply only to explicitly marked DTO transport classes.

### Modifies (config)

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/uow-outcome-policy.md`
  - [x] `docs/ssot/uow-shapes.md`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0021-unit-of-work-context-shape.md`
  - [x] `docs/adr/ADR-0022-unit-of-work-result-outcome-policy.md`

#### Package skeleton (if type=package)

- [x] `framework/packages/core/kernel/src/Module/KernelModule.php` (runtime)
- [x] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php` (runtime)
- [x] `framework/packages/core/kernel/config/kernel.php`  # returns subtree (no repeated root)
- [x] `framework/packages/core/kernel/config/rules.php`
- [x] `framework/packages/core/kernel/README.md`
- [x] `framework/packages/core/kernel/composer.json`

## Configuration (keys + defaults) (MUST)

- Files:
  - [x] `framework/packages/core/kernel/config/kernel.php`
- Keys (dot):
  - [x] `kernel.uow.attributes.max_depth` = 10
  - [x] `kernel.uow.attributes.max_keys`  = 200
- Rules:
  - [x] `framework/packages/core/kernel/config/rules.php` enforces shape for:
    - [x] `kernel.uow.attributes.max_depth` int>0
    - [x] `kernel.uow.attributes.max_keys`  int>0

## Cross-cutting (MUST)

### Context & UoW

- [x] `UnitOfWorkContext.attributes` MUST NOT contain:
  - [x] Authorization/Cookie/session id/tokens/raw payload/raw SQL
- [x] Allowed:
  - [x] safe ids, enums, counts, lengths, hashes
- [x] `UnitOfWorkResult.extensions` MUST NOT contain:
  - [x] stacktrace, raw payload, PII, tokens

### Observability (policy-compliant)

- [x] Policy link:
  - [x] metrics labels use `operation=uow_type`, `outcome` (терміни закріплені; реалізація — в runtimes)

### Errors

- [x] Exceptions introduced:
  - [x] `UnitOfWorkContextInvalidException` — `CORETSIA_UOW_CONTEXT_INVALID`
  - [x] `UnitOfWorkResultInvalidException` — `CORETSIA_UOW_RESULT_INVALID`
- [x] `ErrorDescriptor` in result:
  - [x] optional, format-neutral, extensions json-like only

### Security / Redaction

- [x] MUST NOT leak via ctx.attributes / result.extensions:
  - [x] tokens/session/cookies/raw payload/raw SQL/stacktrace/PII
- [x] Allowed:
  - [x] safe meta only

### Phase 0 parity: json-like policy (MUST)

- [x] Цей епік MUST бути сумісним із зацементованими json-like інваріантами з PHASE 0 SPIKES:
  - [x] 0.70.0 (PayloadNormalizer + StableJsonEncoder + float-forbidden policy)
  - [x] 0.20.0 (output-free бізнес-логіка; deterministic errors; no secrets/PII)

#### Json-like definition (single-choice; cemented)

- [x] `UnitOfWorkContext.attributes` і `UnitOfWorkResult.extensions` MUST бути **json-like**:
  - [x] Allowed scalars: `null|bool|int|string`
  - [x] Forbidden scalars: `float` (включно `NaN`, `INF`, `-INF`)
  - [x] Allowed containers: `array` тільки як:
    - [x] list (`array_is_list($value) === true`) — порядок елементів зберігається
    - [x] map  (`array_is_list($value) === false`) — ключі тільки `string` (no int keys)
- [x] Objects/Resources/Closures/Enums (як objects) — FORBIDDEN.

#### Normalization invariant for exported shapes (single-choice)

- [x] Коли shape експортується назовні kernel (hooks / adapters / artifacts), він MUST бути нормалізований детерміновано:
  - [x] maps: ключі сортуються **на кожному рівні** за byte-order (`strcmp`)
  - [x] lists: порядок **не змінюється**
  - [x] locale MUST NOT впливати (no `setlocale`, no `LC_ALL` reliance)

#### Float/NaN/INF rejection (cemented)

- [x] Якщо в `attributes` або `extensions` знайдено `float|NaN|INF`:
  - [x] MUST бути детермінована відмова (без друку значень)
  - [x] diagnostics MAY містити тільки *path-to-value* (наприклад `a.b[3].c`) і MUST NOT містити raw value

#### Safety / Redaction parity

- [x] `attributes/extensions` MUST NOT містити:
  - [x] tokens/session/cookies/Authorization
  - [x] raw payload/raw SQL
  - [x] stacktrace/PII
- [x] Allowed: safe ids/enums/counts/lengths/hashes.

## Verification (TEST EVIDENCE) (MUST when applicable)

### Contract / snapshot locks

- [x] Context shape lock:
  - [x] `framework/packages/core/kernel/tests/Contract/UnitOfWorkContextShapeContractTest.php`
- [x] Kernel config subtree shape lock:
  - [x] `framework/packages/core/kernel/tests/Contract/KernelConfigSubtreeShapeContractTest.php`
- [x] Context attributes json-like + limits:
  - [x] `framework/packages/core/kernel/tests/Contract/UnitOfWorkContextAttributesAreJsonLikeContractTest.php`
    - [x] Перевірити, що context attributes reject keys:
      - [x] `authorization`, `cookie`, `cookies`, `session`, `sessionId`, `session_id`, `token`, `tokens`, `accessToken`, `access_token`, `refreshToken`, `refresh_token`, `password`, `secret`, `credential`, `credentials`, `raw`, `rawBody`, `rawPayload`, `payload`, `rawSql`, `sql`, `stacktrace`, `stackTrace`, `trace`, `email`, `phone`, `username`, `fullName`, `userId`, `tenantId`
      - [x] Очікування:
        - [x] `UnitOfWorkContextInvalidException`
        - [x] `ERROR_CODE === CORETSIA_UOW_CONTEXT_INVALID`
        - [x] `reason === uow-context-attributes-unsafe-key-forbidden`
- [x] Result shape lock:
  - [x] `framework/packages/core/kernel/tests/Contract/UnitOfWorkResultShapeContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/UnitOfWorkResultExtensionsAreJsonLikeContractTest.php`
    - [x] Перевірити, що context attributes reject keys:
      - [x] `authorization`, `cookie`, `cookies`, `session`, `sessionId`, `session_id`, `token`, `tokens`, `accessToken`, `access_token`, `refreshToken`, `refresh_token`, `password`, `secret`, `credential`, `credentials`, `raw`, `rawBody`, `rawPayload`, `payload`, `rawSql`, `sql`, `stacktrace`, `stackTrace`, `trace`, `email`, `phone`, `username`, `fullName`, `userId`, `tenantId`
      - [x] Очікування:
        - [x] `UnitOfWorkResultInvalidException`
        - [x] `ERROR_CODE === CORETSIA_UOW_RESULT_INVALID`
        - [x] `reason === uow-result-extensions-unsafe-key-forbidden`
    - [x] asserts `extensions` reject `float|NaN|INF|-INF`
    - [x] asserts `extensions` reject objects/resources
    - [x] asserts diagnostics are safe and contain no raw values
    - [x] asserts failures use `CORETSIA_UOW_RESULT_INVALID`
- [x] Outcome mapping stability snapshot (policy lock):
  - [x] `framework/packages/core/kernel/tests/Contract/OutcomeMappingStabilityContractTest.php`
  - [x] MUST check:
    - [x] HTTP status `200` => `success`
    - [x] HTTP status `399` => `success`
    - [x] HTTP status `400` => `handled_error`
    - [x] HTTP status `500` => `handled_error`
    - [x] CLI exit code `0` => `success`
    - [x] CLI exit code `2` => `handled_error`
    - [x] uncaught exception => `fatal_error`

> NOTE: `OutcomeMappingStabilityContractTest` є **єдиним** контрактом, що цементує правила з `docs/ssot/uow-outcome-policy.md`.

## Tests (MUST)

Contract:
- [x] `framework/packages/core/kernel/tests/Contract/UnitOfWorkContextShapeContractTest.php`
- [x] `framework/packages/core/kernel/tests/Contract/UnitOfWorkContextAttributesAreJsonLikeContractTest.php`
- [x] `framework/packages/core/kernel/tests/Contract/UnitOfWorkResultShapeContractTest.php`
- [x] `framework/packages/core/kernel/tests/Contract/UnitOfWorkResultExtensionsAreJsonLikeContractTest.php`
- [x] `framework/packages/core/kernel/tests/Contract/OutcomeMappingStabilityContractTest.php`
- [x] `framework/packages/core/kernel/tests/Contract/KernelConfigSubtreeShapeContractTest.php`
  - [x] MUST fail if `framework/packages/core/kernel/config/kernel.php` returns repeated root:
    - [x] ✅ subtree only
    - [x] ❌ `['kernel' => [...]]`
  - [x] MUST fail if any `@*` key exists under returned subtree (any depth)
  - [x] additive namespaces introduced by later kernel epics are allowed

## DoD (MUST)

- [x] Context shape stable + contract-tested
- [x] Result/outcome shape stable + contract-tested
- [x] Outcome tokens stable: `success|handled_error|fatal_error`
- [x] Attributes guard is implemented and prevents non-json-like values / overflows deterministically
- [x] No PSR-7/15 leakage
- [x] `docs/ssot/uow-outcome-policy.md` відповідає реальним правилам, які зафіксовані `OutcomeMappingStabilityContractTest`
- [x] Нема дублювання “як саме” у коді — лише правила/інваріанти в SSoT; runtimes реалізують mapping.
- [x] Non-goals / out of scope:
  - [x] Kernel не будує HTTP response і не визначає статус-коди (це adapters/platform-layer).
  - [x] Kernel не логує stacktrace у `result.extensions`.
- [x] Adapters invariants:
  - [x] `platform/http` може додавати http-specific attributes (safe only), але **shape залишається kernel-owned**
  - [x] `platform/cli` може додавати cli-specific attributes (safe only), але **shape залишається kernel-owned**
- [x] When a UoW is started, then it contains `uowId`, `type`, `startedAt`, `correlationId`, and json-like `attributes`.
- [x] When a CLI command returns exit code 2 without uncaught exceptions, then the UoW outcome is `handled_error`.
- [x] `UnitOfWorkContext` and `UnitOfWorkResult` are kernel-owned runtime shapes/VOs, not DTO-marker classes by default

---

### 1.275.0 Foundation: Json-like Runtime Value Normalization Primitive (MUST) [IMPL]

---
type: package
phase: 1
epic_id: "1.275.0"
owner_path: "framework/packages/core/foundation"

package_id: "core/foundation"
composer: "coretsia/core-foundation"
kind: runtime
module_id: "core.foundation"

goal: "Centralize baseline runtime json-like value validation and deterministic normalization in core/foundation so foundation and kernel no longer duplicate scalar, array, float, object, resource, and stable ordering policy."
provides:
- "Canonical runtime json-like value normalizer for foundation and higher runtime packages"
- "Path-aware safe normalization failures with stable reason tokens"
- "Shared foundation primitive used by stable JSON encoding and context safe-write validation"
- "Kernel UoW json-like shape normalization delegated through a foundation-owned primitive while keeping UoW-specific policy in kernel"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: docs/adr/ADR-0004-foundation-json-like-runtime-values.md
ssot_refs:
- docs/ssot/json-like-runtime-values.md
- docs/ssot/uow-shapes.md
- docs/ssot/context-store.md
- docs/ssot/observability-and-errors.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.200.0 — Foundation DI/tags/reset/stable diagnostics baseline already provides `Coretsia\Foundation\Serialization\StableJsonEncoder` and runtime package boundaries.
  - 1.210.0 — Foundation context/correlation/id/time baseline already provides runtime context services and context value safety policy.
  - 1.270.0 — Kernel UnitOfWork shapes already provide `UnitOfWorkContext`, `UnitOfWorkResult`, and the current kernel-local `JsonLikeShapeNormalizer` that this epic refactors.

- Required deliverables (exact paths):
  - `framework/packages/core/foundation/src/Serialization/StableJsonEncoder.php` — existing foundation stable JSON encoder that MUST delegate baseline json-like normalization to the new primitive.
  - `framework/packages/core/foundation/src/Context/ContextStorePolicy.php` — existing foundation context write guard that MUST delegate value-shape validation to the new primitive.
  - `framework/packages/core/kernel/src/Runtime/Internal/JsonLikeShapeNormalizer.php` — existing kernel UoW shape wrapper that MUST retain only UoW-specific policy and delegate baseline normalization to foundation.
  - `framework/packages/core/kernel/src/Runtime/UnitOfWorkContext.php` — existing context shape that MUST continue to use the kernel wrapper.
  - `framework/packages/core/kernel/src/Runtime/UnitOfWorkResult.php` — existing result shape that MUST continue to use the kernel wrapper.
  - `docs/ssot/uow-shapes.md` — existing Kernel UoW shape SSoT that MUST be updated to reference the foundation-owned baseline json-like policy.

- Required config roots/keys:
  - `foundation` — existing Foundation config root; this epic introduces no new Foundation config keys.
  - `kernel.uow.attributes.max_depth` — existing Kernel UoW attributes defensive depth limit consumed by the kernel wrapper.
  - `kernel.uow.attributes.max_keys` — existing Kernel UoW attributes defensive key-count limit consumed by the kernel wrapper.

- Required tags:
  - N/A

- Required contracts / ports:
  - N/A

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `psr/clock`
- `psr/container`
- `psr/log`

Forbidden:

- `platform/*`
- `integrations/*`
- `devtools/*`
- `tools/*`
- `framework/tools/spikes/*`
- `core/kernel` from `core/foundation`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - N/A
- Contracts:
  - N/A
- Foundation APIs introduced by this epic:
  - `Coretsia\Foundation\Serialization\JsonLikeNormalizer`
  - `Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException`

### Entry points / integration points (MUST)

N/A

### Deliverables (MUST)

#### Creates

- [x] `framework/packages/core/foundation/src/Serialization/JsonLikeNormalizer.php`
  - [x] Create `Coretsia\Foundation\Serialization\JsonLikeNormalizer` as the canonical foundation-owned runtime json-like value normalizer.
  - [x] Implement `public static function normalize(mixed $value, string $path = 'value'): mixed`.
  - [x] Allow only `null`, `bool`, `int`, `string`, `list<value>`, and `array<string,value>`.
  - [x] Reject every `float`, including finite floats, `NAN`, `INF`, and `-INF`.
  - [x] Reject objects, including enum objects and stringable objects.
  - [x] Reject `Closure` before generic object rejection so closure diagnostics stay stable.
  - [x] Reject resources and unsupported PHP value types.
  - [x] Treat `array_is_list($value) === true` as list semantics and preserve caller-supplied list order.
  - [x] Treat `array_is_list($value) === false` as map semantics and require every map key to be a string.
  - [x] Sort every map recursively by byte-order `strcmp`.
  - [x] Preserve empty array as `[]`.
  - [x] Build path notation using `.key` for map keys and `[index]` for list indexes.
  - [x] Throw `JsonLikeNormalizationException` with safe path and stable reason token only.
  - [x] MUST NOT include raw values, object class names, resource ids, secrets, raw payloads, raw SQL, local paths, or environment-specific bytes in exception messages.
  - [x] MUST NOT depend on `devtools/internal-toolkit` or any spike package.
  - [x] Build diagnostic paths using safe key segments only.
  - [x] Use raw `.key` notation only for conservative safe keys, for example `/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/`.
  - [x] Use stable placeholders such as `[<key>]` or `[<empty-key>]` for unsafe, empty, long, control-character, whitespace, URL-like, SQL-like, or secret-like map keys.
  - [x] Diagnostic paths MUST NOT leak raw map keys when the key itself is unsafe.

- [x] `framework/packages/core/foundation/src/Serialization/Exception/JsonLikeNormalizationException.php`
  - [x] Create `Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException`.
  - [x] Extend `\InvalidArgumentException`.
  - [x] Define `public const string ERROR_CODE = 'CORETSIA_JSON_LIKE_INVALID'`.
  - [x] Implement `public static function atPath(string $path, string $reason): self`.
  - [x] Implement `public function errorCode(): string`.
  - [x] Implement `public function path(): string`.
  - [x] Implement `public function reason(): string`.
  - [x] Message MUST contain only `ERROR_CODE`, safe path, and stable reason.
  - [x] Message MUST NOT contain raw normalized/rejected values.
  - [x] Reason tokens MUST include:
    - [x] `json-like-float-forbidden`
    - [x] `json-like-resource-forbidden`
    - [x] `json-like-closure-forbidden`
    - [x] `json-like-object-forbidden`
    - [x] `json-like-map-key-must-be-string`
    - [x] `json-like-type-forbidden`

- [x] `framework/packages/core/foundation/tests/Contract/JsonLikeNormalizerContractTest.php`
  - [x] Assert scalar acceptance for `null`, `bool`, `int`, and `string`.
  - [x] Assert finite float rejection with reason `json-like-float-forbidden`.
  - [x] Assert `NAN` rejection with reason `json-like-float-forbidden`.
  - [x] Assert `INF` rejection with reason `json-like-float-forbidden`.
  - [x] Assert `-INF` rejection with reason `json-like-float-forbidden`.
  - [x] Assert object rejection with reason `json-like-object-forbidden`.
  - [x] Assert enum object rejection with reason `json-like-object-forbidden` when test fixture enum is available.
  - [x] Assert `Closure` rejection with reason `json-like-closure-forbidden`.
  - [x] Assert resource rejection with reason `json-like-resource-forbidden`.
  - [x] Assert non-string map key rejection with reason `json-like-map-key-must-be-string`.
  - [x] Assert unsupported value type rejection with reason `json-like-type-forbidden` when a PHP value type can be represented safely in test.
  - [x] Assert recursive map key sorting by byte-order `strcmp`.
  - [x] Assert list order is preserved.
  - [x] Assert nested lists preserve list order while nested maps are sorted.
  - [x] Assert empty array remains `[]`.
  - [x] Assert exception exposes `errorCode()`, `path()`, and `reason()`.
  - [x] Assert failure messages do not leak raw values, secrets, SQL fragments, object class names, resource metadata, or absolute local paths.
  - [x] Assert unsafe map keys are not leaked in diagnostic `path()`.
  - [x] Assert keys containing tokens, SQL fragments, control chars, URLs, or absolute paths are replaced with safe placeholders in failure diagnostics.

- [x] `framework/packages/core/foundation/tests/Contract/StableJsonEncoderUsesJsonLikeNormalizerContractTest.php`
  - [x] Assert `StableJsonEncoder` output remains deterministic for valid json-like values.
  - [x] Assert recursive map ordering remains `strcmp` based.
  - [x] Assert list order remains preserved.
  - [x] Assert final LF remains present.
  - [x] Assert floats fail through the foundation json-like policy.
  - [x] Assert object, closure, resource, and non-string map key failures are path-aware.
  - [x] Assert `StableJsonEncoder` failure messages do not leak raw values.

- [x] `framework/packages/core/foundation/tests/Contract/ContextStorePolicyUsesJsonLikeNormalizerContractTest.php`
  - [x] Assert `ContextStorePolicy::assertValue()` delegates json-like validation through the foundation normalizer.
  - [x] Assert existing context reason tokens are preserved after exception mapping.
  - [x] Assert path is preserved from nested invalid values.
  - [x] Assert raw rejected values do not leak into `ContextWriteForbiddenException` messages.
  - [x] Assert `ContextStorePolicy` still validates only values and does not normalize stored context values as a side effect.

- [x] `framework/packages/core/kernel/tests/Contract/KernelJsonLikePolicyMatchesFoundationContractTest.php`
  - [x] Assert valid context attributes normalize to the same baseline recursive shape as `JsonLikeNormalizer`.
  - [x] Assert valid result extensions normalize to the same baseline recursive shape as `JsonLikeNormalizer`.
  - [x] Assert kernel still rejects root lists for `attributes`.
  - [x] Assert kernel still rejects root lists for `extensions`.
  - [x] Assert kernel still rejects unsafe metadata keys.
  - [x] Assert kernel still applies `attributesMaxDepth`.
  - [x] Assert kernel still applies `attributesMaxKeys`.
  - [x] Assert foundation float violations map to `UnitOfWorkContextInvalidException` / `UnitOfWorkResultInvalidException` reason tokens.
  - [x] Assert foundation object, closure, resource, map-key, and type violations map to UoW-specific reason tokens.
  - [x] Assert kernel UoW exceptions do not leak raw values from foundation-level failures.

- [x] `docs/ssot/json-like-runtime-values.md`
  - [x] Create the canonical SSoT for runtime json-like value validation and deterministic normalization.
  - [x] Declare owner package: `core/foundation`.
  - [x] Declare canonical implementation: `framework/packages/core/foundation/src/Serialization/JsonLikeNormalizer.php`.
  - [x] Declare canonical exception: `framework/packages/core/foundation/src/Serialization/Exception/JsonLikeNormalizationException.php`.
  - [x] Declare consumers:
    - [x] `Coretsia\Foundation\Serialization\StableJsonEncoder`
    - [x] `Coretsia\Foundation\Context\ContextStorePolicy`
    - [x] `Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer`
  - [x] Define allowed scalar values: `null`, `bool`, `int`, `string`.
  - [x] Define forbidden scalar values: finite float, `NAN`, `INF`, `-INF`.
  - [x] Define forbidden values: object, enum object, closure, resource, unsupported PHP value types.
  - [x] Define list semantics: preserve order.
  - [x] Define map semantics: string keys only and recursive `strcmp` ordering.
  - [x] Define empty array policy: preserve as `[]`.
  - [x] Define diagnostics policy: safe path and stable reason only.
  - [x] Define explicit non-goals:
    - [x] no generic redaction engine
    - [x] no unsafe metadata key denylist in foundation
    - [x] no UoW root map policy in foundation
    - [x] no transport/request payload semantics
    - [x] no dependency on devtools/tooling/spikes

- [x] `docs/adr/ADR-0004-foundation-json-like-runtime-values.md`
  - [x] Record the decision that `core/foundation` owns the reusable runtime json-like value normalizer.
  - [x] Record the decision that `core/kernel` MUST NOT duplicate baseline json-like normalization.
  - [x] Record the decision that `core/kernel` keeps UoW-specific root map, unsafe-key, limit, and exception mapping policy.
  - [x] Record the decision that `core/contracts` remains unchanged.
  - [x] Record the decision that `devtools/internal-toolkit` remains tooling-only and MUST NOT be used by runtime packages.
  - [x] Record rejected alternative: direct copy from `framework/tools/spikes/payload/*`.
  - [x] Record rejected alternative: moving json-like normalization to `core/contracts`.
  - [x] Record rejected alternative: keeping duplicated foundation/kernel recursive walkers.

#### Modifies

- [x] `docs/ssot/context-store.md`
  - [x] Add cross-reference to `docs/ssot/json-like-runtime-values.md`.
  - [x] Clarify that baseline json-like value validation and deterministic normalization are owned by `Coretsia\Foundation\Serialization\JsonLikeNormalizer`.
  - [x] Clarify that `ContextStorePolicy` owns context-specific key allowlist, `@*` reserved key rejection, and context exception mapping.
  - [x] Keep the context safe-write security rules unchanged.
  - [x] Avoid conflicting ownership language where `context-store.md` appears to own the reusable baseline json-like model.

- [x] `framework/packages/core/foundation/src/Serialization/StableJsonEncoder.php`
  - [x] Replace the private recursive `normalize()` implementation with `JsonLikeNormalizer::normalize($value, 'value')`.
  - [x] MUST map `JsonLikeNormalizationException` reasons to stable-json reason tokens:
    - [x] `json-like-float-forbidden` → `stable-json-float-forbidden`
    - [x] `json-like-resource-forbidden` → `stable-json-resource-forbidden`
    - [x] `json-like-closure-forbidden` → `stable-json-closure-forbidden`
    - [x] `json-like-object-forbidden` → `stable-json-object-forbidden`
    - [x] `json-like-map-key-must-be-string` → `stable-json-map-key-must-be-string`
    - [x] `json-like-type-forbidden` → `stable-json-type-forbidden`
  - [x] Preserve `stable-json-encode-failed` for `json_encode()` failures.
  - [x] Preserve existing JSON flags:
    - [x] `JSON_UNESCAPED_SLASHES`
    - [x] `JSON_UNESCAPED_UNICODE`
    - [x] `JSON_THROW_ON_ERROR`
  - [x] Preserve final LF behavior.
  - [x] Preserve deterministic byte output for valid payloads.
  - [x] Ensure failures are path-aware.
  - [x] Ensure failures do not leak raw values.
  - [x] Remove duplicated baseline scalar, array, float, object, closure, resource, and map-key validation logic from this class.

- [x] `framework/packages/core/foundation/src/Context/ContextStorePolicy.php`
  - [x] Replace recursive json-like value walking with `JsonLikeNormalizer::normalize($value, $path)` inside `assertValue()`.
  - [x] Preserve key policy in `assertKey()`:
    - [x] empty key rejection
    - [x] `@*` reserved key rejection
    - [x] unknown `ContextKeys` rejection
  - [x] Map `JsonLikeNormalizationException` reasons to existing context write reason tokens:
    - [x] `json-like-float-forbidden` → `context-write-forbidden-float`
    - [x] `json-like-closure-forbidden` → `context-write-forbidden-closure`
    - [x] `json-like-object-forbidden` → `context-write-forbidden-object`
    - [x] `json-like-resource-forbidden` → `context-write-forbidden-resource`
    - [x] `json-like-map-key-must-be-string` → `context-write-forbidden-map-key`
    - [x] `json-like-type-forbidden` → `context-write-forbidden-type`
  - [x] Preserve nested path information in mapped `ContextWriteForbiddenException`.
  - [x] Remove duplicated recursive array walker from this class.
  - [x] Do not store or return the normalized value from `ContextStorePolicy`; it remains a validation boundary only.

- [x] `framework/packages/core/kernel/src/Runtime/Internal/JsonLikeShapeNormalizer.php`
  - [x] Keep the class internal to `core/kernel`.
  - [x] Keep public static methods used by `UnitOfWorkContext` and `UnitOfWorkResult`:
    - [x] `normalizeContextAttributes()`
    - [x] `normalizeResultExtensions()`
    - [x] `normalizeExportedErrorMap()`
  - [x] Delegate baseline recursive normalization to `Coretsia\Foundation\Serialization\JsonLikeNormalizer`.
  - [x] Preserve context root map policy for `attributes`.
  - [x] Preserve result root map policy for `extensions`.
  - [x] Preserve non-empty root map policy for exported `error`.
  - [x] Preserve `attributesMaxDepth` validation.
  - [x] Preserve `attributesMaxKeys` validation.
  - [x] Preserve unsafe metadata key denylist in kernel only.
  - [x] Preserve safe string / safe single-line string checks where required by UoW policy.
  - [x] Map foundation reason tokens to context reasons:
    - [x] `json-like-float-forbidden` → `uow-context-attributes-float-forbidden`
    - [x] `json-like-object-forbidden` → `uow-context-attributes-object-forbidden`
    - [x] `json-like-closure-forbidden` → `uow-context-attributes-closure-forbidden`
    - [x] `json-like-resource-forbidden` → `uow-context-attributes-resource-forbidden`
    - [x] `json-like-map-key-must-be-string` → `uow-context-attributes-map-key-must-be-string`
    - [x] `json-like-type-forbidden` → `uow-context-attributes-type-forbidden`
  - [x] Map foundation reason tokens to result reasons:
    - [x] `json-like-float-forbidden` → `uow-result-float-forbidden`
    - [x] `json-like-object-forbidden` → `uow-result-object-forbidden`
    - [x] `json-like-closure-forbidden` → `uow-result-closure-forbidden`
    - [x] `json-like-resource-forbidden` → `uow-result-resource-forbidden`
    - [x] `json-like-map-key-must-be-string` → `uow-result-map-key-must-be-string`
    - [x] `json-like-type-forbidden` → `uow-result-type-forbidden`
  - [x] Remove duplicated baseline scalar/object/resource/float recursive policy from kernel except where required for UoW-specific exception mapping.
  - [x] Ensure no raw rejected values leak in UoW exceptions.

- [x] `framework/packages/core/kernel/src/Runtime/UnitOfWorkContext.php`
  - [x] Keep the public API unchanged.
  - [x] Keep usage of `JsonLikeShapeNormalizer::normalizeContextAttributes()`.
  - [x] Update only PHPDoc/type annotations if required by the refactored normalizer return type.
  - [x] Do not expose `JsonLikeNormalizer` directly from `UnitOfWorkContext`.

- [x] `framework/packages/core/kernel/src/Runtime/UnitOfWorkResult.php`
  - [x] Keep the public API unchanged.
  - [x] Keep usage of `JsonLikeShapeNormalizer::normalizeResultExtensions()`.
  - [x] Keep usage of `JsonLikeShapeNormalizer::normalizeExportedErrorMap()`.
  - [x] Update only PHPDoc/type annotations if required by the refactored normalizer return type.
  - [x] Do not expose `JsonLikeNormalizer` directly from `UnitOfWorkResult`.

- [x] `framework/packages/core/foundation/README.md`
  - [x] Add a `Json-like runtime values` section.
  - [x] Document `Coretsia\Foundation\Serialization\JsonLikeNormalizer` as the canonical runtime json-like value normalizer.
  - [x] Document that `StableJsonEncoder` uses `JsonLikeNormalizer`.
  - [x] Document that `ContextStorePolicy` uses `JsonLikeNormalizer` for value validation.
  - [x] Document that kernel may consume the normalizer through its own domain-specific wrapper.
  - [x] Reaffirm that foundation does not introduce UoW-specific policy, unsafe metadata key denylist, transport payload semantics, or generic redaction.

- [x] `framework/packages/core/kernel/README.md`
  - [x] Document that Kernel UoW shapes use the foundation-owned baseline json-like policy through `JsonLikeShapeNormalizer`.
  - [x] Document that kernel remains the owner of UoW root map policy, unsafe metadata key policy, attributes limits, and UoW exception mapping.
  - [x] Document that `JsonLikeShapeNormalizer` remains internal and is not public Kernel API.

- [x] `docs/adr/INDEX.md`
  - [x] Register `docs/adr/ADR-0004-foundation-json-like-runtime-values.md` exactly once.
  - [x] Insert the ADR entry in deterministic `relative-path` / `strcmp` order.
  - [x] Use owner `core/foundation`.
  - [x] Use scope tokens such as `foundation,json-like,normalization,runtime,serialization`.
  - [x] Do not add dates, timestamps, or unstable metadata.

- [x] `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
  - [x] Add a short note that the baseline json-like value validation and recursive deterministic normalization used by `StableJsonEncoder` is now owned by `Coretsia\Foundation\Serialization\JsonLikeNormalizer`.
  - [x] Preserve ADR-0014 stable JSON encoder behavior: JSON flags, final LF, recursive `strcmp` map ordering, list order preservation, float/object/resource/closure rejection.
  - [x] Do not move UoW-specific policy, unsafe metadata key policy, or transport payload semantics into ADR-0014.
  - [x] Cross-reference `docs/adr/ADR-0004-foundation-json-like-runtime-values.md`.

- [x] `docs/ssot/INDEX.md`
  - [x] Add `docs/ssot/json-like-runtime-values.md` to the SSoT index.
  - [x] Ensure the entry identifies `core/foundation` as owner.
  - [x] Ensure the entry links to related UoW shape policy.

- [x] `docs/ssot/uow-shapes.md`
  - [x] Replace language that implies Kernel owns the baseline json-like value model.
  - [x] State that `Coretsia\Foundation\Serialization\JsonLikeNormalizer` owns baseline json-like validation and deterministic normalization.
  - [x] State that `Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer` owns UoW root map policy, unsafe metadata key policy, limits, and exception mapping.
  - [x] Keep UoW-specific rules unchanged:
    - [x] `attributes` root map policy
    - [x] `extensions` root map policy
    - [x] exported `error` map policy
    - [x] unsafe metadata keys
    - [x] `attributesMaxDepth`
    - [x] `attributesMaxKeys`
    - [x] UoW exception codes and reason tokens
  - [x] Add cross-reference to `docs/ssot/json-like-runtime-values.md`.

- [x] `framework/packages/core/kernel/tests/Contract/UnitOfWorkContextAttributesAreJsonLikeContractTest.php`
  - [x] Keep existing behavior expectations green.
  - [x] Adjust only if reason/path details change due to foundation delegation.
  - [x] Preserve assertions for float, `NAN`, `INF`, `-INF`, object, closure, resource, unsafe keys, depth, key count, and safe diagnostics.
  - [x] Preserve no-raw-value-leak assertions.

- [x] `framework/packages/core/kernel/tests/Contract/UnitOfWorkResultExtensionsAreJsonLikeContractTest.php`
  - [x] Keep existing behavior expectations green.
  - [x] Adjust only if reason/path details change due to foundation delegation.
  - [x] Preserve assertions for recursive sorting, list preservation, root map rejection, float/object/closure/resource rejection, unsafe keys, and safe diagnostics.
  - [x] Preserve no-raw-value-leak assertions.

#### Package skeleton (if type=package)

- N/A — this epic modifies the existing `core/foundation` runtime package and does not create a new package skeleton.

#### Configuration (keys + defaults)

- N/A

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A — this epic introduces no tags.
- ServiceProvider wiring evidence:
  - N/A — `JsonLikeNormalizer` is a static low-level runtime primitive and is not registered as a DI service in this epic.
- [x] Compliance delta:
  - [x] this package MUST NOT introduce owner constants for tags it does not own.
  - [x] runtime code MUST NOT use raw literal tag strings for this epic because no tags are involved.
  - [x] non-owner packages MUST NOT define competing tag semantics as part of this epic.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads:
  - N/A — this epic does not add context reads.
- Context writes (safe only):
  - N/A — this epic does not add new context writes.
- [x] Context write validation:
  - [x] `ContextStorePolicy` MUST validate context values through `JsonLikeNormalizer`.
  - [x] `ContextStorePolicy` MUST preserve existing context key validation.
  - [x] `ContextStorePolicy` MUST preserve existing context reason tokens.
  - [x] Context validation failures MUST NOT leak raw values.
- [x] UoW shape normalization:
  - [x] `JsonLikeShapeNormalizer` MUST delegate baseline json-like normalization to foundation.
  - [x] `JsonLikeShapeNormalizer` MUST preserve UoW-specific root map, unsafe-key, depth/key-limit, safe-string, and exception-mapping policy.
- Reset discipline:
  - N/A — this epic introduces no stateful services and no reset tags.

#### Observability (policy-compliant)

N/A

#### Errors

- [x] Exceptions introduced:
  - [x] `Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException` — errorCode `CORETSIA_JSON_LIKE_INVALID`
- [x] Mapping:
  - [x] No `error.mapper` integration is introduced.
  - [x] Foundation json-like failures are mapped locally by `StableJsonEncoder` to stable-json reason tokens.
  - [x] Foundation json-like failures are mapped locally by `ContextStorePolicy` to existing context write exceptions.
  - [x] Foundation json-like failures are mapped locally by Kernel `JsonLikeShapeNormalizer` to existing UoW exceptions.

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] auth values
  - [x] cookies
  - [x] session ids
  - [x] tokens
  - [x] credentials
  - [x] passwords
  - [x] raw SQL
  - [x] raw payloads
  - [x] rejected raw scalar values
  - [x] object class names
  - [x] resource ids
  - [x] local absolute paths
  - [x] environment-specific bytes
- [x] Allowed:
  - [x] safe structural path
  - [x] stable reason token
  - [x] package-owned error code

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [x] Json-like failure safety:
  - [x] `framework/packages/core/foundation/tests/Contract/JsonLikeNormalizerContractTest.php` asserts safe diagnostics and no raw value leakage.
  - [x] `framework/packages/core/foundation/tests/Contract/StableJsonEncoderUsesJsonLikeNormalizerContractTest.php` asserts encoder failures remain safe and path-aware.
  - [x] `framework/packages/core/foundation/tests/Contract/ContextStorePolicyUsesJsonLikeNormalizerContractTest.php` asserts context exception mapping remains safe.
  - [x] `framework/packages/core/kernel/tests/Contract/KernelJsonLikePolicyMatchesFoundationContractTest.php` asserts kernel exception mapping remains safe.

#### Test harness / fixtures (when integration is needed)

- N/A

### Tests (MUST)

- Unit:
  - N/A — behavior is locked through package contract tests.
- Contract:
  - [x] `framework/packages/core/foundation/tests/Contract/JsonLikeNormalizerContractTest.php`
  - [x] `framework/packages/core/foundation/tests/Contract/StableJsonEncoderUsesJsonLikeNormalizerContractTest.php`
  - [x] `framework/packages/core/foundation/tests/Contract/ContextStorePolicyUsesJsonLikeNormalizerContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/KernelJsonLikePolicyMatchesFoundationContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/UnitOfWorkContextAttributesAreJsonLikeContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/UnitOfWorkResultExtensionsAreJsonLikeContractTest.php`
- Integration:
  - N/A
- Gates/Arch:
  - [x] deptrac remains green: `core/foundation` MUST NOT depend on `core/kernel`, `devtools/*`, `tools/*`, `platform/*`, or `integrations/*`.
  - [x] public API gates remain green: Kernel internal normalizer remains internal and is not added to `framework/packages/core/kernel/PUBLIC_API.md`.
  - [x] package compliance gates remain green: no new tags, config roots, artifacts, or forbidden runtime dependencies.

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact.
- [x] Preconditions satisfied; no forward references introduced.
- [x] `core/foundation` owns `JsonLikeNormalizer` and `JsonLikeNormalizationException`.
- [x] `core/contracts` remains unchanged.
- [x] `core/foundation` does not depend on `core/kernel`.
- [x] Runtime packages do not depend on `devtools/internal-toolkit` or `framework/tools/spikes/*`.
- [x] `StableJsonEncoder` delegates baseline normalization to `JsonLikeNormalizer`.
- [x] `ContextStorePolicy` delegates value-shape validation to `JsonLikeNormalizer`.
- [x] Kernel `JsonLikeShapeNormalizer` delegates baseline normalization to `JsonLikeNormalizer`.
- [x] Kernel keeps UoW-specific root map, unsafe-key, max-depth, max-keys, safe-string, and exception-mapping policy.
- [x] No copy-paste from `framework/tools/spikes/payload/*`.
- [x] No public Kernel normalizer introduced.
- [x] No transport/request payload semantics introduced.
- [x] No generic redaction engine introduced in foundation.
- [x] No unsafe metadata key denylist introduced in foundation.
- [x] Verification tests present where applicable.
- [x] Determinism preserved: recursive map ordering uses `strcmp`; lists preserve caller order.
- [x] Stable JSON output remains LF-terminated.
- [x] Safe diagnostics preserved: no raw values, secrets, SQL, object internals, resource ids, local paths, or environment-specific bytes in failures.
- [x] Docs updated:
  - [x] `docs/ssot/json-like-runtime-values.md`
  - [x] `docs/ssot/INDEX.md`
  - [x] `docs/ssot/uow-shapes.md`
  - [x] `docs/ssot/context-store.md`
  - [x] `docs/adr/INDEX.md`
  - [x] `docs/adr/ADR-0004-foundation-json-like-runtime-values.md`
  - [x] `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
  - [x] `framework/packages/core/foundation/README.md`
  - [x] `framework/packages/core/kernel/README.md`

---

### 1.277.0 Foundation: Runtime Failure Safety Hardening (MUST) [IMPL]

---
type: package
phase: 1
epic_id: "1.277.0"
owner_path: "framework/packages/core/foundation"

package_id: "core/foundation"
composer: "coretsia/core-foundation"
kind: runtime
module_id: "core.foundation"

goal: "Harden Foundation runtime failure and diagnostics boundaries so reset observability, context key/write rejection, correlation id reads, and container diagnostics cannot leak unsafe raw runtime values."
provides:
- "Sanitized reset failure exception copies for observability recording"
- "Consistent ResetException accessors compatible with runtime exception style"
- "Safe ContextStore invalid-key diagnostics without raw unsafe key leakage"
- "CorrelationIdProvider read-side validation for canonical safe correlation ids"
- "Container diagnostics sanitization for suspicious service ids"
- "Contract and integration tests proving Foundation runtime diagnostics do not leak unsafe values"
- "Safe ContextStore write-forbidden diagnostics with stable reason tokens and safe path segments"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: "updates existing ADRs only; no new ADR"
adr_updates:
- docs/adr/ADR-0019-enhanced-reset-long-running.md
- docs/adr/ADR-0015-context-bag-context-store-correlation-id.md
- docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md
- docs/adr/ADR-0016-clock-ids-stopwatch.md
- docs/adr/ADR-0006-reset-interface-uow-hooks.md
ssot_refs:
- docs/ssot/uow-and-reset-contracts.md
- docs/ssot/observability-and-errors.md
- docs/ssot/observability.md
- docs/ssot/reset-tags.md
- docs/ssot/context-store.md
- docs/ssot/context-keys.md
- docs/ssot/time-ids-and-duration.md
- docs/ssot/di-tags-and-middleware-ordering.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.120.0 — `ResetInterface` and UoW/reset contracts exist.
  - 1.200.0 — Foundation DI/tags/reset/stable diagnostics baseline exists.
  - 1.260.0 — Foundation enhanced reset ordering and observability exist.
  - 1.270.0 — downstream Kernel UoW shapes exist; this epic hardens the Foundation reset boundary they will later consume.
  - 1.275.0 — Foundation safe diagnostics discipline exists for json-like runtime values; this epic applies the same no-raw-diagnostics policy to reset observability.

- Required deliverables (exact paths):
  - `framework/packages/core/foundation/src/Runtime/Reset/ResetException.php` — existing deterministic reset failure exception to harden with stable accessors and sanitized-copy API.
  - `framework/packages/core/foundation/src/Runtime/Reset/PriorityResetOrchestrator.php` — existing enhanced reset executor that records reset failures into tracing spans.
  - `framework/packages/core/foundation/tests/Integration/ResetOrchestratorRejectsTaggedNonResettableServiceTest.php` — existing reset rejection test to extend with `errorCode()` / `reason()` assertions.
  - `framework/packages/core/foundation/tests/Integration/PriorityResetFailsFastOnFirstServiceExceptionTest.php` — existing service-failure test to extend with `errorCode()` / `reason()` and sanitized recorded exception assertions.
  - `framework/packages/core/foundation/tests/Integration/PriorityResetEmitsSafeSummaryObservabilityTest.php` — existing reset observability baseline reference.
  - `framework/packages/core/foundation/src/Context/Exception/ContextInvalidKeyException.php` — existing context key rejection exception to harden against unsafe raw key leakage.
  - `framework/packages/core/foundation/src/Observability/CorrelationIdProvider.php` — existing read-side correlation id provider to harden against unsafe malformed context values.
  - `framework/packages/core/foundation/src/Container/ContainerDiagnostics.php` — existing deterministic container diagnostics snapshot to harden suspicious service id handling.
  - `docs/ssot/context-store.md` — existing ContextStore SSoT to document safe invalid-key diagnostics.
  - `docs/ssot/context-keys.md` — existing context key policy SSoT to document safe diagnostic key segments.
  - `docs/ssot/time-ids-and-duration.md` — existing IDs/time SSoT to document canonical correlation id read-side format.
  - `docs/ssot/di-tags-and-middleware-ordering.md` — existing DI/tag ordering SSoT to document container diagnostics service-id sanitization.
  - `framework/packages/core/foundation/README.md` — existing Foundation package README to document runtime failure safety hardening.
  - `docs/ssot/uow-and-reset-contracts.md` — existing reset/UoW SSoT to document safe reset failure diagnostics.
  - `docs/ssot/observability-and-errors.md` — existing observability/error policy SSoT to document sanitized reset exception recording.
  - `docs/ssot/observability.md` — existing observability SSoT to cross-reference reset observability safety.
  - `framework/packages/core/foundation/src/Context/Exception/ContextWriteForbiddenException.php` — existing context write rejection exception to harden with stable reason/safePath accessors and safe path diagnostics.
  - `framework/packages/core/foundation/tests/Integration/ContextStoreSafeWriteGuardBlocksForbiddenKeysTest.php` — existing safe-write guard integration test to align invalid-key diagnostics with <key> policy and extend write-forbidden assertions.
  - `framework/packages/core/foundation/tests/Contract/ContextWriteForbiddenDiagnosticsAreSafeContractTest.php` — new contract test proving write-forbidden diagnostics expose only stable reason tokens and safe path segments.

- Required config roots/keys:
  - `foundation` — existing Foundation config root.
  - `foundation.reset.tag` — existing effective reset discovery tag.
  - `foundation.reset.priority.enabled` — existing enhanced reset orchestration switch.
  - `foundation.reset.group.default` — existing enhanced reset default group.

- Required tags:
  - `kernel.reset` — existing effective reset discovery tag used by Foundation reset orchestration.
  - This epic introduces no new tags and MUST NOT redefine tag ownership.

- Required contracts / ports:
  - `Coretsia\Contracts\Runtime\ResetInterface` — reset capability contract implemented by resettable services and consumed by Foundation reset orchestration.
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface` — reset tracing span source.
  - `Coretsia\Contracts\Observability\Tracing\SpanInterface` — reset failure exception recording target.
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface` — reset metrics target.
  - `Coretsia\Contracts\Context\ContextAccessorInterface` — read-side context accessor consumed by `CorrelationIdProvider`.
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface` — read-side correlation id provider port implemented by Foundation.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `psr/container`
- `psr/log`
- `psr/clock`

Forbidden:

- `core/kernel`
- `platform/*`
- `integrations/*`
- `devtools/*`
- `tools/*`
- `framework/tools/spikes/*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Container\ContainerInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Runtime\ResetInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Tracing\SpanInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Runtime\Reset\ResetException`
  - `Coretsia\Foundation\Runtime\Reset\ResetErrorCodes`
  - `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`
  - `Coretsia\Foundation\Runtime\Reset\PriorityResetOrchestrator`
  - `Coretsia\Foundation\Tag\TagRegistry`
  - `Coretsia\Foundation\Tag\TaggedService`
  - `Coretsia\Foundation\Time\Stopwatch`
  - `Coretsia\Foundation\Container\ContainerBuilder`
  - `Coretsia\Foundation\Tag\ReservedTags`

### Entry points / integration points (MUST)

- Other runtime discovery / integration tags:
  - `kernel.reset` — existing reset discovery tag consumed only by Foundation reset orchestration; this epic does not define or redefine ownership, priority semantics, or tag meta-schema.
- Observability:
  - span: `foundation.reset`
  - metrics:
    - `foundation.reset_total`
    - `foundation.reset_duration_ms`
  - logs:
    - lifecycle summary message `foundation.reset`

### Deliverables (MUST)

#### Creates

- [x] `framework/packages/core/foundation/tests/Contract/ContextWriteForbiddenDiagnosticsAreSafeContractTest.php`
  - [x] Assert `ContextWriteForbiddenException::ERROR_CODE` remains stable.
  - [x] Assert `reason()` exposes the stable write-forbidden reason token.
  - [x] Assert `safePath()` exposes only a safe diagnostic path segment.
  - [x] Assert safe paths remain visible when they match the conservative safe-path policy.
  - [x] Assert unsafe paths are replaced with `<path>`.
  - [x] Assert messages contain only stable reason and safe path segment.
  - [x] Assert messages MUST NOT include raw rejected values.
  - [x] Assert messages MUST NOT include unsafe path fragments containing auth values, cookies, session ids, tokens, credentials, passwords, raw SQL, object dumps, local absolute paths, control chars, or environment-specific bytes.
  - [x] Assert `getCode()` remains `0`.
  - [x] Assert previous throwable may be preserved for programmatic chaining, while `getMessage()` remains safe.

- [x] `framework/packages/core/foundation/tests/Unit/ResetExceptionRuntimeShapeTest.php`
  - [x] Assert each static constructor returns the expected reset code.
  - [x] Assert `code()` and `errorCode()` return the same value.
  - [x] Assert `reason()` returns the stable safe reason token.
  - [x] Assert `getCode()` remains `0`.
  - [x] Assert `withoutPrevious()` preserves code/errorCode/reason/message.
  - [x] Assert `withoutPrevious()` strips previous throwable.
  - [x] Assert exception messages remain stable safe reason tokens only.

- [x] `framework/packages/core/foundation/tests/Integration/PriorityResetRecordsSanitizedFailureExceptionTest.php`
  - [x] Assert a reset service may throw an unsafe exception message containing token/cookie/raw SQL/local path fragments.
  - [x] Assert surfaced `ResetException` message remains safe.
  - [x] Assert surfaced `ResetException::getPrevious()` may preserve the original service failure for programmatic chaining.
  - [x] Assert span `recordException()` receives a sanitized `ResetException` copy.
  - [x] Assert the recorded exception has no previous throwable.
  - [x] Assert the recorded exception message does not leak the raw reset service exception message.
  - [x] Assert recorded exception diagnostics do not leak auth values, cookies, session ids, tokens, credentials, passwords, raw SQL, raw payloads, object dumps, local absolute paths, or environment-specific bytes.
  - [x] Assert span exception attributes remain summary-only:
    - [x] `outcome=failed`
  - [x] Assert reset metrics/log summary remain policy-compliant and do not include service internals.

- [x] `framework/packages/core/foundation/tests/Integration/PriorityResetObservabilityFailurePrecedenceTest.php`
  - [x] Assert observability failure after successful reset is surfaced when span `end()` throws.
  - [x] Assert observability failure after successful reset is surfaced when meter emission throws.
  - [x] Assert observability failure after successful reset is surfaced when logger emission throws.
  - [x] Assert reset service failure remains primary when span `recordException()` throws while recording the reset failure.
  - [x] Assert observability failure message is stable and safe.
  - [x] Assert observability failure does not leak the unsafe previous throwable message through `ResetException::getMessage()`.
  - [x] Assert reset service failure remains primary when reset service failure occurs before tracer/span/meter/logger failure.
  - [x] Assert observability failure does not replace primary `ResetException::serviceFailed()`.
  - [x] Assert observability failure does not leak through reset summary logs or metrics.
  - [x] Assert failure precedence is deterministic:
    - [x] reset succeeds + observability fails → `reset-observability-failed`
    - [x] reset fails + observability also fails → original reset failure remains surfaced

- [x] `framework/packages/core/foundation/tests/Contract/ContextInvalidKeyDiagnosticsAreSafeContractTest.php`
  - [x] Assert safe unknown key diagnostics remain stable for conservative safe keys.
  - [x] Assert safe reserved key diagnostics remain stable for conservative safe `@*` keys.
  - [x] Assert unsafe unknown keys are replaced with `<key>`.
  - [x] Assert unsafe reserved keys are replaced with `<key>`.
  - [x] Assert keys containing tokens, cookies, SQL fragments, URLs, absolute paths, control chars, or credentials do not appear in exception messages.
  - [x] Assert `ContextInvalidKeyException::reason()` exposes the stable reason token.
  - [x] Assert `ContextInvalidKeyException::safeKey()` exposes only a safe diagnostic segment.

- [x] `framework/packages/core/foundation/tests/Integration/CorrelationIdProviderRejectsUnsafeCorrelationIdsTest.php`
  - [x] Assert provider returns canonical ULID correlation id.
  - [x] Assert provider returns `null` for empty string.
  - [x] Assert provider returns `null` for non-string values.
  - [x] Assert provider returns `null` for lowercase ULID-like values.
  - [x] Assert provider returns `null` for token-like strings.
  - [x] Assert provider returns `null` for cookie-like strings.
  - [x] Assert provider returns `null` for raw SQL-like strings.
  - [x] Assert provider returns `null` for URL/path/header-like strings.
  - [x] Assert provider has no write side effects.

- [x] `framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsDoesNotLeakSensitiveServiceIdsContractTest.php`
  - [x] Assert normal FQCN service ids remain readable.
  - [x] Assert normal safe aliases remain readable.
  - [x] Assert absolute paths are hashed.
  - [x] Assert URL-like service ids are hashed.
  - [x] Assert token-like service ids are hashed.
  - [x] Assert credential-like service ids are hashed.
  - [x] Assert SQL-like service ids are hashed.
  - [x] Assert diagnostics JSON does not contain unsafe raw service ids.
  - [x] Assert hash format is deterministic and includes `hash:sha256:` and `len:`.
  - [x] Assert suspicious aliases such as `token:abc`, `secret.value`, `password:raw`, and `credential.token` are hashed even though they match the conservative alias character pattern.
  - [x] Assert sensitive/suspicious detection has precedence over readable alias allowlisting.

#### Modifies

- [x] `framework/packages/core/foundation/README.md`
  - [x] Document that reset observability records sanitized reset failures only.
  - [x] Document that `ResetException::withoutPrevious()` is used for span recording.
  - [x] Document that `ResetException::errorCode()` / `reason()` are stable runtime-style accessors.
  - [x] Document context invalid-key diagnostics safety.
  - [x] Document CorrelationIdProvider read-side validation.
  - [x] Document ContainerDiagnostics suspicious service-id hashing.
  - [x] Reaffirm that Foundation diagnostics/logs/metrics/spans are summary-only and do not expose unsafe raw runtime values.
  - [x] Document `ContextWriteForbiddenException::reason()` / `safePath()` as stable runtime-style diagnostics accessors.
  - [x] Document that write-forbidden messages expose only stable reason and safe path segment.

- [x] `docs/ssot/uow-and-reset-contracts.md`
  - [x] Clarify that `ResetInterface` services may throw arbitrary exceptions, but reset diagnostics and observability MUST remain safe.
  - [x] Clarify that Foundation reset orchestration may preserve previous throwables for programmatic chaining while sanitized observability MUST strip previous exception chains.
  - [x] Clarify that reset execution remains Foundation-owned and KernelRuntime consumes only `ResetOrchestrator::resetAll()`.

- [x] `docs/ssot/observability-and-errors.md`
  - [x] Add reset observability policy:
    - [x] spans may record reset failures only as sanitized `ResetException` copies without previous throwables.
    - [x] span exception recording MUST NOT leak raw previous throwable messages or stack traces.
    - [x] logs/metrics MUST remain summary-only.
  - [x] Clarify allowed reset labels:
    - [x] `outcome`
  - [x] Clarify reset failure diagnostics allowed values:
    - [x] stable reset code
    - [x] stable reason token
    - [x] summary counts
    - [x] duration

- [x] `docs/ssot/observability.md`
  - [x] Cross-reference reset observability safety policy from `docs/ssot/observability-and-errors.md`.
  - [x] Do not introduce new span names, metric names, labels, or logging payload fields.

- [x] `docs/ssot/context-store.md`
  - [x] Document that context invalid-key diagnostics MUST NOT leak unsafe raw rejected keys.
  - [x] Document that safe context keys may remain visible only under conservative safe-key rules.
  - [x] Document that unsafe keys are represented by stable placeholders such as `<key>`.
  - [x] Document that context write-forbidden diagnostics MUST NOT leak rejected raw values.
  - [x] Document that context write-forbidden diagnostics may include only stable reason token and safe path segment.
  - [x] Document that unsafe paths are represented by `<path>`.
  - [x] Document that unsafe map keys inside value paths are represented by `[<key>]`.

- [x] `docs/ssot/context-keys.md`
  - [x] Cross-reference context invalid-key diagnostic safety policy.
  - [x] Clarify that key names used in diagnostics are safe structural identifiers, not raw user-controlled values.
  - [x] Cross-reference write-forbidden safe path diagnostics.
  - [x] Clarify that diagnostic paths are structural safe paths, not raw user-controlled key/value payloads.

- [x] `docs/ssot/time-ids-and-duration.md`
  - [x] Document canonical Foundation correlation id format used by `CorrelationIdProvider`.
  - [x] Clarify that malformed or unsafe correlation id context values resolve to `null`.

- [x] `docs/ssot/di-tags-and-middleware-ordering.md`
  - [x] Document that container diagnostics sanitize suspicious service ids.
  - [x] Clarify that service id diagnostics may hash unsafe ids using `hash:sha256:<hash>;len:<len>`.
  - [x] Do not introduce new tag ownership, tag meta-schema, or discovery semantics.

- [x] `framework/packages/core/foundation/src/Container/ContainerDiagnostics.php`
  - [x] Preserve deterministic JSON output.
  - [x] Preserve recursive stable JSON encoding through `StableJsonEncoder`.
  - [x] Preserve existing absolute-path hashing behavior.
  - [x] Extend `diagnosticSafeId()` to hash suspicious service ids, not only absolute paths.
  - [x] Keep normal class-like service ids readable.
  - [x] Keep conservative safe aliases readable.
  - [x] MUST NOT leak raw URL-like, token-like, credential-like, SQL-like, absolute-path-like, control-character, or overlong service ids.
  - [x] Hash replacement MUST remain deterministic as `hash:sha256:<hash>;len:<len>`.
  - [x] Safe readable service ids are:
    - [x] class-like ids matching `/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/`
    - [x] conservative aliases matching `/\A[A-Za-z_][A-Za-z0-9_.:-]{0,127}\z/`
  - [x] Suspicious/sensitive service-id detection MUST run before readable-id allowlist checks.
  - [x] Token-like, credential-like, password-like, secret-like, cookie-like, authorization-like, SQL-like, URL-like, path-like, control-character, and overlong ids MUST be hashed even if they match the conservative alias pattern.
  - [x] Any id outside these patterns MUST be hashed unless already normalized by existing absolute-path hash logic.

- [x] `framework/packages/core/foundation/src/Context/Exception/ContextInvalidKeyException.php`
  - [x] Preserve `ERROR_CODE`.
  - [x] Add `private readonly string $reason`.
  - [x] Add `private readonly ?string $safeKey`.
  - [x] Add `public function reason(): string`.
  - [x] Add `public function safeKey(): ?string`.
  - [x] Message MUST contain only stable reason and safe key segment.
  - [x] Message MUST NOT contain raw unsafe rejected keys.
  - [x] Use raw key in message only when it matches a conservative safe-key pattern.
  - [x] Replace unsafe, long, whitespace, URL-like, SQL-like, path-like, token-like, credential-like, or control-character keys with `<key>`.
  - [x] Use raw key in message only when it matches conservative safe-key pattern:
    - [x] `/\A@?[A-Za-z_][A-Za-z0-9_]{0,63}\z/`

- [x] `framework/packages/core/foundation/src/Context/Exception/ContextWriteForbiddenException.php`
  - [x] Preserve `ERROR_CODE`.
  - [x] Add `private readonly string $reason`.
  - [x] Add `private readonly ?string $safePath`.
  - [x] Add `public function reason(): string`.
  - [x] Add `public function safePath(): ?string`.
  - [x] Validate allowed stable reason tokens.
  - [x] Message MUST contain only stable reason and safe path segment.
  - [x] Message MUST NOT contain rejected raw values.
  - [x] Message MUST NOT contain unsafe raw path fragments.
  - [x] Use raw path in message only when it matches conservative safe-path policy.
  - [x] Replace unsafe, long, whitespace, URL-like, SQL-like, path-like, token-like, credential-like, control-character, or sensitive paths with `<path>`.
  - [x] Preserve previous throwable for programmatic chaining.
  - [x] Previous throwable message MUST NOT be copied into `getMessage()`.
  - [x] Safe readable paths are:
    - [x] root/context path segments matching conservative identifier shape.
    - [x] dotted safe segments.
    - [x] list indices like `[0]`.
    - [x] sanitized map placeholders like `[<key>]`.
  - [x] Constructor message policy MUST remain stable and safe.

- [x] `framework/packages/core/foundation/src/Runtime/Reset/ResetException.php`
  - [x] Preserve existing `public function code(): string` behavior.
  - [x] Add `private readonly string $reason`.
  - [x] Store `$reason` in the constructor.
  - [x] Add `public function errorCode(): string`.
  - [x] `errorCode()` MUST return the same value as `code()`.
  - [x] Add `public function reason(): string`.
  - [x] `reason()` MUST return the stable safe message token.
  - [x] Add `public function withoutPrevious(): self`.
  - [x] `withoutPrevious()` MUST return a new `ResetException` using the stored `$resetCode` and stored `$reason`, not by parsing or deriving data from the previous throwable.
  - [x] `withoutPrevious()` MUST NOT preserve the previous throwable.
  - [x] `withoutPrevious()` MUST NOT change `getMessage()`, `code()`, `errorCode()`, or `reason()`.
  - [x] Keep existing static constructors:
    - [x] `metaInvalid()`
    - [x] `serviceNotResettable()`
    - [x] `serviceFailed()`
  - [x] Constructor message policy MUST remain stable and safe.
  - [x] Exception messages MUST NOT include service ids, payloads, secrets, raw context values, absolute paths, headers, cookies, Authorization values, tokens, session ids, host-specific values, or environment-specific data.

- [x] `framework/packages/core/foundation/src/Runtime/Reset/PriorityResetOrchestrator.php`
  - [x] When recording reset failure into span, call `ResetException::withoutPrevious()` before `SpanInterface::recordException()`.
  - [x] MUST NOT pass a `ResetException` containing a raw previous chain to `recordException()`.
  - [x] Preserve existing span name:
    - [x] `foundation.reset`
  - [x] Preserve existing metrics:
    - [x] `foundation.reset_total`
    - [x] `foundation.reset_duration_ms`
  - [x] Preserve existing labels:
    - [x] `outcome`
  - [x] Preserve existing log message:
    - [x] `foundation.reset`
  - [x] Preserve existing failure precedence:
    - [x] reset fails + observability also fails → preserve primary reset failure
  - [x] Observability summary MUST remain summary-only.
  - [x] Observability summary MUST NOT include raw service ids, tag metadata, service instances, raw previous exception messages, stack traces, payloads, secrets, headers, cookies, Authorization values, tokens, session ids, absolute paths, or raw context values.

- [x] `framework/packages/core/foundation/tests/Integration/ResetOrchestratorRejectsTaggedNonResettableServiceTest.php`
  - [x] Add assertions that `ResetException::errorCode()` equals `ResetException::code()`.
  - [x] Add assertions that `ResetException::reason()` equals `reset-not-resettable`.
  - [x] Assert `withoutPrevious()` preserves code/errorCode/reason/message.
  - [x] Assert `withoutPrevious()` has no previous throwable.
  - [x] Preserve existing deterministic stop-at-first-invalid-service behavior.
  - [x] Preserve existing safe message assertions.

- [x] `framework/packages/core/foundation/tests/Integration/PriorityResetFailsFastOnFirstServiceExceptionTest.php`
  - [x] Add assertions that `ResetException::errorCode()` equals `ResetException::code()`.
  - [x] Add assertions that `ResetException::reason()` equals `reset-service-failed`.
  - [x] Assert surfaced `ResetException` may preserve the original service failure as previous.
  - [x] Assert the span recorded exception is a sanitized reset exception without previous.
  - [x] Assert the recorded exception has the same code/errorCode/reason/message as the surfaced reset failure.
  - [x] Preserve existing first-failing-service behavior.
  - [x] Preserve existing safe summary observability assertions.

- [x] `framework/packages/core/foundation/tests/Integration/PriorityResetEmitsSafeSummaryObservabilityTest.php`
  - [x] Update only if helper fakes require shared recorded-exception assertions.
  - [x] Preserve existing success and failure observability summary expectations.

- [x] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsAtPrefixedKeysTest.php`
  - [x] Preserve exact message assertions only for safe keys like `@foo`.
  - [x] Add unsafe reserved key no-leak assertions.

- [x] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsUnknownKeysTest.php`
  - [x] Preserve exact message assertions only for safe keys like `unknown_key`.
  - [x] Add unsafe unknown key no-leak assertions.

- [x] `framework/packages/core/foundation/tests/Contract/ContextStorePolicyUsesJsonLikeNormalizerContractTest.php`
  - [x] Adjust key-policy assertions if needed after safe-key diagnostics hardening.
  - [x] Assert `ContextWriteForbiddenException::reason()` equals mapped context write reason.
  - [x] Assert `ContextWriteForbiddenException::safePath()` equals expected safe path.
  - [x] Assert unsafe JsonLikeNormalizer map-key paths remain represented by `[<key>]`.
  - [x] Assert write-forbidden messages do not expose rejected raw values.
  - [x] Assert write-forbidden messages do not expose raw JsonLikeNormalizationException reason tokens.
  - [x] Preserve existing JsonLikeNormalizer delegation assertions.
  - [x] Preserve existing no-mutation assertions.

- [x] `framework/packages/core/foundation/tests/Integration/ContextStoreSafeWriteGuardBlocksForbiddenKeysTest.php`
  - [x] Preserve unsafe non-canonical key rejection before storage.
  - [x] For sensitive unsafe non-canonical keys, assert `ContextInvalidKeyException::safeKey()` is `<key>`.
  - [x] For sensitive unsafe non-canonical keys, assert message is `context-key-unknown: <key>`.
  - [x] Preserve exact raw-key message assertions only for conservative safe rejected keys such as `headers`, `request_body`, `response_body`, `email`, `phone`, `full_name`.
  - [x] Add `ContextWriteForbiddenException::reason()` assertions for forbidden value-shape rejection.
  - [x] Add `ContextWriteForbiddenException::safePath()` assertions for forbidden value-shape rejection.
  - [x] Preserve write-before-storage behavior.
  - [x] Preserve callable-like string accepted as plain string.

- [x] `framework/packages/core/foundation/src/Observability/CorrelationIdProvider.php`
  - [x] Keep read-only behavior.
  - [x] MUST NOT generate a correlation id.
  - [x] MUST NOT normalize unsafe input.
  - [x] MUST return current context correlation id only when it matches canonical Foundation correlation id format.
  - [x] MUST return `null` for unsafe or malformed correlation id values.
  - [x] MUST NOT leak malformed correlation id values through exceptions, logs, metrics, traces, or diagnostics.
  - [x] Canonical Foundation correlation id format is uppercase ULID-like:
    - [x] `/\A[0-9A-HJKMNP-TV-Z]{26}\z/`

- [x] `framework/packages/core/foundation/tests/Integration/CorrelationIdProviderReadsContextStoreTest.php`
  - [x] Preserve canonical valid correlation id behavior.
  - [x] Add or keep null behavior for absent/empty/non-string values.
  - [x] Assert canonical valid value matches `/\A[0-9A-HJKMNP-TV-Z]{26}\z/`.

#### Package skeleton (if type=package)

N/A — this epic modifies the existing `core/foundation` runtime package and does not create a new package skeleton.

#### Configuration (keys + defaults)

- Files:
  - N/A — this epic introduces no config files.
- Keys (dot):
  - N/A — this epic introduces no config keys.
- [x] Rules:
  - [x] Existing reset config shape is unchanged.
  - [x] Existing reset tag config is unchanged.
  - [x] Existing priority/group reset config is unchanged.
  - [x] Sanitized reset observability MUST be unconditional and MUST NOT be feature-disabled via config.

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A — this epic introduces no tags.
- ServiceProvider wiring evidence:
  - N/A — this epic introduces no new services and no new DI registrations.
- [x] Compliance delta:
  - [x] this package MUST NOT introduce owner constants for tags it does not own.
  - [x] runtime code MUST NOT use raw reset tag strings for new behavior.
  - [x] existing reset tag ownership and discovery semantics remain unchanged.
  - [x] non-owner packages MUST NOT define competing reset tag semantics as part of this epic.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [x] Context reads:
  - [x] `ContextKeys::CORRELATION_ID` / `correlation_id`
  - [x] `CorrelationIdProvider` MUST read correlation id values only through `ContextAccessorInterface`.
  - [x] malformed or unsafe correlation id context values MUST resolve to `null`.
  - [x] context reads MUST NOT log, trace, emit, normalize, or leak malformed values.
- Context writes (safe only):
  - N/A — this epic does not add context writes.
- [x] Reset discipline:
  - [x] resettable services still implement `Coretsia\Contracts\Runtime\ResetInterface`.
  - [x] reset execution remains owned by Foundation reset orchestration.
  - [x] `ResetOrchestrator::resetAll()` remains the public reset execution boundary.
  - [x] reset discovery tag semantics are unchanged.
  - [x] reset failure observability MUST use sanitized `ResetException` copies.
  - [x] reset service failures MUST NOT leak raw previous throwable data through spans/logs/metrics.
- [x] Context invalid-key diagnostics:
  - [x] Context key rejection MUST NOT leak unsafe raw rejected keys.
  - [x] Safe context key identifiers may remain visible only when they match conservative safe-key rules.
  - [x] Unsafe keys MUST be replaced with stable placeholders.
- [x] Context write-forbidden diagnostics:
  - [x] Context value-shape rejection MUST NOT leak rejected raw values.
  - [x] Context value-shape rejection MUST NOT leak unsafe raw path fragments.
  - [x] Safe path-to-value diagnostics may remain visible only when they match conservative safe-path rules.
  - [x] Unsafe paths MUST be replaced with stable placeholder `<path>`.
  - [x] Sanitized JsonLikeNormalizer map-key placeholders such as `[<key>]` are allowed in safe paths.
  - [x] `ContextWriteForbiddenException::reason()` MUST return a stable reason token.
  - [x] `ContextWriteForbiddenException::safePath()` MUST return only a safe diagnostic path segment or `null`.

#### Observability (policy-compliant)

- [x] Spans:
  - [x] `foundation.reset`
  - [x] Span attributes remain summary-only:
    - [x] `services_count`
    - [x] `groups_count`
    - [x] `outcome`
  - [x] Failed reset spans may record a sanitized `ResetException` only.
  - [x] Failed reset spans MUST NOT record a throwable with a raw previous chain.
- [x] Metrics:
  - [x] `foundation.reset_total` (labels: `outcome`)
  - [x] `foundation.reset_duration_ms` (labels: `outcome`)
- [x] Logs:
  - [x] message: `foundation.reset`
  - [x] context remains summary-only:
    - [x] `services_count`
    - [x] `groups_count`
    - [x] `outcome`
  - [x] redaction applied by construction; no service internals, raw payloads, raw previous throwable messages, stack traces, tokens, cookies, raw SQL, or local paths.
- [x] Correlation id read-side safety:
  - [x] `CorrelationIdProvider` MUST return only canonical safe correlation id strings.
  - [x] malformed or unsafe correlation id context values MUST resolve to `null`.

#### Errors

- Exceptions introduced:
  - N/A — this epic introduces no new exception class.
- [x] Exceptions modified:
  - [x] `Coretsia\Foundation\Runtime\Reset\ResetException`
    - [x] preserve `code()`
    - [x] add `errorCode()`
    - [x] add `reason()`
    - [x] add `withoutPrevious()`
  - [x] `Coretsia\Foundation\Context\Exception\ContextInvalidKeyException`
    - [x] preserve `ERROR_CODE`
    - [x] add `reason()`
    - [x] add `safeKey()`
    - [x] replace unsafe raw rejected keys with safe placeholders
  - [x] `Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException`
    - [x] preserve `ERROR_CODE`
    - [x] add `reason()`
    - [x] add `safePath()`
    - [x] validate stable write-forbidden reason tokens
    - [x] replace unsafe raw paths with `<path>`
    - [x] keep messages stable and safe
- [x] Mapping:
  - [x] No `error.mapper` integration is introduced.
  - [x] Reset failures continue to use existing `ResetErrorCodes`.
  - [x] Service reset failure continues to map to `CORETSIA_RESET_SERVICE_FAILED`.
  - [x] Tagged non-resettable service continues to map to `CORETSIA_RESET_SERVICE_NOT_RESETTABLE`.
  - [x] Context write forbidden failures continue to map to `CORETSIA_CONTEXT_WRITE_FORBIDDEN`.
  - [x] JsonLikeNormalizer reasons are mapped to stable context write-forbidden reason tokens.

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] auth values
  - [x] cookies
  - [x] session ids
  - [x] tokens
  - [x] credentials
  - [x] passwords
  - [x] headers
  - [x] raw SQL
  - [x] raw payloads
  - [x] raw context values
  - [x] raw reset service exception messages
  - [x] raw previous throwable messages
  - [x] Throwable stack traces
  - [x] object dumps
  - [x] service internals
  - [x] service instances
  - [x] tag metadata values
  - [x] local absolute paths
  - [x] environment-specific bytes
  - [x] unsafe context keys
  - [x] unsafe service ids
  - [x] malformed correlation ids
  - [x] unsafe context write paths
  - [x] rejected raw context values
  - [x] raw JsonLikeNormalizer previous throwable messages
- [x] Allowed:
  - [x] stable reset error code
  - [x] stable reset reason token
  - [x] summary counts:
    - [x] `services_count`
    - [x] `groups_count`
  - [x] summary outcome:
    - [x] `ok`
    - [x] `failed`
  - [x] reset duration in milliseconds
  - [x] sanitized `ResetException` without previous throwable for span exception recording
  - [x] safe context key segment
  - [x] hashed diagnostic id
  - [x] canonical correlation id only when it matches `/\A[0-9A-HJKMNP-TV-Z]{26}\z/`
  - [x] stable context write-forbidden reason token
  - [x] safe context write path segment
  - [x] sanitized path placeholder `<path>`
  - [x] sanitized map-key path placeholder `[<key>]`

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [x] Reset observability failure safety:
  - [x] `framework/packages/core/foundation/tests/Integration/PriorityResetRecordsSanitizedFailureExceptionTest.php`
    - [x] fails if `PriorityResetOrchestrator` records a reset failure with raw previous chain.
    - [x] fails if recorded exception leaks token/cookie/raw SQL/local path fragments.
  - [x] `framework/packages/core/foundation/tests/Integration/PriorityResetObservabilityFailurePrecedenceTest.php`
    - [x] fails if observability failure replaces primary reset failure.
    - [x] fails if observability failure diagnostics leak unsafe details.
- [x] Reset exception shape:
  - [x] `framework/packages/core/foundation/tests/Integration/ResetOrchestratorRejectsTaggedNonResettableServiceTest.php`
    - [x] fails if `errorCode()` / `reason()` are missing or inconsistent.
  - [x] `framework/packages/core/foundation/tests/Integration/PriorityResetFailsFastOnFirstServiceExceptionTest.php`
    - [x] fails if `errorCode()` / `reason()` are missing or inconsistent.
    - [x] fails if span recorded exception preserves unsafe previous chain.
  - [x] `framework/packages/core/foundation/tests/Unit/ResetExceptionRuntimeShapeTest.php`
    - [x] fails if `code()` / `errorCode()` diverge.
    - [x] fails if `reason()` is missing or unstable.
    - [x] fails if `withoutPrevious()` preserves previous throwable.
- [x] Observability policy:
  - [x] `framework/packages/core/foundation/tests/Integration/PriorityResetEmitsSafeSummaryObservabilityTest.php`
    - [x] remains green and continues proving summary-only reset observability.
- [x] Context invalid-key diagnostic safety:
  - [x] `framework/packages/core/foundation/tests/Contract/ContextInvalidKeyDiagnosticsAreSafeContractTest.php`
    - [x] fails if unsafe rejected context keys appear raw in exception messages.
    - [x] fails if `reason()` / `safeKey()` are missing or expose unsafe data.
  - [x] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsAtPrefixedKeysTest.php`
    - [x] fails if unsafe reserved `@*` keys leak raw values.
  - [x] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsUnknownKeysTest.php`
    - [x] fails if unsafe unknown keys leak raw values.
- [x] Correlation id read-side safety:
  - [x] `framework/packages/core/foundation/tests/Integration/CorrelationIdProviderRejectsUnsafeCorrelationIdsTest.php`
    - [x] fails if malformed/token-like/cookie-like/SQL-like/path-like correlation id values are returned.
    - [x] fails if provider mutates context or generates new ids.
  - [x] `framework/packages/core/foundation/tests/Integration/CorrelationIdProviderReadsContextStoreTest.php`
    - [x] remains green for canonical valid correlation id reads.
- [x] Container diagnostics service-id safety:
  - [x] `framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsDoesNotLeakSensitiveServiceIdsContractTest.php`
    - [x] fails if diagnostics JSON contains unsafe raw service ids.
    - [x] fails if suspicious ids are not hashed deterministically.
    - [x] fails if normal FQCN/safe aliases stop being readable.
- [x] Context write-forbidden diagnostic safety:
  - [x] `framework/packages/core/foundation/tests/Contract/ContextWriteForbiddenDiagnosticsAreSafeContractTest.php`
    - [x] fails if unsafe rejected write paths appear raw in exception messages.
    - [x] fails if rejected raw values appear in exception messages.
    - [x] fails if `reason()` / `safePath()` are missing or expose unsafe data.
  - [x] `framework/packages/core/foundation/tests/Contract/ContextStorePolicyUsesJsonLikeNormalizerContractTest.php`
    - [x] fails if JsonLikeNormalizer-to-ContextWriteForbiddenException mapping loses safe path or stable reason.
    - [x] fails if unsafe map-key path placeholders leak raw keys.
  - [x] `framework/packages/core/foundation/tests/Integration/ContextStoreSafeWriteGuardBlocksForbiddenKeysTest.php`
    - [x] fails if unsafe non-canonical context keys leak raw values.
    - [x] fails if forbidden value-shape diagnostics leak rejected raw values or unsafe paths.

#### Test harness / fixtures (when integration is needed)

- [x] Fake adapters:
  - [x] fake tracer/span captures recorded exceptions and attributes.
  - [x] fake meter captures reset metrics and labels.
  - [x] fake logger captures reset summary records.
  - [x] fake container/reset services produce controlled reset success/failure.
  - [x] fake observability adapters produce controlled tracer/span/meter/logger failures.

### Tests (MUST)

- Unit:
  - [x] `framework/packages/core/foundation/tests/Unit/ResetExceptionRuntimeShapeTest.php`
- Contract:
  - [x] `framework/packages/core/foundation/tests/Contract/ContextInvalidKeyDiagnosticsAreSafeContractTest.php`
  - [x] `framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsDoesNotLeakSensitiveServiceIdsContractTest.php`
  - [x] `framework/packages/core/foundation/tests/Contract/ContextStorePolicyUsesJsonLikeNormalizerContractTest.php`
  - [x] `framework/packages/core/foundation/tests/Contract/ContextWriteForbiddenDiagnosticsAreSafeContractTest.php`
- Integration:
  - [x] `framework/packages/core/foundation/tests/Integration/PriorityResetRecordsSanitizedFailureExceptionTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/PriorityResetObservabilityFailurePrecedenceTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/ResetOrchestratorRejectsTaggedNonResettableServiceTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/PriorityResetFailsFastOnFirstServiceExceptionTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/PriorityResetEmitsSafeSummaryObservabilityTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/CorrelationIdProviderRejectsUnsafeCorrelationIdsTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsAtPrefixedKeysTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/ContextStoreRejectsUnknownKeysTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/CorrelationIdProviderReadsContextStoreTest.php`
  - [x] `framework/packages/core/foundation/tests/Integration/ContextStoreSafeWriteGuardBlocksForbiddenKeysTest.php`
- Gates/Arch:
  - [x] deptrac remains green: `core/foundation` MUST NOT depend on `core/kernel`, `platform/*`, `integrations/*`, `devtools/*`, or `tools/*`.
  - [x] package compliance gates remain green: no new tags, config roots, artifacts, or forbidden runtime dependencies.
  - [x] public API compatibility remains green:
    - [x] `ResetException::code()` remains available.
    - [x] `ContextInvalidKeyException::ERROR_CODE` remains available.

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact.
- [x] Preconditions satisfied; no forward references introduced.
- [x] `ResetException::code()` remains backward-compatible.
- [x] `ResetException::errorCode()` returns the same stable reset code as `code()`.
- [x] `ResetException::reason()` returns the stable safe reason token.
- [x] `ResetException::withoutPrevious()` returns a safe copy without previous throwable.
- [x] `ResetException::withoutPrevious()` preserves reset code, errorCode, reason, and message.
- [x] `PriorityResetOrchestrator` records sanitized reset failure exceptions into spans.
- [x] `PriorityResetOrchestrator` MUST NOT pass raw previous exception chains to `SpanInterface::recordException()`.
- [x] Reset observability failure precedence is deterministic:
  - [x] reset succeeds + observability fails → `reset-observability-failed`
  - [x] reset fails + observability also fails → primary reset failure remains surfaced
- [x] Reset metrics remain summary-only.
- [x] Reset logs remain summary-only.
- [x] Reset spans remain summary-only.
- [x] Safe diagnostics preserved: no raw values, tokens, cookies, SQL, object dumps, stack traces, service internals, tag metadata values, local paths, or environment-specific bytes in reset failures or reset observability.
- [x] Verification tests present where applicable.
- [x] Runtime packages do not depend on `devtools/internal-toolkit` or `framework/tools/spikes/*`.
- [x] `core/foundation` does not depend on `core/kernel`.
- [x] No config roots, config keys, DI tags, or artifacts introduced.
- [x] Docs updated:
  - [x] `framework/packages/core/foundation/README.md`
  - [x] `docs/ssot/uow-and-reset-contracts.md`
  - [x] `docs/ssot/observability-and-errors.md`
  - [x] `docs/ssot/observability.md`
  - [x] `docs/ssot/context-store.md`
  - [x] `docs/ssot/context-keys.md`
  - [x] `docs/ssot/time-ids-and-duration.md`
  - [x] `docs/ssot/di-tags-and-middleware-ordering.md`
- [x] `ContextInvalidKeyException` diagnostics are safe:
  - [x] safe keys may remain visible.
  - [x] unsafe keys are replaced with `<key>`.
  - [x] `reason()` returns a stable reason token.
  - [x] `safeKey()` never exposes unsafe raw input.
- [x] `ContextWriteForbiddenException` diagnostics are safe:
  - [x] rejected raw values never appear in exception messages.
  - [x] unsafe paths are replaced with `<path>`.
  - [x] safe paths may remain visible only under conservative safe-path rules.
  - [x] sanitized map-key placeholders like `[<key>]` are allowed.
  - [x] `reason()` returns a stable reason token.
  - [x] `safePath()` never exposes unsafe raw path input.
  - [x] previous throwable may be preserved for programmatic chaining, but its message is not copied into `getMessage()`.
- [x] `CorrelationIdProvider` read-side safety is enforced:
  - [x] returns only canonical Foundation correlation id format.
  - [x] returns `null` for malformed or unsafe context values.
  - [x] does not generate, normalize, mutate, log, trace, or emit malformed values.
- [x] `ContainerDiagnostics` service-id safety is enforced:
  - [x] safe FQCN/class-like service ids remain readable.
  - [x] conservative safe aliases remain readable.
  - [x] absolute-path, URL-like, token-like, credential-like, SQL-like, control-character, and overlong ids are hashed.
  - [x] hash format remains deterministic: `hash:sha256:<hash>;len:<len>`.
  - [x] suspicious/sensitive detection takes precedence over readable alias allowlisting.
- [x] Safe diagnostics preserved across all touched Foundation boundaries:
  - [x] reset failures
  - [x] reset observability
  - [x] context invalid-key exceptions
  - [x] correlation id provider reads
  - [x] container diagnostics snapshots
  - [x] context write-forbidden exceptions

---

### 1.278.0 Docs/Ops: CI Workflow Separation and Architecture Generator Evidence (SHOULD) [DOC]

---
type: docs
phase: 1
epic_id: "1.278.0"
owner_path: "docs/ops"

goal: "Separate Coretsia CI concerns for core verification, spikes, and architecture generator evidence, while adding lightweight architecture generator idempotence evidence in a dedicated workflow."
provides:
- "Architecture generator idempotence evidence in GitHub Actions summary"
- "Repeated architecture generator check timing visibility"
- "Tracked generated architecture file drift detection after each repeated check iteration"
- "Ops documentation explaining architecture generator evidence scope, metrics, current target, and non-goals"
- "README cross-reference to architecture generator idempotence evidence"
- "Dedicated architecture evidence workflow"
- "Dedicated spikes workflow"
- "Main CI workflow focused on core framework verification"
- "Spike/prototype rails separated from main CI workflow"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []

adr: none
ssot_refs: []
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.277.0 — Foundation runtime failure safety hardening is completed and should not be expanded with unrelated evidence work.
  - Existing architecture generator checks already exist and are callable from Composer:
    - `arch:package-index:check`
    - `arch:deptrac:check`
  - Existing generated architecture files are tracked:
    - `framework/tools/testing/package-index.php`
    - `framework/tools/testing/deptrac.yaml`
    - `framework/tools/testing/deptrac.allowlist.yaml`
  - Existing GitHub Actions CI workflow contains an `arch` job that owns regular architecture checks and dep graph artifact generation.
  - This epic keeps regular architecture checks in `.github/workflows/ci.yml` and adds architecture generator idempotence evidence in a dedicated workflow.

- Required deliverables (exact paths):
  - `.github/workflows/ci.yml` — keep the main CI workflow focused on core framework verification by removing spike/prototype jobs.
  - `.github/workflows/spikes.yml` — create a dedicated workflow for spike/prototype rails.
  - `.github/workflows/architecture-evidence.yml` — create a dedicated workflow for architecture generator idempotence evidence.
  - `docs/ops/architecture-generator-evidence.md` — new ops document describing evidence scope, generated files, metrics, current target, non-goals, and rationale.
  - `README.md` — add a small link to the architecture generator idempotence evidence document.

- Required config roots/keys:
  - N/A — this epic introduces no config roots or config keys.

- Required tags:
  - N/A — this epic introduces no tags and does not touch runtime discovery.

- Required contracts / ports:
  - N/A — this epic introduces no runtime contracts or ports.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- N/A — docs/ops workflow-only epic.

Forbidden:

- runtime package dependency changes
- package graph changes
- deptrac layer changes
- framework runtime imports
- test-only runtime dependencies

#### Uses ports (API surface, NOT deps) (optional)

N/A

### Entry points / integration points (MUST)

- CI workflows:
  - `.github/workflows/ci.yml`
    - main framework verification workflow.
    - keeps core/system verification jobs only.
    - MUST NOT contain spike/prototype jobs after this epic.
    - MUST NOT contain architecture generator evidence after this epic.
  - `.github/workflows/spikes.yml`
    - dedicated spike/prototype workflow.
    - owns `spike:test`.
    - owns `spike:test:determinism`.
  - `.github/workflows/architecture-evidence.yml`
    - dedicated architecture generator idempotence evidence workflow.
    - owns the GitHub Actions summary evidence for architecture generator checks.

- GitHub Actions summary:
  - `.github/workflows/architecture-evidence.yml` writes a markdown summary section to `$GITHUB_STEP_SUMMARY`.
  - summary title:

```text
Architecture Generator Idempotence Evidence
```

- Runtime:
  - N/A — this epic adds no runtime entry points.
- Artifacts:
  - N/A — this epic does not persist evidence artifacts.
  - GitHub Actions step summary is ephemeral CI evidence and is not a repository artifact.

### Deliverables (MUST)

#### Creates

- [x] `docs/ops/architecture-generator-evidence.md`
  - [x] Document that Coretsia collects early architecture generator idempotence evidence.
  - [x] Clarify that this is not an application benchmark.
  - [x] Clarify that this is not a production framework comparison.
  - [x] Document repeated checks:
    - [x] `arch:package-index:check`
    - [x] `arch:deptrac:check`
  - [x] Document tracked generated files checked for drift:
    - [x] `framework/tools/testing/package-index.php`
    - [x] `framework/tools/testing/deptrac.yaml`
    - [x] `framework/tools/testing/deptrac.allowlist.yaml`
  - [x] Document evidence metrics:
    - [x] `iteration`
    - [x] `duration_ms`
    - [x] `result`
    - [x] `git diff`
  - [x] Document current target:
    - [x] all repeated architecture generator checks pass.
    - [x] generated files remain unchanged.
    - [x] duration is visible in the GitHub Actions summary.
  - [x] Document non-goals:
    - [x] runtime HTTP behavior.
    - [x] application-level performance.
    - [x] flaky test rate.
    - [x] Windows parity.
    - [x] comparison with other frameworks.
  - [x] Explain that the first measurable deterministic property is generator idempotence.

- [x] `.github/workflows/architecture-evidence.yml`
  - [x] Create dedicated workflow named `architecture-evidence`.
  - [x] Trigger on:
    - [x] `push` to all branches.
    - [x] `pull_request`.
  - [x] Use `permissions: contents: read`.
  - [x] Add one job:
    - [x] `architecture-generator-idempotence`
  - [x] Job display name MUST be:
    - [x] `architecture-generator-idempotence / ubuntu / PHP 8.4`
  - [x] Run only on:
    - [x] `ubuntu-latest`
  - [x] Use PHP:
    - [x] `8.4`
  - [x] Use Composer:
    - [x] `composer:v2`
  - [x] Use setup extension:
    - [x] `imagick`
  - [x] Include setup steps:
    - [x] checkout repository.
    - [x] setup PHP + Composer.
    - [x] run `composer --no-interaction sync:check`.
    - [x] run `composer --no-interaction --no-progress install:framework`.
  - [x] Add `Evidence: architecture generator idempotence` step.
  - [x] Use `shell: bash`.
  - [x] Use `set -euo pipefail`.
  - [x] Run exactly 3 repetitions.
  - [x] Track only:
    - [x] `framework/tools/testing/package-index.php`
    - [x] `framework/tools/testing/deptrac.yaml`
    - [x] `framework/tools/testing/deptrac.allowlist.yaml`
  - [x] Check that tracked generated architecture files are clean before the evidence run.
  - [x] Run `composer --no-interaction arch:package-index:check` on each iteration.
  - [x] Run `composer --no-interaction arch:deptrac:check` on each iteration.
  - [x] Check tracked generated architecture file drift after each iteration.
  - [x] Write a GitHub Actions summary table with:
    - [x] check name
    - [x] iteration
    - [x] duration in milliseconds
    - [x] result
  - [x] Record `pass` and `fail` rows in the summary.
  - [x] Fail the step if any command fails.
  - [x] Fail the step if tracked generated architecture files drift.
  - [x] Fail the step if tracked generated architecture files are dirty before the evidence run.
  - [x] Run `composer --no-interaction lock:check` after the evidence step.
  - [x] Do not add Windows execution.
  - [x] Do not add `test-fast`.
  - [x] Do not run `spike:test`.
  - [x] Do not run `spike:test:determinism`.
  - [x] Do not run `arch:deptrac:generate`.
  - [x] Do not upload dep graph artifacts.
  - [x] Do not introduce a separate runner.
  - [x] Do not persist evidence artifacts.

- [x] `.github/workflows/spikes.yml`
  - [x] Create dedicated workflow named `spikes`.
  - [x] Trigger on:
    - [x] `push` to all branches.
    - [x] `pull_request`.
  - [x] Use `permissions: contents: read`.
  - [x] Add job `spikes`.
  - [x] `spikes` job display name MUST be:
    - [x] `spikes / ubuntu / PHP 8.4`
  - [x] `spikes` job MUST run on:
    - [x] `ubuntu-latest`
  - [x] `spikes` job MUST:
    - [x] checkout repository.
    - [x] setup PHP `8.4` with Composer v2.
    - [x] use `coverage: none`.
    - [x] enable `imagick`.
    - [x] run `composer --no-interaction sync:check`.
    - [x] run `composer --no-interaction --no-progress install:framework`.
    - [x] run `composer --no-interaction spike:test`.
    - [x] run `composer --no-interaction lock:check`.
  - [x] Add job `spikes-determinism`.
  - [x] `spikes-determinism` job display name MUST be:
    - [x] `spikes-determinism / ${{ matrix.os_name }} / PHP ${{ matrix.php }}`
  - [x] `spikes-determinism` job MUST use matrix:
    - [x] `ubuntu-latest` with display OS name `ubuntu`.
    - [x] `windows-2025-vs2026` with display OS name `windows`.
    - [x] PHP `8.4`.
  - [x] `spikes-determinism` job MUST use `fail-fast: false`.
  - [x] `spikes-determinism` job MUST preserve environment:
    - [x] `MSYS=winsymlinks:nativestrict`
    - [x] `CORETSIA_CI_SAFE_DEBUG=${{ vars.CORETSIA_CI_SAFE_DEBUG || '0' }}`
  - [x] `spikes-determinism` job MUST preserve Windows symlink rails:
    - [x] configure `core.autocrlf=false`.
    - [x] configure `core.eol=lf`.
    - [x] configure `core.symlinks=true`.
    - [x] configure `core.safecrlf=true`.
    - [x] assert git symlink mode.
    - [x] verify shell symlink capability.
    - [x] verify PHP symlink capability.
    - [x] cleanup PHP symlink check with `always()`.
  - [x] `spikes-determinism` job MUST:
    - [x] checkout repository.
    - [x] setup PHP with Composer v2.
    - [x] use `coverage: none`.
    - [x] enable `imagick`.
    - [x] run `composer --no-interaction sync:check`.
    - [x] run `composer --no-interaction --no-progress install:framework`.
    - [x] run `composer --no-interaction spike:test:determinism`.
    - [x] preserve the safe Windows debug step guarded by:
      - [x] `runner.os == 'Windows'`
      - [x] `failure()`
      - [x] `CORETSIA_CI_SAFE_DEBUG == '1'`
    - [x] run `composer --no-interaction lock:check`.
  - [x] Do not run framework package tests in this workflow.
  - [x] Do not run architecture generator evidence in this workflow.
  - [x] Do not introduce runtime package changes.

#### Modifies

- [x] `.github/workflows/ci.yml`
  - [x] Keep main workflow role as the main CI workflow.
  - [x] Rename workflow display name:
    - [x] from `CI`
    - [x] to `ci`
  - [x] Keep triggers:
    - [x] `push` to all branches.
    - [x] `pull_request`.
  - [x] Keep `permissions: contents: read`.
  - [x] Keep these jobs:
    - [x] `gates`
    - [x] `gates-windows`
    - [x] `arch`
    - [x] `arch-windows`
    - [x] `test`
    - [x] `quality`
  - [x] Remove these jobs:
    - [x] `spikes`
    - [x] `determinism`
  - [x] Do not add architecture generator evidence to `ci.yml`.
  - [x] Keep existing core CI behavior:
    - [x] gates remain green.
    - [x] arch checks remain green.
    - [x] framework tests remain green.
    - [x] quality checks remain green.
    - [x] lock drift checks remain green.
  - [x] Preserve existing runner labels:
    - [x] `ubuntu-latest`
    - [x] `windows-2025-vs2026`
  - [x] Rename display job names only:
    - [x] replace display text `ubuntu-latest` with `ubuntu`.
    - [x] replace display text `windows-2025-vs2026` with `windows`.
  - [x] Do not rename `runs-on` values.
  - [x] Do not remove Windows rails from Windows jobs.
  - [x] Do not change Composer command semantics for retained jobs.

- [x] `README.md`
  - [x] Add a small link to `docs/ops/architecture-generator-evidence.md`.
  - [x] Mention that architecture generator idempotence evidence is collected by the dedicated `architecture-evidence` workflow.
  - [x] Do not add a badge.
  - [x] Do not present this evidence as a benchmark.
  - [x] Do not present this evidence as a framework comparison.
  - [x] Do not claim production runtime determinism from this evidence.

#### Package skeleton (if type=package)

N/A — this epic is docs/ops and does not create a package skeleton.

#### Configuration (keys + defaults)

- Files:
  - N/A — this epic introduces no config files.
- Keys (dot):
  - N/A — this epic introduces no config keys.
- Rules:
  - N/A — this epic introduces no package config rules.

#### Wiring / DI tags (when applicable)

N/A — this epic introduces no runtime wiring and no DI tags.

#### Artifacts / outputs (if applicable)

- Writes:
  - N/A — this epic writes no repository artifacts.
- Reads:
  - N/A — this epic reads no runtime artifacts.
- [x] CI summary:
  - [x] writes only to `$GITHUB_STEP_SUMMARY`.
  - [x] must not be treated as a stable repository artifact.
  - [x] must not be required for local development.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

N/A — this epic does not read or write runtime context and does not affect UoW/reset behavior.

#### Observability (policy-compliant)

N/A — this epic does not introduce runtime spans, metrics, logs, labels, or telemetry payloads.

#### Errors

N/A — this epic introduces no runtime exceptions and no error mapping.

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] secrets
  - [x] tokens
  - [x] credentials
  - [x] cookies
  - [x] authorization values
  - [x] raw payloads
  - [x] environment values
- [x] Allowed:
  - [x] command names
  - [x] iteration numbers
  - [x] duration in milliseconds
  - [x] pass/fail result
  - [x] tracked generated architecture file paths listed in this epic

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- [x] CI workflow separation:
  - [x] `.github/workflows/ci.yml`
    - [x] fails if retained core CI jobs are accidentally removed.
    - [x] no longer contains `spikes`.
    - [x] no longer contains `determinism`.
    - [x] does not contain architecture generator evidence step.
  - [x] `.github/workflows/spikes.yml`
    - [x] contains `spikes` job.
    - [x] contains `spikes-determinism` job.
    - [x] runs `spike:test`.
    - [x] runs `spike:test:determinism`.
    - [x] preserves Windows spike determinism rails.
  - [x] `.github/workflows/architecture-evidence.yml`
    - [x] contains architecture generator idempotence evidence job.
    - [x] does not run spike rails.
    - [x] does not run framework test rails.

- [x] Architecture generator idempotence evidence:
  - [x] `.github/workflows/architecture-evidence.yml`
    - [x] fails if `arch:package-index:check` fails.
    - [x] fails if `arch:deptrac:check` fails.
    - [x] fails if tracked generated architecture files are dirty before the evidence run.
    - [x] fails if tracked generated architecture files drift after any iteration.
    - [x] writes visible pass/fail evidence to `$GITHUB_STEP_SUMMARY`.
    - [x] writes duration in milliseconds for each measured command.
    - [x] runs exactly 3 repetitions.
    - [x] checks only the tracked generated architecture files listed in this epic.

#### Test harness / fixtures (when integration is needed)

N/A — this epic uses `.github/workflows/spikes.yml`, `.github/workflows/architecture-evidence.yml`, and existing Composer scripts.

### Tests (MUST)

- Unit:
  - N/A
- Contract:
  - N/A
- Integration:
  - N/A
- [x] Gates/Arch:
  - [x] `.github/workflows/ci.yml` keeps core verification jobs and excludes spike/evidence jobs.
  - [x] `.github/workflows/spikes.yml` contains dedicated spike/prototype jobs.
  - [x] `.github/workflows/architecture-evidence.yml` contains dedicated architecture generator idempotence evidence job.
  - [x] Local `composer arch` remains green.
  - [x] Existing deptrac generator rerun-no-diff behavior remains unchanged.
  - [x] Existing CI architecture checks remain in `.github/workflows/ci.yml`.
  - [x] Architecture evidence workflow does not modify tracked generated architecture files.
  - [x] No runtime package code is changed.

### DoD (MUST)

- [x] Deliverables complete, paths exact.
- [x] Preconditions satisfied; no forward references introduced.
- [x] `.github/workflows/ci.yml` remains the main core framework verification workflow.
- [x] `.github/workflows/ci.yml` keeps:
  - [x] `gates`
  - [x] `gates-windows`
  - [x] `arch`
  - [x] `arch-windows`
  - [x] `test`
  - [x] `quality`
- [x] `.github/workflows/ci.yml` no longer contains:
  - [x] `spikes`
  - [x] `determinism`
- [x] `.github/workflows/ci.yml` does not contain architecture generator evidence.
- [x] `.github/workflows/spikes.yml` exists.
- [x] `.github/workflows/spikes.yml` owns:
  - [x] `spike:test`
  - [x] `spike:test:determinism`
- [x] `.github/workflows/spikes.yml` preserves Linux and Windows spike determinism coverage.
- [x] `.github/workflows/spikes.yml` preserves Windows symlink rails and safe debug behavior.
- [x] `.github/workflows/architecture-evidence.yml` exists.
- [x] `.github/workflows/architecture-evidence.yml` contains the architecture generator idempotence evidence job.
- [x] Evidence job runs only on Ubuntu.
- [x] Evidence job repeats `arch:package-index:check` and `arch:deptrac:check` exactly 3 times.
- [x] Evidence job checks tracked generated architecture files before the run and after each iteration.
- [x] Evidence job fails on command failure.
- [x] Evidence job fails on tracked generated file drift.
- [x] Evidence job writes pass/fail and duration evidence to GitHub Actions summary.
- [x] Evidence job does not run:
  - [x] Windows.
  - [x] `spike:test`.
  - [x] `spike:test:determinism`.
  - [x] `arch:deptrac:generate`.
  - [x] dep graph artifact upload.
- [x] `docs/ops/architecture-generator-evidence.md` documents scope, generated files, metrics, current target, non-goals, and rationale.
- [x] `README.md` links to the evidence document without adding a badge.
- [x] Job display names may use shorter OS labels:
  - [x] `ubuntu`
  - [x] `windows`
- [x] Runner labels remain valid GitHub runner labels:
  - [x] `ubuntu-latest`
  - [x] `windows-2025-vs2026`
- [x] No runtime package code changed.
- [x] No config roots, config keys, DI tags, or artifacts introduced.
- [x] No ADR or SSoT update required.
- [x] Evidence remains narrow:
  - [x] no Windows parity claim.
  - [x] no benchmark claim.
  - [x] no production framework comparison claim.
  - [x] no runtime HTTP behavior claim.
  - [x] no flaky test rate claim.

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
- "Гарантія для `runUnitOfWork()`: after-phase виконується завжди; reset рівно 1 раз (long-running safe)"
- "Low-level `beginUnitOfWork()` / `afterUnitOfWork()` API для adapters, які самі інтегруються через `try/finally`"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: "docs/adr/ADR-0020-kernel-runtime-uow-spi.md"
ssot_refs:
- "docs/ssot/uow-and-reset-contracts.md"
- "docs/ssot/uow-outcome-policy.md"
- "docs/ssot/uow-shapes.md"
- "docs/ssot/tags.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.10.0 — Tag registry SSoT exists (`core/kernel` is the owner package that declares canonical constants for `kernel.hook.before_uow` and `kernel.hook.after_uow`)
  - 1.20.0 — Config roots registry exists and includes root `kernel` owned by `core/kernel`
  - 1.200.0 — Foundation tag/reset infrastructure exists (`TagRegistry`, `ResetOrchestrator`, deterministic ordering).
  - 1.205.0 — Foundation noop observability + logger baseline bindings exist, so KernelRuntime observability remains safely resolvable without `platform/*` packages installed.
  - 1.210.0 — `ContextStore`, `ContextKeys`, `CorrelationIdProviderInterface` binding, Foundation `CorrelationIdProvider`, Foundation id generation, and resettable context infrastructure exist and are canonical for runtime context.
    - KernelRuntime MUST consume this infrastructure and implement the lifecycle handoff recorded by `1.210.0`:
      - write base context keys at begin-UoW;
      - execute Foundation reset orchestration after every UoW.
  - 1.220.0 — canonical clock/ids/stopwatch bindings exist in Foundation.
  - 1.270.0 — UnitOfWork shapes are canonical and provide the exported ctx/result model for hooks.
  - 1.275.0 — Foundation json-like runtime value normalizer exists and is canonical for baseline json-like validation/normalization used by hook payload export.

- Terminology note (MUST): config root vs config key namespaces
  - Config root for Kernel is **`kernel`** (file: `framework/packages/core/kernel/config/kernel.php`).
  - Any dotted prefixes like `kernel.uow.*`, `kernel.runtime.*`, `kernel.modules.*`, `kernel.config.*`, `kernel.artifacts.*`, `kernel.fingerprint.*`
    are **config key namespaces**, not separate roots.
  - `config/<name>.php` MUST return subtree for `<name>` (no wrapper array repeating the root key).

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/src/Runtime/ResetInterface.php` — reset discipline port.
  - `framework/packages/core/contracts/src/Runtime/Hook/BeforeUowHookInterface.php` — existing before-UoW hook port updated by this epic.
  - `framework/packages/core/contracts/src/Runtime/Hook/AfterUowHookInterface.php` — existing after-UoW hook port updated by this epic.
  - `framework/packages/core/contracts/src/Observability/CorrelationIdProviderInterface.php` — canonical correlation id source.
  - `framework/packages/core/contracts/src/Observability/Tracing/TracerPortInterface.php` — tracing port.
  - `framework/packages/core/contracts/src/Observability/Metrics/MeterPortInterface.php` — metrics port.
  - `framework/packages/core/foundation/src/Context/ContextStore.php` — safe context store.
  - `framework/packages/core/contracts/src/Context/ContextKeys.php` — canonical base context keys.
  - `framework/packages/core/foundation/src/Runtime/Reset/ResetOrchestrator.php` — reset executor boundary.
  - `framework/packages/core/foundation/src/Tag/TagRegistry.php` — deterministic tag ordering source.
  - `framework/packages/core/foundation/src/Time/Stopwatch.php` — canonical duration measurement.
  - `framework/packages/core/foundation/src/Id/IdGeneratorInterface.php` — canonical `uow_id` generator dependency.
  - `framework/packages/core/foundation/src/Id/CorrelationIdGenerator.php` — canonical fallback `correlation_id` generator.
  - `framework/packages/core/foundation/src/Serialization/JsonLikeNormalizer.php` — canonical baseline json-like normalizer used by HookContextNormalizer.
  - `framework/packages/core/foundation/src/Serialization/Exception/JsonLikeNormalizationException.php` — canonical json-like normalization failure used by the foundation normalizer.

- Required config roots/keys:
  - none — this epic introduces no new config roots and no new config keys.

- Required tags:
  - `kernel.hook.before_uow` — existing reserved before-UoW hook discovery tag; canonical public owner constant introduced by this epic in `Coretsia\Foundation\Tag\ReservedTags`.
  - `kernel.hook.after_uow` — existing reserved after-UoW hook discovery tag; canonical public owner constant introduced by this epic in `Coretsia\Foundation\Tag\ReservedTags`.
  - reset discipline is consumed only through `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`; kernel MUST NOT depend on reset tag naming.

- Required contracts / ports:
  - `Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface` — before hooks receive normalized exported UoW context array
  - `Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface` — after hooks receive normalized exported UoW context/result arrays
  - `Coretsia\Contracts\Runtime\KernelRuntimeInterface` — external UoW runtime port consumed by platform/http, platform/cli, worker, scheduler adapters
  - `Coretsia\Contracts\Runtime\ResetInterface` — reset capability contract implemented by resettable services and consumed by Foundation reset orchestration; KernelRuntime MUST NOT enumerate or call reset services directly.
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface` — canonical correlation id source for UoW base context
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
- `psr/container`
- `psr/log`

Forbidden:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- `Psr\Http\Server\*`

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `Psr\Container\ContainerInterface`
  - `Psr\Log\LoggerInterface`
- Contracts:
  - `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
  - `Coretsia\Contracts\Observability\CorrelationIdProviderInterface`
  - `Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface`
  - `Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface`
  - `Coretsia\Contracts\Runtime\ResetInterface` — indirect prerequisite only; KernelRuntime MUST NOT enumerate or call reset services directly and MUST trigger reset only through Foundation `ResetOrchestrator`.
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
- Foundation stable APIs:
  - `Coretsia\Foundation\Context\ContextStore`
  - `Coretsia\Contracts\Context\ContextKeys`
  - `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator` — only reset execution boundary KernelRuntime may call.
  - `Coretsia\Foundation\Tag\TagRegistry`
  - `Psr\Clock\ClockInterface` (via foundation binding)
  - `Coretsia\Foundation\Time\Stopwatch`
  - `Coretsia\Foundation\Id\IdGeneratorInterface`
  - `Coretsia\Foundation\Id\CorrelationIdGenerator`

### Entry points / integration points (MUST)

- Kernel hooks/tags:
  - `kernel.hook.before_uow` (before-uow hooks discovery tag; ordering owned by Foundation TagRegistry)
  - `kernel.hook.after_uow`  (after-uow hooks discovery tag; ordering owned by Foundation TagRegistry)
  - consumer MUST NOT enumerate reset tags directly (reset discipline tag; owned by `core/foundation`)

- Platform/HTTP (owner: `platform/http`):
  - SHOULD wrap each request through `runUnitOfWork(type=http, body, ...)` when the adapter can delegate the full lifecycle to KernelRuntime.
  - MAY use low-level `beginUnitOfWork()` / `afterUnitOfWork()` only when integrating around an existing framework lifecycle.
  - When using low-level API, `afterUnitOfWork()` MUST be called in `finally`.

- Platform/CLI (owner: `platform/cli`):
  - SHOULD wrap each command through `runUnitOfWork(type=cli, body, ...)`.
  - MAY use low-level `beginUnitOfWork()` / `afterUnitOfWork()` for ultra-early or framework-owned flows.
  - When using low-level API, `afterUnitOfWork()` MUST be called in `finally`.

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

- [x] `framework/packages/core/contracts/src/Runtime/KernelRuntimeInterface.php`
  - [x] Create `Coretsia\Contracts\Runtime\KernelRuntimeInterface`.
  - [x] Define external UoW runtime port consumed by platform/http, platform/cli, worker, and scheduler adapters.
  - [x] Define `runUnitOfWork(string $type, callable $body, array $attributes = []): mixed`.
  - [x] Define `beginUnitOfWork(string $type, array $attributes = []): array`.
  - [x] Define `afterUnitOfWork(array $context, string $outcome, ?\Throwable $error = null, array $extensions = []): array`.
  - [x] `runUnitOfWork()` is the preferred API for adapters that want KernelRuntime to enforce `after/reset` with `try/finally`.
  - [x] `beginUnitOfWork()` / `afterUnitOfWork()` are low-level primitives for adapters that must integrate around an existing event loop or framework lifecycle.
  - [x] MUST NOT depend on `core/kernel`.
  - [x] MUST NOT reference `UnitOfWorkContext`, `UnitOfWorkResult`, `Outcome`, or `UnitOfWorkType`.
  - [x] MUST NOT depend on PSR-7/15, platform, integrations, or foundation.
  - [x] `beginUnitOfWork()` MUST create the UoW context, write base context keys, invoke before-uow hooks, and return the normalized exported context array.
  - [x] If `beginUnitOfWork()` returns successfully, before-uow hooks have already completed successfully.
  - [x] Low-level adapters MUST execute the external body only after successful `beginUnitOfWork()`.
  - [x] `runUnitOfWork()` MUST return the external body return value when the body and after/reset phase complete successfully.
  - [x] `runUnitOfWork()` MUST NOT return the exported UoW result array.
  - [x] Exported UoW context/result arrays are lifecycle hook payloads; low-level adapters that need the exported result array MUST use `afterUnitOfWork()`.
  - [x] If body succeeds but after-hook or reset fails, `runUnitOfWork()` MUST surface the after/reset failure instead of returning the body value.

- [x] `framework/packages/core/kernel/src/Runtime/KernelRuntime.php` — orchestrator:
  - [x] Implement `Coretsia\Contracts\Runtime\KernelRuntimeInterface`.
  - [x] Internally use `UnitOfWorkContext` / `UnitOfWorkResult`.
  - [x] Return normalized exported arrays from `beginUnitOfWork()` and `afterUnitOfWork()`.
  - [x] MUST call `ResetOrchestrator::resetAll()` exactly once after every completed/started UoW lifecycle that reaches reset responsibility.
  - [x] `beginUnitOfWork()` MUST:
    - [x] validate `$type` against canonical `UnitOfWorkType` values;
    - [x] create the UoW context;
    - [x] write base context keys before the external runtime body is executed:
      - [x] `ContextKeys::CORRELATION_ID`
      - [x] `ContextKeys::UOW_ID`
      - [x] `ContextKeys::UOW_TYPE`
    - [x] invoke before-uow hooks;
    - [x] return the normalized exported context array.
  - [x] If `beginUnitOfWork()` returns successfully, before-uow hooks have already completed successfully.
  - [x] Low-level adapters MUST execute the external body only after successful `beginUnitOfWork()`.
  - [x] Invalid `$type` MUST fail with `KernelRuntimeException`.
  - [x] Type validation failures MUST NOT leak raw payload values.
  - [x] Implement `runUnitOfWork()` as the canonical lifecycle wrapper:
    - [x] create/begin the UoW context using the same validation and export rules as `beginUnitOfWork()`;
    - [x] invoke before-uow hooks before the external body;
    - [x] execute the external body only after before-uow hooks succeed;
    - [x] execute after-phase and reset through KernelRuntime-owned `try/finally` semantics;
    - [x] preserve the primary thrown failure after after/reset phase.
  - [x] `runUnitOfWork()` MUST NOT rely on public `beginUnitOfWork()` as an opaque black box if doing so would prevent after/reset semantics after before-hook failure.
  - [x] `runUnitOfWork()` MAY reuse shared private begin internals.
  - [x] `runUnitOfWork()` MUST return the external body return value when the body and after/reset phase complete successfully.
  - [x] `runUnitOfWork()` MUST NOT return the exported UoW result array.
  - [x] Exported UoW context/result arrays are lifecycle hook payloads; low-level adapters that need the exported result array MUST use `afterUnitOfWork()`.
  - [x] If body succeeds but after-hook or reset fails, `runUnitOfWork()` MUST surface the after/reset failure instead of returning the body value.
  - [x] `afterUnitOfWork()` MUST:
    - [x] validate the provided exported context array before using it;
    - [x] validate `$outcome` against canonical `Outcome` values;
    - [x] validate extensions through the canonical UoW/result json-like policy;
    - [x] build and normalize the exported UoW result array;
    - [x] invoke after-uow hooks;
    - [x] trigger reset through `ResetOrchestrator::resetAll()`.
  - [x] Invalid or incomplete context arrays MUST fail with `KernelRuntimeException`.
  - [x] Invalid `$outcome` MUST fail with `KernelRuntimeException`.
  - [x] Validation failures MUST NOT leak raw payload values.
  - [x] Required context fields:
    - [x] `uowId`
    - [x] `type`
    - [x] `startedAt`
    - [x] `correlationId`
    - [x] `attributes`
  - [x] Failure precedence MUST be deterministic:
    - [x] if the external body throws, that throwable remains the primary surfaced failure after after/reset phase;
    - [x] if no external body throwable exists and an after-uow hook throws, the after-hook failure is surfaced;
    - [x] reset MUST still run exactly once before any failure is surfaced;
    - [x] failure handling MUST NOT leak raw payloads, context arrays, hook payloads, tokens, cookies, or transport data.
  - [x] For `runUnitOfWork()`, before-hook failure semantics MUST be deterministic:
    - [x] if a before-uow hook throws, the external body MUST NOT run;
    - [x] after-phase MUST still run with `Outcome::FATAL_ERROR` when before-uow hook failure prevents the external body from running;
    - [x] if a before-uow hook throws and an after-uow hook also throws, the before-hook throwable remains the primary surfaced failure;
    - [x] reset MUST still run exactly once before the before-hook failure is surfaced.
  - [x] Low-level `beginUnitOfWork()` / `afterUnitOfWork()` users receive weaker lifecycle guarantees and MUST use `try/finally`; adapters that need KernelRuntime-owned before-hook failure handling SHOULD use `runUnitOfWork()`.
  - [x] Low-level `beginUnitOfWork()` failure semantics MUST be deterministic:
    - [x] if `beginUnitOfWork()` fails before any UoW context/base context writes, no reset is required;
    - [x] if `beginUnitOfWork()` fails after creating the UoW context or writing base context keys, KernelRuntime MUST trigger reset exactly once before surfacing the failure;
    - [x] if `beginUnitOfWork()` invokes before-uow hooks and a before hook throws, KernelRuntime MUST NOT leave ContextStore or other resettable state dirty;
    - [x] low-level `beginUnitOfWork()` MAY surface the original before-hook failure after reset;
    - [x] diagnostics MUST NOT leak raw context arrays, hook payloads, tokens, cookies, raw SQL, stack traces, object dumps, or local paths.
  - [x] Low-level `beginUnitOfWork()` failure does not guarantee after-uow hook execution, because no exported context is returned to the adapter.
  - [x] Adapters that require after-uow hooks even for before-hook failures MUST use `runUnitOfWork()`.
  - [x] Low-level `afterUnitOfWork()` failure semantics MUST be deterministic:
    - [x] if `afterUnitOfWork()` receives invalid context, invalid outcome, or invalid extensions after a UoW has begun, KernelRuntime MUST still trigger reset exactly once before surfacing the failure;
    - [x] invalid context/outcome/extensions failures MUST surface as `KernelRuntimeException`;
    - [x] reset failure MUST NOT replace the primary invalid-after input failure when that failure already exists;
    - [x] diagnostics MUST NOT leak raw context arrays, extensions, hook payloads, Throwable stack traces, tokens, cookies, raw SQL, object dumps, or local paths.
  - [x] Reset failure precedence MUST be deterministic:
    - [x] if no earlier primary failure exists and `ResetOrchestrator::resetAll()` throws, KernelRuntime MUST surface a `KernelRuntimeException` with a stable reset-failed reason;
    - [x] if an earlier primary failure exists and reset also throws, the earlier primary failure MUST remain the surfaced failure;
    - [x] reset failure diagnostics MUST NOT leak raw context arrays, hook payloads, transport payloads, tokens, cookies, raw SQL, stack traces, object dumps, or local paths.

- [x] `framework/packages/core/kernel/src/Runtime/Hook/HookInvoker.php`
  - [x] Invoke before hooks through `BeforeUowHookInterface::beforeUow(array $context): void`.
  - [x] Invoke after hooks through `AfterUowHookInterface::afterUow(array $context, array $result): void`.
  - [x] Obtain hook services only from `TagRegistry::all(ReservedTags::KERNEL_HOOK_BEFORE_UOW)` and `TagRegistry::all(ReservedTags::KERNEL_HOOK_AFTER_UOW)`.
  - [x] Invoke hooks in exact `TagRegistry` order.
  - [x] MUST NOT re-sort hooks.
  - [x] MUST NOT dedupe hooks.
  - [x] MUST NOT apply custom priority rules.
  - [x] MUST reject services that do not implement the expected hook interface with `KernelRuntimeException`.
  - [x] Resolve hook service ids through `Psr\Container\ContainerInterface`.
  - [x] Reject unresolved services with `KernelRuntimeException`.
  - [x] Reject resolved services that do not implement the expected hook interface with `KernelRuntimeException`.
  - [x] Treat each `TagRegistry::all(<tag>)` item as a tagged service reference.
  - [x] Resolve each tagged service through `Psr\Container\ContainerInterface` by service id.
  - [x] MUST NOT assume `TagRegistry` returns already-instantiated hook objects.
  - [x] MUST preserve the exact order returned by `TagRegistry::all()` after resolution.
  - [x] Treat each `TagRegistry::all(<tag>)` item as `Coretsia\Foundation\Tag\TaggedService`.
  - [x] Resolve each hook service through `Psr\Container\ContainerInterface` using `TaggedService::id()`.
  - [x] Hook service resolution failures MUST be wrapped as `KernelRuntimeException`.
  - [x] Hook service type mismatches MUST be wrapped as `KernelRuntimeException`.
  - [x] Exceptions thrown by valid hook implementations MUST NOT be replaced with `KernelRuntimeException`; they MUST propagate as the hook's original throwable so KernelRuntime can apply deterministic failure precedence.
  - [x] Hook-thrown failures MUST NOT cause HookInvoker to log or dump hook payloads.

- [x] `framework/packages/core/kernel/src/Runtime/Hook/HookContextNormalizer.php` — ensures ctx/result payload is json-like
  - [x] MUST normalize known internal kernel result objects before generic object rejection.
  - [x] In particular, if `UnitOfWorkResult.error` is internally represented as `Coretsia\Contracts\Observability\Errors\ErrorDescriptor`, it MUST be exported as a json-like error map.
  - [x] After normalization, no object instances may remain in the hook payload.
  - [x] MUST delegate baseline json-like validation/normalization to `Coretsia\Foundation\Serialization\JsonLikeNormalizer`.
  - [x] MUST preserve Kernel UoW exported shape semantics from `UnitOfWorkContext` / `UnitOfWorkResult`.
  - [x] MUST NOT define a second json-like policy in `core/kernel`.
  - [x] MUST be stateless.
  - [x] MUST NOT be a DI service in this epic.
  - [x] MUST NOT keep mutable runtime state, caches, buffers, or request/UoW-local data.
  - [x] KernelRuntime/HookInvoker may call it as an internal static normalization primitive.

- [x] `framework/packages/core/kernel/src/Runtime/Exception/KernelRuntimeException.php`
  - [x] Create `Coretsia\Kernel\Runtime\Exception\KernelRuntimeException`.
  - [x] Extend `\RuntimeException`.
  - [x] Define `public const string ERROR_CODE = 'CORETSIA_KERNEL_RUNTIME_ERROR'`.
  - [x] Implement `public static function withReason(string $reason, ?\Throwable $previous = null): self`.
  - [x] Implement `public function errorCode(): string`.
  - [x] Implement `public function reason(): string`.
  - [x] Message MUST contain only `ERROR_CODE` and stable reason token.
  - [x] Message MUST NOT contain raw context arrays, hook payloads, transport payloads, tokens, cookies, raw SQL, object dumps, local paths, or environment-specific data.
  - [x] Define stable reason constants:
    - [x] `REASON_INVALID_TYPE = 'kernel-runtime-invalid-type'`
    - [x] `REASON_INVALID_OUTCOME = 'kernel-runtime-invalid-outcome'`
    - [x] `REASON_INVALID_CONTEXT = 'kernel-runtime-invalid-context'`
    - [x] `REASON_INVALID_RESULT = 'kernel-runtime-invalid-result'`
    - [x] `REASON_HOOK_SERVICE_NOT_FOUND = 'kernel-runtime-hook-service-not-found'`
    - [x] `REASON_HOOK_SERVICE_INVALID = 'kernel-runtime-hook-service-invalid'`
    - [x] `REASON_HOOK_PAYLOAD_INVALID = 'kernel-runtime-hook-payload-invalid'`
    - [x] `REASON_RESET_FAILED = 'kernel-runtime-reset-failed'`

- [x] `framework/packages/core/foundation/src/Tag/ReservedTags.php` — tag constants owner

Docs:
- [x] `docs/adr/ADR-0020-kernel-runtime-uow-spi.md`
  - [x] `core/contracts` owns the external `KernelRuntimeInterface`.
  - [x] `core/kernel` owns the `KernelRuntime` implementation.
  - [x] `core/contracts` owns hook method signatures.
  - [x] `core/kernel` owns normalized hook payload production from `UnitOfWorkContext` / `UnitOfWorkResult`.
  - [x] Rejected alternative: kernel-local `Coretsia\Kernel\Runtime\KernelRuntimeInterface`.
  - [x] Rejected alternative: parameterless hooks with future-only payload normalizer.
  - [x] Platform/worker/scheduler adapters MUST depend on `Coretsia\Contracts\Runtime\KernelRuntimeInterface`.
  - [x] Adapters MUST NOT typehint or construct `Coretsia\Kernel\Runtime\KernelRuntime` directly.
  - [x] `Coretsia\Kernel\Runtime\KernelRuntime` is the `core/kernel` implementation bound to the contracts port by DI.

Tests:
- [x] `framework/packages/core/contracts/tests/Contract/KernelRuntimeInterfaceIsFormatNeutralContractTest.php`
  - [x] Assert `KernelRuntimeInterface` exists in `core/contracts`.
  - [x] Assert it exposes `runUnitOfWork()`, `beginUnitOfWork()`, and `afterUnitOfWork()`.
  - [x] Assert it does not reference `Coretsia\Kernel\*`.
  - [x] Assert it does not reference PSR-7/15, platform, or integrations.

- [x] `framework/packages/core/kernel/tests/Unit/HookInvokerDeterministicOrderTest.php`
  - [x] Assert before hooks are invoked in exact `TagRegistry::all(ReservedTags::KERNEL_HOOK_BEFORE_UOW)` order.
  - [x] Assert after hooks are invoked in exact `TagRegistry::all(ReservedTags::KERNEL_HOOK_AFTER_UOW)` order.
  - [x] Assert hook services are resolved through `Psr\Container\ContainerInterface`.
  - [x] Assert shared hook instances are not deduped.
  - [x] Assert `HookInvoker` does not re-sort hooks.
  - [x] Assert empty hook tag lists are deterministic no-ops.

- [x] `framework/packages/core/kernel/tests/Unit/HookContextNormalizerRejectsNonJsonLikeValuesTest.php`
  - [x] Reject floats.
  - [x] Reject `NaN`.
  - [x] Reject `INF`.
  - [x] Reject `-INF`.
  - [x] Reject objects.
  - [x] Reject resources.
  - [x] Assert failure is deterministic.
  - [x] Assert failure message does not leak raw rejected values.
  - [x] Assert failure message does not leak tokens, cookies, raw SQL, local paths, or object diagnostics.

- [x] `framework/packages/core/kernel/tests/Unit/HookContextNormalizerNormalizesErrorDescriptorTest.php`
  - [x] Assert internal `ErrorDescriptor` in `UnitOfWorkResult` is exported as a json-like error map.
  - [x] Assert hooks never receive an `ErrorDescriptor` object.
  - [x] Assert exported error map contains safe deterministic fields.
  - [x] Assert exported error payload remains json-like.

- [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeWritesBaseContextKeysAtBeginUowTest.php`
  - [x] Assert `runUnitOfWork()` writes base context keys before the external body is executed.
  - [x] Assert the external body receives no arguments.
  - [x] Assert the external body can read current UoW state from `ContextStore`.
  - [x] Assert `ContextKeys::CORRELATION_ID` is written before body execution.
  - [x] Assert `ContextKeys::UOW_ID` is written before body execution.
  - [x] Assert `ContextKeys::UOW_TYPE` is written before body execution.
  - [x] Assert `ContextKeys::UOW_TYPE` equals the normalized `UnitOfWorkType`.
  - [x] Assert `correlation_id` value is a string.
  - [x] Assert `uow_id` value is a string.
  - [x] Assert written values pass `ContextStore` policy.
  - [x] Assert KernelRuntime does not pass exported context as a body argument.

- [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeUsesCorrelationSourcesAndDefaultIdGeneratorTest.php`
  - [x] Assert `correlation_id` comes from `CorrelationIdProviderInterface` when the provider returns a non-empty string.
  - [x] Assert exported context `correlationId` equals the provider value.
  - [x] Assert `ContextStore` `correlation_id` equals the provider value.
  - [x] Assert KernelRuntime falls back to `Coretsia\Foundation\Id\CorrelationIdGenerator` when the provider returns `null`.
  - [x] Assert fallback `correlation_id` matches canonical ULID format `/\A[0-9A-HJKMNP-TV-Z]{26}\z/`.
  - [x] Assert fallback `correlation_id` is not affected by the default Foundation `IdGeneratorInterface`.
  - [x] Assert `uow_id` comes from the default Foundation `IdGeneratorInterface`.
  - [x] Use fake `IdGeneratorInterface` returning `01ARZ3NDEKTSV4RRFFQ69G5FAV`.
  - [x] Assert exported context `uowId` equals `01ARZ3NDEKTSV4RRFFQ69G5FAV`.
  - [x] Assert `ContextStore` `uow_id` equals `01ARZ3NDEKTSV4RRFFQ69G5FAV`.

- [x] `framework/packages/core/kernel/tests/Integration/KernelServiceProviderWiresKernelRuntimeTest.php`
  - [x] Assert container has `Coretsia\Kernel\Runtime\KernelRuntime`.
  - [x] Assert container has `Coretsia\Kernel\Runtime\Hook\HookInvoker`.
  - [x] Assert container has `Coretsia\Contracts\Runtime\KernelRuntimeInterface`.
  - [x] Assert `KernelRuntimeInterface` resolves to `KernelRuntime`.
  - [x] Assert resolving `KernelRuntime` succeeds with Foundation noop bindings.
  - [x] Assert `KernelRuntime` receives `ResetOrchestrator` through DI.
  - [x] Assert `KernelRuntime` receives `ContextStore` through DI.
  - [x] Assert `KernelRuntime` receives `CorrelationIdProviderInterface` through DI.
  - [x] Assert `KernelRuntime` receives `CorrelationIdGenerator` through DI.
  - [x] Assert `KernelRuntime` receives `IdGeneratorInterface` through DI.
  - [x] Assert `KernelRuntime` receives `Stopwatch` through DI.
  - [x] Assert `KernelRuntime` receives `LoggerInterface` through DI.
  - [x] Assert `KernelRuntime` receives `TracerPortInterface` through DI.
  - [x] Assert `KernelRuntime` receives `MeterPortInterface` through DI.
  - [x] Assert `LoggerInterface`, `TracerPortInterface`, and `MeterPortInterface` are resolvable through Foundation noop bindings.
  - [x] Assert provider does not start a UoW during registration.
  - [x] Assert provider does not trigger reset during registration.

- [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeInvokesHooksInDeterministicOrderTest.php`
  - [x] Assert before hooks are invoked before the external body.
  - [x] Assert after hooks are invoked after the external body.
  - [x] Assert before hooks receive normalized exported context array.
  - [x] Assert after hooks receive normalized exported context array.
  - [x] Assert after hooks receive normalized exported result array.
  - [x] Assert exact invocation order equals `TagRegistry::all()` order.
  - [x] Assert KernelRuntime does not re-sort hooks.
  - [x] Assert KernelRuntime does not dedupe hooks.
  - [x] Assert KernelRuntime does not apply custom priority rules.
  - [x] Assert external body is called without context/result arguments.
  - [x] Assert adapters needing exported context/result must use `beginUnitOfWork()` / `afterUnitOfWork()`.

- [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeExportsNormalizedHookPayloadsTest.php`
  - [x] Assert before hook receives `array<string, mixed>` context payload.
  - [x] Assert before hook context payload contains no objects.
  - [x] Assert before hook context payload contains no resources.
  - [x] Assert before hook context payload contains no floats.
  - [x] Assert context maps are recursively sorted by `strcmp`.
  - [x] Assert context lists preserve caller order.
  - [x] Assert context attributes are normalized through the canonical UoW/json-like policy.
  - [x] Assert after hook receives `array<string, mixed>` result payload.
  - [x] Assert result payload contains `correlationId`.
  - [x] Assert result payload contains `durationMs`.
  - [x] Assert result payload contains `extensions`.
  - [x] Assert result payload contains `finishedAt`.
  - [x] Assert result payload contains `outcome`.
  - [x] Assert result payload contains `startedAt`.
  - [x] Assert result payload contains `type`.
  - [x] Assert result payload contains `uowId`.
  - [x] Assert result maps are recursively sorted by `strcmp`.
  - [x] Assert result lists preserve caller order.
  - [x] Assert result payload contains no objects.
  - [x] Assert result payload contains no resources.
  - [x] Assert result payload contains no floats.
  - [x] Assert when `UnitOfWorkResult.error` exists internally as `ErrorDescriptor`, hooks receive `error` as an array.
  - [x] Assert hook result `error` is not an `ErrorDescriptor` object.
  - [x] Assert hook result `error` contains `code`.
  - [x] Assert hook result `error` contains `message`.
  - [x] Assert hook result `error` is json-like.
  - [x] Assert unsafe throwable message does not leak into hook result.

- [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeResetHappensAfterAfterUowHooksTest.php`
  - [x] Assert happy path event order is `before → body → after → reset`.
  - [x] Assert reset happens after after-uow hooks.
  - [x] Assert reset does not happen before after-uow hooks.
  - [x] Assert when an after-uow hook throws, reset still runs after the after hook starts/fails.
  - [x] Assert after-hook failure path event order is `before → body → after-start/after-throws → reset`.
  - [x] Assert after-hook throwable is surfaced when no body throwable exists.
  - [x] Keep this test focused on reset trigger order.
  - [x] Do not duplicate all primary failure precedence cases from `KernelRuntimeAlwaysResetsAfterUowTest.php`.

- [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeAlwaysResetsAfterUowTest.php`
  - [x] Assert `runUnitOfWork()` returns the external body value when body, after phase, and reset succeed.
  - [x] Assert external body is called without arguments.
  - [x] Assert body success → after success → reset success → returns body value.
  - [x] Assert body throws → after hooks still run.
  - [x] Assert body throws → reset runs exactly once.
  - [x] Assert body throws → body throwable remains surfaced.
  - [x] Assert body throws + reset throws → body throwable remains surfaced.
  - [x] Assert reset exception does not replace body throwable.
  - [x] Assert body succeeds + after hook throws → reset runs exactly once.
  - [x] Assert body succeeds + after hook throws → after-hook throwable is surfaced.
  - [x] Assert body succeeds + reset throws → surfaced exception is `KernelRuntimeException`.
  - [x] Assert body succeeds + reset throws → reason is `KernelRuntimeException::REASON_RESET_FAILED`.
  - [x] Assert before hook throws → external body is not executed.
  - [x] Assert before hook throws → after phase still runs.
  - [x] Assert before hook throws → after hooks receive result with `Outcome::FATAL_ERROR`.
  - [x] Assert before hook throws → reset runs exactly once.
  - [x] Assert before hook throws → before-hook throwable remains surfaced.
  - [x] Assert before hook throws + after hook throws → before-hook throwable remains surfaced.
  - [x] Assert before hook throws + reset throws → before-hook throwable remains surfaced.
  - [x] Assert low-level `beginUnitOfWork()` resets when before hook throws after context creation/base writes.
  - [x] Assert low-level `beginUnitOfWork()` surfaces original before-hook throwable after reset.
  - [x] Assert low-level `beginUnitOfWork()` does not execute after-uow hooks when no exported context is returned.
  - [x] Assert low-level `beginUnitOfWork()` does not leave `ContextStore` dirty after before-hook failure.
  - [x] Assert `ContextKeys::CORRELATION_ID` is cleared/reset after low-level begin failure.
  - [x] Assert `ContextKeys::UOW_ID` is cleared/reset after low-level begin failure.
  - [x] Assert `ContextKeys::UOW_TYPE` is cleared/reset after low-level begin failure.
  - [x] Assert reset failure diagnostics remain safe.

- [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeRejectsInvalidExportedContextTest.php`
  - [x] Assert `afterUnitOfWork()` rejects missing `uowId`.
  - [x] Assert `afterUnitOfWork()` rejects missing `type`.
  - [x] Assert `afterUnitOfWork()` rejects missing `startedAt`.
  - [x] Assert `afterUnitOfWork()` rejects missing `correlationId`.
  - [x] Assert `afterUnitOfWork()` rejects missing `attributes`.
  - [x] Assert missing required context fields fail with `KernelRuntimeException::REASON_INVALID_CONTEXT`.
  - [x] Assert missing required context fields trigger reset exactly once.
  - [x] Assert invalid context field types fail with `KernelRuntimeException::REASON_INVALID_CONTEXT`.
  - [x] Assert invalid context field types trigger reset exactly once.
  - [x] Assert invalid `startedAt` fails with `KernelRuntimeException::REASON_INVALID_CONTEXT`.
  - [x] Assert invalid `startedAt` triggers reset exactly once.
  - [x] Assert invalid outcome fails with `KernelRuntimeException::REASON_INVALID_OUTCOME`.
  - [x] Assert invalid outcome triggers reset exactly once.
  - [x] Assert invalid extensions fail with `KernelRuntimeException::REASON_INVALID_RESULT`.
  - [x] Assert invalid extensions trigger reset exactly once.
  - [x] Use invalid extensions example containing `\NAN`.
  - [x] Assert invalid after-input failure remains primary when reset also fails.
  - [x] Assert reset failure does not replace invalid-context failure.
  - [x] Assert reset failure does not replace invalid-outcome failure.
  - [x] Assert reset failure does not replace invalid-result failure.
  - [x] Assert validation failures do not leak raw context arrays.
  - [x] Assert validation failures do not leak raw extensions.
  - [x] Assert validation failures do not leak `Authorization`.
  - [x] Assert validation failures do not leak `Cookie`.
  - [x] Assert validation failures do not leak `session_id`.
  - [x] Assert validation failures do not leak `SELECT * FROM users`.
  - [x] Assert validation failures do not leak `/tmp/` local paths.

- [x] `framework/packages/core/kernel/tests/Contract/KernelPublicApiDoesNotExposePsr7Test.php`
  - [x] Assert Kernel implementation does not expose PSR-7 types.
  - [x] Assert Kernel implementation does not expose PSR-15 types.
  - [x] Assert external runtime port is `Coretsia\Contracts\Runtime\KernelRuntimeInterface`.
  - [x] Assert Kernel does not define a competing `Coretsia\Kernel\Runtime\KernelRuntimeInterface`.
  - [x] Assert platform/integration adapters should depend on the contracts port, not concrete `KernelRuntime`.
  - [x] Assert test forbids `Psr\Http\Message\*`.
  - [x] Assert test forbids `Psr\Http\Server\*`.
  - [x] Do not forbid all `Psr\*`.
  - [x] Allow `Psr\Container\ContainerInterface`.
  - [x] Allow `Psr\Log\LoggerInterface`.

- [x] `framework/packages/core/kernel/tests/Contract/KernelDoesNotWriteToStdoutTest.php`
  - [x] Token-scan `framework/packages/core/kernel/src/Runtime/**`.
  - [x] Token-scan `framework/packages/core/kernel/src/Provider/**`.
  - [x] Exclude tests and fixtures.
  - [x] Fail on `echo`.
  - [x] Fail on `print`.
  - [x] Fail on `var_dump`.
  - [x] Fail on `print_r`.
  - [x] Fail on `printf`.
  - [x] Fail on `error_log`.
  - [x] Fail on `STDOUT`.
  - [x] Fail on `STDERR`.
  - [x] Fail on `php://stdout`.
  - [x] Fail on `php://stderr`.
  - [x] Fail on `php://output`.
  - [x] Do not fail on `$this->logger->info(...)`.
  - [x] Assert Kernel runtime diagnostics go through deterministic exceptions/results or logging ports, not stdout/stderr.

- [x] `framework/packages/core/kernel/tests/Contract/KernelDoesNotEnumerateResetDiscoveryTagTest.php`
  - [x] Assert `core/kernel/src/**` contains no string literal `kernel.reset`.
  - [x] Assert `core/kernel/src/**` does not reference `Coretsia\Foundation\Tag\ReservedTags::KERNEL_RESET`.
  - [x] Assert `core/kernel/src/**` does not read config key `foundation.reset.tag`.
  - [x] Assert `core/kernel/src/**` does not enumerate reset services through `TagRegistry`.
  - [x] Assert `core/kernel/src/Runtime/**` does not import `Coretsia\Contracts\Runtime\ResetInterface`.
  - [x] Assert KernelRuntime does not call `ResetInterface::reset()` directly.
  - [x] Allow dependency on `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`.
  - [x] Allow calling only `ResetOrchestrator::resetAll(): void`.
  - [x] Assert `core/kernel` does not define `KERNEL_RESET` constants.
  - [x] Assert reset tag ownership remains in `core/foundation`.

- [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeEmitsPolicyCompliantObservabilityTest.php`
  - [x] Assert `LoggerInterface` is received through DI.
  - [x] Assert `TracerPortInterface` is received through DI.
  - [x] Assert `MeterPortInterface` is received through DI.
  - [x] Assert span `kernel.uow` is emitted.
  - [x] Assert span name is `kernel.uow`.
  - [x] Assert span attributes use only `operation` and `outcome`.
  - [x] Assert `uow_type` is normalized into `operation`.
  - [x] Assert span attribute `operation` equals `UnitOfWorkType::HTTP` for HTTP UoW.
  - [x] Assert span attribute `outcome` equals `Outcome::SUCCESS` on successful UoW.
  - [x] Assert metric `kernel.uow_total` is emitted.
  - [x] Assert metric `kernel.uow_duration_ms` is emitted.
  - [x] Assert metric labels use only `operation` and `outcome`.
  - [x] Assert metric label `operation` equals `UnitOfWorkType::HTTP` for HTTP UoW.
  - [x] Assert metric label `outcome` equals `Outcome::SUCCESS` on successful UoW.
  - [x] Assert lifecycle summary log message is `kernel.uow`.
  - [x] Assert lifecycle summary log context contains `operation`.
  - [x] Assert lifecycle summary log context contains `outcome`.
  - [x] Assert lifecycle summary log context contains `duration_ms`.
  - [x] Assert lifecycle summary log context does not contain raw `uowId`.
  - [x] Assert lifecycle summary log context does not contain raw `correlationId`.
  - [x] Assert lifecycle summary log context does not contain raw context arrays.
  - [x] Assert lifecycle summary log context does not contain raw hook payloads.
  - [x] Assert lifecycle summary log context does not contain raw throwable messages.
  - [x] Assert lifecycle summary log context does not contain stack traces.
  - [x] Assert lifecycle summary log context does not contain tokens.
  - [x] Assert lifecycle summary log context does not contain cookies.
  - [x] Assert lifecycle summary log context does not contain headers.
  - [x] Assert lifecycle summary log context does not contain raw SQL.
  - [x] Assert lifecycle summary log context does not contain local absolute paths.
  - [x] Assert observability port failures do not replace primary KernelRuntime lifecycle failures.

#### Modifies

- [x] `framework/packages/core/contracts/src/Runtime/Hook/BeforeUowHookInterface.php`
  - [x] Change `beforeUow(): void` to `beforeUow(array $context): void`.
  - [x] Document `$context` as normalized exported UoW context array.
  - [x] MUST remain format-neutral.
  - [x] MUST NOT depend on `core/kernel`, PSR-7/15, platform, or integrations.

- [x] `framework/packages/core/contracts/src/Runtime/Hook/AfterUowHookInterface.php`
  - [x] Change `afterUow(): void` to `afterUow(array $context, array $result): void`.
  - [x] Document `$context` as normalized exported UoW context array.
  - [x] Document `$result` as normalized exported UoW result array.
  - [x] MUST remain format-neutral.
  - [x] MUST NOT depend on `core/kernel`, PSR-7/15, platform, or integrations.

- [x] `framework/packages/core/contracts/tests/Contract/HookInterfacesDoNotDependOnPlatformTest.php`
  - [x] Update expectations for hook parameters.
  - [x] Assert `BeforeUowHookInterface::beforeUow(array $context): void`.
  - [x] Assert `AfterUowHookInterface::afterUow(array $context, array $result): void`.
  - [x] Assert hook methods accept normalized array payloads.
  - [x] Assert hooks still do not reference `core/kernel`, PSR-7/15, platform, or integrations.

- [x] `docs/adr/ADR-0006-reset-interface-uow-hooks.md`
  - [x] Update hook signatures from parameterless hooks to normalized array payload hooks.
  - [x] Clarify that contracts own the format-neutral hook port shape.
  - [x] Clarify that kernel owns the producer/normalization implementation.

- [x] `docs/ssot/uow-and-reset-contracts.md`
  - [x] Update hook method signatures.
  - [x] Add `Coretsia\Contracts\Runtime\KernelRuntimeInterface` as the external UoW runtime port.
  - [x] Clarify that contracts expose arrays only; kernel-owned UoW classes do not cross into contracts.

- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0020-kernel-runtime-uow-spi.md`

- [x] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php` — registers/binds runtime services + hook invoker
- [x] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
- [x] `framework/packages/core/kernel/README.md` — documents:
  - [x] KernelRuntime lifecycle (`begin → hooks → external runtime → after → reset`)
  - [x] reset boundary (`core/kernel` calls only `ResetOrchestrator::resetAll()`)
  - [x] no-PSR-7/15 invariant in kernel runtime
  - [x] hook discovery via `kernel.hook.before_uow` / `kernel.hook.after_uow`
  - [x] Platform/worker/scheduler adapters MUST depend on `Coretsia\Contracts\Runtime\KernelRuntimeInterface`.
  - [x] Adapters MUST NOT typehint or construct `Coretsia\Kernel\Runtime\KernelRuntime` directly.
  - [x] `Coretsia\Kernel\Runtime\KernelRuntime` is the `core/kernel` implementation bound to the contracts port by DI.

- [x] `framework/packages/core/kernel/composer.json`
  - [x] add runtime requirement:
    - [x] `psr/container`
    - [x] `psr/log`

#### Configuration (keys + defaults)

- Files:
  - N/A — this epic introduces no config files.
- Keys (dot):
  - N/A — this epic introduces no config keys.
- [x] Rules:
  - [x] `kernel.hook.before_uow` and `kernel.hook.after_uow` are kernel-owned canonical tag names.
  - [x] They MUST NOT be configurable via runtime config.
  - [x] `HookInvoker` MUST use the kernel-owned constants, not config-provided tag strings.
  - [x] `KernelRuntime` and hook discovery are baseline kernel infrastructure and MUST NOT be feature-disabled via config.
  - [x] Absence of hooks is represented by empty `TagRegistry` results, NOT by disabling the hook execution subsystem.

#### Wiring / DI tags (when applicable)

- [x] Kernel constants for already-canonical hook tags:
  - [x] `framework/packages/core/foundation/src/Tag/ReservedTags.php`
  - [x] constants:
    - [x] `KERNEL_HOOK_BEFORE_UOW = 'kernel.hook.before_uow'`
    - [x] `KERNEL_HOOK_AFTER_UOW  = 'kernel.hook.after_uow'`
    - [x] These constants are the canonical public owner constants for the reserved hook tags.
    - [x] Any package that is allowed to depend on `core/kernel` and needs these tags in runtime code MUST use:
      - [x] `Coretsia\Foundation\Tag\ReservedTags::KERNEL_HOOK_BEFORE_UOW`
      - [x] `Coretsia\Foundation\Tag\ReservedTags::KERNEL_HOOK_AFTER_UOW`
    - [x] Raw literal tag strings remain allowed only in docs/tests/fixtures for readability and MUST NOT be the preferred runtime-code pattern.
- [x] ServiceProvider wiring evidence:
  - [x] registers: `Coretsia\Kernel\Runtime\KernelRuntime`
  - [x] registers: `Coretsia\Kernel\Runtime\Hook\HookInvoker`
  - [x] binds: `Coretsia\Contracts\Runtime\KernelRuntimeInterface` → `Coretsia\Kernel\Runtime\KernelRuntime`

- [x] `KernelRuntime` MUST receive `ResetOrchestrator` via DI (constructor injection).
- [x] `KernelRuntime` MUST receive `Coretsia\Foundation\Context\ContextStore` via DI for begin-UoW base context writes.
- [x] `KernelRuntime` MUST receive `Coretsia\Contracts\Observability\CorrelationIdProviderInterface` via DI for reading an already-current `correlation_id`.
- [x] `KernelRuntime` MUST receive `Coretsia\Foundation\Id\CorrelationIdGenerator` via DI for generating a new `correlation_id` when the provider returns `null`.
- [x] `KernelRuntime` MUST receive `\Psr\Log\LoggerInterface` via DI for lifecycle summary logs.
- [x] `KernelRuntime` MUST receive `\Coretsia\Contracts\Observability\Tracing\TracerPortInterface` via DI for canonical `kernel.uow` span emission.
- [x] `KernelRuntime` MUST receive `\Coretsia\Contracts\Observability\Metrics\MeterPortInterface` via DI for canonical `kernel.uow_total` / `kernel.uow_duration_ms` metrics.
- [x] `KernelRuntime` MUST receive the default Foundation id generator via DI for canonical `uow_id` generation.
- [x] `KernelRuntime` MUST receive `Coretsia\Foundation\Id\IdGeneratorInterface` via DI for canonical `uow_id` generation.
- [x] `KernelRuntime` MUST receive `Coretsia\Foundation\Time\Stopwatch` via DI for canonical `durationMs` measurement.
- [x] `core/kernel` MUST NOT define `KERNEL_RESET` constants.
  - [x] Tag constant lives in `core/foundation`: `Coretsia\Foundation\Tag\ReservedTags::KERNEL_RESET`.

#### Artifacts / outputs (if applicable)

N/A

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [x] Context reads:
  - [x] `ContextKeys::CORRELATION_ID` — read indirectly through `CorrelationIdProviderInterface::correlationId()`.
  - [x] KernelRuntime MUST NOT read `ContextKeys::UOW_ID` or `ContextKeys::UOW_TYPE` from ContextStore; it creates and writes them for the current UoW.
- [x] Context writes (safe only):
  - [x] KernelRuntime MUST write base context keys at begin-UoW before the external runtime body is executed:
    - [x] `ContextKeys::CORRELATION_ID` (safe id)
    - [x] `ContextKeys::UOW_ID` (safe id)
    - [x] `ContextKeys::UOW_TYPE` (`http|cli|queue|scheduler`)
  - [x] `correlation_id` source is provider-first, generator-fallback:
    - [x] KernelRuntime MUST first read the current correlation id through `CorrelationIdProviderInterface::correlationId()`.
    - [x] If the provider returns a non-empty string, KernelRuntime MUST use that value.
    - [x] If the provider returns `null`, KernelRuntime MUST generate a new correlation id through `Coretsia\Foundation\Id\CorrelationIdGenerator`.
    - [x] KernelRuntime MUST NOT use `Coretsia\Foundation\Id\IdGeneratorInterface` or `foundation.ids.default` for `correlation_id`.
    - [x] Rationale: `correlation_id` remains ULID-backed per `1.210.0`, while `IdGeneratorInterface` is runtime-selectable in `1.220.0`.
  - [x] `uow_id` MUST come only from the default Foundation `IdGeneratorInterface`.
  - [x] `durationMs` MUST be measured only via `Stopwatch` and exported as non-negative `int`.
- [x] Reset discipline:
  - [x] reset executed via `ResetOrchestrator::resetAll()` against the effective Foundation reset discovery tag, opaque to kernel
  - [x] Kernel MUST NOT know, read, hardcode, or enumerate `foundation.reset.tag` / `kernel.reset`
  - [x] ContextStore reset MUST happen through Foundation reset orchestration, not by calling `ContextStore::reset()` directly from KernelRuntime
  - [x] no secrets/PII/session ids written into ContextStore

#### Observability (policy-compliant)

- [x] Spans:
  - [x] `kernel.uow` (attrs: `operation=uow_type`, `outcome`)
- [x] Metrics:
  - [x] `kernel.uow_total` (labels: `operation`, `outcome`)
  - [x] `kernel.uow_duration_ms` (labels: `operation`, `outcome`)
- [x] Logs:
  - [x] lifecycle summary only (no payloads, no env values)
- [x] Label normalization applied (if needed):
  - [x] `uow_type → operation`

#### Errors

- [x] Exceptions introduced:
  - [x] `framework/packages/core/kernel/src/Runtime/Exception/KernelRuntimeException.php` — errorCode `CORETSIA_KERNEL_RUNTIME_ERROR`
- [x] Mapping:
  - [x] kernel does not map to HTTP; mapping done by `platform/errors` + adapters

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] dotenv values
  - [x] env values
  - [x] auth values
  - [x] cookies
  - [x] session ids
  - [x] tokens
  - [x] credentials
  - [x] passwords
  - [x] headers
  - [x] raw SQL
  - [x] raw payloads
  - [x] raw context arrays
  - [x] raw hook payloads
  - [x] transport request/response data
  - [x] Throwable stack traces
  - [x] object dumps
  - [x] local absolute paths
  - [x] environment-specific bytes
- [x] Allowed:
  - [x] safe ids (`correlation_id`, `uow_id`)
  - [x] normalized `operation`
  - [x] normalized `outcome`
  - [x] stable reason token
  - [x] package-owned error code
  - [x] `hash/len` for sensitive debug strings if ever needed

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

- [x] Hook ordering invariant:
  - [x] `framework/packages/core/kernel/tests/Unit/HookInvokerDeterministicOrderTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeInvokesHooksInDeterministicOrderTest.php`
- [x] Hook payload normalization invariant:
  - [x] `framework/packages/core/kernel/tests/Unit/HookContextNormalizerRejectsNonJsonLikeValuesTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeExportsNormalizedHookPayloadsTest.php`
- [x] Foundation context handoff from `1.210.0`:
  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeWritesBaseContextKeysAtBeginUowTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeUsesCorrelationSourcesAndDefaultIdGeneratorTest.php`
- [x] Reset boundary + exactly-once semantics:
  - [x] `framework/packages/core/kernel/tests/Contract/KernelDoesNotEnumerateResetDiscoveryTagTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeResetHappensAfterAfterUowHooksTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeAlwaysResetsAfterUowTest.php`
- [x] Observability policy:
  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeEmitsPolicyCompliantObservabilityTest.php`
- [x] PSR-7/15-free public API:
  - [x] `framework/packages/core/kernel/tests/Contract/KernelPublicApiDoesNotExposePsr7Test.php`

#### Test harness / fixtures (when integration is needed)

- [x] Test harness / fixtures:
  - [x] Recording/fake logger, tracer, and meter test doubles cover observability integration.

### Tests (MUST)

- Unit:
  - [x] `framework/packages/core/kernel/tests/Unit/HookInvokerDeterministicOrderTest.php` (asserts: **preserves TagRegistry order**; no additional sorting)
  - [x] `framework/packages/core/kernel/tests/Unit/HookContextNormalizerRejectsNonJsonLikeValuesTest.php`
    - [x] rejects floats / `NaN` / `INF` / `-INF`
    - [x] rejects objects / resources
    - [x] failure MUST be deterministic and MUST NOT leak raw values
  - [x] `framework/packages/core/kernel/tests/Unit/HookContextNormalizerNormalizesErrorDescriptorTest.php`
- Contract:
  - [x] `framework/packages/core/kernel/tests/Contract/KernelPublicApiDoesNotExposePsr7Test.php`
    - [x] asserts Kernel implementation does not expose PSR-7/15
    - [x] asserts external runtime port is `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
    - [x] asserts Kernel does not define a competing `Coretsia\Kernel\Runtime\KernelRuntimeInterface`
  - [x] `framework/packages/core/kernel/tests/Contract/KernelDoesNotWriteToStdoutTest.php`
    - [x] token-scan `framework/packages/core/kernel/src/Runtime/**` and `src/Provider/**`
    - [x] MUST fail on `echo|print|var_dump|print_r|printf|error_log`
    - [x] MUST fail on `STDOUT|STDERR`, `php://stdout`, `php://stderr`, `php://output`
    - [x] tests/fixtures are excluded
  - [x] `framework/packages/core/kernel/tests/Contract/KernelDoesNotEnumerateResetDiscoveryTagTest.php`
    - [x] MUST fail if `core/kernel/src/**` contains any direct reset-discovery knowledge, including:
      - [x] string literal `kernel.reset`
      - [x] reads of config key `foundation.reset.tag`
      - [x] references to `Coretsia\Foundation\Tag\ReservedTags::KERNEL_RESET`
      - [x] direct reset-service enumeration via `TagRegistry`
    - [x] Allowed boundary:
      - [x] dependency on `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`
      - [x] calling only `ResetOrchestrator::resetAll(): void`
    - [x] MUST fail if `framework/packages/core/kernel/src/Runtime/**` imports `Coretsia\Contracts\Runtime\ResetInterface`.
    - [x] MUST fail if KernelRuntime calls `ResetInterface::reset()` directly.

  - [x] `framework/packages/core/contracts/tests/Contract/KernelRuntimeInterfaceIsFormatNeutralContractTest.php`
  - [x] `framework/packages/core/contracts/tests/Contract/HookInterfacesDoNotDependOnPlatformTest.php`
    - [x] updated for array payload signatures
- Integration:
  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeEmitsPolicyCompliantObservabilityTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeRejectsInvalidExportedContextTest.php`
    - [x] asserts invalid/missing `uowId`, `type`, `startedAt`, `correlationId`, or `attributes` fails deterministically.
    - [x] asserts invalid context failure does not leak raw payload values.
    - [x] asserts invalid UoW type fails deterministically.
    - [x] asserts invalid outcome fails deterministically.
    - [x] asserts invalid extensions fail deterministically with `KernelRuntimeException::REASON_INVALID_RESULT`.
    - [x] asserts invalid type/outcome failures do not leak raw payload values.
    - [x] covers invalid low-level lifecycle inputs, including exported context shape, UoW type, and outcome token.
    - [x] asserts `afterUnitOfWork()` resets exactly once when exported context validation fails.
    - [x] asserts `afterUnitOfWork()` resets exactly once when outcome validation fails.
    - [x] asserts invalid after-input failure remains primary if reset also fails.

  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeInvokesHooksInDeterministicOrderTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeResetHappensAfterAfterUowHooksTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeAlwaysResetsAfterUowTest.php` MUST assert:
    - [x] after-uow hooks ran **before** reset trigger
      - [x] reset trigger ran **exactly once per UoW**
      - [x] reset trigger runs in exception path (try/finally semantics)
    - [x] when an `after-uow` hook throws, reset still runs exactly once before the failure is surfaced
    - [x] asserts external body throwable remains primary when an after-uow hook also throws.
    - [x] asserts after-uow hook throwable is surfaced when there is no external body throwable.
    - [x] asserts reset runs when a before-uow hook throws.
    - [x] asserts external body is not executed when a before-uow hook throws.
    - [x] asserts after-uow hooks still run with `fatal_error` outcome after a before-uow hook failure.
    - [x] asserts before-hook throwable remains primary when an after-uow hook also throws.
    - [x] asserts reset failure is surfaced when no earlier primary failure exists.
    - [x] asserts reset failure does not replace an external body / before-hook / after-hook primary failure.
    - [x] asserts reset failure diagnostics remain safe.
    - [x] asserts low-level `beginUnitOfWork()` resets when a before-uow hook throws after base context writes.
    - [x] asserts low-level `beginUnitOfWork()` does not leave ContextStore dirty after before-hook failure.
    - [x] asserts the before-hook throwable remains the surfaced failure after low-level begin reset.

  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeExportsNormalizedHookPayloadsTest.php`
    - [x] maps recursively sorted by `strcmp`
    - [x] list order preserved
    - [x] exported ctx/result passed to hooks are json-like only
    - [x] if `UnitOfWorkResult.error` exists internally as `ErrorDescriptor`, hooks MUST receive a normalized json-like `error` map, never an object
  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeWritesBaseContextKeysAtBeginUowTest.php`
    - [x] asserts `ContextStore` contains `correlation_id`, `uow_id`, `uow_type` before the external runtime body is executed
    - [x] asserts written keys use `ContextKeys::CORRELATION_ID`, `ContextKeys::UOW_ID`, and `ContextKeys::UOW_TYPE`
    - [x] asserts values pass `ContextStorePolicy`

  - [x] `framework/packages/core/kernel/tests/Integration/KernelRuntimeUsesCorrelationSourcesAndDefaultIdGeneratorTest.php`
    - [x] asserts `correlation_id` comes from `CorrelationIdProviderInterface` when the provider returns a non-empty string
    - [x] asserts KernelRuntime falls back to `Coretsia\Foundation\Id\CorrelationIdGenerator` when the provider returns `null`
    - [x] asserts fallback `correlation_id` matches canonical ULID format `/\A[0-9A-HJKMNP-TV-Z]{26}\z/`
    - [x] asserts fallback `correlation_id` is not affected by `foundation.ids.default`
    - [x] asserts `uow_id` comes from the default Foundation `IdGeneratorInterface`

- Gates/Arch:
  - [x] PSR-7/15-free gate for core/kernel (existing)

### DoD (MUST)

- [x] Deliverables complete (creates+modifies), paths exact
- [x] Preconditions satisfied (no forward references)
- [x] deps/forbidden respected (deptrac; no cycles)
- [x] Verification tests present where applicable
- [x] Determinism: deterministic hook invocation
- [x] Docs updated:
  - [x] `framework/packages/core/kernel/README.md`
  - [x] `docs/adr/ADR-0020-kernel-runtime-uow-spi.md`
  - [x] `docs/adr/ADR-0006-reset-interface-uow-hooks.md`
  - [x] `docs/adr/INDEX.md`
  - [x] `docs/ssot/uow-and-reset-contracts.md`
- [x] Дає канонічний **UoW orchestrator**: begin → hooks → external runtime (http/cli/worker) → after → reset.
- [x] Гарантує **однаковий lifecycle** для HTTP/CLI/Queue/Scheduler без PSR-7/15 у kernel.
- [x] `runUnitOfWork()` гарантує: after-phase виконується завжди і reset відбувається рівно 1 раз.
- [x] Low-level adapters using `beginUnitOfWork()` / `afterUnitOfWork()` MUST call `afterUnitOfWork()` in `finally`.
- [x] Non-goals / out of scope:
  - [x] Kernel НЕ реалізує HTTP pipeline / middleware (це `platform/http`).
  - [x] Kernel НЕ робить feature-specific flush логіки (events/outbox/audit тощо) — тільки hooks.
  - [x] Kernel НЕ залежить від `platform/*` і НЕ тягне PSR-7/15.
- [x] Usually present when enabled in presets/bundles:
  - [x] `platform/http` SHOULD wrap each request through `runUnitOfWork(type=http, ...)` when full lifecycle delegation is possible; otherwise it MUST use `beginUnitOfWork()` / `afterUnitOfWork()` with `afterUnitOfWork()` in `finally`.
  - [x] `platform/cli` SHOULD wrap each command through `runUnitOfWork(type=cli, ...)` when full lifecycle delegation is possible; otherwise it MUST use low-level primitives with `afterUnitOfWork()` in `finally`.
  - [x] observability ports are resolvable in the micro preset via Foundation noop bindings from `1.205.0`; later `platform/logging|tracing|metrics` packages MAY override these bindings when enabled in a preset/bundle.
- [x] Discovery / wiring via kernel-owned tags:
  - [x] `kernel.hook.before_uow`
  - [x] `kernel.hook.after_uow`
- [x] Reset integration boundary:
  - [x] `core/kernel` MUST NOT own, resolve, enumerate, or hardcode `kernel.reset`
  - [x] `core/kernel` MUST call ONLY:
    - [x] `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator::resetAll(): void`
- [x] When an exception happens in HTTP runtime but is handled into an error response, then `afterUnitOfWork()` still runs and ContextStore is reset before the next request.
- [x] Kernel MUST NOT care whether enhanced reset (1.250.0) is enabled.
  - [x] Kernel always calls `ResetOrchestrator::resetAll()`.
  - [x] Ordering + meta parsing is entirely owned by `core/foundation`.
- [x] Foundation context lifecycle handoff from `1.210.0` implemented:
  - [x] Base context keys are written at begin-UoW before the external runtime body is executed:
    - [x] `correlation_id`
    - [x] `uow_id`
    - [x] `uow_type`
  - [x] `correlation_id` is resolved provider-first:
    - [x] use `CorrelationIdProviderInterface::correlationId()` when it returns a non-empty string;
    - [x] otherwise generate through `Coretsia\Foundation\Id\CorrelationIdGenerator`.
  - [x] `correlation_id` MUST NOT use `Coretsia\Foundation\Id\IdGeneratorInterface` and MUST NOT be affected by `foundation.ids.default`.
  - [x] `uow_id` is sourced from the default Foundation `IdGeneratorInterface`.
  - [x] `uow_type` is the normalized runtime operation type.
  - [x] Context reset is executed after UoW only through `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator::resetAll(): void`.
  - [x] KernelRuntime never calls `ContextStore::reset()` directly.

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
- "Bootstrap Phase A: env source policy + dotenv + minimal overrides (optional)"
- "Boot without skeleton/config/* (bare skeleton safe)"
- "Deterministic env source precedence: strict_dotenv vs allow_system"
- "Deterministic app target selection (`web|api|console|worker`) as a minimal boot input"
- "Selected app target resolves the app root under `skeleton/apps/<app>/` without filesystem scanning"
- "App target selection is entrypoint-owned input; it is NOT inferred by probing `skeleton/apps/*`"
- "Deterministic preset selection: explicit input → app.php per-app preset → app.php global preset → package default"

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

### Bootstrap Phase A input boundary (MUST)

- Bootstrap Phase A is a minimal boot-input phase, not a full config merge phase.
- Phase A MUST resolve only:
  - `skeletonRoot`
  - `appTarget`
  - `appEnv`
  - `preset` selected from explicit input, bootstrap-only per-app/global preset overrides, or package default
  - `debug`
  - `envSourcePolicy`
  - `appRoot`
  - immutable `EnvRepositoryInterface` snapshot
- Phase A MAY consume optional bootstrap-only `presets` map from `skeleton/config/app.php` only to resolve final `preset`.
- `presets` MUST NOT be stored in `BootstrapConfig`.
- Phase A MUST NOT read full skeleton config files.
- Phase A MUST NOT read:
  - `skeleton/config/roots.php`
  - `skeleton/config/<root>.php`
  - `skeleton/config/environments/**`
  - `skeleton/apps/<appTarget>/config/**`
- Phase A MAY read only bootstrap-only overrides from:
  - `skeleton/config/app.php`
- `skeleton/config/app.php` is a bootstrap-only input file.
- `skeleton/config/app.php` MAY define:
  - `appEnv`
  - `preset`
  - `presets`
  - `debug`
- `presets` is a bootstrap-only per-app preset map.
- `presets` MUST NOT participate in ConfigKernel Phase B merge.
- `presets` MUST NOT be used as module enable/disable composition.
- Module enable/disable composition remains owned by `1.310.0` ModulePlan from preset files + Composer metadata.
- `skeleton/config/app.php` MUST NOT participate in ConfigKernel Phase B merge.
- ConfigKernel Phase B owns full config file discovery, merge, directives, validation, explain, and env overlays.

### BootstrapConfig resolution boundary (MUST)

- `BootstrapConfig` is a resolved immutable VO only.
- `BootstrapConfig` MUST NOT resolve optional values from `BootstrapInput`.
- `BootstrapConfig` MUST NOT read package defaults.
- `BootstrapConfig` MUST NOT read `skeleton/config/app.php`.
- `BootstrapConfig` MUST NOT read dotenv files.
- `BootstrapConfig` MUST NOT read system env.
- `BootstrapConfig` MUST NOT expose `fromInput()` or any method that performs Phase A resolution.
- Resolution from:
  - explicit `BootstrapInput`;
  - bootstrap-only overrides from `skeleton/config/app.php`;
  - package defaults from `kernel.boot.*` and `kernel.env.*`;
    MUST be owned by an internal resolver/builder layer.
- The canonical internal owner is:
  - `Coretsia\Kernel\Boot\BootstrapConfigResolver`
- `BootstrapConfigResolver` MUST return a resolved `BootstrapConfig`.
- `BootstrapConfigResolver` MUST NOT build `EnvRepositoryInterface`.
- `EnvRepositoryBuilder` MUST consume a resolved `BootstrapConfig`; it MUST NOT resolve `BootstrapConfig` itself.

### Preset resolution precedence (MUST)

`preset` resolution is single-choice and MUST be deterministic.

Final selected preset MUST be resolved in this order:

1. explicit `BootstrapInput.preset`;
2. `skeleton/config/app.php` per-app `presets[appTarget]`;
3. `skeleton/config/app.php` global `preset`;
4. package default `kernel.boot.default_preset`.

`BootstrapInput.preset` MUST always win over `skeleton/config/app.php`.

`skeleton/config/app.php` `presets[appTarget]` MUST win over `skeleton/config/app.php` global `preset` only for the currently selected explicit `appTarget`.

If `presets` does not contain the selected `appTarget`, resolver MUST fall back to global `preset`.

If neither `presets[appTarget]` nor global `preset` exists, resolver MUST fall back to `kernel.boot.default_preset`.

`presets` MUST NOT select or infer `appTarget`.

`appTarget` remains entrypoint-owned input and MUST NOT be read from `skeleton/config/app.php`.

### Bootstrap-only app.php shape examples (MUST)

Global preset for all app targets:

```php
return [
    'preset' => 'hybrid',
    'debug' => true,
    'appEnv' => 'local',
];
```

Per-app presets with global fallback:

```php
return [
    'preset' => 'hybrid',
    'presets' => [
        'api' => 'micro',
        'worker' => 'enterprise',
    ],
    'debug' => true,
    'appEnv' => 'local',
];
```

For selected `appTarget=api`, final preset is `micro`.

For selected `appTarget=worker`, final preset is `enterprise`.

For selected `appTarget=web`, final preset is `hybrid`.

For selected `appTarget=console`, final preset is `hybrid`.

Fully explicit per-app presets:

```php
return [
    'preset' => 'hybrid',
    'presets' => [
        'api' => 'micro',
        'web' => 'express',
        'console' => 'hybrid',
        'worker' => 'enterprise',
    ],
    'debug' => true,
    'appEnv' => 'local',
];
```

When both `preset` and matching `presets[appTarget]` exist, matching `presets[appTarget]` wins over global `preset`.

When explicit `BootstrapInput.preset` exists, explicit `BootstrapInput.preset` wins over both `presets[appTarget]` and global `preset`.

### Deliverables (MUST)

#### Creates

- [x] `framework/packages/core/kernel/src/Boot/BootstrapEnvSourcePolicy.php`
  - [x] Kernel-owned Phase A env source precedence enum.
  - [x] Allowed values:
    - [x] `strict_dotenv`
    - [x] `allow_system`
  - [x] MUST NOT be confused with `Coretsia\Contracts\Env\EnvPolicy`.
  - [x] `Coretsia\Contracts\Env\EnvPolicy` remains missing-value policy only: `required|optional|defaulted`.

- [x] `framework/packages/core/kernel/src/Boot/ArrayEnvRepository.php`
  - [x] Internal immutable implementation of `Coretsia\Contracts\Env\EnvRepositoryInterface`.
  - [x] Stores a normalized `array<string,string>` snapshot.
  - [x] Stores optional safe `ConfigValueSource` metadata per env key.
  - [x] `has()` returns true for present empty string.
  - [x] `get()` returns `EnvValue::present($value)` when present.
  - [x] `get()` returns `EnvValue::missing()` when missing.
  - [x] `all()` returns a copy of present raw env values for runtime owners only.
  - [x] `sourceOf()` returns safe source metadata only, never raw values.
  - [x] MUST NOT read from `$_ENV`, `$_SERVER`, or `getenv()` after construction.
  - [x] MUST NOT expose raw env values in diagnostics.
  - [x] MUST be marked `@internal`.
  - [x] MUST NOT be added to `PUBLIC_API.md`.

- [x] `framework/packages/core/kernel/src/Boot/DotenvLoader.php`
  - [x] Parses allowed dotenv files safely.
  - [x] Dotenv file template expansion MUST use the already selected Phase A `appEnv`.
  - [x] MUST consume resolved `BootstrapConfig`.
  - [x] MUST NOT resolve `appEnv`.
  - [x] MUST NOT read `BootstrapInput`.
  - [x] MUST NOT read `skeleton/config/app.php`.
  - [x] MUST NOT apply package boot defaults.
  - [x] `appEnv` selection MUST NOT depend on reading `.env.<env>` before `<env>` is known.
  - [x] If `appEnv` is absent from explicit `BootstrapInput` and `skeleton/config/app.php`, package default `kernel.boot.default_env` is used for dotenv template expansion.
  - [x] Returns normalized dotenv key/value snapshot plus safe source metadata.
  - [x] MUST NOT apply system-env precedence.
  - [x] MUST NOT use `Coretsia\Contracts\Env\EnvPolicy`.
  - [x] MUST NOT expose raw dotenv values in diagnostics.
  - [x] Filesystem-safety rules for entries:
    - [x] no `/`
    - [x] no `\`
    - [x] no `..`
    - [x] no `NUL`
    - [x] no drive letters
    - [x] no stream wrappers

- [x] `framework/packages/core/kernel/src/Boot/EnvRepositoryBuilder.php` — builds `EnvRepositoryInterface`
  - [x] MUST build an immutable env repository snapshot.
  - [x] MUST use `ArrayEnvRepository`.
  - [x] MUST NOT create a mutable repository.
  - [x] MUST NOT expose raw env values in diagnostics.
  - [x] MUST snapshot system env once before applying precedence.
  - [x] MUST NOT continue reading `$_ENV`, `$_SERVER`, or `getenv()` after repository construction.
  - [x] MUST apply `BootstrapEnvSourcePolicy`, not `Coretsia\Contracts\Env\EnvPolicy`.
  - [x] MUST treat `Coretsia\Contracts\Env\EnvPolicy` as missing-value policy only.
  - [x] MUST preserve present-empty-string as present.
  - [x] MUST preserve missing as `EnvValue::missing()`.
  - [x] MUST attach safe `ConfigValueSource` metadata where available.
  - [x] MUST consume a resolved `BootstrapConfig`.
  - [x] MUST NOT resolve `BootstrapConfig`.
  - [x] MUST NOT read `BootstrapInput` optional values.
  - [x] MUST NOT read `skeleton/config/app.php`.
  - [x] MUST NOT apply package boot defaults.
  - [x] Env source precedence MUST be deterministic:
    - [x] `strict_dotenv`:
      - [x] dotenv values win.
      - [x] system env values are ignored for keys present in dotenv.
      - [x] missing dotenv values remain missing in `EnvRepository`.
      - [x] system env fallback is forbidden.
    - [x] `allow_system`:
      - [x] system env values win.
      - [x] dotenv values are used only when the same key is absent from system env.
  - [x] Canonical env snapshot order:
    - [x] resolved `BootstrapConfig` is consumed first;
    - [x] dotenv files are loaded using resolved `BootstrapConfig::appEnv()`;
    - [x] system env is snapshotted once;
    - [x] dotenv/system precedence is applied according to resolved `BootstrapConfig::envSourcePolicy()`;
    - [x] immutable `EnvRepositoryInterface` snapshot is returned.

- [x] `framework/packages/core/kernel/src/Boot/BootstrapOverridesLoader.php` — reads optional bootstrap-only overrides:
  - [x] `skeleton/config/app.php`
  - [x] this file is a Phase A bootstrap-only input, NOT a reserved config root, and MUST NOT participate in Phase B config merge
  - [x] allowed override keys are:
    - [x] `appEnv`
    - [x] `preset`
    - [x] `presets`
    - [x] `debug`
  - [x] `preset` is a global fallback preset override.
  - [x] `presets` is a per-app preset override map.
  - [x] `presets` MAY be partial.
  - [x] `presets` keys MUST be valid app targets:
    - [x] `web`
    - [x] `api`
    - [x] `console`
    - [x] `worker`
  - [x] `presets` values MUST be non-empty safe strings.
  - [x] `presets` MUST be a string-keyed map of appTarget => presetName.
  - [x] `presets` MUST NOT be a list/sequence.
  - [x] Empty `presets` map is allowed and behaves as absent.
  - [x] `presets` MUST NOT contain unknown app target keys.
  - [x] `presets` MUST NOT infer or select app target.
  - [x] `BootstrapOverridesLoader` MUST accept only an array return value.
  - [x] Unknown top-level keys MUST fail deterministically with `BootstrapException::REASON_OVERRIDES_INVALID`.
  - [x] Unknown `presets` keys MUST fail deterministically with `BootstrapException::REASON_OVERRIDES_INVALID`.
  - [x] `appEnv` MUST be non-empty safe string.
  - [x] `preset` MUST be non-empty safe string.
  - [x] `debug` MUST be bool.
  - [x] `skeleton/config/modules.php` MUST NOT be read here.
  - [x] module enable/disable composition is resolved only by `1.310.0` ModulePlan from preset files + Composer metadata.
  - [x] Values MUST NOT be logged or embedded in exception messages.
  - [x] The loader MUST NOT include/require any file except `skeleton/config/app.php`.

- [x] `framework/packages/core/kernel/src/Boot/BootstrapConfigResolver.php` — resolves `BootstrapConfig`
  - [x] Internal resolver for resolved Bootstrap Phase A config.
  - [x] MUST use explicit `BootstrapInput` values first:
    - [x] `appEnv`
    - [x] `preset`
    - [x] `debug`
    - [x] `envSourcePolicy`
  - [x] MUST use `BootstrapOverridesLoader` second for bootstrap-only overrides:
    - [x] `appEnv`
    - [x] `preset`
    - [x] `presets`
    - [x] `debug`
  - [x] MUST use package defaults only as fallback:
    - [x] `kernel.boot.default_env`
    - [x] `kernel.boot.default_preset`
    - [x] `kernel.boot.default_debug`
    - [x] `kernel.env.source_policy.default_local`
    - [x] `kernel.env.source_policy.default_production`
  - [x] Preset resolution order MUST be:
    - [x] explicit `BootstrapInput.preset`
    - [x] `skeleton/config/app.php` `presets[appTarget]`
    - [x] `skeleton/config/app.php` `preset`
    - [x] `kernel.boot.default_preset`
  - [x] `presets[appTarget]` MUST be evaluated only for the already selected `BootstrapInput.appTarget()`.
  - [x] `presets[appTarget]` MUST NOT select or modify app target.
  - [x] If `presets` exists but does not contain selected `appTarget`, resolver MUST fall back to global `preset`.
  - [x] If global `preset` is absent, resolver MUST fall back to `kernel.boot.default_preset`.
  - [x] MUST resolve `envSourcePolicy` after final `appEnv` is selected.
  - [x] For local-like env selection, default policy MUST be `strict_dotenv`.
  - [x] For production-like env selection, default policy MUST be `allow_system`.
  - [x] MUST return `BootstrapConfig`.
  - [x] MUST NOT build `EnvRepositoryInterface`.
  - [x] MUST NOT parse dotenv files.
  - [x] MUST NOT read system env.
  - [x] MUST NOT scan `skeleton/apps/*`.
  - [x] MUST NOT require `appRoot` to exist.
  - [x] MUST NOT expose raw override values in diagnostics.
  - [x] MUST be marked `@internal`.
  - [x] MUST NOT be added to `PUBLIC_API.md`.
  - [x] Production-like env names are exactly:
    - [x] `prod`
    - [x] `production`
  - [x] Production-like env names default to `allow_system`.
  - [x] Every other env name defaults to `strict_dotenv`.
  - [x] `staging` defaults to `strict_dotenv`.
  - [x] Non-production envs that need system env precedence MUST pass explicit `BootstrapEnvSourcePolicy::AllowSystem` through `BootstrapInput`.
  - [x] Phase A validates only preset value shape, not preset existence.
  - [x] Preset file existence and preset schema validation are owned by `1.310.0` ModulePlan.
  - [x] Phase A MUST NOT read `kernel.modes.defaults_path` or `kernel.modes.overrides_path`.
  - [x] Phase A MUST NOT load `resources/modes/*.php` or `skeleton/config/modes/*.php`.

- [x] `framework/packages/core/kernel/src/Boot/BootstrapInput.php`
  - [x] Immutable VO for Phase A entrypoint-owned inputs.
  - [x] Fields:
    - [x] `skeletonRoot`
    - [x] `appTarget`
    - [x] optional `appEnv`
    - [x] optional `preset`
    - [x] optional `debug`
    - [x] optional `envSourcePolicy`
  - [x] MUST NOT read filesystem.
  - [x] MUST NOT infer app target.
  - [x] MUST NOT contain raw env values.

- [x] `framework/packages/core/kernel/src/Boot/AppTarget.php`
  - [x] Canonical allowed targets:
    - [x] `web`
    - [x] `api`
    - [x] `console`
    - [x] `worker`
  - [x] Invalid target fails with `BootstrapException`.
  - [x] Error message MUST NOT include raw input.

- [x] `framework/packages/core/kernel/src/Boot/BootstrapConfig.php`
  - [x] Immutable VO with:
    - [x] `appEnv: non-empty-string`
    - [x] `preset: non-empty-string`
    - [x] `debug: bool`
    - [x] `envSourcePolicy: BootstrapEnvSourcePolicy`
    - [x] `appTarget: web|api|console|worker`
    - [x] `skeletonRoot: string`
    - [x] `appRoot: string`
  - [x] `appRoot` MUST be derived deterministically as `skeletonRoot/apps/<appTarget>`.
  - [x] MUST NOT scan `skeleton/apps/*`.
  - [x] MUST NOT require the app root to exist during Phase A unless explicitly configured by a later boot phase.
  - [x] MUST remain a resolved VO only.
  - [x] MUST NOT expose `fromInput()`.
  - [x] MUST NOT resolve defaults, overrides, dotenv, or system env.

- [x] `framework/packages/core/kernel/src/Boot/Exception/BootstrapException.php`
  - [x] Extends `\RuntimeException`.
  - [x] `public const string ERROR_CODE = 'CORETSIA_BOOTSTRAP_FAILED'`.
  - [x] Stable reason constants:
    - [x] `REASON_INVALID_APP_TARGET = 'bootstrap-invalid-app-target'`
    - [x] `REASON_INVALID_SKELETON_ROOT = 'bootstrap-invalid-skeleton-root'`
    - [x] `REASON_DOTENV_FILE_INVALID = 'bootstrap-dotenv-file-invalid'`
    - [x] `REASON_DOTENV_LOAD_FAILED = 'bootstrap-dotenv-load-failed'`
    - [x] `REASON_OVERRIDES_INVALID = 'bootstrap-overrides-invalid'`
    - [x] `REASON_ENV_SOURCE_POLICY_INVALID = 'bootstrap-env-source-policy-invalid'`
  - [x] Message MUST contain only `ERROR_CODE` and reason token.
  - [x] Message MUST NOT contain dotenv values, absolute paths, raw PHP warnings, OS error messages, or raw override arrays.

- [x] `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`
  - [x] documents deterministic preset resolution precedence:
    - [x] explicit `BootstrapInput.preset`
    - [x] `skeleton/config/app.php` `presets[appTarget]`
    - [x] `skeleton/config/app.php` global `preset`
    - [x] `kernel.boot.default_preset`
  - [x] documents that `presets` is a bootstrap-only per-app preset map.
  - [x] documents that `presets` does not select, infer, or modify `appTarget`.
  - [x] documents that module enable/disable composition remains owned by 1.310.0 ModulePlan.
  - [x] documents that `BootstrapConfig` is a resolved VO only.
  - [x] documents that `BootstrapConfigResolver` owns Phase A config resolution.
  - [x] documents that `EnvRepositoryBuilder` owns env snapshot construction only.
  - [x] documents why public `Bootstrapper` / `BootstrapResult` are not introduced.

- [x] `framework/packages/core/kernel/tests/Integration/BootstrapSelectsExplicitAppTargetTest.php`
  - [x] accepts `web`
  - [x] accepts `api`
  - [x] accepts `console`
  - [x] accepts `worker`
  - [x] resolves `appRoot` as `skeleton/apps/<target>`
  - [x] does not scan `skeleton/apps/*`
  - [x] invalid target fails with `BootstrapException::REASON_INVALID_APP_TARGET`
  - [x] invalid target failure does not leak raw input

- [x] `framework/packages/core/kernel/tests/Integration/BootstrapDoesNotScanSkeletonAppsTest.php`
  - [x] creates multiple synthetic app dirs
  - [x] selected app remains explicit input
  - [x] absence/presence of sibling app dirs does not affect result

- [x] `framework/packages/core/kernel/tests/Integration/BootstrapOverridesLoaderReadsOnlyAppPhpTest.php`
  - [x] reads `skeleton/config/app.php` when present
  - [x] allows only `appEnv`, `preset`, `presets`, `debug`
  - [x] does not read `skeleton/config/modules.php`
  - [x] unknown override keys fail deterministically
  - [x] unknown `presets` app target keys fail deterministically
  - [x] raw override values do not leak in exception messages

- [x] `framework/packages/core/kernel/tests/Integration/BootstrapPresetResolutionPrecedenceTest.php`
  - [x] explicit `BootstrapInput.preset` wins over `skeleton/config/app.php` `presets[appTarget]`
  - [x] `skeleton/config/app.php` `presets[appTarget]` wins over global `preset`
  - [x] global `preset` is used when `presets` does not contain selected `appTarget`
  - [x] `kernel.boot.default_preset` is used when neither explicit input nor app.php preset exists
  - [x] `presets` does not select or modify app target
  - [x] diagnostics do not leak raw preset values
  - [x] empty `presets` map behaves as absent
  - [x] Phase A does not require selected preset file to exist

- [x] `framework/packages/core/kernel/tests/Contract/KernelBootstrapDoesNotUseRuntimeLifecycleTest.php`
  - [x] scans `src/Boot/**`
  - [x] fails on `ResetOrchestrator`
  - [x] fails on `TagRegistry`
  - [x] fails on `ResetInterface`
  - [x] fails on `KernelRuntime`
  - [x] fails on `kernel.reset`

#### Modifies

- [x] `framework/packages/core/kernel/PUBLIC_API.md`
  - [x] add public boot API symbols that are intentionally public:
    - [x] `Coretsia\Kernel\Boot\AppTarget`
    - [x] `Coretsia\Kernel\Boot\BootstrapConfig`
    - [x] `Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy`
    - [x] `Coretsia\Kernel\Boot\BootstrapInput`
    - [x] `Coretsia\Kernel\Boot\Exception\BootstrapException`
  - [x] keep internal implementation helpers marked `@internal`:
    - [x] `BootstrapConfigResolver`
    - [x] `DotenvLoader`
    - [x] `EnvRepositoryBuilder`
    - [x] `BootstrapOverridesLoader`

- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`
- [x] `framework/packages/core/kernel/config/kernel.php` — adds boot/env policy defaults
- [x] `framework/packages/core/kernel/config/rules.php` — enforces shape
- [x] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php`
  - [x] registers Phase A boot services:
    - [x] `BootstrapConfigResolver`
    - [x] `DotenvLoader`
    - [x] `EnvRepositoryBuilder`
    - [x] `BootstrapOverridesLoader`
- [x] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php`
  - [x] deterministic factory wiring for Phase A boot services
  - [x] includes factory wiring for `BootstrapConfigResolver`
  - [x] MUST NOT keep mutable runtime state

- [x] `framework/packages/core/kernel/tests/Contract/KernelDoesNotWriteToStdoutTest.php`
  - [x] add token-scan `framework/packages/core/kernel/src/Boot/**`
  - [x] renamed from `KernelRuntimeDoesNotWriteToStdoutTest`

#### Configuration (keys + defaults)

- [x] Files:
  - [x] `framework/packages/core/kernel/config/kernel.php`
- [x] Keys (dot):
  - [x] `kernel.boot.default_env` = "local"
  - [x] `kernel.boot.default_preset` = "micro"
  - [x] `kernel.boot.default_debug` = false
  - [x] `kernel.env.source_policy.default_local` = "strict_dotenv"
  - [x] `kernel.env.source_policy.default_production` = "allow_system"
  - [x] `kernel.env.dotenv.files` = [".env",".env.local",".env.<env>",".env.<env>.local"]
- [x] Rules:
  - [x] `framework/packages/core/kernel/config/rules.php` enforces shape

- [x] Dotenv files are resolved relative to `skeletonRoot`.
- [x] File names are normalized names only, not arbitrary paths.
- [x] Entries containing `/`, `\`, `..`, NUL, drive letters, or stream wrappers are rejected.
- [x] Missing dotenv files are skipped deterministically.
- [x] Unreadable dotenv files fail with `BootstrapException`.
- [x] Diagnostics may include only normalized dotenv file names, never absolute paths.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [x] Logs: N/A for Kernel Bootstrap Phase A.
  - [x] Kernel Phase A boot code does not log boot errors.
  - [x] Future platform/cli diagnostics MAY log only safe keys and `hash(value)` / `len(value)`, never dotenv values.

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] `.env` values, secrets, tokens
- [x] Allowed:
  - [x] dotenv file names (normalized), hash/len (never raw)

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

- [x] Redaction does not leak:
  - [x] `framework/packages/core/kernel/tests/Integration/BootstrapDotenvRespectedUnderStrictPolicyTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/BootstrapSystemEnvOverridesDotenvUnderAllowSystemPolicyTest.php`

### Tests (MUST)

- Integration:
  - [x] `framework/packages/core/kernel/tests/Integration/BootstrapWorksWithoutAnySkeletonConfigFilesTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/BootstrapDotenvRespectedUnderStrictPolicyTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/BootstrapSystemEnvOverridesDotenvUnderAllowSystemPolicyTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/KernelDoesNotWriteToStdoutTest.php`
    - [x] token-scan:
      - [x] `framework/packages/core/kernel/src/Runtime/**`
      - [x] `framework/packages/core/kernel/src/Provider/**`
      - [x] `framework/packages/core/kernel/src/Boot/**`
    - [x] MUST fail on `echo|print|var_dump|print_r|printf|error_log`
    - [x] MUST fail on `STDOUT|STDERR`, `php://stdout`, `php://stderr`, `php://output`
    - [x] tests/fixtures are excluded
  - [x] `framework/packages/core/kernel/tests/Integration/BootstrapPresetResolutionPrecedenceTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/BootstrapOverridesLoaderReadsOnlyAppPhpTest.php`

### DoD (MUST)

- [x] Phase A boot succeeds without skeleton config
- [x] `BootstrapEnvSourcePolicy` precedence matches ADR/SSoT and is tested.
- [x] Bootstrap Phase A includes deterministic app target selection:
  - [x] selected app is an explicit input (`web|api|console|worker`)
  - [x] selected app root resolves to `skeleton/apps/<app>/`
  - [x] bootstrap MUST NOT scan `skeleton/apps/*` to auto-detect the app
- [x] Bootstrap Phase A includes deterministic preset selection:
  - [x] explicit `BootstrapInput.preset` wins first
  - [x] `skeleton/config/app.php` `presets[appTarget]` wins second for the already selected `BootstrapInput.appTarget()`
  - [x] `skeleton/config/app.php` global `preset` wins third
  - [x] `kernel.boot.default_preset` is the final fallback
  - [x] `presets` MUST NOT select, infer, or modify `appTarget`
- [x] No secret leakage in error paths
- [x] Non-goals / out of scope:
  - [x] Phase A НЕ читає merged config через ConfigKernel (Phase B).
    Phase A uses only EnvRepository + optional bootstrap overrides; internal defaults MUST be deterministic
    and MUST match the package defaults declared later under `kernel.boot.*` / `kernel.env.*` keys.
  - [x] Phase A не сканує filesystem packages; module discovery — лише через composer metadata (епік module plan).
- [x] Kernel boot implementation MAY be informed by Phase 0 spikes.
- [x] Kernel boot runtime code MUST NOT import or include spike code.
- [x] Kernel boot runtime code MUST NOT depend on `coretsia/devtools-internal-toolkit`.
- [x] Any reused algorithm must be reimplemented in production-owned kernel/foundation code with tests.
- [x] `framework/packages/core/kernel/src/Boot/**` MUST NOT import:
  - [x] `Coretsia\Kernel\Runtime\KernelRuntime`
  - [x] `Coretsia\Kernel\Runtime\Hook\HookInvoker`
  - [x] `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator`
  - [x] `Coretsia\Foundation\Tag\TagRegistry`
  - [x] `Coretsia\Contracts\Runtime\ResetInterface`

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

- Kernel API:
  - `ModulePlanResolver` is the kernel-owned runtime entrypoint for ModulePlan resolution.
  - Adapters MAY call `ModulePlanResolver` later, but missing/conflict classification remains kernel-owned.

- CLI:
  - `coretsia debug:modules` is a future `platform/cli` adapter integration.
  - This epic MUST NOT create or modify `platform/cli` command files unless the epic scope is explicitly expanded.
  - CLI maps deterministic kernel exceptions later via platform-owned error boundary.

- Config compile:
  - `coretsia config:compile` MAY use ModulePlan as input in a later config/artifacts epic.
  - This epic MUST NOT write config compile artifacts.

- Artifacts:
  - This epic introduces no artifacts directly.
  - `skeleton/var/cache/<appTarget>/module-manifest.php` is written later by the artifacts/config compile owner.
  - `ModulePlan::toArray()` is artifact-ready but not written by this epic.

- Policy surface:
  - missing/conflict classification MUST be performed only here, in `core/kernel`.
  - adapters surface deterministic kernel results later.

### Deliverables (MUST)

#### Creates

Discovery + preset loading + graph:
- [x] `framework/packages/core/kernel/src/Module/ComposerManifestReader.php` — ManifestReaderInterface via composer metadata
  - [x] Discovery source is Composer installed metadata only.
  - [x] Runtime MUST NOT scan `framework/packages/**`, package directories, source trees, or skeleton directories.
  - [x] Runtime MUST NOT instantiate module classes during discovery.
  - [x] Runtime MUST NOT require package filesystem paths to derive module identity.
  - [x] A package is a Coretsia runtime module only when `extra.coretsia.moduleId` is present and valid.
  - [x] `extra.coretsia.kind` MUST be `runtime` for runtime ModulePlan inclusion.
  - [x] `extra.coretsia.moduleId` MUST be parsed through `Coretsia\Contracts\Module\ModuleId`.
  - [x] `extra.coretsia.moduleClass`, when present, MUST be a non-empty safe single-line string.
  - [x] `extra.coretsia.providers`, when present, MUST be a list of non-empty safe single-line strings.
  - [x] `extra.coretsia.defaultsConfigPath`, when present, MAY be read as metadata but MUST NOT be exported in diagnostics.
  - [x] Discovery MUST collapse duplicate composer package records deterministically by composer package name.
  - [x] Duplicate `moduleId` across installed packages MUST hard fail deterministically.
  - [x] Invalid Coretsia module metadata MUST hard fail with `CORETSIA_MODULE_MANIFEST_INVALID`.
  - [x] Returned `ModuleManifest` MUST be sorted by moduleId using byte-order `strcmp`.
  - [x] Normalized `extra.coretsia.requires` MUST be stored in `ModuleDescriptor.metadata()['requires']`.
  - [x] Normalized `extra.coretsia.conflicts` MUST be stored in `ModuleDescriptor.metadata()['conflicts']`.
  - [x] `requires` and `conflicts` metadata values MUST be exported as deterministic lists of module id strings.
  - [x] Missing `requires` / `conflicts` MUST be exported as empty lists.
  - [x] Production source MAY use `Composer\InstalledVersions` or Composer installed metadata files through a runtime-safe adapter.
  - [x] Tests MUST be able to inject deterministic installed metadata without depending on the real project `vendor/` state.
  - [x] Injected test metadata MUST use the same normalization/validation path as production metadata.
  - [x] Test fixtures MUST NOT require filesystem package scans.

Composer installed metadata source:
- [x] `framework/packages/core/kernel/src/Module/ComposerInstalledMetadataProvider.php`
  - [x] Provides normalized raw Composer package metadata to `ComposerManifestReader`.
  - [x] Production implementation reads Composer installed metadata only.
  - [x] Test fixtures can provide deterministic metadata arrays.
  - [x] MUST NOT scan package directories.
  - [x] Provider normalizes Composer package records, but does not classify Coretsia modules.
  - [x] Provider MUST NOT validate `extra.coretsia`; that belongs to `ComposerManifestReader`.
  - [x] Missing `extra` is normalized as `[]`.
  - [x] Returned package records are sorted by Composer package name using byte-order `strcmp`.

Module dependency metadata policy:
- [x] Canonical module dependency metadata source is `composer.json` `extra.coretsia.requires` and `extra.coretsia.conflicts`.
- [x] Composer package-level `require` / `conflict` MUST NOT be treated as module graph edges unless explicitly represented in `extra.coretsia.requires` / `extra.coretsia.conflicts`.
- [x] `extra.coretsia.requires` MUST be absent or a list of valid module ids.
- [x] `extra.coretsia.conflicts` MUST be absent or a list of valid module ids.
- [x] Missing `extra.coretsia.requires` is equivalent to `[]`.
- [x] Missing `extra.coretsia.conflicts` is equivalent to `[]`.
- [x] `requires` and `conflicts` MUST be deduplicated and sorted by byte-order `strcmp`.
- [x] A module MUST NOT require itself.
- [x] A module MUST NOT conflict with itself.
- [x] A module MUST NOT list the same target in both `requires` and `conflicts`.
- [x] Invalid dependency metadata MUST hard fail with `CORETSIA_MODULE_MANIFEST_INVALID`.
- [x] `core.kernel` MUST declare `extra.coretsia.requires = ["core.foundation"]`.

Preset value objects:
- [x] `framework/packages/core/kernel/src/Module/ModePreset.php` — immutable Kernel-owned implementation of `ModePresetInterface`
  - [x] Stores `schemaVersion`, `name`, `description`, `required`, `optional`, `disabled`, `featureBundles`, `metadata`.
  - [x] Exports `moduleIds()` as `required + optional - disabled`, sorted by moduleId using byte-order `strcmp`.
  - [x] Exports `toArray()` as stable scalar/json-like shape.
  - [x] MUST NOT expose source file path, skeleton root, defaults path, overrides path, PHP objects, services, or filesystem handles.
  - [x] `required`, `optional`, and `disabled` are canonical string sets exported as lists sorted by byte-order `strcmp`.
  - [x] Source list order is not semantic.

Preset loader factory:
- [x] `framework/packages/core/kernel/src/Module/ModePresetLoaderFactory.php` — creates per-resolution `FilesystemModePresetLoader`
  - [x] Accepts Kernel modes config.
  - [x] Accepts package root as a constructor/input safe path boundary.
  - [x] Accepts `kernel.modes.defaults_path` as package-relative path.
  - [x] Accepts `kernel.modes.overrides_path` as skeleton-root-relative path.
  - [x] MUST reject absolute configured defaults/overrides paths before constructing loader.
  - [x] Creates loader for a given `BootstrapConfig`.
  - [x] Resolves skeleton override path from `BootstrapConfig.skeletonRoot()` + `kernel.modes.overrides_path`.
  - [x] Resolves framework defaults path from package root + `kernel.modes.defaults_path`.
  - [x] MUST NOT cache loaders.
  - [x] MUST NOT cache loaded presets.
  - [x] MUST NOT retain `BootstrapConfig` beyond factory method call.

ModulePlan internal value objects:
- [x] `framework/packages/core/kernel/src/Module/ModulePlanEntry.php` — immutable resolved module entry
  - [x] Fields: `moduleId`, `composerName`, `requires`, `conflicts`.
  - [x] `requires` and `conflicts` are sorted by byte-order `strcmp`.
  - [x] `toArray()` returns stable scalar/json-like shape.
  - [x] MUST NOT expose paths, provider class list, defaultsConfigPath, package filesystem metadata, or runtime services.

- [x] `framework/packages/core/kernel/src/Module/FilesystemModePresetLoader.php` — load: skeleton override → framework default
  - [x] Input preset name MUST be a non-empty lowercase ASCII safe preset name.
  - [x] Lookup order is single-choice:
    - [x] Framework default path is resolved as package root + `kernel.modes.defaults_path` + `<preset>.php`.
    - [x] Skeleton override path is resolved as `BootstrapConfig.skeletonRoot()` + `kernel.modes.overrides_path` + `<preset>.php`.
  - [x] First existing preset file wins.
  - [x] Loader MUST NOT merge skeleton preset with framework default preset.
  - [x] Missing skeleton override is not an error.
  - [x] Missing framework default after missing skeleton override MUST hard fail with `CORETSIA_MODE_PRESET_NOT_FOUND`.
  - [x] A present but unreadable or invalid file MUST hard fail with `CORETSIA_MODE_PRESET_INVALID`.
  - [x] `listNames()` MUST return available preset names in deterministic byte-order `strcmp` order.
  - [x] Skeleton preset names override framework preset names with the same name in `listNames()`.
  - [x] `has()` MUST return false for invalid names and missing presets.
  - [x] `tryLoad()` MUST return null for invalid names and missing presets.
  - [x] `load()` MUST throw deterministic exceptions for invalid or missing presets.
  - [x] Loader diagnostics MUST NOT include filesystem paths.
  - [x] Invalid preset names passed to `load()` MUST fail deterministically without echoing the raw invalid value.
  - [x] Invalid preset names MUST use stable placeholder context `preset = invalid`.
  - [x] Invalid preset names SHOULD use reason token `mode-preset-name-invalid`.

- [x] `framework/packages/core/kernel/src/Module/ModePresetSchemaValidator.php` — validates canonical preset PHP array shape
  - [x] Preset file MUST return an array.
  - [x] Preset file MUST NOT return a root wrapper such as `['mode' => ...]`, `['modes' => ...]`, or `['kernel' => ...]`.
  - [x] `schemaVersion` MUST be integer `1`.
  - [x] `name` MUST be a non-empty lowercase ASCII preset name.
  - [x] `name` MUST match the requested preset filename stem.
  - [x] `description` MUST be `null` or non-empty safe string.
  - [x] `required`, `optional`, and `disabled` MUST be lists.
  - [x] Every item in `required`, `optional`, and `disabled` MUST be a valid module id.
  - [x] Duplicate ids inside each list MUST be collapsed deterministically.
  - [x] `required`, `optional`, and `disabled` MUST be pairwise disjoint after normalization.
  - [x] `featureBundles` MUST be a JSON-like map.
  - [x] `metadata` MUST be a JSON-like map.
  - [x] Floats, objects, closures, resources, service instances, filesystem handles, and runtime wiring objects are forbidden.
  - [x] Validation diagnostics MUST include only `presetName`, stable reason token, and stable error code.
  - [x] Validation diagnostics MUST NOT include preset file path, skeleton root, defaults path, overrides path, raw config payload, or filesystem layout.
  - [x] Invalid preset MUST hard fail with `CORETSIA_MODE_PRESET_INVALID`.
  - [x] `metadata` and `featureBundles` MUST NOT contain absolute path-like string values.
  - [x] Absolute path-like strings MUST be rejected with `CORETSIA_MODE_PRESET_INVALID`.
  - [x] Rejection diagnostics MUST include only `presetName`, stable reason token, and stable error code.
  - [x] Rejection diagnostics MUST NOT echo the offending path-like value.

- [x] `framework/packages/core/kernel/src/Module/ModuleGraphResolver.php` — preset required/optional/disabled + module requires/conflicts policy
  - [x] Inputs:
    - [x] installed `ModuleManifest`
    - [x] validated `ModePresetInterface`
    - [x] module dependency metadata from `extra.coretsia.requires`
    - [x] module conflict metadata from `extra.coretsia.conflicts`
  - [x] Initial enabled seed set:
    - [x] all preset `required`
    - [x] preset `optional` only when installed
    - [x] never preset `disabled`
  - [x] Preset optional module not installed:
    - [x] MUST be added to `optionalMissing`
    - [x] MUST create `ModuleOptionalMissingWarning`
    - [x] MUST NOT fail resolution
  - [x] Preset required module not installed:
    - [x] MUST hard fail with `CORETSIA_MODULE_REQUIRED_MISSING`
  - [x] Required dependency closure:
    - [x] if enabled module `A` requires installed module `B`, `B` MUST be enabled transitively
    - [x] if enabled module `A` requires missing module `B`, resolution MUST hard fail with `CORETSIA_MODULE_REQUIRED_MISSING`
    - [x] if enabled module `A` requires disabled module `B`, resolution MUST hard fail with `CORETSIA_MODULE_CONFLICT` using reason `required-module-disabled`
  - [x] Conflict policy:
    - [x] if enabled module `A` conflicts with enabled module `B`, resolution MUST hard fail with `CORETSIA_MODULE_CONFLICT`
    - [x] conflicts against modules that are not enabled MUST NOT fail
    - [x] conflict pair in diagnostics MUST be sorted as `[lowerModuleId, higherModuleId]` using byte-order `strcmp`
  - [x] Disabled policy:
    - [x] disabled modules MUST NOT appear in `enabled`
    - [x] disabled modules MAY appear in `disabled`
    - [x] disabled modules MUST be sorted by byte-order `strcmp`
  - [x] Output:
    - [x] enabled module ids sorted by byte-order `strcmp`
    - [x] disabled module ids sorted by byte-order `strcmp`
    - [x] optionalMissing sorted by byte-order `strcmp`
    - [x] warning list sorted by canonical warning key using byte-order `strcmp`
  - [x] Reads module dependency edges from `ModuleDescriptor.metadata()['requires']`.
  - [x] Reads module conflict edges from `ModuleDescriptor.metadata()['conflicts']`.
  - [x] Missing metadata keys are treated as empty lists.
  - [x] Non-list or invalid metadata values MUST hard fail with `CORETSIA_MODULE_MANIFEST_INVALID`.
  - [x] `ModuleGraphResolver::resolve()` accepts app target as output metadata only; app target MUST NOT affect module selection.
  - [x] `ModuleGraphResolver` MUST collect graph failure candidates before throwing, so graph failures are selected deterministically before being surfaced to `ModulePlanResolver`.
  - [x] Graph-level failure precedence is:
    - [x] conflicts
    - [x] required missing
    - [x] cycle detection
  - [x] `ModulePlanResolver` applies global pipeline precedence around preset loading, manifest reading, graph resolution, and success/warnings.

- [x] `framework/packages/core/kernel/src/Module/TopologicalSorter.php` — deterministic topo sort + cycle detection
  - [x] Edge direction: `A requires B` means `B` MUST appear before `A` in `topologicalOrder`.
  - [x] Only enabled modules participate in topo sorting.
  - [x] Missing required modules are classified before topo sort.
  - [x] Conflict failures are classified before topo sort.
  - [x] Topo order MUST be stable across runs, OS, filesystem order, and locale.
  - [x] When multiple nodes are available, sorter MUST pick the lowest moduleId by byte-order `strcmp`.
  - [x] Cycle detection MUST hard fail with `CORETSIA_MODULE_CYCLE_DETECTED`.
  - [x] Cycle diagnostics MUST include only stable module ids and reason token.
  - [x] Cycle diagnostics MUST NOT include graph dumps, paths, filesystem layout, or raw metadata payloads.
  - [x] Cycle module ids in diagnostics MUST be sorted deterministically unless preserving the minimal canonical cycle path is explicitly required by the test.

- [x] `framework/packages/core/kernel/src/Module/ModulePlan.php` — stable payload shape for artifacts/debug (+ warnings storage)
  - [x] Shape examples below are illustrative test vectors, not hardcoded values.
  - [x] Actual `preset` MUST come from `BootstrapConfig.preset()`.
  - [x] Actual `app` MUST come from `BootstrapConfig.appTarget()`.
  - [x] Actual `enabled`, `disabled`, `optionalMissing`, `topologicalOrder`, and `modules` MUST come from graph resolution.
  - [x] `'schemaVersion' => 1`
  - [x] `'preset' => 'micro'`
  - [x] `'app' => 'api'`
  - [x] `'enabled' => ['core.foundation', 'core.kernel']`
  - [x] `'disabled' => []`
  - [x] `'optionalMissing' => []`
  - [x] `'warnings' => []`
  - [x] `'topologicalOrder' => ['core.foundation', 'core.kernel']`
  - [x] `modules`
    - [x] `'core.foundation'`
      - [x] `'moduleId' => 'core.foundation'`
      - [x] `'composerName' => 'coretsia/core-foundation'`
      - [x] `'requires' => []`
      - [x] `'conflicts' => []`
    - [x] `'core.kernel'`
      - [x] `'moduleId' => 'core.kernel'`
      - [x] `'composerName' => 'coretsia/core-kernel'`
      - [x] `'requires' => ['core.foundation']`
      - [x] `'conflicts' => []`
  - [x] ModulePlan MUST NOT export `skeletonRoot/appRoot/defaultsPath/overridesPath/absolute` paths.
  - [x] `ModulePlan::toArray()` MUST return only scalar/json-like values.
  - [x] Top-level exported key order MUST be deterministic.
  - [x] Canonical top-level `toArray()` key order:
    - [x] `app`
    - [x] `disabled`
    - [x] `enabled`
    - [x] `modules`
    - [x] `optionalMissing`
    - [x] `preset`
    - [x] `schemaVersion`
    - [x] `topologicalOrder`
    - [x] `warnings`
  - [x] `modules` MUST be a map keyed by moduleId.
  - [x] `modules` map keys MUST be sorted by byte-order `strcmp`.
  - [x] Each module entry key order:
    - [x] `composerName`
    - [x] `conflicts`
    - [x] `moduleId`
    - [x] `requires`
  - [x] `enabled`, `disabled`, `optionalMissing`, and `topologicalOrder` MUST be lists of module ids.
  - [x] `enabled`, `disabled`, and `optionalMissing` MUST be sorted by byte-order `strcmp`.
  - [x] `topologicalOrder` MUST preserve dependency order and be deterministic.
  - [x] `warnings` MUST be a list of warning exported arrays.
  - [x] Warning exported key order:
    - [x] `code`
    - [x] `moduleId`
    - [x] `preset`
    - [x] `reason`
  - [x] Warning canonical sort key is `code + "\0" + preset + "\0" + moduleId + "\0" + reason`.
  - [x] `ModulePlan` MUST NOT contain or export `skeletonRoot`, `appRoot`, `defaultsPath`, `overridesPath`, absolute paths, provider class lists, raw composer payloads, raw config payloads, or service instances.

- [x] `framework/packages/core/kernel/src/Module/ModulePlanResolver.php` — Plan resolution entrypoint (single brain):
  - [x] Single-choice module selection inputs:
    - [x] `BootstrapConfig.preset` selected in Phase A
    - [x] mode files (`skeleton/config/modes/*.php` override → framework defaults)
    - [x] First existing preset file wins:
      - [x] 1. skeleton override path: `BootstrapConfig.skeletonRoot()` + `kernel.modes.overrides_path` + `<preset>.php`
      - [x] 2. framework default path: package root + `kernel.modes.defaults_path` + `<preset>.php`
      - [x] No merge.
    - [x] Resolver/loader MUST NOT export either resolved path in diagnostics or ModulePlan.
    - [x] composer metadata discovery
    - [x] selected app target from Phase A (`BootstrapConfig.app`) MAY affect app-root resolution for app-local config/bootstrap, but MUST NOT introduce a parallel module-selection source
    - [x] forbidden parallel module-selection paths:
      - [x] `skeleton/config/modules.php`
      - [x] `skeleton/apps/*/config/modules.php`
  - [x] orchestrates:
    - [x] preset load (skeleton override → framework default)
    - [x] app-root is already resolved by `BootstrapConfig`; `ModulePlanResolver` MUST NOT use `appRoot()` as a module-selection source.
    - [x] schema validation
    - [x] composer metadata discovery
    - [x] graph resolve + topo sort
    - [x] returns a deterministic `ModulePlan` (same inputs → same plan)
  - [x] MUST NOT read `skeleton/config/modules.php`.
  - [x] MUST NOT read `skeleton/apps/*/config/modules.php`.
  - [x] MUST NOT scan `skeleton/apps/*`.
  - [x] MUST NOT infer app target from filesystem.
  - [x] MUST use only `BootstrapConfig.preset()` as selected preset input.
  - [x] MUST use only `BootstrapConfig.appTarget()` / `BootstrapConfig.appRoot()` for app-root derivation, not for module selection.
  - [x] MUST not write artifacts; artifact writing belongs to later artifacts/config compile epic.
  - [x] MUST not emit stdout/stderr.
  - [x] MUST not log raw paths or raw metadata.
  - [x] MUST return a deterministic `ModulePlan` or throw deterministic module resolution exception.
  - [x] Uses `ModePresetLoaderFactory` to create a per-resolution loader from the current `BootstrapConfig`.
  - [x] Emits metrics through `MeterPortInterface`.
  - [x] Measures duration through Foundation `Stopwatch` or another deterministic monotonic duration boundary.
  - [x] Emits `kernel.modules_resolve_total` exactly once per resolution attempt.
  - [x] Emits `kernel.modules_resolve_duration_ms` exactly once per resolution attempt.
  - [x] Metrics emission MUST happen for both success and deterministic failure outcomes.
  - [x] Metrics labels MUST use only allowed `outcome` values.
  - [x] Metrics labels MUST NOT contain module ids, preset names, paths, raw errors, or exception messages.
  - [x] Metrics backend failures MUST NOT affect ModulePlan resolution.
  - [x] Metrics backend failures MUST NOT replace deterministic module resolution exceptions.
  - [x] Metrics emission is best-effort: resolver attempts `kernel.modules_resolve_total` and `kernel.modules_resolve_duration_ms` once per resolution attempt.
  - [x] MUST validate `kernel.modules.discovery.source` before discovery.
  - [x] Unsupported source MUST fail before preset loading and composer metadata reading.
  - [x] MUST validate `kernel.modules.discovery.source` against `kernel.modules.discovery.allowed_sources`.
  - [x] Unsupported source MUST fail with `CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED`.
  - [x] Unsupported source validation MUST happen before preset loading and before composer metadata reading.
  - [x] Emits safe logs through `Psr\Log\LoggerInterface`.
  - [x] Logging MUST be optional and MUST NOT affect ModulePlan resolution.
  - [x] Optional missing warnings MAY be logged with stable reason tokens.
  - [x] Conflict failures MAY be logged only as safe deterministic summaries.
  - [x] Log context MUST contain only stable error/warning code, reason token, presetName, and moduleIds.
  - [x] Log context MUST NOT contain paths, raw composer metadata, raw preset payloads, exception messages, stack traces, secrets, or PII.

Failure precedence (single-choice):
- [x] `ModulePlanResolver` MUST classify failures in this order:
  - [x] 1. unsupported discovery source → `CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED`
  - [x] 2. preset not found → `CORETSIA_MODE_PRESET_NOT_FOUND`
  - [x] 3. preset invalid → `CORETSIA_MODE_PRESET_INVALID`
  - [x] 4. composer/module manifest invalid → `CORETSIA_MODULE_MANIFEST_INVALID`
  - [x] 5. module conflicts → `CORETSIA_MODULE_CONFLICT`
  - [x] 6. required missing → `CORETSIA_MODULE_REQUIRED_MISSING`
  - [x] 7. cycle detected → `CORETSIA_MODULE_CYCLE_DETECTED`
  - [x] 8. success with optional missing warnings
- [x] If multiple failures of the same class exist, report the one with the smallest canonical failure key by byte-order `strcmp`.
- [x] Conflict failure key: `lowerModuleId + "\0" + higherModuleId + "\0" + reason`.
- [x] Required-missing failure key: `requiredBy + "\0" + missingModuleId + "\0" + reason`.
- [x] Preset-invalid failure key: `presetName + "\0" + reason`.

Framework default presets:
- [x] `framework/packages/core/kernel/resources/modes/micro.php` must return:
  - [x] `'schemaVersion' => 1`
  - [x] `'name' => 'micro'`
  - [x] `'description' => 'Micro web application mode.'`
  - [x] `required`
    - [x] `core.foundation`
    - [x] `core.kernel`
    - [x] `platform.cli`
  - [x] `optional`
    - [x] `platform.logging`
    - [x] `platform.metrics`
    - [x] `platform.tracing`
  - [x] `'disabled' => []`
  - [x] `featureBundles`
    - [x] `'observability' => 'minimal'`
  - [x] `'metadata' => []`

- [x] `framework/packages/core/kernel/resources/modes/express.php` must return:
  - [x] `'schemaVersion' => 1`
  - [x] `'name' => 'express'`
  - [x] `'description' => 'Express web application mode.'`
  - [x] `required`
    - [x] `core.foundation`
    - [x] `core.kernel`
    - [x] `platform.cli`
  - [x] `optional`
    - [x] `platform.http`
    - [x] `platform.logging`
    - [x] `platform.metrics`
    - [x] `platform.tracing`
  - [x] `'disabled' => []`
  - [x] `featureBundles`
    - [x] `'observability' => 'minimal'`
  - [x] `'metadata' => []`

- [x] `framework/packages/core/kernel/resources/modes/hybrid.php` must return:
  - [x] `'schemaVersion' => 1`
  - [x] `'name' => 'hybrid'`
  - [x] `'description' => 'Hybrid web application mode.'`
  - [x] `required`
    - [x] `core.foundation`
    - [x] `core.kernel`
    - [x] `platform.cli`
  - [x] `optional`
    - [x] `platform.http`
    - [x] `platform.logging`
    - [x] `platform.metrics`
    - [x] `platform.tracing`
  - [x] `'disabled' => []`
  - [x] `featureBundles`
    - [x] `'observability' => 'minimal'`
  - [x] `'metadata' => []`

- [x] `framework/packages/core/kernel/resources/modes/enterprise.php` must return:
  - [x] `'schemaVersion' => 1`
  - [x] `'name' => 'enterprise'`
  - [x] `'description' => 'Enterprise web application mode.'`
  - [x] `required`
    - [x] `core.foundation`
    - [x] `core.kernel`
    - [x] `platform.cli`
  - [x] `optional`
    - [x] `platform.http`
    - [x] `platform.logging`
    - [x] `platform.metrics`
    - [x] `platform.tracing`
  - [x] `'disabled' => []`
  - [x] `featureBundles`
    - [x] `'observability' => 'minimal'`
  - [x] `'metadata' => []`

Deterministic exceptions (resolution + policy):
- [x] `framework/packages/core/kernel/src/Module/Exception/ModePresetNotFoundException.php` — `CORETSIA_MODE_PRESET_NOT_FOUND`
  - [x] Extends `ModuleResolutionException`.
  - [x] Error code MUST be read from `ModuleErrorCodes`.
  - [x] Message format MUST be `ERROR_CODE: reason-token`.
  - [x] `errorCode()` MUST return stable public code.
  - [x] `reason()` MUST return stable reason token.
  - [x] `context()` MUST return safe deterministic json-like context.
  - [x] Context MUST NOT contain paths, raw composer metadata, raw preset payload, secrets, PII, stack traces, or previous throwable message.

- [x] `framework/packages/core/kernel/src/Module/Exception/ModePresetInvalidException.php` — `CORETSIA_MODE_PRESET_INVALID`
  - [x] Extends `ModuleResolutionException`.
  - [x] Error code MUST be read from `ModuleErrorCodes`.
  - [x] Message format MUST be `ERROR_CODE: reason-token`.
  - [x] `errorCode()` MUST return stable public code.
  - [x] `reason()` MUST return stable reason token.
  - [x] `context()` MUST return safe deterministic json-like context.
  - [x] Context MUST NOT contain paths, raw composer metadata, raw preset payload, secrets, PII, stack traces, or previous throwable message.

- [x] `framework/packages/core/kernel/src/Module/Exception/ModuleCycleDetectedException.php` — `CORETSIA_MODULE_CYCLE_DETECTED`
  - [x] Extends `ModuleResolutionException`.
  - [x] Error code MUST be read from `ModuleErrorCodes`.
  - [x] Message format MUST be `ERROR_CODE: reason-token`.
  - [x] `errorCode()` MUST return stable public code.
  - [x] `reason()` MUST return stable reason token.
  - [x] `context()` MUST return safe deterministic json-like context.
  - [x] Context MUST NOT contain paths, raw composer metadata, raw preset payload, secrets, PII, stack traces, or previous throwable message.

- [x] `framework/packages/core/kernel/src/Module/Exception/ModuleConflictException.php` — `CORETSIA_MODULE_CONFLICT`
  - [x] Extends `ModuleResolutionException`.
  - [x] Error code MUST be read from `ModuleErrorCodes`.
  - [x] Message format MUST be `ERROR_CODE: reason-token`.
  - [x] `errorCode()` MUST return stable public code.
  - [x] `reason()` MUST return stable reason token.
  - [x] `context()` MUST return safe deterministic json-like context.
  - [x] Context MUST NOT contain paths, raw composer metadata, raw preset payload, secrets, PII, stack traces, or previous throwable message.

- [x] `framework/packages/core/kernel/src/Module/Exception/ModuleRequiredMissingException.php` — `CORETSIA_MODULE_REQUIRED_MISSING`
  - [x] Extends `ModuleResolutionException`.
  - [x] Error code MUST be read from `ModuleErrorCodes`.
  - [x] Message format MUST be `ERROR_CODE: reason-token`.
  - [x] `errorCode()` MUST return stable public code.
  - [x] `reason()` MUST return stable reason token.
  - [x] `context()` MUST return safe deterministic json-like context.
  - [x] Context MUST NOT contain paths, raw composer metadata, raw preset payload, secrets, PII, stack traces, or previous throwable message.

- [x] `framework/packages/core/kernel/src/Module/Exception/ModuleManifestInvalidException.php` — `CORETSIA_MODULE_MANIFEST_INVALID`
  - [x] Extends `ModuleResolutionException`.
  - [x] Error code MUST be read from `ModuleErrorCodes`.
  - [x] Used for invalid composer installed metadata / invalid `extra.coretsia` metadata / duplicate module ids.
  - [x] Message format MUST be `ERROR_CODE: reason-token`.
  - [x] `errorCode()` MUST return stable public code.
  - [x] `reason()` MUST return stable reason token.
  - [x] `context()` MUST return safe deterministic json-like context.
  - [x] Context MUST NOT contain paths, raw composer metadata, raw preset payload, secrets, PII, stack traces, or previous throwable message.

- [x] `framework/packages/core/kernel/src/Module/Exception/ModuleDiscoverySourceUnsupportedException.php` — `CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED`
  - [x] Extends `ModuleResolutionException`.
  - [x] Error code MUST be read from `ModuleErrorCodes`.
  - [x] Used when config selects unsupported `kernel.modules.discovery.source`.
  - [x] Message format MUST be `ERROR_CODE: reason-token`.
  - [x] `errorCode()` MUST return stable public code.
  - [x] `reason()` MUST return stable reason token.
  - [x] `context()` MUST return safe deterministic json-like context.
  - [x] Context MUST NOT contain paths, raw composer metadata, raw preset payload, secrets, PII, stack traces, or previous throwable message.

Exception/error-code support:
- [x] `framework/packages/core/kernel/src/Module/Exception/ModuleResolutionException.php`
  - [x] Abstract Kernel-owned base exception class, not an interface/port.
  - [x] Extends `\RuntimeException`.
  - [x] Exposes `errorCode(): string`.
  - [x] Exposes `reason(): string`.
  - [x] Exposes `context(): array`.
  - [x] `context()` MUST contain only safe scalar/json-like diagnostic values.
  - [x] Message format MUST be `ERROR_CODE: reason-token`.
  - [x] MUST NOT be placed in `core/contracts`.
  - [x] MUST NOT be treated as a framework port.
  - [x] MAY be caught by Kernel-owned callers and future adapters as a concrete Kernel package exception base.
  - [x] MUST NOT introduce a cross-package interface in `core/kernel`.
  - [x] Context string values MUST be safe diagnostic tokens only.
  - [x] Context string values MUST NOT be free-form messages.
  - [x] Context string values MUST NOT contain path separators, whitespace, control characters, stream-wrapper-like values, or path traversal fragments.
  - [x] Context validation MUST NOT rely on sensitive-word or SQL-like regex filtering; concrete exceptions MUST pass only pre-classified safe tokens/module ids/preset names.

- [x] `framework/packages/core/kernel/src/Module/Exception/ModuleErrorCodes.php`
  - [x] Kernel-owned constants holder, not a contracts port.
  - [x] Final non-instantiable class.
  - [x] Used only by Kernel module resolution exceptions/warnings.
  - [x] MUST NOT be placed in `core/contracts`.
  - [x] MUST NOT be treated as a framework port.
  - [x] Defines:
    - [x] `CORETSIA_MODE_PRESET_NOT_FOUND`
    - [x] `CORETSIA_MODE_PRESET_INVALID`
    - [x] `CORETSIA_MODULE_MANIFEST_INVALID`
    - [x] `CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED`
    - [x] `CORETSIA_MODULE_CYCLE_DETECTED`
    - [x] `CORETSIA_MODULE_CONFLICT`
    - [x] `CORETSIA_MODULE_REQUIRED_MISSING`
    - [x] `CORETSIA_MODULE_OPTIONAL_MISSING`

Warnings (non-fatal):
- [x] `framework/packages/core/kernel/src/Module/Warning/ModuleOptionalMissingWarning.php` — `CORETSIA_MODULE_OPTIONAL_MISSING`
  - [x] Warning code MUST be read from `ModuleErrorCodes`.
  - [x] Fields: `code`, `moduleId`, `preset`, `reason`.
  - [x] `code` MUST be `CORETSIA_MODULE_OPTIONAL_MISSING`.
  - [x] `reason` MUST be a stable reason token.
  - [x] `toArray()` MUST export keys in order:
    - [x] `code`
    - [x] `moduleId`
    - [x] `preset`
    - [x] `reason`
  - [x] MUST NOT contain paths, raw preset payload, raw composer payload, secrets, PII, or filesystem layout.

Docs:
- [x] `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
- [x] `docs/adr/ADR-0025-kernel-conflicts-optional-missing-policy.md`

Tests:
- [x] `framework/packages/core/kernel/tests/Integration/ComposerManifestReaderReadsOnlyComposerMetadataTest.php`
- [x] `framework/packages/core/kernel/tests/Integration/ComposerManifestReaderDoesNotLeakPathsTest.php`
- [x] `framework/packages/core/kernel/tests/Integration/ComposerManifestReaderSortsModulesDeterministicallyTest.php`
- [x] `framework/packages/core/kernel/tests/Integration/ModePresetLoaderUsesSkeletonOverrideBeforeFrameworkDefaultTest.php`
- [x] `framework/packages/core/kernel/tests/Integration/ModePresetLoaderDoesNotMergeOverrideWithDefaultTest.php`
- [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverUsesBootstrapPresetAsOnlySelectionSourceTest.php`
- [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverIgnoresSkeletonConfigModulesPhpTest.php`
- [x] `framework/packages/core/kernel/tests/Integration/KernelRequiresFoundationInModulePlanTest.php`
- [x] `framework/packages/core/kernel/tests/Contract/ModulePlanDoesNotExportFilesystemPathsContractTest.php`
- [x] `framework/packages/core/kernel/tests/Contract/ModuleResolutionExceptionsExposeSafeDiagnosticsContractTest.php`
- [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverRejectsUnsupportedDiscoverySourceTest.php`
  - [x] config value `kernel.modules.discovery.source = "filesystem"` fails with `CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED`
  - [x] diagnostics do not expose paths or config payload
- [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverEmitsPolicyCompliantMetricsTest.php`
- [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverDoesNotEmitPathLabelsTest.php`
- [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverLogsSafeOptionalMissingWarningsTest.php`
- [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverLogsDoNotLeakPathsTest.php`

#### Modifies

- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
  - [x] `docs/adr/ADR-0025-kernel-conflicts-optional-missing-policy.md`

- [x] `docs/ssot/modules-and-manifests.md` — document module dependency metadata:
  - [x] `extra.coretsia.requires` is the canonical module dependency metadata source.
  - [x] `extra.coretsia.conflicts` is the canonical module conflict metadata source.
  - [x] Composer package-level `require` / `conflict` are not module graph edges unless explicitly represented in `extra.coretsia.requires` / `extra.coretsia.conflicts`.
  - [x] `requires` / `conflicts` are stored in `ModuleDescriptor.metadata()` as deterministic lists of module id strings.
  - [x] `requires` / `conflicts` MUST NOT expose filesystem paths or composer raw payloads.

- [x] `framework/packages/core/kernel/config/kernel.php` — adds modules/modes config defaults
- [x] `framework/packages/core/kernel/config/rules.php` — enforces shape

- [x] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php` — registers:
  - [x] `ModulePlanResolver`
  - [x] `ComposerManifestReader`
  - [x] `Coretsia\Contracts\Module\ManifestReaderInterface` → `ComposerManifestReader`
  - [x] `ModePresetLoaderFactory`
  - [x] `ModePresetSchemaValidator`
  - [x] `ModuleGraphResolver`
  - [x] `TopologicalSorter`
  - [x] Provider MUST NOT bind `ModePresetLoaderInterface` globally because skeleton override path is BootstrapConfig-specific.
  - [x] Provider MUST NOT register `FilesystemModePresetLoader` as a global service.
  - [x] `FilesystemModePresetLoader` MUST be created only through `ModePresetLoaderFactory` per `ModulePlanResolver` call.
  - [x] Provider registration MUST NOT resolve ModulePlan.
  - [x] Provider registration MUST NOT read composer metadata.
  - [x] Provider registration MUST NOT read preset files.
  - [x] Provider registration MUST NOT scan filesystem.
  - [x] `ModulePlanResolver` factory MUST inject `MeterPortInterface`.
  - [x] `ModulePlanResolver` factory MUST inject `Stopwatch` or duration provider.
  - [x] `ModulePlanResolver` factory MUST inject `Psr\Log\LoggerInterface` when logs are in scope.
  - [x] Provider module-plan closures MUST NOT capture full `kernel` config arrays.
  - [x] Module-plan factories MAY read the `kernel` root from Foundation `Container::config()` during lazy service creation.
  - [x] Created module-plan services MUST retain only normalized minimal config values, not the full `kernel` config subtree.

- [x] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php` — module plan wiring
  - [x] builds `ComposerManifestReader` from config/runtime-safe composer metadata source
  - [x] builds `FilesystemModePresetLoader` from kernel modes config + BootstrapConfig skeleton root when resolver is invoked
  - [x] builds `ModePresetSchemaValidator`
  - [x] builds `ModuleGraphResolver`
  - [x] builds `TopologicalSorter`
  - [x] builds `ModulePlanResolver`
  - [x] MUST NOT cache loaded presets.
  - [x] MUST NOT cache composer metadata.
  - [x] MUST NOT retain `BootstrapConfig`.
  - [x] MUST NOT retain container or config arrays beyond construction.
  - [x] wires `MeterPortInterface` into `ModulePlanResolver` when observability is enabled by this epic.
  - [x] wires `Stopwatch` or duration provider into `ModulePlanResolver` for duration metrics.
  - [x] wires `LoggerInterface` into `ModulePlanResolver` for safe module resolution logs.

- [x] `framework/packages/core/kernel/composer.json` — extend `extra.coretsia` module metadata:
  - [x] add `"requires": ["core.foundation"]`
  - [x] add `"conflicts": []`
  - [x] keep `"moduleId": "core.kernel"`
  - [x] keep `"kind": "runtime"`
  - [x] keep `"moduleClass": "Coretsia\\Kernel\\Module\\KernelModule"`
  - [x] keep provider metadata deterministic
  - [x] require `"composer-runtime-api": "^2.2"` if `Composer\InstalledVersions` is used directly.

### Configuration (keys + defaults) (MUST)

- Files:
  - [x] `framework/packages/core/kernel/config/kernel.php`
- Keys (dot):
  - [x] `kernel.modules.discovery.source` = "composer"
  - [x] `kernel.modules.discovery.allowed_sources` = ["composer"]
  - [x] `kernel.modes.schema_version` = 1
  - [x] `kernel.modes.defaults_path` = "resources/modes"
    - [x] `kernel.modes.defaults_path` is package-relative.
  - [x] `kernel.modes.overrides_path` = "config/modes"
    - [x] `kernel.modes.overrides_path` is skeleton-root-relative.
  - [x] Config defaults MUST NOT contain absolute paths.
  - [x] Config defaults MUST NOT contain monorepo-only paths such as `framework/packages/core/kernel/...` unless explicitly documented as repo-relative and never exported.
- Rules:
  - [x] `framework/packages/core/kernel/config/rules.php` — enforces shape
    - [x] `kernel.modules.discovery.source` MUST be a non-empty safe string.
    - [x] `kernel.modules.discovery.source` shape validation MUST NOT enforce the concrete source value.
    - [x] Supported discovery source membership is validated by `ModulePlanResolver`, not by config rules.
    - [x] If `kernel.modules.discovery.source` is not in `kernel.modules.discovery.allowed_sources`, `ModulePlanResolver` MUST fail with `CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED`.
    - [x] `kernel.modules.discovery.allowed_sources` MUST be list of non-empty strings.
    - [x] `kernel.modes.schema_version` MUST be integer `1`.
    - [x] `kernel.modes.defaults_path` MUST be non-empty relative safe string.
    - [x] `kernel.modes.overrides_path` MUST be non-empty relative safe string.
    - [x] `kernel.modes.defaults_path` MUST NOT be absolute.
    - [x] `kernel.modes.overrides_path` MUST NOT be absolute.
    - [x] Unknown keys under `modules` and `modes` MUST be rejected.
    - [x] Reserved `@*` keys MUST be rejected.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [x] Metrics:
  - [x] `kernel.modules_resolve_total` (labels: `outcome`)
  - [x] `kernel.modules_resolve_duration_ms` (labels: `outcome`)
- [x] Logs:
  - [x] warnings about optionalMissing/conflicts redacted (only moduleIds)
  - [x] no paths/secrets

- [x] `ModulePlanResolver` emits `kernel.modules_resolve_total` exactly once per resolution attempt.
- [x] `ModulePlanResolver` emits `kernel.modules_resolve_duration_ms` exactly once per resolution attempt.
- [x] Allowed `outcome` labels:
  - [x] `success`
  - [x] `preset_not_found`
  - [x] `preset_invalid`
  - [x] `manifest_invalid`
  - [x] `discovery_source_unsupported`
  - [x] `conflict`
  - [x] `required_missing`
  - [x] `cycle`
- [x] Metrics tests:
  - [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverEmitsPolicyCompliantMetricsTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverDoesNotEmitPathLabelsTest.php`
- [x] Logs tests:
  - [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverLogsSafeOptionalMissingWarningsTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverLogsDoNotLeakPathsTest.php`

#### Errors

- [x] Deterministic exception codes:
  - [x] `CORETSIA_MODE_PRESET_NOT_FOUND`
  - [x] `CORETSIA_MODE_PRESET_INVALID`
  - [x] `CORETSIA_MODULE_MANIFEST_INVALID`
  - [x] `CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED`
  - [x] `CORETSIA_MODULE_CYCLE_DETECTED`
  - [x] `CORETSIA_MODULE_CONFLICT`
  - [x] `CORETSIA_MODULE_REQUIRED_MISSING`
- [x] Deterministic warning code:
  - [x] `CORETSIA_MODULE_OPTIONAL_MISSING`
- [x] Mapping:
  - [x] adapters/CLI map later via `platform/errors`

### Phase 0 parity: deterministic ordering + safe diagnostics (MUST)

ModulePlan MUST бути детермінованим у сенсі PHASE 0 SPIKES:

- [x] будь-які списки/плани/графи/попередження:
  - [x] stable sort by byte-order (`strcmp`)
  - [x] locale-independent

#### Safe diagnostics invariant (single-choice)

В diagnostics (exceptions/warnings) MUST NOT бути:
- [x] абсолютних шляхів
- [x] фрагментів filesystem layout
- [x] секретів/PII

Allowed:
- [x] moduleId, presetName, stable reason tokens, stable error code.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] Deterministic topo/cycle:
  - [x] `framework/packages/core/kernel/tests/Unit/GraphCycleDetectionTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/TopologicalSorterDeterministicOrderTest.php`
- [x] Stable plan shape:
  - [x] `framework/packages/core/kernel/tests/Contract/ModulePlanShapeContractTest.php`
- [x] `framework/packages/core/kernel/tests/Contract/ModulePlanWarningsAreDeterministicallySortedContractTest.php`
  - [x] asserts `optionalMissing` and any warnings collections are sorted deterministically by canonical key using byte-order `strcmp`
  - [x] asserts locale does not affect ordering
- [x] Deterministic behaviors (missing/conflicts policy):
  - [x] `framework/packages/core/kernel/tests/Integration/OptionalMissingDoesNotFailTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/RequiredMissingFailsDeterministicallyTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModuleConflictsFailDeterministicallyTest.php`

### Tests (MUST)

- Unit:
  - [x] `framework/packages/core/kernel/tests/Unit/GraphCycleDetectionTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/TopologicalSorterDeterministicOrderTest.php`
- Contract:
  - [x] `framework/packages/core/kernel/tests/Contract/ModulePlanShapeContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/ModulePlanWarningsAreDeterministicallySortedContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/ModulePlanRecursiveKeyOrderContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/ModulePlanWarningShapeContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/ModuleResolutionExceptionShapeContractTest.php`
    - [x] asserts all module resolution exceptions extend `ModuleResolutionException`
    - [x] asserts no `ModuleResolutionExceptionInterface` exists in `core/kernel`
    - [x] asserts `errorCode()`, `reason()`, and `context()` shape
    - [x] asserts exception message is `ERROR_CODE: reason-token`
  - [x] `framework/packages/core/kernel/tests/Contract/ModePresetExportShapeContractTest.php`
- Integration:
  - [x] `framework/packages/core/kernel/tests/Integration/ModePresetAppliesRequiredOptionalDisabledTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModePresetSchemaValidatorEnforcesMicroAndExpressRulesTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/OptionalMissingDoesNotFailTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/RequiredMissingFailsDeterministicallyTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModuleConflictsFailDeterministicallyTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ComposerManifestReaderRejectsDuplicateModuleIdsTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ComposerManifestReaderRejectsInvalidCoretsiaMetadataTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ComposerManifestReaderReadsRequiresConflictsFromExtraCoretsiaTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModuleGraphResolverAddsTransitiveRequiredDependenciesTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModuleGraphResolverIgnoresConflictsWithDisabledModulesTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModuleGraphResolverFailsWhenEnabledModuleRequiresDisabledModuleTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverFailurePrecedenceTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModePresetSchemaValidatorRejectsOverlappingRequiredOptionalDisabledTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModePresetSchemaValidatorRejectsPathLeakingMetadataTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverRejectsUnsupportedDiscoverySourceTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverEmitsPolicyCompliantMetricsTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverDoesNotEmitPathLabelsTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverLogsSafeOptionalMissingWarningsTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverLogsDoNotLeakPathsTest.php`

### DoD (MUST)

- [x] Module discovery is metadata-only through Composer installed metadata.
- [x] No runtime scan of `framework/packages/**`.
- [x] Kernel does not instantiate module classes for discovery.
- [x] Kernel does not write artifacts in this epic.
- [x] Mode preset load order correct + tested:
  - [x] skeleton override first
  - [x] framework default second
  - [x] first existing file wins
  - [x] no merge
- [x] Deterministic ModulePlan:
  - [x] stable `toArray()` shape
  - [x] stable recursive map key ordering
  - [x] stable list ordering where lists are sets
  - [x] deterministic topological order
  - [x] rerun produces identical plan
- [x] Policy implemented + tested:
  - [x] conflicts → hard fail (`CORETSIA_MODULE_CONFLICT`)
  - [x] required missing → hard fail (`CORETSIA_MODULE_REQUIRED_MISSING`)
  - [x] optional missing → warning (`CORETSIA_MODULE_OPTIONAL_MISSING`) and does not fail
- [x] Failure precedence is implemented exactly as specified.
- [x] Failures are deterministic across runs/OS/locales.
- [x] Diagnostics are safe:
  - [x] no absolute paths
  - [x] no filesystem layout fragments
  - [x] no raw composer payload
  - [x] no raw preset payload
  - [x] no secrets/PII
- [x] Presets `micro`, `express`, `hybrid`, `enterprise` exist as framework defaults in `core/kernel/resources/modes/*.php`.
- [x] Any preset that enables `core.kernel` MUST also enable `core.foundation`.
- [x] `core.kernel` ModulePlan metadata MUST reflect required module dependency:
  - [x] `core.kernel` requires `core.foundation`
- [x] `core/kernel` still MUST NOT own `kernel.reset`; it only triggers `ResetOrchestrator` at runtime.
- [x] CLI `debug:modules` is out of scope for this epic and belongs to `platform/cli`.
- [x] JSON CLI error rendering is out of scope for this epic and belongs to `platform/cli` / `platform/errors`.
- [x] ModulePlanResolver emits policy-compliant metrics exactly once per resolution attempt.
- [x] Metrics labels use only allowlisted `operation` and `outcome` labels; `operation` is stable token `resolve`.
- [x] Metrics do not expose module ids, preset names, paths, raw metadata, secrets, or exception messages.
- [x] ModulePlanResolver logs only policy-compliant safe records when logging is used.
- [x] Logs do not expose absolute paths, filesystem layout, raw composer payloads, raw preset payloads, secrets, PII, exception messages, or stack traces.
- [x] Log context contains only stable error/warning code, reason token, presetName, and moduleIds.

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
  - 1.300.0 — immutable `EnvRepositoryInterface` snapshot and kernel-owned `BootstrapEnvSourcePolicy` source precedence are available for environment overlays.

- Terminology note (MUST): config root vs config key namespaces
  - Config root for Kernel is **`kernel`** (file: `framework/packages/core/kernel/config/kernel.php`).
  - Any dotted prefixes like `kernel.uow.*`, `kernel.runtime.*`, `kernel.modules.*`, `kernel.config.*`, `kernel.artifacts.*`, `kernel.fingerprint.*` are **config key namespaces**, not separate roots.
  - `config/<name>.php` MUST return subtree for `<name>` (no wrapper array repeating the root key).
  - `Coretsia\Contracts\Env\EnvPolicy` is used only for missing-value semantics (`required|optional|defaulted`).
  - `BootstrapEnvSourcePolicy` has already resolved dotenv-vs-system precedence before ConfigKernel consumes `EnvRepositoryInterface`.
  - ConfigKernel MUST NOT re-read `$_ENV`, `$_SERVER`, or `getenv()`.

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
  - writes (via artifacts epic): `skeleton/var/cache/<appTarget>/config.php`
- Docs:
  - SSoT docs MUST match implementation + tests (no “creative” divergence)

### Config rules validation model (MUST)

- `config/rules.php` files are declarative ruleset files.
- `config/rules.php` MUST return a plain PHP array.
- `config/rules.php` MUST NOT return callable/closure/object.
- `config/rules.php` MUST NOT contain executable validation policy beyond returning data.
- Validation logic MUST live in kernel-owned runtime code.
- ConfigKernel MUST load package-owned `config/rules.php` files from enabled runtime packages and validate the merged global config against those rules.
- ConfigKernel MUST validate rulesets before using them:
  - ruleset must be array
  - `configRoot` must be non-empty string
  - `schemaVersion` must be supported if present
  - `keys` must be map
  - unknown rule DSL keys must fail deterministically
- Validation diagnostics MUST be deterministic:
  - stable code/reason tokens
  - config path in dot notation
  - no raw secret values
  - no absolute paths

### User-owned config validation boundary (MUST)

- ConfigKernel validates framework-owned and module-owned roots only when a ruleset exists for that root.
- ConfigKernel MUST NOT invent validation rules for user-owned roots.
- User-owned roots without rules are accepted as deterministic config data.
- User-owned roots without rules MUST be included in:
  - final merged config;
  - explain trace;
  - compiled config artifact payload;
  - config fingerprint inputs.
- User-owned roots without rules MUST be marked as:
  - `user_owned`
  - `unvalidated`
- User-owned roots without rules MUST NOT be silently treated as framework-owned roots.
- User-owned roots MUST still obey global config safety rules:
  - config files must return arrays;
  - directive keys must follow directive policy;
  - unsupported directive keys fail;
  - directive mixed-level violations fail;
  - unsafe diagnostics are forbidden;
  - absolute local paths MUST NOT appear in exception messages.
- If a user wants semantic validation for a custom root, the user/module MAY provide a declarative `config/rules.php` ruleset for that root.
- Missing custom validation is user responsibility.

### ConfigKernel Phase B policy summary

The following implementation files below MUST collectively implement:

- deterministic config file discovery for package defaults, skeleton shared config, skeleton environment config, app shared config, and app environment config;
- active Phase B merge order from package defaults to env overlays;
- env overlay projection from known config paths/rules to env var names;
- user-owned/custom root handling where unvalidated custom roots are accepted, merged, explained, fingerprinted, and compiled.

The concrete obligations are assigned to the file-specific deliverables below.
The canonical docs are:

- `docs/ssot/config-merge-order.md`
- `docs/ssot/config-precedence-matrix.md`
- `docs/adr/ADR-0026-config-kernel-merge-directives-reserved-namespaces.md`

### Deliverables (MUST)

#### Creates

Kernel config core:
- [x] `framework/packages/core/kernel/src/Config/ConfigKernel.php` — orchestrates loaders + directives + merge + validate + explain
  - [x] public `compile(...)` result MUST expose safe metadata needed by artifact fingerprinting:
    - [x] `envOverlayMappings`;
    - [x] `configSourceFiles`;
  - [x] `envOverlayMappings` MUST be the exact resolved mappings produced by `EnvironmentOverlayLoader` from rulesets + explicit mappings;
  - [x] `configSourceFiles` MUST be the safe source file metadata produced by config loaders for skeleton/app/user-owned config files;
  - [x] `ConfigKernel` MUST NOT calculate artifact fingerprints;
  - [x] `ConfigKernel` MUST NOT hash compiled artifact envelopes;
  - [x] `ConfigKernel` MUST NOT expose absolute filesystem paths;
  - [x] `ConfigKernel` MUST NOT expose raw config values or raw env values inside safe provenance metadata:
    - [x] `envOverlayMappings`;
    - [x] `configSourceFiles`;
    - [x] `owners`;
    - [x] `sources`;
    - [x] `explain`;
    - [x] logs;
    - [x] metrics;
    - [x] spans.
  - [x] The existing public `config` result remains the final merged global config payload and is not treated as provenance metadata.
  - [x] `ConfigKernel` MUST only pass through or derive safe Phase B source provenance metadata needed for merge, explain, and artifact fingerprint input.

  Construction / dependencies:
  - [x] MUST be an orchestration service, not a loader, validator, merger, or explainer implementation.
  - [x] MUST receive `ConfigMerger`, `ConfigRulesLoader`, `ConfigValidator`, `ConfigExplainer`, `PackageDefaultsConfigLoader`, `SkeletonConfigLoader`, and `EnvironmentOverlayLoader` through constructor wiring.
  - [x] MUST receive or be passed the immutable `EnvRepositoryInterface` snapshot produced by Bootstrap Phase A.
  - [x] MUST receive or be passed `BootstrapConfig` produced by Bootstrap Phase A.
  - [x] MUST receive or be passed `ModulePlan` produced by the module plan resolver.
  - [x] MUST NOT construct config primitives with mutable runtime state.
  - [x] MUST NOT mutate `ConfigNamespaceGuard`, `DirectiveProcessor`, or `ConfigMerger` during pipeline execution.
  - [x] MUST treat `ConfigNamespaceGuard` wiring as a provider/factory responsibility.
  - [x] MUST NOT re-read final merged config to reconfigure namespace guard during the same pipeline run.

  Config location inputs:
  - [x] MUST consume explicit package default source candidates supplied by the Kernel config-location source builder.
  - [x] MUST consume explicit package rules source candidates supplied by the Kernel config-location source builder.
  - [x] MUST consume explicit skeleton/app split-root candidate names as a deterministic path list.
  - [x] MUST consume optional explicit env overlay mappings only when supplied by a future user/module mapping mechanism.
  - [x] MUST NOT infer package filesystem paths from `ModulePlanEntry`.
  - [x] MUST NOT scan package directories.
  - [x] MUST NOT scan skeleton/app config directories.
  - [x] MUST NOT scan arbitrary directories outside declared config locations.
  - [x] MUST treat package/root ownership metadata as coming from the Kernel config-location source builder derived from `docs/ssot/config-roots.md`.
  - [x] MUST preserve safe logical source paths, source ids, package ids, module ids, root names, and layer/kind metadata for explain/diagnostics.
  - [x] MUST NOT expose or return absolute filesystem paths in config results, explain traces, logs, metrics, or diagnostics.

  Canonical documentation / rank order:
  - [x] MUST orchestrate the active Phase B merge pipeline in deterministic rank order.
  - [x] MUST treat `docs/ssot/config-merge-order.md` and `docs/ssot/config-precedence-matrix.md` as the canonical rank-order documentation.
  - [x] MUST NOT invent source precedence in loaders, validator, explainer, or merger.
  - [x] MUST keep source rank/order explicit in ConfigKernel-level entry metadata.
  - [x] MUST keep rules loading separate from config value precedence; rulesets are validation/overlay metadata, not config value sources.

  Phase B pipeline order:
  - [x] MUST load package rulesets before env overlay generation.
  - [x] MUST load package-owned rulesets through `ConfigRulesLoader`.
  - [x] MUST load only package rulesets from enabled ModulePlan modules.
  - [x] MUST NOT require every user-owned/custom root to have a ruleset.
  - [x] MUST compute validation subjects after final config is built.
  - [x] MUST call package defaults loading before skeleton/app/env overlays.
  - [x] MUST load package defaults through `PackageDefaultsConfigLoader`.
  - [x] MUST load package defaults only from enabled ModulePlan modules.
  - [x] MUST load skeleton/app config through `SkeletonConfigLoader`.
  - [x] MUST pass deterministic split root names to `SkeletonConfigLoader`.
  - [x] MUST load env overlays through `EnvironmentOverlayLoader` after rulesets are available.
  - [x] MUST pass only the immutable `EnvRepositoryInterface` snapshot to `EnvironmentOverlayLoader`.
  - [x] MUST NOT read `$_ENV`, `$_SERVER`, or `getenv()`.
  - [x] MUST NOT read `skeleton/config/app.php`; that file is Phase A bootstrap-only input.
  - [x] MUST treat CLI/runtime overrides as reserved/future unless explicitly introduced by a later epic.

  Per-file directive processing:
  - [x] MUST rely on loaders/`DirectiveProcessor` to apply directive namespace/type processing per file before merge.
  - [x] MUST NOT apply directives before the source file has been normalized.
  - [x] MUST NOT apply directives while loading rulesets.
  - [x] MUST merge only normalized file payloads.
  - [x] MUST apply normalized directives at merge time through `ConfigMerger`, where the previous/base value is known.
  - [x] MUST preserve directive names in safe source metadata when available.
  - [x] MUST NOT include directive payload values in diagnostics, logs, metrics, or explain output.

  Merge entries:
  - [x] MUST represent every loaded config value source as a deterministic merge entry.
  - [x] Package default entries MUST be weaker than skeleton/app/env entries.
  - [x] Skeleton shared aggregate `roots.php` entries MUST be weaker than skeleton shared split `<root>.php` entries at the same layer.
  - [x] Skeleton environment aggregate `roots.php` entries MUST be weaker than skeleton environment split `<root>.php` entries at the same layer.
  - [x] App shared aggregate `roots.php` entries MUST be weaker than app shared split `<root>.php` entries at the same layer.
  - [x] App environment aggregate `roots.php` entries MUST be weaker than app environment split `<root>.php` entries at the same layer.
  - [x] Skeleton shared entries MUST be weaker than skeleton environment entries.
  - [x] Skeleton environment entries MUST be weaker than app shared entries.
  - [x] App shared entries MUST be weaker than app environment entries.
  - [x] App environment entries MUST be weaker than env overlay entries.
  - [x] Env overlay entries MUST override only paths for which an env overlay mapping exists and a value is present in the env snapshot.
  - [x] Unknown env vars MUST NOT create config entries.
  - [x] ConfigKernel MUST fold entries through `ConfigMerger` in the active Phase B rank order.
  - [x] ConfigKernel MUST NOT merge entries manually using ad-hoc array logic.

  Final validation:
  - [x] MUST run semantic validation only after the final merged global config is built.
  - [x] MUST validate only roots with loaded rulesets.
  - [x] MUST validate framework-owned roots strictly according to owner package rules.
  - [x] MUST validate module-owned roots strictly when module-owned rules are present.
  - [x] MUST NOT invent validation rules for user-owned/custom roots.
  - [x] MUST NOT reject user-owned/custom roots solely because they do not have rules.
  - [x] MUST obtain validation subjects from `ConfigValidator::validationSubjects()`.
  - [x] MUST mark user-owned/custom roots without rules as `user_owned` and `unvalidated`.
  - [x] MUST preserve validation result for explain.
  - [x] MUST throw `ConfigInvalidException` or return a failing validation result according to the public ConfigKernel API design.
  - [x] MUST keep validation diagnostics deterministic and safe:
    - [x] stable reason tokens;
    - [x] dot-path config paths;
    - [x] no raw config values;
    - [x] no raw env values;
    - [x] no absolute paths;
    - [x] no previous throwable messages exposed.

  Effective source tracing:
  - [x] MUST build effective per-path source traces during Phase B merge.
  - [x] MUST build source traces in lockstep with value merging.
  - [x] MUST pass effective per-path source traces to `ConfigExplainer`.
  - [x] MUST NOT pass only root-level loader sources when explaining nested effective config paths.
  - [x] MUST preserve the source of weaker values that survived stronger partial overrides.
  - [x] MUST record source type for every effective config path.
  - [x] MUST record source id for every effective config path.
  - [x] MUST record source precedence/rank for every effective config path.
  - [x] MUST record source order/tie-break position for every effective config path.
  - [x] MUST record key path for path-specific effective sources.
  - [x] MUST record directive name when a directive affected the effective value.
  - [x] MUST mark env-overlay effective paths as redacted.
  - [x] MUST NOT store raw config values in source traces.
  - [x] MUST NOT store raw env values in source traces.
  - [x] MUST NOT store absolute filesystem paths in source traces.

  Source trace merge semantics:
  - [x] Scalar replacement MUST make the higher-rank source effective for the replaced path.
  - [x] List replacement MUST make the higher-rank source effective for the replaced list path.
  - [x] Map merge MUST preserve weaker source traces for keys not touched by the higher-rank patch.
  - [x] Map merge MUST update source traces only for keys touched by the higher-rank patch.
  - [x] `@merge` MUST preserve weaker source traces for untouched nested keys.
  - [x] `@replace` MUST mark the replaced node as coming from the directive source.
  - [x] `@append` MUST preserve existing list item traces where feasible and mark appended items/list mutation as directive source.
  - [x] `@prepend` MUST preserve existing list item traces where feasible and mark prepended items/list mutation as directive source.
  - [x] `@remove` MUST remove traces for removed list items and mark the resulting list mutation as directive source.
  - [x] If item-level list source tracking is too fine-grained for this epic, the resulting list node MUST at least be marked with the directive source and redaction-safe metadata.
  - [x] Trace sorting MUST be deterministic by precedence, path, source id, and source type.

  Explain capability:
  - [x] Config explain capability MUST be a baseline kernel facility.
  - [x] Config explain capability MUST NOT be feature-disabled through config.
  - [x] Whether explain is produced MUST be decided by the caller/entrypoint, not by a runtime feature flag.
  - [x] MUST call `ConfigExplainer` only after final merge and validation.
  - [x] MUST pass final merged global config shape to `ConfigExplainer`.
  - [x] MUST pass effective per-path source traces to `ConfigExplainer`.
  - [x] MUST pass validation subjects to `ConfigExplainer`.
  - [x] MUST pass validation result to `ConfigExplainer`.
  - [x] MUST pass env overlay mapping metadata to `ConfigExplainer`.
  - [x] MUST explain aggregate-vs-root override behavior.
  - [x] MUST explain shared-vs-environment-vs-app precedence.
  - [x] MUST explain env overlay wins only where env overlay mapping exists.
  - [x] MUST NOT include raw config values in explain output.
  - [x] MUST NOT include raw env values in explain output.
  - [x] MUST NOT include secrets, tokens, DSNs, cookies, headers, raw SQL, payloads, stack traces, previous throwable messages, or absolute local paths in explain output.
  - [x] MAY pass safe metadata for CLI use:
    - [x] normalized relative source ids;
    - [x] config dot paths;
    - [x] directive names;
    - [x] source type;
    - [x] source precedence/order;
    - [x] hash metadata if produced upstream;
    - [x] length metadata if produced upstream.
  - [x] ConfigKernel MUST NOT compute hash/length metadata from raw values inside `ConfigExplainer`; if needed, safe metadata must be produced upstream and passed through `ConfigValueSource::meta()`.

  Public result shape / API:
  - [x] MUST expose final merged global config.
  - [x] MUST expose validation result or throw a canonical config exception according to the chosen API.
  - [x] MAY expose explain output when requested by caller.
  - [x] MUST expose no raw env values.
  - [x] MUST expose no absolute filesystem paths.
  - [x] MUST keep returned arrays deterministic:
    - [x] stable map key ordering;
    - [x] stable source order;
    - [x] stable validation subjects order;
    - [x] stable explain path order.

  Observability ownership:
  - [x] MUST own ConfigKernel-level observability boundaries for config merge and config explain.
  - [x] MUST NOT put config merge/explain metrics or spans inside `ConfigMerger.php`.
  - [x] MUST NOT put config merge/explain metrics or spans inside `DirectiveProcessor.php`.
  - [x] MUST NOT put config merge/explain metrics or spans inside config loaders.
  - [x] MUST NOT put config merge/explain metrics or spans inside `ConfigValidator.php`.
  - [x] MUST NOT put config merge/explain metrics or spans inside `ConfigExplainer.php` unless a later explicit wrapper design changes this.
  - [x] Observability MUST NOT change merge/validation/explain results.
  - [x] Observability failures MUST NOT affect config pipeline success unless observability ports are explicitly defined as throwing by a later policy.

  Spans:
  - [x] MUST emit span `kernel.config_merge` around the full Phase B config merge pipeline.
  - [x] MUST emit span `kernel.config_explain` around explain generation.
  - [x] Span names MUST NOT include config roots, file paths, env names, key paths, app target, app env, exception messages, or user-controlled values.
  - [x] Span attributes, if any, MUST contain only safe bounded metadata.
  - [x] Span attributes MAY include bounded counts:
    - [x] source entry count;
    - [x] ruleset count;
    - [x] validated root count;
    - [x] unvalidated root count;
    - [x] env overlay mapping count.
  - [x] Span attributes MUST NOT include raw config values.
  - [x] Span attributes MUST NOT include raw env values.
  - [x] Span attributes MUST NOT include secrets, tokens, DSNs, cookies, headers, raw SQL, payloads, stack traces, previous throwable messages, or absolute local paths.

  Metrics:
  - [x] MUST emit `kernel.config_merge_total` with labels: `outcome`.
  - [x] MUST emit `kernel.config_merge_duration_ms` with labels: `outcome`.
  - [x] MUST emit `kernel.config_explain_total` with labels: `outcome`.
  - [x] MUST emit `kernel.config_explain_duration_ms` with labels: `outcome`.
  - [x] MUST emit merge metrics exactly once per merge operation.
  - [x] MUST emit explain metrics exactly once per explain operation when explain is requested.
  - [x] MUST record failure metrics when merge/explain throws.
  - [x] MUST use only the `outcome` label for these metrics.
  - [x] MUST NOT use `operation` label for these operation-specific metrics.
  - [x] MUST NOT emit labels such as `root`, `path`, `file`, `key`, `env`, `app`, `source`, `exception`, `request_id`, `tenant_id`, or `user_id`.
  - [x] Metric names MUST be registered in `docs/ssot/observability.md` canonical metrics catalog before runtime emission.
  - [x] Metric labels MUST match both the global label allowlist and the metric-specific catalog row.
  - [x] Metric outcome values MUST be safe bounded values:
    - [x] `success`;
    - [x] `failure`.

  Logs:
  - [x] MAY log config merge/explain lifecycle events only at ConfigKernel orchestration boundaries.
  - [x] Logs MUST contain only safe metadata.
  - [x] Logs MAY include bounded counts:
    - [x] source entry count;
    - [x] ruleset count;
    - [x] validated root count;
    - [x] unvalidated root count;
    - [x] env overlay mapping count.
  - [x] Logs MAY include normalized relative file paths.
  - [x] Logs MAY include config dot paths.
  - [x] Logs MAY include directive names.
  - [x] Logs MAY include source type.
  - [x] Logs MAY include source kind/layer.
  - [x] Logs MAY include durations and outcome.
  - [x] Logs MUST NOT include raw config values.
  - [x] Logs MUST NOT include raw env values.
  - [x] Logs MUST NOT include secrets, tokens, DSNs, cookies, headers, raw SQL, payloads, stack traces, previous throwable messages, or absolute local paths.

  Security / redaction:
  - [x] MUST NOT leak dotenv values, process env values, passwords, tokens, DSNs, cookies, headers, raw SQL, request payloads, stack traces, or absolute local paths.
  - [x] MUST NOT expose previous throwable messages from file loading, env loading, validation, or explain generation.
  - [x] MUST use stable error/reason tokens for failures.
  - [x] MUST keep all diagnostics safe even when source files contain invalid PHP return values.
  - [x] MUST keep all diagnostics safe even when env values are invalid for coercion.
  - [x] MUST keep all diagnostics safe even when paths/source ids from source candidates are invalid.

  Determinism:
  - [x] MUST sort all source entries deterministically before merge.
  - [x] MUST sort rulesets deterministically by root.
  - [x] MUST sort validation subjects deterministically by root.
  - [x] MUST sort explain paths deterministically by dot path.
  - [x] MUST preserve list order unless a directive explicitly changes it.
  - [x] MUST preserve deterministic map key ordering in final merged config.
  - [x] MUST ensure repeated runs with the same inputs produce the same final config and explain trace, excluding observability durations.
  - [x] MUST NOT include wall-clock time, random ids, absolute paths, object ids, or nondeterministic exception text in returned artifacts/explain output.

  Future reserved behavior:
  - [x] CLI/runtime overrides are reserved/future unless explicitly introduced by a later epic.
  - [x] Map/list env overlay syntax is reserved/future unless explicitly introduced by a later typed env syntax epic.
  - [x] Runtime feature flags for enabling/disabling ConfigKernel or explain are forbidden for this baseline.

- [x] `framework/packages/core/kernel/src/Config/ConfigMerger.php` — deterministic merge
  - [x] Lower rank source is weaker; higher rank source overrides lower rank source.
  - [x] MUST merge sources according to the active Phase B rank order supplied by `ConfigKernel`.
  - [x] MUST preserve deterministic map key ordering.
  - [x] MUST preserve list order unless a directive explicitly changes it.
  - [x] MUST apply normalized directives at merge time when the previous/base value is known.
  - [x] MUST NOT invent source precedence.
  - [x] MUST NOT apply env overlay logic directly; env overlays are prepared by `EnvironmentOverlayLoader`.

- [x] `framework/packages/core/kernel/src/Config/DirectiveProcessor.php` — per-file directive processing + typing rules
  - [x] Parses directives per-file before merge.
  - [x] Allows only:
    - [x] `@append`
    - [x] `@prepend`
    - [x] `@remove`
    - [x] `@merge`
    - [x] `@replace`
  - [x] Any unknown `@*` key MUST fail as reserved namespace violation.
  - [x] If a map level contains any `@*` key, that level MUST contain only directive keys.
  - [x] Each directive level MUST contain exactly one directive key.
  - [x] `@append`, `@prepend`, and `@remove` values MUST be lists.
  - [x] `@merge` value MUST be a map.
  - [x] `@replace` value MAY be scalar/list/map.
  - [x] Empty array `[]` MUST be accepted and interpreted by directive context.
  - [x] Locale MUST NOT affect directive classification.

- [x] `framework/packages/core/kernel/src/Config/Explain/ConfigExplainer.php` — source tracking (no secrets)
  - [x] MUST record source type for every effective config path.
  - [x] MUST record effective source rank/order for precedence explain.
  - [x] MUST record whether a root was validated or unvalidated.
  - [x] MUST mark user-owned/custom roots without rules as:
    - [x] `user_owned`
    - [x] `unvalidated`
  - [x] MUST explain aggregate-vs-root override behavior.
  - [x] MUST explain shared-vs-environment-vs-app precedence.
  - [x] MUST explain env overlay wins only where env overlay mapping exists.
  - [x] MUST NOT include raw config values.
  - [x] MUST NOT include raw env values.
  - [x] MUST NOT include secrets, tokens, DSNs, cookies, headers, raw SQL, or absolute local paths.
  - [x] MAY expose safe metadata:
    - [x] normalized relative source ids;
    - [x] config dot paths;
    - [x] directive names;
    - [x] source type;
    - [x] hash/len metadata when needed by CLI.

- [x] `framework/packages/core/kernel/src/Config/Validation/ConfigNamespaceGuard.php` — reserved namespaces guard
  - [x] Guards forbidden top-level roots such as `coretsia` and `_internal`.
  - [x] MUST NOT reject user-owned top-level roots solely because they are not framework-owned.
  - [x] MUST allow unknown/custom top-level roots unless they violate global config safety rules.
  - [x] MUST reserve `@*` keys for directive processing.
  - [x] MUST enforce directive namespace violations before semantic config validation.
  - [x] MUST reject unsupported `@*` directive keys deterministically.
  - [x] MUST reject directive mixed-level violations deterministically.
  - [x] MUST NOT leak raw config values in diagnostics.

Loaders:
- [x] `framework/packages/core/kernel/src/Config/Loaders/PackageDefaultsConfigLoader.php`
  - [x] Loads package defaults only from enabled module package files `config/<root>.php`.
  - [x] MUST use ModulePlan-enabled modules only.
  - [x] Package defaults MUST NOT use `config/roots.php`.
  - [x] Package default files `config/<root>.php` MUST return the subtree for `<root>`.
  - [x] MUST preserve package/root ownership metadata for explain and validation.
  - [x] MUST load files deterministically.
  - [x] MUST NOT scan arbitrary package directories outside ModulePlan-provided package config locations.

- [x] `framework/packages/core/kernel/src/Config/Loaders/SkeletonConfigLoader.php`
  - [x] Loads skeleton shared aggregate config:
    - [x] `skeleton/config/roots.php`
  - [x] Loads skeleton shared root config:
    - [x] `skeleton/config/<root>.php`
  - [x] Loads skeleton environment aggregate config:
    - [x] `skeleton/config/environments/<appEnv>/roots.php`
  - [x] Loads skeleton environment root config:
    - [x] `skeleton/config/environments/<appEnv>/<root>.php`
  - [x] Loads app shared aggregate config:
    - [x] `skeleton/apps/<appTarget>/config/roots.php`
  - [x] Loads app shared root config:
    - [x] `skeleton/apps/<appTarget>/config/<root>.php`
  - [x] Loads app environment aggregate config:
    - [x] `skeleton/apps/<appTarget>/config/environments/<appEnv>/roots.php`
  - [x] Loads app environment root config:
    - [x] `skeleton/apps/<appTarget>/config/environments/<appEnv>/<root>.php`
  - [x] `roots.php` is the aggregate root-map file for a config layer.
  - [x] `<root>.php` is the split root-subtree file for one config root.
  - [x] Root-specific files override `roots.php` files at the same layer.
  - [x] Users MAY place custom roots in `roots.php`.
  - [x] Users MAY split custom roots into dedicated `<root>.php` files.
  - [x] Aggregate and split styles MUST produce the same final global config when their effective root payloads are equivalent.
  - [x] Discovery MUST be deterministic and path-list based.
  - [x] MUST NOT scan arbitrary directories outside declared skeleton/app config locations.
  - [x] MUST NOT read `skeleton/config/app.php`; that file is Phase A bootstrap-only input.
  - [x] MUST return safe `sourceFiles` metadata for every skeleton/app config candidate it resolves;
  - [x] `sourceFiles` MUST distinguish:
    - [x] skeleton shared config files;
    - [x] skeleton environment config files;
    - [x] app shared config files;
    - [x] app environment config files;
    - [x] user-owned/custom split-root files;
  - [x] each source file metadata item MUST include only safe fields:
    - [x] `layer`;
    - [x] `kind`;
    - [x] `root`;
    - [x] `sourceId`;
    - [x] normalized relative `path`;
    - [x] `exists`;
    - [x] `readable`;
    - [x] `hash` when readable;
    - [x] `len` when readable;
  - [x] `hash` MUST be `sha256` over LF-normalized file bytes;
  - [x] missing expected candidates MUST be represented as `exists=false`;
  - [x] unreadable existing candidates MUST be represented as `exists=true`, `readable=false` or fail according to existing loader policy;
  - [x] MUST NOT expose absolute paths;
  - [x] MUST NOT expose raw file contents;
  - [x] MUST NOT expose mtimes, permissions, filesystem owners, hostnames, user names, or process-specific data.

- [x] `framework/packages/core/kernel/src/Config/Loaders/EnvironmentOverlayLoader.php`
  - [x] Builds env overlays only from the immutable `EnvRepositoryInterface` snapshot.
  - [x] MUST NOT read `$_ENV`, `$_SERVER`, or `getenv()`.
  - [x] Config path `kernel.boot.default_env` maps to env var `KERNEL_BOOT_DEFAULT_ENV`.
  - [x] Projection is uppercase ASCII.
  - [x] `.` maps to `_`.
  - [x] `-` maps to `_`.
  - [x] Env overlay generation MUST be allowlisted by config rules / known config paths.
  - [x] Unknown env vars MUST NOT create config keys automatically.
  - [x] User-owned custom roots MAY receive env overlays only when the user/module provides a matching ruleset or explicit env overlay mapping.
  - [x] User-owned custom roots without rules are loaded from config files, merged, explained, fingerprinted, and compiled, but are not env-overlay-expanded automatically.
  - [x] Env values are strings and MUST be coerced only according to declarative config rules.
  - [x] Baseline env scalar coercion supports:
    - [x] `string`
    - [x] `non-empty-string`
    - [x] `non-empty-string-no-ws`
    - [x] `bool`
    - [x] `int`
  - [x] Boolean env coercion is single-choice:
    - [x] `true` and `1` map to `true`;
    - [x] `false` and `0` map to `false`;
    - [x] any other bool token fails deterministically.
  - [x] Env overlays for `map` and `list` are out of scope unless a future typed env syntax is introduced.
  - [x] Env overlay diagnostics MUST NOT expose raw env values.

- [x] `framework/packages/core/kernel/src/Config/ConfigRulesLoader.php`
  - [x] loads package-owned `config/rules.php` files deterministically
  - [x] requires each rules file and accepts only plain array return values
  - [x] rejects callables/closures/objects/resources deterministically
  - [x] MUST preserve package/root ownership metadata supplied by the Kernel config-location source builder derived from `docs/ssot/config-roots.md`.
  - [x] MAY load user/module-owned rulesets when provided by ModulePlan or a future explicit application rules mechanism.
  - [x] MUST NOT require every user-owned/custom root to have a ruleset.
  - [x] MUST preserve ruleset owner metadata for explain and validation diagnostics.

- [x] `framework/packages/core/kernel/src/Config/ConfigValidator.php`
  - [x] validates merged global config using declarative rules arrays
  - [x] supports the baseline rules DSL:
    - [x] `configRoot`
    - [x] `schemaVersion`
    - [x] `additionalKeys`
    - [x] `keys`
    - [x] `required`
    - [x] `type`
    - [x] `items`
    - [x] `allowedValues`
  - [x] supported baseline types:
    - [x] `map`
    - [x] `list`
    - [x] `string`
    - [x] `non-empty-string`
    - [x] `non-empty-string-no-ws`
    - [x] `relative-safe-path`
    - [x] `bool`
    - [x] `int`
  - [x] `relative-safe-path` type:
    - [x] MUST be a string.
    - [x] MUST be non-empty.
    - [x] MUST be relative.
    - [x] MUST NOT be absolute.
    - [x] MUST NOT contain NUL bytes.
    - [x] MUST NOT contain stream wrappers.
    - [x] MUST NOT contain Windows drive-letter prefixes.
    - [x] MUST NOT contain leading `/`.
    - [x] MUST NOT contain leading `\`.
    - [x] MUST NOT contain path traversal segment `..`.
    - [x] MAY contain `/` as a relative path separator.
    - [x] MUST keep diagnostics safe:
      - [x] report only dot-path config path;
      - [x] report stable reason token;
      - [x] MUST NOT echo the raw path value;
      - [x] MUST NOT expose absolute paths.
  - [x] validates each ruleset against the subtree under `configRoot`
  - [x] MUST NOT execute package-provided validation closures
  - [x] Validates only roots with loaded rulesets.
  - [x] MUST validate framework-owned roots strictly according to owner package rules.
  - [x] MUST validate module-owned roots strictly when module-owned rules are present.
  - [x] MUST NOT invent validation rules for user-owned/custom roots.
  - [x] MUST NOT reject user-owned/custom roots solely because they do not have rules.
  - [x] MUST mark user-owned/custom roots without rules as unvalidated validation subjects.
  - [x] MUST report unvalidated user-owned/custom roots to `ConfigExplainer`.
  - [x] MUST keep validation diagnostics deterministic and safe:
    - [x] stable code/reason tokens;
    - [x] dot-path config path;
    - [x] no raw values;
    - [x] no absolute paths.

Errors:
- [x] `framework/packages/core/kernel/src/Config/Exception/ConfigInvalidException.php` — `CORETSIA_CONFIG_INVALID`
- [x] `framework/packages/core/kernel/src/Config/Exception/ConfigReservedNamespaceException.php` — `CORETSIA_CONFIG_RESERVED_NAMESPACE_USED`
- [x] `framework/packages/core/kernel/src/Config/Exception/ConfigDirectiveMixedLevelException.php` — `CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL`
- [x] `framework/packages/core/kernel/src/Config/Exception/ConfigDirectiveTypeMismatchException.php` — `CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH`

SSoT docs (canonical):
- [x] `docs/adr/ADR-0026-config-kernel-merge-directives-reserved-namespaces.md`
- [x] `docs/ssot/config-directives.md` — examples for @append/@prepend/@remove/@merge/@replace (no rule duplication)
- [x] `docs/ssot/config-merge-order.md` — merge order narrative
  - [x] documents the full active Phase B rank order.
  - [x] documents aggregate `roots.php` vs root-specific `<root>.php` behavior.
  - [x] documents shared skeleton vs environment skeleton vs app shared vs app environment precedence.
  - [x] documents directives before merge.
  - [x] documents env overlays after file config.
  - [x] documents validation after final merge.
  - [x] marks CLI/runtime overrides as reserved/future and NOT active Phase 1 behavior.

- [x] `docs/ssot/config-precedence-matrix.md` — precedence matrix
  - [x] includes source type + priority/rank.
  - [x] includes package defaults.
  - [x] includes preset/mode overlays.
  - [x] includes skeleton shared aggregate/root.
  - [x] includes skeleton environment aggregate/root.
  - [x] includes app shared aggregate/root.
  - [x] includes app environment aggregate/root.
  - [x] includes env overlays.
  - [x] includes user-owned/custom roots behavior.
  - [x] includes aggregate-vs-root examples.
  - [x] includes env overlay projection examples.

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/config-directives.md`
  - [x] `docs/ssot/config-merge-order.md`
  - [x] `docs/ssot/config-precedence-matrix.md`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0026-config-kernel-merge-directives-reserved-namespaces.md`
- [x] `framework/packages/core/kernel/config/kernel.php` — adds config kernel keys
- [x] `framework/packages/core/kernel/config/rules.php` — enforces shape
- [x] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php`
  - [x] registers:
    - [x] `ConfigKernel`
    - [x] `ConfigMerger`
    - [x] `DirectiveProcessor`
    - [x] `ConfigExplainer`
    - [x] `ConfigNamespaceGuard`
    - [x] `PackageDefaultsConfigLoader`
    - [x] `SkeletonConfigLoader`
    - [x] `EnvironmentOverlayLoader`
    - [x] `ConfigRulesLoader`
    - [x] `ConfigValidator`

- [x] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php`
  - [x] deterministic factory wiring for ConfigKernel services
  - [x] MUST NOT keep mutable runtime state

- [x] `docs/ssot/observability.md` — register config observability signals
  - [x] Add canonical span `kernel.config_merge`.
  - [x] Add canonical span `kernel.config_explain`.
  - [x] Add metric `kernel.config_merge_total` owned by `core/kernel`, type `counter`, labels `outcome`.
  - [x] Add metric `kernel.config_merge_duration_ms` owned by `core/kernel`, type `observe`, labels `outcome`.
  - [x] Add metric `kernel.config_explain_total` owned by `core/kernel`, type `counter`, labels `outcome`.
  - [x] Add metric `kernel.config_explain_duration_ms` owned by `core/kernel`, type `observe`, labels `outcome`.
  - [x] MUST NOT add `operation` label to these four operation-specific metrics.

### Configuration (keys + defaults) (MUST)

- Files:
  - [x] `framework/packages/core/kernel/config/kernel.php`
- Keys (dot):
  - [x] `kernel.config.forbidden_top_level_roots` = ["coretsia","_internal"]
    - [x] Optional hardening (MAY, if you truly need it later)
    - [x] MUST NOT include "kernel" or "foundation" because apps must be able to configure those roots.
- Rules:
  - [x] `framework/packages/core/kernel/config/rules.php` enforces shape

- [x] `ConfigKernel` and explain capability are baseline kernel facilities and MUST NOT be feature-disabled via config.
- [x] Whether explain is produced is decided by the caller/entrypoint, not by a runtime feature flag.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Observability (policy-compliant)

- [x] Spans:
  - [x] `kernel.config_merge`
  - [x] `kernel.config_explain`
- [x] Metrics:
  - [x] `kernel.config_merge_total` (labels: `outcome`)
  - [x] `kernel.config_explain_total` (labels: `outcome`)
  - [x] `kernel.config_merge_duration_ms` (labels: `outcome`)
  - [x] `kernel.config_explain_duration_ms` (labels: `outcome`)
- [x] Logs:
  - [x] only safe metadata: normalized file paths, key paths, directive names; no values

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] dotenv values, passwords, tokens, DSNs
- [x] Allowed:
  - [x] `hash(value)` / `len(value)` only (printed by CLI layer), never raw
  - [x] normalized file paths + key paths + directive names (no raw values)

### Phase 0 parity: directives + precedence + explain (MUST)

Цей епік MUST бути семантично еквівалентним PHASE 0 SPIKE:
- [x] 0.90.0 Two-phase config merge + directives + explain prototype

#### Directive allowlist (cemented)
Allowlist MUST бути EXACT:
- [x] `@append`, `@prepend`, `@remove`, `@merge`, `@replace`

#### Directive allowlist ownership (cemented)

The directive allowlist is kernel-owned and MUST NOT be configurable via runtime config.
The canonical set is fixed and exact:

- [x] `@append`
- [x] `@prepend`
- [x] `@remove`
- [x] `@merge`
- [x] `@replace`

#### Reserved namespace guard for "@*" (cemented; single-choice)
Будь-який key, що починається з `@`, є RESERVED тільки для directives.

- [x] дозволені тільки allowlist directives
- [x] будь-який інший `@*` key MUST fail детерміновано

#### Exclusive-level rule (cemented)
Якщо map на певному рівні містить хоча б один `@*` key:
- [x] на цьому рівні MUST бути **тільки directives keys**
- [x] MUST бути **рівно один** directive key

#### Typing rules (cemented; incl empty-array rule)
- [x] `@append|@prepend|@remove`: value MUST be list
- [x] `@merge`: value MUST be map
- [x] `@replace`: value може бути scalar/list/map

Empty array rule (cemented):
- [x] `[]` приймається як порожній контейнер і трактується контекстом:
  - [x] list directives → empty list
  - [x] `@merge` → empty map

Classification (cemented):
- [x] non-empty: `array_is_list` визначає list vs map
- [x] locale MUST NOT впливати

#### Two-phase semantics (cemented)
- [x] Phase A (per-file): parse/allowlist/type-validate/normalize directives
- [x] Phase B (merge-time): apply normalized directives коли base вже відомий

#### Error precedence (cemented; single-choice)
- [x] якщо існує unknown `@*` → MUST fail as RESERVED NAMESPACE violation
- [x] else якщо порушено exclusive-level (mixing або multi-directive) → MUST fail as MIXED LEVEL
- [x] else якщо type mismatch → MUST fail as TYPE MISMATCH

#### Error codes alignment (cemented; single-choice)

Kernel ConfigKernel MUST використовувати ті самі code strings, що в spike (0.90.0):

- [x] `CORETSIA_CONFIG_RESERVED_NAMESPACE_USED`
- [x] `CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL`
- [x] `CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH`

Ці коди є “line-of-truth” для contract locks і CI.

Будь-яка майбутня зміна semantics MUST супроводжуватись оновленням spike fixtures + contract locks в тому ж PR.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] Directives semantics:
  - [x] `framework/packages/core/kernel/tests/Unit/DirectivesAppendRemoveListLikeOnlyTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/DirectivesMergeMapLikeOnlyTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/DirectivesExclusiveLevelTest.php`

- [x] Precedence + explain:
  - [x] `framework/packages/core/kernel/tests/Integration/ConfigPrecedenceMatrixTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ConfigExplainSmokeIntegrationTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ConfigExplainShowsPackageDefaultWhenNoSkeletonOverridesTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ConfigExplainReturnsStableSourceTypesTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ReservedNamespaceWriteGuardTest.php`

- [x] Spike compatibility locks:
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeConfigMergeCompatibilityContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeConfigExplainTraceCompatibilityContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeConfigExplainTraceIsSafeContractTest.php`

- [x] Docs are verified by matching tests (no separate “doc tests” required, but docs MUST be consistent):
  - [x] `docs/ssot/config-directives.md` verified by directive unit tests above
  - [x] `docs/ssot/config-merge-order.md` + `docs/ssot/config-precedence-matrix.md` verified by `ConfigPrecedenceMatrixTest`

### Tests (MUST)

- Unit:
  - [x] `framework/packages/core/kernel/tests/Unit/DirectivesAppendRemoveListLikeOnlyTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/DirectivesMergeMapLikeOnlyTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/DirectivesExclusiveLevelTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/Config/ConfigRulesLoaderRejectsCallableRulesTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/Config/ConfigRulesLoaderRequiresPlainArrayRulesTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/Config/ConfigValidatorAcceptsCliRulesFixtureTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/Config/ConfigValidatorRejectsUnknownCliKeysTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/Config/ConfigValidatorRejectsInvalidCliCommandsTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/Config/ConfigValidatorRejectsInvalidCliOutputFormatTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/Config/ConfigValidatorDiagnosticsAreSafeAndDeterministicTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/ConfigValidatorRelativeSafePathTypeTest.php`
    - [x] accepts `resources/modes`
    - [x] accepts `config/modes`
    - [x] rejects empty string
    - [x] rejects absolute Unix paths
    - [x] rejects absolute Windows drive-letter paths
    - [x] rejects paths with `..` traversal
    - [x] rejects stream wrappers
    - [x] rejects NUL bytes
    - [x] diagnostics do not include the raw path value

- Contract:
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeConfigMergeCompatibilityContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeConfigExplainTraceCompatibilityContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeConfigExplainTraceIsSafeContractTest.php`

- Integration:
  - [x] `framework/packages/core/kernel/tests/Integration/ConfigPrecedenceMatrixTest.php`
    - [x] asserts implementation rank order matches `docs/ssot/config-precedence-matrix.md`.
  - [x] `framework/packages/core/kernel/tests/Integration/ConfigExplainSmokeIntegrationTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ConfigExplainShowsPackageDefaultWhenNoSkeletonOverridesTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ConfigExplainReturnsStableSourceTypesTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ReservedNamespaceWriteGuardTest.php`

  - [x] `framework/packages/core/kernel/tests/Integration/ConfigAggregateAndSplitFilesMergeOrderTest.php`
    - [x] asserts `skeleton/config/roots.php` is loaded.
    - [x] asserts `skeleton/config/<root>.php` is loaded.
    - [x] asserts root-specific file overrides `roots.php` at the same layer.
    - [x] asserts app root-specific file overrides app `roots.php` at the same layer.
    - [x] asserts equivalent aggregate/split config produces equivalent final config.

  - [x] `framework/packages/core/kernel/tests/Integration/ConfigEnvironmentSpecificOverlaysPrecedenceTest.php`
    - [x] asserts shared skeleton config is weaker than skeleton environment config.
    - [x] asserts skeleton environment config is weaker than app shared config.
    - [x] asserts app shared config is weaker than app environment config.
    - [x] asserts env overlays win over file config where an env overlay mapping exists.

  - [x] `framework/packages/core/kernel/tests/Integration/UserOwnedConfigRootsAreMergedButNotFrameworkValidatedTest.php`
    - [x] asserts custom top-level roots from `roots.php` are accepted.
    - [x] asserts custom top-level roots from `<root>.php` are accepted.
    - [x] asserts custom roots appear in final config.
    - [x] asserts custom roots appear in explain trace.
    - [x] asserts custom roots are marked `user_owned`.
    - [x] asserts custom roots without rules are marked `unvalidated`.
    - [x] asserts custom roots participate in fingerprint input.
    - [x] asserts framework does not apply owner package rules to unowned roots.

  - [x] `framework/packages/core/kernel/tests/Integration/EnvironmentOverlayProjectionTest.php`
    - [x] asserts `kernel.boot.default_env` maps to `KERNEL_BOOT_DEFAULT_ENV`.
    - [x] asserts projection uses uppercase ASCII.
    - [x] asserts `.` maps to `_`.
    - [x] asserts `-` maps to `_`.
    - [x] asserts unknown env vars do not create config keys.
    - [x] asserts bool env coercion accepts only `true`, `false`, `1`, `0`.
    - [x] asserts env overlay diagnostics do not leak raw env values.

### DoD (MUST)

- [x] Directives semantics match SSoT + unit tests
- [x] Precedence matrix data-driven test green
- [x] Explain trace safe + stable source types
- [x] Reserved namespaces are enforced and tested
- [x] Fully compatible with spike semantics (0.90.0) — confirmed by contract locks
- [x] Any semantic change requires updating spike fixtures + locks in same PR
- [x] `docs/ssot/config-directives.md`:
  - [x] examples відповідають `framework/packages/core/kernel/src/Config/DirectiveProcessor.php`
  - [x] показує типові use-cases: додати middleware у список, змінити map, прибрати значення
  - [x] не дублює правила (rules — у коді/SSoT, doc — приклади)
- [x] `docs/ssot/config-merge-order.md` + `docs/ssot/config-precedence-matrix.md`:
  - [x] відповідають сценаріям `ConfigPrecedenceMatrixTest`
  - [x] фіксують порядок: directives per-file before merge, env overlays win, validate після merge
  - [x] пояснюють: defaults (packages) vs overrides (skeleton) vs overlays (env)
  - [x] if “runtime overrides” are mentioned, the doc MUST explicitly mark them as reserved/future and NOT part of the active Phase 1 merge pipeline
  - [x] мають explicit Non-goals / out of scope (1–5 bullets)
- [x] Example guarantee:
  - [x] When skeleton override uses `@append` to add a middleware class into `http.middleware.system_pre`, then the final merged config contains it in deterministic order and explain exposes the applied directive name/source trace safely.
- [x] Kernel exposes final merged config and safe explain trace for downstream artifact, HTTP, and CLI consumers.
- [x] Non-goals / out of scope (minimum):
  - [x] Kernel не друкує секрети/values; це робить лише CLI шар з редекцією.
  - [x] Не вводить нові правила precedence/directives — лише реалізує/документує SSoT.
- [x] Reset discipline (kernel.reset) — NOT USED in ConfigKernel (MUST)
  - [x] ConfigKernel Phase B MUST NOT trigger reset.
  - [x] Config compilation/validation/explain MUST be deterministic and safe without relying on UoW lifecycle.
  - [x] Rationale: config pipeline is build-time / compile-time; reset is runtime UoW lifecycle (1.280.0).
- [x] Config file discovery supports both aggregate and split user config:
  - [x] `roots.php` global root map;
  - [x] `<root>.php` root subtree;
  - [x] root-specific files override aggregate files at the same layer.
- [x] User-owned/custom roots are accepted, merged, and explained.
- [x] User-owned/custom roots without rules are marked `user_owned` and `unvalidated`.
- [x] Framework validates only roots with loaded declarative rules.
- [x] Unknown env vars do not create config keys.
- [x] Env overlays are generated only for known paths/rules/mappings.
- [x] `docs/ssot/config-merge-order.md` documents the complete active Phase B rank order.
- [x] `docs/ssot/config-precedence-matrix.md` includes aggregate-vs-root and shared-vs-env-vs-app precedence cases.

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

- CLI integration:
  - `platform/cli` owns `coretsia config:compile` command class, output formatting, and exit codes.
  - `platform/cli` owns `coretsia cache:verify` command class, output formatting, and exit codes.
  - `core/kernel` owns only runtime-safe services consumed by CLI:
    - `ArtifactCompiler`;
    - `CacheVerifier`;
    - deterministic compile/verify result arrays or value objects.
  - `core/kernel` MUST NOT depend on `platform/cli`.
  - `core/kernel` MUST NOT render CLI output.
- Artifacts:
  - writes:
    - `skeleton/var/cache/<appTarget>/module-manifest.php`
    - `skeleton/var/cache/<appTarget>/config.php`
    - `skeleton/var/cache/<appTarget>/container.php` (stub; REAL in 1.340.0)
  - reads (verify):
    - validates header + payload schema for the same files

- `routes@1` is owned and emitted only by `platform/routing`.
- `core/kernel` MUST NOT emit a stub or placeholder `routes.php` artifact.

Recommended CLI exit-code mapping owned by `platform/cli`:
- `0` = clean/success;
- `1` = dirty artifacts;
- `2` = invalid artifact or verification failure.

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

#### Hard boundary: no new discovery during artifact/fingerprint production (MUST)

`core/kernel` artifact/fingerprint production MUST NOT reintroduce discovery behavior that previous kernel epics intentionally avoided.

Artifact/fingerprint code MUST NOT:
- scan `framework/packages/**`;
- scan package source trees;
- scan `vendor/**`;
- scan `skeleton/apps/*` to infer applications;
- scan config directories to discover unknown roots;
- infer ModulePlan inputs from filesystem state;
- infer ConfigKernel inputs from filesystem state.

Artifact/fingerprint code MAY hash explicit source candidates already supplied by the entrypoint/config-location owner.

For config fingerprinting, missing explicit source candidates MUST be represented deterministically as `exists=false` metadata so creating or removing a declared candidate path changes the fingerprint without relying on directory scanning.

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

- [x] `framework/packages/core/kernel/src/Boot/Exception/ArtifactRuntimeBootException.php`
- [x] `framework/packages/core/kernel/src/Boot/ArtifactRuntimeBooter.php` Kernel artifact runtime boot public facade
  - [x] `ArtifactRuntimeBooter` is public Kernel API
  - [x] `ArtifactRuntimeBooter` hides Kernel artifact/container internals from other packages
  - [x] `platform-worker` MUST NOT import Kernel artifact/container internal classes
  - [x] `coretsia-worker` MUST use `ArtifactRuntimeBooter`
  - [x] `coretsia-worker` MUST NOT import:
    - [x] `ArtifactEnvelopeFactory`
    - [x] `ArtifactInvalidException`
    - [x] `PhpArtifactReader`
    - [x] `ArtifactSchemaValidator`
    - [x] `CompiledContainerFactory`

- [x] `framework/packages/core/kernel/src/Artifacts/Fingerprint/ConfigFingerprintInputBuilder.php`
  - [x] MUST produce deterministic safe fingerprint input from:
    - [x] the resolved `BootstrapConfig`;
    - [x] the resolved `ModulePlan`;
    - [x] the `ConfigKernel::compile(...)` result:
      - [x] `config`;
      - [x] `sources`;
      - [x] `owners`;
      - [x] `validation`;
      - [x] `validationSubjects`;
      - [x] `envOverlayMappings`;
      - [x] `configSourceFiles`;
    - [x] the same explicit config source candidate arrays passed to `ConfigKernel::compile(...)`;
    - [x] the immutable `EnvRepositoryInterface` source metadata needed for env-overlay provenance.
  - [x] `ConfigKernel` MUST remain the Phase B config orchestration boundary and MUST NOT become the fingerprint builder.
  - [x] MUST NOT re-run config discovery, module discovery, preset resolution, env loading, or config merging.
  - [x] MUST include logical source ids and content hashes for:
    - [x] package config files;
    - [x] preset/mode config overlays;
    - [x] skeleton shared config files;
    - [x] skeleton environment config files;
    - [x] app shared config files;
    - [x] app environment config files;
    - [x] env overlay source metadata;
    - [x] config rules files;
    - [x] ModulePlan / enabled module graph identity.
  - [x] MUST include selected resolved bootstrap identity values:
    - [x] `appTarget`;
    - [x] `appEnv`;
    - [x] `preset`;
    - [x] `debug`;
    - [x] `BootstrapEnvSourcePolicy`.
  - [x] MUST include user-owned/custom config roots.
  - [x] MUST include user-owned/custom config file content hashes.
  - [x] MUST include unvalidated user-owned roots as data.
  - [x] MUST NOT imply semantic validation for user-owned roots when no rules exist.
  - [x] MUST represent missing explicit source candidates as `exists=false`.
  - [x] MUST NOT include raw config values directly.
  - [x] MUST NOT include raw env values directly.
  - [x] MUST NOT include secrets, absolute paths, timestamps, mtimes, permissions, filesystem owners, host-specific bytes, or process-specific bytes.
  - [x] MAY include cryptographic hashes of source content.
  - [x] MAY include cryptographic hashes of normalized compiled payload bytes.
  - [x] dotenv file content hashes MUST be derived from canonical `kernel.env.dotenv.files` templates resolved against `BootstrapConfig::skeletonRoot()` and `BootstrapConfig::appEnv()`;
  - [x] dotenv fingerprinting MAY read only those explicitly resolved dotenv candidate files;
  - [x] missing dotenv candidates MUST be represented as `exists=false`;
  - [x] dotenv fingerprinting MUST NOT enumerate arbitrary dotenv files;
  - [x] dotenv fingerprinting MUST NOT read process env directly.
  - [x] MUST expose only safe bounded metadata that downstream fingerprint observability MAY count or log:
    - [x] bucket names;
    - [x] normalized logical source ids;
    - [x] normalized relative paths;
    - [x] source candidate counts;
    - [x] missing candidate counts;
    - [x] validation subject counts;
  - [x] MUST NOT expose raw config values, raw env values, dotenv values, source file contents, compiled payload bytes, absolute paths, fingerprints, mtimes, permissions, filesystem owners, host-specific bytes, or process-specific bytes for observability.
  - [x] MUST NOT emit spans, metrics, logs, stdout, or stderr directly.

- [x] `framework/packages/core/kernel/src/Artifacts/ArtifactWriter.php`
  - [x] writes text artifacts as LF-only bytes and ensures exactly one final newline;
  - [x] normalizes `\r\n` and `\r` to `\n` before writing;
  - [x] writes through a temporary file created in the same target directory;
  - [x] writes the full temporary file before rename;
  - [x] renames temporary file to final target atomically where supported;
  - [x] removes temporary file on failure when possible;
  - [x] MUST NOT leave partial final artifacts visible;
  - [x] write-time permissions MAY be applied as best-effort safety policy;
  - [x] write-time permissions MUST NOT become cache clean/dirty semantics;
  - [x] MUST NOT include timestamps, absolute paths, tool versions, hostnames, mtimes, permissions, owners, or process-specific bytes in artifact content.
  - [x] owns observability for artifact write operation:
    - [x] span: `kernel.artifacts_write`;
    - [x] metric: `kernel.artifacts_write_total` with labels: `outcome`;
    - [x] metric: `kernel.artifacts_write_duration_ms` with labels: `outcome`;
  - [x] outcome label values MUST be bounded tokens only:
    - [x] `success`;
    - [x] `failure`;
  - [x] MUST NOT use path, artifact name, app target, env, fingerprint, exception class, or reason as metric labels;
  - [x] span attributes MAY include only safe counts and bounded operation metadata;
  - [x] span attributes MUST NOT include absolute paths, raw artifact bytes, payloads, config values, env values, fingerprints, temporary file names, permissions, owners, hostnames, or user names;
  - [x] logs MAY include normalized relative artifact paths, artifact basenames, byte counts, duration milliseconds, and outcome;
  - [x] logs MUST NOT include absolute paths, temp paths, raw bytes, raw payloads, config values, env values, fingerprints, PHP warning text, previous throwable messages, permissions, owners, hostnames, or user names;
  - [x] observability failures MUST NOT change artifact write behavior.
  - [x] MUST receive observability dependencies through public observability ports/interfaces plus Foundation `Stopwatch`:
    - [x] `TracerPortInterface`;
    - [x] `MeterPortInterface`;
    - [x] `LoggerInterface`;
    - [x] `Stopwatch`;
  - [x] MUST NOT know whether observability dependencies are real adapters or Noop adapters;
  - [x] MUST NOT instantiate `NoopLogger`, `NoopMeter`, `NoopTracer`, or other observability implementations directly;
  - [x] log event name SHOULD be a fixed token such as `kernel.artifacts.write`;
  - [x] logger/meter/tracer failures MUST be caught and MUST NOT change artifact write behavior;

- [x] `framework/packages/core/kernel/src/Artifacts/Paths/ArtifactPathResolver.php`
  - [x] resolves artifact output paths from:
    - [x] `BootstrapConfig::skeletonRoot()`;
    - [x] `BootstrapConfig::appTarget()->value`;
    - [x] `kernel.artifacts.cache_dir`;
    - [x] canonical artifact basename;
  - [x] MUST resolve only under `<skeletonRoot>/var/cache/<appTarget>/`;
  - [x] MUST reject path traversal;
  - [x] MUST reject absolute `cache_dir`;
  - [x] MUST reject `cache_dir` values prefixed with `skeleton/`;
  - [x] MUST normalize separators to `/` for diagnostics/explain;
  - [x] MUST NOT leak absolute paths in exception messages.

- [x] `framework/packages/core/kernel/src/Artifacts/PayloadNormalizer.php`
  - [x] normalizes artifact payloads before JSON/PHP emission;
  - [x] treats associative arrays/maps as maps and sorts map keys with bytewise `strcmp`;
  - [x] preserves list order exactly;
  - [x] recursively normalizes nested arrays;
  - [x] rejects any `float`, including `NaN`, `INF`, and `-INF`;
  - [x] rejects objects, resources, closures, and non-scalar/non-array values;
  - [x] MUST produce deterministic diagnostics using only a path-to-value token such as `a.b[3].c`;
  - [x] MUST NOT include the rejected raw value in exception messages;
  - [x] MUST align with Foundation stable JSON byte rules and MUST NOT redefine conflicting serialization semantics;
  - [x] MUST preserve `null`, `bool`, `int`, and `string` values without semantic conversion.
  - [x] rejects non-float invalid json-like values with `ArtifactPayloadInvalidException`;
  - [x] uses `JsonFloatForbiddenException` only for float / NaN / INF / -INF violations;

- [x] `framework/packages/core/kernel/src/Artifacts/Php/StablePhpArrayDumper.php`
  - [x] emits deterministic PHP files returning arrays;
  - [x] output MUST start with `<?php` and return a single array expression;
  - [x] output MUST be LF-only and end with exactly one final newline;
  - [x] map keys MUST be emitted in the normalized order provided by `PayloadNormalizer`;
  - [x] list order MUST be preserved;
  - [x] string escaping MUST be deterministic and independent of locale;
  - [x] integer, boolean, null, list, and map emission MUST be stable across OS/PHP minor versions;
  - [x] MUST NOT emit comments containing timestamps, tool versions, absolute paths, hostnames, user names, or process-specific data;
  - [x] MUST NOT use `var_export()` directly unless wrapped/proven by contract tests to satisfy Coretsia stable emission rules;
  - [x] MUST be covered by rerun-no-diff and canonical-envelope tests;
  - [x] MUST emit PHP files that return the canonical envelope array unchanged;
  - [x] MUST NOT wrap the envelope in another root key;

- [x] `framework/packages/core/kernel/src/Artifacts/Header/ArtifactHeader.php` — immutable canonical artifact header value object:
  - [x] `name`
  - [x] `schemaVersion`
  - [x] `fingerprint`
  - [x] `generator`
  - [x] optional `requires`
  - [x] no timestamps

- [x] `framework/packages/core/kernel/src/Artifacts/ArtifactEnvelopeFactory.php`
  - [x] builds canonical `{ "_meta": ..., "payload": ... }` envelopes;
  - [x] creates a fresh `ArtifactHeader` per artifact;
  - [x] MUST NOT keep current artifact mutable state;
  - [x] MUST NOT include timestamps, absolute paths, tool versions, hostnames, or environment-specific bytes;
  - [x] MUST be the only kernel-owned service that assembles artifact envelopes;
  - [x] every envelope MUST have exactly the top-level keys:
    - [x] `_meta`;
    - [x] `payload`;
  - [x] MUST reject envelope construction if payload is not normalized json-like array data;
  - [x] MUST create envelope-compatible data for PHP artifact emission and cache verification.

Fingerprint:
- [x] `framework/packages/core/kernel/src/Artifacts/Fingerprint/DeterministicFileLister.php` — stable listing + symlink forbidden
  - [x] MAY be used only for explicitly declared fingerprint input roots/candidates;
  - [x] MUST NOT be used to discover modules, config roots, app targets, package lists, or unknown config files;
  - [x] directory listing is allowed only as deterministic hashing of an already-declared input bucket.

- [x] `framework/packages/core/kernel/src/Artifacts/Fingerprint/FingerprintCalculator.php`
  - [x] calculates lowercase hex `sha256` fingerprints over deterministic fingerprint input only;
  - [x] MUST consume the normalized structure produced by `ConfigFingerprintInputBuilder`;
  - [x] MUST serialize fingerprint input through the canonical Foundation `StableJsonEncoder`;
  - [x] MUST NOT use PHP native serialization;
  - [x] MUST NOT include raw config values directly;
  - [x] MUST NOT include raw env values directly;
  - [x] MUST NOT include secrets, absolute paths, timestamps, mtimes, permissions, filesystem owners, hostnames, process ids, random bytes, or locale-dependent bytes;
  - [x] MAY include cryptographic hashes of source file content;
  - [x] MAY include cryptographic hashes of normalized compiled payload bytes;
  - [x] MUST preserve deterministic bucket/key ordering;
  - [x] MUST return a stable 64-character lowercase hex digest;
  - [x] same logical input MUST produce the same fingerprint across OSes;
  - [x] any non-normalizable input MUST fail deterministically without leaking raw values.
  - [x] owns observability for fingerprint calculation operation:
    - [x] span: `kernel.fingerprint_calculate`;
    - [x] metric: `kernel.fingerprint_calculate_total` with labels: `outcome`;
    - [x] metric: `kernel.fingerprint_calculate_duration_ms` with labels: `outcome`;
  - [x] outcome label values MUST be bounded tokens only:
    - [x] `success`;
    - [x] `failure`;
  - [x] MUST NOT use path, source id, config key, app target, env, preset, fingerprint, exception class, or reason as metric labels;
  - [x] span attributes MAY include only safe counts:
    - [x] fingerprint input bucket count;
    - [x] source candidate count;
    - [x] missing source candidate count;
    - [x] env overlay metadata count;
    - [x] validation subject counts;
  - [x] span attributes MUST NOT include raw config values, raw env values, dotenv values, source file contents, compiled payloads, fingerprints, absolute paths, PHP warning text, or previous throwable messages;
  - [x] logs MAY include safe bucket names, normalized relative paths, source counts, missing counts, and outcome;
  - [x] logs MUST NOT include raw config values, raw env values, dotenv values, source file contents, compiled payloads, fingerprints, absolute paths, PHP warning text, or previous throwable messages;
  - [x] observability failures MUST NOT change fingerprint calculation behavior.
  - [x] MUST receive observability dependencies through public observability ports/interfaces plus Foundation `Stopwatch`:
    - [x] `TracerPortInterface`;
    - [x] `MeterPortInterface`;
    - [x] `LoggerInterface`;
    - [x] `Stopwatch`;
  - [x] MUST NOT know whether observability dependencies are real adapters or Noop adapters;
  - [x] MUST NOT instantiate `NoopLogger`, `NoopMeter`, `NoopTracer`, or other observability implementations directly;
  - [x] log event name SHOULD be a fixed token such as `kernel.fingerprint.calculate`;
  - [x] logger/meter/tracer failures MUST be caught and MUST NOT change fingerprint calculation behavior;

- [x] `framework/packages/core/kernel/src/Artifacts/Fingerprint/FingerprintExplainer.php`
  - [x] produces safe deterministic explain data for cache verification and fingerprint diffs;
  - [x] explain output MAY show:
    - [x] normalized logical source ids;
    - [x] normalized relative paths;
    - [x] config key paths;
    - [x] source type;
    - [x] `hash(value)`;
    - [x] `len(value)`;
    - [x] validation status such as `validated` / `unvalidated`;
    - [x] reason tokens such as `missing`, `changed`, `extra`, `invalid`;
  - [x] explain output MUST NOT show:
    - [x] raw config values;
    - [x] raw env values;
    - [x] dotenv values;
    - [x] secrets;
    - [x] raw payloads;
    - [x] raw SQL;
    - [x] absolute paths;
    - [x] PHP warning text;
    - [x] previous throwable messages;
  - [x] MUST order explain entries deterministically using bytewise `strcmp`;
  - [x] MUST be safe for `platform/cli` to render without additional redaction;
  - [x] MUST NOT print output directly.

Builders:
- [x] `framework/packages/core/kernel/src/Artifacts/Builders/ModuleManifestBuilder.php`
  - [x] MUST use `ModulePlan::toArray()` as the canonical payload base;
  - [x] MUST NOT re-resolve modules;
  - [x] MUST NOT read Composer metadata;
  - [x] MUST NOT read mode preset files;
  - [x] MUST NOT scan filesystem paths;
  - [x] MUST preserve the existing ModulePlan exported key order and schema semantics.

- [x] `framework/packages/core/kernel/src/Artifacts/Builders/CompiledConfigBuilder.php`
  - [x] MUST include the full merged global config payload, including user-owned/custom roots.
  - [x] MUST preserve deterministic map key ordering.
  - [x] MUST not drop unvalidated user-owned roots.
  - [x] MUST not mark unvalidated user-owned roots as framework-validated.
  - [x] MUST write canonical artifact envelope `{ "_meta", "payload" }`.
  - [x] MUST consume the `ConfigKernel::compile(...)` result instead of re-running config merge internally.
  - [x] MUST preserve `validationSubjects` metadata for fingerprint/explain linkage.
  - [x] MUST preserve `sources` as safe source metadata only.
  - [x] MUST NOT include raw env values inside provenance metadata:
    - [x] `sources`;
    - [x] `owners`;
    - [x] `envOverlayMappings`;
    - [x] `configSourceFiles`;
    - [x] `validation`;
    - [x] `validationSubjects`.
  - [x] `payload.config` remains the full merged global config payload and is not treated as provenance metadata.
  - [x] MUST NOT include raw filesystem absolute paths from source candidate arrays.
  - [x] MUST NOT include PHP objects such as `ConfigValidationResult` or `ConfigValueSource` directly; payload MUST be scalar/json-like array data only.

- [x] `framework/packages/core/kernel/src/Artifacts/Builders/StubContainerBuilder.php`
  - [x] emits `container@1` as a deterministic stub artifact in this epic;
  - [x] payload MUST be forward-compatible with 1.340.0 compiled container work;
  - [x] payload MUST include:
    - [x] `kind` = `"stub"`;
    - [x] `compiled` = `false`;
    - [x] `services` = [];
    - [x] `aliases` = [];
    - [x] `tags` = [];
  - [x] 1.340.0 MAY emit `kind = "compiled"` only if it remains compatible with `container@1`;
  - [x] if real compiled container payload shape is incompatible, 1.340.0 MUST introduce `container@2`.

Compiler:
- [x] `framework/packages/core/kernel/src/Artifacts/Compiler/ArtifactCompiler.php`
  - [x] orchestrates kernel-owned artifact generation;
  - [x] receives already resolved:
    - [x] `BootstrapConfig`;
    - [x] `ModulePlan`;
    - [x] `EnvRepositoryInterface`;
    - [x] package default config source candidates;
    - [x] package rule source candidates;
    - [x] split root source candidates;
    - [x] explicit rule source candidates;
    - [x] explicit env overlay mappings;
  - [x] invokes `ConfigKernel::compile(...)` exactly once per compile operation with those inputs;
  - [x] builds:
    - [x] `module-manifest.php`;
    - [x] `config.php`;
    - [x] `container.php`;
  - [x] writes artifacts through `ArtifactWriter`;
  - [x] returns deterministic result data for platform/cli rendering;
  - [x] MUST NOT print output;
  - [x] MUST NOT depend on `platform/cli`;
  - [x] MUST NOT invoke runtime UoW/reset lifecycle;
  - [x] MUST NOT reuse an existing compiled config artifact unless the stored artifact fingerprint equals the current fingerprint;
  - [x] on fingerprint mismatch, MUST rebuild and rewrite kernel-owned artifacts;
  - [x] MUST rely on `ArtifactWriter` for artifact write observability and MUST NOT duplicate `kernel.artifacts_write_*` metrics;
  - [x] MUST rely on `FingerprintCalculator` for fingerprint calculation observability and MUST NOT duplicate `kernel.fingerprint_calculate_*` metrics;
  - [x] compile result data MAY include safe counts and normalized relative artifact paths for platform/cli rendering;
  - [x] compile result data MUST NOT include raw payloads, raw config values, raw env values, absolute paths, fingerprints unless explicitly required as safe hash metadata, PHP warning text, stack traces, or previous throwable messages.

Verification:
- [x] `framework/packages/core/kernel/src/Artifacts/Php/PhpArtifactReader.php`
  - [x] reads existing artifact raw bytes for byte-level comparison;
  - [x] parses PHP-returned artifact arrays for envelope/header/payload validation;
  - [x] returns both normalized bytes and parsed envelope data to `CacheVerifier`;
  - [x] converts file read/include/require warnings/errors into deterministic `ArtifactInvalidException`;
  - [x] MUST NOT leak absolute paths, input path strings, PHP warning text, stack traces, or previous throwable messages.

- [x] `framework/packages/core/kernel/src/Artifacts/Verifier/ArtifactSchemaValidator.php`
  - [x] validates canonical envelope shape;
  - [x] validates header fields:
    - [x] `name`;
    - [x] `schemaVersion`;
    - [x] `fingerprint`;
    - [x] `generator`;
    - [x] optional `requires`;
  - [x] validates artifact-specific payload schema for:
    - [x] `module-manifest@1`;
    - [x] `config@1`;
    - [x] `container@1`;
  - [x] MUST NOT rely on PHP object identity or class type semantics.
  - [x] MUST reject any kernel-owned artifact that does not have exactly the canonical top-level envelope shape `{ "_meta", "payload" }`;
  - [x] MUST reject artifact-specific alternative top-level shapes;

- [x] `framework/packages/core/kernel/src/Artifacts/Verifier/CacheVerifier.php`
  - [x] computes current deterministic fingerprint input;
  - [x] rebuilds expected artifact envelopes in memory;
  - [x] dumps expected PHP artifact bytes with `StablePhpArrayDumper`;
  - [x] reads existing artifact normalized bytes and parsed envelope data with `PhpArtifactReader`;
  - [x] validates existing artifact envelope/header/payload schema with `ArtifactSchemaValidator`;
  - [x] normalizes LF bytes before comparison;
  - [x] compares content bytes only;
  - [x] ignores mtime/ctime/permissions/ownership;
  - [x] returns deterministic clean/dirty/invalid result data for CLI rendering;
  - [x] MUST NOT print output;
  - [x] MUST NOT depend on `platform/cli`;
  - [x] MUST NOT invoke `ResetOrchestrator`;
  - [x] MUST NOT start a UnitOfWork.
  - [x] missing expected artifact files MUST produce `dirty=true` with safe reason token `missing`;
  - [x] unreadable files, invalid PHP-returned payloads, invalid envelopes, or schema violations MUST produce `invalid`;
  - [x] missing/dirty/invalid explain output MUST use only artifact basenames and normalized relative paths.
  - [x] stored artifact fingerprint MUST be compared with the current fingerprint;
  - [x] stored fingerprint mismatch MUST produce `dirty=true` with safe reason token `fingerprint_mismatch`;
  - [x] owns observability for cache verification operation:
    - [x] span: `kernel.cache_verify`;
    - [x] metric: `kernel.cache_verify_total` with labels: `outcome`;
    - [x] metric: `kernel.cache_verify_duration_ms` with labels: `outcome`;
  - [x] outcome label values MUST be bounded tokens only:
    - [x] `clean`;
    - [x] `dirty`;
    - [x] `invalid`;
    - [x] `failure`;
  - [x] MUST NOT use path, artifact name, app target, env, preset, fingerprint, exception class, or reason as metric labels;
  - [x] span attributes MAY include only safe counts:
    - [x] expected artifact count;
    - [x] existing artifact count;
    - [x] missing artifact count;
    - [x] dirty artifact count;
    - [x] invalid artifact count;
  - [x] span attributes MUST NOT include absolute paths, raw artifact bytes, raw payloads, config values, env values, fingerprints, PHP warning text, stack traces, or previous throwable messages;
  - [x] logs MAY include normalized relative artifact paths, artifact basenames, safe reason tokens, counts, and verification outcome;
  - [x] logs MUST NOT include absolute paths, raw artifact bytes, raw PHP payloads, raw config values, raw env values, fingerprints, PHP warning text, stack traces, previous throwable messages, mtimes, permissions, owners, hostnames, or user names;
  - [x] observability failures MUST NOT change cache verification result semantics.
  - [x] MUST receive observability dependencies through public observability ports/interfaces plus Foundation `Stopwatch`:
    - [x] `TracerPortInterface`;
    - [x] `MeterPortInterface`;
    - [x] `LoggerInterface`;
    - [x] `Stopwatch`;
  - [x] MUST NOT know whether observability dependencies are real adapters or Noop adapters;
  - [x] MUST NOT instantiate `NoopLogger`, `NoopMeter`, `NoopTracer`, or other observability implementations directly;
  - [x] log event name SHOULD be a fixed token such as `kernel.cache.verify`;
  - [x] logger/meter/tracer failures MUST be caught and MUST NOT change clean/dirty/invalid cache verification semantics;

Errors:
- [x] `framework/packages/core/kernel/src/Artifacts/Exception/ArtifactWriteFailedException.php` — `CORETSIA_ARTIFACT_WRITE_FAILED`
- [x] `framework/packages/core/kernel/src/Artifacts/Exception/ArtifactInvalidException.php` — `CORETSIA_ARTIFACT_INVALID`
- [x] `framework/packages/core/kernel/src/Artifacts/Exception/FingerprintSymlinkForbiddenException.php` — `CORETSIA_FINGERPRINT_SYMLINK_FORBIDDEN`
- [x] `framework/packages/core/kernel/src/Artifacts/Exception/JsonFloatForbiddenException.php` — `CORETSIA_JSON_FLOAT_FORBIDDEN`
- [x] `framework/packages/core/kernel/src/Artifacts/Exception/ArtifactPathInvalidException.php` — `CORETSIA_ARTIFACT_PATH_INVALID`
- [x] `framework/packages/core/kernel/src/Artifacts/Exception/ArtifactPayloadInvalidException.php` — `CORETSIA_ARTIFACT_PAYLOAD_INVALID`

Docs:
- [x] `docs/adr/ADR-0028-kernel-artifacts-fingerprint-cache-verify.md`
- [x] `docs/ssot/artifacts-and-fingerprint.md` - kernel artifact production + fingerprint behavior
  - [x] MUST NOT redefine the canonical artifact envelope, header fields, or artifact registry rows from `docs/ssot/artifacts.md`.
  - [x] Owns only kernel-side production rules for artifacts, fingerprint behavior, exclusions, and verification linkage.
- [x] `docs/ssot/cache-verify.md`

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/artifacts-and-fingerprint.md`
  - [x] `docs/ssot/cache-verify.md`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0028-kernel-artifacts-fingerprint-cache-verify.md`

- [x] `docs/ssot/observability.md`
  - [x] register artifact/fingerprint/cache verify spans;
  - [x] register artifact/fingerprint/cache verify metrics;
  - [x] keep labels limited to `outcome`;
  - [x] do not introduce `path`, `artifact`, `app`, `env`, or `fingerprint` labels.

- [x] `framework/packages/core/kernel/config/kernel.php` — adds artifacts/fingerprint keys
- [x] `framework/packages/core/kernel/config/rules.php` — enforces shape

- [x] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php`
  - [x] registers artifact/fingerprint/cache services as factories only;
  - [x] registration MUST happen after ConfigKernel Phase B service registrations and before Kernel runtime service registrations;
  - [x] provider registration MUST NOT:
    - [x] write artifacts;
    - [x] read artifacts;
    - [x] calculate fingerprints;
    - [x] run cache verification;
    - [x] resolve `BootstrapConfig`;
    - [x] resolve `ModulePlan`;
    - [x] build `EnvRepositoryInterface`;
    - [x] run `ConfigKernel::compile(...)`;
    - [x] invoke `ResetOrchestrator`;
    - [x] start a UnitOfWork;
    - [x] emit stdout/stderr.
  - [x] registers:
    - [x] `ArtifactWriter`
    - [x] `PayloadNormalizer`
    - [x] `StablePhpArrayDumper`
    - [x] `ArtifactEnvelopeFactory`
    - [x] `ArtifactPathResolver`
    - [x] `PhpArtifactReader`
    - [x] `ArtifactSchemaValidator`
    - [x] `ConfigFingerprintInputBuilder`
    - [x] `ArtifactCompiler`
    - [x] `DeterministicFileLister`
    - [x] `FingerprintCalculator`
    - [x] `FingerprintExplainer`
    - [x] `ModuleManifestBuilder`
    - [x] `CompiledConfigBuilder`
    - [x] `StubContainerBuilder`
    - [x] `CacheVerifier`
  - [x] registers observability-aware artifact/fingerprint/cache services as factories only;
  - [x] provider registration MUST NOT start `kernel.artifacts_write`, `kernel.fingerprint_calculate`, or `kernel.cache_verify` spans;
  - [x] provider registration MUST NOT emit artifact/fingerprint/cache metrics;
  - [x] provider registration MUST NOT write artifact/fingerprint/cache logs.

- [x] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php`
  - [x] deterministic factory wiring for artifact/fingerprint/compile/verify services;
  - [x] artifact factory methods MUST be static construction/wiring methods only;
  - [x] artifact factory methods MUST NOT:
    - [x] write files;
    - [x] read generated artifacts;
    - [x] calculate fingerprints;
    - [x] run cache verification;
    - [x] resolve bootstrap/config/module plans;
    - [x] retain the container;
    - [x] retain mutable config snapshots;
    - [x] depend on `ResetOrchestrator`;
  - [x] MUST wire `StableJsonEncoder` / Foundation json-like serialization as the canonical byte-rule dependency;
  - [x] MUST NOT keep mutable runtime state.
  - [x] wires observability dependencies for artifact/fingerprint/cache services:
    - [x] `MeterPortInterface`;
    - [x] `TracerPortInterface`;
    - [x] `Stopwatch`;
    - [x] `LoggerInterface`;
  - [x] factory MUST resolve observability dependencies from their public ports/interfaces only;
  - [x] factory MUST NOT decide whether an observability dependency is real or Noop;
  - [x] factory MUST NOT instantiate `NoopLogger`, `NoopMeter`, `NoopTracer`, or other observability implementations directly;
  - [x] default real-vs-Noop binding is owned by the application/foundation composition layer, not by artifact services;
  - [x] MUST wire observability only into:
    - [x] `ArtifactWriter`;
    - [x] `FingerprintCalculator`;
    - [x] `CacheVerifier`;
  - [x] factory wiring MUST NOT start spans, emit metrics, write logs, calculate fingerprints, write artifacts, read artifacts, or verify cache.
  - [x] artifact/fingerprint/cache services MUST receive non-null observability dependencies:
    - [x] `MeterPortInterface`;
    - [x] `TracerPortInterface`;
    - [x] `LoggerInterface`;
    - [x] `Stopwatch`;
  - [x] artifact/fingerprint/cache service code MUST NOT branch on nullable logger/meter/tracer dependencies;
  - [x] observability adapters MAY still throw, so service code MUST catch observability failures.

- [x] `framework/packages/core/kernel/README.md`
  - [x] remove `config artifact writing` from out-of-scope;
  - [x] add Kernel-owned artifacts/fingerprint/cache verification to package scope;
  - [x] document that artifact services are registered as factories only;
  - [x] document that provider registration does not write/read artifacts, calculate fingerprints, run cache verification, invoke reset, or start UoW.

#### Configuration (keys + defaults)

- [x] Files:
  - [x] `framework/packages/core/kernel/config/kernel.php`
- [x] Keys (dot):
  - [x] `kernel.artifacts.cache_dir` = "var/cache"
    - [x] value is `BootstrapConfig::skeletonRoot()`-relative;
    - [x] value MUST be a non-empty `relative-safe-path`;
    - [x] value MUST NOT contain `skeleton/` prefix;
    - [x] value MUST NOT be absolute;
    - [x] value MUST NOT contain `..`.
  - [x] `kernel.fingerprint.skeleton_ignore_prefixes` = ["var/cache", "var/maintenance"]
    - [x] values are `BootstrapConfig::skeletonRoot()`-relative;
    - [x] values MUST be non-empty `relative-safe-path` strings;
    - [x] values MUST NOT contain `skeleton/` prefix;
    - [x] values MUST NOT be absolute;
    - [x] values MUST NOT contain `..`.
  - [x] No `kernel.fingerprint.env.tracked_keys` config key is introduced.
  - [x] Fingerprint env coverage is derived from:
    - [x] resolved `BootstrapConfig` values:
      - [x] `appTarget`;
      - [x] `appEnv`;
      - [x] `preset`;
      - [x] `debug`;
      - [x] `BootstrapEnvSourcePolicy`;
    - [x] canonical dotenv file templates from `kernel.env.dotenv.files`;
    - [x] existing resolved dotenv file logical names and content hashes;
    - [x] env-overlay mappings produced from rulesets and explicit mappings;
    - [x] `EnvRepositoryInterface::sourceOf($name)` metadata for env names that affect compiled config.
  - [x] Raw env values MUST NOT be included in fingerprint input or explain output.
  - [x] Env value influence MAY be represented only as `hash(value)` and `len(value)` for env names that are already allowlisted by config rules or explicit env overlay mappings.
- [x] Rules:
  - [x] `framework/packages/core/kernel/config/rules.php`
    - [x] adds strict shape validation for `kernel.artifacts.*`;
    - [x] adds strict shape validation for `kernel.fingerprint.*`;
    - [x] removes stale comments saying this epic introduces no artifact config;
    - [x] keeps unknown keys rejected at every declared map level;
    - [x] keeps artifact/fingerprint facilities non-feature-flagged.

- Artifacts pipeline and fingerprint verification are baseline kernel facilities and MUST NOT be feature-disabled via config.
- Artifact schema versions are owner-locked by artifact code/SSoT and MUST NOT be runtime-configurable.
- Fingerprint MUST reuse the canonical dotenv files list from `kernel.env.dotenv.files` defined by Phase A boot; no duplicate dotenv-files list is allowed under `kernel.fingerprint.*`.

NOTE: `rules.php` validates `relative-safe-path` shape only.
The stronger `skeleton/` prefix rejection is enforced by `ArtifactPathResolver`.

#### Artifacts / outputs (if applicable)

- [x] Writes:
  - [x] `skeleton/var/cache/<appTarget>/module-manifest.php` (schemaVersion, deterministic bytes)
  - [x] `skeleton/var/cache/<appTarget>/config.php` (schemaVersion, deterministic bytes)
  - [x] `skeleton/var/cache/<appTarget>/container.php` (stub)
- [x] Reads:
  - [x] validates header + payload schema

`<appTarget>` is derived only from `BootstrapConfig::appTarget()->value`.

Allowed values are the canonical `AppTarget` tokens:
- `web`
- `api`
- `console`
- `worker`

Artifact path resolution MUST NOT invent a separate app id, scan `skeleton/apps/*`, read app config, or infer the active app from filesystem state.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [x] Fingerprint explain MUST NOT include env values; only key names + hashes/len.
- [x] No session ids/tokens stored or printed.

#### Observability (policy-compliant)

- [x] Spans:
  - [x] `kernel.artifacts_write`
  - [x] `kernel.fingerprint_calculate`
  - [x] `kernel.cache_verify`
- [x] Metrics:
  - [x] `kernel.artifacts_write_total` (labels: `outcome`)
  - [x] `kernel.artifacts_write_duration_ms` (labels: `outcome`)
  - [x] `kernel.fingerprint_calculate_total` (labels: `outcome`)
  - [x] `kernel.fingerprint_calculate_duration_ms` (labels: `outcome`)
  - [x] `kernel.cache_verify_total` (labels: `outcome`)
  - [x] `kernel.cache_verify_duration_ms` (labels: `outcome`)
- [x] Logs:
  - [x] lifecycle logs are emitted through `LoggerInterface`;
  - [x] logger implementation MAY be real or Noop, but artifact/fingerprint/cache services MUST NOT know which one they received;
  - [x] logger calls MUST be failure-silent and MUST NOT change artifact/fingerprint/cache behavior;
  - [x] logs MAY include normalized relative paths, artifact basenames, safe bucket names, safe reason tokens, counts, durations, and bounded outcome tokens;
  - [x] logs MUST NOT include secrets, raw payloads, raw config values, raw env values, dotenv values, source file contents, full fingerprints, absolute paths, temp paths, PHP warning text, stack traces, previous throwable messages, mtimes, permissions, owners, hostnames, user names, process ids, or random bytes.
- Ownership:
  - `ArtifactWriter` owns `kernel.artifacts_write`.
  - `FingerprintCalculator` owns `kernel.fingerprint_calculate`.
  - `CacheVerifier` owns `kernel.cache_verify`.
- Dependency model:
  - [x] artifact/fingerprint/cache services MUST depend on observability ports/interfaces only, plus Foundation `Stopwatch` for duration measurement;
  - [x] artifact/fingerprint/cache services MUST NOT instantiate Noop observability implementations;
  - [x] artifact/fingerprint/cache services MUST NOT know whether observability dependencies are real adapters or Noop/no-op adapters;
  - [x] real-vs-Noop/default binding is outside artifact service responsibility;
  - [x] observability failures MUST NOT change deterministic artifact/fingerprint/cache behavior.

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] auth/cookies/session ids/tokens/raw payload/raw SQL
- [x] Allowed:
  - [x] `hash(value)` / `len(value)` / safe ids

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] Artifact/header/payload determinism:
  - [x] `framework/packages/core/kernel/tests/Integration/ArtifactsRerunNoDiffTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/ArtifactsHeaderShapeContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/KernelPhpArtifactsUseCanonicalEnvelopeContractTest.php`
    - [x] asserts kernel-owned PHP artifacts (`module-manifest.php`, `config.php`, `container.php`) return the canonical top-level envelope `{ "_meta", "payload" }`
    - [x] asserts no artifact-specific alternative top-level shape exists
    - [x] asserts builders produce envelopes through the canonical factory path
    - [x] asserts no builder emits artifact-specific alternative top-level shape
  - [x] `framework/packages/core/kernel/tests/Contract/PayloadNormalizerDeterministicOrderTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/KernelArtifactsReuseFoundationStableJsonEncoderContractTest.php`
- [x] Fingerprint cross-OS invariants:
  - [x] `framework/packages/core/kernel/tests/Contract/FingerprintPathSeparatorContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/FingerprintFileListingOrderContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/FingerprintExplainerDeterminismContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/FingerprintIgnoresSkeletonVarTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/CacheVerifyIgnoresMtimeAndPermissionsTest.php`
- [x] Spike locks:
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintGoldenHashLockTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintExplainSafetyLockTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintSymlinkForbiddenLockTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintPathNormalizationCrossOsLockTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeStableJsonEncodingLockTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikePayloadNormalizerDeterministicOrderLockTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeJsonFloatForbiddenLockTest.php`

### Tests (MUST)

- Contract:
  - [x] `framework/packages/core/kernel/tests/Contract/ArtifactsHeaderShapeContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/KernelPhpArtifactsUseCanonicalEnvelopeContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/PayloadNormalizerDeterministicOrderTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/KernelArtifactsReuseFoundationStableJsonEncoderContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/FingerprintPathSeparatorContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/FingerprintFileListingOrderContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/FingerprintExplainerDeterminismContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintGoldenHashLockTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintExplainSafetyLockTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintSymlinkForbiddenLockTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeFingerprintPathNormalizationCrossOsLockTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeStableJsonEncodingLockTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikePayloadNormalizerDeterministicOrderLockTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/SpikeJsonFloatForbiddenLockTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/KernelArtifactsRuntimeDependencyBoundaryContractTest.php`
    - [x] asserts artifact/fingerprint/cache runtime code does not import `Coretsia\Tools\Spikes\*`;
    - [x] asserts artifact/fingerprint/cache runtime code does not import `devtools/*`;
    - [x] asserts artifact/fingerprint/cache runtime code does not import `platform/*`;
    - [x] asserts artifact/fingerprint/cache runtime code does not read `framework/tools/**`;
    - [x] allows `framework/tools/spikes/fixtures/**` only from Contract tests.
  - [x] `framework/packages/core/kernel/tests/Contract/KernelDoesNotEmitRoutesArtifactContractTest.php`
    - [x] artifact compiler does not write `routes.php`;
    - [x] artifact builders list does not contain a routes builder;
    - [x] kernel-owned artifact schema validator does not claim ownership of `routes@1`;
    - [x] `routes@1` remains owned by `platform/routing`.
  - [x] `framework/packages/core/kernel/tests/Contract/KernelArtifactsDocsAndRegistryConsistencyContractTest.php`
    - [x] `docs/ssot/artifacts-and-fingerprint.md` does not redefine global envelope law;
    - [x] `docs/ssot/cache-verify.md` does not redefine artifact registry rows;
    - [x] `docs/ssot/observability.md` contains registered artifact/fingerprint/cache verify metric names;
    - [x] `framework/packages/core/kernel/README.md` no longer lists config artifact writing as out-of-scope.
  - [x] `framework/packages/core/kernel/tests/Contract/StablePhpArrayDumperDeterministicEmissionContractTest.php`
    - [x] emits LF-only PHP with final newline;
    - [x] preserves canonical envelope top-level shape;
    - [x] preserves list order;
    - [x] preserves normalized map order;
    - [x] emits stable bytes on repeated runs.
  - [x] `framework/packages/core/kernel/tests/Contract/FingerprintCalculatorStableInputContractTest.php`
    - [x] same normalized input produces same 64-char lowercase sha256;
    - [x] map key insertion order does not affect fingerprint;
    - [x] list order affects fingerprint;
    - [x] raw config/env values are absent from normalized fingerprint input fixtures;
  - [x] `framework/packages/core/kernel/tests/Contract/FingerprintExplainerRedactionContractTest.php`
    - [x] explain output includes only safe ids, key paths, relative paths, hash/len metadata, and validation status;
    - [x] explain output does not include raw config values;
    - [x] explain output does not include raw env values;
    - [x] explain output does not include absolute paths.
  - [x] `framework/packages/core/kernel/tests/Contract/KernelArtifactsObservabilityPolicyContractTest.php`
    - [x] asserts artifact/fingerprint/cache observability names are exactly:
      - [x] `kernel.artifacts_write`;
      - [x] `kernel.fingerprint_calculate`;
      - [x] `kernel.cache_verify`;
      - [x] `kernel.artifacts_write_total`;
      - [x] `kernel.artifacts_write_duration_ms`;
      - [x] `kernel.fingerprint_calculate_total`;
      - [x] `kernel.fingerprint_calculate_duration_ms`;
      - [x] `kernel.cache_verify_total`;
      - [x] `kernel.cache_verify_duration_ms`;
    - [x] asserts metrics use only the `outcome` label;
    - [x] asserts no metric label uses `path`, `artifact`, `app`, `env`, `preset`, `fingerprint`, `reason`, or `exception`;
    - [x] asserts observability logs do not include absolute paths, raw payloads, raw config values, raw env values, fingerprints, PHP warning text, stack traces, or previous throwable messages.
- Unit:
  - [x] `framework/packages/core/kernel/tests/Unit/FingerprintInstalledManifestNormalizationTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/ArtifactPathResolverUsesBootstrapAppTargetTest.php`
    - [x] resolves paths under `var/cache/<appTarget>/`;
    - [x] rejects absolute cache_dir;
    - [x] rejects `..`;
    - [x] rejects `skeleton/`-prefixed cache_dir;
    - [x] normalizes diagnostics to relative safe paths only.
  - [x] `framework/packages/core/kernel/tests/Unit/PayloadNormalizerRejectsUnsafeValuesTest.php`
    - [x] rejects floats;
    - [x] rejects `NaN`, `INF`, `-INF`;
    - [x] rejects objects/resources/closures;
    - [x] exception message includes path token only;
    - [x] exception message does not include raw value.
  - [x] `framework/packages/core/kernel/tests/Unit/ConfigFingerprintInputBuilderBuildsSafeBucketsTest.php`
    - [x] includes declared source candidates as logical ids;
    - [x] represents missing candidates as `exists=false`;
    - [x] includes user-owned roots as unvalidated when no rules exist;
    - [x] includes ModulePlan identity;
    - [x] does not include raw config/env values.
- Integration:
  - [x] `framework/packages/core/kernel/tests/Integration/ArtifactsRerunNoDiffTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/FingerprintIgnoresSkeletonVarTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/CacheVerifyIgnoresMtimeAndPermissionsTest.php`
    - [x] touching only mtime MUST keep `dirty=false`
    - [x] changing only permissions/ownership metadata MUST keep `dirty=false`
    - [x] bytes unchanged remains the only clean/dirty criterion
  - [x] `framework/packages/core/kernel/tests/Integration/FingerprintIncludesUserOwnedConfigRootsTest.php`
    - [x] asserts custom roots affect config fingerprint.
    - [x] asserts changing a user-owned config value changes fingerprint.
    - [x] asserts user-owned roots are present in compiled config artifact.
    - [x] asserts fingerprint explain marks user-owned roots as `unvalidated` when no rules exist.
    - [x] asserts fingerprint explain does not leak raw custom values.
  - [x] `framework/packages/core/kernel/tests/Integration/CompiledConfigKeepsUserOwnedRootsTest.php`
    - [x] asserts user-owned roots from `roots.php` are emitted.
    - [x] asserts user-owned roots from `<root>.php` are emitted.
    - [x] asserts split and aggregate equivalent user config produces equivalent artifact payload.
  - [x] `framework/packages/core/kernel/tests/Integration/KernelArtifactServicesRegisterAsFactoriesOnlyTest.php`
    - [x] provider registration does not write artifacts;
    - [x] provider registration does not read artifacts;
    - [x] provider registration does not calculate fingerprints;
    - [x] provider registration does not run cache verification;
    - [x] provider registration does not resolve `BootstrapConfig`;
    - [x] provider registration does not resolve `ModulePlan`;
    - [x] provider registration does not run `ConfigKernel::compile(...)`.
  - [x] `framework/packages/core/kernel/tests/Integration/KernelArtifactServicesDoNotUseResetOrUowTest.php`
    - [x] artifact compile does not invoke `ResetOrchestrator`;
    - [x] cache verify does not invoke `ResetOrchestrator`;
    - [x] artifact compile does not start a UnitOfWork;
    - [x] cache verify does not start a UnitOfWork.
  - [x] `framework/packages/core/kernel/tests/Integration/CacheVerifyDetectsArtifactByteDriftTest.php`
    - [x] compile artifacts;
    - [x] verify returns `dirty=false`;
    - [x] mutate artifact bytes while preserving valid PHP syntax and canonical envelope shape;
    - [x] verify returns `dirty=true`;
    - [x] mutate artifact bytes into invalid PHP syntax;
    - [x] verify returns `invalid`;
    - [x] explain does not leak raw payload values;
    - [x] explain does not leak absolute paths.
  - [x] `framework/packages/core/kernel/tests/Integration/ArtifactWriterAtomicNoPartialWriteTest.php`
    - [x] failed write does not leave a partially written final artifact;
    - [x] failed write cleans temporary files when possible;
    - [x] successful write produces LF-only bytes with final newline;
    - [x] write-time permission changes do not affect cache verify clean/dirty semantics.
  - [x] `framework/packages/core/kernel/tests/Integration/KernelArtifactObservabilityDoesNotChangeBehaviorTest.php`
    - [x] failing meter does not fail artifact write;
    - [x] failing tracer does not fail fingerprint calculation;
    - [x] failing logger does not fail cache verification;
    - [x] observability failures do not change clean/dirty/invalid cache verification semantics.
    - [x] artifact/fingerprint/cache services depend only on observability ports/interfaces;
    - [x] artifact/fingerprint/cache services do not instantiate concrete observability implementations directly, including Foundation Noop implementations;
    - [x] fake no-op implementations of public observability ports can be injected without changing compile/verify behavior;
    - [x] failing observability port implementations do not change artifact write, fingerprint calculation, or cache verification behavior;
    - [x] real-vs-no-op/default observability binding is not asserted by this epic;

### DoD (MUST)

- [x] Rerun no diff for generated artifacts
- [x] Cross-OS deterministic invariants verified by contract tests
- [x] cache:verify clean immediately after compile
- [x] Docs explain middleware linkage + fingerprint exclusions
- [x] Spike fixtures lock production invariants
- [x] Artifacts live only in `skeleton/var/cache/<appTarget>/*` and are written atomically (no partial writes).
- [x] Compiled config preserves platform-owned config data without semantic ownership:
  - [x] compiled config payload preserves `http.middleware.<slot>` lists as data when present;
  - [x] compiled config payload preserves `http.middleware.auto.*` toggles as data when present;
  - [x] `core/kernel` MUST NOT import or depend on `platform/http`;
  - [x] downstream `platform/http` MAY consume these fields from compiled config without reading source config files.
- [x] When only `skeleton/var/maintenance/*` changes, then fingerprint remains unchanged and `cache:verify` stays clean.
- [x] Reset discipline (kernel.reset) — NOT USED in artifacts/fingerprint/cache:verify (MUST)
  - [x] Artifacts pipeline MUST NOT invoke reset and MUST NOT require UoW lifecycle.
  - [x] Cache verification MUST remain pure (read/compare) and deterministic:
    - [x] no dependency on `ResetOrchestrator` execution
  - [x] Rationale: artifacts/fingerprint are build-time concerns; reset is runtime UoW boundary enforcement.
- [x] User-owned/custom roots are included in config fingerprint inputs.
- [x] User-owned/custom roots are included in compiled config artifact payload.

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
artifacts_introduced: []  # no new artifact identity; this epic defines the first REAL payload schema for existing `container@1`. `1.330.0` materialized transitional stub bytes for `container@1`. `1.340.0` replaces stub semantics with REAL compiled-container semantics. This is not a `container@2` introduction.
adr: "docs/adr/ADR-0029-kernel-container-compile-artifact.md"
ssot_refs:
- "docs/ssot/compiled-container.md" # compiled-container-specific payload + boot semantics
- "docs/ssot/artifacts.md"          # global artifact law
---

### `container@1` Schema Evolution Note (MUST)

`container@1` is the Kernel-owned artifact identity for the compiled container artifact in Phase 1.

The `1.330.0` payload was a transitional deterministic stub used to materialize the artifact slot before real compiled-container semantics existed.

This epic defines the first REAL `container@1` payload schema in `docs/ssot/compiled-container.md`.

The REAL `container@1` payload MAY extend or replace the earlier stub payload shape, provided that it remains compatible with:

- the canonical artifact identity `container@1`;
- the global artifact envelope `{ "_meta": <header>, "payload": <payload> }`;
- canonical artifact header semantics;
- deterministic serialization law;
- `core/kernel` ownership of the container payload schema.

Compatibility for this epic means compatibility with `container@1` identity and global artifact law.

It does **not** mean preserving the earlier `1.330.0` stub payload as a supported runtime container format.

A future `container@2` is required only if a later change needs to preserve an already-stable REAL `container@1` payload contract while introducing an incompatible compiled-container payload format.

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
  - Future `platform/cli` command `coretsia config:compile` will trigger Kernel artifact compilation through the Kernel ops façade.
  - The CLI command itself is owned by `2.30.0 Platform CLI` and is not a blocking deliverable of this epic.
- Artifacts:
  - writes: `skeleton/var/cache/<appTarget>/container.php` (compiled `container@1`)

### Artifact-Only Runtime Boot Boundary (MUST)

Production runtime boot paths covered by this epic MUST use compiled-artifact boot.

Production runtime boot MUST read `container.php` through `CompiledContainerFactory`.

Production runtime boot MUST NOT register runtime providers into Foundation `ContainerBuilder` as an implicit fallback.

Provider-based container construction remains allowed only for:

- compile-time artifact production;
- test scaffolding;
- explicitly documented non-production paths outside this epic.

Any future developer-mode fallback requires a separate epic/ADR and MUST NOT be implied by this epic.

### Compiled Container Boot Inputs (MUST)

`CompiledContainerFactory` MUST NOT read source config files.

Runtime boot MUST use artifact-owned inputs only.

For this epic, runtime boot uses:

- `container@1` for compiled service definitions, aliases, parameters, and tags;
- `config@1` as the runtime config snapshot input.

`container@1` MUST NOT duplicate the full compiled config payload unless `docs/ssot/compiled-container.md` explicitly defines such duplication as part of the REAL `container@1` schema.

`CompiledContainerFactory` MUST receive an already-read and already-validated `config@1` payload from the caller.

Reading, parsing, and schema-validating `config@1` remain owned by Kernel artifact reading/schema infrastructure outside `CompiledContainerFactory`.

`CompiledContainerFactory` MUST NOT read source config files and MUST NOT run source config discovery.

`container@1` MUST NOT embed raw secrets unless those values are already part of the canonical compiled config artifact semantics.

### Phase 0 parity: deterministic artifacts discipline (container.php) (MUST)

Цей епік активує REAL `container.php` artifact і MUST наслідувати ті самі “deterministic bytes” інваріанти,
які зацементовані Phase 0 rails (0.20.0) та artifacts policy (узгоджено з 1.330.0).

#### Hard boundary: no Phase 0 tooling deps in runtime (MUST)
`core/kernel` runtime MUST NOT:
- залежати від `devtools/*` (включно `devtools/internal-toolkit`, `devtools/cli-spikes`)
- імпортувати `Coretsia\Tools\Spikes\*`
- читати `framework/tools/**` у runtime execution
  (Spike fixtures допускаються тільки в test-time locks.)

#### Gate boundary: closure definitions are compiler semantics, not a standalone static gate (MUST)

This epic MUST NOT introduce `framework/tools/gates/container_no_closure_definitions_gate.php`.

Closure rejection is a semantic compiled-container invariant, not a generic PHP-source invariant:
- closures / anonymous functions are forbidden only when they become container definitions, factories, compiled graph entries, or artifact payload values;
- ordinary closure usage in unrelated tests/fixtures/tools is outside this epic’s runtime policy;
- enforcement MUST live in `ContainerCompiler` and be locked by Contract/Integration tests.

The canonical enforcement chain is:
- `ContainerCompiler` rejects closure / anonymous-function based definitions or factories before artifact write;
- `CompiledContainerRejectsClosureDefinitionsDeterministicallyTest.php` locks the failure semantics;
- artifact/header/schema validation covers only the already-produced artifact envelope and payload shape, not provider-source semantics.

If the compiled-container pipeline is ever not executed by CI on every relevant PR, a later tooling epic MAY introduce a broader `compiled_container_policy_gate.php`.
That future gate, if introduced, MUST inspect the compiled definition graph / artifact payload and MUST NOT be named `container_no_closure_definitions_gate.php`.

## Required invariants for compiled container (cemented; single-choice)

### Descriptor-Based Compile Input (MUST)

Compiled container input MUST be descriptor-based and closure-free.

`ContainerCompiler` MUST NOT serialize or inspect runtime closures captured by `Foundation\ContainerBuilder::factory(...)`.

Runtime provider closures MAY exist in non-artifact builder mode, compile-time wiring, tests, or provider-based scaffolding.

However, any definition that reaches the compiled-container graph MUST be represented as deterministic schema data.

Provider-owned runtime factories MAY be represented in the compiled graph only as deterministic references, for example:

- factory class;
- factory method;
- service references;
- parameter references;
- scalar/list/map arguments.

The compiled graph MUST NOT contain:

- `Closure`;
- anonymous function;
- callable object;
- raw PHP callable array as runtime data;
- source code snippet;
- reflection file/line metadata;
- absolute paths;
- runtime callable payload.

### REAL `container@1` Payload Schema (MUST)

The REAL `container@1` payload schema is owned by `docs/ssot/compiled-container.md`.

The REAL payload MUST use the canonical artifact envelope from `docs/ssot/artifacts.md` and MUST NOT introduce a container-specific top-level artifact shape.

The REAL payload MUST set:

```text
kind = compiled
compiled = true
```

The REAL payload MAY define top-level payload fields such as:

- `aliases`;
- `compiled`;
- `kind`;
- `parameters`;
- `services`;
- `tags`.

The exact REAL `container@1` payload field set is defined by `docs/ssot/compiled-container.md`.

Adding `parameters` or other schema-owned top-level payload fields in this epic does **not** require `container@2`.

The earlier `1.330.0` stub payload:

```text
kind = stub
compiled = false
```

is a transitional placeholder and is not the supported runtime compiled-container format after this epic.

`CompiledContainerFactory` MUST NOT accept the old stub payload as a production runtime container artifact.

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
- no closure / anonymous-function based service definitions, factories, configurators, lazy factories, or compiled graph values MAY reach artifact serialization
- compiled definition graph values MUST be deterministic schema values only: scalars, lists, maps, class/service ids, references, parameters, and schema-owned structured values
- callable-like runtime behavior MUST be represented by deterministic service ids / class references, never by serialized PHP callables or closures

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
- invalid, unreadable, schema-invalid, or legacy-stub `container.php` hard-fails with deterministic code `CORETSIA_CONTAINER_ARTIFACT_INVALID` (без path leaks)
- closure / anonymous-function based container definitions are rejected before artifact write:
  - failure code: `CORETSIA_CONTAINER_COMPILE_FAILED`
  - message token: `container-compile-failed`
  - no closure dumps, source snippets, absolute paths, raw config values, env-specific bytes, or OS error messages
  - this verification MUST be part of the canonical CI/test rail for this epic, not an optional local-only check

### Deliverables (MUST)

#### Creates

Compiler:
- [x] `framework/packages/core/kernel/src/Container/ContainerCompiler.php` — builds deterministic definition graph (json-like)
  - [x] MUST compile only descriptor-based, closure-free container input.
  - [x] MUST produce a deterministic `DefinitionGraph`.
  - [x] MUST use `ServiceDefinition`, `ParameterBag`, and `DefinitionGraph` as kernel-owned compilation models.
  - [x] MUST preserve the caller-supplied deterministic provider/module order exactly.
  - [x] MUST NOT globally re-sort providers before applying definition override semantics.
  - [x] MUST preserve the canonical Foundation binding-collision rule exactly:
    - [x] for the same service id / interface binding, the later provider definition overrides the earlier one deterministically;
    - [x] compiler output MUST NOT invent a different override policy from `core/foundation`.
  - [x] if compiled output materializes tag registrations / discovery lists, it MUST preserve Foundation tag semantics exactly:
    - [x] dedupe remains first-wins per `(tag, serviceId)`;
    - [x] final discovery order remains the canonical Foundation order;
    - [x] compiler/runtime MUST NOT introduce a second tag-merge policy.
  - [x] Rationale:
    - [x] compiled-container semantics MUST remain aligned with `core/foundation` `ContainerBuilder`;
    - [x] later binding overrides earlier binding deterministically.
  - [x] MUST reject any closure / anonymous-function based definition, factory, configurator, lazy factory, argument, parameter, tag metadata, or compiled graph value deterministically before artifact write.
  - [x] rejection MUST surface:
    - [x] `ContainerCompileFailedException`;
    - [x] code: `CORETSIA_CONTAINER_COMPILE_FAILED`;
    - [x] message: `container-compile-failed`.
  - [x] diagnostics MUST NOT include:
    - [x] closure dumps;
    - [x] source code snippets;
    - [x] absolute paths;
    - [x] raw config values;
    - [x] raw env values;
    - [x] raw payload dumps;
    - [x] OS error messages.
  - [x] MUST NOT read source config files.
  - [x] MUST NOT read generated artifacts.
  - [x] MUST NOT write artifacts.
  - [x] MUST NOT resolve `BootstrapConfig`.
  - [x] MUST NOT resolve `ModulePlan`.
  - [x] MUST NOT run provider-based runtime boot.
  - [x] MUST NOT instantiate runtime services while compiling the graph.
  - [x] MUST NOT emit stdout or stderr.
  - [x] owns observability for container compile operation:
    - [x] span: `kernel.container_compile`;
    - [x] metric: `kernel.container_compile_total` with labels: `outcome`;
    - [x] metric: `kernel.container_compile_duration_ms` with labels: `outcome`;
  - [x] outcome label values MUST be bounded tokens only:
    - [x] `success`;
    - [x] `failure`;
  - [x] MUST receive observability dependencies through public observability ports/interfaces plus Foundation `Stopwatch`.
  - [x] MUST NOT instantiate Noop observability implementations directly.
  - [x] logger/meter/tracer failures MUST be caught and MUST NOT change compile behavior.

- [x] `framework/packages/core/kernel/src/Container/CompiledContainerFactory.php` — builds runtime Container from artifact
  - [x] MUST build the runtime Foundation container from REAL `container@1` artifact data.
  - [x] MUST receive an already-read and already-validated `config@1` payload from the caller.
  - [x] MUST use artifact-owned runtime config input (`config@1`) and MUST NOT read source config files.
  - [x] MUST hard-fail deterministically when `container.php` is missing.
  - [x] MUST surface `ContainerArtifactMissingException` with code `CORETSIA_CONTAINER_ARTIFACT_MISSING` for missing artifact.
  - [x] MUST surface `ContainerArtifactInvalidException` with code `CORETSIA_CONTAINER_ARTIFACT_INVALID` for invalid, unreadable, schema-invalid, legacy-stub, or non-compiled `container@1` artifacts.
  - [x] MUST NOT silently fall back to provider-based container construction.
  - [x] MUST NOT accept the earlier `1.330.0` stub payload as a production runtime container artifact.
  - [x] MUST NOT run source config discovery.
  - [x] MUST NOT run module discovery.
  - [x] MUST NOT register runtime providers as a fallback.
  - [x] MUST NOT compile a new container during runtime boot.
  - [x] MUST NOT calculate fingerprints.
  - [x] MUST NOT write artifacts.
  - [x] MUST NOT mutate existing artifacts.
  - [x] MUST NOT emit stdout or stderr.
  - [x] MUST construct runtime definitions only from deterministic compiled graph entries.
  - [x] MUST preserve the runtime config snapshot from `config@1` when constructing the Foundation container.
  - [x] MUST preserve compiled aliases, parameters, service definitions, and tags according to `docs/ssot/compiled-container.md`.
  - [x] diagnostics MUST NOT include:
    - [x] absolute paths;
    - [x] raw artifact payloads;
    - [x] raw config values;
    - [x] raw env values;
    - [x] source snippets;
    - [x] closure dumps;
    - [x] PHP warning text;
    - [x] OS error messages.

- [x] `framework/packages/core/kernel/src/Artifacts/Builders/CompiledContainerBuilder.php` — builds REAL `container@1` artifact envelope with standard header
  - [x] MUST receive deterministic compiled container data from `ContainerCompiler`.
  - [x] MUST reuse the canonical kernel artifact envelope introduced in 1.330.0:
    - [x] top-level shape: `{ "_meta": <header>, "payload": <compiled-container-payload> }`.
  - [x] MUST NOT introduce a container-specific top-level artifact shape.
  - [x] MUST build the REAL `container@1` payload schema defined by `docs/ssot/compiled-container.md`.
  - [x] MUST replace the earlier stub payload semantics with REAL compiled-container semantics.
  - [x] MUST set:
    - [x] `kind = compiled`;
    - [x] `compiled = true`.
  - [x] MUST use `ArtifactEnvelopeFactory` for envelope construction.
  - [x] MUST NOT assemble artifact envelopes manually.
  - [x] MUST NOT calculate fingerprints.
  - [x] MUST NOT compile the container graph.
  - [x] MUST NOT read files.
  - [x] MUST NOT write files.
  - [x] MUST NOT validate existing artifacts.
  - [x] MUST NOT emit stdout or stderr.
  - [x] MUST reject payload data that is not deterministic json-like schema data.
  - [x] MUST NOT include timestamps, absolute paths, hostnames, user names, process ids, raw env values, raw config values, closure dumps, or source snippets in payload/header data.

Definition shapes:
- [x] `framework/packages/core/kernel/src/Container/Definition/ServiceDefinition.php`
  - [x] Represents one deterministic compiled service definition.
  - [x] MUST be a kernel container compilation model, not a public DTO by default.
  - [x] MUST expose only deterministic schema data suitable for REAL `container@1` payload emission.
  - [x] MUST have a stable service id.
  - [x] MUST represent runtime construction through deterministic class/factory references, service references, parameter references, and scalar/list/map arguments.
  - [x] MUST NOT store `Closure`, anonymous function, callable object, raw PHP callable array, object instance, resource, reflection object, source snippet, or absolute path.
  - [x] MUST NOT instantiate the represented service.
  - [x] MUST normalize nested map keys deterministically where it owns map-like data.
  - [x] MUST preserve list order where list order is semantic.
  - [x] MUST reject invalid definition values with deterministic compile failure semantics.
  - [x] MUST NOT include raw config values, raw env values, secrets, closure dumps, source snippets, or OS-specific metadata.

- [x] `framework/packages/core/kernel/src/Container/Definition/ParameterBag.php`
  - [x] MAY be an in-memory compilation model and/or part of the REAL `container@1` payload schema as defined by `docs/ssot/compiled-container.md`.
  - [x] Does not require `container@2` by itself.
  - [x] MUST NOT be treated as a public DTO marker class by default.
  - [x] MUST represent deterministic parameter data only.
  - [x] MUST use stable parameter names.
  - [x] MUST normalize map keys deterministically.
  - [x] MUST preserve list order where list order is semantic.
  - [x] MUST reject `Closure`, anonymous function, callable object, object instance, resource, reflection object, source snippet, or absolute path.
  - [x] MUST NOT duplicate the full compiled config payload from `config@1`.
  - [x] MUST NOT embed raw secrets unless those values are already part of the canonical compiled config artifact semantics.
  - [x] MUST be safe for deterministic artifact serialization.

- [x] `framework/packages/core/kernel/src/Container/Definition/DefinitionGraph.php`
  - [x] Represents the complete deterministic compiled container graph.
  - [x] MUST contain only deterministic schema values.
  - [x] MUST contain service definitions, aliases, parameters, and tags according to `docs/ssot/compiled-container.md`.
  - [x] MUST sort service ids by byte-order `strcmp` for emitted graph order.
  - [x] MUST sort alias and parameter maps by byte-order `strcmp`.
  - [x] MUST preserve canonical Foundation tag discovery order:
    - [x] `priority DESC, id ASC`.
  - [x] MUST preserve canonical Foundation tag dedupe:
    - [x] first-wins per `(tag, serviceId)`.
  - [x] MUST preserve later-binding-overrides-earlier semantics.
  - [x] MUST NOT contain `Closure`, anonymous function, callable object, raw PHP callable array, object instance, resource, reflection object, source snippet, or absolute path.
  - [x] MUST be exportable to deterministic json-like payload data.
  - [x] MUST NOT perform runtime service resolution.
  - [x] MUST NOT read files, write files, calculate fingerprints, or emit output.

DTO policy boundary:
- [x] `ServiceDefinition`, `ParameterBag`, and `DefinitionGraph` are kernel container compilation models/shapes.
- [x] They are NOT DTO-marker classes by default.
- [x] DTO gates apply only to explicitly marked DTO transport classes.
- [x] Their presence in the compiled-container implementation MUST NOT imply a public package API commitment.
- [x] Their serialized form, if any, is owned by `docs/ssot/compiled-container.md`, not by DTO-marker gates.

Errors:
- [x] `framework/packages/core/kernel/src/Container/Exception/ContainerCompileFailedException.php` — `CORETSIA_CONTAINER_COMPILE_FAILED`
  - [x] MUST use fixed public message token `container-compile-failed`.
  - [x] MUST expose deterministic error code `CORETSIA_CONTAINER_COMPILE_FAILED`.
  - [x] MUST provide bounded reason tokens only.
  - [x] MUST NOT include closure dumps, source snippets, absolute paths, raw config values, raw env values, raw payloads, OS error messages, stack traces, or previous throwable messages in public diagnostics.

- [x] `framework/packages/core/kernel/src/Container/Exception/ContainerArtifactMissingException.php` — `CORETSIA_CONTAINER_ARTIFACT_MISSING`
  - [x] MUST use fixed public message token `container-artifact-missing`.
  - [x] MUST expose deterministic error code `CORETSIA_CONTAINER_ARTIFACT_MISSING`.
  - [x] MUST NOT include the missing filesystem path.
  - [x] MUST NOT include absolute paths, configured path strings, OS error messages, stack traces, or previous throwable messages in public diagnostics.

- [x] `framework/packages/core/kernel/src/Container/Exception/ContainerArtifactInvalidException.php` — `CORETSIA_CONTAINER_ARTIFACT_INVALID`
  - [x] MUST use fixed public message token `container-artifact-invalid`.
  - [x] MUST expose deterministic error code `CORETSIA_CONTAINER_ARTIFACT_INVALID`.
  - [x] MUST cover invalid, unreadable, schema-invalid, legacy-stub, or non-compiled `container@1` artifacts.
  - [x] MUST NOT include absolute paths, raw artifact payloads, raw config values, raw env values, PHP warning text, closure dumps, source snippets, OS error messages, stack traces, or previous throwable messages in public diagnostics.

Docs:
- [x] `docs/adr/ADR-0029-kernel-container-compile-artifact.md`
  - [x] MUST record the decision to keep `container@1` and not introduce `container@2` in this epic.
  - [x] MUST explain that `1.330.0` stub payload was transitional.
  - [x] MUST explain that `1.340.0` defines the first REAL `container@1` payload schema.
  - [x] MUST record descriptor-based, closure-free compile input as the selected design.
  - [x] MUST record artifact-only production runtime boot as the selected boot policy.
  - [x] MUST record that provider-based container construction remains allowed only for compile-time artifact production, tests, or explicitly documented non-production paths outside this epic.
  - [x] MUST record that runtime boot uses `container@1` plus already-read/validated `config@1` payload.
  - [x] MUST record deterministic missing/invalid artifact failure codes:
    - [x] `CORETSIA_CONTAINER_ARTIFACT_MISSING`;
    - [x] `CORETSIA_CONTAINER_ARTIFACT_INVALID`.

- [x] `docs/ssot/compiled-container.md` - compiled-container-specific payload + boot semantics
  - [x] Service definition lifecycle subsection
  - [x] Alias lifecycle rule for compiled aliases
  - [x] MUST NOT redefine the global artifact envelope, header fields, or artifact registry rows from `docs/ssot/artifacts.md`.
  - [x] Owns only compiled-container payload shape, compile rules, and artifact-only boot policy.
  - [x] MUST define the first REAL `container@1` payload schema.
  - [x] MUST define allowed top-level payload fields.
  - [x] MUST define service definition schema.
  - [x] MUST define parameter bag schema if `parameters` are part of the payload.
  - [x] MUST define alias schema.
  - [x] MUST define tag schema and preserve Foundation tag ordering/dedupe semantics.
  - [x] MUST define closure/callable rejection semantics.
  - [x] MUST define artifact-only runtime boot inputs:
    - [x] `container@1`;
    - [x] already-read/validated `config@1` payload.
  - [x] MUST define missing artifact and invalid artifact failure semantics.
  - [x] MUST define legacy `1.330.0` stub payload as unsupported for production runtime boot.
  - [x] MUST cross-reference:
    - [x] `docs/ssot/artifacts.md`;
    - [x] `docs/ssot/artifacts-and-fingerprint.md`;
    - [x] `docs/ssot/cache-verify.md`;
    - [x] `docs/ssot/observability.md`.

#### Modifies

- [x] `docs/ssot/INDEX.md` — register:
  - [x] `docs/ssot/compiled-container.md`
- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0029-kernel-container-compile-artifact.md`

- [x] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php`
  - [x] production/runtime container creation MUST use `CompiledContainerFactory`
  - [x] missing compiled artifact MUST fail with `ContainerArtifactMissingException` (`CORETSIA_CONTAINER_ARTIFACT_MISSING`)
  - [x] runtime MUST NOT silently fall back to a non-artifact container in production mode
  - [x] Artifact-only boot clarification (single-choice; cemented):
    - [x] after this epic, all Phase 1 runtime boot paths covered by Kernel/AppBuilder/smoke tests MUST use compiled-artifact boot
    - [x] missing compiled artifact MUST hard-fail deterministically with `CORETSIA_CONTAINER_ARTIFACT_MISSING`
    - [x] no implicit non-artifact fallback exists in the runtime boot paths covered by this epic
    - [x] any future developer-mode fallback requires a separate epic/ADR and MUST NOT be implied here

- [x] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php` registers:
  - [x] `ContainerCompiler`
  - [x] `CompiledContainerFactory`
  - [x] `CompiledContainerBuilder`

- [x] `framework/packages/core/kernel/src/Artifacts/Compiler/ArtifactCompiler.php`
  - [x] MUST replace `StubContainerBuilder` usage with REAL `CompiledContainerBuilder`.
  - [x] MUST build the compiled container payload through `ContainerCompiler`.
  - [x] MUST NOT write the old stub container payload after this epic.
  - [x] MUST keep artifact set unchanged:
    - [x] `module-manifest.php`
    - [x] `config.php`
    - [x] `container.php`
  - [x] MUST keep `routes.php` out of `core/kernel`.

- [x] `framework/packages/core/kernel/src/Artifacts/Verifier/CacheVerifier.php`
  - [x] MUST build expected REAL `container.php` envelope in memory.
  - [x] MUST use the same `ContainerCompiler` / `CompiledContainerBuilder` semantics as artifact production.
  - [x] MUST NOT compare existing REAL container artifacts against the old stub payload.
  - [x] MUST keep verification read-only and MUST NOT write or repair artifacts.

- [x] `framework/packages/core/kernel/src/Artifacts/Verifier/ArtifactSchemaValidator.php`
  - [x] MUST validate REAL `container@1` compiled payload schema.
    - [x] MUST validate canonical map key order for container payload maps.
    - [x] MUST validate canonical tag discovery order: priority DESC, id ASC.
  - [x] MUST validate by scalar/array schema only.
  - [x] MUST validate type-specific compiled service construction schemas:
    - [x] class construction: exact keys class
    - [x] factory construction: exact key factory
    - [x] factory class-method: exact keys class, kind, method
    - [x] factory service-method: exact keys kind, method, service
    - [x] reject legacy flat factory construction keys
    - [x] reject unknown service type
    - [x] reject invalid factory kind / method name / class reference / service id shape
  - [x] MUST NOT rely on PHP object identity or runtime class checks.
  - [x] MUST reject closure/callable-like graph values if present in the artifact payload.
  - [x] MUST NOT accept non-REAL `container@1` payloads, including transitional stub-shaped payloads.
  - [x] MUST keep canonical envelope validation unchanged:
    - [x] top-level keys exactly `_meta`, `payload`.

- [x] `docs/ssot/observability.md`
  - [x] register spans and metrics;
  - [x] keep labels limited to `outcome`;

- [x] `framework/packages/core/kernel/src/Artifacts/Builders/StubContainerBuilder.php`
  - [x] Delete `StubContainerBuilder` as production code.
  - [x] Remove `StubContainerBuilder` DI registration.
  - [x] Remove `KernelServiceFactory::stubContainerBuilder()`.
  - [x] Rewrite tests that depend on `StubContainerBuilder` to use:
    - [x] CompiledContainerBuilder for valid REAL `container@1` artifacts;
    - [x] inline invalid stub envelope fixtures for legacy-stub rejection tests.

#### Artifacts / outputs (if applicable)

- [x] Writes:
  - [x] `skeleton/var/cache/<appTarget>/container.php` — REAL `container@1` compiled container artifact, same path and artifact identity as the earlier stub placeholder, but with REAL payload schema defined by `docs/ssot/compiled-container.md`.
  - [x] The emitted artifact MUST use deterministic bytes, canonical header fields, the canonical `schemaVersion` for `container@1`, and the global artifact envelope.
- [x] Reads:
  - [x] validates header + payload schema for same file
  - [x] validation MUST assert the same canonical top-level envelope `{ "_meta", "payload" }` used by other kernel-owned artifacts.

- [x] This epic reuses existing Kernel artifact path policy from `kernel.artifacts.cache_dir`.
- [x] This epic does not introduce container-specific artifact path config.
- [x] Fingerprint exclusion policy remains owned by the existing artifact/fingerprint pipeline.
- [x] `ContainerCompiler`, `CompiledContainerBuilder`, and `CompiledContainerFactory` MUST NOT read `kernel.fingerprint.*` configuration directly.

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- N/A — all services discovered through the effective Foundation reset discovery tag implementing `ResetInterface` remains Foundation/gate-owned.

#### Observability (policy-compliant)

- [x] Spans:
  - [x] `kernel.container_compile`
- [x] Metrics:
  - [x] `kernel.container_compile_total` (labels: `outcome`)
  - [x] `kernel.container_compile_duration_ms` (labels: `outcome`)
- [x] Metric labels:
  - [x] only `outcome`
  - [x] allowed values:
    - [x] `success`
    - [x] `failure`
- [x] Logs:
  - [x] compile summary only
  - [x] no secrets, raw config dumps, raw env values, closure dumps, source snippets, absolute paths, fingerprints, or OS error messages
- [x] Observability failures MUST NOT change compile behavior.

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] raw secrets;
  - [x] raw env values;
  - [x] raw config values not already owned by `config@1` artifact semantics;
  - [x] absolute paths;
  - [x] source snippets;
  - [x] closure dumps;
  - [x] OS error messages;
  - [x] host-specific bytes.
- [x] Callable-like runtime behavior MUST be represented by deterministic references, never by serialized closures/callables.
- [x] Secret-bearing runtime configuration MUST come from the canonical compiled config artifact semantics, not from duplicated ad-hoc `container@1` payload fields.

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] Deterministic container bytes:
  - [x] `framework/packages/core/kernel/tests/Contract/CompiledContainerIsDeterministicTest.php`
- [x] Header shape:
  - [x] `framework/packages/core/kernel/tests/Contract/ContainerArtifactHeaderShapeContractTest.php`
- [x] Runtime factory from artifact:
  - [x] `framework/packages/core/kernel/tests/Integration/CompiledContainerFactoryBuildsContainerFromArtifactTest.php`
- [x] Missing artifact hard-fail:
  - [x] `framework/packages/core/kernel/tests/Integration/ArtifactOnlyBootFailsDeterministicallyWhenContainerArtifactMissingTest.php`
- [x] Invalid artifact hard-fail:
  - [x] `framework/packages/core/kernel/tests/Integration/ArtifactOnlyBootFailsDeterministicallyWhenContainerArtifactInvalidTest.php`
- [x] Closure-definition rejection:
  - [x] `framework/packages/core/kernel/tests/Integration/CompiledContainerRejectsClosureDefinitionsDeterministicallyTest.php`
- [x] Foundation semantic parity:
  - [x] `framework/packages/core/kernel/tests/Integration/CompiledContainerPreservesLaterBindingOverridesTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/CompiledContainerPreservesTagDedupeFirstWinsTest.php`

### Tests (MUST)

- Contract:
  - [x] `framework/packages/core/kernel/tests/Contract/ContainerArtifactHeaderShapeContractTest.php`
  - [x] `framework/packages/core/kernel/tests/Contract/CompiledContainerIsDeterministicTest.php`
- Integration:
  - [x] `framework/packages/core/kernel/tests/Integration/CompiledContainerFactoryBuildsContainerFromArtifactTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ArtifactOnlyBootResolvesResetOrchestratorTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ArtifactOnlyBootKernelRuntimeTriggersResetOncePerUowTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/ArtifactOnlyBootFailsDeterministicallyWhenContainerArtifactMissingTest.php`
    - [x] asserts code `CORETSIA_CONTAINER_ARTIFACT_MISSING`
    - [x] asserts no silent fallback to non-artifact container in production mode
    - [x] asserts no absolute path leak in the surfaced failure
  - [x] `framework/packages/core/kernel/tests/Integration/ArtifactOnlyBootFailsDeterministicallyWhenContainerArtifactInvalidTest.php`
    - [x] asserts code `CORETSIA_CONTAINER_ARTIFACT_INVALID`
    - [x] asserts legacy `1.330.0` stub payload is rejected for production runtime boot
    - [x] asserts no absolute path leak in the surfaced failure
    - [x] asserts no raw payload, closure dump, source snippet, or PHP warning text leaks
  - [x] `framework/packages/core/kernel/tests/Integration/CompiledContainerRejectsClosureDefinitionsDeterministicallyTest.php`
    - [x] asserts code `CORETSIA_CONTAINER_COMPILE_FAILED`
    - [x] asserts fixed message token `container-compile-failed`
    - [x] asserts no absolute paths, closure dumps, source snippets, or raw config values leak into the surfaced failure
  - [x] `framework/packages/core/kernel/tests/Integration/CompiledContainerPreservesLaterBindingOverridesTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/CompiledContainerPreservesTagDedupeFirstWinsTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/CompiledContainerFactoryPreservesNonSharedServiceDefinitionsTest.php`
    - [x] container artifact service shared=false
    - [x] container->get(id) twice returns different objects
  - [x] `framework/packages/core/kernel/tests/Integration/CompiledContainerFactoryAliasDoesNotMakeNonSharedTargetSharedTest.php`
    - [x] target shared=false
    - [x] alias points to target
    - [x] container->get(alias) twice returns different objects
- Foundation Integration:
  - [x] `framework/packages/core/foundation/tests/Integration/Container/ContainerFactoryDefinitionsCanBeNonSharedTest.php`
    - [x] factory shared=false returns different objects across get()
    - [x] factory shared=true returns same object across get()

### DoD (MUST)

- [x] REAL `container.php` produced in Phase 1 pipeline
- [x] identical compiled-container inputs produce identical `container.php` bytes.
- [x] deterministic hard-fail when artifact missing (production policy)
- [x] docs complete
- [x] `docs/ssot/compiled-container.md` defines the first REAL `container@1` payload schema.
- [x] `container@2` is NOT introduced by this epic.
- [x] The earlier `1.330.0` stub payload is documented as a transitional placeholder, not as the supported runtime compiled-container format.
- [x] Phase 1 artifact-based runtime boot paths hard-fail without `container.php` with deterministic code:
  - [x] `CORETSIA_CONTAINER_ARTIFACT_MISSING`
- [x] Phase 1 artifact-based runtime boot paths hard-fail on invalid or legacy-stub `container.php` with deterministic code:
  - [x] `CORETSIA_CONTAINER_ARTIFACT_INVALID`
- [x] No implicit non-artifact fallback exists in the production runtime boot paths covered by this epic.
- [x] Provider-based container construction remains allowed only for compile-time artifact production, tests, or explicitly documented non-production paths outside this epic.
- [x] When the compiled-container input is compiled twice without changes, deterministic `container.php` bytes are identical.
  - [x] Locked by `CompiledContainerIsDeterministicTest.php`.
  - [x] This epic does not own the `cache:verify` CLI command.
- [x] Artifact-only runtime boot uses artifact-owned config input:
  - [x] `container@1` provides service definitions / aliases / parameters / tags;
  - [x] `config@1` provides the runtime config snapshot;
  - [x] runtime boot MUST NOT read source config files.
- [x] Reset discipline in artifact-only boot (MUST)
  - [x] effective reset tag resolution remains Foundation-owned and MUST NOT be duplicated in compiler/artifact code
  - [x] When REAL container artifact becomes active (this epic scope), the compiled container MUST include Foundation reset infrastructure so runtime can trigger reset:
    - [x] `Coretsia\Foundation\Runtime\Reset\ResetOrchestrator` must be resolvable from the container
    - [x] `Coretsia\Foundation\Tag\TagRegistry` must be resolvable (as ResetOrchestrator dependency)
  - [x] Kernel MUST still not enumerate `kernel.reset`.
    - [x] KernelRuntime triggers reset ONLY by calling `ResetOrchestrator::resetAll()` obtained via DI/container.
  - [x] No semantic duplication:
    - [x] Container compiler/artifact builder MUST NOT re-implement reset ordering/meta parsing.
    - [x] reset ordering, reset tag ownership, and reset metadata semantics remain in `core/foundation` (1.200.0 / 1.250.0).
- [x] Add/extend an integration test in `core/kernel` (or wherever your artifact boot tests live) asserting:
  - [x] artifact-only boot can resolve `ResetOrchestrator`
  - [x] KernelRuntime can execute a UoW and triggers reset once per UoW
- [x] Closure-definition rejection is enforced by `ContainerCompiler` + integration test evidence.
- [x] No standalone `container_no_closure_definitions_gate.php` exists.
  - [x] This is verified together with positive compiler-level closure rejection test evidence.
- [x] Any future static/payload policy gate for compiled containers must be introduced as `compiled_container_policy_gate.php` by a separate tooling epic and must inspect compiled graph/artifact semantics, not arbitrary PHP closure syntax.

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

adr: "docs/adr/ADR-0027-runtime-driver-guard.md"

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

- Required existing docs:
  - `docs/ssot/runtime-drivers.md` — canonical runtime driver ids, config keys, compatibility matrix, and decision rules

- Required config roots/keys:
  - `kernel` — existing config root owned by `core/kernel`

- Forward-compatible optional inputs:
  - `worker.enabled` — optional future-owned key; missing key MUST be treated as disabled before 1.360.0
  - `worker.task_type` — optional future-owned key; missing key MUST activate no worker-derived driver before 1.360.0

- Required contracts / ports:
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface` — guard reads selected config values through this port only

- Required kernel types/services:
  - `Coretsia\Kernel\Module\ModulePlan` — caller-provided input for module compatibility checks

- Caller-owned integration boundary:
  - `Coretsia\Kernel\Module\ModulePlanResolver` may be used by callers before invoking the guard, but `RuntimeDriverGuard` MUST NOT resolve module plans internally.

- Terminology note (MUST): config root vs config key namespaces
  - Config root for Kernel is **`kernel`**.
  - Dotted prefixes such as `kernel.runtime.*`, `kernel.modules.*`, `kernel.config.*`, `kernel.artifacts.*`, `kernel.fingerprint.*`, and `kernel.uow.*` are config key namespaces, not separate roots.
  - `config/<name>.php` MUST return subtree for `<name>` and MUST NOT wrap values in a repeated root key.

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- `core/foundation`

Forbidden:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- `Psr\Http\Server\*`

Tooling tests:

- N/A — tooling tests live under `framework/tools/tests/**`; no additional deptrac constraints beyond repo norms.

#### Uses ports (API surface, NOT deps) (optional)

- Contracts:
  - `Coretsia\Contracts\Config\ConfigRepositoryInterface`
- Kernel public API:
  - `Coretsia\Kernel\Module\ModulePlan`
- Foundation stable APIs:
  - N/A — `RuntimeDriverGuard` is pure and MUST NOT require container services such as `TagRegistry`.

### Entry points / integration points (MUST)

Kernel guard callers:

- CLI:
  - future `coretsia worker:start` owner `platform/worker` MUST call Kernel `RuntimeDriverGuard` before starting a long-running worker loop
- HTTP:
  - future runtime entrypoints such as `frankenphp.php`, `swoole.php`, and `roadrunner.php` MUST call Kernel `RuntimeDriverGuard` before runtime boot
- Guard inputs:
  - reads selected config values via `ConfigRepositoryInterface`
  - receives caller-resolved `ModulePlan` only for module compatibility checks
  - MUST NOT resolve `ModulePlan` internally
- Artifacts:
  - N/A — this epic does not introduce, read, or write Kernel artifacts.
- Notes:
  - this epic introduces the Kernel guard used by future platform/runtime entrypoints
  - it does not implement runtime adapters, worker pools, HTTP servers, PSR-7/15 bridges, or long-running loops

### Deliverables (MUST)

#### Creates

Drivers model:
- [x] `framework/packages/core/kernel/src/Runtime/Driver/HttpDriver.php` — canonical HTTP runtime driver enum
  - [x] MUST be a string-backed enum
  - [x] `CLASSIC = 'http.classic'`
  - [x] `FRANKENPHP = 'http.frankenphp'`
  - [x] `SWOOLE = 'http.swoole'`
  - [x] `ROADRUNNER = 'http.roadrunner'`
  - [x] `WORKER = 'http.worker'`
  - [x] MUST expose canonical ids only through enum values
  - [x] MUST NOT expose shortened aliases such as `classic`, `frankenphp`, `swoole`, `roadrunner`, or `worker` as runtime ids
  - [x] MAY expose `id(): string` as a thin alias for `$this->value`
  - [x] MUST NOT contain config-reading logic
  - [x] MUST NOT contain compatibility matrix logic

- [x] `framework/packages/core/kernel/src/Runtime/Driver/BackgroundDriver.php` — canonical background runtime driver enum
  - [x] MUST be a string-backed enum
  - [x] `WORKER_QUEUE = 'bg.worker_queue'`
  - [x] MUST expose canonical ids only through enum values
  - [x] MUST NOT expose shortened aliases such as `worker_queue` as runtime ids
  - [x] MAY expose `id(): string` as a thin alias for `$this->value`
  - [x] MUST NOT contain config-reading logic
  - [x] MUST NOT contain compatibility matrix logic

- [x] `framework/packages/core/kernel/src/Runtime/Driver/RuntimeDrivers.php` — immutable selected runtime drivers value object
  - [x] `httpDriver(): HttpDriver`
  - [x] `backgroundDrivers(): list<BackgroundDriver>`
  - [x] `driverIds(): list<string>` sorted by canonical id using `strcmp`
  - [x] `httpDriverId(): string`
  - [x] `backgroundDriverIds(): list<string>` sorted by canonical id using `strcmp`
  - [x] constructor/factory MUST reject duplicate background driver entries deterministically
  - [x] returned `backgroundDrivers()` and `backgroundDriverIds()` MUST never contain duplicates
  - [x] constructor/factory MUST accept only `HttpDriver` and `BackgroundDriver` instances
  - [x] MUST NOT accept raw string aliases as constructor input
  - [x] MUST NOT read config
  - [x] MUST NOT know module compatibility rules
  - [x] MUST NOT emit stdout/stderr

Guard:
- [x] `framework/packages/core/kernel/src/Runtime/Driver/RuntimeDriverGuard.php` — canonical runtime driver matrix guard
  - [x] MUST be stateless
  - [x] MUST be deterministic for the same `ConfigRepositoryInterface` values
  - [x] MUST read config only through:
    - [x] `ConfigRepositoryInterface::get(...)`
    - [x] optionally `ConfigRepositoryInterface::has(...)`
  - [x] MUST NOT call:
    - [x] `ConfigRepositoryInterface::all()`
    - [x] `ConfigRepositoryInterface::sourceOf(...)`
    - [x] `ConfigRepositoryInterface::explain()`
  - [x] MUST NOT dump config trees or source metadata
  - [x] MUST NOT read source config files
  - [x] MUST NOT read generated artifacts
  - [x] MUST NOT inspect environment variables, loaded PHP extensions, process names, CLI argv, ports, filesystem adapter presence, container services, or reflection
  - [x] `detect(ConfigRepositoryInterface $cfg): RuntimeDrivers`
    - [x] returns `RuntimeDrivers` only for a valid single-HTTP-driver selection
    - [x] MUST throw `RuntimeDriverConflictException` when multiple HTTP drivers are active
    - [x] MUST apply missing-key policy for `worker.*` exactly as defined by `docs/ssot/runtime-drivers.md`
  - [x] `assertCompatible(ConfigRepositoryInterface $cfg): void`
    - [x] MUST be config-only
    - [x] MUST NOT inspect `ModulePlan`
    - [x] MAY delegate to `detect(...)`
  - [x] `assertHttpDriverCompatibleWithModules(ConfigRepositoryInterface $cfg, ModulePlan $plan): void`
    - [x] MUST be the only method that validates `platform.http` / module-plan compatibility
    - [x] MUST first derive active drivers from config through the same deterministic selection logic as `detect(...)`
    - [x] MUST NOT resolve `ModulePlan` internally
  - [x] ModulePlan compatibility rule:
    - [x] MUST require `platform.http` for:
      - [x] `http.frankenphp`
      - [x] `http.swoole`
      - [x] `http.roadrunner`
      - [x] `http.worker`
    - [x] MUST NOT require `platform.http` for:
      - [x] `http.classic`
      - [x] `bg.worker_queue`
    - [x] MUST inspect only caller-provided `ModulePlan`
    - [x] MUST compare module ids by canonical string value
    - [x] MUST NOT inspect Composer metadata, providers, package paths, module manifests, generated artifacts, config source files, or container services
  - [x] when producing conflict / invalid-config diagnostics, guard MUST use canonical driver ids from `docs/ssot/runtime-drivers.md` exactly
  - [x] guard MUST NOT emit shortened aliases such as `classic`, `roadrunner`, `worker_queue`
  - [x] any diagnostics list of active/conflicting drivers MUST be sorted by canonical driver id using byte-order `strcmp`
  - [x] any diagnostics list of required moduleIds MUST also be sorted by `strcmp`
  - [x] MUST NOT emit stdout/stderr
  - [x] MUST NOT log directly; callers MAY log safe exception data

- [x] Guard input policy:
  - [x] RuntimeDriverGuard MUST read only these canonical config keys:
    - [x] `kernel.runtime.frankenphp.enabled`
    - [x] `kernel.runtime.swoole.enabled`
    - [x] `kernel.runtime.roadrunner.enabled`
    - [x] `worker.enabled`
    - [x] `worker.task_type`
  - [x] For `kernel.runtime.*.enabled`, guard MUST treat only strict boolean `true` as active
  - [x] For `worker.enabled`, missing key is treated as `false`
  - [x] For `worker.enabled`, only strict boolean `true` activates worker-derived drivers
  - [x] For `worker.task_type`, missing key activates no worker-derived driver
  - [x] If `worker.enabled === true` and `worker.task_type === 'http'`, activate `http.worker`
  - [x] If `worker.enabled === true` and `worker.task_type === 'queue'`, activate `bg.worker_queue`
  - [x] If `worker.enabled === true` and `worker.task_type` is present but not `http` or `queue`, fail with:
    - [x] `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`
    - [x] reason `worker_task_type_invalid`
  - [x] generic worker root shape / unknown-key validation remains owned by future `platform/worker` config rules
  - [x] core/kernel MUST NOT define `worker.*` defaults or rules in this epic

Errors:
- [x] `framework/packages/core/kernel/src/Runtime/Exception/RuntimeDriverConflictException.php`
  - [x] error code: `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`
  - [x] public message MUST be deterministic and safe:
    - [x] `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT: <reason>`
  - [x] MUST expose:
    - [x] `errorCode(): string`
    - [x] `reason(): string`
    - [x] `activeDriverIds(): list<string>`
    - [x] `conflictingDriverIds(): list<string>`
  - [x] MUST provide named constructors:
    - [x] `multipleHttpDrivers(list<string> $activeDriverIds, list<string> $conflictingDriverIds): self`
    - [x] `workerHttpConflictsWithHttpDriver(list<string> $activeDriverIds, list<string> $conflictingDriverIds): self`
  - [x] Conflict reasons:
    - [x] `multiple_http_drivers`
    - [x] `worker_http_conflicts_with_http_driver`
  - [x] driver id lists MUST be sorted by byte-order `strcmp`
  - [x] driver id lists MUST contain canonical ids only
  - [x] MUST NOT expose config paths, config values, env values, adapter internals, stack traces, previous throwable messages, or payload dumps

- [x] `framework/packages/core/kernel/src/Runtime/Exception/RuntimeDriverInvalidConfigException.php`
  - [x] error code: `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`
  - [x] public message MUST be deterministic and safe:
    - [x] `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG: <reason>`
  - [x] MUST expose:
    - [x] `errorCode(): string`
    - [x] `reason(): string`
    - [x] `activeDriverIds(): list<string>`
    - [x] `requiredModuleIds(): list<string>`
  - [x] MUST provide named constructors:
    - [x] `requiresPlatformHttpModule(list<string> $activeDriverIds): self`
    - [x] `workerTaskTypeInvalid(list<string> $activeDriverIds = []): self`
  - [x] Invalid-config reasons:
    - [x] `requires_platform_http_module`
    - [x] `worker_task_type_invalid`
  - [x] required module ids MUST be sorted by byte-order `strcmp`
  - [x] active driver ids MUST be sorted by byte-order `strcmp`
  - [x] config shape / unknown-key enforcement is owned by config rules validation, NOT by `RuntimeDriverGuard`
  - [x] `RuntimeDriverGuard` MUST remain matrix-focused and MUST NOT become a second source of truth for generic config validation
  - [x] MUST NOT expose ModulePlan dumps, config dumps, paths, env values, adapter internals, stack traces, previous throwable messages, or payload dumps

Docs:
- [x] `docs/adr/ADR-0027-runtime-driver-guard.md` — decision record for runtime driver guard, deterministic codes, public API boundary, and matrix ownership
  - [x] MUST record that `RuntimeDriverGuard` is public Kernel API
  - [x] MUST record that no new `core/contracts` runtime-driver port is introduced by this epic
  - [x] MUST record that `docs/ssot/runtime-drivers.md` remains the single canonical source for driver ids, config keys, and matrix rules
  - [x] MUST record deterministic error codes:
    - [x] `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`
    - [x] `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`

- [x] `docs/architecture/runtime-driver-guard.md` — architecture overview
  - [x] API surface + callers + deterministic error codes
  - [x] MUST NOT duplicate the canonical matrix/rules
  - [x] MUST state that the canonical source for compatibility matrix and decision rules is `docs/ssot/runtime-drivers.md`
  - [x] Any behavioral change MUST update:
    - [x] `docs/ssot/runtime-drivers.md`
    - [x] Kernel unit/integration locks
    - [x] E2E matrix fixtures/tests under `framework/tools/tests/Fixtures/RuntimeDriverMatrix/*`

Tooling fixtures:
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/ClassicHttpApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/RoadrunnerHttpApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/FrankenphpHttpApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/SwooleHttpApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/WorkerQueueApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/WorkerHttpApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/RoadrunnerPlusWorkerHttpApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/RoadrunnerPlusWorkerQueueApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/FrankenphpPlusWorkerHttpApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/FrankenphpPlusWorkerQueueApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/SwoolePlusWorkerHttpApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/SwoolePlusWorkerQueueApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/WorkerHttpWithoutPlatformHttpModuleApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/MultipleConfiguredHttpDriversApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/WorkerTaskTypeInvalidApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/RoadrunnerWithoutPlatformHttpModuleApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/FrankenphpWithoutPlatformHttpModuleApp/`
- [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/SwooleWithoutPlatformHttpModuleApp/`

Fixture app contract:
- [x] each `framework/tools/tests/Fixtures/RuntimeDriverMatrix/<App>/` MUST contain:
  - [x] `config.php` — deterministic merged-config input subset for driver detection
  - [x] `modules.php` — deterministic enabled module ids for ModulePlan construction
  - [x] `expected.php` — expected outcome/code/reason/active driver ids
- [x] `config.php` MUST return a plain array using canonical config keys only
- [x] `modules.php` MUST return a list of canonical module id strings
- [x] `expected.php` MUST return a plain array with stable keys:
  - [x] `outcome`
  - [x] `code`
  - [x] `reason`
  - [x] `activeDriverIds`
  - [x] `conflictingDriverIds`
  - [x] `requiredModuleIds`
  - [x] For allowed fixtures:
    - [x] `outcome` MUST be `allowed`
    - [x] `code` MUST be `null`
    - [x] `reason` MUST be `null`
    - [x] `conflictingDriverIds` MUST be `[]`
    - [x] `requiredModuleIds` MUST be `[]`
  - [x] For forbidden/conflict fixtures:
    - [x] `outcome` MUST be `conflict`
    - [x] `code` MUST be `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`
    - [x] `reason` MUST be a fixed conflict reason token
  - [x] For invalid-config fixtures:
    - [x] `outcome` MUST be `invalid_config`
    - [x] `code` MUST be `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`
    - [x] `reason` MUST be a fixed invalid-config reason token
- [x] fixture files MUST return plain arrays only
- [x] fixture files MUST NOT contain absolute paths, timestamps, random ids, env reads, closures, objects, resources, adapter classes, or real runtime server boot logic

Tooling support:
- [x] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixTestSupport.php`
  - [x] loads fixture arrays deterministically
  - [x] discovers fixture apps under `framework/tools/tests/Fixtures/RuntimeDriverMatrix/*` deterministically
  - [x] validates fixture shape before executing assertions
  - [x] instantiates the dedicated test `ConfigRepositoryInterface`
  - [x] builds a minimal `ModulePlan`
  - [x] invokes `RuntimeDriverGuard`
  - [x] asserts only deterministic outcomes / codes / reason tokens / canonical driver ids / canonical module ids
  - [x] normalizes paths if fixture paths are ever shown in assertion messages
  - [x] MUST NOT shell out
  - [x] MUST NOT read environment variables
  - [x] MUST NOT depend on real runtime adapters
  - [x] MUST NOT start long-running loops
  - [x] MUST NOT write artifacts

- [x] `framework/tools/tests/Integration/Runtime/Support/RuntimeDriverMatrixConfigRepository.php`
  - [x] implements test-only `ConfigRepositoryInterface`
  - [x] supports only `has(...)` and `get(...)`
  - [x] `all()` MUST throw deterministically
  - [x] `sourceOf(...)` MUST throw deterministically
  - [x] `explain()` MUST throw deterministically
  - [x] MUST NOT read environment variables
  - [x] MUST NOT read config source files
  - [x] MUST NOT expose source metadata
  - [x] MUST NOT depend on real runtime adapters
  - [x] MUST NOT write artifacts

#### Modifies

- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0027-runtime-driver-guard.md`

- [x] `framework/packages/core/kernel/src/Provider/KernelServiceProvider.php`
  - [x] registers `RuntimeDriverGuard`
  - [x] provider registration MUST NOT run guard detection
  - [x] provider registration MUST NOT inspect config values
  - [x] provider registration MUST NOT resolve `ModulePlan`
  - [x] provider registration MUST NOT emit stdout/stderr

- [x] `framework/packages/core/kernel/src/Provider/KernelServiceFactory.php`
  - [x] adds stateless factory/wiring helper for `RuntimeDriverGuard`
  - [x] MUST NOT keep mutable runtime state
  - [x] MUST NOT cache config, module plans, detected drivers, or guard results
  - [x] MUST NOT inspect config values while wiring the guard

- [x] `framework/packages/core/kernel/config/kernel.php`
- [x] `framework/packages/core/kernel/config/rules.php`

- [x] `framework/packages/core/kernel/PUBLIC_API.md` register runtime-driver guard public API symbols:
  - [x] `Coretsia\Kernel\Runtime\Driver\HttpDriver`
  - [x] `Coretsia\Kernel\Runtime\Driver\BackgroundDriver`
  - [x] `Coretsia\Kernel\Runtime\Driver\RuntimeDrivers`
  - [x] `Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard`
  - [x] `Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException`
  - [x] `Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException`

#### Package skeleton (if type=package)

- N/A — `core/kernel` package already exists; this epic extends the existing package and does not create a new package skeleton.

#### Configuration (keys + defaults)

- [x] Files:
  - [x] `framework/packages/core/kernel/config/kernel.php`
  - [x] `framework/packages/core/kernel/config/rules.php`
- [x] Keys (dot):
  - [x] `kernel.runtime.frankenphp.enabled` = `false`
  - [x] `kernel.runtime.swoole.enabled` = `false`
  - [x] `kernel.runtime.roadrunner.enabled` = `false`
- [x] Rules:
  - [x] `framework/packages/core/kernel/config/rules.php` enforces bool shape for `kernel.runtime.frankenphp.enabled`
  - [x] `framework/packages/core/kernel/config/rules.php` enforces bool shape for `kernel.runtime.swoole.enabled`
  - [x] `framework/packages/core/kernel/config/rules.php` enforces bool shape for `kernel.runtime.roadrunner.enabled`
  - [x] unknown `kernel.runtime.*` keys are rejected
  - [x] `worker.*` rules are not introduced by core/kernel

#### Wiring / DI tags (when applicable)

- Tags introduced:
  - N/A — this epic introduces no tags
- [x] ServiceProvider wiring evidence:
  - [x] registers: `Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard`
  - [x] adds no tags
- [x] Compliance delta:
  - [x] this package MUST NOT introduce owner constants for tags it does not own
  - [x] raw literal tag strings are not needed for this epic
  - [x] runtime code MUST NOT use `kernel.reset` tag

#### Artifacts / outputs (if applicable)

- Writes:
  - N/A — this epic writes no Kernel artifacts
- Reads:
  - N/A — this epic reads no Kernel artifacts

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- Context reads:
  - N/A
- Context writes:
  - N/A
- [x] Reset discipline:
  - [x] This epic introduces pure guard services and tooling tests only
  - [x] This epic does not introduce runtime loops, runtime entrypoint execution, UoW execution, or long-running runtime state
  - [x] Reset is N/A for this epic
  - [x] `RuntimeDriverGuard` MUST NOT call `TagRegistry`
  - [x] `RuntimeDriverGuard` MUST NOT enumerate `kernel.reset`
  - [x] `RuntimeDriverGuard` MUST NOT trigger reset
  - [x] Future runtime entrypoint/loop epics MUST delegate UoW lifecycle to `KernelRuntime`

#### Observability (policy-compliant)

- Spans:
  - N/A
- Metrics:
  - N/A
- [x] Logs:
  - [x] `RuntimeDriverGuard` MUST NOT log directly
  - [x] RuntimeDriverGuard exceptions MUST expose only safe deterministic data that callers MAY log:
    - [x] canonical driver ids
    - [x] module ids
    - [x] deterministic error code
    - [x] fixed reason token
  - [x] operation-level observability is deferred to caller/entrypoint epics

#### Errors

- [x] Exceptions introduced:
  - [x] `Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException` — errorCode `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`
  - [x] `Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException` — errorCode `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`

- Mapping:
  - N/A — this epic introduces deterministic Kernel exceptions only; platform/caller error-to-output mapping is owned by later caller epics

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] full config dumps
  - [x] config values
  - [x] env values
  - [x] file paths
  - [x] adapter internals
  - [x] stack traces
  - [x] previous throwable messages
  - [x] payload dumps
  - [x] stdout/stderr output
- [x] Allowed:
  - [x] canonical driver ids
  - [x] canonical module ids
  - [x] deterministic error code
  - [x] fixed reason token

### Phase 0 parity: deterministic guard semantics + safe failures (MUST)

RuntimeDriverGuard MUST follow Phase 0 rails safety policy:
- [x] deterministic ordering using byte-order `strcmp`
- [x] deterministic error codes
- [x] code-first failures
- [x] no secrets/PII
- [x] no full config dumps
- [x] no stdout/stderr output from kernel

#### Output-free invariant (MUST)

- [x] `core/kernel` runtime MUST NOT write to stdout/stderr:
  - [x] no `echo`
  - [x] no `print`
  - [x] no `var_dump`
  - [x] no `print_r`
  - [x] no `printf`
  - [x] no `error_log`
  - [x] no direct `STDOUT` / `STDERR` writes

#### Deterministic decision model (single-choice; cemented)

- [x] `detect()` MUST produce the same result for the same config
- [x] `detect()` MUST NOT probe environment/runtime state beyond declared config inputs
- [x] `assertCompatible()` MUST fail with the same code and reason for the same config combination
- [x] all active/conflicting driver id lists MUST be sorted by byte-order `strcmp`
- [x] all module id lists MUST be sorted by byte-order `strcmp`
- [x] ordering/diagnostics MUST use canonical SSoT driver ids exactly:
  - [x] `http.classic`
  - [x] `http.frankenphp`
  - [x] `http.swoole`
  - [x] `http.roadrunner`
  - [x] `http.worker`
  - [x] `bg.worker_queue`

## E2E matrix fixtures/tests: determinism rules (MUST)

- [x] Fixture apps under `framework/tools/tests/Fixtures/RuntimeDriverMatrix/*` MUST be cross-OS stable
- [x] tests MUST normalize paths if paths appear in assertion messages
- [x] tests MUST NOT compare `\` vs `/` literally
- [x] tests MUST NOT rely on locale/environment ordering
- [x] fixture cache dirs, if present, MUST be stable:
  - [x] no timestamps
  - [x] no random suffixes
- [x] matrix tests MUST assert deterministic error codes
- [x] matrix tests MUST NOT assert full user-facing messages
- [x] matrix tests MAY assert minimal reason tokens

### Verification (TEST EVIDENCE) (MUST when applicable)

#### Required policy tests matrix

- If Context writes exist:
  - N/A — this epic writes no context
- If `kernel.reset` used:
  - N/A — this epic must not use `kernel.reset`
- If metrics/spans/logs exist:
  - N/A — guard does not emit observability directly
- [x] If redaction exists:
  - [x] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardConflictDiagnosticsAreDeterministicallySortedTest.php`
    - [x] asserts diagnostics use only canonical ids from `docs/ssot/runtime-drivers.md`
    - [x] forbids shortened aliases such as `classic`, `roadrunner`, `worker_queue`
    - [x] asserts active/conflicting drivers are sorted by canonical id using byte-order `strcmp`
  - [x] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardRejectsWorkerTaskTypeInvalidTest.php`
    - [x] asserts invalid worker task type does not leak raw config dumps or env values

#### Test harness / fixtures

- [x] Fixture apps:
  - [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/ClassicHttpApp/config.php`
  - [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/ClassicHttpApp/modules.php`
  - [x] `framework/tools/tests/Fixtures/RuntimeDriverMatrix/ClassicHttpApp/expected.php`
  - [x] same structure for:
    - [x] `RoadrunnerHttpApp`
    - [x] `FrankenphpHttpApp`
    - [x] `SwooleHttpApp`
    - [x] `WorkerQueueApp`
    - [x] `WorkerHttpApp`
    - [x] `RoadrunnerPlusWorkerHttpApp`
    - [x] `RoadrunnerPlusWorkerQueueApp`
    - [x] `FrankenphpPlusWorkerHttpApp`
    - [x] `FrankenphpPlusWorkerQueueApp`
    - [x] `SwoolePlusWorkerHttpApp`
    - [x] `SwoolePlusWorkerQueueApp`
    - [x] `WorkerHttpWithoutPlatformHttpModuleApp`
    - [x] `MultipleConfiguredHttpDriversApp`
    - [x] `WorkerTaskTypeInvalidApp`
    - [x] `RoadrunnerWithoutPlatformHttpModuleApp`
    - [x] `FrankenphpWithoutPlatformHttpModuleApp`
    - [x] `SwooleWithoutPlatformHttpModuleApp`
- Fake adapters:
  - N/A — matrix tests must not depend on real adapters or fake server adapters

### Tests (MUST)

Kernel:
- Contract:
  - [x] `framework/packages/core/kernel/tests/Contract/KernelRuntimeDriverConfigDefaultsContractTest.php`
    - [x] asserts all three `kernel.runtime.*.enabled` defaults are false
  - [x] `framework/packages/core/kernel/tests/Contract/KernelRuntimeDriverConfigRulesContractTest.php`
    - [x] asserts non-bool values are rejected by config validation
    - [x] asserts unknown `kernel.runtime.*` keys are rejected
    - [x] asserts `worker.*` is not introduced into kernel config defaults/rules
  - [x] `framework/packages/core/kernel/tests/Contract/KernelRuntimeDriverPublicApiContractTest.php`
    - [x] asserts runtime-driver public API symbols are listed in `PUBLIC_API.md`
  - [x] `framework/packages/core/kernel/tests/Contract/KernelRuntimeDriverNoForbiddenDepsContractTest.php`
    - [x] asserts kernel runtime-driver source does not import `platform/*`
    - [x] asserts kernel runtime-driver source does not import PSR-7/15 namespaces
    - [x] asserts kernel runtime-driver source does not import observability ports or `Psr\Log\LoggerInterface`
- Unit:
  - [x] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardDetectsClassicWhenNoAdaptersEnabledTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardTreatsMissingWorkerKeysAsDisabledTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardDetectsRoadrunnerWhenEnabledTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardAllowsRoadrunnerPlusWorkerQueueTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardAllowsSwoolePlusWorkerQueueTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardAllowsFrankenphpPlusWorkerQueueTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardRejectsMultipleHttpDriversTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardRejectsWorkerHttpWithRoadrunnerTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardRejectsWorkerTaskTypeInvalidTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardConflictDiagnosticsAreDeterministicallySortedTest.php`
  - [x] `framework/packages/core/kernel/tests/Unit/RuntimeDriverGuardRejectsWorkerHttpWithAnyConfiguredHttpDriverTest.php`
- Integration:
  - [x] `framework/packages/core/kernel/tests/Integration/RuntimeDriverGuardChecksModulePlanForPlatformHttpTest.php`
    - [x] asserts exact code `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`
    - [x] asserts non-classic HTTP drivers require `platform.http`
    - [x] asserts classic HTTP and `bg.worker_queue` do not require `platform.http`
- Gates/Arch:
  - [x] existing arch/deptrac rails prove no forbidden deps
  - [x] existing output-free gates prove no stdout/stderr from kernel runtime code

Tooling:
- Integration:
  - [x] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixTestSupport.php`
  - [x] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixDefaultClassicIsAllowedTest.php`
  - [x] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllowsClassicPlusWorkerQueueTest.php`
  - [x] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllowsRoadrunnerPlusWorkerQueueTest.php`
  - [x] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsRoadrunnerPlusWorkerHttpTest.php`
  - [x] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllowsFrankenphpPlusWorkerQueueTest.php`
  - [x] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsFrankenphpPlusWorkerHttpTest.php`
  - [x] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllowsSwoolePlusWorkerQueueTest.php`
  - [x] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsSwoolePlusWorkerHttpTest.php`
  - [x] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixRejectsWorkerHttpWithoutPlatformHttpModuleTest.php`
  - [x] `framework/tools/tests/Integration/Runtime/RuntimeDriverMatrixAllFixturesMatchGuardTest.php`
    - [x] asserts the fixture corpus is explicit
    - [x] asserts all runtime-driver matrix fixtures are read
    - [x] asserts all fixture apps match `RuntimeDriverGuard`
    - [x] prevents unused/stale runtime-driver matrix fixtures

### DoD (MUST)

- [x] Deliverables complete:
  - [x] all source files in `Creates` exist
  - [x] all docs in `Creates` exist
  - [x] all modified files in `Modifies` updated
  - [x] all fixture files exist with required shape
- [x] Preconditions satisfied:
  - [x] no unresolved forward references except explicitly future-owned `worker.*` optional keys
  - [x] `RuntimeDriverGuard` does not require `platform/worker`, runtime adapters, or PSR-7/15
- [x] Kernel guard:
  - [x] guard exists and is wired in `core/kernel`
  - [x] guard is public Kernel API and listed in `PUBLIC_API.md`
  - [x] deterministic conflict detection tests green
  - [x] rules match `docs/ssot/runtime-drivers.md`
  - [x] no forbidden deps
  - [x] no platform/runtime adapter owns a competing matrix rule in this epic
- [x] Tooling matrix locks:
  - [x] matrix tests cover allowed + forbidden combos
  - [x] failing conflict combos assert:
    - [x] `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`
  - [x] failing invalid-config combos assert:
    - [x] `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`
  - [x] tests prove guard is invoked and blocks conflicting combinations deterministically
  - [x] tests prove default classic HTTP does not block `bg.worker_queue`
  - [x] tests run against fixture apps with different config/modules enablement
  - [x] tests green in CI
- [x] Non-goals / out of scope:
  - [x] does not implement runtime adapters
  - [x] does not implement worker pool
  - [x] does not introduce PSR-7/15 into kernel
  - [x] does not test performance
  - [x] does not start long-running loops
  - [x] does not write/read Kernel artifacts
- [x] Concrete lock example:
  - [x] when `kernel.runtime.roadrunner.enabled=true` and `worker.enabled=true && worker.task_type=http`, then:
    - [x] `RuntimeDriverGuard` fails with `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`
    - [x] reason is `worker_http_conflicts_with_http_driver`
    - [x] E2E matrix test asserts the same deterministic code/reason
- [x] Determinism:
  - [x] no generated outputs; rerun-no-diff applies only to fixtures/docs/source stability
  - [x] diagnostics sorted by canonical ids using `strcmp`
- [x] Docs updated:
  - [x] `docs/adr/ADR-0027-runtime-driver-guard.md`
  - [x] `docs/architecture/runtime-driver-guard.md`
  - [x] `docs/adr/INDEX.md`
  - [x] `framework/packages/core/kernel/PUBLIC_API.md`

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
- "Reset discipline between UoW via `KernelRuntime`, which owns hook execution and delegates reset to Foundation reset orchestration."

tags_introduced: []
config_roots_introduced:
- "worker"

artifacts_introduced: []
adr: "docs/adr/ADR-0017-worker-manager-application-worker.md"
ssot_refs:
- "docs/ssot/tags.md"
- "docs/ssot/config-roots.md"
- "docs/ssot/observability.md"
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 1.10.0 — Tag registry SSoT exists. `cli.command` is an existing reserved tag owned by `platform/cli`; `platform/worker` may use it only as a non-owner contributor without depending on `platform/cli`.
  - 1.20.0 — Config roots registry SSoT exists
  - This epic introduces root `worker` owned by `platform/worker` and MUST register it in `docs/ssot/config-roots.md`
  - 1.200.0 — `Coretsia\Foundation\Serialization\StableJsonEncoder` exists and is canonical for runtime JSON bytes.
  - 1.200.0 — TagRegistry + DeterministicOrder + reset orchestration exist.
  - 1.205.0 — Foundation noop observability + logger baseline bindings exist, so worker runtime can safely resolve logger / optional observability ports before `platform/*` implementations are installed.
  - 1.280.0 — `core/kernel` provides the canonical UoW wrapper + reset execution policy via `KernelRuntime`.
  - 1.350.0 — `Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard` exists and is the only allowed runtime-composition guard here.

- Guard enforcement (MUST):
  - Before starting the pool, `WorkerStartCommand` MUST invoke `Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard` and reject conflicting runtime combinations deterministically.
  - This epic MAY test `WorkerStartCommand` through direct command invocation or a package-local command harness.
  - This epic MUST NOT require full `coretsia worker:start` dispatch through `platform/cli` container-backed tag discovery.
  - Full end-to-end CLI dispatch for `worker:*` commands is owned by `2.30.0 Platform CLI — Tag-first Command Catalog + Kernel ops façade`.
  - If `worker.task_type=http`, `WorkerStartCommand` MUST also enforce module compatibility through `RuntimeDriverGuard::assertHttpDriverCompatibleWithModules(...)`.
  - Missing `platform.http` in `ModulePlan` MUST fail with:
    - `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`
  - This failure MUST happen before `RequestHandlerInterface` resolution.

- Isolation policy (single-choice; cemented):
  - When `worker.enabled=true`, other long-running integrations/adapters MUST NOT become active implicitly.
  - If `worker.task_type=http`, the application preset MUST provide an HTTP handling stack by registering a `Psr\Http\Server\RequestHandlerInterface` implementation in the container.
    - Typical future provider: `platform/http` (Phase 2+), but worker MUST NOT require it as a compile-time dependency.

- Hook tags ownership note (MUST):
  - `kernel.hook.before_uow` and `kernel.hook.after_uow` are OWNED by `core/kernel`.
  - This epic MUST NOT consume them directly via `TagRegistry`.
  - This epic MUST NOT define or rename them.
  - The only allowed lifecycle entrypoint here is:
    - `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
  - Rationale:
    - hook discovery/order and reset trigger semantics are kernel-owned; `platform/worker` only supplies tasks to the canonical UoW runtime.

- Required deliverables (exact paths):
  - `framework/packages/core/contracts/` — `ResetInterface`, hooks, correlation provider port.
  - `framework/packages/core/foundation/` — deterministic order + context accessor (if used).
  - `framework/packages/core/kernel/` — UoW lifecycle + reset policy.

- Required config roots/keys:
  - `worker.*` — worker pool settings.

- Existing required tag usage:
  - CLI command discovery (IMPORTANT PHASE NOTE):
    - `platform/worker` contributes CLI commands via the existing reserved tag `cli.command` **only in kernel/container-backed CLI mode (Phase 1+)**.
    - `cli.command` is owned by `platform/cli`; this epic only contributes services under that tag.
    - Phase 0 CLI base (0.130.0) is config-only (`cli.commands`) and does NOT consume `cli.command`; therefore `worker:*` commands are out of scope for Phase 0 CLI base.
  - When referenced by `platform/worker`, the tag MUST be referenced through `Coretsia\Foundation\Tag\ReservedTags::CLI_COMMAND`.
  - The resolved tag string remains exactly `cli.command`.

- Hook/reset lifecycle boundary (MUST):
  - `kernel.hook.before_uow` and `kernel.hook.after_uow` are owned and consumed by `core/kernel`.
  - `platform/worker` MUST NOT discover or invoke these tags directly via `TagRegistry`.
  - `platform/worker` MUST execute each task as a UoW only through `Coretsia\Contracts\Runtime\KernelRuntimeInterface`.
  - Reset remains transitive through:
    - `platform/worker` → `KernelRuntime` → `ResetOrchestrator`

- Required kernel lifecycle entrypoint (single-choice):
  - `Coretsia\Contracts\Runtime\KernelRuntimeInterface` — the only allowed entrypoint for executing each task as a UoW.
  - Hook interfaces are kernel-owned lifecycle internals here and are NOT direct compile-time API requirements for `platform/worker`.

- Required contracts / ports:
  - `Coretsia\Contracts\Context\ContextAccessorInterface`
  - `Coretsia\Contracts\Observability\Tracing\TracerPortInterface`
  - `Coretsia\Contracts\Observability\Metrics\MeterPortInterface`
  - `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
  - `Psr\Container\ContainerInterface`
  - `Psr\Log\LoggerInterface`
  - (http task mode) `Psr\Http\Message\ServerRequestInterface`, `Psr\Http\Message\ResponseInterface`, `Psr\Http\Server\RequestHandlerInterface`
  - (CLI) Contract-level command & IO ports from `core/contracts` (the same ports used by `platform/cli` discovery), so `platform/worker` can contribute `cli.command` implementations without depending on `platform/cli`.

- Non-goals (legacy):
  - Not a RoadRunner replacement (in-framework pool only).
  - No assumption pcntl exists on all OS (driver abstraction).
  - No new cross-package ports (TaskFactory is internal to this package).

- Acceptance scenario:
  - When `WorkerStartCommand` is invoked through direct command invocation or a package-local harness,
    then a master spawns N workers, and each worker can process many tasks sequentially with reset after each task.
  - Full `coretsia worker:start` binary dispatch is verified by `2.30.0 Platform CLI`.

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
  - `Coretsia\Foundation\Time\Stopwatch`
  - `Coretsia\Foundation\Serialization\StableJsonEncoder`

### Entry points / integration points (MUST)

- CLI:
  - Phase note (cemented): this epic creates worker command classes and contributes them via the canonical `cli.command` tag only.
    - Phase 0 CLI base (0.130.0) is config-only (`cli.commands`) and does NOT consume `cli.command`.
    - Full `coretsia worker:*` dispatch through container-backed CLI tag discovery is owned by `2.30.0 Platform CLI`.
  - Created command classes:
    - `platform/worker` `src/Console/WorkerStartCommand.php` — intended command name `worker:start`
    - `platform/worker` `src/Console/WorkerStopCommand.php` — intended command name `worker:stop`
    - `platform/worker` `src/Console/WorkerStatusCommand.php` — intended command name `worker:status`
  - This epic MUST test worker command behavior through direct command invocation or package-local harnesses.
  - This epic MUST NOT require `platform/cli` to discover or dispatch these commands.

- HTTP:
  - N/A (worker can execute HTTP tasks internally; does not add endpoints)

- Kernel hooks/tags:
  - consumed transitively only through `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
  - `platform/worker` MUST NOT discover or invoke `kernel.hook.before_uow` directly
  - `platform/worker` MUST NOT discover or invoke `kernel.hook.after_uow` directly
  - consumer MUST NOT enumerate reset tags directly

- Artifacts:
  - reads: `skeleton/var/cache/<appTarget>/container.php` (optional; compiled container)
  - writes: `skeleton/var/tmp/worker.sock` — only when resolved `worker.control.transport = unix`
  - writes: `skeleton/var/tmp/worker.state.json`
  - writes: `skeleton/var/tmp/worker.stop`

### Deliverables (MUST)

#### Creates

- [x] `framework/packages/core/foundation/src/Serialization/StableJsonDecoder.php`
  - [x] deterministic JSON decoder for runtime-safe JSON bytes
  - [x] MUST use `json_decode(...)` with `JSON_THROW_ON_ERROR`
  - [x] MUST NOT rely on PHP associative JSON decoding because it can collapse JSON object/list distinctions too early
  - [x] MUST decode JSON objects as `stdClass` and JSON arrays as lists during the initial JSON parse
  - [x] MUST convert JSON objects to string-key maps before passing values to `JsonLikeNormalizer`
  - [x] MUST reject JSON object keys that cannot be safely represented as PHP string map keys
  - [x] MUST provide generic `decode()` / `decodeStable()` for shape-insensitive decoding
  - [x] MUST provide `decodeMap()` / `decodeStableMap()` for callers that require JSON object root
  - [x] MUST provide `decodeList()` / `decodeStableList()` for callers that require JSON array root
  - [x] conversion-stage `JsonLikeNormalizationException` MUST be mapped to stable `stable-json-*` failures
  - [x] Empty JSON object `{}` and empty JSON array `[]` both normalize to `[]` in generic mode
  - [x] Callers that require root object/list distinction MUST use `decodeMap()` or `decodeList()`
  - [x] Nested empty object/list distinction is not preserved by the baseline json-like model; schema-specific formats MUST NOT rely on that distinction unless a shape-preserving decoder is introduced
  - [x] MUST normalize decoded values through `JsonLikeNormalizer`
  - [x] MUST accept only baseline json-like runtime values:
    - [x] `null`
    - [x] `bool`
    - [x] `int`
    - [x] `string`
    - [x] `list<value>`
    - [x] `array<string,value>`
  - [x] MUST reject floats
  - [x] MUST reject unsupported decoded value types
  - [x] MUST return deterministic normalized arrays/lists
  - [x] MUST expose safe failure messages only
  - [x] MUST NOT expose raw JSON payloads
  - [x] MUST NOT expose file paths
  - [x] MUST NOT perform schema-specific validation
  - [x] MUST NOT perform redaction
  - [x] MUST NOT read files
  - [x] MUST NOT write files
  - [x] MUST NOT emit stdout/stderr

Package skeleton:
- [x] `framework/packages/platform/worker/src/Module/WorkerModule.php`
- [x] `framework/packages/platform/worker/src/Provider/WorkerServiceProvider.php`

- [x] `framework/packages/platform/worker/src/Provider/WorkerServiceFactory.php` — Stateless factory/wiring helper: builds services from DI+config; MUST NOT keep mutable runtime state (no caches/buffers).
  - [x] MUST create `WorkerPoolSpec` from merged worker config after config validation pipeline
    - [x] MUST read all required worker config keys:
      - [x] `worker.enabled`
      - [x] `worker.workers`
      - [x] `worker.max_requests`
      - [x] `worker.task_type`
      - [x] `worker.socket_path`
      - [x] `worker.driver`
      - [x] `worker.control.transport`
      - [x] `worker.tcp.host`
      - [x] `worker.tcp.port`
      - [x] `worker.state_path`
      - [x] `worker.stop_flag_path`
      - [x] `worker.stop_timeout_ms`
    - [x] MUST NOT silently invent missing defaults outside `config/worker.php`
    - [x] MUST NOT read environment variables for config defaults
    - [x] MUST NOT perform CLI command discovery
    - [x] MUST NOT depend on `platform/cli`
    - [x] MUST inject logger/tracer/meter/stopwatch dependencies into worker services through DI
    - [x] MUST NOT instantiate Noop logger/meter/tracer directly
  - [x] MUST inject `ContextAccessorInterface` into services that read context values

- [x] `framework/packages/platform/worker/README.md` (Observability / Errors / Security-Redaction)

Implementation:
- [x] `framework/packages/platform/worker/src/Internal/WorkerManagerDriverInterface.php`
  - [x] package-internal process driver strategy seam only
  - [x] MUST be marked `@internal`
  - [x] MUST NOT be treated as public package API
  - [x] MUST NOT be documented as an extension point in README
  - [x] MUST NOT be exported through composer extra metadata
  - [x] MUST NOT be moved to `core/contracts`
  - [x] Rationale:
    - [x] this is not a framework-level port
    - [x] this is only the internal seam between `WorkerManager`, `PcntlWorkerManagerDriver`, `ProcWorkerManagerDriver`, and worker package tests
  - [x] MUST expose deterministic driver identity:
    - [x] `name(): string` returning `pcntl` or `proc`
  - [x] MUST expose deterministic support check:
    - [x] `supports(WorkerPoolSpec $spec): bool`
  - [x] MUST expose lifecycle operations:
    - [x] `start(WorkerPoolSpec $spec): WorkerPoolState`
    - [x] `stop(WorkerPoolSpec $spec): WorkerPoolState`
    - [x] `status(WorkerPoolSpec $spec): WorkerPoolState`
  - [x] lifecycle methods MUST return immutable `WorkerPoolState`
  - [x] lifecycle methods MUST throw deterministic worker exceptions on failure
  - [x] MUST NOT use stdout/stderr directly
  - [x] MUST NOT log payloads or raw endpoints
  - [x] MUST NOT contain task execution logic
  - [x] MUST NOT call `KernelRuntimeInterface`
  - [x] MUST NOT know about CLI command dispatch or `platform/cli`
  - [x] MUST NOT depend on `platform/http`

- [x] `framework/packages/platform/worker/src/Manager/Driver/PcntlWorkerManagerDriver.php`
  - [x] implements `WorkerManagerDriverInterface`
  - [x] `name()` MUST return `pcntl`
  - [x] `supports(...)` MUST return false deterministically when:
    - [x] `pcntl_fork` is unavailable
    - [x] platform is Windows
    - [x] resolved worker driver is not `pcntl`
  - [x] MUST be skipped/disabled deterministically when pcntl is unavailable
  - [x] MUST NOT fail just because pcntl is unavailable when `worker.driver=auto`; fallback selection must choose `proc`
  - [x] MUST fork exactly `worker.workers` child workers when selected
  - [x] MUST track master pid and worker count without writing raw process details into unsafe diagnostics
  - [x] MUST pass `WorkerPoolSpec` to child runner so `ApplicationWorker` can enforce `worker.max_requests`
  - [x] `ApplicationWorker` MUST enforce `worker.max_requests`
  - [x] MUST respect graceful stop via stop flag / control channel according to `WorkerPoolSpec`
  - [x] MUST return `WorkerPoolState` with resolved driver `pcntl`
  - [x] MUST NOT write `worker.state.json` directly; state persistence goes through `WorkerStateStore`
  - [x] MUST NOT transmit raw task payloads over the control channel
  - [x] MUST NOT log raw socket path, raw tcp endpoint, task payload, headers, tokens, or config dumps
  - [x] MUST convert fork failure into `WorkerForkFailedException::forkFailed()`
  - [x] MUST convert communication/control failures into `WorkerCommunicationFailedException::communicationFailed()`
  - [x] MUST NOT call logger/meter/tracer directly
  - [x] MUST NOT write to stdout/stderr directly

- [x] `framework/packages/platform/worker/src/Manager/Driver/ProcWorkerManagerDriver.php`
  - [x] implements `WorkerManagerDriverInterface`
  - [x] `name()` MUST return `proc`
  - [x] MUST be the cross-platform fallback
  - [x] MUST support resolved worker driver `proc`
  - [x] MUST support `worker.driver=auto` after fallback resolution has selected `proc`
  - [x] MUST start child worker processes without relying on pcntl
  - [x] MUST construct process commands deterministically
  - [x] MUST NOT shell-inject untrusted config values
  - [x] MUST NOT expose full command lines, absolute paths, env values, or raw endpoints in public diagnostics
  - [x] MUST respect `worker.workers`
  - [x] MUST pass `worker.max_requests` to the child worker command deterministically
  - [x] `ApplicationWorker` MUST enforce `worker.max_requests`
  - [x] MUST request graceful stop via stop flag and control channel according to `WorkerPoolSpec`
  - [x] MUST return `WorkerPoolState` with resolved driver `proc`
  - [x] MUST NOT write `worker.state.json` directly; state persistence goes through `WorkerStateStore`
  - [x] MUST NOT transmit raw task payloads over the control channel
  - [x] MUST convert process start failures into `WorkerStartFailedException::startFailed()` or a narrower worker exception when applicable
  - [x] MUST convert communication/control failures into `WorkerCommunicationFailedException::communicationFailed()`
  - [x] MUST NOT call logger/meter/tracer directly
  - [x] MUST NOT write to stdout/stderr directly

- [x] `framework/packages/platform/worker/src/Internal/TaskFactoryInternalInterface.php`
  - [x] package-internal task factory seam only
  - [x] MUST be marked `@internal`
  - [x] MUST NOT be treated as public package API
  - [x] MUST NOT be documented as an extension point in README
  - [x] MUST NOT be exported through composer extra metadata
  - [x] MUST NOT be moved to `core/contracts`
  - [x] Rationale:
    - [x] this is not a framework-level task-source port
    - [x] this epic does not introduce external worker task providers
    - [x] real HTTP/queue task sources are owned by later `platform/http` or integration epics
  - [x] MUST produce package-internal task work consumed by `ApplicationWorker`
  - [x] produced task work MUST expose a safe operation id for observability
  - [x] produced operation id MUST be deterministic and safe for metric label `operation`
  - [x] MUST NOT expose raw payloads through operation ids
  - [x] MUST NOT expose raw socket paths, raw tcp endpoints, headers, tokens, or config values
  - [x] MUST support task types defined by worker config:
    - [x] `queue`
    - [x] `http`
  - [x] MUST fail deterministically for unsupported task types
  - [x] MUST NOT read from `platform/cli`
  - [x] MUST NOT depend on `platform/http`
  - [x] MUST NOT write to stdout/stderr directly

- [x] `framework/packages/platform/worker/src/Task/HttpTaskFactory.php`
  - [x] implements `TaskFactoryInternalInterface`
  - [x] handles `worker.task_type=http`
  - [x] MUST NOT create PSR-7 requests itself
  - [x] MUST NOT implement a real HTTP request source in this epic
  - [x] MUST NOT depend on `platform/http`
  - [x] MUST NOT import `Coretsia\Platform\Http\*`
  - [x] MAY validate that `Psr\Http\Server\RequestHandlerInterface` is resolvable
  - [x] MUST fail deterministically when HTTP task mode requires a handler and handler preflight fails:
    - [x] missing binding / PSR container not-found MUST use `WorkerStartFailedException::requestHandlerMissing()`
    - [x] container resolution failure MUST use `WorkerStartFailedException::requestHandlerUnresolvable()`
    - [x] resolved service with invalid type MUST use `WorkerStartFailedException::requestHandlerInvalid()`
  - [x] request handler missing failure MUST happen only after RuntimeDriverGuard/module compatibility has already passed
  - [x] MUST NOT bypass `RuntimeDriverGuard`
  - [x] MUST NOT transmit or log raw HTTP payloads
  - [x] MUST NOT log headers, cookies, Authorization, tokens, or body fragments
  - [x] MUST expose safe operation id such as `http`
  - [x] MUST NOT write to stdout/stderr directly
  - [x] real HTTP task payload production remains owned by a later `platform/http` or runtime adapter epic

- [x] `framework/packages/platform/worker/src/Task/QueueTaskFactory.php`
  - [x] implements `TaskFactoryInternalInterface`
  - [x] handles `worker.task_type=queue`
  - [x] MUST NOT implement a real external queue adapter in this epic
  - [x] MUST NOT depend on integrations or external queue packages
  - [x] MAY produce deterministic package-local queue task work for smoke/integration tests
  - [x] MUST execute queue task work through `KernelRuntimeInterface` via `ApplicationWorker`
  - [x] MUST expose safe operation id such as `queue`
  - [x] MUST NOT expose raw queue payloads
  - [x] MUST NOT log raw payloads
  - [x] MUST NOT transmit payloads over control socket
  - [x] MUST NOT write to stdout/stderr directly
  - [x] future real queue sources/adapters MUST be introduced by later epics

- [x] `framework/packages/platform/worker/src/Communication/WorkerSocketServer.php`
  - [x] owns worker control channel behavior
  - [x] MUST support resolved control transport:
    - [x] `unix`
    - [x] `tcp`
  - [x] MUST NOT be used for task payload transport
  - [x] control protocol MUST be payload-free
  - [x] MUST support only safe control operations:
    - [x] start/status summary as applicable
    - [x] stop request
    - [x] health/status query
  - [x] MUST NOT transmit raw task payloads
  - [x] MUST NOT log raw socket path
  - [x] MUST NOT log raw tcp host/port
  - [x] MUST hash endpoint identifiers before logging/state output
  - [x] MUST convert communication failures into `WorkerCommunicationFailedException::communicationFailed()`
  - [x] MUST NOT expose absolute paths, socket paths, tcp endpoints, payload fragments, headers, or tokens in exceptions
  - [x] MUST NOT write to stdout/stderr directly

- [x] `framework/packages/platform/worker/src/Console/WorkerStartCommand.php`
  - [x] package worker start command class
  - [x] intended command name MUST be `worker:start`
  - [x] MUST use contract-level CLI command / input / output ports from `core/contracts`
  - [x] MUST NOT depend on `platform/cli`
  - [x] MUST be invokable directly or through a package-local command harness in this epic
  - [x] MUST NOT require full `coretsia worker:start` binary/catalog dispatch
  - [x] MUST invoke `RuntimeDriverGuard` before starting the pool
  - [x] MUST surface guard failures without translating them into worker-specific conflict codes
  - [x] MUST call `RuntimeDriverGuard::assertCompatible(...)` before start
  - [x] when `worker.task_type=http`, MUST call `RuntimeDriverGuard::assertHttpDriverCompatibleWithModules(...)`
  - [x] RuntimeDriverGuard failure MUST prevent command startup side effects:
    - [x] MUST happen before `WorkerServiceFactory::workerPoolSpec(...)`
    - [x] MUST happen before `WorkerManager::start(...)`
    - [x] therefore MUST happen before worker manager / driver side effects:
      - [x] process fork
      - [x] `proc_open`
      - [x] socket open
      - [x] pid file write
      - [x] state file write
      - [x] stop flag write
    - [x] command run path MUST NOT resolve `RequestHandlerInterface` directly
  - [x] MUST build or receive `WorkerPoolSpec` from validated config
  - [x] MUST delegate pool startup to `WorkerManager`
  - [x] MUST use only CLI output abstraction, never raw stdout/stderr
  - [x] MUST NOT echo/print/printf/var_dump/print_r/error_log
  - [x] MUST NOT expose raw config values, raw endpoints, absolute paths, env values, payloads, headers, or tokens
  - [x] MUST emit only safe start summary through `OutputInterface`
  - [x] MAY delegate logger/observability summary emission to `WorkerManager`
  - [x] MUST NOT write ContextStore values directly
  - [x] MUST let KernelRuntime own runtime UoW context writes
  - [x] command does not log directly
  - [x] lifecycle logging remains delegated to `WorkerManager`
  - [x] start summary MUST NOT contain raw socket path, raw tcp endpoint, absolute paths, config dumps, env values, payloads, headers, or tokens
  - [x] command identity MUST be declared by the command class:
    - [x] `public const string NAME = 'worker:start'`
    - [x] `public const string SUMMARY = 'Start the configured worker pool.'`
    - [x] `public const string GROUP = 'worker'`
    - [x] `public const bool HIDDEN = false`
    - [x] `public const string MODE = 'none'`
    - [x] `public const array ARGUMENTS = []`
    - [x] `public const array OPTIONS = []`
  - [x] `name()` MUST return `self::NAME`
  - [x] command name MUST match canonical regex:
    - [x] `\A[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)*\z`
  - [x] provider tag metadata MUST reference these constants
  - [x] provider tag metadata `name` MUST equal `CommandInterface::name()`
  - [x] command MUST consume parsed `InputInterface` accessors and MUST NOT parse raw tokens directly unless explicitly needed for diagnostics
  - [x] command MUST NOT depend on `platform/cli`
  - [x] command MUST NOT import `Coretsia\Platform\Cli\*`

- [x] `framework/packages/platform/worker/src/Console/WorkerStopCommand.php`
  - [x] package worker stop command class
  - [x] intended command name MUST be `worker:stop`
  - [x] MUST use contract-level CLI command / input / output ports from `core/contracts`
  - [x] MUST NOT depend on `platform/cli`
  - [x] MUST be invokable directly or through a package-local command harness in this epic
  - [x] MUST NOT require full binary/catalog dispatch
  - [x] MUST delegate stop behavior to `WorkerManager` or dedicated control service
  - [x] MUST use configured stop flag / control transport through `WorkerPoolSpec`
  - [x] MUST NOT write stop files directly if stop handling is owned by manager/driver/control collaborator
  - [x] MUST handle “not running” deterministically
  - [x] MUST NOT expose raw pid path, socket path, tcp endpoint, or absolute path in diagnostics
  - [x] MUST use only CLI output abstraction, never raw stdout/stderr
  - [x] MUST NOT log payloads
  - [x] MUST NOT write ContextStore values directly
  - [x] MUST let KernelRuntime own runtime UoW context writes
  - [x] MAY delegate logger summary emission to `WorkerManager`
  - [x] stop summary MUST NOT contain raw socket path, raw tcp endpoint, absolute paths, config dumps, env values, payloads, headers, or tokens
  - [x] command identity MUST be declared by the command class:
    - [x] `public const string NAME = 'worker:stop'`
    - [x] `public const string SUMMARY = 'Stop the configured worker pool.'`
    - [x] `public const string GROUP = 'worker'`
    - [x] `public const bool HIDDEN = false`
    - [x] `public const string MODE = 'none'`
    - [x] `public const array ARGUMENTS = []`
    - [x] `public const array OPTIONS = []`
  - [x] `name()` MUST return `self::NAME`
  - [x] command name MUST match canonical regex:
    - [x] `\A[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)*\z`
  - [x] provider tag metadata MUST reference these constants
  - [x] provider tag metadata `name` MUST equal `CommandInterface::name()`
  - [x] command MUST consume parsed `InputInterface` accessors and MUST NOT parse raw tokens directly unless explicitly needed for diagnostics
  - [x] command MUST NOT depend on `platform/cli`
  - [x] command MUST NOT import `Coretsia\Platform\Cli\*`

- [x] `framework/packages/platform/worker/src/Console/WorkerStatusCommand.php`
  - [x] package worker status command class
  - [x] intended command name MUST be `worker:status`
  - [x] MUST use contract-level CLI command / input / output ports from `core/contracts`
  - [x] MUST NOT depend on `platform/cli`
  - [x] MUST be invokable directly or through a package-local command harness in this epic
  - [x] MUST NOT require full binary/catalog dispatch
  - [x] MUST read worker state only through `WorkerStateStore` or manager status API
  - [x] MUST output only safe status data:
    - [x] lifecycle status
    - [x] pid
    - [x] worker count
    - [x] resolved driver
    - [x] resolved control transport
    - [x] endpoint hash
  - [x] MUST NOT output raw socket path
  - [x] MUST NOT output raw tcp host/port
  - [x] MUST NOT output absolute paths
  - [x] MUST NOT output payloads, headers, tokens, config dumps, or env values
  - [x] MUST use only CLI output abstraction, never raw stdout/stderr
  - [x] MUST NOT write ContextStore values directly
  - [x] MUST let KernelRuntime own runtime UoW context writes
  - [x] MAY delegate logger summary emission to `WorkerManager`
  - [x] status summary MUST use endpoint hash only
  - [x] status summary MUST NOT contain raw socket path, raw tcp endpoint, absolute paths, config dumps, env values, payloads, headers, or tokens
  - [x] command identity MUST be declared by the command class:
    - [x] `public const string NAME = 'worker:status'`
    - [x] `public const string SUMMARY = 'Show the configured worker pool status.'`
    - [x] `public const string GROUP = 'worker'`
    - [x] `public const bool HIDDEN = false`
    - [x] `public const string MODE = 'none'`
    - [x] `public const array ARGUMENTS = []`
    - [x] `public const array OPTIONS = []`
  - [x] `name()` MUST return `self::NAME`
  - [x] command name MUST match canonical regex:
    - [x] `\A[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)*\z`
  - [x] provider tag metadata MUST reference these constants
  - [x] provider tag metadata `name` MUST equal `CommandInterface::name()`
  - [x] command MUST consume parsed `InputInterface` accessors and MUST NOT parse raw tokens directly unless explicitly needed for diagnostics
  - [x] command MUST NOT depend on `platform/cli`
  - [x] command MUST NOT import `Coretsia\Platform\Cli\*`

- [x] `framework/packages/platform/worker/src/Runtime/WorkerStateStore.php`
  - [x] only class allowed to write `worker.state.json`
  - [x] MUST read and write worker state using `WorkerPoolState`
  - [x] MUST use `Coretsia\Foundation\Serialization\StableJsonEncoder`
  - [x] MUST use `Coretsia\Foundation\Serialization\StableJsonDecoder` for reading `worker.state.json` once the decoder exists
  - [x] MUST preserve cemented schema:
    - [x] `version`
    - [x] `pid`
    - [x] `worker_count`
    - [x] `driver_requested`
    - [x] `driver`
    - [x] `control_transport_requested`
    - [x] `control_transport`
    - [x] `endpoint_hash`
  - [x] `version` MUST be exactly `1`
  - [x] `started_at` MUST be forbidden
  - [x] `env` MUST be forbidden
  - [x] MUST NOT persist timestamps
  - [x] MUST NOT persist raw socket path
  - [x] MUST NOT persist raw tcp host/port
  - [x] MUST NOT persist absolute paths
  - [x] MUST NOT persist task payloads
  - [x] MUST compute endpoint hash from canonical endpoint identifier when creating state:
    - [x] unix: `unix:` + configured socket path exactly
    - [x] tcp: `tcp:` + host + `:` + resolved port
  - [x] hash encoding MUST be lowercase hex sha256
  - [x] MUST normalize LF and ensure final newline
  - [x] MUST not expose absolute paths in exceptions/logs
  - [x] MUST not expose raw state file path in public diagnostics
  - [x] MAY use atomic write mechanics, but persisted JSON bytes MUST remain deterministic for the same state
  - [x] MUST return deterministic failure exceptions on invalid/unreadable state

- [x] `framework/packages/platform/worker/src/Runtime/WorkerPoolSpec.php`
  - [x] immutable normalized worker pool specification
  - [x] MUST represent validated `worker.*` config input
  - [x] MUST be built from the complete worker config key set:
    - [x] `worker.enabled`
    - [x] `worker.workers`
    - [x] `worker.max_requests`
    - [x] `worker.task_type`
    - [x] `worker.socket_path`
    - [x] `worker.driver`
    - [x] `worker.control.transport`
    - [x] `worker.tcp.host`
    - [x] `worker.tcp.port`
    - [x] `worker.state_path`
    - [x] `worker.stop_flag_path`
    - [x] `worker.stop_timeout_ms`
  - [x] MUST preserve default values exactly as defined by `framework/packages/platform/worker/config/worker.php`
  - [x] MUST preserve path values as relative strings:
    - [x] `socket_path`
    - [x] `state_path`
    - [x] `stop_flag_path`
  - [x] MUST NOT introduce a separate `worker.pid_path`
  - [x] master pid MUST be stored only inside `worker.state.json`
  - [x] MUST NOT prepend `skeleton/` to stored path values
  - [x] MUST expose resolved values separately from requested values:
    - [x] requested driver: `auto|pcntl|proc`
    - [x] resolved driver: `pcntl|proc`
    - [x] requested control transport: `auto|unix|tcp`
    - [x] resolved control transport: `unix|tcp`
  - [x] MUST expose endpoint identifier for hashing without exposing it in logs/public diagnostics
  - [x] MUST expose:
    - [x] `enabled(): bool`
    - [x] `workers(): int`
    - [x] `maxRequests(): int`
    - [x] `taskType(): string`
    - [x] `driverRequested(): string`
    - [x] `driver(): string`
    - [x] `controlTransportRequested(): string`
    - [x] `controlTransport(): string`
    - [x] `socketPath(): string`
    - [x] no `pidPath()` accessor is exposed
    - [x] `pid()` belongs to `WorkerPoolState`, not `WorkerPoolSpec`
    - [x] `statePath(): string`
    - [x] `stopFlagPath(): string`
    - [x] `tcpHost(): string`
    - [x] `tcpPort(): int`
    - [x] `stopTimeoutMs(): int`
  - [x] MUST normalize defaults exactly from `config/worker.php`
  - [x] MUST keep configured paths relative
  - [x] MUST NOT expand relative paths with `realpath`
  - [x] MUST NOT store absolute paths
  - [x] MUST resolve `worker.driver=auto` deterministically:
    - [x] `pcntl` when `pcntl_fork` is available and platform is not Windows
    - [x] otherwise `proc`
  - [x] MUST resolve `worker.control.transport=auto` deterministically:
    - [x] `unix` when resolved driver is `pcntl` and unix domain sockets are supported
    - [x] otherwise `tcp`
  - [x] MUST reject or receive already-rejected invalid values:
    - [x] workers <= 0
    - [x] max_requests <= 0
    - [x] tcp port outside `1..65535`
    - [x] tcp port `0`
    - [x] float values
    - [x] absolute paths
    - [x] unknown enum values
  - [x] MUST expose deterministic endpoint identifier for hashing
  - [x] MUST NOT expose raw endpoint in logs/exception messages
  - [x] MUST NOT read environment variables except deterministic capability checks needed for driver/transport auto-resolution
  - [x] MUST NOT write files
  - [x] MUST NOT emit stdout/stderr
  - [x] auto-resolution MUST be testable with explicit capability inputs
  - [x] tests MUST NOT depend on the host machine actually having pcntl or unix sockets
  - [x] production resolution MAY use deterministic runtime capability checks:
    - [x] `pcntl_fork` availability
    - [x] platform family
    - [x] unix domain socket support

- [x] `framework/packages/platform/worker/src/Runtime/WorkerPoolState.php`
  - [x] immutable runtime pool state DTO
  - [x] MUST represent only safe state fields allowed in `worker.state.json`
  - [x] MUST expose:
    - [x] `version(): int`
    - [x] `pid(): int`
    - [x] `workerCount(): int`
    - [x] `driverRequested(): string`
    - [x] `driver(): string`
    - [x] `controlTransportRequested(): string`
    - [x] `controlTransport(): string`
    - [x] `endpointHash(): string`
    - [x] `toArray(): array`
  - [x] `version()` MUST always be `1`
  - [x] `toArray()` MUST return stable keys matching the cemented JSON schema
  - [x] `toArray()` MUST NOT include:
    - [x] timestamps
    - [x] env
    - [x] raw socket path
    - [x] raw tcp host/port
    - [x] absolute paths
    - [x] payloads
    - [x] headers
    - [x] tokens
  - [x] MUST validate pid and worker count as ints
  - [x] MUST validate resolved driver is only `pcntl|proc`
  - [x] MUST validate resolved transport is only `unix|tcp`
  - [x] MUST validate `endpoint_hash` is lowercase hex sha256
  - [x] MUST NOT write files
  - [x] MUST NOT emit stdout/stderr

- [x] `framework/packages/platform/worker/src/Manager/WorkerManager.php`
  - [x] owns high-level worker pool orchestration: start / stop / status
  - [x] MUST accept already-built `WorkerPoolSpec`
  - [x] MUST delegate process-specific behavior to `WorkerManagerDriverInterface`
  - [x] MUST NOT fork, call `proc_open`, open sockets, or write state files directly when this is driver/store responsibility
  - [x] MUST select only an explicitly supported driver for the resolved `WorkerPoolSpec`
  - [x] MUST select the driver resolved by `WorkerPoolSpec`
  - [x] MUST select `ProcWorkerManagerDriver` when `WorkerPoolSpec::driver()` is resolved to `proc`
  - [x] MUST NOT silently fallback from explicit `pcntl` to `proc` when `WorkerPoolSpec::driver()` is `pcntl` but the pcntl driver does not support the spec
  - [x] MUST NOT persist pool state directly
  - [x] MUST delegate lifecycle to drivers that persist pool state through `WorkerStateStore`
  - [x] MUST NOT write `worker.state.json` directly
  - [x] MUST NOT write pid/socket/stop files except through the dedicated runtime/storage/control collaborator
  - [x] MUST NOT call `RuntimeDriverGuard`; guard enforcement belongs to `WorkerStartCommand` before manager start
  - [x] MUST NOT call `KernelRuntimeInterface` directly for individual task execution; that belongs to `ApplicationWorker`
  - [x] MUST NOT enumerate `kernel.reset`, `kernel.hook.before_uow`, or `kernel.hook.after_uow`
  - [x] MUST NOT call `ResetOrchestrator::resetAll()` directly
  - [x] MUST emit `worker.process` span around pool lifecycle operations when tracing port is available
  - [x] MUST record `worker.process_total` metric with allowlisted label `status`
  - [x] span attributes MAY include only policy-allowed safe attrs:
    - [x] `pid`
    - [x] `outcome`
  - [x] `pid` MUST NOT be metric labels
  - [x] MUST emit/log only start/stop/status summary data through injected observability/logger ports
  - [x] MUST NOT log payloads, raw socket paths, raw tcp endpoints, absolute paths, or config dumps
  - [x] MUST surface deterministic worker exceptions only:
    - [x] `WorkerStartFailedException`
    - [x] `WorkerForkFailedException`
    - [x] `WorkerCommunicationFailedException`
  - [x] MUST NOT expose previous throwable messages in public diagnostics
  - [x] MUST NOT write to stdout/stderr directly
  - [x] MUST NOT write ContextStore values directly
  - [x] MAY include safe correlation/uow ids in logs only if provided by context accessor and allowed by redaction policy

- [x] `framework/packages/platform/worker/src/Worker/ApplicationWorker.php`
  - [x] owns sequential task execution inside a long-running worker child
  - [x] MUST process many tasks sequentially without restarting PHP
  - [x] MUST execute each task as a separate UoW only through `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
  - [x] MUST pass the resolved UoW type (`http` or `queue`) into `KernelRuntimeInterface`
  - [x] MAY read safe context values for internal correlation/logging only when allowed by observability policy
  - [x] MUST NOT emit correlation_id, uow_id, or uow_type as worker task metric labels
  - [x] MUST NOT emit correlation_id, uow_id, or uow_type as worker.task span attributes unless explicitly allowed by `docs/ssot/observability.md`
  - [x] MUST NOT write context directly
  - [x] MUST NOT create its own UoW id
  - [x] MUST NOT create its own correlation id
  - [x] MUST NOT override `uow_type` after passing it to `KernelRuntimeInterface`
  - [x] MUST NOT write context directly; KernelRuntime owns base `ContextStore` writes
  - [x] MUST NOT invoke kernel hooks directly
  - [x] MUST NOT enumerate `kernel.hook.before_uow` / `kernel.hook.after_uow`
  - [x] MUST NOT enumerate `kernel.reset`
  - [x] MUST NOT call `ResetOrchestrator::resetAll()` directly
  - [x] MUST stop/recycle after `worker.max_requests`
  - [x] MUST observe graceful stop requests between tasks
  - [x] MUST NOT interrupt an in-flight task unless future cancellation semantics are explicitly introduced
  - [x] MUST obtain task work only through `TaskFactoryInternalInterface`
  - [x] MUST NOT implement queue adapter or HTTP adapter logic itself
  - [x] MUST emit `worker.task` span / task metrics around task execution
  - [x] metric labels MUST be only allowlisted labels:
    - [x] `operation`
    - [x] `outcome`
  - [x] resolved task type MUST be passed as UoW type only and MUST NOT become a metric label
  - [x] MUST emit `worker.task` span around each task when tracing port is available
  - [x] MUST record `worker.task_total` with labels:
    - [x] `operation`
    - [x] `outcome`
  - [x] MUST record `worker.task_duration_ms` with labels:
    - [x] `operation`
    - [x] `outcome`
  - [x] MUST measure duration using Foundation `Stopwatch`
  - [x] MUST catch tracer/meter failures and MUST NOT change task control-flow semantics
  - [x] MUST NOT log raw payloads
  - [x] MUST NOT write to stdout/stderr directly

- [x] `framework/packages/platform/worker/bin/coretsia-worker`
  - [x] worker-owned proc child-process launcher
  - [x] MUST be executable PHP launcher
  - [x] MUST NOT import or instantiate `Coretsia\Platform\Cli\Application`
  - [x] MUST NOT depend on `platform/cli`
  - [x] MUST NOT use `CommandCatalog`
  - [x] MUST NOT consume `cli.command`
  - [x] MUST NOT parse user-facing CLI commands
  - [x] MUST parse only worker-owned internal proc args
  - [x] MUST bootstrap through kernel/runtime services, not CLI dispatch
  - [x] MUST resolve `ApplicationWorker` from the application/container boundary
  - [x] MUST resolve or build `WorkerPoolSpec` from validated config
  - [x] MUST run `ApplicationWorker::run($spec)`
  - [x] MUST emit only deterministic safe boot failures
  - [x] MUST NOT expose absolute paths, raw config values, env values, payloads, headers, tokens, command lines, or stack traces

Docs:
- [x] `docs/adr/ADR-0017-worker-manager-application-worker.md`
- [x] `docs/architecture/worker.md` — MUST include:
  - [x] process model (master + N workers) and driver selection (`pcntl` vs `proc_open`)
  - [x] reset discipline between UoW is achieved only transitively via `Coretsia\Contracts\Runtime\KernelRuntimeInterface` (`begin -> before hooks -> task -> after hooks -> ResetOrchestrator::resetAll()`).
  - [x] `platform/worker` MUST NOT call hooks or `ResetOrchestrator` directly.
  - [x] safety limits (`max_requests`, graceful shutdown, stop timeout)
  - [x] ops notes (pid/state files, control transport, redaction rules)

Configuration:
- [x] `framework/packages/platform/worker/config/worker.php`
- [x] `framework/packages/platform/worker/config/rules.php`

- [x] `framework/packages/platform/worker/tests/Fake/FakeWorkerManagerDriver.php`
  - [x] simulates spawn/stop/status deterministically
  - [x] does not fork
  - [x] does not call proc_open
  - [x] does not use sockets
  - [x] used by unit/integration tests that verify manager lifecycle and max_requests behavior

Error:
- [x] `framework/packages/platform/worker/src/Exception/WorkerException.php`
  - [x] abstract base exception for package-level worker failures
  - [x] MUST expose:
    - [x] `errorCode(): string`
    - [x] `reason(): string`
  - [x] public message MUST be deterministic:
    - [x] `<ERROR_CODE>: <reason>`
  - [x] MUST NOT be thrown directly unless no narrower worker exception applies
  - [x] MUST NOT expose previous throwable messages in public diagnostics
  - [x] `WorkerException` messages MUST be stable and MUST NOT include:
    - [x] absolute paths (POSIX/Windows/UNC),
    - [x] raw socket paths, raw tcp endpoints,
    - [x] payload fragments, headers, tokens.

- [x] `framework/packages/platform/worker/src/Exception/WorkerStartFailedException.php`
  - [x] errorCode: `CORETSIA_WORKER_START_FAILED`
  - [x] reasons:
    - [x] `start_failed`
    - [x] `invalid_state`
    - [x] `request_handler_missing`
    - [x] `request_handler_unresolvable`
    - [x] `request_handler_invalid`
  - [x] named constructors:
    - [x] `startFailed(): self`
    - [x] `invalidState(): self`
    - [x] `requestHandlerMissing(): self`
    - [x] `requestHandlerUnresolvable(): self`
    - [x] `requestHandlerInvalid(): self`

- [x] `framework/packages/platform/worker/src/Exception/WorkerForkFailedException.php`
  - [x] errorCode: `CORETSIA_WORKER_FORK_FAILED`
  - [x] reasons:
    - [x] `fork_failed`
  - [x] named constructors:
    - [x] `forkFailed(): self`

- [x] `framework/packages/platform/worker/src/Exception/WorkerCommunicationFailedException.php`
  - [x] errorCode: `CORETSIA_WORKER_COMMUNICATION_FAILED`
  - [x] reasons:
    - [x] `communication_failed`
  - [x] named constructors:
    - [x] `communicationFailed(): self`

- [x] `framework/packages/platform/worker/src/Exception/WorkerNotRunningException.php`
  - [x] errorCode: `CORETSIA_WORKER_NOT_RUNNING`
  - [x] reasons:
    - [x] `not_running`
  - [x] named constructors:
    - [x] `notRunning(): self`
  - [x] MUST be used for stop/status operations when worker state marker is missing
  - [x] MUST NOT be used for invalid JSON, schema drift, unreadable existing state, or invalid state values
  - [x] MUST NOT expose raw pid path, state path, socket path, tcp endpoint, absolute path, OS error text, previous throwable messages, env values, payloads, headers, or tokens

Tests:
- [x] `framework/packages/platform/worker/tests/Contract/CoretsiaWorkerChildLauncherContractTest.php`
  - [x] launcher accepts only worker-owned internal args
  - [x] launcher accepts `--coretsia-worker-config`
  - [x] launcher accepts `--coretsia-worker-container`
  - [x] launcher rejects unknown args
  - [x] launcher rejects missing `--coretsia-worker-config`
  - [x] launcher rejects missing `--coretsia-worker-container`
  - [x] launcher does not import `Coretsia\Platform\Cli\Application`
  - [x] launcher does not reference `CommandCatalog`
  - [x] launcher does not reference `ReservedTags::CLI_COMMAND`
  - [x] launcher imports only public Kernel boot facade for artifact runtime boot
  - [x] launcher does not import Kernel artifact/container internal classes

- [x] `framework/packages/platform/worker/tests/Contract/WorkerNotRunningLifecycleContractTest.php`
  - [x] `WorkerStateStore::read(...)` throws `WorkerNotRunningException::notRunning()` when worker state marker is missing
  - [x] missing worker state marker returns error code `CORETSIA_WORKER_NOT_RUNNING`
  - [x] missing worker state marker returns reason `not_running`
  - [x] existing but unreadable worker state does not map to `WorkerNotRunningException`
  - [x] existing non-file state path does not map to `WorkerNotRunningException`
  - [x] invalid JSON state does not map to `WorkerNotRunningException`
  - [x] schema drift / extra keys do not map to `WorkerNotRunningException`
  - [x] invalid worker state values do not map to `WorkerNotRunningException`
  - [x] `WorkerManager::stop(...)` passes through `WorkerNotRunningException`
  - [x] `WorkerManager::status(...)` passes through `WorkerNotRunningException`
  - [x] not-running diagnostics do not expose:
    - [x] raw state path
    - [x] raw pid path
    - [x] raw socket path
    - [x] raw TCP endpoint
    - [x] absolute paths
    - [x] OS error text
    - [x] previous throwable messages
    - [x] env values
    - [x] payloads
    - [x] headers
    - [x] tokens

- [x] `framework/packages/platform/worker/tests/Contract/WorkerStatusCommandContractTest.php`
  - [x] directly instantiates `WorkerStatusCommand`
  - [x] calls `run(InputInterface $input, OutputInterface $output)` directly
  - [x] does not use full binary/catalog dispatch
  - [x] delegates to `WorkerServiceFactory::workerPoolSpec(...)`
  - [x] delegates to `WorkerManager::status(...)`
  - [x] passes the built `WorkerPoolSpec` into `WorkerManager::status(...)`
  - [x] successful status emits only safe JSON summary keys:
    - [x] `status`
    - [x] `pid`
    - [x] `worker_count`
    - [x] `driver`
    - [x] `control_transport`
    - [x] `endpoint_hash`
  - [x] successful status summary uses `status=running`
  - [x] successful status summary does not contain:
    - [x] raw socket path
    - [x] raw TCP endpoint
    - [x] absolute paths
    - [x] config dumps
    - [x] env values
    - [x] payloads
    - [x] headers
    - [x] tokens
  - [x] not-running case returns `CORETSIA_WORKER_NOT_RUNNING`
  - [x] not-running case returns `not_running`
  - [x] communication failure returns original `WorkerCommunicationFailedException::ERROR_CODE`
  - [x] communication failure returns original `reason()`
  - [x] worker start/state failure returns `CORETSIA_WORKER_STATUS_FAILED`
  - [x] worker start/state failure returns `worker-status-failed`
  - [x] invalid parsed command name fails safely
  - [x] unsupported arguments fail safely
  - [x] unsupported options fail safely
  - [x] command does not call `InputInterface::tokens()`

- [x] `framework/packages/platform/worker/tests/Contract/WorkerStopCommandContractTest.php`
  - [x] directly instantiates `WorkerStopCommand`
  - [x] calls `run(InputInterface $input, OutputInterface $output)` directly
  - [x] does not use full binary/catalog dispatch
  - [x] delegates to `WorkerServiceFactory::workerPoolSpec(...)`
  - [x] delegates to `WorkerManager::stop(...)`
  - [x] passes the built `WorkerPoolSpec` into `WorkerManager::stop(...)`
  - [x] successful stop emits only safe JSON summary keys:
    - [x] `status`
    - [x] `pid`
    - [x] `worker_count`
    - [x] `driver`
    - [x] `control_transport`
    - [x] `endpoint_hash`
  - [x] successful stop summary does not contain raw paths/endpoints/config/env/payload/header/token fields
  - [x] communication failure returns original `WorkerCommunicationFailedException::ERROR_CODE`
  - [x] communication failure returns original `reason()`
  - [x] not-running case fails deterministically
  - [x] invalid command name fails safely
  - [x] unsupported arguments fail safely
  - [x] unsupported options fail safely
  - [x] command does not call `InputInterface::tokens()`
  - [x] not-running case returns `CORETSIA_WORKER_NOT_RUNNING`
  - [x] not-running case returns `not_running`
  - [x] invalid worker state does not get translated to `CORETSIA_WORKER_NOT_RUNNING`

- [x] `framework/packages/platform/worker/tests/Contract/WorkerStartCommandContractTest.php`
  - [x] directly instantiates `WorkerStartCommand`
  - [x] calls `run(InputInterface $input, OutputInterface $output)` directly
  - [x] does not use full `coretsia worker:start` binary/catalog dispatch
  - [x] successful start delegates to `WorkerManager`
  - [x] successful start emits only safe JSON summary keys:
    - [x] `status`
    - [x] `pid`
    - [x] `worker_count`
    - [x] `driver`
    - [x] `control_transport`
    - [x] `endpoint_hash`
  - [x] successful start summary does not contain:
    - [x] raw socket path
    - [x] raw TCP endpoint
    - [x] absolute paths
    - [x] config dumps
    - [x] env values
    - [x] payloads
    - [x] headers
    - [x] tokens
  - [x] guard conflict returns original `RuntimeDriverConflictException::ERROR_CODE`
  - [x] guard conflict returns original `reason()`
  - [x] guard invalid config returns original `RuntimeDriverInvalidConfigException::ERROR_CODE`
  - [x] guard invalid config returns original `reason()`
  - [x] HTTP worker without `platform.http` returns original runtime-driver invalid-config code/reason
  - [x] guard failure prevents `WorkerManager::start()`
  - [x] guard failure prevents worker driver start
  - [x] command does not call `InputInterface::tokens()`
  - [x] invalid parsed command name fails safely
  - [x] unsupported arguments fail safely
  - [x] unsupported options fail safely

- [x] `framework/packages/platform/worker/tests/Contract/WorkerStateStoreOwnershipContractTest.php`
  - [x] only `WorkerStateStore` has `write()`
  - [x] only `WorkerStateStore` has `writeBytes()`
  - [x] only `WorkerStateStore` writes bytes to worker state temp file
  - [x] only `WorkerStateStore` performs atomic rename into state path
  - [x] other worker classes must not resolve `$spec->statePath()`
  - [x] drivers/providers/factories may depend on `WorkerStateStore`, but must not write `worker.state.json` directly

- [x] `framework/packages/core/foundation/tests/Contract/StableJsonSerializationSafetyContractTest.php`
  - [x] StableJsonEncoder does not read/write files
  - [x] StableJsonDecoder does not read/write files
  - [x] JsonLikeNormalizer does not read/write files
  - [x] serialization foundation does not inspect env
  - [x] serialization foundation does not emit stdout/stderr
  - [x] encoder diagnostics do not leak raw values/classes/payloads/headers/tokens/paths/env
  - [x] decoder diagnostics do not leak raw JSON/payloads/headers/tokens/paths/env
  - [x] normalizer diagnostics do not leak raw values/classes/payloads/headers/tokens/paths/env
  - [x] invalid JSON diagnostics do not leak raw JSON payload

- [x] `framework/packages/core/foundation/tests/Contract/StableJsonDecoderUsesJsonLikeNormalizerContractTest.php`
  - [x] MUST verify that `StableJsonDecoder` delegates baseline decoded value normalization to `JsonLikeNormalizer`
  - [x] MUST verify that `StableJsonDecoder::decodeStable(...)` maps normalization-stage `JsonLikeNormalizationException` failures to stable `stable-json-*` failures
  - [x] MUST verify that `StableJsonDecoder::decodeStable(...)` maps conversion-stage `JsonLikeNormalizationException` failures to stable `stable-json-*` failures
  - [x] MUST verify that `StableJsonDecoder` rejects JSON object keys that cannot be safely represented as PHP string map keys
  - [x] MUST verify that ambiguous JSON object keys such as `"0"` fail with `stable-json-map-key-must-be-string`
  - [x] MUST verify that nested ambiguous JSON object keys fail with a safe path only
  - [x] MUST verify that decoder failures do not expose raw JSON payloads
  - [x] MUST verify that decoder failures do not expose raw rejected values
  - [x] MUST verify that decoder failures do not expose file paths
  - [x] MUST verify that decoder failures do not expose secrets
  - [x] MUST verify that decoder failures do not expose tokens
  - [x] MUST verify that decoder failures do not expose payload fragments
  - [x] MUST verify that decoder failures do not expose object class names
  - [x] MUST verify that decoder failures do not expose resource ids
  - [x] MUST verify that decoder failures do not expose environment-specific data

- [x] `framework/packages/core/foundation/tests/Contract/StableJsonSerializationRootShapeContractTest.php`
  - [x] MUST verify that `StableJsonEncoder::encodeStable([])` emits `[]` followed by a final LF
  - [x] MUST verify that `StableJsonEncoder::encodeStableMap([])` emits `{}` followed by a final LF
  - [x] MUST verify that `StableJsonEncoder::encodeStableList([])` emits `[]` followed by a final LF
  - [x] MUST verify that `StableJsonEncoder::encodeStableMap(...)` rejects non-map root values with `stable-json-root-map-required`
  - [x] MUST verify that `StableJsonEncoder::encodeStableList(...)` rejects non-list root values with `stable-json-root-list-required`
  - [x] MUST verify that `StableJsonDecoder::decodeStable('{}')` and `StableJsonDecoder::decodeStable('[]')` both normalize to PHP `[]` in generic mode
  - [x] MUST verify that `StableJsonDecoder::decodeStableMap('{}')` accepts an empty JSON object root
  - [x] MUST verify that `StableJsonDecoder::decodeStableMap('[]')` rejects a JSON array root with `stable-json-root-map-required`
  - [x] MUST verify that `StableJsonDecoder::decodeStableList('[]')` accepts an empty JSON array root
  - [x] MUST verify that `StableJsonDecoder::decodeStableList('{}')` rejects a JSON object root with `stable-json-root-list-required`
  - [x] MUST verify that root object/list distinction is enforced before `{}` and `[]` both become PHP `[]`
  - [x] MUST verify that nested empty object/list distinction is not asserted by this baseline serialization contract
  - [x] MUST verify that stable JSON encoder failures do not expose raw rejected values
  - [x] MUST verify that stable JSON encoder failures do not expose file paths
  - [x] MUST verify that stable JSON encoder failures do not expose secrets
  - [x] MUST verify that stable JSON encoder failures do not expose tokens
  - [x] MUST verify that stable JSON encoder failures do not expose payload fragments
  - [x] MUST verify that stable JSON encoder failures do not expose object class names
  - [x] MUST verify that stable JSON encoder failures do not expose resource ids
  - [x] MUST verify that stable JSON encoder failures do not expose environment-specific data
  - [x] MUST verify that stable JSON decoder failures do not expose raw JSON payloads
  - [x] MUST verify that stable JSON decoder failures do not expose raw rejected values
  - [x] MUST verify that stable JSON decoder failures do not expose file paths
  - [x] MUST verify that stable JSON decoder failures do not expose secrets
  - [x] MUST verify that stable JSON decoder failures do not expose tokens
  - [x] MUST verify that stable JSON decoder failures do not expose payload fragments
  - [x] MUST verify that stable JSON decoder failures do not expose object class names
  - [x] MUST verify that stable JSON decoder failures do not expose resource ids
  - [x] MUST verify that stable JSON decoder failures do not expose environment-specific data

- [x] `framework/packages/platform/worker/tests/Contract/WorkerCommandsUseCliContractsOnlyTest.php`
  - [x] asserts worker command classes implement `Coretsia\Contracts\Cli\Command\CommandInterface`
  - [x] asserts worker command classes use `InputInterface` / `OutputInterface`
  - [x] asserts worker command classes do not import `Coretsia\Platform\Cli\*`
  - [x] asserts worker command classes do not implement any platform/cli command contract or adapter
  - [x] asserts worker command classes do not echo/print/printf/var_dump/print_r/error_log
  - [x] asserts worker command classes do not use direct output sinks:
    - [x] `echo`
    - [x] `print`
    - [x] `printf`
    - [x] `fwrite(STDOUT)`
    - [x] `fwrite(STDERR)`
    - [x] `var_dump`
    - [x] `print_r`
    - [x] `error_log`

- [x] `framework/packages/platform/worker/tests/Contract/WorkerCommandMetadataConstantsTest.php`
  - [x] asserts `WorkerStartCommand::NAME === 'worker:start'`
  - [x] asserts `WorkerStopCommand::NAME === 'worker:stop'`
  - [x] asserts `WorkerStatusCommand::NAME === 'worker:status'`
  - [x] asserts all command names match canonical regex
  - [x] asserts `name()` returns `self::NAME`
  - [x] asserts `SUMMARY`, `GROUP`, `HIDDEN`, `MODE`, `ARGUMENTS`, and `OPTIONS` constants exist
  - [x] asserts no command metadata constant contains raw paths, endpoints, env values, secrets, or payloads

- [x] `framework/packages/platform/worker/tests/Contract/WorkerServiceProviderCliCommandTaggingTest.php`
  - [x] asserts worker provider tags worker commands with `ReservedTags::CLI_COMMAND`
  - [x] asserts tag metadata uses command class constants
  - [x] asserts tag metadata `name` equals command instance `name()`
  - [x] asserts tag metadata contains no `priority`
  - [x] asserts tag metadata contains only allowlisted keys
  - [x] asserts provider does not import `Coretsia\Platform\Cli\*`
  - [x] asserts provider does not build or resolve `CommandCatalog`
  - [x] provider registers `WorkerStartCommand` as a container service
  - [x] provider registers `WorkerStopCommand` as a container service
  - [x] provider registers `WorkerStatusCommand` as a container service
  - [x] provider tags `WorkerStartCommand` with `ReservedTags::CLI_COMMAND`
  - [x] provider tags `WorkerStopCommand` with `ReservedTags::CLI_COMMAND`
  - [x] provider tags `WorkerStatusCommand` with `ReservedTags::CLI_COMMAND`
  - [x] provider source imports `Coretsia\Foundation\Tag\ReservedTags`
  - [x] provider source does not contain raw string `'cli.command'`
  - [x] provider source does not import `Coretsia\Platform\Cli\*`
  - [x] tag metadata for `WorkerStartCommand` references:
    - [x] `WorkerStartCommand::NAME`
    - [x] `WorkerStartCommand::SUMMARY`
    - [x] `WorkerStartCommand::GROUP`
    - [x] `WorkerStartCommand::HIDDEN`
    - [x] `WorkerStartCommand::MODE`
    - [x] `WorkerStartCommand::ARGUMENTS`
    - [x] `WorkerStartCommand::OPTIONS`
  - [x] tag metadata for `WorkerStopCommand` references:
    - [x] `WorkerStopCommand::NAME`
    - [x] `WorkerStopCommand::SUMMARY`
    - [x] `WorkerStopCommand::GROUP`
    - [x] `WorkerStopCommand::HIDDEN`
    - [x] `WorkerStopCommand::MODE`
    - [x] `WorkerStopCommand::ARGUMENTS`
    - [x] `WorkerStopCommand::OPTIONS`
  - [x] tag metadata for `WorkerStatusCommand` references:
    - [x] `WorkerStatusCommand::NAME`
    - [x] `WorkerStatusCommand::SUMMARY`
    - [x] `WorkerStatusCommand::GROUP`
    - [x] `WorkerStatusCommand::HIDDEN`
    - [x] `WorkerStatusCommand::MODE`
    - [x] `WorkerStatusCommand::ARGUMENTS`
    - [x] `WorkerStatusCommand::OPTIONS`
  - [x] tag metadata contains only allowlisted keys:
    - [x] `name`
    - [x] `summary`
    - [x] `group`
    - [x] `hidden`
    - [x] `mode`
    - [x] `arguments`
    - [x] `options`
  - [x] tag metadata does not contain:
    - [x] `priority`
    - [x] closures
    - [x] objects
    - [x] resources
    - [x] raw config values
    - [x] raw socket paths
    - [x] raw TCP endpoints
    - [x] absolute paths
    - [x] env values
    - [x] secrets
    - [x] tokens
    - [x] payloads
  - [x] registering provider does not start workers
  - [x] registering provider does not call `proc_open`
  - [x] registering provider does not open sockets
  - [x] registering provider does not write pid/state/socket/stop files
  - [x] registering provider does not parse CLI input
  - [x] registering provider does not build or resolve `CommandCatalog`

- [x] `framework/packages/platform/worker/tests/Unit/ApplicationWorkerTest.php`
  - [x] verifies `runOne()` obtains task work only through `TaskFactoryInternalInterface`
  - [x] verifies `runOne()` passes `WorkerPoolSpec::taskType()` as UoW type to `KernelRuntimeInterface`
  - [x] verifies task metrics use `operation_id` from task work, not `WorkerPoolSpec::taskType()` directly
  - [x] verifies task metrics use only labels:
    - [x] `operation`
    - [x] `outcome`
  - [x] verifies successful task records `worker.task_total` with outcome `success`
  - [x] verifies failed task records `worker.task_total` with outcome `failure`
  - [x] verifies task duration is measured with Foundation `Stopwatch`
  - [x] verifies tracer failures do not change task control-flow semantics
  - [x] verifies meter failures do not change task control-flow semantics
  - [x] verifies context reads are limited to:
    - [x] `ContextKeys::CORRELATION_ID`
    - [x] `ContextKeys::UOW_ID`
    - [x] `ContextKeys::UOW_TYPE`
  - [x] verifies worker does not write context directly
  - [x] verifies worker does not emit stdout/stderr

- [x] `framework/packages/platform/worker/tests/Unit/WorkerPoolSpecTest.php`
  - [x] creates spec from complete default-like worker config
  - [x] rejects missing required top-level keys
  - [x] rejects missing `worker.control.transport`
  - [x] rejects missing `worker.tcp.host`
  - [x] rejects missing `worker.tcp.port`
  - [x] rejects non-bool `worker.enabled`
  - [x] rejects non-int `worker.workers`
  - [x] rejects `worker.workers <= 0`
  - [x] rejects non-int `worker.max_requests`
  - [x] rejects `worker.max_requests <= 0`
  - [x] rejects unsupported `worker.task_type`
  - [x] rejects unsupported `worker.driver`
  - [x] rejects unsupported `worker.control.transport`
  - [x] rejects non-int `worker.tcp.port`
  - [x] rejects `worker.tcp.port < 1`
  - [x] rejects `worker.tcp.port > 65535`
  - [x] rejects non-int `worker.stop_timeout_ms`
  - [x] rejects `worker.stop_timeout_ms < 0`
  - [x] preserves path values as configured relative strings
  - [x] rejects absolute Unix paths
  - [x] rejects absolute Windows paths
  - [x] rejects paths with `..` segments
  - [x] rejects paths with `skeleton/` prefix
  - [x] resolves `worker.driver=auto` to `pcntl` when explicit capabilities allow pcntl
  - [x] resolves `worker.driver=auto` to `proc` when pcntl is unavailable
  - [x] resolves `worker.driver=auto` to `proc` on Windows
  - [x] preserves explicit `worker.driver=pcntl`
  - [x] preserves explicit `worker.driver=proc`
  - [x] resolves `worker.control.transport=auto` to `unix` when resolved driver is `pcntl` and unix sockets are supported
  - [x] resolves `worker.control.transport=auto` to `tcp` when unix sockets are unsupported
  - [x] resolves `worker.control.transport=auto` to `tcp` when resolved driver is `proc`
  - [x] preserves explicit `worker.control.transport=unix`
  - [x] preserves explicit `worker.control.transport=tcp`
  - [x] exposes deterministic unix endpoint identifier for hashing
  - [x] exposes deterministic tcp endpoint identifier for hashing
  - [x] MUST NOT rely on host pcntl or unix socket support in tests
  - [x] MUST NOT rely on filesystem state
  - [x] MUST NOT write files
  - [x] MUST NOT emit stdout/stderr

- [x] `framework/packages/platform/worker/tests/Unit/WorkerPoolStateTest.php`
  - [x] verifies valid `WorkerPoolState` construction
  - [x] verifies `version()` always returns `1`
  - [x] verifies `toArray()` returns exact stable key order and values:
    - [x] `version`
    - [x] `pid`
    - [x] `worker_count`
    - [x] `driver_requested`
    - [x] `driver`
    - [x] `control_transport_requested`
    - [x] `control_transport`
    - [x] `endpoint_hash`
  - [x] rejects `pid <= 0`
  - [x] rejects `workerCount <= 0`
  - [x] rejects requested driver outside `auto|pcntl|proc`
  - [x] rejects resolved driver outside `pcntl|proc`
  - [x] rejects requested control transport outside `auto|unix|tcp`
  - [x] rejects resolved control transport outside `unix|tcp`
  - [x] rejects `endpoint_hash` when it contains uppercase hex characters
  - [x] rejects `endpoint_hash` when it is not exactly 64 lowercase hex characters
  - [x] MUST NOT rely on filesystem state
  - [x] MUST NOT write files
  - [x] MUST NOT emit stdout/stderr

- [x] `framework/packages/platform/worker/tests/Unit/WorkerStateStoreStateFactoryTest.php`
  - [x] creates `WorkerPoolState` from `WorkerPoolSpec`
  - [x] computes unix endpoint hash from `unix:` + configured socket path exactly
  - [x] computes tcp endpoint hash from `tcp:` + host + `:` + resolved port
  - [x] MUST NOT read files
  - [x] MUST NOT write files
  - [x] MUST NOT emit stdout/stderr

- [x] `framework/packages/platform/worker/tests/Unit/ProcWorkerManagerDriverSupportTest.php`
  - [x] verifies `name()` returns `proc`
  - [x] verifies driver supports specs resolved to `proc`
  - [x] verifies driver does not support specs resolved to `pcntl`
  - [x] verifies `worker.driver=auto` after fallback resolution to `proc` is supported
  - [x] verifies constructor rejects invalid worker command vectors deterministically
  - [x] MUST NOT call `proc_open`
  - [x] MUST NOT require pcntl
  - [x] MUST NOT execute task payloads
  - [x] MUST NOT depend on `platform/cli`
  - [x] MUST NOT depend on `platform/http`
  - [x] MUST NOT emit stdout/stderr

- [x] `framework/packages/platform/worker/tests/Unit/WorkerServiceProviderTaskFactorySelectionTest.php`
  - [x] queue mode resolves `QueueTaskFactory`
  - [x] queue mode does not resolve `HttpTaskFactory`
  - [x] http mode resolves `HttpTaskFactory`
  - [x] unsupported task type fails deterministically
  - [x] MUST NOT start workers
  - [x] MUST NOT fork
  - [x] MUST NOT call `proc_open`
  - [x] MUST NOT open sockets
  - [x] MUST NOT write files
  - [x] MUST NOT depend on `platform/cli`
  - [x] MUST NOT depend on `platform/http`
  - [x] MUST NOT emit stdout/stderr

- [x] `framework/packages/platform/worker/tests/Integration/WorkerStateStoreFilesystemTest.php`
  - [x] writes stable JSON using the cemented schema
  - [x] written JSON has LF line endings and final newline
  - [x] reads valid state into `WorkerPoolState`
  - [x] rejects unreadable or missing state
  - [x] rejects invalid JSON
  - [x] rejects non-map JSON
  - [x] rejects missing required schema keys
  - [x] rejects extra schema keys
  - [x] rejects `version !== 1`
  - [x] rejects invalid `endpoint_hash`
  - [x] rejects invalid resolved driver
  - [x] rejects invalid resolved control transport
  - [x] failure messages do not include absolute paths
  - [x] failure messages do not include raw state file path
  - [x] verifies written JSON ends with final LF
  - [x] MUST NOT emit stdout/stderr

- [x] `framework/packages/platform/worker/tests/Integration/WorkerSocketServerTransportTest.php`
  - [x] verifies `unix` control transport address handling without exposing raw socket path in public diagnostics
  - [x] verifies `tcp` control transport address handling without exposing raw host/port in public diagnostics
  - [x] verifies start request frame is payload-free
  - [x] verifies stop request frame is payload-free
  - [x] verifies status request frame is payload-free
  - [x] verifies health request frame is payload-free
  - [x] converts bind/listen failures into `WorkerCommunicationFailedException::communicationFailed()`
  - [x] converts connect failures into `WorkerCommunicationFailedException::communicationFailed()`
  - [x] converts accept/read/write protocol failures into `WorkerCommunicationFailedException::communicationFailed()`
  - [x] MUST NOT use task payload fixtures
  - [x] MUST NOT require real worker process execution
  - [x] MUST NOT depend on `platform/cli`
  - [x] MUST NOT depend on `platform/http`
  - [x] MUST NOT emit stdout/stderr

- [x] `framework/packages/platform/worker/tests/Integration/ProcWorkerManagerDriverProcessTest.php`
  - [x] verifies start uses `proc_open` path without requiring pcntl
  - [x] verifies start launches exactly `worker.workers` child processes
  - [x] verifies deterministic worker command argument order
  - [x] verifies command includes `worker.max_requests`
  - [x] verifies start returns `WorkerPoolState` with resolved driver `proc`
  - [x] verifies state persistence delegates to `WorkerStateStore`
  - [x] verifies stop writes stop flag
  - [x] verifies stop sends payload-free stop request through `WorkerSocketServer`
  - [x] verifies process start failure maps to `WorkerStartFailedException::startFailed()`
  - [x] verifies communication failure maps to `WorkerCommunicationFailedException::communicationFailed()`
  - [x] MUST NOT require pcntl
  - [x] MUST NOT execute task payloads
  - [x] MUST NOT depend on `platform/cli`
  - [x] MUST NOT depend on `platform/http`
  - [x] MUST NOT emit stdout/stderr

- [x] `framework/packages/platform/worker/tests/Contract/WorkerStateJsonSchemaContractTest.php`
  - [x] written JSON does not include `started_at`
  - [x] written JSON does not include `env`
  - [x] written JSON does not include raw socket path
  - [x] written JSON does not include raw tcp host/port as endpoint data
  - [x] written JSON does not include absolute paths
  - [x] written JSON has exact schema keys:
    - [x] `version`
    - [x] `pid`
    - [x] `worker_count`
    - [x] `driver_requested`
    - [x] `driver`
    - [x] `control_transport_requested`
    - [x] `control_transport`
    - [x] `endpoint_hash`
  - [x] `version` is exactly `1`
  - [x] endpoint hash is lowercase hex sha256
  - [x] MUST NOT emit stdout/stderr
  - [x] asserts no `/home/`, `/Users/`, `(?i)\b[A-Z]:(\\|/)`, `\\server\share` patterns
  - [x] same `WorkerPoolState` + same `WorkerPoolSpec` produce identical JSON bytes
  - [x] key order remains stable
  - [x] final LF remains stable

- [x] `framework/packages/platform/worker/tests/Contract/WorkerPoolStateSchemaContractTest.php`
  - [x] verifies `toArray()` returns exact stable key order:
    - [x] `version`
    - [x] `pid`
    - [x] `worker_count`
    - [x] `driver_requested`
    - [x] `driver`
    - [x] `control_transport_requested`
    - [x] `control_transport`
    - [x] `endpoint_hash`
  - [x] verifies `toArray()` does not include unsafe state fields:
    - [x] timestamps
    - [x] env
    - [x] raw socket path
    - [x] raw tcp host/port
    - [x] absolute paths
    - [x] payloads
    - [x] headers
    - [x] tokens
  - [x] MUST NOT rely on filesystem state
  - [x] MUST NOT write files
  - [x] MUST NOT emit stdout/stderr

- [x] `framework/packages/platform/worker/tests/Contract/WorkerPoolSpecConfigContractTest.php`
  - [x] config-derived paths MUST remain relative
  - [x] absolute Unix paths MUST be rejected
  - [x] absolute Windows paths MUST be rejected
  - [x] `..` path segments MUST be rejected
  - [x] `skeleton/` path prefix MUST be rejected
  - [x] TCP port `0` MUST be rejected
  - [x] float config values MUST be rejected
  - [x] endpoint identifiers MUST be deterministic and hash-only for public output
  - [x] tests MUST NOT depend on host pcntl or unix socket support
  - [x] tests MUST NOT rely on filesystem state
  - [x] tests MUST NOT write files
  - [x] MUST NOT emit stdout/stderr
  - [x] rejects `/home/...`, `/Users/...`, `(?i)\b[A-Z]:(\\|/)`, `\\server\share` patterns

- [x] `framework/packages/platform/worker/tests/Contract/WorkerSocketProtocolSafetyContractTest.php`
  - [x] verifies only allowed control operations are accepted:
    - [x] `start`
    - [x] `stop`
    - [x] `status`
    - [x] `health`
  - [x] rejects unknown control operation frames
  - [x] rejects empty frames
  - [x] rejects oversized frames
  - [x] rejects frames containing control characters
  - [x] rejects frames that look like payload transport
  - [x] verifies start summary contains only safe fields:
    - [x] `pid`
    - [x] `worker_count`
    - [x] `driver`
    - [x] `control_transport`
    - [x] `endpoint_hash`
  - [x] verifies status summary contains only safe fields:
    - [x] `pid`
    - [x] `worker_count`
    - [x] `driver`
    - [x] `control_transport`
    - [x] `endpoint_hash`
  - [x] verifies summary frames do not contain raw socket path
  - [x] verifies summary frames do not contain raw tcp host/port
  - [x] verifies summary frames do not contain absolute paths
  - [x] verifies summary frames do not contain payloads
  - [x] verifies summary frames do not contain headers
  - [x] verifies summary frames do not contain tokens
  - [x] verifies endpoint identifiers are exposed only as lowercase hex sha256 hashes in safe summaries
  - [x] verifies exception messages do not expose absolute paths
  - [x] verifies exception messages do not expose socket paths
  - [x] verifies exception messages do not expose tcp endpoints
  - [x] verifies exception messages do not expose payload fragments
  - [x] verifies exception messages do not expose headers
  - [x] verifies exception messages do not expose tokens
  - [x] MUST NOT use task payload fixtures
  - [x] MUST NOT require real worker process execution
  - [x] MUST NOT depend on `platform/cli`
  - [x] MUST NOT depend on `platform/http`
  - [x] MUST NOT emit stdout/stderr

- [x] `framework/packages/platform/worker/tests/Contract/ProcWorkerManagerDriverSafetyContractTest.php`
  - [x] verifies command does not include raw socket path
  - [x] verifies command does not include raw tcp host/port
  - [x] verifies command does not include task payloads
  - [x] verifies command does not include headers
  - [x] verifies command does not include tokens
  - [x] verifies driver does not write `worker.state.json` directly
  - [x] verifies exception messages do not expose full command lines
  - [x] verifies exception messages do not expose absolute paths
  - [x] verifies exception messages do not expose env values
  - [x] verifies exception messages do not expose raw endpoints
  - [x] MUST NOT require pcntl
  - [x] MUST NOT execute task payloads
  - [x] MUST NOT depend on `platform/cli`
  - [x] MUST NOT depend on `platform/http`
  - [x] MUST NOT emit stdout/stderr

- [x] `framework/packages/platform/worker/tests/Unit/WorkerManagerLifecycleTest.php`
  - [x] driver selection by resolved `WorkerPoolSpec::driver()`
  - [x] start delegates to selected driver
  - [x] stop delegates to selected driver
  - [x] status delegates to selected driver
  - [x] unsupported selected driver maps to `WorkerStartFailedException::startFailed()`
  - [x] unsupported driver support maps to `WorkerStartFailedException::startFailed()`
  - [x] passes through:
    - [x] `WorkerNotRunningException`
    - [x] `WorkerStartFailedException`
    - [x] `WorkerForkFailedException`
    - [x] `WorkerCommunicationFailedException`
  - [x] wraps unknown `Throwable` into `WorkerStartFailedException::startFailed()`
  - [x] observability failures do not alter lifecycle result/failure semantics
  - [x] metric labels stay low-cardinality: only `status`
  - [x] log context stays safe

- [x] `framework/packages/platform/worker/tests/Integration/WorkerHandlesMultipleTasksSequentiallyTest.php`
  - [x] `ApplicationWorker::run(...)` processes multiple tasks sequentially
  - [x] task order is deterministic
  - [x] each task enters KernelRuntimeInterface separately
  - [x] each task gets its own UnitOfWork boundary
  - [x] loop stops through package-owned stop condition, not through CLI/catalog

- [x] `framework/packages/platform/worker/tests/Integration/MaxRequestsTriggersRecycleTest.php`
  - [x] worker loop exits after exactly `WorkerPoolSpec::maxRequests()`
  - [x] no extra task is processed after limit
  - [x] exit is deterministic and non-error
  - [x] no stdout/stderr
  - [x] no platform/cli

- [x] `framework/packages/platform/worker/tests/Fixtures/WorkerApp/config/modes/micro.php`
  - [x] fixture app MUST express module enable/disable through a mode override, not through `config/modules.php`

Add contract-style tests (Phase 0 aligned):
- [x] `framework/packages/platform/worker/tests/Contract/WorkerRuntimeDoesNotWriteToStdoutTest.php`
  - [x] mandatory contract lock because stdout/stderr direct writes are a hard policy ban for runtime source scope
  - [x] asserts no stdout/stderr sinks are used in runtime source scope (token-based scan; tests/fixtures excluded)
  - [x] no `echo`
  - [x] no `print`
  - [x] no `printf`
  - [x] no `var_dump`
  - [x] no `print_r`
  - [x] no `error_log`
  - [x] no `fwrite(STDOUT)`
  - [x] no `fwrite(STDERR)`
  - [x] no scan: `tests/`, `fixtures/`, `bin/coretsia-worker`

Add config contract tests (policy rails):
- [x] `framework/packages/platform/worker/tests/Contract/WorkerConfigSubtreeShapeContractTest.php`
  - [x] MUST fail if `config/worker.php` repeats root (`['worker'=>...]` is forbidden; subtree only)
  - [x] MUST fail if any `@*` key exists under returned subtree (any depth)
  - [x] `config/worker.php` returns worker subtree only
  - [x] returned array must not contain root key `worker`
  - [x] no key beginning with `@` exists at any depth
  - [x] defaults are deterministic scalar/list/map values
  - [x] no closures/objects/resources

- [x] `framework/packages/platform/worker/tests/Integration/WorkerHttpTaskRequiresRequestHandlerTest.php`
  - [x] asserts `WorkerStartCommand` invokes `RuntimeDriverGuard` through direct command invocation or package-local harness
  - [x] MUST NOT require `platform/cli` command catalog discovery
  - [x] covers the later DI/runtime failure only after runtime-driver/module compatibility has already passed
  - [x] asserts `worker.task_type=http` hard-fails deterministically when `Psr\Http\Server\RequestHandlerInterface` is not resolvable
  - [x] `worker.task_type=http`
  - [x] ModulePlan contains `platform.http`
  - [x] RuntimeDriverGuard passes
  - [x] command/harness does not use platform/cli catalog
  - [x] RequestHandlerInterface is missing/unresolvable
  - [x] failure is deterministic:
    - [x] `CORETSIA_WORKER_START_FAILED`
    - [x] `request_handler_missing` або `request_handler_unresolvable`, залежно від поточної реалізації

#### Modifies

- [x] deptrac expectations updated (if needed) — (exact path owned elsewhere; referenced only)

- [x] `framework/packages/core/foundation/src/Serialization/StableJsonEncoder.php`
  - [x] MUST provide `encodeMap()` / `encodeStableMap()` for callers that require JSON object root
  - [x] MUST provide `encodeList()` / `encodeStableList()` for callers that require JSON array root
  - [x] `encodeStableMap([])` MUST return `{}` + final LF
  - [x] `encodeStableList([])` MUST return `[]` + final LF

- [x] `framework/packages/core/contracts/src/Cli/Input/InputInterface.php`
  - [x] MUST expose deterministic parsed input access for package commands
  - [x] MUST remain independent from `platform/cli`
  - [x] MUST NOT expose parser implementation classes
  - [x] MUST expose raw tokens:
    - [x] `tokens(): array`
  - [x] MUST expose resolved command name:
    - [x] `commandName(): string`
  - [x] MUST expose positional arguments:
    - [x] `arguments(): array`
  - [x] MUST expose normalized options:
    - [x] `options(): array`
  - [x] MUST expose option lookup:
    - [x] `hasOption(string $name): bool`
    - [x] `option(string $name): string|bool|array|null`
  - [x] option values MUST be limited to:
    - [x] `string`
    - [x] `bool`
    - [x] `list<string>`
    - [x] `null`
  - [x] MUST NOT require command implementations to parse raw argv tokens directly

- [x] `framework/packages/core/contracts/src/Cli/Command/CommandInterface.php`
  - [x] MUST remain the only command execution contract used by package-contributed commands
  - [x] MUST NOT depend on `platform/cli`
  - [x] command name returned by `name()` MUST be stable and deterministic
  - [x] command names SHOULD follow canonical CLI command name regex:
    - [x] `\A[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)*\z`
  - [x] command classes SHOULD expose command identity constants:
    - [x] `public const string NAME`
    - [x] `public const string SUMMARY`
    - [x] `public const string GROUP`
  - [x] `name()` MUST return the same value as `self::NAME`
  - [x] provider tag metadata `name` MUST match `CommandInterface::name()`
  - [x] provider tag metadata MUST reference command class constants, not unrelated string literals

- [x] `framework/packages/core/contracts/src/Cli/Output/OutputInterface.php`
  - [x] MUST remain the only output abstraction used by package-contributed commands
  - [x] command implementations MUST NOT write stdout/stderr directly
  - [x] JSON payloads passed to `json(...)` MUST be intended as safe json-like payloads
  - [x] error messages passed to `error(...)` MUST be safe and secret-free
  - [x] final deterministic rendering and redaction policy remain owned by `platform/cli`

- [x] Contracts boundary:
  - [x] MUST NOT introduce `CommandDescriptor` into `core/contracts`
  - [x] MUST NOT introduce command catalog classes into `core/contracts`
  - [x] MUST NOT introduce CLI tag metadata schema validators into `core/contracts`
  - [x] `CommandDescriptor`, command catalog, metadata schema validation, reserved-name enforcement, and dispatch semantics remain owned by `platform/cli`

- [x] `docs/ssot/config-roots.md`
  - [x] add canonical registry row:
    - [x] `worker` | `platform/worker` | `framework/packages/platform/worker/config/worker.php` | `framework/packages/platform/worker/config/rules.php` | long-running worker runtime root
  - [x] once this row is added, the earlier “future reserved identifier: worker” note from `1.20.0` is considered resolved and MUST NOT remain as a second active source of truth
  - [x] historical “Initial Rows Introduced by This Epic” block removed to keep the registry table as the only active source of truth

- [x] `docs/adr/INDEX.md` — register:
  - [x] `docs/adr/ADR-0017-worker-manager-application-worker.md`

- [x] `docs/ssot/observability.md`
  - [x] register worker spans:
    - [x] `worker.process`
    - [x] `worker.task`
  - [x] register worker metrics:
    - [x] `worker.process_total`
    - [x] `worker.task_total`
    - [x] `worker.task_duration_ms`
  - [x] register label allowlist:
    - [x] `status`
    - [x] `operation`
    - [x] `outcome`
  - [x] MUST NOT allow worker id, pid, path, socket, endpoint, payload, exception class, or error reason as metric labels

- [x] `docs/roadmap/phase0/00_2-dependency-table.md`
  - [x] registers `platform/worker`
  - [x] declares direct compile-time deps: `core/contracts`, `core/foundation`, `core/kernel`
  - [x] does not declare external PSR packages
  - [x] does not declare forbidden deps: `platform/cli`, `platform/http`, `integrations/*`

#### Package skeleton (if type=package)

- [x] `framework/packages/platform/worker/composer.json`
  - [x] package name MUST be `coretsia/platform-worker`
  - [x] type MUST be `library`
  - [x] MUST require runtime packages:
    - [x] `psr/container`
    - [x] `psr/log`
    - [x] `psr/http-message`
    - [x] `psr/http-server-handler`
  - [x] autoload PSR-4:
    - [x] `Coretsia\\Platform\\Worker\\` => `src/`
  - [x] autoload-dev PSR-4:
    - [x] `Coretsia\\Platform\\Worker\\Tests\\` => `tests/`
  - [x] composer extra MUST define:
    - [x] `kind = runtime`
    - [x] `moduleId = platform.worker`
    - [x] `moduleClass = Coretsia\\Platform\\Worker\\Module\\WorkerModule`
    - [x] providers:
      - [x] `Coretsia\\Platform\\Worker\\Provider\\WorkerServiceProvider`
    - [x] defaultsConfigPath = `config/worker.php`
    - [x] composer extra runtime module dependency:
      - [x] `requires = ["core.kernel"]`
- [x] `framework/packages/platform/worker/src/Module/WorkerModule.php`
- [x] `framework/packages/platform/worker/src/Provider/WorkerServiceProvider.php`
- [x] `framework/packages/platform/worker/config/worker.php`
- [x] `framework/packages/platform/worker/config/rules.php`
- [x] `framework/packages/platform/worker/README.md`

#### Configuration (keys + defaults)

- [x] Files:
  - [x] `framework/packages/platform/worker/config/worker.php`
- [x] Keys (dot):
  - [x] `worker.enabled` = false
  - [x] `worker.workers` = 4
  - [x] `worker.max_requests` = 1000
  - [x] `worker.task_type` = "queue"  # allowed: "http" | "queue"
  - [x] `worker.socket_path` = "var/tmp/worker.sock"
  - [x] `worker.driver` = "auto" # "pcntl"|"proc"
  - [x] `worker.proc.command`
    - [x] owned by `platform/worker`
    - [x] default is `[]`
    - [x] argv list, not shell string
    - [x] app/skeleton preset may override with concrete child-process entrypoint
  - [x] `worker.control.transport` = "auto" # "unix"|"tcp"
  - [x] `worker.tcp.host` = "127.0.0.1"
  - [x] `worker.tcp.port` = 9327
  - [x] `worker.state_path` = "var/tmp/worker.state.json"
  - [x] `worker.stop_flag_path` = "var/tmp/worker.stop"
  - [x] `worker.stop_timeout_ms` = 3000
- [x] Rules:
  - [x] `framework/packages/platform/worker/config/rules.php` enforces shape
  - [x] Reserved namespace parity (cemented):
    - [x] any key starting with `@` under `worker` subtree (any depth) MUST hard-fail
  - [x] No absolute paths (cemented):
    - [x] `worker.socket_path`, `worker.state_path`, `worker.stop_flag_path` MUST be relative paths (reject POSIX/Windows/UNC absolute forms deterministically)
  - [x] No floats (cemented):
    - [x] numeric keys under `worker.*` MUST be `int` only (reject float/NaN/INF)
  - [x] Explicit enum / bounds rules (cemented):
    - [x] `worker.enabled` MUST be `bool`
    - [x] `worker.workers` MUST be `int > 0`
    - [x] `worker.max_requests` MUST be `int > 0`
    - [x] `worker.task_type` MUST be exactly one of: `http|queue`
    - [x] `worker.driver` MUST be exactly one of: `auto|pcntl|proc`
    - [x] `worker.control.transport` MUST be exactly one of: `auto|unix|tcp`
    - [x] `worker.tcp.host` MUST be a non-empty string
    - [x] `worker.tcp.port` MUST be an `int` in range `1..65535`
    - [x] `worker.stop_timeout_ms` MUST be `int >= 0`
    - [x] rules MUST declare `worker.proc`
    - [x] rules MUST reject unknown `worker.proc.*` keys
    - [x] rules MUST declare `worker.proc.command`
    - [x] `worker.proc.command` MUST be a list
    - [x] `worker.proc.command` items MUST be non-empty strings
    - [x] empty `worker.proc.command` list MUST pass package default validation

- [x] Auto-resolution rules (single-choice; cemented):
  - [x] `worker.driver=auto` resolves deterministically:
    - [x] `pcntl` when `pcntl_fork` is available and the platform is not Windows
    - [x] otherwise `proc`
  - [x] `worker.control.transport=auto` resolves deterministically:
    - [x] `unix` when the resolved driver is `pcntl` and unix domain sockets are supported
    - [x] otherwise `tcp`
  - [x] when the resolved transport is `tcp`, `worker.tcp.port` MUST be an explicit fixed port in range `1..65535`
  - [x] `worker.tcp.port = 0` is forbidden because it makes endpoint identity and `worker.state.json` non-deterministic across runs

- [x] Worker proc child-process command vector policy
  - [x] owned by `platform/worker`
  - [x] config key MUST be under `worker.*`
  - [x] MUST introduce `worker.proc.command`
  - [x] `worker.proc.command` MUST be an argv list, not a shell string
  - [x] package default MUST be non-empty
  - [x] package default MUST point to worker-owned child launcher
  - [x] package default MUST NOT point to `framework/bin/coretsia`
  - [x] package default MUST NOT require `platform/cli`
  - [x] package default MAY use worker-owned `@php` token
  - [x] `@php` MUST be expanded by `WorkerServiceFactory`, not by `WorkerServiceProvider`
  - [x] `WorkerServiceProvider` MUST NOT invent or mutate proc command vector
  - [x] `WorkerServiceFactory::procWorkerCommand(...)` MUST normalize `worker.proc.command`
  - [x] `WorkerServiceFactory::procWorkerCommand(...)` MUST reject empty command vector
  - [x] runtime normalization MUST reject argv parts with leading/trailing whitespace
  - [x] runtime normalization MUST reject argv parts with control characters
  - [x] `ProcWorkerManagerDriver` MUST receive an already-normalized non-empty argv vector
  - [x] `platform/worker` MUST NOT depend on `platform/cli`
  - [x] proc child process entrypoint MUST be package/runtime-owned, not CLI catalog-owned

#### Wiring / DI tags (when applicable)

- Tags introduced (this epic is the OWNER):
  - N/A (uses existing reserved tag `cli.command`, owned by `platform/cli`)

- [x] ServiceProvider wiring evidence:
  - [x] `ContainerBuilder::factory(...)` MUST register factory definitions without executing them during provider registration
  - [x] factory closures MUST execute only during `Container::get(...)` resolution
  - [x] thin DI registration layer
  - [x] tags introduced by this epic: N/A
    - [x] uses existing reserved tag `cli.command`
    - [x] `cli.command` is owned by `platform/cli`
    - [x] `platform/worker` is only a non-owner contributor
  - [x] Current runtime wiring phase:
    - [x] MUST register `WorkerServiceFactory`
    - [x] MUST register `WorkerPoolSpec`
    - [x] MUST register `WorkerStateStore`
    - [x] MUST register `WorkerSocketServer`
    - [x] MUST register `QueueTaskFactory`
    - [x] MUST register `HttpTaskFactory`
    - [x] MUST register `TaskFactoryInternalInterface`
    - [x] MUST register `ApplicationWorker`
    - [x] `TaskFactoryInternalInterface` MUST be selected lazily from `WorkerPoolSpec`
    - [x] queue mode MUST NOT resolve `HttpTaskFactory`
  - [x] Current provider safety invariants:
    - [x] MUST NOT inspect `worker.*` config values directly
    - [x] MUST derive artifact paths only from `BootstrapConfig` through safe normalized app target
    - [x] MUST NOT perform CLI command discovery
    - [x] MUST NOT depend on `platform/cli`
    - [x] MUST NOT import `Coretsia\Platform\Cli\*`
    - [x] MUST NOT instantiate Noop logger/meter/tracer directly
    - [x] MUST NOT start workers during provider registration
    - [x] MUST NOT fork during provider registration
    - [x] MUST NOT call `proc_open()` during provider registration
    - [x] MUST NOT open sockets during provider registration
    - [x] MUST NOT write runtime files during provider registration
  - [x] Deferred process-manager wiring phase:
    - [x] MUST register `WorkerManager` after child-process command vector policy is cemented
    - [x] MUST register `PcntlWorkerManagerDriver`
    - [x] MUST register `ProcWorkerManagerDriver`
    - [x] MUST NOT invent a deterministic proc worker command argv vector inside the provider before the policy is cemented
  - [x] Deferred worker command registration phase, after CLI command contract hardening:
    - [x] MUST register `WorkerStartCommand` as a container service
    - [x] MUST register `WorkerStopCommand` as a container service
    - [x] MUST register `WorkerStatusCommand` as a container service
    - [x] MUST tag `WorkerStartCommand` with `ReservedTags::CLI_COMMAND`
    - [x] MUST tag `WorkerStopCommand` with `ReservedTags::CLI_COMMAND`
    - [x] MUST tag `WorkerStatusCommand` with `ReservedTags::CLI_COMMAND`
    - [x] MUST use `Coretsia\Foundation\Tag\ReservedTags::CLI_COMMAND`
    - [x] MUST NOT define a new worker-owned CLI command tag
    - [x] MUST NOT own CLI command metadata schema
    - [x] MUST NOT validate CLI command metadata schema
    - [x] MUST NOT build or resolve `CommandCatalog`
    - [x] MUST NOT depend on `platform/cli`
    - [x] MUST NOT import `Coretsia\Platform\Cli\*`
    - [x] MUST NOT perform CLI command discovery
    - [x] MUST NOT parse CLI input
    - [x] MUST NOT inspect runtime command options
    - [x] MUST NOT start workers during provider registration
  - [x] Worker command tag metadata MUST reference command class constants:
    - [x] `WorkerStartCommand::NAME`
    - [x] `WorkerStartCommand::SUMMARY`
    - [x] `WorkerStartCommand::GROUP`
    - [x] `WorkerStartCommand::HIDDEN`
    - [x] `WorkerStartCommand::MODE`
    - [x] `WorkerStartCommand::ARGUMENTS`
    - [x] `WorkerStartCommand::OPTIONS`
    - [x] `WorkerStopCommand::NAME`
    - [x] `WorkerStopCommand::SUMMARY`
    - [x] `WorkerStopCommand::GROUP`
    - [x] `WorkerStopCommand::HIDDEN`
    - [x] `WorkerStopCommand::MODE`
    - [x] `WorkerStopCommand::ARGUMENTS`
    - [x] `WorkerStopCommand::OPTIONS`
    - [x] `WorkerStatusCommand::NAME`
    - [x] `WorkerStatusCommand::SUMMARY`
    - [x] `WorkerStatusCommand::GROUP`
    - [x] `WorkerStatusCommand::HIDDEN`
    - [x] `WorkerStatusCommand::MODE`
    - [x] `WorkerStatusCommand::ARGUMENTS`
    - [x] `WorkerStatusCommand::OPTIONS`
  - [x] Worker command tag metadata MUST contain only allowlisted keys:
    - [x] `name`
    - [x] `summary`
    - [x] `group`
    - [x] `hidden`
    - [x] `mode`
    - [x] `arguments`
    - [x] `options`
  - [x] Worker command tag metadata MUST NOT contain:
    - [x] `priority`
    - [x] closures
    - [x] objects
    - [x] resources
    - [x] raw config values
    - [x] raw socket paths
    - [x] raw TCP endpoints
    - [x] absolute paths
    - [x] env values
    - [x] secrets
    - [x] tokens
    - [x] payloads
  - [x] CLI ownership boundaries:
    - [x] command-name metadata/schema for `cli.command` is owned only by `platform/cli`
    - [x] this epic MUST NOT define a competing `cli.command` meta contract
    - [x] this epic MAY contribute worker command services through `ReservedTags::CLI_COMMAND`
    - [x] this epic MUST NOT assert full command catalog discovery
    - [x] this epic MUST NOT assert full binary dispatch through `platform/cli`
    - [x] full discovery/dispatch assertions for worker commands belong to `2.30.0 Platform CLI`
  - [x] WorkerStartCommand manager resolution safety:
    - [x] resolving `WorkerStartCommand` MUST NOT resolve `WorkerManager`
    - [x] resolving `WorkerStartCommand` MUST NOT resolve process drivers
    - [x] resolving `WorkerStartCommand` MUST NOT resolve `ApplicationWorker`
    - [x] resolving `WorkerStartCommand` MUST NOT resolve `TaskFactoryInternalInterface`
    - [x] resolving `WorkerStartCommand` MUST NOT resolve `WorkerPoolSpec`
    - [x] `WorkerManager` MUST be resolved only after disabled-worker short-circuit and RuntimeDriverGuard checks in `WorkerStartCommand::run()`

#### Artifacts / outputs (if applicable)

- [x] Writes:
  - [x] `skeleton/var/tmp/worker.sock` — only when resolved `worker.control.transport = unix`
    - [x] MUST be created only for resolved unix control transport
    - [x] MUST NOT be created for resolved tcp control transport
    - [x] raw socket path MUST NOT be exposed in public diagnostics
  - [x] `skeleton/var/tmp/worker.state.json`
    - [x] serialization MUST be produced via `Coretsia\Foundation\Serialization\StableJsonEncoder`
    - [x] Direct `json_encode(...)` usage is FORBIDDEN for this artifact unless it is wrapped to provide identical canonical behavior
    - [x] MUST NOT contain secrets; store only redacted/hashed endpoint identifiers
    - [x] MUST have a cemented schema:
      - [x] `version` = 1
      - [x] `pid` (int)
      - [x] `worker_count` (int)
      - [x] `driver_requested` ("auto"|"pcntl"|"proc")
      - [x] `driver` ("pcntl"|"proc") — RESOLVED driver
      - [x] `control_transport_requested` ("auto"|"unix"|"tcp")
      - [x] `control_transport` ("unix"|"tcp") — RESOLVED transport
      - [x] `endpoint_hash` (string) — lowercase hex sha256 of canonical endpoint identifier
    - [x] `started_at` is FORBIDDEN
    - [x] `env` is FORBIDDEN
    - [x] JSON encoding MUST be stable:
      - [x] key ordering MUST be deterministic at all nesting levels
      - [x] output MUST be LF-only and end with final newline
    - [x] The file MUST NOT contain raw socket path, raw tcp host/port, absolute paths, tokens, payloads
  - [x] `skeleton/var/tmp/worker.stop`
    - [x] MUST be used only as a graceful stop marker
    - [x] MUST NOT contain payloads, config dumps, env values, headers, tokens, or secrets
- [x] Reads:
  - [x] `skeleton/var/cache/<appTarget>/container.php` (optional)

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Context & UoW

- [x] Context reads:
  - [x] canonical context key value equivalent to `ContextKeys::CORRELATION_ID` (`correlation_id`)
  - [x] canonical context key value equivalent to `ContextKeys::UOW_ID` (`uow_id`)
  - [x] canonical context key value equivalent to `ContextKeys::UOW_TYPE` (`uow_type`)
  - [x] `platform/worker` reads them only through `ContextAccessorInterface`
  - [x] `platform/worker` does not import Foundation-owned `ContextKeys`
- [x] Context writes:
  - N/A directly in `platform/worker`
  - [x] worker passes the UoW type (`http|queue`) into `Coretsia\Contracts\Runtime\KernelRuntimeInterface`; KernelRuntime owns base `ContextStore` writes
- [x] Reset discipline:
  - [x] each task is executed as a separate UoW via `Coretsia\Contracts\Runtime\KernelRuntimeInterface`
  - [x] after each task, reset happens only through the standard KernelRuntime flow:
    - [x] begin → hooks → task → after → `ResetOrchestrator::resetAll()`
  - [x] worker MUST NOT know or enumerate the reset discovery tag
  - [x] worker MUST NOT call `ResetOrchestrator::resetAll()` directly
  - [x] worker enablement controls only the worker runtime loop
  - [x] it MUST NOT disable or redefine Foundation reset infrastructure

#### Observability (policy-compliant)

- [x] Spans:
  - [x] `worker.process` (attrs: pid, outcome)
  - [x] `worker.task` (attrs: operation, outcome)
- [x] Metrics:
  - [x] `worker.process_total` (labels: `status`)
  - [x] `worker.task_total` (labels: `operation`, `outcome`)
  - [x] `worker.task_duration_ms` (labels: `operation`, `outcome`)
- [x] Logs:
  - [x] start/stop/status summary only; no payload; socket path hashed

- [x] Label/attribute normalization (MUST):
  - [x] resolved task operation id → metric label `operation` and span attribute `operation`
  - [x] resolved task type → ContextStore key `uow_type` ONLY (MUST NOT be a metric label)

- [x] Redaction reminders:
  - [x] No payload logging anywhere (stdout/stderr/log files/socket protocol).
  - [x] Any socket/control endpoint info MUST be redacted/hashed in logs/state dumps.

- [x] Worker observability dependencies MUST be injected through public ports/interfaces plus Foundation `Stopwatch`.
  - [x] worker services MUST receive logger/tracer/meter/stopwatch from DI
  - [x] worker services MUST NOT instantiate Noop logger/meter/tracer implementations directly
  - [x] worker services MUST NOT construct observability adapters directly
  - [x] logger/meter/tracer failures MUST be caught
  - [x] logger/meter/tracer failures MUST NOT change worker control-flow semantics
  - [x] spans MUST use names registered in `docs/ssot/observability.md`
  - [x] metrics MUST use names registered in `docs/ssot/observability.md`
  - [x] metric labels MUST stay within allowlist:
    - [x] `status`
    - [x] `operation`
    - [x] `outcome`
  - [x] worker id and pid MAY be span attributes only if allowed by observability policy
  - [x] worker id and pid MUST NOT be metric labels
  - [x] path/socket/endpoint/payload/exception class/error reason MUST NOT be metric labels
  - [x] logs MUST contain summary only
  - [x] logs MUST NOT contain payloads, raw endpoints, absolute paths, config dumps, env values, headers, tokens, or stack traces

#### Errors

- [x] Exceptions introduced:
  - [x] `Coretsia\Platform\Worker\Exception\WorkerException` — abstract/base package exception
  - [x] `Coretsia\Platform\Worker\Exception\WorkerStartFailedException` — errorCode `CORETSIA_WORKER_START_FAILED`
  - [x] `Coretsia\Platform\Worker\Exception\WorkerForkFailedException` — errorCode `CORETSIA_WORKER_FORK_FAILED`
  - [x] `Coretsia\Platform\Worker\Exception\WorkerCommunicationFailedException` — errorCode `CORETSIA_WORKER_COMMUNICATION_FAILED`

- [x] Runtime driver conflicts / invalid runtime composition:
  - [x] MUST be enforced by `Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard` (owner: guard epic; SSoT: 1.260.0).
  - [x] `platform/worker` MUST NOT introduce its own “driver conflict” error codes to avoid drift.
  - [x] On guard failure, worker:start MUST surface the guard’s deterministic codes (single-choice; cemented by SSoT):
    - [x] `CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT`
    - [x] `CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG`

#### Security / Redaction

- [x] MUST NOT leak:
  - [x] raw socket path
  - [x] raw TCP host/port
  - [x] absolute paths
  - [x] payloads
  - [x] headers
  - [x] tokens
  - [x] env values
  - [x] config dumps

- [x] Allowed:
  - [x] `pid`
  - [x] `worker_count`
  - [x] `driver`
  - [x] `control_transport`
  - [x] `operation`
  - [x] `outcome`
  - [x] `endpoint_hash`

- [x] Worker runtime MUST NOT write to stdout/stderr directly:
  - [x] forbidden: `echo`, `print`, `var_dump`, `print_r`, `printf`, `error_log`
  - [x] forbidden: `php://stdout|php://stderr|php://output`, `STDOUT|STDERR` writes

- [x] Allowed channels:
  - [x] Logs via `Psr\Log\LoggerInterface` only (redacted, no payload).
  - [x] CLI output only via the CLI output abstraction (no raw sinks).

- [x] Socket/control protocol MUST be payload-free:
  - [x] MUST NOT transmit raw task payloads over control channel
  - [x] MUST NOT log raw payloads ever
  - [x] operation identifiers MUST be safe strings; any identifiers that resemble paths/endpoints MUST be hashed

### Verification (TEST EVIDENCE) (MUST when applicable)

- [x] deterministic auto-resolution:
  - [x] `framework/packages/platform/worker/tests/Unit/WorkerPoolSpecTest.php`
- [x] tcp port zero is forbidden deterministically:
  - [x] `framework/packages/platform/worker/tests/Contract/WorkerPoolSpecConfigContractTest.php`
  - [x] `framework/packages/platform/worker/tests/Unit/WorkerPoolSpecTest.php`

#### Required policy tests matrix

- [x] worker-side UoW/reset boundary evidence → `framework/packages/platform/worker/tests/Integration/WorkerHandlesMultipleTasksSequentiallyTest.php`

#### Test harness / fixtures (when integration is needed)

- [x] Fixture app:
  - [x] `framework/packages/platform/worker/tests/Fixtures/WorkerApp/config/modes/micro.php`
  - [x] fixture app expresses module enable/disable through a mode override, never through `config/modules.php`

### Tests (MUST)

- Unit:
  - [x] `framework/packages/platform/worker/tests/Unit/WorkerManagerLifecycleTest.php`
- Contract:
  - [x] `framework/packages/platform/worker/tests/Contract/WorkerConfigSubtreeShapeContractTest.php`
  - [x] `framework/packages/platform/worker/tests/Contract/WorkerRuntimeDoesNotWriteToStdoutTest.php`
  - [x] `framework/packages/platform/worker/tests/Contract/WorkerExceptionsAreDeterministicContractTest.php`
    - [x] asserts each concrete worker exception exposes exact `errorCode()`
    - [x] asserts each exception exposes fixed `reason()`
    - [x] asserts public message is exactly `<ERROR_CODE>: <reason>`
    - [x] asserts previous throwable messages are not exposed
    - [x] asserts messages do not contain absolute paths, raw socket paths, tcp endpoints, payload fragments, headers, or tokens
  - [x] `framework/packages/platform/worker/tests/Contract/WorkerInternalInterfacesAreNotPublicApiContractTest.php`
    - [x] asserts worker internal interfaces are under `Coretsia\Platform\Worker\Internal\*`
    - [x] asserts internal interfaces contain `@internal`
    - [x] asserts README does not document them as extension points
    - [x] asserts composer extra does not export them
- Integration:
  - [x] `framework/packages/platform/worker/tests/Integration/WorkerHandlesMultipleTasksSequentiallyTest.php`
  - [x] `framework/packages/platform/worker/tests/Integration/MaxRequestsTriggersRecycleTest.php`
  - [x] `framework/packages/platform/worker/tests/Integration/WorkerHttpTaskRequiresRequestHandlerTest.php`
- Gates/Arch:
  - [x] deptrac expectations updated (if needed)

### DoD (MUST)

- [x] This epic creates worker command classes and contributes them via `cli.command`.
- [x] Full end-to-end `worker:start` dispatch through container-backed CLI discovery is owned by the platform/cli tag-discovery epic and is not a blocking deliverable of this epic unless that CLI mode already exists.
- [x] Deliverables complete (creates+modifies), paths exact
- [x] Preconditions satisfied (no forward references)
- [x] deps/forbidden respected (deptrac; no cycles)
- [x] Observability policy satisfied (names + label allowlist + redaction)
- [x] Tests: unit + contract + package-local integration tests pass
- [x] Full CLI binary/catalog dispatch tests are owned by `2.30.0 Platform CLI`
- [x] Docs updated:
  - [x] `docs/architecture/worker.md`
  - [x] `framework/packages/platform/worker/README.md`
  - [x] `docs/adr/ADR-0017-worker-manager-application-worker.md`
  - [x] `docs/ssot/config-roots.md`
  - [x] `docs/ssot/observability.md`

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

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- "docs/ssot/modes.md"
- "docs/ssot/artifacts.md"
- "docs/ssot/artifacts-and-fingerprint.md"
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

- Express fail lock (MUST):
  - `framework/packages/core/kernel/resources/modes/express.php` MUST require a Phase-2-only module that is intentionally absent from the boot-smoke fixture manifest.
  - For this epic, the missing required module MUST be:
    - `platform.http`
  - The boot-smoke fixture manifest MUST include enough modules for `micro` to pass:
    - `core.foundation`
    - `core.kernel`
    - `platform.cli`
  - The boot-smoke fixture manifest MUST NOT include:
    - `platform.http`
  - Therefore:
    - `micro` MUST resolve successfully
    - `express` MUST fail with `CORETSIA_MODULE_REQUIRED_MISSING`

#### Compile-time deps (deptrac-enforceable) (MUST)

N/A (tests/tooling)

### Entry points / integration points (MUST)

- CLI:
  - `composer test` executes these tests

### Deliverables (MUST)

#### Creates

- [x] `framework/packages/core/kernel/tests/Support/AppBuilder.php` — helper to boot fixture apps deterministically
  - [x] MUST support two explicit flows:
    - [x] `bootMicro()` / equivalent success path
    - [x] `bootExpressExpectingRequiredMissing()` / equivalent failure path
  - [x] Micro success path MUST:
    - [x] load framework default `micro` preset from `framework/packages/core/kernel/resources/modes/micro.php`
    - [x] resolve `ModulePlan` from deterministic fixture manifest
    - [x] compile all runtime artifacts before boot:
      - [x] `module-manifest.php`
      - [x] `config.php`
      - [x] `container.php`
    - [x] boot only through artifact-only runtime path after `1.340.0`
    - [x] MUST NOT bypass `CompiledContainerFactory`
  - [x] Express failure path MUST:
    - [x] load framework default `express` preset from `framework/packages/core/kernel/resources/modes/express.php`
    - [x] use deterministic fixture manifest where `platform.http` is absent
    - [x] fail with `CORETSIA_MODULE_REQUIRED_MISSING`
    - [x] assert deterministic error code, not full exception message
    - [x] MUST NOT require compiled artifacts to exist after expected pre-boot failure
  - [x] Micro/Express boot flows MUST NOT require `skeleton/config/modes/*.php`
  - [x] Micro/Express boot flows MUST NOT use skeleton mode overrides
  - [x] `AppBuilder` additionally provides `resolveSkeletonOnlyPreset()` for the separate custom preset test
  - [x] MUST NOT print stdout/stderr
  - [x] MUST NOT assert absolute paths

- [x] `framework/packages/core/kernel/tests/Integration/BootMicroPresetTest.php`
  - [x] uses `AppBuilder`
  - [x] MUST use framework default `micro` preset
  - [x] MUST NOT create `skeleton/config/modes/micro.php`
  - [x] MUST compile:
    - [x] `module-manifest.php`
    - [x] `config.php`
    - [x] `container.php`
  - [x] MUST boot through compiled artifacts only
  - [x] MUST assert no deterministic failure code is thrown
  - [x] MUST NOT assert stdout/stderr
  - [x] MUST NOT assert absolute paths

- [x] `framework/packages/core/kernel/tests/Integration/BootExpressPresetTest.php`
  - [x] uses `AppBuilder`
  - [x] MUST use framework default `express` preset
  - [x] MUST NOT create `skeleton/config/modes/express.php`
  - [x] MUST use fixture manifest without `platform.http`
  - [x] MUST assert exact error code:
    - [x] `CORETSIA_MODULE_REQUIRED_MISSING`
  - [x] MUST assert only fixed reason token if reason is asserted
  - [x] MUST NOT assert full exception message
  - [x] MUST NOT assert stdout/stderr
  - [x] MUST NOT assert absolute paths
  - [x] MUST NOT require `container.php` to exist after expected pre-boot failure

- [x] `framework/packages/core/kernel/tests/Integration/ModulePlanResolverLoadsSkeletonOnlyCustomPresetTest.php`
  - [x] proves owner-defined custom preset names are supported through skeleton mode files
  - [x] MUST create a fixture preset:
    - [x] `skeleton/config/modes/worker-only.php`
  - [x] MUST NOT create:
    - [x] `framework/packages/core/kernel/resources/modes/worker-only.php`
  - [x] MUST select preset through `BootstrapConfig::preset()` or bootstrap override:
    - [x] `worker-only`
  - [x] MUST resolve `ModulePlan` successfully from the skeleton-only preset
  - [x] MUST use normal `required|optional|disabled` rules
  - [x] MUST NOT require `skeleton/config/modules.php`
  - [x] MUST NOT use app-local module selection
    - [x] scenario does not create or require app-local module selection
    - [x] ignore-if-present behavior is already locked by `ModulePlanResolverUsesBootstrapPresetAsOnlySelectionSourceTest.php`
  - [x] MUST NOT expose skeleton paths in diagnostics
  - [x] MUST assert deterministic enabled module list

#### Modifies

- [x] `docs/ssot/modes.md`
  - [x] clarify terminology:
    - [x] framework canonical presets are `micro|express|hybrid|enterprise`
    - [x] owner-defined custom preset names are non-canonical names
    - [x] owner-defined custom preset names MUST NOT use canonical names
    - [x] skeleton overrides MAY override framework canonical preset names through `skeleton/config/modes/<canonical>.php`
  - [x] clarify Express Phase 1/Phase 2 cutline:
    - [x] `express` is the conventional HTTP/web application mode
    - [x] framework-owned `express` preset requires `platform.http`
    - [x] until `platform.http` exists in the installed manifest, Express boot MUST fail deterministically with `CORETSIA_MODULE_REQUIRED_MISSING`

- [x] `framework/tools/testing/phpunit.xml` or package test discovery config
  - [x] canonical PHPUnit package discovery MUST include `framework/packages/core/kernel/tests/Integration/*`
  - [x] If these tests are already discovered by the canonical package test runner, no `phpunit.xml` source change is required.

- [x] `framework/packages/core/kernel/resources/modes/express.php`
  - [x] move `platform.http` from `optional` to `required`
  - [x] required MUST become:
    - [x] `core.foundation`
    - [x] `core.kernel`
    - [x] `platform.cli`
    - [x] `platform.http`
  - [x] optional MUST become:
    - [x] `platform.logging`
    - [x] `platform.metrics`
    - [x] `platform.tracing`

- [x] `framework/packages/core/kernel/tests/Integration/ModePresetSchemaValidatorEnforcesMicroAndExpressRulesTest.php`
  - [x] update Express canonical rules expectation:
    - [x] `platform.http` MUST be in `required`
    - [x] `platform.http` MUST NOT be in `optional`
  - [x] Express `moduleIds()` MUST still include:
    - [x] `platform.http`
    - [x] `platform.logging`
    - [x] `platform.metrics`
    - [x] `platform.tracing`
  - [x] Express `toArray()` MUST reflect `platform.http` under `required`, not `optional`

### Cross-cutting (only if applicable; otherwise `N/A`)

#### Security / Redaction

- [x] Tests MUST NOT print secrets; use deterministic error codes only.

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

- [x] Smoke suite:
  - [x] `framework/packages/core/kernel/tests/Integration/BootMicroPresetTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/BootExpressPresetTest.php`

### Tests (MUST)

- Integration:
  - [x] `framework/packages/core/kernel/tests/Integration/BootMicroPresetTest.php`
  - [x] `framework/packages/core/kernel/tests/Integration/BootExpressPresetTest.php`

### DoD (MUST)

- [x] Micro boot test green
- [x] Express boot asserts deterministic failure code until Phase 2 cutline
- [x] Smoke harness remains valid after artifact-only boot policy:
  - [x] fixture app is compiled first
  - [x] boot then uses compiled artifacts instead of bypassing the real runtime path
- [x] Дає тестовий harness для boot сценаріїв (micro/express) без вимоги існування `skeleton/config/modes/*.php`.
- [x] Цементує очікування: micro boot OK, express boot може deterministic fail до Phase 2 cutline.
- [x] Non-goals / out of scope
  - [x] Не реалізує HTTP runtime; лише boot.
- [x] Є 2 smoke тести, що доводять: micro boot стабільно працює, express — стабільно падає з очікуваним кодом (до Phase 2).
- [x] When booting Express fixture in Phase 0, then it fails with `CORETSIA_MODULE_REQUIRED_MISSING` deterministically.
- [x] Micro boot success means:
  - [x] fixture app compiles `module-manifest.php`, `config.php`, and `container.php`
  - [x] runtime boot uses `CompiledContainerFactory`
  - [x] runtime boot reads compiled `container.php`
  - [x] runtime boot uses compiled `config.php` payload
  - [x] runtime boot does not read source config files as fallback
  - [x] runtime boot does not run provider discovery as fallback
  - [x] runtime boot does not require HTTP runtime
- [x] Owner-defined custom preset support is locked:
  - [x] skeleton-only preset name such as `worker-only` can be selected
  - [x] `skeleton/config/modes/worker-only.php` is loaded without a framework default file
  - [x] normal `required|optional|disabled` rules apply

---

### 1.380.0 Deptrac SSoT Composer Edge Consistency Check (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.380.0"
owner_path: "framework/tools/build/"

goal: "Ensure internal Composer require edges cannot drift from the canonical SSoT dependency table used by deptrac generation."
provides:
- "Composer internal require edge validation inside the existing deptrac SSoT generator/checker"
- "Deterministic failure when a materialized internal Composer dependency is missing from the SSoT dependency table"
- "No new architecture gate wrapper and no second architecture-rules/config brain"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []
adr: none
ssot_refs:
- docs/roadmap/phase0/00_2-dependency-table.md
- docs/architecture/PACKAGING.md
- docs/architecture/STRUCTURE.md
- docs/ops/architecture-generator-evidence.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

- Epic prerequisites:
  - 0.80.0 — deptrac generator prototype exists
  - 1.50.0 — tooling baseline exists
  - `docs/roadmap/phase0/00_2-dependency-table.md` defines canonical compile-time dependency edges.
  - `docs/architecture/PACKAGING.md` and `docs/architecture/STRUCTURE.md` document package/structure architecture.
  - `docs/ops/architecture-generator-evidence.md` documents architecture generator evidence / operational proof.

- Required deliverables:
  - `framework/tools/build/deptrac_generate.php` — canonical SSoT → deptrac config generator/checker
  - `framework/tools/testing/deptrac.yaml` — generated canonical deptrac config
  - `docs/roadmap/phase0/00_2-dependency-table.md` — canonical compile-time dependency SSoT

#### Compile-time deps

Depends on:

- `devtools/internal-toolkit` (for path normalization)

Forbidden:

- none

### Entry points / integration points (MUST)

- Composer, framework workspace:
  - `composer arch:deptrac:check` — verifies generated deptrac config is up to date from SSoT and validates internal Composer require edges against the SSoT dependency table.
  - `composer arch:deptrac:generate` — regenerates canonical deptrac config from SSoT.
  - `composer arch:deptrac:analyze` — runs raw deptrac analysis against `framework/tools/testing/deptrac.yaml`.

- Composer, repo root:
  - existing `composer arch` delegates to framework workspace `arch`.

- CI:
  - no new CI entrypoint is introduced.
  - existing architecture rail remains:
    - `arch:package-index:check`
    - `arch:deptrac:check`
    - `arch:deptrac:analyze`

### Deliverables (MUST)

#### Creates

- N/A

#### Explicit non-goals / duplicate-gate guard (MUST)

- [x] SSoT dependency-table consistency is owned by `framework/tools/build/deptrac_generate.php --check`.
- [x] Existing architecture rail MUST continue to use `deptrac_generate.php --check` as the only SSoT freshness and dependency-table consistency entrypoint.
- [x] Any future SSoT dependency-table validation improvements MUST be added to `deptrac_generate.php` or the package-compliance rail, not to a second deptrac SSoT gate.

#### Internal Composer dependency consistency (MUST)

The policy “core packages do not depend on forbidden platform/integrations/devtools packages” MUST be enforced through the canonical dependency chain:

`docs/roadmap/phase0/00_2-dependency-table.md`
→ `framework/tools/build/deptrac_generate.php --check`
→ `framework/tools/testing/deptrac.yaml`
→ `arch:deptrac:analyze`

Additional Composer-level consistency MUST be implemented in the existing SSoT/deptrac generation rail:

- [x] for each materialized package `framework/packages/<layer>/<slug>/composer.json`:
  - [x] collect internal runtime dependencies from `require` where package name starts with `coretsia/`
  - [x] map internal Composer names to package ids
  - [x] every mapped internal dependency MUST appear in the package’s direct `depends_on` cell in `docs/roadmap/phase0/00_2-dependency-table.md`
  - [x] dependencies not present in SSoT MUST fail deterministically
  - [x] diagnostics MUST include:
    - [x] source package id
    - [x] target package id
    - [x] reason token `composer-edge-not-in-ssot`
  - [x] diagnostics MUST NOT include absolute paths or raw composer file dumps

This check closes forbidden dependency drift without introducing a second architecture policy gate.

#### Modifies

- [x] `framework/tools/build/deptrac_generate.php` — extend SSoT validation:
  - [x] for each materialized `framework/packages/*/*/composer.json`, internal `require` edges to `coretsia/*` packages MUST be a subset of direct `depends_on` entries in `docs/roadmap/phase0/00_2-dependency-table.md`
  - [x] internal package self-requires are forbidden
  - [x] unknown internal package names MUST fail deterministically
  - [x] diagnostics MUST use package ids, not absolute paths
  - [x] failure MUST use a deterministic code:
    - [x] `CORETSIA_DEPTRAC_COMPOSER_EDGE_NOT_IN_SSOT`
  - [x] this check MUST NOT inspect or enforce external vendor packages
  - [x] this check MUST NOT create a second architecture ruleset

- [x] `framework/tools/spikes/_support/ErrorCodes.php` — register `CORETSIA_DEPTRAC_COMPOSER_EDGE_NOT_IN_SSOT`

- [x] update command in `docs/guides/commands.md` if necessary:
  - [x] `composer arch`
  - [x] `arch:package-index:check`
  - [x] `arch:deptrac:check`
  - [x] `arch:deptrac:analyze`

- [x] Composer package name → package id mapping MUST reuse the existing canonical package metadata / package-index logic already used by the deptrac generation rail.
- [x] This epic MUST NOT introduce a second Composer-name-to-package-id mapping source.

### Cross-cutting

#### Observability

- N/A

#### Errors

- [x] Deterministic codes.

#### Security / Redaction

- [x] Output MUST NOT contain absolute paths, raw composer JSON, source code, or filesystem layout.
- [x] Output contains only deterministic package ids, reason tokens, and error codes.
- [x] Diagnostics MUST be deduplicated and sorted by byte-order `strcmp`.

### Verification

- N/A

### Tests (MUST)

- [x] `framework/tools/tests/Integration/DeptracGenerateComposerEdgesMatchSsotTest.php`
  - [x] creates synthetic package composer metadata with internal `coretsia/*` require edge
  - [x] asserts edge missing from SSoT `depends_on` fails with:
    - [x] `CORETSIA_DEPTRAC_COMPOSER_EDGE_NOT_IN_SSOT`
  - [x] asserts diagnostics include source package id, target package id, reason token
  - [x] asserts diagnostics do not include absolute paths or raw composer JSON dumps

### DoD

- [x] `deptrac_generate.php --check` validates internal Composer require edges against the SSoT dependency table.
- [x] No new Composer script is introduced.
- [x] Existing `arch` script remains the architecture rail.
- [x] Internal Composer `require` edges cannot silently bypass the SSoT dependency table.
- [x] `composer arch:deptrac:check` remains the only SSoT freshness / dependency-table consistency check entrypoint.
- [x] No new `architecture:gate` Composer script is introduced.
- [x] No new `framework/tools/gates/architecture_gate.php` file is introduced.
- [x] Output semantics MUST remain compatible with existing tooling checks:
  - [x] pass: no output, exit `0`
  - [x] failure: deterministic code + deterministic diagnostics, exit `1`
- [x] New diagnostics for Composer-edge drift MUST include only:
  - [x] source package id
  - [x] target package id
  - [x] reason token
  - [x] deterministic error code
- [x] New diagnostics MUST be deduplicated and sorted by byte-order `strcmp`.
- [x] New diagnostics MUST NOT include:
  - [x] absolute paths
  - [x] raw composer JSON
  - [x] source code
  - [x] filesystem layout
  - [x] exception messages
  - [x] stack traces

---

### 1.390.0 Atomic Transaction Gate (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.390.0"
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
  - Existing production tools that write files MUST be migrated to `DeterministicFile` before enabling this gate in CI.

- Required deliverables:
  - `framework/tools/spikes/_support/DeterministicFile.php` — canonical deterministic file helper and reference pattern for safe tools-side writes.

#### Compile-time deps

N/A

### Entry points / integration points (MUST)

- Composer:
  - `composer atomic-write:gate` — runs atomic write gate
- CI:
  - run in framework gates aggregate after existing writer migration

### Deliverables (MUST)

#### Creates

- [x] `framework/tools/config/atomic_write_allowlist.php` MUST return deterministic framework-relative file paths.
  - [x] Each entry MUST include a fixed reason token.
  - [x] Wildcard patterns are forbidden in Phase 1.
  - [x] Absolute paths are forbidden.
  - [x] Allowlist entries MUST use exact shape:
    - [x] `path` — framework-relative path string, relative to `framework/`
    - [x] `reason` — fixed lowercase reason token
  - [x] Allowlist entries MUST be sorted by `path` using byte-order `strcmp`.
  - [x] Unknown allowlist keys MUST fail deterministically.

- [x] `framework/tools/gates/atomic_write_gate.php` — deterministic gate:
  - [x] scans production tooling PHP files under `framework/tools/**/*.php`
  - [x] excludes:
    - [x] `framework/tools/tests/**`
    - [x] `framework/tools/**/fixtures/**`
    - [x] `framework/tools/spikes/fixtures/**`
    - [x] `framework/tools/spikes/_support/DeterministicFile.php`
  - [x] Phase 1 single-choice policy:
    - [x] persistent tools-side writes MUST go through `DeterministicFile`
  - [x] forbidden raw write sinks outside allowlisted files:
    - [x] `file_put_contents`
    - [x] `fwrite`
    - [x] `rename` used as custom atomic-write implementation outside `DeterministicFile`
    - [x] `copy` used as write/update sink
  - [x] `fopen` in read-only modes (`r`, `rb`, `rt`) is allowed.
  - [x] `fopen` with write/append/create modes (`w`, `wb`, `a`, `ab`, `c`, `cb`, `x`, `xb`, and `+` variants) is forbidden outside allowlisted files.
  - [x] `SplFileObject` / `FilesystemIterator` read-only usage is allowed.
  - [x] `SplFileObject` with write/append/create modes is forbidden outside allowlisted files.
  - [x] if unsafe write found, prints `CORETSIA_ATOMIC_WRITE_VIOLATION`
  - [x] On scanner/tooling failure, prints:
    - [x] `CORETSIA_ATOMIC_WRITE_GATE_FAILED`
  - [x] diagnostics include framework-relative file path and line number only in production scan mode
  - [x] diagnostics include scan-root-relative file path and line number only when `--path` points outside `framework/`
  - [x] diagnostics MUST NOT include source code snippets
  - [x] uses `ConsoleOutput`
  - [x] supports `--path` override for test fixture roots
  - [x] Unknown allowlist keys MUST fail deterministically.
  - [x] MUST resolve the tools root deterministically from the executing gate file.
  - [x] MUST load `framework/tools/spikes/_support/bootstrap.php` before scanning.
  - [x] If bootstrap is missing or unreadable:
    - [x] MUST attempt to load `framework/tools/spikes/_support/ConsoleOutput.php`
    - [x] MUST print the gate scan-failed code using `ConsoleOutput::codeWithDiagnostics($code, [])` when ConsoleOutput is available
    - [x] MUST exit with code `1`
  - [x] MUST use `Coretsia\Tools\Spikes\_support\ConsoleOutput::codeWithDiagnostics()` for all non-empty diagnostics output.
  - [x] MUST NOT use `echo`, `print`, `var_dump`, `print_r`, `printf`, direct `STDOUT`, or direct `STDERR` for diagnostics.
  - [x] MUST load `framework/tools/spikes/_support/ErrorCodes.php` when available.
  - [x] MUST resolve error code constants from `ErrorCodes` when defined.
  - [x] MUST keep deterministic fallback string codes when `ErrorCodes` is unavailable.
  - [x] MUST use two code classes when applicable:
    - [x] violation/finding code
    - [x] scan-failed/tooling-failed code
  - [x] MUST suppress warnings/notices around filesystem probing where existing gates do so, to avoid output pollution.
  - [x] MUST wrap scanning/parsing logic in `try/catch`.
  - [x] On unexpected throwable:
    - [x] MUST emit the scan-failed code through `ConsoleOutput::codeWithDiagnostics($code, [])`
    - [x] MUST exit with code `1`
  - [x] On pass:
    - [x] MUST emit no output
    - [x] MUST exit with code `0`
  - [x] On violation/finding:
    - [x] MUST emit only the deterministic violation/finding code and sorted diagnostics
    - [x] MUST exit with code `1`
  - [x] Diagnostics MUST be:
    - [x] deduplicated
    - [x] sorted by byte-order `strcmp`
    - [x] stable across OS/filesystem order/locale
    - [x] free of absolute paths, raw payloads, source snippets, secrets, tokens, credentials, stack traces, and exception messages.

#### Modifies

- [x] `composer.json` — add mirror scripts (delegates to framework):
  - [x] `atomic-write:gate` → `@composer --no-interaction --working-dir=framework run-script atomic-write:gate --`

- [x] `framework/composer.json` — add gate script
  - [x] `atomic-write:gate` → `@php tools/gates/atomic_write_gate.php`
  - [x] add to `gates`

- [x] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [x] `CORETSIA_ATOMIC_WRITE_VIOLATION`
  - [x] `CORETSIA_ATOMIC_WRITE_GATE_FAILED`

- [x] Housekeeping: move existing package compliance allowlist into canonical tools config directory:
  - [x] `framework/tools/gates/package_compliance_allowlist.php` → `framework/tools/config/package_compliance_allowlist.php`
  - [x] update `framework/tools/gates/package_compliance_gate.php` to read allowlist from `framework/tools/config/`
  - [x] this move MUST NOT change package compliance semantics

- [x] add command `atomic-write:gate` in `docs/guides/commands.md`
- [x] update command `composer gates` in `docs/guides/commands.md`

### Cross-cutting

#### Observability

- N/A

#### Errors

- [x] Deterministic error codes.

#### Security / Redaction

- [x] No file contents; only paths.

### Verification

- [x] Integration test: create a PHP file with unsafe `file_put_contents`, run gate with `--path`, assert failure.

### Tests

- [x] `framework/tools/tests/Integration/AtomicWriteGateTest.php`
  - [x] unsafe `file_put_contents` fails with `CORETSIA_ATOMIC_WRITE_VIOLATION`
  - [x] read-only `fopen(..., 'rb')` passes with no output
  - [x] writable `fopen(..., 'wb')` fails with `CORETSIA_ATOMIC_WRITE_VIOLATION`
  - [x] invalid allowlist fails with `CORETSIA_ATOMIC_WRITE_GATE_FAILED`
  - [x] unreadable/missing scan root fails with `CORETSIA_ATOMIC_WRITE_GATE_FAILED`

### DoD

- [x] Gate implemented, CI integrated, error code registered.

---

### 1.400.0 Composer Audit Gate (MUST) [TOOLING]

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
- CI:
  - MUST run in a dedicated security job or security lane.
  - MUST NOT run as part of architecture/deptrac job.

### Deliverables (MUST)

#### Creates

- [x] `framework/tools/gates/composer_audit_gate.php` — deterministic gate:
  - [x] locates audit-capable install roots only:
    - [x] repo root
    - [x] `framework/`
    - [x] `skeleton/`
  - [x] package manifests under `framework/packages/**` MUST NOT be audited directly by this gate
  - [x] for each audit-capable root, runs `composer audit --format=json --abandoned=ignore` in the corresponding directory
  - [x] parses JSON output and derives sanitized advisory diagnostics from `advisories`
  - [x] if any advisories, prints `CORETSIA_COMPOSER_AUDIT_FAILED` + list of affected packages and advisories (sanitized)
  - [x] uses `ConsoleOutput` for output
  - [x] if `composer` command fails (not found), prints `CORETSIA_COMPOSER_AUDIT_SCAN_FAILED`
  - [x] supports `--path` override for testing
  - [x] MUST parse JSON stdout/stderr when available even if `composer audit` exits non-zero.
  - [x] If valid audit JSON contains advisories, classify as:
    - [x] `CORETSIA_COMPOSER_AUDIT_FAILED`
  - [x] Classify as `CORETSIA_COMPOSER_AUDIT_SCAN_FAILED` only when:
    - [x] composer executable cannot be run
    - [x] process times out
    - [x] output is not valid JSON
    - [x] expected audit fields are absent or unusable
    - [x] composer exits non-zero without usable advisory findings
  - [x] MUST resolve repository root from the framework workspace:
    - [x] default repo root is parent directory of `framework/`
  - [x] MUST audit install roots relative to repo root:
    - [x] `<repo-root>/composer.json`
    - [x] `<repo-root>/framework/composer.json`
    - [x] `<repo-root>/skeleton/composer.json`
  - [x] `--path` override MUST be treated as fixture repo root in tests.
  - [x] MUST resolve the tools root deterministically from the executing gate file.
  - [x] MUST load `framework/tools/spikes/_support/bootstrap.php` before scanning.
  - [x] If bootstrap is missing or unreadable:
    - [x] MUST attempt to load `framework/tools/spikes/_support/ConsoleOutput.php`
    - [x] MUST print the gate scan-failed code using `ConsoleOutput::codeWithDiagnostics($code, [])` when fallback output writer is available
    - [x] MUST exit with code `1`
  - [x] MUST use `Coretsia\Tools\Spikes\_support\ConsoleOutput::codeWithDiagnostics()` for all non-empty diagnostics output.
  - [x] MUST NOT use `echo`, `print`, `var_dump`, `print_r`, `printf`, direct `STDOUT`, or direct `STDERR` for diagnostics.
  - [x] MUST load `framework/tools/spikes/_support/ErrorCodes.php` when available.
  - [x] MUST resolve error code constants from `ErrorCodes` when defined.
  - [x] MUST keep deterministic fallback string codes when `ErrorCodes` is unavailable.
  - [x] MUST use two code classes when applicable:
    - [x] violation/finding code
    - [x] scan-failed/tooling-failed code
  - [x] MUST suppress warnings/notices around filesystem probing where existing gates do so, to avoid output pollution.
  - [x] MUST wrap scanning/parsing logic in `try/catch`.
  - [x] On unexpected throwable:
    - [x] MUST emit the scan-failed code through `ConsoleOutput::codeWithDiagnostics($code, [])`
    - [x] MUST exit with code `1`
  - [x] On pass:
    - [x] MUST emit no output
    - [x] MUST exit with code `0`
  - [x] On violation/finding:
    - [x] MUST emit only the deterministic violation/finding code and sorted diagnostics
    - [x] MUST exit with code `1`
  - [x] Diagnostics MUST be:
    - [x] deduplicated
    - [x] sorted by byte-order `strcmp`
    - [x] stable across OS/filesystem order/locale
    - [x] free of absolute paths, raw payloads, source snippets, secrets, tokens, credentials, stack traces, and exception messages.
  - [x] MUST run Composer audit with captured stdout/stderr.
  - [x] MUST NOT stream raw Composer audit output directly to stdout/stderr.
  - [x] MUST parse JSON output from captured stdout/stderr only.
  - [x] MUST normalize parsed diagnostics through `ConsoleOutput::codeWithDiagnostics()`.

#### Modifies

- [x] `composer.json` — add mirror scripts:
  - [x] `composer-audit:gate` → `@composer --no-interaction --working-dir=framework run-script composer-audit:gate --`
  - [x] `security` → `@composer --no-interaction --working-dir=framework run-script security --`

- [x] `framework/composer.json` — add gate script
  - [x] `composer-audit:gate` → `@php tools/gates/composer_audit_gate.php`
  - [x] MUST NOT add `composer-audit:gate` to the generic `gates` aggregate.
  - [x] MUST add or update dedicated security aggregate:
    - [x] `security` MUST include `@composer-audit:gate`

- [x] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [x] `CORETSIA_COMPOSER_AUDIT_FAILED`
  - [x] `CORETSIA_COMPOSER_AUDIT_SCAN_FAILED`

- [x] add command `composer-audit:gate` in `docs/guides/commands.md`
- [x] add command `composer security` in `docs/guides/commands.md`

### Cross-cutting

#### Observability

- N/A

#### Errors

- [x] Deterministic codes for vulnerability found and scan failure.

#### Security / Redaction

- [x] `composer audit` output may contain URLs; we strip to only advisory IDs and package names.

### Verification

- [x] Integration test MUST NOT call live Packagist or live advisory services.
- [x] `framework/tools/tests/Fixtures/ComposerAudit/audit_clean.json` — captured clean output fixture
- [x] `framework/tools/tests/Fixtures/ComposerAudit/audit_with_advisories.json` — captured advisory output fixture
- [x] `framework/tools/tests/Fixtures/ComposerAudit/audit_scan_failed.json` — captured process-failure fixture
- [x] `framework/tools/tests/Integration/ComposerAuditGateTest.php` MUST use mocked process output / fixtures only and assert deterministic codes for:
  - [x] advisory found → `CORETSIA_COMPOSER_AUDIT_FAILED`
  - [x] scan failure → `CORETSIA_COMPOSER_AUDIT_SCAN_FAILED`
  - [x] composer audit exits non-zero but returns valid JSON with advisories → `CORETSIA_COMPOSER_AUDIT_FAILED`, not scan failed

### Tests

- [x] `framework/tools/tests/Integration/ComposerAuditGateTest.php` (mocks composer output).

### DoD

- [x] Gate implemented and integrated
- [x] Error codes registered
- [x] Verification test exists (even if mocked)

---

### 1.410.0 Secret Leakage Gate (MUST) [TOOLING]

---
type: tools
phase: 1
epic_id: "1.410.0"
owner_path: "framework/tools/gates/"

goal: "Prevent accidental commits of secrets by scanning the repository with Gitleaks."
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
  - Gitleaks is the scanner.

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
  - [ ] MUST resolve repository root from the framework workspace:
    - [ ] default repo root is parent directory of `framework/`
  - [ ] MUST run scanner against repo root, not only `framework/`
  - [ ] MUST use repo-root `.gitleaks.toml`
  - [ ] default command shape:
    - [ ] `gitleaks detect --source=<repo-root> --config=<repo-root>/.gitleaks.toml --no-git --report-format=json --redact`
  - [ ] MUST NOT print raw matches
  - [ ] parses JSON output; if any leak, prints `CORETSIA_SECRET_LEAK_DETECTED` + list of files and findings (sanitized)
  - [ ] if gitleaks not available, prints `CORETSIA_SECRET_GATE_SCAN_FAILED`
  - [ ] uses `ConsoleOutput`
  - [ ] supports `--path` override for test fixture repo roots.
  - [ ] when `--path` is provided, both source root and config path are resolved relative to that fixture root unless explicitly overridden by test-only flags.
  - [ ] MUST invoke Gitleaks in JSON report mode only.
  - [ ] MUST NOT parse human-readable Gitleaks output.
  - [ ] MUST classify missing/unavailable Gitleaks as `CORETSIA_SECRET_GATE_SCAN_FAILED`.
  - [ ] MUST resolve the tools root deterministically from the executing gate file.
  - [ ] MUST load `framework/tools/spikes/_support/bootstrap.php` before scanning.
  - [ ] If bootstrap is missing or unreadable:
    - [ ] MUST attempt to load `framework/tools/spikes/_support/ConsoleOutput.php`
    - [ ] MUST print the gate scan-failed code using `ConsoleOutput::codeWithDiagnostics($code, [])`
    - [ ] MUST exit with code `1`
  - [ ] MUST use `Coretsia\Tools\Spikes\_support\ConsoleOutput::codeWithDiagnostics()` for all non-empty diagnostics output.
  - [ ] MUST NOT use `echo`, `print`, `var_dump`, `print_r`, `printf`, direct `STDOUT`, or direct `STDERR` for diagnostics.
  - [ ] MUST load `framework/tools/spikes/_support/ErrorCodes.php` when available.
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
    - [ ] free of absolute paths, raw payloads, source snippets, secrets, tokens, credentials, stack traces, and exception messages.
  - [ ] MUST run Gitleaks with captured stdout/stderr.
  - [ ] MUST NOT stream raw Gitleaks output directly to stdout/stderr.
  - [ ] MUST parse JSON report output from captured stdout/stderr only.
  - [ ] MUST normalize parsed diagnostics through `ConsoleOutput::codeWithDiagnostics()`.

#### Modifies

- [ ] `composer.json` — add mirror scripts (delegates to framework):
  - [ ] `secret-leakage:gate` → `@composer --no-interaction --working-dir=framework run-script secret-leakage:gate --`

- [ ] `framework/composer.json` — add gate script
  - [ ] `secret-leakage:gate` → `@php tools/gates/secret_leakage_gate.php`
  - [ ] MUST NOT add `secret-leakage:gate` to the generic `gates` aggregate.
  - [ ] MUST add it to the dedicated security aggregate:
    - [ ] `security` → [`@secret-leakage:gate`]

- [ ] `framework/tools/spikes/_support/ErrorCodes.php` — register:
  - [ ] `CORETSIA_SECRET_LEAK_DETECTED`
  - [ ] `CORETSIA_SECRET_GATE_SCAN_FAILED`

- [ ] add command `secret-leakage:gate` in `docs/guides/commands.md`
- [ ] update command `composer security` in `docs/guides/commands.md`

### Cross-cutting

#### Observability

- N/A

#### Errors

- [ ] Deterministic codes.

#### Security / Redaction

- [ ] The gate must redact secrets from output; Gitleaks output already redacts, but we also ensure we don't print raw matches.

### Verification

- [ ] Integration test MUST NOT require live Gitleaks scanning against realistic secrets.
- [ ] `framework/tools/tests/Fixtures/Gitleaks/gitleaks_clean.json`
- [ ] `framework/tools/tests/Fixtures/Gitleaks/gitleaks_with_findings.json`
- [ ] `framework/tools/tests/Fixtures/Gitleaks/gitleaks_scan_failed.json`
- [ ] `framework/tools/tests/Integration/SecretLeakageGateTest.php` MUST use mocked Gitleaks JSON output / fixtures and assert:
  - [ ] finding found → `CORETSIA_SECRET_LEAK_DETECTED`
  - [ ] scanner unavailable / invalid JSON → `CORETSIA_SECRET_GATE_SCAN_FAILED`

### Tests

- [ ] `framework/tools/tests/Integration/SecretLeakageGateTest.php` (uses mocked Gitleaks JSON output fixtures)
  - [ ] tests MUST use mocked Gitleaks JSON output fixtures
  - [ ] tests MUST NOT commit realistic live credentials

### DoD

- [ ] Gate implemented, config created, CI integrated.
