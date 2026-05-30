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

# ADR-0023: Kernel Bootstrap Phase A

## Status

Accepted.

## Context

Kernel needs a deterministic minimal bootstrap phase before full configuration compilation, module planning, runtime lifecycle orchestration, HTTP/CLI adapter execution, and unit-of-work handling.

This phase is called Bootstrap Phase A.

Bootstrap Phase A exists to resolve only the minimal boot input required by early runtime owners:

```text
skeletonRoot
appTarget
appEnv
preset
debug
envSourcePolicy
appRoot
immutable EnvRepositoryInterface snapshot
```

Phase A must not become a full configuration merge phase.

Full configuration discovery, merge, directives, environment overlays, validation, explain output, and application config file discovery are owned by ConfigKernel Phase B.

Phase A needs to support:

- explicit application target selection;
- deterministic app root derivation;
- optional bootstrap-only overrides;
- package fallback defaults;
- dotenv loading based on already selected `appEnv`;
- deterministic dotenv/system env source precedence;
- immutable env repository snapshots;
- safe failure diagnostics.

The kernel package already owns the `kernel` config root.

The Phase A package defaults live under the existing `kernel` subtree:

```text
kernel.boot.default_env
kernel.boot.default_preset
kernel.boot.default_debug
kernel.env.source_policy.default_local
kernel.env.source_policy.default_production
kernel.env.dotenv.files
```

These are config key namespaces under `kernel`, not separate config roots.

The kernel config defaults file returns the `kernel` subtree only and must not wrap values in a repeated root key:

```text
config/kernel.php => ['boot' => [...], 'env' => [...], 'uow' => [...]]
```

It must not return:

```text
['kernel' => [...]]
```

## Decision

Kernel Bootstrap Phase A is implemented as a small set of focused boot value objects, loaders, resolvers, and builders.

The public Bootstrap Phase A API surface is intentionally limited to:

```text
Coretsia\Kernel\Boot\AppTarget
Coretsia\Kernel\Boot\BootstrapConfig
Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy
Coretsia\Kernel\Boot\BootstrapInput
Coretsia\Kernel\Boot\Exception\BootstrapException
```

Internal implementation helpers remain `@internal` and are not part of the public Kernel API.

The internal Phase A implementation helpers are:

```text
Coretsia\Kernel\Boot\BootstrapConfigResolver
Coretsia\Kernel\Boot\BootstrapOverridesLoader
Coretsia\Kernel\Boot\DotenvLoader
Coretsia\Kernel\Boot\EnvRepositoryBuilder
Coretsia\Kernel\Boot\ArrayEnvRepository
```

## Decision 1: Phase A is a minimal boot-input phase

Bootstrap Phase A resolves only:

```text
skeletonRoot
appTarget
appEnv
preset
debug
envSourcePolicy
appRoot
immutable EnvRepositoryInterface snapshot
```

Phase A must not read full skeleton configuration files.

Phase A must not read:

```text
skeleton/config/all.php
skeleton/config/<root>.php
skeleton/config/environments/**
skeleton/apps/<appTarget>/config/**
```

Phase A may read only one bootstrap-only skeleton config input:

```text
skeleton/config/app.php
```

This file is a Bootstrap Phase A input file only.

It is not a reserved config root.

It must not participate in ConfigKernel Phase B merge.

Phase A must not scan:

```text
skeleton/apps/*
```

The application target must be explicit input.

## Decision 2: AppTarget is explicit and canonical

`Coretsia\Kernel\Boot\AppTarget` is the canonical application target enum.

The allowed target tokens are:

```text
web
api
console
worker
```

Invalid targets fail with:

```text
BootstrapException::REASON_INVALID_APP_TARGET
```

Invalid target diagnostics must not include the rejected raw input.

`appRoot` is derived deterministically as:

```text
skeletonRoot/apps/<appTarget>
```

Phase A must not require this directory to exist.

Phase A must not infer the application target from filesystem state.

Phase A must not choose an application by scanning sibling app directories.

## Decision 3: BootstrapInput is entrypoint-owned input only

