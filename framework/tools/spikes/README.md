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

# Phase 0 Spikes (tools-only sandbox)

**Scope:** `framework/tools/spikes/**`  
**Goal:** a stable sandbox + CI rails for deterministic tooling experiments across Linux/Windows.

This directory is **tools-only**. Spike implementations here MUST remain production-safe by construction:

- no runtime dependencies,
- no secrets in output,
- deterministic behavior (rerun ⇒ no diff),
- no repository writes (except committed fixtures).

---

## Directory layout

- `framework/tools/spikes/` — sandbox root (all spikes live here)
- `framework/tools/spikes/_support/` — Phase 0 rails infrastructure (bootstrap, deterministic runner, error codes, fixtures helpers)
- `framework/tools/spikes/fixtures/` — committed fixtures namespace
- `framework/tools/spikes/tests/` — spikes rails tests and contract enforcement
- `framework/tools/gates/` — gates scripts (must follow the same output policy as spikes rails)

Spike modules live under:

- `framework/tools/spikes/<module>/...` (e.g. `fingerprint/`, `payload/`, `config_merge/`, `workspace/`)

---

## Canonical rules (MUST / MUST NOT)

### 1) Single sandbox root (single-choice)

- Spikes **MUST** live under `framework/tools/spikes/**`.
- Spike implementations **MUST NOT** live under `framework/packages/**` (runtime area).

### 2) No runtime deps (hard boundary)

Spikes **MUST NOT** compile-time depend on, import, or reference:

- `core/*` (explicitly includes `core/contracts`)
- `platform/*`
- `integrations/*`
- `devtools/cli-spikes`
- any code under `framework/packages/**/src/**` via path-based imports

**Path-based imports are forbidden:**

- `require|require_once|include|include_once` of anything under `framework/packages/**` is not allowed.

### 3) Single tooling exception: `coretsia/internal-toolkit` (single-choice)

Spikes **MAY** depend on exactly one Coretsia internal tooling library:

- `coretsia/internal-toolkit`

Usage rules:

- **MUST** use Composer autoload (namespace-based)
- **MUST NOT** use path-based includes to reach it

The internal-toolkit exception is **closed** to determinism primitives only:

- `Slug::*`, `Path::*`, `Json::*`

Tooling **MUST NOT** duplicate these primitives anywhere under `framework/tools/**`.

### 4) Output policy (non-bypassable)

- Spike business logic **MUST** be output-free (no direct stdout/stderr writes).
- The only allowlisted writer for Phase 0 rails is:
  - `framework/tools/spikes/_support/ConsoleOutput.php`

Reserved usage (single-choice):

- rails code only: gates / determinism runner / bootstrap diagnostics
- spike module logic MUST NOT use `ConsoleOutput`

Also:

- output MUST NOT leak secrets/PII (dotenv values, tokens, passwords, raw payloads, raw SQL)
- diagnostics MUST be code + normalized relative paths + fixed reason tokens only

### 5) Determinism + no repo writes

- Spikes MUST be deterministic across OSes (Linux + Windows).
- The determinism suite uses a **rerun-no-diff** policy:
  - same entrypoint executed twice
  - git worktree must be clean before / between / after runs
- Spikes MUST NOT write into the repo during tests.
  - committed fixtures are the only repo content relied upon
  - runtime writes must go to temp dirs only

---

## How to run (local)

All commands are intended to be run from the **repo root**.

### Run spikes suite

```bash
composer spike:test
```

What it does (Phase 0 rails contract):

1. runs the Phase 0 output gate first
2. runs spikes PHPUnit suite with a dedicated config:

- `framework/tools/spikes/phpunit.spikes.xml`
- bootstrap is `_support/bootstrap.php` (CWD-independent)

### Run determinism suite (rerun-no-diff)

```bash
composer spike:test:determinism
```

This is the **only** canonical determinism mechanism. It orchestrates:

- git clean checks (`git status --porcelain`)
- two identical runs of `composer spike:test`
- stable failure semantics via deterministic error codes

### Run output bypass gate directly

```bash
composer spike:output:gate
```

If this gate fails it prints:

- line 1: deterministic `CODE`
- line 2+: stable diagnostics `<scan-root-relative-path>: output-bypass` sorted by `strcmp`

---

## How to run from the framework workspace (optional)

Rails are designed to be CWD-independent. You can run from `framework/` as well:

```bash
cd framework
composer spike:test
composer spike:test:determinism
composer spike:output:gate
```

---

## PHPUnit invariants (spikes suite)

The spikes PHPUnit config is **dedicated** and MUST remain repo-write-free:

- Bootstrap is **single-choice**: `_support/bootstrap.php`
- PHPUnit result cache MUST be fully disabled:
  - canonical mechanism: invoke PHPUnit with `--do-not-cache-result`
