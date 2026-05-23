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

# ADR-0021: UnitOfWork context shape

## Status

Accepted.

## Context

Epic `1.270.0` introduces the first Kernel-owned UnitOfWork runtime shapes under:

```text
framework/packages/core/kernel/src/Runtime/
```

Earlier ADRs intentionally avoided freezing a UnitOfWork context model in `core/contracts`.

ADR-0006 introduced reset and UnitOfWork hook contracts as minimal format-neutral contracts and explicitly rejected introducing a contracts-level UnitOfWork context/result schema in epic `1.120.0`.

That decision remains correct for `core/contracts`.

The Kernel runtime now needs a canonical context shape owned by `core/kernel`, not by `core/contracts`.

The context shape must support future HTTP, CLI, queue, scheduler, worker, and custom runtime adapters without coupling the Kernel package to transport-specific APIs.

The Kernel package must remain independent of:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- `Psr\Http\Server\*`
- framework HTTP request/response implementations
- framework CLI command implementations
- queue vendor message objects
- scheduler vendor objects
- generated artifacts
- tooling-only packages

The context shape is needed before the UnitOfWork runtime executor is implemented, because tests, hooks, adapters, documentation, and future runtime code need one stable source of truth for:

- context identity fields;
- UnitOfWork type vocabulary;
- start timestamp representation;
- correlation id representation;
- safe context attributes;
- json-like value restrictions;
- deterministic normalization;
- safe diagnostics;
- hook/export boundary rules.

The detailed normative policy for this ADR is defined by:

```text
docs/ssot/uow-shapes.md
```

Related policy for lifecycle ordering, after-phase reset, and outcome mapping is defined by:

```text
docs/ssot/uow-outcome-policy.md
```

The context shape is introduced by `core/kernel`.

It is not a DTO-marker class by default.

It is not a contracts package shape.

It is not a transport object wrapper.

It is not an observability event.

It is not a generic metadata bag.

## Decision

Coretsia will introduce `UnitOfWorkContext` as the canonical Kernel-owned runtime context shape for the beginning of a UnitOfWork.

The implementation path is:

```text
framework/packages/core/kernel/src/Runtime/UnitOfWorkContext.php
```

The canonical UnitOfWork type vocabulary is implemented by:

```text
framework/packages/core/kernel/src/Runtime/UnitOfWorkType.php
```

The context shape is owned by:

```text
core/kernel
```

The shape is format-neutral and exportable as normalized json-like data.

The canonical logical fields are:

```text
uowId
type
startedAt
correlationId
attributes
```

The canonical exported array shape uses deterministic key ordering governed by:

```text
docs/ssot/uow-shapes.md
```

## Decision 1: Kernel owns UnitOfWorkContext

`UnitOfWorkContext` is a Kernel runtime shape/value object.

It is owned by:

```text
core/kernel
```

It is not owned by:

- `core/contracts`
- `core/foundation`
- `platform/http`
- `platform/cli`
- `platform/routing`
- `integrations/*`
- applications

The Kernel owns the canonical field set, validation rules, export rules, and hook/export boundary rules.

Platform adapters may attach safe adapter-specific metadata only through:

```text
attributes
```

Platform adapters must not add top-level context fields.

## Decision 2: UnitOfWorkContext is format-neutral

`UnitOfWorkContext` must not depend on HTTP, CLI, queue, scheduler, worker, or vendor-specific runtime APIs.

The context must not contain:

- PSR-7 request objects;
- PSR-7 response objects;
- PSR-15 middleware objects;
- PSR-15 request handler objects;
- framework HTTP request objects;
- framework HTTP response objects;
- CLI command objects;
- CLI input/output objects;
- queue message objects;
- scheduler job objects;
- worker context objects;
- concrete service container objects;
- service instances;
- closures;
- resources.

Runtime adapters may derive safe scalar or json-like metadata from transport input, but raw transport objects must not be stored in the context.

## Decision 3: Canonical field set

The canonical `UnitOfWorkContext` fields are:

| field           | type                  | required | meaning                                                 |
|-----------------|-----------------------|----------|---------------------------------------------------------|
| `uowId`         | `string`              | yes      | Stable UnitOfWork id. ULID format is recommended.       |
| `type`          | `string`              | yes      | UnitOfWork type token.                                  |
| `startedAt`     | `int`                 | yes      | Wall-clock start timestamp in Unix epoch milliseconds.  |
| `correlationId` | `string`              | yes      | Safe correlation id. ULID format is recommended.        |
| `attributes`    | `array<string,mixed>` | yes      | Safe json-like metadata map for the UnitOfWork context. |

No additional top-level context fields are introduced by this epic.

Adding a future top-level field requires:

- update to `docs/ssot/uow-shapes.md`;
- update to this ADR or a new ADR;
- update to contract tests;
- explicit owner review.

## Decision 4: UnitOfWork type vocabulary

The canonical UnitOfWork type values are:

```text
http
cli
queue
scheduler
```

These values are stable lowercase ASCII tokens.

They must be compared byte-for-byte.

They must not be translated, localized, title-cased, or vendor-mapped.

No other type token is canonical in epic `1.270.0`.

Adding a future type token requires SSoT and contract-test updates.

## Decision 5: `uowId` is a safe UnitOfWork id

`uowId` is a stable id for one UnitOfWork.

It must be a non-empty string.

ULID format is recommended.

The recommended generator is the Foundation id generator port:

```text
Coretsia\Foundation\Id\IdGeneratorInterface
```

`uowId` must not encode or expose:

- credentials;
- passwords;
- secrets;
- tokens;
- authorization headers;
- cookies;
- session ids;
- raw request bodies;
- raw response bodies;
- raw queue payloads;
- raw SQL;
- direct user identifiers;
- private customer data.

`uowId` must not be used as a metric label.

## Decision 6: `startedAt` is wall-clock milliseconds, not duration source

`startedAt` is the wall-clock timestamp captured at UnitOfWork begin.

It must be an integer Unix epoch timestamp in milliseconds.

It must represent UTC time.

The recommended clock source is:

```text
Psr\Clock\ClockInterface
```

`startedAt` must not be used to calculate canonical duration.

System wall time may move forward or backward.

Duration measurement belongs to `UnitOfWorkResult.durationMs` and must use the canonical monotonic timing source defined in:

```text
docs/ssot/time-ids-and-duration.md
docs/ssot/uow-shapes.md
```

## Decision 7: `correlationId` is safe correlation metadata

`correlationId` is a safe id used for correlation.

It must be a non-empty string.

ULID format is recommended.

It may be used by logging and tracing owner packages according to observability policy.

It must not expose:

- direct user identifiers;
- secrets;
- tokens;
- session ids;
- authorization values;
- cookies;
- raw payloads;
- raw SQL;
- private customer data.

`correlationId` must not be used as a metric label.

## Decision 8: `attributes` is a safe json-like map

`attributes` is the only extension point in `UnitOfWorkContext`.

The root `attributes` value must be a map with string keys.

An empty array represents an empty attributes map at this shape boundary.

A non-empty list must not be used as the root attributes value.

Allowed scalar values are:

```text
null
bool
int
string
```

Forbidden scalar values are:

```text
float
NaN
INF
-INF
```

Allowed containers are:

```text
list of allowed values
map with string keys and allowed values
```

Forbidden values at any nesting depth include:

- floats;
- `NaN`;
- `INF`;
- `-INF`;
- objects;
- enum objects;
- closures;
- resources;
- streams;
- filesystem handles;
- service instances;
- runtime wiring objects;
- throwable instances;
- request objects;
- response objects;
- PSR-7 objects;
- vendor SDK objects.

Finite floats are forbidden.

Objects are forbidden even when stringable.

If decimal values are needed, they must be represented as strings with a documented owner format.

## Decision 9: Attributes are bounded by Kernel config

`UnitOfWorkContext.attributes` must obey the Kernel-owned defensive limits:

```text
kernel.uow.attributes.max_depth
kernel.uow.attributes.max_keys
```

The defaults are:

```text
kernel.uow.attributes.max_depth = 10
kernel.uow.attributes.max_keys = 200
```

The config defaults path is:

```text
framework/packages/core/kernel/config/kernel.php
```

The config rules path is:

```text
framework/packages/core/kernel/config/rules.php
```

The config root is:

```text
kernel
```

The defaults file must return the `kernel` subtree only.

It must not return a repeated root wrapper such as:

```php
return [
    'kernel' => [
        // ...
    ],
];
```

`max_depth` and `max_keys` must be integers greater than zero.

Limit failures must be deterministic and safe.

Diagnostics must not dump raw attribute values.

## Decision 10: Attributes are normalized before export

When a UnitOfWork context crosses a Kernel hook/export boundary, it must be normalized deterministically.

Normalization rules:

- maps are sorted recursively by string key using byte-order `strcmp`;
- lists preserve caller-supplied order;
- locale must not affect ordering;
- normalization must not call `setlocale`;
- normalization must not rely on `LC_ALL`;
- normalization must not rely on filesystem traversal order;
- normalization must not rely on Composer package order.

The exported top-level context shape must follow the deterministic key ordering defined in:

```text
docs/ssot/uow-shapes.md
```

## Decision 11: Failure diagnostics are deterministic and safe

Invalid attributes must fail deterministically.

The canonical context validation exception is:

```text
Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException
```

The canonical error code is:

```text
CORETSIA_UOW_CONTEXT_INVALID
```

Diagnostics may include only safe structural metadata:

```text
path-to-value
stable reason code
safe type name
```

Diagnostics must not include raw values.

Valid diagnostic examples:

```text
attributes.duration: float-forbidden
attributes.items[3].status: object-forbidden
attributes.items[0].meta: max-depth-exceeded
```

Invalid diagnostic examples:

```text
attributes.duration = 1.25
attributes.authorization = Bearer ...
attributes.payload = {"email":"person@example.com"}
```

## Decision 12: Hook/export boundary uses arrays, not objects

Before hooks and export consumers receive a normalized array derived from `UnitOfWorkContext`.

The boundary representation is:

```text
UnitOfWorkContext -> normalized array $ctx
```

No object instance may cross this boundary.

Forbidden boundary values include:

- `UnitOfWorkContext` object;
- request object;
- response object;
- PSR-7 object;
- CLI command object;
- queue message object;
- service instance;
- closure;
- resource.

This decision introduces a Kernel-owned exported context shape.

It does not move the context schema into `core/contracts`.

It does not require `core/contracts` hook interfaces to own or expose the context shape.

## Decision 13: DTO policy remains explicit opt-in

`UnitOfWorkContext` is not a DTO-marker class by default.

It must not be marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

DTO gates apply only to explicitly marked DTO transport classes.

`UnitOfWorkContext` is governed by Kernel SSoT and Kernel contract tests, not DTO marker policy.

Having structured fields, constructor arguments, accessors, or exported array representation does not make this class a DTO.

## Decision 14: No config roots, tags, or artifacts are introduced

Epic `1.270.0` introduces no new config root.

The existing config root is:

```text
kernel
```

Epic `1.270.0` introduces these Kernel config keys only:

```text
kernel.uow.attributes.max_depth
kernel.uow.attributes.max_keys
```

This ADR introduces no tags.

This ADR introduces no tag constants.

This ADR introduces no generated artifacts.

In particular, this ADR must not introduce reset-tag constants or redefine ownership of:

```text
kernel.reset
kernel.hook.before_uow
kernel.hook.after_uow
kernel.stateful
```

Tag ownership remains governed by:

```text
docs/ssot/tags.md
docs/ssot/reset-tags.md
docs/ssot/uow-and-reset-contracts.md
```

## Security and redaction

`UnitOfWorkContext` must prefer rejection or omission over unsafe storage.

`attributes` must not contain:

- authorization headers;
- cookies;
- session ids;
- tokens;
- passwords;
- credentials;
- private keys;
- raw headers;
- raw request payloads;
- raw response payloads;
- raw queue payloads;
- raw SQL;
- stack traces;
- raw Throwable objects;
- PII;
- private customer data;
- direct user identifiers such as email, phone, full name, username, or external account identifiers;
- absolute local paths;
- environment-specific bytes.

Allowed safe metadata includes:

```text
safe ids
stable enums
counts
lengths
hashes
bounded safe status/category tokens
```

Safe derivations such as `hash(value)` and `len(value)` must not allow reconstruction of sensitive values.

## Observability impact

This ADR introduces no metrics, spans, or logs.

Future runtime owners may derive observability data from `UnitOfWorkContext` only when emissions comply with:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
```

Safe candidate values include:

```text
type -> operation value where owner policy allows it
```

Metric labels must not include:

```text
uowId
correlationId
request_id
user_id
tenant_id
path
field
property
```

IDs must not be metric labels.

`attributes` must not be copied wholesale into logs, spans, metrics, errors, health output, profiling output, public diagnostics, or generated artifacts.

## Runtime lifecycle impact

A later Kernel runtime integration must create or derive a `UnitOfWorkContext` at begin-UoW.

The accepted begin shape contains:

```text
uowId
type
startedAt
correlationId
attributes
```

Before hooks and adapters receive only a normalized exported array derived from this shape.

The context shape remains Kernel-owned even when platform adapters attach safe metadata.

HTTP adapters may attach safe HTTP-specific attributes.

CLI adapters may attach safe CLI-specific attributes.

Queue and scheduler adapters may attach safe adapter-specific attributes when those owners define their integration behavior.

No adapter may add top-level context fields.

No adapter may attach unsafe raw payloads, secrets, PII, or transport objects.

## Consequences

### Positive

The Kernel gets a stable UnitOfWork context shape before lifecycle executor implementation.

Runtime adapters can rely on one canonical context field set.

The context remains usable across HTTP, CLI, queue, scheduler, workers, and custom runtimes.

The shape does not couple Kernel to PSR-7, PSR-15, platform packages, or vendor objects.

Hook/export consumers receive deterministic normalized arrays.

Attributes provide a controlled extension point without allowing arbitrary unsafe payloads.

Float-forbidden json-like policy remains aligned with Phase 0 deterministic payload rules.

Safe diagnostics prevent accidental secret or PII leaks.

Contract tests can lock the shape before wider runtime implementation.

### Negative / trade-offs

Adapters cannot place arbitrary objects or raw transport data into context.

Floats are forbidden even when finite.

Every future top-level context field requires SSoT and test updates.

The root `attributes` value must be a map, not a list.

Context validation requires explicit guard logic in Kernel.

The Kernel must maintain clear boundaries between runtime shape objects and exported arrays.

## Rejected alternatives

### Put UnitOfWorkContext in `core/contracts`

Rejected.

`core/contracts` must remain a pure library boundary and must not own Kernel runtime shape evolution.

ADR-0006 intentionally avoided freezing a context schema in `core/contracts`.

`UnitOfWorkContext` is Kernel-owned.

### Pass PSR-7 request/response objects through context

Rejected.

PSR-7 would make the context HTTP-specific and would break CLI, queue, scheduler, worker, and custom runtime use.

### Make context attributes an arbitrary mixed bag

Rejected.

An arbitrary mixed bag would allow objects, resources, raw payloads, secrets, PII, and high-cardinality values to cross runtime boundaries.

The accepted design uses a json-like map with deterministic validation and redaction constraints.

### Allow floats in attributes

Rejected.

Floats create deterministic serialization and cross-platform representation risks.

Finite floats, `NaN`, `INF`, and `-INF` are all forbidden.

Use integer milliseconds, integer counts, or documented string decimal formats instead.

### Allow object values if they are stringable

Rejected.

Stringable objects still carry behavior, identity, runtime state, and hidden data.

Context attributes must be structural json-like data only.

### Use raw request path or raw payload as attributes

Rejected.

Raw paths, raw payloads, headers, cookies, authorization values, and SQL can expose secrets, PII, high-cardinality values, or environment-specific data.

Safe alternatives include hashes, lengths, counts, and bounded category tokens.

### Make UnitOfWorkContext a DTO by convention

Rejected.

DTO policy is explicit opt-in only through `#[Coretsia\Dto\Attribute\Dto]`.

