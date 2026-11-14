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

# ADR-0037: Platform logging PSR-3 backend and sink safety

```yaml
adrVersion: 1
status: pre-accepted
owner: platform/logging
```

## Context

Coretsia already provides the PSR-3 `LoggerInterface` through Foundation's `NoopLogger`, a canonical target-local `ModulePlan`, Foundation declarative container definitions, read-only UoW/correlation ports, Foundation `StableJsonEncoder`, and shared sensitive-data redaction owned by `platform.redaction`.

The baseline runtime needs useful diagnostic logging in all canonical presets, beginning with `micro`, without making `LoggerInterface` dependent on HTTP, CLI, Worker, Errors, platform-specific middleware, vendor logging SDKs, or transport-specific request data.

Runtime operators need a stable STDERR backend and a constrained daily local-file option while preserving target-local module exclusion, deterministic records, mandatory redaction, safe no-throw failure handling, and existing Foundation ownership.

HTTP request completion is produced by `platform/http` through the existing PSR-3 `LoggerInterface`, using canonical bounded completion values for both finalized responses and failures without a finalized response. When no finalized response exists, the producer supplies `status='unknown'` and `outcome='fatal_error'`. Logging admits these operational fields without importing HTTP implementations or interpreting request lifecycle state. `route_template` is a fixed producer-supplied context value; an independently admitted `path_template` can be enriched from the current canonical context.

A generic redactor cannot prove that arbitrary producer data is safe for logging.

Its shared classifier recognizes a fixed high-confidence vocabulary but may return `null` for unclassified keys.

Log emission therefore needs a separate canonical destination-specific sink-field policy, bounded operational values, validated key paths, and a strict post-redaction check.

Context lifetime remains Kernel/Foundation-owned; a logger must not retain identifiers across units of work or restore completed context from caller-supplied values.

The design must not embed process resources, filesystem paths, or `php://stderr` values in source or compiled container definitions.

File availability may change after logger construction and therefore cannot be made a static configuration validity condition.

The complete normative field schema and operational limits are owned by `docs/ssot/logging-policy.md` rather than duplicated in this decision record.

## Decision

### Decision 1: Keep the Foundation PSR-3 binding and select one real runtime module

`coretsia/framework` includes `coretsia/platform-logging` as a baseline Composer dependency.

The canonical `micro`, `express`, `hybrid`, and `enterprise` presets select `platform.logging` in their `modules` lists, not their `required` lists.

Installed packages do not auto-enable runtime modules; the target-local Kernel `ModulePlan` remains the sole selection authority.

`FoundationServiceProvider` retains its existing `Psr\Log\LoggerInterface` to `Coretsia\Foundation\Logging\NoopLogger` binding.

When the logging provider is present later in the selected provider plan, its declarative binding overrides Foundation's logger using normal Foundation/Kernel later-binding precedence.

Excluding `platform.logging` through `moduleOverrides.exclude` preserves the Foundation binding without uninstalling the Composer package.

Selecting the logging module with the string driver `null` returns the same Foundation `NoopLogger` after complete configuration validation.

A missing selected logging module, an excluded active dependency, or another module-planning conflict follows canonical Kernel fail-closed semantics; it is not a signal to silently disable logging.

The selected module requires `core.foundation`, `core.kernel`, and `platform.redaction`.

No module-discovery, Composer-installation, mode-selection, or provider-ordering mechanism is added.

### Decision 2: Reuse the existing contracts-level context and app-target ports

`LoggingServiceFactory::createLogger()` resolves the selected canonical app target from `AppTargetProviderInterface::app()`, supplied by the existing Kernel `ModulePlan` through its declarative runtime-seed binding.

Only `web`, `api`, `console`, and `worker` are accepted.

No logging-specific app-target resolver, environment inference, CLI inference, mutable registry, or Kernel implementation source import is introduced.

`logging.service_name='auto'` uses that target as the emitted `service_name`.

