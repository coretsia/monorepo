## Summary

<!--
Describe what changed and why.
Explain the problem, the implemented solution, and the expected outcome.
Keep the description concrete and focused.
-->

## Type of change

<!-- Select all that apply. -->

- [ ] Bug fix
- [ ] Feature
- [ ] Refactor
- [ ] Performance improvement
- [ ] Security hardening
- [ ] Documentation
- [ ] Architecture / SSoT
- [ ] Tooling / gates / CI
- [ ] Package scaffold / compliance
- [ ] Dependency update
- [ ] Test-only change

## Related references

<!--
Provide relevant references when applicable.

Examples:
- Closes #...
- Related to #...
- Discussion: #...
- ADR: docs/adr/...
- SSoT: docs/ssot/...
- Roadmap: docs/roadmap/...
-->

## Affected areas

<!-- Select all that apply. -->

- [ ] `packages/framework/**`
- [ ] `packages/applications/skeleton/**`
- [ ] `packages/core/**`
- [ ] `packages/platform/**`
- [ ] `packages/integrations/**`
- [ ] `packages/presets/**`
- [ ] `packages/enterprise/**`
- [ ] `packages/devtools/**`
- [ ] `tools/**`
- [ ] `.github/**`
- [ ] `docs/**`
- [ ] Root Composer / workspace / repository metadata

## Architecture and compliance

<!--
Check only requirements that are applicable and satisfied.
Explain unchecked requirements in the reviewer notes.
Any intentional policy exception must be explicitly justified and permitted
by the applicable project policy.
-->

- [ ] Package ownership, dependency boundaries, and architectural layering are preserved.
- [ ] Public contracts, SPIs, and documented APIs are respected.
- [ ] Existing canonical owners and mechanisms are reused without introducing parallel implementations.
- [ ] Explicit dependency injection, module composition, and runtime lifecycle contracts are preserved.
- [ ] Production runtime code does not import, read, execute, or depend on tooling code or tooling-generated architecture artifacts.
- [ ] Affected SSoT documents, ADRs, guides, and public API references are consistent with the canonical implementation and their applicable documentation policies.
- [ ] `docs/guides/commands.md` is updated when command contracts or catalog entries change.
- [ ] Deterministic serialization, ordering, artifacts, and output policies are preserved.
- [ ] Diagnostics follow the canonical redaction and disclosure policies without exposing secrets, sensitive data, or unauthorized runtime information.
- [ ] Tooling gates follow the canonical ConsoleOutput policy where applicable.
- [ ] New or changed gates are exposed through the canonical Composer entrypoints where required.
- [ ] Composer manifests, dependency constraints, and lockfiles remain consistent.
- [ ] Lockfile changes are intentional, necessary, and consistent with the dependency management policy.
- [ ] New or changed behavior is covered by appropriate tests, including regression coverage for bug fixes.

## Verification

<!--
Run all required and applicable commands from the repository root.

Check only commands that were executed successfully and whose results
remain valid for the changes included in this PR.

For generators and synchronization commands, ensure the resulting changes
are included in this PR and the corresponding verification checks pass.

Repeat affected checks after further changes.
Explain skipped, failed, or non-applicable checks in the verification notes.
Include only concise, sanitized verification output.
-->

### Core verification

- [ ] `composer sync:check`
- [ ] `composer validate`
- [ ] `composer security`
- [ ] `composer gates`
- [ ] `composer dto:gate`
- [ ] `composer arch`
- [ ] `composer quality`
- [ ] `composer test`
- [ ] `composer test:slow`
- [ ] `composer lock:check`

### Architecture and generated artifacts

<!--
Run the applicable generators when package manifests, package structure,
dependency rules, architecture policies, release-line metadata, or other
authoritative generator inputs change.

Generate affected artifacts after their authoritative inputs have been
updated and synchronized.
Verify the resulting artifacts through the corresponding checks.
-->

- [ ] `composer arch:package-index:generate`
- [ ] `composer arch:installation-catalog:generate`
- [ ] `composer arch:deptrac:generate`

### Package scaffold and compliance

<!--
Applicable when packages are created or modified in ways that affect
canonical scaffolding, compliance policies, legal files, required package
documentation, configuration scaffolding, or package structure.

Run synchronization only when required.
Verify scaffold consistency after all applicable changes.
-->

- [ ] `composer package-scaffold:sync`
- [ ] `composer package-scaffold:check`

### Release-line and publishing

<!--
Applicable when release-line metadata, internal package constraints,
managed Composer repositories, or split publishing configuration change,
or when packages are prepared for publication.

Run the applicable synchronization commands before regenerating dependent
artifacts. Regenerate the consumer installation catalog when its inputs change.
Complete the corresponding release-line, catalog, and publishing checks.
-->

- [ ] `composer sync:repos`
- [ ] `composer release-line:workspace:sync`
- [ ] `composer release-line:public-constraints:sync`
- [ ] `composer release-line:workspace:check`
- [ ] `composer release-line:public-constraints:check`
- [ ] `composer arch:installation-catalog:check`
- [ ] `composer package-publish-safety:gate`

### Final pre-commit verification

<!--
Verify the staged changes before creating the commit.
Ensure only intended changes are included.
-->

- [ ] `git diff --cached --check`
- [ ] `composer lock:check`
- [ ] Staged changes have been reviewed.
- [ ] No unintended unstaged or untracked files remain.

### Final pre-push verification

<!--
Run the final verification against the exact committed revision intended
for publication.

If verification requires further changes, commit or amend those changes
and repeat the affected checks before pushing.
-->

- [ ] `composer security`
- [ ] `composer ci`
- [ ] Working tree is clean after final verification.

### Verification output

```text
# Concise, sanitized verification results.
```

### Verification notes

<!--
Document additional checks, skipped or non-applicable commands,
failed checks, environment limitations, and known verification gaps.
-->

## Determinism and generated artifacts

<!--
Select exactly one option.
Report incomplete or failed verification in the verification notes.
-->

- [ ] No generated files are changed and all applicable artifact checks pass.
- [ ] Generated files are intentionally updated and all applicable artifact and rerun-no-diff checks pass.
- [ ] Generated artifact verification is incomplete or failed.

### Affected generated files

<!--
List affected generated files and explain why regeneration was required.
Use N/A if no generated files are affected.
-->

## Compatibility and public API

<!--
Select exactly one option.
If this PR includes both backward-compatible and breaking changes,
select the breaking changes option.
-->

- [ ] No public API or documented behavior changes.
- [ ] Backward-compatible public API or documented behavior changes.
- [ ] Breaking public API or documented behavior changes.

### Compatibility notes

<!--
Describe changes affecting public contracts, commands, configuration,
environment variables, package dependencies, module composition,
artifact formats, or documented runtime behavior.

Explain the compatibility impact and include migration instructions
only when required by the applicable release policy.
-->

## Reviewer notes

<!--
Highlight architectural decisions, security considerations,
intentional trade-offs, affected runtime boundaries, or areas requiring
particular review attention.
-->
