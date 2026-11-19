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

# Reserved Path Matching SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: platform/http
```

## Scope

This document is the Single Source of Truth for Coretsia concrete reserved-path matching, encoded URI path normalization, segment-prefix boundaries, invalid-input handling, and reserved-path consumer obligations.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Consumers need one deterministic concrete-path matching implementation that identifies canonical system-route ownership without resolving endpoint availability or access rights.

## Authority and ownership (MUST)

The canonical implementation is:

```text
Coretsia\Platform\Http\System\ReservedPathMatcher
```

It consumes `Coretsia\Platform\Http\System\SystemRouteTable::entries()` as the only declaration source. Declaration ownership, status, path grammar, ordering, and versioning are governed by [System Route Table SSoT](./system-route-table.md).

Consumers MUST NOT introduce competing concrete reserved-path matching implementations or duplicate the canonical declaration list.

## Matcher contract (MUST)

The canonical static API is:

```php
public static function match(string $path): ?array
```

The return shape is:

```text
array{owner: string, pattern: string, status: 'active'|'reserved'|'dev_only', type: 'exact'|'prefix'}|null
```

The input is an encoded concrete URI path component. It MAY be empty or omit the leading slash. It MUST NOT be a complete URI, request-target form, query string, fragment, or route template.

The matcher normalizes the supplied path exactly once and returns the complete matching canonical entry in its original field order, or `null` for a valid path without a matching declaration. Invalid input MUST follow the exception policy defined below, not return `null`.

Canonical registry patterns are stricter than matcher inputs. Input normalization MUST NOT be used to admit noncanonical registry declarations.

## Concrete path grammar (MUST)

The matcher accepts only the following literal ASCII characters and valid percent-encoded octets:

- unreserved characters: `A-Z`, `a-z`, `0-9`, `-`, `.`, `_`, `~`;
- URI sub-delimiters: `!`, `$`, `&`, `'`, `(`, `)`, `*`, `+`, `,`, `;`, `=`;
- the path characters `:`, `@`, and `/`;
- `%HH` octets, where each `H` is an ASCII hexadecimal digit.

A URI scheme prefix at the beginning of the supplied input and the standalone asterisk request-target form `*` MUST be rejected. Literal `*` within an otherwise valid path is path data, not a wildcard.

Repeated leading slashes MUST be interpreted as path data, not URI authority syntax. The matcher MUST NOT parse a complete URI or interpret a host, port, scheme, query, or fragment.

## Normalization semantics (MUST)

The matcher MUST:

- normalize the empty path to `/`;
- ensure exactly one leading slash;
- collapse every consecutive slash sequence into one `/`, including interior slash sequences;
- preserve a trailing slash after slash normalization;
- preserve path case and the original encoded representation of every valid percent-encoded octet;
- reject literal `.` or `..` path segments, including segments exposed by slash normalization.

The matcher MUST NOT trim whitespace, lowercase paths, URL-decode or re-encode percent escapes, resolve dot segments, or interpret encoded octets as path delimiters.

Percent-encoded slash, underscore, dot, non-ASCII, and other octets remain opaque path data. Their decoded meaning MUST NOT create an implicit alias for a canonical declaration.

Normalization MUST remain non-recursive, with O(n) time and at most O(n) additional memory for an input of length n.

## Matching semantics (MUST)

- `exact` declarations match only by strict equality between the normalized path and the complete canonical pattern.
- `prefix` declarations match only when the normalized path starts with the complete slash-terminated canonical pattern. The slash is the segment-prefix boundary.
- The exact `/` declaration matches only the normalized root path.
- No trailing-slash alias is implied by a non-root exact declaration.
- Matching is case-sensitive and independent of declaration status.
- The matcher MUST preserve deterministic results without runtime sorting or mutable caches.

## Invalid-input policy (MUST)

The matcher MUST reject:

- literal ASCII whitespace, control characters, DEL, and non-ASCII bytes;
- backslashes, query and fragment delimiters, and malformed percent escapes;
- URI scheme prefixes, the standalone asterisk request-target form, and raw route-template delimiters, including `/{slug}` and `/health/{probe}`;
- literal `.` or `..` path segments;
- any other character outside the canonical encoded URI path grammar.

Invalid input MUST raise `InvalidArgumentException` with the fixed diagnostic:

```text
http-reserved-path-invalid
```

The diagnostic MUST NOT include the rejected path or any derived raw input value.

## Concrete matching examples

Matching examples (input to returned declaration pattern):

