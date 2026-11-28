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

# ADR-0018: Platform routing compiled routes

```yaml
adrVersion: 1
status: pre-accepted
owner: platform/routing
```

## Context

Coretsia needs deterministic application route compilation and artifact-only runtime matching for the selected application target. Route declarations may be contributed by multiple enabled providers, but matching precedence must be explicit and reproducible, and application routes must not claim any system-reserved path or namespace.

Routing contracts define transport-neutral descriptors and ports. Foundation owns shared deterministic serialization, filesystem primitives, DI tag ordering, and reserved-path matching. Kernel owns application target selection, source-host composition, runtime input metadata, and immutable Kernel artifact generations.

Application route compilation, owner-artifact publication, and runtime route matching require a distinct platform ownership boundary. Source declaration convenience, including nested route groups, must not introduce an additional runtime representation or change the identity of equivalent compiled routes.

The detailed normative artifact and matching policy is defined by `docs/ssot/routing-artifact.md`.

## Decision

### Decision 1: Platform Routing owns the target-local routes artifact

`platform/routing` owns source route aggregation, route-template admission, route compilation, the `routes@1` artifact schema, publication, reading, and runtime route matching.

The canonical artifact identity is `routes@1`, its generator is `platform/routing/artifacts`, and its basename is `routes.php`.

The authoritative artifact and owner-local lock paths are:

```text
<RuntimePathContext::artifactRoot()>/routes.php
<RuntimePathContext::artifactRoot()>/routes.lock
```

`RoutesArtifactPath` derives both paths from the authoritative Kernel runtime input. Routing must not derive its own root from the working directory, environment variables, filesystem discovery, or the Kernel generation pointer. Unsafe root components and non-regular or symlinked publication targets are rejected. The lock is a coordination file, not an artifact.

The `routes@1` artifact is independent of Kernel immutable generation identity. Neither `routes.php` nor `routes.lock` belongs to the Kernel generation manifest or generation directory. Routing does not change Kernel generation members, fingerprints, or the `current` pointer. Kernel compilation and cache verification do not produce or verify the Routing artifact.

The global artifact registry and envelope law remain governed by `docs/ssot/artifacts.md`; the Kernel generation boundary remains governed by `docs/ssot/artifact-generations.md`.

### Decision 2: Compile from an explicitly selected target-local source host

The canonical source operations are `routes:compile` and `routes:validate`. Both require an explicit `--app=web` or `--app=api` selection and execute from a Console source host, not an artifact-runtime host.

`RoutesCommandTargetResolver` uses the authoritative application root and `KernelOpsHostBooter::bootForTarget()` to obtain the selected target's source container. Kernel owns bootstrap, module resolution, effective configuration, and runtime input construction. Routing must not introduce another bootstrap pipeline, infer the target, or inherit the Console host's enabled route providers as the target's declarations.

The selected target's `ModulePlan::app()` and `RuntimeDiagnosticsInput::appEnv()` are authoritative. Compilation requires `platform.routing` to be enabled in that plan and `artifactGenerationLoaded()` to be false.

`RoutesCompiler` validates the complete ordered route table and produces a publication-ready envelope without filesystem publication. `routes:compile` publishes that result through `RoutesArtifactWriter`. `routes:validate` performs the same complete compilation and serialization validation without reading or modifying route artifacts or creating artifact directories or locks.

Both commands use the contracts-level CLI command and output ports and canonical `cli.command` tag. Neither requires a selected Kernel generation, HTTP module activation, handler resolution, or an additional UnitOfWork.

### Decision 3: Preserve canonical provider and route ordering

`RouteProviderRegistry` enumerates route providers exclusively through:

```php
TagRegistry::all(ReservedTags::ROUTING_ROUTE_PROVIDER)
```

The tag identifier is `routing.route_provider`, and its metadata is exactly `[]`. Foundation `TagRegistry` owns repeated registration handling and ordering by priority descending, then service id ascending under byte-order `strcmp`.