`Coretsia\Kernel\Boot\BootstrapInput` is the immutable entrypoint-owned input object.

It carries only:

```text
skeletonRoot
appTarget
appEnv?
preset?
debug?
envSourcePolicy?
```

`BootstrapInput` must not:

- read filesystem state;
- infer app target;
- inspect `skeleton/apps/*`;
- read process env;
- parse dotenv files;
- contain raw env values.

Optional values in `BootstrapInput` have the highest Phase A resolution precedence.

## Decision 4: BootstrapConfig is a resolved immutable VO only

`Coretsia\Kernel\Boot\BootstrapConfig` is a resolved immutable value object.

It represents the selected Phase A config after resolution has already happened.

It must contain:

```text
appEnv
preset
debug
envSourcePolicy
appTarget
skeletonRoot
appRoot
```

`BootstrapConfig` must not:

- resolve optional values from `BootstrapInput`;
- read package defaults;
- read `skeleton/config/app.php`;
- read dotenv files;
- read system env;
- scan `skeleton/apps/*`;
- require `appRoot` to exist;
- expose `fromInput()`;
- expose any method that performs Phase A resolution.

The object derives only `appRoot` from already resolved `skeletonRoot` and `appTarget`.

## Decision 5: BootstrapConfigResolver owns Phase A config resolution

`Coretsia\Kernel\Boot\BootstrapConfigResolver` is the canonical internal owner of Phase A config resolution.

Resolution order is:

1. explicit `BootstrapInput` values;
2. bootstrap-only overrides from `skeleton/config/app.php`;
3. package defaults from `kernel.boot.*` and `kernel.env.*`.

The resolver resolves:

```text
appEnv
preset
debug
envSourcePolicy
```

The resolver returns a resolved `BootstrapConfig`.

The resolver must not:

- build `EnvRepositoryInterface`;
- parse dotenv files;
- read system env;
- scan `skeleton/apps/*`;
- require `appRoot` to exist;
- expose raw override values in diagnostics.

`envSourcePolicy` is resolved after final `appEnv` is selected.

Production-like env names are exactly:

```text
prod
production
```

Production-like env names default to:

```text
allow_system
```

Every other env name defaults to:

```text
strict_dotenv
```

`staging` is intentionally default-strict and therefore defaults to:

```text
strict_dotenv
```

Non-production env names that need system env precedence must pass explicit:

```text
BootstrapEnvSourcePolicy::AllowSystem
```

through `BootstrapInput`.

## Decision 6: BootstrapOverridesLoader reads only skeleton/config/app.php

`Coretsia\Kernel\Boot\BootstrapOverridesLoader` reads only:

```text
skeleton/config/app.php
```

Missing `skeleton/config/app.php` means no overrides.

Existing but invalid `skeleton/config/app.php` fails deterministically.

The file must return an array.

Allowed override keys are exactly:

```text
appEnv
preset
debug
```

Unknown keys fail with:

```text
BootstrapException::REASON_OVERRIDES_INVALID
```

Allowed values are:

```text
appEnv: non-empty safe string
preset: non-empty safe string
debug: bool
```

The loader must not read:

```text
skeleton/config/modules.php
skeleton/config/all.php
skeleton/config/<root>.php
skeleton/config/environments/**
skeleton/apps/<appTarget>/config/**
```

Module enable/disable composition is not handled by Phase A.

Module composition is owned by the ModulePlan epic and is resolved from preset files plus composer metadata.

Override values must not appear in exception messages.

## Decision 7: BootstrapEnvSourcePolicy is source precedence only

`Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy` is a Kernel-owned Bootstrap Phase A env source precedence enum.

Allowed values are:

```text
strict_dotenv
allow_system
```

This enum must not be confused with:

```text
Coretsia\Contracts\Env\EnvPolicy
```

`Coretsia\Contracts\Env\EnvPolicy` remains a missing-value policy only:

```text
required
optional
defaulted
```

It does not control dotenv/system env source precedence.

## Decision 8: DotenvLoader consumes resolved BootstrapConfig

`Coretsia\Kernel\Boot\DotenvLoader` parses configured dotenv files safely.

