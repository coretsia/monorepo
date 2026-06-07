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

# Config Directives Examples (SSoT)

## Scope

This document is the Single Source of Truth for examples of Coretsia config directives.

It explains how directive-shaped config payloads are intended to look in package, skeleton, and application config files.

This document intentionally does not duplicate the full runtime validation rules.

The canonical directive allowlist is owned by:

```text
framework/packages/core/contracts/src/Config/ConfigDirective.php
```

The per-file directive namespace and type processing implementation is owned by:

```text
framework/packages/core/kernel/src/Config/DirectiveProcessor.php
```

The merge-time directive application implementation is owned by:

```text
framework/packages/core/kernel/src/Config/ConfigMerger.php
```

The config and env policy is owned by:

```text
docs/ssot/config-and-env.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Config directives allow higher-rank config sources to express list and map mutations without requiring every config layer to repeat the entire effective value.

The examples in this document exist to make directive usage understandable and testable while keeping the executable rules centralized in runtime code and the broader config policy centralized in `docs/ssot/config-and-env.md`.

## Source-of-truth boundaries

This document owns:

- examples for `@append`;
- examples for `@prepend`;
- examples for `@remove`;
- examples for `@merge`;
- examples for `@replace`;
- examples showing aggregate `roots.php` usage;
- examples showing split `<root>.php` usage;
- examples showing empty directive payloads.

This document does not own:

- the directive enum allowlist;
- namespace validation;
- mixed-level validation;
- directive value type validation;
- config file discovery;
- Phase B source precedence;
- env overlay generation;
- semantic config validation;
- config explain output.

Those are owned by the corresponding runtime files and SSoT documents listed in the scope section.

## Directive processing model

Directive processing has two phases.

First, each config file is processed independently.

```text
config file payload
→ directive namespace/type processing
→ normalized config tree
```

Second, normalized config trees are merged in Phase B order.

```text
lower-rank config
→ higher-rank normalized config
→ ConfigMerger applies directives when the base value is known
```

This distinction is important because directives such as `@append`, `@prepend`, `@remove`, and `@merge` need the previous/base value to produce the effective result.

## Merge-time base compatibility

Directive payload validation and merge-time base compatibility are distinct.

DirectiveProcessor validates the directive payload shape before merge.

ConfigMerger validates the existing lower-rank/base value shape when applying the normalized directive.

Missing base values are allowed and are interpreted by directive context:

```text
@append  + missing base => empty list base
@prepend + missing base => empty list base
@remove  + missing base => empty list base
@merge   + missing base => empty map base
```

Existing base values with an incompatible container kind are rejected deterministically:

```text
@append  requires existing base to be a list
@prepend requires existing base to be a list
@remove  requires existing base to be a list
@merge   requires existing base to be a map
```

These failures use the canonical directive type mismatch error code:

```text
CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH
```

The stable reason tokens distinguish payload mismatch from base mismatch:

```text
config-directive-list-value-must-be-list
config-directive-merge-value-must-be-map
list-directive-base-must-be-list
merge-directive-base-must-be-map
```

## File shapes

### Aggregate root-map file

A `roots.php` file returns a global root map.

Example:

```php
<?php

declare(strict_types=1);

return [
    'kernel' => [
        'boot' => [
            'default_env' => 'prod',
        ],
    ],
    'custom_app' => [
        'features' => [
            'search' => true,
        ],
    ],
];
```

### Split root-subtree file

A `<root>.php` file returns only the subtree for that root and does not repeat the root wrapper.

Example file:

```text
skeleton/config/kernel.php
```

Valid shape:

```php
<?php

declare(strict_types=1);

return [
    'boot' => [
        'default_env' => 'dev',
    ],
];
```

Invalid shape:

```php
<?php

declare(strict_types=1);

return [
    'kernel' => [
        'boot' => [
            'default_env' => 'dev',
        ],
    ],
];
```

## `@append`

`@append` appends list items after the existing list.

### Example

Lower-rank effective config:

```php
[
    'http' => [
        'middleware' => [
            'app' => [
                'AuthMiddleware',
            ],
        ],
    ],
]
```

Higher-rank split root file:

```php
<?php

