<!--
  Coretsia Skeleton

  Project: Coretsia Skeleton
  Authors: Vladyslav Mudrichenko and contributors
  Copyright (c) 2026 Vladyslav Mudrichenko

  SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
  SPDX-License-Identifier: Apache-2.0

  For contributors list, see git history.
  See LICENSE and NOTICE in the project root for full license information.
-->

# Coretsia Application

This is a Coretsia application created from the `coretsia/skeleton` project template.

The project starts with Composer-managed runtime dependencies, application configuration, app targets, environment configuration, and mutable runtime state under `var/`.

Adapt it as the application evolves.

## Application root

The project directory is the application root.

Installed Coretsia packages operate within this application context.

## Dependencies

The application baseline requires:

```text
PHP ^8.4
coretsia/framework
```

`coretsia/framework` is the only project-owned Coretsia baseline root requirement. It is a dependency-only Kernel bootstrap metapackage with exactly `php` and `coretsia/core-kernel` as direct requirements.

The initial Composer bootstrap graph is:

```text
coretsia/skeleton
    -> coretsia/framework
        -> coretsia/core-kernel
            -> coretsia/core-contracts
            -> coretsia/core-foundation
                -> coretsia/core-contracts
```

Composer also installs the external transitive dependencies required by the Kernel/Foundation baseline.

The initial installation does not require `coretsia/platform-cli`, `coretsia/platform-logging`, or an installed Micro runtime. These mode-selected packages become part of the physical installation only through the applicable dependency closure and authorized Composer synchronization, unless already present through independently owned requirements.

The default Kernel preset is `micro` (`kernel.boot.default_preset`). It requires the non-excludable `core.foundation` and `core.kernel` modules and selects `platform.cli` and `platform.logging` as excludable runtime roots.

This is mode-selection policy, not the initial Composer bootstrap graph. After the required packages have been installed and verified, the normal Micro runtime resolves the following transitive enabled-module closure:

```text
core.foundation, core.kernel, platform.cli, platform.errors, platform.logging, platform.redaction
```

Errors and Redaction are transitive module dependencies, not additional preset roots. Missing selected `platform.logging` or required `platform.errors`, or excluding required `platform.redaction`, fails closed during module composition.

Composer installation establishes the physical package graph; it does not enable runtime modules. The Kernel independently resolves a target-local `ModulePlan` from the effective preset and module overrides. An explicit `moduleOverrides.exclude` for `platform.logging` disables Logging for that target only. A subsequent authorized DependencySync apply may remove its managed Composer requirement if no selected target needs the package; Redaction requirements of other selected modules remain in the project-wide union. The separate string setting `logging.driver='null'` retains the Logging module but resolves `LoggerInterface` to Foundation `NoopLogger`. The normal CLI host requires an enabled `platform.cli` in its Console plan and fails closed when it is excluded.

The project `composer.json` defines the application's runtime and application-specific dependencies.

Composer records the resolved dependency graph in `composer.lock` and installs dependencies under `vendor/`.

## HTTP PSR-7/17 factory selection (optional)

HTTP request handling requires `platform.http` to be installed and explicitly selected for the target, together with a compatible PSR-7/17 implementation. The `platform/http` package depends on PSR interfaces but does not install a concrete message/factory implementation automatically. Installing an implementation through Composer alone does not select `platform.http`, activate its middleware, or make HTTP request handling available.

Install one of the supported concrete implementations from the application root:

```bash
composer require nyholm/psr7
composer require guzzlehttp/psr7
composer require laminas/laminas-diactoros
composer require slim/psr7
```

These commands are alternatives; install the implementation chosen for the target. Set the matching target-local `http.factory` value:

- `nyholm` for `nyholm/psr7`;
- `guzzlehttp` for `guzzlehttp/psr7`;
- `laminas` for `laminas/laminas-diactoros`;
- `slim` for `slim/psr7`.

For example, `apps/web/config/http.php` may contain the following Phase B root-specific override after the HTTP module is selected:

```php
return [
    'factory' => 'nyholm',
];
```

