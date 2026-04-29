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

**Scope:** public interfaces / ports / small value objects that define cross-package boundaries.  
**Out of scope:** implementations, runtime wiring, filesystem scanning, platform adapters.

## Package identity (Prelude rules)

- **Path:** `framework/packages/core/contracts`
- **Package id:** `core/contracts`
- **Composer name:** `coretsia/core-contracts`
- **Namespace:** `Coretsia\Contracts\*` (PSR-4: `src/`)

Monorepo versioning is **repo-wide only** via git tags `vMAJOR.MINOR.PATCH`.  
Per-package independent versions **MUST NOT** be used.

## Dependency policy (Phase 0)

This package is **boundary-only** and MUST stay lightweight:

- **Depends on:** none (PHP only)
- **Forbidden:** `platform/*`, `integrations/*`

Contracts MUST NOT introduce platform-specific or vendor-specific types that would leak implementation details across the boundary.

## CLI ports (Epic 0.120.0)

These ports are introduced to prevent package-local cross-package interfaces.

### `Cli\Input\InputInterface`

- Exposes **raw tokens only**
- MUST NOT freeze parsing semantics (flags/options/argv rules are CLI-implementation concern)

### `Cli\Output\OutputInterface`

Output adapters MUST enforce:

- **Determinism** (no timestamps / randomness in produced output)
- **Redaction safety** (no secrets/PII leakage)

The interface intentionally does not define styling/verbosity policies.

### `Cli\Command\CommandInterface`

- Provides a stable command identifier via `name(): string`
- Executes via `run(InputInterface $input, OutputInterface $output): int`
- Returns a standard process exit code (`0` = success)

## Notes for implementers

- Implementations live outside `core/contracts` (e.g. in `platform/cli` or devtools packages).
- Contracts should remain:
  - stable,
  - minimal,
  - format-neutral,
  - easy to depend on.

## Observability

This package does not emit telemetry directly.

## Errors

This package does not define runtime error codes directly.

## Security / Redaction

This package does not process sensitive runtime payloads directly.

## References

- Roadmap condensed rules: `docs/roadmap/ROADMAP.md`