- The XML config MUST NOT declare cache paths or report outputs that write into the repo:
  - no `.phpunit.result.cache`
  - no junit/coverage/testdox/html/xml outputs written to disk

---

## Bootstrap rules (tools rails bootstrap)

`framework/tools/spikes/_support/bootstrap.php` is the canonical bootstrap for:

- spikes test suite
- gates (they `require_once` bootstrap before scanning)

Autoload probing is **ordered fallback** (single-choice):

1. `framework/vendor/autoload.php`
2. `vendor/autoload.php`

No other probing is allowed. If autoload is missing, bootstrap fails deterministically:

- line 1: `CORETSIA_SPIKES_BOOTSTRAP_AUTOLOAD_MISSING`
- line 2: `autoload-missing`
- exit code: `1`

---

## Fixtures

All fixtures live under:

- `framework/tools/spikes/fixtures/**`

Base namespaces (may expand with later epics):

- `repo_min/` — minimal repo inputs (fingerprint/config spikes)
- `payloads_min/` — minimal payload inputs
- `deptrac_min/` — minimal deptrac inputs
- `workspace_min/` — minimal workspace inputs

Rules:

- spikes read fixtures only
- spikes MUST NOT mutate fixtures at runtime
- runtime writes go to temp directories only (see `CORETSIA_SPIKES_TMP` below)

---

## Temporary directories

Rails may set:

- `CORETSIA_SPIKES_TMP` — a temp root path outside the repo

Spikes MUST treat it as the only place for writes during tests/determinism runs.

---

## CI rails

CI must include two jobs:

- `spikes` (Linux): runs `composer spike:test`
- `determinism` (Linux + Windows): runs `composer spike:test:determinism`

Windows rails MUST enforce deterministic git settings (single-choice):

- `git config --global core.autocrlf false`
- `git config --global core.symlinks true`

The Windows job must run in an environment that allows symlink creation.

---

## Error codes (deterministic, shared)

Phase 0 rails use deterministic string error codes (stable identifiers).
They are owned by `framework/tools/spikes/_support/ErrorCodes.php`.

Key codes (initial registry for 0.20.0):

- `CORETSIA_DETERMINISM_GIT_REQUIRED`
- `CORETSIA_DETERMINISM_WORKTREE_DIRTY`
- `CORETSIA_DETERMINISM_RERUN_FAILED`
- `CORETSIA_SPIKES_BOOTSTRAP_AUTOLOAD_MISSING`
- `CORETSIA_SPIKES_FIXTURE_PATH_INVALID`
- `CORETSIA_SPIKES_OUTPUT_BYPASS_DETECTED`
- `CORETSIA_SPIKES_OUTPUT_GATE_SCAN_FAILED`

Output contract for gates/runner:

- line 1: `CODE` only
- next lines (optional): stable short reason tokens / normalized relative paths only
- never print absolute paths or secret-like values

---

## Examples (valid/invalid)

### ✅ Valid: spike module uses fixtures and throws deterministic exceptions

```php
<?php

declare(strict_types=1);

use Coretsia\Tools\Spikes\_support\FixtureRoot;

// ok: fixtures access is centralized, no CWD reliance
$repo = FixtureRoot::path('repo_min/skeleton');

// ok: spike logic returns data or throws DeterministicException (no output)
return $repo;
```

### ❌ Invalid: importing runtime code from `framework/packages/**`

```php
<?php

declare(strict_types=1);

// forbidden: runtime package import
use Coretsia\Kernel\Runtime\KernelRuntime;
```

### ❌ Invalid: path-based import from `framework/packages/**/src/**`

```php
<?php

require __DIR__ . '/../../../packages/core/kernel/src/Runtime/KernelRuntime.php';
```

### ❌ Invalid: output bypass in spike business logic

```php
<?php

echo "debug\n";      // forbidden
var_dump($x);        // forbidden
fwrite(STDOUT, "x"); // forbidden (stdout/stderr sink)
```

### ✅ Valid: rails-only diagnostics via ConsoleOutput (rails code only)

```php
<?php

declare(strict_types=1);

use Coretsia\Tools\Spikes\_support\ConsoleOutput;

// allowed ONLY in rails (gates/runner/bootstrap diagnostics)
ConsoleOutput::line('SOME_DETERMINISTIC_CODE');
```

---

## Notes about CLI

A runtime CLI package (`coretsia/cli`) MAY exist as a UX entrypoint, but it does **not** change spikes boundary:

- spike implementations remain tools-only under `framework/tools/spikes/**`
- a devtools command pack (e.g. `coretsia/cli-spikes`) dispatches into spikes; spikes MUST NOT depend on it

---

## Out of scope (for Phase 0 spikes rails)

- no production runtime behavior
- no plugin/extensibility frameworks
- no dependency on `core/kernel` unless explicitly stated by a later epic
