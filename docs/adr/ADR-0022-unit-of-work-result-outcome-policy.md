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

# ADR-0022: UnitOfWork result and outcome policy

## Status

Accepted.

## Context

Epic `1.270.0` introduces Kernel-owned UnitOfWork runtime shapes and outcome policy under:

```text
framework/packages/core/kernel/src/Runtime/
```

`ADR-0021` records the decision to introduce `UnitOfWorkContext` as the Kernel-owned format-neutral context shape for the beginning of a UnitOfWork.

The Kernel also needs a canonical result shape and stable outcome vocabulary for the end of a UnitOfWork.

The result shape must support future HTTP, CLI, queue, scheduler, worker, and custom runtime adapters without coupling `core/kernel` to transport-specific APIs.

The outcome policy must be deterministic, testable, and stable before implementing the UnitOfWork lifecycle executor.

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

Earlier ADRs intentionally kept `core/contracts` minimal.

ADR-0006 introduced reset and UnitOfWork hook contracts as format-neutral contracts and explicitly avoided freezing context/result schemas in `core/contracts`.

That decision remains correct.

`UnitOfWorkResult`, `Outcome`, and outcome mapping policy are Kernel-owned runtime decisions.

The detailed normative shape policy is defined by:

```text
docs/ssot/uow-shapes.md
```

The detailed normative lifecycle and outcome policy is defined by:

```text
docs/ssot/uow-outcome-policy.md
```

The result/outcome policy must also respect the existing Foundation reset ownership:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

Foundation owns reset discovery and reset orchestration mechanics.

Kernel owns the UnitOfWork lifecycle trigger point and the policy that reset must run after after-hooks and before `endUoW()`.

This ADR records the decision introduced by epic `1.270.0`.

## Decision

Coretsia will introduce `UnitOfWorkResult` as the canonical Kernel-owned runtime result shape for the completion of a UnitOfWork.

Coretsia will introduce `Outcome` as the canonical Kernel-owned outcome token carrier.

The implementation paths are:

```text
framework/packages/core/kernel/src/Runtime/UnitOfWorkResult.php
framework/packages/core/kernel/src/Runtime/Outcome.php
```

The result validation exception path is:

```text
framework/packages/core/kernel/src/Runtime/Exception/UnitOfWorkResultInvalidException.php
```

`UnitOfWorkResultInvalidException` is the canonical Kernel result validation failure type.

It exposes a finite public reason-token vocabulary, rejects unknown reason strings deterministically, and stores only safe diagnostic paths. Unsafe diagnostic paths are replaced with the stable placeholder `<path>`.

The exception message may include only a stable reason token and a safe structural path. It must never include raw extension values, unsafe raw map keys, raw exported error values, secrets, raw SQL, authorization data, cookies, tokens, session ids, PII, stack traces, absolute local paths, or environment-specific data.

The result shape and outcome vocabulary are owned by:

```text
core/kernel
```

The canonical logical result fields are:

```text
uowId
type
correlationId
startedAt
finishedAt
durationMs
outcome
error
extensions
```

`error` is optional.

The canonical outcome tokens are:

```text
success
handled_error
fatal_error
```

The canonical lifecycle reset position is:

```text
after hooks → ResetOrchestrator.resetAll() → endUoW
```

The canonical HTTP and CLI outcome mappings are governed by:

```text
docs/ssot/uow-outcome-policy.md
```

## Decision 1: Kernel owns UnitOfWorkResult

`UnitOfWorkResult` is a Kernel runtime shape/value object.

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

The Kernel owns the canonical field set, validation rules, export rules, error boundary, outcome vocabulary, and hook/export boundary rules.

Platform adapters may attach safe adapter-specific completion metadata only through:

```text
extensions
```

Platform adapters must not add top-level result fields.

## Decision 2: UnitOfWorkResult is format-neutral

`UnitOfWorkResult` must not depend on HTTP, CLI, queue, scheduler, worker, or vendor-specific runtime APIs.

The result must not contain:

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

Runtime adapters may derive safe scalar or json-like metadata from transport completion data, but raw transport objects must not be stored in the result.

## Decision 3: Canonical result field set

