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

# Trace Context Propagation SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: platform/tracing
```

## Scope

This document is the Single Source of Truth for transport-neutral, deterministic, bounded, fail-closed W3C `traceparent` extraction and injection provided by `platform/tracing` through the existing `ContextPropagationInterface`.

It governs the accepted carrier boundary, the exact normalized context, W3C version and flag handling, existing-header validation, same-carrier identity mutation, `tracestate` pass-through and removal, failure precedence, and the separation between W3C trace identity and Kernel-owned context.

The supported capability is **traceparent-only propagation**, not a recording tracer or complete W3C Trace Context forwarding implementation. This document does not define recording, exporting, sampling decisions, active spans, HTTP integration, persistence, or a trace viewer.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

One existing tracing port provides an explicit, reproducible way to extract a safe W3C trace identity from an incoming carrier and inject that identity into an independently supplied outgoing carrier.

The propagation implementation MUST:

- return only validated, canonical trace identity values;
- preserve unrelated contract-valid carrier entries without interpreting or rewriting them;
- reject malformed or untrusted tracing fields without generating identities or disclosing raw carrier values;
- isolate propagation failures from the primary application outcome;
- remain stateless, transport-neutral, and independent of Kernel-owned context and lifecycle state.

## Authority and ownership

`core/contracts` owns the format-neutral `ContextPropagationInterface` signature and accepted carrier PHP type. `platform/tracing` owns the W3C traceparent-specific validation and mutation policy described here, not a new carrier contract.

The implementation boundary is:

```text
packages/core/contracts/src/Observability/Tracing/ContextPropagationInterface.php
packages/platform/tracing/src/W3c/W3cTraceContextPropagation.php
packages/platform/tracing/src/Provider/TracingServiceProvider.php
packages/platform/tracing/src/Module/TracingModule.php
packages/platform/tracing/config/tracing.php
packages/platform/tracing/config/rules.php
```

`core/foundation` remains the sole owner of the canonical `NoopTracer`, `NoopSpan`, `NoopContextPropagation`, and `ContextStore` implementations. Kernel owns target-local `ModulePlan` selection, provider ordering, UnitOfWork lifecycle, correlation identity, and reset orchestration.

The package MUST NOT create a second tracer, span, context store, reset owner, transport normalization layer, or W3C identity generator. Direct production source dependencies are limited to `core/contracts` and `core/foundation`, as specified in `docs/architecture/DEPENDENCIES.md`.

## Canonical port and carrier boundary

The existing port has exactly these public methods:

```php
public function extract(array $carrier): array;

public function inject(array $carrier, array $context = []): array;
```

Their PHPDoc shapes remain:

```text
extract: array<string,string|list<string>> -> array<string,mixed>
inject:  array<string,string|list<string>>, array<string,mixed>
         -> array<string,string|list<string>>
