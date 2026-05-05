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

# ADR-0014: DI container, tags, deterministic ordering, and reset orchestration

## Status

Accepted.

## Context

Epic `1.200.0` introduces the `core/foundation` runtime package under:

```text
framework/packages/core/foundation/
```

The package provides the baseline runtime mechanisms that higher-level packages need before Kernel, HTTP, CLI, worker, and platform packages can compose deterministic runtime behavior:

- PSR-11 container runtime;
- deterministic container build behavior from caller-supplied service providers;
- canonical tag registry for service discovery lists;
- canonical deterministic discovery ordering;
- stable diagnostics serialization;
- reset orchestration for long-running runtimes.

The package identity is:

```text
package_id: core/foundation
composer: coretsia/core-foundation
module_id: core.foundation
kind: runtime
```

Runtime packages need one canonical way to register and consume tagged services without each consumer inventing its own sort rule, dedupe rule, middleware ordering rule, or reset discovery logic.

Long-running runtimes also need a single reset execution mechanism so mutable service state does not leak across units of work.

The reset contracts already exist in `core/contracts`:

```text
Coretsia\Contracts\Runtime\ResetInterface
Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface
Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface
```

The tag registry SSoT already reserves relevant tags in:

```text
docs/ssot/tags.md
```

Relevant existing reserved tags include:

```text
kernel.reset
kernel.stateful
kernel.hook.before_uow
kernel.hook.after_uow
http.middleware.*
cli.command
error.mapper
health.check
```

The config roots registry already reserves the Foundation root in:

```text
docs/ssot/config-roots.md
```

Relevant config root:

```text
foundation
```

The HTTP middleware catalog already owns HTTP slot taxonomy and slot contents in:

```text
docs/ssot/http-middleware-catalog.md
```

Epic `1.200.0` does not implement Kernel lifecycle behavior, HTTP middleware stacks, CLI command execution, platform adapters, integrations, or tooling-only behavior.

## Decision

Coretsia will introduce `core/foundation` as the runtime package that owns:

- Foundation PSR-11 container runtime;
- Foundation service provider contract and builder;
- canonical `TagRegistry`;
- canonical `TaggedService` value object;
- canonical `DeterministicOrder` ordering primitive;
- Foundation reset orchestrator;
- Foundation tag constants for already-owned Foundation tags;
- Foundation configuration defaults and rules;
- stable JSON encoder for deterministic diagnostics;
- container diagnostics snapshot.

The implementation paths are:

```text
framework/packages/core/foundation/src/Container/Container.php
framework/packages/core/foundation/src/Container/ContainerBuilder.php
framework/packages/core/foundation/src/Container/ServiceProviderInterface.php
framework/packages/core/foundation/src/Container/Exception/ContainerException.php
framework/packages/core/foundation/src/Container/Exception/NotFoundException.php
framework/packages/core/foundation/src/Container/ContainerDiagnostics.php
framework/packages/core/foundation/src/Discovery/DeterministicOrder.php
framework/packages/core/foundation/src/Module/FoundationModule.php
framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php
framework/packages/core/foundation/src/Provider/FoundationServiceFactory.php
framework/packages/core/foundation/src/Provider/Tags.php
framework/packages/core/foundation/src/Runtime/Reset/ResetOrchestrator.php
framework/packages/core/foundation/src/Serialization/StableJsonEncoder.php
framework/packages/core/foundation/src/Tag/TagRegistry.php
framework/packages/core/foundation/src/Tag/TaggedService.php
framework/packages/core/foundation/config/foundation.php
framework/packages/core/foundation/config/rules.php
```

The package depends on:

```text
core/contracts
psr/container
```

The package must not depend on:

```text
platform/*
integrations/*
devtools/*
```

This includes tooling-only packages such as:

```text
devtools/internal-toolkit
devtools/cli-spikes
```

## Container decision

`core/foundation` provides a PSR-11-compatible container runtime.

The container implements:

```text
Psr\Container\ContainerInterface
```

Foundation exceptions implement the PSR-11 exception interfaces:

```text
Psr\Container\ContainerExceptionInterface
Psr\Container\NotFoundExceptionInterface
```

The canonical Foundation exception classes are:

```text
Coretsia\Foundation\Container\Exception\ContainerException
Coretsia\Foundation\Container\Exception\NotFoundException
```

Their stable error codes are:

