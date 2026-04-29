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

# Шаблон епіка (Non-SSoT, поза DAG фреймворку, Non-product doc)

### <phase>.<epic>.<sub> <Epic title> (<MUST|SHOULD|OPTIONAL>) [<IMPL|CONTRACTS|TOOLING|DOC>]

---
type: <package|skeleton|tools|docs>
phase: <PRELUDE|0|1|2|3|4|5|6+>
epic_id: "<phase>.<epic>.<sub>"
owner_path: "<single canonical path>"  # package root OR skeleton/tools/docs root

# package-only (present ONLY when type=package; otherwise these keys are absent):

package_id: "<layer>/<slug>"           # e.g. "core/contracts", "core/foundation", "core/kernel"
composer: "coretsia/<slug>"
kind: <runtime|library>
module_id: "<layer>.<slug>"            # present only if kind=runtime

goal: "<one sentence>"
provides:
  - "<capability 1>"
  - "<capability 2>"

tags_introduced: []
config_roots_introduced: []
artifacts_introduced: []               # "name@schemaVersion"

adr: <none|docs/adr/ADR-XXXX-....md>
ssot_refs:
  - docs/ssot/<...>.md
---

### Dependencies (MUST)

#### Preconditions (MUST)

> Everything that MUST already exist (files/keys/tags/contracts/artifacts).

- Epic prerequisites:
  - <phase.epic.sub> — <reason>

- Required deliverables (exact paths):
  - `<path>` — <why>

- Required config roots/keys:
  - `<root>` / `<root.key>` — <why>

- Required tags:
  - `<tag>` — <why>

- Required contracts / ports:
  - `<FQCN>` — <why>

#### Compile-time deps (deptrac-enforceable) (MUST)

Depends on:

- `core/contracts`
- ...

Forbidden:

- `platform/*`
- `integrations/*`
- ...

#### Uses ports (API surface, NOT deps) (optional)

- PSR:
  - `<FQCN>`
- Contracts:
  - `<FQCN>`
- Foundation stable APIs:
  - `<FQCN>`

### Entry points / integration points (MUST)

> If none exist for this epic, write exactly: `N/A`

N/A

- CLI:
  - `<command>` → `<OwnerPackage>` `src/.../<Command>.php`
  - tag/discovery integration (if applicable):
    - `<tag>` priority `<int>` meta `per owner schema`

- HTTP:
  - routes: `<method path>` → `<OwnerPackage>` `<Handler>`
  - middleware slots/tags:
    - `<tag>` priority `<int>` meta `per owner schema`

- Kernel hooks/tags:
  - `<tag>` priority `<int>` meta `per owner schema`

- Other runtime discovery / integration tags:
  - `<tag>` priority `<int>` meta `per owner schema`

- Artifacts:
  - reads: `skeleton/var/cache/<appId>/<artifact>.php`
  - writes: `skeleton/var/cache/<appId>/<artifact>.php`

- Notes (only if applicable):
  - if this epic references a tag owned by another package, this section MUST name only:
    - the tag
    - the integration point / consumer or contributor role
    - the priority (if already cemented)
    - `meta per owner schema`
  - this section MUST NOT define or redefine:
    - tag ownership
    - competing meta keys
    - competing discovery semantics
  - if the owner meta-schema is not cemented yet:
    - do NOT invent placeholder meta keys
    - describe the intent in prose only, while keeping runtime examples as `meta per owner schema`

### Deliverables (MUST)

#### Creates

- [ ] `<path>` — <purpose>
- [ ] `<path>` — <purpose>

#### Modifies

- [ ] `<path>` — <what changes + why>

#### Package skeleton (if type=package)

- [ ] `composer.json`
- [ ] `src/Module/<StudlySlug>Module.php` (runtime only)
- [ ] `src/Provider/<StudlySlug>ServiceProvider.php` (runtime only)
- [ ] `config/<snake_case(slug)>.php`  # returns subtree (no repeated root)
- [ ] `config/rules.php`
- [ ] `README.md` (must include: Observability / Errors / Security-Redaction)
- [ ] `tests/Contract/CrossCuttingNoopDoesNotThrowTest.php` (runtime only)

#### Configuration (keys + defaults)

- [ ] Files:
  - [ ] `<path>`
- [ ] Keys (dot):
  - [ ] `<root.key>` = <default>
- [ ] Rules:
  - [ ] `<path>/config/rules.php` enforces shape

#### Wiring / DI tags (when applicable)

- [ ] Tags introduced (this epic is the OWNER):
  - [ ] if this epic owns one or more reserved tags:
    - [ ] `src/Provider/Tags.php` (owner constants)
    - [ ] constants:
      - [ ] `<TAG_CONST> = '<tag>'`
  - [ ] if this epic does not own the referenced tags:
    - [ ] `N/A (uses existing <tag>; owner: <owner package_id>)`