An explicit bounded safe name matching `[a-z][a-z0-9_-]{0,63}` overrides only the service label, not `app_target`.

Service identity is captured once at construction, appears as required canonical root fields, and never comes from caller context or the current UoW.

`ContextBagProcessor` reads the current `ContextAccessorInterface` and `CorrelationIdProviderInterface` when a record is admitted.

Outside an active UoW, `correlation_id`, `uow_id`, and `uow_type` remain structurally present with `null` values; a missing or throwing optional context read does not fail logging.

`ContextStore` and Kernel reset orchestration remain authoritative.

Neither logger nor processor caches UoW identifiers, copies runtime context between invocations, or implements a reset hook.

### Decision 3: Own one logging sink admission policy and reuse shared security ports

`LogRecordPolicy` owns the single producer-neutral admission policy for the canonical nine-field root schema, event-token grammar, bounded operational context, safe key-path admission, and post-redaction validation. The exact field grammars and limits are normative in `docs/ssot/logging-policy.md`.

Its context policy admits the ten exact uppercase `method` values `GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `TRACE`, `CONNECT`, and `OTHER`; `status` as either a strict integer `100..599` or a bounded category string; and `route_template` only as the exact string `unknown`. Existing `outcome` category, integer `duration_ms`, and optional nested identifier bounds apply independently. The sink never derives or cross-validates HTTP status/outcome semantics.

Producer-supplied `context.route_template='unknown'` is distinct from optional `context.path_template` enrichment by `ContextBagProcessor` through `ContextAccessorInterface` and `ContextKeys::PATH_TEMPLATE`. Both fields are independently validated, and neither is copied into the other. Logging reads no HTTP request or route metadata to populate `route_template`.

The sink admits only producer-owned safe-by-construction operational summaries; it rejects arbitrary raw transport data, SQL, secrets, PII, objects, Throwables, and unsafe messages. Syntactic admission does not prove provenance, and producers retain responsibility for safe static events, bounded values, and exclusion of arbitrary request-derived information.

`core/contracts` provides `SensitiveDataRedactorInterface` and the additional read-only `SensitiveKeyClassifierInterface`, returning `?RedactionKind`.

The default `platform.redaction` provider exposes the classifier through a declarative alias to the same immutable `SensitiveKeyClassifier` instance already injected into `DefaultSensitiveDataRedactor`.

The canonical 98-alias vocabulary remains owned by `platform.redaction`: it is neither moved into contracts nor copied into logging.

A classifier `null` result means *unclassified*, not *admitted*.

For `key_paths`, logging applies its own structural restrictions, classifies the complete dotted path, each segment, and structural-placeholder components.

Classified sensitive paths reject the entire branch.

Producer-specific checks such as Kernel's existing sensitive-key and SQL-like restrictions remain with their original producers and are not copied into `LogRecordPolicy`.

Source-package-specific or event-name-specific sink exceptions are forbidden.

Initial caller context exceeding 48 entries is omitted as a whole without necessarily rejecting the log event. After optional canonical `path_template` enrichment, the complete assembled record is validated again: an over-48-field context invalidates the record before redaction. Valid canonical records passing instance-bound sensitive `key_paths` checks reach one mandatory `SensitiveDataRedactorInterface::redactJsonLike()` call using `RedactionContext('logging.record', RedactionMode::Placeholder)`.

Post-redaction validation retains valid canonical roots and independently rechecks `key_paths`; ordinary invalid context fields are omitted without restoring original values, and a redactor-produced context exceeding 48 fields becomes `null` as a whole when sensitive-key validation succeeds. An invalid non-null context container, malformed root, invalid/sensitive `key_paths`, classifier exception, or redaction failure rejects the entire record.

`StreamLogger` and both canonical formatters retain one admission and deterministic serialization path for all producers, with integer/string context types preserved after redaction. No unredacted, partial, hashed, length-derived, or alternative-sink fallback exists. There is no logging redaction-disable setting, second generic classifier, or additional redaction runtime module.

### Decision 4: Select one deterministic formatter over one admitted canonical record

The supported `logging.format` values are exactly `jsonl` (default) and `logfmt`.

Formatter selection occurs once during logger construction; it does not select another logging backend, alter provider order, or add a runtime formatter registry.

Both `JsonLineFormatter` and `LogfmtLineFormatter` consume the same complete admitted and redacted canonical nine-field record.

They reuse `LogRecordPolicy::isValidCanonicalRecord()` for structural validation rather than maintaining different sink schemas.

JSONL delegates canonical recursive map ordering, JSON encoding, and final-LF framing to Foundation `StableJsonEncoder`.

Logfmt emits the same root fields as deterministic `key=value` pairs in the specified order, with double-quoted escaped strings and unquoted `null` values.

A non-empty context is encoded through Foundation `StableJsonEncoder` as compact JSON, with only its encoder-supplied final LF removed before embedding as one escaped quoted logfmt value.

Logfmt appends exactly one final LF of its own.

Neither format introduces an additional recursive JSON encoder, context schema, redactor, or admission policy.

`logging.max_record_bytes` sets the effective serialized-frame limit in the inclusive range `1024..65536`, and `StreamLogger::MAX_RECORD_BYTES = 65536` is the immutable ceiling.

The final LF participates in byte accounting for both formats.

Oversized records are omitted after formatting and before I/O; they are never truncated or retried.

These limits govern a single record, not total file size.

### Decision 5: Fix the stream destination to STDERR, with no public stream URI

The string enum `logging.driver` supports exactly `stream`, `file`, and `null`; PHP `null` is invalid.

Stream mode always uses the internal `php://stderr` destination.

