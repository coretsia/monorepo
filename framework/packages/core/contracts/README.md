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

`core/contracts` is the **contracts-only** package for the Coretsia Framework monorepo.

**Scope:** public interfaces, ports, enums, small value objects, and contract-level shapes that define cross-package boundaries.

**Out of scope:** runtime implementations, DI wiring, filesystem scanning, platform adapters, integrations, generated artifacts, and tooling-only behavior.

## Package identity

- **Path:** `framework/packages/core/contracts`
- **Package id:** `core/contracts`
- **Composer name:** `coretsia/core-contracts`
- **Namespace:** `Coretsia\Contracts\*` (PSR-4: `src/`)
- **Kind:** library

Monorepo versioning is **repo-wide only** via git tags `vMAJOR.MINOR.PATCH`.

Per-package independent versions **MUST NOT** be used.

## Dependency policy

This package is **boundary-only** and MUST stay lightweight.

- **Depends on:** PHP only
- **Forbidden:**
  - `platform/*`
  - `integrations/*`
  - `devtools/*`

Contracts MUST NOT introduce concrete runtime dependencies or vendor-specific implementation types that would leak implementation details across package boundaries.

Contracts MUST remain:

- stable;
- minimal;
- format-neutral;
- deterministic where they expose exported shapes;
- safe to depend on from runtime packages.

## Contract areas

This package contains contracts for cross-package capabilities such as:

- CLI command/input/output boundaries;
- module identity, descriptors, manifests, and mode preset access;
- config, env, source tracking, and validation result shapes;
- runtime reset and unit-of-work hooks;
- observability, health, profiling, and error descriptor boundaries;
- routing and HTTP application ports;
- validation ports;
- filesystem ports;
- database and migrations ports;
- rate limit ports;
- mail ports;
- secrets ports.

Implementations live outside `core/contracts`.

## CLI ports

CLI contracts prevent package-local cross-package interfaces and keep CLI behavior implementation-owned.

### `Cli\Input\InputInterface`

- Exposes raw input tokens only.
- MUST NOT freeze parsing semantics.
- Flags, options, argv rules, and command-line policy are owned by CLI implementations.

### `Cli\Output\OutputInterface`

Output adapters MUST enforce:

- deterministic output behavior;
- redaction safety;
- no secrets or PII leakage.

The interface intentionally does not define styling, verbosity, formatting, or terminal capability policy.

### `Cli\Command\CommandInterface`

- Provides a stable command identifier via `name(): string`.
- Executes via `run(InputInterface $input, OutputInterface $output): int`.
- Returns a standard process exit code.

## Runtime contracts

Runtime contracts are format-neutral and transport-neutral.

Examples include:

- `Coretsia\Contracts\Runtime\ResetInterface`
- `Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface`
- `Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface`

The contracts package does not own DI tags, reset discovery, hook discovery, lifecycle execution, config defaults, config rules, or provider wiring.

Runtime discovery and execution are owned by runtime implementation packages.

## Config and env contracts

Config and env contracts define stable ports and safe shapes for:

- merged config access;
- env-derived values;
- config source tracking;
- config validation results;
- config validation violations;
- declarative ruleset boundaries.

The contracts package does not implement config loading, config merging, env loading, ruleset discovery, validation execution, or generated config artifacts.

Package `config/rules.php` files are implementation-owned by their package owners and MUST remain declarative data.

## Observability and errors contracts

Observability and error contracts define boundaries and safe shapes only.

They MUST NOT require concrete logger, tracer, metrics, HTTP, database, queue, or vendor-specific clients.

Diagnostic shapes MUST NOT expose:

- raw payloads;
- secrets;
- credentials;
- tokens;
- cookies;
- authorization headers;
- private customer data;
- absolute local paths;
- host-specific bytes.

## Notes for implementers

Implementations belong in owner packages such as:

- `core/foundation`
- `core/kernel`
- `platform/cli`
- `platform/http`
- future platform or integration packages

Implementation packages MAY depend on `core/contracts`.

`core/contracts` MUST NOT depend back on implementation packages.

## Observability

This package does not emit telemetry directly.

It defines observability-related contract boundaries and safe shapes only.

## Errors

This package does not define runtime error mapping behavior directly.

Error mapping, exception normalization, and transport-specific error responses are owned by higher layers.

## Security / Redaction

This package does not process sensitive runtime payloads directly.

Contracts that expose diagnostic or exported shapes MUST be safe by construction and MUST NOT require storing raw secrets, raw env values, raw request data, raw response data, credentials, tokens, cookies, authorization headers, private customer data, or absolute local paths.

## References

- `docs/ssot/modules-and-manifests.md`
- `docs/ssot/config-and-env.md`
- `docs/ssot/uow-and-reset-contracts.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/routing-and-http-app-contracts.md`
- `docs/ssot/validation-contracts.md`
- `docs/ssot/filesystem-contracts.md`
- `docs/ssot/database-contracts.md`
- `docs/ssot/rate-limit-contracts.md`
- `docs/ssot/mail-contracts.md`
- `docs/ssot/secrets-contracts.md`
- `docs/roadmap/ROADMAP.md`
