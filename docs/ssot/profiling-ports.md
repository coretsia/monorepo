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

# Profiling Ports SSoT

## Scope

This document is the Single Source of Truth for Coretsia profiling port semantics, profile artifact invariants, opaque payload policy, profiling redaction rules, and unit-of-work-neutral profiling boundaries.

This document governs profiling contracts introduced by epic `1.90.0` under:

```text
framework/packages/core/contracts/src/Observability/Profiling/
```

It complements:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/tags.md
docs/ssot/dto-policy.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Phase 0 lock-source alignment

Profiling metadata follows the Phase 0 json-like and redaction invariants:

- floats are forbidden;
- lists preserve order;
- maps are ordered deterministically by byte-order `strcmp`;
- raw payload values MUST NOT be logged or printed;
- diagnostics MAY expose only `hash(value)` or `len(value)`.

## Contract boundary

Profiling contracts are format-neutral and vendor-agnostic.

They define stable ports and profile artifact models only.

The contracts package MUST NOT implement:

- profilers;
- sampling engines;
- exporter backends;
- artifact writers;
- flamegraph renderers;
- HTTP middleware;
- CLI commands;
- worker hooks;
- DI registration;
- kernel hook registration.

Concrete profiling implementation belongs to future runtime owner packages.

## UoW neutrality

Profiling contracts MUST be usable by any unit of work.

Supported unit-of-work categories include:

```text
HTTP
CLI
Worker
Scheduler
Queue consumer
Custom runtime boundary
```

Profiling contracts MUST NOT assume HTTP.

Profiling contracts MUST NOT require:

- PSR-7 request objects;
- PSR-7 response objects;
- framework HTTP request objects;
- CLI concrete input/output objects;
- queue vendor message objects;
- worker vendor context objects.

Runtime packages adapt their specific boundary to the profiling ports.

## Contract package dependency policy

Profiling contracts MUST remain format-neutral.

They MUST NOT depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- PDO concrete APIs
- Redis concrete APIs
- S3 concrete APIs
- Prometheus concrete APIs
- OpenTelemetry concrete SDK APIs
- Blackfire concrete APIs
- Xdebug concrete APIs
- Tideways concrete APIs
- vendor-specific runtime clients
- framework tooling packages
- generated architecture artifacts

## DTO terminology boundary

This document uses the terms `descriptor`, `result`, `shape`, and `model` according to:

```text
docs/ssot/dto-policy.md
```

Profiling contract classes are not DTO-marker classes by default.

`ProfileArtifact` is a contracts model, not a DTO-marker class by default.

A future owner epic MAY explicitly opt a profiling model into DTO policy. Until such an epic exists, profiling models are governed by this SSoT and contracts shape rules, not DTO gate rules.

## Profiling port

`ProfilerPortInterface` is the canonical profiling boundary port.

The canonical interface shape is:

```text
start(string $uowType, array $metadata = []): ProfilingSessionInterface
```

`start()` starts profiling a unit of work or nested operation and returns a profiling session handle.

The `uowType` value MUST be safe and MUST NOT contain secrets, raw paths, raw queries, raw SQL, tokens, credentials, request/response bodies, private customer data, or absolute local paths.

`metadata` MUST be a safe json-like map.

The profiler port does not expose `stop()` directly.

Stopping belongs to the returned `ProfilingSessionInterface`.

This session-handle model supports nested or concurrent profiling without requiring an implicit global active profile on the port.

It MUST be safe to call from any runtime boundary.

It MUST NOT prescribe:

- concrete profiler backend;
- storage format;
- exporter format;
- output file paths;
- UI format;
- transport protocol;
- sampling algorithm;
- kernel hook implementation.

Profiling implementations MAY be wired by future owner packages through kernel lifecycle hooks reserved in:

```text
docs/ssot/tags.md
```

Relevant reserved tags:

```text
kernel.hook.before_uow
kernel.hook.after_uow
```

The contracts package MUST NOT declare these tags as constants.

## Profiling session interface

`ProfilingSessionInterface` is the profiling session handle returned by `ProfilerPortInterface`.

The canonical interface shape is:

```text
stop(): ?ProfileArtifact
```

`stop()` stops profiling and returns the captured artifact when available.

Calling `stop()` more than once SHOULD be noop-safe and MAY return `null` after the first completed stop.

The returned `ProfileArtifact` payload is opaque.

Consumers MUST NOT log, print, trace, use as metric labels, embed into error descriptor extensions, or expose raw payload contents through diagnostics.

## Profile artifact

`ProfileArtifact` is the canonical contracts model for a captured profiling result.

It is not a DTO-marker class by default.

The canonical logical profile artifact fields are:

```text
schemaVersion
name
metadata
payload
```

Field meanings:

| field           | meaning                                                                 |
|-----------------|-------------------------------------------------------------------------|
| `schemaVersion` | Stable profile artifact schema version.                                 |
| `name`          | Stable safe profile artifact name or implementation-owned profile kind. |
| `metadata`      | Json-like safe metadata map.                                            |
| `payload`       | Opaque implementation-owned profile payload carrier.                    |

`ProfileArtifact::toArray()` MUST return a safe public shape only.

The safe public shape MUST contain exactly these top-level fields:

```text
metadata
name
payload
schemaVersion
```

The canonical profile artifact schema version is:

```text
1
```

The `schemaVersion` field MUST be exported by `ProfileArtifact::toArray()`.

The `payload` field in `ProfileArtifact::toArray()` MUST always be `null`.

Raw opaque payload contents MAY be accessed only through the explicit payload accessor for implementation-owned profiling exporters or compatible owner-defined consumers.