`logging.stream` is not a recognized configuration key, and callers cannot provide arbitrary stream URIs, writable stream resources, or alternative destination registries through the logger's public configuration.

`LoggingServiceProvider::define()` produces deterministic declarative source definitions with the seven required factory service references in exact positional order:

```text
ConfigRepositoryInterface
ContextAccessorInterface
CorrelationIdProviderInterface
SensitiveDataRedactorInterface
SensitiveKeyClassifierInterface
ClockInterface
AppTargetProviderInterface
```

`LoggingServiceFactory` reads validated merged configuration through `ConfigRepositoryInterface` during logger construction.

Source and compiled definitions contain neither `logging.config` destination blobs nor `php://stderr`, file paths, open handles, selected dates, runtime resources, or closures.

The same provider definition is valid for either active driver and either format; there is no imperative source-only wiring path.

Failure to open the *fixed* STDERR destination during initialization returns the existing Foundation `NoopLogger` without diagnostics.

A short or failed subsequent write discards that record without a second write, raw fallback, or permanent mutable-disable flag.

Invalid configuration is a distinct bootstrap error, never an automatic Noop fallback.

Active `StreamLogger` rejects invalid PSR-3 log levels through `Psr\Log\InvalidArgumentException`; the existing Foundation noop semantics are unchanged.

### Decision 6: Provide daily UTC files as a destination mode, not another logger

`driver='file'` uses the same `StreamLogger`, formatter selection, `LogRecordPolicy`, shared redactor/classifier, ContextBagProcessor, severity filtering, and byte limits.

A `FileLogger`, second admission pipeline, separate service-provider binding, generic filesystem service, or reusable lock abstraction is not introduced.

The required `logging.file` map contains only `path` and `name`.

`file.name` is a bounded extension-free stem matching `[a-z][a-z0-9_-]{0,63}` and is preserved verbatim after validation.

`file.path` is an administrator-controlled absolute local directory; Kernel declarative validation owns its type, while `LoggingServiceFactory` checks driver-specific local path syntax and normalization without probing the filesystem.

A syntactically valid directory may not exist when a logger is constructed.

Every complete eligible file emission resolves directory availability anew and selects this exact basename:

```text
{app_target}-{file.name}-{YYYY-MM-DD}.{format}
```

