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

# Routing Artifact (SSoT)

```yaml
ssotVersion: 1
status: pre-stable
owner: platform/routing
```

## Scope

This document is the Single Source of Truth for the `routes@1` artifact identity, target-local storage, exact envelope and descriptor schema, fingerprint input, route declaration admission, deterministic source compilation, publication, immutable runtime hydration, and owner-artifact readiness.

It also defines the source route grouping policy and the application route matching semantics shared by compilation and artifact consumption.

The global artifact envelope, deterministic serialization law, and artifact registry remain owned by [Artifact Header and Schema Registry](./artifacts.md). The format-neutral routing ports and descriptors remain owned by [Routing and HttpApp Contracts SSoT](./routing-and-http-app-contracts.md).

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Application routes MUST be compiled deterministically for an explicitly selected application target and consumed at runtime from one validated immutable route table. Source declaration convenience MUST NOT change artifact identity, route precedence, or reserved endpoint ownership.

## Authority Boundary (MUST)

- `platform/routing` owns route compilation, `routes@1` schema validation, artifact publication and reading, source grouping, and application route matching.
- `core/contracts` owns `RouteDefinition`, `RouteMatch`, `RouteProviderInterface`, `RouterInterface`, and `RoutingArtifactReadinessInterface`.
- `core/foundation` owns the system-route registry, concrete reserved-path matching, canonical json-like normalization, stable JSON and PHP-array codecs, and shared filesystem publication primitives.
- `core/kernel` owns target-local source-host composition, `ModulePlan`, `RuntimePathContext`, `RuntimeDiagnosticsInput`, and immutable Kernel artifact generations.
- `platform/http` owns HTTP entrypoint validation and consumes the contracts-level readiness port when Routing is selected. HTTP and Routing MUST NOT depend directly on each other's implementation or require each other's module.
- `platform/redaction` supplies the canonical sensitive-key classifier and redaction bindings. Routing consumes the contracts-level ports, not concrete Redaction implementation classes.

## Invariants (MUST)

- The authoritative target is `ModulePlan::app()`; the authoritative environment is `RuntimeDiagnosticsInput::appEnv()`.
- Provider-emitted order defines matching precedence. No route-name or specificity sorting is allowed.
- Complete flattened route declarations MUST pass the same schema and safety validation during compilation and artifact hydration.
- All system-route reservations apply independently of method, environment, module selection, or endpoint exposure.
- Artifact bytes MUST be canonical, deterministic, and decoded without execution.
- Runtime matching MUST NOT read artifacts, resolve providers, interpret route groups, or resolve or invoke handlers.

## `routes@1` Identity (MUST)

The canonical artifact identity is `routes@1`.

Its owner is `platform/routing`, its generator is `platform/routing/artifacts`, and its canonical PHP basename is `routes.php`. The artifact registry is owned by [Artifact Header and Schema Registry](./artifacts.md).

This artifact is not a member of the Kernel immutable generation publication set. Its fingerprint is not a Kernel generation id.

## Artifact Location (MUST)

The only authoritative artifact and owner-local publication lock paths are:

```text
<RuntimePathContext::artifactRoot()>/routes.php
<RuntimePathContext::artifactRoot()>/routes.lock
```

The default application-relative layout is:

```text
var/cache/<appTarget>/routes.php
var/cache/<appTarget>/routes.lock
```

Supported artifact targets are exactly `web` and `api`. The effective artifact root is derived by Kernel from the selected target's bootstrap configuration; the default layout does not override it.

- Readers and writers MUST use the same `RoutesArtifactPath` policy. Routing MUST NOT derive the root from CWD, process environment, directory scans, or the Kernel `current` pointer.
- The authoritative root MUST be an absolute, platform-appropriate filesystem path. Stream wrappers, unsafe filesystem roots, literal traversal components, invalid path components, and symbolic links in existing root components are forbidden.
- Existing root components MUST be directories. Existing artifact and lock targets MUST be regular non-symlink files; absent publication targets are allowed. The reader requires an existing readable artifact.
- Both targets and existing root components MUST be checked before filesystem operations and revalidated at the publication boundary.
- `routes.lock` is a coordination file, not an artifact. Absolute resolved paths MUST NOT enter artifact payloads, fingerprints, or public diagnostics.

