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

# coretsia/platform-routing

`platform/routing` is the deterministic application route compiler and artifact-backed runtime router for the Coretsia Framework.

Scope: target-local source route aggregation, immutable route grouping, canonical route admission and reserved-domain protection, `routes@1` artifact validation and atomic publication, artifact-only matching, conditional owner-artifact readiness, explicit Routing CLI commands, path-template context enrichment, and bounded routing match observability.

Out of scope: HTTP transport processing, system-endpoint dispatch, middleware execution, handler resolution and action invocation, Kernel generation publication, context storage and reset execution, and observability backend implementation.

This README is a consumer-oriented package summary.

## Package identity

- Path: `packages/platform/routing`
- Package id: `platform/routing`
- Composer name: `coretsia/platform-routing`
- Module id: `platform.routing`
- Namespace: `Coretsia\Platform\Routing\*` (PSR-4: `src/`)
- Kind: runtime
- Config root: `routing`

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions MUST NOT be used.

## Dependency policy

- Direct Composer requirements:
  - PHP `^8.4`
  - `coretsia/core-contracts`
  - `coretsia/core-foundation`
  - `coretsia/core-kernel`
  - `coretsia/platform-redaction`
  - `psr/container` and `psr/log`
- Direct PHP source dependencies:
  - `core/contracts`
  - `core/foundation`
  - `core/kernel`, restricted to approved public source-host and runtime input APIs
- Forbidden production implementation dependencies:
  - Kernel internal implementation APIs;
  - concrete Redaction implementation classes;
  - `platform/cli`, `platform/http`, `platform/http-app`, `platform/errors`, and `platform/problem-details` implementation classes;
  - `integrations/*` and repository machinery under `tools/**`.

The runtime module requires `core.foundation`, `core.kernel`, and `platform.redaction`. Redaction supplies the canonical sensitive-key classifier and redactor bindings through contracts-level ports, not concrete implementation dependencies.

Routing does not require `platform/logging`. It consumes the effective `Psr\Log\LoggerInterface` binding. Routing and HTTP MUST NOT depend directly on each other's source, Composer packages, or runtime modules. Kernel does not depend on Routing; Foundation does not depend on Kernel or platform packages.

## Runtime responsibilities

`platform/routing` provides:

- one canonical declarative service graph through `RoutingServiceProvider`, shared by source and compiled containers;
- explicit target-local source compilation through the public Kernel source-host API;
- lazy, tag-backed route provider aggregation with deterministic precedence;
- the public immutable `RouteGroup` source declaration helper;
- complete template, conflict, reserved-domain, safety, and resource admission;
- owner-specific `routes@1` schema, fingerprint, safe reading, and atomic publication;
- one shared immutable `CompiledRouteTable`, consumed by `CompiledRouter` and `RoutingArtifactReadiness`;
- `routes:compile` and `routes:validate` through the canonical CLI contribution boundary;
- exclusive mutation of `ContextKeys::PATH_TEMPLATE` through the contracts-level context writer;
- independent tracing, metrics, and safe diagnostic logging for completed routing matches.

Service definitions do not resolve route providers or read artifacts. Runtime paths and route payloads are not embedded in compiled container definitions. Table hydration happens when its shared service is resolved.

## Module activation

Composer installation and module activation are separate. The effective target-local `ModulePlan` determines Routing participation.

- Select `platform.routing` and `platform.cli` in the command-owning Console target to expose Routing commands.
- Select `platform.routing` and the intended route-contributing modules in each `web` or `api` target whose routes are to be compiled.
- Select HTTP independently for an HTTP runtime, with the intended driver and compatible PSR-7/17 factories. Routing does not supply HTTP transport bindings.

Use the canonical application module-selection and dependency composition mechanisms, preserving existing presets and unrelated overrides. Installing a route-contributing package without enabling its owning module does not contribute its routes. The Console plan determines command availability, not the providers selected for Web or API compilation.

## Public contracts

The canonical routing descriptors and ports belong to `core/contracts` under `Coretsia\Contracts\Routing`:

- `RouteDefinition` — immutable format-neutral declaration and canonical descriptor representation;
- `RouteMatch` — successful match containing the declared name, template, logical handler, string parameters, and safe metadata;
- `RouteProviderInterface` — `id(): string` and `routes(): array`, returning a stable provider identity and a flat ordered list of `RouteDefinition` instances;
- `RouterInterface` — `match(string $method, string $path, ?string $host = null): ?RouteMatch`;
- `RoutingArtifactReadinessInterface` — `assertReady(): void`, the narrow transport-neutral owner-artifact readiness port.

The public package-owned declaration helper is `Coretsia\Platform\Routing\Definition\RouteGroup`. It is not a contracts-level descriptor or runtime matching port.

Consumers resolve `RouterInterface` and `RoutingArtifactReadinessInterface` through the canonical container aliases. `CompiledRouter`, `CompiledRouteTable`, the compiler, artifact services, and observer are owner implementation details, not additional consumer contracts.

## Route declaration and provider discovery

A provider returns deterministic safe declarations. Provider identities and route names are stable logical identifiers; duplicate provider identities and duplicate route names are rejected. Handler identifiers describe logical actions, not executable values or filesystem paths.

Register the provider service in its enabled owning module's canonical definition provider, using `ContainerDefinitionBuilder` and `ReservedTags::ROUTING_ROUTE_PROVIDER` (`routing.route_provider`). Tag metadata MUST be empty (`[]`). For example, within the definition provider's `define()` method, where `CatalogRouteProvider` is the application's own implementation of `RouteProviderInterface`:

```php
$definitions
    ->classService(CatalogRouteProvider::class, CatalogRouteProvider::class)
    ->tag(ReservedTags::ROUTING_ROUTE_PROVIDER, CatalogRouteProvider::class, 10, []);
```

`RouteProviderRegistry` enumerates only `TagRegistry::all(ReservedTags::ROUTING_ROUTE_PROVIDER)`. Foundation owns tag order: priority descending, then service id ascending (`strcmp`). Each provider's emitted route order is preserved exactly. Routing does not re-sort or deduplicate registry output, scan files, discover controllers or attributes, or resolve handlers.

### Templates and matching precedence

Supported templates are `/` and paths consisting of complete static or named placeholder segments. Static segments match `[a-z_-][a-z0-9_-]*`; placeholder names match `[A-Za-z_][A-Za-z0-9_]*`. Templates are case-sensitive and limited to 256 bytes. Placeholder names are unique across the complete template.

Supported complete-segment requirement expressions are exactly:

```text
[0-9]+
[A-Za-z]+
[A-Za-z0-9_-]+
```

An unconstrained placeholder matches a non-empty valid encoded segment. Requirement keys must identify existing placeholders. Arbitrary regular expressions, wildcard or optional segments, embedded placeholders, dot segments, empty segments, and non-empty defaults are unsupported.

Supported methods are `GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `TRACE`, and `CONNECT`. `OTHER` is an observability sentinel, not a routing method.

Equivalent matching domains with intersecting method sets are rejected even when placeholder names differ. Distinct overlapping domains are allowed and use first-match-wins in canonical provider and route order. There is no implicit static-route precedence or specificity sorting.

### Source route grouping

`RouteGroup` is immutable and provides:

```text
static root(): self
group(string $pathPrefix, string $namePrefix, array $requirements, array $metadata): self
route(RouteDefinition $route): RouteDefinition
routes(array $routes): array
```

The root has path prefix `/`, empty name prefix, and empty requirements and metadata. Child groups do not modify their parents. Paths join with one literal slash (`/` is the identity); non-empty name prefixes join with `.`. Non-empty group name prefixes consist of dot-separated components matching `[A-Za-z_][A-Za-z0-9_-]*`.

Within a provider, nested groups can compose parameterized prefixes and ordinary route declarations:

```php
use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Platform\Routing\Definition\RouteGroup;

$api = RouteGroup::root()->group('/api', 'api', [], ['area' => 'api']);
$v1 = $api->group('/v1', 'v1', [], ['version' => 1]);
$teams = $v1->group('/teams/{team}', 'teams', ['team' => '[0-9]+'], []);

