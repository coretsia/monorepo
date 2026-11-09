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

# Logging Policy SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: platform/logging
```

## Scope

This document is the Single Source of Truth for the `platform.logging` PSR-3 runtime sink: module activation, configuration, canonical record admission, UoW enrichment, mandatory shared redaction, deterministic `jsonl`/`logfmt` output, the fixed STDERR destination, and automatic daily UTC file output.

The implementation is owned by:

```text
packages/platform/logging/config/logging.php
packages/platform/logging/config/rules.php
packages/platform/logging/src/Provider/LoggingServiceProvider.php
packages/platform/logging/src/Provider/LoggingServiceFactory.php
packages/platform/logging/src/Record/LogRecordPolicy.php
packages/platform/logging/src/Processor/ContextBagProcessor.php
packages/platform/logging/src/Formatter/JsonLineFormatter.php
packages/platform/logging/src/Formatter/LogfmtLineFormatter.php
packages/platform/logging/src/Logger/StreamLogger.php
```

This SSoT defines logging sink policy.

This SSoT defines only Logging sink policy. Producer-side validation, runtime module selection, Composer dependency synchronization, and Kernel lifecycle orchestration remain outside this contract.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Module selection and binding (MUST)

`platform.logging` participates in provider registration only when enabled in the resolved target-local ModulePlan. Physical Composer installation alone does not activate the logging provider.

The logging module declares runtime dependencies on `core.foundation`, `core.kernel`, and `platform.redaction`. It does not implement module discovery, graph resolution, or Composer reconciliation.

When the logging provider is selected, its validated `logging.driver` determines the logger binding:

- `null`: `LoggingServiceFactory` returns `Coretsia\Foundation\Logging\NoopLogger` after complete configuration validation.
- `stream`: `LoggingServiceFactory` returns `StreamLogger` configured for the fixed internal `php://stderr` destination.
- `file`: `LoggingServiceFactory` returns `StreamLogger` configured for automatic daily UTC local-file output.

The string `null` is not PHP `null`.

The real logging backend becomes available only after successful provider and container composition. Ultra-early and pre-container diagnostics are not guaranteed to use this backend.

## Configuration and ownership (MUST)

The required `logging` map has exactly the following configuration entries:

- `logging.driver`: required string enum `stream`, `file`, `null`; default `stream`.
- `logging.file`: required map with exactly `path` and `name`.
- `logging.file.path`: required string; default `''`; may be empty when the file backend is inactive.
- `logging.file.name`: required `reset-group-id` string; default `app`; extension-free filename stem matching `[a-z][a-z0-9_-]{0,63}`.
- `logging.level`: required PSR-3 severity enum; default `info`.
- `logging.max_record_bytes`: required integer in the inclusive range `1024..65536`; default `65536`.
- `logging.service_name`: required `reset-group-id` string; default `auto`; exact grammar `[a-z][a-z0-9_-]{0,63}`.
- `logging.format`: required string enum `jsonl`, `logfmt`; default `jsonl`.

Unknown and reserved configuration keys are rejected.

In particular, `logging.stream`, `logging.redaction`, redaction enable/disable switches, formatter registries, rotation modes, and arbitrary stream URI settings are not recognized.

Mandatory redaction is never controlled by logging configuration.

The Logging package declares its configuration validation rules in `config/rules.php`. `LoggingServiceFactory` independently validates its inputs when called directly.

Invalid `service_name` and malformed declarative settings fail during canonical config validation, before lazy `LoggerInterface` construction.

`LoggingServiceFactory` independently verifies required keys, types, enum values, filename stem, service name, file-map consistency, and driver-dependent file-path syntax when called directly; it does not introduce a new config rule type.

For `driver='file'`, `file.path` MUST be a non-empty absolute local directory path of at most 1024 bytes, with no NUL/control characters, dot traversal components, stream wrappers, or remote schemes.

POSIX uses a single leading `/` and rejects empty internal components, backslashes, and colons.

On Windows only drive-rooted local absolute paths are supported; drive-relative paths, UNC shares, device namespaces, alternate data streams, and unsafe components are rejected.

`file.name` remains an opaque validated stem: app-target-like or date-like substrings are not interpreted or deduplicated.

Factory-time path validation checks syntax and captures immutable settings; it does not create, canonicalize, open, probe, or require a directory.

The existence, canonical resolution, type, accessibility, and writability of a directory are emission-time I/O concerns owned by `StreamLogger`.

