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

# ADR-0010: Sensitive data redaction boundary

```yaml
adrVersion: 1
status: pre-accepted
owner: platform/redaction
```

## Context

Coretsia needs one deterministic, config-free defense-in-depth redaction capability for explicitly sensitive string values and recursive json-like diagnostic/output values. Eligible consumers require a contracts-level port without depending on a concrete redaction engine or copying generic classification logic into their own packages.

Redaction cannot establish whether an arbitrary producer value is safe for a destination. Error descriptors, Kernel operations, Context/UoW boundaries, observability sinks, secrets consumers, and other producers retain their own safe-by-construction contracts, semantic-key restrictions, omission rules, cardinality controls, and resource limits. An unclassified value is not thereby safe to emit.

The json-like value model, deterministic normalization, and structural resource accounting belong to `core/foundation`. Canonical stable-JSON byte framing also belongs to Foundation. Shared generic semantic classification and redaction belong to the `platform.redaction` runtime module, while explicit classification identifiers and failure/result contracts belong to `core/contracts`.

## Decision

### Decision 1: One contracts port and one default platform implementation

`core/contracts` defines the sole cross-package redaction port:

```text
Coretsia\Contracts\Security\SensitiveDataRedactorInterface
```

It exposes exactly `redactValue(string, RedactionKind, RedactionContext): RedactedValue` and `redactJsonLike(mixed, RedactionContext): mixed`. `RedactionContext`, `RedactionKind`, `RedactionMode`, `RedactedValue`, and `Coretsia\Contracts\Security\Exception\RedactionException` are contracts-level types. They contain no concrete classifier, hashing, DI, transport, or configuration dependency.

`platform/redaction` owns exactly one default implementation:

```text
Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor
```

`SensitiveKeyClassifier`, `SensitiveValueClassifier`, and `StableRedactionHasher` are package-internal, stateless implementation services, **not** cross-package extension points. Eligible consumers depend on `SensitiveDataRedactorInterface`, not those concrete helpers. The shared service is registered only by explicit application of the `RedactionServiceProvider` declared by `RedactionModule::providers()`; package/autoload presence, debug state, configuration, and environment never auto-enable it. Kernel owns module planning.

The `platform.redaction` module is the single owner of shared generic semantic redaction. A separate `platform/security` security capability does not own or introduce another generic redaction engine. This division does not import security backend or lower-layer owner policy into the redactor.

### Decision 2: Config-free, stateless, deterministic, fail-closed behavior

There is no config root, disable switch, policy callback, mutable registry, learned classifier state, environment-dependent rule, runtime service locator, `ContextStore`/`ContextAccessorInterface`, Kernel runtime, UoW, reset orchestration, or logger/tracer/meter/reporter dependency. The shared implementation keeps no raw inputs, partial outputs, or previous failure state. No redaction service emits logs, spans, metrics, or stdout/stderr.

Known-sensitive direct strings are always classified explicitly by the owner using `redactValue()`. Recursive `redactJsonLike()` applies the canonical immutable key/value classifiers and returns only Foundation-normalized json-like shapes. Semantic key classification takes priority over value classification and recursion, **after complete Foundation input normalization succeeds**. An unclassified map key whose original bytes match a high-confidence sensitive-value pattern fails with `sensitive-map-key`; the key is never returned.

A selected string or complete branch never appears unchanged or partially masked in a successful redacted output. Any input, traversal, encoding, or output-validation failure aborts the whole operation without returning a partial result. The only public redaction failure is contracts-level `RedactionException`, with the allowlisted, reason-only message `CORETSIA_REDACTION_FAILED: <reason>`. Unexpected Throwables map to `internal-failure` without retaining the original Throwable; explicit mapped reasons retain their original reason.

### Decision 3: Placeholder is the default; metadata disclosure is owner-selected

`RedactionContext` is an explicit immutable method argument carrying a bounded, stable operation scope and a `RedactionMode`. `Placeholder` is the default and discloses neither length nor hash. `Length`, `Hash`, and `HashAndLength` require explicit owner selection and disclose byte count, SHA-256 digest, or both.

One context mode applies uniformly to **every** selected branch within a single `redactJsonLike()` call. The redactor must not silently upgrade, downgrade, substitute, or fall back to another mode for a particular kind, field, classifier result, or branch. For heterogeneous boundaries or any possibly low-entropy/reconstructable value not approved for that metadata, the caller uses omission or `Placeholder`, or splits separately governed values into separate operations.