The canonical `UnitOfWorkResult` fields are:

| field           | type                  | required | meaning                                                     |
|-----------------|-----------------------|----------|-------------------------------------------------------------|
| `uowId`         | `string`              | yes      | UnitOfWork id copied from the originating context.          |
| `type`          | `string`              | yes      | UnitOfWork type copied from the originating context.        |
| `correlationId` | `string`              | yes      | Safe correlation id copied from the originating context.    |
| `startedAt`     | `int`                 | yes      | Wall-clock start timestamp copied from the context.         |
| `finishedAt`    | `int`                 | yes      | Wall-clock completion timestamp in Unix epoch milliseconds. |
| `durationMs`    | `int`                 | yes      | Canonical non-negative duration in integer milliseconds.    |
| `outcome`       | `string`              | yes      | Outcome token.                                              |
| `error`         | `array<string,mixed>` | no       | Normalized json-like exported error map.                    |
| `extensions`    | `array<string,mixed>` | yes      | Safe json-like completion metadata map.                     |

No additional top-level result fields are introduced by this epic.

Adding a future top-level field requires:

- update to `docs/ssot/uow-shapes.md`;
- update to `docs/ssot/uow-outcome-policy.md` when policy is affected;
- update to this ADR or a new ADR;
- update to contract tests;
- explicit owner review.

## Decision 4: Result identity fields match the originating context

`UnitOfWorkResult` must preserve identity from the originating `UnitOfWorkContext`.

The following fields must match the originating context:

```text
uowId
type
correlationId
startedAt
```

`uowId` must remain a safe non-empty string.

`type` must remain one of:

```text
http
cli
queue
scheduler
```

`correlationId` must remain a safe non-empty string.

`startedAt` must remain the original wall-clock start timestamp in Unix epoch milliseconds.

These fields must not be derived again at result time.

## Decision 5: `finishedAt` is wall-clock completion time, not duration source

`finishedAt` is the wall-clock timestamp captured at UnitOfWork completion.

It must be an integer Unix epoch timestamp in milliseconds.

It must represent UTC time.

The recommended clock source is:

```text
Psr\Clock\ClockInterface
```

`finishedAt` must not be used to calculate canonical duration.

System wall time may move forward or backward.

Consumers must not rely on:

```text
finishedAt >= startedAt
```

Consumers must not derive duration from:

```text
finishedAt - startedAt
```

## Decision 6: `durationMs` is the canonical duration source

`durationMs` is the only canonical duration field for a UnitOfWork result.

It must be an integer.

It must be greater than or equal to zero.

The canonical exported unit is milliseconds.

`durationMs` must be measured from the canonical monotonic timing source:

```text
Coretsia\Foundation\Time\Stopwatch
```

`durationMs` must not be calculated from wall-clock timestamps.

`durationMs` must not be represented as float seconds.

This keeps duration deterministic and avoids wall-clock drift.

## Decision 7: Outcome tokens are stable Kernel vocabulary

The canonical outcome tokens are:

```text
success
handled_error
fatal_error
```

These values are stable lowercase ASCII tokens.

They must be compared byte-for-byte.

They must not be translated, localized, title-cased, or vendor-mapped.

No other outcome token is canonical in epic `1.270.0`.

Adding a future outcome token requires:

- update to `docs/ssot/uow-outcome-policy.md`;
- update to `docs/ssot/uow-shapes.md`;
- update to this ADR or a new ADR;
- update to `OutcomeMappingStabilityContractTest`;
- explicit owner review.

## Decision 8: Outcome meaning

The accepted outcome meanings are:

| outcome         | Meaning                                                                 |
|-----------------|-------------------------------------------------------------------------|
| `success`       | The UnitOfWork completed without a transport/application error outcome. |
| `handled_error` | The UnitOfWork completed with an expected/handled error result.         |
| `fatal_error`   | The UnitOfWork terminated through an uncaught exception/fatal failure.  |

`handled_error` means the runtime owner produced a controlled completion result.

`fatal_error` means the runtime owner observed an uncaught exception or equivalent fatal boundary failure.

`fatal_error` takes precedence over status-code or exit-code based mapping.

