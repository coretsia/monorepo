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

`core/dto-attribute` provides the canonical DTO marker attribute for the Coretsia Framework monorepo.

**Scope:** explicit DTO opt-in marker only.

**Out of scope:** validation, serialization, hydration, normalization, runtime mapping, service behavior, domain modeling, and transport execution.

## Package identity

- **Path:** `framework/packages/core/dto-attribute`
- **Package id:** `core/dto-attribute`
- **Composer name:** `coretsia/core-dto-attribute`
- **Namespace:** `Coretsia\Dto\Attribute\*` (PSR-4: `src/Attribute/`)
- **Kind:** library

Monorepo versioning is **repo-wide only** via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions **MUST NOT** be used.

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

Marking a class as DTO means the class must follow the canonical DTO rules documented in:

```text
docs/ssot/dto-policy.md
```

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

- `docs/ssot/dto-policy.md`
- `docs/roadmap/ROADMAP.md`