A deterministic hash and a byte length can be sensitive correlation or reconstruction metadata. Their presence is not declassification, encryption, admissibility for a destination sink, or proof of irreversibility. Owner-specific disclosure and destination policy remain authoritative.

### Decision 4: Domain-separated SHA-256 is byte-oriented

`StableRedactionHasher` owns the single SHA-256 implementation. The exact preimage is:

```php
"coretsia.redaction@1\0" . $context->scope() . "\0" . $kind->value . "\0" . $representation . "\0" . $bytes
```

The representation is exactly `string` for direct string/recursive string summaries and `json-like` for non-string branch summaries. The digest is exactly `sha256:` followed by 64 lowercase hexadecimal characters. No salt, time, host, process identifier, random bytes, or environment input participates. Representation is part of the **hash preimage only**; it never contributes to disclosed byte length. Distinct representation preimages are not a general claim of SHA-256 collision freedom.

### Decision 5: Foundation owns structural validation and resource budgets

`DefaultSensitiveDataRedactor` reuses `JsonLikeNormalizer::normalize()` and the immutable `JsonLikeNormalizationLimits(32, 10000, 65536, 1048576)` for direct strings, complete recursive input, and completed recursive output. Foundation owns forbidden type and non-string map-key rejection, depth, node count, individual string bytes, total string bytes, map sorting, and list ordering. The redaction package introduces **no** duplicate structural normalizer, forbidden-type validator, parallel resource-budget walker, or aggregate-byte counter.

Foundation's optional fourth `JsonLikeNormalizationLimits` field, `?int $maxTotalStringBytes = null`, is a domain-neutral resource-budget primitive. `null` retains the unbounded-aggregate behavior for callers that omit this optional limit; a positive integer activates aggregate accounting across string map keys and values. Zero is not an unlimited sentinel. Foundation performs that accounting in the same canonical traversal as its other validation, with individual-string limit precedence and its existing safe error-path policy. The redactor supplies a fixed positive aggregate bound, not a separate accounting pass.

Complete Foundation input normalization **precedes** redaction-specific semantic traversal, even for a sensitive structural key whose whole value branch will be replaced. Consequently a sensitive key cannot hide a forbidden nested type or an input-limit violation.

After normalization, a map key reached by semantic traversal must not contain an ASCII C0 control byte (`0x00..0x1F`) or DEL (`0x7F`). This is a redaction-specific output-safety check immediately before classification, not a second whole-input structural walk. Keys nested entirely inside an already selected complete branch are not visited solely for this policy. Foundation structural and resource failures win when they occur before semantic traversal.

### Decision 6: Foundation owns stable JSON framing; consumers own whole-result serialization

A selected string branch uses its exact original string bytes as summary input. For a non-string sensitive branch, `Length|Hash|HashAndLength` use `StableJsonEncoder::encodeStable()` on the normalized branch and remove **exactly the one encoder-owned final LF** from those summary bytes. This is the only use of stable JSON serialization inside redaction; branch byte materialization happens once and is reused for length/hash. `Placeholder` does not materialize branch bytes or invoke the stable JSON encoder.

The complete recursively redacted result is normalized with Foundation's fixed output limits and returned as a PHP json-like value. There is **no final whole-result stable JSON encode, LF revalidation, or byte-serialization pass**. Any destination serialization is consumer-owned and subject to the destination's own schema and emission policy.

### Decision 7: Lower-layer safe-by-construction boundaries remain authoritative

Lower-layer `core/contracts`, `core/foundation`, and `core/kernel` owners maintain their own boundary-specific validation, rejection, omission, and safe derivations. They **must not** acquire an upward dependency on `platform/redaction` to satisfy their safety contracts. In particular, Kernel Ops results are safe before the CLI receives them and do not consume this port. Owners able to depend on the port may reuse it for eligible generic redaction, but redaction does not grant an exception to `ErrorDescriptor` semantic-key restrictions, observability allowlists, absolute-path bans, metrics-label constraints, or source-schema/resource limits.

### Decision 8: No implicit interpretation of opaque values

Structural keys are canonicalized using ASCII lowercase and removal of only `-`, `_`, `.`, and ASCII space. The immutable alias table assigns documented kinds only. Value classification uses a deterministic ordered set of high-confidence **whole-value** ASCII grammars. Unknown keys, near-misses, and unclassified values return `null`, never permission to export them.

No JSON, URL, base64, JWT payload, SQL statement, provider payload, or arbitrary transport body is decoded, semantically parsed, or rewritten. Owner-known SQL, environment values, local paths, and other sensitive values without an unambiguous shared structural kind must use the owner's explicit `redactValue()` classification (or omission). Ambiguous vocabulary from another Core boundary is not mechanically imported as shared redaction aliases.