$routes = $teams->routes([
    new RouteDefinition(
        name: 'member',
        methods: ['GET'],
        pathTemplate: '/members/{member}',
        handler: 'Members::show',
        requirements: ['member' => '[A-Za-z]+'],
        metadata: ['view' => 'summary'],
    ),
]);
```

The resulting list contains a canonical descriptor named `api.v1.teams.member`, with template `/api/v1/teams/{team}/members/{member}` and inherited numeric `team` and alphabetic `member` requirements. Its metadata is the canonical map containing `area`, `version`, and `view`.

Group requirements refer only to placeholders declared in that group's supplied prefix; route requirements refer to the route's own supplied template. Combined requirements cannot override inherited keys. Duplicate placeholders across nested prefixes and routes are rejected. Metadata is normalized through Foundation `JsonLikeNormalizer`; duplicate inherited keys, including keys whose value is `null`, are rejected without recursive merging. Methods, handler, and defaults remain owned by the individual route.

`routes()` accepts only a list of ordinary `RouteDefinition` instances and preserves its order. Providers MUST return flattened descriptors rather than group objects. All flattened routes undergo the same global admission as standalone declarations. Equivalent grouped and standalone declarations produce identical canonical bytes and fingerprints for identical ordered descriptors and authoritative inputs. Groups do not introduce middleware, authorization, controller resolution, DI wiring, or a second matching mechanism.

## Reserved path ownership

Routing consumes the canonical Foundation-owned primitives:

- `Coretsia\Foundation\Routing\SystemRouteTable`
- `Coretsia\Foundation\Routing\ReservedPathMatcher`

All `active`, `reserved`, and `dev_only` reservations apply independently of endpoint activation, module selection, environment, and HTTP method. Routing checks complete template-domain intersection against exact and slash-terminated prefix declarations, using the canonical segment matching semantics and deterministic concrete witnesses confirmed by `ReservedPathMatcher::match()`.

For example, `/_alive`, `/health/{item}`, and unconstrained `/{segment}` are rejected. A numeric requirement on `/{number}` makes the domain disjoint from the canonical reserved declarations; other admission rules still apply.

Validation runs during compilation and hydration. Runtime matching independently rejects concrete reserved paths. Routing does not maintain a separate reservation registry or normalization implementation.

## Configuration

The config root is `routing`. The canonical defaults file is `config/routing.php`, which returns the subtree only, without a repeated root key:

```php
return [
    'tracing' => [
        'enabled' => true,
    ],
    'metrics' => [
        'enabled' => true,
    ],
    'logging' => [
        'enabled' => true,
    ],
];
```

Kernel loads, merges, and validates the `routing.*` subtree. `config/rules.php` defines the strict schema: each flag is boolean and unknown keys are rejected at every declared map level.

`RoutingServiceFactory::matchObserver()` reads `routing.tracing.enabled`, `routing.metrics.enabled`, and `routing.logging.enabled` from the effective `ConfigRepositoryInterface`. The flags independently control Routing's own signals and are all enabled by default. They do not affect compilation, matching, admission, readiness, or the other signals.

There are no Routing configuration switches for module activation, artifact paths, runtime source fallback, or disabling reserved-route protection or artifact readiness.

## Routing commands

Routing contributes normal CLI commands through the contracts-level `CommandInterface`, `InputInterface`, and `OutputInterface` and the canonical `cli.command` tag. Both commands belong to the `routes` group, are visible, accept no positional arguments, and require a single non-repeatable `--app` option with value `web` or `api`.

From the application root with the required CLI and Routing modules installed and selected:

```bash
php coretsia routes:validate --app=web
php coretsia routes:validate --app=api
php coretsia routes:compile --app=web
php coretsia routes:compile --app=api
```

Both commands execute from the Console source host. `RoutesCommandTargetResolver` reuses `KernelOpsHostBooter::bootForTarget()` with the authoritative application root and explicit target. Compilation uses the selected source host's `ModulePlan::app()` and `RuntimeDiagnosticsInput::appEnv()`, requires enabled Routing and `artifactGenerationLoaded() === false`, and does not require a loaded Kernel generation. Routing neither infers a target nor starts an additional UnitOfWork.

### `routes:validate`

Validates all target-local declarations through `RoutesCompiler`, including complete envelope validation and serialized-byte limits. It does not resolve the artifact writer, read or modify route artifacts, create artifact directories, or acquire the publication lock. This is source validation, not verification of a deployed artifact.

Successful result fields: `app`, `valid` (`true`), and `route_count`.

### `routes:compile`

Uses the same complete source compilation and validation operation before resolving `RoutesArtifactWriter` to publish the validated envelope. It does not publish partial results.

Successful result fields: `app`, `artifact` (fixed basename `routes.php`), and `route_count`.

Both commands return `0` on success and `1` for handled failures. Expected failures emit fixed safe error codes and bounded reason tokens through `OutputInterface`; unexpected failures are not treated as success. The CLI owns output rendering and command lifecycle. Use the CLI-global `--format=adaptive|json|table|plain` option, not a Routing-local format option. Machine consumers explicitly select `--format=json` and check the process exit code.

## Routes artifact

Routing owns `routes@1`, with generator `platform/routing/artifacts` and basename `routes.php`. The authoritative paths are:

```text
<RuntimePathContext::artifactRoot()>/routes.php
<RuntimePathContext::artifactRoot()>/routes.lock
```

The default layout is `var/cache/<appTarget>/`, but the effective root comes from Kernel's target-local bootstrap configuration. `routes.lock` is a publication coordination file, not an artifact. Neither file belongs to a Kernel immutable generation or changes its fingerprint or `current` pointer.

The logical envelope contains exactly `_meta` and `payload`. The header contains `fingerprint`, `generator`, `name` (`routes`), and `schemaVersion` (`1`). The payload contains `app` (`web` or `api`) and `routes`, the ordered list of complete canonical `RouteDefinition::toArray()` representations. Each entry contains `defaults`, `handler`, `metadata`, `methods`, `name`, `pathTemplate`, `requirements`, and `schemaVersion` (`1`). No group structure or provider identity field is serialized.

The lowercase SHA-256 fingerprint uses Foundation stable JSON bytes of the canonical payload, authoritative environment identifier, artifact identity and generator, and canonical system-route table entries and schema version. The environment identifier is not serialized separately. Route order is fingerprint-relevant; timestamps, absolute paths, raw environment values, and Kernel generation ids are not fingerprint inputs. The fingerprint is not a signature or freshness check against live providers.

`RoutesArtifactSchema` validates the complete envelope and emits canonical PHP-array bytes through the shared Foundation dumper. Map keys are sorted recursively by byte order and lists preserve their declared canonical order. Identical inputs produce identical bytes, with LF-only output and exactly one final LF.

Artifact limits include 16,777,216 serialized bytes, 4,096 flattened routes, depth 32, 100,000 nodes, 65,536 bytes per string, and 8,388,608 aggregate string bytes. Complete envelope structural accounting uses Foundation `JsonLikeNormalizationLimits`; every descriptor also passes the canonical Redaction limits.

Publication uses Foundation `ScopedFileLock` and `AtomicFileWriter`. Complete validation precedes filesystem publication. Existing-byte comparison and replacement occur under the same exclusive owner-local lock. Identical bytes leave the artifact unchanged; a failure before atomic replacement preserves the previous valid artifact. Successful replacement is the commit point and is not rolled back for subsequent lock cleanup failure.

## Artifact-only runtime and HttpApp boundary

`RoutesArtifactReader` captures one bounded regular-file snapshot and decodes it using Foundation `StablePhpArrayParser::parseBounded()`. It validates canonical bytes, exact envelope and descriptor shapes, authoritative target and fingerprint, resource limits, conflicts, reserved domains, and safety before table hydration. Artifact PHP source MUST NOT be executed, included, required, or evaluated.

`CompiledRouteTable` retains immutable validated descriptors and their parsed templates in stored order. `CompiledRouter` implements `RouterInterface` and returns the first matching canonical `RouteMatch` or `null`. It accepts only canonical encoded URI paths whose Foundation normalization result equals the supplied path byte-for-byte. Invalid, reserved, non-canonical, and unmatched input returns `null`; method matching is exact and encoded octets are not decoded or case-normalized. Non-root trailing slash paths are not aliases. Host-based matching, implicit GET-to-HEAD fallback, automatic OPTIONS, redirects, and 405 response generation are not provided.

Runtime matching does not read artifact files, resolve route providers, interpret groups, compile or repair artifacts, resolve handlers, or invoke actions. Replacement of an artifact does not change a hydrated table; a new runtime is needed to consume a replacement.

`RoutingArtifactReadiness` implements the contracts-level readiness port and consumes the same shared table as the router. Successful table construction establishes readiness; `assertReady()` does not perform another read or parse. When the effective HTTP target enables `platform.routing`, the HTTP-owned `HttpRuntimeEntrypointGuard` resolves the port and calls `assertReady()` after module, driver, and PSR-17 factory checks and before HTTP runtime construction. Missing or invalid required artifacts or bindings prevent startup. If Routing is not selected, HTTP does not require this artifact.

Deployment MUST prepare the target-local route artifact and the Kernel generation separately, using the intended declarations, configuration, modules, and authoritative environment before starting the runtime. Kernel `cache:verify` does not verify the Routing artifact. Successful source validation alone is not publication.

HttpApp integration consumes the contracts-level matching port and match descriptor for application dispatch. Routing neither implements `RouterMiddleware` nor owns request/response conversion, HTTP pipeline construction, system endpoint execution, action resolution, invocation, or response rendering.

## Context and UnitOfWork

`CompiledRouter` requires `Coretsia\Contracts\Context\ContextWriterInterface` and uses its `remove()` and `set()` operations exclusively for `ContextKeys::PATH_TEMPLATE` (`path_template`). Routing owns this key's semantic mutation:

- remove any previous template at the start of each matching operation;
- write only the complete declared template on success, including flattened prefixes;
- leave the template absent on a completed miss;
- finish context mutation before routing completion observability.

Routing does not read raw paths from context, write other keys, or store raw paths, hosts, parameters, or handler identifiers in context. It does not depend on `ContextAccessorInterface` or concrete `ContextStore`. Foundation owns shared context storage and structural validation; Kernel owns UnitOfWork lifecycle and reset orchestration. Routing neither starts or finishes a UnitOfWork nor registers the immutable table or router for reset execution.

## Observability

Routing consumes the canonical `TracerPortInterface`, `MeterPortInterface`, `Psr\Log\LoggerInterface`, and Foundation `Stopwatch` through mandatory dependencies. Foundation supplies noop tracer, meter, and `NoopLogger` bindings when no effective backend overrides them. Routing does not select, instantiate, or require a concrete logging or observability backend.

Routing span name:

```text
routing.match
```

Routing metric names:

```text
routing.match_total
routing.match_duration_ms
```

The initial span attribute is `method`. Completed matches add `outcome` and, only on success, `path_template`. Both metrics use exactly `method` and `outcome` labels. `method` is one of the supported canonical methods or `OTHER`; `outcome` is `matched|not_matched`. The counter increments by one per completed operation; duration is an integer millisecond observation in `0..4294967295` when measurement succeeds. Timing is attempted only when metrics are enabled. The path template is not a metric label.

The fixed diagnostic log message is:

```text
routing.match.completed
```

`RoutingMatchObserver` attempts one `LoggerInterface::debug()` call per completed match operation when `routing.logging.enabled` is true, with `method`, `outcome`, and `path_template` only on success. Logging is independent of tracing and metrics. The effective logging implementation owns admission, filtering, formatting, redaction, and sink selection; the Foundation noop logger emits nothing.

Span start, attribute updates, span end, stopwatch, counter, duration observation, and logger failures are independently best-effort and MUST NOT change matching outcomes. Unexpected matching failures propagate unchanged; an opened span is finalized best-effort, without recording raw exceptions or emitting completed-match metrics or logs. Signal handles are invocation-local. Routing does not duplicate HTTP request or CLI command lifecycle observations.

Raw paths, hosts, query strings, request data, matched parameter values, handler identifiers, raw Throwable messages, and unbounded identifiers MUST NOT be emitted by Routing. In Routing observability, the declared path template is used only as the approved successful-match span attribute and diagnostic log context field, never as a metric label.

## Errors

Routing uses fixed safe error codes and reason tokens for expected failures:

- `CORETSIA_ROUTING_COMMAND_TARGET_INVALID` — invalid command host, target selection, or target module availability;
- `CORETSIA_ROUTING_ROUTE_DEFINITION_INVALID` — invalid provider output, route semantics, conflicts, safety, or resource admission;
- `CORETSIA_ROUTING_RESERVED_ROUTE_FORBIDDEN` — intersection with canonical system reservations;
- `CORETSIA_ROUTES_ARTIFACT_INVALID` — invalid envelope, bytes, fingerprint, target, or artifact read/hydration;
- `CORETSIA_ROUTES_ARTIFACT_WRITE_FAILED` — artifact publication failure.

Source-host boot failures retain the canonical safe `KernelOpsFailedException` boundary. Invalid runtime input is a non-match, not an HTTP response. Unexpected failures are not converted into successful results. Public diagnostics do not disclose rejected descriptors, bytes, absolute paths, parser offsets, raw filesystem errors, or underlying exception messages.

## Security / Redaction

Providers MUST emit safe deterministic declarations and logical identifiers, not request-derived data, private customer data, credentials, raw environment values, or executable handlers. Application-controlled metadata is a canonical JSON-like map. Routing recursively rejects sensitive metadata and requirement keys regardless of their values, and rejects filesystem paths in metadata, including slash-leading and backslash-leading strings, drive-qualified absolute paths, and `file://` values. Validated URI templates follow their own grammar.