declare(strict_types=1);

return [
    'middleware' => [
        'app' => [
            '@append' => [
                'AuditMiddleware',
                'ResponseHeaderMiddleware',
            ],
        ],
    ],
];
```

Effective result:

```php
[
    'http' => [
        'middleware' => [
            'app' => [
                'AuthMiddleware',
                'AuditMiddleware',
                'ResponseHeaderMiddleware',
            ],
        ],
    ],
]
```

### Empty append

An empty append payload expresses no new items.

```php
[
    'middleware' => [
        'app' => [
            '@append' => [],
        ],
    ],
]
```

The effective list remains unchanged.

## `@prepend`

`@prepend` prepends list items before the existing list.

### Example

Lower-rank effective config:

```php
[
    'http' => [
        'middleware' => [
            'app' => [
                'AuthMiddleware',
                'ControllerMiddleware',
            ],
        ],
    ],
]
```

Higher-rank split root file:

```php
<?php

declare(strict_types=1);

return [
    'middleware' => [
        'app' => [
            '@prepend' => [
                'RequestIdMiddleware',
            ],
        ],
    ],
];
```

Effective result:

```php
[
    'http' => [
        'middleware' => [
            'app' => [
                'RequestIdMiddleware',
                'AuthMiddleware',
                'ControllerMiddleware',
            ],
        ],
    ],
]
```

### Empty prepend

An empty prepend payload expresses no new items.

```php
[
    'middleware' => [
        'app' => [
            '@prepend' => [],
        ],
    ],
]
```

The effective list remains unchanged.

## `@remove`

`@remove` removes matching items from the existing list.

### Example

Lower-rank effective config:

```php
[
    'http' => [
        'middleware' => [
            'app' => [
                'RequestIdMiddleware',
                'AuthMiddleware',
                'DebugToolbarMiddleware',
                'ControllerMiddleware',
            ],
        ],
    ],
]
```

Higher-rank split root file:

```php
<?php

declare(strict_types=1);

return [
    'middleware' => [
        'app' => [
            '@remove' => [
                'DebugToolbarMiddleware',
            ],
        ],
    ],
];
```

Effective result:

```php
[
    'http' => [
        'middleware' => [
            'app' => [
                'RequestIdMiddleware',
                'AuthMiddleware',
                'ControllerMiddleware',
            ],
        ],
    ],
]
```

### Empty remove

An empty remove payload expresses no removed items.

```php
[
    'middleware' => [
        'app' => [
            '@remove' => [],
        ],
    ],
]
```

The effective list remains unchanged.

## `@merge`

`@merge` merges a map into the existing map.

### Example

Lower-rank effective config:

```php
[
    'kernel' => [
        'boot' => [
            'default_env' => 'prod',
            'default_app' => 'main',
        ],
        'modules' => [
            'discovery' => [
                'enabled' => true,
                'source' => 'composer',
            ],
        ],
    ],
]
```

Higher-rank split root file:

```php
<?php

declare(strict_types=1);

return [
    'boot' => [
        '@merge' => [
            'default_env' => 'dev',
        ],
    ],
    'modules' => [
        'discovery' => [
            '@merge' => [
                'source' => 'static',
            ],
        ],
    ],
];
```

Effective result:

```php
[
    'kernel' => [
        'boot' => [
            'default_app' => 'main',
            'default_env' => 'dev',
        ],
        'modules' => [
            'discovery' => [
                'enabled' => true,
                'source' => 'static',
            ],
        ],
    ],
]
```

### Empty merge

An empty merge payload expresses no changed map keys.

```php
[
    'boot' => [
        '@merge' => [],
    ],
]
```

The effective map remains unchanged.

## `@replace`

`@replace` replaces the existing node with the supplied value.

The replacement value may be a scalar, list, or map.

### Replace with scalar

Lower-rank effective config:

```php
[
    'kernel' => [
        'boot' => [
            'default_env' => 'prod',
        ],
    ],
]
```

Higher-rank split root file:

```php
<?php

