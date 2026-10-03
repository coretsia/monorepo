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

`coretsia/framework` installs the baseline Coretsia runtime dependencies and remains a project-owned root requirement.

Additional mode-required Coretsia packages are materialized only through explicit application dependency synchronization. DependencySync may manage only the Coretsia root requirements recorded in its ownership marker; application-owned roots remain unchanged.

The project `composer.json` defines the application's runtime and application-specific dependencies.

Composer records the resolved dependency graph in `composer.lock` and installs dependencies under `vendor/`.

## Application dependency synchronization

Dependency synchronization is an explicit consumer operation. It is never triggered by application/runtime boot.

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

The shipped integration entrypoint is:

```text
bin/dependency-sync.php
```

Examples:

```bash
php bin/dependency-sync.php plan --target=web
php bin/dependency-sync.php review --target=web --target=worker
php bin/dependency-sync.php apply --target=web --target=worker
```

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

Canonical current multi-target example:

```text
explicit targets: web, worker
web    -> micro      -> core.foundation, core.kernel
worker -> enterprise -> core.foundation, core.kernel, platform.worker

physical project union:
core.foundation, core.kernel, platform.worker
```

`platform.worker` remains disabled in `ModulePlan(web)` even though it is physically installed for `worker`.

See `docs/ssot/application-dependency-sync.md` in the Coretsia monorepo for the normative ownership, execution, verification, and recovery contract.

## Project structure

The initial project structure is:

```text
./
├── bin/
│   └── dependency-sync.php
├── composer.json
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

Each app target lives under:

```text
apps/<appTarget>/
```

The initial project includes a `web` app target:

```text
apps/web/
├── config/
└── public/
```

`apps/web/config/` contains configuration specific to the `web` app target.

`apps/web/public/` is the public directory for the `web` app target.

Add additional app-target directories under `apps/` as the application requires. Directory presence does not select a target for DependencySync; installation target membership is explicit caller input.

## Configuration

Application configuration contains project-specific overrides.

Default values are provided by the installed Coretsia packages.

Only add overrides that the application actually needs. Coretsia combines package defaults with the application's configuration during bootstrap.

### Bootstrap configuration

The application provides:

```text
config/app.php
```

This file contains application settings required during early bootstrap.

The initial file returns an empty array:

```php
return [];
```

An empty initial config means the application uses the defaults provided by installed Coretsia packages until project-specific bootstrap settings are added.

### Application-wide configuration

Application-wide configuration belongs under:

```text
config/
```

Environment-specific application-wide configuration can live under:

```text
config/environments/<appEnv>/
```

Only create configuration files for overrides the application actually needs.

### App-target configuration

App-target-specific configuration belongs under:

```text
apps/<appTarget>/config/
```

Environment-specific app-target configuration can live under:

```text
apps/<appTarget>/config/environments/<appEnv>/
```

The initial `apps/web/config/` directory is empty until the application requires an app-target-specific override.

## Runtime directories

`var/` contains mutable application runtime state such as caches, logs, locks, sessions, temporary data, and generated runtime artifacts.

Mutable contents under `var/` are ignored by Git, while tracked `.gitkeep` files preserve the initial directory structure.

## Environment configuration

The application includes:

```text
.env.example
```

The initial file contains no `KEY=VALUE` entries because no environment key is currently required by every Coretsia application.

Create a local `.env` file when environment-specific overrides are required.

The local `.env` file is ignored by Git.

Coretsia loads environment configuration during application bootstrap.

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

## Observability

Logging, metrics, tracing, and other observability capabilities are provided by installed Coretsia packages and configured through the application's configuration files.

## Security

Keep secrets, credentials, and other sensitive application data out of source control.

Use `.env.example` only for documentation-safe examples, never for production credentials or private environment values.

Keep committed configuration under `config/` and `apps/*/config/` free of secrets and private environment values.

For documentation and guides, see [coretsia.dev](https://coretsia.dev/).
