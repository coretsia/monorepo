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

# Future Cleanup Candidates

This document tracks cleanup candidates that were intentionally not included in active implementation epics.

Entries in this file are not accepted architecture decisions, not SSoT policy, and not committed roadmap items.

A cleanup candidate becomes actionable only when it is promoted into a numbered epic, ADR, or SSoT update.

## Rules

- Do not use this document as runtime policy.
- Do not use this document as package compliance authority.
- Do not treat listed candidates as accepted scope.
- Each candidate must explain why it was not included in the original epic.
- Each candidate must list promotion conditions before implementation.
- Prefer finishing active epics over expanding them with cleanup work.

## Future sections

Add future candidates for cleanup to the end of this document.

Each section should include:

```text
Status
Source epic
Owner area
Goal
Candidate files
```

---

## Foundation exception diagnostics shape consistency

- Status: candidate
- Source epic: `1.277.0 Foundation: Runtime Failure Safety Hardening`
- Owner area: `core/foundation`
- Priority: later cleanup
- Type: API consistency / diagnostics policy

### Goal

Define a consistent Foundation exception diagnostics shape policy after `1.277.0`.

### Candidate policy

- `errorCode(): string` remains canonical for package error codes.
- `reason(): string` is added only when the exception message is intentionally a stable reason token.
- `safeKey()` / `safePath()` / `safeId()` are added only when the exception exposes a diagnostic segment.
- `withoutPrevious()` is added only for exceptions intentionally recorded into observability boundaries.
- Strict reason registries are used only where the reason space is closed and package-owned.

### Candidate files

```text
framework/packages/core/foundation/src/Container/Exception/ContainerException.php
framework/packages/core/foundation/src/Container/Exception/NotFoundException.php
framework/packages/core/foundation/src/Id/Exception/IdGenerationFailedException.php
framework/packages/core/foundation/src/Serialization/Exception/JsonLikeNormalizationException.php
framework/packages/core/foundation/src/Time/Exception/StopwatchInvalidStateException.php
```

### Why not now

`1.277.0` is focused on direct runtime diagnostics leak boundaries.

The candidate files above are mostly exception-shape consistency work.

### Promotion condition

Promote only through a numbered epic, ADR, or SSoT update.

### Possible future epic shape

```text
1.xxx.0 Foundation: Exception Diagnostics Shape Consistency
```

Potential deliverables:

- define Foundation exception diagnostics shape rules;
- add `reason()` where message is a stable reason token;
- add `safePath()` / `safeId()` only where a diagnostic segment exists;
- clarify programmatic accessors versus diagnostics-safe messages;
- add contract tests for modified exception classes;
- update relevant SSoT and README documentation.

---