The canonical `app_target`, not the potentially overridden `service_name`, is the filename prefix.

The selected format supplies the extension (`jsonl` or `logfmt`).

The date comes from the final validated and redacted UTC record timestamp.

Only when `ts_utc=null` does a separate invocation-local `gmdate('Y-m-d')` supply the filename date; that fallback never changes the canonical record or invokes redaction again.

The logger does not cache a date or daily file handle, so UTC rollover is automatic without restart.

For each eligible record the logger checks the existing canonical directory, denies detectable symlink and non-regular destinations, opens the daily file in append-binary mode, validates the opened file handle, takes `flock(LOCK_EX | LOCK_NB)` on that same handle, attempts one bounded `fwrite()`, and releases the lock/handle on exit.

Failure to find or write the destination, including non-blocking lock contention, discards the current record without diagnostic leakage or switching to NoopLogger.

Future emissions may recover once the directory becomes available.

No directory, separate lock file, temporary artifact, or retry queue is created for this purpose.

The deployment administrator must prevent untrusted modification of the directory and destination files.

PHP-level symlink and file-handle checks do not guarantee race-free protection against adversarial concurrent changes.

There is no durable `fsync` guarantee, guaranteed multi-process atomicity on unsupported filesystems, or ability to roll back a short write.

External operations own retention, archival, deletion, rotation outside this logger, disk quotas, and total log-file size.

Daily files are runtime output, not Kernel build artifacts or compiled manifests.

### Decision 7: Avoid recursive instrumentation and preserve internal failure isolation

Logging itself does not start a `logging.write` span, emit per-record metrics, report its own errors, or register reset services.

Tracer/exporter or metrics adapters may use `LoggerInterface`; instrumentation from inside that interface risks unbounded recursive reporting.

Existing producer and operation-boundary observability remains authoritative.

Severity rejection and initial admission happen before optional context reads or clock access.

Classifier failure, mandatory redaction failure, invalid post-redaction shape, formatter failure, oversized frame, destination unavailability, locking failure, and write failure are isolated without caller-visible internal exceptions or raw diagnostics.

A clock failure yields a canonical `null` timestamp and does not interrupt logging.

Invalid PSR-3 levels remain explicit caller API errors rather than an internal failure to suppress.

No internal failure may change the caller's primary business exception, silently switch to a raw debugging sink, or trigger recursive self-logging.

## Consequences

### Positive

- Canonical presets have one selectable real PSR-3 backend while Foundation remains independently usable when logging is excluded or disabled.
- Both active destinations share one admitted/redacted record and one bounded single-write pipeline; both formats retain the same canonical service and UoW semantics.
- Source/compiled DI definitions remain deterministic and resource-free, with Kernel retaining module selection, app-target identity, and config ownership.
- The shared classifier vocabulary and redactor implementation retain a single owner; logging applies one producer-neutral sink policy to HTTP completion events and other approved operational events without coupling to HTTP runtime implementations.
- A temporarily unavailable daily file directory can recover on a later emission without rebuilding the logger or adding mutable failure state.

### Trade-offs

- Conservative sink admission deliberately drops unsupported caller context, so logging is not a general-purpose dump of PSR-3 context. Syntax and type validation cannot establish producer provenance, and HTTP status/outcome consistency remains the producer's responsibility.
- A failed or short write is discarded without retries; previously written partial bytes may remain and multi-process framing is not universally atomic.
- Deployment is responsible for trusted directory permissions and file lifecycle; PHP checks alone cannot defend against an adversarial filesystem actor.
- The fixed STDERR stream offers no public arbitrary-destination configuration. More elaborate transports belong to a separately specified future capability, not this module.

## Rejected alternatives

### Replace Foundation's logger or automatically enable the installed package

Changing the baseline Foundation binding or treating Composer installation as module activation would break independent Foundation use, target-local exclusion, canonical module planning, and missing-module fail-closed behavior.

