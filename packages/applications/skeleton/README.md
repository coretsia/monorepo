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

`coretsia/framework` installs the baseline Coretsia runtime dependencies.

Install additional Coretsia packages and capabilities through Composer; they become requirements of this project's `composer.json`.

The project `composer.json` defines the application's runtime and application-specific dependencies.

Composer records the resolved dependency graph in `composer.lock` and installs dependencies under `vendor/`.

## Project structure

The initial project structure is:

```text
./
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

Add additional app targets under `apps/` as the application requires.

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