## Artifact Envelope (MUST)

The exact logical envelope is shown below in PHP array notation. This describes the logical schema, not the serialized byte spelling:

```php
[
    '_meta' => [
        'fingerprint' => '<routes-fingerprint>',
        'generator' => 'platform/routing/artifacts',
        'name' => 'routes',
        'schemaVersion' => 1,
    ],
    'payload' => [
        'app' => 'web',
        'routes' => [],
    ],
]
```

The fingerprint placeholder denotes the computed digest, not an admissible literal. This example represents the shape of an empty Web route table.

The envelope contains exactly `_meta` and `payload` in that order. No alternative envelope or payload-only representation is allowed.

## Header Fields (MUST)

The exact header key order is:

```text
fingerprint
generator
name
schemaVersion
```

- `fingerprint` — lowercase 64-character SHA-256 digest string, calculated as specified below.
- `generator` — exact string `platform/routing/artifacts`.
- `name` — exact string `routes`.
- `schemaVersion` — integer `1`.

The header MUST NOT contain `requires`, timestamps, an environment field, a generation id, or additional members. Values MUST be validated without type coercion.

## Artifact Payload (MUST)

The payload contains exactly these members in canonical order:

```text
app
routes
```

- `app` — exact string `web` or `api`, equal to the authoritative `ModulePlan::app()`.
- `routes` — ordered list of complete canonical `RouteDefinition::toArray()` representations, including `[]` for no routes.

### Route entry schema

Each entry contains exactly these keys in canonical order:

```text
defaults
handler
metadata
methods
name
pathTemplate
requirements
schemaVersion
```

- `defaults` — exactly `[]`.
- `handler` — stable logical handler identifier, not an executable value or filesystem path.
- `metadata` — canonical safe json-like map; `[]` is allowed.
- `methods` — canonical non-empty list as specified by the method policy.
- `name` — stable logical route identifier, globally unique within the table.
- `pathTemplate` — complete validated template string, including inherited prefixes where applicable.
- `requirements` — canonical map of placeholder names to supported requirement strings; `[]` is allowed.
- `schemaVersion` — integer `1`, equal to `RouteDefinition::SCHEMA_VERSION`.

Route names and handler identifiers MUST be non-empty strings of at most 256 bytes matching `[A-Za-z_][A-Za-z0-9_.:-]*` in full.

Missing, additional, incorrectly typed, unsupported, and non-canonical members MUST be rejected. The complete descriptor MUST be validated before reconstruction; the reconstructed `RouteDefinition::toArray()` MUST equal the supplied canonical descriptor exactly. Hydration MUST NOT silently normalize method case, list order, duplicates, or map values into acceptance.

## Source Compilation Boundary (MUST)

`RoutesCompiler` consumes the selected target's source-host `ModulePlan`, runtime diagnostics, and `RouteProviderRegistry`. The selected plan MUST enable `platform.routing`, and `artifactGenerationLoaded()` MUST be `false`.

The command-owning Console source host MUST select the target explicitly using `--app=web` or `--app=api`. The command resolver uses `KernelOpsHostBooter::bootForTarget()` with the authoritative application root. Target-local configuration and enabled providers come from the selected target, not from the Console plan. Artifact-runtime invocation is forbidden.

- `routes:compile` validates the complete source result and publishes the canonical envelope through `RoutesArtifactWriter`.
- `routes:validate` performs the same complete source compilation and validation, including serialized-byte limits, without reading, creating, modifying, or deleting route artifacts, artifact directories, or publication locks.
- Compilation and validation do not require an existing `routes.php`, an active Kernel generation, or HTTP module selection. They MUST NOT create an additional UnitOfWork.
- Compilation MUST return a complete publication-ready envelope or fail. Partial results MUST NOT be published.

## Route Provider Order (MUST)

Provider discovery is governed by the `routing.route_provider` contract in [Tag Registry](./tags.md).