### Add another LoggerInterface, FileLogger, or destination-specific record pipeline

Separate logger implementations, HTTP-specific formatters, event-name-specific admission exceptions, or admission pipelines would drift on redaction, correlation fields, severity, format, byte limits, and error isolation.

The existing `StreamLogger` owns both destinations and accepts HTTP completion records through the same PSR-3 sink boundary.

### Configure arbitrary stream URIs or inject a generic stream-opener service

An arbitrary URI widens the destination attack surface and risks embedding runtime resources in declarative definitions.

Fixed STDERR and factory-owned file-path policy are sufficient for the required backends.

Tests simulate I/O failures in isolated processes without changing the production API.

### Duplicate the shared sensitive-key vocabulary or rely on redaction alone

Generic classifiers cannot establish field provenance or destination-specific safety.

Copying the platform redaction aliases or Kernel producer patterns would create multiple authorities.

Logging owns a bounded sink schema and delegates only shared semantic classification/redaction to existing security ports.

### Give logfmt an independent canonical schema or recursive JSON encoder

A second schema or JSON encoder would make two formats semantically divergent.

Both formatters reuse the canonical record policy and Foundation `StableJsonEncoder`.

### Rotate files by a configurable policy or manage their retention inside logging

Runtime-owned retention, compression, deletion, storage quotas, or configurable rotation would require additional mutable state and deployment policy.

The only built-in partition is the UTC calendar day, determined at emission time.

### Check file existence during logger construction or permanently disable a failing sink

Filesystem availability changes independently of validated configuration.

Early probing or permanent-disable state would prevent missing-directory recovery and conflate malformed configuration with emission-time I/O failure.

### Add per-record logging spans, metrics, or reset hooks

These mechanisms would create recursive observability paths or duplicate Foundation/Kernel UoW state and reset ownership.

## Non-goals

- HTTP access-log middleware, CLI rendering, Worker-specific event production, request inspection, or transport-level error presentation.
- New PSR-3 interfaces, classifier registries, redaction modes, generic JSON encoders, or correlation-id generators.
- New module discovery, mode preset selection, config validation rule types, runtime artifacts, or container compilation mechanisms.
- Filesystem race-free guarantees, atomic recovery of partial writes, external log rotation, archival, retention, deletion, or disk-quota management.
- Arbitrary network, vendor SDK, or remotely configured logging destinations.

## Related SSoT

- `docs/ssot/logging-policy.md` — exact canonical record schema, sink admission, context bounds, formats, and destination behavior.
- `docs/ssot/sensitive-data-redaction.md` — shared classification, redaction context, failure isolation, and immutable security vocabulary.
- `docs/ssot/json-like-runtime-values.md` — Foundation json-like values and stable JSON serialization.
- `docs/ssot/context-lifecycle.md` and `docs/ssot/context-keys.md` — UoW context and reset ownership.
- `docs/ssot/observability.md` and `docs/ssot/observability-and-errors.md` — safe diagnostic output and instrumentation ownership.
- `docs/ssot/config-roots.md` — package-owned logging configuration root.
- `docs/ssot/runtime-container-definitions.md` and `docs/ssot/compiled-container.md` — declarative definition and compiled parity requirements.
- `docs/ssot/modes.md`, `docs/ssot/modules-and-manifests.md`, and `docs/ssot/application-dependency-sync.md` — preset, selected module, and Composer package boundaries.
- `docs/architecture/DEPENDENCIES.md` — production source dependency boundaries.

## Related ADRs

- `docs/adr/ADR-0001-module-descriptor-manifest-modepreset-ports.md`
- `docs/adr/ADR-0006-reset-interface-uow-hooks.md`
- `docs/adr/ADR-0010-sensitive-data-redaction-boundary.md`
- `docs/adr/ADR-0021-unit-of-work-context-shape.md`
- `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`
- `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md`
- `docs/adr/ADR-0036-platform-errors-errorhandler-exception-mapper.md`
