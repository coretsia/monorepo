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

# ADR-0009: Database and migrations ports

## Status

Accepted.

## Context

Epic `1.150.0` introduces stable database and migration contracts under:

```text
framework/packages/core/contracts/src/Database/
framework/packages/core/contracts/src/Migrations/
```

Future `platform/database`, future `platform/migrations`, and future database driver packages need to interoperate through a stable contracts boundary without coupling `core/contracts` to database vendor APIs, runtime platform packages, integration packages, generated artifacts, configuration loading, dependency injection wiring, migration discovery, migration runners, query builders, schema builders, or SQL compiler implementations.

The detailed normative policy for this ADR is defined by:

```text
docs/ssot/database-contracts.md
docs/ssot/migrations-contracts.md
```

The contracts package must remain a pure library boundary.

It must not depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- `PDO`
- `PDOStatement`
- `PDOException`
- `mysqli`
- `SQLite3`
- `PgSql\*`
- `DateTimeInterface`
- `DateTimeImmutable`
- `DateTime`
- Doctrine DBAL classes
- Eloquent classes
- Cycle ORM classes
- Laminas DB classes
- vendor database clients
- vendor result objects
- vendor query builders
- vendor schema builders
- vendor migration classes
- framework tooling packages
- generated architecture artifacts

Phase 0 also cemented safety and determinism rules that directly affect database and migration contracts:

- json-like payloads forbid floats;
- missing and empty values must remain distinguishable where the boundary needs presence-sensitive behavior;
- safe diagnostics must not expose raw values;
- no-secrets output policy applies to diagnostics, tooling output, and generated-output surfaces;
- lists preserve order;
- maps use deterministic key ordering when exported or serialized by future owners.

Database contracts therefore need to model query input, query result output, driver identity, connection identity, dialect behavior, transaction primitives, and migration execution without exposing implementation-specific database handles.

## Decision

Coretsia will introduce database and migration contracts as vendor-neutral, format-neutral contracts in `core/contracts`.

The contracts introduced by epic `1.150.0` define:

- `SqlQueryInterface`
- `SqlQuery`
- `DatabaseDriverInterface`
- `ConnectionInterface`
- `QueryResultInterface`
- `SqlDialectInterface`
- `MigrationInterface`

The contracts package defines only stable ports, value semantics, boundary rules, and redaction policy.

It does not implement:

- concrete database access;
- concrete drivers;
- PDO wrappers;
- connection factories;
- connection registries;
- transaction managers;
- savepoint managers;
- query builders;
- schema builders;
- SQL compilers;
- migration runners;
- migration discovery;
- migration registries;
- migration CLI commands;
- database configuration loading;
- database exception mapping;
- dependency injection wiring;
- generated artifacts.

## Database value domain decision

Database contracts use a scalar-only value domain:

```text
DbValue = int|string|bool|null
DbRow = array<string, DbValue>
DbRows = list<DbRow>
```

`DbValue` intentionally excludes `float`.

Database decimals, floating-point values, big integers that are not safely bounded, dates, times, binary values, and JSON values cross the contracts boundary as `string`.

This preserves deterministic behavior and avoids introducing precision loss, `NaN`, `INF`, `-INF`, platform-specific numeric coercion, or locale-dependent numeric formatting into contracts.

If a future owner needs typed decimal, timestamp, binary, JSON, or identifier models, that owner must introduce dedicated format-neutral contracts through a future SSoT and ADR update.

## No vendor concrete types decision

Database and migration contracts must not expose vendor concrete database types.

This includes:

```text
PDO
PDOStatement
PDOException
mysqli
SQLite3
PgSql\*
DateTimeInterface
DateTimeImmutable
DateTime
Doctrine DBAL classes
Eloquent classes
Cycle ORM classes
Laminas DB classes
vendor connection objects
vendor statement objects
vendor result objects
vendor schema builders
vendor query builders
vendor migration classes
```

Vendor objects are implementation details of future driver or runtime packages.