- `RouteProviderRegistry` MUST enumerate only `TagRegistry::all(ReservedTags::ROUTING_ROUTE_PROVIDER)`. Tag metadata MUST be exactly `[]`.
- TagRegistry owns priority descending, service id ascending under byte-order `strcmp`, and repeated registration handling. Routing MUST NOT re-sort or re-deduplicate its output.
- Each service MUST be resolved lazily during explicit source compilation and implement `RouteProviderInterface`. Provider identity MUST be stable, at most 256 bytes, and match `[A-Za-z_][A-Za-z0-9_.:-]*` in full. Distinct services declaring the same identity MUST be rejected.
- `routes()` MUST return a flat ordered list containing only `RouteDefinition` instances. An empty list is valid. Duplicate route names within a provider and across the complete table MUST be rejected.
- Provider-emitted order, including the relative order of grouped and standalone routes, MUST be preserved. Filesystem, Composer, module-manifest, controller, and attribute scanning MUST NOT be used for route discovery.

## Route Template Grammar (MUST)

`RouteTemplate` owns the immutable parsed representation used for admission, reserved domain intersection, and runtime matching.

A complete template MUST be at most 256 bytes and consist of `/` or one or more slash-separated segments beginning with a literal `/`.

Each segment MUST be exactly one of:

- a static literal matching `[a-z_-][a-z0-9_-]*`;
- a complete named placeholder matching `\{[A-Za-z_][A-Za-z0-9_]*\}`.

Placeholder identifiers MUST be unique within the complete template. Empty paths, relative paths, empty segments, trailing slash aliases (except `/`), dot segments, embedded placeholders, wildcards, optional segments, and unsupported static literals MUST be rejected.

### Placeholder requirements

The requirements field is a map from declared placeholder identifiers to supported expression strings. Every key MUST identify an existing placeholder in the template to which the requirement is supplied.

Supported expressions are exactly:

```text
[0-9]+
[A-Za-z]+
[A-Za-z0-9_-]+
```

Requirements match the complete encoded segment using fixed matching semantics. An unconstrained placeholder matches one non-empty valid encoded URI path segment. Arbitrary provider-supplied regular expressions MUST NOT be executed.

### Methods and defaults

Supported canonical method values are exactly:

```text
GET
HEAD
POST
PUT
PATCH
DELETE
OPTIONS
TRACE
CONNECT
```

Serialized method lists MUST be non-empty, unique, and sorted in ascending byte order according to the canonical `RouteDefinition::toArray()` representation. Runtime method comparison is exact and case-sensitive. `OTHER` is an observability-only sentinel and MUST NOT be admitted as a declared or matching method.

Non-empty defaults are unsupported and MUST be rejected.

## Source Route Grouping (MUST)

`RouteGroup` is an immutable source declaration helper owned by `platform/routing`. It composes prefixes and inherited values into ordinary `RouteDefinition` instances. It is not a descriptor contract or a runtime route table.

### Root group and composition

The root group created by `RouteGroup::root()` has exactly these logical values:

```php
[
    'pathPrefix' => '/',
    'namePrefix' => '',
    'requirements' => [],
    'metadata' => [],
]
```

The source helper exposes:

```php
public static function root(): self;
public function group(string $pathPrefix, string $namePrefix, array $requirements, array $metadata): self;
public function route(RouteDefinition $route): RouteDefinition;
public function routes(array $routes): array;
```

- `group()` MUST return a new group without modifying its parent. Prefixes follow the canonical template grammar, including parameterized segments.
- Parent and child path prefixes join at exactly one literal slash. `/` is the identity prefix. A route declared with `/` resolves to the complete enclosing group prefix.
- A non-empty group name prefix MUST match `[A-Za-z_][A-Za-z0-9_-]*(?:\.[A-Za-z_][A-Za-z0-9_-]*)*` in full. Non-empty name components join with `.`; the empty string is an identity and MUST NOT introduce separators.
- The final route name and complete template MUST satisfy normal artifact admission limits and grammar.

### Requirement and metadata inheritance

- Each group's requirements apply only to placeholders declared in that group's supplied path prefix. A child route's requirements apply to its own supplied template.
- Parent and child requirements MUST be combined without overriding. Repeated placeholder identifiers across any ancestor, child group, or child route MUST be rejected, even when requirement expressions are identical or absent.
- Duplicate inherited requirement keys MUST be rejected.
- Metadata is a deterministic json-like map normalized through Foundation `JsonLikeNormalizer`. Parent, child group, and route metadata MUST combine without duplicate keys, including duplicates whose inherited value is `null`.
- Duplicate metadata keys MUST NOT be resolved by overwriting or recursive merging. The complete inherited metadata remains subject to canonical descriptor safety validation.
- The individual route retains its methods, handler, and defaults.