A syntactically valid missing directory does not constitute malformed configuration, and file-mode unavailability MUST NOT change the configured logger to `NoopLogger`.

The factory reads the validated merged values through `ConfigRepositoryInterface` once when constructing the logger.

Configuration is not reread per record.

No destination URI, file path, filename, open handle, selected UTC day, or runtime configuration object enters a declarative DI parameter or serialized source/compiled service-definition value.

`LoggingServiceProvider::define()` is the single declarative wiring source, with the same seven required service references in source and compiled definitions.

## Service identity (MUST)

`platform.logging` obtains the selected application target through `AppTargetProviderInterface::app()`: exactly `web`, `api`, `console`, or `worker`.

The logging package MUST NOT infer the target from event names, context, paths, process arguments, or environment variables.

`logging.service_name='auto'` uses the canonical app target as the emitted `service_name`.

An explicit safe service-name token changes only the service label; `app_target` remains the selected canonical target and is the sole daily filename prefix source.

Both values are captured at logger construction, are immutable for the logger lifetime, cannot be overridden by caller context, and are included in the assembled canonical record before mandatory shared redaction and post-redaction validation.

## Severity, message, and record pipeline (MUST)

The exact PSR-3 severity order, from least to most severe, is:

```text
debug, info, notice, warning, error, critical, alert, emergency
```

`StreamLogger` validates the PSR-3 level first.

Unknown, non-string, or otherwise invalid levels raise `Psr\Log\InvalidArgumentException`; this caller API error is not treated as an internal logging failure.

Records below the configured minimum level are discarded before admission, context reads, clock access, redaction, formatting, or I/O.

The exact eligible-record sequence is:

```text
PSR-3 level validation
→ minimum severity filter
→ initial message and context sink admission
→ current UoW correlation/context enrichment
→ ClockInterface UTC timestamp capture (nullable on failure)
→ assembly of all nine canonical root fields
→ one mandatory shared record redaction
→ complete post-redaction structural and sensitive-key admission
→ selected jsonl or logfmt serialization
→ full serialized frame byte-limit check (including final LF)
→ eligible fixed STDERR or UTC daily file destination selection
→ one bounded write attempt
```

No canonical root field may be added, reconstructed, or replaced after post-redaction validation.

Neither formatter may perform an independent redaction or bypass instance-bound sink admission.

No raw fallback emission or recursive self-reporting occurs when any internal stage fails.

A message MUST be a producer-owned static safe event identifier.

Its exact byte-oriented grammar is:

```text
\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z
maximum: 128 bytes (strlen)
rejected message fallback: logging.message_rejected
```

The grammar supports existing dotted, underscored, and hyphenated Core event identifiers.

It rejects whitespace, uppercase letters, control characters, arbitrary prose, and unsupported types.

`Stringable::__toString()` is not invoked to establish safety and context is never interpolated into a message.

Lexical validity cannot prove that a string is not derived from user input; producers MUST supply static event identifiers, not arbitrary request text or raw diagnostics.

## Canonical record schema (MUST)

A successfully admitted record has exactly nine required root keys, with no additional root fields:

- `ts_utc`: UTC ISO-8601 timestamp string or `null`. The logger emits `YYYY-MM-DDTHH:MM:SS.ffffffZ` using the injected `ClockInterface`; canonical validation also accepts structurally valid UTC `Z`/`+00:00` timestamps with at most six fractional digits and no more than 40 bytes.
- `level`: exactly one of the eight canonical lowercase PSR-3 levels.
- `message`: admitted bounded static event identifier; never arbitrary diagnostic prose.
- `service_name`: required string matching `[a-z][a-z0-9_-]{0,63}`; never `null`.
- `app_target`: required string `web`, `api`, `console`, or `worker`; never `null`.
- `correlation_id`: `null` or a 26-character uppercase Crockford ULID restricted to `[0-9A-HJKMNP-TV-Z]{26}`.
- `uow_id`: `null` or a string of at most 128 bytes matching `[A-Za-z0-9][A-Za-z0-9._:-]*`.
- `uow_type`: `null` or exactly `http`, `cli`, `queue`, or `scheduler`.
- `context`: `null` or a non-empty, sanitized, string-keyed map of at most 48 fields; empty maps normalize to `null`.

Root field presence is unconditional.

`correlation_id`, `uow_id`, and `uow_type` remain present with `null` values outside an active UoW, when a context value is absent/invalid, or when a canonical context read throws.

No artificial identifier is generated.