Exposing them from `core/contracts` would make the contracts package depend on specific storage technology, prevent alternative implementations, and force migration code to know about driver internals.

Dates and times are also not exposed as `DateTimeInterface` values because database date/time semantics vary by backend, timezone policy, precision, storage type, and hydration strategy. At this boundary, date/time values are represented as strings unless a future owner introduces dedicated format-neutral time value contracts.

## SQL query carrier decision

`SqlQueryInterface` is the canonical contracts-level SQL query carrier.

Its canonical shape is:

```text
sql(): string
bindings(): array
```

The PHPDoc return shape for `bindings()` is:

```php
@return list<int|string|bool|null>
```

`SqlQuery` is the canonical immutable contracts value object implementing `SqlQueryInterface`.

`SqlQuery` is intentionally an opaque-ish value object.

It carries raw SQL and positional bindings, but it does not parse, normalize, classify, validate grammar, strip comments, infer operation kind, quote identifiers, or compile SQL.

SQL grammar, placeholder style, identifier quoting, SQL validity, operation classification, and backend-specific syntax are runtime-owned.

`SqlQuery` accepts any non-empty SQL string, including multiline SQL.

It rejects empty or whitespace-only SQL strings.

This is structural validation only. It is not SQL grammar validation.

`SqlQuery` must not implement:

```text
__toString()
```

This is deliberate.

Stringification would make accidental raw SQL leakage too easy in logs, exception messages, traces, metrics, CLI output, migration tooling, and debug output. Callers must use explicit accessors and runtime owners must apply redaction policy before any diagnostic emission.

Contracts-level SQL validation is intentionally minimal and structural. The contracts package must not reject valid backend-specific SQL merely because it contains whitespace, comments, dialect-specific clauses, or multiline formatting.

## Binding shape decision

SQL bindings are positional only:

```text
list<DbValue>
```

Associative bindings are rejected in this contracts epic.

Binding order is semantic and must match placeholder order according to the future database runtime owner and concrete driver semantics.

This decision avoids freezing named-placeholder syntax into the contracts boundary.

Different backends, drivers, PDO modes, query compilers, and SQL dialects handle named parameters differently. Some drivers support repeated named placeholders, some do not, and some rewrite placeholders internally.

A positional `list<DbValue>` keeps the contracts surface deterministic and driver-neutral.

Associative binding maps may be introduced later only by an owner that also defines placeholder naming, collision behavior, repeated-name behavior, deterministic ordering, and compiler/driver translation semantics.

## Query result decision

`QueryResultInterface` is intentionally minimal.

Its canonical shape is:

```text
rows(): array
affectedRows(): int
```

`rows()` returns materialized rows as:

```text
list<array<string,int|string|bool|null>>
```

`affectedRows()` returns an integer according to implementation-owned backend semantics.

The result contract does not expose:

- cursors;
- generators;
- iterators;
- streams;
- resources;
- vendor result objects;
- vendor row objects;
- statement handles;
- lazy result handles;
- result metadata objects;
- typed row mappers;
- generated keys;
- column descriptors;
- pagination helpers.

Streaming, cursor-based reads, generated keys, metadata, and typed mapping are real features, but they require additional runtime policy. They are outside this contracts epic.

## Driver port decision

`DatabaseDriverInterface` is the canonical contracts-level database driver port.

Its canonical shape is:

```text
id(): string
connect(string $connectionName, array $config, array $tuning): ConnectionInterface
```

`id()` returns a logical driver id.

The logical driver id must be stable, deterministic, vendor-neutral at the contracts boundary, and match:

```text
^[a-z][a-z0-9_-]*$
```

The logical driver id is not a PHP extension name, PDO driver name, Composer package name, service id, class name, or vendor SDK name.

The contracts package defines only the generic id shape.

The platform-supported driver allowlist is owned by future `platform/database` configuration policy, not by `core/contracts`.

## Driver config and tuning decision

`DatabaseDriverInterface::connect()` receives all driver inputs explicitly:

```text
connectionName
config
tuning
```