### Flattening and artifact equivalence

`route()` returns one complete `RouteDefinition`. `routes()` accepts only a list of `RouteDefinition` instances and returns the flattened list in the supplied order. Providers MUST flatten groups before returning through `RouteProviderInterface::routes()`.

For example, a parent prefix `/teams/{team}` with name prefix `teams` and requirement `team => [0-9]+`, followed by `/members/{member}` with name prefix `members` and requirement `member => [A-Za-z]+`, composes a GET route `/` named `show` with handler `Members::show` into:

```php
[
    'defaults' => [],
    'handler' => 'Members::show',
    'metadata' => [
        'area' => 'teams',
        'section' => 'members',
    ],
    'methods' => ['GET'],
    'name' => 'teams.members.show',
    'pathTemplate' => '/teams/{team}/members/{member}',
    'requirements' => [
        'member' => '[A-Za-z]+',
        'team' => '[0-9]+',
    ],
    'schemaVersion' => 1,
]
```

In this example, `area` is inherited from the parent and `section` from the child group. The equivalent standalone declaration has exactly the same canonical representation.

- All flattened routes MUST pass the complete template, duplicate-name, equivalent-domain, reserved-domain, metadata, and resource validation applied to standalone routes.
- Equivalent grouped and standalone declarations with the same ordered canonical descriptors and authoritative fingerprint inputs MUST produce identical fingerprints and artifact bytes.
- No group node, prefix descriptor, group metadata envelope, nested route collection, or group-specific field is permitted in `routes@1`. Runtime hydration MUST NOT reconstruct groups.

## Route Conflicts (MUST)

The complete route table MUST reject duplicate route names independently of methods or providers.

Matching-domain equivalence is determined by segment count, static literal bytes, and the supported placeholder requirement language at each position. Placeholder names are not part of the matching-domain identity.

Declarations with equivalent domains and intersecting method sets MUST be rejected. Equivalent domains with disjoint method sets are permitted if route names are distinct. Distinct overlapping domains are permitted and use first-match-wins in the canonical stored route order; static routes do not receive implicit precedence.

The same global conflict rules apply to flattened groups, standalone declarations, and decoded artifact entries. No declaration may silently overwrite another.

## Reserved Route Domain Validation (MUST)

`ReservedRoutesGuard` MUST consume every entry from `SystemRouteTable::entries()` and confirm reserved ownership through `ReservedPathMatcher::match()`. The statuses `active`, `reserved`, and `dev_only` are equally reserved. Routing MUST NOT maintain a local reserved registry or allow configuration to disable the guard.

For each complete parsed template and canonical reserved declaration:

1. For an exact declaration, require the same segment count. For a slash-terminated prefix declaration, require more template segments than reserved prefix segments.
2. Compare each reserved segment with the corresponding template segment using the shared `RouteTemplate::segmentMatches()` semantics. A non-matching segment establishes disjointness for that declaration.
3. Construct a concrete witness using the matching reserved segments and deterministic representatives for remaining template segments. A static segment uses its literal value, `[0-9]+` uses `0`, and `[A-Za-z]+`, `[A-Za-z0-9_-]+`, and unconstrained placeholders use `a`.
4. Reject the template when the canonical concrete matcher recognizes the witness as reserved.

This is a domain intersection check, not arbitrary placeholder sampling or a literal comparison between a template string and a concrete reserved path. A broad template that can match a reserved path is invalid even if its literal template is not reserved. A constrained template is allowed with respect to a reservation only when their matching domains are disjoint.

Admission MUST run before publication and again during artifact hydration. Concrete runtime matching MUST independently reject reserved paths through the same Foundation matcher.

## Fingerprint Input (MUST)

The exact logical fingerprint input is:

```php
[
    'appEnv' => $appEnv,
    'generator' => 'platform/routing/artifacts',
    'name' => 'routes',
    'payload' => $payload,
    'schemaVersion' => 1,
    'systemRouteTable' => [
        'entries' => SystemRouteTable::entries(),
        'schemaVersion' => SystemRouteTable::SCHEMA_VERSION,
    ],
]
```