Contract tests MUST verify that `ProfileArtifact::toArray()` never exports raw payload contents.

## Opaque payload law

`ProfileArtifact.payload` is opaque.

The contracts package MUST NOT define its internal schema.

Runtime consumers MUST NOT inspect the payload unless they are the owning profiling implementation or an explicit compatible exporter.

The payload MUST NEVER be used as:

- metric label;
- span name;
- span attribute;
- log field;
- error descriptor extension;
- health check output;
- user-facing diagnostics;
- deterministic public artifact metadata.

The payload MUST NEVER be logged, printed, traced, exported as raw diagnostics, or embedded into metrics.

## Payload transport policy

The opaque payload MUST be represented by a safe contract-level carrier.

The opaque payload carrier MUST be one of:

```text
string
null
```

`string` MAY contain implementation-owned opaque bytes or encoded profiler output.

`null` represents absence of an exported payload.

The payload carrier is not a public semantic schema.

The contracts package MUST NOT inspect, normalize, parse, log, or expose the payload contents.

The public contract MUST NOT expose:

- resources;
- streams;
- open filesystem handles;
- closures;
- service instances;
- vendor profiler objects;
- runtime wiring objects;
- request objects;
- response objects.

If binary or large payloads are needed, implementations SHOULD store them outside the contracts model and expose only safe metadata or implementation-owned handles that do not leak local paths or secrets.

Contracts MUST NOT require absolute filesystem paths for profile payloads.

## Metadata json-like model

`ProfileArtifact.metadata` MUST be a json-like map.

The root value MUST be a map with string keys.

Allowed metadata values are:

- `null`
- `bool`
- `int`
- `string`
- list of allowed metadata values
- map with string keys and allowed metadata values

Floating-point values are forbidden.

Metadata MUST NOT contain:

- floats;
- `NaN`;
- `INF`;
- `-INF`;
- PHP objects;
- closures;
- resources;
- streams;
- filesystem handles;
- service instances;
- runtime wiring objects;
- executable validators;
- raw request or response objects;
- raw headers;
- raw cookies;
- raw bodies;
- raw SQL;
- credentials;
- tokens;
- private customer data;
- absolute local paths.

## Metadata determinism

Profile metadata maps MUST be ordered deterministically by string key using byte-order `strcmp`.

Lists preserve semantic order.

Ordering MUST be locale-independent.

Repeated runs with the same logical metadata MUST produce the same logical metadata shape.

Metadata MUST NOT include:

- timestamps;
- random values;
- process ids;
- host machine identifiers;
- absolute local paths;
- environment-specific bytes.

If duration or size information is needed, it SHOULD be represented as integers with documented units.

Decimal values MUST be represented as strings with a documented format.

## Redaction law

Profiling contracts MUST follow the global observability redaction policy from:

```text
docs/ssot/observability.md
```

Profiling metadata and diagnostics MUST NOT leak:

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

## Exporter port

`ProfileExporterInterface` is a contracts port for exporting profile artifacts to implementation-owned sinks.

The canonical interface shape is:

```text
name(): string
export(ProfileArtifact $artifact): void
```

`name()` returns exporter stable name.

The exporter name MUST be safe, deterministic, non-empty, and suitable for diagnostics.

`export()` exports a profile artifact to an implementation-owned sink.

The exporter interface MUST remain vendor-neutral.

It MUST NOT require:

- Blackfire SDK classes;
- Xdebug classes;
- Tideways classes;
- OpenTelemetry SDK classes;
- PSR-7 objects;
- concrete filesystem abstractions;
- concrete network clients.

Exporter implementations MUST NOT expose raw profile payloads in logs, metrics, spans, error descriptors, or unsafe diagnostics.

## Profiling and metrics boundary

Profiling data MUST NOT be used as metric labels.

Profile payloads MUST NOT be transformed into high-cardinality metric dimensions.

A profiling implementation MAY emit bounded summary metrics only when:

- metric names follow `docs/ssot/observability.md`;
- label keys are allowlisted;
- label values are safe and bounded-cardinality;
- raw payloads are not exposed.

## Profiling and tracing boundary

Profiling implementations MAY create spans or span events only when:

- span names follow `docs/ssot/observability.md`;
- span attributes obey the global redaction law;
- raw profile payloads are not attached;
- raw request data and raw SQL are not attached.

Profile payloads MUST NOT be stored as span attributes.

## Profiling and errors boundary

Profiling failures MAY be mapped to `ErrorDescriptor`.

`ErrorDescriptor.extensions` MUST NOT include raw profile payloads.

Profiling error metadata MUST follow the json-like and redaction rules defined in:

```text
docs/ssot/observability-and-errors.md
```

## Hook integration policy

Profiling implementations may be wired around unit-of-work lifecycle boundaries.

Future owner packages MAY use existing kernel hook tags:

```text
kernel.hook.before_uow
kernel.hook.after_uow
```

This SSoT does not define hook metadata schema.

Hook semantics are owned by the kernel owner package.

The contracts package MUST NOT introduce profiling-specific DI tags in epic `1.90.0`.

## Security

Profiling data is considered sensitive by default.

Implementations MUST treat profile payloads as unsafe for logs and metrics.

Implementations MUST prefer omission over unsafe emission.

Implementations MUST NOT expose profiling payloads through public diagnostics unless a future owner SSoT defines an explicit safe export surface.

## Non-goals

This SSoT does not define:

- concrete profiler implementation;
- sampling algorithm;
- profile payload schema;
- flamegraph format;
- storage backend;
- file path conventions;
- HTTP middleware;
- CLI command behavior;
- worker integration;
- kernel hook metadata schema;
- DI tags;
- runtime service registration;
- generated profiling artifacts.