The driver must not read global config directly.

The `$config` argument is secrets-allowed driver-owned connection config.

It may contain credentials, tokens, DSNs, passwords, private endpoints, certificate material, and other connection-scoped secrets when required by the concrete driver.

Because `$config` is secrets-allowed, it must not be logged, traced, copied into metric labels, copied into error descriptor extensions, printed by CLI diagnostics, printed by migration tooling, exposed through health output, or emitted through unsafe debug output.

The `$tuning` argument is no-secrets effective driver tuning.

It is expected to be computed by future `platform/database` from driver defaults and per-connection overrides.

The driver treats `$tuning` as final input and must not merge global tuning itself.

PDO options are not exposed through contract method signatures as `PDO::ATTR_*` constants. If a future PDO-backed driver supports PDO options, they remain driver-owned string-keyed config or tuning entries and are mapped internally by that driver.

## Connection identity decision

`ConnectionInterface` exposes both:

```text
name(): string
driverId(): string
```

The connection name identifies the logical connection, such as:

```text
main
analytics
tenant_metadata
```

`name()` must equal the `$connectionName` argument passed into:

```text
DatabaseDriverInterface::connect(...)
```

`driverId()` must equal the producing driver id from:

```text
DatabaseDriverInterface::id()
```

`driverId()` must match:

```text
^[a-z][a-z0-9_-]*$
```

These accessors are required for deterministic multi-connection and multi-driver behavior.

They allow runtime owners, migration runners, diagnostics, and safe observability code to reason about the logical connection and logical driver without exposing DSNs, credentials, vendor objects, PDO driver names, PHP extension names, service ids, or platform-specific connection registry objects.

Connection names and driver ids are still subject to redaction and bounded-cardinality policy before use in diagnostics.

## Connection operation decision

`ConnectionInterface` is the canonical contracts-level database connection port.

Its canonical shape is:

```text
execute(SqlQueryInterface $query): QueryResultInterface
beginTransaction(): void
commit(): void
rollBack(): void
dialect(): SqlDialectInterface
name(): string
driverId(): string
```

`execute()` accepts only `SqlQueryInterface`.

It does not accept raw SQL strings directly.

This keeps SQL and bindings explicit and prevents accidental reliance on `SqlQuery::__toString()`.

The transaction methods are intentionally minimal.

Nested transactions, savepoints, isolation levels, retries, deadlock handling, lock timeouts, automatic rollback policy, and transaction manager orchestration are runtime-owned.

## SQL dialect decision

`SqlDialectInterface` is required in this epic.

A minimal `id(): string` dialect would not be sufficient for realistic migrations and query compiler parity.

The canonical shape is:

```text
id(): string
quoteIdentifier(string $identifier): string
booleanLiteral(bool $value): string
applyLimitOffset(SqlQueryInterface $query, ?int $limit = null, ?int $offset = null): SqlQueryInterface
supportsReturning(): bool
supportsIdentityColumns(): bool
supportsTransactionalDdl(): bool
```

The dialect contract exists because real database integrations differ in ways that migrations and query compilers must be able to handle without exposing vendor platform objects.

Examples include:

- identifier quoting;
- boolean literal syntax;
- limit/offset syntax;
- SQL Server pagination behavior;
- returning-clause support;
- identity/autoincrement behavior;
- transactional DDL support.

`SqlDialectInterface` is intentionally narrow.

It does not turn `core/contracts` into a SQL compiler package.

Full SQL compilation, schema building, expression modeling, query builder behavior, and grammar expansion remain runtime-owned.

## Migration contract decision

`MigrationInterface` is the canonical contracts-level migration boundary.

Its canonical shape is:

```text
up(ConnectionInterface $connection): void
down(ConnectionInterface $connection): void
```

Migrations depend on `ConnectionInterface`, not on a database driver, database platform, connection factory, connection registry, migration runner, PDO object, vendor schema builder, or vendor migration base class.

This preserves the migration contract as a stable application/runtime boundary.