It consumes a resolved `BootstrapConfig`.

It must not:

- resolve `appEnv`;
- read `BootstrapInput`;
- read `skeleton/config/app.php`;
- apply package boot defaults;
- apply system env precedence;
- use `Coretsia\Contracts\Env\EnvPolicy`.

Dotenv template expansion uses the already selected:

```text
BootstrapConfig::appEnv()
```

The default dotenv file templates are read from:

```text
kernel.env.dotenv.files
```

The canonical default templates are:

```text
.env
.env.local
.env.<env>
.env.<env>.local
```

If `appEnv` is absent from explicit `BootstrapInput` and `skeleton/config/app.php`, the package default:

```text
kernel.boot.default_env
```

is resolved by `BootstrapConfigResolver` before dotenv template expansion.

Dotenv entries must be filesystem-safe file names.

Configured dotenv file entries must reject:

```text
/
\
..
NUL
drive letters
stream wrappers
```

Dotenv loading returns:

```text
values: array<string,string>
sources: array<string,ConfigValueSource>
```

Raw dotenv values must not appear in diagnostics.

## Decision 9: EnvRepositoryBuilder owns env snapshot construction only

`Coretsia\Kernel\Boot\EnvRepositoryBuilder` builds immutable
`EnvRepositoryInterface` snapshots.

It consumes a resolved `BootstrapConfig`.

It must not:

- resolve `BootstrapConfig`;
- read `BootstrapInput` optional values;
- read `skeleton/config/app.php`;
- apply package boot defaults;
- use `Coretsia\Contracts\Env\EnvPolicy`;
- create a mutable repository;
- expose raw env values in diagnostics.

`EnvRepositoryBuilder` owns env repository construction only.

It does not own Phase A config resolution.

It does not own dotenv parsing policy beyond invoking `DotenvLoader`.

It does not own missing-value policy.

The canonical env snapshot order is:

1. resolved `BootstrapConfig` is consumed;
2. dotenv files are loaded using resolved `BootstrapConfig::appEnv()`;
3. system env is snapshotted once;
4. dotenv/system precedence is applied according to resolved `BootstrapConfig::envSourcePolicy()`;
5. immutable `EnvRepositoryInterface` snapshot is returned.

After repository construction, the returned repository must not read from:

```text
$_ENV
$_SERVER
getenv()
```

## Decision 10: ArrayEnvRepository is the internal immutable repository

`Coretsia\Kernel\Boot\ArrayEnvRepository` is the internal immutable implementation of:

```text
Coretsia\Contracts\Env\EnvRepositoryInterface
```

It stores:

```text
array<string,string>
```

as a normalized snapshot.

It may store safe `ConfigValueSource` metadata per env key.

It must preserve present empty string as present.

`has()` must return true for present empty string.

`get()` returns:

```text
EnvValue::present($value)
```

when a value is present.

`get()` returns:

```text
EnvValue::missing()
```

when the key is absent.

`all()` returns a copy of present raw env values for runtime owners only.

`sourceOf()` returns safe source metadata only.

`ArrayEnvRepository` is internal and must not be added to the public Kernel API.

## Decision 11: strict_dotenv precedence

When `envSourcePolicy` is:

```text
strict_dotenv
```

then:

- dotenv values win;
- system env values are ignored;
- system env fallback is forbidden;
- missing dotenv keys remain missing in the repository;
- present empty dotenv values remain present.

`strict_dotenv` still permits system env to be snapshotted internally before precedence application, but the snapshot must not affect repository values.

## Decision 12: allow_system precedence

When `envSourcePolicy` is:

```text
allow_system
```

then:

- system env values win;
- dotenv values are used only when the same key is absent from system env;
- system-only keys are present;
- present empty system values remain present;
- missing keys remain missing.

System env names that are unsafe for source metadata are ignored before `ConfigValueSource` metadata is created.

System source metadata must be redacted and must not contain raw env values.

## Decision 13: BootstrapException owns safe Phase A failure messages

