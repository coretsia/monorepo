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

# ADR-0042: System route table and reserved-path matching

```yaml
adrVersion: 1
status: pre-accepted
owner: core/foundation
```

## Context

Coretsia needs deterministic ownership of system HTTP paths independently of endpoint implementation, package installation, module participation, runtime exposure, and authorization. Application route admission and system-endpoint dispatch need a common declaration source without acquiring each other's responsibilities.

Routing and HttpApp contracts define format-neutral routing descriptors and ports. Foundation owns shared deterministic runtime mechanisms, including DI tag infrastructure, canonical system-route declarations, and concrete reserved-path matching. Endpoint implementation and application route compilation remain owned by their respective platform packages.

HTTP maintenance also has an exact liveness bypass that must not acquire additional bypass paths through reserved-path normalization. Concrete path matching, route-template overlap analysis, endpoint execution, and endpoint access decisions therefore require distinct ownership boundaries.

## Decision

### Decision 1: Foundation owns one canonical versioned declaration registry

`Coretsia\Foundation\Routing\SystemRouteTable` is the sole production system-route declaration registry. It exposes the typed `SCHEMA_VERSION` and `ALIVE_PATH` constants and the static `entries()` API over immutable declaration metadata.

Each declaration contains exactly `owner`, `pattern`, `status`, and `type`. The canonical list is deterministic, ordered by `pattern` and then `type` using byte-order comparison, and forbids duplicates and overlapping declarations. Declaration grammar, owner identity, and the complete versioned table are governed by `docs/ssot/system-route-table.md`.

All three statuses reserve ownership: `active` identifies a materialized endpoint capability, `reserved` protects a path without asserting implementation, and `dev_only` protects a development-only declaration without enabling it. A declaration may name an owner package that is not yet materialized. Only the declared owner may claim the endpoint, and actual exposure remains subject to its applicable implementation, module, configuration, and access policy.

The exact `/_alive`, `/error`, and `/ping` declarations belong to `platform/http` and identify active terminal system-endpoint capabilities. The exact `/_debug` and prefix `/_debug/` reservations retain `dev_only` status. Neither declaration status nor a matching result establishes runtime exposure or authorization.

### Decision 2: Keep declarations, matching, admission, and execution separate

The ownership boundaries are:

- `SystemRouteTable` owns immutable route declarations and canonical path identifiers.
- `Coretsia\Foundation\Routing\ReservedPathMatcher` owns concrete reserved-path normalization and matching against those declarations.
- Application route compilation guards must analyze route-template overlap against the canonical exact paths and prefix namespaces.
- System-endpoint runtime owners own endpoint selection and execution independently of reservation matching.
- `FallbackRouter` consumes the canonical matcher result for terminal system-endpoint dispatch. `FallbackResponses` owns terminal response construction, including reserved fallback responses and unmatched responses.
- The declared endpoint owner retains responsibility for access policy and exposure eligibility, including development-only restrictions.

A concrete-path match is reserved ownership metadata, not an endpoint dispatch decision, route-template admission proof, or authorization outcome.

Contracts routing descriptors remain transport-neutral under `docs/ssot/routing-and-http-app-contracts.md`. Foundation owns the shared reservation registry and stateless concrete-path matching without acquiring endpoint implementation, transport processing, or application route compilation responsibilities. HTTP and Routing consume these primitives independently and must preserve the canonical direct package dependency matrix without introducing dependencies on each other.

### Decision 3: Enforce registry consistency through a committed version baseline

The canonical registry schema version is `SystemRouteTable::SCHEMA_VERSION`, independently of SSoT document metadata versions. The PHP declarations, the uniquely named normative registry section in the System Route Table SSoT, and the latest committed snapshot in `tools/config/system-route-table.json` must agree exactly on version and declaration values.

The baseline contains complete canonical snapshots in contiguous version order starting at `1`.

A semantic change to a released registry declaration set requires incrementing the registry version by exactly one, updating the PHP declarations and normative table, and appending the corresponding full snapshot. Every released snapshot must remain unchanged. A matcher-only or documentation-formatting change does not justify a registry version increment.

The read-only `composer system-route-table:gate` command participates in the repository gates and CI rails. It inspects PHP declarations without executing registry source and validates the normative Markdown table, canonical JSON serialization, contiguous snapshot history, semantic version transitions, and latest-snapshot equality. Presentation-only table alignment does not alter declaration semantics. Mandatory liveness and development-only invariants apply regardless of registry version.