- [ ] ServiceProvider wiring evidence:
  - [ ] registers: `<FQCN>`
  - [ ] adds tag: `<tag>` priority `<int>` meta `per owner schema`
  - [ ] if owner meta-schema is not cemented yet:
    - [ ] do NOT invent alternative meta keys
    - [ ] describe intent in prose/note form until the owner epic defines the canonical meta-schema

- [ ] Compliance delta:
  - [ ] this package MUST NOT introduce owner constants for tags it does not own
  - [ ] runtime code MUST use:
    - [ ] the owner public constant, if the owner package is an allowed compile-time dependency; OR
    - [ ] a package-local mirror constant in `src/Provider/Tags.php`, if the owner package is a forbidden compile-time dependency
  - [ ] any package-local mirror constant:
    - [ ] MUST be package-internal only
    - [ ] MUST equal the canonical tag string exactly
    - [ ] MUST NOT be treated as public API
  - [ ] non-owner packages MUST NOT define:
    - [ ] a competing tag owner constant
    - [ ] a competing meta-schema
    - [ ] competing consumer/discovery semantics for an existing tag
  - [ ] raw literal tag strings are allowed in docs/tests/fixtures for readability, but MUST NOT be the preferred runtime-code pattern

#### Artifacts / outputs (if applicable)

- [ ] Writes:
  - [ ] `skeleton/var/cache/<appId>/<artifact>.php` (schemaVersion, deterministic bytes)
- [ ] Reads:
  - [ ] validates header + payload schema

### Cross-cutting (only if applicable; otherwise `N/A`)

> If a subsection is not touched by this epic: write `N/A` and DO NOT add tests for it.

#### Context & UoW

- [ ] Context reads:
  - [ ] `<ContextKeys::...>`
- [ ] Context writes (safe only):
  - [ ] `<ContextKeys::...>` (no secrets/PII/session ids)
- [ ] Reset discipline:
  - [ ] stateful services implement `ResetInterface`
  - [ ] tagged `kernel.reset`

#### Observability (policy-compliant)

- [ ] Spans:
  - [ ] `<span.name>`
- [ ] Metrics:
  - [ ] `<metric_name_total>` (labels: allowlist only)
  - [ ] `<metric_name_duration_ms>` (labels: allowlist only)
- [ ] Logs:
  - [ ] redaction applied; no secrets/PII/raw payloads

#### Errors

- [ ] Exceptions introduced:
  - [ ] `<FQCN>` — errorCode `<CODE>`
- [ ] Mapping:
  - [ ] new mapper via tag `error.mapper` OR reuse existing

#### Security / Redaction

- [ ] MUST NOT leak:
  - [ ] auth/cookies/session ids/tokens/raw SQL/raw payload
- [ ] Allowed:
  - [ ] `hash(value)` / `len(value)` / safe ids

### Verification (TEST EVIDENCE) (MUST when applicable)

> These are NOT “nice to have” — they are the proof that the above is real.
> Each verification test MUST fail if the corresponding mechanism is removed (redaction/context guard/metrics
> emitter/etc).

#### Required policy tests matrix

- [ ] If Context writes exist → `tests/Contract/ContextWriteSafetyTest.php`
  (asserts: no secrets/PII; allowed keys only)
- [ ] If `kernel.reset` used → `tests/Contract/ResetWiringTest.php`
  (asserts: tagged services implement ResetInterface; reset is idempotent)
- [ ] If metrics/spans/logs exist → `tests/Contract/ObservabilityPolicyTest.php`
  (asserts: names + label allowlist + no PII)
- [ ] If redaction exists → `tests/Contract/RedactionDoesNotLeakTest.php`
  (asserts: token/cookie/auth never appears raw)

#### Test harness / fixtures (when integration is needed)

- [ ] Fixture app:
  - [ ] `<path>/tests/Fixtures/<App>/...` (only if wiring requires boot)
- [ ] Fake adapters:
  - [ ] `FakeTracer` / `FakeMetrics` / `FakeLogger` capture events for assertions

### Tests (MUST)

- Unit:
  - [ ] `<path>`
- Contract:
  - [ ] `<path>`
- Integration:
  - [ ] `<path>`
- Gates/Arch:
  - [ ] deptrac updated (if needed)
  - [ ] gates updated (if new invariants)

### DoD (MUST)

- [ ] Deliverables complete (creates+modifies), paths exact
- [ ] Preconditions satisfied (no forward references)
- [ ] deps/forbidden respected (deptrac; no cycles)
- [ ] Verification tests present where applicable
- [ ] Determinism: rerun-no-diff (if outputs generated)
- [ ] Docs updated:
  - [ ] README
  - [ ] ssot_refs if needed
  - [ ] ADR if needed

<!-- Invariants (applies to every epic):
- No forward refs: everything referenced outside this epic is listed in Preconditions or already exists.
- Deliverables are authoritative: if a file is not in Creates/Modifies, this epic MUST NOT touch it.
- Cross-cutting is N/A or proven: either "N/A" or concrete verification tests exist.
-->