```

The accepted carrier is a PHP array with string keys and values that are strings or lists of strings. A value in the `traceparent` field MAY use either one string or a list containing **exactly one string**. Both representations MUST pass through the identical canonical parser. An empty list, multi-value list, associative array, or nested list is not a valid traceparent candidate.

A transport owner MUST validate unrelated transport fields that fall outside this existing interface contract. `platform/tracing` MUST NOT widen the port, promise a contract-valid output for an out-of-contract unrelated field, or add a general-purpose header validator, serializer, sanitizer, or normalization pipeline.

Contract-valid unrelated entries MUST retain their original keys, values, bytes, list structure, and caller ownership. They MUST NOT be semantically parsed, normalized, classified, copied into extracted context, or redacted by this propagation helper. A caller-owned `baggage`, `Authorization`, `Cookie`, or `x-request-id` field is not automatically a W3C context field.

Malformed `traceparent` values are explicitly handled defensively, including values outside the carrier's documented value type. The propagator MUST reject integers, floats, booleans, objects, resources, invalid lists, and `Stringable` instances without scalar coercion, `__toString()`, reflection, serialization, or an application-visible parsing exception. This defensive behavior does not redefine the accepted carrier contract.

## Normalized context shape

Successful `extract()` returns exactly this string-keyed map, in this canonical key order:

```php
[
    'trace_id' => '4bf92f3577b34da6a3ce929d0e0e4736',
    'parent_id' => '00f067aa0ba902b7',
    'trace_flags' => '03',
]
```

The required keys are `trace_id`, `parent_id`, and `trace_flags`; their values MUST be canonical strings. No header version, raw `traceparent`, `tracestate`, baggage, arbitrary extension, or unrelated carrier field belongs in extracted context. Invalid or absent trace context yields exactly `[]`.

`inject()` accepts exactly these three context keys, with no additional or missing entries. Input associative-array key insertion order MUST NOT affect validation or the output value. Any non-string component, uppercase or malformed identifier, zero identifier, invalid flag value, or extra context key MUST reject injection. Implicit conversion of integer, float, boolean, resource, object, or `Stringable` values is forbidden.

The normalized IDs are values returned to or supplied by the caller, not handles to mutable runtime state. `trace_id` identifies the W3C trace; `parent_id` identifies an **upstream parent**, not a newly generated local `span_id`.

## Traceparent version-00 validation

Canonical version `00` has exactly 55 ASCII bytes:

```text
00-<32 lowercase hex trace-id>-<16 lowercase hex parent-id>-<2 lowercase hex trace-flags>
```

The parser MUST enforce all of the following:

- the version is exactly `00`, with separators `-` at zero-based byte offsets 2, 35, and 52;
- `trace_id` is exactly 32 lowercase hexadecimal characters and is not 32 zeroes;
- `parent_id` is exactly 16 lowercase hexadecimal characters and is not 16 zeroes;
- `trace_flags` is exactly two lowercase hexadecimal characters with no unsupported/reserved bit set;
- no trailing bytes, additional fields, control bytes, alternative separators, uppercase hexadecimal, or partial fields are accepted;
- parsing is byte-oriented, deterministic, and independent of locale.

Version-00 `trace_flags` accepts **only** `00`, `01`, `02`, or `03`. Reserved-bit values including `04`, `05`, `80`, and `ff` MUST fail closed. Coretsia deliberately applies this strict version-00 ingress policy; it MUST NOT silently clear reserved bits and reinterpret an invalid version-00 value as a different valid header.

## Forward-version parsing and downgrade

The version field is two lowercase hexadecimal characters. Version `ff` is always invalid. Versions `01` through `fe` follow the bounded forward-compatibility policy:

- the candidate MUST contain at least 55 bytes and no more than 512 bytes;
- the version, separators, trace ID, parent ID, and trace-flags positions in the first 55 bytes MUST follow the same canonical field offsets;
- the trace ID and parent ID MUST satisfy the same lowercase-hexadecimal and nonzero rules;
- any additional bytes MUST begin with `-` immediately after byte 55 (zero-based offset 55); a 55-byte forward-version header without an extension is also accepted;
- ASCII control bytes (`0x00..0x1f` and `0x7f`) are forbidden throughout the candidate;
- unknown extension bytes MUST NOT be decoded, interpreted, persisted, or returned to callers.

For an accepted forward version, only the supported trace-flags bits are retained. The extracted context is normalized to the same three-field model; `inject()` constructs canonical version `00`, removes unsupported bits and unknown extensions, and treats that change as a traceparent mutation. A forward-version value is never forwarded byte-identically as the outgoing version `00` traceparent.

This forward-version downgrade policy MUST NOT be conflated with rejection of reserved bits in **version `00`**.

## W3C Trace Context Level 2 flags

`trace_flags` is a bitfield, not a boolean or a single sampled marker:

- `0x01` is the **sampled** bit; sampled is true precisely when `(flags & 0x01) !== 0`;
- `0x02` is the **random-trace-id** bit; it is set precisely when `(flags & 0x02) !== 0`;
- the supported-bit normalization mask is `0x03`;
- valid normalized values are `00` (neither), `01` (sampled), `02` (random-trace-id), and `03` (both).

Extraction MUST preserve both supported bits: `03` remains `03`, not `01`; `02` remains `02`, not `00`. Accepted forward-version reserved bits are cleared **only** as part of explicit version-00 downgrade. Version-00 input with reserved bits MUST be rejected, not normalized.

The random-trace-id bit MUST remain unchanged whenever the same `trace_id` is continued. The bit is metadata; it MUST NOT be treated as proof of actual entropy or randomness. This propagator MUST NOT generate trace IDs or make sampling decisions.

## Existing traceparent and same-carrier injection

`inject()` receives its carrier and normalized context explicitly. It MUST validate the supplied context, and MUST validate a pre-existing `traceparent` before treating its identity as continuous or retaining any associated `tracestate`.

A carrier without `traceparent` MAY receive a new canonical `traceparent` when the caller independently supplies a valid normalized context. The propagator MUST NOT infer such context from a `ContextStore` or manufacture a new identity.

When there is exactly one valid existing `traceparent`, the implementation compares its parsed identifiers and supported flag bits with the requested outgoing context:

- if `trace_id` is unchanged, changing the random-trace-id bit (`0x02`) is forbidden, even when `parent_id` changes;
- if `parent_id` is unchanged, changing the sampled bit (`0x01`) is forbidden, even when `trace_id` changes;
- if `trace_id` and `parent_id` are both unchanged, neither supported flag bit may be changed;
- changing the sampled bit with an explicitly different `parent_id` MAY be accepted if all other continuity checks pass; that new parent identity is caller-supplied, never generated by the propagator;
- changing the trace ID, parent ID, or allowed flags creates a new canonical outgoing value and invokes the tracestate mutation policy.

A prohibited flag mutation MUST produce the same fail-closed injection result as any invalid existing traceparent: remove all existing `traceparent` and `tracestate` fields from the **returned** carrier, preserve unrelated entries, and emit no new tracing field.

A stateless propagator cannot establish the provenance or continuity of contexts transferred between independent carriers. The caller owns cross-carrier identity decisions. The propagator MUST NOT claim an upstream `parent_id` is a local span ID, allocate a local span ID, or synchronize a separate carrier with Kernel identity.

## Traceparent and tracestate pass-through policy

An accepted injection emits exactly one canonical lowercase `traceparent` key in the returned carrier. The caller's supplied carrier array is not mutated in place.

The existing caller-owned `tracestate` value is retained unchanged **only** when an existing `traceparent` value has been validated and is byte-identical to the required outgoing canonical version-00 value. A single-string-list existing traceparent qualifies when its sole string is byte-identical; the outgoing traceparent representation is still one canonical string.

Otherwise `inject()` MUST remove all case variants of `tracestate`, including when:

- no existing traceparent was supplied, even if a valid context is injected;
- the traceparent is replaced or its identity/flags change;
- an accepted forward-version traceparent is downgraded to version `00`;
- an existing traceparent is malformed, duplicated, oversized, invalid, or fails continuity checks;
- the supplied normalized context is missing or invalid.

The package does not parse, create, enrich, normalize, validate vendor semantics, or serialize `tracestate`. It does not support baggage propagation. Byte-identical same-carrier retention is **not** general cross-carrier forwarding.

In particular, `extract($incomingCarrier)` returns only the three normalized strings. A subsequent `inject($outboundCarrier, $context)` cannot transfer opaque incoming `tracestate` to another carrier through that normalized context. Full cross-carrier tracestate forwarding and its validation/privacy boundary require a separately approved transport-owned contract. An implicit raw-header store is forbidden.

The Foundation `NoopContextPropagation` fallback is **not a sanitization boundary**: its `extract()` returns `[]`, while its `inject()` returns the carrier unchanged, including pre-existing malformed tracing headers. Its behavior MUST NOT be represented as the W3C fail-closed mutation policy.

## Bounded carrier processing and failure precedence

The tracing package owns fixed resource limits; they are not configurable:

```text
maximum accepted carrier entries: 128
maximum traceparent candidate bytes: 512
exact version-00 traceparent bytes: 55
valid traceparent list size: 1 string
```

Tracing header-name recognition MUST compare the byte length before ASCII case-insensitive matching:

- `traceparent` has exactly 11 ASCII bytes;
- `tracestate` has exactly 10 ASCII bytes;
- ASCII case variants of these exact names are equivalent;
- long, non-ASCII, or unrelated names MUST NOT be lowercased, Unicode-normalized, semantically interpreted, or passed through an unbounded normalization pipeline.

`extract()` and `inject()` MUST use equivalent tracing-header matching semantics. No implicit type conversion, `Stringable::__toString()`, reflection, or serialization is permitted for malformed tracing-field input.

The deterministic decision order is:

1. **Carrier size.** `extract()` returns `[]` immediately for more than 128 entries, without inspecting field values. `inject()` emits no new traceparent and performs only a key-oriented removal of exact case-insensitive `traceparent` and `tracestate` names; it preserves all unrelated entries without inspecting their values. The PHP array entry count is authoritative.
2. **Carrier names and traceparent cardinality.** For carriers within the limit, non-string keys fail extraction; injection removes tracing fields and preserves unrelated entries. More than one ASCII-case-insensitive traceparent key is invalid regardless of whether their values agree.
3. **Traceparent value shape and byte bound.** A traceparent must be one string or a one-string list, no longer than 512 bytes. Invalid types, empty/multi-value lists, or oversized values are rejected without conversion.
4. **W3C syntax and identity.** Invalid version, separators, control bytes, hex characters, length, zero identifiers, and version-specific flags invalidate the existing traceparent.
5. **Injection context and continuity.** Injection additionally requires exactly three valid normalized context entries and, when an existing header is present, satisfaction of the same-carrier flag-mutation policy.
6. **Outbound mutation.** Only a validated case may emit canonical traceparent. Tracestate retention is decided by exact existing/outgoing traceparent byte equality; all other cases remove tracing fields according to this document.

These rules specify the observable failure result; an implementation MAY short-circuit once rejection is determined and MUST NOT leak which raw input failed into diagnostics. Normal tracing-field validation does not transfer responsibility for unrelated invalid transport fields from their transport owner.

## Invalid and missing input behavior

For missing, malformed, duplicate, conflicting, invalid-type, oversized, or invalid-version traceparent input, `extract()` MUST return `[]` without throwing or exposing raw headers.

`inject([], [])` MUST return `[]`. An empty/default context is **not** a valid normalized tracing context; it MUST NOT generate a trace identity. With an invalid or missing context, `inject()` MUST return the original carrier with all `traceparent` and `tracestate` case variants removed and unrelated entries unchanged. The same removal applies to invalid existing traceparent input even when an otherwise valid outgoing context was supplied.

A valid normalized context MAY be injected into a carrier with no existing traceparent. Its output is always canonical version `00`; an existing orphan tracestate MUST be removed. Invalid context MUST NOT authorize pass-through of an unvalidated existing traceparent or tracestate.

The over-limit injection rule is a deliberate fail-closed exception: only tracing-field keys are removed; the package does not inspect, convert, or attempt to sanitize unrelated values, including values outside the existing carrier contract.

## Context, UnitOfWork, and lifecycle boundaries

W3C `trace_id` and `parent_id` MUST NOT be silently converted to, stored as, or equated with Kernel `correlation_id`, `request_id`, or `uow_id`. Those identifiers have separate ownership and semantics; neither a correlation ID nor a UnitOfWork ID is a W3C trace or span ID.

`W3cTraceContextPropagation` MUST operate solely on its explicit carrier/context arguments. It MUST NOT depend on `ContextAccessorInterface`, `CorrelationIdProviderInterface`, or `ContextStore`; it MUST NOT read, write, snapshot, export, synchronize, or reset Kernel/Foundation context. It MUST NOT generate a trace/span/correlation/request/UoW identifier.

The implementation MUST be stateless across repeated calls and UnitOfWork boundaries. No process-lifetime registry, active-span stack, hook, reset tag, mutable context holder, or recording lifecycle is introduced. Foundation retains ownership of UoW-local context reset, and Kernel retains lifecycle orchestration.

## Module selection, configuration, and DI

The reserved package-owned configuration root is `tracing`. Its package defaults are exactly `[]`, and its declarative ruleset is exactly:

```php
[
    'schemaVersion' => 1,
    'configRoot' => 'tracing',
    'additionalKeys' => false,
    'keys' => [],
]
```

Kernel config validation MUST reject unknown keys, including `propagation` and `propagation.mode`. No `w3c|noop` selector, enabled switch, sampling/export configuration, runtime tracing factory, or alternate propagation binding is part of this contract.

Explicit target-local `ModulePlan` selection of `platform.tracing` contributes one direct declarative binding:

```text
ContextPropagationInterface -> W3cTraceContextPropagation
```

When the module is absent or excluded, Foundation's existing binding remains:

```text
ContextPropagationInterface -> NoopContextPropagation
```

`TracerPortInterface` remains bound to Foundation `NoopTracer` in either case. No additional service alias, span implementation, config repository reference, factory-method descriptor, tag, reset registration, sampler, exporter, meter, or logger is permitted as a tracing-owned side effect. Provider selection/order remain Kernel-owned; source and compiled container resolution MUST be equivalent. Installation of the package alone MUST NOT enable the module.

## Observability, errors, and security

The propagator MUST NOT create spans, metrics, log records, exported telemetry, diagnostic output, request instrumentation, or a persistent trace history. It MUST NOT read or emit raw `traceparent`, `tracestate`, baggage, credentials, tokens, cookies, Authorization, SQL, request/response bodies, profile payloads, or private customer data as diagnostic or normalized context values.

Parsing an absent, malformed, conflicting, or oversized tracing field MUST fail without throwing. Tracing infrastructure failures MUST NOT replace the primary operation result or exception. If application code calls Foundation `TracerPortInterface::inSpan()`, the original callback `Throwable` MUST remain observable; this propagator MUST NOT intercept or suppress it.

The normalized three-string context is the only extraction output. No unvalidated string is converted into a span name, span attribute, metric label, or logger field. The package MUST NOT implement a second redactor, sensitive-data classifier, serializer, security registry, or warn-only logger. Any future recording/export sink must obey the existing shared observability and redaction contracts at its own boundary.

The tracing package does not create an HTTP middleware implementation. HTTP carrier acquisition, unrelated header validation, request/response lifecycle, and any future cross-carrier tracestate security boundary belong to the transport owner. No recording or persisted trace viewer can be inferred from successful propagation.

## Deterministic examples

The examples use synthetic identifiers only.

Canonical extraction and cross-carrier injection:

```php
$incoming = [
    'TrAcEpArEnT' => ['00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-03'],
    'tracestate' => 'vendor=opaque',
    'authorization' => 'caller-owned',
];

