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

# coretsia/platform-logging

`platform/logging` is the canonical PSR-3 structured logging runtime package for Coretsia, providing a fixed STDERR stream backend and an automatic daily UTC local-file backend while retaining the existing Foundation `NoopLogger` fallback.

Scope: target-local runtime module selection, declarative `LoggerInterface` binding, validated logging configuration, safe operational sink admission, current UnitOfWork correlation enrichment, mandatory shared redaction and sensitive-key classification, deterministic JSONL/logfmt serialization, bounded single-write emission, and isolated stream/file I/O failures.

Out of scope: log producer ownership, HTTP access-log middleware, raw request or exception diagnostics, arbitrary stream destinations, vendor logging SDKs, new correlation or reset services, custom redaction engines, tracing and metrics exporters, file retention and archival, disk quotas, and Kernel module planning or container compilation.

This README is a consumer-oriented package summary. The exact normative record and sink policies are defined in `docs/ssot/logging-policy.md`.

## Package identity

- Path: `packages/platform/logging`
- Package id: `platform/logging`
- Composer name: `coretsia/platform-logging`
- Module id: `platform.logging`
- Namespace: `Coretsia\Platform\Logging\*` (PSR-4: `src/`)
- Kind: runtime
- Config root: `logging`

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions MUST NOT be used.

## Dependency policy

The package declares these production Composer dependencies:

- PHP `^8.4`
- `coretsia/core-contracts`
- `coretsia/core-foundation`
- `coretsia/core-kernel`
- `coretsia/platform-redaction`
- `psr/log`
- `psr/clock`

The package's `composer.json` is authoritative for exact constraints. `core/contracts` supplies the shared security, configuration, app-target, context, and correlation ports. `core/foundation` supplies the baseline `NoopLogger`, declarative container definitions, context infrastructure, and `StableJsonEncoder`.

The required `core.kernel` and `platform.redaction` Composer/module dependencies provide runtime composition and the mandatory security implementations. Direct production PHP source dependencies are restricted to `core/contracts` and `core/foundation`; `packages/platform/logging/src/**` MUST NOT import Kernel or concrete Redaction implementation classes. Neither runtime dependency grants an additional layered source allowance.

Forbidden production imports include `platform/http`, `platform/errors`, `platform/cli`, `platform/worker`, other platform implementations, integrations, enterprise/devtools packages, vendor logger SDKs, and repository tooling under `tools/**`.

Existing consumers continue to depend on `Psr\Log\LoggerInterface` only; they do not need to construct or depend on `StreamLogger`.

## Runtime responsibilities

`platform/logging` provides:

- `LoggingModule`, declaring one runtime module and the required `core.foundation`, `core.kernel`, and `platform.redaction` dependencies;
- `LoggingServiceProvider` and `LoggingServiceFactory`, supplying the single declarative PSR-3 binding and construction-time configuration selection;
- `StreamLogger`, the same logging implementation for both active destinations;
- `ContextBagProcessor`, reading canonical current context and correlation without storing UnitOfWork state;
- `LogRecordPolicy`, owning producer-neutral destination-specific sink admission and post-redaction validation;
- `JsonLineFormatter` and `LogfmtLineFormatter`, serializing the same admitted and redacted record with Foundation stable JSON reuse.

The active logger applies the same severity filtering, record admission, enrichment, redaction, formatter selection, and complete-record byte limit for the `stream` and `file` drivers. No `FileLogger`, second admission policy, classifier registry, logging-owned context store, or reset service is introduced. HTTP request completion events are produced by `platform/http` through `Psr\Log\LoggerInterface`; `platform/logging` applies its producer-neutral sink contract without importing HTTP implementations, inspecting requests, implementing middleware, or inferring HTTP outcomes.

## Configuration

The package defaults are in `config/logging.php`; the canonical declarative rules are in `config/rules.php`. The merged `logging.*` values are read from `ConfigRepositoryInterface` at logger construction, not on every record.

The package-owned configuration subtree is:

```php
return [
    'driver' => 'stream',
    'file' => [
        'path' => '',
        'name' => 'app',
    ],
    'level' => 'info',
    'max_record_bytes' => 65536,
    'service_name' => 'auto',
    'format' => 'jsonl',
];
```

