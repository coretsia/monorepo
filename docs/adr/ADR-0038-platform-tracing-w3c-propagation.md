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

# ADR-0038: Platform tracing W3C traceparent propagation

```yaml
adrVersion: 1
status: pre-accepted
owner: platform/tracing
```

## Context

Coretsia already defines `TracerPortInterface`, `SpanInterface`, and `ContextPropagationInterface` in `core/contracts`. Foundation provides the canonical `NoopTracer`, `NoopSpan`, and `NoopContextPropagation` implementations and owns its `ContextStore` and UnitOfWork-local reset behavior. Kernel owns target-local module selection, provider order, and lifecycle orchestration.

The existing noop propagation is a deliberate disabled behavior: extraction returns `[]` and injection leaves the caller's carrier unchanged. It is not a tracing-header validation or sanitization boundary. Replacing it globally would enable propagation for applications that did not select tracing; creating platform-local noop tracing classes would duplicate existing contracts and lifecycle ownership.

Applications nevertheless need a transport-neutral way to validate an incoming W3C trace identity and explicitly inject that identity into a separate carrier. This capability is useful before any recording backend exists: it gives application and future transport consumers a bounded, deterministic identity exchange without making span recording, HTTP integration, or telemetry export an implicit dependency.

The relevant decisions are therefore about optional provider selection, the exact scope of W3C parsing, opaque `tracestate` handling, and the boundary between propagation and the existing Kernel/Foundation context lifecycle. The normative behavior is specified in `docs/ssot/tracecontext.md`.

## Decision

### Decision 1: Reuse Foundation noop tracing instead of introducing another tracer lifecycle

`core/foundation` remains the sole owner of `NoopTracer`, `NoopSpan`, and `NoopContextPropagation`. The disabled propagation path continues to resolve `ContextPropagationInterface` to Foundation `NoopContextPropagation`; its existing pass-through injection contract is unchanged.

`platform/tracing` does not introduce `DefaultTracer`, `DefaultSpan`, platform-local noop implementations, an active span stack, or an alternative `TracerPortInterface` binding. Foundation's existing tracer/span behavior and callback-`Throwable` precedence remain authoritative. A propagation-only package has no reason to allocate local span identities, track durations, or perform lifecycle resets.

This reuse keeps one canonical implementation of disabled tracing, prevents divergent noop behavior, and allows subsequent recording implementations to depend on the existing public ports rather than a competing platform tracing abstraction.

### Decision 2: Enable W3C propagation only through target-local ModulePlan selection

Installing `coretsia/platform-tracing` does not enable its provider. Kernel's explicit target-local `ModulePlan` and deterministic provider order select the module `platform.tracing`, which requires `core.foundation`.

When tracing is unselected or excluded, Foundation retains:

```text
ContextPropagationInterface -> NoopContextPropagation
```

When the module is explicitly selected, the tracing provider overrides only that service identity:

```text
ContextPropagationInterface -> W3cTraceContextPropagation
```

There is no configurable `w3c|noop` mode, `propagation.mode`, enabled switch, `TracingServiceFactory`, or runtime selection service. The reserved package-owned `tracing` root has empty defaults and an empty, closed ruleset. Kernel configuration validation rejects unknown tracing keys. Runtime configuration is not consulted to choose the implementation.

### Decision 3: Use one declarative binding and preserve source/compiled parity

`TracingServiceProvider` contributes exactly one direct `ContainerDefinitionBuilder::classService()` definition for `ContextPropagationInterface` to `W3cTraceContextPropagation`, with no constructor arguments, aliases, tags, or factory-method descriptor.

The provider uses the established Foundation declarative definition-provider path. It does not resolve a `ConfigRepositoryInterface`, execute a closure or factory to choose a propagation mode, or construct runtime state. Kernel remains the owner of module/provider planning; source and compiled containers consume equivalent ordered definitions and resolve the same selected or fallback implementation.

Selection must not change Foundation's `TracerPortInterface` binding, other services, tag registry, or reset definitions. The only intended service-graph difference is the implementation behind `ContextPropagationInterface`.

### Decision 4: Limit the transport-neutral contract to validated traceparent values

`W3cTraceContextPropagation` implements the existing `ContextPropagationInterface` without widening the accepted carrier type `array<string,string|list<string>>`. It consumes only explicit `extract($carrier)` and `inject($carrier, $context)` arguments and returns a successful extraction as exactly three canonical strings: `trace_id`, `parent_id`, and `trace_flags`.

Version `00` requires exactly 55 ASCII bytes, lowercase hexadecimal identifiers, fixed separators, nonzero trace and parent IDs, and the supported trace-flags values. Version `ff` is rejected. Versions `01..fe` use bounded forward-compatible parsing; accepted extra fields are not interpreted or retained, and injection emits canonical version `00`.