```text
CORETSIA_CONTAINER_ERROR
CORETSIA_CONTAINER_NOT_FOUND
```

Native PHP exception codes remain integers. Coretsia string error codes are exposed through explicit methods or constants.

Container exception messages must remain stable machine-readable strings and must not contain secrets, raw config payloads, constructor arguments, environment values, tokens, credentials, absolute local paths, service instances, or reflection dumps.

## Container builder decision

`ContainerBuilder` owns deterministic registration behavior.

Provider order is caller-supplied and significant.

`ContainerBuilder` must preserve the exact caller-supplied provider order.

`ContainerBuilder` must not globally sort providers by FQCN.

The upstream module plan or Kernel boot owner is responsible for producing a deterministic provider list.

This keeps DI override semantics aligned with deterministic module/provider order and avoids imposing an arbitrary global provider-class sort inside Foundation.

## Container definition collision decision

Container binding collision behavior is single-choice:

```text
later provider binding overrides earlier provider binding
```

For the same service id or interface binding, the later container definition replaces the earlier definition deterministically.

This collision policy applies only to container definitions and instances.

It does not apply to tag registrations.

Tag dedupe remains independent and is owned by `TagRegistry`.

## Service provider contract decision

`ServiceProviderInterface` is owned by `core/foundation`, not by `core/contracts`.

The service provider contract is coupled to Foundation DI runtime behavior and deterministic registration semantics.

Its canonical shape is:

```text
register(ContainerBuilder $builder): void
```

Providers may register container definitions, instances, factories, and tagged service entries into the builder.

Provider implementations must be deterministic for the same provider state.

Provider implementations must not depend on filesystem traversal order, locale collation, environment dumps, tooling-only packages, generated architecture artifacts, or unsafe reflection side effects outside explicit builder behavior.

## Autowire decision

Foundation container autowiring is intentionally conservative.

Concrete-class autowire is allowed only when the merged global config contains:

```text
foundation.container.autowire_concrete
foundation.container.allow_reflection_for_concrete
```

The strict config behavior is:

```text
missing config['foundation'] -> ContainerException
missing config['foundation']['container'] -> ContainerException
invalid foundation.container shape -> ContainerException
```

Interfaces and abstract classes must not be autowired.

Runtime reset execution must never rely on reflection or autowire.

The container may use reflection only for allowed concrete-class resolution during normal service resolution.

Autowiring must not silently guess defaults when the Foundation container config is missing.

## Config decision

`core/foundation` owns the existing reserved config root:

```text
foundation
```

The defaults file is:

```text
framework/packages/core/foundation/config/foundation.php
```

The rules file is:

```text
framework/packages/core/foundation/config/rules.php
```

The defaults file must return the `foundation` subtree only.

Valid shape:

```php
return [
    'container' => [
        'autowire_concrete' => true,
        'allow_reflection_for_concrete' => true,
    ],
    'reset' => [
        'tag' => 'kernel.reset',
    ],
];
```

Invalid shape:

```php
return [
    'foundation' => [
        'container' => [],
    ],
];
```

Runtime code reads from the merged global config under:

```text
foundation.*
```

Canonical keys introduced by the Foundation implementation are:

```text
foundation.container.autowire_concrete
foundation.container.allow_reflection_for_concrete
foundation.reset.tag
```

`foundation.reset.tag` is the effective reset discovery tag.

The reserved default value is:

```text
kernel.reset
```

`kernel.reset` is the default tag value, not a Kernel-owned runtime feature.

Consumers outside Foundation must not read or hardcode `foundation.reset.tag` or the default tag string as a runtime discovery shortcut.

## Config rules decision

Foundation config rules are declarative data only.

The Foundation rules file must not return:

- callables;
- closures;
- objects;
- service instances;
- container-aware validators;
- executable validators;
- resources;
- filesystem handles;
- runtime wiring objects.

Reserved `@*` keys are rejected by strict shape policy.

Tag discovery and reset orchestration are baseline runtime mechanisms and must not be feature-disabled through config.

The following feature flags must not be introduced:

```text
foundation.tags.enabled
foundation.reset.enabled
```

If no services are registered under the effective reset tag, reset orchestration is a deterministic noop by empty-list semantics.

It is not disabled.

## Tag registry decision

`Coretsia\Foundation\Tag\TagRegistry` is the canonical runtime discovery registry for tagged services.