`$appEnv` is the authoritative `RuntimeDiagnosticsInput::appEnv()` value. It MUST be non-empty, have no leading or trailing whitespace, and satisfy the schema's single-line control-byte restrictions. `$payload` is the complete canonical payload, including the explicit target and ordered route descriptors.

The fingerprint is calculated as:

```php
hash('sha256', StableJsonEncoder::encodeStableMap($input))
```

- The SHA-256 input is the exact stable JSON byte sequence, including its final LF. The digest is lowercase hexadecimal.
- All string keys and values in the complete fingerprint input MUST be valid UTF-8. Stable JSON encoding failures MUST reject the artifact through `RoutesArtifactInvalidException` with reason `routes-artifact-fingerprint-invalid`. Compilation, validation, publication, and hydration MUST NOT substitute, discard, or silently repair invalid string bytes.
- The fingerprint MUST be recomputed during artifact hydration using the decoded payload, authoritative environment, and canonical system-route table entries and version.
- Changes to the payload, route order, target, environment, or system-route declarations are fingerprint-relevant. Group structure and provider identity are not independent fingerprint fields.
- The authoritative environment identifier is the only environment input. Raw environment values, filesystem paths, timestamps, and Kernel generation fingerprints MUST NOT enter the fingerprint input. The environment identifier is not serialized as a header or payload field.
- Fingerprint verification does not assert freshness against current source providers and is not a signature or authentication mechanism.

## Deterministic Serialization Law (MUST)

`RoutesArtifactSchema` owns complete envelope validation and canonical byte serialization. It MUST reuse Foundation `JsonLikeNormalizer` and `StablePhpArrayDumper`. `RoutesArtifactReader` MUST use the matching Foundation `StablePhpArrayParser`. Routing MUST NOT implement another normalization or codec algorithm.

- Map keys MUST be sorted recursively by byte-order `strcmp`. Lists MUST preserve their canonical order; list-vs-map classification uses `array_is_list()`.
- Empty arrays serialize as `[]` and are interpreted by schema context. The value model is limited to null, bool, int, string, lists, and string-keyed maps. Floats, objects, closures, resources, and non-string map keys are forbidden.
- The canonical PHP-array encoding is the output of `StablePhpArrayDumper::dumpNormalized()` for the validated envelope: `<?php`, a blank line, `return ` followed by the array expression, and `;` followed by exactly one final LF. Non-empty arrays use four-space indentation and trailing entry commas. Strings use canonical double-quoted literals with deterministic escaping, including escaped control bytes. Raw CR bytes and additional final newlines are forbidden.
- Serialization is locale-independent and MUST NOT embed timestamps, absolute filesystem paths, secrets, raw environment values, or private request data.
- Identical canonical inputs MUST produce byte-identical artifacts. No extra normalization may change the canonical declared order or descriptor semantics.

## Artifact Resource Limits (MUST)

`RoutesArtifactSchema` owns these inclusive limits:

- serialized artifact size: `16777216` bytes;
- flattened route count: `4096`;
- normalized complete envelope depth: `32`;
- normalized complete envelope node count: `100000`;
- individual string size: `65536` bytes;
- aggregate complete envelope string size: `8388608` bytes.

Structural accounting MUST use Foundation `JsonLikeNormalizationLimits` and `JsonLikeNormalizer` semantics: container depth includes the root, nodes count map values and list items, and individual and aggregate string byte budgets include string map keys and string values. The complete envelope, including header and payload, is subject to the limits. Each descriptor is independently validated, including canonical Redaction resource limits.

The same limits MUST apply during source compilation, read-only validation, publication validation, and artifact decoding and hydration. Oversized input MUST fail before publication or runtime table construction. Bounded decoding MUST enforce structural budgets while parsing, not after unrestricted allocation. No independent Routing normalization implementation is permitted.

## Safety and Redaction (MUST)

Providers MUST emit deterministic safe application declarations only. Request-derived data, credentials, raw environment-derived values, and private customer data MUST NOT be used as route definitions or metadata.