The built-in selections contribute the matching `ResponseFactoryInterface` and `StreamFactoryInterface` bindings through canonical declarative DI. No application-owned DI provider is required for those two interfaces when a built-in selection is used. The selection is explicit and does not fall back to another installed implementation.

The default `http.factory=external` selects custom, application/distribution-owned factory binding. In this mode, `platform/http` does not contribute implementation services or aliases; the consuming application must provide compatible effective declarative DI bindings for both `Psr\Http\Message\ResponseFactoryInterface` and `Psr\Http\Message\StreamFactoryInterface` using its own provider configuration.

This describes factory configuration only. Complete HTTP target bootstrap, front-controller registration, SAPI request construction, response emission, and browser-serving instructions are not yet part of the Skeleton application baseline. No HTTP entrypoint, default HTTP configuration, or runtime implementation discovery is introduced by these steps.

## Command-line interface

The Skeleton project-root `coretsia` launcher has two distinct execution paths.

Immediately after the initial Kernel-only Composer installation, the project-owned dependency synchronization operations are available:

```bash
php coretsia plan
php coretsia review
php coretsia apply
```

These operations route directly to `bin/dependency-sync.php` before normal CLI host boot. They use Kernel-owned DependencySync and do not require an installed `platform.cli` package.

Normal CLI commands require the selected CLI runtime packages to have been installed and verified through DependencySync. After the canonical Micro installation, run from the Skeleton application root:

```bash
php coretsia list
php coretsia help
php coretsia doctor
php coretsia debug:modules --target=web
php coretsia config:validate --target=web
php coretsia config:debug --target=web
php coretsia config:compile --target=web
php coretsia cache:verify --target=web
```

The project-root launcher delegates normal CLI commands to the Composer-installed `vendor/bin/coretsia` binary. Running `php vendor/bin/coretsia <command>` invokes the packaged CLI implementation directly and bypasses the Skeleton-specific DependencySync routes.

If the packaged CLI binary is not installed or readable, the Skeleton launcher writes exactly `CORETSIA_CLI_BOOTSTRAP_FAILED: autoload-unavailable` followed by one LF to stderr and exits `1`, without exposing paths or exception details. This does not prevent Kernel-only DependencySync operations when the baseline Composer autoloader is available.

Normal commands bootstrap the Kernel-owned Console operations host using the resolved Console `ModulePlan`. Target-scoped Kernel Ops commands still require an explicit `--target=web|api|console|worker`; this operation target does not change the CLI host's Console target.

Built-in `list`, `help`, and `doctor` commands do not require `--target`. The exact `doctor` command remains the ultra-early CLI diagnostic path and does not require Kernel host boot or generated artifacts, but its packaged CLI executable must still be installed.

## Application dependency synchronization

Dependency synchronization is a project-owned consumer operation. It is never triggered implicitly by `composer create-project`, preset selection, ordinary CLI execution, or runtime boot.

The canonical initial consumer workflow is:

```bash
composer create-project coretsia/skeleton my-app
cd my-app
php coretsia plan
php coretsia review
php coretsia apply
```

The commands run from the Skeleton application root after its Kernel-only Composer baseline has been installed.

The canonical dependency synchronization flow is:

```text
create-project / Kernel-only Composer installation
    -> BootstrapSourceResolver / one project-wide source snapshot
    -> configuration-owned activeTargets
    -> independent target-local effective presets and module policy
    -> plan / review
    -> explicitly authorized apply
    -> Composer lock/vendor validation
    -> fresh configuration and installed ModulePlan verification
```

Project-wide application membership is defined by `activeTargets` in `config/app.php` or permitted `CORETSIA_BOOT_ACTIVE_TARGETS` environment sources.

An absent `activeTargets` uses the DependencySync-owned fallback `['web']`. An explicitly empty list is invalid. Application directories, preset-map keys, and installed packages do not select additional targets.

The default target-local preset is `micro` unless a higher-priority global or target-specific bootstrap value applies.

The three operations are:

- `plan`: resolve the effective configured installation plan without Composer effects;
- `review`: perform read-only managed-root reconciliation without intentional Composer changes;
- `apply`: reconcile the configuration-owned project-wide package union under the existing authorization, lock, verification, and recovery policies.