Each complete canonical descriptor is independently checked through `SensitiveDataRedactorInterface` in `RedactionMode::Placeholder` with fixed scope `routing.artifact`, using the canonical sensitive-key classifier. Any changed redaction result or classification/redaction failure rejects the declaration. Redaction is rejection-only, not a mechanism for silently changing route semantics or declassifying data.

Artifact paths are derived only from authoritative runtime inputs. Unsafe roots, traversal components, stream wrappers, symlinked root components or targets, and existing non-regular targets are rejected. Artifact reading is bounded and non-executable; canonical structural limits also apply during decoding. Invalid UTF-8 fingerprint inputs are rejected without repair. Missing or invalid required artifacts fail closed without source fallback or request-time regeneration.

## Non-goals

This package does not provide:

- implicit module activation, dependency synchronization, or another bootstrap pipeline;
- HTTP middleware, dispatch, controller resolution, authorization, or action invocation;
- runtime group traversal, a separate routing descriptor schema, or a second matching mechanism;
- a second reservation registry, serialization algorithm, or ordinary atomic publication implementation;
- Kernel generation ownership, runtime route registration, request-time artifact reload, or automatic artifact repair;
- context storage, reset execution, or UnitOfWork orchestration;
- logging backends, observability exporters, or an independent redaction policy engine.

## References

- [Routing Artifact SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/routing-artifact.md)
- [Routing and HTTP Application Contracts SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/routing-and-http-app-contracts.md)
- [System Route Table SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/system-route-table.md)
- [Reserved Path Matching SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/reserved-path-matching.md)
- [Artifact Header and Schema Registry SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/artifacts.md)
- [Artifact Generations SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/artifact-generations.md)
- [Canonical Runtime Container Definitions SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/runtime-container-definitions.md)
- [Tag Registry SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/tags.md)
- [Modules and Manifests SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/modules-and-manifests.md)
- [Mode Presets SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/modes.md)
- [Config Roots SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/config-roots.md)
- [Runtime Drivers SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/runtime-drivers.md)
- [Context Keys SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/context-keys.md)
- [Reset Tags SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/reset-tags.md)
- [Observability SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability.md)
- [Logging Policy SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/logging-policy.md)
- [Sensitive Data Redaction SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/sensitive-data-redaction.md)
- [ADR-0018: Platform routing compiled routes](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0018-platform-routing-compiled-routes.md)
