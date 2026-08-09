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

# coretsia/core-dto-attribute

`core/dto-attribute` is the canonical DTO marker package for explicit Coretsia DTO opt-in.

Scope: one behavior-free class attribute that marks a class as subject to the canonical Coretsia DTO policy.

Out of scope: validation, serialization, hydration, normalization, mapping, dependency injection, runtime discovery, service behavior, domain modeling, and transport execution.

## Package identity

- Path: `framework/packages/core/dto-attribute`
- Package id: `core/dto-attribute`
- Composer name: `coretsia/core-dto-attribute`
- Namespace: `Coretsia\Dto\Attribute\*` (PSR-4: `src/Attribute/`)
- Kind: library

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

The corresponding split repository is `coretsia/core-dto-attribute` and receives the same tag for the package subtree.

Per-package independent versions MUST NOT be used.

## Dependency policy

This package is intentionally minimal and must remain safe to depend on from any layer that needs explicit DTO classification.

- Depends on:
  - PHP only
- Forbidden:
  - `core/*` runtime implementations
  - `platform/*`
  - `integrations/*`
  - `devtools/*`

The package MUST NOT depend on:

- validators;
- serializers;
- hydrators;
- normalizers;
- mappers;
- service containers;
- runtime discovery infrastructure;
- platform adapters;
- transport implementations;
- tooling packages.

Adding runtime or tooling dependencies to this package would violate its marker-only ownership boundary.

## Package responsibilities

This package owns exactly one public responsibility:

```text
explicit DTO opt-in marker
```

The canonical marker is:

```php
#[Coretsia\Dto\Attribute\Dto]
```

The attribute targets classes only.

Applying the attribute declares that the class participates in the canonical Coretsia DTO policy.

The package does not inspect, instantiate, validate, transform, serialize, hydrate, normalize, map, discover, or execute marked classes.

Those responsibilities remain external to this package.

## DTO marker

The canonical marker class is:

```text
Coretsia\Dto\Attribute\Dto
```

Typical usage:

```php
<?php

declare(strict_types=1);

namespace Acme\App\Api;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final readonly class CreateUserRequest
{
    public function __construct(
        public string $email,
        public string $displayName,
    ) {
    }
}
```

A marked class explicitly opts into DTO policy.

An unmarked class is outside DTO policy enforcement scope unless another canonical policy explicitly places it within scope.

Marker presence is classification only.

It MUST NOT imply:

- runtime registration;
- service-container registration;
- automatic validation;
- automatic serialization;
- automatic hydration;
- automatic normalization;
- automatic mapping;
- transport registration;
- persistence semantics;
- domain-model semantics.

## DTO policy

A DTO is a narrow data-transfer shape.

A DTO is not, by default:

- a value object;
- a domain model;
- an entity;
- a service;
- a validator;
- a serializer;
- a mapper;
- a stateful runtime object;
- a runtime descriptor;
- a result object;
- a contract shape.

Classes marked with `#[Dto]` are governed by the canonical DTO policy documented in:

```text
docs/ssot/dto-policy.md
```

The monorepo SSoT remains authoritative for DTO rules.

The split package does not duplicate that policy locally.

This README describes package ownership and marker semantics only.

## Ownership boundaries

`core/dto-attribute` owns marker vocabulary.

It does not own DTO enforcement.

DTO policy enforcement and static-analysis tooling consume the marker externally.

Implementation owners remain responsible for any behavior applied to marked DTOs, including:

- validation;
- serialization;
- hydration;
- normalization;
- mapping;
- transport conversion;
- persistence conversion;
- schema generation;
- runtime discovery.

Importing or applying `Coretsia\Dto\Attribute\Dto` does not grant this package ownership over those behaviors.

Consumers MUST NOT treat the marker as an executable hook or service-registration mechanism.

## Design constraints

The marker attribute is intentionally behavior-free.

It MUST NOT provide:

- validation logic;
- serialization logic;
- hydration logic;
- normalization logic;
- mapping logic;
- dependency injection behavior;
- service lookup;
- runtime discovery behavior;
- transport-specific behavior;
- filesystem access;
- environment access;
- configuration access;
- generated artifact behavior;
- observability behavior.

The marker MUST remain usable without requiring a Coretsia runtime container or platform package.

Its meaning is declarative:

```text
this class opts into canonical Coretsia DTO policy
```

Nothing more is implied by the package itself.

## Observability

This package does not emit telemetry.

It does not define:

- logs;
- spans;
- metrics;
- profiling;
- tracing propagation;
- observability exporters.

Applying or reflecting on the marker MUST NOT itself require observability infrastructure.

Any observability associated with processing a marked DTO belongs to the implementation owner performing that processing.

## Errors

This package does not define runtime error codes or runtime exception mapping.

The marker itself does not perform operations that require validation or transformation failures.

DTO policy violations are reported by external policy-enforcement or static-analysis tooling.

Runtime validation, serialization, hydration, mapping, or transport failures remain owned by the packages performing those operations.

## Security / Redaction

This package does not read or process DTO property values.

A class marked with `#[Dto]` may contain sensitive transport data, but this package does not:

- inspect those values;
- serialize them;
- normalize them;
- validate them;
- log them;
- trace them;
- export them;
- redact them.

Applying the marker MUST NOT make DTO values safe for diagnostics or observability by implication.

Consumers remain responsible for:

- validation;
- redaction;
- safe diagnostics;
- observability policy;
- transport-specific data handling;
- secret and PII protection.

Marked DTOs MAY contain data that must not cross diagnostic or observability boundaries without owner-defined normalization or redaction.

The marker carries classification only and MUST NOT be treated as a security approval for the contents of the marked class.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [DTO Attribute package source](https://github.com/coretsia/monorepo/tree/main/framework/packages/core/dto-attribute)
- [Packaging strategy](https://github.com/coretsia/monorepo/blob/main/docs/architecture/PACKAGING.md)
- [DTO policy SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/dto-policy.md)