`Coretsia\Kernel\Boot\Exception\BootstrapException` is the canonical Bootstrap Phase A exception.

Its error code is:

```text
CORETSIA_BOOTSTRAP_FAILED
```

Stable reason tokens are:

```text
bootstrap-invalid-app-target
bootstrap-invalid-skeleton-root
bootstrap-dotenv-file-invalid
bootstrap-dotenv-load-failed
bootstrap-overrides-invalid
bootstrap-env-source-policy-invalid
```

The exception message contains only:

```text
ERROR_CODE: reason-token
```

The message must not contain:

- dotenv values;
- raw env values;
- raw override arrays;
- raw PHP warnings;
- OS error messages;
- absolute paths;
- tokens;
- cookies;
- Authorization values;
- secrets;
- stack traces;
- environment-specific data.

## Decision 14: Provider wiring registers Phase A services only

`KernelServiceFactory` may provide deterministic constructors/factories for Bootstrap Phase A services.

`KernelServiceProvider` may register Phase A boot services as DI factories:

```text
BootstrapOverridesLoader
BootstrapConfigResolver
DotenvLoader
EnvRepositoryBuilder
```

Provider registration must not execute Bootstrap Phase A.

Provider registration must not:

- resolve `BootstrapInput`;
- read `skeleton/config/app.php`;
- parse dotenv files;
- snapshot system env;
- build `EnvRepositoryInterface`;
- start UnitOfWork;
- trigger reset orchestration.

The factory must not keep mutable runtime state.

## Decision 15: No public Phase A orchestration facade or aggregate result are introduced

Kernel does not introduce public:

```text
Bootstrapper
BootstrapResult
```

in this ADR.

This is intentional.

`BootstrapConfigResolver` exists, but it is not a Bootstrapper.

`BootstrapConfigResolver` is an internal, narrow resolver. It owns only Phase A config resolution:

```text
BootstrapInput
bootstrap-only overrides from skeleton/config/app.php
kernel.boot.* defaults
kernel.env.* defaults
```

and returns only:

```text
BootstrapConfig
```

It must not:

- build `EnvRepositoryInterface`;
- invoke `EnvRepositoryBuilder`;
- parse dotenv files;
- read system env;
- execute lifecycle behavior;
- hide full Phase A orchestration behind a public API.

A public `Bootstrapper` would be a different abstraction. It would likely orchestrate at least:

```text
BootstrapInput
BootstrapConfigResolver
DotenvLoader
EnvRepositoryBuilder
BootstrapConfig
EnvRepositoryInterface
```

and would freeze a public one-call Phase A orchestration contract before platform entrypoints and CLI commands own their concrete invocation contracts.

A public `BootstrapResult` would also be a different abstraction. It would aggregate at least:

```text
BootstrapConfig
EnvRepositoryInterface
```

This ADR rejects introducing that aggregate result because the canonical result objects already exist and have clear ownership:

```text
BootstrapConfig
EnvRepositoryInterface
```

The current design keeps public API stable and minimal:

- entrypoints construct `BootstrapInput`;
- internal resolver returns `BootstrapConfig`;
- internal builder returns `EnvRepositoryInterface`;
- entrypoint/platform owners compose these services explicitly.

A future owner epic may introduce a public bootstrap orchestration facade only if an actual platform entrypoint contract requires it.

For the current CLI use case, command owners can orchestrate explicit DI services without requiring a public `Bootstrapper` type.

Example future CLI commands:

```text
coretsia config:compile
coretsia cache:verify
```

may invoke the relevant boot/config services through DI while keeping Phase A resolution and env snapshot boundaries unchanged.

## Consequences

### Positive

Bootstrap Phase A remains minimal and deterministic.

Application target selection is explicit.

`appRoot` derivation is stable and does not depend on filesystem scanning.

Bare skeletons can boot from package defaults.

`BootstrapConfig` remains a clean resolved VO.

`BootstrapConfigResolver` owns Phase A config resolution.

`EnvRepositoryBuilder` owns env snapshot construction only.

Dotenv template expansion cannot depend on reading `.env.<env>` before the final env is known.