The canonical API is:

```text
add(string $tag, string $serviceId, int $priority = 0, array $meta = []): void
all(string $tag): list<Coretsia\Foundation\Tag\TaggedService>
```

`TagRegistry::all($tag)` is the single source of truth for discovery lists.

Consumers must treat `TagRegistry->all($tag)` output as canonical.

Consumers must not:

- re-sort the list;
- apply a different dedupe rule;
- re-dedupe by class name;
- re-dedupe by instance identity;
- re-dedupe by interface;
- re-dedupe by metadata values;
- reconstruct discovery through reflection;
- reconstruct discovery from container ids;
- reconstruct discovery from provider internals;
- use diagnostics output as runtime discovery input;
- use filesystem scans as a competing discovery source for the same tag.

The tag name grammar follows the canonical tag registry grammar:

```text
^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$
```

Runtime code should use owner public constants when the owner package is an allowed dependency.

## Tagged service decision

A tagged service entry is represented by:

```text
Coretsia\Foundation\Tag\TaggedService
```

The semantic fields are:

```text
id: string
priority: int
meta: array<string, mixed>
```

`id` is the PSR-11 service id.

`priority` participates in canonical deterministic ordering.

`meta` is owner-defined extension data.

Foundation preserves `meta` for owner-defined consumers, but diagnostics must not serialize arbitrary tag metadata unless the caller has explicitly redacted it and the tag owner has approved the schema.

Consumers may read metadata only when the tag owner has documented a stable metadata schema for that specific tag.

Consumers must not infer generic metadata semantics for all tags.

## Deterministic ordering decision

The canonical discovery ordering rule is single-choice:

```text
priority DESC, id ASC
```

Service id comparison must use byte-order `strcmp` semantics.

Ordering must be locale-independent.

Ordering must not depend on:

- `setlocale(...)`;
- `LC_ALL`;
- ICU collation;
- filesystem traversal order;
- provider class-name sorting by consumers;
- insertion order after canonical discovery output is requested;
- PHP array hash iteration side effects outside Foundation-owned normalization;
- host operating system;
- timestamps;
- random values.

The canonical implementation primitive is:

```text
Coretsia\Foundation\Discovery\DeterministicOrder
```

## DeterministicOrder service boundary decision

`DeterministicOrder` is a stateless static canonical ordering primitive.

It is not a runtime service.

It is not a strategy extension point.

It is not a replaceable DI dependency.

Foundation service providers must not register:

```text
Coretsia\Foundation\Discovery\DeterministicOrder
```

as a container service.

Registering it would incorrectly expose the canonical sort rule as replaceable runtime strategy and would allow discovery-list consumers to drift from the single Foundation-owned ordering rule.

Ordering behavior must be locked by direct unit/contract tests and by integration tests proving that `TagRegistry->all($tag)` returns the canonical order.

## Tag dedupe decision

The canonical tag dedupe policy is single-choice:

```text
first wins
```

For the same `(tag, serviceId)` pair, the first registration is retained.

Later duplicate registrations for the same `(tag, serviceId)` pair are ignored deterministically.

Dedupe applies per tag.

The same service id may appear under different tags, subject to each tag owner's semantics.

This prevents accidental double-registration while keeping discovery stable across runs and operating systems.

## HTTP middleware discovery decision

`core/foundation` does not implement HTTP middleware stacks.

Foundation provides only the common discovery mechanism and ordering primitive that HTTP runtime owners consume.

The canonical HTTP middleware taxonomy and slot contents are owned by:

```text
docs/ssot/http-middleware-catalog.md
```

The canonical middleware slot tags are:

```text
http.middleware.system_pre
http.middleware.system
http.middleware.system_post
http.middleware.app_pre
http.middleware.app
http.middleware.app_post
http.middleware.route_pre
http.middleware.route
http.middleware.route_post
```

HTTP middleware stack composition must consume each slot through:

```text
TagRegistry->all(<slotTag>)
```

HTTP middleware consumers must preserve the exact returned order.

HTTP middleware consumers must not re-sort or re-dedupe the slot lists.

The legacy `http.middleware.user*` names remain forbidden by the tag registry policy.

## CLI discovery decision

Phase 0 `platform/cli` is kernel-free and uses only the config registry:

```text
cli.commands
```

Phase 0 does not use tag-based CLI command discovery.

