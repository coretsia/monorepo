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

# Migrations Contracts SSoT

## Scope

This document is the Single Source of Truth for Coretsia migration contracts, migration boundary policy, migration determinism expectations, migration tooling redaction rules, and runtime ownership boundaries for future migration implementations.

This document governs contracts introduced by epic `1.150.0` under:

```text
framework/packages/core/contracts/src/Migrations/
```

The canonical migration contract introduced by this epic is:

```text
Coretsia\Contracts\Migrations\MigrationInterface
```

The implementation path is:

```text
framework/packages/core/contracts/src/Migrations/MigrationInterface.php
```

This document complements and depends on:

```text
docs/ssot/database-contracts.md
```

The database contracts SSoT is the canonical source of truth for:

```text
Coretsia\Contracts\Database\ConnectionInterface
Coretsia\Contracts\Database\SqlQueryInterface
Coretsia\Contracts\Database\SqlQuery
Coretsia\Contracts\Database\QueryResultInterface
Coretsia\Contracts\Database\SqlDialectInterface
DbValue
DbRow
DbRows
```

This document MUST NOT redefine competing database value, SQL query, connection, result, driver, or dialect semantics.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Migration execution must be expressible through a stable contracts-level migration port without coupling `core/contracts` to database drivers, migration runners, migration discovery, migration registries, CLI commands, generated artifacts, platform packages, integration packages, or vendor database APIs.

The contracts introduced by this epic define only:

- a minimal migration interface;
- the migration dependency on `ConnectionInterface`;
- migration redaction policy;
- migration determinism expectations for future runtime owners;
- dependency and ownership boundaries for future migration packages.

The contracts package MUST NOT implement migration execution, migration discovery, migration ordering, migration registries, migration persistence, migration CLI commands, database driver behavior, SQL compilation, transaction orchestration, configuration loading, DI wiring, generated artifacts, or exception mapping.

## Phase 0 lock-source alignment

This SSoT preserves the following Phase 0 invariants:

- `0.20.0` no-secrets output policy applies to migration diagnostics, migration tooling, SQL diagnostics, driver diagnostics, and CLI output.
- `0.60.0` missing vs empty MUST remain distinguishable when future migration owners model migration state, registry records, checksums, or execution metadata.
- `0.70.0` json-like payloads forbid floats, including `NaN`, `INF`, and `-INF`.
- `0.70.0` lists preserve order and maps use deterministic key ordering when json-like migration metadata is introduced by future owners.
- `0.90.0` safe diagnostics MUST NOT expose raw values and MAY expose only safe derivations such as `hash(value)` or `len(value)`.

Epic `1.150.0` itself introduces no migration implementation, no migration runner, no migration discovery, no migration registry, no migration CLI, no migration config root, no migration DI tag, and no migration artifact.

## Contract boundary

Migration contracts are format-neutral and runtime-neutral.

They define a stable interface and boundary policy only.

The contracts package MUST NOT implement:

- migration discovery;
- migration file scanning;
- migration class scanning;
- migration sorting;
- migration graph validation;
- migration dependency resolution;
- migration registry behavior;
- migration persistence tables;
- migration lock behavior;
- migration runner behavior;
- migration batch behavior;
- migration rollback planning;
- migration CLI commands;
- migration status output;
- migration artifact generation;
- migration artifact reading;
- database connection factories;
- database connection registries;
- database driver registries;
- SQL compilers;
- schema builders;
- query builders;
- PDO wrappers;
- transaction managers;
- savepoint managers;
- database exception mapping;
- migration exception mapping;
- DI registration;
- runtime discovery;
- config defaults;
- config rules;
- package providers.

Runtime owner packages implement concrete migration behavior later.

## Contract package dependency policy

Migration contracts MUST remain dependency-free beyond PHP itself and the database contracts introduced by epic `1.150.0`.

Migration contracts MAY depend on:

```text
Coretsia\Contracts\Database\ConnectionInterface
```

