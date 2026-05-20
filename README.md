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

<div align="center">

# Coretsia Framework (Monorepo)

**Coretsia** [kɔˈrɛtsjɑ] / [ko-RET-si-ya] — from the Ukrainian word **“серцевина”** (*core, foundation*)

**A modular, deterministic-by-default PHP framework monorepo with strict compile-time boundaries and SSoT-driven development**

</div>

## Status

Coretsia Framework is in **active development**.

Roadmap status:

- **Prelude**: implemented
- **Phase 0 — Spikes and prototypes**: implemented
- **Phase 1 — Core**: active development
- **Phase 2 — Mode Infrastructure & CLI**: planned
- **Phase 3 — RELEASE: micro**: planned
- **Phase 4 — RELEASE: express**: planned
- **Phase 5 — RELEASE: hybrid**: planned
- **Phase 6+ — RELEASE: enterprise (extensions)**: planned
- **Stable production release**: not available yet

Current public implementation baseline:

- deterministic tooling and gates
- managed Composer workspace/repository synchronization
- publishing rails policy
- public split-package publishing baseline
- canonical CLI ports
- prod-safe CLI base
- Phase 0 devtools command pack
- Phase 1 contracts baseline
- DTO policy and package compliance rails
- architecture and quality verification rails

Authoritative planning and invariants:

- [Roadmap (canonical)](docs/roadmap/ROADMAP.md)
- [Single Source of Truth (invariants/shapes/policies)](docs/ssot/INDEX.md)
- [Security policy](SECURITY.md)

## Repository layout (canonical)

- `framework/` — framework meta-package + all framework packages
  - packages: `framework/packages/<layer>/<slug>/`
  - layers: `core|platform|integrations|enterprise|devtools|presets`
- `skeleton/` — local workspace app sandbox
  - fixtures, entrypoints, E2E tests, runtime caches (`skeleton/var/**`)
- `docs/` — documentation
  - `docs/roadmap/**` — task-first roadmap (phases/epics)
  - `docs/ssot/**` — Single Source of Truth (invariants, shapes, policies)
  - `docs/architecture/**`, `docs/ops/**` — non-SSoT guides (must link to SSoT for truth)
- `framework/tools/` — tooling, gates, CI rails, spikes (Phase 0 / Prelude)

## Canonical rules (selected highlights)

### Monorepo packaging law (MUST)

- Package path: `framework/packages/<layer>/<slug>/`
- Package id: `<layer>/<slug>`
- Composer name: `coretsia/<layer>-<slug>`
- Monorepo-wide versioning via git tags `vMAJOR.MINOR.PATCH` (no per-package versions)
- [Canonical packaging strategy (single-choice)](docs/architecture/PACKAGING.md)

### Canonical entrypoints (MUST)

Run commands from the **repo root**. Canonical scripts:

```bash
composer setup
composer test
composer ci
```

### Managed Composer repositories (MUST NOT edit manually)

`repositories` blocks in root/framework/skeleton composer.json files are managed only through the canonical repo-root commands:

```bash
composer sync:check
composer sync:repos
```

Manual edits are forbidden by policy and enforced by pre-commit/CI drift checks.

See:

- [Command catalog](docs/guides/commands.md)
- [Git hooks + managed repositories policy](docs/guides/git-hooks.md)

### Lock determinism (MUST)

- lock files are committed for root/framework/skeleton
- CI uses `composer install` and fails on lock drift
- drift check for managed repositories runs **before** installs

### SSoT config shape invariant (cemented)

- `config/<name>.php` returns a **subtree** (no wrapper repeating the root key).
  - Example: `config/foundation.php` returns `['container' => ...]`, not `['foundation' => ...]`.
- Runtime reads from global config under that root key (e.g. `foundation.container.*`).

### Runtime discovery (MUST)

- Runtime module discovery uses **Composer metadata only**.
- Runtime discovery **MUST NOT** do filesystem scanning.
- Tooling package indexes **MUST NOT** be used as runtime input.

### Spikes boundary (MUST)

- The only spikes root is `framework/tools/spikes/**`.
- Spikes must not import runtime packages (`core/*`, `platform/*`, `integrations/*`) nor path-import `framework/packages/**/src/**`.
- Single exception: `coretsia/devtools-internal-toolkit` (tooling-only) used only via Composer autoload.

## Requirements

- PHP: **>= 8.4**
- Composer: **2.x**

## Quick start (monorepo dev)

Run everything from the **repository root**. Canonical entrypoints:

```bash
composer setup
composer test
composer ci
```

- `composer setup` enables `.githooks/` (sets `core.hooksPath`) and ensures managed Composer repositories policy.
- `composer test` runs the baseline test suite.
- `composer ci` runs the CI-style pipeline locally.

## Documentation index

### Guides

- [Quickstart (clean clone → working baseline)](docs/guides/quickstart.md)
- [Command catalog (canonical)](docs/guides/commands.md)
- [Developer onboarding checklist](docs/guides/onboarding.md)
- [Git hooks + managed repositories policy](docs/guides/git-hooks.md)
- [Dependency graph (conceptual; truth is in SSoT)](docs/guides/dependency-graph.md)
- [Releasing (GitHub Release + Packagist)](docs/guides/releasing.md)
- [Packagist split publishing](docs/guides/packagist-split-publishing-guide.md)

### References

- [Packaging strategy (canonical)](docs/architecture/PACKAGING.md)
- [Repository structure](docs/architecture/STRUCTURE.md)
- [Roadmap](docs/roadmap/ROADMAP.md)
- [Canonical condensed rules (normative)](docs/roadmap/ROADMAP.md)
- [SSoT index](docs/ssot/INDEX.md)
- [Branding spec](docs/architecture/BRANDING.md)
- [Website architecture draft](docs/architecture/WEBSITE.md)

## Community RFCs

### Website Design RFC

Coretsia is currently collecting open-source ideas for the future official website design direction.

The discussion is focused on translating the canonical Coretsia branding specification into a practical website concept, including the homepage, documentation section, news/blog section, and community pages.

- [Website architecture draft](docs/architecture/WEBSITE.md)
- [Branding specification](docs/architecture/BRANDING.md)
- [Website design RFC](https://github.com/coretsia/monorepo/discussions/51)

## License

Licensed under the Apache License, Version 2.0. See [LICENSE](LICENSE).  
See [NOTICE](NOTICE) for attribution and third-party notices (if applicable).