The gate enforces local baseline consistency; it cannot independently establish released historical immutability if the declarations and retained history are rewritten together. Repository review must compare all previously released snapshots against an independently trusted revision and reject historical replacement, removal, or reordering. Gate validation remains independent of Composer package versions, Git tags, runtime state, and package discovery.

Baseline history is repository tooling data, not production configuration or a generated Kernel artifact. Runtime consumers depend on the canonical PHP declarations only.

### Decision 4: Match concrete encoded URI paths through one canonical implementation

`ReservedPathMatcher::normalize(string $path): string` validates and normalizes a concrete encoded URI path without inspecting reserved declarations.

`ReservedPathMatcher::match(string $path): ?array` invokes `normalize()` exactly once, consumes `SystemRouteTable::entries()` directly, and returns a complete canonical entry or `null` for a valid path without a reservation match. It is independent of status-based endpoint activation.

Both operations accept a concrete encoded URI path component, not a complete URI, request-target parser input, or route template. Canonical declaration grammar is intentionally stricter than the concrete path input grammar.

Normalization must:

- accept the canonical ASCII URI path character set and valid `%HH` octets;
- normalize an empty path to `/`, ensure one leading slash, and collapse consecutive slash sequences without authority parsing;
- preserve trailing slashes, case, and the exact encoded octet representation;
- reject literal dot segments instead of resolving them;
- remain non-recursive and linear in input length with at most linear additional memory.

The matcher must not trim whitespace, lowercase, URL-decode, or reinterpret valid encoded data. It rejects literal whitespace, control characters, DEL, non-ASCII bytes, backslashes, query and fragment delimiters, malformed escapes, URI scheme prefixes, raw route-template delimiters, the standalone `*` request-target form, literal dot segments, and other invalid path characters.

Invalid input raises `InvalidArgumentException` with the fixed diagnostic `http-reserved-path-invalid`, without the rejected path or derived raw values. Invalid input is not a non-match.

An `exact` declaration matches by strict equality with the normalized path. A `prefix` declaration matches the complete slash-terminated prefix as a segment boundary. `/health` and `/health/` are distinct declarations, and `/healthz` does not match `/health/`. Valid encoded octets remain opaque: `/%5Falive` does not match the exact `/_alive` declaration, and `/health%2Fready` does not match the `/health/` prefix.

The normative path grammar, normalization, matching semantics, and consumer obligations are defined by `docs/ssot/reserved-path-matching.md`.

### Decision 5: Preserve path representation consistency at consumer boundaries

Any runtime consumer that integrates reserved-path matching with endpoint dispatch must use the same effective path representation for both operations. A non-matching encoded path must not authorize dispatch of its decoded equivalent. No case folding, slash reinterpretation, percent decoding, or other representation change may occur between the reservation decision and the corresponding dispatch decision.

`FallbackRouter` filters the exact case-sensitive method `GET` before URI access, passes the original URI path to `ReservedPathMatcher::match()` exactly once, and selects explicitly implemented behavior using the returned canonical declaration's owner, pattern, type, and status. It treats only the canonical matcher invalid-input exception as unmatched; URI access and endpoint execution failures propagate unchanged.

The terminal dispatcher delegates construction of a safe HTTP-owned `501` fallback to `FallbackResponses` for explicitly supported reserved health and metrics declarations when no earlier owner-controlled response has short-circuited the pipeline. This diagnostic does not claim, implement, or authorize the reserved owner's endpoint and does not establish package installation or backend availability. No independent registry, matcher, or owner-discovery mechanism is introduced.

Application routing consumes `normalize()` as the canonical concrete-path validation boundary. A concrete application path must equal its normalized representation byte-for-byte, and a non-root path must not end with a literal slash. Normalization must not introduce application-route aliases.

Application route compilation must separately analyze route-template overlap, including dynamic templates that could match an exact reservation or enter a reserved prefix namespace. Passing a route template or a sample concrete path to `ReservedPathMatcher` is not a sufficient admission check.

### Decision 6: Preserve the exact maintenance liveness bypass and HTTP pipeline

`MaintenanceMiddleware` compares the original PSR-7 URI `getPath()` value directly and case-sensitively with `SystemRouteTable::ALIVE_PATH`. Active maintenance or unavailable maintenance state returns the canonical maintenance response except for this exact `/_alive` bypass.

The maintenance decision must not call `ReservedPathMatcher`. Its general slash normalization would otherwise extend bypass eligibility to inputs such as `_alive`, `//_alive`, and `///_alive`. Trailing-slash variants, different case, encoded substitutes, and debug paths must not bypass maintenance. Query strings do not affect the URI path comparison.

The exact bypass delegates once to the downstream handler and preserves its response or original Throwable. It does not implement a liveness endpoint, synthesize a liveness response, establish a downstream status, or authorize access.

