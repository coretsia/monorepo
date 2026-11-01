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

`coretsia/framework` is the only project-owned Coretsia baseline root requirement. As a dependency-only Composer metapackage, it installs Kernel, CLI, Logging, and their transitive dependencies:

```text
coretsia/skeleton
    -> coretsia/framework
        -> coretsia/core-kernel
        -> coretsia/platform-cli
            -> coretsia/core-kernel
            -> coretsia/platform-errors
                -> coretsia/core-foundation
                -> coretsia/platform-redaction
            -> coretsia/platform-redaction
        -> coretsia/platform-logging
            -> coretsia/core-kernel
            -> coretsia/core-foundation
            -> coretsia/platform-redaction
```

Composer also installs Coretsia Contracts and the remaining transitive dependencies.

The default Kernel preset is `micro` (`kernel.boot.default_preset`). It requires the non-excludable `core.foundation` and `core.kernel` modules and selects `platform.cli` and `platform.logging` as excludable runtime roots. Their transitive runtime dependencies are resolved into the effective Console `ModulePlan`:

```text
core.foundation, core.kernel, platform.cli, platform.errors, platform.logging, platform.redaction
```

Errors and Redaction are transitive module dependencies, not additional preset roots. Missing selected `platform.logging` or required `platform.errors`, or excluding required `platform.redaction`, fails closed during module composition.

The intended capabilities described by a mode do not automatically materialize unimplemented runtime modules. The current canonical `micro` preset selects only its implemented module roots; future HTTP or other capabilities require their own implemented descriptors and explicit selection policy.

Composer installation establishes the physical package graph; it does not enable runtime modules. The Kernel independently resolves a target-local `ModulePlan` from the effective preset and module overrides. An explicit `moduleOverrides.exclude` for `platform.logging` disables Logging only for the specified target while preserving its installed package and any Redaction requirement of another selected module. The separate string setting `logging.driver='null'` retains the Logging module but resolves `LoggerInterface` to Foundation `NoopLogger`. The normal CLI host requires an enabled `platform.cli` in its Console plan and fails closed when it is excluded.

The project `composer.json` defines the application's runtime and application-specific dependencies.

Composer records the resolved dependency graph in `composer.lock` and installs dependencies under `vendor/`.

## Command-line interface

After baseline Composer installation, run the canonical CLI from the Skeleton project root:

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

The project-root `coretsia` launcher delegates normal CLI commands to the Composer-installed `vendor/bin/coretsia` binary. Running `php vendor/bin/coretsia <command>` from the same project root invokes the packaged CLI implementation directly, bypassing the Skeleton-specific dependency synchronization routes. No additional CLI package installation, shell alias, or CLI configuration step is required after the baseline Composer installation.

If `vendor/bin/coretsia` is missing or unreadable, the Skeleton wrapper writes exactly `CORETSIA_CLI_BOOTSTRAP_FAILED: autoload-unavailable` followed by one LF to stderr and exits with code `1`, without exposing paths, PHP warnings, or exception traces.

Normal CLI commands bootstrap the Kernel-owned Console operations host using the resolved Console `ModulePlan`. Target-scoped Kernel Ops commands require an explicit `--target=web|api|console|worker`; the requested operation target does not change the CLI host's Console target. Built-in `list`, `help`, and `doctor` commands do not require `--target`. The `doctor` command remains the ultra-early diagnostic path and does not require Kernel host boot or generated artifacts.

## Application dependency synchronization

Dependency synchronization is an explicit consumer operation. It is never triggered implicitly by `composer create-project`, preset selection, normal CLI command invocation, or application/runtime boot.

Canonical workflow:

```text
baseline Composer install
    -> explicit application targets
    -> per-target preset and module policy
    -> plan/review
    -> explicit apply
    -> composer.lock/vendor validation
    -> fresh per-target ModulePlan verification
```

The Skeleton project-root `coretsia` launcher exposes three explicit dependency synchronization operations:

```bash
php coretsia plan --target=web
php coretsia review --target=web --target=worker
php coretsia apply --target=web --target=worker
```

`plan` calculates installation intent without applying changes. `review` verifies the relevant state without intentionally modifying Composer-managed dependencies. `apply` is the only operation authorized to perform Composer effects, subject to its explicit execution policy.

These operations delegate directly to the canonical project-owned adapter, before normal CLI host boot. They preserve the adapter's argument validation, safety policy, JSON result schema, and exit codes. They are not registered `cli.command` services and do not participate in the normal CLI command catalog or UnitOfWork lifecycle.

The canonical adapter also supports direct invocation as `php bin/dependency-sync.php <operation> [options]`.

A fixed installation preset can be supplied only for a selected target:

```bash
php bin/dependency-sync.php review \
  --target=web \
  --target=worker \
  --preset=worker=enterprise
```

Apply-only effect authorization flags are:

```text
--allow-composer-scripts
--allow-composer-plugins
--allow-broad-update
--allow-repair
```

DependencySync records the Coretsia root requirements it owns under `extra.coretsia.dependencySync` using `managedRequire` and `lastAppliedRequire`. An untracked root remains project-owned and is never silently claimed.