- `logging.driver`: required **string** enum `stream|file|null`; default `stream`.
- `logging.file.path`: required string, default `''`; empty is valid for inactive file output. File mode requires a non-empty validated absolute local directory.
- `logging.file.name`: required extension-free basename stem, default `app`; exact ASCII grammar `[a-z][a-z0-9_-]{0,63}`. The stem is not interpreted to strip dates or app-target prefixes.
- `logging.level`: required minimum PSR-3 severity, default `info`.
- `logging.max_record_bytes`: required integer in `1024..65536`, default `65536`, including the serialized final LF.
- `logging.service_name`: required bounded service label, default `auto`; exact grammar `[a-z][a-z0-9_-]{0,63}`.
- `logging.format`: required string enum `jsonl|logfmt`, default `jsonl`.

Only these fields and the exact `file.path`/`file.name` map are supported. Unknown keys and unsupported formats fail canonical configuration validation. There is no `logging.stream`, `logging.redaction`, `redaction.enabled`, destination URI, arbitrary formatter, retention, rotation, or redaction-disable configuration surface.

Kernel validates the declarative shape, scalar types, enums, and existing `reset-group-id` grammar. `LoggingServiceFactory` independently verifies required values and driver-specific path syntax when called directly. Malformed configuration fails deterministically at the configuration/bootstrap boundary or logger construction; it is not an I/O failure and MUST NOT silently fall back to `NoopLogger`.

The canonical selected application target is obtained through `AppTargetProviderInterface::app()`: exactly `web`, `api`, `console`, or `worker`. With `service_name='auto'` the emitted service name is that target; an explicit validated service-name token changes only `service_name`, never `app_target` or the file prefix. Both identities are immutable for the lifetime of the constructed logger and cannot be overridden by caller context.

## Severity and canonical records

The exact severity order, from least to most severe, is:

```text
debug, info, notice, warning, error, critical, alert, emergency
```

`StreamLogger` validates the requested PSR-3 level before applying the configured minimum. Records below that minimum perform no context read, redaction, clock read, serialization, or destination I/O. For example, default `logging.level='info'` filters `debug` but admits `info` and all more severe levels.

