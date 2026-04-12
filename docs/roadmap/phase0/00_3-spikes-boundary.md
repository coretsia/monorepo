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

# Phase 0 — Spikes boundary (tools-only, single-choice)

> **Canonical / single-choice.**  
> Цей документ фіксує **єдину** межу для Phase 0 spikes. Будь-які інші документи/реалізації **MUST** відповідати цьому файлу і **MUST NOT** вводити альтернативні “either/or” правила.

---

## 0) Scope

- **Scope:** Phase 0 spikes / prototypes та Phase 0 tooling rails.
- **Goal:** жорстко зацементувати, що spikes — це **tools-only**, і вони **не можуть** “просочитися” в runtime-пакети.
- **Non-goals:**
  - цей документ **не** вводить runtime behavior;
  - цей документ **не** вводить plugin/extensibility frameworks;
  - цей документ **не** змінює Phase 0 dependency SSoT (`00_2-dependency-table.md`);
  - цей документ **MUST NOT** вимагати, вводити або імплікувати compile-time dependency на `core/kernel`
    (якщо це явно не сказано в epic / не належить до scope іншого епіку).

---

## 1) Canonical references

### 1.1 Packaging law (MUST)

Всі твердження про “package vs tooling” **MUST** спиратися на monorepo packaging law:

- `docs/architecture/PACKAGING.md`

Ключове (для цього boundary):

- `framework/packages/**` — **publishable units** (runtime/devtools packages).
- `framework/tools/**` — **non-publishable tooling** (gates, rails, spikes, generators).  
  (див. `docs/architecture/PACKAGING.md`)

### 1.2 Phase 0 dependency truth (MUST)

Цей boundary **не** є таблицею залежностей. Єдина truth-source для Phase 0 compile-time edges:

- `docs/roadmap/phase0/00_2-dependency-table.md`

---

## 2) Terms (normative)

- **Spike** — прототип/експеримент Phase 0, який допускає швидкі ітерації, але **MUST** бути детермінований, rerun-no-diff та без leakage секретів.
- **Tooling-only** — код, який живе у `framework/tools/**` і **MUST NOT** бути частиною production/runtime поверхні.
- **Runtime packages area** — будь-які publishable units під `framework/packages/**` (включно з `core/*`, `platform/*`, `integrations/*`, `enterprise/*`, `devtools/*`, `presets/*`).
- **Path-based import** — будь-який `require|require_once|include|include_once` шляхом до `framework/packages/**/src/**`
  (або еквівалент, включно з `..`/backslashes).

---

## 3) Boundary decision (single-choice) (MUST)

### 3.1 Spikes location (MUST)

- Spikes **MUST** жити **тільки** під:
  - `framework/tools/spikes/**`

### 3.2 Spikes MUST NOT live in runtime packages (MUST NOT)

- Spike implementations **MUST NOT** жити під:
  - `framework/packages/**`

Це включає будь-які класи/скрипти “spike-логіки”, навіть якщо їх “зручно” запускати через CLI.

### 3.3 Spikes MUST NOT import runtime code (MUST NOT)

Spikes **MUST NOT** імпортувати (namespace imports) runtime packages:

- `core/*`
- `platform/*`
- `integrations/*`
- `enterprise/*`
- `presets/*`

Тобто spikes **MUST NOT** мати `use Coretsia\...` імпорти до runtime namespaces (наприклад `Coretsia\Kernel\...`, `Coretsia\Foundation\...`, `Coretsia\Platform\...`, `Coretsia\Integrations\...`).

> Note: окремо дозволений один tooling-only виняток — `coretsia/internal-toolkit` (див. §4).

### 3.4 Spikes MUST NOT do path-based imports from packages/src (MUST NOT)

Spikes **MUST NOT** робити `require/include` з `framework/packages/**/src/**` (в будь-якій формі шляху):

- `framework/packages/core/*/src/**`
- `framework/packages/platform/*/src/**`
- `framework/packages/integrations/*/src/**`
- etc.

Це правило **забороняє** “обхід” залежностей через файлові шляхи.

---

## 4) Single-choice exception: `coretsia/internal-toolkit` (MUST)

### 4.1 Allowed Coretsia dependency (exactly one) (MAY)

Spikes **MAY** залежати рівно від **однієї** Coretsia internal tooling library:

- `coretsia/internal-toolkit`

І **MUST** використовувати її **лише** через Composer autoload (namespace-based), а не через `require` по шляху.

### 4.2 What the exception covers (CLOSED set) (MUST)

Цей виняток **закритий** і покриває **тільки** канонічні *determinism primitives*:

- slug casing helpers: `Slug::*`
- path normalization helpers: `Path::*`
- stable JSON encoding helpers: `Json::*`

### 4.3 What the exception does NOT cover (MUST NOT)

- Tooling **MUST NOT** дублювати `Slug::*`, `Path::*`, `Json::*` де-небудь під `framework/tools/**`.
- Deterministic file IO helpers (наприклад Phase 0 `DeterministicFile` для EOL/LF normalization і safe writes)
  **не** є частиною `internal-toolkit` primitive set і **MAY** жити у:
  - `framework/tools/spikes/_support/**`
    за умови, що вони **не** дублюють `Slug::*`, `Path::*`, `Json::*`.

> Цей виняток не “відкриває двері” для перенесення будь-якої Phase 0 логіки в publishable packages.

---

## 5) Third-party tooling deps (allowed) vs Coretsia runtime deps (forbidden) (MUST)

- Spikes **MAY** використовувати third-party dev tooling dependencies (наприклад PHPUnit) через tooling workspace.
- Spikes **MUST NOT** мати compile-time dependencies на Coretsia runtime packages:
  - `core/*`, `platform/*`, `integrations/*` (і інші runtime publishable units),
    навіть якщо “це тільки для прототипу”.

