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

# Observability and Errors SSoT

## Scope

This document is the Single Source of Truth for Coretsia observability contracts, error descriptor boundary policy, error mapping semantics, redaction invariants, and format-neutral error handling boundaries.

This document governs contracts introduced by epic `1.90.0` under:

```text
framework/packages/core/contracts/src/Observability/
```

It complements:

```text
docs/ssot/error-descriptor.md
docs/ssot/errors-boundary.md
docs/ssot/observability.md
docs/ssot/tags.md
docs/ssot/dto-policy.md
docs/ssot/profiling-ports.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Phase 0 lock-source alignment

This SSoT preserves the following Phase 0 invariants:

- `0.70.0` json-like payloads forbid floats, including `NaN`, `INF`, and `-INF`.
- `0.70.0` list order is preserved and map ordering is deterministic.
- `0.90.0` safe diagnostics do not expose raw values.
- `0.20.0` no-secrets output policy applies to diagnostics and observability output.

## Contract boundary

Observability and error contracts are format-neutral.

They define stable ports, enums, descriptor models, context models, and invariants only.

The contracts package MUST NOT implement:

- tracing backends;
- metrics backends;
- loggers;
- error handler runtime behavior;
- exception mapper registries;
- HTTP problem-details adaptation;
- health endpoint routing;
- profiling exporters;
- DI registration;
- runtime discovery;
- generated observability artifacts.

Runtime owner packages implement concrete behavior later.

## Contract package dependency policy

The observability and error contracts MUST remain format-neutral.

They MUST NOT depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- PDO concrete APIs
- Redis concrete APIs
- S3 concrete APIs
- Prometheus concrete APIs
- OpenTelemetry concrete SDK APIs
- vendor-specific runtime clients
- framework tooling packages
- generated architecture artifacts

## DTO terminology boundary

This document uses the terms `descriptor`, `result`, `shape`, and `model` according to:

```text
docs/ssot/dto-policy.md
```

Observability and error contract classes are not DTO-marker classes by default.

A future owner epic MAY explicitly opt a class into DTO policy. Until such an epic exists, observability descriptors, error descriptors, context models, and result models are governed by this SSoT and contracts shape rules, not DTO gate rules.

## Json-like value model

Any json-like payload exposed by observability and error contracts MUST contain only:

- `null`
- `bool`
- `int`
- `string`
- list of allowed values
- map with string keys and allowed values

Floating-point values are forbidden.

The following values MUST NOT appear in exported json-like contract shapes:

- floats
- `NaN`
- `INF`
- `-INF`
- PHP objects
- closures
- resources
- streams
- filesystem handles
- service instances
- runtime wiring objects
- executable validators
- throwable instances

If a future owner needs decimal values, they MUST be represented as strings with a documented format.

## Json-like container determinism

Lists preserve order.

Maps MUST be ordered deterministically by string key using byte-order `strcmp`.

Map ordering MUST be locale-independent.

Implementations that expose json-like maps SHOULD normalize map ordering recursively before export, serialization, rendering, or diagnostics.

An empty array is context-dependent:

- if the contract location requires a list, `[]` is treated as an empty list;
- if the contract location requires a map, `[]` is treated as an empty map at the semantic boundary;
- serialized PHP array representations may not preserve empty list vs empty map distinction.

Contracts MUST document the expected context for any ambiguous empty array field.

## Raw payload redaction rule

Implementations MUST NOT log, print, export, trace, or render raw json-like payload values for diagnostics.

Diagnostics MAY expose only safe derivations:

```text
hash(value)
len(value)
```

Safe derivations MUST NOT expose raw values, secrets, PII, request bodies, response bodies, profile payloads, or raw persistence payloads.

## Observability naming and labels

Observability naming, metric label allowlist, span naming, and global redaction policy are governed by:

```text
docs/ssot/observability.md
```

Metric label keys MUST stay within the canonical allowlist unless that SSoT is directly updated.

The baseline allowed metric label keys are:

```text
method
status
driver
operation
table
outcome
```

Forbidden label keys include:

```text
field
path
property
request_id
correlation_id
tenant_id
user_id
```

Correlation identifiers MAY exist for tracing or log correlation, but they MUST NOT become canonical metric labels under the baseline policy.

## Correlation id provider port

`CorrelationIdProviderInterface` is a contracts port for obtaining a safe correlation identifier.

The port MUST NOT prescribe:

- HTTP header names;
- PSR-7 request objects;
- framework request objects;
- storage mechanism;
- generator algorithm;
- propagation format.

A correlation identifier returned by the port MUST be safe to use for correlation.

A correlation identifier MUST NOT be used as a metric label unless a future SSoT explicitly allows it.

## Tracer port

`TracerPortInterface` is a contracts port for starting and managing spans.

The tracing contracts MUST remain vendor-agnostic.

The canonical interface shape is:

```text
startSpan(string $name, array $attributes = []): SpanInterface
inSpan(string $name, callable $callback, array $attributes = []): mixed
currentSpan(): ?SpanInterface
```

`startSpan()` starts a span with safe json-like attributes.

`inSpan()` runs code under a span and MUST end that span in a `finally` block.

If the callback passed to `inSpan()` throws, the implementation MUST re-throw the original throwable after applying safe exception-recording policy.

`currentSpan()` returns the current span for the runtime boundary, or `null` when no span is active or the tracer implementation is no-op.

Tracing contracts MUST NOT expose:

- OpenTelemetry SDK classes;
- vendor span classes;
- PSR-7 request or response objects;
- raw headers;
- raw bodies;
- raw paths;
- raw queries;
- raw SQL.

Span names MUST follow `docs/ssot/observability.md`.

Span attributes MUST obey the global redaction law.

## Span interface

`SpanInterface` is a contracts abstraction for a currently active or completed span.

It MUST expose only stable operations needed by runtime packages.

The canonical interface shape is:

```text
name(): string
setAttribute(string $key, mixed $value): void
setAttributes(array $attributes): void
addEvent(string $name, array $attributes = []): void
recordException(Throwable $throwable, array $attributes = []): void
end(): void
```

`setAttribute()` and `setAttributes()` accept safe json-like values.

`addEvent()` accepts a safe event name and safe json-like event attributes.

`recordException()` records an exception safely.

Implementations MUST NOT export stack traces, raw Throwable messages, raw payloads, raw SQL, credentials, tokens, cookies, request/response bodies, or profile payloads by default.

`end()` ends the span.

Calling `end()` more than once SHOULD be noop-safe.

`SpanInterface` MUST NOT expose vendor-specific span handles.

It MUST NOT expose raw payloads, raw request data, raw SQL, headers, cookies, tokens, or credentials.

## Context propagation interface

`ContextPropagationInterface` is a format-neutral propagation port.

It MAY operate on scalar/list/map carrier shapes.

It MUST NOT require:

- PSR-7 requests;
- PSR-7 responses;
- framework HTTP request objects;
- vendor propagation carriers.

Carrier keys and values MUST be safe strings or documented json-like scalar values.

Propagation MUST NOT leak secrets or raw headers as observability payload.

## Span exporter interface

`SpanExporterInterface` is a contracts port for exporting spans to an implementation-owned backend.

The canonical interface shape is:

```text
export(iterable $spans): void
```

`$spans` MUST contain only `SpanInterface` instances.

The interface MUST remain vendor-neutral.

Exporter implementations MUST apply redaction before emission.

Exporter implementations MUST NOT export raw payload values unless a future owner SSoT explicitly allows a safe shape.

## Sampler interface

`SamplerInterface` is a contracts port for deciding whether a span or trace should be sampled.

The canonical interface shape is:

```text
shouldSample(string $spanName, array $attributes = []): SamplingDecision
```

`attributes` MUST be a safe json-like map.

The canonical sampling decision model is `SamplingDecision`.

The canonical sampling decision values are:

```text
record
drop
defer
```

Decision meanings:

| decision | meaning                                                                                                  |
|----------|----------------------------------------------------------------------------------------------------------|
| `record` | The span or trace SHOULD be recorded by the implementation.                                              |
| `drop`   | The span or trace SHOULD NOT be recorded by the implementation.                                          |
| `defer`  | The sampler does not make a final decision and the implementation MAY apply its default sampling policy. |

Sampling decision values MUST be lowercase ASCII strings.

Sampling decision values MUST be compared byte-for-byte.

Sampling decisions MUST be deterministic for the same declared inputs unless the implementation explicitly documents probabilistic sampling policy outside contracts.

Sampling inputs MUST NOT include raw request bodies, raw response bodies, tokens, credentials, or raw SQL.

## Meter port

`MeterPortInterface` is a contracts port for emitting metrics.

Metric names MUST follow `docs/ssot/observability.md`.

Metric labels MUST use only allowlisted label keys.

Metric label values are safe bounded scalar labels, not generic json-like payloads.

Metric label values MUST be one of:

```text
string
int
bool
```

`null` is not a valid metric label value.

If a label is not applicable, the label key MUST be omitted instead of emitted with `null`.

Metric labels MUST NOT use lists, maps, floats, `null`, objects, resources, closures, raw payloads, or transport/runtime objects.

Metric label values MUST be safe and bounded-cardinality.

Metric labels MUST NOT include:

- raw path;
- raw query;
- headers;
- cookies;
- body;
- auth identifiers;
- session identifiers;
- tokens;
- raw SQL;
- profile payloads;
- arbitrary user identifiers.

## Metrics renderer interface

`MetricsRendererInterface` is a contracts port for rendering implementation-owned metric state into an output representation.

The canonical interface shape is:

```text
contentType(): string
render(): string
```

`contentType()` returns the rendered metrics content type.

The content type value is implementation-owned and MUST NOT require concrete vendor classes.

`render()` returns implementation-owned metric state as a string.

The returned string MUST NOT contain secrets, raw payloads, raw SQL, tokens, cookies, private customer data, profile payloads, or other unsafe diagnostics.

The interface MUST remain vendor-neutral.

It MUST NOT require Prometheus classes or any vendor concrete renderer.

Rendering format is implementation-owned.

Contracts MUST NOT require a concrete wire format.

## Error descriptor

`ErrorDescriptor` is the canonical contracts descriptor model for normalized errors.

It is not a DTO-marker class by default.

It MUST be format-neutral.

The single human-readable field-by-field reference for `ErrorDescriptor` is:

```text
docs/ssot/error-descriptor.md
```

This SSoT records the observability/error boundary, payload safety, redaction policy, and port relationships only.

This SSoT MUST NOT redefine a competing field-by-field `ErrorDescriptor` schema.

`ErrorDescriptor` MUST NOT expose:

- raw `Throwable` objects;
- stack traces by default;
- request objects;
- response objects;
- PSR-7 objects;
- raw headers;
- cookies;
- auth tokens;
- session identifiers;
- raw SQL;
- raw payloads;
- profile payloads;
- credentials;
- private customer data.

Problem-details adaptation is platform-owned and MUST NOT be implemented in `core/contracts`.

HTTP adapters MAY use `ErrorDescriptor` through the boundary policy defined in:

```text
docs/ssot/errors-boundary.md
```

## Exception mapper interface

`ExceptionMapperInterface` is a contracts port for mapping `Throwable` to `ErrorDescriptor`.

The canonical interface shape is:

```text
map(Throwable $throwable, ?ErrorHandlingContext $context = null): ?ErrorDescriptor
```

The mapper MAY inspect a throwable internally.

The mapper MAY use safe context metadata when available.

Returning `null` means this mapper does not handle the throwable and the owner registry may try the next mapper or use its fallback descriptor.

The returned descriptor MUST NOT expose the raw throwable.

Mapper discovery is runtime policy owned by `platform/errors` through the existing reserved tag:

```text
error.mapper
```

The contracts package MUST NOT declare this tag as a constant.

## Error reporter port

`ErrorReporterPortInterface` is a contracts port for reporting normalized errors.

The canonical interface shape is:

```text
report(ErrorDescriptor $descriptor, ?ErrorHandlingContext $context = null): void
```

It SHOULD accept `ErrorDescriptor` and safe context.

Reporter implementations MUST preserve redaction policy.

Reporter implementations MUST NOT emit raw throwable payload, raw request payload, raw SQL, credentials, tokens, cookies, or profile payloads.

## Error handler interface

`ErrorHandlerInterface` is a contracts port for handling errors at runtime boundaries.

It MUST remain format-neutral.

It MUST NOT require:

- PSR-7 request objects;
- PSR-7 response objects;
- framework HTTP request objects;
- framework HTTP response objects;
- CLI concrete output implementations;
- worker concrete message objects.

Runtime packages adapt their transport-specific error handling to this contract.

## Error handling context

`ErrorHandlingContext` is a contracts context model for safe error handling metadata.

It is not a DTO-marker class by default.

It MUST contain only safe deterministic context data.

It MUST NOT contain:

- raw transport objects;
- request or response objects;
- raw headers;
- raw cookies;
- raw bodies;
- raw SQL;
- credentials;
- tokens;
- session identifiers;
- profile payloads;
- private customer data;
- absolute local paths.

The canonical safe public array shape for `ErrorHandlingContext` contains
exactly these top-level fields in deterministic byte-order key order:

```text
correlationId
metadata
operation
```

`metadata` MUST be a json-like map.

## Health contracts

Health contracts under:

```text
framework/packages/core/contracts/src/Observability/Health/
```

define health check port shape, typed health result shape, and health status vocabulary only.

The canonical `HealthCheckInterface` shape is:

```text
id(): string
check(): HealthCheckResult
```

`id()` returns the stable safe health check id.

`check()` executes the check and returns the normalized health result.

The canonical `HealthCheckResult` shape is:

```text
schemaVersion(): int
status(): HealthStatus
message(): ?string
details(): array<string,mixed>
toArray(): array<string,mixed>
```

`HealthCheckResult` is a safe json-like result model.

The canonical health result schema version is:

```text
1
```

The canonical exported `HealthCheckResult::toArray()` field order is:

```text
details
message
schemaVersion
status
```

`details` MUST be a json-like map.

`details` MUST NOT contain floats, PHP objects, closures, resources, streams, service instances, runtime wiring objects, raw credentials, tokens, cookies, raw SQL, request/response bodies, profile payloads, private customer data, or absolute local paths.

The canonical health status values are:

```text
pass
warn
fail
```

Status meanings:

| status | meaning                                            |
|--------|----------------------------------------------------|
| `pass` | The checked component is healthy.                  |
| `warn` | The checked component is usable but degraded.      |
| `fail` | The checked component is unhealthy or unavailable. |

Status values MUST be lowercase ASCII strings.

Status values MUST be compared byte-for-byte.

Health endpoint representation is platform-owned and MUST NOT be implemented in `core/contracts`.

Health endpoint routing is platform-owned.

Health check discovery is runtime policy owned by `platform/health` through the existing reserved tag:

```text
health.check
```

The contracts package MUST NOT declare this tag as a constant.

## Noop-safe port policy

Observability ports MUST be safe to implement as no-op adapters.

A no-op implementation MUST be valid for:

- tracing;
- metrics;
- correlation;
- error reporting;
- profiling integration boundaries.

No-op implementations MUST NOT require backend configuration.

No-op implementations MUST preserve redaction policy.

No-op implementations MUST NOT throw merely because no vendor backend is installed.

## Runtime discovery policy

Runtime discovery tags are reserved in:

```text
docs/ssot/tags.md
```

This epic does not introduce DI tags.

Contracts MAY reference existing reserved tags in documentation as runtime policy, but MUST NOT own tag constants or tag schemas.

## Security and redaction

Observability and error contracts MUST NOT require storing secrets.

Contracts and implementations MUST NOT leak:

- `.env` values;
- passwords;
- credentials;
- tokens;
- private keys;
- cookies;
- authorization headers;
- session identifiers;
- request bodies;
- response bodies;
- raw SQL;
- profile payloads;
- private customer data;
- absolute local paths.

Safe implementation diagnostics MAY use:

```text
hash(value)
len(value)
```

Raw values MUST NOT be printed or logged.

## Non-goals

This SSoT does not define:

- concrete tracing backend implementation;
- concrete metrics backend implementation;
- concrete logger implementation;
- concrete exception mapper registry implementation;
- HTTP problem-details renderer;
- PSR-7 integration;
- health endpoint routing;
- DI tags;
- DI registration;
- generated artifacts;
- profiling payload schema;
- CLI command behavior.
