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

# ADR-0004: Foundation json-like runtime values

## Status

Accepted.

## Context

Coretsia runtime packages need one canonical baseline model for in-process json-like values used by diagnostics, stable serialization, context write validation, and Kernel UnitOfWork shape normalization.

Before this decision, the same baseline json-like policy existed in multiple runtime locations:

```text
Coretsia\Foundation\Serialization\StableJsonEncoder
Coretsia\Foundation\Context\ContextStorePolicy
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

Those implementations overlapped in the following rules:

- allowed scalar values;
- forbidden float values;
- forbidden object, closure, and resource values;
- non-string map-key rejection;
- recursive list/map walking;
- recursive map sorting by byte-order `strcmp`;
- list order preservation;
- safe deterministic diagnostics.

The prototype payload spike files under:

```text
framework/tools/spikes/payload/*
```

proved the baseline behavior, but they are tooling/prototype material and cannot become runtime dependencies.

Runtime packages must not depend on:

```text
devtools/*
tools/*
framework/tools/spikes/*
```

The existing Foundation stable JSON decision is recorded in:

```text
docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md
```

That ADR introduced `StableJsonEncoder` as a Foundation stable diagnostics primitive.

The existing Foundation context decision is recorded in:

```text
docs/adr/ADR-0015-context-bag-context-store-correlation-id.md
```

That ADR introduced `ContextStorePolicy` as the always-on context write guard.

The existing Kernel UnitOfWork shape decisions are recorded in:

```text
docs/adr/ADR-0021-unit-of-work-context-shape.md
docs/adr/ADR-0022-unit-of-work-result-outcome-policy.md
```

Those ADRs introduced Kernel-owned UnitOfWork context/result shapes and Kernel-local json-like shape validation.

The duplication became an architectural problem because:

- Foundation and Kernel both had recursive json-like walkers;
- Foundation stable JSON encoding and Foundation context value validation drifted toward separate local policies;
- Kernel owned both UoW-specific shape policy and baseline json-like value normalization;
- path-aware safe diagnostics were not consistently centralized;
- future runtime packages had no single baseline json-like primitive to reuse.

The canonical live SSoT for the new baseline model is:

```text
docs/ssot/json-like-runtime-values.md
```

## Decision

Coretsia will introduce a Foundation-owned runtime json-like value primitive.

The owner package is:

```text
core/foundation
```

The canonical implementation is:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

The canonical exception is:

```text
Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException
```

The baseline normalizer implementation paths are:

```text
framework/packages/core/foundation/src/Serialization/JsonLikeNormalizer.php
framework/packages/core/foundation/src/Serialization/Exception/JsonLikeNormalizationException.php
```

The stable JSON serialization consumer implementation paths are:

```text
framework/packages/core/foundation/src/Serialization/StableJsonEncoder.php
framework/packages/core/foundation/src/Serialization/StableJsonDecoder.php
```

The baseline model is governed by:

```text
docs/ssot/json-like-runtime-values.md
```

`core/foundation` owns the reusable baseline json-like runtime value model.

`core/kernel` consumes that baseline through its own UoW-specific wrapper.

`core/contracts` remains unchanged.

Runtime packages must not copy code from `framework/tools/spikes/payload/*`.

Runtime packages must not depend on `devtools/internal-toolkit` for this behavior.

## Decision 1: Foundation owns the baseline json-like runtime value model

`core/foundation` owns baseline runtime json-like value validation and deterministic recursive normalization.

The baseline allowed scalar values are:

```text
null
bool
int
string
```

The baseline allowed compound values are:

```text
list<value>
array<string,value>
```

Where `value` recursively means any value accepted by the baseline json-like runtime value model.

The baseline forbidden values are:

```text
float
NAN
INF
-INF
object
Closure
resource
non-string map key
unsupported PHP value type
```

Every `float` is forbidden, including finite floats.

Objects are forbidden even when they are stringable.

Enum objects are forbidden.

`Closure` is rejected before generic object rejection so closure diagnostics remain stable.

Resources are forbidden.

Unsupported PHP value types are rejected with the generic type-forbidden reason.

## Decision 2: Foundation owns deterministic recursive normalization

`JsonLikeNormalizer` owns deterministic recursive normalization.

The deterministic rules are:

- scalar values are returned unchanged;
- lists preserve caller-supplied order;
- maps require string keys;
- maps are sorted recursively by byte-order `strcmp`;
- nested maps inside lists are sorted recursively;
- nested lists inside maps preserve caller-supplied order;
- empty arrays remain `[]`.

The implementation must not depend on:

- locale-sensitive ordering;
- `setlocale`;
- environment variables;
- filesystem traversal order;
- Composer package order;
- PHP hash-map insertion side effects for final map order;
- platform packages;
- integration packages;
- tooling packages;
- spike packages.

## Decision 3: Foundation owns path-aware safe normalization failures

`JsonLikeNormalizationException` is the canonical baseline failure type.

The canonical error code is:

```text
CORETSIA_JSON_LIKE_INVALID
```

The exception exposes:

```text
errorCode(): string
path(): string
reason(): string
```

The exception message may contain only:

```text
ERROR_CODE
safe path-to-value
stable reason token
```

The exception message must not contain:

- rejected raw scalar values;
- raw payloads;
- secrets;
- tokens;
- credentials;
- passwords;
- cookies;
- session ids;
- Authorization values;
- raw SQL;
- raw request bodies;
- raw response bodies;
- object class names;
- enum class names;
- resource ids;
- local absolute paths;
- environment-specific bytes.

The canonical baseline reason tokens are:

```text
json-like-float-forbidden
json-like-resource-forbidden
json-like-closure-forbidden
json-like-object-forbidden
json-like-map-key-must-be-string
json-like-type-forbidden
```

Runtime PHP code should use constants from:

```text
Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException
```

instead of raw reason strings when possible.

## Decision 4: Diagnostic paths must be safe

The baseline normalizer may expose safe path-to-value diagnostics.

List indexes use bracket notation:

```text
value.items[0]
```

Safe map keys use dot notation:

```text
value.metadata.count
```

Unsafe map keys must not be emitted raw in diagnostic paths.

Unsafe, empty, long, control-character, whitespace, URL-like, SQL-like, path-like, or secret-like keys must use stable placeholders such as:

```text
[<key>]
[<empty-key>]
```

Invalid root paths must be sanitized before they are used in diagnostics.

## Decision 5: Stable JSON serialization delegates baseline normalization to Foundation JsonLikeNormalizer

`StableJsonEncoder` remains the Foundation stable JSON encoder.

`StableJsonDecoder` is the Foundation stable JSON decoder.

The implementation paths are:

```text
framework/packages/core/foundation/src/Serialization/StableJsonEncoder.php
framework/packages/core/foundation/src/Serialization/StableJsonDecoder.php
```

Both stable JSON serialization primitives must delegate baseline recursive normalization to:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

`StableJsonEncoder` owns only encoder-specific behavior:

- `json_encode()` invocation;
- JSON flags;
- final LF;
- root encode shape entrypoints;
- stable-json reason mapping;
- `stable-json-encode-failed`.

`StableJsonDecoder` owns only decoder-specific behavior:

- `json_decode()` invocation;
- `associative: false`;
- decoded JSON object/list root checks;
- JSON object key safety checks before PHP map conversion;
- root decode shape entrypoints;
- stable-json reason mapping;
- `stable-json-decode-failed`.

The required encoder JSON flags remain:

```text
JSON_UNESCAPED_SLASHES
JSON_UNESCAPED_UNICODE
JSON_THROW_ON_ERROR
```

The required decoder JSON behavior is:

```text
associative: false
JSON_THROW_ON_ERROR
```

The encoded output must end with a final LF.

Generic stable JSON methods are shape-insensitive at the root:

```text
StableJsonEncoder::encode(...)
StableJsonEncoder::encodeStable(...)
StableJsonDecoder::decode(...)
StableJsonDecoder::decodeStable(...)
```

Callers that require root JSON object shape must use:

```text
StableJsonEncoder::encodeMap(...)
StableJsonEncoder::encodeStableMap(...)
StableJsonDecoder::decodeMap(...)
StableJsonDecoder::decodeStableMap(...)
```

Callers that require root JSON array shape must use:

```text
StableJsonEncoder::encodeList(...)
StableJsonEncoder::encodeStableList(...)
StableJsonDecoder::decodeList(...)
StableJsonDecoder::decodeStableList(...)
```

`StableJsonEncoder::encodeStableMap([])` emits JSON object `{}` followed by a final LF.

`StableJsonEncoder::encodeStableList([])` emits JSON array `[]` followed by a final LF.

`StableJsonDecoder::decodeStableMap('{}')` accepts an empty JSON object root and returns PHP `[]` after verifying root object shape.

`StableJsonDecoder::decodeStableList('[]')` accepts an empty JSON array root and returns PHP `[]` after verifying root array shape.

Empty root map/list distinction is preserved only by shape-aware root entrypoints.

Nested empty map/list distinction is not preserved by the baseline json-like model.

Stable JSON serialization may map baseline Foundation reason tokens to stable-json reason tokens.

The canonical mapping is:

```text
json-like-float-forbidden             -> stable-json-float-forbidden
json-like-resource-forbidden          -> stable-json-resource-forbidden
json-like-closure-forbidden           -> stable-json-closure-forbidden
json-like-object-forbidden            -> stable-json-object-forbidden
json-like-map-key-must-be-string      -> stable-json-map-key-must-be-string
json-like-type-forbidden              -> stable-json-type-forbidden
```

`json_encode()` failures continue to use:

```text
stable-json-encode-failed
```

`json_decode()` failures use:

```text
stable-json-decode-failed
```

Stable JSON serialization must not reintroduce private recursive json-like walkers.

Stable JSON serialization must not perform schema-specific validation or redaction.

## Decision 6: ContextStorePolicy delegates value-shape validation to Foundation JsonLikeNormalizer

`ContextStorePolicy` remains the Foundation context write guard.

The implementation path remains:

```text
framework/packages/core/foundation/src/Context/ContextStorePolicy.php
```

It must delegate baseline value-shape validation to:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

`ContextStorePolicy` remains a validation boundary only.

It must not store or return the normalized value produced by `JsonLikeNormalizer`.

It must not reorder caller-owned arrays as a side effect.

`ContextStorePolicy` owns context-specific policy:

- context key allowlist enforcement;
- empty context key rejection;
- reserved `@*` key rejection;
- unknown `ContextKeys` rejection;
- mapping baseline json-like failures to context write failures.

The canonical mapping is:

```text
json-like-float-forbidden             -> context-write-forbidden-float
json-like-closure-forbidden           -> context-write-forbidden-closure
json-like-object-forbidden            -> context-write-forbidden-object
json-like-resource-forbidden          -> context-write-forbidden-resource
json-like-map-key-must-be-string      -> context-write-forbidden-map-key
json-like-type-forbidden              -> context-write-forbidden-type
```

`ContextStorePolicy` must not own or duplicate the reusable baseline json-like runtime value model.

## Decision 7: Kernel delegates baseline normalization to Foundation and keeps only UoW-specific policy

Kernel keeps its internal UoW json-like shape wrapper:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

The implementation path remains:

```text
framework/packages/core/kernel/src/Runtime/Internal/JsonLikeShapeNormalizer.php
```

This class remains internal to `core/kernel`.

It is not public Kernel API.

It is not a DI service.

It is not a transport extension point.

It must not be exposed through:

```text
framework/packages/core/kernel/PUBLIC_API.md
```

The Kernel wrapper must delegate baseline recursive normalization to:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

The Kernel wrapper owns only UoW-specific policy:

- root map validation for `attributes`;
- root map validation for `extensions`;
- non-empty root map validation for exported `error`;
- `attributesMaxDepth`;
- `attributesMaxKeys`;
- deterministic unsafe-key rejection for known unsafe metadata keys;
- safe string and safe single-line string checks;
- UoW-specific exception reason mapping;
- UoW-safe diagnostics without raw values.

The Kernel wrapper must not duplicate the baseline recursive json-like walker.

## Decision 8: Kernel owns UoW-specific exception mapping

Kernel maps baseline json-like failures into existing UoW validation exceptions.

For UnitOfWork context attributes, the canonical mapping is:

```text
json-like-float-forbidden             -> uow-context-attributes-float-forbidden
json-like-object-forbidden            -> uow-context-attributes-object-forbidden
json-like-closure-forbidden           -> uow-context-attributes-closure-forbidden
json-like-resource-forbidden          -> uow-context-attributes-resource-forbidden
json-like-map-key-must-be-string      -> uow-context-attributes-map-key-must-be-string
json-like-type-forbidden              -> uow-context-attributes-type-forbidden
```

For UnitOfWork result extensions and exported error maps, the canonical mapping is:

```text
json-like-float-forbidden             -> uow-result-float-forbidden
json-like-object-forbidden            -> uow-result-object-forbidden
json-like-closure-forbidden           -> uow-result-closure-forbidden
json-like-resource-forbidden          -> uow-result-resource-forbidden
json-like-map-key-must-be-string      -> uow-result-map-key-must-be-string
json-like-type-forbidden              -> uow-result-type-forbidden
```

Kernel UoW exceptions must not leak raw values from Foundation-level failures.

Kernel unsafe metadata key rejection must preserve safe structural parent paths and hide unsafe leaf key segments.

Examples:

```text
attributes[<key>]
extensions[<key>]
extensions.safe.nested[<key>]
```

## Decision 9: core/contracts remains unchanged

The baseline json-like runtime value normalizer is not a contract and not a port.

It is a runtime implementation primitive owned by Foundation.

`core/contracts` must remain unchanged by this decision.

No interface is introduced for `JsonLikeNormalizer`.

No contracts-level json-like payload model is introduced.

No DTO semantics are introduced.

No public Kernel normalizer is introduced.

## Decision 10: No config, DI tags, or artifacts are introduced

This decision introduces no config root.

This decision introduces no config keys.

The following keys must not be introduced by this decision:

```text
foundation.json_like.*
foundation.serialization.json_like.*
kernel.json_like.*
```

The normalizer is a static low-level runtime primitive.

It is not registered as a DI service.

This decision introduces no DI tags.

This decision introduces no generated artifacts.

The normalizer must not read generated artifacts.

The normalizer must not write generated artifacts.

## Decision 11: Runtime packages must not use tooling packages or spike code

Runtime packages must not copy code from:

```text
framework/tools/spikes/payload/*
```

Runtime packages must not depend on:

```text
Coretsia\Devtools\InternalToolkit
Coretsia\Tools\Spikes
devtools/*
tools/*
framework/tools/spikes/*
```

The runtime implementation must be native to runtime packages and respect package dependency boundaries.

`core/foundation` must not depend on:

```text
core/kernel
platform/*
integrations/*
devtools/*
tools/*
framework/tools/spikes/*
```

`core/kernel` may depend on:

```text
core/foundation
```

and may therefore consume `JsonLikeNormalizer`.

## Decision 12: Normalization does not grant emission permission

A value accepted by the baseline json-like model may still contain application-sensitive data.

Json-like normalization does not grant permission to emit normalized values into:

- logs;
- metrics;
- spans;
- error descriptors;
- health output;
- profiling output;
- public diagnostics;
- generated artifacts;
- HTTP responses;
- CLI output;
- queue messages;
- worker payloads.

Emission safety remains governed by:

```text
docs/ssot/observability-and-errors.md
```

Callers remain responsible for target-boundary redaction and omission policy.

## Consequences

### Positive

Foundation and Kernel no longer duplicate baseline recursive json-like normalization.

Runtime packages get one reusable baseline json-like value primitive.

Stable JSON encoding, context write validation, and Kernel UoW shape normalization share the same baseline model.

Safe path-aware failures are standardized.

Foundation owns json-like scalar/type/map/list behavior.

Kernel keeps only UoW-specific behavior.

`core/contracts` remains small and does not gain implementation primitives.

Runtime packages remain independent from tooling packages and spike code.

`StableJsonEncoder` keeps its stable-json surface while reusing the baseline normalizer.

`StableJsonDecoder` is aligned with the same baseline normalizer and stable-json reason mapping policy.

Stable JSON serialization now has explicit root object/list entrypoints for callers that need root-shape guarantees without introducing objects into the baseline json-like runtime value model.

`ContextStorePolicy` keeps context key policy and context reason tokens while reusing the baseline normalizer.

`JsonLikeShapeNormalizer` keeps UoW root map policy, unsafe key policy, limits, and exception mapping while reusing the baseline normalizer.

### Negative / trade-offs

`JsonLikeNormalizer` becomes a new Foundation public runtime primitive and must remain stable.

Consumers now depend on Foundation reason-token stability.

`StableJsonEncoder`, `ContextStorePolicy`, and Kernel UoW shape normalization need explicit local reason mapping.

The normalizer is intentionally narrow and rejects floats, objects, resources, closures, enum objects, and unsupported PHP value types.

Nested empty map/list distinction is intentionally not preserved by the baseline json-like model. Schema-specific formats that need nested object/list distinction must use required keys, schema-level validation, or a future shape-preserving serialization model.

The normalizer is not configurable.

The normalizer is not a DI service and is not replaceable as a strategy.

Path-safety rules may make diagnostics less human-detailed for unsafe keys, but this is required to avoid leaking secrets or raw metadata.

## Rejected alternatives

### Direct copy from `framework/tools/spikes/payload/*`

Rejected.

The spike files are prototype/tooling material.

Runtime packages must not import or copy spike code as runtime implementation.

The runtime implementation must be native to runtime packages and must not depend on tooling support classes.

### Depend on `devtools/internal-toolkit` from runtime packages

Rejected.

`devtools/internal-toolkit` is tooling-only.

Runtime packages must not depend on `devtools/*`.

Depending on tooling packages from `core/foundation` or `core/kernel` would violate runtime package boundaries.

### Move json-like normalization to `core/contracts`

Rejected.

This is not a port and not a contracts-level abstraction.

It is a runtime implementation primitive.

Putting implementation normalization policy into `core/contracts` would expand contracts beyond stable interfaces and value contracts.

`core/contracts` remains unchanged.

### Keep duplicated recursive walkers in Foundation and Kernel

Rejected.

That preserves the drift risk.

The same scalar, float, object, resource, map-key, list/map, ordering, and diagnostic-safety policy would continue to exist in multiple places.

Foundation owns the baseline walker.

Kernel owns only the UoW wrapper.

### Make Kernel own the baseline json-like model

Rejected.

Kernel may consume Foundation, but Foundation must not depend on Kernel.

The baseline json-like model is needed by Foundation itself for stable JSON encoding and context write validation.

Putting the baseline in Kernel would invert the dependency boundary or force Foundation to keep its own duplicate model.

### Make `JsonLikeNormalizer` a DI service

Rejected.

The normalizer is a stateless low-level runtime primitive.

Registering it as a DI service would imply replaceable strategy semantics and lifecycle concerns that are not intended.

The baseline model is not a configurable or replaceable policy in this epic.

### Add json-like configuration keys

Rejected.

The baseline runtime value model is safety infrastructure.

Feature-disabling or configuring it would create behavior drift and unsafe runtime modes.

No `foundation.json_like.*`, `foundation.serialization.json_like.*`, or `kernel.json_like.*` keys are introduced.

### Introduce a generic redaction engine in Foundation

Rejected.

Json-like normalization is structural validation and deterministic normalization.

It is not semantic redaction.

It does not classify arbitrary values as PII.

It does not hash, redact, mask, or encrypt values.

Higher-level owners remain responsible for redaction and omission policy.

### Move unsafe metadata key denylist to Foundation

Rejected.

Unsafe metadata key policy is UoW-specific in this decision.

Foundation baseline normalization must not know about `attributes`, `extensions`, `error`, UnitOfWork semantics, or Kernel unsafe metadata keys.

Kernel keeps the unsafe metadata key denylist.

### Introduce transport/request payload semantics

Rejected.

A json-like runtime value is not an HTTP request payload, HTTP response payload, queue message body, worker payload, CLI input, or transport DTO.

Transport-specific payload policy belongs to platform or transport packages.

### Expose Kernel `JsonLikeShapeNormalizer` as public API

Rejected.

`JsonLikeShapeNormalizer` is an internal Kernel implementation detail.

Public Kernel API consumers use:

```text
Coretsia\Kernel\Runtime\UnitOfWorkContext
Coretsia\Kernel\Runtime\UnitOfWorkResult
```

not the internal normalizer.

## Non-goals

This ADR does not implement:

- HTTP request payload validation;
- HTTP response payload validation;
- CLI input payload validation;
- queue message payload validation;
- worker job payload validation;
- DTO policy;
- generic redaction engine;
- unsafe metadata key denylist in Foundation;
- UoW root map policy in Foundation;
- public Kernel normalizer API;
- contracts-level json-like payload interface;
- DI service registration for the normalizer;
- config keys for json-like behavior;
- generated artifacts;
- artifact schema;
- shape-preserving nested JSON object/list model;
- preservation of nested empty object/list distinction;
- duplicate JSON object member name rejection;
- custom JSON parser;
- observability backend;
- logging backend;
- metric backend;
- tracing backend;
- platform adapter behavior;
- transport adapter behavior;
- copying spike implementation into runtime packages;
- dependency on `devtools/internal-toolkit`.

## Verification decision

The behavior must be locked by contract tests.

Foundation tests include:

```text
framework/packages/core/foundation/tests/Contract/JsonLikeNormalizerContractTest.php
framework/packages/core/foundation/tests/Contract/StableJsonEncoderUsesJsonLikeNormalizerContractTest.php
framework/packages/core/foundation/tests/Contract/StableJsonDecoderUsesJsonLikeNormalizerContractTest.php
framework/packages/core/foundation/tests/Contract/StableJsonSerializationRootShapeContractTest.php
framework/packages/core/foundation/tests/Contract/ContextStorePolicyUsesJsonLikeNormalizerContractTest.php
```

Stable JSON serialization tests must verify:

- generic encoder and decoder entrypoints are shape-insensitive at the root;
- `encodeStable([])` emits `[]` followed by a final LF;
- `encodeStableMap([])` emits `{}` followed by a final LF;
- `encodeStableList([])` emits `[]` followed by a final LF;
- `decodeStable('{}')` and `decodeStable('[]')` both normalize to PHP `[]`;
- `decodeStableMap('{}')` accepts an empty JSON object root;
- `decodeStableMap('[]')` rejects a JSON array root with `stable-json-root-map-required`;
- `decodeStableList('[]')` accepts an empty JSON array root;
- `decodeStableList('{}')` rejects a JSON object root with `stable-json-root-list-required`;
- JSON object keys that cannot be safely represented as PHP string map keys are rejected;
- conversion-stage and normalization-stage failures are mapped to stable `stable-json-*` failures;
- stable JSON failures do not expose raw JSON payloads, raw rejected values, object class names, resource ids, file paths, secrets, tokens, payload fragments, or environment-specific data.

Kernel tests include:

```text
framework/packages/core/kernel/tests/Contract/KernelJsonLikePolicyMatchesFoundationContractTest.php
framework/packages/core/kernel/tests/Contract/UnitOfWorkContextAttributesAreJsonLikeContractTest.php
framework/packages/core/kernel/tests/Contract/UnitOfWorkResultExtensionsAreJsonLikeContractTest.php
```

Architecture gates must verify that `core/foundation` does not depend on:

```text
core/kernel
platform/*
integrations/*
devtools/*
tools/*
framework/tools/spikes/*
```

Public API gates must verify that:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

is not added to:

```text
framework/packages/core/kernel/PUBLIC_API.md
```

## Related SSoT

- `docs/ssot/json-like-runtime-values.md`
- `docs/ssot/context-store.md`
- `docs/ssot/uow-shapes.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/artifacts.md`
- `docs/ssot/config-roots.md`

## Related ADRs

- `docs/adr/ADR-0014-di-container-tags-deterministic-order-reset-orchestration.md`
- `docs/adr/ADR-0015-context-bag-context-store-correlation-id.md`
- `docs/adr/ADR-0021-unit-of-work-context-shape.md`
- `docs/adr/ADR-0022-unit-of-work-result-outcome-policy.md`

## Related epic

- `1.275.0 Foundation: Json-like Runtime Value Normalization Primitive`