- Recursively classify application-controlled metadata map keys and requirement keys through `SensitiveKeyClassifierInterface`. A sensitive key is rejected independently of its associated value.
- Reject absolute filesystem paths in metadata string values, including slash-leading and backslash-leading values, drive-qualified absolute paths, and `file://` values, irrespective of redaction classification. Apply this check recursively to nested maps and lists. The validated `pathTemplate` field follows URI template grammar instead.
- Independently validate every complete canonical descriptor using `SensitiveDataRedactorInterface` with `RedactionMode::Placeholder` and fixed scope `routing.artifact`.
- Reject any redaction result differing from the canonical normalized descriptor, and fail closed on classification or redaction failures. Redaction is a rejection-only admission check; it MUST NOT publish a changed replacement declaration.
- A redaction non-match does not establish semantic safety or declassify data. Source providers retain responsibility for the provenance and intended disclosure of their declarations.

## Publication Protocol (MUST)

`RoutesArtifactWriter` MUST complete envelope validation and canonical serialization before initiating filesystem publication.

1. Resolve the owner-local `routes.lock` path and acquire an exclusive Foundation `ScopedFileLock` operation.
2. Revalidate existing path components and both publication targets after lock acquisition.
3. Compare the existing artifact with the replacement bytes under the same lock. A differing file size does not require reading existing bytes. For an equal-size file, verify the opened regular file and its identity against the filesystem entry and read at most replacement length plus one byte. Identical bytes MUST leave the existing file unchanged.
4. For a replacement, use Foundation `AtomicFileWriter` to write complete bytes to a temporary file in the target directory and atomically replace `routes.php` only after a successful complete write. Partial writes MUST fail and unsuccessful temporary files MUST be cleaned.
5. Release the lock. Successful atomic replacement is the publication commit point.

A failed compilation or pre-commit publication failure MUST preserve the previous valid artifact. The writer MUST NOT delete the existing target before replacement or perform rollback after a successful commit. A post-commit lock cleanup failure MUST NOT invalidate a successful publication. A lock cleanup failure after an identical-byte comparison is not a successful publication commit.

An oversized existing artifact MUST NOT be consumed into memory solely for equality comparison. The lock is owner-local and does not coordinate or publish Kernel generations.

## Artifact Reading and Hydration (MUST)

`RoutesArtifactReader` MUST consume a single bounded byte snapshot of a regular non-symlink `routes.php`.

- Validate the target and existing path components before opening the artifact. Open one read handle; validate its regular-file type and device/inode identity against the current filesystem entry before reading.
- Reject an initial size above the artifact byte limit. Read at most `MAX_ARTIFACT_BYTES + 1` bytes and reject a read result above the limit or inconsistent with the opened file's initial size.
- Close the handle and decode the captured bytes through `StablePhpArrayParser::parseBounded()` with the complete envelope limits. MUST NOT reopen the path during validation of the same snapshot.
- Accept only the canonical PHP-array byte grammar emitted by the shared dumper. MUST NOT execute, evaluate, include, or require the artifact source. Non-canonical encodings, executable statements, malformed grammar, and duplicate map keys MUST be rejected.
- Validate the exact envelope, header, payload, authoritative target and fingerprint; reconstruct and validate every canonical descriptor, including global conflicts, reserved ownership, and safety policy.
- Missing, unreadable, oversized, malformed, incompatible, or unsafe artifacts MUST fail closed. Reading MUST NOT resolve providers, compile, regenerate, rewrite, repair, or remove artifacts.

Expected artifact read, decoding, and hydration failures cross the `RoutesArtifactInvalidException` boundary with error code `CORETSIA_ROUTES_ARTIFACT_INVALID` and fixed reason tokens. Public diagnostics MUST NOT disclose rejected bytes, paths, descriptors, parser offsets, or raw underlying exception messages.

## Immutable Runtime Route Table (MUST)

`CompiledRouteTable` is a shared immutable runtime service. Its construction consumes the validated ordered route definitions from `RoutesArtifactReader` and prepares their canonical `RouteTemplate` representations. No provider or group object enters the table.

- Canonical declarative service definitions MUST remain lazy and constructible without `routes.php`. Source and compiled containers use the same definition graph.
- `CompiledRouter` and `RoutingArtifactReadiness` MUST consume the same shared table instance. Compiled definitions MUST NOT embed resolved artifact paths or route payloads.
- Once hydrated, the table remains independent of subsequent filesystem replacement. An independently booted runtime may load the replacement artifact; an existing table MUST NOT reload or mutate its snapshot.
- The table retains no request-local mutable state and MUST NOT be registered for reset or `kernel.stateful` lifecycle participation.