`UnitOfWorkContext` is a Kernel runtime shape/value object, not a DTO-marker class by default.

### Add adapter-specific top-level fields

Rejected.

Top-level fields are Kernel-owned.

Adapter-specific metadata belongs under safe `attributes`.

## Non-goals

This ADR does not implement:

- UnitOfWork lifecycle executor;
- hook dispatcher;
- hook discovery;
- reset orchestration;
- reset tag constants;
- outcome mapping;
- result shape;
- HTTP response construction;
- HTTP status code selection;
- PSR-7 integration;
- PSR-15 integration;
- CLI command execution;
- queue consumer behavior;
- scheduler behavior;
- generated artifacts;
- problem-details formatting;
- logging backend behavior;
- tracing backend behavior;
- metrics backend behavior.

## Boundaries

This ADR does not change the `core/contracts` hook interface shapes introduced earlier.

This ADR does not add parameters to contracts-level hook interfaces.

This ADR does not move transport state into Kernel.

This ADR does not allow objects across hook/export boundaries.

This ADR does not introduce new config roots.

This ADR does not introduce new tags.

This ADR does not introduce new artifacts.

## Verification

Expected verification includes:

```text
framework/packages/core/kernel/tests/Contract/UnitOfWorkContextShapeContractTest.php
framework/packages/core/kernel/tests/Contract/UnitOfWorkContextAttributesAreJsonLikeContractTest.php
framework/packages/core/kernel/tests/Contract/KernelConfigSubtreeShapeContractTest.php
```

Verification must prove:

- context field set is stable;
- context exported key order is stable;
- `uowId` is represented as string;
- `type` accepts only `http`, `cli`, `queue`, and `scheduler`;
- `startedAt` is represented as integer Unix epoch milliseconds;
- `correlationId` is represented as string;
- `attributes` root is a map;
- attributes accept `null`, `bool`, `int`, `string`, lists, and string-keyed maps;
- attributes reject floats, including `NaN`, `INF`, and `-INF`;
- attributes reject objects;
- attributes reject closures;
- attributes reject resources;
- attributes reject non-string map keys;
- attributes are normalized deterministically;
- nested maps are sorted by byte-order `strcmp`;
- lists preserve order;
- diagnostics do not expose raw values;
- `kernel.uow.attributes.max_depth` is enforced;
- `kernel.uow.attributes.max_keys` is enforced;
- `UnitOfWorkContextInvalidException` uses `CORETSIA_UOW_CONTEXT_INVALID`;
- `config/kernel.php` returns subtree only;
- `config/kernel.php` does not repeat the `kernel` root;
- no `@*` directive keys exist under the returned Kernel defaults.

## Related SSoT

- `docs/ssot/uow-shapes.md`
- `docs/ssot/uow-outcome-policy.md`
- `docs/ssot/uow-and-reset-contracts.md`
- `docs/ssot/config-and-env.md`
- `docs/ssot/context-store.md`
- `docs/ssot/context-keys.md`
- `docs/ssot/time-ids-and-duration.md`
- `docs/ssot/error-descriptor.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/dto-policy.md`
- `docs/ssot/tags.md`
- `docs/ssot/reset-tags.md`

## Related ADR

- `docs/adr/ADR-0006-reset-interface-uow-hooks.md`
- `docs/adr/ADR-0015-context-bag-context-store-correlation-id.md`
- `docs/adr/ADR-0016-clock-ids-stopwatch.md`
- `docs/adr/ADR-0019-enhanced-reset-long-running.md`

## Related epic

- `1.270.0 Kernel: UnitOfWork Shapes Pack (Context + Result + Outcome + SSoT invariants)`