`strict_dotenv` and `allow_system` have clear, testable precedence rules.

Present empty string remains distinct from missing env values.

Safe `ConfigValueSource` metadata can explain source origin without leaking raw env values.

Phase A boot code is isolated from runtime lifecycle/reset infrastructure.

The public API remains small.

### Negative / trade-offs

There is no one-call public bootstrap facade.

Entrypoints or platform packages must compose the resolver and builder through DI until a future owner epic introduces a justified orchestration facade.

`staging` defaults to `strict_dotenv`, so deployments that want system env precedence for staging must pass explicit `BootstrapEnvSourcePolicy::AllowSystem`.

`BootstrapOverridesLoader` supports only `appEnv`, `preset`, and `debug`. Other bootstrap inputs require explicit entrypoint input or future owner epics.

Phase A does not validate the existence of `appRoot`, so later phases must own application root existence checks when needed.

## Rejected alternatives

### Alternative 1: Let BootstrapConfig resolve from BootstrapInput

Rejected.

`BootstrapConfig` must be a resolved immutable VO only.

Putting resolution logic into `BootstrapConfig` would mix data representation with IO-aware/default-aware resolution policy and would make it harder to keep Phase A boundaries testable.

### Alternative 2: Read full skeleton config during Phase A

Rejected.

Full config discovery and merge are owned by ConfigKernel Phase B.

Reading full skeleton config during Phase A would create ordering drift, duplicate merge behavior, and risk reading application config before the minimal boot boundary is stable.

### Alternative 3: Infer app target by scanning skeleton/apps/*

Rejected.

App target must be explicit.

Scanning app directories would make boot behavior depend on filesystem shape, sibling directories, and local development artifacts.

The accepted design derives:

```text
appRoot = skeletonRoot/apps/<appTarget>
```

from explicit input only.

### Alternative 4: Read skeleton/config/modules.php during Phase A

Rejected.

Module enable/disable composition is owned by ModulePlan.

Phase A reads only bootstrap-only `skeleton/config/app.php`.

`modules.php` must not be read here.

### Alternative 5: Use Coretsia\Contracts\Env\EnvPolicy for source precedence

Rejected.

`Coretsia\Contracts\Env\EnvPolicy` is a missing-value policy only.

It describes:

```text
required
optional
defaulted
```

It must not control dotenv/system source precedence.

The accepted design uses Kernel-owned `BootstrapEnvSourcePolicy`.

### Alternative 6: Introduce public Bootstrapper now

Rejected.

`BootstrapConfigResolver` already exists as an internal resolver, but it is not a public bootstrap orchestration facade.

A public `Bootstrapper` would compose multiple Phase A steps and would likely return both resolved config and env repository state.

No current public platform entrypoint contract requires that one-call facade.

Introducing it now would freeze orchestration prematurely and could grow into a hidden lifecycle/config merge owner.

The accepted design keeps orchestration explicit and keeps `BootstrapConfigResolver` narrow and internal.

### Alternative 7: Introduce public BootstrapResult now

Rejected.

The canonical result objects already exist:

```text
BootstrapConfig
EnvRepositoryInterface
```

`BootstrapConfigResolver` returns only `BootstrapConfig`.

`EnvRepositoryBuilder` returns `EnvRepositoryInterface`.

A public `BootstrapResult` would aggregate those objects into a second public result shape, which would create another object to version, preserve, and keep in sync.

A future owner epic may introduce a result wrapper only if there is concrete public API pressure from platform entrypoints.

### Alternative 8: Treat staging as production-like by default

Rejected.

Production-like env names are exactly:

```text
prod
production
```

`staging` remains default-strict.

Deployments that want system env precedence for staging must pass explicit `BootstrapEnvSourcePolicy::AllowSystem`.

## Security and redaction

Bootstrap Phase A must prefer safe deterministic failure over leaking raw input.

Bootstrap exceptions must not expose:

- raw dotenv values;
- raw env values;
- raw override arrays;
- raw PHP warnings;
- OS error messages;
- absolute local paths;
- tokens;
- session ids;
- cookies;
- Authorization headers;
- credentials;
- passwords;
- private keys;
- raw SQL;
- stack traces;
- environment-specific bytes.