Migration contracts MUST NOT depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- `Psr\Http\Message\StreamInterface`
- `PDO`
- `PDOStatement`
- `PDOException`
- `mysqli`
- `SQLite3`
- `PgSql\*`
- Doctrine DBAL classes
- Eloquent classes
- Cycle ORM classes
- Laminas DB classes
- vendor migration classes
- vendor schema builders
- vendor query builders
- vendor database clients
- concrete service container implementations
- concrete logger implementations
- concrete tracing implementations
- concrete metrics implementations
- framework tooling packages
- generated architecture artifacts

Runtime packages MAY depend on `core/contracts`.

`core/contracts` MUST NOT depend back on runtime packages.

Allowed direction:

```text
platform/migrations → core/contracts
platform/database → core/contracts
platform/database-driver-* → core/contracts
integrations/* database drivers → core/contracts
application migrations → core/contracts
```

Forbidden direction:

```text
core/contracts → platform/migrations
core/contracts → platform/database
core/contracts → platform/database-driver-*
core/contracts → integrations/*
```

## DTO terminology boundary

This document uses the terms `contract`, `port`, and `runtime boundary` according to:

```text
docs/ssot/dto-policy.md
```

`MigrationInterface` is a contracts interface.

It is not a DTO-marker class.

DTO gates apply only to classes explicitly marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Interfaces introduced by epic `1.150.0` MUST NOT be treated as DTOs.

## Database dependency authority

Migrations use database access through the database contracts boundary.

The database contracts SSoT is authoritative for:

- `ConnectionInterface`;
- `SqlQueryInterface`;
- `SqlQuery`;
- `QueryResultInterface`;
- `SqlDialectInterface`;
- `DbValue = int|string|bool|null`;
- `DbRow = array<string, DbValue>`;
- `DbRows = list<DbRow>`;
- positional bindings;
- no-float database value policy;
- no `__toString()` SQL leakage;
- driver id semantics;
- connection name semantics;
- dialect boundary semantics;
- database redaction rules.

This migrations SSoT MUST NOT duplicate or redefine those database contract rules.

If a migration needs to execute SQL, it is expected to use database contracts such as:

```text
ConnectionInterface
SqlQueryInterface
SqlQuery
```

The exact SQL syntax, placeholder style, dialect behavior, transactional DDL behavior, and driver-specific validity are database/runtime-owned.

## Migration interface

`MigrationInterface` is the canonical contracts-level migration boundary.

The implementation path is:

```text
framework/packages/core/contracts/src/Migrations/MigrationInterface.php
```

The canonical interface shape is:

```text
up(ConnectionInterface $connection): void
down(ConnectionInterface $connection): void
```

`up()` applies the migration.

`down()` reverts the migration when the migration author and runtime owner support rollback semantics.

Both methods receive only:

```text
Coretsia\Contracts\Database\ConnectionInterface
```

Both methods return:

```text
void
```

`MigrationInterface` MUST NOT require:

- migration runner objects;
- migration registry objects;
- migration context objects;
- migration event objects;
- migration output objects;
- CLI input/output objects;
- database driver objects;
- database connection factory objects;
- database connection registry objects;
- PDO objects;
- vendor connection objects;
- vendor statement objects;
- vendor schema builder objects;
- vendor migration base classes;
- service container objects;
- runtime wiring objects.

The migration contract is intentionally minimal.

## No metadata methods in this epic

Epic `1.150.0` intentionally introduces no migration metadata methods.

`MigrationInterface` MUST NOT expose:

- `id()`;
- `name()`;
- `version()`;
- `timestamp()`;
- `description()`;
- `dependencies()`;
- `tags()`;
- `checksum()`;
- `batch()`;
- `connectionName()`;
- `transactional()`;
- `isTransactional()`;
- `createdAt()`;
- `author()`;
- `moduleId()`;
- `packageId()`.

Migration identity, naming, versioning, dependency graphs, checksums, registry records, transaction policy, connection selection, and metadata extraction are runtime-owned by future `platform/migrations` or application-level owners.

A future runtime owner MAY derive migration metadata from:

- class names;
- file names;
- generated registries;
- explicit owner-owned manifests;
- package metadata;
- application configuration;
- another owner-defined source.

That behavior MUST be introduced through future owner SSoT and ADR updates.

It MUST NOT be added to `MigrationInterface` in epic `1.150.0`.

## SQL execution boundary

Migration methods MAY execute SQL through the received `ConnectionInterface`.