declare(strict_types=1);

return [
    'boot' => [
        'default_env' => [
            '@replace' => 'test',
        ],
    ],
];
```

Effective result:

```php
[
    'kernel' => [
        'boot' => [
            'default_env' => 'test',
        ],
    ],
]
```

### Replace with list

Lower-rank effective config:

```php
[
    'http' => [
        'middleware' => [
            'app' => [
                'AuthMiddleware',
                'ControllerMiddleware',
            ],
        ],
    ],
]
```

Higher-rank split root file:

```php
<?php

declare(strict_types=1);

return [
    'middleware' => [
        'app' => [
            '@replace' => [
                'ControllerMiddleware',
            ],
        ],
    ],
];
```

Effective result:

```php
[
    'http' => [
        'middleware' => [
            'app' => [
                'ControllerMiddleware',
            ],
        ],
    ],
]
```

### Replace with map

Lower-rank effective config:

```php
[
    'kernel' => [
        'modules' => [
            'discovery' => [
                'enabled' => true,
                'source' => 'composer',
            ],
        ],
    ],
]
```

Higher-rank split root file:

```php
<?php

declare(strict_types=1);

return [
    'modules' => [
        'discovery' => [
            '@replace' => [
                'enabled' => false,
            ],
        ],
    ],
];
```

Effective result:

```php
[
    'kernel' => [
        'modules' => [
            'discovery' => [
                'enabled' => false,
            ],
        ],
    ],
]
```

### Empty replace

An empty array replacement replaces the node with an empty container.

```php
[
    'modules' => [
        'discovery' => [
            '@replace' => [],
        ],
    ],
]
```

Effective result for that node:

```php
[]
```

## Aggregate and split file example

A shared aggregate config file may provide defaults for several roots.

File:

```text
skeleton/config/roots.php
```

Example:

```php
<?php

declare(strict_types=1);

return [
    'kernel' => [
        'boot' => [
            'default_env' => 'prod',
        ],
    ],
    'http' => [
        'middleware' => [
            'app' => [
                'AuthMiddleware',
            ],
        ],
    ],
];
```

A split root file at the same layer may refine only one root.

File:

```text
skeleton/config/http.php
```

Example:

```php
<?php

declare(strict_types=1);

return [
    'middleware' => [
        'app' => [
            '@append' => [
                'ControllerMiddleware',
            ],
        ],
    ],
];
```

Effective result for `http.middleware.app`:

```php
[
    'AuthMiddleware',
    'ControllerMiddleware',
]
```

## Environment-specific example

A shared skeleton config may define production-oriented defaults.

File:

```text
skeleton/config/http.php
```

Example:

```php
<?php

declare(strict_types=1);

return [
    'middleware' => [
        'app' => [
            'AuthMiddleware',
            'ControllerMiddleware',
        ],
    ],
];
```

An environment-specific config may remove one item for a local environment.

File:

```text
skeleton/config/environments/local/http.php
```

Example:

```php
<?php

declare(strict_types=1);

return [
    'middleware' => [
        'app' => [
            '@remove' => [
                'AuthMiddleware',
            ],
        ],
    ],
];
```

Effective result for `local`:

```php
[
    'ControllerMiddleware',
]
```

## Nested directives example

Directives may appear at nested config nodes when that node is the intended merge target.

Example:

```php
<?php

declare(strict_types=1);