- `/`, the empty string, and `///` match `/` (`exact`).
- `/_alive`, `_alive`, `//_alive`, and `///_alive` match `/_alive` (`exact`).
- `/_debug` matches `/_debug` (`exact`).
- `/_debug/` and `/_debug//toolbar` match `/_debug/` (`prefix`).
- `/health` matches `/health` (`exact`).
- `/health/`, `/health/ready`, and `//health//ready` match `/health/` (`prefix`).
- `/ping` matches `/ping` (`exact`).
- `/health/%2E%2E`, `/health/%2Fready`, and `/health/%C3%A9` match `/health/` (`prefix`) without interpreting the encoded octets.

Valid paths without a matching declaration return `null`:

- `/healthz`, `/healthz/ready`, and `/_debugger`;
- `/_alive/`, `/ping/`, and `/metrics/`;
- `/_ALIVE` and `/Health/ready`;
- `/%5Falive` and `/health%2Fready`;
- `/users/123`.

Invalid inputs such as `/health/../ready`, `/./health`, `/health/%GG`, `/_alive?probe=1`, `/_alive#probe`, `https://example.org/_alive`, `/{slug}`, and `*` raise the fixed invalid-input exception.

## Runtime consumer obligations (MUST)

A matching result identifies reserved ownership only. It MUST NOT activate an endpoint, authorize access, or bypass the applicable endpoint-owner policy.

A non-matching encoded path MUST NOT be treated as authorization to dispatch a decoded equivalent. Runtime consumers MUST match and dispatch the same effective path representation, including the same case, slash, trailing-slash, and percent-encoding semantics. They MUST NOT introduce path reinterpretation between reservation matching and endpoint dispatch.

Compile-time application route admission guards MUST separately analyze route-template overlap against exact paths and prefix namespaces. A concrete-path match or non-match is not sufficient to validate a route template for admission.

Route descriptors and invocation ports remain governed by [Routing and HttpApp Contracts SSoT](./routing-and-http-app-contracts.md).

## Maintenance liveness bypass (MUST)

Maintenance liveness bypass is intentionally independent of concrete reserved-path normalization.

`MaintenanceMiddleware` MUST compare the original URI `getPath()` value directly and case-sensitively against `SystemRouteTable::ALIVE_PATH`. It MUST NOT invoke `ReservedPathMatcher` or normalize the path to determine bypass eligibility.

Under active maintenance or unavailable maintenance state, only the exact URI path `/_alive` bypasses the maintenance short-circuit and delegates to the downstream handler. A query string does not change the URI path comparison. `//_alive`, `///_alive`, `_alive`, `/_alive/`, `/_ALIVE`, and `/%5Falive` MUST NOT bypass maintenance when supplied as the original URI path.

The bypass does not implement the liveness endpoint, authorize endpoint access, or change downstream response and Throwable ownership.

## Dependency, lifecycle, and observability policy (MUST)

`ReservedPathMatcher` is a final stateless static implementation and depends only on `SystemRouteTable` and native PHP operations.

It MUST NOT consume PSR-7 messages, runtime configuration, service containers, filesystem services, or observability ports. It MUST NOT retain mutable state, participate in ContextStore or UnitOfWork, implement `ResetInterface`, register `kernel.reset`, or participate in Kernel lifecycle hooks.

The matcher does not emit logs, spans, or metrics. Concrete path input MUST NOT be retained as context or exported through diagnostics or observability.

## Verification evidence

- `packages/platform/http/tests/Contract/SystemRouteTableShapeContractTest.php` verifies canonical declaration shape and immutability.
- `packages/platform/http/tests/Unit/ReservedPathMatcherSegmentPrefixTest.php` verifies concrete path normalization, matching boundaries, opaque percent encoding, deterministic results, and invalid-input policy.
- `packages/platform/http/tests/Integration/AliveEndpointBypassesMaintenanceTest.php` verifies exact maintenance bypass independently of matcher normalization.

## Non-goals

- A general router, URI parser, route-template compiler, or additional matching interface.
- Route admission guard implementation, endpoint dispatch, access policy, or response construction.
- New service wiring, runtime configuration, generated artifacts, context keys, or observability signals.

## Cross-references

- [SSoT Index](./INDEX.md)
- [System Route Table SSoT](./system-route-table.md)
- [Routing and HttpApp Contracts SSoT](./routing-and-http-app-contracts.md)
- [HTTP Middleware Catalog SSoT](./http-middleware-catalog.md)
- [ADR-0042: System route table and reserved-path matching](../adr/ADR-0042-system-route-table-and-reserved-path-matching.md)
