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

# WORKFLOW

Daily development workflow for Coretsia Framework (Monorepo).

## Branch policy

- `main` must stay clean and releasable.
- Do not develop directly on `main`.
- Every task must be done in a separate branch.
- Merge into `main` via Pull Request.
- Prefer **Squash and merge** so `main` keeps one clean commit per completed task.

## Branch naming

Use short, predictable branch names:

- `feat/<name>`
- `fix/<name>`
- `docs/<name>`
- `chore/<name>`

Examples:

- `feat/footer-links-fix`
- `fix/privacy-cookie-banner`
- `docs/readme-phase0`
- `chore/release-prep`

## Daily start

Before starting work:

```bash
git switch main
git pull --ff-only
composer validate --strict
composer test
```

Purpose:

- switch to the main branch
- pull the latest changes without creating merge commits
- validate Composer manifests
- verify the project is green before starting new work

## Start a new task

Create a branch from the latest `main`:

```bash
git switch main
git pull --ff-only
git switch -c feat/my-task
```

## During development

Check changed files:

```bash
git status --short
git diff
```

Run validation and tests before commit:

```bash
composer validate --strict
composer test
```

## Commit changes

Stage and commit:

```bash
git add .
git commit -m "Add clear human commit message"
```

Commit messages must be written in English.

## Push branch

First push:

```bash
git push -u origin feat/my-task
```

Next pushes:

```bash
git push
```

## Pull Request

After the task is ready:

- open a Pull Request from your working branch into `main`
- review the final diff carefully
- use **Squash and merge**
- write one clean final commit message for `main`

This keeps `main` readable even if the working branch contains many small or temporary commits.

## Keep branch up to date

If `main` moved forward while you were working:

```bash
git fetch origin
git rebase origin/main
```

Resolve conflicts if needed, then continue.

## After merge

Return to `main`:

```bash
git switch main
git pull --ff-only
```

Delete the local branch:

```bash
git branch -d feat/my-task
```

Delete the remote branch if needed:

```bash
git push origin --delete feat/my-task
```

## Local-only experimental work

If work is not ready to publish, keep it local:

```bash
git switch -c wip/local-experiment
```

If you do not run `git push`, the branch remains visible only on your machine.

## Practical rule

- `main` = stable public history
- working branch = active task
- many small commits in a working branch are acceptable
- `main` should receive one clean squash commit per finished task
