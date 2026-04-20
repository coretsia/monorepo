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

Branch format:

```text
<type>/<branch-name>
```

Recommended branch types:

- `feat`  = new functionality or new behavior
- `fix`   = bug fix, broken behavior, CI fix, workflow fix, security warning fix
- `docs`  = documentation only
- `chore` = maintenance, service changes, dependency refresh, config updates, routine non-feature work
- `wip`   = local or temporary unfinished work that is not ready to publish

Examples:

- `feat/http-route-cache`
- `fix/ci-workflow-permissions`
- `docs/workflow-cheatsheet`
- `chore/phpunit-refresh`
- `wip/local-experiment`

## Start a new working branch

Run once at the start of a task:

```bash
git switch main                            # switch to local main
git pull --ff-only                         # sync local main with origin/main
git switch -c <type>/<branch-name>         # create and open a new working branch
```

## Return to an existing working branch

```bash
git switch <type>/<branch-name>            # return to an existing working branch
```

If you need to inspect local branches:

```bash
git branch                                 # list local branches
git branch --show-current                  # show current branch
```

## Check current changes

```bash
git status --short --untracked-files=all   # show changed and untracked files
git diff                                   # show unstaged diff
```

## Stage changes

Prefer explicit staging:

```bash
git add <explicit paths>                   # stage only selected files
```

For partial staging:

```bash
git add -p                                 # stage selected hunks
```

Review staged content before commit:

```bash
git diff --cached --name-only              # show staged file list
git diff --cached                          # show full staged diff
```

## Pre-commit checks

Run before each commit:

```bash
composer validate:all --strict             # validate all composer manifests
composer spike:test                        # run spike gates and spike tests
composer test                              # run main test suite
```

## Create a commit

Run for each new commit in the current working branch:

```bash
git status --short --untracked-files=all   # inspect current changes
git add <explicit paths>                   # stage only selected files
git diff --cached --name-only              # verify staged file list
git commit -m "Your commit message"        # create local commit
```

## Post-commit determinism check

Run after each commit:

```bash
composer spike:test:determinism            # run determinism check on committed state
```

If the check fails:

```bash
git add <explicit paths>                   # stage the fix
git commit --amend --no-edit               # update the last commit without changing its message
composer spike:test:determinism            # rerun determinism check
```

## Sync working branch with main

Run only from the working branch, not from `main`:

```bash
git fetch origin                           # update remote refs locally
git rebase origin/main                     # rebase current working branch onto latest origin/main
```

If conflicts appear:

```bash
git add <resolved files>                   # stage manually resolved files
git rebase --continue                      # continue rebase
```

If you need to cancel the rebase:

```bash
git rebase --abort                         # abort rebase and return to pre-rebase state
```

## Push working branch

First push of a new working branch:

```bash
git push -u origin <type>/<branch-name>    # push branch and set upstream tracking
```

Next pushes in the same branch:

```bash
git push                                   # push new local commits to tracked remote branch
```

## Pull Request flow

After the branch is ready:

- open a Pull Request into `main`
- review the final diff carefully
- keep fixing in the same working branch if needed
- wait for green checks
- use **Squash and merge**
- write one clean final commit message for `main`

## If you accidentally committed on main

Preserve the commit first:

```bash
git switch -c chore/my-task                # save accidental main work into a new branch
```

Restore local `main`:

```bash
git switch main                            # return to main
git fetch origin                           # refresh origin/main
git reset --hard origin/main               # reset local main to remote main
```

## If `git pull --ff-only` fails on main

Inspect the current state:

```bash
git status                                 # inspect working tree state
git log --oneline --decorate --graph -n 10 # inspect recent history
```

If task work was committed on `main`:

```bash
git switch -c chore/my-task                # preserve the work in a new branch
git switch main                            # return to main
git fetch origin                           # refresh origin/main
git reset --hard origin/main               # restore clean local main
```

## If direct push to main is rejected

Use a task branch instead:

```bash
git switch -c chore/my-task                # create a working branch from current state
git push -u origin chore/my-task           # publish the working branch
```

Then open or update the Pull Request.

## Fix a branch after failed CI

Normal fix flow:

```bash
git add <explicit paths>                   # stage the fix
git commit -m "Fix CI failure in <context>" # create a follow-up commit
git push                                   # push the fix
```

Single-commit cleanup flow:

```bash
git add <explicit paths>                   # stage the fix
git commit --amend --no-edit               # update the latest commit
git push --force-with-lease                # safely replace remote branch history
```

Use `--force-with-lease` only on your own working branch. Never use it on `main`.

## Local-only work

```bash
git switch -c wip/local-experiment         # create a local-only experimental branch
```

If you do not run `git push`, the branch remains local only.

## After Pull Request merge

Return to `main` and sync it:

```bash
git switch main                            # return to main
git pull --ff-only                         # fetch merged changes from origin/main
```

Delete the local branch:

```bash
git branch -d <type>/<branch-name>         # delete local branch if already merged
```

Delete the remote branch if it still exists:

```bash
git push origin --delete <type>/<branch-name>  # delete remote branch on GitHub
```

If the local branch does not delete with `-d`, but you know it is no longer needed:

```bash
git branch -D <type>/<branch-name>         # force delete local branch
```

## Quick flow

### Daily

```bash
git switch main                            # switch to local main
git pull --ff-only                         # sync local main with origin/main
git switch -c <type>/<branch-name>         # create and open working branch

git status --short --untracked-files=all   # inspect changed files
git add <explicit paths>                   # stage selected files
git diff --cached --name-only              # verify staged file list

composer validate:all --strict             # validate all composer manifests
composer spike:test                        # run spike gates and spike tests
composer test                              # run main test suite

git commit -m "Your commit message"        # create local commit
composer spike:test:determinism            # run determinism check

git push -u origin <type>/<branch-name>    # first push of the working branch
```

### Next commit in the same branch

```bash
git switch <type>/<branch-name>            # return to working branch if needed

git status --short --untracked-files=all   # inspect changed files
git add <explicit paths>                   # stage selected files
git diff --cached --name-only              # verify staged file list

composer validate:all --strict             # validate all composer manifests
composer spike:test                        # run spike gates and spike tests
composer test                              # run main test suite

git commit -m "Your commit message"        # create next local commit
composer spike:test:determinism            # run determinism check

git push                                   # push next commit to tracked remote branch
```

### Sync

```bash
git switch <type>/<branch-name>            # make sure you are on the working branch
git fetch origin                           # refresh remote refs
git rebase origin/main                     # rebase onto latest origin/main

git add <resolved files>                   # stage resolved conflicts if any
git rebase --continue                      # continue rebase
```

Abort if needed:

```bash
git rebase --abort                         # cancel rebase
```

### Recovery

Accidental commit on `main`:

```bash
git switch -c chore/my-task                # preserve work in a new branch
git switch main                            # return to main
git fetch origin                           # refresh origin/main
git reset --hard origin/main               # restore clean local main
```

Fix failed determinism or failed CI on a working branch:

```bash
git add <explicit paths>                   # stage the fix
git commit --amend --no-edit               # update the latest commit
composer spike:test:determinism            # rerun determinism
git push --force-with-lease                # safely update remote working branch
```

After merge:

```bash
git switch main                            # return to main
git pull --ff-only                         # sync merged changes
git branch -d <type>/<branch-name>         # delete local branch
git push origin --delete <type>/<branch-name>  # delete remote branch if still present
```