A migration should be executable by any future runtime owner that can provide a `ConnectionInterface`.

The contract intentionally does not include metadata methods such as:

```text
id()
name()
version()
timestamp()
description()
dependencies()
tags()
checksum()
batch()
connectionName()
transactional()
isTransactional()
createdAt()
author()
moduleId()
packageId()
```

Migration identity, naming, versioning, dependency graphs, checksums, registry records, batch behavior, transaction policy, connection selection, and metadata extraction are runtime-owned by future `platform/migrations` or application-level owners.

## Migration SQL execution decision

Migration methods may execute SQL through the received `ConnectionInterface`.

Raw SQL must cross the database contracts boundary through:

```text
SqlQueryInterface
```

or the canonical value object:

```text
SqlQuery
```

Migrations must not require `ConnectionInterface` implementations to accept raw SQL strings directly.

Migration implementations may use dialect information through:

```text
ConnectionInterface::dialect()
```

This supports portable migration decisions without exposing vendor dialect objects, Doctrine platform objects, PDO driver names, extension names, or vendor schema managers.

## Raw SQL redaction decision

Raw SQL is sensitive by default.

Raw SQL may contain:

- literal values;
- table names;
- column names;
- tenant-specific schema names;
- temporary object names;
- comments;
- unsafe fragments;
- user-controlled fragments;
- high-cardinality application data.

Runtime diagnostics, logs, spans, metrics, health output, CLI output, error descriptors, migration output, and unsafe debug output must not expose raw SQL by default.

Raw SQL must not become:

- a metric label;
- a span name fragment;
- a log message;
- an error descriptor extension;
- a CLI output line;
- a health output field;
- an artifact identity.

Safe diagnostics may expose derived information such as:

```text
hash(sql)
len(sql)
hash(bindings)
len(bindings)
operation
driver
connectionName
outcome
```

Safe derivations must not expose raw values or allow reconstruction of sensitive values.

## Runtime ownership decision

`platform/database` and `platform/migrations` are future runtime owners only.

Epic `1.150.0` does not create or modify those packages.

A future `platform/database` package is expected to own:

- driver registry behavior;
- connection registry behavior;
- configuration loading;
- configuration validation;
- driver selection;
- tuning merge behavior;
- connection lifecycle;
- retry policy;
- transaction orchestration beyond minimal connection methods;
- safe database diagnostics;
- database observability integration;
- database error mapping integration.

Future database driver packages are expected to implement `DatabaseDriverInterface` and produce `ConnectionInterface` implementations.

A future `platform/migrations` package is expected to own:

- migration discovery;
- migration ordering;
- migration registry behavior;
- migration execution;
- migration rollback behavior;
- migration CLI commands;
- migration status behavior;
- migration locking;
- migration persistence;
- migration transaction policy;
- migration diagnostics;
- migration error handling.

These runtime packages may depend on `core/contracts`.

`core/contracts` must not depend back on them.

## Config, DI tag, and artifact decision

Epic `1.150.0` introduces no config roots, no DI tags, and no artifacts.

The contracts package must not introduce:

- database config roots;
- migration config roots;
- package config files;
- config defaults;
- config rules;
- database DI tag constants;
- migration DI tag constants;
- package-local mirror constants for database or migration tags;
- database artifacts;
- migration artifacts;
- schema artifacts;
- connection artifacts;
- driver artifacts;
- migration registry artifacts;
- migration plan artifacts;
- migration status artifacts.

Configuration ownership belongs to future runtime owner packages and must go through the config roots registry process.

DI tag ownership belongs to future runtime owner packages and must go through the tag registry process.

Artifact ownership belongs to future runtime owner packages and must go through the artifact registry process.

Contracts may document future runtime policy context, but they must not create registry rows or runtime files in this epic.

## Determinism

Database and migration contracts preserve deterministic behavior at the boundary.

Bindings are positional lists and preserve order.

Rows preserve database result order as produced by implementations.

Any future json-like database or migration metadata must remain float-free, preserve list order, and sort maps by byte-order `strcmp`.

