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

# System Route Table SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: platform/http
```

## Scope

This document is the Single Source of Truth for Coretsia system-route declarations, reserved path ownership, declaration status semantics, and registry versioning and enforcement policy.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

System routes MUST have one deterministic, versioned ownership registry independent of endpoint implementation, module selection, and runtime exposure.

## Authority and ownership (MUST)

The canonical code-level registry is:

```text
Coretsia\Platform\Http\System\SystemRouteTable
```

`platform/http` owns the declaration registry. Each entry declares exactly one semantic owner package, which owns the corresponding endpoint behavior and access policy.

`SystemRouteTable::entries()` returns the immutable canonical declaration list in registry order. Each entry contains exactly four string fields in the order `owner`, `pattern`, `status`, `type`.

Production code MUST consume this registry rather than introduce competing reserved-path lists, registries, configuration sources, runtime discovery, or generated artifacts for these declarations.

`SystemRouteTable` is dependency-free static metadata. It does not own route matching, dispatch, service registration, or endpoint activation.

## Declaration semantics (MUST)

- `exact` reserves a complete, case-sensitive URI path.
- `prefix` reserves a slash-terminated, case-sensitive namespace, including the prefix path itself and paths beneath that namespace.
- An exact declaration without a trailing slash and a prefix declaration with a trailing slash are distinct. For example, `/health` and `/health/` do not overlap.
- The exact declaration `/` intentionally reserves the root path for `platform/http`. It MUST NOT be interpreted as a wildcard or namespace reservation, and competing application ownership of the exact root path MUST be rejected by route admission guards.

Canonical declaration patterns MUST:

- begin with exactly one `/`;
- use non-empty lowercase ASCII segments composed only of letters `a-z`, digits `0-9`, `_`, and `-`;
- omit a trailing slash for `exact` declarations, except for the root path `/`;
- end with exactly one `/` for `prefix` declarations; a root prefix `/` is forbidden.

Canonical declarations MUST NOT contain wildcard or regular expressions, route placeholders, query strings, fragments, percent-encoded substitutions, control characters, backslashes, dot segments, or empty interior segments.

Duplicate patterns and overlapping declarations are forbidden regardless of owner or status. No exact path may fall within a declared prefix, and no prefix namespace may contain another declared prefix.

Entries MUST be stored in ascending byte order by `pattern`, then `type`, using `strcmp` semantics. The registry MUST NOT depend on runtime sorting or environment state.

Owners MUST use canonical layered `package_id` values according to [Architecture Structure](../architecture/STRUCTURE.md). A reservation MAY identify a package that is not yet materialized or installed.

## Declaration status (MUST)

All supported statuses represent reserved route ownership:

- `active` identifies a materialized system-endpoint capability. It does not imply unconditional runtime exposure or authorization.
- `reserved` protects a system-route declaration without asserting that its endpoint implementation exists.
- `dev_only` protects a development-only system-route declaration without activating its endpoint or authorizing access.

Status MUST NOT be interpreted as proof of package installation, module participation, endpoint registration, request eligibility, or authorization.

Only the declared owner MAY claim the endpoint. Claiming a reservation for runtime use MUST be backed by the owner implementation and its endpoint-specific policy. Transitioning a declaration to `active` MUST follow the registry change and versioning rules.

Actual endpoint exposure MUST require the applicable module participation, endpoint registration, configuration, and access policy of the owner. Non-owner packages MUST NOT claim a competing route, independently activate an endpoint, or bypass its access policy because a reservation exists.

The active exact `/`, `/_alive`, `/error`, and `/ping` declarations identify materialized terminal system endpoints owned by `platform/http`. HTTP-owned terminal `501` fallback responses for explicitly supported reserved health and metrics declarations MUST NOT be interpreted as owner endpoint implementation, activation, availability discovery, or transfer of reserved ownership. Their behavior is governed by [HTTP System Endpoints SSoT](./http-system-endpoints.md).

Development-only endpoints MUST remain subject to explicit development-mode eligibility and owner-controlled access policy. The `dev_only` reservations themselves do not enable development endpoints.

## System Route Registry (MUST)

Schema version: `1`

| pattern    | type     | status     | owner              |
|------------|----------|------------|--------------------|
| `/`        | `exact`  | `active`   | `platform/http`    |
| `/_alive`  | `exact`  | `active`   | `platform/http`    |
| `/_debug`  | `exact`  | `dev_only` | `platform/http`    |
| `/_debug/` | `prefix` | `dev_only` | `platform/http`    |
| `/error`   | `exact`  | `active`   | `platform/http`    |
| `/health`  | `exact`  | `reserved` | `platform/health`  |
| `/health/` | `prefix` | `reserved` | `platform/health`  |
| `/metrics` | `exact`  | `reserved` | `platform/metrics` |
| `/ping`    | `exact`  | `active`   | `platform/http`    |

## Matching and runtime boundaries (MUST)

Concrete reserved-path matching is owned by `Coretsia\Platform\Http\System\ReservedPathMatcher` according to [Reserved Path Matching SSoT](./reserved-path-matching.md).

The matcher consumes the canonical table and identifies reserved ownership for any supported declaration status. It does not implement route-template overlap analysis, route admission, endpoint dispatch, authorization, or response construction.

`FallbackRouter` MUST consume the matching canonical declaration to select explicitly supported terminal behavior according to its owner, pattern, type, and status. It MUST NOT introduce another declaration registry or reserved-path matcher. Endpoint dispatch, response construction, and applicable owner access policy remain independent of the declaration registry.

Compile-time route admission guards MUST analyze competing route templates against the canonical declarations. System-endpoint dispatch and access decisions remain separate responsibilities of the applicable runtime owners.

`SystemRouteTable::ALIVE_PATH` is the canonical exact liveness-path identifier and MUST retain the literal value `/_alive`. The exact liveness declaration MUST retain ownership by `platform/http`.

`MaintenanceMiddleware` compares the original URI path directly with `SystemRouteTable::ALIVE_PATH`. Its bypass for active maintenance or unavailable maintenance state delegates to the downstream handler; it does not implement the liveness endpoint, construct a liveness response, or determine endpoint availability.

The exact `/_debug` and prefix `/_debug/` declarations MUST retain `dev_only` status. A registry version change MUST NOT bypass mandatory liveness or development-only reservation invariants.

## Registry versioning (MUST)

The document metadata `ssotVersion` identifies the SSoT document version. It is independent of `SystemRouteTable::SCHEMA_VERSION`, which identifies the canonical system-route declaration set.

The version declared in the normative registry section MUST equal `SystemRouteTable::SCHEMA_VERSION` and the latest committed snapshot version in:

```text
tools/config/system-route-table.json
```

The committed baseline is a non-empty JSON list of snapshots. Each snapshot contains exactly `entries` and `schemaVersion`. Versions MUST be positive integers, contiguous from `1`, with one snapshot per version. Entries MUST preserve canonical field and list ordering. The baseline MUST use `Coretsia\Devtools\InternalToolkit\Json::encodeStable()` serialization semantics with exactly one final LF.

Any addition, removal, or change to a normative entry field in a released registry declaration set MUST:

1. Update the canonical PHP declarations and normative registry table together.
2. Increment `SCHEMA_VERSION` by exactly one and align the version declared in this document.
3. Append the corresponding complete snapshot to the committed baseline.
4. Preserve every previously released snapshot unchanged.

Version increments without a semantic declaration change are forbidden. Documentation-only edits and matcher algorithm changes MUST NOT increment the registry version unless the canonical declarations also change.

Released historical snapshot immutability is a mandatory repository review invariant. Review MUST compare the retained released history against an independently trusted repository revision and reject replacement, removal, or reordering of released snapshots.

The baseline is repository tooling data only. Production HTTP code MUST NOT read it or depend on repository tooling for route declarations.

## Enforcement rails

The repository gate is `tools/gates/system_route_table_gate.php`, registered as `composer system-route-table:gate` and included in the canonical `gates` and `ci` rails.

The gate verifies the selected repository's PHP registry declaration shape, canonical entries, normative Markdown section, and version baseline without executing registry source. It enforces semantic equality between the PHP registry, the normative table, and the latest baseline snapshot, together with local history consistency.

The machine-consumed registry section MUST occur exactly once and contain only the schema version line, the canonical table, and optional blank lines outside the table. Explanatory prose and examples MUST remain outside that section.

Policy drift is reported with `CORETSIA_SYSTEM_ROUTE_TABLE_DRIFT`. Repository selection, file inspection, syntax parsing, and argument failures are reported with `CORETSIA_SYSTEM_ROUTE_TABLE_GATE_FAILED`. Successful validation emits no output and does not rewrite files.

Local baseline validation does not independently prove released historical immutability. Simultaneous rewriting or truncation of the declarations and baseline history may be internally consistent; comparison with an independently trusted revision remains a repository review obligation.

The gate MUST validate local registry and baseline consistency independently of Composer package versions, Git tags, runtime state, or package discovery.

## Non-goals

- A second production route registry, routing port, configuration root, runtime discovery layer, or Kernel artifact.
- Endpoint implementation, module activation, route-template compilation, route admission guard implementation, system-endpoint dispatch, or endpoint authorization.
- Runtime state, ContextStore participation, observability, or reset registration for declaration metadata.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Reserved Path Matching SSoT](./reserved-path-matching.md)
- [Routing and HttpApp Contracts SSoT](./routing-and-http-app-contracts.md)
- [HTTP Middleware Catalog SSoT](./http-middleware-catalog.md)
- [HTTP System Endpoints SSoT](./http-system-endpoints.md)
- [Modules and Manifests SSoT](./modules-and-manifests.md)
- [Architecture Structure](../architecture/STRUCTURE.md)
- [ADR-0042: System route table and reserved-path matching](../adr/ADR-0042-system-route-table-and-reserved-path-matching.md)