## Decision 9: HTTP outcome mapping

HTTP outcome mapping is based on the completed HTTP status code when no uncaught exception occurred.

Exact mapping:

| HTTP condition     | Outcome         |
|--------------------|-----------------|
| status `< 400`     | `success`       |
| status `>= 400`    | `handled_error` |
| uncaught exception | `fatal_error`   |

Rules:

- HTTP status `< 400` maps to `success`.
- HTTP status `>= 400` maps to `handled_error`.
- An uncaught exception maps to `fatal_error`.
- `fatal_error` takes precedence over status-code mapping.
- Kernel does not build HTTP responses.
- Kernel does not select HTTP status codes.
- Kernel does not depend on PSR-7 or PSR-15.

A completed HTTP `500` response without an uncaught exception is a handled runtime result and maps to:

```text
handled_error
```

If an uncaught exception occurred, the outcome is:

```text
fatal_error
```

## Decision 10: CLI outcome mapping

CLI outcome mapping is based on the completed exit code when no uncaught exception occurred.

Exact mapping:

| CLI condition                               | Outcome         |
|---------------------------------------------|-----------------|
| `exitCode = 0`                              | `success`       |
| `exitCode != 0` without uncaught exceptions | `handled_error` |
| uncaught exception                          | `fatal_error`   |

Rules:

- Exit code `0` maps to `success`.
- Exit code other than `0` maps to `handled_error` when no uncaught exception occurred.
- An uncaught exception maps to `fatal_error`.
- `fatal_error` takes precedence over exit-code mapping.
- Kernel does not execute CLI commands directly in this policy epic.
- Kernel does not render CLI output.

A CLI command returning exit code `2` without an uncaught exception maps to:

```text
handled_error
```

## Decision 11: Queue and scheduler mappings are out of scope

Epic `1.270.0` does not define queue outcome mapping.

Epic `1.270.0` does not define scheduler outcome mapping.

The outcome token vocabulary still applies to future queue and scheduler UnitOfWork types.

Future owner epics may define queue or scheduler mapping only by updating:

```text
docs/ssot/uow-outcome-policy.md
```

and the corresponding contract tests.

Until then, only HTTP and CLI mapping policies are canonical.

## Decision 12: Failure precedence

Outcome selection follows this precedence:

1. uncaught exception or equivalent fatal boundary failure;
2. transport-specific controlled completion result;
3. default success condition for completed work.

If an uncaught exception occurs, the outcome must be:

```text
fatal_error
```

This applies even if a partial HTTP status code or CLI exit code exists.

If no uncaught exception occurs, HTTP and CLI adapters use the exact mapping tables in `docs/ssot/uow-outcome-policy.md`.

## Decision 13: ErrorDescriptor is optional internally, json-like externally

`UnitOfWorkResult.error` may be represented internally as:

```text
Coretsia\Contracts\Observability\Errors\ErrorDescriptor
```

Before `UnitOfWorkResult` crosses a Kernel hook/export boundary, `error` must be normalized to a json-like exported error map.

No `ErrorDescriptor` object instance may cross the hook/export boundary.

No `Throwable` object instance may cross the hook/export boundary.

The exported error map must follow:

```text
docs/ssot/error-descriptor.md
docs/ssot/uow-shapes.md
```

The exported error map must not contain:

- raw Throwable objects;
- raw exception messages when unsafe;
- stack traces;
- transport objects;
- request objects;
- response objects;
- PSR-7 objects;
- CLI command objects;
- queue message objects;
- raw headers;
- raw cookies;
- raw authorization values;
- raw session identifiers;
- raw tokens;
- raw request payloads;
- raw response payloads;
- raw SQL;
- PII;
- private customer data;
- service instances;
- closures;
- resources.

If no error exists, the exported result shape should omit the `error` key.

## Decision 14: `extensions` is a safe json-like completion map

`extensions` is the only adapter/runtime metadata extension point in `UnitOfWorkResult`.

The root `extensions` value must be a map with string keys.

An empty array represents an empty extensions map at this shape boundary.

A non-empty list must not be used as the root extensions value.

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

## Decision 15: Result export is normalized and object-free