---

## 6) Non-primitive Phase 0 rails infrastructure (allowed in tools) (MAY)

Phase 0 rails infrastructure (gates/runner/diagnostics utilities) **MAY** жити під:

- `framework/tools/spikes/_support/**`

за умови, що вона:

- поважає заборони з §3 (no runtime imports, no path-imports з `framework/packages/**/src/**`);
- не дублює primitive set `Slug::*`, `Path::*`, `Json::*` (див. §4).

Приклади допустимої rails-інфраструктури:

- ErrorCodes registry,
- deterministic exception carrier,
- deterministic IO wrappers (не primitives),
- CI rails runner,
- gates scripts,
- safe console output adapter (якщо policy/rails це дозволяє).

---

## 7) CLI exception (explicit; does NOT change spikes boundary) (MUST)

### 7.1 CLI runtime package may exist (MAY)

- CLI runtime package `coretsia/cli` **MAY** існувати як UX entrypoint.

### 7.2 Spike command implementations MUST NOT live in production runtime packages (MUST NOT)

- Phase 0 spike command **IMPLEMENTATIONS MUST NOT** жити в production runtime packages.
- Spike commands **MUST** жити:
  - або як tools-only scripts у `framework/tools/spikes/**`,
  - або в devtools-only package (`coretsia/cli-spikes`, epic `0.140.0`) як thin dispatcher **без** spike бізнес-логіки.

### 7.3 Production safety invariant (MUST)

- Installing only `coretsia/cli` **MUST NOT** включати doctor/spike/deptrac/workspace command classes у package.

### 7.4 Boundary remains tools-only (MUST)

- CLI **не** дає права переносити spikes з `framework/tools/spikes/**`.
- CLI може лише dispatch/exec spikes і читати fixtures; spikes лишаються tools-only.

> Цей boundary decision **не** вимагає, щоб `0.120/0.130/0.140` вже були реалізовані — він лише фіксує правило.

---

## 8) Examples (copy-pastable)

> **DoD note:** мінімальний набір прикладів (3–4) — це **§8.1–§8.4** (4 приклади).  
> **§8.5** — додатковий edge-case приклад (не змінює boundary та не розширює rules).

### 8.1 ✅ Valid: spike lives under `framework/tools/spikes/**` and uses `internal-toolkit` via autoload

```php
<?php

declare(strict_types=1);

use Coretsia\Devtools\InternalToolkit\Path;
use Coretsia\Devtools\InternalToolkit\Json;

$rel = Path::normalizeRelative('a\\b/../c');
echo Json::encodeStable(['path' => $rel]) . "\n";
```

**Placement (valid):**

```txt
framework/tools/spikes/example_valid/run.php
```

---

### 8.2 ✅ Valid: non-primitive rails helper under `_support/**` (no duplication of Slug/Path/Json)

```php
<?php

declare(strict_types=1);

final class DeterministicFile
{
    public static function readTextNormalizedEol(string $path): string
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('CORETSIA_SPIKES_IO_READ_FAILED');
        }

        $raw = str_replace("\r\n", "\n", $raw);
        $raw = str_replace("\r", "\n", $raw);

        return $raw;
    }
}
```

**Placement (valid):**

```txt
framework/tools/spikes/_support/DeterministicFile.php
```

---

### 8.3 ❌ Invalid: moving spike implementation into `framework/packages/**` (boundary violation)

**Placement (invalid):**

```txt
framework/packages/core/kernel/src/Spike/FingerprintPrototype.php
```

**File content (invalid even if syntactically correct):**

```php
<?php

declare(strict_types=1);

namespace Coretsia\Kernel\Spike;

/**
 * ❌ Boundary violation:
 * A spike/prototype implementation MUST NOT live under framework/packages/**,
 * even if it is "temporary" and even if CLI can dispatch it.
 */
final class FingerprintPrototype
{
    public function run(): void
    {
        // spike logic placeholder
    }
}
```

Навіть якщо це “тимчасово” і навіть якщо CLI може це запустити — це **MUST** вважатися порушенням boundary.

---

### 8.4 ❌ Invalid: importing runtime namespaces from spikes

```php
<?php

declare(strict_types=1);

use Coretsia\Kernel\KernelRuntime; // ❌ forbidden runtime import

final class Spike
{
}
```

---

### 8.5 ❌ Invalid (additional): path-based import from `framework/packages/**/src/**`

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../../../packages/core/kernel/src/KernelRuntime.php'; // ❌ forbidden
```

---

## 9) Cutline / enforcement intent (MUST)

- Цей документ — **cutline blocker** для Phase 0:
  - поки boundary не зацементований, наступні tooling epics не повинні розширювати поверхню spikes.
- Будь-яка реалізація Phase 0 tooling **MUST** бути сумісна з цим boundary.
- Подальші epics **SHOULD** додати enforcement gates, але цей epic є **doc-only** і фіксує правило.

---

## 10) Deliverables (exact paths only) (MUST)

### Creates

- `docs/roadmap/phase0/00_3-spikes-boundary.md` — canonical boundary decision (single-choice) + MUST/MUST NOT rules + examples

### Modifies

N/A (doc-only)

---

## 11) See also

- Packaging law (publishable vs tooling): `../../architecture/PACKAGING.md`
- Repo structure overview: `../../architecture/STRUCTURE.md`
- Phase 0 build order (guidance): `./00_1-build-order.md`
- Phase 0 dependency truth (SSoT): `./00_2-dependency-table.md`
- Prelude rules snapshot: `../ROADMAP.md`