A future Kernel-backed CLI mode may use tag-based discovery through:

```text
cli.command
```

only when that mode runs over Kernel/container infrastructure.

`core/foundation` provides the tag registry and deterministic ordering mechanism.

It does not own the `cli.command` tag.

## Reset orchestration decision

`core/foundation` owns reset discovery and reset execution through:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

`ResetOrchestrator` is the only runtime reset executor that `core/kernel` is allowed to use.

The Kernel must not enumerate tagged reset services directly.

The Kernel must call only:

```text
ResetOrchestrator::resetAll(): void
```

The effective reset discovery tag is resolved by Foundation wiring/config from:

```text
foundation.reset.tag
```

The reserved default value is:

```text
kernel.reset
```

The reset orchestrator must:

1. use the effective reset discovery tag supplied by Foundation wiring/config;
2. obtain the discovery list only through `TagRegistry->all($effectiveResetTag)`;
3. resolve each service through `Psr\Container\ContainerInterface`;
4. verify that each resolved service implements `Coretsia\Contracts\Runtime\ResetInterface`;
5. call `ResetInterface::reset()` exactly once per resolved service per reset cycle;
6. be safely callable when the discovery list is empty;
7. never rely on reflection or autowire during reset execution;
8. never emit stdout or stderr;
9. never dump service instances, constructor arguments, raw config payloads, environment values, tokens, or absolute local paths.

## Reset ordering decision

Before epic `1.250.0`, reset orchestration runs in legacy/base mode.

Legacy/base mode ordering is single-choice:

```text
exact TagRegistry->all($effectiveResetTag) order
```

In legacy/base mode the reset executor must not:

- parse reset metadata;
- apply additional sorting;
- apply additional dedupe;
- group services;
- compute a reset plan.

Enhanced reset planning is deferred to epic `1.250.0`.

In enhanced mode, a future owner may compute a deterministic reset plan from supported metadata keys.

The future enhanced order is expected to be:

```text
priority DESC
group ASC using strcmp after normalization
serviceId ASC using strcmp
```

When enhanced mode is disabled, behavior must remain exact legacy/base mode.

Epic `1.200.0` locks only the legacy/base behavior.

## Reset failure decision

Reset tag misuse must hard-fail deterministically.

In epic `1.200.0`, a tagged reset service that does not implement:

```text
Coretsia\Contracts\Runtime\ResetInterface
```

must fail with the stable machine-readable message:

```text
reset-not-resettable
```

Typed reset failure is intentionally deferred to epic `1.250.0`.

The future canonical typed failure is expected to use:

```text
ResetException
CORETSIA_RESET_SERVICE_NOT_RESETTABLE
message: reset-not-resettable
```

Tests in epic `1.200.0` must not require the future typed exception class or future error code.

They must lock only deterministic hard-fail behavior and the stable message.

## Stateful marker decision

`kernel.stateful` is a fixed non-configurable enforcement marker.

It is used only by CI/static-analysis rails.

Runtime reset execution must not depend on:

```text
kernel.stateful
```

If a service is stateful, it must be explicitly tagged:

```text
kernel.stateful
```

Any service tagged `kernel.stateful` must:

- implement `Coretsia\Contracts\Runtime\ResetInterface`;
- also be tagged with the effective Foundation reset discovery tag.

The effective Foundation reset discovery tag defaults to:

```text
kernel.reset
```

but may be configured through:

```text
foundation.reset.tag
```

Static-analysis rails may enforce stateful/reset invariants.

The Kernel must not consume `kernel.stateful` at runtime.

## Kernel integration decision

The conceptual long-running runtime flow is:

```text
beginUow()
run before_uow hooks
handle UoW
run after_uow hooks
resetOrchestrator.resetAll()
endUow()
```

Kernel lifecycle implementation is not part of this ADR.

The concrete Kernel trigger point is owned by `core/kernel`.

The intended trigger point is after each unit of work, after after-UoW hooks complete.

Foundation owns the reset orchestrator.

Kernel owns lifecycle timing and hook execution.

## Stable JSON encoder decision

`core/foundation` provides a stable JSON encoder:

```text
Coretsia\Foundation\Serialization\StableJsonEncoder
```

The encoder exists to produce byte-stable JSON for diagnostics and runtime-safe outputs.

Accepted input types are intentionally narrow:

```text
null
bool
int
string
list<value>
array<string, value>
```

