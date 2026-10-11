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

# coretsia/platform-redaction

`platform/redaction` is the shared, deterministic sensitive-data redaction runtime package for Coretsia.

Scope: explicit redaction of known-sensitive strings, recursive redaction of json-like diagnostic/output values, canonical sensitive-key and high-confidence value classification, bounded traversal, immutable redacted summaries, domain-separated SHA-256 hashing, and fail-closed error handling.

Out of scope: secret resolution, payload or SQL parsing, configurable redaction rules, mutable classifiers, partially masked previews, logging and tracing infrastructure, consumer-specific diagnostic schemas, transport output rendering, Kernel operations, and replacement of producer-owned safe-by-construction guarantees.

## Package identity

- Path: `packages/platform/redaction`
- Package id: `platform/redaction`
- Composer name: `coretsia/platform-redaction`
- Module id: `platform.redaction`
- Namespace: `Coretsia\Platform\Redaction\*` (PSR-4: `src/`)
- Kind: runtime

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions MUST NOT be used.

## Dependency policy

This package is config-free and has a deliberately narrow runtime dependency surface.

- Depends on:
  - PHP `^8.4`
  - `core/contracts`
  - `core/foundation`
- Forbidden:
  - `core/kernel`
  - other `platform/*` packages
  - `integrations/*`
  - `enterprise/*`
  - `devtools/*`
  - `psr/log`
  - vendor HTTP, database, mail, authentication, or secrets SDK implementations

The package MUST NOT depend on observability ports, runtime context accessors, UnitOfWork lifecycle, reset orchestration, or repository machinery under `tools/**`.

Foundation owns baseline json-like input normalization, resource-budget enforcement, and stable JSON encoding. This package owns only the shared redaction semantics layered on those primitives.

## Runtime responsibilities

`platform/redaction` provides:

- explicit redaction of strings already classified as sensitive by their owners;
- recursive processing of normalized json-like values with key-first classification;
- stable classification for canonical sensitive structural keys and conservative whole-value patterns;
- one immutable `RedactionContext` per operation;
- deterministic placeholder, byte-length, hash, and combined summary modes;
- fixed resource limits and validation before and after recursive redaction;
- fail-closed failures with bounded, reason-only diagnostics;
- declarative container definitions for one default implementation of the contracts-level redaction port.

The package is stateless. It does not retain redacted input, traversal state, current paths, prior failures, or runtime context between calls.

## Ownership boundaries

The cross-package API is:

```text
Coretsia\Contracts\Security\SensitiveDataRedactorInterface
```

Cross-package consumers MUST depend on this interface rather than on the concrete implementation or its helper services.

The default implementation is:

```text
Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor
```

The following classes are package-internal implementation details, not consumer extension points:

```text
Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier
Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier
Coretsia\Platform\Redaction\Redaction\StableRedactionHasher
```

Consumers own their output schemas, acceptable diagnostic fields, semantic-key restrictions, cardinality, path and size limits, and decisions about permitted metadata disclosure. A redacted summary does not override those policies.

Lower-layer Core packages keep their own boundary-specific validation, rejection, omission, and safe derivation. Kernel operation results remain safe by construction and do not depend on this redaction port.

## Module and provider

Module metadata is exposed by:

```text
Coretsia\Platform\Redaction\Module\RedactionModule
```

The module identifies `platform.redaction` and declares `RedactionServiceProvider` as its provider.

`RedactionServiceProvider` implements the canonical Foundation service-provider and declarative definition-provider interfaces. When explicitly applied, its definitions contribute one `SensitiveDataRedactorInterface` binding to the shared `DefaultSensitiveDataRedactor` service and its internal dependencies.

Composer installation, class availability, environment state, and debug mode MUST NOT automatically enable or register the module. Module selection and provider application remain the responsibility of the application composition owner; consumers declare their module dependency explicitly.

The package introduces no configuration root, defaults file, runtime configuration switches, disable toggle, mutable policy registry, or runtime implementation selector. Its provider introduces no service tags.