When a UnitOfWork result crosses a Kernel hook/export boundary, it must be normalized deterministically.

Normalization rules:

- maps are sorted recursively by string key using byte-order `strcmp`;
- lists preserve caller-supplied order;
- locale must not affect ordering;
- normalization must not call `setlocale`;
- normalization must not rely on `LC_ALL`;
- normalization must not rely on filesystem traversal order;
- normalization must not rely on Composer package order.

The exported top-level result shape must follow the deterministic key ordering defined in:

```text
docs/ssot/uow-shapes.md
```

After hooks, adapters, artifacts, or other export consumers receive:

```text
UnitOfWorkResult -> normalized array $result
```

No object instance may cross this boundary.

Forbidden boundary values include:

- `UnitOfWorkResult` object;
- `ErrorDescriptor` object;
- Throwable object;
- request object;
- response object;
- PSR-7 object;
- CLI command object;
- queue message object;
- service instance;
- closure;
- resource.

## Decision 16: Result validation diagnostics are deterministic and safe

Invalid result extensions must fail deterministically.

Failure diagnostics may include only safe structural metadata:

```text
path-to-value
stable reason code
safe type name
```

Diagnostics must not include raw values.

Valid diagnostic examples:

```text
extensions.duration: float-forbidden
extensions.items[3].status: object-forbidden
extensions.payload: raw-payload-forbidden
```

Invalid diagnostic examples:

```text
extensions.duration = 1.25
extensions.authorization = Bearer ...
extensions.payload = {"email":"person@example.com"}
extensions.stacktrace = #0 ...
```

Invalid `UnitOfWorkResult` shapes, invalid `extensions`, and invalid exported `error` maps MUST fail with:

```text
Coretsia\Kernel\Runtime\Exception\UnitOfWorkResultInvalidException
CORETSIA_UOW_RESULT_INVALID
```

This mirrors the context-side deterministic validation model while keeping context and result failures distinguishable for contract tests and diagnostics.

## Decision 17: Reset lifecycle invariant is defined here, implementation later

Foundation owns reset discovery and reset orchestration mechanics.

The canonical Foundation reset executor is:

```text
Coretsia\Foundation\Runtime\Reset\ResetOrchestrator
```

The typo `ResetOrcestrator` is invalid and must not be introduced in docs, code, tests, or generated artifacts.

Kernel owns the UnitOfWork lifecycle trigger point.

The canonical reset position is:

```text
after hooks → ResetOrchestrator.resetAll() → endUoW
```

Once the after-phase is entered, `ResetOrchestrator.resetAll()` must run exactly once before `endUoW()`.

This exactly-once reset requirement applies even if an after-uow hook throws.

The next UnitOfWork must start clean.

Epic `1.270.0` defines this policy and its contract evidence only.

Runtime lifecycle implementation is introduced by a later Kernel runtime epic.

## Decision 18: No reset DI tag identifiers are introduced

Epic `1.270.0` must not introduce reset DI tag identifier constants.

Epic `1.270.0` must not depend on reset tag naming.

Epic `1.270.0` must not reference `TagRegistry` enumeration logic.

Reset discovery remains owned by `core/foundation`.

Reset tag naming and tag ownership remain governed by:

```text
docs/ssot/tags.md
docs/ssot/reset-tags.md
docs/ssot/uow-and-reset-contracts.md
```

The canonical code-level registry for framework-reserved DI tag identifier strings is:

```text
Coretsia\Foundation\Tag\ReservedTags
```

This ADR may reference `ResetOrchestrator.resetAll()` as the canonical reset trigger action.

It must not duplicate Foundation reset discovery implementation details.

## Decision 19: Outcome mapping is not configurable in this epic

Epic `1.270.0` introduces no outcome mapping config keys.

Outcome mapping is canonical policy, not runtime configuration.

The following config keys are not introduced:

```text
kernel.uow.outcomes.*
kernel.uow.outcome_mapping.*
kernel.outcome.*
kernel.http.outcome.*
kernel.cli.outcome.*
```

The Kernel config keys introduced by epic `1.270.0` are limited to:

```text
kernel.uow.attributes.max_depth
kernel.uow.attributes.max_keys
```

