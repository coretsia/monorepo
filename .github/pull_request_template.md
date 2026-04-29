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

## Summary

<!--
Describe the change in concrete terms.
Avoid broad narrative. State what changed and why.
-->

## Type of change

- [ ] Bug fix
- [ ] Feature
- [ ] Refactor
- [ ] Documentation
- [ ] Tooling / gate / CI rail
- [ ] Architecture / SSoT / roadmap policy
- [ ] Package scaffold / compliance
- [ ] Test-only change

## Linked issue / roadmap / SSoT

<!--
Required when applicable.

Examples:
- Closes #...
- Epic: docs/roadmap/...
- SSoT: docs/ssot/...
- Command catalog: docs/guides/commands.md
-->

## Affected areas

- [ ] `framework/packages/core/**`
- [ ] `framework/packages/platform/**`
- [ ] `framework/packages/integrations/**`
- [ ] `framework/packages/presets/**`
- [ ] `framework/packages/enterprise/**`
- [ ] `framework/packages/devtools/**`
- [ ] `framework/tools/**`
- [ ] `skeleton/**`
- [ ] `.github/**`
- [ ] `docs/**`
- [ ] root Composer / workspace / repository metadata

## Policy checklist

- [ ] I updated SSoT docs when changing commands, config roots, tags, artifacts, observability policy, public API, or architecture rules.
- [ ] I updated `docs/guides/commands.md` when adding, changing, removing, or reclassifying a command.
- [ ] I preserved deterministic output policy for gates and generators.
- [ ] Diagnostics do not include secrets, raw config payloads, absolute paths, environment values, tokens, request bodies, headers, cookies, or private data.
- [ ] Runtime packages do not import, read, execute, or reference tooling code or tooling-generated architecture artifacts.
- [ ] Tooling gates use the canonical ConsoleOutput policy where applicable.
- [ ] New or changed gates are separately invokable through repo-root Composer scripts when required.
- [ ] Generated artifacts remain rerun-no-diff and have canonical envelope/header shape where applicable.
- [ ] No lockfile was changed unless the change intentionally updates dependencies.

## Verification

<!--
Paste the commands you ran. Keep output summarized and sanitized.
Use N/A only when a command is genuinely not applicable.
-->

- [ ] `composer sync:check`
- [ ] `composer validate:all`
- [ ] `composer gates`
- [ ] `composer dto:gate`
- [ ] `composer arch`
- [ ] `composer quality`
- [ ] `composer spike:test`
- [ ] `composer test`
- [ ] `composer lock:check`

### Verification output

```text
# Paste concise sanitized output here.
```

## Determinism / generated files

- [ ] This PR does not change generated files.
- [ ] This PR changes generated files and rerun-no-diff checks pass.
- [ ] This PR changes generated files intentionally; affected outputs are listed below.

Affected generated outputs:

```text
# Example:
# docs/generated/GENERATED_STRUCTURE.md
# docs/generated/GENERATED_STRUCTURE_TREE.md
# framework/tools/testing/deptrac.yaml
```

## Backward compatibility

- [ ] No public API or documented behavior change.
- [ ] Public API or documented behavior changed; migration notes are included.
- [ ] Internal-only change.
- [ ] Not applicable.

## Migration notes

<!--
Required if behavior, commands, config, package layout, public API, or SSoT policy changed.
-->

## Reviewer notes

<!--
Call out risky files, expected review focus, or intentional trade-offs.
-->