Rejected input types include:

```text
float
NaN
INF
-INF
resource
object
Closure
non-string map keys
```

Callable-ness is not treated as a standalone runtime type check.

Plain strings remain plain strings even if PHP could call them as function names.

Output rules are:

- maps are sorted recursively by `strcmp`;
- lists preserve order;
- LF-only;
- final newline required;
- no implicit redaction;
- no environment inspection.

Redaction is caller responsibility.

The encoder must not silently accept floats because floats create precision and serialization drift.

## Container diagnostics decision

`core/foundation` provides deterministic container diagnostics through:

```text
Coretsia\Foundation\Container\ContainerDiagnostics
```

Diagnostics are structural snapshots only.

Diagnostics may include:

- schema version;
- service ids;
- tag names;
- tag priorities.

Diagnostics must not include:

- service instances;
- constructor arguments;
- factories;
- closures;
- reflection data;
- raw config payloads;
- arbitrary tag metadata;
- environment values;
- tokens;
- credentials;
- cookies;
- authorization headers;
- private customer data;
- absolute local paths.

Container diagnostics must use `StableJsonEncoder` for JSON output.

JSON output must be byte-stable and end with a final newline.

Absolute local path-like service ids must not be emitted raw.

Safe derived forms such as hash and length are allowed when needed.

Diagnostics must not become a runtime discovery source.

## Observability decision

Epic `1.200.0` introduces no observability backend and no telemetry emission.

Foundation diagnostics are structural snapshots only.

They must be deterministic and redaction-safe.

Default noop observability or logger bindings are not introduced by this epic.

Such bindings are deferred to later Foundation or platform observability epics.

