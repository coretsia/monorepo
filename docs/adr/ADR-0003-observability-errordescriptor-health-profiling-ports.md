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

# ADR-0003: Observability, ErrorDescriptor, health, and profiling ports

## Status

Accepted.

## Context

Epic `1.90.0` introduces stable contracts for observability, errors, health, and profiling under:

```text
framework/packages/core/contracts/src/Observability/
```

Runtime packages need shared ports for tracing, metrics, correlation, error mapping, health checks, and profiling without depending on transport-specific APIs or vendor-specific SDKs.

The contracts package must remain a pure library boundary.

It must not depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- vendor concrete clients or SDKs
- framework tooling packages
- generated architecture artifacts

The existing SSoT baseline already defines:

```text
docs/ssot/tags.md
docs/ssot/observability.md
docs/ssot/dto-policy.md
```

This ADR records the decision to add:

```text
docs/ssot/observability-and-errors.md
docs/ssot/profiling-ports.md
```

and to use them as the policy basis for the contracts introduced by epic `1.90.0`.

## Decision

Coretsia will introduce observability, error, health, and profiling contracts as format-neutral contracts in `core/contracts`.

The contracts will define ports and models only.

They will not implement runtime behavior, DI discovery, HTTP adaptation, exporter backends, or vendor integration.

## ErrorDescriptor decision

Coretsia will introduce `ErrorDescriptor` as the canonical normalized error descriptor model.

`ErrorDescriptor` is format-neutral.

It is not a DTO-marker class by default.

Its logical field set is:

```text
schemaVersion
code
message
severity
httpStatus
extensions
```

Its exported public array field order is:

```text
code
extensions
httpStatus
message
schemaVersion
severity
```

`httpStatus` is optional and is only a transport hint.

HTTP-specific adaptation, including RFC7807/problem-details conversion, belongs to platform packages and must not leak PSR-7 into contracts.

`extensions` must be a json-like map.

`extensions` must not contain floats, throwable objects, raw payloads, raw SQL, credentials, tokens, cookies, private customer data, absolute paths, or transport objects.

## Error mapping decision

Coretsia will introduce an exception mapper port that maps `Throwable` and optional safe error handling context to `?ErrorDescriptor`.

The canonical mapper shape is:

```text
map(Throwable $throwable, ?ErrorHandlingContext $context = null): ?ErrorDescriptor
```

Mapper implementations may inspect throwables internally, but the returned descriptor must not expose the raw throwable.

Returning `null` means that the mapper does not handle the throwable and the owner registry may try the next mapper or use a fallback descriptor.

Runtime discovery of exception mappers is platform-owned through the existing reserved tag:

```text
error.mapper
```

The contracts package does not introduce or own this tag.

## Observability decision

Coretsia will introduce noop-safe observability ports for:

```text
correlation
tracing
metrics
```

These ports must remain vendor-neutral.

They must not expose PSR-7 objects, OpenTelemetry concrete SDK types, Prometheus concrete types, raw headers, raw request bodies, raw SQL, cookies, credentials, tokens, or private customer data.

Metric naming, span naming, label allowlist, and redaction rules are governed by:

```text
docs/ssot/observability.md
```

## Health decision

Coretsia will introduce health check contracts, a typed health result model, and a health status vocabulary.

The canonical health check shape is:

```text
id(): string
check(): HealthCheckResult
```

`HealthCheckResult` is a typed safe json-like result model.

It replaces loose health result arrays at the contracts boundary.

The canonical health result logical fields are:

```text
schemaVersion
status
message
details
```

Its exported public array field order is:

```text
details
message
schemaVersion
status
```

Health endpoint routing, response rendering, aggregation, and health check discovery are platform-owned.

Runtime discovery is through the existing reserved tag:

```text
health.check
```

The contracts package does not introduce or own this tag.

## Profiling decision

Coretsia will introduce vendor-agnostic profiling ports usable across HTTP, CLI, worker, scheduler, queue consumer, and custom unit-of-work boundaries.