The project-root launcher delegates these operations to the canonical `bin/dependency-sync.php` adapter before normal `platform.cli` host boot. They preserve the existing machine JSON success/error envelopes, `schemaVersion=1`, stable error codes, bounded context, and exit codes.

The direct adapter equivalents are:

```bash
php bin/dependency-sync.php plan
php bin/dependency-sync.php review
php bin/dependency-sync.php apply
```

DependencySync does not accept a `--target` option. Targets are selected through persistent bootstrap configuration, not per-command arguments.

For read-only analysis, `plan` and `review` support a temporary target-qualified preset override:

```bash
php coretsia plan --preset=web=enterprise
php coretsia review --preset=web=enterprise
```

The syntax is `--preset=<target>=<preset>`. The target must already belong to the effective `activeTargets` set. The override affects only the specified target's read-only plan and is never persisted.

`apply` rejects every `--preset` argument, including values equal to persistent configuration. To change the applied preset, update the supported bootstrap configuration source before applying.

The four existing apply-only effect authorization flags are:

```text
--allow-composer-scripts
--allow-composer-plugins
--allow-broad-update
--allow-repair
```

Composer scripts and plugins remain disabled unless separately authorized. Broad updates and installed-vendor repair retain their existing explicit policy gates. The flags are rejected for plan/review.

DependencySync records the Coretsia root requirements it owns under `extra.coretsia.dependencySync` using `managedRequire` and `lastAppliedRequire`. An untracked root remains project-owned and is never silently claimed.

Every explicit non-Coretsia root in `require` or `require-dev` remains protected. Its root constraint, locked identity, and installed identity MUST remain unchanged during synchronization.

Effectful apply acquires the existing project lock:

```text
var/locks/dependency-sync.lock
```

Under the lock, it re-resolves current configuration-owned application membership and installation-relevant bootstrap values before Composer effects. A changed approved installation plan fails before mutation.

Composer effects are not transactionally atomic across `composer.json`, `composer.lock`, and `vendor/`. After effects begin, subsequent Composer or verification failures may return `RECOVERY_REQUIRED` with a recovery receipt stored under:

```text
var/dependency-sync/recovery/<recoveryReceiptId>/
```

Fresh verification resolves a new source snapshot and actual `activeTargets` and independently verifies the installed `ModulePlan` of every selected target. It does not trust only the approved verification envelope or reuse temporary preset overrides.

A repeated apply is a stable no-op when effective configuration, managed Composer requirements, lock/vendor state, and target-local installed plans already match.

For example, the following persistent `config/app.php` selects two applications:

```php
return [
    'activeTargets' => ['web', 'worker'],
    'preset' => 'micro',
    'presets' => [
        'worker' => 'enterprise',
    ],
];
```

The equivalent bootstrap environment selection may be represented as:

```dotenv
CORETSIA_BOOT_ACTIVE_TARGETS=["web","worker"]
CORETSIA_BOOT_PRESET=micro
CORETSIA_BOOT_PRESETS={"worker":"enterprise"}
```

With these sources selected under their respective source precedence rules, the resulting module closures are:

```text
web    -> micro      -> core.foundation, core.kernel, platform.cli, platform.errors, platform.logging, platform.redaction
worker -> enterprise -> core.foundation, core.kernel, platform.cli, platform.errors, platform.logging, platform.redaction, platform.worker

project-wide physical module union:
core.foundation, core.kernel, platform.cli, platform.errors, platform.logging, platform.redaction, platform.worker
```

Target-local exclusions do not imply project-wide package removal. Physical installation for another target does not automatically enable that module in the current target's `ModulePlan`.

DependencySync is Kernel-owned and is not registered in the normal `platform.cli` command catalog or UnitOfWork lifecycle.

See [Application Dependency Sync SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/application-dependency-sync.md) in the Coretsia monorepo for the normative planning, ownership, execution, verification, and recovery contract.

## Project structure

The initial project structure is:

