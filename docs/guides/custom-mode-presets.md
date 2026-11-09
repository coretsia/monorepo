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

# Custom mode presets

Goal: define an application-owned runtime module composition, select it for an application target, and reconcile its physical Composer dependencies through the canonical DependencySync workflow.

Scope: Consumer applications based on `coretsia/skeleton`. Commands in this guide run from the **Skeleton application root**, not the Coretsia monorepo root.

This is an operational guide. Normative preset schema and module-selection rules belong to `docs/ssot/modes.md`; physical installation and recovery rules belong to `docs/ssot/application-dependency-sync.md`.

---

## 1) What a Custom preset is

Coretsia reserves four canonical preset names:

```text
micro
express
hybrid
enterprise
```

A **Custom preset** is an application-owned preset with another safe name. `Custom` describes the source namespace, not an additional reserved mode or a separate Composer installation mechanism.

A selected Custom preset:

- declares `required` and `modules` as runtime `ModuleId` roots;
- is loaded from `<applicationRoot>/<kernel.modes.overrides_path>/<name>.php`;
- passes the same schema and module-graph validation as a canonical preset;
- participates in the same target-local `ModuleSelection` and `ModulePlan` pipeline;
- does NOT implicitly install packages when its PHP file is created or edited.

With the current Kernel defaults, `kernel.modes.overrides_path` is `config/modes`, relative to the application root. It is NOT relative to the monorepo root or the Kernel package directory.

The requested preset name determines its namespace before filesystem lookup. A missing Custom preset does not fall back to a canonical preset.

---

## 2) Create an application-owned preset

Choose a name such as `my-web` and create:

```text
<applicationRoot>/
  config/
    app.php
    modes/
      my-web.php
```

File: `config/modes/my-web.php`

```php
<?php

declare(strict_types=1);

return [
    'schemaVersion' => 1,
    'name' => 'my-web',
    'description' => 'Custom web application module composition.',
    'required' => [
        'core.foundation',
        'core.kernel',
    ],
    'modules' => [
        'platform.cli',
        'platform.logging',
    ],
    'featureBundles' => [],
    'metadata' => [],
];
```

This example uses implemented module IDs and the canonical seven-key schema. It does not introduce new runtime modules or Composer packages.

### 2.1 Preset name policy

Custom names MUST:

- match `^[a-z][a-z0-9-]{0,63}$` (maximum 64 bytes);
- match the PHP filename exactly, excluding `.php`;
- differ from `micro`, `express`, `hybrid`, and `enterprise`.

For example, `my-web` and `worker-lite` are valid names. `MyWeb`, `my_web`, and a file attempting to redefine `micro` are invalid.

An application MUST NOT place `micro.php`, `express.php`, `hybrid.php`, or `enterprise.php` in its Custom preset directory. Existing reserved regular files or symbolic links are rejected **even when another preset is selected**.

### 2.2 Schema version 1

The returned PHP array MUST contain exactly these top-level keys:

| Key | Contract |
|-----|----------|
| `schemaVersion` | Integer `1`. |
| `name` | Safe preset name equal to the requested filename. |
| `description` | `null` or a non-empty safe description (maximum 512 bytes). |
| `required` | List of non-excludable canonical runtime module IDs. |
| `modules` | List of selectable canonical runtime module IDs. |
| `featureBundles` | Deterministic JSON-like policy map. |
| `metadata` | Deterministic JSON-like metadata map. |

All seven keys are required, including `featureBundles` and `metadata` when empty. The `required` and `modules` lists MUST contain unique canonical `ModuleId` strings and MUST NOT overlap. Unexpected keys and wrapper structures such as `['kernel' => ...]` are rejected.

`featureBundles` and `metadata` are descriptive/policy data; adding values to them does not create Composer package requirements or substitute for installed module descriptors. Consult `docs/ssot/modes.md` for their allowed nested-value, size, and safety limits.

---

## 3) Select the Custom preset for an active target

File: `config/app.php`

```php
<?php

declare(strict_types=1);

return [
    'activeTargets' => [
        'web',
    ],
    'presets' => [
        'web' => 'my-web',
    ],
];
```

`activeTargets` determines the configuration-owned application set for DependencySync. `presets` selects the effective preset **per target**; it does not add targets by itself.

Supported application target names are `api`, `console`, `web`, and `worker`. For an absent `activeTargets`, DependencySync uses the compatibility fallback `['web']`; an explicitly empty list is invalid.

A global `preset` may provide a fallback for targets without a target-specific selection. The Kernel's default is `kernel.boot.default_preset = micro` when no higher-priority preset applies.

### 3.1 Equivalent dotenv input

Instead of putting the example selection in `config/app.php`, the following Phase A inputs may be supplied through an applicable dotenv source:

```dotenv
CORETSIA_BOOT_ACTIVE_TARGETS=["web"]
CORETSIA_BOOT_PRESETS={"web":"my-web"}
```

The effective values depend on the canonical Phase A source precedence and frozen `strict_dotenv` / `allow_system` policy. Process environment keys are not always authoritative: under `strict_dotenv`, process bootstrap overrides are not accepted as configuration sources.

Do not create a second, application-specific dotenv parser or a separate preset registry. See `docs/ssot/config-precedence-matrix.md` and `docs/ssot/config-merge-order.md` for the source-merge contracts.

---

## 4) Plan, review, and apply physical dependencies

Prerequisite: the Skeleton's Kernel-only Composer bootstrap is installed and the project-root `coretsia` launcher is available.

Run from the **Skeleton application root**:

```bash
php coretsia plan
php coretsia review
php coretsia apply
```