`ContextBagProcessor` reads the current context on every admitted emission using `ContextAccessorInterface::has()` before `get()` (without a default argument) and `CorrelationIdProviderInterface::correlationId()`.

An optional safe route template from `ContextKeys::PATH_TEMPLATE` is admitted under `context.path_template`, not at the root.

Caller-provided `correlation_id`, `uow_id`, and `uow_type` may survive only as separately admitted, non-authoritative nested context values; they cannot populate canonical root identifiers or restore a completed UoW.

After `ContextStore::reset()`, the next record uses only the next UoW's current values.

The processor and logger do not own ContextStore, UoW snapshots, caches, or reset hooks.

## Context sink-field admission (MUST)

`LogRecordPolicy` owns the single canonical context allowlist.

Every listed bound is measured using `strlen()` for strings and exact PHP types for numeric fields.

No unlisted field, list, object, float, resource, `Throwable`, arbitrary nested map, or raw request value is permitted.

Invalid branches are omitted; a wholly omitted context becomes `null`.

The exact allowed field families are:

- Category strings: `operation`, `outcome`, `event`, `reason`, `status`, `driver`, `control_transport`, `presetName`, `preset`. Each is 1..96 bytes and matches `\A[a-z][a-z0-9]*(?:[._:-][a-z0-9]+)*\z`.
- `code`: 1..128 bytes, matching `\A[A-Za-z][A-Za-z0-9_:-]*\z`.
- Non-negative count integers, each at most `1000000000`: `artifact_count`, `bucket_count`, `byte_count`, `dirty_artifact_count`, `env_overlay_mapping_count`, `env_overlay_metadata_count`, `existing_artifact_count`, `expected_artifact_count`, `groups_count`, `invalid_artifact_count`, `missing_artifact_count`, `missing_source_candidate_count`, `ready_worker_count`, `ruleset_count`, `services_count`, `source_candidate_count`, `source_entry_count`, `unvalidated_root_count`, `validated_root_count`, `validation_subject_unvalidated_count`, `validation_subject_validated_count`, `worker_count`.
- `duration_ms`: integer `0..4294967295`; `exit_code`: integer `0..255`; `pid`: integer `1..2147483647`.
- `artifact_basename`: 1..128 bytes, matching `\A[A-Za-z0-9][A-Za-z0-9._-]*\z`, without `..`.
- `artifact_path`: 1..512 bytes, restricted to normalized relative path characters `[A-Za-z0-9_@.<>\[\]/-]`, with no absolute prefix, `\\`, colon, double slash, or `.`/`..` traversal component.
- `endpoint_hash`: exactly 64 lowercase hexadecimal characters; only an owner-approved safe derivation is admissible.
- `app_target`: one of `web`, `api`, `console`, `worker`, when present as a *nested*, non-authoritative context field.
- `correlation_id`, `uow_id`, `uow_type`: nested caller values using the same non-null validation as their respective root fields; their admission never overrides canonical root values.
- `path_template`: `/` or a structural route template of at most 256 bytes with static lowercase path components (`[a-z_-][a-z0-9_-]*`) or named `{placeholder}` components (`[A-Za-z_][A-Za-z0-9_]*`); never a raw request path or route parameter value.

The following are the *only* admitted flat list fields.

Each is a PHP list of strings, with the given maximum number of items, maximum bytes per item, and semantic validation:

- `artifact_paths`: 12 items, 512 bytes, normalized safe relative paths.
- `artifact_reasons`: 12 items, 160 bytes, exactly `artifact_basename:category`, with each component independently valid.
- `bucket_names`: 20 items, 96 bytes, category tokens.
- `directive_names`: 20 items, 160 bytes, safe relative-metadata tokens.
- `key_paths`: 20 items, 160 bytes, structurally valid dotted key paths and mandatory shared sensitive-key classification.
- `moduleIds`: 24 items, 128 bytes, strings matching `\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z`.
- `source_kinds`: 20 items, 160 bytes, safe relative-metadata tokens.
- `source_layers`: 20 items, 160 bytes, safe relative-metadata tokens.
- `source_paths`: 20 items, 160 bytes, safe relative-metadata tokens.
- `source_types`: 20 items, 160 bytes, safe relative-metadata tokens.

Safe relative-metadata tokens use the same bounded relative-path shape as `artifact_path` with their own byte cap; they are not permission to export arbitrary filesystem paths.

Valid admitted lists are deduplicated and sorted by ascending `strcmp` byte order.

Post-redaction validation requires the same canonical unique, sorted order.