Provider services are resolved lazily during explicit source compilation. Every provider implements `RouteProviderInterface`, supplies a stable unique provider identity, and returns a flat ordered list of `RouteDefinition` instances. The order within each provider is preserved exactly. Routing must not re-sort or re-deduplicate the tag registry output or discover routes by scanning files, Composer metadata, module manifests, controllers, or PHP attributes.

Duplicate provider identities and duplicate route names are rejected. Declarations with equivalent template-matching domains and intersecting method sets are rejected. Domain equivalence depends on segment count, static segment literals, and placeholder requirement languages, not on placeholder names.

Distinct overlapping domains remain valid and are resolved by first match in the stored order. There is no implicit static-route precedence, route-name sorting, or specificity sorting. The complete stored route order is fingerprint-relevant.

### Decision 4: Groups are immutable source declaration helpers

`Coretsia\Platform\Routing\Definition\RouteGroup` composes nested path prefixes, name prefixes, requirements, and metadata into ordinary `RouteDefinition` instances. Groups belong to source declaration composition inside `platform/routing`, not to `core/contracts` or the runtime route table.

The root group has path prefix `/`, empty name prefix, and empty requirements and metadata. Nested path prefixes follow the canonical route-template grammar and join with exactly one literal slash. `/` is the identity prefix, including when used as a child route template. Non-empty name prefixes join with `.`; empty prefixes add no separator.

Group requirements apply to placeholders declared in that group's supplied prefix. Inherited requirements are combined without overriding, and placeholder names remain unique throughout the complete flattened template. Metadata is normalized using Foundation `JsonLikeNormalizer` and combined as a deterministic map. Duplicate inherited requirement or metadata keys are rejected, including metadata keys whose inherited value is `null`. Metadata is not recursively merged.

The individual route owns its methods, handler, and defaults. Group composition does not modify parent group instances or establish a mutable route registry. The helper returns flattened descriptors in the supplied order, and providers complete flattening before returning through `RouteProviderInterface::routes()`.

Consumer-owned list-valued metadata, including HttpApp's `http_app_middleware` selection, preserves its element order through grouping, compilation, serialization, hydration, and matching. Generic metadata safety and duplicate inherited-key rejection remain authoritative. Routing does not interpret consumer-specific middleware semantics, resolve services, or execute middleware. HttpApp owns those policies under `docs/ssot/http-app-dispatch.md`.

Every flattened declaration is subject to the same complete template, conflict, reserved-domain, metadata, and resource validation as a standalone declaration. Equivalent grouped and standalone declarations produce identical fingerprints and artifact bytes when their ordered canonical descriptors and authoritative fingerprint inputs are identical.

### Decision 5: Preserve contract descriptor and artifact schema ownership

`core/contracts` owns `RouteDefinition`, `RouteMatch`, `RouteProviderInterface`, `RouterInterface`, and the transport-neutral `RoutingArtifactReadinessInterface`. The canonical routing descriptor schema version is `1`. Neither grouping nor artifact consumption changes the descriptor shapes or introduces a group-specific routing port.

`RoutesArtifactSchema` owns the exact `routes@1` envelope, header, payload, admission rules, and fingerprint. The payload contains the authoritative application target and the ordered list of complete canonical `RouteDefinition::toArray()` representations. No group node, prefix descriptor, nested route collection, or group-specific metadata envelope is part of the schema.

The fingerprint binds the canonical payload, authoritative environment, artifact identity, and canonical `SystemRouteTable` entries and schema version using SHA-256 over Foundation stable JSON bytes. Group structure and provider identity are not independent fingerprint inputs. Absolute filesystem paths, timestamps, and Kernel generation ids are not fingerprint inputs. Stable JSON encoding failures are rejected rather than repairing or substituting invalid string bytes.

Compilation and hydration share complete schema validation, including canonical descriptor reconstruction, conflict detection, reserved-route admission, bounded resource accounting, and rejection-only sensitive-data checks. Routing consumes the canonical classifier and redactor through contracts-level ports; it does not publish a redacted replacement for an unsafe declaration.

