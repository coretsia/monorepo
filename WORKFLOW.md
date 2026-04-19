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

## Rules

- `main` must stay clean.
- Do not work on `main`.
- Do not commit task work on `main`.
- Use one branch per task.
- Merge into `main` only via Pull Request.
- Prefer **Squash and merge**.
- Commit messages must be in English.

## Branch names

- `feat/<name>`
- `fix/<name>`
- `docs/<name>`
- `chore/<name>`
- `wip/<name>` — local-only work

Examples:

- `feat/footer-links-fix`
- `fix/privacy-cookie-banner`
- `docs/readme-phase0`
- `chore/release-prep`
- `wip/local-experiment`

## Daily start

```bash
git switch main          # main
git pull --ff-only       # sync
composer validate --strict  # validate
composer test            # test
```

## Start new task

```bash
git switch main              # main
git pull --ff-only           # sync
git switch -c feat/my-task   # new branch
```

## Continue task

```bash
git switch feat/my-task                  # branch
git status --short --untracked-files=all # status
```

## Check changes

```bash
git status --short --untracked-files=all # status
git diff                                 # diff
```

## Stage changes

Prefer explicit staging:

```bash
git add <explicit paths>   # stage paths
```

Or partial staging:

```bash
git add -p                 # stage hunks
```

Review staged set:

```bash
git diff --cached --name-only  # staged files
git diff --cached              # staged diff
```

## Pre-commit checks

```bash
composer validate --strict  # validate
composer spike:test         # gates
composer test               # test
```

## Commit

```bash
git status --short --untracked-files=all # status
git add <explicit paths>                  # stage
git diff --cached --name-only             # staged files
git commit -m "Add clear human commit message" # commit
```

## Post-commit check

```bash
composer spike:test:determinism  # determinism
```

If it fails:

```bash
git add <explicit paths>      # stage fix
git commit --amend --no-edit  # amend
composer spike:test:determinism # recheck
```

## First push

```bash
git push -u origin feat/my-task  # push branch
```

## Next pushes

```bash
git push  # push
```

## Pull Request

- open PR into `main`
- review final diff
- keep fixing in the same branch
- use **Squash and merge**
- write one clean final commit message

## Update working branch

Run on the working branch, not on `main`:

```bash
git fetch origin        # fetch
git rebase origin/main  # rebase
```

After conflicts:

```bash
git add <resolved files> # resolve
git rebase --continue    # continue
```

Cancel rebase:

```bash
git rebase --abort       # abort
```

## Accidental commit on `main`

Preserve commit first:

```bash
git switch -c chore/my-task  # save work
```

Restore local `main`:

```bash
git switch main           # main
git fetch origin          # fetch
git reset --hard origin/main # reset
```

## `git pull --ff-only` failed on `main`

Inspect:

```bash
git status                              # status
git log --oneline --decorate --graph -n 10 # log
```

If task work was committed on `main`:

```bash
git switch -c chore/my-task  # save work
git switch main              # main
git fetch origin             # fetch
git reset --hard origin/main # reset
```

## Direct push to `main` rejected

Use a task branch:

```bash
git switch -c chore/my-task      # new branch
git push -u origin chore/my-task # push
```

Then open or update the Pull Request.

## Fix branch after failed CI

Normal fix:

```bash
git add <explicit paths>                  # stage
git commit -m "Fix CI failure in workflow update" # commit
git push                                  # push
```

Single-commit cleanup:

```bash
git add <explicit paths>      # stage
git commit --amend --no-edit  # amend
git push --force-with-lease   # safe force push
```

Use `--force-with-lease` only on your own working branch. Never on `main`.

## Local-only work

```bash
git switch -c wip/local-experiment  # local branch
```

No `git push` = local only.

## After merge

```bash
git switch main      # main
git pull --ff-only   # sync
```

Delete local branch:

```bash
git branch -d feat/my-task  # delete local
```

Delete remote branch if it still exists:

```bash
git push origin --delete feat/my-task  # delete remote
```

## Quick flow

### Daily

```bash
git switch main                      # main
git pull --ff-only                   # sync
git switch -c feat/my-task           # new branch

git status --short --untracked-files=all  # status
git add <explicit paths>                  # stage
composer validate --strict                # validate
composer spike:test                       # gates
composer test                             # test
git commit -m "Add clear human commit message" # commit
composer spike:test:determinism           # determinism

git push -u origin feat/my-task           # push
```

### Sync

```bash
git switch feat/my-task              # branch
git fetch origin                     # fetch
git rebase origin/main               # rebase

git add <resolved files>             # resolve
git rebase --continue                # continue
```

Abort if needed:

```bash
git rebase --abort                   # abort
```

### Recovery

Accidental commit on `main`:

```bash
git switch -c chore/my-task          # save work
git switch main                      # main
git fetch origin                     # fetch
git reset --hard origin/main         # reset
```

CI fix on working branch:

```bash
git add <explicit paths>             # stage
git commit --amend --no-edit         # amend
composer spike:test:determinism      # determinism
git push --force-with-lease          # safe force push
```

After merge:

```bash
git switch main                      # main
git pull --ff-only                   # sync
git branch -d feat/my-task           # delete local
git push origin --delete feat/my-task # delete remote
```