A migration MUST NOT require `ConnectionInterface` implementations to accept raw SQL strings directly.

Raw SQL must cross the database contract boundary through:

```text
SqlQueryInterface
```

or the canonical value object:

```text
SqlQuery
```

The canonical database query and binding semantics are defined by:

```text
docs/ssot/database-contracts.md
```

Migration code MAY use:

```text
$connection->execute(new SqlQuery($sql, $bindings));
```

where `$bindings` are positional:

```text
list<int|string|bool|null>
```

Associative bindings are forbidden by the database contracts SSoT.

Float bindings are forbidden by the database contracts SSoT.

`MigrationInterface` MUST NOT introduce a competing SQL carrier.

`MigrationInterface` MUST NOT introduce a migration-specific query object.

`MigrationInterface` MUST NOT introduce a migration-specific schema builder.

## Dialect boundary

Migrations MAY inspect the connection dialect through:

```text
ConnectionInterface::dialect()
```

The dialect contract is defined by:

```text
docs/ssot/database-contracts.md
```

Migrations MAY use dialect information for portable SQL decisions, such as:

- identifier quoting;
- boolean literals;
- limit/offset differences;
- returning support;
- identity column support;
- transactional DDL support.

Migrations MUST NOT depend on vendor dialect objects.

Migrations MUST NOT require Doctrine platform objects, PDO driver names, extension names, or vendor schema managers.

The dialect boundary is contracts-level and vendor-neutral.

Full SQL compilation remains runtime-owned.

## Transaction boundary

`MigrationInterface` does not define transaction orchestration.

A migration MAY call the minimal transaction primitives exposed by `ConnectionInterface`:

```text
beginTransaction()
commit()
rollBack()
```

However, the following are runtime-owned and outside this contracts epic:

- whether a migration runner wraps migrations in transactions;
- whether DDL is transactional for a given database;
- savepoint behavior;
- nested transactions;
- retry policy;
- deadlock handling;
- lock timeout handling;
- migration lock behavior;
- automatic rollback on failure;
- per-migration transactional metadata;
- transaction isolation.

A future `platform/migrations` owner MUST define transaction orchestration policy in its own SSoT before implementing a migration runner.

Migration diagnostics around transactions MUST NOT expose raw SQL, bindings, credentials, DSNs, tokens, private customer data, or vendor object dumps.

## Determinism expectations

Migration runtime behavior MUST be deterministic when implemented by a future runtime owner.

This contracts epic does not implement migration discovery, ordering, registry persistence, or CLI output.

Future migration owners MUST define deterministic policy for:

- migration discovery sources;
- migration identity;
- migration ordering;
- duplicate migration detection;
- migration registry records;
- migration status output;
- migration plan output;
- rollback selection;
- batch semantics;
- checksum semantics, if introduced;
- generated migration artifacts, if introduced.

Future migration ordering MUST NOT depend on:

- filesystem traversal order;
- Composer package declaration order;
- PHP hash-map insertion side effects;
- process locale;
- host platform;
- timestamps;
- random values.

Future migration diagnostics MUST NOT expose absolute local paths.

If source locations are needed for diagnostics, future owners MUST use safe logical identifiers or safe derivations such as:

```text
hash(path)
len(path)
```

This SSoT documents deterministic expectations only.

It does not define the runtime ordering algorithm for migrations.

## Migration implementation expectations

A migration implementation is expected to be an application/runtime class that implements:

```text
Coretsia\Contracts\Migrations\MigrationInterface
```

Migration implementations SHOULD keep their public migration surface compatible with the minimal contract:

```text
up(ConnectionInterface $connection): void
down(ConnectionInterface $connection): void
```

Migration implementations SHOULD use explicit SQL query objects and positional bindings.

Migration implementations SHOULD avoid writing directly to stdout, stderr, logs, metrics, spans, or health output.

Migration implementations SHOULD leave presentation and diagnostics to the future migration runner or tooling owner.

Migration implementations MUST NOT rely on `SqlQuery::__toString()` because `SqlQuery` MUST NOT expose `__toString()`.

Migration implementations MUST NOT require vendor database APIs in the migration contract method signatures.

Constructor injection for migration classes, if supported later, is runtime-owned and MUST NOT change the `MigrationInterface` method shape.