`FallbackRouterHandler` is the canonical PSR-15 terminal handler. It delegates to `FallbackRouter` and returns the dispatched response unchanged or obtains the safe empty-body HTTP `404` from `FallbackResponses` for unmatched requests. Earlier middleware responses retain precedence. Terminal system-endpoint behavior is governed by `docs/ssot/http-system-endpoints.md`; general application routing remains a separate responsibility.

HTTP middleware ordering, response decoration and finalization, finalized request observability, and Kernel UnitOfWork and reset ownership remain unchanged. Terminal endpoint-decision observability belongs to `FallbackRouter` and remains independent of the finalized request outcome.

### Decision 7: Keep the registry and matcher free of runtime dependencies

`SystemRouteTable` is dependency-free static metadata. `ReservedPathMatcher` depends only on the table and native PHP operations. Both are final, non-instantiable classes without mutable runtime state.

Neither requires a service binding, additional Composer dependency, new DI tag, configuration root, module discovery, filesystem access, runtime container, ContextStore, observability port, generated artifact, or `kernel.reset` registration. No request-derived values enter the registry, and the matcher does not emit logs, spans, metrics, or raw-input diagnostics.

The terminal dispatcher is a separate stateless runtime consumer with explicit dependencies on response construction, the canonical injected clock and meter, and the validated HTTP metrics toggle. It owns endpoint decisions and their bounded counter emission, without transferring runtime dependencies or observability responsibilities into the registry or matcher.

## Consequences

### Positive consequences

- one Foundation-owned declaration registry provides deterministic reserved ownership without coupling it to endpoint availability;
- HTTP and Routing consume the canonical reservation policy without depending on each other;
- concrete reserved-path matching has a single stateless implementation and preserves segment boundaries and encoded path identity;
- the repository gate checks synchronized declarations and local version history while repository review retains authority over released historical immutability;
- maintenance liveness bypass remains exact and independent of general reserved-path normalization;
- terminal system-endpoint dispatch consumes canonical declarations and matching results without conflating dispatch, application route admission, or authorization.

### Trade-offs

- semantic declaration changes require a coordinated PHP, SSoT, and baseline update;
- local consistency checks require a separate trusted-revision review to establish released historical immutability;
- concrete-path matching cannot substitute for template overlap analysis or endpoint access policy;
- encoded path aliases are intentionally not inferred, and consumers must preserve consistent path semantics through dispatch.

## Rejected alternatives

### Derive reservations from configuration, module discovery, or generated artifacts

Endpoint ownership would vary with installed packages, runtime selection, or the availability of generated state. A competing configuration root or discovery layer would duplicate the canonical declarations and make reservation checks environment-dependent.

### Treat version-baseline equality as proof of immutable history

A self-contained gate cannot establish a trusted prior history. Coordinated rewriting of declarations and snapshots may satisfy local consistency checks while violating historical review requirements.

### Reuse concrete matching as a routing compiler or endpoint dispatcher

A single concrete path cannot represent the complete match domain of a route template. Matching metadata also cannot establish endpoint implementation, availability, or authorization. Combining these concerns would obscure ownership and introduce unsupported routing runtime behavior.

### Decode paths during reservation matching or reuse normalization for maintenance bypass

Decoding creates implicit aliases and permits divergence from effective dispatch semantics. Reusing general normalization for maintenance would expand an exact liveness exception to additional URI path representations.

## Non-goals

- General application routing, route-template compiler or admission guard implementation, or endpoint access policy implementation. The terminal dispatcher and response services retain their separate system-endpoint responsibilities.
- Activation of liveness, health, metrics, debug, or other reserved endpoints solely through declarations or matching.
- A competing reservation registry, configuration root, generated artifact, runtime discovery layer, DI binding, or public matching port.
- A competing middleware topology, HTTP execution boundary, Kernel lifecycle, context owner, request-observability pipeline, or reset orchestration.

## Related SSoT

- `docs/ssot/system-route-table.md`
- `docs/ssot/reserved-path-matching.md`
- `docs/ssot/routing-and-http-app-contracts.md`
- `docs/ssot/http-middleware-catalog.md`
- `docs/ssot/http-middleware-wiring.md`
- `docs/ssot/http-system-endpoints.md`
- `docs/ssot/tags.md`
- `docs/ssot/di-tags-and-middleware-ordering.md`

## Related ADRs

- `docs/adr/ADR-0005-routing-httpapp-ports.md`
- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/adr/ADR-0041-platform-http-runtime-pipeline-wiring.md`