A list's presence does not authorize arbitrary nested JSON objects, and a redacted summary map does not satisfy a field requiring a scalar string.

`key_paths` have additional exact restrictions: each entry matches `\A[A-Za-z0-9_@<>\[\]-]+(?:\.[A-Za-z0-9_@<>\[\]-]+)*\z`.

Empty dotted segments, path separators, and unsupported structural characters are rejected; square brackets are admitted by this bounded structural grammar without an independent requirement that they form balanced pairs.

`LogRecordPolicy` calls the injected `SensitiveKeyClassifierInterface::classify()` on the complete path and every dot-separated segment, and separately checks components split by `@`, `<`, `>`, `[` and `]` where present.

Any non-null `RedactionKind` classification rejects the *entire* `key_paths` branch at initial admission; it is never emitted as a partial list, hash, length, or original value.

Classifier contract failures reject the complete log record, not merely a context branch.

The same instance-bound checks apply to canonical pre-redaction records and post-redaction output.

The Logging sink consumes the shared sensitive-key classifier through its contracts-level interface. It does not duplicate the shared classifier vocabulary or implement producer-specific sensitive-data filters.

An unclassified result (`null`) does not bypass the sink's own lexical, shape, and type validation.

The sink is producer-neutral: its allowlist does not branch on source package, runtime lifecycle phase, command name, event name, or business identity.

New producers SHOULD reuse existing field families; extending the sink schema requires deliberate SSoT review, not package-specific exceptions.

## Mandatory shared redaction and forbidden data (MUST)

`LogRecordPolicy` depends on both `SensitiveDataRedactorInterface` and `SensitiveKeyClassifierInterface` as contracts-level ports.

Redaction of the *complete assembled canonical record* is mandatory through `SensitiveDataRedactorInterface::redactJsonLike()` and immutable `RedactionContext('logging.record', RedactionMode::Placeholder)`; no config flag, mode switch, new generic classifier, or duplicate redaction walker may disable or replace it.

Initial admission rejects unknown or unsafe field shapes and classified key paths before redaction.

The complete assembled record includes `ts_utc`, `level`, `message`, `service_name`, `app_target`, current UoW identifiers, and admitted context before it reaches the redactor.

The post-redaction record is validated again for exact root fields, allowed values, and sensitive key paths.

Invalid root fields, unexpected redactor result types, classifier failures, and redaction exceptions discard the *entire* emission.

An invalid redacted context branch can be omitted, but raw original values may never be restored.

There is no second redaction attempt or fallback sink.

The following raw categories MUST NOT enter emitted records: Authorization, Cookie and Set-Cookie values, session identifiers, credentials, tokens, arbitrary headers, query strings, request/response bodies, provider payloads, raw SQL, environment/config secrets or references, absolute local paths, raw request path, host, user-agent, client IP, customer email/phone/username/name/PII, arbitrary exception messages, traces, previous Throwables, and arbitrary runtime objects.

Safe categories, explicit integer counts/durations, owner-approved route templates, and normalized relative artifact summaries are permitted only within their individual bounds.

A string looking like an approved category does not prove that its original producer value was safe.

## Deterministic serialization (MUST)

Both formatters receive the same final admitted, redacted canonical nine-field map and call `LogRecordPolicy::isValidCanonicalRecord()` for structural validation.

This static check is defense in depth, not a replacement for instance-bound classification/redaction in `StreamLogger`.

- `jsonl` uses `Coretsia\Foundation\Serialization\StableJsonEncoder::encodeStableMap()` for deterministic recursive map ordering, preserved list order, JSON object framing, and the single final LF. Null context becomes JSON `null`; non-empty context becomes a JSON object.
- `logfmt` emits a single space-separated line of canonical `key=value` fields in exactly this order: `ts_utc`, `level`, `message`, `service_name`, `app_target`, `correlation_id`, `uow_id`, `uow_type`, `context`. All non-null strings are double quoted with deterministic escaping of backslash, quote, LF, CR, tab, and other control bytes. Nullable fields are the unquoted token `null`; a string with contents `null` is still quoted.
- `logfmt` encodes non-empty `context` using Foundation `StableJsonEncoder::encodeStableMap()`, removes only that encoder's final LF from the embedded JSON, then quotes/escapes the compact JSON as one value. `context=null` remains unquoted. Only one final LF is appended to the complete logfmt frame. No second recursive JSON normalizer, sorter, or encoder is introduced.

Synthetic valid input after admission and shared redaction, without an active UoW:

```text
level = info
message = kernel.boot
service_name = web
app_target = web
ts_utc = 2026-10-31T12:00:00.000000Z
correlation_id = null
uow_id = null
uow_type = null
context = {operation: boot}
```

The corresponding canonical JSONL frame is:

```json
{"app_target":"web","context":{"operation":"boot"},"correlation_id":null,"level":"info","message":"kernel.boot","service_name":"web","ts_utc":"2026-10-31T12:00:00.000000Z","uow_id":null,"uow_type":null}
```

The corresponding logfmt frame is:

```text
ts_utc="2026-10-31T12:00:00.000000Z" level="info" message="kernel.boot" service_name="web" app_target="web" correlation_id=null uow_id=null uow_type=null context="{\"operation\":\"boot\"}"
```

Each fenced output line represents exactly one final LF-terminated frame.

Its runtime timestamp was captured externally; determinism means identical *accepted values in a selected format* serialize to identical bytes, not that separate log invocations have identical timestamps.

Unsafe counterexamples, which MUST NOT pass through as raw output:

```text
message = "Authorization: Bearer secret"    -> logging.message_rejected
context.authorization = "Bearer secret"     -> omitted
context.request_body = {secret: "secret"}   -> omitted
context.raw_sql = "SELECT ..."              -> omitted
context.host = "private.internal"           -> omitted
context.key_paths = ["db.password"]         -> entire key_paths branch omitted
context.artifact_path = "/var/private/file" -> omitted
context.duration_ms = 1.5                   -> omitted
context.exception = Throwable               -> omitted
```

Classification failures reject a whole log record rather than publishing an error, a partial record, or a raw fallback.

A caller must not treat an unknown key or classifier non-match as an authorization to serialize it.

## Record byte budget and failure isolation (MUST)

`StreamLogger::MAX_RECORD_BYTES` is the immutable hard ceiling of `65536` bytes.

`logging.max_record_bytes` is an effective per-record limit in `1024..65536`; it does not cap log-file size.

The logger measures `strlen()` of the *complete serialized output* after formatting, including the final LF.

A record of exactly the effective limit is admissible; one byte above the limit is discarded before any destination I/O.

The logger does not truncate frames, create malformed JSON, retry oversized emissions, or switch to a fallback destination.

Successful complete emissions submit one bounded `fwrite()` attempt; short or failed writes are failures, never requests for a second write.

Formatter/classifier/redactor errors, invalid output, clock read failures, destination failures, and PHP warnings do not expose raw input or change the caller's business flow.

An invalid PSR-3 level is the explicit API exception to this internal no-throw behavior.

No per-record `logging.write` spans or metrics are created because instrumentation inside `LoggerInterface` risks recursive logging via tracer/exporter/metrics adapters.

Existing producer operation boundaries remain authoritative.

## Fixed STDERR and daily file destinations (MUST)

The `stream` driver opens only the internal, non-configurable `php://stderr` in append-binary mode during logger construction.

If this fixed initialization fails, `LoggingServiceFactory` returns the existing Foundation `NoopLogger` without output or leaking diagnostics.

The process-lifetime STDERR handle is infrastructure, not UoW-local context state.

A later stream write failure discards that emission without opening another destination or recursively reporting itself.

The `file` driver retains the same `StreamLogger`, formatter, redactor, key classifier, record admission, severity filter, and byte budget.

For each fully admitted and bounded record it selects exactly:

```text
{app_target}-{file.name}-{YYYY-MM-DD}.{format}
```

The filename prefix uses the immutable canonical `app_target`, not `service_name` or caller context.

The stem is the unchanged validated `logging.file.name`; extension is exactly `jsonl` or `logfmt`.

The date is taken from the final post-redaction validated `ts_utc` when non-null.

When the final post-redaction `ts_utc` is `null` (including a clock failure), an invocation-local system `gmdate('Y-m-d')` supplies the filename date **only**; the canonical `ts_utc` remains `null`.

The logger never caches the date or a daily file handle between emissions.

A UTC day change automatically selects a new file without restarting the logger or rotating the previous file.

On each eligible emission `StreamLogger` resolves the configured directory to an existing canonical local directory, checks accessibility, checks for detectable symlink/non-regular destinations, opens the generated daily file in append-binary mode, verifies the opened handle is a regular file, acquires `flock(LOCK_EX | LOCK_NB)` on that *same* handle, attempts one `fwrite()`, releases any acquired lock, and closes the handle.