## Consequences

### Positive

- Eligible consumer packages share one contracts-level entrypoint and one canonical deterministic semantic policy.
- Fixed, stateless, config-free services give repeatable results without runtime context, observability, or reset dependencies.
- Foundation remains the single owner of json-like validity, aggregate resource accounting, and stable JSON framing.
- Explicit operation scope, kind, and representation domains prevent accidental equality of different canonical SHA-256 preimages.
- Safe-by-construction producer and destination policies retain full authority.

### Trade-offs

- A conservative classifier intentionally leaves some sensitive-looking values unclassified; producers cannot treat non-matches as safe.
- Some inputs fail closed, including sensitive-value-shaped map keys, unsupported json-like values, control-byte structural keys, and oversized input/output shapes.
- Non-placeholder summary modes can disclose sensitive metadata and therefore require owner approval for every potentially selected branch.
- Fixed limits, fixed alias vocabulary, and fixed disclosure modes require a deliberate policy change rather than runtime configuration.

## Rejected alternatives

### Duplicate generic redaction engines in eligible consumers

Consumer-local copies of generic key/value classification and redaction can drift. Eligible consumers use the contracts-level port; owner-specific safe-by-construction guards remain local and are not generic replacement engines.

### Config-driven classifiers and mutable policy registries

Runtime rule files, custom regex packs, callbacks, stateful learning, and environment-controlled classifiers make behavior ambiguous or nondeterministic. The platform implementation has one immutable canonical policy.

### Raw, debug, disabled, or passthrough modes

These options violate the rule that a selected sensitive branch is never returned unchanged and that redaction cannot be disabled through a runtime switch.

### Package-local structural validation or aggregate byte-budget walkers

A second normalizer or resource-limit traversal would compete with Foundation's canonical limits, failure precedence, map-key model, and deterministic sorting.

### Whole-result stable JSON serialization inside the redactor

A final encoder pass would transfer destination representation ownership to the package. The redactor returns a Foundation-normalized PHP value; consumers own sink encoding.

### Redactor-owned observability and runtime context

Self-logging, metrics, tracing, reporters, ContextStore reads, or reset orchestration would create another sensitive-output surface and additional runtime dependencies.

### Semantic payload inspection or decoding

Parsing JWTs, URLs, JSON, SQL, request/provider payloads, mail bodies, or other opaque payloads exceeds the canonical high-confidence classification boundary and may expose sensitive data.

### A competing `platform/security` generic engine

A distinct security capability does not duplicate the shared semantic redaction port or default implementation owned by `platform.redaction`.

## Non-goals

- Secret resolution, vault/cloud SDK adapters, secret storage, token validation, or authorization decisions.
- SQL parsing, email/HTTP body rewriting, PII rewriting, AI guardrails, or provider-payload inspection.
- Pluggable policy packs, dynamic classification registries, caller-selected matcher callbacks, and entropy-based classification.
- Sink schema approval, metrics-label generation, error/observability emission, or Throwable stack-trace sanitization.
- Kernel `ModulePlan` implementation, automatic module enablement, or changes to Kernel Ops production shapes.
- Filesystem/network/process work, side-effectful runtime configuration, or generated artifact ownership.

## Related SSoT

- `docs/ssot/sensitive-data-redaction.md` — canonical port, classifiers, traversal, hashing, modes, limits, and failures.
- `docs/ssot/json-like-runtime-values.md` — Foundation normalization, resource accounting, and stable JSON framing.
- `docs/ssot/runtime-container-definitions.md` — explicit canonical provider definitions and DI lifecycle.
- `docs/ssot/error-descriptor.md` — owner-specific safe field/extension admissibility.
- `docs/ssot/observability-and-errors.md` and `docs/ssot/observability.md` — emission and diagnostic safety.
- `docs/ssot/secrets-contracts.md` and `docs/ssot/config-and-env.md` — secret and environment ownership.
- `docs/ssot/modules-and-manifests.md` and `docs/ssot/application-dependency-sync.md` — module and dependency boundaries.

## Related ADRs

- `docs/adr/ADR-0004-foundation-json-like-runtime-values.md`
- `docs/adr/ADR-0013-secrets-port.md`
- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md`
- `docs/adr/ADR-0023-kernel-bootstrap-phase-a.md`
- `docs/adr/ADR-0024-kernel-module-plan-resolution.md`