Any future logs, metrics, spans, or profiling around Foundation behavior must follow:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/profiling-ports.md
```

Metric labels must remain within the canonical allowlist.

## Security and redaction decision

Foundation runtime code must not leak sensitive runtime data.

Forbidden diagnostic and error output includes:

- `.env` values;
- credentials;
- secrets;
- tokens;
- private keys;
- authorization headers;
- cookies;
- raw request payloads;
- raw response payloads;
- raw queue messages;
- raw SQL;
- constructor arguments;
- service instances;
- arbitrary tag metadata;
- reflection dumps;
- raw config payloads;
- private customer data;
- absolute local paths.

Allowed safe diagnostic information is limited to structural metadata such as service ids, tag names, priorities, schema versions, and safe derivations such as:

```text
hash(value)
len(value)
```

Runtime owners must prefer omission over unsafe emission.

## Dependency boundary decision

`core/foundation` is a runtime core package.

It may depend on:

```text
core/contracts
psr/container
```

It must not depend on:

```text
platform/*
integrations/*
devtools/*
```

The allowed direction is:

```text
core/foundation -> core/contracts
platform/* -> core/foundation
core/kernel -> core/foundation
integrations/* -> core/contracts or platform-owned ports when allowed
```

The forbidden direction is:

```text
core/foundation -> platform/*
core/foundation -> integrations/*
core/foundation -> devtools/*
core/foundation -> generated tooling artifacts as runtime dependencies
```

Phase 0 tooling libraries and gates are tools-only.

Runtime packages must not import them as compile-time dependencies.

## Module metadata decision

`FoundationModule` exposes stable Foundation package metadata and the provider list.

It does not perform:

- filesystem scanning;
- container construction;
- config loading;
- runtime side effects;
- module plan resolution;
- manifest reading;
- Composer traversal.

Canonical module metadata includes:

```text
module_id: core.foundation
package_id: core/foundation
composer: coretsia/core-foundation
kind: runtime
config_root: foundation
```

The provider list is module-declared order.

`ContainerBuilder` must preserve that order when it is supplied by the caller.

The owning Kernel/module-plan epics may later integrate this metadata into the canonical module plan.

## DI wiring decision

`FoundationServiceProvider` registers Foundation-owned runtime services.

It must register:

```text
Coretsia\Foundation\Tag\TagRegistry
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

`TagRegistry` is registered as the exact builder-owned instance.

`ResetOrchestrator` is created through:

```text
Coretsia\Foundation\Provider\FoundationServiceFactory
```

`FoundationServiceFactory` is a stateless factory/wiring helper.

It must not keep mutable runtime state:

- no static snapshots;
- no caches;
- no buffers;
- no retained container instance;
- no retained config payload.

`DeterministicOrder` is intentionally not registered as a container service.

## Config/tag/artifact registry decision

Epic `1.200.0` introduces no new tag registry rows.

It implements owner constants for Foundation-owned existing reserved tags:

```text
kernel.reset
kernel.stateful
```

The implementation path is:

```text
framework/packages/core/foundation/src/Provider/Tags.php
```

Epic `1.200.0` introduces no new config root registry rows.

It implements the already-reserved `foundation` config root.

Epic `1.200.0` introduces no generated artifacts.

## Verification decision

Foundation behavior must be locked by unit, contract, and integration tests.

Required test areas include:

- interfaces are not autowired;
- `Container::canAutowire()` is strict on missing Foundation config;
- provider order is preserved exactly;
- later container bindings override earlier bindings;
- `DeterministicOrder` implements `priority DESC, id ASC`;
- ordering uses `strcmp` and is locale-independent;
- `TagRegistry->all($tag)` returns canonical order;
- tag dedupe is first-wins;
- reset orchestrator invokes each resettable service exactly once per cycle;
- reset orchestrator uses configured `foundation.reset.tag`;
- reset orchestrator rejects tagged non-resettable services with `reset-not-resettable`;
- Foundation config defaults return subtree-only shape;
- Foundation config has no reserved `@*` keys;
- `StableJsonEncoder` rejects floats and non-json-like values;
- `StableJsonEncoder` sorts map keys recursively and preserves list order;
- `ContainerDiagnostics` JSON is deterministic;
- `ContainerDiagnostics` does not leak secrets;
- `ContainerDiagnostics` does not contain absolute local paths.

Architecture gates must verify that `core/foundation` does not depend on forbidden package families.

## Consequences

Positive consequences:

- Runtime packages get one canonical tag discovery mechanism.
- Service discovery order is stable across operating systems and process locales.
- HTTP middleware, reset services, CLI commands, error mappers, health checks, and future tagged lists can share one ordering law.
- Consumers do not need to duplicate sorting or dedupe logic.
- Reset orchestration becomes a Foundation-owned runtime primitive instead of being duplicated by Kernel or platform packages.
- Kernel integration stays small: Kernel calls the orchestrator instead of enumerating reset services.
- `foundation.reset.tag` allows the reset discovery tag to be configured without making Kernel read or hardcode tag strings.
- Diagnostics can be stable, reproducible, and safe by construction.
- The runtime package remains independent of platform packages, integrations, and tooling-only packages.

Trade-offs:

- Provider order must be made deterministic by the upstream module/kernel planner.
- Foundation does not support arbitrary container extension strategies in this epic.
- `DeterministicOrder` is not replaceable through DI.
- Consumers that need different ordering must use a distinct owner-approved tag or wait for an owner-approved planning layer.
- Reset planning metadata is deferred to epic `1.250.0`.
- Foundation diagnostics intentionally omit rich details such as constructor args, instances, and tag metadata.

## Rejected alternatives

### Sort service providers globally by FQCN inside ContainerBuilder

Rejected.

Provider order is semantically significant.

Global FQCN sorting would make override behavior arbitrary and detached from the future deterministic module plan.

The upstream module/kernel planner owns deterministic provider-list construction.

`ContainerBuilder` preserves the caller-supplied order exactly.

### Make earlier container definitions win

Rejected.

Later provider bindings overriding earlier bindings is the clearer and more conventional composition rule for deterministic DI registration.

It also allows a later owner-approved provider or application provider to override earlier defaults.

This rule applies only to container definitions.

Tag dedupe remains first-wins and independent.

### Make tag dedupe later-wins

Rejected.

Later-wins tag dedupe would allow accidental duplicate registrations to silently change discovery metadata and priority.

First-wins prevents duplicate registration drift while preserving the first deterministic declaration for a `(tag, serviceId)` pair.

### Let every consumer sort tagged services itself

Rejected.

That would recreate the original problem: each consumer could drift in sorting, tie-breakers, dedupe, metadata interpretation, and locale behavior.

`TagRegistry->all($tag)` is the canonical discovery list authority.

### Use insertion order as the canonical discovery order

Rejected.

Insertion order is not enough for cross-package deterministic discovery.

Consumers need priority ordering and deterministic tie-breaking.

The canonical order is:

```text
priority DESC, id ASC
```

### Use locale-aware string comparison

Rejected.

Locale-aware collation can vary across machines, process settings, operating systems, and installed locale/ICU data.

Foundation ordering must be byte-stable.

String comparison uses `strcmp` semantics.

### Register DeterministicOrder as a DI service

Rejected.

`DeterministicOrder` is a stateless static canonical ordering primitive.

Registering it as a service would imply it has lifecycle, dependencies, configuration, reset behavior, or replaceable strategy semantics.

The ordering law is not a strategy extension point in this epic.

### Make reset orchestration Kernel-owned

Rejected.

Reset discovery and execution are reusable runtime safety mechanisms.

Foundation owns the reset orchestrator so Kernel does not need to duplicate tag enumeration, ordering, service resolution, reset validation, or reset failure behavior.

Kernel owns lifecycle trigger points.

Foundation owns reset execution.

### Let Kernel hardcode `kernel.reset`

Rejected.

The effective reset discovery tag is Foundation-owned config/wiring.

Kernel must not know, read, or hardcode the reset discovery tag name.

Kernel calls only:

```text
ResetOrchestrator::resetAll()
```

### Use `kernel.stateful` for runtime reset discovery

Rejected.

`kernel.stateful` is an enforcement marker for CI/static-analysis rails.

Runtime execution must not depend on it.

Runtime reset discovery uses the effective Foundation reset tag from:

```text
foundation.reset.tag
```

### Add reset feature flags

Rejected.

Tag discovery and reset orchestration are baseline runtime safety mechanisms.

Disabling them through config would create unsafe runtime modes and increase behavior drift.

Empty reset lists are represented by empty-list semantics.

### Parse reset metadata in epic 1.200.0

Rejected.

Reset metadata planning belongs to the enhanced reset epic `1.250.0`.

Epic `1.200.0` locks legacy/base reset behavior only.

Base behavior executes exactly in `TagRegistry->all($effectiveResetTag)` order.

### Put PSR-7, HTTP middleware, or platform APIs into Foundation

Rejected.

Foundation must remain runtime-core and platform-neutral.

HTTP middleware implementation belongs to `platform/http`.

HTTP slot taxonomy and contents are governed by the HTTP middleware catalog SSoT.

Foundation provides only tag registry and ordering mechanisms.

### Use tooling packages for runtime deterministic helpers

Rejected.

Phase 0 tooling packages are tools-only.

Runtime packages must not depend on `devtools/*`.

Foundation must implement its runtime-safe deterministic helpers directly or through allowed runtime dependencies.

### Let diagnostics dump complete container internals

Rejected.

Container internals contain unsafe and unstable runtime data such as instances, constructor arguments, factories, closures, reflection data, environment-derived values, credentials, and absolute paths.

Diagnostics are structural snapshots only.

### Allow floats in stable JSON diagnostics

Rejected.

Floats introduce precision drift, `NaN`, `INF`, `-INF`, locale issues, and JSON serialization instability.

Stable diagnostics accept only deterministic JSON-safe scalar/list/map values.

## Non-goals

This ADR does not implement:

- Kernel lifecycle runtime;
- Kernel module planning;
- Kernel hook executor;
- HTTP middleware stack;
- HTTP middleware classes;
- CLI command execution;
- tag-based CLI command runtime;
- platform adapters;
- integrations;
- observability backend;
- logger bindings;
- metrics backend;
- tracing backend;
- default noop observability bindings;
- reset enhanced planning;
- typed reset exception taxonomy;
- reset metadata schema;
- generated container artifacts;
- generated middleware artifacts;
- generated reset artifacts;
- compiled container format;
- route artifacts;
- platform-specific config loading;
- config merge engine;
- config validation engine;
- Composer package scanning;
- filesystem scanning;
- tooling gates implementation.

## Related SSoT

- `docs/ssot/di-tags-and-middleware-ordering.md`
- `docs/ssot/tags.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/http-middleware-catalog.md`
- `docs/ssot/uow-and-reset-contracts.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/error-descriptor.md`
- `docs/ssot/artifacts.md`

## Related ADRs

- `docs/adr/ADR-0002-config-env-source-tracking-directives-invariants.md`
- `docs/adr/ADR-0006-reset-interface-uow-hooks.md`

## Related epic

- `1.200.0 Foundation: DI Container + Tags + DeterministicOrder + Reset orchestration`
