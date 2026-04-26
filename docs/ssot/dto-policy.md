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

# DTO Policy

This document is the **single source of truth** for DTO policy in Coretsia Framework.

DTO policy is intentionally narrow. It applies only to classes that explicitly opt in through the canonical DTO marker attribute.

## Invariants (MUST)

- DTO detection **MUST** be explicit opt-in only.
- A class is treated as a DTO only when it is marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

- Unmarked classes **MUST NOT** be analyzed by DTO gates.
- Absence of the DTO marker means the class is outside DTO policy scope.
- DTO gates **MUST NOT** infer DTO status from:
  - class name;
  - namespace;
  - directory;
  - suffix;
  - constructor shape;
  - property shape;
  - package ownership.
- Phase 1 DTO detection **MUST** be attribute-only.
- The canonical DTO marker is `Coretsia\Dto\Attribute\Dto`.
- DTO policy **MUST NOT** impose DTO rules on unmarked:
  - value objects;
  - descriptors;
  - result models;
  - shape models;
  - context models;
  - runtime services;
  - artifact builders;
  - config builders;
  - container builders;
  - kernel runtime models.

## Canonical vocabulary

- **DTO** — explicit transport class, opt-in via marker, enforced by DTO gates.
- **VO** — value object with behavior, invariants, validation, normalization, or domain semantics; outside DTO gate scope unless explicitly marked.
- **Descriptor** — structured model used for cross-package or runtime boundaries; not automatically a DTO.
- **Result** — structured operation result; not automatically a DTO.
- **Shape** — structured payload or schema-like model; not automatically a DTO.
- **Context model** — runtime/context carrier model; not automatically a DTO.

## Scope rule

DTO policy applies only to classes explicitly marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Unmarked classes are outside DTO gate scope.

Contracts VOs, descriptors, result models, artifact payload models, config trace models, and runtime services **MUST NOT** be treated as DTOs unless explicitly marked.

## Canonical DTO rules

A compliant DTO in Coretsia Phase 1:

- **MUST** be explicitly marked with `#[Coretsia\Dto\Attribute\Dto]`;
- **MUST** be declared as `final class`;
- **MUST** contain only instance properties;
- **MUST** declare an explicit type for every property;
- **MUST** expose every property as `public`;
- **MUST NOT** declare static properties;
- **MUST NOT** extend another class;
- **MUST NOT** be extended;
- **MUST NOT** use traits;
- **MUST NOT** implement interfaces;
- **MUST NOT** contain methods except optional `__construct(...)`;
- **MUST NOT** contain business logic;
- **MUST NOT** perform I/O;
- **MUST NOT** depend on services;
- **MUST NOT** define an inheritance-based extension model.

## Constructor rule

A DTO may declare `__construct(...)`.

The constructor **MUST** be limited to DTO initialization. It **MUST NOT** introduce:

- validation policy;
- normalization policy;
- service calls;
- filesystem access;
- network access;
- environment access;
- process execution;
- global state access;
- business decisions.

DTO gates may enforce constructor restrictions incrementally through specialized gates.

## What DTO is not

A DTO is not:

- a domain entity;
- a service;
- a policy object;
- a validator;
- a stateful runtime object;
- a behavior-rich value object;
- a descriptor by default;
- a result model by default;
- a shape class by default;
- a kernel runtime orchestrator;
- an artifact builder;
- a config builder;
- a container builder.

## Marker strategy

The canonical marker package is:

```text
coretsia/core-dto-attribute
```

The canonical marker class is:

```text
Coretsia\Dto\Attribute\Dto
```

The marker attribute:

- **MUST** target classes only;
- **MUST** have no parameters in Phase 1;
- **MUST** have no runtime behavior;
- **MUST** be used only to opt a class into DTO policy.

## Compliant example

```php
<?php

declare(strict_types=1);

namespace Coretsia\Example;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class ExampleDto
{
    public function __construct(
        public string $name,
        public int $count,
    ) {
    }
}
```

## Non-compliant examples

### Missing marker

This class is not a DTO and is outside DTO gate scope:

```php
<?php

declare(strict_types=1);

namespace Coretsia\Example;

final class ExampleShape
{
    public function __construct(
        public string $name,
    ) {
    }
}
```

### Marked DTO with behavior

This class opts into DTO policy and is non-compliant because it contains behavior:

```php
<?php

declare(strict_types=1);

namespace Coretsia\Example;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class ExampleDto
{
    public function __construct(
        public string $name,
    ) {
    }

    public function normalizedName(): string
    {
        return trim($this->name);
    }
}
```

### Marked DTO with private state

This class opts into DTO policy and is non-compliant because DTO properties must be public typed instance properties:

```php
<?php

declare(strict_types=1);

namespace Coretsia\Example;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class ExampleDto
{
    public function __construct(
        private string $name,
    ) {
    }
}
```

## Gate policy

DTO gates **MUST** follow the canonical tooling gate output policy:

- line 1: deterministic error `CODE` only;
- line 2+: stable diagnostics using normalized repo-relative paths and fixed reason tokens;
- diagnostics sorted by byte-order `strcmp`;
- no secrets;
- no values;
- no method bodies;
- no property values;
- no class body dumps.

DTO aggregate execution **MUST** preserve specialized gate output unchanged.

## Out of scope

The following DTO models are out of scope for this rail and require a future epic or ADR:

- getter-only DTOs;
- private-readonly DTOs;
- wither-based DTOs;
- interface marker fallback;
- naming-based DTO detection;
- directory-based DTO detection;
- serializer-specific DTO policy;
- validation-rich DTOs;
- behavior-rich transport models.
