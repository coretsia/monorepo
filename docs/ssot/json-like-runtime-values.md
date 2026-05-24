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

# Json-like Runtime Values SSoT

## Scope

This document is the Single Source of Truth for Coretsia Foundation baseline runtime json-like value validation, deterministic recursive normalization, and safe path/reason diagnostics.

It defines the canonical runtime value model implemented by:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException
```

The canonical implementation paths are:

```text
framework/packages/core/foundation/src/Serialization/JsonLikeNormalizer.php
framework/packages/core/foundation/src/Serialization/Exception/JsonLikeNormalizationException.php
```

This document also defines how the baseline model is consumed by:

```text
Coretsia\Foundation\Serialization\StableJsonEncoder
Coretsia\Foundation\Context\ContextStorePolicy
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

The consumer implementation paths are:

```text
framework/packages/core/foundation/src/Serialization/StableJsonEncoder.php
framework/packages/core/foundation/src/Context/ContextStorePolicy.php
framework/packages/core/kernel/src/Runtime/Internal/JsonLikeShapeNormalizer.php
```

This document complements:

```text
docs/ssot/context-store.md
docs/ssot/uow-shapes.md
docs/ssot/observability-and-errors.md
```

This document does not replace context-specific or UnitOfWork-specific policy documents.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Canonical authority

`core/foundation` owns the baseline runtime json-like value model.

This document is the canonical human-readable reference for:

- accepted runtime json-like scalar values;
- accepted runtime json-like array shapes;
- forbidden runtime value types;
- deterministic recursive map/list normalization;
- safe path-to-value diagnostics;
- baseline json-like reason tokens.

Runtime code, tests, package READMEs, ADRs, SSoT documents, and future runtime package consumers MUST treat this document as the canonical baseline json-like runtime value authority.

`docs/ssot/context-store.md` owns context storage, context key policy, context write semantics, and context exception mapping.

`docs/ssot/uow-shapes.md` owns UnitOfWork root shape policy, unsafe metadata key policy, attributes limits, exported error map policy, and UoW exception mapping.

`docs/ssot/observability-and-errors.md` owns observability and error emission safety rules.

## Goal

Runtime packages need one reusable baseline model for values that can safely cross in-process context, diagnostic, serialization, and UnitOfWork shape boundaries without depending on tooling packages or duplicating recursive validation logic.

The baseline json-like runtime value model provides:

- one Foundation-owned normalizer;
- one Foundation-owned exception type;
- one deterministic recursive map/list normalization rule;
- one scalar/type rejection policy;
- one safe path/reason diagnostic policy;
- one reusable primitive for Foundation and higher runtime packages.

## Ownership

The owner package is:

```text
core/foundation
```

The package path is:

```text
framework/packages/core/foundation/
```

The Composer package is:

```text
coretsia/core-foundation
```

The module id is:

```text
core.foundation
```

The canonical implementation is:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

The canonical exception is:

```text
Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException
```

## Package dependency policy

`core/foundation` MAY depend on:

```text
core/contracts
psr/clock
psr/container
psr/log
```

`core/foundation` MUST NOT depend on:

```text
core/kernel
platform/*
integrations/*
devtools/*
tools/*
framework/tools/spikes/*
```

Runtime packages MAY consume the Foundation normalizer only when their package dependency rules allow a dependency on `core/foundation`.

Runtime packages MUST NOT copy the recursive baseline json-like walker.

## Runtime value purpose

A json-like runtime value is a narrow in-process structural value model.

It is suitable for low-level runtime boundaries that require deterministic structure and safe failure diagnostics.

It is not a transport payload model.

It is not an HTTP request or response payload model.

It is not a DTO policy.

It is not a generic redaction engine.

It is not an observability emission permission.

A value accepted by this model MAY still be unsafe for logs, metrics, spans, error descriptors, public diagnostics, generated artifacts, HTTP responses, CLI output, or other outward-facing boundaries.

Callers remain responsible for target-boundary safety.

## Canonical API