```text
./
├── bin/
│   └── dependency-sync.php
├── composer.json
├── coretsia
├── .env.example
├── .gitignore
├── README.md
│
├── config/
│   └── app.php
│
├── apps/
│   └── web/
│       ├── config/
│       └── public/
│
└── var/
    ├── cache-data/
    ├── cache/
    ├── etl/
    ├── locks/
    ├── logs/
    ├── maintenance/
    ├── quarantine/
    ├── sessions/
    └── tmp/
```

## App targets

Application targets live under `apps/<appTarget>/`. The initial `web` target provides `apps/web/config/` for target-specific configuration and `apps/web/public/` as its public directory.

Add additional app-target directories under `apps/` as the application requires. Directory presence does not select a target for DependencySync; installation membership comes exclusively from the effective configuration-owned `activeTargets`, with `['web']` used only when that value is absent. Runtime entrypoints continue to select their AppTarget explicitly and independently.

## Runtime directories

`var/` contains mutable application runtime state such as caches, logs, locks, sessions, temporary data, and generated runtime artifacts.

Mutable contents under `var/` are ignored by Git, while tracked `.gitkeep` files preserve the initial directory structure.

## Configuration

Installed Coretsia packages provide configuration defaults. Application-owned overrides are resolved through the appropriate bootstrap or application configuration phase. Define only the values the application needs.

### Bootstrap configuration

The application provides:

```text
config/app.php
```

This file controls early application bootstrap and accepts only `appEnv`, `activeTargets`, `preset`, `presets`, `moduleOverrides`, `debug`, and `artifactsCacheDir`.

The initial file returns an empty array:

```php
return [];
```

An empty initial config uses the canonical bootstrap defaults. Applications may override these settings using the following structure:

```php
return [
    'appEnv' => 'local', // Examples: local, staging, prod, production.
    'activeTargets' => ['web', 'worker'], // Project-wide installation membership.
    'preset' => 'micro', // micro|express|hybrid|enterprise.

    'presets' => [
        'api' => 'micro', // micro|express|hybrid|enterprise.
        'console' => 'micro', // micro|express|hybrid|enterprise.
        'web' => 'micro', // micro|express|hybrid|enterprise.
        'worker' => 'micro', // Additional presets may require installed packages.
    ],

    'moduleOverrides' => [
        'web' => [
            'include' => [], // Installed ModuleIds to enable explicitly.
            'exclude' => [], // Example: ['platform.logging'].
        ],
    ],

    'debug' => false, // true|false.
    'artifactsCacheDir' => 'var/cache', // Safe application-relative path.
];
```

The `appEnv` value is a validated non-empty environment name, not a fixed enumeration. The documented presets are the four canonical built-in presets; preset overrides may select other valid, explicitly provided presets.

`activeTargets` defines project-wide DependencySync installation membership. It is a non-empty list of unique canonical targets: `api`, `console`, `web`, or `worker`. Absence uses the DependencySync-owned `['web']` fallback; an explicit empty list is invalid.

A target-specific `presets` entry takes precedence over the global `preset` fallback. Explicit target-local `BootstrapInput::preset()` may take precedence over both. Preset-map entries and module-override entries do not add application membership.

`BootstrapSourceResolver` resolves one project-wide immutable source snapshot. `BootstrapConfigResolver` then constructs independent target-local configurations for explicit AppTargets. Neither reading bootstrap configuration nor resolving a preset installs or activates additional packages.

`moduleOverrides.include` and `moduleOverrides.exclude` are target-local ModuleId lists. Required modules and active transitive dependencies cannot be excluded. A physically missing selected module causes bootstrap failure.

The file MUST NOT contain package-owned configuration roots such as `foundation`, `kernel`, `cli`, `errors`, `logging`, or `worker`. Those are loaded separately during application configuration composition.

### Package configuration

All package-owned configuration overrides can be declared together in:

```text
config/roots.php
```

This file returns a map of configuration roots. Unlike `config/app.php`, it is loaded by the application configuration pipeline after bootstrap. It is optional, and the installed packages provide defaults for omitted keys.

The following example shows the complete configuration structure supported by the current Coretsia packages, using their default values:

```php
return [
    'foundation' => [
        'container' => [
            'autowire_concrete' => true, // true|false.
            'allow_reflection_for_concrete' => true, // true|false.
        ],

        'ids' => [
            'default' => 'ulid', // ulid|uuid.
        ],

        'reset' => [
            'tag' => 'kernel.reset', // Non-empty string without whitespace.

            'priority' => [
                'enabled' => true, // true|false.
            ],

            'group' => [
                'default' => 'default', // [a-z][a-z0-9_-]{0,63}.
            ],
        ],
    ],

    'kernel' => [
        'config' => [
            'forbidden_top_level_roots' => [
                'coretsia',
                '_internal',
            ],
        ],

        'boot' => [
            // Phase A overrides belong in config/app.php.
            'default_env' => 'local',
            'default_preset' => 'micro',
            'default_debug' => false,
            'default_artifacts_cache_dir' => 'var/cache',
        ],

        'runtime' => [
            'http_driver' => 'http.classic',
            // http.classic|http.frankenphp|http.swoole|http.roadrunner.
        ],

        'env' => [
            'source_policy' => [
                'default_local' => 'strict_dotenv', // Only strict_dotenv.
                'default_production' => 'allow_system', // Only allow_system.
            ],

            'dotenv' => [
                'files' => [
                    '.env',
                    '.env.local',
                    '.env.<env>',
                    '.env.<env>.local',
                ],
            ],
        ],

        'modules' => [
            'discovery' => [
                'source' => 'composer', // Supported: composer.
                'allowed_sources' => ['composer'],
            ],
        ],

        'modes' => [
            'schema_version' => 1, // Fixed at 1.
            'defaults_path' => 'resources/modes', // Package-relative path.
            'overrides_path' => 'config/modes', // Application-relative path.
        ],

        'fingerprint' => [
            'application_ignore_prefixes' => [
                'var/maintenance',
            ],
        ],

        'uow' => [
            'attributes' => [
                'max_depth' => 10, // Integer >= 1.
                'max_keys' => 200, // Integer >= 1.
            ],
        ],
    ],

    'cli' => [
        'commands' => [
            'overrides' => [], // Dynamic command map: summary, hidden, group.
        ],

        'workflows' => [
            'definitions' => [], // Dynamic workflow map.
        ],

        'output' => [
            'format_default' => 'adaptive', // adaptive|json|table|plain.

            'adaptive' => [
                'interactive' => 'table', // json|table|plain.
                'non_interactive' => 'table', // json|table|plain.
            ],

            'color_default' => 'auto', // auto|always|never.

            'table' => [
                'max_width' => 120, // Integer 40..240.
            ],
        ],
    ],

    'errors' => [
        'reporting' => [
            'enabled' => false, // true|false.
            'min_severity' => 'info', // info|warning|error|critical.
            'exclude_codes' => [], // Non-empty strings without whitespace.
            'sample_rate' => 10000, // Integer 0..10000 (basis points).

            'mapper_failures' => [
                'enabled' => false, // true|false.
            ],
        ],
    ],

    'logging' => [
        'driver' => 'stream', // stream|file|null (string 'null').

        'file' => [
            'path' => '', // Absolute local directory required with driver=file.
            'name' => 'app', // [a-z][a-z0-9_-]{0,63}.
        ],

        'level' => 'info', // debug|info|notice|warning|error|critical|alert|emergency.

        'max_record_bytes' => 65536, // Integer 1024..65536.
        'service_name' => 'auto', // auto or [a-z][a-z0-9_-]{0,63}.
        'format' => 'jsonl', // jsonl|logfmt.
    ],

    // Optional: enable this block only when platform.worker is installed
    // and enabled for the selected application target.
    /*
    'worker' => [
        'workers' => 4, // Integer >= 1.
        'max_requests' => 1000, // Integer >= 1.
        'task_type' => 'queue', // queue|http.
        'socket_path' => 'var/tmp/worker.sock', // Application-relative path.
        'driver' => 'auto', // auto|pcntl|proc.

        'proc' => [
            'command' => [
                '@php',
                'vendor/coretsia/platform-worker/bin/coretsia-worker',
            ],
        ],

        'control' => [
            'transport' => 'auto', // auto|unix|tcp.
        ],

        'tcp' => [
            'host' => '127.0.0.1', // Only 127.0.0.1.
            'port' => 9327, // Integer 1..65535.
        ],

        'state_path' => 'var/tmp/worker.state.json',
        'stop_flag_path' => 'var/tmp/worker.stop',

        'start_timeout_ms' => 10000, // Integer 1..86400000.
        'stop_timeout_ms' => 10000, // Integer 1..86400000.
        'force_kill_timeout_ms' => 1000, // Integer 1..86400000.
    ],
    */
];
```