The PSR-3 message MUST be a producer-owned static event identifier, at most 128 bytes, matching `\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z`. The public `StreamLogger` method accepts `string|\Stringable` as required by PSR-3 (subject to PHP's caller-side scalar coercion rules). Strings that fail the event-token grammar and `Stringable` objects reaching `LogRecordPolicy::admitMessage()` use the fixed safe `logging.message_rejected` event without invoking `__toString()`; incompatible argument types fail at the PHP method boundary before sink admission. Lexical validation does not prove the message was not derived from sensitive user input; producer owners MUST supply safe static identifiers and bounded safe values. Logger code never interpolates arbitrary context into the message or evaluates `Stringable::__toString()` to diagnose rejected input.

Every successful canonical record has exactly these nine root fields:

```text
ts_utc
level
message
service_name
app_target
correlation_id
uow_id
uow_type
context
```

`ts_utc` is a UTC ISO-8601 string or `null` if the clock cannot be read. `service_name` and `app_target` are always strings. `correlation_id`, `uow_id`, and `uow_type` are always present but nullable; `context` is either `null` or a non-empty admitted string-keyed map. Additional root fields are forbidden.

The record pipeline is fixed:

```text
PSR-3 level validation
-> minimum severity filter
-> initial message and context admission
-> current UoW enrichment
-> ClockInterface UTC timestamp capture
-> nine-field canonical record assembly
-> pre-redaction canonical record and sensitive key-path validation
-> mandatory shared whole-record redaction
-> post-redaction schema and classifier validation
-> selected jsonl or logfmt serialization
-> complete serialized-frame byte-limit check
-> selected STDERR or daily file destination
-> one bounded write attempt
```

The canonical record is not modified after post-redaction validation. The configured `max_record_bytes` applies to the entire serialized frame (including final LF), while `StreamLogger::MAX_RECORD_BYTES = 65536` is the immutable hard ceiling. An exactly-at-limit frame is accepted; an oversized frame is dropped rather than truncated or retried. This per-record budget does not limit a daily file's total size.

## Operational context and correlation

`LogRecordPolicy` admits only bounded safe-by-construction operational fields; it is not a general-purpose serializer of PSR-3 context. Caller context with more than 48 entries is omitted as a whole before optional `ContextBagProcessor` enrichment; an otherwise valid event may still be emitted. Within that limit, invalid or unknown branches are omitted, and a context with no admitted fields becomes `null`. The complete assembled canonical context is checked again after enrichment: more than 48 fields reject the entire record before redaction. If the shared redactor returns a context with more than 48 fields, post-redaction admission omits the entire context while retaining otherwise valid root fields, subject to independent sensitive `key_paths` checks.

The allowed scalar families and bounds are:

- Category tokens `operation`, `outcome`, `event`, `reason`, `driver`, `control_transport`, `presetName`, and `preset`: 1..96 ASCII bytes, matching `\A[a-z][a-z0-9]*(?:[._:-][a-z0-9]+)*\z`.
- `status`: either a strict integer `100..599` or a string satisfying the same 1..96-byte category grammar (for example, `ready` or `unknown`); numeric strings, floats, and booleans are not admitted.
- `method`: exactly `GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `TRACE`, `CONNECT`, or `OTHER` (uppercase, byte-for-byte).
- `route_template`: the exact string `unknown`; no other value is admitted.
- `code`: 1..128 bytes, using the bounded safe code grammar.
- Approved non-negative count fields (integers `0..1000000000`; no arbitrary `*_count` names are accepted):
  - `artifact_count`, `bucket_count`, `byte_count`, `dirty_artifact_count`, `env_overlay_mapping_count`, `env_overlay_metadata_count`, `existing_artifact_count`, `expected_artifact_count`.
  - `groups_count`, `invalid_artifact_count`, `missing_artifact_count`, `missing_source_candidate_count`, `ready_worker_count`, `ruleset_count`, `services_count`.
  - `source_candidate_count`, `source_entry_count`, `unvalidated_root_count`, `validated_root_count`, `validation_subject_unvalidated_count`, `validation_subject_validated_count`, `worker_count`.
- `duration_ms`: integer `0..4294967295`; `exit_code`: integer `0..255`; `pid`: integer `1..2147483647`.
- `artifact_basename`: bounded validated basename, at most 128 bytes; `artifact_path`: validated owner-approved relative path, at most 512 bytes. Absolute local paths are forbidden.
- `endpoint_hash`: exactly 64 lowercase hexadecimal characters derived under the producer's safe disclosure policy.
- Nested `app_target`: one of `web|api|console|worker`; nested `correlation_id`, `uow_id`, and `uow_type`: individually validated, non-authoritative copies only.
- `path_template`: bounded structural template of at most 256 bytes, independently admitted from `route_template`; never a raw request path or resolved route parameter. An optional safe value may be enriched from `ContextKeys::PATH_TEMPLATE` by `ContextBagProcessor`.

For the `http.request.completed` event, `platform/http` supplies only bounded `method`, `status`, `outcome`, `duration_ms`, fixed `route_template='unknown'`, and an optional canonical-shaped nested `correlation_id`. Its finalized responses use integer statuses `100..599` with producer-selected `success` (below `400`) or `handled_error` (`400..599`); when no finalized response is available it uses `status=unknown` and `outcome=fatal_error`. The generic sink validates `status` and `outcome` independently and does not derive or enforce their HTTP lifecycle relationship. Neither package derives `route_template` from raw request paths, route parameters, or `path_template`.

The only permitted list fields and their maximum item counts are:

- `artifact_paths`: 12 (512 bytes per item); `artifact_reasons`: 12 (160 bytes per item).
- `bucket_names`: 20 (96 bytes per item); `directive_names`: 20 (160 bytes per item).
- `key_paths`: 20 (160 bytes per item); `moduleIds`: 24 (128 bytes per item).
- `source_kinds`, `source_layers`, `source_paths`, and `source_types`: 20 each (160 bytes per item).

List entries must additionally satisfy their field-specific structural and producer-owned safety rules. Admitted lists are deduplicated and sorted by ascending `strcmp` byte order; no arbitrary nested maps, objects, floats, resources, exceptions, or unlisted fields may be emitted. The exact count-field allowlist, scalar grammars, and per-list validations are normative in `docs/ssot/logging-policy.md`.

`ContextBagProcessor` reads `ContextAccessorInterface` and `CorrelationIdProviderInterface` for each admitted record. Before a UnitOfWork, or following `ContextStore::reset()`, canonical `correlation_id`, `uow_id`, and `uow_type` are present with `null` values; no artificial correlation ID is created. During an active UoW these values come only from the current canonical providers, with an optional safe `context.path_template` when already available. Missing, invalid, and throwing context reads remain isolated.

Caller-supplied identifiers cannot override canonical root identifiers. Separately admitted nested identifiers are non-authoritative and cannot restore a completed UoW or leak into a subsequent UoW. Neither `StreamLogger` nor `ContextBagProcessor` owns `ContextStore`, caches UoW identifiers, or registers a reset service.

## Deterministic JSONL and logfmt output

`logging.format='jsonl'` is the default. Both formats serialize exactly the same admitted and redacted canonical record; selection changes only its byte representation and, for file mode, the filename extension. Format selection does not alter the module plan, DI binding, severity filtering, shared redaction, key-path admission, service identity, byte limit, or UnitOfWork lifecycle.

JSONL uses Foundation `StableJsonEncoder::encodeStableMap()` for stable map ordering and one final LF. Logfmt uses exactly this root field order:

```text
ts_utc level message service_name app_target correlation_id uow_id uow_type context
```

Logfmt emits `key=value` pairs with all non-null strings double-quoted; backslashes, quotes, and control characters are deterministically escaped. Null values are the unquoted token `null` (the string `"null"` remains quoted). A non-empty context is Foundation-encoded compact JSON with only the encoder's trailing LF removed, then escaped and embedded as one quoted logfmt value. A null context emits `context=null`. Logfmt owns exactly one final LF; no second recursive JSON encoder is introduced.

Both formatters validate the same final canonical record through `LogRecordPolicy::isValidCanonicalRecord()` and preserve the integer or string types of post-redaction admitted context values; in logfmt these types are preserved inside the embedded JSON value.

For a synthetic record with no active UoW, a fixed UTC timestamp, and admitted `operation=boot`, the canonical JSONL frame is:

```json
{"app_target":"web","context":{"operation":"boot"},"correlation_id":null,"level":"info","message":"kernel.boot","service_name":"web","ts_utc":"2026-10-31T12:00:00.000000Z","uow_id":null,"uow_type":null}
```

The same canonical record serialized as logfmt is:

```text
ts_utc="2026-10-31T12:00:00.000000Z" level="info" message="kernel.boot" service_name="web" app_target="web" correlation_id=null uow_id=null uow_type=null context="{\"operation\":\"boot\"}"
```

Each displayed frame represents exactly one LF-terminated physical record. Identical admitted values in the same format produce identical bytes; timestamps from separate invocations are not claimed to be identical.

CLI command output is a separate presentation boundary. Selecting `jsonl` or `logfmt` affects logger output on STDERR or daily files, never the CLI command's stdout rendering or its exit code.

## Fixed STDERR and daily file destinations

The `stream` driver uses only the fixed internal `php://stderr` destination, opened in append-binary mode at logger construction. It is not configurable via `logging.stream` or an injected arbitrary stream resource. The STDERR handle may be retained for the logger's process lifetime, but it does not hold UnitOfWork state.

The `file` driver reuses the same `StreamLogger`, `LogRecordPolicy`, redactor/classifier, and formatter pipeline. `logging.file.path` is a deployment-administrator-controlled absolute local directory path, not a file or stream URI; it must have valid platform-specific syntax and be at most 1024 bytes. Driver-specific path syntax is checked during logger construction, without probing, opening, creating, or requiring that directory. The directory must exist, resolve to a permitted canonical local directory, and be accessible at each eligible emission.

The emitted daily basename is always:

```text
{app_target}-{file.name}-{YYYY-MM-DD}.{format}
```

For example, target `web`, stem `app`, selected date `2026-10-31`, and `jsonl` format produce `web-app-2026-10-31.jsonl`. Selecting `logfmt` produces `web-app-2026-10-31.logfmt`. The canonical `app_target` from `AppTargetProviderInterface`, never an explicit `service_name` override or caller context, determines the prefix. The format determines the extension. Daily UTC partitioning is automatic and cannot be disabled or replaced by a rotation-mode setting.

The filename date follows the final validated post-redaction `ts_utc` when present. When `ts_utc` is `null`, an invocation-local `gmdate('Y-m-d')` supplies the date only for the filename; it does not populate or modify the canonical record. A UTC day change selects a new filename without a process restart or cached date.

File mode resolves the directory and checks destination type on every eligible emission, then opens the generated file in append-binary mode, acquires a non-blocking exclusive `flock(LOCK_EX | LOCK_NB)` on that same opened file, attempts exactly one bounded `fwrite()`, releases the lock, and closes the handle. There is no separate lock file, no directory creation, no retained daily handle, and no automatic retries.

The administrator MUST own and secure the configured directory and its contents against untrusted modifications. Detectable symlinks, non-regular destinations, directory traversal, wrapper/remote schemes, and unsupported local path shapes are rejected. PHP-level filesystem checks are not race-free against adversarial concurrent changes; such untrusted concurrent modification is outside the supported deployment model.

An unavailable or missing directory is an emission-time I/O condition, not malformed configuration when its syntax is valid. A failed emission is silently omitted; the selected file-mode `StreamLogger` is **not** replaced with `NoopLogger` or permanently disabled. The same logger can emit a subsequent record when an administrator restores that directory. Inactive file configuration (`driver='stream'` or `'null'`) never opens or creates file destinations or directories.

The module does not implement retention, old-file deletion, archival, compression, configurable rotation, disk quotas, or total-storage limits. Those responsibilities belong to deployment operations. `max_record_bytes` limits each serialized record only; automatic UTC daily files are not a storage-budget or retention mechanism.

## Observability

Logging is a sink for existing safe operational events, not an instrumentation source for its own writes. It introduces no `logging.write` spans, per-record metrics, reporter events, tracing exporters, or logging-owned UnitOfWork reset hooks. Calling a tracer, meter, or reporter from an individual logger emission could recursively invoke `LoggerInterface`.

Kernel, CLI, Errors, and Worker retain responsibility for producing their own safe event identifiers, bounded telemetry dimensions, outcomes, and UnitOfWork boundaries. `ContextBagProcessor` reads current canonical correlation and UoW fields without generating new identifiers, caching snapshots, or duplicating Kernel observability.

## Errors

Invalid logging configuration is a deterministic bootstrap/configuration or construction failure. An unsupported driver, format, PSR-3 severity setting, service name, file stem, or invalid file-path syntax MUST NOT silently select a `NoopLogger`. A syntactically valid but absent or unwritable file directory is an emission-time failure and leaves the configured file-mode logger intact.

The string `logging.driver='null'` explicitly selects Foundation `NoopLogger` without opening a stream or file. Independently, failure to initialize the *fixed* STDERR destination returns Foundation `NoopLogger` without raw diagnostics. File-mode destination failures do not change `LoggerInterface` to `NoopLogger`.

Active `StreamLogger` throws `Psr\Log\InvalidArgumentException` for an invalid PSR-3 level, before filtering; this caller API contract differs from Foundation's existing noop behavior. After valid-level admission, internal context, clock, classification, redaction, formatter, byte-limit, stream/file open, lock, write, and close failures are isolated without caller-visible logging errors or recursive logging. Clock failure yields a nullable timestamp rather than suppressing an otherwise admissible record.

Every successfully formatted, bounded record is submitted through at most one `fwrite()` attempt. Short writes, failed writes, and non-blocking file-lock contention discard the affected emission without a second write or raw fallback. Partial bytes already accepted by the underlying destination cannot be rolled back. The module does not guarantee `fsync` durability, atomicity across unsupported filesystems, race-free filesystem protection, or complete record framing after a partial write; multi-process interleaving guarantees are limited by the actual filesystem and destination semantics.

Internal logger failures do not replace a caller's primary business exception, reveal stream warnings, expose paths or previous Throwable messages, create a failure registry, or permanently disable future file emissions.

## Security / Redaction

Logging requires **both** the contracts-level `SensitiveDataRedactorInterface` and `SensitiveKeyClassifierInterface` services. The selected `platform.redaction` module supplies them; its provider aliases the classifier to the same shared immutable `SensitiveKeyClassifier` instance used by the default redactor. Logging neither imports concrete Redaction implementations nor owns a second sensitive-key alias list or generic classifier.

These ports have distinct purposes:

- `SensitiveKeyClassifierInterface::classify()` is read-only shared sensitive-key classification. `LogRecordPolicy` uses it for semantic admission of the complete `context.key_paths` strings, their non-empty dotted segments, and structural components split by `@`, `<`, `>`, `[` and `]`.
- `SensitiveDataRedactorInterface::redactJsonLike()` performs mandatory whole-record redaction after the full nine-field canonical map is assembled, with the fixed immutable `RedactionContext('logging.record', RedactionMode::Placeholder)`. The complete result is checked again before formatting.

A classified sensitive key in any `key_paths` entry omits the **entire** field during initial context admission, not just the offending item. For example, `['db.account', 'db.password']` cannot be emitted as a partial key-path list. A classifier `null` result means *unclassified*, not *safe*: producer-owned restrictions, exact path grammar, logging's context-field allowlist, and mandatory redaction still apply. Logging does not copy Kernel's sensitive-key/SQL-like producer regexes, and producers may not resubmit paths their own policies rejected.

Ordinary unsafe or unrecognized context fields are omitted, potentially yielding `context=null`. Following mandatory whole-record redaction, individual ordinary fields that no longer satisfy sink admission are omitted without recovering their original values or coercing their original types. A malformed non-null redacted context container, invalid canonical root, or invalid/sensitive post-redaction `key_paths` rejects the whole record. A classifier exception or invalid classifier result also aborts the **whole record** at the existing no-throw logging boundary. Initial classifier failure prevents redaction, formatting, and I/O. Post-redaction classifier failure aborts after the already completed single redaction call, without a second redaction, a partial record, raw diagnostic, or fallback sink. Redactor failure or invalid post-redaction output also fails closed.

No generic redactor or key classifier can automatically establish that arbitrary runtime-derived strings, customer data, or even syntactically valid event identifiers are safe. Producers MUST use static, safe-by-construction event names and field-specific approved values. Raw headers, cookies, Authorization credentials, session identifiers, raw request paths, query strings, request/response bodies, SQL, environment secrets, arbitrary exception messages, personal information, and unchecked strings are not approved operational context.

There is no `redaction.enabled` flag, `logging.redaction` subtree, classifier override, mutable alias registry, configurable redaction mode, debug/raw passthrough, or alternate sink for rejected data. A redactor non-match never authorizes emission of an otherwise unsafe value.

## Tests and architectural restrictions

- Package unit tests cover severity filtering, message/context admission, mandatory shared redaction, classifier failures, both deterministic formatters, exact serialized byte limits, UTC timestamps, daily filename selection, and destination I/O isolation.
- Package contract tests cover config and declarative DI safety, required security ports, secret suppression, module Composer metadata, and Foundation noop behavior.
- Package integration tests cover explicit Foundation/Logging provider precedence, stream/file selection, canonical service identity, correlation/UoW lifecycle, and reset isolation.
- Kernel-owned architecture and integration gates retain ownership of selected module plans, required dependency closure, source/compiled container parity, and default `micro` behavior.

Tests may isolate the fixed STDERR stream through a PHP subprocess and process-local stream-wrapper substitution or use temporary directories for file-mode failures. They do not authorize public arbitrary stream injection, logging-owned module-planning implementations, or test-only production dependency expansion.

## Non-goals

- HTTP access-log middleware or transport-specific request inspection.
- Raw logging of arbitrary PSR-3 messages, Throwable objects, request payloads, or environment/config secrets.
- New `LoggerInterface`, generic JSON encoder, redaction engine, classifier vocabulary, context store, correlation provider, reset service, or tracing/metrics exporter.
- Configurable stream URIs, network destinations, vendor logging SDKs, formatter registries, or separate file logger pipelines.
- Guaranteed atomic writes, automatic retries, disk quota management, retention, archival, compression, deletion, or storage-size-based rotation.
- Kernel module selection, declarative config rule types, source/compiled container compilation, and build artifact ownership.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Logging package source](https://github.com/coretsia/monorepo/tree/main/packages/platform/logging)
- [Logging Policy SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/logging-policy.md)
- [Sensitive Data Redaction SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/sensitive-data-redaction.md)
- [Context Lifecycle SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/context-lifecycle.md)
- [Context Keys SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/context-keys.md)
- [Observability SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability.md)
- [Observability and Errors SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability-and-errors.md)
- [Config Roots SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/config-roots.md)
- [Modes SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/modes.md)
- [Modules and Manifests SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/modules-and-manifests.md)
- [Runtime Container Definitions SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/runtime-container-definitions.md)
- [Compiled Container SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/compiled-container.md)
- [ADR-0037: Platform logging PSR-3 backend](https://github.com/coretsia/monorepo/blob/main/docs/adr/ADR-0037-platform-logging-psr3.md)