The canonical normalizer API is:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer::normalize(mixed $value, string $path = 'value'): mixed
```

The method MUST return a deterministic json-like runtime value.

The method MUST throw:

```text
Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException
```

when a value cannot be represented by the baseline json-like runtime model.

The normalizer is a low-level static runtime primitive.

It is not a DI service.

It introduces no config keys, no tags, and no artifacts.

## Value model

Allowed scalar values are:

```text
null
bool
int
string
```

Allowed compound values are:

```text
list<value>
array<string,value>
```

Where `value` recursively means any value accepted by this model.

## Forbidden values

The following values are forbidden everywhere, including nested structures:

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

Every `float` MUST be rejected, including finite floats.

Objects MUST be rejected even when they are stringable.

Enum objects MUST be rejected.

`Closure` MUST be rejected before generic object rejection so closure diagnostics remain stable.

Resources MUST be rejected.

Streams and filesystem handles are forbidden.

Unsupported PHP value types MUST be rejected with the generic type-forbidden reason.

If a future owner needs decimal data, it MUST represent the value as a string with a documented safe format.

## Array model

PHP arrays are interpreted as either lists or maps.

A PHP array is a list when:

```text
array_is_list($value) === true
```

List order MUST preserve caller-supplied order.

List item paths MUST use bracket indexes:

```text
value.items[0]
value.items[1]
```

A PHP array is a map when:

```text
array_is_list($value) === false
```

Map keys MUST be strings.

A map with any non-string key MUST be rejected.

Maps MUST be sorted recursively by byte-order string comparison:

```text
strcmp($left, $right)
```

Nested lists and nested maps MUST obey the same rules recursively.

## Empty arrays

An empty PHP array is allowed.

The normalizer MUST preserve an empty array as:

```text
[]
```

Consumers MUST NOT infer a semantic list-vs-map distinction from an empty array unless a higher-level owner explicitly defines that distinction.

## Deterministic normalization

Normalization MUST be deterministic for the same input shape.

The baseline deterministic rules are:

- scalar values are returned unchanged;
- lists preserve caller-supplied order;
- maps are sorted recursively by byte-order `strcmp`;
- nested maps inside lists are sorted recursively;
- nested lists inside maps preserve caller-supplied order;
- empty arrays remain `[]`.

The normalizer MUST NOT:

- read environment variables;
- inspect runtime configuration;
- use locale-sensitive ordering;
- call `setlocale`;
- depend on filesystem traversal order;
- depend on Composer package order;
- depend on PHP hash-map insertion side effects for final map order;
- depend on platform, integration, devtools, tools, or spike code.

## Diagnostics

Failures MUST be deterministic and safe.

The canonical failure exception is:

```text
Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException
```

The canonical error code is:

```text
CORETSIA_JSON_LIKE_INVALID
```

The exception MUST expose:

```text
errorCode(): string
path(): string
reason(): string
```

The exception message MUST contain only:

```text
ERROR_CODE
safe path-to-value
stable reason token
```

The exception message MUST NOT contain:

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

## Reason tokens

The canonical Foundation reason tokens are:

```text
json-like-float-forbidden
json-like-resource-forbidden
json-like-closure-forbidden
json-like-object-forbidden
json-like-map-key-must-be-string
json-like-type-forbidden
```

These reason tokens are owned by:

```text
Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException
```

Runtime PHP code SHOULD use the exception constants rather than raw string literals when it can depend on the exception class.

Docs and tests MAY use raw reason strings for readability.

## Path-to-value notation

When an error identifies a nested value, it MUST include a deterministic safe path-to-value.

Recommended path notation:

```text
value.metadata.items[3].count
```

Rules:

- list indexes use bracket notation;
- safe map keys use dot notation;
- unsafe map keys use stable placeholders;
- paths MUST NOT include raw rejected values;
- paths MUST NOT include unsafe raw map keys;
- paths MUST be deterministic for the same input shape.

Safe map keys MAY use raw dot notation only when they match the conservative safe-key pattern:

```text
/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/
```

Examples of safe path segments:

```text
value.alpha
value.request_id
value.items[0].count
```

Unsafe, empty, long, control-character, whitespace, URL-like, SQL-like, path-like, or secret-like keys MUST NOT be emitted raw in diagnostic paths.

Such keys MUST be represented by stable placeholders such as:

```text
[<key>]
[<empty-key>]
```

Examples:

```text
value.safe[<key>]
value.safe[<empty-key>]
```

Invalid root paths MUST be sanitized before they are used in diagnostics.

## StableJsonEncoder boundary

`StableJsonEncoder` is a Foundation consumer of the baseline json-like runtime value model.

The canonical implementation is:

```text
Coretsia\Foundation\Serialization\StableJsonEncoder
```

`StableJsonEncoder` MUST delegate baseline recursive normalization to:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

`StableJsonEncoder` owns only encoder-specific behavior:

- `json_encode()` invocation;
- JSON flags;
- final LF;
- stable-json reason mapping;
- `stable-json-encode-failed`.

The required JSON flags are:

```text
JSON_UNESCAPED_SLASHES
JSON_UNESCAPED_UNICODE
JSON_THROW_ON_ERROR
```

The encoded output MUST end with a final LF.

`StableJsonEncoder` MUST preserve deterministic bytes for valid json-like values.

`StableJsonEncoder` MAY map Foundation reason tokens to encoder-specific reason tokens.

The canonical mapping is:

```text
json-like-float-forbidden             -> stable-json-float-forbidden
json-like-resource-forbidden          -> stable-json-resource-forbidden
json-like-closure-forbidden           -> stable-json-closure-forbidden
json-like-object-forbidden            -> stable-json-object-forbidden
json-like-map-key-must-be-string      -> stable-json-map-key-must-be-string
json-like-type-forbidden              -> stable-json-type-forbidden
```

`json_encode()` failures MUST use:

```text
stable-json-encode-failed
```

The encoder MUST NOT reintroduce a private recursive json-like walker.

## ContextStorePolicy boundary

`ContextStorePolicy` is a Foundation consumer of the baseline json-like runtime value model.

The canonical implementation is:

```text
Coretsia\Foundation\Context\ContextStorePolicy
```

`ContextStorePolicy` MUST delegate baseline value-shape validation to:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

`ContextStorePolicy` remains a validation boundary only.

It MUST NOT store or return the normalized value.

It MUST NOT reorder caller-owned arrays as a side effect.

`ContextStorePolicy` owns context-specific policy:

- context key allowlist;
- empty context key rejection;
- reserved `@*` namespace rejection;
- unknown `ContextKeys` rejection;
- context exception mapping.

The context key registry remains owned by:

```text
docs/ssot/context-keys.md
```

The context store policy remains governed by:

```text
docs/ssot/context-store.md
```

`ContextStorePolicy` MAY map Foundation reason tokens to context-specific reason tokens.

The canonical mapping is:

```text
json-like-float-forbidden             -> context-write-forbidden-float
json-like-closure-forbidden           -> context-write-forbidden-closure
json-like-object-forbidden            -> context-write-forbidden-object
json-like-resource-forbidden          -> context-write-forbidden-resource
json-like-map-key-must-be-string      -> context-write-forbidden-map-key
json-like-type-forbidden              -> context-write-forbidden-type
```

`ContextStorePolicy` MUST NOT own the reusable baseline json-like model.

`ContextStorePolicy` MUST NOT introduce transport payload semantics.

## Kernel UoW boundary

`JsonLikeShapeNormalizer` is a Kernel consumer of the baseline json-like runtime value model.

The canonical Kernel wrapper is:

```text
Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer
```

The Kernel wrapper MUST delegate baseline recursive normalization to:

```text
Coretsia\Foundation\Serialization\JsonLikeNormalizer
```

The Kernel wrapper MUST remain internal to `core/kernel`.

It MUST NOT become public Kernel API.

It MUST NOT be exposed through:

```text
framework/packages/core/kernel/PUBLIC_API.md
```

Kernel owns UoW-specific policy:

- `attributes` root map policy;
- `extensions` root map policy;
- exported `error` map policy;
- non-empty exported `error` map policy;
- unsafe metadata key denylist;
- `attributesMaxDepth`;
- `attributesMaxKeys`;
- safe string and safe single-line string checks;
- UoW-specific exception mapping.

The UoW shape policy remains governed by:

```text
docs/ssot/uow-shapes.md
```

The unsafe metadata key denylist MUST remain in Kernel.

The baseline Foundation normalizer MUST NOT know about:

```text
attributes
extensions
error
UnitOfWorkContext
UnitOfWorkResult
attributesMaxDepth
attributesMaxKeys
unsafe metadata keys
UoW exception codes
UoW reason tokens
```

Kernel MAY map Foundation reason tokens to UoW-specific reason tokens.

The canonical context attributes mapping is:

```text
json-like-float-forbidden             -> uow-context-attributes-float-forbidden
json-like-object-forbidden            -> uow-context-attributes-object-forbidden
json-like-closure-forbidden           -> uow-context-attributes-closure-forbidden
json-like-resource-forbidden          -> uow-context-attributes-resource-forbidden
json-like-map-key-must-be-string      -> uow-context-attributes-map-key-must-be-string
json-like-type-forbidden              -> uow-context-attributes-type-forbidden
```

The canonical result mapping is:

```text
json-like-float-forbidden             -> uow-result-float-forbidden
json-like-object-forbidden            -> uow-result-object-forbidden
json-like-closure-forbidden           -> uow-result-closure-forbidden
json-like-resource-forbidden          -> uow-result-resource-forbidden
json-like-map-key-must-be-string      -> uow-result-map-key-must-be-string
json-like-type-forbidden              -> uow-result-type-forbidden
```

Kernel UoW exceptions MUST NOT leak raw values from Foundation-level failures.

Unsafe metadata keys rejected by Kernel MUST use safe diagnostic paths.

Examples:

```text
attributes[<key>]
extensions[<key>]
extensions.safe.nested[<key>]
```

## Security boundary

The baseline normalizer is a shape and deterministic normalization primitive.

It is not a generic redaction engine.

It MUST NOT attempt to classify arbitrary values as PII.

It MUST NOT inspect semantic map keys to decide whether a key is unsafe metadata.

It MUST NOT hash, redact, encrypt, or mask values.

It MUST reject unsupported shapes safely.

It MUST ensure diagnostics do not leak raw rejected values or unsafe raw path segments.

Semantic redaction and unsafe metadata key policy remain owned by higher-level domain owners.

## Observability and errors boundary

Any export to logs, spans, metrics, error descriptors, profiling output, health output, public diagnostics, or generated artifacts remains governed by:

```text
docs/ssot/observability-and-errors.md
```

Json-like normalization does not grant permission to emit normalized data.

A normalized value may still contain application-sensitive data if a caller supplied it.

Callers remain responsible for ensuring data is safe for the target boundary.

Diagnostic failures from this model MUST use only:

```text
safe path
stable reason token
package-owned error code
```

Raw values MUST NOT be emitted in failure messages.

## Configuration policy

This document introduces no config root and no config keys.

The following config keys MUST NOT be introduced by this model:

```text
foundation.json_like.*
foundation.serialization.json_like.*
kernel.json_like.*
```

The normalizer is baseline runtime infrastructure.

It MUST NOT be feature-disabled through config.

## DI policy

The normalizer is a static low-level runtime primitive.

It MUST NOT be registered as a DI service by this SSoT.

This document introduces no DI tags.

This document owns no tag constants.

## Artifact policy

This document introduces no artifacts.

The normalizer MUST NOT write generated artifacts.

The normalizer MUST NOT read generated artifacts.

Any future artifact that serializes normalized json-like values MUST define its own artifact schema and owner SSoT.

## Transport boundary

This document introduces no HTTP, CLI, queue, worker, or scheduler transport payload semantics.

The phrase `json-like runtime value` MUST NOT be interpreted as:

```text
HTTP request payload
HTTP response payload
CLI input
queue message body
worker job payload
public API payload
transport DTO
```

Transport-specific payload rules are owned by transport/platform packages.

## Relationship to spikes

The prototype payload spike files are non-runtime tooling/prototype material.

Runtime packages MUST NOT copy code from:

```text
framework/tools/spikes/payload/*
```

Runtime packages MUST NOT depend on:

```text
Coretsia\Devtools\InternalToolkit
Coretsia\Tools\Spikes
```

The runtime implementation MUST be native to runtime packages.

## Verification evidence

Expected contract verification includes:

```text
framework/packages/core/foundation/tests/Contract/JsonLikeNormalizerContractTest.php
framework/packages/core/foundation/tests/Contract/StableJsonEncoderUsesJsonLikeNormalizerContractTest.php
framework/packages/core/foundation/tests/Contract/ContextStorePolicyUsesJsonLikeNormalizerContractTest.php
framework/packages/core/kernel/tests/Contract/KernelJsonLikePolicyMatchesFoundationContractTest.php
```

Existing UoW regression tests MUST remain green:

```text
framework/packages/core/kernel/tests/Contract/UnitOfWorkContextAttributesAreJsonLikeContractTest.php
framework/packages/core/kernel/tests/Contract/UnitOfWorkResultExtensionsAreJsonLikeContractTest.php
```

These tests are expected to verify:

- scalar acceptance;
- finite float rejection;
- `NAN`, `INF`, and `-INF` rejection;
- object rejection;
- enum object rejection;
- closure rejection before generic object rejection;
- resource rejection;
- unsupported type rejection;
- non-string map-key rejection;
- recursive `strcmp` map ordering;
- list order preservation;
- empty array preservation;
- safe path/reason diagnostics;
- no raw value leakage;
- no unsafe raw map-key leakage;
- stable-json reason mapping;
- context exception mapping;
- UoW exception mapping;
- Kernel ownership of root map, unsafe-key, depth, and key-count policy.

## Compliance checklist

A runtime implementation is compliant with this SSoT only if:

- `JsonLikeNormalizer` owns baseline recursive normalization;
- `JsonLikeNormalizationException` owns the baseline error code and reason tokens;
- `StableJsonEncoder` delegates baseline normalization to `JsonLikeNormalizer`;
- `ContextStorePolicy` delegates value-shape validation to `JsonLikeNormalizer`;
- `JsonLikeShapeNormalizer` delegates baseline normalization to `JsonLikeNormalizer`;
- Kernel keeps UoW-specific policy in Kernel only;
- Foundation does not know about UoW-specific shapes;
- runtime packages do not depend on devtools, tools, or spikes;
- diagnostics contain only safe path, stable reason, and package-owned error code;
- raw values, object class names, resources, secrets, tokens, SQL, payloads, and local paths do not leak through failures.

## Non-goals

This SSoT does not define:

- a generic redaction engine;
- an unsafe metadata key denylist in Foundation;
- UoW root map policy in Foundation;
- transport/request payload semantics;
- DTO semantics;
- public Kernel normalizer API;
- dependency on `devtools/internal-toolkit`;
- dependency on `framework/tools/spikes/*`;
- movement of json-like normalization to `core/contracts`;
- generated artifacts;
- config keys;
- DI tags;
- HTTP response construction;
- CLI output formatting;
- queue payload validation;
- worker job payload validation;
- platform adapter implementation;
- observability backend schema;
- metric backend schema;
- tracing backend schema;
- logging backend schema.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Context Store SSoT](./context-store.md)
- [Context Keys SSoT](./context-keys.md)
- [UnitOfWork Shapes SSoT](./uow-shapes.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [ADR-0004 — Foundation Json-like Runtime Values](../adr/ADR-0004-foundation-json-like-runtime-values.md)