Future migration owners must define deterministic policy for:

- migration discovery sources;
- migration identity;
- migration ordering;
- duplicate detection;
- registry records;
- status output;
- plan output;
- rollback selection;
- batch semantics;
- checksum semantics, if introduced;
- generated migration artifacts, if introduced.

Future migration ordering must not depend on:

- filesystem traversal order;
- Composer package declaration order;
- PHP hash-map insertion side effects;
- process locale;
- host platform;
- timestamps;
- random values.

## Security and redaction

Database and migration diagnostics must follow the global redaction law.

They must not expose:

- `.env` values;
- passwords;
- credentials;
- tokens;
- private keys;
- cookies;
- authorization headers;
- session identifiers;
- raw SQL;
- raw SQL bindings;
- raw query result values;
- raw driver config;
- raw DSNs;
- database passwords;
- private customer data;
- vendor object dumps;
- request bodies;
- response bodies;
- raw queue messages;
- raw worker payloads;
- profile payloads;
- absolute local paths.

Runtime owners must prefer omission over unsafe emission.

Metric labels must remain within the canonical allowlist from:

```text
docs/ssot/observability.md
```

Epic `1.150.0` introduces no new metric label keys.

The existing `driver`, `operation`, `table`, and `outcome` label keys may be used only with safe, bounded-cardinality, owner-approved values.

Raw SQL, SQL bindings, connection names, DSNs, database names, usernames, host names, schema names, tenant ids, user ids, request ids, and correlation ids must not become metric labels under the baseline policy.

## Consequences

Positive consequences:

- `core/contracts` remains vendor-neutral and dependency-light.
- Runtime database packages can evolve independently from contracts.
- Driver packages can implement stable ports without exposing vendor handles.
- Migration code depends on a stable connection boundary instead of runtime platform classes.
- SQL and bindings remain explicit.
- Query results remain scalar-only and float-free.
- Database diagnostics and migration tooling remain compatible with the global redaction law.
- Dialect differences can be represented without introducing a full SQL compiler into contracts.
- Future platform owners retain ownership of configuration, DI tags, artifacts, discovery, registries, and execution policy.

Trade-offs:

- Contracts do not provide a concrete database implementation.
- Contracts do not provide query builder or schema builder APIs.
- Contracts do not expose streaming/cursor result APIs.
- Contracts do not expose generated keys or result metadata.
- Contracts do not define migration discovery or ordering.
- Contracts do not define migration metadata methods.
- Runtime owners must implement driver registries, connection registries, compiler behavior, migration runners, error mapping, configuration, and diagnostics later.

## Rejected alternatives

### Expose PDO in database contracts

Rejected.

PDO is an implementation choice.

Exposing `PDO`, `PDOStatement`, `PDOException`, or `PDO::ATTR_*` constants would make `core/contracts` depend on a concrete PHP database abstraction and would prevent non-PDO drivers, in-memory drivers, cloud database SDK drivers, and custom drivers from implementing the same contracts cleanly.

### Expose vendor result objects

Rejected.

Vendor result objects leak backend-specific execution models into the contracts boundary.

They may be cursor-based, statement-based, stream-based, lazy, resource-backed, metadata-heavy, or tied to vendor lifecycle rules.

The contracts boundary uses materialized scalar-only rows and an integer affected-row count.

### Expose DateTimeInterface for database date/time values

Rejected.

Database date/time behavior varies by precision, timezone, storage type, driver hydration policy, and backend semantics.

`DateTimeInterface` would force an object model and timezone interpretation into the contracts boundary.

Date/time values cross as strings unless a future owner introduces dedicated format-neutral contracts.

### Make SqlQuery stringable

Rejected.

`__toString()` makes raw SQL leakage too easy.

It encourages accidental logging, tracing, exception-message interpolation, metric label misuse, and CLI output leakage.

`SqlQuery` is accessed explicitly through `sql()` and `bindings()`, and runtime owners must apply redaction before diagnostics.

### Validate SQL grammar in SqlQuery