There is no separate lock-anchor file, automatic directory creation, retry queue, status registry, retained handle, or Foundation `ScopedFileLock` dependency.

File availability or lock contention discards the current record without replacing `StreamLogger`; a later record may succeed after the administrator restores the destination.

Deployment administrators MUST control the configured directory and its contents and prevent untrusted concurrent modification.

PHP-level `is_link()`, `realpath()`, `lstat()`, and `fstat()` checks are defense in depth, not race-free symlink protection.

There is no guaranteed multi-process atomicity on unsupported filesystems, `fsync` durability, or complete framing after an underlying partial write; partial bytes already written cannot be rolled back.

No file retention, deletion, archival, compression, rotation-mode configuration, disk quota, or total-file-size management is provided.

These remain external deployment responsibilities.

Generated daily log files are runtime outputs, not Kernel build artifacts or artifact-registry entries.

## Declarative DI, lifecycle, and security boundaries (MUST)

`LoggingServiceProvider` implements the Foundation declarative provider contracts.

Its `define()` method binds the PSR-3 logger using `LoggingServiceFactory::createLogger` and requires these seven references in exact positional order:

```text
ConfigRepositoryInterface
ContextAccessorInterface
CorrelationIdProviderInterface
SensitiveDataRedactorInterface
SensitiveKeyClassifierInterface
ClockInterface
AppTargetProviderInterface
```

Source and compiled DI definitions contain only deterministic declarative references; no resource, service instance, destination URI, file path, runtime date, closure, extra module, or reset tag is embedded.

Format and destination are selected from the validated runtime repository only during factory construction and eligible emissions respectively.

Logging does not own mutable UnitOfWork context state or its reset lifecycle.

`ContextBagProcessor` reads current canonical values on each admitted record.

Neither logger nor processor registers a `kernel.reset` service, caches correlation IDs, retains per-UoW diagnostic records, or manages context restoration.

No additional `LoggerInterface`, redactor, classifier, stable JSON encoder, correlation provider, generic lock abstraction, or runtime registry is introduced.

## Enforcement rails

The verification boundary includes:

- `packages/platform/logging/tests/Unit/` for severity, canonical admission, both deterministic formatters, clock, serialized byte limits, stream/file writes, and UTC filename selection.
- `packages/platform/logging/tests/Contract/` for declarative config/DI safety, mandatory redaction, secret suppression, metadata, and Foundation noop behavior.
- `packages/platform/logging/tests/Integration/` for explicit Foundation/Logging provider precedence, fixed STDERR and file output, and ContextStore UoW/reset isolation.

These tests do not authorize runtime stream injection or arbitrary destination configuration.

Isolated PHP subprocesses may simulate fixed STDERR stream-wrapper failures; temporary local directories support file tests.

No actual user or system log directories may be modified by the test suite.

## Non-goals

- New PSR-3 ports, a second logger backend implementation, public stream URI configuration, or runtime formatter registries.
- HTTP access-log middleware implementation, transport payload capture, user-data logging, raw exception reporting, or security-event forensics.
- New `ContextStore`, correlation generation, UoW snapshot, reset orchestration, or logging-owned tracing and metrics.
- New generic sensitive-key aliases, redaction engines, JSON serializers, file locks, or filesystem service abstractions.
- Filesystem race immunity, atomic writes across unsupported filesystems, retries, buffering, fsync guarantees, or recovery of partial writes.
- Retention, archival, deletion, compression, external log rotation, quotas, or log-file manifest/fingerprint/hydration artifacts.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Sensitive Data Redaction SSoT](./sensitive-data-redaction.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [Observability SSoT](./observability.md)
- [ContextStore lifecycle SSoT](./context-lifecycle.md)
- [Context Keys SSoT](./context-keys.md)
- [Config Roots SSoT](./config-roots.md)
- [Runtime Container Definitions SSoT](./runtime-container-definitions.md)
- [Compiled Container SSoT](./compiled-container.md)
- [Modes SSoT](./modes.md)
- [Modules and Manifests SSoT](./modules-and-manifests.md)
- [Application Dependency Sync SSoT](./application-dependency-sync.md)
- [Architecture Dependencies](../architecture/DEPENDENCIES.md)
- [ADR-0010: Sensitive data redaction boundary](../adr/ADR-0010-sensitive-data-redaction-boundary.md)
- [ADR-0021: Unit of work context shape](../adr/ADR-0021-unit-of-work-context-shape.md)
- [ADR-0037: Platform logging PSR-3](../adr/ADR-0037-platform-logging-psr3.md)
