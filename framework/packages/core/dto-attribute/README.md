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
**Out of scope:** validation, serialization, hydration, runtime mapping, service behavior, domain modeling.

## Package identity

- **Path:** `framework/packages/core/dto-attribute`
- **Package id:** `core/dto-attribute`
- **Composer name:** `coretsia/core-dto-attribute`
- **Namespace:** `Coretsia\Dto\Attribute\*` (PSR-4: `src/Attribute/`)

Monorepo versioning is **repo-wide only** via git tags `vMAJOR.MINOR.PATCH`.  
Per-package independent versions **MUST NOT** be used.

## DTO marker

The canonical DTO marker is:

```php
#[Coretsia\Dto\Attribute\Dto]
```

A class marked with this attribute explicitly opts into Coretsia DTO policy and is subject to DTO gates.

Unmarked classes are outside DTO gate scope.

## DTO policy

A DTO is a narrow transport shape.

A DTO is not:

- a value object by default;
- a domain model;
- a service;
- a validator;
- a stateful runtime object;
- a descriptor/result/shape class by default.

Marking a class as DTO means the class must follow the canonical DTO rules documented in `docs/ssot/dto-policy.md`.

## Dependency policy

This package is intentionally minimal:

- **Depends on:** PHP only
- **Forbidden:** runtime framework packages, platform packages, integrations, service implementations