Rejected.

SQL grammar is dialect-specific and runtime-owned.

Contracts must not reject valid backend-specific SQL or require a parser/compiler implementation.

`SqlQuery` is an opaque-ish carrier, not a SQL AST, parser, compiler, or query builder.

### Use associative SQL bindings

Rejected.

Associative bindings freeze named-placeholder semantics too early.

Placeholder naming, repeated placeholders, collision behavior, and driver rewriting differ across implementations.

Positional `list<DbValue>` bindings are deterministic, order-preserving, and driver-neutral.

### Allow float in DbValue

Rejected.

Floats introduce precision loss, `NaN`, `INF`, `-INF`, serialization drift, platform variation, and locale-sensitive formatting risks.

Decimals and database floating-point values cross the contracts boundary as strings.

### Log raw SQL for debugging convenience

Rejected.

Raw SQL is sensitive and high-cardinality by default.

It may contain literals, identifiers, tenant-specific schema names, comments, unsafe fragments, or user-controlled data.

Diagnostics must use safe derivations such as hashes and lengths.

### Make SqlDialectInterface only id()

Rejected.

A dialect id alone is not enough for real migrations and query compiler parity.

Identifier quoting, boolean literals, limit/offset syntax, returning support, identity support, and transactional DDL support are common portability boundaries.

The dialect contract remains narrow but useful.

### Make migrations depend on database drivers or platform database objects

Rejected.

Migration contracts must remain runtime-neutral.

Depending on drivers, connection factories, registries, platform database managers, PDO objects, or vendor schema builders would couple application migrations to runtime implementation details.

`ConnectionInterface` is the only database-specific object accepted by `MigrationInterface`.

### Add migration metadata methods in this epic

Rejected.

Migration metadata requires runtime policy for identity, naming, ordering, dependency graphs, checksums, registry records, transaction policy, and connection selection.

Those concerns belong to future `platform/migrations` or application-level owners.

### Introduce database or migration config roots in contracts

Rejected.

Configuration is runtime/platform policy.

The contracts package introduces no config roots, defaults files, rules files, or package config files.

Future runtime owners may introduce config only through their own owner epics and the config roots registry process.

### Introduce database or migration DI tags in contracts

Rejected.

DI tag ownership is governed by `docs/ssot/tags.md`.

This epic does not need database or migration discovery tags in `core/contracts`.

Future runtime owners may introduce tags through their own owner epics.

### Introduce database or migration artifacts in contracts

Rejected.

Generated artifacts require owner-defined schema semantics, source discovery, deterministic serialization, runtime integration, and registry ownership.

Those responsibilities belong to future runtime owner packages, not `core/contracts`.

## What this epic must not create

Epic `1.150.0` must not create:

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

## Non-goals

This ADR does not implement:

- concrete database implementation;
- concrete database driver implementation;
- PDO adapter;
- PDO wrapper;
- local database adapter;
- cloud database adapter;
- in-memory database adapter;
- connection registry;
- connection pool;
- database manager;
- transaction manager;
- savepoint manager;
- query builder;
- schema builder;
- SQL compiler implementation;
- SQL parser;
- migration runner;
- migration discovery;
- migration CLI;
- migration ordering;
- migration persistence table;
- migration lock behavior;
- migration metadata methods;
- database health checks;
- database exception hierarchy;
- database exception mapper;
- migration exception mapper;
- database config roots;
- migration config roots;
- package config files;
- DI tags;
- DI registration;
- generated database artifacts;
- generated migration artifacts;
- vendor driver implementation;
- `platform/database` implementation;
- `platform/migrations` implementation;
- `platform/database-driver-*` implementation;
- `integrations/*` driver implementation.

## Related SSoT

- `docs/ssot/database-contracts.md`
- `docs/ssot/migrations-contracts.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/error-descriptor.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/tags.md`
- `docs/ssot/artifacts.md`
- `docs/ssot/dto-policy.md`

## Related epic

- `1.150.0 Contracts: Database + Migrations ports`