## Public API

The public contract offers exactly two operations:

```php
public function redactValue(
    string $value,
    RedactionKind $kind,
    RedactionContext $context,
): RedactedValue;

public function redactJsonLike(mixed $value, RedactionContext $context): mixed;
```

Both operations receive an explicit immutable `RedactionContext`. The scope is a stable, low-cardinality operation or output-boundary identifier such as `cli.output`, `logging.record`, or `http.problem-detail`.

A scope MUST match `\A[a-z][a-z0-9]*(?:[._:-][a-z0-9]+)*\z` and be at most 128 bytes. It MUST NOT be constructed from paths, user or tenant identifiers, endpoints, request identifiers, tokens, field values, or other runtime data.

### Direct string redaction

Call `redactValue()` when the producer already knows that the string is sensitive. Supply the explicit semantic `RedactionKind`; do not rely on generic value classification to rediscover an owner-known secret.

The method returns a `RedactedValue` object. Its `toArray()` representation has exactly six stable fields, in order: `hash`, `kind`, `length`, `mode`, `redacted`, and `schemaVersion`.

### Recursive json-like redaction

Call `redactJsonLike()` for a diagnostic or output boundary that is allowed to use shared recursive redaction. Input is restricted to the Foundation-supported json-like shape: `null`, `bool`, `int`, `string`, and recursively nested lists or string-keyed maps.

The operation validates and normalizes the complete input before semantic traversal. For a map, it classifies each original structural key first. A sensitive key causes its entire associated branch to be replaced; descendants are not individually processed. An otherwise unclassified map key that matches a sensitive-value pattern fails with `sensitive-map-key`. String leaves under non-sensitive keys are then classified by conservative whole-value patterns.

Unclassified values are not necessarily safe. Classifier non-match MUST NOT be used as permission to export arbitrary raw values. List order is preserved, and maps use deterministic `strcmp` key ordering.

### Usage

The following examples use synthetic values only. `$redactor` is supplied to the consumer through the contracts-level interface.

```php
<?php

declare(strict_types=1);

use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;

function redactKnownCredential(SensitiveDataRedactorInterface $redactor): array
{
    return $redactor->redactValue(
        'synthetic-credential',
        RedactionKind::Credential,
        new RedactionContext('cli.output'),
    )->toArray();
}

function redactDiagnostic(SensitiveDataRedactorInterface $redactor): mixed
{
    return $redactor->redactJsonLike(
        [
            'authorization' => 'synthetic-authorization-value',
            'details' => [
                'status' => 'ok',
                'token' => 'synthetic-token-value',
            ],
        ],
        new RedactionContext('cli.output', RedactionMode::Placeholder),
    );
}
```

The first operation explicitly redacts a known-sensitive string. In the second, the `authorization` and `token` keys select their associated values for replacement. The non-sensitive `status` value remains unchanged only as a result of classification; its admissibility at the final destination remains consumer-owned.

## Disclosure modes

A `RedactionContext` selects exactly one of these modes:

- `Placeholder` (default): disclose only the redacted classification and mode; `hash` and `length` are `null`.
- `Length`: disclose the byte length; `hash` is `null`.
- `Hash`: disclose a deterministic, domain-separated SHA-256 hash; `length` is `null`.
- `HashAndLength`: disclose both byte length and the domain-separated hash.

Length is measured in bytes, not Unicode characters. Hashing incorporates the stable scope, redaction kind, and `string` versus `json-like` representation domain. Hashes have the form `sha256:` followed by 64 lowercase hexadecimal characters.

Neither hashing nor length disclosure declassifies the original value. Deterministic hashes can permit reconstruction or correlation of low-entropy inputs, and lengths can reveal sensitive metadata. Non-placeholder modes require explicit consumer-owner approval for the relevant output boundary.

For one `redactJsonLike()` call, the selected mode applies uniformly to **every** branch chosen for replacement. The redactor does not change disclosure modes by field, kind, branch, or classifier result.