Those keys govern context attributes and are documented by:

```text
docs/ssot/uow-shapes.md
```

## Decision 20: DTO policy remains explicit opt-in

`Outcome`, `UnitOfWorkContext`, and `UnitOfWorkResult` are Kernel runtime shapes/value objects or enum-like runtime symbols.

They are not DTO-marker classes by default.

They must not be marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

DTO gates apply only to explicitly marked DTO transport classes.

The UnitOfWork result and outcome policy is governed by Kernel SSoT and Kernel contract tests, not DTO marker policy.

Having structured fields, constructor arguments, accessors, enum-like constants, or exported array representation does not make these classes DTOs.

## Security and redaction

`UnitOfWorkResult` must prefer rejection or omission over unsafe storage.

`extensions` must not contain:

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

Future runtime owners may derive observability data from `UnitOfWorkResult` only when emissions comply with:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
```

Safe candidate values include:

```text
type -> operation value where owner policy allows it
outcome -> outcome label where metric catalog allows it
durationMs -> metric value or span/log field
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

`durationMs` must not be used as a metric label.

`extensions` must not be copied wholesale into logs, spans, metrics, errors, health output, profiling output, public diagnostics, or generated artifacts.

## Runtime lifecycle impact

A later Kernel runtime integration must produce or derive a `UnitOfWorkResult` at UnitOfWork completion.

The accepted result shape contains:

```text
uowId
type
correlationId
startedAt
finishedAt
durationMs
outcome
error
extensions
```

After hooks and adapters receive only a normalized exported array derived from this shape.

The result shape remains Kernel-owned even when platform adapters attach safe metadata.

HTTP adapters may attach safe HTTP-specific extensions.

CLI adapters may attach safe CLI-specific extensions.

Queue and scheduler adapters may attach safe adapter-specific extensions when those owners define their integration behavior.

No adapter may add top-level result fields.

No adapter may attach unsafe raw payloads, secrets, PII, stack traces, or transport objects.

## Consequences

### Positive

The Kernel gets a stable UnitOfWork result shape before lifecycle executor implementation.

Runtime adapters can rely on one canonical result field set.

Outcome tokens are stable and contract-testable.

HTTP and CLI mapping is deterministic and not dependent on adapter-local vocabulary.

Fatal failures have clear precedence over partial status or exit-code data.

The result remains usable across HTTP, CLI, queue, scheduler, workers, and custom runtimes.

The shape does not couple Kernel to PSR-7, PSR-15, platform packages, or vendor objects.

Hook/export consumers receive deterministic normalized arrays.

`ErrorDescriptor` can be used internally while preserving object-free exported boundaries.

`extensions` provides a controlled metadata extension point without allowing unsafe payloads.

Result validation failures have a stable machine-readable error code.

Contract tests can assert `CORETSIA_UOW_RESULT_INVALID` instead of relying on generic PHP exception types.

Float-forbidden json-like policy remains aligned with Phase 0 deterministic payload rules.

Exactly-once reset policy is documented before the runtime executor is implemented.

### Negative / trade-offs

Adapters cannot place arbitrary objects or raw transport data into results.

Floats are forbidden even when finite.

Every future top-level result field requires SSoT and test updates.

Queue and scheduler mappings are intentionally deferred.

The root `extensions` value must be a map, not a list.

Result validation requires explicit guard logic in Kernel.

Kernel owns one additional result-specific exception class.

The Kernel must maintain clear boundaries between runtime shape objects and exported arrays.

The reset invariant is defined before the runtime executor exists, so implementation must conform later.

## Rejected alternatives

### Put UnitOfWorkResult in `core/contracts`

Rejected.

`core/contracts` must remain a pure library boundary and must not own Kernel runtime shape evolution.

ADR-0006 intentionally avoided freezing a result schema in `core/contracts`.

`UnitOfWorkResult` is Kernel-owned.

### Use HTTP status code as the outcome

Rejected.

HTTP status codes are transport-specific and do not work for CLI, queue, scheduler, workers, or custom runtimes.

The accepted design uses stable Kernel outcome tokens.

### Use CLI exit code as the outcome