Exact schema fields, ordering, byte encoding, fingerprint input, and resource limits are governed by `docs/ssot/routing-artifact.md`. Format-neutral descriptor and port rules remain governed by `docs/ssot/routing-and-http-app-contracts.md`.

### Decision 6: Protect reserved matching domains, not just literal paths

Foundation `SystemRouteTable` owns the canonical system-route declarations. Foundation `ReservedPathMatcher` owns concrete encoded URI path normalization and reserved-path matching. Routing owns application route-template admission and must consume every reservation, independently of method, environment, selected modules, endpoint exposure, or declaration status.

`RouteTemplate` owns the parsed application template representation and supported complete-segment matching semantics. Templates support `/`, canonical static segments, and complete named placeholders with a closed set of requirement languages. Arbitrary provider-supplied regular expressions and non-empty route defaults are not supported.

`ReservedRoutesGuard` determines whether the complete parsed template domain intersects a canonical reservation:

- An exact reservation requires equal segment counts.
- A slash-terminated prefix reservation requires at least one additional template segment after the reserved prefix.
- Each reserved segment must match the corresponding static literal or supported placeholder language using the canonical `RouteTemplate` segment semantics.
- When the segment domains intersect, deterministic representatives complete a concrete witness for any remaining template segments. `ReservedPathMatcher::match()` confirms reserved ownership of that witness.

An intersecting declaration is rejected before publication and again during artifact hydration. A constrained template is admissible with respect to a reservation only when the domains are disjoint. Group prefixes cannot bypass admission because the guard consumes the complete flattened template.

Routing must not pass raw template strings to the concrete matcher as proof of non-intersection or approximate the domain by sampling arbitrary placeholder values. There is no second reservation registry or configuration switch to disable admission.

### Decision 7: Reuse Foundation artifact primitives

Routing owns artifact semantics, not independent serialization or atomic filesystem mechanisms.

The shared Foundation primitives are:

- `JsonLikeNormalizer` and `JsonLikeNormalizationLimits` for deterministic value normalization and structural resource accounting;
- `StableJsonEncoder` for canonical fingerprint input bytes;
- `StablePhpArrayDumper` for canonical PHP-array byte emission;
- `StablePhpArrayParser` for canonical non-executing decoding, including bounded decoding with the same structural limits;
- `ScopedFileLock` and `AtomicFileWriter` for owner-local publication coordination and atomic file replacement.

The shared primitives do not own `routes@1` schema, fingerprint policy, artifact path selection, or diagnostics. Kernel consumes the shared codecs and ordinary atomic writer within its own artifact boundary and retains its distinct durable generation publication protocol. Foundation does not depend on Kernel or Routing.

`RoutesArtifactWriter` validates the complete envelope and serialized bytes before publication. It performs a bounded existing-byte comparison and atomic replacement under the same exclusive owner-local lock. Identical bytes leave the existing artifact unchanged. Failed publication before atomic replacement preserves the previous valid artifact; successful replacement is the commit point and is not rolled back because of a subsequent lock cleanup failure.

`RoutesArtifactReader` validates the filesystem target, captures one bounded regular-file byte snapshot, and decodes it through `StablePhpArrayParser::parseBounded()`. Artifact source must never be executed, evaluated, included, or required. Canonical encoding, exact schema, target, fingerprint, resource limits, and admission policy are checked before the route table is constructed. Missing, incompatible, unsafe, or invalid artifacts fail closed through the owner-artifact validation boundary.

### Decision 8: Runtime matching consumes one immutable validated route table

`RoutingServiceProvider` contributes one canonical declarative graph for source and compiled containers. Service definitions are lazy and do not embed resolved artifact paths or route payloads. The graph can be constructed without an existing `routes.php`.

`CompiledRouteTable` is a shared immutable service hydrated from `RoutesArtifactReader`. It retains the validated ordered descriptors and their parsed templates, not provider services or source groups. `CompiledRouter` implements `RouterInterface` and consumes this table.