If a boundary may contain sensitive branches with different disclosure requirements, the consumer MUST omit the data or use `Placeholder` unless its policy explicitly permits the same non-placeholder disclosure for every potentially selected branch. Alternatively, the owner may split separately governed values into separate redaction operations.

## Resource limits

Both entrypoints use the fixed Foundation normalization limits:

- Maximum depth: `32`
- Maximum nodes: `10000`
- Maximum bytes per string: `65536`
- Maximum aggregate string bytes: `1048576`

The aggregate budget includes string values and string map keys. `redactJsonLike()` validates input before traversal and validates its completed output against the same limits. The package introduces no parallel structural validator or resource-budget walker.

A selected sensitive branch does not bypass input validation. For map keys reached during semantic traversal, ASCII C0 control bytes and DEL are rejected before classification. Keys inside an already selected complete branch are not separately traversed for that redaction-specific check after the complete input has passed Foundation normalization.

## Observability

This package emits no baseline logs, spans, span events, metrics, or reporter payloads. It has no logger, tracer, meter, reporter, runtime `ContextStore`, or `ContextAccessorInterface` dependency.

Consumers MAY observe their already-safe operation outcomes only under their own observability policies. Producing a canonical redacted summary does not make it automatically admissible as a log field, trace attribute, event value, or metric label.

Sink-specific schemas, allowlists, cardinality constraints, and resource limits remain authoritative. Redacted summary maps MUST NOT be used as metric label values. Raw stack traces MUST NOT be exported through diagnostic or output sinks.

## Errors

Failures are exposed through the contracts-level exception:

```text
Coretsia\Contracts\Security\Exception\RedactionException
```

Its stable error code is `CORETSIA_REDACTION_FAILED`, with one of the following reason tokens:

- `input-invalid`
- `input-limit-exceeded`
- `sensitive-map-key`
- `output-invalid`
- `internal-failure`

Input type or shape failures map to `input-invalid`; input resource-budget failures map to `input-limit-exceeded`; a sensitive-value-shaped structural map key produces `sensitive-map-key`; output normalization failures map to `output-invalid`; other unexpected failures map to `internal-failure`.

Exceptions contain no rejected values, map keys, scopes, hashes, paths, payload fragments, or previous `Throwable` messages in their public message or custom state. Native PHP stack-trace storage is not modified by the package, and consumers MUST NOT expose raw stack traces to diagnostic sinks.

Invalid `RedactionContext` scopes fail with `InvalidArgumentException('redaction-context-scope-invalid')`. Invalid `RedactedValue` combinations fail with `InvalidArgumentException('redacted-value-shape-invalid')`.

Redaction failures never return the original value or a partially redacted result.

## Security / Redaction

Redaction is **defense in depth**, not a substitute for safe-by-construction producer output. The preferred treatment of values that are not operationally necessary is omission or an owner-defined stable safe reason or category.

A consumer MUST NOT use successful redaction, a valid `RedactedValue` shape, or a classifier non-match as proof that an arbitrary diagnostic/output value is safe to export. Destination-owned schema, semantic-key, absolute-local-path, cardinality, and resource constraints still apply.

The classifier does not perform semantic parsing, decoding, credential validation, secret resolution, arbitrary PII rewriting, SQL inspection, or entropy scoring. It cannot discover all sensitive strings. Known-sensitive values MUST be classified explicitly by the producing owner when using `redactValue()`.

The package has no debug passthrough, disabled/raw mode, environment-dependent behavior, configurable policy packs, mutable classifier registry, runtime context access, UnitOfWork integration, or reset dependency. `RedactionContext` is supplied explicitly to each call and remains immutable.

Kernel operation results and other lower-layer safe-by-construction boundaries MUST NOT acquire an upward dependency on this package to justify emitting otherwise unsafe data.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Redaction package source](https://github.com/coretsia/monorepo/tree/main/packages/platform/redaction)
- [Sensitive Data Redaction SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/sensitive-data-redaction.md)