The configuration example includes package-owned roots used by the canonical Micro runtime after the corresponding packages have been installed and selected. These CLI, Errors, Logging, and Redaction capabilities are not guaranteed by the initial Kernel-only Composer baseline. The commented `worker` block documents the optional Worker package and must only be activated when that package participates in the selected runtime module graph.

`kernel.boot.*` documents package fallback defaults. To change effective early-bootstrap settings, use the corresponding top-level keys in `config/app.php`, permitted `CORETSIA_BOOT_*` environment sources, or explicit `BootstrapInput` where supported. Phase B application configuration is not loaded early enough to replace frozen Phase A bootstrap values.

`cli.commands.overrides` and `cli.workflows.definitions` accept dynamic maps whose entries have additional CLI-owned validation rules. The example uses their valid empty defaults.

The example is illustrative: omitted package keys retain their defaults. Package configuration does not install or enable runtime modules.

Package configuration can be organized either in the aggregate `config/roots.php` file or in separate root-specific files named `config/<root>.php`. Each root-specific file returns only its own configuration subtree, without repeating the root name.

For example, `config/foundation.php`:

```php
return [
    'ids' => [
        'default' => 'uuid', // ulid|uuid.
    ],
];
```

The same configuration can be declared in `config/roots.php`:

```php
return [
    'foundation' => [
        'ids' => [
            'default' => 'uuid',
        ],
    ],
];
```

Both forms produce the same `foundation.ids.default` configuration value. The same split-file convention applies to other declared configuration roots, including `kernel`, `cli`, `errors`, `logging`, and `worker` when their owning modules are enabled.

Root-specific overrides are also supported at environment and app-target levels:

```text
config/<root>.php
config/environments/<appEnv>/<root>.php
apps/<appTarget>/config/<root>.php
apps/<appTarget>/config/environments/<appEnv>/<root>.php
```

Each layer also supports its own aggregate `roots.php`. Within the same layer, a root-specific file takes precedence over matching entries in `roots.php`. Configuration layers are applied in order: application-wide, application environment, app-target, and app-target environment.

Only declared configuration roots are considered for split-file loading; Kernel does not scan configuration directories to discover arbitrary files. Applications may use aggregate files, root-specific files, or both.

## Environment configuration

The application includes:

```text
.env.example
```

The initial `.env.example` contains commented, non-sensitive examples of the canonical Bootstrap Phase A `CORETSIA_BOOT_*` inputs and the supported ConfigKernel Phase B package environment overlays. No environment variable is required by every Coretsia application.

Create a local `.env` file when overrides are needed. Copy `.env.example` and uncomment only the required entries:

```dotenv
FOUNDATION_IDS_DEFAULT=uuid
LOGGING_LEVEL=debug
CLI_OUTPUT_TABLE_MAX_WIDTH=140
```

Environment variable names use uppercase configuration paths with dots and hyphens replaced by underscores. For example, `FOUNDATION_IDS_DEFAULT` overrides `foundation.ids.default`. The Kernel applies environment overlays to the merged package configuration after bootstrap, with higher precedence than PHP configuration files.

Automatic overlays support only scalar ruleset types: `string`, `non-empty-string`, `non-empty-string-no-ws`, `bool`, and `int`. Boolean values accept exactly `true`, `false`, `1`, or `0`. Integers use canonical decimal syntax. Values must satisfy the owning package's validation rules. Unknown environment variable names do not create configuration keys, and unsupported configuration types are not automatically projected.