$context = $propagation->extract($incoming);
// [
//     'trace_id' => '4bf92f3577b34da6a3ce929d0e0e4736',
//     'parent_id' => '00f067aa0ba902b7',
//     'trace_flags' => '03',
// ]

$outbound = $propagation->inject(['x-request-id' => ['safe']], $context);
// [
//     'x-request-id' => ['safe'],
//     'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-03',
// ]
```

No incoming `tracestate` is transferred into `$outbound`. The incoming `authorization` field also does not become extracted context.

Same-carrier canonical pass-through:

```php
$carrier = [
    'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-03',
    'tracestate' => 'vendor=opaque',
];

$propagation->inject($carrier, $context);
// [
//     'tracestate' => 'vendor=opaque',
//     'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-03',
// ]
```

The traceparent **value** is byte-identical and validated; only in this case does the existing tracestate remain intact.

Accepted forward-version downgrade:

```text
incoming: 01-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-ff-future
extracted trace_flags: 03
outgoing: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-03
tracestate: removed from the returned carrier
```

The same `ff` flags in **version `00`**, or version `ff` itself, MUST be rejected. With a malformed existing traceparent, injection removes tracing fields even if the caller offers a valid normalized context.

## Verification and acceptance criteria

Unit, contract, and integration tests MUST cover the following deterministic matrix without creating a second parser or transport abstraction:

- valid version-00 fixtures for flags `00`, `01`, `02`, and `03`, including exact three-key extraction and canonical lowercase injection;
- byte-identical extraction of string and one-string-list traceparent values;
- malformed versions, `ff` version, separators, uppercase hex, invalid lengths, control bytes, zero IDs, and version-00 reserved flags;
- accepted forward versions, unknown extensions, supported-bit masking, version-00 downgrade, and tracestate removal;
- missing/duplicate ASCII-case-varied tracing headers, invalid list shapes and scalar/object/resource inputs, without conversion or exception;
- context key order independence, exact three-key validation, forbidden flag mutations, and explicitly caller-provided changed parent identities;
- 128 versus 129 carrier entries; 512 versus 513 candidate bytes; late tracing fields; long unrelated names; locale-independent matching; over-limit key-only removal;
- unchanged unrelated contract-valid strings and string lists; invalid unrelated values remaining a transport-owner responsibility;
- identical traceparent retention of existing same-carrier tracestate versus its removal on replacement, downgrade, invalid input, orphan state, or separate-carrier injection;
- absence of synthetic IDs, `ContextStore` access, spans, logs, metrics, reset registrations, and cross-UnitOfWork state;
- explicit selected-module W3C binding, unselected Foundation noop fallback, and source/compiled provider parity;
- identical results on repeated calls, no raw-header diagnostics, no thrown transport-parse exceptions, and preservation of the application's primary callback exception.

A test MUST NOT redefine the accepted interface carrier shape to accommodate malformed tracing input, nor assert contract-valid output for an unrelated field that violated the caller's transport precondition. No test is permitted to imply that Foundation noop propagation sanitizes headers or that this package forwards complete W3C tracestate across carriers.

## Non-goals

This SSoT does not introduce or define:

- a recording tracer, local span creation, span duration tracking, active-span lifecycle, sampler, exporter, telemetry endpoint, storage, or trace viewer;
- Jaeger, Grafana Tempo, vendor tracing SDKs, or any tracing metrics catalog;
- automatic HTTP middleware or cross-process header forwarding;
- parsing, validation, enrichment, storage, or cross-carrier propagation of opaque `tracestate` and baggage;
- implicit conversion between W3C and Kernel context identities, a second context store, new reset hooks, or tracing-specific configuration selectors;
- a package-local carrier validator, generalized sanitizer, serializer, redaction implementation, or alternate dependency registry.

## Normative references

- [W3C Trace Context Level 2](https://www.w3.org/TR/trace-context-2/) — `traceparent` version handling and sampled/random-trace-id flag semantics; Coretsia's strict version-00 reserved-bit rejection and bounded fail-closed behavior are additional local policies.
- `docs/ssot/observability.md` — canonical span/metric naming, label and diagnostic boundaries.
- `docs/ssot/observability-and-errors.md` — existing observability failure and error ownership.
- `docs/ssot/context-keys.md` — canonical Foundation context keys and identity separation.
- `docs/ssot/context-lifecycle.md` and `docs/ssot/reset-tags.md` — ContextStore lifecycle and Foundation reset ownership.
- `docs/ssot/modules-and-manifests.md` and `docs/ssot/runtime-container-definitions.md` — target-local module selection and declarative provider/container policy.
- `docs/ssot/config-roots.md` and `docs/ssot/config-and-env.md` — reserved config root and Kernel validation boundaries.
- `docs/ssot/http-middleware-catalog.md` — transport middleware ownership.
- `docs/ssot/sensitive-data-redaction.md` — shared sensitive-data policy for future observability sinks.
- `docs/architecture/DEPENDENCIES.md` — authoritative package-level direct source dependency matrix.