Rejected.

CLI exit codes are transport-specific and do not work for HTTP, queue, scheduler, workers, or custom runtimes.

The accepted design maps exit codes into stable Kernel outcome tokens.

### Treat all HTTP `5xx` responses as fatal errors

Rejected.

A completed HTTP response with status `>= 400` is a controlled completion result when no uncaught exception occurred.

It maps to `handled_error`.

`fatal_error` is reserved for uncaught exception or equivalent fatal boundary failure.

### Make outcome mapping configurable

Rejected.

Configurable mapping would create package drift and make contract tests weaker.

The accepted HTTP/CLI mapping is canonical policy.

### Export ErrorDescriptor objects directly

Rejected.

Objects must not cross the Kernel hook/export boundary.

The accepted design allows internal `ErrorDescriptor` representation but exports only a normalized json-like error map.

### Put stack traces in `result.extensions`

Rejected.

Stack traces are unsafe for Kernel hook/export arrays and may expose implementation details, paths, secrets, payloads, or PII.

`result.extensions` is safe metadata only.

### Make result extensions an arbitrary mixed bag

Rejected.

An arbitrary mixed bag would allow objects, resources, raw payloads, secrets, PII, and stack traces to cross runtime boundaries.

The accepted design uses a json-like map with deterministic validation and redaction constraints.

### Use generic InvalidArgumentException for result validation

Rejected.

Generic PHP exception types are less precise for Kernel contract tests and do not provide a stable machine-readable Kernel error code.

The accepted design uses `UnitOfWorkResultInvalidException` with `CORETSIA_UOW_RESULT_INVALID`.

### Allow arbitrary result validation reason strings

Rejected.

Arbitrary reason strings would weaken contract tests and could accidentally turn user/runtime-derived data into diagnostics.

The accepted design uses a finite public reason-token vocabulary on `UnitOfWorkResultInvalidException`.

### Return raw result diagnostic paths from exceptions

Rejected.

Raw paths may contain unsafe extension keys, unsafe exported error keys, or environment-specific data.

The accepted design stores and exposes only safe structural diagnostic paths. Unsafe paths are represented by the stable placeholder `<path>`.

### Allow floats in extensions

Rejected.

Floats create deterministic serialization and cross-platform representation risks.

Finite floats, `NaN`, `INF`, and `-INF` are all forbidden.

Use integer milliseconds, integer counts, or documented string decimal formats instead.

### Use `finishedAt - startedAt` as duration

Rejected.

Wall-clock time is not monotonic.

The accepted design uses `durationMs` measured from `Coretsia\Foundation\Time\Stopwatch`.

### Run reset before after hooks

Rejected.

After hooks must observe the completed UnitOfWork result before reset clears UnitOfWork-local state.

The accepted order is:

```text
after hooks → ResetOrchestrator.resetAll() → endUoW
```

### Skip reset when after hook throws

Rejected.

The reset guarantee is required to prevent state leakage into the next UnitOfWork.

Once the after-phase is entered, reset must run exactly once even if an after hook throws.

### Introduce reset DI tag identifiers in Kernel

Rejected.

Reset discovery and reset tag mechanics remain owned by Foundation and the tag SSoTs.

Framework-reserved DI tag identifier strings are declared in `Coretsia\Foundation\Tag\ReservedTags`.

This epic cements only the lifecycle invariant.

### Make UnitOfWorkResult a DTO by convention

Rejected.

DTO policy is explicit opt-in only through `#[Coretsia\Dto\Attribute\Dto]`.

`UnitOfWorkResult` is a Kernel runtime shape/value object, not a DTO-marker class by default.

### Add adapter-specific top-level fields

Rejected.

Top-level fields are Kernel-owned.

Adapter-specific metadata belongs under safe `extensions`.

## Non-goals

This ADR does not implement:

- UnitOfWork lifecycle executor;
- hook dispatcher;
- hook discovery;
- reset orchestration;
- reset discovery;
- reset DI tag identifier constants;
- additional code-level registries for framework-reserved DI tag identifiers;
- `TagRegistry` enumeration logic;
- reset failure aggregation policy;
- HTTP response construction;
- HTTP status code selection;
- PSR-7 integration;
- PSR-15 integration;
- CLI command execution;
- CLI output rendering;
- queue outcome mapping;
- scheduler outcome mapping;
- retry policy;
- process supervision policy;
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