Profiling uses a session-handle model.

The canonical profiler shape is:

```text
start(string $uowType, array $metadata = []): ProfilingSessionInterface
```

The returned `ProfilingSessionInterface` owns stopping:

```text
stop(): ?ProfileArtifact
```

This avoids an implicit global active profile on `ProfilerPortInterface` and supports nested or concurrent profiling boundaries.

`ProfileArtifact` is the canonical contracts model for profiling output.

Its safe exported public array field order is:

```text
metadata
name
payload
schemaVersion
```

The exported `payload` field is always `null`.

`ProfileArtifact.payload` is opaque.

Raw opaque payload contents MAY be accessed only through an explicit accessor by implementation-owned profiling exporters or compatible owner-defined consumers. Public safe array/export shapes MUST NOT expose the raw payload.

The contracts package does not define the payload schema.

The payload must never be logged, printed, traced, exported as metric labels, embedded into error descriptor extensions, or exposed through unsafe diagnostics.

Profiling metadata must be json-like and deterministic.

Profiling implementations may be wired by future owner packages through existing kernel hook tags:

```text
kernel.hook.before_uow
kernel.hook.after_uow
```

The contracts package does not introduce profiling-specific DI tags in epic `1.90.0`.

## Json-like payload decision

Any json-like payload exposed by contracts introduced in epic `1.90.0` must follow the Phase 0 json-like policy:

- allowed scalars are `string`, `int`, `bool`, and `null`;
- floats are forbidden, including `NaN`, `INF`, and `-INF`;
- lists preserve order;
- maps are ordered deterministically by byte-order `strcmp`;
- raw payload values must not be printed or logged;
- diagnostics may expose only safe derivations such as `hash(value)` or `len(value)`.

This applies to:

```text
ErrorDescriptor.extensions
ErrorHandlingContext safe metadata
HealthCheckResult.details
ProfileArtifact.metadata
SpanInterface attributes
SpanInterface event attributes
TracerPortInterface attributes
SamplerInterface attributes
any future json-like observability metadata introduced by this epic
```

## DTO boundary decision

Descriptor, result, shape, and context models introduced by this epic are not DTO-marker classes by default.

DTO policy remains explicit opt-in only through:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Contract tests for descriptor shape enforce the shape directly.

DTO gates do not own these models unless a future epic explicitly opts them into DTO policy.

## Consequences

Runtime packages can depend on stable contracts without depending on transport-specific or vendor-specific APIs.

HTTP packages can adapt `ErrorDescriptor` to problem-details without changing contracts.

CLI and worker packages can reuse the same error and profiling ports without HTTP coupling.

Profiling can be added later through platform-owned implementations and kernel hook integration without changing the contracts.

The contracts package remains format-neutral and dependency-light.

## Rejected alternatives

### Put PSR-7 types in error or tracing contracts

Rejected.

PSR-7 would make the contracts HTTP-specific and would leak transport concerns into non-HTTP runtimes.

### Make ErrorDescriptor an RFC7807 model

Rejected.

RFC7807 is an HTTP/problem-details representation.

`ErrorDescriptor` is a format-neutral normalized error descriptor.

### Put vendor tracing or metrics SDKs in contracts

Rejected.

Vendor SDK types would make the contracts package depend on implementation choices and would prevent noop-safe runtime-agnostic use.

### Treat profile payload as a public schema

Rejected.

Profile payloads are implementation-owned and may be large, sensitive, binary, or vendor-specific.

Only safe metadata belongs at the contracts boundary.

### Introduce new DI tags in contracts

Rejected.

DI tag ownership is defined by `docs/ssot/tags.md`.

This epic references existing reserved tags as runtime policy only.

## Cross-references

- [Observability Naming and Labels Allowlist](../ssot/observability.md)
- [Observability and Errors SSoT](../ssot/observability-and-errors.md)
- [Profiling Ports SSoT](../ssot/profiling-ports.md)
- [Tag Registry](../ssot/tags.md)
- [DTO Policy](../ssot/dto-policy.md)