Processing is bounded to 128 carrier entries and 512 bytes per traceparent candidate. Header names are matched by exact ASCII case-insensitive `traceparent`/`tracestate` names with byte-length checks. A traceparent field accepts one string or a list containing exactly one string; duplicate case-variant names, malformed values, invalid lists, and unsupported types fail closed without implicit conversion.

The propagator does not validate or normalize unrelated transport fields. Contract-valid caller-owned fields are preserved without semantic inspection; unrelated values outside the port's PHP type contract remain the transport owner's validation responsibility. The exhaustive parsing and failure precedence are normative in `docs/ssot/tracecontext.md`, not duplicated in a second validator.

### Decision 5: Preserve Level 2 trace flags without inventing sampling or random identity

The W3C Trace Context Level 2 sampled bit is `0x01`, and the random-trace-id bit is `0x02`. The supported mask is `0x03`, with normalized outputs `00`, `01`, `02`, and `03`.

Coretsia deliberately rejects version-00 input containing reserved trace-flags bits rather than silently rewriting it. An accepted forward-version value is different: unsupported bits are discarded when explicitly downgrading to version `00`, while supported bits are retained. A propagator neither makes sampling decisions nor infers the actual entropy of an incoming trace ID from the random-trace-id flag.

For an existing traceparent in the carrier supplied to `inject()`, unchanged `trace_id` forbids changing the random-trace-id bit; unchanged `parent_id` forbids changing the sampled bit. An allowed sampled-bit change with a different parent ID uses the caller's explicit identity. A prohibited mutation removes tracing fields from the returned carrier and emits no replacement. The propagator cannot establish continuity or provenance across independent carriers.

### Decision 6: Keep opaque tracestate caller-owned and defer cross-carrier forwarding

The normalized three-field context cannot carry opaque `tracestate` entries. Extraction does not parse or return `tracestate` or baggage, and injection does not synthesize them. A caller performing `extract($incomingCarrier)` followed by `inject($outboundCarrier, $context)` therefore does not transfer incoming `tracestate` between independent carriers.

An existing caller-owned `tracestate` is retained unchanged only when the existing traceparent is valid and its value is byte-identical to the required canonical outgoing version-00 value. Otherwise injection removes all case variants of `tracestate`, including on traceparent replacement, accepted forward-version downgrade, missing or invalid context, invalid existing traceparent, and carriers with an orphan tracestate value. Unrelated contract-valid fields remain unchanged.

Full W3C Trace Context forwarding requires decisions about validating opaque vendor entries, privacy, cross-carrier provenance, and transport ownership that the three-string public context cannot express. Adding a hidden raw-header store, an implicit ContextStore bridge, or unconditional opaque-state forwarding would cross a security boundary without an explicit contract. This decision intentionally provides **traceparent-only** propagation, not complete W3C Trace Context forwarding compliance.

### Decision 7: Keep Kernel and Foundation context identities and resets independent

W3C `trace_id` is not Kernel `correlation_id` or `request_id`. The extracted `parent_id` is an upstream parent identity, not a generated local `span_id` or a Kernel `uow_id`.

The implementation has no `ContextAccessorInterface`, `CorrelationIdProviderInterface`, or `ContextStore` constructor dependency and does not read, write, snapshot, reset, or synchronize Foundation/Kernel context. It does not generate a trace ID, span ID, correlation ID, request ID, or UnitOfWork ID. No new process-lifetime registry, UnitOfWork-local tracing state, hook, reset service, or reset tag is registered.

Foundation continues to own its existing ContextStore reset registration. Kernel continues to own UnitOfWork lifecycle, correlation identity, and reset execution. Trace identity propagation cannot implicitly create or advance an active span lifecycle.

### Decision 8: Fail closed without replacing primary application outcomes

Missing, malformed, duplicated, invalid-type, conflicting, or oversized tracing data returns an empty extracted context without a transport-parsing exception. Invalid injection context, invalid existing traceparent, and prohibited flag mutations emit no new traceparent; existing `traceparent` and `tracestate` fields are removed from the returned carrier while unrelated entries remain caller-owned.

For an input carrier exceeding 128 entries, injection performs only a key-oriented tracing-field removal pass: it neither inspects unrelated values nor constructs a partial outgoing tracing context. No raw tracing header, opaque vendor state, credential, payload, or previous exception is emitted as diagnostic output.

Propagation has no logging, metrics, span creation, exporter, or redaction implementation side effect. It cannot suppress or replace a primary operation result or exception. Existing Foundation tracer callback exceptions retain their original precedence. Future telemetry sinks must use the existing observability and shared redaction contracts rather than introducing an independent security or reporting pipeline in propagation.