Lists, maps, relative-safe paths, and bounded `reset-group-id` fields must be configured through PHP configuration files unless an explicit supported environment mapping is provided. For example, `FOUNDATION_RESET_GROUP_DEFAULT` is not an automatic overlay even though `foundation.reset.group.default` is a valid PHP configuration key.

Bootstrap Phase A consumes only the canonical `CORETSIA_BOOT_*` allowlist for bootstrap environment overrides. It supports `CORETSIA_BOOT_APP_ENV`, `CORETSIA_BOOT_ACTIVE_TARGETS`, `CORETSIA_BOOT_PRESET`, `CORETSIA_BOOT_PRESETS`, `CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE`, `CORETSIA_BOOT_MODULE_OVERRIDES_EXCLUDE`, `CORETSIA_BOOT_DEBUG`, and `CORETSIA_BOOT_ARTIFACTS_CACHE_DIR`.

`activeTargets` is a non-empty JSON list. `presets` and each module override field use JSON object maps keyed by canonical AppTarget; `{}` is a no-op, and `{"web":[]}` explicitly clears only the corresponding include or exclude list.

Bootstrap source resolution validates and merges these values independently of ConfigKernel Phase B. Target-specific presets override the merged global preset; module override lists are replaced independently per target and field.

Phase B environment overlays do not reselect Phase A appEnv, presets, activeTargets, module overrides, debug, or the artifact cache directory. For example, `KERNEL_BOOT_DEFAULT_ENV` changes the merged Phase B configuration value only; it does not restart Phase A or reload dotenv files.

By default, Kernel reads these dotenv files in order, skipping missing files:

```text
.env
.env.local
.env.<appEnv>
.env.<appEnv>.local
```

Phase A initially selects appEnv using explicit `BootstrapInput::appEnv()`, process `CORETSIA_BOOT_APP_ENV` only under explicitly requested `AllowSystem`, `.env.local`, `.env`, `config/app.php`, and `kernel.boot.default_env`, in descending precedence.

The selected appEnv is frozen before environment-specific dotenv layers are loaded. `CORETSIA_BOOT_APP_ENV` in an environment-specific layer MUST match the frozen value and MUST NOT initiate another selection.

Other bootstrap values use permitted process environment, `.env.<appEnv>.local`, `.env.<appEnv>`, `.env.local`, `.env`, `config/app.php`, and Kernel defaults in descending precedence. Structured bootstrap maps merge by target and field before ordinary dotenv values are flattened.

Local and other non-production environments use the default `strict_dotenv` policy, which ignores process environment values. `prod` and `production` default to `allow_system`, where permitted process values take precedence over dotenv. The production-like default does not retroactively reselect appEnv from process environment.

Kernel captures an immutable environment repository during bootstrap. Only recognized environment mappings contribute to the validated runtime configuration.

## Version control

The project `.gitignore` excludes local environment files, generated dependencies under `vendor/`, and mutable runtime contents under `var/`.

Keep `.env.example`, tracked `.gitkeep` files, and `composer.lock` under version control.

## Extending the application

The initial project is intentionally small. Add these areas when the application needs them:

```text
modules/
resources/
tests/
config/modes/
```

Application modules are part of the project codebase and are separate from installed Coretsia packages.

## Runtime diagnostics

The Skeleton does not implement a separate logger, error handler, or exception mapper. These concerns belong to their enabled Coretsia runtime modules. With the default Logging `stream` driver, eligible PSR-3 records are written to STDERR independently of CLI command output on STDOUT and command exit codes.

## Security / Redaction

Never commit credentials, tokens, or private deployment values to `.env.example` or configuration files under `config/` and `apps/*/config/`.

`platform.redaction` supplies the shared sensitive-data redaction and sensitive-key classification ports; `platform/logging` owns its strict sink-field admission and mandatory whole-record redaction. The Skeleton does not define a separate redaction policy, classifier, or logging redaction-disable switch.

For documentation and guides, see [coretsia.dev](https://coretsia.dev/).
