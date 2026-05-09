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

`coretsia/core-dto-attribute` provides the canonical DTO marker attribute for explicit Coretsia DTO opt-in.

The package is intentionally tiny: it defines the marker only and does not provide validation, serialization, hydration, normalization, mapping, dependency injection, runtime discovery, or transport execution.

This repository is a split package generated from the Coretsia monorepo package `framework/packages/core/dto-attribute`.

**Scope:** explicit DTO opt-in marker only.

**Out of scope:** validation, serialization, hydration, normalization, runtime mapping, service behavior, domain modeling, and transport execution.

## Package identity

- **Monorepo source path:** `framework/packages/core/dto-attribute`
- **Split repository:** `coretsia/core-dto-attribute`
- **Package id:** `core/dto-attribute`
- **Composer name:** `coretsia/core-dto-attribute`
- **Namespace:** `Coretsia\Dto\Attribute\*` (PSR-4: `src/Attribute/`)
- **Kind:** library

Versioning is monorepo-wide.

The monorepo tag `vMAJOR.MINOR.PATCH` is the single version source of truth, and the split repository receives the same tag for the corresponding package subtree.

Per-package independent versions MUST NOT be used.

## Dependency policy

This package is intentionally minimal.

- **Depends on:** PHP only
- **Forbidden:**
  - `core/*` runtime implementations
  - `platform/*`
  - `integrations/*`
  - `devtools/*`

The package MUST NOT depend on validators, serializers, hydrators, mappers, containers, platform adapters, or tooling packages.

## DTO marker

The canonical DTO marker is:

```php
#[Coretsia\Dto\Attribute\Dto]
```

The attribute targets classes only.

A class marked with this attribute explicitly opts into Coretsia DTO policy and is subject to DTO gates.

Unmarked classes are outside DTO gate scope unless a future policy explicitly says otherwise.

## DTO policy

A DTO is a narrow transport shape.

A DTO is not:

- a value object by default;
- a domain model;
- a service;
- a validator;
- a stateful runtime object;
- a runtime descriptor by default;
- a result object by default;
- a contract shape by default.

Marking a class as DTO means the class must follow the canonical DTO rules documented in the Coretsia monorepo:

```text
docs/ssot/dto-policy.md
```

In this split repository, that document is not copied locally. The monorepo remains the source of truth for SSoT policy documents.

## Design constraints

The marker attribute is intentionally behavior-free.

It MUST NOT provide:

- validation logic;
- serialization logic;
- hydration logic;
- normalization logic;
- mapping logic;
- dependency injection behavior;
- runtime discovery behavior;
- transport-specific behavior.

DTO gates and static-analysis tooling consume the marker externally.

## Usage

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

## Observability

This package does not emit telemetry directly.

It defines a marker attribute only.

## Errors

This package does not define runtime error codes directly.

DTO policy violations are reported by external gates or static-analysis tooling.

## Security / Redaction

This package does not process sensitive runtime payloads directly.

DTO classes marked with this attribute may contain transport data, but this package does not read, serialize, log, normalize, or redact those values.

Redaction, validation, and safe diagnostics are owner-package responsibilities.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [DTO Attribute package source](https://github.com/coretsia/monorepo/tree/main/framework/packages/core/dto-attribute)
- [Packaging strategy](https://github.com/coretsia/monorepo/blob/main/docs/architecture/PACKAGING.md)
- [Roadmap](https://github.com/coretsia/monorepo/blob/main/docs/roadmap/ROADMAP.md)
- [DTO policy SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/dto-policy.md)
