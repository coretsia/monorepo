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

# Database Contracts SSoT

## Scope

This document is the Single Source of Truth for Coretsia database contracts, SQL query value semantics, database result value semantics, driver and connection ports, SQL dialect boundaries, deterministic database policy, dependency restrictions, and database redaction rules.

This document governs contracts introduced by epic `1.150.0` under:

```text
framework/packages/core/contracts/src/Database/
```

The canonical database contracts introduced by this epic are:

```text
Coretsia\Contracts\Database\SqlQueryInterface
Coretsia\Contracts\Database\SqlQuery
Coretsia\Contracts\Database\DatabaseDriverInterface
Coretsia\Contracts\Database\ConnectionInterface
Coretsia\Contracts\Database\QueryResultInterface
Coretsia\Contracts\Database\SqlDialectInterface
```

The implementation paths are:

```text
framework/packages/core/contracts/src/Database/SqlQueryInterface.php
framework/packages/core/contracts/src/Database/SqlQuery.php
framework/packages/core/contracts/src/Database/DatabaseDriverInterface.php
framework/packages/core/contracts/src/Database/ConnectionInterface.php
framework/packages/core/contracts/src/Database/QueryResultInterface.php
framework/packages/core/contracts/src/Database/SqlDialectInterface.php
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Database access must be expressed through stable contracts-level ports so future `platform/database`, future `platform/migrations`, and driver packages can interoperate without leaking vendor-specific database APIs into `core/contracts`.

The contracts introduced by this epic define only:

- driver-agnostic database ports;
- a safe SQL query carrier;
- a scalar-only database value domain;
- connection identity semantics;
- query result shape semantics;
- dialect boundary policy;
- redaction and observability constraints for database diagnostics;
- dependency and ownership boundaries for future runtime packages.

The contracts package MUST NOT implement database access, connection pooling, connection registries, transaction orchestration, SQL compilation, schema building, query builders, migration runners, migration discovery, migration CLI commands, database configuration loading, driver configuration validation, exception mapping, observability emitters, DI registration, config defaults, config rules, generated artifacts, PDO wrappers, or vendor database adapters.

## Phase 0 lock-source alignment

This SSoT preserves the following Phase 0 invariants:

- `0.20.0` no-secrets output policy applies to database diagnostics, migration diagnostics, SQL diagnostics, and driver diagnostics.
- `0.60.0` missing vs empty MUST remain distinguishable where database values, configuration values, or driver inputs need presence-sensitive behavior.
- `0.70.0` json-like payloads forbid floats, including `NaN`, `INF`, and `-INF`.
- `0.70.0` lists preserve order and maps use deterministic key ordering when json-like metadata is introduced by future owners.
- `0.90.0` safe diagnostics MUST NOT expose raw values and MAY expose only safe derivations such as `hash(value)` or `len(value)`.

Epic `1.150.0` itself introduces no database implementation, no migration implementation, no driver implementation, no config root, no generated artifact, and no DI tag.

## Contract boundary

Database contracts are format-neutral and vendor-neutral.

They define stable ports, value semantics, and boundary policy only.

The contracts package MUST NOT implement:

- concrete database connections;
- PDO connection wrappers;
- PDO statement wrappers;
- connection factories;
- connection pools;
- connection registries;
- database managers;
- query builders;
- SQL compilers;
- schema builders;
- migration runners;
- migration discovery;
- migration ordering;
- migration persistence tables;
- migration CLI commands;
- database health checks;
- database exception mapping;
- retry policies;
- transaction managers;
- savepoint managers;
- driver selection;
- driver configuration loading;
- driver tuning merge execution;
- driver package discovery;
- DI registration;
- runtime discovery;
- config defaults;
- config rules;
- generated database artifacts;
- generated migration artifacts.

Runtime owner packages implement concrete database behavior later.

## Contract package dependency policy

Database contracts MUST remain dependency-free beyond PHP itself.

They MUST NOT depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- `Psr\Http\Message\StreamInterface`
- `PDO`
- `PDOStatement`
- `PDOException`
- `PDOStatement::*`
- `mysqli`
- `SQLite3`
- `PgSql\*`
- `Redis`
- `DateTimeInterface`
- `DateTimeImmutable`
- `DateTime`
- Doctrine DBAL classes
- Eloquent classes
- Cycle ORM classes
- Laminas DB classes
- vendor query builders
- vendor schema builders
- vendor migration classes
- vendor database clients
- cloud database SDK clients
- concrete service container implementations
- concrete middleware implementations
- concrete logger implementations
- concrete tracing implementations
- concrete metrics implementations
- framework tooling packages
- generated architecture artifacts

Runtime packages MAY depend on `core/contracts`.

`core/contracts` MUST NOT depend back on runtime packages.

Allowed direction:

```text
platform/database → core/contracts
platform/migrations → core/contracts
platform/database-driver-* → core/contracts
integrations/* database drivers → core/contracts
```

Forbidden direction:

```text
core/contracts → platform/database
core/contracts → platform/migrations
core/contracts → platform/database-driver-*
core/contracts → integrations/*
```

## DTO terminology boundary

This document uses the terms `contract`, `port`, `value object`, `result`, `shape`, and `runtime boundary` according to:

```text
docs/ssot/dto-policy.md
```

`SqlQuery` is an immutable contracts value object.

It is not a DTO-marker class by default.

Database interfaces introduced by epic `1.150.0` are contracts interfaces.

They are not DTO-marker classes.

DTO gates apply only to classes explicitly marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Database contracts introduced by epic `1.150.0` MUST NOT be treated as DTOs unless a future owner epic explicitly opts them into DTO policy.

## Database value domain

Database contracts use a scalar-only database value domain.

The canonical database value pseudo-type is:

```text
DbValue = int|string|bool|null
```

`DbValue` MUST NOT include `float`.

The canonical database row pseudo-type is:

```text
DbRow = array<string, DbValue>
```

The canonical database rows pseudo-type is:

```text
DbRows = list<DbRow>
```

The value-domain mapping is:

| database value category      | contracts representation |
|------------------------------|--------------------------|
| integer safely representable | `int`                    |
| string/text                  | `string`                 |
| boolean                      | `bool`                   |
| SQL NULL                     | `null`                   |
| decimal/numeric precision    | `string`                 |
| floating-point values        | `string`                 |
| bigint not safely bounded    | `string`                 |
| date/time values             | `string`                 |
| binary values                | `string`                 |
| JSON values                  | `string`                 |

Database contracts never expose `float`.

Decimals, database floating-point values, and big integers that are not safely representable as PHP integers MUST be represented as strings at the contracts boundary.

If a future owner needs typed decimal, timestamp, binary, JSON, or identifier models, that owner MUST introduce dedicated format-neutral contracts through SSoT and ADR updates.

## SQL sensitivity and redaction

Raw SQL is sensitive by default.

Raw SQL MAY contain:

- literal values;
- table names;
- column names;
- tenant-specific schema names;
- temporary object names;
- comments;
- unsafe fragments;
- user-controlled fragments;
- high-cardinality application data.

Runtime diagnostics, logs, spans, metrics, health output, CLI output, error descriptors, worker failure output, migration output, and unsafe debug output MUST NOT expose raw SQL by default.

Raw SQL MUST NOT become a metric label.

Raw SQL MUST NOT be used as a span name fragment.

Raw SQL MUST NOT be copied into error descriptor extensions by default.

Safe diagnostics MAY expose only derived information such as:

```text
hash(sql)
len(sql)
operation
driver
table
outcome
```

Safe derivations MUST NOT expose raw SQL or allow reconstruction of sensitive SQL.

The label key `table` is allowed by the baseline observability policy, but table label values MUST be safe, bounded-cardinality, and owner-approved.

Dynamic table names, tenant table names, raw identifiers, and arbitrary SQL fragments MUST NOT be emitted as metric labels.

Runtime owners MUST prefer omission over unsafe emission.

## SQL query contract

`SqlQueryInterface` is the canonical contracts-level SQL query carrier.

The implementation path is:

```text
framework/packages/core/contracts/src/Database/SqlQueryInterface.php
```

The canonical interface shape is:

```text
sql(): string
bindings(): array
```

The PHPDoc return shape for `bindings()` MUST be:

```php
@return list<int|string|bool|null>
```

`sql()` returns the raw SQL string carried by the query.

`bindings()` returns positional query bindings.

Binding order is semantic and MUST be preserved.

Bindings MUST match placeholder order according to the runtime database owner and driver semantics.

Associative bindings are forbidden in this contracts epic.

The root bindings array MUST be a list.

Valid bindings examples:

```php
[]
[1, 'email@example.com', true, null]
['12345678901234567890', '19.95']
```

Invalid bindings examples:

```php
['id' => 1]
[1.25]
[new stdClass()]
[['nested']]
```

A database binding MUST be a `DbValue`.

Bindings MUST NOT contain:

- floats;
- arrays;
- objects;
- closures;
- resources;
- streams;
- file handles;
- service instances;
- runtime wiring objects;
- vendor database values;
- request objects;
- response objects;
- PSR-7 objects.

`SqlQueryInterface` MUST NOT expose:

- `__toString()`;
- statement objects;
- prepared statement handles;
- parameter objects;
- vendor expression objects;
- query builder objects;
- schema builder objects;
- platform query objects;
- integration query objects.

## SQL query value object

`SqlQuery` is the canonical immutable contracts value object implementing `SqlQueryInterface`.

The implementation path is:

```text
framework/packages/core/contracts/src/Database/SqlQuery.php
```

`SqlQuery` MUST be immutable.

`SqlQuery` MUST be final.

`SqlQuery` MUST be readonly.

`SqlQuery` MUST be outside DTO marker policy by default.

`SqlQuery` exists to pass SQL and positional bindings through database and migration contracts without relying on unsafe stringification.

`SqlQuery` MUST reject an empty SQL string.

`SqlQuery` MUST reject a whitespace-only SQL string.

`SqlQuery` MUST accept any non-empty SQL string, including multiline SQL.

`SqlQuery` MUST throw `InvalidArgumentException` for structural validation failures.

Validation exception messages MUST be stable and MUST NOT include:

- raw SQL;
- raw binding values;
- rejected object dumps;
- resource identifiers;
- file paths;
- secrets;
- DSNs.

The canonical constructor shape is:

```text
__construct(string $sql, array $bindings = [])
```

The canonical accessor shape is:

```text
sql(): string
bindings(): array
```

The PHPDoc shape for constructor bindings MUST be:

```php
@param list<int|string|bool|null> $bindings
```

The PHPDoc shape for `bindings()` MUST be:

```php
@return list<int|string|bool|null>
```

`SqlQuery` MUST reject non-list bindings.

`SqlQuery` MUST reject float bindings.

`SqlQuery` MUST reject nested arrays, objects, closures, resources, streams, file handles, service instances, vendor objects, and runtime wiring objects in bindings.

`SqlQuery` MUST NOT implement `__toString()`.

`SqlQuery` MUST treat the SQL string as opaque application data.

The contracts package MUST NOT normalize SQL syntax.

The contracts package MUST NOT strip comments.

The contracts package MUST NOT parse SQL.

The contracts package MUST NOT infer query operation from SQL text.

The contracts package MUST NOT validate SQL grammar.

SQL grammar, placeholder style, identifier quoting, and driver-specific SQL validity are runtime-owned.

## Database driver interface

`DatabaseDriverInterface` is the canonical contracts-level database driver port.

The implementation path is:

```text
framework/packages/core/contracts/src/Database/DatabaseDriverInterface.php
```

The canonical interface shape is:

```text
id(): string
connect(string $connectionName, array $config, array $tuning): ConnectionInterface
```

`id()` returns a logical driver id.

The logical driver id MUST be:

- non-empty;
- stable across runs;
- deterministic;
- vendor-neutral at the contracts boundary;
- lowercase ASCII;
- matched by this regex:

```text
^[a-z][a-z0-9_-]*$
```

The logical driver id is not the same thing as a PHP extension name, PDO driver name, Composer package name, service id, class name, or vendor SDK name.

Examples of valid logical driver-id shape:

```text
mysql
pgsql
sqlite
mariadb
sql-server
test-memory
```

Examples of invalid driver ids:

```text
PDO
PdoMysql
pdo_mysql
sqlsrv\driver
vendor/package
Coretsia\Platform\Database
```

The canonical allowlist for platform-supported driver ids is owned by future `platform/database` configuration SSoT, not by `core/contracts`.

Contracts lock only the generic logical driver-id shape and regex.

Contracts MUST NOT enumerate the platform-supported driver set.

### Driver connection input

`connect()` creates or opens a connection according to driver-owned behavior.

The `$connectionName` argument is the logical connection name.

The connection name MUST be:

- non-empty;
- stable;
- deterministic;
- safe for diagnostics only when treated as owner-approved metadata;
- not a DSN;
- not a filesystem path;
- not a raw secret;
- not a vendor connection object.

Recommended connection-name shape:

```text
^[a-z][a-z0-9_-]*$
```

Examples:

```text
main
analytics
tenant_metadata
```

`connect()` receives all driver inputs explicitly.

A database driver MUST treat `connect()` as a pure function of:

```text
connectionName
config
tuning
```

A database driver MUST NOT read global config directly.

A database driver MUST NOT read package config files directly.

A database driver MUST NOT read environment variables directly when those values should have been resolved into `$config`.

A database driver MUST NOT depend on platform configuration repository types.

A database driver MUST NOT require `platform/database` classes in its public contract surface.

### Driver config input

The `$config` argument is secrets-allowed driver-owned connection config.

The semantic source for `$config` is expected to be:

```text
database.connections.<name>.config
```

This source path is future `platform/database` policy, not a config root introduced by `core/contracts`.

`$config` MAY contain secrets such as credentials, tokens, DSNs, passwords, private endpoints, or certificate material when those values are required by the driver.

Because `$config` is secrets-allowed, it MUST NOT be logged, traced, exported as metric labels, copied into error descriptor extensions, printed by migration tooling, printed by CLI diagnostics, exposed through health output, or copied into unsafe debug output.

Driver implementations MUST prefer omission over unsafe emission.

Safe diagnostics MAY expose only derived config information such as:

```text
hash(config)
len(config)
driver
connectionName
operation
outcome
```

Safe derivations MUST NOT expose raw config values or allow secret reconstruction.

`$config` MUST NOT contain:

- objects;
- closures;
- resources;
- streams;
- file handles;
- service instances;
- container references;
- vendor connection objects;
- vendor client objects;
- runtime wiring objects.

PDO canonical options are not passed through the contract surface as PDO attributes.

If a future `platform/database` owner supports PDO options, they remain driver-owned string-keyed config or tuning entries such as:

```text
config.pdo_options
```

The driver MAY map those owner-approved string-keyed entries to vendor internals.

The contracts package MUST NOT expose `PDO::ATTR_*` constants in method signatures.

### Driver tuning input

The `$tuning` argument is no-secrets effective driver tuning.

The semantic source for `$tuning` is expected to be computed by future `platform/database` as the merge of:

```text
database.drivers.<driverId>.tuning
database.connections.<name>.tuning
```

This source path is future `platform/database` policy, not a config root introduced by `core/contracts`.

`$tuning` is final input to the driver.

The driver MUST NOT merge global tuning by itself.

The driver MUST NOT read global config directly.

The driver MUST NOT reinterpret `$tuning` as secrets-allowed config.

`$tuning` MUST NOT contain secrets.

`$tuning` MUST NOT contain passwords, credentials, tokens, private keys, DSNs, raw SQL, request bodies, response bodies, private customer data, or absolute local paths.

`$tuning` MUST NOT contain:

- floats;
- objects;
- closures;
- resources;
- streams;
- file handles;
- service instances;
- container references;
- vendor connection objects;
- vendor client objects;
- runtime wiring objects.

If decimal tuning values are needed, they MUST be represented as strings with an owner-documented format.

If duration or size tuning values are needed, they SHOULD be represented as integers with documented units.

## Connection interface

`ConnectionInterface` is the canonical contracts-level database connection port.

The implementation path is:

```text
framework/packages/core/contracts/src/Database/ConnectionInterface.php
```

The canonical interface shape is:

```text
execute(SqlQueryInterface $query): QueryResultInterface
beginTransaction(): void
commit(): void
rollBack(): void
dialect(): SqlDialectInterface
name(): string
driverId(): string
```

`execute()` executes a `SqlQueryInterface` and returns a `QueryResultInterface`.

`execute()` MUST NOT accept raw SQL strings directly in this contracts epic.

A raw SQL string MUST be wrapped in `SqlQueryInterface`.

This prevents accidental reliance on `__toString()` and keeps bindings explicit.

`beginTransaction()` begins a transaction according to driver-owned semantics.

`commit()` commits the current transaction according to driver-owned semantics.

`rollBack()` rolls back the current transaction according to driver-owned semantics.

Nested transactions, savepoints, retry policy, isolation level, transaction timeouts, deadlock handling, and transaction manager behavior are outside this contracts epic.

`dialect()` returns the SQL dialect port associated with this connection.

`name()` returns the logical connection name.

`name()` MUST be non-empty.

`name()` MUST equal the `$connectionName` argument passed into:

```text
DatabaseDriverInterface::connect(...)
```

`driverId()` returns the logical driver id.

`driverId()` MUST be non-empty.

`driverId()` MUST equal `DatabaseDriverInterface::id()` of the driver instance that produced this connection.

`driverId()` MUST match:

```text
^[a-z][a-z0-9_-]*$
```

The platform-supported driver allowlist is enforced by future `platform/database` config rules, not by `core/contracts`.

For multi-connection runtime behavior, `DatabaseDriverInterface::connect(...)` already receives `$connectionName`.

A concrete driver is expected to store the connection name and logical driver id in the produced connection object.

This is runtime policy only.

`ConnectionInterface` MUST NOT expose:

- `PDO`;
- `PDOStatement`;
- `PDOException`;
- `mysqli`;
- `SQLite3`;
- vendor connection objects;
- vendor statement objects;
- vendor result objects;
- transaction objects;
- savepoint objects;
- connection registry objects;
- configuration repository objects;
- platform database objects;
- integration database objects.

## Query result interface

`QueryResultInterface` is the canonical contracts-level query result port.

The implementation path is:

```text
framework/packages/core/contracts/src/Database/QueryResultInterface.php
```

The canonical interface shape is:

```text
rows(): array
affectedRows(): int
```

The PHPDoc return shape for `rows()` MUST be:

```php
@return list<array<string,int|string|bool|null>>
```

`rows()` returns materialized database rows.

Rows MUST use the canonical database row shape:

```text
DbRow = array<string, DbValue>
```

Multiple rows MUST use the canonical database rows shape:

```text
DbRows = list<DbRow>
```

Row order MUST preserve database result order as produced by the implementation.

Row maps SHOULD use deterministic string-key ordering when rows are constructed by owner code.

No row value may be a float.

No row value may be an object, resource, stream, closure, array, vendor value object, date object, or runtime wiring object.

`affectedRows()` returns the number of rows affected according to implementation-owned backend semantics.

`affectedRows()` MUST be an integer.

If a driver cannot determine the number of affected rows, it SHOULD return `0` unless a future owner SSoT defines a more precise policy.

`QueryResultInterface` intentionally does not expose:

- cursors;
- generators;
- iterators;
- streams;
- resources;
- file handles;
- vendor result objects;
- vendor row objects;
- PDO statements;
- lazy result handles;
- result metadata objects.

Streaming, cursor-based reads, pagination helpers, metadata, generated keys, column descriptors, and typed row mapping are outside this contracts epic.

Future owner epics MAY introduce safe result extensions through SSoT and ADR updates.

## SQL dialect interface

`SqlDialectInterface` is the canonical contracts-level SQL dialect boundary.

The implementation path is:

```text
framework/packages/core/contracts/src/Database/SqlDialectInterface.php
```

The dialect contract exists so future database and migration owners can handle driver-specific SQL differences without exposing vendor database objects.

The canonical interface shape is:

```text
id(): string
quoteIdentifier(string $identifier): string
booleanLiteral(bool $value): string
applyLimitOffset(SqlQueryInterface $query, ?int $limit = null, ?int $offset = null): SqlQueryInterface
supportsReturning(): bool
supportsIdentityColumns(): bool
supportsTransactionalDdl(): bool
```

`id()` returns the logical dialect id.

The dialect id MUST be:

- non-empty;
- stable across runs;
- deterministic;
- lowercase ASCII;
- matched by this regex:

```text
^[a-z][a-z0-9_-]*$
```

The dialect id SHOULD usually equal or be compatible with the logical driver id when one driver maps to one SQL dialect.

The dialect id MUST NOT be a PHP extension name, class name, vendor object name, or platform service id.

`quoteIdentifier()` quotes one SQL identifier according to dialect-owned rules.

The input identifier MUST be treated as raw SQL-adjacent data.

Implementations MUST NOT log the raw identifier by default.

`quoteIdentifier()` MUST NOT accept compound SQL fragments as a generic SQL escaping mechanism.

Schema-qualified names, table-qualified names, wildcard expressions, expressions, aliases, and raw SQL fragments are compiler-owned concerns.

`booleanLiteral()` returns the dialect-specific SQL literal for a boolean value.

The returned string is SQL text and MUST be treated as raw SQL-adjacent data.

`applyLimitOffset()` applies dialect-specific limit/offset syntax to an existing `SqlQueryInterface`.

This method is intentionally narrow.

It exists because limit/offset syntax differs across dialects and is required by real database integrations.

`applyLimitOffset()` MUST preserve existing binding order.

If the dialect needs additional bindings for limit or offset, those bindings MUST be appended in placeholder order.

`$limit` and `$offset` are optional non-negative integers.

When both `$limit` and `$offset` are `null`, `applyLimitOffset()` SHOULD return an equivalent query.

A dialect implementation MAY reject unsupported combinations through owner-defined exceptions.

Those exceptions MUST NOT expose raw SQL by default.

`supportsReturning()` returns whether the dialect supports a native SQL returning clause for relevant write operations according to owner-defined semantics.

`supportsIdentityColumns()` returns whether the dialect supports identity or auto-generated column behavior according to owner-defined schema/migration semantics.

`supportsTransactionalDdl()` returns whether DDL statements can normally be executed transactionally according to owner-defined dialect semantics.

`SqlDialectInterface` MUST NOT expose:

- vendor dialect objects;
- query builder objects;
- schema builder objects;
- PDO objects;
- database platform objects;
- Doctrine platform objects;
- grammar objects from vendor frameworks;
- closures;
- resources;
- streams;
- service instances;
- runtime wiring objects.

Full SQL compilation remains runtime-owned.

The dialect contract does not turn `core/contracts` into a SQL compiler package.

## Identifier and SQL fragment safety

SQL identifiers and SQL fragments are sensitive by default.

Even when an identifier looks safe, it may reveal tenant names, table names, schema names, feature names, customer names, or internal persistence layout.

Runtime owners MUST NOT log raw identifiers or SQL fragments by default.

Safe diagnostics MAY expose only:

```text
hash(identifier)
len(identifier)
hash(sql)
len(sql)
operation
driver
outcome
```

When the `table` metric label is used, its value MUST be explicitly owner-approved, stable, low-cardinality, and non-sensitive.

Dynamic table names, tenant-specific table names, customer-specific schema names, and arbitrary identifiers MUST NOT become metric labels.

## Transaction semantics

`ConnectionInterface` exposes only minimal transaction primitives:

```text
beginTransaction()
commit()
rollBack()
```

This contracts epic does not define:

- nested transaction behavior;
- savepoint behavior;
- transaction isolation;
- read-only transactions;
- retry policy;
- deadlock handling;
- lock timeout handling;
- automatic rollback on exception;
- transaction context objects;
- transaction callbacks;
- transaction manager orchestration.

Runtime owners MAY implement those behaviors outside `core/contracts`.

Any transaction diagnostics MUST preserve the global redaction law and MUST NOT expose raw SQL, bindings, credentials, tokens, private customer data, or vendor object dumps.

## Driver configuration ownership

Epic `1.150.0` introduces no config roots and no config keys.

The contracts package MUST NOT require package config files for database contracts.

No files under package `config/` are introduced by this epic.

The future `platform/database` owner is expected to own database configuration policy.

Possible future runtime config paths may include:

```text
database.connections.<name>.driver
database.connections.<name>.config
database.connections.<name>.tuning
database.drivers.<driverId>.tuning
```

These paths are documented here only as future runtime policy context.

They are not config roots or config keys introduced by `core/contracts`.

Database DSNs, credentials, host names, ports, database names, driver options, SSL material, pool settings, retry settings, timeouts, and PDO options are runtime configuration concerns.

Future runtime owner packages MAY introduce database config roots or config keys only through their own owner epics and the config roots registry process.

## Driver id ownership

Contracts define the generic logical driver-id shape.

Contracts do not define the list of supported driver ids.

A future `platform/database` owner MUST own platform-supported driver allowlists, aliases, default driver selection, and config validation.

Driver packages are expected to expose a stable logical id through:

```text
DatabaseDriverInterface::id()
```

The connection produced by the driver is expected to expose the same logical id through:

```text
ConnectionInterface::driverId()
```

This supports deterministic multi-driver and multi-connection behavior without requiring `core/contracts` to know about specific drivers.

## Runtime usage policy

`DatabaseDriverInterface`, `ConnectionInterface`, `QueryResultInterface`, `SqlQueryInterface`, `SqlQuery`, and `SqlDialectInterface` are the contracts boundary for future database-aware runtime packages.

A future `platform/database` package is expected to own:

- driver registry behavior;
- connection registry behavior;
- configuration loading;
- configuration validation;
- driver selection;
- tuning merge behavior;
- connection lifecycle;
- retry policy;
- transaction orchestration beyond the minimal connection methods;
- safe database diagnostics;
- database observability integration;
- database error mapping integration.

Future driver packages such as:

```text
platform/database-driver-*
integrations/* database drivers
```

are expected to implement `DatabaseDriverInterface` and produce `ConnectionInterface` implementations.

Future `platform/migrations` is expected to execute migrations using `ConnectionInterface` and `SqlQueryInterface`.

This is documented runtime policy only.

Epic `1.150.0` does not create or modify those runtime packages.

## Migrations boundary

Database contracts support future migrations through:

```text
ConnectionInterface
SqlQueryInterface
SqlQuery
SqlDialectInterface
```

Migration execution is not defined by this document.

The migration contract and migration-specific determinism policy are owned by the migrations SSoT introduced by the same epic.

Database contracts MUST NOT implement:

- migration discovery;
- migration sorting;
- migration graph validation;
- migration runner behavior;
- migration persistence table behavior;
- migration CLI behavior;
- migration file scanning;
- migration artifact generation.

Migrations tooling MUST NOT log raw SQL.

Migrations tooling MUST NOT print raw bindings.

Migrations tooling MUST NOT expose credentials, DSNs, tokens, private customer data, or vendor diagnostics through unsafe output.

## DI tag policy

Epic `1.150.0` introduces no DI tags.

The contracts package MUST NOT declare public database tag constants.

The contracts package MUST NOT declare public migration tag constants.

The contracts package MUST NOT define package-local mirror constants for database or migration tags.

The contracts package MUST NOT define database tag metadata keys, migration tag metadata keys, tag priority semantics, driver discovery semantics, or migration discovery semantics.

If a future runtime owner needs database or migration DI tags, that owner MUST introduce them through:

```text
docs/ssot/tags.md
```

according to tag registry rules.

## Config policy

Epic `1.150.0` introduces no config roots and no config keys.

The contracts package MUST NOT require package config files for database contracts.

The contracts package MUST NOT add package config defaults or config rules for database contracts.

Future runtime owner packages MAY introduce database config only through their own owner epics and the config roots registry process.

## Artifact policy

Epic `1.150.0` introduces no artifacts.

The contracts package MUST NOT generate:

- database artifacts;
- migration artifacts;
- schema artifacts;
- query artifacts;
- driver artifacts;
- connection artifacts;
- runtime lifecycle artifacts.

A future runtime owner MAY introduce generated database or migration artifacts only through its own owner epic and the artifact registry process.

## Observability and diagnostics policy

Database contracts do not define observability signals.

Runtime implementations MAY emit logs, spans, metrics, or profiling signals around database operations only when those signals follow:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
```

Diagnostics MUST NOT expose:

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

Safe implementation diagnostics MAY use:

```text
hash(sql)
len(sql)
hash(bindings)
len(bindings)
operation
outcome
driver
table
```

Metric labels MUST remain within the canonical allowlist from:

```text
docs/ssot/observability.md
```

Epic `1.150.0` introduces no new metric label keys.

Raw SQL, SQL bindings, connection names, DSNs, database names, usernames, host names, schema names, tenant ids, user ids, request ids, and correlation ids MUST NOT become metric labels under the baseline policy.

The baseline label key `driver` MAY be used with safe logical driver ids.

The baseline label key `operation` MAY be used with safe bounded operation names.

The baseline label key `outcome` MAY be used with safe bounded outcome names.

The baseline label key `table` MAY be used only with owner-approved safe table labels.

## Error and exception policy

Epic `1.150.0` does not introduce a database exception hierarchy.

Database failure handling is runtime-owned.

Concrete implementations MAY throw owner-defined exceptions.

Owner-defined exceptions and diagnostics MUST NOT expose:

- raw SQL;
- raw bindings;
- credentials;
- tokens;
- private keys;
- raw DSNs;
- database passwords;
- private customer data;
- vendor object dumps;
- absolute local paths;
- stack traces by default.

If database errors are normalized later, mapping to `ErrorDescriptor` is owned by runtime error mapping packages.

The contracts package MUST NOT implement database exception mapping.

The contracts package MUST NOT require `ErrorDescriptor` construction inside database contracts.

## Contract surface restrictions

Database public method signatures MUST NOT contain:

- `Psr\Http\Message\*`;
- `Psr\Http\Message\StreamInterface`;
- `Coretsia\Platform\*`;
- `Coretsia\Integrations\*`;
- `PDO`;
- `PDOStatement`;
- `PDOException`;
- `mysqli`;
- `SQLite3`;
- `DateTimeInterface`;
- `DateTimeImmutable`;
- `DateTime`;
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

Database public method signatures MUST use only:

- PHP built-in scalar/array/null/void types;
- contracts-level database types introduced by this epic;
- other explicitly allowed `core/contracts` types from the same database boundary.

The only database-specific object types allowed in public method signatures introduced by this SSoT are:

```text
Coretsia\Contracts\Database\SqlQueryInterface
Coretsia\Contracts\Database\QueryResultInterface
Coretsia\Contracts\Database\ConnectionInterface
Coretsia\Contracts\Database\SqlDialectInterface
```

## Acceptance scenario

When a future runtime package needs database access:

1. the runtime owner selects a driver using runtime-owned config policy;
2. the driver exposes a stable logical id through `DatabaseDriverInterface::id()`;
3. the runtime owner computes secrets-allowed `$config`;
4. the runtime owner computes no-secrets effective `$tuning`;
5. the runtime owner calls `DatabaseDriverInterface::connect($connectionName, $config, $tuning)`;
6. the produced connection exposes `name()` equal to `$connectionName`;
7. the produced connection exposes `driverId()` equal to the producing driver id;
8. callers execute explicit `SqlQueryInterface` objects, not raw SQL strings;
9. query bindings are positional `list<DbValue>`;
10. query results expose only `DbRows` using `DbValue`;
11. no float values cross the database contracts boundary;
12. SQL dialect differences are accessed through `SqlDialectInterface`;
13. no PDO or vendor database API appears in `core/contracts`;
14. no platform or integration package is required by `core/contracts`;
15. no raw SQL, bindings, credentials, or result values are emitted through unsafe diagnostics.

This acceptance scenario is policy intent.

The concrete driver registry, connection registry, connection implementation, pooling behavior, SQL compiler, schema builder, migration runner, error mapping, configuration, observability integration, and driver implementation are runtime-owned.

## Verification evidence

Contracts-level enforcement evidence for this epic includes:

```text
framework/packages/core/contracts/tests/Contract/SqlQueryShapeContractTest.php
framework/packages/core/contracts/tests/Contract/DatabaseContractsShapeContractTest.php
framework/packages/core/contracts/tests/Contract/DatabaseContractsNeverExposeFloatTypeContractTest.php
```

These tests are expected to verify:

- `SqlQueryInterface` exists;
- `SqlQueryInterface` exposes the canonical method surface;
- `SqlQuery` exists;
- `SqlQuery` is immutable;
- `SqlQuery` implements `SqlQueryInterface`;
- `SqlQuery` does not implement `__toString()`;
- `SqlQuery` preserves binding order;
- `SqlQuery` rejects float bindings;
- `SqlQuery` rejects associative bindings;
- `SqlQuery` rejects empty SQL strings;
- `SqlQuery` rejects whitespace-only SQL strings;
- `SqlQuery` allows multiline non-empty SQL strings;
- `SqlQuery` does not expose rejected raw SQL through exception messages;
- `DatabaseDriverInterface` exposes the canonical method surface;
- `ConnectionInterface` exposes the canonical method surface;
- `ConnectionInterface` exposes `name()`;
- `ConnectionInterface` exposes `driverId()`;
- `ConnectionInterface` exposes `dialect()`;
- `QueryResultInterface` exposes the canonical method surface;
- `SqlDialectInterface` exposes the canonical method surface;
- public method signatures do not depend on platform packages;
- public method signatures do not depend on integration packages;
- public method signatures do not depend on `Psr\Http\Message\*`;
- public method signatures do not depend on PDO or vendor database concretes;
- public method signatures do not expose streams, resources, iterators, generators, closures, or vendor result objects;
- database contracts do not declare DI tag constants;
- database contracts do not introduce config or artifact concepts;
- database contracts never expose `float` as an accepted value or returned result value.

Architecture gates are expected to verify that `core/contracts` does not introduce forbidden compile-time dependencies.

## Non-goals

This SSoT does not define:

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
- database health checks;
- database exception hierarchy;
- database exception mapper;
- database config roots;
- database config defaults;
- database config rules;
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

## Cross-references

- [SSoT Index](./INDEX.md)
- [Observability Naming and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [ErrorDescriptor SSoT](./error-descriptor.md)
- [DTO Policy](./dto-policy.md)
- [Config Roots Registry](./config-roots.md)
- [Tag Registry](./tags.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