`CompiledRouter` accepts only canonical encoded concrete paths whose Foundation normalization result equals the supplied path byte-for-byte. Invalid paths, non-root trailing slash paths, reserved concrete paths, unsupported methods, and unmatched inputs produce no match. Percent-encoded octets are not decoded or case-normalized. Methods are compared exactly, and the first successful match returns a canonical `RouteMatch` with declared name, complete template, logical handler, encoded string parameters, and safe metadata. The optional host does not participate in matching.

`RouterInterface::allowedMethods(string $path, ?string $host = null): array` consumes the same immutable `CompiledRouteTable` and parsed templates. It applies the same canonical concrete encoded-path admission and reserved-path exclusion as `match()`, returning `[]` for invalid, non-canonical, reserved, or unmatched paths. It collects declared methods from every matching template domain, including placeholder requirements, removes duplicates, and sorts the exact method strings ascending by byte-order `strcmp`. Host remains unused; no implicit HEAD or OPTIONS methods are added.

Method enumeration does not apply first-match-wins to the method set or change ordinary matching precedence, stored route order, descriptor shapes, or the `routes@1` schema and fingerprint. It does not invoke another observed match, read or mutate routing context, or emit additional routing spans, metrics, or logs.

Routing exposes matching information only. HttpApp owns ordinary application 404/405 exception classification, and Problem Details owns HTTP adaptation and validated Allow-header materialization. Routing does not generate HTTP 405 responses or construct an Allow header.

Runtime matching and allowed-method enumeration do not read artifact files, resolve route providers, compile declarations, reconstruct or traverse groups, resolve or execute middleware, resolve handlers, or invoke actions. Once hydrated, the table remains independent of subsequent artifact replacement. An independently booted runtime may consume a replacement snapshot.

Routing owns `ContextKeys::PATH_TEMPLATE` mutation through `ContextWriterInterface`: it removes a stale template at the beginning of matching and writes only the complete declared template after success, before completion observability. The router and table do not own context storage, context reset, or UnitOfWork lifecycle. Routing observability uses the canonical ports and policies; raw matching input and handler values do not become routing signals, and the path template is not a metric label.

### Decision 9: Owner-artifact readiness is conditional and separate from Kernel boot

`RoutingArtifactReadiness` implements `RoutingArtifactReadinessInterface` and consumes the same shared `CompiledRouteTable` as `CompiledRouter`. Successful table construction establishes readiness. `assertReady()` does not read or parse the artifact again.

When the authoritative target `ModulePlan` enables `platform.routing`, `HttpRuntimeEntrypointGuard` resolves the readiness port and invokes `assertReady()` after HTTP module, runtime-driver, and PSR-17 factory checks and before HTTP runtime construction. Missing or invalid readiness bindings and invalid artifacts prevent HTTP startup. When Routing is not selected, the HTTP entrypoint does not resolve the port or require the artifact.

HTTP and Routing remain independent platform packages without direct source, Composer, or module requirements on each other. Kernel does not depend on Routing. Routing uses the public Kernel source-host and runtime input APIs without acquiring ownership of Kernel boot or generation publication.

Deployment must publish the target-local route artifact from the intended declarations, environment, and selected modules before starting a runtime that requires it. Readiness establishes validity against authoritative runtime inputs, not freshness against live source providers. Publication and Kernel runtime configuration must be coordinated by deployment, and a new runtime is required to consume a replacement table.

## Consequences

### Positive consequences

- Route precedence, canonical bytes, and fingerprints are reproducible for identical authoritative inputs.
- Source groups reduce declaration repetition without introducing a second descriptor, artifact schema, or matching path.
- Reserved ownership is enforced against the complete template domain at compilation and hydration and against concrete paths during matching.
- Source compilation and runtime matching are separate, and runtime consumers share one validated immutable snapshot.
- Shared Foundation primitives avoid duplicate codecs and ordinary atomic publication mechanisms while preserving owner-specific policy.

### Trade-offs

- The supported route grammar and requirement languages are deliberately limited so admission and matching share deterministic semantics.
- Declaration authors must choose the matching precedence explicitly and resolve duplicate inherited keys and conflicting declarations.
- A valid artifact is not proof that source providers have not changed, and its fingerprint is not an authentication mechanism.