This ADR does not introduce outcome mapping config keys.

This ADR does not introduce new tags.

This ADR does not introduce new artifacts.

## Verification

Expected verification includes:

```text
framework/packages/core/kernel/tests/Contract/UnitOfWorkResultShapeContractTest.php
framework/packages/core/kernel/tests/Contract/UnitOfWorkResultExtensionsAreJsonLikeContractTest.php
framework/packages/core/kernel/tests/Contract/OutcomeMappingStabilityContractTest.php
```

Verification must prove:

- result field set is stable;
- result exported key order is stable;
- `uowId` is represented as string and matches context;
- `type` accepts only `http`, `cli`, `queue`, and `scheduler`;
- `correlationId` is represented as string and matches context;
- `startedAt` is represented as integer Unix epoch milliseconds and matches context;
- `finishedAt` is represented as integer Unix epoch milliseconds;
- consumers must not rely on `finishedAt >= startedAt`;
- `durationMs` is represented as non-negative integer milliseconds;
- `durationMs` is the canonical duration source;
- `durationMs` is not derived from `finishedAt - startedAt`;
- outcome tokens are exactly `success`, `handled_error`, and `fatal_error`;
- HTTP status `< 400` maps to `success`;
- HTTP status `>= 400` maps to `handled_error`;
- HTTP uncaught exception maps to `fatal_error`;
- CLI exit code `0` maps to `success`;
- CLI exit code other than `0` without uncaught exceptions maps to `handled_error`;
- CLI uncaught exception maps to `fatal_error`;
- CLI exit code `2` without uncaught exception maps to `handled_error`;
- `fatal_error` takes precedence over status-code or exit-code mapping;
- `error` is exported as a json-like map when present;
- no `ErrorDescriptor` object crosses the export boundary;
- no Throwable object crosses the export boundary;
- `extensions` root is a map;
- extensions accept `null`, `bool`, `int`, `string`, lists, and string-keyed maps;
- extensions reject floats, including `NaN`, `INF`, and `-INF`;
- extensions reject objects;
- extensions reject closures;
- extensions reject resources;
- extensions reject non-string map keys;
- extensions reject unsafe stack traces, raw payloads, PII, and tokens;
- extensions are normalized deterministically;
- nested maps are sorted by byte-order `strcmp`;
- lists preserve order;
- result validation reason strings are whitelisted;
- unknown result validation reason strings fail deterministically;
- unsafe diagnostic paths are replaced with `<path>`;
- `UnitOfWorkResultInvalidException::path()` does not expose unsafe raw paths;
- diagnostics do not expose raw values.

`OutcomeMappingStabilityContractTest` is the single contract lock for HTTP/CLI outcome mapping rules.

Shape-specific tests are governed by:

```text
docs/ssot/uow-shapes.md
```

Outcome policy tests are governed by:

```text
docs/ssot/uow-outcome-policy.md
```

## Related SSoT

- `docs/ssot/uow-outcome-policy.md`
- `docs/ssot/uow-shapes.md`
- `docs/ssot/uow-and-reset-contracts.md`
- `docs/ssot/reset-tags.md`
- `docs/ssot/runtime-drivers.md`
- `docs/ssot/stateful-services.md`
- `docs/ssot/error-descriptor.md`
- `docs/ssot/time-ids-and-duration.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/dto-policy.md`
- `docs/ssot/tags.md`

## Related ADR

- `docs/adr/ADR-0003-observability-errordescriptor-health-profiling-ports.md`
- `docs/adr/ADR-0006-reset-interface-uow-hooks.md`
- `docs/adr/ADR-0016-clock-ids-stopwatch.md`
- `docs/adr/ADR-0019-enhanced-reset-long-running.md`
- `docs/adr/ADR-0021-unit-of-work-context-shape.md`

## Related epic

- `1.270.0 Kernel: UnitOfWork Shapes Pack (Context + Result + Outcome + SSoT invariants)`
