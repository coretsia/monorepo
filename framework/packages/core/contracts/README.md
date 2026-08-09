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

# coretsia/core-contracts

`core/contracts` is the boundary-only contracts package for the Coretsia Framework monorepo.

Scope: public interfaces, ports, enums, small value objects, public context key identifiers, and contract-level shapes that define stable cross-package boundaries.

Out of scope: runtime implementations, DI wiring, filesystem scanning, platform adapters, integrations, generated artifacts, concrete transport behavior, and tooling-only behavior.

## Package identity

- Path: `framework/packages/core/contracts`
- Package id: `core/contracts`
- Composer name: `coretsia/core-contracts`
- Namespace: `Coretsia\Contracts\*` (PSR-4: `src/`)
- Kind: library

Monorepo versioning is repo-wide only via git tags `vMAJOR.MINOR.PATCH`.

The corresponding split repository is `coretsia/core-contracts` and receives the same tag for the package subtree.

Per-package independent versions MUST NOT be used.

## Dependency policy

This package is boundary-only and MUST stay lightweight.

- Depends on:
  - PHP only
- Forbidden:
  - `core/*` runtime implementations
  - `platform/*`
  - `integrations/*`
  - `devtools/*`

Contracts MUST NOT introduce concrete runtime dependencies or vendor-specific implementation types that would leak implementation details across package boundaries.

Contracts MUST remain:

- stable;
- minimal;
- format-neutral where the boundary is transport-independent;
- deterministic where they expose exported shapes;
- safe to depend on from runtime packages;
- free of implementation-owned lifecycle, discovery, wiring, and transport policy.

## Contract responsibilities

This package provides the canonical public boundaries shared across Coretsia packages.

Contract areas include:

- CLI command, input, and output boundaries;
- module identity, descriptors, manifests, and mode preset access;
- config, environment, source-tracking, and validation result shapes;
- runtime reset and UnitOfWork lifecycle ports;
- runtime hook boundaries;
- read-only runtime context access;
- public context key identifiers;
- observability ports and shapes;
- health and profiling boundaries;
- error descriptor boundaries;
- routing and HTTP application ports;
- validation ports;
- filesystem ports;
- database and migration ports;
- rate-limit ports;
- mail ports;
- secrets ports;
- transport-neutral Worker task-source and task-settlement ports.

`core/contracts` owns boundary vocabulary and public cross-package shapes only.

Concrete behavior belongs to implementation owner packages.

## Ownership boundaries

Importing a contract does not transfer ownership of the corresponding runtime behavior.

Implementation packages MAY depend on `core/contracts`.

`core/contracts` MUST NOT depend back on implementation packages.

Typical implementation owners include:

- `core/foundation`;
- `core/kernel`;
- `platform/cli`;
- `platform/http`;
- `platform/worker`;
- integration packages that implement a contracts-owned port.

Contract-level vocabulary MUST NOT become an alternate implementation layer.

In particular, `core/contracts` does not own:

- dependency injection wiring;
- service discovery;
- runtime service tags;
- config defaults;
- config validation execution;
- lifecycle execution;
- reset discovery;
- hook discovery;
- filesystem or Composer scanning;
- transport adapters;
- HTTP response construction;
- queue acknowledgement, retry, requeue, or dead-letter policy;
- observability exporters or backends;
- generated artifact production;
- tooling policy enforcement.

Implementation owners MUST enforce their own lifecycle, write, propagation, transport, safety, and redaction rules at their respective boundaries.

## CLI ports

CLI contracts prevent package-local cross-package interface drift while keeping CLI parsing, rendering, and terminal behavior implementation-owned.

### `Cli\Input\InputInterface`

`Cli\Input\InputInterface` exposes raw input tokens only.

It MUST NOT freeze:

- flag parsing semantics;
- option parsing semantics;
- argv policy;
- command-line validation policy;
- terminal behavior.

Those responsibilities belong to CLI implementation packages.

### `Cli\Output\OutputInterface`

Output implementations MUST enforce their own:

- deterministic output behavior;
- redaction safety;
- secret and PII protection.

The contracts-owned output boundary intentionally does not define:

- styling;
- verbosity;
- formatting policy;
- terminal capability detection;
- concrete output backends.

### `Cli\Command\CommandInterface`

A command contract:

- exposes a stable command identifier through `name(): string`;
- executes through `run(InputInterface $input, OutputInterface $output): int`;
- returns a standard process exit code.

Command discovery, command catalog construction, argument parsing, output rendering, and binary dispatch remain implementation-owned.

## Runtime contracts

Runtime contracts are format-neutral and transport-neutral.

Core runtime boundaries include:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface
Coretsia\Contracts\Runtime\UnitOfWorkHandle
Coretsia\Contracts\Runtime\ResetInterface
Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface
Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface
```

### UnitOfWork handle

`UnitOfWorkHandle` is a contracts-owned opaque low-level lifecycle handle.

It is not a Kernel context or result schema object.

It may expose only the normalized safe context array through:

```php
UnitOfWorkHandle::context()
```

A runtime implementation MAY associate private lifecycle state with the exact handle object identity.

That private state is not part of the contracts-owned handle shape and MUST NOT be exposed through `UnitOfWorkHandle::context()`.

The handle MUST NOT expose:

- Stopwatch tokens;
- wall-clock timestamps;
- transport objects;
- service instances;
- mutable runtime state;
- Kernel-owned runtime internals.

The contracts package does not own:

- UnitOfWork execution;
- lifecycle timing;
- hook discovery;
- reset discovery;
- service tags;
- provider wiring;
- runtime driver implementation.

Runtime discovery and execution remain implementation-owned.

## Worker task-source contracts

The public Worker task-source SPI is transport-neutral and lives under:

```text
Coretsia\Contracts\Worker
```

The canonical contracts include:

```text
WorkerTaskType
WorkerTaskSourceInterface
WorkerTaskSourceContextInterface
WorkerTaskInterface
```

`WorkerTaskSourceInterface` represents blocking or cancellable acquisition of real work.

`WorkerTaskInterface` represents one acquired unit of work together with transport-owned success or failure settlement.

The contracts package does not own:

- task-source discovery;
- DI tags;
- Worker child orchestration;
- Worker process supervision;
- queue clients;
- HTTP runtime APIs;
- broker semantics;
- response emission;
- acknowledgement policy;
- retry policy;
- requeue policy;
- dead-letter policy.

Transport and runtime implementations remain outside `core/contracts`.

Canonical task-source runtime semantics are defined by:

```text
docs/ssot/worker-task-sources.md
```

## Context contracts

Context contracts define the public vocabulary and read-only access boundary for runtime context data.

The canonical public context key registry is:

```text
Coretsia\Contracts\Context\ContextKeys
```

`ContextKeys` defines stable key identifiers only.

Importing `ContextKeys` does not grant write ownership over the corresponding values.

The read-only context access port is:

```text
Coretsia\Contracts\Context\ContextAccessorInterface
```

Runtime readers SHOULD depend on `ContextAccessorInterface` when they need access to current context values.

Runtime readers MAY import `ContextKeys` to avoid raw-string key drift.

The contracts package does not own:

- context storage;
- mutable context writes;
- context write validation;
- context reset behavior;
- lifecycle writes;
- context propagation;
- logging;
- tracing;
- context export policy.

Known implementation owners include:

- `core/foundation` for `ContextStore`, `ContextBag`, `ContextStorePolicy`, and accessor binding;
- `core/kernel` for base UnitOfWork context writes;
- platform packages for transport- or runtime-specific context enrichment.

Context vocabulary ownership and runtime write ownership are separate concerns.

## Config and environment contracts

Config and environment contracts define stable ports and safe shapes for:

- merged configuration access;
- environment-derived values;
- config source tracking;
- config validation results;
- config validation violations;
- declarative ruleset boundaries.

The contracts package does not implement:

- config loading;
- config merging;
- environment loading;
- ruleset discovery;
- validation execution;
- package config defaults;
- generated config artifacts.

Package `config/rules.php` files are implementation-owned by their package owners and MUST remain declarative data.

Contract interfaces MUST NOT acquire implementation-specific config loading or filesystem discovery behavior.

## Module and manifest contracts

Module-related contracts provide cross-package vocabulary for:

- module identity;
- module descriptors;
- module manifests;
- mode preset access.

The contracts package defines public shapes only.

Module discovery, manifest loading, dependency resolution, mode resolution, graph policy, provider discovery, and generated module artifacts remain implementation-owned.

Canonical module and manifest semantics are defined by the corresponding monorepo SSoT documents.

## Routing and HTTP application contracts

Routing and HTTP application contracts define cross-package ports without owning concrete transport integration.

The contracts package MUST NOT become the implementation owner for:

- PSR-7 request or response construction;
- PSR-15 middleware execution;
- HTTP status-code selection;
- concrete router implementations;
- server adapters;
- runtime driver adapters.

Concrete HTTP behavior remains owned by platform and integration packages.

## Validation and infrastructure ports

The package also provides contract boundaries for reusable capabilities including:

- validation;
- filesystem access;
- database access;
- migrations;
- rate limiting;
- mail;
- secrets.

These contracts define public ports and cross-package shapes only.

Concrete clients, adapters, drivers, persistence behavior, external service integrations, and operational policy remain implementation-owned.

## Observability

This package does not emit telemetry directly.

It defines observability-related public boundaries and safe contract shapes only.

Contracts MAY define ports for capabilities such as:

- tracing;
- metrics;
- error reporting;
- profiling;
- health reporting;
- correlation access.

Contracts MUST NOT require concrete:

- logger implementations;
- tracer implementations;
- metrics backends;
- exporters;
- HTTP clients;
- database clients;
- queue clients;
- vendor-specific observability SDKs.

Observability contracts MUST remain usable without selecting a concrete backend.

Telemetry production, lifecycle instrumentation, exporter selection, backend integration, sampling, buffering, and transport remain implementation-owned.

## Errors

This package does not own runtime error mapping or transport-specific error rendering.

It may define contract-level error descriptor boundaries and safe diagnostic shapes required for cross-package interoperability.

Higher-level implementation packages own:

- exception normalization;
- runtime failure mapping;
- error reporting;
- HTTP error responses;
- CLI error rendering;
- transport-specific error representation.

Contract-owned diagnostic shapes MUST be deterministic and safe by construction.

They MUST NOT require exposing implementation-specific exception internals or raw runtime payloads.

## Security / Redaction

`core/contracts` does not process sensitive runtime payloads directly.

Public contracts MUST nevertheless be designed so that safe implementations do not require leaking sensitive data across package boundaries.

Contracts that expose diagnostic, context, observability, validation, or exported shapes MUST NOT require storing or exposing:

- raw secrets;
- raw environment values;
- credentials;
- passwords;
- private keys;
- bearer tokens;
- session ids;
- cookies;
- Authorization headers;
- raw request payloads;
- raw response payloads;
- raw queue payloads;
- raw SQL;
- private customer data;
- direct PII;
- absolute local paths;
- host-specific bytes;
- transport objects;
- runtime service objects;
- mutable runtime storage.

Context contracts expose key identifiers and read-only access only.

They MUST NOT require exposing mutable context storage or implementation-owned context lifecycle state.

Diagnostic contracts SHOULD prefer bounded safe values such as:

```text
stable enums
stable reason tokens
safe ids
counts
lengths
hashes
bounded status/category values
```

Unsafe runtime values SHOULD be omitted, normalized, redacted, or represented through owner-defined safe diagnostics before crossing a contracts-owned boundary.

Concrete redaction policy remains an implementation-owner responsibility.

## References

- [Coretsia monorepo](https://github.com/coretsia/monorepo)
- [Contracts package source](https://github.com/coretsia/monorepo/tree/main/framework/packages/core/contracts)
- [Packaging strategy](https://github.com/coretsia/monorepo/blob/main/docs/architecture/PACKAGING.md)
- [Modules and manifests SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/modules-and-manifests.md)
- [Config and env SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/config-and-env.md)
- [UoW and Reset Contracts SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/uow-and-reset-contracts.md)
- [Context Keys SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/context-keys.md)
- [Observability and Errors SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/observability-and-errors.md)
- [Routing and HttpApp Contracts SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/routing-and-http-app-contracts.md)
- [Validation Contracts SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/validation-contracts.md)
- [Filesystem Contracts SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/filesystem-contracts.md)
- [Database Contracts SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/database-contracts.md)
- [Rate Limit Contracts SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/rate-limit-contracts.md)
- [Mail Contracts SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/mail-contracts.md)
- [Secrets Contracts SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/secrets-contracts.md)
- [Worker Task Sources SSoT](https://github.com/coretsia/monorepo/blob/main/docs/ssot/worker-task-sources.md)