- `plan` builds a project-wide installation plan without Composer effects.
- `review` performs read-only reconciliation and validation without intentionally changing installed dependencies.
- `apply` is the effectful operation. It re-resolves configuration under the existing project lock, reconciles managed Composer requirements, and performs fresh-process installed verification.

The same adapter can be invoked directly:

```bash
php bin/dependency-sync.php plan
php bin/dependency-sync.php review
php bin/dependency-sync.php apply
```

DependencySync obtains `activeTargets` from Phase A configuration, NOT from CLI `--target` flags. `--target` is not supported by these three operations.

For a temporary, non-persistent preview of this Custom preset, `plan` and `review` allow:

```bash
php coretsia plan --preset=web=my-web
php coretsia review --preset=web=my-web
```

`apply` MUST NOT receive `--preset`. To install a Custom composition, select it persistently through the effective Phase A configuration before running `apply`.

Only `apply` accepts the existing effect-authorization flags when needed:

```text
--allow-composer-scripts
--allow-composer-plugins
--allow-broad-update
--allow-repair
```

These flags do not alter preset ownership or module-selection rules. DependencySync preserves its machine JSON result/error protocol and existing Composer failure/recovery policy.

---

## 5) Runtime modules versus Composer packages

A Custom preset defines **runtime selection intent**, not arbitrary package-install instructions.

The Kernel validates two separate boundaries:

1. **Installation planning:** the release installation catalog supplies known module metadata, dependency closure, and the managed Composer requirements union across all active targets.
2. **Runtime composition:** installed Composer module metadata is discovered and validated to build a target-local `ModulePlan`.

For the example in section 2, the selected roots are:

```text
required: core.foundation, core.kernel
modules:  platform.cli, platform.logging
```

The dependency graph adds the necessary transitive modules, including `platform.errors` and `platform.redaction`. They are not additional preset roots.

A module MUST have an implemented runtime descriptor and must be available through the applicable discovery/catalog boundary. Listing an invented or future module ID in `modules` does not create its implementation or make Composer install it automatically.

`required` entries cannot be removed by target-local exclusions. `modules` entries can be excluded when doing so does not violate required dependencies or graph conflict rules.

### 5.1 Optional target-local module overrides

A Custom preset may use the same target-local overrides as canonical presets. For example, to omit the selectable Logging root for `web`:

```php
'moduleOverrides' => [
    'web' => [
        'exclude' => [
            'platform.logging',
        ],
    ],
],
```

Place this entry inside the array returned by `config/app.php`. The Kernel still validates dependency closure: excluding a module needed by another enabled module fails rather than silently breaking its graph.

Excluding a runtime module for one target does not automatically uninstall its Composer package if another active target requires it.

---

## 6) Validation, determinism, and failure behavior

- The selected PHP preset is loaded through the Kernel-owned, namespace-bound `FilesystemModePresetLoader` and validated by `ModePresetSchemaValidator`.
- Custom source directories and existing files must remain within their owning application root; invalid symlinks and path escapes fail closed.
- Preset loading is not a general-purpose extension bootstrap. Files SHOULD return deterministic, data-only arrays without side effects, output, environment mutation, secrets, or runtime service construction.
- A missing Custom file is a missing preset. It does not fall back to Kernel-owned presets.
- Invalid names, malformed schema, overlapping module lists, unknown catalog modules, and dependency conflicts are rejected by their respective existing Kernel owners.
- During effectful synchronization, changes to effective active targets, presets, or target-local module overrides between approval and locked replan cause a project-state mismatch **before Composer effects**.
- Unrelated source provenance, debug flags, and artifact cache location do not change the canonical installation identity by themselves.

Ordinary PHP output written while the selected preset executes is contained by the Kernel loader, but applications MUST NOT rely on output, direct stream writes, output-buffer manipulation, or process termination as part of preset behavior.

---

## 7) Common failures and fixes

### 7.1 Custom file not found

Check that `config/modes/my-web.php` exists **under the Skeleton application root**, and that the filename and returned `name` are identical. No canonical fallback is performed for missing Custom presets.

### 7.2 Invalid schema or module IDs

Verify the exact seven-key schema, `schemaVersion => 1`, list-shaped `required` / `modules`, canonical module IDs, unique entries, and disjoint required/selectable roots. Verify that each requested module is implemented and supported by the applicable installation catalog.

### 7.3 Canonical preset override rejected

Remove reserved filenames (`micro.php`, `express.php`, `hybrid.php`, `enterprise.php`) from the application-owned Custom preset directory. Do not attempt to replace Kernel-owned canonical resources.

### 7.4 `--target` or `apply --preset` rejected

Move application membership to `activeTargets` and persistent Custom selection to `presets` in Phase A configuration. Use `--preset=target=name` only for `plan` or `review`. Kernel Ops commands are separate and may still require `--target` for an explicit runtime operation target.

### 7.5 A module is selected but unavailable

Confirm it is present in the canonical release installation catalog, is implemented as a runtime module, and has compatible dependencies. `plan`/`review` expose the intended project-wide installation closure; `apply` is required for physical dependency reconciliation. Do not attempt to repair this by editing Kernel ModePreset resources or generated catalog metadata by hand.

---

## 8) Related documentation

- `docs/ssot/modes.md` — canonical mode names, preset schema, Custom namespace ownership, and target-local selection.
- `docs/ssot/application-dependency-sync.md` — configuration-owned installation, Composer reconciliation, verification, and recovery.
- `docs/ssot/modules-and-manifests.md` — runtime ModulePlan and discovery contracts.
- `docs/ssot/config-precedence-matrix.md` — bootstrap source precedence and environment policies.
- `docs/ssot/config-merge-order.md` — structured bootstrap merge and absence/empty semantics.
- `docs/guides/commands.md` — canonical command catalog.