## Migration tooling redaction

Migration tooling is sensitive by default.

Migration tooling includes future:

- migration discovery commands;
- migration status commands;
- migration plan commands;
- migration apply commands;
- migration rollback commands;
- migration dry-run commands;
- migration diff commands;
- migration generated-output commands;
- migration diagnostics;
- migration failure output.

Migration tooling MUST NOT log, print, trace, render, export, or expose:

- raw SQL;
- raw SQL bindings;
- raw query result values;
- raw driver config;
- raw DSNs;
- database passwords;
- credentials;
- tokens;
- private keys;
- raw migration registry payloads when they contain sensitive data;
- vendor object dumps;
- stack traces by default;
- absolute local paths;
- private customer data;
- request bodies;
- response bodies;
- raw queue messages;
- profile payloads.

Migration tooling MUST NOT use raw SQL as:

- a metric label;
- a span name fragment;
- a log message;
- an error descriptor extension;
- a CLI output line;
- a health output field;
- an artifact identity.

Safe migration diagnostics MAY expose derived information such as:

```text
hash(sql)
len(sql)
hash(bindings)
len(bindings)
operation
driver
connectionName
outcome
migrationId
```

Safe derivations MUST NOT expose raw values or allow reconstruction of sensitive values.

`migrationId`, if introduced by a future runtime owner, MUST be safe, stable, bounded-cardinality, and non-sensitive before it is used in diagnostics.

## Observability policy

Migration contracts do not define observability signals.