Every explicit non-Coretsia root in `require` or `require-dev` is protected for a synchronization. Its root constraint, locked identity, and installed identity must remain unchanged. If the requested Coretsia solve requires changing that package, the synchronization fails visibly instead of widening the update scope.

Composer effects are not transactionally atomic across `composer.json`, `composer.lock`, and `vendor/`. When an effectful process has started and a later Composer or verification failure occurs, the adapter reports `RECOVERY_REQUIRED` with a recovery receipt. Recovery material is stored under:

```text
var/dependency-sync/recovery/<recoveryReceiptId>/
```

Effectful synchronization uses:

```text
var/locks/dependency-sync.lock
```

A stable repeated synchronization starts from a fresh PHP process and is a no-op when manifest, lock, vendor state, and all selected target plans already match.

Canonical multi-target example with explicitly selected `web` and `worker` targets, the default `micro` preset for `web`, and a target-qualified `enterprise` preset for `worker`:

```text
explicit targets: web, worker
web    -> micro      -> core.foundation, core.kernel, platform.cli, platform.errors, platform.logging, platform.redaction
worker -> enterprise -> core.foundation, core.kernel, platform.cli, platform.errors, platform.logging, platform.redaction, platform.worker

project-wide physical module union:
core.foundation, core.kernel, platform.cli, platform.errors, platform.logging, platform.redaction, platform.worker
```

Both target-local closures include `platform.errors` through CLI and `platform.redaction` through CLI and Logging. The project-wide union determines physical Composer installation intent, not global runtime enablement: `platform.worker` remains disabled in `ModulePlan(web)` even though it is installed for `worker`. Excluding `platform.logging` for one target does not remove its Composer package or disable Redaction still required by CLI. Only explicitly selected targets participate in dependency planning, and adding mode-required packages still requires an explicit DependencySync `apply`.

See `docs/ssot/application-dependency-sync.md` in the Coretsia monorepo for the normative ownership, execution, verification, and recovery contract.

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

Add additional app-target directories under `apps/` as the application requires. Directory presence does not select a target for DependencySync; installation target membership is explicit caller input.

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

This file controls early application bootstrap and accepts only `appEnv`, `preset`, `presets`, `moduleOverrides`, `debug`, and `artifactsCacheDir`.

The initial file returns an empty array:

```php
return [];
```

An empty initial config uses the canonical bootstrap defaults. Applications may override these settings using the following structure:

```php
return [
    'appEnv' => 'local', // Examples: local, staging, prod, production.
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

A target-specific `presets` entry takes precedence over the global `preset` fallback. Explicit bootstrap input may take precedence over both. Only the selected application target is evaluated; configuring a target does not install or activate additional packages.

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

The active configuration example includes the package-owned roots available through the baseline framework distribution. The commented `worker` block documents the optional Worker package and must only be activated when that package participates in the selected runtime module graph.

`kernel.boot.*` documents package fallback defaults. To change effective early-bootstrap settings, use the corresponding top-level keys in `config/app.php`; application configuration is not loaded early enough to replace Phase A bootstrap input.

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

The initial `.env.example` contains commented, non-sensitive examples of all package configuration keys supported by automatic environment overlays. No environment variable is required by every Coretsia application.

Create a local `.env` file when overrides are needed. Copy `.env.example` and uncomment only the required entries:

```dotenv
FOUNDATION_IDS_DEFAULT=uuid
LOGGING_LEVEL=debug
CLI_OUTPUT_TABLE_MAX_WIDTH=140
```

Environment variable names use uppercase configuration paths with dots and hyphens replaced by underscores. For example, `FOUNDATION_IDS_DEFAULT` overrides `foundation.ids.default`. The Kernel applies environment overlays to the merged package configuration after bootstrap, with higher precedence than PHP configuration files.

Automatic overlays support only scalar ruleset types: `string`, `non-empty-string`, `non-empty-string-no-ws`, `bool`, and `int`. Boolean values accept exactly `true`, `false`, `1`, or `0`. Integers use canonical decimal syntax. Values must satisfy the owning package's validation rules. Unknown environment variable names do not create configuration keys, and unsupported configuration types are not automatically projected.

Lists, maps, relative-safe paths, and bounded `reset-group-id` fields must be configured through PHP configuration files unless an explicit supported environment mapping is provided. For example, `FOUNDATION_RESET_GROUP_DEFAULT` is not an automatic overlay even though `foundation.reset.group.default` is a valid PHP configuration key.

Environment overlays do not control the current Bootstrap Phase A selection. Configure `appEnv`, `preset`, `presets`, `moduleOverrides`, `debug`, and `artifactsCacheDir` through `config/app.php` or explicit `BootstrapInput`. A `KERNEL_BOOT_DEFAULT_ENV` overlay changes the merged configuration value but does not reselect the current environment or reload dotenv files.

By default, Kernel reads these dotenv files in order, skipping missing files:

```text
.env
.env.local
.env.<appEnv>
.env.<appEnv>.local
```

Later files override earlier dotenv values. Local and other non-production environments use the default `strict_dotenv` policy, which does not fall back to system environment variables. Production-like `prod` and `production` environments default to `allow_system`, where system environment variables take precedence over dotenv values.

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