return [
    'modules' => [
        'discovery' => [
            '@merge' => [
                'source' => 'static',
            ],
        ],
    ],
];
```

This targets:

```text
kernel.modules.discovery
```

and merges the supplied map into that node.

## Invalid examples

The examples in this section are illustrative. Canonical validation behavior is owned by the runtime implementation and config policy SSoTs.

### Unknown directive key

```php
[
    'middleware' => [
        'app' => [
            '@push' => [
                'ControllerMiddleware',
            ],
        ],
    ],
]
```

The `@*` namespace is reserved for config directives.

### Mixed directive level

```php
[
    'middleware' => [
        'app' => [
            '@append' => [
                'ControllerMiddleware',
            ],
            'other' => true,
        ],
    ],
]
```

A directive level is a directive payload shape, not a normal mixed config map.

### Multiple directives at one level

```php
[
    'middleware' => [
        'app' => [
            '@append' => [
                'ControllerMiddleware',
            ],
            '@remove' => [
                'DebugToolbarMiddleware',
            ],
        ],
    ],
]
```

Use separate config layers when separate directive operations are needed at the same target.

### List directive with map payload

```php
[
    'middleware' => [
        'app' => [
            '@append' => [
                'name' => 'ControllerMiddleware',
            ],
        ],
    ],
]
```

List-style directive examples use list payloads.

### Merge directive with list payload

```php
[
    'boot' => [
        '@merge' => [
            'prod',
            'dev',
        ],
    ],
]
```

Map-style merge examples use map payloads.

### List directive against non-list base

Lower-rank effective config:

```php
[
    'http' => [
        'middleware' => [
            'app' => [
                'name' => 'AuthMiddleware',
            ],
        ],
    ],
]
```

Higher-rank config:

```php
[
    'middleware' => [
        'app' => [
            '@append' => [
                'ControllerMiddleware',
            ],
        ],
    ],
]
```

The `@append` payload is valid because it is a list.

The merge still fails because the existing base value at `http.middleware.app` is a map, not a list.

The failure reason is:

```text
list-directive-base-must-be-list
```

### Merge directive against non-map base

Lower-rank effective config:

```php
[
    'http' => [
        'features' => [
            'request_id',
            'trace',
        ],
    ],
]
```

Higher-rank config:

```php
[
    'features' => [
        '@merge' => [
            'request_id' => true,
        ],
    ],
]
```

The `@merge` payload is valid because it is a map.

The merge still fails because the existing base value at `http.features` is a list, not a map.

The failure reason is:

```text
merge-directive-base-must-be-map
```

## Diagnostic safety

Directive diagnostics MUST remain safe.

Directive diagnostics MUST NOT expose:

- raw config values;
- raw env values;
- secrets;
- tokens;
- DSNs;
- cookies;
- headers;
- raw SQL;
- payloads;
- object dumps;
- PHP warnings;
- stack traces;
- previous throwable messages;
- absolute local paths.

Safe diagnostics MAY identify:

- stable reason tokens;
- directive names;
- safe config dot paths;
- normalized relative source ids;
- normalized relative source paths.

## Non-goals

This document does not define:

- the full config directive validation algorithm;
- the full config merge algorithm;
- config file discovery;
- config source precedence;
- env overlay projection;
- env value coercion;
- semantic config validation;
- config explain trace format;
- package config ownership;
- generated config artifacts;
- CLI commands.

## Enforcement rails

Expected enforcement belongs to runtime tests and SSoT consistency gates.

Test coverage SHOULD include:

- each valid directive example;
- unknown `@*` directive rejection;
- mixed directive level rejection;
- directive value type mismatch rejection;
- empty directive payload behavior;
- aggregate `roots.php` and split `<root>.php` equivalence scenarios;
- deterministic rerun behavior.

The canonical runtime files for directive enforcement are:

```text
framework/packages/core/contracts/src/Config/ConfigDirective.php
framework/packages/core/kernel/src/Config/DirectiveProcessor.php
framework/packages/core/kernel/src/Config/ConfigMerger.php
```

## Cross-references

- [SSoT Index](./INDEX.md)
- [Config and env SSoT](./config-and-env.md)
- [Config Roots Registry](./config-roots.md)
- [Config Merge Order](./config-merge-order.md)
- [Config Precedence Matrix](./config-precedence-matrix.md)
- [Observability Naming, Metrics Catalog, and Labels Allowlist](./observability.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