### Concrete matching

`CompiledRouter` consumes the canonical `RouterInterface::match()` inputs. It accepts a concrete encoded URI path only when `ReservedPathMatcher::normalize($path)` succeeds and equals the supplied path byte-for-byte. Non-root paths ending with a literal slash are invalid for application matching.

- Empty paths, relative paths, repeated slash aliases, literal dot segments, malformed percent triplets, and reserved concrete paths MUST produce a non-match. Percent-encoded octets MUST NOT be decoded or case-normalized.
- Methods are matched exactly and segments are matched in full. The first successful match in stored order returns a canonical `RouteMatch` with the route name, complete path template, logical handler, matched encoded string parameters, and safe metadata.
- Unsupported methods and unmatched paths return `null`. Host is not a matching input. No implicit GET-to-HEAD fallback, automatic OPTIONS handling, redirects, or 405 response generation belongs to this boundary.
- The router removes stale `ContextKeys::PATH_TEMPLATE` at the beginning of each matching operation and writes only the complete declared template on success, before completion observability. It consumes the canonical `ContextWriterInterface` and MUST NOT own context reset or another UnitOfWork.
- Raw path, host, parameters, and handler values MUST NOT enter routing observability signals. The template is permitted as a bounded declared attribute or context field, not as a metric label. The canonical observability and logging policies remain authoritative.

## Owner-Artifact Readiness and Deployment (MUST)

When `platform.routing` is enabled in the authoritative target `ModulePlan`, `HttpRuntimeEntrypointGuard` MUST resolve `RoutingArtifactReadinessInterface` and invoke `assertReady()` before HTTP runtime construction, after the existing HTTP module, runtime-driver, and PSR-17 factory checks.

The readiness implementation requires the shared validated `CompiledRouteTable`. Successful table construction establishes readiness; `assertReady()` MUST NOT read or parse the artifact again. Missing or invalid readiness bindings and artifact validation failures MUST prevent HTTP startup. When Routing is not selected, the HTTP entrypoint MUST NOT resolve this port or require `routes.php`.

Deployment tooling MUST prepare the target-local routes artifact from the intended source declarations, selected modules, environment, and reserved registry before starting a runtime that requires it. `routes:validate` is a read-only source validation operation and is not a substitute for publication. Runtime readiness verifies the artifact against authoritative runtime inputs, not against live source providers. Deployment MUST arrange a consistent artifact and Kernel runtime configuration and start a new runtime to consume a replacement route table.

## Kernel Generation Boundary (MUST)

The Kernel immutable generation publication set remains exactly `module-manifest.php`, `config.php`, and `container.php`, with `generation-manifest.php` as the generation manifest, as defined by [Artifact Generations](./artifact-generations.md).

- `routes.php` and `routes.lock` remain outside generation directories and the generation manifest. Neither is a Kernel generation member.
- Routing compilation, validation, publication, and runtime hydration MUST NOT change Kernel generation identity, member bytes, or the `current` pointer.
- Kernel artifact compilation and cache verification MUST NOT produce or verify `routes@1`. Kernel runtime boot hydrates its own generation; Routing readiness is an owner-artifact boundary separate from Kernel generation selection.
- Routing MUST NOT add runtime seeds or source-host-only service identifiers to the compiled runtime graph. The source-host boot API is invoked only during explicit source commands, never during runtime service construction or matching.

## Validation and Testing Obligations (MUST)

Verification MUST cover:

- exact artifact schema, canonical serialization, authoritative target and environment, fingerprint sensitivity, and byte-identical reruns;
- provider order, duplicate rejection, complete segment grammar, constrained and overlapping domains, reserved domain intersections for every declaration status, and encoded concrete-path semantics;
- nested immutable grouping, prefix composition, inherited requirements and metadata, duplicate inherited keys and placeholders, flattening, and equivalent standalone artifact identity;
- complete envelope resource budgets, bounded non-executing decoding, non-canonical descriptor rejection, and canonical rejection-only redaction;
- target-local source compilation and read-only validation, bounded existing-byte comparison, lock coordination, pre-commit preservation, post-commit cleanup failure isolation, and unsafe filesystem target rejection;
- single-handle snapshot reading, immutable table lifetime, source and compiled container parity, and actual artifact-only runtime matching without providers or group hydration;
- selected and unselected Routing readiness, missing and invalid readiness binding rejection, and unchanged Kernel generation bytes, member set, and current pointer.