Runtime implementations MAY emit logs, spans, metrics, profiling signals, or diagnostics around migrations only when those signals follow:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
docs/ssot/profiling-ports.md
```

Metric labels MUST remain within the canonical allowlist from:

```text
docs/ssot/observability.md
```

Epic `1.150.0` introduces no new metric label keys.

Raw SQL, SQL bindings, migration file names, filesystem paths, DSNs, database names, usernames, host names, schema names, tenant ids, user ids, request ids, and correlation ids MUST NOT become metric labels under the baseline policy.

The baseline label key `driver` MAY be used with safe logical driver ids.

The baseline label key `operation` MAY be used with safe bounded operation names.

The baseline label key `outcome` MAY be used with safe bounded outcome names.

The baseline label key `table` MAY be used only with owner-approved safe table labels.

Dynamic table names, tenant-specific table names, customer-specific schema names, arbitrary identifiers, and raw SQL fragments MUST NOT become metric labels.

## Error and exception policy

Epic `1.150.0` does not introduce a migration exception hierarchy.

Migration failure handling is runtime-owned.

Concrete migration runners MAY throw owner-defined exceptions.

Owner-defined migration exceptions and diagnostics MUST NOT expose:

- raw SQL;
- raw SQL bindings;
- credentials;
- tokens;
- private keys;
- raw DSNs;
- database passwords;
- private customer data;
- vendor object dumps;
- absolute local paths;
- stack traces by default.

If migration errors are normalized later, mapping to `ErrorDescriptor` is owned by runtime error mapping packages.

The contracts package MUST NOT implement migration exception mapping.

The contracts package MUST NOT require `ErrorDescriptor` construction inside migration contracts.

## Migration registry ownership

Epic `1.150.0` introduces no migration registry.

A future `platform/migrations` owner may define a migration registry for applied migrations.

That future owner is expected to own:

- registry table name policy;
- registry row shape;
- registry schema version;
- registry lock behavior;
- registry transaction behavior;
- registry consistency checks;
- registry read/write operations;
- registry diagnostics;
- registry migration between schema versions.

Registry data MUST be deterministic and safe according to future owner policy.

Registry diagnostics MUST NOT expose raw SQL, raw bindings, credentials, DSNs, private customer data, absolute local paths, or vendor object dumps.

## Migration discovery ownership

Epic `1.150.0` introduces no migration discovery.

A future `platform/migrations` owner may define discovery from:

- application directories;
- package metadata;
- generated registries;
- Composer metadata;
- PHP attributes;
- owner-defined manifests;
- another owner-defined source.

That future owner MUST define deterministic discovery behavior in its own SSoT.

Discovery MUST NOT depend on filesystem traversal order.

Discovery diagnostics MUST NOT expose absolute local paths by default.

The contracts package MUST NOT implement discovery or require a discovery source format.

## Migration CLI ownership

Epic `1.150.0` introduces no migration CLI.

A future `platform/migrations` owner may define CLI commands for migration operations.

That future owner MUST define:

- command names;
- command inputs;
- output format;
- exit code behavior;
- dry-run semantics;
- status rendering;
- plan rendering;
- rollback behavior;
- failure behavior;
- diagnostics redaction.

Migration CLI output MUST follow the no-secrets output policy.

Migration CLI output MUST NOT print raw SQL or raw bindings by default.

## Database driver ownership

Migrations do not own database drivers.

Migration contracts MUST NOT introduce:

- database driver ids;
- driver allowlists;
- driver aliases;
- driver factories;
- driver registries;
- driver configuration;
- driver tuning;
- PDO options;
- driver health checks;
- driver exception mapping.

Database driver contracts and driver boundary policy are owned by:

```text
docs/ssot/database-contracts.md
```

Concrete driver implementation is owned by future runtime driver packages such as:

```text
platform/database-driver-*
integrations/* database drivers
```

## Config policy

Epic `1.150.0` introduces no config roots and no config keys.

The contracts package MUST NOT require package config files for migration contracts.

No files under package `config/` are introduced by this epic.

Migration configuration is runtime-owned.

Future runtime owner packages MAY introduce migration config only through their own owner epics and the config roots registry process.

Future migration config may include owner-defined policy for:

- migration paths;
- migration namespaces;
- default connection;
- transaction mode;
- table names;
- lock behavior;
- batch behavior;
- dry-run behavior.

Those paths are not introduced by `core/contracts`.

## DI tag policy

Epic `1.150.0` introduces no DI tags.

The contracts package MUST NOT declare public migration tag constants.

The contracts package MUST NOT declare public database tag constants.

The contracts package MUST NOT define package-local mirror constants for migration or database tags.

The contracts package MUST NOT define migration tag metadata keys, migration tag priority semantics, migration discovery semantics, or migration runner semantics.

If a future runtime owner needs migration DI tags, that owner MUST introduce them through:

```text
docs/ssot/tags.md
```

according to tag registry rules.

## Artifact policy

Epic `1.150.0` introduces no artifacts.

The contracts package MUST NOT generate:

- migration artifacts;
- migration registry artifacts;
- migration plan artifacts;
- migration status artifacts;
- migration discovery artifacts;
- migration checksum artifacts;
- schema artifacts;
- database artifacts;
- driver artifacts;
- connection artifacts.

A future runtime owner MAY introduce generated migration artifacts only through its own owner epic and the artifact registry process.

Any future artifact MUST follow:

```text
docs/ssot/artifacts.md
```

## Contract surface restrictions

Migration public method signatures MUST NOT contain:

- `Psr\Http\Message\*`;
- `Psr\Http\Message\StreamInterface`;
- `Coretsia\Platform\*`;
- `Coretsia\Integrations\*`;
- `PDO`;
- `PDOStatement`;
- `PDOException`;
- `mysqli`;
- `SQLite3`;
- Doctrine DBAL classes;
- Eloquent classes;
- Cycle ORM classes;
- Laminas DB classes;
- vendor database clients;
- vendor query builders;
- vendor schema builders;
- vendor migration classes;
- resources;
- closures;
- streams;
- iterators;
- generators;
- concrete service container objects;
- runtime wiring objects.

Migration public method signatures introduced by this SSoT MUST use only:

- PHP built-in scalar/array/null/void types where applicable;
- contracts-level database types explicitly allowed by this epic.

The only database-specific object type allowed in `MigrationInterface` public method signatures is:

```text
Coretsia\Contracts\Database\ConnectionInterface
```

## What this epic MUST NOT create

Epic `1.150.0` MUST NOT create:

```text
framework/packages/platform/database/*
framework/packages/platform/migrations/*
framework/packages/platform/database-driver-*/*
framework/packages/integrations/*
config/*.php
provider/module wiring files
database implementation
migration runner
migration discovery
migration CLI
query compiler implementation
PDO wrapper implementation
transaction manager implementation
connection registry
connection factory implementation
database exception mapper
DI tag constants
artifact files
```

These are runtime-owned concerns for future owner epics.

## Runtime usage policy

`MigrationInterface` is the contracts boundary for future migration-aware runtime packages.

A future `platform/migrations` package is expected to own:

- migration discovery;
- migration registry behavior;
- migration ordering;
- migration execution;
- migration rollback behavior;
- migration status behavior;
- migration CLI commands;
- migration diagnostics;
- migration locking;
- migration persistence;
- migration transaction policy;
- migration error handling;
- safe migration observability integration.

A future `platform/database` package is expected to provide database runtime behavior through the database contracts boundary.

Migration implementations are expected to receive `ConnectionInterface` and execute explicit `SqlQueryInterface` objects.

This is documented runtime policy only.

Epic `1.150.0` does not create or modify runtime migration packages.

## Acceptance scenario

When a future runtime package executes migrations:

1. the runtime owner discovers migration classes through runtime-owned deterministic policy;
2. each migration implements `MigrationInterface`;
3. migration ordering is determined by runtime-owned deterministic policy;
4. the runtime owner selects a database connection through runtime-owned database policy;
5. the migration receives only `ConnectionInterface` through `up()` or `down()`;
6. the migration executes SQL through `SqlQueryInterface` or `SqlQuery`;
7. query bindings are positional `list<DbValue>`;
8. no float values cross the database contracts boundary;
9. dialect differences are accessed through `ConnectionInterface::dialect()`;
10. no PDO or vendor database API appears in `MigrationInterface`;
11. no platform or integration package is required by `core/contracts`;
12. migration tooling does not log raw SQL, bindings, credentials, DSNs, or vendor diagnostics;
13. migration discovery, registry, ordering, CLI, and runner behavior remain runtime-owned.

This acceptance scenario is policy intent.

The concrete migration runner, discovery implementation, ordering algorithm, registry schema, CLI behavior, locking, transaction orchestration, error mapping, configuration, observability integration, and database driver implementation are runtime-owned.

## Verification evidence

Contracts-level enforcement evidence for this epic includes:

```text
framework/packages/core/contracts/tests/Contract/MigrationInterfaceShapeContractTest.php
```

This test is expected to verify:

- `MigrationInterface` exists;
- `MigrationInterface` is an interface;
- `MigrationInterface` exposes the canonical method surface;
- `up()` accepts only `ConnectionInterface` and returns `void`;
- `down()` accepts only `ConnectionInterface` and returns `void`;
- `MigrationInterface` does not expose metadata methods;
- migration contracts do not depend on platform packages;
- migration contracts do not depend on integration packages;
- migration contracts do not depend on `Psr\Http\Message\*`;
- migration contracts do not depend on PDO or vendor database concretes;
- migration contracts do not expose streams, resources, iterators, generators, closures, or vendor migration objects;
- migration contracts do not declare DI tag constants;
- migration contracts do not introduce config or artifact concepts.

Architecture gates are expected to verify that `core/contracts` does not introduce forbidden compile-time dependencies.

## Non-goals

This SSoT does not define:

- concrete migration implementation;
- migration runner;
- migration discovery;
- migration ordering algorithm;
- migration graph validation;
- migration dependency resolution;
- migration registry;
- migration persistence table;
- migration locking;
- migration CLI;
- migration status output;
- migration plan output;
- migration rollback planner;
- migration batch behavior;
- migration checksum behavior;
- migration metadata methods;
- migration config roots;
- migration config defaults;
- migration config rules;
- migration DI tags;
- migration artifacts;
- database implementation;
- database driver implementation;
- connection registry;
- connection factory;
- transaction manager;
- savepoint manager;
- SQL compiler implementation;
- query builder;
- schema builder;
- PDO wrapper;
- database exception mapper;
- migration exception mapper;
- `platform/database` implementation;
- `platform/migrations` implementation;
- `platform/database-driver-*` implementation;
- `integrations/*` driver implementation.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Database Contracts SSoT](./database-contracts.md)
- [Observability Naming and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [ErrorDescriptor SSoT](./error-descriptor.md)
- [DTO Policy](./dto-policy.md)
- [Config Roots Registry](./config-roots.md)
- [Tag Registry](./tags.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