`EnvRepositoryInterface::all()` exposes raw env values to runtime owners only.

Diagnostics, traces, logs, validation errors, generated artifacts, and explain output must not print the raw env map directly.

`ConfigValueSource` metadata attached by Phase A must be safe and redacted.

Source metadata may include safe logical identifiers such as:

```text
dotenv/.env.test.local
system_env
env key name
```

Source metadata must not include raw values or absolute skeleton roots.

## Runtime lifecycle impact

Bootstrap Phase A is lifecycle-free.

Boot source code under:

```text
framework/packages/core/kernel/src/Boot/**
```

must not depend on:

```text
ResetOrchestrator
TagRegistry
ResetInterface
KernelRuntime
kernel.reset
```

Phase A does not start a unit of work.

Phase A does not trigger reset orchestration.

Phase A does not register runtime hooks.

Phase A only prepares minimal boot inputs and env snapshots for later owners.

## Boundaries

This ADR does not introduce:

- public `Bootstrapper`;
- public `BootstrapResult`;
- new config roots;
- full config merge;
- config explain output;
- env overlays;
- module planning;
- module enable/disable composition;
- preset file parsing;
- composer metadata scanning;
- app root existence validation;
- HTTP adapter behavior;
- CLI command behavior;
- runtime lifecycle execution;
- reset orchestration;
- UnitOfWork start/end behavior;
- generated artifacts.

## Verification

Expected verification includes:

```text
framework/packages/core/kernel/tests/Contract/KernelBootstrapDoesNotUseRuntimeLifecycleTest.php
framework/packages/core/kernel/tests/Contract/KernelDoesNotWriteToStdoutTest.php
framework/packages/core/kernel/tests/Integration/BootstrapSelectsExplicitAppTargetTest.php
framework/packages/core/kernel/tests/Integration/BootstrapDoesNotScanSkeletonAppsTest.php
framework/packages/core/kernel/tests/Integration/BootstrapOverridesLoaderReadsOnlyAppPhpTest.php
framework/packages/core/kernel/tests/Integration/BootstrapWorksWithoutAnySkeletonConfigFilesTest.php
framework/packages/core/kernel/tests/Integration/BootstrapDotenvRespectedUnderStrictPolicyTest.php
framework/packages/core/kernel/tests/Integration/BootstrapSystemEnvOverridesDotenvUnderAllowSystemPolicyTest.php
```

Verification must prove:

- `web`, `api`, `console`, and `worker` are accepted app targets;
- invalid app target fails with `BootstrapException::REASON_INVALID_APP_TARGET`;
- invalid app target diagnostics do not leak raw input;
- `appRoot` is derived as `skeletonRoot/apps/<target>`;
- Phase A does not scan `skeleton/apps/*`;
- sibling app directories do not affect selected app target;
- `BootstrapOverridesLoader` reads `skeleton/config/app.php` when present;
- `BootstrapOverridesLoader` does not read `skeleton/config/modules.php`;
- unknown override keys fail deterministically;
- raw override values do not leak in exception messages;
- bare skeleton boot works without any skeleton config files;
- package defaults are used when explicit input and overrides are absent;
- strict dotenv policy ignores system env values;
- strict dotenv policy forbids system env fallback;
- allow-system policy lets system env values override dotenv values;
- allow-system policy uses dotenv values only when system env keys are absent;
- present empty string remains present;
- missing env keys remain `EnvValue::missing()`;
- source metadata is redacted;
- source metadata does not contain raw dotenv values;
- source metadata does not contain raw system env values;
- source metadata does not contain absolute skeleton roots;
- Boot source does not depend on runtime lifecycle/reset services;
- Kernel boot/runtime/provider source does not write to stdout or stderr.

## Related SSoT

- `docs/ssot/config-and-env.md`
- `docs/ssot/tags.md`
- `docs/ssot/uow-and-reset-contracts.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`

## Related epic

- `1.290.0 Kernel Bootstrap Phase A`