## Non-goals / Clarifications (MUST)

- A second format-neutral route descriptor, group-specific artifact schema, reserved endpoint registry, or normalization and publication mechanism.
- HTTP middleware implementation, system-endpoint dispatch, action resolution or invocation, controller discovery, authorization, or group-level middleware execution.
- Runtime route registration, automatic route scanning, request-time artifact reload, or hot replacement of a loaded route table.
- Route inspection commands, public route metadata export, host-based matching, arbitrary regular expressions, wildcard or optional route segments.
- Kernel generation publication, deployment orchestration, or another error, observability, context storage, or lifecycle implementation.

## Implementation Mapping

```text
packages/platform/routing/src/Compile/RouteProviderRegistry.php
packages/platform/routing/src/Compile/RoutesCompiler.php
packages/platform/routing/src/Compile/RouteTemplate.php
packages/platform/routing/src/Compile/ReservedRoutesGuard.php
packages/platform/routing/src/Definition/RouteGroup.php
packages/platform/routing/src/Artifacts/RoutesArtifactSchema.php
packages/platform/routing/src/Artifacts/RoutesArtifactPath.php
packages/platform/routing/src/Artifacts/RoutesArtifactWriter.php
packages/platform/routing/src/Artifacts/RoutesArtifactReader.php
packages/platform/routing/src/Runtime/CompiledRouteTable.php
packages/platform/routing/src/Runtime/CompiledRouter.php
packages/platform/routing/src/Runtime/RoutingArtifactReadiness.php
packages/platform/routing/src/Console/RoutesCommandTargetResolver.php
packages/platform/routing/src/Console/RoutesCompileCommand.php
packages/platform/routing/src/Console/RoutesValidateCommand.php
packages/platform/routing/src/Provider/RoutingServiceProvider.php
packages/platform/routing/src/Provider/RoutingServiceFactory.php
packages/platform/http/src/Runtime/HttpRuntimeEntrypointGuard.php
packages/core/kernel/src/Ops/KernelOpsHostBooter.php
packages/core/foundation/src/Routing/SystemRouteTable.php
packages/core/foundation/src/Routing/ReservedPathMatcher.php
packages/core/foundation/src/Serialization/JsonLikeNormalizer.php
packages/core/foundation/src/Serialization/JsonLikeNormalizationLimits.php
packages/core/foundation/src/Serialization/StableJsonEncoder.php
packages/core/foundation/src/Serialization/StablePhpArrayDumper.php
packages/core/foundation/src/Serialization/StablePhpArrayParser.php
packages/core/foundation/src/Filesystem/ScopedFileLock.php
packages/core/foundation/src/Filesystem/AtomicFileWriter.php
```

These implementation points do not change this document's authority boundary.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Artifact Generations](./artifact-generations.md)
- [Kernel Artifacts and Fingerprint Behavior](./artifacts-and-fingerprint.md)
- [Routing and HttpApp Contracts SSoT](./routing-and-http-app-contracts.md)
- [System Route Table SSoT](./system-route-table.md)
- [Reserved Path Matching SSoT](./reserved-path-matching.md)
- [Tag Registry](./tags.md)
- [Runtime Container Definitions SSoT](./runtime-container-definitions.md)
- [Compiled Container Payload and Artifact-Only Boot Semantics](./compiled-container.md)
- [Runtime Drivers SSoT](./runtime-drivers.md)
- [JSON-like Runtime Values SSoT](./json-like-runtime-values.md)
- [Sensitive Data Redaction SSoT](./sensitive-data-redaction.md)
- [Context Keys SSoT](./context-keys.md)
- [Observability SSoT](./observability.md)
- [Logging Policy SSoT](./logging-policy.md)
- [Reset Tags SSoT](./reset-tags.md)
- [Architecture Dependencies](../architecture/DEPENDENCIES.md)
- [ADR-0042: System route table and reserved-path matching](../adr/ADR-0042-system-route-table-and-reserved-path-matching.md)