### Decision 9: Leave application transport consumption and recording to their owners

The package exposes an application-resolvable propagation port but does not install an HTTP middleware or automatically extract headers during request handling. Until a transport or other application owner explicitly consumes the port, there is no automatic production request/response trace continuity.

`platform/http` owns any future HTTP middleware, carrier acquisition, and unrelated transport-header validation. A future recording tracer owns local span creation, identity generation, sampling decisions, durations, storage, and export. It may consume validated W3C identities without redefining this parser's canonical ingress rules. No trace files, exported spans, persisted history, telemetry endpoint, or trace viewer are delivered by this decision.

## Consequences

### Positive consequences

- an explicitly selected application target gains deterministic W3C traceparent extraction and injection without requiring a recording backend;
- Foundation noop tracing remains one canonical disabled behavior, and unselected applications remain unchanged;
- direct declarative wiring preserves source/compiled container parity without a selector factory;
- validated three-field context prevents opaque carrier values from entering tracing identity results;
- explicit same-carrier mutation rules and privacy-preserving tracestate removal prevent unvalidated tracing-field forwarding;
- Kernel correlation, UnitOfWork lifecycle, and Foundation ContextStore reset ownership stay independent of W3C propagation;
- later transports and recording tracers can consume the existing port without a competing lifecycle or new carrier contract.

### Trade-offs

- callers must explicitly select `platform.tracing` and call the propagation port; installation alone has no runtime effect;
- the feature does not record spans or provide trace history, sampling, exporting, or visibility into execution paths;
- a valid three-field context cannot independently forward incoming vendor `tracestate` to a new carrier;
- the strict version-00 reserved-bit policy may reject traceparent values that a more permissive receiver would accept;
- the stateless boundary cannot prove cross-carrier identity continuity or create a local parent/span identity on behalf of a caller.

## Rejected alternatives

### Introduce platform-local DefaultTracer, DefaultSpan, or noop wrappers

Rejected because Foundation already owns the canonical noop ports, implementations, and lifecycle behavior. Duplicating them creates competing disabled semantics and reset responsibility without providing recording functionality.

### Select w3c or noop using tracing configuration and TracingServiceFactory

Rejected because target-local `ModulePlan` already determines provider participation. A runtime selector would add an unnecessary configuration contract, factory path, and source/compiled divergence risk.

### Bind a tracing-owned tracer, ContextStore adapter, or reset hook

Rejected because propagation processes caller-supplied values only. Joining Kernel correlation or UnitOfWork context to W3C identity would conflate independent identities and introduce hidden mutable state.

### Forward tracestate through an implicit store or a richer private context

Rejected because the public normalized context has exactly three validated fields. An opaque state store would create undocumented cross-carrier forwarding and privacy exposure. Complete forwarding requires a separately approved transport/security contract.

### Generate trace/span identities or make sampling decisions during propagation

Rejected because generating a local span identity requires an explicit recording/span lifecycle owner. The incoming parent ID must not be misrepresented as a locally created span, and sampled/random flags are not authority to allocate identities.

### Add automatic HTTP middleware, recording, or an exporter to the propagation package

Rejected because HTTP integration and telemetry recording have different owners and dependencies. A transport-neutral baseline must not acquire request lifecycle, storage, logging, or exporter responsibilities.

## Non-goals

This decision does not define an active tracer, local span lifecycle, automatic identity generation, recording durations, sampling, exporter SDK, metrics catalog, telemetry storage, persisted trace history, human-readable trace viewer, HTTP middleware, or automatic cross-process propagation. It does not validate unrelated transport headers, implement baggage support, parse vendor tracestate, or provide complete W3C Trace Context forwarding. It introduces no new runtime configuration keys, services beyond the direct propagation binding, tags, reset hooks, or alternative context owner.

## Related SSoT

- `docs/ssot/tracecontext.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/context-keys.md`
- `docs/ssot/context-lifecycle.md`
- `docs/ssot/reset-tags.md`
- `docs/ssot/modules-and-manifests.md`
- `docs/ssot/runtime-container-definitions.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/http-middleware-catalog.md`
- `docs/ssot/sensitive-data-redaction.md`

## Related ADRs

- `docs/adr/ADR-0003-observability-errordescriptor-health-profiling-ports.md`
- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/adr/ADR-0015-context-bag-context-store-correlation-id.md`
- `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
- `docs/adr/ADR-0025-kernel-conflicts-exclusion-policy.md`
- `docs/adr/ADR-0029-kernel-container-compile-artifact.md`
- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md`