### Operational consequences

- Deployment runs explicit target-local compilation; successful source validation alone does not publish an artifact.
- A missing or invalid required owner artifact prevents startup rather than triggering runtime compilation or repair.
- Replacing `routes.php` does not change Kernel generation identity or reload a route table already hydrated by a runtime.

## Rejected alternatives

### Put Routing artifact production into Kernel or Contracts

Rejected. Contracts own format-neutral descriptors and ports, and Kernel owns its immutable generation publication set. Coupling either to application route compilation would violate the package ownership boundary.

### Derive route compilation inputs from the Console plan or filesystem discovery

Rejected. The selected application's effective configuration and enabled providers are authoritative. Implicit discovery or Console-target inheritance would make compilation dependent on unrelated host state.

### Implement Routing-specific serialization and atomic publication primitives

Rejected. Foundation already owns deterministic codecs, normalization, resource accounting, and ordinary atomic byte publication. Separate implementations would permit inconsistent artifact bytes and failure semantics.

### Validate only literal templates or active reserved endpoints

Rejected. Placeholder domains can intersect exact reservations and reserved prefix namespaces without literal equality. All declaration statuses reserve ownership independently of endpoint activation.

### Re-sort routes by name or specificity

Rejected. Foundation tag order and provider-emitted order define matching precedence. Additional sorting or silent duplicate replacement would alter declared behavior.

### Interpret route groups at runtime or serialize a separate group schema

Rejected. Grouping is source-only descriptor composition. Group-aware hydration, traversal, matching, and precedence would introduce a second runtime representation and allow equivalent declarations to produce different artifact identities.

### Resolve providers or rebuild artifacts on runtime cache misses

Rejected. Runtime matching consumes only the validated immutable route table. Missing or invalid required artifacts fail readiness; automatic regeneration would make runtime behavior dependent on source discovery and mutable filesystem state.

### Add Routing implementation dependencies to HTTP or Kernel

Rejected. The readiness port is the transport-neutral integration boundary. Direct implementation dependencies would couple independent runtime owners and compromise conditional module selection.

## Non-goals

- HTTP middleware implementation, system-endpoint execution, controller discovery, action argument resolution, or action invocation.
- Group-level middleware registration or execution, authorization, or controller resolution.
- Host-based matching, implicit GET-to-HEAD fallback, automatic OPTIONS handling, redirects, 405 response generation, arbitrary regular expressions, wildcard segments, or optional segments.
- Runtime route registration, artifact repair, request-time reload, or route inspection and public metadata export.
- A competing routing descriptor, group-specific artifact schema, reservation registry, matching mechanism, context store, observability backend, or reset orchestration.
- Kernel generation publication, deployment orchestration, or another source-host bootstrap pipeline.

## Related SSoT

- `docs/ssot/routing-artifact.md`
- `docs/ssot/routing-and-http-app-contracts.md`
- `docs/ssot/http-app-dispatch.md`
- `docs/ssot/problem-details.md`
- `docs/ssot/system-route-table.md`
- `docs/ssot/reserved-path-matching.md`
- `docs/ssot/artifacts.md`
- `docs/ssot/artifact-generations.md`
- `docs/ssot/json-like-runtime-values.md`
- `docs/ssot/tags.md`
- `docs/ssot/runtime-container-definitions.md`
- `docs/ssot/runtime-drivers.md`
- `docs/ssot/context-keys.md`
- `docs/ssot/observability.md`
- `docs/ssot/logging-policy.md`
- `docs/ssot/sensitive-data-redaction.md`
- `docs/ssot/reset-tags.md`

## Related ADRs

- `docs/adr/ADR-0005-routing-httpapp-ports.md`
- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/adr/ADR-0027-runtime-driver-resolution.md`
- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md`
- `docs/adr/ADR-0031-atomic-artifact-generations.md`
- `docs/adr/ADR-0041-platform-http-runtime-pipeline-wiring.md`
- `docs/adr/ADR-0042-system-route-table-and-reserved-path-matching.md`
